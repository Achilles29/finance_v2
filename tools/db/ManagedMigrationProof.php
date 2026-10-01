<?php
declare(strict_types=1);

/** Schema evidence for manual adoption and a POS baseline correction. SELECT only; never repairs drift. */
final class ManagedMigrationProof
{
    public const CONTRACT = 'FINANCE_MANAGED_ADOPTION_V1';
    public static function definitions(): array
    {
        return [
            '2026-09-14c-finance-allocation-bank-review'=>['tables'=>['fin_receipt_distribution','fin_receipt_allocation','fin_plan_allocation','fin_bank_statement_row'],'pages'=>[], 'menus'=>[], 'parents'=>[], 'requires'=>['fin_revenue_reconciliation_line','fin_account_mutation_log','fin_settlement_control','fin_cash_plan']],
            '2026-09-15a-finance-general-ledger'=>['tables'=>['fin_gl_guard','fin_gl_account','fin_gl_journal','fin_gl_line'],'pages'=>['finance.accounting.index'=>'FINANCE'],'menus'=>['finance.accounting'=>['grp.finance','finance-reports/accounting','finance.accounting.index']], 'parents'=>['grp.finance'],'requires'=>['fin_company_account','fin_account_mutation_log','fin_period_close','aud_transaction_log']],
            '2026-09-15b-finance-journal-assistant'=>['tables'=>['fin_gl_mapping'],'pages'=>['finance.accounting.settings'=>'FINANCE'],'menus'=>[], 'parents'=>[], 'requires'=>['fin_gl_account','fin_gl_journal','fin_gl_line']],
            '2026-09-15c-application-user-guide'=>['tables'=>[],'pages'=>['system.guide.index'=>'SYSTEM','system.guide.server'=>'SYSTEM'],'menus'=>['system.guide'=>['grp.system','guide','system.guide.index']], 'parents'=>['grp.system'],'requires'=>[]],
            '2026-09-16a-procurement-stock-review'=>['tables'=>['pur_division_stock_review'],'pages'=>[], 'menus'=>[], 'parents'=>[], 'requires'=>['pur_division_request']],
            '2026-09-20a-pos-stock-commit-not-required'=>['tables'=>[],'pages'=>[], 'menus'=>[], 'parents'=>[], 'requires'=>['pos_order']],
        ];
    }
    private static function literal(string $s): string { return "'".str_replace("'","''",$s)."'"; }
    /** Full columns/defaults/generated properties, indexes, foreign keys, engine and collation; no row data. */
    public static function schemaSql(string $table, array $columns=[]): string
    {
        if (!preg_match('/\A[a-z0-9_]+\z/D',$table)) throw new RuntimeException('PROOF_IDENTIFIER_INVALID');
        $where='TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.self::literal($table);
        $columnWhere=$where.($columns?' AND COLUMN_NAME IN ('.implode(',',array_map([self::class,'literal'],$columns)).')':'');
        $c="(SELECT GROUP_CONCAT(JSON_ARRAY(COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME,GENERATION_EXPRESSION) ORDER BY ORDINAL_POSITION SEPARATOR '|') FROM information_schema.COLUMNS WHERE $columnWhere)";
        if($columns) return "SELECT SHA2(COALESCE($c,''),256)";
        $i="(SELECT GROUP_CONCAT(JSON_ARRAY(INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,COLLATION,SUB_PART,INDEX_TYPE) ORDER BY BINARY INDEX_NAME,SEQ_IN_INDEX SEPARATOR '|') FROM information_schema.STATISTICS WHERE $where)";
        $f="(SELECT GROUP_CONCAT(JSON_ARRAY(CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,REFERENCED_TABLE_SCHEMA=DATABASE()) ORDER BY BINARY CONSTRAINT_NAME,ORDINAL_POSITION SEPARATOR '|') FROM information_schema.KEY_COLUMN_USAGE WHERE $where AND REFERENCED_TABLE_NAME IS NOT NULL)";
        $r="(SELECT GROUP_CONCAT(JSON_ARRAY(CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE) ORDER BY BINARY CONSTRAINT_NAME SEPARATOR '|') FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=".self::literal($table).')';
        // fin_gl_guard contains no text and inherits the database default; charset is immaterial to its single integer.
        $t="(SELECT CONCAT_WS('|',TABLE_TYPE,ENGINE".($table==='fin_gl_guard'?'':',TABLE_COLLATION').") FROM information_schema.TABLES WHERE $where)";
        return "SELECT SHA2(CONCAT_WS('~',COALESCE($t,''),COALESCE($c,''),COALESCE($i,''),COALESCE($f,''),COALESCE($r,'')),256)";
    }
    public static function snapshots(callable $read): array
    {
        $result=[];
        foreach(self::definitions() as $id=>$d) foreach($d['tables'] as $table) $result[$table]=(string)$read(self::schemaSql($table));
        $result['reconciliation_columns']=(string)$read(self::schemaSql('fin_revenue_reconciliation_line',['counter_account_id','counter_payment_method_id','counter_mutation_id','resolution_type']));
        return $result;
    }
    /** ABSENT is safe to execute; VERIFIED is safe to enroll without replay; mixed/drift always stops. */
    public static function state(string $root,string $id,callable $read): ?string
    {
        $d=self::definitions()[$id]??null;if($d===null)return null;
        $count=static fn(string $sql):int=>(int)$read($sql);
        foreach($d['requires'] as $table) if($count("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".self::literal($table)." AND ENGINE='InnoDB' AND TABLE_TYPE='BASE TABLE'")!==1)throw new RuntimeException('MIGRATION_PREREQUISITE_MISSING:'.$table);
        foreach($d['requires'] as $table) {
            $column=$table==='fin_gl_account'?"COLUMN_NAME='code' AND COLUMN_TYPE='varchar(20)'":"COLUMN_NAME='id' AND DATA_TYPE='bigint' AND COLUMN_TYPE LIKE '%unsigned'";
            if($count('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.self::literal($table)." AND COLUMN_KEY='PRI' AND IS_NULLABLE='NO' AND ".$column)!==1)throw new RuntimeException('MIGRATION_PREREQUISITE_DRIFT:'.$table);
        }
        if($id==='2026-09-20a-pos-stock-commit-not-required') {
            $original="enum('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED')|NO|'PENDING'|";
            $corrected="enum('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED','NOT_REQUIRED')|NO|'PENDING'|";
            $column=(string)$read("SELECT CONCAT_WS('|',COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pos_order' AND COLUMN_NAME='stock_commit_status'");
            if(!in_array($column,[$original,$corrected],true))throw new RuntimeException('MIGRATION_SCHEMA_DRIFT:pos_order.stock_commit_status');
            if($count("SELECT COUNT(*) FROM pos_order WHERE stock_commit_status='' OR stock_commit_status IS NULL")!==0)throw new RuntimeException('MIGRATION_DATA_REVIEW_REQUIRED:pos_order.stock_commit_status');
            return $column===$original?'ABSENT':'VERIFIED';
        }
        if($d['pages']) {
            foreach(['sys_page','sys_menu','auth_role','auth_role_permission'] as $table) if($count("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".self::literal($table)." AND ENGINE='InnoDB'")!==1)throw new RuntimeException('MIGRATION_PREREQUISITE_MISSING:'.$table);
            if($count("SELECT COUNT(*) FROM auth_role WHERE BINARY role_code='SUPERADMIN'")!==1)throw new RuntimeException('MIGRATION_PREREQUISITE_MISSING:SUPERADMIN');
        }
        foreach($d['parents'] as $parent) if($count('SELECT COUNT(*) FROM sys_menu WHERE BINARY menu_code='.self::literal($parent))!==1)throw new RuntimeException('MIGRATION_PREREQUISITE_MISSING:'.$parent);
        $presence=[];
        foreach($d['tables'] as $table)$presence[]=$count('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.self::literal($table));
        foreach($d['pages'] as $page=>$module)$presence[]=$count('SELECT COUNT(*) FROM sys_page WHERE BINARY page_code='.self::literal($page));
        foreach($d['menus'] as $menu=>$v)$presence[]=$count('SELECT COUNT(*) FROM sys_menu WHERE BINARY menu_code='.self::literal($menu));
        if(str_contains($id,'14c-')) foreach(['counter_account_id','counter_payment_method_id','counter_mutation_id'] as $col)$presence[]=$count("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_revenue_reconciliation_line' AND COLUMN_NAME=".self::literal($col));
        if(array_sum($presence)===0) {
            if(str_contains($id,'14c-')&&(string)$read("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_revenue_reconciliation_line' AND COLUMN_NAME='resolution_type'")!=="enum('NONE','IN','OUT')")throw new RuntimeException('MIGRATION_SCHEMA_DRIFT:resolution_type');
            return 'ABSENT';
        }
        foreach($presence as $n)if($n!==1)throw new RuntimeException('MIGRATION_PARTIAL_REVIEW_REQUIRED:'.$id);
        $path=$root.'/tools/db/managed_migration_proofs.json';
        if(is_link($path)||!is_file($path))throw new RuntimeException('MIGRATION_PROOF_MISSING');
        $proof=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
        if(($proof['contract']??'')!==self::CONTRACT)throw new RuntimeException('MIGRATION_PROOF_INVALID');
        foreach($d['tables'] as $table) {
            if(($proof['schemas'][$table]??null)!==(string)$read(self::schemaSql($table)))throw new RuntimeException('MIGRATION_SCHEMA_DRIFT:'.$table);
            if($count('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE='.self::literal($table))!==0)throw new RuntimeException('MIGRATION_UNREVIEWED_TRIGGER:'.$table);
        }
        if(str_contains($id,'14c-')&&($proof['schemas']['reconciliation_columns']??null)!==(string)$read(self::schemaSql('fin_revenue_reconciliation_line',['counter_account_id','counter_payment_method_id','counter_mutation_id','resolution_type'])))throw new RuntimeException('MIGRATION_SCHEMA_DRIFT:reconciliation_columns');
        foreach($d['pages'] as $page=>$module) {
            if($count('SELECT COUNT(*) FROM sys_page WHERE BINARY page_code='.self::literal($page).' AND BINARY module='.self::literal($module))!==1)throw new RuntimeException('MIGRATION_REFERENCE_DRIFT:'.$page);
            // Preserve user-edited grants, labels and order. Require exactly one existing SUPERADMIN row, never elevate it.
            if($count('SELECT COUNT(*) FROM auth_role_permission a JOIN auth_role r ON r.id=a.role_id JOIN sys_page p ON p.id=a.page_id WHERE BINARY r.role_code=\'SUPERADMIN\' AND BINARY p.page_code='.self::literal($page))!==1)throw new RuntimeException('MIGRATION_PERMISSION_REVIEW_REQUIRED:'.$page);
        }
        foreach($d['menus'] as $menu=>[$parent,$url,$page]) if($count('SELECT COUNT(*) FROM sys_menu m JOIN sys_menu g ON g.id=m.parent_id JOIN sys_page p ON p.id=m.page_id WHERE BINARY m.menu_code='.self::literal($menu).' AND BINARY g.menu_code='.self::literal($parent).' AND BINARY m.url='.self::literal($url).' AND BINARY p.page_code='.self::literal($page))!==1)throw new RuntimeException('MIGRATION_REFERENCE_DRIFT:'.$menu);
        if(str_contains($id,'15a-')) {
            if($count('SELECT COUNT(*) FROM fin_gl_guard WHERE id=1')!==1)throw new RuntimeException('MIGRATION_REFERENCE_DRIFT:fin_gl_guard');
            foreach(['ASSET'=>['1100','1190','1200','1300','1400','1490','1500'],'LIABILITY'=>['2100','2200','2300','2400','2500'],'EQUITY'=>['3100','3200','3300'],'INCOME'=>['4100','4200'],'EXPENSE'=>['5100','5200','5300','5400','5500','5600','5700']] as $type=>$codes) {
                if($count('SELECT COUNT(*) FROM fin_gl_account WHERE code IN ('.implode(',',array_map([self::class,'literal'],$codes)).') AND BINARY account_type='.self::literal($type)." AND is_cash=IF(code='1100',1,0)")!==count($codes))throw new RuntimeException('MIGRATION_REFERENCE_DRIFT:fin_gl_account');
            }
        }
        return 'VERIFIED';
    }
    /** Exclude ONLY the pinned scripts' human-readable trailing postchecks from the streaming client protocol. */
    public static function executionSql(string $id,string $sql): string
    {
        if($id==='2026-09-15c-application-user-guide')return explode('-- Postcheck metadata',$sql,2)[0];
        if($id==='2026-09-16a-procurement-stock-review')return explode('SHOW COLUMNS FROM pur_division_stock_review;',$sql,2)[0];
        return $sql;
    }
}
