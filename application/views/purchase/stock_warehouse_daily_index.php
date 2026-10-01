<?php
$baseUrl = site_url('inventory/stock/warehouse/daily');
$profileAuditBaseUrl = site_url('inventory/fifo-audit');
$genMonth = $month !== '' ? substr((string)$month, 0, 7) : date('Y-m');
$windowStart = $genMonth . '-01';
$windowEnd = date('Y-m-t', strtotime($windowStart));
$buildLotUrl = static function (array $row) use ($profileAuditBaseUrl): string {
  $searchToken = trim((string)($row['profile_key'] ?? ''));
  if ($searchToken === '') {
    $searchToken = trim((string)($row['item_code'] ?? ''));
  }
  if ($searchToken === '') {
    $searchToken = trim((string)($row['material_code'] ?? ''));
  }
  if ($searchToken === '') {
    $searchToken = trim((string)($row['item_name'] ?? ''));
  }
  if ($searchToken === '') {
    $searchToken = trim((string)($row['material_name'] ?? ''));
  }

  $params = [
    'scope' => 'WAREHOUSE',
    'q' => $searchToken,
    'profile_key' => trim((string)($row['profile_key'] ?? '')),
    'item_id' => (int)($row['item_id'] ?? 0) > 0 ? (int)($row['item_id'] ?? 0) : null,
    'material_id' => (int)($row['material_id'] ?? 0) > 0 ? (int)($row['material_id'] ?? 0) : null,
  ];
  $params = array_filter($params, static function ($value) {
    return $value !== null && $value !== '';
  });

  return $profileAuditBaseUrl . (!empty($params) ? ('?' . http_build_query($params)) : '');
};
$rowsData = is_array($rows ?? null) ? $rows : [];
$monthlyMap = [];
foreach ($rowsData as $row) {
  $profileNameKey = strtoupper(trim((string)($row['profile_name'] ?? '')));
  $profileBrandKey = strtoupper(trim((string)($row['profile_brand'] ?? '')));
  $profileDescKey = strtoupper(trim((string)($row['profile_description'] ?? '')));
  $profileContentPerBuyKey = number_format((float)($row['profile_content_per_buy'] ?? 0), 6, '.', '');
  $key = implode('|', [
    strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
    (int)($row['item_id'] ?? 0),
    (int)($row['material_id'] ?? 0),
    (int)($row['buy_uom_id'] ?? 0),
    (int)($row['content_uom_id'] ?? 0),
    (string)($row['profile_key'] ?? ''),
    $profileNameKey,
    $profileBrandKey,
    $profileDescKey,
    $profileContentPerBuyKey,
    strtoupper(trim((string)($row['profile_buy_uom_code'] ?? ''))),
    strtoupper(trim((string)($row['profile_content_uom_code'] ?? ''))),
  ]);

  if (!isset($monthlyMap[$key])) {
    $monthlyMap[$key] = [
      'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
      'item_id' => (int)($row['item_id'] ?? 0),
      'material_id' => (int)($row['material_id'] ?? 0),
      'profile_key' => (string)($row['profile_key'] ?? ''),
      'item_code' => (string)($row['item_code'] ?? ''),
      'item_name' => (string)($row['item_name'] ?? ''),
      'material_code' => (string)($row['material_code'] ?? ''),
      'material_name' => (string)($row['material_name'] ?? ''),
      'profile_name' => (string)($row['profile_name'] ?? '-'),
      'profile_brand' => (string)($row['profile_brand'] ?? '-'),
      'profile_description' => (string)($row['profile_description'] ?? '-'),
      'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
      'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 0),
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'opening_qty_content' => 0.0,
      'opening_qty_pack' => 0.0,
      'in_qty_content' => 0.0,
      'in_qty_pack' => 0.0,
      'out_qty_content' => 0.0,
      'out_qty_pack' => 0.0,
      'adjustment_qty_content' => 0.0,
      'adjustment_qty_pack' => 0.0,
      'closing_qty_content' => 0.0,
      'closing_qty_pack' => 0.0,
      'total_value' => 0.0,
      'avg_cost_per_content' => 0.0,
      'discard_qty_content' => 0.0,
      'discard_qty_pack' => 0.0,
      'spoil_qty_content' => 0.0,
      'spoil_qty_pack' => 0.0,
      'waste_qty_content' => 0.0,
      'waste_qty_pack' => 0.0,
      'waste_component_qty_content' => 0.0,
      'waste_component_qty_pack' => 0.0,
      'waste_component_value' => 0.0,
      'spoilage_qty_content' => 0.0,
      'spoilage_qty_pack' => 0.0,
      'spoilage_value' => 0.0,
      'process_loss_qty_content' => 0.0,
      'process_loss_qty_pack' => 0.0,
      'process_loss_value' => 0.0,
      'variance_qty_content' => 0.0,
      'variance_qty_pack' => 0.0,
      'variance_value' => 0.0,
      'adjustment_plus_qty_content' => 0.0,
      'adjustment_plus_qty_pack' => 0.0,
      'adjustment_plus_value' => 0.0,
      '_min_date' => null,
      '_max_date' => null,
    ];
  }

  $movementDate = (string)($row['movement_date'] ?? '');
  $entry =& $monthlyMap[$key];
  $profileContentPerBuy = (float)($row['profile_content_per_buy'] ?? 0);
  $inQtyContent = (float)($row['in_qty_content'] ?? 0);
  $outQtyContent = (float)($row['out_qty_content'] ?? 0);
  $adjustmentQtyContent = (float)($row['adjustment_qty_content'] ?? 0);
  $discardQtyContent = (float)($row['discard_qty_content'] ?? 0);
  $spoilQtyContent = (float)($row['spoil_qty_content'] ?? 0);
  $wasteQtyContent = (float)($row['waste_qty_content'] ?? 0);
  $wasteComponentQtyContent = $wasteQtyContent + $discardQtyContent;
  $spoilageQtyContent = $spoilQtyContent;
  $processLossQtyContent = (float)($row['process_loss_qty_content'] ?? 0);
  $varianceQtyContent = (float)($row['variance_qty_content'] ?? 0);
  if ($varianceQtyContent <= 0 && $adjustmentQtyContent < 0) {
    $varianceQtyContent = abs($adjustmentQtyContent);
  }
  $adjustmentPlusQtyContent = (float)($row['adjustment_plus_qty_content'] ?? 0);
  if ($adjustmentPlusQtyContent <= 0 && $adjustmentQtyContent > 0) {
    $adjustmentPlusQtyContent = $adjustmentQtyContent;
  }

  $avgCostPerContent = (float)($row['avg_cost_per_content'] ?? 0);
  $wasteComponentValue = (float)($row['waste_total_value'] ?? 0);
  if ($wasteComponentValue <= 0 && $wasteComponentQtyContent > 0) {
    $wasteComponentValue = $wasteComponentQtyContent * $avgCostPerContent;
  }
  $spoilageValue = (float)($row['spoilage_total_value'] ?? 0);
  if ($spoilageValue <= 0 && $spoilageQtyContent > 0) {
    $spoilageValue = $spoilageQtyContent * $avgCostPerContent;
  }
  $processLossValue = (float)($row['process_loss_total_value'] ?? 0);
  if ($processLossValue <= 0 && $processLossQtyContent > 0) {
    $processLossValue = $processLossQtyContent * $avgCostPerContent;
  }
  $varianceValue = (float)($row['variance_total_value'] ?? 0);
  if ($varianceValue <= 0 && $varianceQtyContent > 0) {
    $varianceValue = $varianceQtyContent * $avgCostPerContent;
  }
  $adjustmentPlusValue = (float)($row['adjustment_plus_total_value'] ?? 0);
  if ($adjustmentPlusValue <= 0 && $adjustmentPlusQtyContent > 0) {
    $adjustmentPlusValue = $adjustmentPlusQtyContent * $avgCostPerContent;
  }

  $entry['in_qty_content'] += $inQtyContent;
  $entry['out_qty_content'] += $outQtyContent;
  $entry['adjustment_qty_content'] += $adjustmentQtyContent;
  $entry['discard_qty_content'] += $discardQtyContent;
  $entry['spoil_qty_content'] += $spoilQtyContent;
  $entry['waste_qty_content'] += $wasteQtyContent;
  $entry['waste_component_qty_content'] += $wasteComponentQtyContent;
  $entry['waste_component_value'] += $wasteComponentValue;
  $entry['spoilage_qty_content'] += $spoilageQtyContent;
  $entry['spoilage_value'] += $spoilageValue;
  $entry['process_loss_qty_content'] += $processLossQtyContent;
  $entry['process_loss_value'] += $processLossValue;
  $entry['variance_qty_content'] += $varianceQtyContent;
  $entry['variance_value'] += $varianceValue;
  $entry['adjustment_plus_qty_content'] += $adjustmentPlusQtyContent;
  $entry['adjustment_plus_value'] += $adjustmentPlusValue;

  if ($profileContentPerBuy > 0) {
    $entry['in_qty_pack'] += ($inQtyContent / $profileContentPerBuy);
    $entry['out_qty_pack'] += ($outQtyContent / $profileContentPerBuy);
    $entry['adjustment_qty_pack'] += ($adjustmentQtyContent / $profileContentPerBuy);
    $entry['discard_qty_pack'] += ($discardQtyContent / $profileContentPerBuy);
    $entry['spoil_qty_pack'] += ($spoilQtyContent / $profileContentPerBuy);
    $entry['waste_qty_pack'] += ($wasteQtyContent / $profileContentPerBuy);
    $entry['waste_component_qty_pack'] += ($wasteComponentQtyContent / $profileContentPerBuy);
    $entry['spoilage_qty_pack'] += ($spoilageQtyContent / $profileContentPerBuy);
    $entry['process_loss_qty_pack'] += ($processLossQtyContent / $profileContentPerBuy);
    $entry['variance_qty_pack'] += ($varianceQtyContent / $profileContentPerBuy);
    $entry['adjustment_plus_qty_pack'] += ($adjustmentPlusQtyContent / $profileContentPerBuy);
  }

  if ($entry['_min_date'] === null || ($movementDate !== '' && $movementDate < $entry['_min_date'])) {
    $entry['_min_date'] = $movementDate;
    $entry['opening_qty_content'] = (float)($row['opening_qty_content'] ?? 0);
    $entry['opening_qty_pack'] = $profileContentPerBuy > 0 ? ($entry['opening_qty_content'] / $profileContentPerBuy) : 0.0;
  }
  if ($entry['_max_date'] === null || ($movementDate !== '' && $movementDate > $entry['_max_date'])) {
    $entry['_max_date'] = $movementDate;
    $entry['closing_qty_content'] = (float)($row['closing_qty_content'] ?? 0);
    $entry['closing_qty_pack'] = $profileContentPerBuy > 0 ? ($entry['closing_qty_content'] / $profileContentPerBuy) : 0.0;
    $entry['total_value'] = (float)($row['total_value'] ?? 0);
    $entry['avg_cost_per_content'] = (float)($row['avg_cost_per_content'] ?? 0);
  }
}

