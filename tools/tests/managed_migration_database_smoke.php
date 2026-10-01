<?php
declare(strict_types=1);
// Always creates its OWN non-networked MariaDB. No connection arguments, no active configuration.
require_once dirname(__DIR__).'/install/PrivateDeployment.php';
require_once dirname(__DIR__).'/install/portable/PortableDatabase.php';
require_once dirname(__DIR__).'/db/ManagedMigrationProof.php';
if(PHP_SAPI!=='cli'||posix_geteuid()!==0||!in_array('--disposable',$argv,true))throw new RuntimeException('EXPLICIT_DISPOSABLE_TEST_REQUIRED');
$root=dirname(__DIR__,2);$base='/var/lib/finance-migrations-'.bin2hex(random_bytes(8));mkdir($base,0700);$process=null;
$execute=static function(PDO $db,string $sql):void {foreach(PortableDatabase::statements($sql) as $s){$q=$db->query($s);if($q){do{if($q->columnCount())$q->fetchAll();}while($q->nextRowset());$q->closeCursor();}}};
$remove=static function(string $p)use(&$remove):void{if(is_dir($p)&&!is_link($p)){foreach(new FilesystemIterator($p)as$f)$remove($f->getPathname());rmdir($p);}else unlink($p);};
try {
    $mysql='/www/server/mysql';
    PrivateDeployment::run([$mysql.'/scripts/mariadb-install-db','--no-defaults','--basedir='.$mysql,'--datadir='.$base.'/data','--auth-root-authentication-method=normal','--skip-test-db'],$base.'/init.log',180);
    $socket=$base.'/db.sock';
    $process=proc_open([$mysql.'/bin/mariadbd','--no-defaults','--user=root','--basedir='.$mysql,'--datadir='.$base.'/data','--socket='.$socket,'--pid-file='.$base.'/pid','--log-error='.$base.'/error.log','--skip-networking','--skip-log-bin','--innodb-buffer-pool-size=64M'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    for($n=0;$n<150;$n++){try{$pdo=new PDO('mysql:unix_socket='.$socket.';dbname=mysql','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);break;}catch(Throwable $e){usleep(100000);}}
    if(!isset($pdo))throw new RuntimeException('DISPOSABLE_DATABASE_START_FAILED');
    $pdo->exec('CREATE DATABASE migration_reference CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$pdo->exec('USE migration_reference');
    $execute($pdo,file_get_contents($root.'/sql/baseline/2026-09-05_clean_install_schema.sql'));
    $catalog=a5_validate_catalog($root);$plan=a5_plan($catalog,'clean_install');
    foreach($plan as $m)$execute($pdo,file_get_contents($root.'/'.$m['path']));
    $read=static fn(string $sql)=>$pdo->query($sql)->fetchColumn();
    if(in_array('--capture-proof',$argv,true)) {echo json_encode(['contract'=>ManagedMigrationProof::CONTRACT,'engine'=>'MariaDB 10.11','source'=>'immutable SQL on newly initialized disposable database','schemas'=>ManagedMigrationProof::snapshots($read)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";return;}
    foreach(ManagedMigrationProof::definitions() as $id=>$d)if(ManagedMigrationProof::state($root,$id,$read)!=='VERIFIED')throw new RuntimeException('PROOF_FAILED:'.$id);
    echo "PASS: disposable reference schema and all five complete manual installations verified\n";
    $option=$base.'/client.cnf';file_put_contents($option,"[client]\nuser=root\nsocket=$socket\nprotocol=socket\n");chmod($option,0600);
    // Populate the earlier ledger ONLY by executing the old registered migrations on a fresh fixture.
    $pdo->exec('CREATE DATABASE migration_old CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$pdo->exec('USE migration_old');
    $execute($pdo,file_get_contents($root.'/sql/baseline/2026-09-05_clean_install_schema.sql'));
    foreach($plan as $m) {
        if(isset(ManagedMigrationProof::definitions()[$m['id']]))continue;
        $execute($pdo,file_get_contents($root.'/'.$m['path']));
        $q=$pdo->prepare('INSERT INTO sys_schema_migration(migration_id,filename,checksum_sha256,catalog_version,tool_version,classification,policies,applied_by) VALUES(?,?,?,1,?,?,?,?)');
        $q->execute([$m['id'],$m['path'],$m['sha256'],A5_MIGRATION_TOOL_VERSION,$m['classification'],implode(',',$m['policies']),'disposable_old_fixture']);
    }
    $assert=static function(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo 'PASS: '.$name."\n";};
    foreach(ManagedMigrationProof::definitions() as $id=>$d) {
        // Journal-assistant prerequisites arrive with 15a; absence is verified during ordered apply.
        if(str_contains($id,'15b-'))continue;
        $assert(ManagedMigrationProof::state($root,$id,$read)==='ABSENT','old database: pending '.$id);
    }
    $before=$pdo->query('SELECT COUNT(*) FROM auth_user')->fetchColumn();
    $expectedUpgradeCount=count(a5_plan($catalog,'upgrade'));
    $r=a5_apply($catalog,$root,'upgrade',$option,'migration_old');$assert($r['applied']===6&&$r['skipped']===$expectedUpgradeCount-6,'real streaming upgrade applies five manual migrations and one POS correction');
    $snapshot=static function(PDO $db):array{$r=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$t)$r[$t]=$db->query('CHECKSUM TABLE `'.$t.'` EXTENDED')->fetch(PDO::FETCH_NUM)[1];return $r;};
    $hashes=$snapshot($pdo);$r=a5_apply($catalog,$root,'upgrade',$option,'migration_old');
    $assert($r['applied']===0&&$r['skipped']===$expectedUpgradeCount&&$hashes===$snapshot($pdo),'upgrade replay preserves every table checksum and ledger');
    $assert($before===$pdo->query('SELECT COUNT(*) FROM auth_user')->fetchColumn(),'upgrade does not create customer accounts');
    // A separate manually-applied fixture has no ledger. Copy verified EARLIER records only;
    // the five new records must be written by the real verifier, not this test.
    $pdo->exec('INSERT INTO migration_reference.sys_schema_migration SELECT * FROM migration_old.sys_schema_migration WHERE migration_id NOT IN ('.implode(',',array_map([$pdo,'quote'],array_keys(ManagedMigrationProof::definitions()))).')');
    $pdo->exec('USE migration_reference');
    $pdo->exec("UPDATE fin_gl_account SET name='Customer renamed cash account' WHERE code='1100'");
    $pdo->exec("UPDATE auth_role_permission a JOIN sys_page p ON p.id=a.page_id SET a.can_view=0 WHERE p.page_code='system.guide.server'");
    $manual=$snapshot($pdo);$r=a5_apply($catalog,$root,'upgrade',$option,'migration_reference');$after=$snapshot($pdo);unset($manual['sys_schema_migration'],$after['sys_schema_migration']);
    $assert($r['applied']===6&&$manual===$after,'manual adoption changes ONLY ledger; preserves custom account names and RBAC');
    $r=a5_apply($catalog,$root,'upgrade',$option,'migration_reference');$assert($r['applied']===0,'manual-adoption replay does not duplicate references');
    // Drift only inside the disposable database; never attempt automatic repair.
    $pdo->exec('ALTER TABLE fin_gl_mapping MODIFY cashflow_class VARCHAR(17) NOT NULL');
    $ledger=$pdo->query('SELECT COUNT(*) FROM sys_schema_migration')->fetchColumn();
    try{a5_apply($catalog,$root,'upgrade',$option,'migration_reference');throw new LogicException('DRIFT_ACCEPTED');}
    catch(A5MigrationFailure $e){$assert(str_contains($e->getMessage(),'fin_gl_mapping')&&$ledger===$pdo->query('SELECT COUNT(*) FROM sys_schema_migration')->fetchColumn(),'schema drift fails specifically without new ledger entries');}
    $pdo->exec('ALTER TABLE fin_gl_mapping MODIFY cashflow_class VARCHAR(16) NOT NULL');
    $pdo->exec("DELETE FROM sys_schema_migration WHERE migration_id='2026-09-14c-finance-allocation-bank-review'");
    $pdo->exec('RENAME TABLE fin_plan_allocation TO fixture_missing_allocation');
    $ledger=$pdo->query('SELECT COUNT(*) FROM sys_schema_migration')->fetchColumn();
    try{a5_apply($catalog,$root,'upgrade',$option,'migration_reference');throw new LogicException('PARTIAL_ACCEPTED');}
    catch(A5MigrationFailure $e){$assert(str_contains($e->getMessage(),'MIGRATION_PARTIAL_REVIEW_REQUIRED')&&$ledger===$pdo->query('SELECT COUNT(*) FROM sys_schema_migration')->fetchColumn(),'partial manual schema stops before replay or false adoption');}
    echo "PASS: MariaDB ".$pdo->query('SELECT VERSION()')->fetchColumn()."; active database never accessed\n";
}finally {if(is_resource($process)){proc_terminate($process);proc_close($process);}$remove($base);}
