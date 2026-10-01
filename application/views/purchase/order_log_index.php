<?php
$baseUrl = site_url('purchase-orders/logs');
$actionOptions = is_array($action_options ?? null) ? $action_options : [];
?>

<div class="mb-2">
  <h4 class="mb-1">Log Purchase</h4>
  <small class="text-muted">Timeline khusus modul purchase dari pur_purchase_txn_log.</small>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'log-purchase']); ?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($q ?? '')); ?>" placeholder="PO / action / transaksi / catatan">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Action</label>
        <select name="action" class="form-select">
          <option value="">ALL</option>
          <?php foreach ($actionOptions as $opt): ?>
            <?php $code = strtoupper((string)$opt); ?>
            <option value="<?php echo html_escape($code); ?>" <?php echo strtoupper((string)($action ?? '')) === $code ? 'selected' : ''; ?>><?php echo html_escape($code); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" name="limit" min="1" max="1000" value="<?php echo (int)($limit ?? 300); ?>">
      </div>
      <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
      </div>
      <div class="col-md-1 d-grid">
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0 table-autofit fin-audit-table">
      <thead>
        <tr>
          <th class="text-nowrap col-date">Waktu</th>
          <th class="text-nowrap col-no">PO</th>
          <th class="text-nowrap col-type">Action</th>
          <th class="text-nowrap col-balance">Before</th>
          <th class="text-nowrap col-balance">After</th>
          <th class="text-nowrap col-no">Transaksi</th>
          <th class="text-nowrap col-ref">Ref</th>
          <th class="text-end col-amount">Amount</th>
          <th class="col-notes">Catatan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows ?? [])): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data log purchase.</td></tr>
        <?php else: ?>
          <?php foreach (($rows ?? []) as $r): ?>
            <?php
              $poId = (int)($r['purchase_order_id'] ?? 0);
              $poNo = trim((string)($r['po_no'] ?? ''));
              if ($poNo === '' && $poId > 0) {
                  $poNo = 'PO#' . $poId;
              }
              if ($poNo === '') {
                  $poNo = '-';
              }
              $statusBefore = strtoupper(trim((string)($r['status_before'] ?? '')));
              $statusAfter = strtoupper(trim((string)($r['status_after'] ?? '')));
            ?>
            <tr>
              <td class="text-nowrap col-date"><?php echo html_escape((string)($r['created_at'] ?? '-')); ?></td>
              <td>
                <?php if ($poId > 0): ?>
                  <a href="<?php echo site_url('purchase-orders/detail/' . $poId); ?>"><?php echo html_escape($poNo); ?></a>
                <?php else: ?>
                  <?php echo html_escape($poNo); ?>
                <?php endif; ?>
              </td>
              <td class="col-type"><span class="badge bg-secondary"><?php echo html_escape((string)($r['action_code'] ?? '-')); ?></span></td>
              <td class="text-nowrap col-balance"><?php echo html_escape($statusBefore !== '' ? $statusBefore : '-'); ?></td>
              <td class="text-nowrap col-balance"><?php echo html_escape($statusAfter !== '' ? $statusAfter : '-'); ?></td>
              <td class="text-nowrap col-no"><?php echo html_escape((string)($r['transaction_no'] ?? '-')); ?></td>
              <td class="text-nowrap col-ref">
                <?php echo html_escape((string)($r['ref_table'] ?? '-')); ?>
                <?php if (!empty($r['ref_id'])): ?>#<?php echo (int)$r['ref_id']; ?><?php endif; ?>
              </td>
              <td class="text-end col-amount"><?php echo $r['amount'] !== null ? number_format((float)$r['amount'], 2, ',', '.') : '-'; ?></td>
              <td class="col-notes"><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
