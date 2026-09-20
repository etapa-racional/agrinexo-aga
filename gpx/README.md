# gpx — Gemini model proxy

Gemini model proxy (Cloud Run), sibling of `opx`. Accepts the
provider-agnostic message shape from `agt/assistant.php`, performs one Gemini
call, and returns it — including `thought_signature` handling opx has no
analogue for.

Python (`main.py`), containerised via `Dockerfile`, deployed with
`deploy.sh`. Stateless, one instance per Vertex project/key, same contract as
`opx` (`/generate`, `/health`). `smoke.py` exercises it like opx's PHP smoke
harness.
