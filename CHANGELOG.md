# Changelog

## 0.3.2 - 2026-06-10

- Updated Line Snapshot to draw `sName` and `eName` endpoint labels like Two Point Snapshot.
- Added `lineNames` for per-segment line labels, accepting `|` or comma-separated values with empty segments allowed.
- Updated the Line demo, README example, and generated OSM line example images.

## 0.3.1 - 2026-06-10

- Replaced catalog recipe thumbnails with OSM snapshot PNGs generated through the service API.
- Added README example gallery for Single Point, Two Point, Multi Point, Line, and Polygon recipes.

## 0.3.0 - 2026-06-10

- Added Multi Point Snapshot recipe, demo page, and API endpoint.
- Added per-point label parsing through `names` or `labels`, with automatic numbering when labels are omitted.
- Added labeled point layout bounds so multi-point labels are included when choosing zoom and image origin.

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
