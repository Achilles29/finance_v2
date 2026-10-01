<?php

declare(strict_types=1);

if (!defined('A510_RESTORE_PREFLIGHT_LIBRARY_ONLY')) {
    define('A510_RESTORE_PREFLIGHT_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/restore_preflight.php';

final class A511Failure extends RuntimeException
{
    public string $failureCode;

    public function __construct(string $code, string $message)
    {
        parent::__construct($message);
        $this->failureCode = $code;
    }
}

function a511_fail(string $code, string $message): void
{
    throw new A511Failure($code, $message);
}

function a511_emit(array $payload, $stream = null): void
{
    $stream = $stream ?? STDOUT;
    fwrite($stream, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function a511_root(): string
{
    $root = realpath(dirname(__DIR__, 2));
    if ($root === false) a511_fail('root_missing', 'Repository root is unavailable.');
    return $root;
}

function a511_timeout_seconds(): int
{
    $raw = getenv('A511_TIMEOUT_SECONDS');
    if ($raw === false || $raw === '') return 300;
    if (preg_match('/^[1-9][0-9]{0,3}$/D', $raw) !== 1) a511_fail('invalid_timeout', 'Drill timeout is invalid.');
    $seconds = (int)$raw;
    if ($seconds < 5 || $seconds > 1800) a511_fail('invalid_timeout', 'Drill timeout is invalid.');
    return $seconds;
}

function a511_assert_environment(): void
{
    $values = getenv();
    if (!is_array($values)) a511_fail('credential_env', 'Process environment could not be inspected safely.');
    foreach ($values as $name => $value) {
        $upper = strtoupper((string)$name);
        if ((string)$value !== '' && ($upper === 'DATABASE_URL' || strpos($upper, 'FINANCE_DB_') === 0
            || strpos($upper, 'MYSQL_') === 0 || strpos($upper, 'MARIADB_') === 0 || strpos($upper, 'DB_') === 0)) {
            a511_fail('credential_env', 'Credential environment variables are forbidden.');
        }
    }
}

function a511_trusted_binary(string $path, string $label): string
{
    $stat = @lstat($path);
    if (!is_array($stat) || is_link($path) || !is_file($path) || !is_executable($path)
        || realpath($path) !== $path || ($stat['uid'] ?? -1) !== 0 || (($stat['mode'] ?? 0) & 0022) !== 0) {
        a511_fail('untrusted_binary', $label . ' binary is unavailable or unsafe.');
    }
    return $path;
}

function a511_private_file(string $path, string $root): string
{
    clearstatcache(true, $path);
    if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR || is_link($path) || !is_file($path) || !is_readable($path)) {
        a511_fail('option_file', 'Admin option file must be an absolute readable regular non-link file.');
    }
    $real = realpath($path);
    $stat = lstat($path);
    if ($real === false || !is_array($stat) || (($stat['mode'] ?? 0) & 0077) !== 0
        || $real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
        a511_fail('option_file_permissions', 'Admin option file is not private or is inside the repository.');
    }
    if (function_exists('posix_geteuid') && ($stat['uid'] ?? -1) !== posix_geteuid()) {
        a511_fail('option_file_owner', 'Admin option file owner is unsafe.');
    }
    return $real;
}

function a511_private_directory(string $path, string $root): string
{
    clearstatcache(true, $path);
    if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR || is_link($path) || !is_dir($path)) {
        a511_fail('evidence_directory', 'Evidence directory must be an absolute regular directory.');
    }
    $real = realpath($path);
    $stat = lstat($path);
    if ($real === false || $real !== rtrim($path, DIRECTORY_SEPARATOR) || !is_array($stat)
        || (($stat['mode'] ?? 0) & 0077) !== 0 || $real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
        a511_fail('evidence_directory_permissions', 'Evidence directory is not private or is inside the repository.');
    }
    if (function_exists('posix_geteuid') && ($stat['uid'] ?? -1) !== posix_geteuid()) {
        a511_fail('evidence_directory_owner', 'Evidence directory owner is unsafe.');
    }
    return $real;
}

function a511_target_name(string $runId): string
{
    if (preg_match('/^[a-f0-9]{16}$/D', $runId) !== 1) a511_fail('invalid_run_id', 'Generated run identifier is invalid.');
    return 'a511_restore_' . $runId;
}

function a511_assert_target_name(string $name): void
{
    if (preg_match('/^a511_restore_[a-f0-9]{16}$/D', $name) !== 1) {
        a511_fail('unsafe_target', 'Disposable target name is unsafe.');
    }
}

function a511_sql_value(string $value): string
{
    return "CONVERT(UNHEX('" . bin2hex($value) . "') USING utf8mb4)";
}

function a511_account(string $user, string $host): string
{
    a511_assert_target_name($user);
    if (!in_array($host, ['localhost', '127.0.0.1'], true)) a511_fail('unsafe_account_host', 'Disposable account host is unsafe.');
    return "'" . $user . "'@'" . $host . "'";
}

function a511_option_endpoint(string $path): array
{
    $raw = file_get_contents($path, false, null, 0, 65537);
    if (!is_string($raw) || strlen($raw) > 65536) a511_fail('option_file_parse', 'Admin option file is unreadable or oversized.');
    $section = '';
    $values = [];
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === ';') continue;
        if (preg_match('/^!(?:include|includedir)\b/i', $trimmed) === 1) a511_fail('option_file_parse', 'Included option files are forbidden.');
        if (preg_match('/^\[([^]]+)\]$/', $trimmed, $match) === 1) {
            $section = strtolower(trim($match[1]));
            continue;
        }
        if ($section !== 'client' || preg_match('/^([A-Za-z0-9_-]+)\s*=\s*(.*)$/', $trimmed, $match) !== 1) continue;
        $key = strtolower(str_replace('-', '_', $match[1]));
        if (!in_array($key, ['host', 'port', 'socket', 'protocol'], true) || isset($values[$key])) continue;
        $value = trim($match[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (strpos($value, "\0") !== false || strpos($value, "\n") !== false || strpos($value, "\r") !== false) a511_fail('option_file_parse', 'Connection endpoint is malformed.');
        $values[$key] = $value;
    }
    $values['host'] = $values['host'] ?? 'localhost';
    if (!in_array(strtolower($values['host']), ['localhost', '127.0.0.1'], true)) {
        a511_fail('remote_endpoint', 'Only a local disposable database endpoint is allowed.');
    }
    $values['host'] = strtolower($values['host']);
    if (isset($values['port']) && preg_match('/^[1-9][0-9]{0,4}$/D', $values['port']) !== 1) a511_fail('option_file_parse', 'Connection port is malformed.');
    if (isset($values['protocol']) && !in_array(strtolower($values['protocol']), ['tcp', 'socket'], true)) a511_fail('option_file_parse', 'Connection protocol is unsupported.');
    if (isset($values['socket']) && ($values['socket'] === '' || $values['socket'][0] !== DIRECTORY_SEPARATOR || strpos($values['socket'], '..') !== false)) {
        a511_fail('option_file_parse', 'Connection socket is malformed.');
    }
    return $values;
}

function a511_option_quote(string $value): string
{
    return '"' . str_replace(['\\', "\n", "\r", '"'], ['\\\\', '\\n', '\\r', '\\"'], $value) . '"';
}

function a511_write_target_option(string $directory, string $runId, array $endpoint, string $user, string $password, string $database): string
{
    a511_assert_target_name($user);
    a511_assert_target_name($database);
    $path = $directory . DIRECTORY_SEPARATOR . '.a511-target-' . $runId . '.cnf';
    $lines = ['[client]', 'host=' . a511_option_quote((string)$endpoint['host'])];
    foreach (['port', 'socket', 'protocol'] as $key) if (isset($endpoint[$key]) && $endpoint[$key] !== '') $lines[] = $key . '=' . a511_option_quote((string)$endpoint[$key]);
    $lines[] = 'user=' . a511_option_quote($user);
    $lines[] = 'password=' . a511_option_quote($password);
    $lines[] = 'database=' . a511_option_quote($database);
    $handle = @fopen($path, 'xb');
    if (!is_resource($handle)) a511_fail('target_option_create', 'Temporary target option file could not be created.');
    $payload = implode("\n", $lines) . "\n";
    $written = fwrite($handle, $payload);
    $synced = $written === strlen($payload) && fflush($handle) && (!function_exists('fsync') || fsync($handle));
    fclose($handle);
    $modeSecured = chmod($path, 0600);
    clearstatcache(true, $path);
    if (!$synced || !$modeSecured || (fileperms($path) & 0777) !== 0600) {
        @unlink($path);
        a511_fail('target_option_create', 'Temporary target option file could not be secured.');
    }
    return $path;
}

function a511_write_target_database_name(string $directory, string $runId, string $database): string
{
    a511_assert_target_name($database);
    $path = $directory . DIRECTORY_SEPARATOR . '.a511-target-' . $runId . '.database-name';
    $handle = @fopen($path, 'xb');
    if (!is_resource($handle)) a511_fail('target_database_name_create', 'Temporary database-name file could not be created.');
    $payload = $database . "\n";
    $written = fwrite($handle, $payload);
    $synced = $written === strlen($payload) && fflush($handle) && (!function_exists('fsync') || fsync($handle));
    fclose($handle);
    $modeSecured = chmod($path, 0600);
    clearstatcache(true, $path);
    if (!$synced || !$modeSecured || (fileperms($path) & 0777) !== 0600) {
        @unlink($path);
        a511_fail('target_database_name_create', 'Temporary database-name file could not be secured.');
    }
    return $path;
}

function a511_remove_target_temporary_file(string $path, string $directory, string $runId): bool
{
    if ($path === '') return true;
    $allowed = [
        $directory . DIRECTORY_SEPARATOR . '.a511-target-' . $runId . '.cnf',
        $directory . DIRECTORY_SEPARATOR . '.a511-target-' . $runId . '.database-name',
    ];
    if (!in_array($path, $allowed, true)) return false;
    @unlink($path);
    clearstatcache(true, $path);
    return !file_exists($path) && !is_link($path);
}

function a511_process(array $command, string $stdin, int $timeout, ?string $cwd = null, ?array $environment = null): array
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $environment);
    if (!is_resource($process)) a511_fail('process_start', 'Required process could not start.');
    foreach ($pipes as $pipe) stream_set_blocking($pipe, false);
    $offset = 0;
    $stdout = '';
    $deadline = microtime(true) + $timeout;
    $stdinClosed = false;
    $observedExit = null;
    while (true) {
        if (!$stdinClosed && $offset < strlen($stdin)) {
            $written = @fwrite($pipes[0], substr($stdin, $offset, 65536));
            if (is_int($written) && $written > 0) $offset += $written;
        }
        if (!$stdinClosed && $offset >= strlen($stdin)) {
            fclose($pipes[0]);
            $stdinClosed = true;
        }
        $chunk = stream_get_contents($pipes[1]);
        if (is_string($chunk) && $chunk !== '') {
            if (strlen($stdout) + strlen($chunk) > 65536) {
                a511_terminate($process, $pipes, $stdinClosed);
                a511_fail('process_output', 'Required process returned oversized output.');
            }
            $stdout .= $chunk;
        }
        stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $observedExit = $status['exitcode'];
            break;
        }
        if (microtime(true) >= $deadline) {
            a511_terminate($process, $pipes, $stdinClosed);
            a511_fail('process_timeout', 'Required process timed out.');
        }
        usleep(10000);
    }
    foreach ([1, 2] as $index) {
        $chunk = stream_get_contents($pipes[$index]);
        if ($index === 1 && is_string($chunk) && $chunk !== '') $stdout .= substr($chunk, 0, max(0, 65536 - strlen($stdout)));
        fclose($pipes[$index]);
    }
    $closed = proc_close($process);
    $exit = $closed >= 0 ? $closed : $observedExit;
    return ['exit' => $exit, 'stdout' => $stdout];
}

