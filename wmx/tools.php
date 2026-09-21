<?php

// Dispatch runs no SQL: each tool is an api/ call with the caller's header.


// $ctx carries the tenant key and the caller's raw Authorization header.
function wmxApiRequest(string $endpoint, array $params, array $ctx, ?array $postBody = null): array
{
    $base = rtrim((string) ($ctx['api_base'] ?? ''), '/');
    if ($base === '') {
        throw new RuntimeException('ECO_API_URL is not configured for this instance');
    }

    $params['krd'] = $ctx['krd'];
    $url = $base . '/' . $endpoint . '?' . http_build_query($params);

    $headers = ['Accept: application/json'];
    if (!empty($ctx['authorization'])) {
        $headers[] = 'Authorization: ' . $ctx['authorization'];
    }

    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int) ($ctx['timeout'] ?? 60),
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($postBody !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($postBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $options[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: application/json']);
    }

    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Tool request failed (' . $endpoint . '): ' . ($error ?: 'transport error'));
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Tool endpoint returned non-JSON (' . $endpoint . ', HTTP ' . $status . ')');
    }

    if (($decoded['success'] ?? false) !== true) {
        throw new RuntimeException($decoded['message'] ?? ('Tool endpoint failed: ' . $endpoint));
    }

    return [
        'data' => $decoded['data'] ?? [],
        'meta' => $decoded['meta'] ?? [],
    ];
}

// Keeps only the named keys, in order: the model gets the tool's columns.
function wmxProject(array $rows, array $keys): array
{
    return array_values(array_map(static function ($row) use ($keys) {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $row[$key] ?? null;
        }
        return $out;
    }, $rows));
}


