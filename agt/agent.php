<?php

// Prompts and wire shapes are pinned by a regression capture: changes break.

// Tool-call round trips per turn before the loop demands a plain answer.
const AI_MAX_TOOL_ROUNDTRIPS = 5;

// AI_FILL_SKIP_REASONING: 3 calls with prose reasoning, 2 without.
const AI_FILL_SKIP_REASONING = true;

// Europe/Lisbon: php.ini is UTC, so date() is a day behind after midnight.
const AGT_TIMEZONE = 'Europe/Lisbon';


function agtLog(string $message, ?array $ctx = null): void
{
    $ctx = $ctx ?? ($GLOBALS['agt_ctx'] ?? []);

    $logFile = (string) ($ctx['log_file'] ?? '');
    if ($logFile === '') {
        return;
    }

    $prefix = '';
    if (!empty($ctx['krd'])) {
        $prefix .= ' | krd=' . $ctx['krd'];
    }
    if (isset($ctx['user_id']) && $ctx['user_id'] !== null) {
        $prefix .= ' | uid=' . $ctx['user_id'];
    }

    @file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . $prefix . ' | ' . $message . "\n",
        FILE_APPEND
    );
}


function agtHandleStatus(array $ctx, array $openAiCompatibleConfig, array $geminiConfig, array $agtConfig): void
{
    // A bad krd would look healthy until the first tool call.
    try {
        agtApiRequest('inputs.php', ['action' => 'read', 'limit' => 1], $ctx);
    } catch (Throwable $exception) {
        agtFail(400, 'Backend unreachable or krd invalid: ' . $exception->getMessage());
    }

    $defaultInstance = defaultOpenAiCompatibleInstance($openAiCompatibleConfig);
    $availableModels = availableModels($openAiCompatibleConfig, $geminiConfig);

    agtRespond(true, 'AI status retrieved successfully', [
        'status' => 'ok',
        'model' => $availableModels[0] ?? '',
        'endpoint' => (string) ($openAiCompatibleConfig['instances'][$defaultInstance]['agent_url'] ?? ''),
        'embedding_provider' => 'epx',
        'available_models' => $availableModels,
        'configured' => true,
        'tools_enabled' => true,
        'tools' => array_column(
            $ctx['user_id'] === null ? wmxToolDefinitions() : agtToolDefinitions(),
            'name'
        ),
        'eco_api_url' => $ctx['api_base'],
    ]);
}

function agtHandleChat(array $payload, array $ctx, array $openAiCompatibleConfig, array $geminiConfig, array $agtConfig): void
{
    if (!isset($payload['messages']) || !is_array($payload['messages'])) {
        agtFail(400, 'Messages are required');
    }

    $messages = sanitizeChatMessages($payload['messages']);
    if (count($messages) === 0) {
        agtFail(400, 'At least one valid message is required');
    }

    [$provider, $providerModel, $instance] = resolveProviderAndModel($payload['model'] ?? null);
    assertProviderConfigured($provider, $instance);

    // Cleared before the loop: a stale proposal would offer an old card.
    $GLOBALS['agt_proposal'] = null;

    try {
        $loop = runAiToolLoop(
            $provider,
            $providerModel,
            $messages,
            buildAiSystemPrompt(),
            agtToolDefinitions(),
            AI_MAX_TOOL_ROUNDTRIPS,
            false,
            $instance
        );

        agtRespond(true, 'AI response generated successfully', [
            'provider' => $provider === 'opx' ? $instance : $provider,
            'model' => $providerModel,
            'role' => 'assistant',
            'content' => trim((string) ($loop['reply']['content'] ?? '')),
            'tools_used' => array_values(array_unique(array_column($loop['tool_trace'], 'name'))),
            'tool_trace' => $loop['tool_trace'],

            'proposal' => $GLOBALS['agt_proposal'] ?? null,
        ]);
    } catch (Throwable $exception) {
        agtFail(400, 'AI request failed: ' . $exception->getMessage());
    }
}

// Two-phase: tools first, then re-ask with tools off and strict schema.
function agtHandleFill(array $payload, array $ctx, array $openAiCompatibleConfig, array $geminiConfig, array $agtConfig): void
{
    if (!isset($payload['messages']) || !is_array($payload['messages'])) {
        agtFail(400, 'Messages are required');
    }

    $messages = sanitizeChatMessages($payload['messages']);
    if (count($messages) === 0) {
        agtFail(400, 'At least one valid message is required');
    }

    [$provider, $providerModel, $instance] = resolveProviderAndModel($payload['model'] ?? null);
    assertProviderConfigured($provider, $instance);

    $schema = resolveFillSchema($_GET['schema'] ?? null);

    try {
        $loop = runAiToolLoop(
            $provider,
            $providerModel,
            $messages,
            buildAiFillToolSystemPrompt($schema),
            agtToolDefinitions(),
            AI_MAX_TOOL_ROUNDTRIPS,
            AI_FILL_SKIP_REASONING,
            $instance
        );

        // Gemini rejects a generateContent whose last turn is a model turn.
        $schemaMessages = $loop['messages'];
        $schemaMessages[] = [
            'role' => 'user',
            'content' => fillSchemaInstruction($schema),
        ];

        $providerReply = callAiProvider(
            $provider,
            $providerModel,
            $schemaMessages,
            buildAiFillSchemaSystemPrompt($schema),
            [],
            true,
            $instance,
            buildFillSchema($schema)
        );
        $parameters = validateAiFillResponse($providerReply['content'] ?? '', $schema);

        agtRespond(true, 'AI parameters generated successfully', [
            'provider' => $provider === 'opx' ? $instance : $provider,
            'model' => $providerModel,
            'schema' => $schema,
            'content' => json_encode($parameters, JSON_UNESCAPED_SLASHES),
            'tools_used' => array_values(array_unique(array_column($loop['tool_trace'], 'name'))),
            'tool_trace' => $loop['tool_trace'],
        ]);
    } catch (Throwable $exception) {
        agtFail(400, 'AI fill request failed: ' . $exception->getMessage());
    }
}


