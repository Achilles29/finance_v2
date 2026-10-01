<?php
$detail = is_array($detail ?? null) ? $detail : [];
$header = is_array($detail['header'] ?? null) ? $detail['header'] : [];
$movementUsages = is_array($detail['movement_usages'] ?? null) ? $detail['movement_usages'] : [];
$batchUsages = is_array($detail['batch_usages'] ?? null) ? $detail['batch_usages'] : [];
$lotIssueUsages = is_array($detail['lot_issue_usages'] ?? null) ? $detail['lot_issue_usages'] : [];
$traceRows = is_array($detail['trace_rows'] ?? null) ? $detail['trace_rows'] : [];
$materialInputs = is_array($detail['material_inputs'] ?? null) ? $detail['material_inputs'] : [];
$canVoid = !empty($detail['can_void']);
$blockReason = trim((string)($detail['block_reason'] ?? ''));
$voidProjection = is_array($detail['void_projection'] ?? null) ? $detail['void_projection'] : [];
$voidWillGoNegative = !empty($voidProjection['available']) && !empty($voidProjection['would_go_negative']);
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
    <h4 class="mb-1"><i class="ri ri-flow-chart page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Detail Usage Batch')); ?></h4>
    <small class="text-muted">Investigasi dokumen dan movement yang memakai output batch component.</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?php echo site_url('production/component-batches'); ?>#component-batch-<?php echo (int)($header['id'] ?? 0); ?>" class="btn btn-outline-secondary">Kembali ke Batch</a>
    <a href="<?php echo site_url('production/component-batches'); ?>" class="btn btn-outline-dark">Daftar Batch</a>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'batch']); ?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <div class="text-muted small">Batch</div>
            <div class="h5 mb-1"><?php echo html_escape((string)($header['batch_no'] ?? '-')); ?></div>
            <div class="text-muted"><?php echo html_escape((string)($header['component_name'] ?? '-')); ?></div>
          </div>
          <div class="text-end">
            <div class="mb-1"><?php echo $canVoid ? '<span class="badge text-bg-success">Masih Bisa Void</span>' : '<span class="badge text-bg-warning">Tidak Bisa Void</span>'; ?></div>
            <div class="small text-muted">Status <?php echo html_escape((string)($header['status'] ?? '-')); ?></div>
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-3">
            <div class="text-muted small">Tanggal Batch</div>
            <div class="fw-semibold"><?php echo html_escape((string)($header['batch_date'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Qty Output</div>
            <div class="fw-semibold"><?php echo number_format((float)($header['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($header['uom_code'] ?? '')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Divisi</div>
            <div class="fw-semibold"><?php echo html_escape((string)($header['division_name'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Lokasi</div>
            <div class="fw-semibold"><?php echo html_escape($locationLabel((string)($header['location_type'] ?? ''))); ?></div>
          </div>
        </div>
        <?php if ($blockReason !== ''): ?>
          <div class="alert alert-warning mt-3 mb-0"><?php echo html_escape($blockReason); ?></div>
        <?php endif; ?>
        <?php if ($canVoid && $voidWillGoNegative): ?>
          <div class="alert alert-warning mt-3 mb-0">
            Void tetap boleh, tetapi saldo global component akan minus sementara.
            Stok global saat ini <strong><?php echo number_format((float)($voidProjection['current_global_qty'] ?? 0), 4, ',', '.'); ?></strong>,
            rollback batch <strong><?php echo number_format((float)($voidProjection['rollback_qty'] ?? 0), 4, ',', '.'); ?></strong>,
            proyeksi setelah void <strong><?php echo number_format((float)($voidProjection['projected_global_qty_after_void'] ?? 0), 4, ',', '.'); ?></strong>.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Ringkasan Usage</div>
        <div class="display-6 mb-2"><?php echo (int)($detail['summary']['usage_count'] ?? 0); ?></div>
        <div class="small text-muted mb-2">Total dokumen/movement terdeteksi memakai output batch ini.</div>
            <div class="d-flex justify-content-between py-1 border-top"><span>Trace posting batch</span><strong><?php echo (int)($detail['summary']['trace_count'] ?? 0); ?></strong></div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Input bahan baku</span><strong><?php echo (int)($detail['summary']['material_input_count'] ?? 0); ?></strong></div>
            <div class="d-flex justify-content-between py-1 border-top"><span>Produksi inline</span><strong><?php echo (int)($detail['summary']['inline_output_count'] ?? 0); ?></strong></div>
            <div class="d-flex justify-content-between py-1 border-top"><span>Pemakaian hasil inline</span><strong><?php echo (int)($detail['summary']['inline_usage_count'] ?? 0); ?></strong></div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Dipakai batch lain</span><strong><?php echo (int)($detail['summary']['batch_usage_count'] ?? 0); ?></strong></div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Dipakai movement keluar</span><strong><?php echo (int)($detail['summary']['movement_usage_count'] ?? 0); ?></strong></div>
        <div class="d-flex justify-content-between py-1 border-top"><span>Dipakai issue FIFO</span><strong><?php echo (int)($detail['summary']['lot_issue_usage_count'] ?? 0); ?></strong></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header bg-transparent">
    <strong>Input Bahan Baku Batch Ini</strong>
  </div>
  <div class="card-body p-0">
    <?php if (empty($materialInputs)): ?>
      <div class="p-3 text-muted">Batch ini tidak memakai bahan baku langsung atau trace input bahan belum tersedia.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>Line</th>
              <th>Bahan</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Unit Cost</th>
              <th class="text-end">Total Cost</th>
              <th>No FIFO</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($materialInputs as $row): ?>
              <tr>
                <td><?php echo (int)($row['line_no'] ?? 0); ?></td>
                <td><?php echo html_escape((string)($row['material_label'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['total_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($row['fifo_issue_no'] ?? '-') ?: '-'); ?></td>
                <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
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
        <strong>Trace Produksi Batch Ini</strong>
      </div>
      <div class="card-body p-0">
        <?php if (empty($traceRows)): ?>
          <div class="p-3 text-muted">Belum ada trace posting batch yang tersimpan.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th>Tahap</th>
                  <th>Komponen</th>
                  <th>Jenis</th>
                  <th class="text-end">Qty In</th>
                  <th class="text-end">Qty Out</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($traceRows as $row): ?>
                  <tr>
                    <td><?php echo html_escape((string)($row['trace_label'] ?? '-')); ?></td>
                    <td>
                      <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                      <small class="text-muted"><?php echo html_escape((string)($row['component_type'] ?? '-')); ?></small>
                    </td>
                    <td><?php echo html_escape((string)($row['movement_type_label'] ?? $row['movement_type'] ?? '-')); ?></td>
                    <td class="text-end"><?php echo number_format((float)($row['qty_in'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format((float)($row['qty_out'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
                    <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
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
    <strong>Batch Yang Memakai Output Ini</strong>
  </div>
  <div class="card-body p-0">
    <?php if (empty($batchUsages)): ?>
      <div class="p-3 text-muted">Belum ada batch lain yang memakai output component ini sebagai input.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>Batch</th>
              <th>Tanggal</th>
              <th>Output Batch Pemakai</th>
              <th class="text-end">Qty Pakai</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($batchUsages as $row): ?>
              <tr>
                <td><a href="<?php echo site_url('production/component-batches/detail/' . (int)($row['id'] ?? 0)); ?>"><?php echo html_escape((string)($row['batch_no'] ?? '-')); ?></a></td>
                <td><?php echo html_escape((string)($row['batch_date'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['output_component_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header bg-transparent">
    <strong>Issue FIFO Dari Lot Output Batch Ini</strong>
  </div>
  <div class="card-body p-0">
    <?php if (empty($lotIssueUsages)): ?>
      <div class="p-3 text-muted">Belum ada issue FIFO yang memakai lot output batch ini.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>No Issue</th>
              <th>Tanggal</th>
              <th>Sumber</th>
              <th>Catatan</th>
              <th class="text-end">Qty Out</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lotIssueUsages as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['issue_no'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['issue_date'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['source_module'] ?? '-')); ?><?php echo !empty($row['source_id']) ? ' #' . (int)$row['source_id'] : ''; ?></td>
                <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['qty_out'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header bg-transparent">
    <strong>Movement Keluar Setelah Batch Ini</strong>
  </div>
  <div class="card-body p-0">
    <?php if (empty($movementUsages)): ?>
      <div class="p-3 text-muted">Belum ada movement keluar yang memakai output batch ini.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>No Movement</th>
              <th>Tanggal</th>
              <th>Jenis</th>
              <th>Sumber</th>
              <th>Catatan</th>
              <th class="text-end">Qty Out</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movementUsages as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['movement_no'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['movement_date'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['movement_type_label'] ?? $row['movement_type'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['source_module'] ?? '-')); ?><?php echo !empty($row['source_id']) ? ' #' . (int)$row['source_id'] : ''; ?></td>
                <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['qty_out'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

