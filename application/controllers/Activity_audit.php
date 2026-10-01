<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity_audit extends MY_Controller
{
    private const PAGE_CODE = 'system.activity_audit.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Activity_audit_model');
    }

    public function index()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $filters = $this->filters();
        $perPage = 50;
        $page = max(1, (int)$this->input->get('page', true));
        $total = $this->Activity_audit_model->count_events($filters);
        $pageCount = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pageCount);

        $this->render('system/activity_audit_index', [
            'title' => 'Log Aktivitas',
            'active_menu' => self::PAGE_CODE,
            'filters' => $filters,
            'users' => $this->Activity_audit_model->list_users(),
            'summary' => $this->Activity_audit_model->summary($filters),
            'rows' => $this->Activity_audit_model->list_events($filters, $perPage, ($page - 1) * $perPage),
            'total' => $total,
            'page' => $page,
            'page_count' => $pageCount,
        ]);
    }

    private function filters(): array
    {
        $today = date('Y-m-d');
        $from = trim((string)$this->input->get('from', true));
        $to = trim((string)$this->input->get('to', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d', strtotime('-6 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $today;
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        // Batas agar layar audit tidak berubah menjadi query log tanpa batas.
        if (strtotime($to) - strtotime($from) > 30 * 86400) {
            $from = date('Y-m-d', strtotime($to . ' -30 days'));
        }

        $kind = strtoupper(trim((string)$this->input->get('kind', true)));
        if (!in_array($kind, ['PAGE_VIEW', 'LOGIN', 'TRANSACTION'], true)) {
            $kind = '';
        }

        return [
            'from' => $from,
            'to' => $to,
            'from_at' => $from . ' 00:00:00',
            'until_at' => date('Y-m-d 00:00:00', strtotime($to . ' +1 day')),
            'user_id' => max(0, (int)$this->input->get('user_id', true)),
            'kind' => $kind,
            'q' => mb_substr(trim((string)$this->input->get('q', true)), 0, 100),
        ];
    }
}
