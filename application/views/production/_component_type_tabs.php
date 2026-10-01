<?php
$baseUrl    = (string)($component_type_base_url ?? site_url('production/component-stock'));
$filters    = is_array($component_type_filters ?? null) ? $component_type_filters : [];
$activeType = strtoupper(trim((string)($component_type_active ?? '')));
$tabs = [
    ''        => 'Semua',
    'BASE'    => 'Base',
    'PREPARE' => 'Prepare',
];

$buildUrl = static function (string $type) use ($baseUrl, $filters): string {
    $params = [];
    foreach ($filters as $key => $value) {
        if ($key === 'type') continue;
        if ($value === null || $value === '' || $value === 0 || $value === '0') continue;
        $params[$key] = $value;
    }
    if ($type !== '') $params['type'] = $type;
    return $baseUrl . (!empty($params) ? ('?' . http_build_query($params)) : '');
};
?>

<nav class="component-workbench-tabs component-workbench-group" aria-label="Filter tipe Component"
     style="margin-top:.1rem; padding-top:.45rem; border-top:1px solid #e9e3dc;">
  <span class="component-workbench-label">Tipe</span>
  <div class="component-workbench-tabs__links" role="list">
    <?php foreach ($tabs as $typeValue => $label): ?>
      <?php $isActive = $activeType === (string)$typeValue; ?>
      <a href="<?php echo html_escape($buildUrl((string)$typeValue)); ?>"
         class="component-workbench-tab<?php echo $isActive ? ' is-active' : ''; ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
        <?php echo html_escape((string)$label); ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
