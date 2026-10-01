<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CI facade for feature decisions. Verified customer packages always enforce;
 * the historical opt-in audit behavior is retained only for non-customer installations.
 */
class Feature_gate
{
    private $ci;
    private $catalog;

    public function __construct()
    {
        $this->ci = get_instance();
        $this->ci->load->model('License_runtime_model');
    }

    public function mode(): string
    {
        require_once __DIR__.'/Feature_policy.php';
        if (Feature_policy::runtime()->managed()) return 'ENFORCE';
        // A SQL setting alone must not switch a running customer into enforcement.
        if (getenv('FINANCE_LICENSE_ENFORCEMENT_APPROVED') !== '1') return 'AUDIT_ONLY';
        if (!$this->ci->db->table_exists('sys_app_config')) return 'AUDIT_ONLY';
        try {
            $row = $this->ci->db->select('config_value')->from('sys_app_config')
                ->where('config_key', 'license.feature_gate_mode')->limit(1)->get()->row_array();
            return strtoupper((string)($row['config_value'] ?? '')) === 'ENFORCE' ? 'ENFORCE' : 'AUDIT_ONLY';
        } catch (Throwable $error) {
            return 'AUDIT_ONLY';
        }
    }

    public function catalog(): array
    {
        if (is_array($this->catalog)) return $this->catalog;
        $this->catalog = [];
        $path = dirname(APPPATH) . DIRECTORY_SEPARATOR . 'app-manifest.json';
        try {
            $manifest = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            foreach ((array)($manifest['features'] ?? []) as $feature) {
                if (!is_array($feature) || !preg_match('/\A[A-Z][A-Z0-9_]{1,99}\z/D', (string)($feature['code'] ?? ''))) continue;
                $this->catalog[(string)$feature['code']] = $feature;
            }
        } catch (Throwable $error) {
            log_message('error', 'Feature catalog manifest could not be read.');
        }
        return $this->catalog;
    }

    public function decision(string $featureCode, array $context = []): array
    {
        $featureCode = strtoupper(trim($featureCode));
        require_once __DIR__.'/Feature_policy.php';
        if (Feature_policy::runtime()->managed()) return Feature_policy::runtime()->feature($featureCode);
        $mode = $this->mode();
        $known = isset($this->catalog()[$featureCode]);
        $route = trim((string)($context['route_path'] ?? uri_string()));
        $safeContext = ['source'=>(string)($context['source'] ?? 'runtime'), 'route'=>$route];
        if ($mode !== 'ENFORCE') {
            $code = $known ? 'AUDIT_ALLOW' : 'AUDIT_UNKNOWN_FEATURE';
            $this->ci->License_runtime_model->record_decision($featureCode, $route, 'AUDIT_ONLY', $code, $safeContext);
            return ['allowed'=>true, 'enforced'=>false, 'mode'=>'AUDIT_ONLY', 'code'=>$code, 'feature_code'=>$featureCode];
        }
        $allowed = $known && $this->ci->License_runtime_model->feature_enabled($featureCode);
        $code = $allowed ? 'ENTITLED' : ($known ? 'NOT_ENTITLED' : 'UNKNOWN_FEATURE');
        $this->ci->License_runtime_model->record_decision($featureCode, $route, 'ENFORCE', $code, $safeContext);
        return ['allowed'=>$allowed, 'enforced'=>true, 'mode'=>'ENFORCE', 'code'=>$code, 'feature_code'=>$featureCode];
    }
}
