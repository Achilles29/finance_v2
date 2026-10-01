<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Business_profile extends MY_Controller
{
    private const PAGE = 'system.business_profile';
    private const CSRF_KEY = 'business_profile_form_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Business_profile_model');
    }

    public function index()
    {
        $this->require_permission(self::PAGE, 'view');
        $this->require_schema();
        if ($this->input->method(true) === 'POST') {
            $this->require_permission(self::PAGE, 'edit');
            if (!$this->require_csrf()) return;
            $current = $this->Business_profile_model->profile();
            $upload = $this->store_logo_upload();
            if (empty($upload['ok'])) {
                $this->session->set_flashdata('error', (string)($upload['message'] ?? 'Logo usaha tidak dapat diunggah.'));
                redirect('system/business-profile');
                return;
            }
            $input = [
                'legal_name' => $this->input->post('legal_name', false),
                'display_name' => $this->input->post('display_name', false),
                'short_name' => $this->input->post('short_name', false),
                'tax_id' => $this->input->post('tax_id', false),
                'address' => $this->input->post('address', false),
                'phone' => $this->input->post('phone', false),
                'email' => $this->input->post('email', false),
                'website_url' => $this->input->post('website_url', false),
                'timezone' => $this->input->post('timezone', false),
                'locale' => $this->input->post('locale', false),
                'currency_code' => $this->input->post('currency_code', false),
                'document_footer' => $this->input->post('document_footer', false),
                'logo_url' => !empty($upload['logo_url'])
                    ? $upload['logo_url']
                    : ((int)$this->input->post('remove_logo', true) === 1 ? '' : (string)($current['logo_url'] ?? '')),
            ];
            // Older clients saving identity must not silently reset the public template.
            if ($this->input->post('menu_book_template', false) !== null) {
                $input['menu_book_template'] = $this->input->post('menu_book_template', false);
            }
            $result = $this->Business_profile_model->save($input, $this->actor_id(), (string)$this->input->ip_address());
            $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', !empty($result['ok'])
                ? 'Profil usaha tersimpan. Pengaturan outlet dan cetak yang sudah ada tetap menjadi override.'
                : (string)($result['message'] ?? 'Profil usaha gagal disimpan.'));
            redirect('system/business-profile');
            return;
        }
        $this->load->library('Upload_storage_policy');
        $this->render('system/business_profile', [
            'page_title' => 'Profil Usaha & Tampilan',
            'active_menu' => self::PAGE,
            'profile' => $this->Business_profile_model->profile(),
            'menu_book_template' => $this->Business_profile_model->menu_book_template(),
            'upload_storage' => Upload_storage_policy::inspect(FCPATH),
            'can_edit' => $this->can(self::PAGE, 'edit'),
            'profile_csrf' => $this->csrf_token(),
            'timezones' => ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'],
        ]);
    }

    private function require_schema(): void
    {
        if (!$this->Business_profile_model->ready()) {
            show_error('Migration Profil Usaha C2/C4 belum diterapkan.', 503, 'Profil Usaha Belum Siap');
        }
    }

    private function actor_id(): int { return (int)($this->current_user['id'] ?? 0); }

    private function csrf_token(): string
    {
        $token = (string)$this->session->userdata(self::CSRF_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::CSRF_KEY, $token);
        }
        return $token;
    }

    private function require_csrf(): bool
    {
        $provided = trim((string)$this->input->post('business_profile_csrf', false));
        if ($this->input->method(true) !== 'POST' || !hash_equals($this->csrf_token(), $provided)) {
            show_error('Sesi formulir Profil Usaha tidak valid. Muat ulang halaman lalu coba kembali.', 403, 'CSRF Ditolak');
            return false;
        }
        return true;
    }

    private function store_logo_upload(): array
    {
        $file = $_FILES['logo_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok' => true];
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'message' => 'Unggah logo gagal. Pilih kembali PNG atau JPG maksimal 1 MB.'];
        $path = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'assets/uploads/business-profile-logo';
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) return ['ok' => false, 'message' => 'Folder logo usaha belum dapat disiapkan. Hubungi administrator server.'];
        if (!is_writable($path)) return ['ok' => false, 'message' => 'Folder logo usaha tidak dapat ditulis. Hubungi administrator server.'];
        $config = ['upload_path'=>$path, 'allowed_types'=>'png|jpg|jpeg', 'max_size'=>1024, 'max_width'=>2048, 'max_height'=>2048, 'encrypt_name'=>true, 'file_ext_tolower'=>true, 'remove_spaces'=>true];
        $this->load->library('upload', $config, 'business_profile_logo_upload');
        if (!$this->business_profile_logo_upload->do_upload('logo_file')) return ['ok' => false, 'message' => 'Logo harus PNG/JPG valid, maksimal 1 MB dan 2048 × 2048 piksel.'];
        $upload = (array)$this->business_profile_logo_upload->data();
        $full = (string)($upload['full_path'] ?? '');
        $imageInfo = is_file($full) ? @getimagesize($full) : false;
        $type = is_array($imageInfo) ? (int)($imageInfo[2] ?? 0) : 0;
        if (!in_array($type, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            if ($full !== '' && is_file($full)) @unlink($full);
            return ['ok' => false, 'message' => 'Isi file bukan gambar PNG atau JPG yang valid.'];
        }
        $name = basename((string)($upload['file_name'] ?? ''));
        return preg_match('/^[a-f0-9]{32}\.(?:png|jpe?g)$/D', $name) === 1
            ? ['ok'=>true, 'logo_url'=>base_url('assets/uploads/business-profile-logo/' . rawurlencode($name))]
            : ['ok'=>false, 'message'=>'Nama file logo tidak valid.'];
    }
}
