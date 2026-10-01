<?php
declare(strict_types=1);

// Exercises the real proof service with session/DB doubles. No credential,
// order, or transaction from staging is read or written.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');

$stepUpCi = null;
function &get_instance() { global $stepUpCi; return $stepUpCi; }
final class PosStepUpQuery { public function __construct(private ?array $row) {} public function row_array(): array { return $this->row ?? []; } }
final class PosStepUpDb
{
    public ?array $row; public array $calls = [];
    public function __construct(?array $row) { $this->row = $row; }
    public function select($fields): self { $this->calls[] = ['select', $fields]; return $this; }
    public function from($table): self { $this->calls[] = ['from', $table]; return $this; }
    public function where($field, $value = null, $escape = true): self { $this->calls[] = ['where', $field, $value, $escape]; return $this; }
    public function limit($limit): self { $this->calls[] = ['limit', $limit]; return $this; }
    public function get(): PosStepUpQuery { $this->calls[] = ['get']; return new PosStepUpQuery($this->row); }
}
final class PosStepUpSession
{
    public array $values = [], $writes = [], $unsets = [];
    public function userdata($key) { return $this->values[$key] ?? null; }
    public function set_userdata($key, $value): void { $this->values[$key] = $value; $this->writes[] = [$key, $value]; }
    public function unset_userdata($key): void { unset($this->values[$key]); $this->unsets[] = $key; }
}
require dirname(__DIR__, 2) . '/application/libraries/SensitiveActionStepUp.php';

function posStepUpFixture(?array $row, int &$now): array
{
    global $stepUpCi;
    $stepUpCi = new stdClass();
    $stepUpCi->db = new PosStepUpDb($row);
    $stepUpCi->session = new PosStepUpSession();
    return [new SensitiveActionStepUp(['clock' => static function () use (&$now): int { return $now; }]), $stepUpCi];
}
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$now = 1700000000;
$hash = password_hash('correct horse battery staple', PASSWORD_BCRYPT, ['cost' => 4]);
[$service, $ci] = posStepUpFixture(['id' => 7, 'password_hash' => $hash], $now);
foreach ([[0, 'VOID', 12, 'correct horse battery staple'], [7, 'UNKNOWN', 12, 'correct horse battery staple'], [7, 'VOID', 0, 'correct horse battery staple'], [7, 'VOID', 12, ''], [7, 'VOID', 12, str_repeat('x', 73)], [7, 'VOID', [], 'correct horse battery staple'], [7, [], 12, 'correct horse battery staple'], [7, 'VOID', [], 'correct horse battery staple']] as [$user, $action, $target, $password]) {
    $result = $service->issue($user, $action, $target, $password);
    $check($result['ok'] === false && $result['status'] === 422, 'malformed proof request is rejected before verification');
}
$check($ci->db->calls === [], 'malformed proof request does not read auth_user');

$result = $service->issue(7, 'VOID', 12, 'wrong password');
$state = $ci->session->values['finance_sensitive_action_step_up'] ?? [];
$check($result['ok'] === false && $result['status'] === 403, 'wrong password is denied');
$check(($state['failure']['count'] ?? 0) === 1 && !array_key_exists('proof_hash', $state), 'failed verification stores only bounded failure state');
$check(strpos(serialize($state), 'wrong password') === false, 'password is never stored in session state');

$issued = $service->issue(7, 'VOID', 12, 'correct horse battery staple');
$state = $ci->session->values['finance_sensitive_action_step_up'] ?? [];
$proof = (string)($issued['proof'] ?? '');
$check($issued['ok'] === true && preg_match('/\A[0-9a-f]{64}\z/D', $proof) === 1 && ($issued['expires_in_seconds'] ?? 0) === 180, 'valid password issues bounded random proof');
$check(($state['user_id'] ?? 0) === 7 && ($state['action'] ?? '') === 'VOID' && ($state['target_id'] ?? 0) === 12, 'proof binds current user, action, and exact order');
$check(isset($state['proof_hash']) && !hash_equals((string)$state['proof_hash'], $proof) && strpos(serialize($state), $proof) === false, 'session keeps only proof hash');
$check(($state['failure'] ?? null) === [], 'successful verification clears failure counter');
foreach ([[8, 'VOID', 12, $proof], [7, 'REFUND', 12, $proof], [7, 'VOID', 13, $proof], [7, 'VOID', 12, str_repeat('b', 64)], [7, 'VOID', 12, ['proof']]] as [$user, $action, $target, $candidate]) {
    $result = $service->consume($user, $action, $target, $candidate);
    $check($result['ok'] === false && $result['status'] === 428, 'proof cannot be reused for a different identity/action/document or malformed value');
    $check(isset($ci->session->values['finance_sensitive_action_step_up']), 'invalid consume does not burn valid proof');
}
$result = $service->consume(7, 'VOID', 12, $proof);
$check($result['ok'] === true && !isset($ci->session->values['finance_sensitive_action_step_up']), 'valid proof is consumed exactly once');
$check($service->consume(7, 'VOID', 12, $proof)['ok'] === false, 'replayed proof is denied');

