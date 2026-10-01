<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
    define('BASEPATH', $root . '/system/');
}
if (!defined('FCPATH')) {
    define('FCPATH', $root . DIRECTORY_SEPARATOR);
}

require_once $root . '/application/libraries/AuditRoadmapReader.php';
require_once $root . '/tools/release/ReleasePackagePolicy.php';

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    $failures[] = $label;
    fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
};
$source = static function (string $path): string {
    $contents = @file_get_contents($path);
    return is_string($contents) ? $contents : '';
};

$controllerPath = $root . '/application/controllers/Audit.php';
$readerPath = $root . '/application/libraries/AuditRoadmapReader.php';
$viewPath = $root . '/application/views/audit/roadmap.php';
$commercialPath = $root . '/docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md';
$controller = $source($controllerPath);
$readerSource = $source($readerPath);
$viewSource = $source($viewPath);
$commercialSource = $source($commercialPath);
$markerPath = $root . '/.codex/internal_audit_dashboard.enabled';

$check(
    strpos($controller, "class Audit extends MY_Controller") !== false
        && strpos($controller, "function roadmap(): void") !== false,
    'default CodeIgniter /audit/roadmap controller target exists without route wiring'
);
$check(
    strpos($controller, "method(true) !== 'GET'") !== false
        && strpos($controller, "set_header('Allow: GET')") !== false
        && strpos($controller, '405') !== false,
    'controller rejects non-GET methods and declares Allow GET'
);
$check(
    strpos($controller, "!defined('ENVIRONMENT')") !== false
        && strpos($controller, "!defined('FCPATH')") !== false
        && strpos($controller, "\$environment !== 'development'") !== false
        && strpos($controller, 'show_404()') !== false
        && strpos($controller, '!$this->is_superadmin()') !== false
        && strpos($controller, '403') !== false,
    'only development may pass the internal policy and non-superadmin access is denied with 403'
);
$constructorGuard = strpos($controller, 'public function __construct()');
$environmentConstructorGuard = strpos($controller, 'if (!self::internalDashboardEnabled())');
$parentConstructor = strpos($controller, 'parent::__construct()');
$check(
    $constructorGuard !== false
        && $environmentConstructorGuard !== false
        && $parentConstructor !== false
        && $constructorGuard < $environmentConstructorGuard
        && $environmentConstructorGuard < $parentConstructor,
    'environment and marker 404 guard runs before inherited authentication can redirect'
);
$environmentGuard = strpos($controller, 'if (!self::internalDashboardEnabled())');
$methodGuard = strpos($controller, "method(true) !== 'GET'");
$superadminGuard = strpos($controller, '!$this->is_superadmin()');
$check(
    $environmentGuard !== false
        && $methodGuard !== false
        && $superadminGuard !== false
        && $environmentGuard < $methodGuard
        && $methodGuard < $superadminGuard
        && strpos($controller, 'require_permission') === false
        && strpos($controller, 'PAGE_CODE') === false
        && strpos($controller, "'active_menu' => 'grp.system'") !== false,
    'internal marker guard precedes GET/superadmin checks without a synthetic RBAC page code'
);
$check(
    strpos($controller, "'.codex/internal_audit_dashboard.enabled'") !== false
        && strpos($controller, 'is_link($marker)') !== false
        && strpos($controller, '!is_file($marker)') !== false
        && strpos($controller, '!is_readable($marker)') !== false
        && strpos($controller, 'hash_equals(self::ENABLE_TOKEN, $contents)') !== false
        && strpos($controller, 'getenv(') === false,
    'controller uses a fixed regular-readable-nonsymlink marker with exact token and no environment-variable switch'
);
$check(
    strpos($controller, 'Cache-Control: no-store') !== false
        && strpos($controller, 'X-Content-Type-Options: nosniff') !== false,
    'controller emits no-store and nosniff response guards'
);
$check(
    strpos($controller . $readerSource, '$_GET') === false
        && strpos($controller . $readerSource, 'input->get') === false
        && strpos($controller . $readerSource, 'QUERY_STRING') === false,
    'dashboard enablement and reader selection cannot be influenced by query input'
);