$monthlyRows = array_values($monthlyMap);
usort($monthlyRows, static function (array $a, array $b): int {
  $aName = trim(($a['item_name'] ?? '') !== '' ? (string)$a['item_name'] : (string)($a['material_name'] ?? ''));
  $bName = trim(($b['item_name'] ?? '') !== '' ? (string)$b['item_name'] : (string)($b['material_name'] ?? ''));
  $cmp = strcasecmp($aName, $bName);
  if ($cmp !== 0) {
    return $cmp;
  }
  return strcasecmp((string)($a['profile_name'] ?? ''), (string)($b['profile_name'] ?? ''));
});

$parentMap = [];
foreach ($monthlyRows as $row) {
  $materialId = (int)($row['material_id'] ?? 0);
  $itemId = (int)($row['item_id'] ?? 0);
  $objectIdentity = $materialId > 0 ? ('M-' . $materialId) : ('I-' . $itemId);
  if ($objectIdentity === 'M-0' || $objectIdentity === 'I-0') {
    $objectIdentity .= '|' . strtoupper(trim((string)($row['material_code'] ?? '') . '|' . (string)($row['item_code'] ?? '')));
  }
  $parentKey = implode('|', [
    $objectIdentity,
  ]);

  if (!isset($parentMap[$parentKey])) {
    $parentMap[$parentKey] = [
      'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
      'item_code' => (string)($row['item_code'] ?? ''),
      'item_name' => (string)($row['item_name'] ?? ''),
      'material_code' => (string)($row['material_code'] ?? ''),
      'material_name' => (string)($row['material_name'] ?? ''),
      'profile_count' => 0,
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'avg_content_per_buy' => 0.0,
      'opening_qty_content' => 0.0,
      'opening_qty_pack' => 0.0,
      'in_qty_content' => 0.0,
      'in_qty_pack' => 0.0,
      'out_qty_content' => 0.0,
      'out_qty_pack' => 0.0,
      'discard_qty_content' => 0.0,
      'discard_qty_pack' => 0.0,
      'spoil_qty_content' => 0.0,
      'spoil_qty_pack' => 0.0,
      'waste_qty_content' => 0.0,
      'waste_qty_pack' => 0.0,
      'waste_component_qty_content' => 0.0,
      'waste_component_qty_pack' => 0.0,
      'waste_component_value' => 0.0,
      'spoilage_qty_content' => 0.0,
      'spoilage_qty_pack' => 0.0,
      'spoilage_value' => 0.0,
      'process_loss_qty_content' => 0.0,
      'process_loss_qty_pack' => 0.0,
      'process_loss_value' => 0.0,
      'variance_qty_content' => 0.0,
      'variance_qty_pack' => 0.0,
      'variance_value' => 0.0,
      'adjustment_plus_qty_content' => 0.0,
      'adjustment_plus_qty_pack' => 0.0,
      'adjustment_plus_value' => 0.0,
      'adjustment_qty_content' => 0.0,
      'adjustment_qty_pack' => 0.0,
      'closing_qty_content' => 0.0,
      'closing_qty_pack' => 0.0,
      'total_value' => 0.0,
      'avg_cost_per_content' => 0.0,
      'avg_cost_per_pack' => 0.0,
      '_content_per_buy_sum' => 0.0,
      '_hpp_sum' => 0.0,
      '_hpp_pack_sum' => 0.0,
      'children' => [],
    ];
  }

  $avgCostPerContent = (float)($row['avg_cost_per_content'] ?? 0);
  $avgCostPerPack = $avgCostPerContent * (float)($row['profile_content_per_buy'] ?? 0);
  $parent =& $parentMap[$parentKey];

  $parent['profile_count']++;
  $parent['_content_per_buy_sum'] += (float)($row['profile_content_per_buy'] ?? 0);
  $parent['_hpp_sum'] += $avgCostPerContent;
  $parent['_hpp_pack_sum'] += $avgCostPerPack;

  if ($parent['profile_buy_uom_code'] !== '' && (string)($row['profile_buy_uom_code'] ?? '') !== '' && $parent['profile_buy_uom_code'] !== (string)($row['profile_buy_uom_code'] ?? '')) {
    $parent['profile_buy_uom_code'] = 'MIX';
  }
  if ($parent['profile_content_uom_code'] !== '' && (string)($row['profile_content_uom_code'] ?? '') !== '' && $parent['profile_content_uom_code'] !== (string)($row['profile_content_uom_code'] ?? '')) {
    $parent['profile_content_uom_code'] = 'MIX';
  }

  foreach (['opening_qty_content','opening_qty_pack','in_qty_content','in_qty_pack','out_qty_content','out_qty_pack','discard_qty_content','discard_qty_pack','spoil_qty_content','spoil_qty_pack','waste_qty_content','waste_qty_pack','waste_component_qty_content','waste_component_qty_pack','waste_component_value','spoilage_qty_content','spoilage_qty_pack','spoilage_value','process_loss_qty_content','process_loss_qty_pack','process_loss_value','variance_qty_content','variance_qty_pack','variance_value','adjustment_plus_qty_content','adjustment_plus_qty_pack','adjustment_plus_value','adjustment_qty_content','adjustment_qty_pack','closing_qty_content','closing_qty_pack','total_value'] as $metricKey) {
    $parent[$metricKey] += (float)($row[$metricKey] ?? 0);
  }

  $parent['children'][] = $row;
}
unset($parent);

