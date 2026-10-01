<?php
$filters = is_array($filters ?? null) ? $filters : [];
$matrix = is_array($matrix ?? null) ? $matrix : [];
$dates = is_array($matrix['dates'] ?? null) ? $matrix['dates'] : [];
$rows = is_array($matrix['rows'] ?? null) ? $matrix['rows'] : [];
$matrixEmbedded = !empty($matrix_embedded);
$selectedMonth = (string)($filters['month'] ?? date('Y-m'));
$componentClearUrl = site_url('production/component-daily');
if ($matrixEmbedded) {
  $componentClearUrl .= '?' . http_build_query([
    'matrix_fullscreen' => 1,
    'division_id' => (int)($filters['division_id'] ?? 0),
    'month' => $selectedMonth,
  ]);
}
$divisions = is_array($divisions ?? null) ? $divisions : [];
$todayDate = date('Y-m-d');
$todayInView = strpos($todayDate, $selectedMonth . '-') === 0;
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$adjustmentReasonOptions = function_exists('component_adjustment_reason_options')
  ? component_adjustment_reason_options()
  : [];
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT') {
    return 'Event';
  }
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY') {
    return 'Reguler';
  }
  return $value !== '' ? $value : '-';
};
$divisionLabel = static function (array $row): string {
  $code = trim((string)($row['division_code'] ?? $row['code'] ?? ''));
  $name = trim((string)($row['division_name'] ?? $row['name'] ?? ''));
  if ($code !== '' && $name !== '') {
    return $code . ' - ' . $name;
  }
  return $name !== '' ? $name : ($code !== '' ? $code : '-');
};
$lotAverageCost = static function (array $lotSummary): float {
  $balanceQty = (float)($lotSummary['balance_qty'] ?? 0);
  if ($balanceQty <= 0) {
    return 0.0;
  }
  return round((float)($lotSummary['total_value'] ?? 0) / $balanceQty, 6);
};
$isCurrentMonthView = $selectedMonth === date('Y-m');
$dailyMatrixColspan = (int)($matrixEmbedded ? (4 + (count($dates) * 5)) : (4 + count($dates)));
$summaryRows = count($rows);
$summaryClosingQty = 0.0;
$summaryOpeningQty = 0.0;
$summaryInQty = 0.0;
$summaryOutQty = 0.0;
$summaryAdjQty = 0.0;
$summaryValue = 0.0;
$summaryPositive = 0;
$summaryZero = 0;
$summaryNegative = 0;
$summaryBase = 0;
$summaryPrepare = 0;
$summaryReguler = 0;
$summaryEvent = 0;
$summaryDivisionValues = [];
foreach ($rows as $summaryRow) {
  $rowClosing = (float)($summaryRow['total_closing'] ?? 0);
  $rowOpening = (float)($summaryRow['total_opening'] ?? 0);
  $rowIn = (float)($summaryRow['total_in'] ?? 0);
  $rowOut = (float)($summaryRow['total_out'] ?? 0);
  $rowAdj = (float)($summaryRow['total_adj'] ?? 0);
  $rowValue = (float)($summaryRow['total_value'] ?? 0);
  $summaryClosingQty += $rowClosing;
  $summaryOpeningQty += $rowOpening;
  $summaryInQty += $rowIn;
  $summaryOutQty += $rowOut;
  $summaryAdjQty += $rowAdj;
  $summaryValue += $rowValue;

  if ($rowClosing > 0.0001) {
    $summaryPositive++;
  } elseif ($rowClosing < -0.0001) {
    $summaryNegative++;
  } else {
    $summaryZero++;
  }

  $componentType = strtoupper(trim((string)($summaryRow['component_type'] ?? '')));
  if ($componentType === 'BASE') {
    $summaryBase++;
  } elseif ($componentType === 'PREPARE') {
    $summaryPrepare++;
  }

  $locationType = strtoupper(trim((string)($summaryRow['location_type'] ?? '')));
  if ($locationType === 'BAR_EVENT' || $locationType === 'KITCHEN_EVENT' || $locationType === 'ROASTERY_EVENT') {
    $summaryEvent++;
  } else {
    $summaryReguler++;
  }

  $divisionName = trim((string)($summaryRow['division_name'] ?? '-'));
  $summaryDivisionValues[$divisionName] = ($summaryDivisionValues[$divisionName] ?? 0) + $rowValue;
}
arsort($summaryDivisionValues);
$topDivisionName = key($summaryDivisionValues) ?: '-';
$topDivisionValue = (float)(reset($summaryDivisionValues) ?: 0);
$topDivisionShare = $summaryValue > 0 ? round(($topDivisionValue / $summaryValue) * 100, 1) : 0.0;
$summaryTurnoverBase = $summaryOpeningQty + max($summaryInQty, 0);
$summaryUsageRate = $summaryTurnoverBase > 0 ? round((max($summaryOutQty, 0) / $summaryTurnoverBase) * 100, 1) : 0.0;
?>

