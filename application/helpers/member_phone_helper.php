<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('member_phone_normalize')) {
    /**
     * Convert common Indonesian mobile-number formats into one comparable value.
     */
    function member_phone_normalize($value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string)$value));
        if ($digits === '') {
            return '';
        }

        while (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        if (strpos($digits, '0') === 0) {
            $digits = '62' . substr($digits, 1);
        } elseif (strpos($digits, '8') === 0) {
            $digits = '62' . $digits;
        }

        return $digits;
    }
}

if (!function_exists('member_phone_display')) {
    function member_phone_display($value): string
    {
        $phone = member_phone_normalize($value);
        if (strpos($phone, '62') === 0) {
            return '0' . substr($phone, 2);
        }

        return $phone;
    }
}

if (!function_exists('member_name_normalize')) {
    function member_name_normalize($value): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string)$value));
        if ($name === '') {
            return '';
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($name, 'UTF-8')
            : strtolower($name);
    }
}

if (!function_exists('member_names_match')) {
    function member_names_match($left, $right): bool
    {
        $left = member_name_normalize($left);
        $right = member_name_normalize($right);
        return $left !== '' && $left === $right;
    }
}
