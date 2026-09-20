<?php

// A shell requires this; nothing else decides its language.

$GLOBALS['AGN_CFG_SHELLS']['en'] = ['file' => 'AGA-PT26-AGT-EN.php', 'label' => 'English'];

if (!isset($GLOBALS['AGN_CFG_SHELL'])) {
$GLOBALS['AGN_CFG_SHELL'] = [
    'shell'    => 'AGA-PT26-AGT-EN.php',

    'manifest' => 'msk-manifest.php',
    'offline'  => 'msk-offline.php',
    'sw'       => 'msk-sw.js',
];
}

require_once __DIR__ . '/msk-lang-en.php';

return $GLOBALS['AGN_CFG_SHELL'];
