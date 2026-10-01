<?php
$financeRoot    = (string)($finance_root ?? '');
$recentDumps    = is_array($recent_dumps ?? null) ? $recent_dumps : [];
$envExists      = !empty($env_exists);
$envConfigured  = !empty($env_configured);
$scriptDir      = rtrim($financeRoot, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'backup';
$cronExample    = rtrim($financeRoot, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'crontab.example';
?>

<style>
  .guide-card { border:0; border-radius:16px; box-shadow:0 4px 16px rgba(58,38,30,.07); }
  .guide-step { display:flex; gap:1rem; align-items:flex-start; margin-bottom:1.2rem; }
  .guide-step-num { width:28px; height:28px; min-width:28px; border-radius:50%; background:#9f2141; color:#fff; font-size:.78rem; font-weight:800; display:flex; align-items:center; justify-content:center; }
  .guide-code { background:#1e1e2e; color:#cdd6f4; border-radius:10px; padding:.9rem 1rem; font-family:monospace; font-size:.82rem; overflow-x:auto; white-space:pre; }
  .guide-status-ok  { background:#dcfce7; color:#166534; }
  .guide-status-warn { background:#fff3cd; color:#856404; }
  .guide-status-err  { background:#fee2e2; color:#991b1b; }
  .dump-row { display:flex; justify-content:space-between; align-items:center; padding:.4rem .6rem; border-bottom:1px solid rgba(224,209,198,.5); }
  .dump-row:last-child { border-bottom:0; }
</style>

<div class="fin-page-header mb-4">
  <div>
    <p class="fin-breadcrumb">Sistem / Panduan</p>
    <h4 class="fin-page-title"><i class="ri ri-database-2-line me-1 text-primary"></i>Backup Database — Skema 1</h4>
    <p class="fin-page-subtitle mb-0">Backup otomatis via mysqldump. Dump dan log disimpan lokal sesuai masa retensi.</p>
  </div>
  <div class="fin-page-actions">
    <a href="<?php echo site_url('dbtools'); ?>" class="btn btn-outline-info btn-sm">
      <i class="ri ri-server-line me-1"></i>Skema 2 — Replication
    </a>
  </div>
</div>

<!-- Status kartu -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card guide-card <?php echo $envConfigured ? 'guide-status-ok' : 'guide-status-warn'; ?> p-3">
      <div class="small fw-bold mb-1">File Konfigurasi (.env)</div>
      <div class="fw-semibold">
        <?php if ($envConfigured): ?>
          <i class="ri ri-checkbox-circle-line me-1"></i>Tersedia dan dikonfigurasi
        <?php elseif ($envExists): ?>
          <i class="ri ri-alert-line me-1"></i>Ada tapi mungkin belum diisi
        <?php else: ?>
          <i class="ri ri-close-circle-line me-1"></i>Belum ada — buat dari .env.example
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card guide-card <?php echo !empty($recentDumps) ? 'guide-status-ok' : 'guide-status-warn'; ?> p-3">
      <div class="small fw-bold mb-1">Dump Terakhir</div>
      <div class="fw-semibold">
        <?php if (!empty($recentDumps)): ?>
          <i class="ri ri-checkbox-circle-line me-1"></i><?php echo html_escape($recentDumps[0]['date']); ?>
        <?php else: ?>
          <i class="ri ri-alert-line me-1"></i>Belum ada dump tersimpan
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card guide-card p-3">
      <div class="small fw-bold mb-1">Total Dump Tersimpan</div>
      <div class="fw-semibold"><?php echo count($recentDumps); ?> file</div>
    </div>
  </div>
</div>

<!-- Langkah setup -->
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">Setup Backup Otomatis (Linux/Server)</div>
  <div class="card-body">

    <div class="guide-step">
      <div class="guide-step-num">1</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Buat file konfigurasi .env</div>
        <div class="text-muted small mb-2">Di folder <code>scripts/backup/</code>, salin .env.example menjadi .env lalu isi sesuai server kamu.</div>
        <pre class="guide-code">cd <?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/scripts/backup
cp .env.example .env
nano .env        # Edit DB_USER, DB_PASS, DB_NAME, RETENTION_DAYS, dst.</pre>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">2</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Beri permission execute pada script</div>
        <pre class="guide-code">chmod +x <?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/scripts/backup/backup_full.sh
chmod +x <?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/scripts/replication/*.sh</pre>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">3</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Test manual sekali</div>
        <pre class="guide-code"><?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/scripts/backup/backup_full.sh</pre>
        <div class="text-muted small mt-1">Cek apakah file .sql.gz muncul di <code>backup/dumps/</code>. Runner ini menyimpan backup lokal saja.</div>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">4</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jadwalkan via cron (setiap 30 menit)</div>
        <pre class="guide-code">crontab -e

# Tambahkan baris ini:
*/30 * * * * <?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/scripts/backup/backup_full.sh >> <?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/backup/logs/cron.log 2>&amp;1</pre>
        <div class="text-muted small mt-1">Cek crontab.example di <code>scripts/cron/crontab.example</code> untuk referensi lengkap.</div>
      </div>
    </div>

  </div>
</div>

<!-- Setup Windows -->
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">Setup Backup Otomatis (Windows / Laptop Lokal)</div>
  <div class="card-body">
    <div class="guide-step">
      <div class="guide-step-num">1</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Buat file .env di <code>scripts\backup\</code></div>
        <pre class="guide-code">copy scripts\backup\.env.example scripts\backup\.env
notepad scripts\backup\.env</pre>
      </div>
    </div>
    <div class="guide-step">
      <div class="guide-step-num">2</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jadwalkan via Windows Task Scheduler</div>
        <div class="text-muted small mb-2">Buka Task Scheduler → Create Task → Trigger: setiap 30 menit → Action:</div>
        <pre class="guide-code">Program : C:\Windows\System32\cmd.exe
Arguments: /c "<?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>\scripts\backup\backup_full.bat"</pre>
      </div>
    </div>
  </div>
</div>

<!-- File dump terkini -->
<?php if (!empty($recentDumps)): ?>
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">Dump Tersimpan Terakhir</div>
  <div class="card-body p-0">
    <?php foreach ($recentDumps as $dump): ?>
      <div class="dump-row">
        <div>
          <div class="fw-semibold small"><?php echo html_escape($dump['name']); ?></div>
          <div class="text-muted" style="font-size:.75rem"><?php echo html_escape($dump['date']); ?></div>
        </div>
        <span class="badge bg-secondary"><?php echo html_escape($dump['size']); ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Catatan penting -->
<div class="card guide-card border-warning">
  <div class="card-body">
    <div class="fw-bold mb-2"><i class="ri ri-alert-line me-1 text-warning"></i>Catatan Penting</div>
    <ul class="mb-0 small">
      <li>File dump dan log hasil runner diabaikan oleh kontrol versi; placeholder <code>.gitkeep</code> tetap dipertahankan.</li>
      <li>File dump lokal otomatis dihapus setelah <strong>3 hari</strong> (konfigurasi <code>RETENTION_DAYS</code>).</li>
      <li>Jika membutuhkan salinan di luar host, konfigurasi backup off-site terenkripsi secara terpisah dari runner ini.</li>
    </ul>
  </div>
</div>
