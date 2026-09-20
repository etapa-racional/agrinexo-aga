<?php

// Each language installs as its own app; 'id' keeps them separate.
require_once __DIR__ . '/config-en.php';
require_once __DIR__ . '/msk-lang-helpers.php';

$cfg   = $GLOBALS['AGN_CFG_SHELL'];
$lang  = $GLOBALS['AGN_CFG_LANGUAGE']['code'];

$start = $cfg['shell'];

header('Content-Type: application/manifest+json; charset=utf-8');

echo json_encode([
    'id'               => 'agrinexo-aga-' . $lang,
    'name'             => t('shell.app_name'),
    'description'      => t('shell.app_title'),
    'lang'             => $GLOBALS['AGN_CFG_LANGUAGE']['code'],
    'dir'              => 'ltr',
    'start_url'        => $start,
    'scope'            => './',
    'display'          => 'standalone',
    'orientation'      => 'any',
    'theme_color'      => '#414C33',
    'background_color' => '#f0f2f5',
    'icons'            => [
        [
            'src'     => 'msk/icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => 'msk/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
