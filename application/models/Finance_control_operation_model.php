<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Finance_insight_model.php';
require_once __DIR__.'/../libraries/Finance_allocation_operations.php';
require_once __DIR__.'/../libraries/Finance_bank_operations.php';

/** Metadata operations never post cash. Posting stays in the existing, permission-checked writers. */
class Finance_control_operation_model extends Finance_insight_model
{
    use Finance_allocation_operations;
    use Finance_bank_operations;
    private function write(callable $work,int $actor): array
    {
        if ($actor<=0) return ['ok'=>false,'message'=>'Sesi pengguna tidak valid.'];
        if (!Finance_settlement_control::operations_ready($this->db)) return ['ok'=>false,'message'=>'Jalankan migrasi 2026-09-14b terlebih dahulu.'];
        return $this->transaction($work);
    }
    private function put(string $table,array $data,int $id=0): int
    {
        $ok=$id?$this->db->where('id',$id)->update($table,$data):$this->db->insert($table,$data);
        if (!$ok) throw new RuntimeException('Penyimpanan gagal atau identitas dokumen sudah dipakai.');
        return $id ?: (int)$this->db->insert_id();
    }
    private function key(array $p): string
    {
        $key=(string)($p['request_key']??'');
        if (!preg_match('/\A[a-f0-9]{32}\z/D',$key)) throw new RuntimeException('Identitas formulir tidak valid. Muat ulang.');
        return $key;
    }
    private function revision(array $p,array $row): void
    {
        if ((int)($p['revision']??0)!==(int)($row['revision']??0)) throw new RuntimeException('Data telah berubah. Muat ulang sebelum menyimpan.');
    }
    private function account_lock(int $id): void
    {
        if (!$this->rows("SELECT id FROM fin_company_account WHERE id=? AND is_active=1 AND currency_code='IDR' FOR UPDATE",[$id])) throw new RuntimeException('Rekening IDR tidak aktif/tidak ditemukan.');
    }
    private function case_lock(int $id): array
    {
        $row=$this->rows('SELECT * FROM fin_settlement_control WHERE id=?',[$id])[0]??[];
        $this->account_lock((int)($row['account_id']??0));
        $row=$this->rows('SELECT * FROM fin_settlement_control WHERE id=? FOR UPDATE',[$id])[0]??[];
        if (!$row) throw new RuntimeException('Settlement tidak ditemukan.');
        Finance_mutation_policy::assert_open_period($this->db,$row['revenue_date']);
        return $row;
    }
    private function evidence_check(int $id,int $caseId): void
    {
        if (!$id) return;
        $row=$this->rows('SELECT * FROM fin_control_evidence WHERE id=? AND settlement_id=?',[$id,$caseId])[0]??[];
        if (!$row) throw new RuntimeException('Bukti bukan milik settlement ini.');
        require_once __DIR__.'/../libraries/Finance_control_evidence.php';Finance_control_evidence::download_path($row);
    }
    private function positive($value): float
    {
        $amount=$this->money($value);
        if ($amount<=0) throw new RuntimeException('Nominal wajib lebih dari nol.');
        return $amount;
    }
    private function same_request(array $before,array $data,array $fields): void
    {
        foreach ($fields as $field) if ((string)($before[$field]??'')!==(string)($data[$field]??'') && !($field==='amount' && (float)$before[$field]===(float)$data[$field])) throw new RuntimeException('Identitas formulir sudah digunakan dengan isi berbeda. Muat ulang.');
    }
    public function save_receipt(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $case=$this->case_lock((int)($p['settlement_id']??0));$id=(int)$case['id'];$key=$this->key($p);
            $date=self::date_value($p['received_date']??'');
            if ($date<$case['revenue_date'] || $date>date('Y-m-d')) throw new RuntimeException('Tanggal transfer harus sejak tanggal pendapatan sampai hari ini.');
            Finance_mutation_policy::assert_open_period($this->db,$date);
            $ref=mb_strtoupper($this->text($p['reference_no']??'',80),'UTF-8');
            $evidence=(int)($p['evidence_id']??0);$this->evidence_check($evidence,$id);
            $data=['settlement_id'=>$id,'account_id'=>$case['account_id'],'received_date'=>$date,'reference_no'=>$ref,
                'active_reference_hash'=>hash('sha256',$ref),'amount'=>$this->positive($p['amount']??''),'evidence_id'=>$evidence?:null,
                'notes'=>$this->text($p['notes']??'',255),'request_key'=>$key,'created_by'=>$actor];
            $prior=$this->rows('SELECT * FROM fin_settlement_receipt WHERE request_key=? FOR UPDATE',[$key])[0]??[];
            if ($prior) { $this->same_request($prior,$data,['settlement_id','received_date','reference_no','amount','evidence_id','notes']);return ['message'=>'Transfer sudah tercatat; tidak digandakan.','id'=>(int)$prior['id'],'replayed'=>true]; }
            $this->revision($p,$case);
            $receiptId=$this->put('fin_settlement_receipt',$data);
            $this->receipt_total($case,$actor);
            $this->audit('SETTLEMENT_RECEIPT_ADD','fin_settlement_receipt',$receiptId,[],$data,$actor,$ip);
            return ['id'=>$receiptId,'message'=>'Rincian transfer disimpan. Total pencairan dihitung ulang tanpa mengubah saldo bank.'];
        },$actor);
    }
    private function receipt_total(array $case,int $actor): void
    {
        $opening=(int)$case['receipt_mode']?(float)$case['receipt_opening_amount']:(float)$case['received_amount'];
        $rows=$this->rows("SELECT amount FROM fin_settlement_receipt WHERE settlement_id=? AND status='ACTIVE' FOR UPDATE",[$case['id']]);
        $total=round($opening+(Finance_allocation_policy::ready($this->db)?Finance_allocation_policy::receipt_total($this->db,(int)$case['id']):array_sum(array_column($rows,'amount'))),2);
        if ($total>999999999999.99) throw new RuntimeException('Total pencairan melewati batas nominal.');
        $this->put('fin_settlement_control',['receipt_mode'=>1,'receipt_opening_amount'=>$opening,'received_amount'=>$total,
            'revision'=>(int)$case['revision']+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')],(int)$case['id']);
    }
    public function void_receipt(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $row=$this->rows('SELECT * FROM fin_settlement_receipt WHERE id=?',[(int)($p['id']??0)])[0]??[];
            $case=$this->case_lock((int)($row['settlement_id']??0));
            $row=$this->rows('SELECT * FROM fin_settlement_receipt WHERE id=? FOR UPDATE',[$row['id']])[0];
            if ($row['status']==='VOID') return ['message'=>'Rincian transfer sudah dibatalkan.','replayed'=>true];
            $this->revision($p,$case);Finance_mutation_policy::assert_open_period($this->db,$row['received_date']);
            $affected=[$case];
            if (Finance_allocation_policy::ready($this->db)) {
                $distribution=$this->rows('SELECT * FROM fin_receipt_distribution WHERE receipt_id=? FOR UPDATE',[$row['id']])[0]??[];
                if ($distribution) {
                    if ((int)($p['distribution_revision']??-1)!==(int)$distribution['revision']) throw new RuntimeException('Pembagian transfer berubah. Muat ulang sebelum membatalkan seluruh transfer.');
                    foreach ($this->rows('SELECT DISTINCT settlement_id FROM fin_receipt_allocation WHERE receipt_id=? AND active_key IS NOT NULL ORDER BY settlement_id',[$row['id']]) as $part) {
                        if ((int)$part['settlement_id']!==(int)$case['id']) $affected[]=$this->case_lock((int)$part['settlement_id']);
                    }
                }
            }
            $data=['status'=>'VOID','active_reference_hash'=>null,'voided_by'=>$actor,'voided_at'=>date('Y-m-d H:i:s'),'void_reason'=>$this->text($p['reason']??'',255)];
            $this->put('fin_settlement_receipt',$data,(int)$row['id']);
            foreach ($affected as $affectedCase) $this->receipt_total($affectedCase,$actor);
            $this->audit('SETTLEMENT_RECEIPT_VOID','fin_settlement_receipt',(int)$row['id'],$row,$data,$actor,$ip);
            return ['message'=>'Rincian transfer dibatalkan; total dihitung ulang. Ini bukan refund/pengeluaran bank.'];
        },$actor);
    }
    public function save_charge(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $case=$this->case_lock((int)($p['settlement_id']??0));$id=(int)($p['id']??0);$key=$this->key($p);
            $before=$id?($this->rows('SELECT * FROM fin_settlement_charge WHERE id=? FOR UPDATE',[$id])[0]??[]):[];
            if ($id && (!$before || (int)$before['settlement_id']!==(int)$case['id'])) throw new RuntimeException('Rincian biaya tidak ditemukan.');
            $this->revision($p,$before);
            if ($id && $this->rows('SELECT id FROM fin_account_mutation_log m WHERE settlement_charge_id=? AND '.Finance_settlement_control::effective().' LIMIT 1 FOR UPDATE',[$id])) throw new RuntimeException('Biaya sudah diposting. VOID mutasi dahulu sebelum mengubah rincian.');
            $date=self::date_value($p['charge_date']??'');
            if ($date<$case['revenue_date'] || $date>date('Y-m-d')) throw new RuntimeException('Tanggal biaya tidak sesuai.');
            Finance_mutation_policy::assert_open_period($this->db,$date);
            $direction=(string)($p['direction']??'');$category=(string)($p['category']??'');
            if (!in_array($direction,['IN','OUT'],true) || !Finance_mutation_policy::valid($category,$direction)) throw new RuntimeException('Kategori dan arah biaya tidak sesuai.');
            $doc=mb_strtoupper($this->text($p['document_no']??'',80),'UTF-8');$line=mb_strtoupper($this->text($p['line_reference']??'',40),'UTF-8');
            $evidence=(int)($p['evidence_id']??0);$this->evidence_check($evidence,(int)$case['id']);
            $data=['settlement_id'=>(int)$case['id'],'account_id'=>$case['account_id'],'charge_date'=>$date,'document_no'=>$doc,'line_reference'=>$line,
                'identity_hash'=>hash('sha256',json_encode([$doc,$line],JSON_THROW_ON_ERROR)),'category'=>$category,'direction'=>$direction,
                'amount'=>$this->positive($p['amount']??''),'evidence_id'=>$evidence?:null,'notes'=>$this->text($p['notes']??'',255),
                'revision'=>(int)($before['revision']??0)+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')];
            if (!$id) {
                $prior=$this->rows('SELECT * FROM fin_settlement_charge WHERE request_key=? FOR UPDATE',[$key])[0]??[];
                if ($prior) { $this->same_request($prior,$data,['settlement_id','charge_date','document_no','line_reference','category','direction','amount','evidence_id','notes']);return ['id'=>(int)$prior['id'],'message'=>'Rincian sudah tersimpan; tidak digandakan.']; }
                $data['request_key']=$key;$data['created_by']=$actor;
            }
            $id=$this->put('fin_settlement_charge',$data,$id);
            $legacyId=(int)($p['existing_mutation_id']??0);
            if ($legacyId) {
                $legacy=$this->rows('SELECT * FROM fin_account_mutation_log m WHERE id=? AND '.Finance_settlement_control::effective().' FOR UPDATE',[$legacyId])[0]??[];
                if (!$legacy || !empty($legacy['settlement_charge_id']) || !Finance_mutation_policy::manual_module((string)$legacy['ref_module'])
                    || (int)$legacy['settlement_control_id']!==(int)$case['id'] || (int)$legacy['account_id']!==(int)$case['account_id']
                    || $legacy['mutation_date']!==$date || $legacy['mutation_type']!==$direction || $legacy['report_category']!==$category || abs((float)$legacy['amount']-$data['amount'])>=.005) throw new RuntimeException('Mutasi lama tidak sesuai rincian atau sudah memiliki identitas.');
                if ($this->rows('SELECT id FROM fin_account_mutation_log m WHERE settlement_charge_id=? AND '.Finance_settlement_control::effective().' LIMIT 1 FOR UPDATE',[$id])) throw new RuntimeException('Rincian sudah terpakai oleh mutasi lain.');
                $this->put('fin_account_mutation_log',['settlement_charge_id'=>$id],$legacyId);
                $this->audit('SETTLEMENT_LEGACY_IDENTIFY','fin_account_mutation_log',$legacyId,$legacy,['settlement_charge_id'=>$id,'notes'=>$data['notes']],$actor,$ip);
            }
            $this->audit('SETTLEMENT_CHARGE_SAVE','fin_settlement_charge',$id,$before,$data,$actor,$ip);
            return ['id'=>$id,'message'=>'Identitas biaya disimpan, belum memposting kas. Persetujuan lama tidak berlaku jika rincian berubah.'];
        },$actor);
    }
    public function search(array $p): array
    {
        $q=$this->text($p['q']??'',80,false);$where=['1=1'];$params=[];
        if ($q!=='') { $where[]="(c.provider_reference LIKE ? ESCAPE '!' OR pm.method_name LIKE ? ESCAPE '!' OR CAST(c.id AS CHAR)=?)";$params[]='%'.$this->db->escape_like_str($q).'%';$params[]='%'.$this->db->escape_like_str($q).'%';$params[]=$q; }
        foreach (['from'=>'>=','to'=>'<='] as $key=>$op) if (!empty($p[$key])) { $where[]="c.revenue_date $op ?";$params[]=self::date_value($p[$key]); }
        if ((int)($p['method_id']??0)>0) { $where[]='c.payment_method_id=?';$params[]=(int)$p['method_id']; }
        if ((int)($p['id']??0)>0) { $where[]='c.id=?';$params[]=(int)$p['id']; }
        if (isset($p['complete']) && in_array((string)$p['complete'],['0','1'],true)) { $where[]='c.settlement_complete=?';$params[]=(int)$p['complete']; }
        $base=' FROM fin_settlement_control c JOIN pos_payment_method pm ON pm.id=c.payment_method_id WHERE '.implode(' AND ',$where);
        $total=(int)$this->rows('SELECT COUNT(*) n'.$base,$params)[0]['n'];$pages=max(1,(int)ceil($total/25));$page=min($pages,max(1,(int)($p['page']??1)));$offset=($page-1)*25;
        $rows=$this->rows('SELECT c.id,c.revenue_date,c.provider_reference,c.payment_method_id,c.account_id,c.settlement_complete,pm.method_name'.$base." ORDER BY c.revenue_date DESC,c.id DESC LIMIT 25 OFFSET $offset",$params);
        return compact('rows','total','page','pages');
    }
    public function details(int $caseId): array
    {
        if (!Finance_settlement_control::operations_ready($this->db) || !$caseId) return [];
        $charges=$this->rows('SELECT c.*,m.id mutation_id,m.mutation_no FROM fin_settlement_charge c LEFT JOIN fin_account_mutation_log m ON m.settlement_charge_id=c.id AND '.Finance_settlement_control::effective().' WHERE c.settlement_id=? ORDER BY c.id DESC',[$caseId]);
        $receipts=$this->rows('SELECT * FROM fin_settlement_receipt WHERE settlement_id=? ORDER BY received_date DESC,id DESC',[$caseId]);
        $incoming=[];
        if (Finance_allocation_policy::ready($this->db)) {
            foreach ($receipts as &$receipt) {
                $receipt['distribution']=$this->rows('SELECT * FROM fin_receipt_distribution WHERE receipt_id=?',[$receipt['id']])[0]??[];
                $receipt['allocations']=$this->rows('SELECT a.*,c.revenue_date,pm.method_name FROM fin_receipt_allocation a JOIN fin_settlement_control c ON c.id=a.settlement_id JOIN pos_payment_method pm ON pm.id=c.payment_method_id WHERE a.receipt_id=? AND a.active_key IS NOT NULL ORDER BY a.settlement_id',[$receipt['id']]);
            } unset($receipt);
            $incoming=$this->rows("SELECT a.amount,r.reference_no,r.status,c.revenue_date,c.payment_method_id FROM fin_receipt_allocation a JOIN fin_settlement_receipt r ON r.id=a.receipt_id JOIN fin_settlement_control c ON c.id=r.settlement_id WHERE a.settlement_id=? AND a.active_key IS NOT NULL AND r.settlement_id<>?",[$caseId,$caseId]);
        }
        return ['receipts'=>$receipts,'incoming_allocations'=>$incoming,'charges'=>$charges,
            'evidence'=>$this->rows('SELECT id,original_name,mime_type,byte_size,created_at FROM fin_control_evidence WHERE settlement_id=? ORDER BY id DESC',[$caseId])];
    }
    public function search_mutations(array $p): array
    {
        $q=$this->text($p['q']??'',80,false);$page=max(1,min(100000,(int)($p['page']??1)));$offset=($page-1)*25;
        $params=[];$filter='';
        if ($q!=='') { $filter=" AND (m.mutation_no LIKE ? ESCAPE '!' OR m.ref_no LIKE ? ESCAPE '!')";$params=['%'.$this->db->escape_like_str($q).'%','%'.$this->db->escape_like_str($q).'%']; }
        foreach (['from'=>'>=','to'=>'<='] as $key=>$op) if (!empty($p[$key])) { $filter.=" AND m.mutation_date $op ?";$params[]=self::date_value($p[$key]); }
        $remaining='m.amount';
        if (Finance_allocation_policy::ready($this->db)) {
            $remaining="(m.amount-COALESCE((SELECT SUM(pa.amount) FROM fin_plan_allocation pa WHERE pa.mutation_id=m.id AND pa.active_key IS NOT NULL),0))";
            $filter.=" AND $remaining>0";
        }
        $rows=$this->rows("SELECT m.id,m.mutation_no,m.mutation_date,m.mutation_type,m.amount,a.account_name FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id WHERE a.currency_code='IDR' AND m.ref_module IN ('FINANCE','FINANCE_RECON','REVENUE_RECON') AND m.mutation_type IN ('IN','OUT') AND ".Finance_settlement_control::effective()." AND NOT EXISTS(SELECT 1 FROM fin_cash_plan_realization r WHERE r.active_mutation_id=m.id) $filter ORDER BY m.mutation_date DESC,m.id DESC LIMIT 26 OFFSET $offset",$params);
        if (Finance_allocation_policy::ready($this->db)) foreach ($rows as &$row) $row['available_amount']=Finance_allocation_policy::decimal(max(0,Finance_allocation_policy::cents((string)$row['amount'])-Finance_allocation_policy::used_plan_cents($this->db,(int)$row['id']))); unset($row);
        return ['rows'=>array_slice($rows,0,25),'page'=>$page,'more'=>count($rows)>25];
    }
    public function link_plan(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $mid=(int)($p['mutation_id']??0);$planId=(int)($p['plan_id']??0);
            $m=$this->rows('SELECT * FROM fin_account_mutation_log WHERE id=?',[$mid])[0]??[];$this->account_lock((int)($m['account_id']??0));
            $plan=$this->rows('SELECT * FROM fin_cash_plan WHERE id=? FOR UPDATE',[$planId])[0]??[];
            $m=$this->rows('SELECT * FROM fin_account_mutation_log m WHERE id=? AND '.Finance_settlement_control::effective().' FOR UPDATE',[$mid])[0]??[];
            if (!$plan || !$m || !Finance_mutation_policy::manual_module((string)$m['ref_module']) || $plan['direction']!==$m['mutation_type'] || (float)$m['amount']<=0 || $plan['status']==='CANCELLED') throw new RuntimeException('Pilih mutasi manual efektif dengan arah sama dan rencana yang tidak dibatalkan. POS/tagihan/payroll otomatis tidak boleh ditautkan ulang.');
            $before=$this->rows('SELECT * FROM fin_cash_plan_realization WHERE active_mutation_id=? FOR UPDATE',[$mid])[0]??[];
            if ($before) { if ((int)$before['plan_id']!==$planId) throw new RuntimeException('Mutasi sudah ditautkan ke rencana lain.');return ['message'=>'Mutasi sudah tertaut, tidak digandakan.','replayed'=>true]; }
            $this->revision($p,$plan);
            if (Finance_allocation_policy::ready($this->db) && Finance_allocation_policy::used_plan_cents($this->db,$mid)>0) throw new RuntimeException('Mutasi sudah dialokasikan sebagian. Gunakan alokasi nominal atau lepas alokasi sebelumnya.');
            $data=['plan_id'=>$planId,'mutation_id'=>$mid,'active_mutation_id'=>$mid,'notes'=>$this->text($p['notes']??'',255),'created_by'=>$actor];
            $id=$this->put('fin_cash_plan_realization',$data);
            $this->put('fin_cash_plan',['revision'=>(int)$plan['revision']+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')],$planId);
            $this->audit('CASH_PLAN_LINK','fin_cash_plan_realization',$id,[],$data,$actor,$ip);
            return ['message'=>'Realisasi ditautkan. Sisa proyeksi dihitung dari mutasi efektif; saldo bank tidak ditambah/dikurangi lagi.'];
        },$actor);
    }
    public function unlink_plan(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $id=(int)($p['id']??0);$row=$this->rows('SELECT * FROM fin_cash_plan_realization WHERE id=?',[$id])[0]??[];
            $plan=$this->rows('SELECT * FROM fin_cash_plan WHERE id=? FOR UPDATE',[(int)($row['plan_id']??0)])[0]??[];
            if (!$plan) throw new RuntimeException('Tautan tidak ditemukan.');
            $row=$this->rows('SELECT * FROM fin_cash_plan_realization WHERE id=? FOR UPDATE',[$id])[0];
            if (!$row['active_mutation_id']) return ['message'=>'Tautan sudah dilepas.','replayed'=>true];
            $this->revision($p,$plan);
            $data=['active_mutation_id'=>null,'unlinked_by'=>$actor,'unlinked_at'=>date('Y-m-d H:i:s'),'unlink_reason'=>$this->text($p['reason']??'',255)];
            $this->put('fin_cash_plan_realization',$data,$id);$this->put('fin_cash_plan',['revision'=>(int)$plan['revision']+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')],(int)$plan['id']);
            $this->audit('CASH_PLAN_UNLINK','fin_cash_plan_realization',$id,$row,$data,$actor,$ip);
            return ['message'=>'Tautan dilepas; transaksi asli dan saldo bank tetap utuh.'];
        },$actor);
    }
    public function save_policy(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $before=Finance_settlement_control::policy($this->db,true);$this->revision($p,$before);
            foreach (['approval_enabled','evidence_required'] as $field) if (!in_array((string)($p[$field]??''),['0','1'],true)) throw new RuntimeException('Pilihan kebijakan tidak valid.');
            $day=(string)($p['payroll_day']??'');if (!ctype_digit($day) || (int)$day<1 || (int)$day>31) throw new RuntimeException('Tanggal proyeksi payroll harus 1–31.');
            $data=['approval_enabled'=>(int)$p['approval_enabled'],'evidence_required'=>(int)$p['evidence_required'],
                'approval_threshold'=>$this->positive($p['approval_threshold']??''),'payroll_day'=>(int)$day,
                'revision'=>(int)$before['revision']+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')];
            $this->put('fin_control_policy',$data,1);
            $this->audit('FINANCE_CONTROL_POLICY','fin_control_policy',1,$before,$data+['notes'=>$this->text($p['notes']??'',255)],$actor,$ip);
            return ['message'=>'Kebijakan disimpan. Persetujuan lama perlu diajukan ulang karena kebijakan berubah. Tidak mengubah perhitungan gaji.'];
        },$actor);
    }
    private function approval_lock(string $action,int $id): array
    {
        if (!in_array($action,['POST_CHARGE','VOID_ADJUSTMENT'],true)) throw new RuntimeException('Aksi persetujuan tidak valid.');
        $table=$action==='POST_CHARGE'?'fin_settlement_charge':'fin_account_mutation_log';
        $row=$this->rows("SELECT * FROM $table WHERE id=?",[$id])[0]??[];
        $this->case_lock((int)($row[$action==='POST_CHARGE'?'settlement_id':'settlement_control_id']??0));
        if ($action==='POST_CHARGE' && $this->rows('SELECT id FROM fin_account_mutation_log m WHERE settlement_charge_id=? AND '.Finance_settlement_control::effective().' LIMIT 1 FOR UPDATE',[$id])) throw new RuntimeException('Biaya sudah diposting; tidak perlu mengajukan posting ulang.');
        if ($action==='VOID_ADJUSTMENT' && $this->rows('SELECT id FROM fin_account_mutation_log WHERE reversal_of_mutation_id=? LIMIT 1 FOR UPDATE',[$id])) throw new RuntimeException('Mutasi sudah di-VOID; tidak perlu mengajukan ulang.');
        return Finance_settlement_control::approval_snapshot($this->db,$action,$id);
    }
    public function request_approval(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $action=(string)($p['action_code']??'');$id=(int)($p['target_id']??0);$snap=$this->approval_lock($action,$id);
            $existing=$this->rows("SELECT id FROM fin_control_approval WHERE action_code=? AND target_id=? AND payload_hash=? AND status IN ('PENDING','APPROVED') LIMIT 1 FOR UPDATE",[$action,$id,$snap['hash']]);
            if ($existing) return ['message'=>'Pengajuan untuk rincian ini sudah tersedia.'];
            $data=['action_code'=>$action,'target_id'=>$id,'payload_hash'=>$snap['hash'],'requested_by'=>$actor,'reason'=>$this->text($p['reason']??'',255)];
            $aid=$this->put('fin_control_approval',$data);$this->audit('FINANCE_APPROVAL_REQUEST','fin_control_approval',$aid,[],$data,$actor,$ip);
            return ['message'=>'Diajukan. Pengguna lain dengan hak Persetujuan perlu memeriksa dan menyetujui.'];
        },$actor);
    }
    public function review_approval(array $p,int $actor,string $ip=''): array
    {
        return $this->write(function()use($p,$actor,$ip){
            $id=(int)($p['id']??0);$before=$this->rows('SELECT * FROM fin_control_approval WHERE id=?',[$id])[0]??[];
            $snap=$this->approval_lock((string)($before['action_code']??''),(int)($before['target_id']??0));
            $before=$this->rows('SELECT * FROM fin_control_approval WHERE id=? FOR UPDATE',[$id])[0];
            if ($before['status']!=='PENDING') throw new RuntimeException('Pengajuan sudah diproses.');
            if ($actor===(int)$before['requested_by'] || $actor===$snap['maker'] || $actor===(int)($snap['row']['created_by']??0)) throw new RuntimeException('Pembuat/pengaju tidak boleh menyetujui sendiri. Gunakan pengguna pemeriksa yang berbeda.');
            if (!hash_equals($before['payload_hash'],$snap['hash'])) throw new RuntimeException('Rincian atau kebijakan berubah. Ajukan ulang rincian terbaru.');
            $status=(string)($p['decision']??'');if (!in_array($status,['APPROVED','REJECTED'],true)) throw new RuntimeException('Keputusan tidak valid.');
            $data=['status'=>$status,'reviewed_by'=>$actor,'reviewed_at'=>date('Y-m-d H:i:s'),'reason'=>$this->text($p['reason']??'',255)];
            $this->put('fin_control_approval',$data,$id);$this->audit('FINANCE_APPROVAL_REVIEW','fin_control_approval',$id,$before,$data,$actor,$ip);
            return ['message'=>'Keputusan disimpan. Persetujuan tidak memposting kas; lanjutkan posting/VOID melalui modul asal.'];
        },$actor);
    }
    public function approvals(int $page=1): array
    {
        $page=max(1,min(100000,$page));$offset=($page-1)*25;
        $rows=$this->rows('SELECT a.*,u.username requester_name,v.username reviewer_name FROM fin_control_approval a LEFT JOIN auth_user u ON u.id=a.requested_by LEFT JOIN auth_user v ON v.id=a.reviewed_by ORDER BY a.id DESC LIMIT 26 OFFSET '.$offset);
        foreach($rows as &$row) {
            $table=$row['action_code']==='POST_CHARGE'?'fin_settlement_charge':'fin_account_mutation_log';
            $row['target']=$this->rows("SELECT * FROM $table WHERE id=?",[$row['target_id']])[0]??[];
            $aid=(int)($row['target']['account_id']??0);$row['account']=$this->rows('SELECT account_name FROM fin_company_account WHERE id=?',[$aid])[0]['account_name']??'';
            $row['stale']=false;
            if (in_array($row['status'],['PENDING','APPROVED'],true)) {
                try { $snapshot=Finance_settlement_control::approval_snapshot($this->db,$row['action_code'],(int)$row['target_id'],false);$row['stale']=!hash_equals($row['payload_hash'],$snapshot['hash']); }
                catch(Throwable $e) { $row['stale']=true; }
            }
        }unset($row);
        return ['rows'=>array_slice($rows,0,25),'page'=>$page,'more'=>count($rows)>25];
    }

    public function upload_evidence(int $caseId,array $file,int $actor,string $ip=''): array
    {
        require_once __DIR__.'/../libraries/Finance_control_evidence.php';
        return $this->write(function()use($caseId,$file,$actor,$ip){
            $case=$this->case_lock($caseId);$data=Finance_control_evidence::inspect_upload($file);
            $directory=Finance_control_evidence::directory(true);$name=bin2hex(random_bytes(32));$path=$directory.'/'.$name;
            $handle=@fopen($path,'xb');if(!$handle)throw new RuntimeException('Bukti belum dapat disimpan. Periksa ruang dan izin folder privat.');fclose($handle);@chmod($path,0600);
            if (!move_uploaded_file($file['tmp_name'],$path) || !chmod($path,0600)) throw new RuntimeException('Bukti gagal dipindahkan ke folder privat.');
            // Preserve evidence on a DB failure; never delete runtime files silently.
            log_message('info','Finance evidence staged '.$name.' for settlement '.$case['id']);
            $data+=['settlement_id'=>$caseId,'storage_name'=>$name,'created_by'=>$actor];$id=$this->put('fin_control_evidence',$data);
            $this->audit('FINANCE_EVIDENCE_UPLOAD','fin_control_evidence',$id,[],$data,$actor,$ip);
            return ['id'=>$id,'message'=>'Bukti privat disimpan. Pilih bukti ini pada rincian transfer atau biaya.'];
        },$actor);
    }
    public function evidence_record(int $id): array
    {
        return $this->rows('SELECT * FROM fin_control_evidence WHERE id=?',[$id])[0]??[];
    }
}
