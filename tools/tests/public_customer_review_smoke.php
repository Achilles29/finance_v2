<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
define('ENVIRONMENT', 'testing');
session_id('public-review-fixture-session');
require APPPATH . 'libraries/CustomerReviewGuard.php';
require APPPATH . 'libraries/CustomerReviewInput.php';
$temp = sys_get_temp_dir() . '/finance-review-smoke-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
$parentPid = getmypid();
register_shutdown_function(static function () use ($temp, $parentPid): void {
    if (getmypid() !== $parentPid) return;
    foreach (['guard', 'concurrent', 'ip', 'device', 'corrupt', 'controller'] as $name) {
        $file = $temp . '/' . $name . '/state.php';
        if (is_file($file)) unlink($file);
        if (is_dir($temp . '/' . $name)) rmdir($temp . '/' . $name);
    }
    rmdir($temp);
});
$now = 1800000000;
$makeGuard = static function (string $name) use ($temp, &$now): CustomerReviewGuard {
    return new CustomerReviewGuard(['directory' => $temp . '/' . $name, 'clock' => static function () use (&$now): int { return $now; }]);
};
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
    echo 'PASS: ' . $message . PHP_EOL;
};
$guard = $makeGuard('guard');
$ip = '192.0.2.45'; $device = 'private-session-fixture'; $target = 'station:GENERAL';
$form = $guard->issue($target, $device);
$input = ['_review_guard' => $form['token'], 'website' => ''];
$check($form['ok'] && $guard->authorize($target, $device, $ip, $input, 'phone-a', 'comment-a')['status'] === 403, 'too-fast submission denied');
$now += 2;
$check($guard->authorize($target, 'another-session', $ip, $input, 'phone-a', 'comment-a')['status'] === 403, 'form cannot cross browser sessions');
$check($guard->authorize('receipt:other', $device, $ip, $input, 'phone-a', 'comment-a')['status'] === 403, 'form cannot cross target or receipt/station boundary');
$check($guard->authorize($target, $device, $ip, array_replace($input, ['website' => 'https://spam.invalid']), 'phone-a', 'comment-a')['status'] === 403, 'honeypot rejected');
$accepted = $guard->authorize($target, $device, $ip, $input, 'phone-a', 'comment-a');
$check($accepted['ok'], 'valid session-bound form accepted');
$check($guard->authorize($target, $device, $ip, $input, 'phone-a', 'comment-a')['status'] === 429, 'replay cannot reserve a second write');
$another = $guard->issue($target, 'another-session'); $now += 2;
$check($guard->authorize($target, 'another-session', $ip, ['_review_guard' => $another['token']], 'phone-a', 'different-comment')['status'] === 429, 'same phone cooldown survives a new session');
$now += 60;
$another = $guard->issue($target, 'another-session'); $now += 2;
$check($guard->authorize($target, 'another-session', $ip, ['_review_guard' => $another['token']], 'phone-a', 'comment-a')['status'] === 429, 'duplicate content survives device cooldown');
$guard->outcome($ip, $accepted['reservation'], ['ok' => false]);
$now += 61;
$another = $guard->issue($target, 'another-session'); $now += 2;
$check($guard->authorize($target, 'another-session', $ip, ['_review_guard' => $another['token']], 'phone-a', 'comment-a')['ok'], 'failed save permits retry after normal cooldown');
$expired = $guard->issue($target, 'expired-session'); $now += 3601;
$check($guard->authorize($target, 'expired-session', $ip, ['_review_guard' => $expired['token']], 'phone-b', 'comment-b')['status'] === 403, 'expired form denied');
$guard->outcome($ip, '', ['ok' => true, 'review_id' => 7, 'member_id' => 8, 'member_created' => true]);
$raw = file_get_contents($temp . '/guard/state.php');
$check(strpos($raw, $ip) === false && strpos($raw, $device) === false && strpos($raw, 'phone-a') === false && strpos($raw, 'comment-a') === false && strpos($raw, $form['token']) === false, 'runtime diagnostics never contain IP, session, phone, comment, or form token');
$state = json_decode(substr($raw, strlen("<?php exit; ?>\n")), true);
$event = end($state['events']);
$check($event['member_created'] && $event['review_id'] === 7 && $event['member_id'] === 8, 'member creation diagnostic links only internal record IDs');
$ipGuard = $makeGuard('ip'); $ipAllowed = 0;
for ($n = 0; $n < 61; $n++) if ($ipGuard->attempt($ip, 'device-' . $n)['ok']) $ipAllowed++;
$check($ipAllowed === 60, 'IP limit applies across fresh browser sessions');
$deviceGuard = $makeGuard('device'); $deviceAllowed = 0;
for ($n = 0; $n < 13; $n++) if ($deviceGuard->attempt('192.0.2.' . ($n + 1), $device)['ok']) $deviceAllowed++;
$check($deviceAllowed === 12, 'device attempt limit applies across IP changes');
$now += 600;
$check($deviceGuard->attempt($ip, $device)['ok'], 'expired rate bucket allows legitimate retry');
$broken = $makeGuard('corrupt'); $broken->issue($target, $device);
file_put_contents($temp . '/corrupt/state.php', 'broken fixture');
$check($broken->attempt($ip, $device)['status'] === 503, 'corrupt limiter fails closed');
$unwritable = new CustomerReviewGuard(['directory' => $temp . '/absent/child']);
$check($unwritable->attempt($ip, $device)['status'] === 503, 'unavailable runtime storage fails closed');
$parallel = $makeGuard('concurrent');
$form = $parallel->issue($target, $device); $now += 2;
$children = [];
for ($n = 0; $n < 4; $n++) {
    $pid = pcntl_fork();
    if ($pid === -1) throw new RuntimeException('Cannot fork concurrency fixture.');
    if ($pid === 0) {
        $result = $parallel->authorize($target, $device, $ip, ['_review_guard' => $form['token']], 'same-phone', 'same-review');
        exit($result['ok'] ? 0 : 10);
    }
    $children[] = $pid;
}
$winners = 0;
foreach ($children as $pid) { pcntl_waitpid($pid, $status); if (pcntl_wexitstatus($status) === 0) $winners++; }
$check($winners === 1, 'four concurrent PHP workers reserve exactly one submission');

