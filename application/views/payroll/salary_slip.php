<?php
$line = $line ?? [];
$context = $context ?? 'admin';

$employeeName = (string)($line['employee_name_snapshot'] ?? '-');
$employeeCode = (string)($line['employee_code_snapshot'] ?? '-');
$periodCode = (string)($line['period_code'] ?? '-');
$periodStart = (string)($line['period_start'] ?? '-');
$periodEnd = (string)($line['period_end'] ?? '-');
$disbursementNo = (string)($line['disbursement_no'] ?? '-');
$disbursementDate = (string)($line['disbursement_date'] ?? '-');

$basic = (float)($line['basic_total'] ?? 0);
$allowance = (float)($line['allowance_total'] ?? 0);
$meal = (float)($line['meal_total'] ?? 0);
$hasMealModeBreakdown = !empty($line['meal_mode_breakdown_available']);
$mealMonthly = (float)($line['meal_monthly_total'] ?? 0);
$mealCustom = (float)($line['meal_custom_total'] ?? 0);
$overtime = (float)($line['overtime_total'] ?? 0);
$manualAdd = (float)($line['manual_addition_total'] ?? 0);

$lateDed = (float)($line['late_deduction_total'] ?? 0);
$alphaDed = (float)($line['alpha_deduction_total'] ?? 0);
$manualDedTotal = (float)($line['manual_deduction_total'] ?? 0);
$cashCut = (float)($line['cash_advance_cut_total'] ?? 0);
$manualDedOther = max(0, round($manualDedTotal - $cashCut, 2));

$mealPaidTotal = (float)($line['meal_paid_total'] ?? 0);
$mealPaidDays = (int)($line['meal_paid_days'] ?? 0);
$mealPaidDeduction = (float)($line['meal_paid_deduction'] ?? 0);
$mealCustomSettlement = $hasMealModeBreakdown ? $mealCustom : 0.0;
$mealCustomRemaining = $hasMealModeBreakdown
    ? max(0, round($mealCustom - $mealPaidTotal, 2))
    : 0.0;

$gross = (float)($line['gross_pay'] ?? ($basic + $allowance + $meal + $overtime + $manualAdd));
$thpFullSystem = (float)($line['net_pay_raw'] ?? (($line['net_pay'] ?? 0) - ($line['rounding_adjustment'] ?? 0)));
$rounding = (float)($line['rounding_adjustment'] ?? 0);
$transfer = (float)($line['transfer_amount'] ?? ($line['net_pay'] ?? 0));
$totalEntitlement = round($transfer + $mealCustom, 2);

$deductionSubtotal = round($lateDed + $alphaDed + $manualDedOther + $cashCut + $mealPaidDeduction + $mealCustomSettlement, 2);
$thpFullCalculated = round($gross - $deductionSubtotal, 2);
$reconcile = round($thpFullSystem - $thpFullCalculated, 2);
if (abs($reconcile) <= 0.009) {
    $reconcile = 0.0;
}

