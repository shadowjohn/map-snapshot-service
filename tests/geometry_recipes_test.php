<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$recipes = array(
    'single-point' => array(
        'file' => $root . '/recipes/single-point/single_point_snapshot.php',
        'handler' => 'mss_single_point_handle_request',
        'valid' => array(
            'latLon' => '24.1782252,120.6484168',
            'name' => '逢甲大學',
            'basemap' => 'fixture',
            'width' => '416',
            'height' => '416',
        ),
        'invalid' => array(
            'latLon' => 'not-a-coordinate',
            'name' => 'bad',
        ),
    ),
    'multi-point' => array(
        'file' => $root . '/recipes/multi-point/multi_point_snapshot.php',
        'handler' => 'mss_multi_point_handle_request',
        'valid' => array(
            'points' => '24.1782252,120.6484168;24.1111272,120.6100528;24.1700000,120.6500000',
            'names' => '逢甲大學;ICC 辦公大樓;測試點',
            'basemap' => 'fixture',
            'width' => '416',
            'height' => '416',
        ),
        'invalid' => array(
            'points' => '24.1782252,120.6484168',
            'names' => 'bad',
        ),
    ),
    'line' => array(
        'file' => $root . '/recipes/line/line_snapshot.php',
        'handler' => 'mss_line_handle_request',
        'valid' => array(
            'points' => '24.1782252,120.6484168;24.1500000,120.6300000;24.1111272,120.6100528',
            'sName' => '逢甲大學',
            'eName' => 'ICC 辦公大樓',
            'lineNames' => '5km|2km',
            'basemap' => 'fixture',
            'width' => '416',
            'height' => '416',
        ),
        'invalid' => array(
            'points' => '24.1782252,120.6484168',
            'name' => 'bad',
        ),
    ),
    'polygon' => array(
        'file' => $root . '/recipes/polygon/polygon_snapshot.php',
        'handler' => 'mss_polygon_handle_request',
        'valid' => array(
            'points' => '24.1835000,120.6422000;24.1835000,120.6578000;24.1722000,120.6578000;24.1722000,120.6422000',
            'name' => '逢甲周邊範圍',
            'basemap' => 'fixture',
            'width' => '416',
            'height' => '416',
        ),
        'invalid' => array(
            'points' => '24.1782252,120.6484168;24.1111272,120.6100528',
            'name' => 'bad',
        ),
    ),
);

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assert_png(string $bytes, string $message): void
{
    assert_true(substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n", $message);
}

foreach ($recipes as $name => $recipe) {
    assert_true(is_file($recipe['file']), "{$name} recipe file exists");
    require_once $recipe['file'];
    assert_true(function_exists($recipe['handler']), "{$name} handler exists");
}

foreach ($recipes as $name => $recipe) {
    $cacheDir = sys_get_temp_dir() . '/map_snapshot_service_' . $name . '_' . getmypid();
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0700, true);
    }
    foreach (glob($cacheDir . '/*.png') ?: array() as $file) {
        unlink($file);
    }

    $handler = $recipe['handler'];
    $first = $handler($recipe['valid'], array('cache_dir' => $cacheDir));
    assert_png($first, "{$name} valid render returns PNG bytes");

    $files = glob($cacheDir . '/*.png') ?: array();
    assert_true(count($files) === 1, "{$name} valid render writes one cache PNG");
    assert_true(preg_match('/^[a-f0-9]{64}\.png$/', basename($files[0])) === 1, "{$name} cache filename is sha256 hex PNG");

    $second = $handler($recipe['valid'], array('cache_dir' => $cacheDir));
    assert_true($first === $second, "{$name} cached response matches initial render");

    $bad = $handler($recipe['invalid'], array('cache_dir' => $cacheDir));
    assert_png($bad, "{$name} invalid input returns PNG error image");

    $cacheBeforeIncomplete = count(glob($cacheDir . '/*.png') ?: array());
    $incomplete = $handler(array_merge($recipe['valid'], array('basemap' => 'baidu')), array('cache_dir' => $cacheDir));
    assert_png($incomplete, "{$name} incomplete tile render still returns PNG bytes");
    $cacheAfterIncomplete = count(glob($cacheDir . '/*.png') ?: array());
    assert_true($cacheBeforeIncomplete === $cacheAfterIncomplete, "{$name} incomplete tile render is not cached");
}

$lineCacheDir = sys_get_temp_dir() . '/map_snapshot_service_line_params_' . getmypid();
if (!is_dir($lineCacheDir)) {
    mkdir($lineCacheDir, 0700, true);
}
foreach (glob($lineCacheDir . '/*.png') ?: array() as $file) {
    unlink($file);
}

$lineBase = array(
    'points' => '24.1782252,120.6484168;24.1500000,120.6300000;24.1111272,120.6100528',
    'sName' => '逢甲大學',
    'eName' => 'ICC 辦公大樓',
    'lineNames' => '5km|2km',
    'basemap' => 'fixture',
    'width' => '416',
    'height' => '416',
);
mss_line_handle_request($lineBase, array('cache_dir' => $lineCacheDir));
mss_line_handle_request(array_merge($lineBase, array('lineNames' => '5km,3km')), array('cache_dir' => $lineCacheDir));
mss_line_handle_request(array_merge($lineBase, array('sName' => '逢甲校門')), array('cache_dir' => $lineCacheDir));
assert_true(count(glob($lineCacheDir . '/*.png') ?: array()) === 3, 'line cache key includes sName/eName/lineNames');

echo "PASS\n";
