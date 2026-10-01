<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$detailHeader = $detail_header ?? null;
$detailLineBreakdown = $detail_line_breakdown ?? [];
$detailAccountRecap = $detail_account_recap ?? [];
$periodOptions = $payroll_period_options ?? [];
$accounts = $company_account_options ?? [];
$gen = $gen ?? ['payroll_period_id' => 0, 'disbursement_date' => date('Y-m-d'), 'notes' => ''];
$previewRows = $preview_rows ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'status' => $filters['status'] ?? '',
        'payroll_period_id' => $filters['payroll_period_id'] ?? '',
        'q' => $filters['q'] ?? '',
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Pencairan Gaji'); ?></h4>
    <small class="text-muted">Generate batch pencairan dari payroll period yang sudah difinalisasi.</small>
  </div>
</div>

<?php
$workspace_tabs = [
  ['label' => 'Generate Payroll Period', 'url' => site_url('payroll/payroll-periods'), 'active' => false],
  ['label' => 'Generate Batch Pencairan Gaji', 'url' => site_url('payroll/salary-disbursements'), 'active' => true],
];
$workspace_tab_label = 'Payroll';
$workspace_tab_aria_label = 'Navigasi proses payroll';
$this->load->view('layout/_workspace_tabs', get_defined_vars());
?>

<div class="alert alert-info py-2 px-3 mb-3 small">
  Periksa detail payroll period lebih dulu di halaman terpisah, lalu generate batch pencairan di halaman ini.
</div>

