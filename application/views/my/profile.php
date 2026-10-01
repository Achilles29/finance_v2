<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$contractRows = $contract_rows ?? [];
$selectedContractId = (int)($selected_contract_id ?? 0);
$selectedContract = $selected_contract ?? null;
$currentUser = $current_user ?? [];
$approvalMap = $approval_map ?? [];
$signatureMap = $signature_map ?? [];
$canEmployeeSign = !empty($can_employee_sign);
$employeeApproval = $approvalMap['EMPLOYEE'] ?? null;
$companyApproval = $approvalMap['COMPANY'] ?? null;
$employeeSignature = $signatureMap['EMPLOYEE'] ?? null;
$companySignature = $signatureMap['COMPANY'] ?? null;
$contractStatus = strtoupper((string)($selectedContract['status'] ?? 'DRAFT'));

$buildProfileUrl = static function (array $overrides = []) use ($selectedEmployeeId, $selectedContractId): string {
    $query = [];
    if ($selectedEmployeeId > 0) {
        $query['employee_id'] = $selectedEmployeeId;
    }
    if ($selectedContractId > 0) {
        $query['contract_id'] = $selectedContractId;
    }
    $query = array_merge($query, $overrides);
    return site_url('my/profile' . (!empty($query) ? ('?' . http_build_query($query)) : ''));
};

$contractStatusClass = static function (string $status): string {
    $status = strtoupper(trim($status));
    if ($status === 'DRAFT') return 'bg-label-warning';
    if ($status === 'GENERATED') return 'bg-label-info';
    if ($status === 'SIGNED') return 'bg-label-primary';
    if ($status === 'ACTIVE') return 'bg-label-success';
    if ($status === 'EXPIRED') return 'bg-label-dark';
    if (in_array($status, ['TERMINATED', 'CANCELLED'], true)) return 'bg-label-danger';
    return 'bg-label-secondary';
};

$verifyUrl = !empty($selectedContract['verification_token']) ? site_url('hr-contracts/verify/' . (string)$selectedContract['verification_token']) : '';
$printQuery = [];
if ($selectedEmployeeId > 0) {
    $printQuery['employee_id'] = $selectedEmployeeId;
}
$printUrl = !empty($selectedContract['id'])
    ? site_url('my/profile/contracts/print/' . (int)$selectedContract['id'] . (!empty($printQuery) ? ('?' . http_build_query($printQuery)) : ''))
    : '';
$signUrl = !empty($selectedContract['id']) ? site_url('my/profile/contracts/sign/' . (int)$selectedContract['id']) : '';
?>

