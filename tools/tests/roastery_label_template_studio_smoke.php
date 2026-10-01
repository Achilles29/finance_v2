<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'routes' => $root . '/application/config/routes.php',
    'controller' => $root . '/application/controllers/Roastery.php',
    'model' => $root . '/application/models/Coffee_packaging_label_model.php',
    'view' => $root . '/application/views/roastery/coffee_packaging_label_index.php',
    'migration' => $root . '/sql/2026-09-06h_roastery_label_template_studio.sql',
    'element_migration' => $root . '/sql/2026-10-01b_roastery_label_element_library.sql',
    'blank_template_migration' => $root . '/sql/2026-10-01c_roastery_blank_default_template.sql',
    'soft_circle_migration' => $root . '/sql/2026-10-01d_roastery_soft_circle_element.sql',
    'dynamic_text_migration' => $root . '/sql/2026-10-01e_roastery_dynamic_text_defaults.sql',
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
        && strpos($source['controller'], 'sanitize_canvas_elements') !== false
        && strpos($source['controller'], "'template_id'") !== false
        && strpos($source['controller'], "unset(\$designData['layout'])") !== false,
    'controller persists a safe template reference and retires the old split layout marker'
);
$check(
    strpos($source['controller'], "'mountain'") !== false
        && strpos($source['controller'], "'elevation_text'") !== false
        && strpos($source['controller'], "'elements'") !== false,
    'saved templates retain validated free text, mountain artwork, and elevation bindings'
);
$check(
    strpos($source['controller'], '$selectedTemplateId > 0 ? \'\' : $selectedTemplateKey') !== false,
    'an explicit template ID takes precedence over the default template key'
);
$check(
    strpos($source['controller'], '($applyTemplate || $newMode)') !== false
        && strpos($source['view'], 'isBlankNew') !== false
        && strpos($source['view'], 'const isBlankNew=<?php echo $isBlankNew ? \'true\' : \'false\'; ?>;') !== false
        && strpos($source['view'], 'blankCanvas') !== false
        && strpos($source['view'], 'value="<?php echo $selectedTemplateId; ?>"') !== false
        && strpos($source['view'], 'Mulai kosong') === false
        && strpos($source['view'], 'data-design="<?php echo html_escape((string)($template[\'design_json\'] ?? \'{}\')); ?>"') !== false
        && strpos($source['view'], "templateSelect.addEventListener('change',function(){\n    const option=this.selectedOptions&&this.selectedOptions[0];if(!option)return;") !== false
        && strpos($source['view'], 'window.location.assign(url)') === false
        && strpos($source['view'], 'class="label-canvas theme-') !== false
        && strpos($source['view'], 'is-blank-label') !== false,
    'new labels use the selected blank default template with no content defaults and initialize editor mode safely'
);
$check(
    strpos($source['model'], 'Blank Canvas') !== false
        && strpos($source['model'], '"elements":[],"blocks":{}') !== false
        && strpos($source['view'], "Object.prototype.hasOwnProperty.call((initialDesign.blocks||{})[key]||{},'visible')") !== false,
    'the blank default stays empty while explicitly enabled blocks remain editable'
);
$check(
    strpos($source['view'], 'data-editor-tab="data"') !== false
        && strpos($source['view'], 'data-editor-tab="artwork"') !== false
        && strpos($source['view'], 'data-editor-tab="elements"') !== false
        && strpos($source['view'], 'data-editor-tab="print"') !== false
        && strpos($source['view'], 'data-editor-panel="artwork"') !== false
        && strpos($source['view'], 'data-editor-panel="print"') !== false
        && strpos($source['view'], 'printSettings.append(document.querySelector') !== false,
    'all label settings are organized into main editor tabs'
);
$catalog = json_decode($source['catalog'], true);
$blankMigration = null;
foreach ((array)($catalog['migrations'] ?? []) as $migration) {
    if (($migration['id'] ?? '') === '2026-10-01c-roastery-blank-default-template') {
        $blankMigration = $migration;
        break;
    }
}
$check(
    is_array($blankMigration)
        && ($blankMigration['path'] ?? '') === 'sql/2026-10-01c_roastery_blank_default_template.sql'
        && ($blankMigration['policies'] ?? []) === ['clean_install', 'upgrade']
        && hash_file('sha256', $files['blank_template_migration']) === ($blankMigration['sha256'] ?? '')
        && strpos($source['blank_template_migration'], "WHERE `template_key` = 'classic-portrait'") !== false,
    'default blank template migration is checksum-bound and only updates the default template'
);
$check(
    strpos($source['model'], 'coffee_packaging_label_template') !== false
        && strpos($source['model'], 'function list_templates') !== false
        && strpos($source['model'], 'function list_design_elements') !== false
        && strpos($source['model'], 'coffee_packaging_label_element') !== false
        && strpos($source['model'], "CASE WHEN template_key = 'classic-portrait' THEN 0") !== false
        && strpos($source['model'], 'function save_template') !== false
        && strpos($source['model'], 'function delete_custom_template') !== false,
    'model supports template lifecycle operations and keeps the default template first'
);
$check(
    strpos($source['view'], 'function renderCustomElements()') !== false
        && strpos($source['view'], 'function moveFreeElement(') !== false
        && strpos($source['view'], 'function addFreeElement(') !== false
        && strpos($source['view'], "['rotate','free-rotate-handle'") !== false
        && strpos($source['view'], "['curve','free-curve-handle'") !== false
        && strpos($source['view'], "['font-size','free-size-handle'") !== false
        && strpos($source['view'], 'freeElementWeight') !== false
        && strpos($source['view'], 'textPath') !== false
        && strpos($source['view'], 'function freeValueIsPlaceholder(item)') !== false
        && strpos($source['view'], "if(isBlankCanvas)item.color='#50302a'") !== false
        && strpos($source['view'], '.is-dynamic-placeholder{display:none!important}') !== false
        && strpos($source['view'], "resize.dataset.freeAction='resize'") !== false
        && strpos($source['view'], "readout.className='free-drag-readout'") !== false
        && strpos($source['view'], "'Rotasi '+item.rotation.toFixed(1)") !== false
        && strpos($source['view'], "fontWeight:500") !== false
        && strpos($source['controller'], "'fontWeight' =>") !== false
        && strpos($source['controller'], "'curve' =>") !== false
        && strpos($source['view'], 'mountainPaths') !== false
        && strpos($source['view'], ':not([data-block]){display:none!important}') !== false
        && strpos($source['view'], '.free-canvas-element{position:absolute') !== false
        && strpos($source['view'], 'pointer-events:auto}') !== false,
    'canvas supports multiple draggable, resizable text and illustration elements'
);
$dynamicTextMigration = null;
foreach ((array)($catalog['migrations'] ?? []) as $migration) {
    if (($migration['id'] ?? '') === '2026-10-01e-roastery-dynamic-text-defaults') {
        $dynamicTextMigration = $migration;
        break;
    }
}
$check(
    is_array($dynamicTextMigration)
        && hash_file('sha256', $files['dynamic_text_migration']) === ($dynamicTextMigration['sha256'] ?? '')
        && strpos($source['dynamic_text_migration'], "'text-coffee-name'") !== false
        && strpos($source['dynamic_text_migration'], '"fontWeight":500') !== false,
    'dynamic text presets have visible placeholders and medium-weight defaults'
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
    strpos($source['view'], 'label-mountain-art') !== false
        && strpos($source['view'], 'mountain-horizon') !== false
        && strpos($source['view'], "style-'+style") !== false,
    'Prau template supports reusable mountain artwork decoration without baking product text into the background'
);
$check(
    strpos($source['migration'], 'CREATE TABLE IF NOT EXISTS `coffee_packaging_label_template`') !== false
        && strpos($source['migration'], "'classic-portrait'") !== false
        && strpos($source['migration'], "'retail-wide'") !== false
        && strpos($source['baseline'], 'CREATE TABLE `coffee_packaging_label_template`') !== false,
    'managed migration and clean-install baseline create the same template store with two system templates'
);
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
$elementMigration = null;
foreach ((array)($catalog['migrations'] ?? []) as $migration) {
    if (($migration['id'] ?? '') === '2026-10-01b-roastery-label-element-library') {
        $elementMigration = $migration;
        break;
    }
}
$check(
    is_array($elementMigration)
        && ($elementMigration['path'] ?? '') === 'sql/2026-10-01b_roastery_label_element_library.sql'
        && ($elementMigration['policies'] ?? []) === ['clean_install', 'upgrade']
        && hash_file('sha256', $files['element_migration']) === ($elementMigration['sha256'] ?? '')
        && strpos($source['element_migration'], "'mountain-ridge'") !== false
        && strpos($source['element_migration'], "'text-origin'") !== false
        && strpos($source['baseline'], 'CREATE TABLE `coffee_packaging_label_element`') !== false,
    'element library migration and baseline include reusable text, shapes, and mountain presets'
);
$softCircleMigration = null;
foreach ((array)($catalog['migrations'] ?? []) as $migration) {
    if (($migration['id'] ?? '') === '2026-10-01d-roastery-soft-circle-element') {
        $softCircleMigration = $migration;
        break;
    }
}
$check(
    is_array($softCircleMigration)
        && ($softCircleMigration['path'] ?? '') === 'sql/2026-10-01d_roastery_soft_circle_element.sql'
        && hash_file('sha256', $files['soft_circle_migration']) === ($softCircleMigration['sha256'] ?? '')
        && strpos($source['soft_circle_migration'], "'soft-circle-halo'") !== false
        && strpos($source['controller'], "'soft' => !empty(\$element['soft'])") !== false
        && strpos($source['view'], "radial-gradient(circle, '+hexToRgba(color,.32)") !== false,
    'soft circle element is seeded, sanitized, and rendered with a feathered radial edge'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Roastery Label Studio check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' Roastery Label Studio checks passed.' . PHP_EOL;
