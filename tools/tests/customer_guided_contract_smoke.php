<?php
declare(strict_types=1);
require dirname(__DIR__).'/install/portable/LinuxPreparation.php';
$n=0;$check=static function(bool $ok,string $m)use(&$n):void{if(!$ok)throw new RuntimeException('FAIL '.$m);$n++;echo "PASS $m\n";};
$reject=static function(callable $f,string $code)use($check):void{try{$f();}catch(Throwable $e){$check($e->getMessage()===$code,$code);return;}$check(false,$code);};
$root=dirname(__DIR__,2);$target='/opt/customer finance';$php='/usr/bin/php8.1';
$old="MAILTO=admin@example.invalid\n0 4 * * * /opt/unrelated/backup\n";
$new=LinuxPreparation::mergeCron($old,$target,$php);
$check(str_starts_with($new,$old),'other schedules preserved byte-for-byte');
$check(LinuxPreparation::mergeCron($new,$target,$php)===$new,'second preparation cannot duplicate schedules');
$check(substr_count($new,'* * * * *')===3,'installer, license and heartbeat are separate schedules');
$check(str_contains($new,"'/opt/customer finance/tools/install/portable/finance_setup.php'"),'spaces safely quoted');
$reject(fn()=>LinuxPreparation::cronBlock('/opt/finance%bad',$php),'SCHEDULER_PATH_INVALID');
$reject(fn()=>LinuxPreparation::cronBlock("/opt/finance\nroot",$php),'SCHEDULER_PATH_INVALID');
$reject(fn()=>LinuxPreparation::mergeCron($new.$new,$target,$php),'SCHEDULER_BLOCK_AMBIGUOUS');
$reject(fn()=>SetupService::id('../secret'),'SETUP_REQUEST_INVALID');
$check(SetupService::code(new RuntimeException('password=do-not-show'))==='INSTALL_STEP_FAILED','unexpected detail never sent to UI');
$config=['base_url'=>'https://finance.example.invalid/','database'=>['host'=>'127.0.0.1','port'=>3306,'socket'=>'','name'=>'customer_test','user'=>'fixture_'.bin2hex(random_bytes(4)),'password'=>bin2hex(random_bytes(24))]];
$prepared=PortableInstaller::configuration($config);
$check($prepared['runtime']['directory']==='storage'&&strlen($prepared['encryption_key'])>=32,'runtime inside package and key generated per installation');
foreach(['DATABASE_NOT_FOUND','DATABASE_CREDENTIAL_REJECTED','DATABASE_NOT_EMPTY','SERVICE_UNAVAILABLE','SETUP_PERMISSION_EXPIRED']as$code)
    $check(!str_contains(SetupUi::message($code),'Langkah ini belum berhasil'),'specific customer action: '.$code);
$profile=json_decode(file_get_contents($root.'/tools/release/customer_clean_profile.json'),true);
$check($profile['profile_version']===11&&$profile['setup_contract']==='FINANCE_GUIDED_SETUP_V1'&&$profile['installation_permission_policy']==='UNTIL_USED_OR_REVOKED','new signed guided contract without installation deadline');
foreach(['LinuxPreparation.php','SetupService.php','prepare.php','prepare.sh','setup.js','setup.css']as$file)
    $check(in_array('tools/install/portable/'.$file,$profile['code_files'],true),'exact signed inventory includes '.$file);
$check(!in_array('tools/tests/customer_guided_control_fixture.php',$profile['code_files'],true),'synthetic issuer never packaged');
echo json_encode(['status'=>'PASS','checks'=>$n,'control'=>'NOT_CONTACTED','database'=>'NOT_CONTACTED'])."\n";
