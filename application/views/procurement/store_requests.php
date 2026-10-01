<?php
$hasSchema = !empty($has_schema);
$filters = $filters ?? [];
$rows = $rows ?? [];
$summary = $summary ?? [];
$filteredSummary = $filtered_summary ?? [];
$timelineMap = $timeline_map ?? [];
$lineRows = $line_rows ?? [];
$lineSummary = $line_summary ?? [];
$divisionOptions = $division_options ?? [];
$statusOptions = $status_options ?? [];
$destinationOptions = $destination_options ?? [];
$destinationGuardMap = $destination_guard_map ?? [];
$limit = (int)($limit ?? 50);
$activeTab = in_array((string)($active_tab ?? 'nota'), ['nota', 'rincian'], true) ? (string)$active_tab : 'nota';
$canCreate = !empty($can_create);
$canEdit = !empty($can_edit);
$canRepairHistory = !empty($can_repair_history);
$procurementMutationCsrfToken = (string)($procurement_mutation_csrf_token ?? '');
$monthAttentionSummary = (array)($month_attention_summary ?? []);
$usagePurposeAttention = (array)($usage_purpose_attention ?? []);

$baseFilters = [
  'q' => (string)($filters['q'] ?? ''),
  'status' => (string)($filters['status'] ?? ''),
  'division_id' => (string)($filters['division_id'] ?? ''),
  'destination_type' => (string)($filters['destination_type'] ?? ''),
  'date_start' => (string)($filters['date_start'] ?? ''),
  'date_end' => (string)($filters['date_end'] ?? ''),
  'limit' => (string)$limit,
];
$tabNotaUrl = site_url('store-requests') . '?' . http_build_query(array_merge($baseFilters, ['tab' => 'nota']));
$tabRincianUrl = site_url('store-requests') . '?' . http_build_query(array_merge($baseFilters, ['tab' => 'rincian']));
$resetUrl = site_url('store-requests') . '?tab=' . urlencode($activeTab);
$activeStatus = strtoupper(trim((string)($filters['status'] ?? '')));
if ($activeStatus === '') {
  $activeStatus = 'ALL';
}
$statusTabOptions = array_merge(['ALL'], array_values(array_filter(array_map(static function ($value) {
  return strtoupper(trim((string)$value));
}, $statusOptions), static function ($value) {
  return $value !== '';
})));
$statusTabOptions = array_values(array_unique($statusTabOptions));
$statusLabel = static function (string $value): string {
  $value = strtoupper(trim($value));
  if ($value === '' || $value === 'ALL') {
    return 'Semua';
  }
  return ucwords(strtolower(str_replace('_', ' ', $value)));
};
$usagePurposeLabel = static function (string $purpose): string {
  return strtoupper(trim($purpose)) === 'OPERASIONAL' ? 'Operasional' : 'Persediaan Produksi';
};
$usagePurposeIssueLabel = static function (string $issueType): string {
  return strtoupper(trim($issueType)) === 'MATERIAL_AS_OPERATIONAL'
    ? 'Material sebagai operasional'
    : 'Tujuan berbeda dari default';
};
$buildStatusTabUrl = static function (string $statusValue) use ($baseFilters, $activeTab): string {
  $filters = $baseFilters;
  $filters['status'] = $statusValue === 'ALL' ? '' : $statusValue;
  $filters['tab'] = $activeTab;
  return site_url('store-requests') . '?' . http_build_query($filters);
};
$summaryStatusText = $statusLabel($activeStatus);
$summaryRangeText = trim(((string)($filters['date_start'] ?? '') !== '' ? (string)($filters['date_start'] ?? '') : '-') . ' s/d ' . ((string)($filters['date_end'] ?? '') !== '' ? (string)($filters['date_end'] ?? '') : '-'));
$srPageCount = count($rows);
$srPageReqValue = 0.0;
$srPageFulfilledValue = 0.0;
foreach ($rows as $pageRow) {
  $srPageReqValue += (float)($pageRow['req_total_value'] ?? 0);
  $srPageFulfilledValue += (float)($pageRow['fulfilled_total_value'] ?? 0);
}
$srLinePageCount = count($lineRows);
$srLinePageReqValue = 0.0;
$srLinePageFulfilledValue = 0.0;
foreach ($lineRows as $lineRow) {
  $srLinePageReqValue += (float)($lineRow['req_total_value'] ?? 0);
  $srLinePageFulfilledValue += (float)($lineRow['fulfilled_total_value'] ?? 0);
}
?>

