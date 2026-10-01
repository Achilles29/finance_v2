<?php
$initialMonth = (string)($month ?? date('Y-m'));
$initialMonth = preg_match('/^\d{4}-\d{2}$/', substr($initialMonth, 0, 7)) ? substr($initialMonth, 0, 7) : date('Y-m');
$initialWindowStart = $initialMonth . '-01';
$initialWindowEnd = date('Y-m-t', strtotime($initialWindowStart));
$profileAuditBaseUrl = site_url('inventory/fifo-audit');
$adjustmentStoreUrl = site_url('inventory/stock/adjustment/store');
$adjustmentIndexUrl = site_url('inventory/stock/adjustment/warehouse');
$initialQ = (string)($q ?? '');
$initialDateFrom = (string)($date_from ?? '');
$initialDateTo = (string)($date_to ?? '');
$initialLimit = (int)($limit ?? 120);
if ($initialLimit <= 0 || $initialLimit > 1000) {
  $initialLimit = 120;
}
$matrixEmbedded = !empty($matrix_embedded);
?>

<?php if (!$matrixEmbedded): ?>
<div class="mb-2">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div><h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title ?? 'Inventory Warehouse Daily'); ?></h4>
      <small class="text-muted">Matrix stok gudang per hari dengan ringkasan item yang bisa di-expand per profil.</small></div>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('inventory/daily-matrix') . '?' . http_build_query(['scope' => 'warehouse', 'month' => $initialMonth]); ?>"><i class="ri ri-fullscreen-line me-1"></i>Workspace Matrix</a>
  </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'WAREHOUSE', 'active_tab' => 'daily_matrix']); ?>
</div>
<?php $this->load->view('purchase/_warehouse_stock_generate_btn', [
  'warehouse_action_params' => ['month' => substr($initialMonth, 0, 7), 'date_from' => $initialDateFrom],
]); ?>
<?php endif; ?>

