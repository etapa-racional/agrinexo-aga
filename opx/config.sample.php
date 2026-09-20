<?php
// agent_token is the only thing between the internet and a LAN GPU.

return [
    'endpoint' => 'http://192.168.1.203:11434/v1',

    'upstream_key' => '',

    'agent_token' => '',

    'allowed_models' => [
        'qwen3.6:35b',
        'muse-glimmer:30b',
        'qwen3.8:27b',
        'gemma4:26b',
        'gemma4:31b',
        'qwen3.6:27b',
    ],

    'extra_headers' => [
    ],

    'default_temperature' => 0.2,

    'upstream_timeout' => 110,

    'structured_output' => false,

    'log_payloads' => 'off',

    'log_max_chars' => 60000,
];