<style>
  .sr-nota-table { table-layout: fixed; }
  .sr-nota-table th,
  .sr-nota-table td { vertical-align: top; }
  .sr-nota-table th:nth-child(1), .sr-nota-table td:nth-child(1) { width: 16%; }
  .sr-nota-table th:nth-child(2), .sr-nota-table td:nth-child(2) { width: 32%; }
  .sr-nota-table th:nth-child(3), .sr-nota-table td:nth-child(3) { width: 10%; }
  .sr-nota-table th:nth-child(4), .sr-nota-table td:nth-child(4) { width: 16%; }
  .sr-nota-table th:nth-child(5), .sr-nota-table td:nth-child(5) { width: 26%; }
  .sr-tab-link { font-weight: 600; }
  .sr-status-tab-link { font-weight: 600; white-space: nowrap; }
  .sr-view-tab-link { font-weight: 700; }
  .sr-scroll { max-height: 260px; overflow: auto; }
  .sr-status-legend code { font-size: 11px; }
  .sr-note-meta { min-width: 0; }
  .sr-note-stack { display: flex; flex-direction: column; gap: 2px; }
  .sr-note-qty strong { display: block; font-size: 13px; }
  .sr-note-qty small { display: block; color: #6c757d; }
  .sr-note-value strong { display: block; font-size: 13px; }
  .sr-status-badge { font-size: 11px; letter-spacing: .02em; }
  .sr-request-meta { display: flex; flex-direction: column; gap: 3px; }
  .sr-request-meta strong { font-size: 13px; }
  .sr-qty-stack { display: flex; flex-direction: column; gap: 2px; }
  .sr-detail-table { table-layout: fixed; }
  .sr-detail-table th,
  .sr-detail-table td { vertical-align: top; }
  .sr-detail-table th:nth-child(1), .sr-detail-table td:nth-child(1) { width: 14%; }
  .sr-detail-table th:nth-child(2), .sr-detail-table td:nth-child(2) { width: 14%; }
  .sr-detail-table th:nth-child(3), .sr-detail-table td:nth-child(3) { width: 10%; }
  .sr-detail-table th:nth-child(4), .sr-detail-table td:nth-child(4) { width: 22%; }
  .sr-detail-table th:nth-child(5), .sr-detail-table td:nth-child(5) { width: 10%; }
  .sr-detail-table th:nth-child(6), .sr-detail-table td:nth-child(6) { width: 10%; }
  .sr-detail-table th:nth-child(7), .sr-detail-table td:nth-child(7) { width: 10%; }
  .sr-detail-table th:nth-child(8), .sr-detail-table td:nth-child(8) { width: 10%; }
  .sr-detail-title { display:block; font-weight:700; font-size:12px; line-height:1.25; color:#243445; }
  .sr-detail-subtext { display:block; color:#6c757d; font-size:11px; line-height:1.2; margin-top:2px; }
  .sr-detail-num { display:block; font-weight:700; font-size:12px; line-height:1.2; }
  .sr-summary-card { border:0; box-shadow:0 6px 22px rgba(67,89,113,.08); }
  .sr-summary-card .card-body { padding:0.9rem 1rem; }
  .sr-summary-head { display:flex; align-items:flex-start; justify-content:space-between; gap:0.75rem; margin-bottom:0.45rem; }
  .sr-summary-kicker { display:block; color:#7b8190; font-size:0.72rem; text-transform:uppercase; letter-spacing:.05em; margin-bottom:0.2rem; }
  .sr-summary-count { font-size:1.05rem; font-weight:800; color:#243445; line-height:1.1; }
  .sr-summary-icon { width:2rem; height:2rem; border-radius:.7rem; display:inline-flex; align-items:center; justify-content:center; background:rgba(168,16,39,.08); color:#a81027; flex:0 0 2rem; }
  .sr-summary-value { display:block; font-weight:800; color:#243445; font-size:.92rem; line-height:1.15; margin-bottom:.18rem; }
  .sr-summary-meta { display:flex; flex-wrap:wrap; gap:.35rem; }
  .sr-summary-chip { display:inline-flex; align-items:center; padding:.18rem .5rem; border-radius:999px; background:rgba(67,89,113,.08); color:#566070; font-size:.68rem; line-height:1.15; }
  .sr-table-footer { border-top:1px solid rgba(67,89,113,.12); background:rgba(67,89,113,.04); padding:10px 12px; font-size:12px; }
  .sr-footer-block { padding:.45rem .55rem; border-radius:.7rem; background:rgba(255,255,255,.76); height:100%; }
  .sr-table-footer strong { display:block; color:#243445; font-size:12px; }
  .sr-table-footer span { color:#6c757d; font-size:11px; }
  .sr-footer-value { display:block; font-size:13px; font-weight:800; color:#243445; line-height:1.2; margin-top:.12rem; }
  @media (max-width: 767.98px) {
    .sr-summary-card .card-body { padding:.8rem .85rem; }
    .sr-summary-count { font-size:.96rem; }
    .sr-summary-value { font-size:.86rem; }
    .sr-table-footer { padding:.55rem .65rem; }
  }
  .sr-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid #dfe6e2;
    border-radius: 24px;
    background: linear-gradient(135deg, #fbfffd 0%, #f3fbf7 100%);
    box-shadow: 0 12px 30px rgba(67,89,113,.08);
  }
  .sr-hero::before,
  .sr-hero::after {
    content: '';
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
  }
  .sr-hero::before {
    width: 280px;
    height: 280px;
    top: -130px;
    right: -90px;
    background: radial-gradient(circle, rgba(31, 93, 84, .08) 0%, rgba(31, 93, 84, 0) 72%);
  }
  .sr-hero::after {
    width: 220px;
    height: 220px;
    left: -70px;
    bottom: -130px;
    background: radial-gradient(circle, rgba(199, 119, 50, .08) 0%, rgba(199, 119, 50, 0) 72%);
  }
  .sr-hero-body {
    position: relative;
    z-index: 1;
    padding: .95rem 1.15rem;
  }
  .sr-hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .28rem .62rem;
    border-radius: 999px;
    background: rgba(255,255,255,.14);
    color: #1f5d54;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    border: 1px solid rgba(31, 93, 84, .14);
  }
  .sr-hero-title {
    margin: 0 0 .35rem;
    color: #21302c;
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.1;
  }
  .sr-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin-top: .55rem;
  }
  .sr-hero-chip {
    display: inline-flex;
    align-items: center;
    padding: .34rem .68rem;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #dfe6e2;
    color: #4d5f59;
    font-size: .75rem;
    font-weight: 600;
  }
  .sr-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: center;
  }
  .sr-hero-btn {
    border-radius: 14px;
    padding: .58rem .88rem;
    font-weight: 800;
    box-shadow: 0 8px 18px rgba(24, 16, 17, .12);
  }
  .sr-hero-actions {
    align-self: center;
  }
  .sr-board-card,
  .sr-filter-card,
  .sr-legend-card {
    border: 0;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 14px 30px rgba(67,89,113,.09);
  }
  .sr-summary-card {
    --sr-accent: #1f5d54;
    --sr-soft: rgba(31, 93, 84, .14);
    --sr-surface: #effbf7;
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    background: linear-gradient(145deg, var(--sr-surface) 0%, #fff 68%);
    box-shadow: 0 16px 32px rgba(67,89,113,.1);
    border: 1px solid rgba(214, 223, 218, .9);
  }
  .sr-summary-card::before {
    content: '';
    position: absolute;
    inset: auto -24px -34px auto;
    width: 130px;
    height: 130px;
    border-radius: 50%;
    background: radial-gradient(circle, var(--sr-soft) 0%, rgba(255,255,255,0) 72%);
    pointer-events: none;
  }
  .sr-summary-card .card-body {
    position: relative;
    z-index: 1;
    padding: 1rem 1.05rem;
  }
  .sr-summary-card--primary {
    --sr-accent: #1f5d54;
    --sr-soft: rgba(31, 93, 84, .16);
    --sr-surface: #effaf7;
  }
  .sr-summary-card--flow {
    --sr-accent: #1d4ed8;
    --sr-soft: rgba(29, 78, 216, .15);
    --sr-surface: #eef4ff;
  }
  .sr-summary-card--done {
    --sr-accent: #15803d;
    --sr-soft: rgba(21, 128, 61, .15);
    --sr-surface: #eefbf0;
  }
  .sr-summary-card--pending {
    --sr-accent: #b45309;
    --sr-soft: rgba(180, 83, 9, .16);
    --sr-surface: #fff7eb;
  }
  .sr-summary-kicker {
    color: var(--sr-accent);
    font-weight: 800;
    letter-spacing: .08em;
  }
  .sr-summary-count,
  .sr-summary-value {
    color: #1f2a39;
  }
  .sr-summary-icon {
    background: var(--sr-soft);
    color: var(--sr-accent);
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 18px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
  }
  .sr-summary-chip {
    background: rgba(255,255,255,.94);
    border: 1px solid rgba(31, 42, 57, .12);
    color: #46515f;
    font-weight: 600;
  }
  .sr-tab-strip {
    gap: .55rem;
    border: 0;
  }
  .sr-tab-strip .nav-item {
    margin: 0;
  }
  .sr-tab-strip .nav-link {
    border: 0;
    border-radius: 15px;
    padding: .55rem .85rem;
    background: #eef3f1;
    color: #405651;
    font-weight: 700;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
    border: 1px solid #d6e0dc;
  }
  .sr-tab-strip .nav-link:hover {
    color: #2e433f;
    background: #e2ece8;
  }
  .sr-tab-strip .nav-link.active {
    background: #1f5d54;
    color: #f6fffc;
    box-shadow: 0 14px 24px rgba(20, 52, 47, .18);
    border-color: #1f5d54;
  }
  .sr-status-tab-link {
    font-weight: 600;
    white-space: nowrap;
  }
  .sr-view-tab-link {
    font-weight: 700;
    border-color: #cec2b8 !important;
    background: #efe8e2 !important;
    color: #544740 !important;
  }
  .sr-view-tab-link:hover {
    color: #443a34 !important;
    background: #e7ddd5 !important;
    border-color: #c6b7aa !important;
  }
  .sr-view-tab-link.active {
    background: #2f2a4f !important;
    border-color: #2f2a4f !important;
    color: #fff !important;
    box-shadow: 0 6px 14px rgba(47, 42, 79, .2);
  }
  .sr-filter-card .card-body,
  .sr-board-card .card-body {
    padding: 1rem 1rem 0;
  }
  .sr-filter-card .form-control,
  .sr-filter-card .form-select {
    border-radius: 12px;
    border-color: rgba(73, 97, 91, .2);
    background: #fffdfb;
  }
  .sr-filter-card .btn,
  .sr-board-card .btn {
    border-radius: 12px;
    font-weight: 700;
  }
  .sr-filter-grid {
    display: grid;
    gap: .65rem;
    align-items: end;
  }
  .sr-filter-field {
    min-width: 0;
  }
  .sr-filter-field .form-label {
    white-space: nowrap;
  }
  .sr-filter-field .form-control,
  .sr-filter-field .form-select {
    min-width: 0;
  }
  .sr-filter-field--date .form-control {
    min-width: 148px;
  }
  .sr-filter-field--limit .form-select {
    min-width: 96px;
  }
  .sr-filter-actions {
    display: flex;
    gap: .5rem;
    justify-content: flex-end;
    align-items: center;
  }
  .sr-filter-actions .btn {
    min-width: 120px;
  }
  .sr-filter-actions .btn-primary {
    background: #c73f2b;
    border-color: #c73f2b;
    box-shadow: 0 10px 20px rgba(199, 63, 43, .18);
  }
  .sr-filter-actions .btn-outline-secondary {
    background: #fff;
    border-color: #a9b2be;
    color: #4b5563;
  }
  @media (min-width: 992px) {
    .sr-filter-grid {
      grid-template-columns: minmax(0, 1.9fr) minmax(126px, .95fr) minmax(126px, .95fr) minmax(126px, .95fr) minmax(148px, 1.05fr) minmax(148px, 1.05fr) minmax(92px, .62fr);
    }
    .sr-filter-actions {
      grid-column: 1 / -1;
      margin-top: .1rem;
    }
  }
  @media (max-width: 991.98px) {
    .sr-filter-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .sr-filter-actions {
      grid-column: 1 / -1;
    }
  }
  @media (max-width: 575.98px) {
    .sr-filter-grid {
      grid-template-columns: minmax(0, 1fr);
    }
    .sr-filter-field--date .form-control,
    .sr-filter-field--limit .form-select {
      min-width: 0;
    }
    .sr-filter-actions {
      flex-direction: column;
      align-items: stretch;
    }
    .sr-filter-actions .btn {
      width: 100%;
    }
  }
  .sr-table-main thead th {
    white-space: nowrap;
  }
  .sr-cell-left { text-align: left; }
  .sr-cell-center { text-align: center; }
  .sr-cell-right { text-align: right; }
  .sr-detail-table td,
  .sr-detail-table th {
    vertical-align: middle;
  }
  .sr-plain-text {
    font-weight: 500;
    color: #334155;
    line-height: 1.35;
  }
  .sr-legend-card .card-body {
    background: linear-gradient(180deg, #fffaf3 0%, #ffffff 100%);
  }
  .sr-month-warning {
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(185, 28, 28, .08);
  }
  .sr-purpose-flare {
    border: 1px solid #e8c47e;
    border-radius: 18px;
    background: linear-gradient(135deg, #fffaf0 0%, #fff4de 100%);
    box-shadow: 0 10px 24px rgba(176, 111, 26, .08);
    color: #5b4123;
  }
  .sr-purpose-flare-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.85rem; }
  .sr-purpose-flare-icon { display:inline-flex; align-items:center; justify-content:center; width:2.35rem; height:2.35rem; border-radius:.85rem; background:#9c5b10; color:#fff; flex:0 0 auto; }
  .sr-purpose-flare-title { color:#72420b; font-weight:800; }
  .sr-purpose-flare-meta { color:#806043; font-size:.76rem; line-height:1.35; }
  .sr-purpose-flare-chips { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.35rem; }
  .sr-purpose-flare-chip { display:inline-flex; align-items:center; border:1px solid #e5bd81; border-radius:999px; padding:.2rem .52rem; background:rgba(255,255,255,.72); color:#775027; font-size:.69rem; font-weight:700; }
  .sr-purpose-flare-list { display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:.45rem; margin-top:.75rem; }
  .sr-purpose-flare-item { display:block; padding:.52rem .62rem; border:1px solid rgba(173,111,27,.22); border-radius:12px; background:rgba(255,255,255,.72); color:inherit; text-decoration:none; }
  .sr-purpose-flare-item:hover { border-color:#b86d17; color:inherit; background:#fff; }
  .sr-purpose-flare-doc { display:flex; align-items:center; justify-content:space-between; gap:.45rem; color:#75430d; font-size:.72rem; font-weight:800; }
  .sr-purpose-flare-line { display:block; margin-top:.15rem; color:#2e2b28; font-size:.78rem; font-weight:700; line-height:1.25; }
  .sr-purpose-flare-item small { display:block; margin-top:.2rem; color:#806043; font-size:.68rem; line-height:1.25; }
  .sr-status-audit { display:block; margin-top:.35rem; color:#806043; font-size:.68rem; line-height:1.3; white-space:normal; }
  .sr-status-audit strong { color:#5d3519; }
  .sr-board-card .action-icon-btn,
  #srCreateModal .action-icon-btn {
    width: 30px !important;
    height: 30px !important;
    min-width: 30px !important;
    border-radius: 8px !important;
    padding: 0 !important;
  }
  .sr-board-card .action-icon-btn i,
  #srCreateModal .action-icon-btn i {
    font-size: .92rem !important;
  }
  #srCreateModal .modal-dialog {
    max-width: min(1480px, 96vw);
  }
  #srCreateModal .modal-body {
    padding-bottom: .85rem;
  }
  #srCreateModal .table,
  #srCreateModal .sr-scroll table {
    min-width: 1240px;
  }
  #srCreateModal #srLineTableBody {
    white-space: nowrap;
  }
  #srCreateModal .table-responsive {
    overflow-x: auto;
    padding-bottom: .35rem;
  }
  #srCreateModal .table-responsive > .table {
    min-width: 1480px;
  }
  #srCreateModal .sr-usage-purpose {
    min-width: 190px;
    width: 100%;
  }
  #srCreateModal .table th,
  #srCreateModal .sr-scroll th {
    white-space: nowrap;
  }
  #srCreateModal .table th:nth-child(4),
  #srCreateModal .table td:nth-child(4) {
    min-width: 210px;
  }
  #srCreateModal .table th:nth-child(5),
  #srCreateModal .table td:nth-child(5) {
    min-width: 110px;
  }
  #srCreateModal .table th:nth-child(6),
  #srCreateModal .table td:nth-child(6),
  #srCreateModal .table th:nth-child(7),
  #srCreateModal .table td:nth-child(7) {
    min-width: 130px;
  }
  #srCreateModal .table th:nth-child(8),
  #srCreateModal .table td:nth-child(8) {
    min-width: 100px;
  }
  #srCreateModal .table th:nth-child(9),
  #srCreateModal .table td:nth-child(9),
  #srCreateModal .table th:nth-child(10),
  #srCreateModal .table td:nth-child(10) {
    min-width: 110px;
  }
  @media (max-width: 767.98px) {
    .sr-hero-title {
      font-size: 1.45rem;
    }
    .sr-hero-body {
      padding: .9rem 1rem;
    }
    .sr-hero-actions {
      width: 100%;
    }
    .sr-hero-actions .btn {
      width: 100%;
      justify-content: center;
    }
    .sr-purpose-flare-chips { justify-content:flex-start; }
  }
</style>

<div class="card sr-hero mb-3">
  <div class="sr-hero-body d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
      <h4 class="sr-hero-title d-flex align-items-center gap-2"><i class="ri ri-inbox-archive-line text-primary"></i><span><?php echo html_escape($title ?? 'Store Request'); ?></span></h4>
      <div class="sr-hero-meta">
        <span class="sr-hero-chip">Status: <?php echo html_escape($summaryStatusText); ?></span>
        <span class="sr-hero-chip"><?php echo html_escape($summaryRangeText); ?></span>
        <span class="sr-hero-chip">View: <?php echo $activeTab === 'rincian' ? 'Per Rincian' : 'Per Nota'; ?></span>
      </div>
    </div>
    <div class="sr-hero-actions">
      <?php if ($canCreate): ?>
        <a href="<?php echo site_url('store-requests/create'); ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 sr-hero-btn"><i class="ri ri-file-list-3-line"></i>Form Full SR</a>
        <button type="button" class="btn btn-danger d-inline-flex align-items-center gap-2 sr-hero-btn" data-bs-toggle="modal" data-bs-target="#srCreateModal"><i class="ri ri-add-line"></i>Tambah SR</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ((int)($monthAttentionSummary['pending_fulfillment_count'] ?? 0) > 0): ?>
<div class="alert alert-danger sr-month-warning mb-3" role="alert">
  <div class="fw-semibold mb-1">Perhatian Bulan Ini</div>
  <div>Masih ada <?php echo number_format((int)($monthAttentionSummary['pending_fulfillment_count'] ?? 0)); ?> store request bulan ini yang belum fulfillment penuh. Nilai request yang masih perlu ditindaklanjuti: Rp <?php echo number_format((float)($monthAttentionSummary['pending_fulfillment_value_total'] ?? 0), 2, ',', '.'); ?>.</div>
</div>
<?php endif; ?>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'store-request']); ?>

<?php if (!$hasSchema): ?>
<div class="alert alert-warning">Schema Store Request belum tersedia. Jalankan SQL terbaru procurement.</div>
<?php endif; ?>

<div id="srAlert"></div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card h-100 sr-summary-card sr-summary-card--primary"><div class="card-body"><div class="sr-summary-head"><div><span class="sr-summary-kicker">Total SR</span><div class="sr-summary-count"><?php echo (int)($summary['total'] ?? 0); ?> nota</div></div><span class="sr-summary-icon"><i class="ri ri-inbox-archive-line"></i></span></div><span class="sr-summary-value">Rp <?php echo number_format((float)($summary['req_value_total'] ?? 0), 2, ',', '.'); ?></span><div class="sr-summary-meta"><span class="sr-summary-chip">Status: <?php echo html_escape($summaryStatusText); ?></span><span class="sr-summary-chip"><?php echo html_escape($summaryRangeText); ?></span></div></div></div></div>
  <div class="col-md-3"><div class="card h-100 sr-summary-card sr-summary-card--flow"><div class="card-body"><div class="sr-summary-head"><div><span class="sr-summary-kicker">Submitted / Approval</span><div class="sr-summary-count"><?php echo (int)($summary['submitted'] ?? 0); ?> nota</div></div><span class="sr-summary-icon"><i class="ri ri-file-check-line"></i></span></div><span class="sr-summary-value"><?php echo (int)($summary['approved'] ?? 0); ?> nota approved/partial</span><div class="sr-summary-meta"><span class="sr-summary-chip">Menunggu fulfill</span></div></div></div></div>
  <div class="col-md-3"><div class="card h-100 sr-summary-card sr-summary-card--done"><div class="card-body"><div class="sr-summary-head"><div><span class="sr-summary-kicker">SR Fulfilled</span><div class="sr-summary-count"><?php echo (int)($summary['fulfilled'] ?? 0); ?> nota</div></div><span class="sr-summary-icon"><i class="ri ri-check-double-line"></i></span></div><span class="sr-summary-value">Rp <?php echo number_format((float)($summary['fulfilled_value_total'] ?? 0), 2, ',', '.'); ?></span><div class="sr-summary-meta"><span class="sr-summary-chip">Sudah fulfilled</span></div></div></div></div>
  <div class="col-md-3"><div class="card h-100 sr-summary-card sr-summary-card--pending"><div class="card-body"><div class="sr-summary-head"><div><span class="sr-summary-kicker">Belum Fulfilled</span><div class="sr-summary-count"><?php echo (int)($summary['pending_fulfillment_count'] ?? 0); ?> nota</div></div><span class="sr-summary-icon"><i class="ri ri-timer-line"></i></span></div><span class="sr-summary-value">Rp <?php echo number_format((float)($summary['pending_fulfillment_value_total'] ?? 0), 2, ',', '.'); ?></span><div class="sr-summary-meta"><span class="sr-summary-chip">Belum selesai penuh</span></div></div></div></div>
</div>

<div class="card mb-3 sr-filter-card">
  <div class="card-body">
    <form class="sr-filter-grid" method="get" action="<?php echo site_url('store-requests'); ?>">
      <input type="hidden" name="tab" value="<?php echo html_escape($activeTab); ?>">
      <div class="sr-filter-field sr-filter-field--search"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No. SR, catatan, nama bahan / produk"></div>
      <div class="sr-filter-field sr-filter-field--status"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $st): ?><option value="<?php echo html_escape($st); ?>" <?php echo ((string)($filters['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo html_escape($st); ?></option><?php endforeach; ?></select></div>
      <div class="sr-filter-field sr-filter-field--division"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $d): ?><option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>><?php echo html_escape((string)($d['division_name'] ?? $d['name'] ?? ('DIV#'.$d['id']))); ?></option><?php endforeach; ?></select></div>
      <div class="sr-filter-field sr-filter-field--destination"><label class="form-label mb-1">Tujuan</label><select name="destination_type" class="form-select"><option value="">Semua</option><?php foreach($destinationOptions as $op): ?><option value="<?php echo html_escape((string)$op['value']); ?>" <?php echo ((string)($filters['destination_type'] ?? '') === (string)$op['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$op['label']); ?></option><?php endforeach; ?></select></div>
      <div class="sr-filter-field sr-filter-field--date"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="sr-filter-field sr-filter-field--date"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="sr-filter-field sr-filter-field--limit"><label class="form-label mb-1">Baris</label><select name="limit" class="form-select"><?php foreach([25,50,100,200] as $lm): ?><option value="<?php echo $lm; ?>" <?php echo $limit === $lm ? 'selected' : ''; ?>><?php echo $lm; ?></option><?php endforeach; ?></select></div>
      <div class="sr-filter-actions">
        <a href="<?php echo $resetUrl; ?>" class="btn btn-outline-secondary">Clear Filter</a>
        <button type="submit" class="btn btn-primary">Terapkan</button>
      </div>
    </form>
  </div>
</div>

<?php if ((int)($usagePurposeAttention['total_lines'] ?? 0) > 0): ?>
<div class="sr-purpose-flare mb-3 p-3" role="alert">
  <div class="sr-purpose-flare-head">
    <div class="d-flex gap-2">
      <span class="sr-purpose-flare-icon"><i class="ri ri-alarm-warning-line"></i></span>
      <div>
        <div class="sr-purpose-flare-title">Peruntukan bahan Store Request perlu ditinjau</div>
        <div class="sr-purpose-flare-meta">
          <?php echo number_format((int)($usagePurposeAttention['total_lines'] ?? 0)); ?> line pada
          <?php echo number_format((int)($usagePurposeAttention['document_count'] ?? 0)); ?> SR di rentang tanggal ini
          tidak selaras dengan default material, atau merupakan material yang ditandai operasional. Data tidak diubah otomatis.
        </div>
      </div>
    </div>
    <div class="sr-purpose-flare-chips">
      <?php if ((int)($usagePurposeAttention['material_operational_count'] ?? 0) > 0): ?>
        <span class="sr-purpose-flare-chip"><?php echo number_format((int)$usagePurposeAttention['material_operational_count']); ?> material operasional</span>
      <?php endif; ?>
      <?php if ((int)($usagePurposeAttention['purpose_mismatch_count'] ?? 0) > 0): ?>
        <span class="sr-purpose-flare-chip"><?php echo number_format((int)$usagePurposeAttention['purpose_mismatch_count']); ?> tujuan ≠ default</span>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($usagePurposeAttention['rows'])): ?>
  <div class="sr-purpose-flare-list">
    <?php foreach ((array)$usagePurposeAttention['rows'] as $attentionRow): ?>
      <a class="sr-purpose-flare-item" href="<?php echo site_url('store-requests/detail/' . (int)($attentionRow['store_request_id'] ?? 0)); ?>">
        <span class="sr-purpose-flare-doc">
          <span><?php echo html_escape((string)($attentionRow['sr_no'] ?? '-')); ?> · <?php echo html_escape((string)($attentionRow['request_date'] ?? '-')); ?></span>
          <span><?php echo html_escape($usagePurposeIssueLabel((string)($attentionRow['issue_type'] ?? ''))); ?></span>
        </span>
        <span class="sr-purpose-flare-line"><?php echo html_escape((string)($attentionRow['line_name'] ?? '-')); ?></span>
        <small>Transaksi: <?php echo html_escape($usagePurposeLabel((string)($attentionRow['actual_usage_purpose'] ?? ''))); ?> · Default: <?php echo html_escape($usagePurposeLabel((string)($attentionRow['default_usage_purpose'] ?? ''))); ?></small>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card sr-board-card">
  <div class="card-body pb-0">
    <ul class="nav nav-pills sr-tab-strip mb-3" role="tablist">
      <?php foreach ($statusTabOptions as $statusOption): ?>
        <li class="nav-item" role="presentation">
          <a class="nav-link sr-status-tab-link <?php echo $activeStatus === $statusOption ? 'active' : ''; ?>" href="<?php echo html_escape($buildStatusTabUrl($statusOption)); ?>"><?php echo html_escape($statusLabel($statusOption)); ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <ul class="nav nav-pills sr-tab-strip mb-3" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link sr-tab-link sr-view-tab-link <?php echo $activeTab === 'nota' ? 'active' : ''; ?>" href="<?php echo $tabNotaUrl; ?>">Per Nota</a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link sr-tab-link sr-view-tab-link <?php echo $activeTab === 'rincian' ? 'active' : ''; ?>" href="<?php echo $tabRincianUrl; ?>">Per Rincian</a>
      </li>
    </ul>
  </div>
  <div class="tab-content p-0 border-0">
    <div class="tab-pane fade <?php echo $activeTab === 'nota' ? 'show active' : ''; ?>">
      <div class="table-responsive">
        <table class="table table-striped mb-0 sr-nota-table sr-table-main">
      <thead>
        <tr>
          <th>SR</th><th>Ringkasan</th><th>Status</th><th>Qty</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="5" class="text-center text-muted py-3">
            Belum ada Store Request.
            <?php if ($canCreate): ?>
              <button type="button" class="btn btn-sm btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#srCreateModal">Buat SR</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php else: foreach($rows as $r): ?>
        <?php
          $st = strtoupper((string)($r['status'] ?? 'DRAFT'));
          $rid = (int)($r['id'] ?? 0);
          $remainBuy = (float)($r['req_buy_total'] ?? 0) - (float)($r['fulfilled_buy_total'] ?? 0);
          $remainContent = (float)($r['req_content_total'] ?? 0) - (float)($r['fulfilled_content_total'] ?? 0);
          $timeline = (array)($timelineMap[$rid] ?? []);
          $approvalTrail = (array)($timeline['approvals'] ?? []);
          $latestReject = null;
          foreach ($approvalTrail as $approvalEvent) {
            if (strtoupper((string)($approvalEvent['action'] ?? '')) === 'REJECT') {
              $latestReject = $approvalEvent;
            }
          }
          $statusClass = 'secondary';
          if ($st === 'APPROVED') { $statusClass = 'success'; }
          elseif ($st === 'SUBMITTED') { $statusClass = 'primary'; }
          elseif ($st === 'PARTIAL_FULFILLED') { $statusClass = 'warning text-dark'; }
          elseif ($st === 'FULFILLED') { $statusClass = 'dark'; }
          elseif (in_array($st, ['REJECTED', 'VOID'], true)) { $statusClass = 'danger'; }
        ?>
        <tr>
          <td class="sr-note-meta"><div class="sr-request-meta"><strong><?php echo html_escape((string)($r['sr_no'] ?? '-')); ?></strong><span class="small text-muted"><?php echo html_escape((string)($r['request_date'] ?? '-')); ?></span><span class="small text-muted">Dibuat oleh <?php echo html_escape((string)($r['created_by_username'] ?? '-')); ?></span></div></td>
          <td class="sr-note-meta"><div class="sr-request-meta"><strong><?php echo html_escape((string)($r['division_name'] ?? '-')); ?> | <?php echo html_escape((string)($r['destination_type'] ?? '-')); ?></strong><span class="small text-muted">Need <?php echo html_escape((string)($r['needed_date'] ?? '-')); ?></span><span class="small text-muted">Line <?php echo (int)($r['line_count'] ?? 0); ?></span></div></td>
          <td>
            <span class="badge bg-<?php echo $statusClass; ?> sr-status-badge"><?php echo html_escape($st); ?></span>
            <?php if ($st === 'REJECTED' && !empty($latestReject)): ?>
              <span class="sr-status-audit" title="<?php echo html_escape((string)($latestReject['notes'] ?? '')); ?>">Ditolak oleh <strong><?php echo html_escape((string)($latestReject['actor_username'] ?? '-')); ?></strong><br><?php echo html_escape((string)($latestReject['created_at'] ?? '-')); ?></span>
            <?php endif; ?>
          </td>
          <td class="text-end sr-note-qty"><div class="sr-qty-stack"><strong><?php echo ui_num((float)($r['req_buy_total'] ?? 0)); ?> pack</strong><small>Fulfilled <?php echo ui_num((float)($r['fulfilled_buy_total'] ?? 0)); ?> pack</small><small>Sisa <?php echo ui_num($remainBuy); ?> pack</small></div></td>
          <td class="action-cell">
            <div class="d-flex gap-1 flex-nowrap justify-content-end">
              <a href="<?php echo site_url('store-requests/detail/' . $rid); ?>" class="btn btn-sm btn-outline-info action-icon-btn" title="Detail SR" aria-label="Detail SR"><i class="ri ri-eye-line"></i></a>
              <?php if ($canEdit || ($canRepairHistory && $st === 'VOID')): ?>
                <?php if ($st === 'DRAFT'): ?><button type="button" class="btn btn-sm btn-outline-primary action-icon-btn sr-action" data-id="<?php echo $rid; ?>" data-action="SUBMIT" title="Submit" aria-label="Submit"><i class="ri ri-send-plane-line"></i></button><?php endif; ?>
                <?php if ($st === 'SUBMITTED'): ?><button type="button" class="btn btn-sm btn-outline-success action-icon-btn sr-action" data-id="<?php echo $rid; ?>" data-action="APPROVE" title="Approve" aria-label="Approve"><i class="ri ri-check-line"></i></button><?php endif; ?>
                <?php if ($st === 'SUBMITTED'): ?><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn sr-action" data-id="<?php echo $rid; ?>" data-action="REJECT" title="Reject" aria-label="Reject"><i class="ri ri-close-line"></i></button><?php endif; ?>
                <?php if (in_array($st, ['APPROVED','PARTIAL_FULFILLED'], true)): ?><button type="button" class="btn btn-sm btn-outline-info action-icon-btn sr-split" data-id="<?php echo $rid; ?>" title="Cek Split" aria-label="Cek Split"><i class="ri ri-git-branch-line"></i></button><?php endif; ?>
                <?php if (in_array($st, ['APPROVED','PARTIAL_FULFILLED'], true)): ?><button type="button" class="btn btn-sm btn-outline-warning action-icon-btn sr-fulfill" data-id="<?php echo $rid; ?>" title="Fulfilled dari gudang" aria-label="Fulfilled dari gudang"><i class="ri ri-checkbox-circle-line"></i></button><?php endif; ?>
                <?php if (in_array($st, ['APPROVED','PARTIAL_FULFILLED'], true)): ?><button type="button" class="btn btn-sm btn-outline-primary action-icon-btn sr-gpo" data-id="<?php echo $rid; ?>" title="Generate PO Shortage" aria-label="Generate PO Shortage"><i class="ri ri-shopping-bag-3-line"></i></button><?php endif; ?>
                <?php if ($st === 'DRAFT'): ?><a href="<?php echo site_url('store-requests/edit/' . $rid); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" title="Edit Draft" aria-label="Edit Draft"><i class="ri ri-edit-line"></i></a><?php endif; ?>
                <?php if (in_array($st, ['DRAFT','SUBMITTED','APPROVED','REJECTED','PARTIAL_FULFILLED','FULFILLED'], true)): ?><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn sr-action" data-id="<?php echo $rid; ?>" data-action="VOID" title="Void" aria-label="Void"><i class="ri ri-close-circle-line"></i></button><?php endif; ?>
                <?php if ($st === 'VOID' && $canRepairHistory): ?><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn sr-repair-history" data-id="<?php echo $rid; ?>" title="Repair histori stok SR VOID" aria-label="Repair histori stok SR VOID"><i class="ri ri-tools-line"></i></button><?php endif; ?>
              <?php else: ?>
                <span class="text-muted small">-</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      </table>
      </div>
      <div class="sr-table-footer">
        <div class="row g-2">
          <div class="col-md-6"><div class="sr-footer-block"><strong>Pagination Saat Ini</strong><span class="sr-footer-value"><?php echo number_format($srPageCount); ?> nota</span><span>Nilai Req Rp <?php echo number_format($srPageReqValue, 2, ',', '.'); ?> | Nilai Fulfilled Rp <?php echo number_format($srPageFulfilledValue, 2, ',', '.'); ?></span></div></div>
          <div class="col-md-6"><div class="sr-footer-block"><strong>Total Range Filter</strong><span class="sr-footer-value"><?php echo number_format((int)($filteredSummary['total'] ?? 0)); ?> nota</span><span>Nilai Req Rp <?php echo number_format((float)($filteredSummary['req_value_total'] ?? 0), 2, ',', '.'); ?> | Nilai Fulfilled Rp <?php echo number_format((float)($filteredSummary['fulfilled_value_total'] ?? 0), 2, ',', '.'); ?></span></div></div>
        </div>
      </div>
    </div>
    <div class="tab-pane fade <?php echo $activeTab === 'rincian' ? 'show active' : ''; ?>">
      <div class="table-responsive">
        <table class="table table-striped mb-0 sr-detail-table sr-table-main">
          <thead>
            <tr>
              <th class="sr-cell-left">SR</th>
              <th class="sr-cell-left">Divisi / Tujuan</th>
              <th class="sr-cell-center">Status</th>
              <th class="sr-cell-left">Rincian</th>
              <th class="sr-cell-right">Request</th>
              <th class="sr-cell-right">Fulfilled</th>
              <th class="sr-cell-right">Sisa</th>
              <th class="sr-cell-right">Nilai</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($lineRows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">Belum ada data rincian Store Request.</td></tr>
          <?php else: foreach($lineRows as $ln): ?>
            <?php $remainBuy = (float)($ln['qty_buy_requested'] ?? 0) - (float)($ln['qty_buy_fulfilled'] ?? 0); ?>
            <?php $remain = (float)($ln['qty_content_requested'] ?? 0) - (float)($ln['qty_content_fulfilled'] ?? 0); ?>
            <tr>
              <td class="sr-cell-left">
                <a href="<?php echo site_url('store-requests/detail/' . (int)($ln['store_request_id'] ?? 0)); ?>"><span class="sr-detail-title"><?php echo html_escape((string)($ln['sr_no'] ?? '-')); ?></span></a>
                <span class="sr-detail-subtext"><?php echo html_escape((string)($ln['request_date'] ?? '-')); ?></span>
                <span class="sr-detail-subtext">Need <?php echo html_escape((string)($ln['needed_date'] ?? '-')); ?></span>
              </td>
              <td class="sr-cell-left">
                <span class="sr-detail-title"><?php echo html_escape((string)($ln['division_name'] ?? '-')); ?></span>
                <span class="sr-detail-subtext"><?php echo html_escape((string)($ln['destination_type'] ?? '-')); ?></span>
              </td>
              <td class="sr-cell-center">
                <span class="badge bg-light text-dark border"><?php echo html_escape((string)($ln['status'] ?? '-')); ?></span>
              </td>
              <td class="sr-cell-left">
                <span class="sr-detail-title"><?php echo html_escape((string)($ln['profile_name'] ?? '-')); ?></span>
                <span class="sr-detail-subtext">Jenis: <?php echo html_escape((string)($ln['effective_line_kind'] ?? $ln['line_kind'] ?? '-')); ?></span>
                <span class="sr-detail-subtext"><?php echo html_escape((string)($ln['profile_brand'] ?? '')); ?> <?php echo html_escape((string)($ln['profile_description'] ?? '')); ?></span>
              </td>
              <td class="sr-cell-right">
                <span class="sr-detail-num"><?php echo ui_num((float)($ln['qty_buy_requested'] ?? 0)); ?> <?php echo html_escape((string)($ln['profile_buy_uom_code'] ?? '')); ?></span>
                <span class="sr-detail-subtext"><?php echo ui_num((float)($ln['qty_content_requested'] ?? 0)); ?> <?php echo html_escape((string)($ln['profile_content_uom_code'] ?? '')); ?></span>
              </td>
              <td class="sr-cell-right">
                <span class="sr-detail-num"><?php echo ui_num((float)($ln['qty_buy_fulfilled'] ?? 0)); ?> <?php echo html_escape((string)($ln['profile_buy_uom_code'] ?? '')); ?></span>
                <span class="sr-detail-subtext"><?php echo ui_num((float)($ln['qty_content_fulfilled'] ?? 0)); ?> <?php echo html_escape((string)($ln['profile_content_uom_code'] ?? '')); ?></span>
              </td>
              <td class="sr-cell-right">
                <span class="sr-detail-num"><?php echo ui_num($remainBuy); ?> <?php echo html_escape((string)($ln['profile_buy_uom_code'] ?? '')); ?></span>
                <span class="sr-detail-subtext"><?php echo ui_num($remain); ?> <?php echo html_escape((string)($ln['profile_content_uom_code'] ?? '')); ?></span>
              </td>
              <td class="sr-cell-right">
                <span class="sr-detail-num">Req Rp <?php echo number_format((float)($ln['req_total_value'] ?? 0), 2, ',', '.'); ?></span>
                <span class="sr-detail-subtext">Fulfilled Rp <?php echo number_format((float)($ln['fulfilled_total_value'] ?? 0), 2, ',', '.'); ?></span>
                <span class="sr-detail-subtext">Harga Rp <?php echo number_format((float)($ln['unit_cost_ref'] ?? 0), 2, ',', '.'); ?></span>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="sr-table-footer">
        <div class="row g-2">
          <div class="col-md-6"><div class="sr-footer-block"><strong>Pagination Saat Ini</strong><span class="sr-footer-value"><?php echo number_format($srLinePageCount); ?> baris</span><span>Nilai Req Rp <?php echo number_format($srLinePageReqValue, 2, ',', '.'); ?> | Nilai Fulfilled Rp <?php echo number_format($srLinePageFulfilledValue, 2, ',', '.'); ?></span></div></div>
          <div class="col-md-6"><div class="sr-footer-block"><strong>Total Range Filter</strong><span class="sr-footer-value"><?php echo number_format((int)($lineSummary['total_lines'] ?? 0)); ?> baris</span><span>Nilai Req Rp <?php echo number_format((float)($lineSummary['req_value_total'] ?? 0), 2, ',', '.'); ?> | Nilai Fulfilled Rp <?php echo number_format((float)($lineSummary['fulfilled_value_total'] ?? 0), 2, ',', '.'); ?></span></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($canCreate): ?>
<div class="modal fade" id="srCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Buat Store Request Manual</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="srCreateAlert"></div>
        <form id="srCreateForm">
          <div class="row g-2 mb-2">
            <div class="col-md-2"><label class="form-label mb-1">Tgl Request</label><input type="date" id="sr_request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="col-md-2"><label class="form-label mb-1">Tgl Butuh</label><input type="date" id="sr_needed_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="col-md-3"><label class="form-label mb-1">Divisi</label><select id="sr_division_id" class="form-select"><?php foreach($divisionOptions as $d): ?><option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape((string)($d['division_name'] ?? $d['name'] ?? ('DIV#'.$d['id']))); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label mb-1">Tujuan</label><select id="sr_destination_type" class="form-select"><?php foreach($destinationOptions as $op): ?><option value="<?php echo html_escape((string)$op['value']); ?>"><?php echo html_escape((string)$op['label']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label mb-1">Catatan</label><input type="text" id="sr_notes" class="form-control" placeholder="Opsional"></div>
          </div>

          <div class="border rounded p-2 mb-2">
            <label class="form-label mb-1">Cari Profile Stok Gudang</label>
            <div class="d-flex gap-2">
              <input type="text" id="sr_profile_q" class="form-control" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="Nama profile / item / material">
              <button type="button" id="btnSearchProfile" class="btn btn-outline-primary">Cari</button>
            </div>
            <div class="sr-scroll mt-2">
              <table class="table table-sm mb-0">
                <thead><tr><th>Profile</th><th>Keterangan</th><th>UOM</th><th class="text-end">Stok Gudang</th><th class="text-end">Harga Satuan</th><th>Exp Date</th><th>Tgl Beli Terakhir</th><th style="width:80px">Aksi</th></tr></thead>
                <tbody id="srProfileResults"><tr><td colspan="8" class="text-muted text-center py-2">Belum ada pencarian.</td></tr></tbody>
              </table>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead><tr><th>Profile</th><th>Keterangan</th><th>Jenis</th><th>Pemakaian</th><th>UOM</th><th class="text-end">Stok Gudang</th><th class="text-end">Harga Satuan</th><th>Exp Date</th><th>Qty Beli Req</th><th>Qty Isi Req</th><th>Aksi</th></tr></thead>
              <tbody id="srLineTableBody"><tr><td colspan="11" class="text-muted text-center py-2">Belum ada line.</td></tr></tbody>
            </table>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-outline-primary" id="btnCreateSrSubmit">Simpan & Submit</button>
        <button type="button" class="btn btn-primary" id="btnCreateSr" data-status="DRAFT">Simpan DRAFT</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="modal fade" id="srRejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title text-danger"><i class="ri ri-close-circle-line me-1"></i>Tolak Store Request</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small mb-3">Penolakan mengakhiri proses SR ini. Stok tidak akan dipindahkan ke divisi melalui dokumen ini.</div>
        <div class="small text-muted mb-2">Dokumen: <strong id="srRejectDocumentNo">-</strong></div>
        <div id="srRejectAlert"></div>
        <label for="srRejectReason" class="form-label">Alasan penolakan <span class="text-danger">*</span></label>
        <textarea id="srRejectReason" class="form-control" rows="3" maxlength="255" placeholder="Contoh: request duplikat, stok sudah dipenuhi dari SR lain" required></textarea>
        <div class="form-text">Alasan akan tersimpan dalam jejak status dan tidak dapat dikosongkan.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="btnConfirmSrReject"><i class="ri ri-close-circle-line me-1"></i>Tolak SR</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="srSplitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Preview Split SR (Gudang vs Shortage)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"><div id="srSplitModalBody" class="small text-muted">Belum ada data.</div></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-warning text-dark" id="btnSrSplitFulfill" hidden>Fulfill yang tersedia</button>
        <button type="button" class="btn btn-secondary" id="btnSrSplitGenPo" hidden>Generate PO shortage</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  'use strict';
  var canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
  var canCreate = <?php echo $canCreate ? 'true' : 'false'; ?>;
  var searchUrl = <?php echo json_encode(site_url('procurement/store-request/profile-search')); ?>;
  var storeUrl = <?php echo json_encode(site_url('procurement/store-request/store')); ?>;
  var actionUrlBase = <?php echo json_encode(site_url('procurement/store-request/action/')); ?>;
  var splitPreviewUrlBase = <?php echo json_encode(site_url('procurement/store-request/split-preview/')); ?>;
  var fulfillUrlBase = <?php echo json_encode(site_url('procurement/store-request/fulfill/')); ?>;
  var repairHistoryUrlBase = <?php echo json_encode(site_url('procurement/store-request/repair-history/')); ?>;
  var generatePoUrlBase = <?php echo json_encode(site_url('procurement/store-request/generate-po/')); ?>;
  var reloadUrl = <?php echo json_encode($resetUrl); ?>;
  var destinationGuardMap = <?php echo json_encode($destinationGuardMap); ?>;
  var usagePurposeOptions = [
    { value: 'BAHAN_BAKU', label: 'Persediaan Produksi' },
    { value: 'OPERASIONAL', label: 'Kebutuhan Operasional' }
  ];
  var canRepairHistory = <?php echo $canRepairHistory ? 'true' : 'false'; ?>;
  var procurementMutationCsrfToken = <?php echo json_encode($procurementMutationCsrfToken); ?>;
  var alertBox = document.getElementById('srAlert');
  var createAlertBox = document.getElementById('srCreateAlert');
  var splitModalEl = document.getElementById('srSplitModal');
  var splitModalBody = document.getElementById('srSplitModalBody');
  var splitFulfillBtn = document.getElementById('btnSrSplitFulfill');
  var splitGenPoBtn = document.getElementById('btnSrSplitGenPo');
  var rejectModalEl = document.getElementById('srRejectModal');
  var rejectReasonEl = document.getElementById('srRejectReason');
  var rejectAlertBox = document.getElementById('srRejectAlert');
  var rejectDocumentNoEl = document.getElementById('srRejectDocumentNo');
  var rejectConfirmBtn = document.getElementById('btnConfirmSrReject');
  var rejectRequestId = 0;
  var createLines = [];
  var profileSearchTimer = null;
  var profileSearchAbort = null;
  var currentSplitRequestId = 0;

  function flash(type, msg){ if(!alertBox) return; alertBox.innerHTML='<div class="alert alert-'+type+' py-2 mb-2">'+msg+'</div>'; }
  function flashCreate(type, msg){ if(!createAlertBox) return; createAlertBox.innerHTML='<div class="alert alert-'+type+' py-2 mb-2">'+msg+'</div>'; }
  function num(v){ var n=Number(v||0); return Number.isFinite(n)?n:0; }
  function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function flashReject(type, msg){ if(!rejectAlertBox) return; rejectAlertBox.innerHTML='<div class="alert alert-'+type+' py-2 mb-2">'+esc(msg)+'</div>'; }
  function fmtMoney(v){ return 'Rp ' + num(v).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  function fmtDate(v){ return v ? esc(v) : '-'; }
  function normalizeUsagePurpose(value){ return String(value || '').toUpperCase().trim() === 'OPERASIONAL' ? 'OPERASIONAL' : 'BAHAN_BAKU'; }
  function defaultUsagePurposeForRow(row){
    var lineKind = String(row && row.line_kind || '').toUpperCase().trim();
    return lineKind === 'MATERIAL' || num(row && row.material_id) > 0 ? 'BAHAN_BAKU' : 'OPERASIONAL';
  }
  function resolvedUsagePurposeForRow(row){
    if (row && String(row.usage_purpose || '').trim() !== '') return normalizeUsagePurpose(row.usage_purpose);
    return defaultUsagePurposeForRow(row);
  }
  function renderUsagePurposeOptions(selected){
    var normalized = normalizeUsagePurpose(selected);
    var html = '';
    usagePurposeOptions.forEach(function(option){
      var value = normalizeUsagePurpose(option.value);
      html += '<option value="' + esc(value) + '"' + (value === normalized ? ' selected' : '') + '>' + esc(option.label) + '</option>';
    });
    return html;
  }
  function serverMessage(raw, fallback){
    var text=String(raw||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
    var match=text.match(/Message:\s*([^\r\n]+)/i);
    if(match && match[1]) return match[1].trim();
    return text && text.length<240 ? text : fallback;
  }
  function fetchJson(url, opts){
    opts=opts||{};
    if(String(opts.method||'GET').toUpperCase()==='POST'){
      opts.headers=Object.assign({'X-Procurement-Mutation-CSRF':procurementMutationCsrfToken},opts.headers||{});
    }
    opts.headers=Object.assign({'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},opts.headers||{});
    return fetch(url, opts).then(function(res){ return res.text().then(function(t){ var d={}, invalidJson=false; try{d=t?JSON.parse(t):{};}catch(e){d={};invalidJson=true;} if(!res.ok && !d.ok){ d.ok=false; d.message=d.message||(invalidJson ? serverMessage(t,'Server gagal memproses permintaan. Muat ulang halaman lalu coba kembali.') : ('Request gagal ('+res.status+')')); } return d;}); });
  }
  function uiConfirm(message, options){
    if(window.FinanceUI && typeof window.FinanceUI.confirm === 'function'){
      return window.FinanceUI.confirm(message, options || {});
    }
    if(window.FinanceUI && typeof window.FinanceUI.alert === 'function'){
      return window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', { title: 'UI Belum Siap' })
        .then(function(){ return false; });
    }
    return Promise.resolve(false);
  }
  function localDateInputValue(){ var now=new Date(); var y=now.getFullYear(); var m=String(now.getMonth()+1).padStart(2,'0'); var d=String(now.getDate()).padStart(2,'0'); return y+'-'+m+'-'+d; }
  function setBusy(btn, busy){ if(!btn) return ''; if(busy){ var old=btn.innerHTML; btn.disabled=true; btn.setAttribute('data-old-html', old); btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>'; return old; } btn.disabled=false; btn.innerHTML=btn.getAttribute('data-old-html') || btn.innerHTML; return btn.innerHTML; }

  function runFulfill(requestId, btn){
    if(requestId<=0) return Promise.resolve(false);
    if(btn){ setBusy(btn, true); }
    return fetchJson(fulfillUrlBase+requestId, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({fulfillment_date:localDateInputValue(), notes:''}) })
      .then(function(res){
        if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal fulfill SR.'); if(btn){ setBusy(btn, false); } return false; }
        window.location.href=reloadUrl;
        return true;
      })
      .catch(function(){ flash('danger','Gagal fulfill SR.'); if(btn){ setBusy(btn, false); } return false; });
  }

  function runGeneratePo(requestId, btn){
    if(requestId<=0) return Promise.resolve(false);
    if(btn){ setBusy(btn, true); }
    return fetchJson(generatePoUrlBase+requestId, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({}) })
      .then(function(res){
        if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal generate draft PO.'); if(btn){ setBusy(btn, false); } return false; }
        if(res.data && res.data.redirect_url){ window.location.href=String(res.data.redirect_url); return true; }
        window.location.href=reloadUrl;
        return true;
      })
      .catch(function(){ flash('danger','Gagal generate draft PO.'); if(btn){ setBusy(btn, false); } return false; });
  }

  function updateSplitActions(requestId, totals){
    currentSplitRequestId = requestId > 0 ? requestId : 0;
    var fulfillable = num((totals||{}).fulfillable_content);
    var shortage = num((totals||{}).shortage_content);
    if(splitFulfillBtn){ splitFulfillBtn.hidden = !(currentSplitRequestId > 0 && fulfillable > 0); }
    if(splitGenPoBtn){ splitGenPoBtn.hidden = !(currentSplitRequestId > 0 && shortage > 0); }
  }

  function parseDestinationOptionMeta(){
    var destEl = document.getElementById('sr_destination_type');
    var meta = [];
    if (!destEl) return meta;
    for (var i = 0; i < destEl.options.length; i++) {
      var op = destEl.options[i];
      meta.push({ value: String(op.value || ''), label: String(op.text || op.value || '') });
    }
    return meta;
  }

  var destinationOptionMeta = parseDestinationOptionMeta();

  function applyDivisionDestinationGuard(){
    var divisionEl = document.getElementById('sr_division_id');
    var destinationEl = document.getElementById('sr_destination_type');
    if (!divisionEl || !destinationEl) return;

    var divisionId = String(divisionEl.value || '');
    var allowed = destinationGuardMap[divisionId] || [];
    var allowedSet = {};
    for (var i = 0; i < allowed.length; i++) {
      allowedSet[String(allowed[i] || '')] = true;
    }

    var current = String(destinationEl.value || '');
    var html = [];
    destinationOptionMeta.forEach(function(op){
      if (allowedSet[op.value]) {
        html.push('<option value="' + esc(op.value) + '">' + esc(op.label) + '</option>');
      }
    });
    destinationEl.innerHTML = html.join('');

    if (current && allowedSet[current]) {
      destinationEl.value = current;
    }
    if (!destinationEl.value && destinationEl.options.length > 0) {
      destinationEl.selectedIndex = 0;
    }
  }

  function renderCreateSearch(rows){
    var tb = document.getElementById('srProfileResults'); if(!tb) return;
    if(!rows || !rows.length){ tb.innerHTML='<tr><td colspan="8" class="text-muted text-center py-2">Tidak ada data.</td></tr>'; return; }
    var html='';
    rows.forEach(function(row){
      var buyCode = esc(row.profile_buy_uom_code || '-');
      var contentCode = esc(row.profile_content_uom_code || '-');
      var stockBuy = num(row.qty_buy_balance).toFixed(2);
      var stockContent = num(row.qty_content_balance).toFixed(2);
      var brandText = row.profile_brand ? '<div class="small text-muted">Brand: '+esc(row.profile_brand)+'</div>' : '';
      var descriptionText = row.profile_description ? esc(row.profile_description) : '<span class="text-muted">-</span>';
      var expDateText = fmtDate(row.profile_expired_date || '');
      var lastPurchaseDate = row.last_purchase_date ? esc(row.last_purchase_date) : '-';
      html += '<tr>'
        + '<td><strong>'+esc(row.profile_name||'-')+'</strong>'+brandText+'</td>'
        + '<td class="small">'+descriptionText+'</td>'
        + '<td>'+buyCode+' -> '+contentCode+'</td>'
        + '<td class="text-end"><div class="fw-semibold">'+stockBuy+' '+buyCode+'</div><div class="small text-muted">'+stockContent+' '+contentCode+'</div></td>'
        + '<td class="text-end"><div class="fw-semibold">'+fmtMoney(row.last_unit_price)+'</div><div class="small text-muted">/ '+buyCode+'</div></td>'
        + '<td>'+expDateText+'</td>'
        + '<td>'+lastPurchaseDate+'</td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-primary sr-pick-profile" data-row="'+esc(JSON.stringify(row))+'">Pilih</button></td>'
        + '</tr>';
    });
    tb.innerHTML = html;
  }

  function renderCreateLines(){
    var tb=document.getElementById('srLineTableBody'); if(!tb) return;
    if(!createLines.length){ tb.innerHTML='<tr><td colspan="11" class="text-muted text-center py-2">Belum ada line.</td></tr>'; return; }
    var html='';
    createLines.forEach(function(line, idx){
      var lineBrand = line.profile_brand ? '<div class="small text-muted">Brand: '+esc(line.profile_brand)+'</div>' : '';
      var lineDescription = line.profile_description ? esc(line.profile_description) : '<span class="text-muted">-</span>';
      html += '<tr>'
        + '<td><strong>'+esc(line.profile_name||'-')+'</strong>'+lineBrand+'</td>'
        + '<td class="small">'+lineDescription+'</td>'
        + '<td>'+esc(line.line_kind||'-')+'</td>'
        + '<td><select class="form-select form-select-sm sr-usage-purpose" data-idx="'+idx+'">'+renderUsagePurposeOptions(line.usage_purpose || line.default_usage_purpose)+'</select></td>'
        + '<td>'+esc(line.profile_buy_uom_code||'-')+' -> '+esc(line.profile_content_uom_code||'-')+'</td>'
        + '<td class="text-end"><div class="fw-semibold">'+num(line.qty_buy_balance).toFixed(2)+' '+esc(line.profile_buy_uom_code||'-')+'</div><div class="small text-muted">'+num(line.qty_content_balance).toFixed(2)+' '+esc(line.profile_content_uom_code||'-')+'</div></td>'
        + '<td class="text-end"><div class="fw-semibold">'+fmtMoney(line.last_unit_price)+'</div><div class="small text-muted">/ '+esc(line.profile_buy_uom_code||'-')+'</div></td>'
        + '<td>'+fmtDate(line.profile_expired_date || '')+'</td>'
        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm sr-qty-buy" data-idx="'+idx+'" value="'+num(line.qty_buy_requested).toFixed(2)+'"></td>'
        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm sr-qty-content" data-idx="'+idx+'" value="'+num(line.qty_content_requested).toFixed(2)+'"></td>'
        + '<td class="action-cell"><div class="d-flex gap-1 flex-nowrap justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn sr-remove-line" data-idx="'+idx+'" title="Hapus" aria-label="Hapus"><i class="ri ri-delete-bin-line"></i></button></div></td>'
        + '</tr>';
    });
    tb.innerHTML = html;
  }

  function addCreateLine(row){
    var key=[row.profile_key||'', row.item_id||0, row.material_id||0, row.buy_uom_id||0, row.content_uom_id||0].join('|');
    for(var i=0;i<createLines.length;i++){
      var x=createLines[i];
      var xk=[x.profile_key||'', x.item_id||0, x.material_id||0, x.buy_uom_id||0, x.content_uom_id||0].join('|');
      if(xk===key){ flashCreate('warning','Line dengan profile dan UOM yang sama sudah ada.'); return; }
    }
    var cpb=num(row.profile_content_per_buy); if(cpb<=0){ cpb=1; }
    var canonicalKind = num(row.item_id) > 0 ? 'ITEM' : ((num(row.material_id)>0)?'MATERIAL':String(row.line_kind || 'ITEM').toUpperCase());
    createLines.push({
      line_kind: canonicalKind,
      item_id: num(row.item_id)||null,
      material_id: num(row.material_id)||null,
      profile_key: row.profile_key || '',
      profile_name: row.profile_name || '',
      profile_brand: row.profile_brand || '',
      profile_description: row.profile_description || '',
      profile_expired_date: row.profile_expired_date || '',
      expiry_policy: row.expiry_policy || ((row.required_expiry_date || row.profile_expired_date) ? 'EXACT_DATE' : 'NONE'),
      required_expiry_date: row.required_expiry_date || row.profile_expired_date || '',
      min_remaining_days: num(row.min_remaining_days || 0) || null,
      buy_uom_id: num(row.buy_uom_id),
      content_uom_id: num(row.content_uom_id),
      profile_content_per_buy: cpb,
      profile_buy_uom_code: row.profile_buy_uom_code || '',
      profile_content_uom_code: row.profile_content_uom_code || '',
      last_unit_price: num(row.last_unit_price || row.standard_price),
      qty_buy_balance: num(row.qty_buy_balance),
      qty_content_balance: num(row.qty_content_balance),
      qty_buy_requested: 1,
      qty_content_requested: Number(cpb.toFixed(2)),
      default_usage_purpose: defaultUsagePurposeForRow(row),
      usage_purpose: resolvedUsagePurposeForRow(row)
    });
    renderCreateLines();
  }

  document.addEventListener('click', function(e){
    var pick = e.target.closest('.sr-pick-profile');
    if (pick && canCreate){
      e.preventDefault();
      try { addCreateLine(JSON.parse(pick.getAttribute('data-row') || '{}')); }
      catch (err) { flashCreate('danger', 'Data profile tidak valid.'); }
      return;
    }
    var del = e.target.closest('.sr-remove-line');
    if (del && canCreate){
      e.preventDefault();
      var idxDel = parseInt(del.getAttribute('data-idx') || '-1', 10);
      if (idxDel >= 0){ createLines.splice(idxDel, 1); renderCreateLines(); }
      return;
    }

    var repairBtn=e.target.closest('.sr-repair-history');
    if(repairBtn && canRepairHistory){
      e.preventDefault();
      var repairId=parseInt(repairBtn.getAttribute('data-id')||'0',10); if(repairId<=0) return;
      uiConfirm('Repair histori akan menghapus ulang jejak fulfillment SR VOID dari movement log lalu rebuild stok gudang dan divisi yang terdampak.', {
        title: 'Konfirmasi Repair Histori SR VOID',
        okText: 'Repair Histori',
        cancelText: 'Batal'
      }).then(function(ok){
        if(!ok) return;
        setBusy(repairBtn, true);
        fetchJson(repairHistoryUrlBase+repairId, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({}) })
          .then(function(res){
            if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal repair histori SR VOID.'); setBusy(repairBtn, false); return; }
            window.location.href=reloadUrl;
          })
          .catch(function(){ flash('danger','Gagal repair histori SR VOID.'); setBusy(repairBtn, false); });
      });
      return;
    }

    if(!canEdit) return;

    var act=e.target.closest('.sr-action');
    if(act){
      e.preventDefault();
      var id=parseInt(act.getAttribute('data-id')||'0',10); var action=String(act.getAttribute('data-action')||'').toUpperCase();
      if(id<=0 || !action) return;
      if(action === 'REJECT'){
        if(!rejectModalEl || !window.bootstrap){ flash('danger','Modal alasan penolakan tidak tersedia. Muat ulang halaman lalu coba lagi.'); return; }
        rejectRequestId=id;
        if(rejectReasonEl) rejectReasonEl.value='';
        if(rejectAlertBox) rejectAlertBox.innerHTML='';
        var row=act.closest('tr');
        var docEl=row ? row.querySelector('.sr-request-meta strong') : null;
        if(rejectDocumentNoEl) rejectDocumentNoEl.textContent=docEl ? String(docEl.textContent || '').trim() : ('SR #' + id);
        window.bootstrap.Modal.getOrCreateInstance(rejectModalEl).show();
        window.setTimeout(function(){ if(rejectReasonEl) rejectReasonEl.focus(); }, 180);
        return;
      }
      var old=act.innerHTML; act.disabled=true; act.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
      fetchJson(actionUrlBase+id, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:action, notes:''}) })
        .then(function(res){ if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal aksi SR.'); act.disabled=false; act.innerHTML=old; return; } window.location.href=reloadUrl; })
        .catch(function(){ flash('danger','Gagal aksi SR.'); act.disabled=false; act.innerHTML=old; });
      return;
    }

    var splitBtn=e.target.closest('.sr-split');
    if(splitBtn){
      e.preventDefault();
      var sid=parseInt(splitBtn.getAttribute('data-id')||'0',10); if(sid<=0) return;
      var olds=splitBtn.innerHTML; splitBtn.disabled=true; splitBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
      fetchJson(splitPreviewUrlBase+sid, {credentials:'same-origin'})
        .then(function(res){
          splitBtn.disabled=false; splitBtn.innerHTML=olds;
          if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal preview split.'); return; }
          var rows=(res.rows||[]); var html=[];
          html.push('<div class="sr-split-summary">'
            + '<div class="card"><div class="card-body py-2"><small class="text-muted d-block">Request</small><strong>'+num((res.totals||{}).request_content).toFixed(2)+'</strong></div></div>'
            + '<div class="card"><div class="card-body py-2"><small class="text-muted d-block">Bisa Dipenuhi</small><strong>'+num((res.totals||{}).fulfillable_content).toFixed(2)+'</strong></div></div>'
            + '<div class="card"><div class="card-body py-2"><small class="text-muted d-block">Shortage</small><strong>'+num((res.totals||{}).shortage_content).toFixed(2)+'</strong></div></div>'
            + '</div>');
          html.push('<div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><th>Line</th><th>Profile</th><th class="text-end">Req</th><th class="text-end">Avail</th><th class="text-end">Fulfill</th><th class="text-end">Shortage</th></tr></thead><tbody>');
          if(!rows.length){ html.push('<tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>'); }
          else { rows.forEach(function(r){ html.push('<tr><td>'+esc(r.line_no)+'</td><td>'+esc(r.profile_name||'-')+'</td><td class="text-end">'+num(r.request_remain_content).toFixed(2)+'</td><td class="text-end">'+num(r.available_content).toFixed(2)+'</td><td class="text-end">'+num(r.fulfillable_content).toFixed(2)+'</td><td class="text-end">'+num(r.shortage_content).toFixed(2)+'</td></tr>'); }); }
          html.push('</tbody></table></div>');
          if(splitModalBody){ splitModalBody.innerHTML=html.join(''); }
          updateSplitActions(sid, res.totals || {});
          if(window.bootstrap && splitModalEl){ window.bootstrap.Modal.getOrCreateInstance(splitModalEl).show(); }
        }).catch(function(){ splitBtn.disabled=false; splitBtn.innerHTML=olds; flash('danger','Gagal preview split.'); });
      return;
    }

    var fulfillBtn=e.target.closest('.sr-fulfill');
    if(fulfillBtn){
      e.preventDefault();
      var fid=parseInt(fulfillBtn.getAttribute('data-id')||'0',10); if(fid<=0) return;
      uiConfirm('Fulfillment akan memindahkan stok gudang ke divisi tujuan untuk SR ini.', {
        title: 'Konfirmasi Fulfillment SR',
        okText: 'Post Fulfillment',
        cancelText: 'Batal'
      }).then(function(ok){
        if(!ok) return;
        runFulfill(fid, fulfillBtn);
      });
      return;
    }

    var poBtn=e.target.closest('.sr-gpo');
    if(poBtn){
      e.preventDefault();
      var pid=parseInt(poBtn.getAttribute('data-id')||'0',10); if(pid<=0) return;
      uiConfirm('Draft PO akan dibuat hanya untuk kebutuhan shortage dari SR ini.', {
        title: 'Konfirmasi Generate Draft PO',
        okText: 'Generate PO',
        cancelText: 'Batal'
      }).then(function(ok){
        if(!ok) return;
        runGeneratePo(pid, poBtn);
      });
      return;
    }
  });

  if(rejectConfirmBtn){
    rejectConfirmBtn.addEventListener('click', function(){
      var reason=String((rejectReasonEl||{}).value||'').trim();
      if(rejectRequestId<=0){ flashReject('danger','Dokumen Store Request tidak valid.'); return; }
      if(reason.length<3){ flashReject('warning','Alasan penolakan wajib diisi minimal 3 karakter.'); if(rejectReasonEl) rejectReasonEl.focus(); return; }
      setBusy(rejectConfirmBtn, true);
      fetchJson(actionUrlBase+rejectRequestId, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'REJECT', notes:reason}) })
        .then(function(res){
          if(!res || !res.ok){
            flashReject('danger',(res&&res.message)?res.message:'Gagal menolak Store Request.');
            setBusy(rejectConfirmBtn, false);
            return;
          }
          window.location.href=reloadUrl;
        })
        .catch(function(){ flashReject('danger','Gagal menolak Store Request.'); setBusy(rejectConfirmBtn, false); });
    });
  }
  if(rejectModalEl){
    rejectModalEl.addEventListener('hidden.bs.modal', function(){
      rejectRequestId=0;
      if(rejectReasonEl) rejectReasonEl.value='';
      if(rejectAlertBox) rejectAlertBox.innerHTML='';
    });
  }

  document.addEventListener('change', function(e){
    var usage = e.target.closest('.sr-usage-purpose');
    if (usage && canCreate){
      var idxU = parseInt(usage.getAttribute('data-idx') || '-1', 10);
      if (idxU >= 0 && createLines[idxU]){
        createLines[idxU].usage_purpose = normalizeUsagePurpose(usage.value);
      }
    }
  });

  if(splitFulfillBtn){
    splitFulfillBtn.addEventListener('click', function(){
      if(currentSplitRequestId<=0) return;
      uiConfirm('Qty yang tersedia dari hasil split akan langsung diposting sebagai fulfillment.', {
        title: 'Konfirmasi Fulfillment Split',
        okText: 'Post Fulfillment',
        cancelText: 'Batal'
      }).then(function(ok){
        if(!ok) return;
        runFulfill(currentSplitRequestId, splitFulfillBtn);
      });
    });
  }

  if(splitGenPoBtn){
    splitGenPoBtn.addEventListener('click', function(){
      if(currentSplitRequestId<=0) return;
      uiConfirm('Draft PO akan dibuat hanya untuk kekurangan stok dari hasil split ini.', {
        title: 'Konfirmasi Generate PO Shortage',
        okText: 'Generate PO',
        cancelText: 'Batal'
      }).then(function(ok){
        if(!ok) return;
        runGeneratePo(currentSplitRequestId, splitGenPoBtn);
      });
    });
  }

  document.addEventListener('input', function(e){
    if (!canCreate) return;
    var buy = e.target.closest('.sr-qty-buy');
    if (buy){
      var idxB = parseInt(buy.getAttribute('data-idx') || '-1', 10);
      if (idxB >= 0 && createLines[idxB]){
        var cpbB = num(createLines[idxB].profile_content_per_buy); if(cpbB<=0) cpbB=1;
        createLines[idxB].qty_buy_requested = num(buy.value);
        createLines[idxB].qty_content_requested = createLines[idxB].qty_buy_requested * cpbB;
        renderCreateLines();
      }
      return;
    }
    var content = e.target.closest('.sr-qty-content');
    if (content){
      var idxC = parseInt(content.getAttribute('data-idx') || '-1', 10);
      if (idxC >= 0 && createLines[idxC]){
        var cpbC = num(createLines[idxC].profile_content_per_buy); if(cpbC<=0) cpbC=1;
        createLines[idxC].qty_content_requested = num(content.value);
        createLines[idxC].qty_buy_requested = createLines[idxC].qty_content_requested / cpbC;
        renderCreateLines();
      }
    }
  });

  if (canCreate){
    var divisionEl = document.getElementById('sr_division_id');
    if (divisionEl) {
      divisionEl.addEventListener('change', applyDivisionDestinationGuard);
    }
    applyDivisionDestinationGuard();

    function runProfileSearch() {
      var q = (document.getElementById('sr_profile_q') || {}).value || '';
      q = String(q).trim();
      if (q.length < 2) {
        renderCreateSearch([]);
        return;
      }

      if (profileSearchAbort && typeof profileSearchAbort.abort === 'function') {
        profileSearchAbort.abort();
      }
      profileSearchAbort = new AbortController();

      fetchJson(searchUrl + '?q=' + encodeURIComponent(q) + '&limit=20', {
        credentials: 'same-origin',
        signal: profileSearchAbort.signal
      })
        .then(function(res){
          if (!res || res.ok === false){
            flashCreate('danger', (res && res.message) ? res.message : 'Gagal memuat profile gudang.');
            return;
          }
          renderCreateSearch(res.rows || []);
        })
        .catch(function(err){
          if (err && err.name === 'AbortError') return;
          flashCreate('danger', 'Gagal memuat profile gudang.');
        });
    }

    var btnSearch = document.getElementById('btnSearchProfile');
    if (btnSearch){
      btnSearch.addEventListener('click', function(){
        runProfileSearch();
      });
    }

    var profileQEl = document.getElementById('sr_profile_q');
    if (profileQEl) {
      profileQEl.addEventListener('input', function(){
        if (profileSearchTimer) {
          window.clearTimeout(profileSearchTimer);
        }
        profileSearchTimer = window.setTimeout(runProfileSearch, 280);
      });
      profileQEl.addEventListener('keydown', function(ev){
        if (ev.key === 'Enter') {
          ev.preventDefault();
          runProfileSearch();
        }
      });
    }

    var btnCreate = document.getElementById('btnCreateSr');
    if (btnCreate){
      btnCreate.addEventListener('click', function(){
        if (!createLines.length){ flashCreate('warning', 'Line Store Request belum ada.'); return; }
        var old = btnCreate.innerHTML;
        btnCreate.disabled = true;
        btnCreate.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
        var desiredStatus = (btnCreate.getAttribute('data-status') || 'DRAFT').toUpperCase();
        var payload = {
          header: {
            request_date: (document.getElementById('sr_request_date') || {}).value || '',
            needed_date: (document.getElementById('sr_needed_date') || {}).value || '',
            request_division_id: Number((document.getElementById('sr_division_id') || {}).value || 0),
            destination_type: (document.getElementById('sr_destination_type') || {}).value || '',
            notes: (document.getElementById('sr_notes') || {}).value || '',
            status: desiredStatus
          },
          lines: createLines
        };

        var divisionId = String(payload.header.request_division_id || '');
        var allowed = destinationGuardMap[divisionId] || [];
        if (allowed.indexOf(payload.header.destination_type) === -1) {
          flashCreate('warning', 'Tujuan tidak sesuai dengan divisi yang dipilih.');
          btnCreate.disabled = false;
          btnCreate.innerHTML = old;
          return;
        }

        fetchJson(storeUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        }).then(function(res){
          if (!res || !res.ok){
            flashCreate('danger', (res && res.message) ? res.message : 'Gagal menyimpan Store Request.');
            btnCreate.disabled = false;
            btnCreate.innerHTML = old;
            return;
          }
          window.location.href = reloadUrl;
        }).catch(function(){
          flashCreate('danger', 'Gagal menyimpan Store Request.');
          btnCreate.disabled = false;
          btnCreate.innerHTML = old;
        });
      });
    }

    var btnCreateSubmit = document.getElementById('btnCreateSrSubmit');
    if (btnCreateSubmit && btnCreate){
      btnCreateSubmit.addEventListener('click', function(){
        btnCreate.setAttribute('data-status', 'SUBMITTED');
        btnCreate.click();
        btnCreate.setAttribute('data-status', 'DRAFT');
      });
    }
  }
})();
</script>