function agtIndexById(array $rows, string $key = 'id'): array
{
    $indexed = [];
    foreach ($rows as $row) {
        if (isset($row[$key])) {
            $indexed[(int) $row[$key]] = $row;
        }
    }
    return $indexed;
}

function validateOperationFill(array $decoded): array
{
    $ctx = $GLOBALS['agt_ctx'] ?? [];
    $unresolved = [];

    $cropsById = [];
    $operationTypesById = [];
    $inputsById = [];

    try {
        $crops = agtDispatchTool('list_crops', ['limit' => 500], $ctx);
        $cropsById = agtIndexById($crops['crops'] ?? [], 'crop_id');

        $operationTypes = agtDispatchTool('list_operation_types', ['limit' => 500], $ctx);
        $operationTypesById = agtIndexById($operationTypes['operation_types'] ?? []);

        $inputs = agtDispatchTool('list_inputs', ['limit' => 500], $ctx);
        $inputsById = agtIndexById($inputs['inputs'] ?? []);
    } catch (Throwable $exception) {
        $unresolved[] = 'could not verify ids against the farm: ' . $exception->getMessage();
    }

    // Two id spaces: a field id and a crop_id can both arrive here.
    $cropsByFieldId = [];
    foreach ($cropsById as $cropId => $crop) {
        $cropsByFieldId[(int) ($crop['field_id'] ?? 0)][] = $cropId;
    }

    $cropIds = [];
    $resolvedCrops = [];
    foreach ((array) ($decoded['crop_ids'] ?? []) as $rawId) {
        $id = (int) $rawId;
        if (isset($cropsById[$id])) {
            $cropIds[] = $id;
            $resolvedCrops[] = [
                'crop_id' => $id,
                'field_name' => $cropsById[$id]['field_name'],
                'production_name' => $cropsById[$id]['production_name'],
            ];
        } elseif (isset($cropsByFieldId[$id])) {
            // Never substitute: guessing an unasked id is the bug.
            $field = $cropsById[$cropsByFieldId[$id][0]]['field_name'] ?? ('field ' . $id);
            $unresolved[] = $rawId . ' is the field id for "' . $field . '", not a crop_id — '
                . 'the crops on it are ' . implode(', ', $cropsByFieldId[$id]);
        } else {
            $unresolved[] = 'crop_id ' . $rawId . ' does not exist on this farm';
        }
    }
    $cropIds = array_values(array_unique($cropIds));
    if (count($cropIds) === 0) {
        $unresolved[] = 'no valid crop_id — the operations form requires at least one';
    }

    $operationTypeId = (int) ($decoded['operation_type_id'] ?? 0);
    $operationTypeName = null;
    if (isset($operationTypesById[$operationTypeId])) {
        $operationTypeName = $operationTypesById[$operationTypeId]['name'];
    } else {
        $unresolved[] = 'operation_type_id ' . ($decoded['operation_type_id'] ?? 'missing') . ' is not an operation type on this farm';
        $operationTypeId = null;
    }

    $operationDate = agtNormalizeDate($decoded['operation_date'] ?? null);
    if ($operationDate === null) {
        $unresolved[] = 'operation_date "' . ($decoded['operation_date'] ?? '') . '" is not a valid YYYY-MM-DD date';
    }

    $inputRows = [];
    foreach ((array) ($decoded['inputs'] ?? []) as $entry) {
        if (!is_array($entry)) {
            $unresolved[] = 'an inputs entry was not an object';
            continue;
        }

        $inputId = (int) ($entry['input_id'] ?? 0);
        if (!isset($inputsById[$inputId])) {
            $unresolved[] = 'input_id ' . ($entry['input_id'] ?? 'missing') . ' is not in the inputs catalogue';
            continue;
        }

        $rawRate = $entry['application_rate_per_ha'] ?? null;
        $rate = parseDecimal($rawRate);

        // application_rate_per_ha is NOT NULL: the row must reach the form.
        if ($rawRate === null || $rawRate === '' || ($rate !== null && $rate == 0.0)) {
            $inputRows[] = [
                'input_id' => $inputId,
                'name' => $inputsById[$inputId]['name'],
                'unit' => $inputsById[$inputId]['unit'],
                'application_rate_per_ha' => null,
            ];
            $unresolved[] = 'no application rate given for "' . $inputsById[$inputId]['name']
                . '" — enter it in ' . $inputsById[$inputId]['unit'] . '/ha before saving';
            continue;
        }

        if ($rate === null || $rate < 0) {
            $unresolved[] = 'application_rate_per_ha for "' . $inputsById[$inputId]['name']
                . '" is not a positive number (received: '
                . (is_scalar($rawRate) ? var_export($rawRate, true) : gettype($rawRate)) . ')';
            continue;
        }

        $inputRows[] = [
            'input_id' => $inputId,
            'name' => $inputsById[$inputId]['name'],
            'unit' => $inputsById[$inputId]['unit'],
            'application_rate_per_ha' => $rate,
        ];
    }

    $scoutingImages = agtSanitizeScoutingImages($decoded['scouting_images'] ?? null, $unresolved);

    return [
        'crop_ids' => $cropIds,
        'operation_type_id' => $operationTypeId,
        'operation_date' => $operationDate,
        'inputs' => $inputRows,
        'scouting_images' => $scoutingImages,
        'resolved' => [
            'crops' => $resolvedCrops,
            'operation_type_name' => $operationTypeName,
        ],
        'unresolved' => $unresolved,
        'complete' => count($unresolved) === 0,
    ];
}

