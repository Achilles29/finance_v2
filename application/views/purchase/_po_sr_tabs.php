<?php
$poSrActive = (string)($po_sr_active ?? '');
$poSrTabs = [
  ['key' => 'purchase-order', 'label' => 'Purchase Order', 'hint' => 'Monitoring', 'url' => site_url('purchase-orders')],
  ['key' => 'store-request', 'label' => 'Store Request', 'hint' => 'Fulfillment', 'url' => site_url('store-requests')],
  ['key' => 'division-po-sr', 'label' => 'PO / SR Divisi', 'hint' => 'Review', 'url' => site_url('procurement/division-po-sr')],
  ['key' => 'log-purchase', 'label' => 'Log Purchase', 'hint' => 'Histori', 'url' => site_url('purchase-orders/logs')],
  ['key' => 'report-purchase', 'label' => 'Laporan Purchase', 'hint' => 'Ringkasan', 'url' => site_url('purchase-orders/report')],
  ['key' => 'report-sr', 'label' => 'Laporan SR', 'hint' => 'Realisasi', 'url' => site_url('store-requests/report'), 'page' => 'procurement.sr_report.index'],
  ['key' => 'report-materials', 'label' => 'Bahan Baku ke Divisi', 'hint' => 'Purchase + SR', 'url' => site_url('procurement/reports/division-materials'), 'page' => 'procurement.material_report.index'],
  ['key' => 'rebuild-impact', 'label' => 'Rebuild Impact', 'hint' => 'Audit', 'url' => site_url('purchase/rebuild-impact')],
  ['key' => 'receipt-purchase', 'label' => 'Receipt Purchase', 'hint' => 'Inbound', 'url' => site_url('purchase/receipt')],
  ['key' => 'reclassify-profile', 'label' => 'Reclassify Profile', 'hint' => 'Cleanup', 'url' => site_url('purchase/reclassify-profile-domain')],
  ['key' => 'price-history', 'label' => 'Riwayat Harga', 'hint' => 'Tren Item', 'url' => site_url('purchase/item-price-history')],
];
?>

<style>
  .po-sr-nav {
    display: grid;
    grid-template-columns: 92px minmax(0, 1fr);
    align-items: start;
    gap: .5rem;
    margin-bottom: 1rem;
  }
  .po-sr-nav-label { padding-top:.45rem; color:#7a6d62; font-size:.72rem; font-weight:800; letter-spacing:.055em; text-transform:uppercase; }
  .po-sr-nav-list { display:flex; gap:.42rem; min-width:0; overflow-x:auto; padding:.08rem .08rem .35rem; scrollbar-width:thin; }
  .po-sr-nav .po-sr-nav-link {
    display: inline-flex;
    flex:0 0 auto;
    align-items: center;
    gap: .45rem;
    border-radius: 10px;
    font-weight: 700;
    padding: .55rem .85rem;
    border: 1px solid #dfd5cb;
    background: #fff;
    color: #51453d;
    text-decoration: none;
  }
  .po-sr-nav .po-sr-nav-link:hover {
    color: #3f342d;
    border-color: #d9c8bc;
    background: #fff8f3;
  }
  .po-sr-nav .po-sr-nav-link.is-active {
    background: #9f2141;
    border-color: #9f2141;
    color: #fff;
    box-shadow: 0 8px 18px rgba(159, 33, 65, .18);
  }
  .po-sr-nav .po-sr-nav-link:focus-visible { outline:3px solid rgba(159,33,65,.24); outline-offset:2px; }
  .po-sr-nav .po-sr-dot {
    width: .58rem;
    height: .58rem;
    border-radius: .18rem;
    background: #6a5c54;
    display: inline-block;
    flex: 0 0 .58rem;
  }
  .po-sr-nav .po-sr-nav-link.is-active .po-sr-dot {
    background: #fff;
    opacity: .95;
  }
  @media (max-width:575px) { .po-sr-nav { grid-template-columns:1fr; gap:.1rem; } .po-sr-nav-label { padding-top:0; } .po-sr-nav-list { padding-bottom:.45rem; } }
</style>

<nav class="po-sr-nav" aria-label="Navigasi Purchase dan Store Request">
  <span class="po-sr-nav-label">Purchase</span>
  <div class="po-sr-nav-list" role="list">
  <?php foreach ($poSrTabs as $tab): ?>
    <?php if (!empty($tab['page']) && empty($user_perms[$tab['page']]['can_view']) && empty($current_user['is_superadmin'])) { continue; } ?>
    <?php $isActive = $poSrActive === $tab['key']; ?>
    <a href="<?php echo html_escape((string)$tab['url']); ?>" class="po-sr-nav-link <?php echo $isActive ? 'is-active' : ''; ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
      <span class="po-sr-dot" aria-hidden="true"></span>
      <span><?php echo html_escape($tab['label']); ?></span>
    </a>
  <?php endforeach; ?>
  </div>
</nav>
