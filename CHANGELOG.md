# Changelog

## 0.2.0 - 2026-06-10

- Added Single Point Snapshot recipe, demo page, and API endpoint.
- Added Line Snapshot recipe, demo page, and API endpoint.
- Added Polygon Snapshot recipe, demo page, and API endpoint.
- Added shared geometry rendering helpers for point, line, and polygon recipes.
- Added basemap `max_zoom` handling so single-point snapshots do not request unsupported OSM zoom levels.
- Added a hidden fixture basemap and blank tile asset for stable tests that do not hammer public tile providers.

## 0.1.0 - 2026-06-10

- Added the first public catalog entry for Map Snapshot Service.
- Added `Two Point Snapshot` PHP renderer and API endpoint.
- Added GET and POST request support for `api/two-point.php`.
- Added selectable basemaps: OpenStreetMap, Google roadmap/satellite/terrain compatibility, and NLSC EMAP5.
- Added snapshot cache with `sha256` filenames and provider tile cache.
- Added safeguards for public hosting: provider allowlist, output size clamp, curl timeouts, rate limiting, bad-tile rejection, and incomplete snapshot cache skip.
- Added root `.htaccess` and `cache/.htaccess` protection for `.git`, dotfiles, `.superpowers`, and cache files.
- Added README, TODO, threat model, and lightweight PHP tests.
