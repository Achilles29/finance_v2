<?php
$activeTab = strtolower(trim((string)($component_tab_active ?? 'stock')));
$groups = [
    [
        'label' => 'Master',
        'links' => [
            ['key' => 'category', 'label' => 'Kategori', 'url' => site_url('production/component-categories')],
            ['key' => 'master', 'label' => 'Master Component', 'url' => site_url('production/component-masters')],
            ['key' => 'formula', 'label' => 'Formula', 'url' => site_url('production/component-formulas')],
            ['key' => 'variable-cost', 'label' => 'Variable Cost', 'url' => site_url('production/component-cost-variables')],
        ],
    ],
    [
        'label' => 'Operasional',
        'links' => [
            ['key' => 'batch',       'label' => 'Batch Produksi',   'url' => site_url('production/component-batches')],
            ['key' => 'daily',       'label' => 'Daily Matrix',     'url' => site_url('production/component-daily')],
            ['key' => 'daily_recon', 'label' => 'Daily Recon',      'url' => site_url('production/component-daily-recon')],
            ['key' => 'stock',       'label' => 'Stok Base/Prepare','url' => site_url('production/component-stock')],
            ['key' => 'monthly',     'label' => 'Stok Bulanan',     'url' => site_url('production/component-monthly')],
            ['key' => 'adjustment',      'label' => 'Adjustment',       'url' => site_url('production/component-adjustments')],
            ['key' => 'opening_monthly', 'label' => 'Opening Bulanan',  'url' => site_url('production/component-opening-monthly')],
            ['key' => 'opening',         'label' => 'Opening',          'url' => site_url('production/component-openings')],
            ['key' => 'movement',    'label' => 'Mutasi',           'url' => site_url('production/component-movements')],
            ['key' => 'lot',         'label' => 'Lot FIFO',         'url' => site_url('production/component-lots')],
            ['key' => 'reconcile',   'label' => 'Reconcile',        'url' => site_url('production/component-reconcile')],
            ['key' => 'opname',      'label' => 'Opname',           'url' => site_url('production/component-opname')],
        ],
    ],
];

$tabClass = static function (string $key) use ($activeTab): string {
    return 'component-workbench-tab' . ($activeTab === $key ? ' is-active' : '');
};
?>

<style>
  .component-workbench-tabs { display:grid; grid-template-columns:88px minmax(0,1fr); align-items:start; gap:.5rem; margin-bottom:.42rem; }
  .component-workbench-tabs__links { display:flex; gap:.42rem; min-width:0; overflow-x:auto; padding:.08rem .08rem .35rem; scrollbar-width:thin; }
  .component-workbench-group + .component-workbench-group {
    margin-top: .1rem;
  }
  .component-workbench-label {
    min-width: 88px;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
    padding-top: .38rem;
  }
  .component-workbench-tab {
    display:inline-flex; flex:0 0 auto; align-items:center; min-height:32px;
    border:1px solid #eadbd2; border-radius:9px; background:#fffaf7; color:#6e5147;
    padding:.4rem .66rem; font-size:.76rem; font-weight:700; line-height:1.15;
    text-decoration:none; transition:background .15s ease,border-color .15s ease,color .15s ease,box-shadow .15s ease;
  }
  .component-workbench-tab:hover { border-color:#bc8270; background:#fff1e9; color:#5b2419; }
  .component-workbench-tab:focus-visible { outline:3px solid rgba(165,80,53,.25); outline-offset:2px; }
  .component-workbench-tab.is-active { border-color:#6a2d3c; background:linear-gradient(135deg,#6a2d3c,#8d4454); box-shadow:0 4px 10px rgba(106,45,60,.22); color:#fff; }
  @media (max-width:575px) {
    .component-workbench-tabs { grid-template-columns:1fr; gap:.1rem; }
    .component-workbench-label { padding-top:0; }
    .component-workbench-tabs__links { padding-bottom:.45rem; }
  }
  .component-action-stack {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: nowrap;
    justify-content: center;
  }
  .component-action-cell {
    white-space: nowrap;
    width: 1%;
    text-align: center;
  }
  .component-action-btn {
    flex-shrink: 0;
    width: 38px !important;
    min-width: 38px !important;
    height: 38px !important;
    padding: 0 !important;
    border-radius: 10px !important;
  }
  .component-action-btn i,
  .component-action-btn [class^="ri-"],
  .component-action-btn [class*=" ri-"] {
    font-size: 1.12rem !important;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
</style>

<?php foreach ($groups as $group): ?>
  <nav class="component-workbench-tabs component-workbench-group" aria-label="Navigasi Component <?php echo html_escape((string)$group['label']); ?>">
    <div class="component-workbench-label"><?php echo html_escape((string)$group['label']); ?></div>
    <div class="component-workbench-tabs__links" role="list">
      <?php foreach ($group['links'] as $link): ?>
        <?php $isActive = $activeTab === (string)$link['key']; ?>
        <a href="<?php echo html_escape((string)$link['url']); ?>" class="<?php echo $tabClass((string)$link['key']); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo html_escape((string)$link['label']); ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
<?php endforeach; ?>
