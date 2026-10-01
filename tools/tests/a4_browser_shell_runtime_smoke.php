<?php

declare(strict_types=1);

/**
 * A4.3 local-only browser runtime smoke.
 *
 * It copies real shell assets into a disposable document root, starts PHP's
 * loopback-only server, and asks Chrome to render desktop and mobile fixtures.
 */
$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
$chromeCandidates = [
    '/usr/bin/google-chrome-stable',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
];
$chrome = '';
foreach ($chromeCandidates as $candidate) {
    if (is_file($candidate) && is_executable($candidate)) {
        $chrome = $candidate;
        break;
    }
}
$sourceOnly = in_array('--source-only', $argv ?? [], true);
$checks = 0;
$failures = [];
$tempBase = sys_get_temp_dir();
$tempRoot = '';
$serverProcess = null;
$serverPipes = [];

function a4BrowserCheck(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    }
}

function a4BrowserRemoveTree(string $path): void
{
    global $tempBase;
    $base = rtrim($tempBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'finance-a4-browser-';
    if ($path === '' || strpos($path, $base) !== 0 || !is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isLink() || $item->isFile()) {
            @unlink($item->getPathname());
        } else {
            @rmdir($item->getPathname());
        }
    }
    @rmdir($path);
}

function a4BrowserStopServer(): void
{
    global $serverProcess, $serverPipes;
    foreach ($serverPipes as $pipe) {
        if (is_resource($pipe)) {
            @fclose($pipe);
        }
    }
    $serverPipes = [];
    if (is_resource($serverProcess)) {
        $status = proc_get_status($serverProcess);
        if (!empty($status['running'])) {
            @proc_terminate($serverProcess, 15);
            if (function_exists('posix_kill') && !empty($status['pid'])) {
                @posix_kill((int)$status['pid'], 15);
            }
            usleep(150000);
            $status = proc_get_status($serverProcess);
            if (!empty($status['running'])) {
                @proc_terminate($serverProcess, 9);
            }
        }
        @proc_close($serverProcess);
    }
    $serverProcess = null;
}

function a4BrowserCleanup(): void
{
    global $tempRoot;
    a4BrowserStopServer();
    a4BrowserRemoveTree($tempRoot);
    $tempRoot = '';
}

function a4BrowserRun(
    array $command,
    array $environment,
    int $timeoutSeconds = 45,
    ?string $completionFile = null,
    int $minimumCompletionBytes = 1000
): array
{
    $pipes = [];
    $process = @proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, $environment);
    if (!is_resource($process)) {
        return ['exit' => 127, 'stdout' => '', 'stderr' => 'proc_open failed', 'timed_out' => true, 'rendered' => false];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $started = microtime(true);
    $timedOut = false;
    $rendered = false;
    $exit = null;
    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (empty($status['running'])) {
            $exit = (int)$status['exitcode'];
            break;
        }
        if (
            $completionFile !== null
            && is_file($completionFile)
            && (int)filesize($completionFile) > $minimumCompletionBytes
        ) {
            // Chrome sometimes keeps an idle headless process alive after a
            // screenshot is complete on constrained staging hosts. The image
            // plus the loopback server log are the actual render evidence; end
            // the disposable child instead of converting a successful render
            // into a timeout failure.
            $rendered = true;
            proc_terminate($process, 15);
            usleep(150000);
            $status = proc_get_status($process);
            if (!empty($status['running'])) {
                proc_terminate($process, 9);
            }
            $exit = 0;
            break;
        }
        if ((microtime(true) - $started) > $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process, 15);
            usleep(150000);
            $status = proc_get_status($process);
            if (!empty($status['running'])) {
                proc_terminate($process, 9);
            }
            break;
        }
        usleep(50000);
    }
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closedExit = proc_close($process);
    if (($exit === null || $exit < 0) && $closedExit >= 0) {
        $exit = $closedExit;
    }
    if ($exit === 124) {
        $timedOut = true;
    }
    if (
        !$rendered
        && $completionFile !== null
        && is_file($completionFile)
        && (int)filesize($completionFile) > $minimumCompletionBytes
    ) {
        $rendered = true;
    }
    return [
        'exit' => $exit ?? 1,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'timed_out' => $timedOut,
        'rendered' => $rendered,
    ];
}

function a4BrowserReservePort(): int
{
    $errno = 0;
    $error = '';
    $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!is_resource($socket)) {
        return 0;
    }
    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    if (!is_string($name) || !preg_match('/:(\d+)$/', $name, $match)) {
        return 0;
    }
    return (int)$match[1];
}

