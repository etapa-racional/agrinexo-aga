# opx — OpenAI-compatible model proxy

OpenAI-compatible model proxy. Wire-format boundary between
`agt/assistant.php` and one Ollama server or hosted OpenAI-compatible
account, relocated across a network hop. Sibling of `gpx`, same contract for
Gemini.

Stateless PHP, no session/DB. Routed via `index.php` (`/generate`,
`/health`), no mod_rewrite needed. One instance = one endpoint/key, set in
`config.php`; shared key checked against
`agt/config-ai-openai-compatible.php` via `X-Agent-Token`. `.htaccess` needs
`AllowOverride All`.
