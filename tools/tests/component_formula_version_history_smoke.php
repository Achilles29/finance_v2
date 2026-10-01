<?php
declare(strict_types=1);

// Source contract only: validates immutable formula history without loading
// CodeIgniter, a database, production data, or POS Mobile/APK code.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$model = (string)file_get_contents($root . '/application/models/Production_model.php');
$controller = (string)file_get_contents($root . '/application/controllers/Production.php');
$detailView = (string)file_get_contents($root . '/application/views/production/component_formula_detail.php');
$migration = (string)file_get_contents($root . '/sql/2026-09-06a_component_formula_version_history.sql');
$baseline = (string)file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
$catalog = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$block = static function (string $source, string $method): string {
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        $name = '';
        for ($j = $i + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name = $tokens[$j][1]; break; }
            if ($tokens[$j] === '(') break;
        }
        if ($name !== $method) continue;
        $depth = 0; $opened = false; $result = '';
        for ($j = $i; $j < count($tokens); $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $result .= $text;
            if ($text === '{') { $opened = true; $depth++; }
            if ($text === '}' && $opened && --$depth === 0) return $result;
        }
    }
    return '';
};

$quoted = chr(96);
$check(strpos($migration, 'CREATE TABLE IF NOT EXISTS ' . $quoted . 'mst_component_formula_version' . $quoted) !== false, 'managed migration creates immutable formula version headers');
$check(strpos($migration, 'CREATE TABLE IF NOT EXISTS ' . $quoted . 'mst_component_formula_version_line' . $quoted) !== false && strpos($migration, 'ON DELETE CASCADE') !== false, 'managed migration creates immutable version lines owned by their header');
$check(strpos($migration, 'original_line_id') !== false && strpos($migration, 'FOREIGN KEY (' . $quoted . 'original_line_id' . $quoted . ')') === false, 'historical line identity is retained without a foreign key to mutable live rows');
$check(substr_count($baseline, 'CREATE TABLE ' . $quoted . 'mst_component_formula_version' . $quoted) === 1 && substr_count($baseline, 'CREATE TABLE ' . $quoted . 'mst_component_formula_version_line' . $quoted) === 1, 'clean-install baseline contains both formula history tables exactly once');

$versionWriter = $block($model, 'write_component_formula_version');
$check(strpos($versionWriter, "'BASELINE'") !== false && strpos($versionWriter, "'REPLACE'") !== false && strpos($versionWriter, "'RESTORE'") !== false, 'version writer accepts baseline, replacement, and restore snapshots');
$check(strpos($versionWriter, "insert('mst_component_formula_version'") !== false && strpos($versionWriter, "insert('mst_component_formula_version_line'") !== false, 'version writer persists header and immutable line snapshots');
$baselineWriter = $block($model, 'ensure_component_formula_baseline_version');
$check(strpos($baselineWriter, '$beforeRows === []') !== false && strpos($baselineWriter, "'BASELINE'") !== false, 'first change preserves a non-empty pre-existing formula as baseline');
$versionsReader = $block($model, 'component_formula_versions');
$check(strpos($versionsReader, "order_by('v.version_no', 'DESC')") !== false && strpos($versionsReader, 'auth_user u') !== false, 'timeline reads newest versions with accountable actor where available');
$show = $block($controller, 'component_formula_show');
$check(strpos($show, "'versions' => " . '$this->Production_model->component_formula_versions') !== false, 'formula detail controller supplies history to the view');
$check(strpos($detailView, 'Riwayat versi formula') !== false && strpos($detailView, 'Pulihkan versi formula') !== false, 'detail UI exposes an explicit, protected restore action');

$entry = null;
foreach (($catalog['migrations'] ?? []) as $candidate) {
    if (($candidate['id'] ?? '') === '2026-09-06a-component-formula-version-history') { $entry = $candidate; break; }
}
$check(is_array($entry) && ($entry['path'] ?? '') === 'sql/2026-09-06a_component_formula_version_history.sql' && ($entry['classification'] ?? '') === 'schema', 'history migration is registered as managed schema');
$check(strpos($model, 'Pos_mobile') === false && strpos($controller, 'Pos_mobile') === false, 'formula history does not alter POS Mobile/APK contracts');

echo 'PASS component-formula-version-history checks=' . $checks . PHP_EOL;
