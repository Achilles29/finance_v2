<?php
$user     = $user ?? [];
$username = html_escape((string)($user['username'] ?? ''));
$email    = html_escape((string)($user['email'] ?? ''));
$lastLogin = trim((string)($user['last_login_at'] ?? ''));
?>
<style>
  .settings-wrap { max-width: 560px; }
  .settings-wrap .card { border-radius: 12px; border-color: #e8ddd8; }
  .settings-wrap .card-header { background: #faf7f5; border-bottom-color: #e8ddd8; font-weight: 600; padding: .85rem 1.1rem; }
  .settings-info-row { display: flex; flex-direction: column; gap: .1rem; padding: .5rem 0; border-bottom: 1px solid #f1ebe7; }
  .settings-info-row:last-child { border-bottom: 0; padding-bottom: 0; }
  .settings-info-label { font-size: .78rem; color: #9b8b84; text-transform: uppercase; letter-spacing: .04em; }
  .settings-info-value { font-weight: 600; color: #3a2a26; }
</style>

<div class="settings-wrap">
  <div class="mb-4">
    <h5 class="fw-bold mb-1">Pengaturan Akun</h5>
    <div class="text-muted" style="font-size:.875rem;">Kelola informasi akun dan keamanan login Anda.</div>
  </div>

  <!-- Informasi Akun -->
  <div class="card mb-4">
    <div class="card-header">Informasi Akun</div>
    <div class="card-body">
      <div class="settings-info-row">
        <span class="settings-info-label">Username</span>
        <span class="settings-info-value"><?= $username ?: '-'; ?></span>
      </div>
      <div class="settings-info-row">
        <span class="settings-info-label">Email</span>
        <span class="settings-info-value"><?= $email ?: '-'; ?></span>
      </div>
      <?php if ($lastLogin !== ''): ?>
      <div class="settings-info-row">
        <span class="settings-info-label">Login Terakhir</span>
        <span class="settings-info-value"><?= html_escape($lastLogin); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($can_manage_roast_connect)): ?>
  <div class="card mb-4"><div class="card-header">Integrasi aplikasi</div><div class="card-body"><h6>NAMUA × FINANCE</h6><p class="text-muted small">Buat token koneksi dan atur akses katalog stok untuk Roast Studio, termasuk ketika berjalan di server berbeda.</p><a class="btn btn-outline-primary" href="<?= site_url('system/roast-connect') ?>">Integrasi Roast Studio</a></div></div>
  <?php endif; ?>

  <!-- Ganti Password -->
  <div class="card">
    <div class="card-header">Ganti Password</div>
    <div class="card-body">
      <div id="cp-alert" class="alert mb-3" style="display:none;"></div>

      <div class="mb-3">
        <label class="form-label">Password Saat Ini</label>
        <input type="password" id="cp-current" class="form-control" placeholder="Masukkan password saat ini" autocomplete="current-password">
      </div>
      <div class="mb-3">
        <label class="form-label">Password Baru</label>
        <input type="password" id="cp-new" class="form-control" placeholder="Minimal 6 karakter" autocomplete="new-password">
      </div>
      <div class="mb-4">
        <label class="form-label">Konfirmasi Password Baru</label>
        <input type="password" id="cp-confirm" class="form-control" placeholder="Ulangi password baru" autocomplete="new-password">
      </div>

      <button type="button" id="btn-cp-save" class="btn btn-primary px-4">Simpan Password</button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var CHANGE_URL = '<?= site_url('settings/change-password'); ?>';
  var alertEl    = document.getElementById('cp-alert');
  var btnSave    = document.getElementById('btn-cp-save');
  var origLabel  = btnSave.textContent;

  function showAlert(type, msg) {
    alertEl.className = 'alert alert-' + type + ' mb-3';
    alertEl.textContent = msg;
    alertEl.style.display = '';
    alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearAlert() {
    alertEl.style.display = 'none';
  }

  btnSave.addEventListener('click', function () {
    var current = document.getElementById('cp-current').value;
    var newPass  = document.getElementById('cp-new').value;
    var confirm  = document.getElementById('cp-confirm').value;

    clearAlert();

    if (!current || !newPass || !confirm) {
      showAlert('warning', 'Semua field wajib diisi.');
      return;
    }
    if (newPass.length < 6) {
      showAlert('warning', 'Password baru minimal 6 karakter.');
      return;
    }
    if (newPass !== confirm) {
      showAlert('warning', 'Konfirmasi password tidak cocok.');
      return;
    }

    btnSave.disabled = true;
    btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';

    var form = new FormData();
    form.append('current_password', current);
    form.append('new_password', newPass);
    form.append('confirm_password', confirm);

    fetch(CHANGE_URL, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: form
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.ok) {
        showAlert('success', res.message || 'Password berhasil diubah.');
        document.getElementById('cp-current').value = '';
        document.getElementById('cp-new').value = '';
        document.getElementById('cp-confirm').value = '';
      } else {
        showAlert('danger', res.message || 'Gagal mengubah password.');
      }
    })
    .catch(function () {
      showAlert('danger', 'Terjadi kesalahan jaringan. Silakan coba lagi.');
    })
    .finally(function () {
      btnSave.disabled = false;
      btnSave.textContent = origLabel;
    });
  });

  // Submit on Enter from any password field
  ['cp-current', 'cp-new', 'cp-confirm'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') btnSave.click();
    });
  });
})();
</script>
