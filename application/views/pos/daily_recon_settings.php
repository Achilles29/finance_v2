<?php
$mode = strtoupper(trim((string)($mode ?? 'OPEN_AND_CLOSE')));
$policy = strtoupper(trim((string)($policy ?? 'BLOCK')));
$confirmMode = strtoupper(trim((string)($confirm_mode ?? 'BULK_ALLOWED')));
$requiredMaterialIds = array_flip(array_map('intval', (array)($required_material_ids ?? [])));
$requiredComponentIds = array_flip(array_map('intval', (array)($required_component_ids ?? [])));
$materialOptions = is_array($material_options ?? null) ? $material_options : [];
$componentOptions = is_array($component_options ?? null) ? $component_options : [];
$canEdit = !empty($can_edit);

$modeLabels = [
    'OFF' => [
        'title' => 'Nonaktif',
        'desc' => 'Kasir tidak dicek terhadap konfirmasi daily recon saat buka atau tutup.',
    ],
    'OPEN_ONLY' => [
        'title' => 'Cek saat buka kasir',
        'desc' => 'Saat kasir dibuka, sistem memblokir proses jika daily recon hari itu belum dikonfirmasi lengkap.',
    ],
    'CLOSE_ONLY' => [
        'title' => 'Cek saat tutup kasir',
        'desc' => 'Saat kasir ditutup, sistem memblokir proses jika daily recon hari itu belum dikonfirmasi lengkap.',
    ],
    'OPEN_AND_CLOSE' => [
        'title' => 'Cek saat buka dan tutup kasir',
        'desc' => 'Mode paling lengkap: sistem mengecek konfirmasi daily recon saat awal dan akhir shift.',
    ],
];

$confirmModeLabels = [
    'BULK_ALLOWED' => [
        'title' => 'Boleh konfirmasi semua',
        'desc' => 'User bisa konfirmasi semua, kecuali ada item multi-lot atau item wajib recon yang belum dicek per baris.',
    ],
    'ROW_REQUIRED' => [
        'title' => 'Wajib satu per satu',
        'desc' => 'Setiap baris wajib dikonfirmasi dari halaman recon sebelum checkpoint divisi dianggap aman.',
    ],
];
?>