// Images travel as an attachment number; base64 would bloat the prompt.
function agtSanitizeScoutingImages($value, array &$unresolved): array
{
    $rows = [];

    foreach ((array) $value as $entry) {
        if (!is_array($entry)) {
            $unresolved[] = 'a scouting_images entry was not an object';
            continue;
        }

        $imageNumber = filter_var($entry['image'] ?? null, FILTER_VALIDATE_INT);
        if ($imageNumber === false || $imageNumber < 1) {
            $unresolved[] = 'a scouting_images entry did not name an attached image by its number';
            continue;
        }

        $note = trim((string) ($entry['note'] ?? ''));
        if (mb_strlen($note) > 1000) {
            $note = mb_substr($note, 0, 1000);
        }

        $rows[] = ['image' => $imageNumber, 'note' => $note];
    }

    return $rows;
}

function validateCropFill(array $decoded): array
{
    $ctx = $GLOBALS['agt_ctx'] ?? [];
    $unresolved = [];

    $fieldsById = [];
    $productionsById = [];

    try {
        $fields = agtDispatchTool('list_fields', [], $ctx);
        $fieldsById = agtIndexById($fields['fields'] ?? []);

        $productions = agtDispatchTool('list_productions', ['limit' => 500], $ctx);
        $productionsById = agtIndexById($productions['productions'] ?? []);
    } catch (Throwable $exception) {
        $unresolved[] = 'could not verify ids against the farm: ' . $exception->getMessage();
    }

    $fieldId = (int) ($decoded['field_id'] ?? 0);
    $fieldName = null;
    if (isset($fieldsById[$fieldId])) {
        $fieldName = $fieldsById[$fieldId]['name'];
    } else {
        $unresolved[] = 'field_id ' . ($decoded['field_id'] ?? 'missing') . ' is not a field on this farm';
        $fieldId = null;
    }

    $productionId = (int) ($decoded['production_id'] ?? 0);
    $productionName = null;
    if (isset($productionsById[$productionId])) {
        $productionName = $productionsById[$productionId]['name'];
    } else {
        $unresolved[] = 'production_id ' . ($decoded['production_id'] ?? 'missing') . ' is not in the productions catalogue';
        $productionId = null;
    }

    $startDate = agtNormalizeDate($decoded['dti'] ?? null);
    if ($startDate === null) {
        $unresolved[] = 'dti "' . ($decoded['dti'] ?? '') . '" is not a valid YYYY-MM-DD start date';
    }

    // parseDecimal: '0,45' would cast to zero.
    $parameters = [];
    foreach (aiFillParameterKeys() as $key) {
        $value = parseDecimal($decoded[$key] ?? null);
        if ($value === null) {
            $unresolved[] = 'parameter ' . $key . ' is missing or not a number';
            continue;
        }
        $parameters[$key] = $value;
    }

    return array_merge([
        'field_id' => $fieldId,
        'production_id' => $productionId,
        'dti' => $startDate,
        'resolved' => [
            'field_name' => $fieldName,
            'production_name' => $productionName,
        ],
        'unresolved' => $unresolved,
        'complete' => count($unresolved) === 0,
    ], $parameters);
}

function safeJsonEncode($value): string
{
    $encodedValue = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encodedValue === false) {
        throw new RuntimeException('Failed to encode JSON payload');
    }
    return $encodedValue;
}

function decodeJsonToArray($value): array
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

