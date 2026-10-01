<?php
$tabScope = strtoupper(trim((string)($tab_scope ?? 'WAREHOUSE')));
$activeTab = strtolower(trim((string)($active_tab ?? 'stock')));

if (!in_array($tabScope, ['WAREHOUSE', 'DIVISION'], true)) {
    return;
}

$tabClass = static function (string $tabKey) use ($activeTab): string {
    return 'stock-scope-tab' . ($activeTab === $tabKey ? ' is-active' : '');
};

if ($tabScope === 'DIVISION') {
    $links = [
        ['key' => 'daily_recon',    'label' => 'Daily Recon',            'url' => site_url('inventory/stock/daily-recon/division')],
        ['key' => 'daily_matrix',   'label' => 'Daily Material Matrix',  'url' => site_url('inventory-material-daily')],
        ['key' => 'stock',          'label' => 'Stok Bahan Baku Live',   'url' => site_url('inventory/stock/division')],
        ['key' => 'daily',          'label' => 'Stok Bahan Baku Bulanan','url' => site_url('inventory/stock/division/daily')],
        ['key' => 'adjustment',     'label' => 'Adjustment Bahan Baku',  'url' => site_url('inventory/stock/adjustment/division')],
        ['key' => 'transfer',       'label' => 'Mutasi Bahan Baku',      'url' => site_url('inventory/stock/transfer/division')],
        ['key' => 'movement',       'label' => 'Log Bahan Baku',         'url' => site_url('inventory/stock/division/movement')],
        ['key' => 'stok_awal',      'label' => 'Stok Awal Bahan Baku',   'url' => site_url('inventory/stock/stok-awal/division')],
        ['key' => 'opening',        'label' => 'Opening Manual Bahan Baku', 'url' => site_url('inventory/stock/opening/division')],
        ['key' => 'opname_monthly', 'label' => 'Opname Bahan Baku',     'url' => site_url('inventory/stock/opname/division/monthly')],
        ['key' => 'lot',            'label' => 'Lot Bahan Baku',         'url' => site_url('inventory/stock/division/lot')],
        ['key' => 'fifo_audit',     'label' => 'FIFO Audit Bahan Baku',  'url' => site_url('inventory/fifo-audit')],
        ['key' => 'compare',        'label' => 'Audit Bahan Baku',       'url' => site_url('inventory/stock/division/reconcile')],
    ];
} else {
    $links = [
        ['key' => 'daily_matrix',  'label' => 'Inventory Warehouse Daily',                 'url' => site_url('inventory-warehouse-daily')],
        ['key' => 'stock',         'label' => 'Stok Gudang',                               'url' => site_url('inventory/stock/warehouse')],
        ['key' => 'daily',         'label' => 'Stok Bulanan / Snapshot Harian Gudang',     'url' => site_url('inventory/stock/warehouse/daily')],
        ['key' => 'movement',      'label' => 'Keluar Masuk Stok Gudang',                  'url' => site_url('inventory/stock/warehouse/movement')],
        ['key' => 'stok_awal',     'label' => 'Stok Awal Gudang',                          'url' => site_url('inventory/stock/stok-awal/warehouse')],
        ['key' => 'opening',       'label' => 'Opening Manual Gudang',                     'url' => site_url('inventory/stock/opening/warehouse')],
        ['key' => 'adjustment',    'label' => 'Adjustment Stok Gudang',                    'url' => site_url('inventory/stock/adjustment/warehouse')],
        ['key' => 'opname_monthly','label' => 'Opname Bulanan Gudang',                     'url' => site_url('inventory/stock/opname/warehouse/monthly')],
    ];
}

?>
<style>
  .stock-scope-tabs { display:grid; grid-template-columns:auto minmax(0,1fr); align-items:start; gap:.5rem; margin:0 0 .65rem; }
  .stock-scope-tabs__label { min-width:86px; padding-top:.38rem; color:#7a6d62; font-size:.72rem; font-weight:800; letter-spacing:.055em; text-transform:uppercase; }
  .stock-scope-tabs__list { display:flex; gap:.42rem; min-width:0; overflow-x:auto; padding:.08rem .08rem .35rem; scrollbar-width:thin; }
  .stock-scope-tab { display:inline-flex; flex:0 0 auto; align-items:center; min-height:32px; border:1px solid #eadbd2; border-radius:9px; background:#fffaf7; color:#6e5147; padding:.4rem .66rem; font-size:.76rem; font-weight:700; line-height:1.15; text-decoration:none; transition:background .15s ease,border-color .15s ease,color .15s ease,box-shadow .15s ease; }
  .stock-scope-tab:hover { border-color:#bc8270; background:#fff1e9; color:#5b2419; }
  .stock-scope-tab:focus-visible { outline:3px solid rgba(165,80,53,.25); outline-offset:2px; }
  .stock-scope-tab.is-active { border-color:#6a2d3c; background:linear-gradient(135deg,#6a2d3c,#8d4454); box-shadow:0 4px 10px rgba(106,45,60,.22); color:#fff; }
  @media (max-width:575px) { .stock-scope-tabs { grid-template-columns:1fr; gap:.1rem; } .stock-scope-tabs__label { padding-top:0; } .stock-scope-tabs__list { padding-bottom:.45rem; } }
</style>
<nav class="stock-scope-tabs" aria-label="Navigasi stok <?php echo $tabScope === 'DIVISION' ? 'bahan baku' : 'gudang'; ?>">
  <span class="stock-scope-tabs__label"><?php echo $tabScope === 'DIVISION' ? 'Bahan baku' : 'Gudang'; ?></span>
  <div class="stock-scope-tabs__list" role="list">
    <?php foreach ($links as $link): ?>
      <?php $isActive = $activeTab === (string)$link['key']; ?>
      <a href="<?php echo html_escape((string)$link['url']); ?>" class="<?php echo $tabClass((string)$link['key']); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo html_escape((string)$link['label']); ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php if ($tabScope === 'WAREHOUSE' && in_array($activeTab, ['lot', 'fifo_audit'], true)): ?>
  <nav class="stock-scope-tabs" aria-label="Audit stok gudang">
    <span class="stock-scope-tabs__label">Audit</span>
    <div class="stock-scope-tabs__list" role="list">
      <a href="<?php echo html_escape(site_url('inventory/stock/warehouse/lot')); ?>" class="<?php echo $tabClass('lot'); ?>"<?php echo $activeTab === 'lot' ? ' aria-current="page"' : ''; ?>>Audit Profil Gudang</a>
      <a href="<?php echo html_escape(site_url('inventory/fifo-audit?scope=WAREHOUSE')); ?>" class="<?php echo $tabClass('fifo_audit'); ?>"<?php echo $activeTab === 'fifo_audit' ? ' aria-current="page"' : ''; ?>>FIFO Audit Gudang</a>
    </div>
  </nav>
<?php endif; ?>