function a511_terminate($process, array &$pipes, bool $stdinClosed): void
{
    if (!$stdinClosed && isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
    @proc_terminate($process, 15);
    usleep(100000);
    $status = proc_get_status($process);
    if ($status['running']) @proc_terminate($process, 9);
    foreach ([1, 2] as $index) if (isset($pipes[$index]) && is_resource($pipes[$index])) { @stream_get_contents($pipes[$index]); fclose($pipes[$index]); }
    @proc_close($process);
}

function a511_direct_client_argv(string $optionFile, ?string $selectedDatabase = null): array
{
    $command = ['/usr/bin/mariadb', '--defaults-extra-file=' . $optionFile, '--sandbox', '--local-infile=0', '--batch', '--raw', '--skip-column-names'];
    if ($selectedDatabase !== null) {
        a511_assert_target_name($selectedDatabase);
        $command[] = $selectedDatabase;
    }
    return $command;
}

function a511_restore_client_argv(string $optionFile, string $target): array
{
    a511_assert_target_name($target);
    return ['/usr/bin/mariadb', '--defaults-extra-file=' . $optionFile, '--sandbox', '--local-infile=0', '--batch', $target];
}

function a511_client(string $optionFile, string $sql, int $timeout, ?string $selectedDatabase = null): string
{
    a511_trusted_binary('/usr/bin/mariadb', 'MariaDB client');
    $result = a511_process(a511_direct_client_argv($optionFile, $selectedDatabase), $sql . "\n", $timeout, null, ['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta','LANG'=>'C']);
    if ($result['exit'] !== 0) a511_fail('database_client', 'Database operation failed with redacted output.');
    return trim($result['stdout']);
}

function a511_counts(string $optionFile, string $database, string $user, string $host, int $timeout): array
{
    $sql = 'SELECT (SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=' . a511_sql_value($database)
        . '),(SELECT COUNT(*) FROM mysql.user WHERE User=' . a511_sql_value($user) . ' AND Host=' . a511_sql_value($host) . ');';
    $parts = explode("\t", a511_client($optionFile, $sql, $timeout));
    if (count($parts) !== 2 || preg_match('/^[0-9]+$/D', $parts[0]) !== 1 || preg_match('/^[0-9]+$/D', $parts[1]) !== 1) {
        a511_fail('database_protocol', 'Database existence probe returned malformed output.');
    }
    return [(int)$parts[0], (int)$parts[1]];
}

function a511_strip_sql_literals(string $sql): string
{
    $result = '';
    $quote = '';
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote === '') {
            if ($char === "'" || $char === '"') { $quote = $char; $result .= ' '; }
            else $result .= $char;
            continue;
        }
        $result .= ' ';
        if ($char === '\\') { if ($i + 1 < $length) { $result .= ' '; $i++; } continue; }
        if ($char === $quote) {
            if ($i + 1 < $length && $sql[$i + 1] === $quote) { $result .= ' '; $i++; }
            else $quote = '';
        }
    }
    return $result;
}

