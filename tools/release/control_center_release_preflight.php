<?php
declare(strict_types=1);

/** Read-only bridge: tells Control Center whether this source can become a customer artifact. */
require_once __DIR__ . '/CustomerReleaseProfile.php';

function c3_fail(string $code, string $message): void { throw new RuntimeException($code . ': ' . $message); }
function c3_manifest(string $root): array {
    $path = $root . '/app-manifest.json';
    if (!is_file($path) || is_link($path)) c3_fail('manifest_missing', 'app-manifest.json is unavailable.');
    try { $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR); } catch (Throwable $e) { c3_fail('manifest_json', 'app manifest is malformed.'); }
    if (!is_array($value) || ($value['manifest_version'] ?? null) !== 2 || preg_match('/\A[A-Z][A-Z0-9_]{2,99}\z/D', (string)($value['product_code'] ?? '')) !== 1 || preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/D', (string)($value['version'] ?? '')) !== 1) c3_fail('manifest_contract', 'Product code or version is invalid.');
    return $value;
}
function c3_catalog_result(string $root): array {
    $command = [PHP_BINARY, $root . '/tools/db/migration_runner.php', 'validate'];
    $process = proc_open($command, [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
    if (!is_resource($process)) c3_fail('migration_runner', 'Migration validator could not start.');
    $stdout = (string)stream_get_contents($pipes[1]); $stderr = (string)stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
    if ($code !== 0) c3_fail('migration_catalog', 'Migration catalog validation failed.');
    $result = json_decode(trim($stdout), true);
    if (!is_array($result) || ($result['status'] ?? '') !== 'ok') c3_fail('migration_catalog', 'Migration catalog output is malformed.');
    return $result;
}

try {
    if (PHP_SAPI !== 'cli' || count($argv) > 2 || ($argv[1] ?? 'check') !== 'check') c3_fail('usage', 'Usage: php control_center_release_preflight.php check');
    $root = realpath(dirname(__DIR__, 2)); if ($root === false) c3_fail('root', 'Repository root is unavailable.');
    $manifest = c3_manifest($root);
    $policy = ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json');
    $enumerated = $policy->enumerate($root);
    $runtime = json_decode((string)file_get_contents($root . '/tools/release/runtime_compatibility.json'), true);
    if (!is_array($runtime) || ($runtime['schema'] ?? '') !== 'finance.runtime-compatibility') c3_fail('runtime_contract', 'Runtime compatibility contract is invalid.');
    $catalog = c3_catalog_result($root);
    $clean = ReleasePackagePolicy::worktreeClean($root);
    $profile = CustomerReleaseProfile::fromRoot($root);
    $audit = ['status' => 'NOT_RUN_SOURCE_DIRTY'];
    if ($clean && $enumerated['issues'] === []) {
        $entries = [];
        foreach ($enumerated['files'] as $path) if ($profile->allows($path)) $entries[] = ['path' => $path, 'sha256' => hash_file('sha256', $root . '/' . $path)];
        $audit = $profile->audit($entries);
    }
    echo json_encode(['status'=>'ok','product_code'=>$manifest['product_code'],'version'=>$manifest['version'],'schema_version'=>$manifest['schema_version'] ?? null,'worktree_clean'=>$clean,'artifact_publishable'=>$clean && $enumerated['issues'] === [] && $audit['status'] === 'PASS','package_issues'=>count($enumerated['issues']),'migration_catalog'=>$catalog,'distribution_profile'=>CustomerReleaseProfile::ID,'seed_profile'=>'REFERENCE_ONLY','source_content_audit'=>$audit,'next'=>$clean ? 'build_signed_artifact' : 'commit_or_stash_source_before_artifact_build'], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR, json_encode(['status'=>'blocked','reason'=>explode(': ', $e->getMessage(), 2)[0]], JSON_UNESCAPED_SLASHES) . PHP_EOL); exit(1); }
