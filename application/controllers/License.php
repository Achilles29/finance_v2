<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class License extends MY_Controller
{
    private const PAGE = 'system.license.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('License_runtime_model');
        $this->load->library('Feature_gate', [], 'feature_gate');
    }

    public function index()
    {
        $this->require_permission(self::PAGE, 'view');
        if (!$this->License_runtime_model->ready()) {
            show_error('Migration Lisensi C2/C4 belum diterapkan.', 503, 'Lisensi Belum Siap');
        }
        $catalog = $this->feature_gate->catalog();
        $features = [];
        foreach ($catalog as $code => $feature) {
            $decision = $this->feature_gate->decision($code, ['source'=>'license_status_page', 'route_path'=>'system/license']);
            $features[] = ['code'=>$code, 'name'=>(string)($feature['name'] ?? $code), 'category'=>(string)($feature['category'] ?? 'Lainnya'), 'decision'=>$decision];
        }
        $this->render('system/license_index', [
            'page_title' => 'Lisensi & Aktivasi',
            'can_manage_updates' => $this->is_superadmin(),
            'active_menu' => self::PAGE,
            'installation' => $this->License_runtime_model->installation(),
            'license' => $this->License_runtime_model->current_license(),
            'verification' => $this->License_runtime_model->verification(),
            'synchronization' => $this->License_runtime_model->synchronization(),
            'mode' => $this->feature_gate->mode(),
            'features' => $features,
        ]);
    }
}
