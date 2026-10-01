<?php
$settings = (array)($settings ?? []);
$canEdit = (bool)($can_edit ?? false);
$settingsMutationCsrf = (string)($wa_settings_mutation_csrf ?? '');
?>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-settings-3-line me-1"></i>Pengaturan WA Bot</h4>
      <p class="text-muted mb-0 small">Atur penerima notifikasi, sambungkan WhatsApp, dan periksa bot melalui tab berikut.</p>
    </div>
    <a href="<?= site_url('wa/guide') ?>" class="btn btn-outline-info btn-sm">
      <i class="ri ri-book-open-line me-1"></i>Panduan Instalasi & Penggunaan
    </a>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <ul class="nav nav-pills flex-wrap gap-2 mb-3" id="wa-settings-tabs" role="tablist" aria-label="Bagian pengaturan WhatsApp">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="wa-tab-notifications" data-bs-toggle="pill" data-bs-target="#wa-notifications" type="button" role="tab" aria-controls="wa-notifications" aria-selected="true"><i class="ri ri-notification-3-line me-1" aria-hidden="true"></i>Notifikasi</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="wa-tab-connection" data-bs-toggle="pill" data-bs-target="#wa-connection" type="button" role="tab" aria-controls="wa-connection" aria-selected="false"><i class="ri ri-qr-code-line me-1" aria-hidden="true"></i>Koneksi & QR</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="wa-tab-testing" data-bs-toggle="pill" data-bs-target="#wa-testing" type="button" role="tab" aria-controls="wa-testing" aria-selected="false"><i class="ri ri-wifi-line me-1" aria-hidden="true"></i>Pengujian</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="wa-tab-technical" data-bs-toggle="pill" data-bs-target="#wa-technical" type="button" role="tab" aria-controls="wa-technical" aria-selected="false"><i class="ri ri-tools-line me-1" aria-hidden="true"></i>Teknis & pemulihan</button></li>
  </ul>
  <div class="tab-content p-0 bg-transparent shadow-none">
    <div class="tab-pane show active" id="wa-notifications" role="tabpanel" aria-labelledby="wa-tab-notifications" tabindex="0">
  <?php $this->load->view('notifications/settings', [
      'notification_channel' => 'WA', 'notification_can_edit' => $canEdit,
      'notification_csrf_name' => 'wa_settings_mutation_csrf', 'notification_csrf' => $settingsMutationCsrf,
      'notification_action' => 'wa/notification-settings',
  ]); ?>
    </div>
    <div class="tab-pane" id="wa-connection" role="tabpanel" aria-labelledby="wa-tab-connection" tabindex="0">
      <div class="row g-3">
        <div class="col-12 col-lg-7">
      <!-- QR Code Panel -->
      <div class="card border-0 shadow-sm mb-3" id="qr-panel">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Scan QR Code</h5>
          <button class="btn btn-sm btn-outline-success" id="btn-load-qr">
            <i class="ri ri-qr-code-line me-1"></i>Muat QR Code
          </button>
        </div>
        <div class="card-body text-center">
          <div id="qr-status-msg" class="text-muted small mb-2">
            Klik "Muat QR Code" untuk menampilkan kode scan.
            QR Code akan muncul jika status bot adalah <strong>Menunggu QR</strong>.
          </div>
          <div id="qr-container" class="d-flex justify-content-center mb-2"></div>
          <div id="qr-countdown" class="text-muted small d-none">
            <i class="ri ri-time-line me-1"></i>QR kadaluarsa dalam <span id="qr-seconds">60</span> detik. Refresh otomatis…
          </div>
          <div id="qr-connected" class="d-none">
            <i class="ri ri-checkbox-circle-line text-success me-1" style="font-size:2rem;"></i>
            <div class="text-success fw-semibold">WhatsApp Terhubung!</div>
            <div class="text-muted small" id="qr-phone"></div>
          </div>
        </div>
      </div>
      <!-- Koneksi Bot -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><h5 class="mb-0">Koneksi Bot</h5></div>
        <form method="post" action="<?= site_url('wa/settings') ?>">
          <?php if ($canEdit): ?>
          <input type="hidden" name="wa_settings_mutation_csrf" value="<?= html_escape($settingsMutationCsrf) ?>">
          <?php endif; ?>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">URL Internal Bot</label>
              <input type="url" name="bot_api_url" class="form-control font-monospace"
                value="<?= html_escape($settings['bot_api_url'] ?? 'http://127.0.0.1:3070') ?>"
                <?= !$canEdit ? 'readonly' : '' ?>
                placeholder="http://127.0.0.1:3070">
              <div class="form-text">
                URL base internal bot (port 3070 secara default).
                Finance app akan menghubungi bot via URL ini.
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Path Node.js <span class="badge bg-warning text-dark">Penting</span></label>
              <input type="text" name="node_path" class="form-control font-monospace"
                value="<?= html_escape($settings['node_path'] ?? '') ?>"
                <?= !$canEdit ? 'readonly' : '' ?>
                placeholder="Contoh: /home/ubuntu/.nvm/versions/node/v20.x.x/bin/node">
              <div class="form-text">
                Isi jika Node.js tidak terdeteksi otomatis (NVM per-user).
                Cari path dengan: <code>which node</code> atau <code>find /home -name node 2>/dev/null | head -5</code>
              </div>
            </div>
          </div>
          <?php if ($canEdit): ?>
          <div class="card-footer">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="ri ri-save-line me-1"></i>Simpan Pengaturan
            </button>
          </div>
          <?php endif; ?>
        </form>
      </div>
        </div>
        <div class="col-12 col-lg-5">
      <!-- Status Bot WA -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><h5 class="mb-0">Status WA Bot</h5></div>
        <div class="card-body">
          <?php
          $st = strtoupper($session['status'] ?? 'UNKNOWN');
          $badge = match($st) { 'CONNECTED' => 'bg-success', 'WAITING_QR' => 'bg-warning', 'DISCONNECTED', 'LOGGED_OUT' => 'bg-danger', default => 'bg-secondary' };
          $label = match($st) { 'CONNECTED' => 'Terhubung', 'WAITING_QR' => 'Menunggu QR', 'DISCONNECTED' => 'Terputus', 'LOGGED_OUT' => 'Sesi Keluar', default => 'Tidak Diketahui' };
          ?>
          <dl class="row mb-0 small">
            <dt class="col-5">Status</dt>
            <dd class="col-7"><span class="badge <?= $badge ?>"><?= $label ?></span></dd>
            <dt class="col-5">Nomor Terhubung</dt>
            <dd class="col-7"><?= html_escape($session['phone_number'] ?? '-') ?></dd>
            <dt class="col-5">Ping Terakhir</dt>
            <dd class="col-7"><?= html_escape($session['last_ping_at'] ?? '-') ?></dd>
          </dl>
        </div>
      </div>
        </div>
      </div>
    </div>
    <div class="tab-pane" id="wa-testing" role="tabpanel" aria-labelledby="wa-tab-testing" tabindex="0">
      <!-- Test Koneksi -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><h5 class="mb-0">Test Koneksi & Kirim Pesan</h5></div>
        <div class="card-body">
          <div class="d-flex gap-2 mb-3">
            <button class="btn btn-outline-primary btn-sm" id="btn-ping">
              <i class="ri ri-wifi-line me-1"></i>Ping Bot
            </button>
            <div id="ping-result" class="align-self-center small text-muted"></div>
          </div>
          <?php if ($canEdit): ?>
          <hr>
          <div class="mb-2">
            <label class="form-label fw-semibold">Kirim Pesan Test</label>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-5">
              <input type="text" id="test-phone" class="form-control form-control-sm font-monospace" placeholder="6281234567890">
            </div>
            <div class="col-md-7">
              <textarea id="test-message" class="form-control form-control-sm" rows="2" placeholder="Halo! Ini pesan test dari Finance App."></textarea>
            </div>
          </div>
          <button class="btn btn-success btn-sm" id="btn-send-test">
            <i class="ri ri-send-plane-line me-1"></i>Kirim
          </button>
          <div id="test-result" class="mt-2 small"></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="tab-pane" id="wa-technical" role="tabpanel" aria-labelledby="wa-tab-technical" tabindex="0">
      <div class="row g-3">
        <div class="col-12 col-lg-7">
      <!-- Konfigurasi .env Node.js -->
      <?php if ($canEdit): ?>
      <div class="card border-0 shadow-sm mb-3" id="env-card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ri ri-file-settings-line me-1"></i>Konfigurasi Node.js (.env)</h5>
          <button class="btn btn-sm btn-outline-secondary" id="btn-env-load">
            <i class="ri ri-eye-line me-1"></i>Muat
          </button>
        </div>
        <div class="card-body" id="env-body" style="display:none;">
          <div class="alert alert-warning small py-2 mb-3">
            <i class="ri ri-error-warning-line me-1"></i>
            Nilai dari file tidak ditampilkan. Isi hanya field yang ingin diubah; field secret kosong akan mempertahankan nilai lama. Restart wa-engine setelah simpan.
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-8">
              <label class="form-label small fw-semibold mb-1">DB_HOST</label>
              <input type="text" id="env-db-host" data-env-key="DB_HOST" class="form-control form-control-sm font-monospace" value="" placeholder="Nilai baru (contoh: 127.0.0.1)">
              <div class="form-text" id="env-db-host-status">Status belum dimuat.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold mb-1">DB_NAME</label>
              <input type="text" id="env-db-name" data-env-key="DB_NAME" class="form-control form-control-sm font-monospace" value="" placeholder="Nilai baru">
              <div class="form-text" id="env-db-name-status">Status belum dimuat.</div>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">DB_USER</label>
              <input type="text" id="env-db-user" data-env-key="DB_USER" class="form-control form-control-sm font-monospace" value="" placeholder="Nilai baru">
              <div class="form-text" id="env-db-user-status">Status belum dimuat.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">DB_PASS</label>
              <input type="password" id="env-db-pass" data-env-key="DB_PASS" data-env-secret="1" class="form-control form-control-sm font-monospace" value="" autocomplete="new-password" placeholder="Kosong = pertahankan">
              <div class="form-text" id="env-db-pass-status">Status belum dimuat.</div>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-12">
              <label class="form-label small fw-semibold mb-1">WA_PORT</label>
              <input type="text" id="env-wa-port" data-env-key="WA_PORT" class="form-control form-control-sm font-monospace" value="" placeholder="Nilai baru (contoh: 3070)">
              <div class="form-text" id="env-wa-port-status">Status belum dimuat.</div>
            </div>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-primary btn-sm" id="btn-env-save">
              <i class="ri ri-save-line me-1"></i>Simpan .env
            </button>
            <span id="env-save-result" class="small"></span>
          </div>
        </div>
      </div>
      <?php endif; ?>
        </div>
        <div class="col-12 col-lg-5">
      <!-- Engine Process Control -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ri ri-node-tree me-1"></i>wa-engine Process</h5>
          <button class="btn btn-outline-secondary btn-sm" id="btn-engine-refresh" title="Refresh status">
            <i class="ri ri-refresh-line"></i>
          </button>
        </div>
        <div class="card-body">
          <!-- Status proses -->
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge bg-secondary fs-6" id="engine-proc-badge">Mengecek…</span>
            <span class="text-muted small" id="engine-proc-info"></span>
          </div>

          <!-- Tombol kontrol -->
          <?php if ($canEdit): ?>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <button class="btn btn-success btn-sm" id="btn-engine-start" disabled>
              <i class="ri ri-play-line me-1"></i>Start
            </button>
            <button class="btn btn-danger btn-sm" id="btn-engine-stop" disabled>
              <i class="ri ri-stop-line me-1"></i>Stop
            </button>
            <button class="btn btn-warning btn-sm" id="btn-engine-restart" disabled>
              <i class="ri ri-restart-line me-1"></i>Restart
            </button>
          </div>
          <?php endif; ?>

          <div id="engine-action-msg" class="small mb-2"></div>

          <?php if ($canEdit): ?>
          <!-- Log diagnostic status (edit-only) -->
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold text-muted">Status Log Diagnostik</span>
            <button class="btn btn-link btn-sm p-0 text-muted" id="btn-engine-log">
              <i class="ri ri-file-list-line"></i> Cek Status
            </button>
          </div>
          <pre id="engine-log-output" class="bg-dark text-white rounded p-2 small mb-0"
            style="max-height:200px;overflow-y:auto;font-size:0.72rem;display:none;">(klik Cek Status)</pre>
          <?php endif; ?>
        </div>
      </div>
      <!-- Reset Sesi WA -->
      <?php if ($canEdit): ?>
      <div class="card border-0 shadow-sm mb-3 border-danger border-opacity-25">
        <div class="card-header bg-danger bg-opacity-10">
          <h5 class="mb-0 text-danger"><i class="ri ri-refresh-line me-1"></i>Reset Sesi WA</h5>
        </div>
        <div class="card-body small">
          <p class="mb-2 text-muted">
            Hapus sesi tersimpan agar bot meminta QR baru saat restart.
            Gunakan jika bot tidak bisa reconnect, QR tidak muncul, atau penerima melihat pesan “menunggu pesan ini”.
          </p>
          <button class="btn btn-outline-danger btn-sm" id="btn-session-reset">
            <i class="ri ri-delete-bin-line me-1"></i>Hapus Sesi & Paksa QR Baru
          </button>
          <div id="session-reset-result" class="mt-2"></div>
        </div>
      </div>
      <?php endif; ?>
        </div>
      </div>
      <!-- Panduan singkat -->
      <div class="card border-0 shadow-sm">
        <div class="card-header"><h5 class="mb-0">Langkah Setup</h5></div>
        <div class="card-body small">
          <ol class="mb-0">
            <li class="mb-2">Pastikan <code>finance/wa-engine/.env</code> sudah dikonfigurasi (DB_PASS, dll).</li>
            <li class="mb-2">Pastikan credential API internal sudah di-inject ke environment proses PHP/FPM dan wa-engine.</li>
            <li class="mb-2">Klik <strong>Start</strong> pada panel proses di tab ini untuk menjalankan wa-engine.</li>
            <li class="mb-2">Klik <strong>Muat QR Code</strong> pada tab Koneksi &amp; QR → scan dengan WA.</li>
            <li class="mb-2">Status WA Bot berubah ke <span class="badge bg-success">Terhubung</span>.</li>
            <li>Buka tab Pengujian, lalu klik <strong>Ping Bot</strong> untuk memverifikasi koneksi.</li>
          </ol>
          <div class="mt-2">
            <a href="<?= site_url('wa/guide') ?>" class="btn btn-outline-info btn-sm w-100">
              <i class="ri ri-book-open-line me-1"></i>Panduan Lengkap
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- QR Code library (qrcodejs) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Retain the selected section after saving; only known pane IDs may be restored.
document.addEventListener('DOMContentLoaded', function () {
  const tabs = Array.from(document.querySelectorAll('#wa-settings-tabs [data-bs-toggle="pill"]'));
  const storageKey = 'finance-wa-settings-tab:' + window.location.pathname;
  let saved = '';
  try { saved = window.sessionStorage.getItem(storageKey) || ''; } catch (_) {}
  const hash = window.location.hash === '#module-notifications' ? '#wa-notifications' : window.location.hash;
  const selected = tabs.find(tab => tab.dataset.bsTarget === hash) || tabs.find(tab => tab.dataset.bsTarget === saved);
  tabs.forEach(tab => tab.addEventListener('shown.bs.tab', function () {
    try { window.sessionStorage.setItem(storageKey, tab.dataset.bsTarget); } catch (_) {}
    // Replace rather than add history entries; avoids old hashes overriding a saved tab.
    try { window.history.replaceState(null, '', tab.dataset.bsTarget); } catch (_) {}
  }));
  if (selected && window.bootstrap && window.bootstrap.Tab) {
    window.bootstrap.Tab.getOrCreateInstance(selected).show();
  }
});

