<?php
$row = $row ?? [];
$linkedUsers = $linked_users ?? [];
$schedules = $schedules ?? [];
$attDailyRows = $att_daily_rows ?? [];
$entity = $entity ?? 'org-employee';
$compensation = $compensation ?? [];
$basicSalary = (float)($compensation['basic_salary'] ?? 0);
$positionAllowance = (float)($compensation['position_allowance'] ?? 0);
$objectiveAllowance = (float)($compensation['objective_allowance'] ?? 0);
$mealRate = (float)($compensation['meal_rate'] ?? 0);
$compensationSource = (string)($compensation['source'] ?? 'MISSING');
$contractNumber = (string)($compensation['contract_number'] ?? '');
$takeHomeWithoutMeal = $basicSalary + $positionAllowance + $objectiveAllowance;
$mealMonthlyEstimate = $mealRate * 26;
$takeHomeWithMeal = $takeHomeWithoutMeal + $mealMonthlyEstimate;
?>

<style>
  .employee-comp-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
  }
  .employee-comp-summary-card {
    border: 1px solid #e7d8cd;
    border-radius: 14px;
    padding: 0.9rem 1rem;
    background: linear-gradient(180deg, #fffdfb 0%, #fff6ef 100%);
  }
  .employee-comp-summary-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #8a6d5c;
    margin-bottom: 0.3rem;
  }
  .employee-comp-summary-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #7b1220;
    line-height: 1.2;
  }
  .employee-comp-summary-note {
    margin-top: 0.3rem;
    font-size: 0.75rem;
    color: #8a776d;
  }
  @media (max-width: 767.98px) {
    .employee-comp-summary {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Detail Pegawai'); ?></h4>
    <small class="text-muted">ID Pegawai: <?php echo (int)($row['id'] ?? 0); ?></small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('master/' . $entity); ?>" class="btn btn-outline-secondary">Kembali</a>
    <a href="<?php echo site_url('master/' . $entity . '/edit/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-primary">Edit Pegawai</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Data Utama Pegawai</h6>
        <div class="row g-2">
          <div class="col-md-6"><small class="text-muted d-block">Kode Pegawai</small><div class="fw-semibold"><?php echo html_escape((string)($row['employee_code'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">NIP</small><div class="fw-semibold"><?php echo html_escape((string)($row['employee_nip'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Nama Pegawai</small><div class="fw-semibold"><?php echo html_escape((string)($row['employee_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Gender</small><div><?php echo html_escape((string)($row['gender'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Tanggal Lahir</small><div><?php echo html_escape((string)($row['birth_date'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Tanggal Bergabung</small><div><?php echo html_escape((string)($row['join_date'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Divisi</small><div><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Jabatan</small><div><?php echo html_escape((string)($row['position_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Status Kerja</small><div><?php echo html_escape((string)($row['employment_status'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small class="text-muted d-block">Status Aktif</small><div><?php echo ((int)($row['is_active'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif'; ?></div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
          <h6 class="mb-0">Komponen Kompensasi</h6>
          <span class="badge <?php echo $compensationSource === 'CONTRACT' ? 'text-bg-success' : 'text-bg-warning'; ?>">
            <?php echo $compensationSource === 'CONTRACT' ? 'Kontrak aktif' : 'Belum ada kontrak'; ?>
          </span>
        </div>
        <?php if ($contractNumber !== ''): ?>
          <div class="small text-muted mb-3">Sumber nominal: <?php echo html_escape($contractNumber); ?>. Master Pegawai hanya menyimpan cache sinkron.</div>
        <?php else: ?>
          <div class="small text-warning mb-3">Belum ada kontrak aktif beserta snapshot kompensasi. Nominal master tidak dipakai sebagai sumber perhitungan.</div>
        <?php endif; ?>
        <div class="employee-comp-summary">
          <div class="employee-comp-summary-card">
            <div class="employee-comp-summary-label">Take Home Pay Tanpa Uang Makan</div>
            <div class="employee-comp-summary-value">Rp <?php echo number_format($takeHomeWithoutMeal, 2, ',', '.'); ?></div>
            <div class="employee-comp-summary-note">Gaji pokok + tunjangan jabatan + tunjangan objektif.</div>
          </div>
          <div class="employee-comp-summary-card">
            <div class="employee-comp-summary-label">Estimasi THP Dengan Uang Makan</div>
            <div class="employee-comp-summary-value">Rp <?php echo number_format($takeHomeWithMeal, 2, ',', '.'); ?></div>
            <div class="employee-comp-summary-note">THP tetap + uang makan harian x 26 hari.</div>
          </div>
        </div>
        <div class="small mb-2"><span class="text-muted">Gaji Pokok:</span> <strong><?php echo number_format($basicSalary, 2, ',', '.'); ?></strong></div>
        <div class="small mb-2"><span class="text-muted">Tunjangan Jabatan:</span> <strong><?php echo number_format($positionAllowance, 2, ',', '.'); ?></strong></div>
        <div class="small mb-2"><span class="text-muted">Tunjangan Objektif/Lain:</span> <strong><?php echo number_format($objectiveAllowance, 2, ',', '.'); ?></strong></div>
        <div class="small mb-2"><span class="text-muted">Uang Makan:</span> <strong><?php echo number_format($mealRate, 2, ',', '.'); ?> / hari</strong> <span class="text-muted">| Estimasi 26 hari: <?php echo number_format($mealMonthlyEstimate, 2, ',', '.'); ?></span></div>
        <div class="small mb-2"><span class="text-muted">Tarif Lembur:</span> <strong>Mengikuti Master Standar Lembur</strong></div>
        <div class="small text-muted">Ringkasan take home pay ini belum memasukkan lembur, potongan, bonus, atau komponen payroll periodik lainnya. Tarif lembur dipilih per entri atau dari standar default policy untuk mode otomatis.</div>
        <hr>
        <div class="small mb-2"><span class="text-muted">Bank:</span> <?php echo html_escape((string)($row['bank_display_name'] ?? $row['bank_name'] ?? '-')); ?></div>
        <div class="small mb-2"><span class="text-muted">No Rekening:</span> <?php echo html_escape((string)($row['bank_account_no'] ?? '-')); ?></div>
        <div class="small"><span class="text-muted">Nama Rekening:</span> <?php echo html_escape((string)($row['bank_account_name'] ?? '-')); ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-3">Akun Login Terkait</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead><tr><th>ID User</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($linkedUsers)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Belum ada akun user yang terhubung.</td></tr>
          <?php else: ?>
            <?php foreach ($linkedUsers as $u): ?>
              <tr>
                <td><?php echo (int)$u['id']; ?></td>
                <td><?php echo html_escape((string)$u['username']); ?></td>
                <td><?php echo html_escape((string)($u['email'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($u['roles'] ?? '-')); ?></td>
                <td><?php echo ((int)($u['is_active'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif'; ?></td>
                <td><?php echo html_escape((string)($u['last_login_at'] ?? '-')); ?></td>
                <td>
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('users/edit/' . (int)$u['id']); ?>">Edit</a>
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('users/permissions/' . (int)$u['id']); ?>">Izin</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Jadwal Shift Terakhir (31 hari)</h6>
        <div class="table-responsive" style="max-height: 360px; overflow:auto;">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Tanggal</th><th>Shift</th><th>Nama Shift</th><th>Catatan</th></tr></thead>
            <tbody>
              <?php if (empty($schedules)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada jadwal shift.</td></tr>
              <?php else: ?>
                <?php foreach ($schedules as $s): ?>
                  <tr>
                    <td><?php echo html_escape((string)$s['schedule_date']); ?></td>
                    <td><?php echo html_escape((string)($s['shift_code'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($s['shift_name'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($s['notes'] ?? '-')); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Rekap Absensi Harian Terakhir (31 hari)</h6>
        <div class="table-responsive" style="max-height: 360px; overflow:auto;">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Tanggal</th><th>Status</th><th>Checkin</th><th>Checkout</th><th class="text-end">Telat</th><th class="text-end">Kerja</th></tr></thead>
            <tbody>
              <?php if (empty($attDailyRows)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada rekap absensi.</td></tr>
              <?php else: ?>
                <?php foreach ($attDailyRows as $d): ?>
                  <tr>
                    <td><?php echo html_escape((string)$d['attendance_date']); ?></td>
                    <td><?php echo html_escape((string)$d['attendance_status']); ?></td>
                    <td><?php echo html_escape((string)($d['checkin_at'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($d['checkout_at'] ?? '-')); ?></td>
                    <td class="text-end"><?php echo (int)($d['late_minutes'] ?? 0); ?></td>
                    <td class="text-end"><?php echo (int)($d['work_minutes'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
