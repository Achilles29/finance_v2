<?php
$cfg          = is_array($cfg ?? null) ? $cfg : [];
$envExists    = !empty($env_exists);
$recentDumps  = is_array($recent_dumps ?? null) ? $recent_dumps : [];
$replStatus   = is_array($repl_status ?? null) ? $repl_status : [];
$failoverActive = !empty($failover_active);
$failoverTime   = (string)($failover_time ?? '');
$isWindows    = !empty($is_windows);

function cfgVal(array $cfg, string $key, string $default = ''): string {
    return html_escape((string)($cfg[$key] ?? $default));
}
?>
<style>
  .dbt-card  { border:0; border-radius:16px; box-shadow:0 4px 16px rgba(58,38,30,.07); }
  .dbt-section-title { font-size:.76rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#8a776d; margin-bottom:.75rem; }
  .dbt-status-ok   { background:#dcfce7; color:#166534; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .dbt-status-warn { background:#fff3cd; color:#856404; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .dbt-status-err  { background:#fee2e2; color:#991b1b; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .dbt-output { background:#1e1e2e; color:#cdd6f4; border-radius:10px; padding:.9rem 1rem; font-family:monospace; font-size:.8rem; max-height:260px; overflow-y:auto; white-space:pre-wrap; display:none; }
  .dbt-dump-item { display:flex; justify-content:space-between; align-items:center; padding:.35rem .6rem; border-bottom:1px solid rgba(224,209,198,.4); }
  .dbt-dump-item:last-child { border-bottom:0; }
  .failover-banner { background:linear-gradient(135deg,#fef3c7,#fffbeb); border:2px solid #f59e0b; border-radius:14px; padding:.9rem 1rem; }
</style>

<!-- Failover banner -->
<?php if ($failoverActive): ?>
<div class="failover-banner mb-4">
  <div class="fw-bold mb-1"><i class="ri ri-alert-line me-1 text-warning"></i>FAILOVER AKTIF sejak <?php echo html_escape($failoverTime); ?></div>
  <div class="small">Server ini beroperasi sebagai master sementara. Setelah Server 1 online, gunakan aksi <strong>Restart Replication</strong> di bawah.</div>
</div>
<?php endif; ?>

<div class="fin-page-header mb-4">
  <div>
    <p class="fin-breadcrumb"><a href="<?php echo site_url('dbtools'); ?>">DB Tools</a> / Pengaturan</p>
    <h4 class="fin-page-title"><i class="ri ri-settings-3-line me-1 text-primary"></i>Pengaturan DB Tools</h4>
    <p class="fin-page-subtitle mb-0">Konfigurasi backup otomatis &amp; replication tersimpan di database, tidak perlu edit file manual.</p>
  </div>
  <div class="fin-page-actions">
    <a href="<?php echo site_url('dbtools'); ?>" class="btn btn-outline-info btn-sm">
      <i class="ri ri-book-open-line me-1"></i>Panduan
    </a>
  </div>
</div>

<div id="dbt-alert" class="mb-3" style="display:none"></div>

<!-- ── Nav Tabs ─────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-4" id="dbtTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-backup">Backup (Skema 1)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-replication">Replication (Skema 2)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-status">Status &amp; Aksi</a></li>
</ul>

<div class="tab-content">

  <!-- ── TAB 1: Backup ──────────────────────────────────────── -->
  <div class="tab-pane fade show active" id="tab-backup">
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card dbt-card p-4">
          <div class="dbt-section-title">Koneksi Database</div>
          <div class="row g-3 mb-4">
            <div class="col-md-8">
              <label class="form-label small mb-1">Host</label>
              <input type="text" id="cfg_backup_db_host" class="form-control" value="<?php echo cfgVal($cfg, 'backup.db_host', 'localhost'); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label small mb-1">Port</label>
              <input type="number" id="cfg_backup_db_port" class="form-control" value="<?php echo cfgVal($cfg, 'backup.db_port', '3306'); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small mb-1">User</label>
              <input type="text" id="cfg_backup_db_user" class="form-control" value="<?php echo cfgVal($cfg, 'backup.db_user', 'root'); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small mb-1">Password</label>
              <input type="password" id="cfg_backup_db_pass" class="form-control" placeholder="Kosongkan jika tidak berubah" autocomplete="new-password">
            </div>
            <div class="col-12">
              <label class="form-label small mb-1">Nama Database</label>
              <input type="text" id="cfg_backup_db_name" class="form-control" value="<?php echo cfgVal($cfg, 'backup.db_name', 'db_finance'); ?>">
            </div>
            <div class="col-12">
              <button type="button" id="btn-test-db" class="btn btn-outline-info btn-sm">
                <i class="ri ri-database-line me-1"></i>Test Koneksi
              </button>
              <span id="db-test-result" class="ms-2 small"></span>
            </div>
          </div>

          <div class="dbt-section-title">Pengaturan Backup</div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small mb-1">Simpan lokal (hari)</label>
              <input type="number" id="cfg_backup_retention_days" class="form-control" min="1" max="30"
                     value="<?php echo cfgVal($cfg, 'backup.retention_days', '3'); ?>">
            </div>
            <div class="col-12">
              <label class="form-label small mb-1">Tabel dikecualikan <small class="text-muted">(pisah koma)</small></label>
              <input type="text" id="cfg_backup_exclude_tables" class="form-control"
                     placeholder="misal: sys_audit_log,att_presence"
                     value="<?php echo cfgVal($cfg, 'backup.exclude_tables', ''); ?>">
            </div>
            <div class="col-12">
              <div class="alert alert-info border-0 small mb-0">Runner backup hanya membuat dump dan log lokal di host ini. Backup off-site terenkripsi harus dikonfigurasi secara terpisah.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card dbt-card p-4 mb-3">
          <div class="dbt-section-title">Cron Schedule</div>
          <div class="text-muted small mb-2">Script berjalan otomatis. Panduan setup cron:</div>
          <?php if ($isWindows): ?>
          <div class="alert alert-info border-0 small py-2">
            Windows: Gunakan <strong>Task Scheduler</strong>. Jalankan<br>
            <code>scripts\backup\backup_full.bat</code> setiap 30 menit.
          </div>
          <?php else: ?>
          <pre style="background:#1e1e2e;color:#cdd6f4;border-radius:8px;padding:.6rem .8rem;font-size:.76rem">*/30 * * * * <?php echo rtrim(FCPATH, '/'); ?>/scripts/backup/backup_full.sh</pre>
          <?php endif; ?>
          <div class="text-muted small">File .env diperbarui otomatis saat Save.</div>
          <div class="mt-2">
            <span class="<?php echo $envExists ? 'dbt-status-ok' : 'dbt-status-warn'; ?>">
              .env: <?php echo $envExists ? 'Ada' : 'Belum dibuat'; ?>
            </span>
          </div>
        </div>

        <div class="card dbt-card p-4">
          <div class="dbt-section-title">Dump Terakhir</div>
          <?php if (empty($recentDumps)): ?>
            <div class="text-muted small">Belum ada dump tersimpan.</div>
          <?php else: ?>
            <?php foreach ($recentDumps as $d): ?>
              <div class="dbt-dump-item">
                <div class="small"><?php echo html_escape($d['name']); ?><br>
                  <span class="text-muted" style="font-size:.72rem"><?php echo html_escape($d['date']); ?></span>
                </div>
                <span class="badge bg-secondary"><?php echo html_escape($d['size']); ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button type="button" id="btn-save-backup" class="btn btn-primary">
        <i class="ri ri-save-line me-1"></i>Simpan &amp; Generate .env
      </button>
    </div>
  </div>

  <!-- ── TAB 2: Replication ─────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-replication">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card dbt-card p-4">
          <div class="dbt-section-title">Role Server Ini</div>
          <div class="row g-3 mb-4">
            <div class="col-12">
              <div class="btn-group w-100" role="group">
                <?php foreach (['STANDALONE' => 'Standalone', 'MASTER' => 'Master (Server 1)', 'SLAVE' => 'Slave (Server 2)'] as $v => $l): ?>
                  <input type="radio" class="btn-check" name="repl_role" id="role_<?php echo $v; ?>" value="<?php echo $v; ?>"
                    <?php echo ($cfg['repl.server_role'] ?? 'STANDALONE') === $v ? 'checked' : ''; ?>>
                  <label class="btn btn-outline-primary" for="role_<?php echo $v; ?>"><?php echo $l; ?></label>
                <?php endforeach; ?>
              </div>
              <div class="text-muted small mt-1">STANDALONE = tidak ada replication aktif.</div>
            </div>
          </div>

          <div id="slave-fields" class="<?php echo ($cfg['repl.server_role'] ?? '') !== 'SLAVE' ? 'd-none' : ''; ?>">
            <div class="dbt-section-title">Koneksi ke Master (Server 1)</div>
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label small mb-1">Host / IP Master</label>
                <input type="text" id="cfg_repl_master_host" class="form-control"
                       placeholder="IP atau domain Server 1"
                       value="<?php echo cfgVal($cfg, 'repl.master_host', ''); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1">Port MySQL</label>
                <input type="number" id="cfg_repl_master_port" class="form-control" value="<?php echo cfgVal($cfg, 'repl.master_port', '3306'); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">User Replication</label>
                <input type="text" id="cfg_repl_repl_user" class="form-control" value="<?php echo cfgVal($cfg, 'repl.repl_user', 'repl_user'); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Password Replication</label>
                <input type="password" id="cfg_repl_repl_pass" class="form-control" placeholder="Kosongkan jika tidak berubah" autocomplete="new-password">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card dbt-card p-4">
          <div class="dbt-section-title">SSH Tunnel (Jika Slave = Laptop Lokal)</div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="cfg_tunnel_enabled" role="switch"
              <?php echo ($cfg['tunnel.enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="cfg_tunnel_enabled">Aktifkan SSH Tunnel</label>
          </div>
          <div id="tunnel-fields" class="<?php echo ($cfg['tunnel.enabled'] ?? '0') !== '1' ? 'd-none' : ''; ?>">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label small mb-1">SSH Host</label>
                <input type="text" id="cfg_tunnel_ssh_host" class="form-control"
                       placeholder="IP/domain Server 1 (sama dengan master_host)"
                       value="<?php echo cfgVal($cfg, 'tunnel.ssh_host', ''); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1">SSH User</label>
                <input type="text" id="cfg_tunnel_ssh_user" class="form-control" value="<?php echo cfgVal($cfg, 'tunnel.ssh_user', 'root'); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Port Lokal Tunnel</label>
                <input type="number" id="cfg_tunnel_local_port" class="form-control" value="<?php echo cfgVal($cfg, 'tunnel.local_port', '3307'); ?>">
                <div class="form-text">MySQL slave akan connect ke localhost:port_ini</div>
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Port Remote (MySQL di Server 1)</label>
                <input type="number" id="cfg_tunnel_remote_port" class="form-control" value="<?php echo cfgVal($cfg, 'tunnel.remote_port', '3306'); ?>">
              </div>
            </div>
            <div class="alert alert-info border-0 small mt-3 py-2">
              Start tunnel: <code>scripts/replication/tunnel_start.<?php echo $isWindows ? 'bat' : 'sh'; ?></code>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <button type="button" id="btn-save-replication" class="btn btn-primary">
        <i class="ri ri-save-line me-1"></i>Simpan Replication Settings
      </button>
    </div>
  </div>

  <!-- ── TAB 3: Status & Aksi ───────────────────────────────── -->
  <div class="tab-pane fade" id="tab-status">
    <div class="row g-3 mb-4">
      <!-- Status kartu -->
      <div class="col-md-4">
        <div class="card dbt-card p-3 h-100">
          <div class="dbt-section-title">Status Backup</div>
          <div id="status-backup-info">
            <?php if (!empty($recentDumps)): ?>
              <span class="dbt-status-ok">Dump terakhir: <?php echo html_escape($recentDumps[0]['date'] ?? '-'); ?></span>
            <?php else: ?>
              <span class="dbt-status-warn">Belum ada dump</span>
            <?php endif; ?>
          </div>
          <div class="mt-3">
            <button type="button" id="btn-run-backup" class="btn btn-outline-primary btn-sm w-100">
              <i class="ri ri-play-line me-1"></i>Jalankan Backup Sekarang
            </button>
          </div>
          <div id="output-backup" class="dbt-output mt-2"></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card dbt-card p-3 h-100">
          <div class="dbt-section-title">Status Replication</div>
          <div id="status-repl-info">
            <?php
              $rs = $replStatus['status'] ?? '-';
              $rsClass = $rs === 'OK' ? 'dbt-status-ok' : ($rs === 'ERROR' ? 'dbt-status-err' : 'dbt-status-warn');
            ?>
            <span class="<?php echo $rsClass; ?>"><?php echo html_escape($rs); ?></span>
            <?php if (isset($replStatus['lag_seconds'])): ?>
              <span class="ms-2 text-muted small">Lag: <?php echo (int)$replStatus['lag_seconds']; ?>s</span>
            <?php endif; ?>
          </div>
          <div class="mt-3">
            <button type="button" id="btn-check-repl" class="btn btn-outline-info btn-sm w-100">
              <i class="ri ri-refresh-line me-1"></i>Cek Status Sekarang
            </button>
          </div>
          <div id="output-repl" class="dbt-output mt-2"></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card dbt-card p-3 h-100">
          <div class="dbt-section-title">Failover State</div>
          <?php if ($failoverActive): ?>
            <span class="dbt-status-warn">Aktif sejak <?php echo html_escape($failoverTime); ?></span>
          <?php else: ?>
            <span class="dbt-status-ok">Normal (tidak ada failover aktif)</span>
          <?php endif; ?>
          <div class="mt-3 text-muted small">
            Gunakan aksi di bawah untuk failover atau restart replication.
          </div>
        </div>
      </div>
    </div>

    <!-- Aksi berbahaya -->
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card dbt-card p-4 border border-warning">
          <div class="fw-bold mb-2"><i class="ri ri-alert-line me-1 text-warning"></i>Failover — Aktifkan Server Ini</div>
          <div class="text-muted small mb-3">
            Gunakan <strong>hanya</strong> saat Server 1 (Master) benar-benar DOWN. Akan:<br>
            • Hentikan slave replication<br>
            • Aktifkan write mode di server ini<br>
            • Catat waktu failover untuk recovery
          </div>
          <button type="button" id="btn-failover" class="btn btn-warning w-100" <?php echo $failoverActive ? 'disabled' : ''; ?>>
            <i class="ri ri-alert-line me-1"></i>Initiate Failover
          </button>
          <div id="output-failover" class="dbt-output mt-2"></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card dbt-card p-4 border border-success">
          <div class="fw-bold mb-2"><i class="ri ri-link me-1 text-success"></i>Restart Replication</div>
          <div class="text-muted small mb-3">
            Jalankan setelah recovery data selesai. Akan:<br>
            • Ambil posisi binlog terbaru dari Master<br>
            • Setup slave ke Master (via tunnel jika aktif)<br>
            • Hapus failover marker
          </div>
          <button type="button" id="btn-restart-repl" class="btn btn-success w-100" <?php echo !$failoverActive ? 'disabled' : ''; ?>>
            <i class="ri ri-link me-1"></i>Restart Replication
          </button>
          <div id="output-restart" class="dbt-output mt-2"></div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /tab-content -->

<!-- Modal Failover Confirm -->
<div class="modal fade" id="failoverModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title fw-bold">Konfirmasi Failover</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Kamu yakin Server 1 (Master) <strong>tidak bisa diakses</strong> dan ingin mengaktifkan server ini sebagai master sementara?</p>
        <p class="text-muted small">Semua transaksi baru akan masuk ke server ini. Jangan lakukan ini jika Server 1 hanya lambat.</p>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="failover-agree">
          <label class="form-check-label small" for="failover-agree">
            Saya mengerti konsekuensinya dan Server 1 memang DOWN.
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btn-failover-confirm" class="btn btn-warning" disabled>Lanjutkan Failover</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const BASE = '<?php echo site_url(); ?>';
  const systemToolsMutationCsrfToken = <?php echo json_encode((string)($system_tools_mutation_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  function esc(v) { const d = document.createElement('div'); d.textContent = String(v??''); return d.innerHTML; }
  function showAlert(type, msg) {
    const el = document.getElementById('dbt-alert');
    el.className = 'alert alert-' + type + ' border-0';
    el.innerHTML = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 6000);
  }
  function setOutput(id, text, visible) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
    el.style.display = visible ? 'block' : 'none';
  }
  function setBtn(id, loading, origHtml) {
    const btn = document.getElementById(id);
    if (!btn) return;
    if (loading) {
      btn._orig = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';
    } else {
      btn.disabled = false;
      btn.innerHTML = btn._orig || origHtml || btn.innerHTML;
    }
  }

  async function apiPost(url, payload) {
    const res = await fetch(BASE + url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-System-Tools-CSRF': systemToolsMutationCsrfToken },
      body: JSON.stringify(payload)
    });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch(e) { throw new Error('Response bukan JSON. Cek permission.'); }
    if (!json.ok) throw new Error(json.message || 'Gagal');
    return json;
  }

  async function apiGet(url) {
    const res = await fetch(BASE + url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const json = await res.json();
    if (!json.ok) throw new Error(json.message || 'Gagal');
    return json;
  }

  function collectBackupCfg() {
    return {
      'backup.db_host':         document.getElementById('cfg_backup_db_host').value.trim(),
      'backup.db_port':         document.getElementById('cfg_backup_db_port').value.trim(),
      'backup.db_user':         document.getElementById('cfg_backup_db_user').value.trim(),
      'backup.db_pass':         document.getElementById('cfg_backup_db_pass').value,
      'backup.db_name':         document.getElementById('cfg_backup_db_name').value.trim(),
      'backup.retention_days':  document.getElementById('cfg_backup_retention_days').value.trim(),
      'backup.exclude_tables':  document.getElementById('cfg_backup_exclude_tables').value.trim(),
    };
  }

  function collectReplCfg() {
    return {
      'repl.server_role':  document.querySelector('input[name="repl_role"]:checked')?.value || 'STANDALONE',
      'repl.master_host':  document.getElementById('cfg_repl_master_host')?.value.trim() || '',
      'repl.master_port':  document.getElementById('cfg_repl_master_port')?.value.trim() || '3306',
      'repl.repl_user':    document.getElementById('cfg_repl_repl_user')?.value.trim() || '',
      'repl.repl_pass':    document.getElementById('cfg_repl_repl_pass')?.value || '',
      'tunnel.enabled':    document.getElementById('cfg_tunnel_enabled').checked ? '1' : '0',
      'tunnel.ssh_host':   document.getElementById('cfg_tunnel_ssh_host')?.value.trim() || '',
      'tunnel.ssh_user':   document.getElementById('cfg_tunnel_ssh_user')?.value.trim() || 'root',
      'tunnel.local_port': document.getElementById('cfg_tunnel_local_port')?.value.trim() || '3307',
      'tunnel.remote_port':document.getElementById('cfg_tunnel_remote_port')?.value.trim() || '3306',
    };
  }

  // Show/hide slave fields on role change
  document.querySelectorAll('input[name="repl_role"]').forEach(el => {
    el.addEventListener('change', () => {
      document.getElementById('slave-fields')?.classList.toggle('d-none', el.value !== 'SLAVE');
    });
  });
  document.getElementById('cfg_tunnel_enabled').addEventListener('change', function() {
    document.getElementById('tunnel-fields')?.classList.toggle('d-none', !this.checked);
  });

  // Save backup settings
  document.getElementById('btn-save-backup').addEventListener('click', async function() {
    setBtn('btn-save-backup', true);
    try {
      const json = await apiPost('dbtools/settings/save', collectBackupCfg());
      showAlert('success', '<i class="ri ri-checkbox-circle-line me-1"></i>' + esc(json.message || 'Tersimpan.'));
    } catch(e) {
      showAlert('danger', esc(e.message));
    } finally { setBtn('btn-save-backup', false); }
  });

  // Save replication settings
  document.getElementById('btn-save-replication').addEventListener('click', async function() {
    setBtn('btn-save-replication', true);
    try {
      const json = await apiPost('dbtools/settings/save', collectReplCfg());
      showAlert('success', '<i class="ri ri-checkbox-circle-line me-1"></i>' + esc(json.message || 'Tersimpan.'));
    } catch(e) {
      showAlert('danger', esc(e.message));
    } finally { setBtn('btn-save-replication', false); }
  });

  // Test DB connection
  document.getElementById('btn-test-db').addEventListener('click', async function() {
    setBtn('btn-test-db', true);
    const result = document.getElementById('db-test-result');
    result.textContent = '';
    try {
      const json = await apiPost('dbtools/action/test-db', {
        host: document.getElementById('cfg_backup_db_host').value,
        port: document.getElementById('cfg_backup_db_port').value,
        user: document.getElementById('cfg_backup_db_user').value,
        pass: document.getElementById('cfg_backup_db_pass').value,
        name: document.getElementById('cfg_backup_db_name').value,
      });
      result.innerHTML = '<span class="text-success fw-semibold"><i class="ri ri-checkbox-circle-line me-1"></i>' + esc(json.message) + '</span>';
    } catch(e) {
      result.innerHTML = '<span class="text-danger"><i class="ri ri-close-circle-line me-1"></i>' + esc(e.message) + '</span>';
    } finally { setBtn('btn-test-db', false); }
  });

  // Run backup
  document.getElementById('btn-run-backup').addEventListener('click', async function() {
    setBtn('btn-run-backup', true);
    setOutput('output-backup', 'Menjalankan backup...', true);
    try {
      const json = await apiPost('dbtools/action/run-backup', {});
      setOutput('output-backup', json.output || 'Backup selesai.', true);
      showAlert('success', 'Backup berhasil dijalankan!');
    } catch(e) {
      setOutput('output-backup', e.message, true);
      showAlert('danger', 'Backup gagal: ' + esc(e.message));
    } finally { setBtn('btn-run-backup', false); }
  });

  // Check replication
  document.getElementById('btn-check-repl').addEventListener('click', async function() {
    setBtn('btn-check-repl', true);
    try {
      const json = await apiGet('dbtools/action/check-replication');
      const statusEl = document.getElementById('status-repl-info');
      const cls = json.status === 'OK' ? 'dbt-status-ok' : (json.status === 'ERROR' ? 'dbt-status-err' : 'dbt-status-warn');
      statusEl.innerHTML = '<span class="' + cls + '">' + esc(json.status) + '</span>' +
        (json.lag_seconds !== undefined ? '<span class="ms-2 text-muted small">Lag: ' + json.lag_seconds + 's</span>' : '') +
        (json.error ? '<div class="text-danger small mt-1">' + esc(json.error) + '</div>' : '');
    } catch(e) {
      showAlert('danger', 'Gagal cek replication: ' + esc(e.message));
    } finally { setBtn('btn-check-repl', false); }
  });

  // Failover — buka modal
  document.getElementById('btn-failover').addEventListener('click', function() {
    document.getElementById('failover-agree').checked = false;
    document.getElementById('btn-failover-confirm').disabled = true;
    new bootstrap.Modal(document.getElementById('failoverModal')).show();
  });
  document.getElementById('failover-agree').addEventListener('change', function() {
    document.getElementById('btn-failover-confirm').disabled = !this.checked;
  });
  document.getElementById('btn-failover-confirm').addEventListener('click', async function() {
    bootstrap.Modal.getInstance(document.getElementById('failoverModal')).hide();
    setBtn('btn-failover', true);
    setOutput('output-failover', 'Menjalankan failover...', true);
    try {
      const json = await apiPost('dbtools/action/failover', { confirm: 'YES_FAILOVER' });
      setOutput('output-failover', json.message, true);
      showAlert('success', 'Failover berhasil. Muat ulang halaman.');
      document.getElementById('btn-failover').disabled = true;
      document.getElementById('btn-restart-repl').disabled = false;
    } catch(e) {
      setOutput('output-failover', e.message, true);
      showAlert('danger', 'Failover gagal: ' + esc(e.message));
    } finally { setBtn('btn-failover', false); }
  });

  // Restart replication
  document.getElementById('btn-restart-repl').addEventListener('click', async function() {
    setBtn('btn-restart-repl', true);
    setOutput('output-restart', 'Menghubungkan ke master...', true);
    try {
      const payload = {
        master_host: document.getElementById('cfg_repl_master_host')?.value || '',
        master_port: document.getElementById('cfg_repl_master_port')?.value || '3306',
        repl_user:   document.getElementById('cfg_repl_repl_user')?.value || '',
        repl_pass:   document.getElementById('cfg_repl_repl_pass')?.value || '',
      };
      const json = await apiPost('dbtools/action/restart-replication', payload);
      setOutput('output-restart', json.message + '\nBinlog: ' + (json.log_file||'-') + ' @ ' + (json.log_pos||0), true);
      showAlert('success', 'Replication di-restart! Server kembali jadi Slave.');
      document.getElementById('btn-restart-repl').disabled = true;
      document.getElementById('btn-failover').disabled = false;
    } catch(e) {
      setOutput('output-restart', e.message, true);
      showAlert('danger', 'Restart gagal: ' + esc(e.message));
    } finally { setBtn('btn-restart-repl', false); }
  });
})();
</script>
