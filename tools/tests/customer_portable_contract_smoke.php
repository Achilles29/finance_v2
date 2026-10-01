<?php
declare(strict_types=1);
require dirname(__DIR__).'/install/portable/PortableDatabase.php';
require dirname(__DIR__).'/release/CustomerReleaseProfile.php';
$n=0;$check=static function(bool $ok,string $m)use(&$n):void{if(!$ok)throw new RuntimeException('FAIL '.$m);$n++;echo 'PASS '.$m."\n";};
$reject=static function(callable $f,string $m)use($check):void{try{$f();}catch(Throwable $e){$check(true,$m);return;}$check(false,$m);};
$sql="-- comment ;\nSELECT 'a;b', \"c;d\", `x;y`;\nDELIMITER $$\nCREATE PROCEDURE p() BEGIN SELECT 'it''s;ok'; SELECT 2; END$$\nDELIMITER ;\n/* regular ; */ SELECT 3;\n/*!40101 SET NAMES utf8mb4 */;";
$parts=iterator_to_array(PortableDatabase::statements($sql));
$check(count($parts)===4,'native SQL executor separates statements but preserves procedure and quoted semicolons');
$check(str_contains($parts[1],"SELECT 'it''s;ok'; SELECT 2; END"),'stored procedure body not split or stripped');
$check($parts[3]==='/*!40101 SET NAMES utf8mb4 */','executable MySQL version comments preserved');
$reject(fn()=>iterator_to_array(PortableDatabase::statements("SELECT 'unterminated")),'unfinished quote rejected');
$reject(fn()=>iterator_to_array(PortableDatabase::statements('/* incomplete')),'unfinished comment rejected');
$reject(fn()=>iterator_to_array(PortableDatabase::statements("DELIMITER unsupported\nSELECT 1unsupported")),'unknown delimiter rejected');
$root=dirname(__DIR__,2);$catalog=json_decode(file_get_contents($root.'/tools/db/migration_catalog.json'),true);
foreach(array_merge([['path'=>'sql/baseline/2026-09-05_clean_install_schema.sql']],$catalog['migrations'])as$m){$check(iterator_count(PortableDatabase::statements(file_get_contents($root.'/'.$m['path'])))>0,'registered SQL parsed: '.$m['path']);}
$username='owner_'.bin2hex(random_bytes(4));
foreach(['','simple','0123456789abcd']as$password)$reject(fn()=>PortableDatabase::owner(['username'=>$username,'email'=>'','password'=>$password]),'weak owner password rejected');
$owner=['username'=>$username,'email'=>'owner@example.invalid','password'=>bin2hex(random_bytes(12)).'Az9!'];$check(PortableDatabase::owner($owner)===$owner,'strong unique owner input accepted');
$profile=CustomerReleaseProfile::fromRoot($root);$check($profile->version()===11,'new profile independently versioned');
foreach(CustomerLayout::MAP as$source=>$target){$check(CustomerLayout::source(CustomerLayout::target($source))===$source,'exact reversible package mapping '.$target);}
foreach(['config/customer.json','private/agent/agent.json','storage/license/runtime.json','private/delivery/credentials.json']as$p)$check(!$profile->allows($p),'release refuses local secret/runtime '.$p);
$check(CustomerLayout::target('assets/img/business-placeholder.svg')==='public/assets/img/business-placeholder.svg','approved static assets only move in build');
$check(CustomerLayout::target('application/controllers/Auth.php')==='application/controllers/Auth.php','business controller not moved/rewritten');
echo "All $n portable contract checks passed; Windows ACL/Task Scheduler NOT executed.\n";
