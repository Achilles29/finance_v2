<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Bounded scalar input, shared by both public submission paths. */
class CustomerReviewInput
{
    public static function validate(array $input, bool $station): array
    {
        $limits = ['rating' => 1, 'review_text' => 1200, '_review_guard' => 120, 'website' => 200];
        if ($station) $limits += ['customer_name' => 150, 'mobile_phone' => 30, 'join_member' => 1];
        $clean = [];
        foreach ($limits as $field => $limit) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit
                || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)
            ) return ['ok' => false, 'message' => 'Isian tidak valid atau terlalu panjang. Periksa kembali formulir Anda.'];
            $clean[$field] = $field === 'website' ? $value : trim($value);
        }
        if (!preg_match('/^[1-5]$/D', $clean['rating'])) return ['ok' => false, 'message' => 'Pilih bintang dari 1 sampai 5 terlebih dahulu.'];
        if ($station) {
            if ($clean['customer_name'] === '' || !preg_match('/^[+0-9() .-]{9,30}$/D', $clean['mobile_phone'])
                || $clean['join_member'] !== '1'
            ) return ['ok' => false, 'message' => 'Periksa nama, nomor WhatsApp, dan pilihan persetujuan Anda.'];
            $phone = preg_replace('/\D+/', '', $clean['mobile_phone']);
            if (strpos($phone, '62') === 0) $phone = '0' . substr($phone, 2);
            elseif (strpos($phone, '8') === 0) $phone = '0' . $phone;
            if (!preg_match('/^[0-9]{9,16}$/D', $phone)) return ['ok' => false, 'message' => 'Nomor WhatsApp belum valid. Periksa kembali nomor yang dimasukkan.'];
            $clean['mobile_phone'] = $phone;
        }
        return ['ok' => true, 'input' => $clean];
    }
}
