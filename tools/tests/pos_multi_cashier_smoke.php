<?php
declare(strict_types=1);

// No application bootstrap/config. --mariadb creates a private socket-only
// server with synthetic data; never accepts an existing database connection.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
define('APPPATH', $root . '/application/');
date_default_timezone_set('Asia/Jakarta');
function log_message($level, $message): void {}
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message, ...$rest): void { throw new RuntimeException('Fixture database error.'); }
class CI_Model { public $db; }
require BASEPATH . 'database/DB.php';
require APPPATH . 'models/Pos_model.php';
require APPPATH . 'models/Pos_report_model.php';
$checks = 0;
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $GLOBALS['checks']++;
    echo 'PASS: ' . $message . PHP_EOL;
}
function fixture_db(string $socket, string $schema = 'multi_cashier_fixture') {
    if (!preg_match('~\A/tmp/finance-multi-cashier-[a-zA-Z0-9]+/db.sock\z~D', $socket)
        || is_link(dirname($socket))) throw new RuntimeException('Disposable socket required.');
    $db = DB(['dbdriver'=>'mysqli', 'hostname'=>$socket, 'username'=>'root', 'password'=>'',
        'database'=>$schema, 'db_debug'=>false, 'pconnect'=>false, 'char_set'=>'utf8mb4'], true);
    $server = $db->query('SELECT @@datadir AS datadir, @@skip_networking AS isolated')->row_array();
    if (realpath($server['datadir']) !== realpath(dirname($socket) . '/data') || (int)$server['isolated'] !== 1)
        throw new RuntimeException('Refusing non-isolated database.');
    $db->query('SET SESSION innodb_lock_wait_timeout=8');
    return $db;
}
function fixture_pos($db): Pos_model {
    $model = (new ReflectionClass(Pos_model::class))->newInstanceWithoutConstructor();
    $model->db = $db;
    return $model;
}
if (($argv[1] ?? '') === '--worker') {
    $db = fixture_db((string)($argv[2] ?? ''));
    echo json_encode(fixture_pos($db)->open_cashier_session([
        'outlet_id'=>1, 'terminal_id'=>(int)$argv[4], 'opening_cash'=>100000,
    ], (int)$argv[3]), JSON_THROW_ON_ERROR);
    exit;
}

$source = file_get_contents(APPPATH . 'models/Pos_model.php');
$open = substr($source, strpos($source, 'public function open_cashier_session('));
$open = substr($open, 0, strpos($open, 'public function close_cashier_session('));
check(substr_count($open, 'FOR UPDATE') === 3, 'employee, outlet and terminal locked before new session');
check(strpos($open, 'FOR UPDATE') < strrpos($open, 'find_active_cashier_session('), 'employee session checked again inside transaction');
check(strpos($open, 'FOR UPDATE') < strpos($open, 'active_cashier_sessions()'), 'terminal occupancy checked after locking');
check(strpos($open, 'finally') !== false && strpos($open, '$this->db->db_debug = $previousDebug') !== false, 'debug restored after every open result');
$view = file_get_contents(APPPATH . 'views/pos/cashier_index.php');
check(str_contains($view, '$occupiedTerminals') && str_contains($view, ' — Dipakai '), 'busy terminals identified for cashier');
check(str_contains($view, 'isVisible && !opt.disabled') && str_contains($view, '!selectedOption.disabled'), 'busy terminals excluded from automatic selection');
foreach (['report_daily_sales.php', 'report_daily_sales_print.php'] as $file) {
    $view = file_get_contents(APPPATH . 'views/pos/' . $file);
    check(str_contains($view, "shift['terminal_name']") && str_contains($view, "shift['outlet_name']"), $file . ' identifies terminal/outlet');
}
if (!in_array('--mariadb', $argv, true)) {
    echo "PASS: $checks source checks; run --mariadb for real concurrency and report tests.\n";
    exit;
}

$scratch = trim((string)shell_exec('mktemp -d /tmp/finance-multi-cashier-XXXXXX'));
if (!preg_match('~\A/tmp/finance-multi-cashier-[a-zA-Z0-9]+\z~D', $scratch) || !is_dir($scratch))
    throw new RuntimeException('Private scratch unavailable.');
