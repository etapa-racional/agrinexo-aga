# www — public landing shells

Public marketing/landing shells per language (EN/PT/LAN) that host the AGT
chat widget (`AGA-PT26-AGT-*.php`) for anonymous visitors, plus PWA manifest
and service worker.

Plain PHP includes (`msk-*.php` fragments), no framework. Per-locale
`config-*.php` selects copy/endpoints. `msk-sw*.js`/`msk-manifest*.php` add
offline caching. `release.sh` prunes the non-PT locale files from the shipped
build.
