<?php
$employeeOptions = $employee_options ?? [];
$templateOptions = $template_options ?? [];
$prefill = $prefill ?? [];
$compensationPrefills = $compensation_prefills ?? [];
$ctx = $ctx ?? 'finance';
?>

<style>
  .hr-contract-generate .card {
    border: 0;
    box-shadow: 0 2px 12px rgba(31, 41, 55, 0.08);
  }
  .hr-contract-generate .form-control,
  .hr-contract-generate .form-select {
    min-height: 42px;
  }
</style>

<div class="hr-contract-generate">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Generate Kontrak Pegawai'); ?></h4>
    <small class="text-muted">Nominal disepakati di kontrak ini. Master Pegawai hanya menerima cache dari kontrak ACTIVE.</small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?php echo site_url('hr/contracts?' . http_build_query(['ctx' => $ctx])); ?>">Kembali</a>
    <a class="btn btn-outline-primary" href="<?php echo site_url('hr-contracts/templates?' . http_build_query(['ctx' => $ctx])); ?>">Kelola Template</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <form method="post" action="" id="generateForm" class="row g-2">
          <div class="col-12">
            <label class="form-label">Pegawai</label>
            <select name="employee_id" id="fieldEmployee" class="form-select" required>
              <option value="">Pilih pegawai</option>
              <?php foreach ($employeeOptions as $o): ?>
                <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($prefill['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <input type="hidden" name="previous_contract_id" id="fieldPreviousContractId" value="">

          <div class="col-12">
            <label class="form-label">Template Kontrak</label>
            <select name="template_id" id="fieldTemplate" class="form-select" required>
              <option value="">Pilih template</option>
              <?php foreach ($templateOptions as $o): ?>
                <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($prefill['template_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Jenis kontrak otomatis mengikuti template terpilih.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="start_date" id="fieldStartDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>

          <div class="col-12">
            <div class="border rounded-3 p-3 bg-light-subtle">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                  <div class="fw-semibold">Kompensasi Kontrak</div>
                  <div class="small text-muted">Inilah satu-satunya input nominal yang dipakai untuk absensi dan payroll berikutnya.</div>
                </div>
                <span class="badge text-bg-light border" id="compensationSourceBadge">Pilih pegawai</span>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small mb-1">Gaji Pokok</label>
                  <input type="number" min="0" step="0.01" name="basic_salary" class="form-control" data-compensation-field="basic_salary" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">Tunjangan Jabatan</label>
                  <input type="number" min="0" step="0.01" name="position_allowance" class="form-control" data-compensation-field="position_allowance" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">Tunjangan Objektif</label>
                  <input type="number" min="0" step="0.01" name="other_allowance" class="form-control" data-compensation-field="other_allowance" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">Uang Makan per Hari</label>
                  <input type="number" min="0" step="0.01" name="meal_rate" class="form-control" data-compensation-field="meal_rate" required>
                </div>
              </div>
              <div class="small mt-3 text-muted">
                THP tetap tanpa uang makan: <strong id="fixedTotalLabel">Rp 0</strong>
                <span class="mx-1">|</span>
                estimasi uang makan 26 hari: <strong id="mealEstimateLabel">Rp 0</strong>
              </div>
              <div class="small mt-2 text-muted">Tarif lembur tidak menjadi komponen kontrak; nilainya mengikuti Master Standar Lembur saat lembur dicatat.</div>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Tanggal Akhir</label>
            <input type="date" name="end_date" id="fieldEndDate" class="form-control" placeholder="Auto dari durasi template">
          </div>

          <div class="col-12">
            <label class="form-label">Catatan</label>
            <input type="text" name="notes" class="form-control" placeholder="Opsional">
          </div>

          <div class="col-12 d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary">Generate Kontrak</button>
            <button type="button" class="btn btn-outline-secondary" id="btnPreview">Preview Dokumen</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body d-flex flex-column">
        <h6 class="mb-2">Preview Dokumen Kontrak</h6>
        <div class="border rounded p-3 bg-white flex-grow-1" id="previewContainer" style="min-height: 560px; overflow:auto;">
          <div class="text-muted">Isi data terlebih dulu, lalu klik <strong>Preview Dokumen</strong>.</div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<script>
(function () {
  var btnPreview = document.getElementById('btnPreview');
  var form = document.getElementById('generateForm');
  var previewContainer = document.getElementById('previewContainer');
  var employeeField = document.getElementById('fieldEmployee');
  var compensationFields = Array.prototype.slice.call(document.querySelectorAll('[data-compensation-field]'));
  var sourceBadge = document.getElementById('compensationSourceBadge');
  var fixedTotalLabel = document.getElementById('fixedTotalLabel');
  var mealEstimateLabel = document.getElementById('mealEstimateLabel');
  var compensationPrefills = <?php echo json_encode($compensationPrefills, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  function numberValue(name) {
    var input = form ? form.querySelector('[name="' + name + '"]') : null;
    return input ? (Number(input.value || 0) || 0) : 0;
  }

  function money(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function updateCompensationSummary() {
    var fixed = numberValue('basic_salary') + numberValue('position_allowance') + numberValue('other_allowance');
    var mealEstimate = numberValue('meal_rate') * 26;
    if (fixedTotalLabel) fixedTotalLabel.textContent = money(fixed);
    if (mealEstimateLabel) mealEstimateLabel.textContent = money(mealEstimate);
  }

  function applyCompensationPrefill() {
    if (!employeeField) return;
    var row = compensationPrefills[String(employeeField.value || '')] || null;
    if (!row) {
      compensationFields.forEach(function (input) { input.value = ''; });
      if (sourceBadge) sourceBadge.textContent = 'Pilih pegawai';
      updateCompensationSummary();
      return;
    }
    var hasContractBasis = row.source === 'CONTRACT';
    compensationFields.forEach(function (input) {
      var key = input.getAttribute('data-compensation-field');
      input.value = hasContractBasis ? Number(row[key] || 0).toFixed(2) : '';
    });
    if (sourceBadge) {
      sourceBadge.textContent = hasContractBasis
        ? ('Menyalin ' + (row.contract_number || 'kontrak aktif'))
        : 'Isi nominal untuk kontrak pertama';
    }
    updateCompensationSummary();
  }

  function qs(params) {
    return new URLSearchParams(params).toString();
  }

  function runPreview() {
    if (!btnPreview || !form || !previewContainer) return;
    var fd = new FormData(form);
    var templateId = fd.get('template_id') || '';
    var employeeId = fd.get('employee_id') || '';
    if (!templateId || !employeeId) {
      previewContainer.innerHTML = '<div class="alert alert-warning mb-0">Pilih template dan pegawai terlebih dulu.</div>';
      return;
    }

    var params = {
      preview: 1,
      ctx: '<?php echo html_escape($ctx); ?>',
      template_id: templateId,
      employee_id: employeeId,
      contract_type: '',
      start_date: fd.get('start_date') || '',
      end_date: fd.get('end_date') || ''
    };
    compensationFields.forEach(function (input) {
      params[input.getAttribute('data-compensation-field')] = input.value || '0';
    });

    btnPreview.disabled = true;
    previewContainer.innerHTML = '<div class="text-muted">Memuat preview...</div>';

    fetch('<?php echo site_url('hr-contracts/generate'); ?>?' + qs(params), { credentials: 'same-origin' })
      .then(function (res) { return res.text(); })
      .then(function (html) {
        previewContainer.innerHTML = html;
      })
      .catch(function () {
        previewContainer.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat preview dokumen.</div>';
      })
      .finally(function () {
        btnPreview.disabled = false;
      });
  }

  if (btnPreview) {
    btnPreview.addEventListener('click', runPreview);
  }
  if (employeeField) {
    employeeField.addEventListener('change', applyCompensationPrefill);
  }
  compensationFields.forEach(function (input) {
    input.addEventListener('input', updateCompensationSummary);
  });
  applyCompensationPrefill();
})();
</script>