function wmxToolDefinitions(): array
{
    return [
        [
            'name' => 'list_fields',
            'description' => 'List the available fields (talhões) for the current farm, with their id, name, reference and area in hectares (area_ha). Use this to find out which fields exist, to resolve an ambiguous or unclear field name mentioned by the user, and whenever a quantity is wanted rather than a rate: application rates are per hectare, so a total is the rate multiplied by that field\'s area_ha.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new stdClass(),
                'required' => [],
            ],
        ],
        [
            'name' => 'get_field_context',
            'description' => 'Get real vegetation (NDVI, temperature, precipitation, evapotranspiration) and climate normal data for one specific field (talhão). Call this whenever the user asks about a specific field. Call it once per field if the question involves multiple fields.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'field' => [
                        'type' => 'string',
                        'description' => 'The field name (as mentioned by the user) or its numeric id (from list_fields).',
                    ],
                    'months' => [
                        'type' => 'integer',
                        'description' => 'How many months of recent daily vegetation/climate data to summarize. Defaults to 12.',
                    ],
                    'years' => [
                        'type' => 'integer',
                        'description' => 'How many years of historical data to use for climate normals. Defaults to 30.',
                    ],
                ],
                'required' => ['field'],
            ],
        ],
        [
            'name' => 'list_productions',
            'description' => 'List the productions catalogue for this farm, with each production\'s id, name and unit of measure. A production is what is grown — maize, vine, tomato — as opposed to a crop, which is one cultivation of a production on one field. Use this to resolve a production named by the user into a production_id, or to find out which productions exist.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Production name or partial name to filter by.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return. Default 200, max 500. The result reports total and truncated, so you can tell whether you are seeing all of them.',
                    ],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'get_operations',
            'description' => 'Retrieve operations already logged on this farm. Every operation comes back with the production grown, the field it was performed on (field_name), the operation type and date, and the inputs applied to it — each input\'s name, unit and application rate per hectare. Irrigation operations also carry irrigation_mm, the irrigation depth in mm (null otherwise). Harvest operations carry harvest_qty, the total harvested from that crop, in harvest_unit (null otherwise); a crop can have several harvests, so sum them for its yield. Use this for what was applied, where, when and how much, including totals of a fertiliser or treatment over a period — filter by input_query or input_ids rather than pulling every operation and reading through them. If a filter matches nothing the result lists it under unresolved and returns no rows, which means exactly that: no matching operations. If a date filter returns nothing, available_range reports the dates that do hold operations, so retry against that instead of guessing. If the result has truncated=true it was cut off at the limit and older operations are missing, so narrow start_date/end_date rather than raising the limit — a total computed from a truncated result is wrong.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'production_ids' => [
                        'type' => 'array',
                        'description' => 'Known production IDs (from list_productions) to filter by.',
                        'items' => ['type' => 'integer'],
                    ],
                    'production_query' => [
                        'type' => 'string',
                        'description' => 'Production name or partial production name when the ID is not known.',
                    ],
                    'operation_type_query' => [
                        'type' => 'string',
                        'description' => 'Operation type name or partial operation type name to filter by.',
                    ],
                    'input_ids' => [
                        'type' => 'array',
                        'description' => 'Known input IDs (from list_inputs) to filter by. Returns only operations where one of these inputs was applied.',
                        'items' => ['type' => 'integer'],
                    ],
                    'input_query' => [
                        'type' => 'string',
                        'description' => 'Input name or partial name when the ID is not known — e.g. "copper", "nitrogen". Use this to answer "how much of X was applied" instead of retrieving every operation and reading through them.',
                    ],
                    'start_date' => [
                        'type' => 'string',
                        'description' => 'Start date in YYYY-MM-DD format.',
                    ],
                    'end_date' => [
                        'type' => 'string',
                        'description' => 'End date in YYYY-MM-DD format.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of operations to return. Default 200, max 500. If the result comes back truncated, narrow the date range instead of raising this.',
                    ],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'list_crops',
            'description' => 'List the crops on this farm. A crop is one cultivation of one production on one field, identified by crop_id, and is what an operation is logged against. Each row carries the crop_id, the field it is on, the production grown and the cycle start and end dates. Use this to find out what is growing where, or to resolve a field or production mentioned by the user into a crop_id.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'field_query' => [
                        'type' => 'string',
                        'description' => 'Field name or partial field name to filter by.',
                    ],
                    'field_id' => [
                        'type' => 'integer',
                        'description' => 'Numeric field id (from list_fields) to filter by.',
                    ],
                    'active_on' => [
                        'type' => 'string',
                        'description' => 'Date in YYYY-MM-DD format. Returns only crops whose cycle was under way on that day.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of crops to return. Default 200, max 500. The result reports total and truncated, so you can tell whether you are seeing all of them.',
                    ],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'list_operation_types',
            'description' => 'List the operation types available on this farm (e.g. fertilization, pest control, sowing), with their ids. Use this to resolve an operation type named by the user into an operation_type_id.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Operation name or partial name to filter by.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return. Default 200, max 500. The result reports total and truncated, so you can tell whether you are seeing all of them.',
                    ],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'list_inputs',
            'description' => 'List the inputs catalogue (fertilisers, treatments, amendments) with each input\'s id and unit of measure. Use this to resolve an input named by the user into an input_id, and always check the unit before stating or proposing an application rate.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Input name or partial name to filter by.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return. Default 200, max 500. The result reports total and truncated, so you can tell whether you are seeing all of them.',
                    ],
                ],
                'required' => [],
            ],
        ],
        // search_knowledge_base is agt-only: it costs an embedding per query.

        // Proposals are reads: the user still presses Save on the real form.
        [
            'name' => 'propose_operation',
            'description' => 'Propose a field operation for the user to review and save. Call this once you have resolved the ids with list_crops, list_operation_types and list_inputs and the user wants the operation recorded, logged or registered. This does NOT save anything: it puts a draft on screen that the user checks and saves themselves, so proposing is always safe. Anything you could not resolve comes back listed for them to complete rather than guessed at.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'crop_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'The crops the operation applies to. Each one must be a crop_id from list_crops. It is NOT the field id from list_fields and NOT a production_id: those number differently, and a field id put here names no crop.',
                    ],
                    'operation_type_id' => [
                        'type' => 'integer',
                        'description' => 'The operation type, from list_operation_types.',
                    ],
                    'operation_date' => [
                        'type' => 'string',
                        'description' => 'The date the operation happened, YYYY-MM-DD. Resolve relative dates against today; never invent one.',
                    ],
                    'inputs' => [
                        'type' => 'array',
                        'description' => 'The inputs applied. Use an empty array if none apply.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'input_id' => [
                                    'type' => 'integer',
                                    'description' => 'From list_inputs.',
                                ],
                                'application_rate_per_ha' => [
                                    'type' => 'number',
                                    'description' => 'Only when the user actually stated a dose, in the unit list_inputs gives for that input. If they named an input but no rate, include the input and omit this entirely - do not send 0 and do not invent a typical dose. The user will be asked for it.',
                                ],
                            ],
                            'required' => ['input_id'],
                        ],
                    ],
                    'scouting_images' => [
                        'type' => 'array',
                        'description' => 'Notes on the photographs the user attached in this conversation. Use this after looking at an image the user sent - for example when they ask what weeds or pests are visible - so your finding is filed against the picture instead of being lost in the chat.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'image' => [
                                    'type' => 'integer',
                                    'description' => 'Which attached image this note is about, counting from 1 in the order the user sent them. Never put image data here.',
                                ],
                                'note' => [
                                    'type' => 'string',
                                    'description' => 'The observation to file against that photograph. One concise paragraph.',
                                ],
                            ],
                            'required' => ['image', 'note'],
                        ],
                    ],
                ],
                'required' => ['crop_ids', 'operation_type_id', 'operation_date'],
            ],
        ],
        [
            'name' => 'propose_crop',
            'description' => 'Propose a new crop - one cultivation of one production on one field - for the user to review and save, together with the FAO-56 water-balance parameters for its cycle. Call this when the user wants to add or start a crop. Resolve the field with list_fields and the production with list_productions first, and use get_field_context, and the knowledge base if you have a tool for it, to ground the parameters in that field\'s real climate and vegetation rather than reciting textbook defaults. This does NOT save anything: the user reviews the values in the form and saves them.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'field_id' => [
                        'type' => 'integer',
                        'description' => 'The field to plant on, from list_fields.',
                    ],
                    'production_id' => [
                        'type' => 'integer',
                        'description' => 'What is being grown, from list_productions.',
                    ],
                    'dti' => [
                        'type' => 'string',
                        'description' => 'Cycle start date, YYYY-MM-DD.',
                    ],
                    'dri' => ['type' => 'number', 'description' => 'Length in days of the initial crop stage.'],
                    'kci' => ['type' => 'number', 'description' => 'Crop coefficient Kc for the initial crop stage (dimensionless).'],
                    'drd' => ['type' => 'number', 'description' => 'Length in days of the development crop stage.'],
                    'kcm' => ['type' => 'number', 'description' => 'Crop coefficient Kc for the mid-season crop stage (dimensionless).'],
                    'drm' => ['type' => 'number', 'description' => 'Length in days of the mid-season crop stage.'],
                    'kce' => ['type' => 'number', 'description' => 'Crop coefficient Kc for the late-season crop stage (dimensionless).'],
                    'drl' => ['type' => 'number', 'description' => 'Length in days of the late-season crop stage.'],
                    'rdi' => ['type' => 'number', 'description' => 'Root depth in metres for the initial crop stage.'],
                    'rdm' => ['type' => 'number', 'description' => 'Root depth in metres for the mid-season crop stage.'],
                    'iws' => ['type' => 'number', 'description' => 'Initial soil water as a percentage of TAW (Total Available Water).'],
                    'awc' => ['type' => 'number', 'description' => 'Available water capacity, as volume of water per volume of soil.'],
                ],
                'required' => ['field_id', 'production_id', 'dti'],
            ],
        ],
    ];
}


