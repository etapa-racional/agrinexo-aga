<?php
// OpenAI-compatible model proxy; it owns the wire format and the key.

declare(strict_types=1);

set_time_limit(300);

header('Content-Type: application/json');

$opxConfig = opxLoadConfig();

opxDispatch(opxResolveRoute(), $opxConfig);


function opxLoadConfig(): array
{
    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        opxFail('opx is not configured: copy config.sample.php to config.php', 500);
    }

    $config = require $configPath;
    if (!is_array($config)) {
        opxFail('opx config.php must return an array', 500);
    }

    return $config + [
        'endpoint' => '',
        'upstream_key' => '',
        'agent_token' => '',
        'allowed_models' => [],
        'extra_headers' => [],
        'default_temperature' => 0.2,
        'upstream_timeout' => 110,
        'structured_output' => false,
        'log_payloads' => 'off',
        'log_max_chars' => 60000,
    ];
}


function opxResolveRoute(): string
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

    if ($segment === '' || $segment === 'index.php' || $segment === 'opx') {
        return '';
    }

    return strtolower($segment);
}

function opxDispatch(string $route, array $config): void
{
    switch ($route) {
        case 'health':
            opxHandleHealth($config);
            break;
        case 'generate':
            opxHandleGenerate($config);
            break;
        default:
            opxFail('Unknown route: ' . ($route === '' ? '(none)' : $route), 404);
    }
}


function opxSend(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// The error.message shape is what the caller surfaces; keep it.
function opxFail(string $message, int $status): void
{
    opxSend(['error' => ['message' => $message]], $status);
}

function opxReadJsonBody(): array
{
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        opxFail('Request body must be a JSON object', 400);
    }
    return $body;
}


function opxTokenOk(array $config): bool
{
    $expected = (string) $config['agent_token'];
    if ($expected === '') {
        return false;
    }

    $presented = (string) ($_SERVER['HTTP_X_AGENT_TOKEN'] ?? '');
    return $presented !== '' && hash_equals($expected, $presented);
}