function a511_scan_segment(string $segment, bool $lineStart, string &$carry = ''): bool
{
    if (!$lineStart) return false;
    $trimmed = ltrim($segment);
    if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) return false;
    if (preg_match('/^(?:\\\\|\?|clear\b|connect\b|delimiter\b|edit\b|ego\b|exit\b|go\b|help\b|nopager\b|notee\b|pager\b|print\b|prompt\b|quit\b|rehash\b|source\b|status\b|system\b|tee\b|warnings\b|nowarning\b)/i', $trimmed) === 1) {
        a511_fail('dump_escape', 'Dump contains a MariaDB client metacommand.');
    }
    if (preg_match('/^(?:INSERT|REPLACE)\b/i', $trimmed) === 1) {
        $valuesAt = stripos($trimmed, 'VALUES');
        $prefix = substr($trimmed, 0, $valuesAt === false ? 4096 : min($valuesAt, 4096));
        if (preg_match('/`[^`]+`\s*\.\s*`[^`]+`/', $prefix) === 1) {
            a511_fail('dump_escape', 'Dump contains a database-qualified data target.');
        }
        $carry = '';
        return false;
    }
    $normalized = a511_strip_sql_literals($trimmed);
    $boundary = '(?:^|;)\s*(?:\/\*![0-9]{5,6}\s*)?';
    $combined = ltrim($carry . ' ' . $normalized);
    if (preg_match('/\bDEFINER\s*=/i', $combined) === 1
        || preg_match('/`[^`]+`\s*\.\s*`[^`]+`/', $combined) === 1
        || preg_match('/' . $boundary . '(?:USE\b|SOURCE\b|\\\\|(?:CREATE|DROP)\s+DATABASE\b|(?:GRANT|REVOKE)\b|(?:CREATE|ALTER|DROP)\s+USER\b|SET\s+(?:@@\s*)?GLOBAL\b|RESET\b|FLUSH\b|INSTALL\b|UNINSTALL\b|SHUTDOWN\b|LOAD\s+DATA\b)/i', $combined) === 1
        || preg_match('/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i', $combined) === 1) {
        a511_fail('dump_escape', 'Dump contains an operation outside the disposable target contract.');
    }
    $lastTerminator = strrpos($combined, ';');
    $carry = substr($combined, $lastTerminator === false ? -512 : $lastTerminator + 1, 512);
    return false;
}