$parentRows = array_values($parentMap);
usort($parentRows, static function (array $a, array $b): int {
  $aName = trim(($a['item_name'] ?? '') !== '' ? (string)$a['item_name'] : (string)($a['material_name'] ?? ''));
  $bName = trim(($b['item_name'] ?? '') !== '' ? (string)$b['item_name'] : (string)($b['material_name'] ?? ''));
  return strcasecmp($aName, $bName);
});

foreach ($parentRows as &$parentRow) {
  $count = max(1, (int)($parentRow['profile_count'] ?? 0));
  $parentRow['avg_content_per_buy'] = (float)$parentRow['_content_per_buy_sum'] / $count;
  $parentRow['avg_cost_per_content'] = (float)$parentRow['_hpp_sum'] / $count;
  $parentRow['avg_cost_per_pack'] = (float)$parentRow['_hpp_pack_sum'] / $count;
}
unset($parentRow);

$summaryRows = count($monthlyRows);
$summaryInPack = 0.0;
$summaryOutPack = 0.0;
$summaryClosingPack = 0.0;
$summaryIn = 0.0;
$summaryOut = 0.0;
$summaryClosing = 0.0;
$summaryValue = 0.0;
foreach ($monthlyRows as $row) {
  $summaryInPack += (float)($row['in_qty_pack'] ?? 0);
  $summaryOutPack += (float)($row['out_qty_pack'] ?? 0);
  $summaryClosingPack += (float)($row['closing_qty_pack'] ?? 0);
  $summaryIn += (float)($row['in_qty_content'] ?? 0);
  $summaryOut += (float)($row['out_qty_content'] ?? 0);
  $summaryClosing += (float)($row['closing_qty_content'] ?? 0);
  $summaryValue += (float)($row['total_value'] ?? 0);
}
?>

