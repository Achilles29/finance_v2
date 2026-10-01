<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Public, token-only endpoint reached from the QR on a receipt. */
class Customer_reviews extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_customer_review_model');
        $this->load->model('Business_profile_model');
        $this->load->library(['CustomerReviewGuard', 'CustomerReviewInput']);
        $this->output->set_header('Cache-Control: private, no-store');
        $this->output->set_header('Referrer-Policy: no-referrer');
        $this->output->set_header('X-Robots-Tag: noindex, nofollow');
        $this->output->set_header('X-Content-Type-Options: nosniff');
        $this->output->set_header('X-Frame-Options: DENY');
    }

    public function index($token = '')
    {
        $this->show_page((string)$token);
    }

    public function submit($token = '')
    {
        if (strtoupper((string)$this->input->method()) !== 'POST') {
            redirect('review/' . rawurlencode((string)$token));
            return;
        }
        $result = $this->guarded_submission((string)$token, false);
        $this->show_page((string)$token, $result);
    }

    /** Public QR for a permanent outlet area, such as a table tent or exit. */
    public function station($code = '')
    {
        $this->show_station_page((string)$code);
    }

    public function station_submit($code = '')
    {
        if (strtoupper((string)$this->input->method()) !== 'POST') {
            redirect('review/station/' . rawurlencode((string)$code));
            return;
        }
        $result = $this->guarded_submission((string)$code, true);
        $this->show_station_page((string)$code, $result);
    }

    private function guarded_submission(string $target, bool $station): array
    {
        $ip = (string)$this->input->ip_address(); // CI honors only explicitly trusted proxies.
        $device = session_id();
        $attempt = $this->customerreviewguard->attempt($ip, $device);
        if (!$attempt['ok']) return $this->rejected($attempt);
        if ((int)$this->input->server('CONTENT_LENGTH') > 16384) {
            return $this->rejected(['ok' => false, 'status' => 413, 'message' => 'Isian terlalu besar. Batasi ulasan sampai 1.200 karakter.']);
        }
        $raw = $this->input->post(null, false);
        $checked = CustomerReviewInput::validate(is_array($raw) ? $raw : [], $station);
        if (!$checked['ok']) return $this->rejected($checked + ['status' => 422]);
        $input = $checked['input'];
        $identity = $station ? 'phone:' . $input['mobile_phone'] : 'receipt:' . strtolower($target);
        $grant = $this->customerreviewguard->authorize(
            $this->guard_target($target, $station), $device, $ip, $input, $identity,
            (string)json_encode([$input['rating'], mb_strtolower(preg_replace('/\s+/u', ' ', $input['review_text']))])
        );
        if (!$grant['ok']) return $this->rejected($grant);
        try {
            $result = $station
                ? $this->Pos_customer_review_model->submit_station_review($target, $input)
                : $this->Pos_customer_review_model->submit($target, (int)$input['rating'], $input['review_text']);
        } catch (Throwable $error) {
            $result = ['ok' => false, 'message' => 'Ulasan belum dapat disimpan. Silakan coba kembali nanti.'];
        }
        $this->customerreviewguard->outcome($ip, $grant['reservation'], $result);
        return !empty($result['ok']) ? $result : $this->rejected($result + ['status' => 422]);
    }

    private function rejected(array $result): array
    {
        $this->output->set_status_header((int)($result['status'] ?? 422));
        if (!empty($result['retry_after'])) $this->output->set_header('Retry-After: ' . max(1, (int)$result['retry_after']));
        return $result;
    }

    private function guard_target(string $target, bool $station): string
    {
        return $station ? 'station:' . strtoupper(trim($target)) : 'receipt:' . strtolower($target);
    }

    private function show_page(string $token, ?array $result = null): void
    {
        $review = $this->Pos_customer_review_model->find_by_token($token);
        $guard = $this->customerreviewguard->issue($this->guard_target($token, false), session_id());
        if (!$guard['ok'] && $result === null) $result = $this->rejected($guard);
        $this->load->view('pos/customer_review_form', [
            'review' => $review,
            'token' => $token,
            'result' => $result,
            'form_guard' => (string)($guard['token'] ?? ''),
            'business_profile' => $this->business_profile(),
        ]);
    }

    private function show_station_page(string $code, ?array $result = null): void
    {
        $station = $this->Pos_customer_review_model->find_station_by_code($code);
        $guard = $this->customerreviewguard->issue($this->guard_target($code, true), session_id());
        if (!$guard['ok'] && $result === null) $result = $this->rejected($guard);
        $this->load->view('pos/customer_review_station_form', [
            'station' => $station,
            'station_code' => $code,
            'result' => $result,
            'form_guard' => (string)($guard['token'] ?? ''),
            'business_profile' => $this->business_profile(),
        ]);
    }

    /** Public forms may use only display data; schema failure stays neutral. */
    private function business_profile(): array
    {
        try {
            $profile = $this->Business_profile_model->profile();
            return is_array($profile) ? $profile : [];
        } catch (Throwable $error) {
            return [];
        }
    }
}
