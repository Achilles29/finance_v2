<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Gowes_voucher extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Gowes_voucher_model');
        $this->output->set_header('Cache-Control: private, no-store');
        $this->output->set_header('Referrer-Policy: no-referrer');
        $this->output->set_header('X-Content-Type-Options: nosniff');
        $this->output->set_header('X-Frame-Options: DENY');
        $this->output->set_header("Content-Security-Policy: default-src 'self'; img-src 'self' blob: data:; style-src 'self'; font-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }

    public function index(): void
    {
        $token = (string)$this->session->userdata('gowes_claim_csrf');
        if ($token === '') { $token = bin2hex(random_bytes(32)); $this->session->set_userdata('gowes_claim_csrf', $token); }
        $this->load->view('gowes/claim', ['claim_csrf' => $token]);
    }

    public function claim(): void
    {
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            $this->respond(['ok' => false, 'message' => 'Gunakan formulir klaim voucher.'], 405); return;
        }
        $this->load->library('Gowes_claim_guard');
        $attempt = $this->gowes_claim_guard->attempt((string)$this->input->ip_address(), session_id());
        if (!$attempt['ok']) {
            if (isset($attempt['retry_after'])) $this->output->set_header('Retry-After: ' . $attempt['retry_after']);
            $this->respond(['ok' => false, 'message' => $attempt['status'] === 429
                ? 'Terlalu banyak percobaan. Tunggu beberapa menit sebelum mencoba kembali.'
                : 'Layanan klaim sementara belum tersedia. Coba lagi nanti.'], $attempt['status']); return;
        }
        $token = $this->input->post('claim_csrf', false);
        $expected = (string)$this->session->userdata('gowes_claim_csrf');
        if (!is_string($token) || $expected === '' || !hash_equals($expected, $token)
            || $this->input->post('website', false) !== '' || (int)$this->input->server('CONTENT_LENGTH') > 4096) {
            $this->respond(['ok' => false, 'message' => 'Formulir tidak valid atau sesi berakhir. Muat ulang halaman lalu coba kembali.'], 403); return;
        }
        $email = Gowes_participant_import::email($this->input->post('email', false));
        if ($email === '') { $this->respond(['ok' => false, 'message' => 'Masukkan alamat email yang valid.'], 422); return; }
        $result = $this->Gowes_voucher_model->claim($email);
        $this->respond($result, !empty($result['ok']) ? 200 : (int)($result['status'] ?? 422));
    }

    private function respond(array $data, int $status): void
    {
        $this->output->set_status_header($status)->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
