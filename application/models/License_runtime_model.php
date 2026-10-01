<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read-only runtime view of the signed local entitlement cache. */
class License_runtime_model extends CI_Model
{
    private $verifiedRuntime;
    private $fileRuntime;
    private const TABLES = [
        'lic_installation', 'lic_license_cache', 'lic_feature', 'lic_feature_cache',
        'lic_device_activation', 'lic_activation_audit', 'lic_runtime_audit',
    ];

    public function ready(): bool
    {
        foreach (self::TABLES as $table) if (!$this->db->table_exists($table)) return false;
        return true;
    }

    public function installation(): array
    {
        $file = $this->managed_file_runtime();
        if ($file !== null) return ['installation_id'=>$file['identity']['installation_id'] ?? '',
            'activation_status'=>$file['verification']['status'], 'instance_id'=>$file['identity']['instance_id'] ?? ''];
        if (!$this->ready()) return [];
        $row = $this->db->from('lic_installation')->where('id', 1)->limit(1)->get()->row_array();
        return is_array($row) ? $row : [];
    }

    public function current_license(): array
    {
        $file = $this->managed_file_runtime();
        if ($file !== null) {
            $v = $file['verification'];
            if (empty($v['verified'])) return [];
            $p = $v['payload'];
            return ['id'=>1,'verification_status'=>'VERIFIED','license_id'=>$p['license_id'],
                'edition_code'=>$p['edition'],'rights_model'=>$p['rights_model'],'not_before_at'=>$p['issued_at'],
                'expires_at'=>$p['expires_at'],'grace_until_at'=>$p['grace_until'],
                'maintenance_ends_at'=>$p['maintenance_ends_at'] ?? null,'source'=>'DEPLOYMENT_CACHE'];
        }
        if (!$this->ready()) return [];
        $row = $this->db->select('id, license_id, verification_status, edition_code, rights_model, not_before_at, expires_at, maintenance_ends_at, grace_until_at, verified_at, received_at')
            ->from('lic_license_cache')->where('is_current', 1)->order_by('id', 'DESC')->limit(1)->get()->row_array();
        return is_array($row) ? $row : [];
    }

    public function feature_cache(int $licenseCacheId): array
    {
        if (!$this->ready() || $licenseCacheId <= 0) return [];
        return $this->db->select('feature_code, access_mode, limit_json, depends_on_json')
            ->from('lic_feature_cache')->where('license_cache_id', $licenseCacheId)->order_by('feature_code', 'ASC')->get()->result_array();
    }

    public function feature_enabled(string $featureCode): bool
    {
        $license = $this->current_license();
        if (($license['verification_status'] ?? '') !== 'VERIFIED' || (int)($license['id'] ?? 0) <= 0) return false;
        $verified = $this->verification();
        // A mutable VERIFIED flag/feature_cache row is never sufficient authority.
        return !empty($verified['verified']) && in_array($verified['status'] ?? '', ['ACTIVE', 'GRACE'], true)
            && ($verified['payload']['entitlements'][$featureCode] ?? false) === true;
    }

    public function verification(): array
    {
        if (is_array($this->verifiedRuntime)) return $this->verifiedRuntime;
        $file = $this->managed_file_runtime();
        if ($file !== null) return $this->verifiedRuntime = $file['verification'];
        $this->load->library('Control_license_verifier');
        $trust = Control_license_verifier::deployment_document((string)getenv('FINANCE_LICENSE_TRUST_FILE'), FCPATH);
        $identity = Control_license_verifier::deployment_document((string)getenv('FINANCE_LICENSE_IDENTITY_FILE'), FCPATH);
        if (!$trust || !$identity) return $this->verifiedRuntime = ['verified' => false, 'status' => 'UNCONFIGURED', 'code' => 'DEPLOYMENT_TRUST_NOT_CONFIGURED'];
        if (!$this->ready()) return $this->verifiedRuntime = ['verified' => false, 'status' => 'UNCONFIGURED', 'code' => 'SCHEMA_NOT_READY'];
        $row = $this->db->from('lic_license_cache')->where('is_current', 1)->order_by('id', 'DESC')->limit(1)->get()->row_array();
        if (!is_array($row) || ($row['verification_status'] ?? '') !== 'VERIFIED') return $this->verifiedRuntime = ['verified' => false, 'status' => 'UNACTIVATED', 'code' => 'NO_VERIFIED_DOCUMENT'];
        $result = Control_license_verifier::verify(['schema' => 1, 'algorithm' => 'Ed25519', 'key_id' => $row['signing_key_id'] ?? '',
            'payload_base64' => base64_encode((string)($row['payload_json'] ?? '')), 'signature_base64' => $row['signature_b64'] ?? ''], $trust, $identity);
        if (!empty($result['verified']) && (!hash_equals((string)($row['payload_sha256'] ?? ''), $result['payload_sha256'])
            || !hash_equals((string)($row['license_id'] ?? ''), $result['payload']['license_id']))) {
            $result = ['verified' => false, 'status' => 'RESTRICTED', 'code' => 'CACHE_METADATA_MISMATCH'];
        }
        return $this->verifiedRuntime = $result;
    }

