<?php
$routeRows = (array)($routes ?? []);
$eventLabels = (array)($event_type_labels ?? []);
$documentLabels = (array)($document_type_labels ?? []);
$scopeLabels = ['ALL_ITEMS' => 'Semua item order', 'MATCHED_DIVISION' => 'Item divisi terpilih'];
$preferredRouteId = 0;
foreach ($routeRows as $route) {
  if (strtoupper((string)($route['document_type'] ?? '')) === 'RECEIPT'
      && strtoupper((string)($route['event_code'] ?? '')) === 'ORDER_PAID_RECEIPT') {
    $preferredRouteId = (int)($route['id'] ?? 0);
    break;
  }
}
if ($preferredRouteId <= 0) {
  foreach ($routeRows as $route) {
    if (strtoupper((string)($route['document_type'] ?? '')) === 'RECEIPT') {
      $preferredRouteId = (int)($route['id'] ?? 0);
      break;
    }
  }
}
?>
<style>
  .live-preview-stage{min-height:600px;display:flex;align-items:flex-start;justify-content:center;padding:2.25rem 1.25rem;background:radial-gradient(circle at 50% 10%,#4b525c 0,#24272d 53%,#121417 100%);border-radius:16px;overflow-x:hidden;overflow-y:auto}
  .live-preview-paper{--preview-paper-width:440px;--preview-font-size:13px;box-sizing:border-box;width:min(100%,var(--preview-paper-width));margin:0 auto;flex:0 0 auto;min-height:430px;background:#fff;color:#1d2025;box-shadow:0 22px 40px rgba(0,0,0,.42);padding:1rem;transition:width .16s ease}
  .live-preview-paper.paper-58{--preview-paper-width:319px}
  .live-preview-paper.paper-80{--preview-paper-width:440px}
  .live-preview-paper pre{margin:0;padding:0;overflow-x:auto;background:transparent;border:0;color:#1d2025;white-space:pre;font-family:Consolas,"Courier New",monospace;font-size:var(--preview-font-size);line-height:1.42;tab-size:4}
  .live-preview-paper>img{display:block;max-width:62%;max-height:78px;margin:0 auto .75rem;object-fit:contain}
  .live-preview-qr{display:flex;align-items:center;justify-content:center;gap:.75rem;margin-top:.9rem;padding-top:.85rem;border-top:1px dashed #c9b8ae;text-align:center;font:700 .72rem Arial,sans-serif;color:#554640}
  .live-preview-qr .qr-copy{max-width:190px;line-height:1.4}.live-preview-qr-code{width:88px;height:88px;flex:0 0 auto;display:grid;place-items:center;padding:4px;border:1px solid #d9c3b8;background:#fff;border-radius:5px}.live-preview-qr-code img,.live-preview-qr-code canvas{display:none!important}.live-preview-qr-code [data-pos-qr-visual="true"]{display:block!important;width:100%!important;height:100%!important;max-width:none!important;max-height:none!important;margin:0!important;image-rendering:pixelated}
  .live-preview-paper.paper-58 .live-preview-qr{flex-direction:column;gap:.55rem}.live-preview-paper.paper-58 .live-preview-qr .qr-copy{max-width:100%}
  .live-preview-route-detail{border-top:1px solid #eee0d8;margin-top:1rem;padding-top:1rem;font-size:.86rem;line-height:1.55;color:#705d55}.live-preview-document-hint{display:none;margin-top:.75rem;padding:.7rem .8rem;border:1px solid #ead9ce;border-radius:11px;background:#fff8ef;color:#745e54;font-size:.8rem;line-height:1.45}.live-preview-document-hint.is-visible{display:block}
</style>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3"><div><h4 class="fin-page-title mb-1">Preview Aturan Cetak</h4><p class="fin-page-subtitle mb-0">Periksa gabungan Tampilan Umum, layout, dan koneksi sebelum transaksi nyata dicetak.</p></div></div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'preview']); ?>
  <div class="print-config-note mb-3"><strong>Preview ini aman.</strong> Kertas putih di tengah adalah simulasi area cetak. Lebar, jumlah karakter, logo, dan data yang terlihat dibaca dari aturan serta konfigurasi database yang sama dengan engine POS. Halaman ini tidak mengirim test ke printer fisik.</div>
  <div class="row g-3"><div class="col-lg-4"><div class="card print-config-card"><div class="card-body"><label class="form-label">Pilih aturan cetak</label><select class="form-select" id="preview-route"><option value="">Pilih aturan</option><?php foreach ($routeRows as $route): ?><option value="<?= (int)$route['id'] ?>"<?= (int)$route['id'] === $preferredRouteId ? ' selected' : '' ?>><?= html_escape((string)($eventLabels[$route['event_code']] ?? $route['event_code'])) ?> - <?= html_escape((string)$route['route_name']) ?></option><?php endforeach; ?></select><div class="live-preview-route-detail" id="preview-info">Pilih aturan untuk melihat printer tujuan, layout, dan cakupan item yang dipakai.</div></div></div></div><div class="col-lg-8"><div class="card print-config-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><div class="print-config-kicker">Hasil preview</div><h5 class="mb-0">Tampilan pada kertas</h5></div><div class="text-end"><span class="print-config-status generated" id="preview-doc">-</span><div class="small text-muted mt-1" id="preview-paper-info">-</div></div></div><div class="live-preview-stage"><div class="live-preview-paper paper-80" id="preview-paper"><img class="d-none" id="preview-logo" alt="Logo usaha"><pre id="preview-lines">Pilih aturan cetak di sebelah kiri.</pre><div class="live-preview-qr d-none" id="preview-qr"><div class="live-preview-qr-code d-none" id="preview-qr-image" role="img" aria-label="QR ulasan pelanggan"></div><div class="qr-copy" id="preview-qr-message"></div></div></div></div><div class="live-preview-document-hint" id="preview-document-hint"></div></div></div></div>
</div>
<script src="<?= base_url('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
<script src="<?= base_url('assets/js/pos-local-qr.js') ?>?v=20260825g"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ui = window.PrinterConfigUI;
  const eventLabels = <?= json_encode($eventLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const documentLabels = <?= json_encode($documentLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const scopeLabels = <?= json_encode($scopeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
   const select = document.getElementById('preview-route'); const info = document.getElementById('preview-info'); const lines = document.getElementById('preview-lines'); const documentBadge = document.getElementById('preview-doc'); const paperInfo = document.getElementById('preview-paper-info'); const paper = document.getElementById('preview-paper'); const stage = document.querySelector('.live-preview-stage'); const logo = document.getElementById('preview-logo'); const previewQr = document.getElementById('preview-qr'); const previewQrImage = document.getElementById('preview-qr-image'); const previewQrMessage = document.getElementById('preview-qr-message'); const documentHint = document.getElementById('preview-document-hint');
   function clearPreviewQr() { previewQrMessage.textContent = ''; if (window.PosLocalQr) { window.PosLocalQr.clear(previewQrImage); } else { previewQrImage.textContent = ''; } previewQrImage.classList.add('d-none'); }
   function applyPreviewPaperMetrics(width, chars) {
    const paperPixels = width === 58 ? 319 : 440;
    const printablePixels = Math.max(200, paperPixels - 32);
    const fontSize = Math.max(10, Math.min(13, printablePixels / Math.max(1, chars) / 0.606));
    paper.className = 'live-preview-paper paper-' + width;
    paper.style.setProperty('--preview-font-size', fontSize.toFixed(2) + 'px');
  }
  async function load() {
    const id = Number(select.value || 0);
     if (!id) { info.textContent = 'Pilih aturan untuk melihat printer tujuan, layout, dan cakupan item yang dipakai.'; lines.textContent = 'Pilih aturan cetak di sebelah kiri.'; documentBadge.textContent = '-'; paperInfo.textContent = '-'; applyPreviewPaperMetrics(80, 48); logo.classList.add('d-none'); logo.removeAttribute('src'); previewQr.classList.add('d-none'); clearPreviewQr(); documentHint.classList.remove('is-visible'); documentHint.textContent = ''; return; }
    info.textContent = 'Membaca konfigurasi dari database...';
    try {
      const json = await ui.get('<?= site_url('pos/printers/preview-live/data') ?>/' + id);
      const route = json.route || {}; const template = json.template || {}; const preview = json.preview || {}; const width = Number(preview.paper_width_mm || route.paper_width_mm || 80) === 58 ? 58 : 80;
      const mode = String(route.print_mode || (Number(route.is_active) ? 'AUTO' : 'OFF')).toUpperCase();
      info.innerHTML = '<strong>Aturan: ' + ui.escapeHtml(route.route_name || '-') + '</strong><br>Event: ' + ui.escapeHtml(eventLabels[route.event_code] || route.event_code || '-') + '<br>Mode: ' + ui.escapeHtml(mode === 'ASK' ? 'Tanya dulu' : (mode === 'AUTO' ? 'Cetak otomatis' : 'Tidak cetak')) + '<br>Printer: ' + ui.escapeHtml(route.connection_name || '-') + '<br>Layout: ' + ui.escapeHtml(route.layout_name || '-') + '<br>Lokasi: ' + ui.escapeHtml(route.location_label || route.outlet_name || 'Semua lokasi') + '<br>Cakupan: ' + ui.escapeHtml(scopeLabels[route.content_scope] || route.content_scope || 'Semua item order');
      lines.textContent = Array.isArray(preview.lines) ? preview.lines.join('\n') : 'Preview belum tersedia.';
      if (stage) stage.scrollTop = 0;
      documentBadge.textContent = documentLabels[template.document_type] || template.document_type || '-';
      const chars = Number(preview.chars_per_line || (width === 58 ? 32 : 48));
      paperInfo.textContent = width + ' mm / ' + String(chars) + ' karakter';
      applyPreviewPaperMetrics(width, chars);
      if (preview.logo_url) { logo.src = String(preview.logo_url); logo.classList.remove('d-none'); } else { logo.classList.add('d-none'); logo.removeAttribute('src'); }
       const reviewQr = preview.customer_review_qr || {}; const documentType = String(template.document_type || '');
      if (reviewQr.enabled) {
        const qrUrl = String(reviewQr.url || '');
        previewQrMessage.textContent = String(reviewQr.message || 'Bagikan ulasan Anda dengan scan QR berikut.');
         if (qrUrl !== '') { const rendered = Boolean(window.PosLocalQr && window.PosLocalQr.render(previewQrImage, qrUrl, {size: 240, label: 'QR ulasan pelanggan'})); previewQrImage.classList.remove('d-none'); if (!rendered) previewQrMessage.textContent += ' (QR belum dapat dimuat. Muat ulang halaman.)'; } else { clearPreviewQr(); previewQrMessage.textContent += ' (QR area belum tersedia pada database.)'; }
        previewQr.classList.remove('d-none');
        } else { previewQr.classList.add('d-none'); clearPreviewQr(); }
       if (documentType !== 'RECEIPT') { documentHint.textContent = 'Dokumen ini adalah ' + String(documentLabels[documentType] || documentType || 'dokumen operasional') + '. QR ulasan pelanggan dan pesan kode voucher memang hanya dicetak pada Struk pembayaran.'; documentHint.classList.add('is-visible'); } else if (!reviewQr.enabled) { documentHint.textContent = 'Ini adalah struk pembayaran, tetapi QR ulasan belum tampil. Periksa saklar QR di Ulasan Pelanggan dan checkbox QR ulasan pada layout ini.'; documentHint.classList.add('is-visible'); } else { documentHint.classList.remove('is-visible'); documentHint.textContent = ''; }
     } catch (error) { info.textContent = error.message || 'Preview tidak dapat dibuka.'; lines.textContent = 'Preview tidak dapat dibuat.'; documentBadge.textContent = 'ERROR'; paperInfo.textContent = '-'; applyPreviewPaperMetrics(80, 48); previewQr.classList.add('d-none'); clearPreviewQr(); documentHint.classList.remove('is-visible'); documentHint.textContent = ''; }
  }
   select.addEventListener('change', load); if (select.value) { load(); }
});
</script>

