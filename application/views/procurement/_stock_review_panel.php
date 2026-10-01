<?php $reviewCsrf = (string)($stock_review_csrf ?? ''); ?>
<section class="card mb-3" id="procurementStockReview"
 data-url="<?= html_escape(site_url('procurement/division-po-sr/stock-preview')) ?>"
 data-csrf="<?= html_escape($reviewCsrf) ?>" data-request-id="<?= (int)($request_id ?? 0) ?>"
 data-verify="<?= !empty($can_verify) ? '1' : '0' ?>" data-line-review="<?= !empty($line_review) ? '1' : '0' ?>">
 <div class="card-body">
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
   <h6 class="mb-0">Cek stok sebelum menyetujui kebutuhan</h6>
   <button class="btn btn-outline-primary btn-sm" type="button" data-stock-refresh>Perbarui cek stok</button>
  </div>
  <p class="small text-muted">Stok bahan baku divisi peminta dan gudang disamakan ke satuan isi pengajuan. Saldo merupakan total material lintas profil pada lokasi tersebut, bukan reservasi atau jaminan profil tersedia untuk SR. Snapshot di tabel pengajuan tetap dibedakan dari cek terbaru ini.</p>
  <div data-stock-status role="status" aria-live="polite">Tambahkan barang dan pilih divisi/lokasi untuk memeriksa stok.</div>
  <div data-stock-rows class="row g-2 my-2"></div>
  <?php if (!empty($can_verify)): ?>
  <div data-stock-confirmation hidden>
   <div class="alert alert-warning">Ada stok tersisa, saldo negatif, atau stok belum diketahui. Hubungi divisi terlebih dahulu. Pengajuan tetap dapat disetujui setelah kebutuhan dikonfirmasi dan alasannya dicatat.</div>
   <div class="row g-2">
    <div class="col-md-4"><label for="stockConfirmedWith" class="form-label">Nama pihak divisi yang dikonfirmasi</label><input id="stockConfirmedWith" data-stock-contact class="form-control" maxlength="150" autocomplete="off"></div>
    <div class="col-md-8"><label for="stockReviewReason" class="form-label">Alasan kebutuhan / hasil konfirmasi</label><textarea id="stockReviewReason" data-stock-reason class="form-control" rows="2" maxlength="1000" placeholder="Contoh: persiapan event besok; sudah dikonfirmasi dengan kepala divisi."></textarea></div>
   </div>
   <label class="d-flex gap-2 align-items-start mt-3"><input data-stock-confirmed type="checkbox" class="form-check-input mt-1"><span>Saya sudah mengonfirmasi kebutuhan seluruh baris yang perlu diperiksa di atas kepada divisi.</span></label>
  </div>
  <?php endif; ?>
  <p class="small text-muted mb-0">Saat disimpan, stok dan isi pengajuan dicek lagi. Jika berubah atau tinjauan lebih dari 15 menit, perbarui lalu konfirmasi ulang. Pencatatan ini tidak mengubah saldo.</p>
  <noscript><div class="alert alert-warning">Aktifkan JavaScript untuk membaca stok terbaru dan memverifikasi pengajuan bahan baku. Jangan melanjutkan berdasarkan snapshot lama.</div></noscript>
 </div>
</section>
<input type="hidden" name="procurement_csrf" value="<?= html_escape($reviewCsrf) ?>">
<input type="hidden" name="stock_review_json" id="stockReviewJson" value="">
<script src="<?= html_escape(base_url('assets/js/procurement-current-stock.js?v=20260920')) ?>" defer></script>
<script src="<?= html_escape(base_url('assets/js/procurement-stock-review.js?v=20260923line')) ?>" defer></script>
