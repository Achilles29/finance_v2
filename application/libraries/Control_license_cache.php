<?php
declare(strict_types=1);
require_once __DIR__ . '/Control_license_verifier.php';

/** Pure cache transitions; the agent owns storage, the web process only reads it. */
final class Control_license_cache
{
    /** Small immutable bootstrap set; uploads and business controllers are not runtime-rehashed. */
    public static function customer_core_files(int $profileVersion = 5): array
    {
        $files = ['index.php', 'application/libraries/Control_license_cache.php',
            'application/libraries/Control_license_verifier.php', 'application/libraries/DeploymentConfig.php',
            'application/config/config.php', 'application/config/routes.php',
            'system/core/CodeIgniter.php', 'system/core/URI.php', 'system/core/Router.php',
            'tools/release/customer_clean_profile.json'];
        if ($profileVersion >= 5) $files = array_merge($files, ['application/libraries/CustomerLocalConfig.php',
            'application/config/database.php', 'config/.htaccess', '.htaccess']);
        if ($profileVersion >= 6) $files = array_merge($files, ['application/libraries/CustomerPlatform.php',
            'public/index.php','public/.htaccess','public/web.config','installer/layout.json',
            'tools/install/portable/windows-inspect.ps1']);
        if ($profileVersion >= 7) $files = array_merge($files, ['tools/install/portable/SetupUi.php',
            'tools/install/portable/SetupService.php','tools/install/portable/PortableInstaller.php',
            'tools/install/portable/PortablePackage.php','tools/install/portable/LinuxPreparation.php',
            'tools/install/portable/prepare.php','tools/install/portable/prepare.sh',
            'tools/install/portable/setup.php','tools/install/portable/setup.js','tools/install/portable/setup.css']);
        if ($profileVersion >= 9) $files = array_merge($files, [
            'app-manifest.json', 'application/core/MY_Hooks.php', 'application/core/MY_Router.php', 'application/core/MY_Controller.php',
            'system/core/Hooks.php', 'system/core/Common.php', 'system/core/Controller.php',
            'application/config/feature_access.php', 'application/libraries/Feature_policy.php',
            'application/libraries/Feature_gate.php', 'application/controllers/Feature_access.php',
            'application/controllers/Auth.php', 'application/controllers/Dashboard.php', 'application/controllers/Master.php',
            'application/models/Pos_model.php', 'application/views/layout/sidebar.php',
            'application/views/system/feature_home.php', 'application/views/system/feature_locked.php',
            'application/views/system/feature_upgrade.php', 'application/views/master/detail_basic.php']);
        if ($profileVersion >= 10) $files = array_merge($files, ['tools/db/ManagedMigrationProof.php',
            'tools/db/managed_migration_proofs.json', 'tools/db/migration_catalog.json',
            'tools/db/migration_runner.php', 'tools/install/portable/PortableDatabase.php']);
        if ($profileVersion >= 11) $files = array_merge($files, ['application/libraries/Customer_update_guard.php',
            'tools/update/UpdateAuthorization.php','tools/update/UpdateTransport.php','tools/update/UpdateFiles.php',
            'tools/update/UpdateDatabase.php','tools/update/UpdateService.php']);
        return $files;
    }

    private static function customer_source_file(string $root, string $relative): string
    {
        $path = $root . '/' . $relative;
        if (CustomerPlatform::portable($root)) {
            CustomerPlatform::path($root,$path);
            if (!is_file($path)) throw new RuntimeException('CUSTOMER_CORE_MISSING');
            return $path;
        }
        if (realpath($path) !== $path || !is_file($path) || is_link($path) || !is_readable($path)) {
            throw new RuntimeException('CUSTOMER_CORE_MISSING');
        }
        for ($part = $path; ; $part = dirname($part)) {
            $stat = stat($part);
            if (!is_array($stat) || (int)$stat['uid'] !== 0 || ($stat['mode'] & 0022) !== 0) {
                throw new RuntimeException('CUSTOMER_CORE_UNSAFE');
            }
            if ($part === dirname($part)) break;
        }
        return $path;
    }

