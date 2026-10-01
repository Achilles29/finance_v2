<?php
$backupConfigured = !empty($backup_configured);
$hasRecentBackup = !empty($has_recent_backup);
$failoverActive = !empty($failover_active);
?>
<div class="fin-page-header mb-4">
  <div>
    <p class="fin-breadcrumb">Sistem / DB Tools</p>
    <h4 class="fin-page-title"><i class="ri-shield-keyhole-line me-1 text-primary"></i>Perlindungan Database</h4>
    <p class="fin-page-subtitle mb-0">Ringkasan aman tanpa alamat server, nama database, daftar file, log, atau credential.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Konfigurasi backup</div><div class="fw-bold mt-1"><?= $backupConfigured ? 'Tersedia' : 'Belum tersedia' ?></div></div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Backup lokal</div><div class="fw-bold mt-1"><?= $hasRecentBackup ? 'Sudah pernah dibuat' : 'Belum ditemukan' ?></div></div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Mode darurat</div><div class="fw-bold mt-1 <?= $failoverActive ? 'text-warning' : 'text-success' ?>"><?= $failoverActive ? 'Aktif' : 'Tidak aktif' ?></div></div></div></div>
</div>

<div class="alert alert-warning border-0">
  <div class="fw-semibold mb-1">Detail teknis disembunyikan</div>
  <div class="small">Untuk melihat konfigurasi, nama dump, log, path server, dan status replication, admin perlu memberi hak <strong>Export</strong> pada halaman <strong>DB Tools Settings</strong> melalui menu Role &amp; Permission. Hak View saja hanya menampilkan ringkasan aman ini.</div>
</div>
