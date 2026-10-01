<?php
$filters      = $filters      ?? ['period' => 'today', 'date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d'), 'period_label' => 'Hari Ini'];
$chartFilters = $chart_filters ?? ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d'), 'period_label' => 'Bulan Ini'];
$kpi          = $kpi          ?? [];
$trend        = $trend        ?? ['labels' => [], 'sales' => [], 'orders' => [], 'po' => [], 'sr' => []];
$posStatusRows    = is_array($pos_status_rows  ?? null) ? $pos_status_rows  : [];
$posScopeRows     = is_array($pos_scope_rows   ?? null) ? $pos_scope_rows   : [];
$stockBreakdown   = is_array($stock_breakdown  ?? null) ? $stock_breakdown  : ['warehouse' => [], 'division' => [], 'component' => []];
$componentProductionSuggestions = is_array($component_production_suggestions ?? null) ? $component_production_suggestions : ['summary' => [], 'rows' => []];
$stockProductLive = is_array($stock_product_live ?? null) ? $stock_product_live : ['summary' => [], 'rows' => []];
$stagnantLotAnalysis = is_array($stagnant_lot_analysis ?? null) ? $stagnant_lot_analysis : [
    'threshold_days' => 15,
    'material' => ['multi_lot' => [], 'aged_lot' => []],
    'component' => ['multi_lot' => [], 'aged_lot' => []],
    'summary' => [],
];
$adjustmentSummary = is_array($adjustment_summary ?? null) ? $adjustment_summary : [
    'daily' => ['label' => 'Hari Ini', 'warehouse' => ['rows' => [], 'totals' => []], 'division' => ['rows' => [], 'totals' => []], 'component' => ['rows' => [], 'totals' => []]],
    'weekly' => ['label' => 'Minggu Ini', 'warehouse' => ['rows' => [], 'totals' => []], 'division' => ['rows' => [], 'totals' => []], 'component' => ['rows' => [], 'totals' => []]],
    'monthly' => ['label' => 'Bulan Ini', 'warehouse' => ['rows' => [], 'totals' => []], 'division' => ['rows' => [], 'totals' => []], 'component' => ['rows' => [], 'totals' => []]],
];
$topSellingProducts = is_array($top_selling_products ?? null) ? $top_selling_products : [
    'daily' => ['label' => 'Hari Ini', 'groups' => ['FOOD' => ['label' => 'Food', 'rows' => []], 'BEVERAGE' => ['label' => 'Beverage', 'rows' => []]]],
    'weekly' => ['label' => 'Minggu Ini', 'groups' => ['FOOD' => ['label' => 'Food', 'rows' => []], 'BEVERAGE' => ['label' => 'Beverage', 'rows' => []]]],
    'monthly' => ['label' => 'Bulan Ini', 'groups' => ['FOOD' => ['label' => 'Food', 'rows' => []], 'BEVERAGE' => ['label' => 'Beverage', 'rows' => []]]],
];
$criticalStockRows  = is_array($critical_stock_rows   ?? null) ? $critical_stock_rows   : [];
$activeDeficitRows   = is_array($active_deficit_rows    ?? null) ? $active_deficit_rows    : [];
$reconcileMismatch   = is_array($reconcile_mismatch    ?? null) ? $reconcile_mismatch    : ['material' => [], 'component' => [], 'as_of_date' => date('Y-m-d')];
$recentActivity      = is_array($recent_activity        ?? null) ? $recent_activity        : [];
$prodLiveHiddenCats  = is_array($prod_live_hidden_cats  ?? null) ? $prod_live_hidden_cats  : [];

$cur = static fn($v): string => 'Rp ' . number_format((float)$v, 0, ',', '.');
$fmtGap = static function ($v): string {
    $num = (float)$v;
    if (abs($num) <= 0.01) {
        return '0,00';
    }
    return ($num > 0 ? '+' : '') . number_format($num, 2, ',', '.');
};

$statusPalette = [
    'PAID'=>'#2e7d32','SERVED'=>'#2e7d32','READY'=>'#0288d1','IN_KITCHEN'=>'#7b1fa2',
    'CONFIRMED'=>'#1565c0','PENDING'=>'#ef6c00','PAID_PARTIAL'=>'#f9a825','DRAFT'=>'#8d6e63',
    'VOID'=>'#c62828','APPROVED'=>'#00897b','ORDERED'=>'#3949ab','PARTIAL_RECEIVED'=>'#5e35b1',
    'RECEIVED'=>'#43a047','SUBMITTED'=>'#fb8c00','PARTIAL_FULFILLED'=>'#8e24aa','FULFILLED'=>'#2e7d32',
];
$sLabels = []; $sTotals = []; $sColors = [];
foreach ($posStatusRows as $r) {
    $c = strtoupper((string)($r['status'] ?? '-'));
    $sLabels[] = $c; $sTotals[] = (int)($r['total'] ?? 0); $sColors[] = $statusPalette[$c] ?? '#8d6e63';
}

// Group product live rows by division (skip EVENT division)
$productByDivision = [];
$productCatsByDiv  = [];
foreach (($stockProductLive['rows'] ?? []) as $r) {
    $div = (string)($r['division_name'] ?? 'LAIN');
    if (strtoupper($div) === 'EVENT') continue;
    $productByDivision[$div][] = $r;
    $cat = (string)($r['category_name'] ?? '');
    if ($cat !== '' && !in_array($cat, $productCatsByDiv[$div] ?? [], true)) {
        $productCatsByDiv[$div][] = $cat;
    }
}

// Group stock breakdown division by location_name for sub-tabs
$divisionSubTabs = [];
foreach (($stockBreakdown['division'] ?? []) as $r) {
    $loc = (string)($r['location_name'] ?? '-');
    $divisionSubTabs[$loc][] = $r;
}
$componentSubTabs = [];
foreach (($stockBreakdown['component'] ?? []) as $r) {
    $loc = (string)($r['location_name'] ?? '-');
    $componentSubTabs[$loc][] = $r;
}

// Group critical stock rows by division/scope for JS filter
$criticalByDivision = ['ALL' => $criticalStockRows];
foreach ($criticalStockRows as $r) {
    $loc = (string)($r['location_name'] ?? '-');
    $criticalByDivision[$loc][] = $r;
}
$criticalLocations = array_keys(array_diff_key($criticalByDivision, ['ALL' => true]));
?>