if (!class_exists('MY_Controller', false)) {
    class MY_Controller
    {
    }
}
require_once $controllerPath;
$controllerReflection = new ReflectionClass(Audit::class);
$policyAllows = $controllerReflection->getMethod('internalDashboardPolicyAllows');
$policyAllows->setAccessible(true);
$enableToken = (string)$controllerReflection->getConstant('ENABLE_TOKEN');
$fixtureDirectory = sys_get_temp_dir() . '/finance-audit-dashboard-' . bin2hex(random_bytes(6));
mkdir($fixtureDirectory, 0700, true);
$validMarker = $fixtureDirectory . '/enabled';
$wrongMarker = $fixtureDirectory . '/wrong';
$linkedMarker = $fixtureDirectory . '/linked';
file_put_contents($validMarker, $enableToken);
file_put_contents($wrongMarker, "enabled-v1");
$linkCreated = @symlink($validMarker, $linkedMarker);
register_shutdown_function(static function () use ($validMarker, $wrongMarker, $linkedMarker, $fixtureDirectory): void {
    @unlink($linkedMarker);
    @unlink($wrongMarker);
    @unlink($validMarker);
    @rmdir($fixtureDirectory);
});
$allows = static function (string $environment, string $marker) use ($policyAllows): bool {
    return (bool)$policyAllows->invoke(null, $environment, $marker);
};
$check(
    $allows('development', $validMarker)
        && !$allows('testing', $validMarker)
        && !$allows('production', $validMarker)
        && !$allows('unknown', $validMarker),
    'policy fixture allows only the exact development environment'
);
$check(
    !$allows('development', $fixtureDirectory . '/missing')
        && !$allows('development', $wrongMarker)
        && (!$linkCreated || !$allows('development', $linkedMarker)),
    'policy fixture rejects missing, wrong-token, and symlink markers'
);
if (!file_exists($markerPath) && !is_link($markerPath)) {
    $check(!$allows('development', $markerPath), 'clean checkout keeps internal dashboard disabled without copying a staging marker');
} else $check(
    is_file($markerPath)
        && !is_link($markerPath)
        && is_readable($markerPath)
        && hash_equals($enableToken, (string)file_get_contents($markerPath)),
    'workspace marker is a regular readable non-symlink with the exact token bytes'
);