    /** Root-owned per-release context. Loading it does not require an activated lease. */
    public static function customer_context(string $webroot, string $contextFile): array
    {
        require_once __DIR__.'/CustomerPlatform.php';
        $root = realpath(CustomerPlatform::root($webroot));
        if ($root!==false) $root=str_replace('\\','/',$root);
        if ($root === false || (PHP_OS_FAMILY !== 'Linux' && !CustomerPlatform::portable($root))) throw new RuntimeException('CUSTOMER_PLATFORM_UNSUPPORTED');
        $c = Control_license_verifier::deployment_document($contextFile, $root, 300000);
        if (($c['schema'] ?? null) !== 1 || ($c['purpose'] ?? '') !== 'FINANCE_CUSTOMER_INSTALLATION'
            || ($c['product_code'] ?? '') !== 'NAMUA_FINANCE' || ($c['release_root'] ?? '') !== $root
            || ($c['customer_runtime_guard'] ?? '') !== 'FINANCE_CUSTOMER_SERVER_V1'
            || ($c['distribution_profile'] ?? '') !== 'CUSTOMER_CLEAN'
            || !is_int($c['distribution_profile_version'] ?? null) || $c['distribution_profile_version'] < 1
            || !is_array($c['identity'] ?? null) || !is_array($c['core_sha256'] ?? null)
            || !is_string($c['version'] ?? null) || preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/D', $c['version']) !== 1
            || !is_string($c['source_commit'] ?? null) || preg_match('/\A[a-f0-9]{40}\z/D', $c['source_commit']) !== 1) {
            throw new RuntimeException('CUSTOMER_CONTEXT_INVALID');
        }
        if (!is_string($c['release_public_id'] ?? null)
            || preg_match('/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/D', $c['release_public_id']) !== 1) {
            throw new RuntimeException('CUSTOMER_CONTEXT_INVALID');
        }
        foreach (['artifact_sha256', 'release_manifest_sha256', 'app_manifest_sha256',
            'source_manifest_sha256', 'profile_sha256', 'machine_fingerprint_sha256'] as $key) {
            if (!is_string($c[$key] ?? null) || preg_match('/\A[a-f0-9]{64}\z/D', $c[$key]) !== 1) {
                throw new RuntimeException('CUSTOMER_CONTEXT_INVALID');
            }
        }
        foreach (['instance_id', 'installation_id', 'instance_public_key_sha256'] as $key) {
            if (!is_string($c['identity'][$key] ?? null) || $c['identity'][$key] === '') {
                throw new RuntimeException('CUSTOMER_CONTEXT_INVALID');
            }
        }
        if (count($c['identity']) !== 3 || preg_match('/\A[a-f0-9]{64}\z/D', $c['identity']['instance_public_key_sha256']) !== 1) {
            throw new RuntimeException('CUSTOMER_CONTEXT_INVALID');
        }
        foreach (['license_trust_file', 'license_identity_file', 'license_cache_file'] as $key) {
            if (!is_string($c[$key] ?? null) || $c[$key] === '') throw new RuntimeException('CUSTOMER_CONTEXT_INVALID');
        }
        self::customer_release_proof($c);
        $appPath = self::customer_source_file($root, 'app-manifest.json');
        $innerPath = self::customer_source_file($root, 'RELEASE-MANIFEST.json');
        if (!hash_equals($c['app_manifest_sha256'], (string)hash_file('sha256', $appPath))
            || !hash_equals($c['source_manifest_sha256'], (string)hash_file('sha256', $innerPath))) {
            throw new RuntimeException('CUSTOMER_SOURCE_MISMATCH');
        }
        $app = json_decode((string)file_get_contents($appPath), true, 32, JSON_THROW_ON_ERROR);
        $inner = json_decode((string)file_get_contents($innerPath), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($app) || ($app['product_code'] ?? '') !== $c['product_code'] || ($app['version'] ?? '') !== $c['version']
            || !is_array($inner) || !is_array($inner['files'] ?? null)) throw new RuntimeException('CUSTOMER_SOURCE_MISMATCH');
        $inventory = [];
        foreach ($inner['files'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null) || isset($inventory[$entry['path']])) {
                throw new RuntimeException('CUSTOMER_SOURCE_MISMATCH');
            }
            $inventory[$entry['path']] = $entry['sha256'] ?? null;
        }
        foreach (self::customer_core_files($c['distribution_profile_version']) as $relative) {
            $expected = $c['core_sha256'][$relative] ?? null;
            if (!is_string($expected) || preg_match('/\A[a-f0-9]{64}\z/D', $expected) !== 1
                || ($inventory[$relative] ?? null) !== $expected
                || !hash_equals($expected, (string)hash_file('sha256', self::customer_source_file($root, $relative)))) {
                throw new RuntimeException('CUSTOMER_CORE_MISMATCH');
            }
        }
        $profilePath = self::customer_source_file($root, 'tools/release/customer_clean_profile.json');
        $profile = json_decode((string)file_get_contents($profilePath), true, 32, JSON_THROW_ON_ERROR);
        if (!hash_equals($c['profile_sha256'], (string)hash_file('sha256', $profilePath)) || !is_array($profile)
            || ($profile['profile'] ?? '') !== 'CUSTOMER_CLEAN'
            || ($profile['profile_version'] ?? null) !== $c['distribution_profile_version']) {
            throw new RuntimeException('CUSTOMER_PROFILE_MISMATCH');
        }
        return $c;
    }

    /** Re-authenticate original signed Control bytes; root context never substitutes a signature. */
    public static function customer_release_proof(array $c): void
    {
        $encoded = $c['release_manifest_base64'] ?? null;
        $raw = is_string($encoded) ? base64_decode($encoded, true) : false;
        $signature = $c['release_signature'] ?? null; $trust = $c['release_trust'] ?? null;
        if (!is_string($raw) || $raw === '' || strlen($raw) > 100000 || base64_encode($raw) !== $encoded
            || !hash_equals($c['release_manifest_sha256'], hash('sha256', $raw)) || !is_array($signature) || !is_array($trust)) {
            throw new RuntimeException('CUSTOMER_RELEASE_PROOF_INVALID');
        }
        $public = base64_decode((string)($trust['public_key_base64'] ?? ''), true);
        $sig = base64_decode((string)($signature['signature_base64'] ?? ''), true);
        if (($trust['schema'] ?? null) !== 1 || ($trust['product_code'] ?? '') !== 'NAMUA_FINANCE'
            || ($trust['algorithm'] ?? '') !== 'Ed25519' || ($trust['status'] ?? '') !== 'ACTIVE'
            || !is_string($public) || strlen($public) !== 32
            || ($trust['public_key_sha256'] ?? '') !== hash('sha256', $public)
            || ($signature['schema'] ?? null) !== 1 || ($signature['product_code'] ?? '') !== 'NAMUA_FINANCE'
            || ($signature['algorithm'] ?? '') !== 'Ed25519' || ($signature['context'] ?? '') !== 'NAMUA_RELEASE_MANIFEST_V1'
            || ($signature['key_id'] ?? null) !== ($trust['key_id'] ?? null)
            || ($signature['public_key_sha256'] ?? '') !== hash('sha256', $public)
            || ($signature['manifest_sha256'] ?? '') !== $c['release_manifest_sha256']
            || !is_string($sig) || strlen($sig) !== 64 || !function_exists('sodium_crypto_sign_verify_detached')
            || !sodium_crypto_sign_verify_detached($sig, "NAMUA_RELEASE_MANIFEST_V1\n" . $c['release_manifest_sha256'], $public)) {
            throw new RuntimeException('CUSTOMER_RELEASE_PROOF_INVALID');
        }
        $m = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($m) || ($m['schema'] ?? null) !== 1 || ($m['context'] ?? '') !== 'NAMUA_RELEASE_MANIFEST_V1'
            || ($m['product_code'] ?? '') !== $c['product_code'] || ($m['version'] ?? '') !== $c['version']
            || ($m['source_commit'] ?? '') !== $c['source_commit'] || ($m['sha256'] ?? '') !== $c['artifact_sha256']
            || ($m['source_manifest_sha256'] ?? '') !== $c['app_manifest_sha256']
            || ($m['customer_runtime_guard'] ?? '') !== 'FINANCE_CUSTOMER_SERVER_V1'
            || ($m['release_public_id'] ?? '') !== $c['release_public_id']
            || ($m['distribution_profile'] ?? '') !== $c['distribution_profile']
            || ($m['distribution_profile_version'] ?? null) !== $c['distribution_profile_version']
            || ($m['customer_content_audit']['status'] ?? '') !== 'PASS'
            || ($m['customer_content_audit']['profile_sha256'] ?? '') !== $c['profile_sha256']
            || ($m['customer_content_audit']['artifact_sha256'] ?? '') !== $c['artifact_sha256']
            || ($m['customer_content_audit']['source_manifest_sha256'] ?? '') !== $c['source_manifest_sha256']
            || ($m['packaging']['profile_code'] ?? '') !== $c['distribution_profile']
            || ($m['packaging']['rules_sha256'] ?? '') !== $c['profile_sha256']
            || ($m['packaging']['audience'] ?? '') !== 'CUSTOMER' || ($m['packaging']['sample_data'] ?? '') !== 'NONE'
            || ($m['contains_customer_data'] ?? null) !== false || ($m['contains_secrets'] ?? null) !== false
            || !is_string($m['filename'] ?? null) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.tar\z/D', $m['filename']) !== 1
            || ($signature['signed_file'] ?? '') !== substr($m['filename'], 0, -4) . '.release.json') {
            throw new RuntimeException('CUSTOMER_RELEASE_BINDING_MISMATCH');
        }
    }

    public static function customer_machine_fingerprint(): string
    {
        require_once __DIR__.'/CustomerPlatform.php';
        if (PHP_OS_FAMILY === 'Windows') return CustomerPlatform::fingerprint();
        if (PHP_OS_FAMILY !== 'Linux' || !in_array(strtolower(php_uname('m')), ['x86_64', 'amd64'], true)) {
            throw new RuntimeException('CUSTOMER_PLATFORM_UNSUPPORTED');
        }
        $machine = trim((string)@file_get_contents('/etc/machine-id'));
        if (preg_match('/\A[a-f0-9]{32}\z/D', $machine) !== 1) throw new RuntimeException('CUSTOMER_MACHINE_ID_UNAVAILABLE');
        return hash('sha256', 'NAMUA_FINANCE' . "\0" . $machine . "\0linux-amd64");
    }

    /** No SQL/network access. Root health uses this too, so an unlocked login is not license proof. */
    public static function customer_verification(string $webroot, string $contextFile, ?int $now = null): array
    {
        try {
            $c = self::customer_context($webroot, $contextFile);
            $identity = Control_license_verifier::deployment_document($c['license_identity_file'], $webroot);
            if ($identity !== $c['identity']) throw new RuntimeException('CUSTOMER_IDENTITY_MISMATCH');
            $machine = self::customer_machine_fingerprint();
            if (!hash_equals($c['machine_fingerprint_sha256'], $machine)) throw new RuntimeException('CUSTOMER_MACHINE_MISMATCH');
            $trust = Control_license_verifier::deployment_document($c['license_trust_file'], $webroot);
            $cache = Control_license_verifier::deployment_document($c['license_cache_file'], $webroot, 300000);
            $v = self::verification($cache, $trust, $identity, $now);
            if (empty($v['verified']) || !in_array($v['status'] ?? '', ['ACTIVE', 'GRACE'], true)) return $v;
            return Control_license_verifier::verify($cache['envelope'], $trust,
                $identity + ['machine_fingerprint_sha256' => $machine], $now);
        } catch (Throwable $e) {
            return ['verified' => false, 'status' => 'RESTRICTED',
                'code' => $e instanceof RuntimeException ? $e->getMessage() : 'CUSTOMER_CONTEXT_INVALID'];
        }
    }

    /** Called before CI starts. No customer flags means unchanged master/legacy behaviour. */
    public static function customer_guard(string $webroot, array $environment, array $server, ?int $now = null): array
    {
        $managed = false;
        foreach (['FINANCE_CUSTOMER_INSTALLATION_FILE', 'FINANCE_LICENSE_TRUST_FILE',
            'FINANCE_LICENSE_IDENTITY_FILE', 'FINANCE_LICENSE_CACHE_FILE'] as $key) {
            if (($environment[$key] ?? '') !== '') $managed = true;
        }
        if (!$managed) return ['allowed' => true, 'managed' => false, 'status' => 'LEGACY', 'code' => 'CUSTOMER_GUARD_NOT_CONFIGURED'];
        try {
            $path = $environment['FINANCE_CUSTOMER_INSTALLATION_FILE'] ?? '';
            if (!is_string($path) || $path === '') throw new RuntimeException('CUSTOMER_CONTEXT_REQUIRED');
            $c = self::customer_context($webroot, $path);
            foreach (['TRUST' => 'license_trust_file', 'IDENTITY' => 'license_identity_file', 'CACHE' => 'license_cache_file'] as $key => $field) {
                $configured = $environment['FINANCE_LICENSE_' . $key . '_FILE'] ?? '';
                if ($configured !== '' && $configured !== $c[$field]) throw new RuntimeException('CUSTOMER_LICENSE_PATH_MISMATCH');
            }
            $v = self::customer_verification($webroot, $path, $now);
            $allowed = !empty($v['verified']) && in_array($v['status'] ?? '', ['ACTIVE', 'GRACE'], true);
            $method = $server['REQUEST_METHOD'] ?? ''; $uri = $server['REQUEST_URI'] ?? '';
            $route = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : false;
            $query = is_string($uri) ? parse_url($uri, PHP_URL_QUERY) : false; $args = [];
            if (is_string($query)) parse_str($query, $args);
            $recovery = !isset($args['c']) && !isset($args['m']) && !isset($args['d'])
                && ((($method === 'GET' || $method === 'HEAD') && in_array($route,
                    ['/login', '/auth', '/auth/index', '/system/license', '/license', '/license/index'], true))
                    || ($method === 'POST' && $route === '/auth/do_login')
                    || (($method === 'GET' || $method === 'POST') && in_array($route, ['/logout', '/auth/logout'], true)));
            return ['allowed' => $allowed || $recovery, 'managed' => true, 'recovery_only' => !$allowed && $recovery,
                'status' => $v['status'] ?? 'RESTRICTED', 'code' => $v['code'] ?? 'CUSTOMER_LICENSE_REQUIRED', 'verification'=>$v];
        } catch (Throwable $e) {
            return ['allowed' => false, 'managed' => true, 'status' => 'RESTRICTED',
                'code' => $e instanceof RuntimeException ? $e->getMessage() : 'CUSTOMER_CONTEXT_INVALID'];
        }
    }

    public static function initial(array $identity): array
    {
        return ['schema'=>1, 'purpose'=>'FINANCE_CONTROL_CACHE', 'identity'=>$identity,
            'last_seen_at'=>0, 'synced_at'=>0, 'issued_at'=>0, 'payload_sha256'=>'', 'revoked_at'=>0,
            'connection'=>'UNACTIVATED', 'envelope'=>null];
    }

    public static function assertState(array $cache, array $identity, int $now): void
    {
        if (($cache['schema'] ?? null) !== 1 || ($cache['purpose'] ?? '') !== 'FINANCE_CONTROL_CACHE'
            || ($cache['identity'] ?? null) !== $identity) throw new RuntimeException('CACHE_IDENTITY_INVALID');
        foreach (['last_seen_at','synced_at','issued_at','revoked_at'] as $field) {
            if (!is_int($cache[$field] ?? null) || $cache[$field] < 0) throw new RuntimeException('CACHE_STATE_INVALID');
        }
        if ($now + 300 < $cache['last_seen_at']) throw new RuntimeException('CLOCK_ROLLBACK');
    }

    public static function transition(array $cache, int $http, array $response, array $trust, array $identity, int $now): array
    {
        self::assertState($cache, $identity, $now);
        $next = $cache;
        $next['last_seen_at'] = max($now, $cache['last_seen_at']);
        if ($http === 403 && ($response['code'] ?? '') === 'license_revoked') {
            $next['revoked_at'] = max($cache['revoked_at'], $now);
            $next['connection'] = 'REVOKED';
            return $next; // Retain evidence, never delete the last signed document.
        }
        if ($http === 202 && ($response['status'] ?? '') === 'PENDING') {
            $next['connection'] = 'PENDING';
            return $next;
        }
        if ($http !== 200) {
            $next['connection'] = 'SYNC_UNAVAILABLE';
            return $next; // Offline/auth/server failures do not erase licensed rights.
        }
        $envelope = $response['license'] ?? null;
        if (!is_array($envelope)) throw new RuntimeException('LICENSE_RESPONSE_INVALID');
        // Control hashes this canonical envelope, not the decoded payload alone.
        $envelope = ['schema'=>$envelope['schema'] ?? null,'algorithm'=>$envelope['algorithm'] ?? null,
            'key_id'=>$envelope['key_id'] ?? null,'payload_base64'=>$envelope['payload_base64'] ?? null,
            'signature_base64'=>$envelope['signature_base64'] ?? null];
        $hash = hash('sha256', json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (!is_string($response['token_sha256'] ?? null) || !hash_equals($hash, $response['token_sha256'])) throw new RuntimeException('TOKEN_HASH_MISMATCH');
        $verified = Control_license_verifier::verify($envelope, $trust, $identity, $now);
        if (empty($verified['verified'])) throw new RuntimeException('LICENSE_DOCUMENT_INVALID');
        $issued = (new DateTimeImmutable($verified['payload']['issued_at']))->getTimestamp();
        if ($issued < $cache['issued_at']) throw new RuntimeException('LEASE_REPLAY');
        if ($issued === $cache['issued_at'] && $cache['payload_sha256'] !== ''
            && !hash_equals($cache['payload_sha256'], $verified['payload_sha256'])) throw new RuntimeException('LEASE_SEQUENCE_CONFLICT');
        if ($cache['revoked_at'] > 0 && $issued <= $cache['revoked_at']) throw new RuntimeException('REVOKED_LEASE_REPLAY');
        $next['issued_at'] = $issued;
        $next['payload_sha256'] = $verified['payload_sha256'];
        $next['envelope'] = $envelope;
        $next['connection'] = 'SYNCED';
        $next['synced_at'] = $now;
        $next['revoked_at'] = 0;
        return $next;
    }

    public static function verification(array $cache, array $trust, array $identity, ?int $now = null): array
    {
        try {
            $now = $now ?? time();
            self::assertState($cache, $identity, $now);
            if ($cache['revoked_at'] > 0) return ['verified'=>false,'status'=>'REVOKED','code'=>'CONTROL_REVOKED'];
            if (!is_array($cache['envelope'] ?? null)) return ['verified'=>false,'status'=>'UNACTIVATED','code'=>'NO_VERIFIED_DOCUMENT'];
            $v = Control_license_verifier::verify($cache['envelope'], $trust, $identity, $now);
            if (!empty($v['verified']) && (!is_string($cache['payload_sha256'] ?? null)
                || !hash_equals($v['payload_sha256'], $cache['payload_sha256'])
                || (new DateTimeImmutable($v['payload']['issued_at']))->getTimestamp() !== $cache['issued_at'])) throw new RuntimeException('CACHE_WATERMARK_MISMATCH');
            return $v;
        } catch (Throwable $e) {
            return ['verified'=>false,'status'=>'RESTRICTED','code'=>$e instanceof RuntimeException ? $e->getMessage() : 'CACHE_INVALID'];
        }
    }
}
