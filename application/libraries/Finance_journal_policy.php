<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Exact cents and explicit recognition: no amount/note-based account guessing. */
class Finance_journal_policy
{
    public static function cents($value): int
    {
        if (!is_scalar($value) || !preg_match('/\A(-?)(\d{1,12})(?:\.(\d{1,2}))?\z/D',(string)$value,$m)) {
            throw new InvalidArgumentException('Nominal harus angka tanpa pemisah ribuan, maksimal dua desimal.');
        }
        return ($m[1]==='-'?-1:1)*((int)$m[2]*100+(int)str_pad($m[3]??'',2,'0'));
    }
    public static function decimal(int $cents): string
    {
        return ($cents<0?'-':'').intdiv(abs($cents),100).'.'.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT);
    }
    public static function date($value): string
    {
        if (!is_string($value)) throw new InvalidArgumentException('Tanggal tidak valid.');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$d || $d->format('Y-m-d')!==$value) throw new InvalidArgumentException('Tanggal tidak valid.');
        return $value;
    }
    public static function text($value,int $max): string
    {
        if (!is_string($value) || trim($value)==='' || mb_strlen($value)>$max) throw new InvalidArgumentException('Referensi/keterangan wajib diisi dan tidak boleh terlalu panjang.');
        return trim($value);
    }
    public static function source_hash(array $m): string
    {
        $v=[];
        foreach(['id','mutation_date','account_id','mutation_type','amount','ref_module','ref_table','ref_id','reversal_of_mutation_id','report_category'] as $key) {
            $v[$key]=$key==='amount'?self::decimal(self::cents($m[$key]??'0')):(string)($m[$key]??'');
        }
        return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));
    }
    public static function lines(array $input,array $accounts): array
    {
        if (count($input)<2 || count($input)>100) throw new InvalidArgumentException('Jurnal harus mempunyai 2–100 baris.');
        $result=[];$debit=0;$credit=0;
        foreach($input as $line) {
            if (!is_array($line)) throw new InvalidArgumentException('Baris jurnal tidak valid.');
            $code=is_string($line['account_code']??null)?$line['account_code']:'';
            $a=$accounts[$code]??null;
            if (!$a || empty($a['is_active'])) throw new InvalidArgumentException('Pilih akun akuntansi yang aktif.');
            $d=self::cents($line['debit']??'0');$c=self::cents($line['credit']??'0');
            if ($d<0 || $c<0 || (($d>0)===($c>0))) throw new InvalidArgumentException('Setiap baris harus berisi debit ATAU kredit positif, bukan keduanya.');
            $bank=(int)($line['company_account_id']??0);
            if ((!empty($a['is_cash']) && $bank<=0) || (empty($a['is_cash']) && $bank!==0)) throw new InvalidArgumentException('Rekening usaha hanya wajib untuk akun kas.');
            $result[]=['account_code'=>$code,'company_account_id'=>$bank?:null,'debit'=>self::decimal($d),'credit'=>self::decimal($c)];
            $debit+=$d;$credit+=$c;
        }
        if ($debit!==$credit) throw new InvalidArgumentException('Jurnal belum seimbang: total debit harus sama dengan kredit.');
        return $result;
    }
    public static function suggestion(array $mutation): ?string
    {
        // Suggestions only, never automatic posting; POS/purchase/loan need document review.
        $map=['OTHER_INCOME'=>'4200','CASH_SURPLUS'=>'4200','OPERATING_EXPENSE'=>'5200',
            'PROMO_EXPENSE'=>'5300','PLATFORM_FEE'=>'5400','CASH_SHORTAGE'=>'5600',
            'OWNER_CAPITAL'=>'3100','OWNER_DRAWING'=>'3300'];
        return $map[$mutation['report_category']??'']??null;
    }
}
