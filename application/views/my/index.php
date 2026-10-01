<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$attendanceUrl = site_url('my/attendance' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$profileUrl = site_url('my/profile' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$scheduleUrl = site_url('my/schedule' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$payrollUrl = site_url('my/payroll' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$bonusUrl = site_url('my/bonus' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$bonusPenaltyGuideUrl = site_url('my/bonus?' . http_build_query(array_filter([
    'employee_id' => !empty($selectedEmployeeId) ? (int)$selectedEmployeeId : null,
    'tab' => 'penalties',
    'month' => date('Y-m'),
])));
$leaveUrl = $leave_url ?? site_url('my/leave-requests' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$attendanceAlerts = is_array($attendance_alerts ?? null) ? $attendance_alerts : [];
$revisionRule = is_array($revision_window_rule ?? null) ? $revision_window_rule : ['enabled' => true, 'days' => 7, 'mode' => 'ON'];
$revisionWindowEnabled = !empty($revisionRule['enabled']);
$revisionWindowDays = (int)($revision_window_days ?? 7);
$revisionNoticeText = (string)($revision_notice_text ?? '');
$bonusTargetSummary = is_array($bonus_target_summary ?? null) ? $bonus_target_summary : [];
$bonusTodayShortfall = (float)($bonusTargetSummary['today_shortfall_amount'] ?? 0);
$bonusMonthShortfall = (float)($bonusTargetSummary['monthly_shortfall_amount'] ?? 0);
$bonusTodayNotes = (array)($bonusTargetSummary['today_notes'] ?? []);
$bonusMonthNotes = (array)($bonusTargetSummary['monthly_notes'] ?? []);
?>

<div class="my-mobile-shell d-block d-md-none">
  <div class="my-mobile-header">
    <div>
      <h4 class="my-mobile-title"><i class="ri ri-contacts-book-3-line me-1 text-primary"></i>Portal Pegawai</h4>
      <p class="my-mobile-sub">Akses cepat absensi, bonus, jadwal, profil, dan payroll.</p>
    </div>
  </div>
  <div class="my-mobile-grid">
    <a href="<?php echo $attendanceUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-fingerprint-line"></i></span>
      <span class="my-mobile-tile-text">Absensi Saya</span>
    </a>
    <a href="<?php echo $scheduleUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-calendar-check-line"></i></span>
      <span class="my-mobile-tile-text">Jadwal Shift</span>
    </a>
    <a href="<?php echo $profileUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-user-3-line"></i></span>
      <span class="my-mobile-tile-text">Profil & Kontrak</span>
    </a>
    <a href="<?php echo $payrollUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-file-list-3-line"></i></span>
      <span class="my-mobile-tile-text">Slip Gaji</span>
    </a>
    <a href="<?php echo $bonusUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-medal-line"></i></span>
      <span class="my-mobile-tile-text">Bonus Saya</span>
    </a>
    <a href="<?php echo $bonusPenaltyGuideUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-alert-line"></i></span>
      <span class="my-mobile-tile-text">Master Penalti</span>
    </a>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 d-none d-md-flex">
  <div>
    <h4 class="mb-0">Portal Pegawai</h4>
    <small class="text-muted">Beranda personal untuk absensi, bonus, jadwal, kontrak, dan payroll.</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my'); ?>" class="d-flex gap-2">
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
<div class="alert alert-warning d-none d-md-block">
  Akun ini belum terhubung ke data pegawai. Hubungkan `auth_user.employee_id` ke `org_employee.id` untuk membuka fitur portal pegawai.
</div>
<div class="card border-0 shadow-sm d-block d-md-none">
  <div class="card-body p-3">
    <div class="d-flex align-items-start gap-2">
      <span class="my-mobile-tile-icon"><i class="ri ri-alert-line"></i></span>
      <div>
        <div class="fw-semibold text-dark mb-1">Akun belum terhubung ke pegawai</div>
        <div class="small text-muted">Hubungkan <code>auth_user.employee_id</code> ke <code>org_employee.id</code> untuk membuka fitur portal pegawai.</div>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<?php if (!empty($attendanceAlerts)): ?>
<div class="mb-3">
  <?php if ($revisionWindowEnabled && $revisionNoticeText !== ''): ?>
  <div class="alert alert-danger border-0 shadow-sm mb-2">
    <div class="fw-semibold mb-1">Perhatian</div>
    <div class="small"><?php echo html_escape($revisionNoticeText); ?></div>
  </div>
  <?php endif; ?>
  <?php foreach ($attendanceAlerts as $alert): ?>
    <?php
      $severity = strtolower(trim((string)($alert['severity'] ?? 'warning')));
      $alertClass = $severity === 'danger' ? 'alert-danger' : 'alert-warning';
    ?>
    <div class="alert <?php echo $alertClass; ?> border-0 shadow-sm mb-2">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-2">
        <div>
          <div class="fw-semibold mb-1"><?php echo html_escape((string)($alert['title'] ?? 'Notifikasi Absensi')); ?></div>
          <div class="small"><?php echo html_escape((string)($alert['message'] ?? '')); ?></div>
        </div>
        <a href="<?php echo $leaveUrl; ?>" class="btn btn-sm btn-outline-dark">Tindak Lanjut</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="row g-3 d-none d-md-flex">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <small class="text-muted d-block">Pegawai</small>
        <h5 class="mb-1"><?php echo html_escape((string)$employee['employee_name']); ?></h5>
        <div class="text-muted small"><?php echo html_escape((string)$employee['employee_code']); ?></div>
        <div class="mt-2">
          <span class="badge bg-label-primary"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?></span>
          <span class="badge bg-label-info"><?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="mb-2">Akses Cepat</h6>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?php echo $attendanceUrl; ?>" class="btn btn-outline-primary btn-sm">Absensi Saya</a>
          <a href="<?php echo $bonusUrl; ?>" class="btn btn-outline-secondary btn-sm">Bonus Saya</a>
          <a href="<?php echo $bonusPenaltyGuideUrl; ?>" class="btn btn-outline-secondary btn-sm">Master Penalti</a>
          <a href="<?php echo $profileUrl; ?>" class="btn btn-outline-secondary btn-sm">Profil & Kontrak</a>
          <a href="<?php echo $scheduleUrl; ?>" class="btn btn-outline-secondary btn-sm">Jadwal Shift</a>
          <a href="<?php echo $payrollUrl; ?>" class="btn btn-outline-secondary btn-sm">Slip Gaji</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h6 class="mb-1">Ringkasan Bonus Bulan Ini</h6>
            <div class="small text-muted">Baca cepat bonus estimasi, penalti, dan posisi target tanpa harus pindah dulu ke halaman detail.</div>
          </div>
          <a href="<?php echo $bonusUrl; ?>" class="btn btn-sm btn-outline-primary">Buka Detail Bonus</a>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded-3 p-3 h-100" style="background:linear-gradient(135deg,#fff7ef,#ffffff);">
              <div class="small text-muted text-uppercase">Kekurangan Target Hari Ini</div>
              <div class="fw-bold fs-5 <?php echo $bonusTodayShortfall > 0 ? 'text-danger' : 'text-success'; ?>">
                <?php echo $bonusTodayShortfall > 0 ? ('Rp ' . number_format($bonusTodayShortfall, 2, ',', '.')) : 'Aman'; ?>
              </div>
              <div class="small text-muted">
                <?php echo !empty($bonusTodayNotes) ? html_escape(implode(' | ', $bonusTodayNotes)) : 'Tidak ada target harian aktif yang sedang kurang hari ini.'; ?>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded-3 p-3 h-100" style="background:linear-gradient(135deg,#fff3f6,#ffffff);">
              <div class="small text-muted text-uppercase">Kekurangan Target Bulan Ini</div>
              <div class="fw-bold fs-5 <?php echo $bonusMonthShortfall > 0 ? 'text-danger' : 'text-success'; ?>">
                <?php echo $bonusMonthShortfall > 0 ? ('Rp ' . number_format($bonusMonthShortfall, 2, ',', '.')) : 'Aman'; ?>
              </div>
              <div class="small text-muted">
                <?php echo !empty($bonusMonthNotes) ? html_escape(implode(' | ', $bonusMonthNotes)) : 'Gerbang target bulanan bulan ini sedang aman.'; ?>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded-3 p-3 h-100" style="background:linear-gradient(135deg,#fff9f3,#ffffff);">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                  <div class="small text-muted text-uppercase">Panduan Penalti Bonus</div>
                  <div class="fw-bold fs-6 text-dark">Lihat daftar hal yang bisa mengurangi bonus</div>
                  <div class="small text-muted">Supaya pegawai bisa tahu mana yang otomatis, mana yang manual, dan mana yang butuh verifikasi admin.</div>
                </div>
                <a href="<?php echo $bonusPenaltyGuideUrl; ?>" class="btn btn-sm btn-outline-danger">Buka Master Penalti</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="d-block d-md-none">
  <div class="card border-0 shadow-sm mb-2">
    <div class="card-body p-3">
      <small class="text-muted d-block">Pegawai</small>
      <h6 class="mb-1"><?php echo html_escape((string)$employee['employee_name']); ?></h6>
      <div class="text-muted small mb-2"><?php echo html_escape((string)$employee['employee_code']); ?></div>
      <div class="d-flex flex-wrap gap-1">
        <span class="badge bg-label-primary"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?></span>
        <span class="badge bg-label-info"><?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></span>
      </div>
    </div>
  </div>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-3">
      <div class="fw-semibold mb-2">Akses Cepat</div>
      <div class="my-mobile-grid">
        <a href="<?php echo $attendanceUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-fingerprint-line"></i></span>
          <span class="my-mobile-tile-text">Absensi Saya</span>
        </a>
        <a href="<?php echo $scheduleUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-calendar-check-line"></i></span>
          <span class="my-mobile-tile-text">Jadwal Shift</span>
        </a>
        <a href="<?php echo $profileUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-user-3-line"></i></span>
          <span class="my-mobile-tile-text">Profil & Kontrak</span>
        </a>
        <a href="<?php echo $payrollUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-file-list-3-line"></i></span>
          <span class="my-mobile-tile-text">Slip Gaji</span>
        </a>
        <a href="<?php echo $bonusUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-medal-line"></i></span>
          <span class="my-mobile-tile-text">Bonus Saya</span>
        </a>
        <a href="<?php echo $bonusPenaltyGuideUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-alert-line"></i></span>
          <span class="my-mobile-tile-text">Master Penalti</span>
        </a>
      </div>
    </div>
  </div>
  <div class="card border-0 shadow-sm mt-2">
    <div class="card-body p-3">
      <div class="fw-semibold mb-2">Ringkasan Bonus Bulan Ini</div>
      <div class="small text-muted mb-2">Fokus portal pegawai di sini adalah target hari ini dan target bulan ini. Nominal bonus dibuka saat perusahaan mempublikasikannya.</div>
      <div class="row g-2">
        <div class="col-6">
          <div class="border rounded-3 p-2 h-100" style="background:linear-gradient(135deg,#fff7ef,#ffffff);">
            <div class="small text-muted">Kurang Hari Ini</div>
            <div class="fw-bold <?php echo $bonusTodayShortfall > 0 ? 'text-danger' : 'text-success'; ?>">
              <?php echo $bonusTodayShortfall > 0 ? ('Rp ' . number_format($bonusTodayShortfall, 0, ',', '.')) : 'Aman'; ?>
            </div>
          </div>
        </div>
        <div class="col-6">
          <div class="border rounded-3 p-2 h-100" style="background:linear-gradient(135deg,#fff3f6,#ffffff);">
            <div class="small text-muted">Kurang Bulan Ini</div>
            <div class="fw-bold <?php echo $bonusMonthShortfall > 0 ? 'text-danger' : 'text-success'; ?>">
              <?php echo $bonusMonthShortfall > 0 ? ('Rp ' . number_format($bonusMonthShortfall, 0, ',', '.')) : 'Aman'; ?>
            </div>
          </div>
        </div>
      </div>
      <a href="<?php echo $bonusUrl; ?>" class="btn btn-outline-primary btn-sm w-100 mt-3">Lihat Detail Bonus</a>
    </div>
  </div>
</div>
<?php endif; ?>
