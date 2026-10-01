<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'routes' => $root . '/application/config/routes.php',
    'controller' => $root . '/application/controllers/Roastery.php',
    'model' => $root . '/application/models/Coffee_packaging_label_model.php',
    'view' => $root . '/application/views/roastery/coffee_packaging_label_index.php',
    'migration' => $root . '/sql/2026-09-06h_roastery_label_template_studio.sql',
    'baseline' => $root . '/sql/baseline/2026-09-05_clean_install_schema.sql',
    'catalog' => $root . '/tools/db/migration_catalog.json',
];
$source = [];
foreach ($files as $key => $path) {
    $source[$key] = (string)@file_get_contents($path);
}

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$check(!in_array('', $source, true), 'Roastery Label Studio implementation files are readable');
$check(
    strpos($source['routes'], "roastery/packaging-labels/templates/save") !== false
        && strpos($source['routes'], "roastery/packaging-labels/templates/delete/(:num)") !== false,
    'template save and delete endpoints are explicitly routed'
);
$check(
    strpos($source['controller'], 'function packaging_label_template_save()') !== false
        && strpos($source['controller'], 'sanitize_template_design_json') !== false
        && strpos($source['controller'], "'template_id'") !== false
        && strpos($source['controller'], "unset(\$designData['layout'])") !== false,
    'controller persists a safe template reference and retires the old split layout marker'
);
$check(
    strpos($source['model'], 'coffee_packaging_label_template') !== false
        && strpos($source['model'], 'function list_templates') !== false
        && strpos($source['model'], "CASE WHEN template_key = 'classic-portrait' THEN 0") !== false
        && strpos($source['model'], 'function save_template') !== false
        && strpos($source['model'], 'function delete_custom_template') !== false,
    'model supports template lifecycle operations and keeps the default template first'
);
$check(
    strpos($source['view'], 'data-element-toggle="logo"') !== false
        && strpos($source['view'], 'function syncElementToggles()') !== false
        && strpos($source['view'], 'function beginDrag(') !== false
        && strpos($source['view'], 'function buildPrintSheet()') !== false,
    'editor exposes direct element visibility, drag handling, and shared print-sheet rendering'
);
$check(
    strpos($source['view'], 'notes.forEach((note,idx)=>') !== false
        && strpos($source['view'], 'notes.slice(0,3)') === false,
    'all entered tasting notes are rendered instead of silently truncating after three'
);
$check(
    strpos($source['view'], 'function exportTemplateDesign()') !== false
        && strpos($source['view'], 'saveTemplateForm.submit()') !== false
        && strpos($source['view'], 'template_id" id="templateIdInput') !== false,
    'current layout can be saved as a reusable template and then applied to a label'
);
$check(
    strpos($source['view'], 'id="templateSelect"') !== false
        && strpos($source['view'], 'Default — ') !== false
        && strpos($source['view'], 'id="saveTemplateModal"') !== false
        && strpos($source['view'], 'id="templateNameField"') !== false
        && strpos($source['view'], 'window.prompt') === false
        && strpos($source['view'], 'template-card') === false,
    'template selection is compact, default-first, and saving requires a named dialog instead of browser prompts'
);
$check(
    strpos($source['migration'], 'CREATE TABLE IF NOT EXISTS `coffee_packaging_label_template`') !== false
        && strpos($source['migration'], "'classic-portrait'") !== false
        && strpos($source['migration'], "'retail-wide'") !== false
        && strpos($source['baseline'], 'CREATE TABLE `coffee_packaging_label_template`') !== false,
    'managed migration and clean-install baseline create the same template store with two system templates'
);
$catalog = json_decode($source['catalog'], true);
$candidate = null;
foreach ((array)($catalog['migrations'] ?? []) as $migration) {
    if (($migration['id'] ?? '') === '2026-09-06h-roastery-label-template-studio') {
        $candidate = $migration;
        break;
    }
}
$check(
    is_array($candidate)
        && ($candidate['path'] ?? '') === 'sql/2026-09-06h_roastery_label_template_studio.sql'
        && ($candidate['policies'] ?? []) === ['clean_install', 'upgrade']
        && hash_file('sha256', $files['migration']) === ($candidate['sha256'] ?? ''),
    'Label Studio schema migration is checksum-bound for clean installs and upgrades'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Roastery Label Studio check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' Roastery Label Studio checks passed.' . PHP_EOL;