$issued = $service->issue(7, 'REFUND', 33, 'correct horse battery staple');
$now += 181;
$check($service->consume(7, 'REFUND', 33, $issued['proof'])['ok'] === false, 'expired proof is denied');

$now = 1700000900;
$issued = $service->issue(7, 'PERIOD_REOPEN', 44, 'correct horse battery staple');
$check($issued['ok'] === true && $service->consume(7, 'PERIOD_REOPEN', 44, $issued['proof'])['ok'] === true, 'period reopen can use the same scoped one-use proof contract');
$issued = $service->issue(7, 'ORDER_REPRINT', 45, 'correct horse battery staple');
$check($issued['ok'] === true && $service->consume(7, 'ORDER_REPRINT', 45, $issued['proof'])['ok'] === true, 'order reprint can use the same scoped one-use proof contract');
$issued = $service->issue(7, 'COMPONENT_ADJUSTMENT_POST', 46, 'correct horse battery staple');
$check($issued['ok'] === true && $service->consume(7, 'COMPONENT_ADJUSTMENT_POST', 46, $issued['proof'])['ok'] === true, 'component adjustment post can use the same scoped one-use proof contract');
$issued = $service->issue(7, 'COMPONENT_ADJUSTMENT_VOID', 47, 'correct horse battery staple');
$check($issued['ok'] === true && $service->consume(7, 'COMPONENT_ADJUSTMENT_VOID', 47, $issued['proof'])['ok'] === true, 'component adjustment void can use the same scoped one-use proof contract');
$issued = $service->issue(7, 'STOCK_ADJUSTMENT_POST', 48, 'correct horse battery staple');
$check($issued['ok'] === true && $service->consume(7, 'STOCK_ADJUSTMENT_POST', 48, $issued['proof'])['ok'] === true, 'stock adjustment post can use the same scoped one-use proof contract');
$issued = $service->issue(7, 'STOCK_ADJUSTMENT_VOID', 49, 'correct horse battery staple');
$check($issued['ok'] === true && $service->consume(7, 'STOCK_ADJUSTMENT_VOID', 49, $issued['proof'])['ok'] === true, 'stock adjustment void can use the same scoped one-use proof contract');

