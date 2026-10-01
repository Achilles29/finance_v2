<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
final class ReviewAdminResponse extends RuntimeException {}
class MY_Controller
{
    public $input, $session, $output, $db, $load, $Pos_customer_review_model, $Pos_print_model;
    public array $current_user = ['id' => 7];
    public bool $authorized = true;
    public array $permissions = [], $rendered = [];
    public function require_permission($page, $action = 'view'): void {
        $this->permissions[] = [$page, $action];
        if (!$this->authorized) { $this->output->status = 403; throw new ReviewAdminResponse(); }
    }
    public function can($page, $action = 'view'): bool { return true; }
    public function render($view, $data): void { $this->rendered = $data; }
}
require dirname(__DIR__, 2) . '/application/controllers/Pos.php';
final class ReviewAdminInput
{
    public string $verb = 'POST';
    public array $headers = [];
    public int $payloadReads = 0;
    public function method($upper = false): string { return $upper ? $this->verb : strtolower($this->verb); }
    public function get_request_header($key, $clean = false) { return $this->headers[$key] ?? null; }
    public function __get($key) {
        if ($key !== 'raw_input_stream') throw new RuntimeException('Unexpected input access.');
        $this->payloadReads++;
        return '{"hidden":1,"reason":"Fixture reason","station_name":"Fixture area","customer_review_qr_enabled":1}';
    }
}
function reviewAdminFixture($expected): Pos
{
    $controller = (new ReflectionClass(Pos::class))->newInstanceWithoutConstructor();
    $controller->input = new ReviewAdminInput();
    $controller->output = new class {
        public int $status = 200;
        public array $headers = [];
        public string $body = '';
        public function set_header($value): self { $this->headers[] = $value; return $this; }
        public function set_status_header($value): self { $this->status = $value; return $this; }
        public function set_content_type($value): self { return $this; }
        public function set_output($value): self { $this->body = $value; return $this; }
        public function _display(): void { throw new ReviewAdminResponse(); }
    };
    $controller->session = new class($expected) {
        public array $values, $writes = [];
        public function __construct($expected) { $this->values = ['pos_customer_review_csrf' => $expected, 'pos_transaction_csrf' => 'unchanged fixture']; }
        public function userdata($key) { return $this->values[$key] ?? null; }
        public function set_userdata($key, $value): void { $this->values[$key] = $value; $this->writes[] = $key; }
    };
    $controller->db = new class {
        public bool $registered = true;
        public function table_exists($table): bool { return true; }
        public function from($table): self { return $this; }
        public function where($field, $value): self { return $this; }
        public function count_all_results(): int { return $this->registered ? 1 : 0; }
    };
    $model = new class {
        public array $calls = [];
        public function __call($method, $arguments) {
            if (in_array($method, ['options', 'general_settings', 'station_rows'], true)) return [];
            if ($method === 'station_ready') return true;
            $this->calls[] = [$method, $arguments];
            return ['ok' => true, 'id' => 12, 'review_status' => 'HIDDEN', 'is_active' => 0];
        }
    };
    $controller->Pos_print_model = $model;
    $controller->Pos_customer_review_model = $model;
    $controller->load = new class { public function view($view, $data = []): void {} };
    return $controller;
}
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
    echo 'PASS: ' . $message . PHP_EOL;
};
$token = str_repeat('a', 64);
$header = 'X-Pos-Review-Csrf';
$writers = ['customer_review_visibility' => 'set_visibility', 'customer_review_settings' => 'save_customer_review_settings', 'customer_review_station_save' => 'save_station', 'customer_review_station_toggle' => 'toggle_station'];
foreach ($writers as $action => $writer) {
    foreach ([
        ['GET', $token, $token, 405, true],
        ['PUT', $token, $token, 405, true],
        ['POST', null, $token, 403, true],
        ['POST', str_repeat('b', 64), $token, 403, true],
        ['POST', 'short', $token, 403, true],
        ['POST', [$token], $token, 403, true],
        ['POST', $token, null, 403, true],
        ['POST', $token, [$token], 403, true],
        ['POST', $token, $token, 403, false],
        ['POST', $token, $token, 200, true],
    ] as [$method, $provided, $expected, $status, $authorized]) {
        $controller = reviewAdminFixture($expected);
        $controller->authorized = $authorized;
        $controller->input->verb = $method;
        $controller->input->headers = [$header => $provided];
        try { $controller->$action(12); } catch (ReviewAdminResponse $response) {}
        $calls = $controller->Pos_customer_review_model->calls;
        $check($controller->output->status === $status, $action . ' returns expected HTTP ' . $status);
        $check($controller->permissions === [['pos.customer_review.index', 'edit']], $action . ' retains edit permission check');
        if ($status === 200) {
            $check(count($calls) === 1 && $calls[0][0] === $writer, $action . ' authorized POST reaches exactly one correct writer');
            if ($action === 'customer_review_visibility') {
                $check($calls[0][1] === [12, true, 7, 'Fixture reason'], 'visibility target, actor and reason preserved');
            }
        } else {
            $check($calls === [] && $controller->input->payloadReads === 0, $action . ' rejected before payload and writer');
        }
        if ($status === 405) $check(in_array('Allow: POST', $controller->output->headers, true), '405 declares Allow POST');
        $check($controller->session->writes === [], 'mutations never mint or replace a missing CSRF token');
    }
    $controller = reviewAdminFixture($token);
    $controller->input->headers = ['X-Pos-Transaction-Csrf' => $token];
    $_POST = ['pos_customer_review_csrf' => $token];
    try { $controller->$action(12); } catch (ReviewAdminResponse $response) {}
    $check($controller->output->status === 403 && $controller->input->payloadReads === 0, $action . ' ignores transaction header and body-only token');
    $_POST = [];
}
$controller = reviewAdminFixture(null);
$controller->customer_reviews();
$issued = $controller->rendered['customer_review_csrf_token'];
$controller->customer_reviews();
$check(preg_match('/^[a-f0-9]{64}$/D', $issued) === 1 && $controller->rendered['customer_review_csrf_token'] === $issued && count($controller->session->writes) === 1, 'page creates random stable per-session token; multiple tabs remain valid');
$check($controller->session->values['pos_transaction_csrf'] === 'unchanged fixture' && $controller->rendered['customer_review_csrf_header'] === $header, 'review token/header remain separate from POS transaction token');
$check(in_array('Cache-Control: private, no-store', $controller->output->headers, true), 'token-bearing page is not cached');
$denied = reviewAdminFixture(null); $denied->authorized = false;
try { $denied->customer_reviews(); } catch (ReviewAdminResponse $response) {}
$check($denied->session->writes === [] && $denied->rendered === [], 'unauthorized viewer receives no token/page');
$legacy = reviewAdminFixture($token); $legacy->db->registered = false;
$legacy->input->headers = [$header => $token];
try { $legacy->customer_review_station_toggle(12); } catch (ReviewAdminResponse $response) {}
$check($legacy->permissions === [['pos.printer.index', 'edit']] && $legacy->output->status === 200, 'legacy permission fallback unchanged');

