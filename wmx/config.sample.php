<?php

// Instance axis is the tenant backend, not a language: eco_api_url differs.

return [
    'jwt_public_key' => require __DIR__ . '/pukkey.php',

    'eco_api_url' => 'https://agrinexo.pt/api',

    'api_timeout' => 60,

    'log_file' => '../log/query.txt',
];
