<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/renderer/map_snapshot_renderer.php';

/**
 * Two Point Snapshot recipe.
 *
 * Inputs:
 * - sLatLon/eLatLon: WGS84 "lat,lon"
 * - sName/eName: labels drawn beside the marker(s)
 * - width/height/padding/basemap: rendering controls with conservative limits
 */

if (!defined('MSS_TWO_POINT_MARKER_WIDTH')) {
    define('MSS_TWO_POINT_MARKER_WIDTH', 28.0);
    define('MSS_TWO_POINT_MARKER_HEIGHT', 40.0);
}

if (!function_exists('mss_two_point_marker_bounds')) {
    function mss_two_point_marker_bounds(array $anchor): array
    {
        return mss_rect(
            $anchor['x'] - (MSS_TWO_POINT_MARKER_WIDTH / 2.0),
            $anchor['y'] - MSS_TWO_POINT_MARKER_HEIGHT,
            MSS_TWO_POINT_MARKER_WIDTH,
            MSS_TWO_POINT_MARKER_HEIGHT
        );
    }

    function mss_two_point_build_layout_state(
        float $sLat,
        float $sLon,
        float $eLat,
        float $eLon,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        array $startLabelSize,
        array $endLabelSize
    ): array {
        $selected = null;
        $labelGap = 12.0;
        $labelHorizontalPadding = 8.0;
        $labelVerticalPadding = 5.0;
        $sharedDistanceThreshold = 18.0;

        for ($zoom = 20; $zoom >= 1; $zoom--) {
            $startWorld = mss_latlon_to_world($sLat, $sLon, $zoom);
            $endWorld = mss_latlon_to_world($eLat, $eLon, $zoom);
            $dx = $endWorld['x'] - $startWorld['x'];
            $dy = $endWorld['y'] - $startWorld['y'];
            $useSharedAnchor = sqrt(($dx * $dx) + ($dy * $dy)) < $sharedDistanceThreshold;
            $sharedWorld = array(
                'x' => ($startWorld['x'] + $endWorld['x']) / 2.0,
                'y' => ($startWorld['y'] + $endWorld['y']) / 2.0,
            );

            $contentBounds = null;
            if ($useSharedAnchor) {
                $contentBounds = mss_rect_union($contentBounds, mss_two_point_marker_bounds($sharedWorld));
                $contentBounds = mss_rect_union($contentBounds, mss_rect(
                    $sharedWorld['x'] - $labelGap - ($startLabelSize['width'] + ($labelHorizontalPadding * 2.0)),
                    $sharedWorld['y'] - ($startLabelSize['height'] + ($labelVerticalPadding * 2.0)) / 2.0,
                    $startLabelSize['width'] + ($labelHorizontalPadding * 2.0),
                    $startLabelSize['height'] + ($labelVerticalPadding * 2.0)
                ));
                $contentBounds = mss_rect_union($contentBounds, mss_rect(
                    $sharedWorld['x'] + $labelGap,
                    $sharedWorld['y'] - ($endLabelSize['height'] + ($labelVerticalPadding * 2.0)) / 2.0,
                    $endLabelSize['width'] + ($labelHorizontalPadding * 2.0),
                    $endLabelSize['height'] + ($labelVerticalPadding * 2.0)
                ));
            } else {
                $contentBounds = mss_rect_union($contentBounds, mss_two_point_marker_bounds($startWorld));
                $contentBounds = mss_rect_union($contentBounds, mss_two_point_marker_bounds($endWorld));
                $contentBounds = mss_rect_union($contentBounds, mss_rect(
                    $startWorld['x'] - $labelGap - ($startLabelSize['width'] + ($labelHorizontalPadding * 2.0)),
                    $startWorld['y'] - ($startLabelSize['height'] + ($labelVerticalPadding * 2.0)) / 2.0,
                    $startLabelSize['width'] + ($labelHorizontalPadding * 2.0),
                    $startLabelSize['height'] + ($labelVerticalPadding * 2.0)
                ));
                $contentBounds = mss_rect_union($contentBounds, mss_rect(
                    $endWorld['x'] + $labelGap,
                    $endWorld['y'] - ($endLabelSize['height'] + ($labelVerticalPadding * 2.0)) / 2.0,
                    $endLabelSize['width'] + ($labelHorizontalPadding * 2.0),
                    $endLabelSize['height'] + ($labelVerticalPadding * 2.0)
                ));
            }

            $candidate = array(
                'zoom' => $zoom,
                'useSharedAnchor' => $useSharedAnchor,
                'contentBounds' => $contentBounds,
                'startWorld' => $startWorld,
                'endWorld' => $endWorld,
                'sharedWorld' => $sharedWorld,
            );

            $availableWidth = max($outputWidth - ($padding * 2), 1);
            $availableHeight = max($outputHeight - ($padding * 2), 1);
            $selected = $candidate;
            if ($contentBounds['width'] <= $availableWidth && $contentBounds['height'] <= $availableHeight) {
                break;
            }
        }

        if ($selected === null) {
            $sharedWorld = mss_latlon_to_world(($sLat + $eLat) / 2.0, ($sLon + $eLon) / 2.0, 1);
            $selected = array(
                'zoom' => 1,
                'useSharedAnchor' => true,
                'contentBounds' => mss_two_point_marker_bounds($sharedWorld),
                'startWorld' => $sharedWorld,
                'endWorld' => $sharedWorld,
                'sharedWorld' => $sharedWorld,
            );
        }

        $extraWidth = max(0.0, ($outputWidth - ($padding * 2)) - $selected['contentBounds']['width']);
        $extraHeight = max(0.0, ($outputHeight - ($padding * 2)) - $selected['contentBounds']['height']);

        $selected['originX'] = $selected['contentBounds']['left'] - $padding - ($extraWidth / 2.0);
        $selected['originY'] = $selected['contentBounds']['top'] - $padding - ($extraHeight / 2.0);

        return $selected;
    }

    function mss_two_point_draw_vector_marker($image, array $anchor): void
    {
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
    }

    function mss_two_point_draw_marker($image, array $anchor, $pinImage): void
    {
        if ($pinImage === null) {
            mss_two_point_draw_vector_marker($image, $anchor);
            return;
        }

        $drawHeight = MSS_TWO_POINT_MARKER_HEIGHT;
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
    }

    function mss_two_point_build_map_image(
        float $sLat,
        float $sLon,
        float $eLat,
        float $eLon,
        string $sName,
        string $eName,
        int $outputWidth,
        int $outputHeight,
        int $padding,
        string $basemap,
        string $pinPath,
        ?array &$renderMeta = null
    ) {
        $fontPath = mss_font_path(true);
        $maxTextWidth = mss_label_max_text_width($outputWidth, $padding);
        $startLabelSize = mss_measure_label($sName, $fontPath, 14, $maxTextWidth);
        $endLabelSize = mss_measure_label($eName, $fontPath, 14, $maxTextWidth);
        $layout = mss_two_point_build_layout_state($sLat, $sLon, $eLat, $eLon, $outputWidth, $outputHeight, $padding, $startLabelSize, $endLabelSize);

        $image = imagecreatetruecolor($outputWidth, $outputHeight);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        if (function_exists('imageantialias')) {
            imageantialias($image, true);
        }

        imagefilledrectangle($image, 0, 0, $outputWidth, $outputHeight, mss_color($image, 255, 255, 255));
        $tileStats = mss_draw_map_tiles($image, $layout, $outputWidth, $outputHeight, $basemap);
        imagefilledrectangle($image, 0, 0, $outputWidth, $outputHeight, mss_color($image, 255, 255, 255, 97));

        $centerLatLon = mss_world_to_latlon(
            $layout['originX'] + ($outputWidth / 2.0),
            $layout['originY'] + ($outputHeight / 2.0),
            (int) $layout['zoom']
        );
        $startPoint = array('x' => $layout['startWorld']['x'] - $layout['originX'], 'y' => $layout['startWorld']['y'] - $layout['originY']);
        $endPoint = array('x' => $layout['endWorld']['x'] - $layout['originX'], 'y' => $layout['endWorld']['y'] - $layout['originY']);
        $sharedPoint = array('x' => $layout['sharedWorld']['x'] - $layout['originX'], 'y' => $layout['sharedWorld']['y'] - $layout['originY']);
        $pinImage = mss_load_png($pinPath);

        if ($layout['useSharedAnchor']) {
            mss_two_point_draw_marker($image, $sharedPoint, $pinImage);
            mss_draw_label($image, $sName, $sharedPoint, true, $fontPath);
            mss_draw_label($image, $eName, $sharedPoint, false, $fontPath);
        } else {
            mss_two_point_draw_marker($image, $startPoint, $pinImage);
            mss_two_point_draw_marker($image, $endPoint, $pinImage);
            mss_draw_label($image, $sName, $startPoint, true, $fontPath);
            mss_draw_label($image, $eName, $endPoint, false, $fontPath);
        }

        if ($pinImage !== null) {
            imagedestroy($pinImage);
        }

        if (($tileStats['complete'] ?? false) !== true) {
            $message = ((int) ($tileStats['loaded'] ?? 0)) > 0
                ? sprintf('Map tiles partially unavailable (%d/%d)', (int) ($tileStats['loaded'] ?? 0), (int) ($tileStats['required'] ?? 0))
                : 'Map tiles unavailable';
            mss_draw_text_lines($image, array($message), mss_font_path(false), 11, 16.0, 14.0, 15, mss_color($image, 120, 80, 20, 23));
        }

        mss_draw_attribution_box($image, (array) ($tileStats['provider'] ?? mss_basemap_definition($basemap)), mss_font_path(false));
        mss_draw_coord_box($image, $centerLatLon, mss_font_path(false));
        $renderMeta = array(
            'cacheable' => ($tileStats['complete'] ?? false) === true,
            'tileStats' => $tileStats,
        );

        return $image;
    }

    function mss_two_point_build_cache_key(array $normalized, string $pinPath): string
    {
        $pinLastWrite = is_file($pinPath) ? (string) filemtime($pinPath) : '0';
        $raw = implode('|', array(
            $normalized['sLatLon'],
            $normalized['eLatLon'],
            $normalized['sName'],
            $normalized['eName'],
            $normalized['basemap'],
            (string) $normalized['width'],
            (string) $normalized['height'],
            (string) $normalized['padding'],
            $pinLastWrite,
            MSS_CACHE_VERSION,
            'two-point',
        ));

        return hash('sha256', $raw);
    }

    function mss_two_point_handle_request(array $params, array $options = array()): string
    {
        $outputWidth = mss_int_param($params['width'] ?? null, 416, 320, 1024);
        $outputHeight = mss_int_param($params['height'] ?? null, 416, 240, 1024);
        $padding = mss_int_param($params['padding'] ?? null, 40, 10, 240);
        $sName = mss_safe_text($params['sName'] ?? null, 'Start');
        $eName = mss_safe_text($params['eName'] ?? null, 'End');
        $basemap = mss_normalize_basemap($params['basemap'] ?? ($params['mode'] ?? 'osm'));
        $pinPath = (string) ($options['pin_path'] ?? (mss_project_root() . '/assets/images/map/pin.png'));
        $cacheDirectory = (string) ($options['cache_dir'] ?? (mss_project_root() . '/cache/two-point'));
        $cacheTtlSeconds = mss_int_param($options['cache_ttl'] ?? MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, MSS_DEFAULT_SNAPSHOT_CACHE_TTL_SECONDS, 60, 31536000);

        mss_ensure_directory($cacheDirectory);
        mss_prune_expired_cache_files($cacheDirectory, $cacheTtlSeconds);

        $normalized = array(
            'sLatLon' => mss_utf8_truncate(trim((string) ($params['sLatLon'] ?? '')), 80),
            'eLatLon' => mss_utf8_truncate(trim((string) ($params['eLatLon'] ?? '')), 80),
            'sName' => $sName,
            'eName' => $eName,
            'basemap' => $basemap,
            'width' => $outputWidth,
            'height' => $outputHeight,
            'padding' => $padding,
        );
        $cacheFilePath = $cacheDirectory . '/' . mss_two_point_build_cache_key($normalized, $pinPath) . '.png';
        $cachedBytes = mss_read_png_cache($cacheFilePath, $cacheTtlSeconds);
        if ($cachedBytes !== null) {
            return $cachedBytes;
        }

        $start = mss_parse_latlon($params['sLatLon'] ?? null);
        $end = mss_parse_latlon($params['eLatLon'] ?? null);
        $renderMeta = array('cacheable' => false);
        if ($start === null || $end === null) {
            $image = mss_build_error_image($outputWidth, $outputHeight, 'Missing or invalid sLatLon/eLatLon. Format: lat,lon');
        } else {
            $image = mss_two_point_build_map_image(
                $start[0],
                $start[1],
                $end[0],
                $end[1],
                $sName,
                $eName,
                $outputWidth,
                $outputHeight,
                $padding,
                $basemap,
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
