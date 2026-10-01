<?php
$active = (string)($asset_nav_active ?? '');
$items = [
  'items' => ['label' => 'Daftar Aset', 'url' => site_url('asset-management'), 'icon' => 'ri-list-check-2'],
  'changes' => ['label' => 'Perubahan Data', 'url' => site_url('asset-management/changes'), 'icon' => 'ri-file-edit-line'],
  'damage' => ['label' => 'Lapor Rusak', 'url' => site_url('asset-management/damage'), 'icon' => 'ri-alert-line'],
  'recon' => ['label' => 'Rekon Bulanan', 'url' => site_url('asset-management/recon'), 'icon' => 'ri-calendar-check-line'],
  'labels' => ['label' => 'QR Label', 'url' => site_url('asset-management/labels'), 'icon' => 'ri-fingerprint-line'],
  'transfer' => ['label' => 'Mutasi', 'url' => site_url('asset-management/transfer'), 'icon' => 'ri-arrow-left-right-line'],
  'handover' => ['label' => 'Serah Terima', 'url' => site_url('asset-management/handover'), 'icon' => 'ri-user-follow-line'],
  'maintenance' => ['label' => 'Maintenance', 'url' => site_url('asset-management/maintenance'), 'icon' => 'ri-tools-line'],
  'disposal' => ['label' => 'Disposal', 'url' => site_url('asset-management/disposal'), 'icon' => 'ri-delete-bin-line'],
  'depreciation' => ['label' => 'Penyusutan', 'url' => site_url('asset-management/depreciation'), 'icon' => 'ri-line-chart-line'],
];
?>
<style>
.asset-module-nav{display:grid;grid-template-columns:92px minmax(0,1fr);gap:.5rem;margin-bottom:1rem}
.asset-module-nav-label{padding-top:.48rem;color:#7a6d62;font-size:.72rem;font-weight:800;letter-spacing:.055em;text-transform:uppercase}
.asset-module-nav-list{display:flex;gap:.42rem;min-width:0;overflow-x:auto;padding:.08rem .08rem .35rem;scrollbar-width:thin}
.asset-module-nav-link{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:40px;padding:.58rem .72rem;border:1px solid #d8c9bd;border-radius:8px;background:#fff;color:#5a4a40;font-weight:700;font-size:.86rem;line-height:1.15;text-align:center;text-decoration:none;box-shadow:0 6px 18px rgba(35,24,18,.035)}
.asset-module-nav-link:hover{border-color:#18745c;color:#18745c;background:#f7fbf9;text-decoration:none}
.asset-module-nav-link.is-active{border-color:#18745c;background:#18745c;color:#fff;box-shadow:0 10px 22px rgba(24,116,92,.18)}
.asset-module-nav-link:focus-visible{outline:3px solid rgba(24,116,92,.24);outline-offset:2px}
.asset-module-nav-link i{font-size:1.05rem;line-height:1;display:inline-flex;align-items:center;justify-content:center}
.asset-filter-actions{display:flex;align-items:stretch;justify-content:flex-end;gap:.45rem}
.asset-filter-actions .btn{flex:0 0 42px;width:42px;height:42px;padding:0;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}
.asset-filter-actions .btn i{display:block;font-size:1.15rem;line-height:1}
@media (max-width:575.98px){.asset-module-nav{grid-template-columns:1fr;gap:.1rem}.asset-module-nav-label{padding-top:0}.asset-module-nav-list{padding-bottom:.45rem}.asset-module-nav-link{font-size:.8rem;padding:.5rem .56rem;white-space:nowrap}.asset-filter-actions .btn{min-width:0}}
</style>
<nav class="asset-module-nav" aria-label="Navigasi manajemen aset">
  <span class="asset-module-nav-label">Aset</span>
  <div class="asset-module-nav-list" role="list">
  <?php foreach ($items as $key => $item): ?>
    <?php $isActive = $active === $key; ?>
    <a class="asset-module-nav-link <?= $isActive ? 'is-active' : '' ?>" href="<?= html_escape((string)$item['url']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
      <i class="ri <?= html_escape($item['icon']) ?>"></i><?= html_escape($item['label']) ?>
    </a>
  <?php endforeach; ?>
  </div>
</nav>
