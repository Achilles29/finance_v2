<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Finance_bank_csv.php';

trait Finance_bank_operations
{
    public function import_statement(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function() use($p,$actor,$ip) {
            $this->allocation_ready(); $aid=(int)($p['account_id']??0); $this->account_lock($aid);
            $parsed=Finance_bank_csv::parse((string)($p['csv']??''),$p); $rows=$parsed['rows'];
            $previewHash=hash('sha256',json_encode([$aid,$parsed['file_hash'],$rows],JSON_THROW_ON_ERROR));
            if (($p['confirm_hash']??'')==='') return ['preview'=>true,'rows'=>$rows,'file_hash'=>$parsed['file_hash'],'preview_hash'=>$previewHash,'message'=>'Pratinjau saja. Belum menyimpan baris atau mengubah kas.'];
            if (!hash_equals($previewHash,(string)$p['confirm_hash'])) throw new RuntimeException('File, rekening atau pemetaan kolom berubah. Pratinjau ulang sebelum impor.');
            $count=0; $duplicates=0;
            foreach ($rows as $row) {
                if ($this->rows('SELECT id FROM fin_bank_statement_row WHERE account_id=? AND row_hash=?',[$aid,$row['row_hash']])) { $duplicates++;continue; }
                $this->put('fin_bank_statement_row',$row+['account_id'=>$aid,'file_hash'=>$parsed['file_hash'],'created_by'=>$actor]); $count++;
            }
            $this->audit('BANK_CSV_IMPORT','fin_company_account',$aid,[],['file_hash'=>$parsed['file_hash'],'rows'=>$count,'duplicates'=>$duplicates,'notes'=>'Impor bukti bank saja; belum cocok dan tidak memposting kas.'],$actor,$ip);
            return ['message'=>$count.' baris diimpor; '.$duplicates.' baris identik dilewati. Saldo bank tidak berubah.'];
        },$actor);
    }

    public function bank_rows(array $p): array
    {
        $this->allocation_ready(); $aid=(int)($p['account_id']??0); $page=max(1,min(100000,(int)($p['page']??1))); $offset=($page-1)*25;
        $rows=$this->rows('SELECT b.*,m.mutation_no,CASE WHEN b.active_mutation_id IS NOT NULL AND m.account_id=b.account_id AND m.mutation_date=b.statement_date AND m.mutation_type=b.direction AND m.amount=b.amount AND '.Finance_settlement_control::effective().' THEN 1 ELSE 0 END effective FROM fin_bank_statement_row b LEFT JOIN fin_account_mutation_log m ON m.id=b.active_mutation_id WHERE b.account_id=? ORDER BY b.statement_date DESC,b.id DESC LIMIT 26 OFFSET '.$offset,[$aid]);
        foreach ($rows as &$row) {
            $row['effective']=(int)$row['effective'] && !empty($row['mutation_no']);
            $row['candidates']=$row['active_mutation_id']?[]:$this->rows("SELECT m.id,m.mutation_no,m.ref_no,m.ref_module FROM fin_account_mutation_log m WHERE m.account_id=? AND m.mutation_date=? AND m.mutation_type=? AND m.amount=? AND ".Finance_settlement_control::effective()." AND NOT EXISTS(SELECT 1 FROM fin_bank_statement_row b WHERE b.active_mutation_id=m.id) ORDER BY m.id DESC LIMIT 6",[$aid,$row['statement_date'],$row['direction'],$row['amount']]);
        } unset($row);
        return ['rows'=>array_slice($rows,0,25),'more'=>count($rows)>25,'page'=>$page,'account_id'=>$aid];
    }

    public function match_statement(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function() use($p,$actor,$ip) {
            $this->allocation_ready(); $id=(int)($p['id']??0); $mid=(int)($p['mutation_id']??0);
            $row=$this->rows('SELECT * FROM fin_bank_statement_row WHERE id=?',[$id])[0]??[];
            $this->account_lock((int)($row['account_id']??0));
            $row=$this->rows('SELECT * FROM fin_bank_statement_row WHERE id=? FOR UPDATE',[$id])[0]??[];
            if (!$row) throw new RuntimeException('Baris rekening koran tidak ditemukan.');
            $note=$this->text($p['reason']??'',255);
            $this->revision($p,$row);
            if ($mid>0) {
                if ($row['active_mutation_id']) throw new RuntimeException('Lepaskan kecocokan lama sebelum mengganti.');
                $m=$this->rows('SELECT * FROM fin_account_mutation_log m WHERE id=? AND '.Finance_settlement_control::effective().' FOR UPDATE',[$mid])[0]??[];
                if (!$m || (int)$m['account_id']!==(int)$row['account_id'] || $m['mutation_date']!==$row['statement_date'] || $m['mutation_type']!==$row['direction'] || Finance_allocation_policy::cents((string)$m['amount'])!==Finance_allocation_policy::cents((string)$row['amount'])) throw new RuntimeException('Rekening, tanggal, arah, nominal, atau status mutasi tidak cocok.');
                $data=['mutation_id'=>$mid,'active_mutation_id'=>$mid,'revision'=>(int)$row['revision']+1];
            } else $data=['active_mutation_id'=>null,'revision'=>(int)$row['revision']+1];
            $this->put('fin_bank_statement_row',$data,$id);
            $this->audit($mid?'BANK_MATCH':'BANK_UNMATCH','fin_bank_statement_row',$id,$row,$data+['notes'=>$note],$actor,$ip);
            return ['message'=>$mid?'Kecocokan dikonfirmasi. Tidak memposting kas.':'Kecocokan dilepas; mutasi asli tetap utuh.'];
        },$actor);
    }
}
