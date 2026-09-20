# wmx — tool surface

Tool surface published to the agent and browser. Defines each tool's schema
and executes it by calling `api/` on the caller's behalf — it runs no SQL
itself.

`tools.php` dispatches to `api/*` forwarding the caller's Authorization
header and `krd`. `tool-validators.php` validates arguments before dispatch.
`webmcp.js`/`aibridge.js` expose the same tools in-browser (WebMCP) for `wna`
to call.
