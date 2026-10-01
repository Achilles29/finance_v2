<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Application_update extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if(!$this->is_superadmin()||$this->input->is_cli_request())show_error('Pembaruan hanya dapat dikelola administrator utama.',403);
        require_once APPPATH.'libraries/CustomerPlatform.php';
    }
    private function root(): string { return CustomerPlatform::root(rtrim(FCPATH,'/\\')); }
    private function status(): array
    {
        $root=$this->root();$path=$root.'/storage/setup/update-status.json';
        if(!CustomerPlatform::portable($root))return ['phase'=>'MASTER_SOURCE'];
        if(!is_file($path))return ['phase'=>'WAITING_SERVICE'];
        try{return CustomerPlatform::document($root,$path);}catch(Throwable $e){return ['phase'=>'ATTENTION','code'=>'UPDATE_STATUS_UNAVAILABLE'];}
    }
    public function index()
    {
        if(!$this->session->userdata('application_update_csrf'))$this->session->set_userdata('application_update_csrf',bin2hex(random_bytes(32)));
        $this->render('system/application_update',['page_title'=>'Pembaruan aplikasi','active_menu'=>'system.license.index',
            'update'=>$this->status(),'update_csrf'=>$this->session->userdata('application_update_csrf')]);
    }
    public function confirm()
    {
        if($this->input->method(TRUE)!=='POST')show_error('Gunakan tombol Pasang pembaruan.',405);
        $token=(string)$this->input->post('update_csrf');$expected=(string)$this->session->userdata('application_update_csrf');
        if(strlen($expected)!==64||!hash_equals($expected,$token))show_error('Sesi berubah. Muat ulang halaman.',403);
        $s=$this->status();$hash=(string)$this->input->post('plan_sha256');
        if(($s['phase']??'')!=='READY'||!preg_match('/\A[a-f0-9]{64}\z/D',$hash)||!hash_equals($s['plan_sha256']??'',$hash)
            ||$this->input->post('confirmed')!=='1')show_error('Periksa versi yang tersedia dan setujui jeda penggunaan aplikasi.',409);
        $dir=$this->root().'/storage/inbox';
        if(is_link($dir)||realpath($dir)!==$dir)show_error('Folder antrean belum siap. Hubungi administrator.',503);
        $file=$dir.'/update-'.$hash.'.json';
        if(!file_exists($file)){
            $mask=umask(0007);$tmp=$dir.'/.update-confirm-'.bin2hex(random_bytes(16));$h=null;
            try{$h=fopen($tmp,'xb');$bytes=json_encode(['plan_sha256'=>$hash,'confirmed'=>true],JSON_THROW_ON_ERROR);
                if(!$h||fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h))throw new RuntimeException('WRITE_FAILED');
                fclose($h);$h=null;if(!rename($tmp,$file))throw new RuntimeException('WRITE_FAILED');
            }catch(Throwable $e){show_error('Persetujuan belum tersimpan. Silakan coba kembali.',503);
            }finally{if(is_resource($h))fclose($h);if(is_file($tmp))unlink($tmp);umask($mask);}
        }
        $this->session->set_flashdata('success','Pembaruan dijadwalkan. Pendamping akan memulai pada pemeriksaan berikutnya. Jangan menutup layanan server.');
        redirect('system/updates');
    }
}
