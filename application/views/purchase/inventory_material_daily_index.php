<?php
$initialMonth = (string)($month ?? date('Y-m'));
$initialMonth = preg_match('/^\d{4}-\d{2}$/', substr($initialMonth, 0, 7)) ? substr($initialMonth, 0, 7) : date('Y-m');
$initialWindowStart = $initialMonth . '-01';
$initialWindowEnd = date('Y-m-t', strtotime($initialWindowStart));
$generateUrl = site_url('inventory/stock/opname/generate');
$lotAuditBaseUrl = site_url('inventory/stock/division/lot');
$adjustmentStoreUrl = site_url('inventory/stock/adjustment/store');
$adjustmentIndexUrl = site_url('inventory/stock/adjustment/division');
$initialQ = (string)($q ?? '');
$initialDateFrom = (string)($date_from ?? '');
$initialDateTo = (string)($date_to ?? '');
$initialDivisionId = (int)($division_id ?? 0);
$initialMaterialId = (int)($material_id ?? 0);
$initialDestination = strtoupper(trim((string)($destination ?? 'ALL')));
if ($initialDestination === '') {
  $initialDestination = 'ALL';
}
$initialLimit = (int)($limit ?? 120);
if ($initialLimit <= 0 || $initialLimit > 1000) {
  $initialLimit = 120;
}
$divisionOptions = is_array($divisions ?? null) ? $divisions : [];
$divisionOptions = array_values(array_filter($divisionOptions, static function ($row) {
  $code = strtoupper(trim((string)($row['code'] ?? '')));
  $name = strtoupper(trim((string)($row['name'] ?? '')));
  return strpos($code, 'MANAJEMEN') === false
    && strpos($name, 'MANAJEMEN') === false
    && strpos($code, 'MANAGEMENT') === false
    && strpos($name, 'MANAGEMENT') === false;
}));
$divisionIds = array_map(static function ($row) {
  return (int)($row['id'] ?? 0);
}, $divisionOptions);
if ($initialDivisionId > 0 && !in_array($initialDivisionId, $divisionIds, true)) {
  $initialDivisionId = 0;
}
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$matrixEmbedded = !empty($matrix_embedded);
?>

<?php if (!$matrixEmbedded): ?>
<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div><h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title ?? 'Daily Material Matrix'); ?></h4>
      <small class="text-muted">Matrix stok bahan baku per divisi/tujuan, bisa digabung item lalu expand ke profil.</small></div>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('inventory/daily-matrix') . '?' . http_build_query(array_filter(['division_id' => $initialDivisionId ?: null, 'source' => 'material', 'month' => $initialMonth])); ?>"><i class="ri ri-fullscreen-line me-1"></i>Workspace Matrix</a>
  </div>
</div>
<?php endif; ?>
<?php if (!$matrixEmbedded): ?>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'daily_matrix']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => substr($initialMonth, 0, 7), 'division_id' => (string)(int)$initialDivisionId, 'destination_type' => $initialDestination],
]); ?>
<div class="mb-2"><a href="<?php echo site_url('inventory/fifo-audit'); ?>" class="btn btn-sm btn-outline-secondary"><i class="ri ri-bar-chart-line me-1"></i>Audit FIFO</a></div>
<?php endif; ?>

