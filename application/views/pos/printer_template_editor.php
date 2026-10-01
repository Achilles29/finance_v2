<?php
$row = is_array($row ?? null) ? $row : null;
$payload = is_array($payload ?? null) ? $payload : [];
$previewPrinters = is_array($preview_printers ?? null) ? $preview_printers : [];
$selectedPrinterId = !empty($previewPrinters) ? (int)$previewPrinters[0]['id'] : 0;
?>
<style>
  .printer-editor-shell{display:grid;grid-template-columns:minmax(360px,420px) minmax(0,1fr);gap:1rem;align-items:start}
  .printer-editor-card{border:1px solid rgba(188,44,69,.12);border-radius:22px;background:linear-gradient(180deg,#fffdfb 0%,#fff 100%);box-shadow:0 14px 36px rgba(71,34,34,.08)}
  .printer-editor-card .card-body{padding:1.2rem}
  .printer-editor-kicker{display:inline-flex;align-items:center;gap:.45rem;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#8a786c}
  .printer-toggle-grid{display:grid;grid-template-columns:1fr;gap:.65rem}
  .printer-toggle-item{display:flex;justify-content:space-between;gap:.85rem;align-items:center;padding:.8rem .9rem;border:1px solid rgba(188,44,69,.12);border-radius:16px;background:#fff8f5}
  .printer-toggle-item .form-check-input{width:2.7rem;height:1.4rem;float:none;margin:0;cursor:pointer}
  .printer-toggle-item .label-title{font-weight:700;color:#412d33}
  .printer-toggle-item .label-note{font-size:.76rem;color:#8b7a72;margin-top:.1rem}
  .printer-preview-shell{border:1px solid rgba(188,44,69,.12);border-radius:24px;background:linear-gradient(180deg,#fff5ef 0%,#fff 100%);padding:1rem;position:sticky;top:94px}
  .printer-preview-stage{border:1px solid rgba(188,44,69,.12);border-radius:18px;background:#f8ebe2;padding:1rem}
  .printer-paper{width:392px;max-width:100%;margin:0 auto;background:#111827;color:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 18px 30px rgba(17,24,39,.18)}
  .printer-paper pre{margin:0;background:transparent;color:#fff;font-family:Consolas,Monaco,monospace;white-space:pre-wrap;line-height:1.4;font-size:.93rem}
  .printer-summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;margin-top:.9rem}
  .printer-summary-item{border:1px solid rgba(188,44,69,.12);border-radius:14px;background:#fff;padding:.7rem .8rem}
  .printer-summary-item small{display:block;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;color:#9a7f72;font-weight:800;margin-bottom:.2rem}
  .printer-section-title{font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#8b5d5f;margin-bottom:.65rem}
  @media (max-width: 1200px){.printer-editor-shell{grid-template-columns:1fr}.printer-preview-shell{position:static}.printer-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media (max-width: 767px){.printer-summary-grid{grid-template-columns:1fr}}
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1"><?= html_escape($page_title ?? 'Template Printer POS') ?></h4>
      <p class="fin-page-subtitle mb-0">Atur template printer secara visual, lalu lihat hasil cetak berubah secara live sebelum disimpan.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= site_url('pos/printers/settings') ?>" class="btn btn-outline-secondary">Pengaturan Umum</a>
      <?php if ($row && !empty($row['id'])): ?>
        <a href="<?= site_url('pos/printers/templates/preview/' . (int)$row['id']) ?>" class="btn btn-outline-primary">Preview Halaman</a>
      <?php endif; ?>
      <a href="<?= site_url('pos/printers') ?>" class="btn btn-outline-secondary">Kembali</a>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>

  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div>
  <?php endif; ?>

  <form method="post" id="printerTemplateWorkbenchForm">
    <div class="printer-editor-shell">
      <div class="d-grid gap-3">
        <div class="card printer-editor-card">
          <div class="card-body">
            <div class="printer-editor-kicker"><i class="ri-file-settings-line"></i> Identitas Template</div>
            <div class="row g-3 mt-1">
              <div class="col-md-4">
                <label class="form-label">Kode</label>
                <input class="form-control" name="template_code" value="<?= html_escape($row['template_code'] ?? '') ?>" placeholder="Otomatis saat simpan">
              </div>
              <div class="col-md-5">
                <label class="form-label">Nama Template</label>
                <input class="form-control" name="template_name" value="<?= html_escape($row['template_name'] ?? '') ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Dokumen</label>
                <select class="form-select" name="document_type" id="document_type">
                  <?php foreach (($document_types ?? []) as $doc): ?>
                    <option value="<?= html_escape($doc) ?>" <?= strtoupper((string)($row['document_type'] ?? 'RECEIPT')) === $doc ? 'selected' : '' ?>><?= html_escape($doc) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Preview Printer</label>
                <select class="form-select" id="preview_printer_id" name="printer_id">
                  <?php foreach ($previewPrinters as $printer): ?>
                    <option value="<?= (int)$printer['id'] ?>" <?= $selectedPrinterId === (int)$printer['id'] ? 'selected' : '' ?>><?= html_escape(($printer['printer_role'] ?? 'CUSTOM') . ' • ' . ($printer['printer_name'] ?? '-') . ' • ' . ($printer['paper_width_mm'] ?? 80) . 'mm') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Default</label>
                <div class="form-check form-switch border rounded-4 px-3 py-2 bg-light-subtle">
                  <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" <?= !empty($row['is_default']) ? 'checked' : '' ?>>
                  <label class="form-check-label ms-2" for="is_default">Gunakan sebagai template default</label>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Status</label>
                <div class="form-check form-switch border rounded-4 px-3 py-2 bg-light-subtle">
                  <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= array_key_exists('is_active', $row ?? []) ? (!empty($row['is_active']) ? 'checked' : '') : 'checked' ?>>
                  <label class="form-check-label ms-2" for="is_active">Template aktif</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card printer-editor-card">
          <div class="card-body">
            <div class="printer-section-title">Branding & Header</div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Judul</label>
                <input class="form-control" name="payload_title" value="<?= html_escape($payload['title'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Subjudul</label>
                <input class="form-control" name="payload_subtitle" value="<?= html_escape($payload['subtitle'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Logo URL</label>
                <input class="form-control" name="logo_url" value="<?= html_escape($payload['logo_url'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Perataan Header</label>
                <select class="form-select" name="header_align">
                  <?php foreach (['LEFT','CENTER','RIGHT','JUSTIFY'] as $align): ?>
                    <option value="<?= $align ?>" <?= strtoupper((string)($payload['header_align'] ?? 'CENTER')) === $align ? 'selected' : '' ?>><?= html_escape($align) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Filter Divisi</label>
                <select class="form-select" name="division_filter">
                  <?php foreach (['ALL','BAR','KITCHEN'] as $division): ?>
                    <option value="<?= $division ?>" <?= strtoupper((string)($payload['division_filter'] ?? 'ALL')) === $division ? 'selected' : '' ?>><?= html_escape($division) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Baris Header Tambahan</label>
                <textarea class="form-control" rows="4" name="header_lines" placeholder="Satu baris per enter"><?= html_escape(implode("\n", (array)($payload['header_lines'] ?? []))) ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="card printer-editor-card">
          <div class="card-body">
            <div class="printer-section-title">Toggle Konten</div>
            <div class="printer-toggle-grid">
              <?php
              $toggles = [
                'show_logo' => ['Tampilkan Logo','Logo brand di header'],
                'show_header' => ['Tampilkan Header','Judul, alamat, dan baris header'],
                'show_invoice_no' => ['Tampilkan No Order','Nomor order transaksi'],
                'show_payment_no' => ['Tampilkan No Payment','Nomor pembayaran final'],
                'show_customer' => ['Tampilkan Customer','Nama customer / member'],
                'show_table_no' => ['Tampilkan Meja','Nomor meja atau area layanan'],
                'show_order_time' => ['Tampilkan Waktu Order','Waktu order dicatat'],
                'show_payment_time' => ['Tampilkan Waktu Bayar','Waktu transaksi dibayar'],
                'show_cashier_order' => ['Tampilkan Kasir Order','Kasir pencatat order'],
                'show_cashier_payment' => ['Tampilkan Kasir Bayar','Kasir yang menyelesaikan pembayaran'],
                'show_product_name' => ['Tampilkan Nama Produk','Nama produk per baris'],
                'show_qty' => ['Tampilkan Qty','Kuantitas per produk'],
                'show_extra' => ['Tampilkan Extra','Modifier / extra produk'],
                'show_notes' => ['Tampilkan Catatan Item','Catatan per produk atau line item'],
                'show_order_notes' => ['Tampilkan Catatan Order','Catatan header dari pos_order.notes'],
                'show_price' => ['Tampilkan Harga','Harga line item'],
                'show_subtotal' => ['Tampilkan Subtotal','Subtotal sebelum diskon'],
                'show_discount' => ['Tampilkan Diskon','Potongan transaksi'],
                'show_compliment' => ['Tampilkan Compliment','Potongan karena compliment'],
                'show_deposit_applied' => ['Tampilkan Deposit','Deposit yang dipakai'],
                'show_grand_total' => ['Tampilkan Grand Total','Nilai akhir transaksi'],
                'show_payment_breakdown' => ['Tampilkan Metode Bayar','Rincian pembayaran'],
                'show_paid_amount' => ['Tampilkan Sudah Bayar','Nominal yang sudah dibayar'],
                'show_balance_due' => ['Tampilkan Sisa Bayar','Nominal kurang bayar'],
                'show_void_reason' => ['Tampilkan Alasan Void','Khusus slip void'],
                'show_refund_reason' => ['Tampilkan Alasan Refund','Khusus slip refund'],
                'show_footer' => ['Tampilkan Footer','Footer dan ucapan penutup'],
                'show_footer_barcode' => ['Tampilkan Barcode','Barcode bawah struk'],
                'show_wifi_info' => ['Tampilkan Wi-Fi','Nama dan password Wi-Fi'],
                'show_customer_point_info' => ['Tampilkan Poin','Info poin member'],
                'show_customer_stamp_info' => ['Tampilkan Stamp','Info stamp member'],
                'show_customer_voucher' => ['Tampilkan Voucher','Pesan voucher customer'],
              ];
              foreach ($toggles as $field => $meta): ?>
                <label class="printer-toggle-item" for="<?= $field ?>">
                  <div>
                    <div class="label-title"><?= html_escape($meta[0]) ?></div>
                    <div class="label-note"><?= html_escape($meta[1]) ?></div>
                  </div>
                  <input class="form-check-input" type="checkbox" id="<?= $field ?>" name="<?= $field ?>" value="1" <?= !empty($payload[$field]) ? 'checked' : '' ?>>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="card printer-editor-card">
          <div class="card-body">
            <div class="printer-section-title">Footer & Pelengkap</div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Perataan Footer</label>
                <select class="form-select" name="footer_align">
                  <?php foreach (['LEFT','CENTER','RIGHT','JUSTIFY'] as $align): ?>
                    <option value="<?= $align ?>" <?= strtoupper((string)($payload['footer_align'] ?? 'CENTER')) === $align ? 'selected' : '' ?>><?= html_escape($align) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Sumber Barcode</label>
                <select class="form-select" name="footer_barcode_source">
                  <?php foreach (['ORDER_NO','PAYMENT_NO','VOID_NO','REFUND_NO','VOUCHER_CODE','CUSTOM'] as $source): ?>
                    <option value="<?= $source ?>" <?= strtoupper((string)($payload['footer_barcode_source'] ?? 'ORDER_NO')) === $source ? 'selected' : '' ?>><?= html_escape($source) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Baris Footer</label>
                <textarea class="form-control" rows="4" name="footer_lines" placeholder="Satu baris per enter"><?= html_escape(implode("\n", (array)($payload['footer_lines'] ?? []))) ?></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">Barcode Custom</label>
                <input class="form-control" name="footer_barcode_custom" value="<?= html_escape($payload['footer_barcode_custom'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Wi-Fi Name</label>
                <input class="form-control" name="wifi_name" value="<?= html_escape($payload['wifi_name'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Wi-Fi Password</label>
                <input class="form-control" name="wifi_password" value="<?= html_escape($payload['wifi_password'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Voucher Limit</label>
                <input class="form-control" type="number" min="1" max="5" name="customer_voucher_limit" value="<?= (int)($payload['customer_voucher_limit'] ?? 1) ?>">
              </div>
              <div class="col-md-8">
                <label class="form-label">Perataan Voucher</label>
                <select class="form-select" name="customer_voucher_align">
                  <?php foreach (['LEFT','CENTER','RIGHT','JUSTIFY'] as $align): ?>
                    <option value="<?= $align ?>" <?= strtoupper((string)($payload['customer_voucher_align'] ?? 'CENTER')) === $align ? 'selected' : '' ?>><?= html_escape($align) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Template Pesan Voucher</label>
                <textarea class="form-control" rows="3" name="customer_voucher_message_template"><?= html_escape($payload['customer_voucher_message_template'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pb-3">
          <a href="<?= site_url('pos/printers') ?>" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan Template</button>
        </div>
      </div>

      <div class="printer-preview-shell">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
          <div>
            <div class="printer-editor-kicker"><i class="ri-printer-cloud-line"></i> Preview Live</div>
            <h5 class="mb-0 mt-2" id="livePreviewDocLabel">-</h5>
          </div>
          <span class="badge text-bg-light border" id="livePreviewPrinterLabel">-</span>
        </div>
        <div class="printer-preview-stage">
          <div class="printer-paper" id="livePreviewPaper">
            <div id="livePreviewLogoWrap" style="display:none;text-align:center;margin-bottom:12px;">
              <img id="livePreviewLogo" src="" alt="Logo" style="max-height:58px;max-width:100%;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.22));">
            </div>
            <pre id="livePreviewText"></pre>
          </div>
        </div>
        <div class="printer-summary-grid" id="livePreviewSummary"></div>
      </div>
    </div>
  </form>
</div>

<script>
(function(){
  const form = document.getElementById('printerTemplateWorkbenchForm');
  const summaryEl = document.getElementById('livePreviewSummary');
  const textEl = document.getElementById('livePreviewText');
  const logoWrap = document.getElementById('livePreviewLogoWrap');
  const logoEl = document.getElementById('livePreviewLogo');
  const paperEl = document.getElementById('livePreviewPaper');
  const docLabelEl = document.getElementById('livePreviewDocLabel');
  const printerLabelEl = document.getElementById('livePreviewPrinterLabel');
  let timer = null;

  function formPayload(){
    const fd = new FormData(form);
    const obj = {};
    fd.forEach((value, key) => { obj[key] = value; });
    return obj;
  }

  function renderSummary(summary, payload, paperWidth, charsPerLine){
    const items = [
      ['Printer', `${summary.printer_role || 'CUSTOM'} • ${summary.printer_name || '-'}`],
      ['Output', `${paperWidth}mm • ${charsPerLine} cpl`],
      ['Outlet', summary.outlet_name || 'GLOBAL'],
      ['Connection', summary.connection_type || 'LOCAL_AGENT'],
      ['Agent', summary.agent_host || '-'],
      ['Device', summary.device_name || '-'],
      ['Order / Payment', `${payload.show_invoice_no ? 'Order' : '-'} • ${payload.show_payment_no ? 'Payment' : '-'}`],
      ['Customer / Meja', `${payload.show_customer ? 'Customer' : '-'} • ${payload.show_table_no ? 'Meja' : '-'}`],
      ['Produk / Harga', `${payload.show_product_name ? 'Produk' : '-'} • ${payload.show_price ? 'Harga' : '-'}`],
      ['Qty / Extra / Note', `${payload.show_qty ? 'Qty' : '-'} • ${payload.show_extra ? 'Extra' : '-'} • ${payload.show_order_notes ? 'Note Order' : '-'}`],
      ['Footer / Barcode', `${payload.show_footer ? 'Footer' : '-'} • ${payload.show_footer_barcode ? 'Barcode' : '-'}`],
      ['Poin / Stamp / Voucher', `${payload.show_customer_point_info ? 'Poin' : '-'} • ${payload.show_customer_stamp_info ? 'Stamp' : '-'} • ${payload.show_customer_voucher ? 'Voucher' : '-'}`],
    ];
    summaryEl.innerHTML = items.map(([label, value]) => `<div class="printer-summary-item"><small>${label}</small><div>${value}</div></div>`).join('');
  }

  async function refreshPreview(){
    try {
      const response = await fetch('<?= site_url('pos/printers/templates/live-preview') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(formPayload())
      });
      const json = await response.json();
      if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal membangun preview printer.');
      const data = json;
      const preview = data.lines || [];
      const payload = data.payload || {};
      textEl.textContent = preview.join("\n");
      docLabelEl.textContent = data.document_type_label || data.document_type || '-';
      printerLabelEl.textContent = `${data.summary.printer_role || 'CUSTOM'} • ${data.summary.printer_name || '-'} • ${data.paper_width_mm || 80}mm / ${data.chars_per_line || 48}cpl`;
      paperEl.style.width = (Number(data.paper_width_mm || 80) === 58 ? 286 : 392) + 'px';
      if (data.logo_url) {
        logoWrap.style.display = '';
        logoEl.src = data.logo_url;
      } else {
        logoWrap.style.display = 'none';
        logoEl.removeAttribute('src');
      }
      renderSummary(data.summary || {}, payload, data.paper_width_mm || 80, data.chars_per_line || 48);
    } catch (err) {
      textEl.textContent = 'Preview gagal dimuat. ' + (err && err.message ? err.message : '');
    }
  }

  function queuePreview(){
    clearTimeout(timer);
    timer = setTimeout(refreshPreview, 120);
  }

  form.addEventListener('input', queuePreview);
  form.addEventListener('change', queuePreview);
  refreshPreview();
})();
</script>