<style>
  .contract-doc-preview {
    max-height: 640px;
    overflow: auto;
    background: #fff;
  }
  .signature-pad-wrap {
    border: 1px dashed #cfd4dc;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
    padding: 12px;
  }
  .signature-pad {
    width: 100%;
    height: 220px;
    display: block;
    border: 1px solid #dfe3ea;
    border-radius: 10px;
    background: #fff;
    touch-action: none;
    cursor: crosshair;
  }
  .signature-preview {
    max-width: 100%;
    max-height: 120px;
    border: 1px solid #dfe3ea;
    border-radius: 10px;
    background: #fff;
    padding: 6px;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Kontrak Saya'); ?></h4>
    <small class="text-muted">Akses kontrak kerja masing-masing pegawai langsung dari portal pribadi.</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/profile'); ?>" class="d-flex gap-2">
    <select name="employee_id" class="form-select form-select-sm" style="min-width:260px">
      <option value="">Pilih Pegawai (Preview Superadmin)</option>
      <?php foreach ($employeeOptions as $o): ?>
        <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>>
          <?php echo html_escape($o['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-primary">Buka</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">
  Data pegawai belum terhubung ke akun ini.
</div>
<?php else: ?>
<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <small class="text-muted d-block">Pegawai</small>
        <h5 class="mb-1"><?php echo html_escape((string)$employee['employee_name']); ?></h5>
        <div class="text-muted small mb-2"><?php echo html_escape((string)$employee['employee_code']); ?></div>
        <div class="small text-muted mb-1">NIP: <?php echo html_escape((string)($employee['employee_nip'] ?? '-')); ?></div>
        <div class="small text-muted mb-3">Email: <?php echo html_escape((string)($employee['email'] ?? '-')); ?></div>
        <div class="d-flex gap-2 flex-wrap">
          <span class="badge bg-label-primary"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?></span>
          <span class="badge bg-label-info"><?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <small class="text-muted d-block">Ringkasan Kontrak</small>
            <div class="fw-semibold"><?php echo count($contractRows); ?> dokumen kontrak</div>
            <div class="small text-muted">Menampilkan seluruh kontrak yang terhubung ke pegawai ini.</div>
          </div>
          <?php if ($printUrl !== ''): ?>
          <div class="d-flex gap-2">
            <a href="<?php echo $printUrl; ?>" target="_blank" rel="noopener" class="btn btn-outline-dark btn-sm">Cetak</a>
            <?php if ($verifyUrl !== ''): ?>
              <a href="<?php echo $verifyUrl; ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Verifikasi</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if (empty($contractRows)): ?>
          <div class="alert alert-warning mb-0">Belum ada kontrak yang terdaftar untuk pegawai ini.</div>
        <?php else: ?>
          <div class="row g-2">
            <?php foreach ($contractRows as $contract): ?>
              <?php $isActive = (int)($contract['id'] ?? 0) === $selectedContractId; ?>
              <div class="col-md-6">
                <a href="<?php echo html_escape($buildProfileUrl(['contract_id' => (int)($contract['id'] ?? 0)])); ?>" class="text-decoration-none">
                  <div class="border rounded p-3 h-100 <?php echo $isActive ? 'border-primary bg-label-primary' : 'bg-white'; ?>">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                      <div class="fw-semibold text-dark"><?php echo html_escape((string)($contract['contract_number'] ?? 'Draft Kontrak')); ?></div>
                      <span class="badge <?php echo $contractStatusClass((string)($contract['status'] ?? '')); ?>"><?php echo html_escape((string)($contract['status'] ?? '-')); ?></span>
                    </div>
                    <div class="small text-muted"><?php echo html_escape((string)($contract['template_name'] ?? 'Tanpa template')); ?></div>
                    <div class="small text-muted"><?php echo html_escape((string)($contract['contract_type'] ?? '-')); ?> • <?php echo html_escape((string)($contract['start_date'] ?? '-')); ?> s/d <?php echo html_escape((string)($contract['end_date'] ?? '-')); ?></div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($selectedContract)): ?>
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
          <div>
            <small class="text-muted d-block">Kontrak Terpilih</small>
            <div class="fw-semibold fs-5"><?php echo html_escape((string)($selectedContract['contract_number'] ?? '-')); ?></div>
          </div>
          <span class="badge <?php echo $contractStatusClass((string)($selectedContract['status'] ?? '')); ?>"><?php echo html_escape((string)($selectedContract['status'] ?? '-')); ?></span>
        </div>
        <div class="row g-2 small">
          <div class="col-md-6"><span class="text-muted">Jenis:</span> <?php echo html_escape((string)($selectedContract['contract_type'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Periode:</span> <?php echo html_escape((string)($selectedContract['start_date'] ?? '-')); ?> s/d <?php echo html_escape((string)($selectedContract['end_date'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Template:</span> <?php echo html_escape((string)($selectedContract['template_name'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Generated:</span> <?php echo !empty($selectedContract['generated_at']) ? html_escape((string)$selectedContract['generated_at']) : '-'; ?></div>
          <div class="col-md-6"><span class="text-muted">Issued At:</span> <?php echo html_escape((string)($selectedContract['document_issued_at'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Doc Hash:</span> <code><?php echo html_escape((string)($selectedContract['final_document_hash'] ?? '-')); ?></code></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="mb-3">Pengesahan Sistem</h6>
        <?php foreach (['EMPLOYEE', 'COMPANY'] as $role): ?>
          <?php
            $approval = $role === 'EMPLOYEE' ? $employeeApproval : $companyApproval;
            $signature = $role === 'EMPLOYEE' ? $employeeSignature : $companySignature;
          ?>
          <div class="border rounded p-2 mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <div class="fw-semibold"><?php echo html_escape($role); ?></div>
              <span class="badge <?php echo (!empty($approval) && strtoupper((string)($approval['approval_status'] ?? '')) === 'APPROVED') ? 'bg-label-success' : 'bg-label-warning'; ?>">
                <?php echo !empty($approval) ? html_escape((string)($approval['approval_status'] ?? '-')) : 'PENDING'; ?>
              </span>
            </div>
            <div class="small text-muted">
              <?php if (!empty($approval)): ?>
                <?php echo html_escape((string)($approval['approver_name'] ?? '-')); ?><br>
                <?php echo html_escape((string)($approval['approved_at'] ?? '-')); ?>
              <?php else: ?>
                Belum ada pengesahan.
              <?php endif; ?>
            </div>
            <div class="small text-muted">
              Tanda tangan:
              <?php if (!empty($signature)): ?>
                sudah tersimpan<?php echo !empty($signature['signed_at']) ? ' • ' . html_escape((string)$signature['signed_at']) : ''; ?>
              <?php else: ?>
                belum ada
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!empty($signatureMap)): ?>
        <div class="small text-muted mt-3">Legacy signature masih tersimpan untuk arsip internal.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
      <div>
        <h6 class="mb-1">Persetujuan & Tanda Tangan Pegawai</h6>
        <div class="small text-muted">Pegawai dapat menyetujui isi kontrak dan menandatangani langsung dari portal pribadinya.</div>
      </div>
      <?php if (!empty($employeeSignature['signature_data'])): ?>
        <img src="<?php echo html_escape((string)$employeeSignature['signature_data']); ?>" alt="Signature Pegawai" class="signature-preview">
      <?php endif; ?>
    </div>

    <?php if (!$canEmployeeSign): ?>
      <div class="alert alert-warning mb-0">
        <?php if ((int)$selectedEmployeeId > 0 && (int)($employee['id'] ?? 0) !== (int)($currentUser['employee_id'] ?? 0) && !empty($employeeOptions)): ?>
          Preview superadmin tidak dapat dipakai untuk menandatangani. Login sebagai pegawai yang bersangkutan untuk melanjutkan.
        <?php elseif (!in_array($contractStatus, ['GENERATED', 'SIGNED'], true)): ?>
          Kontrak hanya bisa ditandatangani saat statusnya `GENERATED` atau `SIGNED`.
        <?php else: ?>
          Akun ini belum terhubung langsung ke profil pegawai yang boleh menandatangani kontrak.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <form method="post" action="<?php echo $signUrl; ?>" id="portal-contract-sign-form">
        <div class="row g-3 align-items-start">
          <div class="col-lg-7">
            <label class="form-label fw-semibold">Tanda Tangan Elektronik</label>
            <div class="signature-pad-wrap">
              <canvas id="employee-signature-pad" class="signature-pad"></canvas>
              <input type="hidden" name="signature_data" id="employee-signature-data">
              <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                <small class="text-muted">Gambar tanda tangan di area putih. Tanda tangan ini akan disimpan sebagai persetujuan elektronik pegawai.</small>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-employee-signature">Bersihkan</button>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="border rounded p-3 bg-light-subtle h-100">
              <div class="small text-muted mb-2">Status pegawai</div>
              <div class="fw-semibold mb-1"><?php echo !empty($employeeApproval) ? html_escape((string)($employeeApproval['approval_status'] ?? 'PENDING')) : 'PENDING'; ?></div>
              <div class="small text-muted mb-3">
                <?php echo !empty($employeeApproval['approved_at']) ? html_escape((string)$employeeApproval['approved_at']) : 'Belum ada persetujuan pegawai.'; ?>
              </div>

              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="agree-contract" name="agree_contract">
                <label class="form-check-label" for="agree-contract">
                  Saya telah membaca, memahami, dan menyetujui isi kontrak ini.
                </label>
              </div>

              <div class="mb-3">
                <label class="form-label">Catatan Persetujuan</label>
                <textarea name="approval_note" class="form-control" rows="4" placeholder="Opsional, misalnya konfirmasi sudah membaca dokumen."></textarea>
              </div>

              <button type="submit" class="btn btn-primary w-100">Simpan Persetujuan & Tanda Tangan</button>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($selectedContract['snapshot_lines'])): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-2">Snapshot Komponen Kontrak</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Tipe</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)$selectedContract['snapshot_lines'] as $line): ?>
          <tr>
            <td><?php echo html_escape((string)($line['component_code_snapshot'] ?? '')); ?></td>
            <td><?php echo html_escape((string)($line['component_name_snapshot'] ?? '')); ?></td>
            <td><?php echo html_escape((string)($line['component_type'] ?? '')); ?></td>
            <td class="text-end"><?php echo number_format((float)($line['amount'] ?? 0), 2, ',', '.'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($selectedContract['body_html'])): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
      <h6 class="mb-0">Dokumen Kontrak</h6>
      <?php if ($printUrl !== ''): ?>
      <a href="<?php echo $printUrl; ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">Cetak / Simpan PDF</a>
      <?php endif; ?>
    </div>
    <div class="border rounded p-3 contract-doc-preview">
      <?php echo (string)$selectedContract['body_html']; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<?php if ($canEmployeeSign): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('portal-contract-sign-form');
  var canvas = document.getElementById('employee-signature-pad');
  var hidden = document.getElementById('employee-signature-data');
  var clearBtn = document.getElementById('clear-employee-signature');

  if (!form || !canvas || !hidden || !clearBtn) {
    return;
  }

  var ctx = canvas.getContext('2d');
  var drawing = false;
  var hasStroke = false;

  function resizeCanvas() {
    var ratio = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(320, Math.floor(rect.width * ratio));
    canvas.height = Math.floor(220 * ratio);
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111827';
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, 220);
    hasStroke = false;
    hidden.value = '';
  }

  function pointFromEvent(event) {
    var rect = canvas.getBoundingClientRect();
    return {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top
    };
  }

  function beginDraw(event) {
    drawing = true;
    hasStroke = true;
    var point = pointFromEvent(event);
    ctx.beginPath();
    ctx.moveTo(point.x, point.y);
    event.preventDefault();
  }

  function draw(event) {
    if (!drawing) {
      return;
    }
    var point = pointFromEvent(event);
    ctx.lineTo(point.x, point.y);
    ctx.stroke();
    event.preventDefault();
  }

  function endDraw() {
    if (!drawing) {
      return;
    }
    drawing = false;
    hidden.value = hasStroke ? canvas.toDataURL('image/png') : '';
  }

  clearBtn.addEventListener('click', function () {
    resizeCanvas();
  });

  canvas.addEventListener('pointerdown', beginDraw);
  canvas.addEventListener('pointermove', draw);
  canvas.addEventListener('pointerup', endDraw);
  canvas.addEventListener('pointerleave', endDraw);
  canvas.addEventListener('pointercancel', endDraw);

  form.addEventListener('submit', function (event) {
    hidden.value = hasStroke ? canvas.toDataURL('image/png') : '';
    if (!hidden.value) {
      event.preventDefault();
      window.alert('Tanda tangan pegawai belum diisi.');
    }
  });

  resizeCanvas();
});
</script>
<?php endif; ?>
