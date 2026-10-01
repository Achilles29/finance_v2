<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'items']);
$asset = $asset ?? null;
$isEdit = !empty($is_edit);
$action = $isEdit ? site_url('asset-management/update/' . (int)$asset['id']) : site_url('asset-management/store');
$val = static function ($key, $default = '') use ($asset) {
  return $asset !== null ? ($asset[$key] ?? $default) : $default;
};
$today = date('Y-m-d');
$photo = trim((string)$val('photo_path', ''));
?>
<style>
.asset-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.asset-photo-preview{width:150px;height:150px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover;background:#f8fafc}
.asset-help-box{border:1px solid #e7d8ce;border-radius:8px;background:#fffdfb}
@media (max-width: 767.98px){.asset-form-grid{grid-template-columns:1fr}.asset-photo-preview{width:100%;height:190px}}
</style>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1"><?= $isEdit ? 'Edit Aset' : 'Tambah Aset Bulk' ?></h4>
    <div class="text-muted"><?= $isEdit ? 'Ubah data satu unit aset.' : 'Input satu model barang, sistem akan membuat kode aset unik sejumlah quantity.' ?></div>
  </div>
  <a href="<?= site_url('asset-management') ?>" class="btn btn-outline-secondary"><i class="ri ri-arrow-left-line me-1"></i>Kembali</a>
</div>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" autocomplete="off">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header fw-semibold">Identitas aset</div>
        <div class="card-body asset-form-grid">
          <div>
            <label class="form-label">Nama aset</label>
            <input type="text" name="asset_name" class="form-control" required value="<?= html_escape($val('asset_name')) ?>" placeholder="Contoh: Gelas latte 250ml">
          </div>
          <div>
            <label class="form-label">Kategori</label>
            <select name="category_id" id="assetCategory" class="form-select">
              <option value="0">Pilih kategori</option>
              <?php foreach (($categories ?? []) as $cat): ?>
                <option
                  value="<?= (int)$cat['id'] ?>"
                  data-method="<?= html_escape($cat['default_depreciation_method'] ?? 'STRAIGHT_LINE') ?>"
                  data-life="<?= (int)($cat['default_useful_life_months'] ?? 36) ?>"
                  data-residual="<?= html_escape((string)($cat['default_residual_value'] ?? '0')) ?>"
                  <?= (int)$val('category_id', 0) === (int)$cat['id'] ? 'selected' : '' ?>
                ><?= html_escape($cat['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Brand</label>
            <input type="text" name="brand" class="form-control" value="<?= html_escape($val('brand')) ?>">
          </div>
          <div>
            <label class="form-label">Model / spesifikasi</label>
            <input type="text" name="model_name" class="form-control" value="<?= html_escape($val('model_name')) ?>" placeholder="Ukuran, warna, tipe, bahan">
          </div>
          <div>
            <label class="form-label">Serial no</label>
            <input type="text" name="serial_no" class="form-control" value="<?= html_escape($val('serial_no')) ?>" <?= $isEdit ? '' : 'placeholder="Opsional bila hanya 1 unit"' ?>>
          </div>
          <div>
            <label class="form-label">Batch no</label>
            <input type="text" name="batch_no" class="form-control" value="<?= html_escape($val('batch_no')) ?>" placeholder="Otomatis jika kosong">
          </div>
          <?php if (!$isEdit): ?>
            <div>
              <label class="form-label">Quantity bulk</label>
              <input type="number" name="quantity" class="form-control" min="1" max="500" value="1">
            </div>
            <div>
              <label class="form-label">Serial number per unit</label>
              <textarea name="serial_numbers" class="form-control" rows="3" placeholder="Satu serial per baris, opsional"></textarea>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header fw-semibold">Lokasi dan penanggung jawab</div>
        <div class="card-body asset-form-grid">
          <div>
            <label class="form-label">Divisi</label>
            <select name="division_id" class="form-select">
              <option value="0">Pilih divisi</option>
              <?php foreach (($divisions ?? []) as $div): ?>
                <option value="<?= (int)$div['id'] ?>" <?= (int)$val('division_id', 0) === (int)$div['id'] ? 'selected' : '' ?>><?= html_escape($div['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Outlet</label>
            <select name="outlet_id" class="form-select">
              <option value="0">Pilih outlet</option>
              <?php foreach (($outlets ?? []) as $outlet): ?>
                <option value="<?= (int)$outlet['id'] ?>" <?= (int)$val('outlet_id', 0) === (int)$outlet['id'] ? 'selected' : '' ?>><?= html_escape($outlet['outlet_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Lokasi detail</label>
            <input type="text" name="current_location" class="form-control" value="<?= html_escape($val('current_location')) ?>" placeholder="Contoh: Rak bar kanan, kitchen prep">
          </div>
          <div>
            <label class="form-label">PIC / pemegang</label>
            <select name="custodian_employee_id" class="form-select">
              <option value="0">Tidak ditentukan</option>
              <?php foreach (($employees ?? []) as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>" <?= (int)$val('custodian_employee_id', 0) === (int)$emp['id'] ? 'selected' : '' ?>><?= html_escape($emp['employee_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header fw-semibold">Nilai dan penyusutan</div>
        <div class="card-body asset-form-grid">
          <div>
            <label class="form-label">Tanggal beli</label>
            <input type="date" name="purchase_date" class="form-control" value="<?= html_escape($val('purchase_date', $today)) ?>">
          </div>
          <div>
            <label class="form-label">Tanggal mulai aset</label>
            <input type="date" name="acquisition_date" class="form-control" value="<?= html_escape($val('acquisition_date', $today)) ?>">
          </div>
          <div>
            <label class="form-label">Harga per unit</label>
            <input type="number" name="acquisition_cost" class="form-control" min="0" step="0.01" value="<?= html_escape($val('acquisition_cost', '0')) ?>">
          </div>
          <div>
            <label class="form-label">Nilai residu</label>
            <input type="number" name="residual_value" id="assetResidual" class="form-control" min="0" step="0.01" value="<?= html_escape($val('residual_value', '0')) ?>">
          </div>
          <div>
            <label class="form-label">Metode penyusutan</label>
            <select name="depreciation_method" id="assetMethod" class="form-select">
              <?php foreach (['STRAIGHT_LINE' => 'Garis lurus', 'NONE' => 'Tidak disusutkan'] as $key => $label): ?>
                <option value="<?= $key ?>" <?= strtoupper((string)$val('depreciation_method', 'STRAIGHT_LINE')) === $key ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Umur manfaat bulan</label>
            <input type="number" name="useful_life_months" id="assetLife" class="form-control" min="0" max="600" value="<?= html_escape($val('useful_life_months', '36')) ?>">
          </div>
          <div>
            <label class="form-label">Bulan mulai susut</label>
            <input type="month" name="depreciation_start_month" class="form-control" value="<?= html_escape($val('depreciation_start_month', date('Y-m'))) ?>">
          </div>
          <div>
            <label class="form-label">Skor kondisi</label>
            <input type="number" name="condition_score" class="form-control" min="0" max="100" value="<?= html_escape($val('condition_score', '100')) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header fw-semibold">Foto dan status</div>
        <div class="card-body">
          <div class="mb-3">
            <?php if ($photo !== ''): ?>
              <img src="<?= html_escape(base_url($photo)) ?>" class="asset-photo-preview mb-2" id="assetPhotoPreview" alt="Foto aset">
            <?php else: ?>
              <img src="" class="asset-photo-preview mb-2" id="assetPhotoPreview" alt="" style="display:none">
            <?php endif; ?>
            <input type="file" name="asset_photo" id="assetPhotoInput" class="form-control" accept="image/*">
            <div class="form-text">Foto akan dipakai sebagai referensi visual saat rekon.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (($status_labels ?? []) as $key => $label): ?>
                <option value="<?= html_escape($key) ?>" <?= strtoupper((string)$val('status', 'ACTIVE')) === $key ? 'selected' : '' ?>><?= html_escape($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="5" placeholder="Keterangan pembelian, ciri fisik, atau catatan lokasi"><?= html_escape($val('notes')) ?></textarea>
          </div>
        </div>
      </div>

      <div class="asset-help-box p-3 mb-3">
        <div class="fw-semibold mb-1"><i class="ri ri-magic-line me-1"></i>Prinsip pencatatan</div>
        <div class="small text-muted">
          Bulk tetap dibuat per unit. Jika 24 gelas diinput sekali, sistem membuat 24 kode aset supaya saat satu pecah, hanya satu unit yang berubah status.
        </div>
      </div>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary"><i class="ri ri-save-line me-1"></i>Simpan</button>
        <a href="<?= site_url('asset-management') ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </div>
  </div>
</form>

<script>
(function(){
  var cat = document.getElementById('assetCategory');
  var method = document.getElementById('assetMethod');
  var life = document.getElementById('assetLife');
  var residual = document.getElementById('assetResidual');
  if (cat) {
    cat.addEventListener('change', function(){
      var opt = cat.options[cat.selectedIndex];
      if (!opt) return;
      if (method && opt.dataset.method) method.value = opt.dataset.method;
      if (life && opt.dataset.life) life.value = opt.dataset.life;
      if (residual && opt.dataset.residual) residual.value = opt.dataset.residual;
    });
  }
  var input = document.getElementById('assetPhotoInput');
  var preview = document.getElementById('assetPhotoPreview');
  if (input && preview) {
    input.addEventListener('change', function(){
      var file = input.files && input.files[0];
      if (!file) return;
      if (!file.type || file.type.indexOf('image/') !== 0) {
        alert('File harus gambar.');
        input.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = '';
      };
      reader.readAsDataURL(file);
    });
  }
})();
</script>
