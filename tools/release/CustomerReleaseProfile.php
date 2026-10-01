<?php
declare(strict_types=1);

require_once __DIR__ . '/ReleasePackagePolicy.php';
require_once __DIR__ . '/CustomerLayout.php';

/** Build-time allowlist. Never reads an application database or executes archive code. */
final class CustomerReleaseProfile
{
    public const PATH = 'tools/release/customer_clean_profile.json';
    public const ID = 'CUSTOMER_CLEAN';
    private array $profile;
    private string $digest;

    public function __construct(string $raw)
    {
        $p = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($p) || ($p['schema'] ?? '') !== 'finance.customer-clean-profile'
            || ($p['schema_version'] ?? null) !== 1 || ($p['profile'] ?? '') !== self::ID
            || !in_array($p['profile_version'] ?? null, [1,2,3,4,5,6,7,8,9,10,11], true) || ($p['seed_profile'] ?? '') !== 'REFERENCE_ONLY'
            || ($p['demo_data'] ?? null) !== false) throw new RuntimeException('CUSTOMER_PROFILE_INVALID');
        foreach (['code_files', 'files', 'static_sha256', 'sql_sha256'] as $field) {
            if (!isset($p[$field]) || !is_array($p[$field]) || $p[$field] === []) throw new RuntimeException('CUSTOMER_PROFILE_INVALID');
            foreach ($p[$field] as $key => $value) {
                $path = in_array($field, ['static_sha256', 'sql_sha256'], true) ? $key : $value;
                if (!is_string($path) || !ReleasePackagePolicy::relativePathValid($path)) throw new RuntimeException('CUSTOMER_PROFILE_PATH');
                if (in_array($field, ['static_sha256', 'sql_sha256'], true)
                    && (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1)) throw new RuntimeException('CUSTOMER_PROFILE_DIGEST');
            }
        }
        if ($p['profile_version']>=5 && ($p['local_configuration']??null)!==[
            'contract'=>'FINANCE_CUSTOMER_LOCAL_V1','mutable_path'=>'config/customer.json',
            'template_path'=>'config/customer.example.json','included_in_artifact'=>false]) {
            throw new RuntimeException('CUSTOMER_LOCAL_CONTRACT_INVALID');
        }
        $this->profile = $p;
        if ($p['profile_version']>=6 && ($p['layout_contract']??'')!=='FINANCE_SINGLE_FOLDER_V1') throw new RuntimeException('CUSTOMER_LAYOUT_INVALID');
        if ($p['profile_version']>=7 && ($p['setup_contract']??'')!=='FINANCE_GUIDED_SETUP_V1') throw new RuntimeException('CUSTOMER_SETUP_CONTRACT_INVALID');
        if ($p['profile_version']>=8 && ($p['installation_permission_policy']??'')!=='UNTIL_USED_OR_REVOKED') throw new RuntimeException('CUSTOMER_SETUP_CONTRACT_INVALID');
        if ($p['profile_version']>=9 && ($p['feature_boundary_contract']??'')!=='FINANCE_FEATURE_BOUNDARY_V1') throw new RuntimeException('CUSTOMER_FEATURE_CONTRACT_INVALID');
        if ($p['profile_version']>=10 && ($p['managed_migration_contract']??'')!=='FINANCE_MANAGED_ADOPTION_V1') throw new RuntimeException('CUSTOMER_MIGRATION_CONTRACT_INVALID');
        if ($p['profile_version']>=11 && ($p['application_update_contract']??'')!=='FINANCE_APPLICATION_UPDATE_V1') throw new RuntimeException('CUSTOMER_UPDATE_CONTRACT_INVALID');
        $this->digest = hash('sha256', $raw);
    }

    public static function fromRoot(string $root): self
    {
        $path = $root . '/' . self::PATH;
        if (ReleasePackagePolicy::absoluteFileProblem($path) !== null) throw new RuntimeException('CUSTOMER_PROFILE_MISSING');
        return new self((string)file_get_contents($path));
    }

    public function digest(): string { return $this->digest; }
    public function version(): int { return $this->profile['profile_version']; }

    public function allows(string $path): bool
    {
        if ($path==='config/customer.json') return false;
        if (!ReleasePackagePolicy::relativePathValid($path)) return false;
        // Legacy artwork contains real product names, prices and photographs, including inline HTML.
        if (str_starts_with($path, 'application/views/menu_book/') && $path !== 'application/views/menu_book/customer.php') return false;
        if (isset($this->profile['static_sha256'][$path]) || isset($this->profile['sql_sha256'][$path])
            || in_array($path, $this->profile['files'], true)) return true;
        return in_array($path, $this->profile['code_files'], true);
    }

    public function filter(array $entries): array
    {
        return array_filter($entries, fn(array $e): bool => $this->allows($e['path']));
    }

    /** Exact immutable static/SQL inventory; unknown files or changed content fail closed. */
    public function audit(array $entries): array
    {
        $seen = [];
        $base = ReleasePackagePolicy::fromFile(__DIR__ . '/package_policy.json');
        foreach ($entries as $entry) {
            $path = $entry['path'] ?? '';
            if ($this->version()>=6 && is_string($path)) $path=CustomerLayout::source($path);
            if (!is_string($path) || isset($seen[$path]) || !$base->included($path) || $base->denied($path)
                || !$this->allows($path)) throw new RuntimeException('CUSTOMER_CONTENT_FORBIDDEN_PATH');
            $seen[$path] = $entry['sha256'] ?? '';
        }
        foreach (array_merge($this->profile['static_sha256'], $this->profile['sql_sha256'], [self::PATH => $this->digest]) as $path => $hash) {
            if (($seen[$path] ?? null) !== $hash) throw new RuntimeException('CUSTOMER_CONTENT_CHECKSUM');
        }
        foreach (array_merge($this->profile['files'], $this->profile['code_files']) as $path) {
            if (!isset($seen[$path])) throw new RuntimeException('CUSTOMER_CONTENT_REQUIRED_FILE');
        }
        return ['schema' => 'finance.customer-content-audit', 'schema_version' => 1, 'status' => 'PASS',
            'profile_sha256' => $this->digest, 'files_checked' => count($seen),
            'static_files_checked' => count($this->profile['static_sha256']),
            'sql_files_checked' => count($this->profile['sql_sha256']),
            'demo_data' => false, 'source_database_accessed' => false];
    }

    /** Catalog history can stay as metadata; unbundled legacy SQL must never become runnable. */
    public static function installed(string $root): bool
    {
        $path = $root . '/RELEASE-MANIFEST.json';
        if (!is_file($path) || is_link($path)) return false;
        $manifest = json_decode((string)file_get_contents($path), true);
        foreach ($manifest['files'] ?? [] as $entry) {
            if (($entry['path'] ?? '') === self::PATH) {
                return is_file($root . '/' . self::PATH) && !is_link($root . '/' . self::PATH)
                    && ($entry['sha256'] ?? '') === hash_file('sha256', $root . '/' . self::PATH);
            }
        }
        return false;
    }
}
