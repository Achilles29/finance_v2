<?php
$baseUrl    = site_url((string)($base_url_adjustment ?? 'inventory/stock/adjustment'));
$searchUrl  = site_url('inventory/stock/adjustment/item-search');
$storeUrl   = site_url('inventory/stock/adjustment/store');
$postBaseUrl   = site_url('inventory/stock/adjustment/post');
$voidBaseUrl   = site_url('inventory/stock/adjustment/void');
$deleteBaseUrl = site_url('inventory/stock/adjustment/delete');
$stepUpVerifyUrl = site_url('inventory/stock/adjustment/step-up/verify');
$stockAdjustmentCsrfToken = (string)($stock_adjustment_csrf_token ?? '');

$rows       = is_array($rows ?? null) ? $rows : [];
$lineRows   = is_array($line_rows ?? null) ? $line_rows : [];
$divisions  = is_array($divisions ?? null) ? $divisions : [];
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];

$stockScope       = strtoupper(trim((string)($stock_scope ?? 'WAREHOUSE')));
$isDivisionScope  = !empty($is_division_scope);
$isWarehouseScope = !$isDivisionScope;
$selectedDivisionId  = (int)($division_id ?? 0);
$selectedDestination = strtoupper(trim((string)($destination ?? ($isDivisionScope ? 'OTHER' : 'ALL'))));
if ($selectedDestination === '') { $selectedDestination = $isDivisionScope ? 'OTHER' : 'ALL'; }

$activeTab = strtolower(trim((string)($active_tab ?? 'input')));
if (!in_array($activeTab, ['input', 'rincian'], true)) { $activeTab = 'input'; }
$selectedStatus = strtoupper(trim((string)($status ?? 'ALL')));
if (!in_array($selectedStatus, ['ALL', 'DRAFT', 'POSTED', 'VOID'], true)) { $selectedStatus = 'ALL'; }
$statusCounts = is_array($status_counts ?? null) ? $status_counts : [];

$dateFrom = (string)($date_from ?? '');
$dateTo   = (string)($date_to   ?? '');
$qSearch  = (string)($q ?? '');

// Column labels by scope
$qtyUnitLabel            = $isDivisionScope ? 'Isi' : 'Pack / Satuan Beli';
$costInputLabel          = $isDivisionScope ? 'Unit Cost / Isi' : 'Harga Satuan / Beli';
$availColumnLabel        = $isDivisionScope ? 'Avail (Isi)' : 'Avail (Pack)';
$wasteColumnLabel        = $isDivisionScope ? 'Waste (Isi)' : 'Waste (Pack)';
$spoilColumnLabel        = $isDivisionScope ? 'Spoil (Isi)' : 'Spoil (Pack)';
$processLossColumnLabel  = $isDivisionScope ? 'P.Loss (Isi)' : 'P.Loss (Pack)';
$varianceColumnLabel     = $isDivisionScope ? 'Variance (Isi)' : 'Variance (Pack)';
$adjustmentPlusColumnLabel = $isDivisionScope ? 'Adj + (Isi)' : 'Adj + (Pack)';
$costColumnLabel         = $isDivisionScope ? 'Cost / Isi' : 'Harga / Beli';
$kpiUomLabel             = $isDivisionScope ? 'Isi' : 'Pack';

$adjustmentReasonOptions = [
  'WASTE' => ['cancel_order' => 'Cancel Order','kitchen_error' => 'Kitchen Error','overproduction' => 'Overproduction','spillage' => 'Spillage / Tumpah','prep_trim_excess' => 'Prep Trim Excess','expired_opened' => 'Expired Opened','other' => 'Other'],
  'SPOILAGE' => ['expired' => 'Expired','temperature_abuse' => 'Temperature Abuse','contamination' => 'Contamination','overstock' => 'Overstock','improper_storage' => 'Improper Storage','other' => 'Other'],
  'PROCESS_LOSS' => ['defrost_loss' => 'Defrost Loss','trimming_standard' => 'Trimming Standard','cooking_loss' => 'Cooking Loss','evaporation' => 'Evaporation','brew_loss' => 'Brew Loss','absorption_loss' => 'Absorption Loss','process_residue' => 'Process Residue','variable_process_consumable' => 'Variable Process Consumable','other' => 'Other'],
  'VARIANCE' => ['over_usage' => 'Over Usage','under_usage' => 'Under Usage','unrecorded_usage' => 'Unrecorded Usage','counting_error' => 'Counting Error','system_mismatch' => 'System Mismatch','theft_suspected' => 'Theft Suspected','unknown_shrinkage' => 'Unknown Shrinkage','other' => 'Other'],
  'ADJUSTMENT_PLUS' => ['opening_correction' => 'Opening Correction','stock_found' => 'Stock Found','manual_reclass' => 'Manual Reclass','other' => 'Other'],
];
$adjustmentKindOptions = $isDivisionScope
  ? [
      'WASTE' => 'Waste (Isi)',
      'SPOIL' => 'Spoil (Isi)',
      'PROCESS_LOSS' => 'P.Loss (Isi)',
      'MINUS' => 'Variance (Isi)',
      'PLUS' => 'Adj + (Isi)',
    ]
  : [
      'WASTE' => 'Waste',
      'SPOIL' => 'Spoil',
      'PROCESS_LOSS' => 'Process Loss',
      'MINUS' => 'Variance / Minus',
      'PLUS' => 'Adjustment Plus',
    ];
$resolveReasonLabel = static function (string $category, ?string $value) use ($adjustmentReasonOptions): string {
  $key = trim((string)$value);
  return $key === '' ? '-' : (string)($adjustmentReasonOptions[$category][$key] ?? $key);
};
$detailQtyByScope = static function (array $row, string $field) use ($isWarehouseScope): float {
  $qtyContent = (float)($row[$field] ?? 0);
  if (!$isWarehouseScope) { return $qtyContent; }
  $factor = (float)($row['profile_content_per_buy'] ?? 1);
  return $qtyContent / max(1, $factor);
};
$detailCostByScope = static function (array $row) use ($isWarehouseScope): float {
  $unitCost = (float)($row['unit_cost'] ?? 0);
  if (!$isWarehouseScope) { return $unitCost; }
  $factor = (float)($row['profile_content_per_buy'] ?? 1);
  return $unitCost * max(1, $factor);
};
$detailObjectLabel = static function (array $row): string {
  $code = !empty($row['material_id']) ? (string)($row['material_code'] ?? ($row['item_code'] ?? '-')) : (string)($row['item_code'] ?? ($row['material_code'] ?? '-'));
  $name = !empty($row['material_id']) ? (string)($row['material_name'] ?? ($row['item_name'] ?? '-')) : (string)($row['item_name'] ?? ($row['material_name'] ?? '-'));
  return trim($code . ' - ' . $name);
};
$detailProfileLabel = static function (array $row): string {
  $parts = array_filter([trim((string)($row['profile_name'] ?? '')), trim((string)($row['profile_brand'] ?? ''))], static fn($v) => $v !== '');
  return !empty($parts) ? implode(' | ', $parts) : '-';
};
$detailReasonSummary = static function (array $row) use ($resolveReasonLabel): string {
  $parts = [];
  if ((float)($row['qty_waste_content'] ?? 0) > 0)            $parts[] = 'Waste: ' . $resolveReasonLabel('WASTE', $row['waste_reason_code'] ?? null);
  if ((float)($row['qty_spoil_content'] ?? 0) > 0)            $parts[] = 'Spoil: ' . $resolveReasonLabel('SPOILAGE', $row['spoil_reason_code'] ?? null);
  if ((float)($row['qty_variance_content'] ?? 0) > 0)         $parts[] = 'Minus: ' . $resolveReasonLabel('VARIANCE', $row['variance_reason_code'] ?? null);
  if ((float)($row['qty_adjustment_plus_content'] ?? 0) > 0)  $parts[] = 'Plus: '  . $resolveReasonLabel('ADJUSTMENT_PLUS', $row['adjustment_plus_reason_code'] ?? null);
  return !empty($parts) ? implode(' | ', $parts) : '-';
};
$destinationCategoryLabel = static function (string $dest): string {
  $d = strtoupper(trim($dest));
  if (in_array($d, ['BAR', 'KITCHEN', 'ROASTERY'], true))              return 'Reguler';
  if (in_array($d, ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true))  return 'Event';
  if ($d === 'OFFICE')                                      return 'Office';
  return 'Other';
};
$lineItemName = static function (array $row): string {
  $matName  = trim((string)($row['material_name'] ?? ''));
  $itemName = trim((string)($row['item_name'] ?? ''));
  return $matName !== '' ? $matName : ($itemName ?: '-');
};
$lineItemUom = static function (array $row): string {
  return trim((string)($row['profile_content_uom_code'] ?? ($row['default_content_uom_code'] ?? '')));
};
$lineQtyTotalContent = static function (array $row): float {
  return (float)($row['qty_waste_content'] ?? 0)
    + (float)($row['qty_spoil_content'] ?? 0)
    + (float)($row['qty_process_loss_content'] ?? 0)
    + (float)($row['qty_variance_content'] ?? 0)
    + (float)($row['qty_adjustment_plus_content'] ?? 0);
};
$lineEffectiveValue = static function (array $row) use ($lineQtyTotalContent): array {
  $qty = $lineQtyTotalContent($row);
  $unitCost = (float)($row['unit_cost'] ?? 0);
  $fallbackValue = (float)($row['shrink_fifo_total_cost'] ?? 0);
  $value = $qty * $unitCost;
  if ($unitCost <= 0 && $fallbackValue > 0) {
    $value = $fallbackValue;
    $unitCost = $qty > 0 ? ($fallbackValue / $qty) : 0.0;
  }
  return [$value, $unitCost];
};
$lineJenisAlasan = static function (array $row) use ($resolveReasonLabel): array {
  if ((float)($row['qty_adjustment_plus_content'] ?? 0) > 0) return ['Plus',    $resolveReasonLabel('ADJUSTMENT_PLUS', $row['adjustment_plus_reason_code'] ?? null)];
  if ((float)($row['qty_waste_content']            ?? 0) > 0) return ['Waste',   $resolveReasonLabel('WASTE',           $row['waste_reason_code']            ?? null)];
  if ((float)($row['qty_spoil_content']            ?? 0) > 0) return ['Spoil',   $resolveReasonLabel('SPOILAGE',        $row['spoil_reason_code']            ?? null)];
  if ((float)($row['qty_process_loss_content']     ?? 0) > 0) return ['P.Loss',  $resolveReasonLabel('PROCESS_LOSS',    $row['process_loss_reason_code']     ?? null)];
  if ((float)($row['qty_variance_content']         ?? 0) > 0) return ['Minus',   $resolveReasonLabel('VARIANCE',        $row['variance_reason_code']         ?? null)];
  return ['-', '-'];
};
$documentAdjustmentBreakdown = static function (array $row) use ($isWarehouseScope): array {
  $qtySuffix = $isWarehouseScope ? 'buy' : 'content';
  $parts = [
    ['label' => 'Waste',    'field' => 'total_waste_' . $qtySuffix,                'is_plus' => false],
    ['label' => 'Spoil',    'field' => 'total_spoil_' . $qtySuffix,                'is_plus' => false],
    ['label' => 'P.Loss',   'field' => 'total_process_loss_' . $qtySuffix,         'is_plus' => false],
    ['label' => 'Variance', 'field' => 'total_variance_' . $qtySuffix,             'is_plus' => false],
    ['label' => 'Adj +',    'field' => 'total_adjustment_plus_' . $qtySuffix,      'is_plus' => true],
  ];
  $result = [];
  foreach ($parts as $part) {
    $qty = (float)($row[$part['field']] ?? 0);
    if ($qty > 0) {
      $part['qty'] = $qty;
      $result[] = $part;
    }
  }
  return $result;
};
$fmtMoney = static function ($value): string { return 'Rp ' . number_format((float)$value, 2, ',', '.'); };

// ── KPI vars (POSTED rows only) ──
$kpiDocs = 0; $kpiLines = 0;
$kpiShrink = 0.0; $kpiAddition = 0.0;
$kpiWaste = 0.0; $kpiSpoil = 0.0; $kpiPL = 0.0; $kpiVariance = 0.0; $kpiPlus = 0.0;
$summaryDraft = 0; $summaryPosted = 0; $summaryVoid = 0;
foreach ($rows as $row) {
  $rowStatus = strtoupper((string)($row['status'] ?? 'DRAFT'));
  if ($rowStatus === 'POSTED') {
    $summaryPosted++;
    $kpiDocs++;
    $kpiLines   += (int)($row['line_count'] ?? 0);
    $kpiShrink  += (float)($row['total_waste_value'] ?? 0) + (float)($row['total_spoil_value'] ?? 0) + (float)($row['total_process_loss_value'] ?? 0) + (float)($row['total_variance_value'] ?? 0);
    $kpiAddition+= (float)($row['total_adjustment_plus_value'] ?? 0);
    $kpiWaste   += (float)($isWarehouseScope ? ($row['total_waste_buy'] ?? 0) : ($row['total_waste_content'] ?? 0));
    $kpiSpoil   += (float)($isWarehouseScope ? ($row['total_spoil_buy'] ?? 0) : ($row['total_spoil_content'] ?? 0));
    $kpiPL      += (float)($isWarehouseScope ? ($row['total_process_loss_buy'] ?? 0) : ($row['total_process_loss_content'] ?? 0));
    $kpiVariance+= (float)($isWarehouseScope ? ($row['total_variance_buy'] ?? 0) : ($row['total_variance_content'] ?? 0));
    $kpiPlus    += (float)($isWarehouseScope ? ($row['total_adjustment_plus_buy'] ?? 0) : ($row['total_adjustment_plus_content'] ?? 0));
  } elseif ($rowStatus === 'VOID') {
    $summaryVoid++;
  } else {
    $summaryDraft++;
  }
}
$kpiNet      = $kpiAddition - $kpiShrink;
$kpiShrinkQty = $kpiWaste + $kpiSpoil + $kpiPL + $kpiVariance;

// ── Pagination — Per Nota ──
$perPage     = max(10, (int)($limit ?? 25));
$currentPage = max(1, (int)($page ?? 1));
$totalRows   = count($rows);
$totalPages  = $totalRows > 0 ? (int)ceil($totalRows / $perPage) : 1;
$currentPage = min($currentPage, max(1, $totalPages));
$pagedRows   = array_slice($rows, ($currentPage - 1) * $perPage, $perPage);
$pBase = ['limit' => $perPage];
if ($dateFrom !== '')              $pBase['date_from']   = $dateFrom;
if ($dateTo !== '')                $pBase['date_to']     = $dateTo;
if ($qSearch !== '')               $pBase['q']           = $qSearch;
if ($isDivisionScope && $selectedDivisionId > 0)      $pBase['division_id'] = $selectedDivisionId;
if ($isDivisionScope && $selectedDestination !== 'ALL') $pBase['destination'] = $selectedDestination;
if ($isDivisionScope && $selectedStatus !== 'ALL')      $pBase['status'] = $selectedStatus;
$pNota    = array_merge($pBase, ['tab' => 'input']);
$pQsNota  = http_build_query($pNota);

// ── Pagination — Per Rincian ──
$totalLineRows  = count($lineRows);
$totalLinePages = $totalLineRows > 0 ? (int)ceil($totalLineRows / $perPage) : 1;
$currentLinePage = min($currentPage, max(1, $totalLinePages));
$pagedLineRows  = array_slice($lineRows, ($currentLinePage - 1) * $perPage, $perPage);
$pRincian = array_merge($pBase, ['tab' => 'rincian']);
$pQsRincian = http_build_query($pRincian);

