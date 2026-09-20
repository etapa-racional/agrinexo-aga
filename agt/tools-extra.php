<?php

// Empty on purpose: a tool here is private because wmx cannot publish it.

declare(strict_types=1);

function agtExtraToolDefinitions(): array
{
    return [
        [
            'name' => 'search_knowledge_base',
            'description' => 'Search the knowledge base for relevant agricultural information using semantic similarity. Use this when the user asks questions about agricultural practices, crop management, pest control, soil science, irrigation, fertilization, or any general farming knowledge. Returns matching documents with similarity scores.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query text. Use keywords or a natural language question related to the topic being asked about.',
                    ],
                    'top_k' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return. Defaults to 5.',
                    ],
                ],
                'required' => ['query'],
            ],
        ],
    ];
}

function agtDispatchExtraTool(string $toolName, array $arguments, array $ctx): array
{
    switch ($toolName) {
        case 'search_knowledge_base': {
            $queryText = trim((string) ($arguments['query'] ?? ''));
            $topK = wmxClampInt($arguments['top_k'] ?? 5, 1, 20, 5);

            if ($queryText === '') {
                return ['error' => 'Query text is required.', 'results' => []];
            }

            $response = wmxApiRequest('kb-search.php', [], $ctx, [
                'query' => $queryText,
                'top_k' => $topK,
            ]);

            return [
                'query' => $queryText,
                'count' => count($response['data']),
                'results' => $response['data'],
            ];
        }

        default:
            return ['error' => 'Unsupported tool: ' . $toolName];
    }
}
