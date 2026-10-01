<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$divisionOptions = $division_options ?? [];

$summary = [
    'employee_count' => count($rows),
    'scheduled_days' => 0,
    'present_days' => 0,
    'alpha_days' => 0,
    'overtime_minutes' => 0,
    'gross_total' => 0.0,
    'thp_total' => 0.0,
];

foreach ($rows as $row) {
    $summary['scheduled_days'] += (int)($row['scheduled_days'] ?? 0);
    $summary['present_days'] += (int)($row['present_days'] ?? 0);
    $summary['alpha_days'] += (int)($row['alpha_days'] ?? 0);
    $summary['overtime_minutes'] += (int)($row['overtime_minutes'] ?? 0);
    $summary['gross_total'] += (float)($row['gross_total'] ?? 0);
    $summary['thp_total'] += (float)($row['net_total'] ?? 0);
}

$formatCurrency = static function (float $value): string {
    return 'Rp ' . number_format($value, 2, ',', '.');
};

$formatMinutes = static function (int $minutes): string {
    if ($minutes <= 0) {
        return '0 Jam';
    }
    $hours = floor($minutes / 60);
    $remain = $minutes % 60;
    if ($hours > 0 && $remain > 0) {
        return $hours . 'j ' . $remain . 'm';
    }
    if ($hours > 0) {
        return $hours . ' Jam';
    }
    return $remain . ' Menit';
};

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

<style>
  .attendance-estimate-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
  }

  .attendance-estimate-card {
    border: 1px solid rgba(154, 23, 37, 0.08);
    border-radius: 18px;
    background:
      radial-gradient(circle at top right, rgba(195, 39, 57, 0.08), transparent 28%),
      linear-gradient(135deg, #ffffff, #fff7f2);
    box-shadow: 0 12px 30px rgba(89, 38, 23, 0.08);
    padding: 18px 18px 16px;
    min-height: 118px;
  }

  .attendance-estimate-card-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #9a1725;
    margin-bottom: 10px;
  }

  .attendance-estimate-card-value {
    font-size: 27px;
    line-height: 1;
    font-weight: 800;
    color: #2b1a14;
    margin-bottom: 8px;
  }

  .attendance-estimate-card-note {
    font-size: 12px;
    line-height: 1.45;
    color: #7a685d;
  }

  .attendance-estimate-table-wrap {
    max-height: 72vh;
    overflow: auto;
  }

  .attendance-estimate-table {
    min-width: 1680px;
  }

  .attendance-estimate-table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa;
    box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.06);
    white-space: nowrap;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Rekap Gaji Bulanan (Absensi)'); ?></h4>
    <small class="text-muted">Rekap semua pegawai per periode. Klik detail untuk lihat rincian harian per pegawai.</small>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('attendance/meal-calendar'); ?>">Estimasi Uang Makan</a>
    <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
  </div>
</div>

