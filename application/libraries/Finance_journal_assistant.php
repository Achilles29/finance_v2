<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Finance_journal_policy.php';

/** Suggestions for explicit review, not authoritative document recognition or auto-post. */
class Finance_journal_assistant
{
    public static function scenarios(): array
    {
        $definitions=[
            'OTHER_INCOME'=>['Uang masuk lain yang benar-benar pendapatan','IN','INCOME','4200','OPERATING','Bukan modal, pinjaman, DP atau penagihan piutang.',true],
            'CASH_SURPLUS'=>['Selisih kas lebih yang sudah diperiksa','IN','INCOME','4200','OPERATING','Gunakan hanya setelah penyebab selisih diperiksa.',true],
            'OPERATING_EXPENSE'=>['Membayar biaya operasional','OUT','EXPENSE','5200','OPERATING','Contoh listrik periode berjalan; bukan stok, aset atau pelunasan utang.',true],
            'PROMO_EXPENSE'=>['Membayar promo yang ditanggung usaha','OUT','EXPENSE','5300','OPERATING','Periksa agar promo belum dikurangkan sebagai diskon penjualan yang sama.',true],
            'PLATFORM_FEE'=>['Membayar biaya / komisi platform','OUT','EXPENSE','5400','OPERATING','Hanya biaya terpisah yang belum diakui; bukan seluruh nilai pencairan.',true],
            'CASH_SHORTAGE'=>['Selisih kas kurang yang sudah diperiksa','OUT','EXPENSE','5600','OPERATING','Bukan pengganti mencari transaksi yang hilang.',true],
            'OWNER_CAPITAL'=>['Pemilik menyetor modal','IN','EQUITY','3100','FINANCING','Modal bukan pendapatan penjualan.',true],
            'OWNER_DRAWING'=>['Pemilik mengambil uang usaha (prive)','OUT','EQUITY','3300','FINANCING','Prive bukan biaya operasional; pastikan sesuai bentuk usaha.',true],
            'CASH_INVENTORY'=>['Membeli stok tunai, belum dicatat sebagai utang','OUT','ASSET','1300','OPERATING','Jika pembelian sudah diakui sebagai utang, pilih pelunasan utang agar stok tidak bertambah dua kali.',false],
            'CASH_FIXED_ASSET'=>['Membeli aset tetap tunai','OUT','ASSET','1400','INVESTING','Untuk nilai aset, bukan bunga/pajak yang perlu dipisah atau pembayaran utang aset lama.',false],
            'PAY_TRADE_DEBT'=>['Melunasi utang pembelian yang sudah dicatat','OUT','LIABILITY','2100','OPERATING','Tidak mencatat beban/persediaan lagi. Utang pembelian aset dapat perlu kelompok Investasi.',false],
            'COLLECT_RECEIVABLE'=>['Menerima pelunasan piutang yang sudah dicatat','IN','ASSET','1200','OPERATING','Jangan mengakui penjualan kedua kali; hanya penagihan piutang usaha.',false],
            'CUSTOMER_DEPOSIT'=>['Menerima DP pelanggan sebelum pesanan selesai','IN','LIABILITY','2200','OPERATING','DP masih kewajiban usaha, belum pendapatan final.',false],
            'BORROW_PRINCIPAL'=>['Menerima pokok pinjaman','IN','LIABILITY','2500','FINANCING','Pinjaman bukan pendapatan; pisahkan potongan biaya jika ada.',false],
            'REPAY_PRINCIPAL'=>['Membayar pokok pinjaman','OUT','LIABILITY','2500','FINANCING','Hanya pokok. Jika termasuk bunga/biaya, gunakan jurnal rinci.',false],
        ];
        $result=[];
        foreach($definitions as $code=>$d)$result[$code]=array_combine(['label','direction','account_type','default_account','default_flow','help','category_bound'],$d);
        return $result;
    }
    public static function eligible(string $code,array $mutation): bool
    {
        $d=self::scenarios()[$code]??null;
        if (!$d || !empty($mutation['reversal_of_mutation_id']) || ($mutation['ref_module']??'')==='FINANCE_TRANSFER' || $d['direction']!==($mutation['mutation_type']??'')) return false;
        return !$d['category_bound'] || (in_array($mutation['ref_module']??'', ['FINANCE','FINANCE_RECON','REVENUE_RECON'],true) && ($mutation['report_category']??'')===$code);
    }
    public static function account_hash(?array $account): string
    {
        $values=[];foreach(['code','name','account_type','is_cash','is_active'] as $key)$values[$key]=(string)($account[$key]??'');
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    public static function validate_account(string $scenario,string $code,array $accounts): void
    {
        $d=self::scenarios()[$scenario]??null;$a=$accounts[$code]??null;
        if (!$d || !$a || empty($a['is_active']) || !empty($a['is_cash']) || in_array($code,['1100','1190'],true) || $a['account_type']!==$d['account_type']) throw new InvalidArgumentException('Pilih akun nonkas aktif dengan kelompok yang sesuai jenis transaksi.');
    }
}
