<?php
$activeTab = strtolower(trim((string)($printer_tab_active ?? 'overview')));
$tabs = [
    ['key' => 'overview', 'label' => 'Ringkasan', 'url' => site_url('pos/printers')],
    ['key' => 'connections', 'label' => 'Koneksi Printer', 'url' => site_url('pos/printers/connections')],
    ['key' => 'general', 'label' => 'Tampilan Umum', 'url' => site_url('pos/printers/general')],
    ['key' => 'layouts', 'label' => 'Layout Dokumen', 'url' => site_url('pos/printers/layouts')],
    ['key' => 'rules', 'label' => 'Aturan Cetak', 'url' => site_url('pos/printers/rules')],
    ['key' => 'preview', 'label' => 'Preview', 'url' => site_url('pos/printers/preview-live')],
    ['key' => 'monitor', 'label' => 'Monitor', 'url' => site_url('pos/printers/monitor')],
    ['key' => 'reviews', 'label' => 'Ulasan Pelanggan', 'url' => site_url('pos/customer-reviews')],
    ['key' => 'guide', 'label' => 'Panduan', 'url' => site_url('pos/printers/guide')],
];
?>
<style>
  .printer-tab-label{min-width:104px;font-size:.74rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#7a6d62;padding-top:.35rem}
  .printer-tab-pill{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:.48rem .95rem;border-radius:12px;font-size:.86rem;font-weight:700;text-decoration:none;border:1px solid #d9c7bc;background:#fffaf6;color:#6a5c54}
  .printer-tab-pill.is-active{background:#b4233c;border-color:#b4233c;color:#fff;box-shadow:0 10px 24px rgba(180,35,60,.16)}
</style>
<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
  <div class="printer-tab-label">Printer POS</div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($tabs as $tab): ?>
      <a href="<?= $tab['url'] ?>" class="printer-tab-pill <?= $activeTab === $tab['key'] ? 'is-active' : '' ?>"><?= html_escape($tab['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
