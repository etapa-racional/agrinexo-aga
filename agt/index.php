<?php

// Config is required relatively: __DIR__ would run agt-pt on agt's config.

declare(strict_types=1);

// agt includes nothing from api/: the dependency is the HTTP contract.
if (!file_exists('config.php')) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'agt holds shared code only. Call an instance instead: /agt-en or /agt-pt.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$agtConfig = require 'config.php';
$geminiConfig = require 'config-ai-google-gemini.php';
$openAiCompatibleConfig = require 'config-ai-openai-compatible.php';

require __DIR__ . '/agent.php';
require __DIR__ . '/tools.php';


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function agtRespond(bool $success, string $message = '', array $data = []): void
{
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function agtAssertConsent(?array $claims): void
{
    if ($claims === null) {
        return;
    }
    if (!array_key_exists('ai', $claims) || !$claims['ai']) {
        agtFail(403, 'Agrinexo AGA AI is off for this account.');
    }
}

function agtFail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function agtResolveRoute(): string
{
    $pathInfo = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
    if ($pathInfo !== '') {
        return strtolower($pathInfo);
    }

    $queryRoute = trim((string) ($_GET['route'] ?? ''), '/');
    if ($queryRoute !== '') {
        return strtolower($queryRoute);
    }

    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $segment = basename(rtrim($path, '/'));

    if ($segment === '' || $segment === 'index.php' || $segment === basename(getcwd())) {
        return '';
    }

    return strtolower($segment);
}

function agtRequestBody(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? $decoded : [];
}

// agt derives identity for logging only; api/ enforces access per call.

function agtBase64UrlDecode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder !== 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return (string) base64_decode(strtr($value, '-_', '+/'), true);
}

function agtJwtSignatureToDer(string $signature): ?string
{
    if (strlen($signature) !== 64) {
        return null;
    }

    $integers = '';
    foreach (str_split($signature, 32) as $value) {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        }
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        $integers .= "\x02" . chr(strlen($value)) . $value;
    }

    return "\x30" . chr(strlen($integers)) . $integers;
}

function agtVerifyJwt(?string $header, string $publicKey): ?array
{
    if ($header === null || !preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return null;
    }

    $parts = explode('.', $matches[1]);
    if (count($parts) !== 3) {
        return null;
    }
    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

    $head = json_decode(agtBase64UrlDecode($encodedHeader), true);
    if (!is_array($head) || ($head['alg'] ?? '') !== 'ES256') {
        return null;
    }

    $derSignature = agtJwtSignatureToDer(agtBase64UrlDecode($encodedSignature));
    if ($derSignature === null || openssl_verify(
        $encodedHeader . '.' . $encodedPayload,
        $derSignature,
        $publicKey,
        OPENSSL_ALGO_SHA256
    ) !== 1) {
        return null;
    }

    $payload = json_decode(agtBase64UrlDecode($encodedPayload), true);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}


$agtRoute = agtResolveRoute();
$agtAuthHeader = null;
foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
    if (!empty($_SERVER[$key])) {
        $agtAuthHeader = (string) $_SERVER[$key];
        break;
    }
}
if ($agtAuthHeader === null && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $agtAuthHeader = (string) $value;
            break;
        }
    }
}

$agtClaims = agtVerifyJwt($agtAuthHeader, (string) ($agtConfig['jwt_public_key'] ?? ''));
$agtUserId = isset($agtClaims['user_id']) ? ((int) $agtClaims['user_id'] - 10000) : null;

$agtCtx = [
    'krd' => trim((string) ($_GET['krd'] ?? '')),
    'authorization' => $agtAuthHeader,
    'user_id' => $agtUserId,
    'api_base' => rtrim((string) ($agtConfig['eco_api_url'] ?? ''), '/'),
    'timeout' => (int) ($agtConfig['api_timeout'] ?? 60),
    'log_file' => (string) ($agtConfig['log_file'] ?? ''),
];

// Name is load-bearing: runAiToolLoop() reads $GLOBALS['agt_ctx'].
$GLOBALS['agt_ctx'] = $agtCtx;


switch ($agtRoute) {

    case 'health':
        agtRespond(true, 'ok', [
            'status' => 'ok',
            'service' => 'agt',
            'eco_api_url' => $agtCtx['api_base'],
        ]);
        break;

    case 'status':
        if ($agtCtx['krd'] === '') {
            agtFail(400, 'Missing krd parameter');
        }
        agtHandleStatus($agtCtx, $openAiCompatibleConfig, $geminiConfig, $agtConfig);
        break;

    case 'chat':
        if ($agtCtx['krd'] === '') {
            agtFail(400, 'Missing krd parameter');
        }
        agtAssertConsent($agtClaims);
        agtHandleChat(agtRequestBody(), $agtCtx, $openAiCompatibleConfig, $geminiConfig, $agtConfig);
        break;

    case 'fill':
        if ($agtCtx['krd'] === '') {
            agtFail(400, 'Missing krd parameter');
        }
        agtAssertConsent($agtClaims);
        agtHandleFill(agtRequestBody(), $agtCtx, $openAiCompatibleConfig, $geminiConfig, $agtConfig);
        break;

    default:
        agtFail(404, 'Unknown route: ' . ($agtRoute === '' ? '(none)' : $agtRoute));
}