function wmxExecuteTool(string $toolName, array $arguments, array $ctx): array
{
    wmxLog("tool_call | $toolName | args=" . json_encode($arguments), $ctx);

    $startedAt = microtime(true);

    try {
        $result = wmxDispatchTool($toolName, $arguments, $ctx);
    } catch (Throwable $exception) {
        // A failing tool is reported to the model, not raised.
        wmxLog("tool_error | $toolName | " . $exception->getMessage(), $ctx);
        $result = ['error' => $exception->getMessage()];
    }

    $outcome = '';
    if (isset($result['error'])) {
        $outcome = ' | error=' . substr((string) $result['error'], 0, 80);
    } elseif (isset($result['status']) && $result['status'] !== 'found') {
        $outcome = ' | status=' . $result['status'];
    }

    wmxLog(sprintf(
        'tool_done | %s | %dms | results=%d%s',
        $toolName,
        (int) round((microtime(true) - $startedAt) * 1000),
        wmxToolResultCount($result),
        $outcome
    ), $ctx);

    return $result;
}

function wmxToolResultCount(array $toolResult): int
{
    // Proposals log unresolved count, not results: 0 is a clean draft.
    if (isset($toolResult['unresolved']) && is_array($toolResult['unresolved'])) {
        return count($toolResult['unresolved']);
    }

    foreach (['results', 'operations', 'productions', 'fields', 'crops', 'operation_types', 'inputs'] as $key) {
        if (isset($toolResult[$key]) && is_array($toolResult[$key])) {
            return count($toolResult[$key]);
        }
    }
    return 0;
}

