<?php

// An instance key must not match a model name's first ':' segment.

if (!isset($GLOBALS['AGN_CFG_AI_OPENAI_COMPATIBLE'])) {
$GLOBALS['AGN_CFG_AI_OPENAI_COMPATIBLE'] = [
    'default_instance' => 'lan',

    'instances' => [
        'lan' => [
            'agent_url' => getenv('AGRINEXO_AI_OPX_LAN_URL')
                ?: 'https://example.org/opx/index.php',

            'agent_token' => getenv('AGRINEXO_AI_OPX_LAN_TOKEN')
                ?: '',

            'models' => [
                'gemma4:26b',
                'gemma4:31b',
                'qwen3.8:27b',
            ],

            'temperature' => (float) (getenv('AGRINEXO_AI_OPX_LAN_TEMPERATURE') ?: 0.2),

            'debug_payloads' => (bool) (getenv('AGRINEXO_AI_OPX_LAN_DEBUG') ?: false),
        ],

        'openaicompatible' => [
            'agent_url' => getenv('AGRINEXO_AI_OPX_OPENAICOMPATIBLE_URL') ?: '',
            'agent_token' => getenv('AGRINEXO_AI_OPX_OPENAICOMPATIBLE_TOKEN') ?: '',
            'models' => [
                getenv('AGRINEXO_AI_OPENAICOMPATIBLE_MODEL') ?: 'provider/model-name',
            ],
            'temperature' => (float) (getenv('AGRINEXO_AI_OPX_OPENAICOMPATIBLE_TEMPERATURE') ?: 0.2),
            'debug_payloads' => (bool) (getenv('AGRINEXO_AI_OPX_OPENAICOMPATIBLE_DEBUG') ?: false),
        ],
    ],
];
}
return $GLOBALS['AGN_CFG_AI_OPENAI_COMPATIBLE'];