// Execute the actual rendered UI script against a DOM/fetch double, not string-only assertions.
function site_url($path): string { return 'https://finance.example/' . ltrim($path, '/'); }
function base_url($path): string { return site_url($path); }
function html_escape($value, $doubleEncode = true): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8', $doubleEncode); }
$renderer = function (array $data): string {
    extract($data); ob_start();
    include dirname(__DIR__, 2) . '/application/views/pos/customer_reviews_index.php';
    return (string)ob_get_clean();
};
$html = $renderer->call($controller, $controller->rendered);
preg_match_all('/<script>(.*?)<\/script>/s', $html, $matches);
$script = end($matches[1]);
if (!is_string($script)) throw new RuntimeException('Rendered review script unavailable.');
$javascript = <<<'JS'
const vm = require('node:vm');
const assert = require('node:assert/strict');
let input = ''; process.stdin.on('data', chunk => input += chunk); process.stdin.on('end', async () => {
  try {
    const fixture = JSON.parse(input); const nodes = new Map(); const requests = []; const notices = []; const confirmations = [];
    function node(id) {
      if (!nodes.has(id)) nodes.set(id, {value: '', checked: true, dataset: {}, listeners: {}, classList: {add(){},remove(){}},
        addEventListener(event, fn){this.listeners[event] = fn;}, querySelector(){return node(id + '-submit');}, reportValidity(){return true;}, setAttribute(){}, reset(){}, focus(){}});
      return nodes.get(id);
    }
    const toggle = node('toggle'); toggle.dataset.stationToggle = '12';
    const document = {getElementById: node, querySelectorAll: s => s === '[data-station-toggle]' ? [toggle] : [], addEventListener: (event, fn) => fn()};
    const ui = {get: async () => ({rows: [], meta: {}}), post: () => {throw Error('Shared unguarded post must not be used');},
      pager(){}, notice: (...args) => notices.push(args), escapeHtml: x => String(x), formObject: () => ({station_name: 'Fixture area'}), icon: () => ''};
    let mode = 'ok';
    const window = {PrinterConfigUI: ui, location: {href: 'https://finance.example/pos/customer-reviews', origin: 'https://finance.example', reload(){}}, setTimeout(){}, confirm: text => {confirmations.push(text); return true;}};
    const fetch = async (url, options) => {requests.push({url, options}); return {ok: mode === 'ok', text: async () => mode === 'html' ? '<html>Login</html>' : JSON.stringify(mode === 'ok' ? {ok:true} : {ok:false,message:'Sesi formulir ulasan tidak valid.'})};};
    const context = {window, document, fetch, URL, URLSearchParams, console};
    const source = fixture.script.replace("document.addEventListener('DOMContentLoaded', function () {", "document.addEventListener('DOMContentLoaded', function () { window.reviewTestPost = postReview;");
    vm.runInNewContext(source, context); await Promise.resolve();
    const body = node('review-body');
    for (const hidden of ['1', '0']) {
      const button = {dataset: {hidden, visibility: '12'}};
      await body.listeners.click({target: {closest: () => button}});
      assert.equal(JSON.parse(requests.at(-1).options.body).hidden, Number(hidden));
      assert.ok(confirmations.at(-1).includes(hidden === '1' ? 'menyembunyikan' : 'menampilkan kembali'));
    }
    await node('receipt-review-settings').listeners.submit({preventDefault(){}});
    await node('review-station-form').listeners.submit({preventDefault(){}});
    await toggle.listeners.click();
    assert.equal(requests.length, 5);
    assert.deepEqual(requests.map(r => new URL(r.url).pathname), ['/pos/customer-reviews/visibility/12','/pos/customer-reviews/visibility/12','/pos/customer-reviews/settings','/pos/customer-reviews/stations/save','/pos/customer-reviews/stations/toggle/12']);
    for (const request of requests) {
      assert.equal(request.options.headers['X-Pos-Review-Csrf'], fixture.token);
      assert.equal(request.options.method, 'POST'); assert.equal(request.options.credentials, 'same-origin');
      assert.equal(request.options.mode, 'same-origin'); assert.equal(request.options.redirect, 'error');
      assert.ok(!request.url.includes(fixture.token));
    }
    mode = 'denied'; await assert.rejects(window.reviewTestPost('/settings', {}), /Sesi formulir/);
    mode = 'html'; await assert.rejects(window.reviewTestPost('/settings', {}), /Jawaban server/);
    const before = requests.length; window.location.origin = 'https://other.example';
    await assert.rejects(window.reviewTestPost('/settings', {}), /Alamat aplikasi/); assert.equal(requests.length, before);
    window.location.origin = 'https://finance.example';
    vm.runInNewContext(source.replace('const canEdit = true', 'const canEdit = false'), context);
    await assert.rejects(window.reviewTestPost('/settings', {}), /Sesi formulir/); assert.equal(requests.length, before);
    console.log('PASS actual review UI: all four writers, hide/show confirmation, scoped headers, same-origin, errors, readonly viewer');
  } catch (error) { console.error(error.message); process.exitCode = 1; }
});
JS;
$process = proc_open(['node', '-e', $javascript], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Node fixture unavailable.');
fwrite($pipes[0], json_encode(['script' => $script, 'token' => $issued], JSON_THROW_ON_ERROR)); fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
$exit = proc_close($process);
echo $stdout;
$check($exit === 0, 'rendered UI execution passed' . ($stderr !== '' ? ': ' . trim($stderr) : ''));
echo 'REVIEW_ADMIN_CSRF PASS checks=' . $checks . PHP_EOL;
