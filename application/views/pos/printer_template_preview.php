<?php
$row = is_array($row ?? null) ? $row : [];
$payload = is_array($payload ?? null) ? $payload : [];
$preview = is_array($preview ?? null) ? $preview : [];
$previewPrinters = is_array($preview_printers ?? null) ? $preview_printers : [];
$selectedPrinterId = (int)($selected_printer_id ?? 0);
?>
<style>
  .printer-preview-page{display:grid;grid-template-columns:380px minmax(0,1fr);gap:1rem;align-items:start}
  .printer-preview-card{border:1px solid rgba(188,44,69,.12);border-radius:22px;background:#fff;box-shadow:0 14px 34px rgba(71,34,34,.08)}
  .printer-preview-shell{border:1px solid rgba(188,44,69,.12);border-radius:22px;background:linear-gradient(180deg,#fff5ef 0%,#fff 100%);padding:1rem}
  .printer-preview-stage{border:1px solid rgba(188,44,69,.12);border-radius:18px;background:#f8ebe2;padding:1rem}
  .printer-paper{width:392px;max-width:100%;margin:0 auto;background:#111827;color:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 18px 30px rgba(17,24,39,.18)}
  .printer-paper pre{margin:0;background:transparent;color:#fff;font-family:Consolas,Monaco,monospace;white-space:pre-wrap;line-height:1.4;font-size:.93rem}
  .printer-chip{display:inline-flex;align-items:center;padding:.35rem .7rem;border-radius:999px;background:#f6eee8;color:#7f5d4f;font-size:.74rem;font-weight:700;margin:.2rem .3rem .2rem 0}
  @media (max-width: 1200px){.printer-preview-page{grid-template-columns:1fr}}
</style>
<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Preview Template Printer POS</h4>
      <p class="fin-page-subtitle mb-0"><?= html_escape(($row['template_name'] ?? '-') . ' • ' . ($row['template_code'] ?? '-')) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= site_url('pos/printers/templates/edit/' . (int)($row['id'] ?? 0)) ?>" class="btn btn-outline-primary">Edit Live</a>
      <a href="<?= site_url('pos/printers') ?>" class="btn btn-outline-secondary">Kembali</a>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>

  <div class="printer-preview-page">
    <div class="d-grid gap-3">
      <div class="card printer-preview-card"><div class="card-body p-4">
        <div class="small text-uppercase fw-bold text-muted mb-2">Template</div>
        <div class="fw-semibold mb-1"><?= html_escape($row['template_name'] ?? '-') ?></div>
        <div class="text-muted small mb-3"><?= html_escape($row['template_code'] ?? '-') ?> • <?= html_escape($preview['document_type_label'] ?? '-') ?></div>
        <div class="d-flex flex-wrap">
          <span class="printer-chip"><?= !empty($row['is_default']) ? 'Default' : 'Non Default' ?></span>
          <span class="printer-chip"><?= !empty($row['is_active']) ? 'Aktif' : 'Nonaktif' ?></span>
          <span class="printer-chip">Divisi <?= html_escape($payload['division_filter'] ?? 'ALL') ?></span>
        </div>
      </div></div>

      <div class="card printer-preview-card"><div class="card-body p-4">
        <form method="get" class="row g-3">
          <div class="col-12">
            <label class="form-label">Preview di Printer</label>
            <select class="form-select" name="printer_id" onchange="this.form.submit()">
              <?php foreach ($previewPrinters as $printer): ?>
                <option value="<?= (int)$printer['id'] ?>" <?= $selectedPrinterId === (int)$printer['id'] ? 'selected' : '' ?>><?= html_escape(($printer['printer_role'] ?? 'CUSTOM') . ' • ' . ($printer['printer_name'] ?? '-') . ' • ' . ($printer['paper_width_mm'] ?? 80) . 'mm') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 small text-muted">Ganti printer untuk melihat hasil template di lebar kertas dan jumlah karakter yang berbeda.</div>
        </form>
      </div></div>
    </div>

    <div class="printer-preview-shell">
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
          <div class="small text-uppercase fw-bold text-muted">Simulasi Cetak</div>
          <h5 class="mb-0 mt-1"><?= html_escape($preview['summary']['printer_role'] ?? 'CUSTOM') ?> • <?= html_escape($preview['summary']['printer_name'] ?? '-') ?></h5>
        </div>
        <span class="badge text-bg-light border"><?= (int)($preview['paper_width_mm'] ?? 80) ?>mm / <?= (int)($preview['chars_per_line'] ?? 48) ?> cpl</span>
      </div>
      <div class="printer-preview-stage">
        <div class="printer-paper" style="width:<?= ((int)($preview['paper_width_mm'] ?? 80) === 58) ? 286 : 392 ?>px;">
          <?php if (!empty($preview['logo_url'])): ?>
            <div style="text-align:center;margin-bottom:12px;"><img src="<?= html_escape($preview['logo_url']) ?>" alt="Logo" style="max-height:58px;max-width:100%;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.22));"></div>
          <?php endif; ?>
          <pre><?= html_escape(implode("\n", (array)($preview['lines'] ?? []))) ?></pre>
        </div>
      </div>
    </div>
  </div>
</div>
