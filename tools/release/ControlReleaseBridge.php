<?php
declare(strict_types=1);

require_once __DIR__ . '/ReleasePackagePolicy.php';
require_once __DIR__ . '/CustomerReleaseProfile.php';
if (!defined('FINANCE_ARTIFACT_SIGNATURE_LIBRARY_ONLY')) define('FINANCE_ARTIFACT_SIGNATURE_LIBRARY_ONLY', true);
require_once __DIR__ . '/artifact_signature.php';

/** Local, non-executing adapter. Never load PHP from the archive or apply its SQL. */
final class ControlReleaseBridge
{
    public const CONTEXT = 'NAMUA_RELEASE_MANIFEST_V1';
    public const MAX_BYTES = 1073741824; // Private CLI only, not the Control HTTP upload limit.
    public const BUILD_GATES = ['source_clean', 'security_scan', 'install_test', 'backup_restore',
        'customer_data_scan', 'secrets_scan', 'clean_install', 'source_untouched'];

    private static function need(bool $ok, string $reason): void
    {
        if (!$ok) throw new RuntimeException($reason);
    }

    public static function json(string $raw): array
    {
        $value = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        self::need(is_array($value), 'JSON_OBJECT_REQUIRED');
        return $value;
    }

    private static function member(string $artifact, string $path): string
    {
        $result = financeArtifactSignatureRun([financeArtifactSignatureTarBinary(), '--extract', '--to-stdout', '--file=' . $artifact, '--', $path]);
        self::need($result['code'] === 0 && !$result['overflow'], 'ARCHIVE_MEMBER_INVALID');
        return $result['stdout'];
    }

    /** Approved contracts; preserve verification of immutable 10.6 candidates. */
    public static function databaseRule(string $contract): array
    {
        $rules = [
            'MariaDB >=10.6 <10.7' => ['minimum' => '10.6.0', 'maximum_exclusive' => '10.7.0'],
            'MariaDB >=10.11 <10.12' => ['minimum' => '10.11.0', 'maximum_exclusive' => '10.12.0'],
        ];
        self::need(isset($rules[$contract]), 'RUNTIME_CONTRACT');
        return $rules[$contract];
    }

    public static function validateRuntime(array $app, array $runtime): void
    {
        $rule = self::databaseRule((string)($app['runtime']['database'] ?? ''));
        self::need(($app['runtime']['php'] ?? '') === ($runtime['runtimes']['php']['composer_constraint'] ?? null)
            && ($app['runtime']['php'] ?? '') === '>=8.1 <8.2'
            && ($runtime['runtimes']['mariadb']['minimum'] ?? '') === $rule['minimum']
            && ($runtime['runtimes']['mariadb']['maximum_exclusive'] ?? '') === $rule['maximum_exclusive'], 'RUNTIME_CONTRACT');
    }

