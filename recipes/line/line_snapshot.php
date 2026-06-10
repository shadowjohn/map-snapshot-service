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

    function mss_line_endpoint_names(array $params): array
    {
        $rawName = trim((string) ($params['name'] ?? ''));
        $hasStartName = trim((string) ($params['sName'] ?? '')) !== '';
        $hasEndName = trim((string) ($params['eName'] ?? '')) !== '';

        if (!$hasStartName && !$hasEndName && $rawName !== '') {
            $parts = preg_split('/\s*(?:→|->|－>|-->|到|至)\s*/u', $rawName, 2);
            if (is_array($parts) && count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                return array(mss_safe_text($parts[0], 'Start', 60), mss_safe_text($parts[1], 'End', 60));
            }
        }

        return array(
            mss_safe_text($params['sName'] ?? null, 'Start', 60),
            mss_safe_text($params['eName'] ?? null, 'End', 60),
        );
    }

    function mss_line_parse_segment_names($value, int $segmentCount, string $fallbackName = ''): array
    {
        $rawNames = array();
        if (is_array($value)) {
            $rawNames = $value;
        } else {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                $parts = preg_split('/\s*[|,\n]\s*/u', $text, -1);
                $rawNames = is_array($parts) ? $parts : array();
            }
        }

        $names = array_fill(0, $segmentCount, '');
        for ($i = 0; $i < $segmentCount; $i++) {
            $names[$i] = mss_safe_text($rawNames[$i] ?? null, '', 40);
        }

        if (implode('', $names) === '' && $fallbackName !== '') {
            $middle = max(0, (int) floor(($segmentCount - 1) / 2));
            $names[$middle] = mss_safe_text($fallbackName, '', 60);
        }

        return $names;
    }

    function mss_line_world_midpoints(array $worldPoints): array
    {
        $midpoints = array();
        for ($i = 1; $i < count($worldPoints); $i++) {
            $midpoints[] = array(
                'x' => (((float) $worldPoints[$i - 1]['x']) + ((float) $worldPoints[$i]['x'])) / 2.0,
                'y' => (((float) $worldPoints[$i - 1]['y']) + ((float) $worldPoints[$i]['y'])) / 2.0,
            );
        }

        return $midpoints;
    }

    function mss_line_build_layout_state(
        array $points,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        array $startLabelSize,
        array $endLabelSize,
        array $segmentLabelSizes,
        int $maxZoom = 20
    ): array {
        $selected = null;

        for ($zoom = min(max($maxZoom, 1), 22); $zoom >= 1; $zoom--) {
            $worldPoints = array();
            $contentBounds = null;
            foreach ($points as $point) {
                $worldPoint = mss_latlon_to_world((float) $point['lat'], (float) $point['lon'], $zoom);
                $worldPoints[] = $worldPoint;
                $contentBounds = mss_rect_union($contentBounds, mss_geometry_point_padding_bounds($worldPoint));
            }

            $lastIndex = count($worldPoints) - 1;
            $contentBounds = mss_rect_union($contentBounds, mss_geometry_marker_bounds($worldPoints[0]));
            $contentBounds = mss_rect_union($contentBounds, mss_geometry_marker_bounds($worldPoints[$lastIndex]));
            $contentBounds = mss_rect_union($contentBounds, mss_geometry_label_bounds($worldPoints[0], $startLabelSize, true));
            $contentBounds = mss_rect_union($contentBounds, mss_geometry_label_bounds($worldPoints[$lastIndex], $endLabelSize, false));

            foreach (mss_line_world_midpoints($worldPoints) as $index => $midpoint) {
                if (isset($segmentLabelSizes[$index]) && is_array($segmentLabelSizes[$index])) {
                    $contentBounds = mss_rect_union($contentBounds, mss_geometry_label_bounds($midpoint, $segmentLabelSizes[$index], false));
                }
            }

            $candidate = array(
                'zoom' => $zoom,
                'worldPoints' => $worldPoints,
                'contentBounds' => $contentBounds,
            );

            $availableWidth = max($outputWidth - ($padding * 2), 1);
            $availableHeight = max($outputHeight - ($padding * 2), 1);
            $selected = $candidate;
            if ($contentBounds['width'] <= $availableWidth && $contentBounds['height'] <= $availableHeight) {
                break;
            }
        }

        if ($selected === null) {
            $worldPoint = mss_latlon_to_world((float) $points[0]['lat'], (float) $points[0]['lon'], 1);
            $selected = array(
                'zoom' => 1,
                'worldPoints' => array($worldPoint),
                'contentBounds' => mss_geometry_point_padding_bounds($worldPoint),
            );
        }

        $extraWidth = max(0.0, ($outputWidth - ($padding * 2)) - $selected['contentBounds']['width']);
        $extraHeight = max(0.0, ($outputHeight - ($padding * 2)) - $selected['contentBounds']['height']);
        $selected['originX'] = $selected['contentBounds']['left'] - $padding - ($extraWidth / 2.0);
        $selected['originY'] = $selected['contentBounds']['top'] - $padding - ($extraHeight / 2.0);

        return $selected;
    }

    function mss_line_build_map_image(
        array $points,
        string $sName,
        string $eName,
        array $segmentNames,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        string $basemap,
        string $pinPath,
        ?array &$renderMeta = null
    ) {
        $fontPath = mss_font_path(true);
        $labelMaxWidth = mss_label_max_text_width($outputWidth, $padding);
        $startLabelSize = mss_measure_label($sName, $fontPath, 14, $labelMaxWidth);
        $endLabelSize = mss_measure_label($eName, $fontPath, 14, $labelMaxWidth);
        $segmentLabelSizes = array();
        foreach ($segmentNames as $segmentName) {
            $segmentLabelSizes[] = $segmentName === '' ? null : mss_measure_label($segmentName, $fontPath, 14, $labelMaxWidth);
        }

        $layout = mss_line_build_layout_state($points, $outputWidth, $outputHeight, $padding, $startLabelSize, $endLabelSize, $segmentLabelSizes, mss_basemap_max_zoom($basemap));
        [$image, $tileStats] = mss_geometry_prepare_image($layout, $outputWidth, $outputHeight, $basemap);
        $screenPoints = mss_geometry_screen_points($layout);

        mss_geometry_draw_polyline($image, $screenPoints, mss_color($image, 255, 255, 255, 32), 10);
        mss_geometry_draw_polyline($image, $screenPoints, mss_color($image, 212, 60, 60, 8), 5);
        mss_geometry_draw_marker($image, $screenPoints[0], $pinPath);
        mss_geometry_draw_marker($image, $screenPoints[count($screenPoints) - 1], $pinPath);
        mss_draw_label($image, $sName, $screenPoints[0], true, $fontPath);
        mss_draw_label($image, $eName, $screenPoints[count($screenPoints) - 1], false, $fontPath);
        foreach (mss_line_world_midpoints($layout['worldPoints']) as $index => $midpoint) {
            $segmentName = $segmentNames[$index] ?? '';
            if ($segmentName === '') {
                continue;
            }

            mss_draw_label($image, $segmentName, array(
                'x' => (float) $midpoint['x'] - (float) $layout['originX'],
                'y' => (float) $midpoint['y'] - (float) $layout['originY'],
            ), false, $fontPath);
        }

        $renderMeta = mss_geometry_finish_image($image, $layout, $tileStats, $basemap);
        return $image;
    }

    function mss_line_handle_request(array $params, array $options = array()): string
    {
        $common = mss_geometry_common_params($params);
        [$sName, $eName] = mss_line_endpoint_names($params);
        $legacyName = mss_safe_text($params['name'] ?? null, '', 80);
        $pinPath = (string) ($options['pin_path'] ?? (mss_project_root() . '/assets/images/map/pin.png'));
        $cacheDirectory = (string) ($options['cache_dir'] ?? (mss_project_root() . '/cache/line'));
        $cacheTtlSeconds = mss_int_param($options['cache_ttl'] ?? MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, 60, 31536000);

        mss_ensure_directory($cacheDirectory);
        mss_prune_expired_cache_files($cacheDirectory, $cacheTtlSeconds);

        $points = mss_line_points_from_params($params);
        $segmentNames = $points === null ? array() : mss_line_parse_segment_names($params['lineNames'] ?? null, max(count($points) - 1, 1), $legacyName);
        $normalized = array(
            'points' => $points === null ? mss_utf8_truncate(trim((string) ($params['points'] ?? '')), 600) : mss_geometry_points_param($points),
            'sName' => $sName,
            'eName' => $eName,
            'lineNames' => implode('|', $segmentNames),
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
            $image = mss_line_build_map_image($points, $sName, $eName, $segmentNames, $common['width'], $common['height'], $common['padding'], $common['basemap'], $pinPath, $renderMeta);
        }

        $bytes = mss_png_bytes($image);
        if (($renderMeta['cacheable'] ?? false) === true) {
            mss_write_cache_file($cacheFilePath, $bytes);
        }

        return $bytes;
    }
}
