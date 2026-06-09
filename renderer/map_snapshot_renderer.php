<?php
declare(strict_types=1);

/**
 * Shared rendering helpers for Map Snapshot Service.
 *
 * This file intentionally uses plain PHP functions instead of a framework so
 * the service can run inside simple PHP hosting, cron jobs, and small demos.
 * Recipe files should keep product-specific layout decisions outside here.
 */

if (!defined('MSS_TILE_SIZE')) {
    define('MSS_TILE_SIZE', 256);
    define('MSS_CACHE_VERSION', 'mss-php-v3');
    define('MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS', 2592000);
    define('MSS_DEFAULT_TILE_CACHE_TTL_SECONDS', 604800);
    define('MSS_RATE_LIMIT_WINDOW_SECONDS', 60);
    define('MSS_RATE_LIMIT_MAX_REQUESTS', 60);
}

if (!function_exists('mss_project_root')) {
    function mss_project_root(): string
    {
        return dirname(__DIR__);
    }

    function mss_cache_root(): string
    {
        return mss_project_root() . '/cache';
    }

    function mss_ensure_directory(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        return @mkdir($directory, 0755, true);
    }

    function mss_cache_file_is_fresh(string $path, int $ttlSeconds): bool
    {
        if ($ttlSeconds <= 0 || !is_file($path) || !is_readable($path)) {
            return false;
        }

        $modifiedAt = @filemtime($path);
        if (!is_int($modifiedAt)) {
            return false;
        }

        return (time() - $modifiedAt) <= $ttlSeconds;
    }

    function mss_read_png_cache(string $path, int $ttlSeconds): ?string
    {
        if (!mss_cache_file_is_fresh($path, $ttlSeconds)) {
            return null;
        }

        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            @unlink($path);
            return null;
        }

        return $bytes;
    }

    function mss_write_cache_file(string $path, string $bytes): bool
    {
        $directory = dirname($path);
        if (!mss_ensure_directory($directory)) {
            return false;
        }

        try {
            $nonce = bin2hex(random_bytes(4));
        } catch (Throwable $exception) {
            $nonce = str_replace('.', '', uniqid('', true));
        }

        $tempFile = $path . '.tmp.' . getmypid() . '.' . $nonce;
        if (@file_put_contents($tempFile, $bytes, LOCK_EX) === false) {
            return false;
        }

        @chmod($tempFile, 0644);
        if (!@rename($tempFile, $path)) {
            @unlink($tempFile);
            return false;
        }

        return true;
    }

    function mss_prune_expired_cache_files(string $directory, int $ttlSeconds, string $suffix = '.png', int $maxChecks = 80): void
    {
        if ($ttlSeconds <= 0 || !is_dir($directory) || mt_rand(1, 20) !== 1) {
            return;
        }

        $checked = 0;
        foreach (glob($directory . '/*' . $suffix) ?: array() as $file) {
            if ($checked >= $maxChecks) {
                return;
            }

            $checked++;
            $modifiedAt = @filemtime($file);
            if (is_int($modifiedAt) && (time() - $modifiedAt) > $ttlSeconds) {
                @unlink($file);
            }
        }
    }

    function mss_rate_limit_exceeded(string $namespace, string $identifier, int $limit = MSS_RATE_LIMIT_MAX_REQUESTS, int $windowSeconds = MSS_RATE_LIMIT_WINDOW_SECONDS): bool
    {
        if ($identifier === '' || $limit <= 0 || $windowSeconds <= 0) {
            return false;
        }

        $bucket = (string) floor(time() / $windowSeconds);
        $directory = mss_cache_root() . '/rate-limits';
        if (!mss_ensure_directory($directory)) {
            return false;
        }
        mss_prune_expired_cache_files($directory, $windowSeconds * 3, '.count', 120);

        $key = hash('sha256', $namespace . '|' . $identifier . '|' . $bucket);
        $path = $directory . '/' . $key . '.count';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return false;
        }

        $count = 0;
        if (flock($handle, LOCK_EX)) {
            $raw = stream_get_contents($handle);
            $count = is_string($raw) && trim($raw) !== '' ? (int) trim($raw) : 0;
            $count++;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $count);
            fflush($handle);
            flock($handle, LOCK_UN);
        }

        fclose($handle);
        @chmod($path, 0644);

        return $count > $limit;
    }

    function mss_clamp(float $value, float $minValue, float $maxValue): float
    {
        return min(max($value, $minValue), $maxValue);
    }

    function mss_int_param($value, int $defaultValue, int $minValue, int $maxValue): int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT);
        if ($result === false || $result === null) {
            $result = $defaultValue;
        }

        return min(max((int) $result, $minValue), $maxValue);
    }

    function mss_utf8_truncate(string $text, int $maxChars): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return substr($text, 0, $maxChars);
        }

        if (count($chars) <= $maxChars) {
            return $text;
        }

        return implode('', array_slice($chars, 0, $maxChars));
    }

    function mss_safe_text($value, string $defaultValue, int $maxChars = 80): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text !== '' && preg_match('//u', $text) !== 1) {
            $text = '';
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text) ?? '';
        $text = mss_utf8_truncate($text, $maxChars);

        return $text === '' ? $defaultValue : $text;
    }

    /**
     * Parse "lat,lon" in WGS84 degrees. Invalid input returns null instead of
     * silently becoming 0,0; callers can render an error PNG.
     */
    function mss_parse_latlon($value): ?array
    {
        $parts = preg_split('/\s*,\s*/', trim((string) ($value ?? '')));
        if ($parts === false || count($parts) !== 2) {
            return null;
        }

        if (!is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }

        $lat = (float) $parts[0];
        $lon = (float) $parts[1];
        if (!is_finite($lat) || !is_finite($lon)) {
            return null;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            return null;
        }

        return array($lat, $lon);
    }

    function mss_latlon_to_world(float $latitude, float $longitude, int $zoom): array
    {
        $latitude = mss_clamp($latitude, -85.05112878, 85.05112878);
        $longitude = mss_clamp($longitude, -180.0, 180.0);

        $mapSize = MSS_TILE_SIZE * (2 ** $zoom);
        $x = ($longitude + 180.0) / 360.0 * $mapSize;
        $sinLatitude = sin($latitude * M_PI / 180.0);
        $y = (0.5 - log((1.0 + $sinLatitude) / (1.0 - $sinLatitude)) / (4.0 * M_PI)) * $mapSize;

        return array('x' => $x, 'y' => $y);
    }

    function mss_world_to_latlon(float $worldX, float $worldY, int $zoom): array
    {
        $mapSize = MSS_TILE_SIZE * (2 ** $zoom);
        $longitude = ($worldX / $mapSize * 360.0) - 180.0;
        $mercatorY = 0.5 - ($worldY / $mapSize);
        $latitude = 90.0 - (360.0 * atan(exp(-$mercatorY * 2.0 * M_PI)) / M_PI);

        return array('lat' => $latitude, 'lon' => $longitude);
    }

    function mss_rect(float $left, float $top, float $width, float $height): array
    {
        return array(
            'left' => $left,
            'top' => $top,
            'width' => max($width, 1.0),
            'height' => max($height, 1.0),
        );
    }

    function mss_rect_union(?array $current, array $next): array
    {
        if ($current === null) {
            return $next;
        }

        $left = min($current['left'], $next['left']);
        $top = min($current['top'], $next['top']);
        $right = max($current['left'] + $current['width'], $next['left'] + $next['width']);
        $bottom = max($current['top'] + $current['height'], $next['top'] + $next['height']);

        return mss_rect($left, $top, $right - $left, $bottom - $top);
    }

    function mss_label_max_text_width(int $outputWidth, int $padding): float
    {
        $availableWidth = max($outputWidth - ($padding * 2), 120);
        $computed = ($availableWidth - 80.0) / 2.0;

        return min(160.0, max(90.0, $computed));
    }

    function mss_font_path(bool $bold = false): ?string
    {
        static $regularFont = null;
        static $boldFont = null;

        if ($bold && $boldFont !== null) {
            return $boldFont;
        }

        if (!$bold && $regularFont !== null) {
            return $regularFont;
        }

        $candidates = $bold
            ? array(
                '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
                '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
                '/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
                '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            )
            : array(
                '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
                '/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
                '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            );

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                if ($bold) {
                    $boldFont = $candidate;
                    return $boldFont;
                }

                $regularFont = $candidate;
                return $regularFont;
            }
        }

        return null;
    }

    function mss_text_chars(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars !== false) {
            return $chars;
        }

        return str_split($text);
    }

    function mss_measure_line(string $text, ?string $fontPath, int $fontSize): float
    {
        if ($fontPath !== null && function_exists('imagettfbbox')) {
            $box = @imagettfbbox($fontSize, 0, $fontPath, $text);
            if (is_array($box)) {
                return (float) abs($box[2] - $box[0]);
            }
        }

        return (float) (strlen($text) * imagefontwidth(4));
    }

    function mss_trim_line_to_width(string $text, ?string $fontPath, int $fontSize, float $maxWidth): string
    {
        $chars = mss_text_chars($text);
        while (count($chars) > 0) {
            $candidate = implode('', $chars) . '...';
            if (mss_measure_line($candidate, $fontPath, $fontSize) <= $maxWidth) {
                return $candidate;
            }

            array_pop($chars);
        }

        return '...';
    }

    function mss_wrap_text(string $text, ?string $fontPath, int $fontSize, float $maxWidth, int $maxLines = 3): array
    {
        $lines = array();
        $paragraphs = explode("\n", str_replace(array("\r\n", "\r"), "\n", $text));

        foreach ($paragraphs as $paragraph) {
            $buffer = '';
            foreach (mss_text_chars($paragraph) as $char) {
                $candidate = $buffer . $char;
                if ($buffer !== '' && mss_measure_line($candidate, $fontPath, $fontSize) > $maxWidth) {
                    $lines[] = $buffer;
                    $buffer = $char;
                    if (count($lines) >= $maxLines) {
                        $lines[$maxLines - 1] = mss_trim_line_to_width($lines[$maxLines - 1], $fontPath, $fontSize, $maxWidth);
                        return $lines;
                    }
                } else {
                    $buffer = $candidate;
                }
            }

            if ($buffer !== '') {
                $lines[] = $buffer;
                if (count($lines) >= $maxLines) {
                    return array_slice($lines, 0, $maxLines);
                }
            }
        }

        return count($lines) > 0 ? $lines : array('');
    }

    function mss_measure_label(string $text, ?string $fontPath, int $fontSize, float $maxTextWidth): array
    {
        $lines = mss_wrap_text($text, $fontPath, $fontSize, $maxTextWidth);
        $width = 1.0;
        foreach ($lines as $line) {
            $width = max($width, min($maxTextWidth, mss_measure_line($line, $fontPath, $fontSize)));
        }

        $lineHeight = (int) ceil($fontSize * 1.45);

        return array(
            'lines' => $lines,
            'width' => $width,
            'height' => max(1.0, count($lines) * $lineHeight),
            'lineHeight' => $lineHeight,
        );
    }

    function mss_color($image, int $red, int $green, int $blue, int $alpha = 0): int
    {
        return imagecolorallocatealpha($image, $red, $green, $blue, min(max($alpha, 0), 127));
    }

    function mss_basemap_definitions(): array
    {
        return array(
            'osm' => array(
                'key' => 'osm',
                'label' => 'OpenStreetMap',
                'template' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                'referer' => 'https://www.openstreetmap.org/',
                'attribution' => '© OpenStreetMap contributors',
                'cache_ttl' => 604800,
                'max_zoom' => 19,
                'enabled' => true,
            ),
            'google' => array(
                'key' => 'google',
                'label' => 'Google Roadmap',
                'template' => 'https://mt1.google.com/vt?hl=zh-TW&gl=TW&lyrs=m&x={x}&y={y}&z={z}',
                'fallback_templates' => array(
                    'https://mt0.google.com/vt?hl=zh-TW&gl=TW&lyrs=m&x={x}&y={y}&z={z}',
                    'https://3wa.tw/MYNCHC_PNG/https://mt1.google.com/vt?hl=zh-TW&gl=TW&lyrs=m&x={x}&y={y}&z={z}',
                ),
                'referer' => 'https://www.google.com/',
                'attribution' => '© Google Maps',
                'cache_ttl' => 3600,
                'max_zoom' => 21,
                'enabled' => true,
            ),
            'google-satellite' => array(
                'key' => 'google-satellite',
                'label' => 'Google Satellite',
                'template' => 'https://mt1.google.com/vt?hl=zh-TW&gl=TW&lyrs=s&x={x}&y={y}&z={z}',
                'fallback_templates' => array(
                    'https://mt0.google.com/vt?hl=zh-TW&gl=TW&lyrs=s&x={x}&y={y}&z={z}',
                    'https://3wa.tw/MYNCHC_PNG/https://mt1.google.com/vt?hl=zh-TW&gl=TW&lyrs=s&x={x}&y={y}&z={z}',
                ),
                'referer' => 'https://www.google.com/',
                'attribution' => '© Google Maps',
                'cache_ttl' => 3600,
                'max_zoom' => 21,
                'enabled' => true,
            ),
            'google-terrain' => array(
                'key' => 'google-terrain',
                'label' => 'Google Terrain',
                'template' => 'https://mt1.google.com/vt?hl=zh-TW&gl=TW&lyrs=p&x={x}&y={y}&z={z}',
                'fallback_templates' => array(
                    'https://mt0.google.com/vt?hl=zh-TW&gl=TW&lyrs=p&x={x}&y={y}&z={z}',
                    'https://3wa.tw/MYNCHC_PNG/https://mt1.google.com/vt?hl=zh-TW&gl=TW&lyrs=p&x={x}&y={y}&z={z}',
                ),
                'referer' => 'https://www.google.com/',
                'attribution' => '© Google Maps',
                'cache_ttl' => 3600,
                'max_zoom' => 21,
                'enabled' => true,
            ),
            'emap5' => array(
                'key' => 'emap5',
                'label' => 'NLSC EMAP5',
                'template' => 'https://wmts.nlsc.gov.tw/wmts/EMAP5/default/GoogleMapsCompatible/{z}/{y}/{x}',
                'referer' => 'https://maps.nlsc.gov.tw/',
                'attribution' => '© NLSC',
                'cache_ttl' => 2592000,
                'max_zoom' => 20,
                'enabled' => true,
            ),
            'fixture' => array(
                'key' => 'fixture',
                'label' => 'Fixture Basemap',
                'template' => 'https://3wa.tw/demo/php/map/map-snapshot-service/assets/images/map/blank-tile.png',
                'referer' => 'https://3wa.tw/demo/php/map/map-snapshot-service/',
                'attribution' => 'Fixture',
                'cache_ttl' => 31536000,
                'max_zoom' => 20,
                'enabled' => true,
            ),
            'baidu' => array(
                'key' => 'baidu',
                'label' => 'Baidu Maps',
                'template' => '',
                'referer' => '',
                'enabled' => false,
                'reason' => 'Planned: Baidu needs a BD-09 tile/projection adapter before it can be rendered accurately.',
            ),
        );
    }

    function mss_normalize_basemap($value): string
    {
        $key = strtolower(trim((string) ($value ?? '')));
        $key = str_replace(array('_', ' '), '-', $key);
        if ($key === '') {
            return 'osm';
        }

        $aliases = array(
            'openstreetmap' => 'osm',
            'open-street-map' => 'osm',
            'osm' => 'osm',
            'map' => 'google',
            'm' => 'google',
            'roadmap' => 'google',
            'google' => 'google',
            'google-map' => 'google',
            'google-roadmap' => 'google',
            'sat' => 'google-satellite',
            's' => 'google-satellite',
            'satellite' => 'google-satellite',
            'googlesatellite' => 'google-satellite',
            'google-satellite' => 'google-satellite',
            'terrain' => 'google-terrain',
            'physical' => 'google-terrain',
            't' => 'google-terrain',
            'p' => 'google-terrain',
            'googlephysical' => 'google-terrain',
            'google-terrain' => 'google-terrain',
            'emap' => 'emap5',
            'emap5' => 'emap5',
            'nlsc' => 'emap5',
            'nlsc-emap5' => 'emap5',
            'baidu' => 'baidu',
            'fixture' => 'fixture',
        );

        return $aliases[$key] ?? 'osm';
    }

    function mss_basemap_definition(string $basemap): array
    {
        $definitions = mss_basemap_definitions();
        $key = mss_normalize_basemap($basemap);

        return $definitions[$key] ?? $definitions['osm'];
    }

    function mss_basemap_max_zoom(string $basemap): int
    {
        $definition = mss_basemap_definition($basemap);
        $maxZoom = filter_var($definition['max_zoom'] ?? null, FILTER_VALIDATE_INT);
        if ($maxZoom === false || $maxZoom === null) {
            return 20;
        }

        return min(max((int) $maxZoom, 1), 22);
    }

    function mss_build_tile_url_from_template(string $template, int $zoom, int $tileX, int $tileY): string
    {
        return strtr($template, array(
            '{z}' => (string) $zoom,
            '{x}' => (string) $tileX,
            '{y}' => (string) $tileY,
            '{Z}' => (string) $zoom,
            '{X}' => (string) $tileX,
            '{Y}' => (string) $tileY,
            '{TileMatrix}' => (string) $zoom,
            '{TileCol}' => (string) $tileX,
            '{TileRow}' => (string) $tileY,
        ));
    }

    function mss_build_tile_url(array $provider, int $zoom, int $tileX, int $tileY): string
    {
        return mss_build_tile_url_from_template((string) $provider['template'], $zoom, $tileX, $tileY);
    }

    function mss_provider_tile_templates(array $provider): array
    {
        $templates = array((string) ($provider['template'] ?? ''));
        foreach ((array) ($provider['fallback_templates'] ?? array()) as $template) {
            $templates[] = (string) $template;
        }

        return array_values(array_unique(array_filter($templates, static function (string $template): bool {
            return $template !== '';
        })));
    }

    function mss_tile_cache_path(array $provider, int $zoom, int $tileX, int $tileY): string
    {
        $providerKey = preg_replace('/[^a-z0-9-]+/', '-', (string) ($provider['key'] ?? 'unknown')) ?? 'unknown';
        $directory = mss_cache_root() . '/tiles/' . $providerKey . '/' . $zoom . '/' . $tileX;

        return $directory . '/' . $tileY . '.tile';
    }

    function mss_tile_cache_ttl(array $provider): int
    {
        $ttl = filter_var($provider['cache_ttl'] ?? null, FILTER_VALIDATE_INT);
        if ($ttl === false || $ttl === null) {
            return MSS_DEFAULT_TILE_CACHE_TTL_SECONDS;
        }

        return max(0, (int) $ttl);
    }

    function mss_image_from_cached_bytes(string $path, int $ttlSeconds)
    {
        if (!mss_cache_file_is_fresh($path, $ttlSeconds)) {
            return null;
        }

        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || $bytes === '') {
            @unlink($path);
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            @unlink($path);
            return null;
        }

        return $image;
    }

    /**
     * Fetch a single map tile from a whitelisted provider definition.
     *
     * Security note: caller input selects only a provider key. It never becomes
     * a URL, which prevents the renderer from becoming a generic HTTP proxy.
     */
    function mss_download_map_tile(int $zoom, int $tileX, int $tileY, array $provider)
    {
        if (!function_exists('curl_init') || ($provider['enabled'] ?? false) !== true) {
            return null;
        }

        $cachePath = mss_tile_cache_path($provider, $zoom, $tileX, $tileY);
        $ttlSeconds = mss_tile_cache_ttl($provider);
        $cachedImage = mss_image_from_cached_bytes($cachePath, $ttlSeconds);
        if ($cachedImage !== null) {
            return $cachedImage;
        }

        foreach (mss_provider_tile_templates($provider) as $template) {
            $url = mss_build_tile_url_from_template($template, $zoom, $tileX, $tileY);
            if ($url === '' || strpos($url, 'https://') !== 0) {
                continue;
            }

            for ($attempt = 0; $attempt < 2; $attempt++) {
                $ch = curl_init($url);
                if ($ch === false) {
                    continue;
                }

                $curlOptions = array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 2,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 7,
                    CURLOPT_USERAGENT => 'MapSnapshotService/1.0 (+https://3wa.tw/demo/php/map/map-snapshot-service/)',
                );
                if (($provider['referer'] ?? '') !== '') {
                    $curlOptions[CURLOPT_REFERER] = (string) $provider['referer'];
                }
                if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                    $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
                }

                curl_setopt_array($ch, $curlOptions);

                $bytes = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);

                if (!is_string($bytes) || $bytes === '' || $status >= 400) {
                    continue;
                }

                $image = @imagecreatefromstring($bytes);
                if ($image === false) {
                    continue;
                }

                mss_write_cache_file($cachePath, $bytes);
                mss_prune_expired_cache_files(dirname($cachePath), $ttlSeconds, '.tile', 20);

                return $image;
            }
        }

        return null;
    }

    function mss_draw_placeholder_tile($image, float $x, float $y): void
    {
        $fill = mss_color($image, 235, 238, 243);
        $border = mss_color($image, 205, 210, 216);
        imagefilledrectangle($image, (int) round($x), (int) round($y), (int) round($x + MSS_TILE_SIZE), (int) round($y + MSS_TILE_SIZE), $fill);
        imagerectangle($image, (int) round($x), (int) round($y), (int) round($x + MSS_TILE_SIZE), (int) round($y + MSS_TILE_SIZE), $border);
    }

    function mss_draw_map_tiles($image, array $layout, int $outputWidth, int $outputHeight, string $basemap): array
    {
        $provider = mss_basemap_definition($basemap);
        if (($provider['enabled'] ?? false) !== true) {
            return array(
                'required' => 0,
                'loaded' => 0,
                'missed' => 1,
                'complete' => false,
                'provider' => $provider,
            );
        }

        $tilesPerAxis = 2 ** (int) $layout['zoom'];
        $minTileX = max(0, (int) floor($layout['originX'] / MSS_TILE_SIZE));
        $maxTileX = min($tilesPerAxis - 1, (int) floor(($layout['originX'] + $outputWidth - 1) / MSS_TILE_SIZE));
        $minTileY = max(0, (int) floor($layout['originY'] / MSS_TILE_SIZE));
        $maxTileY = min($tilesPerAxis - 1, (int) floor(($layout['originY'] + $outputHeight - 1) / MSS_TILE_SIZE));
        $required = 0;
        $loaded = 0;
        $missed = 0;

        for ($tileY = $minTileY; $tileY <= $maxTileY; $tileY++) {
            for ($tileX = $minTileX; $tileX <= $maxTileX; $tileX++) {
                $required++;
                $drawX = ($tileX * MSS_TILE_SIZE) - $layout['originX'];
                $drawY = ($tileY * MSS_TILE_SIZE) - $layout['originY'];
                $tile = mss_download_map_tile((int) $layout['zoom'], $tileX, $tileY, $provider);
                if ($tile !== null) {
                    imagecopyresampled(
                        $image,
                        $tile,
                        (int) round($drawX),
                        (int) round($drawY),
                        0,
                        0,
                        MSS_TILE_SIZE,
                        MSS_TILE_SIZE,
                        imagesx($tile),
                        imagesy($tile)
                    );
                    imagedestroy($tile);
                    $loaded++;
                } else {
                    $missed++;
                    mss_draw_placeholder_tile($image, $drawX, $drawY);
                }
            }
        }

        return array(
            'required' => $required,
            'loaded' => $loaded,
            'missed' => $missed,
            'complete' => $required > 0 && $missed === 0,
            'provider' => $provider,
        );
    }

    function mss_rounded_rect($image, float $x, float $y, float $width, float $height, float $radius, int $fill, ?int $border = null): void
    {
        $x1 = (int) round($x);
        $y1 = (int) round($y);
        $x2 = (int) round($x + $width);
        $y2 = (int) round($y + $height);
        $radius = (int) round(min($radius, max(1.0, min($width, $height) / 2.0)));

        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $fill);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $fill);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $fill);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $fill);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $fill);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $fill);

        if ($border !== null) {
            imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $border);
            imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $border);
            imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $border);
            imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $border);
            imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $border);
            imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $border);
            imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $border);
            imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $border);
        }
    }

    function mss_load_png(string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $image = @imagecreatefrompng($path);
        return $image === false ? null : $image;
    }

    function mss_draw_text_lines($image, array $lines, ?string $fontPath, int $fontSize, float $x, float $y, int $lineHeight, int $color): void
    {
        if ($fontPath !== null && function_exists('imagettftext')) {
            $baseline = $y + $fontSize;
            foreach ($lines as $line) {
                imagettftext($image, $fontSize, 0, (int) round($x), (int) round($baseline), $color, $fontPath, $line);
                $baseline += $lineHeight;
            }
            return;
        }

        $lineY = (int) round($y);
        foreach ($lines as $line) {
            imagestring($image, 4, (int) round($x), $lineY, $line, $color);
            $lineY += imagefontheight(4) + 2;
        }
    }

    function mss_draw_label($image, string $text, array $anchor, bool $alignLeft, ?string $fontPath): void
    {
        $fontSize = 14;
        $textInfo = mss_measure_label($text, $fontPath, $fontSize, mss_label_max_text_width(imagesx($image), 0));
        $horizontalPadding = 8.0;
        $verticalPadding = 5.0;
        $gap = 12.0;
        $backgroundWidth = $textInfo['width'] + ($horizontalPadding * 2.0);
        $backgroundHeight = $textInfo['height'] + ($verticalPadding * 2.0);
        $backgroundX = $alignLeft ? $anchor['x'] - $gap - $backgroundWidth : $anchor['x'] + $gap;
        $backgroundY = $anchor['y'] - ($backgroundHeight / 2.0);

        $background = mss_color($image, 255, 255, 255, 13);
        $border = mss_color($image, 220, 40, 40, 48);
        $textColor = mss_color($image, 210, 30, 30, 18);

        mss_rounded_rect($image, $backgroundX, $backgroundY, $backgroundWidth, $backgroundHeight, 7.0, $background, $border);
        mss_draw_text_lines(
            $image,
            $textInfo['lines'],
            $fontPath,
            $fontSize,
            $backgroundX + $horizontalPadding,
            $backgroundY + $verticalPadding,
            (int) $textInfo['lineHeight'],
            $textColor
        );
    }

    function mss_draw_coord_box($image, array $centerLatLon, ?string $fontPath): void
    {
        $fontSize = 11;
        $lineHeight = (int) ceil($fontSize * 1.35);
        $text = sprintf('經緯度:%.6f %.6f', $centerLatLon['lon'], $centerLatLon['lat']);
        $width = mss_measure_line($text, $fontPath, $fontSize);
        $height = $lineHeight;
        $x = imagesx($image) - $width - 12.0;
        $y = imagesy($image) - $height - 8.0;

        $background = mss_color($image, 255, 255, 255, 42);
        $textColor = mss_color($image, 20, 20, 20, 17);
        imagefilledrectangle($image, (int) round($x), (int) round($y), (int) round($x + $width + 6.0), (int) round($y + $height + 4.0), $background);
        mss_draw_text_lines($image, array($text), $fontPath, $fontSize, $x + 3.0, $y + 2.0, $lineHeight, $textColor);
    }

    function mss_draw_attribution_box($image, array $provider, ?string $fontPath): void
    {
        $text = trim((string) ($provider['attribution'] ?? ''));
        if ($text === '') {
            return;
        }

        $fontSize = 10;
        $lineHeight = (int) ceil($fontSize * 1.35);
        $width = mss_measure_line($text, $fontPath, $fontSize);
        $x = 6.0;
        $y = imagesy($image) - $lineHeight - 8.0;

        $background = mss_color($image, 255, 255, 255, 42);
        $textColor = mss_color($image, 40, 52, 65, 19);
        imagefilledrectangle($image, (int) round($x), (int) round($y), (int) round($x + $width + 6.0), (int) round($y + $lineHeight + 4.0), $background);
        mss_draw_text_lines($image, array($text), $fontPath, $fontSize, $x + 3.0, $y + 2.0, $lineHeight, $textColor);
    }

    function mss_build_error_image(int $width, int $height, string $message)
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, $width, $height, mss_color($image, 245, 245, 245));

        $fontPath = mss_font_path(true);
        $textColor = mss_color($image, 180, 40, 40);
        $info = mss_measure_label($message, $fontPath, 14, max(120.0, $width - 40.0));
        mss_draw_text_lines($image, $info['lines'], $fontPath, 14, 20.0, 20.0, (int) $info['lineHeight'], $textColor);

        return $image;
    }

    function mss_png_bytes($image): string
    {
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
