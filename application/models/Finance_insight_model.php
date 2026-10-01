<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/../libraries/Finance_settlement_control.php';
require_once __DIR__ . '/../libraries/Finance_allocation_policy.php';

class Finance_insight_model extends CI_Model
{
    protected function rows(string $sql, array $params = []): array { return Finance_settlement_control::rows($this->db,$sql,$params); }
    public static function date_value($value): string
    {
        if (!is_string($value) || strlen($value)!==10) throw new RuntimeException('Tanggal tidak valid.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value) throw new RuntimeException('Tanggal tidak valid.');
        return $value;
    }
    protected function money($value): float
    {
        if (!is_scalar($value) || !preg_match('/\A\d+(?:\.\d{1,2})?\z/D',(string)$value) || (float)$value>999999999999.99) throw new RuntimeException('Nominal wajib angka positif/nol, maksimal dua desimal.');
        return round((float)$value,2);
    }
    protected function text($value, int $limit, bool $required=true): string
    {
        if (!is_scalar($value)) throw new RuntimeException('Isian teks tidak valid.');
        $value=trim((string)$value);
        if (($required && $value==='') || mb_strlen($value)>$limit) throw new RuntimeException('Referensi, judul, atau alasan kosong/terlalu panjang.');
        return $value;
    }
    protected function audit(string $action,string $table,int $id,array $before,array $after,int $actor,string $ip): void
    {
        if (!$this->db->insert('aud_transaction_log',['module_code'=>'FINANCE','action_code'=>$action,'entity_table'=>$table,'entity_id'=>$id,
            'actor_user_id'=>$actor ?: null,'source_ip'=>$ip ?: null,'before_payload'=>json_encode($before,JSON_THROW_ON_ERROR),
            'after_payload'=>json_encode($after,JSON_THROW_ON_ERROR),'notes'=>$after['notes'] ?? 'Kontrol keuangan'])) throw new RuntimeException('Audit gagal disimpan.');
    }
    protected function transaction(callable $work): array
    {
        if (!$this->db->table_exists('fin_settlement_control') || !$this->db->table_exists('fin_cash_plan')) return ['ok'=>false,'message'=>'Jalankan migrasi Kontrol Keuangan 2026-09-14a terlebih dahulu.'];
        if ($this->db->trans_begin()===false) return ['ok'=>false,'message'=>'Transaksi gagal dimulai.'];
        try {
            Finance_mutation_policy::assert_open_period($this->db,date('Y-m-d'));
            $result=$work();
            if ($this->db->trans_status()===false || $this->db->trans_commit()===false) throw new RuntimeException('Penyimpanan gagal; perubahan dibatalkan.');
            return $result+['ok'=>true];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok'=>false,'message'=>$this->db->error()['code'] ? 'Penyimpanan gagal atau referensi sudah dipakai. Muat ulang dan periksa kembali.' : $e->getMessage()];
        }
    }
    public function methods(): array
    {
        return $this->rows("SELECT pm.id,pm.method_name,pm.method_type,pm.company_account_id,a.account_name
            FROM pos_payment_method pm JOIN fin_company_account a ON a.id=pm.company_account_id
            WHERE pm.is_active=1 AND a.is_active=1 AND a.currency_code='IDR' ORDER BY pm.sort_order,pm.id");
    }
    public function settlement(string $date,int $methodId,int $page=1): array
    {
        self::date_value($date);
        if ($date>date('Y-m-d')) throw new RuntimeException('Tanggal pendapatan tidak boleh di masa depan.');
        $trace=Finance_settlement_control::trace($this->db,$date,$methodId);
        $case=Finance_settlement_control::ready($this->db) ? ($this->rows('SELECT * FROM fin_settlement_control WHERE revenue_date=? AND payment_method_id=?',[$date,$methodId])[0] ?? []) : [];
        $mutations=$case ? $this->rows("SELECT m.*,CASE WHEN " . Finance_settlement_control::effective() . " THEN 1 ELSE 0 END effective
            FROM fin_account_mutation_log m WHERE settlement_control_id=? ORDER BY mutation_date,id",[(int)$case['id']]) : [];
        $signed=0.; foreach ($mutations as $m) if ($m['effective']) $signed+=($m['mutation_type']==='IN'?1:-1)*(float)$m['amount'];
        $expected=round($trace['expected_amount']+$signed,2);
        $received=(float)($case['received_amount'] ?? 0);
        $remaining=round($expected-$received,2);
        $stale=$case && !hash_equals($case['source_fingerprint'],$trace['source_fingerprint']);
        $status=!$case?'BELUM_DITINJAU':($stale?'SUMBER_BERUBAH':(abs($remaining)<.005?'SESUAI':((int)$case['settlement_complete']?'SELISIH_PERLU_DITELUSURI':'BELUM_CAIR_PENUH')));
        $total=count($trace['payments']); $page=min(max(1,$page),max(1,(int)ceil($total/25)));
        return ['trace'=>$trace,'payment_rows'=>array_slice($trace['payments'],($page-1)*25,25),'page'=>$page,'pages'=>max(1,(int)ceil($total/25)),
            'case'=>$case,'mutations'=>$mutations,'adjustment'=>$signed,'expected_net'=>$expected,'received'=>$received,'remaining'=>$remaining,'status'=>$status,'stale'=>$stale];
    }
    public function save_settlement(array $p,int $actor,string $ip=''): array
    {
        return $this->transaction(function() use($p,$actor,$ip) {
            $date=self::date_value($p['revenue_date']??''); $due=self::date_value($p['due_date']??'');
            if ($date>date('Y-m-d') || $due<$date) throw new RuntimeException('Tanggal pendapatan/jadwal pencairan tidak sesuai.');
            $methodId=(int)($p['payment_method_id']??0);
            $method=$this->rows('SELECT * FROM pos_payment_method WHERE id=? AND is_active=1 FOR UPDATE',[$methodId])[0]??null;
            if (!$method) throw new RuntimeException('Metode pembayaran tidak aktif.');
            $account=$this->rows("SELECT * FROM fin_company_account WHERE id=? AND is_active=1 AND currency_code='IDR' FOR UPDATE",[(int)$method['company_account_id']])[0]??null;
            if (!$account) throw new RuntimeException('Rekening metode harus aktif dan memakai IDR.');
            $before=$this->rows('SELECT * FROM fin_settlement_control WHERE revenue_date=? AND payment_method_id=? FOR UPDATE',[$date,$methodId])[0]??[];
            $revision=(int)($p['revision']??0);
            if ($revision!==(int)($before['revision']??0)) throw new RuntimeException('Data sudah diubah pengguna lain. Muat ulang sebelum menyimpan.');
            if ($before && (int)$before['account_id']!==(int)$account['id']) throw new RuntimeException('Mapping rekening metode berubah. Perlu peninjauan administrator sebelum mengubah settlement historis.');
            $trace=Finance_settlement_control::trace($this->db,$date,$methodId,true);
            if (!hash_equals($trace['source_fingerprint'],(string)($p['source_fingerprint']??''))) throw new RuntimeException('Transaksi POS/refund berubah sejak halaman dibuka. Muat ulang dan periksa nominal.');
            if (!$trace['payments'] && !$trace['refunds']) throw new RuntimeException('Belum ada pembayaran/refund pada tanggal dan metode ini.');
            $data=['revenue_date'=>$date,'payment_method_id'=>$methodId,'account_id'=>(int)$account['id'],
                'provider_reference'=>strtoupper($this->text($p['provider_reference']??'',80)),'due_date'=>$due,
                'received_amount'=>$this->money($p['received_amount']??''),'settlement_complete'=>!empty($p['settlement_complete'])?1:0,
                'expected_amount'=>$trace['expected_amount'],'source_fingerprint'=>$trace['source_fingerprint'],'revision'=>$revision+1,
                'notes'=>$this->text($p['notes']??'',255),'updated_by'=>$actor?:null,'updated_at'=>date('Y-m-d H:i:s')];
            if (!empty($before['receipt_mode'])) {
                if (abs($data['received_amount']-(float)$before['received_amount'])>=.005) throw new RuntimeException('Total pencairan dihitung dari rincian transfer. Tambah/VOID rincian; jangan mengubah total langsung.');
                $data['received_amount']=$before['received_amount'];
            }
            if (!$before && Finance_settlement_control::operations_ready($this->db)) {
                if ($data['received_amount']>.005) throw new RuntimeException('Rekap baru dimulai dari nol. Simpan rekap, lalu catat dana diterima melalui rincian transfer.');
                $data['receipt_mode']=1;$data['receipt_opening_amount']=0;
            }
            if ($before) { $id=(int)$before['id']; $ok=$this->db->where('id',$id)->update('fin_settlement_control',$data); }
            else { $data['created_by']=$actor?:null; $ok=$this->db->insert('fin_settlement_control',$data); $id=(int)$this->db->insert_id(); }
            if (!$ok || !$id) throw new RuntimeException('Referensi settlement sudah dipakai atau tidak dapat disimpan.');
            $this->audit('SETTLEMENT_REVIEW','fin_settlement_control',$id,$before,$data,$actor,$ip);
            return ['id'=>$id,'message'=>'Konfirmasi settlement disimpan. Tidak ada pemasukan baru atau perubahan saldo rekening.'];
        });
    }
    public function save_plan(array $p,int $actor,string $ip=''): array
    {
        return $this->transaction(function() use($p,$actor,$ip) {
            $id=(int)($p['id']??0); $revision=(int)($p['revision']??0);
            $before=$id?$this->rows('SELECT * FROM fin_cash_plan WHERE id=? FOR UPDATE',[$id])[0]??[]:[];
            if (($id && !$before) || $revision!==(int)($before['revision']??0)) throw new RuntimeException('Rencana berubah/tidak ditemukan. Muat ulang dahulu.');
            $key=(string)($p['request_key']??'');
            if (!preg_match('/\A[a-f0-9]{32}\z/D',$key)) throw new RuntimeException('Identitas formulir tidak valid.');
            $direction=(string)($p['direction']??''); $certainty=(string)($p['certainty']??''); $status=(string)($p['status']??'OPEN');
            if (!in_array($direction,['IN','OUT'],true)||!in_array($certainty,['COMMITTED','ESTIMATE'],true)||!in_array($status,['OPEN','DONE','CANCELLED'],true)) throw new RuntimeException('Arah, kepastian, atau status rencana tidak valid.');
            $amount=$this->money($p['amount']??''); if ($amount<=0) throw new RuntimeException('Nominal rencana harus lebih dari nol.');
            if ($id && Finance_settlement_control::operations_ready($this->db)) {
                $links=$this->rows('SELECT id FROM fin_cash_plan_realization WHERE plan_id=? AND active_mutation_id IS NOT NULL LIMIT 1 FOR UPDATE',[$id]);
                if (Finance_allocation_policy::ready($this->db)) $links=array_merge($links,$this->rows('SELECT id FROM fin_plan_allocation WHERE plan_id=? AND active_key IS NOT NULL LIMIT 1 FOR UPDATE',[$id]));
                if ($links && $direction!==$before['direction']) throw new RuntimeException('Arah tidak boleh diubah selama masih ada tautan realisasi. Lepaskan tautan dahulu.');
                if ($links && $status==='DONE') throw new RuntimeException('Rencana tertaut selesai otomatis saat realisasi cukup. Pilih Direncanakan atau Dibatalkan.');
            }
            $data=['title'=>$this->text($p['title']??'',160),'direction'=>$direction,'amount'=>$amount,'due_date'=>self::date_value($p['due_date']??''),
                'certainty'=>$certainty,'status'=>$status,'notes'=>$this->text($p['notes']??'',255),'revision'=>$revision+1,'updated_by'=>$actor?:null,'updated_at'=>date('Y-m-d H:i:s')];
            if (!$id) {
                $prior=$this->rows('SELECT * FROM fin_cash_plan WHERE request_key=? FOR UPDATE',[$key])[0]??null;
                if ($prior) {
                    foreach (['title','direction','amount','due_date','certainty','status','notes'] as $field) if ((string)$prior[$field] !== (string)$data[$field] && !($field==='amount' && (float)$prior[$field]===$amount)) throw new RuntimeException('Identitas formulir sudah dipakai untuk rencana lain. Muat ulang.');
                    return ['id'=>(int)$prior['id'],'message'=>'Rencana sudah tersimpan; tidak digandakan.'];
                }
                $data['request_key']=$key;$data['created_by']=$actor?:null;
                $ok=$this->db->insert('fin_cash_plan',$data);$id=(int)$this->db->insert_id();
            } else $ok=$this->db->where('id',$id)->update('fin_cash_plan',$data);
            if (!$ok || !$id) throw new RuntimeException('Rencana gagal disimpan.');
            $this->audit('CASH_PLAN_SAVE','fin_cash_plan',$id,$before,$data,$actor,$ip);
            return ['id'=>$id,'message'=>'Rencana tersimpan; tidak memposting pembayaran atau mengubah saldo.'];
        });
    }
    public function adjustment_permission(int $id): ?string
    {
        $row=$this->rows('SELECT ref_module,settlement_control_id FROM fin_account_mutation_log WHERE id=?',[$id])[0]??[];
        if (empty($row['settlement_control_id'])) return null;
        return ['FINANCE'=>'purchase.order.index','FINANCE_RECON'=>'finance.cash_reconciliation.index','REVENUE_RECON'=>'finance.revenue_reconciliation.index'][$row['ref_module']]??null;
    }
    /** Only reverse a linked manual adjustment, never POS/payments/transfers. */
    public function void_adjustment(array $p,int $actor,string $ip=''): array
    {
        $id=(int)($p['mutation_id']??0);
        $original=$id>0?($this->rows('SELECT * FROM fin_account_mutation_log WHERE id=?',[$id])[0]??[]):[];
        if (!$original || empty($original['settlement_control_id']) || !Finance_mutation_policy::manual_module((string)$original['ref_module'])) return ['ok'=>false,'message'=>'Hanya penyesuaian manual tertaut settlement yang dapat dibatalkan di sini.'];
        return $this->transaction(function()use($p,$actor,$ip,$id,$original){
            Finance_mutation_policy::assert_open_period($this->db,(string)$original['mutation_date']);
            $account=$this->rows('SELECT * FROM fin_company_account WHERE id=? AND is_active=1 FOR UPDATE',[(int)$original['account_id']])[0]??[];
            $row=$this->rows('SELECT * FROM fin_account_mutation_log WHERE id=? FOR UPDATE',[$id])[0]??[];
            if (!$account || !$row || !empty($row['reversal_of_mutation_id']) || $row['mutation_date']!==$original['mutation_date']) throw new RuntimeException('Rekening tidak aktif atau penyesuaian tidak dapat dibatalkan.');
            $case=$this->rows('SELECT * FROM fin_settlement_control WHERE id=? FOR UPDATE',[(int)$row['settlement_control_id']])[0]??[];
            if (!$case || (int)$case['account_id']!==(int)$account['id']) throw new RuntimeException('Referensi settlement tidak sesuai.');
            $reversed=$this->rows('SELECT id FROM fin_account_mutation_log WHERE reversal_of_mutation_id=? FOR UPDATE',[$id]);
            if ($reversed) return ['message'=>'Penyesuaian sudah dibatalkan; saldo tidak diubah lagi.','replayed'=>true];
            $reason=$this->text($p['reason']??'',200);
            $amount=round((float)$row['amount'],2);$direction=$row['mutation_type']==='OUT'?'IN':'OUT';
            if ($amount<=0 || !in_array($row['mutation_type'],['IN','OUT'],true)) throw new RuntimeException('Nominal/arah penyesuaian tidak valid.');
            Finance_settlement_control::consume_approval($this->db,'VOID_ADJUSTMENT',$id);
            $before=round((float)$account['current_balance'],2);$after=round($before+($direction==='IN'?$amount:-$amount),2);
            if ($after<0) throw new RuntimeException('Saldo tidak cukup untuk membatalkan pemasukan ini.');
            $reversal=['mutation_no'=>'SCVOID-'.date('Ymd').'-'.$id,'mutation_date'=>date('Y-m-d'),'account_id'=>(int)$account['id'],
                'mutation_type'=>$direction,'amount'=>$amount,'balance_before'=>$before,'balance_after'=>$after,'ref_module'=>$row['ref_module'],
                'ref_table'=>'fin_account_mutation_log','ref_id'=>$id,'ref_no'=>$row['mutation_no'],'settlement_control_id'=>(int)$case['id'],
                'reversal_of_mutation_id'=>$id,'notes'=>'VOID: '.$reason,'created_by'=>$actor?:null];
            if (!$this->db->where('id',$account['id'])->update('fin_company_account',['current_balance'=>$after]) || !$this->db->insert('fin_account_mutation_log',$reversal)) throw new RuntimeException('Pembatalan gagal; perubahan saldo dibatalkan.');
            $reversalId=(int)$this->db->insert_id();
            $this->audit('SETTLEMENT_ADJUSTMENT_VOID','fin_account_mutation_log',$id,$row,$reversal+['id'=>$reversalId],$actor,$ip);
            return ['message'=>'Penyesuaian dibatalkan dengan mutasi pembalikan. Saldo diperbarui; riwayat tetap ada. Koreksi dapat diposting ulang dari modul asal.'];
        });
    }
    private function settlement_balances(): array
    {
        if (!Finance_settlement_control::ready($this->db)) return [];
        return $this->rows("SELECT c.*,pm.method_name,a.currency_code,a.is_active account_active,COALESCE(x.adjustment,0) adjustment,
            c.expected_amount+COALESCE(x.adjustment,0)-c.received_amount remaining
            FROM fin_settlement_control c JOIN pos_payment_method pm ON pm.id=c.payment_method_id
            JOIN fin_company_account a ON a.id=c.account_id
            LEFT JOIN (SELECT m.settlement_control_id,SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END) adjustment
            FROM fin_account_mutation_log m WHERE " . Finance_settlement_control::effective() . " GROUP BY m.settlement_control_id) x ON x.settlement_control_id=c.id
            ORDER BY c.due_date,c.id");
    }
    public function forecast(int $days=7): array
    {
        $days=in_array($days,[7,30],true)?$days:7; $today=date('Y-m-d');$end=date('Y-m-d',strtotime($today.' +'.($days-1).' days'));
        $accounts=$this->rows('SELECT id,account_name,currency_code,current_balance FROM fin_company_account WHERE is_active=1');
        $book=0.;$foreign=0;foreach($accounts as $a)if($a['currency_code']==='IDR')$book+=(float)$a['current_balance'];else$foreign++;
        $events=[];$warnings=[];$pending=0.;
        foreach($this->settlement_balances() as $c) {
            if ($c['currency_code']!=='IDR' || !(int)$c['account_active']) { $warnings[]='Settlement #'.$c['id'].' dikecualikan karena rekening non-IDR/nonaktif.';continue; }
            $trace=Finance_settlement_control::trace($this->db,$c['revenue_date'],(int)$c['payment_method_id']);
            $remaining=max(0,round($trace['expected_amount']+(float)$c['adjustment']-(float)$c['received_amount'],2));
            $pending+=$remaining;
            if (!hash_equals($c['source_fingerprint'],$trace['source_fingerprint'])) $warnings[]='Settlement #'.$c['id'].' berubah; nominal proyeksi perlu ditinjau.';
            if ($remaining>.005 && !(int)$c['settlement_complete']) $events[]=['key'=>'SETTLEMENT:'.$c['id'],'date'=>$c['due_date'],'direction'=>'IN','amount'=>$remaining,'certainty'=>'ESTIMATE','label'=>'Settlement '.$c['provider_reference'],'url'=>'finance-reports/control?tab=settlement&date='.$c['revenue_date'].'&method_id='.$c['payment_method_id']];
            elseif ($remaining>.005) $warnings[]='Settlement #'.$c['id'].' dinyatakan lengkap tetapi selisih belum dijelaskan; tidak diproyeksikan sebagai dana akan cair.';
        }
        foreach(['fin_payable'=>['payable_no','OUT','COMMITTED','finance/utang'],'fin_receivable'=>['receivable_no','IN','ESTIMATE','finance/piutang']] as $table=>$cfg) {
            foreach($this->rows("SELECT h.id,h.{$cfg[0]} doc,h.due_date,h.outstanding_amount,a.currency_code FROM $table h LEFT JOIN fin_company_account a ON a.id=h.company_account_id WHERE h.status IN ('OPEN','PARTIAL') AND h.outstanding_amount>0") as $row) {
                if ($row['currency_code'] && $row['currency_code']!=='IDR') { $warnings[]=$row['doc'].' tidak dihitung (mata uang bukan IDR).';continue; }
                if (!$row['due_date']) { $warnings[]=$row['doc'].' belum memiliki jatuh tempo.';continue; }
                $events[]=['key'=>$table.':'.$row['id'],'date'=>$row['due_date'],'direction'=>$cfg[1],'amount'=>(float)$row['outstanding_amount'],'certainty'=>$cfg[2],'label'=>$row['doc'],'url'=>$cfg[3]];
            }
        }
        $policy=Finance_settlement_control::policy($this->db);
        foreach($this->rows("SELECT pp.id,pp.period_code,pp.period_end,SUM(pr.net_pay) amount FROM pay_payroll_result pr JOIN pay_payroll_period pp ON pp.id=pr.payroll_period_id
            WHERE pr.status='FINALIZED' AND pp.status IN ('FINALIZED','PAID','CLOSED') AND pr.paid_at IS NULL GROUP BY pp.id,pp.period_code,pp.period_end") as $row) {
            $scheduled=self::payroll_forecast_date($row['period_end'],(int)$policy['payroll_day']);
            $events[]=['key'=>'PAYROLL:'.$row['id'],'date'=>$scheduled,'direction'=>'OUT','amount'=>(float)$row['amount'],'certainty'=>'ESTIMATE','label'=>'Payroll '.$row['period_code'].' (jadwal perkiraan tanggal '.$policy['payroll_day'].')','url'=>'payroll'];
        }
        $plans=$this->db->table_exists('fin_cash_plan')?$this->rows('SELECT * FROM fin_cash_plan ORDER BY due_date DESC,id DESC'):[];
        foreach($plans as &$p) {
            $p['realizations']=Finance_settlement_control::operations_ready($this->db)?$this->rows('SELECT r.*,m.mutation_no,m.amount,m.mutation_date,CASE WHEN r.active_mutation_id IS NOT NULL AND '.Finance_settlement_control::effective().' THEN 1 ELSE 0 END effective FROM fin_cash_plan_realization r JOIN fin_account_mutation_log m ON m.id=r.mutation_id WHERE r.plan_id=? ORDER BY r.id',[$p['id']]):[];
            if (Finance_allocation_policy::ready($this->db)) {
                $parts=$this->rows('SELECT r.*,m.mutation_no,m.mutation_date,CASE WHEN r.active_key IS NOT NULL THEN r.mutation_id ELSE NULL END active_mutation_id,CASE WHEN r.active_key IS NOT NULL AND '.Finance_settlement_control::effective().' THEN 1 ELSE 0 END effective FROM fin_plan_allocation r JOIN fin_account_mutation_log m ON m.id=r.mutation_id WHERE r.plan_id=? ORDER BY r.id',[$p['id']]);
                foreach ($parts as &$part) $part['allocation_link']=true; unset($part);
                $p['realizations']=array_merge($p['realizations'],$parts);
            }
            $p['actual']=0.;$hasLinks=false;foreach($p['realizations'] as $link){if($link['effective'])$p['actual']+=(float)$link['amount'];if($link['active_mutation_id'])$hasLinks=true;}
            $p['remaining']=max(0,round((float)$p['amount']-$p['actual'],2));$p['overage']=max(0,round($p['actual']-(float)$p['amount'],2));
            $p['computed_status']=$p['status']==='CANCELLED'?'Dibatalkan':($hasLinks?($p['remaining']>.005?'Belum lunas':'Selesai berdasarkan mutasi'):($p['status']==='DONE'?'Selesai manual (belum tertaut)':'Direncanakan'));
            if ($p['status']!=='CANCELLED' && ($p['status']==='OPEN'||$hasLinks) && $p['remaining']>.005) $events[]=['key'=>'PLAN:'.$p['id'],'date'=>$p['due_date'],'direction'=>$p['direction'],'amount'=>$p['remaining'],'certainty'=>$p['certainty'],'label'=>$p['title'],'url'=>'finance-reports/control?tab=cash-plan'];
        }unset($p);
        $opening=round($book-$pending,2);$committed=$opening;$projected=$opening;$daily=[];$visible=[];
        for($n=0;$n<$days;$n++){
            $date=date('Y-m-d',strtotime($today.' +'.$n.' days'));$in=0.;$out=0.;$certain=0.;
            foreach($events as $event){ $scheduled=max($today,$event['date']);if($scheduled!==$date)continue;
                $signed=$event['direction']==='IN'?$event['amount']:-$event['amount'];
                if($event['direction']==='IN')$in+=$event['amount'];else$out+=$event['amount'];
                if($event['certainty']==='COMMITTED')$certain+=$signed;
                $visible[]=$event+['overdue'=>$event['date']<$today];
            }
            $committed=round($committed+$certain,2);$projected=round($projected+$in-$out,2);
            $daily[]=['date'=>$date,'in'=>$in,'out'=>$out,'committed'=>$committed,'projected'=>$projected];
        }
        if($foreign)$warnings[]=$foreign.' rekening non-IDR dikecualikan; tidak ada konversi kurs otomatis.';
        $warnings[]='POS sudah membukukan pembayaran. Saldo awal indikatif dikurangi settlement terlacak yang belum cair, lalu proyeksi menambahnya saat dijadwalkan; bukan pemasukan kedua.';
        $warnings[]='Jangan menggandakan tagihan/settlement/payroll otomatis dalam rencana manual. Status Selesai hanya menutup rencana, bukan membayar.';
        $warnings[]='Payroll FINALIZED belum dibayar mengikuti tanggal proyeksi '.$policy['payroll_day'].' setelah akhir periode (dibatasi akhir bulan). Atur di tab Pengaturan. Ini perkiraan, bukan instruksi membayar atau perubahan gaji.';
        $warnings[]='Saldo tersedia hanya indikatif: pembayaran platform yang belum dibuatkan Kontrol Settlement belum dapat dipisahkan dari saldo buku. Lengkapi cakupan melalui tab Kualitas laporan.';
        return compact('days','today','end','book','pending','opening','committed','projected','daily','visible','plans','warnings');
    }

    public static function payroll_forecast_date(string $periodEnd,int $day): string
    {
        self::date_value($periodEnd);$day=max(1,min(31,$day));$base=new DateTimeImmutable(substr($periodEnd,0,7).'-01');
        for($i=0;$i<2;$i++) {
            $date=$base->format('Y-m-').str_pad((string)min($day,(int)$base->format('t')),2,'0',STR_PAD_LEFT);
            if ($date>$periodEnd) return $date;
            $base=$base->modify('first day of next month');
        }
        throw new RuntimeException('Jadwal payroll tidak dapat dihitung.');
    }

    public function quality(string $from,string $to): array
    {
        $issues=[]; $effective=Finance_settlement_control::effective();
        $coverage=$this->rows("SELECT COUNT(*) amount FROM (SELECT DATE(p.paid_at) revenue_date,pl.payment_method_id
            FROM pos_payment p JOIN pos_payment_line pl ON pl.payment_id=p.id AND pl.status='PAID'
            JOIN pos_payment_method pm ON pm.id=pl.payment_method_id
            LEFT JOIN fin_settlement_control c ON c.revenue_date=DATE(p.paid_at) AND c.payment_method_id=pl.payment_method_id
            WHERE p.payment_status='PAID' AND p.payment_type IN ('FINAL','DEPOSIT') AND pm.method_type NOT IN ('CASH','COMPLIMENT','DEPOSIT')
            AND p.paid_at>=? AND p.paid_at<? AND c.id IS NULL GROUP BY DATE(p.paid_at),pl.payment_method_id) missing",[$from,date('Y-m-d',strtotime($to.' +1 day'))])[0];
        $issues[]=['title'=>'Hari/metode non-tunai belum ditinjau','count'=>(int)$coverage['amount'],'level'=>'WARNING','action'=>'Belum ada Kontrol Settlement untuk kelompok ini; bukan bukti dana pasti tertunda. Pilih tanggal/metode untuk konfirmasi.','url'=>'finance-reports/control?tab=settlement'];
        $unclassified=Finance_mutation_policy::expressions(true)['unclassified_count'];
        $summary=$this->rows("SELECT $unclassified amount FROM fin_account_mutation_log WHERE mutation_date>=? AND mutation_date<=?
            AND reversal_of_mutation_id IS NULL AND NOT EXISTS (SELECT 1 FROM fin_account_mutation_log r WHERE r.reversal_of_mutation_id=fin_account_mutation_log.id)",[$from,$to])[0]??[];
        $issues[]=['title'=>'Mutasi belum berkategori','count'=>(int)($summary['amount']??0),'level'=>'WARNING','action'=>'Klasifikasikan dengan alasan; saldo tidak diubah.','url'=>'finance/mutations?date_from='.$from.'&date_to='.$to];
        $mismatch=$this->rows("SELECT a.id,a.account_name FROM fin_company_account a LEFT JOIN
            (SELECT account_id,SUM(CASE WHEN mutation_type='IN' THEN amount ELSE -amount END) amount FROM fin_account_mutation_log GROUP BY account_id) m ON m.account_id=a.id
            WHERE a.is_active=1 AND ABS(a.current_balance-a.opening_balance-COALESCE(m.amount,0))>.01");
        $issues[]=['title'=>'Saldo rekening vs riwayat mutasi','count'=>count($mismatch),'level'=>'WARNING','action'=>'Periksa saldo awal, pencatatan historis dan rekonsiliasi; tidak otomatis dianggap biaya.','url'=>'finance/mutations'];
        $pending=0;$stale=0;$difference=0;
        foreach($this->settlement_balances() as $c){ if($c['revenue_date']<$from||$c['revenue_date']>$to)continue;
            $trace=Finance_settlement_control::trace($this->db,$c['revenue_date'],(int)$c['payment_method_id']);
            if(!hash_equals($trace['source_fingerprint'],$c['source_fingerprint']))$stale++;
            if(abs($trace['expected_amount']+(float)$c['adjustment']-(float)$c['received_amount'])>.005){if($c['settlement_complete'])$difference++;else$pending++;}
        }
        foreach([['Settlement belum cair penuh',$pending],['Sumber settlement berubah',$stale],['Settlement lengkap tetapi masih berselisih',$difference]] as [$title,$count])$issues[]=['title'=>$title,'count'=>$count,'level'=>'WARNING','action'=>'Telusuri bukti penerimaan dan biaya; jangan langsung memotong kas.','url'=>'finance-reports/control?tab=settlement'];
        $this->load->model('Pos_report_model');
        $audit=$this->Pos_report_model->sales_hpp_integrity_audit(['date_from'=>$from,'date_to'=>$to,'limit'=>10]);
        foreach($audit['checks']??[] as $item) $issues[]=['title'=>$item['title'],'count'=>(int)($item['issue_count']??0),'level'=>$item['status'],'action'=>$item['message']??'Periksa audit HPP transaksi.','url'=>'pos/reports/sales-audit?date_from='.$from.'&date_to='.$to];
        return ['issues'=>$issues,'account_samples'=>array_slice($mismatch,0,20),'hpp_audit'=>$audit];
    }
    public function profit_loss(string $from,string $to): array
    {
        $this->load->model('Pos_report_model');
        $basis=$this->Pos_report_model->financial_profit_loss_basis($from,$to);
        $parts=[];foreach(Finance_mutation_policy::expressions(true) as $key=>$expression)$parts[]="$expression AS $key";
        $m=$this->rows('SELECT '.implode(',',$parts).' FROM fin_account_mutation_log WHERE mutation_date>=? AND mutation_date<=?
            AND reversal_of_mutation_id IS NULL AND NOT EXISTS (SELECT 1 FROM fin_account_mutation_log r WHERE r.reversal_of_mutation_id=fin_account_mutation_log.id)',[$from,$to])[0]??[];
        $m=Finance_mutation_policy::totals($m);
        $payroll=$this->rows("SELECT COALESCE(SUM(pr.gross_pay-pr.late_deduction_total-pr.alpha_deduction_total-pr.manual_deduction_total+pr.rounding_adjustment),0) amount,
            COUNT(*) count FROM pay_payroll_result pr JOIN pay_payroll_period pp ON pp.id=pr.payroll_period_id
            WHERE pp.period_start>=? AND pp.period_end<=? AND pr.status IN ('FINALIZED','PAID')",[$from,$to])[0];
        $salary=(float)$payroll['amount'];
        $result=round($basis['net_sales']-$basis['hpp']+$m['other_income_total']-$m['other_expense_total']-$salary,2);
        $warnings=['Laba-rugi manajemen berbasis HPP transaksi lunas; bukan laporan keuangan akrual lengkap/tersahkan. Belum mencakup penyusutan, pajak penghasilan dan akrual biaya di luar sumber yang ditampilkan.',
            'Pajak penjualan dipisahkan dari pendapatan; service dan pembulatan mengikuti tagihan POS. DP tanpa transaksi final tidak menjadi penjualan pada laporan ini.',
            'Biaya lain mengikuti mutasi berkategori pada tanggalnya. Pembelian persediaan, modal/prive, transfer, pelunasan hutang/piutang, dan pencairan payroll tidak dipotong lagi.',
            'Beban gaji memakai hasil FINALIZED/PAID periode yang seluruhnya tercakup; pelunasan kasbon tidak mengurangi beban gaji. Rentang parsial bukan prorata otomatis.',
            'Refund diakui pada tanggal refund; HPP refund dan koreksi defisit mengikuti tanggal pengakuan masing-masing. Snapshot periode tertutup tidak ditulis ulang.'];
        if(!$payroll['count'])$warnings[]='Belum ada payroll final untuk periode ini; angka gaji nol bukan berarti tidak ada beban gaji.';
        if($m['unclassified_count'])$warnings[]='Ada '.$m['unclassified_count'].' mutasi belum berkategori, belum termasuk pendapatan/biaya laba-rugi ini.';
        return compact('basis','m','salary','result','warnings');
    }
}
