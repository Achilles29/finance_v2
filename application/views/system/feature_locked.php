<?php $d = $feature_decision; ?>
<div class="container-fluid py-4"><div class="card mx-auto" style="max-width:780px"><div class="card-body p-4 p-md-5">
  <span class="badge bg-label-warning mb-3" style="display:inline-flex;align-items:center;gap:6px">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4"/></svg> Upgrade
  </span>
  <h3><?= html_escape($d['feature_name']) ?></h3>
  <p>Paket Anda saat ini: <strong><?= html_escape($d['edition_name']) ?></strong>.</p>
  <p><?= $d['code'] === 'FEATURE_ACCESS_UNAVAILABLE' ? 'Akses fitur ini belum tersedia pada versi paket ini. Minta penjual memeriksa dukungan fiturnya.' : 'Fitur ini belum termasuk dalam hak paket Anda. Setelah upgrade disetujui dan lisensi tersinkron, fitur akan terbuka tanpa memasang ulang aplikasi.' ?></p>
  <p class="text-muted">Hak akun dan paket berbeda. Administrator customer juga mengikuti batas paket yang dibeli.</p>
  <div class="d-flex flex-wrap gap-2 mt-4"><a class="btn btn-primary" href="<?= site_url('system/feature-access') ?>">Lihat paket / Informasi upgrade</a><a class="btn btn-outline-secondary" href="<?= site_url('dashboard') ?>">Kembali ke beranda</a></div>
</div></div></div>
