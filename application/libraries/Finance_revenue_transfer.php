<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Daily revenue reconciliation posts an ordinary two-account cash transfer. */
trait Finance_revenue_transfer
{
    private function transfer_ready(): bool
    {
        return $this->db->field_exists('counter_payment_method_id',self::LINE)
            && $this->db->field_exists('counter_account_id',self::LINE)
            && $this->db->field_exists('counter_mutation_id',self::LINE);
    }

    private function post_transfer_locked(array $line,int $actor): void
    {
        if (!$this->transfer_ready()) throw new RuntimeException('Transfer belum aktif; minta administrator menerapkan migrasi 2026-09-14c.');
        $accountId=(int)$line['account_id'];
        $counterId=(int)($line['counter_account_id']??0);
        $methodId=(int)$line['payment_method_id'];
        $counterMethodId=(int)($line['counter_payment_method_id']??0);
        if ($accountId<=0 || $counterId<=0 || $accountId===$counterId) throw new RuntimeException('Pilih rekening lawan yang berbeda untuk transfer.');
        if ($counterMethodId===$methodId) throw new RuntimeException('Metode pendapatan lawan harus berbeda atau dikosongkan.');

        // Optional method attribution is not a prerequisite and never closes another line.
        $methodIds=[$methodId];
        if ($counterMethodId>0) $methodIds[]=$counterMethodId;
        sort($methodIds,SORT_NUMERIC); $methods=[];
        foreach($methodIds as $id) {
            $methods[$id]=$this->db->query('SELECT * FROM pos_payment_method WHERE id=? AND is_active=1 FOR UPDATE',[$id])->row_array();
            if (!$methods[$id]) throw new RuntimeException('Metode pembayaran tidak aktif.');
        }
        if ($counterMethodId>0 && (int)$methods[$counterMethodId]['company_account_id']!==$counterId) throw new RuntimeException('Metode lawan opsional tidak sesuai rekening lawan. Pilih yang sesuai atau kosongkan.');

        $ordered=[$accountId,$counterId]; sort($ordered,SORT_NUMERIC); $accounts=[];
        foreach($ordered as $id) {
            $accounts[$id]=$this->db->query('SELECT * FROM fin_company_account WHERE id=? AND is_active=1 FOR UPDATE',[$id])->row_array();
            if (!$accounts[$id]) throw new RuntimeException('Rekening transfer tidak aktif.');
        }
        if ((string)$accounts[$accountId]['currency_code']!==(string)$accounts[$counterId]['currency_code']) throw new RuntimeException('Transfer hanya untuk dua rekening dengan mata uang sama.');
        $expectedMap=$this->expected_by_method($line['revenue_date']);
        $expected=(float)($expectedMap[$methodId]['expected_amount']??0);
        $adjusted=$this->posted_adjustment_map($line['revenue_date'],true);
        $difference=round((float)$line['actual_amount']-$expected-(float)($adjusted[$methodId]??0),2);
        if (abs($expected-(float)$line['expected_amount'])>=.005 || abs($difference-(float)$line['difference_amount'])>=.005) throw new RuntimeException('Pendapatan atau penyesuaian berubah. Muat ulang dan simpan ulang nilai riil sebelum posting.');
        if (abs($difference)<.005) throw new RuntimeException('Tidak ada sisa selisih pendapatan untuk ditransfer.');
        if (trim((string)$line['resolution_note'])==='') throw new RuntimeException('Isi alasan transfer antar rekening.');
        if (!empty($line['settlement_charge_id'])) throw new RuntimeException('Transfer bukan biaya; jangan memakai rincian biaya settlement.');

        $amount=abs($difference);
        $source=$difference>0?$counterId:$accountId;
        $destination=$difference>0?$accountId:$counterId;
        if ((float)$accounts[$source]['current_balance']-$amount<-.004) throw new RuntimeException('Saldo rekening sumber tidak cukup. Kedua sisi transfer tidak diposting.');
        $mutations=[];
        foreach([$source=>'OUT',$destination=>'IN'] as $id=>$direction) {
            $before=round((float)$accounts[$id]['current_balance'],2);
            $after=round($before+($direction==='IN'?$amount:-$amount),2);
            // Never clamp a negative destination's balance: that would manufacture cash.
            if (!$this->db->where('id',$id)->update(self::ACCOUNT,['current_balance'=>$after])) throw new RuntimeException('Saldo transfer gagal disimpan.');
            $data=['mutation_no'=>'RPMUT-'.date('Ymd',strtotime($line['reconciliation_date'])).'-'.$line['id'].'-TRANSFER-'.$direction,
                'mutation_date'=>$line['reconciliation_date'],'account_id'=>$id,'mutation_type'=>$direction,'amount'=>$amount,
                'balance_before'=>$before,'balance_after'=>$after,'ref_module'=>'FINANCE_TRANSFER','report_category'=>null,
                'ref_table'=>self::LINE,'ref_id'=>(int)$line['id'],'ref_no'=>$line['reconciliation_no'],
                'notes'=>mb_substr('Transfer rekonsiliasi pendapatan '. $line['revenue_date'].': '.(string)$line['resolution_note'],0,255),
                'created_by'=>$actor?:null,'created_at'=>date('Y-m-d H:i:s')];
            if (!$this->db->insert(self::MUTATION,$data)) throw new RuntimeException('Mutasi transfer gagal dicatat.');
            $mutations[$id]=(int)$this->db->insert_id();
        }
        $data=['status'=>'POSTED','resolution_type'=>'TRANSFER','report_category'=>null,'settlement_control_id'=>null,'settlement_charge_id'=>null,
            'mutation_id'=>$mutations[$accountId],'counter_mutation_id'=>$mutations[$counterId],
            'counter_account_id'=>$counterId,'counter_payment_method_id'=>$counterMethodId?:null,
            'resolved_by'=>$actor?:null,'resolved_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
        // Legacy schemas may have transfer support before settlement-link support.
        foreach(['settlement_control_id','settlement_charge_id'] as $column) if (!$this->db->field_exists($column,self::LINE)) unset($data[$column]);
        if (!$this->db->where('id',(int)$line['id'])->update(self::LINE,$data)) throw new RuntimeException('Status rekonsiliasi gagal disimpan.');
        if (!$this->db->insert('aud_transaction_log',['module_code'=>'FINANCE','action_code'=>'REVENUE_TRANSFER','entity_table'=>self::LINE,
            'entity_id'=>(int)$line['id'],'actor_user_id'=>$actor?:null,'before_payload'=>json_encode($line,JSON_THROW_ON_ERROR),
            'after_payload'=>json_encode($data+['accounts_before'=>$accounts,'source_account_id'=>$source,'destination_account_id'=>$destination,'amount'=>$amount],JSON_THROW_ON_ERROR),
            'notes'=>(string)$line['resolution_note']])) throw new RuntimeException('Audit transfer gagal; kedua sisi transfer dibatalkan.');
    }
}
