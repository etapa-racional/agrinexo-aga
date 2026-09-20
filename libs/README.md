# libs — vendored raster JS libraries

Vendored, unmodified browser JS libraries (`georaster`, `geoblaze`,
`georaster-layer-for-leaflet`) that `app/vegetation.php` loads to render
satellite raster layers on the Leaflet map.

No package manager, no build step — files copied as-is from upstream
releases and served as static assets. Update by dropping in a newer upstream
build, never by hand-editing these files.
