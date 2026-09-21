<?php

// Refusals are labelled (meta.code); an unresolvable filter returns no rows.

declare(strict_types=1);

// Europe/Lisbon rather than php.ini's UTC, so "today" means the farm's today.
const WMX_TIMEZONE = 'Europe/Lisbon';

function wmxLog(string $message, ?array $ctx = null): void
{
    $ctx = $ctx ?? ($GLOBALS['wmx_ctx'] ?? []);

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


function wmxIndexById(array $rows, string $key = 'id'): array
{
    $indexed = [];
    foreach ($rows as $row) {
        if (isset($row[$key])) {
            $indexed[(int) $row[$key]] = $row;
        }
    }
    return $indexed;
}

function wmxValidateOperationFill(array $decoded): array
{
    $ctx = $GLOBALS['wmx_ctx'] ?? [];
    $unresolved = [];

    $cropsById = [];
    $operationTypesById = [];
    $inputsById = [];

    try {
        $crops = wmxDispatchTool('list_crops', ['limit' => 500], $ctx);
        $cropsById = wmxIndexById($crops['crops'] ?? [], 'crop_id');

        $operationTypes = wmxDispatchTool('list_operation_types', ['limit' => 500], $ctx);
        $operationTypesById = wmxIndexById($operationTypes['operation_types'] ?? []);

        $inputs = wmxDispatchTool('list_inputs', ['limit' => 500], $ctx);
        $inputsById = wmxIndexById($inputs['inputs'] ?? []);
    } catch (Throwable $exception) {
        $unresolved[] = 'could not verify ids against the farm: ' . $exception->getMessage();
    }

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

    $operationDate = wmxNormalizeDate($decoded['operation_date'] ?? null);
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
        $rate = wmxParseDecimal($rawRate);

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

    $scoutingImages = wmxSanitizeScoutingImages($decoded['scouting_images'] ?? null, $unresolved);

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

// Images travel as an attachment number; base64 would bloat every prompt.
function wmxSanitizeScoutingImages($value, array &$unresolved): array
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

function wmxFillParameterKeys(): array
{
    return ['dri', 'kci', 'drd', 'kcm', 'drm', 'kce', 'drl', 'rdi', 'rdm', 'iws', 'awc'];
}

function wmxValidateCropFill(array $decoded): array
{
    $ctx = $GLOBALS['wmx_ctx'] ?? [];
    $unresolved = [];

    $fieldsById = [];
    $productionsById = [];

    try {
        $fields = wmxDispatchTool('list_fields', [], $ctx);
        $fieldsById = wmxIndexById($fields['fields'] ?? []);

        $productions = wmxDispatchTool('list_productions', ['limit' => 500], $ctx);
        $productionsById = wmxIndexById($productions['productions'] ?? []);
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

    $startDate = wmxNormalizeDate($decoded['dti'] ?? null);
    if ($startDate === null) {
        $unresolved[] = 'dti "' . ($decoded['dti'] ?? '') . '" is not a valid YYYY-MM-DD start date';
    }

    // wmxParseDecimal: '0,45' would cast to zero.
    $parameters = [];
    foreach (wmxFillParameterKeys() as $key) {
        $value = wmxParseDecimal($decoded[$key] ?? null);
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

function wmxSafeJsonEncode($value): string
{
    $encodedValue = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encodedValue === false) {
        throw new RuntimeException('Failed to encode JSON payload');
    }
    return $encodedValue;
}

function wmxParseDecimal($value): ?float
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
