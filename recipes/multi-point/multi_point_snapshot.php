<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_shared/geometry_snapshot.php';

if (!function_exists('mss_multi_point_parse_names')) {
    function mss_multi_point_parse_names($value, int $count): array
    {
        $rawNames = array();
        if (is_array($value)) {
            $rawNames = $value;
        } else {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                $parts = preg_split('/\s*[;\|\n]\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);
                $rawNames = is_array($parts) ? $parts : array();
            }
        }

        $names = array();
        for ($i = 0; $i < $count; $i++) {
            $defaultName = (string) ($i + 1);
            $names[] = mss_safe_text($rawNames[$i] ?? null, $defaultName, 40);
        }

        return $names;
    }

    function mss_multi_point_build_map_image(
        array $points,
        array $names,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        string $basemap,
        string $pinPath,
        ?array &$renderMeta = null
    ) {
        $fontPath = mss_font_path(true);
        $labelMaxWidth = mss_label_max_text_width($outputWidth, $padding);
        $labelSizes = array();
        foreach ($names as $name) {
            $labelSizes[] = mss_measure_label($name, $fontPath, 14, $labelMaxWidth);
        }

        $layout = mss_geometry_build_labeled_points_layout_state($points, $labelSizes, $outputWidth, $outputHeight, $padding, mss_basemap_max_zoom($basemap));
        [$image, $tileStats] = mss_geometry_prepare_image($layout, $outputWidth, $outputHeight, $basemap);
        $screenPoints = mss_geometry_screen_points($layout);

        foreach ($screenPoints as $point) {
            mss_geometry_draw_marker($image, $point, $pinPath);
        }

        foreach ($screenPoints as $index => $point) {
            mss_draw_label($image, $names[$index] ?? (string) ($index + 1), $point, false, $fontPath);
        }

        $renderMeta = mss_geometry_finish_image($image, $layout, $tileStats, $basemap);
        return $image;
    }

    function mss_multi_point_handle_request(array $params, array $options = array()): string
    {
        $common = mss_geometry_common_params($params);
        $pinPath = (string) ($options['pin_path'] ?? (mss_project_root() . '/assets/images/map/pin.png'));
        $cacheDirectory = (string) ($options['cache_dir'] ?? (mss_project_root() . '/cache/multi-point'));
        $cacheTtlSeconds = mss_int_param($options['cache_ttl'] ?? MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, 60, 31536000);

        mss_ensure_directory($cacheDirectory);
        mss_prune_expired_cache_files($cacheDirectory, $cacheTtlSeconds);

        $points = mss_geometry_parse_points($params['points'] ?? null, 2, 100);
        $names = $points === null ? array() : mss_multi_point_parse_names($params['names'] ?? ($params['labels'] ?? null), count($points));
        $normalized = array(
            'points' => $points === null ? mss_utf8_truncate(trim((string) ($params['points'] ?? '')), 800) : mss_geometry_points_param($points),
            'names' => implode(';', $names),
            'basemap' => $common['basemap'],
            'width' => $common['width'],
            'height' => $common['height'],
            'padding' => $common['padding'],
        );
        $pinVersion = is_file($pinPath) ? (string) filemtime($pinPath) : '0';
        $cacheFilePath = $cacheDirectory . '/' . mss_geometry_cache_key('multi-point', $normalized, $pinVersion) . '.png';
        $cachedBytes = mss_read_png_cache($cacheFilePath, $cacheTtlSeconds);
        if ($cachedBytes !== null) {
            return $cachedBytes;
        }

        $renderMeta = array('cacheable' => false);
        if ($points === null) {
            $image = mss_build_error_image($common['width'], $common['height'], 'Missing or invalid points. Multi point needs at least 2 points.');
        } else {
            $image = mss_multi_point_build_map_image($points, $names, $common['width'], $common['height'], $common['padding'], $common['basemap'], $pinPath, $renderMeta);
        }

        $bytes = mss_png_bytes($image);
        if (($renderMeta['cacheable'] ?? false) === true) {
            mss_write_cache_file($cacheFilePath, $bytes);
        }

        return $bytes;
    }
}
