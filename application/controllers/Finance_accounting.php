<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_accounting extends MY_Controller
{
    private const PAGE='finance.accounting.index';
    private const CSRF='finance_accounting_csrf';
    private const SETTINGS='finance.accounting.settings';
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_accounting_model');
    }
    public function index()
    {
        $this->require_permission(self::PAGE,'view');
        $tab=$this->input->get('tab',true)?:'cash';
        if (!is_string($tab) || !in_array($tab,['cash','queue','journals','entry','ledger','trial','profit','balance','equity','guide','settings'],true)) { show_404();return; }
        if ($tab==='settings')$this->require_permission(self::SETTINGS,'view');
        $token=$this->session->userdata(self::CSRF);
        if (!is_string($token)||!preg_match('/\A[a-f0-9]{64}\z/D',$token)) {$token=bin2hex(random_bytes(32));$this->session->set_userdata(self::CSRF,$token);}
        $data=['page_title'=>'Akuntansi dan Jurnal','active_menu'=>'finance.accounting','finance_tab_active'=>'accounting',
            'tab'=>$tab,'month'=>date('Y-m'),'currency'=>'IDR','csrf'=>$token,'can_post'=>$this->can(self::PAGE,'create'),
            'ready'=>$this->Finance_accounting_model->ready(),'error'=>'','result'=>[],'accounts'=>[],'opening'=>null,'currencies'=>[],
            'can_view_setup'=>$this->can(self::SETTINGS,'view'),'can_setup'=>$this->can(self::SETTINGS,'edit')];
        try {
            $month=$this->input->get('month',true)?:date('Y-m');
            if (!is_string($month)) throw new InvalidArgumentException('Bulan tidak valid.');
            $this->Finance_accounting_model->period($month);$data['month']=$month;
            $data['accounts']=$this->Finance_accounting_model->accounts();$data['opening']=$this->Finance_accounting_model->opening();
            $page=max(1,(int)$this->input->get('page',true));
            if ($tab==='cash') {
                $currency=$this->input->get('currency',true)?:'IDR';
                if (!is_string($currency)) throw new InvalidArgumentException('Mata uang tidak valid.');
                $data['currency']=$currency;$data['currencies']=$this->Finance_accounting_model->currencies();
                $data['result']=$this->Finance_accounting_model->cash_report($month,$currency);
            } elseif ($tab==='guide') {
                // Guidance remains available before schema activation.
            } elseif (!$data['ready']) throw new InvalidArgumentException('Modul jurnal belum aktif. Admin perlu menerapkan SQL 2026-09-15a melalui IDE.');
            elseif ($tab==='queue') $data['result']=$this->Finance_accounting_model->queue($month,$page);
            elseif ($tab==='journals') $data['result']=$this->Finance_accounting_model->journal_list($month,$page,(int)$this->input->get('id',true));
            elseif ($tab==='settings')$data['result']=$this->Finance_accounting_model->setup_data();
            elseif ($tab==='entry') {
                $this->require_permission(self::PAGE,'create');
                $kind=$this->input->get('kind',true)?:'ADJUSTMENT';$date=$this->input->get('date',true)?:date('Y-m-d');
                if (!is_string($kind)||!is_string($date)) throw new InvalidArgumentException('Jenis/tanggal formulir tidak valid.');
                $data['result']=$this->Finance_accounting_model->preview($kind,(int)$this->input->get('source_id',true),$date);
            } elseif ($tab==='ledger') {
                $code=$this->input->get('code',true)?:'1100';
                if (!is_string($code)) throw new InvalidArgumentException('Kode akun tidak valid.');
                $data['result']=$this->Finance_accounting_model->ledger($month,$code,$page);
            } else $data['result']=$this->Finance_accounting_model->statements($month);
        } catch(Throwable $e) {
            log_message('error','Accounting read failed ('.get_class($e).').');
            $data['error']=$e instanceof InvalidArgumentException?$e->getMessage():'Laporan belum dapat ditampilkan. Periksa kesiapan migrasi atau hubungi administrator.';
        }
        $this->output->set_header('Cache-Control: private, no-store');
        $this->render('finance/accounting',$data);
    }
    public function post()
    {
        $this->require_permission(self::PAGE,'create');
        $expected=$this->session->userdata(self::CSRF);$provided=$this->input->get_request_header('X-Finance-Accounting-CSRF',false);
        if ($this->input->method(true)!=='POST'||!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/D',$expected)||!is_string($provided)||!hash_equals($expected,$provided)) {
            $this->respond(['ok'=>false,'message'=>'Sesi formulir tidak valid. Muat ulang halaman.'],403);return;
        }
        $raw=(string)$this->input->raw_input_stream;
        $p=strlen($raw)<=65536?json_decode($raw,true,8):null;
        if (!is_array($p)) {$this->respond(['ok'=>false,'message'=>'Formulir tidak valid atau terlalu besar.'],400);return;}
        $result=$this->Finance_accounting_model->post($p,(int)($this->current_user['id']??0),(string)$this->input->ip_address());
        $this->respond($result,empty($result['ok'])?422:200);
    }
    private function respond(array $data,int $status): void
    {
        $this->output->set_status_header($status)->set_header('Cache-Control: private, no-store')->set_content_type('application/json')
            ->set_output(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
    }
    public function save_setup($kind='')
    {
        $this->require_permission(self::PAGE,'view');
        $this->require_permission(self::SETTINGS,'edit');
        $expected=$this->session->userdata(self::CSRF);$provided=$this->input->get_request_header('X-Finance-Accounting-CSRF',false);
        if ($this->input->method(true)!=='POST'||!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/D',$expected)||!is_string($provided)||!hash_equals($expected,$provided)) {
            $this->respond(['ok'=>false,'message'=>'Sesi pengaturan tidak valid. Muat ulang halaman.'],403);return;
        }
        $raw=(string)$this->input->raw_input_stream;$p=strlen($raw)<=16384?json_decode($raw,true,6):null;
        if (!in_array($kind,['account','mapping'],true)||!is_array($p)){$this->respond(['ok'=>false,'message'=>'Isian pengaturan tidak valid.'],400);return;}
        $r=$this->Finance_accounting_model->save_setup($kind,$p,(int)($this->current_user['id']??0),(string)$this->input->ip_address());
        $this->respond($r,empty($r['ok'])?422:200);
    }
}
