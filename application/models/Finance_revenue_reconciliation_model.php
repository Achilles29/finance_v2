<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/../libraries/Finance_mutation_policy.php';

require_once __DIR__.'/../libraries/Finance_revenue_transfer.php';
class Finance_revenue_reconciliation_model extends CI_Model
{
    use Finance_revenue_transfer;
    private const HEADER = 'fin_revenue_reconciliation';
    private const LINE = 'fin_revenue_reconciliation_line';
    private const METHOD = 'pos_payment_method';
    private const ACCOUNT = 'fin_company_account';
    private const MUTATION = 'fin_account_mutation_log';

    private function ready(): bool
    {
        return $this->db->table_exists(self::HEADER) && $this->db->table_exists(self::LINE)
            && $this->db->table_exists(self::METHOD) && $this->db->table_exists(self::ACCOUNT)
            && $this->db->table_exists(self::MUTATION);
    }

    private function date($value): ?string
    {
        $value = trim((string)$value);
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function amount($value): ?float
    {
        if ($value === null || trim((string)$value) === '') return null;
        $value = preg_replace('/[^0-9,\.\-]/', '', str_ireplace('rp', '', (string)$value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(',', '.', str_replace('.', '', $value)) : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') === 1 && strlen($value) - strrpos($value, '.') - 1 === 3) {
            $value = str_replace('.', '', $value);
        }
        return is_numeric($value) && is_finite((float)$value) && abs((float)$value) < 1.0e16 ? round((float)$value, 2) : null;
    }

    private function note($value): ?string
    {
        $value = trim(strip_tags((string)$value));
        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function header(int $id): ?array
    {
        return $id > 0 ? ($this->db->get_where(self::HEADER, ['id' => $id], 1)->row_array() ?: null) : null;
    }

    private function latest(string $reconDate, string $revenueDate): ?array
    {
        return $this->db->where(['reconciliation_date' => $reconDate, 'revenue_date' => $revenueDate])
            ->order_by('round_no', 'DESC')->order_by('id', 'DESC')->get(self::HEADER, 1)->row_array() ?: null;
    }

    private function expected_by_method(string $revenueDate): array
    {
        $sql = "SELECT pl.payment_method_id, ROUND(COALESCE(SUM(pl.amount),0),2) expected_amount,
                       COUNT(DISTINCT p.id) transaction_count
                FROM pos_payment p
                JOIN pos_payment_line pl ON pl.payment_id=p.id AND pl.status='PAID'
                WHERE p.payment_status='PAID' AND p.payment_type IN ('FINAL','DEPOSIT')
                  AND p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY)
                GROUP BY pl.payment_method_id";
        $rows = $this->db->query($sql, [$revenueDate, $revenueDate])->result_array();
        $result = [];
        foreach ($rows as $row) $result[(int)$row['payment_method_id']] = $row;
        if ($this->db->table_exists('pos_refund')) {
            $refunds = $this->db->select('payment_method_id, COALESCE(SUM(refund_amount),0) refund_amount', false)
                ->from('pos_refund')->where('refund_status', 'POSTED')
                ->where('refunded_at >=', $revenueDate)->where('refunded_at <', date('Y-m-d', strtotime($revenueDate . ' +1 day')))
                ->where('payment_method_id IS NOT NULL', null, false)->group_by('payment_method_id')->get()->result_array();
            foreach ($refunds as $refund) {
                $id = (int)$refund['payment_method_id'];
                if (!isset($result[$id])) $result[$id] = ['payment_method_id'=>$id, 'expected_amount'=>0, 'transaction_count'=>0];
                $result[$id]['expected_amount'] = round((float)$result[$id]['expected_amount'] - (float)$refund['refund_amount'], 2);
            }
        }
        return $result;
    }

    private function posted_adjustment_map(string $revenueDate, bool $lock = false): array
    {
        $suffix=$lock?' FOR UPDATE':'';
        $effective = $this->db->field_exists('reversal_of_mutation_id', self::MUTATION)
            ? ' AND m.reversal_of_mutation_id IS NULL AND NOT EXISTS (SELECT 1 FROM fin_account_mutation_log r WHERE r.reversal_of_mutation_id=m.id)' : '';
        $rows = $this->db->query("SELECT l.payment_method_id, SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END) adjusted
            FROM fin_revenue_reconciliation_line l
            JOIN fin_revenue_reconciliation h ON h.id=l.reconciliation_id
            JOIN fin_account_mutation_log m ON m.id=l.mutation_id AND m.ref_module IN ('REVENUE_RECON','FINANCE_TRANSFER')
            WHERE h.revenue_date=? AND l.status='POSTED' $effective GROUP BY l.payment_method_id" . $suffix, [$revenueDate])->result_array();
        $map = array_column($rows, 'adjusted', 'payment_method_id');
        if ($this->transfer_ready()) {
            // New transfers belong to one recon line. Optional attribution carries only
            // the counter leg to another method; old paired-line transfers already count above.
            $counter=$this->db->query("SELECT l.counter_payment_method_id payment_method_id,
                SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END) adjusted
                FROM fin_revenue_reconciliation_line l JOIN fin_revenue_reconciliation h ON h.id=l.reconciliation_id
                JOIN fin_account_mutation_log m ON m.id=l.counter_mutation_id AND m.ref_module='FINANCE_TRANSFER'
                    AND m.ref_table='fin_revenue_reconciliation_line' AND m.ref_id=l.id
                WHERE h.revenue_date=? AND l.status='POSTED' AND l.counter_payment_method_id IS NOT NULL
                $effective GROUP BY l.counter_payment_method_id".$suffix,[$revenueDate])->result_array();
            foreach($counter as $item) $map[$item['payment_method_id']]=(float)($map[$item['payment_method_id']]??0)+(float)$item['adjusted'];
        }
        require_once APPPATH . 'libraries/Finance_settlement_control.php';
        if (Finance_settlement_control::ready($this->db)) {
            $linked = Finance_settlement_control::rows($this->db,"SELECT c.payment_method_id,SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END) adjusted
                FROM fin_settlement_control c JOIN fin_account_mutation_log m ON m.settlement_control_id=c.id
                WHERE c.revenue_date=? AND m.ref_module IN ('FINANCE','FINANCE_RECON') AND " . Finance_settlement_control::effective() . ' GROUP BY c.payment_method_id' . $suffix,[$revenueDate]);
            foreach ($linked as $row) $map[$row['payment_method_id']]=(float)($map[$row['payment_method_id']]??0)+(float)$row['adjusted'];
        }
        return $map;
    }

    public function dashboard(string $reconDate, string $revenueDate, int $headerId = 0): array
    {
        if (!$this->ready()) return ['ok' => false, 'message' => 'Migration rekonsiliasi pendapatan belum dijalankan.'];
        $reconDate = $this->date($reconDate) ?: date('Y-m-d');
        $revenueDate = $this->date($revenueDate) ?: date('Y-m-d', strtotime('-1 day'));
        $header = $this->header($headerId);
        if (!$header || $header['reconciliation_date'] !== $reconDate || $header['revenue_date'] !== $revenueDate) {
            $header = $this->latest($reconDate, $revenueDate);
        }
        $lineMap = [];
        if ($header) {
            foreach ($this->db->get_where(self::LINE, ['reconciliation_id' => (int)$header['id']])->result_array() as $line) {
                $lineMap[(int)$line['payment_method_id']] = $line;
            }
        }
        $expected = $this->expected_by_method($revenueDate);
        $adjusted = $this->posted_adjustment_map($revenueDate);
        $methods = $this->db->select('pm.*, a.account_code, a.account_name, COALESCE(cfg.settlement_delay_days, IF(pm.method_type="CASH",0,1)) settlement_delay_days', false)
            ->from(self::METHOD . ' pm')->join(self::ACCOUNT . ' a', 'a.id=pm.company_account_id', 'left')
            ->join('fin_revenue_reconciliation_method cfg', 'cfg.payment_method_id=pm.id', 'left')
            ->where('pm.is_active', 1)->where('(cfg.is_enabled IS NULL OR cfg.is_enabled=1)', null, false)
            ->order_by('pm.sort_order', 'ASC')->order_by('pm.method_name', 'ASC')->get()->result_array();
        $rows = [];
        $summary = ['expected_total'=>0.0,'actual_total'=>0.0,'difference_total'=>0.0,'checked'=>0,'matched'=>0,'open'=>0,'posted'=>0];
        foreach ($methods as $method) {
            $id = (int)$method['id']; $line = $lineMap[$id] ?? [];
            $expectedAmount = round((float)($expected[$id]['expected_amount'] ?? 0), 2);
            $actual = array_key_exists('actual_amount', $line) && $line['actual_amount'] !== null ? round((float)$line['actual_amount'], 2) : null;
            $difference = $actual === null ? null : round($actual - $expectedAmount - (float)($adjusted[$id] ?? 0), 2);
            // Keep the posting snapshot as history, not as the remaining difference.
            $postedDifference=($line['status']??'')==='POSTED'?(float)$line['difference_amount']:null;
            $status = $actual === null ? 'UNCHECKED' : ((string)($line['status'] ?? '') === 'POSTED' ? 'POSTED' : (abs($difference) < .005 ? 'MATCHED' : 'OPEN'));
            $rows[] = array_merge($method, $line, [
                'line_id'=>(int)($line['id'] ?? 0), 'payment_method_id'=>$id,
                'expected_amount'=>$expectedAmount, 'actual_amount'=>$actual, 'difference_amount'=>$difference,
                'posted_difference_amount'=>$postedDifference,
                'transaction_count'=>(int)($expected[$id]['transaction_count'] ?? 0), 'status'=>$status,
                'adjusted_total' => (float)($adjusted[$id] ?? 0),
                'account_id'=>(int)($line['account_id'] ?? $method['company_account_id'] ?? 0),
            ]);
            $summary['expected_total'] += $expectedAmount;
            if ($actual !== null) { $summary['actual_total'] += $actual; $summary['difference_total'] += $difference; $summary['checked']++; }
            if ($status === 'MATCHED') $summary['matched']++; elseif ($status === 'OPEN') $summary['open']++; elseif ($status === 'POSTED') $summary['posted']++;
        }
        foreach (['expected_total','actual_total','difference_total'] as $key) $summary[$key] = round($summary[$key], 2);
        $accounts = $this->db->where('is_active', 1)->order_by('account_name')->get(self::ACCOUNT)->result_array();
        $recent = $this->db->order_by('reconciliation_date','DESC')->order_by('id','DESC')->get(self::HEADER, 8)->result_array();
        return compact('header','rows','summary','accounts','recent') + ['ok'=>true,'transfer_ready'=>$this->transfer_ready(),'reconciliation_date'=>$reconDate,'revenue_date'=>$revenueDate];
    }

    private function create_header(string $reconDate, string $revenueDate, int $actor): array
    {
        $last = $this->db->select_max('round_no','last')->get_where(self::HEADER, ['reconciliation_date'=>$reconDate,'revenue_date'=>$revenueDate])->row_array();
        $round = max(1, (int)($last['last'] ?? 0) + 1);
        $no = 'REK-PDT-' . date('Ymd', strtotime($revenueDate)) . '-' . date('Ymd', strtotime($reconDate)) . '-' . str_pad((string)$round,2,'0',STR_PAD_LEFT);
        $this->db->insert(self::HEADER, ['reconciliation_no'=>$no,'reconciliation_date'=>$reconDate,'revenue_date'=>$revenueDate,'round_no'=>$round,'status'=>'OPEN','created_by'=>$actor ?: null,'updated_by'=>$actor ?: null,'updated_at'=>date('Y-m-d H:i:s')]);
        return $this->header((int)$this->db->insert_id()) ?: [];
    }

    public function create_round(array $payload, int $actor): array
    {
        if (!$this->ready()) return ['ok'=>false,'message'=>'Migration rekonsiliasi pendapatan belum dijalankan.'];
        $recon = $this->date($payload['reconciliation_date'] ?? ''); $revenue = $this->date($payload['revenue_date'] ?? '');
        if (!$recon || !$revenue || $revenue > $recon || $recon > date('Y-m-d')) return ['ok'=>false,'message'=>'Tanggal pendapatan/rekonsiliasi tidak valid atau berada di masa depan.'];
        if ($this->db->trans_begin() === false) return ['ok'=>false,'message'=>'Transaksi rekonsiliasi gagal dimulai.'];
        try { $header=$this->create_header($recon,$revenue,$actor); if (!$header) throw new RuntimeException('Sesi tidak dapat dibuat.'); if ($this->db->trans_status() === false || $this->db->trans_commit() === false) throw new RuntimeException('Sesi gagal disimpan.'); return ['ok'=>true,'header'=>$header,'message'=>'Sesi rekonsiliasi pendapatan dibuat.']; }
        catch(Throwable $e) { $this->db->trans_rollback(); return ['ok'=>false,'message'=>$e->getMessage()]; }
    }

    public function save_line(array $payload, int $actor): array
    {
        if (!$this->ready()) return ['ok'=>false,'message'=>'Migration rekonsiliasi pendapatan belum dijalankan.'];
        $recon=$this->date($payload['reconciliation_date'] ?? ''); $revenue=$this->date($payload['revenue_date'] ?? '');
        $methodId=(int)($payload['payment_method_id'] ?? 0); $actual=$this->amount($payload['actual_amount'] ?? null);
        $accountId=(int)($payload['account_id'] ?? 0); $resolution=strtoupper(trim((string)($payload['resolution_type'] ?? 'NONE')));
        $reportCategory = strtoupper(trim((string)($payload['report_category'] ?? '')));
        if ($reportCategory !== '' && in_array($resolution, ['IN', 'OUT'], true) && !Finance_mutation_policy::valid($reportCategory, $resolution)) {
            return ['ok' => false, 'message' => 'Kategori laporan tidak sesuai arah mutasi.'];
        }
        if (!$recon || !$revenue || $revenue>$recon || $recon>date('Y-m-d') || $methodId<1 || $actual===null || $actual<0) return ['ok'=>false,'message'=>'Tanggal, metode, atau nilai penerimaan riil tidak valid.'];
        if (!in_array($resolution,['NONE','IN','OUT','TRANSFER'],true)) return ['ok'=>false,'message'=>'Tindak lanjut tidak valid.'];
        $counterMethodId=(int)($payload['counter_payment_method_id']??0);
        $counterAccountId=(int)($payload['counter_account_id']??0);
        $expectedMap=$this->expected_by_method($revenue); $expected=round((float)($expectedMap[$methodId]['expected_amount'] ?? 0),2);
        $difference=round($actual-$expected-(float)($this->posted_adjustment_map($revenue)[$methodId] ?? 0),2);
        if (abs($difference)<.005) $resolution='NONE';
        if ($resolution==='IN' && $difference<=0) return ['ok'=>false,'message'=>'Sisa selisih negatif; gunakan mutasi keluar atau transfer.'];
        if ($resolution==='OUT' && $difference>=0) return ['ok'=>false,'message'=>'Sisa selisih positif; gunakan mutasi masuk atau transfer.'];
        if ($resolution!=='NONE' && $accountId<1) return ['ok'=>false,'message'=>'Pilih rekening yang akan disesuaikan.'];
        if ($resolution==='TRANSFER' && !$this->transfer_ready()) return ['ok'=>false,'message'=>'Transfer belum aktif; minta administrator menerapkan migrasi 2026-09-14c.'];
        if ($resolution==='TRANSFER' && ($counterAccountId<=0 || $counterAccountId===$accountId)) return ['ok'=>false,'message'=>'Pilih rekening lawan yang berbeda. Metode pendapatan lawan tidak wajib.'];
        if (!in_array($resolution,['IN','OUT'],true)) $reportCategory='';
        if ($this->db->trans_begin() === false) return ['ok'=>false,'message'=>'Transaksi rekonsiliasi gagal dimulai.'];
        try {
            $header=$this->header((int)($payload['reconciliation_id'] ?? 0)) ?: $this->latest($recon,$revenue) ?: $this->create_header($recon,$revenue,$actor);
            if (!$header || $header['reconciliation_date']!==$recon || $header['revenue_date']!==$revenue) throw new RuntimeException('Sesi tidak sesuai tanggal.');
            $this->db->query('SELECT id FROM '.self::HEADER.' WHERE id=? FOR UPDATE', [(int)$header['id']]);
            $existing=$this->db->query('SELECT * FROM '.self::LINE.' WHERE reconciliation_id=? AND payment_method_id=? FOR UPDATE', [(int)$header['id'], $methodId])->row_array();
            if ($existing && $existing['status']==='POSTED') throw new RuntimeException('Baris sudah diposting.');
            $method=$this->db->get_where(self::METHOD,['id'=>$methodId,'is_active'=>1])->row_array();
            if (!$method) throw new RuntimeException('Metode pembayaran tidak aktif.');
            if ($resolution!=='NONE') {
                $account=$this->db->get_where(self::ACCOUNT,['id'=>$accountId,'is_active'=>1])->row_array();
                if (!$account) throw new RuntimeException('Rekening tidak aktif.');
            }
            if ($resolution==='TRANSFER') {
                $counter=$this->db->get_where(self::ACCOUNT,['id'=>$counterAccountId,'is_active'=>1])->row_array();
                if (!$counter || (string)$counter['currency_code']!==(string)$account['currency_code']) throw new RuntimeException('Pilih rekening lawan aktif dengan mata uang sama.');
                if ($counterMethodId>0) {
                    $counterMethod=$this->db->get_where(self::METHOD,['id'=>$counterMethodId,'is_active'=>1])->row_array();
                    if (!$counterMethod || $counterMethodId===$methodId || (int)$counterMethod['company_account_id']!==$counterAccountId) throw new RuntimeException('Metode lawan opsional tidak sesuai rekening lawan.');
                }
            }
            $data=['reconciliation_id'=>(int)$header['id'],'payment_method_id'=>$methodId,'account_id'=>$accountId,'expected_amount'=>$expected,'actual_amount'=>$actual,'difference_amount'=>$difference,'transaction_count'=>(int)($expectedMap[$methodId]['transaction_count'] ?? 0),'resolution_type'=>$resolution,'resolution_note'=>$this->note($payload['resolution_note'] ?? ''),'status'=>abs($difference)<.005?'MATCHED':'OPEN','entered_by'=>$actor ?: null,'entered_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
            if ($this->db->field_exists('report_category', self::LINE)) {
                $data['report_category'] = in_array($resolution, ['IN', 'OUT'], true) ? ($reportCategory ?: null) : null;
            } elseif ($reportCategory !== '') {
                throw new RuntimeException('Jalankan migrasi kategori mutasi 2026-09-13a terlebih dahulu.');
            }
            $data['account_id']=$accountId>0?$accountId:null;
            if ($this->db->field_exists('settlement_control_id', self::LINE)) $data['settlement_control_id']=in_array($resolution,['IN','OUT'],true)?(max(0,(int)($payload['settlement_control_id']??0))?:null):null;
            if ($this->transfer_ready()) {
                $data['counter_payment_method_id']=$resolution==='TRANSFER'?($counterMethodId?:null):null;
                $data['counter_account_id']=$resolution==='TRANSFER'?$counterAccountId:null;
            }
            if ($this->db->field_exists('settlement_charge_id', self::LINE)) $data['settlement_charge_id']=in_array($resolution,['IN','OUT'],true)?(max(0,(int)($payload['settlement_charge_id']??0))?:null):null;
            if ($existing) { $this->db->where('id',(int)$existing['id'])->update(self::LINE,$data); $lineId=(int)$existing['id']; }
            else { $data['created_at']=date('Y-m-d H:i:s'); $this->db->insert(self::LINE,$data); $lineId=(int)$this->db->insert_id(); }
            $this->sync_header((int)$header['id'],$actor);
            if ($this->db->trans_status() === false || $this->db->trans_commit() === false) throw new RuntimeException('Hasil cek harian gagal disimpan; perubahan dibatalkan.');
            return ['ok'=>true,'line_id'=>$lineId,'header_id'=>(int)$header['id'],'message'=>abs($difference)<.005?'Penerimaan riil sesuai setelah penyesuaian sebelumnya; tidak ada mutasi baru.':'Hasil cek harian tersimpan tanpa mengubah saldo. Selisih boleh tetap terbuka atau diposting setelah terverifikasi.'];
        } catch(Throwable $e) { $this->db->trans_rollback(); return ['ok'=>false,'message'=>$e->getMessage()]; }
    }

    private function sync_header(int $headerId, int $actor): void
    {
        $methodCount=(int)$this->db->from(self::METHOD.' pm')->join('fin_revenue_reconciliation_method cfg','cfg.payment_method_id=pm.id','left')->where('pm.is_active',1)->where('(cfg.is_enabled IS NULL OR cfg.is_enabled=1)',null,false)->count_all_results();
        $settled=(int)$this->db->where('reconciliation_id',$headerId)->where_in('status',['MATCHED','POSTED'])->count_all_results(self::LINE);
        $this->db->where('id',$headerId)->update(self::HEADER,['status'=>$methodCount>0&&$settled>=$methodCount?'COMPLETED':'OPEN','updated_by'=>$actor ?: null,'updated_at'=>date('Y-m-d H:i:s')]);
    }

    public function post_line(int $lineId, int $actor): array
    {
        if (!$this->ready() || $lineId<1) return ['ok'=>false,'message'=>'Baris rekonsiliasi tidak valid.'];
        if ($this->db->trans_begin() === false) return ['ok'=>false,'message'=>'Transaksi rekonsiliasi gagal dimulai.'];
        try {
            $line=$this->db->query('SELECT l.*,h.reconciliation_no,h.reconciliation_date,h.revenue_date FROM '.self::LINE.' l JOIN '.self::HEADER.' h ON h.id=l.reconciliation_id WHERE l.id=? FOR UPDATE',[$lineId])->row_array();
            if (!$line || $line['status']==='POSTED' || (int)$line['mutation_id']>0) throw new RuntimeException('Baris tidak ditemukan atau sudah diposting.');
            if ($line['reconciliation_date'] !== date('Y-m-d')) throw new RuntimeException('Selisih hanya dapat diposting pada rekonsiliasi hari ini agar kas berjalan tetap konsisten.');
            Finance_mutation_policy::assert_open_period($this->db, (string)$line['reconciliation_date']);
            if (!$this->db->field_exists('report_category', self::MUTATION)) {
                throw new RuntimeException('Jalankan migrasi kategori mutasi 2026-09-13a terlebih dahulu.');
            }
            $resolution=strtoupper((string)$line['resolution_type']);
            if ($resolution==='TRANSFER') {
                $this->post_transfer_locked($line,$actor);
                $this->sync_header((int)$line['reconciliation_id'],$actor);
                if ($this->db->trans_status()===false || $this->db->trans_commit()===false) throw new RuntimeException('Transfer gagal; kedua sisi dibatalkan.');
                return ['ok'=>true,'message'=>'Transfer antar rekening diposting IN/OUT dalam satu transaksi. Baris ini selesai; total dana, omzet dan biaya tidak bertambah.'];
            }
            if ($resolution==='NONE') throw new RuntimeException('Biarkan terbuka tidak membuat mutasi. Pilih IN, OUT, atau transfer lalu simpan jika ingin memposting.');
            $reportCategory = (string)($line['report_category'] ?? '');
            if (!Finance_mutation_policy::valid($reportCategory, $resolution)) {
                throw new RuntimeException('Pilih kategori laporan dan simpan sebelum memposting selisih.');
            }
            // Serialize all rounds of the same payment method, then recompute.
            $method = $this->db->query('SELECT id FROM pos_payment_method WHERE id=? AND is_active=1 FOR UPDATE', [(int)$line['payment_method_id']])->row_array();
            if (!$method) throw new RuntimeException('Metode pembayaran tidak ditemukan.');
            $expectedMap = $this->expected_by_method((string)$line['revenue_date']);
            $expected = (float)($expectedMap[(int)$line['payment_method_id']]['expected_amount'] ?? 0);
            $adjusted = (float)($this->posted_adjustment_map((string)$line['revenue_date'])[(int)$line['payment_method_id']] ?? 0);
            $difference=round((float)$line['actual_amount']-$expected-$adjusted,2);
            if (abs($expected-(float)$line['expected_amount']) >= .005 || abs($difference-(float)$line['difference_amount']) >= .005) {
                throw new RuntimeException('Pendapatan atau penyesuaian sebelumnya berubah. Muat ulang dan simpan ulang penerimaan riil; jangan posting selisih lama.');
            }
            if (abs($difference)<.005 || !in_array($resolution,['IN','OUT'],true)) throw new RuntimeException('Pilih mutasi masuk/keluar untuk selisih yang terverifikasi.');
            if (($resolution==='IN' && $difference<=0)||($resolution==='OUT' && $difference>=0)) throw new RuntimeException('Arah mutasi tidak sesuai sisa selisih harian.');
            $account=$this->db->query('SELECT * FROM '.self::ACCOUNT.' WHERE id=? AND is_active=1 FOR UPDATE',[(int)$line['account_id']])->row_array();
            if (!$account) throw new RuntimeException('Rekening tujuan tidak aktif.');
            $freshAdjusted=(float)($this->posted_adjustment_map((string)$line['revenue_date'],true)[(int)$line['payment_method_id']]??0);
            if (abs($freshAdjusted-$adjusted)>=.005) throw new RuntimeException('Penyesuaian lain baru diposting. Muat ulang dan simpan ulang agar tidak terpotong dua kali.');
            require_once APPPATH . 'libraries/Finance_settlement_control.php';
            $settlementId=(int)($line['settlement_control_id']??0);
            $chargeId=(int)($line['settlement_charge_id']??0);
            Finance_settlement_control::assert_post($this->db,$settlementId,$reportCategory,(int)$account['id'],$line['reconciliation_date'],'REVENUE_RECON',0,$chargeId,abs($difference),$resolution);
            $case=Finance_settlement_control::reference_for($this->db,$settlementId);
            if ($case && ($case['revenue_date']!==$line['revenue_date'] || (int)$case['payment_method_id']!==(int)$line['payment_method_id'])) {
                throw new RuntimeException('Referensi settlement yang dipilih tidak sesuai tanggal dan metode pendapatan.');
            }
            $amount=abs($difference); $before=round((float)$account['current_balance'],2); $after=$resolution==='IN'?$before+$amount:$before-$amount;
            if ($after<-.004) throw new RuntimeException('Saldo rekening tidak cukup untuk mutasi keluar.');
            $after=round(max(0,$after),2); $this->db->where('id',(int)$account['id'])->update(self::ACCOUNT,['current_balance'=>$after]);
            $mutationNo='RPMUT-'.date('Ymd',strtotime($line['reconciliation_date'])).'-'.str_pad((string)$lineId,8,'0',STR_PAD_LEFT).'-'.$resolution;
            $this->db->insert(self::MUTATION,['mutation_no'=>$mutationNo,'mutation_date'=>$line['reconciliation_date'],'account_id'=>(int)$account['id'],'mutation_type'=>$resolution,'amount'=>$amount,'balance_before'=>$before,'balance_after'=>$after,'ref_module'=>'REVENUE_RECON','report_category'=>$reportCategory,'ref_table'=>self::LINE,'ref_id'=>$lineId,'ref_no'=>$line['reconciliation_no'],'notes'=>mb_substr('Penyesuaian penerimaan harian. '.(string)$line['resolution_note'],0,255),'created_by'=>$actor ?: null,'created_at'=>date('Y-m-d H:i:s')]);
            $mutationId=(int)$this->db->insert_id(); if (!$mutationId) throw new RuntimeException('Mutasi tidak dapat dibuat.');
            if ($settlementId>0 && !$this->db->where('id',$mutationId)->update(self::MUTATION,['settlement_control_id'=>$settlementId])) throw new RuntimeException('Referensi settlement gagal disimpan.');
            if ($chargeId>0 && !$this->db->where('id',$mutationId)->update(self::MUTATION,['settlement_charge_id'=>$chargeId])) throw new RuntimeException('Identitas biaya gagal disimpan.');
            $this->db->where('id',$lineId)->update(self::LINE,['difference_amount'=>$difference,'status'=>'POSTED','mutation_id'=>$mutationId,'resolved_by'=>$actor ?: null,'resolved_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $this->sync_header((int)$line['reconciliation_id'],$actor);
            if ($this->db->trans_status() === false || $this->db->trans_commit() === false) {
                throw new RuntimeException('Posting selisih harian gagal; seluruh perubahan dibatalkan.');
            }
            return ['ok'=>true,'message'=>'Selisih harian diposting sesuai kategori dan saldo rekening telah diperbarui.'];
        } catch(Throwable $e) { $this->db->trans_rollback(); log_message('error','Revenue reconciliation post failed: '.$e->getMessage()); return ['ok'=>false,'message'=>$e->getMessage()]; }
    }
}