// Last separator is the decimal point: '1.234,56' and '1,234.56'.
function parseDecimal($value): ?float
{
    if (is_int($value) || is_float($value)) {
        return is_finite((float) $value) ? (float) $value : null;
    }

    if (!is_string($value)) {
        return null;
    }

    $text = str_replace(["\xc2\xa0", ' '], '', trim($value));
    if ($text === '') {
        return null;
    }

    $lastComma = strrpos($text, ',');
    $lastDot = strrpos($text, '.');

    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        } else {
            $text = str_replace(',', '', $text);
        }
    } elseif ($lastComma !== false) {
        $text = str_replace(',', '.', $text);
    }

    if (!is_numeric($text)) {
        return null;
    }

    $number = (float) $text;

    return is_finite($number) ? $number : null;
}

// Model string: 'gemini:x' to gpx, 'instance:x' to opx, bare to default.

function defaultOpenAiCompatibleInstance(array $openAiCompatibleConfig): string
{
    $key = (string) ($openAiCompatibleConfig['default_instance'] ?? '');
    if ($key !== '' && isset($openAiCompatibleConfig['instances'][$key])) {
        return $key;
    }

    $keys = array_keys((array) ($openAiCompatibleConfig['instances'] ?? []));
    return $keys[0] ?? '';
}

function defaultOpenAiCompatibleModel(array $openAiCompatibleConfig): string
{
    $instance = defaultOpenAiCompatibleInstance($openAiCompatibleConfig);
    $models = (array) ($openAiCompatibleConfig['instances'][$instance]['models'] ?? []);
    return (string) ($models[0] ?? '');
}

function availableModels(array $openAiCompatibleConfig, array $geminiConfig): array
{
    $models = [];

    if (isGeminiConfigured($geminiConfig)) {
        $models[] = 'gemini:' . $geminiConfig['model'];
    }

    $defaultInstance = defaultOpenAiCompatibleInstance($openAiCompatibleConfig);
    foreach ((array) ($openAiCompatibleConfig['instances'] ?? []) as $instance => $settings) {
        if (!isOpenAiCompatibleInstanceConfigured($openAiCompatibleConfig, (string) $instance)) {
            continue;
        }
        foreach ((array) ($settings['models'] ?? []) as $model) {
            $models[] = ($instance === $defaultInstance) ? $model : $instance . ':' . $model;
        }
    }

    return $models;
}

function resolveProviderAndModel(?string $requestedModel): array
{
    global $openAiCompatibleConfig, $geminiConfig;

    $defaultModel = availableModels($openAiCompatibleConfig, $geminiConfig)[0] ?? '';

    $model = trim((string) ($requestedModel ?: $defaultModel));
    if ($model === '') {
        $model = $defaultModel;
    }

    // An instance key must never collide with a model name's first segment.
    $separator = strpos($model, ':');
    if ($separator !== false) {
        $prefix = substr($model, 0, $separator);
        $remainder = substr($model, $separator + 1);

        if ($prefix === 'gemini' && $remainder !== '') {
            return ['gemini', $remainder, ''];
        }
        if ($remainder !== '' && isset($openAiCompatibleConfig['instances'][$prefix])) {
            return ['opx', $remainder, $prefix];
        }
    }

    return ['opx', $model, defaultOpenAiCompatibleInstance($openAiCompatibleConfig)];
}

function isGeminiConfigured(array $geminiConfig): bool
{
    return !empty($geminiConfig['agent_url']);
}

function isOpenAiCompatibleInstanceConfigured(array $openAiCompatibleConfig, string $instance): bool
{
    return !empty($openAiCompatibleConfig['instances'][$instance]['agent_url']);
}

function openAiCompatibleInstanceConfig(string $instance): array
{
    global $openAiCompatibleConfig;
    return (array) ($openAiCompatibleConfig['instances'][$instance] ?? []);
}

function assertProviderConfigured(string $provider, string $instance = ''): void
{
    global $geminiConfig, $openAiCompatibleConfig;

    if ($provider === 'gemini') {
        if (!isGeminiConfigured($geminiConfig)) {
            agtFail(400, 'Gemini provider is not configured');
        }
        return;
    }

    if (!isset($openAiCompatibleConfig['instances'][$instance])) {
        agtFail(400, 'Unknown model provider: ' . $instance);
    }
    if (!isOpenAiCompatibleInstanceConfigured($openAiCompatibleConfig, $instance)) {
        agtFail(400, 'Model provider is not configured: ' . $instance);
    }
}

// 'operation' mirrors the Vue form object so applying it is assignment.

function resolveFillSchema($requested): string
{
    $schema = strtolower(trim((string) ($requested ?? '')));

    return in_array($schema, ['crop_parameters', 'operation'], true) ? $schema : 'crop_parameters';
}

function aiFillParameterKeys(): array
{
    return ['dri', 'kci', 'drd', 'kcm', 'drm', 'kce', 'drl', 'rdi', 'rdm', 'iws', 'awc'];
}