$readerReflection = new ReflectionClass(AuditRoadmapReader::class);
$publicMethods = array_values(array_filter(
    $readerReflection->getMethods(ReflectionMethod::IS_PUBLIC),
    static fn(ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === AuditRoadmapReader::class
));
$check(
    count($publicMethods) === 1
        && $publicMethods[0]->getName() === 'read'
        && $publicMethods[0]->getNumberOfParameters() === 0,
    'reader exposes only a zero-argument read API'
);
$deriveReadiness = $readerReflection->getMethod('deriveReadiness');
$deriveReadiness->setAccessible(true);
$phaseFixture = static function (string $prefix, ?int $blockedIndex = null): array {
    $rows = [];
    for ($index = 0; $index < 6; $index++) {
        $rows[] = [
            'Fase' => $prefix . $index . ' — fixture',
            'Status fase' => $blockedIndex === $index ? 'PARTIAL' : 'DONE',
            'Alasan/gerbang berikutnya' => $blockedIndex === $index ? 'Fixture belum selesai.' : 'Fixture selesai.',
        ];
    }
    return $rows;
};
$blockedReadiness = $deriveReadiness->invoke(
    new AuditRoadmapReader(),
    $phaseFixture('A', 1),
    $phaseFixture('C')
);
$readyReadiness = $deriveReadiness->invoke(
    new AuditRoadmapReader(),
    $phaseFixture('A'),
    $phaseFixture('C')
);
$check(
    ($blockedReadiness['status'] ?? '') === 'BLOCKED'
        && ($blockedReadiness['label'] ?? '') === 'Belum siap jual'
        && ($blockedReadiness['technical_incomplete_count'] ?? null) === 1
        && count($blockedReadiness['reasons'] ?? []) === 1
        && strpos((string)($blockedReadiness['reasons'][0] ?? ''), 'Fixture belum selesai.') !== false,
    'private readiness helper derives blocked state, technical count, and reason from phase status'
);
$check(
    ($readyReadiness['status'] ?? '') === 'READY'
        && ($readyReadiness['label'] ?? '') === 'Siap jual'
        && ($readyReadiness['technical_incomplete_count'] ?? null) === 0
        && ($readyReadiness['commercial_incomplete_count'] ?? null) === 0
        && ($readyReadiness['reasons'] ?? null) === [],
    'private readiness helper changes blocked to ready only when all A0-A5 and C0-C5 are DONE'
);
$check(
    substr_count($readerSource, 'docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md') === 1
        && substr_count($readerSource, 'docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md') === 1
        && strpos($readerSource, 'MAX_DOCUMENT_BYTES') !== false
        && strpos($readerSource, 'is_readable') !== false
        && strpos($readerSource, 'is_link') !== false,
    'reader has exactly two fixed documents and fail-closed file guards'
);
$check(
    strpos($readerSource, 'Ringkasan roadmap belum dapat ditampilkan saat ini.') !== false
        && strpos($readerSource, "'message' => \$exception") === false
        && strpos($readerSource, 'getMessage()') === false,
    'reader exposes only a generic unavailable message'
);

$result = (new AuditRoadmapReader())->read();
$check(
    ($result['ok'] ?? false) === true
        && count($result['phases'] ?? []) === 6
        && count($result['findings'] ?? []) >= 1
        && count($result['ui_waves'] ?? []) === 9
        && count($result['sql_register'] ?? []) >= 1
        && count($result['commercial_phases'] ?? []) === 6,
    'real canonical documents parse into every dashboard section'
);
$phaseStatuses = [];
foreach ($result['phases'] ?? [] as $phase) {
    if (preg_match('/\A(A[0-5])\b/u', (string)($phase['Fase'] ?? ''), $match) === 1) {
        $phaseStatuses[$match[1]] = (string)($phase['Status fase'] ?? '');
    }
}
$check(
    isset($phaseStatuses['A0'], $phaseStatuses['A1'], $phaseStatuses['A5'])
        && $phaseStatuses['A0'] !== 'DONE'
        && $phaseStatuses['A1'] !== 'DONE'
        && $phaseStatuses['A5'] !== 'DONE',
    'A0, A1, and A5 remain explicitly not DONE in real audit data'
);
$check(
    ($result['readiness']['label'] ?? '') === 'Belum siap jual'
        && ($result['readiness']['status'] ?? '') === 'BLOCKED'
        && ($result['readiness']['technical_incomplete_count'] ?? null) === 6
        && count($result['readiness']['reasons'] ?? []) >= 6
        && ($result['readiness']['total_findings'] ?? 0) === count($result['findings'] ?? []),
    'real readiness is phase-derived with technical count/reasons and informational finding total'
);
$check(
    strpos($commercialSource, '### 0.1 Status kanonis fase C0–C5') !== false
        && preg_match_all('/^\| C[0-5] —/mu', $commercialSource) === 6,
    'commercial roadmap owns one canonical C0-C5 status table'
);

$flatten = static function (array $value) use (&$flatten): array {
    $output = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            $output = array_merge($output, $flatten($item));
        } elseif (is_string($item)) {
            $output[] = $item;
        }
    }
    return $output;
};
$parsedText = implode("\n", $flatten([
    $result['phases'] ?? [],
    $result['findings'] ?? [],
    $result['ui_waves'] ?? [],
    $result['sql_register'] ?? [],
    $result['commercial_phases'] ?? [],
]));
$check(
    strpos($parsedText, '`') === false
        && preg_match('/\[[^\]]+\]\([^)]+\)/u', $parsedText) !== 1,
    'parser projects canonical cells as plain text rather than Markdown'
);

