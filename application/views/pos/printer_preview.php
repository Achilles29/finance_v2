<?php
$row = is_array($row ?? null) ? $row : [];
$templates = is_array($templates ?? null) ? $templates : [];
$selectedTemplate = is_array($selected_template ?? null) ? $selected_template : [];
$preview = is_array($preview ?? null) ? $preview : [];
$autoprint = !empty($autoprint);
$pythonPort = (int)($preview['summary']['python_port'] ?? $row['python_port'] ?? 0);
?>
<style>
  .printer-runtime-page{display:grid;grid-template-columns:380px minmax(0,1fr);gap:1rem;align-items:start}
  .printer-runtime-card{border:1px solid rgba(188,44,69,.12);border-radius:22px;background:#fff;box-shadow:0 14px 34px rgba(71,34,34,.08)}
  .printer-runtime-shell{border:1px solid rgba(188,44,69,.12);border-radius:22px;background:linear-gradient(180deg,#fff5ef 0%,#fff 100%);padding:1rem}
  .printer-runtime-stage{border:1px solid rgba(188,44,69,.12);border-radius:18px;background:#f8ebe2;padding:1rem}
  .printer-runtime-paper{width:392px;max-width:100%;margin:0 auto;background:#111827;color:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 18px 30px rgba(17,24,39,.18)}
  .printer-runtime-paper pre{margin:0;background:transparent;color:#fff;font-family:Consolas,Monaco,monospace;white-space:pre-wrap;line-height:1.4;font-size:.93rem}
  .printer-runtime-chip{display:inline-flex;align-items:center;padding:.35rem .7rem;border-radius:999px;background:#f6eee8;color:#7f5d4f;font-size:.74rem;font-weight:700;margin:.2rem .3rem .2rem 0}
  @media (max-width: 1200px){.printer-runtime-page{grid-template-columns:1fr}}
</style>
<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Preview Printer POS</h4>
      <p class="fin-page-subtitle mb-0"><?= html_escape(($row['device_name'] ?? '-') . ' • ' . ($row['device_code'] ?? '-')) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= site_url('pos/printers') ?>" class="btn btn-outline-secondary">Kembali</a>
      <button type="button" class="btn btn-primary" id="localPrintBtn">Test Print</button>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>

  <div class="printer-runtime-page">
    <div class="d-grid gap-3">
      <div class="card printer-runtime-card"><div class="card-body p-4">
        <div class="small text-uppercase fw-bold text-muted mb-2">Profil Printer</div>
        <table class="table table-sm mb-0">
          <tr><th style="width:42%">Outlet</th><td><?= html_escape($preview['summary']['outlet_name'] ?? 'GLOBAL') ?></td></tr>
          <tr><th>Role / Scope</th><td><?= html_escape(($preview['summary']['printer_role'] ?? 'CUSTOM') . ' • ' . ($preview['summary']['print_scope'] ?? 'DIVISION')) ?></td></tr>
          <tr><th>Connection</th><td><?= html_escape($preview['summary']['connection_type'] ?? 'LOCAL_AGENT') ?></td></tr>
          <tr><th>Agent</th><td><?= html_escape($preview['summary']['agent_host'] ?? '-') ?></td></tr>
          <tr><th>Python Port</th><td><?= $pythonPort > 0 ? $pythonPort : '-' ?></td></tr>
          <tr><th>Kertas</th><td><?= (int)($preview['paper_width_mm'] ?? 80) ?>mm • <?= (int)($preview['chars_per_line'] ?? 48) ?> cpl</td></tr>
        </table>
      </div></div>

      <div class="card printer-runtime-card"><div class="card-body p-4">
        <form method="get" class="row g-3">
          <div class="col-12">
            <label class="form-label">Template</label>
            <select class="form-select" name="template_id" onchange="this.form.submit()">
              <?php foreach ($templates as $template): ?>
                <option value="<?= (int)$template['id'] ?>" <?= (int)($selectedTemplate['id'] ?? 0) === (int)$template['id'] ? 'selected' : '' ?>><?= html_escape(($template['template_name'] ?? '-') . ' • ' . ($template['document_type'] ?? '-')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 small text-muted">Pilih template yang ingin diuji di printer ini. Tombol <strong>Test Print</strong> akan mengirim isi preview ini ke Python agent lokal.</div>
          <div class="col-12 d-flex flex-wrap">
            <span class="printer-runtime-chip"><?= !empty($selectedTemplate['is_default']) ? 'Template Default' : 'Template Non Default' ?></span>
            <span class="printer-runtime-chip"><?= !empty($selectedTemplate['is_active']) ? 'Template Aktif' : 'Template Nonaktif' ?></span>
            <span class="printer-runtime-chip"><?= html_escape($preview['document_type_label'] ?? '-') ?></span>
          </div>
        </form>
      </div></div>
    </div>

    <div class="printer-runtime-shell">
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
          <div class="small text-uppercase fw-bold text-muted">Simulasi Cetak</div>
          <h5 class="mb-0 mt-1"><?= html_escape($selectedTemplate['template_name'] ?? 'Preview Default') ?></h5>
        </div>
        <?php if ($pythonPort > 0): ?>
          <span class="badge text-bg-success">Siap Test Print</span>
        <?php else: ?>
          <span class="badge text-bg-warning">Python Port belum diisi</span>
        <?php endif; ?>
      </div>
      <div class="printer-runtime-stage">
        <div class="printer-runtime-paper" id="previewPaperSheet" style="width:<?= ((int)($preview['paper_width_mm'] ?? 80) === 58) ? 286 : 392 ?>px;">
          <?php if (!empty($preview['logo_url'])): ?>
            <div style="text-align:center;margin-bottom:12px;"><img src="<?= html_escape($preview['logo_url']) ?>" alt="Logo" style="max-height:58px;max-width:100%;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.22));"></div>
          <?php endif; ?>
          <pre id="previewPrintText"><?= html_escape(implode("\n", (array)($preview['lines'] ?? []))) ?></pre>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  const btn = document.getElementById('localPrintBtn');
  const preview = document.getElementById('previewPrintText');
  if (!btn || !preview) return;
  const pythonPort = <?= $pythonPort > 0 ? $pythonPort : 0 ?>;
  const logoPrintMarker = <?= json_encode((string)($logo_print_marker ?? '')) ?>;
  const autoPrint = <?= $autoprint ? 'true' : 'false' ?>;
  let started = false;

  function sendPrint(){
    if (pythonPort <= 0) {
      alert('Python port printer belum diisi. Lengkapi device printer dulu sebelum test print.');
      return;
    }
    btn.disabled = true;
    let textPayload = preview.textContent || '';
    if (logoPrintMarker) {
      textPayload = logoPrintMarker + '\n' + textPayload;
    }
    fetch('http://127.0.0.1:' + pythonPort + '/cetak', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        text: textPayload,
        printer_code: <?= json_encode($row['device_code'] ?? '') ?>,
        printer_name: <?= json_encode($row['device_name'] ?? '') ?>,
        paper_width_mm: <?= (int)($preview['paper_width_mm'] ?? 80) ?>,
        chars_per_line: <?= (int)($preview['chars_per_line'] ?? 48) ?>
      })
    }).then(function(res){
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json().catch(function(){ return {}; });
    }).then(function(){
      alert('Test print berhasil dikirim ke printer lokal.');
    }).catch(function(err){
      alert('Gagal menghubungi service printer lokal: ' + (err && err.message ? err.message : 'unknown error'));
    }).finally(function(){
      btn.disabled = false;
    });
  }

  btn.addEventListener('click', sendPrint);
  if (autoPrint) {
    window.addEventListener('load', function(){
      if (started) return;
      started = true;
      window.setTimeout(sendPrint, 250);
    }, { once: true });
  }
})();
</script>