function fillSchemaInstruction(string $schema): string
{
    if ($schema === 'operation') {
        return 'Now output the operation as a single JSON object with exactly these keys: '
            . 'crop_ids (array of integers), operation_type_id (integer), operation_date (string, YYYY-MM-DD), '
            . 'Each value in crop_ids must be a crop_id from list_crops. '
            . 'It is NOT the field id from list_fields and NOT a production_id: those number differently, and a field id put here names no crop. '
            . 'inputs (array of objects, each with input_id (integer) and, only when the user actually stated a dose, application_rate_per_ha (number)). '
            . 'Use only ids you obtained from the tools. Use an empty array for inputs if none apply. '
            . 'If the user named an input but no rate, include the input and omit application_rate_per_ha entirely — do not send 0 and do not invent a typical dose. '
            . 'Write decimals with a point. '
            . 'Output only that JSON object — no markdown, no code fences, no commentary.';
    }

    return 'Now output the final parameters as a single JSON object with exactly these numeric keys: '
        . implode(', ', aiFillParameterKeys())
        . '. Output only that JSON object — no markdown, no code fences, no commentary.';
}

// Pulls the first balanced {...} out: models wrap it in fences or prose.
function extractFirstJsonObject(string $content): ?string
{
    $content = preg_replace('/<think\b[^>]*>.*?<\/think>/is', '', $content) ?? $content;
    $content = preg_replace('/```[a-z]*\s*/i', '', $content) ?? $content;

    $start = strpos($content, '{');
    if ($start === false) {
        return null;
    }

    $depth = 0;
    $inString = false;
    $escaped = false;
    $length = strlen($content);

    for ($i = $start; $i < $length; $i++) {
        $char = $content[$i];

        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif ($char === '"') {
                $inString = false;
            }
            continue;
        }

        if ($char === '"') {
            $inString = true;
        } elseif ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($content, $start, $i - $start + 1);
            }
        }
    }

    return null;
}

function validateAiFillResponse(string $content, string $schema = 'crop_parameters'): array
{
    $decoded = null;

    try {
        $decoded = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $extracted = extractFirstJsonObject($content);
        if ($extracted !== null) {
            $decoded = json_decode($extracted, true);
        }
    }

    if (!is_array($decoded)) {
        agtLog('fill_invalid_json | raw=' . substr(preg_replace('/\s+/', ' ', $content), 0, 400));
        throw new RuntimeException('Provider returned invalid JSON: ' . substr(trim($content), 0, 200));
    }

    if ($schema === 'operation') {
        return validateOperationFill($decoded);
    }

    $expectedKeys = aiFillParameterKeys();
    if (!is_array($decoded)
        || count($decoded) !== count($expectedKeys)
        || count(array_diff($expectedKeys, array_keys($decoded))) !== 0
        || count(array_diff(array_keys($decoded), $expectedKeys)) !== 0) {
        throw new RuntimeException('Provider returned an invalid crop parameter object');
    }

    foreach ($expectedKeys as $key) {
        // parseDecimal, not is_numeric: a Portuguese fill returns '0,45'.
        $parsed = parseDecimal($decoded[$key]);
        if ($parsed === null) {
            throw new RuntimeException('Crop parameter is not numeric: ' . $key
                . ' (received: ' . (is_scalar($decoded[$key]) ? var_export($decoded[$key], true) : gettype($decoded[$key])) . ')');
        }
        $decoded[$key] = $parsed;
        if (!is_finite($decoded[$key])) {
            throw new RuntimeException('Crop parameter is not finite: ' . $key);
        }
    }

    return $decoded;
}


function sanitizeChatMessages(array $messages): array
{
    $cleanedMessages = [];

    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = $message['role'] ?? '';
        if ($role !== 'user' && $role !== 'assistant') {
            continue;
        }

        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        $cleanedMessage = ['role' => $role, 'content' => $content];

        $image = sanitizeChatImage($message['image'] ?? null);
        if ($image !== null) {
            $cleanedMessage['image'] = $image;
        }

        $cleanedMessages[] = $cleanedMessage;
    }

    return array_slice($cleanedMessages, -12);
}

function sanitizeChatImage($image): ?array
{
    if (!is_string($image) || $image === '') {
        return null;
    }

    if (!preg_match('/^data:(image\/(?:jpeg|png|webp));base64,([a-zA-Z0-9+\/=]+)$/', trim($image), $matches)) {
        return null;
    }

    $decoded = base64_decode($matches[2], true);
    if ($decoded === false || strlen($decoded) === 0 || strlen($decoded) > 8 * 1024 * 1024) {
        return null;
    }

    return ['mime_type' => $matches[1], 'data' => $matches[2]];
}


// Weekday included: 'last Friday' cannot be resolved from a bare date.
function agtTodayLine(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone(AGT_TIMEZONE));

    return 'Today is ' . $now->format('l, Y-m-d')
        . '. Resolve relative dates against it; never invent one.';
}

