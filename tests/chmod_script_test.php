<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . '/chmod.sh';
$readme = $root . '/README.md';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function mode_of(string $path): int
{
    $mode = @fileperms($path);
    assert_true(is_int($mode), "can read mode for {$path}");

    return $mode & 0777;
}

function write_fixture_file(string $path, string $contents = 'x'): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    file_put_contents($path, $contents);
}

assert_true(is_file($script), 'chmod.sh exists');
assert_true(is_executable($script), 'chmod.sh is executable');
assert_true(strpos((string) file_get_contents($readme), './chmod.sh') !== false, 'README documents chmod.sh');

$fixture = sys_get_temp_dir() . '/map_snapshot_chmod_' . getmypid();
if (is_dir($fixture)) {
    exec('rm -rf ' . escapeshellarg($fixture));
}
mkdir($fixture, 0700, true);

mkdir($fixture . '/api', 0700, true);
mkdir($fixture . '/recipes/polygon', 0700, true);
mkdir($fixture . '/assets/images/map', 0700, true);
mkdir($fixture . '/cache/polygon', 0700, true);
mkdir($fixture . '/.git', 0700, true);

write_fixture_file($fixture . '/index.php', '<?php echo "ok";');
write_fixture_file($fixture . '/api/polygon.php', '<?php echo "ok";');
write_fixture_file($fixture . '/recipes/polygon/polygon_snapshot.php', '<?php echo "ok";');
write_fixture_file($fixture . '/assets/images/map/pin.png', 'png');
write_fixture_file($fixture . '/cache/.htaccess', 'Require all denied');
write_fixture_file($fixture . '/cache/polygon/secret.png', 'cache');
write_fixture_file($fixture . '/.git/config', 'private');

chmod($fixture . '/index.php', 0600);
chmod($fixture . '/api/polygon.php', 0600);
chmod($fixture . '/recipes/polygon/polygon_snapshot.php', 0600);
chmod($fixture . '/assets/images/map/pin.png', 0600);
chmod($fixture . '/cache/.htaccess', 0600);
chmod($fixture . '/cache/polygon/secret.png', 0600);
chmod($fixture . '/.git/config', 0600);

$command = 'bash ' . escapeshellarg($script) . ' ' . escapeshellarg($fixture);
exec($command . ' 2>&1', $output, $exitCode);
assert_true($exitCode === 0, 'chmod.sh exits successfully: ' . implode("\n", $output));

assert_true(mode_of($fixture) === 0755, 'project root directory is 0755');
assert_true(mode_of($fixture . '/api') === 0755, 'api directory is 0755');
assert_true(mode_of($fixture . '/recipes/polygon') === 0755, 'recipe directory is 0755');
assert_true(mode_of($fixture . '/index.php') === 0644, 'index.php is 0644');
assert_true(mode_of($fixture . '/api/polygon.php') === 0644, 'api script is 0644');
assert_true(mode_of($fixture . '/recipes/polygon/polygon_snapshot.php') === 0644, 'recipe include is 0644');
assert_true(mode_of($fixture . '/assets/images/map/pin.png') === 0644, 'asset file is 0644');
assert_true(mode_of($fixture . '/cache/.htaccess') === 0644, 'cache .htaccess is 0644');
assert_true(mode_of($fixture . '/cache/polygon/secret.png') === 0600, 'cache contents are left unchanged');
assert_true(mode_of($fixture . '/.git/config') === 0600, '.git contents are left unchanged');

exec('rm -rf ' . escapeshellarg($fixture));

echo "PASS\n";
