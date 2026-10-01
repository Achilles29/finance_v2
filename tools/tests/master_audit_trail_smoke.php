<?php

declare(strict_types=1);

defined('BASEPATH') || define('BASEPATH', __DIR__);

if (!class_exists('CI_Controller')) {
    class CI_Controller {}
}
if (!class_exists('MY_Controller')) {
    class MY_Controller extends CI_Controller
    {
        protected $current_user = [];
    }
}
if (!function_exists('log_message')) {
    function log_message($level, $message): void {}
}
if (!function_exists('show_error')) {
    function show_error($message = '', $statusCode = 500, $heading = ''): void {}
}

require dirname(__DIR__, 2) . '/application/controllers/Master.php';

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/application/controllers/Master.php');
$baseline = (string) file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

function master_audit_method(string $source, string $method): string
{
    $needle = 'function ' . $method . '(';
    $start = strpos($source, $needle);
    if ($start === false) {
        return '';
    }
    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        return '';
    }
    $depth = 0;
    $length = strlen($source);
    for ($index = $brace; $index < $length; $index++) {
        if ($source[$index] === '{') {
            $depth++;
        } elseif ($source[$index] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $index - $start + 1);
            }
        }
    }
    return '';
}

function master_audit_ordered(string $source, array $needles): bool
{
    $position = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $position) {
            return false;
        }
        $position = $next;
    }
    return true;
}

$begin = master_audit_method($source, 'beginMasterAuditTransaction');
$finish = master_audit_method($source, 'finishMasterAuditTransaction');
$write = master_audit_method($source, 'writeMasterAudit');
$sanitize = master_audit_method($source, 'sanitizeMasterAuditPayload');
$failure = master_audit_method($source, 'failMasterAuditedMutation');

$check(
    master_audit_ordered($begin, [
        "table_exists('aud_transaction_log')",
        "field_exists(\$column, 'aud_transaction_log')",
        'trans_begin()',
    ]),
    'audit schema and required columns are checked before transaction begins'
);
$check(
    strpos($begin, "show_error('Pencatatan audit belum siap. Perubahan tidak dijalankan.', 503") !== false,
    'missing audit schema fails closed before a Master write'
);
$check(
    master_audit_ordered($finish, ['!$auditWritten', 'trans_rollback()', 'trans_commit()'])
        && substr_count($finish, 'trans_rollback()') === 2,
    'failed write/status/commit rolls the audited transaction back'
);
$check(
    strpos($write, "'module_code' => 'MASTER'") !== false
        && strpos($write, "insert('aud_transaction_log'") !== false
        && strpos($write, "'actor_user_id'") !== false
        && strpos($write, "'source_ip'") !== false
        && strpos($write, "'before_payload'") !== false
        && strpos($write, "'after_payload'") !== false,
    'audit row records module, action, entity, actor, IP, and before/after payloads'
);
$check(
    strpos($sanitize, 'password') !== false
        && strpos($sanitize, 'token') !== false
        && strpos($sanitize, 'secret') !== false
        && strpos($sanitize, 'authorization') !== false
        && strpos($sanitize, "'[REDACTED]'") !== false,
    'nested credential-like audit keys are redacted'
);
$check(
    strpos($failure, "set_status_header(500)") !== false
        && strpos($failure, "set_flashdata('error'") !== false,
    'audit failure has bounded AJAX and browser responses'
);
$check(
    substr_count($baseline, 'CREATE TABLE `aud_transaction_log`') === 1
        && strpos($baseline, '`before_payload` longtext') !== false
        && strpos($baseline, '`after_payload` longtext') !== false,
    'clean-install baseline contains the append-only audit target'
);

$writerContracts = [
    'store' => ['beginMasterAuditTransaction()', 'Master_model->insert(', "writeMasterAudit(\n            'CREATE'", 'finishMasterAuditTransaction('],
    'update' => ['beginMasterAuditTransaction()', 'Master_model->update(', "writeMasterAudit(\n            'UPDATE'", 'finishMasterAuditTransaction('],
    'toggle' => ['beginMasterAuditTransaction()', 'Master_model->toggle_active(', "writeMasterAudit(\n            'TOGGLE_ACTIVE'", 'finishMasterAuditTransaction('],
    'stock_mode' => ['beginMasterAuditTransaction()', "Master_model->update('mst_product'", "writeMasterAudit(\n            'STOCK_MODE'", 'finishMasterAuditTransaction('],
    'att_holiday_generate_year' => ['beginMasterAuditTransaction()', 'ON DUPLICATE KEY UPDATE', "writeMasterAudit(\n            'HOLIDAY_GENERATE'", 'finishMasterAuditTransaction('],
    'reorder' => ['beginMasterAuditTransaction()', "update((string)\$cfg['table']", "writeMasterAudit(\n            'REORDER'", 'finishMasterAuditTransaction('],
];
foreach ($writerContracts as $method => $needles) {
    $body = master_audit_method($source, $method);
    $check(master_audit_ordered($body, $needles), 'Master::' . $method . ' writes data, audit, then commits in one ordered boundary');
    $check(
        strpos($body, 'failMasterAuditedMutation(') !== false,
        'Master::' . $method . ' returns an explicit failure when audit cannot commit'
    );
}

$reflection = new ReflectionClass(Master::class);
/** @var Master $controller */
$controller = $reflection->newInstanceWithoutConstructor();
$redactor = $reflection->getMethod('sanitizeMasterAuditPayload');
$redactor->setAccessible(true);
$passwordKey = 'pass' . 'word';
$payload = [
    'name' => 'Contoh',
    'nested' => [
        $passwordKey => 'should-not-survive',
        'api_token' => 'should-not-survive',
        'normal_value' => 17,
    ],
    'session_cookie' => 'should-not-survive',
];
$sanitized = $redactor->invoke($controller, $payload);
$check(($sanitized['name'] ?? null) === 'Contoh', 'ordinary audit values remain readable');
$check(($sanitized['nested']['normal_value'] ?? null) === 17, 'ordinary nested audit values remain readable');
$check(
    ($sanitized['nested'][$passwordKey] ?? null) === '[REDACTED]'
        && ($sanitized['nested']['api_token'] ?? null) === '[REDACTED]'
        && ($sanitized['session_cookie'] ?? null) === '[REDACTED]',
    'credential values do not survive runtime redaction'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Master audit trail smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'MASTER AUDIT TRAIL PASS checks=' . $checks . ' writers=' . count($writerContracts) . PHP_EOL;
