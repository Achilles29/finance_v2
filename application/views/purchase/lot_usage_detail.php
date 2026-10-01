<?php
$detail = is_array($detail ?? null) ? $detail : [];
$header = is_array($detail['header'] ?? null) ? $detail['header'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
$rows = is_array($detail['rows'] ?? null) ? $detail['rows'] : [];

$divisionText = trim((string)($header['division_code'] ?? ''));
if (trim((string)($header['division_name'] ?? '')) !== '') {
    $divisionText = $divisionText !== ''
        ? $divisionText . ' - ' . trim((string)$header['division_name'])
        : trim((string)$header['division_name']);
}

$buildSourceUrl = static function (array $row): string {
    $sourceTable = trim((string)($row['source_table'] ?? ''));
    $sourceId = (int)($row['source_id'] ?? 0);
    if ($sourceTable === 'pos_stock_commit' && $sourceId > 0) {
        $orderId = (int)($row['source_order_id'] ?? 0);
        if ($orderId > 0) {
            return site_url('pos/reports/sales/' . $orderId);
        }
        return site_url('pos/stock-commit-audit') . '?q=' . rawurlencode((string)$sourceId);
    }
    if ($sourceTable === 'pur_store_request_fulfillment' && $sourceId > 0) {
        $requestId = (int)($row['source_store_request_id'] ?? 0);
        if ($requestId > 0) {
            return site_url('store-requests/detail/' . $requestId) . '#sr-fulfillment-' . $sourceId;
        }
        return site_url('store-requests') . '?q=' . rawurlencode((string)$sourceId);
    }
    if ($sourceTable === 'inv_stock_adjustment' && $sourceId > 0) {
        return site_url('inventory/stock/adjustment/division') . '#stock-adjustment-' . $sourceId;
    }
    if ($sourceTable === 'pur_purchase_receipt' && $sourceId > 0) {
        $purchaseOrderId = (int)($row['receipt_purchase_order_id'] ?? 0);
        if ($purchaseOrderId > 0) {
            return site_url('purchase-orders/detail/' . $purchaseOrderId) . '#po-receipt-' . $sourceId;
        }
        return site_url('purchase-orders/detail/' . $sourceId);
    }
    if ($sourceTable === 'pur_purchase_order' && $sourceId > 0) {
        return site_url('purchase-orders/detail/' . $sourceId);
    }
    if ($sourceTable === 'pos_order_void' && $sourceId > 0) {
        return site_url('pos/reports/voids/' . $sourceId);
    }
    if ($sourceTable === 'pos_order_refund' && $sourceId > 0) {
        return site_url('pos/reports/refunds/' . $sourceId);
    }
    if (($sourceTable === 'production_component_batch' || $sourceTable === 'inv_component_batch') && $sourceId > 0) {
        return site_url('production/component-batches/detail/' . $sourceId);
    }
    if ($sourceTable === 'inv_division_stock_opening_snapshot' && $sourceId > 0) {
        return site_url('inventory/stock/opening/division');
    }
    return '';
};

$buildSourceLabel = static function (array $row): string {
    $sourceTable = trim((string)($row['source_table'] ?? ''));
    $sourceId = (int)($row['source_id'] ?? 0);
    if ($sourceTable === 'pur_purchase_receipt') {
        $receiptNo = trim((string)($row['receipt_no'] ?? ''));
        return $receiptNo !== '' ? ('Receipt ' . $receiptNo) : ('Receipt #' . $sourceId);
    }
    if ($sourceTable === 'pur_store_request_fulfillment') {
        $no = trim((string)($row['source_fulfillment_no'] ?? ''));
        return $no !== '' ? ('Fulfillment ' . $no) : ('Fulfillment #' . $sourceId);
    }
    if ($sourceTable === 'inv_component_batch' || $sourceTable === 'production_component_batch') {
        $batchNo = trim((string)($row['source_batch_no'] ?? ''));
        return $batchNo !== '' ? ('Batch ' . $batchNo) : ('Batch #' . $sourceId);
    }
    if ($sourceTable === 'pos_order_void') {
        return 'Void #' . $sourceId;
    }
    if ($sourceTable === 'pos_order_refund') {
        return 'Refund #' . $sourceId;
    }
    if ($sourceTable === 'pos_stock_commit') {
        $commitNo = trim((string)($row['source_commit_no'] ?? ''));
        if ($commitNo !== '') {
            return 'POS ' . $commitNo;
        }
        return 'Commit POS #' . $sourceId;
    }
    if ($sourceTable === 'inv_stock_adjustment') {
        return 'Adjustment #' . $sourceId;
    }
    if ($sourceId > 0) {
        return $sourceTable . ' #' . $sourceId;
    }
    return $sourceTable !== '' ? $sourceTable : '-';
};
?>

<style>
  .lot-usage-shell { display:grid; gap:1rem; }
  .lot-usage-hero {
    border:1px solid rgba(224,209,198,.88);
    border-radius:26px;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
    box-shadow:0 18px 38px rgba(58,38,30,.08);
    padding:1.1rem 1.2rem;
  }
  .lot-usage-grid { display:grid; gap:.75rem; grid-template-columns:repeat(4,minmax(0,1fr)); }
  .lot-usage-kpi, .lot-usage-meta {
    border:1px solid rgba(226,212,200,.88);
    border-radius:18px;
    background:#fff;
    padding:.85rem .95rem;
  }
  .lot-usage-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:#8a7a72; margin-bottom:.2rem; }
  .lot-usage-value { font-size:.92rem; font-weight:800; color:#312729; line-height:1.3; }
  .lot-usage-table th { white-space:nowrap; }
  .lot-usage-table td { vertical-align:top; }
  @media (max-width: 991.98px) {
    .lot-usage-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  }
</style>

<div class="lot-usage-shell">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4 class="mb-1"><?php echo html_escape((string)($title ?? 'Pemakaian Lot Material')); ?></h4>
      <small class="text-muted"><?php echo html_escape((string)($subtitle ?? '')); ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo site_url('inventory/stock/division/lot'); ?>" class="btn btn-outline-secondary btn-sm">Kembali ke Lot Bahan Baku</a>
      <a href="<?php echo site_url('inventory/fifo-audit?scope=WAREHOUSE'); ?>" class="btn btn-outline-secondary btn-sm">Audit Profil Gudang</a>
    </div>
  </div>

  <div class="lot-usage-hero">
    <div class="lot-usage-grid mb-3">
      <div class="lot-usage-meta"><div class="lot-usage-label">Lot</div><div class="lot-usage-value"><?php echo html_escape((string)($header['lot_no'] ?? '-')); ?></div></div>
      <div class="lot-usage-meta"><div class="lot-usage-label">Objek</div><div class="lot-usage-value"><?php echo html_escape((string)($header['item_name'] ?? $header['material_name'] ?? '-')); ?></div></div>
      <div class="lot-usage-meta"><div class="lot-usage-label">Divisi / Tujuan</div><div class="lot-usage-value"><?php echo html_escape($divisionText !== '' ? $divisionText : '-'); ?><br><small class="text-muted"><?php echo html_escape((string)($header['destination_name'] ?? '-')); ?></small></div></div>
      <div class="lot-usage-meta"><div class="lot-usage-label">Saldo Kini</div><div class="lot-usage-value"><?php echo number_format((float)($header['qty_balance'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($header['content_uom_code'] ?? '')); ?></div></div>
    </div>
    <div class="lot-usage-grid">
      <div class="lot-usage-kpi"><div class="lot-usage-label">Jumlah Pemakaian</div><div class="lot-usage-value"><?php echo number_format((int)($summary['usage_count'] ?? 0)); ?></div></div>
      <div class="lot-usage-kpi"><div class="lot-usage-label">Qty Keluar</div><div class="lot-usage-value"><?php echo number_format((float)($summary['qty_out_total'] ?? 0), 2, ',', '.'); ?></div></div>
      <div class="lot-usage-kpi"><div class="lot-usage-label">Nilai Keluar</div><div class="lot-usage-value">Rp <?php echo number_format((float)($summary['cost_total'] ?? 0), 2, ',', '.'); ?></div></div>
      <div class="lot-usage-kpi"><div class="lot-usage-label">Status</div><div class="lot-usage-value"><?php echo ui_status_badge((string)($header['status'] ?? 'OPEN')); ?></div></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 lot-usage-table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Dokumen</th>
              <th>Sumber</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Unit Cost</th>
              <th class="text-end">Total</th>
              <th>Balance</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Lot ini belum pernah dipakai keluar.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $sourceUrl = $buildSourceUrl($row);
                  $sourceLabel = $buildSourceLabel($row);
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo html_escape((string)($row['issue_date'] ?? '-')); ?></div>
                    <small class="text-muted"><?php echo html_escape((string)($row['issue_datetime'] ?? '')); ?></small>
                  </td>
                  <td>
                    <div class="fw-semibold"><?php echo html_escape((string)($row['issue_no'] ?? '-')); ?></div>
                    <small class="text-muted"><?php echo html_escape((string)($row['status'] ?? '-')); ?></small>
                  </td>
                  <td>
                    <div><?php echo html_escape((string)($row['source_module'] ?? '-')); ?></div>
                    <small class="text-muted">
                      <?php if ($sourceUrl !== ''): ?>
                        <a href="<?php echo html_escape($sourceUrl); ?>"><?php echo html_escape($sourceLabel); ?></a>
                      <?php else: ?>
                        <?php echo html_escape($sourceLabel); ?>
                      <?php endif; ?>
                    </small>
                    <?php if ($sourceUrl !== ''): ?>
                      <div><a href="<?php echo html_escape($sourceUrl); ?>" class="small">Buka detail sumber</a></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-semibold"><?php echo number_format((float)($row['qty_out'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['total_cost'] ?? 0), 2, ',', '.'); ?></td>
                  <td>
                    <div class="small">Sebelum: <?php echo number_format((float)($row['source_balance_before'] ?? 0), 2, ',', '.'); ?></div>
                    <div class="small text-muted">Sesudah: <?php echo number_format((float)($row['source_balance_after'] ?? 0), 2, ',', '.'); ?></div>
                  </td>
                  <td>
                    <small><?php echo html_escape((string)($row['notes'] ?? '-')); ?></small>
                    <?php if ($sourceUrl !== ''): ?>
                      <div><a href="<?php echo html_escape($sourceUrl); ?>" class="small">Lihat detail sumber</a></div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
