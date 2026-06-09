<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_shared/geometry_snapshot.php';

if (!function_exists('mss_single_point_build_map_image')) {
    function mss_single_point_build_map_image(
        array $point,
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
        $layout = mss_geometry_build_layout_state(array($point), $outputWidth, $outputHeight, $padding, $labelSize, 'marker', mss_basemap_max_zoom($basemap));
        [$image, $tileStats] = mss_geometry_prepare_image($layout, $outputWidth, $outputHeight, $basemap);

        $screenPoint = mss_geometry_screen_points($layout)[0];
        mss_geometry_draw_marker($image, $screenPoint, $pinPath);
        mss_draw_label($image, $name, $screenPoint, false, $fontPath);

        $renderMeta = mss_geometry_finish_image($image, $layout, $tileStats, $basemap);
        return $image;
    }

    function mss_single_point_handle_request(array $params, array $options = array()): string
    {
        $common = mss_geometry_common_params($params);
        $name = mss_safe_text($params['name'] ?? ($params['sName'] ?? null), 'Point');
        $pinPath = (string) ($options['pin_path'] ?? (mss_project_root() . '/assets/images/map/pin.png'));
        $cacheDirectory = (string) ($options['cache_dir'] ?? (mss_project_root() . '/cache/single-point'));
        $cacheTtlSeconds = mss_int_param($options['cache_ttl'] ?? MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, 60, 31536000);

        mss_ensure_directory($cacheDirectory);
        mss_prune_expired_cache_files($cacheDirectory, $cacheTtlSeconds);

        $latLon = mss_parse_latlon($params['latLon'] ?? ($params['sLatLon'] ?? null));
        $normalized = array(
            'latLon' => $latLon === null ? mss_utf8_truncate(trim((string) ($params['latLon'] ?? '')), 80) : sprintf('%.7f,%.7f', $latLon[0], $latLon[1]),
            'name' => $name,
            'basemap' => $common['basemap'],
            'width' => $common['width'],
            'height' => $common['height'],
            'padding' => $common['padding'],
        );
        $pinVersion = is_file($pinPath) ? (string) filemtime($pinPath) : '0';
        $cacheFilePath = $cacheDirectory . '/' . mss_geometry_cache_key('single-point', $normalized, $pinVersion) . '.png';
        $cachedBytes = mss_read_png_cache($cacheFilePath, $cacheTtlSeconds);
        if ($cachedBytes !== null) {
            return $cachedBytes;
        }

        $renderMeta = array('cacheable' => false);
        if ($latLon === null) {
            $image = mss_build_error_image($common['width'], $common['height'], 'Missing or invalid latLon. Format: lat,lon');
        } else {
            $image = mss_single_point_build_map_image(
                array('lat' => $latLon[0], 'lon' => $latLon[1]),
                $name,
                $common['width'],
                $common['height'],
                $common['padding'],
                $common['basemap'],
                $pinPath,
                $renderMeta
            );
        }

        $bytes = mss_png_bytes($image);
        if (($renderMeta['cacheable'] ?? false) === true) {
            mss_write_cache_file($cacheFilePath, $bytes);
        }

        return $bytes;
    }
}
