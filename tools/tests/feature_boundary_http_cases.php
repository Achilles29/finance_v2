<?php
// Included ONLY in disposable portable acceptance: no real Control/customer/DB.
if(!isset($base,$root,$pdo,$worker,$fixture,$check)||!preg_match('#\A/var/lib/finance-portable-[a-f0-9]{16}\z#D',$base))throw new RuntimeException('DISPOSABLE_FIXTURE_REQUIRED');
$browser=static function(string $path,?array $body=null,array $headers=[])use($base,&$port):array{
 $c=curl_init('https://127.0.0.1:'.$port.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_TIMEOUT=>90,CURLOPT_COOKIEFILE=>$base.'/login-cookies',CURLOPT_COOKIEJAR=>$base.'/login-cookies']);
 if($body!==null){curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($body));$headers[]='Content-Type: application/json';}
 if($headers)curl_setopt($c,CURLOPT_HTTPHEADER,$headers);$b=curl_exec($c);$s=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
 return ['status'=>$s,'body'=>(string)$b,'json'=>is_string($b)?json_decode($b,true):null];
};
$r=$browser('/dashboard');
if($r['status']!==200||!str_contains($r['body'],'Starter POS')) {
 echo 'FEATURE diagnostic HTTP='.$r['status'].' '.substr(strip_tags($r['body']),0,700)."\n";
 $log=is_file($root.'/storage/logs/php.log')?file_get_contents($root.'/storage/logs/php.log'):'';
 echo 'FEATURE PHP diagnostic '.substr(str_replace([$dbPassword,$fixture['owner']['password'],$secret,$monitor,base64_encode($sk)],'[redacted]',$log),-2500)."\n";
}
$check($r['status']===200&&str_contains($r['body'],'Starter POS'),'signed Starter home, not mixed inventory dashboard');
$check(str_contains($r['body'],'finance-feature-lock')&&str_contains($r['body'],'<svg width="14"'),'RBAC-visible sidebar retains paid links with inline SVG lock');
foreach(['/inventory/stock/warehouse','/payroll/bonus','/finance-reports/financial-estimation','/finance-reports/accounting','/finance_accounting/index?tab=settings','/pos/reservations','/master/component','/pos-mobile/auth/login']as$path){
 $r=$browser($path);$check($r['status']===403,'superadmin package denial '.$path.' HTTP='.$r['status'].' '.($r['status']!==403?substr(strip_tags($r['body']),0,300):''));
 if($path!=='/pos-mobile/auth/login')$check(str_contains($r['body'],'layout-menu')&&str_contains($r['body'],'Starter POS')&&str_contains($r['body'],'Lihat paket'),'denied HTML uses app layout/sidebar and upgrade information '.$path);
}
$r=$browser('/production/component_opening_export_existing');$check($r['status']===403&&($r['json']['code']??'')==='FEATURE_UPGRADE_REQUIRED','unlicensed download denied as JSON before export reads');
$pdo->exec('USE portable_customer');
$snapshot=static function()use($pdo):array{$result=[];foreach(['pos_order','pos_payment','inv_stock_adjustment','pay_salary_disbursement','fin_company_account','fin_gl_journal','fin_gl_line','fin_gl_mapping','pur_division_stock_review']as$t){$result[$t]=[$pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn(),$pdo->query('CHECKSUM TABLE `'.$t.'` EXTENDED')->fetch(PDO::FETCH_NUM)[1]];}return $result;};
$before=$snapshot();
foreach(['/payroll/salary_disbursement_generate','/inventory_warehouse/adjustment','/finance_accounting/post','/finance_reports/revenue_reconciliation_line_post','/pos/online_food_order_verify/1','/master/store/component','/pos/orders/payment/save']as$path){
 $r=$browser($path,['voucher_selection'=>'RULE:1']);$check($r['status']===403&&($r['json']['code']??'')==='FEATURE_UPGRADE_REQUIRED','POST direct/alias rejected before business handler '.$path.' HTTP='.$r['status'].' '.json_encode($r['json']));
}
$r=$browser('/payroll/bonus',null,['Accept: application/json']);$check($r['status']===403&&($r['json']['code']??'')==='FEATURE_UPGRADE_REQUIRED','AJAX GET returns consistent JSON denial');
$check($snapshot()===$before,'rejected calls leave disposable business row counts and checksums unchanged');
foreach(['/master/product','/master/uom','/master/org-employee','/master/company-account','/pos/outlets-terminals','/pos/payment-methods','/pos/printers/general','/system/feature-access','/system/license','/system/business-profile']as$path){$r=$browser($path);$check($r['status']===200,'Starter dependency page '.$path.' status='.$r['status']);}
foreach(['/system/core/CodeIgniter.php','/system/libraries/Session/Session.php','/system/not-a-registered-route','/config/customer.json','/private/agent/agent.json']as$path)$check($browser($path)['status']===404,'public route exception never exposes source/secrets '.$path);
// Minimal sale fixtures only, in this harness's new socket/database. No existing master data.
$insert=static function(string $table,array $row)use($pdo):int{$q=$pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');$q->execute(array_values($row));return(int)$pdo->lastInsertId();};
$employee=$insert('org_employee',['employee_code'=>'TEST-CASHIER','employee_name'=>'Fixture cashier','is_active'=>1]);
$q=$pdo->prepare('UPDATE auth_user SET employee_id=? WHERE username=?');$q->execute([$employee,$fixture['owner']['username']]);
$outlet=$insert('pos_outlet',['outlet_code'=>'TEST','outlet_name'=>'Disposable outlet','is_active'=>1]);
$terminal=$insert('pos_terminal',['outlet_id'=>$outlet,'terminal_code'=>'TEST','terminal_name'=>'Disposable terminal','is_active'=>1]);
$division=$insert('mst_product_division',['code'=>'TEST','name'=>'Test','pos_scope'=>'REGULAR','is_active'=>1]);
$op=$insert('mst_operational_division',['code'=>'TEST','name'=>'Test','is_active'=>1]);
$class=$insert('mst_product_classification',['product_division_id'=>$division,'code'=>'TEST','name'=>'Test','is_active'=>1]);
$category=$insert('mst_product_category',['product_division_id'=>$division,'classification_id'=>$class,'code'=>'TEST','name'=>'Test','is_active'=>1]);
$uom=$insert('mst_uom',['code'=>'TEST','name'=>'Test','is_active'=>1]);
$r=$browser('/master/product/create');$check($r['status']===200&&!str_contains($r['body'],'id="id_hpp_standard"'),'Starter product form offers basic catalog, not HPP management');
preg_match('/name="master_mutation_csrf" value="([a-f0-9]+)"/',$r['body'],$masterToken);
$productPayload=['master_mutation_csrf'=>$masterToken[1]??'','product_name'=>'Disposable Starter sale','product_division_id'=>$division,'default_operational_division_id'=>$op,'classification_id'=>$class,'product_category_id'=>$category,'uom_id'=>$uom,'stock_mode'=>'MANUAL_AVAILABLE','selling_price'=>10000,'online_food_price'=>0,'show_pos'=>1,'is_active'=>1];
$c=curl_init('https://127.0.0.1:'.$port.'/master/product/store');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_COOKIEFILE=>$base.'/login-cookies',CURLOPT_COOKIEJAR=>$base.'/login-cookies',CURLOPT_POSTFIELDS=>http_build_query($productPayload)]);curl_exec($c);$productStatus=curl_getinfo($c,CURLINFO_RESPONSE_CODE);$productRedirect=curl_getinfo($c,CURLINFO_REDIRECT_URL);curl_setopt($c,CURLOPT_COOKIELIST,'FLUSH');curl_close($c);unset($c);
$product=(int)$pdo->query("SELECT id FROM mst_product WHERE product_name='Disposable Starter sale'")->fetchColumn();
$check($product>0&&$productStatus===303,'Starter creates actual product through CSRF-protected web form; redirect '.parse_url((string)$productRedirect,PHP_URL_PATH));
$account=$insert('fin_company_account',['account_code'=>'TEST-CASH','account_name'=>'Disposable cashier cash','account_type'=>'CASH','is_active'=>1]);
$method=$insert('pos_payment_method',['method_code'=>'TEST-CASH','method_name'=>'Cash','method_type'=>'CASH','company_account_id'=>$account,'is_active'=>1]);
// Existing optional inventory recon settings must not make Starter require a locked screen.
$pdo->exec("INSERT INTO sys_app_config(config_key,config_value) VALUES('pos.daily_recon_gate_mode','OPEN_AND_CLOSE') ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)");
$browser('/auth/logout');$c=curl_init('https://127.0.0.1:'.$port.'/auth/do_login');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_COOKIEFILE=>$base.'/login-cookies',CURLOPT_COOKIEJAR=>$base.'/login-cookies',CURLOPT_POSTFIELDS=>http_build_query(['identifier'=>$fixture['owner']['username'],'password'=>$fixture['owner']['password']])]);curl_exec($c);curl_setopt($c,CURLOPT_COOKIELIST,'FLUSH');curl_close($c);unset($c);
$r=$browser('/pos/cashier');$check($r['status']===200,'Starter cashier loads with basic master');
preg_match('/const posTransactionCsrfToken = ("[^"]+")/',$r['body'],$csrfMatch);$csrf=json_decode($csrfMatch[1]??'""',true);$check(is_string($csrf)&&strlen($csrf)>20,'cashier CSRF issued');$headers=['X-Pos-Transaction-CSRF: '.$csrf];
$r=$browser('/pos/cashier/open',['outlet_id'=>$outlet,'terminal_id'=>$terminal,'opening_cash'=>0],$headers);$check($r['status']===200&&!empty($r['json']['ok']),'Starter opens cashier '.json_encode($r['json']));
$r=$browser('/pos/orders/draft/save',['outlet_id'=>$outlet,'terminal_id'=>$terminal,'customer_name'=>'Walk in','service_type'=>'DINE_IN','lines'=>[['product_id'=>$product,'qty'=>2]]],$headers);$check($r['status']===200&&!empty($r['json']['id']),'Starter draft stored '.json_encode($r['json']));$order=(int)$r['json']['id'];
$r=$browser('/pos/order_draft_confirm/'.$order,[],$headers);$check($r['status']===200&&!empty($r['json']['ok']),'Starter confirms order '.json_encode($r['json']));
$r=$browser('/pos/order_payment_prepare/'.$order);$check($r['status']===200&&!empty($r['json']['ok']),'Starter prepares payment '.json_encode($r['json']));
$check(($r['json']['payment']['voucher_options']??null)===[],'Starter payment does not expose voucher programs');
$r=$browser('/pos/orders/payment/save',['order_id'=>$order,'payment_method_ids'=>[$method],'paid_amounts'=>[20000],'reference_nos'=>['TEST']],$headers);$check($r['status']===200&&!empty($r['json']['ok']),'Starter payment succeeds '.json_encode($r['json']));$payment=(int)$r['json']['id'];
$check($pdo->query('SELECT status FROM pos_order WHERE id='.$order)->fetchColumn()==='PAID','order really paid in disposable DB');
$check((float)$pdo->query('SELECT current_balance FROM fin_company_account WHERE id='.$account)->fetchColumn()===20000.0,'internal cash posting works while Finance Advanced is locked');
$balanceBefore=$pdo->query('SELECT * FROM fin_company_account WHERE id='.$account)->fetch(PDO::FETCH_ASSOC);
$r=$browser('/master/company-account/update/'.$account,['current_balance'=>999999]);
$check($r['status']===403&&($r['json']['code']??'')==='FEATURE_UPGRADE_REQUIRED'&&$balanceBefore===$pdo->query('SELECT * FROM fin_company_account WHERE id='.$account)->fetch(PDO::FETCH_ASSOC),'forged manual finance balance is denied without updating the existing account');
$r=$browser('/pos/order_payment_print_targets/'.$payment);$check($r['status']===422&&str_contains($r['json']['message']??'','Konfigurasi printer belum siap'),'unconfigured physical printer produces setup guidance, not a license rejection');
$connection=$insert('pos_print_connection',['connection_code'=>'TEST','connection_name'=>'Disposable printer payload only','connection_type'=>'LOCAL_AGENT','agent_printer_code'=>'TEST','agent_host'=>'127.0.0.1','python_port'=>18099,'is_active'=>1]);
$layout=$insert('pos_print_layout',['layout_code'=>'TEST','layout_name'=>'Disposable receipt','document_type'=>'RECEIPT','layout_payload'=>'{}','is_active'=>1]);
$insert('pos_print_route',['route_code'=>'TEST','route_name'=>'Disposable receipt route','event_code'=>'ORDER_PAID_RECEIPT','document_type'=>'RECEIPT','connection_id'=>$connection,'layout_id'=>$layout,'print_mode'=>'AUTO','is_active'=>1]);
$r=$browser('/pos/order_payment_print_targets/'.$payment);$check($r['status']===200&&!empty($r['json']['direct_print_targets']),'Starter generates configured printer payload; no physical network dispatch '.json_encode($r['json']['message']??''));
foreach(['/pos/report_sales_document_print/'.$order.'/receipt','/pos/reports/sales','/pos/report_sales_transaction/'.$order]as$path){$r=$browser($path);$check($r['status']===200,'Starter printable receipt / sales reporting '.$path.' status='.$r['status']);}
require $source.'/tools/tests/feature_boundary_stock_http_cases.php';
$r=$browser('/pos/cashier/close',['actual_cash'=>20000],$headers);$check($r['status']===200&&!empty($r['json']['ok']),'Starter closes cashier '.json_encode($r['json']));
// Same authenticated browser, new signed document via normal companion sync; no reactivation/reinstall.
$identityBeforeUpgrade=hash_file('sha256',$root.'/private/agent/agent.json');$journalBeforeUpgrade=hash_file('sha256',$root.'/private/database.json');
$fixture['edition']='ENTERPRISE';$fixture['issued']=time()+1;$json($base.'/fixture.json',$fixture);$r=$worker('sync');$check($r['ok'],'valid Enterprise signed upgrade sync');
$r=$browser('/payroll/bonus');$check($r['status']===200,'same superadmin session unlocks after signed upgrade');
foreach(['/finance-reports/accounting','/finance-reports/accounting?tab=guide','/finance_accounting/index?tab=settings','/guide','/guide?audience=server'] as $path) {
 $r=$browser($path);$check($r['status']===200&&!str_contains($r['body'],'A PHP Error was encountered'),'Full package accounting + user-guide dependency included '.$path.' status='.$r['status']);
}
$r=$browser('/dashboard');$check($r['status']===200&&!str_contains($r['body'],'finance-feature-lock'),'sidebar package cache follows upgraded signed license');
$check(hash_file('sha256',$root.'/private/agent/agent.json')===$identityBeforeUpgrade&&hash_file('sha256',$root.'/private/database.json')===$journalBeforeUpgrade,'upgrade preserves identity and SQL journal');
$check($pdo->query('SELECT status FROM pos_order WHERE id='.$order)->fetchColumn()==='PAID','upgrade preserves transaction');
// Enterprise does not grant RBAC. A different account has only home access.
$role=$insert('auth_role',['role_code'=>'FIXTURE_HOME_ONLY','role_name'=>'Fixture home only','is_active'=>1]);
$page=(int)$pdo->query("SELECT id FROM sys_page WHERE page_code='dashboard.index'")->fetchColumn();
$insert('auth_role_permission',['role_id'=>$role,'page_id'=>$page,'can_view'=>1]);
$limitedUsername='limited_'.bin2hex(random_bytes(6));$limitedPassword=bin2hex(random_bytes(12)).'X9!';$limited=$insert('auth_user',['username'=>$limitedUsername,'password_hash'=>password_hash($limitedPassword,PASSWORD_DEFAULT),'is_active'=>1]);
$insert('auth_user_role',['user_id'=>$limited,'role_id'=>$role]);
$c=curl_init('https://127.0.0.1:'.$port.'/auth/do_login');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_COOKIEJAR=>$base.'/limited-cookies',CURLOPT_POSTFIELDS=>http_build_query(['identifier'=>$limitedUsername,'password'=>$limitedPassword])]);curl_exec($c);curl_setopt($c,CURLOPT_COOKIELIST,'FLUSH');curl_close($c);unset($c);
$c=curl_init('https://127.0.0.1:'.$port.'/payroll/bonus');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_COOKIEFILE=>$base.'/limited-cookies']);$limitedBody=curl_exec($c);$limitedStatus=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);unset($c);
$check($limitedStatus===403&&!str_contains((string)$limitedBody,'FEATURE_UPGRADE_REQUIRED'),'Enterprise still denies user without payroll RBAC');
$fixture['edition']='STARTER_POS';$fixture['issued']=time()+2;$json($base.'/fixture.json',$fixture);$check($worker('sync')['ok'],'signed Starter replacement sync');
$r=$browser('/payroll/bonus');$check($r['status']===403,'same session re-locks without logout');
// Cashier-only users must not depend on Dashboard or the paid HR portal to log in.
$cashierRole=$insert('auth_role',['role_code'=>'FIXTURE_CASHIER_ONLY','role_name'=>'Fixture cashier only','is_active'=>1]);
$cashierPage=(int)$pdo->query("SELECT id FROM sys_page WHERE page_code='pos.cashier.index'")->fetchColumn();
$insert('auth_role_permission',['role_id'=>$cashierRole,'page_id'=>$cashierPage,'can_view'=>1]);
$cashierUsername='cashier_'.bin2hex(random_bytes(6));$cashierPassword=bin2hex(random_bytes(12)).'X9!';
$cashierUser=$insert('auth_user',['username'=>$cashierUsername,'password_hash'=>password_hash($cashierPassword,PASSWORD_DEFAULT),'employee_id'=>$employee,'is_active'=>1]);
$insert('auth_user_role',['user_id'=>$cashierUser,'role_id'=>$cashierRole]);
$c=curl_init('https://127.0.0.1:'.$port.'/auth/do_login');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_COOKIEJAR=>$base.'/cashier-cookies',CURLOPT_POSTFIELDS=>http_build_query(['identifier'=>$cashierUsername,'password'=>$cashierPassword])]);curl_exec($c);$cashierRedirect=curl_getinfo($c,CURLINFO_REDIRECT_URL);curl_setopt($c,CURLOPT_COOKIELIST,'FLUSH');curl_close($c);unset($c);
$check(str_ends_with((string)$cashierRedirect,'/pos/cashier'),'cashier-only Starter login redirects to licensed cashier, not paid HR; got '.parse_url((string)$cashierRedirect,PHP_URL_PATH));
$c=curl_init('https://127.0.0.1:'.$port.'/pos/cashier');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_COOKIEFILE=>$base.'/cashier-cookies']);curl_exec($c);$cashierStatus=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);unset($c);
$check($cashierStatus===200,'cashier-only Starter account really accesses cashier');
$visual=$run(['/usr/bin/node',$source.'/tools/tests/feature_boundary_browser.cjs',$base,'https://127.0.0.1:'.$port]);
echo 'BROWSER evidence '.($visual['stdout']??$visual['output']??'').($visual['stderr']??'')."\n";
foreach(['application/core/MY_Hooks.php','application/core/MY_Router.php','application/config/feature_access.php','application/libraries/Feature_policy.php','tools/db/ManagedMigrationProof.php','tools/db/managed_migration_proofs.json','tools/db/migration_catalog.json','tools/db/migration_runner.php','tools/install/portable/PortableDatabase.php']as$file){$original=file_get_contents($root.'/'.$file);$write($root.'/'.$file,$original."\n",0644);$check($browser('/pos/cashier')['status']===423,'changed feature or migration core fails closed '.$file);$write($root.'/'.$file,$original,0644);}
$savedFixture=$fixture;$fixture['entitlements']=[];$fixture['issued']=time()+3;$json($base.'/fixture.json',$fixture);$check($worker('licenseSync')['ok'],'signed missing-entitlement document sync');
$check($browser('/pos/cashier')['status']===403,'edition name Starter without actual grants does not unlock POS');
$fixture['revoked']=true;$json($base.'/fixture.json',$fixture);$worker('licenseSync');$check($browser('/pos/cashier')['status']===423,'revoked license blocks business even with authenticated superadmin');
$fixture=$savedFixture;$fixture['issued']=time()+4;$json($base.'/fixture.json',$fixture);$check($worker('licenseSync')['ok'],'restore valid signed document in disposable fixture');
$check($visual['code']===0,'real Chrome paints sidebar SVG and locked page');
