<?php
declare(strict_types=1);
// Pure policy tests: no DB, environment mutation, HTTP, or live customer credentials.
$root=dirname(__DIR__,2);
require $root.'/application/libraries/Feature_policy.php';
$m=json_decode(file_get_contents($root.'/app-manifest.json'),true,64,JSON_THROW_ON_ERROR);
$p=require $root.'/application/config/feature_access.php';
$types=array_column($m['features'],'value_type','code');$editions=[];
foreach($m['editions'] as $edition)foreach($edition['features'] as $key=>$value)$editions[$edition['code']][$key]=$types[$key]==='BOOLEAN'?($value==='1'):(int)$value;
$v=['verified'=>true,'status'=>'ACTIVE','payload'=>['edition'=>'STARTER_POS','entitlements'=>$editions['STARTER_POS']]];
$f=new Feature_policy($m,$p,$v,true);$n=0;
$check=static function(bool $ok,string $name)use(&$n):void{if(!$ok)throw new RuntimeException($name);$n++;};
foreach(['cashier','cashier_open','cashier_close','order_draft_save','order_draft_confirm','order_payment_prepare','order_payment_save','order_payment_print_targets','report_sales','report_sales_detail'] as $method)$check($f->route('pos',$method)['allowed'],'Starter POS '.$method);
foreach(['uom','product','extra','extra-group','product-division','product-category','product-classification','company-account','bank','org-employee','operational-division']as$entity)$check($f->route('master','index',[$entity])['allowed'],'Basic master '.$entity);
foreach([['inventory_warehouse','index'],['payroll','bonus'],['finance_accounting','index'],['pos','reservations'],['pos_mobile','login'],['master','index',['component']]]as$r)$check(!$f->route(...$r)['allowed'],'Paid route '.json_encode($r));
foreach(['auth'=>['index','do_login','logout'],'license'=>['index'],'feature_access'=>['index','upgrade']] as $c=>$methods)foreach($methods as $method)$check($f->route($c,$method)['allowed'],'Recovery '.$c.'/'.$method);
$check(!$f->route('pos','future_unreviewed_export')['allowed'],'unknown method fail closed');
$check(!$f->route('master','index',['unknown'])['allowed'],'unknown entity fail closed');
foreach([false,0,1,'1','true',null]as$value){$bad=$v;$bad['payload']['entitlements']['POS_WEB']=$value;$check(!(new Feature_policy($m,$p,$bad,true))->allows('POS_WEB'),'strict boolean');}
$check(!$f->allows('LIMIT_OUTLETS'),'integer limit is not a boolean feature');
foreach(['verified'=>false,'status'=>'REVOKED']as$k=>$val){$bad=$v;$bad[$k]=$val;$check(!(new Feature_policy($m,$p,$bad,true))->allows('POS_WEB'),'invalid/revoked denied');}
$bad=$v;unset($bad['payload']['entitlements']['BUSINESS_PROFILE']);$check(!(new Feature_policy($m,$p,$bad,true))->allows('POS_WEB'),'missing dependency fail closed');
$check($f->payload('pos','order_payment_save',['voucher_selection'=>'RULE:1'])['code']==='FEATURE_UPGRADE_REQUIRED','embedded voucher denied');
foreach([0,0.0,'0.00','',null]as$zero)$check($f->payload('pos','order_payment_save',['voucher_amount'=>$zero])===null,'normal payment without promo');
$check($f->payload('master','update',['current_balance'=>10],['company-account'])!==null,'forged manual balance denied');
$legacy=new Feature_policy($m,$p,[],false);$check($legacy->route('anything','legacy')['allowed'],'development unchanged');
$full=$v;$full['payload']['edition']='ENTERPRISE';$full['payload']['entitlements']=$editions['ENTERPRISE'];$e=new Feature_policy($m,$p,$full,true);
foreach($m['features']as$feature)if($feature['value_type']==='BOOLEAN')$check($e->allows($feature['code']),'full package '.$feature['code']);
define('BASEPATH',$root.'/system/');$route=[];require $root.'/application/config/routes.php';
foreach(['inventory/stock/warehouse','purchase/stock/warehouse','payroll/bonus','finance-reports/financial-estimation','pos-mobile/auth/login','master/component']as$url)$check($f->menu($url,$route)['code']==='FEATURE_UPGRADE_REQUIRED','alias locked '.$url);
foreach(['inventory_warehouse'=>['index','opening','lot','opname_monthly'],'inventory_division'=>['index','transfer','compare','lot'],'inventory'=>['fifo_audit','lot_audit'],'finance'=>['utang','piutang'],'procurement'=>['division_requests']]as$c=>$methods)foreach($methods as$method)$check($f->route($c,$method)['page'],'wrapper page uses locked HTML '.$c.'/'.$method);
foreach(['pos/cashier','pos/orders/payment/save','master/product','pos/reports/sales']as$url)$check($f->menu($url,$route)['allowed'],'alias basic '.$url);
foreach($p['groups']as$c=>$groups)foreach($groups as$features=>$methods)foreach(explode(' ',$methods)as$method){if($features==='@entity')continue;$check($e->route($c,$method)['allowed'],'all mapped full package '.$c.'/'.$method);}
// New public actions need a reviewed mapping. PHP constructors/private/inherited framework methods are not entry points.
foreach(glob($root.'/application/controllers/*.php')as$file){$c=strtolower(basename($file,'.php'));if(str_ends_with($c,'_bak'))continue;
 preg_match_all('/public\s+function\s+([a-zA-Z0-9_]+)\s*\(/',file_get_contents($file),$found);
 foreach($found[1]as$method){if(str_starts_with($method,'_'))continue;$args=$c==='master'?['product']:[];$check($e->route($c,strtolower($method),$args)['allowed'],'unmapped public action '.$c.'/'.$method);}}
echo json_encode(['status'=>'PASS','checks'=>$n,'contract'=>$p['contract'],'database_accessed'=>false])."\n";
