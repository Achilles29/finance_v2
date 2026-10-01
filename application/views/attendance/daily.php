<?php
$tab = $tab ?? 'daily';
if (!in_array($tab, ['daily', 'recap'], true)) {
    $tab = 'daily';
}
$filters = $filters ?? [];
$rows = $rows ?? [];
$recapRows = $recap_rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$recapPg = $recap_pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$activePg = $active_pg ?? ($tab === 'recap' ? $recapPg : $pg);
$divisionOptions = $division_options ?? [];
$statusOptions = $status_options ?? [];

$summary = [
    'row_count' => 0,
    'present_count' => 0,
    'late_count' => 0,
    'open_count' => 0,
    'alpha_count' => 0,
    'overtime_minutes' => 0,
    'gross_total' => 0.0,
    'thp_total' => 0.0,
];

if ($tab === 'recap') {
    foreach ($recapRows as $row) {
        $summary['row_count']++;
        $summary['present_count'] += (int)($row['present_days'] ?? 0);
        $summary['alpha_count'] += (int)($row['alpha_days'] ?? 0);
        $summary['overtime_minutes'] += (int)($row['overtime_minutes'] ?? 0);
        $summary['gross_total'] += (float)($row['gross_total'] ?? 0);
        $summary['thp_total'] += (float)($row['net_total'] ?? 0);
    }
} else {
    foreach ($rows as $row) {
        $status = strtoupper((string)($row['attendance_status'] ?? 'OFF'));
        $summary['row_count']++;
        if (in_array($status, ['PRESENT', 'LATE', 'HOLIDAY'], true)) {
            $summary['present_count']++;
        }
        if ($status === 'LATE') {
            $summary['late_count']++;
        }
        if ($status === 'ALPHA') {
            $summary['alpha_count']++;
        }
        if (!empty($row['checkin_at']) && empty($row['checkout_at']) && $status !== 'HOLIDAY') {
            $summary['open_count']++;
        }
        $summary['overtime_minutes'] += (int)($row['overtime_minutes'] ?? 0);
        $summary['gross_total'] += (float)($row['gross_amount'] ?? 0);
        $summary['thp_total'] += (float)($row['daily_salary_amount'] ?? 0);
    }
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

$buildQuery = static function ($overrides = []) use ($filters, $activePg, $tab) {
    $base = [
        'tab' => $tab,
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'status' => $filters['status'] ?? '',
        'date_start' => $filters['date_start'] ?? '',
        'date_end' => $filters['date_end'] ?? '',
        'per_page' => $activePg['per_page'] ?? 25,
        'page' => $activePg['page'] ?? 1,
    ];
    $params = array_merge($base, $overrides);
    return http_build_query($params);
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
  .attendance-daily-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
  }
  .attendance-daily-card {
    border: 1px solid rgba(154, 23, 37, 0.08);
    border-radius: 18px;
    background:
      radial-gradient(circle at top right, rgba(195, 39, 57, 0.08), transparent 28%),
      linear-gradient(135deg, #ffffff, #fff7f2);
    box-shadow: 0 12px 30px rgba(89, 38, 23, 0.08);
    padding: 18px 18px 16px;
    min-height: 112px;
  }
  .attendance-daily-card-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #9a1725;
    margin-bottom: 10px;
  }
  .attendance-daily-card-value {
    font-size: 27px;
    line-height: 1;
    font-weight: 800;
    color: #2b1a14;
    margin-bottom: 8px;
  }
  .attendance-daily-card-note {
    font-size: 12px;
    line-height: 1.45;
    color: #7a685d;
  }
  .attendance-daily-table-wrap {
    max-height: 72vh;
    overflow: auto;
  }
  .attendance-daily-table {
    min-width: 1500px;
  }
  .attendance-daily-table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa;
    white-space: nowrap;
    box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.06);
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo html_escape($title ?? 'Rekap Absensi'); ?></h4>
  <span class="text-muted small">Total: <?php echo (int)($activePg['total'] ?? 0); ?></span>
</div>

<?php
$workspace_tabs = [
  ['label' => 'Rekap Bulanan per Pegawai', 'url' => site_url('attendance/daily?' . $buildQuery(['tab' => 'recap', 'page' => 1])), 'active' => $tab === 'recap'],
  ['label' => 'Harian', 'url' => site_url('attendance/daily?' . $buildQuery(['tab' => 'daily', 'page' => 1])), 'active' => $tab === 'daily'],
];
$workspace_tab_label = 'Absensi';
$workspace_tab_aria_label = 'Pilih tampilan rekap absensi';
$this->load->view('layout/_workspace_tabs', get_defined_vars());
?>

