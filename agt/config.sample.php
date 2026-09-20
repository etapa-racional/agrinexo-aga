<?php

// Each agt instance needs its own complete copy - no inheritance.

return [
    'jwt_public_key' => require __DIR__ . '/pukkey.php',

    'eco_api_url'   => 'https://agrinexo.pt/api',
    'ops_api_url'   => 'https://agrinexo.pt/api',
    'ops_media_url' => 'https://agrinexo.pt',
    'app_url'       => 'https://agrinexo.pt/app',

    'api_timeout' => 60,


    'log_file' => '/var/log/agrinexo/agt.log',
];
