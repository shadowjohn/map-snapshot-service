<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/recipes/single-point/single_point_snapshot.php';

$params = array_merge($_GET, $_POST);
$clientIdentifier = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');

if ($clientIdentifier !== 'cli' && mss_rate_limit_exceeded('api-single-point', $clientIdentifier)) {
    $image = mss_build_error_image(416, 240, 'Too many requests. Please try again later.');
    $bytes = mss_png_bytes($image);
    if (!headers_sent()) {
        http_response_code(429);
    }
} else {
    $bytes = mss_single_point_handle_request($params);
}

if (!headers_sent()) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . strlen($bytes));
}

echo $bytes;