function buildAiSystemPrompt(): string
{
    return implode("\n", [
        'You are the AgriNexo agricultural AI assistant.',
        agtTodayLine(),
        'Retrieve the smallest data slice needed before answering.',
        'Do not invent data or pretend a tool exists when it does not.',
        'When the user asks a factual question about agriculture, or about AgriNexo and its own modules and features, search the knowledge base before answering.',
        'You cannot write to the database, and the propose_ tools do not write either. What they do is fill in the real form and show it to the user, who checks it and saves it themselves. So proposing is always safe, and it is never a substitute for their consent — they still press Save.',
        'If the user asks to record, log or register an operation, or to add or start a crop, do not answer that you cannot write and leave them to copy values by hand. Resolve the ids with the read tools, then call propose_operation or propose_crop. Afterwards, say briefly what you proposed and tell them a draft is on screen for them to review and save. Do not describe it as saved, and do not tell them to press a button beside the message box — there is none.',
        'If something would not resolve, propose anyway: the unresolved list travels with the draft and tells the user exactly what to complete, which is more use to them than a refusal.',
        'When the user attaches photographs, they are numbered from 1 in the order sent. If you examine one — weeds, pests, disease, crop condition — answer in the chat as usual, and when the observation is worth keeping against the picture, call propose_operation with a scouting_images entry naming that image number and your note. Never put image data in a tool argument.',
        'If the available data is still insufficient after tool calls, ask a focused clarification question.',
        'Keep answers concise and cite the field/crop and date range used when you rely on retrieved data.',
    ]);
}

function buildAiFillDomainPrompt(string $schema = 'crop_parameters'): array
{
    if ($schema === 'operation') {
        return [
            'You are drafting one entry for the farm\'s operations log, from what the user describes.',
            'An operation is attached to a crop_id: one cultivation of one production on one field, not to a production or a field alone.',
            'Never invent an id. Every crop_id, operation_type_id and input_id must come from a tool result.',
            'Application rates are per hectare, in the unit the inputs catalogue gives for that input.',
        ];
    }

    return [
        'You are estimating FAO-56 crop water requirement parameters for one crop - one cultivation of one production on one field.',
        'The user message contains the production name (what is grown), the cycle start date, the field id and the field centroid.',
        'Consider that the duration of each development stage depends on growing degrees day and therefore on planting date.',
        'Ground the parameters in the field\'s real climate normals and vegetation data rather than generic assumptions.',
    ];
}

function buildAiFillToolSystemPrompt(string $schema = 'crop_parameters'): string
{
    if ($schema === 'operation') {
        return implode("\n", array_merge(buildAiFillDomainPrompt($schema), [
            agtTodayLine(),
            'Before answering you must resolve every id with the tools:',
            '- Call list_crops to turn the field or production the user named into crop_id values. Filter with field_query on the field name. Do not pass active_on unless you need to disambiguate between two crops on the same field: operations are often logged outside the modelled cycle, so filtering by it will hide the crop you want.',
            '- Call list_operation_types to turn the operation type the user named into an operation_type_id.',
            '- Call list_inputs for each input mentioned, to get its input_id and its unit. Check the unit matches the quantity the user gave before using it as a rate.',
            '- The catalogues may be written in a different language from the user. When a lookup returns nothing it gives you the full list instead — match it by meaning rather than repeating the search with the same word.',
            '- Call get_operations if you need to check whether this operation was already logged.',
            'If something cannot be resolved, say so plainly rather than guessing an id.',
            'Then state the values you have concluded. The strict output format will be requested separately.',
        ]));
    }

    return implode("\n", array_merge(buildAiFillDomainPrompt($schema), [
        'Before answering you must gather the data you need:',
        '- Call get_field_context with the given field id to obtain that field\'s real climate normals and vegetation data.',
        '- Call search_knowledge_base for the production to retrieve any locally adjusted Kc values or stage-length guidance.',
        '- Call get_operations if planting or irrigation history for this crop would help you estimate stage lengths.',
        'Then state the parameter values you have concluded. The strict output format will be requested separately.',
    ]));
}

function buildAiFillSchemaSystemPrompt(string $schema = 'crop_parameters'): string
{
    if ($schema === 'operation') {
        return implode("\n", array_merge(buildAiFillDomainPrompt($schema), [
            agtTodayLine(),
            'The conversation above contains the ids you resolved. Use only those.',
            'crop_ids come from the crop_id key of list_crops. The id in list_fields and the field_id in list_crops are the same number, and both identify a field rather than a crop; neither belongs here.',
            'Return only a JSON object with exactly these keys: crop_ids, operation_type_id, operation_date, inputs.',
            'Do not use markdown or additional text.',
        ]));
    }

    return implode("\n", array_merge(buildAiFillDomainPrompt($schema), [
        'The conversation above contains the data you retrieved. Base your values on it.',
        'Return only a JSON object with exactly these numeric keys: ' . implode(', ', aiFillParameterKeys()) . '.',
        'Do not use markdown or additional text.',
    ]));
}


