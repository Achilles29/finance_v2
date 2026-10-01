<?php
$extraTabActive = $extraTabActive ?? '';
$extraTabs = [
    'workspace' => ['label' => 'Workspace', 'url' => site_url('master/relation/product-extra-workspace'), 'icon' => 'ri-dashboard-horizontal-line'],
    'master-extra' => ['label' => 'Master Extra', 'url' => site_url('master/extra'), 'icon' => 'ri-add-circle-line'],
    'extra-group' => ['label' => 'Group Extra', 'url' => site_url('master/extra-group'), 'icon' => 'ri-layout-grid-line'],
    'extra-link' => ['label' => 'Extra ke Group', 'url' => site_url('master/relation/extra-item-group'), 'icon' => 'ri-links-line'],
    'product-extra' => ['label' => 'Mapping Produk', 'url' => site_url('master/relation/product-extra'), 'icon' => 'ri-links-line'],
    'group-checklist' => ['label' => 'Checklist Group', 'url' => site_url('master/relation/extra-group'), 'icon' => 'ri-checkbox-multiple-line'],
];
?>
<style>
  .extra-workspace-tabs { display:grid; grid-template-columns:88px minmax(0,1fr); gap:.5rem; align-items:start; margin-bottom:1rem; }
  .extra-workspace-tabs__label { padding-top:.42rem; color:#7a6d62; font-size:.72rem; font-weight:800; letter-spacing:.055em; text-transform:uppercase; }
  .extra-workspace-tabs__list { display:flex; gap:.42rem; min-width:0; overflow-x:auto; padding:.08rem .08rem .35rem; scrollbar-width:thin; }
  .extra-workspace-tabs__link { display:inline-flex; flex:0 0 auto; align-items:center; min-height:34px; border:1px solid #eadbd2; border-radius:9px; background:#fffaf7; color:#6e5147; padding:.4rem .66rem; font-size:.76rem; font-weight:700; line-height:1.15; text-decoration:none; }
  .extra-workspace-tabs__link:hover { border-color:#bc8270; background:#fff1e9; color:#5b2419; }
  .extra-workspace-tabs__link.is-active { border-color:#6a2d3c; background:linear-gradient(135deg,#6a2d3c,#8d4454); box-shadow:0 4px 10px rgba(106,45,60,.22); color:#fff; }
  .extra-workspace-tabs__link:focus-visible { outline:3px solid rgba(165,80,53,.25); outline-offset:2px; }
  @media (max-width:575px) { .extra-workspace-tabs { grid-template-columns:1fr; gap:.1rem; } .extra-workspace-tabs__label { padding-top:0; } .extra-workspace-tabs__list { padding-bottom:.45rem; } }
</style>
<nav class="extra-workspace-tabs" aria-label="Navigasi Master Extra">
  <span class="extra-workspace-tabs__label">Master Extra</span>
  <div class="extra-workspace-tabs__list" role="list">
      <?php foreach ($extraTabs as $key => $tab): ?>
        <?php $isActive = $extraTabActive === $key; ?>
        <a href="<?php echo html_escape((string)$tab['url']); ?>" class="extra-workspace-tabs__link<?php echo $isActive ? ' is-active' : ''; ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
          <i class="<?php echo html_escape($tab['icon']); ?> me-1"></i><?php echo html_escape($tab['label']); ?>
        </a>
      <?php endforeach; ?>
  </div>
</nav>
