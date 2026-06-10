<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_shared/geometry_snapshot.php';

if (!function_exists('mss_polygon_build_map_image')) {
    function mss_polygon_build_map_image(
        array $points,
        string $name,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        string $basemap,
        ?array &$renderMeta = null
    ) {
        $fontPath = mss_font_path(true);
        $labelSize = $name === '' ? null : mss_measure_label($name, $fontPath, 14, mss_label_max_text_width($outputWidth, $padding));
        $labelMode = $name === '' ? 'none' : 'label';
        $layout = mss_geometry_build_layout_state($points, $outputWidth, $outputHeight, $padding, $labelSize, $labelMode, mss_basemap_max_zoom($basemap));
        [$image, $tileStats] = mss_geometry_prepare_image($layout, $outputWidth, $outputHeight, $basemap);
        $screenPoints = mss_geometry_screen_points($layout);

        mss_geometry_draw_polygon(
            $image,
            $screenPoints,
            mss_color($image, 39, 111, 191, 82),
            mss_color($image, 39, 111, 191, 20)
        );
        if ($name !== '') {
            mss_draw_label($image, $name, mss_geometry_screen_centroid($screenPoints), false, $fontPath);
        }

        $renderMeta = mss_geometry_finish_image($image, $layout, $tileStats, $basemap);
        return $image;
    }

    function mss_polygon_handle_request(array $params, array $options = array()): string
    {
        $common = mss_geometry_common_params($params);
        $name = mss_safe_text($params['name'] ?? null, '', 80);
        $cacheDirectory = (string) ($options['cache_dir'] ?? (mss_project_root() . '/cache/polygon'));
        $cacheTtlSeconds = mss_int_param($options['cache_ttl'] ?? MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, 60, 31536000);

        mss_ensure_directory($cacheDirectory);
        mss_prune_expired_cache_files($cacheDirectory, $cacheTtlSeconds);

        $points = mss_geometry_parse_points($params['points'] ?? null, 3, 100);
        $normalized = array(
            'points' => $points === null ? mss_utf8_truncate(trim((string) ($params['points'] ?? '')), 800) : mss_geometry_points_param($points),
            'name' => $name,
            'basemap' => $common['basemap'],
            'width' => $common['width'],
            'height' => $common['height'],
            'padding' => $common['padding'],
        );
        $cacheFilePath = $cacheDirectory . '/' . mss_geometry_cache_key('polygon', $normalized) . '.png';
        $cachedBytes = mss_read_png_cache($cacheFilePath, $cacheTtlSeconds);
        if ($cachedBytes !== null) {
            return $cachedBytes;
        }

        $renderMeta = array('cacheable' => false);
        if ($points === null) {
            $image = mss_build_error_image($common['width'], $common['height'], 'Missing or invalid points. Polygon needs at least 3 points.');
        } else {
            $image = mss_polygon_build_map_image($points, $name, $common['width'], $common['height'], $common['padding'], $common['basemap'], $renderMeta);
        }

        $bytes = mss_png_bytes($image);
        if (($renderMeta['cacheable'] ?? false) === true) {
            mss_write_cache_file($cacheFilePath, $bytes);
        }

        return $bytes;
    }
}