// $stopAfterTools leaves 'messages' ending on the tool results.
function runAiToolLoop(string $provider, string $model, array $messages, string $systemPrompt, array $toolDefinitions, int $maxRoundTrips, bool $stopAfterTools = false, string $instance = ''): array
{
    $toolTrace = [];

    for ($roundTrip = 0; $roundTrip < $maxRoundTrips; $roundTrip++) {
        $providerReply = callAiProvider($provider, $model, $messages, $systemPrompt, $toolDefinitions, false, $instance);

        if (empty($providerReply['tool_calls'])) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $providerReply['content'] ?? '',
            ];

            return [
                'reply' => $providerReply,
                'messages' => $messages,
                'tool_trace' => $toolTrace,
            ];
        }

        $messages[] = [
            'role' => 'assistant',
            'content' => $providerReply['content'] ?? '',
            'tool_calls' => $providerReply['tool_calls'],
        ];

        foreach ($providerReply['tool_calls'] as $toolCall) {
            $toolResult = agtExecuteTool($toolCall['name'], $toolCall['arguments'] ?? [], $GLOBALS['agt_ctx']);
            $toolTrace[] = [
                'name' => $toolCall['name'],
                'arguments' => $toolCall['arguments'] ?? [],
                'result_count' => agtToolResultCount($toolResult),
            ];

            $messages[] = [
                'role' => 'tool',
                'name' => $toolCall['name'],
                'tool_call_id' => $toolCall['id'],
                'content' => safeJsonEncode($toolResult),
            ];
        }

        if ($stopAfterTools) {
            return [
                'reply' => $providerReply,
                'messages' => $messages,
                'tool_trace' => $toolTrace,
            ];
        }
    }

    $providerReply = callAiProvider($provider, $model, $messages, $systemPrompt, [], false, $instance);
    $messages[] = [
        'role' => 'assistant',
        'content' => $providerReply['content'] ?? '',
    ];

    return [
        'reply' => $providerReply,
        'messages' => $messages,
        'tool_trace' => $toolTrace,
    ];
}


function callAiProvider(string $provider, string $model, array $messages, string $systemPrompt, array $toolDefinitions, bool $jsonOnly = false, string $instance = '', ?array $responseSchema = null): array
{
    global $geminiConfig;

    $startedAt = microtime(true);

    try {
        switch ($provider) {
            case 'gemini':
                $reply = callGoogleGemini($geminiConfig, $model, $messages, $systemPrompt, $toolDefinitions, $jsonOnly, $responseSchema);
                break;
            default:
                $reply = callOpenAiCompatible(openAiCompatibleInstanceConfig($instance), $instance, $model, $messages, $systemPrompt, $toolDefinitions, $jsonOnly, $responseSchema);
        }
    } catch (Throwable $exception) {
        agtLog(sprintf(
            'provider_call | %s | instance=%s | model=%s | FAILED after %dms | %s',
            $provider,
            $instance !== '' ? $instance : '-',
            $model,
            (int) round((microtime(true) - $startedAt) * 1000),
            $exception->getMessage()
        ));
        throw $exception;
    }

    agtLog(sprintf(
        'provider_call | %s | instance=%s | model=%s | fill_mode=%s | %dms | msgs=%d | tools=%s | json_only=%s | tool_calls=%d | content_len=%d | reasoning_len=%d',
        $provider,
        $instance !== '' ? $instance : '-',
        $model,
        AI_FILL_SKIP_REASONING ? 'no_reasoning' : 'reasoning',
        (int) round((microtime(true) - $startedAt) * 1000),
        count($messages),
        count($toolDefinitions) > 0 ? 'yes' : 'no',
        $jsonOnly ? 'yes' : 'no',
        count($reply['tool_calls'] ?? []),
        strlen((string) ($reply['content'] ?? '')),
        strlen((string) ($reply['reasoning'] ?? ''))
    ));

    return $reply;
}

function callGoogleGemini(array $geminiConfig, string $model, array $messages, string $systemPrompt, array $toolDefinitions, bool $jsonOnly = false, ?array $responseSchema = null): array
{
    $agentUrl = rtrim((string) ($geminiConfig['agent_url'] ?? ''), '/');
    if ($agentUrl === '') {
        throw new RuntimeException('Gemini agent service URL is not configured');
    }

    $requestId = bin2hex(random_bytes(8));

    $payload = [
        'request_id' => $requestId,
        'model' => $model,
        'system_prompt' => $systemPrompt,
        'messages' => $messages,
        'tools' => $toolDefinitions,
        'json_only' => $jsonOnly,
        'temperature' => $geminiConfig['temperature'] ?? 0.2,
    ];

    if ($jsonOnly) {
        $payload['response_schema'] = $responseSchema ?? buildFillSchema();
    }

    $headers = ['Content-Type: application/json'];
    if (!empty($geminiConfig['agent_token'])) {
        $headers[] = 'X-Agent-Token: ' . $geminiConfig['agent_token'];
    }
    if (!empty($geminiConfig['debug_payloads'])) {
        $headers[] = 'X-Ai-Debug: 1';
    }

    $response = sendJsonRequest($agentUrl . '/generate', $headers, $payload);

    if (!isset($response['content']) && !isset($response['tool_calls'])) {
        agtLog("gemini_call | ERROR: unexpected agent response | req=$requestId");
        throw new RuntimeException('Invalid Gemini agent response');
    }

    $toolCalls = [];
    foreach ($response['tool_calls'] ?? [] as $toolCall) {
        if (!is_array($toolCall) || empty($toolCall['name'])) {
            continue;
        }
        $toolCalls[] = [
            'id' => $toolCall['id'] ?? ('gemini-tool-' . uniqid()),
            'name' => $toolCall['name'],
            'arguments' => is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : [],
            // Opaque base64; the proxy decodes it for replay.
            'thought_signature' => $toolCall['thought_signature'] ?? null,
        ];
    }

    $normalized = [
        'content' => trim((string) ($response['content'] ?? '')),
        'reasoning' => trim((string) ($response['reasoning'] ?? '')),
        'tool_calls' => $toolCalls,
    ];

    agtLog("gemini_call | ok | model=$model | req=$requestId | tool_calls=" . count($normalized['tool_calls']) . ' | content_len=' . strlen($normalized['content']));

    return $normalized;
}

