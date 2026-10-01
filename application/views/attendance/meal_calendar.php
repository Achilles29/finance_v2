<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$days = $days ?? [];
$dailyMap = $daily_map ?? [];
$summary = $summary ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'date_start' => $filters['date_start'] ?? '',
        'date_end' => $filters['date_end'] ?? '',
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};

$pageItems = static function (int $page, int $totalPages): array {
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

$indoDow = [
    'Mon' => 'Sen',
    'Tue' => 'Sel',
    'Wed' => 'Rab',
    'Thu' => 'Kam',
    'Fri' => 'Jum',
    'Sat' => 'Sab',
    'Sun' => 'Min',
];

$renderCell = static function (array $dailyRow): array {
    $meal = (float)($dailyRow['meal_amount'] ?? 0);
    $status = strtoupper((string)($dailyRow['attendance_status'] ?? ''));
    $isPaid = (int)($dailyRow['is_paid'] ?? 0) === 1;

    if ($meal > 0) {
        return [
            'text' => number_format($meal, 0, ',', '.') . ($isPaid ? ' ✓' : ''),
            'class' => $isPaid ? 'mc-cell mc-paid' : 'mc-cell mc-earned',
        ];
    }

    if ($status === 'ALPHA') {
        return ['text' => 'Alpha', 'class' => 'mc-cell mc-alpha'];
    }
    if ($status === 'SICK') {
        return ['text' => 'Sakit', 'class' => 'mc-cell mc-leave'];
    }
    if ($status === 'LEAVE') {
        return ['text' => 'Izin', 'class' => 'mc-cell mc-leave'];
    }
    if ($status === 'HOLIDAY') {
        return ['text' => 'Libur', 'class' => 'mc-cell mc-off'];
    }
    if ($status === 'OFF') {
        return ['text' => 'Off', 'class' => 'mc-cell mc-off'];
    }
    if ($status === 'LATE') {
        return ['text' => 'Telat', 'class' => 'mc-cell mc-presence'];
    }
    if ($status === 'PRESENT') {
        return ['text' => 'Hadir', 'class' => 'mc-cell mc-presence'];
    }

    return ['text' => '—', 'class' => 'mc-cell mc-empty'];
};
?>

<style>
  .meal-calendar-wrap .summary-card { border: 1px solid #efe3de; border-radius: 14px; }
  .meal-calendar-wrap .summary-card .value { font-weight: 700; font-size: 1.05rem; color: #2f2230; }
  .meal-calendar-wrap .legend-pill { border-radius: 999px; padding: .35rem .7rem; font-size: .78rem; font-weight: 700; border: 1px solid transparent; }
  .meal-calendar-wrap .legend-earned { background: #d6f5dd; color: #196f3d; border-color: #b8eac4; }
  .meal-calendar-wrap .legend-paid { background: #d6e7ff; color: #0d4ea6; border-color: #b6d0f6; }
  .meal-calendar-wrap .legend-alpha { background: #fff1c9; color: #9a6a00; border-color: #ffd77a; }
  .meal-calendar-wrap .legend-off { background: #eceff3; color: #4f5967; border-color: #d6dde6; }

  .meal-calendar-wrap .mc-table th,
  .meal-calendar-wrap .mc-table td { white-space: nowrap; vertical-align: middle; }
  .meal-calendar-wrap .mc-sticky { position: sticky; left: 0; z-index: 2; background: #fff; }
  .meal-calendar-wrap .mc-sticky-2 { position: sticky; left: 180px; z-index: 2; background: #fff; }
  .meal-calendar-wrap .mc-sticky-3 { position: sticky; left: 340px; z-index: 2; background: #fff; }
  .meal-calendar-wrap thead .mc-sticky,
  .meal-calendar-wrap thead .mc-sticky-2,
  .meal-calendar-wrap thead .mc-sticky-3 { z-index: 3; }

  .meal-calendar-wrap .mc-col-name { min-width: 180px; max-width: 180px; }
  .meal-calendar-wrap .mc-col-div { min-width: 160px; max-width: 160px; }
  .meal-calendar-wrap .mc-col-rate { min-width: 90px; max-width: 90px; text-align: right; }
  .meal-calendar-wrap .mc-day-col { min-width: 68px; text-align: center; }
  .meal-calendar-wrap .mc-total-col { min-width: 110px; text-align: right; }

  .meal-calendar-wrap .mc-cell { border-radius: 8px; font-size: .78rem; font-weight: 700; display: inline-block; padding: .2rem .45rem; min-width: 54px; text-align: center; }
  .meal-calendar-wrap .mc-earned { background: #d6f5dd; color: #196f3d; }
  .meal-calendar-wrap .mc-paid { background: #d6e7ff; color: #0d4ea6; }
  .meal-calendar-wrap .mc-alpha { background: #fff1c9; color: #9a6a00; }
  .meal-calendar-wrap .mc-leave { background: #ffe4bf; color: #8e4f00; }
  .meal-calendar-wrap .mc-off { background: #eceff3; color: #4f5967; }
  .meal-calendar-wrap .mc-presence { background: #e6f4ff; color: #165ea8; }
  .meal-calendar-wrap .mc-empty { background: #f5f6f8; color: #9aa3af; }
</style>

<div class="meal-calendar-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-0"><?php echo html_escape($title ?? 'Uang Makan Custom'); ?></h4>
      <small class="text-muted">Rekap hak uang makan <strong>Custom</strong> per pegawai per tanggal, dengan status sudah dibayar/belum. Uang makan Bulanan ada pada payroll.</small>
    </div>
    <a href="<?php echo site_url('my/meal-ledger'); ?>" class="btn btn-outline-secondary">Ledger Uang Makan Saya</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?php echo site_url('attendance/meal-calendar'); ?>" class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label mb-1">Dari Tanggal</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
        <div class="col-md-2"><label class="form-label mb-1">Sampai Tanggal</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
        <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua Divisi</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" placeholder="Nama / NIP" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>"></div>
        <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Tampilkan</button><a href="<?php echo site_url('attendance/meal-calendar'); ?>" class="btn btn-outline-secondary">Reset</a></div>
      </form>

      <div class="d-flex gap-2 flex-wrap mt-3">
        <span class="legend-pill legend-earned">Hadir + Hak Uang Makan Custom</span>
        <span class="legend-pill legend-paid">Sudah Dicairkan</span>
        <span class="legend-pill legend-alpha">Alpha</span>
        <span class="legend-pill legend-off">Off / Libur / Tidak Ada Data</span>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6"><div class="summary-card p-3"><small class="text-muted d-block">Pegawai dalam rentang</small><div class="value"><?php echo (int)($summary['employee_count'] ?? 0); ?> Pegawai</div></div></div>
    <div class="col-xl-3 col-md-6"><div class="summary-card p-3"><small class="text-muted d-block">Hari hak uang makan Custom</small><div class="value"><?php echo (int)($summary['meal_days'] ?? 0); ?> Hari</div></div></div>
    <div class="col-xl-3 col-md-6"><div class="summary-card p-3"><small class="text-muted d-block">Total hak Custom periode</small><div class="value">Rp <?php echo number_format((float)($summary['meal_total'] ?? 0), 0, ',', '.'); ?></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="summary-card p-3"><small class="text-muted d-block">Belum dicairkan</small><div class="value">Rp <?php echo number_format((float)($summary['unpaid_total'] ?? 0), 0, ',', '.'); ?></div></div></div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-bordered table-sm mc-table mb-0">
        <thead>
          <tr>
            <th class="mc-sticky mc-col-name">Pegawai</th>
            <th class="mc-sticky-2 mc-col-div">Divisi</th>
            <th class="mc-sticky-3 mc-col-rate">Rate/Hari</th>
            <?php foreach ($days as $d): ?>
              <th class="mc-day-col text-center"><?php echo html_escape((string)$d['day']); ?><br><small class="text-muted"><?php echo html_escape((string)($indoDow[$d['dow']] ?? $d['dow'])); ?></small></th>
            <?php endforeach; ?>
            <th class="mc-total-col">Range</th>
            <th class="mc-total-col">Belum</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="<?php echo 5 + count($days); ?>" class="text-center text-muted py-4">Tidak ada data pada rentang ini.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <?php
              $eid = (int)($r['employee_id'] ?? 0);
              $empMap = $dailyMap[$eid] ?? [];
              $mealTotal = (float)($r['meal_total'] ?? 0);
              $paidTotal = (float)($r['paid_total'] ?? 0);
              $unpaidTotal = max(0, $mealTotal - $paidTotal);
            ?>
            <tr>
              <td class="mc-sticky mc-col-name">
                <div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div>
                <small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></small>
              </td>
              <td class="mc-sticky-2 mc-col-div"><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
              <?php
                $mealRateMin = (float)($r['meal_rate_min'] ?? $r['meal_rate'] ?? 0);
                $mealRateMax = (float)($r['meal_rate'] ?? 0);
              ?>
              <td class="mc-sticky-3 mc-col-rate" title="Tarif uang makan per hari dari snapshot absensi pada rentang terpilih.">
                <?php if (abs($mealRateMax - $mealRateMin) > 0.005): ?>
                  <?php echo number_format($mealRateMin, 0, ',', '.'); ?>-<?php echo number_format($mealRateMax, 0, ',', '.'); ?>
                <?php else: ?>
                  <?php echo number_format($mealRateMax, 0, ',', '.'); ?>
                <?php endif; ?>
              </td>
              <?php foreach ($days as $d): ?>
                <?php
                  $dateKey = (string)$d['date'];
                  $dailyRow = $empMap[$dateKey] ?? null;
                  if ($dailyRow) {
                      $cell = $renderCell((array)$dailyRow);
                  } else {
                      $cell = ['text' => '—', 'class' => 'mc-cell mc-empty'];
                  }
                ?>
                <td class="text-center"><span class="<?php echo html_escape((string)$cell['class']); ?>"><?php echo html_escape((string)$cell['text']); ?></span></td>
              <?php endforeach; ?>
              <td class="text-end fw-semibold">Rp <?php echo number_format($mealTotal, 0, ',', '.'); ?></td>
              <td class="text-end text-warning fw-semibold">Rp <?php echo number_format($unpaidTotal, 0, ',', '.'); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (($pg['total_pages'] ?? 1) > 1): ?>
      <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); $items = $pageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
        <div class="btn-group">
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('attendance/meal-calendar?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
          <?php foreach ($items as $it): ?>
            <?php if ($it === '...'): ?>
              <span class="btn btn-sm btn-outline-secondary disabled">...</span>
            <?php else: ?>
              <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$it) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/meal-calendar?' . $buildQuery(['page' => (int)$it])); ?>"><?php echo (int)$it; ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('attendance/meal-calendar?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
