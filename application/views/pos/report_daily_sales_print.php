<?php
$overview = is_array($overview ?? null) ? $overview : [];
$payMethods = is_array($pay_methods ?? null) ? $pay_methods : [];
$payAccounts = is_array($pay_accounts ?? null) ? $pay_accounts : [];
$shifts = is_array($shifts ?? null) ? $shifts : [];
$byDivision = is_array($by_division ?? null) ? $by_division : [];
$date = trim((string)($date ?? date('Y-m-d')));
$outletName = trim((string)($outlet_name ?? ''));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Sales POS - <?php echo html_escape($date); ?></title>
  <style>
    :root { --ink:#2f3442; --muted:#7d6f68; --accent:#7f1d1d; --line:#eaded7; --soft:#faf6f3; }
    * { box-sizing:border-box; }
    body { margin:0; padding:20px 28px; font-family:Georgia, "Times New Roman", serif; color:var(--ink); background:#fff; font-size:13px; }
    .sheet { max-width:960px; margin:0 auto; }
    .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid var(--line); padding-bottom:12px; margin-bottom:14px; }
    .header h1 { margin:0 0 4px; font-size:24px; color:var(--accent); }
    .sub { font-size:12px; color:var(--muted); }
    .section { border:1px solid var(--line); border-radius:8px; padding:12px 14px; margin-bottom:14px; break-inside:avoid; }
    .section h2 { margin:0 0 10px; font-size:14px; color:var(--accent); text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid var(--line); padding-bottom:6px; }
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    table { width:100%; border-collapse:collapse; }
    th { background:var(--soft); padding:7px 10px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.07em; color:var(--accent); border-bottom:1px solid var(--line); }
    td { padding:7px 10px; border-bottom:1px solid #f0e6e0; font-size:13px; vertical-align:top; }
    td.text-end, th.text-end { text-align:right; }
    td.text-center, th.text-center { text-align:center; }
    tfoot td { font-weight:700; background:var(--soft); border-top:1px solid var(--line); }
    .kv td:first-child { color:var(--muted); width:58%; }
    .kv td:last-child { text-align:right; font-weight:600; }
    .print-btn { position:fixed; top:16px; right:16px; padding:8px 18px; background:var(--accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:13px; }
    @media print { body { padding:0; } .sheet { max-width:none; } .print-btn { display:none; } .section { border-radius:0; } }
    <?php if (!empty($pdf_mode)): ?>
    @page { size:A4 landscape; margin:12mm; }
    body { padding:0; }
    .sheet { max-width:none; }
    <?php endif; ?>
  </style>
</head>
<body>
<?php if (empty($pdf_mode)): ?><button class="print-btn" onclick="window.print()">Cetak / PDF</button><?php endif; ?>
<div class="sheet">
  <div class="header">
    <div>
      <h1>Daily Sales POS</h1>
      <div class="sub"><?php echo html_escape(date('l, d F Y', strtotime($date))); ?><?php if ($outletName !== ''): ?> - <?php echo html_escape($outletName); ?><?php endif; ?></div>
      <div class="sub">Dicetak: <?php echo html_escape(date('d/m/Y H:i')); ?></div>
    </div>
  </div>

  <div class="two-col">
    <div class="section">
      <h2>Revenue per Divisi</h2>
      <table>
        <thead><tr><th>Divisi</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php $totalDivision = 0.0; foreach ($byDivision as $row): $totalDivision += (float)($row['revenue'] ?? 0); ?>
          <tr><td><?php echo html_escape((string)($row['division_name'] ?? 'Lainnya')); ?></td><td class="text-end">Rp <?php echo number_format((float)($row['revenue'] ?? 0), 0, ',', '.'); ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($byDivision)): ?><tr><td colspan="2">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td>Total</td><td class="text-end">Rp <?php echo number_format($totalDivision, 0, ',', '.'); ?></td></tr></tfoot>
      </table>
    </div>
    <div class="section">
      <h2>Ringkasan Transaksi</h2>
      <table class="kv">
        <tbody>
          <tr><td>Transaksi</td><td><?php echo number_format((int)($overview['order_count'] ?? 0)); ?></td></tr>
          <tr><td>Pending</td><td><?php echo number_format((int)($overview['pending_count'] ?? 0)); ?></td></tr>
          <tr><td>Nominal Pending</td><td>Rp <?php echo number_format((float)($overview['pending_amount'] ?? 0), 0, ',', '.'); ?></td></tr>
          <tr><td>Gross Sales</td><td>Rp <?php echo number_format((float)($overview['gross_sales'] ?? 0), 0, ',', '.'); ?></td></tr>
          <tr><td>Refund</td><td>Rp <?php echo number_format((float)($overview['refund_amount'] ?? 0), 0, ',', '.'); ?></td></tr>
          <tr><td>Net Sales</td><td>Rp <?php echo number_format((float)($overview['net_sales'] ?? 0), 0, ',', '.'); ?></td></tr>
          <tr><td>Total Purchase</td><td>Rp <?php echo number_format((float)($total_purchase ?? 0), 0, ',', '.'); ?></td></tr>
          <tr><td>Net Daily Sales</td><td>Rp <?php echo number_format((float)($net_daily_sales ?? 0), 0, ',', '.'); ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="two-col">
    <div class="section">
      <h2>Metode Pembayaran</h2>
      <table>
        <thead><tr><th>Metode</th><th class="text-end">Gross</th><th class="text-end">Refund</th><th class="text-end">Net</th></tr></thead>
        <tbody>
        <?php $grossMethod = 0.0; $refundMethod = 0.0; $netMethod = 0.0; foreach ($payMethods as $row): $grossMethod += (float)($row['gross_amount'] ?? 0); $refundMethod += (float)($row['refund_amount'] ?? 0); $netMethod += (float)($row['net_amount'] ?? 0); ?>
          <tr><td><?php echo html_escape((string)($row['method_name'] ?? '-')); ?></td><td class="text-end">Rp <?php echo number_format((float)($row['gross_amount'] ?? 0), 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format((float)($row['refund_amount'] ?? 0), 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format((float)($row['net_amount'] ?? 0), 0, ',', '.'); ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($payMethods)): ?><tr><td colspan="4">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td>Total</td><td class="text-end">Rp <?php echo number_format($grossMethod, 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format($refundMethod, 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format($netMethod, 0, ',', '.'); ?></td></tr></tfoot>
      </table>
    </div>
    <div class="section">
      <h2>Rekening Pembayaran</h2>
      <table>
        <thead><tr><th>Rekening</th><th class="text-end">Gross</th><th class="text-end">Refund</th><th class="text-end">Net</th></tr></thead>
        <tbody>
        <?php $grossAcc = 0.0; $refundAcc = 0.0; $netAcc = 0.0; foreach ($payAccounts as $row): $grossAcc += (float)($row['gross_amount'] ?? 0); $refundAcc += (float)($row['refund_amount'] ?? 0); $netAcc += (float)($row['net_amount'] ?? 0); ?>
          <tr><td><?php echo html_escape((string)($row['account_name'] ?? '-')); ?><div class="sub"><?php echo html_escape((string)($row['bank_name'] ?? '-')); ?> - <?php echo html_escape((string)($row['account_no'] ?? '-')); ?></div></td><td class="text-end">Rp <?php echo number_format((float)($row['gross_amount'] ?? 0), 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format((float)($row['refund_amount'] ?? 0), 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format((float)($row['net_amount'] ?? 0), 0, ',', '.'); ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($payAccounts)): ?><tr><td colspan="4">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td>Total</td><td class="text-end">Rp <?php echo number_format($grossAcc, 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format($refundAcc, 0, ',', '.'); ?></td><td class="text-end">Rp <?php echo number_format($netAcc, 0, ',', '.'); ?></td></tr></tfoot>
      </table>
    </div>
  </div>

  <div class="section">
    <h2>Riwayat Shift</h2>
      <p class="small text-muted">Sesi aktif diperbarui dari transaksi berjalan; sesi tutup memakai ringkasan penutupan. Angka sesi mencakup seluruh durasi sesi dan DP, bukan subtotal harian. Total harian di atas mengikuti tanggal transaksi.</p>
    <table>
      <thead><tr><th>Sesi / Terminal</th><th>Kasir</th><th>Mulai</th><th>Selesai</th><th class="text-center">Status</th><th class="text-end">Trx</th><th class="text-end">Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($shifts as $shift): ?>
        <tr><td><?php echo html_escape((string)($shift['shift_no'] ?? '-')); ?><br><small><?php echo html_escape((string)($shift['outlet_name'] ?? '-') . ' / ' . (string)($shift['terminal_name'] ?? '-')); ?></small></td><td><?php echo html_escape((string)($shift['cashier_name'] ?? '-')); ?></td><td><?php echo !empty($shift['opened_at']) ? html_escape(date('d/m H:i', strtotime((string)$shift['opened_at']))) : '-'; ?></td><td><?php echo !empty($shift['closed_at']) ? html_escape(date('d/m H:i', strtotime((string)$shift['closed_at']))) : '-'; ?></td><td class="text-center"><?php echo html_escape((string)($shift['shift_status'] ?? '-')); ?></td><td class="text-end"><?php echo number_format((int)($shift['trx_count'] ?? 0)); ?></td><td class="text-end">Rp <?php echo number_format((float)($shift['revenue'] ?? 0), 0, ',', '.'); ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($shifts)): ?><tr><td colspan="7">Tidak ada shift.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php if (empty($pdf_mode)): ?><script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 150); });</script><?php endif; ?>
</body>
</html>
