<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/renderer/map_snapshot_renderer.php';

if (!defined('MSS_GEOMETRY_MARKER_WIDTH')) {
    define('MSS_GEOMETRY_MARKER_WIDTH', 28.0);
    define('MSS_GEOMETRY_MARKER_HEIGHT', 40.0);
}

if (!function_exists('mss_geometry_parse_points')) {
    function mss_geometry_parse_points($value, int $minPoints, int $maxPoints = 100): ?array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $text = trim((string) ($value ?? ''));
            $parts = preg_split('/\s*[;\|\n]\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($parts)) {
            return null;
        }

        $points = array();
        foreach ($parts as $part) {
            $point = is_array($part) && isset($part['lat'], $part['lon'])
                ? array((float) $part['lat'], (float) $part['lon'])
                : mss_parse_latlon($part);
            if ($point === null) {
                return null;
            }

            $points[] = array('lat' => (float) $point[0], 'lon' => (float) $point[1]);
            if (count($points) > $maxPoints) {
                return null;
            }
        }

        return count($points) >= $minPoints ? $points : null;
    }

    function mss_geometry_points_param(array $points): string
    {
        $items = array();
        foreach ($points as $point) {
            $items[] = sprintf('%.7f,%.7f', (float) $point['lat'], (float) $point['lon']);
        }

        return implode(';', $items);
    }

    function mss_geometry_marker_bounds(array $worldPoint): array
    {
        return mss_rect(
            $worldPoint['x'] - (MSS_GEOMETRY_MARKER_WIDTH / 2.0),
            $worldPoint['y'] - MSS_GEOMETRY_MARKER_HEIGHT,
            MSS_GEOMETRY_MARKER_WIDTH,
            MSS_GEOMETRY_MARKER_HEIGHT
        );
    }

    function mss_geometry_point_padding_bounds(array $worldPoint, float $padding = 28.0): array
    {
        return mss_rect($worldPoint['x'] - $padding, $worldPoint['y'] - $padding, $padding * 2.0, $padding * 2.0);
    }

    function mss_geometry_label_bounds(array $worldPoint, array $labelSize, bool $alignLeft = false): array
    {
        $labelGap = 12.0;
        $labelHorizontalPadding = 8.0;
        $labelVerticalPadding = 5.0;
        $labelWidth = $labelSize['width'] + ($labelHorizontalPadding * 2.0);
        $labelHeight = $labelSize['height'] + ($labelVerticalPadding * 2.0);

        return mss_rect(
            $alignLeft ? $worldPoint['x'] - $labelGap - $labelWidth : $worldPoint['x'] + $labelGap,
            $worldPoint['y'] - ($labelHeight / 2.0),
            $labelWidth,
            $labelHeight
        );
    }

    function mss_geometry_build_labeled_points_layout_state(
        array $points,
        array $labelSizes,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        int $maxZoom = 20
    ): array {
        $selected = null;

        for ($zoom = min(max($maxZoom, 1), 22); $zoom >= 1; $zoom--) {
            $worldPoints = array();
            $contentBounds = null;
            foreach ($points as $index => $point) {
                $worldPoint = mss_latlon_to_world((float) $point['lat'], (float) $point['lon'], $zoom);
                $worldPoints[] = $worldPoint;
                $contentBounds = mss_rect_union($contentBounds, mss_geometry_point_padding_bounds($worldPoint));
                $contentBounds = mss_rect_union($contentBounds, mss_geometry_marker_bounds($worldPoint));
                if (isset($labelSizes[$index]) && is_array($labelSizes[$index])) {
                    $contentBounds = mss_rect_union($contentBounds, mss_geometry_label_bounds($worldPoint, $labelSizes[$index]));
                }
            }

            if ($contentBounds === null) {
                continue;
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

    function mss_geometry_build_layout_state(
        array $points,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        ?array $labelSize = null,
        string $labelMode = 'none',
        int $maxZoom = 20
    ): array {
        $selected = null;
        $labelGap = 12.0;
        $labelHorizontalPadding = 8.0;
        $labelVerticalPadding = 5.0;

        for ($zoom = min(max($maxZoom, 1), 22); $zoom >= 1; $zoom--) {
            $worldPoints = array();
            $contentBounds = null;
            foreach ($points as $point) {
                $worldPoint = mss_latlon_to_world((float) $point['lat'], (float) $point['lon'], $zoom);
                $worldPoints[] = $worldPoint;
                $contentBounds = mss_rect_union($contentBounds, mss_geometry_point_padding_bounds($worldPoint));
            }

            if ($labelSize !== null && count($worldPoints) > 0) {
                $anchor = count($worldPoints) === 1 ? $worldPoints[0] : mss_geometry_world_centroid($worldPoints);
                if ($labelMode === 'marker') {
                    $contentBounds = mss_rect_union($contentBounds, mss_geometry_marker_bounds($anchor));
                }

                if ($labelMode !== 'none') {
                    $labelWidth = $labelSize['width'] + ($labelHorizontalPadding * 2.0);
                    $labelHeight = $labelSize['height'] + ($labelVerticalPadding * 2.0);
                    $contentBounds = mss_rect_union($contentBounds, mss_rect(
                        $anchor['x'] + $labelGap,
                        $anchor['y'] - ($labelHeight / 2.0),
                        $labelWidth,
                        $labelHeight
                    ));
                }
            }

            if ($contentBounds === null) {
                continue;
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

    function mss_geometry_world_centroid(array $worldPoints): array
    {
        $x = 0.0;
        $y = 0.0;
        foreach ($worldPoints as $point) {
            $x += (float) $point['x'];
            $y += (float) $point['y'];
        }

        $count = max(count($worldPoints), 1);
        return array('x' => $x / $count, 'y' => $y / $count);
    }

    function mss_geometry_screen_points(array $layout): array
    {
        $points = array();
        foreach ($layout['worldPoints'] as $point) {
            $points[] = array(
                'x' => (float) $point['x'] - (float) $layout['originX'],
                'y' => (float) $point['y'] - (float) $layout['originY'],
            );
        }

        return $points;
    }

    function mss_geometry_screen_centroid(array $points): array
    {
        $x = 0.0;
        $y = 0.0;
        foreach ($points as $point) {
            $x += (float) $point['x'];
            $y += (float) $point['y'];
        }

        $count = max(count($points), 1);
        return array('x' => $x / $count, 'y' => $y / $count);
    }

    function mss_geometry_draw_marker($image, array $anchor, string $pinPath): void
    {
        $pinImage = mss_load_png($pinPath);
        if ($pinImage === null) {
            $fill = mss_color($image, 224, 53, 48, 12);
            $border = mss_color($image, 140, 20, 20, 38);
            $white = mss_color($image, 255, 255, 255);
            $x = (int) round($anchor['x']);
            $y = (int) round($anchor['y']);
            imagefilledpolygon($image, array($x - 6, $y - 13, $x + 6, $y - 13, $x, $y), 3, $fill);
            imagepolygon($image, array($x - 6, $y - 13, $x + 6, $y - 13, $x, $y), 3, $border);
            imagefilledellipse($image, $x, $y - 18, 18, 18, $fill);
            imageellipse($image, $x, $y - 18, 18, 18, $border);
            imagefilledellipse($image, $x, $y - 18, 8, 8, $white);
            return;
        }

        $drawHeight = MSS_GEOMETRY_MARKER_HEIGHT;
        $imageHeight = max(1, imagesy($pinImage));
        $imageWidth = max(1, imagesx($pinImage));
        $drawWidth = max(18.0, $drawHeight * ($imageWidth / $imageHeight));
        imagecopyresampled(
            $image,
            $pinImage,
            (int) round($anchor['x'] - ($drawWidth / 2.0)),
            (int) round($anchor['y'] - $drawHeight),
            0,
            0,
            (int) round($drawWidth),
            (int) round($drawHeight),
            $imageWidth,
            $imageHeight
        );
        imagedestroy($pinImage);
    }

    function mss_geometry_draw_polyline($image, array $points, int $color, int $width = 5): void
    {
        if (count($points) < 2) {
            return;
        }

        $previousThickness = 1;
        imagesetthickness($image, $width);
        for ($i = 1; $i < count($points); $i++) {
            imageline(
                $image,
                (int) round($points[$i - 1]['x']),
                (int) round($points[$i - 1]['y']),
                (int) round($points[$i]['x']),
                (int) round($points[$i]['y']),
                $color
            );
        }
        imagesetthickness($image, $previousThickness);
    }

    function mss_geometry_draw_polygon($image, array $points, int $fill, int $stroke): void
    {
        if (count($points) < 3) {
            return;
        }

        $flat = array();
        foreach ($points as $point) {
            $flat[] = (int) round($point['x']);
            $flat[] = (int) round($point['y']);
        }

        imagefilledpolygon($image, $flat, count($points), $fill);
        imagesetthickness($image, 4);
        for ($i = 0; $i < count($points); $i++) {
            $next = ($i + 1) % count($points);
            imageline(
                $image,
                (int) round($points[$i]['x']),
                (int) round($points[$i]['y']),
                (int) round($points[$next]['x']),
                (int) round($points[$next]['y']),
                $stroke
            );
        }
        imagesetthickness($image, 1);
    }

    function mss_geometry_prepare_image(array $layout, int $outputWidth, int $outputHeight, string $basemap): array
    {
        $image = imagecreatetruecolor($outputWidth, $outputHeight);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        if (function_exists('imageantialias')) {
            imageantialias($image, true);
        }

        imagefilledrectangle($image, 0, 0, $outputWidth, $outputHeight, mss_color($image, 255, 255, 255));
        $tileStats = mss_draw_map_tiles($image, $layout, $outputWidth, $outputHeight, $basemap);
        imagefilledrectangle($image, 0, 0, $outputWidth, $outputHeight, mss_color($image, 255, 255, 255, 97));

        return array($image, $tileStats);
    }

    function mss_geometry_finish_image($image, array $layout, array $tileStats, string $basemap): array
    {
        if (($tileStats['complete'] ?? false) !== true) {
            $message = ((int) ($tileStats['loaded'] ?? 0)) > 0
                ? sprintf('Map tiles partially unavailable (%d/%d)', (int) ($tileStats['loaded'] ?? 0), (int) ($tileStats['required'] ?? 0))
                : 'Map tiles unavailable';
            mss_draw_text_lines($image, array($message), mss_font_path(false), 11, 16.0, 14.0, 15, mss_color($image, 120, 80, 20, 23));
        }

        $centerLatLon = mss_world_to_latlon(
            (float) $layout['originX'] + (imagesx($image) / 2.0),
            (float) $layout['originY'] + (imagesy($image) / 2.0),
            (int) $layout['zoom']
        );
        mss_draw_attribution_box($image, (array) ($tileStats['provider'] ?? mss_basemap_definition($basemap)), mss_font_path(false));
        mss_draw_coord_box($image, $centerLatLon, mss_font_path(false));

        return array(
            'cacheable' => ($tileStats['complete'] ?? false) === true,
            'tileStats' => $tileStats,
        );
    }

    function mss_geometry_cache_key(string $recipeKey, array $normalized, string $assetVersion = ''): string
    {
        ksort($normalized);
        $raw = array($recipeKey, MSS_CACHE_VERSION, $assetVersion);
        foreach ($normalized as $key => $value) {
            $raw[] = $key . '=' . (string) $value;
        }

        return hash('sha256', implode('|', $raw));
    }

    function mss_geometry_common_params(array $params): array
    {
        return array(
            'width' => mss_int_param($params['width'] ?? null, 416, 320, 1024),
            'height' => mss_int_param($params['height'] ?? null, 416, 240, 1024),
            'padding' => mss_int_param($params['padding'] ?? null, 40, 10, 240),
            'basemap' => mss_normalize_basemap($params['basemap'] ?? ($params['mode'] ?? 'osm')),
        );
    }
}
