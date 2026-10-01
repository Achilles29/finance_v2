<?php
declare(strict_types=1);
// Default: pure policy/contracts. --disposable: real CI query builder + own MariaDB,
// actual clean baseline, synthetic bot/entitlements. Never loads active DB credentials.
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
$notificationAppPath = $root . '/application/';
if (in_array('--disposable', $argv, true)) {
    // Private test runtime; read source through links, never write real application/cache.
    $notificationAppPath = sys_get_temp_dir() . '/finance-notification-app-' . bin2hex(random_bytes(8)) . '/';
    mkdir($notificationAppPath, 0700);
    foreach (['libraries','models','controllers','config'] as $part) symlink($root . '/application/' . $part, $notificationAppPath . $part);
    mkdir($notificationAppPath . 'cache', 0700);
}
define('APPPATH', $notificationAppPath);
define('ENVIRONMENT', 'testing');
date_default_timezone_set('Asia/Jakarta');
require APPPATH . 'libraries/Module_notification.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . "\n";
};
$reject = static function (callable $fn, string $label) use ($check): void {
    try { $fn(); } catch (InvalidArgumentException | RuntimeException $e) { $check(true, $label); return; }
    $check(false, $label);
};
$targets = ['group:1' => ['destination' => '1200001@g.us', 'label' => 'Grup fixture']];
$check(count(Module_notification::targets(['group:1', 'group:1'], '', $targets, 'WA')) === 1, 'duplicate recipients collapsed');
$phones = Module_notification::targets([], '081234567890; +62 81234567890', [], 'WA');
$check(count($phones) === 1 && isset($phones['phone:6281234567890']), 'Indonesian phone normalization/deduplication');
$reject(fn() => Module_notification::targets(['group:99'], '', $targets, 'WA'), 'unregistered recipient rejected');
$reject(fn() => Module_notification::targets([], 'abc', [], 'WA'), 'invalid phone rejected');
$reject(fn() => Module_notification::targets([], '081234567890', [], 'TELEGRAM'), 'Telegram cannot be routed to arbitrary phone');
$reject(fn() => Module_notification::targets([], '', [], 'EMAIL'), 'unknown channel rejected');
$reject(fn() => Module_notification::targets('group:1', '', $targets, 'WA'), 'non-list recipients rejected');
$reject(fn() => Module_notification::targets([], implode(',', range(628123456780, 628123456791)), [], 'WA'), 'recipient fanout capped');
$key = Module_notification::key('WA', 'SELF_ORDER', 1, 'group:1');
$check($key === Module_notification::key('WA', 'SELF_ORDER', 1, 'group:1'), 'stable automatic delivery key');
$check($key !== Module_notification::key('TELEGRAM', 'SELF_ORDER', 1, 'group:1'), 'channels isolated');
$check(Module_notification::key('WA', 'DIVISION_REQUEST', 1, 'group:1', 'a') !== Module_notification::key('WA', 'DIVISION_REQUEST', 1, 'group:1', 'b'), 'revised request can be sent again');
$message = Module_notification::message('SELF_ORDER', ['order_no' => "ABC\nInjected", 'grand_total' => 12000], array_fill(0, 40, ['product_name' => str_repeat('🍵', 100), 'qty' => 2]), 'https://fixture.invalid/orders');
$check(strlen($message) <= 3900 && mb_check_encoding($message, 'UTF-8'), 'Telegram byte limit and valid UTF8');
$check(strpos($message, 'bukan konfirmasi pembayaran') !== false && strpos($message, "ABC\nInjected") === false, 'no payment claims or injected newlines');
$check(strpos($message, 'item lainnya') !== false, 'long item list summarized');
$policy = require APPPATH . 'config/feature_access.php';
$source = file_get_contents(APPPATH . 'config/feature_access.php');
$check(strpos($source, "'PROCUREMENT AUTOMATION_MESSAGING' => 'division_po_sr_notify'") !== false, 'manual endpoint requires both product entitlements');
foreach (['Whatsapp' => 'require_wa_settings_mutation_csrf', 'Telegram' => "require_post_csrf('settings')"] as $name => $csrf) {
    $code = file_get_contents(APPPATH . 'controllers/' . $name . '.php');
    $body = substr($code, strpos($code, 'public function notification_settings()'));
    $body = substr($body, 0, strpos($body, "\n    public function", 10));
    $check(strpos($body, "require_permission(self::PAGE_SETTINGS, 'edit')") < strpos($body, 'save_rules(') && strpos($body, $csrf) < strpos($body, 'save_rules('), $name . ' settings use RBAC and CSRF before mutation');
}
$code = file_get_contents(APPPATH . 'controllers/Procurement.php');
$body = substr($code, strpos($code, 'public function division_po_sr_notify('));
$body = substr($body, 0, strpos($body, "\n    public function", 10));
$check(strpos($body, 'isDivisionRequestAccessible') < strpos($body, 'get_division_request_detail'), 'division scope checked before reading request items');
$check(strpos($body, 'require_procurement_mutation_csrf()') < strpos($body, 'enqueue_division'), 'manual send is POST/CSRF guarded');
$check(strpos($body, "\$payload['destination']") === false && strpos($body, "\$payload['phone']") === false, 'manual endpoint accepts no arbitrary recipients');
if (!in_array('--disposable', $argv, true)) {
    echo "Module notifications: {$checks} checks PASS (no DB/network).\n";
    exit;
}
if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) throw new RuntimeException('DISPOSABLE_TEST_REQUIRES_LOCAL_ROOT');
require_once $root . '/tools/install/PrivateDeployment.php';
require_once $root . '/tools/install/portable/PortableDatabase.php';
function log_message($level, $message): void {}
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message = '', $status = 500): void { throw new RuntimeException('CI_DB_ERROR'); }
function site_url($path = ''): string { return 'https://fixture.invalid/' . $path; }
$context = new stdClass();
function &get_instance() { return $GLOBALS['context']; }
require BASEPATH . 'core/Model.php';
require BASEPATH . 'database/DB.php';
require APPPATH . 'models/Telegram_model.php';
require APPPATH . 'models/Module_notification_model.php';
class NotificationFixtureGate {
    public array $denied = [];
    public function decision(string $feature, array $context): array { return ['allowed' => !in_array($feature, $this->denied, true)]; }
}
$context->feature_gate = new NotificationFixtureGate();
$context->load = new class {
    public function library($name): void { if ($name !== 'Feature_gate') throw new RuntimeException('Unexpected library'); }
    public function model($name): void { if ($name !== 'Telegram_model') throw new RuntimeException('Unexpected model'); }
};
$base = '/var/lib/finance-notification-test-' . bin2hex(random_bytes(8));
mkdir($base, 0700);
$process = null;
$execute = static function (PDO $pdo, string $sql): void {
    foreach (PortableDatabase::statements($sql) as $statement) {
        $q = $pdo->query($statement);
        if ($q) { do { if ($q->columnCount()) $q->fetchAll(); } while ($q->nextRowset()); $q->closeCursor(); }
    }
};
try {
    $mysql = '/www/server/mysql';
    PrivateDeployment::run([$mysql . '/scripts/mariadb-install-db', '--no-defaults', '--basedir=' . $mysql, '--datadir=' . $base . '/data', '--auth-root-authentication-method=normal', '--skip-test-db'], $base . '/init.log', 120);
    $socket = $base . '/db.sock';
    $process = proc_open([$mysql . '/bin/mariadbd', '--no-defaults', '--user=root', '--basedir=' . $mysql, '--datadir=' . $base . '/data', '--socket=' . $socket, '--pid-file=' . $base . '/pid', '--log-error=' . $base . '/error.log', '--skip-networking', '--skip-log-bin', '--innodb-buffer-pool-size=64M'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 150; $i++) {
        try { $pdo = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); break; } catch (Throwable $e) { usleep(100000); }
    }
    if (!isset($pdo)) throw new RuntimeException('DISPOSABLE_DATABASE_START_FAILED');
    $pdo->exec('CREATE DATABASE notification_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE notification_fixture');
    $execute($pdo, file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql'));
    $execute($pdo, file_get_contents($root . '/sql/2026-09-05a_telegram_bot_foundation.sql'));
    $context->db = DB(['dbdriver' => 'mysqli', 'hostname' => $socket, 'username' => 'root', 'password' => '', 'database' => 'notification_fixture', 'db_debug' => false, 'char_set' => 'utf8mb4'], true);
    $model = new Module_notification_model();
    $context->Telegram_model = new Telegram_model();
    $check(!$model->ready() && $model->enabled_channels() === [], 'missing migration fails closed without breaking existing modules');
    $sql = file_get_contents($root . '/sql/2026-09-23a_module_notifications.sql');
    $execute($pdo, $sql);
    $execute($pdo, file_get_contents($root . '/sql/2026-09-23b_module_notification_pdf_attachment.sql'));
    $context->db->data_cache = [];
    $pdo->exec('ALTER TABLE app_notification_queue DROP INDEX uq_notification_delivery');
    $check(!(new Module_notification_model())->ready(), 'missing deduplication index fails closed');
    $pdo->exec('ALTER TABLE app_notification_queue ADD UNIQUE KEY uq_notification_delivery (delivery_key)');
    $check($model->ready() && $model->enabled_channels() === [], 'clean migration creates empty inactive integration');
    $execute($pdo, $sql);
    $check((int)$pdo->query('SELECT COUNT(*) FROM app_notification_rule')->fetchColumn() === 0, 'migration replay has no seeds or side effects');
    $pdo->exec("INSERT INTO wa_group_map (id,group_key,group_name,group_jid,is_active) VALUES (1,'FIXTURE','WA Fixture','1200001@g.us',0),(2,'OTHER','Other registered group','1200002@g.us',0),(3,'INVALID','Missing JID','',0)");
    $check(count($model->available_targets('WA')) === 2, 'inactive registered WA groups remain sendable; malformed JID excluded');
    $check(count($model->available_targets('WA', true)) === 3 && $model->available_targets('WA', true)['group:3']['unavailable'], 'all registered WA groups visible; invalid JID display-only');
    $reject(fn() => Module_notification::targets(['group:3'], '', $model->available_targets('WA'), 'WA'), 'display-only invalid JID cannot bypass server validation');
    $pdo->exec("INSERT INTO tg_target (id,chat_id,target_type,title,is_active) VALUES (1,'-100123456','SUPERGROUP','Telegram Fixture',1)");
    $pdo->exec('UPDATE tg_target SET is_active=0 WHERE id=1');
    $check($model->available_targets('TELEGRAM') === [], 'Telegram inactive targets still excluded');
    $pdo->exec('UPDATE tg_target SET is_active=1 WHERE id=1');
    $context->Telegram_model->save_enabled(true, 0);
    // Seed only this disposable schema; use real baseline constraints/columns.
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec("INSERT INTO pos_outlet (id,outlet_code,outlet_name) VALUES (1,'FIXTURE','Outlet Fixture')");
    $pdo->exec("INSERT INTO mst_product (id,product_code,product_name,product_division_id,default_operational_division_id,classification_id,product_category_id,uom_id) VALUES (1,'FIXTURE','Kopi Fixture',1,1,1,1,1)");
    $insertOrder = static function (int $id, string $channel = 'SELF_ORDER', string $status = 'PENDING') use ($pdo): void {
        $stmt = $pdo->prepare('INSERT INTO pos_order (id,order_no,order_channel,outlet_id,cashier_employee_id,ordered_at,status,grand_total) VALUES (?,?,?,?,?,NOW(),?,12000)');
        $stmt->execute([$id, 'ORDER-' . $id, $channel, 1, 1, $status]);
        $pdo->exec('INSERT INTO pos_order_line (order_id,line_no,product_id,qty) VALUES (' . $id . ',1,1,2)');
    };
    $pdo->exec("INSERT INTO pur_division_request (id,request_no,request_date,division_id,status) VALUES (1,'REQ-FIXTURE',CURDATE(),1,'SUBMITTED')");
    $insertOrder(1);
    $input = static function (string $key): array {
        $r = []; foreach (Module_notification::EVENTS as $event => $_) $r[$event] = ['enabled' => '1', 'targets' => [$key]]; return $r;
    };
    $wa = $input('group:1'); $tg = $input('chat:1');
    $model->save_rules('WA', $wa, 0); $model->save_rules('TELEGRAM', $tg, 0);
    $check($model->enabled_channels() === ['WA', 'TELEGRAM'], 'both channel buttons available only after configuration');
    $calls = [];
    $send = static function (array $row) use (&$calls): array { $calls[] = $row; return ['ok' => true, 'status' => 'SENT']; };
    $check($model->run('WA', $send)['queued'] === 0, 'initial activation does not broadcast historical orders');
    $insertOrder(2); $insertOrder(3, 'DELIVERY'); $insertOrder(4, 'CASHIER'); $insertOrder(5, 'SELF_ORDER', 'VOID');
    $pdo->exec('UPDATE wa_group_map SET is_active=1 WHERE id=1');
    $model->save_rules('WA', $wa, 0);
    $pdo->exec('UPDATE wa_group_map SET is_active=0 WHERE id=1');
    $model->save_rules('WA', $wa, 0);
    $check((int)$model->rules('WA')['SELF_ORDER']['order_start_id'] === 1, 'toggling inbound replies then saving retains notification cutoff');
    $before = $pdo->query('CHECKSUM TABLE pos_order, pos_order_line, pur_division_request EXTENDED')->fetchAll(PDO::FETCH_ASSOC);
    $r = $model->run('WA', $send);
    $check($r['queued'] === 2 && $r['processed'] === 2, 'actual SQL discovers self/online orders but excludes cashier/void');
    $check(count($calls) === 2 && count(array_filter($calls, static fn(array $row): bool => $row['target_key'] === 'group:1')) === 2, 'inactive selected group receives notifications; unselected group does not');
    $check((int)$pdo->query('SELECT SUM(is_active) FROM wa_group_map')->fetchColumn() === 0, 'saving and dispatch never activate inbound bot replies');
    $check(strpos($calls[0]['message_text'], 'Kopi Fixture') !== false, 'order lines read through real product join');
    $check($model->run('WA', $send)['processed'] === 0, 'repeated polling does not duplicate messages');
    $check($model->run('TELEGRAM', $send)['processed'] === 2, 'independent Telegram delivery');
    $check($before === $pdo->query('CHECKSUM TABLE pos_order, pos_order_line, pur_division_request EXTENDED')->fetchAll(PDO::FETCH_ASSOC), 'delivery leaves business table checksums unchanged');
    $detail = ['header' => ['id' => 1, 'request_no' => 'REQ-FIXTURE', 'request_date' => '2026-09-23', 'division_name' => 'Fixture', 'status' => 'SUBMITTED'], 'lines' => [['profile_name' => 'Bahan Fixture', 'qty_buy_requested' => 2, 'profile_buy_uom_code' => 'PACK']]];
    $model->enqueue_division('WA', $detail, 0); $model->enqueue_division('WA', $detail, 0);
    $check($model->run('WA', $send)['processed'] === 1, 'double-click/manual replay sends division request once');
    $detail['lines'][0]['qty_buy_requested'] = 3;
    $model->enqueue_division('WA', $detail, 0);
    $check($model->run('WA', $send)['processed'] === 1, 'changed request can be shared as a new revision');
    $divisionMessage = Module_notification::message('DIVISION_REQUEST', $detail['header'], $detail['lines'], site_url('procurement/division-po-sr/detail/1'));
    $legacyPdfKey = Module_notification::key('WA', 'DIVISION_REQUEST', 1, 'group:1', hash('sha256', $divisionMessage . '|pdf-v1'));
    $pdo->prepare("INSERT INTO app_notification_queue (delivery_key,channel,event_code,source_id,target_key,destination,target_label,message_text,status,created_at) VALUES (?,'WA','DIVISION_REQUEST',1,'group:1','1200001@g.us','Fixture',?,'SENT',NOW())")->execute([$legacyPdfKey, $divisionMessage]);
    $pdfFixture = ['name'=>'pengajuan-fixture.pdf','path'=>'/synthetic-only/fixture.pdf'];
    $model->enqueue_division('WA', $detail, 0, $pdfFixture);
    $check($model->run('WA', $send)['processed'] === 1, 'corrected stock PDF may be explicitly sent after legacy PDF');
    $model->enqueue_division('WA', $detail, 0, $pdfFixture);
    $check($model->run('WA', $send)['processed'] === 0, 'corrected PDF repeat click remains deduplicated');
    $check($pdo->query('SELECT status FROM app_notification_queue WHERE delivery_key=' . $pdo->quote($legacyPdfKey))->fetchColumn() === 'SENT', 'legacy delivery evidence retained');
    $detail['header']['status'] = 'VOID';
    $reject(fn() => $model->enqueue_division('WA', $detail, 0), 'void request cannot be shared');
    $insertOrder(6);
    $r = $model->run('WA', static fn(array $row): array => ['ok' => false, 'status' => 'UNKNOWN']);
    $unknown = (int)$pdo->query("SELECT id FROM app_notification_queue WHERE channel='WA' AND source_id=6")->fetchColumn();
    $check($r['processed'] === 1 && $model->run('WA', $send)['processed'] === 0, 'ambiguous delivery is not automatically resent');
    $reject(fn() => $model->retry('WA', $unknown), 'operator cannot blindly retry UNKNOWN');
    $insertOrder(7);
    $model->run('WA', static fn(array $row): array => ['ok' => false, 'status' => 'FAILED']);
    $failed = (int)$pdo->query("SELECT id FROM app_notification_queue WHERE channel='WA' AND source_id=7")->fetchColumn();
    $model->retry('WA', $failed);
    $check($model->run('WA', $send)['processed'] === 1, 'known failure can be retried explicitly');
    $reject(fn() => $model->retry('TELEGRAM', $failed), 'queue retry cannot cross channel');
    $insertOrder(8);
    $model->run('WA', static fn(array $row): array => ['ok' => false, 'status' => 'FAILED']);
    $id = (int)$pdo->query("SELECT id FROM app_notification_queue WHERE channel='WA' AND source_id=8")->fetchColumn();
    $model->retry('WA', $id);
    $pdo->exec("UPDATE wa_group_map SET group_jid='1200002@g.us' WHERE id=1");
    $prior = count($calls); $model->run('WA', $send);
    $check(count($calls) === $prior && $pdo->query('SELECT status FROM app_notification_queue WHERE id=' . $id)->fetchColumn() === 'CANCELLED', 'changed registered destination never reroutes queued confidential payload');
    $pdo->exec("UPDATE wa_group_map SET group_jid='1200001@g.us' WHERE id=1");
    $model->save_rules('WA', [], 0);
    $detail['header']['status'] = 'SUBMITTED';
    $reject(fn() => $model->enqueue_division('WA', $detail, 0), 'disabled channel rejected server-side');
    $insertOrder(9); $model->save_rules('WA', $wa, 0);
    $check($model->run('WA', $send)['processed'] === 0, 'reactivation does not send orders from disabled period');
    $insertOrder(10);
    $context->feature_gate->denied = ['AUTOMATION_MESSAGING'];
    $check($model->run('WA', $send)['state'] === 'DISABLED' && $model->enabled_channels() === [], 'license downgrade disables worker and manual buttons');
    $reject(fn() => $model->enqueue_division('WA', $detail, 0), 'license downgrade rejects manual sends');
    $context->feature_gate->denied = ['SELF_ORDER'];
    $check($model->run('WA', $send)['processed'] === 0, 'originating module entitlement enforced independently');
    $context->feature_gate->denied = [];
    $check($model->run('WA', $send)['processed'] === 1, 'valid upgrade resumes without reinstall');
    $inputPhone = $wa; $inputPhone['SELF_ORDER']['phones'] = '6281234567890';
    $reject(fn() => $model->save_rules('WA', $inputPhone, 0), 'pre-existing personal WhatsApp account lock preserved');
    // Separate real DB connection holds the channel lock: overlapping workers do not send twice.
    $name = 'finance-notify:' . substr(hash('sha256', 'notification_fixture'), 0, 20) . ':WA';
    $pdo->query('SELECT GET_LOCK(' . $pdo->quote($name) . ',0)');
    $check($model->run('WA', $send)['state'] === 'BUSY', 'real MariaDB advisory lock excludes overlapping workers');
    $reject(fn() => $model->save_rules('WA', $wa, 0), 'settings cannot race in-flight delivery');
    $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($name) . ')');
    $pdo->exec("UPDATE app_notification_queue SET status='PROCESSING' WHERE id=" . $unknown);
    $model->run('WA', $send);
    $check($pdo->query('SELECT status FROM app_notification_queue WHERE id=' . $unknown)->fetchColumn() === 'UNKNOWN', 'interrupted worker retains ambiguous delivery evidence');
    $context->Telegram_model->save_enabled(false, 0);
    $check($model->run('TELEGRAM', $send)['state'] === 'DISABLED', 'Telegram master switch respected');
    $multi = $wa; $multi['SELF_ORDER']['targets'] = ['group:1', 'group:2'];
    $model->save_rules('WA', $multi, 0);
    $insertOrder(11);
    $check($model->run('WA', $send)['processed'] === 2, 'multiple checked inactive groups each receive the new self order');
    $check((int)$pdo->query("SELECT COUNT(DISTINCT target_key) FROM app_notification_queue WHERE channel='WA' AND source_id=11 AND status='SENT'")->fetchColumn() === 2, 'separate delivery evidence for each selected group');
    $check($model->run('WA', $send)['processed'] === 0, 'multiple recipients remain deduplicated');
    $model->save_rules('WA', $wa, 0);
    $insertOrder(12);
    $check($model->run('WA', $send)['processed'] === 1, 'unchecking a group stops its future notifications');
    $check($model->enabled_channels('DAILY_SALES') === [], 'Daily Sales OFF by default');
    $dailyRules = $wa; $dailyRules['DAILY_SALES'] = ['enabled'=>'1','targets'=>['group:1','group:2']];
    $model->save_rules('WA', $dailyRules, 0);
    $check($model->enabled_channels('DAILY_SALES') === ['WA'], 'Daily Sales supports configured WA channel only');
    $filters = ['date'=>'2026-09-23','outlet_id'=>1];
    require_once APPPATH . 'libraries/Daily_sales_pdf.php';
    $fingerprint = Daily_sales_pdf::snapshot($filters, ['total'=>100], 'Fixture');
    mkdir(APPPATH . 'cache/wa-attachments', 0700);
    $attachment = ['path'=>APPPATH . 'cache/wa-attachments/daily-sales-' . $fingerprint . '.pdf', 'name'=>'daily-sales-2026-09-23-outlet-1.pdf'];
    file_put_contents($attachment['path'], "%PDF-1.4\nqueue fixture only");
    $businessBefore = $pdo->query('CHECKSUM TABLE pos_order,pos_order_line,pur_division_request EXTENDED')->fetchAll(PDO::FETCH_ASSOC);
    $model->enqueue_daily_sales($filters, 'Fixture', $fingerprint, 0, $attachment);
    $model->enqueue_daily_sales($filters, 'Fixture', $fingerprint, 0, $attachment);
    $check($model->run('WA', $send)['processed'] === 2, 'Daily Sales PDF sends once per selected group, no pos_order lookup/cutoff');
    $dailyCalls = array_values(array_filter($calls, static fn(array $row): bool => $row['event_code'] === 'DAILY_SALES'));
    $check(count($dailyCalls) === 2 && $dailyCalls[0]['attachment_path'] === $attachment['path'], 'PDF attachment delivered through existing worker');
    $check($model->run('WA', $send)['processed'] === 0, 'Daily Sales replay deduplicated');
    $changed = Daily_sales_pdf::snapshot($filters, ['total'=>120], 'Fixture');
    $model->enqueue_daily_sales($filters, 'Fixture', $changed, 0, $attachment);
    $context->feature_gate->denied = ['SALES_REPORTING'];
    $reject(fn() => $model->enqueue_daily_sales($filters, 'Fixture', $changed, 0, $attachment), 'Daily Sales entitlement required for enqueue');
    $check($model->run('WA', $send)['processed'] === 0, 'license downgrade cancels unsent report');
    $context->feature_gate->denied = [];
    $changedAgain = Daily_sales_pdf::snapshot($filters, ['total'=>130], 'Fixture');
    $model->enqueue_daily_sales($filters, 'Fixture', $changedAgain, 0, $attachment);
    $model->save_rules('WA', $wa, 0);
    $check($model->run('WA', $send)['processed'] === 0, 'turning Daily Sales OFF cancels pending report');
    $check($businessBefore === $pdo->query('CHECKSUM TABLE pos_order,pos_order_line,pur_division_request EXTENDED')->fetchAll(PDO::FETCH_ASSOC), 'report sends leave business tables unchanged');
    $snapshot = $pdo->query('CHECKSUM TABLE app_notification_rule,app_notification_queue EXTENDED')->fetchAll(PDO::FETCH_ASSOC);
    $execute($pdo, $sql);
    $check($snapshot === $pdo->query('CHECKSUM TABLE app_notification_rule,app_notification_queue EXTENDED')->fetchAll(PDO::FETCH_ASSOC), 'SQL replay preserves settings and delivery evidence');
    echo 'Module notifications: ' . $checks . ' checks PASS; MariaDB ' . $pdo->query('SELECT VERSION()')->fetchColumn() . "; synthetic sends only; active DB untouched.\n";
} finally {
    if (isset($context->db)) $context->db->close();
    $pdo = null;
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    // Deliberately retain disposable files for inspection; never clean unrelated paths.
    echo 'Disposable evidence: ' . $base . "\n";
}