$now = 1700001000;
[$service, $ci] = posStepUpFixture(['id' => 7, 'password_hash' => $hash], $now);
for ($i = 0; $i < 5; $i++) $service->issue(7, 'VOID', 12, 'wrong password');
$callsBeforeLock = count($ci->db->calls);
$locked = $service->issue(7, 'VOID', 12, 'correct horse battery staple');
$check($locked['ok'] === false && $locked['status'] === 429 && count($ci->db->calls) === $callsBeforeLock, 'five failed attempts lock session before another password query');
$now += 601;
$check($service->issue(7, 'VOID', 12, 'correct horse battery staple')['ok'] === true, 'verification recovers after bounded lock window');

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Pos.php');
$view = (string)file_get_contents($root . '/application/views/pos/cashier_index.php');
$paidView = (string)file_get_contents($root . '/application/views/pos/order_paid_index.php');
$block = static function (string $source, string $method): string {
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        $name = '';
        for ($j = $i + 1; $j < count($tokens); $j++) { if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name = $tokens[$j][1]; break; } if ($tokens[$j] === '(') break; }
        if ($name !== $method) continue;
        $depth = 0; $opened = false; $result = '';
        for ($j = $i; $j < count($tokens); $j++) { $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j]; $result .= $text; if ($text === '{') { $opened = true; $depth++; } if ($text === '}' && $opened && --$depth === 0) return $result; }
    }
    return '';
};
$ordered = static function (string $source, array $needles): bool { $at = -1; foreach ($needles as $needle) { $next = strpos($source, $needle, $at + 1); if ($next === false) return false; $at = $next; } return true; };
$check(strpos($routes, "\$route['pos/orders/reversal-step-up/verify'] = 'pos/order_reversal_step_up_verify';") !== false, 'fixed local step-up route is registered');
$check(strpos($routes, "\$route['pos/orders/reprint-step-up/verify'] = 'pos/order_reprint_step_up_verify';") !== false, 'fixed local order-reprint step-up route is registered');
$verify = $block($controller, 'order_reversal_step_up_verify');
$check($ordered($verify, ['require_pos_transaction_csrf()', '$this->request_payload()', 'require_permission(', "load->library('SensitiveActionStepUp'", '->issue(', '$this->json_ok(']), 'verification endpoint has CSRF, RBAC, and service issuance in order');
foreach (['order_void_save' => ['VOID', 'save_order_void'], 'order_refund_save' => ['REFUND', 'save_order_refund']] as $method => [$action, $writer]) {
    $writerBlock = $block($controller, $method);
    $check($ordered($writerBlock, ['require_permission(', 'require_pos_transaction_csrf()', '$this->request_payload()', "consume_order_reversal_step_up('{$action}'", "unset(\$payload['step_up_proof'])", "\$this->Pos_model->{$writer}("]), $method . ' consumes proof before writer and removes it from model payload');
    $check(strpos($writerBlock, "\$payload['password']") === false, $method . ' never accepts a password at the financial writer');
}
$reprintVerify = $block($controller, 'order_reprint_step_up_verify');
$check($ordered($reprintVerify, ['require_permission(', 'require_pos_transaction_csrf()', '$this->request_payload()', "load->library('SensitiveActionStepUp'", '->issue(', "'ORDER_REPRINT'", '$this->json_ok(']), 'reprint verification endpoint keeps permission, CSRF, and issuance order');
$reprintWriter = $block($controller, 'order_reprint_print_targets');
$check($ordered($reprintWriter, ['require_permission(', 'require_pos_transaction_csrf()', '$this->request_payload()', 'consume_order_reprint_step_up(', "unset(\$payload['step_up_proof'])", 'direct_print_targets_for_order_reprint(']), 'reprint consumes proof before preparing printer targets and removes it from model payload');
$check(strpos($reprintWriter, "\$payload['password']") === false, 'reprint printer-target writer never accepts a password');
$check(strpos($view, 'type="password" class="form-control" id="cashier_reversal_step_up_password"') !== false && strpos($view, 'autocomplete="current-password"') !== false, 'cashier uses a masked current-password field');
$check($ordered($view, ["const password = String(reversalStepUpPassword?.value || '');", "reversalStepUpPassword.value = '';", "pos/orders/reversal-step-up/verify", 'step_up_proof']), 'cashier clears password before receiving and forwarding one-use proof');
$check(strpos($view, 'type="password" class="form-control" id="cashier_order_reprint_step_up_password"') !== false && strpos($view, 'autocomplete="current-password"') !== false, 'cashier reprint uses a masked current-password field');
$check($ordered($view, ["const password = String(orderReprintStepUpPassword?.value || '');", "orderReprintStepUpPassword.value = '';", 'pos/orders/reprint-step-up/verify', 'step_up_proof', 'pos/orders/reprint-print-targets']), 'cashier reprint clears password then forwards only the one-use proof to printer-target preparation');
$check(strpos($paidView, 'type="password" class="form-control" id="refund_step_up_password"') !== false && strpos($paidView, 'autocomplete="current-password"') !== false, 'paid-order refund uses a masked current-password field');
$check($ordered($paidView, ["const password = String(refundStepUpPassword?.value || '');", "refundStepUpPassword.value = '';", "pos/orders/reversal-step-up/verify", "action: 'REFUND'", 'step_up_proof', "pos/orders/refund/save"]), 'paid-order refund clears password then forwards only the one-use proof to its writer');
$check(strpos($view, 'password only') === false && strpos((string)file_get_contents($root . '/application/views/pos/cashier_index_bak.php'), 'cashier_reversal_step_up_password') === false && strpos((string)file_get_contents($root . '/application/views/pos/cashier_index_bak.php'), 'cashier_order_reprint_step_up_password') === false, 'backup APK comparison file remains untouched');
echo 'PASS pos-reversal-step-up checks=' . $checks . PHP_EOL;
