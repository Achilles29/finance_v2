<?php
declare(strict_types=1);
require dirname(__DIR__).'/release/CustomerReleaseProfile.php';
$root=dirname(__DIR__,2);$p=json_decode(file_get_contents($root.'/'.CustomerReleaseProfile::PATH),true);
$profile=CustomerReleaseProfile::fromRoot($root);$policy=ReleasePackagePolicy::fromFile($root.'/tools/release/package_policy.json');
$n=0;$check=static function(bool $ok,string $label)use(&$n):void{if(!$ok)throw new RuntimeException($label);$n++;};
$paths=array_unique(array_merge($p['files'],$p['code_files'],array_keys($p['static_sha256']),array_keys($p['sql_sha256'])));$entries=[];
foreach($paths as$path)$entries[]=['path'=>CustomerLayout::target($path),'sha256'=>hash_file('sha256',$root.'/'.$path)];
$profile->audit($entries);$check(true,'Complete profile includes both signed root guides');
foreach(['MULAI-DI-SINI.html','MULAI-DI-SINI.txt']as$file){
    $check($policy->included($file)&&!$policy->denied($file)&&$profile->allows($file),'Exact root guide allowlisted');
    $check(hash_file('sha256',$root.'/'.$file)===$p['static_sha256'][$file],'Guide pinned by hash');
    $check(CustomerLayout::target($file)===$file,'Guide remains at application root');
    foreach(['missing','tampered']as$kind){$bad=[];foreach($entries as$e){if($e['path']===$file){if($kind==='missing')continue;$e['sha256']=str_repeat('0',64);}$bad[]=$e;}
        try{$profile->audit($bad);$blocked=false;}catch(RuntimeException$e){$blocked=true;}$check($blocked,'Missing/modified root guide rejected');}
}
$dom=new DOMDocument();@$dom->loadHTML(file_get_contents($root.'/MULAI-DI-SINI.html'));$xp=new DOMXPath($dom);
$check($xp->query('//li[@class="step"]')->length===5,'Five readable steps');
$check($dom->getElementsByTagName('script')->length===0&&$dom->getElementsByTagName('form')->length===0,'Offline guide has no executable script or credential form');
foreach(['sudo sh tools/install/portable/prepare.sh','finance/public','finance/config/customer.json','finance/private/delivery/KODE-SETUP.txt']as$text)
    foreach(['MULAI-DI-SINI.html','MULAI-DI-SINI.txt']as$file)$check(str_contains(file_get_contents($root.'/'.$file),$text),'HTML and text guide agree');
$check(!$profile->allows('MULAI-DI-SINI.php')&&!$profile->allows('private/delivery/credentials.json'),'No arbitrary PHP/credential exemption');
echo "Root customer guide: $n checks PASS; no database or Control access.\n";
