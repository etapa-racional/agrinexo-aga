<?php

// Do not tidy into __DIR__: a shadow instance would serve the wrong dict.
require 'config-language.php';

function lang_code(): string
{
    return $GLOBALS['AGN_CFG_LANGUAGE']['code'] ?? 'en';
}

function lang_locale(): string
{
    return $GLOBALS['AGN_CFG_LANGUAGE']['locale'] ?? 'en-GB';
}

function t(string $key, array $vars = []): string
{
    static $missing = [];

    $strings = $GLOBALS['AGN_CFG_LANGUAGE']['strings'] ?? [];
    if (!isset($strings[$key])) {
        if (!isset($missing[$key])) {
            $missing[$key] = true;
            error_log('[lang] missing key "' . $key . '" in dictionary "' . lang_code() . '"');
        }
        return $key;
    }

    $text = $strings[$key];
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function th(string $key, array $vars = []): string
{
    return htmlspecialchars(t($key, $vars), ENT_QUOTES, 'UTF-8');
}

function tj(string $key, array $vars = []): string
{
    return json_encode(
        t($key, $vars),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
    );
}

function tv(string $key, array $vars = []): string
{
    $literal = "'" . addcslashes(t($key, $vars), "\\'") . "'";
    return htmlspecialchars($literal, ENT_COMPAT, 'UTF-8');
}
