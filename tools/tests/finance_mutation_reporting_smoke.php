<?php
declare(strict_types=1);

// Default: real CI query builder on in-memory SQLite, never application config.
// --mysql-fixture: fresh socket-only MariaDB with synthetic data and baseline DDL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
define('APPPATH', $root . '/application/');
define('ENVIRONMENT', 'testing');
date_default_timezone_set('Asia/Jakarta');
function log_message($level, $message): void { /* Expected negative cases are checked by return value. */ }
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message = '', $status = 500): void { throw new RuntimeException('CI error: ' . (is_array($message) ? implode(' ', $message) : $message)); }
function site_url($uri = ''): string { return '/' . ltrim($uri, '/'); }
function base_url($uri = ''): string { return site_url($uri); }
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$context = new class {};
function &get_instance() { return $GLOBALS['context']; }
require BASEPATH . 'core/Model.php';
require BASEPATH . 'database/DB.php';
foreach (['Finance_report_model', 'Purchase_model', 'Finance_revenue_reconciliation_model', 'Finance_cash_reconciliation_model'] as $class) {
    require APPPATH . 'models/' . $class . '.php';
    $context->$class = new $class();
}
$context->load = new class {
    public function view($name, $data = []): void { /* Layout tab includes are unrelated to fixtures. */ }
    public function model($name): void {
        if (!in_array($name, ['Procurement_model','Payroll_preview_model'], true)) throw new RuntimeException('Unexpected fixture model.');
        get_instance()->$name = new stdClass(); // No procurement/payroll data in this fixture.
    }
};
$context->security = new class {
    public function get_csrf_token_name(): string { return 'fixture_csrf'; }
    public function get_csrf_hash(): string { return str_repeat('f', 32); }
};
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    if (!array_filter($GLOBALS['argv'], static fn($v) => str_starts_with($v, '--render='))) echo 'PASS: ' . $label . PHP_EOL;
};
$mysql = in_array('--mysql-fixture', $argv, true);
if ($mysql) {
    $scratch = trim((string)shell_exec('mktemp -d /tmp/finance-mutation-test-XXXXXX'));
    if (!preg_match('~\A/tmp/finance-mutation-test-[A-Za-z0-9]+\z~D', $scratch) || !is_dir($scratch)) throw new RuntimeException('Private scratch unavailable.');
    $base = '/www/server/mysql';
    $run = static function (array $command) use ($scratch): void {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $scratch . '/init.log', 'a'], 2 => ['file', $scratch . '/init.log', 'a']], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Fixture initialization failed; inspect ' . $scratch . '/init.log');
    };
    $run([$base . '/scripts/mariadb-install-db', '--no-defaults', '--basedir=' . $base, '--datadir=' . $scratch . '/data', '--auth-root-authentication-method=normal', '--skip-test-db']);
    $socket = $scratch . '/db.sock';
    $server = proc_open([$base . '/bin/mariadbd', '--no-defaults', '--user=' . posix_getpwuid(posix_geteuid())['name'], '--basedir=' . $base, '--datadir=' . $scratch . '/data', '--socket=' . $socket, '--pid-file=' . $scratch . '/db.pid', '--log-error=' . $scratch . '/db.log', '--skip-networking', '--skip-log-bin', '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=16M'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', $scratch . '/db.log', 'a']], $pipes);
    if (!is_resource($server)) throw new RuntimeException('Fixture server unavailable.');
    register_shutdown_function(static function () use ($server, $scratch): void { proc_terminate($server); proc_close($server); echo 'Isolated fixture stopped; diagnostics: ' . $scratch . PHP_EOL; });
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = null;
    for ($i = 0; $i < 100; $i++) {
        $candidate = @new mysqli('localhost', 'root', '', '', 0, $socket);
        if (!$candidate->connect_errno) { $connection = $candidate; break; }
        usleep(100000);
    }
    if (!$connection || !$connection->query('CREATE DATABASE fixture_finance')) throw new RuntimeException('Fixture socket not ready.');
    $connection->close();
    $context->db = DB(['dbdriver' => 'mysqli', 'hostname' => $socket, 'username' => 'root', 'password' => '', 'database' => 'fixture_finance', 'db_debug' => false, 'save_queries' => true, 'pconnect' => false, 'char_set' => 'utf8mb4'], true);
    $db = $context->db;
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $ddl = file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
    foreach (['auth_user', 'fin_company_account', 'fin_account_mutation_log', 'fin_period_close', 'aud_transaction_log', 'fin_cash_reconciliation', 'fin_cash_reconciliation_line', 'fin_revenue_reconciliation', 'fin_revenue_reconciliation_line', 'fin_revenue_reconciliation_method', 'pos_payment_method', 'pos_payment', 'pos_payment_line'] as $table) {
        if (!preg_match('/CREATE TABLE `' . $table . '` \(.*?;\n/s', $ddl, $match)) throw new RuntimeException('Missing baseline fixture DDL: ' . $table);
        $check($db->query($match[0]) !== false, 'baseline table ' . $table);
    }
    $db->insert('fin_account_mutation_log', ['mutation_no'=>'BEFORE-MIGRATION', 'mutation_date'=>date('Y-m-d'), 'account_id'=>1, 'mutation_type'=>'IN', 'amount'=>12.34, 'ref_module'=>'FINANCE']);
    $before = $db->get('fin_account_mutation_log')->row_array();
    $sql = file_get_contents($root . '/sql/2026-09-13a_finance_mutation_reporting_category.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    for ($round = 1; $round <= 2; $round++) {
        foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $check($db->query($statement) !== false, 'migration round ' . $round);
    }
    $after = $db->get('fin_account_mutation_log')->row_array();
    $check(array_intersect_key($after, $before) === $before && $after['report_category'] === null, 'migration preserves every legacy value and does not infer category');
    $db->query('DELETE FROM fin_account_mutation_log'); // This connection can only access the generated fixture socket.
    $db->data_cache = [];
} else {
    $context->db = DB(['dbdriver'=>'sqlite3', 'database'=>':memory:', 'db_debug'=>false, 'save_queries'=>true], true);
    $db = $context->db;
    $db->query('CREATE TABLE fin_account_mutation_log (id INTEGER PRIMARY KEY, mutation_no TEXT, mutation_date TEXT, account_id INTEGER, mutation_type TEXT, amount NUMERIC, balance_before NUMERIC, balance_after NUMERIC, ref_module TEXT, ref_table TEXT, ref_no TEXT, report_category TEXT, reversal_of_mutation_id INTEGER)');
}

$today = date('Y-m-d');
if (function_exists('finance_control_workspace_fixture')) { finance_control_workspace_fixture($db,$context,$check,$root); exit; }
$insert = static function ($module, $direction, $amount, $category = null, $table = null, $reversal = null) use ($db, $today): int {
    $ok = $db->insert('fin_account_mutation_log', ['mutation_no'=>'FIXTURE-' . bin2hex(random_bytes(6)), 'mutation_date'=>$today, 'account_id'=>1, 'mutation_type'=>$direction, 'amount'=>$amount, 'ref_module'=>$module, 'ref_table'=>$table, 'report_category'=>$category, 'reversal_of_mutation_id'=>$reversal]);
    if (!$ok) throw new RuntimeException('Fixture insert failed.');
    return (int)$db->insert_id();
};
$aggregate = new ReflectionMethod(Finance_report_model::class, 'estimation_mutation_rows');
$aggregate->setAccessible(true);
$totals = static fn() => Finance_mutation_policy::totals($aggregate->invoke($context->Finance_report_model, $today, $today)[0] ?? []);
foreach (Finance_mutation_policy::categories() as $category => $option) {
    foreach (['IN', 'OUT', 'TRANSFER', ''] as $direction) {
        $valid = in_array($direction, ['IN', 'OUT'], true) && ($option['direction'] === 'BOTH' || $option['direction'] === $direction);
        $check(Finance_mutation_policy::valid($category, $direction) === $valid, $category . ' direction ' . ($direction ?: 'empty'));
    }
}
$check(!Finance_mutation_policy::valid('UNKNOWN', 'IN'), 'unknown category rejected');
$insert('POS', 'IN', 100000);
$insert('POS', 'OUT', 5000, null, 'pos_refund');
$insert('PURCHASE', 'OUT', 20000);
$insert('FINANCE', 'IN', 10000, 'OTHER_INCOME');
$insert('FINANCE_RECON', 'IN', 1000, 'CASH_SURPLUS');
$insert('REVENUE_RECON', 'OUT', 2000, 'PROMO_EXPENSE');
$insert('FINANCE', 'OUT', 3000, 'PLATFORM_FEE');
$insert('FINANCE_RECON', 'OUT', 500, 'CASH_SHORTAGE');
$insert('FINANCE', 'OUT', 4000, 'OPERATING_EXPENSE');
$insert('FINANCE', 'IN', 50000, 'OWNER_CAPITAL');
$insert('FINANCE', 'OUT', 6000, 'OWNER_DRAWING');
$insert('FINANCE_RECON', 'IN', 100, 'BALANCE_CORRECTION');
$insert('FINANCE_RECON', 'OUT', 200, 'BALANCE_CORRECTION');
$legacy = $insert('FINANCE', 'IN', 700);
$insert('FINANCE', 'OUT', 800);
$insert(null, 'OUT', 50);
$insert('POS', 'OUT', 300, null, 'pos_other');
foreach (['FINANCE_TRANSFER', 'FINANCE_PAYABLE', 'FINANCE_RECEIVABLE', 'PAYROLL'] as $module) {
    $insert($module, 'IN', 999999); $insert($module, 'OUT', 999999);
}
$void = $insert('FINANCE', 'IN', 7654321, 'OTHER_INCOME');
$insert('FINANCE', 'OUT', 7654321, null, null, $void);
$summary = $totals();
foreach (['sales_total'=>100000,'refund_total'=>5000,'purchase_total'=>20000,'other_income_total'=>11000,'other_expense_total'=>9500,'promo_total'=>2000,'platform_fee_total'=>3000,'unclassified_in_total'=>700,'unclassified_out_total'=>850,'unclassified_count'=>3,'balance_only_in_total'=>50100,'balance_only_out_total'=>6200,'other_pos_out_total'=>300,'expense_total'=>30650,'gross_profit'=>75350] as $key=>$value) {
    $check(abs($summary[$key]-$value)<.001, 'SQL report ' . $key . '=' . $value);
}
$daily = $aggregate->invoke($context->Finance_report_model, $today, $today, true);
$check(Finance_mutation_policy::totals($daily[0])['gross_profit'] === $summary['gross_profit'], 'daily and aggregate shared rules agree; void pairs excluded');
$report = $context->Finance_report_model->financial_estimation_report((int)date('Y'), (int)date('n'));
$check($report['overview']['total_final_profit'] === 75350.0 && $report['overview']['total_other_pos_out'] === 300.0, 'public monthly estimation reconciles to breakdown');
$profitMethod=new ReflectionMethod(Finance_report_model::class,'estimated_profit_summary');$profitMethod->setAccessible(true);
$profit=$profitMethod->invoke($context->Finance_report_model,$today,$today);
$check($profit['estimated_profit_value']===75350.0 && $profit['other_income_total']===11000.0 && $profit['unclassified_count']===3,'global target/period profit uses same mutation totals');
// Pre-migration reporting stays readable and explicitly provisional.
$expressions = Finance_mutation_policy::expressions(false);
$check(!str_contains(implode(' ', $expressions), 'report_category'), 'legacy SQL does not query absent category column');

$controller = file_get_contents(APPPATH . 'controllers/Finance_reports.php');
foreach (['cash_reconciliation_line_save', 'cash_reconciliation_line_post', 'cash_reconciliation_round_create', 'revenue_reconciliation_line_save', 'revenue_reconciliation_line_post', 'revenue_reconciliation_round_create'] as $action) {
    $check(preg_match('/function ' . $action . '\([^)]*\)\s*\{\s*if \(!\$this->require_reconciliation_csrf\(\)\) return;/', $controller) === 1, $action . ' guards CSRF before mutation');
}
$purchaseController = file_get_contents(APPPATH . 'controllers/Purchase.php');
$check(preg_match('/function finance_mutation_classify\(\).*?require_permission\(self::PAGE_ORDER, \'edit\'\).*?require_purchase_mutation_csrf\(\)/s', $purchaseController) === 1, 'classification requires existing edit permission and CSRF');

// Execute the actual controller guard without bootstrapping sessions, HTTP, or app DB.
class MY_Controller {
    public function __get($key) { return get_instance()->$key; }
    protected function require_permission($page, $action): void { if (empty($GLOBALS['finance_fixture_permission_allowed']) || ($GLOBALS['finance_fixture_denied_page']??null)===$page) throw new RuntimeException('fixture_permission_denied'); }
}
require APPPATH . 'controllers/Finance_reports.php';
$context->input = new class {
    public string $token=''; public string $verb='POST';
    public function get_request_header($name, $xss=false) { return $this->token; }
    public function method($upper=false) { return $upper ? $this->verb : strtolower($this->verb); }
};
$context->session = new class { public function userdata($key) { return str_repeat('b',64); } };
$context->output = new class {
    public int $status=200; public string $body='';
    public function set_status_header($status) { $this->status=$status; return $this; }
    public function set_content_type(...$args) { return $this; }
    public function set_output($body) { $this->body=$body; return $this; }
};
$http=(new ReflectionClass(Finance_reports::class))->newInstanceWithoutConstructor();
foreach (['cash_reconciliation_line_save','cash_reconciliation_line_post','cash_reconciliation_round_create','revenue_reconciliation_line_save','revenue_reconciliation_line_post','revenue_reconciliation_round_create'] as $action) {
    $queryCount=count($db->queries); $http->$action();
    $check($context->output->status===403 && count($db->queries)===$queryCount, $action . ' rejects missing CSRF without querying DB');
}
$guard=new ReflectionMethod(Finance_reports::class,'require_reconciliation_csrf');$guard->setAccessible(true);
$context->input->token=str_repeat('b',64);
$check($guard->invoke($http)===true,'matching session CSRF accepted');
$context->input->verb='GET';
$check($guard->invoke($http)===false,'GET cannot mutate even with correct CSRF');
$context->input->verb='POST';
try { $http->revenue_reconciliation_line_post(); $denied=false; } catch(RuntimeException $e) { $denied=$e->getMessage()==='fixture_permission_denied'; }
$check($denied,'valid CSRF cannot bypass existing edit permission');

if ($mysql) {
    $db->insert('fin_company_account', ['id'=>1,'account_code'=>'FIXTURE','account_name'=>'Fixture only','current_balance'=>1000000]);
    $balance = static fn() => (float)$db->get_where('fin_company_account', ['id'=>1])->row('current_balance');
    $purchase = $context->Purchase_model;
    $before = $db->get_where('fin_account_mutation_log', ['id'=>$legacy])->row_array();
    $classification = ['mutation_id'=>$legacy,'report_category'=>'OTHER_INCOME','expected_category'=>'','reason'=>'Verified synthetic income'];
    $check($purchase->classify_account_mutation($classification, 0)['ok'], 'classify legacy incoming');
    $after = $db->get_where('fin_account_mutation_log', ['id'=>$legacy])->row_array();
    unset($before['report_category'], $after['report_category']);
    $check($before === $after && $balance() === 1000000.0, 'classification changes neither posting fields nor account balance');
    $check($totals()['gross_profit'] === 76050.0, 'classified legacy income updates live report');
    $check(!$purchase->classify_account_mutation($classification, 0)['ok'], 'stale category rejected');
    $check(!$purchase->classify_account_mutation(array_replace($classification,['mutation_id'=>$void]), 0)['ok'], 'void original classification rejected');
    $check((int)$db->where('action_code','MUTATION_CLASSIFY')->count_all_results('aud_transaction_log') === 1, 'metadata correction audited once');
    $payload=['account_id'=>1,'mutation_type'=>'IN','amount'=>123.45,'mutation_date'=>$today,'report_category'=>'OTHER_INCOME','client_request_key'=>str_repeat('a',32),'reference_no'=>'FIXTURE-IN','notes'=>'fixture'];
    $check($purchase->apply_manual_account_mutation($payload,0)['ok'], 'classified manual income saves');
    $check($balance()===1000123.45, 'manual income credits correct cents');
    $check(!empty($purchase->apply_manual_account_mutation($payload,0)['replayed']) && $balance()===1000123.45, 'identical retry does not credit twice');
    $check(!$purchase->apply_manual_account_mutation(array_replace($payload,['amount'=>124]),0)['ok'], 'same request different payload rejected');
    foreach ([INF, NAN, 'abc', -1, 0] as $invalid) $check(!$purchase->apply_manual_account_mutation(array_replace($payload,['amount'=>$invalid]),0)['ok'], 'invalid manual amount rejected');
    $fee=array_replace($payload,['mutation_type'=>'OUT','amount'=>20,'report_category'=>'PLATFORM_FEE','client_request_key'=>str_repeat('b',32),'reference_no'=>'SETTLEMENT-1']);
    $check(!$purchase->apply_manual_account_mutation($fee,0)['ok'], 'platform fee requires no-double-deduction confirmation');
    $fee['settlement_confirmed']=true;
    $check(!$purchase->apply_manual_account_mutation($fee,0)['ok'], 'confirmed platform fee additionally requires structured settlement reference');
    $check(!$purchase->apply_manual_account_mutation(array_replace($fee,['client_request_key'=>str_repeat('c',32)]),0)['ok'], 'new request cannot bypass structured settlement requirement');
    $db->insert('fin_period_close',['period_code'=>'FIXTURE','period_year'=>(int)date('Y'),'period_start'=>$today,'period_end'=>$today,'status'=>'CLOSED']);
    $check(!$purchase->classify_account_mutation(array_replace($classification,['expected_category'=>'OTHER_INCOME','report_category'=>'OWNER_CAPITAL']),0)['ok'], 'closed period rejects reclassification');
    $check(!$purchase->apply_manual_account_mutation(array_replace($payload,['client_request_key'=>str_repeat('d',32)]),0)['ok'], 'closed period rejects manual mutation');
    $db->where('period_code','FIXTURE')->update('fin_period_close',['status'=>'OPEN']);
    $db->insert('pos_payment_method',['id'=>1,'method_code'=>'FIXTURE','method_name'=>'Platform fixture','company_account_id'=>1]);
    $db->insert('pos_payment',['id'=>1,'payment_no'=>'FIXTURE-PAY','payment_status'=>'PAID','paid_at'=>$today . ' 01:00:00']);
    $db->insert('pos_payment_line',['payment_id'=>1,'line_no'=>1,'payment_method_id'=>1,'amount'=>100,'status'=>'PAID']);
    $revenue=$context->Finance_revenue_reconciliation_model;
    $settlement=['reconciliation_date'=>$today,'revenue_date'=>$today,'payment_method_id'=>1,'account_id'=>1,'actual_amount'=>'80,25','resolution_type'=>'OUT','report_category'=>'OPERATING_EXPENSE'];
    $saved=$revenue->save_line($settlement,0);
    $check($saved['ok'], 'revenue settlement draft saved with cents');
    $beforeBalance=$balance();
    $posted=$revenue->post_line($saved['line_id'],0);
    $check($posted['ok'] && abs($balance()-($beforeBalance-19.75))<.001, 'settlement 100 to 80.25 posts only 19.75');
    $check(!$revenue->post_line($saved['line_id'],0)['ok'], 'posted settlement cannot post twice');
    $check(!$revenue->save_line(array_replace($settlement,['actual_amount'=>'75']),0)['ok'], 'posted draft cannot be overwritten');
    $round=$revenue->create_round($settlement,0);
    $settlement['reconciliation_id']=$round['header']['id'];
    $saved=$revenue->save_line($settlement,0);
    $check($saved['ok'] && !$revenue->post_line($saved['line_id'],0)['ok'], 'same full settlement in a new round has no additional debit');
    $settlement['actual_amount']='75,25';
    $saved=$revenue->save_line($settlement,0);
    $check($saved['ok'] && $revenue->post_line($saved['line_id'],0)['ok'] && abs($balance()-($beforeBalance-24.75))<.001, 'corrected full settlement posts only incremental 5');
    $round=$revenue->create_round($settlement,0); $settlement['reconciliation_id']=$round['header']['id']; $settlement['actual_amount']='70,25';
    $saved=$revenue->save_line($settlement,0);
    $db->where('id',1)->update('pos_payment_line',['amount'=>101]);
    $check(!$revenue->post_line($saved['line_id'],0)['ok'], 'POS changed after draft: stale settlement blocked');
    $dashboard=$revenue->dashboard($today,$today);
    $check($dashboard['ok'] && (float)$dashboard['rows'][0]['adjusted_total']===-24.75, 'dashboard exposes cumulative prior adjustment');
    $db->where('period_code','FIXTURE')->update('fin_period_close',['status'=>'CLOSED']);
    $check(!$revenue->post_line($saved['line_id'],0)['ok'], 'closed period rejects settlement');
    $db->where('period_code','FIXTURE')->update('fin_period_close',['status'=>'OPEN']);
    $cash=$context->Finance_cash_reconciliation_model;
    $cashPayload=['reconciliation_date'=>$today,'account_id'=>1,'actual_balance'=>(string)($balance()+50.25),'resolution_type'=>'IN','report_category'=>'CASH_SURPLUS','resolution_note'=>'Verified fixture'];
    $cashSave=$cash->save_line($cashPayload,0);
    $check($cashSave['ok'] && $cashSave['line']['report_category']==='CASH_SURPLUS', 'cash reconciliation saves and returns category');
    $cashId=$cashSave['line']['id']; $beforeCash=$balance();
    $check($cash->post_line($cashId,0)['ok'] && abs($balance()-$beforeCash-50.25)<.001, 'cash surplus posts correct category and cents');
    $check(!$cash->post_line($cashId,0)['ok'], 'cash reconciliation cannot post twice');
    $cashRound=$cash->create_round($today,0);
    $cashPayload['reconciliation_id']=$cashRound['header']['id'] ?? $cashRound['reconciliation_id'] ?? 0;
    $cashPayload['actual_balance']=(string)($balance()-10);
    $cashPayload['resolution_type']='OUT'; $cashPayload['report_category']='';
    $cashSave=$cash->save_line($cashPayload,0);
    $check($cashSave['ok'] && !$cash->post_line($cashSave['line']['id'],0)['ok'], 'uncategorized draft allowed but posting blocked');
    // Exercise atomic rollback when the audit storage rejects a metadata correction.
    $db->query("CREATE TRIGGER fixture_audit_failure BEFORE INSERT ON aud_transaction_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture failure'");
    $correction=array_replace($classification,['expected_category'=>'OTHER_INCOME','report_category'=>'OWNER_CAPITAL']);
    $check(!$purchase->classify_account_mutation($correction,0)['ok'], 'audit failure rejects category correction');
    $check($db->get_where('fin_account_mutation_log',['id'=>$legacy])->row('report_category')==='OTHER_INCOME', 'audit failure rolls back category');
    $db->query('DROP TRIGGER fixture_audit_failure');
}

foreach ($argv as $argument) if (str_starts_with($argument,'--render=')) {
    $view=substr($argument,9);
    $data=['report'=>$report,'year'=>(int)date('Y'),'month'=>(int)date('n'),'report_categories'=>Finance_mutation_policy::categories(),'category_schema_ready'=>true,'can_classify_mutation'=>true,'purchase_mutation_csrf_token'=>str_repeat('a',64),'reconciliation_csrf'=>str_repeat('b',64),'can_reconcile_edit'=>true,'filter_account_id'=>'','date_from'=>$today,'date_to'=>$today,'accounts'=>[['id'=>1,'account_name'=>'Fixture account','account_code'=>'TEST','current_balance'=>1000000]],'rows'=>[],'dashboard'=>['ok'=>true,'reconciliation_date'=>$today,'revenue_date'=>$today,'accounts'=>[['id'=>1,'account_name'=>'Fixture account','account_code'=>'TEST']],'rows'=>[['payment_method_id'=>1,'method_name'=>'Fixture platform','method_code'=>'FIXTURE','actual_amount'=>80.25,'expected_amount'=>100,'difference_amount'=>-19.75,'line_id'=>1,'account_id'=>1,'transaction_count'=>1,'status'=>'OPEN','report_category'=>'PLATFORM_FEE','resolution_type'=>'OUT','settlement_delay_days'=>1]]]];
    $paths=['estimation'=>'finance/financial_estimation','mutations'=>'purchase/finance_mutation_index','revenue'=>'finance/revenue_reconciliation','cash'=>'finance/cash_reconciliation'];
    $data += ['title'=>'Mutasi Rekening','save_url'=>'/fixture/save','post_url'=>'/fixture/post','round_create_url'=>'/fixture/round'];
    $data['dashboard']['rows'][0]['method_type']='EWALLET';
    $data['dashboard']['accounts'][0] += ['account_id'=>1,'account_type'=>'CASH','current_system_balance'=>100,'system_balance'=>100,'actual_balance'=>80.25,'difference_amount'=>-19.75,'status'=>'OPEN','line_id'=>1,'resolution_type'=>'OUT','report_category'=>'CASH_SHORTAGE'];
    if (!isset($paths[$view])) throw new RuntimeException('Unknown render fixture.');
    (function ($path, $data): void { extract($data); include APPPATH . 'views/' . $path . '.php'; })->call($context,$paths[$view],$data);
    exit;
}
echo 'Finance mutation reporting: ' . $checks . ' PASS (' . ($mysql ? 'isolated MariaDB' : 'in-memory SQLite') . '). No application database accessed.' . PHP_EOL;
