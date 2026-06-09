<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_shared/geometry_snapshot.php';

if (!function_exists('mss_line_points_from_params')) {
    function mss_line_points_from_params(array $params): ?array
    {
        if (isset($params['points'])) {
            return mss_geometry_parse_points($params['points'], 2, 100);
        }

        if (isset($params['sLatLon'], $params['eLatLon'])) {
            return mss_geometry_parse_points((string) $params['sLatLon'] . ';' . (string) $params['eLatLon'], 2, 2);
        }

        return null;
    }

    function mss_line_build_map_image(
        array $points,
        string $name,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        string $basemap,
        string $pinPath,
        ?array &$renderMeta = null
    ) {
        $fontPath = mss_font_path(true);
        $labelSize = mss_measure_label($name, $fontPath, 14, mss_label_max_text_width($outputWidth, $padding));
        $layout = mss_geometry_build_layout_state($points, $outputWidth, $outputHeight, $padding, $labelSize, 'label', mss_basemap_max_zoom($basemap));
        [$image, $tileStats] = mss_geometry_prepare_image($layout, $outputWidth, $outputHeight, $basemap);
        $screenPoints = mss_geometry_screen_points($layout);

        mss_geometry_draw_polyline($image, $screenPoints, mss_color($image, 255, 255, 255, 32), 10);
        mss_geometry_draw_polyline($image, $screenPoints, mss_color($image, 212, 60, 60, 8), 5);
        mss_geometry_draw_marker($image, $screenPoints[0], $pinPath);
        mss_geometry_draw_marker($image, $screenPoints[count($screenPoints) - 1], $pinPath);
        mss_draw_label($image, $name, mss_geometry_screen_centroid($screenPoints), false, $fontPath);

        $renderMeta = mss_geometry_finish_image($image, $layout, $tileStats, $basemap);
        return $image;
    }

    function mss_line_handle_request(array $params, array $options = array()): string
    {
        $common = mss_geometry_common_params($params);
        $name = mss_safe_text($params['name'] ?? null, 'Line');
        $pinPath = (string) ($options['pin_path'] ?? (mss_project_root() . '/assets/images/map/pin.png'));
        $cacheDirectory = (string) ($options['cache_dir'] ?? (mss_project_root() . '/cache/line'));
        $cacheTtlSeconds = mss_int_param($options['cache_ttl'] ?? MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, 60, 31536000);

        mss_ensure_directory($cacheDirectory);
        mss_prune_expired_cache_files($cacheDirectory, $cacheTtlSeconds);

        $points = mss_line_points_from_params($params);
        $normalized = array(
            'points' => $points === null ? mss_utf8_truncate(trim((string) ($params['points'] ?? '')), 600) : mss_geometry_points_param($points),
            'name' => $name,
            'basemap' => $common['basemap'],
            'width' => $common['width'],
            'height' => $common['height'],
            'padding' => $common['padding'],
        );
        $pinVersion = is_file($pinPath) ? (string) filemtime($pinPath) : '0';
        $cacheFilePath = $cacheDirectory . '/' . mss_geometry_cache_key('line', $normalized, $pinVersion) . '.png';
        $cachedBytes = mss_read_png_cache($cacheFilePath, $cacheTtlSeconds);
        if ($cachedBytes !== null) {
            return $cachedBytes;
        }

        $renderMeta = array('cacheable' => false);
        if ($points === null) {
            $image = mss_build_error_image($common['width'], $common['height'], 'Missing or invalid points. Format: lat,lon;lat,lon');
        } else {
            $image = mss_line_build_map_image($points, $name, $common['width'], $common['height'], $common['padding'], $common['basemap'], $pinPath, $renderMeta);
        }

        $bytes = mss_png_bytes($image);
        if (($renderMeta['cacheable'] ?? false) === true) {
            mss_write_cache_file($cacheFilePath, $bytes);
        }

        return $bytes;
    }
}
