<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$overview = is_array($overview ?? null) ? $overview : [];
$meta = is_array($meta ?? null) ? $meta : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
$money = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$dt = static function ($value): string { $time = $value ? strtotime((string)$value) : false; return $time ? date('d M Y H:i', $time) : '-'; };
$statusBadge = static function (string $status): string {
    $status = strtoupper(trim($status));
    $map = ['PAID' => ['success', 'Paid'], 'PENDING' => ['warning', 'Pending'], 'FAILED' => ['danger', 'Failed'], 'VOID' => ['secondary', 'Void']];
    $item = $map[$status] ?? ['secondary', $status !== '' ? $status : '-'];
    return '<span class="pos-report-badge ' . $item[0] . '">' . html_escape($item[1]) . '</span>';
};
$this->load->view('pos/_report_styles');
?>

<div class="container-xxl py-3">
  <div class="pos-report-shell">
    <div class="pos-report-hero mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <div class="pos-report-title">Laporan Pembayaran POS</div>
          <p class="pos-report-copy mb-0">Ledger pembayaran POS per dokumen pembayaran. Setelah perbaikan filter tanggal, data payment final hari ini sekarang ikut tampil normal di halaman ini.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?php echo site_url('pos/reports/payment-methods?date_from=' . rawurlencode((string)($filters['date_from'] ?? '')) . '&date_to=' . rawurlencode((string)($filters['date_to'] ?? '')) . ((int)($filters['outlet_id'] ?? 0) > 0 ? '&outlet_id=' . (int)$filters['outlet_id'] : '')); ?>" class="btn btn-outline-secondary"><i class="ri-bank-card-2-line me-1"></i>Metode Bayar</a>
          <a href="<?php echo site_url('pos/reports/payment-accounts?date_from=' . rawurlencode((string)($filters['date_from'] ?? '')) . '&date_to=' . rawurlencode((string)($filters['date_to'] ?? '')) . ((int)($filters['outlet_id'] ?? 0) > 0 ? '&outlet_id=' . (int)$filters['outlet_id'] : '')); ?>" class="btn btn-outline-secondary"><i class="ri-wallet-3-line me-1"></i>Rekening Bayar</a>
        </div>
      </div>
    </div>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'payment']); ?>
    <?php $this->load->view('pos/_report_filter_summary', ['filters' => $filters, 'outlets' => $outlets]); ?>

    <div class="row g-3 mb-3">
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Dokumen</div><div class="pos-report-card-value"><?php echo number_format((int)($overview['payment_count'] ?? 0)); ?></div><div class="pos-report-card-note">Jumlah payment document.</div></div></div>
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Net Amount</div><div class="pos-report-card-value"><?php echo $money($overview['net_amount'] ?? 0); ?></div><div class="pos-report-card-note">Nilai bersih pembayaran.</div></div></div>
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Deposit Applied</div><div class="pos-report-card-value"><?php echo $money($overview['deposit_applied_amount'] ?? 0); ?></div><div class="pos-report-card-note">Deposit yang dipakai di payment.</div></div></div>
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Change</div><div class="pos-report-card-value"><?php echo $money($overview['change_amount'] ?? 0); ?></div><div class="pos-report-card-note">Kembalian transaksi.</div></div></div>
    </div>

    <div class="pos-report-section p-3 mb-3">
      <form method="get" class="pos-report-filter-box row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label small text-muted mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Payment / order / member / outlet"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label small text-muted mb-1">Outlet</label><select name="outlet_id" class="form-select"><option value="0">Semua outlet</option><?php foreach ($outlets as $outlet): ?><option value="<?php echo (int)($outlet['id'] ?? 0); ?>"<?php echo (int)($filters['outlet_id'] ?? 0) === (int)($outlet['id'] ?? 0) ? ' selected' : ''; ?>><?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label small text-muted mb-1">Status</label><select name="status" class="form-select"><?php foreach (['ALL' => 'Semua', 'PENDING' => 'Pending', 'PAID' => 'Paid', 'FAILED' => 'Failed', 'VOID' => 'Void'] as $value => $label): ?><option value="<?php echo $value; ?>"<?php echo (string)($filters['status'] ?? 'ALL') === $value ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label small text-muted mb-1">Tipe</label><select name="payment_type" class="form-select"><?php foreach (['FINAL' => 'Final', 'DEPOSIT' => 'Deposit', 'REFUND' => 'Refund', 'ALL' => 'Semua'] as $value => $label): ?><option value="<?php echo $value; ?>"<?php echo (string)($filters['payment_type'] ?? 'FINAL') === $value ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-1 col-md-6"><label class="form-label small text-muted mb-1">Dari</label><input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>"></div>
        <div class="col-lg-1 col-md-6"><label class="form-label small text-muted mb-1">Sampai</label><input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>"></div>
        <div class="col-lg-1 col-md-6"><label class="form-label small text-muted mb-1">Limit</label><select name="limit" class="form-select"><?php foreach ([25, 50, 100, 200] as $limit): ?><option value="<?php echo $limit; ?>"<?php echo (int)($filters['limit'] ?? 25) === $limit ? ' selected' : ''; ?>><?php echo $limit; ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2 col-md-12 d-flex gap-2"><button type="submit" class="btn btn-dark flex-fill">Filter</button><a href="<?php echo site_url('pos/reports/payments'); ?>" class="btn btn-outline-secondary">Reset</a></div>
      </form>
    </div>

    <div class="pos-report-section p-3">
      <div class="pos-report-table-wrap">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 pos-report-table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Payment</th>
                <th>Order</th>
                <th>Member</th>
                <th>Outlet</th>
                <th>Metode</th>
                <th class="text-end">Net</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center pos-report-empty">Belum ada pembayaran pada filter ini.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td><?php echo html_escape($dt(($row['paid_at'] ?? '') !== '' ? $row['paid_at'] : ($row['created_at'] ?? null))); ?></td>
                    <td><div class="fw-semibold"><?php echo html_escape((string)($row['payment_no'] ?? '-')); ?></div><div class="pos-report-meta"><?php echo html_escape((string)($row['payment_type'] ?? '-')); ?> | <?php echo html_escape((string)($row['cashier_name'] ?? '-')); ?></div></td>
                    <td><div><?php echo html_escape((string)($row['order_no'] ?? '-')); ?></div><div class="pos-report-meta"><?php echo html_escape((string)($row['service_type'] ?? '-')); ?></div></td>
                    <td><div><?php echo html_escape((string)($row['member_name'] ?? '-')); ?></div><div class="pos-report-meta"><?php echo html_escape((string)($row['member_no'] ?? '-')); ?></div></td>
                    <td><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($row['method_names'] ?? '-')); ?></td>
                    <td class="text-end fw-semibold"><?php echo $money($row['net_amount'] ?? $row['amount_total'] ?? 0); ?></td>
                    <td><?php echo $statusBadge((string)($row['payment_status'] ?? '-')); ?></td>
                    <td class="text-end"><a href="<?php echo site_url('pos/reports/payments/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php $this->load->view('pos/_report_pager', ['meta' => $meta, 'filters' => $filters, 'pager_path' => 'pos/reports/payments']); ?>
    </div>
  </div>
</div>