<style>
  :root {
    --swd-sticky-top: 0px;
  }
  .swd-sticky-head {
    position: fixed;
    top: var(--swd-sticky-top);
    left: 0;
    display: none;
    overflow: hidden;
    z-index: 1035;
    pointer-events: none;
    background: #fff8f4;
    border: 1px solid #ead5ca;
    border-bottom: 0;
    border-radius: 14px 14px 0 0;
    box-shadow: 0 10px 24px rgba(95, 23, 39, 0.12);
  }
  .swd-sticky-head table {
    margin-bottom: 0;
    transform: translateX(0);
    will-change: transform;
  }
  .swd-sticky-head th {
    background: #fff8f4 !important;
    box-shadow: inset 0 -1px 0 #e8d1c5;
    white-space: nowrap;
  }
  .swd-table-wrap {
    overflow-x: auto;
    overflow-y: visible;
  }
  .swd-monthly-table thead th {
    background: #fff8f4;
    box-shadow: inset 0 -1px 0 #e8d1c5;
  }
  .swd-monthly-table th:nth-child(2),
  .swd-monthly-table td:nth-child(2) {
    min-width: 250px;
  }
  .swd-monthly-table th:nth-child(3),
  .swd-monthly-table td:nth-child(3) {
    min-width: 260px;
  }
  .swd-parent-row {
    background: #fff6ef;
    border-top: 2px solid #f0d8ca;
  }
  .swd-child-row {
    background: #fffdfb;
  }
  .swd-name-cell {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    min-width: 0;
  }
  .swd-name-body {
    min-width: 0;
    display: grid;
    gap: 0.22rem;
  }
  .swd-name-meta,
  .swd-profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.32rem;
  }
  .swd-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.48rem;
    font-size: 0.68rem;
    font-weight: 800;
    max-width: 100%;
    color: #855346;
    border: 1px solid #ead5ca;
    background: #fff8f3;
  }
  .swd-chip.is-parent {
    border-radius: 999px;
  }
  .swd-chip.is-child {
    border-radius: 10px;
    border-style: dashed;
    background: #fffaf7;
  }
  .swd-object-name {
    font-weight: 700;
    color: #4e1f2e;
    line-height: 1.25;
  }
  .swd-object-code {
    color: #876a65;
    font-size: 0.8rem;
  }
  .swd-profile-stack {
    display: grid;
    gap: 0.24rem;
  }
  .swd-profile-stack.is-child {
    padding-left: 1.25rem;
    position: relative;
  }
  .swd-profile-stack.is-child::before {
    content: '';
    position: absolute;
    left: 0.45rem;
    top: 0.2rem;
    bottom: 0.2rem;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(180deg, #ebd7cc 0%, #d9b6a4 100%);
  }
  .swd-profile-note,
  .swd-child-label {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9a6f60;
  }
</style>

<div class="mb-2">
  <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
  <small class="text-muted">Rekap per bulan. Rentang tampilan opsional selalu dibatasi di dalam bulan yang dipilih.</small>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'WAREHOUSE', 'active_tab' => 'daily']); ?>
