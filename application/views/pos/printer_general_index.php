<?php
$generalRow = is_array($general ?? null) ? $general : [];
$payload = is_array($generalRow['payload'] ?? null) ? $generalRow['payload'] : [];
$headerLines = implode("\n", (array)($payload['header_lines'] ?? []));
$footerLines = implode("\n", (array)($payload['footer_lines'] ?? []));
$canEdit = !empty($can_edit);
$csrfToken = (string)($printer_general_csrf_token ?? '');
?>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3"><div><h4 class="fin-page-title mb-1">Tampilan Umum Cetak</h4><p class="fin-page-subtitle mb-0">Data master yang sama untuk seluruh cetakan: nama usaha, logo, alamat, Wi-Fi, dan pesan penutup.</p></div></div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'general']); ?>
  <div class="print-config-note mb-3"><strong>Diisi sekali, dipakai bersama.</strong> Halaman ini menyimpan nama usaha, logo, Wi-Fi, serta format pesan voucher. Printer tujuan dan data yang tampil diatur dari <a href="<?= site_url('pos/printers/layouts') ?>">Layout Dokumen</a> dan <a href="<?= site_url('pos/printers/rules') ?>">Aturan Cetak</a>. Pengaturan serta QR cetak ulasan pelanggan dikelola khusus di <a href="<?= site_url('pos/customer-reviews') ?>">Ulasan Pelanggan</a>.</div>
  <form method="post" enctype="multipart/form-data" class="card print-config-card"><input type="hidden" name="pos_printer_general_csrf" value="<?= html_escape($csrfToken) ?>"><fieldset <?= $canEdit ? '' : 'disabled' ?>><div class="card-body"><div class="row g-3">
    <div class="col-md-7"><label class="form-label">Nama usaha / judul cetak</label><input class="form-control" name="title" required value="<?= html_escape((string)($payload['title'] ?? '')) ?>"></div>
    <div class="col-md-5"><label class="form-label">Subjudul / alamat singkat</label><input class="form-control" name="subtitle" value="<?= html_escape((string)($payload['subtitle'] ?? '')) ?>"></div>
    <div class="col-12"><label class="form-label">Logo pada struk</label><div class="border rounded p-3 bg-light"><div class="d-flex flex-wrap align-items-center gap-3"><img id="printer-general-logo-preview" src="<?= html_escape((string)($payload['logo_url'] ?? '')) ?>" data-active-src="<?= html_escape((string)($payload['logo_url'] ?? '')) ?>" alt="Logo aktif pada struk" style="width:84px;max-height:84px;object-fit:contain" class="bg-white border rounded p-1"><div class="flex-grow-1"><div id="printer-general-logo-preview-title" class="fw-semibold">Logo aktif</div><div class="small text-muted mb-2">Pilih gambar baru untuk melihat preview, lalu simpan untuk menggantinya. Bila tidak memilih file, logo saat ini tetap dipakai.</div><input id="printer-general-logo-file" type="file" class="form-control" name="logo_file" accept="image/png,image/jpeg"><div class="form-text">Format PNG atau JPG, maksimum 1 MB dan 2048 × 2048 piksel. Logo diunggah ke aplikasi, bukan dari tautan internet.</div></div></div></div></div>
    <div class="col-md-6"><label class="form-label">Baris pembuka</label><textarea class="form-control" rows="4" name="header_lines" placeholder="Satu baris per pesan"><?= html_escape($headerLines) ?></textarea><div class="form-text">Layout menentukan apakah baris ini akan ditampilkan pada jenis dokumen tertentu.</div></div>
    <div class="col-md-6"><label class="form-label">Baris penutup</label><textarea class="form-control" rows="4" name="footer_lines" placeholder="Satu baris per pesan"><?= html_escape($footerLines) ?></textarea><div class="form-text">Gunakan untuk ucapan terima kasih atau informasi umum yang stabil.</div></div>
    <div class="col-md-6"><label class="form-label">Nama Wi-Fi</label><input class="form-control" name="wifi_name" value="<?= html_escape((string)($payload['wifi_name'] ?? '')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Password Wi-Fi</label><input class="form-control" name="wifi_password" value="<?= html_escape((string)($payload['wifi_password'] ?? '')) ?>"></div>
    <div class="col-12"><div class="print-config-note mb-0"><strong>Informasi pelanggan diatur per layout.</strong> Centang poin, stamp, atau voucher hanya pada layout struk yang memang boleh menampilkannya. KOT dan bill yang tidak perlu dapat dibiarkan bersih.</div></div>
    <div class="col-md-3"><label class="form-label">Batas voucher pada struk</label><input type="number" min="1" max="5" class="form-control" name="customer_voucher_limit" value="<?= (int)($payload['customer_voucher_limit'] ?? 1) ?>"></div>
    <div class="col-md-3"><label class="form-label">Posisi pesan voucher</label><select class="form-select" name="customer_voucher_align"><?php foreach (['LEFT'=>'Kiri','CENTER'=>'Tengah','RIGHT'=>'Kanan'] as $key=>$label): ?><option value="<?= $key ?>" <?= (($payload['customer_voucher_align'] ?? 'CENTER') === $key) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Format pesan voucher</label><input class="form-control" name="customer_voucher_message_template" value="<?= html_escape((string)($payload['customer_voucher_message_template'] ?? '')) ?>" placeholder="Gunakan {voucher_benefit}, {voucher_code}, dan {voucher_expiry}"><div class="form-text">Kode voucher selalu dicetak agar customer dapat langsung memakainya kembali.</div></div>
    <div class="col-12"><div class="print-config-note mb-0"><strong>QR ulasan pelanggan dikelola terpisah.</strong> Aktifkan QR struk, atur pesannya, atau cetak QR area pengunjung dari <a href="<?= site_url('pos/customer-reviews') ?>">Ulasan Pelanggan</a>. Pemisahan ini mencegah pengaturan QR tampil dua kali di halaman yang berbeda.</div></div>
  </div></div></fieldset><div class="card-footer bg-white border-0 pt-0 pb-3 text-end"><?php if ($canEdit): ?><button class="btn btn-primary"><?php $this->load->view('pos/_icon', ['name' => 'save', 'label' => '']); ?><span class="ms-1">Simpan Tampilan Umum</span></button><?php else: ?><span class="small text-muted">Akses Anda hanya untuk melihat pengaturan ini.</span><?php endif; ?></div></form>
</div>
<script>
(function () {
  const input = document.getElementById('printer-general-logo-file');
  const preview = document.getElementById('printer-general-logo-preview');
  const title = document.getElementById('printer-general-logo-preview-title');
  if (!input || !preview || !title || !window.URL || typeof window.URL.createObjectURL !== 'function') return;

  let previewUrl = '';
  const clearPreviewUrl = function () {
    if (previewUrl !== '') {
      window.URL.revokeObjectURL(previewUrl);
      previewUrl = '';
    }
  };
  input.addEventListener('change', function () {
    clearPreviewUrl();
    const file = input.files && input.files[0];
    if (!file) {
      preview.src = preview.dataset.activeSrc || '';
      title.textContent = 'Logo aktif';
      return;
    }
    previewUrl = window.URL.createObjectURL(file);
    preview.src = previewUrl;
    title.textContent = 'Preview logo baru: ' + file.name;
  });
  window.addEventListener('beforeunload', clearPreviewUrl);
}());
</script>
