<?php
$baseUrl = site_url('inventory/stock/warehouse/movement');
$genMonth = !empty($date_from ?? '') ? date('Y-m', strtotime((string)$date_from)) : date('Y-m');
$rowsData = is_array($rows ?? null) ? $rows : [];
$summaryRows = count($rowsData);
$summaryIn = 0.0;
$summaryOut = 0.0;
$summaryValue = 0.0;
foreach ($rowsData as $row) {
  $delta = (float)($row['qty_content_delta'] ?? 0);
  $summaryValue += abs($delta) * (float)($row['unit_cost'] ?? 0);
  if ($delta >= 0) {
    $summaryIn += $delta;
  } else {
    $summaryOut += abs($delta);
  }
}
?>

<div class="mb-2">
  <h4 class="mb-1"><i class="ri ri-arrow-left-right-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
  <small class="text-muted">Log keluar masuk stok gudang dari inv_stock_movement_log.</small>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'WAREHOUSE', 'active_tab' => 'movement']); ?>
</div>
<?php $this->load->view('purchase/_warehouse_stock_generate_btn', [
  'warehouse_action_params' => ['month' => $genMonth, 'date_from' => (string)($date_from ?? '')],
]); ?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="No mutasi / item / material / profile / merk">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)$date_from); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)$date_to); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" name="limit" min="1" max="1000" value="<?php echo (int)$limit; ?>">
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
      </div>
      <div class="col-md-2 d-grid">
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php $this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan mutasi stok gudang',
  'stock_summary_cards' => [
    ['label' => 'Baris Mutasi', 'value' => number_format($summaryRows), 'tone' => 'violet', 'icon' => 'ri-arrow-left-right-line'],
    ['label' => 'Total Masuk', 'value' => number_format($summaryIn, 2, ',', '.'), 'tone' => 'aqua', 'icon' => 'ri-arrow-down-circle-line'],
    ['label' => 'Total Keluar', 'value' => number_format($summaryOut, 2, ',', '.'), 'tone' => 'blue', 'icon' => 'ri-arrow-up-circle-line'],
    ['label' => 'Total Nilai', 'value' => 'Rp ' . number_format($summaryValue, 2, ',', '.'), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
  ],
]); ?>

<div class="card">
  <div class="card-body pb-0">
    <small class="fin-audit-note">Audit stok ditampilkan seragam: Before, Delta, After. Nilai bawah menunjukkan satuan beli jika tersedia.</small>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0 fin-audit-table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No Mutasi</th>
          <th>Tipe</th>
          <th>Kategori/Alasan</th>
          <th>Objek</th>
          <th>Profile</th>
          <th>Keterangan</th>
          <th class="text-end col-balance">Before</th>
          <th class="text-end col-delta">Delta Isi</th>
          <th class="text-end col-balance">After</th>
          <th class="text-end col-amount">Unit Cost</th>
          <th class="text-end col-amount">Nilai Mutasi</th>
          <th class="col-ref">Ref</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="13" class="text-center text-muted py-4">Belum ada data mutasi gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $movementTypeLabel = trim((string)($r['movement_type_label'] ?? $r['movement_type'] ?? '-'));
              $itemText = trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''));
              $materialText = trim((string)($r['material_code'] ?? '') . ' - ' . (string)($r['material_name'] ?? ''));
              $preferMaterial = (int)($r['material_id'] ?? 0) > 0;
              if ($preferMaterial) {
                $objectText = $materialText !== ' -' && $materialText !== '' ? $materialText : ($itemText !== ' -' && $itemText !== '' ? $itemText : '-');
              } else {
                $objectText = $itemText !== ' -' && $itemText !== '' ? $itemText : ($materialText !== ' -' && $materialText !== '' ? $materialText : '-');
              }
              $adjustmentCategory = strtoupper(trim((string)($r['adjustment_category'] ?? '')));
              $adjustmentReason = strtolower(trim((string)($r['adjustment_reason_code'] ?? '')));
              $deltaContent = (float)($r['qty_content_delta'] ?? 0);
              $afterContent = (float)($r['qty_content_after'] ?? 0);
              $beforeContent = $afterContent - $deltaContent;
              $deltaBuy = (float)($r['qty_buy_delta'] ?? 0);
              $afterBuy = (float)($r['qty_buy_after'] ?? 0);
              $beforeBuy = $afterBuy - $deltaBuy;
              $unitCost = (float)($r['unit_cost'] ?? 0);
              $mutationValue = abs($deltaContent) * $unitCost;
            ?>
            <tr>
              <td><?php echo html_escape((string)$r['movement_date']); ?></td>
              <td><?php echo html_escape((string)$r['movement_no']); ?></td>
              <td><?php echo html_escape($movementTypeLabel !== '' ? $movementTypeLabel : '-'); ?></td>
              <td>
                <div><?php echo html_escape($adjustmentCategory !== '' ? $adjustmentCategory : '-'); ?></div>
                <small class="text-muted"><?php echo html_escape($adjustmentReason !== '' ? str_replace('_', ' ', $adjustmentReason) : '-'); ?></small>
              </td>
              <td><?php echo html_escape($objectText); ?></td>
              <td>
                <?php echo html_escape((string)($r['profile_name'] ?? '-')); ?><br>
                <small class="text-muted"><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?> | <?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></small>
              </td>
              <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
              <td class="text-end col-balance">
                <div class="fin-audit-metric">
                  <div class="fin-audit-primary"><?php echo ui_num($beforeContent); ?></div>
                  <small class="fin-audit-secondary"><?php echo ui_num($beforeBuy); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end col-delta <?php echo $deltaContent >= 0 ? 'fin-audit-delta-positive' : 'fin-audit-delta-negative'; ?>">
                <div class="fin-audit-metric">
                  <div class="fin-audit-primary"><?php echo ui_num($deltaContent); ?></div>
                  <small class="fin-audit-secondary"><?php echo ui_num($deltaBuy); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end col-balance">
                <div class="fin-audit-metric">
                  <div class="fin-audit-primary"><?php echo ui_num($afterContent); ?></div>
                  <small class="fin-audit-secondary"><?php echo ui_num($afterBuy); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end col-amount"><?php echo ui_num($unitCost); ?></td>
              <td class="text-end col-amount"><?php echo number_format($mutationValue, 2, ',', '.'); ?></td>
              <td class="col-ref">
                <?php echo html_escape((string)($r['ref_table'] ?? '-')); ?>
                <?php if (!empty($r['ref_id'])): ?>#<?php echo (int)$r['ref_id']; ?><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
