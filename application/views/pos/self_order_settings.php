<?php
$settings = is_array($settings ?? null) ? $settings : [];
$qrisPaymentMethodOptions = is_array($qris_payment_method_options ?? null) ? $qris_payment_method_options : [];
?>

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'self-order']); ?>
  <?php $this->load->view('pos/_self_order_tabs', ['self_order_tab_active' => 'settings']); ?>

  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Settings Self Order</h4>
      <p class="fin-page-subtitle mb-0">Aktivasi jalur order mandiri, arahkan QR ke aplikasi member, dan simpan kredensial Midtrans QRIS.</p>
    </div>
  </div>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h5 class="mb-3">Aktivasi & Arah QR</h5>
          <div class="form-check form-switch border rounded-3 px-3 py-2 mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="is_enabled" name="is_enabled" value="1" <?php echo !empty($settings['is_enabled']) ? 'checked' : ''; ?>>
            <label class="form-check-label ms-2" for="is_enabled">Aktifkan order mandiri via scan QR meja</label>
          </div>

          <div class="mb-3">
            <label class="form-label small text-muted mb-1">Base URL aplikasi member</label>
            <input type="url" class="form-control" name="member_base_url" value="<?php echo html_escape((string)($settings['member_base_url'] ?? '')); ?>" placeholder="https://member.domain.com/">
            <div class="small text-muted mt-2">QR meja akan diarahkan ke URL ini. Gunakan URL member yang benar untuk desktop maupun mobile browser.</div>
          </div>

          <div class="mb-0">
            <label class="form-label small text-muted mb-1">Contoh hasil QR</label>
            <div class="form-control bg-light"><?php echo html_escape((string)($settings['qr_scan_example'] ?? '-')); ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0">Keamanan QR Meja</h5>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-generate-secret">Generate Secret Baru</button>
          </div>

          <div class="mb-3">
            <label class="form-label small text-muted mb-1">Secret signature QR</label>
            <input type="text" class="form-control" name="table_qr_secret" id="table_qr_secret" value="<?php echo html_escape((string)($settings['table_qr_secret'] ?? '')); ?>" placeholder="Biarkan kosong untuk generate otomatis saat simpan">
            <div class="small text-muted mt-2">Signature dipakai untuk mencegah QR meja dipalsukan. Jika secret diubah, semua QR meja lama harus dicetak ulang.</div>
          </div>

          <div class="form-check form-switch border rounded-3 px-3 py-2">
            <input class="form-check-input" type="checkbox" role="switch" id="table_qr_enforce" name="table_qr_enforce" value="1" <?php echo !empty($settings['table_qr_enforce']) ? 'checked' : ''; ?>>
            <label class="form-check-label ms-2" for="table_qr_enforce">Wajibkan signature di setiap QR meja</label>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h5 class="mb-3">Midtrans QRIS</h5>
          <div class="form-check form-switch border rounded-3 px-3 py-2 mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="midtrans_is_enabled" name="midtrans_is_enabled" value="1" <?php echo !empty($settings['midtrans_is_enabled']) ? 'checked' : ''; ?>>
            <label class="form-check-label ms-2" for="midtrans_is_enabled">Aktifkan pembayaran QRIS Midtrans untuk self order</label>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">Metode Pembayaran Self Order</label>
              <select class="form-select" name="qris_payment_method_id">
                <option value="">Pilih metode pembayaran...</option>
                <?php foreach ($qrisPaymentMethodOptions as $method): ?>
                  <?php
                    $accountPieces = array_filter([
                      (string)($method['account_name'] ?? ''),
                      (string)($method['bank_name'] ?? ''),
                      (string)($method['account_no'] ?? ''),
                    ], static function ($value) {
                      return trim((string)$value) !== '';
                    });
                    $optionLabel = (string)($method['method_name'] ?? '-');
                    if (!empty($accountPieces)) {
                      $optionLabel .= ' | ' . implode(' | ', $accountPieces);
                    }
                  ?>
                  <option value="<?php echo (int)($method['id'] ?? 0); ?>" <?php echo (int)($settings['qris_payment_method_id'] ?? 0) === (int)($method['id'] ?? 0) ? 'selected' : ''; ?>>
                    <?php echo html_escape($optionLabel); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="small text-muted mt-2">Pilih metode pembayaran aktif yang sudah terhubung ke rekening perusahaan. Di member tetap tampil sebagai QRIS, tetapi payment line POS akan memakai metode ini.</div>
              <?php if (empty($qrisPaymentMethodOptions)): ?>
                <div class="small text-danger mt-2">Belum ada payment method aktif yang terhubung ke rekening. Siapkan dulu di menu Payment Method POS.</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">Server Key</label>
              <textarea class="form-control" name="midtrans_server_key" rows="3" placeholder="SB-Mid-server-..."><?php echo html_escape((string)($settings['midtrans_server_key'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">Client Key</label>
              <textarea class="form-control" name="midtrans_client_key" rows="3" placeholder="SB-Mid-client-..."><?php echo html_escape((string)($settings['midtrans_client_key'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch border rounded-3 px-3 py-2">
                <input class="form-check-input" type="checkbox" role="switch" id="midtrans_is_production" name="midtrans_is_production" value="1" <?php echo !empty($settings['midtrans_is_production']) ? 'checked' : ''; ?>>
                <label class="form-check-label ms-2" for="midtrans_is_production">Gunakan environment production Midtrans</label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">Catatan internal</label>
              <input type="text" class="form-control" name="qris_notes" value="<?php echo html_escape((string)($settings['qris_notes'] ?? '')); ?>" placeholder="Opsional">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
      <a href="<?php echo site_url('pos/self-order/tables'); ?>" class="btn btn-light">Kelola QR Meja</a>
      <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const secretInput = document.getElementById('table_qr_secret');
  const btn = document.getElementById('btn-generate-secret');
  if (!btn || !secretInput) return;
  btn.addEventListener('click', function () {
    const bytes = new Uint8Array(24);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(bytes);
      secretInput.value = Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
      return;
    }
    secretInput.value = 'so_' + Math.random().toString(16).slice(2) + Date.now().toString(16);
  });
});
</script>
