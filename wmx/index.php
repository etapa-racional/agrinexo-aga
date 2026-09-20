<?php

// Every tool carries the CALLER's Authorization header; wmx adds no rights.

declare(strict_types=1);

$wmxConfig = require 'config.php';

require __DIR__ . '/tool-validators.php';
require __DIR__ . '/tools.php';


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function wmxRespond(bool $success, string $message = '', array $data = []): void
{
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function wmxFail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function wmxAssertConsent(?array $claims): void
{
    if ($claims === null) {
        return;
    }
    if (!array_key_exists('webmcp', $claims) || !$claims['webmcp']) {
        wmxFail(403, 'WebMCP is off for this account.');
    }
}

function wmxResolveRoute(): string
{
    $pathInfo = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
    if ($pathInfo !== '') {
        return strtolower($pathInfo);
    }

    $queryRoute = trim((string) ($_GET['route'] ?? ''), '/');
    if ($queryRoute !== '') {
        return strtolower($queryRoute);
    }

    $queryAction = trim((string) ($_GET['action'] ?? ''), '/');
    if ($queryAction !== '') {
        return strtolower($queryAction);
    }

    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $segment = basename(rtrim($path, '/'));

    if ($segment === '' || $segment === 'index.php' || $segment === basename(getcwd())) {
        return '';
    }

    return strtolower($segment);
}

function wmxRequestBody(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? $decoded : [];
}

// Token verified only to derive a log id; api/ enforces access per call.

function wmxBase64UrlDecode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder !== 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return (string) base64_decode(strtr($value, '-_', '+/'), true);
}

function wmxJwtSignatureToDer(string $signature): ?string
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

function wmxVerifyJwt(?string $header, string $publicKey): ?array
{
    if ($header === null || !preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return null;
    }

    $parts = explode('.', $matches[1]);
    if (count($parts) !== 3) {
        return null;
    }
    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

    $head = json_decode(wmxBase64UrlDecode($encodedHeader), true);
    if (!is_array($head) || ($head['alg'] ?? '') !== 'ES256') {
        return null;
    }

    $derSignature = wmxJwtSignatureToDer(wmxBase64UrlDecode($encodedSignature));
    if ($derSignature === null || openssl_verify(
        $encodedHeader . '.' . $encodedPayload,
        $derSignature,
        $publicKey,
        OPENSSL_ALGO_SHA256
    ) !== 1) {
        return null;
    }

    $payload = json_decode(wmxBase64UrlDecode($encodedPayload), true);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}


$wmxRoute = wmxResolveRoute();

$wmxAuthHeader = null;
foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
    if (!empty($_SERVER[$key])) {
        $wmxAuthHeader = (string) $_SERVER[$key];
        break;
    }
}

$wmxClaims = wmxVerifyJwt($wmxAuthHeader, (string) ($wmxConfig['jwt_public_key'] ?? ''));

$wmxUserId = null;
if ($wmxClaims !== null && isset($wmxClaims['user_id'])) {
    $wmxUserId = (int) $wmxClaims['user_id'] - 10000;
}

$wmxCtx = [
    'krd' => trim((string) ($_GET['krd'] ?? '')),
    'authorization' => $wmxAuthHeader,
    'user_id' => $wmxUserId,
    'api_base' => rtrim((string) ($wmxConfig['eco_api_url'] ?? ''), '/'),
    'timeout' => (int) ($wmxConfig['api_timeout'] ?? 60),
    'log_file' => (string) ($wmxConfig['log_file'] ?? ''),
];

// tools.php reads context from here, so in-process callers set it once.
$GLOBALS['wmx_ctx'] = $wmxCtx;


switch ($wmxRoute) {
    case 'health':
        wmxRespond(true, 'ok', [
            'status' => 'ok',
            'service' => 'wmx',
            'eco_api_url' => $wmxCtx['api_base'],
            'tools' => count(wmxToolDefinitions()),
        ]);
        break;

    // Static registry: answers any tenant, so never gate authorisation on it.
    case 'tools':
        if ($wmxCtx['krd'] === '') {
            wmxFail(400, 'Missing krd parameter');
        }

        wmxAssertConsent($wmxClaims);

        $wmxTools = array_map(static function (array $tool): array {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['input_schema'],
                'read_only' => strpos((string) $tool['name'], 'propose_') !== 0,
            ];
        }, wmxToolDefinitions());

        wmxRespond(true, 'Tool registry published', ['tools' => $wmxTools]);
        break;

    case 'tool':
        if ($wmxCtx['krd'] === '') {
            wmxFail(400, 'Missing krd parameter');
        }

        wmxAssertConsent($wmxClaims);

        $wmxName = trim((string) ($_GET['name'] ?? ''));
        if ($wmxName === '') {
            wmxFail(400, 'Missing tool name');
        }

        if (!in_array($wmxName, array_column(wmxToolDefinitions(), 'name'), true)) {
            wmxFail(404, 'Unknown tool: ' . $wmxName);
        }

        wmxLog('wmx_tool | ' . $wmxName, $wmxCtx);
        $wmxResult = wmxExecuteTool($wmxName, wmxRequestBody(), $wmxCtx);

        wmxRespond(true, 'Tool executed', ['tool' => $wmxName, 'result' => $wmxResult]);
        break;

    default:
        wmxFail(404, 'Unknown route: ' . ($wmxRoute === '' ? '(none)' : $wmxRoute));
}
