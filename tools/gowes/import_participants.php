<?php
declare(strict_types=1);
// Usage: php tools/gowes/import_participants.php docs/GOWESDAY.xlsx [--apply] [--migrate]
// Default is a read-only preview. No deployment credentials are printed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
define('APPPATH', $root . '/application/');
define('FCPATH', $root . '/');
define('ENVIRONMENT', 'production');
date_default_timezone_set('Asia/Jakarta');
function log_message($level, $message): void {}
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message = '', $status = 500): void { throw new RuntimeException('Database operation failed.'); }
$context = new stdClass();
function &get_instance() { return $GLOBALS['context']; }
require APPPATH . 'libraries/Gowes_participant_import.php';
try {
    $path = $argv[1] ?? '';
    if ($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') throw new RuntimeException('Usage: php tools/gowes/import_participants.php FILE.xlsx [--apply] [--migrate]');
    foreach (array_slice($argv, 2) as $arg) if (!in_array($arg, ['--apply', '--migrate'], true)) throw new RuntimeException('Unknown option.');
    $prepared = (new Gowes_participant_import())->read($path);
    if (empty($prepared['ok']) || empty($prepared['rows'])) throw new RuntimeException($prepared['message'] ?? 'Tidak ada peserta valid.');
    echo json_encode(['mode' => in_array('--apply', $argv, true) ? 'apply' : 'preview', 'total' => $prepared['total_rows'],
        'unique_valid' => count($prepared['rows']), 'duplicates' => $prepared['duplicate_rows'],
        'invalid' => $prepared['invalid_rows'], 'invalid_rows' => $prepared['invalid_row_numbers']], JSON_PRETTY_PRINT) . PHP_EOL;
    if (!in_array('--apply', $argv, true)) exit;
    require APPPATH . 'config/database.php';
    require BASEPATH . 'core/Model.php';
    require BASEPATH . 'database/DB.php';
    $db['default']['db_debug'] = false;
    $context->db = DB($db['default'], true);
    echo 'Database: ' . $context->db->database . PHP_EOL;
    if (in_array('--migrate', $argv, true)) {
        $sql = file_get_contents($root . '/sql/2026-09-30a_gowes_voucher_claim.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '' && !$context->db->query($statement)) throw new RuntimeException('Migrasi GOWES gagal; tidak mengimpor peserta.');
        }
        echo "Migration 2026-09-30a applied (idempotent).\n";
    }
    require APPPATH . 'models/Gowes_voucher_model.php';
    $result = (new Gowes_voucher_model())->import_participants($prepared, basename($path), hash_file('sha256', $path));
    if (empty($result['ok'])) throw new RuntimeException($result['message']);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); }