$base = '/www/server/mysql';
$init = proc_open([$base.'/scripts/mariadb-install-db','--no-defaults','--basedir='.$base,
    '--datadir='.$scratch.'/data','--auth-root-authentication-method=normal','--skip-test-db'],
    [0=>['file','/dev/null','r'],1=>['file',$scratch.'/init.log','a'],2=>['file',$scratch.'/init.log','a']], $pipes);
if (!is_resource($init) || proc_close($init) !== 0) throw new RuntimeException('Fixture init failed: ' . $scratch);
$socket = $scratch . '/db.sock';
$server = proc_open([$base.'/bin/mariadbd','--no-defaults','--user='.posix_getpwuid(posix_geteuid())['name'],
    '--basedir='.$base,'--datadir='.$scratch.'/data','--socket='.$socket,'--pid-file='.$scratch.'/db.pid',
    '--log-error='.$scratch.'/db.log','--skip-networking','--skip-log-bin','--innodb-buffer-pool-size=32M','--innodb-log-file-size=16M'],
    [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file',$scratch.'/db.log','a']], $pipes);
if (!is_resource($server)) throw new RuntimeException('Fixture process unavailable.');
register_shutdown_function(static function () use ($server, $scratch): void {
    proc_terminate($server); proc_close($server);
    echo "\nDisposable server stopped; diagnostics retained: $scratch\n";
});
mysqli_report(MYSQLI_REPORT_OFF);
$ready = false;
for ($i=0; $i<100; $i++) {
    $connection = @new mysqli('localhost','root','','',0,$socket);
    if (!$connection->connect_errno) { $ready=true; $connection->close(); break; }
    usleep(100000);
}
check($ready, 'disposable MariaDB started');
$db = fixture_db($socket, '');
check($db->query('CREATE DATABASE multi_cashier_fixture') !== false && $db->db_select('multi_cashier_fixture'), 'new fixture database');
echo 'MariaDB ' . $db->query('SELECT VERSION() AS v')->row_array()['v'] . "\n";
$needed = ['org_employee','pos_outlet','pos_terminal','pos_shift','pos_shift_summary','pos_cashier_session',
    'pos_order','pos_payment','pos_payment_line','pos_payment_method','pos_refund','pos_void',
    'pos_shift_account_summary','pos_shift_cash_denomination'];
$ddl = file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
$db->query('SET FOREIGN_KEY_CHECKS=0'); // Empty baseline DDL only; nullable external refs are unused.
foreach ($needed as $table) {
    if (!preg_match('/CREATE TABLE `' . $table . '` \(.*?;\n/s', $ddl, $match)) throw new RuntimeException('Fixture table missing: ' . $table);
    check($db->query($match[0]) !== false, 'baseline DDL: ' . $table);
}
$db->query('SET FOREIGN_KEY_CHECKS=1');
check((int)$db->query('SELECT @@FOREIGN_KEY_CHECKS AS v')->row_array()['v'] === 1, 'FK checks enabled for all test writes');
for ($id=1; $id<=12; $id++) {
    check($db->insert('org_employee',['id'=>$id,'employee_code'=>'TEST-'.$id,'employee_name'=>'Kasir '.$id]), 'synthetic employee '.$id);
}
foreach ([1,2] as $id) check($db->insert('pos_outlet',['id'=>$id,'outlet_code'=>'TEST-'.$id,'outlet_name'=>'Outlet '.$id]), 'synthetic outlet '.$id);
for ($id=1; $id<=12; $id++) check($db->insert('pos_terminal',[
    'id'=>$id,'outlet_id'=>$id===12?2:1,'terminal_code'=>'TEST-'.$id,
    'terminal_name'=>$id===1?'STORE':($id===2?'STREET-COFFEE':'Terminal '.$id),'is_active'=>$id===11?0:1,
]), 'synthetic terminal '.$id);

// Block the outlet row while two independent PHP/database clients reach the
// locking boundary, then release. Proves overlapping requests, not sequential mocks.
$race = static function (array $requests) use ($db, $socket): array {
    $db->trans_begin();
    $db->query('SELECT id FROM pos_outlet WHERE id=1 FOR UPDATE');
    $workers=[];
    foreach ($requests as [$employee,$terminal]) {
        $p=proc_open([PHP_BINARY,__FILE__,'--worker',$socket,(string)$employee,(string)$terminal],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($p)) throw new RuntimeException('Worker failed.');
        $workers[]=[$p,$pipes];
    }
    $waiting=0;
    for ($i=0;$i<100;$i++) {
        $waiting=(int)$db->query("SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE DB='multi_cashier_fixture' AND INFO LIKE 'SELECT id%FOR UPDATE'")->row_array()['n'];
        if ($waiting>=count($workers)) break;
        usleep(20000);
    }
    $db->trans_commit();
    $results=[];
    foreach ($workers as [$p,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($p)!==0 || $err!=='') throw new RuntimeException('Worker failed: '.$err);
        $results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);
    }
    check($waiting>=count($workers),'both real requests overlapped at row locks');
    return $results;
};
$results=$race([[1,1],[2,2]]);
check(!empty($results[0]['ok']) && !empty($results[1]['ok']), 'Store and Street open together');
check($results[0]['shift_id']!==$results[1]['shift_id'], 'parallel terminals have separate shifts');
check($results[0]['session']['shift_no']!==$results[1]['session']['shift_no'], 'parallel shift numbers unique');
$results=$race([[3,3],[4,3]]);
check(count(array_filter($results,fn($r)=>!empty($r['ok'])))===1, 'same terminal has exactly one winner');
check(count(array_filter($results,fn($r)=>($r['code']??'')==='TERMINAL_BUSY'))===1, 'loser receives terminal busy');
$results=$race([[5,4],[5,5]]);
check(!empty($results[0]['ok']) && !empty($results[1]['ok']) && $results[0]['session']['id']===$results[1]['session']['id'], 'same employee opens exactly one session');
check(count(array_filter($results,fn($r)=>!empty($r['already_open'])))===1, 'duplicate request reattaches idempotently');
$pos=fixture_pos($db);
$store=$pos->find_active_cashier_session(1);$street=$pos->find_active_cashier_session(2);
$retry=$pos->open_cashier_session(['outlet_id'=>1,'terminal_id'=>2,'mobile_backup_mode'=>true],1);
check(!empty($retry['already_open']) && $retry['session']['id']===$store['id'], 'same cashier web/APK backup retains original session');
$before=(int)$db->count_all('pos_shift');
foreach ([[11,1,0],[12,1,0],[6,1,-1]] as [$terminal,$outlet,$cash]) {
    $result=$pos->open_cashier_session(['outlet_id'=>$outlet,'terminal_id'=>$terminal,'opening_cash'=>$cash],6);
    check(empty($result['ok']), 'invalid/inactive/wrong-outlet/negative opening rejected: '.$terminal);
}
check((int)$db->count_all('pos_shift')===$before, 'rejections leave no shift or money entry');
check($db->db_debug===false, 'original debug setting restored');
check($db->query("CREATE TRIGGER fixture_open_failure BEFORE INSERT ON pos_cashier_session FOR EACH ROW BEGIN IF NEW.employee_id=8 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture private failure'; END IF; END") !== false, 'isolated failure injection ready');
$db->db_debug=true;
$failed=$pos->open_cashier_session(['outlet_id'=>1,'terminal_id'=>8,'opening_cash'=>100000],8);
check(empty($failed['ok']) && !str_contains($failed['message'],'fixture private'), 'insert failure returns safe message instead of raw SQL');
check($db->db_debug===true && (int)$db->count_all('pos_shift')===$before && $pos->find_active_cashier_session(8)===null, 'partial opening rolls back shift/session and restores debug');
$db->db_debug=false;

$insert = static function (string $table,array $row) use ($db): int {
    if (!$db->insert($table,$row)) throw new RuntimeException('Synthetic insert '.$table.': '.json_encode($db->error()));
    return (int)$db->insert_id();
};
$today=date('Y-m-d');$now=$today.' 12:00:00';
$cashMethod=$insert('pos_payment_method',['method_code'=>'TEST-CASH','method_name'=>'Tunai','method_type'=>'CASH']);
$orders=[];
foreach ([[$store,200000],[$street,75000]] as [$session,$amount]) {
    $order=$insert('pos_order',['order_no'=>'TEST-'.$session['id'],'outlet_id'=>1,'terminal_id'=>$session['terminal_id'],
        'cashier_employee_id'=>$session['employee_id'],'shift_id'=>$session['shift_id'],'cashier_session_id'=>$session['id'],
        'status'=>'PAID','ordered_at'=>$now,'paid_at'=>$now,'subtotal_amount'=>$amount,'grand_total'=>$amount,'paid_total'=>$amount]);
    $orders[]=$order;
    $payment=$insert('pos_payment',['payment_no'=>'TEST-'.$session['id'],'order_id'=>$order,'shift_id'=>$session['shift_id'],
        'cashier_session_id'=>$session['id'],'payment_type'=>'FINAL','payment_status'=>'PAID','net_amount'=>$amount,'paid_at'=>$now]);
    // Two payment lines exercise prevention of double-counting report orders.
    foreach ([0.4,0.6] as $index=>$part) $insert('pos_payment_line',['payment_id'=>$payment,'line_no'=>$index+1,'payment_method_id'=>$cashMethod,'amount'=>$amount*$part]);
}
$report=new Pos_report_model();$report->db=$db;
$shiftsMethod=new ReflectionMethod($report,'daily_sales_shifts');$shiftsMethod->setAccessible(true);
$overviewMethod=new ReflectionMethod($report,'daily_sales_overview');$overviewMethod->setAccessible(true);
$rows=$shiftsMethod->invoke($report,$today,1);$map=array_column($rows,null,'id');
check((float)$map[$store['shift_id']]['revenue']===200000.0 && (float)$map[$street['shift_id']]['revenue']===75000.0, 'both OPEN shift totals live before close');
check((int)$map[$store['shift_id']]['trx_count']===1 && $map[$street['shift_id']]['terminal_name']==='STREET-COFFEE', 'report identifies terminal and counts each sale once');
check((float)$overviewMethod->invoke($report,$today,0)['gross_sales']===275000.0, 'daily report combines both cashiers once');
check($shiftsMethod->invoke($report,$today,2)===[], 'other outlet filter does not leak sessions');
$voidOrder=$insert('pos_order',['order_no'=>'TEST-VOID','outlet_id'=>1,'cashier_employee_id'=>1,'shift_id'=>$store['shift_id'],
    'status'=>'VOID','ordered_at'=>$now,'grand_total'=>90000]);
$rows=$shiftsMethod->invoke($report,$today,1);$map=array_column($rows,null,'id');
check((float)$map[$store['shift_id']]['revenue']===200000.0, 'VOID order excluded from live sales');
$insert('pos_refund',['refund_no'=>'TEST-REFUND','order_id'=>$orders[0],'refund_amount'=>10000,'payment_method_id'=>$cashMethod,'refunded_at'=>$now]);
check((float)$overviewMethod->invoke($report,$today,0)['net_sales']===265000.0, 'posted refund reduces combined daily net');
$closed=$pos->close_cashier_session(['actual_cash'=>290000],1);
check(!empty($closed['ok']) && (float)$closed['summary']['variance_cash']===0.0, 'Store closes with its own opening, sales and refund only');
check((float)$closed['summary']['total_net_sales']===200000.0 && (int)$closed['summary']['total_order_count']===1, 'closing also excludes VOID amounts/counts without editing the void data');
check($pos->find_active_cashier_session(1)===null && $pos->find_active_cashier_session(2)['id']===$street['id'], 'closing Store leaves Street open');
$rows=$shiftsMethod->invoke($report,$today,1);$map=array_column($rows,null,'id');
check($map[$store['shift_id']]['shift_status']==='CLOSED' && (float)$map[$street['shift_id']]['revenue']===75000.0, 'closed snapshot and live other terminal coexist');
check((float)$overviewMethod->invoke($report,$today,0)['gross_sales']===275000.0, 'closing does not change daily sales or double-count');
$db->where('id',$street['shift_id'])->update('pos_shift',['opened_at'=>date('Y-m-d',strtotime('-2 days')).' 23:00:00']);
$rows=$shiftsMethod->invoke($report,$today,1);$map=array_column($rows,null,'id');
check(isset($map[$street['shift_id']]), 'ongoing multi-day shift still visible today');
check($shiftsMethod->invoke($report,date('Y-m-d',strtotime('-3 days')),1)===[], 'future shifts not shown on historical day');
check($db->query('SELECT terminal_id FROM pos_cashier_session WHERE session_status="OPEN" GROUP BY terminal_id HAVING COUNT(*)>1')->num_rows()===0, 'no duplicate OPEN terminal');
check($db->query('SELECT employee_id FROM pos_cashier_session WHERE session_status="OPEN" GROUP BY employee_id HAVING COUNT(*)>1')->num_rows()===0, 'no duplicate OPEN employee');
echo "PASS: $checks multi-cashier checks, real disposable MariaDB and concurrent PHP workers.\n";