<div class="attendance-daily-summary mb-3">
  <div class="attendance-daily-card">
    <div class="attendance-daily-card-label"><?php echo $tab === 'recap' ? 'Pegawai Tampil' : 'Baris Tampil'; ?></div>
    <div class="attendance-daily-card-value"><?php echo number_format((int)$summary['row_count'], 0, ',', '.'); ?></div>
    <div class="attendance-daily-card-note">Ringkasan mengikuti tab dan halaman filter yang sedang tampil.</div>
  </div>
  <div class="attendance-daily-card">
    <div class="attendance-daily-card-label"><?php echo $tab === 'recap' ? 'Hari Hadir' : 'Status Hadir'; ?></div>
    <div class="attendance-daily-card-value"><?php echo number_format((int)$summary['present_count'], 0, ',', '.'); ?></div>
    <div class="attendance-daily-card-note"><?php echo $tab === 'recap' ? 'Akumulasi hari hadir pada pegawai yang tampil.' : 'Jumlah record hadir, late, dan holiday pada halaman aktif.'; ?></div>
  </div>
  <div class="attendance-daily-card">
    <div class="attendance-daily-card-label"><?php echo $tab === 'recap' ? 'Hari Alpha' : 'Alpha / Open'; ?></div>
    <div class="attendance-daily-card-value text-danger">
      <?php echo number_format((int)$summary['alpha_count'], 0, ',', '.'); ?>
      <?php if ($tab !== 'recap'): ?>
        <span style="font-size:16px;color:#6b7280;"> / <?php echo number_format((int)$summary['open_count'], 0, ',', '.'); ?></span>
      <?php endif; ?>
    </div>
    <div class="attendance-daily-card-note"><?php echo $tab === 'recap' ? 'Akumulasi hari alpha dari pegawai yang tampil.' : 'Format: alpha / shift yang masih open.'; ?></div>
  </div>
  <div class="attendance-daily-card">
    <div class="attendance-daily-card-label"><?php echo $tab === 'recap' ? 'Total Lembur' : 'Late / Lembur'; ?></div>
    <div class="attendance-daily-card-value"><?php echo $tab === 'recap' ? html_escape($formatMinutes((int)$summary['overtime_minutes'])) : number_format((int)$summary['late_count'], 0, ',', '.'); ?></div>
    <div class="attendance-daily-card-note"><?php echo $tab === 'recap' ? 'Akumulasi menit lembur pada hasil filter aktif.' : 'Jumlah record telat. Total lembur: ' . html_escape($formatMinutes((int)$summary['overtime_minutes'])); ?></div>
  </div>
  <div class="attendance-daily-card">
    <div class="attendance-daily-card-label">Total Gross</div>
    <div class="attendance-daily-card-value" style="font-size:22px;"><?php echo html_escape($formatCurrency((float)$summary['gross_total'])); ?></div>
    <div class="attendance-daily-card-note">Akumulasi gross dari baris yang sedang tampil.</div>
  </div>
  <div class="attendance-daily-card">
    <div class="attendance-daily-card-label"><?php echo $tab === 'recap' ? 'Total THP' : 'Total THP Harian'; ?></div>
    <div class="attendance-daily-card-value text-success" style="font-size:22px;"><?php echo html_escape($formatCurrency((float)$summary['thp_total'])); ?></div>
    <div class="attendance-daily-card-note"><?php echo $tab === 'recap' ? 'Take home pay total pada recap yang tampil.' : 'Akumulasi THP harian dari record yang tampil.'; ?></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/daily'); ?>" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Shift"></div>
      <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
      <?php if ($tab === 'daily'): ?>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['status'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$activePg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><button type="submit" class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/daily?tab=' . $tab); ?>">Reset</a></div>
    </form>
  </div>
</div>

<?php if ($tab === 'recap'): ?>
<div class="card">
  <div class="attendance-daily-table-wrap">
    <table class="table table-striped mb-0 attendance-daily-table">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Divisi / Jabatan</th>
          <th class="text-end">Jadwal</th>
          <th class="text-end">Hadir</th>
          <th class="text-end">Alpha</th>
          <th class="text-end">Telat</th>
          <th class="text-end">Pulang Cepat</th>
          <th class="text-end">Lembur (mnt)</th>
          <th class="text-end">Gaji</th>
          <th class="text-end">Tunjangan</th>
          <th class="text-end">U. Makan</th>
          <th class="text-end">Lembur (Rp)</th>
          <th class="text-end">Potongan</th>
          <th class="text-end">Adj. (+)</th>
          <th class="text-end">Adj. (-)</th>
          <th class="text-end">Pot. Kasbon</th>
          <th class="text-end">Adj. Net</th>
          <th class="text-end">Gross</th>
          <th class="text-end">Net</th>
          <th class="text-end">THP</th>
          <th class="text-center">Detail</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($recapRows)): ?>
        <tr><td colspan="21" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php else: foreach($recapRows as $r): ?>
        <?php
          $deductionTotal = (float)($r['late_deduction_total'] ?? 0) + (float)($r['alpha_deduction_total'] ?? 0);
          $detailUrl = site_url('attendance/estimate/detail/' . (int)$r['employee_id'] . '?' . $buildQuery());
        ?>
        <tr>
          <td><div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '')); ?></div><small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></small></td>
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
          <td class="text-end"><?php echo number_format((float)($r['net_only_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end fw-semibold text-success"><?php echo number_format((float)($r['net_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-center"><a class="btn btn-sm btn-outline-primary" href="<?php echo $detailUrl; ?>">Detail</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="attendance-daily-table-wrap">
    <table class="table table-striped mb-0 attendance-daily-table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>NIP</th>
          <th>Nama</th>
          <th>Divisi</th>
          <th>Jabatan</th>
          <th>Shift</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Status</th>
          <th class="text-end">Telat</th>
          <th class="text-end">Pulang Cepat</th>
          <th class="text-end">Kerja (mnt)</th>
          <th class="text-end">Lembur (mnt)</th>
          <th class="text-end">Basic</th>
          <th class="text-end">Tunjangan</th>
          <th class="text-end">Uang Makan</th>
          <th class="text-end">Lembur</th>
          <th class="text-end">Pot. Telat</th>
          <th class="text-end">Pot. Alpha</th>
          <th class="text-end">Adj. (+)</th>
          <th class="text-end">Adj. (-)</th>
          <th class="text-end">Adj. Net</th>
          <th class="text-end">Gross</th>
          <th class="text-end">Net</th>
          <th class="text-end">THP Harian</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="25" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <?php
          $status = strtoupper((string)($r['attendance_status'] ?? 'OFF'));
          $isClosed = !empty($r['checkout_at']);
          $isPayrollPaidDay = ($isClosed || $status === 'HOLIDAY');
        ?>
        <tr>
          <td><?php echo html_escape((string)$r['attendance_date']); ?></td>
          <td><?php echo html_escape((string)$r['employee_code']); ?></td>
          <td><?php echo html_escape((string)$r['employee_name']); ?></td>
          <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['position_name'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['shift_code'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['checkin_at'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['checkout_at'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)$r['attendance_status']); ?><?php echo (!empty($r['checkin_at']) && empty($r['checkout_at']) && $status !== 'HOLIDAY') ? ' (OPEN)' : ''; ?></td>
          <td class="text-end"><?php echo (int)($r['late_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['early_leave_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['work_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['overtime_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['basic_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['allowance_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['meal_amount'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['overtime_pay'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['late_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['alpha_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end text-success"><?php echo number_format($isPayrollPaidDay ? (float)($r['manual_addition_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format($isPayrollPaidDay ? (float)($r['manual_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end <?php echo ((float)($r['manual_adjustment_net_amount'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($isPayrollPaidDay ? (float)($r['manual_adjustment_net_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['gross_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['net_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isPayrollPaidDay ? (float)($r['daily_salary_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (($activePg['total_pages'] ?? 1) > 1): ?>
<div class="card mt-2">
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$activePg['page']; ?> dari <?php echo (int)$activePg['total_pages']; ?></small>
    <div class="btn-group">
      <?php $prev=max(1,(int)$activePg['page']-1); $next=min((int)$activePg['total_pages'],(int)$activePg['page']+1); ?>
      <?php $pageItems = $buildPageItems((int)$activePg['page'], (int)$activePg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$activePg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$activePg['page']<=1)?'#':site_url('attendance/daily?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$activePg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/daily?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$activePg['page']>=(int)$activePg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$activePg['page']>=(int)$activePg['total_pages'])?'#':site_url('attendance/daily?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
</div>
<?php endif; ?>