// ── Detail summary (all line rows, before pagination) ──
$detailSummary = ['line_count' => count($lineRows), 'doc_count' => 0, 'shrink_value' => 0.0, 'addition_value' => 0.0, 'net_value' => 0.0];
$detailDocIds = [];
foreach ($lineRows as $lr) {
  $adjId = (int)($lr['adjustment_id'] ?? 0);
  if ($adjId > 0) $detailDocIds[$adjId] = true;
  [$effectiveValue, $effectiveUnitCost] = $lineEffectiveValue($lr);
  $isPlusSummary = (float)($lr['qty_adjustment_plus_content'] ?? 0) > 0;
  $detailSummary['shrink_value']   += $isPlusSummary ? 0 : $effectiveValue;
  $detailSummary['addition_value'] += (float)($lr['qty_adjustment_plus_content'] ?? 0) * $effectiveUnitCost;
}
$detailSummary['doc_count'] = count($detailDocIds);
$detailSummary['net_value'] = $detailSummary['addition_value'] - $detailSummary['shrink_value'];

// Tab URLs (for switching tabs while preserving all filter params)
$inputTabUrl  = $baseUrl . '?' . $pQsNota;
$rincianTabUrl = $baseUrl . '?' . $pQsRincian;

$statusTabs = [
  'ALL' => ['label' => 'Semua', 'icon' => 'ri-layout-grid-line', 'class' => 'all'],
  'DRAFT' => ['label' => 'Draft', 'icon' => 'ri-draft-line', 'class' => 'draft'],
  'POSTED' => ['label' => 'Posted', 'icon' => 'ri-checkbox-circle-line', 'class' => 'posted'],
  'VOID' => ['label' => 'Void', 'icon' => 'ri-close-circle-line', 'class' => 'void'],
];
$statusTabUrls = [];
foreach ($statusTabs as $statusKey => $statusTab) {
  $statusParams = $pBase;
  $statusParams['tab'] = $activeTab;
  unset($statusParams['status']);
  if ($statusKey !== 'ALL') { $statusParams['status'] = $statusKey; }
  $statusTabUrls[$statusKey] = $baseUrl . '?' . http_build_query($statusParams);
}
$statusCountMode = $activeTab === 'rincian' ? 'lines' : 'documents';
$statusCountLabel = $activeTab === 'rincian' ? 'baris' : 'dokumen';
?>

<style>
/* ── Filter ── */
.adj-filter-grid {
  display: grid;
  gap: .5rem;
  align-items: end;
}
.adj-filter-grid.div-scope {
  grid-template-columns: minmax(120px,1.2fr) 95px 115px 115px minmax(160px,2fr) 64px auto auto;
}
.adj-filter-grid.wh-scope {
  grid-template-columns: 115px 115px minmax(180px,3fr) 64px auto auto;
}
@media (max-width:1199px) {
  .adj-filter-grid.div-scope { grid-template-columns: minmax(100px,1fr) 88px 105px 105px minmax(130px,2fr) 58px auto auto; }
}
@media (max-width:991px) {
  .adj-filter-grid { grid-template-columns:1fr 1fr 1fr 1fr !important; }
  .adj-filter-btn { grid-column: span 2; display:flex; gap:.4rem; }
}
@media (max-width:575px) {
  .adj-filter-grid { grid-template-columns:1fr 1fr !important; }
  .adj-filter-btn { grid-column: span 2; }
}