function a4BrowserWaitForServer(int $port, int $attempts = 80): bool
{
    for ($i = 0; $i < $attempts; $i++) {
        $errno = 0;
        $error = '';
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.15);
        if (is_resource($socket)) {
            fwrite($socket, "GET /index.html?probe=startup HTTP/1.0\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
            $response = stream_get_contents($socket);
            fclose($socket);
            return strpos($response, '200 OK') !== false && strpos($response, 'finance-a4-browser-marker') !== false;
        }
        usleep(50000);
    }
    return false;
}

register_shutdown_function('a4BrowserCleanup');

try {
    a4BrowserCheck(PHP_SAPI === 'cli', 'browser smoke runs only from CLI');
    a4BrowserCheck(is_file($php) && is_executable($php), 'PHP CLI binary is executable');
    if (!$sourceOnly) {
        a4BrowserCheck($chrome !== '', 'Chrome/Chromium binary is executable');
    }
    a4BrowserCheck(function_exists('proc_open'), 'proc_open is available for isolated child processes');

    $sourceAssets = [
        'assets/css/theme-custom.css' => $root . '/assets/css/theme-custom.css',
        'assets/js/app.js' => $root . '/assets/js/app.js',
    ];
    foreach ($sourceAssets as $relative => $source) {
        a4BrowserCheck(is_file($source) && is_readable($source), 'real shell asset is readable: ' . $relative);
    }
    if ($failures !== []) {
        throw new RuntimeException('Prerequisite check failed.');
    }

    $tempRoot = rtrim($tempBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'finance-a4-browser-' . getmypid() . '-' . bin2hex(random_bytes(5));
    $directories = [
        $tempRoot,
        $tempRoot . '/assets',
        $tempRoot . '/assets/css',
        $tempRoot . '/assets/js',
        $tempRoot . '/profiles',
        $tempRoot . '/profiles/desktop',
        $tempRoot . '/profiles/mobile',
        $tempRoot . '/runtime-home',
    ];
    foreach ($directories as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create fixture directory: ' . $directory);
        }
    }
    foreach ($sourceAssets as $relative => $source) {
        if (!copy($source, $tempRoot . '/' . $relative)) {
            throw new RuntimeException('Cannot copy real shell asset: ' . $relative);
        }
    }

    $fixtureHtml = <<<'HTML'
<!doctype html>
<html lang="id" data-a4-shell="finance-a4-browser-marker">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'">
  <title>A4 Finance Shell Runtime</title>
  <link rel="stylesheet" href="/assets/css/theme-custom.css">
</head>
<body>
  <main class="finance-page-shell" id="finance-a4-browser-marker" data-module="people">
    <header class="finance-page-header"><h1>A4 Browser Shell</h1><p>Desktop dan mobile local fixture</p></header>
    <section class="finance-card"><div class="finance-card-body">
      <div class="finance-action-bar"><button type="button" class="btn finance-icon-action">OK</button></div>
      <div class="finance-table-region"><table><tbody><tr><td id="runtime-marker">runtime-pending</td></tr></tbody></table></div>
    </div></section>
  </main>
  <script src="/assets/js/app.js"></script>
  <script src="/fixture-runtime.js"></script>
</body>
</html>
HTML;
    $fixtureJs = <<<'JS'
(() => {
  const marker = document.getElementById('runtime-marker');
  if (marker) {
    marker.textContent = 'runtime-loaded';
    marker.dataset.viewport = window.innerWidth < 768 ? 'mobile' : 'desktop';
  }
})();
JS;
    if (file_put_contents($tempRoot . '/index.html', $fixtureHtml, LOCK_EX) === false
        || file_put_contents($tempRoot . '/fixture-runtime.js', $fixtureJs, LOCK_EX) === false
    ) {
        throw new RuntimeException('Cannot write local browser fixture.');
    }
    a4BrowserCheck(strpos($fixtureHtml, 'finance-a4-browser-marker') !== false, 'fixture contains stable HTML load marker');
    a4BrowserCheck(strpos($fixtureHtml, 'Content-Security-Policy') !== false, 'fixture declares a restrictive CSP');
    a4BrowserCheck(strpos($fixtureHtml, '/assets/css/theme-custom.css') !== false, 'fixture links copied real theme CSS');
    a4BrowserCheck(strpos($fixtureHtml, '/assets/js/app.js') !== false, 'fixture links copied real application JS');

    if ($sourceOnly) {
        a4BrowserCleanup();
        echo 'A4.3 browser shell source contract passed (' . $checks . ' checks; runtime render not executed).' . PHP_EOL;
        exit(0);
    }

    $port = a4BrowserReservePort();
    if ($port <= 1024 || $port > 65535) {
        throw new RuntimeException('Cannot reserve a safe ephemeral loopback port.');
    }
    $serverLog = $tempRoot . '/php-server.log';
    $serverPipes = [];
    $serverProcess = proc_open(
        [$php, '-S', '127.0.0.1:' . $port, '-t', $tempRoot],
        [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'ab'], 2 => ['file', $serverLog, 'ab']],
        $serverPipes,
        $tempRoot,
        ['PATH' => (string)getenv('PATH')]
    );
    if (!is_resource($serverProcess)) {
        throw new RuntimeException('Cannot start local PHP fixture server.');
    }
    if (isset($serverPipes[0]) && is_resource($serverPipes[0])) {
        fclose($serverPipes[0]);
        unset($serverPipes[0]);
    }
    a4BrowserCheck(a4BrowserWaitForServer($port), 'loopback fixture serves the HTML marker');

    $fixtureUrl = 'http://127.0.0.1:' . $port . '/index.html';
    a4BrowserCheck(strpos($fixtureUrl, 'http://127.0.0.1:') === 0, 'browser target is restricted to IPv4 loopback');
    $runningAsRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;
    if ($runningAsRoot) {
        echo 'NOTICE: Chrome --no-sandbox enabled for root against loopback-only CSP fixture.' . PHP_EOL;
    }

    $runs = [
        'desktop' => ['width' => 1366, 'height' => 768],
        'mobile' => ['width' => 390, 'height' => 844],
    ];
    foreach ($runs as $name => $viewport) {
        $screenshot = $tempRoot . '/' . $name . '.png';
        $profile = $tempRoot . '/profiles/' . $name;
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        $environment = array_merge($environment, [
            'HOME' => $tempRoot . '/runtime-home',
            'TMPDIR' => $tempRoot,
            'PATH' => (string)getenv('PATH'),
            'NO_PROXY' => '127.0.0.1,localhost',
            'no_proxy' => '127.0.0.1,localhost',
        ]);
        $command = [
            '/usr/bin/timeout', '--foreground', '--signal=TERM', '--kill-after=2s', '40s', $chrome,
            '--headless',
            '--disable-background-networking',
            '--disable-component-update',
            '--disable-default-apps',
            '--disable-domain-reliability',
            '--disable-extensions',
            '--disable-sync',
            '--metrics-recording-only',
            '--no-first-run',
            '--no-default-browser-check',
            '--safebrowsing-disable-auto-update',
            '--disable-features=AutofillServerCommunication,MediaRouter,OptimizationHints,OptimizationGuideModelDownloading,PushMessaging',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--force-device-scale-factor=1',
            '--hide-scrollbars',
            '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1',
            '--no-proxy-server',
            '--user-data-dir=' . $profile,
            '--virtual-time-budget=3000',
            '--window-size=' . $viewport['width'] . ',' . $viewport['height'],
            '--screenshot=' . $screenshot,
        ];
        if ($runningAsRoot) {
            $command[] = '--no-sandbox';
        }
        $command[] = $fixtureUrl . '?viewport=' . $name;
        $result = a4BrowserRun($command, $environment, 45, $screenshot);
        a4BrowserCheck(
            !$result['timed_out'] && !empty($result['rendered']),
            $name . ' Chrome completes its screenshot render before timeout'
        );
        a4BrowserCheck(
            $result['exit'] === 0 || !empty($result['rendered']),
            $name . ' Chrome exits or is stopped cleanly after render (stderr: ' . trim(substr($result['stderr'], 0, 180)) . ')'
        );
        a4BrowserCheck(is_file($screenshot) && filesize($screenshot) > 1000, $name . ' screenshot is non-empty');
        $dimensions = is_file($screenshot) ? @getimagesize($screenshot) : false;
        a4BrowserCheck(
            is_array($dimensions) && $dimensions[0] === $viewport['width'] && $dimensions[1] === $viewport['height'],
            $name . ' screenshot matches requested viewport'
        );
    }

    usleep(150000);
    $log = @file_get_contents($serverLog);
    $log = is_string($log) ? $log : '';
    foreach (['viewport=desktop', 'viewport=mobile', '/assets/css/theme-custom.css', '/assets/js/app.js', '/fixture-runtime.js'] as $marker) {
        a4BrowserCheck(strpos($log, $marker) !== false, 'server log confirms browser load: ' . $marker);
    }
    a4BrowserCheck(strpos($log, 'finance-a4-browser-marker') === false, 'server log does not leak fixture document contents');
} catch (Throwable $error) {
    if ($failures === []) {
        a4BrowserCheck(false, $error->getMessage());
    }
} finally {
    a4BrowserCleanup();
}

a4BrowserCheck($tempRoot === '', 'temporary fixture cleanup completed');
a4BrowserCheck(!is_resource($serverProcess), 'local PHP server child cleanup completed');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A4.3 browser shell runtime check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'A4.3 browser shell runtime smoke passed (' . $checks . ' checks; two local Chrome renders via ' . $chrome . ').' . PHP_EOL;
