<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_insights extends MY_Controller
{
    private const PAGE = 'finance.control.index';
    private const CSRF = 'finance_control_csrf';
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_insight_model');
    }
    public function index()
    {
        $this->require_permission(self::PAGE,'view');
        $tab=(string)($this->input->get('tab',true) ?: 'settlement');
        if (!in_array($tab,['settlement','quality','cash-plan','profit-loss','approvals','settings','bank-review'],true)) { show_404(); return; }
        $token=$this->session->userdata(self::CSRF);
        if (!is_string($token)||!preg_match('/\A[a-f0-9]{64}\z/D',$token)) { $token=bin2hex(random_bytes(32));$this->session->set_userdata(self::CSRF,$token); }
        $data=['page_title'=>'Kontrol Keuangan','active_menu'=>'finance.control','finance_tab_active'=>'control','tab'=>$tab,
            'can_edit'=>$this->can(self::PAGE,'edit'),'csrf'=>$token,'error'=>'','result'=>[], 'methods'=>[],
            'date'=>date('Y-m-d'),'method_id'=>0,'month'=>date('Y-m'),'days'=>7,
            'can_void_modules'=>['FINANCE'=>$this->can('purchase.order.index','edit'),'FINANCE_RECON'=>$this->can('finance.cash_reconciliation.index','edit'),'REVENUE_RECON'=>$this->can('finance.revenue_reconciliation.index','edit')]];
        $data['operations_ready']=Finance_settlement_control::operations_ready($this->db);
        $data['allocation_ready']=Finance_allocation_policy::ready($this->db);
        $data['can_approve']=$this->can('finance.control.approve','edit');$data['can_settings']=$this->can('finance.control.settings','edit');
        try {
            if ($data['operations_ready']) $this->load->model('Finance_control_operation_model');
            if (!Finance_settlement_control::ready($this->db)) throw new RuntimeException('Modul belum siap. Admin perlu menjalankan migrasi 2026-09-14a.');
            if ($tab==='settlement') {
                $data['date']=Finance_insight_model::date_value($this->input->get('date',true) ?: date('Y-m-d'));
                $data['methods']=$this->Finance_insight_model->methods();
                $data['method_id']=(int)($this->input->get('method_id',true) ?: ($data['methods'][0]['id']??0));
                if (!in_array($data['method_id'],array_map('intval',array_column($data['methods'],'id')),true)) {
                    $historic=$this->db->query('SELECT pm.id,pm.method_name,a.account_name FROM fin_settlement_control c JOIN pos_payment_method pm ON pm.id=c.payment_method_id JOIN fin_company_account a ON a.id=c.account_id WHERE c.revenue_date=? AND c.payment_method_id=?',[$data['date'],$data['method_id']])->row_array();
                    if (!$historic) throw new RuntimeException('Pilih metode pembayaran dengan rekening IDR aktif.');
                    $data['methods'][]=$historic;
                }
                $data['result']=$this->Finance_insight_model->settlement($data['date'],$data['method_id'],max(1,(int)$this->input->get('page',true)));
                if ($data['operations_ready']) $data['operation_details']=$this->Finance_control_operation_model->details((int)($data['result']['case']['id']??0));
            } elseif ($tab==='cash-plan') {
                $data['result']=$this->Finance_insight_model->forecast((int)$this->input->get('days',true));
                $data['days']=$data['result']['days'];
            } elseif ($tab==='bank-review') {
                if (!$data['allocation_ready']) throw new RuntimeException('SQL 2026-09-14c belum dijalankan.');
                $data['accounts']=$this->db->query("SELECT id,account_name FROM fin_company_account WHERE is_active=1 AND currency_code='IDR' ORDER BY account_name")->result_array();
                $data['result']=$this->Finance_control_operation_model->bank_rows(['account_id'=>$this->input->get('account_id',true)?:($data['accounts'][0]['id']??0),'page'=>$this->input->get('page',true)]);
            } elseif ($tab==='approvals' || $tab==='settings') {
                if (!$data['operations_ready']) throw new RuntimeException('Jalankan migrasi 2026-09-14b.');
                $data['result']=$tab==='approvals'?$this->Finance_control_operation_model->approvals((int)$this->input->get('page',true)):Finance_settlement_control::policy($this->db);
            } else {
                $month=(string)($this->input->get('month',true) ?: date('Y-m'));
                Finance_insight_model::date_value($month.'-01');
                $data['month']=$month;$from=$month.'-01';$to=date('Y-m-t',strtotime($from));
                $data['result']=$tab==='quality'?$this->Finance_insight_model->quality($from,$to):$this->Finance_insight_model->profit_loss($from,$to);
            }
        } catch (Throwable $e) { log_message('error','Finance control read: '.$e->getMessage());$data['error']='Kontrol keuangan belum dapat ditampilkan. Periksa filter, rekening aktif, dan kesiapan migrasi; hubungi administrator bila berlanjut.'; }
        $this->render('finance/control',$data);
    }
    public function save($kind='')
    {
        $this->require_permission(self::PAGE,'edit');
        $expected=$this->session->userdata(self::CSRF);$provided=$this->input->get_request_header('X-Finance-Control-CSRF',false);
        if ($this->input->method(true)!=='POST'||!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/D',$expected)||!is_string($provided)||!hash_equals($expected,$provided)) {
            $this->json(['ok'=>false,'message'=>'Sesi formulir tidak valid. Muat ulang halaman.'],403);return;
        }
        $operations=['receipt'=>'save_receipt','void-receipt'=>'void_receipt','charge'=>'save_charge','plan-link'=>'link_plan','plan-unlink'=>'unlink_plan','policy'=>'save_policy','approval-request'=>'request_approval','approval-review'=>'review_approval',
            'receipt-distribute'=>'distribute_receipt','plan-allocate'=>'allocate_plan','plan-allocation-unlink'=>'unlink_plan_allocation','statement-import'=>'import_statement','statement-match'=>'match_statement'];
        if (!in_array($kind,['settlement','cash-plan','void-adjustment'],true) && !isset($operations[$kind])) { $this->json(['ok'=>false,'message'=>'Aksi tidak tersedia.'],404);return; }
        $raw=(string)$this->input->raw_input_stream;
        $payload=strlen($raw)<=($kind==='statement-import'?2097152:16384)?json_decode($raw,true,8):null;
        if (!is_array($payload)) { $this->json(['ok'=>false,'message'=>'Formulir tidak valid.'],400);return; }
        foreach ($payload as $value) if (!is_scalar($value) && $value!==null) { $this->json(['ok'=>false,'message'=>'Isian formulir tidak valid.'],400);return; }
        $actor=(int)($this->current_user['id']??0);$ip=(string)$this->input->ip_address();
        if (isset($operations[$kind])) {
            if ($kind==='policy') $this->require_permission('finance.control.settings','edit');
            if ($kind==='approval-review') $this->require_permission('finance.control.approve','edit');
            $originId=$kind==='charge'?(int)($payload['existing_mutation_id']??0):($kind==='approval-request' && ($payload['action_code']??'')==='VOID_ADJUSTMENT'?(int)($payload['target_id']??0):0);
            if ($originId) {
                $page=$this->Finance_insight_model->adjustment_permission($originId);
                if (!$page) { $this->json(['ok'=>false,'message'=>'Mutasi asal tidak sesuai.'],422);return; }
                $this->require_permission($page,'edit');
            }
            $this->load->model('Finance_control_operation_model');
            $result=$this->Finance_control_operation_model->{$operations[$kind]}($payload,$actor,$ip);
            $this->json($result,empty($result['ok'])?422:200);return;
        }
        if ($kind==='void-adjustment') {
            $page=$this->Finance_insight_model->adjustment_permission((int)($payload['mutation_id']??0));
            if (!$page) { $this->json(['ok'=>false,'message'=>'Penyesuaian tertaut tidak ditemukan.'],422);return; }
            $this->require_permission($page,'edit');
            $result=$this->Finance_insight_model->void_adjustment($payload,$actor,$ip);
        } else $result=$kind==='settlement'?$this->Finance_insight_model->save_settlement($payload,$actor,$ip):$this->Finance_insight_model->save_plan($payload,$actor,$ip);
        $this->json($result,empty($result['ok'])?422:200);
    }

    public function lookup($kind='settlements')
    {
        if ($kind==='settlements' || $kind==='charges') {
            $allowed=false;foreach([self::PAGE,'purchase.order.index','finance.cash_reconciliation.index','finance.revenue_reconciliation.index'] as $page) if($this->can($page,'view'))$allowed=true;
            if (!$allowed) { $this->json(['ok'=>false,'message'=>'Tidak memiliki hak melihat.'],403);return; }
        } else $this->require_permission(self::PAGE,'view');
        try {
            $this->load->model('Finance_control_operation_model');$p=[];
            foreach(['q','page','from','to','method_id','id','complete'] as $key) { $v=$this->input->get($key,true);if($v!==null){if(!is_scalar($v))throw new RuntimeException('Filter tidak valid.');$p[$key]=$v;} }
            if ($kind==='settlements') $result=$this->Finance_control_operation_model->search($p);
            elseif ($kind==='charges') {
                if (!Finance_settlement_control::operations_ready($this->db)) $result=['rows'=>[]];
                else { $rows=$this->Finance_control_operation_model->details((int)($p['id']??0))['charges']??[];
                    $result=['rows'=>array_map(static fn($r)=>array_intersect_key($r,array_flip(['id','document_no','line_reference','category','direction','amount','charge_date','account_id','mutation_id'])), $rows)]; }
            } elseif ($kind==='mutations') $result=$this->Finance_control_operation_model->search_mutations($p);
            else { $this->json(['ok'=>false,'message'=>'Pencarian tidak tersedia.'],404);return; }
            $this->json(['ok'=>true]+$result,200);
        } catch(Throwable $e) { log_message('error','Finance lookup: '.$e->getMessage());$this->json(['ok'=>false,'message'=>'Pencarian belum dapat dilakukan. Periksa filter, sesi, dan migrasi.'],422); }
    }
    public function evidence_upload()
    {
        $this->require_permission(self::PAGE,'edit');
        $expected=$this->session->userdata(self::CSRF);$provided=$this->input->get_request_header('X-Finance-Control-CSRF',false);
        if ($this->input->method(true)!=='POST'||!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/D',$expected)||!is_string($provided)||!hash_equals($expected,$provided)) { $this->json(['ok'=>false,'message'=>'Sesi formulir tidak valid. Muat ulang.'],403);return; }
        $this->load->model('Finance_control_operation_model');
        $result=$this->Finance_control_operation_model->upload_evidence((int)$this->input->post('settlement_id'),(array)($_FILES['evidence']??[]),(int)($this->current_user['id']??0),(string)$this->input->ip_address());
        $this->json($result,empty($result['ok'])?422:200);
    }
    public function evidence_download($id=0)
    {
        $this->require_permission(self::PAGE,'view');$this->load->model('Finance_control_operation_model');
        require_once APPPATH.'libraries/Finance_control_evidence.php';
        try { $row=$this->Finance_control_operation_model->evidence_record((int)$id);if(!$row)throw new RuntimeException('Bukti tidak ditemukan.');$path=Finance_control_evidence::download_path($row); }
        catch(Throwable $e) { log_message('error','Finance evidence read: '.$e->getMessage());show_404();return; }
        $this->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')
            ->set_header("Content-Disposition: attachment; filename=\"bukti-".(int)$id."\"; filename*=UTF-8''".rawurlencode($row['original_name']))
            ->set_content_type('application/octet-stream')->set_output(file_get_contents($path));
    }
    private function json(array $data,int $status): void
    {
        $this->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