<style>
  :root {
    --pmd-sticky-top: 0px;
    --pmd-col-division: 148px;
    --pmd-col-material: 278px;
    --pmd-col-detail: 298px;
    --pmd-left-1: 0px;
    --pmd-left-2: var(--pmd-col-division);
    --pmd-left-3: calc(var(--pmd-col-division) + var(--pmd-col-material));
    --pmd-date-col: 172px;
    --pmd-header-row-1: 44px;
  }
  .pmd-filter-card,
  .pmd-board-card,
  .pmd-scroll-table,
  .pmd-modal-card {
    font-family: "Trebuchet MS", Verdana, sans-serif;
  }
  .pmd-toast-stack {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 1095;
    display: grid;
    gap: 0.7rem;
    pointer-events: none;
  }
  .pmd-toast {
    min-width: 280px;
    max-width: 360px;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    color: #fff;
    box-shadow: 0 18px 36px rgba(32, 23, 28, 0.24);
    transform: translateY(10px);
    opacity: 0;
    transition: transform .2s ease, opacity .2s ease;
  }
  .pmd-toast.is-visible {
    transform: translateY(0);
    opacity: 1;
  }
  .pmd-toast.success {
    background: linear-gradient(135deg, #176b3a 0%, #289a58 100%);
  }
  .pmd-toast.error {
    background: linear-gradient(135deg, #9f1239 0%, #dc2626 100%);
  }
  .pmd-toast-title {
    font-size: .82rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: .15rem;
  }
  .pmd-toast-body {
    font-size: .86rem;
    line-height: 1.45;
  }
  .pmd-filter-card {
    border: 1px solid #ead9cf;
    border-radius: 16px;
    box-shadow: 0 12px 24px rgba(104, 43, 40, 0.06);
  }
  .pmd-filter-grid {
    display: grid;
    grid-template-columns:
      minmax(112px, 0.9fr)
      minmax(180px, 1.35fr)
      minmax(116px, 0.9fr)
      minmax(220px, 1.6fr)
      minmax(136px, 1fr)
      minmax(136px, 1fr)
      minmax(84px, 0.6fr)
      minmax(164px, 0.9fr);
    gap: 0.65rem;
    align-items: end;
  }
  .pmd-filter-field {
    min-width: 0;
  }
  .pmd-filter-field .form-control,
  .pmd-filter-field .form-select {
    min-width: 0;
  }
  .pmd-filter-field label {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #7a554a;
  }
  .pmd-filter-main,
  .pmd-filter-division,
  .pmd-filter-destination,
  .pmd-filter-search,
  .pmd-filter-date,
  .pmd-filter-date-to,
  .pmd-filter-limit {
    grid-column: span 1;
  }
  .pmd-filter-actions {
    grid-column: span 1;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
    align-self: stretch;
  }
  .pmd-filter-actions .btn {
    min-height: 38px;
    white-space: nowrap;
  }
  .pmd-pagination {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    padding: 0.5rem 0.65rem;
    background: #fff;
    border: 1px solid #ead9cf;
    border-radius: 12px;
  }
  .pmd-pagination-info {
    font-size: 0.78rem;
    color: #7a554a;
    margin-right: 0.35rem;
  }
  .pmd-page-btn {
    border: 1px solid #d7b6a8;
    background: #fff;
    color: #6a2d3c;
    border-radius: 8px;
    padding: 0.28rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.2;
    cursor: pointer;
    transition: background .15s;
  }
  .pmd-page-btn:hover:not(:disabled) { background: #ffece2; border-color: #c99f8f; }
  .pmd-page-btn:disabled { opacity: .38; cursor: default; }
  .pmd-page-btn.is-active { background: linear-gradient(135deg,#5f2432,#8c3f49); color:#fff; border-color:#5f2432; }
  .pmd-board-card {
    border: 1px solid #ead9cf;
    border-radius: 18px;
    overflow: visible;
    box-shadow: 0 14px 28px rgba(104, 43, 40, 0.08);
  }
  .pmd-board-head {
    background: linear-gradient(135deg, #5f2432, #8c3f49);
    color: #fff;
    padding: 0.8rem 0.95rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
  }
  .pmd-legend { display: flex; flex-wrap: wrap; gap: 0.4rem; }
  .pmd-legend-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: rgba(255, 255, 255, 0.14);
    font-size: 0.75rem;
    font-weight: 700;
  }
  .pmd-legend-dot { width: 8px; height: 8px; border-radius: 999px; }
  .pmd-dot-open { background: #2f5b95; }
  .pmd-dot-in { background: #2bb673; }
  .pmd-dot-out { background: #f17f43; }
  .pmd-dot-adj { background: #7f64d9; }
  .pmd-dot-close { background: #4e6b83; }
  .pmd-table-wrap {
    overflow: auto;
    position: relative;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
    isolation: isolate;
  }
  .pmd-matrix-shell {
    display: grid;
    grid-template-columns: calc(var(--pmd-col-division) + var(--pmd-col-material) + var(--pmd-col-detail)) minmax(0, 1fr);
    align-items: start;
  }
  .pmd-freeze-pane {
    position: relative;
    z-index: 2;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
    border-right: 4px solid #d2a08e;
    box-shadow: 12px 0 22px -18px rgba(95, 23, 39, 0.26);
  }
  .pmd-scroll-pane {
    min-width: 0;
    position: relative;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
  }
  .pmd-freeze-head-wrap,
  .pmd-scroll-head-wrap {
    position: sticky;
    top: var(--pmd-sticky-top);
    z-index: 52;
  }
  .pmd-freeze-head-wrap {
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
  }
  .pmd-scroll-head-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
  }
  .pmd-scroll-head-wrap::-webkit-scrollbar {
    display: none;
  }
  .pmd-freeze-table {
    width: calc(var(--pmd-col-division) + var(--pmd-col-material) + var(--pmd-col-detail));
    min-width: calc(var(--pmd-col-division) + var(--pmd-col-material) + var(--pmd-col-detail));
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pmd-scroll-table {
    min-width: 920px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    width: max-content;
  }
  .pmd-freeze-table thead th,
  .pmd-freeze-table tbody td,
  .pmd-scroll-table thead th,
  .pmd-scroll-table tbody td {
    padding: 0.42rem 0.45rem;
    vertical-align: middle;
  }
  .pmd-freeze-table thead th,
  .pmd-scroll-table thead th { text-align: center; }
  .pmd-freeze-table td {
    background: #fffaf7;
    color: #4a2430;
    box-shadow: inset -2px 0 0 #e6c9bd;
  }
  .pmd-freeze-col-1 { width: var(--pmd-col-division); min-width: var(--pmd-col-division); max-width: var(--pmd-col-division); }
  .pmd-freeze-col-2 { width: var(--pmd-col-material); min-width: var(--pmd-col-material); max-width: var(--pmd-col-material); }
  .pmd-freeze-col-3 { width: var(--pmd-col-detail); min-width: var(--pmd-col-detail); max-width: var(--pmd-col-detail); }
  .pmd-scroll-table {
    min-width: 920px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pmd-scroll-table thead th,
  .pmd-scroll-table tbody td {
    padding: 0.42rem 0.45rem;
    vertical-align: middle;
  }
  .pmd-scroll-table thead tr:first-child th {
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
    border-bottom: 1px solid #772938;
    color: #fff8f5;
    white-space: nowrap;
    width: var(--pmd-date-col);
    min-width: var(--pmd-date-col);
    max-width: var(--pmd-date-col);
    height: var(--pmd-header-row-1);
    min-height: var(--pmd-header-row-1);
  }
  .pmd-freeze-table thead tr:first-child th {
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
    border-bottom: 1px solid #772938;
    color: #fff8f5;
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    height: var(--pmd-header-row-1);
    min-height: var(--pmd-header-row-1);
    white-space: nowrap;
  }
  .pmd-day-head {
    width: var(--pmd-date-col);
    min-width: var(--pmd-date-col);
    max-width: var(--pmd-date-col);
    padding: 0.32rem 0.32rem 0.34rem !important;
  }
  .pmd-day-head.is-today {
    background: linear-gradient(180deg, #ffb79e, #ff9370) !important;
    box-shadow: inset 0 -4px 0 #cf5f3e;
    color: #56190e !important;
  }
  .pmd-freeze-body-table,
  .pmd-scroll-body-table {
    margin-top: 0;
  }
  .pmd-headcard {
    display: grid;
    gap: 0.12rem;
    justify-items: center;
    text-align: center;
    line-height: 1;
  }
  .pmd-headcard .day {
    font-size: 1.08rem;
    font-weight: 900;
    letter-spacing: 0.02em;
  }
  .pmd-headcard .meta {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.92;
    white-space: nowrap;
  }
  .pmd-headcard .today-tag {
    margin-top: 0.04rem;
    padding: 0.1rem 0.34rem;
    border-radius: 999px;
    font-size: 0.56rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    background: rgba(93, 22, 13, 0.12);
    color: inherit;
  }
  .pmd-division-pill {
    display: inline-flex;
    padding: 0.16rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #ebd4ca;
    background: #fff2ec;
    color: #8b3c47;
    font-size: 0.66rem;
    font-weight: 800;
    max-width: 100%;
  }
  .pmd-name { font-weight: 800; color: #4e1f2e; line-height: 1.24; }
  .pmd-code { color: #876a65; font-size: 0.79rem; margin-top: 0.14rem; }
  .pmd-division-stack {
    display: grid;
    gap: 0.18rem;
  }
  .pmd-division-cell {
    display: flex;
    align-items: flex-start;
    gap: 0.34rem;
  }
  .pmd-destination-chip {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    padding: 0.16rem 0.48rem;
    border-radius: 999px;
    background: #fff6ef;
    color: #8d5a4c;
    font-size: 0.64rem;
    font-weight: 800;
  }
  .pmd-material-stack {
    display: grid;
    gap: 0.22rem;
    justify-items: center;
    text-align: center;
    position: relative;
    width: 100%;
  }
  .pmd-material-stack.is-parent {
    padding-inline: 0.1rem;
  }
  .pmd-material-stack.is-child {
    width: calc(100% - 18px);
    margin-left: 18px;
    padding-left: 0.35rem;
  }
  .pmd-material-stack.is-child::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 0.22rem;
    bottom: 0.22rem;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(180deg, #efd8cc 0%, #e1bca7 100%);
  }
  .pmd-material-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.28rem;
    justify-content: center;
  }
  .pmd-material-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    border: 1px solid #eed6c9;
    background: #fff8f3;
    color: #8a5547;
    font-size: 0.66rem;
    font-weight: 800;
    max-width: 100%;
  }
  .pmd-material-chip.is-parent {
    border-radius: 999px;
    box-shadow: inset 0 -1px 0 rgba(138, 85, 71, 0.08);
  }
  .pmd-material-chip.is-child {
    border-radius: 10px;
    border-style: dashed;
    background: #fffaf7;
    color: #7c5348;
  }
  .pmd-material-link {
    display: inline-flex;
    align-items: center;
    padding: 0.18rem 0.56rem;
    border-radius: 999px;
    border: 1px solid #d08a2d;
    background: linear-gradient(180deg, #fff1c7 0%, #ffd982 100%);
    color: #7a3d00;
    font-size: 0.67rem;
    font-weight: 900;
    text-decoration: none;
    box-shadow: inset 0 -1px 0 rgba(122, 61, 0, 0.12);
  }
  .pmd-material-link:hover {
    color: #5f2d00;
    background: linear-gradient(180deg, #ffe9b0 0%, #ffcf64 100%);
    border-color: #bb7318;
    text-decoration: none;
  }
  .pmd-material-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
    justify-content: center;
  }
  .pmd-alert-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.16rem 0.5rem;
    border-radius: 999px;
    background: #c0392b;
    color: #fff;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.02em;
  }
  .pmd-material-expand {
    border: 1px solid #d7b6a8;
    background: #fff2ea;
    color: #6a2d3c;
    border-radius: 999px;
    padding: 0.18rem 0.58rem;
    font-size: 0.67rem;
    font-weight: 800;
    line-height: 1;
  }
  .pmd-material-expand:hover {
    background: #ffe8dd;
    border-color: #c99f8f;
  }
  .pmd-detail-card {
    display: grid;
    gap: 0.36rem;
    align-content: start;
  }
  .pmd-detail-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.55rem;
  }
  .pmd-summary-only {
    display: grid;
    gap: 0.3rem;
  }
  .pmd-detail-title {
    min-width: 0;
    display: grid;
    gap: 0.18rem;
  }
  .pmd-detail-metas {
    display: flex;
    flex-wrap: wrap;
    gap: 0.32rem;
  }
  .pmd-detail-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.16rem 0.48rem;
    border-radius: 999px;
    background: #fff3ea;
    border: 1px solid #ead6c9;
    color: #8b4f42;
    font-size: 0.65rem;
    font-weight: 800;
    max-width: 100%;
  }
  .pmd-profile-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.16rem 0.52rem;
    border-radius: 999px;
    background: #fff4eb;
    color: #8b4f42;
    font-size: 0.68rem;
    font-weight: 800;
    width: fit-content;
    max-width: 100%;
  }
  .pmd-profile-line {
    color: #6e5652;
    font-size: 0.78rem;
    line-height: 1.2;
    white-space: normal;
    word-break: break-word;
  }
  .pmd-profile-unit {
    margin-top: 0.18rem;
    color: #7d5648;
    font-size: 0.79rem;
    font-weight: 700;
  }
  .pmd-group-row {
    background: #fff9f6;
    border-top: 2px solid #f0d9cf;
  }
  .pmd-group-row.pmd-group-expandable {
    background: linear-gradient(90deg, #fff4eb, #fffaf6);
  }
  .pmd-group-row.pmd-group-single {
    background: linear-gradient(90deg, #f8fff5, #fafff8);
  }
  .pmd-child-row {
    background: #fffdfb;
  }
  .pmd-freeze-table tbody tr.pmd-group-row td {
    background: linear-gradient(180deg, #fff4eb 0%, #fffaf6 100%);
  }
  .pmd-freeze-table tbody tr.pmd-group-row.pmd-group-single td {
    background: linear-gradient(180deg, #f8fff5 0%, #fafff8 100%);
  }
  .pmd-freeze-table tbody tr.pmd-child-row td {
    background: linear-gradient(180deg, #fffdfb 0%, #fff8f4 100%);
    box-shadow: inset -2px 0 0 #edd6ca;
  }
  .pmd-freeze-table tbody tr.pmd-child-row td.pmd-freeze-col-2,
  .pmd-freeze-table tbody tr.pmd-child-row td.pmd-freeze-col-3 {
    border-left: 3px solid #efd8cc;
  }
  .pmd-scroll-table tbody tr.pmd-group-row td.pmd-metric-cell {
    background: #fff6f0;
  }
  .pmd-scroll-table tbody tr.pmd-group-row.pmd-group-single td.pmd-metric-cell {
    background: #fbfef8;
  }
  .pmd-scroll-table tbody tr.pmd-child-row td.pmd-metric-cell {
    background: #fffdfb;
  }
  .pmd-freeze-table tbody tr.pmd-stock-alert td,
  .pmd-scroll-table tbody tr.pmd-stock-alert td.pmd-metric-cell {
    background: linear-gradient(180deg, #fff1ef 0%, #fff8f7 100%) !important;
  }
  .pmd-stock-alert .pmd-name,
  .pmd-stock-alert .pmd-code,
  .pmd-stock-alert .pmd-summary-amount,
  .pmd-stock-alert .pmd-summary-metric strong {
    color: #8a2f2a !important;
  }
  .pmd-stock-alert .pmd-date-card {
    border-color: #efc0b9 !important;
    background: linear-gradient(180deg, rgba(255, 244, 242, 0.98) 0%, rgba(255, 249, 248, 0.98) 100%) !important;
  }
  .pmd-scroll-table tbody tr.pmd-group-row .pmd-date-card {
    border-color: #ebcdbd;
    background: linear-gradient(180deg, rgba(255, 247, 240, 0.98) 0%, rgba(255, 238, 228, 0.98) 100%);
    box-shadow: 0 14px 24px -24px rgba(122, 61, 0, 0.28);
  }
  .pmd-scroll-table tbody tr.pmd-group-row.pmd-group-single .pmd-date-card {
    border-color: #d9e6cf;
    background: linear-gradient(180deg, rgba(250, 255, 246, 0.98) 0%, rgba(242, 251, 237, 0.98) 100%);
  }
  .pmd-scroll-table tbody tr.pmd-child-row .pmd-date-card {
    border-color: #f0dfd5;
    background: linear-gradient(180deg, rgba(255, 253, 251, 0.98) 0%, rgba(255, 248, 243, 0.98) 100%);
    box-shadow: inset 0 0 0 1px rgba(239, 216, 204, 0.42);
  }
  .pmd-group-row td,
  .pmd-child-row td {
    vertical-align: top !important;
  }
  .pmd-toggle-arrow {
    border: 1px solid #d7b6a8;
    background: #fff;
    color: #6a2d3c;
    border-radius: 8px;
    width: 26px;
    height: 24px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.3rem;
    font-size: 0.85rem;
    font-weight: 800;
  }
  .pmd-toggle-arrow:hover {
    background: #ffece2;
    border-color: #c99f8f;
  }
  .pmd-toggle-static {
    display: inline-flex;
    width: 26px;
    height: 24px;
    align-items: center;
    justify-content: center;
    margin-right: 0.3rem;
    border-radius: 8px;
    background: #f8ece4;
    color: #9a6f60;
    font-size: 0.8rem;
    font-weight: 800;
  }
  .pmd-group-toggle {
    width: 100%;
    border: 1px solid #e8cfc4;
    background: #fff4ee;
    color: #6a2d3c;
    border-radius: 10px;
    padding: 0.22rem 0.4rem;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 700;
  }
  .pmd-group-toggle:hover {
    background: #ffe8dd;
    border-color: #d7b3a5;
  }
  .pmd-summary-card {
    display: grid;
    gap: 0.28rem;
    min-width: 0;
  }
  .pmd-summary-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
  }
  .pmd-summary-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #8a5b4d;
    font-weight: 800;
  }
  .pmd-summary-amount {
    font-size: 0.96rem;
    font-weight: 900;
    color: #523126;
    text-align: right;
  }
  .pmd-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.28rem;
  }
  .pmd-summary-metric {
    border: 1px solid #eadccf;
    border-radius: 12px;
    background: #fffaf6;
    padding: 0.32rem 0.4rem;
    min-width: 0;
  }
  .pmd-summary-metric .label {
    display: block;
    font-size: 0.67rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #8a5b4d;
  }
  .pmd-summary-metric strong {
    display: block;
    font-size: 0.82rem;
    color: #503125;
    line-height: 1.18;
    white-space: normal;
  }
  .pmd-detail-card .pmd-summary-card {
    gap: 0.35rem;
  }
  .pmd-metric-cell {
    width: var(--pmd-date-col);
    min-width: var(--pmd-date-col);
    max-width: var(--pmd-date-col);
    text-align: left;
    font-size: 0.79rem;
    font-variant-numeric: tabular-nums;
    white-space: normal;
    background: #fff;
    vertical-align: top !important;
    padding: 0.4rem !important;
  }
  .pmd-metric-cell.is-today {
    background: #fff4ed;
  }
  .pmd-date-card {
    display: grid;
    gap: 0.24rem;
    min-height: 126px;
    padding: 0.42rem 0.48rem;
    border-radius: 16px;
    border: 1px solid #efd9ca;
    background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(255,249,244,0.98) 100%);
    box-shadow: 0 14px 22px -24px rgba(95, 53, 39, 0.55);
  }
  .pmd-date-band-b .pmd-date-card {
    background: linear-gradient(180deg, rgba(255,251,248,0.98) 0%, rgba(255,244,237,0.98) 100%);
  }
  .pmd-metric-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.34rem;
    align-items: center;
    font-size: 0.68rem;
    line-height: 1.08;
  }
  .pmd-metric-row + .pmd-metric-row {
    padding-top: 0.22rem;
    border-top: 1px dashed #edd7c8;
  }
  .pmd-metric-main {
    min-width: 0;
  }
  .pmd-metric-label {
    display: block;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #8a5b4d;
  }
  .pmd-metric-value-wrap {
    min-width: 0;
    display: flex;
    justify-content: flex-end;
  }
  .pmd-cell-btn {
    border: 0;
    background: transparent;
    color: inherit;
    padding: 0;
    min-width: 0;
    text-align: right;
    display: inline-flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.04rem;
    line-height: 1.08;
  }
  button.pmd-cell-btn:hover {
    opacity: 0.82;
  }
  .pmd-cell-btn.is-static {
    opacity: 0.94;
  }
  .pmd-cell-action-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.28rem;
    min-width: 0;
  }
  .pmd-cell-adjust-trigger {
    border: 1px solid #dec5bb;
    background: #fff8f4;
    color: #7a4858;
    border-radius: 8px;
    width: 28px;
    min-width: 28px;
    min-height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
    font-size: 0.95rem;
    font-weight: 700;
    box-shadow: inset 0 -1px 0 rgba(122, 72, 88, 0.08);
  }
  .pmd-cell-adjust-trigger:hover {
    background: #fcefe8;
    border-color: #cfa692;
    color: #5f2432;
  }
  .pmd-cell-adjust-trigger.is-disabled {
    opacity: 0.34;
    cursor: not-allowed;
    pointer-events: none;
  }
  .pmd-cell-adjust-trigger .ri {
    font-size: 0.95rem;
    line-height: 1;
  }
  .pmd-cell-adjust-glyph {
    display: block;
    line-height: 1;
    transform: translateY(-1px);
  }
  .pmd-metric-open { color: #2f5b95; }
  .pmd-metric-in { color: #248c5d; }
  .pmd-metric-out { color: #cd6b35; }
  .pmd-metric-adj { color: #6a52cf; }
  .pmd-metric-close { color: #4f647d; font-weight: 800; }
  .pmd-cell-pack { display: block; font-size: 0.76rem; font-weight: 800; line-height: 1.05; }
  .pmd-cell-content { display: block; font-size: 0.61rem; opacity: 0.84; line-height: 1.02; margin-top: 1px; }
  .pmd-cell-pack,
  .pmd-cell-content {
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .pmd-day-start { border-left: 1px solid #d8b8a9 !important; }
  .pmd-day-end { border-right: 1px solid #d8b8a9 !important; }
  .pmd-date-band-a {
    background-color: #fffefc;
  }
  .pmd-date-band-b {
    background-color: #fff6f1;
  }
  .pmd-empty,
  .pmd-loading {
    text-align: center;
    color: #8e6f67;
    padding: 2rem 1rem;
  }
  /* ── KPI gradient cards ─────────────────────────────────── */
  .pmd-kpi-row {
    display: grid;
    grid-template-columns: repeat(6, minmax(0,1fr));
    gap: .6rem;
  }
  @media (max-width:991.98px) { .pmd-kpi-row { grid-template-columns: repeat(3,1fr); } }
  @media (max-width:575.98px) { .pmd-kpi-row { grid-template-columns: repeat(2,1fr); } }
  .pmd-kpi-card {
    border-radius: 18px;
    padding: .85rem 1rem;
    position: relative;
    overflow: hidden;
    color: #fff;
    min-height: 84px;
  }
  .pmd-kpi-card::before,.pmd-kpi-card::after {
    content: ''; position: absolute; border-radius: 50%; opacity: .12; background: #fff;
  }
  .pmd-kpi-card::before { width: 80px; height: 80px; right: -18px; top: -18px; }
  .pmd-kpi-card::after  { width: 50px; height: 50px; right: 16px;  bottom: -14px; }
  .pmd-kpi-card > * { position:relative; z-index:1; }
  .pmd-kpi-icon { display:block; min-height:1.25rem; font-size:1.25rem; line-height:1; opacity:.82; }
  .pmd-kpi-label { font-size: .67rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; opacity: .88; }
  .pmd-kpi-value { font-size: 1.5rem; font-weight: 900; line-height: 1.15; margin-top: .16rem; }
  .pmd-kpi-sub   { font-size: .7rem; opacity: .82; margin-top: .1rem; }
  .pmd-kpi-1 { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
  .pmd-kpi-2 { background:linear-gradient(135deg,#0c7cba 0%,#0fcdba 100%); }
  .pmd-kpi-3 { background:linear-gradient(135deg,#134e5e 0%,#38b2a3 100%); }
  .pmd-kpi-4 { background:linear-gradient(135deg,#e06c00 0%,#f7b733 100%); }
  .pmd-kpi-card.is-danger { background:linear-gradient(135deg,#b22222 0%,#e05252 100%); }
  .pmd-kpi-5 { background:linear-gradient(135deg,#1c7ed6 0%,#74c0fc 100%); }
  .pmd-kpi-6 { background:linear-gradient(135deg,#e06c00 0%,#f7b733 100%); }

  /* ── Sparkline (surprise) ────────────────────────────────── */
  .pmd-sparkline-wrap {
    display: flex; flex-direction: column; gap: .2rem; min-width: 90px;
  }
  .pmd-sparkline-label {
    font-size: .58rem; font-weight: 800; letter-spacing: .08em;
    text-transform: uppercase; opacity: .72;
  }
  .pmd-sparkline { display: flex; align-items: flex-end; gap: 2px; height: 26px; }
  .pmd-spark-bar {
    flex: 1; min-width: 2px; border-radius: 2px 2px 0 0;
    background: rgba(255,255,255,.7);
    transition: height .4s ease;
    cursor: default;
  }
  .pmd-spark-bar.is-today { background: #ffb79e; }
  .pmd-spark-bar:hover    { background: rgba(255,255,255,1); }
  .pmd-modal-card {
    border: 1px solid #e9d9d1;
    border-radius: 12px;
    background: #fff9f6;
    padding: 0.75rem 0.9rem;
  }
  .pmd-adjust-selected {
    border: 1px dashed #d8b8a9;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #fff7f2 100%);
    padding: 0.85rem 0.95rem;
  }
  .pmd-adjust-help {
    border: 1px solid #edd8cf;
    border-radius: 12px;
    background: #fffaf7;
    padding: 0.75rem 0.85rem;
    font-size: 0.83rem;
    color: #6f4d47;
  }
  @media (max-width: 1199.98px) {
    .pmd-filter-grid {
      grid-template-columns: repeat(8, minmax(0, 1fr));
      gap: 0.55rem;
    }
    .pmd-filter-main,
    .pmd-filter-destination,
    .pmd-filter-date,
    .pmd-filter-date-to,
    .pmd-filter-limit,
    .pmd-filter-actions {
      grid-column: span 2;
    }
    .pmd-filter-division,
    .pmd-filter-search {
      grid-column: span 4;
    }
  }
  @media (max-width: 991.98px) {
    :root {
      --pmd-col-division: 136px;
      --pmd-col-material: 248px;
      --pmd-col-detail: 276px;
      --pmd-date-col: 162px;
    }
    .pmd-filter-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .pmd-filter-main,
    .pmd-filter-division,
    .pmd-filter-destination,
    .pmd-filter-search,
    .pmd-filter-date,
    .pmd-filter-date-to,
    .pmd-filter-limit,
    .pmd-filter-actions {
      grid-column: span 2;
    }
    .pmd-matrix-shell { grid-template-columns: calc(var(--pmd-col-division) + var(--pmd-col-material) + var(--pmd-col-detail)) minmax(0, 1fr); }
    .pmd-date-card { min-height: 120px; }
  }
  @media (max-width: 767.98px) {
    .pmd-filter-grid {
      grid-template-columns: 1fr;
    }
    .pmd-filter-main,
    .pmd-filter-division,
    .pmd-filter-destination,
    .pmd-filter-search,
    .pmd-filter-date,
    .pmd-filter-date-to,
    .pmd-filter-limit,
    .pmd-filter-actions {
      grid-column: span 1;
    }
    .pmd-filter-actions {
      grid-template-columns: 1fr 1fr;
    }
  }
  <?php if ($matrixEmbedded): ?>
  :root { --pmd-date-col: 300px; }
  .pmd-filter-card { margin-bottom: .35rem !important; }
  .pmd-filter-card .card-body { padding: .25rem .5rem !important; }
  .pmd-filter-card .pmd-filter-grid { gap: .25rem .35rem; }
  .pmd-filter-card .pmd-filter-field label { margin-bottom: .1rem; font-size: .58rem; line-height: 1; }
  .pmd-filter-card .pmd-filter-field .form-control,
  .pmd-filter-card .pmd-filter-field .form-select { min-height: 30px; height: 30px; padding: .2rem .45rem; font-size: .78rem; }
  .pmd-filter-card .pmd-filter-actions { gap: .3rem; }
  .pmd-filter-card .pmd-filter-actions .btn { min-height: 30px; padding: .2rem .7rem; font-size: .78rem; }
  .pmd-board-head { display: none !important; }
  .pmd-kpi-card { min-height: 54px; padding: .45rem .7rem; border-radius: 10px; }
  .pmd-kpi-icon, .pmd-kpi-card::before, .pmd-kpi-card::after { display: none !important; }
  .pmd-kpi-label { font-size: .62rem; margin: 0; }
  .pmd-kpi-value { font-size: 1rem !important; margin: 0; }
  .pmd-kpi-sub { display: none; }
  .pmd-board-head { padding: .35rem .65rem; }
  .pmd-summary-card { display: block; }
  .pmd-summary-head { align-items: center; }
  .pmd-summary-title { font-size: .58rem; }
  .pmd-summary-amount { font-size: .76rem; }
  .pmd-summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .15rem; }
  .pmd-summary-metric { padding: .16rem .2rem; border-radius: 5px; }
  .pmd-summary-metric .label { font-size: .52rem; }
  .pmd-summary-metric strong { font-size: .65rem; white-space: nowrap; }
  .pmd-date-card { display: grid; grid-template-columns: repeat(5, minmax(55px, 1fr)); gap: 0; min-height: 0; padding: .24rem; border-radius: 5px; box-shadow: none; }
  .pmd-metric-row { display: flex; min-width: 0; flex-direction: column; align-items: flex-end; justify-content: center; gap: .12rem; padding: .1rem .18rem; font-size: .64rem; }
  .pmd-metric-row + .pmd-metric-row { border-top: 0; border-left: 1px solid #e7d8cf; padding-top: .1rem; }
  .pmd-metric-label { font-size: .55rem; }
  .pmd-metric-value-wrap { position: static; min-width: 0; max-width: none; width: 100%; justify-content: flex-end; box-shadow: none; }
  .pmd-cell-pack, .pmd-cell-content { font-size: .63rem; }
  .pmd-scroll-table tbody tr:nth-child(odd) td.pmd-metric-cell { background: #fff !important; }
  .pmd-scroll-table tbody tr:nth-child(even) td.pmd-metric-cell { background: #e9eef3 !important; }
  .pmd-freeze-table tbody tr:nth-child(odd) td { background: #fff !important; }
  .pmd-freeze-table tbody tr:nth-child(even) td { background: #e9eef3 !important; }
  .pmd-scroll-table tbody tr td.pmd-metric-cell.is-today { background: #ffe09a !important; box-shadow: inset 0 0 0 2px #d78300; }
  .pmd-day-head.is-today { background: #f6a900 !important; color: #321800 !important; box-shadow: inset 0 0 0 2px #a95b00; }
  .pmd-flat-metric { width: 92px !important; min-width: 92px !important; max-width: 92px !important; padding: .3rem .35rem !important; text-align: right; white-space: nowrap; vertical-align: middle !important; }
  .pmd-matrix-shell.is-flat .pmd-freeze-body-table .pmd-flat-meta-cell { font-size:.64rem; line-height:1.06; }
  .pmd-matrix-shell.is-flat .pmd-freeze-body-table .pmd-compact-main { font-size:.72rem; }
  .pmd-matrix-shell.is-flat .pmd-freeze-body-table .pmd-compact-sub { font-size:.54rem; }
  .pmd-matrix-shell.is-flat .pmd-freeze-body-table .pmd-compact-value { font-size:.72rem; }
  .pmd-matrix-shell.is-flat .pmd-freeze-body-table .pmd-compact-value strong { font-size:.74rem; }
  .pmd-matrix-shell.is-flat .pmd-scroll-body-table .pmd-flat-metric { font-size:.86rem; }
  .pmd-matrix-shell.is-flat .pmd-scroll-body-table .pmd-flat-metric .pmd-cell-pack { font-size:.86rem; }
  .pmd-matrix-shell.is-flat .pmd-scroll-body-table .pmd-flat-metric .pmd-cell-content { font-size:.7rem; }
  .pmd-day-group { min-width: 460px !important; width: 460px !important; text-align: center; }
  .pmd-submetric { min-width: 92px; width: 92px; max-width: 92px; padding: .38rem .25rem !important; text-align: center; font-size: .7rem; }
  .pmd-metric-subhead th { background: #8e1628; color: #fff; }
  .pmd-scroll-table tbody tr.pmd-stock-alert td.pmd-flat-metric { background: #b91c1c !important; color: #fff !important; }
  .pmd-stock-alert td, .pmd-stock-alert td * { color: #fff !important; }
  .pmd-matrix-shell.is-flat .pmd-freeze-table tbody tr.pmd-stock-alert td { background:#b91c1c !important; color:#fff !important; }
  .pmd-day-head.is-today, .pmd-submetric.is-today { background: #f6a900 !important; color: #321800 !important; box-shadow: inset 0 0 0 2px #a95b00; }
  .pmd-matrix-shell.is-flat { --pmd-freeze-width:calc(var(--pmd-flat-col-1,120px) + var(--pmd-flat-col-2,220px) + var(--pmd-flat-col-3,145px) + var(--pmd-flat-col-4,155px)); grid-template-columns:var(--pmd-freeze-width) minmax(0, 1fr); }
  .pmd-freeze-table.is-flat { width:var(--pmd-freeze-width); min-width:var(--pmd-freeze-width); table-layout: fixed; }
  .pmd-freeze-table.is-flat thead th { vertical-align: middle !important; }
  .pmd-freeze-head-table,.pmd-freeze-head-table thead,.pmd-freeze-head-table tr{height:100%}
  .pmd-freeze-table.is-flat thead .pmd-flat-meta-cell{overflow:visible}
  .pmd-flat-meta-cell { position:relative; padding:.28rem .38rem !important; font-size:.68rem; line-height:1.18; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .pmd-flat-metric .pmd-cell-pack, .pmd-flat-metric .pmd-cell-content { display: inline-block; white-space: nowrap; }
  .pmd-flat-metric .pmd-cell-content { margin-left: .22rem; }
  .pmd-flat-meta-cell:nth-child(1) { width:var(--pmd-flat-col-1,120px); min-width:var(--pmd-flat-col-1,120px); }
  .pmd-flat-meta-cell:nth-child(2) { width:var(--pmd-flat-col-2,220px); min-width:var(--pmd-flat-col-2,220px); }
  .pmd-flat-meta-cell:nth-child(3) { width:var(--pmd-flat-col-3,145px); min-width:var(--pmd-flat-col-3,145px); }
  .pmd-flat-meta-cell:nth-child(4) { width:var(--pmd-flat-col-4,155px); min-width:var(--pmd-flat-col-4,155px); }
  .pmd-freeze-table.is-flat thead th.pmd-flat-meta-cell { white-space:normal; overflow-wrap:anywhere; }
  .pmd-col-resizer{position:absolute;top:0;right:-3px;width:7px;height:100%;cursor:col-resize;z-index:12;background:transparent}
  .pmd-col-resizer:hover,.pmd-col-resizer.is-dragging{background:#f6a900}
  .pmd-headcard{display:flex!important;align-items:center;justify-content:center;gap:.35rem;white-space:nowrap}
  .pmd-day-group{min-width:420px!important}
  .pmd-stock-alert .pmd-cell-adjust-trigger{background:#fff!important;border-color:#fff!important;color:#8e1628!important}
  .pmd-flat-meta-cell .pmd-compact-stack { display:flex; flex-direction:column; gap:1px; min-width:0; line-height:1.08; }
  .pmd-flat-meta-cell .pmd-compact-main { font-weight:700; overflow:hidden; text-overflow:ellipsis; }
  .pmd-flat-meta-cell .pmd-compact-sub { font-size:.58rem; color:#765e61; overflow:hidden; text-overflow:ellipsis; }
  .pmd-stock-alert .pmd-flat-meta-cell .pmd-compact-sub { color:#ffe8e8 !important; }
  .pmd-flat-meta-cell .pmd-compact-value { display:flex; justify-content:space-between; gap:.3rem; white-space:nowrap; }
  .pmd-cell-adjust-trigger, .pmd-cell-adjust-trigger .ri { color:#7a1d2c !important; opacity:1 !important; }
  .pmd-matrix-shell.is-flat .pmd-scroll-table tbody tr.pmd-stock-alert td button.pmd-cell-adjust-trigger,
  .pmd-matrix-shell.is-flat .pmd-scroll-table tbody tr.pmd-stock-alert td button.pmd-cell-adjust-trigger i { color:#8e1628 !important; background:#fff !important; border-color:#fff !important; opacity:1 !important; }
  .pmd-table-wrap { max-height: none; }
  <?php endif; ?>
</style>

<div class="pmd-toast-stack" id="pmdToastStack" aria-live="polite" aria-atomic="true"></div>

<div id="pmdActionMsg" class="mb-2"></div>
<?php if (!$matrixEmbedded): ?><?php $matrix_month_selector = '#pmdMonth'; $this->load->view('inventory/_matrix_spreadsheet_panel'); ?><?php endif; ?>

<div class="card pmd-filter-card mb-2">
  <div class="card-body py-3">
    <div class="pmd-filter-grid">
      <div class="pmd-filter-field pmd-filter-main"<?php echo $matrixEmbedded ? ' style="display:none"' : ''; ?>>
        <label>Bulan</label>
        <input type="month" id="pmdMonth" class="form-control" value="<?php echo html_escape(substr($initialMonth, 0, 7)); ?>">
      </div>
      <div class="pmd-filter-field pmd-filter-division"<?php echo $matrixEmbedded ? ' style="display:none"' : ''; ?>>
        <label>Divisi</label>
        <select id="pmdDivision" class="form-select">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisionOptions as $d): ?>
            <?php
              $id = (int)($d['id'] ?? 0);
              $code = trim((string)($d['code'] ?? ''));
              $name = trim((string)($d['name'] ?? ''));
              $label = $code !== '' ? $code . ' - ' . $name : ($name !== '' ? $name : (string)$id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo $id === $initialDivisionId ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pmd-filter-field pmd-filter-destination">
        <label>Tujuan</label>
        <select id="pmdDestination" class="form-select">
          <option value="ALL" <?php echo $initialDestination === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="REGULER" <?php echo $initialDestination === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT" <?php echo $initialDestination === 'EVENT' ? 'selected' : ''; ?>>Event</option>
        </select>
      </div>
      <div class="pmd-filter-field pmd-filter-search">
        <label>Cari</label>
        <input type="text" id="pmdQ" class="form-control" value="<?php echo html_escape($initialQ); ?>" placeholder="Material, profile, merk, divisi">
      </div>
      <div class="pmd-filter-field pmd-filter-date">
        <label>Mulai Tampilan</label>
        <input type="date" id="pmdDateFrom" class="form-control" min="<?php echo html_escape($initialWindowStart); ?>" max="<?php echo html_escape($initialWindowEnd); ?>" value="<?php echo html_escape($initialDateFrom); ?>">
      </div>
      <div class="pmd-filter-field pmd-filter-date-to">
        <label>Sampai Tampilan</label>
        <input type="date" id="pmdDateTo" class="form-control" min="<?php echo html_escape($initialWindowStart); ?>" max="<?php echo html_escape($initialWindowEnd); ?>" value="<?php echo html_escape($initialDateTo); ?>">
      </div>
      <div class="pmd-filter-field pmd-filter-limit">
        <label>Limit</label>
        <input type="number" id="pmdLimit" class="form-control" min="1" max="1000" value="<?php echo (int)$initialLimit; ?>">
      </div>
      <div class="pmd-filter-actions">
        <button type="button" id="pmdApply" class="btn btn-primary">Terapkan</button>
        <button type="button" id="pmdClear" class="btn btn-outline-danger">Clear</button>
      </div>
    </div>
  </div>
</div>

<div class="pmd-kpi-row mb-2">
  <div class="pmd-kpi-card pmd-kpi-1">
    <span class="pmd-kpi-icon" aria-hidden="true"><i class="ri ri-archive-stack-line"></i></span>
    <div class="pmd-kpi-label">Total Profil</div>
    <div id="pmdStatProfiles" class="pmd-kpi-value">0</div>
    <div class="pmd-kpi-sub"><span id="pmdStatDivisions">0</span> divisi</div>
  </div>
  <div class="pmd-kpi-card pmd-kpi-2">
    <span class="pmd-kpi-icon" aria-hidden="true"><i class="ri ri-flask-line"></i></span>
    <div class="pmd-kpi-label">Material</div>
    <div id="pmdStatMaterials" class="pmd-kpi-value">0</div>
    <div class="pmd-kpi-sub">jenis bahan baku</div>
  </div>
  <div class="pmd-kpi-card pmd-kpi-3">
    <span class="pmd-kpi-icon" aria-hidden="true"><i class="ri ri-money-dollar-circle-line"></i></span>
    <div class="pmd-kpi-label">Nilai Sisa</div>
    <div id="pmdStatValue" class="pmd-kpi-value" style="font-size:1.08rem">0,00</div>
    <div class="pmd-kpi-sub">HPP stok aktif</div>
  </div>
  <div id="pmdStatAlertCard" class="pmd-kpi-card pmd-kpi-4">
    <span class="pmd-kpi-icon" aria-hidden="true"><i class="ri ri-alarm-warning-line"></i></span>
    <div class="pmd-kpi-label">Stok Alert</div>
    <div id="pmdStatAlert" class="pmd-kpi-value">0</div>
    <div class="pmd-kpi-sub">minus / habis</div>
  </div>
  <div class="pmd-kpi-card pmd-kpi-5">
    <span class="pmd-kpi-icon" aria-hidden="true"><i class="ri ri-arrow-down-circle-line"></i></span>
    <div class="pmd-kpi-label">Total Masuk (isi)</div>
    <div id="pmdStatIn" class="pmd-kpi-value" style="font-size:1.08rem">0,00</div>
    <div class="pmd-kpi-sub">periode ini</div>
  </div>
  <div class="pmd-kpi-card pmd-kpi-6">
    <span class="pmd-kpi-icon" aria-hidden="true"><i class="ri ri-arrow-up-circle-line"></i></span>
    <div class="pmd-kpi-label">Total Keluar (isi)</div>
    <div id="pmdStatOut" class="pmd-kpi-value" style="font-size:1.08rem">0,00</div>
    <div class="pmd-kpi-sub">adj: <span id="pmdStatAdj">0,00</span></div>
  </div>
</div>

<div class="card pmd-board-card">
  <div class="pmd-board-head">
    <div>
      <div class="fw-semibold">Matrix Daily Inventory Material</div>
    </div>
    <div class="pmd-legend">
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-open"></span>Awal</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-in"></span>In</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-out"></span>Out</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-adj"></span>Adj</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-close"></span>Akhir</span>
    </div>
    <!-- Surprise: daily activity sparkline -->
    <div id="pmdSparklineWrap" class="pmd-sparkline-wrap" style="display:none">
      <div class="pmd-sparkline-label">Aktivitas Masuk Harian</div>
      <div id="pmdSparkline" class="pmd-sparkline"></div>
    </div>
  </div>
  <div class="pmd-matrix-shell<?php echo $matrixEmbedded ? ' is-flat' : ''; ?>" id="pmdMatrixShell">
    <div class="pmd-freeze-pane">
      <div class="pmd-freeze-head-wrap">
        <table class="table pmd-freeze-table<?php echo $matrixEmbedded ? ' is-flat' : ''; ?> align-middle mb-0 pmd-freeze-head-table">
          <thead id="pmdFreezeHead"></thead>
        </table>
      </div>
      <table class="table pmd-freeze-table<?php echo $matrixEmbedded ? ' is-flat' : ''; ?> align-middle mb-0 pmd-freeze-body-table">
        <tbody id="pmdFreezeBody"><tr><td colspan="3" class="pmd-loading">Memuat data...</td></tr></tbody>
      </table>
    </div>
    <div class="pmd-scroll-pane">
      <div class="pmd-scroll-head-wrap" id="pmdScrollHeadWrap">
        <table class="table pmd-scroll-table align-middle mb-0 pmd-scroll-head-table">
          <thead id="pmdScrollHead"></thead>
        </table>
      </div>
      <div class="pmd-table-wrap" id="pmdTableWrap">
        <table class="table pmd-scroll-table align-middle mb-0 pmd-scroll-body-table">
          <tbody id="pmdScrollBody"><tr><td colspan="999" class="pmd-loading">Memuat data...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div id="pmdPaginationWrap" class="mt-2" style="display:none"></div>

<div class="modal fade" id="pmdDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Mutasi Harian Material</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="pmd-modal-card mb-3">
          <div class="fw-semibold" id="pmdModalTitle">-</div>
          <div class="small text-muted" id="pmdModalSubtitle">-</div>
        </div>
        <div id="pmdModalListWrap" class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0 fin-audit-table">
            <thead>
              <tr>
                <th class="col-date">Waktu</th>
                <th class="col-type">Tipe</th>
                <th class="text-end col-balance">Before</th>
                <th class="text-end col-delta">Delta</th>
                <th class="text-end col-balance">After</th>
                <th class="col-ref">Ref</th>
                <th class="col-notes">Catatan</th>
              </tr>
            </thead>
            <tbody id="pmdModalBody"><tr><td colspan="7" class="text-center text-muted py-3">Belum ada data.</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="pmdAdjustModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-uppercase text-muted fw-semibold">Adjustment Harian Divisi</div>
          <h5 class="modal-title mb-0">Penyesuaian Bahan Baku Langsung</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="pmdAdjustAlert" class="mb-2"></div>
        <div class="pmd-adjust-selected mb-3">
          <div class="fw-semibold" id="pmdAdjustTitle">-</div>
          <div class="small text-muted" id="pmdAdjustSubtitle">-</div>
          <div class="row g-2 mt-1 small">
            <div class="col-md-3"><div class="text-muted">Tanggal</div><div class="fw-semibold" id="pmdAdjustDateLabel">-</div></div>
            <div class="col-md-3"><div class="text-muted">Divisi / Tujuan</div><div class="fw-semibold" id="pmdAdjustDivisionLabel">-</div></div>
            <div class="col-md-3"><div class="text-muted">Adj existing</div><div class="fw-semibold" id="pmdAdjustExistingLabel">0,00</div></div>
            <div class="col-md-3"><div class="text-muted">Akhir hari</div><div class="fw-semibold" id="pmdAdjustClosingLabel">0,00</div></div>
          </div>
        </div>
        <div class="pmd-adjust-help mb-3">
          <div class="fw-semibold mb-1">Pola input divisi</div>
          <div>Pilih satu jenis koreksi untuk profile yang dipilih. Qty diisi dalam satuan isi, dan HPP adjustment akan otomatis mengikuti HPP profile line tersebut.</div>
        </div>
        <form id="pmdAdjustForm" class="row g-2" autocomplete="off">
          <div class="col-md-6">
            <label class="form-label">Catatan Header</label>
            <input type="text" class="form-control" id="pmdAdjustHeaderNotes" placeholder="Opsional, misalnya koreksi stok bar pagi">
          </div>
          <div class="col-md-6">
            <label class="form-label">Catatan Line</label>
            <input type="text" class="form-control" id="pmdAdjustLineNote" placeholder="Opsional">
          </div>
          <div class="col-md-4">
            <label class="form-label">Jenis Koreksi</label>
            <select class="form-select" id="pmdAdjustAction">
              <option value="">Pilih salah satu...</option>
              <option value="SPOIL">Spoil</option>
              <option value="WASTE">Waste</option>
              <option value="PROCESS_LOSS">Process Loss</option>
              <option value="MINUS">Minus</option>
              <option value="PLUS">Plus</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" id="pmdQtyLabel">Qty</label>
            <input type="number" class="form-control" id="pmdQtyInput" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label" id="pmdReasonLabel">Alasan</label>
            <select class="form-select" id="pmdReasonSelect">
              <option value="">Pilih jenis koreksi dulu</option>
            </select>
          </div>
          <div class="col-md-4 d-none" id="pmdAutoCostWrap">
            <label class="form-label">HPP Profile Otomatis</label>
            <input type="number" class="form-control" id="pmdAutoCostDisplay" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-4 d-none pmd-plus-only">
            <label class="form-label">Lot Masuk Manual</label>
            <input type="text" class="form-control" id="pmdInboundLotNo" placeholder="Opsional untuk adjustment +">
          </div>
          <div class="col-md-4 d-none pmd-plus-only">
            <label class="form-label">Exp Date Lot Masuk</label>
            <input type="date" class="form-control" id="pmdInboundExpiryDate">
          </div>
          <div class="col-12 d-none" id="pmdAutoCostHint">
            <div class="form-text">Untuk plus, HPP otomatis terisi dari profile line dan tetap bisa diubah manual. Jika koreksi hanya menutup saldo minus sampai 0, lot baru tidak dibuat.</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <span class="small text-muted me-auto">Simpan DRAFT, lalu verifikasi password dan Post di halaman Adjustment.</span>
        <button type="button" class="btn btn-primary" id="pmdAdjustSubmit">Simpan &amp; Lanjut Verifikasi</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var matrixUrl = <?php echo json_encode((string)($matrix_url ?? site_url('inventory-material-daily/matrix'))); ?>;
  var detailUrl = <?php echo json_encode((string)($detail_url ?? site_url('inventory-daily/cell-detail'))); ?>;
  var adjustmentStoreUrl = <?php echo json_encode($adjustmentStoreUrl); ?>;
  var adjustmentIndexUrl = <?php echo json_encode($adjustmentIndexUrl); ?>;
  var stockAdjustmentCsrfToken = <?php echo json_encode((string)($stock_adjustment_csrf_token ?? '')); ?>;
  var matrixEmbedded = <?php echo $matrixEmbedded ? 'true' : 'false'; ?>;
  var embeddedDivisionId = <?php echo (int)$initialDivisionId; ?>;
  var defaultMonth = <?php echo json_encode(substr($initialMonth, 0, 7)); ?>;
  var destinationGuardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var adjustmentReasonOptions = {
    WASTE: [
      { value: 'other', label: 'Other' },
      { value: 'cancel_order', label: 'Cancel Order' },
      { value: 'kitchen_error', label: 'Kitchen Error' },
      { value: 'overproduction', label: 'Overproduction' },
      { value: 'spillage', label: 'Spillage / Tumpah' },
      { value: 'prep_trim_excess', label: 'Prep Trim Excess' },
      { value: 'expired_opened', label: 'Expired Opened' }
    ],
    SPOILAGE: [
      { value: 'other', label: 'Other' },
      { value: 'expired', label: 'Expired' },
      { value: 'temperature_abuse', label: 'Temperature Abuse' },
      { value: 'contamination', label: 'Contamination' },
      { value: 'overstock', label: 'Overstock' },
      { value: 'improper_storage', label: 'Improper Storage' }
    ],
    PROCESS_LOSS: [
      { value: 'other', label: 'Other' },
      { value: 'defrost_loss', label: 'Defrost Loss' },
      { value: 'trimming_standard', label: 'Trimming Standard' },
      { value: 'cooking_loss', label: 'Cooking Loss' },
      { value: 'evaporation', label: 'Evaporation' },
      { value: 'brew_loss', label: 'Brew Loss' },
      { value: 'absorption_loss', label: 'Absorption Loss' },
      { value: 'process_residue', label: 'Process Residue' },
      { value: 'variable_process_consumable', label: 'Variable Process Consumable' }
    ],
    VARIANCE: [
      { value: 'other', label: 'Other' },
      { value: 'over_usage', label: 'Over Usage' },
      { value: 'under_usage', label: 'Under Usage' },
      { value: 'unrecorded_usage', label: 'Unrecorded Usage' },
      { value: 'counting_error', label: 'Counting Error' },
      { value: 'system_mismatch', label: 'System Mismatch' },
      { value: 'theft_suspected', label: 'Theft Suspected' },
      { value: 'unknown_shrinkage', label: 'Unknown Shrinkage' }
    ],
    ADJUSTMENT_PLUS: [
      { value: 'other', label: 'Other' },
      { value: 'opening_correction', label: 'Opening Correction' },
      { value: 'stock_found', label: 'Stock Found' },
      { value: 'manual_reclass', label: 'Manual Reclass' }
    ]
  };
  var destinationOptionMeta = [
    { value: 'ALL', label: 'Semua' },
    { value: 'REGULER', label: 'Reguler' },
    { value: 'EVENT', label: 'Event' },
    { value: 'OTHER', label: 'Lainnya' }
  ];

  var state = {
    month: defaultMonth,
    division_id: <?php echo (int)$initialDivisionId; ?>,
    material_id: <?php echo (int)$initialMaterialId; ?>,
    destination: <?php echo json_encode($initialDestination); ?>,
    q: <?php echo json_encode($initialQ); ?>,
    date_from: <?php echo json_encode($initialDateFrom); ?>,
    date_to: <?php echo json_encode($initialDateTo); ?>,
    limit: <?php echo (int)$initialLimit; ?>,
    offset: 0,
    totalCount: 0,
    dates: [],
    groups: [],
    expanded: {}
  };
  var serverToday = <?php echo json_encode(date('Y-m-d')); ?>;
  function isFutureDate(dateText) {
    return String(dateText || '') > serverToday;
  }

  var freezeHead = document.getElementById('pmdFreezeHead');
  var freezeBody = document.getElementById('pmdFreezeBody');
  var tableHead = document.getElementById('pmdScrollHead');
  var tableBody = document.getElementById('pmdScrollBody');
  var tableHeadWrap = document.getElementById('pmdScrollHeadWrap');
  var tableWrap = document.getElementById('pmdTableWrap');
  var matrixShell = document.getElementById('pmdMatrixShell');
  var modalEl = document.getElementById('pmdDetailModal');
  if (modalEl && modalEl.parentNode !== document.body) { document.body.appendChild(modalEl); }
  if (modalEl) { modalEl.style.zIndex = '2000'; }
  var modal = (modalEl && window.bootstrap && bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;
  var adjustModalEl = document.getElementById('pmdAdjustModal');
  if (adjustModalEl && adjustModalEl.parentNode !== document.body) { document.body.appendChild(adjustModalEl); }
  if (adjustModalEl) { adjustModalEl.style.zIndex = '2000'; }
  var adjustModal = (adjustModalEl && window.bootstrap && bootstrap.Modal) ? new bootstrap.Modal(adjustModalEl) : null;
  var adjustContext = null;
  var adjustBackdropEl = null;
  var adjustActionMeta = {
    SPOIL: { label: 'Spoil', reasonLabel: 'Alasan Spoil', reasonCategory: 'SPOILAGE' },
    WASTE: { label: 'Waste', reasonLabel: 'Alasan Waste', reasonCategory: 'WASTE' },
    PROCESS_LOSS: { label: 'Process Loss', reasonLabel: 'Alasan Process Loss', reasonCategory: 'PROCESS_LOSS' },
    MINUS: { label: 'Minus', reasonLabel: 'Alasan Minus', reasonCategory: 'VARIANCE' },
    PLUS: { label: 'Plus', reasonLabel: 'Alasan Plus', reasonCategory: 'ADJUSTMENT_PLUS' }
  };

  function esc(value){
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function num(value){
    var n = Number(value || 0);
    return n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function money(value){
    return num(value);
  }

  function weekdayName(dateText){
    var names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    var d = new Date(dateText + 'T00:00:00');
    if (Number.isNaN(d.getTime())) { return ''; }
    return names[d.getDay()] || '';
  }

  function isToday(dateText){
    var now = new Date();
    var yyyy = String(now.getFullYear());
    var mm = String(now.getMonth() + 1).padStart(2, '0');
    var dd = String(now.getDate()).padStart(2, '0');
    return dateText === (yyyy + '-' + mm + '-' + dd);
  }

  var stickySyncFrame = 0;

  function applyStickyLayout(){
    var freezeHeaderRow = freezeHead.querySelector('tr:first-child');
    if (matrixEmbedded && freezeHeaderRow && freezeHeaderRow.children.length === 4) {
      var flatMetricHead = tableHead.querySelector('th.pmd-submetric');
      if (flatMetricHead) document.documentElement.style.setProperty('--pmd-date-col', Math.ceil(flatMetricHead.getBoundingClientRect().width) + 'px');
      return;
    }
    if (!freezeHeaderRow || freezeHeaderRow.children.length < (matrixEmbedded ? 2 : 3)) { return; }

    var c1 = matrixEmbedded ? null : freezeHeaderRow.children[0];
    var c2 = freezeHeaderRow.children[matrixEmbedded ? 0 : 1];
    var c3 = freezeHeaderRow.children[matrixEmbedded ? 1 : 2];

    var w1 = matrixEmbedded ? 0 : Math.max(132, Math.ceil(c1.getBoundingClientRect().width));
    var w2 = Math.max(188, Math.ceil(c2.getBoundingClientRect().width));
    var w3 = Math.max(320, Math.ceil(c3.getBoundingClientRect().width));

    var rootStyle = document.documentElement.style;
    rootStyle.setProperty('--pmd-col-division', w1 + 'px');
    rootStyle.setProperty('--pmd-col-material', w2 + 'px');
    rootStyle.setProperty('--pmd-col-detail', w3 + 'px');
    var firstHeaderRow = tableHead.querySelector('tr');
    var firstHeaderHeight = firstHeaderRow ? Math.max(0, Math.ceil(firstHeaderRow.getBoundingClientRect().height)) : 0;
    if (firstHeaderHeight > 0) {
      rootStyle.setProperty('--pmd-header-row-1', firstHeaderHeight + 'px');
    }
    var metricHead = tableHead.querySelector('th.pmd-metric-cell');
    if (metricHead) {
      var metricWidth = Math.max(112, Math.ceil(metricHead.getBoundingClientRect().width));
      rootStyle.setProperty('--pmd-date-col', metricWidth + 'px');
    }
    rootStyle.setProperty('--pmd-left-1', '0px');
    rootStyle.setProperty('--pmd-left-2', w1 + 'px');
    rootStyle.setProperty('--pmd-left-3', (w1 + w2) + 'px');
  }

  function syncFlatHeaderHeight(){
    if (!matrixEmbedded) { return; }
    var freezeWrap = document.querySelector('.pmd-freeze-head-wrap');
    var scrollWrap = document.getElementById('pmdScrollHeadWrap');
    var scrollTable = scrollWrap ? scrollWrap.querySelector('table') : null;
    if (!freezeWrap || !scrollWrap || !scrollTable) { return; }
    var height = Math.ceil(scrollTable.getBoundingClientRect().height);
    if (height > 0) {
      freezeWrap.style.height = height + 'px';
      scrollWrap.style.height = height + 'px';
    }
  }

  function initFlatColumnResize(){
    if (!matrixEmbedded || !freezeHead) { return; }
    var cells = Array.prototype.slice.call(freezeHead.querySelectorAll('th.pmd-flat-meta-cell'));
    cells.forEach(function(cell, index){
      var key = 'pmd-flat-narrow-col-v1-' + (index + 1);
      var property = '--pmd-flat-col-' + (index + 1);
      var saved = Number(window.localStorage.getItem(key) || 0);
      if (saved >= 60 && saved <= 420) document.documentElement.style.setProperty(property, saved + 'px');
      var handle = cell.querySelector('.pmd-col-resizer');
      if (!handle || handle.dataset.ready === '1') return;
      handle.dataset.ready = '1';
      handle.addEventListener('pointerdown', function(event){
        event.preventDefault();
        handle.setPointerCapture(event.pointerId);
        handle.classList.add('is-dragging');
        var startX = event.clientX;
        var startWidth = cell.getBoundingClientRect().width;
        function move(moveEvent){
          var width = Math.max(60, Math.min(420, Math.round(startWidth + moveEvent.clientX - startX)));
          document.documentElement.style.setProperty(property, width + 'px');
          window.localStorage.setItem(key, String(width));
          syncStickyLayout();
        }
        function stop(){
          handle.classList.remove('is-dragging');
          handle.removeEventListener('pointermove', move);
          handle.removeEventListener('pointerup', stop);
          handle.removeEventListener('pointercancel', stop);
        }
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', stop);
        handle.addEventListener('pointercancel', stop);
      });
    });
  }

  function syncStickyTopOffset(){
    var navbar = document.getElementById('layout-navbar') || document.querySelector('.layout-navbar');
    var topOffset = navbar ? Math.ceil(navbar.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--pmd-sticky-top', topOffset + 'px');
  }

  function syncStickyLayout(){
    if (stickySyncFrame) {
      cancelAnimationFrame(stickySyncFrame);
    }
    stickySyncFrame = requestAnimationFrame(function(){
      stickySyncFrame = 0;
      syncStickyTopOffset();
      applyStickyLayout();
      syncFlatHeaderHeight();
    });
  }

  function getStickyOffset(){
    var rootStyle = getComputedStyle(document.documentElement);
    return (
      Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-division')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-material')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-detail'))
    ) || 0;
  }

  function scrollToTodayColumn(){
    if (!tableWrap) { return; }
    var todayCell = tableHead.querySelector('.pmd-day-head.is-today');
    if (!todayCell) { return; }
    var targetLeft = todayCell.offsetLeft - 12;
    tableWrap.scrollLeft = Math.max(0, targetLeft);
    if (tableHeadWrap) {
      tableHeadWrap.scrollLeft = tableWrap.scrollLeft;
    }
  }

  function syncMonthWindowInputs(){
    var monthEl = document.getElementById('pmdMonth');
    var fromEl = document.getElementById('pmdDateFrom');
    var toEl = document.getElementById('pmdDateTo');
    var monthText = monthEl ? String(monthEl.value || '') : '';
    if (!/^\d{4}-\d{2}$/.test(monthText) || !fromEl || !toEl) { return; }
    var start = monthText + '-01';
    var parts = monthText.split('-');
    var end = monthText + '-' + String(new Date(Number(parts[0]), Number(parts[1]), 0).getDate()).padStart(2, '0');
    [fromEl, toEl].forEach(function(input){
      input.min = start;
      input.max = end;
      if (input.value && input.value < start) { input.value = start; }
      if (input.value && input.value > end) { input.value = end; }
    });
    if (fromEl.value && toEl.value && fromEl.value > toEl.value) {
      toEl.value = fromEl.value;
    }
  }

  function readFilters(){
    state.month = document.getElementById('pmdMonth').value || '';
    syncMonthWindowInputs();
    state.division_id = parseInt(document.getElementById('pmdDivision').value || '0', 10);
    if (!Number.isFinite(state.division_id) || state.division_id < 0) { state.division_id = 0; }
    state.destination = document.getElementById('pmdDestination').value || 'ALL';
    state.q = document.getElementById('pmdQ').value || '';
    state.date_from = document.getElementById('pmdDateFrom').value || '';
    state.date_to = document.getElementById('pmdDateTo').value || '';
    state.limit = parseInt(document.getElementById('pmdLimit').value || '120', 10);
    if (!Number.isFinite(state.limit) || state.limit <= 0 || state.limit > 1000) {
      state.limit = 120;
    }
  }

  function clearFilters(){
    document.getElementById('pmdMonth').value = defaultMonth;
    document.getElementById('pmdDivision').value = matrixEmbedded ? String(embeddedDivisionId) : '0';
    document.getElementById('pmdDestination').value = 'ALL';
    document.getElementById('pmdQ').value = '';
    document.getElementById('pmdDateFrom').value = '';
    document.getElementById('pmdDateTo').value = '';
    document.getElementById('pmdLimit').value = '120';
    readFilters();
    state.material_id = 0;
  }

  function applyDestinationGuard(){
    var divisionEl = document.getElementById('pmdDivision');
    var destinationEl = document.getElementById('pmdDestination');
    if (!divisionEl || !destinationEl) { return; }
    var divisionId = parseInt(divisionEl.value || '0', 10);
    var current = String(state.destination || destinationEl.value || 'ALL').toUpperCase();
    if (!Number.isFinite(divisionId) || divisionId <= 0 || !destinationGuardMap[String(divisionId)]) {
      destinationEl.innerHTML = destinationOptionMeta.filter(function(opt){
        return opt.value === 'ALL' || opt.value === 'REGULER' || opt.value === 'EVENT';
      }).map(function(opt){
        return '<option value="' + esc(opt.value) + '">' + esc(opt.label) + '</option>';
      }).join('');
    } else {
      var allowed = (destinationGuardMap[String(divisionId)] || []).map(function(x){ return String(x || '').toUpperCase(); });
      var groupOptions = [];
      var hasRegular = allowed.some(function(opt){ return opt.indexOf('_EVENT') === -1; });
      var hasEvent = allowed.some(function(opt){ return opt.indexOf('_EVENT') !== -1; });
      if (hasRegular) {
        groupOptions.push('REGULER');
      }
      if (hasEvent) {
        groupOptions.push('EVENT');
      }
      var options = destinationOptionMeta.filter(function(opt){
        var value = String(opt.value || '').toUpperCase();
        if (value === 'ALL') { return true; }
        return groupOptions.indexOf(value) !== -1;
      });
      destinationEl.innerHTML = options.map(function(opt){
        return '<option value="' + esc(opt.value) + '">' + esc(opt.label) + '</option>';
      }).join('');
    }
    var exists = Array.prototype.some.call(destinationEl.options, function(opt){
      return String(opt.value || '').toUpperCase() === current;
    });
    destinationEl.value = exists ? current : 'ALL';
  }

  function buildQuery(){
    var p = new URLSearchParams();
    if (state.month) { p.set('month', state.month); }
    if (state.q) { p.set('q', state.q); }
    if (state.date_from) { p.set('date_from', state.date_from); }
    if (state.date_to) { p.set('date_to', state.date_to); }
    if (state.division_id > 0) { p.set('division_id', String(state.division_id)); }
    if (state.material_id > 0) { p.set('material_id', String(state.material_id)); }
    if (state.destination && state.destination !== 'ALL') { p.set('destination', state.destination); }
    p.set('limit', String(state.limit));
    if (state.offset > 0) { p.set('offset', String(state.offset)); }
    return p.toString();
  }

  function parseJson(response){
    return response.text().then(function(text){
      var data = null;
      try { data = JSON.parse(text); } catch (err) { data = null; }
      if (!response.ok) {
        throw new Error((data && data.message) ? data.message : (text || ('HTTP ' + response.status)));
      }
      if (!data) {
        throw new Error('Respons endpoint tidak valid.');
      }
      return data;
    });
  }

  function showMessage(ok, text){
    var box = document.getElementById('pmdActionMsg');
    if (!box) { return; }
    if (!text) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + esc(text) + '</div>';
  }

  function showToast(message, kind, title){
    var stack = document.getElementById('pmdToastStack');
    if (!stack || !message) { return; }
    var item = document.createElement('div');
    item.className = 'pmd-toast ' + (kind === 'error' ? 'error' : 'success');
    item.innerHTML = '<div class="pmd-toast-title">' + esc(title || 'Informasi') + '</div><div class="pmd-toast-body">' + esc(message) + '</div>';
    stack.appendChild(item);
    window.requestAnimationFrame(function(){
      item.classList.add('is-visible');
    });
    window.setTimeout(function(){
      item.classList.remove('is-visible');
      window.setTimeout(function(){
        if (item.parentNode) {
          item.parentNode.removeChild(item);
        }
      }, 220);
    }, 2600);
  }

  function requestJson(url, options){
    return fetch(url, options || {}).then(parseJson);
  }

  function fillReasonSelect(selectId, category){
    var el = document.getElementById(selectId);
    if (!el) { return; }
    var options = adjustmentReasonOptions[category] || [];
    el.innerHTML = options.map(function(opt){
      return '<option value="' + esc(opt.value) + '">' + esc(opt.label) + '</option>';
    }).join('');
    el.value = 'other';
  }

  function showAdjustAlert(ok, text){
    var box = document.getElementById('pmdAdjustAlert');
    if (!box) { return; }
    if (!text) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + esc(text) + '</div>';
  }

  function setSubmitButtonLoading(button, label){
    if (!button) { return; }
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label || 'Memproses...');
      return;
    }
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + esc(label || 'Memproses...');
  }

  function clearSubmitButtonLoading(button){
    if (!button) { return; }
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button);
      return;
    }
    button.disabled = false;
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }

  function openAdjustModalFallback(){
    if (!adjustModalEl || adjustModal) { return; }
    if (adjustModalEl.parentNode !== document.body) { document.body.appendChild(adjustModalEl); }
    adjustModalEl.style.display = 'block';
    adjustModalEl.style.position = 'fixed';
    adjustModalEl.style.inset = '0';
    adjustModalEl.style.zIndex = '2000';
    adjustModalEl.classList.add('show');
    adjustModalEl.removeAttribute('aria-hidden');
    adjustModalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    if (!adjustBackdropEl) {
      adjustBackdropEl = document.createElement('div');
      adjustBackdropEl.className = 'modal-backdrop fade show';
      adjustBackdropEl.style.zIndex = '1990';
      document.body.appendChild(adjustBackdropEl);
    }
  }

  function closeAdjustModalFallback(){
    if (!adjustModalEl || adjustModal) { return; }
    adjustModalEl.classList.remove('show');
    adjustModalEl.style.display = 'none';
    adjustModalEl.setAttribute('aria-hidden', 'true');
    adjustModalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    if (adjustBackdropEl) {
      adjustBackdropEl.remove();
      adjustBackdropEl = null;
    }
  }

  function resetAdjustForm(){
    ['pmdQtyInput'].forEach(function(id){
      var el = document.getElementById(id);
      if (!el) { return; }
      el.value = '0';
    });
    var actionEl = document.getElementById('pmdAdjustAction');
    if (actionEl) { actionEl.value = ''; }
    document.getElementById('pmdQtyLabel').textContent = 'Qty';
    document.getElementById('pmdReasonLabel').textContent = 'Alasan';
    document.getElementById('pmdReasonSelect').innerHTML = '<option value="">Pilih jenis koreksi dulu</option>';
    document.getElementById('pmdAutoCostDisplay').value = '0';
    document.getElementById('pmdAutoCostWrap').classList.add('d-none');
    document.getElementById('pmdAutoCostHint').classList.add('d-none');
    Array.prototype.forEach.call(document.querySelectorAll('.pmd-plus-only'), function(el){ el.classList.add('d-none'); });
    ['pmdAdjustHeaderNotes', 'pmdAdjustLineNote', 'pmdInboundLotNo', 'pmdInboundExpiryDate'].forEach(function(id){
      var el = document.getElementById(id);
      if (el) { el.value = ''; }
    });
    showAdjustAlert(true, '');
  }

  function updateAdjustActionUi(){
    var action = String((document.getElementById('pmdAdjustAction').value || '')).toUpperCase();
    var meta = adjustActionMeta[action] || null;
    var reasonEl = document.getElementById('pmdReasonSelect');
    document.getElementById('pmdQtyInput').value = '0';
    if (!meta) {
      document.getElementById('pmdQtyLabel').textContent = 'Qty';
      document.getElementById('pmdReasonLabel').textContent = 'Alasan';
      reasonEl.innerHTML = '<option value="">Pilih jenis koreksi dulu</option>';
      document.getElementById('pmdAutoCostDisplay').value = '0';
      document.getElementById('pmdAutoCostWrap').classList.add('d-none');
      document.getElementById('pmdAutoCostHint').classList.add('d-none');
      Array.prototype.forEach.call(document.querySelectorAll('.pmd-plus-only'), function(el){ el.classList.add('d-none'); });
      return;
    }
    document.getElementById('pmdQtyLabel').textContent = meta.label + ' (Isi)';
    document.getElementById('pmdReasonLabel').textContent = meta.reasonLabel;
    fillReasonSelect('pmdReasonSelect', meta.reasonCategory);
    if (action === 'PLUS') {
      document.getElementById('pmdAutoCostDisplay').value = String(Number.isFinite(adjustContext && adjustContext.defaultUnitCostInput) ? adjustContext.defaultUnitCostInput : 0);
      document.getElementById('pmdAutoCostWrap').classList.remove('d-none');
      document.getElementById('pmdAutoCostHint').classList.remove('d-none');
      Array.prototype.forEach.call(document.querySelectorAll('.pmd-plus-only'), function(el){ el.classList.remove('d-none'); });
    } else {
      document.getElementById('pmdAutoCostDisplay').value = '0';
      document.getElementById('pmdAutoCostWrap').classList.add('d-none');
      document.getElementById('pmdAutoCostHint').classList.add('d-none');
      Array.prototype.forEach.call(document.querySelectorAll('.pmd-plus-only'), function(el){ el.classList.add('d-none'); });
    }
  }

  function openAdjust(groupIndex, profileIndex, dateText){
    if (isFutureDate(dateText)) {
      showMessage(false, 'Tanggal masa depan hanya ditampilkan sebagai kolom kalender dan belum boleh menerima adjustment stok.');
      return;
    }
    var group = state.groups[groupIndex];
    var row = group && group.children ? group.children[profileIndex] : null;
    if (!group || !row || !dateText) {
      showMessage(false, 'Untuk adjustment langsung, pilih baris profile/material tunggal. Jika masih grup, expand dulu.');
      return;
    }

    var day = (row.daily && row.daily[dateText]) ? row.daily[dateText] : null;
    adjustContext = {
      date: dateText,
      group: group,
      row: row,
      day: day || { adjustment: 0, closing: 0 },
      defaultUnitCostInput: Number(resolveReferenceUnitPrice(row, group) || ((row.metrics && row.metrics.unit_price) || 0) || ((group.metrics && group.metrics.last_unit_price) || 0) || ((group.metrics && group.metrics.hpp) || 0))
    };

    resetAdjustForm();
    document.getElementById('pmdAdjustTitle').textContent = (group.material_name || group.item_name || '-') + ' | ' + (row.profile_name || '-');
    document.getElementById('pmdAdjustSubtitle').textContent = [
      group.material_code || group.item_code || '-',
      row.profile_brand || '-',
      row.profile_description || '-'
    ].join(' | ');
    document.getElementById('pmdAdjustDateLabel').textContent = dateText;
    document.getElementById('pmdAdjustDivisionLabel').textContent = divisionLabel(group) + ' / ' + destinationLabel(group);
    document.getElementById('pmdAdjustExistingLabel').textContent = num(Number(adjustContext.day.adjustment || 0)) + (row.profile_content_uom_code ? (' ' + row.profile_content_uom_code) : '');
    document.getElementById('pmdAdjustClosingLabel').textContent = num(Number(adjustContext.day.closing || 0)) + (row.profile_content_uom_code ? (' ' + row.profile_content_uom_code) : '');
    document.getElementById('pmdAutoCostDisplay').value = String(Number.isFinite(adjustContext.defaultUnitCostInput) ? adjustContext.defaultUnitCostInput : 0);
    document.getElementById('pmdInboundExpiryDate').value = '';
    updateAdjustActionUi();

    if (adjustModalEl && adjustModalEl.parentNode !== document.body) { document.body.appendChild(adjustModalEl); }
    if (adjustModalEl) { adjustModalEl.style.zIndex = '2000'; }
    if (adjustModal) {
      adjustModal.show();
    } else {
      openAdjustModalFallback();
    }
  }

  function buildAdjustPayload(){
    if (!adjustContext || !adjustContext.row) {
      throw new Error('Konteks adjustment belum siap.');
    }

    var destinationType = String(adjustContext.row.destination_type || '').trim().toUpperCase();
    if (!destinationType || ['ALL', 'REGULER', 'EVENT'].indexOf(destinationType) !== -1) {
      throw new Error('Tujuan stok profil tidak valid. Muat ulang Matrix dan pilih baris profil yang tepat.');
    }

    var action = String((document.getElementById('pmdAdjustAction').value || '')).toUpperCase();
    var qtyInput = Number(document.getElementById('pmdQtyInput').value || 0);
    var unitCostInput = Number(document.getElementById('pmdAutoCostDisplay').value || adjustContext.defaultUnitCostInput || 0);
    if (!adjustActionMeta[action]) {
      throw new Error('Pilih dulu salah satu jenis koreksi: spoil, waste, process loss, minus, atau plus.');
    }
    if (!(qtyInput > 0)) {
      throw new Error('Qty koreksi harus lebih dari nol.');
    }
    var qtyWaste = 0;
    var qtySpoil = 0;
    var qtyProcessLoss = 0;
    var qtyVariance = 0;
    var qtyPlus = 0;
    if (action === 'WASTE') { qtyWaste = qtyInput; }
    if (action === 'SPOIL') { qtySpoil = qtyInput; }
    if (action === 'PROCESS_LOSS') { qtyProcessLoss = qtyInput; }
    if (action === 'MINUS') { qtyVariance = qtyInput; }
    if (action === 'PLUS') { qtyPlus = qtyInput; }

    return {
      stock_scope: 'DIVISION',
      adjustment_date: adjustContext.date,
      division_id: Number(adjustContext.group.division_id || adjustContext.row.division_id || 0),
      destination_type: destinationType,
      notes: document.getElementById('pmdAdjustHeaderNotes').value || '',
      lines: [{
        stock_domain: null,
        item_id: Number(adjustContext.row.item_id || adjustContext.group.item_id || 0),
        material_id: Number(adjustContext.row.material_id || adjustContext.group.material_id || 0),
        buy_uom_id: Number(adjustContext.row.buy_uom_id || 0),
        content_uom_id: Number(adjustContext.row.content_uom_id || 0),
        profile_key: String(adjustContext.row.profile_key || ''),
        profile_name: String(adjustContext.row.profile_name || ''),
        profile_brand: String(adjustContext.row.profile_brand || ''),
        profile_description: String(adjustContext.row.profile_description || ''),
        profile_content_per_buy: Number(adjustContext.row.profile_content_per_buy || 1),
        profile_buy_uom_code: String(adjustContext.row.profile_buy_uom_code || ''),
        profile_content_uom_code: String(adjustContext.row.profile_content_uom_code || ''),
        qty_waste_content: qtyWaste,
        waste_reason_code: action === 'WASTE' ? (document.getElementById('pmdReasonSelect').value || 'other') : 'other',
        qty_spoil_content: qtySpoil,
        spoil_reason_code: action === 'SPOIL' ? (document.getElementById('pmdReasonSelect').value || 'other') : 'other',
        qty_process_loss_content: qtyProcessLoss,
        process_loss_reason_code: action === 'PROCESS_LOSS' ? (document.getElementById('pmdReasonSelect').value || 'other') : 'other',
        qty_variance_content: qtyVariance,
        variance_reason_code: action === 'MINUS' ? (document.getElementById('pmdReasonSelect').value || 'other') : 'other',
        qty_adjustment_plus_content: qtyPlus,
        adjustment_plus_reason_code: action === 'PLUS' ? (document.getElementById('pmdReasonSelect').value || 'other') : 'other',
        unit_cost: unitCostInput,
        inbound_lot_no: document.getElementById('pmdInboundLotNo').value || '',
        inbound_expiry_date: document.getElementById('pmdInboundExpiryDate').value || '',
        note: document.getElementById('pmdAdjustLineNote').value || ''
      }]
    };
  }

  function submitAdjust(){
    var submitBtn = document.getElementById('pmdAdjustSubmit');
    if (submitBtn.disabled) return;
    var savedResult = null;
    try {
      var payload = buildAdjustPayload();
      if (!/^[0-9a-f]{64}$/i.test(stockAdjustmentCsrfToken)) {
        throw new Error('Sesi adjustment belum siap. Muat ulang halaman sebelum menyimpan.');
      }
      showAdjustAlert(true, '');
      setSubmitButtonLoading(submitBtn, 'Menyimpan draft...');
      requestJson(adjustmentStoreUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-Stock-Adjustment-Csrf': stockAdjustmentCsrfToken
        },
        body: JSON.stringify(payload)
      }).then(function(result){
        if (!(Number(result.id) > 0) || !result.adjustment_no) {
          throw new Error('Respons draft tidak lengkap. Periksa halaman Adjustment sebelum mencoba lagi.');
        }
        savedResult = result;
        showAdjustAlert(true, 'Draft ' + result.adjustment_no + ' tersimpan. Lanjutkan verifikasi dan Post di halaman Adjustment. Stok belum berubah.');
        var next = new URL(adjustmentIndexUrl, window.location.href);
        next.searchParams.set('q', result.adjustment_no);
        next.searchParams.set('month', payload.adjustment_date.slice(0, 7));
        next.searchParams.set('status', 'DRAFT');
        window.location.assign(next.toString());
      }).catch(function(err){
        var msg = err && err.message ? err.message : 'Gagal memproses adjustment.';
        if (savedResult) msg += ' Draft ' + savedResult.adjustment_no + ' sudah tersimpan; buka halaman Adjustment untuk melanjutkan.';
        showAdjustAlert(false, msg);
        showToast(msg, 'error', 'Simpan Draft');
      }).finally(function(){
        if (!savedResult) clearSubmitButtonLoading(submitBtn);
      });
    } catch (err) {
      showAdjustAlert(false, err && err.message ? err.message : 'Payload adjustment tidak valid.');
    }
  }

  var pmdActionEl = document.getElementById('pmdAdjustAction');
  if (pmdActionEl) {
    pmdActionEl.addEventListener('change', updateAdjustActionUi);
  }

  function divisionLabel(row){
    var code = row.division_code || '';
    var name = row.division_name || '';
    if (code && name && String(code).toUpperCase() === String(name).toUpperCase()) {
      return code;
    }
    if (code && name) { return code + ' - ' + name; }
    if (name) { return name; }
    if (code) { return code; }
    return String(row.division_id || '-');
  }

  function destinationLabel(row){
    return String(row.destination_group || '').toUpperCase() === 'EVENT' ? 'Event' : 'Reguler';
  }

  function normalizeProfileDaily(rowDaily, dates, contentPerBuy){
    var source = rowDaily && typeof rowDaily === 'object' ? rowDaily : {};
    var normalized = {};
    var prevClosing = 0;
    var prevValue = 0;
    var initialized = false;
    var packSize = Number(contentPerBuy || 0);

    dates.forEach(function(dateKey){
      var raw = source[dateKey] || null;
      var opening = initialized ? prevClosing : 0;
      var inQty = 0;
      var outQty = 0;
      var adjQty = 0;
      var closing = opening;
      var mutations = 0;
      var totalValue = prevValue;

      if (raw) {
        opening = initialized ? prevClosing : Number(raw.opening || opening);
        inQty = Number(raw.in || 0);
        outQty = Number(raw.out || 0);
        adjQty = Number(raw.adjustment || 0);
        var hasRawClosing = raw.closing !== undefined && raw.closing !== null && raw.closing !== '';
        var rawClosing = hasRawClosing ? Number(raw.closing) : opening;
        var computedClosing = opening + inQty - outQty + adjQty;
        closing = hasRawClosing ? rawClosing : computedClosing;
        mutations = Number(raw.mutations || 0);
        totalValue = Number(raw.total_value || totalValue);
      }

      normalized[dateKey] = {
        opening: opening,
        in: inQty,
        out: outQty,
        adjustment: adjQty,
        closing: closing,
        opening_pack: packSize > 0 ? (opening / packSize) : 0,
        in_pack: packSize > 0 ? (inQty / packSize) : 0,
        out_pack: packSize > 0 ? (outQty / packSize) : 0,
        adjustment_pack: packSize > 0 ? (adjQty / packSize) : 0,
        closing_pack: packSize > 0 ? (closing / packSize) : 0,
        mutations: mutations,
        total_value: totalValue
      };

      prevClosing = closing;
      prevValue = totalValue;
      initialized = true;
    });

    return normalized;
  }

  function calcMetrics(dailyByDate, contentPerBuy, dates, monthlyOpeningContent, monthlyOpeningPack){
    var inTotal = 0;
    var outTotal = 0;
    var adjTotal = 0;
    var openingContent = 0;
    var closingContent = 0;
    var totalValue = 0;
    var mutationTotal = 0;

    var openingFromMonth = monthlyOpeningContent !== undefined && monthlyOpeningContent !== null;
    var closingDate = dates.length ? dates[dates.length - 1] : '';
    if (dates.length && dates[0] <= serverToday) {
      var actualDates = dates.filter(function(dateKey){ return dateKey <= serverToday; });
      if (actualDates.length) {
        closingDate = actualDates[actualDates.length - 1];
      }
    }

    var firstDateOpening = dates.length
      ? Number((dailyByDate[dates[0]] && dailyByDate[dates[0]].opening) || 0)
      : 0;
    var useFirstDateOpening = !openingFromMonth || Math.abs(firstDateOpening) > 0.000001;

    if (dates.length > 0) {
      openingContent = useFirstDateOpening
        ? firstDateOpening
        : Number(monthlyOpeningContent || 0);
      closingContent = Number((dailyByDate[closingDate] && dailyByDate[closingDate].closing) || 0);
      totalValue = Number((dailyByDate[closingDate] && dailyByDate[closingDate].total_value) || 0);
    }

    dates.forEach(function(dateKey){
      var day = dailyByDate[dateKey] || {};
      inTotal += Number(day.in || 0);
      outTotal += Number(day.out || 0);
      adjTotal += Number(day.adjustment || 0);
      mutationTotal += Number(day.mutations || 0);
    });

    var packSize = Number(contentPerBuy || 0);
    var openingPack = !useFirstDateOpening && monthlyOpeningPack !== undefined && monthlyOpeningPack !== null
      ? Number(monthlyOpeningPack || 0)
      : (packSize > 0 ? openingContent / packSize : 0);
    var closingPack = packSize > 0 ? closingContent / packSize : 0;
    var unitPrice = closingContent !== 0 ? totalValue / closingContent : 0;
    var unitPricePack = unitPrice * (packSize > 0 ? packSize : 0);

    return {
      in_total: inTotal,
      out_total: outTotal,
      adjustment_total: adjTotal,
      opening_content: openingContent,
      closing_content: closingContent,
      opening_pack: openingPack,
      closing_pack: closingPack,
      total_value: totalValue,
      hpp: unitPrice,
      unit_price: unitPrice,
      unit_price_pack: unitPricePack,
      mutation_total: mutationTotal
    };
  }

  function buildGroups(rows, dates){
    var map = {};
    var order = [];

    (rows || []).forEach(function(row){
      var materialId = Number(row.material_id || 0);
      var itemId = Number(row.item_id || 0);
      var objectKey = materialId > 0 ? ('M-' + materialId) : ('I-' + itemId);
      if (objectKey === 'M-0' || objectKey === 'I-0') {
        objectKey += '|' + String(row.material_code || row.item_code || '').toUpperCase();
      }
      var destinationGroup = String(row.destination_group || 'REGULER').toUpperCase();
      var key = [
        Number(row.division_id || 0),
        destinationGroup,
        objectKey
      ].join('|');

      if (!map[key]) {
        map[key] = {
          key: key,
          division_id: Number(row.division_id || 0),
          division_code: String(row.division_code || ''),
          division_name: String(row.division_name || ''),
          destination_type: String(row.destination_type || ''),
          destination_group: destinationGroup,
          destination_name: destinationGroup === 'EVENT' ? 'Event' : 'Reguler',
          item_id: itemId,
          material_id: materialId,
          item_code: String(row.item_code || ''),
          item_name: String(row.item_name || ''),
          material_code: String(row.material_code || ''),
          material_name: String(row.material_name || ''),
          category_name: String(row.category_name || ''),
          children: [],
          daily: {},
          metrics: null
        };
        order.push(key);
      } else {
        if (String(map[key].destination_type || '') !== String(row.destination_type || '')) {
          map[key].destination_type = '';
        }
      }

      var profileDaily = normalizeProfileDaily(row.daily || {}, dates, Number(row.profile_content_per_buy || 0));
      var profileMetrics = calcMetrics(
        profileDaily,
        Number(row.profile_content_per_buy || 0),
        dates,
        row.monthly_opening_qty_content,
        row.monthly_opening_qty_buy
      );

      map[key].children.push({
        division_id: Number(row.division_id || 0),
        destination_type: String(row.destination_type || 'OTHER'),
        stock_domain: 'MATERIAL',
        item_id: Number(row.item_id || 0),
        material_id: Number(row.material_id || 0),
        category_name: String(row.category_name || ''),
        buy_uom_id: Number(row.buy_uom_id || 0),
        content_uom_id: Number(row.content_uom_id || 0),
        profile_key: String(row.profile_key || ''),
        profile_name: String(row.profile_name || ''),
        profile_brand: String(row.profile_brand || ''),
        profile_description: String(row.profile_description || ''),
        profile_expired_date: String(row.profile_expired_date || ''),
        profile_content_per_buy: Number(row.profile_content_per_buy || 0),
        profile_buy_uom_code: String(row.profile_buy_uom_code || ''),
        profile_content_uom_code: String(row.profile_content_uom_code || ''),
        avg_cost_per_content: Number(row.avg_cost_per_content || 0),
        monthly_opening_qty_buy: Number(row.monthly_opening_qty_buy || 0),
        monthly_opening_qty_content: Number(row.monthly_opening_qty_content || 0),
        monthly_closing_qty_buy: Number(row.monthly_closing_qty_buy || 0),
        monthly_closing_qty_content: Number(row.monthly_closing_qty_content || 0),
        profile_standard_price: Number(row.profile_standard_price || 0),
        profile_last_unit_price: Number(row.profile_last_unit_price || 0),
        audit_has_mismatch: Number(row.audit_has_mismatch || 0),
        audit_mismatch_row_count: Number(row.audit_mismatch_row_count || 0),
        audit_mismatch_qty_content: Number(row.audit_mismatch_qty_content || 0),
        audit_mismatch_notes: String(row.audit_mismatch_notes || ''),
        log_has_gap: Number(row.log_has_gap || 0),
        log_gap_content: Number(row.log_gap_content || 0),
        daily: profileDaily,
        metrics: profileMetrics
      });
    });

    order.forEach(function(key){
      var group = map[key];
      var summaryDaily = {};
      dates.forEach(function(dateKey){
        summaryDaily[dateKey] = {
          opening: 0,
          in: 0,
          out: 0,
          adjustment: 0,
          closing: 0,
          opening_pack: 0,
          in_pack: 0,
          out_pack: 0,
          adjustment_pack: 0,
          closing_pack: 0,
          mutations: 0,
          total_value: 0
        };
      });

      var agg = {
        in_total: 0,
        out_total: 0,
        adjustment_total: 0,
        opening_content: 0,
        closing_content: 0,
        opening_pack: 0,
        closing_pack: 0,
        total_value: 0,
        mutation_total: 0,
        hpp: 0,
        last_unit_price: 0,
        standard_price: 0,
        audit_has_mismatch: 0,
        audit_mismatch_row_count: 0,
        audit_mismatch_qty_content: 0,
        log_has_gap: 0,
        log_gap_content: 0
      };
      var refPriceTotal = 0;
      var refPriceCount = 0;
      var standardPriceTotal = 0;
      var standardPriceCount = 0;

      group.children.forEach(function(child){
        dates.forEach(function(dateKey){
          var d = child.daily[dateKey] || {
            opening: 0,
            in: 0,
            out: 0,
            adjustment: 0,
            closing: 0,
            opening_pack: 0,
            in_pack: 0,
            out_pack: 0,
            adjustment_pack: 0,
            closing_pack: 0,
            mutations: 0,
            total_value: 0
          };
          summaryDaily[dateKey].opening += Number(d.opening || 0);
          summaryDaily[dateKey].in += Number(d.in || 0);
          summaryDaily[dateKey].out += Number(d.out || 0);
          summaryDaily[dateKey].adjustment += Number(d.adjustment || 0);
          summaryDaily[dateKey].closing += Number(d.closing || 0);
          summaryDaily[dateKey].opening_pack += Number(d.opening_pack || 0);
          summaryDaily[dateKey].in_pack += Number(d.in_pack || 0);
          summaryDaily[dateKey].out_pack += Number(d.out_pack || 0);
          summaryDaily[dateKey].adjustment_pack += Number(d.adjustment_pack || 0);
          summaryDaily[dateKey].closing_pack += Number(d.closing_pack || 0);
          summaryDaily[dateKey].mutations += Number(d.mutations || 0);
          summaryDaily[dateKey].total_value += Number(d.total_value || 0);
        });

        agg.in_total += Number(child.metrics.in_total || 0);
        agg.out_total += Number(child.metrics.out_total || 0);
        agg.adjustment_total += Number(child.metrics.adjustment_total || 0);
        agg.opening_content += Number(child.metrics.opening_content || 0);
        agg.closing_content += Number(child.metrics.closing_content || 0);
        agg.opening_pack += Number(child.metrics.opening_pack || 0);
        agg.closing_pack += Number(child.metrics.closing_pack || 0);
        agg.total_value += Number(child.metrics.total_value || 0);
        agg.mutation_total += Number(child.metrics.mutation_total || 0);
        if (Number(child.profile_last_unit_price || 0) > 0) {
          refPriceTotal += Number(child.profile_last_unit_price || 0);
          refPriceCount += 1;
        }
        if (Number(child.profile_standard_price || 0) > 0) {
          standardPriceTotal += Number(child.profile_standard_price || 0);
          standardPriceCount += 1;
        }
        if (Number(child.audit_has_mismatch || 0) > 0) {
          agg.audit_has_mismatch = 1;
          agg.audit_mismatch_row_count += Number(child.audit_mismatch_row_count || 0);
          agg.audit_mismatch_qty_content += Number(child.audit_mismatch_qty_content || 0);
        }
        if (Number(child.log_has_gap || 0) > 0) {
          agg.log_has_gap = 1;
          agg.log_gap_content += Number(child.log_gap_content || 0);
        }
      });

      // Profiles with different content_per_buy must not have their per-profile packs simply summed —
      // the sums are incoherent (e.g. profile A closing=-818 pack=-0.5 and profile B closing=+818
      // pack≈0 gives total content=0 but total pack=-0.5). Recompute using the group-level effective
      // pack rate derived from opening balances, then apply the same rate to every date cell.
      var _grpRate = (agg.opening_pack > 0.0001 && agg.opening_content > 0.0001)
        ? agg.opening_pack / agg.opening_content
        : 0;
      agg.closing_pack = _grpRate > 0 ? agg.closing_content * _grpRate : 0;
      if (_grpRate > 0) {
        dates.forEach(function(dk) {
          if (summaryDaily[dk]) {
            summaryDaily[dk].closing_pack = summaryDaily[dk].closing * _grpRate;
          }
        });
      } else {
        dates.forEach(function(dk) {
          if (summaryDaily[dk] && Math.abs(summaryDaily[dk].closing) < 0.0001) {
            summaryDaily[dk].closing_pack = 0;
          }
        });
      }

      group.children.sort(function(a, b){
        var aNonPositive = Number((a.metrics && a.metrics.closing_content) || 0) <= 0.0001;
        var bNonPositive = Number((b.metrics && b.metrics.closing_content) || 0) <= 0.0001;
        if (aNonPositive !== bNonPositive) {
          return aNonPositive ? 1 : -1;
        }
        var cmp = String(a.profile_name || '').localeCompare(String(b.profile_name || ''), 'id', { sensitivity: 'base' });
        if (cmp !== 0) { return cmp; }
        cmp = String(a.profile_brand || '').localeCompare(String(b.profile_brand || ''), 'id', { sensitivity: 'base' });
        if (cmp !== 0) { return cmp; }
        return String(a.profile_description || '').localeCompare(String(b.profile_description || ''), 'id', { sensitivity: 'base' });
      });

      group.children.forEach(function(child, index){
        child.__source_index = index;
      });

      var positiveChildren = group.children.filter(function(child){
        return Number((child.metrics && child.metrics.closing_content) || 0) > 0.0001;
      });
      group.display_children = positiveChildren.length > 0 ? positiveChildren : group.children.slice();
      if (!group.display_children.length && group.children.length) {
        group.display_children = [group.children[0]];
      }
      group.display_alert_metrics = buildDisplayAlertMetrics(group.display_children);

      if (agg.closing_content !== 0) {
        agg.hpp = agg.total_value / agg.closing_content;
      } else if (group.children.length > 0) {
        var sumHpp = 0;
        group.children.forEach(function(child){ sumHpp += Number(child.metrics.hpp || 0); });
        agg.hpp = sumHpp / group.children.length;
      }

      agg.last_unit_price = refPriceCount > 0 ? (refPriceTotal / refPriceCount) : 0;
      agg.standard_price = standardPriceCount > 0 ? (standardPriceTotal / standardPriceCount) : 0;

      group.daily = summaryDaily;
      group.metrics = agg;
    });

    return order.map(function(key){ return map[key]; }).sort(function(left, right){
      var leftRank = String(left.destination_group || '').toUpperCase() === 'EVENT' ? 1 : 0;
      var rightRank = String(right.destination_group || '').toUpperCase() === 'EVENT' ? 1 : 0;
      if (leftRank !== rightRank) return leftRank - rightRank;
      var categoryCompare = String(left.category_name || '').localeCompare(String(right.category_name || ''), 'id', { sensitivity: 'base' });
      if (categoryCompare !== 0) return categoryCompare;
      return String(left.material_name || left.item_name || '').localeCompare(String(right.material_name || right.item_name || ''), 'id', { sensitivity: 'base' });
    });
  }

  function isNonPositiveMetrics(metrics){
    return Number((metrics && metrics.closing_content) || 0) <= 0.0001;
  }

  function buildDisplayAlertMetrics(children){
    var metrics = {
      closing_content: 0,
      log_has_gap: 0,
      log_gap_content: 0,
      audit_has_mismatch: 0,
      audit_mismatch_row_count: 0,
      audit_mismatch_qty_content: 0
    };
    (children || []).forEach(function(child){
      metrics.closing_content += Number((child.metrics && child.metrics.closing_content) || 0);
      if (Number(child.log_has_gap || 0) > 0) {
        metrics.log_has_gap = 1;
        metrics.log_gap_content += Number(child.log_gap_content || 0);
      }
      if (Number(child.audit_has_mismatch || 0) > 0) {
        metrics.audit_has_mismatch = 1;
        metrics.audit_mismatch_row_count += Number(child.audit_mismatch_row_count || 0);
        metrics.audit_mismatch_qty_content += Number(child.audit_mismatch_qty_content || 0);
      }
    });
    return metrics;
  }

  function getDisplayChildren(group){
    if (matrixEmbedded) {
      return (group && Array.isArray(group.children)) ? group.children : [];
    }
    if (group && Array.isArray(group.display_children)) {
      return group.display_children;
    }
    return (group && Array.isArray(group.children)) ? group.children : [];
  }

  function getDisplayAlertMetrics(group){
    return (group && group.display_alert_metrics) ? group.display_alert_metrics : ((group && group.metrics) || {});
  }

  function rowAlertClass(metrics, auditHasMismatch){
    return (isNonPositiveMetrics(metrics) || Number(auditHasMismatch || 0) > 0) ? ' pmd-stock-alert' : '';
  }

  function calcStats(groups, serverSummary){
    var profileCount = 0;
    var divisionSet = {};
    var materialSet = {};
    var totalValue = 0;
    var alertCount = 0;
    var totalIn = 0;
    var totalOut = 0;
    var totalAdj = 0;

    (groups || []).forEach(function(group){
      profileCount += getDisplayChildren(group).length;
      if (group.division_id) { divisionSet[String(group.division_id)] = true; }
      if (group.material_id) { materialSet[String(group.material_id)] = true; }
      var m = group.metrics || {};
      totalValue += Number(m.total_value || 0);
      totalIn    += Number(m.in_total    || 0);
      totalOut   += Number(m.out_total   || 0);
      totalAdj   += Number(m.adjustment_total || 0);
      if (Number(m.closing_content || 0) <= 0.0001) { alertCount++; }
    });

    // Server computes totals across ALL rows (not just loaded page) — use when available
    if (serverSummary) {
      if (Number(serverSummary.total_value || 0) > 0)              { totalValue = Number(serverSummary.total_value); }
      if (Number(serverSummary.in_qty_content  || 0) !== 0)        { totalIn  = Number(serverSummary.in_qty_content); }
      if (Number(serverSummary.out_qty_content || 0) !== 0)        { totalOut = Number(serverSummary.out_qty_content); }
      if (Number(serverSummary.adjustment_qty_content || 0) !== 0) { totalAdj = Number(serverSummary.adjustment_qty_content); }
    }

    document.getElementById('pmdStatProfiles').textContent  = profileCount.toLocaleString('id-ID');
    document.getElementById('pmdStatDivisions').textContent = Object.keys(divisionSet).length.toLocaleString('id-ID');
    document.getElementById('pmdStatMaterials').textContent = Object.keys(materialSet).length.toLocaleString('id-ID');
    document.getElementById('pmdStatValue').textContent     = money(totalValue);
    document.getElementById('pmdStatAlert').textContent     = alertCount.toLocaleString('id-ID');
    document.getElementById('pmdStatAlertCard').classList.toggle('is-danger', alertCount > 0);
    document.getElementById('pmdStatIn').textContent        = num(totalIn);
    document.getElementById('pmdStatOut').textContent       = num(totalOut);
    document.getElementById('pmdStatAdj').textContent       = (totalAdj >= 0 ? '+' : '') + num(totalAdj);
  }

  function renderPagination(){
    var wrap = document.getElementById('pmdPaginationWrap');
    if (!wrap) { return; }
    var total = state.totalCount || 0;
    var limit = state.limit || 120;
    var offset = state.offset || 0;
    if (total <= limit && offset === 0) {
      wrap.style.display = 'none';
      wrap.innerHTML = '';
      return;
    }
    wrap.style.display = '';
    var totalPages = Math.ceil(total / limit);
    var currentPage = Math.floor(offset / limit);
    var from = offset + 1;
    var to = Math.min(offset + limit, total);

    var pageButtons = '';
    var maxButtons = 7;
    var startPage = Math.max(0, currentPage - Math.floor(maxButtons / 2));
    var endPage = Math.min(totalPages - 1, startPage + maxButtons - 1);
    if (endPage - startPage + 1 < maxButtons) {
      startPage = Math.max(0, endPage - maxButtons + 1);
    }
    for (var p = startPage; p <= endPage; p++) {
      pageButtons += '<button type="button" class="pmd-page-btn' + (p === currentPage ? ' is-active' : '') + '" data-page="' + p + '">' + (p + 1) + '</button>';
    }

    wrap.innerHTML = '<div class="pmd-pagination">'
      + '<span class="pmd-pagination-info">Baris <strong>' + from + '–' + to + '</strong> dari <strong>' + total.toLocaleString('id-ID') + '</strong></span>'
      + '<button type="button" class="pmd-page-btn" id="pmdPrevPage"' + (offset === 0 ? ' disabled' : '') + '>&#8592; Prev</button>'
      + pageButtons
      + '<button type="button" class="pmd-page-btn" id="pmdNextPage"' + (to >= total ? ' disabled' : '') + '>Next &#8594;</button>'
      + '</div>';

    wrap.querySelectorAll('[data-page]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var page = parseInt(this.getAttribute('data-page') || '0', 10);
        state.offset = page * limit;
        loadData();
      });
    });
    var prevBtn = document.getElementById('pmdPrevPage');
    if (prevBtn) {
      prevBtn.addEventListener('click', function(){
        state.offset = Math.max(0, offset - limit);
        loadData();
      });
    }
    var nextBtn = document.getElementById('pmdNextPage');
    if (nextBtn) {
      nextBtn.addEventListener('click', function(){
        if (to < total) {
          state.offset = offset + limit;
          loadData();
        }
      });
    }
  }

  function summaryCardHtml(metrics){
    return '' +
      '<div class="pmd-summary-card">' +
        '<div class="pmd-summary-head">' +
          '<span class="pmd-summary-title">Nilai Sisa</span>' +
          '<div class="pmd-summary-amount">' + esc(money(metrics.total_value || 0)) + '</div>' +
        '</div>' +
          '<div class="pmd-summary-grid">' +
          '<div class="pmd-summary-metric"><span class="label">Awal Isi</span><strong>' + esc(num(metrics.opening_content || 0)) + '</strong></div>' +
          '<div class="pmd-summary-metric"><span class="label">Akhir Isi</span><strong>' + esc(num(metrics.closing_content || 0)) + '</strong></div>' +
          '<div class="pmd-summary-metric"><span class="label">Awal Pack</span><strong>' + esc(num(metrics.opening_pack || 0)) + '</strong></div>' +
          '<div class="pmd-summary-metric"><span class="label">Akhir Pack</span><strong>' + esc(num(metrics.closing_pack || 0)) + '</strong></div>' +
        '</div>' +
      '</div>';
  }

  function summaryParentHtml(metrics){
    return summaryCardHtml(metrics);
  }

  function summaryChildHtml(metrics){
    return summaryCardHtml(metrics);
  }

  function freezeHeaderHtml(){
    if (matrixEmbedded) {
      return '<tr>' + ['Kategori / Tujuan','Material / Merk / Satuan','Harga / HPP','Nilai Awal / Nilai Sisa'].map(function(label){
        return '<th class="pmd-flat-meta-cell">' + label + '<span class="pmd-col-resizer" aria-hidden="true"></span></th>';
      }).join('') + '</tr>';
    }
    return '<tr>' + (matrixEmbedded ? '' : '<th class="pmd-freeze-col-1">Divisi / Tujuan</th>')
      + '<th class="pmd-freeze-col-2"' + (matrixEmbedded ? ' rowspan="2"' : '') + '>Material / Profil</th><th class="pmd-freeze-col-3"' + (matrixEmbedded ? ' rowspan="2"' : '') + '>Ringkasan</th></tr>';
  }

  function headerHtml(){
    if (matrixEmbedded) {
      var metrics = [
        { key: 'opening', label: 'Awal' }, { key: 'in', label: 'In' },
        { key: 'out', label: 'Out' }, { key: 'adjustment', label: 'Adj' },
        { key: 'closing', label: 'Akhir' }
      ];
      return '<tr>' + state.dates.map(function(dateText, dateIndex){
        var cls = 'pmd-day-head' + (isToday(dateText) ? ' is-today' : '');
        var bandClass = dateIndex % 2 === 0 ? 'pmd-date-band-a' : 'pmd-date-band-b';
        return '<th colspan="5" class="' + cls + ' ' + bandClass + ' pmd-day-group">'
          + '<div class="pmd-headcard"><span class="day">' + esc(String(dateText).slice(-2)) + '</span>'
          + '<span class="meta">' + esc(weekdayName(dateText)) + ' ' + esc(dateText.slice(5)) + '</span>'
          + (isToday(dateText) ? '<span class="today-tag">Hari ini</span>' : '') + '</div></th>';
      }).join('') + '</tr><tr class="pmd-metric-subhead">' + state.dates.map(function(dateText, dateIndex){
        var bandClass = dateIndex % 2 === 0 ? 'pmd-date-band-a' : 'pmd-date-band-b';
        return metrics.map(function(metric, metricIndex){
          return '<th class="pmd-submetric ' + bandClass + (isToday(dateText) ? ' is-today' : '') + (metricIndex === 0 ? ' pmd-day-start' : '') + (metricIndex === 4 ? ' pmd-day-end' : '') + '">' + metric.label + '</th>';
        }).join('');
      }).join('') + '</tr>';
    }
    return '<tr>' + state.dates.map(function(dateText, dateIndex){
      var cls = 'pmd-day-head' + (isToday(dateText) ? ' is-today' : '');
      var bandClass = dateIndex % 2 === 0 ? 'pmd-date-band-a' : 'pmd-date-band-b';
      return '<th class="' + cls + ' ' + bandClass + ' pmd-metric-cell">'
        + '<div class="pmd-headcard">'
        +   '<div class="day">' + esc(String(dateText).slice(-2)) + '</div>'
        +   '<div class="meta">' + esc(weekdayName(dateText)) + ' ' + esc(dateText.slice(5)) + '</div>'
        +   (isToday(dateText) ? '<span class="today-tag">Hari ini</span>' : '')
        + '</div>'
        + '</th>';
    }).join('') + '</tr>';
  }

  function dayCells(dailyMap, groupIndex, profileIndex, contentPerBuy){
    var html = '';
    var packSize = Number(contentPerBuy || 0);
    state.dates.forEach(function(dateText, dateIndex){
      var day = dailyMap[dateText] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0 };
      var todayCls = isToday(dateText) ? ' is-today' : '';
      var bandClass = dateIndex % 2 === 0 ? ' pmd-date-band-a' : ' pmd-date-band-b';
      var items = [
        { key: 'opening', cls: 'pmd-metric-open', label: 'Awal' },
        { key: 'in', cls: 'pmd-metric-in', label: 'In' },
        { key: 'out', cls: 'pmd-metric-out', label: 'Out' },
        { key: 'adjustment', cls: 'pmd-metric-adj', label: 'Adj' },
        { key: 'closing', cls: 'pmd-metric-close', label: 'Akhir' }
      ];
      var rowsHtml = items.map(function(item){
        var valueContent = Number(day[item.key] || 0);
        var packField = item.key + '_pack';
        var valuePack = Object.prototype.hasOwnProperty.call(day, packField)
          ? Number(day[packField] || 0)
          : (packSize > 0 ? (valueContent / packSize) : 0);
        var valueText = '<span class="pmd-cell-pack">' + esc(num(valueContent)) + '</span><span class="pmd-cell-content">' + esc(num(valuePack)) + '</span>';
        var contentHtml = '';
        if (item.key === 'adjustment') {
          var detailHtml = Math.abs(valueContent) > 0.000001
            ? '<button type="button" class="pmd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>'
            : '<span class="pmd-cell-btn is-static ' + item.cls + '">' + valueText + '</span>';
          contentHtml = '<div class="pmd-cell-action-wrap">'
            + detailHtml
            + (isFutureDate(dateText)
              ? '<span class="pmd-cell-adjust-trigger is-disabled" title="Tanggal masa depan belum dapat disesuaikan" aria-label="Tanggal masa depan belum dapat disesuaikan"><i class="ri ri-lock-line" aria-hidden="true"></i></span>'
              : '<button type="button" class="pmd-cell-adjust-trigger" data-action="adjust" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '" title="Adjustment langsung" aria-label="Adjustment langsung"><i class="ri ri-edit-line" aria-hidden="true"></i></button>')
            + '</div>';
        } else if (Math.abs(valueContent) > 0.000001) {
          contentHtml = '<button type="button" class="pmd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>';
        } else {
          contentHtml = '<span class="pmd-cell-btn is-static ' + item.cls + '">' + valueText + '</span>';
        }
        return '<div class="pmd-metric-row ' + item.cls + '">'
          + '<div class="pmd-metric-main"><span class="pmd-metric-label">' + item.label + '</span></div>'
          + '<div class="pmd-metric-value-wrap">' + contentHtml + '</div>'
          + '</div>';
      }).join('');
      if (matrixEmbedded) {
        html += items.map(function(item, metricIndex){
          var valueContent = Number(day[item.key] || 0);
          var packField = item.key + '_pack';
          var valuePack = Object.prototype.hasOwnProperty.call(day, packField)
            ? Number(day[packField] || 0)
            : (packSize > 0 ? valueContent / packSize : 0);
          var valueText = '<span class="pmd-cell-pack">' + esc(num(valueContent)) + '</span><small class="pmd-cell-content">' + esc(num(valuePack)) + '</small>';
          var cellContent;
          if (item.key === 'adjustment') {
            var detail = '<button type="button" class="pmd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>';
            cellContent = '<div class="pmd-cell-action-wrap">' + detail + (isFutureDate(dateText)
              ? '<span class="pmd-cell-adjust-trigger is-disabled" title="Tanggal masa depan belum dapat disesuaikan"><i class="ri ri-lock-line"></i></span>'
              : '<button type="button" class="pmd-cell-adjust-trigger" data-action="adjust" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '" title="Adjustment langsung" aria-label="Adjustment langsung"><i class="ri ri-edit-line"></i></button>') + '</div>';
          } else {
            cellContent = '<button type="button" class="pmd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '" aria-label="Lihat rincian ' + item.label + '">' + valueText + '</button>';
          }
          return '<td class="pmd-metric-cell pmd-flat-metric ' + item.cls + todayCls + bandClass + (metricIndex === 0 ? ' pmd-day-start' : '') + (metricIndex === 4 ? ' pmd-day-end' : '') + '">' + cellContent + '</td>';
        }).join('');
      } else {
        html += '<td class="pmd-metric-cell' + todayCls + bandClass + ' pmd-day-start pmd-day-end"><div class="pmd-date-card">' + rowsHtml + '</div></td>';
      }
    });
    return html;
  }

  function isExpandable(group){
    return getDisplayChildren(group).length > 1;
  }

  function isExpanded(group){
    return isExpandable(group) ? !!state.expanded[group.key] : true;
  }

  function buildDivisionLotUrl(row){
    var params = new URLSearchParams();
    var searchToken = String(row.profile_key || row.material_code || row.item_code || row.material_name || row.item_name || '').trim();
    if (searchToken) {
      params.set('q', searchToken);
    }
    if (String(row.profile_key || '').trim() !== '') {
      params.set('profile_key', String(row.profile_key || '').trim());
    }
    if (Number(row.division_id || 0) > 0) {
      params.set('division_id', String(Number(row.division_id || 0)));
    }
    if (String(row.destination_type || row.destination_group || '').trim() !== '') {
      params.set('destination', String(row.destination_type || row.destination_group || '').trim());
    }
    if (Number(row.item_id || 0) > 0) {
      params.set('item_id', String(Number(row.item_id || 0)));
    }
    if (Number(row.material_id || 0) > 0) {
      params.set('material_id', String(Number(row.material_id || 0)));
    }
    var query = params.toString();
    return <?php echo json_encode($lotAuditBaseUrl); ?> + (query ? ('?' + query) : '');
  }

  function averageProfileContent(group){
    var rows = getDisplayChildren(group);
    var values = rows.map(function(child){
      return Number(child.profile_content_per_buy || 0);
    }).filter(function(value){
      return value > 0;
    });
    if (!values.length) { return 0; }
    var total = values.reduce(function(sum, value){ return sum + value; }, 0);
    return total / values.length;
  }

  function toContentUnitCost(unitPricePerPack, contentPerBuy){
    var unitPrice = Number(unitPricePerPack || 0);
    var factor = Number(contentPerBuy || 0);
    if (!(unitPrice > 0)) { return 0; }
    if (!(factor > 0)) { factor = 1; }
    return unitPrice / factor;
  }

  function resolveReferenceUnitPrice(row, group){
    var rowContentPerBuy = Number(row.profile_content_per_buy || averageProfileContent(group) || 0);
    var groupContentPerBuy = Number(averageProfileContent(group) || row.profile_content_per_buy || 0);
    if (Number((row.metrics && row.metrics.hpp) || 0) > 0) {
      return Number((row.metrics && row.metrics.hpp) || 0);
    }
    if (Number(row.profile_last_unit_price || 0) > 0) {
      return toContentUnitCost(row.profile_last_unit_price || 0, rowContentPerBuy);
    }
    if (Number(row.profile_standard_price || 0) > 0) {
      return toContentUnitCost(row.profile_standard_price || 0, rowContentPerBuy);
    }
    if (Number((row.metrics && row.metrics.last_unit_price) || 0) > 0) {
      return toContentUnitCost((row.metrics && row.metrics.last_unit_price) || 0, rowContentPerBuy);
    }
    if (Number((row.metrics && row.metrics.standard_price) || 0) > 0) {
      return toContentUnitCost((row.metrics && row.metrics.standard_price) || 0, rowContentPerBuy);
    }
    if (group && Number((group.metrics && group.metrics.last_unit_price) || 0) > 0) {
      return toContentUnitCost((group.metrics && group.metrics.last_unit_price) || 0, groupContentPerBuy);
    }
    if (group && Number((group.metrics && group.metrics.standard_price) || 0) > 0) {
      return toContentUnitCost((group.metrics && group.metrics.standard_price) || 0, groupContentPerBuy);
    }
    if (group && Number((group.metrics && group.metrics.hpp) || 0) > 0) {
      return Number((group.metrics && group.metrics.hpp) || 0);
    }
    return 0;
  }

  function materialCellHtml(group, profile, expandable, expanded, variant){
    var source = profile || group || {};
    var displayChildren = getDisplayChildren(group);
    var firstChild = displayChildren.length ? displayChildren[0] : null;
    var materialName = group.material_name || group.item_name || '-';
    var materialCode = group.material_code || group.item_code || '-';
    var chips = [];
    var priceChips = [];
    var actions = [];
    var displayMetrics = !profile ? getDisplayAlertMetrics(group) : (source.metrics || {});
    var packContent = Number(source.profile_content_per_buy || averageProfileContent(group) || 0);
    var contentUom = source.profile_content_uom_code || (firstChild && firstChild.profile_content_uom_code) || '';
    var buyUom = source.profile_buy_uom_code || (firstChild && firstChild.profile_buy_uom_code) || '-';
    var refUnitPrice = resolveReferenceUnitPrice(source);
    var hppValue = Number((source.metrics && source.metrics.hpp) || 0);
    var isParent = !profile;
    var isChildRow = variant === 'child';
    var stackClass = 'pmd-material-stack' + (isChildRow ? ' is-child' : ' is-parent');
    var chipClass = 'pmd-material-chip' + (isChildRow ? ' is-child' : ' is-parent');

    if (profile && source.profile_brand) {
      chips.push('<span class="' + chipClass + '">' + esc(source.profile_brand) + '</span>');
    }
    if (profile) {
      chips.push('<span class="' + chipClass + '">' + esc(source.profile_expired_date ? ('Exp ' + source.profile_expired_date) : 'Tanpa expiry') + '</span>');
    }
    if (profile && source.profile_name) {
      chips.push('<span class="' + chipClass + '">' + esc(source.profile_name) + '</span>');
    }
    if (!profile && expandable) {
      chips.push('<span class="' + chipClass + '">' + esc(String(displayChildren.length)) + ' profil</span>');
    }
    var logHasGap = profile ? Number(source.log_has_gap || 0) > 0 : Number(displayMetrics.log_has_gap || 0) > 0;
    var logGapContent = profile ? Number(source.log_gap_content || 0) : Number(displayMetrics.log_gap_content || 0);
    if (logHasGap) {
      var gapSign = logGapContent >= 0 ? '+' : '';
      chips.push('<span class="pmd-alert-chip" title="Movement log tidak lengkap. Selisih: ' + esc(gapSign + num(logGapContent)) + '">⚠ Log Gap ' + esc(gapSign + num(logGapContent)) + '</span>');
    } else if (profile ? Number(source.audit_has_mismatch || 0) > 0 : Number(displayMetrics.audit_has_mismatch || 0) > 0) {
      chips.push('<span class="pmd-alert-chip">Mismatch Log ' + esc(num(profile ? (source.audit_mismatch_qty_content || 0) : (displayMetrics.audit_mismatch_qty_content || 0))) + '</span>');
    } else if (isNonPositiveMetrics(profile ? (source.metrics || {}) : displayMetrics)) {
      chips.push('<span class="pmd-alert-chip">Stok Habis / Minus</span>');
    }
    if (packContent > 0) {
      chips.push('<span class="' + chipClass + '">' + esc(num(packContent)) + ' ' + esc(contentUom) + ' / ' + esc(buyUom) + '</span>');
    }
    if (refUnitPrice > 0) {
      priceChips.push('<span class="' + chipClass + '">' + esc(isParent && expandable ? 'Harga satuan rata-rata ' : 'Harga satuan ') + esc(money(refUnitPrice)) + '</span>');
    }
    if (hppValue > 0) {
      priceChips.push('<span class="' + chipClass + '">' + esc(isParent && expandable ? 'HPP rata-rata ' : 'HPP ') + esc(money(hppValue)) + '</span>');
    }

    actions.push('<a class="pmd-material-link" href="' + esc(buildDivisionLotUrl(source)) + '">Lihat Lot</a>');
    if (!profile && expandable) {
      actions.push('<button type="button" class="pmd-material-expand" data-action="toggle-group" data-group-key="' + esc(group.key) + '">' + (expanded ? 'Sembunyikan Profil' : 'Expand Profil') + '</button>');
    }

    return ''
      + '<div class="' + stackClass + '">'
      +   '<div class="pmd-name">' + esc(materialName) + '</div>'
      +   '<div class="pmd-code">' + esc(materialCode) + '</div>'
      +   (matrixEmbedded ? '<span class="pmd-destination-chip">' + esc(destinationLabel(group)) + '</span>' : '')
      +   (chips.length ? '<div class="pmd-material-meta">' + chips.join('') + '</div>' : '')
      +   (priceChips.length ? '<div class="pmd-material-meta">' + priceChips.join('') + '</div>' : '')
      +   '<div class="pmd-material-actions">' + actions.join('') + '</div>'
      + '</div>';
  }

  function detailCellHtml(group, profile, summaryHtml){
    var isParent = !profile;
    var detailParts = [];
    var displayMetrics = isParent ? getDisplayAlertMetrics(group) : (profile || {});
    if (isParent && isExpandable(group)) {
      if (Number((group.metrics && group.metrics.last_unit_price) || 0) > 0) {
        detailParts.push('<span class="pmd-detail-chip">Harga satuan rata-rata ' + esc(money((group.metrics && group.metrics.last_unit_price) || 0)) + '</span>');
      }
    }
    var detailLogHasGap = profile ? Number(profile.log_has_gap || 0) > 0 : Number(displayMetrics.log_has_gap || 0) > 0;
    var detailLogGap = profile ? Number(profile.log_gap_content || 0) : Number(displayMetrics.log_gap_content || 0);
    if (detailLogHasGap) {
      var detailGapSign = detailLogGap >= 0 ? '+' : '';
      detailParts.push('<span class="pmd-alert-chip">⚠ Gap movement log: selisih ' + esc(detailGapSign + num(detailLogGap)) + ' — ada pergerakan yang tidak tercatat di movement log (koreksi phantom, edit manual, dll). Opening & closing tetap dari monthly_stock.</span>');
    }
    if ((profile && Number(profile.audit_has_mismatch || 0) > 0) || (!profile && Number(displayMetrics.audit_has_mismatch || 0) > 0)) {
      var mismatchSource = profile || displayMetrics || {};
      var mismatchNotes = profile ? String(profile.audit_mismatch_notes || '') : '';
      detailParts.push('<span class="pmd-alert-chip">Log mismatch ' + esc(num(mismatchSource.audit_mismatch_qty_content || 0)) + '</span>');
      if (mismatchNotes) {
        detailParts.push('<span class="pmd-detail-chip">' + esc(mismatchNotes) + '</span>');
      }
    }

    return ''
      + '<div class="pmd-detail-card pmd-summary-only">'
      +   (detailParts.length ? '<div class="pmd-detail-metas">' + detailParts.join('') + '</div>' : '')
      +   summaryHtml
      + '</div>';
  }

  function freezeGroupRowHtml(group){
    var expandable = isExpandable(group);
    var expanded = isExpanded(group);
    var displayChildren = getDisplayChildren(group);
    var singleProfile = (!expandable && displayChildren.length === 1) ? displayChildren[0] : null;
    var divisionText = divisionLabel(group);
    var destinationText = destinationLabel(group);
    var toggleHtml = expandable
      ? '<button type="button" class="pmd-toggle-arrow" title="Expand/Collapse" data-action="toggle-group" data-group-key="' + esc(group.key) + '">' + (expanded ? '&#9662;' : '&#9656;') + '</button>'
      : '<span class="pmd-toggle-static" title="Baris tunggal">&bull;</span>';
    var rowClass = expandable ? 'pmd-group-row pmd-group-expandable' : 'pmd-group-row pmd-group-single';
    rowClass += rowAlertClass(singleProfile ? singleProfile.metrics : getDisplayAlertMetrics(group), singleProfile ? singleProfile.audit_has_mismatch : (getDisplayAlertMetrics(group).audit_has_mismatch || 0));
    if (matrixEmbedded) return '<tr class="' + rowClass + '">' + flatMaterialMetaCells(group, singleProfile) + '</tr>';

    return '' +
      '<tr class="' + rowClass + '">' + (matrixEmbedded ? '' :
        '<td class="pmd-freeze-col-1"><div class="pmd-division-cell">' + toggleHtml + '<div class="pmd-division-stack"><span class="pmd-division-pill">' + esc(divisionText) + '</span><span class="pmd-destination-chip">' + esc(destinationText) + '</span></div></div></td>') +
        '<td class="pmd-freeze-col-2">' + materialCellHtml(group, singleProfile, expandable, expanded, 'parent') + '</td>' +
        '<td class="pmd-freeze-col-3">' + detailCellHtml(group, singleProfile, summaryParentHtml(group.metrics || {})) + '</td>' +
      '</tr>';
  }

  function groupRowHtml(group, groupIndex){
    var displayChildren = getDisplayChildren(group);
    var singleProfile = (!isExpandable(group) && displayChildren.length === 1) ? displayChildren[0] : null;
    var parentProfileIndex = singleProfile ? Number(singleProfile.__source_index || 0) : -1;
    var rowClass = isExpandable(group) ? 'pmd-group-row pmd-group-expandable' : 'pmd-group-row pmd-group-single';
    rowClass += rowAlertClass(singleProfile ? singleProfile.metrics : getDisplayAlertMetrics(group), singleProfile ? singleProfile.audit_has_mismatch : (getDisplayAlertMetrics(group).audit_has_mismatch || 0));
    var packSize = 0;
    if (singleProfile && Number(singleProfile.profile_content_per_buy || 0) > 0) {
      packSize = Number(singleProfile.profile_content_per_buy || 0);
    } else {
      var openingPack = Number((group.metrics && group.metrics.opening_pack) || 0);
      var openingContent = Number((group.metrics && group.metrics.opening_content) || 0);
      if (openingPack > 0.000001 && openingContent > 0.000001) {
        packSize = openingContent / openingPack;
      }
    }
    return '<tr class="' + rowClass + '">' + dayCells(group.daily || {}, groupIndex, parentProfileIndex, packSize) + '</tr>';
  }

  function freezeProfileRowHtml(group, profile){
    if (matrixEmbedded) return '<tr class="pmd-child-row' + rowAlertClass(profile.metrics, profile.audit_has_mismatch) + '">' + flatMaterialMetaCells(group, profile) + '</tr>';
    return '' +
      '<tr class="pmd-child-row' + rowAlertClass(profile.metrics, profile.audit_has_mismatch) + '">' + (matrixEmbedded ? '' : '<td class="pmd-freeze-col-1"></td>') +
        '<td class="pmd-freeze-col-2">' + materialCellHtml(group, profile, false, false, 'child') + '</td>' +
        '<td class="pmd-freeze-col-3">' + detailCellHtml(null, profile, summaryChildHtml(profile.metrics || {})) + '</td>' +
      '</tr>';
  }

  function flatMaterialMetaCells(group, profile){
    var row = profile || {};
    var metrics = row.metrics || {};
    var price = Number(row.profile_last_unit_price || row.profile_standard_price || 0);
    var hpp = Number(row.avg_cost_per_content || metrics.hpp || metrics.unit_price || 0);
    var category = group.category_name || row.category_name || '-';
    var destination = destinationLabel(group);
    var material = group.material_name || group.item_name || '-';
    var brand = row.profile_brand || '-';
    var contentUom = row.profile_content_uom_code || '-';
    var buyUom = row.profile_buy_uom_code || '-';
    var contentPerBuy = Number(row.profile_content_per_buy || 0) > 0 ? num(row.profile_content_per_buy) : '-';
    var packLabel = contentPerBuy !== '-' ? (contentPerBuy + ' ' + contentUom + '/' + buyUom) : (contentUom + '/' + buyUom);
    if (hpp <= 0 && Number(row.profile_content_per_buy || 0) > 0 && price > 0) {
      hpp = price / Number(row.profile_content_per_buy);
    }
    var openingValue = Number(metrics.opening_content || 0) * hpp;
    var values = [
      '<span class="pmd-compact-stack"><span class="pmd-compact-main">' + esc(category) + '</span><span class="pmd-compact-sub">' + esc(destination) + '</span></span>',
      '<span class="pmd-compact-stack"><span class="pmd-compact-main">' + esc(material) + '</span><span class="pmd-compact-sub">' + esc(brand) + ' · ' + esc(packLabel) + '</span></span>',
      '<span class="pmd-compact-stack"><span class="pmd-compact-value"><span>Harga</span><strong>Rp ' + esc(money(price)) + '</strong></span><span class="pmd-compact-value"><span>HPP</span><strong>Rp ' + esc(money(hpp)) + '</strong></span></span>',
      '<span class="pmd-compact-stack"><span class="pmd-compact-value"><span>Awal</span><strong>Rp ' + esc(money(openingValue)) + '</strong></span><span class="pmd-compact-value"><span>Sisa</span><strong>Rp ' + esc(money(Number(metrics.total_value || 0) || (Number(metrics.closing_content || 0) * hpp))) + '</strong></span></span>'
    ];
    return values.map(function(value){ return '<td class="pmd-flat-meta-cell">' + value + '</td>'; }).join('');
  }

  function profileRowHtml(group, groupIndex, profile, profileIndex){
    return '<tr class="pmd-child-row' + rowAlertClass(profile.metrics, profile.audit_has_mismatch) + '">' + dayCells(profile.daily || {}, groupIndex, profileIndex, Number(profile.profile_content_per_buy || 0)) + '</tr>';
  }

  function syncPaneRowHeights(){
    var freezeRows = Array.prototype.slice.call(freezeBody.querySelectorAll('tr'));
    var scrollRows = Array.prototype.slice.call(tableBody.querySelectorAll('tr'));
    var count = Math.min(freezeRows.length, scrollRows.length);
    var index;
    for (index = 0; index < count; index += 1) {
      freezeRows[index].style.height = '';
      scrollRows[index].style.height = '';
      var height = Math.max(freezeRows[index].offsetHeight, scrollRows[index].offsetHeight);
      freezeRows[index].style.height = height + 'px';
      scrollRows[index].style.height = height + 'px';
    }
  }

  function renderSparkline(groups, dates){
    var wrap      = document.getElementById('pmdSparklineWrap');
    var container = document.getElementById('pmdSparkline');
    if (!wrap || !container || !dates || !dates.length) { if (wrap) wrap.style.display = 'none'; return; }

    var dailyIn = {};
    dates.forEach(function(d){ dailyIn[d] = 0; });
    (groups || []).forEach(function(group){
      dates.forEach(function(d){
        var day = (group.daily && group.daily[d]) || {};
        dailyIn[d] += Number(day.in || 0);
      });
    });

    var values = dates.map(function(d){ return dailyIn[d]; });
    var maxVal  = Math.max.apply(null, values) || 1;
    var today   = (function(){ var n = new Date(); return n.getFullYear() + '-' + String(n.getMonth()+1).padStart(2,'0') + '-' + String(n.getDate()).padStart(2,'0'); })();

    container.innerHTML = values.map(function(v, i){
      var pctH  = Math.max(4, Math.round(v / maxVal * 100));
      var isTd  = dates[i] === today;
      var cls   = 'pmd-spark-bar' + (isTd ? ' is-today' : '');
      var label = dates[i] + ': ' + num(v);
      return '<div class="' + cls + '" style="height:' + pctH + '%" title="' + esc(label) + '"></div>';
    }).join('');

    wrap.style.display = '';
  }

  function render(options){
    var opts = options || {};
    var keepScroll = !!opts.keepScroll;
    var focusToday = !!opts.focusToday;
    var previousScrollLeft = tableWrap ? tableWrap.scrollLeft : 0;
    freezeHead.innerHTML = freezeHeaderHtml();
    tableHead.innerHTML = headerHtml();
    initFlatColumnResize();
    syncStickyLayout();
    requestAnimationFrame(syncFlatHeaderHeight);

    if (!state.groups.length) {
      freezeBody.innerHTML = '<tr><td colspan="3" class="pmd-empty">Belum ada data untuk filter ini.</td></tr>';
      tableBody.innerHTML = '<tr><td colspan="' + Math.max(1, state.dates.length * (matrixEmbedded ? 5 : 1)) + '" class="pmd-empty"></td></tr>';
      requestAnimationFrame(function(){
        syncStickyLayout();
        syncPaneRowHeights();
      });
      return;
    }

    var freezeHtml = '';
    var html = '';
    state.groups.forEach(function(group, groupIndex){
      if (matrixEmbedded && Array.isArray(group.children) && group.children.length) {
        getDisplayChildren(group).forEach(function(profile){
          var profileIndex = Number(profile.__source_index || 0);
          freezeHtml += freezeProfileRowHtml(group, profile);
          html += profileRowHtml(group, groupIndex, profile, profileIndex);
        });
      } else {
        freezeHtml += freezeGroupRowHtml(group);
        html += groupRowHtml(group, groupIndex);
      }
      if (!matrixEmbedded && isExpandable(group) && isExpanded(group)) {
        getDisplayChildren(group).forEach(function(profile){
          var profileIndex = Number(profile.__source_index || 0);
          freezeHtml += freezeProfileRowHtml(group, profile);
          html += profileRowHtml(group, groupIndex, profile, profileIndex);
        });
      }
    });
    freezeBody.innerHTML = freezeHtml;
    tableBody.innerHTML = html;

    requestAnimationFrame(function(){
      syncStickyLayout();
      syncPaneRowHeights();
      if (!tableWrap) { return; }
      if (tableHeadWrap) {
        tableHeadWrap.scrollLeft = keepScroll ? previousScrollLeft : tableWrap.scrollLeft;
      }
      if (focusToday) {
        requestAnimationFrame(scrollToTodayColumn);
      } else if (keepScroll) {
        tableWrap.scrollLeft = previousScrollLeft;
        if (tableHeadWrap) {
          tableHeadWrap.scrollLeft = previousScrollLeft;
        }
      }
    });
  }

  function renderDetailRows(rows){
    var body = document.getElementById('pmdModalBody');
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada mutasi pada sel ini.</td></tr>';
      return;
    }

    body.innerHTML = rows.map(function(row){
      var refText = '';
      var beforeContent = Number(row.qty_content_after || 0) - Number(row.qty_content_delta || 0);
      var beforeBuy = Number(row.qty_buy_after || 0) - Number(row.qty_buy_delta || 0);
      var deltaContent = Number(row.qty_content_delta || 0);
      var deltaBuy = Number(row.qty_buy_delta || 0);
      var afterContent = Number(row.qty_content_after || 0);
      var afterBuy = Number(row.qty_buy_after || 0);
      var buyUomCode = String(row.profile_buy_uom_code || '');
      var deltaClass = deltaContent >= 0 ? 'fin-audit-delta-positive' : 'fin-audit-delta-negative';
      if (row.ref_table) {
        refText = row.ref_table + (row.ref_id ? (' #' + row.ref_id) : '');
      }
      return '<tr>' +
        '<td class="col-date">' + esc(String(row.created_at || row.movement_date || '-')) + '</td>' +
        '<td class="col-type">' + esc(String(row.movement_type_label || row.movement_type || '-')) + '</td>' +
        '<td class="text-end col-balance"><div class="fin-audit-metric"><div class="fin-audit-primary">' + esc(num(beforeContent)) + '</div><small class="fin-audit-secondary">' + esc(num(beforeBuy) + (buyUomCode ? (' ' + buyUomCode) : '')) + '</small></div></td>' +
        '<td class="text-end col-delta ' + deltaClass + '"><div class="fin-audit-metric"><div class="fin-audit-primary">' + esc(num(deltaContent)) + '</div><small class="fin-audit-secondary">' + esc(num(deltaBuy) + (buyUomCode ? (' ' + buyUomCode) : '')) + '</small></div></td>' +
        '<td class="text-end col-balance"><div class="fin-audit-metric"><div class="fin-audit-primary">' + esc(num(afterContent)) + '</div><small class="fin-audit-secondary">' + esc(num(afterBuy) + (buyUomCode ? (' ' + buyUomCode) : '')) + '</small></div></td>' +
        '<td class="col-ref">' + esc(refText || '-') + '</td>' +
        '<td class="col-notes">' + esc(String(row.notes || '-')) + '</td>' +
      '</tr>';
    }).join('');
  }

  function openModalFallback(){
    if (!modalEl || modal) { return; }
    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
  }

  function closeModalFallback(){
    if (!modalEl || modal) { return; }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
  }

  function openDetail(groupIndex, profileIndex, dateText){
    var group = state.groups[groupIndex];
    if (!group || !dateText) { return; }

    var row = null;
    if (profileIndex >= 0 && group.children[profileIndex]) {
      row = group.children[profileIndex];
    }

    var title = group.material_name || group.item_name || '-';
    var subtitleParts = [
      'Tanggal: ' + dateText,
      'Divisi: ' + divisionLabel(group),
      'Tujuan: ' + destinationLabel(group)
    ];
    if (row) {
      subtitleParts.push('Profil: ' + (row.profile_name || '-'));
    }

    document.getElementById('pmdModalTitle').textContent = title;
    document.getElementById('pmdModalSubtitle').textContent = subtitleParts.join(' | ');
    document.getElementById('pmdModalBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Memuat detail mutasi...</td></tr>';

    if (modal) {
      modal.show();
    } else {
      openModalFallback();
    }

    var p = new URLSearchParams();
    p.set('scope', 'DIVISION');
    p.set('movement_date', dateText);
    p.set('division_id', String(group.division_id || 0));
    p.set('stock_domain', String((row && row.stock_domain) || group.stock_domain || 'MATERIAL'));
    p.set('item_id', String(group.item_id || 0));
    p.set('material_id', String(group.material_id || 0));

    if (group.destination_type) {
      p.set('destination_type', String(group.destination_type));
    }

    if (row) {
      p.set('buy_uom_id', String(row.buy_uom_id || 0));
      p.set('content_uom_id', String(row.content_uom_id || 0));
      if (row.profile_key) {
        p.set('profile_key', String(row.profile_key));
      }
    } else {
      p.set('buy_uom_id', '0');
      p.set('content_uom_id', '0');
    }

    p.set('limit', '400');

    fetch(detailUrl + '?' + p.toString(), { headers: { 'Accept': 'application/json' } })
      .then(parseJson)
      .then(function(result){
        renderDetailRows(Array.isArray(result.rows) ? result.rows : []);
      })
      .catch(function(err){
        document.getElementById('pmdModalBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">' + esc(err && err.message ? err.message : 'Gagal memuat detail mutasi.') + '</td></tr>';
      });
  }

  function loadData(){
    tableBody.innerHTML = '<tr><td colspan="999" class="pmd-loading">Memuat data...</td></tr>';
    showMessage(true, '');

    return fetch(matrixUrl + '?' + buildQuery(), { headers: { 'Accept': 'application/json' } })
      .then(parseJson)
      .then(function(result){
        var data = (result && result.data) ? result.data : {};
        state.dates = Array.isArray(data.dates) ? data.dates : [];
        state.groups = buildGroups(Array.isArray(data.rows) ? data.rows : [], state.dates);
        state.totalCount = Number(result.total_count || data.total_count || 0);
        if (state.totalCount === 0 && state.groups.length > 0) {
          state.totalCount = state.groups.length;
        }
        var serverSummary = (data && data.summary) ? data.summary : null;
        calcStats(state.groups, serverSummary);
        render({ focusToday: state.offset === 0 });
        renderPagination();
        renderSparkline(state.groups, state.dates);
      })
      .catch(function(err){
        freezeHead.innerHTML = '';
        tableHead.innerHTML = '';
        freezeBody.innerHTML = '<tr><td colspan="3" class="pmd-empty">Gagal memuat matrix harian.</td></tr>';
        tableBody.innerHTML = '<tr><td colspan="1" class="pmd-empty"></td></tr>';
        showMessage(false, err && err.message ? err.message : 'Terjadi kesalahan saat memuat data.');
        return null;
      });
  }

  document.getElementById('pmdApply').addEventListener('click', function(){
    readFilters();
    state.offset = 0;
    loadData();
  });

  document.getElementById('pmdDivision').addEventListener('change', function(){
    readFilters();
    applyDestinationGuard();
    state.destination = document.getElementById('pmdDestination').value || 'ALL';
  });

  document.getElementById('pmdClear').addEventListener('click', function(){
    clearFilters();
    applyDestinationGuard();
    state.offset = 0;
    loadData();
  });

  document.getElementById('pmdQ').addEventListener('keydown', function(ev){
    if (ev.key === 'Enter') {
      ev.preventDefault();
      readFilters();
      state.offset = 0;
      loadData();
    }
  });

  document.getElementById('pmdMonth').addEventListener('change', syncMonthWindowInputs);

  if (tableWrap && tableHeadWrap) {
    tableWrap.addEventListener('scroll', function(){
      tableHeadWrap.scrollLeft = tableWrap.scrollLeft;
    });
  }

  matrixShell.addEventListener('click', function(ev){
    var toggle = ev.target && ev.target.closest ? ev.target.closest('[data-action="toggle-group"]') : null;
    if (toggle) {
      var groupKey = toggle.getAttribute('data-group-key') || '';
      if (groupKey) {
        state.expanded[groupKey] = !state.expanded[groupKey];
        render({ keepScroll: true });
      }
      return;
    }

    var adjustBtn = ev.target && ev.target.closest ? ev.target.closest('[data-action="adjust"]') : null;
    if (adjustBtn) {
      var adjustGroupIndex = parseInt(adjustBtn.getAttribute('data-group-index') || '', 10);
      var adjustProfileIndex = parseInt(adjustBtn.getAttribute('data-profile-index') || '', 10);
      var adjustDateText = adjustBtn.getAttribute('data-date') || '';
      if (!Number.isFinite(adjustGroupIndex) || adjustGroupIndex < 0) { return; }
      if (!Number.isFinite(adjustProfileIndex)) { adjustProfileIndex = -1; }
      openAdjust(adjustGroupIndex, adjustProfileIndex, adjustDateText);
      return;
    }

    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-action="detail"]') : null;
    if (!btn) { return; }
    var groupIndex = parseInt(btn.getAttribute('data-group-index') || '', 10);
    var profileIndex = parseInt(btn.getAttribute('data-profile-index') || '', 10);
    var dateText = btn.getAttribute('data-date') || '';
    if (!Number.isFinite(groupIndex) || groupIndex < 0) { return; }
    if (!Number.isFinite(profileIndex)) { profileIndex = -1; }
    openDetail(groupIndex, profileIndex, dateText);
  });

  if (!modal) {
    modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn){
      btn.addEventListener('click', function(event){
        event.preventDefault();
        closeModalFallback();
      });
    });

    modalEl.addEventListener('click', function(event){
      if (event.target === modalEl) {
        closeModalFallback();
      }
    });

    document.addEventListener('keydown', function(event){
      if (event.key === 'Escape' && modalEl.classList.contains('show')) {
        closeModalFallback();
      }
    });
  }

  if (!adjustModal) {
    adjustModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn){
      btn.addEventListener('click', function(event){
        event.preventDefault();
        closeAdjustModalFallback();
      });
    });
    adjustModalEl.addEventListener('click', function(event){
      if (event.target === adjustModalEl) {
        closeAdjustModalFallback();
      }
    });
  }

  document.getElementById('pmdAdjustSubmit').addEventListener('click', submitAdjust);

  window.addEventListener('resize', function(){
    syncStickyLayout();
    syncPaneRowHeights();
  });

  readFilters();
  applyDestinationGuard();
  loadData();
})();
</script>
