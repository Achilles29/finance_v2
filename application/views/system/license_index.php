<?php
$installation = is_array($installation ?? null) ? $installation : [];
$license = is_array($license ?? null) ? $license : [];
$active = strtoupper((string)($installation['activation_status'] ?? 'UNACTIVATED'));
$sync = is_array($synchronization ?? null) ? $synchronization : [];
$connectionLabels = ['UNACTIVATED'=>'Belum diminta','PENDING'=>'Menunggu penerbitan dari Control','SYNCED'=>'Lisensi tersinkron',
    'SYNC_UNAVAILABLE'=>'Sinkronisasi belum berhasil; periksa status lisensi lokal di bawah','REVOKED'=>'Aktivasi dicabut melalui Control','UNAVAILABLE'=>'Konfigurasi perlu diperiksa admin server'];
$connection = (string)($sync['connection'] ?? 'UNAVAILABLE');
$lastSync = is_int($sync['synced_at'] ?? null) ? $sync['synced_at'] : 0;
$customerGuard = (string)getenv('FINANCE_CUSTOMER_INSTALLATION_FILE') !== '';
?>
<div class="container-fluid py-3">
  <?php if(!empty($can_manage_updates)): ?><div class="mb-3"><a class="btn btn-outline-primary" href="<?= site_url('system/updates') ?>">Pembaruan aplikasi</a></div><?php endif; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h4 class="mb-1">Lisensi &amp; Aktivasi</h4><p class="text-muted mb-0">Status entitlement lokal tanpa menampilkan token atau rahasia penerbit.</p></div><span class="badge <?= ($mode ?? '') === 'ENFORCE' ? 'text-bg-warning' : 'text-bg-info' ?> p-2">Mode <?= html_escape((string)($mode ?? 'AUDIT_ONLY')) ?></span></div>
  <?php if ($customerGuard): ?><div class="alert alert-info"><strong>Pembatasan instalasi server aktif.</strong> Aplikasi hanya terbuka dengan lisensi Control bertanda tangan yang cocok dengan identitas dan mesin ini, berstatus ACTIVE atau GRACE. Tanpa lisensi sah, hanya halaman login, logout dan status lisensi yang tetap tersedia untuk pemulihan. Mode fitur di atas adalah pemeriksaan entitlement modul, terpisah dari kunci instalasi server.</div>
  <?php elseif (($mode ?? 'AUDIT_ONLY') !== 'ENFORCE'): ?><div class="alert alert-success"><i class="ri-shield-check-line me-1"></i><strong>Mode pemantauan aktif.</strong> Lisensi dipantau, tetapi belum membatasi POS, laporan, maupun data pada instalasi ini.</div><?php endif; ?>
  <div class="card mb-3"><div class="card-body"><h5 class="card-title">Sambungan ke Control</h5>
    <?php if (!empty($sync['configured'])): ?>
      <p class="mb-1"><?= html_escape($connectionLabels[$connection] ?? 'Status perlu diperiksa admin server') ?></p>
      <div class="small text-muted">Terakhir menerima lisensi sah: <?= html_escape($lastSync > 0 ? date('d/m/Y H:i:s', $lastSync) : 'Belum pernah') ?>.</div>
      <div class="small mt-2">Instance: <?= html_escape((string)($installation['instance_id'] ?? '-')) ?> · ID instalasi: <?= html_escape((string)($installation['installation_id'] ?? '-')) ?></div>
    <?php else: ?><p class="mb-0">Admin server belum memasang sambungan lisensi. Penjual mengatur customer, paket, dan kode aktivasi melalui UI Control; admin server memasang agen pada instalasi customer. Tidak perlu mengubah tabel lisensi secara manual.</p><?php endif; ?>
  </div></div>
  <div class="alert <?= !empty($verification['verified']) ? 'alert-info' : 'alert-warning' ?>"><strong>Verifikasi tanda tangan Control:</strong> <?= html_escape((string)($verification['status'] ?? 'UNCONFIGURED')) ?><div class="small mt-1"><?= html_escape((string)($verification['code'] ?? 'DEPLOYMENT_TRUST_NOT_CONFIGURED')) ?>. Status VERIFIED pada tabel lokal saja tidak cukup; dokumen harus cocok dengan kunci publik Control dan identitas instalasi yang disiapkan admin server. <?= $customerGuard ? 'Instalasi customer ini juga memeriksa fingerprint mesin dan integritas bootstrap sebelum membuka aplikasi.' : 'Mode pemantauan tetap tidak memblokir kasir.' ?></div></div>
  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
      <div class="text-muted small">Instalasi</div><div class="fw-semibold mt-1"><?= html_escape($active) ?></div>
      <div class="small text-muted mt-2">Identitas dibuat sekali oleh agen server sebelum meminta aktivasi. Jangan membuat ulang identitas saat koneksi terganggu.</div>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
      <div class="text-muted small">Lisensi lokal</div><div class="fw-semibold mt-1"><?= html_escape((string)($license['verification_status'] ?? 'BELUM ADA')) ?></div>
      <div class="small text-muted mt-2">Edisi: <?= html_escape((string)($license['edition_code'] ?? '-')) ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
      <div class="text-muted small">Maintenance</div><div class="fw-semibold mt-1"><?= html_escape((string)($license['maintenance_ends_at'] ?? '-')) ?></div>
      <div class="small text-muted mt-2">Berakhirnya maintenance tidak boleh menghentikan transaksi atau akses data.</div>
    </div></div></div>
  </div>
  <div class="card"><div class="card-header fw-semibold">Katalog fitur aplikasi</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Fitur</th><th>Kategori</th><th>Keputusan saat ini</th></tr></thead><tbody><?php foreach ((array)($features ?? []) as $feature): ?><tr><td><div class="fw-semibold"><?= html_escape((string)$feature['name']) ?></div><small class="text-muted"><?= html_escape((string)$feature['code']) ?></small></td><td><?= html_escape((string)$feature['category']) ?></td><td><span class="badge text-bg-secondary"><?= html_escape((string)($feature['decision']['code'] ?? 'UNKNOWN')) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
  <div class="alert alert-light border mt-3 mb-0 small"><strong>Penjualan dan aktivasi:</strong> paket, customer, serta kode aktivasi dikelola melalui UI Control. Agen server menerima dan memeriksa lisensi, lalu aplikasi membaca salinan lokalnya. Gangguan koneksi tidak menghapus lisensi yang sah; akses mengikuti batas lease dan grace yang ditandatangani. <?= $customerGuard ? 'Kuota lisensi dihitung per instalasi server, bukan jumlah user atau domain. PHP yang dapat diedit oleh pemilik root belum merupakan native guard atau jaminan anti-bypass.' : 'Pairing terminal, native guard dan penerimaan enforcement masih perlu diselesaikan sebelum mode pembatasan diaktifkan.' ?> Tidak ada tombol lokal untuk menaikkan paket.</div>
</div>
