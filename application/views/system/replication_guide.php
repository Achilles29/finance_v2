<?php
$replStatus    = is_array($repl_status ?? null) ? $repl_status : [];
$failoverActive = !empty($failover_active);
$failoverTime   = (string)($failover_time ?? '');
$financeRoot    = (string)($finance_root ?? '');
$scriptDir      = rtrim($financeRoot, '/\\') . '/scripts/replication';

$statusLabel = $replStatus['status'] ?? '-';
$serverRole  = $replStatus['role']   ?? '-';
$lag         = (int)($replStatus['lag_seconds'] ?? 0);
?>
<style>
  .guide-card { border:0; border-radius:16px; box-shadow:0 4px 16px rgba(58,38,30,.07); }
  .guide-step { display:flex; gap:1rem; align-items:flex-start; margin-bottom:1.2rem; }
  .guide-step-num { width:28px; height:28px; min-width:28px; border-radius:50%; background:#9f2141; color:#fff; font-size:.78rem; font-weight:800; display:flex; align-items:center; justify-content:center; }
  .guide-code { background:#1e1e2e; color:#cdd6f4; border-radius:10px; padding:.9rem 1rem; font-family:monospace; font-size:.82rem; overflow-x:auto; white-space:pre; }
  .flow-box { border:2px solid #d6e0dc; border-radius:12px; padding:.65rem .9rem; background:#f8fdfc; text-align:center; font-weight:700; font-size:.88rem; }
  .flow-box.master { border-color:#9f2141; background:#fff5f5; }
  .flow-box.slave  { border-color:#1a6450; background:#f0fdf8; }
  .flow-box.offline { border-color:#aaa; background:#f5f5f5; opacity:.7; }
  .flow-arrow { text-align:center; font-size:1.3rem; color:#888; line-height:1; }
  .status-ok  { background:#dcfce7; color:#166534; }
  .status-warn { background:#fff3cd; color:#856404; }
  .status-err  { background:#fee2e2; color:#991b1b; }
  .failover-banner { background:linear-gradient(135deg,#fef3c7,#fffbeb); border:2px solid #f59e0b; border-radius:14px; padding:1rem 1.2rem; margin-bottom:1.5rem; }
</style>

<div class="fin-page-header mb-4">
  <div>
    <p class="fin-breadcrumb">Sistem / Panduan</p>
    <h4 class="fin-page-title"><i class="ri ri-server-line me-1 text-primary"></i>Replication &amp; Failover — Skema 2</h4>
    <p class="fin-page-subtitle mb-0">MySQL Master-Slave replication dengan failover manual dan sync terverifikasi.</p>
  </div>
  <div class="fin-page-actions">
    <a href="<?php echo site_url('dbtools'); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri ri-database-2-line me-1"></i>Skema 1 — Backup
    </a>
  </div>
</div>

<!-- Failover active warning -->
<?php if ($failoverActive): ?>
<div class="failover-banner">
  <div class="fw-bold mb-1"><i class="ri ri-alert-line me-1 text-warning"></i>FAILOVER SEDANG AKTIF</div>
  <div class="small">
    Failover dimulai: <strong><?php echo html_escape($failoverTime); ?></strong><br>
    Server 2 saat ini beroperasi sebagai master sementara.<br>
    Setelah Server 1 online, jalankan: <code>scripts/replication/recovery_sync.sh</code>
  </div>
</div>
<?php endif; ?>

<!-- Arsitektur visual -->
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">Arsitektur Replication</div>
  <div class="card-body">
    <div class="row g-3 align-items-center text-center">
      <div class="col-md-3">
        <div class="flow-box master">
          <div><i class="ri ri-server-2-line ri-lg"></i></div>
          <div class="mt-1">Server 1</div>
          <div class="small fw-normal text-muted">MASTER (aktif)</div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="flow-arrow">
          ──binlog──►
          <div class="small text-muted mt-1">real-time</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="flow-box slave">
          <div><i class="ri ri-server-line ri-lg"></i></div>
          <div class="mt-1">Server 2</div>
          <div class="small fw-normal text-muted">SLAVE (backup)</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="small text-muted">
          <div class="fw-bold mb-1">Kunci: auto_increment_offset</div>
          Server 1 → ID ganjil (1,3,5...)<br>
          Server 2 → ID genap (2,4,6...)<br>
          Tidak ada ID bentrok saat failover
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Status saat ini -->
<?php if (!empty($replStatus)): ?>
<div class="card guide-card mb-4 <?php echo $statusLabel === 'OK' ? 'status-ok' : ($statusLabel === 'ERROR' ? 'status-err' : 'status-warn'); ?>">
  <div class="card-body py-2 d-flex gap-3 align-items-center flex-wrap">
    <div>
      <div class="small fw-bold">Status Replication</div>
      <div class="fw-semibold"><?php echo html_escape($statusLabel); ?></div>
    </div>
    <div>
      <div class="small fw-bold">Role</div>
      <div><?php echo html_escape($serverRole); ?></div>
    </div>
    <?php if (isset($replStatus['lag_seconds'])): ?>
    <div>
      <div class="small fw-bold">Lag</div>
      <div><?php echo $lag; ?>s</div>
    </div>
    <?php endif; ?>
    <div>
      <div class="small fw-bold">Update Terakhir</div>
      <div class="small"><?php echo html_escape($replStatus['timestamp'] ?? '-'); ?></div>
    </div>
    <?php if (!empty($replStatus['error'])): ?>
    <div class="flex-fill">
      <div class="small fw-bold">Error</div>
      <div class="small"><?php echo html_escape($replStatus['error']); ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Langkah setup -->
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">Setup Awal (Sekali)</div>
  <div class="card-body">

    <div class="guide-step">
      <div class="guide-step-num">1</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Konfigurasi my.cnf di Server 1 (Master)</div>
        <pre class="guide-code">[mysqld]
server-id                = 1
log_bin                  = mysql-bin
binlog_format            = ROW
expire_logs_days         = 7
gtid_mode                = ON
enforce_gtid_consistency = ON
auto_increment_increment = 2   # Step: 1,3,5,7...
auto_increment_offset    = 1   # Server 1 = ID ganjil</pre>
        <div class="text-muted small mt-1">Setelah edit: <code>sudo systemctl restart mysql</code></div>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">2</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Konfigurasi my.cnf di Server 2 (Slave)</div>
        <pre class="guide-code">[mysqld]
server-id                = 2
log_bin                  = mysql-bin
binlog_format            = ROW
gtid_mode                = ON
enforce_gtid_consistency = ON
read_only                = ON    # Slave hanya baca saat normal
relay_log                = relay-bin
log_slave_updates        = ON
auto_increment_increment = 2   # Step: 2,4,6,8...
auto_increment_offset    = 2   # Server 2 = ID genap</pre>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">3</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jalankan setup script di Server 1</div>
        <pre class="guide-code">scripts/replication/setup_master.sh</pre>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">4</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jalankan setup script di Server 2</div>
        <pre class="guide-code">scripts/replication/setup_slave.sh</pre>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">5</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jadwalkan health check setiap 5 menit (di Server 2)</div>
        <pre class="guide-code">crontab -e
*/5 * * * * <?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/scripts/replication/health_check.sh >> <?php echo htmlspecialchars(rtrim($financeRoot, '/\\')); ?>/backup/logs/replication.log 2>&amp;1</pre>
      </div>
    </div>
  </div>
</div>

<!-- Prosedur failover -->
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">Prosedur Failover (Server 1 DOWN)</div>
  <div class="card-body">
    <div class="alert alert-warning border-0 small mb-3">
      Failover bersifat <strong>MANUAL</strong>. Tidak ada otomatisasi agar tidak ada perubahan tidak disengaja.
    </div>

    <div class="guide-step">
      <div class="guide-step-num">1</div>
      <div class="flex-fill">
        <div class="fw-semibold">Konfirmasi Server 1 benar-benar tidak bisa diakses</div>
        <div class="text-muted small">Cek di browser, ping, SSH. Jangan promosi slave jika Master hanya lambat.</div>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">2</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jalankan di Server 2:</div>
        <pre class="guide-code">scripts/replication/failover_promote_slave.sh</pre>
        <div class="text-muted small">Script ini menghentikan slave, aktifkan write mode, catat waktu failover.</div>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">3</div>
      <div class="flex-fill">
        <div class="fw-semibold">User pakai Server 2 sampai Server 1 kembali</div>
        <div class="text-muted small">Semua transaksi masuk Server 2. Data aman karena auto_increment genap.</div>
      </div>
    </div>
  </div>
</div>

<!-- Recovery -->
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">Recovery (Server 1 Kembali Online)</div>
  <div class="card-body">

    <div class="guide-step">
      <div class="guide-step-num">1</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jalankan script perbandingan data</div>
        <pre class="guide-code">scripts/replication/recovery_sync.sh</pre>
        <div class="text-muted small">Script akan membandingkan data Server 2 vs Server 1 berdasarkan <code>updated_at ≥ failover_time</code> dan <code>id</code>.</div>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">2</div>
      <div class="flex-fill">
        <div class="fw-semibold">Periksa diff report dan sync SQL yang dihasilkan</div>
        <div class="text-muted small">File tersimpan di <code>backup/sync_YYYYMMDD_HHMMSS/</code></div>
        <ul class="small text-muted mt-1">
          <li>Jika 0 perbedaan → aman, langsung restart replication</li>
          <li>Jika ada perbedaan → periksa SQL yang dihasilkan, jalankan di Server 1 setelah yakin</li>
        </ul>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">3</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Restart replication (Server 2 kembali jadi Slave)</div>
        <pre class="guide-code">scripts/replication/restart_replication.sh</pre>
      </div>
    </div>
  </div>
</div>

<!-- Opsi Laptop sebagai Slave -->
<div class="card guide-card mb-4">
  <div class="card-header py-2 fw-semibold">
    <i class="ri ri-laptop-line me-1"></i>Opsi: Laptop Lokal sebagai Server 2 (Tanpa IP Publik)
  </div>
  <div class="card-body">
    <div class="alert alert-info border-0 small mb-3">
      <strong>Kabar baik:</strong> MySQL replication memang bekerja dengan <strong>Slave yang inisiatif koneksi ke Master</strong>.
      Laptop tanpa IP publik bisa jadi Slave selama bisa akses internet dan terhubung ke Server 1.
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border h-100 p-3">
          <div class="fw-bold mb-1 text-success">✓ Opsi A — SSH Tunnel</div>
          <div class="small text-muted mb-2">Paling aman, tidak perlu buka port MySQL ke publik</div>
          <ul class="small mb-0">
            <li>Laptop membuat tunnel SSH ke Server 1</li>
            <li>MySQL replication berjalan dalam tunnel</li>
            <li>Server 1 port 3306 <strong>tidak perlu</strong> dibuka ke publik</li>
          </ul>
          <div class="mt-2">
            <span class="badge bg-success">Recommended</span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border h-100 p-3">
          <div class="fw-bold mb-1">Opsi B — Direct Connection</div>
          <div class="small text-muted mb-2">Mudah setup, tapi butuh port MySQL terbuka</div>
          <ul class="small mb-0">
            <li>Laptop connect langsung ke Server 1:3306</li>
            <li>Server 1 harus buka port 3306 ke internet</li>
            <li>Risiko keamanan jika tidak dikonfigurasi ketat</li>
          </ul>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border h-100 p-3">
          <div class="fw-bold mb-1">Opsi C — Polling Sync</div>
          <div class="small text-muted mb-2">Tidak real-time, tapi paling fleksibel</div>
          <ul class="small mb-0">
            <li>Laptop tarik data dari Server 1 setiap X menit</li>
            <li>Tidak butuh replication native MySQL</li>
            <li>Cocok jika laptop sering offline</li>
          </ul>
        </div>
      </div>
    </div>

    <hr>

    <div class="fw-semibold mb-3">Opsi A — SSH Tunnel (Recommended)</div>

    <div class="guide-step">
      <div class="guide-step-num">1</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Buat SSH tunnel dari laptop ke Server 1</div>
        <div class="text-muted small mb-1"><strong>Linux/Mac/Windows (OpenSSH):</strong></div>
        <pre class="guide-code"># Jalankan di laptop — teruskan port 3307 lokal ke port 3306 Server 1
ssh -N -L 3307:127.0.0.1:3306 user@server1_ip_atau_domain

# Background mode (tetap jalan walaupun terminal ditutup):
ssh -fN -L 3307:127.0.0.1:3306 user@server1_ip_atau_domain</pre>
        <div class="text-muted small mt-1"><strong>Windows (PuTTY):</strong> Connection → SSH → Tunnels → Source port: 3307, Destination: 127.0.0.1:3306</div>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">2</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Setup slave menggunakan localhost:3307</div>
        <pre class="guide-code">-- Di MySQL laptop (jalankan sebagai root):
STOP SLAVE;
CHANGE MASTER TO
  MASTER_HOST='127.0.0.1',    -- bukan server1_ip, karena tunnel!
  MASTER_PORT=3307,            -- port tunnel lokal
  MASTER_USER='repl_user',
  MASTER_PASSWORD='repl_pass',
  MASTER_LOG_FILE='mysql-bin.000001',  -- dari SHOW MASTER STATUS di Server 1
  MASTER_LOG_POS=4;
START SLAVE;</pre>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">3</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Otomatis tunnel saat laptop nyala (Windows Task Scheduler)</div>
        <pre class="guide-code">-- Buat file: scripts/replication/tunnel_start.bat
@echo off
ssh -fN -L 3307:127.0.0.1:3306 user@server1_ip

-- Task Scheduler: trigger = At logon, action = jalankan tunnel_start.bat</pre>
        <div class="text-muted small mt-1">Atau gunakan autossh (Linux) untuk reconnect otomatis jika koneksi terputus.</div>
      </div>
    </div>

    <div class="guide-step">
      <div class="guide-step-num">4</div>
      <div class="flex-fill">
        <div class="fw-semibold mb-1">Jika laptop offline, replication otomatis retry</div>
        <div class="text-muted small">MySQL slave akan mencoba reconnect ke master setiap <code>MASTER_CONNECT_RETRY</code> detik (default: 60 detik). Tidak perlu action manual — data akan catch-up saat laptop online lagi.</div>
      </div>
    </div>

    <hr>

    <div class="fw-semibold mb-2">Skenario Failover dengan Laptop</div>
    <div class="alert alert-warning border-0 small mb-0">
      <strong>Catatan khusus laptop:</strong>
      <ul class="mb-0 mt-1">
        <li>Jika Server 1 down dan laptop sedang offline → user tidak bisa akses apapun sampai laptop dinyalakan dan tunnel dibuat</li>
        <li>Setelah tunnel aktif, jalankan <code>failover_promote_slave.sh</code> di laptop seperti biasa</li>
        <li>Untuk akses dari perangkat lain ke laptop (saat failover), laptop harus bisa diakses di jaringan lokal (LAN/WiFi)</li>
        <li>Pertimbangkan VPN (Tailscale/WireGuard) jika butuh akses laptop dari luar LAN saat failover</li>
      </ul>
    </div>
  </div>
</div>

<!-- Tips -->
<div class="card guide-card">
  <div class="card-body">
    <div class="fw-bold mb-2"><i class="ri ri-lightbulb-line me-1 text-warning"></i>Tips Operasional</div>
    <ul class="small mb-0">
      <li>Pantau lag replication. Lag > 60 detik harus diinvestigasi.</li>
      <li>Backup (Skema 1) tetap jalan di kedua server — independent dari replication.</li>
      <li>Jangan jalankan <code>recovery_sync.sh</code> dalam keadaan Server 2 masih aktif menerima transaksi — hentikan dulu atau set maintenance mode.</li>
      <li>Setelah failover, beritahu user untuk tidak lagi akses Server 1 sampai recovery selesai.</li>
      <li>Log health check tersimpan di <code>backup/logs/replication_status.json</code> — halaman ini membacanya setiap refresh.</li>
      <li><strong>Laptop sebagai Slave:</strong> data catch-up otomatis saat laptop online kembali. Tidak perlu intervensi manual untuk gap saat laptop offline.</li>
    </ul>
  </div>
</div>
