<?php

// No Google credential here; gpx's service account is the only identity.

if (!isset($GLOBALS['AGN_CFG_AI_GOOGLE_GEMINI'])) {
$GLOBALS['AGN_CFG_AI_GOOGLE_GEMINI'] = [
    'engine' => 'google_gemini',

    'agent_url' => getenv('AGRINEXO_AI_AGENT_URL')
        ?: '',

    'agent_token' => getenv('AGRINEXO_AI_AGENT_TOKEN')
        ?: '',

    'model' => getenv('AGRINEXO_AI_GEMINI_MODEL') ?: (getenv('AGRINEXO_AI_MODEL') ?: 'gemini-3.6-flash'),

    'temperature' => (float) (getenv('AGRINEXO_AI_GEMINI_TEMPERATURE') ?: (getenv('AGRINEXO_AI_TEMPERATURE') ?: 0.2)),
    'max_tool_roundtrips' => (int) (getenv('AGRINEXO_AI_GEMINI_MAX_TOOL_ROUNDTRIPS') ?: (getenv('AGRINEXO_AI_MAX_TOOL_ROUNDTRIPS') ?: 3)),

    'debug_payloads' => (bool) (getenv('AGRINEXO_AI_DEBUG_PAYLOADS') ?: false),
];
}
return $GLOBALS['AGN_CFG_AI_GOOGLE_GEMINI'];
