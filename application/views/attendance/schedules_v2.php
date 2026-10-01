<?php
$months = [
  '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
  '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$selectedMonth = (string)($selected_month ?? date('m'));
$selectedYear = (string)($selected_year ?? date('Y'));
$employees = $employees ?? [];
$scheduleMap = $schedule_map ?? [];
$shiftCodes = $shift_codes ?? [];
$holidayMap = array_flip($holiday_dates ?? []);
$nationalHolidayMap = [];
foreach (($national_holidays ?? []) as $holiday) {
  $holidayDate = (string)($holiday['holiday_date'] ?? '');
  if ($holidayDate !== '') {
    $nationalHolidayMap[$holidayDate] = true;
  }
}
$todayDate = date('Y-m-d');
$daysInMonth = (int)date('t', strtotime($selectedYear . '-' . $selectedMonth . '-01'));
$scheduleLimitDays = max(1, (int)($schedule_limit_days ?? 26));
$canMonthlyScheduleOverride = !empty($can_monthly_schedule_override);
$scheduleTab = $schedule_tab ?? 'grid';
$recapRows = $recap_rows ?? [];
$weekdayShortNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
$buildQuery = static function (array $overrides = []) use ($selectedMonth, $selectedYear) {
  return http_build_query(array_merge([
    'month' => $selectedMonth,
    'year' => $selectedYear,
  ], $overrides));
};
?>
<style>
  .schedule-v2-wrap { overflow:auto; max-height:72vh; border:1px solid #d8dee4; border-radius:10px; background:#fff; position: relative; }
  .schedule-v2-table { border-collapse:separate; border-spacing:0; min-width:1200px; }
  .schedule-v2-table th, .schedule-v2-table td { border:1px solid #d7dfe7 !important; }
  .schedule-v2-table thead th { position:sticky; top:0; background:#eef2f6; z-index:7; }
  .schedule-v2-fixed { position:sticky; left:0; min-width:240px; max-width:240px; border-right:2px solid #aeb8c2 !important; background-clip:padding-box; box-shadow:3px 0 0 #aeb8c2, 8px 0 12px rgba(31, 41, 55, 0.08); }
  /* Keep the employee column above scrolling shift cells instead of letting them show through. */
  .schedule-v2-table thead .schedule-v2-fixed { z-index:20; background:#ad0d23 !important; color:#fff !important; }
  .schedule-v2-table tbody .schedule-v2-fixed { z-index:12; background:#fffdfb !important; background-color:#fffdfb !important; opacity:1; }
  .schedule-v2-total { position:sticky; right:0; min-width:112px; text-align:center; white-space:nowrap; border-left:2px solid #aeb8c2 !important; background-clip:padding-box; box-shadow:-3px 0 0 #aeb8c2, -8px 0 12px rgba(31, 41, 55, 0.08); }
  .schedule-v2-table thead .schedule-v2-total { z-index:21; background:#ad0d23 !important; color:#fff !important; }
  .schedule-v2-table tbody .schedule-v2-total { z-index:11; background:#fffdfb !important; background-color:#fffdfb !important; }
  .schedule-v2-total-count { display:block; font-size:1rem; font-weight:800; line-height:1.15; }
  .schedule-v2-total-label { display:block; margin-top:.15rem; font-size:.72rem; color:#7c2d12; }
  .schedule-v2-cell { min-width:58px; text-align:center; font-weight:700; background:#fbf8ea; }
  .schedule-v2-cell[contenteditable="true"] { background:#fffbe8; outline:none; }
  .schedule-v2-cell.is-error { background:#ffe8e8; color:#9f1d1d; }
  .schedule-v2-cell.is-holiday { background:#f8fafc; border-color:#cbd5e1 !important; }
  .schedule-v2-cell.is-holiday[contenteditable="true"] { background:#f1f5f9; }
  .schedule-v2-cell.is-saturday { background:#fff1dd !important; color:#9a4e00; border-color:#f7bd6e !important; }
  .schedule-v2-cell.is-saturday[contenteditable="true"] { background:#ffe2b8 !important; }
  .schedule-v2-cell.is-sunday { background:#fff0f1 !important; color:#b42318; border-color:#f7b6bf !important; }
  .schedule-v2-cell.is-sunday[contenteditable="true"] { background:#ffd9de !important; }
  .schedule-v2-cell.is-national-holiday { background:#f5efff !important; color:#6941c6; border-color:#cdb2fa !important; }
  .schedule-v2-cell.is-national-holiday[contenteditable="true"] { background:#e9ddff !important; }
  .schedule-v2-today-head {
    background: #ffb020 !important;
    color: #1f2937 !important;
    border-color: #f08c00 !important;
    box-shadow: inset 0 -3px 0 #b45309;
  }
  .schedule-v2-today-cell {
    background: #fff2cc !important;
    border-color: #f59e0b !important;
    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.45);
  }
  .schedule-v2-cell.is-saturday.schedule-v2-today-head,
  .schedule-v2-cell.is-sunday.schedule-v2-today-head,
  .schedule-v2-cell.is-national-holiday.schedule-v2-today-head { background:#ffb020 !important; color:#1f2937 !important; border-color:#f08c00 !important; }
  .schedule-v2-cell.is-saturday.schedule-v2-today-cell { background:#ffe2b8 !important; border-color:#f7a93f !important; }
  .schedule-v2-cell.is-sunday.schedule-v2-today-cell { background:#ffd9de !important; border-color:#f36a78 !important; }
  .schedule-v2-cell.is-national-holiday.schedule-v2-today-cell { background:#e9ddff !important; border-color:#9f7aea !important; }
  .schedule-v2-cell.is-empty-schedule[contenteditable="true"] { background:#f1f5f9 !important; color:#64748b; font-weight:700; }
  .schedule-v2-cell.is-ph-schedule[contenteditable="true"] { background:#dbeafe !important; color:#075985; }
  /* Calendar context stays visible even when the editable cell currently shows OFF. */
  .schedule-v2-cell.is-saturday.is-empty-schedule[contenteditable="true"] { background:#ffe2b8 !important; color:#9a4e00; }
  .schedule-v2-cell.is-sunday.is-empty-schedule[contenteditable="true"] { background:#ffd9de !important; color:#b42318; }
  .schedule-v2-cell.is-national-holiday.is-empty-schedule[contenteditable="true"] { background:#e9ddff !important; color:#6941c6; }
  .schedule-v2-legend code { padding:.15rem .35rem; background:#f1f5f9; border-radius:6px; }
  .schedule-v2-day-name { display:block; font-size:.62rem; font-weight:800; letter-spacing:.02em; line-height:1.05; }
  .schedule-v2-day-number { display:block; margin-top:.13rem; font-size:.92rem; line-height:1; }
  .schedule-v2-tabs { display:flex; gap:.45rem; border-bottom:1px solid #e4e7ec; padding:0 .3rem; }
  .schedule-v2-tab { display:inline-flex; align-items:center; gap:.35rem; padding:.75rem .9rem; color:#667085; font-weight:700; text-decoration:none; border-bottom:3px solid transparent; }
  .schedule-v2-tab:hover { color:#ad0d23; }
  .schedule-v2-tab.active { color:#ad0d23; border-bottom-color:#ad0d23; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h5 class="fw-bold mb-0"><i class="ri-table-line me-2 text-primary"></i><?php echo html_escape($title ?? 'Jadwal Shift (Spreadsheet)'); ?></h5>
  <a href="<?php echo site_url('attendance/schedules'); ?>" class="btn btn-outline-secondary">Versi Tabel</a>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
  <form method="get" action="<?php echo site_url('attendance/schedules-v2'); ?>" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?php echo html_escape((string)$scheduleTab); ?>">
    <div class="col-6 col-md-2"><label class="form-label">Bulan</label><select name="month" class="form-select"><?php foreach($months as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo $selectedMonth===$k?'selected':''; ?>><?php echo html_escape($v); ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Tahun</label><input type="number" name="year" min="2000" max="2100" class="form-control" value="<?php echo html_escape($selectedYear); ?>"></div>
    <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
    <?php if ($scheduleTab === 'grid'): ?>
    <div class="col-12 col-md-6 text-md-end schedule-v2-legend">
      <small class="text-muted">Isi sel dengan kode shift. Kosongkan sel untuk hapus jadwal. Maksimum normal <strong><?php echo $scheduleLimitDays; ?> hari/bulan</strong> termasuk PH; Security dikecualikan. Kode aktif:
        <?php foreach($shiftCodes as $s): ?>
          <code><?php echo html_escape((string)($s['shift_code'] ?? '')); ?></code>
        <?php endforeach; ?>
      </small>
    </div>
    <?php endif; ?>
  </form>
</div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body p-0">
  <div class="schedule-v2-tabs">
    <a class="schedule-v2-tab <?php echo $scheduleTab === 'grid' ? 'active' : ''; ?>" href="<?php echo site_url('attendance/schedules-v2?' . $buildQuery(['tab' => 'grid'])); ?>"><i class="ri-table-line"></i>Jadwal Spreadsheet</a>
    <a class="schedule-v2-tab <?php echo $scheduleTab === 'recap' ? 'active' : ''; ?>" href="<?php echo site_url('attendance/schedules-v2?' . $buildQuery(['tab' => 'recap'])); ?>"><i class="ri-bar-chart-grouped-line"></i>Rekap Shift</a>
  </div>
</div></div>

<?php if ($scheduleTab === 'recap'): ?>
  <?php $this->load->view('attendance/_schedule_recap', ['recap_rows' => $recapRows, 'national_holidays' => ($national_holidays ?? [])]); ?>
<?php else: ?>
<div id="scheduleWarn" class="alert alert-danger d-none"></div>

<div class="schedule-v2-wrap">
  <table class="table table-sm table-bordered schedule-v2-table mb-0">
    <thead>
      <tr>
        <th class="schedule-v2-fixed">Pegawai</th>
        <?php for($d=1;$d<=$daysInMonth;$d++): $date = $selectedYear . '-' . $selectedMonth . '-' . str_pad((string)$d,2,'0',STR_PAD_LEFT); $weekdayNumber = (int)date('N', strtotime($date)); $isHoliday = isset($holidayMap[$date]); $isNationalHoliday = isset($nationalHolidayMap[$date]); $isSaturday = $weekdayNumber === 6; $isSunday = $weekdayNumber === 7; $isToday = ($date === $todayDate); ?>
          <th class="schedule-v2-cell<?php echo $isHoliday ? ' is-holiday' : ''; ?><?php echo $isSaturday ? ' is-saturday' : ''; ?><?php echo $isSunday ? ' is-sunday' : ''; ?><?php echo $isNationalHoliday ? ' is-national-holiday' : ''; ?><?php echo $isToday ? ' schedule-v2-today-head' : ''; ?>"><span class="schedule-v2-day-name"><?php echo html_escape((string)($weekdayShortNames[$weekdayNumber] ?? '')); ?></span><span class="schedule-v2-day-number"><?php echo str_pad((string)$d,2,'0',STR_PAD_LEFT); ?></span></th>
        <?php endfor; ?>
        <th class="schedule-v2-total">Total Hadir</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($employees as $emp): ?>
        <tr>
          <td class="schedule-v2-fixed"><?php echo html_escape(((string)($emp->employee_code ?? '-')) . ' - ' . ((string)($emp->employee_name ?? '-'))); ?></td>
          <?php $attendanceScheduleCount = 0; ?>
          <?php for($d=1;$d<=$daysInMonth;$d++): $date = $selectedYear . '-' . $selectedMonth . '-' . str_pad((string)$d,2,'0',STR_PAD_LEFT); $val = $scheduleMap[(int)$emp->id][$date] ?? ''; $shiftCode = strtoupper(trim((string)$val)); $isHoliday = isset($holidayMap[$date]); $isNationalHoliday = isset($nationalHolidayMap[$date]); $weekdayNumber = (int)date('N', strtotime($date)); $isSaturday = $weekdayNumber === 6; $isSunday = $weekdayNumber === 7; $isToday = ($date === $todayDate); $isEmptySchedule = ($shiftCode === ''); $isPhSchedule = in_array($shiftCode, ['PH', 'PHB'], true); if (!$isEmptySchedule) { $attendanceScheduleCount++; } ?>
            <td class="schedule-v2-cell<?php echo $isHoliday ? ' is-holiday' : ''; ?><?php echo $isSaturday ? ' is-saturday' : ''; ?><?php echo $isSunday ? ' is-sunday' : ''; ?><?php echo $isNationalHoliday ? ' is-national-holiday' : ''; ?><?php echo $isEmptySchedule ? ' is-empty-schedule' : ''; ?><?php echo $isPhSchedule ? ' is-ph-schedule' : ''; ?><?php echo $isToday ? ' schedule-v2-today-cell' : ''; ?>" contenteditable="true" data-employee-id="<?php echo (int)$emp->id; ?>" data-date="<?php echo html_escape($date); ?>" data-original="<?php echo html_escape((string)$val); ?>" data-empty-schedule="<?php echo $isEmptySchedule ? '1' : '0'; ?>"><?php echo html_escape($isEmptySchedule ? 'OFF' : $shiftCode); ?></td>
          <?php endfor; ?>
          <td class="schedule-v2-total"><span class="schedule-v2-total-count" data-schedule-attendance-total><?php echo (int)$attendanceScheduleCount; ?></span><span class="schedule-v2-total-label">termasuk PH</span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
(function(){
  var warnEl = document.getElementById('scheduleWarn');
  var canMonthlyScheduleOverride = <?php echo $canMonthlyScheduleOverride ? 'true' : 'false'; ?>;
  function showWarn(msg){ warnEl.textContent = msg; warnEl.classList.remove('d-none'); }
  function clearWarn(){ warnEl.textContent = ''; warnEl.classList.add('d-none'); }

  function getScheduleCode(cell){
    var shiftCode = (cell.textContent || '').trim().toUpperCase();
    return shiftCode === 'OFF' ? '' : shiftCode;
  }

  function refreshAttendanceTotal(cell){
    var row = cell.closest('tr');
    if (!row) return;
    var total = Array.prototype.reduce.call(
      row.querySelectorAll('.schedule-v2-cell[contenteditable="true"]'),
      function(count, scheduleCell){
        return count + (getScheduleCode(scheduleCell) !== '' ? 1 : 0);
      },
      0
    );
    var totalEl = row.querySelector('[data-schedule-attendance-total]');
    if (totalEl) totalEl.textContent = String(total);
  }

  function refreshScheduleCellState(cell, value){
    var shiftCode = (typeof value === 'string' ? value : getScheduleCode(cell)).trim().toUpperCase();
    var isEmpty = (shiftCode === '');
    cell.setAttribute('data-empty-schedule', isEmpty ? '1' : '0');
    cell.classList.toggle('is-empty-schedule', isEmpty);
    cell.classList.toggle('is-ph-schedule', shiftCode === 'PH' || shiftCode === 'PHB');
    cell.textContent = isEmpty ? 'OFF' : shiftCode;
  }

  function restoreCell(cell, original){
    cell.classList.remove('is-error');
    refreshScheduleCellState(cell, original);
    refreshAttendanceTotal(cell);
  }

  function saveCell(cell){
    var employeeId = cell.getAttribute('data-employee-id');
    var date = cell.getAttribute('data-date');
    var shiftCode = getScheduleCode(cell);
    var original = (cell.getAttribute('data-original') || '').trim().toUpperCase();
    if (shiftCode === original) {
      refreshScheduleCellState(cell, shiftCode);
      return;
    }

    function send(allowMonthlyOverride, overrideReason){
      fetch(<?php echo json_encode(site_url('attendance/schedules-v2/save')); ?>, {
        method: 'POST',
        headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({
          employee_id: employeeId,
          schedule_date: date,
          shift_code: shiftCode,
          allow_monthly_override: allowMonthlyOverride ? 1 : 0,
          override_reason: overrideReason || ''
        })
      })
      .then(function(r){ return r.json().then(function(j){ return {ok:r.ok, j:j}; }); })
      .then(function(res){
        var message = (res.j && res.j.message) ? res.j.message : 'Gagal simpan jadwal.';
        if (!res.ok || !res.j || Number(res.j.ok) !== 1) {
          if (res.j && Number(res.j.requires_override) === 1 && canMonthlyScheduleOverride) {
            var approved = window.confirm(message + '\n\nLanjutkan dengan override jadwal?');
            if (approved) {
              var reason = window.prompt('Catatan alasan override:', 'Kebutuhan operasional jadwal');
              if (reason !== null) {
                send(true, reason);
                return;
              }
            }
          }
          restoreCell(cell, original);
          showWarn(message);
          return;
        }
        cell.classList.remove('is-error');
        cell.setAttribute('data-original', shiftCode);
        refreshScheduleCellState(cell, shiftCode);
        refreshAttendanceTotal(cell);
        clearWarn();
      })
      .catch(function(){
        restoreCell(cell, original);
        showWarn('Terjadi kesalahan koneksi saat menyimpan jadwal.');
      });
    }

    send(false, '');
  }

  document.querySelectorAll('.schedule-v2-cell[contenteditable="true"]').forEach(function(cell){
    cell.addEventListener('focus', function(){
      if (cell.getAttribute('data-empty-schedule') === '1') {
        cell.textContent = '';
        cell.setAttribute('data-empty-schedule', '0');
        cell.classList.remove('is-empty-schedule');
      }
    });
    cell.addEventListener('blur', function(){ saveCell(cell); });
    cell.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        cell.blur();
      }
    });
  });
})();
</script>
<?php endif; ?>