function a511_scan_archive(string $archive, int $timeout): void
{
    $gzip = a511_trusted_binary('/usr/bin/gzip', 'gzip');
    $process = proc_open([$gzip, '-dc', '--', $archive], [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, ['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta','LANG'=>'C']);
    if (!is_resource($process)) a511_fail('dump_scan_start', 'Dump scan could not start.');
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $deadline = microtime(true) + $timeout;
    $buffer = '';
    $carry = '';
    $observedExit = null;
    $caught = null;
    try {
        while (true) {
            $madeProgress = false;
            $chunk = fread($pipes[1], 65536);
            if (is_string($chunk) && $chunk !== '') { $buffer .= $chunk; $madeProgress = true; }
            stream_get_contents($pipes[2]);
            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline + 1);
                $buffer = substr($buffer, $newline + 1);
                a511_scan_segment($line, true, $carry);
            }
            if (strlen($buffer) > 8388608) a511_fail('dump_scan_line_oversized', 'Dump contains an oversized SQL line.');
            $status = proc_get_status($process);
            if (!$status['running']) { $observedExit = $status['exitcode']; break; }
            if (microtime(true) >= $deadline) a511_fail('dump_scan_timeout', 'Dump scan timed out.');
            if (!$madeProgress) usleep(10000);
        }
        if ($buffer !== '') a511_scan_segment($buffer, true, $carry);
    } catch (Throwable $error) {
        @proc_terminate($process, 15);
        usleep(100000);
        $status = proc_get_status($process);
        if ($status['running']) @proc_terminate($process, 9);
        $caught = $error;
    } finally {
        foreach ([1,2] as $index) if (is_resource($pipes[$index])) { @stream_get_contents($pipes[$index]); fclose($pipes[$index]); }
    }
    $closed = proc_close($process);
    if ($caught instanceof Throwable) throw $caught;
    $exit = $closed >= 0 ? $closed : $observedExit;
    if ($exit !== 0) a511_fail('dump_scan_failed', 'Dump decompression failed during scan.');
}

