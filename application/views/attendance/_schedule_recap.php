<?php
$recapRows = $recap_rows ?? [];
$nationalHolidays = $national_holidays ?? [];
$grouped = [];
$assignmentTotal = 0;
$divisionSet = [];
$shiftSummaries = [];
$divisionSummaries = [];
$nationalHolidayMap = [];
$weekdayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];

foreach ($nationalHolidays as $holiday) {
    $holidayDate = (string)($holiday['holiday_date'] ?? '');
    $holidayName = trim((string)($holiday['holiday_name'] ?? ''));
    if ($holidayDate === '') {
        continue;
    }
    if (!isset($nationalHolidayMap[$holidayDate])) {
        $nationalHolidayMap[$holidayDate] = [];
    }
    if ($holidayName !== '') {
        $nationalHolidayMap[$holidayDate][$holidayName] = true;
    }
}

$describeDate = static function (string $date) use ($weekdayNames, $monthNames): array {
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return ['weekday_number' => 0, 'day_name' => '-', 'date_label' => $date];
    }
    $weekdayNumber = (int)date('N', $timestamp);
    $monthNumber = (int)date('n', $timestamp);
    return [
        'weekday_number' => $weekdayNumber,
        'day_name' => $weekdayNames[$weekdayNumber] ?? '-',
        'date_label' => date('d', $timestamp) . ' ' . ($monthNames[$monthNumber] ?? '') . ' ' . date('Y', $timestamp),
    ];
};

foreach ($recapRows as $row) {
    $date = (string)($row['schedule_date'] ?? '');
    $divisionId = (string)($row['division_id'] ?? '0');
    $key = $date . '|' . $divisionId;
    if (!isset($grouped[$key])) {
        $dateInfo = $describeDate($date);
        $grouped[$key] = [
            'date' => $date,
            'weekday_number' => (int)$dateInfo['weekday_number'],
            'day_name' => $dateInfo['day_name'],
            'date_label' => $dateInfo['date_label'],
            'national_holiday_names' => array_keys($nationalHolidayMap[$date] ?? []),
            'division_name' => (string)($row['division_name'] ?? 'Tanpa Divisi'),
            'shifts' => [],
            'total' => 0,
        ];
    }
    $count = (int)($row['employee_count'] ?? 0);
    $shiftId = (int)($row['shift_id'] ?? 0);
    $shiftCode = trim((string)($row['shift_code'] ?? ''));
    $shiftName = trim((string)($row['shift_name'] ?? ''));
    $shiftKey = $shiftId > 0 ? 'id:' . $shiftId : 'code:' . $shiftCode . '|' . $shiftName;
    if (!isset($shiftSummaries[$shiftKey])) {
        $shiftSummaries[$shiftKey] = [
            'shift_code' => $shiftCode !== '' ? $shiftCode : '-',
            'shift_name' => $shiftName,
            'assignment_total' => 0,
            'dates' => [],
            'division_ids' => [],
        ];
    }
    $shiftSummaries[$shiftKey]['assignment_total'] += $count;
    $shiftSummaries[$shiftKey]['dates'][$date] = true;
    $shiftSummaries[$shiftKey]['division_ids'][$divisionId] = true;

    if (!isset($divisionSummaries[$divisionId])) {
        $divisionSummaries[$divisionId] = [
            'division_name' => (string)($row['division_name'] ?? 'Tanpa Divisi'),
            'assignment_total' => 0,
            'dates' => [],
            'shift_codes' => [],
        ];
    }
    $divisionSummaries[$divisionId]['assignment_total'] += $count;
    $divisionSummaries[$divisionId]['dates'][$date] = true;
    $divisionSummaries[$divisionId]['shift_codes'][$shiftCode !== '' ? $shiftCode : '-'] = true;

    $grouped[$key]['shifts'][] = $row;
    $grouped[$key]['total'] += $count;
    $assignmentTotal += $count;
    $divisionSet[$divisionId] = true;
}

$dateGroups = [];
foreach ($grouped as $group) {
    $date = (string)$group['date'];
    if (!isset($dateGroups[$date])) {
        $weekdayNumber = (int)($group['weekday_number'] ?? 0);
        $isNationalHoliday = !empty($group['national_holiday_names']);
        $calendarClass = 'weekday';
        // A national holiday takes priority when it falls on a weekend.
        if ($isNationalHoliday) {
            $calendarClass = 'national-holiday';
        } elseif ($weekdayNumber === 6) {
            $calendarClass = 'saturday';
        } elseif ($weekdayNumber === 7) {
            $calendarClass = 'sunday';
        }

        $dateGroups[$date] = [
            'day_name' => (string)$group['day_name'],
            'date_label' => (string)$group['date_label'],
            'weekday_number' => $weekdayNumber,
            'national_holiday_names' => (array)$group['national_holiday_names'],
            'calendar_class' => $calendarClass,
            'divisions' => [],
        ];
    }
    $dateGroups[$date]['divisions'][] = $group;
}

