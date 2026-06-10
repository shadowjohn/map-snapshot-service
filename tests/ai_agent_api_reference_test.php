<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pagePath = $root . '/ai-agent-api.html';
$indexPath = $root . '/index.php';
$readmePath = $root . '/README.md';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assert_contains(string $haystack, string $needle, string $message): void
{
    assert_true(strpos($haystack, $needle) !== false, $message);
}

assert_true(is_file($pagePath), 'AI agent API reference page exists');

$page = (string) file_get_contents($pagePath);
$index = (string) file_get_contents($indexPath);
$readme = (string) file_get_contents($readmePath);

foreach (array(
    'api/single-point.php',
    'api/two-point.php',
    'api/multi-point.php',
    'api/line.php',
    'api/polygon.php',
) as $endpoint) {
    assert_contains($page, $endpoint, "reference page documents {$endpoint}");
}

foreach (array(
    'Prefer POST',
    'WGS84',
    'lat,lon',
    'image/png',
    'PNG signature',
    'report output directory',
    'basemap',
    'Do not bulk-render',
    'custom tile URLs',
) as $requiredText) {
    assert_contains($page, $requiredText, "reference page includes {$requiredText}");
}

assert_contains($index, 'href="ai-agent-api.html"', 'catalog top navigation links AI API reference');
assert_contains($index, '>AI API<', 'catalog top navigation uses visible AI API text');
assert_contains($readme, 'https://3wa.tw/demo/php/map/map-snapshot-service/ai-agent-api.html', 'README links public AI API reference');

echo "PASS\n";