if (!function_exists('html_escape')) {
    function html_escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
$attack = '<script>alert("audit-xss")</script>';
$roadmap = [
    'ok' => true,
    'message' => '',
    'readiness' => [
        'label' => 'Belum siap jual',
        'status' => 'BLOCKED',
        'technical_incomplete_count' => 1,
        'commercial_incomplete_count' => 0,
        'total_findings' => 1,
        'reasons' => ['A1 [PARTIAL] — Fixture belum selesai.'],
    ],
    'phases' => [],
    'findings' => [[
        'ID' => 'AUD-XSS-01',
        'Sumber' => 'fixture',
        'Prioritas/fase' => 'P0 / A1',
        'Masalah' => $attack,
        'Solusi/acceptance' => 'Tetap data',
        'Implementasi' => 'NOT_STARTED',
        'Validasi' => 'NONE',
        'Release/data' => 'BLOCKED',
        'Bukti atau langkah berikutnya' => $attack,
    ]],
    'ui_waves' => [],
    'sql_register' => [],
    'commercial_phases' => [],
];
ob_start();
require $viewPath;
$rendered = (string)ob_get_clean();
$check(
    strpos($rendered, $attack) === false
        && substr_count($rendered, '&lt;script&gt;alert(&quot;audit-xss&quot;)&lt;/script&gt;') >= 2,
    'XSS fixture is projected as escaped data in text and filter attributes'
);
$check(
    strpos($viewSource, 'data-audit-finding') !== false
        && strpos($viewSource, 'audit-finding-search') !== false
        && strpos($viewSource, 'audit-finding-status') !== false
        && strpos($viewSource, 'textContent') !== false
        && strpos($viewSource, '$escape(') !== false
        && strpos($viewSource, 'Fase teknis belum selesai') !== false
        && strpos($viewSource, 'Total temuan') !== false
        && strpos($viewSource, 'Temuan terbuka') === false
        && strpos($viewSource, 'open_findings') === false,
    'view contains escaped filters, phase-derived metric, and informational finding total'
);
$tabContract = [
    ['audit-summary-tab', 'audit-summary', 'Ringkasan'],
    ['audit-findings-tab', 'audit-findings', 'Temuan'],
    ['audit-ui-tab', 'audit-ui-waves', 'UI 8.3'],
    ['audit-sql-tab', 'audit-sql', 'SQL'],
    ['audit-commercial-tab', 'audit-commercial', 'Komersialisasi'],
];
$tabContractValid = strpos($rendered, 'id="audit-roadmap-tabs" role="tablist"') !== false
    && substr_count($rendered, 'data-bs-toggle="tab"') === count($tabContract)
    && preg_match_all('/<button\b[^>]*\brole="tab"/', $rendered) === count($tabContract)
    && preg_match_all('/<div\b[^>]*\brole="tabpanel"/', $rendered) === count($tabContract)
    && substr_count($rendered, 'tab-pane fade show active') === 1;
foreach ($tabContract as [$tabId, $panelId, $label]) {
    $tabContractValid = $tabContractValid
        && strpos($rendered, 'id="' . $tabId . '" data-bs-toggle="tab" data-bs-target="#' . $panelId . '"') !== false
        && strpos($rendered, 'aria-controls="' . $panelId . '"') !== false
        && strpos($rendered, 'id="' . $panelId . '" role="tabpanel" aria-labelledby="' . $tabId . '"') !== false
        && preg_match('/<button[^>]+id="' . preg_quote($tabId, '/') . '"[^>]*>' . preg_quote($label, '/') . '<\/button>/', $rendered) === 1;
}
$check(
    $tabContractValid,
    'view renders exactly five accessible Bootstrap tabs and one initially visible panel'
);
$check(
    strpos($viewSource, "'#audit-phases': '#audit-summary-tab'") !== false
        && strpos($viewSource, "'#audit-ui-waves': '#audit-ui-tab'") !== false
        && strpos($viewSource, "window.addEventListener('hashchange', restoreFromHash)") !== false
        && strpos($viewSource, "window.addEventListener('popstate', restoreFromHash)") !== false
        && strpos($viewSource, 'data-audit-tab-hash') !== false
        && strpos($viewSource, 'window.bootstrap.Tab.getOrCreateInstance(tab).show()') !== false,
    'tab state restores from canonical and legacy audit hashes with browser history support'
);
$check(
    strpos($viewSource, '.ard-tabs-shell { overflow-x:auto;') !== false
        && strpos($viewSource, '.ard-tabs-shell { position:sticky;') !== false
        && strpos($viewSource, '@media (max-width:767.98px)') !== false,
    'tab navigation is horizontally scrollable and sticky only on mobile'
);
$check(
    strpos($viewSource, '<form') === false
        && strpos($viewSource, '$.ajax') === false
        && strpos($viewSource, 'fetch(') === false
        && strpos($viewSource, 'Export') === false,
    'dashboard has no edit, POST, export, or AJAX surface'
);

$policy = ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json');
$internalPaths = [
    'application/controllers/Audit.php',
    'application/libraries/AuditRoadmapReader.php',
    'application/views/audit/roadmap.php',
    'docs/_NOTE.md',
    'docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md',
    'docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md',
];
$allDenied = true;
foreach ($internalPaths as $internalPath) {
    $allDenied = $allDenied && $policy->included($internalPath) && $policy->denied($internalPath);
}
$check($allDenied, 'package policy excludes notes, dated docs, and every audit dashboard PHP surface');
$check(
    !$policy->included('.codex/internal_audit_dashboard.enabled')
        && $policy->denied('.codex/internal_audit_dashboard.enabled'),
    'internal enable marker is outside package scope and explicitly denied'
);
$check(
    $policy->included('docs/customer-guide.md') && !$policy->denied('docs/customer-guide.md'),
    'package policy still allows non-internal customer documentation'
);

echo 'AUDIT ROADMAP DASHBOARD ' . ($failures === [] ? 'PASS' : 'FAIL')
    . ' passed=' . ($checks - count($failures)) . ' failed=' . count($failures) . PHP_EOL;
exit($failures === [] ? 0 : 1);