function opxRequireToken(array $config): void
{
    if ((string) $config['agent_token'] === '') {
        opxLog('WARNING', 'config.no_agent_token');
        opxFail('opx is misconfigured: agent_token is empty', 500);
    }

    if (!opxTokenOk($config)) {
        opxLog('WARNING', 'unauthorized', ['remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '']);
        opxFail('Unauthorized', 401);
    }
}


function opxLog(string $severity, string $message, array $fields = []): void
{
    $entry = ['severity' => $severity, 'message' => 'opx.' . $message];
    foreach ($fields as $key => $value) {
        if ($value !== null) {
            $entry[$key] = $value;
        }
    }
    error_log(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function opxPayloadLoggingEnabled(array $config): bool
{
    $setting = strtolower(trim((string) $config['log_payloads']));
    if ($setting === 'on') {
        return true;
    }
    if ($setting === 'on-request') {
        return (string) ($_SERVER['HTTP_X_AI_DEBUG'] ?? '') === '1';
    }
    return false;
}

function opxClip($value, int $maxChars): string
{
    $text = is_string($value)
        ? $value
        : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (strlen($text) <= $maxChars) {
        return $text;
    }
    return substr($text, 0, $maxChars) . sprintf('...[truncated %d chars]', strlen($text) - $maxChars);
}

// Diagnostic logging must never fail a request that would succeed.
function opxLogPayload(array $config, string $message, string $requestId, $value): void
{
    try {
        opxLog('DEBUG', $message, [
            'request_id' => $requestId,
            'payload' => opxClip($value, (int) $config['log_max_chars']),
        ]);
    } catch (Throwable $exception) {
        opxLog('WARNING', $message . '.unloggable', [
            'request_id' => $requestId,
            'error' => $exception->getMessage(),
        ]);
    }
}


function opxHandleHealth(array $config): void
{
    // Reachability is answered to anyone; the configuration in effect is not.
    if (!opxTokenOk($config)) {
        opxSend(['ok' => true]);
    }

    $endpointHost = (string) parse_url((string) $config['endpoint'], PHP_URL_HOST);

    opxSend([
        'ok' => true,
        'role' => 'chat',
        'endpoint_host' => $endpointHost,
        'upstream_key_set' => (string) $config['upstream_key'] !== '',
        'allowed_models' => array_values((array) $config['allowed_models']),
        'structured_output' => (bool) $config['structured_output'],
        'log_payloads' => (string) $config['log_payloads'],
        'php_version' => PHP_VERSION,
        'curl_available' => function_exists('curl_init'),
    ]);
}

function opxHandleGenerate(array $config): void
{
    opxRequireToken($config);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        opxFail('generate requires POST', 405);
    }

    $body = opxReadJsonBody();

    $requestId = (string) ($body['request_id'] ?? '');
    if ($requestId === '') {
        $requestId = bin2hex(random_bytes(8));
    }

    $model = trim((string) ($body['model'] ?? ''));
    if ($model === '') {
        opxFail('model is required', 400);
    }

    // Second line of defence behind the shared key.
    $allowedModels = array_values((array) $config['allowed_models']);
    if (count($allowedModels) > 0 && !in_array($model, $allowedModels, true)) {
        opxLog('WARNING', 'model_not_allowed', ['request_id' => $requestId, 'model' => $model]);
        opxFail('Model is not allowed on this opx instance: ' . $model, 400);
    }

    $messages = $body['messages'] ?? [];
    if (!is_array($messages) || count($messages) === 0) {
        opxFail('messages must be a non-empty array', 400);
    }

    $systemPrompt = trim((string) ($body['system_prompt'] ?? ''));
    $toolDefinitions = is_array($body['tools'] ?? null) ? $body['tools'] : [];
    $jsonOnly = (bool) ($body['json_only'] ?? false);
    $temperature = isset($body['temperature'])
        ? (float) $body['temperature']
        : (float) $config['default_temperature'];

    $debug = opxPayloadLoggingEnabled($config);
    if ($debug) {
        opxLogPayload($config, 'generate.request', $requestId, $body);
    }

    $startedAt = microtime(true);

    try {
        $normalized = opxCallUpstream(
            $config,
            $model,
            $messages,
            $systemPrompt,
            $toolDefinitions,
            $jsonOnly,
            $temperature,
            is_array($body['response_schema'] ?? null) ? $body['response_schema'] : null,
            $requestId,
            $debug
        );
    } catch (Throwable $exception) {
        opxLog('ERROR', 'generate.failed', [
            'request_id' => $requestId,
            'model' => $model,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error_type' => get_class($exception),
            'error' => $exception->getMessage(),
        ]);
        opxFail(get_class($exception) . ': ' . $exception->getMessage(), 502);
    }

    opxLog('INFO', 'generate.ok', [
        'request_id' => $requestId,
        'model' => $model,
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'message_count' => count($messages),
        'tool_count' => count($toolDefinitions),
        'json_only' => $jsonOnly,
        'tool_calls' => count($normalized['tool_calls']),
        'content_len' => strlen($normalized['content']),
        'reasoning_len' => strlen($normalized['reasoning']),
        'payload_logging' => $debug,
    ]);

    opxSend($normalized);
}


function opxCallUpstream(
    array $config,
    string $model,
    array $messages,
    string $systemPrompt,
    array $toolDefinitions,
    bool $jsonOnly,
    float $temperature,
    ?array $responseSchema,
    string $requestId,
    bool $debug
): array {
    $payload = [
        'model' => $model,
        'messages' => opxConvertMessages($messages, $systemPrompt),
        'temperature' => $temperature,
        'stream' => false,
    ];

    if (count($toolDefinitions) > 0) {
        $payload['tools'] = opxBuildTools($toolDefinitions);
        $payload['tool_choice'] = 'auto';
    }

    if ($jsonOnly) {
        // json_object is the portable floor for OpenAI-compatible servers.
        if ($responseSchema !== null && !empty($config['structured_output'])) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'response',
                    'strict' => true,
                    'schema' => $responseSchema,
                ],
            ];
        } else {
            $payload['response_format'] = ['type' => 'json_object'];
        }
    }

    $headers = ['Content-Type: application/json'];
    if ((string) $config['upstream_key'] !== '') {
        $headers[] = 'Authorization: Bearer ' . $config['upstream_key'];
    }
    // Some providers read HTTP-Referer and X-Title for attribution.
    foreach ((array) $config['extra_headers'] as $name => $value) {
        $headers[] = $name . ': ' . $value;
    }

    $url = rtrim((string) $config['endpoint'], '/') . '/chat/completions';
    $timeout = (int) $config['upstream_timeout'];

    try {
        $response = opxSendJsonRequest($url, $headers, $payload, $timeout);
    } catch (RuntimeException $exception) {
        // Some Ollama models reject tools; retry once without them.
        if (isset($payload['tools']) && opxIsToolUnsupportedError($exception->getMessage())) {
            opxLog('INFO', 'generate.tools_unsupported_retry', [
                'request_id' => $requestId,
                'model' => $model,
            ]);
            unset($payload['tools'], $payload['tool_choice']);
            $response = opxSendJsonRequest($url, $headers, $payload, $timeout);
        } else {
            throw $exception;
        }
    }

    if ($debug) {
        opxLogPayload($config, 'generate.response', $requestId, $response);
    }

    $message = $response['choices'][0]['message'] ?? null;
    if (!is_array($message)) {
        throw new RuntimeException('AI provider returned an unexpected response');
    }

    return opxNormalizeMessage($message);
}

function opxIsToolUnsupportedError(string $message): bool
{
    $normalizedMessage = strtolower($message);
    return strpos($normalizedMessage, 'does not support tools') !== false
        || strpos($normalizedMessage, 'tools are not supported') !== false;
}

