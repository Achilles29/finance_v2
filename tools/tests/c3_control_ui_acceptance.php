<?php
declare(strict_types=1);
// Actual login/forms on the disposable Control; never impersonates an operational owner.
require dirname(__DIR__).'/install/PrivateDeployment.php';umask(0077);$s=$argv[1]??'';
if(PHP_SAPI!=='cli'||posix_geteuid()!==0||preg_match('~\A/var/lib/finance-control-[0-9]{8}[.][A-Za-z0-9]{6}\z~D',$s)!==1||realpath($s)!==$s)throw new RuntimeException('DISPOSABLE_CONTROL_REQUIRED');
$f=PrivateDeployment::read($s.'/fixture.json');if(preg_match('/\Afinance_ctl_test_[a-f0-9]{12}\z/D',$f['database'])!==1||$f['base_url']!=='https://127.0.0.1:18444/')throw new RuntimeException('DISPOSABLE_DATABASE_ONLY');
$db=new PDO('mysql:host=127.0.0.1;dbname='.$f['database'].';charset=utf8mb4',$f['user'],$f['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$evidence=$s.'/ui-'.bin2hex(random_bytes(6));mkdir($evidence,0700);$checks=[];
$check=static function(bool$ok,string$label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=$label;echo 'PASS '.$label."\n";};
$call=static function(string$who,string$path,?array$post=null)use($s,$f,$evidence):array{if(preg_match('~\A[a-z0-9/-]+\z~D',$path)!==1)throw new RuntimeException('LOCAL_PATH_ONLY');$h=curl_init($f['base_url'].$path);$jar=$evidence.'/'.$who.'.cookies';curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CAINFO=>$s.'/tls.crt']);if($post!==null)curl_setopt($h,CURLOPT_POSTFIELDS,$post);$raw=curl_exec($h);$code=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);$n=(int)curl_getinfo($h,CURLINFO_HEADER_SIZE);curl_close($h);if(!is_string($raw))throw new RuntimeException('HTTPS_FAILED');return ['http'=>$code,'body'=>substr($raw,$n),'headers'=>substr($raw,0,$n)];};
$csrf=static function(array$r):string{if(!preg_match('/name="namua_csrf_token" value="([^"]+)"/',$r['body'],$m))throw new RuntimeException('CSRF_MISSING_HTTP_'.$r['http']);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');};
foreach(['owner','reviewer']as$who){$page=$call($who,'login');$r=$call($who,'login',['identifier'=>'practice.'.$who,'password'=>$f['owner_password'],'namua_csrf_token'=>$csrf($page)]);$check(in_array($r['http'],[302,303],true)&&str_contains($r['headers'],'dashboard'),'real '.$who.' login through Control form');}
$release=(int)$db->query("SELECT id FROM releases WHERE product_id=".$f['product_id']." AND status='PUBLISHED' ORDER BY id DESC LIMIT 1")->fetchColumn();if(!$release)throw new RuntimeException('DELIVERY_RELEASE_FIXTURE_REQUIRED');
$page=$call('reviewer','deployments/create');$r=$call('reviewer','deployments/create',['instance_id'=>$f['recovery']['record_id'],'release_id'=>$release,'action'=>'DEPLOY','namua_csrf_token'=>$csrf($page)]);
$deployment=(int)$db->query('SELECT id FROM deployments WHERE instance_id='.$f['recovery']['record_id'].' ORDER BY id DESC LIMIT 1')->fetchColumn();$path='deployments/'.$deployment;
$status=static fn():string=>(string)$db->query('SELECT status FROM deployments WHERE id='.$deployment)->fetchColumn();
$check(in_array($r['http'],[302,303],true)&&$status()==='PLANNED','reviewer creates deployment using actual UI form');
$page=$call('reviewer',$path);$call('reviewer',$path.'/submit',['namua_csrf_token'=>$csrf($page)]);$check($status()==='AWAITING_APPROVAL','submission keeps approval mandatory');
$page=$call('reviewer',$path);$call('reviewer',$path.'/approve',['namua_csrf_token'=>$csrf($page)]);$check($status()==='AWAITING_APPROVAL','same actor cannot approve own deployment');
$page=$call('owner',$path);$call('owner',$path.'/approve',['namua_csrf_token'=>$csrf($page)]);$check($status()==='APPROVED','independent owner approves deployment');
$page=$call('owner',$path);$call('owner',$path.'/execute',['namua_csrf_token'=>$csrf($page)]);$page=$call('owner',$path);preg_match('/ndi_[A-Za-z0-9_-]{48}/',$page['body'],$match);$token=$match[0]??'';
$check($status()==='RUNNING'&&strlen($token)===52,'UI discloses installation token once after approval');
$check(str_contains($page['body'],'Terbitkan token pengganti'),'recovery instructions and form visible for RUNNING deployment');
$before=(int)$db->query('SELECT COUNT(*) FROM deployment_install_tokens WHERE deployment_id='.$deployment)->fetchColumn();
$call('owner',$path.'/reissue',['namua_csrf_token'=>$csrf($page),'reason'=>'Simulasi sambungan unduhan terputus']);
$check((int)$db->query('SELECT COUNT(*) FROM deployment_install_tokens WHERE deployment_id='.$deployment)->fetchColumn()===$before,'reissue requires explicit installer-stopped confirmation');
$page=$call('owner',$path);$call('owner',$path.'/reissue',['namua_csrf_token'=>$csrf($page),'reason'=>'Simulasi sambungan unduhan terputus','installer_stopped'=>'1']);$page=$call('owner',$path);preg_match('/ndi_[A-Za-z0-9_-]{48}/',$page['body'],$match);$new=$match[0]??'';
$q=$db->prepare('SELECT revoked_at FROM deployment_install_tokens WHERE token_hash=?');$q->execute([hash('sha256',$token)]);
$check(strlen($new)===52&&$new!==$token&&$q->fetchColumn()!==null&&$status()==='RUNNING','replacement revokes old token and retains approved plan');
$r=$call('owner',$path.'/reissue',['reason'=>'Missing CSRF must fail','installer_stopped'=>'1']);$check($r['http']===403,'reissue rejects missing CSRF');
$r=$call('owner',$path.'/reissue');$check($r['http']===405,'reissue rejects GET');
PrivateDeployment::write($evidence.'/acceptance.json',['status'=>'PASS','checks'=>count($checks),'passed'=>$checks,'at'=>date(DATE_ATOM),'deployment_id'=>$deployment]);echo 'All '.count($checks).' Control UI checks passed. Evidence: '.$evidence.'/acceptance.json'."\n";
