<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$policy = $policy ?? [];
$today = $today ?? date('Y-m-d');
$todaySchedule = $today_schedule ?? null;
$todayPresence = $today_presence ?? ['checkin_count'=>0,'checkout_count'=>0,'last_checkin_at'=>null,'last_checkout_at'=>null];
$phAutoResult = $ph_auto_result ?? null;
$isAutoPh = in_array(strtoupper(trim((string)($todaySchedule['shift_code'] ?? ''))), ['PH', 'PHB'], true)
    && strtoupper((string)($policy['ph_attendance_mode'] ?? 'AUTO_PRESENT')) === 'AUTO_PRESENT';
$pendingPeerFeedback = $pending_peer_feedback ?? [];
$locationOptions = $location_options ?? [];
$defaultLocationId = (int)($default_location_id ?? 0);
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$statusOptions = $status_options ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $pg, $selectedEmployeeId) {
    $base = [
        'employee_id' => $selectedEmployeeId ?: '',
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'date_start' => $filters['date_start'] ?? '',
        'date_end' => $filters['date_end'] ?? '',
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }
    $items = [1];
    $start = max(2, $page - 1);
    $end = min($totalPages - 1, $page + 1);
    if ($start > 2) {
        $items[] = '...';
    }
    for ($i = $start; $i <= $end; $i++) {
        $items[] = $i;
    }
    if ($end < $totalPages - 1) {
        $items[] = '...';
    }
    $items[] = $totalPages;
    return $items;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Absensi Saya'); ?></h4>
    <small class="text-muted">Check-in/check-out personal dan rekap kehadiran harian.</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/attendance'); ?>" class="d-flex gap-2">
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
  Data pegawai tidak ditemukan pada akun ini. Hubungkan dulu `auth_user.employee_id`.
</div>
<?php else: ?>
<?php if (is_array($phAutoResult) && empty($phAutoResult['ok'])): ?>
<div class="alert alert-danger border-0 shadow-sm">
  <div class="fw-semibold">Shift PH belum dapat ditandai hadir</div>
  <div class="small"><?php echo html_escape((string)($phAutoResult['message'] ?? 'Saldo PH tidak cukup.')); ?></div>
</div>
<?php endif; ?>
<?php if (!empty($pendingPeerFeedback)): ?>
<div class="alert alert-warning border-0 shadow-sm">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <div class="fw-semibold">Masih ada penilaian 360 hari ini</div>
      <div class="small">Ada <?php echo count($pendingPeerFeedback); ?> rekan kerja yang hadir hari ini dan belum Anda nilai. Penilaian ini membantu admin membaca kualitas kerja tim secara lebih adil.</div>
    </div>
    <a href="<?php echo site_url('my/bonus?tab=360&date=' . urlencode($today) . (!empty($selectedEmployeeId) ? ('&employee_id=' . (int)$selectedEmployeeId) : '')); ?>" class="btn btn-sm btn-warning">
      Buka Penilaian 360
    </a>
  </div>
</div>
<?php endif; ?>
<div class="row g-3 mb-3">
  <div class="col-md-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <small class="text-muted d-block">Pegawai</small>
        <h5 class="mb-1"><?php echo html_escape((string)$employee['employee_name']); ?></h5>
        <div class="text-muted small mb-2"><?php echo html_escape((string)$employee['employee_code']); ?></div>
        <div class="d-flex gap-2 flex-wrap">
          <span class="badge bg-label-primary"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?></span>
          <span class="badge bg-label-info"><?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <small class="text-muted d-block">Jadwal Hari Ini (<?php echo html_escape($today); ?>)</small>
        <?php if ($todaySchedule): ?>
          <div class="fw-semibold mb-1"><?php echo html_escape((string)$todaySchedule['shift_code']); ?> - <?php echo html_escape((string)$todaySchedule['shift_name']); ?></div>
          <div class="small text-muted mb-2">Jam: <?php echo html_escape((string)$todaySchedule['start_time']); ?> s/d <?php echo html_escape((string)$todaySchedule['end_time']); ?></div>
        <?php else: ?>
          <div class="text-muted mb-2">Belum ada jadwal shift.</div>
        <?php endif; ?>

        <?php if ($isAutoPh): ?>
        <div class="alert <?php echo !empty($phAutoResult['recorded']) ? 'alert-success' : 'alert-info'; ?> mb-0" role="status">
          <div class="fw-semibold"><?php echo !empty($phAutoResult['recorded']) ? 'Presensi PH sudah tercatat' : 'Jadwal PH — presensi otomatis'; ?></div>
          <div class="small mt-1"><?php echo html_escape((string)($phAutoResult['message'] ?? 'Presensi PH diproses saat halaman ini dibuka.')); ?></div>
          <div class="small mt-1">Tidak perlu menekan Check-in/Check-out atau mengaktifkan GPS untuk PH otomatis. Kehadiran dan penggunaan satu jatah PH dapat dilihat di riwayat.</div>
        </div>
        <?php else: ?>
        <div class="small mb-2">
          <span class="me-3">Check-in: <strong><?php echo $todayPresence['last_checkin_at'] ? html_escape((string)$todayPresence['last_checkin_at']) : '-'; ?></strong></span>
          <span>Check-out: <strong><?php echo $todayPresence['last_checkout_at'] ? html_escape((string)$todayPresence['last_checkout_at']) : '-'; ?></strong></span>
        </div>
        <div class="small mb-2">
          <span class="badge bg-label-secondary" id="gps-status">GPS: belum dibaca</span>
        </div>

        <div class="my-attendance-check-actions">
          <?php if (empty($locationOptions)): ?>
            <div class="alert alert-warning mb-0 py-2">Lokasi absensi belum tersedia. Hubungi admin untuk setup `Master Lokasi Absensi`.</div>
          <?php else: ?>
          <form class="my-attendance-check-form" method="post" action="<?php echo site_url('my/attendance/mark' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>">
            <input type="hidden" name="event_type" value="CHECKIN">
            <input type="hidden" name="latitude" class="gps-lat" value="">
            <input type="hidden" name="longitude" class="gps-lon" value="">
            <div class="mb-2">
              <select name="location_id" class="form-select form-select-sm my-attendance-location" required>
                <?php foreach ($locationOptions as $loc): ?>
                  <option value="<?php echo (int)$loc['value']; ?>" <?php echo ((int)$loc['value'] === $defaultLocationId) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$loc['label']); ?><?php echo ((int)($loc['is_default'] ?? 0) === 1) ? ' (Default)' : ''; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-success btn-sm" type="submit">Check-in</button>
          </form>
          <form class="my-attendance-check-form" method="post" action="<?php echo site_url('my/attendance/mark' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>">
            <input type="hidden" name="event_type" value="CHECKOUT">
            <input type="hidden" name="latitude" class="gps-lat" value="">
            <input type="hidden" name="longitude" class="gps-lon" value="">
            <div class="mb-2">
              <select name="location_id" class="form-select form-select-sm my-attendance-location" required>
                <?php foreach ($locationOptions as $loc): ?>
                  <option value="<?php echo (int)$loc['value']; ?>" <?php echo ((int)$loc['value'] === $defaultLocationId) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$loc['label']); ?><?php echo ((int)($loc['is_default'] ?? 0) === 1) ? ' (Default)' : ''; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-danger btn-sm" type="submit">Check-out</button>
          </form>
          <?php endif; ?>
        </div>

        <div class="form-text mt-2">
          Window check-in mengikuti jadwal shift. Tutup check-out mengikuti batas menit sesudah shift dari pengaturan absensi.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('my/attendance'); ?>" class="row g-2 align-items-end">
      <?php if ($selectedEmployeeId): ?>
      <input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>">
      <?php endif; ?>
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Shift/Catatan"></div>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['status'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1 d-grid"><button type="submit" class="btn btn-primary">Filter</button></div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Shift</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Status</th>
          <th class="text-end">Telat (mnt)</th>
          <th class="text-end">Pulang Cepat</th>
          <th class="text-end">Kerja (mnt)</th>
          <th class="text-end">Lembur (mnt)</th>
          <th class="text-end">Basic</th>
          <th class="text-end">Tunjangan</th>
          <th class="text-end">Uang Makan</th>
          <th class="text-end">Lembur Rp</th>
          <th class="text-end">Pot. Telat</th>
          <th class="text-end">Pot. Alpha</th>
          <th class="text-end">Adj. (+)</th>
          <th class="text-end">Adj. (-)</th>
          <th class="text-end">Adj. Net</th>
          <th class="text-end">Gross</th>
          <th class="text-end">Net</th>
          <th class="text-end">THP Harian</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="22" class="text-center text-muted py-4">Belum ada data absensi.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <?php $isClosed = !empty($r['checkout_at']); ?>
          <tr>
            <td><?php echo html_escape((string)$r['attendance_date']); ?></td>
            <td><?php echo html_escape((string)($r['shift_code'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)($r['checkin_at'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)($r['checkout_at'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)$r['attendance_status']); ?><?php echo (!empty($r['checkin_at']) && empty($r['checkout_at'])) ? ' (OPEN)' : ''; ?></td>
            <td class="text-end"><?php echo (int)($r['late_minutes'] ?? 0); ?></td>
            <td class="text-end"><?php echo (int)($r['early_leave_minutes'] ?? 0); ?></td>
            <td class="text-end"><?php echo (int)($r['work_minutes'] ?? 0); ?></td>
            <td class="text-end"><?php echo (int)($r['overtime_minutes'] ?? 0); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['basic_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['allowance_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($r['meal_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['overtime_pay'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['late_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['alpha_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end text-success"><?php echo number_format($isClosed ? (float)($r['manual_addition_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format($isClosed ? (float)($r['manual_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end <?php echo ((float)($r['manual_adjustment_net_amount'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($isClosed ? (float)($r['manual_adjustment_net_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['gross_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['net_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($isClosed ? (float)($r['daily_salary_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
            <td><?php echo html_escape((string)($r['remarks'] ?? '-')); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?> (Total <?php echo (int)$pg['total']; ?>)</small>
    <div class="btn-group">
      <?php $prev=max(1,(int)$pg['page']-1); $next=min((int)$pg['total_pages'],(int)$pg['page']+1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('my/attendance?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('my/attendance?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('my/attendance?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function () {
  if (!document.querySelector('.my-attendance-check-form')) return;
  var gpsStatusEl = document.getElementById('gps-status');
  var latFields = document.querySelectorAll('.gps-lat');
  var lonFields = document.querySelectorAll('.gps-lon');
  var enforceGeo = <?php echo ((int)($policy['enforce_geofence'] ?? 0) === 1) ? 'true' : 'false'; ?>;

  function setGps(lat, lon) {
    for (var i = 0; i < latFields.length; i++) latFields[i].value = String(lat);
    for (var j = 0; j < lonFields.length; j++) lonFields[j].value = String(lon);
    if (gpsStatusEl) gpsStatusEl.textContent = 'GPS: ' + Number(lat).toFixed(6) + ', ' + Number(lon).toFixed(6);
  }

  function setGpsError(msg) {
    if (gpsStatusEl) gpsStatusEl.textContent = 'GPS: ' + msg;
  }

  function resolveGps() {
    if (!navigator.geolocation) {
      setGpsError('browser tidak mendukung geolocation');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        setGps(pos.coords.latitude, pos.coords.longitude);
      },
      function () {
        setGpsError(enforceGeo ? 'wajib diaktifkan untuk absensi' : 'tidak tersedia');
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
  }

  resolveGps();
})();
</script>
