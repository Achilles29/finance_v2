<?php
$payload = is_array($payload ?? null) ? $payload : [];
?>
<style>
  .printer-settings-shell{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:1rem}
  .printer-settings-card{border:1px solid rgba(188,44,69,.12);border-radius:22px;background:linear-gradient(180deg,#fffdfb 0%,#fff 100%);box-shadow:0 14px 36px rgba(71,34,34,.08)}
  .printer-settings-card .card-body{padding:1.2rem}
  .printer-settings-kicker{display:inline-flex;align-items:center;gap:.45rem;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#8a786c}
  .printer-settings-note{border:1px solid rgba(188,44,69,.12);border-radius:16px;background:#fff8f5;padding:.8rem .9rem;color:#7a675e}
  .printer-settings-list{display:grid;gap:.7rem;margin-top:.9rem}
  .printer-settings-item{border:1px solid rgba(188,44,69,.12);border-radius:16px;background:#fff;padding:.8rem .9rem}
  .printer-settings-item strong{display:block;color:#442f36;margin-bottom:.15rem}
  @media (max-width: 1100px){.printer-settings-shell{grid-template-columns:1fr}}
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1"><?= html_escape($page_title ?? 'Pengaturan Umum Printer POS') ?></h4>
      <p class="fin-page-subtitle mb-0">Atur branding, Wi-Fi, dan default loyalty yang akan dipakai sebagai baseline global untuk template printer POS.</p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>
  <?php $this->load->view('pos/_printer_tabs', ['printer_tab_active' => 'settings']); ?>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?= html_escape($this->session->flashdata('success')) ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div>
  <?php endif; ?>

  <div class="printer-settings-shell">
    <form method="post" class="card printer-settings-card">
      <div class="card-body">
        <div class="printer-settings-kicker"><i class="ri-settings-4-line"></i> Global Printer Defaults</div>
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label">Nama Perusahaan / Judul</label>
            <input class="form-control" name="title" value="<?= html_escape($payload['title'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Subjudul / Alamat Ringkas</label>
            <input class="form-control" name="subtitle" value="<?= html_escape($payload['subtitle'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Logo URL</label>
            <input class="form-control" name="logo_url" value="<?= html_escape($payload['logo_url'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Wi-Fi Name</label>
            <input class="form-control" name="wifi_name" value="<?= html_escape($payload['wifi_name'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Wi-Fi Password</label>
            <input class="form-control" name="wifi_password" value="<?= html_escape($payload['wifi_password'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch border rounded-4 px-3 py-2 bg-light-subtle h-100 d-flex align-items-center">
              <input class="form-check-input" type="checkbox" id="show_customer_point_info" name="show_customer_point_info" value="1" <?= !empty($payload['show_customer_point_info']) ? 'checked' : '' ?>>
              <label class="form-check-label ms-2" for="show_customer_point_info">Cetak info poin default</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch border rounded-4 px-3 py-2 bg-light-subtle h-100 d-flex align-items-center">
              <input class="form-check-input" type="checkbox" id="show_customer_stamp_info" name="show_customer_stamp_info" value="1" <?= !empty($payload['show_customer_stamp_info']) ? 'checked' : '' ?>>
              <label class="form-check-label ms-2" for="show_customer_stamp_info">Cetak info stamp default</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch border rounded-4 px-3 py-2 bg-light-subtle h-100 d-flex align-items-center">
              <input class="form-check-input" type="checkbox" id="show_customer_voucher" name="show_customer_voucher" value="1" <?= !empty($payload['show_customer_voucher']) ? 'checked' : '' ?>>
              <label class="form-check-label ms-2" for="show_customer_voucher">Cetak voucher default</label>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Voucher Limit</label>
            <input class="form-control" type="number" min="1" max="5" name="customer_voucher_limit" value="<?= (int)($payload['customer_voucher_limit'] ?? 1) ?>">
          </div>
          <div class="col-md-8">
            <label class="form-label">Perataan Voucher</label>
            <select class="form-select" name="customer_voucher_align">
              <?php foreach (['LEFT','CENTER','RIGHT','JUSTIFY'] as $align): ?>
                <option value="<?= $align ?>" <?= strtoupper((string)($payload['customer_voucher_align'] ?? 'CENTER')) === $align ? 'selected' : '' ?>><?= html_escape($align) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Template Pesan Voucher</label>
            <textarea class="form-control" rows="3" name="customer_voucher_message_template"><?= html_escape($payload['customer_voucher_message_template'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Baris Header Umum</label>
            <textarea class="form-control" rows="4" name="header_lines"><?= html_escape(implode("\n", (array)($payload['header_lines'] ?? []))) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Baris Footer Umum</label>
            <textarea class="form-control" rows="4" name="footer_lines"><?= html_escape(implode("\n", (array)($payload['footer_lines'] ?? []))) ?></textarea>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="<?= site_url('pos/printers') ?>" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan Pengaturan Umum</button>
        </div>
      </div>
    </form>

    <div class="card printer-settings-card">
      <div class="card-body">
        <div class="printer-settings-kicker"><i class="ri-information-line"></i> Cara Pakai</div>
        <div class="printer-settings-note mt-3">
          Pengaturan di halaman ini menjadi baseline global untuk printer POS. Template baru akan mengikuti nilai umum ini, dan template lama hasil import juga otomatis berhenti memakai URL logo lama dari core.
        </div>
        <div class="printer-settings-list">
          <div class="printer-settings-item">
            <strong>Global</strong>
            Nama perusahaan, logo, Wi-Fi, dan default loyalty dikelola sekali di sini agar tim operasional tidak perlu mengulang di tiap template.
          </div>
          <div class="printer-settings-item">
            <strong>Per Template</strong>
            Template receipt, KOT, refund, dan void tetap bisa override nilai tertentu kalau butuh format khusus per dokumen.
          </div>
          <div class="printer-settings-item">
            <strong>Per Printer</strong>
            Kertas, jumlah copy, agent host, MAC, dan device fisik tetap diatur di level printer/device, bukan di global.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
