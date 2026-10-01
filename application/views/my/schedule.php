<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$month = (string)($month ?? date('Y-m'));
$days = $days ?? [];
$summary = $summary ?? ['total_days' => 0, 'scheduled_days' => 0, 'unscheduled_days' => 0, 'holiday_days' => 0];
?>

<style>
  .my-schedule-table-wrap {
    max-height: 72vh;
    overflow: auto;
    border: 1px solid #ece3dc;
    border-radius: 16px;
    background: #fff;
  }
  .my-schedule-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #7f1d1d;
    color: #fff;
    white-space: nowrap;
  }
  .my-schedule-table th,
  .my-schedule-table td {
    font-size: 0.9rem;
    vertical-align: middle;
  }
  .my-schedule-row-today {
    background: #fff8e7;
  }
  .my-schedule-row-holiday {
    background: #fff2f2;
  }
  .my-schedule-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
  }
  .my-schedule-badge-on {
    background: #e8f7ee;
    color: #0f6b3c;
  }
  .my-schedule-badge-off {
    background: #f3f4f6;
    color: #4b5563;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Jadwal Shift Saya'); ?></h4>
    <small class="text-muted">Kalender jadwal kerja harian selama satu bulan untuk tiap pegawai.</small>
  </div>
  <form method="get" action="<?php echo site_url('my/schedule'); ?>" class="d-flex flex-wrap gap-2 align-items-end">
    <?php if (!empty($employeeOptions)): ?>
      <div>
        <label class="form-label mb-1 small text-muted">Pegawai</label>
        <select name="employee_id" class="form-select form-select-sm" style="min-width:260px">
          <option value="">Pilih Pegawai (Preview Superadmin)</option>
          <?php foreach ($employeeOptions as $o): ?>
            <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>>
              <?php echo html_escape($o['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div>
      <label class="form-label mb-1 small text-muted">Bulan</label>
      <input type="month" name="month" class="form-control form-control-sm" value="<?php echo html_escape($month); ?>">
    </div>
    <div>
      <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
    </div>
  </form>
</div>

<?php if (!$employee): ?>
  <div class="alert alert-warning">Data pegawai belum terhubung ke akun ini.</div>
<?php else: ?>
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block">Pegawai</small>
          <div class="fw-semibold"><?php echo html_escape((string)$employee['employee_name']); ?></div>
          <div class="small text-muted"><?php echo html_escape((string)$employee['employee_code']); ?></div>
          <div class="mt-2 d-flex flex-wrap gap-1">
            <span class="badge bg-label-primary"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?></span>
            <span class="badge bg-label-info"><?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block">Hari Dalam Bulan</small>
          <div class="fs-4 fw-bold"><?php echo (int)$summary['total_days']; ?></div>
          <div class="small text-muted"><?php echo html_escape(date('F Y', strtotime($month . '-01'))); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block">Hari Terjadwal</small>
          <div class="fs-4 fw-bold text-success"><?php echo (int)$summary['scheduled_days']; ?></div>
          <div class="small text-muted">Hari dengan shift aktif</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block">Hari Belum Diisi</small>
          <div class="fs-4 fw-bold text-warning"><?php echo (int)$summary['unscheduled_days']; ?></div>
          <div class="small text-muted">Libur nasional terdeteksi: <?php echo (int)$summary['holiday_days']; ?> hari</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body pb-2">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <div class="fw-semibold">Kalender Harian</div>
          <div class="small text-muted">Jadwal yang tampil berasal dari `att_shift_schedule` dan master shift aktif.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <span class="my-schedule-badge my-schedule-badge-on">Ada shift</span>
          <span class="my-schedule-badge my-schedule-badge-off">Belum ada shift</span>
        </div>
      </div>

      <div class="my-schedule-table-wrap">
        <table class="table table-sm table-hover mb-0 my-schedule-table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Hari</th>
              <th>Shift</th>
              <th>Jam</th>
              <th>Status</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($days as $day): ?>
              <?php
              $schedule = $day['schedule'] ?? null;
              $rowClass = '';
              if (!empty($day['is_today'])) {
                  $rowClass = 'my-schedule-row-today';
              } elseif (!empty($day['is_holiday'])) {
                  $rowClass = 'my-schedule-row-holiday';
              }
              $shiftLabel = '-';
              $timeLabel = '-';
              if (!empty($schedule)) {
                  $shiftLabel = trim((string)(($schedule['shift_code'] ?? '') . ' - ' . ($schedule['shift_name'] ?? '')));
                  $timeLabel = trim((string)(($schedule['start_time'] ?? '-') . ' s/d ' . ($schedule['end_time'] ?? '-')));
                  if (!empty($schedule['is_overnight'])) {
                      $timeLabel .= ' (overnight)';
                  }
              }
              ?>
              <tr class="<?php echo $rowClass; ?>">
                <td class="fw-semibold"><?php echo html_escape((string)$day['day_label']); ?></td>
                <td><?php echo html_escape((string)$day['dow_label']); ?></td>
                <td><?php echo html_escape($shiftLabel !== '' ? $shiftLabel : '-'); ?></td>
                <td><?php echo html_escape($timeLabel); ?></td>
                <td>
                  <?php if (!empty($schedule)): ?>
                    <span class="my-schedule-badge my-schedule-badge-on">Terjadwal</span>
                  <?php else: ?>
                    <span class="my-schedule-badge my-schedule-badge-off"><?php echo !empty($day['is_holiday']) ? 'Libur / belum diisi' : 'Belum diisi'; ?></span>
                  <?php endif; ?>
                </td>
                <td><?php echo html_escape((string)($schedule['notes'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