$sortSummaryByVolume = static function (array $left, array $right): int {
    $byAssignments = (int)$right['assignment_total'] <=> (int)$left['assignment_total'];
    if ($byAssignments !== 0) {
        return $byAssignments;
    }
    $leftLabel = (string)($left['division_name'] ?? $left['shift_code'] ?? '');
    $rightLabel = (string)($right['division_name'] ?? $right['shift_code'] ?? '');
    return strcasecmp($leftLabel, $rightLabel);
};
uasort($shiftSummaries, $sortSummaryByVolume);
uasort($divisionSummaries, $sortSummaryByVolume);
?>

<style>
  .schedule-recap .recap-stat {
    border: 1px solid #e4e7ec;
    border-radius: 12px;
    background: linear-gradient(135deg, #fffdf9, #f8fafc);
    padding: .8rem 1rem;
    height: 100%;
  }
  .schedule-recap .recap-stat-label { color: #667085; font-size: .76rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .schedule-recap .recap-stat-value { color: #182230; font-size: 1.35rem; font-weight: 800; line-height: 1.2; }
  .schedule-recap .shift-chip {
    display: inline-flex;
    align-items: baseline;
    gap: .3rem;
    margin: .16rem .22rem .16rem 0;
    padding: .32rem .48rem;
    border: 1px solid #e1c8cb;
    border-radius: 8px;
    background: #fff8f8;
    color: #77242d;
    white-space: nowrap;
  }
  .schedule-recap .shift-chip-code { font-weight: 800; font-size: .78rem; }
  .schedule-recap .shift-chip-count { font-weight: 800; color: #ad0d23; }
  .schedule-recap .recap-total { color: #0f766e; font-size: 1rem; font-weight: 800; }
  .schedule-recap .recap-date-merged { vertical-align: middle; }
  .schedule-recap .recap-date-cell { border-left: 4px solid #d0d5dd; border-radius: 7px; min-width: 125px; padding: .25rem 0 .25rem .55rem; }
  .schedule-recap .recap-day-name { font-size: .83rem; font-weight: 800; line-height: 1.15; }
  .schedule-recap .recap-date-label { color: #667085; font-size: .78rem; line-height: 1.25; }
  .schedule-recap .recap-date-flags { display: flex; flex-wrap: wrap; gap: .22rem; margin-top: .28rem; }
  .schedule-recap .recap-date-flag { border-radius: 999px; font-size: .66rem; font-weight: 800; letter-spacing: .02em; padding: .08rem .34rem; }
  .schedule-recap .recap-date-flag.saturday { background: #ffe2b8; color: #9a4e00; }
  .schedule-recap .recap-date-flag.sunday { background: #ffd9de; color: #b42318; }
  .schedule-recap .recap-date-flag.national { background: #e9ddff; color: #6941c6; }
  .schedule-recap .table > tbody > tr.recap-row--saturday > td { background-color: #fff4e5 !important; color: #9a4e00; }
  .schedule-recap .table > tbody > tr.recap-row--sunday > td { background-color: #fff0f1 !important; color: #b42318; }
  .schedule-recap .table > tbody > tr.recap-row--national-holiday > td { background-color: #f5efff !important; color: #6941c6; }
  .schedule-recap .table > tbody > tr.recap-row--saturday:hover > td { background-color: #ffedd2 !important; }
  .schedule-recap .table > tbody > tr.recap-row--sunday:hover > td { background-color: #ffe3e6 !important; }
  .schedule-recap .table > tbody > tr.recap-row--national-holiday:hover > td { background-color: #eee2ff !important; }
  .schedule-recap .recap-row--saturday .recap-date-cell { border-left-color: #f79009; }
  .schedule-recap .recap-row--sunday .recap-date-cell { border-left-color: #f04438; }
  .schedule-recap .recap-row--national-holiday .recap-date-cell { border-left-color: #7f56d9; }
  .schedule-recap .recap-row--saturday .recap-date-label,
  .schedule-recap .recap-row--sunday .recap-date-label,
  .schedule-recap .recap-row--national-holiday .recap-date-label { color: inherit; opacity: .82; }
  .schedule-recap .recap-row--saturday .shift-chip { background: #fffaf1; border-color: #f5c782; }
  .schedule-recap .recap-row--sunday .shift-chip { background: #fff8f8; border-color: #f5b8c0; }
  .schedule-recap .recap-row--national-holiday .shift-chip { background: #fcfaff; border-color: #cfb5fb; }
  .schedule-recap .recap-summary-card { height: 100%; border: 1px solid #e4e7ec; border-radius: 12px; background: #fff; overflow: hidden; }
  .schedule-recap .recap-summary-card.shift { border-top: 4px solid #ad0d23; }
  .schedule-recap .recap-summary-card.division { border-top: 4px solid #0f766e; }
  .schedule-recap .recap-summary-head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; padding: .8rem 1rem .7rem; border-bottom: 1px solid #eef1f4; }
  .schedule-recap .recap-summary-title { color: #182230; font-size: .94rem; font-weight: 800; }
  .schedule-recap .recap-summary-note { color: #667085; font-size: .74rem; line-height: 1.35; margin-top: .14rem; }
  .schedule-recap .recap-summary-count { border-radius: 999px; background: #f2f4f7; color: #475467; font-size: .72rem; font-weight: 800; padding: .2rem .5rem; white-space: nowrap; }
  .schedule-recap .recap-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: .55rem; padding: .75rem; }
  .schedule-recap .recap-summary-item { min-width: 0; border: 1px solid #eef1f4; border-radius: 9px; background: #fcfcfd; padding: .62rem .7rem; }
  .schedule-recap .recap-summary-item.shift { border-left: 3px solid #c44758; }
  .schedule-recap .recap-summary-item.division { border-left: 3px solid #27a58e; }
  .schedule-recap .recap-summary-code { color: #ad0d23; font-size: .76rem; font-weight: 900; letter-spacing: .04em; }
  .schedule-recap .recap-summary-name { color: #344054; font-size: .76rem; line-height: 1.25; margin-top: .08rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .schedule-recap .recap-summary-item.division .recap-summary-code { color: #0f766e; }
  .schedule-recap .recap-summary-volume { color: #182230; font-size: 1.08rem; font-weight: 900; line-height: 1.15; margin-top: .42rem; }
  .schedule-recap .recap-summary-volume small { color: #667085; font-size: .68rem; font-weight: 700; }
  .schedule-recap .recap-summary-meta { color: #667085; font-size: .7rem; margin-top: .12rem; }
</style>

<div class="schedule-recap">
  <div class="row g-2 mb-3">
    <div class="col-sm-4">
      <div class="recap-stat"><div class="recap-stat-label">Hari Terjadwal</div><div class="recap-stat-value"><?php echo count($dateGroups); ?></div></div>
    </div>
    <div class="col-sm-4">
      <div class="recap-stat"><div class="recap-stat-label">Divisi Terlibat</div><div class="recap-stat-value"><?php echo count($divisionSet); ?></div></div>
    </div>
    <div class="col-sm-4">
      <div class="recap-stat"><div class="recap-stat-label">Penugasan Shift</div><div class="recap-stat-value"><?php echo number_format($assignmentTotal, 0, ',', '.'); ?></div></div>
    </div>
  </div>

  <?php if (!empty($recapRows)): ?>
    <div class="row g-3 mb-3">
      <div class="col-xl-7">
        <section class="recap-summary-card shift">
          <div class="recap-summary-head">
            <div><div class="recap-summary-title"><i class="ri-time-line me-1"></i>Ringkasan per Shift</div><div class="recap-summary-note">Akumulasi orang terjadwal pada tiap shift selama periode aktif.</div></div>
            <span class="recap-summary-count"><?php echo number_format(count($shiftSummaries), 0, ',', '.'); ?> shift</span>
          </div>
          <div class="recap-summary-grid">
            <?php foreach ($shiftSummaries as $summary): ?>
              <div class="recap-summary-item shift">
                <div class="recap-summary-code"><?php echo html_escape((string)$summary['shift_code']); ?></div>
                <div class="recap-summary-name" title="<?php echo html_escape((string)$summary['shift_name']); ?>"><?php echo html_escape((string)($summary['shift_name'] !== '' ? $summary['shift_name'] : 'Tanpa nama shift')); ?></div>
                <div class="recap-summary-volume"><?php echo number_format((int)$summary['assignment_total'], 0, ',', '.'); ?> <small>penugasan</small></div>
                <div class="recap-summary-meta"><?php echo count($summary['dates']); ?> hari · <?php echo count($summary['division_ids']); ?> divisi</div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
      <div class="col-xl-5">
        <section class="recap-summary-card division">
          <div class="recap-summary-head">
            <div><div class="recap-summary-title"><i class="ri-community-line me-1"></i>Ringkasan per Divisi</div><div class="recap-summary-note">Beban jadwal berdasarkan divisi pegawai pada periode aktif.</div></div>
            <span class="recap-summary-count"><?php echo number_format(count($divisionSummaries), 0, ',', '.'); ?> divisi</span>
          </div>
          <div class="recap-summary-grid">
            <?php foreach ($divisionSummaries as $summary): ?>
              <div class="recap-summary-item division">
                <div class="recap-summary-code">DIVISI</div>
                <div class="recap-summary-name" title="<?php echo html_escape((string)$summary['division_name']); ?>"><?php echo html_escape((string)$summary['division_name']); ?></div>
                <div class="recap-summary-volume"><?php echo number_format((int)$summary['assignment_total'], 0, ',', '.'); ?> <small>penugasan</small></div>
                <div class="recap-summary-meta"><?php echo count($summary['dates']); ?> hari · <?php echo count($summary['shift_codes']); ?> shift</div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
      <strong>Rekap Kebutuhan Shift per Tanggal dan Divisi</strong>
      <div class="small text-muted mt-1">Angka adalah jumlah pegawai unik pada kombinasi tanggal, divisi pegawai, dan shift. Oranye: Sabtu; merah: Minggu; ungu: hari libur nasional. Arahkan kursor ke chip shift untuk melihat nama pegawai.</div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th style="width:150px;">Hari &amp; Tanggal</th><th style="width:190px;">Divisi</th><th>Komposisi Shift</th><th class="text-end" style="width:110px;">Total</th></tr></thead>
        <tbody>
          <?php if (empty($grouped)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada jadwal pada periode ini.</td></tr>
          <?php else: foreach ($dateGroups as $dateGroup): ?>
            <?php
              $rowspan = count($dateGroup['divisions']);
              $weekdayNumber = (int)$dateGroup['weekday_number'];
              $isNationalHoliday = !empty($dateGroup['national_holiday_names']);
            ?>
            <?php foreach ($dateGroup['divisions'] as $divisionIndex => $group): ?>
              <tr class="recap-row--<?php echo html_escape((string)$dateGroup['calendar_class']); ?>">
                <?php if ($divisionIndex === 0): ?>
                  <td rowspan="<?php echo (int)$rowspan; ?>" class="recap-date-merged">
                    <div class="recap-date-cell">
                      <div class="recap-day-name"><?php echo html_escape((string)$dateGroup['day_name']); ?></div>
                      <div class="recap-date-label"><?php echo html_escape((string)$dateGroup['date_label']); ?></div>
                      <?php if ($weekdayNumber >= 6 || $isNationalHoliday): ?>
                        <div class="recap-date-flags">
                          <?php if ($weekdayNumber === 6): ?><span class="recap-date-flag saturday">Sabtu</span><?php endif; ?>
                          <?php if ($weekdayNumber === 7): ?><span class="recap-date-flag sunday">Minggu</span><?php endif; ?>
                          <?php if ($isNationalHoliday): ?><span class="recap-date-flag national" title="<?php echo html_escape(implode(' | ', $dateGroup['national_holiday_names'])); ?>">Libur nasional</span><?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                <?php endif; ?>
                <td><?php echo html_escape((string)$group['division_name']); ?></td>
                <td>
                  <?php foreach ($group['shifts'] as $shift): ?>
                    <?php
                      $code = (string)($shift['shift_code'] ?? '-');
                      $name = (string)($shift['shift_name'] ?? '');
                      $people = (string)($shift['employee_names'] ?? '');
                    ?>
                    <span class="shift-chip" title="<?php echo html_escape($name . ': ' . $people); ?>">
                      <span class="shift-chip-code"><?php echo html_escape($code); ?></span>
                      <span class="shift-chip-count"><?php echo (int)($shift['employee_count'] ?? 0); ?> org</span>
                    </span>
                  <?php endforeach; ?>
                </td>
                <td class="text-end recap-total"><?php echo number_format((int)$group['total'], 0, ',', '.'); ?> org</td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
