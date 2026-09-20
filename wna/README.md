# wna — browser extension (WebMCP nano-agent)

Browser extension (Manifest V3) — a WebMCP nano-agent that drives any page
registering tools via an OpenAI-compatible model, through a side panel UI.

Static JS/HTML, no build step. `content.js` runs in every frame to discover
page-registered tools; `background.js`/`sidepanel.js` hold the chat loop;
`config.js` holds endpoint/key. `release.sh` zips this folder into
`wna/wna.zip` for download.
