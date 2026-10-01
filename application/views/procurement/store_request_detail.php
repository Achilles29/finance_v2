<?php
$detail = (array)($detail ?? []);
$header = (array)($detail['header'] ?? []);
$lines = (array)($detail['lines'] ?? []);
$fulfillments = (array)($detail['fulfillments'] ?? []);
$approvals = (array)($detail['approvals'] ?? []);
$formatAuditQty = static function ($value): string {
  return number_format((float)$value, 2, ',', '.');
};

$status = strtoupper((string)($header['status'] ?? 'DRAFT'));
$usagePurposeLabel = static function ($value): string {
  return strtoupper(trim((string)$value)) === 'OPERASIONAL' ? 'Kebutuhan Operasional' : 'Persediaan Produksi';
};
$statusClass = [
    'DRAFT' => 'bg-secondary',
    'SUBMITTED' => 'bg-primary',
    'APPROVED' => 'bg-success',
    'PARTIAL_FULFILLED' => 'bg-warning text-dark',
    'FULFILLED' => 'bg-dark',
    'REJECTED' => 'bg-danger',
    'VOID' => 'bg-secondary',
][$status] ?? 'bg-secondary';
$actionLabel = static function (string $action): string {
    $labels = [
        'SUBMIT' => 'Disubmit',
        'APPROVE' => 'Disetujui',
        'REJECT' => 'Ditolak',
        'OVERRIDE_APPROVE' => 'Disetujui override',
        'VOID' => 'Divoid',
    ];
    $key = strtoupper(trim($action));
    return $labels[$key] ?? ($key !== '' ? $key : '-');
};
?>
<?php $this->load->view('procurement/_stock_review_history', ['stock_review_history'=>$stock_review_history ?? []]); ?>

