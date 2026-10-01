<?php
$periodFilters = $period_filters ?? [];
$periodRows = $period_rows ?? [];
$periodPg = $period_pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 10, 'total' => 0];
$periodDetailRows = $period_detail_rows ?? [];
$periodBreakdownRows = $period_breakdown_rows ?? [];
$periodAudit = $period_audit ?? null;
$periodDetailId = (int)($period_detail_id ?? 0);
$detailTabDefault = $periodDetailId > 0 ? 'audit' : 'periods';

$buildQuery = static function ($overrides = []) use ($periodFilters, $periodPg) {
    $base = [
        'period_status' => $periodFilters['status'] ?? '',
        'period_q' => $periodFilters['q'] ?? '',
        'period_page' => $periodPg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Generate Payroll Period'); ?></h4>
    <small class="text-muted">Halaman ini khusus snapshot payroll period + review kecocokan sebelum pencairan batch.</small>
  </div>
</div>

<?php
$workspace_tabs = [
  ['label' => 'Generate Payroll Period', 'url' => site_url('payroll/payroll-periods'), 'active' => true],
  ['label' => 'Generate Batch Pencairan Gaji', 'url' => site_url('payroll/salary-disbursements'), 'active' => false],
];
$workspace_tab_label = 'Payroll';
$workspace_tab_aria_label = 'Navigasi proses payroll';
$this->load->view('layout/_workspace_tabs', get_defined_vars());
?>

<div class="card mb-3">
  <div class="card-header"><strong>Generate Payroll Period</strong></div>
  <div class="card-body">
    <form method="post" action="<?php echo site_url('payroll/salary-disbursements/period-generate'); ?>" class="row g-2">
      <div class="col-md-3"><label class="form-label mb-1">Kode</label><input type="text" name="period_code" class="form-control" value="<?php echo date('Y-m'); ?>"></div>
      <div class="col-md-3"><label class="form-label mb-1">Mulai</label><input type="date" name="period_start" class="form-control" required value="<?php echo date('Y-m-01'); ?>"></div>
      <div class="col-md-3"><label class="form-label mb-1">Akhir</label><input type="date" name="period_end" class="form-control" required value="<?php echo date('Y-m-t'); ?>"></div>
      <div class="col-md-3">
        <label class="form-label mb-1">Pembulatan Net</label>
        <select name="rounding_mode" class="form-select">
          <option value="NONE">Tanpa Pembulatan</option>
          <option value="UP_1000">Naik ke Ribuan</option>
        </select>
      </div>
      <div class="col-12"><label class="form-label mb-1">Catatan</label><textarea name="notes" class="form-control" rows="2" placeholder="Opsional"></textarea></div>
      <div class="col-12"><button type="submit" class="btn btn-primary" data-loading-label="Menghitung...">Generate / Refresh Period</button></div>
    </form>
  </div>
</div>

<ul class="nav nav-tabs mb-3" id="payrollPeriodDetailTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $detailTabDefault === 'periods' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#payroll-tab-periods" type="button" role="tab">Daftar Payroll Period</button>
  </li>
  <?php if ($periodDetailId > 0): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $detailTabDefault === 'audit' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#payroll-tab-audit" type="button" role="tab">Ringkasan Audit</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#payroll-tab-summary" type="button" role="tab">Summary Result Period #<?php echo $periodDetailId; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#payroll-tab-breakdown" type="button" role="tab">Breakdown Komponen Period #<?php echo $periodDetailId; ?></button>
  </li>
  <?php endif; ?>
</ul>

<div class="tab-content">
  <div class="tab-pane fade <?php echo $detailTabDefault === 'periods' ? 'show active' : ''; ?>" id="payroll-tab-periods" role="tabpanel">
    <div class="card">
      <div class="card-header"><strong>Daftar Payroll Period</strong></div>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead><tr><th>Periode</th><th>Status</th><th class="text-end">Pegawai</th><th class="text-end">Net</th><th class="text-center">Aksi</th></tr></thead>
          <tbody>
            <?php if (empty($periodRows)): ?><tr><td colspan="5" class="text-center text-muted py-3">Belum ada payroll period.</td></tr><?php else: foreach($periodRows as $p): ?>
            <tr>
              <td><a href="<?php echo site_url('payroll/payroll-periods?' . $buildQuery(['period_detail_id' => (int)$p['id']])); ?>"><?php echo html_escape((string)$p['period_code']); ?></a><div class="small text-muted"><?php echo html_escape((string)$p['period_start']); ?> s/d <?php echo html_escape((string)$p['period_end']); ?></div></td>
              <td><?php echo html_escape((string)$p['status']); ?></td>
              <td class="text-end"><?php echo (int)($p['employee_count'] ?? 0); ?></td>
              <td class="text-end"><?php echo number_format((float)($p['net_pay_total'] ?? 0), 2, ',', '.'); ?></td>
              <td class="action-cell text-center">
                <a href="<?php echo site_url('payroll/payroll-periods?' . $buildQuery(['period_detail_id' => (int)$p['id']])); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Detail"><i class="ri ri-eye-line"></i></a>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/period-void/' . (int)$p['id']); ?>" class="d-inline" data-confirm="Reset period ini ke DRAFT?">
                  <button type="submit" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Reset/VOID"><i class="ri ri-restart-line"></i></button>
                </form>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/period-delete/' . (int)$p['id']); ?>" class="d-inline" data-confirm="Hapus period ini?">
                  <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus"><i class="ri ri-delete-bin-line"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php if ($periodDetailId > 0): ?>
<?php
  $auditPayload = (array)$periodAudit;
  $auditSummary = (array)($auditPayload['summary'] ?? []);
  $auditMismatchRows = (array)($auditPayload['mismatch_rows'] ?? []);
  $auditDupResult = (array)($auditPayload['duplicates_result'] ?? []);
  $auditDupDisb = (array)($auditPayload['duplicates_disbursement'] ?? []);
  $auditPeriod = (array)($auditPayload['period'] ?? []);
  $auditHasActiveBatch = (int)($auditSummary['active_disbursement_batch_count'] ?? 0) > 0;
  $auditHasActualFindings = (
      (int)($auditSummary['mismatch_rows'] ?? 0) > 0
      || (int)($auditSummary['result_duplicates'] ?? 0) > 0
      || (int)($auditSummary['active_disbursement_duplicates'] ?? 0) > 0
  );
  $auditRoundingTotal = round(
      (float)($auditSummary['result_net_final_total'] ?? 0)
      - (float)($auditSummary['result_net_raw_total'] ?? 0),
      2
  );
  $auditMoney = static function ($amount): string {
      return 'Rp ' . number_format((float)$amount, 2, ',', '.');
  };
  if ($auditHasActualFindings) {
      $auditStateClass = 'review';
      $auditStateLabel = 'Perlu ditindaklanjuti';
      $auditStateIcon = 'ri-error-warning-line';
  } elseif ($auditHasActiveBatch) {
      $auditStateClass = 'matched';
      $auditStateLabel = 'Sumber payroll cocok';
      $auditStateIcon = 'ri-shield-check-line';
  } else {
      $auditStateClass = 'awaiting';
      $auditStateLabel = 'Siap untuk pencairan';
      $auditStateIcon = 'ri-time-line';
  }
?>
<div class="tab-pane fade <?php echo $detailTabDefault === 'audit' ? 'show active' : ''; ?>" id="payroll-tab-audit" role="tabpanel">
<style>
  .payroll-audit-card { border: 1px solid #eadfd8; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 30px rgba(89, 34, 29, .07); }
  .payroll-audit-head { padding: 1.05rem 1.15rem; background: linear-gradient(112deg, #fffaf5 0%, #fff 64%, #f8ece4 100%); border-bottom: 1px solid #efe2da; }
  .payroll-audit-kicker { color: #9d3329; font-size: .69rem; font-weight: 800; letter-spacing: .11em; text-transform: uppercase; }
  .payroll-audit-title { margin: .15rem 0 0; color: #342522; font-size: 1.08rem; font-weight: 800; }
  .payroll-audit-meta { color: #927b73; font-size: .8rem; }
  .payroll-audit-state { display: inline-flex; align-items: center; gap: .38rem; padding: .42rem .68rem; border-radius: 999px; font-size: .77rem; font-weight: 800; white-space: nowrap; }
  .payroll-audit-state.matched { background: #e7f6ed; color: #197342; }
  .payroll-audit-state.awaiting { background: #fff3d8; color: #926000; }
  .payroll-audit-state.review { background: #fee8e7; color: #b3261e; }
  .payroll-audit-metrics { display: grid; grid-template-columns: repeat(6, minmax(145px, 1fr)); gap: .72rem; padding: 1rem 1.15rem; background: #fffdfb; }
  .payroll-audit-metric { min-height: 98px; padding: .78rem .82rem; border: 1px solid #f0e4dd; border-radius: 12px; background: #fff; }
  .payroll-audit-metric-label { display: block; color: #90776d; font-size: .68rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }
  .payroll-audit-metric-value { display: block; margin-top: .35rem; color: #3b2925; font-size: .98rem; font-weight: 800; line-height: 1.2; }
  .payroll-audit-metric-note { display: block; margin-top: .28rem; color: #a18a80; font-size: .72rem; line-height: 1.25; }
  .payroll-audit-metric.good .payroll-audit-metric-value { color: #187343; }
  .payroll-audit-metric.warn .payroll-audit-metric-value { color: #a06300; }
  .payroll-audit-metric.danger .payroll-audit-metric-value { color: #bd2d25; }
  .payroll-audit-note { display: flex; align-items: flex-start; gap: .65rem; margin: 0 1.15rem 1rem; padding: .72rem .82rem; border-radius: 11px; font-size: .82rem; line-height: 1.42; }
  .payroll-audit-note i { font-size: 1rem; margin-top: .05rem; }
  .payroll-audit-note.ok { color: #17663c; background: #ecf8f0; border: 1px solid #d7f0e0; }
  .payroll-audit-note.info { color: #805900; background: #fff8e9; border: 1px solid #f8e5b4; }
  .payroll-audit-note.review { color: #9e291f; background: #fff0ee; border: 1px solid #f6d0cb; }
  .payroll-audit-empty { display: flex; align-items: center; gap: .7rem; margin: 0 1.15rem 1.15rem; padding: .85rem .95rem; border: 1px dashed #cfe4d5; border-radius: 12px; color: #247246; background: #fbfffc; font-size: .84rem; }
  .payroll-audit-empty i { font-size: 1.25rem; }
  .payroll-audit-findings { border-top: 1px solid #f0e4dd; }
  .payroll-audit-findings-title { padding: .8rem 1.15rem .55rem; color: #4a3430; font-size: .88rem; font-weight: 800; }
  .payroll-audit-findings .table { font-size: .8rem; }
  .payroll-audit-findings th { color: #7f2b29; font-size: .69rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
  .payroll-audit-issue { display: inline-flex; align-items: center; gap: .32rem; border-radius: 999px; padding: .28rem .46rem; font-size: .7rem; font-weight: 800; }
  .payroll-audit-issue.warning { color: #9a6500; background: #fff3d9; }
  .payroll-audit-issue.danger { color: #ad2c23; background: #fee8e7; }
  @media (max-width: 1199.98px) { .payroll-audit-metrics { grid-template-columns: repeat(3, minmax(170px, 1fr)); } }
  @media (max-width: 767.98px) { .payroll-audit-head { padding: .9rem; } .payroll-audit-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: .9rem; gap: .55rem; } .payroll-audit-metric { min-height: 90px; padding: .68rem; } .payroll-audit-note, .payroll-audit-empty { margin-left: .9rem; margin-right: .9rem; } }
</style>
<div class="card payroll-audit-card">
  <div class="payroll-audit-head d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <div class="payroll-audit-kicker">Audit payroll period</div>
      <h5 class="payroll-audit-title"><?php echo html_escape((string)($auditPeriod['period_code'] ?? ('Period #' . $periodDetailId))); ?></h5>
      <div class="payroll-audit-meta">
        <?php echo html_escape((string)($auditPeriod['period_start'] ?? '-')); ?> s/d <?php echo html_escape((string)($auditPeriod['period_end'] ?? '-')); ?>
        <span class="mx-1">|</span><?php echo html_escape((string)($auditPeriod['status'] ?? '-')); ?>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="payroll-audit-state <?php echo $auditStateClass; ?>"><i class="ri <?php echo $auditStateIcon; ?>"></i><?php echo $auditStateLabel; ?></span>
      <?php if (!$auditHasActiveBatch && (float)($auditSummary['result_net_final_total'] ?? 0) > 0.009): ?>
        <a class="btn btn-sm btn-primary" href="<?php echo site_url('payroll/salary-disbursements?gen_payroll_period_id=' . $periodDetailId); ?>"><i class="ri ri-bank-card-line me-1"></i>Buat Batch Pencairan</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="payroll-audit-metrics">
    <div class="payroll-audit-metric">
      <span class="payroll-audit-metric-label">Payroll siap proses</span>
      <span class="payroll-audit-metric-value"><?php echo (int)($auditSummary['result_rows'] ?? 0); ?> pegawai</span>
      <span class="payroll-audit-metric-note">Result payroll final pada period ini.</span>
    </div>
    <div class="payroll-audit-metric good">
      <span class="payroll-audit-metric-label">THP riil dan absensi</span>
      <span class="payroll-audit-metric-value"><?php echo $auditMoney($auditSummary['result_net_raw_total'] ?? 0); ?></span>
      <span class="payroll-audit-metric-note">Selisih absensi <?php echo $auditMoney($auditSummary['raw_vs_attendance_diff_total'] ?? 0); ?>.</span>
    </div>
    <div class="payroll-audit-metric">
      <span class="payroll-audit-metric-label">THP final payroll</span>
      <span class="payroll-audit-metric-value"><?php echo $auditMoney($auditSummary['result_net_final_total'] ?? 0); ?></span>
      <span class="payroll-audit-metric-note">Pembulatan <?php echo $auditMoney($auditRoundingTotal); ?>.</span>
    </div>
    <div class="payroll-audit-metric <?php echo $auditHasActiveBatch ? 'good' : 'warn'; ?>">
      <span class="payroll-audit-metric-label"><?php echo $auditHasActiveBatch ? 'Batch pencairan tercatat' : 'Menunggu pencairan'; ?></span>
      <span class="payroll-audit-metric-value"><?php echo $auditHasActiveBatch ? $auditMoney($auditSummary['active_disbursement_transfer_total'] ?? 0) : $auditMoney($auditSummary['pending_disbursement_total'] ?? 0); ?></span>
      <span class="payroll-audit-metric-note"><?php echo $auditHasActiveBatch ? ((int)($auditSummary['active_disbursement_batch_count'] ?? 0) . ' batch sedang diaudit.') : ((int)($auditSummary['pending_disbursement_rows'] ?? 0) . ' pegawai belum masuk batch.'); ?></span>
    </div>
    <div class="payroll-audit-metric <?php echo (int)($auditSummary['mismatch_rows'] ?? 0) > 0 ? 'danger' : 'good'; ?>">
      <span class="payroll-audit-metric-label">Temuan aktual</span>
      <span class="payroll-audit-metric-value"><?php echo (int)($auditSummary['mismatch_rows'] ?? 0); ?> baris</span>
      <span class="payroll-audit-metric-note">Absensi <?php echo (int)($auditSummary['attendance_mismatch_rows'] ?? 0); ?> | pencairan <?php echo (int)($auditSummary['transfer_mismatch_rows'] ?? 0); ?>.</span>
    </div>
    <div class="payroll-audit-metric <?php echo ((int)($auditSummary['result_duplicates'] ?? 0) + (int)($auditSummary['active_disbursement_duplicates'] ?? 0)) > 0 ? 'danger' : 'good'; ?>">
      <span class="payroll-audit-metric-label">Duplikasi</span>
      <span class="payroll-audit-metric-value"><?php echo (int)($auditSummary['result_duplicates'] ?? 0) + (int)($auditSummary['active_disbursement_duplicates'] ?? 0); ?> temuan</span>
      <span class="payroll-audit-metric-note">Payroll <?php echo (int)($auditSummary['result_duplicates'] ?? 0); ?> | batch <?php echo (int)($auditSummary['active_disbursement_duplicates'] ?? 0); ?>.</span>
    </div>
  </div>

  <?php if (!$auditHasActiveBatch): ?>
    <div class="payroll-audit-note info"><i class="ri ri-information-line"></i><div>Belum ada batch pencairan untuk periode ini. Nilai transfer nol adalah kondisi normal dan tidak lagi dihitung sebagai mismatch. Semua <?php echo (int)($auditSummary['pending_disbursement_rows'] ?? 0); ?> pegawai siap dimasukkan ke batch.</div></div>
  <?php elseif ($auditHasActualFindings): ?>
    <div class="payroll-audit-note review"><i class="ri ri-error-warning-line"></i><div>Audit menemukan perbedaan aktual. Periksa rincian di bawah sebelum status pencairan dilanjutkan.</div></div>
  <?php else: ?>
    <div class="payroll-audit-note ok"><i class="ri ri-checkbox-circle-line"></i><div>THP riil sudah cocok dengan sumber absensi. Pencairan aktif juga tidak memiliki selisih maupun duplikasi.</div></div>
  <?php endif; ?>

  <?php if (strtoupper((string)($auditPeriod['rounding_mode'] ?? 'NONE')) !== 'NONE'): ?>
    <div class="payroll-audit-note info"><i class="ri ri-calculator-line"></i><div>Pembulatan <strong><?php echo html_escape((string)($auditPeriod['rounding_mode'] ?? '')); ?></strong> menambah total <?php echo $auditMoney($auditRoundingTotal); ?> dari THP riil ke THP final. Ini bukan selisih absensi.</div></div>
  <?php endif; ?>

  <?php if (!$auditHasActualFindings): ?>
    <div class="payroll-audit-empty"><i class="ri ri-shield-check-line"></i><div><strong>Tidak ada temuan yang perlu dicek.</strong><br>Detail per pegawai tetap tersedia pada tab Summary Result untuk review komponen dan pembulatannya.</div></div>
  <?php else: ?>
    <div class="payroll-audit-findings">
      <div class="payroll-audit-findings-title">Temuan yang perlu ditindaklanjuti</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Jenis</th><th>Pegawai / Referensi</th><th class="text-end">Absensi</th><th class="text-end">Pencairan</th><th>Catatan</th></tr></thead>
          <tbody>
            <?php foreach ($auditDupResult as $x): ?>
              <tr><td><span class="payroll-audit-issue danger"><i class="ri ri-file-copy-2-line"></i>Payroll ganda</span></td><td>Pegawai ID <?php echo (int)($x['employee_id'] ?? 0); ?></td><td class="text-end">-</td><td class="text-end">-</td><td>Result payroll: <?php echo html_escape((string)($x['payroll_result_ids'] ?? '-')); ?></td></tr>
            <?php endforeach; ?>
            <?php foreach ($auditDupDisb as $x): ?>
              <tr><td><span class="payroll-audit-issue danger"><i class="ri ri-file-copy-2-line"></i>Batch ganda</span></td><td>Payroll result #<?php echo (int)($x['payroll_result_id'] ?? 0); ?></td><td class="text-end">-</td><td class="text-end">-</td><td>Baris batch: <?php echo html_escape((string)($x['line_refs'] ?? '-')); ?></td></tr>
            <?php endforeach; ?>
            <?php foreach ($auditMismatchRows as $x): ?>
              <?php $issueText = !empty($x['attendance_mismatch']) ? 'Absensi tidak cocok' : 'Pencairan tidak cocok'; ?>
              <tr>
                <td><span class="payroll-audit-issue warning"><i class="ri ri-error-warning-line"></i><?php echo $issueText; ?></span></td>
                <td><strong><?php echo html_escape((string)($x['employee_name_snapshot'] ?? '-')); ?></strong><div class="text-muted small"><?php echo html_escape((string)($x['employee_code_snapshot'] ?? '-')); ?></div></td>
                <td class="text-end <?php echo !empty($x['attendance_mismatch']) ? 'text-danger fw-semibold' : 'text-success'; ?>"><?php echo $auditMoney($x['diff_raw_vs_attendance'] ?? 0); ?></td>
                <td class="text-end <?php echo !empty($x['transfer_mismatch']) ? 'text-danger fw-semibold' : 'text-success'; ?>"><?php echo $auditMoney($x['diff_transfer_vs_final'] ?? 0); ?></td>
                <td>THP final <?php echo $auditMoney($x['result_net_final'] ?? 0); ?> | absensi <?php echo $auditMoney($x['attendance_net'] ?? 0); ?> | batch <?php echo $auditMoney($x['active_transfer_total'] ?? 0); ?>.</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
</div>

<div class="tab-pane fade" id="payroll-tab-summary" role="tabpanel">
<div class="card">
  <div class="card-header">
    <strong>Summary Result Period #<?php echo $periodDetailId; ?></strong>
    <div class="small text-muted mt-1">THP kontrak membaca snapshot kontrak pada absensi periode ini: gaji pokok, tunjangan jabatan, dan tunjangan objektif. Uang makan, lembur, dan penyesuaian dipisahkan dari pembanding. Jika kontrak berubah di tengah periode, sistem menandainya dan tidak memaksakan satu selisih bulanan.</div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Pegawai</th><th class="text-end">Total Kotor (Riil)</th><th class="text-end">THP Kontrak<br><small>tanpa uang makan</small></th><th class="text-end">Riil Gaji Tetap<br><small>sesudah absensi</small></th><th class="text-end">Selisih Riil<br><small>vs kontrak</small></th><th class="text-end">Pembulatan</th><th class="text-end">THP Final (Payroll)</th><th class="text-end">Validasi ke Absensi</th><th>Status</th></tr></thead>
      <tbody>
        <?php if(empty($periodDetailRows)): ?>
          <tr><td colspan="9" class="text-center text-muted py-3">Tidak ada result.</td></tr>
        <?php else: foreach($periodDetailRows as $r):
          $payrollNet = (float)($r['net_pay'] ?? 0);
          $riilNet = (float)($r['net_pay_raw'] ?? $payrollNet);
          $roundingDiff = round((float)($r['rounding_adjustment'] ?? ($payrollNet - $riilNet)), 2);
          $attendanceNet = (float)($r['attendance_net_total'] ?? 0);
          $guardDiff = round($riilNet - $attendanceNet, 2);
          $contractFixed = array_key_exists('contract_fixed_total', $r) && $r['contract_fixed_total'] !== null
            ? (float)$r['contract_fixed_total']
            : null;
          $contractStatus = (string)($r['contract_compensation_status'] ?? 'MISSING');
          $contractRef = (string)($r['contract_source_ref'] ?? '-');
          $contractBasis = (string)($r['contract_basis_label'] ?? 'Nominal kontrak periode ini');
          $fixedActual = (float)($r['fixed_actual_total'] ?? 0);
          $contractDiff = $r['contract_vs_actual_diff'] ?? null;
          $contractDiff = $contractDiff !== null ? (float)$contractDiff : null;
          $contractDiffClass = $contractDiff === null
            ? 'text-muted'
            : (abs($contractDiff) <= 0.009 ? 'text-success' : ($contractDiff < 0 ? 'text-danger' : 'text-primary'));
        ?>
          <tr>
            <td><?php echo html_escape((string)($r['employee_name_snapshot'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($r['employee_code_snapshot'] ?? '')); ?></div></td>
            <td class="text-end"><?php echo number_format((float)($r['gross_pay'] ?? 0),2,',','.'); ?></td>
            <td class="text-end">
              <?php if ($contractFixed !== null): ?>
                <div class="fw-semibold"><?php echo number_format($contractFixed,2,',','.'); ?></div>
                <div class="small text-muted"><?php echo html_escape($contractStatus . ' | ' . $contractRef); ?></div>
                <div class="small text-muted"><?php echo html_escape($contractBasis); ?></div>
              <?php else: ?>
                <span class="text-warning">-</span><div class="small text-muted">Snapshot kontrak tidak tersedia</div>
              <?php endif; ?>
            </td>
            <td class="text-end"><?php echo number_format($fixedActual,2,',','.'); ?></td>
            <td class="text-end <?php echo $contractDiffClass; ?>">
              <?php if ($contractDiff !== null): ?><?php echo $contractDiff > 0.009 ? '+' : ''; ?><?php echo number_format($contractDiff,2,',','.'); ?><?php else: ?>-<?php endif; ?>
            </td>
            <td class="text-end <?php echo abs($roundingDiff) > 0.009 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo number_format($roundingDiff,2,',','.'); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($payrollNet,2,',','.'); ?></td>
            <td class="text-end <?php echo abs($guardDiff) > 0.009 ? 'text-danger fw-semibold' : 'text-success'; ?>"><?php echo number_format($guardDiff,2,',','.'); ?></td>
            <td><?php echo html_escape((string)($r['status'] ?? '-')); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div class="tab-pane fade" id="payroll-tab-breakdown" role="tabpanel">
<div class="card">
  <div class="card-header">
    <strong>Breakdown Komponen Period #<?php echo $periodDetailId; ?></strong>
    <div class="small text-muted mt-1">Kolom uang makan adalah total hak. Pada period baru, mode Bulanan masuk THP final; mode Custom dibayar melalui batch Pencairan Uang Makan. Selisih membandingkan THP kontrak tanpa uang makan dengan nilai gaji tetap yang benar-benar diperoleh dari absensi.</div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Pegawai</th><th class="text-end">Gaji Pokok</th><th class="text-end">Tunjangan</th><th class="text-end">U. Makan<br><small>total hak</small></th><th class="text-end">Lembur</th><th class="text-end">Adj (+)</th><th class="text-end">Kotor Riil</th><th class="text-end">Pot. Telat</th><th class="text-end">Pot. Alpha</th><th class="text-end">Adj (-) Lain</th><th class="text-end">Pot. Kasbon</th><th class="text-end">THP Kontrak<br><small>tanpa uang makan</small></th><th class="text-end">Riil Gaji Tetap<br><small>sesudah absensi</small></th><th class="text-end">Selisih Riil<br><small>vs kontrak</small></th><th class="text-end">Pembulatan</th><th class="text-end">THP Final</th></tr></thead>
      <tbody>
        <?php if(empty($periodBreakdownRows)): ?>
          <tr><td colspan="16" class="text-center text-muted py-3">Tidak ada breakdown.</td></tr>
        <?php else: foreach($periodBreakdownRows as $b): ?>
          <?php
            $basic = (float)($b['basic_total'] ?? 0);
            $allowance = (float)($b['allowance_total'] ?? 0);
            $meal = (float)($b['meal_total'] ?? 0);
            $overtime = (float)($b['overtime_total'] ?? 0);
            $manualAdd = (float)($b['manual_addition_total'] ?? 0);
            $lateDed = (float)($b['late_deduction_total'] ?? 0);
            $alphaDed = (float)($b['alpha_deduction_total'] ?? 0);
            $manualDedTotal = (float)($b['manual_deduction_total'] ?? 0);
            $cashCut = (float)($b['cash_advance_cut'] ?? 0);
            $manualDedOther = max(0, round($manualDedTotal - $cashCut, 2));
            $grossRiil = round((float)($b['gross_pay'] ?? ($basic + $allowance + $meal + $overtime + $manualAdd)), 2);
            $finalNet = (float)($b['net_pay'] ?? 0);
            $riilNet = (float)($b['net_pay_raw'] ?? ($finalNet - (float)($b['rounding_adjustment'] ?? 0)));
            $roundingAdj = round((float)($b['rounding_adjustment'] ?? ($finalNet - $riilNet)), 2);
            $contractFixed = array_key_exists('contract_fixed_total', $b) && $b['contract_fixed_total'] !== null
              ? (float)$b['contract_fixed_total']
              : null;
            $contractStatus = (string)($b['contract_compensation_status'] ?? 'MISSING');
            $contractRef = (string)($b['contract_source_ref'] ?? '-');
            $contractBasis = (string)($b['contract_basis_label'] ?? 'Nominal kontrak periode ini');
            $fixedActual = (float)($b['fixed_actual_total'] ?? 0);
            $contractDiff = $b['contract_vs_actual_diff'] ?? null;
            $contractDiff = $contractDiff !== null ? (float)$contractDiff : null;
            $contractDiffClass = $contractDiff === null
              ? 'text-muted'
              : (abs($contractDiff) <= 0.009 ? 'text-success' : ($contractDiff < 0 ? 'text-danger' : 'text-primary'));
          ?>
          <tr>
            <td><?php echo html_escape((string)($b['employee_name_snapshot'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($b['employee_code_snapshot'] ?? '')); ?></div></td>
            <td class="text-end"><?php echo number_format($basic,2,',','.'); ?></td>
            <td class="text-end"><?php echo number_format($allowance,2,',','.'); ?></td>
            <td class="text-end"><?php echo number_format($meal,2,',','.'); ?></td>
            <td class="text-end"><?php echo number_format($overtime,2,',','.'); ?></td>
            <td class="text-end text-success"><?php echo number_format($manualAdd,2,',','.'); ?></td>
            <td class="text-end"><?php echo number_format($grossRiil,2,',','.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format($lateDed,2,',','.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format($alphaDed,2,',','.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format($manualDedOther,2,',','.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format($cashCut,2,',','.'); ?></td>
            <td class="text-end" title="<?php echo html_escape($contractStatus . ' | ' . $contractRef . ' | ' . $contractBasis); ?>"><?php echo $contractFixed !== null ? number_format($contractFixed,2,',','.') : '-'; ?></td>
            <td class="text-end"><?php echo number_format($fixedActual,2,',','.'); ?></td>
            <td class="text-end <?php echo $contractDiffClass; ?>"><?php if ($contractDiff !== null): ?><?php echo $contractDiff > 0.009 ? '+' : ''; ?><?php echo number_format($contractDiff,2,',','.'); ?><?php else: ?>-<?php endif; ?></td>
            <td class="text-end <?php echo abs($roundingAdj) > 0.009 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo number_format($roundingAdj,2,',','.'); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($finalNet,2,',','.'); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php endif; ?>
</div>