// api/ reports the fallback; the model decides how to explain it.
function wmxFallbackNote(string $what, string $query, int $total): string
{
    return 'Nothing matched "' . $query . '". Returning the full ' . $what . ' list ('
        . $total . ' entries) instead — it may be written in another language, so match by meaning rather than searching again.';
}

function wmxClampInt($value, int $min, int $max, int $default): int
{
    $integerValue = filter_var($value, FILTER_VALIDATE_INT);
    if ($integerValue === false) {
        return $default;
    }
    return max($min, min($max, $integerValue));
}

function wmxNormalizeDate($value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
}

function wmxDispatchTool(string $toolName, array $arguments, array $ctx): array
{
    switch ($toolName) {

        case 'list_fields': {
            $response = wmxApiRequest('fields-list.php', ['action' => 'read'], $ctx);

            // ids stay integers so the payload matches the in-process version.
            return ['fields' => array_map(static function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'reference' => $row['reference'],
                    'area_ha' => $row['area_ha'],
                ];
            }, $response['data'])];
        }

        case 'get_field_context': {
            $response = wmxApiRequest('field-context.php', [
                'field' => (string) ($arguments['field'] ?? ''),
                'months' => isset($arguments['months']) ? (int) $arguments['months'] : 12,
                'years' => isset($arguments['years']) ? (int) $arguments['years'] : 30,
            ], $ctx);

            return $response['data'];
        }

        case 'list_productions': {
            $query = trim((string) ($arguments['query'] ?? ''));
            $limit = wmxClampInt($arguments['limit'] ?? 200, 1, 500, 200);

            $params = ['action' => 'read', 'limit' => $limit];
            if ($query !== '') {
                $params['query'] = $query;
            }

            $response = wmxApiRequest('crops.php', $params, $ctx);
            $rows = $response['data'];
            $total = (int) ($response['meta']['total'] ?? count($rows));

            return [
                'productions' => $rows,
                'count' => count($rows),
                'total' => $total,
                'truncated' => $total > count($rows),
                'note' => empty($response['meta']['fell_back'])
                    ? null
                    : wmxFallbackNote('productions', $query, $total),
            ];
        }

        case 'list_crops': {
            $fieldId = isset($arguments['field_id']) ? (int) $arguments['field_id'] : 0;
            $fieldQuery = trim((string) ($arguments['field_query'] ?? ''));
            $activeOn = wmxNormalizeDate($arguments['active_on'] ?? null);
            $limit = wmxClampInt($arguments['limit'] ?? 200, 1, 500, 200);

            $params = ['action' => 'read', 'limit' => $limit];
            if ($fieldId > 0) {
                $params['aoi'] = $fieldId;
            }
            if ($fieldQuery !== '') {
                $params['field_query'] = $fieldQuery;
            }
            if ($activeOn !== null) {
                $params['active_on'] = $activeOn;
            }

            $response = wmxApiRequest('field-crops.php', $params, $ctx);

            // Projected, not renamed: this map only drops columns.

            $rows = array_map(static function ($row) {
                return [
                    'crop_id' => $row['crop_id'],
                    'field_id' => $row['field_id'],
                    'field_name' => $row['field_name'],
                    'production_id' => $row['production_id'],
                    'production_name' => $row['production_name'],
                    'dti' => $row['dti'],
                    'cycle_days' => $row['cycle_days'],
                    'end_date' => $row['end_date'],
                ];
            }, $response['data']);
            $meta = $response['meta'];
            $total = (int) ($meta['total'] ?? count($rows));

            $note = null;
            if (!empty($meta['fell_back'])) {
                $described = trim($fieldQuery . ($activeOn !== null ? ' active on ' . $activeOn : ''));
                $note = wmxFallbackNote('crop', $described !== '' ? $described : 'those filters', $total)
                    . ' Operations are often logged outside the modelled cycle, so active_on can exclude the crop you want.';
            }

            return [
                'crops' => $rows,
                'count' => count($rows),
                'total' => $total,
                'truncated' => $total > count($rows),
                'note' => $note,
                'filters' => [
                    'field_id' => $fieldId > 0 ? $fieldId : null,
                    'field_query' => $fieldQuery,
                    'active_on' => $activeOn,
                    'limit' => $limit,
                ],
            ];
        }

        case 'list_operation_types': {
            $query = trim((string) ($arguments['query'] ?? ''));
            $limit = wmxClampInt($arguments['limit'] ?? 200, 1, 500, 200);

            $params = ['action' => 'read', 'limit' => $limit];
            if ($query !== '') {
                $params['query'] = $query;
            }

            $response = wmxApiRequest('operation_types.php', $params, $ctx);
            $rows = $response['data'];
            $total = (int) ($response['meta']['total'] ?? count($rows));

            return [
                'operation_types' => $rows,
                'count' => count($rows),
                'total' => $total,
                'truncated' => $total > count($rows),
                'note' => empty($response['meta']['fell_back'])
                    ? null
                    : wmxFallbackNote('operation type', $query, $total),
            ];
        }

        case 'list_inputs': {
            $query = trim((string) ($arguments['query'] ?? ''));
            $limit = wmxClampInt($arguments['limit'] ?? 200, 1, 500, 200);

            $params = ['action' => 'read', 'limit' => $limit];
            if ($query !== '') {
                $params['query'] = $query;
            }

            $response = wmxApiRequest('inputs.php', $params, $ctx);
            $rows = $response['data'];
            $total = (int) ($response['meta']['total'] ?? count($rows));

            return [
                'inputs' => $rows,
                'count' => count($rows),
                'total' => $total,
                'truncated' => $total > count($rows),
                'note' => empty($response['meta']['fell_back'])
                    ? null
                    : wmxFallbackNote('inputs catalogue', $query, $total),
            ];
        }

        case 'get_operations': {
            $productionIds = array_values(array_unique(array_filter(
                array_map('intval', $arguments['production_ids'] ?? []),
                static function ($v) { return $v > 0; }
            )));
            $inputIds = array_values(array_unique(array_filter(
                array_map('intval', $arguments['input_ids'] ?? []),
                static function ($v) { return $v > 0; }
            )));
            $productionQuery = trim((string) ($arguments['production_query'] ?? ''));
            $inputQuery = trim((string) ($arguments['input_query'] ?? ''));
            $operationTypeQuery = trim((string) ($arguments['operation_type_query'] ?? ''));
            $startDate = wmxNormalizeDate($arguments['start_date'] ?? null);
            $endDate = wmxNormalizeDate($arguments['end_date'] ?? null);
            $limit = wmxClampInt($arguments['limit'] ?? 200, 1, 500, 200);

            // no_images: base64 and file paths are pure waste in a prompt.
            $params = ['action' => 'read', 'limit' => $limit, 'no_images' => 1];
            if (count($productionIds) > 0) { $params['production_ids'] = implode(',', $productionIds); }
            if (count($inputIds) > 0)     { $params['input_ids'] = implode(',', $inputIds); }
            if ($productionQuery !== '')  { $params['production_query'] = $productionQuery; }
            if ($inputQuery !== '')       { $params['input_query'] = $inputQuery; }
            if ($operationTypeQuery !== '')   { $params['operation_type_query'] = $operationTypeQuery; }
            if ($startDate !== null)      { $params['start_date'] = $startDate; }
            if ($endDate !== null)        { $params['end_date'] = $endDate; }

            $response = wmxApiRequest('crops_operations.php', $params, $ctx);
            $meta = $response['meta'];

            // An unresolvable name means no rows, never the whole table.
            if (!empty($meta['unresolved'])) {
                return [
                    'operations' => [],
                    'count' => 0,
                    'total' => 0,
                    'truncated' => false,
                    'unresolved' => $meta['unresolved'],
                    'resolved_productions' => $meta['resolved_productions'] ?? [],
                    'resolved_inputs' => $meta['resolved_inputs'] ?? [],
                ];
            }

            $operations = [];
            foreach ($response['data'] as $row) {
                $operations[] = [
                    'id' => $row['id'],
                    'crop_id' => $row['crop_id'],
                    'production_id' => $row['production_id'],
                    'production_name' => $row['production_name'],
                    'field_id' => $row['field_id'],
                    'field_name' => $row['field_name'],
                    'operation_type_id' => $row['operation_type_id'],
                    'operation_type_name' => $row['operation_type_name'],
                    'operation_date' => $row['operation_date'],
                    'irrigation_mm' => $row['irrigation_mm'] ?? null,
                    'harvest_qty' => $row['harvest_qty'] ?? null,
                    'harvest_unit' => $row['harvest_unit'] ?? null,
                    'inputs' => wmxProject($row['inputs'] ?? [], [
                        'input_id', 'name', 'unit', 'application_rate_per_ha',
                    ]),
                ];
            }

            if (count($productionIds) === 0 && !empty($meta['resolved_productions'])) {
                $productionIds = array_map('intval', array_column($meta['resolved_productions'], 'id'));
            }
            if (count($inputIds) === 0 && !empty($meta['resolved_inputs'])) {
                $inputIds = array_map('intval', array_column($meta['resolved_inputs'], 'id'));
            }

            $total = (int) ($meta['total'] ?? count($operations));

            return [
                'operations' => $operations,
                'available_range' => $meta['available_range'] ?? null,
                'count' => count($operations),
                'total' => $total,
                'truncated' => $total > count($operations),
                'filters' => [
                    'production_ids' => $productionIds,
                    'production_query' => $productionQuery,
                    'operation_type_query' => $operationTypeQuery,
                    'input_ids' => $inputIds,
                    'input_query' => $inputQuery,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'limit' => $limit,
                ],
                'resolved_productions' => wmxProject($meta['resolved_productions'] ?? [], ['id', 'name', 'description']),
                'resolved_inputs' => $meta['resolved_inputs'] ?? [],
            ];
        }

        // Proposals validate through the read tools, inheriting tenant scoping.
        case 'propose_operation': {
            $draft = wmxValidateOperationFill($arguments);
            $GLOBALS['wmx_proposal'] = ['target' => 'operations', 'payload' => $draft];

            return $draft;
        }

        case 'propose_crop': {
            $draft = wmxValidateCropFill($arguments);
            $GLOBALS['wmx_proposal'] = ['target' => 'crops', 'payload' => $draft];

            return $draft;
        }

        default:
            return ['error' => 'Unsupported tool: ' . $toolName];
    }
}