// ─── QR Code ───────────────────────────────────────────────
let qrInterval = null;
let qrCountdown = 60;
let qrCountdownTimer = null;
let currentQrInstance = null;

function waSafeJsonResponse(response) {
  return response.text().then(text => {
    try {
      return JSON.parse(text);
    } catch (err) {
      const clean = String(text || '')
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
      return {
        ok: false,
        message: clean ? clean.slice(0, 700) : String(err),
        raw_response: text
      };
    }
  });
}

function clearQrTimers() {
  if (qrInterval) { clearInterval(qrInterval); qrInterval = null; }
  if (qrCountdownTimer) { clearInterval(qrCountdownTimer); qrCountdownTimer = null; }
}

function renderQr(qrString) {
  const container = document.getElementById('qr-container');
  container.innerHTML = '';
  currentQrInstance = new QRCode(container, {
    text: qrString,
    width: 220,
    height: 220,
    correctLevel: QRCode.CorrectLevel.M
  });
}

function showConnected(phone) {
  clearQrTimers();
  document.getElementById('qr-container').innerHTML = '';
  document.getElementById('qr-countdown').classList.add('d-none');
  document.getElementById('qr-status-msg').classList.add('d-none');
  const el = document.getElementById('qr-connected');
  el.classList.remove('d-none');
  if (phone) document.getElementById('qr-phone').textContent = '📱 ' + phone;
}