    public function synchronization(): array
    {
        $file = $this->managed_file_runtime();
        return $file === null ? ['configured'=>false] : ['configured'=>true,
            'connection'=>$file['cache']['connection'] ?? 'UNAVAILABLE',
            'synced_at'=>$file['cache']['synced_at'] ?? 0,'last_seen_at'=>$file['cache']['last_seen_at'] ?? 0];
    }

    /** Explicit managed cache never falls back to a mutable SQL cache on failure. */
    private function managed_file_runtime(): ?array
    {
        $path = (string)getenv('FINANCE_LICENSE_CACHE_FILE');
        $context=(string)getenv('FINANCE_CUSTOMER_INSTALLATION_FILE');
        require_once dirname(__DIR__).'/libraries/DeploymentConfig.php';
        try {
            $e=(new DeploymentConfig())->customerEnvironment(FCPATH, ['FINANCE_CUSTOMER_INSTALLATION_FILE'=>$context]);
            $context=$e['FINANCE_CUSTOMER_INSTALLATION_FILE'] ?? '';
        } catch (Throwable $error) {
            return $this->fileRuntime=['verification'=>['verified'=>false,'status'=>'RESTRICTED',
                'code'=>'CUSTOMER_CONFIGURATION_UNAVAILABLE'],'cache'=>[],'identity'=>[]];
        }
        if ($path === '' && $context === '') return null;
        if (is_array($this->fileRuntime)) return $this->fileRuntime;
        $this->load->library('Control_license_cache');
        if ($context!=='') {
            $v=Control_license_cache::customer_verification(FCPATH,$context);
            try {
                $c=Control_license_cache::customer_context(FCPATH,$context);
                $identity=Control_license_verifier::deployment_document($c['license_identity_file'],FCPATH);
                $cache=Control_license_verifier::deployment_document($c['license_cache_file'],FCPATH,300000);
            } catch (Throwable $error) {
                $identity=[];$cache=[];
            }
            return $this->fileRuntime=['verification'=>$v,'cache'=>$cache,'identity'=>$identity];
        }
        $trust = Control_license_verifier::deployment_document((string)getenv('FINANCE_LICENSE_TRUST_FILE'), FCPATH);
        $identity = Control_license_verifier::deployment_document((string)getenv('FINANCE_LICENSE_IDENTITY_FILE'), FCPATH);
        $cache = Control_license_verifier::deployment_document($path, FCPATH, 300000);
        $v = (!$trust || !$identity || !$cache) ? ['verified'=>false,'status'=>'UNCONFIGURED','code'=>'MANAGED_CACHE_UNAVAILABLE']
            : Control_license_cache::verification($cache, $trust, $identity);
        return $this->fileRuntime = ['verification'=>$v,'cache'=>$cache,'identity'=>$identity];
    }

    /** Records only a hash of declared context; never capture request payload, token, or user data. */
    public function record_decision(?string $featureCode, string $routePath, string $mode, string $decisionCode, array $context = []): void
    {
        if (!$this->ready()) return;
        try {
            $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->db->insert('lic_runtime_audit', [
                'feature_code' => $featureCode !== '' ? mb_substr((string)$featureCode, 0, 100) : null,
                'route_path' => $routePath !== '' ? mb_substr($routePath, 0, 255) : null,
                'decision_mode' => $mode === 'ENFORCE' ? 'ENFORCE' : 'AUDIT_ONLY',
                'decision_code' => mb_substr($decisionCode, 0, 60),
                'context_sha256' => is_string($contextJson) ? hash('sha256', $contextJson) : null,
            ]);
        } catch (Throwable $error) {
            log_message('error', 'License runtime audit could not be recorded.');
        }
    }
}
