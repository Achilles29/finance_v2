<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$dateStart = $date_start ?? date('Y-m-01');
$dateEnd = $date_end ?? date('Y-m-t');
$summary = $summary ?? null;
$dailyRows = $daily_rows ?? [];
$generatedRows = $generated_rows ?? [];
$generatedSummary = $generated_summary ?? ['count' => 0, 'total_transfer' => 0];
$activeTab = strtolower((string)($this->input->get('tab') ?? 'estimate'));
if (!in_array($activeTab, ['estimate', 'generated'], true)) {
    $activeTab = 'estimate';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Estimasi Gaji Saya'); ?></h4>
    <small class="text-muted">Estimasi dari absensi (belum payroll final generate).</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/payroll'); ?>" class="d-flex gap-2">
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
<div class="alert alert-warning">Data pegawai belum terhubung ke akun ini.</div>
<?php else: ?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('my/payroll'); ?>" class="row g-2 align-items-end">
      <?php if ($selectedEmployeeId): ?>
      <input type="hidden" name="employee_id" value="<?php echo (int)$selectedEmployeeId; ?>">
      <?php endif; ?>
      <div class="col-md-3"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)$dateStart); ?>"></div>
      <div class="col-md-3"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)$dateEnd); ?>"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">Hitung</button></div>
    </form>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <button class="nav-link <?php echo $activeTab === 'estimate' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#my-payroll-estimate" type="button">
      Estimasi Gaji
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link <?php echo $activeTab === 'generated' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#my-payroll-generated" type="button">
      Riwayat Slip Gaji Final
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade <?php echo $activeTab === 'estimate' ? 'show active' : ''; ?>" id="my-payroll-estimate">
    <?php if ($summary): ?>
    <div class="row g-2 mb-3">
      <div class="col-md-4"><div class="card"><div class="card-body py-2"><small class="text-muted">Pegawai</small><div class="fw-semibold"><?php echo html_escape((string)$summary['employee_name']); ?></div><small><?php echo html_escape((string)$summary['employee_code']); ?></small></div></div></div>
      <div class="col-md-4"><div class="card"><div class="card-body py-2"><small class="text-muted">Periode</small><div class="fw-semibold"><?php echo html_escape((string)$summary['date_start']); ?> s/d <?php echo html_escape((string)$summary['date_end']); ?></div></div></div></div>
      <div class="col-md-4"><div class="card border-success"><div class="card-body py-2"><small class="text-muted">Estimasi Take Home Pay</small><div class="h5 mb-0 text-success"><?php echo number_format((float)$summary['net_estimate'],2,',','.'); ?></div><small class="text-muted">Sebelum penyesuaian: <?php echo number_format((float)($summary['net_estimate_before_adjustment'] ?? $summary['net_estimate']),2,',','.'); ?></small></div></div></div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-2"><small class="text-muted">Hadir</small><div class="fw-semibold"><?php echo (int)$summary['present_days']; ?> hari</div></div>
          <div class="col-md-2"><small class="text-muted">Alpha</small><div class="fw-semibold text-danger"><?php echo (int)$summary['alpha_days']; ?> hari</div></div>
          <div class="col-md-2"><small class="text-muted">Telat</small><div class="fw-semibold"><?php echo (int)$summary['late_minutes']; ?> menit</div></div>
          <div class="col-md-2"><small class="text-muted">Gross Est.</small><div class="fw-semibold"><?php echo number_format((float)$summary['gross_estimate'],2,',','.'); ?></div></div>
          <div class="col-md-2"><small class="text-muted">Pot. Telat</small><div class="fw-semibold text-danger"><?php echo number_format((float)$summary['late_deduction'],2,',','.'); ?></div></div>
          <div class="col-md-2"><small class="text-muted">Pot. Alpha</small><div class="fw-semibold text-danger"><?php echo number_format((float)$summary['alpha_deduction'],2,',','.'); ?></div></div>
          <div class="col-md-2"><small class="text-muted">Adj. Tambahan</small><div class="fw-semibold text-success"><?php echo number_format((float)($summary['manual_addition'] ?? 0),2,',','.'); ?></div></div>
          <div class="col-md-2"><small class="text-muted">Adj. Pengurangan</small><div class="fw-semibold text-danger"><?php echo number_format((float)($summary['manual_deduction'] ?? 0),2,',','.'); ?></div></div>
          <div class="col-md-2"><small class="text-muted">Pot. Kasbon</small><div class="fw-semibold text-danger"><?php echo number_format((float)($summary['cash_advance_cut'] ?? 0),2,',','.'); ?></div></div>
          <div class="col-md-2"><small class="text-muted">Net Adj.</small><div class="fw-semibold <?php echo ((float)($summary['manual_adjustment_net'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format((float)($summary['manual_adjustment_net'] ?? 0),2,',','.'); ?></div></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Shift</th>
              <th>Status</th>
              <th class="text-end">Telat</th>
              <th class="text-end">Work</th>
              <th class="text-end">Basic</th>
              <th class="text-end">Allowance</th>
              <th class="text-end">Meal</th>
              <th class="text-end">OT</th>
              <th class="text-end">Potongan</th>
              <th class="text-end">Adj. (+)</th>
              <th class="text-end">Adj. (-)</th>
              <th class="text-end">Pot. Kasbon</th>
              <th class="text-end">Adj. Net</th>
              <th class="text-end">Gross</th>
              <th class="text-end">Net</th>
              <th class="text-end">THP Harian</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($dailyRows)): ?>
            <tr><td colspan="17" class="text-center text-muted py-4">Tidak ada data absensi pada periode ini.</td></tr>
          <?php else: foreach($dailyRows as $r): ?>
            <tr>
              <td><?php echo html_escape((string)$r['attendance_date']); ?></td>
              <td><?php echo html_escape((string)$r['shift_code']); ?></td>
              <td><?php echo html_escape((string)$r['status']); ?></td>
              <td class="text-end"><?php echo (int)$r['late_minutes']; ?></td>
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
              <td class="text-end fw-semibold"><?php echo number_format((float)$r['day_total'],2,',','.'); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="tab-pane fade <?php echo $activeTab === 'generated' ? 'show active' : ''; ?>" id="my-payroll-generated">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Riwayat Gaji Final (Siap Cetak Slip)</strong>
        <small class="text-muted">Total transfer: <?php echo number_format((float)($generatedSummary['total_transfer'] ?? 0),2,',','.'); ?></small>
      </div>
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>No Batch</th>
              <th>Periode</th>
              <th>Tgl Cair</th>
              <th class="text-end">THP Full</th>
              <th class="text-end">Pembulatan</th>
              <th class="text-end">Transfer</th>
              <th>Status</th>
              <th>Paid At</th>
              <th class="text-center">Cetak</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($generatedRows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada gaji final yang digenerate pada rentang ini.</td></tr>
          <?php else: foreach($generatedRows as $g): ?>
            <?php
              $thpFull = (float)($g['net_pay_raw'] ?? ($g['net_pay'] ?? $g['transfer_amount'] ?? 0));
              $rounding = (float)($g['rounding_adjustment'] ?? ((float)($g['transfer_amount'] ?? 0) - $thpFull));
              $transfer = (float)($g['transfer_amount'] ?? 0);
            ?>
            <tr>
              <td><?php echo html_escape((string)($g['disbursement_no'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($g['period_code'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($g['period_start'] ?? '')); ?> s/d <?php echo html_escape((string)($g['period_end'] ?? '')); ?></div></td>
              <td><?php echo html_escape((string)($g['disbursement_date'] ?? '-')); ?></td>
              <td class="text-end"><?php echo number_format($thpFull,2,',','.'); ?></td>
              <td class="text-end <?php echo abs($rounding) > 0.009 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo number_format($rounding,2,',','.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format($transfer,2,',','.'); ?></td>
              <td><?php echo html_escape((string)($g['transfer_status'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($g['paid_at'] ?? '-')); ?></td>
              <td class="text-center">
                <a href="<?php echo site_url('my/payroll-slip/' . (int)($g['line_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Cetak Slip">
                  <i class="ri ri-printer-line"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
