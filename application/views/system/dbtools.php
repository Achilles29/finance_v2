<?php
$cfg          = is_array($cfg ?? null) ? $cfg : [];
$recentDumps  = is_array($recent_dumps ?? null) ? $recent_dumps : [];
$replStatus   = is_array($repl_status ?? null) ? $repl_status : [];
$failoverActive = !empty($failover_active);
$failoverTime   = (string)($failover_time ?? '');
$isWindows    = !empty($is_windows);
$envExists    = !empty($env_exists);

$cfgGet = static function(array $c, string $k, string $def = ''): string {
    return html_escape((string)($c[$k] ?? $def));
};

$replRole   = strtoupper((string)($cfg['repl.server_role'] ?? 'STANDALONE'));
$replOk     = ($replStatus['status'] ?? '') === 'OK';
$lastDump   = !empty($recentDumps) ? $recentDumps[0] : null;
?>
<style>
  .dbt-tab-nav { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.5rem; }
  .dbt-tab-btn {
    display:inline-flex; align-items:center; gap:.5rem; padding:.6rem 1.1rem; border-radius:10px;
    border:1px solid #dfd5cb; background:#fff; color:#51453d; font-weight:700; cursor:pointer;
    text-decoration:none; font-size:.9rem; transition:all .15s;
  }
  .dbt-tab-btn:hover { background:#fff8f3; border-color:#d9c8bc; color:#3f342d; }
  .dbt-tab-btn.active { background:#9f2141; border-color:#9f2141; color:#fff; box-shadow:0 8px 18px rgba(159,33,65,.18); }
  .dbt-pane { display:none; }
  .dbt-pane.active { display:block; }
  .dbt-card { border:0; border-radius:16px; box-shadow:0 4px 16px rgba(58,38,30,.07); }
  .dbt-section { font-size:.74rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#9a7f72; margin-bottom:.7rem; }
  .dbt-output { background:#1e1e2e; color:#a6e3a1; border-radius:10px; padding:.85rem 1rem; font-family:monospace; font-size:.8rem; max-height:240px; overflow-y:auto; white-space:pre-wrap; display:none; margin-top:.75rem; }
  .dbt-step { display:flex; gap:.85rem; margin-bottom:1.1rem; }
  .dbt-step-num { width:26px; height:26px; min-width:26px; border-radius:50%; background:#9f2141; color:#fff; font-size:.75rem; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .dbt-code { background:#1e1e2e; color:#cdd6f4; border-radius:10px; padding:.7rem .9rem; font-family:monospace; font-size:.8rem; overflow-x:auto; white-space:pre; }
  .status-ok   { display:inline-flex; align-items:center; gap:.3rem; background:#dcfce7; color:#166534; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .status-warn { display:inline-flex; align-items:center; gap:.3rem; background:#fff3cd; color:#856404; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .status-err  { display:inline-flex; align-items:center; gap:.3rem; background:#fee2e2; color:#991b1b; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .failover-banner { background:#fef3c7; border:2px solid #f59e0b; border-radius:14px; padding:.9rem 1.1rem; margin-bottom:1.5rem; }
  .dbt-summary-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; margin-bottom:1.5rem; }
  .dbt-summary-card { border-radius:12px; padding:.75rem .9rem; border:1px solid rgba(224,209,198,.7); background:#fffdfb; }
  @media(max-width:767px) { .dbt-summary-grid { grid-template-columns:1fr 1fr; } }
</style>

<!-- Failover banner -->
<?php if ($failoverActive): ?>
<div class="failover-banner">
  <div class="fw-bold mb-1"><i class="ri ri-alert-line me-1 text-warning"></i>MODE DARURAT AKTIF — sejak <?php echo html_escape($failoverTime); ?></div>
  <div class="small">Server cadangan sedang dipakai sebagai server utama sementara. Setelah server utama online, gunakan tombol <strong>Pulihkan Koneksi</strong> di tab Server Cadangan.</div>
</div>
<?php endif; ?>

<div class="fin-page-header mb-3">
  <div>
    <h4 class="fin-page-title"><i class="ri ri-shield-check-line me-1 text-primary"></i>Perlindungan Database</h4>
    <p class="fin-page-subtitle mb-0">Backup otomatis lokal &amp; server cadangan untuk jaga-jaga mati listrik atau gangguan server.</p>
  </div>
</div>

<!-- Ringkasan status -->
<div class="dbt-summary-grid">
  <div class="dbt-summary-card">
    <div class="dbt-section mb-1">Backup Terakhir</div>
    <?php if ($lastDump): ?>
      <div class="fw-semibold small"><?php echo html_escape($lastDump['date']); ?></div>
      <div class="text-muted" style="font-size:.75rem"><?php echo html_escape($lastDump['name']); ?> · <?php echo html_escape($lastDump['size']); ?></div>
    <?php else: ?>
      <span class="status-warn"><i class="ri ri-alert-line"></i>Belum ada backup</span>
    <?php endif; ?>
  </div>
  <div class="dbt-summary-card">
    <div class="dbt-section mb-1">Server Cadangan</div>
    <?php
      if ($replRole === 'STANDALONE') { echo '<span class="status-warn"><i class="ri ri-server-line"></i>Belum dikonfigurasi</span>'; }
      elseif ($failoverActive)        { echo '<span class="status-warn"><i class="ri ri-alert-line"></i>Mode darurat aktif</span>'; }
      elseif ($replOk)                { echo '<span class="status-ok"><i class="ri ri-checkbox-circle-line"></i>Sinkron (' . (int)($replStatus['lag_seconds'] ?? 0) . 's lag)</span>'; }
      else                            { echo '<span class="status-err"><i class="ri ri-close-circle-line"></i>Masalah koneksi</span>'; }
    ?>
  </div>
  <div class="dbt-summary-card">
    <div class="dbt-section mb-1">File .env</div>
    <?php if ($envExists): ?>
      <span class="status-ok"><i class="ri ri-checkbox-circle-line"></i>Sudah dikonfigurasi</span>
    <?php else: ?>
      <span class="status-warn"><i class="ri ri-alert-line"></i>Belum dibuat — klik Simpan</span>
    <?php endif; ?>
  </div>
</div>

<!-- Alert area -->
<div id="dbt-alert" class="mb-3" style="display:none"></div>

<!-- Tab navigation -->
<div class="dbt-tab-nav">
  <button class="dbt-tab-btn active" data-tab="backup">
    <i class="ri ri-database-2-line"></i>
    <span>Backup Otomatis</span>
  </button>
  <button class="dbt-tab-btn" data-tab="replica">
    <i class="ri ri-server-line"></i>
    <span>Server Cadangan</span>
  </button>
  <button class="dbt-tab-btn" data-tab="status">
    <i class="ri ri-pulse-line"></i>
    <span>Status &amp; Jalankan</span>
  </button>
  <button class="dbt-tab-btn" data-tab="panduan">
    <i class="ri ri-book-open-line"></i>
    <span>Panduan Lengkap</span>
  </button>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB 1: BACKUP OTOMATIS
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane active" id="tab-backup">
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="card dbt-card p-4">
        <div class="dbt-section">Koneksi ke Database Lokal Server Ini</div>
        <div class="row g-3 mb-4">
          <div class="col-md-8"><label class="form-label small mb-1">Host Database</label>
            <input type="text" id="b_db_host" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_host','localhost'); ?>"></div>
          <div class="col-md-4"><label class="form-label small mb-1">Port</label>
            <input type="number" id="b_db_port" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_port','3306'); ?>"></div>
          <div class="col-md-6"><label class="form-label small mb-1">Username</label>
            <input type="text" id="b_db_user" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_user','root'); ?>"></div>
          <div class="col-md-6"><label class="form-label small mb-1">Password</label>
            <input type="password" id="b_db_pass" class="form-control" placeholder="Kosongkan jika tidak berubah" autocomplete="new-password"></div>
          <div class="col-12"><label class="form-label small mb-1">Nama Database</label>
            <input type="text" id="b_db_name" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_name','db_finance'); ?>"></div>
          <div class="col-12">
            <button type="button" id="btn-test-db" class="btn btn-outline-info btn-sm">
              <i class="ri ri-database-line me-1"></i>Tes Koneksi
            </button>
            <span id="db-test-result" class="ms-2 small"></span>
          </div>
        </div>

        <div class="dbt-section">Pengaturan Backup</div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label small mb-1">Simpan berapa hari?</label>
            <div class="input-group input-group-sm">
              <input type="number" id="b_retention" class="form-control" min="1" max="30" value="<?php echo $cfgGet($cfg,'backup.retention_days','3'); ?>">
              <span class="input-group-text">hari</span>
            </div>
            <div class="form-text">File lama otomatis dihapus.</div>
          </div>
          <div class="col-12">
            <label class="form-label small mb-1">Tabel yang tidak perlu dibackup</label>
            <input type="hidden" id="b_exclude" value="<?php echo $cfgGet($cfg,'backup.exclude_tables',''); ?>">
            <div class="d-flex align-items-center gap-2 mb-2">
              <button type="button" id="btn-pick-tables" class="btn btn-outline-secondary btn-sm">
                <i class="ri ri-table-line me-1"></i>Pilih Tabel
              </button>
              <span class="text-muted small" id="b_exclude_count"></span>
            </div>
            <div id="b_exclude_chips" class="d-flex flex-wrap gap-1"></div>
            <div class="form-text">Tabel log besar yang tidak dibutuhkan untuk restore. Kecuali tabel ini = tetap dibackup semua.</div>
          </div>
          <div class="col-12">
            <div class="alert alert-info border-0 small mb-0">Runner backup hanya membuat dump dan log lokal di host ini. Backup off-site terenkripsi harus dikonfigurasi secara terpisah.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <!-- Cara setup otomatis -->
      <div class="card dbt-card p-4 mb-3">
        <div class="dbt-section">Cara Backup Berjalan Otomatis</div>
        <?php if ($isWindows): ?>
        <div class="dbt-step">
          <div class="dbt-step-num">1</div>
          <div><div class="fw-semibold mb-1">Klik Simpan di bawah</div>
            <div class="text-muted small">File konfigurasi dibuat otomatis.</div></div>
        </div>
        <div class="dbt-step">
          <div class="dbt-step-num">2</div>
          <div><div class="fw-semibold mb-1">Jadwalkan via Task Scheduler Windows</div>
            <div class="text-muted small">Buka Task Scheduler → buat task baru → Trigger: setiap 30 menit → Action:</div>
            <div class="dbt-code mt-1">scripts\backup\backup_full.bat</div>
          </div>
        </div>
        <?php else: ?>
        <div class="dbt-step">
          <div class="dbt-step-num">1</div>
          <div><div class="fw-semibold mb-1">Klik Simpan di bawah</div>
            <div class="text-muted small">File konfigurasi dibuat otomatis di server.</div></div>
        </div>
        <div class="dbt-step">
          <div class="dbt-step-num">2</div>
          <div><div class="fw-semibold mb-1">Jalankan sekali di terminal server:</div>
            <div class="dbt-code mt-1">chmod +x <?php echo rtrim(FCPATH,'/'); ?>/scripts/backup/backup_full.sh</div>
          </div>
        </div>
        <div class="dbt-step">
          <div class="dbt-step-num">3</div>
          <div><div class="fw-semibold mb-1">Tambahkan ke cron (jalankan: <code>crontab -e</code>):</div>
            <div class="dbt-code mt-1">*/30 * * * * <?php echo rtrim(FCPATH,'/'); ?>/scripts/backup/backup_full.sh</div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Backup terakhir -->
      <div class="card dbt-card p-4">
        <div class="dbt-section">Backup Tersimpan</div>
        <?php if (empty($recentDumps)): ?>
          <div class="text-muted small">Belum ada file backup tersimpan di server ini.</div>
        <?php else: ?>
          <?php foreach ($recentDumps as $d): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.35rem 0;border-bottom:1px solid rgba(224,209,198,.4)">
              <div>
                <div class="small fw-semibold"><?php echo html_escape($d['date']); ?></div>
                <div class="text-muted" style="font-size:.72rem"><?php echo html_escape($d['name']); ?></div>
              </div>
              <span class="badge bg-secondary"><?php echo html_escape($d['size']); ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="button" id="btn-save-backup" class="btn btn-primary">
      <i class="ri ri-save-line me-1"></i>Simpan Pengaturan
    </button>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB 2: SERVER CADANGAN
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane" id="tab-replica">
  <div class="alert alert-info border-0 small mb-4">
    <strong>Cara kerja:</strong> Server cadangan <em>menarik</em> data dari server utama secara otomatis. Laptop tanpa IP publik pun bisa jadi server cadangan — selama bisa konek ke internet.
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card dbt-card p-4">
        <div class="dbt-section">Peran Server Ini</div>
        <div class="mb-3">
          <?php foreach (['STANDALONE' => ['label'=>'Berdiri Sendiri','desc'=>'Tidak ada server cadangan'], 'MASTER' => ['label'=>'Server Utama (1)','desc'=>'Data mengalir ke server cadangan'], 'SLAVE' => ['label'=>'Server Cadangan (2)','desc'=>'Menerima data dari server utama']] as $v => $info): ?>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="repl_role" id="role_<?php echo $v; ?>" value="<?php echo $v; ?>"
                <?php echo $replRole === $v ? 'checked' : ''; ?>>
              <label class="form-check-label" for="role_<?php echo $v; ?>">
                <div class="fw-semibold"><?php echo $info['label']; ?></div>
                <div class="text-muted small"><?php echo $info['desc']; ?></div>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <div id="slave-fields" class="<?php echo $replRole !== 'SLAVE' ? 'd-none' : ''; ?>">
          <div class="dbt-section mt-3">Koneksi ke Server Utama</div>
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label small mb-1">Alamat Server Utama</label>
              <input type="text" id="r_master_host" class="form-control" placeholder="IP atau domain server utama"
                     value="<?php echo $cfgGet($cfg,'repl.master_host',''); ?>"></div>
            <div class="col-md-4"><label class="form-label small mb-1">Port MySQL</label>
              <input type="number" id="r_master_port" class="form-control" value="<?php echo $cfgGet($cfg,'repl.master_port','3306'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">User Sinkronisasi</label>
              <input type="text" id="r_repl_user" class="form-control" value="<?php echo $cfgGet($cfg,'repl.repl_user','repl_user'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Password Sinkronisasi</label>
              <input type="password" id="r_repl_pass" class="form-control" placeholder="Kosongkan jika tidak berubah" autocomplete="new-password"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card dbt-card p-4">
        <div class="dbt-section">Jika Server Cadangan = Laptop (Tanpa IP Publik)</div>
        <div class="text-muted small mb-3">
          Laptop terhubung ke server utama via <strong>terowongan SSH</strong>. Sinkronisasi tetap berjalan selama laptop ada koneksi internet.
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="t_enabled" role="switch"
            <?php echo ($cfg['tunnel.enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
          <label class="form-check-label fw-semibold" for="t_enabled">Aktifkan koneksi via SSH Tunnel</label>
        </div>
        <div id="tunnel-fields" class="<?php echo ($cfg['tunnel.enabled'] ?? '0') !== '1' ? 'd-none' : ''; ?>">
          <div class="row g-2">
            <div class="col-md-8"><label class="form-label small mb-1">Alamat SSH Server Utama</label>
              <input type="text" id="t_ssh_host" class="form-control form-control-sm" placeholder="IP/domain (sama dengan server utama)"
                     value="<?php echo $cfgGet($cfg,'tunnel.ssh_host',''); ?>"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Port SSH</label>
              <input type="number" id="t_ssh_port" class="form-control form-control-sm" value="<?php echo $cfgGet($cfg,'tunnel.ssh_port','22'); ?>">
              <div class="form-text" style="font-size:.68rem">Cek di Bitvise</div></div>
            <div class="col-md-2"><label class="form-label small mb-1">User SSH</label>
              <input type="text" id="t_ssh_user" class="form-control form-control-sm" value="<?php echo $cfgGet($cfg,'tunnel.ssh_user','root'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Port lokal terowongan</label>
              <input type="number" id="t_local_port" class="form-control form-control-sm" value="<?php echo $cfgGet($cfg,'tunnel.local_port','3307'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Port MySQL server utama</label>
              <input type="number" id="t_remote_port" class="form-control form-control-sm" value="<?php echo $cfgGet($cfg,'tunnel.remote_port','3306'); ?>"></div>
          </div>
          <div class="alert alert-secondary border-0 small mt-2 py-2">
            Nyalakan terowongan: <code>scripts/replication/tunnel_start.<?php echo $isWindows ? 'bat' : 'sh'; ?></code>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Setup Wizard ─────────────────────────────────────── -->
  <div id="repl-wizard" class="card dbt-card p-4 mb-3 <?php echo $replRole === 'STANDALONE' ? 'd-none' : ''; ?>">
    <div class="dbt-section">Setup Otomatis</div>

    <!-- MASTER wizard -->
    <div id="wizard-master" class="<?php echo $replRole !== 'MASTER' ? 'd-none' : ''; ?>">
      <p class="small text-muted mb-3">
        Klik tombol di bawah untuk menerapkan konfigurasi MySQL server ini sebagai <strong>Server Utama</strong>.
        Setting diterapkan via <code>SET GLOBAL</code> (langsung efektif) dan dicoba ditulis ke <code>conf.d</code> agar permanen.
      </p>
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <button type="button" id="btn-apply-master-cfg" class="btn btn-outline-primary btn-sm">
          <i class="ri ri-settings-3-line me-1"></i>Terapkan Konfigurasi MySQL (Server 1)
        </button>
      </div>
      <div id="out-apply-master" class="dbt-output"></div>
      <hr class="my-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label small mb-1">Username Replikasi</label>
          <input type="text" id="wm_repl_user" class="form-control" value="<?php echo $cfgGet($cfg,'repl.repl_user','repl_user'); ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label small mb-1">Password Replikasi</label>
          <input type="password" id="wm_repl_pass" class="form-control" placeholder="Wajib diisi" autocomplete="new-password">
        </div>
        <div class="col-md-2">
          <button type="button" id="btn-setup-master" class="btn btn-primary w-100">
            <i class="ri ri-user-add-line me-1"></i>Buat User
          </button>
        </div>
      </div>
      <div id="out-setup-master" class="dbt-output mt-2"></div>
      <div class="text-muted small mt-2">Tombol Buat User menjalankan <code>CREATE USER</code> + <code>GRANT REPLICATION SLAVE</code>.</div>
    </div>

    <!-- SLAVE wizard -->
    <div id="wizard-slave" class="<?php echo $replRole !== 'SLAVE' ? 'd-none' : ''; ?>">
      <p class="small text-muted mb-3">
        Ikuti urutan tombol di bawah. Import data awal <strong>bisa dilakukan setelah terhubung</strong> — tombol Sinkronisasi akan menyalin data dari server sambil mengecualikan tabel yang sudah dikonfigurasi.
      </p>
      <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
        <span class="badge bg-secondary" style="font-size:.7rem">1</span>
        <button type="button" id="btn-apply-slave-cfg" class="btn btn-outline-primary btn-sm">
          <i class="ri ri-settings-3-line me-1"></i>Terapkan Konfigurasi MySQL
        </button>
        <span class="badge bg-secondary" style="font-size:.7rem">2</span>
        <button type="button" id="btn-init-slave" class="btn btn-success btn-sm">
          <i class="ri ri-link me-1"></i>Hubungkan ke Server Utama
        </button>
        <span class="badge bg-secondary" style="font-size:.7rem">3</span>
        <button type="button" id="btn-initial-sync" class="btn btn-outline-info btn-sm">
          <i class="ri ri-refresh-line me-1"></i>Sinkronisasi Data Awal
        </button>
      </div>
      <div id="out-apply-slave" class="dbt-output mb-2"></div>
      <div id="out-init-slave" class="dbt-output mb-2"></div>
      <div id="out-initial-sync" class="dbt-output"></div>
      <div class="text-muted small mt-2">
        Tombol Sinkronisasi menyalin semua tabel dari server utama ke server cadangan ini, mengecualikan <code>sys_app_config</code> dan tabel yang dikonfigurasi di tab Backup.
      </div>
    </div>
  </div>

  <!-- Panduan prosedur -->
  <div class="card dbt-card p-4 mb-3">
    <div class="dbt-section">Alur Perlindungan</div>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="fw-semibold small mb-1"><i class="ri ri-arrow-right-line me-1 text-success"></i>Kondisi Normal</div>
        <div class="text-muted small">Server utama aktif. Server cadangan otomatis menyalin semua perubahan data secara real-time. User memakai server utama.</div>
      </div>
      <div class="col-md-4">
        <div class="fw-semibold small mb-1"><i class="ri ri-alert-line me-1 text-warning"></i>Server Utama Mati</div>
        <div class="text-muted small">Gunakan tombol <strong>Aktifkan Darurat</strong> di tab Status. Server cadangan langsung bisa menerima transaksi. User dialihkan ke alamat server cadangan.</div>
      </div>
      <div class="col-md-4">
        <div class="fw-semibold small mb-1"><i class="ri ri-refresh-line me-1 text-info"></i>Server Utama Kembali</div>
        <div class="text-muted small">Jalankan <strong>Cek Perbedaan Data</strong>, lalu <strong>Pulihkan Koneksi</strong>. Data dari server cadangan disalin ke server utama, sinkronisasi normal kembali.</div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="button" id="btn-save-replication" class="btn btn-primary">
      <i class="ri ri-save-line me-1"></i>Simpan Pengaturan
    </button>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB 3: STATUS & JALANKAN
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane" id="tab-status">
  <div class="row g-3 mb-4">
    <!-- Backup actions -->
    <div class="col-md-6">
      <div class="card dbt-card p-4 h-100">
        <div class="dbt-section">Backup Otomatis</div>
        <div class="mb-3">
          <?php if ($lastDump): ?>
            <div class="fw-semibold small">Backup terakhir:</div>
            <div class="small"><?php echo html_escape($lastDump['date']); ?> · <?php echo html_escape($lastDump['size']); ?></div>
          <?php else: ?>
            <span class="status-warn"><i class="ri ri-alert-line"></i>Belum ada backup tersimpan</span>
          <?php endif; ?>
        </div>
        <button type="button" id="btn-run-backup" class="btn btn-outline-primary btn-sm mb-1">
          <i class="ri ri-play-line me-1"></i>Jalankan Backup Sekarang
        </button>
        <div class="text-muted" style="font-size:.75rem">Proses ini mungkin butuh beberapa menit tergantung ukuran database.</div>
        <div id="out-backup" class="dbt-output"></div>
      </div>
    </div>

    <!-- Replication status -->
    <div class="col-md-6">
      <div class="card dbt-card p-4 h-100">
        <div class="dbt-section">Server Cadangan</div>
        <div class="mb-3" id="repl-status-display">
          <?php
            $rs  = $replStatus['status'] ?? '-';
            $cls = $rs === 'OK' ? 'status-ok' : ($rs === 'ERROR' ? 'status-err' : 'status-warn');
            $lag = isset($replStatus['lag_seconds']) ? ' · keterlambatan ' . (int)$replStatus['lag_seconds'] . ' detik' : '';
          ?>
          <?php if ($replRole === 'STANDALONE'): ?>
            <span class="status-warn"><i class="ri ri-server-line"></i>Belum dikonfigurasi</span>
          <?php elseif ($failoverActive): ?>
            <span class="status-warn"><i class="ri ri-alert-line"></i>Mode darurat aktif</span>
          <?php elseif ($rs !== '-'): ?>
            <span class="<?php echo $cls; ?>"><?php echo $rs === 'OK' ? '<i class="ri ri-checkbox-circle-line"></i>Sinkron' : '<i class="ri ri-close-circle-line"></i>Ada masalah'; ?><?php echo html_escape($lag); ?></span>
          <?php else: ?>
            <span class="status-warn"><i class="ri ri-question-line"></i>Belum dicek</span>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap mb-1">
          <button type="button" id="btn-check-repl" class="btn btn-outline-info btn-sm">
            <i class="ri ri-refresh-line me-1"></i>Cek Kondisi Sekarang
          </button>
          <button type="button" id="btn-compare-data" class="btn btn-outline-secondary btn-sm">
            <i class="ri ri-git-diff-line me-1"></i>Bandingkan Data
          </button>
        </div>
        <div class="text-muted" style="font-size:.75rem">Cek apakah sinkronisasi berjalan normal.</div>
        <div id="out-repl" class="dbt-output mt-2"></div>
        <div id="out-compare" class="mt-2" style="display:none"></div>
      </div>
    </div>
  </div>

  <!-- Aksi darurat -->
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card dbt-card p-4 border border-warning">
        <div class="fw-bold mb-2">
          <i class="ri ri-alert-line me-1 text-warning"></i>Aktifkan Mode Darurat
        </div>
        <div class="text-muted small mb-3">
          Gunakan ini <strong>hanya jika server utama benar-benar tidak bisa diakses</strong>.<br>
          Server ini akan langsung bisa menerima transaksi. Data tetap aman karena ID transaksi tidak akan tabrakan.
        </div>
        <button type="button" id="btn-failover" class="btn btn-warning w-100" <?php echo $failoverActive ? 'disabled' : ''; ?>>
          <i class="ri ri-alert-line me-1"></i><?php echo $failoverActive ? 'Mode Darurat Sudah Aktif' : 'Aktifkan Mode Darurat'; ?>
        </button>
        <div id="out-failover" class="dbt-output"></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card dbt-card p-4 border border-success">
        <div class="fw-bold mb-2">
          <i class="ri ri-link me-1 text-success"></i>Pulihkan Koneksi ke Server Utama
        </div>
        <div class="text-muted small mb-3">
          Jalankan ini setelah server utama kembali online dan data sudah disinkronkan.<br>
          Server ini akan kembali ke mode cadangan yang menerima data dari server utama.
        </div>
        <button type="button" id="btn-restart-repl" class="btn btn-success w-100" <?php echo !$failoverActive ? 'disabled' : ''; ?>>
          <i class="ri ri-link me-1"></i>Pulihkan Koneksi
        </button>
        <div id="out-restart" class="dbt-output"></div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB 4: PANDUAN LENGKAP
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane" id="tab-panduan">
<style>
  .guide-chapter { border:0; border-radius:16px; box-shadow:0 4px 16px rgba(58,38,30,.07); margin-bottom:1.2rem; overflow:hidden; }
  .guide-chapter-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:.9rem 1.2rem; cursor:pointer; background:#fffdfb;
    border-bottom:1px solid rgba(224,209,198,.5);
    user-select:none;
  }
  .guide-chapter-header:hover { background:#fff8f3; }
  .guide-chapter-header .chap-title { font-weight:800; font-size:.95rem; display:flex; align-items:center; gap:.6rem; }
  .guide-chapter-header .chap-num { width:28px; height:28px; min-width:28px; border-radius:50%; background:#9f2141; color:#fff; font-size:.75rem; font-weight:800; display:flex; align-items:center; justify-content:center; }
  .guide-chapter-body { padding:1.2rem; background:#fff; display:none; }
  .guide-chapter-body.open { display:block; }
  .guide-toggle-icon { transition:transform .2s; }
  .guide-toggle-icon.open { transform:rotate(180deg); }
  .guide-step-list { list-style:none; padding:0; margin:0; }
  .guide-step-list li { display:flex; gap:.8rem; margin-bottom:.9rem; }
  .guide-step-list li .snum { width:22px; height:22px; min-width:22px; border-radius:50%; background:#f0ede8; color:#9f2141; font-size:.72rem; font-weight:800; display:flex; align-items:center; justify-content:center; margin-top:.1rem; }
  .guide-step-list li .sbody { flex:1; }
  .guide-step-list li .sbody .stitle { font-weight:700; margin-bottom:.2rem; }
  .guide-step-list li .sbody .sdesc { font-size:.86rem; color:#5a4a42; }
  .guide-cmd { background:#1e1e2e; color:#a6e3a1; border-radius:8px; padding:.5rem .8rem; font-family:monospace; font-size:.8rem; margin:.4rem 0; overflow-x:auto; white-space:pre; }
  .guide-note { background:#fff8f2; border-left:3px solid #f59e0b; border-radius:0 8px 8px 0; padding:.6rem .9rem; font-size:.85rem; color:#6b4a2a; margin:.6rem 0; }
  .guide-danger { background:#fff5f5; border-left:3px solid #c0392b; border-radius:0 8px 8px 0; padding:.6rem .9rem; font-size:.85rem; color:#7a1a1a; margin:.6rem 0; }
  .guide-ok { background:#f0fdf4; border-left:3px solid #22c55e; border-radius:0 8px 8px 0; padding:.6rem .9rem; font-size:.85rem; color:#14532d; margin:.6rem 0; }
  .guide-scenario { border:1px solid rgba(224,209,198,.6); border-radius:10px; padding:.8rem 1rem; margin-bottom:.8rem; background:#fffdfb; }
  .guide-scenario-title { font-weight:800; font-size:.88rem; margin-bottom:.4rem; }
  .guide-tag { display:inline-flex; align-items:center; gap:.3rem; padding:.15rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700; }
  .guide-tag.server  { background:#e8f3ef; color:#1a6450; }
  .guide-tag.laptop  { background:#e0f2fe; color:#075985; }
  .guide-tag.both    { background:#fef3c7; color:#92400e; }
  .guide-tag.warning { background:#fee2e2; color:#991b1b; }
  .guide-checklist { list-style:none; padding:0; margin:0; }
  .guide-checklist li { display:flex; align-items:flex-start; gap:.6rem; margin-bottom:.5rem; font-size:.88rem; }
  .guide-checklist li::before { content:'□'; font-size:1rem; color:#9f2141; flex-shrink:0; margin-top:.05rem; }
</style>

<p class="text-muted small mb-4">Panduan lengkap dari awal sampai situasi darurat. Klik judul bab untuk membuka.</p>

<?php
$isWin = !empty($is_windows);
$root  = rtrim(FCPATH, '/\\');
?>

<!-- BAB 1 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">1</span>Persiapan Sebelum Mulai</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Pastikan semua ini sudah siap sebelum mengkonfigurasi backup atau server cadangan.</p>
    <ul class="guide-checklist">
      <li>Database berjalan normal dan bisa diakses dari aplikasi ini</li>
      <?php if (!$isWin): ?>
      <li>Script backup sudah bisa dieksekusi:
        <div class="dbt-code mt-1">chmod +x <?php echo $root; ?>/scripts/backup/backup_full.sh
chmod +x <?php echo $root; ?>/scripts/replication/*.sh</div>
      </li>
      <?php endif; ?>
      <li>mysqldump tersedia di server (cek: <code>mysqldump --version</code>)</li>
      <li>Jika membutuhkan salinan di luar host, siapkan layanan backup off-site terenkripsi secara terpisah dari runner ini</li>
    </ul>
  </div>
</div>

<!-- BAB 2 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">2</span>Setup Backup Otomatis Lokal</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Backup berjalan otomatis setiap 30 menit dan menyimpan file di host ini sesuai masa retensi. Backup off-site terenkripsi harus dikonfigurasi secara terpisah.</p>
    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Buka tab "Backup Otomatis" → isi semua kolom</div>
          <div class="sdesc">Host biasanya <code>localhost</code>, nama database <code>db_finance</code>. Klik <strong>Tes Koneksi</strong> dulu untuk memastikan berhasil.</div>
        </div>
      </li>
      <li>
          <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Klik "Simpan Pengaturan"</div>
          <div class="sdesc">File <code>.env</code> dibuat otomatis di <code>scripts/backup/.env</code>. Tidak perlu edit manual.</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Uji coba manual di terminal server</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Di Windows, jalankan langsung:</div>
          <div class="dbt-code"><?php echo $root; ?>\scripts\backup\backup_full.bat</div>
          <?php else: ?>
          <div class="sdesc">Di server Linux:</div>
          <div class="dbt-code"><?php echo $root; ?>/scripts/backup/backup_full.sh</div>
          <?php endif; ?>
          <div class="sdesc mt-1">Harusnya muncul file <code>.sql.gz</code> di <code>backup/dumps/</code> pada host ini. Runner tidak mengirim dump ke layanan eksternal.</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Jadwalkan agar berjalan otomatis</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Buka <strong>Task Scheduler</strong> → Create Task → Trigger: setiap 30 menit → Action: <code><?php echo $root; ?>\scripts\backup\backup_full.bat</code></div>
          <?php else: ?>
          <div class="sdesc">Jalankan <code>crontab -e</code> dan tambahkan baris ini:</div>
          <div class="dbt-code">*/30 * * * * <?php echo $root; ?>/scripts/backup/backup_full.sh</div>
          <?php endif; ?>
          <div class="guide-ok mt-1">✓ Setelah ini backup berjalan sendiri. Lihat hasilnya di tab "Status & Jalankan". Konfigurasikan salinan off-site terenkripsi secara terpisah bila diperlukan.</div>
        </div>
      </li>
    </ol>
  </div>
</div>

<!-- BAB 3 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">3</span>Setup Server Cadangan — Server ke Server</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Cocok jika kamu punya 2 server di hosting yang berbeda. Keduanya punya IP publik.</p>
    <div class="guide-ok mb-3">Sebagian besar langkah sekarang bisa dilakukan via UI — tidak perlu masuk terminal.</div>

    <div class="fw-bold small mb-2">Di Server Utama (Server 1):</div>
    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Tab "Server Cadangan" → pilih "Server Utama (1)" → klik <strong>Terapkan Konfigurasi MySQL (Server 1)</strong></div>
          <div class="sdesc">Tombol ini menjalankan <code>SET GLOBAL server_id=1</code>, <code>binlog_format=ROW</code>, <code>auto_increment_offset=1</code>, dll, dan mencoba menulis konfigurasi permanen ke <code>conf.d</code> otomatis.</div>
          <div class="guide-note">Jika output menampilkan <em>"MySQL perlu di-restart agar log_bin aktif"</em>, jalankan sekali di terminal: <code>sudo systemctl restart mysql</code> — ini hanya diperlukan jika binary logging belum pernah diaktifkan sebelumnya.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Isi username &amp; password replikasi → klik <strong>Buat User</strong></div>
          <div class="sdesc">Tombol ini menjalankan <code>CREATE USER</code> + <code>GRANT REPLICATION SLAVE</code> otomatis. Catat password yang kamu isi — dipakai di Server 2.</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Klik <strong>Simpan Pengaturan</strong></div>
          <div class="sdesc">Menyimpan peran server sebagai Server Utama.</div>
        </div>
      </li>
    </ol>

    <div class="fw-bold small mt-3 mb-2">Di Server Cadangan (Server 2):</div>
    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Tab "Server Cadangan" → pilih "Server Cadangan (2)" → klik <strong>Terapkan Konfigurasi MySQL (Server 2)</strong></div>
          <div class="sdesc">Menerapkan <code>server_id=2</code>, <code>read_only=ON</code>, <code>auto_increment_offset=2</code>, dll. Sama seperti Server 1 — restart MySQL jika diminta.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Import snapshot awal dari Server 1 <span class="guide-tag warning ms-1">Sekali saja</span></div>
          <div class="sdesc">Ini satu-satunya langkah yang perlu terminal. Jalankan di terminal Server 2:</div>
          <div class="dbt-code">mysqldump -h IP_SERVER1 -u root -p --single-transaction db_finance | mysql -u root -p db_finance</div>
          <div class="sdesc mt-1">Atau dengan dua langkah (jika koneksi lambat):</div>
          <div class="dbt-code"># Di Server 1:
mysqldump -u root -p --single-transaction --master-data=2 db_finance > /tmp/snap.sql
scp /tmp/snap.sql user@IP_SERVER2:/tmp/

# Di Server 2:
mysql -u root -p db_finance &lt; /tmp/snap.sql</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Isi koneksi ke Server 1 → klik <strong>Hubungkan ke Server Utama</strong></div>
          <div class="sdesc">Isi alamat Server 1, port, user, dan password replikasi dari langkah Server 1 tadi. Tombol ini menjalankan <code>CHANGE MASTER TO</code> + <code>START SLAVE</code> otomatis.</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Klik <strong>Simpan Pengaturan</strong>, lalu tab "Status &amp; Jalankan" → <strong>Cek Kondisi Sekarang</strong></div>
          <div class="sdesc">Jika muncul <span style="background:#dcfce7;color:#166534;padding:.1rem .4rem;border-radius:4px;font-size:.75rem;font-weight:700">Sinkron</span> — selesai!</div>
          <div class="guide-ok mt-1">✓ Data dari Server 1 otomatis tersalin ke Server 2 secara real-time.</div>
        </div>
      </li>
    </ol>
  </div>
</div>

<!-- BAB 4 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">4</span>Setup Server Cadangan — Laptop Lokal (Tanpa IP Publik)</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Laptop bisa jadi server cadangan meski tidak punya IP publik. Laptop <em>menarik</em> data dari server utama via terowongan SSH.</p>

    <div class="guide-note mb-3"><strong>Prinsip kerja:</strong> Laptop buka koneksi ke server, bukan sebaliknya. Selama laptop ada internet dan server bisa diakses, sinkronisasi berjalan.</div>

    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Setup Server Utama sama seperti Bab 3 langkah 1 &amp; 2</div>
          <div class="sdesc">Di server utama: klik <strong>Terapkan Konfigurasi MySQL (Server 1)</strong> dan <strong>Buat User</strong> via tab Server Cadangan.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Install MySQL di laptop (jika belum ada)</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Download dari mysql.com atau pakai XAMPP yang sudah ada.</div>
          <?php else: ?>
          <div class="dbt-code">sudo apt install mysql-server    # Ubuntu/Debian
sudo brew install mysql          # Mac</div>
          <?php endif; ?>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Import snapshot database ke laptop <span class="guide-tag warning ms-1">Sekali saja</span></div>
          <div class="sdesc">Jalankan di terminal laptop (setelah terowongan SSH aktif di langkah 4):</div>
          <div class="dbt-code">mysqldump -h 127.0.0.1 -P 3307 -u root -p --single-transaction db_finance | mysql -u root -p db_finance</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Buat terowongan SSH dari laptop ke server</div>
          <div class="sdesc">Terowongan ini membuat MySQL di laptop "seolah" terhubung langsung ke MySQL server:</div>
          <?php if ($isWin): ?>
          <div class="dbt-code">ssh -N -L 3307:127.0.0.1:3306 user@IP_SERVER_UTAMA</div>
          <div class="sdesc">Di Windows bisa juga pakai PuTTY: Connection → SSH → Tunnels → Source: 3307, Destination: 127.0.0.1:3306</div>
          <?php else: ?>
          <div class="dbt-code"># Jalankan di laptop, biarkan terminal ini terbuka
ssh -N -L 3307:127.0.0.1:3306 user@IP_SERVER_UTAMA

# Atau background (tetap jalan meski terminal ditutup):
autossh -M 0 -fN -L 3307:127.0.0.1:3306 user@IP_SERVER_UTAMA</div>
          <?php endif; ?>
        </div>
      </li>
      <li>
        <div class="snum">5</div>
        <div class="sbody">
          <div class="stitle">Tab "Server Cadangan" → centang "SSH Tunnel" → isi koneksi → klik <strong>Terapkan Konfigurasi MySQL (Server 2)</strong> → klik <strong>Hubungkan ke Server Utama</strong> → Simpan</div>
          <div class="sdesc">Isi alamat SSH host, user SSH, port lokal <code>3307</code>. Tombol Hubungkan akan menjalankan <code>CHANGE MASTER TO</code> + <code>START SLAVE</code> via terowongan otomatis.</div>
        </div>
      </li>
      <li>
        <div class="snum">6</div>
        <div class="sbody">
          <div class="stitle">Nyalakan terowongan otomatis saat laptop menyala</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Buka Task Scheduler → buat task → Trigger: At logon → Action: <code>scripts\replication\tunnel_start.bat</code></div>
          <?php else: ?>
          <div class="sdesc">Tambahkan ke crontab: <code>@reboot autossh -M 0 -fN -L 3307:127.0.0.1:3306 user@IP_SERVER</code></div>
          <?php endif; ?>
          <div class="guide-note mt-1">Saat laptop offline, sinkronisasi berhenti sementara. Saat online kembali, otomatis lanjut dari titik terakhir — tidak ada data yang hilang.</div>
        </div>
      </li>
    </ol>
  </div>
</div>

<!-- BAB 5 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">5</span>Penggunaan Sehari-hari</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Setelah semuanya disetup, kondisi normal tidak memerlukan tindakan manual apapun.</p>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="guide-scenario">
          <div class="guide-scenario-title">✓ Kondisi Normal</div>
          <div class="sdesc small">
            Tidak perlu melakukan apapun. Backup berjalan otomatis setiap 30 menit.
            Server cadangan menyinkronkan data secara real-time. Semua transparan di balik layar.
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="guide-scenario">
          <div class="guide-scenario-title">👀 Cek Rutin (Mingguan)</div>
          <div class="sdesc small">
            Buka tab "Status & Jalankan" → klik "Cek Kondisi". Pastikan muncul <strong>Sinkron</strong>.
            Lihat backup terakhir tidak lebih dari 1 jam yang lalu.
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="guide-scenario">
          <div class="guide-scenario-title">📦 Restore dari Backup</div>
          <div class="sdesc small">
            Jika perlu restore: ambil file <code>.sql.gz</code> dari penyimpanan off-site terpisah setelah didekripsi, atau dari folder <code>backup/dumps/</code>.
            <div class="mt-2"><strong>Untuk Windows / localhost:</strong></div>
            <div class="mt-1">Opsi 1, ekstrak dulu dengan 7-Zip atau WinRAR sampai menjadi file <code>.sql</code>, lalu import:</div>
            <div class="dbt-code mt-1">C:\xampp\mysql\bin\mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS db_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
C:\xampp\mysql\bin\mysql -u root -p db_finance < C:\path\to\backup_db_finance_xxxxxx.sql</div>
            <div class="mt-1">Opsi 2, import langsung dari file <code>.gz</code> tanpa ekstrak:</div>
            <div class="dbt-code mt-1">"C:\Program Files\7-Zip\7z.exe" x -so C:\path\to\backup_db_finance_xxxxxx.sql.gz | C:\xampp\mysql\bin\mysql -u root -p db_finance</div>
            <div class="guide-note mt-1">Jika tidak memakai XAMPP, ganti path <code>mysql.exe</code> sesuai instalasi MySQL lokal Anda. Alternatif GUI: ekstrak dulu lalu import lewat phpMyAdmin, HeidiSQL, atau DBeaver.</div>

            <div class="mt-3"><strong>Untuk Linux / server:</strong></div>
            <div class="mt-1">Ekstrak dulu lalu import:</div>
            <div class="dbt-code mt-1">gunzip backup.sql.gz
mysql -u root -p db_finance < backup.sql</div>
            <div class="mt-1">Atau import langsung tanpa ekstrak:</div>
            <div class="dbt-code mt-1">gunzip -c backup.sql.gz | mysql -u root -p db_finance</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- BAB 6 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">6</span>Prosedur Darurat — Server Utama Mati</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <div class="guide-danger mb-3"><strong>Kapan pakai ini:</strong> Server utama benar-benar tidak bisa diakses (mati listrik, gangguan hosting, dll.) dan operasional harus tetap jalan.</div>

    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Pastikan server utama memang benar-benar tidak bisa diakses</div>
          <div class="sdesc">Coba buka aplikasi dari browser, ping server utama, hubungi provider hosting. Jangan panik dulu — tunggu 5-10 menit, kadang server restart sebentar.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Di server cadangan: buka halaman ini → tab "Status & Jalankan" → klik "Aktifkan Mode Darurat"</div>
          <div class="sdesc">Centang konfirmasi, klik lanjutkan. Proses hanya beberapa detik.</div>
          <div class="guide-ok mt-1">Server cadangan sekarang bisa menerima semua transaksi. Data tidak akan tabrakan karena server cadangan pakai ID angka genap (2, 4, 6...) sedangkan server utama pakai angka ganjil (1, 3, 5...).</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Arahkan user ke alamat server cadangan</div>
          <div class="sdesc">Beritahu tim untuk buka aplikasi dari alamat server cadangan (IP atau domain server cadangan). Operasional berjalan normal.</div>
        </div>
      </li>
    </ol>

    <div class="guide-note">Selama mode darurat aktif, backup otomatis tetap berjalan di server cadangan dan tersimpan lokal di sana. Backup off-site terenkripsi tetap merupakan konfigurasi terpisah.</div>
  </div>
</div>

<!-- BAB 7 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">7</span>Pemulihan — Setelah Server Utama Kembali Online</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Jangan tergesa-gesa. Ikuti langkah ini secara berurutan untuk memastikan tidak ada data yang hilang.</p>

    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Pastikan server utama sudah stabil sebelum memulai</div>
          <div class="sdesc">Coba akses aplikasi via server utama. Pastikan database bisa diquery. Jangan lanjut jika server utama masih tidak stabil.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Hentikan transaksi sementara (opsional, tapi disarankan)</div>
          <div class="sdesc">Kalau bisa, beritahu tim untuk berhenti transaksi sebentar (5-10 menit) saat proses pemulihan. Ini menghindari data baru masuk saat sedang sinkronisasi.</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Jalankan pengecekan perbedaan data (lewat terminal server cadangan)</div>
          <div class="dbt-code">scripts/replication/recovery_sync.sh</div>
          <div class="sdesc">Script ini membandingkan data di server cadangan vs server utama. Hasilnya berupa laporan: ada berapa data baru yang perlu dipindahkan.</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Periksa laporan perbedaan</div>
          <div class="sdesc">File laporan ada di <code>backup/sync_TANGGAL/diff_report.txt</code>. Jika 0 perbedaan → langsung ke langkah 6. Jika ada perbedaan → lanjut ke langkah 5.</div>
        </div>
      </li>
      <li>
        <div class="snum">5</div>
        <div class="sbody">
          <div class="stitle">Jalankan file sinkronisasi ke server utama (jika ada perbedaan)</div>
          <div class="dbt-code">mysql -h IP_SERVER_UTAMA -u root db_finance < backup/sync_TANGGAL/sync_to_server1.sql</div>
          <div class="guide-note">Buka file SQL dulu sebelum dijalankan, pastikan isinya masuk akal. File ini hanya berisi INSERT data baru dari server cadangan.</div>
        </div>
      </li>
      <li>
        <div class="snum">6</div>
        <div class="sbody">
          <div class="stitle">Di halaman ini → tab "Status & Jalankan" → klik "Pulihkan Koneksi"</div>
          <div class="sdesc">Server cadangan kembali ke mode sinkronisasi dari server utama. Operasional normal kembali.</div>
          <div class="guide-ok mt-1">✓ Arahkan kembali user ke server utama. Mode darurat selesai.</div>
        </div>
      </li>
    </ol>

    <div class="guide-danger mt-2">
      <strong>Jangan</strong> hapus file di <code>backup/sync_TANGGAL/</code> sampai kamu yakin semua data sudah aman dan aplikasi berjalan normal minimal 1 hari.
    </div>
  </div>
</div>

<!-- BAB 8 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">8</span>Pertanyaan Umum (FAQ)</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Bagaimana menyiapkan backup off-site?</div>
      <div class="sdesc small">Runner ini hanya menyimpan dump dan log di host lokal. Pilih layanan penyimpanan terpisah, enkripsi dump sebelum dikirim, lalu jadwalkan mekanisme off-site tersebut di luar runner ini.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ "Tes Koneksi" gagal tapi database jelas jalan</div>
      <div class="sdesc small">PHP di server mungkin tidak punya ekstensi PDO MySQL. Cek: <code>php -m | grep pdo</code>. Solusi: <code>sudo apt install php-mysql</code> lalu restart web server.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Status server cadangan "Ada masalah" terus</div>
      <div class="sdesc small">Kemungkinan: jaringan putus, password berubah, atau server utama restart. Cek detail error di tab Status. Biasanya cukup tunggu beberapa menit — MySQL slave akan reconnect sendiri.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Laptop cadangan jarang dinyalakan, apa tetap aman?</div>
      <div class="sdesc small">Tetap aman. Saat laptop menyala dan terowongan SSH aktif, MySQL slave otomatis catch-up semua data yang tertinggal. Tidak perlu action manual.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Berapa lama proses pemulihan setelah darurat?</div>
      <div class="sdesc small">Tergantung berapa lama mode darurat berlangsung dan berapa banyak transaksi. Untuk pemadaman 1-2 jam dengan ratusan transaksi, proses pemulihan biasanya 5-15 menit.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Apakah dump lokal sudah terenkripsi?</div>
      <div class="sdesc small">Runner menyimpan dump lokal apa adanya. Jika membutuhkan perlindungan off-site, gunakan mekanisme terpisah yang mengenkripsi dump sebelum dipindahkan dan batasi akses ke folder <code>backup/</code>.</div>
    </div>
  </div>
</div>

<!-- BAB 9 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">9</span>Sinkron IP Domain Cloudflare (DDNS)</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Gunakan ini jika IP publik server bisa berubah dan semua domain Cloudflare perlu mengikuti IP server secara otomatis.</p>

    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Pakai script baru di folder finance</div>
          <div class="sdesc">Lokasi script: <code>scripts/cloudflare/ddns_sync.sh</code>. Script ini hanya memproses record <code>A</code>, jadi record bertipe <strong>Tunnel</strong> otomatis dilewati.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Buat file konfigurasi</div>
          <div class="sdesc">Salin template lalu isi token dan zone Cloudflare:</div>
          <div class="dbt-code">cd <?php echo rtrim(FCPATH, '/'); ?>/scripts/cloudflare
cp .env.example .env
chmod 600 .env</div>
          <div class="guide-note mt-1">Jika semua record <code>A</code> di zone memang milik server ini, biarkan <code>CF_INCLUDE_NAMES</code> kosong. Jika zone dipakai beberapa server, isi <code>CF_INCLUDE_NAMES</code> atau <code>CF_EXCLUDE_NAMES</code> agar tidak semua host dipindah ke IP server ini.</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Tes manual sekali</div>
          <div class="dbt-code">chmod +x <?php echo rtrim(FCPATH, '/'); ?>/scripts/cloudflare/ddns_sync.sh
<?php echo rtrim(FCPATH, '/'); ?>/scripts/cloudflare/ddns_sync.sh --force</div>
          <div class="sdesc">Gunakan <code>--force</code> saat pertama kali agar script tetap cek dan sinkron walaupun cache IP lama belum ada.</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Pasang cron tiap 5 menit</div>
          <div class="dbt-code">*/5 * * * * <?php echo rtrim(FCPATH, '/'); ?>/scripts/cloudflare/ddns_sync.sh >> <?php echo rtrim(FCPATH, '/'); ?>/backup/logs/cloudflare_ddns.log 2>&amp;1</div>
          <div class="sdesc">Cukup satu cron ini saja. Tidak perlu lagi memakai script lama di <code>/usr/local/bin</code>.</div>
        </div>
      </li>
      <li>
        <div class="snum">5</div>
        <div class="sbody">
          <div class="stitle">Saat pindah server</div>
          <div class="sdesc">Cukup salin folder <code>scripts/cloudflare/</code>, isi ulang file <code>.env</code> di server baru, lalu pasang cron yang sama. Tidak perlu edit script inti lagi.</div>
        </div>
      </li>
    </ol>
  </div>
</div>

</div><!-- /tab-panduan -->

<script>
/* ── Table Picker ─────────────────────────────────────────── */
(function () {
  const BASE = '<?php echo site_url(); ?>';
  let allTables = [];
  let selectedTables = new Set();
  function getPickerModal() {
    const el = document.getElementById('tablePickerModal');
    return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
  }

  function e(v) { const d = document.createElement('div'); d.textContent = String(v??''); return d.innerHTML; }

  function initFromHidden() {
    const raw = document.getElementById('b_exclude')?.value || '';
    selectedTables = new Set(raw.split(',').map(s => s.trim()).filter(Boolean));
  }

  function updateCounter() {
    const cnt = document.getElementById('tpicker-selected-count');
    if (cnt) cnt.textContent = selectedTables.size;
  }

  function renderChips() {
    const chips = document.getElementById('b_exclude_chips');
    const count = document.getElementById('b_exclude_count');
    if (!chips) return;
    const arr = [...selectedTables].filter(Boolean).sort();
    chips.innerHTML = arr.map(t =>
      `<span data-chip="${e(t)}" style="display:inline-flex;align-items:center;gap:.3rem;background:#fff0ee;color:#9f2141;border:1px solid #f5c6c6;border-radius:999px;font-size:.75rem;font-weight:700;padding:.15rem .55rem">
        ${e(t)}
        <button type="button" data-remove="${e(t)}" style="background:none;border:none;padding:0;color:#9f2141;cursor:pointer;line-height:1;font-size:.85rem;display:flex;align-items:center">✕</button>
       </span>`
    ).join('');
    if (count) count.textContent = arr.length > 0 ? `${arr.length} tabel dikecualikan` : '';
  }

  // Delegated click untuk chip remove
  document.getElementById('b_exclude_chips')?.addEventListener('click', function(ev) {
    const btn = ev.target.closest('[data-remove]');
    if (!btn) return;
    selectedTables.delete(btn.dataset.remove);
    document.getElementById('b_exclude').value = [...selectedTables].join(',');
    renderChips();
  });

  function syncCardState() {
    document.querySelectorAll('#tpicker-rows .tpicker-card').forEach(card => {
      const name    = card.dataset.name;
      const isSelected = selectedTables.has(name);
      const cb = card.querySelector('input[type="checkbox"]');
      card.classList.toggle('selected', isSelected);
      if (cb) cb.checked = isSelected;
    });
    updateCounter();
  }

  function renderGrid(filter, prefix) {
    const rows = document.getElementById('tpicker-rows');
    if (!rows) return;
    const q = filter.toLowerCase();
    const filtered = allTables.filter(t =>
      (!q || t.name.toLowerCase().includes(q)) &&
      (!prefix || t.name.startsWith(prefix + '_'))
    );
    rows.innerHTML = filtered.map(t => {
      const isSelected = selectedTables.has(t.name);
      const pref       = t.name.includes('_') ? t.name.split('_')[0] : '';
      const shortName  = pref ? t.name.slice(pref.length + 1) : t.name;
      const sizeClass  = t.size_mb > 50 ? 'tpicker-size-warn' : '';
      const meta = [
        t.est_rows > 0 ? `~${t.est_rows.toLocaleString('id-ID')} baris` : null,
        t.size_mb > 0  ? `${t.size_mb} MB` : null,
      ].filter(Boolean).join(' · ');
      return `<div class="col-md-4 col-sm-6 mb-1">
        <div class="tpicker-card${isSelected ? ' selected' : ''}" data-name="${e(t.name)}" role="checkbox" tabindex="0" aria-checked="${isSelected}">
          <div style="display:flex;align-items:flex-start;gap:.5rem;pointer-events:none">
            <input type="checkbox" ${isSelected ? 'checked' : ''} style="margin-top:.18rem;flex-shrink:0;pointer-events:none">
            <div>
              ${pref ? `<span class="tpicker-prefix">${e(pref)}_</span>` : ''}
              <div class="tpicker-tname">${e(shortName)}</div>
              ${meta ? `<div class="tpicker-meta ${sizeClass}">${meta}${t.size_mb > 50 ? ' ⚠ Besar' : ''}</div>` : ''}
            </div>
          </div>
        </div>
      </div>`;
    }).join('');
    updateCounter();
  }

  // Delegated click di grid — klik card mana saja toggle
  document.getElementById('tpicker-rows')?.addEventListener('click', function(ev) {
    const card = ev.target.closest('.tpicker-card');
    if (!card) return;
    const name = card.dataset.name;
    if (!name) return;
    if (selectedTables.has(name)) {
      selectedTables.delete(name);
      card.classList.remove('selected');
      card.setAttribute('aria-checked', 'false');
    } else {
      selectedTables.add(name);
      card.classList.add('selected');
      card.setAttribute('aria-checked', 'true');
    }
    const cb = card.querySelector('input[type="checkbox"]');
    if (cb) cb.checked = selectedTables.has(name);
    updateCounter();
  });

  // Keyboard support (Enter/Space)
  document.getElementById('tpicker-rows')?.addEventListener('keydown', function(ev) {
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    const card = ev.target.closest('.tpicker-card');
    if (card) { ev.preventDefault(); card.click(); }
  });

  function buildPrefixFilter() {
    const prefixes = [...new Set(allTables.map(t => t.name.includes('_') ? t.name.split('_')[0] : '').filter(Boolean))].sort();
    const sel = document.getElementById('tpicker-filter');
    if (!sel) return;
    sel.innerHTML = '<option value="">Semua prefix</option>' + prefixes.map(p => `<option value="${e(p)}">${e(p)}_</option>`).join('');
  }

  async function loadTables() {
    const loading = document.getElementById('tpicker-loading');
    const grid    = document.getElementById('tpicker-grid');
    const errEl   = document.getElementById('tpicker-error');
    loading && (loading.style.display = 'block');
    grid    && (grid.style.display    = 'none');
    errEl   && (errEl.style.display   = 'none');
    try {
      const r = await fetch(BASE + 'dbtools/action/list-tables', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const t = await r.text();
      let j;
      try { j = JSON.parse(t); } catch(_) { throw new Error('Response bukan JSON. Cek koneksi DB di tab Backup Otomatis.'); }
      if (!j.ok) throw new Error(j.message || 'Gagal memuat tabel');
      allTables = Array.isArray(j.tables) ? j.tables : [];
      buildPrefixFilter();
      loading && (loading.style.display = 'none');
      grid    && (grid.style.display    = 'block');
      renderGrid('', '');
    } catch (err) {
      loading && (loading.style.display = 'none');
      errEl   && (errEl.textContent = err.message, errEl.style.display = 'block');
    }
  }

  document.getElementById('btn-pick-tables')?.addEventListener('click', () => {
    initFromHidden();
    loadTables();
    getPickerModal()?.show();
  });
  document.getElementById('tpicker-search')?.addEventListener('input', function () {
    renderGrid(this.value, document.getElementById('tpicker-filter')?.value || '');
  });
  document.getElementById('tpicker-filter')?.addEventListener('change', function () {
    renderGrid(document.getElementById('tpicker-search')?.value || '', this.value);
  });
  document.getElementById('tpicker-select-all')?.addEventListener('click', () => {
    allTables.forEach(t => selectedTables.add(t.name));
    syncCardState();
  });
  document.getElementById('tpicker-clear-all')?.addEventListener('click', () => {
    selectedTables.clear();
    syncCardState();
  });
  document.getElementById('tpicker-confirm')?.addEventListener('click', () => {
    document.getElementById('b_exclude').value = [...selectedTables].join(',');
    renderChips();
    getPickerModal()?.hide();
  });

  // Init chips dari nilai tersimpan saat halaman load
  function esc(v) { const d = document.createElement('div'); d.textContent = String(v??''); return d.innerHTML; }
  initFromHidden();
  renderChips();
})();

function toggleChap(header) {
  const body = header.nextElementSibling;
  const icon = header.querySelector('.guide-toggle-icon');
  body.classList.toggle('open');
  icon.classList.toggle('open');
}
</script>

<!-- Modal pilih tabel exclude -->
<div class="modal fade" id="tablePickerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold"><i class="ri ri-table-line me-1"></i>Pilih Tabel yang Dikecualikan</h5>
          <div class="small text-muted">Tabel yang dicentang <strong>tidak akan</strong> dimasukkan ke dalam backup.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-2">
        <!-- Search + filter -->
        <div class="d-flex gap-2 mb-3">
          <input type="text" id="tpicker-search" class="form-control form-control-sm" placeholder="Cari nama tabel...">
          <select id="tpicker-filter" class="form-select form-select-sm" style="max-width:160px">
            <option value="">Semua prefix</option>
          </select>
          <button type="button" id="tpicker-select-all" class="btn btn-outline-secondary btn-sm">Pilih Semua</button>
          <button type="button" id="tpicker-clear-all" class="btn btn-outline-danger btn-sm">Bersihkan</button>
        </div>
        <!-- Info ukuran -->
        <div id="tpicker-loading" class="text-center py-4 text-muted small">
          <span class="spinner-border spinner-border-sm me-2"></span>Memuat daftar tabel...
        </div>
        <div id="tpicker-error" class="alert alert-danger py-2 small" style="display:none"></div>
        <!-- Grid tabel -->
        <div id="tpicker-grid" style="display:none">
          <div class="row g-1" id="tpicker-rows"></div>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between align-items-center">
        <div class="small text-muted"><span id="tpicker-selected-count">0</span> tabel dipilih</div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" id="tpicker-confirm" class="btn btn-primary">Terapkan</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .tpicker-card { border:1px solid rgba(224,209,198,.65); border-radius:10px; padding:.5rem .7rem; cursor:pointer; transition:all .12s; background:#fffdfb; }
  .tpicker-card:hover { background:#fff8f3; border-color:#d9c8bc; }
  .tpicker-card.selected { background:#fff0ee; border-color:#9f2141; }
  .tpicker-card label { cursor:pointer; display:flex; align-items:flex-start; gap:.5rem; width:100%; }
  .tpicker-tname { font-size:.84rem; font-weight:700; color:#2d1f1c; word-break:break-all; }
  .tpicker-meta  { font-size:.72rem; color:#8a776d; margin-top:.1rem; }
  .tpicker-prefix { font-size:.7rem; font-weight:700; padding:.08rem .4rem; border-radius:999px; background:#eef3f1; color:#1a6450; margin-bottom:.2rem; display:inline-block; }
  .tpicker-size-warn { background:#fff3cd; color:#856404; }
</style>

<!-- Modal konfirmasi darurat -->
<div class="modal fade" id="failoverModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#fef3c7">
        <h5 class="modal-title fw-bold"><i class="ri ri-alert-line me-1 text-warning"></i>Konfirmasi Mode Darurat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Kamu yakin server utama <strong>benar-benar tidak bisa diakses</strong> dan perlu mengaktifkan server ini sebagai pengganti sementara?</p>
        <p class="text-muted small">Jika server utama hanya lambat atau sedang restart, tunggu dulu — tidak perlu mode darurat.</p>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="fail-agree">
          <label class="form-check-label small" for="fail-agree">
            Server utama memang tidak bisa diakses dan saya perlu mengaktifkan server ini.
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btn-fail-confirm" class="btn btn-warning" disabled>Aktifkan Mode Darurat</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const BASE = '<?php echo site_url(); ?>';
  const systemToolsMutationCsrfToken = <?php echo json_encode((string)($system_tools_mutation_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  // ── Tabs ──────────────────────────────────────────────────────
  document.querySelectorAll('.dbt-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.dbt-tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.dbt-pane').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab)?.classList.add('active');
    });
  });

  // ── Helpers ───────────────────────────────────────────────────
  function esc(v) { const d = document.createElement('div'); d.textContent = String(v??''); return d.innerHTML; }
  function alert(type, msg) {
    const el = document.getElementById('dbt-alert');
    el.className = 'alert alert-' + type + ' border-0';
    el.innerHTML = msg;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 6000);
  }
  function output(id, text, show) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text; el.style.display = show ? 'block' : 'none'; }
  }
  function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) { btn._html = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...'; }
    else { btn.disabled = false; btn.innerHTML = btn._html || btn.innerHTML; }
  }
  async function post(url, data) {
    const r = await fetch(BASE + url, { method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-System-Tools-CSRF':systemToolsMutationCsrfToken}, body:JSON.stringify(data) });
    const t = await r.text();
    let j; try { j = JSON.parse(t); } catch(e) { throw new Error('Response error. Cek permission.'); }
    if (!j.ok) throw new Error(j.message || 'Gagal');
    return j;
  }
  async function get(url) {
    const r = await fetch(BASE + url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const t = await r.text();
    let j; try { j = JSON.parse(t); } catch(e) { throw new Error('Response error. Kemungkinan permission belum ada atau halaman error.'); }
    if (!j.ok) throw new Error(j.message || 'Gagal');
    return j;
  }

  // ── Show/hide slave, tunnel, & wizard panels ─────────────────
  document.querySelectorAll('input[name="repl_role"]').forEach(el => {
    el.addEventListener('change', () => {
      const v = el.value;
      document.getElementById('slave-fields')?.classList.toggle('d-none', v !== 'SLAVE');
      document.getElementById('repl-wizard')?.classList.toggle('d-none', v === 'STANDALONE');
      document.getElementById('wizard-master')?.classList.toggle('d-none', v !== 'MASTER');
      document.getElementById('wizard-slave')?.classList.toggle('d-none', v !== 'SLAVE');
    });
  });
  document.getElementById('t_enabled')?.addEventListener('change', function() {
    document.getElementById('tunnel-fields')?.classList.toggle('d-none', !this.checked);
  });

  // ── Kumpulkan data settings ───────────────────────────────────
  function getVal(id) { return document.getElementById(id)?.value?.trim() || ''; }
  function backupPayload() {
    return { 'backup.db_host': getVal('b_db_host'), 'backup.db_port': getVal('b_db_port'),
             'backup.db_user': getVal('b_db_user'), 'backup.db_pass': document.getElementById('b_db_pass')?.value || '',
             'backup.db_name': getVal('b_db_name'), 'backup.retention_days': getVal('b_retention'),
             'backup.exclude_tables': getVal('b_exclude') };
  }
  function replPayload() {
    return { 'repl.server_role': document.querySelector('input[name="repl_role"]:checked')?.value || 'STANDALONE',
             'repl.master_host': getVal('r_master_host'), 'repl.master_port': getVal('r_master_port'),
             'repl.repl_user': getVal('r_repl_user'), 'repl.repl_pass': document.getElementById('r_repl_pass')?.value || '',
             'tunnel.enabled': document.getElementById('t_enabled')?.checked ? '1' : '0',
             'tunnel.ssh_host': getVal('t_ssh_host'), 'tunnel.ssh_port': getVal('t_ssh_port'),
             'tunnel.ssh_user': getVal('t_ssh_user'),
             'tunnel.local_port': getVal('t_local_port'), 'tunnel.remote_port': getVal('t_remote_port') };
  }

  // ── Save actions ──────────────────────────────────────────────
  document.getElementById('btn-save-backup')?.addEventListener('click', async function() {
    setLoading(this, true);
    try { const j = await post('dbtools/settings/save', backupPayload()); alert('success', '✓ ' + esc(j.message)); }
    catch(e) { alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });
  document.getElementById('btn-save-replication')?.addEventListener('click', async function() {
    setLoading(this, true);
    try { const j = await post('dbtools/settings/save', replPayload()); alert('success', '✓ ' + esc(j.message)); }
    catch(e) { alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Test DB ───────────────────────────────────────────────────
  document.getElementById('btn-test-db')?.addEventListener('click', async function() {
    setLoading(this, true);
    const res = document.getElementById('db-test-result');
    try {
      const j = await post('dbtools/action/test-db', { host:getVal('b_db_host'), port:getVal('b_db_port'), user:getVal('b_db_user'), pass:document.getElementById('b_db_pass')?.value||'', name:getVal('b_db_name') });
      res.innerHTML = '<span class="text-success fw-semibold">✓ ' + esc(j.message) + '</span>';
    } catch(e) { res.innerHTML = '<span class="text-danger">✗ ' + esc(e.message) + '</span>'; }
    finally { setLoading(this, false); }
  });

  // ── Run backup ────────────────────────────────────────────────
  document.getElementById('btn-run-backup')?.addEventListener('click', async function() {
    setLoading(this, true); output('out-backup', 'Menjalankan backup, harap tunggu...', true);
    try { const j = await post('dbtools/action/run-backup', {}); output('out-backup', j.output || 'Selesai.', true); alert('success', '✓ Backup selesai!'); }
    catch(e) { output('out-backup', e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Cek replication ───────────────────────────────────────────
  document.getElementById('btn-check-repl')?.addEventListener('click', async function() {
    setLoading(this, true); output('out-repl', '', false);
    try {
      const j = await get('dbtools/action/check-replication');
      const el = document.getElementById('repl-status-display');
      const ok  = j.status === 'OK';
      const lag = j.lag_seconds !== undefined ? ' · keterlambatan ' + j.lag_seconds + 's' : '';
      el.innerHTML = ok
        ? '<span class="status-ok"><i class="ri ri-checkbox-circle-line"></i>Sinkron' + lag + '</span>'
        : '<span class="status-err"><i class="ri ri-close-circle-line"></i>ERROR</span>';
      if (!ok) {
        let detail = '';
        if (j.io_running)  detail += 'IO Thread: '  + esc(j.io_running)  + '\n';
        if (j.sql_running) detail += 'SQL Thread: ' + esc(j.sql_running) + '\n';
        if (j.last_io_error)  detail += 'IO Error: '  + esc(j.last_io_error)  + '\n';
        if (j.last_error)     detail += 'SQL Error: ' + esc(j.last_error) + '\n';
        if (!detail) detail = 'Tidak ada detail error. Cek tunnel SSH masih berjalan.';
        output('out-repl', detail.trim(), true);
      }
    } catch(e) { alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Bandingkan Data ───────────────────────────────────────────
  let _diffTables = [];

  document.getElementById('btn-compare-data')?.addEventListener('click', async function() {
    const wrap = document.getElementById('out-compare');
    setLoading(this, true);
    wrap.style.display = 'none'; wrap.innerHTML = '';
    _diffTables = [];
    try {
      const j = await get('dbtools/action/compare-data');
      _diffTables = (j.results || []).filter(r => !r.match).map(r => r.table);

      const rows = (j.results || []).map(r => {
        const cls      = r.match ? 'text-success' : 'text-danger fw-bold';
        const icon     = r.match ? '✓' : '✗';
        const slaveVal = r.slave === null ? '(tabel tidak ada)' : r.slave;
        const selisih  = r.match ? '' : Math.abs(r.master - (r.slave ?? 0)).toLocaleString('id-ID') + ' beda';
        return `<tr class="${r.match ? '' : 'table-danger'}">
          <td class="${cls}">${icon}</td>
          <td>${esc(r.table)}</td>
          <td class="text-end">${r.master.toLocaleString('id-ID')}</td>
          <td class="text-end ${cls}">${typeof slaveVal === 'number' ? slaveVal.toLocaleString('id-ID') : esc(String(slaveVal))}</td>
          <td class="text-end text-muted" style="font-size:.78rem">${selisih}</td>
        </tr>`;
      }).join('');

      const resyncBtn = _diffTables.length > 0
        ? `<button type="button" id="btn-resync-diff" class="btn btn-warning btn-sm mt-2">
             <i class="ri ri-refresh-line me-1"></i>Resync ${_diffTables.length} Tabel yang Berbeda
           </button>
           <div id="out-resync" class="dbt-output mt-2"></div>`
        : `<div class="text-success small mt-2 fw-semibold"><i class="ri ri-checkbox-circle-line me-1"></i>Semua tabel sinkron!</div>`;

      wrap.innerHTML = `
        <div class="small fw-semibold mb-2">
          ${j.ok} / ${j.total} tabel sama
          ${j.diff > 0 ? `· <span class="text-danger">${j.diff} tabel berbeda</span>` : ''}
        </div>
        <div style="max-height:280px;overflow-y:auto">
          <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
            <thead class="table-light">
              <tr><th></th><th>Tabel</th><th class="text-end">Master</th><th class="text-end">Slave (Laptop)</th><th class="text-end">Selisih</th></tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        ${resyncBtn}`;
      wrap.style.display = 'block';

      document.getElementById('btn-resync-diff')?.addEventListener('click', resyncDiffTables);

      if (j.diff > 0) alert('warning', `⚠ ${j.diff} tabel berbeda. Klik "Resync Tabel yang Berbeda".`);
      else alert('success', '✓ Semua tabel sudah sinkron!');
    } catch(e) { alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  async function resyncDiffTables() {
    if (!_diffTables.length) return;
    const btn   = document.getElementById('btn-resync-diff');
    const outEl = document.getElementById('out-resync');
    setLoading(btn, true);
    outEl.textContent = ''; outEl.style.display = 'block';

    const total  = _diffTables.length;
    const synced = [], failed = [];
    let   pending = [..._diffTables];

    // Kirim semua tabel sekaligus — server proses 25 detik lalu return sisa
    // JS panggil ulang dengan sisa tabel sampai habis
    while (pending.length > 0) {
      outEl.textContent += `Menyinkronkan ${pending.length} tabel (${synced.length + failed.length}/${total} selesai)...\n`;
      outEl.scrollTop = outEl.scrollHeight;
      try {
        const j = await post('dbtools/action/initial-sync', { tables: pending });

        // Update hasil
        (j.synced || []).forEach(s => { const t = s.split(' (')[0]; synced.push(t); outEl.textContent += `  ✓ ${s}\n`; });
        (j.errors || []).forEach(e => {
          const t = e.split(':')[0].trim();
          if (!failed.includes(t)) failed.push(t);
          outEl.textContent += `  ✗ ${e}\n`;
        });

        // Cegah infinite loop: tabel gagal TIDAK boleh diretry
        const doneSet = new Set([...synced, ...failed]);
        pending = (j.remaining || []).filter(t => !doneSet.has(t));
        if (pending.length > 0) outEl.textContent += `  → ${pending.length} tabel dilanjutkan...\n`;

      } catch(e) {
        outEl.textContent += `  ✗ Error: ${e.message}\n`;
        break; // stop jika koneksi benar-benar putus
      }
      outEl.scrollTop = outEl.scrollHeight;
    }

    outEl.textContent += `\n─── ${synced.length}/${total} tabel berhasil ───\n`;
    if (failed.length) {
      outEl.textContent += `Tabel gagal: ${failed.join(', ')}\n`;
      outEl.textContent += `\nAlternatif di terminal server:\n`;
      outEl.textContent += `mysqldump -u root -p --single-transaction db_finance ${failed.join(' ')} | gzip > /tmp/sync.sql.gz\n`;
    }
    outEl.scrollTop = outEl.scrollHeight;
    setLoading(btn, false);
    if (!failed.length) alert('success', `✓ Semua ${total} tabel berhasil!`);
    else alert('warning', `⚠ ${synced.length} berhasil, ${failed.length} gagal.`);
  }

  // ── Apply MySQL config (SET GLOBAL + conf.d) ─────────────────
  async function applyMysqlConfig(role, outId, btnEl) {
    const serverId = role === 'MASTER' ? 1 : 2;
    setLoading(btnEl, true);
    output(outId, 'Menerapkan konfigurasi MySQL...', true);
    try {
      const j = await post('dbtools/action/apply-mysql-config', { role, server_id: serverId });
      let msg = '';
      if (j.applied?.length)  msg += '✓ SET GLOBAL diterapkan:\n  ' + j.applied.join('\n  ');
      if (j.failed?.length)   msg += '\n✗ Gagal:\n  ' + j.failed.map(f => f.sql + ' → ' + f.error).join('\n  ');
      const binlogInfo = j.binlog_on ? '✓ AKTIF' : (j.role === 'SLAVE' ? '— Tidak wajib untuk slave' : '✗ Belum aktif — perlu aktifkan di master');
      msg += '\n\nBinary logging (log_bin): ' + binlogInfo;
      if (j.conf_written)     msg += '\n✓ Config disimpan ke: ' + j.conf_path;
      else                    msg += '\n⚠ Config tidak bisa ditulis otomatis.\n  Tambahkan manual ke /etc/mysql/my.cnf atau conf.d:\n\n' + j.snippet;
      if (j.needs_restart)    msg += '\n\n⚠ MySQL master perlu di-restart agar log_bin aktif:\n  sudo systemctl restart mysql';
      output(outId, msg, true);
      if (!j.needs_restart && !j.failed?.length) alert('success', '✓ Konfigurasi MySQL berhasil diterapkan sepenuhnya.');
      else alert('warning', '⚠ Sebagian konfigurasi diterapkan. Cek detail di output.');
    } catch(e) { output(outId, e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(btnEl, false); }
  }

  document.getElementById('btn-apply-master-cfg')?.addEventListener('click', function() {
    applyMysqlConfig('MASTER', 'out-apply-master', this);
  });
  document.getElementById('btn-apply-slave-cfg')?.addEventListener('click', function() {
    applyMysqlConfig('SLAVE', 'out-apply-slave', this);
  });

  // ── Setup Master (buat replication user) ─────────────────────
  document.getElementById('btn-setup-master')?.addEventListener('click', async function() {
    const pass = document.getElementById('wm_repl_pass')?.value || '';
    if (!pass) { alert('danger', 'Password replikasi wajib diisi.'); return; }
    setLoading(this, true); output('out-setup-master', 'Membuat user replikasi...', true);
    try {
      const j = await post('dbtools/action/setup-master', {
        repl_user: document.getElementById('wm_repl_user')?.value || 'repl_user',
        repl_pass: pass,
      });
      const msg = j.message + (j.binlog && j.binlog !== '-' ? `\nBinlog: ${j.binlog} pos ${j.position}` : '');
      output('out-setup-master', msg, true);
      alert('success', '✓ ' + esc(j.message));
    } catch(e) { output('out-setup-master', e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Sinkronisasi Data Awal ────────────────────────────────────
  document.getElementById('btn-initial-sync')?.addEventListener('click', async function() {
    setLoading(this, true); output('out-initial-sync', 'Menyinkronkan data dari server utama... (mungkin beberapa menit)', true);
    try {
      const j = await post('dbtools/action/initial-sync', {});
      let msg = '✓ ' + j.message;
      if (j.synced?.length)  msg += '\n\nTabel disalin:\n  ' + j.synced.join('\n  ');
      if (j.skipped?.length) msg += '\n\nDikecualikan:\n  ' + j.skipped.join(', ');
      if (j.errors?.length)  msg += '\n\nPerlu sync manual:\n  ' + j.errors.join('\n  ');
      output('out-initial-sync', msg, true);
      alert('success', '✓ Sinkronisasi selesai!');
    } catch(e) { output('out-initial-sync', e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Init Slave (CHANGE MASTER TO) ────────────────────────────
  document.getElementById('btn-init-slave')?.addEventListener('click', async function() {
    setLoading(this, true); output('out-init-slave', 'Menghubungkan ke server utama...', true);
    try {
      const j = await post('dbtools/action/restart-replication', {
        master_host: getVal('r_master_host'), master_port: getVal('r_master_port'),
        repl_user: getVal('r_repl_user'), repl_pass: document.getElementById('r_repl_pass')?.value || '',
      });
      output('out-init-slave', j.message, true); alert('success', '✓ ' + esc(j.message));
    } catch(e) { output('out-init-slave', e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Failover (modal) ──────────────────────────────────────────
  document.getElementById('btn-failover')?.addEventListener('click', () => {
    document.getElementById('fail-agree').checked = false;
    document.getElementById('btn-fail-confirm').disabled = true;
    new bootstrap.Modal(document.getElementById('failoverModal')).show();
  });
  document.getElementById('fail-agree')?.addEventListener('change', function() {
    document.getElementById('btn-fail-confirm').disabled = !this.checked;
  });
  document.getElementById('btn-fail-confirm')?.addEventListener('click', async function() {
    bootstrap.Modal.getInstance(document.getElementById('failoverModal'))?.hide();
    const btn = document.getElementById('btn-failover');
    setLoading(btn, true); output('out-failover', 'Mengaktifkan mode darurat...', true);
    try {
      const j = await post('dbtools/action/failover', { confirm: 'YES_FAILOVER' });
      output('out-failover', j.message, true); alert('success', '✓ Mode darurat aktif. Muat ulang halaman.');
      btn.disabled = true; btn.textContent = 'Mode Darurat Sudah Aktif';
      document.getElementById('btn-restart-repl').disabled = false;
    } catch(e) { output('out-failover', e.message, true); alert('danger', esc(e.message)); setLoading(btn, false); }
  });

  // ── Restart replication ───────────────────────────────────────
  document.getElementById('btn-restart-repl')?.addEventListener('click', async function() {
    setLoading(this, true); output('out-restart', 'Menghubungkan ke server utama...', true);
    try {
      const j = await post('dbtools/action/restart-replication', {
        master_host: getVal('r_master_host'), master_port: getVal('r_master_port'),
        repl_user: getVal('r_repl_user'), repl_pass: document.getElementById('r_repl_pass')?.value || '',
      });
      output('out-restart', j.message, true); alert('success', '✓ Koneksi pulih. Server kembali ke mode cadangan.');
      this.disabled = true;
      document.getElementById('btn-failover').disabled = false;
    } catch(e) { output('out-restart', e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });
})();
</script>
