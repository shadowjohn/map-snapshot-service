<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$recipeFile = $root . '/recipes/two-point/two_point_snapshot.php';

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

assert_true(is_file($recipeFile), 'two-point recipe file exists');
require_once $recipeFile;

assert_true(function_exists('mss_two_point_handle_request'), 'two-point request handler is defined');
assert_true(mss_normalize_basemap(null) === 'osm', 'empty basemap defaults to osm');
assert_true(mss_normalize_basemap('EMAP5') === 'emap5', 'EMAP5 alias normalizes');
assert_true(mss_normalize_basemap('google') === 'google', 'google basemap normalizes');
assert_true(mss_normalize_basemap('mode map') === 'osm', 'unknown basemap falls back to osm');
assert_true(mss_basemap_definition('baidu')['enabled'] === false, 'baidu is registered as planned but disabled');

$cacheDir = sys_get_temp_dir() . '/map_snapshot_service_test_' . getmypid();
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0700, true);
}
foreach (glob($cacheDir . '/*.png') ?: array() as $file) {
    unlink($file);
}

$params = array(
    'sLatLon' => '24.1782252,120.6484168',
    'eLatLon' => '24.1111272,120.6100528',
    'sName' => '起點: 逢甲大學',
    'eName' => '目的地: ICC 辦公大樓',
    'basemap' => 'osm',
    'width' => '416',
    'height' => '416',
);

$first = mss_two_point_handle_request($params, array('cache_dir' => $cacheDir));
assert_png($first, 'valid two-point render returns PNG bytes');

$files = glob($cacheDir . '/*.png') ?: array();
assert_true(count($files) === 1, 'valid render writes one cache PNG');
assert_true(preg_match('/^[a-f0-9]{64}\.png$/', basename($files[0])) === 1, 'cache filename is sha256 hex PNG');

$second = mss_two_point_handle_request($params, array('cache_dir' => $cacheDir));
assert_true($first === $second, 'cached response matches initial render');

$googleBytes = mss_two_point_handle_request(array_merge($params, array('basemap' => 'google')), array('cache_dir' => $cacheDir));
assert_png($googleBytes, 'google basemap render returns PNG bytes');

$bad = mss_two_point_handle_request(array(
    'sLatLon' => 'not-a-coordinate',
    'eLatLon' => '24.1111272,120.6100528',
    'sName' => 'bad',
    'eName' => 'end',
), array('cache_dir' => $cacheDir));
assert_png($bad, 'invalid coordinate render returns PNG error image');

$cacheBeforeIncomplete = count(glob($cacheDir . '/*.png') ?: array());
$incomplete = mss_two_point_handle_request(array_merge($params, array('basemap' => 'baidu')), array('cache_dir' => $cacheDir));
assert_png($incomplete, 'incomplete tile render still returns PNG bytes');
$cacheAfterIncomplete = count(glob($cacheDir . '/*.png') ?: array());
assert_true($cacheBeforeIncomplete === $cacheAfterIncomplete, 'incomplete tile render is not written to snapshot cache');

echo "PASS\n";