function startCountdown() {
  qrCountdown = 60;
  document.getElementById('qr-countdown').classList.remove('d-none');
  document.getElementById('qr-seconds').textContent = qrCountdown;
  qrCountdownTimer = setInterval(() => {
    qrCountdown--;
    document.getElementById('qr-seconds').textContent = qrCountdown;
    if (qrCountdown <= 0) {
      clearInterval(qrCountdownTimer);
      document.getElementById('qr-countdown').classList.add('d-none');
    }
  }, 1000);
}

function fetchQr() {
  fetch('<?= site_url('wa/api/qr') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(waSafeJsonResponse)
    .then(data => {
      if (data.ok === false) {
        document.getElementById('qr-status-msg').textContent = data.message || 'Gagal membaca respons QR.';
        clearQrTimers();
        return;
      }
      const status = (data.status || 'UNKNOWN').toUpperCase();
      if (status === 'CONNECTED') {
        showConnected(data.phone || '');
        return;
      }
      if (data.qr) {
        document.getElementById('qr-status-msg').textContent = 'Scan QR Code baru ini dengan WhatsApp di HP Anda.';
        clearInterval(qrCountdownTimer);
        startCountdown();
        renderQr(data.qr);
      } else if (!data.qr) {
        document.getElementById('qr-status-msg').textContent = 'Bot belum dalam mode QR. Pastikan wa-engine berjalan dan belum terhubung.';
      }
    })
    .catch(() => {
      document.getElementById('qr-status-msg').textContent = 'Tidak dapat menghubungi WA Bot. Pastikan wa-engine berjalan.';
    });
}

document.getElementById('btn-load-qr')?.addEventListener('click', function () {
  document.getElementById('qr-connected').classList.add('d-none');
  document.getElementById('qr-status-msg').classList.remove('d-none');
  document.getElementById('qr-status-msg').textContent = 'Memuat QR Code…';
  clearQrTimers();
  fetchQr();
  // Poll setiap 5 detik utk cek update QR atau status connected
  qrInterval = setInterval(fetchQr, 5000);
});

// ─── Ping ──────────────────────────────────────────────────
document.getElementById('btn-ping')?.addEventListener('click', function () {
  const result = document.getElementById('ping-result');
  this.disabled = true;
  result.textContent = 'Menghubungi bot…';
  fetch('<?= site_url('wa/api/status') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(waSafeJsonResponse)
    .then(d => {
      if (d.ok !== false) {
        result.innerHTML = '<span class="text-success">✓ Bot aktif — Status: <strong>' + (d.status || '?') + '</strong>' + (d.phone ? ' | 📱 ' + d.phone : '') + '</span>';
      } else {
        result.innerHTML = '<span class="text-danger">✗ ' + (d.message || 'Gagal terhubung') + '</span>';
      }
    })
    .catch(e => { result.innerHTML = '<span class="text-danger">✗ ' + e + '</span>'; })
    .finally(() => { this.disabled = false; });
});

<?php if ($canEdit): ?>
const waSendTestCsrfToken = <?= json_encode(
  (string)($wa_send_test_csrf_token ?? ''),
  JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

document.getElementById('btn-send-test')?.addEventListener('click', function () {
  const phone = document.getElementById('test-phone').value.trim();
  const msg   = document.getElementById('test-message').value.trim();
  const result = document.getElementById('test-result');
  if (!phone || !msg) { result.innerHTML = '<span class="text-danger">Isi nomor dan pesan.</span>'; return; }
  this.disabled = true;
  result.textContent = 'Mengirim…';
  fetch('<?= site_url('wa/api/send-test') ?>', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Wa-Send-Test-CSRF': waSendTestCsrfToken
    },
    body: JSON.stringify({ to: phone, message: msg })
  })
    .then(waSafeJsonResponse)
    .then(d => {
      result.innerHTML = d.ok
        ? '<span class="text-success">✓ Pesan terkirim!</span>'
        : '<span class="text-danger">✗ ' + (d.message || 'Gagal') + '</span>';
    })
    .catch(e => { result.innerHTML = '<span class="text-danger">✗ ' + e + '</span>'; })
    .finally(() => { this.disabled = false; });
});
<?php endif; ?>

// ─── Engine Process Control ────────────────────────────────
<?php if ($canEdit): ?>
const waEngineControlCsrfToken = <?= json_encode(
  (string)($wa_engine_control_csrf_token ?? ''),
  JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
const waEnvSaveCsrfToken = <?= json_encode(
  (string)($wa_env_save_csrf_token ?? ''),
  JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

function waEngineControlHeaders() {
  return {
    'X-Requested-With': 'XMLHttpRequest',
    'X-Wa-Engine-Control-CSRF': waEngineControlCsrfToken
  };
}
<?php endif; ?>

function engineSetBtns(running) {
  const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  if (!canEdit) return;
  document.getElementById('btn-engine-start')?.toggleAttribute('disabled', running);
  document.getElementById('btn-engine-stop')?.toggleAttribute('disabled', !running);
  document.getElementById('btn-engine-restart')?.toggleAttribute('disabled', !running);
}

function engineRefreshStatus(silent = false) {
  if (!silent) {
    const badge = document.getElementById('engine-proc-badge');
    if (badge) { badge.className = 'badge bg-secondary fs-6'; badge.textContent = 'Mengecek…'; }
  }
  fetch('<?= site_url('wa/api/engine-status') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(waSafeJsonResponse)
    .then(d => {
      const badge = document.getElementById('engine-proc-badge');
      const info  = document.getElementById('engine-proc-info');
      if (!badge) return;
      if (!d.ok) {
        badge.className = 'badge bg-warning text-dark fs-6';
        badge.textContent = 'Tidak dapat dicek';
        if (info) info.textContent = d.message || '';
        engineSetBtns(false);
        return;
      }
      if (d.running) {
        badge.className = 'badge bg-success fs-6';
        badge.textContent = 'Berjalan';
        if (info) info.textContent = 'PID: ' + (d.pids || []).join(', ') + ' · Port ' + d.port;
      } else {
        badge.className = 'badge bg-danger fs-6';
        badge.textContent = 'Tidak Berjalan';
        if (info) info.textContent = 'Port ' + d.port + ' kosong';
      }
      engineSetBtns(d.running);
    })
    .catch(() => {
      const badge = document.getElementById('engine-proc-badge');
      if (badge) { badge.className = 'badge bg-secondary fs-6'; badge.textContent = 'Error'; }
      engineSetBtns(false);
    });
}

let engineActionBusy = false;

function engineAction(action) {
  if (engineActionBusy) return;
  engineActionBusy = true;
  const msgEl = document.getElementById('engine-action-msg');
  if (msgEl) msgEl.innerHTML = '<span class="text-muted"><i class="ri ri-loader-4-line me-1"></i>' + (action === 'start' ? 'Memulai…' : 'Menghentikan…') + '</span>';

  const url = action === 'start'
    ? '<?= site_url('wa/api/engine-start') ?>'
    : '<?= site_url('wa/api/engine-stop') ?>';

  fetch(url, { method: 'POST', headers: waEngineControlHeaders() })
    .then(waSafeJsonResponse)
    .then(d => {
      if (msgEl) msgEl.innerHTML = d.ok
        ? '<span class="text-success"><i class="ri ri-checkbox-circle-line me-1"></i>' + (d.message || 'Berhasil') + '</span>'
        : '<span class="text-danger"><i class="ri ri-error-warning-line me-1"></i>' + (d.message || 'Gagal') + '</span>';
      setTimeout(() => engineRefreshStatus(true), 800);
    })
    .catch(e => {
      if (msgEl) msgEl.innerHTML = '<span class="text-danger">✗ ' + e + '</span>';
    })
    .finally(() => {
      engineActionBusy = false;
      setTimeout(() => engineRefreshStatus(true), 800);
    });
}

document.getElementById('btn-engine-start')?.addEventListener('click', () => engineAction('start'));
document.getElementById('btn-engine-stop')?.addEventListener('click',  () => engineAction('stop'));
document.getElementById('btn-engine-restart')?.addEventListener('click', function () {
  if (engineActionBusy) return;
  engineActionBusy = true;
  const msgEl = document.getElementById('engine-action-msg');
  if (msgEl) msgEl.innerHTML = '<span class="text-muted">Merestart…</span>';
  fetch('<?= site_url('wa/api/engine-stop') ?>', { method: 'POST', headers: waEngineControlHeaders() })
    .then(waSafeJsonResponse)
    .then(d => {
      if (!d.ok) throw new Error(d.message || 'Gagal menghentikan wa-engine.');
      setTimeout(() => {
        fetch('<?= site_url('wa/api/engine-start') ?>', { method: 'POST', headers: waEngineControlHeaders() })
          .then(waSafeJsonResponse)
          .then(d => {
            if (msgEl) msgEl.innerHTML = d.ok
              ? '<span class="text-success">✓ Restart berhasil — ' + (d.message || '') + '</span>'
              : '<span class="text-danger">✗ ' + (d.message || 'Gagal start ulang') + '</span>';
            setTimeout(() => engineRefreshStatus(true), 1000);
          })
          .catch(e => {
            if (msgEl) msgEl.innerHTML = '<span class="text-danger">✗ ' + e.message + '</span>';
          })
          .finally(() => { engineActionBusy = false; });
      }, 1500);
    })
    .catch(e => {
      if (msgEl) msgEl.innerHTML = '<span class="text-danger">✗ ' + e.message + '</span>';
      engineActionBusy = false;
      engineRefreshStatus(true);
    });
});

document.getElementById('btn-engine-refresh')?.addEventListener('click', () => engineRefreshStatus());

document.getElementById('btn-engine-log')?.addEventListener('click', function () {
  const pre = document.getElementById('engine-log-output');
  if (!pre) return;
  pre.style.display = 'block';
  pre.textContent = 'Memeriksa status log…';
  fetch('<?= site_url('wa/api/engine-logs') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(waSafeJsonResponse)
    .then(d => {
      pre.textContent = d.message || (d.ok === false ? 'Gagal memeriksa status log.' : 'Status log diperbarui.');
      pre.scrollTop = pre.scrollHeight;
    })
    .catch(() => { pre.textContent = 'Gagal memeriksa status log.'; });
});

// Auto-cek status engine saat halaman dibuka
engineRefreshStatus();

// ─── .env Editor ──────────────────────────────────────────
const waEnvInputs = Array.from(document.querySelectorAll('[data-env-key]'));
waEnvInputs.forEach(input => {
  input.dataset.touched = '0';
  input.addEventListener('input', () => { input.dataset.touched = '1'; });
});

function waApplyEnvStatus(statuses) {
  waEnvInputs.forEach(input => {
    const key = input.dataset.envKey;
    const status = statuses && statuses[key] ? statuses[key] : {};
    const statusEl = document.getElementById(input.id + '-status');
    input.value = '';
    input.dataset.touched = '0';
    if (statusEl) {
      statusEl.textContent = status.configured ? 'Sudah dikonfigurasi; nilai disembunyikan.' : 'Belum dikonfigurasi.';
      statusEl.classList.toggle('text-success', status.configured === true);
      statusEl.classList.toggle('text-warning', status.configured !== true);
    }
  });
}

document.getElementById('btn-env-load')?.addEventListener('click', function () {
  const body = document.getElementById('env-body');
  const result = document.getElementById('env-save-result');
  if (!body) return;
  if (body.style.display !== 'none') { body.style.display = 'none'; return; }
  body.style.display = 'block';
  fetch('<?= site_url('wa/api/env-read') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(waSafeJsonResponse)
    .then(d => {
      if (!d.ok) {
        if (result) result.textContent = d.message || 'Status konfigurasi tidak dapat dimuat.';
        return;
      }
      waApplyEnvStatus(d.env || {});
    })
    .catch(() => {
      if (result) result.textContent = 'Status konfigurasi tidak dapat dimuat.';
    });
});

document.getElementById('btn-env-save')?.addEventListener('click', function () {
  const result = document.getElementById('env-save-result');
  const payload = {};
  waEnvInputs.forEach(input => {
    if (input.dataset.envSecret === '1' || input.dataset.touched === '1') {
      payload[input.dataset.envKey] = input.value;
    }
  });
  result.textContent = 'Menyimpan…';
  fetch('<?= site_url('wa/api/env-save') ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Wa-Env-Save-CSRF': waEnvSaveCsrfToken
    },
    body: JSON.stringify(payload)
  })
    .then(waSafeJsonResponse)
    .then(d => {
      if (d.ok) {
        result.innerHTML = '<span class="text-success"><i class="ri ri-checkbox-circle-line me-1"></i>' + (d.message || 'Tersimpan') + '</span>';
        waApplyEnvStatus(d.env || {});
        return;
      }
      result.innerHTML = '<span class="text-danger">✗ ' + (d.message || 'Gagal') + '</span>';
    })
    .catch(() => { result.innerHTML = '<span class="text-danger">✗ Konfigurasi tidak dapat disimpan.</span>'; });
});

// ─── Reset Sesi WA ────────────────────────────────────────
document.getElementById('btn-session-reset')?.addEventListener('click', function () {
  if (!confirm('Yakin hapus sesi WA?\nEngine akan dihentikan, sesi lama dihapus, lalu bot akan meminta QR baru saat Start berikutnya.')) return;
  const result = document.getElementById('session-reset-result');
  this.disabled = true;
  result.textContent = 'Menghapus sesi…';
  fetch('<?= site_url('wa/api/session-reset') ?>', {
    method: 'POST',
    headers: waEngineControlHeaders()
  })
    .then(waSafeJsonResponse)
    .then(d => {
      result.innerHTML = d.ok
        ? '<span class="text-success"><i class="ri ri-checkbox-circle-line me-1"></i>' + (d.message || 'Berhasil') + '</span>'
        : '<span class="text-danger">✗ ' + (d.message || 'Gagal') + '</span>';
    })
    .catch(e => { result.innerHTML = '<span class="text-danger">✗ ' + e + '</span>'; })
    .finally(() => { this.disabled = false; });
});
</script>