    /** Verify archive content, policy, SQL inventory, baseline and runtime without extracting executable code. */
    public static function inspect(string $artifact): array
    {
        financeArtifactSignatureRegularFile($artifact, 'ARTIFACT_UNSAFE');
        self::need(preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.tar\z/D', basename($artifact)) === 1
            && strpos(basename($artifact), '..') === false && filesize($artifact) > 0
            && filesize($artifact) <= self::MAX_BYTES, 'ARTIFACT_SIZE_OR_TYPE');
        // Plain TAR only: gzip bombs cannot expand behind the private CLI size limit.
        self::need(file_get_contents($artifact, false, null, 257, 5) === 'ustar', 'PLAIN_TAR_REQUIRED');
        $before = hash_file('sha256', $artifact);
        $proof = financeArtifactSignatureInspectManifest($artifact);
        $source = self::json(self::member($artifact, 'RELEASE-MANIFEST.json'));
        $files = array_column($source['files'], null, 'path');
        self::need(count($files) === count($source['files']) && count($files) <= 50000
            && array_sum(array_column($files, 'size')) <= self::MAX_BYTES, 'ARCHIVE_LIMIT');
        // Enforce the validator's policy too; a signed bundle cannot weaken its own exclusions.
        $policy = ReleasePackagePolicy::fromFile(__DIR__ . '/package_policy.json');
        foreach ($files as $path => $entry) {
            $sourcePath=CustomerLayout::source($path);
            self::need($policy->included($sourcePath) && !$policy->denied($sourcePath), 'FORBIDDEN_PACKAGE_PATH');
        }
        $customerAudit = null;
        if (isset($files[CustomerReleaseProfile::PATH])) {
            // A bundle cannot approve its own content by replacing its allowlist.
            $profile = CustomerReleaseProfile::fromRoot(dirname(__DIR__, 2));
            self::need($files[CustomerReleaseProfile::PATH]['sha256'] === $profile->digest(), 'CUSTOMER_PROFILE_UNTRUSTED');
            $customerAudit = $profile->audit($source['files']);
            $customerAudit['artifact_sha256'] = $before;
            $customerAudit['source_manifest_sha256'] = $proof['manifest_sha256'];
        }
        $app = self::json(self::member($artifact, 'app-manifest.json'));
        $runtime = self::json(self::member($artifact, 'tools/release/runtime_compatibility.json'));
        self::need(($app['manifest_version'] ?? null) === 2 && ($app['product_code'] ?? '') === 'NAMUA_FINANCE'
            && preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/D', (string)($app['version'] ?? '')) === 1, 'PRODUCT_OR_VERSION');
        foreach (['schema_version', 'baseline_schema_version'] as $field) self::need(preg_match('/\A[A-Za-z0-9._-]{1,80}\z/D', (string)($app[$field] ?? '')) === 1, 'SCHEMA_VERSION');
        self::validateRuntime($app, $runtime);
        $catalog = self::json(self::member($artifact, 'tools/db/migration_catalog.json'));
        self::need(($catalog['catalog_version'] ?? null) === 1 && !empty($catalog['migrations']), 'MIGRATION_CATALOG');
        $inventory = [];
        foreach ($catalog['migrations'] as $entry) {
            $path = $entry['path'] ?? '';
            self::need(is_string($path) && preg_match('/\Asql\/[A-Za-z0-9_-]+\.sql\z/D', $path) === 1
                && !isset($inventory[$path]) && isset($files[$path])
                && ($entry['sha256'] ?? '') === $files[$path]['sha256'], 'MIGRATION_CHECKSUM');
            $inventory[$path] = true;
        }
        $legacy = [];
        foreach (($catalog['legacy_unmanaged_sql'] ?? []) as $path) {
            if ($customerAudit !== null) {
                self::need(is_string($path) && !isset($files[$path]), 'CUSTOMER_LEGACY_SQL_FORBIDDEN');
                continue;
            }
            self::need(is_string($path) && preg_match('/\Asql\/[A-Za-z0-9_-]+\.sql\z/D', $path) === 1
                && !isset($inventory[$path]) && isset($files[$path]), 'LEGACY_SQL_INVENTORY');
            $inventory[$path] = true;
            $legacy[] = ['path' => $path, 'sha256' => $files[$path]['sha256'], 'execution' => 'EXPLICIT_LEGACY_POLICY_ONLY'];
        }
        foreach ($files as $path => $entry) {
            if (preg_match('/\Asql\/[^\/]+\.sql\z/D', $path) === 1) self::need(isset($inventory[$path]), 'UNDECLARED_SQL');
        }
        $baseline = self::json(self::member($artifact, 'tools/db/clean_install_baseline_policy.json'));
        $basePath = $baseline['schema']['path'] ?? '';
        self::need(isset($files[$basePath]) && ($baseline['schema']['sha256'] ?? '') === $files[$basePath]['sha256'], 'BASELINE_CHECKSUM');
        self::need(hash_equals($before, (string)hash_file('sha256', $artifact)), 'ARTIFACT_CHANGED');
        $description = ['manifest_version' => 2, 'product_code' => 'NAMUA_FINANCE', 'version' => $app['version'],
            'schema_version' => $app['schema_version'], 'baseline_schema_version' => $app['baseline_schema_version'],
            'artifact' => basename($artifact), 'sha256' => $before, 'size_bytes' => filesize($artifact),
            'source_manifest_sha256' => $proof['manifest_sha256'], 'source_epoch' => $proof['source_epoch'],
            'policy_digests' => $proof['policy_digests'], 'runtime' => $app['runtime'],
            'migration_format' => 'finance-sql-catalog-v1', 'migrations' => $catalog['migrations'],
            'legacy_sql' => $legacy, 'baseline' => ['path' => $basePath, 'sha256' => $files[$basePath]['sha256']],
            'contains_customer_data' => $customerAudit !== null ? false : null, 'contains_secrets' => false,
            'channel' => 'ALPHA', 'readiness' => 'INTERNAL_CANDIDATE',
            'apk' => ['bundled' => false, 'commercial_work' => 'ALLOWED', 'operational_bugfixes' => 'DEFERRED', 'release_ready' => false]];
        if ($customerAudit !== null) {
            $description += ['distribution_profile' => CustomerReleaseProfile::ID,
                'distribution_profile_version' => $profile->version(), 'seed_profile' => 'REFERENCE_ONLY', 'customer_content_audit' => $customerAudit];
            if ($profile->version() >= 11) $description['application_update_contract'] = 'FINANCE_APPLICATION_UPDATE_V1';
        }
        return $description;
    }

    public static function describe(string $root, string $artifact): array
    {
        self::need(realpath($root) === $root && ReleasePackagePolicy::worktreeClean($root), 'SOURCE_DIRTY');
        // The worker is unprivileged while the approved source remains root-owned.
        // Scope Git trust to this canonical checkout, never to a wildcard/global config.
        $head = financeArtifactSignatureRun(['git', '-c', 'safe.directory=' . $root, '-C', $root, 'rev-parse', '--verify', 'HEAD']);
        $revision = trim($head['stdout']);
        self::need($head['code'] === 0 && preg_match('/\A[a-f0-9]{40}\z/D', $revision) === 1, 'SOURCE_COMMIT');
        $description = self::inspect($artifact);
        self::need(($description['distribution_profile'] ?? '') === CustomerReleaseProfile::ID, 'CUSTOMER_PROFILE_REQUIRED');
        $source = self::json(self::member($artifact, 'RELEASE-MANIFEST.json'));
        $expected = ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json')->enumerate($root);
        self::need($expected['issues'] === [], 'SOURCE_POLICY');
        $profile = CustomerReleaseProfile::fromRoot($root);
        $expected['files'] = array_values(array_filter($expected['files'], fn(string $p): bool => $profile->allows($p)));
        $paths = array_column($source['files'], 'path'); sort($paths);
        $tracked = $profile->version()>=6 ? array_map([CustomerLayout::class,'target'],$expected['files']) : $expected['files']; sort($tracked);
        self::need($paths === $tracked, 'SOURCE_FILE_SET');
        foreach ($source['files'] as $entry) {
            $path = $root . '/' . ($profile->version()>=6 ? CustomerLayout::source($entry['path']) : $entry['path']);
            self::need(!is_link($path) && is_file($path) && filesize($path) === $entry['size']
                && hash_file('sha256', $path) === $entry['sha256'], 'SOURCE_BYTES_MISMATCH');
        }
        $after = financeArtifactSignatureRun(['git', '-c', 'safe.directory=' . $root, '-C', $root, 'rev-parse', '--verify', 'HEAD']);
        self::need(ReleasePackagePolicy::worktreeClean($root) && trim($after['stdout']) === $revision, 'SOURCE_CHANGED');
        return $description + ['source_commit' => $revision, 'source_dirty' => false];
    }

    public static function loadKey(string $path): array
    {
        financeArtifactSignatureRegularFile($path, 'KEY_UNSAFE');
        self::need(strpos($path, '/www/wwwroot/') !== 0 && fileowner($path) === 0
            && (fileperms($path) & 0777) === 0600, 'KEY_PERMISSIONS');
        financeArtifactSignatureSafeParent($path);
        return self::json((string)file_get_contents($path));
    }

    private static function publicKey(array $key): string
    {
        $public = base64_decode((string)($key['public_key_base64'] ?? ''), true);
        self::need(($key['schema'] ?? null) === 1 && ($key['product_code'] ?? '') === 'NAMUA_FINANCE'
            && ($key['algorithm'] ?? '') === 'Ed25519' && preg_match('/\A[0-9a-f-]{36}\z/D', (string)($key['key_id'] ?? '')) === 1
            && is_string($public) && strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            && hash('sha256', $public) === ($key['public_key_sha256'] ?? ''), 'KEY_CONTRACT');
        return $public;
    }

    public static function sign(string $bytes, string $name, array $key): array
    {
        $public = self::publicKey($key);
        $secret = base64_decode((string)($key['secret_key_base64'] ?? ''), true);
        self::need(is_string($secret) && strlen($secret) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 'PRIVATE_KEY_INVALID');
        try {
            self::need(sodium_crypto_sign_publickey_from_secretkey($secret) === $public, 'KEY_PAIR_MISMATCH');
            $digest = hash('sha256', $bytes);
            return ['schema' => 1, 'product_code' => 'NAMUA_FINANCE', 'algorithm' => 'Ed25519',
                'context' => self::CONTEXT, 'signed_file' => $name, 'key_id' => $key['key_id'],
                'manifest_sha256' => $digest, 'public_key_sha256' => hash('sha256', $public),
                'signature_base64' => base64_encode(sodium_crypto_sign_detached(self::CONTEXT . "\n" . $digest, $secret))];
        } finally { sodium_memzero($secret); }
    }

    /** Only recognized wire formats may select the archive basename. Not a signature check. */
    public static function artifactName(array $manifest): string
    {
        $modern = ($manifest['schema'] ?? null) === 1 && ($manifest['context'] ?? '') === self::CONTEXT;
        self::need($modern || ($manifest['manifest_version'] ?? null) === 2, 'MANIFEST_FORMAT');
        $name = $manifest[$modern ? 'filename' : 'artifact'] ?? null;
        self::need(is_string($name) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.tar\z/D', $name) === 1
            && strpos($name, '..') === false, 'ARTIFACT_NAME');
        return $name;
    }

    /** Called only after authenticating the original Control bytes. Never trust archive PHP. */
    private static function normalizeControl(array $wire, array $inspection, string $artifact, string $name): array
    {
        self::need(!isset($wire['customer_runtime_guard'])
            || $wire['customer_runtime_guard'] === 'FINANCE_CUSTOMER_SERVER_V1', 'CONTROL_RUNTIME_GUARD_UNSUPPORTED');
        self::need(($wire['schema'] ?? null) === 1 && ($wire['context'] ?? '') === self::CONTEXT
            && !isset($wire['manifest_version']) && !isset($wire['artifact'])
            && ($wire['media_type'] ?? '') === 'application/x-tar'
            && ($wire['contains_customer_data'] ?? null) === false && ($wire['contains_secrets'] ?? null) === false
            && in_array($wire['channel'] ?? '', ['ALPHA', 'BETA', 'RC', 'STABLE'], true)
            && preg_match('/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/D', (string)($wire['release_public_id'] ?? '')) === 1,
            'CONTROL_MANIFEST_CONTRACT');
        self::need(($inspection['distribution_profile'] ?? '') === CustomerReleaseProfile::ID, 'CUSTOMER_PROFILE_REQUIRED');
        foreach (['product_code', 'version', 'sha256', 'size_bytes'] as $field) {
            self::need(($wire[$field] ?? null) === $inspection[$field], 'MANIFEST_CONTENT_MISMATCH');
        }
        self::need(self::artifactName($wire) === basename($artifact)
            && $name === substr(basename($artifact), 0, -4) . '.release.json', 'CONTROL_ARTIFACT_BINDING');
        foreach (['source_manifest_sha256', 'build_request_sha256', 'build_report_sha256'] as $field) {
            self::need(preg_match('/\A[a-f0-9]{64}\z/D', (string)($wire[$field] ?? '')) === 1, 'CONTROL_DIGEST_INVALID');
        }
        // Control hashes app-manifest.json here; Finance v2 hashes RELEASE-MANIFEST.json.
        // Keeping these separate prevents rejecting valid packages or accepting substituted source.
        self::need(hash('sha256', self::member($artifact, 'app-manifest.json')) === $wire['source_manifest_sha256'], 'CONTROL_SOURCE_MANIFEST_MISMATCH');
        $p = $wire['packaging'] ?? [];
        self::need(is_array($p) && ($p['profile_code'] ?? '') === CustomerReleaseProfile::ID
            && ($p['rules_sha256'] ?? '') === $inspection['customer_content_audit']['profile_sha256']
            && ($p['audience'] ?? '') === 'CUSTOMER' && ($p['sample_data'] ?? '') === 'NONE', 'CONTROL_PROFILE_MISMATCH');
        $gates = $wire['verification'] ?? [];
        self::need(is_array($gates) && count($gates) === count(self::BUILD_GATES), 'CONTROL_GATES_INVALID');
        foreach (self::BUILD_GATES as $gate) self::need(($gates[$gate]['status'] ?? '') === 'PASS'
            && preg_match('/\A[a-f0-9]{64}\z/D', (string)($gates[$gate]['evidence_sha256'] ?? '')) === 1, 'CONTROL_GATES_INVALID');
        $inspection['channel'] = $wire['channel'];
        $normalized = $inspection + ['source_commit' => $wire['source_commit'], 'source_dirty' => false,
            'control_app_manifest_sha256' => $wire['source_manifest_sha256'], 'release_public_id' => $wire['release_public_id']];
        if (isset($wire['customer_runtime_guard'])) $normalized['customer_runtime_guard'] = $wire['customer_runtime_guard'];
        return $normalized;
    }

    public static function verify(string $bytes, string $name, array $signature, array $trust, string $artifact): array
    {
        $public = self::publicKey($trust);
        $sig = base64_decode((string)($signature['signature_base64'] ?? ''), true);
        self::need(($trust['status'] ?? '') === 'ACTIVE' && ($signature['schema'] ?? null) === 1
            && ($signature['product_code'] ?? '') === 'NAMUA_FINANCE' && ($signature['algorithm'] ?? '') === 'Ed25519'
            && ($signature['context'] ?? '') === self::CONTEXT && ($signature['signed_file'] ?? '') === $name
            && ($signature['key_id'] ?? '') === $trust['key_id']
            && ($signature['public_key_sha256'] ?? '') === hash('sha256', $public)
            && ($signature['manifest_sha256'] ?? '') === hash('sha256', $bytes)
            && is_string($sig) && strlen($sig) === SODIUM_CRYPTO_SIGN_BYTES
            && sodium_crypto_sign_verify_detached($sig, self::CONTEXT . "\n" . hash('sha256', $bytes), $public), 'SIGNATURE_INVALID');
        $manifest = self::json($bytes);
        self::need(preg_match('/\A[a-f0-9]{40}\z/D', (string)($manifest['source_commit'] ?? '')) === 1, 'SOURCE_ATTESTATION_INVALID');
        $inspection = self::inspect($artifact);
        if (($manifest['schema'] ?? null) === 1) {
            $manifest = self::normalizeControl($manifest, $inspection, $artifact, $name);
            $inspection['channel'] = $manifest['channel'];
        }
        self::need(($manifest['source_dirty'] ?? true) === false, 'SOURCE_ATTESTATION_INVALID');
        foreach ($inspection as $field => $value) {
            // Historical signed v2 sidecars hardcoded this boolean. Preserve provenance, never clean eligibility.
            if ($field === 'contains_customer_data' && $value === null && ($manifest[$field] ?? null) === false) continue;
            self::need(array_key_exists($field, $manifest) && $manifest[$field] === $value, 'MANIFEST_CONTENT_MISMATCH');
        }
        $clean = ($inspection['distribution_profile'] ?? '') === CustomerReleaseProfile::ID;
        if (!$clean) foreach (['distribution_profile', 'distribution_profile_version', 'seed_profile', 'customer_content_audit'] as $field) {
            self::need(!array_key_exists($field, $manifest), 'LEGACY_CLEAN_CLAIM_FORBIDDEN');
        }
        return ['status' => 'PASS', 'product_code' => 'NAMUA_FINANCE', 'version' => $manifest['version'],
            'source_commit' => $manifest['source_commit'], 'artifact_sha256' => $manifest['sha256'],
            'managed_sql' => count($manifest['migrations']), 'legacy_sql' => count($manifest['legacy_sql']),
            'readiness' => 'INTERNAL_CANDIDATE', 'published' => false, 'database_changed' => false, 'apk_release_ready' => false,
            'customer_clean_eligible' => $clean,
            'distribution_profile' => $clean ? CustomerReleaseProfile::ID : 'LEGACY_INTERNAL',
            'distribution_profile_version' => $clean ? $inspection['distribution_profile_version'] : null,
            'application_update_contract' => $clean ? ($inspection['application_update_contract'] ?? null) : null,
            'seed_profile' => $clean ? 'REFERENCE_ONLY' : null,
            'customer_content_audit' => $inspection['customer_content_audit'] ?? ['status' => 'NOT_AUDITED'],
            'install_manifest' => $manifest];
    }
}
