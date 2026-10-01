<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$overview = is_array($overview ?? null) ? $overview : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
$money = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$this->load->view('pos/_report_styles');
?>

<div class="container-xxl py-3">
  <div class="pos-report-shell">
    <div class="pos-report-hero mb-3">
      <div class="pos-report-title">Laporan Rekening Pembayaran POS</div>
      <p class="pos-report-copy mb-0">Rekap penerimaan POS per rekening perusahaan, sehingga alur uang masuk dan refund per akun lebih mudah diaudit.</p>
    </div>
    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'payment_accounts']); ?>

    <div class="pos-report-section p-3 mb-3">
      <form method="get" class="pos-report-filter-box row g-2 align-items-end">
        <div class="col-lg-4 col-md-6"><label class="form-label small text-muted mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Bank / rekening / payment / order / member"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label small text-muted mb-1">Outlet</label><select name="outlet_id" class="form-select"><option value="0">Semua Outlet</option><?php foreach ($outlets as $outlet): ?><option value="<?php echo (int)($outlet['id'] ?? 0); ?>"<?php echo (int)($filters['outlet_id'] ?? 0) === (int)($outlet['id'] ?? 0) ? ' selected' : ''; ?>><?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label small text-muted mb-1">Status</label><select name="status" class="form-select"><?php foreach (['PAID' => 'Paid', 'ALL' => 'Semua', 'PENDING' => 'Pending', 'FAILED' => 'Failed', 'VOID' => 'Void'] as $value => $label): ?><option value="<?php echo $value; ?>"<?php echo (string)($filters['status'] ?? 'PAID') === $value ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label small text-muted mb-1">Tipe</label><select name="payment_type" class="form-select"><?php foreach (['ALL' => 'Semua', 'FINAL' => 'Final', 'DEPOSIT' => 'Deposit', 'REFUND' => 'Refund'] as $value => $label): ?><option value="<?php echo $value; ?>"<?php echo (string)($filters['payment_type'] ?? 'ALL') === $value ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-1 col-md-4"><label class="form-label small text-muted mb-1">Dari</label><input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>"></div>
        <div class="col-lg-1 col-md-4"><label class="form-label small text-muted mb-1">Sampai</label><input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>"></div>
        <div class="col-lg-2 col-md-8 d-flex gap-2"><button class="btn btn-dark flex-fill">Tampilkan</button><a href="<?php echo site_url('pos/reports/payment-accounts'); ?>" class="btn btn-outline-secondary">Reset</a></div>
      </form>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="pos-report-card"><div class="pos-report-card-label">Baris Pembayaran</div><div class="pos-report-card-value"><?php echo number_format((int)($overview['line_count'] ?? 0)); ?></div></div></div>
      <div class="col-md-4"><div class="pos-report-card"><div class="pos-report-card-label">Transaksi</div><div class="pos-report-card-value"><?php echo number_format((int)($overview['payment_count'] ?? 0)); ?></div></div></div>
      <div class="col-md-4"><div class="pos-report-card"><div class="pos-report-card-label">Net Rekening</div><div class="pos-report-card-value"><?php echo $money($overview['net_amount'] ?? 0); ?></div><div class="pos-report-card-note">Penerimaan bersih per rekening.</div></div></div>
    </div>

    <div class="pos-report-section p-3">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 pos-report-table">
          <thead><tr><th>Bank</th><th>Nama Rekening</th><th>No Rekening</th><th class="text-center">Baris</th><th class="text-center">Transaksi</th><th class="text-end">Masuk</th><th class="text-end">Refund</th><th class="text-end">Net</th></tr></thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center pos-report-empty">Data tidak ditemukan.</td></tr>
          <?php else: foreach ($rows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['bank_name'] ?? '-')); ?></td>
              <td class="fw-semibold"><?php echo html_escape((string)($row['account_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['account_no'] ?? '-')); ?></td>
              <td class="text-center"><?php echo number_format((int)($row['line_count'] ?? 0)); ?></td>
              <td class="text-center"><?php echo number_format((int)($row['payment_count'] ?? 0)); ?></td>
              <td class="text-end"><?php echo $money($row['total_amount'] ?? 0); ?></td>
              <td class="text-end text-danger"><?php echo $money($row['refund_amount'] ?? 0); ?></td>
              <td class="text-end fw-semibold"><?php echo $money($row['net_amount'] ?? 0); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>