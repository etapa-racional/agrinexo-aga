# app — tenant-facing web UI

Tenant-facing web UI. Vue3/Quasar page shells for fields, crops, climate,
vegetation, water, weather, reports and the AI assistant, embedded per-tenant
via iframes keyed by `krd`.

PHP shells + Vue3/Quasar from CDN, no build step. JWT in localStorage, checked
by `auth-guard.php`. Tenant id passed as `window.parent.krd` across
same-origin iframes (no postMessage). `config.php` names the backend API base
URL.
