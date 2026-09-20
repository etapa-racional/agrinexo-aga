# agt — the agent layer

The agent layer. Owns the tool-calling loop (`assistant.php`/`agent.php`/
`chat.php`), conversation history and tenant auth, dispatching to LLM
providers through the `opx`/`gpx` proxies.

Stateless PHP request loop. `tools.php`/`tools-extra.php` define and run tools
against `api/`. `config-ai-google-gemini.php` and
`config-ai-openai-compatible.php` map model → proxy + shared key.
`pukkey.php` handles the key handshake; `webmcp-ask.js` bridges browser-run
tools.
