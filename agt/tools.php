<?php

// Adapter: the tools live in wmx/; tools-extra.php adds private ones.

declare(strict_types=1);

require_once __DIR__ . '/../wmx/tool-validators.php';
require_once __DIR__ . '/../wmx/tools.php';
require_once __DIR__ . '/tools-extra.php';

function agtToolDefinitions(): array
{
    return array_merge(wmxToolDefinitions(), agtExtraToolDefinitions());
}

function agtIsExtraTool(string $toolName): bool
{
    return in_array($toolName, array_column(agtExtraToolDefinitions(), 'name'), true);
}

function agtDispatchTool(string $toolName, array $arguments, array $ctx): array
{
    $GLOBALS['wmx_ctx'] = $ctx;

    if (agtIsExtraTool($toolName)) {
        return agtDispatchExtraTool($toolName, $arguments, $ctx);
    }

    return wmxDispatchTool($toolName, $arguments, $ctx);
}

function agtExecuteTool(string $toolName, array $arguments, array $ctx): array
{
    agtLog("tool_call | $toolName | args=" . json_encode($arguments), $ctx);

    $startedAt = microtime(true);

    $GLOBALS['wmx_proposal'] = null;

    try {
        $result = agtDispatchTool($toolName, $arguments, $ctx);
    } catch (Throwable $exception) {
        agtLog("tool_error | $toolName | " . $exception->getMessage(), $ctx);
        $result = ['error' => $exception->getMessage()];
    }

    if (isset($GLOBALS['wmx_proposal']) && $GLOBALS['wmx_proposal'] !== null) {
        $GLOBALS['agt_proposal'] = $GLOBALS['wmx_proposal'];
    }

    $outcome = '';
    if (isset($result['error'])) {
        $outcome = ' | error=' . substr((string) $result['error'], 0, 80);
    } elseif (isset($result['status']) && $result['status'] !== 'found') {
        $outcome = ' | status=' . (string) $result['status'];
    }

    agtLog(sprintf(
        'tool_done | %s | %dms | rows=%s%s',
        $toolName,
        (int) round((microtime(true) - $startedAt) * 1000),
        (string) agtToolResultCount($result),
        $outcome
    ), $ctx);

    return $result;
}


function agtApiRequest(string $endpoint, array $params, array $ctx, ?array $postBody = null): array
{
    $GLOBALS['wmx_ctx'] = $ctx;

    return wmxApiRequest($endpoint, $params, $ctx, $postBody);
}

function agtNormalizeDate($value): ?string
{
    return wmxNormalizeDate($value);
}

function agtToolResultCount(array $toolResult): int
{
    return wmxToolResultCount($toolResult);
}
