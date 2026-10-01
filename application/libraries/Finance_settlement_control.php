<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/Finance_mutation_policy.php';

/** Shared read/claim rules. Claims run inside the caller's account transaction. */
class Finance_settlement_control
{
    public static function ready($db): bool
    {
        return $db->table_exists('fin_settlement_control')
            && $db->field_exists('settlement_control_id', 'fin_account_mutation_log');
    }

    public static function rows($db, string $sql, array $params = []): array
    {
        $query = $db->query($sql, $params);
        if ($query === false) throw new RuntimeException('Data kontrol keuangan tidak dapat dibaca. Muat ulang atau hubungi administrator.');
        return $query->result_array();
    }

    public static function effective(string $alias = 'm'): string
    {
        return "$alias.reversal_of_mutation_id IS NULL AND NOT EXISTS (SELECT 1 FROM fin_account_mutation_log rv WHERE rv.reversal_of_mutation_id=$alias.id)";
    }

    public static function trace($db, string $date, int $methodId, bool $lock = false): array
    {
        $end = date('Y-m-d', strtotime($date . ' +1 day'));
        $suffix = $lock ? ' FOR UPDATE' : '';
        $payments = self::rows($db, "SELECT pl.id, p.id payment_id,p.payment_no,p.order_id,p.payment_type,p.paid_at,
            pl.amount,pl.reference_no,p.discount_amount,p.promo_amount,p.voucher_amount,p.point_redeem_amount,p.compliment_amount
            FROM pos_payment p JOIN pos_payment_line pl ON pl.payment_id=p.id AND pl.status='PAID'
            WHERE p.payment_status='PAID' AND p.payment_type IN ('FINAL','DEPOSIT')
            AND pl.payment_method_id=? AND p.paid_at>=? AND p.paid_at<? ORDER BY pl.id" . $suffix, [$methodId,$date,$end]);
        $refunds = self::rows($db, "SELECT id,refund_no,order_id,payment_id,refund_amount,refunded_at FROM pos_refund
            WHERE refund_status='POSTED' AND payment_method_id=? AND refunded_at>=? AND refunded_at<? ORDER BY id" . $suffix, [$methodId,$date,$end]);
        $income = round(array_sum(array_column($payments,'amount')),2);
        $refund = round(array_sum(array_column($refunds,'refund_amount')),2);
        // Include all identities, cents, and source statuses through the effective row set.
        $fingerprint = hash('sha256', json_encode([$date,$methodId,$payments,$refunds], JSON_THROW_ON_ERROR));
        return ['payments'=>$payments,'refunds'=>$refunds,'pos_amount'=>$income,'refund_amount'=>$refund,
            'expected_amount'=>round($income-$refund,2),'source_fingerprint'=>$fingerprint];
    }

    public static function options($db): array
    {
        if (!self::ready($db)) return [];
        return self::rows($db, "SELECT c.id,c.revenue_date,c.provider_reference,c.account_id,pm.method_name
            FROM fin_settlement_control c JOIN pos_payment_method pm ON pm.id=c.payment_method_id
            ORDER BY c.revenue_date DESC,c.id DESC LIMIT 25");
    }

    public static function assert_post($db, int $caseId, string $category, int $accountId, string $date, string $module, int $excludeMutation = 0, int $chargeId = 0, float $amount = 0, string $direction = ''): void
    {
        $required = in_array($category,['PROMO_EXPENSE','PLATFORM_FEE'],true);
        if ($caseId <= 0) {
            if ($chargeId > 0) throw new RuntimeException('Rincian biaya harus memiliki settlement.');
            if ($required) throw new RuntimeException('Pilih referensi Kontrol Settlement untuk biaya promo/platform. Biaya harus ditautkan agar tidak tercatat dua kali.');
            return;
        }
        if (!self::ready($db)) throw new RuntimeException('Migrasi Kontrol Keuangan 2026-09-14a belum tersedia.');
        $case = self::rows($db,'SELECT * FROM fin_settlement_control WHERE id=? FOR UPDATE',[$caseId])[0] ?? null;
        if (!$case || (int)$case['account_id'] !== $accountId || $date < $case['revenue_date']) {
            throw new RuntimeException('Referensi settlement tidak sesuai rekening/tanggal transaksi.');
        }
        $trace = self::trace($db,$case['revenue_date'],(int)$case['payment_method_id'],true);
        if (!hash_equals($case['source_fingerprint'],$trace['source_fingerprint'])) {
            throw new RuntimeException('Transaksi POS/refund berubah. Periksa dan simpan ulang Kontrol Settlement sebelum posting.');
        }
        if (in_array($module,['REVENUE_RECON','FINANCE_RECON'],true) && !(int)$case['settlement_complete']) {
            throw new RuntimeException('Settlement masih sebagian. Dana belum cair tidak boleh diposting sebagai biaya; verifikasi settlement lengkap terlebih dahulu.');
        }
        if (self::operations_ready($db) && ($excludeMutation === 0 || $chargeId > 0)) {
            $account=self::rows($db,'SELECT is_active,currency_code FROM fin_company_account WHERE id=?',[$accountId])[0]??[];
            if (empty($account['is_active']) || ($account['currency_code']??'')!=='IDR') throw new RuntimeException('Posting settlement hanya untuk rekening IDR aktif.');
            $charge = self::rows($db,'SELECT * FROM fin_settlement_charge WHERE id=? FOR UPDATE',[$chargeId])[0] ?? [];
            if (!$charge || (int)$charge['settlement_id'] !== $caseId || (int)$charge['account_id'] !== $accountId
                || $charge['category'] !== $category || $charge['charge_date'] !== $date
                || ($excludeMutation === 0 && ($charge['direction'] !== $direction || abs((float)$charge['amount']-$amount) >= .005))) {
                throw new RuntimeException('Pilih rincian biaya yang sesuai tanggal, rekening, arah, kategori, dan nominal. Buat rincian di Kontrol Keuangan terlebih dahulu.');
            }
            $duplicate = self::rows($db,'SELECT id FROM fin_account_mutation_log m WHERE settlement_charge_id=? AND id<>? AND '.self::effective().' LIMIT 1 FOR UPDATE',[$chargeId,$excludeMutation]);
            if ($duplicate) throw new RuntimeException('Dokumen/baris biaya ini sudah diposting. VOID dahulu bila salah; jangan posting ulang.');
            $legacy = self::rows($db,'SELECT id FROM fin_account_mutation_log m WHERE settlement_control_id=? AND report_category=? AND settlement_charge_id IS NULL AND id<>? AND '.self::effective().' LIMIT 1 FOR UPDATE',[$caseId,$category,$excludeMutation]);
            if ($legacy) throw new RuntimeException('Ada biaya lama tanpa identitas dokumen. Tautkan mutasi #'.$legacy[0]['id'].' saat membuat rincian biaya, baru posting biaya tambahan.');
            if ($excludeMutation === 0) self::consume_approval($db,'POST_CHARGE',$chargeId);
            return;
        }
        $duplicate = self::rows($db,"SELECT id,mutation_no FROM fin_account_mutation_log m
            WHERE settlement_control_id=? AND report_category=? AND id<>? AND " . self::effective() . ' LIMIT 1 FOR UPDATE',[$caseId,$category,$excludeMutation]);
        if ($duplicate) throw new RuntimeException('Kategori ini sudah tercatat pada mutasi ' . $duplicate[0]['mutation_no'] . '. Buka Mutasi Rekening; jangan posting ulang melalui modul lain.');
    }

    public static function reference_for($db, int $caseId): ?array
    {
        if ($caseId <= 0 || !self::ready($db)) return null;
        return self::rows($db,'SELECT * FROM fin_settlement_control WHERE id=?',[$caseId])[0] ?? null;
    }

    public static function operations_ready($db): bool { return $db->table_exists('fin_control_policy') && $db->table_exists('fin_settlement_charge'); }

    public static function policy($db, bool $lock=false): array
    {
        if (!self::operations_ready($db)) return ['approval_enabled'=>0,'approval_threshold'=>1000000,'evidence_required'=>0,'payroll_day'=>1,'revision'=>0];
        $row=self::rows($db,'SELECT * FROM fin_control_policy WHERE id=1'.($lock?' FOR UPDATE':''))[0]??[];
        if (!$row) throw new RuntimeException('Kebijakan kontrol belum tersedia; jalankan migrasi 2026-09-14b.');
        return $row;
    }

    /** Bind approval to exact financial facts, source revision, evidence and policy. */
    public static function approval_snapshot($db, string $action, int $id, bool $lock=true): array
    {
        if (!in_array($action,['POST_CHARGE','VOID_ADJUSTMENT'],true)) throw new RuntimeException('Jenis persetujuan tidak valid.');
        $table=$action==='POST_CHARGE'?'fin_settlement_charge':'fin_account_mutation_log';
        $suffix=$lock?' FOR UPDATE':'';
        $row=self::rows($db,"SELECT * FROM $table WHERE id=?".$suffix,[$id])[0]??[];
        $caseId=(int)($row[$action==='POST_CHARGE'?'settlement_id':'settlement_control_id']??0);
        if (!$row || !$caseId || ($action==='VOID_ADJUSTMENT' && (!Finance_mutation_policy::manual_module((string)$row['ref_module']) || !empty($row['reversal_of_mutation_id'])))) throw new RuntimeException('Target persetujuan tidak ditemukan/tidak sesuai.');
        $case=self::rows($db,'SELECT * FROM fin_settlement_control WHERE id=?'.$suffix,[$caseId])[0]??[];
        if (!$case || (int)$case['account_id']!==(int)$row['account_id']) throw new RuntimeException('Rekening target dan settlement tidak sesuai.');
        $policy=self::policy($db,$lock);
        $evidence=!empty($row['evidence_id']) ? (self::rows($db,'SELECT * FROM fin_control_evidence WHERE id=?',[$row['evidence_id']])[0]??[]) : [];
        if ($evidence && (int)$evidence['settlement_id']!==$caseId) throw new RuntimeException('Bukti bukan milik settlement ini.');
        if (!empty($row['evidence_id']) && !$evidence) throw new RuntimeException('Bukti rincian tidak ditemukan.');
        if ($evidence && $lock) { require_once __DIR__.'/Finance_control_evidence.php';Finance_control_evidence::download_path($evidence); }
        $hash=hash('sha256',json_encode([$action,$row,$case['revision'],$case['source_fingerprint'],$policy,$evidence],JSON_THROW_ON_ERROR));
        return ['hash'=>$hash,'row'=>$row,'policy'=>$policy,'evidence'=>$evidence,'maker'=>(int)($row['updated_by']??$row['created_by']??0)];
    }

    public static function consume_approval($db, string $action, int $id): void
    {
        if (!self::operations_ready($db)) return;
        $snap=self::approval_snapshot($db,$action,$id);$policy=$snap['policy'];
        if ($action==='POST_CHARGE' && (int)$policy['evidence_required'] && !$snap['evidence']) throw new RuntimeException('Unggah dan pilih bukti pada rincian biaya sebelum posting.');
        if (!(int)$policy['approval_enabled'] || (float)$snap['row']['amount']<(float)$policy['approval_threshold']) return;
        $approval=self::rows($db,"SELECT * FROM fin_control_approval WHERE action_code=? AND target_id=? AND payload_hash=? AND status='APPROVED' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$action,$id,$snap['hash']])[0]??[];
        if (!$approval || (int)$approval['reviewed_by']===(int)$approval['requested_by'] || (int)$approval['reviewed_by']===$snap['maker'] || (int)$approval['reviewed_by']===(int)($snap['row']['created_by']??0)) throw new RuntimeException('Perlu persetujuan pengguna lain untuk rincian terbaru. Ajukan melalui Kontrol Keuangan → Persetujuan.');
        if (!$db->where('id',$approval['id'])->where('status','APPROVED')->update('fin_control_approval',['status'=>'CONSUMED','consumed_at'=>date('Y-m-d H:i:s')]) || $db->affected_rows()!==1) throw new RuntimeException('Persetujuan sudah digunakan; muat ulang.');
    }
}
