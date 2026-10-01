<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Finance_journal_assistant.php';

trait Finance_accounting_setup
{
    public function setup_ready(): bool {return $this->ready() && $this->db->table_exists('fin_gl_mapping');}
    public function mappings(): array
    {
        $stored=$this->setup_ready()?array_column($this->rows('SELECT * FROM fin_gl_mapping'),null,'scenario_code'):[];
        $accounts=$this->accounts();$result=[];
        foreach(Finance_journal_assistant::scenarios() as $code=>$d){
            $s=$stored[$code]??[];$account=(string)($s['account_code']??$d['default_account']);
            $state=['scenario_code'=>$code,'account_code'=>$account,'cashflow_class'=>(string)($s['cashflow_class']??$d['default_flow']),
                'is_enabled'=>(int)($s['is_enabled']??1),'revision'=>(int)($s['revision']??0),'account_hash'=>Finance_journal_assistant::account_hash($accounts[$account]??null)];
            $valid=true;try{Finance_journal_assistant::validate_account($code,$account,$accounts);}catch(InvalidArgumentException $e){$valid=false;}
            $result[$code]=$state+$d+['configured'=>(bool)$s,'valid'=>$valid,'hash'=>hash('sha256',json_encode([$state,$d],JSON_THROW_ON_ERROR))];
        }return $result;
    }
    public function setup_data(): array
    {
        $accounts=$this->accounts();foreach($accounts as &$a)$a['hash']=Finance_journal_assistant::account_hash($a);unset($a);
        return ['schema_ready'=>$this->setup_ready(),'accounts'=>$accounts,'mappings'=>$this->mappings()];
    }
    public function assistant_options(array $m): array
    {
        $options=[];
        foreach($this->mappings() as $code=>$r)if($r['valid']&&$r['is_enabled']&&Finance_journal_assistant::eligible($code,$m))$options[]=$r;
        return $options;
    }
    private function setup_audit(string $action,string $table,string $key,?array $before,array $after,int $actor,string $ip): void
    {
        if (!$this->db->insert('aud_transaction_log',['module_code'=>'FINANCE','action_code'=>$action,'entity_table'=>$table,
            'transaction_no'=>$key,'actor_user_id'=>$actor,'source_ip'=>$ip?:null,'before_payload'=>json_encode($before,JSON_THROW_ON_ERROR),
            'after_payload'=>json_encode($after,JSON_THROW_ON_ERROR),'notes'=>'Pengaturan jurnal: '.$key])) throw new RuntimeException('Audit pengaturan gagal.');
    }
    public function save_setup(string $kind,array $p,int $actor,string $ip=''): array
    {
        if (!$this->setup_ready()) return ['ok'=>false,'message'=>'Pengaturan tersimpan belum aktif. Admin perlu SQL 2026-09-15b; jurnal manual tetap tersedia.'];
        return $this->tx(function()use($kind,$p,$actor,$ip){
            if ($actor<=0)throw new InvalidArgumentException('Sesi pengguna tidak valid.');
            if ($kind==='mapping') {
                $scenario=Finance_journal_policy::text($p['scenario_code']??'',40);$state=$this->mappings()[$scenario]??null;
                if (!$state)throw new InvalidArgumentException('Jenis transaksi tidak dikenal.');
                $code=Finance_journal_policy::text($p['account_code']??'',20);
                Finance_journal_assistant::validate_account($scenario,$code,$this->accounts());
                $flow=$p['cashflow_class']??'';
                if (!in_array($flow,['OPERATING','INVESTING','FINANCING'],true))throw new InvalidArgumentException('Kelompok arus kas tidak valid.');
                if (!in_array($p['is_enabled']??null,[0,1,'0','1'],true))throw new InvalidArgumentException('Status saran tidak valid.');
                $enabled=(int)$p['is_enabled'];
                if ($state['configured']&&$state['account_code']===$code&&$state['cashflow_class']===$flow&&$state['is_enabled']===$enabled)return ['saved'=>true,'unchanged'=>true];
                if (!is_string($p['expected_hash']??null)||!hash_equals($state['hash'],$p['expected_hash']))throw new InvalidArgumentException('Pengaturan telah berubah. Muat ulang sebelum menyimpan.');
                $before=$this->rows('SELECT * FROM fin_gl_mapping WHERE scenario_code=? FOR UPDATE',[$scenario])[0]??null;
                $data=['scenario_code'=>$scenario,'account_code'=>$code,'cashflow_class'=>$flow,'is_enabled'=>$enabled,'revision'=>$state['revision']+1,'updated_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s')];
                $ok=$before?$this->db->where('scenario_code',$scenario)->update('fin_gl_mapping',$data):$this->db->insert('fin_gl_mapping',$data);
                if (!$ok)throw new RuntimeException('Pemetaan gagal.');
                $this->setup_audit('GL_MAPPING','fin_gl_mapping',$scenario,$before,$data,$actor,$ip);
            } elseif ($kind==='account') {
                $code=Finance_journal_policy::text($p['code']??'',20);
                if (!preg_match('/\A[1-5][0-9]{3,9}\z/D',$code))throw new InvalidArgumentException('Kode akun harus 4–10 angka, dimulai 1 aset, 2 utang, 3 ekuitas, 4 pendapatan, atau 5 beban.');
                if (in_array($code,['1100','1190'],true))throw new InvalidArgumentException('Akun kas dan perantara transfer dilindungi.');
                $before=$this->rows('SELECT * FROM fin_gl_account WHERE code=? FOR UPDATE',[$code])[0]??null;
                if (!is_string($p['name']??null)||trim($p['name'])===''||mb_strlen($p['name'])>150)throw new InvalidArgumentException('Nama akun wajib diisi, maksimal 150 karakter.');
                $name=trim($p['name']);
                if (!in_array($p['is_active']??null,[0,1,'0','1'],true))throw new InvalidArgumentException('Status akun tidak valid.');
                $active=(int)$p['is_active'];$type=['1'=>'ASSET','2'=>'LIABILITY','3'=>'EQUITY','4'=>'INCOME','5'=>'EXPENSE'][$code[0]];
                if ($before && (!empty($before['is_cash']) || $before['account_type']!==$type))throw new InvalidArgumentException('Kelompok/jenis akun existing dilindungi; perlu ditinjau administrator.');
                if ($before && $before['name']===$name && (int)$before['is_active']===$active)return ['saved'=>true,'unchanged'=>true];
                if (!is_string($p['expected_hash']??null)||!hash_equals(Finance_journal_assistant::account_hash($before),$p['expected_hash']))throw new InvalidArgumentException('Daftar akun telah berubah. Muat ulang dahulu.');
                if ($before && !$active) {
                    if ($this->rows('SELECT id FROM fin_gl_line WHERE account_code=? LIMIT 1',[$code]) || $this->rows('SELECT scenario_code FROM fin_gl_mapping WHERE account_code=? AND is_enabled=1 LIMIT 1',[$code]))throw new InvalidArgumentException('Akun sudah dipakai jurnal/pemetaan aktif. Tidak boleh dinonaktifkan agar pembalikan tetap tersedia.');
                }
                $data=['code'=>$code,'name'=>$name,'account_type'=>$type,'is_cash'=>0,'is_active'=>$active];
                $ok=$before?$this->db->where('code',$code)->update('fin_gl_account',['name'=>$name,'is_active'=>$active]):$this->db->insert('fin_gl_account',$data);
                if (!$ok)throw new RuntimeException('Akun gagal disimpan.');
                $this->setup_audit('GL_ACCOUNT','fin_gl_account',$code,$before,$data,$actor,$ip);
            } else throw new InvalidArgumentException('Aksi pengaturan tidak tersedia.');
            return ['saved'=>true];
        });
    }
    private function validate_assistant(array $p,array $m,array $input,string $flow): array
    {
        $scenario=$p['assistant_scenario']??'';
        if ($scenario==='')return [];
        if (!is_string($scenario)||!Finance_journal_assistant::eligible($scenario,$m))throw new InvalidArgumentException('Saran tidak cocok dengan sumber/arah mutasi. Gunakan peninjauan manual.');
        $r=$this->mappings()[$scenario]??null;
        if (!$r || !$r['valid'] || !$r['is_enabled'] || !is_string($p['assistant_hash']??null) || !hash_equals($r['hash'],$p['assistant_hash']))throw new InvalidArgumentException('Pemetaan saran berubah/nonaktif. Perbarui pratinjau sebelum posting.');
        if (($p['assistant_confirmed']??false)!==true)throw new InvalidArgumentException('Konfirmasikan bahwa jenis transaksi sesuai bukti dan belum dicatat dua kali.');
        if (count($input)!==1 || ($input[0]['account_code']??'')!==$r['account_code'] || $flow!==$r['cashflow_class'])throw new InvalidArgumentException('Baris saran telah diubah. Periksa ulang atau gunakan mode manual.');
        $d=Finance_journal_policy::cents($input[0]['debit']??'0');$c=Finance_journal_policy::cents($input[0]['credit']??'0');$n=Finance_journal_policy::cents($m['amount']);
        if ($d!==($m['mutation_type']==='OUT'?$n:0)||$c!==($m['mutation_type']==='IN'?$n:0))throw new InvalidArgumentException('Nominal saran harus sama dengan mutasi sumber.');
        return ['scenario'=>$scenario,'mapping_revision'=>$r['revision'],'mapping_hash'=>$r['hash'],'account_code'=>$r['account_code'],'cashflow_class'=>$flow,'confirmed'=>true];
    }
}
