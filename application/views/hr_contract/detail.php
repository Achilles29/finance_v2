<?php
$row = $row ?? [];
$approvalMap = $approval_map ?? [];
$signatureMap = $signature_map ?? [];
$canEdit = !empty($can_edit);
$currentUser = $current_user ?? [];
$ctx = $ctx ?? 'finance';
$status = strtoupper((string)($row['status'] ?? 'DRAFT'));

$badgeClass = 'bg-label-secondary';
if ($status === 'DRAFT') $badgeClass = 'bg-label-warning';
if ($status === 'GENERATED') $badgeClass = 'bg-label-info';
if ($status === 'SIGNED') $badgeClass = 'bg-label-primary';
if ($status === 'ACTIVE') $badgeClass = 'bg-label-success';
if ($status === 'EXPIRED') $badgeClass = 'bg-label-dark';
if ($status === 'TERMINATED' || $status === 'CANCELLED') $badgeClass = 'bg-label-danger';

$approvalEmployee = $approvalMap['EMPLOYEE'] ?? null;
$approvalCompany = $approvalMap['COMPANY'] ?? null;
$hasCompleteSignoff = !empty($approvalEmployee)
    && !empty($approvalCompany)
    && (($approvalEmployee['approval_status'] ?? '') === 'APPROVED')
    && (($approvalCompany['approval_status'] ?? '') === 'APPROVED')
    && !empty($signatureMap['EMPLOYEE']['signature_data'])
    && !empty($signatureMap['COMPANY']['signature_data']);
$isMigrationRecord = strpos((string)($row['contract_number'] ?? ''), 'MIG/') === 0;
$mealMonthlyEstimate = (float)($row['meal_rate'] ?? 0) * 26;
$snapshotLines = array_values(array_filter((array)($row['snapshot_lines'] ?? []), static function (array $line): bool {
    return !in_array(strtoupper((string)($line['component_code_snapshot'] ?? '')), ['LEMBUR_PER_JAM', 'OVERTIME_RATE'], true);
}));
$hasLegacyOvertimeSnapshot = count($snapshotLines) !== count((array)($row['snapshot_lines'] ?? []));

$canGenerate = $canEdit && $status === 'DRAFT';
$canActivate = $canEdit && in_array($status, ['SIGNED', 'GENERATED'], true);
$canExpire = $canEdit && in_array($status, ['ACTIVE', 'SIGNED', 'GENERATED'], true);
$canTerminate = $canEdit && in_array($status, ['ACTIVE', 'SIGNED', 'GENERATED'], true);
$canCancel = $canEdit && in_array($status, ['DRAFT', 'GENERATED', 'SIGNED'], true);

$verifyUrl = !empty($row['verification_token']) ? site_url('hr-contracts/verify/' . (string)$row['verification_token']) : '';
?>