<style>
  :root {
    --pwd-sticky-top: 0px;
    --pwd-col-kind: 92px;
    --pwd-col-item-profile: 360px;
    --pwd-col-summary: 240px;
    --pwd-left-1: 0px;
    --pwd-left-2: var(--pwd-col-kind);
    --pwd-left-3: calc(var(--pwd-col-kind) + var(--pwd-col-item-profile));
    --pwd-date-col: 172px;
    --pwd-header-row-1: 44px;
  }
  .pwd-filter-card,
  .pwd-board-card,
  .pwd-scroll-table,
  .pwd-modal-card {
    font-family: "Trebuchet MS", Verdana, sans-serif;
  }
  .pwd-filter-card {
    border: 1px solid #ead9cf;
    border-radius: 16px;
    box-shadow: 0 12px 24px rgba(104, 43, 40, 0.06);
  }
  .pwd-board-card {
    border: 1px solid #ead9cf;
    border-radius: 18px;
    overflow: visible;
    box-shadow: 0 14px 28px rgba(104, 43, 40, 0.08);
  }
  .pwd-board-head {
    background: linear-gradient(135deg, #5f2432, #8c3f49);
    color: #fff;
    padding: 0.8rem 0.95rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
  }
  .pwd-legend { display: flex; flex-wrap: wrap; gap: 0.4rem; }
  .pwd-legend-pill {
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
  .pwd-legend-dot { width: 8px; height: 8px; border-radius: 999px; }
  .pwd-dot-open { background: #2f5b95; }
  .pwd-dot-in { background: #2bb673; }
  .pwd-dot-out { background: #f17f43; }
  .pwd-dot-adj { background: #7f64d9; }
  .pwd-dot-close { background: #4e6b83; }
  .pwd-table-wrap {
    overflow: auto;
    position: relative;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
    isolation: isolate;
  }
  .pwd-matrix-shell {
    display: grid;
    grid-template-columns: calc(var(--pwd-col-kind) + var(--pwd-col-item-profile) + var(--pwd-col-summary)) minmax(0, 1fr);
    align-items: start;
  }
  .pwd-freeze-pane {
    position: relative;
    z-index: 2;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
    border-right: 4px solid #d2a08e;
    box-shadow: 12px 0 22px -18px rgba(95, 23, 39, 0.26);
  }
  .pwd-scroll-pane {
    min-width: 0;
    position: relative;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
  }
  .pwd-freeze-head-wrap,
  .pwd-scroll-head-wrap {
    position: sticky;
    top: var(--pwd-sticky-top);
    z-index: 52;
  }
  .pwd-freeze-head-wrap {
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
  }
  .pwd-scroll-head-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
  }
  .pwd-scroll-head-wrap::-webkit-scrollbar {
    display: none;
  }
  .pwd-freeze-table {
    width: calc(var(--pwd-col-kind) + var(--pwd-col-item-profile) + var(--pwd-col-summary));
    min-width: calc(var(--pwd-col-kind) + var(--pwd-col-item-profile) + var(--pwd-col-summary));
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pwd-scroll-table {
    min-width: 920px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pwd-freeze-table thead th,
  .pwd-freeze-table tbody td,
  .pwd-scroll-table thead th,
  .pwd-scroll-table tbody td {
    padding: 0.42rem 0.45rem;
    vertical-align: middle;
  }
  .pwd-freeze-table td {
    background: #fffaf7;
    color: #4a2430;
    box-shadow: inset -2px 0 0 #e6c9bd;
  }
  .pwd-freeze-col-1 { width: var(--pwd-col-kind); min-width: var(--pwd-col-kind); max-width: var(--pwd-col-kind); }
  .pwd-freeze-col-2 { width: var(--pwd-col-item-profile); min-width: var(--pwd-col-item-profile); max-width: var(--pwd-col-item-profile); }
  .pwd-freeze-col-3 { width: var(--pwd-col-summary); min-width: var(--pwd-col-summary); max-width: var(--pwd-col-summary); }
  .pwd-scroll-table {
    min-width: 920px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pwd-scroll-table thead th,
  .pwd-scroll-table tbody td {
    padding: 0.42rem 0.45rem;
    vertical-align: middle;
  }
  .pwd-scroll-table thead tr:first-child th {
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
    border-bottom: 1px solid #772938;
    color: #fff8f5;
    white-space: normal;
    width: var(--pwd-date-col);
    min-width: var(--pwd-date-col);
    max-width: var(--pwd-date-col);
  }
  .pwd-freeze-table thead tr:first-child th {
    background: linear-gradient(180deg, #7a1d2c 0%, #954052 100%);
    border-bottom: 1px solid #772938;
    color: #fff8f5;
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    height: var(--pwd-header-row-1);
    min-height: var(--pwd-header-row-1);
    white-space: nowrap;
  }
  .pwd-freeze-body-table,
  .pwd-scroll-body-table {
    margin-top: 0;
  }
  .pwd-day-head {
    width: var(--pwd-date-col);
    min-width: var(--pwd-date-col);
    max-width: var(--pwd-date-col);
    padding: 0.45rem 0.38rem 0.5rem !important;
  }
  .pwd-day-head.is-today {
    background: linear-gradient(180deg, #ffb79e, #ff9370) !important;
    box-shadow: inset 0 -4px 0 #cf5f3e;
    color: #56190e !important;
  }
  .pwd-headcard {
    display: grid;
    gap: 0.16rem;
    justify-items: center;
    text-align: center;
    line-height: 1.05;
  }
  .pwd-headcard .day {
    font-size: 1.24rem;
    font-weight: 900;
    letter-spacing: 0.02em;
  }
  .pwd-headcard .weekday {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.92;
  }
  .pwd-headcard .full-date {
    font-size: 0.7rem;
    opacity: 0.88;
  }
  .pwd-headcard .today-tag {
    margin-top: 0.08rem;
    padding: 0.14rem 0.42rem;
    border-radius: 999px;
    font-size: 0.61rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    background: rgba(93, 22, 13, 0.12);
    color: inherit;
  }
  .pwd-kind-pill {
    display: inline-flex;
    padding: 0.15rem 0.48rem;
    border-radius: 999px;
    border: 1px solid #ebd4ca;
    background: #fff2ec;
    color: #8b3c47;
    font-size: 0.69rem;
    font-weight: 800;
  }
  .pwd-name { font-weight: 800; color: #4e1f2e; line-height: 1.28; }
  .pwd-code { color: #876a65; font-size: 0.79rem; margin-top: 0.14rem; }
  .pwd-name-stack {
    display: grid;
    gap: 0.18rem;
  }
  .pwd-item-profile-stack {
    display: grid;
    gap: 0.52rem;
    min-width: 0;
  }
  .pwd-item-profile-stack .pwd-profile-stack {
    padding-top: 0.46rem;
    border-top: 1px dashed #ead6c9;
  }
  .pwd-item-profile-stack.is-child {
    padding-left: 0.32rem;
  }
  .pwd-profile-stack {
    display: grid;
    gap: 0.22rem;
    position: relative;
    width: 100%;
  }
  .pwd-profile-stack.is-parent {
    padding-inline: 0.1rem;
  }
  .pwd-profile-stack.is-child {
    width: calc(100% - 18px);
    margin-left: 18px;
    padding-left: 0.38rem;
  }
  .pwd-profile-stack.is-child::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 0.22rem;
    bottom: 0.22rem;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(180deg, #efd8cc 0%, #e1bca7 100%);
  }
  .pwd-profile-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.16rem 0.52rem;
    background: #fff4eb;
    color: #8b4f42;
    font-size: 0.68rem;
    font-weight: 800;
    width: fit-content;
    max-width: 100%;
  }
  .pwd-profile-meta.is-parent {
    border-radius: 999px;
    border: 1px solid #ead6c9;
  }
  .pwd-profile-meta.is-child {
    border-radius: 10px;
    border: 1px dashed #ead6c9;
    background: #fffaf7;
    color: #7c5348;
  }
  .pwd-profile-line {
    color: #6e5652;
    font-size: 0.82rem;
    line-height: 1.25;
    white-space: normal;
    word-break: break-word;
  }
  .pwd-profile-unit {
    margin-top: 0.18rem;
    color: #7d5648;
    font-size: 0.79rem;
    font-weight: 700;
  }
  .pwd-group-row {
    background: #fff9f6;
    border-top: 2px solid #f0d9cf;
  }
  .pwd-group-row.pwd-group-expandable {
    background: linear-gradient(90deg, #fff4eb, #fffaf6);
  }
  .pwd-group-row.pwd-group-single {
    background: linear-gradient(90deg, #f8fff5, #fafff8);
  }
  .pwd-child-row {
    background: #fffdfb;
  }
  .pwd-freeze-table tbody tr.pwd-group-row td {
    background: linear-gradient(180deg, #fff4eb 0%, #fffaf6 100%);
  }
  .pwd-freeze-table tbody tr.pwd-group-row.pwd-group-single td {
    background: linear-gradient(180deg, #f8fff5 0%, #fafff8 100%);
  }
  .pwd-freeze-table tbody tr.pwd-child-row td {
    background: linear-gradient(180deg, #fffdfb 0%, #fff8f4 100%);
    box-shadow: inset -2px 0 0 #edd6ca;
  }
  .pwd-freeze-table tbody tr.pwd-child-row td.pwd-freeze-col-2,
  .pwd-freeze-table tbody tr.pwd-child-row td.pwd-freeze-col-3 {
    border-left: 3px solid #efd8cc;
  }
  .pwd-scroll-table tbody tr.pwd-group-row td.pwd-metric-cell {
    background: #fff6f0;
  }
  .pwd-scroll-table tbody tr.pwd-group-row.pwd-group-single td.pwd-metric-cell {
    background: #fbfef8;
  }
  .pwd-scroll-table tbody tr.pwd-child-row td.pwd-metric-cell {
    background: #fffdfb;
  }
  .pwd-scroll-table tbody tr.pwd-group-row .pwd-date-card {
    border-color: #ebcdbd;
    background: linear-gradient(180deg, rgba(255, 247, 240, 0.98) 0%, rgba(255, 238, 228, 0.98) 100%);
    box-shadow: 0 14px 24px -24px rgba(122, 61, 0, 0.28);
  }
  .pwd-scroll-table tbody tr.pwd-group-row.pwd-group-single .pwd-date-card {
    border-color: #d9e6cf;
    background: linear-gradient(180deg, rgba(250, 255, 246, 0.98) 0%, rgba(242, 251, 237, 0.98) 100%);
  }
  .pwd-scroll-table tbody tr.pwd-child-row .pwd-date-card {
    border-color: #f0dfd5;
    background: linear-gradient(180deg, rgba(255, 253, 251, 0.98) 0%, rgba(255, 248, 243, 0.98) 100%);
    box-shadow: inset 0 0 0 1px rgba(239, 216, 204, 0.42);
  }
  .pwd-group-row td,
  .pwd-child-row td {
    vertical-align: top !important;
  }
  .pwd-toggle-arrow {
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
  .pwd-toggle-arrow:hover {
    background: #ffece2;
    border-color: #c99f8f;
  }
  .pwd-toggle-static {
    display: inline-flex;
    width: 26px;
    height: 24px;
    align-items: center;
    justify-content: center;
    margin-right: 0.3rem;
    border-radius: 8px;
    background: #e9f8df;
    color: #3e7f32;
    font-size: 0.8rem;
    font-weight: 800;
  }
  .pwd-group-toggle {
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
  .pwd-group-toggle:hover {
    background: #ffe8dd;
    border-color: #d7b3a5;
  }
  .pwd-summary-card {
    display: grid;
    gap: 0.42rem;
    min-width: 0;
  }
  .pwd-summary-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
  }
  .pwd-summary-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #8a5b4d;
    font-weight: 800;
  }
  .pwd-summary-amount {
    font-size: 0.96rem;
    font-weight: 900;
    color: #523126;
    text-align: right;
  }
  .pwd-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.38rem;
  }
  .pwd-summary-metric {
    border: 1px solid #eadccf;
    border-radius: 12px;
    background: #fffaf6;
    padding: 0.42rem 0.48rem;
    min-width: 0;
  }
  .pwd-summary-metric .label {
    display: block;
    font-size: 0.67rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #8a5b4d;
  }
  .pwd-summary-metric strong {
    display: block;
    font-size: 0.88rem;
    color: #503125;
    line-height: 1.18;
    white-space: normal;
  }
  .pwd-metric-cell {
    width: var(--pwd-date-col);
    min-width: var(--pwd-date-col);
    max-width: var(--pwd-date-col);
    text-align: left;
    font-size: 0.79rem;
    font-variant-numeric: tabular-nums;
    white-space: normal;
    background: #fff;
    vertical-align: top !important;
    padding: 0.4rem !important;
  }
  .pwd-metric-cell.is-today {
    background: #fff4ed;
  }
  .pwd-date-card {
    display: grid;
    gap: 0.34rem;
    min-height: 148px;
    padding: 0.56rem 0.58rem;
    border-radius: 16px;
    border: 1px solid #efd9ca;
    background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(255,249,244,0.98) 100%);
    box-shadow: 0 14px 22px -24px rgba(95, 53, 39, 0.55);
  }
  .pwd-date-band-b .pwd-date-card {
    background: linear-gradient(180deg, rgba(255,251,248,0.98) 0%, rgba(255,244,237,0.98) 100%);
  }
  .pwd-metric-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.45rem;
    align-items: center;
    font-size: 0.72rem;
    line-height: 1.15;
  }
  .pwd-metric-row + .pwd-metric-row {
    padding-top: 0.32rem;
    border-top: 1px dashed #edd7c8;
  }
  .pwd-metric-main {
    min-width: 0;
  }
  .pwd-metric-label {
    display: block;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #8a5b4d;
  }
  .pwd-metric-value-wrap {
    min-width: 0;
    display: flex;
    justify-content: flex-end;
  }
  .pwd-cell-btn {
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
  button.pwd-cell-btn:hover {
    opacity: 0.82;
  }
  .pwd-cell-btn.is-static {
    opacity: 0.94;
  }
  .pwd-cell-action-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.28rem;
    min-width: 0;
  }
  .pwd-cell-adjust-trigger {
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
  .pwd-cell-adjust-trigger:hover {
    background: #fcefe8;
    border-color: #cfa692;
    color: #5f2432;
  }
  .pwd-cell-adjust-trigger .ri {
    font-size: 0.95rem;
    line-height: 1;
  }
  .pwd-cell-adjust-glyph {
    display: block;
    line-height: 1;
    transform: translateY(-1px);
  }
  .pwd-metric-open { color: #2f5b95; }
  .pwd-metric-in { color: #248c5d; }
  .pwd-metric-out { color: #cd6b35; }
  .pwd-metric-adj { color: #6a52cf; }
  .pwd-metric-close { color: #4f647d; font-weight: 800; }
  .pwd-cell-pack { display: block; font-size: 0.76rem; font-weight: 800; line-height: 1.05; }
  .pwd-cell-content { display: block; font-size: 0.66rem; opacity: 0.84; line-height: 1.05; margin-top: 2px; }
  .pwd-cell-pack,
  .pwd-cell-content {
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .pwd-date-band-a {
    background-color: #fffefc;
  }
  .pwd-date-band-b {
    background-color: #fff6f1;
  }
  .pwd-day-start { border-left: 1px solid #d8b8a9 !important; }
  .pwd-day-end { border-right: 1px solid #d8b8a9 !important; }
  .pwd-empty,
  .pwd-loading {
    text-align: center;
    color: #8e6f67;
    padding: 2rem 1rem;
  }
  .pwd-stat-card {
    min-height:116px; position:relative; overflow:hidden; border:0; border-radius:14px;
    color:#fff; padding:.9rem 1rem; box-shadow:0 4px 18px rgba(0,0,0,.13);
  }
  .pwd-stat-card::before,.pwd-stat-card::after { content:''; position:absolute; border-radius:50%; background:rgba(255,255,255,.12); }
  .pwd-stat-card::before { width:80px; height:80px; right:-18px; bottom:-18px; }
  .pwd-stat-card::after { width:56px; height:56px; right:14px; top:-22px; background:rgba(255,255,255,.09); }
  .pwd-stat-card > * { position:relative; z-index:1; }
  .pwd-stat-card.is-violet { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
  .pwd-stat-card.is-aqua { background:linear-gradient(135deg,#0c7cba 0%,#0fcdba 100%); }
  .pwd-stat-card.is-blue { background:linear-gradient(135deg,#1c7ed6 0%,#74c0fc 100%); }
  .pwd-stat-card.is-teal { background:linear-gradient(135deg,#134e5e 0%,#38b2a3 100%); }
  .pwd-stat-icon { display:block; min-height:1.25rem; font-size:1.25rem; line-height:1; opacity:.82; }
  .pwd-stat-label { margin-top:.42rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; font-weight:800; opacity:.85; }
  .pwd-stat-value { margin-top:.18rem; font-size:1.38rem; font-weight:800; line-height:1.15; overflow-wrap:anywhere; }
  .pwd-modal-card {
    border: 1px solid #e9d9d1;
    border-radius: 12px;
    background: #fff9f6;
    padding: 0.75rem 0.9rem;
  }
  .pwd-adjust-selected {
    border: 1px dashed #d8b8a9;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #fff7f2 100%);
    padding: 0.85rem 0.95rem;
  }
  .pwd-adjust-help {
    border: 1px solid #edd8cf;
    border-radius: 12px;
    background: #fffaf7;
    padding: 0.75rem 0.85rem;
    font-size: 0.83rem;
    color: #6f4d47;
  }
  @media (max-width: 991.98px) {
    :root {
      --pwd-col-kind: 84px;
      --pwd-col-item-profile: 360px;
      --pwd-col-summary: 216px;
      --pwd-date-col: 162px;
    }
    .pwd-matrix-shell { grid-template-columns: calc(var(--pwd-col-kind) + var(--pwd-col-item-profile) + var(--pwd-col-summary)) minmax(0, 1fr); }
    .pwd-date-card { min-height: 142px; }
  }
  <?php if ($matrixEmbedded): ?>
  :root { --pwd-date-col: 300px; }
  .pwd-filter-card { margin-bottom: .35rem !important; }
  .pwd-filter-card .card-body { padding: .45rem .65rem; }
  .pwd-stat-card { min-height: 54px; padding: .45rem .7rem; border-radius: 10px; }
  .pwd-stat-icon, .pwd-stat-card::before, .pwd-stat-card::after { display: none !important; }
  .pwd-stat-label { font-size: .62rem; margin: 0; }
  .pwd-stat-value { font-size: 1rem; margin: 0; }
  .pwd-board-head { padding: .35rem .65rem; }
  .pwd-matrix-shell { grid-template-columns: calc(var(--pwd-col-kind) + var(--pwd-col-item-profile) + var(--pwd-col-summary)) minmax(0, 1fr); }
  .pwd-summary-card { display: block; }
  .pwd-summary-head { align-items: center; }
  .pwd-summary-title { font-size: .58rem; }
  .pwd-summary-amount { font-size: .76rem; }
  .pwd-summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .15rem; }
  .pwd-summary-metric { padding: .16rem .2rem; border-radius: 5px; }
  .pwd-summary-metric .label { font-size: .52rem; }
  .pwd-summary-metric strong { font-size: .65rem; white-space: nowrap; }
  .pwd-date-card { display: grid; grid-template-columns: repeat(5, minmax(55px, 1fr)); gap: 0; min-height: 0; padding: .24rem; border-radius: 5px; box-shadow: none; }
  .pwd-metric-row { display: flex; min-width: 0; flex-direction: column; align-items: flex-end; justify-content: center; gap: .12rem; padding: .1rem .18rem; font-size: .64rem; }
  .pwd-metric-row + .pwd-metric-row { border-top: 0; border-left: 1px solid #e7d8cf; padding-top: .1rem; }
  .pwd-metric-label { font-size: .55rem; }
  .pwd-metric-value-wrap { width: 100%; justify-content: flex-end; }
  .pwd-cell-pack, .pwd-cell-content { font-size: .63rem; }
  .pwd-scroll-table tbody tr:nth-child(odd) td.pwd-metric-cell { background: #fff !important; }
  .pwd-scroll-table tbody tr:nth-child(even) td.pwd-metric-cell { background: #e9eef3 !important; }
  .pwd-freeze-table tbody tr:nth-child(odd) td { background: #fff !important; }
  .pwd-freeze-table tbody tr:nth-child(even) td { background: #e9eef3 !important; }
  .pwd-scroll-table tbody tr td.pwd-metric-cell.is-today { background: #ffe09a !important; box-shadow: inset 0 0 0 2px #d78300; }
  .pwd-scroll-table tbody tr.pwd-stock-alert td.pwd-metric-cell { background: #b91c1c !important; color: #fff !important; }
  .pwd-scroll-table tbody tr.pwd-stock-alert .pwd-metric-label,
  .pwd-scroll-table tbody tr.pwd-stock-alert .pwd-cell-pack,
  .pwd-scroll-table tbody tr.pwd-stock-alert .pwd-cell-content { color: #fff !important; }
  .pwd-freeze-table tbody tr.pwd-stock-alert td { background: #b91c1c !important; color: #fff !important; }
  .pwd-freeze-table tbody tr.pwd-stock-alert .pwd-name,
  .pwd-freeze-table tbody tr.pwd-stock-alert .pwd-code,
  .pwd-freeze-table tbody tr.pwd-stock-alert .pwd-profile-line,
  .pwd-freeze-table tbody tr.pwd-stock-alert .pwd-profile-meta { color: #fff !important; }
  .pwd-day-head.is-today { background: #f6a900 !important; color: #321800 !important; box-shadow: inset 0 0 0 2px #a95b00; }
  .pwd-flat-metric { width: 76px !important; min-width: 76px !important; max-width: 76px !important; padding: .18rem .25rem !important; text-align: right; white-space: nowrap; vertical-align: middle !important; }
  .pwd-day-group { min-width: 380px !important; text-align: center; }
  .pwd-submetric { min-width: 76px; width: 76px; padding: .3rem .2rem !important; text-align: center; font-size: .68rem; }
  .pwd-metric-subhead th { background: #8e1628; color: #fff; }
  .pwd-scroll-table tbody tr.pwd-stock-alert td.pwd-flat-metric { background: #b91c1c !important; color: #fff !important; }
  .pwd-stock-alert td, .pwd-stock-alert td * { color: #fff !important; }
  .pwd-day-head.is-today, .pwd-submetric.is-today { background: #f6a900 !important; color: #321800 !important; box-shadow: inset 0 0 0 2px #a95b00; }
  .pwd-matrix-shell.is-flat { grid-template-columns: var(--pwd-freeze-width, 900px) minmax(0, 1fr); }
  .pwd-freeze-table.is-flat { width: var(--pwd-freeze-width, 900px); min-width: var(--pwd-freeze-width, 900px); table-layout: fixed; }
  .pwd-freeze-table.is-flat thead th { vertical-align:middle!important; }
  .pwd-freeze-head-table,.pwd-freeze-head-table thead,.pwd-freeze-head-table tr{height:100%}
  .pwd-freeze-table.is-flat thead .pwd-flat-meta-cell{overflow:visible}
  .pwd-flat-meta-cell { position:relative;padding:.28rem .38rem!important;font-size:.68rem;line-height:1.18;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
  .pwd-flat-metric .pwd-cell-pack, .pwd-flat-metric .pwd-cell-content { display: inline-block; white-space: nowrap; }
  .pwd-flat-metric .pwd-cell-content { margin-left: .22rem; }
  .pwd-flat-meta-cell:nth-child(1) { width: 92px; min-width: 92px; }
  .pwd-flat-meta-cell:nth-child(2) { width: 82px; min-width: 82px; }
  .pwd-flat-meta-cell:nth-child(3) { width: 68px; min-width: 68px; }
  .pwd-flat-meta-cell:nth-child(4) { width: 178px; min-width: 178px; }
  .pwd-flat-meta-cell:nth-child(5) { width: 105px; min-width: 105px; }
  .pwd-flat-meta-cell:nth-child(6) { width: 145px; min-width: 145px; }
  .pwd-flat-meta-cell:nth-child(7) { width: 96px; min-width: 96px; }
  .pwd-flat-meta-cell:nth-child(8), .pwd-flat-meta-cell:nth-child(9) { width: 76px; min-width: 76px; }
  .pwd-flat-meta-cell:nth-child(10) { width: 82px; min-width: 82px; }
  .pwd-flat-meta-cell:nth-child(1){width:var(--pwd-flat-col-1,92px);min-width:var(--pwd-flat-col-1,92px)}
  .pwd-flat-meta-cell:nth-child(2){width:var(--pwd-flat-col-2,82px);min-width:var(--pwd-flat-col-2,82px)}
  .pwd-flat-meta-cell:nth-child(3){width:var(--pwd-flat-col-3,68px);min-width:var(--pwd-flat-col-3,68px)}
  .pwd-flat-meta-cell:nth-child(4){width:var(--pwd-flat-col-4,178px);min-width:var(--pwd-flat-col-4,178px)}
  .pwd-flat-meta-cell:nth-child(5){width:var(--pwd-flat-col-5,105px);min-width:var(--pwd-flat-col-5,105px)}
  .pwd-flat-meta-cell:nth-child(6){width:var(--pwd-flat-col-6,145px);min-width:var(--pwd-flat-col-6,145px)}
  .pwd-flat-meta-cell:nth-child(7){width:var(--pwd-flat-col-7,96px);min-width:var(--pwd-flat-col-7,96px)}
  .pwd-flat-meta-cell:nth-child(8){width:var(--pwd-flat-col-8,76px);min-width:var(--pwd-flat-col-8,76px)}
  .pwd-flat-meta-cell:nth-child(9){width:var(--pwd-flat-col-9,76px);min-width:var(--pwd-flat-col-9,76px)}
  .pwd-flat-meta-cell:nth-child(10){width:var(--pwd-flat-col-10,82px);min-width:var(--pwd-flat-col-10,82px)}
  .pwd-col-resizer{position:absolute;top:0;right:-3px;width:7px;height:100%;cursor:col-resize;z-index:12}
  .pwd-col-resizer:hover,.pwd-col-resizer.is-dragging{background:#f6a900}
  .pwd-stock-alert .pwd-cell-adjust-trigger{background:#fff!important;border-color:#fff!important;color:#8e1628!important}
  .pwd-table-wrap { max-height: none; }
  <?php endif; ?>
</style>

<div id="pwdActionMsg" class="mb-2"></div>
<?php if (!$matrixEmbedded): ?><?php $matrix_month_selector = '#pwdMonth'; $this->load->view('inventory/_matrix_spreadsheet_panel'); ?><?php endif; ?>

<div class="card pwd-filter-card mb-2">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-2"<?php echo $matrixEmbedded ? ' style="display:none"' : ''; ?>>
        <label class="form-label mb-1">Bulan</label>
        <input type="month" id="pwdMonth" class="form-control" value="<?php echo html_escape(substr($initialMonth, 0, 7)); ?>">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" id="pwdQ" class="form-control" value="<?php echo html_escape($initialQ); ?>" placeholder="Item, bahan baku, profile, merk">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Mulai Tampilan</label>
        <input type="date" id="pwdDateFrom" class="form-control" min="<?php echo html_escape($initialWindowStart); ?>" max="<?php echo html_escape($initialWindowEnd); ?>" value="<?php echo html_escape($initialDateFrom); ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Sampai Tampilan</label>
        <input type="date" id="pwdDateTo" class="form-control" min="<?php echo html_escape($initialWindowStart); ?>" max="<?php echo html_escape($initialWindowEnd); ?>" value="<?php echo html_escape($initialDateTo); ?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" id="pwdLimit" class="form-control" min="1" max="1000" value="<?php echo (int)$initialLimit; ?>">
      </div>
      <div class="col-6 col-md-1 d-grid">
        <button type="button" id="pwdApply" class="btn btn-primary">Terapkan</button>
      </div>
      <div class="col-6 col-md-1 d-grid">
        <button type="button" id="pwdClear" class="btn btn-outline-danger">Clear</button>
      </div>
    </div>
  </div>
</div>

<div class="row g-2 mb-3" aria-label="Ringkasan daily matrix gudang">
  <div class="col-6 col-md-3"><div class="pwd-stat-card is-violet"><span class="pwd-stat-icon" aria-hidden="true"><i class="ri ri-archive-stack-line"></i></span><div class="pwd-stat-label">Profil</div><div id="pwdStatProfiles" class="pwd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pwd-stat-card is-aqua"><span class="pwd-stat-icon" aria-hidden="true"><i class="ri ri-shopping-bag-3-line"></i></span><div class="pwd-stat-label">Item</div><div id="pwdStatItems" class="pwd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pwd-stat-card is-blue"><span class="pwd-stat-icon" aria-hidden="true"><i class="ri ri-flask-line"></i></span><div class="pwd-stat-label">Bahan Baku</div><div id="pwdStatMaterials" class="pwd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pwd-stat-card is-teal"><span class="pwd-stat-icon" aria-hidden="true"><i class="ri ri-money-dollar-circle-line"></i></span><div class="pwd-stat-label">Nilai Sisa</div><div id="pwdStatValue" class="pwd-stat-value">0,00</div></div></div>
</div>

<div class="card pwd-board-card">
  <div class="pwd-board-head">
    <div>
      <div class="fw-semibold">Matrix Daily Inventory Gudang</div>
    </div>
    <div class="pwd-legend">
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-open"></span>Awal</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-in"></span>In</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-out"></span>Out</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-adj"></span>Adj</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-close"></span>Akhir</span>
    </div>
  </div>
  <div class="pwd-matrix-shell<?php echo $matrixEmbedded ? ' is-flat' : ''; ?>" id="pwdMatrixShell">
    <div class="pwd-freeze-pane">
      <div class="pwd-freeze-head-wrap">
        <table class="table pwd-freeze-table<?php echo $matrixEmbedded ? ' is-flat' : ''; ?> align-middle mb-0 pwd-freeze-head-table">
          <thead id="pwdFreezeHead"></thead>
        </table>
      </div>
      <table class="table pwd-freeze-table<?php echo $matrixEmbedded ? ' is-flat' : ''; ?> align-middle mb-0 pwd-freeze-body-table">
        <tbody id="pwdFreezeBody"><tr><td colspan="3" class="pwd-loading">Memuat data...</td></tr></tbody>
      </table>
    </div>
    <div class="pwd-scroll-pane">
      <div class="pwd-scroll-head-wrap" id="pwdScrollHeadWrap">
        <table class="table pwd-scroll-table align-middle mb-0 pwd-scroll-head-table">
          <thead id="pwdScrollHead"></thead>
        </table>
      </div>
      <div class="pwd-table-wrap" id="pwdTableWrap">
        <table class="table pwd-scroll-table align-middle mb-0 pwd-scroll-body-table">
          <tbody id="pwdScrollBody"><tr><td colspan="999" class="pwd-loading">Memuat data...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="pwdDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Mutasi Harian</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="pwd-modal-card mb-3">
          <div class="fw-semibold" id="pwdModalTitle">-</div>
          <div class="small text-muted" id="pwdModalSubtitle">-</div>
        </div>
        <div id="pwdModalListWrap" class="table-responsive">
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
            <tbody id="pwdModalBody"><tr><td colspan="7" class="text-center text-muted py-3">Belum ada data.</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="pwdAdjustModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-uppercase text-muted fw-semibold">Adjustment Harian Gudang</div>
          <h5 class="modal-title mb-0">Penyesuaian Stok Langsung</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="pwdAdjustAlert" class="mb-2"></div>
        <div class="pwd-adjust-selected mb-3">
          <div class="fw-semibold" id="pwdAdjustTitle">-</div>
          <div class="small text-muted" id="pwdAdjustSubtitle">-</div>
          <div class="row g-2 mt-1 small">
            <div class="col-md-4"><div class="text-muted">Tanggal</div><div class="fw-semibold" id="pwdAdjustDateLabel">-</div></div>
            <div class="col-md-4"><div class="text-muted">Adj existing</div><div class="fw-semibold" id="pwdAdjustExistingLabel">0,00</div></div>
            <div class="col-md-4"><div class="text-muted">Akhir hari</div><div class="fw-semibold" id="pwdAdjustClosingLabel">0,00</div></div>
          </div>
        </div>
        <div class="pwd-adjust-help mb-3">
          <div class="fw-semibold mb-1">Pola input gudang</div>
          <div>Pilih satu jenis koreksi untuk profile yang dipilih. Qty diisi dalam pack atau satuan beli, dan HPP adjustment akan otomatis mengikuti HPP profile line tersebut.</div>
        </div>
        <form id="pwdAdjustForm" class="row g-2" autocomplete="off">
          <div class="col-md-6">
            <label class="form-label">Catatan Header</label>
            <input type="text" class="form-control" id="pwdAdjustHeaderNotes" placeholder="Opsional, misalnya koreksi opname shift pagi">
          </div>
          <div class="col-md-6">
            <label class="form-label">Catatan Line</label>
            <input type="text" class="form-control" id="pwdAdjustLineNote" placeholder="Opsional">
          </div>
          <div class="col-md-4">
            <label class="form-label">Jenis Koreksi</label>
            <select class="form-select" id="pwdAdjustAction">
              <option value="">Pilih salah satu...</option>
              <option value="SPOIL">Spoil</option>
              <option value="WASTE">Waste</option>
              <option value="MINUS">Minus</option>
              <option value="PLUS">Plus</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" id="pwdQtyLabel">Qty</label>
            <input type="number" class="form-control" id="pwdQtyInput" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label" id="pwdReasonLabel">Alasan</label>
            <select class="form-select" id="pwdReasonSelect">
              <option value="">Pilih jenis koreksi dulu</option>
            </select>
          </div>
          <div class="col-md-4 d-none" id="pwdAutoCostWrap">
            <label class="form-label">HPP Profile Otomatis</label>
            <input type="text" class="form-control" id="pwdAutoCostDisplay" readonly value="0">
          </div>
          <div class="col-md-4 d-none pwd-plus-only">
            <label class="form-label">Lot Masuk Manual</label>
            <input type="text" class="form-control" id="pwdInboundLotNo" placeholder="Opsional untuk adjustment +">
          </div>
          <div class="col-md-4 d-none pwd-plus-only">
            <label class="form-label">Exp Date Lot Masuk</label>
            <input type="date" class="form-control" id="pwdInboundExpiryDate">
          </div>
          <div class="col-12 d-none" id="pwdAutoCostHint">
            <div class="form-text">Untuk plus, HPP akan mengikuti profile line yang dipilih dari daily matrix. Input harga manual tidak diperlukan.</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <span class="small text-muted me-auto">Simpan DRAFT, lalu verifikasi password dan Post di halaman Adjustment.</span>
        <button type="button" class="btn btn-primary" id="pwdAdjustSubmit">Simpan &amp; Lanjut Verifikasi</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var matrixEmbedded = <?php echo $matrixEmbedded ? 'true' : 'false'; ?>;
  var matrixUrl = <?php echo json_encode((string)($matrix_url ?? site_url('inventory-warehouse-daily/matrix'))); ?>;
  var detailUrl = <?php echo json_encode((string)($detail_url ?? site_url('inventory-daily/cell-detail'))); ?>;
  var adjustmentStoreUrl = <?php echo json_encode($adjustmentStoreUrl); ?>;
  var adjustmentIndexUrl = <?php echo json_encode($adjustmentIndexUrl); ?>;
  var stockAdjustmentCsrfToken = <?php echo json_encode((string)($stock_adjustment_csrf_token ?? '')); ?>;
  var defaultMonth = <?php echo json_encode(substr($initialMonth, 0, 7)); ?>;
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

  var state = {
    month: defaultMonth,
    q: <?php echo json_encode($initialQ); ?>,
    date_from: <?php echo json_encode($initialDateFrom); ?>,
    date_to: <?php echo json_encode($initialDateTo); ?>,
    limit: <?php echo (int)$initialLimit; ?>,
    dates: [],
    groups: [],
    expanded: {}
  };

  var freezeHead = document.getElementById('pwdFreezeHead');
  var freezeBody = document.getElementById('pwdFreezeBody');
  var tableHead = document.getElementById('pwdScrollHead');
  var tableBody = document.getElementById('pwdScrollBody');
  var tableHeadWrap = document.getElementById('pwdScrollHeadWrap');
  var tableWrap = document.getElementById('pwdTableWrap');
  var matrixShell = document.getElementById('pwdMatrixShell');
  var modalEl = document.getElementById('pwdDetailModal');
  if (modalEl && modalEl.parentNode !== document.body) { document.body.appendChild(modalEl); }
  if (modalEl) { modalEl.style.zIndex = '2000'; }
  var modal = (modalEl && window.bootstrap && bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;
  var adjustModalEl = document.getElementById('pwdAdjustModal');
  if (adjustModalEl && adjustModalEl.parentNode !== document.body) { document.body.appendChild(adjustModalEl); }
  if (adjustModalEl) { adjustModalEl.style.zIndex = '2000'; }
  var adjustModal = (adjustModalEl && window.bootstrap && bootstrap.Modal) ? new bootstrap.Modal(adjustModalEl) : null;
  var adjustContext = null;
  var adjustBackdropEl = null;
  var adjustActionMeta = {
    SPOIL: { label: 'Spoil', reasonLabel: 'Alasan Spoil', reasonCategory: 'SPOILAGE' },
    WASTE: { label: 'Waste', reasonLabel: 'Alasan Waste', reasonCategory: 'WASTE' },
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

  function resolveQuickAdjustPackUnitCost(row, group, factor){
    var candidates = [
      Number((row.metrics && row.metrics.hpp) || 0) * factor,
      Number((row.metrics && row.metrics.unit_price_pack) || 0),
      Number((row.metrics && row.metrics.unit_price) || 0) * factor,
      Number((group && group.metrics && group.metrics.unit_price_pack) || 0),
      Number((group && group.metrics && group.metrics.unit_price) || 0) * factor,
      Number((group && group.metrics && group.metrics.hpp) || 0) * factor
    ];
    for (var i = 0; i < candidates.length; i += 1) {
      if (Number(candidates[i] || 0) > 0) {
        return Number(candidates[i] || 0);
      }
    }
    return 0;
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
    if (matrixEmbedded && freezeHeaderRow && freezeHeaderRow.children.length === 10) {
      var flatWidth = Array.prototype.reduce.call(freezeHeaderRow.children, function(sum, cell){ return sum + Math.ceil(cell.getBoundingClientRect().width); }, 0);
      document.documentElement.style.setProperty('--pwd-freeze-width', flatWidth + 'px');
      var flatMetricHead = tableHead.querySelector('th.pwd-submetric');
      if (flatMetricHead) document.documentElement.style.setProperty('--pwd-date-col', Math.ceil(flatMetricHead.getBoundingClientRect().width) + 'px');
      return;
    }
    if (!freezeHeaderRow || freezeHeaderRow.children.length < 3) { return; }

    var c1 = freezeHeaderRow.children[0];
    var c2 = freezeHeaderRow.children[1];
    var c3 = freezeHeaderRow.children[2];

    var w1 = Math.max(64, Math.ceil(c1.getBoundingClientRect().width));
    var w2 = Math.max(220, Math.ceil(c2.getBoundingClientRect().width));
    var w3 = Math.max(150, Math.ceil(c3.getBoundingClientRect().width));

    var rootStyle = document.documentElement.style;
    rootStyle.setProperty('--pwd-col-kind', w1 + 'px');
    rootStyle.setProperty('--pwd-col-item-profile', w2 + 'px');
    rootStyle.setProperty('--pwd-col-summary', w3 + 'px');
    var firstHeaderRow = tableHead.querySelector('tr');
    var firstHeaderHeight = firstHeaderRow ? Math.max(0, Math.ceil(firstHeaderRow.getBoundingClientRect().height)) : 0;
    if (firstHeaderHeight > 0) {
      rootStyle.setProperty('--pwd-header-row-1', firstHeaderHeight + 'px');
    }
    var metricHead = tableHead.querySelector('th.pwd-metric-cell');
    if (metricHead) {
      var metricWidth = Math.max(96, Math.ceil(metricHead.getBoundingClientRect().width));
      rootStyle.setProperty('--pwd-date-col', metricWidth + 'px');
    }
    rootStyle.setProperty('--pwd-left-1', '0px');
    rootStyle.setProperty('--pwd-left-2', w1 + 'px');
    rootStyle.setProperty('--pwd-left-3', (w1 + w2) + 'px');
  }

  function syncFlatHeaderHeight(){
    if (!matrixEmbedded) return;
    var freezeWrap = document.querySelector('.pwd-freeze-head-wrap');
    var scrollWrap = document.getElementById('pwdScrollHeadWrap');
    var scrollTable = scrollWrap ? scrollWrap.querySelector('table') : null;
    if (!freezeWrap || !scrollWrap || !scrollTable) return;
    var height = Math.ceil(scrollTable.getBoundingClientRect().height);
    if (height > 0) { freezeWrap.style.height = height + 'px'; scrollWrap.style.height = height + 'px'; }
  }

  function initFlatColumnResize(){
    if (!matrixEmbedded || !freezeHead) return;
    Array.prototype.forEach.call(freezeHead.querySelectorAll('th.pwd-flat-meta-cell'), function(cell, index){
      var key = 'pwd-flat-col-' + (index + 1);
      var saved = Number(window.localStorage.getItem(key) || 0);
      if (saved >= 60) document.documentElement.style.setProperty('--' + key, saved + 'px');
      var handle = cell.querySelector('.pwd-col-resizer');
      if (!handle || handle.dataset.ready === '1') return;
      handle.dataset.ready = '1';
      handle.addEventListener('pointerdown', function(event){
        event.preventDefault(); handle.setPointerCapture(event.pointerId); handle.classList.add('is-dragging');
        var startX = event.clientX, startWidth = cell.getBoundingClientRect().width;
        function move(moveEvent){
          var width = Math.max(60, Math.min(420, Math.round(startWidth + moveEvent.clientX - startX)));
          document.documentElement.style.setProperty('--' + key, width + 'px');
          window.localStorage.setItem(key, String(width)); syncStickyLayout();
        }
        function stop(){ handle.classList.remove('is-dragging'); handle.removeEventListener('pointermove', move); handle.removeEventListener('pointerup', stop); handle.removeEventListener('pointercancel', stop); }
        handle.addEventListener('pointermove', move); handle.addEventListener('pointerup', stop); handle.addEventListener('pointercancel', stop);
      });
    });
  }

  function syncStickyTopOffset(){
    var navbar = document.getElementById('layout-navbar') || document.querySelector('.layout-navbar');
    var topOffset = navbar ? Math.ceil(navbar.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--pwd-sticky-top', topOffset + 'px');
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
    if (matrixEmbedded) { return 0; }
    var rootStyle = getComputedStyle(document.documentElement);
    return (
      Number.parseFloat(rootStyle.getPropertyValue('--pwd-col-kind')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pwd-col-item-profile')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pwd-col-summary'))
    ) || 0;
  }

  function scrollToTodayColumn(){
    if (!tableWrap) { return; }
    var todayCell = tableHead.querySelector('.pwd-day-head.is-today');
    if (!todayCell) { return; }
    tableWrap.scrollLeft = Math.max(0, todayCell.offsetLeft - getStickyOffset() - 16);
    if (tableHeadWrap) {
      tableHeadWrap.scrollLeft = tableWrap.scrollLeft;
    }
  }

  function syncMonthWindowInputs(){
    var monthEl = document.getElementById('pwdMonth');
    var fromEl = document.getElementById('pwdDateFrom');
    var toEl = document.getElementById('pwdDateTo');
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
    state.month = document.getElementById('pwdMonth').value || '';
    syncMonthWindowInputs();
    state.q = document.getElementById('pwdQ').value || '';
    state.date_from = document.getElementById('pwdDateFrom').value || '';
    state.date_to = document.getElementById('pwdDateTo').value || '';
    state.limit = parseInt(document.getElementById('pwdLimit').value || '120', 10);
    if (!Number.isFinite(state.limit) || state.limit <= 0 || state.limit > 1000) {
      state.limit = 120;
    }
  }

  function clearFilters(){
    document.getElementById('pwdMonth').value = defaultMonth;
    document.getElementById('pwdQ').value = '';
    document.getElementById('pwdDateFrom').value = '';
    document.getElementById('pwdDateTo').value = '';
    document.getElementById('pwdLimit').value = '120';
    readFilters();
  }

  function buildQuery(){
    var p = new URLSearchParams();
    if (state.month) { p.set('month', state.month); }
    if (state.q) { p.set('q', state.q); }
    if (state.date_from) { p.set('date_from', state.date_from); }
    if (state.date_to) { p.set('date_to', state.date_to); }
    p.set('limit', String(state.limit));
    p.set('_ts', String(Date.now()));
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
    var box = document.getElementById('pwdActionMsg');
    if (!box) { return; }
    if (!text) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + esc(text) + '</div>';
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
    var box = document.getElementById('pwdAdjustAlert');
    if (!box) { return; }
    if (!text) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + esc(text) + '</div>';
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
    ['pwdQtyInput'].forEach(function(id){
      var el = document.getElementById(id);
      if (!el) { return; }
      el.value = '0';
    });
    var actionEl = document.getElementById('pwdAdjustAction');
    if (actionEl) { actionEl.value = ''; }
    document.getElementById('pwdQtyLabel').textContent = 'Qty';
    document.getElementById('pwdReasonLabel').textContent = 'Alasan';
    document.getElementById('pwdReasonSelect').innerHTML = '<option value="">Pilih jenis koreksi dulu</option>';
    document.getElementById('pwdAutoCostDisplay').value = '0';
    document.getElementById('pwdAutoCostWrap').classList.add('d-none');
    document.getElementById('pwdAutoCostHint').classList.add('d-none');
    Array.prototype.forEach.call(document.querySelectorAll('.pwd-plus-only'), function(el){ el.classList.add('d-none'); });
    ['pwdAdjustHeaderNotes', 'pwdAdjustLineNote', 'pwdInboundLotNo', 'pwdInboundExpiryDate'].forEach(function(id){
      var el = document.getElementById(id);
      if (el) { el.value = ''; }
    });
    showAdjustAlert(true, '');
  }

  function updateAdjustActionUi(){
    var action = String((document.getElementById('pwdAdjustAction').value || '')).toUpperCase();
    var meta = adjustActionMeta[action] || null;
    var reasonEl = document.getElementById('pwdReasonSelect');
    document.getElementById('pwdQtyInput').value = '0';
    if (!meta) {
      document.getElementById('pwdQtyLabel').textContent = 'Qty';
      document.getElementById('pwdReasonLabel').textContent = 'Alasan';
      reasonEl.innerHTML = '<option value="">Pilih jenis koreksi dulu</option>';
      document.getElementById('pwdAutoCostDisplay').value = '0';
      document.getElementById('pwdAutoCostWrap').classList.add('d-none');
      document.getElementById('pwdAutoCostHint').classList.add('d-none');
      Array.prototype.forEach.call(document.querySelectorAll('.pwd-plus-only'), function(el){ el.classList.add('d-none'); });
      return;
    }
    document.getElementById('pwdQtyLabel').textContent = meta.label + ' (' + ((adjustContext && adjustContext.buyUnitCode) || 'Qty') + ')';
    document.getElementById('pwdReasonLabel').textContent = meta.reasonLabel;
    fillReasonSelect('pwdReasonSelect', meta.reasonCategory);
    if (action === 'PLUS') {
      document.getElementById('pwdAutoCostDisplay').value = String(Number.isFinite(adjustContext && adjustContext.defaultUnitCostInput) ? adjustContext.defaultUnitCostInput : 0);
      document.getElementById('pwdAutoCostWrap').classList.remove('d-none');
      document.getElementById('pwdAutoCostHint').classList.remove('d-none');
      Array.prototype.forEach.call(document.querySelectorAll('.pwd-plus-only'), function(el){ el.classList.remove('d-none'); });
    } else {
      document.getElementById('pwdAutoCostDisplay').value = '0';
      document.getElementById('pwdAutoCostWrap').classList.add('d-none');
      document.getElementById('pwdAutoCostHint').classList.add('d-none');
      Array.prototype.forEach.call(document.querySelectorAll('.pwd-plus-only'), function(el){ el.classList.add('d-none'); });
    }
  }

  function openAdjust(groupIndex, profileIndex, dateText){
    var group = state.groups[groupIndex];
    var row = group && group.children ? group.children[profileIndex] : null;
    if (!group || !row || !dateText) {
      showMessage(false, 'Untuk adjustment langsung, pilih baris profile/item tunggal. Jika item masih grup, expand dulu.');
      return;
    }

    var day = (row.daily_pack && row.daily_pack[dateText]) ? row.daily_pack[dateText] : ((row.daily && row.daily[dateText]) ? row.daily[dateText] : null);
    var buyUnitCode = String(row.profile_buy_uom_code || '').trim();
    var contentUnitCode = String(row.profile_content_uom_code || '').trim();
    var factor = Number(row.profile_content_per_buy || 0);
    if (!Number.isFinite(factor) || factor <= 0) { factor = 1; }

    adjustContext = {
      date: dateText,
      group: group,
      row: row,
      factor: factor,
      day: day || { adjustment: 0, adjustment_pack: 0, closing: 0, closing_pack: 0 },
      defaultUnitCostInput: resolveQuickAdjustPackUnitCost(row, group, factor),
      buyUnitCode: buyUnitCode,
      contentUnitCode: contentUnitCode
    };

    resetAdjustForm();
    document.getElementById('pwdAdjustTitle').textContent = (group.object_name || '-') + ' | ' + (row.profile_name || '-');
    document.getElementById('pwdAdjustSubtitle').textContent = [
      group.object_code || '-',
      row.profile_brand || '-',
      row.profile_description || '-'
    ].join(' | ');
    document.getElementById('pwdAdjustDateLabel').textContent = dateText;
    document.getElementById('pwdAdjustExistingLabel').textContent = num(Number(adjustContext.day.adjustment || 0)) + (contentUnitCode ? (' ' + contentUnitCode) : '');
    document.getElementById('pwdAdjustClosingLabel').textContent = num(Number(adjustContext.day.closing || 0)) + (contentUnitCode ? (' ' + contentUnitCode) : '');
    document.getElementById('pwdAutoCostDisplay').value = String(Number.isFinite(adjustContext.defaultUnitCostInput) ? adjustContext.defaultUnitCostInput : 0);
    document.getElementById('pwdInboundExpiryDate').value = '';
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

    var action = String((document.getElementById('pwdAdjustAction').value || '')).toUpperCase();
    var qtyInput = Number(document.getElementById('pwdQtyInput').value || 0);
    var unitCostInput = Number(adjustContext.defaultUnitCostInput || 0);
    var factor = Number(adjustContext.factor || 1);
    if (!Number.isFinite(factor) || factor <= 0) { factor = 1; }

    if (!adjustActionMeta[action]) {
      throw new Error('Pilih dulu salah satu jenis koreksi: spoil, waste, minus, atau plus.');
    }
    if (!(qtyInput > 0)) {
      throw new Error('Qty koreksi harus lebih dari nol.');
    }
    var qtyWaste = 0;
    var qtySpoil = 0;
    var qtyVariance = 0;
    var qtyPlus = 0;
    if (action === 'WASTE') { qtyWaste = qtyInput; }
    if (action === 'SPOIL') { qtySpoil = qtyInput; }
    if (action === 'MINUS') { qtyVariance = qtyInput; }
    if (action === 'PLUS') { qtyPlus = qtyInput; }

    return {
      stock_scope: 'WAREHOUSE',
      adjustment_date: adjustContext.date,
      notes: document.getElementById('pwdAdjustHeaderNotes').value || '',
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
        profile_content_per_buy: factor,
        profile_buy_uom_code: String(adjustContext.row.profile_buy_uom_code || ''),
        profile_content_uom_code: String(adjustContext.row.profile_content_uom_code || ''),
        qty_waste_content: qtyWaste * factor,
        waste_reason_code: action === 'WASTE' ? (document.getElementById('pwdReasonSelect').value || 'other') : 'other',
        qty_spoil_content: qtySpoil * factor,
        spoil_reason_code: action === 'SPOIL' ? (document.getElementById('pwdReasonSelect').value || 'other') : 'other',
        qty_process_loss_content: 0,
        process_loss_reason_code: 'other',
        qty_variance_content: qtyVariance * factor,
        variance_reason_code: action === 'MINUS' ? (document.getElementById('pwdReasonSelect').value || 'other') : 'other',
        qty_adjustment_plus_content: qtyPlus * factor,
        adjustment_plus_reason_code: action === 'PLUS' ? (document.getElementById('pwdReasonSelect').value || 'other') : 'other',
        unit_cost: factor > 0 ? (unitCostInput / factor) : unitCostInput,
        inbound_lot_no: document.getElementById('pwdInboundLotNo').value || '',
        inbound_expiry_date: document.getElementById('pwdInboundExpiryDate').value || '',
        note: document.getElementById('pwdAdjustLineNote').value || ''
      }]
    };
  }

  function submitAdjust(){
    var submitBtn = document.getElementById('pwdAdjustSubmit');
    if (submitBtn.disabled) return;
    var savedResult = null;
    try {
      var payload = buildAdjustPayload();
      if (!/^[0-9a-f]{64}$/i.test(stockAdjustmentCsrfToken)) {
        throw new Error('Sesi adjustment belum siap. Muat ulang halaman sebelum menyimpan.');
      }
      showAdjustAlert(true, '');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Menyimpan draft...';
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
        window.location.assign(next.toString());
      }).catch(function(err){
        var msg = err && err.message ? err.message : 'Gagal memproses adjustment.';
        if (savedResult && savedResult.adjustment_no) {
          msg += ' Draft ' + savedResult.adjustment_no + ' sudah tersimpan.';
        }
        showAdjustAlert(false, msg);
      }).finally(function(){
        if (!savedResult) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Simpan & Lanjut Verifikasi';
        }
      });
    } catch (err) {
      showAdjustAlert(false, err && err.message ? err.message : 'Payload adjustment tidak valid.');
    }
  }

  var pwdActionEl = document.getElementById('pwdAdjustAction');
  if (pwdActionEl) {
    pwdActionEl.addEventListener('change', updateAdjustActionUi);
  }

  function objectLabel(row){
    var code = row.stock_domain === 'MATERIAL' ? (row.material_code || '') : (row.item_code || '');
    var name = row.stock_domain === 'MATERIAL' ? (row.material_name || '') : (row.item_name || '');
    if (!name && row.material_name) {
      name = row.material_name;
      code = row.material_code || code;
    }
    if (!name && row.item_name) {
      name = row.item_name;
      code = row.item_code || code;
    }
    return {
      name: name || '-',
      code: code || '-'
    };
  }

  function normalizeProfileDaily(rowDaily, dates){
    var source = rowDaily && typeof rowDaily === 'object' ? rowDaily : {};
    var normalized = {};
    var prevClosing = 0;
    var prevValue = 0;
    var initialized = false;

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
        // Stok akhir mengikuti closing dari monthly stock/backend. Jangan dihitung ulang
        // di UI karena adjustment/variance bisa sudah difold ke monthly closing.
        var rawClosing = Number(raw.closing || opening);
        var computedClosing = opening + inQty - outQty + adjQty;
        closing = rawClosing;
        if (!Object.prototype.hasOwnProperty.call(raw, 'closing')) {
          closing = computedClosing;
        }
        mutations = Number(raw.mutations || 0);
        totalValue = Number(raw.total_value || totalValue);
      }

      normalized[dateKey] = {
        opening: opening,
        in: inQty,
        out: outQty,
        adjustment: adjQty,
        closing: closing,
        mutations: mutations,
        total_value: totalValue
      };

      prevClosing = closing;
      prevValue = totalValue;
      initialized = true;
    });

    return normalized;
  }

  function calcMetrics(dailyByDate, contentPerBuy, dates){
    var inTotal = 0;
    var outTotal = 0;
    var adjTotal = 0;
    var openingContent = 0;
    var closingContent = 0;
    var totalValue = 0;
    var mutationTotal = 0;

    if (dates.length > 0) {
      openingContent = Number((dailyByDate[dates[0]] && dailyByDate[dates[0]].opening) || 0);
      closingContent = Number((dailyByDate[dates[dates.length - 1]] && dailyByDate[dates[dates.length - 1]].closing) || 0);
      totalValue = Number((dailyByDate[dates[dates.length - 1]] && dailyByDate[dates[dates.length - 1]].total_value) || 0);
    }

    dates.forEach(function(dateKey){
      var day = dailyByDate[dateKey] || {};
      inTotal += Number(day.in || 0);
      outTotal += Number(day.out || 0);
      adjTotal += Number(day.adjustment || 0);
      mutationTotal += Number(day.mutations || 0);
    });

    var packSize = Number(contentPerBuy || 0);
    var openingPack = packSize > 0 ? openingContent / packSize : 0;
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

  function convertDailyToPack(dailyByDate, contentPerBuy, dates){
    var map = {};
    var packSize = Number(contentPerBuy || 0);
    if (!Number.isFinite(packSize) || packSize <= 0) {
      packSize = 1;
    }
    dates.forEach(function(dateKey){
      var day = dailyByDate[dateKey] || {};
      map[dateKey] = {
        opening: Number(day.opening || 0) / packSize,
        in: Number(day.in || 0) / packSize,
        out: Number(day.out || 0) / packSize,
        adjustment: Number(day.adjustment || 0) / packSize,
        closing: Number(day.closing || 0) / packSize,
        mutations: Number(day.mutations || 0),
        total_value: Number(day.total_value || 0)
      };
    });
    return map;
  }

  function profileDedupKey(child){
    var profileKey = String(child.profile_key || '').trim().toUpperCase();
    if (profileKey) {
      return [
        String(child.stock_domain || '').toUpperCase(),
        Number(child.item_id || 0),
        Number(child.material_id || 0),
        Number(child.buy_uom_id || 0),
        Number(child.content_uom_id || 0),
        profileKey
      ].join('|');
    }
    return [
      String(child.stock_domain || '').toUpperCase(),
      Number(child.item_id || 0),
      Number(child.material_id || 0),
      Number(child.buy_uom_id || 0),
      Number(child.content_uom_id || 0),
      String(child.profile_name || '').trim().toUpperCase(),
      String(child.profile_brand || '').trim().toUpperCase(),
      String(child.profile_description || '').trim().toUpperCase()
    ].join('|');
  }

  function dailyEntryScore(entry){
    if (!entry || typeof entry !== 'object') { return -1; }
    return Math.abs(Number(entry.opening || 0))
      + Math.abs(Number(entry.in || 0))
      + Math.abs(Number(entry.out || 0))
      + Math.abs(Number(entry.adjustment || 0))
      + Math.abs(Number(entry.closing || 0))
      + Math.abs(Number(entry.total_value || 0))
      + Math.abs(Number(entry.mutations || 0));
  }

  function mergeDailyEntry(existing, incoming){
    var left = existing && typeof existing === 'object' ? existing : null;
    var right = incoming && typeof incoming === 'object' ? incoming : null;
    if (!left) { return right || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0, mutations: 0, total_value: 0 }; }
    if (!right) { return left; }

    var leftScore = dailyEntryScore(left);
    var rightScore = dailyEntryScore(right);
    if (rightScore > leftScore + 0.0001) { return right; }
    if (leftScore > rightScore + 0.0001) { return left; }

    return {
      opening: Math.abs(Number(right.opening || 0)) > Math.abs(Number(left.opening || 0)) ? Number(right.opening || 0) : Number(left.opening || 0),
      in: Math.abs(Number(right.in || 0)) > Math.abs(Number(left.in || 0)) ? Number(right.in || 0) : Number(left.in || 0),
      out: Math.abs(Number(right.out || 0)) > Math.abs(Number(left.out || 0)) ? Number(right.out || 0) : Number(left.out || 0),
      adjustment: Math.abs(Number(right.adjustment || 0)) > Math.abs(Number(left.adjustment || 0)) ? Number(right.adjustment || 0) : Number(left.adjustment || 0),
      closing: Math.abs(Number(right.closing || 0)) > Math.abs(Number(left.closing || 0)) ? Number(right.closing || 0) : Number(left.closing || 0),
      mutations: Math.abs(Number(right.mutations || 0)) > Math.abs(Number(left.mutations || 0)) ? Number(right.mutations || 0) : Number(left.mutations || 0),
      total_value: Math.abs(Number(right.total_value || 0)) > Math.abs(Number(left.total_value || 0)) ? Number(right.total_value || 0) : Number(left.total_value || 0)
    };
  }

  function dedupeChildren(children, dates){
    var map = {};
    var order = [];

    (children || []).forEach(function(child){
      var key = profileDedupKey(child);
      if (!map[key]) {
        map[key] = {
          stock_domain: String(child.stock_domain || ''),
          item_id: Number(child.item_id || 0),
          material_id: Number(child.material_id || 0),
          buy_uom_id: Number(child.buy_uom_id || 0),
          content_uom_id: Number(child.content_uom_id || 0),
          profile_key: String(child.profile_key || ''),
          profile_name: String(child.profile_name || ''),
          profile_brand: String(child.profile_brand || ''),
          profile_description: String(child.profile_description || ''),
          profile_expired_date: String(child.profile_expired_date || ''),
          profile_content_per_buy: Number(child.profile_content_per_buy || 0),
          profile_buy_uom_code: String(child.profile_buy_uom_code || ''),
          profile_content_uom_code: String(child.profile_content_uom_code || ''),
          daily: {},
          daily_pack: {},
          metrics: null
        };
        order.push(key);
      }

      var target = map[key];
      if (!target.profile_name && child.profile_name) { target.profile_name = String(child.profile_name || ''); }
      if (!target.profile_brand && child.profile_brand) { target.profile_brand = String(child.profile_brand || ''); }
      if (!target.profile_description && child.profile_description) { target.profile_description = String(child.profile_description || ''); }
      if (!target.profile_expired_date && child.profile_expired_date) { target.profile_expired_date = String(child.profile_expired_date || ''); }
      if (!(Number(target.profile_content_per_buy || 0) > 0) && Number(child.profile_content_per_buy || 0) > 0) {
        target.profile_content_per_buy = Number(child.profile_content_per_buy || 0);
      }
      if (!target.profile_buy_uom_code && child.profile_buy_uom_code) { target.profile_buy_uom_code = String(child.profile_buy_uom_code || ''); }
      if (!target.profile_content_uom_code && child.profile_content_uom_code) { target.profile_content_uom_code = String(child.profile_content_uom_code || ''); }

      dates.forEach(function(dateKey){
        target.daily[dateKey] = mergeDailyEntry(target.daily[dateKey], child.daily && child.daily[dateKey] ? child.daily[dateKey] : null);
      });
    });

    return order.map(function(key){
      var child = map[key];
      child.daily_pack = convertDailyToPack(child.daily, Number(child.profile_content_per_buy || 0), dates);
      child.metrics = calcMetrics(child.daily, Number(child.profile_content_per_buy || 0), dates);
      return child;
    });
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
      var key = objectKey;
      if (!map[key]) {
        var obj = objectLabel(row);
        map[key] = {
          key: key,
          stock_domain: String(row.stock_domain || ''),
          item_id: Number(row.item_id || 0),
          material_id: Number(row.material_id || 0),
          item_code: String(row.item_code || ''),
          item_name: String(row.item_name || ''),
          material_code: String(row.material_code || ''),
          material_name: String(row.material_name || ''),
          category_name: String(row.category_name || ''),
          object_name: obj.name,
          object_code: obj.code,
          children: [],
          daily: {},
          metrics: null
        };
        order.push(key);
      }

      var profileDaily = normalizeProfileDaily(row.daily || {}, dates);
      var profileMetrics = calcMetrics(profileDaily, Number(row.profile_content_per_buy || 0), dates);
      var profileDailyPack = convertDailyToPack(profileDaily, Number(row.profile_content_per_buy || 0), dates);

      map[key].children.push({
        stock_domain: String(row.stock_domain || ''),
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
        daily: profileDaily,
        daily_pack: profileDailyPack,
        metrics: profileMetrics
      });
    });

    order.forEach(function(key){
      var group = map[key];
      group.children = dedupeChildren(group.children, dates);
      var summaryDaily = {};
      var summaryDailyPack = {};
      dates.forEach(function(dateKey){
        summaryDaily[dateKey] = { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0, mutations: 0, total_value: 0 };
        summaryDailyPack[dateKey] = { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0, mutations: 0, total_value: 0 };
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
        hpp: 0
      };

      group.children.forEach(function(child){
        dates.forEach(function(dateKey){
          var d = child.daily[dateKey] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0, mutations: 0, total_value: 0 };
          var dp = child.daily_pack[dateKey] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0, mutations: 0, total_value: 0 };
          summaryDaily[dateKey].opening += Number(d.opening || 0);
          summaryDaily[dateKey].in += Number(d.in || 0);
          summaryDaily[dateKey].out += Number(d.out || 0);
          summaryDaily[dateKey].adjustment += Number(d.adjustment || 0);
          summaryDaily[dateKey].closing += Number(d.closing || 0);
          summaryDaily[dateKey].mutations += Number(d.mutations || 0);
          summaryDaily[dateKey].total_value += Number(d.total_value || 0);
          summaryDailyPack[dateKey].opening += Number(dp.opening || 0);
          summaryDailyPack[dateKey].in += Number(dp.in || 0);
          summaryDailyPack[dateKey].out += Number(dp.out || 0);
          summaryDailyPack[dateKey].adjustment += Number(dp.adjustment || 0);
          summaryDailyPack[dateKey].closing += Number(dp.closing || 0);
          summaryDailyPack[dateKey].mutations += Number(dp.mutations || 0);
          summaryDailyPack[dateKey].total_value += Number(dp.total_value || 0);
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
      });

      if (agg.closing_content !== 0) {
        agg.hpp = agg.total_value / agg.closing_content;
      } else if (group.children.length > 0) {
        var sumHpp = 0;
        group.children.forEach(function(child){ sumHpp += Number(child.metrics.hpp || 0); });
        agg.hpp = sumHpp / group.children.length;
      }

      group.daily = summaryDaily;
      group.daily_pack = summaryDailyPack;
      group.metrics = agg;
    });

    return order.map(function(key){ return map[key]; });
  }

  function calcStats(groups){
    var profileCount = 0;
    var itemSet = {};
    var materialSet = {};
    var totalValue = 0;

    (groups || []).forEach(function(group){
      profileCount += (group.children || []).length;
      if (group.item_id) { itemSet[String(group.item_id)] = true; }
      if (group.material_id) { materialSet[String(group.material_id)] = true; }
      totalValue += Number((group.metrics && group.metrics.total_value) || 0);
    });

    document.getElementById('pwdStatProfiles').textContent = profileCount.toLocaleString('id-ID');
    document.getElementById('pwdStatItems').textContent = Object.keys(itemSet).length.toLocaleString('id-ID');
    document.getElementById('pwdStatMaterials').textContent = Object.keys(materialSet).length.toLocaleString('id-ID');
    document.getElementById('pwdStatValue').textContent = money(totalValue);
  }

  function summaryCardHtml(metrics){
    return '' +
      '<div class="pwd-summary-card">' +
        '<div class="pwd-summary-head">' +
          '<span class="pwd-summary-title">Nilai Sisa</span>' +
          '<div class="pwd-summary-amount">' + esc(money(metrics.total_value || 0)) + '</div>' +
        '</div>' +
        '<div class="pwd-summary-grid">' +
          '<div class="pwd-summary-metric"><span class="label">Awal Pack</span><strong>' + esc(num(metrics.opening_pack || 0)) + '</strong></div>' +
          '<div class="pwd-summary-metric"><span class="label">Akhir Pack</span><strong>' + esc(num(metrics.closing_pack || 0)) + '</strong></div>' +
          '<div class="pwd-summary-metric"><span class="label">Awal Isi</span><strong>' + esc(num(metrics.opening_content || 0)) + '</strong></div>' +
          '<div class="pwd-summary-metric"><span class="label">Akhir Isi</span><strong>' + esc(num(metrics.closing_content || 0)) + '</strong></div>' +
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
      return '<tr>' + ['Kategori','Tujuan','Jenis','Nama Material / Item','Merk','Deskripsi Profil','Kedaluwarsa','Satuan Isi','Satuan Beli','Isi / Beli'].map(function(label){
        return '<th class="pwd-flat-meta-cell">' + label + '<span class="pwd-col-resizer" aria-hidden="true"></span></th>';
      }).join('') + '</tr>';
    }
    return '' +
      '<tr>' +
        '<th class="pwd-freeze-col-1"' + (matrixEmbedded ? ' rowspan="2"' : '') + '>Jenis</th>' +
        '<th class="pwd-freeze-col-2"' + (matrixEmbedded ? ' rowspan="2"' : '') + '>Item / Profil</th>' +
        '<th class="pwd-freeze-col-3"' + (matrixEmbedded ? ' rowspan="2"' : '') + '>Ringkasan</th>' +
      '</tr>' +
      '';
  }

  function headerHtml(){
    if (matrixEmbedded) {
      var metrics = [
        { key: 'opening', label: 'Awal' }, { key: 'in', label: 'In' },
        { key: 'out', label: 'Out' }, { key: 'adjustment', label: 'Adj' },
        { key: 'closing', label: 'Akhir' }
      ];
      return '<tr>' + state.dates.map(function(dateText, dateIndex){
        var cls = 'pwd-day-head' + (isToday(dateText) ? ' is-today' : '');
        var bandClass = dateIndex % 2 === 0 ? 'pwd-date-band-a' : 'pwd-date-band-b';
        return '<th colspan="5" class="' + cls + ' ' + bandClass + ' pwd-day-group"><div class="pwd-headcard">'
          + '<span class="day">' + esc(String(dateText).slice(-2)) + '</span><span class="weekday">' + esc(weekdayName(dateText)) + '</span>'
          + '<span class="full-date">' + esc(dateText) + '</span>' + (isToday(dateText) ? '<span class="today-tag">Hari ini</span>' : '') + '</div></th>';
      }).join('') + '</tr><tr class="pwd-metric-subhead">' + state.dates.map(function(dateText, dateIndex){
        var bandClass = dateIndex % 2 === 0 ? 'pwd-date-band-a' : 'pwd-date-band-b';
        return metrics.map(function(metric, metricIndex){
          return '<th class="pwd-submetric ' + bandClass + (isToday(dateText) ? ' is-today' : '') + (metricIndex === 0 ? ' pwd-day-start' : '') + (metricIndex === 4 ? ' pwd-day-end' : '') + '">' + metric.label + '</th>';
        }).join('');
      }).join('') + '</tr>';
    }
    return '<tr>' + state.dates.map(function(dateText, dateIndex){
      var cls = 'pwd-day-head' + (isToday(dateText) ? ' is-today' : '');
      var bandClass = dateIndex % 2 === 0 ? 'pwd-date-band-a' : 'pwd-date-band-b';
      return '<th class="' + cls + ' ' + bandClass + ' pwd-metric-cell">'
        + '<div class="pwd-headcard">'
        +   '<div class="day">' + esc(String(dateText).slice(-2)) + '</div>'
        +   '<div class="weekday">' + esc(weekdayName(dateText)) + '</div>'
        +   '<div class="full-date">' + esc(dateText) + '</div>'
        +   (isToday(dateText) ? '<span class="today-tag">Hari ini</span>' : '')
        + '</div>'
        + '</th>';
    }).join('') + '</tr>';
  }

  function dayCells(dailyContentMap, dailyPackMap, groupIndex, profileIndex){
    var html = '';
    state.dates.forEach(function(dateText, dateIndex){
      var dayContent = dailyContentMap[dateText] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0 };
      var dayPack = dailyPackMap[dateText] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0 };
      var todayCls = isToday(dateText) ? ' is-today' : '';
      var bandClass = dateIndex % 2 === 0 ? ' pwd-date-band-a' : ' pwd-date-band-b';
      var items = [
        { key: 'opening', cls: 'pwd-metric-open', label: 'Awal' },
        { key: 'in', cls: 'pwd-metric-in', label: 'In' },
        { key: 'out', cls: 'pwd-metric-out', label: 'Out' },
        { key: 'adjustment', cls: 'pwd-metric-adj', label: 'Adj' },
        { key: 'closing', cls: 'pwd-metric-close', label: 'Akhir' }
      ];
      var rowsHtml = items.map(function(item){
        var valueContent = Number(dayContent[item.key] || 0);
        var valuePack = Number(dayPack[item.key] || 0);
        var valueText = '<span class="pwd-cell-pack">' + esc(num(valuePack)) + '</span><span class="pwd-cell-content">' + esc(num(valueContent)) + '</span>';
        var contentHtml = '';
        if (item.key === 'adjustment') {
          var detailHtml = Math.abs(valueContent) > 0.000001
            ? '<button type="button" class="pwd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>'
            : '<span class="pwd-cell-btn is-static ' + item.cls + '">' + valueText + '</span>';
          contentHtml = '<div class="pwd-cell-action-wrap">'
            + detailHtml
            + '<button type="button" class="pwd-cell-adjust-trigger" data-action="adjust" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '" title="Adjustment langsung" aria-label="Adjustment langsung"><i class="ri ri-edit-line" aria-hidden="true"></i></button>'
            + '</div>';
        } else if (Math.abs(valueContent) > 0.000001) {
          contentHtml = '<button type="button" class="pwd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>';
        } else {
          contentHtml = '<span class="pwd-cell-btn is-static ' + item.cls + '">' + valueText + '</span>';
        }
        return '<div class="pwd-metric-row ' + item.cls + '">'
          + '<div class="pwd-metric-main"><span class="pwd-metric-label">' + item.label + '</span></div>'
          + '<div class="pwd-metric-value-wrap">' + contentHtml + '</div>'
          + '</div>';
      }).join('');
      if (matrixEmbedded) {
        html += items.map(function(item, metricIndex){
          var valueContent = Number(dayContent[item.key] || 0);
          var valuePack = Number(dayPack[item.key] || 0);
          var valueText = '<span class="pwd-cell-pack">' + esc(num(valuePack)) + '</span><small class="pwd-cell-content">' + esc(num(valueContent)) + '</small>';
          var contentHtml;
          if (item.key === 'adjustment') {
            var detail = '<button type="button" class="pwd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>';
            contentHtml = '<div class="pwd-cell-action-wrap">' + detail + '<button type="button" class="pwd-cell-adjust-trigger" data-action="adjust" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '" title="Adjustment langsung" aria-label="Adjustment langsung"><i class="ri ri-edit-line"></i></button></div>';
          } else {
            contentHtml = '<button type="button" class="pwd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '" aria-label="Lihat rincian ' + item.label + '">' + valueText + '</button>';
          }
          return '<td class="pwd-metric-cell pwd-flat-metric ' + item.cls + todayCls + bandClass + (metricIndex === 0 ? ' pwd-day-start' : '') + (metricIndex === 4 ? ' pwd-day-end' : '') + '">' + contentHtml + '</td>';
        }).join('');
      } else {
        html += '<td class="pwd-metric-cell' + todayCls + bandClass + ' pwd-day-start pwd-day-end"><div class="pwd-date-card">' + rowsHtml + '</div></td>';
      }
    });
    return html;
  }

  function isExpandable(group){
    return Array.isArray(group.children) && group.children.length > 1;
  }

  function isExpanded(group){
    return isExpandable(group) ? !!state.expanded[group.key] : true;
  }

  function buildWarehouseProfileAuditUrl(row){
    var params = new URLSearchParams();
    params.set('scope', 'WAREHOUSE');
    var searchToken = String(row.profile_key || row.item_code || row.material_code || row.item_name || row.material_name || '').trim();
    if (searchToken) {
      params.set('q', searchToken);
    }
    if (String(row.profile_key || '').trim() !== '') {
      params.set('profile_key', String(row.profile_key || '').trim());
    }
    if (Number(row.item_id || 0) > 0) {
      params.set('item_id', String(Number(row.item_id || 0)));
    }
    if (Number(row.material_id || 0) > 0) {
      params.set('material_id', String(Number(row.material_id || 0)));
    }
    var query = params.toString();
    return <?php echo json_encode($profileAuditBaseUrl); ?> + (query ? ('?' + query) : '');
  }

  function freezeGroupRowHtml(group){
    if (matrixEmbedded) return '<tr class="pwd-group-row' + (Number((group.metrics && group.metrics.closing_content) || 0) <= 0 ? ' pwd-stock-alert' : '') + '">' + flatWarehouseMetaCells(group, (group.children || [])[0]) + '</tr>';
    var kind = group.stock_domain === 'MATERIAL' ? 'Bahan Baku' : 'Item';
    var expandable = isExpandable(group);
    var expanded = isExpanded(group);
    var singleProfile = (!expandable && Array.isArray(group.children) && group.children.length === 1) ? group.children[0] : null;
    var toggleHtml = expandable
      ? '<button type="button" class="pwd-toggle-arrow" title="Expand/Collapse" data-action="toggle-group" data-group-key="' + esc(group.key) + '">' + (expanded ? '&#9662;' : '&#9656;') + '</button>'
      : '<span class="pwd-toggle-static" title="Baris tunggal">1</span>';
    var profileHtml = ''
      + '<div class="pwd-profile-stack is-parent">'
      +   '<div class="pwd-profile-meta is-parent">' + (expandable ? (esc(String((group.children || []).length)) + ' profil aktif') : 'Profil tunggal') + '</div>'
      +   '<div class="pwd-code">' + (expandable ? 'Expand untuk lihat rincian profil.' : 'Detail profil langsung ditampilkan.') + '</div>'
      + '</div>';
    if (singleProfile) {
      var singleProfileText = singleProfile.profile_name || '-';
      var singleDetail = [singleProfile.profile_brand || '-', singleProfile.profile_description || '-', singleProfile.profile_expired_date ? ('Exp ' + singleProfile.profile_expired_date) : 'Tanpa expiry'].join(' | ');
      var singleUnitInfo = num(singleProfile.profile_content_per_buy || 0) + ' ' + (singleProfile.profile_content_uom_code || '') + ' / ' + (singleProfile.profile_buy_uom_code || '-');
      profileHtml = ''
        + '<div class="pwd-profile-stack is-parent">'
        +   '<div class="pwd-profile-line">' + esc(singleProfileText) + '</div>'
        +   '<div class="pwd-profile-line">' + esc(singleDetail) + '</div>'
        +   '<div class="pwd-profile-meta is-parent">' + esc(singleUnitInfo) + '</div>'
        +   '<div class="pwd-profile-meta is-parent">Harga satuan ' + esc(money((singleProfile.metrics && singleProfile.metrics.unit_price) || 0)) + '</div>'
        +   '<div class="pwd-profile-meta is-parent">Harga / pack ' + esc(money((singleProfile.metrics && singleProfile.metrics.unit_price_pack) || 0)) + '</div>'
      +   '<div class="pwd-profile-unit"><a href="' + esc(buildWarehouseProfileAuditUrl(singleProfile)) + '">Audit Profil</a></div>'
        + '</div>';
    }
    var itemProfileHtml = ''
      + '<div class="pwd-item-profile-stack">'
      +   '<div class="pwd-name-stack"><div class="pwd-name">' + esc(group.object_name || '-') + '</div><div class="pwd-code">' + esc(group.object_code || '-') + '</div></div>'
      +   profileHtml
      + '</div>';
    var rowClass = expandable ? 'pwd-group-row pwd-group-expandable' : 'pwd-group-row pwd-group-single';
    if (Number((group.metrics && (group.metrics.closing_content ?? group.metrics.closing)) || 0) <= 0) { rowClass += ' pwd-stock-alert'; }
    return '' +
      '<tr class="' + rowClass + '">' +
        '<td class="pwd-freeze-col-1">' + toggleHtml + '<span class="pwd-kind-pill">' + esc(kind) + '</span></td>' +
        '<td class="pwd-freeze-col-2">' + itemProfileHtml + '</td>' +
        '<td class="pwd-freeze-col-3">' + summaryParentHtml(group.metrics || {}) + '</td>' +
      '</tr>';
  }

  function groupRowHtml(group, groupIndex){
    var singleProfile = (!isExpandable(group) && Array.isArray(group.children) && group.children.length === 1) ? group.children[0] : null;
    var parentProfileIndex = singleProfile ? 0 : -1;
    var rowClass = isExpandable(group) ? 'pwd-group-row pwd-group-expandable' : 'pwd-group-row pwd-group-single';
    if (Number((group.metrics && (group.metrics.closing_content ?? group.metrics.closing)) || 0) <= 0) { rowClass += ' pwd-stock-alert'; }
    return '<tr class="' + rowClass + '">' + dayCells(group.daily || {}, group.daily_pack || {}, groupIndex, parentProfileIndex) + '</tr>';
  }

  function freezeProfileRowHtml(group, profile){
    if (matrixEmbedded) return '<tr class="pwd-child-row' + (Number((profile.metrics && profile.metrics.closing_content) || 0) <= 0 ? ' pwd-stock-alert' : '') + '">' + flatWarehouseMetaCells(group, profile) + '</tr>';
    var profileText = profile.profile_name || '-';
    var detail = [profile.profile_brand || '-', profile.profile_description || '-', profile.profile_expired_date ? ('Exp ' + profile.profile_expired_date) : 'Tanpa expiry'].join(' | ');
    var unitInfo = num(profile.profile_content_per_buy || 0) + ' ' + (profile.profile_content_uom_code || '') + ' / ' + (profile.profile_buy_uom_code || '-');
    var itemProfileHtml = ''
      + '<div class="pwd-item-profile-stack is-child">'
      +   '<div class="pwd-code">Profil Item</div>'
      +   '<div class="pwd-profile-stack is-child">'
      +     '<div class="pwd-profile-line">' + esc(profileText) + '</div>'
      +     '<div class="pwd-profile-line">' + esc(detail) + '</div>'
      +     '<div class="pwd-profile-meta is-child">' + esc(unitInfo) + '</div>'
      +     '<div class="pwd-profile-meta is-child">Harga satuan ' + esc(money((profile.metrics && profile.metrics.unit_price) || 0)) + '</div>'
      +     '<div class="pwd-profile-meta is-child">Harga / pack ' + esc(money((profile.metrics && profile.metrics.unit_price_pack) || 0)) + '</div>'
      +     '<div class="pwd-profile-unit"><a href="' + esc(buildWarehouseProfileAuditUrl(profile)) + '">Audit Profil</a></div>'
      +   '</div>'
      + '</div>';

    return '' +
      '<tr class="pwd-child-row' + (Number((profile.metrics && (profile.metrics.closing_content ?? profile.metrics.closing)) || 0) <= 0 ? ' pwd-stock-alert' : '') + '">' +
        '<td class="pwd-freeze-col-1"></td>' +
        '<td class="pwd-freeze-col-2">' + itemProfileHtml + '</td>' +
        '<td class="pwd-freeze-col-3">' + summaryChildHtml(profile.metrics || {}) + '</td>' +
      '</tr>';
  }

  function flatWarehouseMetaCells(group, profile){
    var row = profile || {};
    var category = group.category_name || (String(group.stock_domain || '').toUpperCase() === 'MATERIAL' ? 'BAHAN BAKU' : 'ITEM');
    var values = [category, 'GUDANG', '-', group.object_name || group.item_name || group.material_name || '-', row.profile_brand || '-', [row.profile_name || '', row.profile_description || ''].filter(Boolean).join(' / ') || '-', row.profile_expired_date || '-', row.profile_content_uom_code || '-', row.profile_buy_uom_code || '-', Number(row.profile_content_per_buy || 0) > 0 ? num(row.profile_content_per_buy) : '-'];
    return values.map(function(value){ return '<td class="pwd-flat-meta-cell" title="' + esc(value) + '">' + esc(value) + '</td>'; }).join('');
  }

  function profileRowHtml(group, groupIndex, profile, profileIndex){
    var closing = Number((profile.metrics && (profile.metrics.closing_content ?? profile.metrics.closing)) || 0);
    return '<tr class="pwd-child-row' + (closing <= 0 ? ' pwd-stock-alert' : '') + '">' + dayCells(profile.daily || {}, profile.daily_pack || {}, groupIndex, profileIndex) + '</tr>';
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

  function render(options){
    var opts = options || {};
    var keepScroll = !!opts.keepScroll;
    var focusToday = !!opts.focusToday;
    var previousScrollLeft = tableWrap ? tableWrap.scrollLeft : 0;
    freezeHead.innerHTML = freezeHeaderHtml();
    tableHead.innerHTML = headerHtml();
    initFlatColumnResize();
    syncStickyLayout();

    if (!state.groups.length) {
      freezeBody.innerHTML = '<tr><td colspan="3" class="pwd-empty">Belum ada data untuk filter ini.</td></tr>';
      tableBody.innerHTML = '<tr><td colspan="' + Math.max(1, state.dates.length * (matrixEmbedded ? 5 : 1)) + '" class="pwd-empty"></td></tr>';
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
        group.children.forEach(function(profile, profileIndex){
          freezeHtml += freezeProfileRowHtml(group, profile);
          html += profileRowHtml(group, groupIndex, profile, profileIndex);
        });
      } else {
        freezeHtml += freezeGroupRowHtml(group);
        html += groupRowHtml(group, groupIndex);
      }
      if (!matrixEmbedded && isExpandable(group) && isExpanded(group)) {
        (group.children || []).forEach(function(profile, profileIndex){
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
        scrollToTodayColumn();
      } else if (keepScroll) {
        tableWrap.scrollLeft = previousScrollLeft;
        if (tableHeadWrap) {
          tableHeadWrap.scrollLeft = previousScrollLeft;
        }
      }
    });
  }

  function renderDetailRows(rows){
    var body = document.getElementById('pwdModalBody');
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

    var title = group.object_name || '-';
    var subtitleParts = ['Tanggal: ' + dateText];
    if (row) {
      subtitleParts.push('Profil: ' + (row.profile_name || '-'));
    } else {
      subtitleParts.push('Ringkasan Item');
    }

    document.getElementById('pwdModalTitle').textContent = title;
    document.getElementById('pwdModalSubtitle').textContent = subtitleParts.join(' | ');
    document.getElementById('pwdModalBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Memuat detail mutasi...</td></tr>';

    if (modal) {
      modal.show();
    } else {
      openModalFallback();
    }

    var p = new URLSearchParams();
    p.set('scope', 'WAREHOUSE');
    p.set('movement_date', dateText);
    p.set('stock_domain', String(group.stock_domain || ''));
    p.set('item_id', String(group.item_id || 0));
    p.set('material_id', String(group.material_id || 0));

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
        document.getElementById('pwdModalBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">' + esc(err && err.message ? err.message : 'Gagal memuat detail mutasi.') + '</td></tr>';
      });
  }

  function loadData(){
    tableBody.innerHTML = '<tr><td colspan="999" class="pwd-loading">Memuat data...</td></tr>';
    showMessage(true, '');

    fetch(matrixUrl + '?' + buildQuery(), { cache: 'no-store', headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' } })
      .then(parseJson)
      .then(function(result){
        var data = (result && result.data) ? result.data : {};
        state.dates = Array.isArray(data.dates) ? data.dates : [];
        state.groups = buildGroups(Array.isArray(data.rows) ? data.rows : [], state.dates);
        calcStats(state.groups);
        render({ focusToday: true });
      })
      .catch(function(err){
        freezeHead.innerHTML = '';
        tableHead.innerHTML = '';
        freezeBody.innerHTML = '<tr><td colspan="3" class="pwd-empty">Gagal memuat matrix harian.</td></tr>';
        tableBody.innerHTML = '<tr><td colspan="1" class="pwd-empty"></td></tr>';
        showMessage(false, err && err.message ? err.message : 'Terjadi kesalahan saat memuat data.');
      });
  }

  document.getElementById('pwdApply').addEventListener('click', function(){
    readFilters();
    loadData();
  });

  document.getElementById('pwdClear').addEventListener('click', function(){
    clearFilters();
    loadData();
  });

  document.getElementById('pwdQ').addEventListener('keydown', function(ev){
    if (ev.key === 'Enter') {
      ev.preventDefault();
      readFilters();
      loadData();
    }
  });

  document.getElementById('pwdMonth').addEventListener('change', syncMonthWindowInputs);

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

  document.getElementById('pwdAdjustSubmit').addEventListener('click', submitAdjust);

  if (tableWrap && tableHeadWrap) {
    tableWrap.addEventListener('scroll', function(){
      tableHeadWrap.scrollLeft = tableWrap.scrollLeft;
    }, { passive: true });
  }

  window.addEventListener('resize', function(){
    syncStickyLayout();
    syncPaneRowHeights();
  });

  readFilters();
  loadData();
})();
</script>
