<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Audit extends MY_Controller
{
    private const ENABLE_MARKER = '.codex/internal_audit_dashboard.enabled';
    private const ENABLE_TOKEN = "enabled-v1\n";

    public function __construct()
    {
        if (!self::internalDashboardEnabled()) {
            show_404();
            exit;
        }
        parent::__construct();
    }

    public function roadmap(): void
    {
        $this->setReadOnlyHeaders();

        if ($this->input->method(true) !== 'GET') {
            $this->output->set_header('Allow: GET');
            show_error('Metode request tidak diizinkan.', 405, 'Method Not Allowed');
            return;
        }

        if (!$this->is_superadmin()) {
            show_error('Anda tidak memiliki izin untuk mengakses halaman ini.', 403, 'Akses Ditolak');
            return;
        }

        $this->load->library('AuditRoadmapReader');
        $roadmap = $this->auditroadmapreader->read();

        $this->render('audit/roadmap', [
            'title' => 'Roadmap Audit Internal',
            'active_menu' => 'grp.system',
            'roadmap' => $roadmap,
        ]);
    }

    private function setReadOnlyHeaders(): void
    {
        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('X-Content-Type-Options: nosniff');
    }

    private static function internalDashboardEnabled(): bool
    {
        if (!defined('ENVIRONMENT') || !defined('FCPATH')) {
            return false;
        }

        return self::internalDashboardPolicyAllows(
            ENVIRONMENT,
            FCPATH . str_replace('/', DIRECTORY_SEPARATOR, self::ENABLE_MARKER)
        );
    }

    /** Kept separate so the fail-closed file policy can be regression-tested. */
    private static function internalDashboardPolicyAllows(string $environment, string $marker): bool
    {
        if ($environment !== 'development'
            || is_link($marker)
            || !is_file($marker)
            || !is_readable($marker)
        ) {
            return false;
        }

        $contents = @file_get_contents($marker);
        return is_string($contents) && hash_equals(self::ENABLE_TOKEN, $contents);
    }
}