<style>
  .sr-detail-action-wrap { display: inline-flex; gap: 8px; flex-wrap: wrap; }
  .sr-detail-action-btn { border-radius: 9px; padding: 6px 12px !important; display: inline-flex; align-items: center; gap: 6px; min-height: 34px; font-size: 12px; font-weight: 600; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1">Detail Store Request</h4>
    <small class="text-muted">Rincian request, fulfillment, dan lot FIFO yang dipakai dari gudang ke divisi.</small>
  </div>
  <div class="sr-detail-action-wrap">
    <a href="<?php echo site_url('store-requests'); ?>" class="btn btn-outline-secondary sr-detail-action-btn"><i class="ri ri-arrow-left-line"></i><span>Kembali</span></a>
    <?php if ($status === 'DRAFT'): ?>
      <a href="<?php echo site_url('store-requests/edit/' . (int)($header['id'] ?? 0)); ?>" class="btn btn-outline-primary sr-detail-action-btn"><i class="ri ri-edit-line"></i><span>Edit Draft</span></a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block mb-1">No SR</small><h5 class="mb-0"><?php echo html_escape((string)($header['sr_no'] ?? '-')); ?></h5></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block mb-1">Status</small><span class="badge <?php echo $statusClass; ?>"><?php echo html_escape($status); ?></span></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block mb-1">Divisi</small><h6 class="mb-0"><?php echo html_escape((string)($header['division_name'] ?? ('DIV#' . (int)($header['request_division_id'] ?? 0)))); ?></h6></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block mb-1">Tujuan</small><h6 class="mb-0"><?php echo html_escape((string)($header['destination_type'] ?? '-')); ?></h6></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-2">Ringkasan Header</h6>
    <div class="row g-2">
      <div class="col-md-3"><small class="text-muted d-block">Tanggal Request</small><strong><?php echo html_escape((string)($header['request_date'] ?? '-')); ?></strong></div>
      <div class="col-md-3"><small class="text-muted d-block">Tanggal Butuh</small><strong><?php echo html_escape((string)($header['needed_date'] ?? '-')); ?></strong></div>
      <div class="col-md-3"><small class="text-muted d-block">Dibuat Oleh</small><strong><?php echo html_escape((string)($header['created_by_username'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($header['created_at'] ?? '-')); ?></div></div>
      <div class="col-md-3"><small class="text-muted d-block">Updated At</small><strong><?php echo html_escape((string)($header['updated_at'] ?? '-')); ?></strong></div>
      <div class="col-12"><small class="text-muted d-block">Catatan</small><strong><?php echo html_escape((string)($header['notes'] ?? '-')); ?></strong></div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
      <div>
        <h6 class="mb-0">Jejak Status</h6>
        <small class="text-muted">Aksi, pelaku, waktu, dan alasan perubahan status Store Request.</small>
      </div>
      <span class="badge bg-light text-dark"><?php echo count($approvals); ?> aksi</span>
    </div>
    <?php if (empty($approvals)): ?>
      <div class="text-muted small">Belum ada jejak aksi status.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Aksi</th><th>Pelaku</th><th>Waktu</th><th>Alasan / Catatan</th></tr></thead>
          <tbody>
            <?php foreach ($approvals as $approval): ?>
              <tr>
                <td><strong><?php echo html_escape($actionLabel((string)($approval['action'] ?? ''))); ?></strong></td>
                <td><?php echo html_escape((string)($approval['actor_name'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($approval['created_at'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($approval['notes'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-2">Line Request</h6>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Profile</th>
            <th>Stok sekarang<br><small>Divisi / Gudang • satuan isi</small></th>
            <th>Pemakaian</th>
            <th class="text-end">Qty Request</th>
            <th class="text-end">Qty Fulfilled</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($lines)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Belum ada line Store Request.</td></tr>
          <?php else: ?>
            <?php foreach ($lines as $line): ?>
              <tr>
                <td>#<?php echo (int)($line['line_no'] ?? 0); ?></td>
                <td>
                  <strong><?php echo html_escape((string)($line['profile_name'] ?? '-')); ?></strong>
                  <div class="small text-muted"><?php echo html_escape((string)($line['profile_brand'] ?? '-')); ?></div>
                  <div class="small text-muted"><?php echo html_escape((string)($line['profile_description'] ?? '-')); ?></div>
                </td>
                <td><?php $this->load->view('procurement/_current_stock', ['stock_line'=>$line]); ?></td>
                <td><span class="badge bg-light text-dark border"><?php echo html_escape($usagePurposeLabel($line['usage_purpose'] ?? $line['default_usage_purpose'] ?? 'BAHAN_BAKU')); ?></span></td>
                <td class="text-end">
                  <div><?php echo number_format((float)($line['qty_buy_requested'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($line['buy_uom_code'] ?? '-')); ?></div>
                  <div class="small text-muted"><?php echo number_format((float)($line['qty_content_requested'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($line['content_uom_code'] ?? '-')); ?></div>
                </td>
                <td class="text-end">
                  <div><?php echo number_format((float)($line['qty_buy_fulfilled'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($line['buy_uom_code'] ?? '-')); ?></div>
                  <div class="small text-muted"><?php echo number_format((float)($line['qty_content_fulfilled'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($line['content_uom_code'] ?? '-')); ?></div>
                </td>
                <td><?php echo html_escape((string)($line['line_status'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
      <div>
        <h6 class="mb-0">Fulfillment & Lot FIFO</h6>
        <small class="text-muted">Lot sumber menunjukkan stok gudang yang terpakai, lot target menunjukkan lot yang dibentuk di stok divisi. Format audit mengikuti before, delta, after.</small>
      </div>
      <span class="badge bg-light text-dark"><?php echo count($fulfillments); ?> fulfillment</span>
    </div>
    <?php if (empty($fulfillments)): ?>
      <div class="text-muted small">Belum ada fulfillment yang diposting untuk SR ini.</div>
    <?php else: ?>
      <div class="accordion" id="srFulfillmentAccordion">
        <?php foreach ($fulfillments as $fulfillmentIndex => $fulfillment): ?>
          <?php $collapseId = 'sr-fulfillment-' . (int)($fulfillment['id'] ?? 0); ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="<?php echo $collapseId; ?>-header">
              <button class="accordion-button <?php echo $fulfillmentIndex === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="<?php echo $fulfillmentIndex === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapseId; ?>">
                <span class="me-3"><strong><?php echo html_escape((string)($fulfillment['fulfillment_no'] ?? '-')); ?></strong></span>
                <span class="me-3 small text-muted">Tgl <?php echo html_escape((string)($fulfillment['fulfillment_date'] ?? '-')); ?></span>
                <span class="me-3 small text-muted">Status <?php echo html_escape((string)($fulfillment['status'] ?? '-')); ?></span>
                <span class="small text-muted">Qty <?php echo number_format((float)($fulfillment['qty_content_total'] ?? 0), 2, ',', '.'); ?></span>
              </button>
            </h2>
            <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $fulfillmentIndex === 0 ? 'show' : ''; ?>" aria-labelledby="<?php echo $collapseId; ?>-header" data-bs-parent="#srFulfillmentAccordion">
              <div class="accordion-body">
                <?php $movementRows = (array)($fulfillment['movement_rows'] ?? []); ?>
                <div class="row g-2 mb-3">
                  <div class="col-md-3"><small class="text-muted d-block">Posted At</small><strong><?php echo html_escape((string)($fulfillment['posted_at'] ?? '-')); ?></strong></div>
                  <div class="col-md-3"><small class="text-muted d-block">Line Count</small><strong><?php echo (int)($fulfillment['line_count'] ?? 0); ?></strong></div>
                  <div class="col-md-3"><small class="text-muted d-block">Qty Beli</small><strong><?php echo number_format((float)($fulfillment['qty_buy_total'] ?? 0), 2, ',', '.'); ?></strong></div>
                  <div class="col-md-3"><small class="text-muted d-block">Qty Isi</small><strong><?php echo number_format((float)($fulfillment['qty_content_total'] ?? 0), 2, ',', '.'); ?></strong></div>
                  <?php if (!empty($fulfillment['notes'])): ?>
                    <div class="col-12"><small class="text-muted d-block">Catatan</small><strong><?php echo html_escape((string)$fulfillment['notes']); ?></strong></div>
                  <?php endif; ?>
                  <?php if (!empty($fulfillment['void_reason'])): ?>
                    <div class="col-12"><small class="text-muted d-block">Alasan Void</small><strong><?php echo html_escape((string)$fulfillment['void_reason']); ?></strong></div>
                  <?php endif; ?>
                </div>
                <?php if (!empty($movementRows)): ?>
                  <div class="mb-3">
                    <h6 class="mb-2">Audit Mutasi</h6>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle mb-0 fin-audit-table">
                        <thead>
                          <tr>
                            <th class="col-type">Scope</th>
                            <th class="col-no">Mutasi</th>
                            <th>Profile</th>
                            <th class="text-end col-balance">Before</th>
                            <th class="text-end col-delta">Delta</th>
                            <th class="text-end col-balance">After</th>
                            <th class="text-end col-amount">Unit Cost</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($movementRows as $movementRow): ?>
                            <?php
                              $beforeContent = (float)($movementRow['qty_content_after'] ?? 0) - (float)($movementRow['qty_content_delta'] ?? 0);
                              $beforeBuy = (float)($movementRow['qty_buy_after'] ?? 0) - (float)($movementRow['qty_buy_delta'] ?? 0);
                              $deltaContent = (float)($movementRow['qty_content_delta'] ?? 0);
                              $deltaBuy = (float)($movementRow['qty_buy_delta'] ?? 0);
                              $scopeLabel = strtoupper((string)($movementRow['movement_scope'] ?? '-'));
                            ?>
                            <tr>
                              <td class="col-type"><?php echo html_escape($scopeLabel); ?></td>
                              <td class="col-no">
                                <div><?php echo html_escape((string)($movementRow['movement_type'] ?? '-')); ?></div>
                                <small class="text-muted"><?php echo html_escape((string)($movementRow['movement_no'] ?? '-')); ?></small>
                              </td>
                              <td>
                                <strong><?php echo html_escape((string)($movementRow['profile_name'] ?? '-')); ?></strong>
                                <div class="small text-muted"><?php echo html_escape((string)($movementRow['profile_brand'] ?? '-')); ?> | <?php echo html_escape((string)($movementRow['profile_description'] ?? '-')); ?></div>
                              </td>
                              <td class="text-end col-balance">
                                <div class="fin-audit-metric">
                                  <div class="fin-audit-primary"><?php echo $formatAuditQty($beforeContent); ?></div>
                                  <small class="fin-audit-secondary"><?php echo $formatAuditQty($beforeBuy); ?> <?php echo html_escape((string)($movementRow['profile_buy_uom_code'] ?? '')); ?></small>
                                </div>
                              </td>
                              <td class="text-end col-delta <?php echo $deltaContent >= 0 ? 'fin-audit-delta-positive' : 'fin-audit-delta-negative'; ?>">
                                <div class="fin-audit-metric">
                                  <div class="fin-audit-primary"><?php echo $formatAuditQty($deltaContent); ?></div>
                                  <small class="fin-audit-secondary"><?php echo $formatAuditQty($deltaBuy); ?> <?php echo html_escape((string)($movementRow['profile_buy_uom_code'] ?? '')); ?></small>
                                </div>
                              </td>
                              <td class="text-end col-balance">
                                <div class="fin-audit-metric">
                                  <div class="fin-audit-primary"><?php echo $formatAuditQty((float)($movementRow['qty_content_after'] ?? 0)); ?></div>
                                  <small class="fin-audit-secondary"><?php echo $formatAuditQty((float)($movementRow['qty_buy_after'] ?? 0)); ?> <?php echo html_escape((string)($movementRow['profile_buy_uom_code'] ?? '')); ?></small>
                                </div>
                              </td>
                              <td class="text-end col-amount">Rp <?php echo number_format((float)($movementRow['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Line</th>
                        <th>Profile</th>
                        <th class="text-end">Qty Posted</th>
                        <th>FIFO Issue</th>
                        <th>Lot Allocation</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $fulfillmentLines = (array)($fulfillment['lines'] ?? []); ?>
                      <?php if (empty($fulfillmentLines)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada line fulfillment.</td></tr>
                      <?php else: ?>
                        <?php foreach ($fulfillmentLines as $fulfillmentLine): ?>
                          <?php $lotRows = (array)($fulfillmentLine['lot_rows'] ?? []); ?>
                          <tr>
                            <td>#<?php echo (int)($fulfillmentLine['request_line_no'] ?? 0); ?></td>
                            <td>
                              <strong><?php echo html_escape((string)($fulfillmentLine['profile_name'] ?? '-')); ?></strong>
                              <div class="small text-muted"><?php echo html_escape((string)($fulfillmentLine['profile_brand'] ?? '-')); ?></div>
                              <div class="small text-muted"><?php echo html_escape((string)($fulfillmentLine['profile_description'] ?? '-')); ?></div>
                            </td>
                            <td class="text-end">
                              <div><?php echo number_format((float)($fulfillmentLine['qty_buy_posted'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($fulfillmentLine['buy_uom_code'] ?? $fulfillmentLine['profile_buy_uom_code'] ?? '-')); ?></div>
                              <div class="small text-muted"><?php echo number_format((float)($fulfillmentLine['qty_content_posted'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($fulfillmentLine['content_uom_code'] ?? $fulfillmentLine['profile_content_uom_code'] ?? '-')); ?></div>
                            </td>
                            <td>
                              <strong><?php echo html_escape((string)($fulfillmentLine['fifo_issue_no'] ?? '-')); ?></strong>
                              <div class="small text-muted">Unit cost Rp <?php echo number_format((float)($fulfillmentLine['unit_cost_snapshot'] ?? 0), 2, ',', '.'); ?></div>
                            </td>
                            <td>
                              <?php if (empty($lotRows)): ?>
                                <span class="text-muted small">Belum ada alokasi lot.</span>
                              <?php else: ?>
                                <?php foreach ($lotRows as $lotRow): ?>
                                  <div class="border rounded p-2 mb-2">
                                    <div><strong>Sumber:</strong> <?php echo html_escape((string)($lotRow['source_lot_no'] ?? '-')); ?></div>
                                    <div class="small text-muted">Receipt <?php echo html_escape((string)($lotRow['source_receipt_date'] ?? '-')); ?><?php echo !empty($lotRow['source_expiry_date']) ? ' | Exp ' . html_escape((string)$lotRow['source_expiry_date']) : ''; ?></div>
                                    <div class="small text-muted">Qty <?php echo number_format((float)($lotRow['qty_out'] ?? 0), 2, ',', '.'); ?> | Unit cost Rp <?php echo number_format((float)($lotRow['unit_cost'] ?? 0), 2, ',', '.'); ?></div>
                                    <?php if ($lotRow['source_balance_before'] !== null || $lotRow['source_balance_after'] !== null): ?>
                                      <div class="small mt-1"><strong>Gudang</strong> | Before <?php echo $formatAuditQty((float)($lotRow['source_balance_before'] ?? 0)); ?> | Delta -<?php echo $formatAuditQty((float)($lotRow['qty_out'] ?? 0)); ?> | After <?php echo $formatAuditQty((float)($lotRow['source_balance_after'] ?? 0)); ?></div>
                                    <?php else: ?>
                                      <div class="small text-muted mt-1">Snapshot before/after gudang belum tersedia pada histori lama.</div>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1"><strong>Lot divisi:</strong> <?php echo html_escape((string)($lotRow['target_lot_no'] ?? '-')); ?><?php echo !empty($lotRow['target_receipt_date']) ? ' | Receipt ' . html_escape((string)$lotRow['target_receipt_date']) : ''; ?></div>
                                    <?php if ($lotRow['target_balance_before'] !== null || $lotRow['target_balance_after'] !== null): ?>
                                      <div class="small"><strong>Divisi</strong> | Before <?php echo $formatAuditQty((float)($lotRow['target_balance_before'] ?? 0)); ?> | Delta +<?php echo $formatAuditQty((float)($lotRow['qty_out'] ?? 0)); ?> | After <?php echo $formatAuditQty((float)($lotRow['target_balance_after'] ?? 0)); ?></div>
                                    <?php endif; ?>
                                  </div>
                                <?php endforeach; ?>
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
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var hash = String(window.location.hash || '').trim();
  if (!hash || hash.indexOf('#sr-fulfillment-') !== 0) {
    return;
  }

  var target = document.querySelector(hash);
  if (!target) {
    return;
  }

  var toggleBtn = document.querySelector('[data-bs-target="' + hash + '"]');
  if (toggleBtn && window.bootstrap && window.bootstrap.Collapse) {
    window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: false }).show();
  } else {
    target.classList.add('show');
  }

  window.setTimeout(function () {
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 120);
})();
</script>
