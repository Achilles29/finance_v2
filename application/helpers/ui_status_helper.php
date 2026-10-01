<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('ui_status_badge')) {
    /**
     * Render badge status standar UI.
     *
     * @param string|int|null $status
     * @param string $mode active|doc
     */
    function ui_status_badge($status, string $mode = 'doc'): string
    {
        $raw = strtoupper(trim((string)$status));
        $label = $raw;
        $class = 'fin-status-badge fin-status-neutral';

        if ($mode === 'active') {
            $isActive = in_array($raw, ['1', 'ACTIVE', 'AKTIF', 'TRUE', 'YES'], true);
            $label = $isActive ? 'AKTIF' : 'NONAKTIF';
            $class = $isActive ? 'fin-status-badge fin-status-active' : 'fin-status-badge fin-status-inactive';
            return '<span class="' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        switch ($raw) {
            case 'DRAFT':
                $class = 'fin-status-badge fin-status-draft';
                break;
            case 'POSTED':
            case 'APPROVED':
            case 'DONE':
                $class = 'fin-status-badge fin-status-posted';
                break;
            case 'VOID':
            case 'CANCEL':
            case 'CANCELLED':
            case 'REJECTED':
                $class = 'fin-status-badge fin-status-void';
                break;
            case 'ACTIVE':
            case 'AKTIF':
                $class = 'fin-status-badge fin-status-active';
                $label = 'AKTIF';
                break;
            case 'INACTIVE':
            case 'NONAKTIF':
                $class = 'fin-status-badge fin-status-inactive';
                $label = 'NONAKTIF';
                break;
            default:
                if ($label === '') {
                    $label = '-';
                }
                break;
        }

        return '<span class="' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