<style>
  .fd { display:grid; gap:1rem; }
  .fd-hero { background:linear-gradient(135deg,#f7efe8 0%,#fdf9f6 54%,#fffdfb 100%); border:1px solid rgba(170,95,78,.14); border-radius:24px; padding:1.25rem 1.3rem; box-shadow:0 14px 30px rgba(109,47,30,.08); }
  .fd-kicker { display:inline-flex; align-items:center; gap:.45rem; font-size:.78rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#8f2d23; }
  .fd-title { margin:.45rem 0 .25rem; font-size:1.8rem; font-weight:800; color:#6f2119; }
  .fd-desc { margin:0; color:#7e6761; }
  .fd-card { border:1px solid rgba(170,95,78,.16); border-radius:20px; box-shadow:0 12px 24px rgba(109,47,30,.08); background:#fff; height:100%; }
  .fd-filter { border-radius:18px; border:1px solid rgba(170,95,78,.16); background:rgba(255,255,255,.88); padding:.9rem; }
  .fd-kpi { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:1rem; }
  .fd-kpi-c { padding:1rem 1rem .95rem; }
  .fd-kpi-lbl { color:#8d6a63; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
  .fd-kpi-val { margin-top:.5rem; font-size:1.6rem; line-height:1.1; font-weight:800; color:#6f2119; }
  .fd-kpi-note { margin-top:.35rem; font-size:.82rem; color:#8b7772; }
  .fd-sec-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
  .fd-sec-title { margin:0; font-size:1.08rem; font-weight:800; color:#6f2119; }
  .fd-sec-sub { margin:.18rem 0 0; color:#8b7772; font-size:.85rem; }
  .fd-chart-grid { display:grid; gap:1rem; grid-template-columns:minmax(0,1.5fr) minmax(320px,.8fr); }
  .fd-3col { display:grid; gap:1rem; grid-template-columns:repeat(3,minmax(0,1fr)); }
  .fd-2col { display:grid; gap:1rem; grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr); }
  .fd-2scope { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
  .fd-scope-card { padding:1rem; background:linear-gradient(160deg,rgba(255,247,242,.96),rgba(255,255,255,.98)); }
  .fd-scope-head { display:flex; justify-content:space-between; align-items:center; gap:.75rem; }
  .fd-scope-title { margin:0; font-size:1.15rem; font-weight:800; color:#6f2119; }
  .fd-scope-val { margin-top:.45rem; font-size:1.55rem; font-weight:800; color:#8f2d23; }
  .fd-scope-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.8rem; margin-top:.95rem; }
  .fd-scope-meta-lbl { color:#8b7772; font-size:.74rem; font-weight:700; text-transform:uppercase; }
  .fd-scope-meta-val { margin-top:.22rem; font-weight:800; color:#6f2119; }
  .fd-list { display:grid; gap:.7rem; }
  .fd-item { display:flex; align-items:center; justify-content:space-between; gap:.8rem; border-radius:16px; padding:.9rem 1rem; background:#fffaf7; border:1px solid rgba(170,95,78,.14); }
  .fd-item.minus { background:#fff5f5; border-color:rgba(198,40,40,.18); }
  .fd-item.kritis { background:#fffaf5; border-color:rgba(239,108,0,.18); }
  .fd-item-title { font-weight:800; color:#6f2119; }
  .fd-item-meta { margin-top:.16rem; color:#8b7772; font-size:.84rem; }
  .fd-recon-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
  .fd-recon-card { position:relative; overflow:hidden; padding:1rem; background:linear-gradient(145deg,#fff 0%,#fff9f5 100%); }
  .fd-recon-card::after { content:""; position:absolute; width:150px; height:150px; right:-52px; top:-66px; border-radius:50%; background:rgba(168,35,44,.08); pointer-events:none; }
  .fd-recon-head { position:relative; z-index:1; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:.9rem; }
  .fd-recon-kicker { font-size:.7rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; color:#a65a45; }
  .fd-recon-title { margin:.18rem 0 0; font-size:1.08rem; line-height:1.2; font-weight:900; color:#6f2119; }
  .fd-recon-count { min-width:82px; text-align:right; font-size:2.1rem; line-height:1; font-weight:900; color:#c62828; }
  .fd-recon-count small { display:block; margin-top:.22rem; color:#8b7772; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
  .fd-recon-breakdown { margin-top:.35rem; color:#8b7772; font-size:.7rem; font-weight:800; white-space:nowrap; }
  .fd-recon-locs { position:relative; z-index:1; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-bottom:.85rem; }
  .fd-recon-loc { display:flex; align-items:center; justify-content:space-between; gap:.55rem; padding:.55rem .65rem; border-radius:14px; background:#fff; border:1px solid rgba(170,95,78,.15); color:inherit; text-decoration:none; }
  .fd-recon-loc:hover { border-color:rgba(168,35,44,.36); box-shadow:0 8px 16px rgba(109,47,30,.08); }
  .fd-recon-loc strong { color:#6f2119; font-size:.82rem; line-height:1.15; }
  .fd-recon-loc span { flex-shrink:0; color:#c62828; font-size:.78rem; font-weight:900; }
  .fd-recon-list { position:relative; z-index:1; display:grid; gap:.45rem; }
  .fd-recon-row { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.75rem; padding:.58rem .65rem; border-radius:14px; background:#fffaf7; border:1px solid rgba(170,95,78,.12); color:inherit; text-decoration:none; }
  .fd-recon-row:hover { background:#fff2eb; border-color:rgba(168,35,44,.28); }
  .fd-recon-row-name { font-weight:900; color:#4a2c2a; font-size:.86rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .fd-recon-row-meta { margin-top:.12rem; color:#8b7772; font-size:.74rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .fd-recon-gap { color:#c62828; font-size:.82rem; font-weight:900; white-space:nowrap; }
  .fd-recon-action { position:relative; z-index:1; display:inline-flex; align-items:center; gap:.35rem; margin-top:.8rem; color:#8f2d23; font-size:.8rem; font-weight:900; text-decoration:none; }
  .fd-recon-action:hover { color:#c62828; }
  .fd-pill { display:inline-flex; align-items:center; padding:.3rem .62rem; border-radius:999px; background:#fff0ea; color:#8f2d23; border:1px solid rgba(170,95,78,.16); font-size:.75rem; font-weight:800; }
  .fd-pill.minus { background:#fff0f0; color:#c62828; border-color:rgba(198,40,40,.2); }
  .fd-pill.kritis { background:#fff8f0; color:#e65100; border-color:rgba(239,108,0,.2); }
  .fd-pill.ok { background:#f0fff4; color:#2e7d32; border-color:rgba(46,125,50,.2); }
  .fd-empty { color:#8b7772; text-align:center; padding:1rem; }
  /* Tabs */
  .fd-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.75rem; }
  .fd-tab { padding:.35rem .8rem; border-radius:999px; font-size:.78rem; font-weight:700; cursor:pointer; border:1px solid rgba(170,95,78,.2); background:#fff; color:#8b7772; transition:all .13s; }
  .fd-tab.on { background:#8f2d23; color:#fff; border-color:#8f2d23; }
  .fd-sub-tab { padding:.28rem .65rem; border-radius:999px; font-size:.74rem; font-weight:700; cursor:pointer; border:1px solid rgba(170,95,78,.18); background:#fff; color:#8b7772; transition:all .13s; }
  .fd-sub-tab.on { background:#6f2119; color:#fff; border-color:#6f2119; }
  /* Scrollable list */
  .fd-scroll { max-height:380px; overflow-y:auto; display:grid; gap:.7rem; padding-right:.25rem; scrollbar-width:thin; }
  .fd-scroll::-webkit-scrollbar { width:4px; }
  .fd-scroll::-webkit-scrollbar-thumb { background:rgba(170,95,78,.25); border-radius:4px; }
  /* Production suggestions */
  .fd-production-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.6rem; margin-bottom:.85rem; }
  .fd-production-stat { border:1px solid rgba(170,95,78,.14); border-radius:14px; background:#fffaf7; padding:.65rem .72rem; text-align:center; }
  .fd-production-stat .num { display:block; color:#6f2119; font-size:1.18rem; font-weight:900; line-height:1.1; }
  .fd-production-stat .lbl { display:block; margin-top:.2rem; color:#8b7772; font-size:.68rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }
  .fd-production-list { display:grid; gap:.58rem; }
  .fd-production-row { display:grid; grid-template-columns:minmax(180px,.95fr) minmax(220px,1.4fr) minmax(165px,.8fr) auto; gap:.75rem; align-items:center; border:1px solid rgba(170,95,78,.15); border-radius:16px; background:linear-gradient(135deg,#fffdfb,#fff7f2); padding:.78rem .85rem; }
  .fd-production-row.ready { border-color:rgba(46,125,50,.24); background:linear-gradient(135deg,#fbfffb,#f1fbf2); }
  .fd-production-row.waiting { border-color:rgba(230,129,0,.24); background:linear-gradient(135deg,#fffdf8,#fff6e7); }
  .fd-production-name { color:#5a231d; font-size:.9rem; font-weight:900; }
  .fd-production-meta { margin-top:.16rem; color:#8b7772; font-size:.73rem; line-height:1.35; }
  .fd-production-kicker { display:inline-flex; align-items:center; gap:.28rem; padding:.16rem .45rem; border-radius:999px; background:#fff0ea; color:#8f2d23; font-size:.65rem; font-weight:900; letter-spacing:.035em; text-transform:uppercase; }
  .fd-production-kicker.empty { background:#fff0f0; color:#c62828; }
  .fd-production-ingredient { color:#4a2c2a; font-size:.8rem; font-weight:800; }
  .fd-production-ingredient small { display:block; margin-top:.12rem; color:#8b7772; font-size:.71rem; font-weight:600; }
  .fd-production-state { min-width:0; }
  .fd-production-state strong { display:block; font-size:.79rem; color:#2e7d32; }
  .fd-production-state.waiting strong { color:#b65c00; }
  .fd-production-state span { display:block; margin-top:.13rem; color:#8b7772; font-size:.71rem; line-height:1.3; }
  .fd-production-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.35rem; }
  .fd-production-btn { display:inline-flex; align-items:center; gap:.25rem; border:1px solid rgba(143,45,35,.28); border-radius:9px; background:#fff; color:#8f2d23; padding:.28rem .48rem; font-size:.7rem; font-weight:900; text-decoration:none; white-space:nowrap; }
  .fd-production-btn:hover { background:#8f2d23; border-color:#8f2d23; color:#fff; }
  /* Product stock */
  .fd-prod-row { border-radius:16px; border:1px solid rgba(170,95,78,.14); overflow:hidden; }
  .fd-prod-head { display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding:.85rem 1rem; background:#fffaf7; cursor:pointer; }
  .fd-prod-head:hover { background:#fff3ec; }
  .fd-prod-name { font-weight:800; color:#6f2119; font-size:.95rem; }
  .fd-prod-meta { margin-top:.14rem; color:#8b7772; font-size:.82rem; }
  .fd-prod-right { display:flex; align-items:center; gap:.6rem; flex-shrink:0; }
  .fd-prod-qty { font-weight:800; font-size:1rem; color:#6f2119; text-align:right; }
  .fd-prod-uom { font-size:.74rem; color:#8b7772; }
  .fd-prod-body { display:none; border-top:1px solid rgba(170,95,78,.12); padding:.8rem 1rem; background:#fff; }
  .fd-prod-body.open { display:block; }
  .fd-recipe-row { display:flex; align-items:center; justify-content:space-between; padding:.35rem 0; border-bottom:1px solid rgba(170,95,78,.08); font-size:.84rem; }
  .fd-recipe-row:last-child { border-bottom:0; }
  .fd-recipe-name { font-weight:700; color:#4a2c2a; }
  .fd-recipe-stock { font-weight:800; }
  .fd-recipe-stock.ok { color:#2e7d32; }
  .fd-recipe-stock.bad { color:#c62828; }
  .fd-recipe-expand { border:0; background:#fff7ea; color:#9a5a00; border:1px solid rgba(226,150,39,.25); border-radius:999px; padding:.18rem .52rem; font-size:.68rem; font-weight:800; cursor:pointer; }
  .fd-recipe-expand:hover { background:#ffeccc; }
  .fd-recipe-child-row { display:none; background:#fffaf2; }
  .fd-recipe-child-row.open { display:table-row; }
  .fd-recipe-child-box { border:1px dashed rgba(170,95,78,.22); border-radius:14px; padding:.65rem .75rem; background:linear-gradient(135deg,#fffdf8,#fff7ed); }
  .fd-recipe-child-title { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.45rem; font-size:.76rem; font-weight:900; color:#6f2119; text-transform:uppercase; letter-spacing:.04em; }
  .fd-recipe-child-note { font-size:.72rem; color:#8b7772; text-transform:none; letter-spacing:0; font-weight:700; }
  .fd-prod-summ { display:grid; grid-template-columns:repeat(4,1fr); gap:.6rem; margin-bottom:.9rem; }
  .fd-prod-summ-card { border-radius:14px; padding:.7rem; text-align:center; border:1px solid rgba(170,95,78,.14); }
  .fd-prod-summ-val { font-size:1.5rem; font-weight:800; color:#6f2119; }
  .fd-prod-summ-val.red { color:#c62828; }
  .fd-prod-summ-val.orange { color:#e65100; }
  .fd-prod-summ-val.green { color:#2e7d32; }
  .fd-prod-summ-lbl { font-size:.73rem; font-weight:700; color:#8b7772; text-transform:uppercase; margin-top:.2rem; }
  .fd-adjust-grid { display:grid; gap:1rem; grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr); align-items:stretch; }
  .fd-adjust-panel { display:flex; flex-direction:column; overflow:hidden; min-height:0; }
  .fd-adjust-panel-summary { height:760px; }
  .fd-adjust-panel-list { height:760px; }
  .fd-adjust-period { display:flex; flex-direction:column; flex:1; min-height:0; }
  .fd-adj-content { display:flex; flex-direction:column; flex:1; min-height:0; }
  .fd-adjust-scroll { flex:1; min-height:0; max-height:none; overflow-y:auto; display:grid; gap:.7rem; padding-right:.25rem; scrollbar-width:thin; }
  .fd-adjust-panel-list .fd-adjust-scroll { max-height:620px; }
  .fd-adjust-panel-summary .fd-adjust-scroll { max-height:680px; }
  .fd-adjust-scroll::-webkit-scrollbar { width:4px; }
  .fd-adjust-scroll::-webkit-scrollbar-thumb { background:rgba(170,95,78,.25); border-radius:4px; }
  .fd-adjust-card { border-radius:16px; border:1px solid rgba(170,95,78,.14); background:#fffaf7; padding:.9rem 1rem; }
  .fd-adjust-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; }
  .fd-adjust-title { font-weight:800; color:#6f2119; }
  .fd-adjust-code { margin-top:.12rem; color:#8b7772; font-size:.8rem; }
  .fd-adjust-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; margin-top:.72rem; }
  .fd-adjust-metric { border-radius:12px; border:1px solid rgba(170,95,78,.12); background:#fff; padding:.5rem .6rem; }
  .fd-adjust-metric .lbl { display:block; font-size:.69rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#8b7772; }
  .fd-adjust-metric .val { display:block; margin-top:.18rem; font-weight:800; color:#6f2119; }
  .fd-adjust-metric .val.plus { color:#2e7d32; }
  .fd-adjust-metric .val.minus { color:#c62828; }
  .fd-adjust-detail { margin-top:.72rem; border-top:1px dashed rgba(170,95,78,.18); padding-top:.72rem; }
  .fd-adjust-detail summary { cursor:pointer; list-style:none; font-size:.82rem; font-weight:800; color:#8f2d23; }
  .fd-adjust-detail summary::-webkit-details-marker { display:none; }
  .fd-adjust-detail-list { display:grid; gap:.5rem; margin-top:.65rem; }
  .fd-adjust-detail-item { border-radius:12px; border:1px solid rgba(170,95,78,.12); background:#fff; padding:.6rem .72rem; }
  .fd-adjust-detail-top { display:flex; justify-content:space-between; gap:.75rem; align-items:flex-start; }
  .fd-adjust-detail-meta { color:#8b7772; font-size:.78rem; margin-top:.14rem; }
  .fd-topprod-period-grid { display:grid; gap:1rem; grid-template-columns:repeat(2,minmax(0,1fr)); }
  .fd-topprod-card { padding:1rem; background:linear-gradient(160deg,rgba(255,247,242,.96),rgba(255,255,255,.98)); }
  .fd-topprod-scroll { max-height:820px; overflow-y:auto; padding-right:.25rem; scrollbar-width:thin; }
  .fd-topprod-scroll::-webkit-scrollbar { width:4px; }
  .fd-topprod-scroll::-webkit-scrollbar-thumb { background:rgba(170,95,78,.25); border-radius:4px; }
  .fd-topprod-subtitle { margin:.2rem 0 .45rem; font-size:.8rem; font-weight:800; color:#8f2d23; text-transform:uppercase; letter-spacing:.04em; }
  .fd-topprod-row { display:flex; align-items:flex-start; justify-content:space-between; gap:.7rem; border-radius:14px; padding:.72rem .78rem; background:#fff; border:1px solid rgba(170,95,78,.12); }
  .fd-topprod-rank { width:1.9rem; height:1.9rem; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:#8f2d23; color:#fff; font-size:.76rem; font-weight:800; flex-shrink:0; }
  .fd-topprod-name { font-weight:800; color:#6f2119; }
  .fd-topprod-meta { margin-top:.15rem; color:#8b7772; font-size:.79rem; }
  .fd-topprod-qty { font-weight:800; color:#6f2119; text-align:right; }
  .fd-topprod-net { margin-top:.15rem; color:#2e7d32; font-size:.78rem; font-weight:700; text-align:right; }
  .fd-card-strong { border:2px solid rgba(143,45,35,.24); box-shadow:0 18px 38px rgba(109,47,30,.14); background:linear-gradient(180deg,#fff 0%,#fffaf6 100%); }
  .fd-section-divider { display:flex; align-items:center; gap:.75rem; margin:.2rem 0 -.2rem; color:#8f2d23; font-size:.72rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
  .fd-section-divider::before, .fd-section-divider::after { content:""; height:1px; flex:1; background:linear-gradient(90deg,transparent,rgba(143,45,35,.36),transparent); }
  .fd-lot-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
  .fd-lot-panel { border-radius:18px; border:2px solid rgba(170,95,78,.22); background:linear-gradient(160deg,#fffaf7,#fff); padding:1rem; min-width:0; box-shadow:inset 0 0 0 1px rgba(255,255,255,.8), 0 10px 22px rgba(109,47,30,.08); }
  .fd-lot-panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; margin-bottom:.8rem; }
  .fd-lot-title { margin:0; font-size:1rem; font-weight:900; color:#6f2119; }
  .fd-lot-sub { margin:.12rem 0 0; color:#8b7772; font-size:.78rem; }
  .fd-lot-section { margin-top:1rem; padding-top:.9rem; border-top:1.5px dashed rgba(143,45,35,.24); }
  .fd-lot-section:first-of-type { margin-top:.2rem; padding-top:0; border-top:0; }
  .fd-lot-section-title { display:flex; align-items:center; justify-content:space-between; gap:.6rem; margin-bottom:.45rem; font-size:.76rem; font-weight:900; color:#8f2d23; text-transform:uppercase; letter-spacing:.05em; }
  .fd-lot-list { display:grid; gap:.5rem; max-height:340px; overflow-y:auto; padding:.55rem; border:1px solid rgba(170,95,78,.14); border-radius:16px; background:rgba(255,255,255,.68); scrollbar-width:thin; }
  .fd-lot-list::-webkit-scrollbar { width:4px; }
  .fd-lot-list::-webkit-scrollbar-thumb { background:rgba(170,95,78,.25); border-radius:4px; }
  .fd-lot-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.75rem; align-items:start; border-radius:14px; border:1px solid rgba(170,95,78,.12); background:#fff; padding:.72rem .78rem; color:inherit; text-decoration:none; }
  .fd-lot-row:hover { border-color:rgba(168,35,44,.32); background:#fff8f4; }
  .fd-lot-name { font-weight:900; color:#4a2c2a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .fd-lot-meta { margin-top:.12rem; color:#8b7772; font-size:.76rem; line-height:1.35; }
  .fd-lot-right { text-align:right; white-space:nowrap; }
  .fd-lot-qty { font-weight:900; color:#6f2119; }
  .fd-lot-value { margin-top:.12rem; color:#2e7d32; font-size:.76rem; font-weight:800; }
  .fd-lot-age { display:inline-flex; align-items:center; padding:.18rem .48rem; border-radius:999px; border:1px solid rgba(198,40,40,.18); background:#fff3f3; color:#c62828; font-size:.7rem; font-weight:900; }
  .fd-lot-bucket { display:grid; gap:.55rem; }
  .fd-lot-bucket + .fd-lot-bucket { margin-top:.9rem; padding-top:.85rem; border-top:1px dashed rgba(143,45,35,.18); }
  .fd-lot-bucket-title { display:flex; align-items:center; justify-content:space-between; gap:.6rem; color:#6f2119; font-size:.76rem; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
  .fd-lot-tabs { display:flex; flex-wrap:wrap; gap:.4rem; margin:.15rem 0 .85rem; padding:.45rem; border:1px solid rgba(170,95,78,.14); border-radius:16px; background:#fff; }
  .fd-lot-tab { border:1px solid rgba(170,95,78,.24); background:#fffaf7; color:#8b7772; border-radius:999px; padding:.24rem .62rem; font-size:.72rem; font-weight:900; }
  .fd-lot-tab.on { background:#8f2d23; color:#fff; border-color:#8f2d23; }
  .fd-lot-toggle { width:100%; border:1px solid rgba(170,95,78,.12); cursor:pointer; text-align:left; }
  .fd-lot-toggle .ri-arrow-down-s-line { transition:transform .15s ease; }
  .fd-lot-toggle.open .ri-arrow-down-s-line { transform:rotate(180deg); }
  .fd-lot-detail { display:none; margin:-.2rem 0 .45rem; border:1px solid rgba(170,95,78,.16); border-radius:14px; background:#fffdfb; overflow:hidden; }
  .fd-lot-detail.open { display:block; }
  .fd-lot-detail table { width:100%; border-collapse:collapse; font-size:.76rem; }
  .fd-lot-detail th { background:#fff4ee; color:#8f2d23; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; padding:.48rem .55rem; border-bottom:1px solid rgba(170,95,78,.14); }
  .fd-lot-detail td { padding:.48rem .55rem; border-bottom:1px solid rgba(170,95,78,.08); vertical-align:middle; }
  .fd-lot-detail tr:last-child td { border-bottom:0; }
  .fd-lot-adjust-btn { border:1px solid rgba(143,45,35,.28); background:#fff; color:#8f2d23; border-radius:999px; padding:.16rem .55rem; font-size:.68rem; font-weight:900; }
  .fd-lot-adjust-btn:hover { background:#8f2d23; color:#fff; }
  @media (max-width:1399.98px) {
    .fd-kpi { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .fd-3col { grid-template-columns:1fr; }
    .fd-topprod-period-grid { grid-template-columns:1fr; }
  }
  @media (max-width:991.98px) {
    .fd-chart-grid, .fd-2col, .fd-adjust-grid, .fd-recon-grid, .fd-lot-grid { grid-template-columns:1fr; }
    .fd-production-row { grid-template-columns:1fr; }
    .fd-production-actions { justify-content:flex-start; }
    .fd-2scope { grid-template-columns:1fr; }
    .fd-adjust-panel-summary, .fd-adjust-panel-list { height:auto; }
  }
  @media (max-width:767.98px) { .fd-kpi, .fd-recon-locs, .fd-production-summary { grid-template-columns:1fr; } }
  /* Category filter */
  .fd-cat-cb { width:.85rem; height:.85rem; cursor:pointer; accent-color:#8f2d23; }
  #prodCatFilterBtn.active { background:#8f2d23; color:#fff; border-color:#8f2d23; }
</style>

<div class="fd">
  <!-- Hero -->
  <section class="fd-hero">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
      <div>
        <div class="fd-kicker"><i class="ri-dashboard-line"></i> Ringkasan Finance</div>
        <h1 class="fd-title">Dashboard Operasional</h1>
        <p class="fd-desc">Selamat datang, <strong><?= htmlspecialchars($current_user['username'] ?? '') ?></strong>. Data KPI: <strong><?= htmlspecialchars($filters['period_label']) ?></strong>. Grafik: <strong><?= htmlspecialchars($chartFilters['period_label']) ?></strong>.</p>
      </div>
      <span class="fd-pill">Update <?= date('d-m-Y H:i') ?></span>
    </div>
  </section>

  <!-- Filter -->
  <form method="get" action="<?= base_url('dashboard') ?>" class="fd-filter fd-card">
    <div class="row g-2 align-items-end">
      <div class="col-xl-3 col-md-4">
        <label class="form-label mb-1">Periode KPI</label>
        <select name="period" id="dashPeriod" class="form-select">
          <option value="today"  <?= ($filters['period'] ?? '') === 'today'  ? 'selected' : '' ?>>Hari Ini</option>
          <option value="month"  <?= ($filters['period'] ?? '') === 'month'  ? 'selected' : '' ?>>Bulan Ini</option>
          <option value="7"      <?= ($filters['period'] ?? '') === '7'      ? 'selected' : '' ?>>7 Hari Terakhir</option>
          <option value="30"     <?= ($filters['period'] ?? '') === '30'     ? 'selected' : '' ?>>30 Hari Terakhir</option>
          <option value="custom" <?= ($filters['period'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom</option>
        </select>
      </div>
      <div class="col-xl-3 col-md-4 fd-custom-date <?= ($filters['period'] ?? '') === 'custom' ? '' : 'd-none' ?>">
        <label class="form-label mb-1">Dari</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-xl-3 col-md-4 fd-custom-date <?= ($filters['period'] ?? '') === 'custom' ? '' : 'd-none' ?>">
        <label class="form-label mb-1">Sampai</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-xl-3 col-md-12 d-grid">
        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
      </div>
    </div>
  </form>

  <!-- KPI -->
  <section class="fd-kpi">
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Transaksi POS</div>
      <div class="fd-kpi-val"><?= number_format((int)($kpi['transaction_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fd-kpi-note">Order aktif (non-draft, non-void) pada periode terpilih.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Omzet POS</div>
      <div class="fd-kpi-val"><?= $cur($kpi['sales_total'] ?? 0) ?></div>
      <div class="fd-kpi-note">Gross <?= $cur($kpi['gross_sales_total'] ?? 0) ?> − refund <?= $cur($kpi['refund_total'] ?? 0) ?>.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Stok Kritis</div>
      <div class="fd-kpi-val"><?= number_format((int)($kpi['critical_stock_total'] ?? 0), 0, ',', '.') ?></div>
      <div class="fd-kpi-note">Row stok gudang, divisi, dan component yang rendah atau nol.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Karyawan Hadir</div>
      <div class="fd-kpi-val"><?= number_format((int)($kpi['present_employee_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fd-kpi-note">PRESENT / LATE / HOLIDAY dalam periode aktif.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Nilai PO</div>
      <div class="fd-kpi-val" style="font-size:1.25rem"><?= $cur($kpi['total_nilai_po'] ?? 0) ?></div>
      <div class="fd-kpi-note">Total PO non-void dalam periode terpilih.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Nilai SR</div>
      <div class="fd-kpi-val" style="font-size:1.25rem"><?= $cur($kpi['total_nilai_sr'] ?? 0) ?></div>
      <div class="fd-kpi-note">Estimasi nilai store request (harga katalog) dalam periode.</div>
    </div>
  </section>
  <!-- ===== ALERT: Defisit Stok Aktif ===== -->
  <?php if (!empty($activeDeficitRows)): ?>
  <?php
    $deficitLabel = static function (string $value): string {
        $value = trim(str_replace('_', ' ', $value));
        if ($value === '') {
            return '-';
        }
        if (strtoupper($value) === 'REGULAR') {
            return 'Reguler';
        }
        return ucwords(strtolower($value));
    };
    $buildDeficitGroups = static function (array $rows, string $mode) use ($deficitLabel): array {
        $groups = [];
        foreach ($rows as $row) {
            $domain = strtoupper((string)($row['stock_domain'] ?? 'MATERIAL')) === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL';
            $divisionId = (int)($row['division_id'] ?? 0);
            $divisionName = trim((string)($row['location_name'] ?? ''));
            $locationScope = trim((string)($row['location_scope'] ?? ''));
            $destinationType = trim((string)($row['destination_type'] ?? ''));
            if ($mode === 'division') {
                $groupKey = $divisionId > 0 ? 'division-' . $divisionId : 'unassigned-' . strtoupper($locationScope . '|' . $destinationType);
                $labelSource = $divisionName !== '' ? $divisionName : ($destinationType !== '' ? $destinationType : $locationScope);
                $context = $destinationType !== '' && strcasecmp($destinationType, $labelSource) !== 0 ? $deficitLabel($destinationType) : '';
            } else {
                $locationSource = strtoupper($locationScope) === 'DIVISION' && $destinationType !== '' ? $destinationType : ($locationScope !== '' ? $locationScope : $destinationType);
                $groupKey = 'location-' . strtoupper($locationSource);
                $labelSource = $locationSource;
                $context = strtoupper($locationScope) === 'DIVISION' && $destinationType !== '' ? 'Stok divisi' : '';
            }
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => $deficitLabel($labelSource !== '' ? $labelSource : 'Lokasi belum tercatat'),
                    'division_id' => $divisionId,
                    'contexts' => [],
                    'rows' => ['MATERIAL' => [], 'COMPONENT' => []],
                    'profile_count' => 0,
                    'event_count' => 0,
                    'estimated_total_value' => 0.0,
                    'last_activity_at' => '',
                ];
            }
            if ($context !== '') {
                $groups[$groupKey]['contexts'][strtolower($context)] = $context;
            }
            $groups[$groupKey]['rows'][$domain][] = $row;
            $groups[$groupKey]['profile_count']++;
            $groups[$groupKey]['event_count'] += (int)($row['event_count'] ?? 0);
            $groups[$groupKey]['estimated_total_value'] += (float)($row['estimated_total_value'] ?? 0);
            $activityAt = (string)($row['last_activity_at'] ?? '');
            if ($activityAt > $groups[$groupKey]['last_activity_at']) {
                $groups[$groupKey]['last_activity_at'] = $activityAt;
            }
        }
        uasort($groups, static function (array $left, array $right): int {
            return strcmp((string)$right['last_activity_at'], (string)$left['last_activity_at']);
        });
        return array_values($groups);
    };
    $deficitRowMarkup = static function (array $row): string {
        $detailUrl = site_url('inventory/stock/deficits/detail/' . (int)($row['id'] ?? 0));
        $name = html_escape((string)($row['inventory_name'] ?? '-'));
        $profile = trim((string)($row['profile_label'] ?? ''));
        $profileKey = trim((string)($row['profile_key'] ?? ''));
        $location = trim((string)($row['location_name'] ?? ''));
        $metaParts = [];
        if ($profile !== '') {
            $metaParts[] = html_escape($profile);
        }
        if ($profileKey !== '') {
            // A deficit is tied to the exact purchase profile, not only the item name.
            $metaParts[] = 'Profil #' . html_escape(substr($profileKey, 0, 8));
        }
        if ($location !== '') {
            $metaParts[] = html_escape($location);
        }
        $meta = !empty($metaParts) ? implode(' | ', $metaParts) : 'Profil atau lot belum tercatat';
        $qty = number_format((float)($row['qty_remaining'] ?? 0), 2, ',', '.');
        $uom = html_escape((string)($row['uom_code'] ?? ''));
        $events = number_format((int)($row['event_count'] ?? 0), 0, ',', '.');
        return '<a class="fd-deficit-row text-decoration-none" href="' . html_escape($detailUrl) . '"><div class="fd-deficit-row-main"><div class="fd-item-title" style="color:#c62828;">' . $name . '</div><div class="fd-item-meta">' . $meta . '</div></div><div class="fd-deficit-row-qty"><span class="fd-pill minus">' . $qty . ' ' . $uom . '</span><div class="fd-item-meta">' . $events . ' kejadian</div></div></a>';
    };
    $renderDeficitGroup = static function (array $group, string $mode) use ($deficitRowMarkup): string {
        $tabKey = $mode . '-' . substr(md5((string)($group['key'] ?? '')), 0, 12);
        $materialRows = is_array($group['rows']['MATERIAL'] ?? null) ? $group['rows']['MATERIAL'] : [];
        $componentRows = is_array($group['rows']['COMPONENT'] ?? null) ? $group['rows']['COMPONENT'] : [];
        $listUrl = site_url('inventory/stock/deficits');
        if ($mode === 'division' && (int)($group['division_id'] ?? 0) > 0) {
            $listUrl .= '?division_id=' . (int)$group['division_id'];
        }
        $contexts = is_array($group['contexts'] ?? null) ? array_values($group['contexts']) : [];
        $contextText = !empty($contexts) ? implode(' | ', $contexts) : '';
        ob_start();
        ?>
        <article class="fd-deficit-zone">
          <div class="fd-deficit-zone-head">
            <div>
              <div class="fd-deficit-zone-title"><?= html_escape((string)($group['label'] ?? '-')) ?></div>
              <div class="fd-deficit-group-meta"><?= html_escape($contextText) ?><?= $contextText !== '' ? ' | ' : '' ?><?= number_format((int)($group['profile_count'] ?? 0), 0, ',', '.') ?> profil | <?= number_format((int)($group['event_count'] ?? 0), 0, ',', '.') ?> kejadian</div>
            </div>
            <div class="fd-deficit-zone-value">
              <strong>Rp <?= number_format((float)($group['estimated_total_value'] ?? 0), 0, ',', '.') ?></strong>
              <a href="<?= html_escape($listUrl) ?>">Lihat daftar</a>
            </div>
          </div>
          <ul class="nav nav-pills gap-2 mb-2 fd-deficit-domain-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= $tabKey ?>-material" type="button">Bahan Baku <span><?= count($materialRows) ?></span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= $tabKey ?>-component" type="button">Component <span><?= count($componentRows) ?></span></button></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="<?= $tabKey ?>-material"><div class="fd-deficit-row-list"><?php if (empty($materialRows)): ?><div class="fd-deficit-empty">Tidak ada defisit bahan baku pada kelompok ini.</div><?php else: foreach ($materialRows as $row): echo $deficitRowMarkup($row); endforeach; endif; ?></div></div>
            <div class="tab-pane fade" id="<?= $tabKey ?>-component"><div class="fd-deficit-row-list"><?php if (empty($componentRows)): ?><div class="fd-deficit-empty">Tidak ada defisit component pada kelompok ini.</div><?php else: foreach ($componentRows as $row): echo $deficitRowMarkup($row); endforeach; endif; ?></div></div>
          </div>
        </article>
        <?php
        return (string)ob_get_clean();
    };
    $divisionDeficitGroups = $buildDeficitGroups($activeDeficitRows, 'division');
    $locationDeficitGroups = $buildDeficitGroups($activeDeficitRows, 'location');
  ?>
  <section id="activeDeficitAlert" style="border-radius:20px;border:1.5px solid rgba(198,40,40,.35);background:linear-gradient(135deg,#fff5f5 0%,#fff9f9 100%);padding:1.15rem 1.3rem;box-shadow:0 8px 24px rgba(198,40,40,.10);">
    <div class="fd-sec-head" style="margin-bottom:.75rem;">
      <div>
        <h2 class="fd-sec-title" style="color:#c62828;display:flex;align-items:center;gap:.5rem;"><i class="ri-error-warning-fill" style="color:#c62828;"></i>Defisit Stok Aktif</h2>
        <p class="fd-sec-sub" style="color:#8b2020;">Pilih tab divisi atau lokasi, lalu lihat bahan baku dan component pada kelompok tersebut. Defisit dibaca per area, UOM, dan profil pembelian. Jadi stok dengan nama barang sama tetapi profil berbeda tidak otomatis menutup defisit. Saldo lot tidak dibuat minus; kekurangan tetap ditampilkan sampai sumber barangnya ditelusuri dan ditutup dengan cara yang benar.</p>
      </div>
      <a class="btn btn-sm btn-outline-danger" href="<?= site_url('inventory/stock/deficits') ?>" style="border-radius:12px;white-space:nowrap;">Buka Defisit Stok</a>
    </div>
    <style>
      .fd-deficit-tabs .nav-link{border:1px solid rgba(198,40,40,.25);border-radius:999px;color:#8b2020;font-size:.78rem;font-weight:800;padding:.34rem .72rem;background:#fff9f8}.fd-deficit-tabs .nav-link.active{background:#b51d31;color:#fff;border-color:#b51d31}.fd-deficit-zone{border:1px solid #f1d7d4;border-radius:14px;background:#fff;padding:.75rem;box-shadow:0 4px 14px rgba(121,37,31,.04)}.fd-deficit-zone+.fd-deficit-zone{margin-top:.65rem}.fd-deficit-zone-head{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;margin-bottom:.65rem}.fd-deficit-zone-title{font-size:.88rem;font-weight:900;color:#7e1e20;text-transform:uppercase}.fd-deficit-group-meta{font-size:.72rem;color:#8e6a66}.fd-deficit-zone-value{text-align:right;font-size:.78rem;color:#b42318}.fd-deficit-zone-value strong{display:block;font-size:.84rem}.fd-deficit-zone-value a{font-size:.72rem;color:#8b2020}.fd-deficit-domain-tabs .nav-link{border:1px solid #efd3cf;border-radius:9px;background:#fffaf9;color:#8b2020;font-size:.74rem;font-weight:800;padding:.3rem .62rem}.fd-deficit-domain-tabs .nav-link.active{background:#fee9e6;color:#a71928;border-color:#e8aaa2}.fd-deficit-domain-tabs .nav-link span{display:inline-flex;min-width:18px;justify-content:center;margin-left:.18rem;padding:0 .25rem;border-radius:999px;background:rgba(139,32,32,.10);font-size:.66rem}.fd-deficit-row-list{max-height:190px;overflow:auto;border:1px solid #f5e1de;border-radius:10px}.fd-deficit-row{display:flex;justify-content:space-between;gap:.75rem;align-items:center;padding:.55rem .65rem;color:inherit;background:#fff}.fd-deficit-row+.fd-deficit-row{border-top:1px solid #f5e7e4}.fd-deficit-row:hover{background:#fff8f7}.fd-deficit-row-main{min-width:0}.fd-deficit-row-qty{text-align:right;white-space:nowrap}.fd-deficit-empty{padding:.85rem;color:#966f6a;text-align:center;font-size:.78rem}@media (max-width:640px){.fd-deficit-zone-head,.fd-deficit-row{align-items:flex-start}.fd-deficit-zone-value{white-space:nowrap}.fd-deficit-row-qty{font-size:.76rem}}
    </style>
    <ul class="nav nav-pills gap-2 mb-2 fd-deficit-tabs" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#deficitByDivision" type="button">Divisi</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#deficitByLocation" type="button">Lokasi</button></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="deficitByDivision"><?php if (empty($divisionDeficitGroups)): ?><div class="fd-deficit-empty">Belum ada defisit aktif yang terkait divisi.</div><?php else: foreach ($divisionDeficitGroups as $group): echo $renderDeficitGroup($group, 'division'); endforeach; endif; ?></div>
      <div class="tab-pane fade" id="deficitByLocation"><?php if (empty($locationDeficitGroups)): ?><div class="fd-deficit-empty">Belum ada defisit aktif pada lokasi yang dapat diringkas.</div><?php else: foreach ($locationDeficitGroups as $group): echo $renderDeficitGroup($group, 'location'); endforeach; endif; ?></div>
    </div>
    <div class="mt-2" style="font-size:.78rem;color:#8b2020;"><i class="ri-information-line"></i> Klik satu barang untuk melihat profil, sumber defisit, stok aktif, lot, dan pilihan recon yang tepat. Riwayat stok minus bulanan lama tidak dipakai sebagai indikator defisit aktif.</div>
  </section>
  <?php endif; ?>

  <!-- Modal: Adjustment Lot dari Analisa FIFO -->
  <div class="modal fade" id="lotAdjModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
      <div class="modal-content" style="border:0;border-radius:22px;overflow:hidden;box-shadow:0 24px 60px -28px rgba(120,0,0,.55)">
        <div class="modal-header" style="background:linear-gradient(135deg,#fff7ef 0%,#fffdfb 100%);border-bottom:1px solid #f3d5c8;padding:.9rem 1.1rem">
          <div>
            <div style="font-size:.66rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#8f2d23">
              <i class="ri ri-stack-line me-1"></i>Adjustment Lot FIFO
            </div>
            <h5 class="modal-title mb-0" id="lotAdjTitle" style="color:#5d160d;font-size:.98rem">-</h5>
            <div class="small" id="lotAdjSub" style="color:#8b7772;font-size:.76rem">-</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:1rem 1.1rem">
          <div id="lotAdjAlert" class="mb-3"></div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Tanggal Recon</label>
              <input type="date" class="form-control form-control-sm" id="lotAdjDate" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Stok Sistem</label>
              <input type="text" class="form-control form-control-sm" id="lotAdjSystem" readonly>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Stok Fisik Benar</label>
              <input type="number" min="0" step="0.0001" class="form-control form-control-sm" id="lotAdjPhysical" required>
              <div class="form-text" id="lotAdjDiffText" style="font-size:.7rem"></div>
            </div>
            <div class="col-6">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Jenis Adjustment</label>
              <select class="form-select form-select-sm" id="lotAdjType"></select>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Alasan</label>
            <select class="form-select form-select-sm" id="lotAdjReason"></select>
          </div>
          <div>
            <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Catatan</label>
            <textarea class="form-control form-control-sm" id="lotAdjNote" rows="2" placeholder="Catatan adjustment lot (opsional)"></textarea>
          </div>
        </div>
        <div class="modal-footer" style="background:#fffaf6;border-top:1px solid #f3d5c8;padding:.7rem 1.1rem">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="button" class="btn btn-sm btn-danger" id="lotAdjSubmitBtn">
            <i class="ri ri-upload-2-line me-1"></i>Simpan &amp; Posting
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Reconcile Mismatch -->
  <?php
  $materialRecon = is_array($reconcileMismatch['material'] ?? null) ? $reconcileMismatch['material'] : [];
  $componentRecon = is_array($reconcileMismatch['component'] ?? null) ? $reconcileMismatch['component'] : [];
  $reconCards = [
      [
          'key' => 'material',
          'icon' => 'ri-seedling-line',
          'kicker' => 'Bahan baku',
          'title' => 'Mismatch Stok Divisi',
          'desc' => 'Reconcile bahan baku per divisi dan lokasi.',
          'data' => $materialRecon,
      ],
      [
          'key' => 'component',
          'icon' => 'ri-stack-line',
          'kicker' => 'Component',
          'title' => 'Mismatch Component',
          'desc' => 'Reconcile component per divisi dan lokasi.',
          'data' => $componentRecon,
      ],
  ];
  ?>
  <section class="fd-recon-grid">
    <?php foreach ($reconCards as $card): ?>
      <?php
      $block = is_array($card['data'] ?? null) ? $card['data'] : [];
      $total = (int)($block['total'] ?? 0);
      $locations = is_array($block['locations'] ?? null) ? $block['locations'] : [];
      $rows = is_array($block['rows'] ?? null) ? $block['rows'] : [];
      $mainUrl = (string)($block['url'] ?? '#');
      $qtyMismatchCount = (int)($block['qty_mismatch_count'] ?? 0);
      $valueMismatchCount = (int)($block['value_mismatch_count'] ?? 0);
      $isClear = $card['key'] === 'component'
          ? $qtyMismatchCount <= 0 && $valueMismatchCount <= 0
          : $total <= 0;
      ?>
      <article class="fd-card fd-recon-card">
        <div class="fd-recon-head">
          <div>
            <div class="fd-recon-kicker"><i class="<?= htmlspecialchars($card['icon']) ?>"></i> <?= htmlspecialchars($card['kicker']) ?></div>
            <h2 class="fd-recon-title"><?= htmlspecialchars($card['title']) ?></h2>
            <p class="fd-sec-sub mb-0"><?= htmlspecialchars($card['desc']) ?></p>
          </div>
          <div class="fd-recon-count">
            <?= number_format($total, 0, ',', '.') ?>
            <small>Mismatch</small>
            <?php if ($card['key'] === 'component'): ?>
              <div class="fd-recon-breakdown">Qty <?= number_format($qtyMismatchCount, 0, ',', '.') ?> &middot; Nilai FIFO <?= number_format($valueMismatchCount, 0, ',', '.') ?></div>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($block['error'])): ?>
          <div class="fd-empty" style="position:relative;z-index:1;text-align:left;color:#c62828;background:#fff5f5;border:1px solid rgba(198,40,40,.18);border-radius:14px;">
            <?= htmlspecialchars((string)$block['error']) ?>
          </div>
        <?php elseif ($isClear): ?>
          <div class="fd-item" style="position:relative;z-index:1;background:#f4fff6;border-color:rgba(46,125,50,.18);">
            <div>
              <div class="fd-item-title" style="color:#2e7d32;">Clear</div>
              <div class="fd-item-meta">Tidak ada mismatch terdeteksi hari ini.</div>
            </div>
            <span class="fd-pill ok">Aman</span>
          </div>
        <?php else: ?>
          <div class="fd-recon-locs">
            <?php foreach ($locations as $loc): ?>
              <a class="fd-recon-loc" href="<?= htmlspecialchars((string)($loc['url'] ?? $mainUrl)) ?>">
                <strong><?= htmlspecialchars((string)($loc['label'] ?? '-')) ?></strong>
                <span><?= number_format((int)($loc['total'] ?? 0), 0, ',', '.') ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="fd-recon-list">
            <?php foreach ($rows as $row): ?>
              <?php
              $gapType = (string)($row['gap_type'] ?? 'qty');
              $gapText = $gapType === 'currency'
                  ? 'Rp ' . number_format((float)($row['gap_value'] ?? 0), 0, ',', '.')
                  : $fmtGap($row['gap'] ?? 0);
              $reason = trim((string)($row['reason'] ?? ''));
              ?>
              <a class="fd-recon-row" href="<?= htmlspecialchars((string)($row['url'] ?? $mainUrl)) ?>">
                <div style="min-width:0">
                  <div class="fd-recon-row-name"><?= htmlspecialchars((string)($row['name'] ?? '-')) ?></div>
                  <div class="fd-recon-row-meta">
                    <?= htmlspecialchars((string)($row['location'] ?? '-')) ?>
                    <?php if (!empty($row['code'])): ?> &middot; <?= htmlspecialchars((string)$row['code']) ?><?php endif; ?>
                    <?php if ($reason !== ''): ?> &middot; <?= htmlspecialchars($reason) ?><?php endif; ?>
                  </div>
                </div>
                <div class="fd-recon-gap"><?= htmlspecialchars($gapText) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <a class="fd-recon-action" href="<?= htmlspecialchars($mainUrl) ?>">
          Buka halaman reconcile <i class="ri-arrow-right-line"></i>
        </a>
      </article>
    <?php endforeach; ?>
  </section>

  <!-- Analisa & Saran Produksi Component -->
  <section class="fd-card p-3">
    <?php
    $productionSummary = (array)($componentProductionSuggestions['summary'] ?? []);
    $productionRows = (array)($componentProductionSuggestions['rows'] ?? []);
    $productionTotalRows = (int)($componentProductionSuggestions['total_rows'] ?? count($productionRows));
    $productionSuggestionDivisions = [];
    foreach ($productionRows as $productionRow) {
        $divisionId = (int)($productionRow['division_id'] ?? 0);
        $divisionKey = $divisionId > 0 ? 'division-' . $divisionId : 'division-other';
        if (!isset($productionSuggestionDivisions[$divisionKey])) {
            $productionSuggestionDivisions[$divisionKey] = [
                'label' => trim((string)($productionRow['division_name'] ?? '')) ?: 'Lainnya',
                'count' => 0,
            ];
        }
        $productionSuggestionDivisions[$divisionKey]['count']++;
    }
    uasort($productionSuggestionDivisions, static function (array $left, array $right): int {
        return strnatcasecmp((string)$left['label'], (string)$right['label']);
    });
    $formatProductionQty = static function ($qty): string {
        $qty = (float)$qty;
        return abs($qty - round($qty)) < 0.0001
            ? number_format($qty, 0, ',', '.')
            : number_format($qty, 2, ',', '.');
    };
    ?>
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title"><i class="ri-restaurant-2-line" style="color:#8f2d23"></i> Analisa &amp; Saran Produksi</h2>
        <p class="fd-sec-sub">Mendeteksi base atau prepare yang kosong/kritis saat bahan resepnya masih tersedia banyak di lokasi formula yang sama.</p>
      </div>
      <a class="fd-production-btn" href="<?= site_url('dashboard/production-suggestions') ?>">
        Analisa lengkap <i class="ri-arrow-right-line"></i>
      </a>
    </div>

    <div class="fd-production-summary">
      <div class="fd-production-stat"><span class="num" style="color:#c62828"><?= number_format((int)($productionSummary['empty_component_count'] ?? 0), 0, ',', '.') ?></span><span class="lbl">Component kosong</span></div>
      <div class="fd-production-stat"><span class="num" style="color:#e65100"><?= number_format((int)($productionSummary['critical_component_count'] ?? 0), 0, ',', '.') ?></span><span class="lbl">Di bawah minimum</span></div>
      <div class="fd-production-stat"><span class="num" style="color:#2e7d32"><?= number_format((int)($productionSummary['ready_count'] ?? 0), 0, ',', '.') ?></span><span class="lbl">Siap diproses</span></div>
      <div class="fd-production-stat"><span class="num"><?= number_format((int)($productionSummary['waiting_count'] ?? 0), 0, ',', '.') ?></span><span class="lbl">Butuh bahan lain</span></div>
    </div>

    <?php if (empty($productionRows)): ?>
      <div class="fd-empty" style="padding:.65rem 0 .2rem">
        Belum ada saran produksi. Component kosong/kritis akan muncul di sini bila ada bahan resep yang cukup untuk minimal dua batch pada lokasi formula yang sama.
      </div>
    <?php else: ?>
      <div class="fd-tabs" id="productionSuggestionTabs" style="margin:.78rem 0 .65rem">
        <button type="button" class="fd-tab on" data-production-suggestion-tab="ALL" aria-pressed="true">
          Semua <span>(<?= count($productionRows) ?>)</span>
        </button>
        <?php foreach ($productionSuggestionDivisions as $divisionKey => $divisionSummary): ?>
          <button type="button" class="fd-tab" data-production-suggestion-tab="<?= htmlspecialchars((string)$divisionKey, ENT_QUOTES) ?>" aria-pressed="false">
            <?= htmlspecialchars((string)$divisionSummary['label']) ?> <span>(<?= (int)$divisionSummary['count'] ?>)</span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="fd-production-list">
        <?php foreach ($productionRows as $row): ?>
          <?php
          $isReady = (string)($row['production_state'] ?? '') === 'READY';
          $piledIngredients = (array)($row['piled_ingredients'] ?? []);
          $leadIngredient = $piledIngredients[0] ?? [];
          $blockingIngredients = (array)($row['blocking_ingredients'] ?? []);
          $blockingNames = array_values(array_filter(array_map(static function (array $ingredient): string {
              return trim((string)($ingredient['name'] ?? ''));
          }, $blockingIngredients)));
          $batchUrl = site_url('production/component-batches')
              . '?q=' . rawurlencode((string)($row['component_name'] ?? ''))
              . '&division_id=' . (int)($row['division_id'] ?? 0);
           $formulaUrl = site_url('production/component-formulas/detail/' . (int)($row['component_id'] ?? 0));
           $divisionId = (int)($row['division_id'] ?? 0);
           $divisionTabKey = $divisionId > 0 ? 'division-' . $divisionId : 'division-other';
           ?>
          <article class="fd-production-row <?= $isReady ? 'ready' : 'waiting' ?>" data-production-suggestion-division="<?= htmlspecialchars($divisionTabKey, ENT_QUOTES) ?>">
            <div>
              <span class="fd-production-kicker <?= (string)($row['stock_state'] ?? '') === 'EMPTY' ? 'empty' : '' ?>">
                <?= htmlspecialchars((string)($row['component_type'] ?? 'COMPONENT')) ?>
                &middot; <?= (string)($row['stock_state'] ?? '') === 'EMPTY' ? 'Kosong' : 'Kritis' ?>
              </span>
              <div class="fd-production-name" style="margin-top:.32rem"><?= htmlspecialchars((string)($row['component_name'] ?? '-')) ?></div>
              <div class="fd-production-meta">
                <?= htmlspecialchars((string)($row['division_name'] ?? '-')) ?> &middot; <?= htmlspecialchars((string)($row['location_label'] ?? '-')) ?><br>
                Stok component <?= $formatProductionQty($row['current_qty'] ?? 0) ?> <?= htmlspecialchars((string)($row['uom_code'] ?? '')) ?>
                <?php if ((float)($row['min_stock'] ?? 0) > 0): ?> / minimum <?= $formatProductionQty($row['min_stock'] ?? 0) ?><?php endif; ?>
              </div>
            </div>

            <div>
              <div class="fd-production-ingredient">
                Bahan tersedia: <?= htmlspecialchars((string)($leadIngredient['name'] ?? '-')) ?>
                <small>
                  <?= $formatProductionQty($leadIngredient['stock_qty'] ?? 0) ?> <?= htmlspecialchars((string)($leadIngredient['uom_code'] ?? '')) ?> tersedia
                  &middot; cukup sampai <?= number_format((int)($leadIngredient['available_batches'] ?? 0), 0, ',', '.') ?> batch
                  <?php if (count($piledIngredients) > 1): ?> &middot; +<?= count($piledIngredients) - 1 ?> bahan lain<?php endif; ?>
                </small>
              </div>
            </div>

            <div class="fd-production-state <?= $isReady ? '' : 'waiting' ?>">
              <?php if ($isReady): ?>
                <strong>Saran: proses <?= number_format((int)($row['recommended_batches'] ?? 1), 0, ',', '.') ?> batch dulu</strong>
                <span>Semua bahan resep tersedia. Kapasitas bahan saat ini sampai <?= number_format((int)($row['max_possible_batches'] ?? 0), 0, ',', '.') ?> batch.</span>
              <?php else: ?>
                <strong>Belum siap diproses</strong>
                <span>Bahan menumpuk, tetapi masih menunggu <?= htmlspecialchars(implode(', ', array_slice($blockingNames, 0, 2)) ?: 'bahan pendukung') ?>.</span>
              <?php endif; ?>
            </div>

            <div class="fd-production-actions">
              <a class="fd-production-btn" href="<?= htmlspecialchars($formulaUrl) ?>">Formula</a>
              <a class="fd-production-btn" href="<?= htmlspecialchars($batchUrl) ?>">Batch</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($productionTotalRows > count($productionRows)): ?>
        <div class="fd-production-meta" style="margin-top:.65rem">Menampilkan <?= count($productionRows) ?> dari <?= $productionTotalRows ?> saran. Buka Analisa lengkap untuk melihat semuanya.</div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Stok Produk (Live POS) -->
  <section class="fd-card p-3">
    <?php
    $summ = $stockProductLive['summary'] ?? [];
    $out  = (int)($summ['out']       ?? 0);
    $lim  = (int)($summ['limited']   ?? 0);
    $avl  = (int)($summ['available'] ?? 0);
    $tot  = (int)($summ['total']     ?? 0);
    ?>
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Stok Produk Live POS</h2>
        <p class="fd-sec-sub">Ketersediaan produk berdasarkan stok bahan baku real-time. Klik produk untuk lihat breakdown resep &amp; bottleneck.</p>
      </div>
      <span class="fd-pill"><?= $tot ?> produk</span>
    </div>
    <div class="fd-prod-summ">
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val red"><?= $out ?></div><div class="fd-prod-summ-lbl">Habis (OUT)</div></div>
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val orange"><?= $lim ?></div><div class="fd-prod-summ-lbl">Terbatas (LIMITED)</div></div>
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val"><?= $tot ?></div><div class="fd-prod-summ-lbl">Total Dipantau</div></div>
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val green"><?= $avl ?></div><div class="fd-prod-summ-lbl">Aman</div></div>
    </div>

    <?php if (empty($productByDivision)): ?>
      <div class="fd-empty">Data ketersediaan produk belum tersedia.</div>
    <?php else: ?>
      <div class="d-flex align-items-center gap-2" style="margin-bottom:.75rem">
        <div class="fd-tabs mb-0 flex-grow-1" id="prodDivTabs" style="margin-bottom:0">
          <?php $first = true; foreach (array_keys($productByDivision) as $divName): ?>
            <?php
            $divRows = $productByDivision[$divName];
            $cntOut  = count(array_filter($divRows, fn($r) => strtoupper($r['availability_status']) === 'OUT'));
            $cntLim  = count(array_filter($divRows, fn($r) => strtoupper($r['availability_status']) === 'LIMITED'));
            ?>
            <button class="fd-tab <?= $first ? 'on' : '' ?>" data-pdiv="<?= htmlspecialchars($divName) ?>">
              <?= htmlspecialchars($divName) ?>
              <?php if ($cntOut > 0): ?><span style="color:#c62828">(<?= $cntOut ?> habis)</span><?php elseif ($cntLim > 0): ?><span style="color:#e65100">(<?= $cntLim ?> terbatas)</span><?php endif; ?>
            </button>
            <?php $first = false; endforeach; ?>
        </div>
        <button type="button" id="prodCatFilterBtn" title="Filter Kategori"
                style="flex-shrink:0;padding:.28rem .7rem;border-radius:999px;font-size:.74rem;font-weight:700;cursor:pointer;border:1px solid rgba(170,95,78,.2);background:#fff;color:#8b7772;transition:all .13s">
          <i class="ri ri-filter-3-line"></i> Kategori
        </button>
      </div>

      <?php foreach ($productByDivision as $divName => $_ignored): ?>
        <?php $cats = $productCatsByDiv[$divName] ?? []; if (empty($cats)) continue; ?>
        <div class="fd-cat-filter-wrap" data-pdiv-filter="<?= htmlspecialchars($divName) ?>" style="display:none;margin-bottom:.75rem">
          <div style="background:#fff8f5;border:1px solid rgba(170,95,78,.18);border-radius:14px;padding:.7rem 1rem">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span style="font-size:.73rem;font-weight:800;color:#6f2119;text-transform:uppercase;letter-spacing:.05em">
                Kategori — <?= htmlspecialchars($divName) ?>
              </span>
              <div class="d-flex align-items-center gap-3">
                <button type="button" onclick="fdCatAll('<?= htmlspecialchars($divName, ENT_QUOTES) ?>',true)"
                        style="font-size:.72rem;font-weight:700;color:#2e7d32;background:none;border:none;cursor:pointer;padding:0">Pilih Semua</button>
                <button type="button" onclick="fdCatAll('<?= htmlspecialchars($divName, ENT_QUOTES) ?>',false)"
                        style="font-size:.72rem;font-weight:700;color:#8b7772;background:none;border:none;cursor:pointer;padding:0">Hapus Semua</button>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3" data-pdiv-checks="<?= htmlspecialchars($divName) ?>">
              <?php
              $hiddenThisDiv = $prodLiveHiddenCats[$divName] ?? [];
              foreach ($cats as $cat):
                  $isChecked = !in_array($cat, $hiddenThisDiv, true);
              ?>
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.74rem;cursor:pointer;background:#fff;border:1px solid rgba(170,95,78,.18);border-radius:999px;padding:.22rem .6rem;user-select:none">
                  <input type="checkbox" class="fd-cat-cb" <?= $isChecked ? 'checked' : '' ?>
                         data-div="<?= htmlspecialchars($divName, ENT_QUOTES) ?>"
                         data-cat="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"
                         onchange="fdCatFilter('<?= htmlspecialchars($divName, ENT_QUOTES) ?>')">
                  <?= htmlspecialchars($cat) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="d-flex align-items-center gap-3">
              <button type="button"
                      onclick="fdCatSave('<?= htmlspecialchars($divName, ENT_QUOTES) ?>', this)"
                      style="padding:.3rem .9rem;border-radius:999px;font-size:.74rem;font-weight:800;cursor:pointer;border:1px solid #8f2d23;background:#8f2d23;color:#fff">
                <i class="ri ri-save-line"></i> Simpan Pengaturan
              </button>
              <span class="fd-cat-save-status" style="font-size:.72rem;font-weight:700;color:#2e7d32;opacity:0;transition:opacity .4s"></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php foreach ($productByDivision as $divName => $divRows): ?>
        <div class="fd-prod-div-content fd-scroll" data-pdiv="<?= htmlspecialchars($divName) ?>"
             style="<?= array_key_first($productByDivision) !== $divName ? 'display:none' : '' ?>">
          <?php foreach ($divRows as $pr): ?>
            <?php
            $status     = strtoupper((string)($pr['availability_status'] ?? 'AVAILABLE'));
            $qty        = (float)($pr['qty'] ?? 0);
            $qtyFloor   = floor($qty);
            $bn         = (string)($pr['bottleneck_name_snapshot'] ?? '');
            $isDirty    = !empty($pr['is_dirty']);
            $pillClass  = $status === 'OUT' ? 'minus' : ($status === 'LIMITED' ? 'kritis' : 'ok');
            $statusLabel = $status === 'OUT' ? 'HABIS' : ($status === 'LIMITED' ? 'TERBATAS' : 'TERSEDIA');
            $catName    = (string)($pr['category_name'] ?? '');
            ?>
            <div class="fd-prod-row" data-category="<?= htmlspecialchars($catName, ENT_QUOTES) ?>" style="margin-bottom:.5rem">
              <div class="fd-prod-head" data-pid="<?= (int)($pr['product_id'] ?? 0) ?>" onclick="fdToggleRecipe(this)">
                <div>
                  <div class="fd-prod-name"><?= htmlspecialchars((string)($pr['product_name'] ?? '-')) ?></div>
                  <div class="fd-prod-meta">
                    <?php if ($catName !== ''): ?><span style="font-size:.73rem;color:#a06050;font-weight:700"><?= htmlspecialchars($catName) ?></span><?= $bn !== '' ? ' · ' : '' ?><?php endif; ?>
                    <?= $bn !== '' ? 'Bottleneck: <strong>' . htmlspecialchars($bn) . '</strong> · ' : '' ?>Klik untuk lihat resep<?= $isDirty ? ' · <em>Cache lama</em>' : '' ?>
                  </div>
                </div>
                <div class="fd-prod-right">
                  <div>
                    <div class="fd-prod-qty"><?= number_format($qtyFloor, 0, ',', '.') ?></div>
                    <div class="fd-prod-uom"><?= htmlspecialchars((string)($pr['uom_code'] ?? '')) ?></div>
                  </div>
                  <span class="fd-pill <?= $pillClass ?>"><?= $statusLabel ?></span>
                  <i class="ri-arrow-down-s-line" style="color:#8b7772"></i>
                </div>
              </div>
              <div class="fd-prod-body" id="recipe-<?= (int)($pr['product_id'] ?? 0) ?>">
                <div class="fd-item-meta">Memuat resep...</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <!-- Analisa Lot Mengendap -->
  <div class="fd-section-divider"><span>Analisa FIFO &amp; Lot</span></div>
  <section class="fd-card fd-card-strong p-3">
    <?php
    $lotThreshold = (int)($stagnantLotAnalysis['threshold_days'] ?? 30);
    $lotSummary = (array)($stagnantLotAnalysis['summary'] ?? []);
    $lotTotal = (int)($lotSummary['material_multi_lot'] ?? 0)
      + (int)($lotSummary['material_aged_lot'] ?? 0)
      + (int)($lotSummary['component_multi_lot'] ?? 0)
      + (int)($lotSummary['component_aged_lot'] ?? 0);

    $lotFmtQty = static function ($qty, string $uom): string {
        $num = (float)$qty;
        $text = number_format($num, 2, ',', '.');
        if (abs($num - round($num)) < 0.0001) {
            $text = number_format($num, 0, ',', '.');
        }
        return trim($text . ' ' . $uom);
    };
    $lotDate = static function ($date): string {
        $date = trim((string)$date);
        if ($date === '' || $date === '0000-00-00') {
            return '-';
        }
        return date('d/m/Y', strtotime($date));
    };
    $lotUrl = static function (array $row, string $source): string {
        if ($source === 'component' && !empty($row['component_id'])) {
            return site_url('production/component-masters/usage/' . (int)$row['component_id']);
        }
        if (!empty($row['material_id'])) {
            return site_url('master/material/usage/' . (int)$row['material_id']);
        }
        return 'javascript:void(0)';
    };
    $lotBucketOrder = [
        'GUDANG' => 'Gudang',
        'BAR_REGULER' => 'BAR Reguler',
        'BAR_EVENT' => 'BAR Event',
        'KITCHEN_REGULER' => 'Kitchen Reguler',
        'KITCHEN_EVENT' => 'Kitchen Event',
    ];
    $lotGroupByBucket = static function (array $rows) use ($lotBucketOrder): array {
        $grouped = [];
        foreach ($rows as $row) {
            $key = (string)($row['location_bucket'] ?? 'LAINNYA');
            $label = (string)($row['location_bucket_label'] ?? ($lotBucketOrder[$key] ?? $key));
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['label' => $label, 'rows' => []];
            }
            $grouped[$key]['rows'][] = $row;
        }
        uksort($grouped, static function ($a, $b) use ($lotBucketOrder) {
            $ai = array_key_exists($a, $lotBucketOrder) ? array_search($a, array_keys($lotBucketOrder), true) : 99;
            $bi = array_key_exists($b, $lotBucketOrder) ? array_search($b, array_keys($lotBucketOrder), true) : 99;
            return $ai === $bi ? strcmp((string)$a, (string)$b) : $ai <=> $bi;
        });
        return $grouped;
    };
    $lotBucketLabels = static function (array $multiRows, array $agedRows) use ($lotGroupByBucket): array {
        $labels = [];
        foreach ([$lotGroupByBucket($multiRows), $lotGroupByBucket($agedRows)] as $bucketed) {
            foreach ($bucketed as $key => $bucket) {
                if (!isset($labels[$key])) {
                    $labels[$key] = (string)($bucket['label'] ?? $key);
                }
            }
        }
        return $labels;
    };
    $lotAdjustPayload = static function (array $lot, string $source): string {
        $payload = $lot;
        $payload['source_type'] = $source;
        if ($source === 'component') {
            $loc = strtoupper((string)($payload['location_type'] ?? ''));
            $payload['recon_location_type'] = substr($loc, -6) === '_EVENT' ? 'EVENT' : 'REGULER';
            if (empty($payload['division_code'])) {
                $payload['division_code'] = substr($loc, 0, 7) === 'KITCHEN' ? 'KITCHEN' : (substr($loc, 0, 3) === 'BAR' ? 'BAR' : '');
            }
        } else {
            $payload['identity_key'] = (string)($payload['profile_key'] ?? '');
        }
        return htmlspecialchars(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    };
    $renderMultiLot = static function (array $rows, string $source) use ($cur, $lotFmtQty, $lotDate, $lotUrl, $lotAdjustPayload): void {
        if (empty($rows)) {
            echo '<div class="fd-empty" style="padding:.75rem">Tidak ada item dengan lot lebih dari 1.</div>';
            return;
        }
        foreach ($rows as $idx => $row) {
            $href = $lotUrl($row, $source);
            $detailId = 'fd-lot-detail-' . $source . '-' . substr(md5(json_encode($row) . $idx), 0, 12);
            echo '<button type="button" class="fd-lot-row fd-lot-toggle" data-lot-target="' . htmlspecialchars($detailId) . '">';
            echo '<div style="min-width:0">';
            echo '<div class="fd-lot-name">' . htmlspecialchars((string)($row['item_name'] ?? '-')) . '</div>';
            echo '<div class="fd-lot-meta">';
            echo htmlspecialchars((string)($row['location_label'] ?? '-'));
            if (!empty($row['item_code'])) {
                echo ' · ' . htmlspecialchars((string)$row['item_code']);
            }
            echo '<br>Lot: <strong>' . number_format((int)($row['lot_count'] ?? 0), 0, ',', '.') . '</strong>';
            echo ' · Tertua: <strong>' . htmlspecialchars($lotDate($row['oldest_receipt_date'] ?? '')) . '</strong>';
            echo '</div></div>';
            echo '<div class="fd-lot-right">';
            echo '<span class="fd-lot-age">' . number_format((int)($row['oldest_age_days'] ?? 0), 0, ',', '.') . ' hari</span>';
            echo '<div class="fd-lot-qty">' . htmlspecialchars($lotFmtQty($row['qty_balance'] ?? 0, (string)($row['uom_code'] ?? ''))) . '</div>';
            echo '<div class="fd-lot-value">' . htmlspecialchars($cur($row['total_value'] ?? 0)) . '</div>';
            echo '<div style="font-size:.72rem;color:#8b7772;margin-top:.12rem"><i class="ri-arrow-down-s-line"></i> Detail</div>';
            echo '</div></button>';

            $lots = (array)($row['lots'] ?? []);
            echo '<div class="fd-lot-detail" id="' . htmlspecialchars($detailId) . '">';
            if (empty($lots)) {
                echo '<div class="fd-empty" style="padding:.75rem">Detail lot tidak tersedia.</div>';
            } else {
                echo '<table><thead><tr><th>Lot</th><th>Tanggal</th><th class="text-end">Qty</th><th class="text-end">Nilai</th><th class="text-end">Aksi</th></tr></thead><tbody>';
                foreach ($lots as $lot) {
                    $canAdjust = $source === 'component'
                        ? ((int)($lot['can_recon_adjust'] ?? 0) === 1 && (int)($lot['lot_id'] ?? 0) > 0 && (int)($lot['component_id'] ?? 0) > 0)
                        : ((int)($lot['can_recon_adjust'] ?? 0) === 1 && (string)($lot['location_scope'] ?? '') === 'DIVISION' && (int)($lot['division_id'] ?? 0) > 0 && (string)($lot['profile_key'] ?? '') !== '');
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars((string)($lot['lot_no'] ?? '-')) . '</strong><div class="text-muted">' . number_format((int)($lot['age_days'] ?? 0), 0, ',', '.') . ' hari</div></td>';
                    echo '<td>' . htmlspecialchars($lotDate($lot['receipt_date'] ?? '')) . '</td>';
                    echo '<td class="text-end">' . htmlspecialchars($lotFmtQty($lot['qty_balance'] ?? 0, (string)($lot['uom_code'] ?? ''))) . '</td>';
                    echo '<td class="text-end">' . htmlspecialchars($cur($lot['total_value'] ?? 0)) . '</td>';
                    echo '<td class="text-end">';
                    if ($canAdjust) {
                        echo '<button type="button" class="fd-lot-adjust-btn" data-lot-adjust="' . $lotAdjustPayload($lot, $source) . '">Adjust</button>';
                    } else {
                        echo '<a href="' . htmlspecialchars($href) . '" class="fd-lot-adjust-btn" style="text-decoration:none" title="Di luar cutoff bulan berjalan, gunakan audit/reconcile.">Audit</a>';
                    }
                    echo '</td></tr>';
                }
                echo '</tbody></table>';
            }
            echo '</div>';
        }
    };
    $renderAgedLot = static function (array $rows, string $source) use ($cur, $lotFmtQty, $lotDate, $lotUrl): void {
        if (empty($rows)) {
            echo '<div class="fd-empty" style="padding:.75rem">Tidak ada lot mengendap pada batas umur ini.</div>';
            return;
        }
        foreach ($rows as $row) {
            $href = $lotUrl($row, $source);
            echo '<a class="fd-lot-row" href="' . htmlspecialchars($href) . '">';
            echo '<div style="min-width:0">';
            echo '<div class="fd-lot-name">' . htmlspecialchars((string)($row['item_name'] ?? '-')) . '</div>';
            echo '<div class="fd-lot-meta">';
            echo htmlspecialchars((string)($row['location_label'] ?? '-'));
            if (!empty($row['item_code'])) {
                echo ' · ' . htmlspecialchars((string)$row['item_code']);
            }
            echo '<br>' . htmlspecialchars((string)($row['lot_no'] ?? '-'));
            echo ' · Receipt: <strong>' . htmlspecialchars($lotDate($row['receipt_date'] ?? '')) . '</strong>';
            echo '</div></div>';
            echo '<div class="fd-lot-right">';
            echo '<span class="fd-lot-age">' . number_format((int)($row['age_days'] ?? 0), 0, ',', '.') . ' hari</span>';
            echo '<div class="fd-lot-qty">' . htmlspecialchars($lotFmtQty($row['qty_balance'] ?? 0, (string)($row['uom_code'] ?? ''))) . '</div>';
            echo '<div class="fd-lot-value">' . htmlspecialchars($cur($row['total_value'] ?? 0)) . '</div>';
            echo '</div></a>';
        }
    };
    $renderBucketedLotList = static function (array $rows, string $source, callable $renderer) use ($lotGroupByBucket): void {
        $bucketed = $lotGroupByBucket($rows);
        if (empty($bucketed)) {
            $renderer([], $source);
            return;
        }
        foreach ($bucketed as $bucketKey => $bucket) {
            $bucketRows = (array)($bucket['rows'] ?? []);
            echo '<div class="fd-lot-bucket" data-lot-bucket-block="' . htmlspecialchars((string)$bucketKey) . '">';
            echo '<div class="fd-lot-bucket-title"><span>' . htmlspecialchars((string)($bucket['label'] ?? '-')) . '</span><span>' . number_format(count($bucketRows), 0, ',', '.') . '</span></div>';
            $renderer($bucketRows, $source);
            echo '</div>';
        }
    };
    ?>
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Analisa Bahan Mengendap</h2>
        <p class="fd-sec-sub">Pantau bahan baku dan component dengan lot aktif lebih dari satu atau lot yang mengendap minimal <?= number_format($lotThreshold, 0, ',', '.') ?> hari.</p>
      </div>
      <span class="fd-pill <?= $lotTotal > 0 ? 'kritis' : 'ok' ?>"><?= number_format($lotTotal, 0, ',', '.') ?> indikator</span>
    </div>

    <div class="fd-lot-grid">
      <?php foreach (['material' => 'Bahan Baku', 'component' => 'Component'] as $lotSource => $lotLabel): ?>
        <?php
        $lotBlock = (array)($stagnantLotAnalysis[$lotSource] ?? ['multi_lot' => [], 'aged_lot' => []]);
        $multiRows = (array)($lotBlock['multi_lot'] ?? []);
        $agedRows = (array)($lotBlock['aged_lot'] ?? []);
        $bucketLabels = $lotBucketLabels($multiRows, $agedRows);
        $lotCardId = 'lot-card-' . $lotSource;
        ?>
        <article class="fd-lot-panel" data-lot-card="<?= htmlspecialchars($lotCardId) ?>">
          <div class="fd-lot-panel-head">
            <div>
              <h3 class="fd-lot-title"><?= htmlspecialchars($lotLabel) ?></h3>
              <p class="fd-lot-sub"><?= count($multiRows) ?> group multi lot · <?= count($agedRows) ?> lot mengendap</p>
            </div>
            <span class="fd-pill"><?= number_format(count($multiRows) + count($agedRows), 0, ',', '.') ?></span>
          </div>
          <?php if (count($bucketLabels) > 0): ?>
            <div class="fd-lot-tabs" data-lot-tabs="<?= htmlspecialchars($lotCardId) ?>">
              <button type="button" class="fd-lot-tab on" data-lot-filter="ALL">Semua</button>
              <?php foreach ($bucketLabels as $bucketKey => $bucketLabel): ?>
                <button type="button" class="fd-lot-tab" data-lot-filter="<?= htmlspecialchars((string)$bucketKey) ?>"><?= htmlspecialchars((string)$bucketLabel) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="fd-lot-section">
            <div class="fd-lot-section-title">
              <span>Lot &gt; 1</span>
              <span><?= number_format(count($multiRows), 0, ',', '.') ?></span>
            </div>
            <div class="fd-lot-list">
              <?php $renderBucketedLotList($multiRows, $lotSource, $renderMultiLot); ?>
            </div>
          </div>

          <div class="fd-lot-section">
            <div class="fd-lot-section-title">
              <span>Lot Mengendap</span>
              <span>&ge; <?= number_format($lotThreshold, 0, ',', '.') ?> hari · <?= number_format(count($agedRows), 0, ',', '.') ?></span>
            </div>
            <div class="fd-lot-list">
              <?php $renderBucketedLotList($agedRows, $lotSource, $renderAgedLot); ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Ringkasan Adjustment -->
  <section class="fd-adjust-grid">
    <div class="fd-card p-3 fd-adjust-panel fd-adjust-panel-list">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Ringkasan Adjustment</h2>
          <p class="fd-sec-sub">Ringkasan per bahan/komponen untuk hari ini, minggu ini, dan bulan ini. Breakdown menampilkan line detail, bukan hanya nomor nota.</p>
        </div>
        <span class="fd-pill">3 periode</span>
      </div>

      <div class="fd-tabs" id="adjPeriodTabs">
        <?php foreach (['daily' => 'Hari Ini', 'weekly' => 'Minggu Ini', 'monthly' => 'Bulan Ini'] as $periodKey => $periodLabel): ?>
          <button class="fd-tab<?= $periodKey === 'daily' ? ' on' : '' ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>"><?= htmlspecialchars($periodLabel) ?></button>
        <?php endforeach; ?>
      </div>

      <?php foreach (['daily', 'weekly', 'monthly'] as $periodKey): ?>
        <?php $periodBlock = (array)($adjustmentSummary[$periodKey] ?? []); ?>
        <div class="fd-adj-period<?= $periodKey === 'daily' ? '' : ' d-none' ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>">
          <div class="fd-tabs" id="adjScopeTabs-<?= htmlspecialchars($periodKey) ?>">
            <?php foreach (['warehouse' => 'Gudang', 'division' => 'Bahan Baku', 'component' => 'Component'] as $adjKey => $adjLabel): ?>
              <button class="fd-sub-tab<?= $adjKey === 'warehouse' ? ' on' : '' ?>" data-adjscope="<?= htmlspecialchars($adjKey) ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>">
                <?= htmlspecialchars($adjLabel) ?> (<?= count((array)($periodBlock[$adjKey]['rows'] ?? [])) ?>)
              </button>
            <?php endforeach; ?>
          </div>

          <?php foreach (['warehouse' => 'Gudang', 'division' => 'Bahan Baku', 'component' => 'Component'] as $adjKey => $adjLabel): ?>
            <?php $adjRows = (array)($periodBlock[$adjKey]['rows'] ?? []); ?>
            <div class="fd-adj-content<?= $adjKey === 'warehouse' ? '' : ' d-none' ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>" data-adjscope="<?= htmlspecialchars($adjKey) ?>">
              <?php if (empty($adjRows)): ?>
                <div class="fd-empty">Belum ada adjustment <?= strtolower($adjLabel) ?> pada periode <?= htmlspecialchars(strtolower((string)($periodBlock['label'] ?? '-'))) ?>.</div>
              <?php else: ?>
                <div class="fd-adjust-scroll">
                  <?php foreach ($adjRows as $adjRow): ?>
                    <?php
                    $detailRows = (array)($adjRow['details'] ?? []);
                    $qtyOutTotal = $adjKey === 'component'
                      ? (float)($adjRow['qty_waste'] ?? 0) + (float)($adjRow['qty_spoil'] ?? 0) + (float)($adjRow['qty_minus'] ?? 0)
                      : (float)($adjRow['qty_waste'] ?? 0) + (float)($adjRow['qty_spoil'] ?? 0) + (float)($adjRow['qty_process_loss'] ?? 0) + (float)($adjRow['qty_variance'] ?? 0);
                    ?>
                    <div class="fd-adjust-card">
                      <div class="fd-adjust-head">
                        <div>
                          <div class="fd-adjust-title"><?= htmlspecialchars((string)($adjRow['object_name'] ?? '-')) ?></div>
                          <div class="fd-adjust-code"><?= htmlspecialchars((string)($adjRow['object_code'] ?? '-')) ?> · <?= htmlspecialchars((string)($adjRow['location_name'] ?? '-')) ?> · <?= (int)($adjRow['doc_count'] ?? 0) ?> nota / <?= (int)($adjRow['line_count'] ?? 0) ?> line</div>
                        </div>
                        <span class="fd-pill"><?= $cur((float)($adjRow['net_value_total'] ?? 0)) ?></span>
                      </div>
                      <div class="fd-adjust-metrics">
                        <div class="fd-adjust-metric">
                          <span class="lbl">Qty Keluar</span>
                          <span class="val minus"><?= number_format($qtyOutTotal, 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl">Adj Plus</span>
                          <span class="val plus"><?= number_format((float)($adjRow['qty_plus'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl">Net Value</span>
                          <span class="val <?= ((float)($adjRow['net_value_total'] ?? 0)) >= 0 ? 'plus' : 'minus' ?>"><?= $cur((float)($adjRow['net_value_total'] ?? 0)) ?></span>
                        </div>
                      </div>
                      <div class="fd-adjust-metrics">
                        <div class="fd-adjust-metric">
                          <span class="lbl">Waste</span>
                          <span class="val minus"><?= number_format((float)($adjRow['qty_waste'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl">Spoil</span>
                          <span class="val minus"><?= number_format((float)($adjRow['qty_spoil'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl"><?= $adjKey === 'component' ? 'Minus' : 'Variance / Loss' ?></span>
                          <span class="val minus"><?= number_format($adjKey === 'component' ? (float)($adjRow['qty_minus'] ?? 0) : ((float)($adjRow['qty_process_loss'] ?? 0) + (float)($adjRow['qty_variance'] ?? 0)), 2, ',', '.') ?></span>
                        </div>
                      </div>

                      <details class="fd-adjust-detail">
                        <summary>Lihat breakdown detail (<?= count($detailRows) ?> line)</summary>
                        <?php if (empty($detailRows)): ?>
                          <div class="fd-empty" style="padding:.7rem 0 0">Tidak ada detail line.</div>
                        <?php else: ?>
                          <div class="fd-adjust-detail-list">
                            <?php foreach ($detailRows as $detailRow): ?>
                              <?php
                              $detailQtyOut = $adjKey === 'component'
                                ? (float)($detailRow['qty_waste'] ?? 0) + (float)($detailRow['qty_spoil'] ?? 0) + (float)($detailRow['qty_minus'] ?? 0)
                                : (float)($detailRow['qty_waste'] ?? 0) + (float)($detailRow['qty_spoil'] ?? 0) + (float)($detailRow['qty_process_loss'] ?? 0) + (float)($detailRow['qty_variance'] ?? 0);
                              $detailMeta = trim(implode(' · ', array_filter([
                                (string)($detailRow['adjustment_no'] ?? ''),
                                (string)($detailRow['adjustment_date'] ?? ''),
                                (string)($detailRow['profile_label'] ?? ''),
                              ])));
                              $detailNote = trim(implode(' | ', array_filter([
                                (string)($detailRow['note'] ?? ''),
                                (string)($detailRow['header_notes'] ?? ''),
                              ])));
                              ?>
                              <div class="fd-adjust-detail-item">
                                <div class="fd-adjust-detail-top">
                                  <div>
                                    <div class="fd-adjust-title" style="font-size:.9rem"><?= htmlspecialchars($detailMeta !== '' ? $detailMeta : '-') ?></div>
                                    <div class="fd-adjust-detail-meta"><?= htmlspecialchars($detailNote !== '' ? $detailNote : 'Tanpa catatan') ?></div>
                                  </div>
                                  <div class="text-end">
                                    <div class="fd-adjust-title" style="font-size:.9rem"><?= number_format($detailQtyOut, 2, ',', '.') ?><?= !empty($detailRow['uom_code']) ? ' ' . htmlspecialchars((string)$detailRow['uom_code']) : '' ?></div>
                                    <div class="fd-adjust-detail-meta">+<?= number_format((float)($detailRow['qty_plus'] ?? 0), 2, ',', '.') ?> · <?= $cur(((float)($detailRow['qty_plus'] ?? 0) - $detailQtyOut) * (float)($detailRow['unit_cost'] ?? 0)) ?></div>
                                  </div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </details>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="fd-card p-3 fd-adjust-panel fd-adjust-panel-summary">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Ringkasan Per Tab</h2>
          <p class="fd-sec-sub">Angka utama menampilkan total net value. Nilai keluar menunjukkan total sisi pengurang, dan plus menunjukkan total adjustment penambah.</p>
        </div>
      </div>
      <div class="fd-adjust-scroll">
        <?php foreach (['daily', 'weekly', 'monthly'] as $periodKey): ?>
          <?php $periodBlock = (array)($adjustmentSummary[$periodKey] ?? []); ?>
          <div style="margin-bottom:1rem">
            <div class="fd-sec-title" style="font-size:.95rem;margin-bottom:.6rem"><?= htmlspecialchars((string)($periodBlock['label'] ?? '-')) ?></div>
            <div class="fd-list">
              <?php foreach (['warehouse' => 'Gudang', 'division' => 'Bahan Baku', 'component' => 'Component'] as $adjKey => $adjLabel): ?>
                <?php $adjTotals = (array)($periodBlock[$adjKey]['totals'] ?? []); ?>
                <div class="fd-item">
                  <div>
                    <div class="fd-item-title"><?= htmlspecialchars($adjLabel) ?></div>
                    <div class="fd-item-meta"><?= (int)($adjTotals['group_count'] ?? 0) ?> bahan · <?= (int)($adjTotals['doc_count'] ?? 0) ?> nota · <?= (int)($adjTotals['line_count'] ?? 0) ?> line</div>
                  </div>
                  <div class="text-end">
                    <div class="fd-item-title"><?= $cur((float)($adjTotals['net_value_total'] ?? 0)) ?></div>
                    <div class="fd-item-meta">Keluar <?= $cur((float)($adjTotals['value_out_total'] ?? 0)) ?> · Plus <?= $cur((float)($adjTotals['value_plus_total'] ?? 0)) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Produk Terbanyak -->
  <section class="fd-card p-3">
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Penjualan Produk Terbanyak</h2>
        <p class="fd-sec-sub">Peringkat berdasarkan qty terjual. Dipisah per hari ini, minggu ini, dan bulan ini.</p>
      </div>
    </div>
    <div class="fd-tabs" id="topProdPeriodTabs">
      <?php foreach (['daily' => 'Hari Ini', 'weekly' => 'Minggu Ini', 'monthly' => 'Bulan Ini'] as $periodKey => $periodLabel): ?>
        <button class="fd-tab<?= $periodKey === 'daily' ? ' on' : '' ?>" data-topperiod="<?= htmlspecialchars($periodKey) ?>"><?= htmlspecialchars($periodLabel) ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach (['daily', 'weekly', 'monthly'] as $periodKey): ?>
      <?php
      $periodBlock = (array)($topSellingProducts[$periodKey] ?? []);
      $periodGroups = (array)($periodBlock['groups'] ?? []);
      $periodCount = 0;
      foreach ($periodGroups as $groupBlock) { $periodCount += count((array)($groupBlock['rows'] ?? [])); }
      ?>
      <div class="fd-topprod-period<?= $periodKey === 'daily' ? '' : ' d-none' ?>" data-topperiod="<?= htmlspecialchars($periodKey) ?>">
        <div class="fd-sec-head" style="margin-bottom:.75rem">
          <div>
            <h2 class="fd-sec-title"><?= htmlspecialchars((string)($periodBlock['label'] ?? '-')) ?></h2>
            <p class="fd-sec-sub">Top produk berdasarkan qty, dipisah Food dan Beverage.</p>
          </div>
          <span class="fd-pill"><?= $periodCount ?> baris</span>
        </div>
        <div class="fd-topprod-period-grid">
          <?php foreach (['FOOD', 'BEVERAGE'] as $groupKey): ?>
            <?php
            $groupBlock = (array)($periodGroups[$groupKey] ?? ['label' => $groupKey, 'rows' => []]);
            $periodRows = (array)($groupBlock['rows'] ?? []);
            ?>
            <div class="fd-card fd-topprod-card">
              <div class="fd-topprod-subtitle"><?= htmlspecialchars((string)($groupBlock['label'] ?? $groupKey)) ?></div>
              <div class="fd-topprod-scroll">
                <div class="fd-list">
                <?php if (empty($periodRows)): ?>
                  <div class="fd-empty" style="padding:.5rem 0">Belum ada penjualan <?= htmlspecialchars(strtolower((string)($groupBlock['label'] ?? $groupKey))) ?>.</div>
                <?php else: ?>
                  <?php foreach ($periodRows as $idx => $periodRow): ?>
                    <div class="fd-topprod-row">
                      <div class="d-flex align-items-start gap-2">
                        <span class="fd-topprod-rank"><?= (int)$idx + 1 ?></span>
                        <div>
                          <div class="fd-topprod-name"><?= htmlspecialchars((string)($periodRow['product_name'] ?? '-')) ?></div>
                          <div class="fd-topprod-meta"><?= htmlspecialchars((string)($periodRow['product_code'] ?? '-')) ?> · <?= (int)($periodRow['order_count'] ?? 0) ?> order</div>
                        </div>
                      </div>
                      <div>
                        <div class="fd-topprod-qty"><?= number_format((float)($periodRow['qty_total'] ?? 0), 0, ',', '.') ?></div>
                        <div class="fd-topprod-net"><?= $cur((float)($periodRow['net_total'] ?? 0)) ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- Scope -->
  <section class="fd-2scope">
    <?php if (empty($posScopeRows)): ?>
      <div class="fd-card fd-scope-card">
        <div class="fd-empty">Belum ada data Reguler atau Event untuk periode ini.</div>
      </div>
    <?php else: ?>
      <?php foreach ($posScopeRows as $sr): ?>
        <div class="fd-card fd-scope-card">
          <div class="fd-scope-head">
            <h2 class="fd-scope-title"><?= htmlspecialchars((string)($sr['scope_label'] ?? '-')) ?></h2>
            <span class="fd-pill"><?= htmlspecialchars((string)($sr['scope_code'] ?? '-')) ?></span>
          </div>
          <div class="fd-item-meta mt-1">Penjualan bersih POS berdasarkan scope order.</div>
          <div class="fd-scope-val"><?= $cur($sr['net_sales_total'] ?? 0) ?></div>
          <div class="fd-scope-meta">
            <div><div class="fd-scope-meta-lbl">Transaksi</div><div class="fd-scope-meta-val"><?= number_format((int)($sr['transaction_count'] ?? 0), 0, ',', '.') ?></div></div>
            <div><div class="fd-scope-meta-lbl">Paid Total</div><div class="fd-scope-meta-val"><?= $cur($sr['gross_sales_total'] ?? 0) ?></div></div>
            <div><div class="fd-scope-meta-lbl">Refund</div><div class="fd-scope-meta-val"><?= $cur($sr['refund_total'] ?? 0) ?></div></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <!-- Charts -->
  <section class="fd-chart-grid">
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Tren Omzet, Nilai PO & Store Request</h2>
          <p class="fd-sec-sub">Omzet bersih POS, total nilai PO, dan jumlah SR per hari (<?= htmlspecialchars($chartFilters['period_label']) ?>).</p>
        </div>
        <span class="fd-pill"><?= htmlspecialchars($chartFilters['period_label']) ?></span>
      </div>
      <div id="fdTrendChart" style="min-height:320px;"></div>
    </div>
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Komposisi Status POS</h2>
          <p class="fd-sec-sub">Distribusi status order dalam periode KPI terpilih.</p>
        </div>
      </div>
      <div id="fdStatusChart" style="min-height:320px;"></div>
    </div>
  </section>

  <!-- Stok Kritis -->
  <section class="fd-2col">
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Stok Kritis</h2>
          <p class="fd-sec-sub">Item yang menyentuh batas minimum per lokasi stok. Kekurangan transaksi aktif dibaca pada Defisit Stok.</p>
        </div>
      </div>

      <div class="fd-tabs" id="stockMainTabs">
        <button class="fd-tab on" data-stab="wh">Gudang (<?= count($stockBreakdown['warehouse'] ?? []) ?>)</button>
        <button class="fd-tab" data-stab="dv">Divisi (<?= count($stockBreakdown['division'] ?? []) ?>)</button>
        <button class="fd-tab" data-stab="cp">Base/Prepare (<?= count($stockBreakdown['component'] ?? []) ?>)</button>
      </div>

      <!-- Gudang -->
      <div id="stWh" class="fd-stab-content">
        <?php if (empty($stockBreakdown['warehouse'])): ?>
          <div class="fd-empty">Tidak ada stok gudang yang rendah atau nol.</div>
        <?php else: ?>
          <div class="fd-scroll">
            <?php foreach ($stockBreakdown['warehouse'] as $r): ?>
              <?php $sev = (string)($r['severity'] ?? 'kritis'); ?>
              <div class="fd-item <?= $sev ?>">
                <div>
                  <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
                  <div class="fd-item-meta">Threshold <?= number_format((float)($r['threshold'] ?? 0), 2, ',', '.') ?></div>
                </div>
                <div class="text-end">
                  <span class="fd-pill <?= $sev ?>"><?= number_format((float)($r['qty'] ?? 0), 2, ',', '.') ?></span>
                  <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Divisi dengan sub-tab per lokasi -->
      <div id="stDv" class="fd-stab-content d-none">
        <?php if (empty($divisionSubTabs)): ?>
          <div class="fd-empty">Tidak ada stok divisi yang rendah atau nol.</div>
        <?php else: ?>
          <div class="fd-tabs" id="dvSubTabs" style="margin-bottom:.65rem">
            <?php $dvFirst = true; foreach (array_keys($divisionSubTabs) as $loc): ?>
              <button class="fd-sub-tab <?= $dvFirst ? 'on' : '' ?>" data-dvloc="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?> (<?= count($divisionSubTabs[$loc]) ?>)</button>
              <?php $dvFirst = false; endforeach; ?>
          </div>
          <?php foreach ($divisionSubTabs as $loc => $rows): ?>
            <div class="fd-dv-loc fd-scroll" data-dvloc="<?= htmlspecialchars($loc) ?>" <?= array_key_first($divisionSubTabs) !== $loc ? 'style="display:none"' : '' ?>>
              <?php foreach ($rows as $r): ?>
                <?php $sev = (string)($r['severity'] ?? 'kritis'); ?>
                <div class="fd-item <?= $sev ?>" style="margin-bottom:.5rem">
                  <div>
                    <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
                    <div class="fd-item-meta">Threshold <?= number_format((float)($r['threshold'] ?? 0), 2, ',', '.') ?></div>
                  </div>
                  <div class="text-end">
                    <span class="fd-pill <?= $sev ?>"><?= number_format((float)($r['qty'] ?? 0), 2, ',', '.') ?></span>
                    <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Base/Prepare dengan sub-tab per lokasi -->
      <div id="stCp" class="fd-stab-content d-none">
        <?php if (empty($componentSubTabs)): ?>
          <div class="fd-empty">Tidak ada stok base/prepare yang rendah atau nol.</div>
        <?php else: ?>
          <div class="fd-tabs" id="cpSubTabs" style="margin-bottom:.65rem">
            <?php $cpFirst = true; foreach (array_keys($componentSubTabs) as $loc): ?>
              <button class="fd-sub-tab <?= $cpFirst ? 'on' : '' ?>" data-cploc="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?> (<?= count($componentSubTabs[$loc]) ?>)</button>
              <?php $cpFirst = false; endforeach; ?>
          </div>
          <?php foreach ($componentSubTabs as $loc => $rows): ?>
            <div class="fd-cp-loc fd-scroll" data-cploc="<?= htmlspecialchars($loc) ?>" <?= array_key_first($componentSubTabs) !== $loc ? 'style="display:none"' : '' ?>>
              <?php foreach ($rows as $r): ?>
                <?php $sev = (string)($r['severity'] ?? 'kritis'); ?>
                <div class="fd-item <?= $sev ?>" style="margin-bottom:.5rem">
                  <div>
                    <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
                    <div class="fd-item-meta"><?= htmlspecialchars((string)($r['location_name'] ?? '-')) ?> · Threshold <?= number_format((float)($r['threshold'] ?? 0), 2, ',', '.') ?></div>
                  </div>
                  <div class="text-end">
                    <span class="fd-pill <?= $sev ?>"><?= number_format((float)($r['qty'] ?? 0), 2, ',', '.') ?></span>
                    <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Aktivitas Terbaru -->
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Aktivitas Terbaru</h2>
          <p class="fd-sec-sub">POS, purchase, dan inventory dalam periode KPI.</p>
        </div>
      </div>
      <div class="fd-list">
        <?php if (empty($recentActivity)): ?>
          <div class="fd-empty">Belum ada aktivitas pada periode ini.</div>
        <?php else: ?>
          <?php foreach ($recentActivity as $r): ?>
            <div class="fd-item">
              <div>
                <div class="fd-item-title"><?= htmlspecialchars((string)($r['source_label'] ?? '-')) ?> · <?= htmlspecialchars((string)($r['ref_no'] ?? '-')) ?></div>
                <div class="fd-item-meta">Status: <?= htmlspecialchars((string)($r['status'] ?? '-')) ?> · <?= date('d-m H:i', strtotime((string)($r['event_at'] ?? 'now'))) ?></div>
              </div>
              <div class="fd-item-title text-end"><?= $cur($r['amount'] ?? 0) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Stok Kritis Detail — JS filter, no reload -->
  <section class="fd-card p-3">
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Stok Kritis Detail</h2>
        <p class="fd-sec-sub">Item yang menyentuh atau turun di bawah threshold. Filter per lokasi tanpa reload.</p>
      </div>
    </div>

    <?php if (!empty($criticalLocations)): ?>
    <div class="fd-tabs" id="critTabs">
      <button class="fd-tab on" data-critloc="ALL">Semua</button>
      <?php foreach ($criticalLocations as $loc): ?>
        <button class="fd-tab" data-critloc="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="fd-list" id="critList">
      <?php if (empty($criticalStockRows)): ?>
        <div class="fd-empty">Belum ada item kritis yang terdeteksi.</div>
      <?php else: ?>
        <?php foreach ($criticalStockRows as $r): ?>
          <div class="fd-item" data-critloc="<?= htmlspecialchars((string)($r['location_name'] ?? '-')) ?>">
            <div>
              <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
              <div class="fd-item-meta"><?= htmlspecialchars((string)($r['stock_scope'] ?? '-')) ?> · <?= htmlspecialchars((string)($r['location_name'] ?? '-')) ?> · Threshold <?= number_format((float)($r['threshold_qty'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="text-end">
              <div class="fd-item-title"><?= number_format((float)($r['qty_balance'] ?? 0), 2, ',', '.') ?></div>
              <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
window.addEventListener('load', function () {
  // Period filter
  const periodSel = document.getElementById('dashPeriod');
  if (periodSel) {
    periodSel.addEventListener('change', function () {
      document.querySelectorAll('.fd-custom-date').forEach(function (el) {
        el.classList.toggle('d-none', this.value !== 'custom');
      }.bind(this));
    });
  }

  // ─── Charts ───────────────────────────────────────────────
  const rupiah = function (v) { return 'Rp ' + Number(v||0).toLocaleString('id-ID', {maximumFractionDigits:0}); };

  const trendEl = document.getElementById('fdTrendChart');
  if (trendEl && window.ApexCharts) {
    const single = <?= json_encode(count($trend['labels'] ?? []) <= 1) ?>;
    new ApexCharts(trendEl, {
      chart: { type: 'line', height: 320, toolbar: { show: false } },
      series: [
        { name: 'Omzet Bersih', type: single ? 'bar' : 'area', data: <?= json_encode(array_values($trend['sales'] ?? [])) ?> },
        { name: 'Nilai PO',     type: single ? 'bar' : 'line', data: <?= json_encode(array_values($trend['po']    ?? [])) ?> },
        { name: 'Jml SR',       type: 'line',                  data: <?= json_encode(array_values($trend['sr']    ?? [])) ?> },
      ],
      colors: ['#8f2d23','#1e88e5','#ef6c00'],
      stroke: { curve: 'smooth', width: [3,2,2] },
      fill:   { type: ['solid','solid','solid'], opacity: [single ? .9 : .18, single ? .9 : 1, 1] },
      dataLabels: { enabled: false },
      xaxis: { categories: <?= json_encode(array_values($trend['labels'] ?? [])) ?> },
      yaxis: [
        { seriesName: 'Omzet Bersih', labels: { formatter: function(v) { return rupiah(v); } } },
        { seriesName: 'Nilai PO', show: false },
        { seriesName: 'Jml SR', opposite: true, labels: { formatter: function(v) { return Number(v||0).toLocaleString('id-ID')+' SR'; } } },
      ],
      tooltip: { shared: true, y: [
        { formatter: function(v) { return rupiah(v); } },
        { formatter: function(v) { return rupiah(v); } },
        { formatter: function(v) { return Number(v||0).toLocaleString('id-ID')+' SR'; } },
      ]},
      legend: { position: 'top' }
    }).render();
  }

  const statusEl = document.getElementById('fdStatusChart');
  if (statusEl && window.ApexCharts) {
    const totals = <?= json_encode(array_values($sTotals)) ?>;
    new ApexCharts(statusEl, {
      chart: { type: 'donut', height: 320 },
      series: totals.length ? totals : [1],
      labels: totals.length ? <?= json_encode(array_values($sLabels)) ?> : ['Belum Ada Data'],
      colors: totals.length ? <?= json_encode(array_values($sColors)) ?> : ['#c7b2ad'],
      legend: { position: 'bottom' },
      dataLabels: { enabled: true },
      tooltip: { y: { formatter: function(v) { return Number(v||0).toLocaleString('id-ID')+' order'; } } }
    }).render();
  }

  // ─── Stock main tabs ──────────────────────────────────────
  function bindTabs(tabsId, contentsSelector, dataAttr) {
    const container = document.getElementById(tabsId);
    if (!container) return;
    container.querySelectorAll('.fd-tab,.fd-sub-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        container.querySelectorAll('.fd-tab,.fd-sub-tab').forEach(function(b) { b.classList.remove('on'); });
        btn.classList.add('on');
        document.querySelectorAll(contentsSelector).forEach(function (el) {
          el.style.display = el.dataset[dataAttr] === btn.dataset[dataAttr] ? '' : 'none';
        });
      });
    });
  }

  // Main stock tabs
  const stockTabBtns = document.querySelectorAll('#stockMainTabs .fd-tab');
  const stockContents = { wh: document.getElementById('stWh'), dv: document.getElementById('stDv'), cp: document.getElementById('stCp') };
  stockTabBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      stockTabBtns.forEach(function(b) { b.classList.remove('on'); });
      btn.classList.add('on');
      Object.values(stockContents).forEach(function(el) { if(el) el.classList.add('d-none'); });
      const target = stockContents[btn.dataset.stab];
      if (target) target.classList.remove('d-none');
    });
  });

  // Adjustment period tabs
  const adjPeriodBtns = document.querySelectorAll('#adjPeriodTabs .fd-tab');
  adjPeriodBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const periodKey = btn.dataset.adjperiod;
      adjPeriodBtns.forEach(function (node) { node.classList.remove('on'); });
      btn.classList.add('on');
      document.querySelectorAll('.fd-adj-period').forEach(function (panel) {
        panel.classList.toggle('d-none', panel.dataset.adjperiod !== periodKey);
      });
    });
  });

  // Adjustment scope tabs per period
  document.querySelectorAll('[id^="adjScopeTabs-"]').forEach(function (container) {
    container.querySelectorAll('.fd-sub-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const periodKey = btn.dataset.adjperiod;
        const scopeKey = btn.dataset.adjscope;
        container.querySelectorAll('.fd-sub-tab').forEach(function (node) { node.classList.remove('on'); });
        btn.classList.add('on');
        document.querySelectorAll('.fd-adj-content[data-adjperiod="' + periodKey + '"]').forEach(function (panel) {
          panel.classList.toggle('d-none', panel.dataset.adjscope !== scopeKey);
        });
      });
    });
  });

  // Top product period tabs
  const topProdBtns = document.querySelectorAll('#topProdPeriodTabs .fd-tab');
  topProdBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const periodKey = btn.dataset.topperiod;
      topProdBtns.forEach(function (node) { node.classList.remove('on'); });
      btn.classList.add('on');
      document.querySelectorAll('.fd-topprod-period').forEach(function (panel) {
        panel.classList.toggle('d-none', panel.dataset.topperiod !== periodKey);
      });
    });
  });

  // Division sub-tabs
  bindTabs('dvSubTabs', '.fd-dv-loc', 'dvloc');
  bindTabs('cpSubTabs', '.fd-cp-loc', 'cploc');

  // ─── Product division tabs + category filter (DB-shared) ────
  var SAVE_CAT_URL = <?= json_encode(site_url('dashboard/save_prod_live_cats')) ?>;

  window.fdCatFilter = function(divName) {
    var checks  = document.querySelectorAll('[data-pdiv-checks="' + divName + '"] .fd-cat-cb');
    var enabled = new Set();
    checks.forEach(function(cb) { if (cb.checked) enabled.add(cb.dataset.cat); });
    var content = document.querySelector('.fd-prod-div-content[data-pdiv="' + divName + '"]');
    if (content) {
      content.querySelectorAll('.fd-prod-row').forEach(function(row) {
        row.style.display = (enabled.size === 0 || enabled.has(row.dataset.category)) ? '' : 'none';
      });
    }
  };

  window.fdCatSave = function(divName, btn) {
    var checks  = document.querySelectorAll('[data-pdiv-checks="' + divName + '"] .fd-cat-cb');
    var hidden  = [];
    checks.forEach(function(cb) { if (!cb.checked) hidden.push(cb.dataset.cat); });

    var wrap   = btn.closest('.fd-cat-filter-wrap');
    var status = wrap ? wrap.querySelector('.fd-cat-save-status') : null;

    btn.disabled = true;
    btn.textContent = 'Menyimpan…';
    if (status) { status.textContent = ''; status.style.opacity = '0'; }

    var fd = new FormData();
    fd.append('division', divName);
    hidden.forEach(function(c) { fd.append('hidden_cats[]', c); });

    fetch(SAVE_CAT_URL, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri ri-save-line"></i> Simpan Pengaturan';
        if (status) {
          status.textContent = data.ok ? '✓ Tersimpan — berlaku untuk semua pengguna' : '✗ Gagal menyimpan';
          status.style.color  = data.ok ? '#2e7d32' : '#c62828';
          status.style.opacity = '1';
          setTimeout(function() { status.style.opacity = '0'; }, 3000);
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri ri-save-line"></i> Simpan Pengaturan';
        if (status) { status.textContent = '✗ Gagal'; status.style.color = '#c62828'; status.style.opacity = '1'; setTimeout(function() { status.style.opacity = '0'; }, 2500); }
      });
  };

  window.fdCatAll = function(divName, checked) {
    document.querySelectorAll('[data-pdiv-checks="' + divName + '"] .fd-cat-cb').forEach(function(cb) { cb.checked = checked; });
    window.fdCatFilter(divName);
  };

  // Apply filter on page load based on PHP-rendered checkbox state (from DB)
  <?php foreach (array_keys($productByDivision) as $divName): ?>
  fdCatFilter(<?= json_encode($divName) ?>);
  <?php endforeach; ?>

  document.querySelectorAll('#prodDivTabs .fd-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('#prodDivTabs .fd-tab').forEach(function(b) { b.classList.remove('on'); });
      btn.classList.add('on');
      var pdiv = btn.dataset.pdiv;
      document.querySelectorAll('.fd-prod-div-content').forEach(function(el) {
        el.style.display = el.dataset.pdiv === pdiv ? '' : 'none';
      });
      // Move category filter panel to active division, keep visible state
      var filterBtn = document.getElementById('prodCatFilterBtn');
      var wasActive = filterBtn && filterBtn.classList.contains('active');
      document.querySelectorAll('.fd-cat-filter-wrap').forEach(function(p) { p.style.display = 'none'; });
      if (wasActive) {
        var panel = document.querySelector('[data-pdiv-filter="' + pdiv + '"]');
        if (panel) panel.style.display = '';
      }
    });
  });

  (function() {
    var filterBtn = document.getElementById('prodCatFilterBtn');
    if (!filterBtn) return;
    filterBtn.addEventListener('click', function() {
      var activePdiv = (document.querySelector('#prodDivTabs .fd-tab.on') || {}).dataset?.pdiv;
      if (!activePdiv) return;
      document.querySelectorAll('.fd-cat-filter-wrap').forEach(function(p) { p.style.display = 'none'; });
      if (filterBtn.classList.contains('active')) {
        filterBtn.classList.remove('active');
      } else {
        var panel = document.querySelector('[data-pdiv-filter="' + activePdiv + '"]');
        if (panel) { panel.style.display = ''; filterBtn.classList.add('active'); }
      }
    });
  })();

  // ─── Product recipe expand ────────────────────────────────
  const recipeCache = {};
  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }
  function fdRecipeSourcePill(srcType) {
    const color = srcType === 'bahan baku'
      ? 'background:#e8f5e9;color:#1b5e20'
      : (srcType === 'base'
        ? 'background:#e3f2fd;color:#0d47a1'
        : (srcType === 'prepare'
          ? 'background:#fff3e0;color:#e65100'
          : 'background:#f5f5f5;color:#555'));
    return '<span class="fd-pill" style="font-size:.7rem;' + color + '">' + escapeHtml(srcType || '-') + '</span>';
  }
  function fdRecipeQty(value, digits) {
    return Number(value || 0).toLocaleString('id-ID', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    });
  }
  window.fdToggleRecipe = function (head) {
    const pid = head.dataset.pid;
    const body = document.getElementById('recipe-' + pid);
    if (!body) return;
    const isOpen = body.classList.contains('open');
    if (isOpen) { body.classList.remove('open'); return; }

    body.classList.add('open');
    if (recipeCache[pid]) { body.innerHTML = recipeCache[pid]; return; }

    body.innerHTML = '<div style="color:#8b7772;padding:.4rem 0">Memuat resep...</div>';
    fetch(BASE_URL + 'dashboard/product_recipe_stock?product_id=' + pid)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok || !data.data.recipe.length) {
          body.innerHTML = '<div style="color:#8b7772">Tidak ada data resep.</div>';
          recipeCache[pid] = body.innerHTML;
          return;
        }
        let html = '<table style="width:100%;font-size:.83rem;border-collapse:collapse">';
        html += '<tr style="color:#8b7772;font-weight:700"><th style="padding:.3rem 0;text-align:left">Bahan</th><th style="padding:.3rem;text-align:center">Jenis Sumber</th><th style="padding:.3rem;text-align:center">Peran</th><th style="padding:.3rem;text-align:right">Per Saji</th><th style="padding:.3rem;text-align:right">Stok Live</th><th style="padding:.3rem;text-align:right">Sisa Produk</th><th style="padding:.3rem;text-align:center">Detail</th></tr>';
        data.data.recipe.forEach(function(r, idx) {
          const stockCls = r.stock_qty <= 0 ? 'bad' : 'ok';
          const stockFmt = fdRecipeQty(r.stock_qty, 2);
          const servingFmt = (r.available_servings === null || typeof r.available_servings === 'undefined')
            ? '-'
            : Number(r.available_servings).toLocaleString('id-ID', {maximumFractionDigits:0});
          const srcType  = r.source_type || '-';
          const children = Array.isArray(r.children) ? r.children : [];
          const childId = 'recipe-child-' + pid + '-' + idx;
          html += '<tr style="border-top:1px solid rgba(170,95,78,.1)">';
          html += '<td style="padding:.32rem 0"><span class="fd-item-title">' + escapeHtml(r.ingredient_name || '-') + '</span></td>';
          html += '<td style="padding:.32rem;text-align:center">' + fdRecipeSourcePill(srcType) + '</td>';
          html += '<td style="padding:.32rem;text-align:center"><span class="fd-pill" style="font-size:.7rem">' + escapeHtml(r.ingredient_role || '-') + '</span></td>';
          html += '<td style="padding:.32rem;text-align:right">' + fdRecipeQty(r.qty_per_serve, 2) + ' ' + escapeHtml(r.uom_code || '') + '</td>';
          html += '<td style="padding:.32rem;text-align:right"><span class="fd-recipe-stock ' + stockCls + '">' + stockFmt + '</span></td>';
          html += '<td style="padding:.32rem;text-align:right"><span class="fd-recipe-stock ' + stockCls + '">' + servingFmt + '</span></td>';
          html += '<td style="padding:.32rem;text-align:center">';
          if ((srcType === 'base' || srcType === 'prepare') && children.length) {
            html += '<button type="button" class="fd-recipe-expand" onclick="fdToggleRecipeChild(\'' + childId + '\', this)">Bahan produksi</button>';
          } else if (srcType === 'base' || srcType === 'prepare') {
            html += '<span class="fd-item-meta">Belum ada formula</span>';
          } else {
            html += '-';
          }
          html += '</td>';
          html += '</tr>';
          if ((srcType === 'base' || srcType === 'prepare') && children.length) {
            html += '<tr class="fd-recipe-child-row" id="' + childId + '"><td colspan="7" style="padding:.45rem 0 .75rem">';
            html += '<div class="fd-recipe-child-box">';
            html += '<div class="fd-recipe-child-title"><span>Komposit ' + escapeHtml(r.ingredient_name || '-') + '</span><span class="fd-recipe-child-note">Cek apakah bahan cukup untuk produksi batch</span></div>';
            html += '<table style="width:100%;font-size:.79rem;border-collapse:collapse">';
            html += '<tr style="color:#8b7772;font-weight:800"><th style="padding:.25rem;text-align:left">Bahan / Sub Component</th><th style="padding:.25rem;text-align:center">Sumber</th><th style="padding:.25rem;text-align:right">Kebutuhan</th><th style="padding:.25rem;text-align:right">Stok</th><th style="padding:.25rem;text-align:right">Estimasi Batch</th></tr>';
            children.forEach(function(c) {
              const cStockCls = Number(c.stock_qty || 0) <= 0 ? 'bad' : 'ok';
              const cBatch = (c.available_batches === null || typeof c.available_batches === 'undefined')
                ? '-'
                : Number(c.available_batches).toLocaleString('id-ID', {maximumFractionDigits:0});
              html += '<tr style="border-top:1px solid rgba(170,95,78,.1)">';
              html += '<td style="padding:.28rem"><span class="fd-item-title">' + escapeHtml(c.ingredient_name || '-') + '</span></td>';
              html += '<td style="padding:.28rem;text-align:center">' + fdRecipeSourcePill(c.source_type || '-') + '</td>';
              html += '<td style="padding:.28rem;text-align:right">' + fdRecipeQty(c.qty_per_batch, 2) + ' ' + escapeHtml(c.uom_code || '') + '</td>';
              html += '<td style="padding:.28rem;text-align:right"><span class="fd-recipe-stock ' + cStockCls + '">' + fdRecipeQty(c.stock_qty, 2) + '</span></td>';
              html += '<td style="padding:.28rem;text-align:right"><span class="fd-recipe-stock ' + cStockCls + '">' + cBatch + '</span></td>';
              html += '</tr>';
            });
            html += '</table></div></td></tr>';
          }
        });
        html += '</table>';
        body.innerHTML = html;
        recipeCache[pid] = html;
      })
      .catch(function() {
        body.innerHTML = '<div style="color:#c62828">Gagal memuat resep.</div>';
      });
  };

  window.fdToggleRecipeChild = function (id, btn) {
    const row = document.getElementById(id);
    if (!row) return;
    row.classList.toggle('open');
    if (btn) {
      btn.textContent = row.classList.contains('open') ? 'Tutup bahan' : 'Bahan produksi';
    }
  };

  // ─── Analisa FIFO Lot: expand + adjustment via daily recon ─────
  (function () {
    const MAT_RECON_ADJ_URL  = <?= json_encode(site_url('inventory/stock/daily-recon/division/quick-adjust')) ?>;
    const COMP_RECON_ADJ_URL = <?= json_encode(site_url('production/component-daily-recon/quick-adjust')) ?>;
    const MAT_REASONS = {
      WASTE: { cancel_order: 'Cancel Order', kitchen_error: 'Kitchen Error', overproduction: 'Overproduction', spillage: 'Spillage / Tumpah', prep_trim_excess: 'Prep Trim Excess', expired_opened: 'Expired Opened', other: 'Other' },
      SPOIL: { expired: 'Expired', temperature_abuse: 'Temperature Abuse', contamination: 'Contamination', improper_storage: 'Improper Storage', overstock: 'Overstock', other: 'Other' },
      PROCESS_LOSS: { defrost_loss: 'Defrost Loss', trimming_standard: 'Trimming Standard', cooking_loss: 'Cooking Loss', evaporation: 'Evaporation', brew_loss: 'Brew Loss', absorption_loss: 'Absorption Loss', process_residue: 'Process Residue', variable_process_consumable: 'Variable Process Consumable', other: 'Other' },
      VARIANCE: { over_usage: 'Over Usage', under_usage: 'Under Usage', unrecorded_usage: 'Unrecorded Usage', counting_error: 'Counting Error', system_mismatch: 'System Mismatch', theft_suspected: 'Theft Suspected', unknown_shrinkage: 'Unknown Shrinkage', other: 'Other' },
      ADJUSTMENT_PLUS: { opening_correction: 'Opening Correction', stock_found: 'Stock Found', manual_reclass: 'Manual Reclass', other: 'Other' }
    };
    const COMP_REASONS = <?= json_encode(function_exists('component_adjustment_reason_options') ? component_adjustment_reason_options() : [
      'WASTE' => ['other' => 'Other'],
      'SPOILAGE' => ['other' => 'Other'],
      'ADJUSTMENT_PLUS' => ['other' => 'Other'],
      'ADJUSTMENT_MINUS' => ['other' => 'Other'],
    ]) ?>;
    const TYPE_LABELS = {
      WASTE: 'Waste',
      SPOIL: 'Spoil',
      SPOILAGE: 'Spoil',
      PROCESS_LOSS: 'Process Loss',
      VARIANCE: 'Variance',
      ADJUSTMENT_MINUS: 'Adjustment Minus',
      ADJUSTMENT_PLUS: 'Adjustment Plus'
    };

    const modalEl = document.getElementById('lotAdjModal');
    const titleEl = document.getElementById('lotAdjTitle');
    const subEl = document.getElementById('lotAdjSub');
    const alertEl = document.getElementById('lotAdjAlert');
    const dateEl = document.getElementById('lotAdjDate');
    const sysEl = document.getElementById('lotAdjSystem');
    const physEl = document.getElementById('lotAdjPhysical');
    const diffEl = document.getElementById('lotAdjDiffText');
    const typeEl = document.getElementById('lotAdjType');
    const reasonEl = document.getElementById('lotAdjReason');
    const noteEl = document.getElementById('lotAdjNote');
    const submitEl = document.getElementById('lotAdjSubmitBtn');
    if (!modalEl || !submitEl) return;

    let lotCtx = {};

    function escHtml(s) {
      const d = document.createElement('div');
      d.textContent = String(s ?? '');
      return d.innerHTML;
    }
    function fmtNum(v, max = 4) {
      return Number(v || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: max });
    }
    function showLotAlert(type, message) {
      alertEl.innerHTML = message ? '<div class="alert alert-' + type + ' py-2 small mb-0">' + message + '</div>' : '';
    }
    async function parseJsonResponse(response) {
      const text = await response.text();
      let data = null;
      try {
        data = text ? JSON.parse(text) : {};
      } catch (err) {
        throw new Error('Respons backend bukan JSON valid: ' + text.substring(0, 180));
      }
      if (!response.ok || data.ok === false) {
        throw new Error(data.message || 'Request gagal.');
      }
      return data;
    }
    function currentProfileSystemQty() {
      if (lotCtx.source_type === 'component') {
        return parseFloat(lotCtx.profile_system_qty || lotCtx.qty_balance || 0) || 0;
      }
      return parseFloat(lotCtx.profile_system_qty_content || lotCtx.qty_balance || 0) || 0;
    }
    function currentSystemQty() {
      return parseFloat(lotCtx.qty_balance || 0) || 0;
    }
    function typeOptionsForDiff() {
      const diff = (parseFloat(physEl.value || 0) || 0) - currentSystemQty();
      if (Math.abs(diff) < 0.0001) return [];
      if (diff > 0) return ['ADJUSTMENT_PLUS'];
      return lotCtx.source_type === 'component'
        ? ['ADJUSTMENT_MINUS', 'WASTE', 'SPOILAGE']
        : ['VARIANCE', 'WASTE', 'SPOIL', 'PROCESS_LOSS'];
    }
    function fillTypeAndReasons(keepType) {
      const types = typeOptionsForDiff();
      const current = keepType || typeEl.value || '';
      typeEl.innerHTML = types.length
        ? types.map(function (type) {
            const selected = current === type ? ' selected' : '';
            return '<option value="' + type + '"' + selected + '>' + (TYPE_LABELS[type] || type) + '</option>';
          }).join('')
        : '<option value="">Selisih 0</option>';
      if (types.length && !types.includes(typeEl.value)) typeEl.value = types[0];
      const reasonMap = lotCtx.source_type === 'component' ? COMP_REASONS : MAT_REASONS;
      const reasonKey = typeEl.value === 'ADJUSTMENT_MINUS' && lotCtx.source_type !== 'component' ? 'VARIANCE' : typeEl.value;
      const options = reasonMap[reasonKey] || { other: 'Other' };
      reasonEl.innerHTML = Object.keys(options).map(function (key) {
        return '<option value="' + key + '">' + escHtml(options[key]) + '</option>';
      }).join('');
      const diff = (parseFloat(physEl.value || 0) || 0) - currentSystemQty();
      const uom = lotCtx.uom_code || '';
      diffEl.textContent = Math.abs(diff) < 0.0001
        ? 'Selisih 0. Ubah stok fisik agar bisa diposting.'
        : 'Selisih: ' + (diff > 0 ? '+' : '') + fmtNum(diff) + ' ' + uom;
      diffEl.className = Math.abs(diff) < 0.0001 ? 'form-text text-muted' : (diff > 0 ? 'form-text text-success fw-semibold' : 'form-text text-danger fw-semibold');
    }
    function openLotAdjustment(payload) {
      lotCtx = payload || {};
      const uom = lotCtx.uom_code || '';
      const systemQty = currentSystemQty();
      const profileQty = currentProfileSystemQty();
      const lotQty = parseFloat(lotCtx.qty_balance || 0) || 0;
      titleEl.textContent = lotCtx.item_name || '-';
      subEl.textContent = (lotCtx.source_type === 'component' ? 'Component' : 'Bahan Baku')
        + ' · ' + (lotCtx.lot_no || '-')
        + ' · ' + (lotCtx.location_type || lotCtx.destination_type || lotCtx.location_scope || '-')
        + ' · Lot ini ' + fmtNum(lotQty) + ' ' + uom
        + ' · Profil ' + fmtNum(profileQty) + ' ' + uom;
      sysEl.value = fmtNum(systemQty) + ' ' + uom;
      physEl.value = Number(systemQty);
      noteEl.value = '';
      showLotAlert('', '');
      fillTypeAndReasons('');
      const modal = window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(modalEl);
      if (modal) modal.show();
    }

    document.addEventListener('click', function (event) {
      const lotTab = event.target.closest('.fd-lot-tab[data-lot-filter]');
      if (lotTab) {
        const tabsWrap = lotTab.closest('[data-lot-tabs]');
        const cardId = tabsWrap ? tabsWrap.dataset.lotTabs : '';
        const card = cardId ? document.querySelector('[data-lot-card="' + cardId + '"]') : lotTab.closest('[data-lot-card]');
        const filter = lotTab.dataset.lotFilter || 'ALL';
        if (tabsWrap) {
          tabsWrap.querySelectorAll('.fd-lot-tab').forEach(function (btn) { btn.classList.remove('on'); });
          lotTab.classList.add('on');
        }
        if (card) {
          card.querySelectorAll('[data-lot-bucket-block]').forEach(function (block) {
            block.style.display = (filter === 'ALL' || block.dataset.lotBucketBlock === filter) ? '' : 'none';
          });
        }
        return;
      }

      const toggle = event.target.closest('[data-lot-target]');
      if (toggle) {
        const target = document.getElementById(toggle.dataset.lotTarget || '');
        if (target) {
          const open = !target.classList.contains('open');
          target.classList.toggle('open', open);
          toggle.classList.toggle('open', open);
        }
        return;
      }

      const adjustBtn = event.target.closest('[data-lot-adjust]');
      if (!adjustBtn) return;
      event.preventDefault();
      event.stopPropagation();
      try {
        openLotAdjustment(JSON.parse(adjustBtn.dataset.lotAdjust || '{}'));
      } catch (err) {
        showLotAlert('danger', 'Data lot tidak valid.');
      }
    });

    physEl.addEventListener('input', function () { fillTypeAndReasons(typeEl.value); });
    typeEl.addEventListener('change', function () { fillTypeAndReasons(typeEl.value); });

    submitEl.addEventListener('click', async function () {
      const physical = parseFloat(physEl.value || 0);
      const systemQty = currentSystemQty();
      const profileSystemQty = currentProfileSystemQty();
      const diff = physical - systemQty;
      if (!dateEl.value) { showLotAlert('danger', 'Tanggal recon wajib diisi.'); return; }
      if (!(physical >= 0)) { showLotAlert('danger', 'Stok fisik tidak valid.'); return; }
      if (Math.abs(diff) < 0.0001) { showLotAlert('danger', 'Selisih masih 0, tidak ada adjustment yang diposting.'); return; }
      if (!typeEl.value) { showLotAlert('danger', 'Jenis adjustment wajib dipilih.'); return; }

      const original = submitEl.innerHTML;
      submitEl.disabled = true;
      submitEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting...';
      showLotAlert('info', 'Memposting adjustment lot...');

      try {
        let url = '';
        let body = {};
        if (lotCtx.source_type === 'component') {
          url = COMP_RECON_ADJ_URL;
          body = {
            opname_date: dateEl.value,
            location_type: lotCtx.recon_location_type || 'REGULER',
            division_id: parseInt(lotCtx.division_id || 0, 10) || null,
            division_code: lotCtx.division_code || '',
            component_id: parseInt(lotCtx.component_id || 0, 10),
            uom_id: parseInt(lotCtx.uom_id || 0, 10),
            lot_id: parseInt(lotCtx.lot_id || 0, 10),
            system_qty: profileSystemQty,
            physical_qty: Math.max(0, profileSystemQty + diff),
            avg_cost: parseFloat(lotCtx.unit_cost || 0),
            adjustment_type: typeEl.value,
            reason_code: reasonEl.value || 'other',
            notes: noteEl.value.trim()
          };
        } else {
          url = MAT_RECON_ADJ_URL;
          body = {
            opname_date: dateEl.value,
            division_id: parseInt(lotCtx.division_id || 0, 10),
            destination_type: lotCtx.destination_type || 'OTHER',
            identity_key: lotCtx.identity_key || lotCtx.profile_key || '',
            physical_qty_content: Math.max(0, profileSystemQty + diff),
            system_qty_content: profileSystemQty,
            adjustment_type: typeEl.value,
            reason_code: reasonEl.value || 'other',
            notes: noteEl.value.trim(),
            lot_id: parseInt(lotCtx.lot_id || 0, 10) || null,
            item_id: parseInt(lotCtx.item_id || 0, 10) || null,
            material_id: parseInt(lotCtx.material_id || 0, 10) || null,
            buy_uom_id: parseInt(lotCtx.buy_uom_id || 0, 10) || null,
            content_uom_id: parseInt(lotCtx.content_uom_id || 0, 10),
            profile_key: lotCtx.profile_key || '',
            profile_name: lotCtx.profile_name || '',
            profile_brand: lotCtx.profile_brand || '',
            profile_description: lotCtx.profile_description || '',
            profile_expired_date: lotCtx.profile_expired_date || '',
            profile_content_per_buy: parseFloat(lotCtx.profile_content_per_buy || 1),
            profile_buy_uom_code: lotCtx.profile_buy_uom_code || '',
            profile_content_uom_code: lotCtx.profile_content_uom_code || lotCtx.uom_code || '',
            avg_cost_per_content: parseFloat(lotCtx.unit_cost || 0)
          };
        }
        await parseJsonResponse(await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify(body)
        }));
        showLotAlert('success', 'Adjustment berhasil diposting. Memuat ulang dashboard...');
        window.setTimeout(function () { window.location.reload(); }, 900);
      } catch (err) {
        showLotAlert('danger', escHtml(err.message || 'Posting adjustment gagal.'));
        submitEl.disabled = false;
        submitEl.innerHTML = original;
      }
    });
  })();

  // ─── Stok Kritis Detail — JS filter no reload ─────────────
  const critTabs = document.getElementById('critTabs');
  const critList = document.getElementById('critList');
  if (critTabs && critList) {
    critTabs.querySelectorAll('.fd-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        critTabs.querySelectorAll('.fd-tab').forEach(function(b) { b.classList.remove('on'); });
        btn.classList.add('on');
        const loc = btn.dataset.critloc;
        critList.querySelectorAll('.fd-item').forEach(function (row) {
          row.style.display = (loc === 'ALL' || row.dataset.critloc === loc) ? '' : 'none';
        });
      });
    });
  }

  // Analisa produksi: filter cepat tanpa menghitung ulang atau memuat ulang halaman.
  const productionSuggestionTabs = document.getElementById('productionSuggestionTabs');
  if (productionSuggestionTabs) {
    const productionSuggestionRows = document.querySelectorAll('[data-production-suggestion-division]');
    productionSuggestionTabs.querySelectorAll('[data-production-suggestion-tab]').forEach(function (button) {
      button.addEventListener('click', function () {
        const selectedDivision = button.dataset.productionSuggestionTab || 'ALL';
        productionSuggestionTabs.querySelectorAll('[data-production-suggestion-tab]').forEach(function (tab) {
          const isActive = tab === button;
          tab.classList.toggle('on', isActive);
          tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        productionSuggestionRows.forEach(function (row) {
          row.hidden = selectedDivision !== 'ALL'
            && row.dataset.productionSuggestionDivision !== selectedDivision;
        });
      });
    });
  }
});
</script>