$valid = ['rating' => '5', 'review_text' => 'Enak', 'customer_name' => 'Nama input', 'mobile_phone' => '+62 812-1234-5678', 'join_member' => '1'];
$check(CustomerReviewInput::validate($valid, true)['input']['mobile_phone'] === '081212345678', 'phone aliases normalized before cooldown');
foreach ([['rating' => '5.0'], ['rating' => ['5']], ['review_text' => str_repeat('x', 1201)], ['customer_name' => ['x']], ['mobile_phone' => 'abc08121234567'], ['review_text' => "a\0b"], ['review_text' => "\xFF"], ['join_member' => '']] as $invalid) {
    $check(!CustomerReviewInput::validate(array_replace($valid, $invalid), true)['ok'], 'malformed, oversized, or unconsented input rejected');
}

// Real review model and CI transactions on isolated SQLite; member writer models nested POS transactions.
function log_message($level, $message): void {}
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function site_url($path): string { return '/' . $path; }
function redirect($path): void { get_instance()->output->status = 302; }
$context = new stdClass();
function &get_instance() { return $GLOBALS['context']; }
require BASEPATH . 'core/Model.php';
require BASEPATH . 'database/DB.php';
require APPPATH . 'models/Pos_customer_review_model.php';
$context->db = DB(['dbdriver' => 'sqlite3', 'database' => ':memory:', 'db_debug' => false], true);
foreach ([
    'CREATE TABLE pos_order (id INTEGER PRIMARY KEY, outlet_id INTEGER, status TEXT)',
    'CREATE TABLE pos_outlet (id INTEGER PRIMARY KEY, outlet_name TEXT)',
    'CREATE TABLE pos_customer_review_station (id INTEGER PRIMARY KEY, station_code TEXT, station_name TEXT, outlet_id INTEGER, is_active INTEGER)',
    'CREATE TABLE crm_member (id INTEGER PRIMARY KEY, member_name TEXT, member_no TEXT, mobile_phone TEXT, is_active INTEGER, member_status TEXT)',
    "CREATE TABLE pos_customer_review (id INTEGER PRIMARY KEY, review_token TEXT UNIQUE, review_source TEXT, order_id INTEGER, outlet_id INTEGER, station_id INTEGER, member_id INTEGER, order_no_snapshot TEXT, customer_name_snapshot TEXT, visitor_phone_snapshot TEXT, rating INTEGER, review_text TEXT, review_status TEXT, submitted_at TEXT, ip_hash TEXT, user_agent TEXT)",
    "INSERT INTO pos_customer_review_station VALUES (1, 'GENERAL', 'Area umum', NULL, 1)",
    "INSERT INTO crm_member VALUES (1, 'PRIVATE REAL NAME', 'PRIVATE MEMBER NUMBER', '081212345678', 1, 'ACTIVE')",
] as $sql) $context->db->query($sql);
$context->load = new class {
    public function model($models): void {}
    public function library($libraries): void {}
    public function view($view, $data): void { get_instance()->rendered = $data; }
};
$context->Pos_model = new class {
    public bool $fail = false;
    public function save_member(array $data): array {
        $db = get_instance()->db; $db->trans_begin();
        if ($this->fail) { $db->trans_rollback(); return ['ok' => false, 'message' => 'PRIVATE REAL NAME PRIVATE MEMBER NUMBER']; }
        $db->insert('crm_member', ['member_name' => $data['member_name'], 'mobile_phone' => $data['mobile_phone'], 'is_active' => 1, 'member_status' => 'ACTIVE']);
        $id = $db->insert_id(); $db->trans_commit();
        return ['ok' => true, 'id' => $id];
    }
    public function find_member(int $id): array { return get_instance()->db->get_where('crm_member', ['id' => $id])->row_array(); }
};
$model = new Pos_customer_review_model();
$result = $model->submit_station_review('GENERAL', $valid);
$check($result['ok'] && $result['member_name'] === 'Nama input' && !isset($result['member_no']), 'existing-member response exposes neither stored name nor member number');
$saved = $context->db->get('pos_customer_review')->row_array();
$check($saved['ip_hash'] === null && $saved['user_agent'] === null && $saved['customer_name_snapshot'] === 'Nama input', 'new review minimizes client tracking and does not assert verified identity');
$context->Pos_model->fail = true;
$failed = $model->submit_station_review('GENERAL', array_replace($valid, ['mobile_phone' => '081211111111']));
$check(!$failed['ok'] && strpos(json_encode($failed), 'PRIVATE') === false, 'member error details do not escape public model');
$context->Pos_model->fail = false;
$result = $model->submit_station_review('GENERAL', array_replace($valid, ['mobile_phone' => '081211111111']));
$check($result['ok'] && $result['member_created'] && $context->db->count_all('crm_member') === 2, 'new member and review commit together');
$receiptToken = str_repeat('b', 64);
$context->db->insert('pos_order', ['id' => 1, 'status' => 'PAID']);
$context->db->insert('pos_customer_review', ['review_token' => $receiptToken, 'order_id' => 1, 'review_status' => 'OPEN']);
$check($model->submit($receiptToken, 5, 'Receipt fixture')['ok'], 'receipt OPEN transitions to SUBMITTED');
$check(!$model->submit($receiptToken, 1, 'Must not replace rating')['ok'], 'already submitted receipt cannot be overwritten');
$context->db->query("CREATE TRIGGER fail_review BEFORE INSERT ON pos_customer_review BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
set_error_handler(static function ($severity, $message): bool { return strpos($message, 'fixture failure') !== false; });
$result = $model->submit_station_review('GENERAL', array_replace($valid, ['mobile_phone' => '081222222222']));
restore_error_handler();
$check(!$result['ok'] && $context->db->count_all('crm_member') === 2 && $context->db->count_all('pos_customer_review') === 3, 'review insert failure rolls back nested member creation');

// Controller boundary: rejected requests must not call either writer.
class CI_Controller { public function __construct() {} public function __get($key) { return get_instance()->$key; } }
require APPPATH . 'controllers/Customer_reviews.php';
$context->customerreviewguard = $makeGuard('controller');
$context->Pos_customer_review_model = new class {
    public int $writes = 0;
    public function submit_station_review($target, $input): array { $this->writes++; return ['ok' => true]; }
    public function submit($target, $rating, $text): array { $this->writes++; return ['ok' => true]; }
    public function find_station_by_code($code): array { return ['is_active' => 1, 'station_name' => 'Area umum']; }
    public function find_by_token($token): array { return ['review_status' => 'OPEN', 'customer_name_snapshot' => 'PRIVATE REAL NAME']; }
};
$context->input = new class {
    public array $data = [];
    public string $verb = 'POST';
    public int $length = 500;
    public function post($key = null, $filter = false) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
    public function ip_address(): string { return '192.0.2.80'; }
    public function server($key) { return $this->length; }
    public function method(): string { return $this->verb; }
};
$context->output = new class {
    public int $status = 200;
    public array $headers = [];
    public function set_header($header): self { $this->headers[] = $header; return $this; }
    public function set_status_header($status): self { $this->status = $status; return $this; }
};
$controller = new Customer_reviews();
$context->input->data = $valid;
$controller->station_submit('GENERAL');
$check($context->output->status === 403 && $context->Pos_customer_review_model->writes === 0, 'missing signed form rejected before member/review writer');
$context->input->data = array_replace($valid, ['rating' => ['5']]);
$controller->station_submit('GENERAL');
$check($context->output->status === 422 && $context->Pos_customer_review_model->writes === 0, 'array payload rejected before writer without PHP casts');
$context->input->length = 20000; $controller->station_submit('GENERAL');
$check($context->output->status === 413 && $context->Pos_customer_review_model->writes === 0, 'oversized body rejected before writer');
$context->input->length = 500; $context->input->data = $valid;
$controller->station('GENERAL'); $form = $context->rendered['form_guard']; $now += 2;
$context->input->data['_review_guard'] = $form;
$controller->station_submit('GENERAL');
$check($context->Pos_customer_review_model->writes === 1, 'valid browser form reaches station writer once');
$controller->station_submit('GENERAL');
$check($context->output->status === 429 && $context->Pos_customer_review_model->writes === 1, 'controller duplicate returns 429 without another write');
$controller->submit(str_repeat('a', 64));
$check($context->output->status === 403 && $context->Pos_customer_review_model->writes === 1, 'station token cannot submit receipt');
$check(in_array('Referrer-Policy: no-referrer', $context->output->headers, true) && in_array('Cache-Control: private, no-store', $context->output->headers, true), 'public form sets privacy/cache headers');
$context->input->data = ['customer_name' => ['invalid'], 'mobile_phone' => ['invalid'], 'review_text' => ['invalid']];
$render = static function (string $view, array $data): string { extract($data); ob_start(); include APPPATH . 'views/pos/' . $view . '.php'; return (string)ob_get_clean(); };
$html = $render('customer_review_station_form', ['station' => ['is_active' => 1], 'station_code' => 'GENERAL', 'form_guard' => 'fixture']);
$check(strpos($html, 'name="_review_guard"') !== false && strpos($html, 'name="website"') !== false, 'station form renders safely even after malformed POST');
$html = $render('customer_review_form', ['review' => ['review_status' => 'OPEN', 'customer_name_snapshot' => 'PRIVATE REAL NAME'], 'token' => 'fixture', 'form_guard' => 'fixture']);
$check(strpos($html, 'PRIVATE REAL NAME') === false && strpos($html, 'name="_review_guard"') !== false, 'receipt form includes guard and excludes stored customer name');
echo 'PUBLIC_REVIEW PASS checks=' . $checks . PHP_EOL;
