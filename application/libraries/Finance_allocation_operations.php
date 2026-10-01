<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Finance_allocation_policy.php';

/** Metadata only. All writers share the existing transaction, period and audit contract. */
trait Finance_allocation_operations
{
    private function allocation_ready(): void
    {
        if (!Finance_allocation_policy::ready($this->db)) throw new RuntimeException('Fitur belum diaktifkan. Minta admin menjalankan SQL 2026-09-14c melalui IDE.');
    }

    public function distribute_receipt(array $p, int $actor, string $ip=''): array
    {
        return $this->write(function() use($p,$actor,$ip) {
            $this->allocation_ready();
            $id=(int)($p['receipt_id']??0);
            $receipt=$this->rows('SELECT * FROM fin_settlement_receipt WHERE id=?',[$id])[0]??[];
            $this->account_lock((int)($receipt['account_id']??0));
            $receipt=$this->rows('SELECT * FROM fin_settlement_receipt WHERE id=? FOR UPDATE',[$id])[0]??[];
            if (($receipt['status']??'')!=='ACTIVE') throw new RuntimeException('Transfer tidak ditemukan atau sudah dibatalkan.');
            Finance_mutation_policy::assert_open_period($this->db,$receipt['received_date']);
            $parts=Finance_allocation_policy::allocations((string)($p['allocations']??''),Finance_allocation_policy::cents((string)$receipt['amount']));
            $note=$this->text($p['notes']??'',255); $key=$this->key($p);
            $hash=hash('sha256',json_encode([$parts,$note],JSON_THROW_ON_ERROR));
            $before=$this->rows('SELECT * FROM fin_receipt_distribution WHERE receipt_id=? FOR UPDATE',[$id])[0]??[];
            if (($before['request_key']??'')===$key) {
                if (!hash_equals($before['payload_hash'],$hash)) throw new RuntimeException('Permintaan sudah dipakai dengan isi berbeda.');
                return ['message'=>'Pembagian sudah tersimpan; tidak digandakan.','replayed'=>true];
            }
            $this->revision($p,$before);
            $old=$this->rows('SELECT * FROM fin_receipt_allocation WHERE receipt_id=? AND active_key IS NOT NULL',[$id]);
            $caseIds=array_unique(array_merge([(int)$receipt['settlement_id']],array_keys($parts),array_column($old,'settlement_id')));
            sort($caseIds,SORT_NUMERIC); $cases=[];
            foreach ($caseIds as $caseId) {
                $case=$this->rows('SELECT * FROM fin_settlement_control WHERE id=? FOR UPDATE',[$caseId])[0]??[];
                if (!$case || (int)$case['account_id']!==(int)$receipt['account_id'] || $case['revenue_date']>$receipt['received_date']) throw new RuntimeException('Rekap harus satu rekening dan tanggal pendapatannya tidak melewati tanggal transfer.');
                Finance_mutation_policy::assert_open_period($this->db,$case['revenue_date']);
                $cases[]=$case;
            }
            $data=['revision'=>(int)($before['revision']??0)+1,'request_key'=>$key,'payload_hash'=>$hash,'notes'=>$note,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')];
            $ok=$before?$this->db->where('receipt_id',$id)->update('fin_receipt_distribution',$data):$this->db->insert('fin_receipt_distribution',['receipt_id'=>$id]+$data);
            if (!$ok || !$this->db->where('receipt_id',$id)->update('fin_receipt_allocation',['active_key'=>null])) throw new RuntimeException('Pembagian gagal disimpan.');
            foreach ($parts as $caseId=>$amount) $this->put('fin_receipt_allocation',['receipt_id'=>$id,'settlement_id'=>$caseId,'amount'=>$amount,'revision'=>$data['revision'],'active_key'=>$id.':'.$caseId,'created_by'=>$actor]);
            foreach ($cases as $case) {
                $this->receipt_total($case,$actor);
                $this->put('fin_settlement_control',['settlement_complete'=>0],(int)$case['id']);
            }
            $this->audit('RECEIPT_ALLOCATE','fin_settlement_receipt',$id,['distribution'=>$before,'allocations'=>$old,'cases'=>$cases],$data+['allocations'=>$parts,'settlement_complete'=>0],$actor,$ip);
            return ['message'=>'Pembagian disimpan. Periksa dan konfirmasi ulang kelengkapan rekap terkait. Sisa transfer belum dialokasikan; saldo bank tidak berubah.'];
        },$actor);
    }

    public function allocate_plan(array $p, int $actor, string $ip=''): array
    {
        return $this->write(function() use($p,$actor,$ip) {
            $this->allocation_ready(); $mid=(int)($p['mutation_id']??0); $pid=(int)($p['plan_id']??0);
            $m=$this->rows('SELECT * FROM fin_account_mutation_log WHERE id=?',[$mid])[0]??[];
            $this->account_lock((int)($m['account_id']??0));
            $plan=$this->rows('SELECT * FROM fin_cash_plan WHERE id=? FOR UPDATE',[$pid])[0]??[];
            $m=$this->rows('SELECT * FROM fin_account_mutation_log m WHERE id=? AND '.Finance_settlement_control::effective().' FOR UPDATE',[$mid])[0]??[];
            if (!$plan || !$m || !Finance_mutation_policy::manual_module((string)$m['ref_module']) || $plan['direction']!==$m['mutation_type'] || $plan['status']==='CANCELLED') throw new RuntimeException('Pilih mutasi manual efektif dan rencana dengan arah sama.');
            Finance_mutation_policy::assert_open_period($this->db,$m['mutation_date']);
            $cents=Finance_allocation_policy::cents($p['amount']??''); $key=$this->key($p);
            $data=['plan_id'=>$pid,'mutation_id'=>$mid,'amount'=>Finance_allocation_policy::decimal($cents),'active_key'=>$mid.':'.$pid,'request_key'=>$key,'notes'=>$this->text($p['notes']??'',255),'created_by'=>$actor];
            $prior=$this->rows('SELECT * FROM fin_plan_allocation WHERE request_key=? FOR UPDATE',[$key])[0]??[];
            if ($prior) { $this->same_request($prior,$data,['plan_id','mutation_id','amount','notes']);return ['message'=>'Alokasi sudah tercatat; tidak digandakan.','replayed'=>true]; }
            $this->revision($p,$plan);
            if (Finance_allocation_policy::used_plan_cents($this->db,$mid)+$cents>Finance_allocation_policy::cents((string)$m['amount'])) throw new RuntimeException('Alokasi melebihi sisa mutasi. Tautan lama utuh harus dilepas sebelum dibagi.');
            $id=$this->put('fin_plan_allocation',$data);
            $this->put('fin_cash_plan',['revision'=>(int)$plan['revision']+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')],$pid);
            $this->audit('PLAN_ALLOCATE','fin_plan_allocation',$id,[],$data,$actor,$ip);
            return ['id'=>$id,'message'=>'Sebagian mutasi ditautkan. Sisa rencana diperbarui tanpa mengubah kas.'];
        },$actor);
    }

    public function unlink_plan_allocation(array $p, int $actor, string $ip=''): array
    {
        return $this->write(function() use($p,$actor,$ip) {
            $this->allocation_ready(); $id=(int)($p['id']??0);
            $row=$this->rows('SELECT r.*,m.account_id,m.mutation_date FROM fin_plan_allocation r JOIN fin_account_mutation_log m ON m.id=r.mutation_id WHERE r.id=?',[$id])[0]??[];
            $this->account_lock((int)($row['account_id']??0));
            $plan=$this->rows('SELECT * FROM fin_cash_plan WHERE id=? FOR UPDATE',[(int)$row['plan_id']])[0]??[];
            $row=$this->rows('SELECT * FROM fin_plan_allocation WHERE id=? FOR UPDATE',[$id])[0]??[];
            if (!$row || !$plan) throw new RuntimeException('Alokasi tidak ditemukan.');
            if (!$row['active_key']) return ['message'=>'Alokasi sudah dilepas.','replayed'=>true];
            $this->revision($p,$plan);
            $data=['active_key'=>null,'unlinked_by'=>$actor,'unlinked_at'=>date('Y-m-d H:i:s'),'unlink_reason'=>$this->text($p['reason']??'',255)];
            $this->put('fin_plan_allocation',$data,$id);
            $this->put('fin_cash_plan',['revision'=>(int)$plan['revision']+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')],(int)$plan['id']);
            $this->audit('PLAN_ALLOCATION_UNLINK','fin_plan_allocation',$id,$row,$data,$actor,$ip);
            return ['message'=>'Alokasi dilepas; mutasi asli tetap utuh.'];
        },$actor);
    }
}
