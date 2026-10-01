<?php
$detail = is_array($detail ?? null) ? $detail : [];
$header = is_array($detail['header'] ?? null) ? $detail['header'] : [];
$lines = is_array($detail['lines'] ?? null) ? $detail['lines'] : [];
$movementRows = is_array($detail['movement_rows'] ?? null) ? $detail['movement_rows'] : [];
$effectiveMovementRows = is_array($detail['effective_movement_rows'] ?? null) ? $detail['effective_movement_rows'] : [];
$lotRows = is_array($detail['lot_rows'] ?? null) ? $detail['lot_rows'] : [];
$activeLotRows = is_array($detail['active_lot_rows'] ?? null) ? $detail['active_lot_rows'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
$canVoid = !empty($detail['can_void']);
$blockReason = trim((string)($detail['block_reason'] ?? ''));
$locationLabel = static function ($locationType): string {
    $value = strtoupper(trim((string)$locationType));
    if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY') {
        return 'Reguler';
    }
    if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT') {
        return 'Event';
    }
    return $value !== '' ? $value : '-';
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-inbox-archive-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Detail Opening Component')); ?></h4>
    <small class="text-muted">Rincian dokumen opening component, baris qty awal, trace movement posting, dan lot inbound awal.</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (strtoupper((string)($header['status'] ?? '')) === 'DRAFT'): ?>
      <a href="<?php echo site_url('production/component-openings') . '?edit=' . (int)($header['id'] ?? 0); ?>#component-opening-form-card" class="btn btn-outline-primary">Edit Draft</a>
    <?php endif; ?>
    <a href="<?php echo site_url('production/component-openings'); ?>#component-opening-<?php echo (int)($header['id'] ?? 0); ?>" class="btn btn-outline-secondary">Kembali ke Opening</a>
    <a href="<?php echo site_url('production/component-openings'); ?>" class="btn btn-outline-dark">Daftar Opening</a>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'opening']); ?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <div class="text-muted small">No Opening</div>
            <div class="h5 mb-1"><?php echo html_escape((string)($header['opening_no'] ?? '-')); ?></div>
            <div class="text-muted"><?php echo html_escape((string)($header['notes'] ?? '-')); ?></div>
          </div>
          <div class="text-end">
            <div class="mb-1"><?php echo ui_status_badge((string)($header['status'] ?? 'DRAFT')); ?></div>
            <?php if (strtoupper((string)($header['status'] ?? '')) === 'POSTED'): ?>
              <div class="small text-muted"><?php echo $canVoid ? 'Masih bisa void' : 'Void terblokir'; ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-3">
            <div class="text-muted small">Bulan Opening</div>
            <div class="fw-semibold"><?php echo html_escape(substr((string)($header['opening_date'] ?? ''), 0, 7) ?: '-'); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Lokasi</div>
            <div class="fw-semibold"><?php echo html_escape($locationLabel((string)($header['location_type'] ?? ''))); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Divisi</div>
            <div class="fw-semibold"><?php echo html_escape((string)($header['division_name'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Tanggal Posting Efektif</div>
            <div class="fw-semibold"><?php echo html_escape((string)($header['opening_date'] ?? '-')); ?></div>
          </div>
        </div>
        <?php if ($blockReason !== ''): ?>
          <div class="alert alert-warning mt-3 mb-0"><?php echo html_escape($blockReason); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Ringkasan Opening</div>
        <div class="display-6 mb-2"><?php echo (int)($summary['line_count'] ?? 0); ?></div>
        <div class="small text-muted mb-2">Jumlah line opening pada dokumen ini.</div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Total Qty</span><strong><?php echo number_format((float)($summary['total_qty'] ?? 0), 2, ',', '.'); ?></strong></div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Total Nilai</span><strong>Rp <?php echo number_format((float)($summary['total_value'] ?? 0), 2, ',', '.'); ?></strong></div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Movement Tercatat</span><strong><?php echo (int)($summary['movement_count'] ?? 0); ?></strong></div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Lot Inbound</span><strong><?php echo (int)($summary['lot_count'] ?? 0); ?></strong></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header bg-transparent">
    <strong>Baris Opening</strong>
  </div>
  <div class="card-body p-0">
    <?php if (empty($lines)): ?>
      <div class="p-3 text-muted">Dokumen ini belum memiliki baris opening.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>Line</th>
              <th>Component</th>
              <th>UOM</th>
              <th class="text-end">Qty Opening</th>
              <th class="text-end">Unit Cost</th>
              <th class="text-end">Total Nilai</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lines as $row): ?>
              <tr>
                <td><?php echo (int)($row['line_no'] ?? 0); ?></td>
                <td>
                  <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($row['component_code'] ?? '')); ?></small>
                </td>
                <td><?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['opening_qty'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format(((float)($row['opening_qty'] ?? 0) * (float)($row['unit_cost'] ?? 0)), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($row['note'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header bg-transparent">
    <strong>Posisi Stok Opening</strong>
  </div>
  <div class="card-body p-0">
    <ul class="nav nav-tabs px-3 pt-3" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#opening-effective-tab" type="button">Posisi Efektif</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#opening-audit-tab" type="button">Histori Audit</button></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="opening-effective-tab">
        <?php if (strtoupper((string)($header['status'] ?? '')) !== 'POSTED'): ?>
          <div class="alert alert-light border-0 border-bottom rounded-0 mb-0 small">Dokumen ini sedang <strong><?php echo html_escape((string)($header['status'] ?? 'DRAFT')); ?></strong>. Edit draft tidak membuat movement baru; posisi efektif di ledger akan muncul saat dokumen diposting.</div>
        <?php elseif (empty($effectiveMovementRows) && empty($activeLotRows)): ?>
          <div class="p-3 text-muted">Belum ada posisi efektif yang aktif dari dokumen opening ini.</div>
        <?php else: ?>
          <div class="alert alert-light border-0 border-bottom rounded-0 mb-0 small">Tab ini menampilkan kontribusi stok yang masih efektif dari opening saat ini. Histori reversal dan repost dipindah ke tab audit.</div>
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th>Component</th>
                  <th>UOM</th>
                  <th class="text-end">Qty Efektif</th>
                  <th class="text-end">Unit Cost Terakhir</th>
                  <th>Tanggal Efektif</th>
                  <th class="text-end">Lot Aktif</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($effectiveMovementRows)): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada net movement aktif untuk ditampilkan.</td></tr>
                <?php else: ?>
                  <?php foreach ($effectiveMovementRows as $row): ?>
                    <?php
                      $lotCount = 0;
                      foreach ($activeLotRows as $lotRow) {
                        if ((int)($lotRow['component_id'] ?? 0) === (int)($row['component_id'] ?? 0) && (int)($lotRow['uom_id'] ?? 0) === (int)($row['uom_id'] ?? 0)) {
                          $lotCount++;
                        }
                      }
                    ?>
                    <tr>
                      <td>
                        <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                        <small class="text-muted"><?php echo html_escape((string)($row['component_code'] ?? '')); ?></small>
                      </td>
                      <td><?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></td>
                      <td class="text-end"><?php echo number_format((float)($row['effective_qty'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-end"><?php echo number_format((float)($row['latest_unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                      <td><?php echo html_escape((string)($row['latest_movement_date'] ?? '-')); ?></td>
                      <td class="text-end"><?php echo number_format($lotCount, 0, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="tab-pane fade" id="opening-audit-tab">
        <?php if (empty($movementRows)): ?>
          <div class="p-3 text-muted">Belum ada movement audit yang tercatat dari dokumen opening ini.</div>
        <?php else: ?>
          <div class="alert alert-light border-0 border-bottom rounded-0 mb-0 small">
            Edit saat status <strong>DRAFT</strong> tidak menulis movement baru. Histori di bawah bertambah jika dokumen pernah <strong>POSTED</strong>, lalu dibuka ulang/void sehingga sistem menulis reversal audit seperti <strong>Adjustment Minus</strong> atau <strong>Pembatalan Void</strong>, lalu diposting ulang.
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th>No Movement</th>
                  <th>Tanggal</th>
                  <th>Component</th>
                  <th>Jenis</th>
                  <th class="text-end">Qty In</th>
                  <th class="text-end">Qty Out</th>
                  <th class="text-end">Unit Cost</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($movementRows as $row): ?>
                  <tr>
                    <td><?php echo html_escape((string)($row['movement_no'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($row['movement_date'] ?? '-')); ?></td>
                    <td>
                      <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                      <small class="text-muted"><?php echo html_escape((string)($row['component_code'] ?? '')); ?></small>
                    </td>
                    <td><?php echo html_escape((string)($row['movement_type_label'] ?? $row['movement_type'] ?? '-')); ?></td>
                    <td class="text-end"><?php echo number_format((float)($row['qty_in'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format((float)($row['qty_out'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                    <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header bg-transparent">
    <strong>Lot Inbound Opening</strong>
  </div>
  <div class="card-body p-0">
    <?php if (empty($lotRows)): ?>
      <div class="p-3 text-muted">Belum ada lot inbound yang terhubung ke opening ini.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>No Lot</th>
              <th>Tanggal</th>
              <th>Component</th>
              <th>UOM</th>
              <th class="text-end">Qty In</th>
              <th class="text-end">Saldo</th>
              <th class="text-end">Unit Cost</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lotRows as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['lot_no'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['receipt_date'] ?? '-')); ?></td>
                <td>
                  <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($row['component_code'] ?? '')); ?></small>
                </td>
                <td><?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['qty_in'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['qty_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($row['status'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
