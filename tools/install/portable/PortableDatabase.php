<?php
declare(strict_types=1);
require_once __DIR__.'/PortableStore.php';
if (!defined('A5_MIGRATION_LIBRARY_ONLY')) define('A5_MIGRATION_LIBRARY_ONLY',true);
require_once dirname(__DIR__,2).'/db/migration_runner.php';
require_once dirname(__DIR__,2).'/db/ManagedMigrationProof.php';

/** Native PDO executor: no root, mysql option files, shell credential arguments, or replay of uncertain DDL. */
final class PortableDatabase
{
    public static function connect(array $db): PDO
    {
        try {
            $dsn=($db['socket']??'')!==''?'unix_socket='.$db['socket']:'host='.$db['host'].';port='.$db['port'];
            return new PDO('mysql:'.$dsn.';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['password'],
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
        } catch (Throwable $e) {
            $number=$e instanceof PDOException?(int)($e->errorInfo[1]??0):0;
            throw new RuntimeException([1049=>'DATABASE_NOT_FOUND',1045=>'DATABASE_CREDENTIAL_REJECTED',1044=>'DATABASE_ACCESS_DENIED',1698=>'DATABASE_CREDENTIAL_REJECTED'][$number]??'DATABASE_CONNECTION_FAILED');
        }
    }

    public static function emptyDatabase(PDO $pdo): void
    {
        foreach(['tables'=>['table_schema'],'routines'=>['routine_schema'],'events'=>['event_schema']] as $table=>$columns) {
            if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.'.$table.' WHERE '.$columns[0].'=DATABASE()')->fetchColumn()!==0)throw new RuntimeException('DATABASE_NOT_EMPTY');
        }
    }

    public static function version(PDO $pdo): void
    {
        if(!preg_match('/\A(?:5\.5\.5-)?(10\.11\.\d+)-MariaDB/',$pdo->query('SELECT VERSION()')->fetchColumn()))throw new RuntimeException('DATABASE_VERSION_UNSUPPORTED');
    }

    /** Metadata/connection only. No CREATE/INSERT/UPDATE/DROP and no root database credential. */
    public static function probe(array $db): array
    {
        $pdo=self::connect($db);self::version($pdo);self::emptyDatabase($pdo);
        return ['connection'=>true,'empty'=>true,'database_version_supported'=>true];
    }

    /** Understand mysql-client DELIMITER, quotes and comments; never split a stored procedure at internal ';'. */
    public static function statements(string $sql): Generator
    {
        $delimiter=';';$buffer='';$quote='';$n=strlen($sql);
        for($i=0;$i<$n;$i++) {
            $c=$sql[$i];$next=$sql[$i+1]??'';
            if($quote!=='') {
                $buffer.=$c;
                if($c==='\\' && $quote!=='`' && $i+1<$n){$buffer.=$sql[++$i];continue;}
                if($c===$quote){if($next===$quote){$buffer.=$sql[++$i];}else{$quote='';}}
                continue;
            }
            if($c==="'" || $c==='"' || $c==='`'){$quote=$c;$buffer.=$c;continue;}
            if($c==='#' || ($c==='-' && $next==='-' && ctype_space($sql[$i+2]??' '))) {
                $end=strpos($sql,"\n",$i);$i=$end===false?$n:$end;$buffer.="\n";continue;
            }
            if($c==='/' && $next==='*') {
                $end=strpos($sql,'*/',$i+2);if($end===false)throw new RuntimeException('SQL_COMMENT_INVALID');
                $buffer.=($sql[$i+2]??'')==='!'?substr($sql,$i,$end+2-$i):' ';
                $i=$end+1;continue;
            }
            if(trim($buffer)==='' && ($i===0 || $sql[$i-1]==="\n") && preg_match('/\A[ \t]*DELIMITER[ \t]+([^\s]+)[ \t]*\r?(?:\n|$)/i',substr($sql,$i),$m)) {
                if(!in_array($m[1],[';','$$','//'],true))throw new RuntimeException('SQL_DELIMITER_UNSUPPORTED');
                $delimiter=$m[1];$i+=strlen($m[0])-1;continue;
            }
            if(substr($sql,$i,strlen($delimiter))===$delimiter) {
                if(trim($buffer)!=='')yield trim($buffer);
                $buffer='';$i+=strlen($delimiter)-1;continue;
            }
            $buffer.=$c;
        }
        if($quote!=='')throw new RuntimeException('SQL_QUOTE_INVALID');
        if(trim($buffer)!=='')yield trim($buffer);
    }

    private static function script(PDO $pdo,string $sql): void
    {
        foreach(self::statements($sql) as $statement) {
            $q=$pdo->query($statement);
            if($q){do{if($q->columnCount())$q->fetchAll(PDO::FETCH_NUM);}while($q->nextRowset());$q->closeCursor();}
        }
    }
    public static function owner(array $owner): array
    {
        if(array_diff(array_keys($owner),['username','email','password']) || count($owner)!==3
            || !is_string($owner['username']) || !preg_match('/\A[A-Za-z][A-Za-z0-9._-]{2,59}\z/D',$owner['username'])
            || !is_string($owner['email']) || ($owner['email']!=='' && (!filter_var($owner['email'],FILTER_VALIDATE_EMAIL)||strlen($owner['email'])>150)))throw new RuntimeException('OWNER_INVALID');
        $p=$owner['password'];
        if(!is_string($p) || strlen($p)<12 || strlen($p)>72 || stripos($p,$owner['username'])!==false
            || (int)preg_match('/[a-z]/',$p)+(int)preg_match('/[A-Z]/',$p)+(int)preg_match('/[0-9]/',$p)+(int)preg_match('/[^A-Za-z0-9]/',$p)<3)throw new RuntimeException('OWNER_PASSWORD_WEAK');
        return $owner;
    }

    public static function install(string $root,array $db,array $owner,string $releaseHash,?callable $progress=null): array
    {
        PortableStore::writer($root);self::owner($owner);
        $store=new PortableStore($root,'private');$pdo=self::connect($db);
        $lock='finance_clean_install_'.substr(hash('sha256',$db['name']),0,40);
        $q=$pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)throw new RuntimeException('DATABASE_INSTALL_BUSY');
        try {
            self::version($pdo);
            $binding=hash('sha256',json_encode([$db['host'],$db['port'],$db['socket']??'',$db['name'],$db['user'],$releaseHash],JSON_THROW_ON_ERROR));
            $state=$store->exists('database.json')?$store->read('database.json'):null;
            if($state!==null && ($state['binding']??'')!==$binding)throw new RuntimeException('DATABASE_ATTEMPT_MISMATCH');
            if($state===null) {
                self::emptyDatabase($pdo);
                $state=['binding'=>$binding,'phase'=>'READY','completed'=>[],'owner'=>hash('sha256',json_encode([$owner['username'],$owner['email']]))];
            }
            if(($state['owner']??'')!==hash('sha256',json_encode([$owner['username'],$owner['email']])))throw new RuntimeException('OWNER_ATTEMPT_MISMATCH');
            if(($state['phase']??'')==='RUNNING')throw new RuntimeException('DATABASE_PARTIAL_REVIEW_REQUIRED');
            $policy=json_decode(file_get_contents($root.'/tools/db/clean_install_baseline_policy.json'),true,32,JSON_THROW_ON_ERROR);
            $catalog=a5_validate_catalog($root);$plan=a5_plan($catalog,'clean_install');
            $scripts=[['id'=>'baseline','path'=>$policy['schema']['path'],'sha256'=>$policy['schema']['sha256']]];
            $scripts=array_merge($scripts,$plan);
            foreach($scripts as $script) {
                $id=$script['id'];$path=$root.'/'.$script['path'];CustomerPlatform::path($root,$path);
                if(!hash_equals($script['sha256'],hash_file('sha256',$path)))throw new RuntimeException('DATABASE_SQL_HASH_INVALID');
                if(isset($state['completed'][$id])) {
                    if($state['completed'][$id]!==$script['sha256'])throw new RuntimeException('DATABASE_JOURNAL_DRIFT');
                    if($id!=='baseline')self::ledger($pdo,$script);
                    if(ManagedMigrationProof::state($root,$id,static fn(string $s)=>$pdo->query($s)->fetchColumn())==='ABSENT')throw new RuntimeException('MIGRATION_REGISTERED_STRUCTURE_MISSING');
                    continue;
                }
                $proof=ManagedMigrationProof::state($root,$id,static fn(string $s)=>$pdo->query($s)->fetchColumn());
                $state['phase']='RUNNING';$state['current']=$id;$store->write('database.json',$state);
                if($proof!=='VERIFIED')self::script($pdo,file_get_contents($path));
                if($proof!==null && ManagedMigrationProof::state($root,$id,static fn(string $s)=>$pdo->query($s)->fetchColumn())!=='VERIFIED')throw new RuntimeException('MIGRATION_POSTCHECK_FAILED');
                if($id!=='baseline') {
                    $insert=$pdo->prepare('INSERT INTO sys_schema_migration (migration_id,filename,checksum_sha256,catalog_version,tool_version,classification,policies,applied_by) VALUES (?,?,?,1,?,?,?,?)');
                    $insert->execute([$id,$script['path'],$script['sha256'],A5_MIGRATION_TOOL_VERSION,$script['classification'],implode(',',$script['policies']),'portable_installer']);
                }
                $state['completed'][$id]=$script['sha256'];$state['phase']='READY';$store->write('database.json',$state);
                if($progress)$progress(count($state['completed']),count($scripts)+1);
            }
            if(!isset($state['owner_id'])) {
                $state['phase']='RUNNING';$state['current']='owner';$store->write('database.json',$state);
                self::health($pdo,$plan,$policy,false);
                $pdo->beginTransaction();
                if((int)$pdo->query('SELECT COUNT(*) FROM auth_user')->fetchColumn()!==0 || (int)$pdo->query('SELECT COUNT(*) FROM auth_user_role')->fetchColumn()!==0)throw new RuntimeException('OWNER_EXISTS');
                $role=$pdo->query("SELECT id FROM auth_role WHERE BINARY role_code='SUPERADMIN' AND is_active=1 AND division_scope_id IS NULL FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
                if(count($role)!==1)throw new RuntimeException('OWNER_ROLE_INVALID');
                $q=$pdo->prepare('INSERT INTO auth_user (employee_id,username,email,password_hash,is_active,created_at) VALUES (NULL,?,?,?,1,CURRENT_TIMESTAMP)');
                $q->execute([$owner['username'],$owner['email']===''?null:$owner['email'],password_hash($owner['password'],PASSWORD_BCRYPT,['cost'=>12])]);
                $id=(int)$pdo->lastInsertId();$q=$pdo->prepare('INSERT INTO auth_user_role (user_id,role_id,assigned_by,assigned_at) VALUES (?,?,?,CURRENT_TIMESTAMP)');$q->execute([$id,$role[0],$id]);$pdo->commit();
                $state['owner_id']=$id;$state['phase']='READY';$store->write('database.json',$state);
            }
            $health=self::health($pdo,$plan,$policy,true);
            $state['phase']='COMPLETE';$store->write('database.json',$state);
            return $health+['migrations'=>array_column($plan,'id'),'owner_id'=>$state['owner_id']];
        } finally {if($pdo->inTransaction())$pdo->rollBack();$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }

    private static function ledger(PDO $pdo,array $migration): void
    {
        $q=$pdo->prepare('SELECT filename,checksum_sha256,catalog_version,tool_version FROM sys_schema_migration WHERE migration_id=?');$q->execute([$migration['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row || $row['filename']!==$migration['path'] || $row['checksum_sha256']!==$migration['sha256'] || (int)$row['catalog_version']!==1 || $row['tool_version']!==A5_MIGRATION_TOOL_VERSION)throw new RuntimeException('DATABASE_LEDGER_DRIFT');
    }
    public static function health(PDO $pdo,array $plan,array $policy,bool $owner): array
    {
        foreach($policy['schema']['required_tables'] as $table) {
            $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?');$q->execute([$table]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('DATABASE_REQUIRED_TABLE_MISSING');
        }
        foreach($plan as $script)self::ledger($pdo,$script);
        if((int)$pdo->query('SELECT COUNT(*) FROM sys_schema_migration')->fetchColumn()!==count($plan))throw new RuntimeException('DATABASE_LEDGER_DRIFT');
        foreach($policy['seed']['post_apply_counts'] as $table=>$count) {
            if(!preg_match('/\A[a-z_]+\z/D',$table))throw new RuntimeException('SEED_TABLE_INVALID');
            $expected=$owner && in_array($table,['auth_user','auth_user_role'],true)?1:$count;
            if((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==$expected)throw new RuntimeException('DATABASE_SEED_DRIFT');
        }
        if($owner && (int)$pdo->query("SELECT COUNT(*) FROM auth_user u JOIN auth_user_role ur ON ur.user_id=u.id JOIN auth_role r ON r.id=ur.role_id WHERE u.is_active=1 AND r.role_code='SUPERADMIN' AND r.is_active=1")->fetchColumn()!==1)throw new RuntimeException('OWNER_POSTCHECK_FAILED');
        return ['status'=>'ok','migration_count'=>count($plan),'reference_seed'=>'exact','owner_verified'=>$owner];
    }
}
