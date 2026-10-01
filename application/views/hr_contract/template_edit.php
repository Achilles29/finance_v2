<?php
$row = $row ?? [];
$isCreate = !empty($is_create);
$ctx = $ctx ?? 'finance';
$contractTypeOptions = $contract_type_options ?? ['K1', 'K2', 'K3', 'CUSTOM'];
$canDelete = !empty($can_delete);
$employeeOptions = $employee_options ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Template Kontrak'); ?></h4>
    <small class="text-muted">Editor template + preview real-time placeholder.</small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?php echo site_url('hr-contracts/templates?' . http_build_query(['ctx' => $ctx])); ?>">Kembali</a>
    <?php if (!$isCreate): ?>
      <a class="btn btn-outline-primary" href="<?php echo site_url('hr-contracts/generate?' . http_build_query(['template_id' => (int)$row['id'], 'ctx' => $ctx])); ?>">Generate Dari Template Ini</a>
    <?php endif; ?>
    <?php if ($canDelete && !$isCreate): ?>
      <form method="post" action="<?php echo site_url('hr-contracts/template-delete/' . (int)$row['id'] . '?' . http_build_query(['ctx' => $ctx])); ?>" onsubmit="return confirm('Hapus template ini?');" class="d-inline">
        <button type="submit" class="btn btn-outline-danger">Hapus</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <form method="post" action="" id="templateForm" class="row g-2">
          <div class="col-md-5">
            <label class="form-label">Kode Template</label>
            <input type="text" class="form-control" name="template_code" value="<?php echo html_escape((string)($row['template_code'] ?? '')); ?>" required>
          </div>
          <div class="col-md-7">
            <label class="form-label">Nama Template</label>
            <input type="text" class="form-control" name="template_name" value="<?php echo html_escape((string)($row['template_name'] ?? '')); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Jenis Kontrak</label>
            <select class="form-select" name="contract_type" id="fieldContractType" required>
              <?php foreach ($contractTypeOptions as $type): ?>
                <option value="<?php echo html_escape($type); ?>" <?php echo (($row['contract_type'] ?? 'K1') === $type) ? 'selected' : ''; ?>><?php echo html_escape($type); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Durasi (bulan)</label>
            <input type="number" class="form-control" name="duration_months" min="1" value="<?php echo (int)($row['duration_months'] ?? 3); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="is_active">
              <option value="1" <?php echo !empty($row['is_active']) ? 'selected' : ''; ?>>Aktif</option>
              <option value="0" <?php echo empty($row['is_active']) ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Body HTML Template</label>
            <textarea class="form-control font-monospace" name="body_html" id="fieldBodyHtml" rows="18" required><?php echo html_escape((string)($row['body_html'] ?? '')); ?></textarea>
            <small class="text-muted">Placeholder: <code>{{NAMA_PEGAWAI}}</code>, <code>{{JABATAN}}</code>, <code>{{DIVISI}}</code>, <code>{{OUTLET}}</code>, <code>{{TANGGAL_KONTRAK}}</code>, <code>{{TANGGAL_MULAI}}</code>, <code>{{TANGGAL_AKHIR}}</code>, <code>{{DURASI_KONTRAK}}</code>, <code>{{KONTRAK_SEBELUMNYA}}</code>, <code>{{GAJI_POKOK_DASAR}}</code>, <code>{{TUNJANGAN_JABATAN}}</code>, <code>{{TUNJANGAN_OBJEKTIF}}</code>, <code>{{UANG_MAKAN}}</code>, <code>{{TARIF_LEMBUR}}</code>, <code>{{TOTAL_KOMPENSASI_TETAP}}</code>. <code>{{TARIF_LEMBUR}}</code> selalu merujuk ke Master Standar Lembur, bukan nominal pegawai.</small>
          </div>

          <div class="col-12 d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary"><?php echo $isCreate ? 'Simpan Template' : 'Update Template'; ?></button>
            <button type="button" class="btn btn-outline-secondary" id="btnPreview">Preview</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Preview Template</h6>
          <select id="previewEmployee" class="form-select form-select-sm" style="max-width: 320px;">
            <?php foreach ($employeeOptions as $eo): ?>
              <option value="<?php echo (int)$eo['value']; ?>"><?php echo html_escape((string)$eo['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="border rounded p-3 bg-white flex-grow-1" id="previewContainer" style="min-height: 520px; overflow:auto;">
          <div class="text-muted">Klik <strong>Preview</strong> untuk melihat hasil render dokumen.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var btnPreview = document.getElementById('btnPreview');
  var previewContainer = document.getElementById('previewContainer');
  var form = document.getElementById('templateForm');
  var employeeSelect = document.getElementById('previewEmployee');

  function runPreview() {
    if (!btnPreview || !previewContainer || !form) return;
    var fd = new FormData(form);
    fd.set('template_id', '<?php echo (int)($row['id'] ?? 0); ?>');
    fd.set('employee_id', employeeSelect ? employeeSelect.value : '0');
    fd.set('start_date', '<?php echo date('Y-m-d'); ?>');
    btnPreview.disabled = true;
    previewContainer.innerHTML = '<div class="text-muted">Memuat preview...</div>';

    fetch('<?php echo site_url('hr-contracts/template-preview?' . http_build_query(['ctx' => $ctx])); ?>', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(function (res) {
      return res.text();
    }).then(function (html) {
      previewContainer.innerHTML = html;
    }).catch(function () {
      previewContainer.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat preview.</div>';
    }).finally(function () {
      btnPreview.disabled = false;
    });
  }

  if (btnPreview) {
    btnPreview.addEventListener('click', runPreview);
  }
})();
</script>