function callOpenAiCompatible(array $instanceConfig, string $instance, string $model, array $messages, string $systemPrompt, array $toolDefinitions, bool $jsonOnly = false, ?array $responseSchema = null): array
{
    $agentUrl = rtrim((string) ($instanceConfig['agent_url'] ?? ''), '/');
    if ($agentUrl === '') {
        throw new RuntimeException('Model proxy URL is not configured for instance: ' . $instance);
    }

    $requestId = bin2hex(random_bytes(8));

    $payload = [
        'request_id' => $requestId,
        'model' => $model,
        'system_prompt' => $systemPrompt,
        'messages' => $messages,
        'tools' => $toolDefinitions,
        'json_only' => $jsonOnly,
        'temperature' => $instanceConfig['temperature'] ?? 0.2,
    ];

    if ($jsonOnly) {
        $payload['response_schema'] = $responseSchema ?? buildFillSchema();
    }

    $headers = ['Content-Type: application/json'];
    if (!empty($instanceConfig['agent_token'])) {
        $headers[] = 'X-Agent-Token: ' . $instanceConfig['agent_token'];
    }
    if (!empty($instanceConfig['debug_payloads'])) {
        $headers[] = 'X-Ai-Debug: 1';
    }

    $response = sendJsonRequest($agentUrl . '/generate', $headers, $payload);

    if (!isset($response['content']) && !isset($response['tool_calls'])) {
        agtLog("opx_call | ERROR: unexpected agent response | instance=$instance | req=$requestId");
        throw new RuntimeException('Invalid response from model proxy: ' . $instance);
    }

    $toolCalls = [];
    foreach ($response['tool_calls'] ?? [] as $toolCall) {
        if (!is_array($toolCall) || empty($toolCall['name'])) {
            continue;
        }
        $toolCalls[] = [
            'id' => $toolCall['id'] ?? ('opx-tool-' . uniqid()),
            'name' => $toolCall['name'],
            'arguments' => is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : [],
        ];
    }

    $normalized = [
        'content' => trim((string) ($response['content'] ?? '')),
        'reasoning' => trim((string) ($response['reasoning'] ?? '')),
        'tool_calls' => $toolCalls,
    ];

    agtLog("opx_call | ok | instance=$instance | model=$model | req=$requestId | tool_calls=" . count($normalized['tool_calls']) . ' | content_len=' . strlen($normalized['content']));

    return $normalized;
}

function buildFillSchema(string $schema = 'crop_parameters'): array
{
    if ($schema === 'operation') {
        return [
            'type' => 'object',
            'properties' => [
                'crop_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
                'operation_type_id' => ['type' => 'integer'],
                'operation_date' => ['type' => 'string'],
                'inputs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'input_id' => ['type' => 'integer'],
                            'application_rate_per_ha' => ['type' => 'number'],
                        ],
                        'required' => ['input_id'],
                    ],
                ],
            ],
            'required' => ['crop_ids', 'operation_type_id', 'operation_date', 'inputs'],
        ];
    }

    $properties = [];
    foreach (aiFillParameterKeys() as $key) {
        $properties[$key] = ['type' => 'number'];
    }

    return [
        'type' => 'object',
        'properties' => $properties,
        'required' => aiFillParameterKeys(),
    ];
}


// $timeout must stay above the proxy's own upstream timeout.
function sendJsonRequest(string $url, array $headers, array $payload, int $timeout = 120): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL extension is required for AI requests');
    }

    $body = safeJsonEncode($payload);
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

    $decodedResponse = json_decode($responseBody, true);
    if (!is_array($decodedResponse)) {
        throw new RuntimeException('AI provider returned a non-JSON response');
    }

    if ($httpCode >= 400) {
        throw new RuntimeException(extractProviderErrorMessage($decodedResponse, 'AI provider request failed'));
    }

    return $decodedResponse;
}

function extractProviderErrorMessage(array $response, string $defaultMessage): string
{
    foreach (['error.message', 'message', 'errorMessage'] as $path) {
        $value = readNestedValue($response, $path);
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }
    }
    return $defaultMessage;
}

function readNestedValue(array $data, string $path)
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