<style>
  .hr-contract-detail .card {
    border: 0;
    box-shadow: 0 2px 12px rgba(31, 41, 55, 0.08);
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

<div class="hr-contract-detail">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Detail Kontrak'); ?></h4>
    <small class="text-muted">ID #<?php echo (int)($row['id'] ?? 0); ?></small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?php echo site_url('hr/contracts?' . http_build_query(['ctx' => $ctx])); ?>">Kembali</a>
    <a class="btn btn-outline-dark" href="<?php echo site_url('hr-contracts/print/' . (int)($row['id'] ?? 0) . '?' . http_build_query(['ctx' => $ctx])); ?>" target="_blank">Cetak</a>
    <?php if ($verifyUrl !== ''): ?>
      <a class="btn btn-outline-primary" href="<?php echo $verifyUrl; ?>" target="_blank">Verifikasi TTE</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <div class="small text-muted">Nomor Kontrak</div>
            <div class="fw-semibold fs-5"><?php echo html_escape((string)($row['contract_number'] ?? '-')); ?></div>
          </div>
          <span class="badge <?php echo $badgeClass; ?>"><?php echo html_escape($status); ?></span>
        </div>

        <?php if ($isMigrationRecord): ?>
          <div class="alert alert-info py-2 small mb-3">
            <strong>Baseline migrasi operasional.</strong> Record ini dipakai agar absensi dan payroll memiliki sumber nominal aktif yang tunggal. Tidak ada TTE formal yang dibuat-buat; buat kontrak formal pada pembaruan berikutnya.
          </div>
        <?php elseif ($status === 'ACTIVE'): ?>
          <div class="small mb-3 <?php echo $hasCompleteSignoff ? 'text-success' : 'text-warning'; ?>">
            Pengesahan formal: <strong><?php echo $hasCompleteSignoff ? 'lengkap (EMPLOYEE + COMPANY)' : 'belum lengkap'; ?></strong>
          </div>
        <?php endif; ?>

        <div class="row g-2 small">
          <div class="col-md-6"><span class="text-muted">Pegawai:</span> <?php echo html_escape((string)($row['employee_name'] ?? '-')); ?> (<?php echo html_escape((string)($row['employee_code'] ?? '-')); ?>)</div>
          <div class="col-md-6"><span class="text-muted">NIP:</span> <?php echo html_escape((string)($row['employee_nip'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Divisi:</span> <?php echo html_escape((string)($row['division_snapshot'] ?: ($row['division_name'] ?? '-'))); ?></div>
          <div class="col-md-6"><span class="text-muted">Jabatan:</span> <?php echo html_escape((string)($row['position_snapshot'] ?: ($row['position_name'] ?? '-'))); ?></div>
          <div class="col-md-6"><span class="text-muted">Jenis:</span> <?php echo html_escape((string)($row['contract_type'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Periode:</span> <?php echo html_escape((string)($row['start_date'] ?? '-')); ?> s/d <?php echo html_escape((string)($row['end_date'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Generated:</span> <?php echo !empty($row['generated_at']) ? html_escape((string)$row['generated_at']) : '-'; ?></div>
          <div class="col-md-6"><span class="text-muted">Generated By:</span> <?php echo html_escape((string)($row['generated_by_username'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Issued At:</span> <?php echo html_escape((string)($row['document_issued_at'] ?? '-')); ?></div>
          <div class="col-md-6"><span class="text-muted">Doc Hash:</span> <code><?php echo html_escape((string)($row['final_document_hash'] ?? '-')); ?></code></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-2">Kompensasi Snapshot</h6>
        <div class="small mb-1"><span class="text-muted">Gaji Pokok:</span> <?php echo number_format((float)($row['basic_salary'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small mb-1"><span class="text-muted">Tunjangan Jabatan:</span> <?php echo number_format((float)($row['position_allowance'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small mb-1"><span class="text-muted">Tunjangan Lain:</span> <?php echo number_format((float)($row['other_allowance'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small mb-1"><span class="text-muted">Uang Makan:</span> <?php echo number_format((float)($row['meal_rate'] ?? 0), 2, ',', '.'); ?> / hari <span class="text-muted">(estimasi 26 hari: <?php echo number_format($mealMonthlyEstimate, 2, ',', '.'); ?>)</span></div>
        <div class="small mb-3"><span class="text-muted">Lembur:</span> mengikuti Master Standar Lembur, di luar komponen kompensasi kontrak.</div>

        <?php if ($canGenerate): ?>
          <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$row['id'] . '/generate?' . http_build_query(['ctx' => $ctx])); ?>" class="mb-2">
            <button class="btn btn-primary btn-sm w-100" type="submit">Generate No Kontrak + Snapshot</button>
          </form>
        <?php endif; ?>

        <?php if ($canActivate): ?>
          <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$row['id'] . '/transition/ACTIVE?' . http_build_query(['ctx' => $ctx])); ?>" class="mb-2">
            <button class="btn btn-success btn-sm w-100" type="submit">Set ACTIVE</button>
          </form>
        <?php endif; ?>

        <?php if ($canExpire): ?>
          <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$row['id'] . '/transition/EXPIRED?' . http_build_query(['ctx' => $ctx])); ?>" class="mb-2">
            <button class="btn btn-dark btn-sm w-100" type="submit">Set EXPIRED</button>
          </form>
        <?php endif; ?>

        <?php if ($canTerminate): ?>
          <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$row['id'] . '/transition/TERMINATED?' . http_build_query(['ctx' => $ctx])); ?>" class="mb-2" onsubmit="return confirm('Terminate kontrak ini?');">
            <button class="btn btn-danger btn-sm w-100" type="submit">Set TERMINATED</button>
          </form>
        <?php endif; ?>

        <?php if ($canCancel): ?>
          <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$row['id'] . '/transition/CANCELLED?' . http_build_query(['ctx' => $ctx])); ?>" onsubmit="return confirm('Cancel kontrak ini?');">
            <button class="btn btn-outline-danger btn-sm w-100" type="submit">Set CANCELLED</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-12">
    <div class="card">
      <div class="card-body">
        <h6 class="mb-2">Pengesahan Sistem (TTE)</h6>
        <div class="text-muted small mb-3">Kontrak sah berdasarkan pengesahan sistem (approval EMPLOYEE + COMPANY) dan verifikasi hash + QR. Tanda tangan gambar manual tidak dipakai sebagai acuan utama.</div>

        <div class="row g-3">
          <?php foreach (['EMPLOYEE', 'COMPANY'] as $role): ?>
            <?php $approval = $role === 'EMPLOYEE' ? $approvalEmployee : $approvalCompany; ?>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="fw-semibold"><?php echo html_escape($role); ?></div>
                  <span class="badge <?php echo (!empty($approval) && ($approval['approval_status'] ?? '') === 'APPROVED') ? 'bg-label-success' : 'bg-label-warning'; ?>">
                    <?php echo !empty($approval) ? html_escape((string)$approval['approval_status']) : 'PENDING'; ?>
                  </span>
                </div>

                <?php if (!empty($approval)): ?>
                  <div class="small text-muted mb-2">Oleh: <?php echo html_escape((string)($approval['approver_name'] ?? '-')); ?> (<?php echo html_escape((string)($approval['approved_at'] ?? '-')); ?>)</div>
                <?php else: ?>
                  <div class="small text-muted mb-2">Belum ada pengesahan.</div>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                  <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$row['id'] . '/approve?' . http_build_query(['ctx' => $ctx])); ?>" class="row g-2 align-items-end">
                    <input type="hidden" name="approver_role" value="<?php echo html_escape($role); ?>">
                    <div class="col-md-5">
                      <label class="form-label mb-1">Nama Approver</label>
                      <input type="text" name="approver_name" class="form-control form-control-sm" value="<?php echo html_escape((string)($currentUser['username'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label mb-1">Aksi</label>
                      <select name="approval_action" class="form-select form-select-sm">
                        <option value="APPROVED">APPROVED</option>
                        <option value="REVOKED">REVOKED</option>
                      </select>
                    </div>
                    <div class="col-md-3 d-grid">
                      <button type="submit" class="btn btn-sm btn-outline-primary">Simpan</button>
                    </div>
                    <div class="col-12">
                      <input type="text" name="approval_note" class="form-control form-control-sm" placeholder="Catatan approval (opsional)">
                    </div>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!empty($signatureMap)): ?>
          <div class="mt-3 pt-2 border-top small text-muted">
            Legacy signature data masih tersimpan untuk arsip, namun tidak dijadikan acuan validasi utama dokumen.
          </div>
        <?php endif; ?>

        <div class="mt-4 pt-3 border-top">
          <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
            <div>
              <h6 class="mb-1">Tanda Tangan Perusahaan</h6>
              <div class="text-muted small">Dipakai untuk pengesahan pihak perusahaan. Tanda tangan ini akan tersimpan dengan role <code>COMPANY</code>.</div>
            </div>
            <?php if (!empty($signatureMap['COMPANY']['signature_data'])): ?>
              <img src="<?php echo html_escape((string)$signatureMap['COMPANY']['signature_data']); ?>" alt="Signature Company" class="signature-preview">
            <?php endif; ?>
          </div>

          <?php if (!$canEdit || !in_array($status, ['GENERATED', 'SIGNED'], true)): ?>
            <div class="alert alert-warning mb-0">
              <?php if (!$canEdit): ?>
                Akun ini belum memiliki izin untuk menandatangani kontrak sebagai perusahaan.
              <?php else: ?>
                Kontrak hanya bisa ditandatangani pihak perusahaan saat statusnya `GENERATED` atau `SIGNED`.
              <?php endif; ?>
            </div>
          <?php else: ?>
            <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$row['id'] . '/sign?' . http_build_query(['ctx' => $ctx])); ?>" id="company-contract-sign-form">
              <input type="hidden" name="signer_role" value="COMPANY">
              <div class="row g-3 align-items-start">
                <div class="col-lg-7">
                  <label class="form-label fw-semibold">Tanda Tangan Elektronik Perusahaan</label>
                  <div class="signature-pad-wrap">
                    <canvas id="company-signature-pad" class="signature-pad"></canvas>
                    <input type="hidden" name="signature_data" id="company-signature-data">
                    <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                      <small class="text-muted">Gambar tanda tangan di area putih. Tanda tangan ini akan disimpan sebagai pengesahan elektronik perusahaan.</small>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-company-signature">Bersihkan</button>
                    </div>
                  </div>
                </div>
                <div class="col-lg-5">
                  <div class="border rounded p-3 bg-light-subtle h-100">
                    <div class="small text-muted mb-2">Penandatangan</div>
                    <input type="text" name="signer_name" class="form-control form-control-sm mb-3" value="<?php echo html_escape((string)($currentUser['username'] ?? 'SYSTEM')); ?>">
                    <div class="small text-muted mb-3">Nama ini akan disimpan pada data tanda tangan perusahaan.</div>
                    <button type="submit" class="btn btn-primary w-100">Simpan Tanda Tangan Perusahaan</button>
                  </div>
                </div>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($snapshotLines)): ?>
<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-2">Detail Snapshot Komponen</h6>
    <?php if ($hasLegacyOvertimeSnapshot): ?>
      <div class="small text-muted mb-2">Tarif lembur lama tidak ditampilkan karena lembur sekarang selalu mengikuti Master Standar Lembur.</div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($snapshotLines as $line): ?>
          <tr>
            <td><?php echo html_escape((string)$line['component_code_snapshot']); ?></td>
            <td><?php echo html_escape((string)$line['component_name_snapshot']); ?></td>
            <td><?php echo html_escape((string)$line['component_type']); ?></td>
            <td class="text-end"><?php echo number_format((float)$line['amount'], 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php $contractBodyHtml = (string)($row['body_html_rendered'] ?? $row['body_html'] ?? ''); ?>
<?php if ($contractBodyHtml !== ''): ?>
<div class="card">
  <div class="card-body">
    <h6 class="mb-2">Dokumen Kontrak</h6>
    <div class="border rounded p-3" style="max-height: 540px; overflow:auto; background:#fff;">
      <?php echo $contractBodyHtml; ?>
    </div>
  </div>
</div>
<?php endif; ?>
</div>

<?php if ($canEdit && in_array($status, ['GENERATED', 'SIGNED'], true)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('company-contract-sign-form');
  var canvas = document.getElementById('company-signature-pad');
  var hidden = document.getElementById('company-signature-data');
  var clearBtn = document.getElementById('clear-company-signature');

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
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
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
    if (!drawing) return;
    var point = pointFromEvent(event);
    ctx.lineTo(point.x, point.y);
    ctx.stroke();
    event.preventDefault();
  }

  function endDraw() {
    if (!drawing) return;
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
      window.alert('Tanda tangan perusahaan belum diisi.');
    }
  });

  resizeCanvas();
});
</script>
<?php endif; ?>
