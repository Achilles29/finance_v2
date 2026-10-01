<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Finance_allocation_policy.php';

/** Bounded UTF-8 CSV parser; never executes formulas, guesses separators or stores uploads. */
class Finance_bank_csv
{
    public static function parse(string $csv, array $p): array
    {
        if ($csv==='' || strlen($csv)>1048576 || str_contains($csv,"\0") || !mb_check_encoding($csv,'UTF-8')) throw new RuntimeException('Pilih CSV UTF-8, maksimal 1 MB.');
        $delimiter=(string)($p['delimiter']??',');
        $dateFormat=(string)($p['date_format']??'Y-m-d'); $numberFormat=(string)($p['number_format']??'en');
        if (!in_array($delimiter,[',',';'],true) || !in_array($dateFormat,['Y-m-d','d/m/Y'],true) || !in_array($numberFormat,['id','en'],true)) throw new RuntimeException('Format CSV tidak valid.');
        $csv=preg_replace('/\A\xEF\xBB\xBF/','',$csv);
        $stream=fopen('php://memory','r+');
        try {
            fwrite($stream,$csv); rewind($stream);
            $headers=fgetcsv($stream,0,$delimiter,'"','');
            if (!$headers || count($headers)<4 || count($headers)>30) throw new RuntimeException('CSV harus memiliki header dan 4–30 kolom.');
            $map=[];
            foreach (['date','reference','in','out'] as $field) {
                $index=(string)($p['column_'.$field]??'');
                if (!ctype_digit($index) || (int)$index>=count($headers)) throw new RuntimeException('Pilih kolom tanggal, referensi, masuk dan keluar.');
                $map[$field]=(int)$index;
            }
            if (count(array_unique($map))!==4) throw new RuntimeException('Kolom tanggal, referensi, masuk dan keluar harus berbeda.');
            $rows=[]; $seen=[]; $line=1;
            while (($values=fgetcsv($stream,0,$delimiter,'"',''))!==false) {
                $line++;
                if ($values===[null] || implode('', $values)==='') continue;
                if (count($rows)>=500 || $line>550) throw new RuntimeException('Maksimal 500 transaksi per CSV.');
                if (count($values)!==count($headers)) throw new RuntimeException('Jumlah kolom tidak sesuai pada baris '.$line.'.');
                $date=trim($values[$map['date']]); $dt=DateTimeImmutable::createFromFormat('!'.$dateFormat,$date);
                if (!$dt || $dt->format($dateFormat)!==$date || $dt->format('Y-m-d')>date('Y-m-d')) throw new RuntimeException('Tanggal tidak valid pada baris '.$line.'.');
                $ref=trim($values[$map['reference']]);
                if ($ref==='' || mb_strlen($ref)>100 || preg_match('/[\x00-\x1F\x7F]/',$ref)) throw new RuntimeException('Referensi wajib diisi (maksimal 100 karakter), baris '.$line.'.');
                $in=self::amount(trim($values[$map['in']]),$numberFormat);
                $out=self::amount(trim($values[$map['out']]),$numberFormat);
                if (($in>0 && $out>0) || ($in===0 && $out===0)) throw new RuntimeException('Isi tepat satu nominal masuk ATAU keluar pada baris '.$line.'.');
                $row=['statement_date'=>$dt->format('Y-m-d'),'reference_no'=>$ref,'direction'=>$in>0?'IN':'OUT','amount'=>Finance_allocation_policy::decimal(max($in,$out)),'description'=>''];
                $row['row_hash']=hash('sha256',json_encode([$row['statement_date'],mb_strtoupper($ref,'UTF-8'),$row['direction'],$row['amount']],JSON_THROW_ON_ERROR));
                if (isset($seen[$row['row_hash']])) throw new RuntimeException('Baris identik muncul dua kali; periksa referensi pada baris '.$line.'.');
                $seen[$row['row_hash']]=true; $rows[]=$row;
            }
            if (!$rows) throw new RuntimeException('CSV tidak berisi transaksi.');
            return ['rows'=>$rows,'file_hash'=>hash('sha256',$csv)];
        } finally { fclose($stream); }
    }

    private static function amount(string $text,string $format): int
    {
        if ($text==='' || $text==='-') return 0;
        $pattern=$format==='id'?'/\A(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)(?:,[0-9]{1,2})?\z/D':'/\A(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?\z/D';
        if (!preg_match($pattern,$text)) throw new RuntimeException('Format nominal CSV tidak sesuai pilihan.');
        $number=$format==='id'?str_replace(',','.',str_replace('.','',$text)):str_replace(',','',$text);
        return Finance_allocation_policy::cents($number,true);
    }
}
