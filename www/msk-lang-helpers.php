<?php

// String lookup for the www/ shell.
if (!function_exists('t')) {
    function t(string $key, array $vars = []): string
    {
        static $missing = [];

        $strings = $GLOBALS['AGN_CFG_LANGUAGE']['strings'] ?? [];
        if (!isset($strings[$key])) {
            if (!isset($missing[$key])) {
                $missing[$key] = true;
                error_log('[lang] missing key "' . $key . '" in dictionary "'
                    . ($GLOBALS['AGN_CFG_LANGUAGE']['code'] ?? '?') . '"');
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
}
