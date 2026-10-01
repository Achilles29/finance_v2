<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/install/portable/PortableDatabase.php';
require_once __DIR__.'/UpdateFiles.php';

/** Upgrade only: never calls the empty-database installer or creates an owner. */
final class UpdateDatabase
{
    public static function plan(PDO $pdo,string $candidate): array
    {
        PortableDatabase::version($pdo);$catalog=a5_validate_catalog($candidate);$scripts=a5_plan($catalog,'upgrade');
        $known=array_column($catalog['migrations'],null,'id');$ledger=$pdo->query('SELECT migration_id,filename,checksum_sha256 FROM sys_schema_migration ORDER BY migration_id')->fetchAll(PDO::FETCH_ASSOC);
        $seen=[];
        foreach($ledger as$row){$id=$row['migration_id'];$m=$known[$id]??null;
            if(!$m||$row['filename']!==$m['path']||$row['checksum_sha256']!==$m['sha256'])throw new RuntimeException('UPDATE_LEDGER_DRIFT');$seen[$id]=true;
        }
        $pending=[];
        foreach($scripts as$m){
            $proof=ManagedMigrationProof::state($candidate,$m['id'],static fn(string $sql)=>$pdo->query($sql)->fetchColumn());
            if(isset($seen[$m['id']])){if($proof==='ABSENT')throw new RuntimeException('UPDATE_REGISTERED_SCHEMA_MISSING');continue;}
            // Old unmanaged SQL must be reviewed. Only explicit structure proofs allow automatic adoption/execution.
            if($proof===null)throw new RuntimeException('UPDATE_UNMANAGED_MIGRATION_REVIEW_REQUIRED');
            $pending[]=$m+['proof'=>$proof];
        }
        return $pending;
    }
    private static function identifier(string $name): string
    {
        if(!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D',$name))throw new RuntimeException('UPDATE_DATABASE_IDENTIFIER_INVALID');return '`'.$name.'`';
    }
    public static function ledgerHash(PDO $pdo): string
    {
        return hash('sha256',json_encode($pdo->query('SELECT * FROM sys_schema_migration ORDER BY migration_id')->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR));
    }
    /** Private streaming backup: exact schema and lossless row values, no credentials in the file. */
    public static function backup(PDO $pdo,string $path): array
    {
        if(file_exists($path)||is_link($path))throw new RuntimeException('UPDATE_BACKUP_EXISTS');
        $tables=$pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach($tables as$t)if($t[1]!=='BASE TABLE')throw new RuntimeException('UPDATE_BACKUP_CUSTOM_OBJECT_REVIEW_REQUIRED');
        foreach(['triggers'=>'trigger_schema','routines'=>'routine_schema','events'=>'event_schema']as$table=>$column)
            if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.'.$table.' WHERE '.$column.'=DATABASE()')->fetchColumn())throw new RuntimeException('UPDATE_BACKUP_CUSTOM_OBJECT_REVIEW_REQUIRED');
        // Non-transactional tables cannot be protected by this consistent-snapshot contract.
        if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND engine<>'InnoDB'")->fetchColumn())throw new RuntimeException('UPDATE_BACKUP_ENGINE_UNSUPPORTED');
        $mask=umask(0077);$h=fopen($path,'xb');umask($mask);if(!$h)throw new RuntimeException('UPDATE_BACKUP_FAILED');
        $rows=0;$count=0;$digest=hash_init('sha256');
        $write=static function(array $line)use($h,$digest):void{$raw=json_encode($line,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            if(strlen($raw)>33554432||fwrite($h,$raw)!==strlen($raw))throw new RuntimeException('UPDATE_BACKUP_FAILED');hash_update($digest,$raw);};
        try{
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
            $write(['format'=>'FINANCE_DATABASE_BACKUP_V1','database'=>hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn())]);
            foreach($tables as[$table]){
                $id=self::identifier($table);$create=$pdo->query('SHOW CREATE TABLE '.$id)->fetch(PDO::FETCH_NUM)[1];
                $columns=$pdo->query('SHOW FULL COLUMNS FROM '.$id)->fetchAll(PDO::FETCH_ASSOC);$names=[];
                foreach($columns as$c)if(stripos($c['Extra'],'GENERATED')===false)$names[]=$c['Field'];
                $write(['table'=>$table,'create_base64'=>base64_encode($create),'columns'=>$names]);$count++;
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
                $q=$pdo->query('SELECT '.implode(',',array_map([self::class,'identifier'],$names)).' FROM '.$id);
                while($row=$q->fetch(PDO::FETCH_NUM)){$write(['row'=>array_map(static fn($v)=>$v===null?null:base64_encode((string)$v),$row)]);$rows++;}
                $q->closeCursor();$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);$write(['end_table'=>$table]);
            }
            $pdo->commit();$write(['end_backup'=>true,'tables'=>$count,'rows'=>$rows]);
            if(!fflush($h)||!fsync($h))throw new RuntimeException('UPDATE_BACKUP_FAILED');
        }finally{if($pdo->inTransaction())$pdo->rollBack();fclose($h);}
        $hash=hash_final($digest);if(!hash_equals($hash,hash_file('sha256',$path)))throw new RuntimeException('UPDATE_BACKUP_VERIFY_FAILED');
        return ['sha256'=>$hash,'tables'=>$count,'rows'=>$rows,'bytes'=>filesize($path)];
    }
    /** Recovery/test only. Refuses any nonempty target; never an automatic production rollback. */
    public static function restoreEmpty(PDO $pdo,string $path,string $hash): void
    {
        PortableDatabase::emptyDatabase($pdo);
        if(!is_file($path)||is_link($path)||hash_file('sha256',$path)!==$hash)throw new RuntimeException('UPDATE_BACKUP_VERIFY_FAILED');
        $h=fopen($path,'rb');$table=null;$query=null;$ended=false;$rows=0;$tables=0;
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try{
            $first=json_decode(fgets($h),true,16,JSON_THROW_ON_ERROR);if(($first['format']??'')!=='FINANCE_DATABASE_BACKUP_V1')throw new RuntimeException('UPDATE_BACKUP_INVALID');
            while(($line=fgets($h,33554433))!==false){
                $v=json_decode($line,true,16,JSON_THROW_ON_ERROR);
                if(isset($v['table'])){
                    if($table!==null)throw new RuntimeException('UPDATE_BACKUP_INVALID');$table=$v['table'];self::identifier($table);
                    $sql=base64_decode($v['create_base64'],true);if(!is_string($sql)||!str_starts_with($sql,'CREATE TABLE `'.$table.'`'))throw new RuntimeException('UPDATE_BACKUP_INVALID');
                    $pdo->exec($sql);$names=array_map([self::class,'identifier'],$v['columns']);
                    $query=$pdo->prepare('INSERT INTO '.self::identifier($table).' ('.implode(',',$names).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')');$tables++;
                }elseif(isset($v['row'])){
                    if(!$query||count($v['row'])!==count($names))throw new RuntimeException('UPDATE_BACKUP_INVALID');
                    $row=[];foreach($v['row']as$x){$decoded=$x===null?null:base64_decode($x,true);if($decoded===false)throw new RuntimeException('UPDATE_BACKUP_INVALID');$row[]=$decoded;}$query->execute($row);$rows++;
                }elseif(isset($v['end_table'])){if($table!==$v['end_table'])throw new RuntimeException('UPDATE_BACKUP_INVALID');$table=null;$query=null;
                }elseif(($v['end_backup']??false)===true){if($table!==null||$v['tables']!==$tables||$v['rows']!==$rows||fgetc($h)!==false)throw new RuntimeException('UPDATE_BACKUP_INVALID');$ended=true;break;
                }else throw new RuntimeException('UPDATE_BACKUP_INVALID');
            }
            if(!$ended)throw new RuntimeException('UPDATE_BACKUP_INVALID');
        }finally{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');fclose($h);}
    }
    public static function apply(PDO $pdo,string $candidate,array $pending,callable $journal): string
    {
        foreach($pending as$m){
            $path=$candidate.'/'.$m['path'];if(is_link($path)||hash_file('sha256',$path)!==$m['sha256'])throw new RuntimeException('UPDATE_SQL_CHANGED');
            $proof=ManagedMigrationProof::state($candidate,$m['id'],static fn(string $s)=>$pdo->query($s)->fetchColumn());
            if(!in_array($proof,['ABSENT','VERIFIED'],true))throw new RuntimeException('UPDATE_MIGRATION_REVIEW_REQUIRED');
            $journal($m['id'],'BEFORE'); // Persist BEFORE DDL. A crash is held for review, never blindly replayed.
            if($proof==='ABSENT'){
                foreach(PortableDatabase::statements(file_get_contents($path))as$sql){$q=$pdo->query($sql);if($q){do{if($q->columnCount())$q->fetchAll(PDO::FETCH_NUM);}while($q->nextRowset());$q->closeCursor();}}
            }
            if(ManagedMigrationProof::state($candidate,$m['id'],static fn(string $s)=>$pdo->query($s)->fetchColumn())!=='VERIFIED')throw new RuntimeException('UPDATE_MIGRATION_POSTCHECK_FAILED');
            $q=$pdo->prepare('INSERT INTO sys_schema_migration (migration_id,filename,checksum_sha256,catalog_version,tool_version,classification,policies,applied_by) VALUES (?,?,?,1,?,?,?,?)');
            $q->execute([$m['id'],$m['path'],$m['sha256'],A5_MIGRATION_TOOL_VERSION,$m['classification'],implode(',',$m['policies']),'customer_updater']);
            $journal($m['id'],'AFTER');
        }
        if(self::plan($pdo,$candidate)!==[])throw new RuntimeException('UPDATE_DATABASE_POSTCHECK_FAILED');return self::ledgerHash($pdo);
    }
}