<style>
  .component-daily-matrix-shell {
    border: 1px solid #e8d2c3;
    border-radius: 18px;
    overflow: hidden;
    background:
      radial-gradient(circle at top right, rgba(232, 123, 72, .08), transparent 28%),
      linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    box-shadow: 0 18px 36px -30px rgba(95, 53, 39, .45), inset 0 0 0 1px rgba(255, 255, 255, .55);
  }
  .component-daily-matrix-shell.is-embedded .component-daily-fixed-3 { left: 270px; }
  /* Single scroll container - both axes in one element so position:sticky works in Chrome */
  .component-daily-matrix-scroll {
    max-height: 74vh;
    overflow: auto;
  }
  .component-daily-matrix {
    min-width: 2280px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    width: max-content;
  }
  .component-daily-matrix th,
  .component-daily-matrix td {
    vertical-align: top;
    border-right: 1px solid #efddd2;
    border-bottom: 1px solid #f3e4da;
  }
  .component-daily-matrix thead th {
    position: sticky;
    top: 0;
    z-index: 7;
    background: linear-gradient(180deg, #7c1f2d 0%, #9f2f3e 100%);
    border-bottom: 1px solid #7f2936;
    color: #fff8f5;
    box-shadow: 0 6px 14px -12px rgba(58, 12, 20, .85);
    text-align: center;
  }
  .component-daily-matrix thead th.component-daily-date-col {
    min-width: 158px;
    padding: .45rem .4rem .5rem;
  }
  .component-daily-matrix tbody td {
    background: #fff;
    padding: .5rem;
  }
  .component-daily-matrix tbody tr:nth-child(even) td {
    background: #fffaf6;
  }
  .component-daily-fixed {
    position: sticky;
    left: 0;
    z-index: 6;
    background: #fff !important;
    min-width: 270px;
    max-width: 270px;
    box-shadow: 1px 0 0 #eadccf, 20px 0 22px -20px rgba(107, 70, 54, .42);
  }
  .component-daily-fixed-2 {
    position: sticky;
    left: 270px;
    z-index: 6;
    background: #fff !important;
    min-width: 150px;
    max-width: 150px;
    box-shadow: 1px 0 0 #eadccf, 18px 0 20px -20px rgba(107, 70, 54, .32);
  }
  .component-daily-fixed-3 {
    position: sticky;
    left: 420px;
    z-index: 6;
    background: #fff !important;
    min-width: 260px;
    max-width: 260px;
    box-shadow: 1px 0 0 #eadccf, 18px 0 20px -20px rgba(107, 70, 54, .26);
  }
  .component-daily-matrix thead .component-daily-fixed,
  .component-daily-matrix thead .component-daily-fixed-2,
  .component-daily-matrix thead .component-daily-fixed-3 {
    z-index: 9;
    background: linear-gradient(180deg, #6f1928 0%, #8a2938 100%) !important;
  }
  .component-daily-matrix tbody tr:nth-child(even) .component-daily-fixed,
  .component-daily-matrix tbody tr:nth-child(even) .component-daily-fixed-2,
  .component-daily-matrix tbody tr:nth-child(even) .component-daily-fixed-3 {
    background: #fff7f1 !important;
  }
  .component-daily-total-group {
    min-width: 176px;
    background: #fcf1e8 !important;
  }
  .component-daily-today {
    background: linear-gradient(180deg, #ffb29d 0%, #ff8f73 100%) !important;
    color: #5d160d !important;
    border-bottom-color: #df6847 !important;
  }
  /* Visual grouping separator between different components */
  tr[style*="border-top"] > td {
    border-top: 2px solid #e8d2c3 !important;
  }
  .component-daily-today-soft {
    background: #fff1ec !important;
  }
  .component-daily-today-close {
    background: #ffe0d6 !important;
    color: #7b2416;
  }
  .component-daily-today-anchor {
    scroll-margin-left: 720px;
  }
  .component-daily-legend {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
  }
  .component-daily-legend-swatch {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    border: 1px solid #ef9d86;
    background: #ffb29d;
  }
  .component-daily-headcard {
    display: grid;
    gap: .18rem;
    justify-items: center;
    text-align: center;
    line-height: 1.05;
  }
  .component-daily-headcard .day {
    font-size: 1.28rem;
    font-weight: 900;
    letter-spacing: .02em;
  }
  .component-daily-headcard .weekday {
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    opacity: .92;
  }
  .component-daily-headcard .full-date {
    font-size: .71rem;
    opacity: .88;
  }
  .component-daily-headcard .today-tag {
    margin-top: .1rem;
    padding: .14rem .42rem;
    border-radius: 999px;
    font-size: .61rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    background: rgba(93, 22, 13, .12);
    color: inherit;
  }
  .component-daily-summary-card {
    min-width: 0;
    white-space: normal;
    line-height: 1.35;
  }
  .component-daily-summary-card .summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    font-weight: 600;
    color: #5f3527;
  }
  .component-daily-summary-card .summary-sub {
    font-size: .74rem;
    color: #7f675f;
  }
  .component-daily-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .42rem;
    margin-top: .38rem;
  }
  .component-daily-summary-metric {
    border: 1px solid #eadccf;
    border-radius: 12px;
    background: #fffaf6;
    padding: .42rem .48rem;
  }
  .component-daily-summary-metric .label {
    display: block;
    font-size: .68rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
  }
  .component-daily-summary-metric strong {
    display: block;
    font-size: .88rem;
    color: #503125;
    line-height: 1.18;
  }
  .component-daily-summary-inline {
    margin-top: .42rem;
    font-size: .7rem;
    color: #8a5b4d;
  }
  .component-daily-date-cell,
  .component-daily-total-cell {
    min-width: 158px;
    padding: .42rem !important;
  }
  .component-daily-metric-card {
    display: grid;
    gap: .34rem;
    min-height: 146px;
    padding: .55rem .6rem;
    border-radius: 16px;
    border: 1px solid #efd9ca;
    background:
      linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(255,249,244,.98) 100%);
    box-shadow: 0 14px 22px -24px rgba(95, 53, 39, .55);
  }
  .component-daily-total-cell .component-daily-metric-card {
    background: linear-gradient(180deg, #fff9f3 0%, #ffefe4 100%);
    border-color: #eccdb8;
  }
  .component-daily-metric-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: .5rem;
    align-items: center;
    font-size: .72rem;
    line-height: 1.15;
  }
  .component-daily-metric-row + .component-daily-metric-row {
    padding-top: .32rem;
    border-top: 1px dashed #edd7c8;
  }
  .component-daily-metric-row .label {
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #8a5b4d;
  }
  .component-daily-metric-row .value {
    font-weight: 800;
    color: #5c3326;
    text-align: right;
  }
  .component-daily-metric-row.metric-in .label,
  .component-daily-metric-row.metric-in .value {
    color: #0e8b40;
  }
  .component-daily-metric-row.metric-out .label,
  .component-daily-metric-row.metric-out .value {
    color: #d7662a;
  }
  .component-daily-metric-row.metric-adj .label,
  .component-daily-metric-row.metric-adj .value {
    color: #5d46d7;
  }
  .component-daily-metric-row.metric-close .label,
  .component-daily-metric-row.metric-close .value {
    color: #9a5a00;
  }
  .component-daily-metric-actions {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    justify-content: flex-end;
  }
  .component-daily-cell-note {
    display: block;
    font-size: .64rem;
    line-height: 1.25;
    color: #9a7a6e;
    white-space: normal;
    text-align: right;
  }
  .component-daily-lot-parent-toggle {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border: 1px solid #e3d1c5;
    border-radius: 999px;
    padding: .18rem .55rem;
    background: #fff;
    color: #7a4c3f;
    font-size: .72rem;
    font-weight: 600;
  }
  .component-daily-lot-parent-toggle i {
    transition: transform .18s ease;
  }
  .component-daily-lot-parent-toggle[aria-expanded="true"] i {
    transform: rotate(180deg);
  }
  .component-daily-lot-child-row td {
    background: #fffdfa !important;
    border-top: 0;
  }
  .component-daily-lot-child-row .component-daily-fixed,
  .component-daily-lot-child-row .component-daily-fixed-2,
  .component-daily-lot-child-row .component-daily-fixed-3 {
    background: #fffaf6 !important;
  }
  .component-daily-lot-child-row .component-daily-fixed {
    padding-left: 1rem !important;
  }
  .component-daily-lot-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .16rem .48rem;
    border-radius: 999px;
    background: #fff4ea;
    border: 1px solid #ead3c2;
    color: #8a5b4d;
    font-size: .66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .component-daily-lot-child-row .component-daily-summary-card {
    min-width: 0;
  }
  .component-daily-child-note {
    display: block;
    font-size: .64rem;
    color: #9a7a6e;
    white-space: normal;
  }
  .component-daily-lot-empty {
    color: #d5b6a3;
  }
  .component-daily-adjust-btn {
    min-width: 26px;
    height: 26px;
    padding: 0;
    border-radius: 10px;
  }
  .component-daily-adjust-btn.btn-pencil {
    border-color: #e7b75e;
    color: #9a5a00;
    background: #fff7e5;
  }
  .component-daily-adjust-btn.btn-pencil:hover {
    border-color: #d29a34;
    color: #6f4200;
    background: #ffe8bf;
  }
  .component-daily-adjust-btn.btn-plus {
    border-color: #b9dfc4;
    color: #0c6a35;
    background: #effbf3;
  }
  .component-daily-adjust-btn.btn-plus:hover {
    border-color: #7fc497;
    color: #084c25;
    background: #dff5e7;
  }
  .component-adjust-quick-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .8rem;
  }
  .component-adjust-quick-grid .full {
    grid-column: 1 / -1;
  }
    gap: .9rem;
    border: 1px solid #ebdfd6;
    background: #fffaf6;
    border-radius: 14px;
    padding: .85rem 1rem;
  .component-adjust-modal-dialog {
    max-width: min(980px, calc(100vw - 1.5rem));
  }
  .component-adjust-modal-content {
    border: 0;
    border-radius: 24px;
    overflow: hidden;
    background:
      radial-gradient(circle at top right, rgba(232, 123, 72, .12), transparent 24%),
      linear-gradient(180deg, #fffdfb 0%, #fff8f3 100%);
    box-shadow: 0 28px 60px -36px rgba(73, 26, 18, .55);
  }
  .component-adjust-modal-header {
    padding: 1rem 1.15rem 1rem 1.2rem;
    border-bottom: 1px solid #f0ddd1;
    background:
      linear-gradient(180deg, rgba(255,255,255,.92) 0%, rgba(255,247,241,.94) 100%);
  }
  .component-adjust-modal-titlewrap {
    display: grid;
    gap: .2rem;
  }
  .component-adjust-modal-kicker {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .66rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #9a5b43;
  }
  .component-adjust-modal-subtitle {
    font-size: .78rem;
    color: #8c6a5a;
  }
  .component-adjust-modal-header .modal-title {
    color: #4f2d22;
    font-weight: 800;
  }
  .component-adjust-modal-body {
    padding: 1rem 1.15rem 1.05rem;
  }
  }
    border: 1px solid #edd8ca;
    background:
      linear-gradient(135deg, rgba(255,255,255,.98) 0%, rgba(255,248,241,.98) 100%);
    border-radius: 20px;
    padding: .95rem 1rem;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
  }
  .component-adjust-quick-context .fw-semibold {
    font-size: 1.02rem;
    color: #4f2d22;
  }
  .component-adjust-quick-context .small {
    color: #8d6c5b !important;
  }
  .component-adjust-section {
    border: 1px solid #efddd1;
    border-radius: 18px;
    padding: .9rem;
    background: rgba(255,255,255,.72);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
  }
  .component-adjust-section + .component-adjust-section {
    margin-top: .85rem;
  }
  .component-adjust-section-title {
    margin-bottom: .7rem;
    font-size: .74rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #8f5946;
  }
  .component-adjust-field {
    display: grid;
    gap: .35rem;
  }
  .component-adjust-field .form-label {
    margin-bottom: 0;
    font-size: .74rem;
    font-weight: 800;
    color: #694236;
  }
  .component-adjust-modal-content .form-control,
  .component-adjust-modal-content .form-select {
    min-height: 48px;
    border-radius: 14px;
    border-color: #ead6c8;
    background: #fff;
    color: #533226;
    box-shadow: none;
  }
  .component-adjust-modal-content textarea.form-control {
    min-height: 92px;
    resize: vertical;
  }
  .component-adjust-modal-content .form-control:focus,
  .component-adjust-modal-content .form-select:focus {
    border-color: #d78966;
    box-shadow: 0 0 0 .22rem rgba(214, 125, 84, .14);
  }
  .component-adjust-modal-content .form-control[readonly] {
    background: #fff6ef;
    color: #7a4f40;
    font-weight: 700;
  }
  .component-adjust-modal-footer {
    padding: .95rem 1.15rem 1.1rem;
    border-top: 1px solid #f0ddd1;
    background: rgba(255,252,249,.92);
  }
  .component-adjust-modal-footer .btn {
    min-width: 138px;
    min-height: 44px;
    border-radius: 14px;
    font-weight: 800;
  }
  .component-adjust-modal-footer .btn-primary {
    border-color: #cf5335;
    background: linear-gradient(180deg, #dd5d3a 0%, #c94428 100%);
    box-shadow: 0 14px 26px -20px rgba(201, 68, 40, .9);
  }
  .component-adjust-modal-footer .btn-primary:hover {
    border-color: #b53b21;
    background: linear-gradient(180deg, #cf5335 0%, #b83a20 100%);
  }
    padding: .85rem 1rem;
  }
  .component-batch-quick-usage {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .5rem;
  }
  @media (max-width: 991.98px) {
    .component-adjust-quick-grid {
      grid-template-columns: 1fr;
    }
    .component-daily-summary-grid {
      grid-template-columns: 1fr;
    }
    .component-daily-matrix-shell {
      border-radius: 12px;
    }
    .component-daily-matrix-scroll {
      max-height: none;
    }
    .component-adjust-modal-dialog {
      max-width: calc(100vw - 1rem);
    }
    .component-adjust-modal-footer {
      flex-direction: column;
    }
    .component-adjust-modal-footer .btn {
      width: 100%;
    }
  }
  <?php if ($matrixEmbedded): ?>
  .component-daily-filter-card { margin-bottom: .35rem !important; }
  .component-daily-filter-card .card-body { padding: .25rem .5rem !important; }
  .component-daily-filter-card .form-label { margin-bottom: .1rem; font-size: .58rem; line-height: 1; }
  .component-daily-filter-card .form-control,
  .component-daily-filter-card .form-select { min-height: 30px; height: 30px; padding: .2rem .45rem; font-size: .78rem; }
  .component-daily-matrix-shell { border-radius: 8px; box-shadow: none; overflow: visible; }
  .stock-summary-grid { grid-template-columns: repeat(6, minmax(0, 1fr)) !important; gap: .25rem !important; margin: 0 0 .3rem !important; }
  .stock-summary-card { min-height: 54px !important; padding: .45rem .7rem !important; border-radius: 10px !important; line-height: 1.05 !important; }
  .stock-summary-card__icon { display: none !important; }
  .stock-summary-card::before, .stock-summary-card::after { display: none !important; }
  .stock-summary-card__label { display: block !important; margin: 0 !important; font-size: .62rem !important; line-height: 1.1 !important; }
  .stock-summary-card__value { display: block !important; margin: 0 !important; font-size: 1rem !important; line-height: 1.1 !important; }
  .stock-summary-card__detail { display: none !important; }
  .component-daily-matrix-scroll { max-height: none; overflow: auto; width: 100%; }
  /* The page owns vertical scrolling; only the date pane scrolls horizontally. */
  .component-daily-matrix-scroll[data-split-ready="1"] { overflow: visible; }
  .component-daily-split { display:grid; grid-template-columns:var(--cdf-freeze-width,calc(var(--cdf-c1,145px) + var(--cdf-c2,245px) + var(--cdf-c3,175px) + var(--cdf-c4,175px))) minmax(0,1fr); width:100%; min-width:0; align-items:start; }
  .component-daily-split-freeze { min-width:0; width:var(--cdf-freeze-width,calc(var(--cdf-c1,145px) + var(--cdf-c2,245px) + var(--cdf-c3,175px) + var(--cdf-c4,175px))); background:#fff; position:relative; z-index:30; overflow:visible; }
  .component-daily-split-scroll { min-width:0; overflow:visible; position:relative; }
  .component-daily-split-head-wrap { overflow:hidden; width:100%; }
  .component-daily-split-body-wrap { overflow:auto; width:100%; }
  .component-daily-split table { margin:0; border:0; border-radius:0; overflow:visible; border-collapse:separate; border-spacing:0; table-layout:fixed; }
  .component-daily-split-freeze table { width:var(--cdf-freeze-width,calc(var(--cdf-c1,145px) + var(--cdf-c2,245px) + var(--cdf-c3,175px) + var(--cdf-c4,175px))); min-width:var(--cdf-freeze-width,calc(var(--cdf-c1,145px) + var(--cdf-c2,245px) + var(--cdf-c3,175px) + var(--cdf-c4,175px))); }
  .component-daily-split-scroll table { min-width:0; }
  .component-daily-split-freeze .component-daily-flat-fixed { position:static !important; }
  .component-daily-split-freeze .component-daily-flat-fixed,
  .component-daily-split-scroll .component-daily-flat-metric { box-sizing:border-box; }
  .component-daily-split-freeze-head-wrap,
  .component-daily-split-scroll-head-wrap { position:sticky; top:0; z-index:40; background:#7c1f2d; }
  .component-daily-split-freeze-head-table,
  .component-daily-split-freeze-head-table thead,
  .component-daily-split-freeze-head-table tr { height:100%; }
  .component-daily-split .component-daily-matrix thead th { position:relative !important; top:auto !important; left:auto !important; }
  .component-daily-split .component-daily-matrix tbody tr > td {
    display:table-cell !important;
    border-top:0 !important;
    border-bottom:1px solid #d4c3ba !important;
    border-right:1px solid #d4c3ba !important;
    box-shadow:none !important;
  }
  .component-daily-split-scroll-body-table tbody tr > td:nth-child(5n + 1) { border-left:2px solid #b68f7d; }
  .component-daily-split .component-daily-matrix tbody tr.component-stock-alert > td { border-color:#d27884 !important; }
  .component-daily-split-scroll-body-table tbody tr > td { position:relative; z-index:1; }
  .component-daily-split-scroll-body-table tbody tr > td.component-stock-alert-cell { z-index:2; }
  .component-daily-matrix { min-width: 0; table-layout: fixed; }
  .component-daily-matrix col.component-daily-date-col-width { width: 120px; }
  .component-daily-matrix thead th.component-daily-date-col,
  .component-daily-date-cell,
  .component-daily-total-cell { min-width: 310px; }
  .component-daily-matrix thead th.component-daily-date-col { min-width: 600px; width:600px; white-space:nowrap; }
  .component-daily-headcard{display:flex;align-items:center;justify-content:center;gap:.35rem;white-space:nowrap}
  .component-daily-metric-heading { min-width: 120px; width: 120px; max-width: 120px; padding: .32rem .2rem !important; text-align: center; background: #8e1628 !important; color: #fff !important; font-size: .64rem; }
  .component-daily-metric-heading.day-start { border-left: 2px solid #d89a7f !important; }
  .component-daily-metric-heading.day-end { border-right: 2px solid #d89a7f !important; }
  .component-daily-flat-metric { min-width: 120px; width: 120px; max-width: 120px; padding: .24rem .3rem !important; text-align: right; white-space: nowrap; vertical-align: middle !important; font-size: .58rem; line-height: 1.05; }
  .component-daily-subhead th.component-daily-today { background: #f6a900 !important; color: #321800 !important; }
  .component-daily-matrix thead th[rowspan="2"] { vertical-align: middle; }
  .component-daily-matrix tbody td { padding: .18rem .25rem; }
  .component-daily-fixed { min-width: 270px; max-width: 270px; }
  .component-daily-fixed-3 { left: 270px; min-width: 190px; max-width: 190px; }
  .component-daily-flat-fixed { position:sticky; z-index:6; background:#fff!important; padding:.22rem .32rem!important; font-size:.6rem; line-height:1.06; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; vertical-align:middle !important; }
  .component-daily-matrix tbody tr { height:52px; }
  .component-daily-matrix tbody tr > td.component-daily-flat-fixed,
  .component-daily-matrix tbody tr > td.component-daily-flat-metric { height:52px; vertical-align:middle !important; }
  .cdf-compact-stack { display:flex; flex-direction:column; justify-content:center; gap:1px; min-width:0; min-height:32px; line-height:1.08; text-align:left; }
  .cdf-compact-stack strong { font-weight:800; font-size:.64rem; }
  .cdf-compact-stack small { font-size:.5rem; color:#765e61; overflow:hidden; text-overflow:ellipsis; }
  .cdf-compact-stack > span { display:flex; justify-content:space-between; gap:.25rem; }
  .component-daily-flat-fixed:nth-child(1){left:0;width:var(--cdf-c1,145px);min-width:var(--cdf-c1,145px)}
  .component-daily-flat-fixed:nth-child(2){left:var(--cdf-c1,145px);width:var(--cdf-c2,245px);min-width:var(--cdf-c2,245px)}
  .component-daily-flat-fixed:nth-child(3){left:calc(var(--cdf-c1,145px) + var(--cdf-c2,245px));width:var(--cdf-c3,175px);min-width:var(--cdf-c3,175px)}
  .component-daily-flat-fixed:nth-child(4){left:calc(var(--cdf-c1,145px) + var(--cdf-c2,245px) + var(--cdf-c3,175px));width:var(--cdf-c4,175px);min-width:var(--cdf-c4,175px);box-shadow:12px 0 16px -16px #481323}
  .component-daily-flat-c1 { left:0 !important; width:var(--cdf-c1,145px) !important; min-width:var(--cdf-c1,145px) !important; }
  .component-daily-flat-c2 { left:var(--cdf-c1,145px) !important; width:var(--cdf-c2,245px) !important; min-width:var(--cdf-c2,245px) !important; }
  .component-daily-flat-c3 { left:calc(var(--cdf-c1,145px) + var(--cdf-c2,245px)) !important; width:var(--cdf-c3,175px) !important; min-width:var(--cdf-c3,175px) !important; }
  .component-daily-flat-c4 { left:calc(var(--cdf-c1,145px) + var(--cdf-c2,245px) + var(--cdf-c3,175px)) !important; width:var(--cdf-c4,175px) !important; min-width:var(--cdf-c4,175px) !important; box-shadow:12px 0 16px -16px #481323; }
  .component-daily-matrix tbody td.component-daily-flat-fixed { z-index:20 !important; background-clip:padding-box; }
  .component-daily-matrix tbody td.component-daily-flat-metric { position:relative; z-index:1; }
  .component-daily-col-resizer{position:absolute;top:0;right:-3px;width:7px;height:100%;cursor:col-resize;z-index:15}
  .component-daily-col-resizer:hover,.component-daily-col-resizer.is-dragging{background:#f6a900}
  .component-daily-matrix thead th.component-daily-flat-fixed { z-index:10; background:#7c1f2d!important; color:#fff; overflow:visible; }
  .component-daily-matrix thead th.component-daily-flat-fixed { font-size:.64rem; line-height:1.05; }
  .component-daily-flat-heading { display:block; max-width:100%; overflow:visible; text-overflow:clip; white-space:normal; overflow-wrap:anywhere; line-height:1.1; }
  .component-daily-matrix thead th.component-daily-date-col .day { font-size:.95rem; }
  .component-daily-matrix thead th.component-daily-date-col .weekday,
  .component-daily-matrix thead th.component-daily-date-col .full-date { font-size:.56rem; }
  .component-daily-subhead th { top:var(--component-daily-head-height,58px)!important; }
  .component-daily-summary-card { line-height: 1.08; }
  .component-daily-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .1rem; margin-top: .1rem; }
  .component-daily-summary-metric { padding: .08rem .16rem; border-radius: 4px; }
  .component-daily-summary-metric .label { font-size: .52rem; }
  .component-daily-summary-metric strong { font-size: .64rem; white-space: nowrap; }
  .component-daily-summary-card .summary-sub { display: none; }
  .component-daily-metric-card { display: grid; grid-template-columns: repeat(5, minmax(52px, 1fr)); gap: 0; min-height: 0; padding: .22rem; border-radius: 5px; box-shadow: none; }
  .component-daily-metric-row { display: flex; min-width: 0; flex-direction: column; align-items: flex-end; justify-content: center; gap: .12rem; padding: .08rem .16rem; font-size: .64rem; }
  .component-daily-metric-row + .component-daily-metric-row { border-top: 0; border-left: 1px solid #e7d8cf; padding-top: .08rem; }
  .component-daily-metric-row .label { font-size: .55rem; }
  .component-daily-metric-row .value { font-size: .64rem; white-space: nowrap; }
  .component-daily-metric-actions { gap: .1rem; }
  .component-daily-adjust-btn { width: 22px; height: 22px; padding: 0; }
  .component-daily-cell-note { display: none; }
  .component-daily-matrix tbody tr:nth-child(odd) td { background: #fff !important; }
  .component-daily-matrix tbody tr:nth-child(even) td { background: #dfe7ee !important; }
  .component-daily-matrix tbody tr:nth-child(odd) .component-daily-flat-fixed { background: #fff !important; }
  .component-daily-matrix tbody tr:nth-child(even) .component-daily-flat-fixed { background: #dfe7ee !important; }
  .component-daily-split tbody tr.component-stock-alert > td,
  .component-daily-split tbody tr.component-stock-alert > td.component-stock-alert-cell {
    background: #a51025 !important;
    background-color: #a51025 !important;
    color: #fff !important;
  }
  .component-daily-split tbody tr.component-stock-alert > td > *,
  .component-daily-split tbody tr.component-stock-alert > td small,
  .component-daily-split tbody tr.component-stock-alert > td strong,
  .component-daily-split tbody tr.component-stock-alert > td span { color: #fff !important; }
  .component-daily-matrix tbody tr.component-stock-alert td { background: #b91c1c !important; color: #fff !important; }
  .component-daily-matrix tbody tr.component-stock-alert td.component-daily-flat-fixed { background:#b91c1c !important; color:#fff !important; }
  .component-daily-matrix tbody tr.component-stock-alert td.component-daily-flat-fixed small { color:#ffe8e8 !important; }
  .component-daily-matrix tbody tr.component-stock-alert .component-daily-metric-row .label,
  .component-daily-matrix tbody tr.component-stock-alert .component-daily-metric-row .value,
  .component-daily-matrix tbody tr.component-stock-alert .component-daily-summary-card,
  .component-daily-matrix tbody tr.component-stock-alert .component-daily-summary-card strong { color: #fff !important; }
  .component-daily-matrix tbody tr.component-stock-alert td a,
  .component-daily-matrix tbody tr.component-stock-alert td small,
  .component-daily-matrix tbody tr.component-stock-alert td .badge { color: #fff !important; }
  .component-daily-matrix tbody tr.component-stock-alert td,
  .component-daily-matrix tbody tr.component-stock-alert td * { color: #fff !important; }
  .component-stock-alert .component-daily-adjust-btn{background:#fff!important;border-color:#fff!important;color:#8e1628!important;opacity:1!important}
  .component-daily-adjust-btn{background:#fff!important;border:1px solid #8e1628!important;color:#8e1628!important;opacity:1!important}
  .component-daily-matrix tbody tr.component-stock-alert td button.component-daily-adjust-btn,
  .component-daily-matrix tbody tr.component-stock-alert td button.component-daily-adjust-btn i { background:#fff!important; border-color:#fff!important; color:#8e1628!important; opacity:1!important; }
  .component-daily-flat-metric.metric-in,
  .component-daily-flat-metric.metric-adj { position:relative; display:table-cell !important; padding-right:.3rem !important; text-align:right; white-space:nowrap; }
  .component-daily-flat-metric.metric-in > span:first-child,
  .component-daily-flat-metric.metric-adj > span:first-child { display:block; min-width:0; overflow:hidden; padding-right:24px; text-overflow:ellipsis; text-align:right; }
  .component-daily-flat-metric.metric-in > span:first-child { display:block; min-width:0; overflow:hidden; padding-right:24px; text-overflow:ellipsis; text-align:right; }
  .component-daily-flat-metric .component-daily-adjust-btn { position:absolute; top:50%; right:2px; display:inline-flex; width:20px; height:20px; align-items:center; justify-content:center; margin:0; transform:translateY(-50%); vertical-align:middle; color:#8e1628!important; }
  .component-daily-matrix tbody tr.component-stock-alert td,
  .component-daily-matrix tbody tr.component-stock-alert td * { color: #fff !important; }
  .component-daily-matrix tbody td.component-daily-flat-metric.is-today {
    box-shadow: none !important;
    border-top: 0 !important;
    border-bottom: 1px solid #d4c3ba !important;
  }
  .component-daily-matrix tbody tr:not(.component-stock-alert) td.component-daily-flat-metric.is-today {
    background: #fff0bd !important;
    color: #4a2a12 !important;
    box-shadow: none !important;
  }
  .component-daily-matrix tbody tr:not(.component-stock-alert) td.component-daily-flat-metric.is-today * { color: inherit !important; }
  .component-daily-matrix tbody tr.component-stock-alert td.component-daily-flat-metric,
  .component-daily-matrix tbody tr.component-stock-alert td.component-daily-flat-fixed {
    background: #a51025 !important;
    color: #fff !important;
    text-shadow: 0 1px 1px rgba(48, 0, 8, .35);
  }
  .component-daily-matrix tbody tr.component-stock-alert td.component-daily-flat-metric *,
  .component-daily-matrix tbody tr.component-stock-alert td.component-daily-flat-fixed * {
    color: #fff !important;
  }
  .component-daily-matrix tbody tr.component-stock-alert > td {
    background: #a51025 !important;
    color: #fff !important;
  }
  .component-daily-matrix tbody tr.component-stock-alert > td.component-daily-flat-fixed,
  .component-daily-matrix tbody tr.component-stock-alert > td.component-daily-flat-metric {
    background: #a51025 !important;
    background-color: #a51025 !important;
    color: #fff !important;
  }
  .component-daily-matrix tbody tr.component-stock-alert > td * { color: #fff !important; }
  .component-daily-matrix tbody td.component-stock-alert-cell {
    background: #a51025 !important;
    background-color: #a51025 !important;
    color: #fff !important;
  }
  .component-daily-matrix tbody td.component-stock-alert-cell * { color: #fff !important; }
  .component-daily-matrix tbody td.component-stock-alert-cell button.component-daily-adjust-btn,
  .component-daily-matrix tbody td.component-stock-alert-cell button.component-daily-adjust-btn i {
    background: #fff !important;
    border-color: #fff !important;
    color: #8e1628 !important;
  }
  .component-daily-matrix tbody tr.component-stock-alert td button.component-daily-adjust-btn,
  .component-daily-matrix tbody tr.component-stock-alert td button.component-daily-adjust-btn i {
    background: #fff !important;
    border-color: #fff !important;
    color: #8e1628 !important;
  }
  .component-daily-matrix tbody tr:not(.component-stock-alert) td.component-daily-flat-fixed,
  .component-daily-matrix tbody tr:not(.component-stock-alert) td.component-daily-flat-fixed * {
    color: #47343a !important;
  }
  .component-daily-matrix tbody tr:not(.component-stock-alert) td.component-daily-flat-fixed small {
    color: #765e61 !important;
  }
  .component-daily-adjust-btn > .ri {
    display: inline-block !important;
    width: 1rem !important;
    height: 1rem !important;
    flex: 0 0 1rem;
    background-color: currentColor !important;
    -webkit-mask-repeat: no-repeat !important;
    mask-repeat: no-repeat !important;
    -webkit-mask-size: 100% 100% !important;
    mask-size: 100% 100% !important;
  }
  .component-daily-split .component-daily-matrix tbody tr td .component-daily-adjust-btn > .ri {
    background-color: currentColor !important;
    color: #8e1628 !important;
  }
  .component-daily-matrix thead th.component-daily-today { background: #f6a900 !important; color: #321800 !important; box-shadow: inset 2px 0 0 #a95b00, inset -2px 0 0 #a95b00; }
  <?php endif; ?>
</style>

<?php if (!$matrixEmbedded): ?><div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Daily Matrix Base/Prepare'); ?></h4>
      <small class="text-muted">Matrix harian komponen per tanggal: opening, in, out, adjustment, closing.</small>
    </div>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('inventory/daily-matrix') . '?' . http_build_query(array_filter(['division_id' => !empty($filters['division_id']) ? (int)$filters['division_id'] : null, 'source' => 'component', 'month' => $selectedMonth])); ?>"><i class="ri ri-fullscreen-line me-1"></i>Workspace Matrix</a>
  </div>
</div>
<?php endif; ?>

<?php if (!$matrixEmbedded): ?>
<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'daily']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-daily'),
  'component_type_filters' => $filters,
  'component_type_active' => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'month'         => (string)($filters['month'] ?? ''),
    'division_id'   => !empty($filters['division_id']) ? (int)$filters['division_id'] : '',
    'location_type' => (string)($filters['location_type'] ?? ''),
  ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>
<?php endif; ?>

<?php
$locationFilterValue = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT' || $value === 'EVENT') {
    return 'EVENT';
  }
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY' || $value === 'REGULER') {
    return 'REGULER';
  }
  return '';
};
$buildLotUrl = static function (array $row, string $status = 'ALL') use ($locationFilterValue): string {
  $params = [
    'q' => trim((string)($row['component_code'] ?? $row['component_name'] ?? '')),
    'status' => $status,
    'location_type' => $locationFilterValue((string)($row['location_type'] ?? '')),
    'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
    'type' => strtoupper(trim((string)($row['component_type'] ?? ''))),
  ];
  return site_url('production/component-lots') . '?' . http_build_query(array_filter($params, static function ($value) {
    return $value !== null && $value !== '';
  }));
};
?>

<?php if (!$matrixEmbedded): ?><?php $matrix_month_selector = 'input[name="month"]'; $this->load->view('inventory/_matrix_spreadsheet_panel'); ?><?php endif; ?>
<div class="card mb-3 component-daily-filter-card">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-daily'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama component / divisi / kode">
      </div>
      <div class="col-sm-6 col-md-2"<?php echo $matrixEmbedded ? ' style="display:none"' : ''; ?>>
        <label class="form-label mb-1">Bulan</label>
        <input type="month" name="month" class="form-control" value="<?php echo html_escape($selectedMonth); ?>">
      </div>
      <?php if ($matrixEmbedded): ?><input type="hidden" name="matrix_fullscreen" value="1"><?php endif; ?>
      <?php if ($matrixEmbedded): ?><input type="hidden" name="division_id" value="<?php echo (int)($filters['division_id'] ?? 0); ?>"><?php else: ?>
      <div class="col-sm-6 col-md-2">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisions as $division): ?>
            <?php $optionId = (int)($division['id'] ?? 0); ?>
            <option value="<?php echo $optionId; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === $optionId) ? 'selected' : ''; ?>><?php echo html_escape($divisionLabel((array)$division)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-sm-6 col-md-2">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape((string)$key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$key) ? 'selected' : ''; ?>><?php echo html_escape((string)$label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-md-1">
        <label class="form-label mb-1">Per Hal.</label>
        <select id="cdPerPage" class="form-select" name="per_page">
          <?php $selPP = (int)($filters['per_page'] ?? 25); ?>
          <?php foreach ([25, 50, 100, 0] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $selPP === $pp ? 'selected' : ''; ?>>
              <?php echo $pp > 0 ? $pp : 'Semua'; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-auto col-md-auto d-flex gap-2 align-items-end">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo html_escape($componentClearUrl); ?>" class="btn btn-outline-secondary">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php if ($summaryRows > 0): ?>
  <?php $this->load->view('layout/_stock_summary_cards', [
    'stock_summary_label' => 'Ringkasan daily matrix komponen',
    'stock_summary_cards' => [
      ['label' => 'Baris Matrix', 'value' => number_format($summaryRows, 0, ',', '.'), 'detail' => number_format($summaryBase, 0, ',', '.') . ' BASE · ' . number_format($summaryPrepare, 0, ',', '.') . ' PREPARE', 'tone' => 'violet', 'icon' => 'ri-box-3-line'],
      ['label' => 'Closing Qty', 'value' => number_format($summaryClosingQty, 2, ',', '.'), 'detail' => $summaryPositive . ' positif · ' . $summaryZero . ' nol · ' . $summaryNegative . ' minus', 'tone' => $summaryNegative > 0 ? 'danger' : 'amber', 'icon' => 'ri-scales-3-line'],
      ['label' => 'Flow Bulan', 'value' => 'In ' . number_format($summaryInQty, 2, ',', '.'), 'detail' => 'Out ' . number_format($summaryOutQty, 2, ',', '.') . ' · Adj ' . ($summaryAdjQty >= 0 ? '+' : '') . number_format($summaryAdjQty, 2, ',', '.'), 'tone' => 'blue', 'icon' => 'ri-exchange-funds-line'],
      ['label' => 'Nilai Stok', 'value' => 'Rp ' . number_format($summaryValue, 0, ',', '.'), 'detail' => 'Rata-rata Rp ' . number_format($summaryValue / $summaryRows, 0, ',', '.'), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
      ['label' => 'Coverage Lokasi', 'value' => number_format($summaryReguler, 0, ',', '.') . ' Reg', 'detail' => number_format($summaryEvent, 0, ',', '.') . ' Event · usage ' . number_format($summaryUsageRate, 1, ',', '.') . '%', 'tone' => 'amber', 'icon' => 'ri-pie-chart-2-line'],
      ['label' => 'Top Divisi', 'value' => $topDivisionName, 'detail' => number_format($topDivisionShare, 1, ',', '.') . '% · Rp ' . number_format($topDivisionValue, 0, ',', '.'), 'tone' => 'aqua', 'icon' => 'ri-building-line'],
    ],
  ]); ?>
<?php endif; ?>

<div class="card">
  <div class="card-body p-2">
    <div class="component-daily-matrix-shell<?php echo $matrixEmbedded ? ' is-embedded' : ''; ?>">
    <div class="component-daily-matrix-scroll" id="componentDailyMatrixWrap">
      <table class="table table-sm table-striped component-daily-matrix">
        <?php if ($matrixEmbedded): ?><colgroup>
          <col style="width:var(--cdf-c1,145px)">
          <col style="width:var(--cdf-c2,245px)">
          <col style="width:var(--cdf-c3,175px)">
          <col style="width:var(--cdf-c4,175px)">
          <?php foreach ($dates as $_date): ?><?php for ($metricIndex = 0; $metricIndex < 5; $metricIndex++): ?><col class="component-daily-date-col-width"><?php endfor; ?><?php endforeach; ?>
        </colgroup><?php endif; ?>
        <thead>
          <tr>
            <?php if ($matrixEmbedded): ?>
              <?php foreach (['Kategori / Tujuan','Component / Profile / Satuan','Harga / HPP','Nilai Awal / Nilai Sisa'] as $flatIndex => $flatHeading): ?><th class="component-daily-flat-fixed component-daily-flat-c<?php echo (int)$flatIndex + 1; ?>" rowspan="2"><span class="component-daily-flat-heading"><?php echo $flatHeading; ?></span><span class="component-daily-col-resizer" data-column="<?php echo (int)$flatIndex + 1; ?>" aria-hidden="true"></span></th><?php endforeach; ?>
            <?php else: ?>
              <th class="component-daily-fixed">Komponen</th><th class="component-daily-fixed-2">Divisi</th><th class="component-daily-fixed-3">Ringkasan</th>
            <?php endif; ?>
            <?php foreach ($dates as $date): ?>
              <?php $isToday = $todayInView && (string)($date['date'] ?? '') === $todayDate; ?>
              <th colspan="<?php echo $matrixEmbedded ? 5 : 1; ?>" class="text-center component-daily-date-col <?php echo $isToday ? 'component-daily-today component-daily-today-anchor' : ''; ?>" <?php echo $isToday ? 'data-today-anchor="1"' : ''; ?>>
                <div class="component-daily-headcard">
                  <span class="day"><?php echo html_escape((string)($date['day'] ?? '')); ?></span>
                  <span class="weekday"><?php echo html_escape((string)($date['weekday'] ?? '')); ?></span>
                  <span class="full-date"><?php echo html_escape((string)($date['date'] ?? '')); ?></span>
                  <?php if ($isToday): ?><span class="today-tag">Hari Ini</span><?php endif; ?>
                </div>
              </th>
            <?php endforeach; ?>
            <?php if (!$matrixEmbedded): ?><th class="text-center component-daily-total-group">
              <div class="component-daily-headcard">
                <span class="day">SUM</span>
                <span class="weekday">Total Bulan</span>
                <span class="full-date">Closing terakhir</span>
              </div>
            </th><?php endif; ?>
          </tr>
          <?php if ($matrixEmbedded): ?><tr class="component-daily-subhead">
            <?php foreach ($dates as $date): ?>
              <?php foreach (['Awal', 'In', 'Out', 'Adj', 'Akhir'] as $subIndex => $metricLabel): ?><th class="component-daily-metric-heading <?php echo $todayInView && (string)($date['date'] ?? '') === $todayDate ? 'component-daily-today' : ''; ?> <?php echo $subIndex === 0 ? 'day-start' : ($subIndex === 4 ? 'day-end' : ''); ?>"><?php echo $metricLabel; ?></th><?php endforeach; ?>
            <?php endforeach; ?>
          </tr><?php endif; ?>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="<?php echo $dailyMatrixColspan; ?>" class="text-center text-muted py-4">Belum ada data daily komponen pada filter ini.</td>
            </tr>
          <?php else: ?>
            <?php $prevGroupKey = null; ?>
            <?php foreach ($rows as $rowIndex => $row): ?>
              <?php $lotSummary = is_array($row['lot_summary'] ?? null) ? $row['lot_summary'] : []; ?>
              <?php $lotRows = array_values((array)($lotSummary['rows'] ?? [])); ?>
              <?php $hasLotChildren = count($lotRows) > 1; ?>
              <?php $singleLot = (!$hasLotChildren && !empty($lotRows)) ? (array)$lotRows[0] : null; ?>
              <?php $lotToggleId = 'componentDailyLot_' . (int)$rowIndex; ?>
              <?php $summaryClosingQty = (float)($row['total_closing'] ?? 0); ?>
              <?php $summaryAvgCost = (float)($row['avg_cost'] ?? 0); ?>
              <?php $summaryTotalValue = (float)($row['total_value'] ?? 0); ?>
              <?php $groupKey = ((int)($row['component_id'] ?? 0)) . '|' . ((int)($row['uom_id'] ?? 0)); ?>
              <?php $isGroupStart = $groupKey !== $prevGroupKey; ?>
              <?php $prevGroupKey = $groupKey; ?>
              <?php if ($matrixEmbedded): ?>
                <?php
                  $embeddedLotRows = array_values((array)($row['lot_daily_rows'] ?? []));
                  $embeddedProfiles = count($embeddedLotRows) > 1 ? $embeddedLotRows : [null];
                  $singleEmbeddedLot = count($embeddedLotRows) === 1 ? (array)$embeddedLotRows[0] : null;
                ?>
                <?php foreach ($embeddedProfiles as $embeddedLot): ?>
                  <?php
                    $profile = is_array($embeddedLot) ? $embeddedLot : null;
                    $profileDays = $profile ? (array)($profile['days'] ?? []) : (array)($row['days'] ?? []);
                    $profileClosing = $profile ? (float)($profile['total_closing'] ?? 0) : $summaryClosingQty;
                    $profileOpening = $profile ? (float)($profile['total_opening'] ?? 0) : (float)($row['total_opening'] ?? 0);
                    $profileHpp = $profile ? (float)($profile['unit_cost'] ?? 0) : $summaryAvgCost;
                    $profilePrice = $profileHpp > 0 ? $profileHpp : $lotAverageCost($lotSummary);
                    $profileOpeningValue = round($profileOpening * $profileHpp, 2);
                    $profileRemainingValue = $profile ? (float)($profile['total_value'] ?? 0) : $summaryTotalValue;
                    $profileLotId = $profile ? (int)($profile['id'] ?? 0) : (int)($singleEmbeddedLot['id'] ?? 0);
                    $profileLotLabel = $profile ? (string)($profile['lot_no'] ?? '-') : (string)($singleEmbeddedLot['lot_no'] ?? '-');
                    $profileUnitCost = $profile ? (float)($profile['unit_cost'] ?? 0) : (float)($singleEmbeddedLot['unit_cost'] ?? $summaryAvgCost);
                    $profileAlertClosing = $profileClosing;
                    if ($todayInView) {
                      $todayCell = (array)($profileDays[$todayDate] ?? []);
                      $profileAlertClosing = array_key_exists('closing', $todayCell)
                        ? (float)$todayCell['closing']
                        : 0.0;
                    }
                    $profileIsAlert = $profileAlertClosing <= 0;
                    $profileAlertCellClass = $profileIsAlert ? ' component-stock-alert-cell' : '';
                    $profileAlertCellStyle = $profileIsAlert ? ' style="background:#a51025 !important;color:#fff !important;"' : '';
                  ?>
                  <tr class="<?php echo $profileIsAlert ? 'component-stock-alert' : ''; ?>">
                    <td class="component-daily-flat-fixed component-daily-flat-c1<?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><span class="cdf-compact-stack"><strong><?php echo html_escape((string)($row['category_name'] ?? '-')); ?></strong><small><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></small></span></td>
                    <td class="component-daily-flat-fixed component-daily-flat-c2<?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><span class="cdf-compact-stack"><strong><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></strong><small><?php echo html_escape($profileLotLabel); ?> · <?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></small></span></td>
                    <td class="component-daily-flat-fixed component-daily-flat-c3<?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><span class="cdf-compact-stack"><span>Harga <strong>Rp <?php echo number_format($profilePrice, 2, ',', '.'); ?></strong></span><span>HPP <strong>Rp <?php echo number_format($profileHpp, 2, ',', '.'); ?></strong></span></span></td>
                    <td class="component-daily-flat-fixed component-daily-flat-c4<?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><span class="cdf-compact-stack"><span>Awal <strong>Rp <?php echo number_format($profileOpeningValue, 2, ',', '.'); ?></strong></span><span>Sisa <strong>Rp <?php echo number_format($profileRemainingValue, 2, ',', '.'); ?></strong></span></span></td>
                    <?php foreach ($dates as $date): ?>
                      <?php $dateText = (string)($date['date'] ?? ''); $cell = (array)($profileDays[$dateText] ?? []); $isToday = $todayInView && $dateText === $todayDate; ?>
                      <td class="component-daily-flat-metric<?php echo $isToday ? ' component-daily-today-soft is-today' : ''; ?><?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><?php echo number_format((float)($cell['opening'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="component-daily-flat-metric metric-in<?php echo $isToday ? ' is-today' : ''; ?><?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><span><?php echo number_format((float)($cell['in'] ?? 0), 2, ',', '.'); ?></span><button type="button" class="btn btn-sm component-daily-adjust-btn btn-plus" data-action="quick-batch" data-batch-date="<?php echo html_escape($dateText); ?>" data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>" data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>" data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>" data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>" data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>" data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>" data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>" data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>" title="Input batch produksi"><i class="ri ri-add-line"></i></button></td>
                      <td class="component-daily-flat-metric metric-out<?php echo $isToday ? ' is-today' : ''; ?><?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><?php echo number_format((float)($cell['out'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="component-daily-flat-metric metric-adj<?php echo $isToday ? ' is-today' : ''; ?><?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><span><?php echo number_format((float)($cell['adj'] ?? 0), 2, ',', '.'); ?></span><button type="button" class="btn btn-sm component-daily-adjust-btn btn-pencil" data-action="quick-adjust" data-adjustment-date="<?php echo html_escape($dateText); ?>" data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>" data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>" data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>" data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>" data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>" data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>" data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>" data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>" data-available-qty="<?php echo html_escape(number_format((float)($cell['closing'] ?? 0), 4, '.', '')); ?>" data-selected-lot-id="<?php echo $profileLotId; ?>" data-unit-cost="<?php echo html_escape(number_format($profileUnitCost, 6, '.', '')); ?>" data-lot-label="<?php echo html_escape($profileLotLabel); ?>" title="Input adjustment cepat"><i class="ri ri-edit-line"></i></button></td>
                      <td class="component-daily-flat-metric metric-close<?php echo $isToday ? ' is-today' : ''; ?><?php echo $profileAlertCellClass; ?>"<?php echo $profileAlertCellStyle; ?>><?php echo number_format((float)($cell['closing'] ?? 0), 2, ',', '.'); ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
                <?php continue; ?>
              <?php endif; ?>
              <tr class="<?php echo $summaryClosingQty <= 0 ? 'component-stock-alert' : ''; ?>" data-cd-parent-row="<?php echo (int)$rowIndex; ?>" data-cd-lot-toggle="<?php echo html_escape($lotToggleId); ?>"<?php if ($isGroupStart && $rowIndex > 0): ?> style="border-top:2px solid #e8d2c3"<?php endif; ?>>
                <td class="component-daily-fixed">
                  <?php if ($isGroupStart): ?>
                    <div><a href="<?php echo html_escape(site_url('production/component-masters/usage/' . (int)($row['component_id'] ?? 0))); ?>" class="fw-semibold text-decoration-none"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></a></div>
                    <small class="text-muted"><?php echo html_escape((string)($row['component_type'] ?? '-')); ?> - <?php echo html_escape((string)($row['uom_code'] ?? '')); ?><?php if ($matrixEmbedded): ?> · <?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?><?php endif; ?></small>
                  <?php else: ?>
                    <div class="text-muted ps-2" style="border-left:3px solid #e8d2c3;font-size:.78rem">
                      <i class="ri ri-corner-down-right-line"></i> <?php echo html_escape((string)($row['component_name'] ?? '-')); ?>
                    </div>
                  <?php endif; ?>
                </td>
                <?php if (!$matrixEmbedded): ?><td class="component-daily-fixed-2">
                  <div><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></small>
                </td><?php endif; ?>
                <?php if (!$matrixEmbedded): ?><td class="component-daily-fixed-3">
                  <div class="component-daily-summary-card">
                    <div class="summary-head">
                      <span><?php echo html_escape((string)($row['component_type'] ?? '-')); ?></span>
                      <?php if ($hasLotChildren): ?>
                        <button type="button" class="component-daily-lot-parent-toggle" data-lot-toggle="<?php echo html_escape($lotToggleId); ?>" aria-expanded="false">
                          <span><?php echo (int)$lotSummary['lot_count']; ?> lot aktif</span>
                          <i class="ri ri-arrow-down-s-line"></i>
                        </button>
                      <?php elseif (!empty($lotSummary['lot_count'])): ?>
                        <span class="badge bg-label-secondary">1 lot aktif</span>
                      <?php endif; ?>
                    </div>
                    <div class="component-daily-summary-grid">
                      <div class="component-daily-summary-metric">
                        <span class="label">Closing</span>
                        <strong><?php echo number_format($summaryClosingQty, 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                      </div>
                      <div class="component-daily-summary-metric">
                        <span class="label">Nilai</span>
                        <strong><?php echo number_format($summaryTotalValue, 2, ',', '.'); ?></strong>
                      </div>
                      <div class="component-daily-summary-metric">
                        <span class="label">Avg Cost</span>
                        <strong><?php echo number_format($summaryAvgCost, 2, ',', '.'); ?></strong>
                      </div>
                      <div class="component-daily-summary-metric">
                        <span class="label"><?php echo $hasLotChildren ? 'Range Lot' : 'Lot Cost'; ?></span>
                        <strong><?php echo number_format((float)($lotSummary['min_unit_cost'] ?? 0), 2, ',', '.'); ?><?php if ((float)($lotSummary['max_unit_cost'] ?? 0) !== (float)($lotSummary['min_unit_cost'] ?? 0)): ?> - <?php echo number_format((float)($lotSummary['max_unit_cost'] ?? 0), 2, ',', '.'); ?><?php endif; ?></strong>
                      </div>
                    </div>
                    <div class="summary-sub mt-2">
                      <?php echo $hasLotChildren ? 'Parent mengikuti monthly stock. Expand untuk melihat tiap lot.' : (!empty($lotSummary['has_mixed_cost']) ? 'Ringkasan dari monthly stock. Cost lot aktif campur.' : 'Ringkasan dari monthly stock. Cost lot aktif seragam.'); ?>
                    </div>
                  </div>
                </td><?php endif; ?>
                <?php foreach ($dates as $date): ?>
                  <?php $cell = (array)($row['days'][(string)($date['date'] ?? '')] ?? []); ?>
                  <?php $isToday = $todayInView && (string)($date['date'] ?? '') === $todayDate; ?>
                  <?php $usageOutQty = (float)($cell['usage_out'] ?? 0); ?>
                  <?php $wasteQty = (float)($cell['waste'] ?? 0); ?>
                  <?php $spoilQty = (float)($cell['spoil'] ?? 0); ?>
                  <?php if ($matrixEmbedded): ?>
                    <td class="component-daily-flat-metric component-daily-today-soft <?php echo $isToday ? 'is-today' : ''; ?>"><?php echo number_format((float)($cell['opening'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="component-daily-flat-metric metric-in <?php echo $isToday ? 'is-today' : ''; ?>"><span><?php echo number_format((float)($cell['in'] ?? 0), 2, ',', '.'); ?></span><button type="button" class="btn btn-sm component-daily-adjust-btn btn-plus" data-action="quick-batch" data-batch-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>" data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>" data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>" data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>" data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>" data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>" data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>" data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>" data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>" title="Input batch produksi" aria-label="Input batch produksi"><i class="ri ri-add-line"></i></button></td>
                    <td class="component-daily-flat-metric metric-out <?php echo $isToday ? 'is-today' : ''; ?>"><?php echo number_format((float)($cell['out'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="component-daily-flat-metric metric-adj <?php echo $isToday ? 'is-today' : ''; ?>"><span><?php echo number_format((float)($cell['adj'] ?? 0), 2, ',', '.'); ?></span><?php if (!$hasLotChildren): ?><button type="button" class="btn btn-sm component-daily-adjust-btn btn-pencil" data-action="quick-adjust" data-adjustment-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>" data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>" data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>" data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>" data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>" data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>" data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>" data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>" data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>" data-available-qty="<?php echo html_escape(number_format((float)($cell['closing'] ?? 0), 4, '.', '')); ?>" data-selected-lot-id="<?php echo (int)($singleLot['id'] ?? 0); ?>" data-unit-cost="<?php echo html_escape(number_format((float)($singleLot['unit_cost'] ?? 0), 6, '.', '')); ?>" data-lot-label="<?php echo html_escape((string)($singleLot['lot_no'] ?? '')); ?>" title="Input adjustment cepat" aria-label="Input adjustment cepat"><i class="ri ri-edit-line"></i></button><?php endif; ?></td>
                    <td class="component-daily-flat-metric metric-close <?php echo $isToday ? 'is-today' : ''; ?>"><?php echo number_format((float)($cell['closing'] ?? 0), 2, ',', '.'); ?></td>
                  <?php else: ?>
                  <td class="component-daily-date-cell <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                    <div class="component-daily-metric-card <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                      <div class="component-daily-metric-row metric-open">
                        <span class="label">Awal</span>
                        <span class="value"><?php echo number_format((float)($cell['opening'] ?? 0), 2, ',', '.'); ?></span>
                      </div>
                      <div class="component-daily-metric-row metric-in">
                        <span class="label">In</span>
                        <span class="value">
                          <span class="component-daily-metric-actions">
                            <span><?php echo number_format((float)($cell['in'] ?? 0), 2, ',', '.'); ?></span>
                            <button
                              type="button"
                              class="btn btn-sm component-daily-adjust-btn btn-plus"
                              data-action="quick-batch"
                              data-batch-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>"
                              data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                              data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>"
                              data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                              data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"
                              data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                              data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>"
                              data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>"
                              data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>"
                              title="Input batch produksi"
                              aria-label="Input batch produksi"
                            ><i class="ri ri-add-line"></i></button>
                          </span>
                        </span>
                      </div>
                      <div class="component-daily-metric-row metric-out">
                        <span class="label">Out</span>
                        <span class="value"><?php echo number_format((float)($cell['out'] ?? 0), 2, ',', '.'); ?></span>
                      </div>
                      <div class="component-daily-metric-row metric-adj">
                        <span class="label">Adj</span>
                        <span class="value">
                          <span class="component-daily-metric-actions">
                            <span><?php echo number_format((float)($cell['adj'] ?? 0), 2, ',', '.'); ?></span>
                            <?php if (!$hasLotChildren): ?>
                              <button
                                type="button"
                                class="btn btn-sm component-daily-adjust-btn btn-pencil"
                                data-action="quick-adjust"
                                data-adjustment-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>"
                                data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                                data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>"
                                data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                                data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"
                                data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                                data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>"
                                data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>"
                                data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>"
                                data-available-qty="<?php echo html_escape(number_format((float)($cell['closing'] ?? 0), 4, '.', '')); ?>"
                                data-selected-lot-id="<?php echo (int)($singleLot['id'] ?? 0); ?>"
                                data-unit-cost="<?php echo html_escape(number_format((float)($singleLot['unit_cost'] ?? 0), 6, '.', '')); ?>"
                                data-lot-label="<?php echo html_escape((string)($singleLot['lot_no'] ?? '')); ?>"
                                title="Input adjustment cepat"
                                aria-label="Input adjustment cepat"
                              ><i class="ri ri-edit-line"></i></button>
                            <?php endif; ?>
                          </span>
                        </span>
                      </div>
                      <div class="component-daily-metric-row metric-close <?php echo $isToday ? 'component-daily-today-close' : ''; ?>">
                        <span class="label">Akhir</span>
                        <span class="value"><?php echo number_format((float)($cell['closing'] ?? 0), 2, ',', '.'); ?></span>
                      </div>
                      <?php if ($usageOutQty > 0 || $wasteQty > 0 || $spoilQty > 0): ?>
                        <small class="component-daily-cell-note">
                          <?php
                            $outNotes = [];
                            if ($usageOutQty > 0) {
                              $outNotes[] = 'Use ' . number_format($usageOutQty, 2, ',', '.');
                            }
                            if ($wasteQty > 0) {
                              $outNotes[] = 'Waste ' . number_format($wasteQty, 2, ',', '.');
                            }
                            if ($spoilQty > 0) {
                              $outNotes[] = 'Spoil ' . number_format($spoilQty, 2, ',', '.');
                            }
                            echo html_escape(implode(' | ', $outNotes));
                          ?>
                        </small>
                      <?php endif; ?>
                    </div>
                  </td>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$matrixEmbedded): ?><td class="component-daily-total-cell component-daily-total-group">
                  <div class="component-daily-metric-card">
                    <div class="component-daily-metric-row metric-open">
                      <span class="label">Awal</span>
                      <span class="value"><?php echo number_format((float)($row['total_opening'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-in">
                      <span class="label">In</span>
                      <span class="value"><?php echo number_format((float)($row['total_in'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-out">
                      <span class="label">Out</span>
                      <span class="value"><?php echo number_format((float)($row['total_out'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-adj">
                      <span class="label">Adj</span>
                      <span class="value"><?php echo number_format((float)($row['total_adj'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-close">
                      <span class="label">Akhir</span>
                      <span class="value"><?php echo number_format((float)($row['total_closing'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                  </div>
                </td><?php endif; ?>
              </tr>
              <?php if ($hasLotChildren): ?>
                <?php $lotDailyRows = array_values((array)($row['lot_daily_rows'] ?? [])); ?>
                <?php foreach ($lotDailyRows as $lotRow): ?>
                  <tr class="component-daily-lot-child-row d-none" data-lot-group="<?php echo html_escape($lotToggleId); ?>">
                    <td class="component-daily-fixed">
                      <div class="component-daily-lot-badge">Lot</div>
                      <div class="mt-1 fw-semibold"><?php echo html_escape((string)($lotRow['lot_no'] ?? '-')); ?></div>
                      <small class="text-muted">Child lot aktif</small>
                    </td>
                    <?php if (!$matrixEmbedded): ?><td class="component-daily-fixed-2">
                      <div>Masuk <?php echo html_escape((string)($lotRow['receipt_date'] ?? '-')); ?></div>
                      <small class="text-muted"><?php echo !empty($lotRow['expiry_date']) ? 'Exp ' . html_escape((string)$lotRow['expiry_date']) : 'Tanpa expiry'; ?></small>
                    </td><?php endif; ?>
                    <td class="component-daily-fixed-3">
                      <div class="component-daily-summary-card">
                        <div class="summary-head">
                          <span>Child Lot</span>
                          <a href="<?php echo html_escape($buildLotUrl((array)$row, 'OPEN')); ?>" class="text-decoration-none small">FIFO</a>
                        </div>
                        <div class="component-daily-summary-grid">
                          <div class="component-daily-summary-metric">
                            <span class="label">Tanggal Masuk</span>
                            <strong><?php echo html_escape((string)($lotRow['receipt_date'] ?? '-')); ?></strong>
                          </div>
                          <div class="component-daily-summary-metric">
                            <span class="label">Qty Masuk</span>
                            <strong><?php echo number_format((float)($lotRow['qty_in_total'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                          </div>
                          <div class="component-daily-summary-metric">
                            <span class="label">Cost</span>
                            <strong><?php echo number_format((float)($lotRow['unit_cost'] ?? 0), 2, ',', '.'); ?></strong>
                          </div>
                          <div class="component-daily-summary-metric">
                            <span class="label">Sisa</span>
                            <strong><?php echo number_format((float)($lotRow['total_closing'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                          </div>
                        </div>
                        <div class="component-daily-summary-inline">Tanggal masuk, qty, dan cost lot tampil tetap di panel kiri.</div>
                      </div>
                    </td>
                    <?php foreach ($dates as $date): ?>
                      <?php $cell = (array)($lotRow['days'][(string)($date['date'] ?? '')] ?? []); ?>
                      <?php $isToday = $todayInView && (string)($date['date'] ?? '') === $todayDate; ?>
                      <?php $isReceiptDate = (string)($lotRow['receipt_date'] ?? '') === (string)($date['date'] ?? ''); ?>
                      <td class="component-daily-date-cell <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                        <div class="component-daily-metric-card <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                          <div class="component-daily-metric-row metric-open">
                            <span class="label">Awal</span>
                            <span class="value"><?php echo number_format((float)($cell['opening'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <div class="component-daily-metric-row metric-in">
                            <span class="label">In</span>
                            <span class="value"><?php echo number_format((float)($cell['in'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <div class="component-daily-metric-row metric-out">
                            <span class="label">Out</span>
                            <span class="value"><?php echo number_format((float)($cell['out'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <div class="component-daily-metric-row metric-adj">
                            <span class="label">Adj</span>
                            <span class="value">
                              <span class="component-daily-metric-actions">
                                <span><?php echo number_format((float)($cell['adj'] ?? 0), 2, ',', '.'); ?></span>
                                <button
                                  type="button"
                                  class="btn btn-sm component-daily-adjust-btn btn-pencil"
                                  data-action="quick-adjust"
                                  data-adjustment-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>"
                                  data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                                  data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>"
                                  data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                                  data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"
                                  data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                                  data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>"
                                  data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>"
                                  data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>"
                                  data-available-qty="<?php echo html_escape(number_format((float)($cell['closing'] ?? 0), 4, '.', '')); ?>"
                                  data-selected-lot-id="<?php echo (int)($lotRow['id'] ?? 0); ?>"
                                  data-unit-cost="<?php echo html_escape(number_format((float)($lotRow['unit_cost'] ?? 0), 6, '.', '')); ?>"
                                  data-lot-label="<?php echo html_escape((string)($lotRow['lot_no'] ?? '')); ?>"
                                  title="Input adjustment cepat lot"
                                  aria-label="Input adjustment cepat lot"
                                ><i class="ri ri-edit-line"></i></button>
                              </span>
                            </span>
                          </div>
                          <div class="component-daily-metric-row metric-close <?php echo $isToday ? 'component-daily-today-close' : ''; ?>">
                            <span class="label">Akhir</span>
                            <span class="value"><?php echo number_format((float)($cell['closing'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <?php if ($isReceiptDate && (float)($cell['in'] ?? 0) > 0): ?>
                            <small class="component-daily-cell-note">Tanggal masuk lot</small>
                          <?php endif; ?>
                        </div>
                      </td>
                    <?php endforeach; ?>
                    <td class="component-daily-total-cell component-daily-total-group">
                      <div class="component-daily-metric-card">
                        <div class="component-daily-metric-row metric-open">
                          <span class="label">Awal</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_opening'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-in">
                          <span class="label">In</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_in'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-out">
                          <span class="label">Out</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_out'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-adj">
                          <span class="label">Adj</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_adj'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-close">
                          <span class="label">Akhir</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_closing'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-2 px-2 pb-1 border-top pt-2">
      <div class="d-flex flex-wrap gap-3 align-items-center">
        <div class="small text-muted">* Kolom Close total menampilkan nilai closing terakhir yang tercatat pada bulan berjalan.</div>
        <?php if ($todayInView): ?>
          <div class="small text-muted component-daily-legend"><span class="component-daily-legend-swatch"></span>Hari ini</div>
        <?php endif; ?>
        <small class="text-muted" id="cdPageInfo"></small>
      </div>
      <nav><ul class="pagination pagination-sm mb-0" id="cdPagination"></ul></nav>
    </div>
  </div>
</div>

<div class="modal fade" id="componentDailyAdjustModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable component-adjust-modal-dialog">
    <div class="modal-content component-adjust-modal-content">
      <div class="modal-header component-adjust-modal-header">
        <div class="component-adjust-modal-titlewrap">
          <span class="component-adjust-modal-kicker">Daily Matrix Adjustment</span>
          <h5 class="modal-title">Adjustment Cepat Komponen</h5>
          <div class="component-adjust-modal-subtitle">Pilih satu jenis koreksi untuk profile atau lot yang sedang dipilih dari matrix harian.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body component-adjust-modal-body">
        <div id="componentDailyAdjustAlert" class="mb-3"></div>
        <div class="component-adjust-quick-context mb-3">
          <div class="fw-semibold" id="qdaComponentLabel">-</div>
          <div class="small text-muted" id="qdaContextLabel">-</div>
        </div>
        <form id="componentDailyAdjustForm">
          <div class="component-adjust-section">
            <div class="component-adjust-section-title">Konteks</div>
            <div class="component-adjust-quick-grid">
              <div class="component-adjust-field">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" id="qdaDate" required>
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Stok Tercatat</label>
                <input type="text" class="form-control" id="qdaAvailable" readonly>
              </div>
            </div>
          </div>
          <div class="component-adjust-section">
            <div class="component-adjust-section-title">Pilih Koreksi</div>
            <div class="component-adjust-quick-grid">
              <div class="component-adjust-field">
                <label class="form-label">Jenis Koreksi</label>
                <select class="form-select" id="qdaAction" required>
                  <option value="">Pilih salah satu...</option>
                  <option value="SPOIL">Spoil</option>
                  <option value="WASTE">Waste</option>
                  <option value="MINUS">Minus</option>
                  <option value="PLUS">Plus</option>
                </select>
              </div>
              <div class="component-adjust-field">
                <label class="form-label" id="qdaQtyLabel">Qty</label>
                <input type="number" min="0" step="0.01" class="form-control" id="qdaQty" value="0">
              </div>
              <div class="component-adjust-field full">
                <label class="form-label" id="qdaReasonLabel">Alasan</label>
                <select class="form-select" id="qdaReason">
                  <option value="">Pilih jenis koreksi dulu</option>
                </select>
              </div>
            </div>
            <div class="component-adjust-field mt-3 d-none" id="qdaCostWrap">
              <label class="form-label">HPP / Unit Cost</label>
              <input type="number" min="0" step="0.000001" class="form-control" id="qdaUnitCostDisplay" value="0" placeholder="Masukkan HPP jika belum terisi">
              <div class="form-text">Terisi otomatis dari profile/lot. Bisa diubah manual jika HPP perlu dikoreksi.</div>
            </div>
          </div>
          <div class="component-adjust-section">
            <div class="component-adjust-section-title">Catatan</div>
            <div class="component-adjust-field">
              <label class="form-label">Catatan Tambahan</label>
              <textarea class="form-control" id="qdaNote" rows="2" placeholder="Catatan adjustment cepat"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer component-adjust-modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="qdaSubmitBtn">Simpan & Verifikasi</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="componentDailyBatchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Batch Produksi Cepat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="componentDailyBatchAlert" class="mb-3"></div>
        <div class="component-adjust-quick-context mb-3">
          <div class="fw-semibold" id="qdbComponentLabel">-</div>
          <div class="small text-muted" id="qdbContextLabel">-</div>
        </div>
        <form id="componentDailyBatchForm" class="component-adjust-quick-grid">
          <div>
            <label class="form-label">Tanggal Batch</label>
            <input type="date" class="form-control" id="qdbDate" required>
          </div>
          <div>
            <label class="form-label">Mode Produksi</label>
            <select class="form-select" id="qdbScalingMode">
              <option value="BATCH">Sesuai Resep</option>
              <option value="REFERENCE">Sesuai Bahan Acuan</option>
            </select>
          </div>
          <div id="qdbBatchCountWrap">
            <label class="form-label">Jumlah Batch</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="qdbBatchCount" value="1.00">
          </div>
          <div class="d-none" id="qdbReferenceLineWrap">
            <label class="form-label">Bahan Acuan</label>
            <select class="form-select" id="qdbReferenceLine"></select>
          </div>
          <div class="d-none" id="qdbReferenceQtyWrap">
            <label class="form-label">Qty Aktual Acuan</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="qdbReferenceQty" value="">
          </div>
          <div class="full">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" id="qdbNotes" rows="2" placeholder="Catatan batch cepat"></textarea>
          </div>
        </form>
        <div class="component-batch-quick-preview mt-3">
          <div class="fw-semibold mb-1" id="qdbPreviewOutput">Preview output belum tersedia.</div>
          <div class="small text-muted" id="qdbPreviewNote">Lengkapi parameter batch untuk memuat preview.</div>
          <div class="component-batch-quick-usage" id="qdbPreviewUsage"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-success" id="qdbSubmitBtn">Simpan & Verifikasi</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="componentDailyAdjustmentStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi Posting Adjustment</h5>
          <div class="small text-muted">Draft sudah tersimpan. Masukkan password akun Anda untuk menerapkan koreksi ke stok, lot, dan nilai component.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="component_daily_adjustment_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="component_daily_adjustment_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada adjustment.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Nanti Saja</button>
        <button type="button" class="btn btn-primary" id="btn-component-daily-adjustment-step-up-post">Verifikasi &amp; Post</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="componentDailyBatchStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi Post Batch</h5>
          <div class="small text-muted">Draft sudah tersimpan. Masukkan password akun Anda sebelum stok bahan, component, lot, dan biaya batch diubah.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="component_daily_batch_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="component_daily_batch_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada batch.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Nanti Saja</button>
        <button type="button" class="btn btn-success" id="btn-component-daily-batch-step-up-post">Verifikasi &amp; Post</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const wrap = document.getElementById('componentDailyMatrixWrap');
  const originalTable = wrap?.querySelector('table.component-daily-matrix');
  if (!wrap || !originalTable || !<?php echo $matrixEmbedded ? 'true' : 'false'; ?>) return;
  if (wrap.dataset.splitReady === '1') return;

  const sourceHead = originalTable.tHead;
  const sourceBody = originalTable.tBodies[0];
  if (!sourceHead || !sourceBody || sourceHead.rows.length < 2) return;

  const split = document.createElement('div');
  split.className = 'component-daily-split';
  const freezePane = document.createElement('div');
  freezePane.className = 'component-daily-split-freeze';
  const scrollPane = document.createElement('div');
  scrollPane.className = 'component-daily-split-scroll';
  const freezeHeadWrap = document.createElement('div');
  freezeHeadWrap.className = 'component-daily-split-head-wrap component-daily-split-freeze-head-wrap';
  const scrollHeadWrap = document.createElement('div');
  scrollHeadWrap.className = 'component-daily-split-head-wrap component-daily-split-scroll-head-wrap';
  const freezeBodyWrap = document.createElement('div');
  freezeBodyWrap.className = 'component-daily-split-body-wrap';
  const scrollBodyWrap = document.createElement('div');
  scrollBodyWrap.className = 'component-daily-split-body-wrap component-daily-split-scroll-body-wrap';

  document.documentElement.style.setProperty(
    '--cdf-freeze-width',
    'calc(var(--cdf-c1,145px) + var(--cdf-c2,245px) + var(--cdf-c3,175px) + var(--cdf-c4,175px))'
  );

  const newTable = (extraClass) => {
    const table = document.createElement('table');
    table.className = originalTable.className + ' ' + extraClass;
    return table;
  };
  const appendCols = (table, count, frozen) => {
    const frozenDefaults = [145, 245, 175, 175];
    const group = document.createElement('colgroup');
    for (let i = 0; i < count; i += 1) {
      const col = document.createElement('col');
      col.style.width = frozen
        ? 'var(--cdf-c' + (i + 1) + ',' + frozenDefaults[i] + 'px)'
        : '120px';
      group.appendChild(col);
    }
    table.appendChild(group);
    if (!frozen) {
      // Intrinsic minimum sizing can expand fixed-layout tables in Firefox.
      const width = (count * 120) + 'px';
      table.style.width = width;
      table.style.minWidth = width;
      table.style.maxWidth = width;
    }
  };
  const cloneCells = (row, start, end) => Array.from(row.cells)
    .slice(start, end)
    .map((cell) => cell.cloneNode(true));

  const freezeHeadTable = newTable('component-daily-split-freeze-head-table');
  appendCols(freezeHeadTable, 4, true);
  const freezeHead = document.createElement('thead');
  const freezeHeadRow = document.createElement('tr');
  cloneCells(sourceHead.rows[0], 0, 4).forEach((cell) => {
    cell.removeAttribute('colspan');
    cell.rowSpan = 1;
    freezeHeadRow.appendChild(cell);
  });
  freezeHead.appendChild(freezeHeadRow);
  freezeHeadTable.appendChild(freezeHead);

  const scrollHeadTable = newTable('component-daily-split-scroll-head-table');
  appendCols(scrollHeadTable, sourceHead.rows[1].cells.length, false);
  const scrollHead = document.createElement('thead');
  const scrollHeadRow = document.createElement('tr');
  cloneCells(sourceHead.rows[0], 4).forEach((cell) => scrollHeadRow.appendChild(cell));
  const scrollSubHeadRow = document.createElement('tr');
  cloneCells(sourceHead.rows[1], 0).forEach((cell) => scrollSubHeadRow.appendChild(cell));
  scrollHead.appendChild(scrollHeadRow);
  scrollHead.appendChild(scrollSubHeadRow);
  scrollHeadTable.appendChild(scrollHead);

  const freezeBodyTable = newTable('component-daily-split-freeze-body-table');
  appendCols(freezeBodyTable, 4, true);
  const freezeBody = document.createElement('tbody');
  const scrollBodyTable = newTable('component-daily-split-scroll-body-table');
  appendCols(scrollBodyTable, sourceHead.rows[1].cells.length, false);
  const scrollBody = document.createElement('tbody');
  const freezeRows = [];
  const scrollRows = [];
  Array.from(sourceBody.rows).forEach((sourceRow) => {
    const freezeRow = sourceRow.cloneNode(false);
    const scrollRow = sourceRow.cloneNode(false);
    if (sourceRow.cells.length === 1 && sourceRow.cells[0].colSpan > 4) {
      const message = sourceRow.cells[0].cloneNode(true);
      message.colSpan = 4;
      freezeRow.appendChild(message);
      const blank = document.createElement('td');
      blank.colSpan = sourceHead.rows[1].cells.length;
      scrollRow.appendChild(blank);
    } else {
      cloneCells(sourceRow, 0, 4).forEach((cell) => freezeRow.appendChild(cell));
      cloneCells(sourceRow, 4).forEach((cell) => scrollRow.appendChild(cell));
    }
    freezeBody.appendChild(freezeRow);
    scrollBody.appendChild(scrollRow);
    freezeRows.push(freezeRow);
    scrollRows.push(scrollRow);
  });
  freezeBodyTable.appendChild(freezeBody);
  scrollBodyTable.appendChild(scrollBody);

  freezeHeadWrap.appendChild(freezeHeadTable);
  freezeBodyWrap.appendChild(freezeBodyTable);
  scrollHeadWrap.appendChild(scrollHeadTable);
  scrollBodyWrap.appendChild(scrollBodyTable);
  freezePane.appendChild(freezeHeadWrap);
  freezePane.appendChild(freezeBodyWrap);
  scrollPane.appendChild(scrollHeadWrap);
  scrollPane.appendChild(scrollBodyWrap);
  split.appendChild(freezePane);
  split.appendChild(scrollPane);
  wrap.insertBefore(split, originalTable);
  originalTable.style.display = 'none';
  wrap.dataset.splitReady = '1';

  const syncRows = () => {
    const count = Math.min(freezeRows.length, scrollRows.length);
    for (let index = 0; index < count; index += 1) {
      freezeRows[index].style.height = '';
      scrollRows[index].style.height = '';
      freezeRows[index].style.display = sourceBody.rows[index].style.display;
      scrollRows[index].style.display = sourceBody.rows[index].style.display;
    }
    const heights = freezeRows.map((row, index) => Math.max(row.getBoundingClientRect().height, scrollRows[index].getBoundingClientRect().height));
    heights.forEach((height, index) => {
      freezeRows[index].style.height = height + 'px';
      scrollRows[index].style.height = height + 'px';
    });
    const headHeight = scrollHeadTable.getBoundingClientRect().height;
    if (headHeight > 0) {
      freezeHeadWrap.style.height = headHeight + 'px';
      freezeHeadTable.style.height = headHeight + 'px';
    }
  };
  const syncScroll = () => {
    scrollHeadWrap.scrollLeft = scrollBodyWrap.scrollLeft;
  };
  scrollBodyWrap.addEventListener('scroll', syncScroll, { passive: true });
  let layoutFrame = 0;
  const scheduleLayout = () => {
    if (layoutFrame) return;
    layoutFrame = window.requestAnimationFrame(() => {
      layoutFrame = 0;
      syncRows();
      syncScroll();
    });
  };
  window.addEventListener('resize', scheduleLayout);
  const observer = new MutationObserver(scheduleLayout);
  observer.observe(sourceBody, { attributes: true, subtree: true, attributeFilter: ['style', 'class'] });
  if (window.ResizeObserver) {
    const sizeObserver = new ResizeObserver(scheduleLayout);
    sizeObserver.observe(freezeHeadTable);
    sizeObserver.observe(scrollHeadTable);
  }
  if (document.fonts) document.fonts.ready.then(scheduleLayout);
  scheduleLayout();
})();

(() => {
  const root = document.documentElement;
  const firstHeaderRow = document.querySelector('.component-daily-split-scroll-head-table thead tr:first-child')
    || document.querySelector('.component-daily-matrix thead tr:first-child');
  if (firstHeaderRow) root.style.setProperty('--component-daily-head-height', Math.ceil(firstHeaderRow.getBoundingClientRect().height) + 'px');
  const handles = Array.from(document.querySelectorAll('.component-daily-col-resizer'));
  handles.forEach((handle) => {
    const index = Number(handle.dataset.column || 0);
    if (!(index > 0)) return;
    const key = 'component-daily-flat-col-v2-' + index;
    const property = '--cdf-c' + index;
    const saved = Number(window.localStorage.getItem(key) || 0);
    if (saved >= 60) root.style.setProperty(property, saved + 'px');
    handle.addEventListener('pointerdown', (event) => {
      event.preventDefault();
      handle.setPointerCapture(event.pointerId);
      handle.classList.add('is-dragging');
      const cell = handle.closest('th');
      const startX = event.clientX;
      const startWidth = cell ? cell.getBoundingClientRect().width : 100;
      const move = (moveEvent) => {
        const width = Math.max(60, Math.min(420, Math.round(startWidth + moveEvent.clientX - startX)));
        root.style.setProperty(property, width + 'px');
        window.localStorage.setItem(key, String(width));
      };
      const stop = () => {
        handle.classList.remove('is-dragging');
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', stop);
        handle.removeEventListener('pointercancel', stop);
      };
      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', stop);
      handle.addEventListener('pointercancel', stop);
    });
  });
})();

(() => {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-lot-toggle]');
    if (!button) {
      return;
    }
    const targetId = String(button.getAttribute('data-lot-toggle') || '');
    if (targetId === '') {
      return;
    }
    const targets = Array.from(document.querySelectorAll('[data-lot-group="' + targetId.replace(/"/g, '\\"') + '"]'));
    if (!targets.length) {
      return;
    }
    const isExpanded = button.getAttribute('aria-expanded') === 'true';
    targets.forEach((target) => target.classList.toggle('d-none', isExpanded));
    button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
  });
})();

// -- Scroll-to-today (horizontal) --------------------------------------------
(() => {
  const wrap = document.getElementById('componentDailyMatrixWrap');
  if (!wrap) return;
  window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
    const shell = wrap.closest('.component-daily-matrix-shell');
    if (!shell?.classList.contains('is-embedded')) {
      const todayAnchor = wrap.querySelector('[data-today-anchor="1"]');
      if (!todayAnchor) return;
      const targetLeft = Math.max(todayAnchor.offsetLeft - 700, 0);
      wrap.scrollTo({ left: targetLeft, behavior: 'auto' });
      return;
    }
    const split = wrap.querySelector('.component-daily-split');
    const bodyWrap = split?.querySelector('.component-daily-split-scroll-body-wrap');
    const headWrap = split?.querySelector('.component-daily-split-scroll-head-wrap');
    const todayAnchor = split?.querySelector('[data-today-anchor="1"]');
    if (!bodyWrap || !todayAnchor) return;
    const targetLeft = Math.max(todayAnchor.offsetLeft - 12, 0);
    bodyWrap.scrollLeft = targetLeft;
    if (headWrap) headWrap.scrollLeft = targetLeft;
  }));
})();

// -- Pagination ---------------------------------------------------------------
(() => {
  const perPage    = parseInt(document.getElementById('cdPerPage')?.value || '25', 10);
  const infoEl     = document.getElementById('cdPageInfo');
  const paginEl    = document.getElementById('cdPagination');
  const tbody      = document.querySelector('.component-daily-matrix tbody');
  if (!tbody) return;

  const parentRows = Array.from(tbody.querySelectorAll('tr[data-cd-parent-row]'));
  if (!parentRows.length) return;

  const total  = parentRows.length;
  const pages  = perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : 1;
  let currentPage = 1;

  function renderPage() {
    const start = perPage > 0 ? (currentPage - 1) * perPage : 0;
    const end   = perPage > 0 ? Math.min(start + perPage, total) : total;

    parentRows.forEach((tr, i) => {
      const show = i >= start && i < end;
      tr.style.display = show ? '' : 'none';
      // hide/show lot children
      const lotId = tr.dataset.cdLotToggle;
      if (lotId) {
        tbody.querySelectorAll('[data-lot-group="' + lotId + '"]').forEach((child) => {
          child.style.display = show ? '' : 'none';
          // keep collapsed (d-none is managed by toggle button; just mirror parent visibility)
          if (!show) child.classList.add('d-none');
        });
      }
    });

    if (infoEl) {
      infoEl.textContent = perPage > 0
        ? 'Baris ' + (start + 1) + '-' + end + ' dari ' + total
        : 'Semua ' + total + ' baris';
    }

    if (!paginEl) return;
    paginEl.innerHTML = '';
    if (perPage <= 0 || pages <= 1) return;

    const mkBtn = (label, page, disabled, active) => {
      const li = document.createElement('li');
      li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.innerHTML = label;
      if (!disabled && !active) {
        a.addEventListener('click', (e) => { e.preventDefault(); currentPage = page; renderPage(); });
      }
      li.appendChild(a); paginEl.appendChild(li);
    };

    mkBtn('&laquo;', currentPage - 1, currentPage <= 1, false);
    const rs = Math.max(1, currentPage - 2), re = Math.min(pages, currentPage + 2);
    if (rs > 1) { mkBtn('1', 1, false, false); if (rs > 2) mkBtn('...', null, true, false); }
    for (let p = rs; p <= re; p++) mkBtn(String(p), p, false, p === currentPage);
    if (re < pages) { if (re < pages - 1) mkBtn('...', null, true, false); mkBtn(String(pages), pages, false, false); }
    mkBtn('&raquo;', currentPage + 1, currentPage >= pages, false);
  }

  renderPage();
})();

(() => {
  const modalEl = document.getElementById('componentDailyAdjustModal');
  const form = document.getElementById('componentDailyAdjustForm');
  const submitBtn = document.getElementById('qdaSubmitBtn');
  const alertHost = document.getElementById('componentDailyAdjustAlert');
  if (!modalEl || !form || !submitBtn) {
    return;
  }

  function getModalInstance() {
    if (!(window.bootstrap && window.bootstrap.Modal)) {
      return null;
    }
    if (modalEl.parentNode !== document.body) document.body.appendChild(modalEl);
    modalEl.style.zIndex = '2000';
    if (adjustmentStepUpModalEl && adjustmentStepUpModalEl.parentNode !== document.body) document.body.appendChild(adjustmentStepUpModalEl);
    if (adjustmentStepUpModalEl) adjustmentStepUpModalEl.style.zIndex = '2010';
    return window.bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  const reasonOptions = <?php echo json_encode($adjustmentReasonOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const saveUrl = '<?php echo site_url('production/component-adjustments/save'); ?>';
  const componentAdjustmentStepUpUrl = '<?php echo site_url('production/component-adjustments/step-up/verify'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-adjustments/post'); ?>';
  const componentAdjustmentCsrfToken = <?php echo json_encode((string)($component_adjustment_csrf_token ?? ''), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const adjustmentStepUpModalEl = document.getElementById('componentDailyAdjustmentStepUpModal');
  const adjustmentStepUpPassword = document.getElementById('component_daily_adjustment_step_up_password');
  const btnAdjustmentStepUpPost = document.getElementById('btn-component-daily-adjustment-step-up-post');
  let pendingAdjustmentPostId = 0;
  let adjustmentStepUpSubmitting = false;
  const state = {
    componentId: 0,
    componentName: '',
    locationType: '',
    divisionId: 0,
    selectedLotId: 0,
    lotLabel: '',
    defaultUnitCost: 0,
    uomId: 0,
    uomCode: ''
  };

  const fields = {
    componentLabel: document.getElementById('qdaComponentLabel'),
    contextLabel: document.getElementById('qdaContextLabel'),
    date: document.getElementById('qdaDate'),
    available: document.getElementById('qdaAvailable'),
    action: document.getElementById('qdaAction'),
    qty: document.getElementById('qdaQty'),
    qtyLabel: document.getElementById('qdaQtyLabel'),
    reason: document.getElementById('qdaReason'),
    reasonLabel: document.getElementById('qdaReasonLabel'),
    costWrap: document.getElementById('qdaCostWrap'),
    unitCostDisplay: document.getElementById('qdaUnitCostDisplay'),
    note: document.getElementById('qdaNote')
  };

  const actionMeta = {
    SPOIL: { label: 'Spoil', reasonLabel: 'Alasan Spoil', reasonCategory: 'SPOILAGE' },
    WASTE: { label: 'Waste', reasonLabel: 'Alasan Waste', reasonCategory: 'WASTE' },
    MINUS: { label: 'Minus', reasonLabel: 'Alasan Minus', reasonCategory: 'ADJUSTMENT_MINUS' },
    PLUS: { label: 'Plus', reasonLabel: 'Alasan Plus', reasonCategory: 'ADJUSTMENT_PLUS' }
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    alertHost.innerHTML = message ? '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>' : '';
  }

  function fillReasonSelect(select, category, selectedValue) {
    const options = reasonOptions?.[category] || {};
    select.innerHTML = Object.keys(options).map((key) => {
      return '<option value="' + escapeHtml(key) + '"' + (String(selectedValue || 'other') === key ? ' selected' : '') + '>' + escapeHtml(options[key]) + '</option>';
    }).join('');
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Production-Component-Adjustment-Csrf': componentAdjustmentCsrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      throw new Error('Server tidak mengirim hasil yang dapat dibaca. Data mungkin sudah tersimpan; muat ulang halaman untuk memastikan, lalu coba kembali.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Permintaan gagal diproses.');
    }
    return json;
  }

  function resetForm() {
    fields.date.value = '';
    fields.available.value = '';
    fields.action.value = '';
    fields.qty.value = '0';
    fields.note.value = '';
    fields.qtyLabel.textContent = 'Qty';
    fields.reasonLabel.textContent = 'Alasan';
    fields.reason.innerHTML = '<option value="">Pilih jenis koreksi dulu</option>';
    fields.costWrap.classList.add('d-none');
    fields.unitCostDisplay.value = '0';
    renderAlert('', '');
  }

  function updateActionFields() {
    const action = String(fields.action.value || '');
    const meta = actionMeta[action] || null;
    fields.qty.value = '0';
    if (!meta) {
      fields.qtyLabel.textContent = 'Qty';
      fields.reasonLabel.textContent = 'Alasan';
      fields.reason.innerHTML = '<option value="">Pilih jenis koreksi dulu</option>';
      fields.costWrap.classList.add('d-none');
      fields.unitCostDisplay.value = '0';
      return;
    }
    fields.qtyLabel.textContent = meta.label + ' (' + (state.uomCode || 'Qty') + ')';
    fields.reasonLabel.textContent = meta.reasonLabel;
    fillReasonSelect(fields.reason, meta.reasonCategory, 'other');
    if (action === 'PLUS') {
      fields.costWrap.classList.remove('d-none');
      fields.unitCostDisplay.value = String(Number.isFinite(state.defaultUnitCost) ? state.defaultUnitCost : 0);
    } else {
      fields.costWrap.classList.add('d-none');
      fields.unitCostDisplay.value = '0';
    }
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="quick-adjust"]');
    if (!button) {
      return;
    }
    resetForm();
    state.componentId = Number(button.dataset.componentId || 0);
    state.componentName = String(button.dataset.componentName || '');
    state.locationType = String(button.dataset.locationType || '');
    state.divisionId = Number(button.dataset.divisionId || 0);
    state.selectedLotId = Number(button.dataset.selectedLotId || 0);
    state.lotLabel = String(button.dataset.lotLabel || '');
    state.defaultUnitCost = Number(button.dataset.unitCost || 0);
    state.uomId = Number(button.dataset.uomId || 0);
    state.uomCode = String(button.dataset.uomCode || '');
    fields.componentLabel.textContent = state.componentName || '-';
    fields.contextLabel.textContent = [button.dataset.locationLabel || '-', button.dataset.divisionName || '-', state.lotLabel ? ('Lot ' + state.lotLabel) : '', state.uomCode || '-', button.dataset.adjustmentDate || '-'].filter(Boolean).join('  -  ');
    fields.date.value = String(button.dataset.adjustmentDate || '');
    fields.available.value = String(button.dataset.availableQty || '0') + ' ' + state.uomCode;
    updateActionFields();
    const modal = getModalInstance();
    if (!modal) {
      window.alert('Modal adjustment belum siap. Muat ulang halaman lalu coba lagi.');
      return;
    }
    modal.show();
  });

  fields.action.addEventListener('change', updateActionFields);

  submitBtn.addEventListener('click', async () => {
    if (!state.componentId || !state.uomId || !fields.date.value) {
      renderAlert('danger', 'Konteks adjustment belum lengkap.');
      return;
    }

    const action = String(fields.action.value || '');
    const qty = parseFloat(fields.qty.value || '0') || 0;
    const meta = actionMeta[action] || null;
    if (!meta) {
      renderAlert('danger', 'Pilih dulu salah satu jenis koreksi: spoil, waste, minus, atau plus.');
      return;
    }
    if (qty <= 0) {
      renderAlert('danger', 'Qty koreksi harus lebih dari 0.');
      return;
    }
    const derivedUnitCost = parseFloat(fields.unitCostDisplay.value) || 0;
    if (action === 'PLUS' && derivedUnitCost <= 0) {
      renderAlert('danger', 'HPP / Unit Cost wajib diisi untuk adjustment plus. Masukkan nilai HPP di field yang tersedia.');
      fields.unitCostDisplay.focus();
      return;
    }

    const linePayload = {
      component_id: state.componentId,
      uom_id: state.uomId,
      selected_lot_id: state.selectedLotId > 0 ? state.selectedLotId : '',
      qty_spoil: 0,
      spoil_reason_code: 'other',
      qty_waste: 0,
      waste_reason_code: 'other',
      qty_adjust_pos: 0,
      adjustment_plus_reason_code: 'other',
      unit_cost: 0,
      qty_adjust_neg: 0,
      adjustment_minus_reason_code: 'other',
      note: String(fields.note.value || '')
    };
    if (action === 'SPOIL') {
      linePayload.qty_spoil = qty;
      linePayload.spoil_reason_code = String(fields.reason.value || 'other');
    } else if (action === 'WASTE') {
      linePayload.qty_waste = qty;
      linePayload.waste_reason_code = String(fields.reason.value || 'other');
    } else if (action === 'MINUS') {
      linePayload.qty_adjust_neg = qty;
      linePayload.adjustment_minus_reason_code = String(fields.reason.value || 'other');
    } else if (action === 'PLUS') {
      linePayload.qty_adjust_pos = qty;
      linePayload.adjustment_plus_reason_code = String(fields.reason.value || 'other');
      linePayload.unit_cost = derivedUnitCost;
    }

    const payload = {
      adjustment_date: fields.date.value,
      location_type: state.locationType,
      division_id: state.divisionId || '',
      notes: String(fields.note.value || ''),
      lines: [linePayload]
    };

    submitBtn.disabled = true;
    renderAlert('info', 'Menyimpan adjustment...');
    try {
      const saveResult = await postJson(saveUrl, payload);
      const adjustmentId = Number(saveResult.id || 0);
      if (adjustmentStepUpModalEl && adjustmentStepUpModalEl.parentNode !== document.body) document.body.appendChild(adjustmentStepUpModalEl);
      if (adjustmentStepUpModalEl) adjustmentStepUpModalEl.style.zIndex = '2010';
      const modal = adjustmentStepUpModalEl && window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(adjustmentStepUpModalEl)
        : null;
      if (!(adjustmentId > 0) || !modal) {
        throw new Error('Draft adjustment sudah tersimpan, tetapi verifikasi posting belum siap. Muat ulang halaman lalu post draft tersebut dari menu Adjustment Base/Prepare.');
      }
      pendingAdjustmentPostId = adjustmentId;
      if (adjustmentStepUpPassword) adjustmentStepUpPassword.value = '';
      renderAlert('info', 'Draft adjustment tersimpan. Verifikasi password untuk menerapkan koreksi.');
      modal.show();
      window.setTimeout(() => adjustmentStepUpPassword?.focus(), 150);
    } catch (error) {
      renderAlert('danger', error.message || 'Adjustment gagal diproses.');
    } finally {
      submitBtn.disabled = false;
    }
  });

  function setAdjustmentStepUpDismissDisabled(disabled) {
    adjustmentStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => { button.disabled = disabled; });
  }

  btnAdjustmentStepUpPost?.addEventListener('click', async () => {
    if (adjustmentStepUpSubmitting) return;
    const adjustmentId = pendingAdjustmentPostId;
    try {
      if (!(adjustmentId > 0)) throw new Error('Draft adjustment tidak valid. Tutup modal lalu coba lagi.');
      const password = String(adjustmentStepUpPassword?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi posting adjustment.');
      if (adjustmentStepUpPassword) adjustmentStepUpPassword.value = '';
      adjustmentStepUpSubmitting = true;
      setAdjustmentStepUpDismissDisabled(true);
      btnAdjustmentStepUpPost.disabled = true;
      btnAdjustmentStepUpPost.textContent = 'Memverifikasi...';
      const stepUp = await postJson(componentAdjustmentStepUpUrl, {adjustment_id: adjustmentId, password});
      if (!/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) throw new Error('Bukti verifikasi ulang tidak valid. Coba lagi.');
      await postJson(postBaseUrl + '/' + encodeURIComponent(String(adjustmentId)), {step_up_proof: String(stepUp.step_up_proof)});
      renderAlert('success', 'Adjustment berhasil diposting. Memuat ulang data...');
      window.setTimeout(() => window.location.reload(), 500);
    } catch (error) {
      adjustmentStepUpSubmitting = false;
      setAdjustmentStepUpDismissDisabled(false);
      if (btnAdjustmentStepUpPost) {
        btnAdjustmentStepUpPost.disabled = false;
        btnAdjustmentStepUpPost.textContent = 'Verifikasi & Post';
      }
      renderAlert('danger', error.message || 'Gagal post adjustment. Draft tetap tersimpan.');
    }
  });

  adjustmentStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (adjustmentStepUpSubmitting) return;
    if (adjustmentStepUpPassword) adjustmentStepUpPassword.value = '';
    pendingAdjustmentPostId = 0;
    if (btnAdjustmentStepUpPost) {
      btnAdjustmentStepUpPost.disabled = false;
      btnAdjustmentStepUpPost.textContent = 'Verifikasi & Post';
    }
  });
})();

(() => {
  const modalEl = document.getElementById('componentDailyBatchModal');
  const submitBtn = document.getElementById('qdbSubmitBtn');
  if (!modalEl || !submitBtn) {
    return;
  }

  function getModalInstance() {
    if (!(window.bootstrap && window.bootstrap.Modal)) {
      return null;
    }
    if (modalEl.parentNode !== document.body) document.body.appendChild(modalEl);
    modalEl.style.zIndex = '2000';
    if (batchStepUpModalEl && batchStepUpModalEl.parentNode !== document.body) document.body.appendChild(batchStepUpModalEl);
    if (batchStepUpModalEl) batchStepUpModalEl.style.zIndex = '2010';
    return window.bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  const previewUrl = '<?php echo site_url('production/component-batches/preview'); ?>';
  const saveUrl = '<?php echo site_url('production/component-batches/save'); ?>';
  const componentBatchStepUpUrl = '<?php echo site_url('production/component-batches/step-up/verify'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-batches/post'); ?>';
  const componentBatchCsrfToken = <?php echo json_encode((string)($component_batch_csrf_token ?? ''), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const batchStepUpModalEl = document.getElementById('componentDailyBatchStepUpModal');
  const batchStepUpPassword = document.getElementById('component_daily_batch_step_up_password');
  const btnBatchStepUpPost = document.getElementById('btn-component-daily-batch-step-up-post');
  let pendingBatchPostId = 0;
  let batchStepUpSubmitting = false;
  const alertHost = document.getElementById('componentDailyBatchAlert');
  const state = { componentId: 0, divisionId: 0, locationType: '', uomId: 0, uomCode: '', componentName: '' };
  let currentPreview = null;

  const fields = {
    componentLabel: document.getElementById('qdbComponentLabel'),
    contextLabel: document.getElementById('qdbContextLabel'),
    date: document.getElementById('qdbDate'),
    scalingMode: document.getElementById('qdbScalingMode'),
    batchCount: document.getElementById('qdbBatchCount'),
    referenceLine: document.getElementById('qdbReferenceLine'),
    referenceQty: document.getElementById('qdbReferenceQty'),
    notes: document.getElementById('qdbNotes'),
    previewOutput: document.getElementById('qdbPreviewOutput'),
    previewNote: document.getElementById('qdbPreviewNote'),
    previewUsage: document.getElementById('qdbPreviewUsage'),
    batchCountWrap: document.getElementById('qdbBatchCountWrap'),
    referenceLineWrap: document.getElementById('qdbReferenceLineWrap'),
    referenceQtyWrap: document.getElementById('qdbReferenceQtyWrap')
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    alertHost.innerHTML = message ? '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>' : '';
  }

  function formatQty(value) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Production-Component-Batch-Csrf': componentBatchCsrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(async (response) => {
      const text = await response.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (error) {
        throw new Error('Server tidak mengirim hasil yang dapat dibaca. Data mungkin sudah tersimpan; muat ulang halaman untuk memastikan, lalu coba kembali.');
      }
      if (!response.ok || !json.ok) {
        throw new Error(json.message || 'Permintaan gagal diproses.');
      }
      return json;
    });
  }

  function syncModeUi() {
    const mode = String(fields.scalingMode.value || 'BATCH').toUpperCase();
    const isReference = mode === 'REFERENCE';
    fields.batchCountWrap.classList.toggle('d-none', isReference);
    fields.referenceLineWrap.classList.toggle('d-none', !isReference);
    fields.referenceQtyWrap.classList.toggle('d-none', !isReference);
  }

  function resetPreview(message) {
    currentPreview = null;
    fields.previewOutput.textContent = 'Preview output belum tersedia.';
    fields.previewNote.textContent = message || 'Lengkapi parameter batch untuk memuat preview.';
    fields.previewUsage.innerHTML = '';
  }

  function renderReferenceOptions(rows, selectedValue) {
    const list = Array.isArray(rows) ? rows : [];
    fields.referenceLine.innerHTML = '<option value="">Pilih bahan acuan...</option>' + list.map((row) => {
      return '<option value="' + escapeHtml(row.line_no) + '"' + (String(selectedValue || '') === String(row.line_no) ? ' selected' : '') + '>' +
        escapeHtml((row.label || '-') + '  -  ' + formatQty(row.base_qty || 0) + ' ' + (row.uom_code || '')) +
      '</option>';
    }).join('');
  }

  function renderPreview(preview) {
    currentPreview = preview;
    const component = preview.component || {};
    fields.previewOutput.textContent = 'Output ' + formatQty(preview.output_qty || 0) + ' ' + String(component.uom_code || state.uomCode || '');
    fields.previewNote.textContent = String(preview.scaling_mode || 'BATCH').toUpperCase() === 'REFERENCE'
      ? 'Output dihitung dari bahan acuan aktual.'
      : 'Output dihitung dari kelipatan batch resep dasar.';
    renderReferenceOptions(preview.reference_options || [], (preview.reference || {}).line_no || '');
    const usageLines = (Array.isArray(preview.lines) ? preview.lines : []).filter((line) => {
      const role = String(line.plan_role || '').toUpperCase();
      return role === 'MATERIAL_USAGE' || role === 'COMPONENT_USAGE';
    });
    fields.previewUsage.innerHTML = usageLines.map((line) => {
      return '<span class="badge text-bg-light border">' +
        escapeHtml(line.source_label || '-') + ': ' + escapeHtml(formatQty(line.required_qty || 0)) + ' ' + escapeHtml(line.uom_code || '') +
      '</span>';
    }).join('');
    if (Array.isArray(preview.issues) && preview.issues.length) {
      renderAlert('danger', preview.issues.join(' | '));
    } else {
      renderAlert('', '');
    }
  }

  let previewTimer = 0;
  let previewRequestId = 0;
  function schedulePreview() {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(loadPreview, 350);
  }

  async function loadPreview() {
    const requestId = ++previewRequestId;
    if (!state.componentId || !state.locationType) {
      resetPreview('Konteks batch belum lengkap.');
      return;
    }
    const mode = String(fields.scalingMode.value || 'BATCH').toUpperCase();
    const params = new URLSearchParams({
      component_id: String(state.componentId),
      location_type: String(state.locationType),
      batch_date: String(fields.date.value || ''),
      scaling_mode: mode,
      batch_count: String(fields.batchCount.value || ''),
      reference_line_no: String(fields.referenceLine.value || ''),
      reference_actual_qty: String(fields.referenceQty.value || '')
    });
    if (mode === 'REFERENCE') {
      if (!fields.referenceLine.value) {
        resetPreview('Pilih bahan acuan untuk melihat preview batch.');
        return;
      }
      if (!(parseFloat(fields.referenceQty.value || '0') > 0)) {
        resetPreview('Isi qty aktual bahan acuan terlebih dahulu.');
        return;
      }
    } else if (!(parseFloat(fields.batchCount.value || '0') > 0)) {
      resetPreview('Jumlah batch harus lebih dari 0.');
      return;
    }

    try {
      const response = await fetch(previewUrl + '?' + params.toString(), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });
      const text = await response.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (error) {
        throw new Error('Preview batch tidak dapat dibaca dari server. Muat ulang halaman lalu coba kembali.');
      }
      if (!response.ok || !json.ok) {
        throw new Error(json.message || 'Preview batch gagal dimuat.');
      }
      if (requestId === previewRequestId) renderPreview(json);
    } catch (error) {
      if (requestId !== previewRequestId) return;
      currentPreview = null;
      renderAlert('danger', error.message || 'Preview batch gagal dimuat.');
      resetPreview('Preview batch gagal dimuat.');
    }
  }

  function resetForm() {
    fields.date.value = '';
    fields.scalingMode.value = 'BATCH';
    fields.batchCount.value = '1.00';
    fields.referenceQty.value = '';
    fields.notes.value = '';
    renderReferenceOptions([], '');
    syncModeUi();
    resetPreview('Lengkapi parameter batch untuk memuat preview.');
    renderAlert('', '');
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="quick-batch"]');
    if (!button) {
      return;
    }
    resetForm();
    state.componentId = Number(button.dataset.componentId || 0);
    state.divisionId = Number(button.dataset.divisionId || 0);
    state.locationType = String(button.dataset.locationType || '');
    state.uomId = Number(button.dataset.uomId || 0);
    state.uomCode = String(button.dataset.uomCode || '');
    state.componentName = String(button.dataset.componentName || '');
    fields.componentLabel.textContent = state.componentName || '-';
    fields.contextLabel.textContent = [button.dataset.locationLabel || '-', button.dataset.divisionName || '-', state.uomCode || '-', button.dataset.batchDate || '-'].join('  -  ');
    fields.date.value = String(button.dataset.batchDate || '');
    const modal = getModalInstance();
    if (!modal) {
      window.alert('Modal batch belum siap. Muat ulang halaman lalu coba lagi.');
      return;
    }
    modal.show();
    loadPreview();
  });

  fields.scalingMode.addEventListener('change', () => { syncModeUi(); schedulePreview(); });
  fields.date.addEventListener('change', schedulePreview);
  fields.batchCount.addEventListener('input', schedulePreview);
  fields.batchCount.addEventListener('change', schedulePreview);
  fields.referenceLine.addEventListener('change', schedulePreview);
  fields.referenceQty.addEventListener('input', schedulePreview);
  fields.referenceQty.addEventListener('change', schedulePreview);

  submitBtn.addEventListener('click', async () => {
    if (!state.componentId || !state.divisionId || !state.locationType || !fields.date.value) {
      renderAlert('danger', 'Konteks batch belum lengkap.');
      return;
    }
    if (!currentPreview) {
      renderAlert('danger', 'Preview batch belum siap.');
      return;
    }
    if (currentPreview.summary && currentPreview.summary.has_shortage) {
      renderAlert('danger', 'Batch masih punya shortage dan belum bisa diposting.');
      return;
    }
    const payload = {
      batch_date: String(fields.date.value || ''),
      location_type: state.locationType,
      division_id: state.divisionId,
      component_id: state.componentId,
      output_qty: String(currentPreview.output_qty || ''),
      output_uom_id: String((currentPreview.component || {}).uom_id || state.uomId || ''),
      scaling_mode: String(fields.scalingMode.value || 'BATCH'),
      batch_count: String(fields.batchCount.value || ''),
      reference_line_no: String(fields.referenceLine.value || ''),
      reference_actual_qty: String(fields.referenceQty.value || ''),
      notes: String(fields.notes.value || '')
    };

    submitBtn.disabled = true;
    renderAlert('info', 'Menyimpan batch...');
    try {
      const saveResult = await postJson(saveUrl, payload);
      const batchId = Number(saveResult.id || 0);
      if (batchStepUpModalEl && batchStepUpModalEl.parentNode !== document.body) document.body.appendChild(batchStepUpModalEl);
      if (batchStepUpModalEl) batchStepUpModalEl.style.zIndex = '2010';
      const modal = batchStepUpModalEl && window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(batchStepUpModalEl)
        : null;
      if (!(batchId > 0) || !modal) {
        throw new Error('Draft batch sudah tersimpan, tetapi verifikasi posting belum siap. Muat ulang halaman lalu post draft tersebut dari menu Batch Produksi Base/Prepare.');
      }
      pendingBatchPostId = batchId;
      if (batchStepUpPassword) batchStepUpPassword.value = '';
      renderAlert('info', 'Draft batch tersimpan. Verifikasi password untuk menerapkan produksi.');
      modal.show();
      window.setTimeout(() => batchStepUpPassword?.focus(), 150);
    } catch (error) {
      renderAlert('danger', error.message || 'Batch gagal diproses.');
    } finally {
      submitBtn.disabled = false;
    }
  });

  function setBatchStepUpDismissDisabled(disabled) {
    batchStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => { button.disabled = disabled; });
  }

  btnBatchStepUpPost?.addEventListener('click', async () => {
    if (batchStepUpSubmitting) return;
    const batchId = pendingBatchPostId;
    try {
      if (!(batchId > 0)) throw new Error('Draft batch tidak valid. Tutup modal lalu coba lagi.');
      const password = String(batchStepUpPassword?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi posting batch.');
      if (batchStepUpPassword) batchStepUpPassword.value = '';
      batchStepUpSubmitting = true;
      setBatchStepUpDismissDisabled(true);
      btnBatchStepUpPost.disabled = true;
      btnBatchStepUpPost.textContent = 'Memverifikasi...';
      const stepUp = await postJson(componentBatchStepUpUrl, {batch_id: batchId, password});
      if (!/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) throw new Error('Bukti verifikasi ulang tidak valid. Coba lagi.');
      await postJson(postBaseUrl + '/' + encodeURIComponent(String(batchId)), {step_up_proof: String(stepUp.step_up_proof)});
      renderAlert('success', 'Batch berhasil diposting. Memuat ulang data...');
      window.setTimeout(() => window.location.reload(), 500);
    } catch (error) {
      batchStepUpSubmitting = false;
      setBatchStepUpDismissDisabled(false);
      if (btnBatchStepUpPost) {
        btnBatchStepUpPost.disabled = false;
        btnBatchStepUpPost.textContent = 'Verifikasi & Post';
      }
      renderAlert('danger', error.message || 'Gagal post batch. Draft tetap tersimpan.');
    }
  });

  batchStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (batchStepUpSubmitting) return;
    if (batchStepUpPassword) batchStepUpPassword.value = '';
    pendingBatchPostId = 0;
    if (btnBatchStepUpPost) {
      btnBatchStepUpPost.disabled = false;
      btnBatchStepUpPost.textContent = 'Verifikasi & Post';
    }
  });
})();
</script>
