<?php
$layout = is_array($layout_row ?? null) ? $layout_row : [];
$payload = is_array($layout['payload'] ?? null) ? $layout['payload'] : [];
$documentTypes = (array)($document_types ?? []);
$documentLabels = (array)($document_type_labels ?? []);
$options = is_array($printer_options ?? null) ? $printer_options : [];
$connections = array_values(array_filter((array)($options['connections'] ?? []), static function (array $row): bool {
    return !empty($row['is_active']);
}));
$testRoutes = (array)($layout_test_routes ?? []);
$layoutRoutes = (array)($layout_routes ?? []);
$generalSettings = is_array($general_settings ?? null) ? $general_settings : [];
$generalPayload = is_array($generalSettings['payload'] ?? null) ? $generalSettings['payload'] : [];
$previewConnectionId = 0;
foreach (!empty($testRoutes) ? $testRoutes : $layoutRoutes as $route) {
    $candidateConnectionId = (int)($route['connection_id'] ?? 0);
    if ($candidateConnectionId > 0) {
        $previewConnectionId = $candidateConnectionId;
        break;
    }
}
$canSave = !empty($layout) ? !empty($can_edit) : !empty($can_create);
$switches = [
  'show_logo' => 'Logo usaha', 'show_header' => 'Judul, alamat, dan pembuka', 'show_invoice_no' => 'Nomor order', 'show_payment_no' => 'Nomor pembayaran',
  'show_customer' => 'Nama customer', 'show_table_no' => 'Nomor meja', 'show_order_time' => 'Waktu order', 'show_payment_time' => 'Waktu bayar',
  'show_cashier_order' => 'Kasir penerima order', 'show_cashier_payment' => 'Kasir pembayaran', 'show_product_name' => 'Nama produk', 'show_qty' => 'Jumlah produk',
  'show_extra' => 'Extra atau modifier', 'show_notes' => 'Catatan per item', 'show_order_notes' => 'Catatan order', 'show_price' => 'Harga item',
  'show_subtotal' => 'Subtotal', 'show_payment_breakdown' => 'Rincian pembayaran', 'show_discount' => 'Diskon dan voucher', 'show_compliment' => 'Compliment',
  'show_deposit_applied' => 'DP atau deposit', 'show_grand_total' => 'Total tagihan', 'show_paid_amount' => 'Nominal dibayar', 'show_balance_due' => 'Sisa tagihan',
  'show_void_reason' => 'Alasan void', 'show_refund_reason' => 'Alasan refund', 'show_footer' => 'Pesan footer', 'show_footer_barcode' => 'Barcode footer',
  'show_wifi_info' => 'Informasi Wi-Fi', 'show_customer_point_info' => 'Saldo poin member', 'show_customer_stamp_info' => 'Saldo stamp member',
  'show_customer_voucher' => 'Pesan voucher member', 'show_customer_review_qr' => 'QR ulasan pelanggan pada struk',
];
$switchGroups = [
  'Identitas & waktu' => ['show_logo', 'show_header', 'show_invoice_no', 'show_payment_no', 'show_customer', 'show_table_no', 'show_order_time', 'show_payment_time', 'show_cashier_order', 'show_cashier_payment'],
  'Pesanan & nilai' => ['show_product_name', 'show_qty', 'show_extra', 'show_notes', 'show_order_notes', 'show_price', 'show_subtotal', 'show_payment_breakdown', 'show_discount', 'show_compliment', 'show_deposit_applied', 'show_grand_total', 'show_paid_amount', 'show_balance_due'],
  'Penutup & customer' => ['show_footer', 'show_footer_barcode', 'show_wifi_info', 'show_customer_point_info', 'show_customer_stamp_info', 'show_customer_voucher', 'show_customer_review_qr', 'show_void_reason', 'show_refund_reason'],
];
$default = [
  'document_type' => 'RECEIPT', 'header_align' => 'CENTER', 'footer_align' => 'CENTER', 'footer_barcode_source' => 'ORDER_NO',
  'is_active' => 1, 'show_logo' => 1, 'show_header' => 1, 'show_invoice_no' => 1, 'show_customer' => 1, 'show_table_no' => 1,
  'show_order_time' => 1, 'show_product_name' => 1, 'show_qty' => 1, 'show_extra' => 1, 'show_notes' => 1,
  'show_subtotal' => 1, 'show_discount' => 1, 'show_grand_total' => 1, 'show_paid_amount' => 1, 'show_balance_due' => 1, 'show_footer' => 1,
];
$formData = array_merge($default, $layout, $payload);
$isExisting = !empty($layout['id']);
?>
<style>
  .layout-editor-grid{display:grid;grid-template-columns:minmax(420px,.93fr) minmax(480px,1.07fr);gap:1rem;align-items:stretch;min-height:clamp(620px,calc(100vh - 220px),960px)}
  .layout-editor-controls,.layout-editor-preview>.print-config-card{height:clamp(620px,calc(100vh - 220px),960px);min-height:0;overflow:hidden}.layout-editor-controls .card-body,.layout-editor-preview>.print-config-card>.card-body{height:100%;display:flex;flex-direction:column;padding:0}
  .layout-editor-form-scroll{flex:1;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:1.15rem 1.15rem 1.5rem}.layout-editor-savebar{flex:0 0 auto;display:flex;justify-content:flex-end;gap:.55rem;align-items:center;padding:.85rem 1.15rem;background:rgba(255,255,255,.96);border-top:1px solid #eadbd3;box-shadow:0 -8px 18px rgba(90,53,43,.05)}
  .layout-editor-preview{position:sticky;top:12px;align-self:start}.layout-editor-preview-stage-wrap{display:flex;flex:1;min-height:0;padding:.75rem}
  .layout-editor-switches{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.48rem}
  .layout-editor-switch{display:flex;gap:.55rem;align-items:center;min-height:40px;border:1px solid #ebdcd4;border-radius:10px;padding:.42rem .52rem;background:#fff}
  .layout-editor-switch .form-check-input{margin:0;flex:0 0 auto}
  .layout-editor-switch label{font-size:.82rem;line-height:1.25;color:#554640}
  .layout-editor-section{margin:0 0 .8rem;border:1px solid #eadbd3;border-radius:13px;background:#fff;overflow:hidden}.layout-editor-section>summary{display:flex;align-items:center;justify-content:space-between;gap:.6rem;cursor:pointer;list-style:none;padding:.8rem .9rem;background:#fcf7f4;color:#513b35;font-weight:800;font-size:.9rem}.layout-editor-section>summary::-webkit-details-marker{display:none}.layout-editor-section>summary:after{content:'+';display:grid;place-items:center;width:22px;height:22px;border-radius:50%;background:#f2e2da;color:#a80e27;font-size:1rem}.layout-editor-section[open]>summary:after{content:'-';background:#a80e27;color:#fff}.layout-editor-section-body{padding:.85rem}
  .layout-preview-stage{width:100%;min-height:0;flex:1;display:flex;align-items:flex-start;justify-content:center;padding:1rem;background:radial-gradient(circle at 50% 10%,#4a4f58 0,#1d2025 56%,#101216 100%);border-radius:16px;overflow-x:hidden;overflow-y:auto}
  .layout-preview-paper{--preview-paper-width:340px;--preview-font-size:9.6px;box-sizing:border-box;width:min(100%,var(--preview-paper-width));margin:0 auto;flex:0 0 auto;background:#fff;color:#1f1f1f;box-shadow:0 18px 34px rgba(0,0,0,.36);padding:.85rem;min-height:360px;transition:width .15s ease}
  .layout-preview-paper.paper-58{--preview-paper-width:250px}
  .layout-preview-paper.paper-80{--preview-paper-width:340px}
  .layout-preview-paper>img{display:block;max-width:62%;max-height:76px;margin:0 auto .7rem;object-fit:contain}
  .layout-preview-paper pre{margin:0;overflow-x:auto;white-space:pre;background:transparent;border:0;padding:0;font-family:Consolas,"Courier New",monospace;font-size:var(--preview-font-size);line-height:1.42;color:#1d2025;tab-size:4}
  .layout-preview-qr{display:flex;align-items:center;justify-content:center;gap:.7rem;margin-top:.8rem;padding-top:.8rem;border-top:1px dashed #c9b8ae;text-align:center;font:700 .7rem Arial,sans-serif;color:#554640}
  .layout-preview-qr .qr-copy{max-width:175px;line-height:1.35}.layout-preview-qr-code{width:82px;height:82px;flex:0 0 auto;display:grid;place-items:center;padding:4px;border:1px solid #d9c3b8;background:#fff;border-radius:5px}.layout-preview-qr-code img,.layout-preview-qr-code canvas{display:none!important}.layout-preview-qr-code [data-pos-qr-visual="true"]{display:block!important;width:100%!important;height:100%!important;max-width:none!important;max-height:none!important;margin:0!important;image-rendering:pixelated}
  .layout-preview-paper.paper-58 .layout-preview-qr{flex-direction:column;gap:.5rem}.layout-preview-paper.paper-58 .layout-preview-qr .qr-copy{max-width:100%}
  @media(max-width:1199.98px){.layout-editor-grid{grid-template-columns:minmax(360px,.93fr) minmax(430px,1.07fr)}.layout-editor-switches{grid-template-columns:1fr}}
  @media(max-width:991.98px){.layout-editor-grid{grid-template-columns:1fr;min-height:0}.layout-editor-controls,.layout-editor-preview>.print-config-card{height:auto;min-height:0;overflow:visible}.layout-editor-controls .card-body,.layout-editor-preview>.print-config-card>.card-body{height:auto}.layout-editor-form-scroll{overflow:visible}.layout-editor-preview{position:static}.layout-preview-stage{min-height:460px}.layout-editor-switches{grid-template-columns:1fr}}
</style>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3 d-flex flex-wrap justify-content-between gap-2"><div><div class="print-config-kicker">Layout dokumen</div><h4 class="fin-page-title mb-1"><?= $isExisting ? 'Edit Layout Cetak' : 'Tambah Layout Cetak' ?></h4><p class="fin-page-subtitle mb-0">Atur data yang terlihat, lihat hasilnya langsung, lalu uji pada printer yang memang memakai layout ini.</p></div><a class="btn btn-outline-secondary" href="<?= site_url('pos/printers/layouts') ?>"><?= $this->load->view('pos/_icon', ['name' => 'arrow-left', 'label' => ''], true) ?> <span class="ms-1">Kembali ke Layout</span></a></div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'layouts']); ?>
  <div class="print-config-note mb-3"><strong>Prinsipnya sederhana:</strong> halaman ini mengatur isi struk atau tiket. Nama usaha, logo, dan Wi-Fi berasal dari Tampilan Umum. QR ulasan dikelola dari <a href="<?= site_url('pos/customer-reviews') ?>">Ulasan Pelanggan</a>. Tombol test memakai printer dari Aturan Cetak yang sudah menghubungkan layout ini, sehingga tidak menebak tujuan fisiknya.</div>
  <div class="layout-editor-grid">
    <section class="card print-config-card layout-editor-controls"><div class="card-body">
      <form id="layout-editor-form" class="layout-editor-form-scroll row g-3"><input type="hidden" name="id" value="<?= (int)($layout['id'] ?? 0) ?>"><input type="hidden" name="layout_code" value="<?= html_escape((string)($layout['layout_code'] ?? '')) ?>">
        <div class="col-md-7"><label class="form-label">Nama layout</label><input class="form-control" name="layout_name" required value="<?= html_escape((string)($formData['layout_name'] ?? '')) ?>" placeholder="Contoh: Struk Kasir Lengkap"></div>
        <div class="col-md-5"><label class="form-label">Jenis dokumen</label><select class="form-select" name="document_type" id="layout-document-type" required><?= implode('', array_map(static function ($type) use ($documentLabels, $formData): string { return '<option value="' . html_escape((string)$type) . '"' . (($formData['document_type'] ?? 'RECEIPT') === $type ? ' selected' : '') . '>' . html_escape((string)($documentLabels[$type] ?? $type)) . '</option>'; }, $documentTypes)) ?></select></div>
        <div class="col-12"><label class="form-label">Catatan penggunaan</label><input class="form-control" name="description" value="<?= html_escape((string)($formData['description'] ?? '')) ?>" placeholder="Contoh: struk pembayaran untuk kasir utama."></div>
        <div class="col-md-4"><label class="form-label">Posisi header</label><select class="form-select" name="header_align"><?php foreach (['LEFT'=>'Kiri','CENTER'=>'Tengah','RIGHT'=>'Kanan'] as $key=>$label): ?><option value="<?= $key ?>" <?= (($formData['header_align'] ?? 'CENTER') === $key) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Posisi footer</label><select class="form-select" name="footer_align"><?php foreach (['LEFT'=>'Kiri','CENTER'=>'Tengah','RIGHT'=>'Kanan'] as $key=>$label): ?><option value="<?= $key ?>" <?= (($formData['footer_align'] ?? 'CENTER') === $key) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Sumber barcode footer</label><select class="form-select" name="footer_barcode_source"><?php foreach (['ORDER_NO'=>'Nomor order','PAYMENT_NO'=>'Nomor pembayaran','VOID_NO'=>'Nomor void','REFUND_NO'=>'Nomor refund','VOUCHER_CODE'=>'Kode voucher','CUSTOM'=>'Teks khusus'] as $key=>$label): ?><option value="<?= $key ?>" <?= (($formData['footer_barcode_source'] ?? 'ORDER_NO') === $key) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Teks barcode khusus</label><input class="form-control" name="footer_barcode_custom" value="<?= html_escape((string)($formData['footer_barcode_custom'] ?? '')) ?>" placeholder="Hanya dipakai jika sumber barcode adalah teks khusus."></div>
        <div class="col-12"><details class="layout-editor-section" open><summary>Simulasi ukuran & test printer</summary><div class="layout-editor-section-body"><div class="row g-3"><div class="col-12"><label class="form-label">Preview menggunakan koneksi</label><select id="preview-connection" class="form-select"><option value="0" <?= $previewConnectionId <= 0 ? 'selected' : '' ?>>Ukuran standar 80 mm / 48 karakter</option><?php foreach ($connections as $connection): ?><option value="<?= (int)$connection['id'] ?>" <?= (int)$connection['id'] === $previewConnectionId ? 'selected' : '' ?>><?= html_escape((string)$connection['connection_name']) ?> - <?= (int)($connection['paper_width_mm'] ?? 80) ?> mm / <?= (int)($connection['chars_per_line'] ?? 48) ?> karakter</option><?php endforeach; ?></select><div class="form-text">Pilihan ini hanya mengatur simulasi di layar. Ukuran dan jumlah karakter cetak tetap mengikuti koneksi pada Aturan Cetak.</div></div><?php if ($isExisting): ?><div class="col-12 border-top pt-3"><label class="form-label">Test ke printer yang memakai layout ini</label><?php if (!empty($testRoutes)): ?><div class="input-group"><select class="form-select" id="layout-test-route"><?php foreach ($testRoutes as $route): ?><option value="<?= (int)$route['id'] ?>"><?= html_escape((string)$route['route_name']) ?> - <?= html_escape((string)$route['connection_name']) ?></option><?php endforeach; ?></select><?php if ($canSave): ?><button type="button" class="btn btn-outline-primary" id="test-layout"><?= $this->load->view('pos/_icon', ['name' => 'print', 'label' => 'Test cetak'], true) ?><span class="ms-1">Test</span></button><?php endif; ?></div><div class="form-text">Test memakai aturan ini, sehingga hasilnya dikirim ke printer fisik yang benar.</div><?php else: ?><div class="print-config-note">Layout ini belum tersambung ke Aturan Cetak aktif. Simpan, hubungkan di <a href="<?= site_url('pos/printers/rules') ?>">Aturan Cetak</a>, lalu kembali ke sini untuk test.</div><?php endif; ?></div><?php else: ?><div class="col-12 border-top pt-3"><div class="print-config-note">Simpan layout terlebih dahulu. Setelah dihubungkan ke Aturan Cetak, pilihan test printer akan tersedia di sini.</div></div><?php endif; ?></div></div></details></div>
        <div class="col-12"><div class="print-config-kicker mb-1">Data yang ditampilkan</div><div class="small text-muted mb-2">Buka bagian yang ingin diatur. Setiap perubahan langsung tercermin pada kertas di kanan.</div><div id="layout-review-qr-hint" class="alert alert-warning small py-2 mb-2 <?= !empty($generalPayload['customer_review_qr_enabled']) ? 'd-none' : '' ?>"><strong>QR ulasan belum aktif secara umum.</strong> Toggle pada layout hanya menentukan struk ini boleh menampilkan QR. Aktifkan sumber QR di <a href="<?= site_url('pos/customer-reviews') ?>">Ulasan Pelanggan</a> agar preview dan hasil cetak benar-benar menampilkannya.</div><?php foreach ($switchGroups as $groupName => $keys): ?><details class="layout-editor-section" <?= $groupName === 'Identitas & waktu' ? 'open' : '' ?>><summary><?= html_escape($groupName) ?></summary><div class="layout-editor-section-body"><div class="layout-editor-switches"><?php foreach ($keys as $key): $label = $switches[$key] ?? $key; ?><div class="layout-editor-switch"><input class="form-check-input" type="checkbox" role="switch" name="<?= $key ?>" id="<?= $key ?>" value="1" <?= !empty($formData[$key]) ? 'checked' : '' ?>><label for="<?= $key ?>"><?= html_escape($label) ?></label></div><?php endforeach; ?></div></div></details><?php endforeach; ?></div>
        <div class="col-md-6"><div class="layout-editor-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_default" id="is-default" value="1" <?= !empty($formData['is_default']) ? 'checked' : '' ?>><label for="is-default">Jadikan layout default untuk jenis dokumen ini</label></div></div>
        <div class="col-md-6"><div class="layout-editor-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" id="is-active" value="1" <?= !empty($formData['is_active']) ? 'checked' : '' ?>><label for="is-active">Layout siap dipakai pada aturan cetak</label></div></div>
      </form>
      <div class="layout-editor-savebar"><a class="btn btn-light" href="<?= site_url('pos/printers/layouts') ?>">Batal</a><?php if ($canSave): ?><button type="button" class="btn btn-primary" id="save-layout"><?= $this->load->view('pos/_icon', ['name' => 'save', 'label' => ''], true) ?><span class="ms-1">Simpan Layout</span></button><?php endif; ?></div>
    </div></section>
    <aside class="layout-editor-preview"><section class="card print-config-card"><div class="card-body">
      <div class="layout-editor-preview-stage-wrap"><div class="layout-preview-stage"><div class="layout-preview-paper paper-80" id="layout-preview-paper"><img id="layout-preview-logo" class="d-none" alt="Logo pada layout"><pre id="layout-preview-lines">Menyiapkan preview...</pre><div class="layout-preview-qr d-none" id="layout-preview-qr"><div class="layout-preview-qr-code d-none" id="layout-preview-qr-image" role="img" aria-label="QR ulasan pelanggan"></div><div class="qr-copy" id="layout-preview-qr-message"></div></div></div></div></div>
    </div></section></aside>
  </div>
</div>
<script src="<?= base_url('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
<script src="<?= base_url('assets/js/pos-local-qr.js') ?>?v=20260825g"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ui = window.PrinterConfigUI;
  const form = document.getElementById('layout-editor-form');
  const previewConnection = document.getElementById('preview-connection');
  const previewLines = document.getElementById('layout-preview-lines');
  const previewPaper = document.getElementById('layout-preview-paper');
  const previewLogo = document.getElementById('layout-preview-logo');
  const previewQr = document.getElementById('layout-preview-qr');
  const previewQrImage = document.getElementById('layout-preview-qr-image');
  const previewQrMessage = document.getElementById('layout-preview-qr-message');
  const reviewQrHint = document.getElementById('layout-review-qr-hint');
  let previewTimer = null;
  function payload() { const result = ui.formObject(form); result.connection_id = Number(previewConnection.value || 0); return result; }
  function clearPreviewQr() { previewQrMessage.textContent = ''; if (window.PosLocalQr) { window.PosLocalQr.clear(previewQrImage); } else { previewQrImage.textContent = ''; } previewQrImage.classList.add('d-none'); }
  function applyPreviewPaperMetrics(width, chars) {
    const paperPixels = width === 58 ? 250 : 340;
    const printablePixels = Math.max(160, paperPixels - 28);
    const fontSize = Math.max(8.2, Math.min(9.8, printablePixels / Math.max(1, chars) / 0.606));
    previewPaper.className = 'layout-preview-paper paper-' + width;
    previewPaper.style.setProperty('--preview-font-size', fontSize.toFixed(2) + 'px');
  }
  async function renderPreview() {
    try {
      const json = await ui.post('<?= site_url('pos/printers/layouts/preview') ?>', payload());
      const preview = json.preview || {};
      const width = Number(preview.paper_width_mm || 80) === 58 ? 58 : 80;
      const chars = Number(preview.chars_per_line || (width === 58 ? 32 : 48));
      applyPreviewPaperMetrics(width, chars);
      previewLines.textContent = Array.isArray(preview.lines) ? preview.lines.join('\n') : 'Preview belum tersedia.';
      // A fresh preview should begin at the document header. The operator can
      // still scroll inside the dark stage to inspect a long thermal receipt.
      const previewStage = document.querySelector('.layout-preview-stage');
      if (previewStage) previewStage.scrollTop = 0;
      if (preview.logo_url) { previewLogo.src = String(preview.logo_url); previewLogo.classList.remove('d-none'); } else { previewLogo.removeAttribute('src'); previewLogo.classList.add('d-none'); }
      const reviewQr = preview.customer_review_qr || {};
      if (reviewQrHint) {
        const layoutAllowsReviewQr = Boolean(reviewQr.layout_enabled);
        const generalReviewQrEnabled = Boolean(reviewQr.general_enabled);
        reviewQrHint.classList.toggle('d-none', !layoutAllowsReviewQr || generalReviewQrEnabled);
      }
      if (reviewQr.enabled) {
        const qrUrl = String(reviewQr.url || '');
        previewQrMessage.textContent = String(reviewQr.message || 'Bagikan ulasan Anda dengan scan QR berikut.');
        if (qrUrl !== '') {
          const rendered = Boolean(window.PosLocalQr && window.PosLocalQr.render(previewQrImage, qrUrl, {size: 220, label: 'QR ulasan pelanggan'}));
          previewQrImage.classList.remove('d-none');
          if (!rendered) previewQrMessage.textContent += ' (QR belum dapat dimuat. Muat ulang halaman.)';
        } else {
          clearPreviewQr();
          previewQrMessage.textContent += ' (QR area akan tersedia setelah migration ulasan dipasang.)';
        }
        previewQr.classList.remove('d-none');
      } else {
        clearPreviewQr(); previewQr.classList.add('d-none');
      }
    } catch (error) { previewLines.textContent = 'Preview tidak dapat dibuat.\n' + String(error.message || 'Coba periksa data layout.'); clearPreviewQr(); previewQr.classList.add('d-none'); }
  }
  function schedulePreview() { window.clearTimeout(previewTimer); previewTimer = window.setTimeout(renderPreview, 180); }
  form.addEventListener('input', schedulePreview); form.addEventListener('change', schedulePreview); previewConnection.addEventListener('change', renderPreview);
  const save = document.getElementById('save-layout');
  if (save) save.addEventListener('click', async function () { if (!form.reportValidity()) return; save.disabled = true; try { const json = await ui.post('<?= site_url('pos/printers/layouts/save') ?>', ui.formObject(form)); const id = Number(json.id || 0); ui.notice('success', 'Layout disimpan', 'Preview dan aturan cetak sekarang memakai pilihan terbaru.'); if (id > 0 && !<?= $isExisting ? 'true' : 'false' ?>) { window.setTimeout(function () { window.location.href = '<?= site_url('pos/printers/layouts/edit') ?>/' + id; }, 450); } } catch (error) { ui.notice('error', 'Layout belum tersimpan', error.message); } finally { save.disabled = false; } });
  const test = document.getElementById('test-layout');
  if (test) test.addEventListener('click', async function () { test.disabled = true; let target = null; try { const routeId = Number(document.getElementById('layout-test-route').value || 0); const json = await ui.post('<?= site_url('pos/printers/layouts/test/' . (int)($layout['id'] ?? 0)) ?>', {route_id: routeId}); target = json.target || {}; const result = await ui.agentPrint(target); try { await ui.post('<?= site_url('pos/printers/attempts/ack') ?>', {attempt_id:Number(target.print_attempt_id || 0),status:'SENT',message:result.message || 'Test layout dikirim ke Local Agent.'}); } catch (ignored) {} ui.notice('success', 'Test dikirim', (result.message || 'Perintah diterima.') + ' Periksa kertas pada printer ' + String(target.printer_name || '') + '. Hasilnya juga masuk Monitor Cetak.'); } catch (error) { const message = error.message || 'Tidak dapat menghubungi Local Agent.'; if (target) { try { await ui.post('<?= site_url('pos/printers/attempts/ack') ?>', {attempt_id:Number(target.print_attempt_id || 0),status:'FAILED',message:message}); } catch (ignored) {} } ui.notice('error', 'Test printer gagal', message); } finally { test.disabled = false; } });
  renderPreview();
});
</script>