function opxNormalizeMessage(array $message): array
{
    $toolCalls = [];
    foreach ($message['tool_calls'] ?? [] as $toolCall) {
        $toolCalls[] = [
            'id' => $toolCall['id'] ?? ('ai-tool-' . uniqid()),
            'name' => $toolCall['function']['name'] ?? '',
            'arguments' => opxDecodeJsonToArray($toolCall['function']['arguments'] ?? '{}'),
        ];
    }

    return [
        'content' => trim((string) ($message['content'] ?? '')),
        // Thinking models return deliberation in a separate field.
        'reasoning' => trim((string) ($message['reasoning'] ?? $message['reasoning_content'] ?? '')),
        'tool_calls' => array_values(array_filter($toolCalls, static function ($toolCall) {
            return $toolCall['name'] !== '';
        })),
    ];
}


function opxBuildContentParts(string $text, array $image): array
{
    $parts = [];
    if ($text !== '') {
        $parts[] = ['type' => 'text', 'text' => $text];
    }
    $parts[] = [
        'type' => 'image_url',
        'image_url' => ['url' => 'data:' . $image['mime_type'] . ';base64,' . $image['data']],
    ];
    return $parts;
}

function opxConvertMessages(array $messages, string $systemPrompt): array
{
    $converted = [];
    if ($systemPrompt !== '') {
        $converted[] = ['role' => 'system', 'content' => $systemPrompt];
    }

    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        if (($message['role'] ?? '') === 'tool') {
            $converted[] = [
                'role' => 'tool',
                'tool_call_id' => $message['tool_call_id'] ?? '',
                'name' => $message['name'] ?? '',
                'content' => $message['content'] ?? '',
            ];
            continue;
        }

        $convertedMessage = [
            'role' => $message['role'] ?? 'user',
            'content' => !empty($message['image'])
                ? opxBuildContentParts((string) ($message['content'] ?? ''), $message['image'])
                : ($message['content'] ?? ''),
        ];

        if (($message['role'] ?? '') === 'assistant' && !empty($message['tool_calls'])) {
            $convertedMessage['tool_calls'] = array_map(static function ($toolCall) {
                return [
                    'id' => $toolCall['id'] ?? ('ai-tool-' . uniqid()),
                    'type' => 'function',
                    'function' => [
                        // json_encode renders an empty PHP array as [], not {}.
                        'name' => $toolCall['name'] ?? '',
                        'arguments' => opxJsonEncode(
                            empty($toolCall['arguments']) ? new stdClass() : $toolCall['arguments']
                        ),
                    ],
                ];
            }, $message['tool_calls']);
        }

        $converted[] = $convertedMessage;
    }

    return $converted;
}

// An empty properties map must stay a map, not become [].
function opxNormalizeSchema($node)
{
    if (!is_array($node)) {
        return $node;
    }

    $out = [];
    foreach ($node as $key => $value) {
        if ($key === 'properties') {
            $out[$key] = (is_array($value) && count($value) === 0)
                ? new stdClass()
                : array_map('opxNormalizeSchema', (array) $value);
        } elseif ($key === 'items') {
            $out[$key] = opxNormalizeSchema($value);
        } else {
            $out[$key] = $value;
        }
    }

    return $out;
}

function opxBuildTools(array $toolDefinitions): array
{
    return array_map(static function ($tool) {
        $schema = $tool['input_schema'] ?? ['type' => 'object', 'properties' => new stdClass()];

        return [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'] ?? '',
                'description' => $tool['description'] ?? '',
                'parameters' => opxNormalizeSchema($schema),
            ],
        ];
    }, $toolDefinitions);
}


function opxSendJsonRequest(string $url, array $headers, array $payload, int $timeout): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL extension is required for AI requests');
    }

    $body = opxJsonEncode($payload);
    $curlHandle = curl_init($url);
    curl_setopt_array($curlHandle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $responseBody = curl_exec($curlHandle);
    $httpCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curlHandle);
    curl_close($curlHandle);

    if ($responseBody === false) {
        throw new RuntimeException('HTTP request failed: ' . $curlError);
    }

    $decodedResponse = json_decode((string) $responseBody, true);
    if (!is_array($decodedResponse)) {
        throw new RuntimeException('AI provider returned a non-JSON response');
    }

    if ($httpCode >= 400) {
        throw new RuntimeException(opxExtractErrorMessage($decodedResponse, 'AI provider request failed'));
    }

    return $decodedResponse;
}

function opxExtractErrorMessage(array $response, string $defaultMessage): string
{
    foreach (['error.message', 'message', 'errorMessage'] as $path) {
        $value = opxReadNestedValue($response, $path);
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }
    }
    return $defaultMessage;
}

function opxReadNestedValue(array $data, string $path)
{
    $segments = explode('.', $path);
    $current = $data;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return null;
        }
        $current = $current[$segment];
    }

    return $current;
}

function opxJsonEncode($value): string
{
    $encodedValue = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encodedValue === false) {
        throw new RuntimeException('Failed to encode JSON payload');
    }
    return $encodedValue;
}

function opxDecodeJsonToArray($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decodedValue = json_decode($value, true);
    return is_array($decodedValue) ? $decodedValue : [];
}
