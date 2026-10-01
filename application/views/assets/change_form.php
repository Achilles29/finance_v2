<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'changes']);
$asset = $asset ?? [];
$val = static function (string $key, $default = '') use ($asset) {
    return $asset[$key] ?? $default;
};
$photo = trim((string)$val('photo_path', ''));
?>
<style>
.asset-change-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.asset-change-note{border:1px solid #d9e9e3;background:#f4fbf8;border-radius:10px}
.asset-change-photo{width:130px;height:130px;border-radius:10px;border:1px solid #e5e7eb;object-fit:cover;background:#f8fafc}
@media (max-width:767.98px){.asset-change-grid{grid-template-columns:1fr}}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h4 class="mb-1">Ajukan Perubahan Data Aset</h4>
    <div class="text-muted"><?= html_escape(($asset['asset_code'] ?? '-') . ' | ' . ($asset['asset_name'] ?? '-')) ?></div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/detail/' . (int)($asset['id'] ?? 0)) ?>"><i class="ri ri-arrow-left-line me-1"></i>Kembali ke aset</a>
</div>

<div class="asset-change-note p-3 mb-3">
  <div class="fw-semibold mb-1"><i class="ri ri-lock-line me-1"></i>Data awal aset sudah terkunci</div>
  <div class="small text-muted">Perubahan di bawah akan disimpan sebagai pengajuan, lalu diperiksa dan diterapkan oleh petugas berwenang. Untuk pindah divisi, lokasi, PIC, rusak, maintenance, atau disposal, gunakan menu workflow aset agar riwayat operasionalnya tetap benar.</div>
</div>

<form method="post" action="<?= site_url('asset-management/changes/store/' . (int)($asset['id'] ?? 0)) ?>" enctype="multipart/form-data" autocomplete="off">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header fw-semibold">Identitas dan nilai yang diajukan</div>
        <div class="card-body asset-change-grid">
          <div>
            <label class="form-label">Nama aset</label>
            <input type="text" name="asset_name" class="form-control" required value="<?= html_escape($val('asset_name')) ?>">
          </div>
          <div>
            <label class="form-label">Kategori</label>
            <select name="category_id" class="form-select">
              <option value="0">Belum dikategorikan</option>
              <?php foreach (($categories ?? []) as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= (int)$val('category_id', 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= html_escape($cat['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Brand</label>
            <input type="text" name="brand" class="form-control" value="<?= html_escape($val('brand')) ?>">
          </div>
          <div>
            <label class="form-label">Model / spesifikasi</label>
            <input type="text" name="model_name" class="form-control" value="<?= html_escape($val('model_name')) ?>">
          </div>
          <div>
            <label class="form-label">Nomor serial</label>
            <input type="text" name="serial_no" class="form-control" value="<?= html_escape($val('serial_no')) ?>">
          </div>
          <div>
            <label class="form-label">Nomor batch</label>
            <input type="text" name="batch_no" class="form-control" value="<?= html_escape($val('batch_no')) ?>">
          </div>
          <div>
            <label class="form-label">Tanggal beli</label>
            <input type="date" name="purchase_date" class="form-control" value="<?= html_escape($val('purchase_date')) ?>">
          </div>
          <div>
            <label class="form-label">Tanggal mulai aset</label>
            <input type="date" name="acquisition_date" class="form-control" value="<?= html_escape($val('acquisition_date')) ?>">
          </div>
          <div>
            <label class="form-label">Harga per unit</label>
            <input type="number" name="acquisition_cost" class="form-control" min="0" step="0.01" value="<?= html_escape((string)$val('acquisition_cost', '0')) ?>">
          </div>
          <div>
            <label class="form-label">Nilai residu</label>
            <input type="number" name="residual_value" class="form-control" min="0" step="0.01" value="<?= html_escape((string)$val('residual_value', '0')) ?>">
          </div>
          <div>
            <label class="form-label">Metode penyusutan</label>
            <select name="depreciation_method" class="form-select">
              <option value="STRAIGHT_LINE" <?= strtoupper((string)$val('depreciation_method', 'STRAIGHT_LINE')) === 'STRAIGHT_LINE' ? 'selected' : '' ?>>Garis lurus</option>
              <option value="NONE" <?= strtoupper((string)$val('depreciation_method', 'STRAIGHT_LINE')) === 'NONE' ? 'selected' : '' ?>>Tidak disusutkan</option>
            </select>
          </div>
          <div>
            <label class="form-label">Umur manfaat (bulan)</label>
            <input type="number" name="useful_life_months" class="form-control" min="0" max="600" value="<?= html_escape((string)$val('useful_life_months', '36')) ?>">
          </div>
          <div>
            <label class="form-label">Bulan mulai susut</label>
            <input type="month" name="depreciation_start_month" class="form-control" value="<?= html_escape($val('depreciation_start_month')) ?>">
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header fw-semibold">Foto, alasan, dan bukti</div>
        <div class="card-body">
          <div class="mb-3">
            <?php if ($photo !== ''): ?><img class="asset-change-photo mb-2" src="<?= html_escape(base_url($photo)) ?>" alt="Foto aset"><?php endif; ?>
            <label class="form-label d-block">Ganti foto aset</label>
            <input type="file" name="asset_photo" class="form-control" accept="image/*">
            <div class="form-text">Kosongkan jika foto aset tidak berubah.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Catatan data aset</label>
            <textarea name="notes" class="form-control" rows="4" placeholder="Contoh: warna unit, sumber pembelian, detail pembeda"><?= html_escape($val('notes')) ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Alasan perubahan</label>
            <textarea name="reason" class="form-control" rows="4" required placeholder="Jelaskan data yang salah atau alasan perubahannya"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Bukti pendukung</label>
            <input type="file" name="evidence_file" class="form-control" accept="image/*">
            <div class="form-text">Opsional, misalnya foto faktur atau label serial.</div>
          </div>
        </div>
      </div>
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary"><i class="ri ri-send-plane-line me-1"></i>Kirim Pengajuan</button>
        <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/detail/' . (int)($asset['id'] ?? 0)) ?>">Batal</a>
      </div>
    </div>
  </div>
</form>