/* ── Secondary status tabs for division adjustments ── */
.adj-status-tabs {
  display:flex; flex-wrap:wrap; align-items:center; gap:.45rem;
  padding:.6rem .85rem; border:1px solid #ecd9d1; border-top:0;
  border-radius:0 0 14px 14px; background:#fffaf7; margin-bottom:.8rem;
}
.adj-status-tabs-label { color:#77584d; font-size:.7rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; margin-right:.1rem; }
.adj-status-tab {
  display:inline-flex; align-items:center; gap:.35rem; min-height:31px;
  padding:.26rem .58rem; border:1px solid #ead7ce; border-radius:999px;
  background:#fff; color:#6f5145; font-size:.75rem; font-weight:700; text-decoration:none;
  transition:all .15s ease;
}
.adj-status-tab:hover { border-color:#b66a53; color:#6a2d3c; background:#fff6f1; }
.adj-status-tab .count { min-width:1.35rem; padding:.02rem .32rem; border-radius:999px; background:#f1e4df; color:#785545; text-align:center; font-size:.68rem; }
.adj-status-tab.is-active { color:#fff; background:#6a2d3c; border-color:#6a2d3c; box-shadow:0 5px 12px rgba(106,45,60,.18); }
.adj-status-tab.is-active .count { background:rgba(255,255,255,.19); color:#fff; }
.adj-status-tab.draft:not(.is-active) { color:#8a5900; border-color:#ecd694; }
.adj-status-tab.posted:not(.is-active) { color:#12653a; border-color:#bee0c9; }
.adj-status-tab.void:not(.is-active) { color:#9b2d2d; border-color:#efc4c4; }
@media (max-width:575px) {
  .adj-status-tabs { gap:.35rem; }
  .adj-status-tabs-label { flex-basis:100%; }
}

/* ── KPI ── */
.adj-kpi-row { display:grid; grid-template-columns:repeat(6,1fr); gap:.6rem; margin-bottom:1rem; }
@media (max-width:1199px) { .adj-kpi-row { grid-template-columns:repeat(3,1fr); } }
@media (max-width:575px)  { .adj-kpi-row { grid-template-columns:repeat(2,1fr); } }
.adj-kpi {
  border-radius:14px; padding:.95rem 1.1rem .9rem; color:#fff;
  position:relative; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.13);
}
.adj-kpi::before { content:''; position:absolute; right:-16px; bottom:-16px; width:76px; height:76px; border-radius:50%; background:rgba(255,255,255,.12); }
.adj-kpi::after  { content:''; position:absolute; right:12px; top:-20px; width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.08); }
.adj-kpi-icon { font-size:1.2rem; opacity:.82; margin-bottom:.3rem; display:block; }
.adj-kpi-val  { font-size:1.35rem; font-weight:800; line-height:1.1; }
.adj-kpi-sub  { font-size:.7rem; opacity:.75; margin-top:.1rem; }
.adj-kpi-lbl  { font-size:.67rem; opacity:.82; text-transform:uppercase; letter-spacing:.06em; margin-top:.2rem; }
.adj-kpi-1 { background:linear-gradient(135deg,#667eea,#764ba2); }
.adj-kpi-2 { background:linear-gradient(135deg,#0c7cba,#0fcdba); }
.adj-kpi-3 { background:linear-gradient(135deg,#e55d2b,#f7b733); }
.adj-kpi-4 { background:linear-gradient(135deg,#11998e,#38ef7d); }
.adj-kpi-5 { background:linear-gradient(135deg,#b22222,#e05252); }
.adj-kpi-6 { background:linear-gradient(135deg,#1c7ed6,#4dabf7); }

/* ── Table ── */
.adj-table-wrap { position:relative; overflow-x:auto; overflow-y:auto; max-height:72vh; }
.adj-table-wrap table thead th {
  position:sticky; top:0; z-index:2;
  background:#fff8f4; box-shadow:inset 0 -1px 0 #e8d1c5;
  white-space:nowrap;
}
#adj-nota-table.adj-nota-table--division { width:100%; min-width:860px; table-layout:fixed; }
#adj-nota-table.adj-nota-table--division th,
#adj-nota-table.adj-nota-table--division td { vertical-align:top; }
#adj-nota-table.adj-nota-table--division th:nth-child(1),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(1) { width:110px; }
#adj-nota-table.adj-nota-table--division th:nth-child(2),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(2) { width:86px; }
#adj-nota-table.adj-nota-table--division th:nth-child(3),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(3) { width:120px; }
#adj-nota-table.adj-nota-table--division th:nth-child(4),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(4) { width:82px; }
#adj-nota-table.adj-nota-table--division th:nth-child(5),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(5) { width:54px; }
#adj-nota-table.adj-nota-table--division th:nth-child(6),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(6) { width:102px; }
#adj-nota-table.adj-nota-table--division th:nth-child(7),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(7) { width:144px; }
#adj-nota-table.adj-nota-table--division th:nth-child(8),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(8) { width:108px; }
#adj-nota-table.adj-nota-table--division th:nth-child(9),
#adj-nota-table.adj-nota-table--division tbody > tr:not(.adj-nota-detail-row) > td:nth-child(9) { width:76px; }
#adj-nota-table .adj-nota-number { overflow-wrap:anywhere; line-height:1.25; }
#adj-nota-table.adj-nota-table--division .adj-adjustment-description { min-width:0; }
#adj-nota-table.adj-nota-table--division .adj-adjustment-kind { white-space:normal; }
#adj-nota-table .adj-nota-action {
  position:sticky; right:0; z-index:3; background:#fff;
  box-shadow:-8px 0 12px rgba(77,40,30,.08);
}
#adj-nota-table thead .adj-nota-action { z-index:4; background:#fff8f4; }
#adj-nota-table tbody tr:hover > .adj-nota-action { background:#fff7f2; }
.adj-nota-detail-row > td { padding:.4rem .7rem .65rem; background:#fffaf7; border-top:0; }
.adj-nota-detail-wrap { display:flex; flex-wrap:wrap; align-items:flex-start; gap:.45rem .8rem; }
.adj-nota-detail-group { display:flex; flex:1 1 420px; flex-wrap:wrap; align-items:center; gap:.35rem; min-width:0; }
.adj-nota-detail-label { color:#815546; font-size:.7rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
.adj-nota-product-list { display:flex; flex:1 1 260px; flex-wrap:wrap; gap:.28rem; min-width:0; }
.adj-nota-product-chip { display:inline-flex; align-items:center; max-width:100%; padding:.16rem .46rem; border:1px solid #efd8cd; border-radius:999px; background:#fff; color:#69453a; font-size:.72rem; line-height:1.25; overflow-wrap:anywhere; }
.adj-nota-product-more { color:#8a6255; font-size:.72rem; font-weight:700; }
.adj-nota-note { flex:1 1 220px; color:#6f625d; font-size:.74rem; line-height:1.35; overflow-wrap:anywhere; }
.adj-nota-note i { color:#a65a42; margin-right:.24rem; }
@media (max-width:767.98px) {
  #adj-nota-table.adj-nota-table--division { min-width:760px; }
  .adj-nota-detail-group { flex-basis:100%; }
}

/* ── Pagination ── */
.adj-pagination { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; }
.adj-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 .5rem;
  border:1px solid #ddd; border-radius:8px;
  background:#fff; color:#555; font-size:.8rem; font-weight:600;
  text-decoration:none; transition:all .15s;
}
.adj-page-btn:hover { background:#f5f5f5; border-color:#bbb; color:#333; }
.adj-page-btn.is-active { background:#6a2d3c; border-color:#6a2d3c; color:#fff; }
.adj-page-btn.is-disabled { opacity:.4; pointer-events:none; }

/* ── Misc (from original) ── */
.adjustment-search-result { cursor:pointer; }
.adjustment-search-result:hover { background:rgba(0,0,0,.03); }
.adjustment-selected-card { border:1px dashed rgba(0,0,0,.18); border-radius:.9rem; background:linear-gradient(180deg,#ffffff,#faf8f4); }
.adjustment-kind-panel { border:1px solid rgba(15,23,42,.08); border-radius:1rem; background:rgba(255,255,255,.86); padding:1rem; }
.adj-scroll-wrap { max-height:66vh; overflow-y:auto; overflow-x:auto; }
.adj-scroll-wrap table thead th { position:sticky; top:0; z-index:2; background:#fff; border-bottom:2px solid rgba(0,0,0,.09); }
.adj-adjustment-description { min-width:190px; }
.adj-adjustment-kind { display:inline-flex; align-items:center; gap:.3rem; margin:.08rem .15rem .08rem 0; padding:.16rem .46rem; border-radius:999px; font-size:.72rem; line-height:1.25; font-weight:700; white-space:nowrap; }
.adj-adjustment-kind.is-minus { color:#9f1239; background:#fff1f2; border:1px solid #fecdd3; }
.adj-adjustment-kind.is-plus { color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; }
.adjustment-confirm-modal .modal-content { border:0; border-radius:1rem; box-shadow:0 1.5rem 3rem rgba(17,24,39,.18); }
.adjustment-confirm-modal .modal-header, .adjustment-confirm-modal .modal-footer { border:0; }
.adjustment-confirm-hero { width:3rem; height:3rem; border-radius:.9rem; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(180deg,#fff7ed,#ffedd5); color:#c2410c; font-size:1.35rem; flex:0 0 auto; }
.adjustment-profile-choice-list { display:grid; gap:.8rem; }
.adjustment-profile-choice-card { width:100%; border:1px solid rgba(15,23,42,.08); border-radius:1rem; background:linear-gradient(180deg,#ffffff,#faf8f4); padding:.9rem 1rem; text-align:left; box-shadow:0 .75rem 1.6rem rgba(15,23,42,.05); transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease; }
.adjustment-profile-choice-card:hover { transform:translateY(-1px); border-color:rgba(147,51,234,.18); box-shadow:0 1rem 2rem rgba(15,23,42,.08); }
.adjustment-profile-choice-head { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; margin-bottom:.45rem; }
.adjustment-profile-choice-title { font-weight:700; color:#111827; }
.adjustment-profile-choice-key { display:inline-flex; align-items:center; padding:.18rem .55rem; border-radius:999px; background:#f8fafc; border:1px solid rgba(15,23,42,.08); color:#475569; font-size:.72rem; font-weight:700; white-space:nowrap; }
.adjustment-profile-choice-desc { color:#6b7280; font-size:.84rem; margin-bottom:.55rem; }
.adjustment-profile-choice-meta { color:#6b7280; font-size:.78rem; margin-bottom:.55rem; }
.adjustment-profile-choice-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; }
.adjustment-profile-choice-metric { border:1px solid rgba(15,23,42,.07); border-radius:.85rem; background:rgba(255,255,255,.82); padding:.55rem .65rem; }
.adjustment-profile-choice-metric .label { display:block; font-size:.72rem; color:#6b7280; margin-bottom:.15rem; }
.adjustment-profile-choice-metric strong { display:block; font-size:.92rem; color:#111827; line-height:1.2; }
@media (max-width:767.98px) { .adjustment-profile-choice-grid { grid-template-columns:1fr; } }
.adj-stat-bar { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; padding:.5rem 1rem; background:#fff9f6; border-bottom:1px solid #f0ddd4; font-size:.82rem; }
.adj-stat-chip { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .6rem; border-radius:999px; font-weight:600; font-size:.78rem; }
.adj-stat-draft   { background:#fef9c3; color:#854d0e; }
.adj-stat-posted  { background:#dcfce7; color:#14532d; }
.adj-stat-void    { background:#fee2e2; color:#7f1d1d; }
.adj-form-modal .modal-dialog { max-width:900px; }
</style>

<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-scales-3-line page-title-icon"></i><?php echo html_escape((string)$title); ?></h4>
    <small class="text-muted">
      Saat diposting: stok live, stok harian, dan lot FIFO ikut berubah secara otomatis.
    </small>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <button type="button" class="btn btn-primary" id="btn-tambah-adj" data-bs-toggle="modal" data-bs-target="#adjFormModal">
      <i class="ri ri-add-line"></i> Tambah Adjustment
    </button>
  </div>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => $isDivisionScope ? 'DIVISION' : 'WAREHOUSE', 'active_tab' => 'adjustment']); ?>
</div>
<div class="alert alert-light border mb-2 py-2 small">
  <strong>Gunakan halaman ini bila perubahan stoknya sudah diketahui.</strong>
  Contoh: waste, spoil, barang ditemukan, atau barang rusak. Isi <strong>jumlah perubahan</strong>, bukan stok fisik akhir.
  Jika sedang menghitung stok fisik seluruh barang, gunakan <a href="<?= site_url($isDivisionScope ? 'inventory/stock/daily-recon/division' : 'inventory/stock/daily-recon/warehouse') ?>">Daily Recon</a> agar selisih dan lot ikut disamakan.
</div>
<?php if ($isDivisionScope): ?>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => (string)($month ?? date('Y-m')), 'division_id' => (string)($division_id ?? ''), 'destination_type' => (string)($destination ?? '')],
]); ?>
<?php else: ?>
<?php $this->load->view('purchase/_warehouse_stock_generate_btn', [
  'warehouse_action_params' => ['month' => !empty($date_from) ? date('Y-m', strtotime((string)$date_from)) : date('Y-m'), 'date_from' => (string)$dateFrom],
]); ?>
<?php endif; ?>

<div id="adjustment-alert"></div>

<!-- Tab nav -->
<ul class="nav nav-tabs mb-0" role="tablist">
  <li class="nav-item" role="presentation">
    <a class="nav-link <?php echo $activeTab === 'input' ? 'active' : ''; ?>" href="<?php echo html_escape($inputTabUrl); ?>">
      <i class="ri ri-file-list-3-line me-1"></i>Per Nota
      <?php if ($summaryDraft > 0): ?>
        <span class="badge bg-warning text-dark ms-1"><?php echo $summaryDraft; ?> draft</span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link <?php echo $activeTab === 'rincian' ? 'active' : ''; ?>" href="<?php echo html_escape($rincianTabUrl); ?>">
      <i class="ri ri-list-check-2 me-1"></i>Per Rincian
    </a>
  </li>
</ul>

<?php if ($isDivisionScope): ?>
<div class="adj-status-tabs" aria-label="Filter status adjustment">
  <span class="adj-status-tabs-label"><i class="ri ri-filter-3-line me-1"></i>Status</span>
  <?php foreach ($statusTabs as $statusKey => $statusTab): ?>
    <?php $statusCount = (int)($statusCounts[$statusKey][$statusCountMode] ?? 0); ?>
    <a class="adj-status-tab <?php echo html_escape($statusTab['class']); ?><?php echo $selectedStatus === $statusKey ? ' is-active' : ''; ?>"
       href="<?php echo html_escape($statusTabUrls[$statusKey]); ?>"
       title="<?php echo number_format($statusCount); ?> <?php echo html_escape($statusCountLabel); ?>">
      <i class="<?php echo html_escape($statusTab['icon']); ?>"></i>
      <?php echo html_escape($statusTab['label']); ?>
      <span class="count"><?php echo number_format($statusCount); ?></span>
    </a>
  <?php endforeach; ?>
  <span class="small text-muted ms-auto">Menampilkan <?php echo html_escape($statusCountLabel); ?> sesuai filter aktif.</span>
</div>
<?php endif; ?>

<!-- ════════════════ TAB 1: PER NOTA ════════════════ -->
<?php if ($activeTab === 'input'): ?>

<!-- Filter -->
<div class="card mb-3" style="border-top-left-radius:0">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" id="adj-nota-filter-form">
      <input type="hidden" name="tab" value="input">
      <?php if ($isDivisionScope): ?>
        <input type="hidden" name="status" value="<?php echo html_escape($selectedStatus); ?>">
      <?php endif; ?>
      <div class="adj-filter-grid <?php echo $isDivisionScope ? 'div-scope' : 'wh-scope'; ?>">
        <?php if ($isDivisionScope): ?>
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id" id="adj-nota-division">
            <option value="">Semua Divisi</option>
            <?php foreach ($divisions as $dr): ?>
              <?php $dId = (int)($dr['id'] ?? 0); $dCode = trim((string)($dr['code'] ?? '')); $dName = trim((string)($dr['name'] ?? '')); $dLabel = $dCode !== '' ? $dCode . ' - ' . $dName : ($dName ?: (string)$dId); ?>
              <option value="<?php echo $dId; ?>" <?php echo $selectedDivisionId === $dId ? 'selected' : ''; ?>><?php echo html_escape($dLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination" id="adj-nota-destination">
            <option value="ALL" <?php echo $selectedDestination === 'ALL' ? 'selected' : ''; ?>>Semua</option>
            <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR</option>
            <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN</option>
            <option value="ROASTERY" <?php echo $selectedDestination === 'ROASTERY' ? 'selected' : ''; ?>>ROASTERY</option>
            <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
            <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
            <option value="ROASTERY_EVENT" <?php echo $selectedDestination === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>ROASTERY_EVENT</option>
            <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
            <option value="OTHER" <?php echo $selectedDestination === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label class="form-label mb-1">Dari</label>
          <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo html_escape($dateFrom); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Sampai</label>
          <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo html_escape($dateTo); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Cari</label>
          <input type="text" class="form-control form-control-sm" name="q" value="<?php echo html_escape($qSearch); ?>" placeholder="No adjustment / catatan" data-adj-filter-q autocomplete="off">
        </div>
        <div>
          <label class="form-label mb-1">/ Hal</label>
          <input type="number" class="form-control form-control-sm" name="limit" min="10" max="500" value="<?php echo $perPage; ?>">
        </div>
        <div class="adj-filter-btn" style="display:flex;gap:.4rem;">
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">Terapkan</button>
          <a href="<?php echo $baseUrl; ?>?tab=input" class="btn btn-sm btn-outline-danger w-100">Clear</a>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- KPI cards (POSTED only) -->
<?php if ($kpiDocs > 0 || count($rows) > 0): ?>
<div class="adj-kpi-row">
  <div class="adj-kpi adj-kpi-1">
    <span class="adj-kpi-icon"><i class="ri ri-file-check-line"></i></span>
    <div class="adj-kpi-val"><?php echo number_format($kpiDocs); ?></div>
    <div class="adj-kpi-sub"><?php echo number_format($kpiLines); ?> lines</div>
    <div class="adj-kpi-lbl">Dokumen Posted</div>
  </div>
  <div class="adj-kpi adj-kpi-2">
    <span class="adj-kpi-icon"><i class="ri ri-layout-grid-line"></i></span>
    <div class="adj-kpi-val"><?php echo number_format($kpiLines); ?></div>
    <div class="adj-kpi-sub"><?php echo number_format($kpiDocs); ?> dok. sumber</div>
    <div class="adj-kpi-lbl">Lines Posted</div>
  </div>
  <div class="adj-kpi adj-kpi-3">
    <span class="adj-kpi-icon"><i class="ri ri-arrow-up-circle-line"></i></span>
    <div class="adj-kpi-val"><?php echo number_format($kpiShrinkQty, 2, ',', '.'); ?></div>
    <div class="adj-kpi-sub"><?php echo html_escape($kpiUomLabel); ?> · W+S+PL+Var</div>
    <div class="adj-kpi-lbl">Total Qty Keluar</div>
  </div>
  <div class="adj-kpi adj-kpi-4">
    <span class="adj-kpi-icon"><i class="ri ri-arrow-down-circle-line"></i></span>
    <div class="adj-kpi-val"><?php echo number_format($kpiPlus, 2, ',', '.'); ?></div>
    <div class="adj-kpi-sub"><?php echo html_escape($kpiUomLabel); ?> · Adj+</div>
    <div class="adj-kpi-lbl">Total Qty Masuk</div>
  </div>
  <div class="adj-kpi adj-kpi-5">
    <span class="adj-kpi-icon"><i class="ri ri-money-dollar-circle-line"></i></span>
    <div class="adj-kpi-val"><?php echo 'Rp ' . number_format($kpiShrink, 0, ',', '.'); ?></div>
    <div class="adj-kpi-sub">Waste+Spoil+PL+Var</div>
    <div class="adj-kpi-lbl">Nilai Pengurang</div>
  </div>
  <div class="adj-kpi <?php echo $kpiNet >= 0 ? 'adj-kpi-6' : 'adj-kpi-5'; ?>">
    <span class="adj-kpi-icon"><i class="ri ri-scales-3-line"></i></span>
    <div class="adj-kpi-val"><?php echo ($kpiNet >= 0 ? '+' : '') . 'Rp ' . number_format($kpiNet, 0, ',', '.'); ?></div>
    <div class="adj-kpi-sub">Adj+ dikurangi pengurang</div>
    <div class="adj-kpi-lbl">Dampak Bersih</div>
  </div>
</div>
<?php endif; ?>

<!-- Table -->
<div class="card">
  <div class="adj-stat-bar">
    <span class="text-muted me-1">Total <?php echo number_format($totalRows); ?> nota</span>
    <?php if ($summaryDraft > 0): ?>
      <span class="adj-stat-chip adj-stat-draft"><i class="ri ri-edit-2-line"></i> <?php echo $summaryDraft; ?> Draft</span>
    <?php endif; ?>
    <?php if ($summaryPosted > 0): ?>
      <span class="adj-stat-chip adj-stat-posted"><i class="ri ri-check-line"></i> <?php echo $summaryPosted; ?> Posted</span>
    <?php endif; ?>
    <?php if ($summaryVoid > 0): ?>
      <span class="adj-stat-chip adj-stat-void"><i class="ri ri-close-line"></i> <?php echo $summaryVoid; ?> Void</span>
    <?php endif; ?>
  </div>
  <div class="adj-table-wrap">
    <table class="table table-sm table-hover mb-0<?php echo $isDivisionScope ? ' adj-nota-table--division' : ''; ?>" id="adj-nota-table">
      <thead>
        <tr>
          <th class="adj-nota-col-no">No Adjustment</th>
          <th>Tanggal</th>
          <?php if ($isDivisionScope): ?><th>Divisi / Tujuan</th><?php endif; ?>
          <th>Status</th>
          <th class="text-end">Lines</th>
          <?php if ($isDivisionScope): ?>
            <th class="text-end">Penyesuaian (Isi)</th>
            <th>Keterangan</th>
          <?php else: ?>
            <th class="text-end"><?php echo html_escape($wasteColumnLabel); ?></th>
            <th class="text-end"><?php echo html_escape($spoilColumnLabel); ?></th>
            <th class="text-end"><?php echo html_escape($processLossColumnLabel); ?></th>
            <th class="text-end"><?php echo html_escape($varianceColumnLabel); ?></th>
            <th class="text-end"><?php echo html_escape($adjustmentPlusColumnLabel); ?></th>
          <?php endif; ?>
          <th class="text-end">Nilai Total</th>
          <th class="adj-nota-action">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($pagedRows)): ?>
        <tr><td colspan="<?php echo $isDivisionScope ? '9' : '11'; ?>" class="text-center text-muted py-4">Belum ada dokumen adjustment untuk filter ini.</td></tr>
      <?php else: ?>
        <?php foreach ($pagedRows as $row): ?>
          <?php
            $documentTotalValue = (float)($row['total_waste_value'] ?? 0)
              + (float)($row['total_spoil_value'] ?? 0)
              + (float)($row['total_process_loss_value'] ?? 0)
              + (float)($row['total_variance_value'] ?? 0)
              + (float)($row['total_adjustment_plus_value'] ?? 0);
            $rowStatus = strtoupper((string)($row['status'] ?? 'DRAFT'));
            $documentAdjustmentParts = $isDivisionScope ? $documentAdjustmentBreakdown($row) : [];
            $documentShrinkQty = 0.0;
            $documentPlusQty = 0.0;
            foreach ($documentAdjustmentParts as $documentAdjustmentPart) {
              if (!empty($documentAdjustmentPart['is_plus'])) {
                $documentPlusQty += (float)$documentAdjustmentPart['qty'];
              } else {
                $documentShrinkQty += (float)$documentAdjustmentPart['qty'];
              }
            }
            $documentProductNames = array_values(array_unique(array_filter(array_map(static function ($name) {
              return trim((string)$name);
            }, explode('||', (string)($row['adjusted_product_names'] ?? ''))), static function ($name) {
              return $name !== '' && $name !== '-';
            })));
            $visibleDocumentProductNames = array_slice($documentProductNames, 0, 8);
            $remainingDocumentProductCount = max(0, count($documentProductNames) - count($visibleDocumentProductNames));
            $documentNotes = trim((string)($row['notes'] ?? ''));
          ?>
          <tr id="stock-adjustment-<?php echo (int)($row['id'] ?? 0); ?>">
            <td class="adj-nota-col-no">
              <div class="fw-semibold small adj-nota-number"><?php echo html_escape((string)($row['adjustment_no'] ?? '-')); ?></div>
              <?php if (!$isDivisionScope && $documentNotes !== ''): ?>
                <small class="text-muted"><?php echo html_escape($documentNotes); ?></small>
              <?php endif; ?>
            </td>
            <td class="small"><?php echo html_escape((string)($row['adjustment_date'] ?? '-')); ?></td>
            <?php if ($isDivisionScope): ?>
            <td>
              <div class="small fw-semibold"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div>
              <small class="text-muted"><?php echo html_escape((string)($row['destination_type'] ?? '-')); ?></small>
            </td>
            <?php endif; ?>
            <td><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></td>
            <td class="text-end small"><?php echo number_format((int)($row['line_count'] ?? 0)); ?></td>
            <?php if ($isDivisionScope): ?>
              <td class="text-end small fw-semibold">
                <?php if ($documentShrinkQty > 0): ?>
                  <div class="text-danger">-<?php echo ui_num($documentShrinkQty); ?></div>
                <?php endif; ?>
                <?php if ($documentPlusQty > 0): ?>
                  <div class="text-success">+<?php echo ui_num($documentPlusQty); ?></div>
                <?php endif; ?>
                <?php if ($documentShrinkQty <= 0 && $documentPlusQty <= 0): ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="small adj-adjustment-description">
                <?php if (empty($documentAdjustmentParts)): ?>
                  <span class="text-muted">-</span>
                <?php else: ?>
                  <?php foreach ($documentAdjustmentParts as $documentAdjustmentPart): ?>
                    <span class="adj-adjustment-kind <?php echo !empty($documentAdjustmentPart['is_plus']) ? 'is-plus' : 'is-minus'; ?>">
                      <?php echo html_escape((string)$documentAdjustmentPart['label']); ?>
                      <strong><?php echo (!empty($documentAdjustmentPart['is_plus']) ? '+' : '-') . ui_num((float)$documentAdjustmentPart['qty']); ?></strong>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
            <?php else: ?>
              <td class="text-end small"><?php echo ui_num((float)($row['total_waste_buy'] ?? 0)); ?></td>
              <td class="text-end small"><?php echo ui_num((float)($row['total_spoil_buy'] ?? 0)); ?></td>
              <td class="text-end small"><?php echo ui_num((float)($row['total_process_loss_buy'] ?? 0)); ?></td>
              <td class="text-end small"><?php echo ui_num((float)($row['total_variance_buy'] ?? 0)); ?></td>
              <td class="text-end small"><?php echo ui_num((float)($row['total_adjustment_plus_buy'] ?? 0)); ?></td>
            <?php endif; ?>
            <td class="text-end small fw-semibold"><?php echo html_escape($fmtMoney($documentTotalValue)); ?></td>
            <td class="adj-nota-action">
              <?php if ($rowStatus === 'DRAFT'): ?>
                <div class="d-flex gap-1 flex-nowrap">
                  <button type="button" class="btn btn-sm btn-outline-success action-icon-btn btn-post-doc" data-id="<?php echo (int)$row['id']; ?>" title="Post"><i class="ri ri-upload-2-line"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-danger action-icon-btn btn-delete-doc" data-id="<?php echo (int)$row['id']; ?>" title="Hapus"><i class="ri ri-delete-bin-line"></i></button>
                </div>
              <?php elseif ($rowStatus === 'POSTED'): ?>
                <button type="button" class="btn btn-sm btn-outline-danger action-icon-btn btn-void-doc" data-id="<?php echo (int)$row['id']; ?>" title="Void"><i class="ri ri-close-circle-line"></i></button>
              <?php else: ?>
                <span class="text-muted small">Void</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($isDivisionScope && (!empty($visibleDocumentProductNames) || $documentNotes !== '')): ?>
          <tr class="adj-nota-detail-row">
            <td colspan="9">
              <div class="adj-nota-detail-wrap">
                <?php if (!empty($visibleDocumentProductNames)): ?>
                <div class="adj-nota-detail-group">
                  <span class="adj-nota-detail-label"><i class="ri ri-shopping-basket-2-line"></i> Rincian bahan / produk</span>
                  <div class="adj-nota-product-list">
                    <?php foreach ($visibleDocumentProductNames as $productName): ?>
                      <span class="adj-nota-product-chip"><?php echo html_escape($productName); ?></span>
                    <?php endforeach; ?>
                    <?php if ($remainingDocumentProductCount > 0): ?>
                      <span class="adj-nota-product-more">+<?php echo number_format($remainingDocumentProductCount); ?> lainnya</span>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>
                <?php if ($documentNotes !== ''): ?>
                  <div class="adj-nota-note"><i class="ri ri-sticky-note-line"></i><?php echo html_escape($documentNotes); ?></div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalRows > 0): ?>
  <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <?php $fromRow = ($currentPage - 1) * $perPage + 1; $toRow = min($currentPage * $perPage, $totalRows); ?>
    <span class="text-muted small">Nota <?php echo "{$fromRow}–{$toRow}"; ?> dari <?php echo $totalRows; ?></span>
    <?php if ($totalPages > 1): ?>
    <div class="adj-pagination">
      <?php
        $ws = max(1, $currentPage - 2); $we = min($totalPages, $currentPage + 2);
        if ($we - $ws < 4) { if ($ws === 1) $we = min($totalPages, $ws + 4); else $ws = max(1, $we - 4); }
      ?>
      <a href="<?php echo html_escape($baseUrl . '?' . $pQsNota . '&page=' . ($currentPage - 1)); ?>" class="adj-page-btn<?php echo $currentPage > 1 ? '' : ' is-disabled'; ?>">&#8249;</a>
      <?php if ($ws > 1): ?><a href="<?php echo html_escape($baseUrl . '?' . $pQsNota . '&page=1'); ?>" class="adj-page-btn">1</a><?php if ($ws > 2): ?><span class="text-muted small px-1">…</span><?php endif; ?><?php endif; ?>
      <?php for ($pn = $ws; $pn <= $we; $pn++): ?><a href="<?php echo html_escape($baseUrl . '?' . $pQsNota . '&page=' . $pn); ?>" class="adj-page-btn<?php echo $pn === $currentPage ? ' is-active' : ''; ?>"><?php echo $pn; ?></a><?php endfor; ?>
      <?php if ($we < $totalPages): ?><?php if ($we < $totalPages - 1): ?><span class="text-muted small px-1">…</span><?php endif; ?><a href="<?php echo html_escape($baseUrl . '?' . $pQsNota . '&page=' . $totalPages); ?>" class="adj-page-btn"><?php echo $totalPages; ?></a><?php endif; ?>
      <a href="<?php echo html_escape($baseUrl . '?' . $pQsNota . '&page=' . ($currentPage + 1)); ?>" class="adj-page-btn<?php echo $currentPage < $totalPages ? '' : ' is-disabled'; ?>">&#8250;</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php else: // ════════════════ TAB 2: PER RINCIAN ════════════════ ?>

<!-- Filter Rincian -->
<div class="card mb-3" style="border-top-left-radius:0">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" id="adj-rincian-filter-form">
      <input type="hidden" name="tab" value="rincian">
      <?php if ($isDivisionScope): ?>
        <input type="hidden" name="status" value="<?php echo html_escape($selectedStatus); ?>">
      <?php endif; ?>
      <div class="adj-filter-grid <?php echo $isDivisionScope ? 'div-scope' : 'wh-scope'; ?>">
        <?php if ($isDivisionScope): ?>
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id" id="adj-rincian-division">
            <option value="">Semua Divisi</option>
            <?php foreach ($divisions as $dr): ?>
              <?php $dId = (int)($dr['id'] ?? 0); $dCode = trim((string)($dr['code'] ?? '')); $dName = trim((string)($dr['name'] ?? '')); $dLabel = $dCode !== '' ? $dCode . ' - ' . $dName : ($dName ?: (string)$dId); ?>
              <option value="<?php echo $dId; ?>" <?php echo $selectedDivisionId === $dId ? 'selected' : ''; ?>><?php echo html_escape($dLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination" id="adj-rincian-destination">
            <option value="ALL" <?php echo $selectedDestination === 'ALL' ? 'selected' : ''; ?>>Semua</option>
            <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR</option>
            <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN</option>
            <option value="ROASTERY" <?php echo $selectedDestination === 'ROASTERY' ? 'selected' : ''; ?>>ROASTERY</option>
            <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
            <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
            <option value="ROASTERY_EVENT" <?php echo $selectedDestination === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>ROASTERY_EVENT</option>
            <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
            <option value="OTHER" <?php echo $selectedDestination === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label class="form-label mb-1">Dari</label>
          <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo html_escape($dateFrom); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Sampai</label>
          <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo html_escape($dateTo); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Cari</label>
          <input type="text" class="form-control form-control-sm" name="q" value="<?php echo html_escape($qSearch); ?>" placeholder="No adj / profile / item / lot / catatan" data-adj-filter-q autocomplete="off">
        </div>
        <div>
          <label class="form-label mb-1">/ Hal</label>
          <input type="number" class="form-control form-control-sm" name="limit" min="10" max="500" value="<?php echo $perPage; ?>">
        </div>
        <div class="adj-filter-btn" style="display:flex;gap:.4rem;">
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">Terapkan</button>
          <a href="<?php echo $baseUrl; ?>?tab=rincian" class="btn btn-sm btn-outline-danger w-100">Clear</a>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Summary bar -->
<div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
  <span><strong class="text-dark"><?php echo number_format($detailSummary['line_count']); ?></strong> baris tampil dari <?php echo number_format($detailSummary['doc_count']); ?> dokumen</span>
  <span>Nilai pengurang: <strong class="text-danger"><?php echo $fmtMoney($detailSummary['shrink_value']); ?></strong></span>
  <span>Adj+: <strong class="text-success"><?php echo $fmtMoney($detailSummary['addition_value']); ?></strong></span>
  <span>Dampak bersih: <strong class="<?php echo $detailSummary['net_value'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $fmtMoney($detailSummary['net_value']); ?></strong></span>
</div>

<div class="card">
  <div class="adj-table-wrap">
    <table class="table table-sm table-hover mb-0">
      <thead>
        <tr>
          <th>NO</th>
          <th>TANGGAL</th>
          <?php if ($isDivisionScope): ?><th>DIVISI / TUJUAN</th><?php endif; ?>
          <th>STATUS</th>
          <th>ITEM / BAHAN / PRODUK</th>
          <th class="text-end">QTY ISI</th>
          <th>JENIS &amp; ALASAN</th>
          <th class="text-end">COST ISI</th>
          <th class="text-end">NILAI (Rp)</th>
          <th>LOT</th>
          <th>CATATAN</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($pagedLineRows)): ?>
        <tr><td colspan="<?php echo $isDivisionScope ? '11' : '10'; ?>" class="text-center text-muted py-4">Belum ada rincian adjustment untuk filter ini.</td></tr>
      <?php else: ?>
        <?php foreach ($pagedLineRows as $lineRow): ?>
          <?php
            $lineQtyTotal  = $lineQtyTotalContent($lineRow);
            [$lineTotalValue, $lineUnitCostDisplay] = $lineEffectiveValue($lineRow);
            $lineUom       = trim((string)($lineRow['profile_content_uom_code'] ?? ''));
            $lineName      = $lineItemName($lineRow);
            [$lineKind, $lineReason] = $lineJenisAlasan($lineRow);
            $lineIsPlus    = ($lineKind === 'Plus');
            $lotDisplay    = trim((string)($lineRow['plus_lot_no'] ?? ''))
                          ?: trim((string)($lineRow['shrink_lot_no'] ?? ''));
            $lineStatus = strtoupper((string)($lineRow['status'] ?? 'DRAFT'));
            if ($lotDisplay === '' && $lineStatus === 'DRAFT') $lotDisplay = 'Belum dipost';
            $destType  = strtoupper(trim((string)($lineRow['destination_type'] ?? '')));
            $destCat   = $destinationCategoryLabel($destType);
            $divName   = trim((string)($lineRow['division_name'] ?? '-'));
          ?>
          <tr>
            <td class="small fw-semibold text-nowrap"><?php echo html_escape(trim((string)($lineRow['adjustment_no'] ?? '-'))); ?></td>
            <td class="small text-nowrap"><?php echo html_escape((string)($lineRow['adjustment_date'] ?? '-')); ?></td>
            <?php if ($isDivisionScope): ?>
            <td class="small text-nowrap">
              <div><?php echo html_escape($destType); ?></div>
              <small class="text-muted"><?php echo html_escape($destCat); ?></small>
            </td>
            <?php endif; ?>
            <td><?php echo ui_status_badge($lineStatus); ?></td>
            <td>
              <div class="fw-semibold small"><?php echo html_escape($lineName); ?></div>
              <?php if ($lineUom !== ''): ?><small class="text-muted"><?php echo html_escape($lineUom); ?></small><?php endif; ?>
              <?php $relatedProducts = trim((string)($lineRow['related_product_names'] ?? '')); ?>
              <?php if ($relatedProducts !== ''): ?>
                <?php $relatedProductParts = array_values(array_filter(explode(' | ', $relatedProducts))); $relatedProductPreview = implode(', ', array_slice($relatedProductParts, 0, 3)); ?>
                <small class="text-primary d-block" title="<?php echo html_escape($relatedProducts); ?>">Produk: <?php echo html_escape($relatedProductPreview); ?><?php echo count($relatedProductParts) > 3 ? ' +' . (count($relatedProductParts) - 3) . ' lainnya' : ''; ?></small>
              <?php endif; ?>
            </td>
            <td class="text-end small fw-semibold <?php echo $lineIsPlus ? 'text-success' : 'text-danger'; ?>">
              <?php echo ($lineIsPlus ? '+' : '-') . number_format($lineQtyTotal, 2, ',', '.'); ?>
              <?php if ($lineUom !== ''): ?><small class="text-muted d-block"><?php echo html_escape($lineUom); ?></small><?php endif; ?>
            </td>
            <td class="small">
              <span class="badge <?php echo $lineIsPlus ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $lineIsPlus ? 'text-success' : 'text-danger'; ?> border <?php echo $lineIsPlus ? 'border-success' : 'border-danger'; ?> me-1"><?php echo html_escape($lineKind); ?></span>
              <span class="text-muted"><?php echo html_escape($lineReason); ?></span>
            </td>
            <td class="text-end small"><?php echo number_format($lineUnitCostDisplay, 2, ',', '.'); ?></td>
            <td class="text-end small fw-semibold <?php echo $lineIsPlus ? 'text-success' : 'text-danger'; ?>">
              <?php echo ($lineIsPlus ? '+' : '-') . 'Rp ' . number_format($lineTotalValue, 2, ',', '.'); ?>
            </td>
            <td class="small">
              <?php if ($lotDisplay !== '' && $lotDisplay !== 'Belum dipost'): ?>
                <span class="text-monospace small" title="<?php echo html_escape($lotDisplay); ?>"><?php echo html_escape(strlen($lotDisplay) > 20 ? substr($lotDisplay, 0, 18) . '…' : $lotDisplay); ?></span>
                <?php if (trim((string)($lineRow['inbound_expiry_date'] ?? '')) !== ''): ?><small class="text-muted d-block">Exp: <?php echo html_escape((string)$lineRow['inbound_expiry_date']); ?></small><?php endif; ?>
              <?php elseif ($lineStatus === 'DRAFT'): ?>
                <span class="text-muted fst-italic small">Belum dipost</span>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php $lineNote = trim((string)($lineRow['note'] ?? ($lineRow['line_note'] ?? ''))); ?>
              <?php $hdrNote = trim((string)($lineRow['header_notes'] ?? '')); ?>
              <?php if ($lineNote !== ''): ?><div><?php echo html_escape($lineNote); ?></div><?php endif; ?>
              <?php if ($hdrNote !== ''): ?><small class="text-muted"><?php echo html_escape($hdrNote); ?></small><?php endif; ?>
              <?php if ($lineNote === '' && $hdrNote === ''): ?>-<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalLineRows > 0): ?>
  <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <?php $fromLR = ($currentLinePage - 1) * $perPage + 1; $toLR = min($currentLinePage * $perPage, $totalLineRows); ?>
    <span class="text-muted small">Baris <?php echo "{$fromLR}–{$toLR}"; ?> dari <?php echo $totalLineRows; ?></span>
    <?php if ($totalLinePages > 1): ?>
    <div class="adj-pagination">
      <?php
        $ws2 = max(1, $currentLinePage - 2); $we2 = min($totalLinePages, $currentLinePage + 2);
        if ($we2 - $ws2 < 4) { if ($ws2 === 1) $we2 = min($totalLinePages, $ws2 + 4); else $ws2 = max(1, $we2 - 4); }
      ?>
      <a href="<?php echo html_escape($baseUrl . '?' . $pQsRincian . '&page=' . ($currentLinePage - 1)); ?>" class="adj-page-btn<?php echo $currentLinePage > 1 ? '' : ' is-disabled'; ?>">&#8249;</a>
      <?php if ($ws2 > 1): ?><a href="<?php echo html_escape($baseUrl . '?' . $pQsRincian . '&page=1'); ?>" class="adj-page-btn">1</a><?php if ($ws2 > 2): ?><span class="text-muted small px-1">…</span><?php endif; ?><?php endif; ?>
      <?php for ($pn2 = $ws2; $pn2 <= $we2; $pn2++): ?><a href="<?php echo html_escape($baseUrl . '?' . $pQsRincian . '&page=' . $pn2); ?>" class="adj-page-btn<?php echo $pn2 === $currentLinePage ? ' is-active' : ''; ?>"><?php echo $pn2; ?></a><?php endfor; ?>
      <?php if ($we2 < $totalLinePages): ?><?php if ($we2 < $totalLinePages - 1): ?><span class="text-muted small px-1">…</span><?php endif; ?><a href="<?php echo html_escape($baseUrl . '?' . $pQsRincian . '&page=' . $totalLinePages); ?>" class="adj-page-btn"><?php echo $totalLinePages; ?></a><?php endif; ?>
      <a href="<?php echo html_escape($baseUrl . '?' . $pQsRincian . '&page=' . ($currentLinePage + 1)); ?>" class="adj-page-btn<?php echo $currentLinePage < $totalLinePages ? '' : ' is-disabled'; ?>">&#8250;</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php endif; // end tab ?>

<!-- ════════════════ MODAL: Form Tambah Adjustment ════════════════ -->
<div class="modal fade adj-form-modal" id="adjFormModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-uppercase text-muted fw-semibold">Divisi Adjustment</div>
          <h5 class="modal-title mb-0">Tambah Adjustment Bahan Baku</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="adjustment-form-alert" class="mb-2"></div>
        <div class="row g-3">
          <!-- Left: Form -->
          <div class="col-lg-5">
            <form id="adjustment-form" class="row g-2" autocomplete="off">
              <input type="hidden" id="stock_scope" value="<?php echo html_escape($stockScope); ?>">
              <?php if ($isDivisionScope): ?>
              <div class="col-md-6">
                <label class="form-label">Divisi</label>
                <select class="form-select" id="division_id" required>
                  <option value="">Pilih divisi...</option>
                  <?php foreach ($divisions as $dr): ?>
                    <?php $id = (int)($dr['id'] ?? 0); $code = trim((string)($dr['code'] ?? '')); $name = trim((string)($dr['name'] ?? '')); $label = $code !== '' ? $code . ' - ' . $name : ($name ?: (string)$id); ?>
                    <option value="<?php echo $id; ?>" <?php echo $selectedDivisionId === $id ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tujuan</label>
                <select class="form-select" id="destination_type" required>
                  <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR (Reguler)</option>
                  <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN (Reguler)</option>
                  <option value="ROASTERY" <?php echo $selectedDestination === 'ROASTERY' ? 'selected' : ''; ?>>ROASTERY (Reguler)</option>
                  <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
                  <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
                  <option value="ROASTERY_EVENT" <?php echo $selectedDestination === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>ROASTERY_EVENT</option>
                  <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
                  <option value="OTHER" <?php echo $selectedDestination === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
                </select>
              </div>
              <?php endif; ?>
              <div class="col-md-6">
                <label class="form-label">Tanggal Adjustment</label>
                <input type="date" class="form-control" id="adjustment_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
              </div>
              <div class="col-md-<?php echo $isDivisionScope ? '12' : '6'; ?>">
                <label class="form-label">Catatan Header</label>
                <input type="text" class="form-control" id="header_notes" placeholder="Opsional">
              </div>

              <div class="col-12">
                <label class="form-label">Cari Item / Profile</label>
                <input type="text" class="form-control" id="item_search" placeholder="Ketik minimal 2 huruf...">
                <div id="item_search_results" class="list-group mt-1" style="max-height:200px;overflow:auto;"></div>
              </div>

              <div class="col-12">
                <div class="adjustment-selected-card p-3" id="selected-card">
                  <div class="fw-semibold mb-1">Belum ada profile dipilih.</div>
                  <div class="text-muted small">Pilih item/profile dari hasil pencarian.</div>
                </div>
              </div>

              <div class="col-md-5">
                <label class="form-label" id="adjustment_qty_label">Penyesuaian (<?php echo html_escape($qtyUnitLabel); ?>)</label>
                <input type="number" class="form-control" id="adjustment_qty_input" min="0" step="0.01" value="0" disabled>
                <div class="form-text" id="adjustment_qty_hint">Pilih keterangan penyesuaian terlebih dahulu.</div>
              </div>

              <div class="col-md-7">
                <label class="form-label">Keterangan</label>
                <select class="form-select" id="adjustment_kind" required>
                  <option value="">Pilih keterangan penyesuaian...</option>
                  <?php foreach ($adjustmentKindOptions as $val => $lbl): ?>
                    <option value="<?php echo html_escape($val); ?>"><?php echo html_escape($lbl); ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Satu line hanya untuk satu jenis. Tambahkan line baru bila jenisnya berbeda.</div>
              </div>

              <div class="col-12">
                <div class="adjustment-kind-panel d-none" id="adjustment-kind-panel">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label" id="adjustment_reason_label">Alasan</label>
                      <select class="form-select" id="adjustment_reason_input"><option value="other">Other</option></select>
                    </div>
                    <div class="col-md-4 d-none" id="adjustment-plus-cost-wrap">
                      <label class="form-label"><?php echo html_escape($costInputLabel); ?></label>
                      <input type="number" class="form-control" id="unit_cost" min="0" step="0.01" value="0">
                      <div class="form-text" id="unit_cost_hint">
                        <?php if ($isDivisionScope): ?>
                          Untuk stok divisi, isi harga per isi, bukan per pack.
                        <?php else: ?>
                          Isi harga per satuan beli.
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="col-md-4 d-none" id="adjustment-plus-lot-wrap">
                      <label class="form-label">Lot Masuk Manual</label>
                      <input type="text" class="form-control" id="inbound_lot_no" placeholder="Opsional (auto jika kosong)">
                    </div>
                    <div class="col-md-4 d-none" id="adjustment-plus-exp-wrap">
                      <label class="form-label">Exp Date Lot Masuk</label>
                      <input type="date" class="form-control" id="inbound_expiry_date">
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Catatan Line</label>
                <input type="text" class="form-control" id="line_note" placeholder="Opsional">
              </div>
              <div class="col-12 d-grid">
                <button type="button" class="btn btn-outline-primary" id="btn-add-line">Tambah Line</button>
              </div>
            </form>
          </div>

          <!-- Right: Draft summary + draft lines table -->
          <div class="col-lg-7">
            <div class="row g-2 mb-3">
              <div class="col-md-3">
                <div class="adjustment-metric-card">
                  <span class="label">Line Draft</span>
                  <span class="value" id="draft-summary-line-count">0</span>
                </div>
              </div>
              <div class="col-md-3">
                <div class="adjustment-metric-card">
                  <span class="label">Qty <?php echo html_escape($qtyUnitLabel); ?></span>
                  <span class="value" id="draft-summary-qty-total">0</span>
                </div>
              </div>
              <div class="col-md-3">
                <div class="adjustment-metric-card value-danger">
                  <span class="label">Nilai Pengurang</span>
                  <span class="value" id="draft-summary-shrink-value"><?php echo html_escape($fmtMoney(0)); ?></span>
                </div>
              </div>
              <div class="col-md-3">
                <div class="adjustment-metric-card value-success">
                  <span class="label">Nilai Bersih</span>
                  <span class="value" id="draft-summary-net-value"><?php echo html_escape($fmtMoney(0)); ?></span>
                </div>
              </div>
            </div>
            <div class="adj-scroll-wrap">
              <table class="table table-sm table-striped mb-0 adjustment-line-table" id="draft-lines-table">
                <thead>
                  <tr>
                    <th>Objek</th>
                    <th class="text-end"><?php echo html_escape($availColumnLabel); ?></th>
                    <?php if ($isDivisionScope): ?>
                      <th class="text-end">Penyesuaian (Isi)</th>
                      <th>Keterangan</th>
                    <?php else: ?>
                      <th class="text-end"><?php echo html_escape($wasteColumnLabel); ?></th>
                      <th class="text-end"><?php echo html_escape($spoilColumnLabel); ?></th>
                      <th class="text-end"><?php echo html_escape($processLossColumnLabel); ?></th>
                      <th class="text-end"><?php echo html_escape($varianceColumnLabel); ?></th>
                      <th class="text-end"><?php echo html_escape($adjustmentPlusColumnLabel); ?></th>
                    <?php endif; ?>
                    <th class="text-end"><?php echo html_escape($costColumnLabel); ?></th>
                    <th class="text-end">Nilai</th>
                    <th>Lot In</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="11" class="text-center text-muted py-3">Belum ada line draft.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="btn-save-draft">Simpan Draft</button>
      </div>
    </div>
  </div>
</div>

<!-- Profile picker modal -->
<div class="modal fade" id="adjustmentProfilePickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-uppercase text-muted fw-semibold">Pilih Profil Adjustment</div>
          <h5 class="modal-title mb-0" id="adjustment-profile-picker-title">Pilih profil item</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3" id="adjustment-profile-picker-meta">Pilih profil yang benar.</div>
        <div class="adjustment-profile-choice-list" id="adjustment-profile-picker-options"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm modal -->
<div class="modal fade adjustment-confirm-modal" id="adjustmentConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header pb-0">
        <div>
          <div class="small text-uppercase text-muted fw-semibold" id="adjustment-confirm-kicker">Konfirmasi</div>
          <h5 class="modal-title mb-0" id="adjustment-confirm-title">Konfirmasi aksi</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="d-flex gap-3 align-items-start">
          <div class="adjustment-confirm-hero"><i class="ri-alert-line"></i></div>
          <div>
            <div class="fw-semibold mb-1" id="adjustment-confirm-message">Pastikan aksi ini memang ingin dilanjutkan.</div>
            <div class="text-muted small" id="adjustment-confirm-note">Perubahan akan langsung diproses.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary px-4" id="adjustment-confirm-submit">Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="stockAdjustmentStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-uppercase text-muted fw-semibold" id="stock-adjustment-step-up-kicker">Verifikasi Ulang</div>
          <h5 class="modal-title mb-0" id="stock-adjustment-step-up-title">Verifikasi Adjustment Stok</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3" id="stock-adjustment-step-up-message">Masukkan password akun Anda untuk melanjutkan.</div>
        <label for="stock_adjustment_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="stock_adjustment_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada dokumen adjustment.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="stock-adjustment-step-up-submit">Verifikasi &amp; Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<style>
  .adjustment-metric-card { border:1px solid rgba(15,23,42,.08); border-radius:1rem; padding:.7rem .9rem; background:linear-gradient(180deg,#ffffff,#faf8f4); box-shadow:0 .5rem 1.2rem rgba(15,23,42,.05); height:100%; }
  .adjustment-metric-card .label { display:block; font-size:.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; font-weight:700; margin-bottom:.2rem; }
  .adjustment-metric-card .value { display:block; font-size:1.1rem; font-weight:800; color:#111827; line-height:1.15; }
  .adjustment-metric-card .meta  { display:block; margin-top:.3rem; color:#6b7280; font-size:.75rem; }
  .adjustment-metric-card.value-danger .value { color:#b42318; }
  .adjustment-metric-card.value-success .value { color:#166534; }
  .adjustment-line-table td, .adjustment-line-table th { vertical-align:middle; }
</style>

<script>
(() => {
  const destinationGuardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const stockScope      = document.getElementById('stock_scope')?.value || 'WAREHOUSE';
  const isDivisionScope = stockScope === 'DIVISION';
  const isWarehouseScope = stockScope === 'WAREHOUSE';
  const draftTableColspan = isDivisionScope ? 8 : 11;
  const alertArea       = document.getElementById('adjustment-alert');
  const formAlertArea   = document.getElementById('adjustment-form-alert');
  const profileModalEl  = document.getElementById('adjustmentProfilePickerModal');
  const profileModalTitleEl   = document.getElementById('adjustment-profile-picker-title');
  const profileModalMetaEl    = document.getElementById('adjustment-profile-picker-meta');
  const profileModalOptionsEl = document.getElementById('adjustment-profile-picker-options');
  const confirmModalEl        = document.getElementById('adjustmentConfirmModal');
  const confirmKickerEl       = document.getElementById('adjustment-confirm-kicker');
  const confirmTitleEl        = document.getElementById('adjustment-confirm-title');
  const confirmMessageEl      = document.getElementById('adjustment-confirm-message');
  const confirmNoteEl         = document.getElementById('adjustment-confirm-note');
  const confirmSubmitBtn      = document.getElementById('adjustment-confirm-submit');
  const stockAdjustmentCsrfToken = <?php echo json_encode($stockAdjustmentCsrfToken, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const stockAdjustmentStepUpUrl = '<?php echo $stepUpVerifyUrl; ?>';
  const stockAdjustmentStepUpModalEl = document.getElementById('stockAdjustmentStepUpModal');
  const stockAdjustmentStepUpKickerEl = document.getElementById('stock-adjustment-step-up-kicker');
  const stockAdjustmentStepUpTitleEl = document.getElementById('stock-adjustment-step-up-title');
  const stockAdjustmentStepUpMessageEl = document.getElementById('stock-adjustment-step-up-message');
  const stockAdjustmentStepUpPasswordEl = document.getElementById('stock_adjustment_step_up_password');
  const stockAdjustmentStepUpSubmitBtn = document.getElementById('stock-adjustment-step-up-submit');
  const confirmModal = (confirmModalEl && window.bootstrap?.Modal) ? new window.bootstrap.Modal(confirmModalEl) : null;
  const searchInput   = document.getElementById('item_search');
  const searchResults = document.getElementById('item_search_results');
  const selectedCard  = document.getElementById('selected-card');
  const draftTableBody = document.querySelector('#draft-lines-table tbody');
  const lines = [];
  let selectedItem = null;
  let selectedProfileOptions = [];
  let currentSearchItems = [];
  let profilePickerBaseItem = null;
  let profilePickerOptions = [];
  let profileLookupToken = 0;
  let searchLookupToken = 0;
  let searchTimer = null;
  let confirmResolver = null;
  let confirmBackdropEl = null;
  let pendingStockAdjustmentAction = null;
  let stockAdjustmentStepUpSubmitting = false;
  const reasonLabelMap = <?php echo json_encode($adjustmentReasonOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const formDivisionEl    = document.getElementById('division_id');
  const formDestinationEl = document.getElementById('destination_type');
  const docFilterDivisionEl    = document.getElementById('adj-nota-division');
  const docFilterDestinationEl = document.getElementById('adj-nota-destination');
  const lineFilterDivisionEl    = document.getElementById('adj-rincian-division');
  const lineFilterDestinationEl = document.getElementById('adj-rincian-destination');
  const formDestinationOptions = [
    {value:'BAR',label:'BAR (Reguler)'},{value:'KITCHEN',label:'KITCHEN (Reguler)'},{value:'ROASTERY',label:'ROASTERY (Reguler)'},
    {value:'BAR_EVENT',label:'BAR_EVENT'},{value:'KITCHEN_EVENT',label:'KITCHEN_EVENT'},{value:'ROASTERY_EVENT',label:'ROASTERY_EVENT'},
    {value:'OFFICE',label:'OFFICE'},{value:'OTHER',label:'OTHER'}
  ];
  const filterDestinationOptions = [
    {value:'ALL',label:'Semua'},{value:'REGULER',label:'Reguler'},{value:'EVENT',label:'Event'},
    {value:'BAR',label:'BAR'},{value:'KITCHEN',label:'KITCHEN'},{value:'ROASTERY',label:'ROASTERY'},
    {value:'BAR_EVENT',label:'BAR_EVENT'},{value:'KITCHEN_EVENT',label:'KITCHEN_EVENT'},{value:'ROASTERY_EVENT',label:'ROASTERY_EVENT'},
    {value:'OFFICE',label:'OFFICE'},{value:'OTHER',label:'OTHER'}
  ];

  const fmt   = (num) => Number(num||0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:2});
  const fmt6  = (num) => Number(num||0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:6});
  const fmtMoney = (num) => 'Rp ' + Number(num||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
  const reasonLabel = (category, value) => ((reasonLabelMap?.[category]||{})[value] || value || '-');
  const contentPerBuyValue = (row) => { const v = Number(row?.profile_content_per_buy || row?.default_content_per_buy || 1); return v > 0 ? v : 1; };
  const qtyByScope    = (row, contentQty) => isWarehouseScope ? (Number(contentQty||0) / contentPerBuyValue(row)) : Number(contentQty||0);
  const qtyUnitByScope = (row) => isWarehouseScope ? (row?.profile_buy_uom_code || row?.default_buy_uom_code || '') : (row?.profile_content_uom_code || row?.default_content_uom_code || '');
  const costByScope   = (row, costPerContent) => isWarehouseScope ? (Number(costPerContent||0) * contentPerBuyValue(row)) : Number(costPerContent||0);
  const costLabelByScope = () => isWarehouseScope ? 'Harga Satuan / Beli' : 'Avg Cost/Isi';
  const catalogUnitPrice = (row) => {
    const suggestedUnitPrice = Number(row?.suggested_unit_price || 0);
    const lastUnitPrice = Number(row?.last_unit_price || 0);
    const standardPrice = Number(row?.standard_price || 0);
    if (suggestedUnitPrice > 0) return suggestedUnitPrice;
    if (lastUnitPrice > 0) return lastUnitPrice;
    if (standardPrice > 0) return standardPrice;
    return 0;
  };
  const effectiveCostPerContent = (row) => {
    const liveCost = Number(row?.live_avg_cost_per_content ?? row?.avg_cost_per_content ?? 0);
    if (liveCost > 0) return liveCost;
    const suggestedCost = Number(row?.suggested_avg_cost_per_content || 0);
    if (suggestedCost > 0) return suggestedCost;
    const catalogPrice = catalogUnitPrice(row);
    return catalogPrice > 0 ? (catalogPrice / contentPerBuyValue(row)) : 0;
  };
  const costSourceLabel = (row) => {
    const source = String(row?.effective_cost_source || '').toUpperCase();
    if (source === 'LIVE_STOCK') return 'Avg cost stok aktif';
    if (source === 'CATALOG_LAST_PURCHASE') return 'Harga beli terakhir katalog';
    if (source === 'CATALOG_STANDARD') return 'Harga standar katalog';
    if (catalogUnitPrice(row) > 0) return 'Harga katalog';
    return 'Harga belum tersedia';
  };
  const selectedDefaultUnitCost = (row) => {
    if (!row) return 0;
    if (isWarehouseScope) {
      const catalogPrice = catalogUnitPrice(row);
      if (catalogPrice > 0) return catalogPrice;
    }
    return costByScope(row, effectiveCostPerContent(row));
  };
  const formatUpdatedAt = (value) => {
    const raw = String(value||'').trim();
    if (!raw) return 'Belum ada update saldo';
    const date = new Date(raw.replace(' ','T'));
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat('id-ID',{year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}).format(date);
  };
  const escHtml = (v) => String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const objectIdentityKey = (row) => [Number(row?.id||0),Number(row?.material_id||0),String(row?.stock_domain||(Number(row?.material_id||0)>0?'MATERIAL':'ITEM')).toUpperCase()].join('|');
  const profileOptionKey  = (row) => [String(row?.profile_key||''),Number(row?.default_buy_uom_id||0),Number(row?.default_content_uom_id||0),Number(contentPerBuyValue(row)||1).toFixed(6),String(row?.profile_name||'').trim().toUpperCase(),String(row?.profile_brand||'').trim().toUpperCase(),String(row?.profile_description||'').trim().toUpperCase()].join('|');
  const sameAdjustmentObject = (l,r) => objectIdentityKey(l) === objectIdentityKey(r);
  const hasPositiveProfileStock = (row) => Number(row?.available_qty_content||0) > 0 || Number(row?.available_qty_buy||0) > 0;
  const buildProfileOptions = (rows, baseItem) => {
    const filtered = Array.isArray(rows) ? rows.filter(row => sameAdjustmentObject(row,baseItem)) : [];
    const seen = new Set(); const options = [];
    filtered.forEach(row => { const key = profileOptionKey(row); if (seen.has(key)) return; seen.add(key); options.push(row); });
    const stocked = options.filter(row => hasPositiveProfileStock(row));
    // Keep the active stock first, then show genuinely different catalog
    // profiles as fallback for an adjustment plus/new stock profile.
    if (stocked.length > 0) return stocked.concat(options.filter(row => !hasPositiveProfileStock(row)));
    if (!options.length && baseItem) options.push(baseItem);
    return options;
  };
  const objectLabel  = (row) => String(row?.material_id ? (row?.material_name||row?.item_name||'-') : (row?.item_name||row?.material_name||'-') || '-').trim();
  const profileLabel = (row) => [row?.profile_name||'Tanpa profile',row?.profile_brand||''].filter(Boolean).join(' | ');
  const sourceBadgeLabel = (row) => {
    const t = String(row?.source_type||'').toUpperCase();
    if (t==='PROFILE_DIVISION_STOCK') return 'Stok Divisi';
    if (t==='PROFILE_STOCK') return 'Stok';
    if (t==='PROFILE_CATALOG') return 'Katalog';
    return 'Profil';
  };
  const dedupeProfileOptions = (rows) => {
    const seen = new Set(); const d = [];
    (Array.isArray(rows)?rows:[]).forEach(row => { const key = profileOptionKey(row); if (seen.has(key)) return; seen.add(key); d.push(row); });
    return d;
  };
  const syncSelectedProfile = (preferredKey='') => {
    if (!selectedProfileOptions.length) { selectedProfileOptions = selectedItem ? [selectedItem] : []; }
    if (!selectedProfileOptions.length) { selectedItem = null; return; }
    const targetKey = preferredKey || profileOptionKey(selectedItem || selectedProfileOptions[0]);
    selectedItem = selectedProfileOptions.find(o => profileOptionKey(o) === targetKey) || selectedProfileOptions[0];
  };
  const resolveProfileOptions = async (baseItem) => {
    const baseOptions = buildProfileOptions(currentSearchItems, baseItem);
    if (!isDivisionScope || !baseItem) return baseOptions;
    const searchToken = String(baseItem.item_code || baseItem.material_code || baseItem.item_name || baseItem.material_name || '').trim();
    if (searchToken.length < 1) return baseOptions;
    const params = new URLSearchParams({q:searchToken,limit:'50',stock_scope:stockScope});
    params.set('division_id', String(document.getElementById('division_id')?.value || ''));
    params.set('destination', String(document.getElementById('destination_type')?.value || ''));
    try {
      const res = await fetchJson('<?php echo $searchUrl; ?>?' + params.toString());
      if (!res.ok) return baseOptions;
      return buildProfileOptions([].concat(baseOptions, Array.isArray(res.items)?res.items:[]), baseItem);
    } catch (e) { return baseOptions; }
  };
  const getProfileModalInstance = () => (!profileModalEl || !(window.bootstrap?.Modal)) ? null : window.bootstrap.Modal.getOrCreateInstance(profileModalEl);
  const closeProfilePicker = () => { const m = getProfileModalInstance(); if (m) m.hide(); };
  const applySelectedProfileOption = (index) => {
    if (index < 0 || index >= profilePickerOptions.length) return;
    selectedProfileOptions = profilePickerOptions.slice();
    selectedItem = selectedProfileOptions[index];
    syncSelectedProfile(profileOptionKey(selectedItem));
    renderSelectedItem(); clearLineInputs(); closeProfilePicker();
  };
  const renderProfilePicker = () => {
    if (!profileModalOptionsEl) return;
    if (!profilePickerBaseItem || !profilePickerOptions.length) { profileModalOptionsEl.innerHTML = '<div class="text-muted small">Belum ada profil.</div>'; return; }
    profileModalTitleEl.textContent = objectLabel(profilePickerBaseItem);
    profileModalMetaEl.textContent  = 'Profil dengan stok aktif ditampilkan lebih dulu. Profil katalog lain tetap tersedia untuk adjustment tambah bila stok baru ditemukan.';
    profileModalOptionsEl.innerHTML = profilePickerOptions.map((option, index) => {
      const availPrimary = fmt(isWarehouseScope ? option.available_qty_buy : option.available_qty_content) + ' ' + (qtyUnitByScope(option)||'');
      const secondaryQty = isWarehouseScope ? (fmt(option.available_qty_content) + ' ' + (option.default_content_uom_code||'')) : (fmt(option.available_qty_buy) + ' ' + (option.default_buy_uom_code||''));
      const hasActiveStock = hasPositiveProfileStock(option);
      const description = [option.profile_description||'',option.profile_expired_date?('Exp '+option.profile_expired_date):'',!hasActiveStock?'Belum ada saldo aktif.':''].filter(Boolean).join(' | ');
      return '<button type="button" class="adjustment-profile-choice-card" data-profile-choice-index="'+index+'">'
        +'<div class="adjustment-profile-choice-head"><div class="adjustment-profile-choice-title">'+escHtml(profileLabel(option))+'</div><span class="adjustment-profile-choice-key">'+escHtml(sourceBadgeLabel(option))+'</span></div>'
        +'<div class="adjustment-profile-choice-desc">'+escHtml(description||'Tidak ada deskripsi.')+'</div>'
        +'<div class="adjustment-profile-choice-meta">Update: '+escHtml(formatUpdatedAt(option.updated_at||''))+'</div>'
        +'<div class="adjustment-profile-choice-grid">'
        +'<div class="adjustment-profile-choice-metric"><span class="label">Avail</span><strong>'+escHtml(availPrimary)+'</strong></div>'
        +'<div class="adjustment-profile-choice-metric"><span class="label">Setara</span><strong>'+escHtml(secondaryQty)+'</strong></div>'
        +'<div class="adjustment-profile-choice-metric"><span class="label">'+escHtml(isWarehouseScope ? 'Harga Katalog' : costSourceLabel(option))+'</span><strong>'+escHtml(isWarehouseScope ? fmtMoney(selectedDefaultUnitCost(option)) : fmt6(effectiveCostPerContent(option)))+'</strong></div>'
        +'</div></button>';
    }).join('');
    profileModalOptionsEl.querySelectorAll('[data-profile-choice-index]').forEach(btn => {
      btn.addEventListener('click', () => applySelectedProfileOption(Number(btn.getAttribute('data-profile-choice-index')||-1)));
    });
  };
  const openProfilePicker = (baseItem, options) => {
    profilePickerBaseItem = baseItem || null;
    profilePickerOptions  = dedupeProfileOptions(Array.isArray(options) ? options.slice() : []);
    if (!profilePickerBaseItem || !profilePickerOptions.length) { showAlert('warning','Profil item tidak ditemukan.'); return; }
    const m = getProfileModalInstance();
    if (!m) { applySelectedProfileOption(0); return; }
    renderProfilePicker(); m.show();
  };

  const syncDivisionDestinationOptions = (divisionEl, destinationEl, options, preserveGroups) => {
    if (!divisionEl || !destinationEl) return;
    const divisionId = parseInt(divisionEl.value||'0',10);
    const current = String(destinationEl.value||'').toUpperCase();
    let filtered = options.slice();
    if (Number.isFinite(divisionId) && divisionId > 0 && destinationGuardMap[String(divisionId)]) {
      const allowed = (destinationGuardMap[String(divisionId)]||[]).map(v => String(v||'').toUpperCase());
      filtered = options.filter(opt => {
        if (preserveGroups && (opt.value==='ALL'||opt.value==='REGULER'||opt.value==='EVENT')) return true;
        return allowed.indexOf(opt.value) !== -1;
      });
    }
    destinationEl.innerHTML = filtered.map(opt => '<option value="'+escHtml(opt.value)+'">'+escHtml(opt.label)+'</option>').join('');
    if (!filtered.length) { destinationEl.value=''; return; }
    destinationEl.value = filtered.some(o => o.value===current) ? current : (preserveGroups?'ALL':filtered[0].value);
  };

  const showAlert = (type, message) => {
    if (alertArea) alertArea.innerHTML = '<div class="alert alert-'+type+' alert-dismissible fade show" role="alert">'+message+'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  };
  const showFormAlert = (type, message) => {
    if (formAlertArea) formAlertArea.innerHTML = '<div class="alert alert-'+type+' alert-dismissible fade show" role="alert">'+message+'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  };

  const askConfirmation = ({kicker='Konfirmasi',title='Konfirmasi aksi',message='',note='',confirmLabel='Lanjutkan',confirmClass='btn-primary'}) => {
    if (!confirmModalEl||!confirmKickerEl||!confirmTitleEl||!confirmMessageEl||!confirmNoteEl||!confirmSubmitBtn) { showAlert('danger','Komponen konfirmasi tidak tersedia.'); return Promise.resolve(false); }
    if (confirmResolver) { confirmResolver(false); confirmResolver = null; }
    confirmKickerEl.textContent = kicker; confirmTitleEl.textContent = title; confirmMessageEl.textContent = message;
    confirmNoteEl.textContent = note; confirmSubmitBtn.textContent = confirmLabel; confirmSubmitBtn.className = 'btn px-4 '+confirmClass;
    if (confirmModal) { confirmModal.show(); } else {
      confirmModalEl.style.display='block'; confirmModalEl.removeAttribute('aria-hidden'); confirmModalEl.setAttribute('aria-modal','true'); confirmModalEl.classList.add('show');
      document.body.classList.add('modal-open');
      if (!confirmBackdropEl) { confirmBackdropEl=document.createElement('div'); confirmBackdropEl.className='modal-backdrop fade show'; document.body.appendChild(confirmBackdropEl); }
    }
    return new Promise(resolve => { confirmResolver = resolve; });
  };
  const closeConfirmation = (accepted) => {
    if (confirmResolver) { const r=confirmResolver; confirmResolver=null; r(accepted); }
    if (confirmModal) { confirmModal.hide(); return; }
    if (confirmModalEl) { confirmModalEl.classList.remove('show'); confirmModalEl.setAttribute('aria-hidden','true'); confirmModalEl.removeAttribute('aria-modal'); confirmModalEl.style.display='none'; }
    document.body.classList.remove('modal-open');
    if (confirmBackdropEl) { confirmBackdropEl.remove(); confirmBackdropEl=null; }
  };
  confirmSubmitBtn?.addEventListener('click', () => closeConfirmation(true));
  confirmModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => btn.addEventListener('click', () => closeConfirmation(false)));
  confirmModalEl?.addEventListener('hidden.bs.modal', () => { if (confirmResolver) { const r=confirmResolver; confirmResolver=null; r(false); } });
  confirmModalEl?.addEventListener('click', e => { if (!confirmModal && e.target===confirmModalEl) closeConfirmation(false); });
  document.addEventListener('keydown', e => { if (!confirmModal && confirmResolver && e.key==='Escape') closeConfirmation(false); });

  if (isDivisionScope) {
    formDivisionEl?.addEventListener('change', () => {
      syncDivisionDestinationOptions(formDivisionEl, formDestinationEl, formDestinationOptions, false);
      resetStockAdjustmentSelection();
    });
    formDestinationEl?.addEventListener('change', resetStockAdjustmentSelection);
    syncDivisionDestinationOptions(formDivisionEl, formDestinationEl, formDestinationOptions, false);
    [[docFilterDivisionEl,docFilterDestinationEl],[lineFilterDivisionEl,lineFilterDestinationEl]].forEach(([dEl,destEl]) => {
      if (!dEl||!destEl) return;
      dEl.addEventListener('change', () => syncDivisionDestinationOptions(dEl, destEl, filterDestinationOptions, true));
      syncDivisionDestinationOptions(dEl, destEl, filterDestinationOptions, true);
    });
  }

  const adjustmentKindEl         = document.getElementById('adjustment_kind');
  const adjustmentKindPanelEl    = document.getElementById('adjustment-kind-panel');
  const adjustmentQtyLabelEl     = document.getElementById('adjustment_qty_label');
  const adjustmentQtyInputEl     = document.getElementById('adjustment_qty_input');
  const adjustmentQtyHintEl      = document.getElementById('adjustment_qty_hint');
  const adjustmentReasonLabelEl  = document.getElementById('adjustment_reason_label');
  const adjustmentReasonInputEl  = document.getElementById('adjustment_reason_input');
  const adjustmentPlusCostWrapEl = document.getElementById('adjustment-plus-cost-wrap');
  const adjustmentPlusLotWrapEl  = document.getElementById('adjustment-plus-lot-wrap');
  const adjustmentPlusExpWrapEl  = document.getElementById('adjustment-plus-exp-wrap');
  const unitCostInputEl          = document.getElementById('unit_cost');
  const unitCostHintEl           = document.getElementById('unit_cost_hint');
  const inboundLotInputEl        = document.getElementById('inbound_lot_no');
  const inboundExpiryInputEl     = document.getElementById('inbound_expiry_date');
  const adjustmentKindConfig = {
    WASTE:        {qtyField:'qty_waste_content',            reasonField:'waste_reason_code',            qtyLabel:'Waste',        reasonLabel:'Alasan Waste',        reasonCategory:'WASTE'},
    SPOIL:        {qtyField:'qty_spoil_content',            reasonField:'spoil_reason_code',            qtyLabel:'Spoil',        reasonLabel:'Alasan Spoil',        reasonCategory:'SPOILAGE'},
    PROCESS_LOSS: {qtyField:'qty_process_loss_content',     reasonField:'process_loss_reason_code',     qtyLabel:'Process Loss', reasonLabel:'Alasan Process Loss', reasonCategory:'PROCESS_LOSS'},
    MINUS:        {qtyField:'qty_variance_content',         reasonField:'variance_reason_code',         qtyLabel:'Variance',     reasonLabel:'Alasan Variance',     reasonCategory:'VARIANCE'},
    PLUS:         {qtyField:'qty_adjustment_plus_content',  reasonField:'adjustment_plus_reason_code',  qtyLabel:'Adj Plus',     reasonLabel:'Alasan Plus',         reasonCategory:'ADJUSTMENT_PLUS', needsInbound:true},
  };
  const adjustmentKindDirectionHint = (kind) => kind === 'PLUS'
    ? 'Adj + akan menambah stok dan membutuhkan harga per isi untuk lot masuk.'
    : 'Jenis ini akan mengurangi stok sesuai jumlah yang Anda isi.';
  const renderUnitCostHint = () => {
    if (!unitCostHintEl) return;
    const kind = String(adjustmentKindEl?.value || '').toUpperCase();
    if (kind !== 'PLUS') {
      unitCostHintEl.textContent = isDivisionScope
        ? 'Untuk stok divisi, isi harga per isi, bukan per pack.'
        : 'Isi harga per satuan beli.';
      return;
    }
    if (!selectedItem) {
      unitCostHintEl.textContent = isDivisionScope
        ? 'Untuk stok divisi, isi harga per isi, bukan per pack.'
        : 'Isi harga per satuan beli.';
      return;
    }
    const contentUom = selectedItem?.profile_content_uom_code || selectedItem?.default_content_uom_code || 'isi';
    const buyUom = selectedItem?.profile_buy_uom_code || selectedItem?.default_buy_uom_code || 'pack';
    const contentPerBuy = contentPerBuyValue(selectedItem);
    const avgCostPerContent = effectiveCostPerContent(selectedItem);
    if (!isDivisionScope) {
      const defaultUnitCost = selectedDefaultUnitCost(selectedItem);
      unitCostHintEl.textContent = defaultUnitCost > 0
        ? 'Harga default mengikuti katalog terpilih: ' + fmtMoney(defaultUnitCost) + ' / ' + buyUom + '. Jika diubah, sistem akan membuat profil katalog harga baru.'
        : 'Isi harga per ' + buyUom + '. Jika diubah ke harga baru, sistem akan membuat profil katalog harga baru.';
      return;
    }
    if (avgCostPerContent > 0 && contentPerBuy > 1) {
      unitCostHintEl.textContent = 'Input harga per ' + contentUom + '. Default mengikuti ' + costSourceLabel(selectedItem).toLowerCase() + ': '
        + fmt6(avgCostPerContent) + ' / ' + contentUom + ', setara '
        + fmtMoney(avgCostPerContent * contentPerBuy) + ' / ' + buyUom + '.';
      return;
    }
    unitCostHintEl.textContent = avgCostPerContent > 0
      ? 'Default mengikuti ' + costSourceLabel(selectedItem).toLowerCase() + ': ' + fmt6(avgCostPerContent) + ' / ' + contentUom + '.'
      : 'Harga katalog belum tersedia. Isi harga per ' + contentUom + ', bukan per ' + buyUom + '.';
  };
  const detectDivisionPackCostInput = (row, rawCost) => {
    if (!isDivisionScope) return '';
    const refCost = effectiveCostPerContent(row);
    const contentPerBuy = contentPerBuyValue(row);
    if (rawCost <= 0 || refCost <= 0 || contentPerBuy <= 1) return '';
    const normalizedCost = rawCost / contentPerBuy;
    const rawRatio = rawCost / refCost;
    const normalizedRatio = normalizedCost / refCost;
    if (rawRatio >= 50 && normalizedRatio >= 0.25 && normalizedRatio <= 4) {
      const contentUom = row?.profile_content_uom_code || row?.default_content_uom_code || 'isi';
      const buyUom = row?.profile_buy_uom_code || row?.default_buy_uom_code || 'pack';
      return 'Unit cost tampak masih harga per ' + buyUom + '. Untuk divisi, isi harga per ' + contentUom
        + '. Perkiraan input yang benar sekitar ' + fmt6(normalizedCost) + ' / ' + contentUom + '.';
    }
    return '';
  };
  const renderAdjustmentKindForm = () => {
    const kind = String(adjustmentKindEl?.value||'').toUpperCase();
    const config = adjustmentKindConfig[kind] || null;
    if (!adjustmentKindPanelEl||!adjustmentQtyInputEl||!adjustmentReasonInputEl||!adjustmentQtyLabelEl||!adjustmentReasonLabelEl) return;
    if (!config) {
      adjustmentKindPanelEl.classList.add('d-none'); adjustmentQtyInputEl.value='0'; adjustmentQtyInputEl.disabled=true;
      adjustmentQtyLabelEl.textContent='Penyesuaian (<?php echo html_escape($qtyUnitLabel); ?>)';
      if (adjustmentQtyHintEl) adjustmentQtyHintEl.textContent='Pilih keterangan penyesuaian terlebih dahulu.';
      adjustmentReasonInputEl.innerHTML='<option value="other">Other</option>'; adjustmentReasonInputEl.value='other';
      adjustmentPlusCostWrapEl?.classList.add('d-none'); adjustmentPlusLotWrapEl?.classList.add('d-none'); adjustmentPlusExpWrapEl?.classList.add('d-none');
      renderUnitCostHint();
      return;
    }
    adjustmentKindPanelEl.classList.remove('d-none');
    adjustmentQtyInputEl.disabled=false;
    adjustmentQtyLabelEl.textContent = 'Penyesuaian (<?php echo html_escape($qtyUnitLabel); ?>)';
    if (adjustmentQtyHintEl) adjustmentQtyHintEl.textContent = adjustmentKindDirectionHint(kind);
    adjustmentReasonLabelEl.textContent = config.reasonLabel;
    const reasonCategory = config.reasonCategory || kind;
    const reasonOptions = reasonLabelMap[reasonCategory] || {other:'Other'};
    adjustmentReasonInputEl.innerHTML = Object.entries(reasonOptions).map(([v,l]) => '<option value="'+escHtml(v)+'">'+escHtml(l)+'</option>').join('');
    adjustmentReasonInputEl.value = 'other'; adjustmentQtyInputEl.value = '0';
    const needsInbound = !!config.needsInbound;
    adjustmentPlusCostWrapEl?.classList.toggle('d-none',!needsInbound);
    adjustmentPlusLotWrapEl?.classList.toggle('d-none',!needsInbound);
    adjustmentPlusExpWrapEl?.classList.toggle('d-none',!needsInbound);
    if (unitCostInputEl) unitCostInputEl.value = selectedItem ? String(selectedDefaultUnitCost(selectedItem)) : '0';
    if (!needsInbound) { if (inboundLotInputEl) inboundLotInputEl.value=''; if (inboundExpiryInputEl) inboundExpiryInputEl.value=''; }
    renderUnitCostHint();
  };
  const clearLineInputs = () => {
    if (adjustmentKindEl) adjustmentKindEl.value = '';
    renderAdjustmentKindForm();
    const noteEl = document.getElementById('line_note'); if (noteEl) noteEl.value='';
    if (inboundLotInputEl) inboundLotInputEl.value='';
    if (inboundExpiryInputEl) inboundExpiryInputEl.value='';
  };
  const renderSelectedItem = () => {
    if (!selectedCard) return;
    if (!selectedItem) { selectedCard.innerHTML='<div class="fw-semibold mb-1">Belum ada profile dipilih.</div><div class="text-muted small">Pilih item/profile dari hasil pencarian.</div>'; return; }
    syncSelectedProfile();
    selectedCard.innerHTML = '<div class="fw-semibold">'+escHtml(objectLabel(selectedItem))+'</div>'
      +'<div class="small text-muted mb-2">'+escHtml(profileLabel(selectedItem))+'</div>'
      +(isDivisionScope ? '<div class="mb-3"><label class="form-label mb-1">Profile Yang Di-adjust</label><div class="small text-muted">'+escHtml(selectedItem.profile_key||'Tanpa profile_key')+'</div>'
        +(selectedProfileOptions.length>1 ? '<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="change-profile">Pilih Ulang Profil</button></div>' : '')
        +'</div>' : '')
      +'<div class="row g-2 small">'
      +'<div class="col-md-4"><div class="text-muted">Avail '+(isWarehouseScope?'Pack':'Isi')+'</div><div class="fw-semibold">'+fmt(isWarehouseScope?selectedItem.available_qty_buy:selectedItem.available_qty_content)+' '+qtyUnitByScope(selectedItem)+'</div></div>'
      +'<div class="col-md-4"><div class="text-muted">'+(isWarehouseScope?'Setara Isi':'Setara Pack')+'</div><div class="fw-semibold">'+fmt(isWarehouseScope?selectedItem.available_qty_content:selectedItem.available_qty_buy)+' '+(isWarehouseScope?(selectedItem.default_content_uom_code||''):(selectedItem.default_buy_uom_code||''))+'</div></div>'
      +'<div class="col-md-4"><div class="text-muted">'+(isWarehouseScope?'Harga Katalog':costSourceLabel(selectedItem))+'</div><div class="fw-semibold">'+(isWarehouseScope?fmtMoney(selectedDefaultUnitCost(selectedItem)):fmt6(effectiveCostPerContent(selectedItem)))+'</div></div>'
      +'</div>';
    renderAdjustmentKindForm();
  };
  const draftAdjustmentParts = (line) => [
    {label:'Waste',    category:'WASTE',              reason:line.waste_reason_code,            qty:Number(line.qty_waste_content || 0),           isPlus:false},
    {label:'Spoil',    category:'SPOILAGE',           reason:line.spoil_reason_code,            qty:Number(line.qty_spoil_content || 0),           isPlus:false},
    {label:'P.Loss',   category:'PROCESS_LOSS',       reason:line.process_loss_reason_code,     qty:Number(line.qty_process_loss_content || 0),    isPlus:false},
    {label:'Variance', category:'VARIANCE',           reason:line.variance_reason_code,         qty:Number(line.qty_variance_content || 0),        isPlus:false},
    {label:'Adj +',    category:'ADJUSTMENT_PLUS',    reason:line.adjustment_plus_reason_code,  qty:Number(line.qty_adjustment_plus_content || 0), isPlus:true},
  ].filter(part => part.qty > 0);
  const renderDraftLines = () => {
    if (isDivisionScope) {
      [formDivisionEl, formDestinationEl].forEach(field => {
        if (!field) return;
        field.disabled = lines.length > 0;
        field.title = lines.length ? 'Hapus rincian draft dahulu sebelum mengganti divisi atau tujuan.' : '';
      });
    }
    const lc = document.getElementById('draft-summary-line-count');
    const qt = document.getElementById('draft-summary-qty-total');
    const sv = document.getElementById('draft-summary-shrink-value');
    const nv = document.getElementById('draft-summary-net-value');
    let draftQtyTotal=0, draftShrinkValue=0, draftAdditionValue=0;
    if (!lines.length) {
      if (lc) lc.textContent='0'; if (qt) qt.textContent='0';
      if (sv) sv.textContent=fmtMoney(0); if (nv) nv.textContent=fmtMoney(0);
      if (draftTableBody) draftTableBody.innerHTML='<tr><td colspan="'+draftTableColspan+'" class="text-center text-muted py-3">Belum ada line draft.</td></tr>';
      return;
    }
    if (draftTableBody) draftTableBody.innerHTML = lines.map((line,index) => {
      const objText = line.material_id ? ((line.material_code||line.item_code||'-')+' - '+(line.material_name||line.item_name||'-')) : ((line.item_code||line.material_code||'-')+' - '+(line.item_name||line.material_name||'-'));
      const profileText = [line.profile_name,line.profile_brand].filter(Boolean).join(' | ');
      const shrinkQty = Number(line.qty_waste_content||0)+Number(line.qty_spoil_content||0)+Number(line.qty_process_loss_content||0)+Number(line.qty_variance_content||0);
      const addQty = Number(line.qty_adjustment_plus_content||0);
      const lineVal = (shrinkQty+addQty)*Number(line.unit_cost||0);
      draftQtyTotal += qtyByScope(line,shrinkQty+addQty);
      draftShrinkValue += shrinkQty*Number(line.unit_cost||0);
      draftAdditionValue += addQty*Number(line.unit_cost||0);
      const adjustmentParts = draftAdjustmentParts(line);
      const rParts = adjustmentParts.map(part => part.label+': '+reasonLabel(part.category,part.reason));
      const adjustmentCells = isDivisionScope
        ? '<td class="text-end small fw-semibold">'
          +(shrinkQty > 0 ? '<div class="text-danger">-'+fmt(qtyByScope(line,shrinkQty))+'</div>' : '')
          +(addQty > 0 ? '<div class="text-success">+'+fmt(qtyByScope(line,addQty))+'</div>' : '')
          +(shrinkQty <= 0 && addQty <= 0 ? '<span class="text-muted">-</span>' : '')
          +'</td>'
          +'<td class="small adj-adjustment-description">'
          +(adjustmentParts.length ? adjustmentParts.map(part => '<span class="adj-adjustment-kind '+(part.isPlus?'is-plus':'is-minus')+'">'
            +escHtml(part.label)+' <strong>'+(part.isPlus?'+':'-')+fmt(qtyByScope(line,part.qty))+'</strong>'
            +'<span class="fw-normal">'+escHtml(reasonLabel(part.category,part.reason))+'</span></span>').join('') : '<span class="text-muted">-</span>')
          +'</td>'
        : '<td class="text-end small">'+fmt(qtyByScope(line,line.qty_waste_content))+'</td>'
          +'<td class="text-end small">'+fmt(qtyByScope(line,line.qty_spoil_content))+'</td>'
          +'<td class="text-end small">'+fmt(qtyByScope(line,line.qty_process_loss_content))+'</td>'
          +'<td class="text-end small">'+fmt(qtyByScope(line,line.qty_variance_content))+'</td>'
          +'<td class="text-end small">'+fmt(qtyByScope(line,line.qty_adjustment_plus_content))+'</td>';
      return '<tr>'
        +'<td><div class="fw-semibold small">'+objText+'</div><small class="text-muted d-block">'+(profileText||'-')+'</small>'+(isDivisionScope?'':'<small class="text-muted">'+(rParts.join(' | ')||'-')+'</small>')+'</td>'
        +'<td class="text-end small">'+fmt(isWarehouseScope?line.available_qty_buy:line.available_qty_content)+' '+qtyUnitByScope(line)+'</td>'
        +adjustmentCells
        +'<td class="text-end small">'+fmt6(costByScope(line,line.unit_cost))+'</td>'
        +'<td class="text-end small fw-semibold">'+fmtMoney(lineVal)+'</td>'
        +'<td class="small"><div>'+(line.inbound_lot_no||'-')+'</div><small class="text-muted">'+(line.inbound_expiry_date||'')+'</small></td>'
        +'<td><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn btn-remove-line" data-index="'+index+'" title="Hapus"><i class="ri ri-delete-bin-line"></i></button></td>'
        +'</tr>';
    }).join('');
    if (lc) lc.textContent=String(lines.length); if (qt) qt.textContent=fmt(draftQtyTotal);
    if (sv) sv.textContent=fmtMoney(draftShrinkValue); if (nv) nv.textContent=fmtMoney(draftAdditionValue-draftShrinkValue);
    draftTableBody?.querySelectorAll('.btn-remove-line').forEach(btn => {
      btn.addEventListener('click', () => { const idx=Number(btn.dataset.index||-1); if (idx>=0) { lines.splice(idx,1); renderDraftLines(); } });
    });
  };

  const fetchJson = async (url, options={}) => {
    const res = await fetch(url, Object.assign({headers:{'X-Requested-With':'XMLHttpRequest'}},options));
    return res.json();
  };

  const postStockAdjustmentJson = async (url, payload) => fetchJson(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Stock-Adjustment-Csrf': stockAdjustmentCsrfToken
    },
    body: JSON.stringify(payload)
  });
  const stepUpModal = () => (!stockAdjustmentStepUpModalEl || !(window.bootstrap?.Modal))
    ? null
    : window.bootstrap.Modal.getOrCreateInstance(stockAdjustmentStepUpModalEl);
  const clearStockAdjustmentButtonLoading = (button) => {
    if (!button) return;
    if (window.FinanceUI?.clearButtonLoading) { window.FinanceUI.clearButtonLoading(button); return; }
    button.disabled = false;
  };
  const setStockAdjustmentButtonLoading = (button, label) => {
    if (!button) return;
    if (window.FinanceUI?.setButtonLoading) { window.FinanceUI.setButtonLoading(button, label); return; }
    button.disabled = true;
  };
  const openStockAdjustmentStepUp = (action) => {
    const modal = stepUpModal();
    if (!modal || !action || !(Number(action.id) > 0)) {
      showAlert('danger', 'Verifikasi ulang belum siap. Muat ulang halaman lalu coba kembali.');
      return;
    }
    pendingStockAdjustmentAction = action;
    if (stockAdjustmentStepUpKickerEl) stockAdjustmentStepUpKickerEl.textContent = action.operation === 'VOID' ? 'Verifikasi VOID' : 'Verifikasi Posting';
    if (stockAdjustmentStepUpTitleEl) stockAdjustmentStepUpTitleEl.textContent = action.operation === 'VOID' ? 'Verifikasi VOID Adjustment Stok' : 'Verifikasi Post Adjustment Stok';
    if (stockAdjustmentStepUpMessageEl) stockAdjustmentStepUpMessageEl.textContent = action.operation === 'VOID'
      ? 'Masukkan password untuk membalik lot FIFO, stok, daily rollup, dan defisit dari dokumen ini.'
      : 'Masukkan password untuk menulis perubahan ke stok live, daily rollup, dan lot FIFO.';
    if (stockAdjustmentStepUpSubmitBtn) stockAdjustmentStepUpSubmitBtn.textContent = action.operation === 'VOID' ? 'Verifikasi & VOID' : 'Verifikasi & Post';
    if (stockAdjustmentStepUpPasswordEl) stockAdjustmentStepUpPasswordEl.value = '';
    modal.show();
    window.setTimeout(() => stockAdjustmentStepUpPasswordEl?.focus(), 150);
  };

  function resetStockAdjustmentSelection() {
    profileLookupToken++;
    searchLookupToken++;
    clearTimeout(searchTimer);
    selectedItem = null;
    selectedProfileOptions = [];
    currentSearchItems = [];
    profilePickerBaseItem = null;
    profilePickerOptions = [];
    closeProfilePicker();
    renderSelectedItem();
    clearLineInputs();
    if (searchResults) searchResults.innerHTML = '';
    if (searchInput) searchInput.value = '';
  }

  const performSearch = async () => {
    const lookupId = ++searchLookupToken;
    const q = String(searchInput?.value||'').trim();
    if (q.length < 2) { currentSearchItems=[]; if (searchResults) searchResults.innerHTML=''; return; }
    const params = new URLSearchParams({q,limit:'12',stock_scope:stockScope});
    if (isDivisionScope) {
      params.set('division_id', String(document.getElementById('division_id')?.value||''));
      params.set('destination',  String(document.getElementById('destination_type')?.value||''));
    }
    const res = await fetchJson('<?php echo $searchUrl; ?>?' + params.toString());
    if (lookupId !== searchLookupToken || q !== String(searchInput?.value || '').trim()) return;
    if (!res.ok) { currentSearchItems=[]; if (searchResults) searchResults.innerHTML='<div class="list-group-item text-danger">Pencarian gagal.</div>'; return; }
    const items = Array.isArray(res.items) ? res.items : [];
    currentSearchItems = items;
    if (!items.length) { if (searchResults) searchResults.innerHTML='<div class="list-group-item text-muted">Tidak ada hasil.</div>'; return; }
    if (searchResults) searchResults.innerHTML = items.map((item,index) => {
      const profileText = [item.profile_name,item.profile_brand,item.profile_description].filter(Boolean).join(' | ');
      const resultCatalogPrice = catalogUnitPrice(item);
      const priceLine = isWarehouseScope
        ? (resultCatalogPrice > 0
          ? 'Harga katalog: ' + fmtMoney(resultCatalogPrice) + ' / ' + escHtml(item.profile_buy_uom_code || item.default_buy_uom_code || '')
          : 'Harga katalog belum ada')
        : (costSourceLabel(item) + ': ' + (effectiveCostPerContent(item) > 0 ? fmt6(effectiveCostPerContent(item)) + ' / ' + escHtml(item.profile_content_uom_code || item.default_content_uom_code || 'isi') : 'belum ada'));
      return '<button type="button" class="list-group-item list-group-item-action adjustment-search-result" data-index="'+index+'">'
        +'<div class="d-flex justify-content-between gap-2"><div class="fw-semibold small">'+escHtml(objectLabel(item))+'</div><span class="badge bg-light text-dark border">'+escHtml(sourceBadgeLabel(item))+'</span></div>'
        +'<div class="small text-muted">'+(profileText||'Tanpa profile')+'</div>'
        +'<div class="small">Avail: '+fmt(isWarehouseScope?item.available_qty_buy:item.available_qty_content)+' '+qtyUnitByScope(item)+'</div>'
        +'<div class="small text-muted">'+priceLine+'</div>'
        +'</button>';
    }).join('');
    searchResults?.querySelectorAll('.adjustment-search-result').forEach(btn => {
      btn.addEventListener('click', async () => {
        const idx = Number(btn.dataset.index||-1);
        const clickedItem = items[idx]||null;
        if (searchResults) searchResults.innerHTML='';
        if (!clickedItem) return;
        if (!isDivisionScope) { selectedItem=clickedItem; selectedProfileOptions=[clickedItem]; renderSelectedItem(); clearLineInputs(); return; }
        const lookupId = ++profileLookupToken;
        const options = await resolveProfileOptions(clickedItem);
        if (lookupId !== profileLookupToken) return;
        openProfilePicker(clickedItem, options);
      });
    });
  };

  selectedCard?.addEventListener('click', e => {
    const trigger = e.target.closest('[data-action="change-profile"]');
    if (!trigger || !selectedItem || !selectedProfileOptions.length) return;
    openProfilePicker(selectedItem, selectedProfileOptions);
  });

  searchInput?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(performSearch, 250); });
  adjustmentKindEl?.addEventListener('change', renderAdjustmentKindForm);
  unitCostInputEl?.addEventListener('input', renderUnitCostHint);
  renderAdjustmentKindForm();

  document.getElementById('btn-add-line')?.addEventListener('click', () => {
    if (!selectedItem) { showFormAlert('warning','Pilih item/profile lebih dulu.'); return; }
    if (isDivisionScope && selectedProfileOptions.length>1 && !String(selectedItem.profile_key||'').trim()) { showFormAlert('warning','Pilih profil item yang akan di-adjust lebih dulu.'); return; }
    if (isDivisionScope) {
      const divId = Number(document.getElementById('division_id')?.value||0);
      const destType = String(document.getElementById('destination_type')?.value||'');
      if (divId<=0||destType==='') { showFormAlert('warning','Divisi dan tujuan wajib dipilih.'); return; }
    }
    const line = {
      stock_domain: selectedItem.stock_domain||(selectedItem.material_id?'MATERIAL':'ITEM'),
      item_id: Number(selectedItem.id||0)||null, material_id: Number(selectedItem.material_id||0)||null,
      item_code:selectedItem.item_code||'', item_name:selectedItem.item_name||'',
      material_code:selectedItem.material_code||'', material_name:selectedItem.material_name||'',
      buy_uom_id:Number(selectedItem.default_buy_uom_id||0)||null, content_uom_id:Number(selectedItem.default_content_uom_id||0)||null,
      profile_key:selectedItem.profile_key||'', profile_name:selectedItem.profile_name||'',
      profile_brand:selectedItem.profile_brand||'', profile_description:selectedItem.profile_description||'',
      profile_content_per_buy:Number(selectedItem.default_content_per_buy||1)||1,
      profile_buy_uom_code:selectedItem.default_buy_uom_code||'', profile_content_uom_code:selectedItem.default_content_uom_code||'',
      available_qty_buy:Number(selectedItem.available_qty_buy||0), available_qty_content:Number(selectedItem.available_qty_content||0),
      qty_waste_content:0,waste_reason_code:'other',qty_spoil_content:0,spoil_reason_code:'other',
      qty_process_loss_content:0,process_loss_reason_code:'other',qty_variance_content:0,variance_reason_code:'other',
      qty_adjustment_plus_content:0,adjustment_plus_reason_code:'other',
      unit_cost:0, inbound_lot_no:String(inboundLotInputEl?.value||'').trim(),
      inbound_expiry_date:String(inboundExpiryInputEl?.value||'').trim(),
      note:String(document.getElementById('line_note')?.value||'').trim()
    };
    const adjKind   = String(adjustmentKindEl?.value||'').toUpperCase();
    const adjConfig = adjustmentKindConfig[adjKind] || null;
    if (!adjConfig) { showFormAlert('warning','Pilih jenis adjustment lebih dulu.'); return; }
    const contentPerBuy = contentPerBuyValue(line);
    const rawQty  = Number(adjustmentQtyInputEl?.value||0);
    const rawCost = Number(unitCostInputEl?.value||0);
    const selectedReason = String(adjustmentReasonInputEl?.value||'other').trim()||'other';
    if (isWarehouseScope) { line[adjConfig.qtyField] = rawQty*contentPerBuy; line.unit_cost = rawCost>0?(rawCost/contentPerBuy):0; }
    else { line[adjConfig.qtyField] = rawQty; line.unit_cost = rawCost; }
    line[adjConfig.reasonField] = selectedReason;
    const totalQty = line.qty_waste_content+line.qty_spoil_content+line.qty_process_loss_content+line.qty_variance_content+line.qty_adjustment_plus_content;
    if (totalQty<=0) { showFormAlert('warning','Isi qty adjustment lebih dari nol.'); return; }
    if (line.qty_adjustment_plus_content>0) {
      const divisionCostWarning = detectDivisionPackCostInput(line, rawCost);
      if (divisionCostWarning) { showFormAlert('warning', divisionCostWarning); return; }
    }
    lines.push(line); renderDraftLines(); clearLineInputs();
    showFormAlert('success','Line ditambahkan ke draft.');
  });

  document.getElementById('btn-save-draft')?.addEventListener('click', async () => {
    const saveDraftBtn = document.getElementById('btn-save-draft');
    if (!lines.length) { showFormAlert('warning','Belum ada line draft untuk disimpan.'); return; }
    const payload = {
      stock_scope: stockScope,
      adjustment_date: String(document.getElementById('adjustment_date')?.value||''),
      notes: String(document.getElementById('header_notes')?.value||'').trim(),
      lines
    };
    if (payload.adjustment_date > '<?php echo date('Y-m-d'); ?>') {
      showFormAlert('warning','Tanggal adjustment tidak boleh melewati hari ini karena stok live langsung berubah saat diposting.');
      return;
    }
    if (isDivisionScope) {
      payload.division_id    = Number(document.getElementById('division_id')?.value||0)||null;
      payload.destination_type = String(document.getElementById('destination_type')?.value||'').trim();
    } else {
      payload.destination_type = 'GUDANG';
    }
    if (window.FinanceUI?.setButtonLoading) window.FinanceUI.setButtonLoading(saveDraftBtn,'Menyimpan draft...');
    try {
      const res = await postStockAdjustmentJson('<?php echo $storeUrl; ?>', payload);
      if (!res.ok) { if (window.FinanceUI?.clearButtonLoading) window.FinanceUI.clearButtonLoading(saveDraftBtn); showFormAlert('danger',res.message||'Gagal menyimpan draft adjustment.'); return; }
      lines.splice(0,lines.length); renderDraftLines();
      showAlert('success','Draft berhasil disimpan. Halaman akan dimuat ulang.');
      window.location.reload();
    } catch (err) { if (window.FinanceUI?.clearButtonLoading) window.FinanceUI.clearButtonLoading(saveDraftBtn); showFormAlert('danger',err.message||'Gagal menyimpan draft adjustment.'); }
  });

  document.querySelectorAll('.btn-post-doc').forEach(btn => {
    btn.addEventListener('click', async () => {
      const confirmed = await askConfirmation({kicker:'Post Adjustment',title:'Post dokumen ini?',message:'Stok live, stok harian, dan lot FIFO akan langsung diperbarui.',note:'Pastikan seluruh line sudah final.',confirmLabel:'Ya, post sekarang',confirmClass:'btn-success'});
      if (!confirmed) return;
      openStockAdjustmentStepUp({id:Number(btn.dataset.id||0), button:btn, operation:'POST', writerBaseUrl:'<?php echo $postBaseUrl; ?>'});
    });
  });

  document.querySelectorAll('.btn-delete-doc').forEach(btn => {
    btn.addEventListener('click', async () => {
      const confirmed = await askConfirmation({kicker:'Hapus Draft',title:'Hapus draft adjustment ini?',message:'Dokumen draft dan line yang belum diposting akan dihapus.',note:'Aksi ini membatalkan draft sebelum stok diproses.',confirmLabel:'Ya, hapus draft',confirmClass:'btn-danger'});
      if (!confirmed) return;
      if (window.FinanceUI?.setButtonLoading) window.FinanceUI.setButtonLoading(btn,'Menghapus...');
      try {
        const res = await postStockAdjustmentJson('<?php echo $deleteBaseUrl; ?>/'+btn.dataset.id, {});
        if (!res.ok) { if (window.FinanceUI?.clearButtonLoading) window.FinanceUI.clearButtonLoading(btn); showAlert('danger',res.message||'Gagal menghapus draft.'); return; }
        window.location.reload();
      } catch (err) { if (window.FinanceUI?.clearButtonLoading) window.FinanceUI.clearButtonLoading(btn); showAlert('danger',err.message||'Gagal menghapus draft.'); }
    });
  });

  document.querySelectorAll('.btn-void-doc').forEach(btn => {
    btn.addEventListener('click', async () => {
      const confirmed = await askConfirmation({kicker:'Void Adjustment',title:'Batalkan adjustment yang sudah diposting?',message:'Posting ke stok, daily, dan lot akan dibatalkan.',note:'VOID mengembalikan histori stok seperti sebelum adjustment.',confirmLabel:'Ya, void sekarang',confirmClass:'btn-danger'});
      if (!confirmed) return;
      openStockAdjustmentStepUp({id:Number(btn.dataset.id||0), button:btn, operation:'VOID', writerBaseUrl:'<?php echo $voidBaseUrl; ?>'});
    });
  });

  stockAdjustmentStepUpSubmitBtn?.addEventListener('click', async () => {
    if (stockAdjustmentStepUpSubmitting) return;
    const action = pendingStockAdjustmentAction;
    try {
      if (!action || !(Number(action.id) > 0) || !action.button) throw new Error('Dokumen adjustment tidak valid. Tutup modal lalu coba lagi.');
      const password = String(stockAdjustmentStepUpPasswordEl?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi adjustment stok.');
      if (stockAdjustmentStepUpPasswordEl) stockAdjustmentStepUpPasswordEl.value = '';
      stockAdjustmentStepUpSubmitting = true;
      stockAdjustmentStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => { button.disabled = true; });
      setStockAdjustmentButtonLoading(stockAdjustmentStepUpSubmitBtn, 'Memverifikasi...');
      const stepUp = await postStockAdjustmentJson(stockAdjustmentStepUpUrl, {
        adjustment_id: Number(action.id), operation: action.operation, password
      });
      if (!stepUp.ok || !/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) {
        throw new Error(stepUp.message || 'Bukti verifikasi ulang tidak valid. Coba lagi.');
      }
      setStockAdjustmentButtonLoading(action.button, action.operation === 'VOID' ? 'VOID...' : 'Posting...');
      const result = await postStockAdjustmentJson(action.writerBaseUrl + '/' + Number(action.id), {
        step_up_proof: String(stepUp.step_up_proof)
      });
      if (!result.ok) throw new Error(result.message || 'Adjustment stok gagal diproses.');
      window.location.reload();
    } catch (error) {
      showAlert('danger', error.message || 'Adjustment stok gagal diproses.');
      stockAdjustmentStepUpSubmitting = false;
      stockAdjustmentStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => { button.disabled = false; });
      clearStockAdjustmentButtonLoading(action?.button);
      clearStockAdjustmentButtonLoading(stockAdjustmentStepUpSubmitBtn);
    }
  });

  stockAdjustmentStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (stockAdjustmentStepUpSubmitting) return;
    if (stockAdjustmentStepUpPasswordEl) stockAdjustmentStepUpPasswordEl.value = '';
    clearStockAdjustmentButtonLoading(stockAdjustmentStepUpSubmitBtn);
    clearStockAdjustmentButtonLoading(pendingStockAdjustmentAction?.button);
    pendingStockAdjustmentAction = null;
  });

  renderSelectedItem();
  renderDraftLines();

  // AJAX search: debounce auto-submit on filter q inputs
  document.querySelectorAll('[data-adj-filter-q]').forEach(input => {
    input.addEventListener('input', () => {
      clearTimeout(window._adjFilterSearchTimer);
      window._adjFilterSearchTimer = setTimeout(() => { input.closest('form')?.submit(); }, 500);
    });
  });
})();
</script>
