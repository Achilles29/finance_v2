<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/../libraries/Finance_journal_policy.php';
require_once __DIR__.'/../libraries/Finance_mutation_policy.php';
require_once __DIR__.'/../libraries/Finance_accounting_setup.php';

/** Separate accrual ledger. Never writes source transactions or company balances. */
class Finance_accounting_model extends CI_Model
{
    use Finance_accounting_setup;
    private function rows(string $sql,array $binds=[]): array
    {
        $q=$this->db->query($sql,$binds);
        if ($q===false) throw new RuntimeException('Query akuntansi gagal.');
        return $q->result_array();
    }
    public function ready(): bool
    {
        foreach(['fin_gl_guard','fin_gl_account','fin_gl_journal','fin_gl_line','fin_period_close','aud_transaction_log'] as $table) if (!$this->db->table_exists($table)) return false;
        return true;
    }
    public function period(string $month): array
    {
        $from=Finance_journal_policy::date($month.'-01');
        return [$from,date('Y-m-t',strtotime($from))];
    }
    public function accounts(): array
    {
        return $this->ready()?array_column($this->rows('SELECT * FROM fin_gl_account ORDER BY code'),null,'code'):[];
    }
    public function opening(): ?array
    {
        return $this->ready()?($this->rows('SELECT * FROM fin_gl_journal WHERE opening_key=1')[0]??null):null;
    }
    public function currencies(): array
    {
        return array_column($this->rows('SELECT DISTINCT currency_code FROM fin_company_account ORDER BY currency_code'),'currency_code');
    }
    public function cash_report(string $month,string $currency='IDR'): array
    {
        [$from,$to]=$this->period($month);
        if (!preg_match('/\A[A-Z]{3}\z/D',$currency)) throw new InvalidArgumentException('Pilih mata uang rekening.');
        // ALL movements, including originals and dated reversals. No effective/VOID filter.
        $rows=$this->rows("SELECT a.id,a.account_name,a.account_type,a.is_active,a.opening_balance,a.current_balance,
            COALESCE(SUM(CASE WHEN m.mutation_date<? THEN CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END ELSE 0 END),0) prior_net,
            COALESCE(SUM(CASE WHEN m.mutation_date>=? AND m.mutation_date<=? AND m.mutation_type='IN' THEN m.amount ELSE 0 END),0) inflow,
            COALESCE(SUM(CASE WHEN m.mutation_date>=? AND m.mutation_date<=? AND m.mutation_type='OUT' THEN m.amount ELSE 0 END),0) outflow,
            COALESCE(SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END),0) all_net
            FROM fin_company_account a LEFT JOIN fin_account_mutation_log m ON m.account_id=a.id
            WHERE a.currency_code=? GROUP BY a.id,a.account_name,a.account_type,a.is_active,a.opening_balance,a.current_balance ORDER BY a.id",[$from,$from,$to,$from,$to,$currency]);
        $total=['opening'=>0,'inflow'=>0,'outflow'=>0,'closing'=>0];$mismatch=0;
        foreach($rows as &$r) {
            $r['opening']=Finance_journal_policy::cents($r['opening_balance'])+Finance_journal_policy::cents($r['prior_net']);
            $r['inflow']=Finance_journal_policy::cents($r['inflow']);$r['outflow']=Finance_journal_policy::cents($r['outflow']);
            $r['closing']=$r['opening']+$r['inflow']-$r['outflow'];
            $r['live_difference']=Finance_journal_policy::cents($r['current_balance'])-Finance_journal_policy::cents($r['opening_balance'])-Finance_journal_policy::cents($r['all_net']);
            if ($r['live_difference']!==0) $mismatch++;
            foreach($total as $k=>$_)$total[$k]+=$r[$k];
        } unset($r);
        $ready=$this->ready();
        $class=$ready?"COALESCE(j.cashflow_class,'UNCLASSIFIED')":"'UNCLASSIFIED'";
        $join=$ready?'LEFT JOIN fin_gl_journal j ON j.source_mutation_id=m.id':'';
        $groups=$this->rows("SELECT $class flow_class,COUNT(*) row_count,
            SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE 0 END) inflow,
            SUM(CASE WHEN m.mutation_type='OUT' THEN m.amount ELSE 0 END) outflow
            FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id $join
            WHERE a.currency_code=? AND m.mutation_date>=? AND m.mutation_date<=? GROUP BY $class",[$currency,$from,$to]);
        foreach($groups as &$g){$g['inflow']=Finance_journal_policy::cents($g['inflow']);$g['outflow']=Finance_journal_policy::cents($g['outflow']);}unset($g);
        return compact('from','to','currency','rows','total','groups','mismatch');
    }
    public function queue(string $month,int $page=1): array
    {
        [$from,$to]=$this->period($month);$page=max(1,min(100000,$page));$offset=($page-1)*25;
        $opening=$this->opening();
        $after=$opening['journal_date']??'9999-12-31';
        $where="a.currency_code='IDR' AND m.amount<>0 AND m.mutation_date>=? AND m.mutation_date<=? AND m.mutation_date>? AND j.id IS NULL";
        $count=(int)$this->rows("SELECT COUNT(*) n FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id LEFT JOIN fin_gl_journal j ON j.source_mutation_id=m.id WHERE $where",[$from,$to,$after])[0]['n'];
        $rows=$this->rows("SELECT m.*,a.account_name FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id LEFT JOIN fin_gl_journal j ON j.source_mutation_id=m.id WHERE $where ORDER BY m.mutation_date,m.id LIMIT 25 OFFSET $offset",[$from,$to,$after]);
        return compact('rows','count','page');
    }
    private function source(int $id,bool $lock=false): array
    {
        $m=$this->rows('SELECT m.*,a.currency_code,a.account_name FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id WHERE m.id=?'.($lock?' FOR UPDATE':''),[$id])[0]??null;
        if (!$m || $m['currency_code']!=='IDR' || Finance_journal_policy::cents($m['amount'])<=0 || !in_array($m['mutation_type'],['IN','OUT'],true)) throw new InvalidArgumentException('Mutasi harus tersedia, nominal positif dan memakai IDR.');
        return $m;
    }
    private function cash_line(array $m): array
    {
        return ['account_code'=>'1100','company_account_id'=>(int)$m['account_id'],'debit'=>$m['mutation_type']==='IN'?$m['amount']:'0','credit'=>$m['mutation_type']==='OUT'?$m['amount']:'0'];
    }
    public function opening_cash(string $date): array
    {
        Finance_journal_policy::date($date);
        $rows=$this->rows("SELECT a.id,a.account_name,a.opening_balance,
            COALESCE(SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END),0) movement
            FROM fin_company_account a LEFT JOIN fin_account_mutation_log m ON m.account_id=a.id AND m.mutation_date<=?
            WHERE a.currency_code='IDR' GROUP BY a.id,a.account_name,a.opening_balance ORDER BY a.id",[$date]);
        $lines=[];
        foreach($rows as $r) {
            $n=Finance_journal_policy::cents($r['opening_balance'])+Finance_journal_policy::cents($r['movement']);
            if ($n!==0) $lines[]=['account_code'=>'1100','company_account_id'=>(int)$r['id'],'debit'=>Finance_journal_policy::decimal(max(0,$n)),
                'credit'=>Finance_journal_policy::decimal(max(0,-$n)),'account_name'=>$r['account_name']];
        }
        return $lines;
    }
    public function preview(string $kind,int $sourceId,string $date): array
    {
        Finance_journal_policy::date($date);
        $result=['kind'=>$kind,'date'=>$date,'fixed'=>[],'source'=>null,'source_hash'=>'','suggestion'=>null,'flow'=>'','warning'=>'','auto_counter'=>false,'assistant_options'=>[]];
        if ($kind==='MUTATION') {
            $m=$this->source($sourceId);$result['source']=$m;$result['source_hash']=Finance_journal_policy::source_hash($m);
            $result['date']=$m['mutation_date'];$result['fixed']=[$this->cash_line($m)];
            $result['warning']='Periksa dokumen sumber: pisahkan DP, pajak, piutang/utang, persediaan dan beban. Penerimaan kas bukan selalu penjualan; pembayaran bukan selalu beban. Jangan mencatat pengakuan yang sama dua kali.';
            if ($m['ref_module']==='FINANCE_TRANSFER') {
                $result['flow']='TRANSFER';$result['auto_counter']=true;
                $result['fixed'][]=['account_code'=>'1190','debit'=>$m['mutation_type']==='OUT'?$m['amount']:'0','credit'=>$m['mutation_type']==='IN'?$m['amount']:'0'];
            }
            if (!empty($m['reversal_of_mutation_id'])) {
                $original=$this->rows('SELECT * FROM fin_gl_journal WHERE source_mutation_id=?',[(int)$m['reversal_of_mutation_id']])[0]??null;
                if ($original) {
                    $result['fixed']=[$this->cash_line($m)];$result['flow']=$original['cashflow_class'];$result['auto_counter']=true;
                    foreach($this->rows('SELECT * FROM fin_gl_line WHERE journal_id=? AND company_account_id IS NULL ORDER BY line_no',[$original['id']]) as $l) $result['fixed'][]=['account_code'=>$l['account_code'],'debit'=>$l['credit'],'credit'=>$l['debit']];
                    $result['warning']='Pembalikan mengikuti akun jurnal asal secara otomatis. Periksa tanggal dan bukti; tidak perlu menebak akun baru.';
                }
            }
        } elseif ($kind==='OPENING') {
            $result['fixed']=$this->opening_cash($date);
            $result['source_hash']=hash('sha256',json_encode($result['fixed'],JSON_THROW_ON_ERROR));
            $result['warning']='Saldo awal dibuat satu kali pada akhir hari sebelum mulai pembukuan. Lengkapi persediaan, piutang, aset, utang dan ekuitas dari bukti; jangan menyeimbangkan selisih secara sembarang ke modal.';
        } elseif ($kind!=='ADJUSTMENT') throw new InvalidArgumentException('Jenis jurnal tidak tersedia.');
        if ($kind==='MUTATION' && !$result['auto_counter']) {
            $result['assistant_options']=$this->assistant_options($result['source']);
            foreach($result['assistant_options'] as $option)if($option['category_bound'])$result['suggestion']=$option['account_code'];
        }
        return $result;
    }
    private function tx(callable $work): array
    {
        if (!$this->ready()) return ['ok'=>false,'message'=>'Jurnal belum aktif. Admin perlu menerapkan SQL 2026-09-15a melalui IDE.'];
        if ($this->db->trans_begin()===false) return ['ok'=>false,'message'=>'Transaksi jurnal tidak dapat dimulai.'];
        try {
            if (!$this->rows('SELECT id FROM fin_gl_guard WHERE id=1 FOR UPDATE')) throw new RuntimeException('Guard jurnal belum siap.');
            $r=$work();
            if (!$this->db->trans_status() || $this->db->trans_commit()===false) throw new RuntimeException('Commit jurnal gagal.');
            return ['ok'=>true]+$r;
        } catch(Throwable $e) {
            $this->db->trans_rollback();
            if (!($e instanceof InvalidArgumentException)) log_message('error','Accounting write failed ('.get_class($e).').');
            return ['ok'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Jurnal gagal disimpan; tidak ada perubahan yang diterapkan. Muat ulang dan periksa kesiapan database.'];
        }
    }
    private function audit(int $id,array $header,array $lines,int $actor,string $ip,array $assistant=[]): void
    {
        if (!$this->db->insert('aud_transaction_log',['module_code'=>'FINANCE','action_code'=>'GL_POST','entity_table'=>'fin_gl_journal',
            'entity_id'=>$id,'actor_user_id'=>$actor,'source_ip'=>$ip?:null,'after_payload'=>json_encode(['header'=>$header,'lines'=>$lines,'assistant'=>$assistant],JSON_THROW_ON_ERROR),'notes'=>$header['reference']])) throw new RuntimeException('Audit jurnal gagal.');
    }
    public function post(array $p,int $actor,string $ip=''): array
    {
        return $this->tx(function()use($p,$actor,$ip) {
            if ($actor<=0) throw new InvalidArgumentException('Sesi pengguna tidak valid.');
            $key=$p['request_key']??'';
            if (!is_string($key) || !preg_match('/\A[a-f0-9]{32}\z/D',$key)) throw new InvalidArgumentException('Identitas formulir tidak valid. Muat ulang.');
            $hash=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));
            $existing=$this->rows('SELECT * FROM fin_gl_journal WHERE request_key=?',[$key])[0]??null;
            if ($existing) {
                if ((int)$existing['created_by']!==$actor || !hash_equals($existing['payload_hash'],$hash)) throw new InvalidArgumentException('Formulir sudah dipakai dengan isi berbeda. Muat ulang.');
                return ['id'=>(int)$existing['id'],'replayed'=>true];
            }
            $date=Finance_journal_policy::date($p['date']??'');
            if ($date>date('Y-m-d')) throw new InvalidArgumentException('Jurnal aktual tidak boleh bertanggal masa depan.');
            try { Finance_mutation_policy::assert_open_period($this->db,$date); }
            catch(RuntimeException $e){throw new InvalidArgumentException('Periode jurnal belum dapat dipakai atau sudah ditutup.');}
            $kind=$p['kind']??'';$accounts=$this->accounts();$opening=$this->opening();
            $header=['journal_date'=>$date,'kind'=>$kind,'reference'=>Finance_journal_policy::text($p['reference']??'',120),
                'memo'=>Finance_journal_policy::text($p['memo']??'',500),'request_key'=>$key,'payload_hash'=>$hash,'created_by'=>$actor];
            $input=$p['lines']??null;
            if (!is_array($input) || count($input)>100) throw new InvalidArgumentException('Baris jurnal tidak valid.');
            foreach($input as $line) {
                if (!is_array($line) || !empty($accounts[$line['account_code']??'']['is_cash']) || !empty($line['company_account_id'])) throw new InvalidArgumentException('Baris manual hanya untuk akun nonkas. Kas dibaca dari mutasi/saldo awal.');
            }
            $fixed=[];$assistant=[];
            if ($kind!=='MUTATION' && !empty($p['assistant_scenario']))throw new InvalidArgumentException('Asisten jenis transaksi hanya untuk mutasi kas.');
            if ($kind==='OPENING') {
                if ($opening || $this->rows('SELECT id FROM fin_gl_journal LIMIT 1')) throw new InvalidArgumentException('Saldo awal sudah ada; gunakan jurnal penyesuaian nonkas.');
                $fixed=$this->opening_cash($date);
                if (!is_string($p['source_hash']??null) || !hash_equals(hash('sha256',json_encode($fixed,JSON_THROW_ON_ERROR)),$p['source_hash'])) throw new InvalidArgumentException('Saldo awal kas berubah. Perbarui pratinjau sebelum posting.');
                foreach($fixed as &$line)unset($line['account_name']);unset($line);
                foreach($input as $line) if (in_array($accounts[$line['account_code']??'']['account_type']??'', ['INCOME','EXPENSE'],true)) throw new InvalidArgumentException('Saldo awal tidak menggunakan pendapatan/beban; gunakan saldo laba awal yang sudah ditinjau.');
                $header['opening_key']=1;
            } else {
                if (!$opening || $date<=$opening['journal_date']) throw new InvalidArgumentException('Siapkan saldo awal dahulu; jurnal berikutnya harus setelah tanggal saldo awal.');
                if ($kind==='MUTATION') {
                    $m=$this->source((int)($p['source_id']??0),true);
                    if ($date!==$m['mutation_date'] || !is_string($p['source_hash']??null) || !hash_equals(Finance_journal_policy::source_hash($m),$p['source_hash'])) throw new InvalidArgumentException('Mutasi berubah sejak dibuka. Perbarui pratinjau.');
                    if ($this->rows('SELECT id FROM fin_gl_journal WHERE source_mutation_id=?',[$m['id']])) throw new InvalidArgumentException('Mutasi ini sudah dijurnal; jangan mencatatnya lagi.');
                    $flow=$p['cashflow_class']??'';
                    if (!in_array($flow,['OPERATING','INVESTING','FINANCING','TRANSFER'],true)) throw new InvalidArgumentException('Pilih kelompok arus kas sesuai dokumen.');
                    $assistant=$this->validate_assistant($p,$m,$input,$flow);
                    $fixed=[$this->cash_line($m)];
                    if ($m['ref_module']==='FINANCE_TRANSFER') {
                        if ($flow!=='TRANSFER') throw new InvalidArgumentException('Transfer internal harus memakai kelompok transfer.');
                        $input=[['account_code'=>'1190','debit'=>$m['mutation_type']==='OUT'?$m['amount']:'0','credit'=>$m['mutation_type']==='IN'?$m['amount']:'0']];
                    } elseif ($flow==='TRANSFER') throw new InvalidArgumentException('Kelompok transfer hanya untuk transaksi transfer internal.');
                    if (!empty($m['reversal_of_mutation_id'])) {
                        $original=$this->rows('SELECT * FROM fin_gl_journal WHERE source_mutation_id=?',[(int)$m['reversal_of_mutation_id']])[0]??null;
                        if ($original) {
                            $originalSource=$this->source((int)$m['reversal_of_mutation_id'],true);
                            if (!hash_equals($original['source_hash'],Finance_journal_policy::source_hash($originalSource)) || $originalSource['account_id']!=$m['account_id'] || $originalSource['mutation_type']===$m['mutation_type'] || Finance_journal_policy::cents($originalSource['amount'])!==Finance_journal_policy::cents($m['amount'])) throw new InvalidArgumentException('Pembalikan tidak cocok dengan mutasi/jurnal asal; perlu ditinjau.');
                            $input=[];
                            foreach($this->rows('SELECT * FROM fin_gl_line WHERE journal_id=? AND company_account_id IS NULL ORDER BY line_no',[$original['id']]) as $l) $input[]=['account_code'=>$l['account_code'],'debit'=>$l['credit'],'credit'=>$l['debit']];
                            $flow=$original['cashflow_class'];
                        } else {
                            $old=$this->source((int)$m['reversal_of_mutation_id'],true);
                            if ($old['mutation_date']>$opening['journal_date']) throw new InvalidArgumentException('Jurnalkan mutasi asal dahulu agar akun pembalikan sesuai.');
                        }
                    }
                    $header+=['source_mutation_id'=>(int)$m['id'],'source_hash'=>Finance_journal_policy::source_hash($m),'cashflow_class'=>$flow];
                } elseif ($kind==='REVERSAL') {
                    $original=$this->rows('SELECT * FROM fin_gl_journal WHERE id=? FOR UPDATE',[(int)($p['journal_id']??0)])[0]??null;
                    if (!$original || $original['kind']!=='ADJUSTMENT' || $date<$original['journal_date']) throw new InvalidArgumentException('Pembalikan jurnal hanya untuk penyesuaian nonkas, tidak boleh mendahului asal.');
                    if ($this->rows('SELECT id FROM fin_gl_journal WHERE reversal_of=?',[$original['id']])) throw new InvalidArgumentException('Jurnal sudah dibalik.');
                    $input=[];
                    foreach($this->rows('SELECT * FROM fin_gl_line WHERE journal_id=? ORDER BY line_no',[$original['id']]) as $l) $input[]=['account_code'=>$l['account_code'],'debit'=>$l['credit'],'credit'=>$l['debit']];
                    $header['reversal_of']=(int)$original['id'];
                } elseif ($kind!=='ADJUSTMENT') throw new InvalidArgumentException('Jenis jurnal tidak tersedia.');
            }
            $allLines=array_merge($fixed,$input);
            // A genuinely empty new business can start with an explicitly confirmed zero opening.
            $lines=$kind==='OPENING' && !$allLines?[]:Finance_journal_policy::lines($allLines,$accounts);
            if (!$this->db->insert('fin_gl_journal',$header)) throw new RuntimeException('Header jurnal gagal.');
            $id=(int)$this->db->insert_id();
            foreach($lines as $i=>$line) if (!$this->db->insert('fin_gl_line',$line+['journal_id'=>$id,'line_no'=>$i+1])) throw new RuntimeException('Baris jurnal gagal.');
            $this->audit($id,$header,$lines,$actor,$ip,$assistant);
            return ['id'=>$id];
        });
    }
    public function journal_list(string $month,int $page=1,int $id=0): array
    {
        [$from,$to]=$this->period($month);$page=max(1,min(100000,$page));$offset=($page-1)*25;
        $count=(int)$this->rows('SELECT COUNT(*) n FROM fin_gl_journal WHERE journal_date>=? AND journal_date<=?',[$from,$to])[0]['n'];
        $rows=$this->rows("SELECT j.*,COALESCE(t.total,0) total FROM fin_gl_journal j LEFT JOIN (SELECT journal_id,SUM(debit) total FROM fin_gl_line GROUP BY journal_id) t ON t.journal_id=j.id WHERE j.journal_date>=? AND j.journal_date<=? ORDER BY j.journal_date DESC,j.id DESC LIMIT 25 OFFSET $offset",[$from,$to]);
        $detail=$id>0?($this->rows('SELECT * FROM fin_gl_journal WHERE id=?',[$id])[0]??null):null;
        $lines=$detail?$this->rows('SELECT l.*,a.name,b.account_name FROM fin_gl_line l JOIN fin_gl_account a ON a.code=l.account_code LEFT JOIN fin_company_account b ON b.id=l.company_account_id WHERE l.journal_id=? ORDER BY l.line_no',[$id]):[];
        return compact('rows','count','page','detail','lines');
    }
    public function statements(string $month): array
    {
        [$from,$to]=$this->period($month);$opening=$this->opening();
        $rows=$this->rows("SELECT a.code,a.name,a.account_type,
            COALESCE(SUM(CASE WHEN j.journal_date<? THEN l.debit-l.credit ELSE 0 END),0) opening,
            COALESCE(SUM(CASE WHEN j.journal_date>=? THEN l.debit ELSE 0 END),0) debit,
            COALESCE(SUM(CASE WHEN j.journal_date>=? THEN l.credit ELSE 0 END),0) credit,
            COALESCE(SUM(l.debit-l.credit),0) closing
            FROM fin_gl_account a LEFT JOIN (fin_gl_line l JOIN fin_gl_journal j ON j.id=l.journal_id AND j.journal_date<=?) ON l.account_code=a.code
            GROUP BY a.code,a.name,a.account_type ORDER BY a.code",[$from,$from,$from,$to]);
        $sum=['assets'=>0,'liabilities'=>0,'equity'=>0,'income'=>0,'expense'=>0,'prior_earnings'=>0,'opening_equity'=>0,'capital_movement'=>0,'trial_difference'=>0];
        foreach($rows as &$r) {
            foreach(['opening','debit','credit','closing'] as $k)$r[$k]=Finance_journal_policy::cents($r[$k]);
            $type=$r['account_type'];$sum['trial_difference']+=$r['closing'];
            if ($type==='ASSET')$sum['assets']+=$r['closing'];
            if ($type==='LIABILITY')$sum['liabilities']-=$r['closing'];
            if ($type==='EQUITY'){$sum['equity']-=$r['closing'];$sum['opening_equity']-=$r['opening'];$sum['capital_movement']+=$r['credit']-$r['debit'];}
            if (in_array($type,['INCOME','EXPENSE'],true)) {
                $sum['prior_earnings']-=$r['opening'];
                if ($type==='INCOME')$sum['income']+=$r['credit']-$r['debit'];
                else $sum['expense']+=$r['debit']-$r['credit'];
            }
        }unset($r);
        $sum['profit']=$sum['income']-$sum['expense'];
        $sum['total_equity']=$sum['equity']+$sum['prior_earnings']+$sum['profit'];
        $sum['balance_difference']=$sum['assets']-$sum['liabilities']-$sum['total_equity'];
        $pending=(int)$this->rows("SELECT COUNT(*) n FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id LEFT JOIN fin_gl_journal j ON j.source_mutation_id=m.id
            WHERE a.currency_code='IDR' AND m.amount<>0 AND m.mutation_date>? AND m.mutation_date<=? AND j.id IS NULL",[$opening['journal_date']??'0001-01-01',$to])[0]['n'];
        // Bounded fail-closed comparison, including deleted or reclassified linked source records.
        $sources=$this->rows('SELECT m.*,j.source_hash AS expected_hash,a.currency_code FROM fin_gl_journal j LEFT JOIN fin_account_mutation_log m ON m.id=j.source_mutation_id LEFT JOIN fin_company_account a ON a.id=m.account_id WHERE j.source_mutation_id IS NOT NULL AND j.journal_date<=? ORDER BY j.id LIMIT 20001',[$to]);
        $stale=0;$verificationComplete=count($sources)<=20000;
        if ($verificationComplete) foreach($sources as $m) {
            if (empty($m['id']) || $m['currency_code']!=='IDR' || !hash_equals($m['expected_hash'],Finance_journal_policy::source_hash($m)))$stale++;
        }
        $cash=$this->cash_report($month,'IDR');
        $glCash=$this->rows('SELECT l.company_account_id,SUM(l.debit-l.credit) balance FROM fin_gl_line l JOIN fin_gl_journal j ON j.id=l.journal_id WHERE l.company_account_id IS NOT NULL AND j.journal_date<=? GROUP BY l.company_account_id',[$to]);
        $glMap=[];foreach($glCash as $g)$glMap[(int)$g['company_account_id']]=Finance_journal_policy::cents($g['balance']);
        $cashDifference=[];
        foreach($cash['rows'] as $r) if (($glMap[(int)$r['id']]??0)!==$r['closing'])$cashDifference[]=['name'=>$r['account_name'],'difference'=>($glMap[(int)$r['id']]??0)-$r['closing']];
        $cashIds=array_map('intval',array_column($cash['rows'],'id'));
        foreach($glMap as $id=>$balance) if (!in_array($id,$cashIds,true) && $balance!==0)$cashDifference[]=['name'=>'Rekening #'.$id.' hilang/berubah mata uang','difference'=>$balance];
        $warnings=['Laporan dari jurnal yang sudah diposting, bukan estimasi. Angka seimbang belum membuktikan pengakuan/pelengkapan akrual benar.',
            'HPP, persediaan, penyusutan, pajak, gaji terutang dan transaksi kredit harus dijurnal berdasarkan bukti; belum dibuat otomatis dari modul sumber.',
            'Hanya IDR; rekening OTHER dan EWALLET perlu ditinjau apakah memenuhi kebijakan kas/setara kas. Belum merupakan klaim kepatuhan SAK/IFRS atau laporan tersahkan.'];
        if (!$opening || $opening['journal_date']>=$from)$warnings[]='Saldo awal belum tersedia sebelum awal periode. Perbandingan periode belum lengkap.';
        if ($pending)$warnings[]="$pending mutasi sampai akhir periode belum dijurnal.";
        if ($stale)$warnings[]="$stale sumber jurnal berubah/hilang. Telusuri dan koreksi; jangan menerbitkan laporan final.";
        if (!$verificationComplete)$warnings[]='Batas verifikasi 20.000 sumber terlampaui; pemeriksaan kelengkapan belum selesai.';
        if ($cashDifference)$warnings[]='Kas pada buku besar belum cocok dengan mutasi sumber untuk '.count($cashDifference).' rekening.';
        if ($cash['mismatch'])$warnings[]='Saldo tersimpan saat ini berbeda dari saldo awal + seluruh mutasi; perlu penelusuran historis.';
        foreach($rows as $r) if ((string)$r['code']==='1190' && $r['closing']!==0)$warnings[]='Akun perantara transfer belum nol. Telusuri pasangan rekening/tanggal; jangan menganggap selisih sebagai laba.';
        return compact('rows','sum','pending','stale','verificationComplete','cashDifference','warnings','opening');
    }
    public function ledger(string $month,string $code,int $page=1): array
    {
        [$from,$to]=$this->period($month);
        if (!isset($this->accounts()[$code])) throw new InvalidArgumentException('Pilih akun buku besar.');
        $page=max(1,min(100000,$page));$offset=($page-1)*50;
        $opening=Finance_journal_policy::cents($this->rows('SELECT COALESCE(SUM(l.debit-l.credit),0) n FROM fin_gl_line l JOIN fin_gl_journal j ON j.id=l.journal_id WHERE l.account_code=? AND j.journal_date<?',[$code,$from])[0]['n']);
        $count=(int)$this->rows('SELECT COUNT(*) n FROM fin_gl_line l JOIN fin_gl_journal j ON j.id=l.journal_id WHERE l.account_code=? AND j.journal_date>=? AND j.journal_date<=?',[$code,$from,$to])[0]['n'];
        $rows=$this->rows("SELECT l.*,j.journal_date,j.reference,j.memo,a.account_name FROM fin_gl_line l JOIN fin_gl_journal j ON j.id=l.journal_id LEFT JOIN fin_company_account a ON a.id=l.company_account_id WHERE l.account_code=? AND j.journal_date>=? AND j.journal_date<=? ORDER BY j.journal_date,l.id LIMIT 50 OFFSET $offset",[$code,$from,$to]);
        return compact('rows','opening','count','page','code');
    }
}