<div class="card mb-3">
  <div class="card-header"><strong>Generate Batch Pencairan Gaji</strong></div>
  <div class="card-body">
    <form method="get" action="<?php echo site_url('payroll/salary-disbursements'); ?>" class="row g-2 mb-3">
      <div class="col-md-4"><label class="form-label mb-1">Payroll Period</label><select name="gen_payroll_period_id" class="form-select" required><option value="">Pilih period</option><?php foreach($periodOptions as $p): ?><option value="<?php echo (int)$p['value']; ?>" <?php echo ((int)$gen['payroll_period_id'] === (int)$p['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$p['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label mb-1">Tgl Pencairan</label><input type="date" name="gen_disbursement_date" class="form-control" required value="<?php echo html_escape((string)$gen['disbursement_date']); ?>"></div>
      <div class="col-md-5"><label class="form-label mb-1">Catatan</label><input type="text" name="gen_notes" class="form-control" placeholder="Opsional" value="<?php echo html_escape((string)$gen['notes']); ?>"></div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Preview Kandidat</button>
        <?php if ((int)$gen['payroll_period_id'] > 0): ?>
          <a href="<?php echo site_url('payroll/salary-disbursements'); ?>" class="btn btn-outline-secondary">Reset Preview</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ((int)$gen['payroll_period_id'] > 0): ?>
      <form method="post" action="<?php echo site_url('payroll/salary-disbursements/generate'); ?>" class="row g-2">
        <input type="hidden" name="payroll_period_id" value="<?php echo (int)$gen['payroll_period_id']; ?>">
        <input type="hidden" name="disbursement_date" value="<?php echo html_escape((string)$gen['disbursement_date']); ?>">
        <input type="hidden" name="notes" value="<?php echo html_escape((string)$gen['notes']); ?>">
        <?php
          $previewCount = count($previewRows);
          $previewThpFull = 0.0;
          $previewTransfer = 0.0;
          foreach ($previewRows as $pr) {
              $previewThpFull += (float)($pr['net_pay_raw'] ?? ($pr['net_pay'] ?? 0));
              $previewTransfer += (float)($pr['net_pay'] ?? 0);
          }
        ?>
        <div class="col-12">
          <div class="small text-muted mb-2">
            Default rekening sumber perusahaan dipilih otomatis berdasarkan bank yang sama dengan rekening pegawai (untuk minim biaya transfer). Jika perlu, bisa override per pegawai.
          </div>
          <?php if ($previewCount > 0): ?>
            <div class="small fw-semibold mb-2">
              Kandidat: <?php echo (int)$previewCount; ?> pegawai | Total THP Full: <?php echo number_format($previewThpFull, 2, ',', '.'); ?> | Total Transfer: <?php echo number_format($previewTransfer, 2, ',', '.'); ?>
            </div>
          <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-2">
              <thead>
                <tr>
                  <th>Pegawai</th>
                  <th>Bank Pegawai</th>
                  <th>No Rekening</th>
                  <th>Rekening Perusahaan Digunakan</th>
                  <th class="text-end">THP Full</th>
                  <th class="text-end">Transfer</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($previewRows)): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada kandidat baru untuk period ini.</td></tr>
                <?php else: foreach($previewRows as $r): ?>
                  <tr>
                    <td><?php echo html_escape((string)($r['employee_name_snapshot'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($r['employee_code_snapshot'] ?? '')); ?></div></td>
                    <td><?php echo html_escape((string)($r['bank_name'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($r['bank_account_no'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($r['bank_account_name'] ?? '-')); ?></div></td>
                    <td>
                      <select class="form-select form-select-sm" name="employee_source_account[<?php echo (int)($r['employee_id'] ?? 0); ?>]">
                        <option value="">Auto (sesuai bank)</option>
                        <?php foreach($accounts as $a): ?>
                          <option value="<?php echo (int)$a['value']; ?>" <?php echo ((int)($r['source_account_id'] ?? 0) === (int)$a['value']) ? 'selected' : ''; ?>>
                            <?php echo html_escape((string)$a['label']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <?php if (!empty($r['source_account_label'])): ?>
                        <div class="small text-muted mt-1">Default: <?php echo html_escape((string)$r['source_account_label']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?php echo number_format((float)($r['net_pay_raw'] ?? ($r['net_pay'] ?? 0)), 2, ',', '.'); ?></td>
                    <td class="text-end fw-semibold"><?php echo number_format((float)($r['net_pay'] ?? 0), 2, ',', '.'); ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
              <?php if (!empty($previewRows)): ?>
                <tfoot>
                  <tr>
                    <th colspan="4" class="text-end">TOTAL PREVIEW</th>
                    <th class="text-end"><?php echo number_format($previewThpFull, 2, ',', '.'); ?></th>
                    <th class="text-end"><?php echo number_format($previewTransfer, 2, ',', '.'); ?></th>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary" data-loading-label="Generating...">Generate Batch dari Preview</button>
          <button type="submit" class="btn btn-outline-primary" name="ignore_preview_map" value="1" data-loading-label="Generating...">Generate Otomatis (Tanpa Override)</button>
        </div>
      </form>
    <?php else: ?>
      <form method="post" action="<?php echo site_url('payroll/salary-disbursements/generate'); ?>" class="row g-2">
        <div class="col-md-4"><label class="form-label mb-1">Payroll Period</label><select name="payroll_period_id" class="form-select" required><option value="">Pilih period</option><?php foreach($periodOptions as $p): ?><option value="<?php echo (int)$p['value']; ?>"><?php echo html_escape((string)$p['label']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label mb-1">Tgl Pencairan</label><input type="date" name="disbursement_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="col-md-5"><label class="form-label mb-1">Catatan</label><input type="text" name="notes" class="form-control" placeholder="Opsional"></div>
        <div class="col-12"><small class="text-muted">Jika generate langsung, sistem otomatis memilih rekening sumber perusahaan per pegawai berdasarkan kecocokan bank.</small></div>
        <div class="col-12"><button type="submit" class="btn btn-primary" data-loading-label="Generating...">Generate Otomatis</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>No Batch</th><th>Period</th><th>Tgl</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Baris</th><th class="text-center">Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada batch pencairan gaji.</td></tr><?php else: foreach($rows as $r): $status = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
          <tr>
            <td><?php echo html_escape((string)$r['disbursement_no']); ?></td>
            <td><?php echo html_escape((string)($r['period_code'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)$r['disbursement_date']); ?></td>
            <td><span class="badge bg-<?php echo $status === 'PAID' ? 'success' : ($status === 'VOID' ? 'danger' : 'warning'); ?>"><?php echo html_escape($status); ?></span></td>
            <td class="text-end fw-semibold"><?php echo number_format((float)($r['total_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($r['paid_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo (int)($r['line_count'] ?? 0); ?></td>
            <td class="action-cell text-center">
              <a href="<?php echo site_url('payroll/salary-disbursements?' . $buildQuery(['detail_id' => (int)$r['id']])); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Detail"><i class="ri ri-eye-line"></i></a>
              <?php if ($status !== 'PAID' && $status !== 'VOID'): ?>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/mark-paid/' . (int)$r['id']); ?>" class="d-inline">
                  <button type="submit" class="btn btn-sm btn-outline-success action-icon-btn" data-bs-toggle="tooltip" title="Tandai Paid" data-loading-label="Posting..."><i class="ri ri-money-dollar-circle-line"></i></button>
                </form>
              <?php endif; ?>
              <?php if ($status !== 'VOID'): ?>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/void/' . (int)$r['id']); ?>" class="d-inline" data-confirm="VOID batch gaji ini?">
                  <button type="submit" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Void (Bisa Generate Ulang)" data-loading-label="Void..."><i class="ri ri-close-circle-line"></i></button>
                </form>
              <?php endif; ?>
              <?php if ($status !== 'PAID' && $status !== 'VOID'): ?>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/delete/' . (int)$r['id']); ?>" class="d-inline" data-confirm="Hapus batch gaji ini?">
                  <button type="submit" class="btn btn-sm btn-outline-secondary action-icon-btn" data-bs-toggle="tooltip" title="Hapus" data-loading-label="Hapus..."><i class="ri ri-delete-bin-line"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($detailHeader)): ?>
<?php
  $recapTotalPayNow = 0.0;
  $recapTotalPaid = 0.0;
  $recapTotalAmount = 0.0;
  $recapTotalBalance = 0.0;
  $recapTotalShortfall = 0.0;
  foreach ($detailAccountRecap as $ar) {
      $recapTotalPayNow += (float)($ar['pay_now_amount'] ?? 0);
      $recapTotalPaid += (float)($ar['paid_amount'] ?? 0);
      $recapTotalAmount += (float)($ar['total_amount'] ?? 0);
      $recapTotalBalance += (float)($ar['current_balance'] ?? 0);
      $recapTotalShortfall += (float)($ar['shortfall_amount'] ?? 0);
  }
?>
<div class="card mt-3 border-0 shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <strong>Rekap Rekening Pembayaran Gaji</strong>
      <div class="small text-muted">Nominal yang masih harus dibayar disandingkan dengan saldo master rekening saat ini.</div>
    </div>
    <?php if ($recapTotalShortfall > 0.009): ?>
      <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
        Total kurang: Rp <?php echo number_format($recapTotalShortfall, 2, ',', '.'); ?>
      </span>
    <?php else: ?>
      <span class="badge bg-success-subtle text-success border border-success-subtle">Saldo rekening cukup</span>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Rekening Pembayaran</th>
          <th>Bank / No Rek</th>
          <th class="text-end">Baris</th>
          <th class="text-end">Total Batch</th>
          <th class="text-end">Sudah Paid</th>
          <th class="text-end">Harus Dibayar Sekarang</th>
          <th class="text-end">Saldo Master Rekening</th>
          <th class="text-end">Sisa / Kurang</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($detailAccountRecap)): ?>
          <tr><td colspan="9" class="text-center text-muted py-3">Belum ada rekening sumber pada batch ini.</td></tr>
        <?php else: foreach ($detailAccountRecap as $ar): ?>
          <?php
            $accountId = (int)($ar['company_account_id'] ?? 0);
            $accountLabel = trim((string)($ar['account_code'] ?? '') . ' - ' . (string)($ar['account_name'] ?? ''));
            if ($accountLabel === '-' || $accountLabel === '') {
                $accountLabel = $accountId > 0 ? 'Rekening tidak ditemukan / nonaktif' : 'Belum ada rekening sumber';
            }
            $payNow = (float)($ar['pay_now_amount'] ?? 0);
            $balance = (float)($ar['current_balance'] ?? 0);
            $after = (float)($ar['balance_after_pay_now'] ?? ($balance - $payNow));
            $shortfall = (float)($ar['shortfall_amount'] ?? 0);
            $isReady = !empty($ar['payment_ready']);
          ?>
          <tr class="<?php echo $payNow > 0.009 && ($shortfall > 0.009 || !$isReady) ? 'table-danger' : ''; ?>">
            <td>
              <div class="fw-semibold"><?php echo html_escape($accountLabel); ?></div>
              <?php if ($accountId <= 0): ?>
                <div class="small text-danger">Baris gaji ini belum punya rekening sumber.</div>
              <?php elseif ((int)($ar['account_is_active'] ?? 0) !== 1): ?>
                <div class="small text-danger">Rekening sumber tidak aktif / tidak ditemukan.</div>
              <?php endif; ?>
            </td>
            <td>
              <?php echo html_escape(trim((string)($ar['bank_name'] ?? '')) !== '' ? (string)$ar['bank_name'] : '-'); ?>
              <div class="small text-muted"><?php echo html_escape(trim((string)($ar['account_no'] ?? '')) !== '' ? (string)$ar['account_no'] : '-'); ?></div>
            </td>
            <td class="text-end"><?php echo (int)($ar['active_line_count'] ?? $ar['line_count'] ?? 0); ?></td>
            <td class="text-end"><?php echo number_format((float)($ar['total_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end text-success"><?php echo number_format((float)($ar['paid_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($payNow, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($balance, 2, ',', '.'); ?></td>
            <td class="text-end <?php echo $shortfall > 0.009 ? 'text-danger fw-semibold' : 'text-success fw-semibold'; ?>">
              <?php if ($shortfall > 0.009): ?>
                Kurang Rp <?php echo number_format($shortfall, 2, ',', '.'); ?>
              <?php else: ?>
                Sisa Rp <?php echo number_format($after, 2, ',', '.'); ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($payNow <= 0.009): ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Tidak ada pending</span>
              <?php elseif ($isReady): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Siap Paid</span>
              <?php else: ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Belum siap</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($detailAccountRecap)): ?>
        <tfoot>
          <tr class="fw-semibold">
            <td colspan="3" class="text-end">TOTAL REKAP</td>
            <td class="text-end"><?php echo number_format($recapTotalAmount, 2, ',', '.'); ?></td>
            <td class="text-end text-success"><?php echo number_format($recapTotalPaid, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($recapTotalPayNow, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format($recapTotalBalance, 2, ',', '.'); ?></td>
            <td class="text-end <?php echo $recapTotalShortfall > 0.009 ? 'text-danger' : 'text-success'; ?>">
              <?php echo $recapTotalShortfall > 0.009 ? 'Kurang Rp ' . number_format($recapTotalShortfall, 2, ',', '.') : '-'; ?>
            </td>
            <td></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Detail Batch <?php echo html_escape((string)$detailHeader['disbursement_no']); ?></strong>
    <span class="text-muted">Total: <?php echo number_format((float)($detailHeader['total_amount'] ?? 0),2,',','.'); ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Pegawai</th><th>Rek Tujuan</th><th class="text-end">Pokok</th><th class="text-end">Tunjangan</th><th class="text-end">U. Makan<br><small>total hak</small></th><th class="text-end">Lembur</th><th class="text-end">Adj (+)</th><th class="text-end">Kotor Riil</th><th class="text-end">Pot. Telat</th><th class="text-end">Pot. Alpha</th><th class="text-end">Adj (-) Lain</th><th class="text-end">Pot. Kasbon</th><th class="text-end">THP Riil</th><th class="text-end">Pembulatan</th><th class="text-end">THP Final</th><th>Status</th><th>Ref</th><th>Paid At</th><th class="text-center">Cetak Slip</th></tr></thead>
      <tbody>
      <?php if (empty($detailLineBreakdown)): ?><tr><td colspan="19" class="text-center text-muted py-3">Tidak ada baris detail.</td></tr><?php else: foreach($detailLineBreakdown as $l): ?>
      <?php
        $basic = (float)($l['basic_total'] ?? 0);
        $allowance = (float)($l['allowance_total'] ?? 0);
        $meal = (float)($l['meal_total'] ?? 0);
        $overtime = (float)($l['overtime_total'] ?? 0);
        $manualAdd = (float)($l['manual_addition_total'] ?? 0);
        $grossRiil = round((float)($l['gross_pay'] ?? ($basic + $allowance + $meal + $overtime + $manualAdd)), 2);
        $lateDed = (float)($l['late_deduction_total'] ?? 0);
        $alphaDed = (float)($l['alpha_deduction_total'] ?? 0);
        $manualDedTotal = (float)($l['manual_deduction_total'] ?? 0);
        $cashCut = (float)($l['cash_advance_cut'] ?? 0);
        $manualDedOther = max(0, round($manualDedTotal - $cashCut, 2));
        $riilNet = (float)($l['net_pay_raw'] ?? ((float)($l['net_pay'] ?? 0) - (float)($l['rounding_adjustment'] ?? 0)));
        $rounding = round((float)($l['rounding_adjustment'] ?? ((float)($l['net_pay'] ?? 0) - $riilNet)), 2);
        $finalNet = (float)($l['transfer_amount'] ?? ($l['net_pay'] ?? 0));
      ?>
      <tr>
        <td><?php echo html_escape((string)($l['employee_name_snapshot'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($l['employee_code_snapshot'] ?? '-')); ?></div></td>
        <td><?php echo html_escape((string)($l['bank_name'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($l['bank_account_no'] ?? '-')); ?> a/n <?php echo html_escape((string)($l['bank_account_name'] ?? '-')); ?></div></td>
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
        <td class="text-end"><?php echo number_format($riilNet,2,',','.'); ?></td>
        <td class="text-end <?php echo abs($rounding) > 0.009 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo number_format($rounding,2,',','.'); ?></td>
        <td class="text-end fw-semibold"><?php echo number_format($finalNet,2,',','.'); ?></td>
        <td><?php echo html_escape((string)($l['transfer_status'] ?? '-')); ?></td>
        <td><?php echo html_escape((string)($l['transfer_ref_no'] ?? '-')); ?></td>
        <td><?php echo html_escape((string)($l['paid_at'] ?? '-')); ?></td>
        <td class="text-center">
          <a href="<?php echo site_url('payroll/salary-disbursements/slip/' . (int)($l['line_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Cetak Slip">
            <i class="ri ri-printer-line"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
