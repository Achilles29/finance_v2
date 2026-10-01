<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Roast_integrations extends MY_Controller
{
    private const CSRF = 'roast_connect_admin_csrf';

    public function __construct()
    {
        parent::__construct();
        if (!$this->is_superadmin() || empty($this->current_user['id'])) {
            show_error('Koneksi aplikasi hanya dapat dikelola superadmin Finance.',403,'Akses Ditolak');
        }
        $this->load->model('Roast_connect_model');
        $this->output->set_header('Cache-Control: no-store')->set_header('Referrer-Policy: no-referrer');
    }

    private function csrf(): string
    {
        $token = $this->session->userdata(self::CSRF);
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) {
            $token = bin2hex(random_bytes(32)); $this->session->set_userdata(self::CSRF,$token);
        }
        return $token;
    }

    public function index(): void
    {
        if ($this->input->method(true) !== 'GET') { show_error('Gunakan GET untuk membuka pengaturan.',405); return; }
        if (!$this->Roast_connect_model->ready()) { show_error('Migration Roast Connect belum dipasang.',503); return; }
        $this->render('system/roast_connect',[
            'title'=>'Integrasi Roast Studio','active_menu'=>'system.roast_connect',
            'connector'=>$this->Roast_connect_model->settings(),'divisions'=>$this->Roast_connect_model->divisions(),
            'connector_audit'=>$this->Roast_connect_model->audit(),'connector_csrf'=>$this->csrf(),
        ]);
    }

    private function mutate(bool $rotate): void
    {
        if ($this->input->method(true) !== 'POST') { $this->json(['ok'=>false,'message'=>'Gunakan POST.'],405); return; }
        $csrf = $this->input->post('connector_csrf',false);
        if (!is_string($csrf) || !hash_equals($this->csrf(),$csrf)) { $this->json(['ok'=>false,'message'=>'Sesi formulir tidak valid. Muat ulang halaman.'],403); return; }
        try {
            $enabled = $this->input->post('enabled',false);
            if (!in_array($enabled,['0','1'],true)) throw new RuntimeException('Status konektor tidak valid.');
            $result = $this->Roast_connect_model->change([
                'name'=>$this->input->post('name',false),'enabled'=>$enabled==='1',
                'revision'=>$this->input->post('revision',false),'division_id'=>$this->input->post('division_id',false),
                'destination_type'=>$this->input->post('destination_type',false),
                'valid_days'=>filter_var($this->input->post('valid_days',false),FILTER_VALIDATE_INT),
            ],(int)$this->current_user['id'],$rotate);
            $this->json(['ok'=>true]+$result);
        } catch (RuntimeException $error) {
            $this->json(['ok'=>false,'message'=>$error->getMessage()],422);
        } catch (Throwable $error) {
            log_message('error','Roast Connect settings update failed.');
            $this->json(['ok'=>false,'message'=>'Pengaturan belum dapat disimpan. Muat ulang dan coba kembali.'],500);
        }
    }

    private function json(array $data,int $status=200): void
    {
        $this->output->set_status_header($status)->set_content_type('application/json','utf-8')
            ->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff')
            ->set_output(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    public function save(): void { $this->mutate(false); }
    public function rotate(): void { $this->mutate(true); }
}