<style>
.pos-recon-setting-card {
  border: 0;
  border-radius: 18px;
  box-shadow: 0 14px 34px rgba(35, 24, 18, .08);
}
.pos-recon-mode-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .8rem;
}
.pos-recon-mode {
  border: 1px solid #ead8cc;
  border-radius: 16px;
  background: linear-gradient(145deg, #fff, #fff7ef);
  padding: 1rem;
  min-height: 132px;
  cursor: pointer;
  transition: .18s ease;
}
.pos-recon-mode:hover {
  transform: translateY(-1px);
  border-color: #d8aa83;
  box-shadow: 0 8px 22px rgba(124, 57, 35, .09);
}
.pos-recon-mode.is-active {
  border-color: #a50f24;
  background: linear-gradient(145deg, #fff7ef, #fff0d6);
  box-shadow: inset 0 0 0 2px rgba(165, 15, 36, .09);
}
.pos-recon-mode input {
  margin-right: .45rem;
}
.pos-recon-mode-title {
  color: #861323;
  font-weight: 800;
  letter-spacing: .01em;
}
.pos-recon-mode-desc {
  color: #756357;
  font-size: .86rem;
  line-height: 1.35;
  margin-top: .55rem;
}
.pos-recon-info {
  border: 1px dashed #e7bd87;
  background: #fffaf0;
  border-radius: 16px;
  padding: .9rem 1rem;
  color: #6b4c37;
}
.pos-recon-checklist {
  border: 1px solid #ead8cc;
  border-radius: 16px;
  background: #fffaf8;
  overflow: hidden;
}
.pos-recon-checklist-head {
  padding: .85rem;
  border-bottom: 1px solid #f0dfd2;
  background: linear-gradient(135deg, #fff, #fff4e7);
}
.pos-recon-checklist-body {
  max-height: 320px;
  overflow: auto;
  padding: .45rem;
}
.pos-recon-check-item {
  display: flex;
  gap: .65rem;
  align-items: flex-start;
  border-radius: 12px;
  padding: .55rem .65rem;
  cursor: pointer;
}
.pos-recon-check-item:hover {
  background: #fff0de;
}
.pos-recon-check-title {
  color: #321d16;
  font-weight: 800;
  line-height: 1.2;
}
.pos-recon-check-code {
  color: #8b6f60;
  font-size: .78rem;
}
@media (max-width: 992px) {
  .pos-recon-mode-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 576px) {
  .pos-recon-mode-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Pengaturan Gate Daily Recon POS</h4>
      <p class="fin-page-subtitle mb-0">
        POS hanya mengecek apakah daily recon bahan baku dan component sudah dikonfirmasi. Rekon tetap dilakukan di halaman divisi masing-masing.
      </p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'daily-recon-settings']); ?>

  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <div class="card pos-recon-setting-card">
    <div class="card-body p-4">
      <?php echo form_open('pos/daily-recon-settings/save'); ?>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <h5 class="mb-1">Mode pengecekan saat buka/tutup kasir</h5>
            <div class="text-muted small">
              Jika gate aktif dan recon belum lengkap, kasir tidak bisa buka/tutup sampai checkpoint recon lengkap. Policy saat ini: <strong><?php echo html_escape($policy); ?></strong>.
            </div>
          </div>
          <button type="submit" class="btn btn-danger" <?php echo !$canEdit ? 'disabled' : ''; ?>>
            Simpan Pengaturan
          </button>
        </div>

        <div class="pos-recon-mode-grid mb-4">
          <?php foreach ($modeLabels as $key => $item): ?>
            <label class="pos-recon-mode <?php echo $mode === $key ? 'is-active' : ''; ?>">
              <div class="d-flex align-items-center">
                <input type="radio" name="daily_recon_gate_mode" value="<?php echo html_escape($key); ?>" <?php echo $mode === $key ? 'checked' : ''; ?> <?php echo !$canEdit ? 'disabled' : ''; ?>>
                <span class="pos-recon-mode-title"><?php echo html_escape($item['title']); ?></span>
              </div>
              <div class="pos-recon-mode-desc"><?php echo html_escape($item['desc']); ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="pos-recon-info">
          <strong>Alur yang dipakai:</strong>
          <span>Divisi melakukan recon di <code>/inventory/stock/daily-recon/division</code> dan <code>/production/component-daily-recon</code>, lalu menekan konfirmasi buka/tutup. POS hanya membaca checkpoint tersebut.</span>
        </div>

        <hr class="my-4">

        <div class="mb-3">
          <h5 class="mb-1">Mode konfirmasi di halaman recon</h5>
          <div class="text-muted small">
            Item dengan lebih dari satu lot otomatis wajib dicek per baris. Daftar wajib di bawah ini menambah item yang tidak boleh dilewati oleh tombol konfirmasi semua.
          </div>
        </div>

        <div class="pos-recon-mode-grid mb-4" style="grid-template-columns:repeat(2,minmax(0,1fr))">
          <?php foreach ($confirmModeLabels as $key => $item): ?>
            <label class="pos-recon-mode <?php echo $confirmMode === $key ? 'is-active' : ''; ?>">
              <div class="d-flex align-items-center">
                <input type="radio" name="daily_recon_confirm_mode" value="<?php echo html_escape($key); ?>" <?php echo $confirmMode === $key ? 'checked' : ''; ?> <?php echo !$canEdit ? 'disabled' : ''; ?>>
                <span class="pos-recon-mode-title"><?php echo html_escape($item['title']); ?></span>
              </div>
              <div class="pos-recon-mode-desc"><?php echo html_escape($item['desc']); ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <label class="form-label fw-semibold">Bahan baku wajib recon per baris</label>
            <div class="pos-recon-checklist" data-checklist>
              <div class="pos-recon-checklist-head">
                <input type="search" class="form-control form-control-sm" data-checklist-search placeholder="Cari bahan baku...">
              </div>
              <div class="pos-recon-checklist-body">
                <?php foreach ($materialOptions as $row): ?>
                  <?php
                    $id = (int)($row['id'] ?? 0);
                    $title = (string)($row['material_name'] ?? '');
                    $code = (string)($row['material_code'] ?? '');
                    $haystack = strtolower(trim($id . ' ' . $title . ' ' . $code));
                  ?>
                  <label class="pos-recon-check-item" data-checklist-item="<?php echo html_escape($haystack); ?>">
                    <input type="checkbox" name="daily_recon_required_materials[]" value="<?php echo $id; ?>" <?php echo isset($requiredMaterialIds[$id]) ? 'checked' : ''; ?> <?php echo !$canEdit ? 'disabled' : ''; ?>>
                    <span>
                      <span class="pos-recon-check-title d-block"><?php echo html_escape($title ?: ('Material #' . $id)); ?></span>
                      <span class="pos-recon-check-code d-block"><?php echo html_escape($code ?: ('ID ' . $id)); ?></span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-text">Checklist material yang tetap wajib dicek per baris walau mode konfirmasi semua aktif.</div>
          </div>
          <div class="col-lg-6">
            <label class="form-label fw-semibold">Component wajib recon per baris</label>
            <div class="pos-recon-checklist" data-checklist>
              <div class="pos-recon-checklist-head">
                <input type="search" class="form-control form-control-sm" data-checklist-search placeholder="Cari component...">
              </div>
              <div class="pos-recon-checklist-body">
                <?php foreach ($componentOptions as $row): ?>
                  <?php
                    $id = (int)($row['id'] ?? 0);
                    $title = (string)($row['component_name'] ?? '');
                    $code = (string)($row['component_code'] ?? '');
                    $haystack = strtolower(trim($id . ' ' . $title . ' ' . $code));
                  ?>
                  <label class="pos-recon-check-item" data-checklist-item="<?php echo html_escape($haystack); ?>">
                    <input type="checkbox" name="daily_recon_required_components[]" value="<?php echo $id; ?>" <?php echo isset($requiredComponentIds[$id]) ? 'checked' : ''; ?> <?php echo !$canEdit ? 'disabled' : ''; ?>>
                    <span>
                      <span class="pos-recon-check-title d-block"><?php echo html_escape($title ?: ('Component #' . $id)); ?></span>
                      <span class="pos-recon-check-code d-block"><?php echo html_escape($code ?: ('ID ' . $id)); ?></span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-text">Checklist component yang tetap wajib dicek per baris walau mode konfirmasi semua aktif.</div>
          </div>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-checklist]').forEach(function (wrap) {
  var input = wrap.querySelector('[data-checklist-search]');
  if (!input) return;
  input.addEventListener('input', function () {
    var q = String(input.value || '').trim().toLowerCase();
    wrap.querySelectorAll('[data-checklist-item]').forEach(function (item) {
      item.style.display = !q || String(item.dataset.checklistItem || '').indexOf(q) !== -1 ? '' : 'none';
    });
  });
});
</script>