$bankName = (string)($line['employee_bank_name'] ?? '-');
$bankNo = (string)($line['employee_bank_account_no'] ?? '-');
$bankHolder = (string)($line['employee_bank_account_name'] ?? '-');
$sourceAccount = trim((string)($line['source_account_name'] ?? ''));
$sourceAccountCode = trim((string)($line['source_account_code'] ?? ''));
$logoUrl = (is_file(FCPATH . 'assets/img/logo.png') ? base_url('assets/img/logo.png') : base_url('assets/img/business-placeholder.svg'));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Slip Gaji <?php echo html_escape($employeeName); ?></title>
  <style>
    :root{
      --ink:#1f2937;
      --muted:#6b7280;
      --line:#e5e7eb;
      --brand:#8b1025;
      --soft:#f8fafc;
      --good:#0f766e;
    }
    *{box-sizing:border-box}
    body{margin:0;padding:22px;background:#fff;color:var(--ink);font-family:"Segoe UI",Tahoma,Arial,sans-serif}
    .wrap{max-width:980px;margin:0 auto}
    .toolbar{margin-bottom:10px}
    .btn{display:inline-block;padding:8px 12px;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#111827;text-decoration:none;margin-right:6px;font-size:13px}
    .slip{border:1px solid var(--line);border-radius:14px;overflow:hidden}
    .head{display:flex;justify-content:space-between;align-items:flex-start;padding:16px 18px;background:linear-gradient(120deg,#fff,#fcf5f7);border-bottom:1px solid var(--line)}
    .brand{display:flex;gap:12px;align-items:center}
    .logo{width:52px;height:52px;border-radius:12px;border:1px solid var(--line);background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}
    .logo img{width:44px;height:44px;object-fit:contain}
    .brand h1{font-size:24px;line-height:1.1;margin:0;color:var(--brand);font-weight:800;letter-spacing:.2px}
    .meta{font-size:12.5px;color:var(--muted);line-height:1.5;text-align:right}
    .meta b{color:#111827}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:14px 18px}
    .card{border:1px solid var(--line);border-radius:10px;padding:9px 10px;background:#fff}
    .card .k{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.35px}
    .card .v{font-size:14px;font-weight:700;margin-top:2px}
    .card .s{font-size:12px;color:var(--muted);margin-top:2px}
    .section{padding:0 18px 14px}
    .title{font-size:13px;color:var(--muted);margin:0 0 8px 0;text-transform:uppercase;letter-spacing:.5px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid var(--line);padding:8px 10px;font-size:13px}
    th{background:var(--soft);font-weight:700;text-align:left}
    .text-end{text-align:right}
    .sum td{font-weight:700;background:#f9fafb}
    .good td{background:#ecfdf5}
    .note{margin-top:10px;font-size:12px;color:var(--muted)}
    .recon{color:#92400e;font-weight:700}
    @media print{
      .toolbar{display:none}
      body{padding:0}
      .slip{border:none;border-radius:0}
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <button class="btn" onclick="window.print()">Cetak</button>
    <?php if ($context === 'my'): ?>
      <a class="btn" href="<?php echo site_url('my/payroll?tab=generated'); ?>">Kembali</a>
    <?php else: ?>
      <a class="btn" href="<?php echo site_url('payroll/salary-disbursements'); ?>">Kembali</a>
    <?php endif; ?>
  </div>

  <div class="slip">
    <div class="head">
      <div class="brand">
        <div class="logo"><img src="<?php echo html_escape($logoUrl); ?>" alt="Logo"></div>
        <div>
          <h1>Slip Gaji</h1>
          <div style="font-size:12.5px;color:var(--muted)">Periode <?php echo html_escape($periodCode); ?> (<?php echo html_escape($periodStart); ?> s/d <?php echo html_escape($periodEnd); ?>)</div>
        </div>
      </div>
      <div class="meta">
        No Batch: <b><?php echo html_escape($disbursementNo); ?></b><br>
        Tgl Pencairan: <b><?php echo html_escape($disbursementDate); ?></b><br>
        Status: <b><?php echo html_escape((string)($line['transfer_status'] ?? '-')); ?></b>
      </div>
    </div>

    <div class="grid">
      <div class="card"><div class="k">Pegawai</div><div class="v"><?php echo html_escape($employeeName); ?></div><div class="s"><?php echo html_escape($employeeCode); ?></div></div>
      <div class="card"><div class="k">Rekening Tujuan</div><div class="v"><?php echo html_escape($bankName); ?> - <?php echo html_escape($bankNo); ?></div><div class="s">a/n <?php echo html_escape($bankHolder); ?></div></div>
      <div class="card"><div class="k">Rekening Sumber</div><div class="v"><?php echo html_escape(trim($sourceAccountCode . ' ' . $sourceAccount)); ?></div><div class="s">Ref Transfer: <?php echo html_escape((string)($line['transfer_ref_no'] ?? '-')); ?></div></div>
      <div class="card"><div class="k">Paid At</div><div class="v"><?php echo html_escape((string)($line['paid_at'] ?? '-')); ?></div><div class="s">Snapshot payroll final</div></div>
    </div>

    <div class="section">
      <p class="title">Rincian Komponen</p>
      <table>
        <thead>
          <tr><th>Komponen</th><th class="text-end">Nominal</th></tr>
        </thead>
        <tbody>
          <tr><td>Gaji Pokok</td><td class="text-end"><?php echo number_format($basic,2,',','.'); ?></td></tr>
          <tr><td>Tunjangan</td><td class="text-end"><?php echo number_format($allowance,2,',','.'); ?></td></tr>
          <?php if ($hasMealModeBreakdown): ?>
            <tr><td>Uang Makan Bulanan (masuk payroll)</td><td class="text-end"><?php echo number_format($mealMonthly,2,',','.'); ?></td></tr>
            <?php if ($mealCustom > 0): ?>
              <tr><td>Uang Makan Custom (hak, dibayar terpisah)</td><td class="text-end"><?php echo number_format($mealCustom,2,',','.'); ?></td></tr>
            <?php endif; ?>
          <?php else: ?>
            <tr><td>Uang Makan (Hak Periode)</td><td class="text-end"><?php echo number_format($meal,2,',','.'); ?></td></tr>
          <?php endif; ?>
          <tr><td>Lembur</td><td class="text-end"><?php echo number_format($overtime,2,',','.'); ?></td></tr>
          <tr><td>Tambahan Manual (+)</td><td class="text-end"><?php echo number_format($manualAdd,2,',','.'); ?></td></tr>
          <tr class="sum"><td>Gaji Kotor (Gross)</td><td class="text-end"><?php echo number_format($gross,2,',','.'); ?></td></tr>

          <tr><td>Potongan Telat</td><td class="text-end"><?php echo number_format($lateDed,2,',','.'); ?></td></tr>
          <tr><td>Potongan Alpha</td><td class="text-end"><?php echo number_format($alphaDed,2,',','.'); ?></td></tr>
          <tr><td>Pengurangan Manual (-) Lain</td><td class="text-end"><?php echo number_format($manualDedOther,2,',','.'); ?></td></tr>
          <tr><td>Potongan Kasbon</td><td class="text-end"><?php echo number_format($cashCut,2,',','.'); ?></td></tr>
          <?php if ($mealCustomSettlement > 0): ?>
            <tr><td>Uang Makan Custom (dibayar melalui batch terpisah)</td><td class="text-end"><?php echo number_format($mealCustomSettlement,2,',','.'); ?></td></tr>
          <?php endif; ?>
          <?php if ($mealPaidDeduction > 0): ?>
            <tr>
              <td>Potongan Uang Makan (sudah dibayar<?php echo $mealPaidDays > 0 ? (' ' . $mealPaidDays . ' hari') : ''; ?>)</td>
              <td class="text-end"><?php echo number_format($mealPaidDeduction,2,',','.'); ?></td>
            </tr>
          <?php endif; ?>

          <tr class="sum"><td>THP Full (Sebelum Pembulatan)</td><td class="text-end"><?php echo number_format($thpFullSystem,2,',','.'); ?></td></tr>
          <?php if ($reconcile != 0.0): ?>
            <tr><td>Penyesuaian Rekonsiliasi Sistem</td><td class="text-end recon"><?php echo number_format($reconcile,2,',','.'); ?></td></tr>
          <?php endif; ?>
          <tr><td>Pembulatan</td><td class="text-end"><?php echo number_format($rounding,2,',','.'); ?></td></tr>
          <tr class="sum good"><td>Transfer Akhir</td><td class="text-end"><?php echo number_format($transfer,2,',','.'); ?></td></tr>
          <?php if ($hasMealModeBreakdown && $mealCustom > 0): ?>
            <tr><td>Total Hak Pegawai (transfer + uang makan custom)</td><td class="text-end"><?php echo number_format($totalEntitlement,2,',','.'); ?></td></tr>
            <tr><td>Uang Makan Custom Sudah Dibayar<?php echo $mealPaidDays > 0 ? (' (' . $mealPaidDays . ' hari)') : ''; ?></td><td class="text-end"><?php echo number_format($mealPaidTotal,2,',','.'); ?></td></tr>
            <tr class="<?php echo $mealCustomRemaining > 0 ? 'recon' : ''; ?>"><td>Sisa Uang Makan Custom</td><td class="text-end"><?php echo number_format($mealCustomRemaining,2,',','.'); ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="note">
        <?php if ($hasMealModeBreakdown): ?>
          Uang makan Bulanan sudah termasuk transfer payroll. Uang makan Custom adalah hak terpisah dan dibayar melalui menu Pencairan Uang Makan; nilainya tidak mengurangi hak pegawai dan tidak masuk transfer gaji.
        <?php else: ?>
          Slip historis belum memiliki snapshot pemisahan mode uang makan. Nilai transfer tetap mengikuti snapshot payroll saat batch difinalisasi.
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
