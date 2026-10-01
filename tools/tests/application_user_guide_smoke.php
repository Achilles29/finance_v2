<?php
declare(strict_types=1);
// No CI bootstrap, server connection, session writes, or live transactions.
$root = dirname(__DIR__, 2);
define('BASEPATH', $root.'/system/');
define('FCPATH', $root.'/');
define('APPPATH', $root.'/application/');
require $root.'/application/libraries/Finance_user_guide.php';
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $checks++;
}
function site_url(string $path = ''): string { return '/'.$path; }
function base_url(string $path = ''): string { return '/'.$path; }
function show_error($message, $status = 500): void { throw new RuntimeException((string)$message, $status); }
function show_404(): void { throw new RuntimeException('Not found', 404); }
class GuideInputDouble {
    public array $query = [];
    public string $verb = 'GET';
    public function get($key, $filter = false) { return $this->query[$key] ?? null; }
    public function method($upper = false): string { return $upper ? $this->verb : strtolower($this->verb); }
}
class GuideOutputDouble {
    public array $headers = [];
    public function set_header($header): self { $this->headers[] = $header; return $this; }
}
class GuideLoaderDouble {
    private $controller;
    public function __construct($controller) { $this->controller = $controller; }
    public function library($name, $args, $alias): void {
        check($name === 'Finance_user_guide' && $alias === 'guide', 'only curated library loaded');
        $this->controller->guide = new Finance_user_guide();
    }
}
class MY_Controller {
    public $input, $output, $load, $guide;
    public array $allowed = ['system.guide.index'];
    public array $rendered = [];
    public function __construct() {
        $this->input = new GuideInputDouble(); $this->output = new GuideOutputDouble();
        $this->load = new GuideLoaderDouble($this);
    }
    protected function can($page, $action = 'view'): bool { return in_array($page, $this->allowed, true); }
    protected function require_permission($page, $action = 'view'): void { if (!$this->can($page, $action)) show_error('Denied', 403); }
    protected function render($view, $data = []): void {
        check($view === 'system/user_guide', 'fixed template'); $this->rendered = $data;
    }
}
require $root.'/application/controllers/User_guide.php';
$request = static function (array $query = [], array $allowed = ['system.guide.index'], string $verb = 'GET'): User_guide {
    $c = new User_guide(); $c->input->query = $query; $c->input->verb = $verb; $c->allowed = $allowed; $c->index(); return $c;
};
$reject = static function (array $query, int $status, array $allowed = ['system.guide.index'], string $verb = 'GET') use ($request): void {
    try { $request($query, $allowed, $verb); } catch (RuntimeException $e) { check($e->getCode() === $status, 'expected HTTP '.$status); return; }
    throw new RuntimeException('Request should fail '.$status);
};
$render = static function (array $data) use ($root): string {
    extract($data); ob_start(); require $root.'/application/views/system/user_guide.php'; return (string)ob_get_clean();
};
$guide = new Finance_user_guide(); $all = $guide->articles(true); $staff = $guide->articles(false);
check(count($all) === 26 && count($staff) === 22, '26 chapters; four server-only');
check(!isset($guide->categories(false)['server'], $guide->audiences(false)['server']), 'server filters absent for staff');
check(count($guide->categories(true)) === 6, 'six topics');
foreach ($all as $id=>$a) {
    check($a['id'] === $id && (bool)preg_match('/\A[a-z0-9-]+\z/D', $id), 'stable article identifier');
    check(isset($guide->categories(true)[$a['category']]), 'known category '.$id);
    check(count($a['steps']) >= 4 && $a['check'] !== '' && $a['warning'] !== '', 'actionable steps/check/warning '.$id);
    check($a['audiences'] && !array_diff($a['audiences'], array_keys($guide->audiences(true))), 'known audiences '.$id);
    check(!$a['commands'] || $a['category'] === 'server', 'commands restricted to server '.$id);
    foreach ($a['links'] as $link) {
        check((bool)preg_match('/\A[a-z0-9][a-z0-9\/\-?=]*\z/D', $link['path']), 'local curated links');
        check($link['permission'] !== '', 'module links have permission');
    }
    // Real controller selection + real view, not a copied implementation.
    $c = $request(['article'=>$id], ['system.guide.index','system.guide.server']);
    $html = $render($c->rendered);
    check(strpos($html, htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8')) !== false, 'renders '.$id);
    check(strpos($html, 'ug-article-title') !== false && strpos($html, 'Bab berikutnya') !== false || $id === 'server-backup-update', 'article navigation');
}
check(isset($guide->search($staff, '', '', 'ph')['attendance']), 'PH search case-insensitive');
check(isset($guide->search($staff, 'finance', 'finance', 'mutasi kas')['cash-mutations']), 'multiword + category + audience');
check($guide->search($staff, '', '', 'finance_license') === [], 'no restricted search leakage');
$reject([], 403, []);
$reject([], 405, ['system.guide.index'], 'POST');
$reject([], 405, ['system.guide.index'], 'HEAD');
foreach ([['q'=>[]], ['article'=>[]], ['category'=>[]], ['audience'=>[]], ['q'=>str_repeat('a',161)], ['q'=>"\xff"], ['q'=>"a\nb"]] as $query) $reject($query,400);
foreach ([['article'=>'../../docs/_NOTE.md'], ['article'=>'missing'], ['category'=>'server'], ['audience'=>'server'], ['category'=>'other'], ['article'=>'cashier','category'=>'finance']] as $query) $reject($query,404);
foreach (['server-install','server-config','server-cron','server-backup-update'] as $id) {
    $reject(['article'=>$id],404); $reject(['article'=>$id],403,['system.guide.server']);
}
$c = $request(); $html = $render($c->rendered);
check(in_array('Cache-Control: private, no-store', $c->output->headers, true), 'private uncached response');
check(!str_contains($html, 'server-cron') && !str_contains($html, 'deployment.json') && !str_contains($html, '/etc/cron.d'), 'staff receives no server bodies/navigation/commands');
check(str_contains($html, 'method="get"') && str_contains($html, 'ug-mobile-chapters'), 'GET and mobile select without JS');
$c = $request(['article'=>'identity']); $html = $render($c->rendered);
check(!str_contains($html,'href="/system/business-profile"') && str_contains($html,'minta akses pengelola'), 'related links withheld');
$c = $request(['article'=>'identity'], ['system.guide.index','system.business_profile']);
check(str_contains($render($c->rendered),'href="/system/business-profile"'), 'related link allowed');
$c = $request(['q'=>'<img src=x onerror=alert(1)>']); $html = $render($c->rendered);
check(!str_contains($html,'<img src=x') && str_contains($html,'&lt;img'), 'search safely escaped');
check(str_contains($html,'Belum ada bab yang cocok') && !str_contains($html,'ug-article-title'), 'empty result graceful');
$c = $request(['article'=>'server-config'], ['system.guide.index','system.guide.server']); $html = $render($c->rendered);
check(str_contains($html,'&quot;FINANCE_DB_HOST&quot;') && str_contains($html,'Salin contoh'), 'escaped example code + copy');
check($guide->releaseLabel('/nonexistent/guide-test.json') === 'Versi belum tersedia', 'missing manifest fallback');
$tmp = tempnam(sys_get_temp_dir(),'finance-guide-manifest-');
try {
    file_put_contents($tmp, '{"version":"<script>alert(1)</script>","secret":"DO_NOT_RENDER"}');
    check($guide->releaseLabel($tmp) === 'Versi belum tersedia', 'reject malicious version');
    file_put_contents($tmp, '{"version":"0.1.0-test","secret":"DO_NOT_RENDER"}');
    check($guide->releaseLabel($tmp) === '0.1.0-test', 'only version allowlisted');
} finally { unlink($tmp); }

// Exercise prepared metadata SQL twice in an isolated memory fixture, never MariaDB.
$sql = file_get_contents($root.'/sql/2026-09-15c_application_user_guide.sql');
$db = new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE sys_page (id INTEGER PRIMARY KEY, page_code TEXT UNIQUE, page_name TEXT, module TEXT, matrix_group TEXT, description TEXT, is_active INTEGER);
CREATE TABLE sys_menu (id INTEGER PRIMARY KEY,parent_id INTEGER,menu_code TEXT UNIQUE,menu_label TEXT,icon TEXT,url TEXT,page_id INTEGER,sort_order INTEGER,is_active INTEGER,sidebar_type TEXT);
CREATE TABLE auth_role (id INTEGER PRIMARY KEY,role_code TEXT);
CREATE TABLE auth_role_permission (role_id INTEGER,page_id INTEGER,can_view INTEGER,can_create INTEGER,can_edit INTEGER,can_delete INTEGER,can_export INTEGER,UNIQUE(role_id,page_id));
INSERT INTO sys_menu(id,menu_code) VALUES(1,"grp.system");
INSERT INTO auth_role(id,role_code) VALUES(1,"SUPERADMIN"),(2,"STAFF");');
$fixtureSql = str_replace('START TRANSACTION;', 'BEGIN TRANSACTION;', $sql);
$db->exec($fixtureSql); $db->exec($fixtureSql);
check((int)$db->query('SELECT COUNT(*) FROM sys_page')->fetchColumn() === 2, 'two pages; repeat-safe');
check((int)$db->query('SELECT COUNT(*) FROM sys_menu')->fetchColumn() === 2, 'one new menu; repeat-safe');
check((int)$db->query('SELECT parent_id FROM sys_menu WHERE menu_code="system.guide"')->fetchColumn() === 1, 'under System');
check((int)$db->query('SELECT COUNT(*) FROM auth_role_permission WHERE role_id=1 AND can_view=1')->fetchColumn() === 2, 'superadmin seeded view');
check((int)$db->query('SELECT COUNT(*) FROM auth_role_permission WHERE role_id=2 OR can_create=1 OR can_edit=1 OR can_delete=1 OR can_export=1')->fetchColumn() === 0, 'no staff auto-grant/mutation grants');
$db->exec('UPDATE auth_role_permission SET can_view=0 WHERE role_id=1'); $db->exec($fixtureSql);
check((int)$db->query('SELECT COUNT(*) FROM auth_role_permission WHERE can_view=1')->fetchColumn() === 0, 'existing grants preserved');
$routes = file_get_contents($root.'/application/config/routes.php');
check(str_contains($routes, "\$route['guide'] = 'user_guide/index';"), 'route registered');
$cron = implode(' ', $all['server-cron']['steps']);
check(str_contains($cron, 'runtime_jobs_run') && str_contains($cron,'run_due') && str_contains($cron,'api_schedule_run'), 'three runtime jobs documented');
check(str_contains($cron,'Bukan') || str_contains($cron,'BUKAN'), 'not live scheduler status');
check(str_contains($cron,'path staging') && str_contains($cron,'bukan cron operasional rutin'), 'legacy and repair exclusions');
// Drift checks: guide commands and baseline must continue matching shipped code.
$runtime = json_decode(file_get_contents($root.'/tools/release/runtime_compatibility.json'), true);
check($runtime['runtimes']['php']['minimum'] === '8.1.0' && $runtime['runtimes']['mariadb']['minimum'] === '10.11.0', 'runtime baseline drift needs guide review');
foreach (['Pos.php'=>'runtime_jobs_run', 'Telegram.php'=>'run_due', 'Whatsapp.php'=>'api_schedule_run'] as $file=>$method) {
    check(str_contains(file_get_contents($root.'/application/controllers/'.$file), 'function '.$method.'('), 'documented scheduler entry exists');
}
foreach ($all as $a) foreach ($a['links'] as $link) {
    $path = explode('?', $link['path'], 2)[0];
    check(str_contains($routes, "\$route['".$path."']") || $path === 'telegram/guide', 'canonical or verified default CI route '.$path);
}
define('FINANCE_QUALITY_GATE_LIBRARY_ONLY', true);
require __DIR__.'/finance_quality_gate.php';
$manifest = finance_quality_gate_manifest();
$client = array_values(array_filter($manifest['required'], static fn(array $test): bool => $test['id'] === 'application-user-guide-client'))[0];
$resolved = finance_quality_gate_resolve_command($client, __DIR__.'/'.$client['file']);
check($resolved['ok'] && $resolved['command'][0] === '/usr/bin/node', 'client gate resolves executable absolute Node runtime');
define('FINANCE_PUBLIC_ROOT',__DIR__);
$customer=$guide->articles(true);
check(count($customer)===26,'customer delivery guide preserves chapter map');
check(str_contains(implode(' ',$customer['server-install']['steps']),'/setup') && str_contains($customer['server-install']['commands'][0]['code'],'prepare.sh'),'customer guide uses one preparation command and setup UI');
check(str_contains(implode(' ',$customer['server-config']['steps']),'config/customer.example.json'),'customer guide points to packaged local configuration template');
check(str_contains(implode(' ',$customer['server-backup-update']['steps']),'belum pergantian kode aktif'),'customer guide does not advertise an unfinished updater');
echo "Application user guide: {$checks} PASS (no live DB/server requests).\n";