</div>
<?php $this->load->view('purchase/_warehouse_stock_generate_btn', [
  'warehouse_action_params' => ['month' => $genMonth, 'date_from' => (string)($date_from ?? '')],
]); ?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" class="form-control" id="swdMonth" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / profile / merk / keterangan">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Mulai Tampilan</label>
        <input type="date" class="form-control" id="swdDateFrom" name="date_from" min="<?php echo html_escape($windowStart); ?>" max="<?php echo html_escape($windowEnd); ?>" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tampilan</label>
        <input type="date" class="form-control" id="swdDateTo" name="date_to" min="<?php echo html_escape($windowStart); ?>" max="<?php echo html_escape($windowEnd); ?>" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" name="limit" min="1" max="1000" value="<?php echo (int)$limit; ?>">
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

<?php $this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan snapshot stok gudang',
  'stock_summary_cards' => [
    ['label' => 'Profil Bulanan', 'value' => number_format($summaryRows), 'tone' => 'violet', 'icon' => 'ri-archive-stack-line'],
    ['label' => 'Total Masuk', 'value' => number_format($summaryInPack, 2, ',', '.'), 'detail' => 'Isi: ' . number_format($summaryIn, 2, ',', '.'), 'tone' => 'aqua', 'icon' => 'ri-arrow-down-circle-line'],
    ['label' => 'Total Keluar', 'value' => number_format($summaryOutPack, 2, ',', '.'), 'detail' => 'Isi: ' . number_format($summaryOut, 2, ',', '.'), 'tone' => 'blue', 'icon' => 'ri-arrow-up-circle-line'],
    ['label' => 'Stok Akhir', 'value' => number_format($summaryClosingPack, 2, ',', '.'), 'detail' => 'Isi: ' . number_format($summaryClosing, 2, ',', '.'), 'tone' => 'amber', 'icon' => 'ri-scales-3-line'],
    ['label' => 'Total Nilai', 'value' => 'Rp ' . number_format($summaryValue, 2, ',', '.'), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
  ],
]); ?>

