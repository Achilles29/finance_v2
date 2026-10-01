<?php
$row = is_array($row ?? null) ? $row : [];
$report = is_array($report ?? null) ? $report : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$byMethod = is_array($report['by_method'] ?? null) ? $report['by_method'] : [];
$accounts = is_array($row['account_rows'] ?? null) ? $row['account_rows'] : [];
$selectedAccount = is_array($selected_account ?? null) ? $selected_account : [];
$focusAccountId = max(0, (int)($focus_account_id ?? 0));
$money = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$badgeClass = static function ($value): string {
    $amount = round((float)$value, 2);
    if (abs($amount) <= 0.009) {
        return 'success';
    }
    return $amount > 0 ? 'warning' : 'danger';
};
$focusLabel = 'Brankas / rekening fokus';
if (!empty($selectedAccount)) {
    $parts = [];
    if (!empty($selectedAccount['account_code'])) { $parts[] = trim((string)$selectedAccount['account_code']); }
    if (!empty($selectedAccount['account_name'])) { $parts[] = trim((string)$selectedAccount['account_name']); }
    $focusLabel = implode(' - ', $parts);
    if (!empty($selectedAccount['bank_name'])) {
        $focusLabel .= ($focusLabel !== '' ? ' | ' : '') . trim((string)$selectedAccount['bank_name']);
    }
}
$this->load->view('pos/_report_styles');
?>

<div class="container-xxl py-3">
  <div class="pos-report-shell">
    <div class="pos-report-hero mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <div class="pos-report-title">Detail Tutup Kasir POS</div>
          <p class="pos-report-copy mb-0"><?php echo html_escape((string)($row['shift_no'] ?? '-')); ?> | <?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?> | <?php echo html_escape((string)($row['terminal_name'] ?? '-')); ?></p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo site_url('pos/reports/cashier-close?account_id=' . $focusAccountId); ?>" class="btn btn-outline-secondary">Kembali</a>
        </div>
      </div>
    </div>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'cashier_close']); ?>

    <div class="pos-report-chip-row mb-3">
      <div class="pos-report-chip"><strong>Rekening fokus:</strong> <?php echo html_escape($focusLabel); ?></div>
      <div class="pos-report-chip"><strong>Kasir buka:</strong> <?php echo html_escape((string)($row['cashier_open_name'] ?? '-')); ?></div>
      <div class="pos-report-chip"><strong>Kasir tutup:</strong> <?php echo html_escape((string)($row['cashier_close_name'] ?? '-')); ?></div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-2 col-md-4 col-6"><div class="pos-report-card"><div class="pos-report-card-label">Modal Awal</div><div class="pos-report-card-value"><?php echo $money($summary['opening_cash'] ?? $row['opening_cash'] ?? 0); ?></div></div></div>
      <div class="col-lg-2 col-md-4 col-6"><div class="pos-report-card"><div class="pos-report-card-label">Penerimaan</div><div class="pos-report-card-value"><?php echo $money($summary['gross_receipts'] ?? 0); ?></div></div></div>
      <div class="col-lg-2 col-md-4 col-6"><div class="pos-report-card"><div class="pos-report-card-label">Refund</div><div class="pos-report-card-value text-danger"><?php echo $money($summary['refund_total'] ?? 0); ?></div></div></div>
      <div class="col-lg-2 col-md-4 col-6"><div class="pos-report-card"><div class="pos-report-card-label">Net Kasir</div><div class="pos-report-card-value"><?php echo $money($summary['net_receipts'] ?? 0); ?></div></div></div>
      <div class="col-lg-2 col-md-4 col-6"><div class="pos-report-card"><div class="pos-report-card-label">Expected</div><div class="pos-report-card-value"><?php echo $money($summary['expected_cash'] ?? $row['expected_cash'] ?? 0); ?></div></div></div>
      <div class="col-lg-2 col-md-4 col-6"><div class="pos-report-card"><div class="pos-report-card-label">Actual</div><div class="pos-report-card-value"><?php echo $money($summary['actual_cash'] ?? $row['actual_cash'] ?? 0); ?></div><div class="pos-report-card-note">Selisih <?php echo $money($summary['variance_cash'] ?? $row['variance_cash'] ?? 0); ?></div></div></div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-6">
        <div class="pos-report-section p-3 h-100">
          <div class="fw-semibold mb-2">Penerimaan per Metode</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 pos-report-table">
              <thead><tr><th>Metode</th><th class="text-end">Masuk</th><th class="text-end">Refund</th><th class="text-end">Net</th></tr></thead>
              <tbody>
                <?php if (empty($byMethod)): ?>
                  <tr><td colspan="4" class="text-center pos-report-empty">Belum ada pembayaran dalam shift ini.</td></tr>
                <?php else: foreach ($byMethod as $item): ?>
                  <tr>
                    <td><div class="fw-semibold"><?php echo html_escape((string)($item['method_name'] ?? 'Tanpa metode')); ?></div><div class="pos-report-meta"><?php echo html_escape((string)($item['method_type'] ?? '-')); ?></div></td>
                    <td class="text-end"><?php echo $money($item['gross_amount'] ?? 0); ?></td>
                    <td class="text-end text-danger"><?php echo $money($item['refund_amount'] ?? 0); ?></td>
                    <td class="text-end fw-semibold"><?php echo $money($item['net_amount'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="pos-report-section p-3 h-100">
          <div class="fw-semibold mb-2">Audit Rekening vs Mutasi</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 pos-report-table">
              <thead><tr><th>Rekening</th><th class="text-end">Snapshot</th><th class="text-end">Mutasi</th><th class="text-end">Selisih</th></tr></thead>
              <tbody>
                <?php if (empty($accounts)): ?>
                  <tr><td colspan="4" class="text-center pos-report-empty">Belum ada snapshot rekening pada shift ini.</td></tr>
                <?php else: foreach ($accounts as $account): ?>
                  <tr class="<?php echo !empty($account['is_focus']) ? 'pos-report-tone-warning' : (!empty($account['has_variance']) ? 'pos-report-tone-danger' : ''); ?>">
                    <td>
                      <div class="fw-semibold"><?php echo html_escape((string)($account['account_label'] ?? '-')); ?></div>
                      <?php if (!empty($account['is_focus'])): ?><div class="pos-report-meta">Rekening fokus</div><?php endif; ?>
                    </td>
                    <td class="text-end"><?php echo $money($account['snapshot_net'] ?? 0); ?></td>
                    <td class="text-end"><?php echo $money($account['mutation_net'] ?? 0); ?></td>
                    <td class="text-end"><span class="pos-report-badge <?php echo html_escape($badgeClass($account['variance_net'] ?? 0)); ?>"><?php echo $money($account['variance_net'] ?? 0); ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($row['notes'])): ?>
      <div class="pos-report-section p-3">
        <div class="fw-semibold mb-2">Catatan Tutup Shift</div>
        <div><?php echo nl2br(html_escape((string)$row['notes'])); ?></div>
      </div>
    <?php endif; ?>
  </div>
</div>