<div class="attendance-estimate-summary mb-3">
  <div class="attendance-estimate-card">
    <div class="attendance-estimate-card-label">Pegawai Tampil</div>
    <div class="attendance-estimate-card-value"><?php echo number_format((int)$summary['employee_count'], 0, ',', '.'); ?></div>
    <div class="attendance-estimate-card-note">Ringkasan dari baris yang sedang tampil pada filter dan halaman aktif.</div>
  </div>
  <div class="attendance-estimate-card">
    <div class="attendance-estimate-card-label">Hari Terjadwal</div>
    <div class="attendance-estimate-card-value"><?php echo number_format((int)$summary['scheduled_days'], 0, ',', '.'); ?></div>
    <div class="attendance-estimate-card-note">Akumulasi total hari kerja terjadwal dari pegawai yang sedang ditampilkan.</div>
  </div>
  <div class="attendance-estimate-card">
    <div class="attendance-estimate-card-label">Hari Hadir</div>
    <div class="attendance-estimate-card-value"><?php echo number_format((int)$summary['present_days'], 0, ',', '.'); ?></div>
    <div class="attendance-estimate-card-note">Membantu melihat kepatuhan kehadiran tanpa perlu scan tabel satu per satu.</div>
  </div>
  <div class="attendance-estimate-card">
    <div class="attendance-estimate-card-label">Hari Alpha</div>
    <div class="attendance-estimate-card-value text-danger"><?php echo number_format((int)$summary['alpha_days'], 0, ',', '.'); ?></div>
    <div class="attendance-estimate-card-note">Total alpha dari data yang sedang tampil. Card ini cepat menandai periode bermasalah.</div>
  </div>
  <div class="attendance-estimate-card">
    <div class="attendance-estimate-card-label">Total Lembur</div>
    <div class="attendance-estimate-card-value"><?php echo html_escape($formatMinutes((int)$summary['overtime_minutes'])); ?></div>
    <div class="attendance-estimate-card-note">Akumulasi menit lembur pada hasil filter aktif.</div>
  </div>
  <div class="attendance-estimate-card">
    <div class="attendance-estimate-card-label">Total THP</div>
    <div class="attendance-estimate-card-value text-success" style="font-size:22px;"><?php echo html_escape($formatCurrency((float)$summary['thp_total'])); ?></div>
    <div class="attendance-estimate-card-note">Take home pay total dari baris yang tampil. Cocok untuk cross-check cepat sebelum buka detail.</div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/estimate'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Divisi/Jabatan"></div>
      <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="<?php echo site_url('attendance/estimate'); ?>" class="btn btn-outline-secondary">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
    <small class="text-muted">Tabel bisa discroll vertikal dan header akan tetap terlihat saat kita menelusuri data panjang.</small>
    <small class="text-muted">Gaji kotor halaman ini: <?php echo html_escape($formatCurrency((float)$summary['gross_total'])); ?></small>
  </div>
  <div class="attendance-estimate-table-wrap">
    <table class="table table-striped mb-0 attendance-estimate-table">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Divisi / Jabatan</th>
          <th class="text-end">Jadwal</th>
          <th class="text-end">Hadir</th>
          <th class="text-end">Alpha</th>
          <th class="text-end">Telat</th>
          <th class="text-end">Pulang Cepat</th>
          <th class="text-end">Lembur</th>
          <th class="text-end">Gaji Harian</th>
          <th class="text-end">Tunjangan</th>
          <th class="text-end">U. Makan</th>
          <th class="text-end">Lembur (Rp)</th>
          <th class="text-end">Potongan</th>
          <th class="text-end">Adj. (+)</th>
          <th class="text-end">Adj. (-)</th>
          <th class="text-end">Pot. Kasbon</th>
          <th class="text-end">Adj. Net</th>
          <th class="text-end">Gaji Kotor</th>
          <th class="text-end">Net</th>
          <th class="text-end">THP</th>
          <th class="text-center">Detail</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="21" class="text-center text-muted py-4">Tidak ada data pada periode ini.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <?php
          $deductionTotal = (float)($r['late_deduction_total'] ?? 0) + (float)($r['alpha_deduction_total'] ?? 0);
          $detailUrl = site_url('attendance/estimate/detail/' . (int)$r['employee_id'] . '?' . $buildQuery());
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '')); ?></div>
            <small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></small>
          </td>
          <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?> / <?php echo html_escape((string)($r['position_name'] ?? '-')); ?></td>
          <td class="text-end"><?php echo (int)($r['scheduled_days'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['present_days'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['alpha_days'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['late_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['early_leave_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['overtime_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['basic_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['allowance_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['meal_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['overtime_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format($deductionTotal, 2, ',', '.'); ?></td>
          <td class="text-end text-success"><?php echo number_format((float)($r['manual_addition_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format((float)($r['manual_deduction_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format((float)($r['cash_advance_cut_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end <?php echo ((float)($r['manual_adjustment_net_total'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format((float)($r['manual_adjustment_net_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['gross_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['net_only_total'] ?? $r['net_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end fw-semibold text-success"><?php echo number_format((float)($r['net_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-center"><a class="btn btn-sm btn-outline-primary" href="<?php echo $detailUrl; ?>">Detail</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
    <div class="btn-group">
      <?php $prev=max(1,(int)$pg['page']-1); $next=min((int)$pg['total_pages'],(int)$pg['page']+1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('attendance/estimate?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/estimate?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('attendance/estimate?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