function a511_restore_archive(string $archive, string $optionFile, string $target, int $timeout): void
{
    $gzip = a511_trusted_binary('/usr/bin/gzip', 'gzip');
    a511_trusted_binary('/usr/bin/mariadb', 'MariaDB client');
    $gz = proc_open([$gzip, '-dc', '--', $archive], [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $gzPipes, null, ['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta','LANG'=>'C']);
    $db = proc_open(a511_restore_client_argv($optionFile, $target), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $dbPipes, null, ['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta','LANG'=>'C']);
    if (!is_resource($gz) || !is_resource($db)) {
        if (is_resource($gz)) @proc_terminate($gz, 9);
        if (is_resource($db)) @proc_terminate($db, 9);
        a511_fail('restore_start', 'Restore pipeline could not start.');
    }
    foreach ([$gzPipes[1],$gzPipes[2],$dbPipes[0],$dbPipes[1],$dbPipes[2]] as $pipe) stream_set_blocking($pipe, false);
    $deadline = microtime(true) + $timeout;
    $buffer = '';
    $gzipDone = false;
    $dbInputClosed = false;
    $gzExit = $dbExit = null;
    $caught = null;
    try {
        while (true) {
            $madeProgress = false;
            if (!$gzipDone && strlen($buffer) < 1048576) {
                $chunk = fread($gzPipes[1], 65536);
                if (is_string($chunk) && $chunk !== '') { $buffer .= $chunk; $madeProgress = true; }
            }
            if ($buffer !== '' && !$dbInputClosed) {
                $written = @fwrite($dbPipes[0], $buffer);
                if (is_int($written) && $written > 0) { $buffer = substr($buffer, $written); $madeProgress = true; }
            }
            stream_get_contents($gzPipes[2]);
            stream_get_contents($dbPipes[1]);
            stream_get_contents($dbPipes[2]);
            $gzStatus = proc_get_status($gz);
            $dbStatus = proc_get_status($db);
            if (!$gzStatus['running']) { $gzipDone = true; if ($gzExit === null) $gzExit = $gzStatus['exitcode']; }
            if ($gzipDone && $buffer === '' && !$dbInputClosed) { fclose($dbPipes[0]); $dbInputClosed = true; }
            if (!$dbStatus['running']) {
                $dbExit = $dbStatus['exitcode'];
                if (!$gzipDone) { @proc_terminate($gz, 15); usleep(100000); $left=proc_get_status($gz); if ($left['running']) @proc_terminate($gz, 9); }
                break;
            }
            if (microtime(true) >= $deadline) a511_fail('restore_timeout', 'Disposable restore timed out.');
            if (!$madeProgress) usleep(10000);
        }
    } catch (Throwable $error) {
        foreach ([$gz,$db] as $process) @proc_terminate($process, 15);
        usleep(100000);
        foreach ([$gz,$db] as $process) { $status=proc_get_status($process); if ($status['running']) @proc_terminate($process, 9); }
        $caught = $error;
    } finally {
        if (!$dbInputClosed && is_resource($dbPipes[0])) fclose($dbPipes[0]);
        foreach ([$gzPipes[1],$gzPipes[2],$dbPipes[1],$dbPipes[2]] as $pipe) if (is_resource($pipe)) { @stream_get_contents($pipe); fclose($pipe); }
    }
    $gzClosed = proc_close($gz);
    $dbClosed = proc_close($db);
    if ($caught instanceof Throwable) throw $caught;
    $gzCode = $gzClosed >= 0 ? $gzClosed : $gzExit;
    $dbCode = $dbClosed >= 0 ? $dbClosed : $dbExit;
    if ($gzCode !== 0 || $dbCode !== 0) a511_fail('restore_failed', 'Disposable restore failed with redacted output.');
}

function a511_json_tool(array $command, int $timeout, string $root): array
{
    $result = a511_process($command, '', $timeout, $root, ['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta','LANG'=>'C']);
    if ($result['exit'] !== 0) a511_fail('verification_tool', 'A5 verification tool failed with redacted output.');
    $decoded = json_decode(trim($result['stdout']), true);
    if (!is_array($decoded)) a511_fail('verification_protocol', 'A5 verification tool returned malformed output.');
    return $decoded;
}

function a511_same_source(array $before, array $after): bool
{
    foreach (['dev','ino','mode','size','mtime','ctime'] as $key) if (($before[$key] ?? null) !== ($after[$key] ?? null)) return false;
    return true;
}

function a511_write_evidence(string $directory, string $runId, array $evidence): string
{
    $name = 'a5_disposable_restore_' . $runId . '.json';
    $final = $directory . DIRECTORY_SEPARATOR . $name;
    $stage = $directory . DIRECTORY_SEPARATOR . '.' . $name . '.stage';
    if (file_exists($final) || file_exists($stage) || is_link($final) || is_link($stage)) a511_fail('evidence_exists', 'Evidence output already exists.');
    $handle = @fopen($stage, 'xb');
    if (!is_resource($handle)) a511_fail('evidence_write', 'Evidence stage could not be created.');
    $json = json_encode($evidence, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $ok = is_string($json) && fwrite($handle, $json) === strlen($json) && fflush($handle) && (!function_exists('fsync') || fsync($handle));
    fclose($handle);
    if (!$ok || !chmod($stage, 0600) || !rename($stage, $final)) {
        @unlink($stage);
        a511_fail('evidence_write', 'Evidence could not be published.');
    }
    return $final;
}

function a511_run_drill(string $bundleDir, string $adminOption, string $evidenceDir): array
{
    $root = a511_root();
    $adminOption = a511_private_file($adminOption, $root);
    $evidenceDir = a511_private_directory($evidenceDir, $root);
    $bundleDir = a510_bundle_directory($bundleDir);
    $timeout = a511_timeout_seconds();
    $runId = bin2hex(random_bytes(8));
    $target = a511_target_name($runId);
    $endpoint = a511_option_endpoint($adminOption);
    $accountHost = ($endpoint['host'] ?? '') === '127.0.0.1' ? '127.0.0.1' : 'localhost';
    $password = bin2hex(random_bytes(24));
    $targetOption = '';
    $targetDatabaseNameFile = '';
    $ownershipClaimed = false;
    $phases = ['precondition'=>false,'provision'=>false,'scan'=>false,'immediate_reverify'=>false,'restore'=>false,'registry'=>false,'fingerprint'=>false,'source_unchanged'=>false,'cleanup'=>false];
    $failureCode = null;
    $started = microtime(true);
    $manifestHash = hash_file('sha256', $bundleDir . DIRECTORY_SEPARATOR . A510_MANIFEST_NAME);
    $catalogHash = hash_file('sha256', $root . '/tools/db/migration_catalog.json');
    $catalog = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
    $managedMigrations = is_array($catalog) ? ($catalog['migrations'] ?? null) : null;
    if (is_array($managedMigrations)) {
        $managedMigrations = array_values(array_filter($managedMigrations, static function (array $migration): bool {
            return in_array('upgrade', $migration['policies'] ?? [], true);
        }));
        usort($managedMigrations, static function (array $left, array $right): int {
            return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
        });
    }
    $managedIds = is_array($managedMigrations) ? array_column($managedMigrations, 'id') : [];
    if ($managedIds !== ['2026-09-04c-a5-schema-migration-registry-foundation', '2026-09-05e-whatsapp-safe-reference-seed', '2026-09-05a-telegram-bot-foundation', '2026-09-05b-telegram-setup-guide', '2026-09-05c-telegram-safe-activation-default', '2026-09-06a-component-formula-version-history', '2026-09-06b-component-formula-restore-action', '2026-09-06c-pos-mobile-reversal-step-up', '2026-09-06d-pos-mobile-reprint-step-up', '2026-09-06e-activity-audit-foundation', '2026-09-06f-pos-mobile-cashier-close-step-up', '2026-09-06g-pos-mobile-reservation-refund-step-up', '2026-09-06h-roastery-label-template-studio', '2026-09-06i-a3-sidebar-task-oriented-layout', '2026-09-07a-c2-c4-business-profile-license-runtime-foundation']) {
        a511_fail('catalog_contract', 'Managed migration catalog contract is unsupported.');
    }
    $managedMigrationCount = count($managedMigrations);
    $expectedRegistryRows = [];
    foreach ($managedMigrations as $managedMigration) {
        if (!is_array($managedMigration) || !is_string($managedMigration['id'] ?? null) || !is_string($managedMigration['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $managedMigration['sha256']) !== 1) {
            a511_fail('catalog_contract', 'Managed migration catalog contract is unsupported.');
        }
        $expectedRegistryRows[] = bin2hex($managedMigration['id']) . "\t" . $managedMigration['sha256'];
    }
    sort($expectedRegistryRows, SORT_STRING);
    $archiveHash = null;
    $registryRows = null;
    $eligible = null;
    try {
        $manifest = a510_load_bundle($bundleDir);
        $archive = $bundleDir . DIRECTORY_SEPARATOR . $manifest['artifact']['path'];
        $sourceBefore = lstat($archive);
        if (!is_array($sourceBefore)) a511_fail('source_identity', 'Bundle archive identity is unavailable.');
        $archiveHash = $manifest['artifact']['sha256'];
        $phases['precondition'] = true;
        [$dbCount,$userCount] = a511_counts($adminOption, $target, $target, $accountHost, $timeout);
        if ($dbCount !== 0 || $userCount !== 0) a511_fail('target_exists', 'Generated disposable target already exists.');
        $ownershipClaimed = true;
        $account = a511_account($target, $accountHost);
        $sql = 'CREATE DATABASE `' . $target . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
            . 'CREATE USER ' . $account . " IDENTIFIED BY '" . $password . "';"
            . 'GRANT ALL PRIVILEGES ON `' . $target . '`.* TO ' . $account . ';';
        a511_client($adminOption, $sql, $timeout);
        [$dbCount,$userCount] = a511_counts($adminOption, $target, $target, $accountHost, $timeout);
        if ($dbCount !== 1 || $userCount !== 1) a511_fail('provision_verify', 'Disposable target provisioning could not be verified.');
        $targetOption = a511_write_target_option($evidenceDir, $runId, $endpoint, $target, $password, $target);
        $targetDatabaseNameFile = a511_write_target_database_name($evidenceDir, $runId, $target);
        $grantSql = 'SELECT (SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=REPLACE(QUOTE(' . a511_sql_value($target . '@' . $accountHost) . "),'@',CHAR(39,64,39)) AND PRIVILEGE_TYPE<>'USAGE'),"
            . '(SELECT COUNT(*) FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE=REPLACE(QUOTE(' . a511_sql_value($target . '@' . $accountHost) . "),'@',CHAR(39,64,39)) AND TABLE_SCHEMA<>" . a511_sql_value($target) . '),'
            . '(SELECT COUNT(*) FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE=REPLACE(QUOTE(' . a511_sql_value($target . '@' . $accountHost) . "),'@',CHAR(39,64,39)) AND IS_GRANTABLE='YES');";
        $grantParts = explode("\t", a511_client($adminOption, $grantSql, $timeout));
        if ($grantParts !== ['0','0','0']) a511_fail('privilege_scope', 'Disposable account privilege scope is unsafe.');
        $phases['provision'] = true;
        a511_scan_archive($archive, $timeout);
        $phases['scan'] = true;
        $manifest = a510_load_bundle($bundleDir);
        $archive = $bundleDir . DIRECTORY_SEPARATOR . $manifest['artifact']['path'];
        $phases['immediate_reverify'] = true;
        a511_restore_archive($archive, $targetOption, $target, $timeout);
        $phases['restore'] = true;
        $absent = a511_client($targetOption, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_schema_migration';", $timeout, $target);
        if ($absent !== '0') a511_fail('registry_precondition', 'Restored backup unexpectedly contains the migration registry.');
        $migration = a511_json_tool([PHP_BINARY,$root.'/tools/db/migration_runner.php','apply','--policy=upgrade','--defaults-extra-file='.$targetOption,'--database-name-file='.$targetDatabaseNameFile],$timeout,$root);
        $replay = a511_json_tool([PHP_BINARY,$root.'/tools/db/migration_runner.php','apply','--policy=upgrade','--defaults-extra-file='.$targetOption,'--database-name-file='.$targetDatabaseNameFile],$timeout,$root);
        if (($migration['applied'] ?? null) !== $managedMigrationCount || ($migration['skipped'] ?? null) !== 0 || ($replay['applied'] ?? null) !== 0 || ($replay['skipped'] ?? null) !== $managedMigrationCount) {
            a511_fail('registry_apply', 'Migration registry bootstrap or replay contract failed.');
        }
        $registryRowsRaw = a511_client($targetOption, "SELECT CONCAT(LOWER(HEX(migration_id)),'\\t',checksum_sha256) FROM sys_schema_migration ORDER BY BINARY migration_id;", $timeout, $target);
        $actualRegistryRows = $registryRowsRaw === '' ? [] : preg_split('/\R/', $registryRowsRaw);
        if ($actualRegistryRows !== $expectedRegistryRows) a511_fail('registry_verify', 'Managed migration ledger rows or checksums are invalid.');
        $registryRows = $managedMigrationCount;
        $phases['registry'] = true;
        $fingerprint = a511_json_tool([PHP_BINARY,$root.'/tools/db/schema_fingerprint_probe.php','probe','--defaults-extra-file='.$targetOption,'--database-name-file='.$targetDatabaseNameFile],$timeout,$root);
        if (($fingerprint['candidate_eligible'] ?? null) !== 4 || ($fingerprint['candidate_total'] ?? null) !== 4
            || ($fingerprint['overall_eligibility'] ?? null) !== 'candidate_evidence_complete_historical_excluded') {
            a511_fail('fingerprint_verify', 'Restored schema fingerprint did not match 4/4 candidates.');
        }
        $eligible = 4;
        $phases['fingerprint'] = true;
        clearstatcache(true, $archive);
        $sourceAfter = lstat($archive);
        $hashAfter = hash_file('sha256', $archive);
        if (!is_array($sourceAfter) || !a511_same_source($sourceBefore, $sourceAfter) || !is_string($hashAfter) || !hash_equals($archiveHash, $hashAfter)) {
            a511_fail('source_changed', 'Bundle source changed during the drill.');
        }
        $phases['source_unchanged'] = true;
    } catch (Throwable $error) {
        $failureCode = $error instanceof A511Failure ? $error->failureCode : ($error instanceof A510BundleFailure ? 'bundle_' . $error->failureCode : 'unexpected_failure');
    } finally {
        $databaseNameRemoved = a511_remove_target_temporary_file($targetDatabaseNameFile, $evidenceDir, $runId);
        $targetOptionRemoved = a511_remove_target_temporary_file($targetOption, $evidenceDir, $runId);
        if (!$databaseNameRemoved || !$targetOptionRemoved) $failureCode = 'cleanup_failed';
        if ($ownershipClaimed) {
            try {
                a511_assert_target_name($target);
                $cleanupSql = 'DROP DATABASE IF EXISTS `' . $target . '`;DROP USER IF EXISTS ' . a511_account($target, $accountHost) . ';';
                a511_client($adminOption, $cleanupSql, $timeout);
                [$dbCount,$userCount] = a511_counts($adminOption, $target, $target, $accountHost, $timeout);
                if ($dbCount !== 0 || $userCount !== 0) a511_fail('cleanup_verify', 'Disposable target cleanup could not be verified.');
                $phases['cleanup'] = true;
            } catch (Throwable $cleanupError) {
                $failureCode = 'cleanup_failed';
            }
        }
    }
    $status = $failureCode === null && $phases['cleanup'] ? 'ok' : 'error';
    $evidence = [
        'format'=>'finance-a5-disposable-restore','version'=>1,'run_id'=>$runId,'status'=>$status,
        'failure_code'=>$failureCode,'bundle'=>basename($bundleDir),'manifest_sha256'=>$manifestHash,
        'archive_sha256'=>$archiveHash,'migration_catalog_sha256'=>$catalogHash,'phases'=>$phases,
        'registry'=>['state'=>$registryRows === $managedMigrationCount ? 'COMPATIBLE_V1' : 'not_verified','bootstrap_rows'=>$registryRows],
        'fingerprint'=>['candidate_eligible'=>$eligible,'candidate_total'=>4],
        'cleanup_verified'=>$phases['cleanup'],'duration_ms'=>(int)round((microtime(true)-$started)*1000),
    ];
    $evidencePath = a511_write_evidence($evidenceDir, $runId, $evidence);
    if ($status !== 'ok') a511_fail($failureCode ?? 'drill_failed', 'Disposable restore drill failed; redacted evidence was written.');
    return ['status'=>'ok','mode'=>'run','run_id'=>$runId,'registry'=>'COMPATIBLE_V1','candidate_eligible'=>4,'candidate_total'=>4,'cleanup_verified'=>true,'evidence'=>basename($evidencePath)];
}

if (defined('A511_DISPOSABLE_RESTORE_LIBRARY_ONLY') && A511_DISPOSABLE_RESTORE_LIBRARY_ONLY) return;

try {
    $args = $argv ?? [];
    foreach ($args as $arg) if (is_string($arg) && preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database|target|run-id)(?:=|$)/i', $arg)) {
        a511_fail('credential_cli', 'Credential and target arguments are forbidden.');
    }
    if (($args[1] ?? '') !== 'run') a511_fail('usage', 'Use run with bundle, admin option file, and evidence directory.');
    $options = [];
    foreach (array_slice($args, 2) as $arg) {
        if (!is_string($arg) || preg_match('/^--(bundle-dir|admin-defaults-extra-file|evidence-dir)=(.+)$/D', $arg, $match) !== 1 || isset($options[$match[1]])) {
            a511_fail('usage', 'Run arguments are malformed.');
        }
        $options[$match[1]] = $match[2];
    }
    if (array_keys($options) !== ['bundle-dir','admin-defaults-extra-file','evidence-dir']) a511_fail('usage', 'Run arguments are incomplete or out of order.');
    a511_assert_environment();
    a511_emit(a511_run_drill($options['bundle-dir'], $options['admin-defaults-extra-file'], $options['evidence-dir']));
} catch (A511Failure $error) {
    a511_emit(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()], STDERR);
    exit($error->failureCode === 'usage' ? 2 : 1);
} catch (A510BundleFailure $error) {
    a511_emit(['status'=>'error','code'=>'bundle_' . $error->failureCode,'message'=>'Bundle validation failed.'], STDERR);
    exit(1);
}
