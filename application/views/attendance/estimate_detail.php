<?php
$summary = $summary ?? null;
$dailyRows = $daily_rows ?? [];
$dateStart = $date_start ?? date('Y-m-01');
$dateEnd = $date_end ?? date('Y-m-t');
$backFilters = $back_filters ?? [];
$backRoute = !empty($backFilters['tab']) ? 'attendance/daily' : 'attendance/estimate';
$backUrl = site_url($backRoute . '?' . http_build_query($backFilters));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Detail Estimasi Gaji Pegawai'); ?></h4>
    <small class="text-muted"><?php echo html_escape((string)($summary['employee_name'] ?? '')); ?> (<?php echo html_escape((string)($summary['employee_code'] ?? '')); ?>) • <?php echo html_escape($dateStart); ?> s/d <?php echo html_escape($dateEnd); ?></small>
  </div>
  <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<?php if ($summary): ?>
<div class="row g-2 mb-3">
  <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Hari Hadir</small><div class="h6 mb-0"><?php echo (int)$summary['present_days']; ?></div></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Hari Alpha</small><div class="h6 mb-0 text-danger"><?php echo (int)$summary['alpha_days']; ?></div></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body py-2"><small class="text-muted">Total Telat</small><div class="h6 mb-0"><?php echo (int)$summary['late_minutes']; ?> menit</div></div></div></div>
  <div class="col-md-3"><div class="card border-success"><div class="card-body py-2"><small class="text-muted">THP Estimasi</small><div class="h6 mb-0 text-success"><?php echo number_format((float)$summary['net_estimate'],2,',','.'); ?></div></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-2"><small class="text-muted">Gaji Harian</small><div class="fw-semibold"><?php echo number_format((float)$summary['basic_estimate'],2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Tunjangan</small><div class="fw-semibold"><?php echo number_format((float)$summary['allowance_estimate'],2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">U. Makan</small><div class="fw-semibold"><?php echo number_format((float)$summary['meal_estimate'],2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Lembur</small><div class="fw-semibold"><?php echo number_format((float)$summary['overtime_estimate'],2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Pot. Telat</small><div class="fw-semibold text-danger"><?php echo number_format((float)$summary['late_deduction'],2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Pot. Alpha</small><div class="fw-semibold text-danger"><?php echo number_format((float)$summary['alpha_deduction'],2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Adj. Tambahan</small><div class="fw-semibold text-success"><?php echo number_format((float)($summary['manual_addition'] ?? 0),2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Adj. Pengurangan</small><div class="fw-semibold text-danger"><?php echo number_format((float)($summary['manual_deduction'] ?? 0),2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Pot. Kasbon</small><div class="fw-semibold text-danger"><?php echo number_format((float)($summary['cash_advance_cut'] ?? 0),2,',','.'); ?></div></div>
      <div class="col-md-2"><small class="text-muted">Adj. Net</small><div class="fw-semibold <?php echo ((float)($summary['manual_adjustment_net'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format((float)($summary['manual_adjustment_net'] ?? 0),2,',','.'); ?></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Tanggal</th><th>Shift</th><th>Status</th><th class="text-end">Telat</th><th class="text-end">Pulang Cepat</th><th class="text-end">Kerja</th><th class="text-end">Gaji</th><th class="text-end">Tunjangan</th><th class="text-end">U. Makan</th><th class="text-end">Lembur</th><th class="text-end">Potongan</th><th class="text-end">Adj. (+)</th><th class="text-end">Adj. (-)</th><th class="text-end">Pot. Kasbon</th><th class="text-end">Adj. Net</th><th class="text-end">Gross</th><th class="text-end">Net</th><th class="text-end">THP Harian</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($dailyRows)): ?>
        <tr><td colspan="18" class="text-center text-muted py-4">Tidak ada data absensi pada periode ini.</td></tr>
      <?php else: foreach($dailyRows as $r): ?>
        <?php $thpDay = (float)($r['day_total'] ?? 0); ?>
        <tr>
          <td><?php echo html_escape((string)$r['attendance_date']); ?></td>
          <td><?php echo html_escape((string)$r['shift_code']); ?></td>
          <td><?php echo html_escape((string)$r['status']); ?></td>
          <td class="text-end"><?php echo (int)$r['late_minutes']; ?></td>
          <td class="text-end"><?php echo (int)($r['early_leave_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)$r['work_minutes']; ?></td>
          <td class="text-end"><?php echo number_format((float)$r['basic_est'],2,',','.'); ?></td>
          <td class="text-end"><?php echo number_format((float)$r['allowance_est'],2,',','.'); ?></td>
          <td class="text-end"><?php echo number_format((float)$r['meal_est'],2,',','.'); ?></td>
          <td class="text-end"><?php echo number_format((float)$r['overtime_pay'],2,',','.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format((float)$r['late_deduction'] + (float)$r['alpha_deduction'],2,',','.'); ?></td>
          <td class="text-end text-success"><?php echo number_format((float)($r['manual_addition_amount'] ?? 0),2,',','.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format((float)($r['manual_deduction_amount'] ?? 0),2,',','.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format((float)($r['cash_advance_cut'] ?? 0),2,',','.'); ?></td>
          <td class="text-end <?php echo ((float)($r['manual_adjustment_net_amount'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format((float)($r['manual_adjustment_net_amount'] ?? 0),2,',','.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['gross_amount'] ?? 0),2,',','.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['net_amount'] ?? 0),2,',','.'); ?></td>
          <td class="text-end fw-semibold"><?php echo number_format($thpDay,2,',','.'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