<div class="card">
  <div class="swd-sticky-head" id="swdStickyHead" aria-hidden="true"></div>
  <div class="table-responsive swd-table-wrap">
    <table class="table table-striped table-hover mb-0 swd-monthly-table" id="swdMonthlyTable">
      <thead>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>Profil Pack</th>
          <th>Satuan</th>
          <th class="text-end">Isi / Pack</th>
          <th class="text-end">Stok Awal</th>
          <th class="text-end">Masuk</th>
          <th class="text-end">Keluar</th>
          <th class="text-end">WASTE</th>
          <th class="text-end">SPOILAGE</th>
          <th class="text-end">PROCESS LOSS</th>
          <th class="text-end">VARIANCE</th>
          <th class="text-end">Adjustment +</th>
          <th class="text-end">Stok Akhir</th>
          <th class="text-end">Nilai Total</th>
          <th class="text-end">HPP / Isi</th>
          <th class="text-end">HPP / Pack</th>
          <th class="text-end">Total Isi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parentRows)): ?>
          <tr><td colspan="18" class="text-center text-muted py-4">Belum ada data bulanan gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($parentRows as $idx => $parent): ?>
            <?php
              $itemText = trim((string)($parent['item_name'] ?? ''));
              $materialText = trim((string)($parent['material_name'] ?? ''));
              $nameText = $itemText !== '' ? $itemText : ($materialText !== '' ? $materialText : '-');
              $codeText = trim((string)($parent['item_code'] ?? ''));
              if ($codeText === '') {
                $codeText = trim((string)($parent['material_code'] ?? ''));
              }
              $kindText = strtoupper((string)($parent['stock_domain'] ?? 'ITEM')) === 'MATERIAL' ? 'Material' : 'Item';
              $rowId = 'swd-parent-' . (int)$idx;
              $isExpandable = ((int)($parent['profile_count'] ?? 0) > 1);
              $singleChild = (!$isExpandable && !empty($parent['children'])) ? $parent['children'][0] : null;
              $totalIsi = (float)($parent['closing_qty_content'] ?? 0);
              $uomPack = (string)($parent['profile_buy_uom_code'] ?? '-');
              $uomContent = (string)($parent['profile_content_uom_code'] ?? '');
              if (is_array($singleChild)) {
                $uomPack = (string)($singleChild['profile_buy_uom_code'] ?? $uomPack);
                $uomContent = (string)($singleChild['profile_content_uom_code'] ?? $uomContent);
              }
            ?>
            <tr class="swd-parent-row">
              <td>
                <?php if ($isExpandable): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary swd-toggle" data-target="<?php echo html_escape($rowId); ?>">+</button>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
                <span class="ms-1"><?php echo (int)($idx + 1); ?></span>
              </td>
              <td>
                <div class="swd-name-cell">
                  <div class="swd-name-body">
                    <div class="swd-object-name"><?php echo html_escape($nameText); ?></div>
                    <div class="swd-name-meta">
                      <?php if ($codeText !== ''): ?>
                        <span class="swd-chip is-parent"><?php echo html_escape($codeText); ?></span>
                      <?php endif; ?>
                      <span class="swd-chip is-parent"><?php echo html_escape($kindText); ?></span>
                      <?php if ($isExpandable): ?>
                        <span class="swd-chip is-parent"><?php echo (int)($parent['profile_count'] ?? 0); ?> profil</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($isExpandable): ?>
                  <div class="swd-profile-stack">
                    <div class="swd-profile-meta">
                      <span class="swd-chip is-parent"><?php echo (int)($parent['profile_count'] ?? 0); ?> profil aktif</span>
                    </div>
                    <div class="swd-profile-note">Expand untuk lihat rincian profil.</div>
                  </div>
                <?php else: ?>
                  <?php
                    $singleProfile = is_array($singleChild) ? $singleChild : [];
                    $profileLines = [];
                    $brandText = trim((string)($singleProfile['profile_brand'] ?? ''));
                    $descText = trim((string)($singleProfile['profile_description'] ?? ''));
                    if ($brandText !== '' && $brandText !== '-') {
                      $profileLines[] = 'Merk: ' . html_escape($brandText);
                    }
                    if ($descText !== '' && $descText !== '-') {
                      $profileLines[] = 'Ket: ' . html_escape($descText);
                    }
                  ?>
                  <div class="swd-profile-stack">
                    <div class="swd-profile-meta">
                      <?php foreach ($profileLines as $profileLine): ?>
                        <span class="swd-chip is-parent"><?php echo $profileLine; ?></span>
                      <?php endforeach; ?>
                    </div>
                    <?php if (is_array($singleChild)): ?>
                      <div class="small mt-1"><a href="<?php echo html_escape($buildLotUrl($singleChild)); ?>">Audit Profil</a></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?php echo html_escape($uomPack); ?></td>
              <td class="text-end">
                <?php
                  $contentPerBuy = is_array($singleChild)
                    ? (float)($singleChild['profile_content_per_buy'] ?? 0)
                    : (float)($parent['avg_content_per_buy'] ?? 0);
                ?>
                <?php echo number_format($contentPerBuy, 2, ',', '.'); ?> <?php echo html_escape($uomContent); ?>
              </td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['opening_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['opening_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end text-success"><div class="fw-semibold"><?php echo ui_num((float)($parent['in_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['in_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end text-danger"><div class="fw-semibold"><?php echo ui_num((float)($parent['out_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['out_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['waste_component_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['waste_component_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['waste_component_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['spoilage_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['spoilage_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['spoilage_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['process_loss_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['process_loss_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['process_loss_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['variance_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['variance_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['variance_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['adjustment_plus_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['adjustment_plus_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['adjustment_plus_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end fw-semibold"><div class="fw-semibold"><?php echo ui_num((float)($parent['closing_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['closing_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><?php echo number_format((float)($parent['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo ui_num((float)($parent['avg_cost_per_content'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num((float)($parent['avg_cost_per_pack'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num($totalIsi); ?></td>
            </tr>
            <?php if ($isExpandable): ?>
            <?php foreach ((array)($parent['children'] ?? []) as $child): ?>
              <?php
                $childUomPack = (string)($child['profile_buy_uom_code'] ?? '-');
                $childUomContent = (string)($child['profile_content_uom_code'] ?? '');
                $childAvgCostPack = (float)($child['avg_cost_per_content'] ?? 0) * (float)($child['profile_content_per_buy'] ?? 0);
              ?>
              <tr class="swd-child-row <?php echo html_escape($rowId); ?>" style="display:none;">
                <td></td>
                <td>
                  <div class="swd-name-cell">
                    <div class="swd-name-body">
                      <div class="swd-child-label">Profil Turunan</div>
                      <div class="swd-object-name"><?php echo html_escape($nameText); ?></div>
                      <div class="swd-name-meta">
                        <?php if ($codeText !== ''): ?>
                          <span class="swd-chip is-child"><?php echo html_escape($codeText); ?></span>
                        <?php endif; ?>
                        <span class="swd-chip is-child">Child</span>
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="swd-profile-stack is-child">
                    <div class="swd-object-name"><?php echo html_escape((string)($child['profile_name'] ?? '-')); ?></div>
                    <div class="swd-profile-meta">
                      <span class="swd-chip is-child">Brand: <?php echo html_escape((string)($child['profile_brand'] ?? '-')); ?></span>
                      <span class="swd-chip is-child"><?php echo number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape($childUomContent); ?> / <?php echo html_escape($childUomPack); ?></span>
                    </div>
                    <div class="small mt-1"><a href="<?php echo html_escape($buildLotUrl($child)); ?>">Audit Profil</a></div>
                  </div>
                </td>
                <td><?php echo html_escape($childUomPack); ?></td>
                <td class="text-end"><?php echo number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape($childUomContent); ?></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['opening_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['opening_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end text-success"><div class="fw-semibold"><?php echo ui_num((float)($child['in_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['in_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end text-danger"><div class="fw-semibold"><?php echo ui_num((float)($child['out_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['out_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['waste_component_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['waste_component_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['waste_component_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['spoilage_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['spoilage_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['spoilage_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['process_loss_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['process_loss_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['process_loss_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['variance_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['variance_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['variance_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['adjustment_plus_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['adjustment_plus_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['adjustment_plus_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end fw-semibold"><div class="fw-semibold"><?php echo ui_num((float)($child['closing_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['closing_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end"><?php echo number_format((float)($child['total_value'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['avg_cost_per_content'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num($childAvgCostPack); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['closing_qty_content'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  document.querySelectorAll('.swd-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var target = btn.getAttribute('data-target');
      if (!target) { return; }
      var rows = document.querySelectorAll('.' + target);
      if (!rows.length) { return; }
      var willShow = rows[0].style.display === 'none';
      rows.forEach(function(row){
        row.style.display = willShow ? '' : 'none';
      });
      btn.textContent = willShow ? '-' : '+';
    });
  });
})();
</script>
<script>
(() => {
  const month = document.getElementById('swdMonth');
  const from = document.getElementById('swdDateFrom');
  const to = document.getElementById('swdDateTo');
  if (!month || !from || !to) return;
  const syncWindow = () => {
    if (!/^\d{4}-\d{2}$/.test(month.value)) return;
    const start = `${month.value}-01`;
    const [year, monthNumber] = month.value.split('-').map(Number);
    const end = `${month.value}-${String(new Date(year, monthNumber, 0).getDate()).padStart(2, '0')}`;
    [from, to].forEach((input) => {
      input.min = start;
      input.max = end;
      if (input.value && input.value < start) input.value = start;
      if (input.value && input.value > end) input.value = end;
    });
    if (from.value && to.value && from.value > to.value) to.value = from.value;
  };
  month.addEventListener('change', syncWindow);
  syncWindow();
})();
</script>

<script>
(function(){
  function syncWarehouseDailyStickyTop(){
    var navbar = document.getElementById('layout-navbar') || document.querySelector('.layout-navbar');
    var topOffset = navbar ? Math.ceil(navbar.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--swd-sticky-top', topOffset + 'px');
    return topOffset;
  }

  function initWarehouseDailyFloatingHeader(){
    var wrapper = document.querySelector('.swd-table-wrap');
    var table = document.getElementById('swdMonthlyTable');
    var host = document.getElementById('swdStickyHead');
    if (!wrapper || !table || !host) { return; }
    var thead = table.querySelector('thead');
    if (!thead) { return; }

    host.innerHTML = '<table class="' + table.className + '"><thead>' + thead.innerHTML + '</thead></table>';
    var cloneTable = host.querySelector('table');
    var cloneHead = cloneTable ? cloneTable.querySelector('thead') : null;
    if (!cloneTable || !cloneHead) { return; }

    function syncFloatingHeader(){
      var stickyTop = syncWarehouseDailyStickyTop();
      var wrapperRect = wrapper.getBoundingClientRect();
      var tableRect = table.getBoundingClientRect();
      var originalThs = Array.prototype.slice.call(thead.querySelectorAll('th'));
      var cloneThs = Array.prototype.slice.call(cloneHead.querySelectorAll('th'));
      originalThs.forEach(function(th, index){
        if (!cloneThs[index]) { return; }
        var width = Math.ceil(th.getBoundingClientRect().width);
        cloneThs[index].style.width = width + 'px';
        cloneThs[index].style.minWidth = width + 'px';
        cloneThs[index].style.maxWidth = width + 'px';
      });
      cloneTable.style.width = Math.ceil(table.getBoundingClientRect().width) + 'px';
      cloneTable.style.transform = 'translateX(' + (-wrapper.scrollLeft) + 'px)';

      var headerHeight = Math.ceil(thead.getBoundingClientRect().height || 0);
      var shouldShow = wrapperRect.top <= stickyTop && tableRect.bottom > (stickyTop + headerHeight);
      host.style.display = shouldShow ? 'block' : 'none';
      host.style.top = stickyTop + 'px';
      host.style.left = Math.ceil(wrapperRect.left) + 'px';
      host.style.width = Math.ceil(wrapperRect.width) + 'px';
    }

    wrapper.addEventListener('scroll', syncFloatingHeader, { passive: true });
    window.addEventListener('scroll', syncFloatingHeader, { passive: true });
    window.addEventListener('resize', syncFloatingHeader);
    requestAnimationFrame(syncFloatingHeader);
  }

  syncWarehouseDailyStickyTop();
  window.addEventListener('resize', syncWarehouseDailyStickyTop);
  initWarehouseDailyFloatingHeader();
})();
</script>
