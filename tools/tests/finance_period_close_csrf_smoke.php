<?php
declare(strict_types=1);

// Real controller and rendered forms; no database, live login, or period writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
set_error_handler(static function ($severity, $message, $file, $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
final class PeriodCloseResponse extends RuntimeException {}
class MY_Controller
{
    public $input, $output, $session, $load, $Finance_report_model;
    public array $current_user = ['id' => 7];
    public array $allowed = ['view', 'create', 'edit'], $permissions = [], $rendered = [];
    public function require_permission($page, $action): void {
        $this->permissions[] = [$page, $action];
        if (!$this->can($page, $action)) throw new PeriodCloseResponse('Permission denied', 403);
    }
    public function can($page, $action): bool { return in_array($action, $this->allowed, true); }
    public function render($view, $data): void { $this->rendered = [$view, $data]; }
}
function show_error($message, $status = 500, $heading = ''): void { throw new PeriodCloseResponse($message, $status); }
function show_404(): void { throw new PeriodCloseResponse('Not found', 404); }
function redirect($url): void { throw new PeriodCloseResponse($url, 303); }
function site_url($uri = ''): string { return 'https://finance.example.test/' . $uri; }
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
require dirname(__DIR__, 2) . '/application/controllers/Finance_reports.php';

function periodCloseFixture($expected): Finance_reports
{
    $c = (new ReflectionClass(Finance_reports::class))->newInstanceWithoutConstructor();
    $c->input = new class {
        public string $verb = 'POST';
        public array $values = [], $reads = [];
        public bool $ajax = false;
        public function method($upper = false): string { return $upper ? $this->verb : strtolower($this->verb); }
        public function post($key = null, $clean = false) {
            $this->reads[] = $key;
            return $key === null ? $this->values : ($this->values[$key] ?? null);
        }
        public function get($key, $clean = false) { return null; }
        public function is_ajax_request(): bool { return $this->ajax; }
    };
    $c->output = new class {
        public int $status = 200;
        public string $body = '', $type = '';
        public array $headers = [];
        public function set_header($value): self { $this->headers[] = $value; return $this; }
        public function set_status_header($value): self { $this->status = $value; return $this; }
        public function set_content_type($value): self { $this->type = $value; return $this; }
        public function set_output($value): self { $this->body = $value; return $this; }
    };
    $c->session = new class($expected) {
        public array $values, $writes = [], $flash = [];
        public function __construct($expected) { $this->values = ['finance_period_close_csrf' => $expected, 'pos_transaction_csrf' => 'unchanged']; }
        public function userdata($key) { return $this->values[$key] ?? null; }
        public function set_userdata($key, $value): void { $this->values[$key] = $value; $this->writes[] = $key; }
        public function set_flashdata($key, $value): void { $this->flash[$key] = $value; }
        public function flashdata($key) { return $this->flash[$key] ?? null; }
    };
    $c->Finance_report_model = new class {
        public array $calls = [];
        public bool $ok = true, $found = true;
        public function __call($method, $args) {
            $this->calls[] = [$method, $args];
            if (in_array($method, ['save_period_close', 'close_period', 'reopen_period'], true)) return ['ok' => $this->ok, 'message' => 'Fixture result'];
            if ($method === 'count_period_closes') return 1;
            if ($method === 'list_period_closes') return [['id' => 12, 'status' => 'OPEN']];
            if ($method === 'get_period_close_detail') return $this->found ? ['id' => 12, 'status' => 'CLOSED'] : null;
            if (in_array($method, ['summarize_period_closes', 'list_period_close_snapshots', 'list_period_close_metrics', 'summarize_period_close_snapshots', 'summarize_period_close_metrics'], true)) return [];
            throw new RuntimeException('Unexpected model call ' . $method);
        }
    };
    $c->sensitiveactionstepup = new class {
        public array $issueCalls = [], $consumeCalls = [];
        public bool $allowConsume = true;
        public function issue($userId, $action, $targetId, $password): array {
            $this->issueCalls[] = [$userId, $action, $targetId, $password];
            if ($password !== 'period-password') return ['ok' => false, 'status' => 403, 'message' => 'Verifikasi ulang tidak berhasil.'];
            return ['ok' => true, 'proof' => str_repeat('c', 64)];
        }
        public function consume($userId, $action, $targetId, $proof): array {
            $this->consumeCalls[] = [$userId, $action, $targetId, $proof];
            return $this->allowConsume && $userId === 7 && $action === 'PERIOD_REOPEN' && $targetId === 12 && $proof === str_repeat('c', 64)
                ? ['ok' => true]
                : ['ok' => false, 'status' => 428, 'message' => 'Verifikasi ulang diperlukan.'];
        }
    };
    $c->load = new class { public array $libraries = []; public function view($view, $data = []): void {} public function library($name, $params = null, $objectName = null): void { $this->libraries[] = [$name, $objectName]; } };
    return $c;
}
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$invoke = static function (Finance_reports $c, string $method): array {
    try { $c->$method(12); return [$c->output->status, $c->output->body]; }
    catch (PeriodCloseResponse $r) { return [$r->getCode(), $r->getMessage()]; }
};
$key = 'finance_period_close_csrf';
$token = str_repeat('a', 64);
$writers = ['period_close_store' => ['save_period_close', 'create'], 'period_close_process' => ['close_period', 'edit'], 'period_close_reopen' => ['reopen_period', 'edit']];
foreach ($writers as $method => [$writer, $permission]) {
    foreach ([
        ['GET', $token, $token, 405], ['PUT', $token, $token, 405], ['DELETE', $token, $token, 405],
        ['POST', null, $token, 403], ['POST', str_repeat('b', 64), $token, 403],
        ['POST', 'short', $token, 403], ['POST', [$token], $token, 403],
        ['POST', 123, $token, 403], ['POST', $token . "\n", $token, 403],
        ['POST', $token, null, 403], ['POST', $token, [$token], 403],
        ['POST', $token, 'short', 403], ['POST', $token, $token, 303],
    ] as [$verb, $provided, $expected, $status]) {
        foreach ([false, true] as $ajax) {
            $c = periodCloseFixture($expected);
            $c->input->verb = $verb;
            $c->input->ajax = $ajax;
            $c->input->values = [$key => $provided, 'notes' => 'Fixture note'];
            if ($method === 'period_close_reopen') $c->input->values['step_up_password'] = 'period-password';
            [$actual, $body] = $invoke($c, $method);
            $check($actual === $status, "$method $verb status $status ajax=" . (int)$ajax);
            $check($c->permissions === [['finance.period_close.index', $permission]], 'existing permission is required');
            $check($c->session->writes === [], 'mutations never mint tokens');
            $check(in_array('Cache-Control: private, no-store', $c->output->headers, true), 'mutation response no-store');
            if ($status !== 303) {
                $check($c->Finance_report_model->calls === [] && $c->session->flash === [], 'invalid request never reaches model or success flash');
                $check(!in_array(null, $c->input->reads, true), 'invalid request never reads business payload');
                if ($ajax) $check(json_decode($body, true)['ok'] === false && $c->output->type === 'application/json', 'AJAX failure is JSON');
                if ($status === 405) $check(in_array('Allow: POST', $c->output->headers, true), 'method guard advertises POST');
            } else {
                $args = $writer === 'save_period_close' ? [['notes' => 'Fixture note'], 7] : [12, 7];
                $check($c->Finance_report_model->calls === [[$writer, $args]], 'one correct writer with session actor and no CSRF or password in business data');
                $check($c->session->flash === ['success' => 'Fixture result'], 'success message preserved');
                if ($method === 'period_close_reopen') {
                    $check($c->sensitiveactionstepup->issueCalls === [[7, 'PERIOD_REOPEN', 12, 'period-password']]
                        && $c->sensitiveactionstepup->consumeCalls === [[7, 'PERIOD_REOPEN', 12, str_repeat('c', 64)]], 'reopen performs scoped issue and one-use consume before its writer');
                }
            }
        }
    }
    // A valid token never upgrades view-only access, including direct-controller URLs.
    $c = periodCloseFixture($token);
    $c->allowed = ['view'];
    $c->input->values = [$key => $token];
    $check($invoke($c, $method)[0] === 403 && $c->input->reads === [] && $c->Finance_report_model->calls === [], 'RBAC denies before token/payload/model');
    $c = periodCloseFixture($token);
    $c->input->values = [$key => $token];
    if ($method === 'period_close_reopen') $c->input->values['step_up_password'] = 'period-password';
    $c->Finance_report_model->ok = false;
    $check($invoke($c, $method)[0] === 303 && $c->session->flash === ['error' => 'Fixture result'], 'business failure stays error with local redirect');
}
$c = periodCloseFixture($token);
$c->input->values = [$key => $token];
$check($invoke($c, 'period_close_reopen')[0] === 303 && $c->Finance_report_model->calls === [] && $c->session->flash === ['error' => 'Verifikasi ulang tidak berhasil.'], 'reopen without password never reaches its writer');
$c = periodCloseFixture($token);
$c->input->values = [$key => $token, 'step_up_password' => 'period-password'];
$c->sensitiveactionstepup->allowConsume = false;
$check($invoke($c, 'period_close_reopen')[0] === 303 && $c->Finance_report_model->calls === [] && $c->session->flash === ['error' => 'Verifikasi ulang diperlukan.'], 'reopen consume failure never reaches its writer');
foreach (['https://outside.example/path', '//outside.example', "https://outside.example\r\nX-Test: yes", ['nested'], 'finance-reports/period-close/detail/99'] as $url) {
    $c = periodCloseFixture($token);
    $c->input->values = [$key => $token, 'redirect_to' => $url, 'actor_user_id' => 999];
    $check($invoke($c, 'period_close_process') === [303, 'finance-reports/period-close/detail/12'], 'posted redirect is ignored');
    $check($c->Finance_report_model->calls === [['close_period', [12, 7]]], 'caller cannot override actor or document');
}
foreach (['period_close', 'period_close_detail'] as $page) {
    $c = periodCloseFixture(null);
    $c->allowed = [];
    $check($invoke($c, $page)[0] === 403 && $c->session->writes === [] && $c->Finance_report_model->calls === [], 'page denies before token and data');
    foreach ([null, ['malformed'], 'short', $token] as $existing) {
        $c = periodCloseFixture($existing);
        $c->$page(12);
        $first = $c->rendered[1]['period_close_csrf'];
        $check($first['name'] === $key && preg_match('/\A[0-9a-f]{64}\z/D', $first['value']) === 1, 'page issues valid dedicated token');
        $c->$page(12);
        $check($c->rendered[1]['period_close_csrf'] === $first, 'page token stable for multiple tabs');
        $check(count($c->session->writes) === ($existing === $token ? 0 : 1), 'only malformed/missing token is replaced');
        $check($c->session->values['pos_transaction_csrf'] === 'unchanged', 'POS token untouched');
        $check(in_array('Cache-Control: private, no-store', $c->output->headers, true), 'page no-store');
    }
}
$c = periodCloseFixture(null);
$c->Finance_report_model->found = false;
$check($invoke($c, 'period_close_detail')[0] === 404 && $c->session->writes === [] && $c->rendered === [], 'missing period gets no form');

// Render the actual templates and submit their hidden fields to the real controller.
$render = static function (Finance_reports $c, string $view, array $data): string {
    return (function () use ($view, $data): string {
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/application/views/' . $view . '.php';
        return (string)ob_get_clean();
    })->call($c);
};
$formCount = 0;
foreach (['period_close', 'period_close_detail'] as $page) {
    foreach (['OPEN', 'REOPENED', 'CLOSED', 'VOID'] as $status) {
        foreach ([[], ['create'], ['edit'], ['create', 'edit']] as $permissions) {
            $c = periodCloseFixture($token);
            $c->allowed = array_merge(['view'], $permissions);
            $c->$page(12);
            [$view, $data] = $c->rendered;
            if ($page === 'period_close') $data['rows'][0]['status'] = $status;
            else $data['row']['status'] = $status;
            $html = $render($c, $view, $data);
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($dom);
            $forms = $xpath->query('//form[@method="post"]');
            $expected = ($page === 'period_close' && in_array('create', $permissions, true) ? 1 : 0)
                + (in_array('edit', $permissions, true) && (in_array($status, ['OPEN', 'REOPENED'], true) || ($page === 'period_close_detail' && $status === 'CLOSED')) ? 1 : 0);
            $check($forms->length === $expected, 'forms follow existing create/edit permissions and period state');
            foreach ($forms as $form) {
                $fields = $xpath->query('.//input[@name="' . $key . '"]', $form);
                $check($fields->length === 1 && $fields->item(0)->getAttribute('value') === $token, 'each actual form has exactly one matching token');
                $check($xpath->query('.//input[@name="redirect_to"]', $form)->length === 0, 'no posted return URL');
                $action = $form->getAttribute('action');
                $check(preg_match('~^https://finance\.example\.test/finance-reports/period-close/(store|process/12|reopen/12)$~D', $action) === 1, 'form submits to fixed local route');
                $method = str_ends_with($action, '/store') ? 'period_close_store' : (str_contains($action, '/process/') ? 'period_close_process' : 'period_close_reopen');
                $stepUpFields = $xpath->query('.//input[@name="step_up_password"]', $form);
                $check(
                    $method !== 'period_close_reopen'
                        ? $stepUpFields->length === 0
                        : $stepUpFields->length === 1
                            && $stepUpFields->item(0)->getAttribute('type') === 'password'
                            && $stepUpFields->item(0)->getAttribute('autocomplete') === 'current-password'
                            && $stepUpFields->item(0)->hasAttribute('required'),
                    'only reopen form has the required masked reauthentication field'
                );
                $submit = periodCloseFixture($token);
                $submit->allowed = $c->allowed;
                $submit->input->values = [$key => $fields->item(0)->getAttribute('value')];
                if ($method === 'period_close_reopen') $submit->input->values['step_up_password'] = 'period-password';
                $check($invoke($submit, $method)[0] === 303, 'rendered form token accepted by its writer');
                $formCount++;
            }
        }
    }
}
echo 'PASS finance-period-close-csrf checks=' . $checks . ' rendered_forms=' . $formCount . PHP_EOL;
