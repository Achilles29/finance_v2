<?php
$reportCategories = (array)($report_categories ?? []);
$canClassify = !empty($can_classify_mutation) && !empty($category_schema_ready);
$storeUrl = site_url('finance/mutations/store');
$baseUrl  = site_url('finance/mutations');
$pg       = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$summary  = (array)($summary ?? []);
$accountBreakdown = (array)($account_breakdown ?? []);
$scope    = (string)($scope ?? 'all');
$filterMutationType = strtoupper((string)($filter_mutation_type ?? 'ALL'));
$filterModule = strtoupper((string)($filter_module ?? 'ALL'));
$purchaseMutationCsrfToken = (string)($purchase_mutation_csrf_token ?? '');
$accountAsOfSnapshot = (array)($account_as_of_snapshot ?? []);
$hasAccountAsOfSnapshot = !empty($accountAsOfSnapshot['available']);

$buildQuery = static function ($overrides = []) use ($filter_account_id, $date_from, $date_to, $pg, $scope, $filterMutationType, $filterModule): string {
    $base = [
        'scope'      => $scope,
        'mutation_type' => $filterMutationType,
        'module_filter' => $filterModule,
        'account_id' => $filter_account_id ?? '',
        'date_from'  => $date_from ?? '',
        'date_to'    => $date_to ?? '',
        'per_page'   => $pg['per_page'] ?? 25,
        'page'       => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) return range(1, $totalPages);
    $items = [1];
    $start = max(2, $page - 1);
    $end   = min($totalPages - 1, $page + 1);
    if ($start > 2) $items[] = '...';
    for ($i = $start; $i <= $end; $i++) $items[] = $i;
    if ($end < $totalPages - 1) $items[] = '...';
    $items[] = $totalPages;
    return $items;
};

/* Resolve a clickable URL from ref_module + ref_table + ref_id */
$resolveRefUrl = static function (string $module, string $table, int $refId, int $posLineParentId = 0): string {
    if ($refId <= 0) return '';
    switch (strtoupper($module)) {
        case 'PURCHASE':
            return site_url('purchase-orders/detail/' . $refId);
        case 'POS':
            if ($table === 'pos_payment')      return site_url('pos/reports/payments/' . $refId);
            if ($table === 'pos_payment_line') return $posLineParentId > 0 ? site_url('pos/reports/payments/' . $posLineParentId) : '';
            if ($table === 'pos_refund')       return site_url('pos/reports/refunds/' . $refId);
            return '';
        case 'PAYROLL':
            if ($table === 'pay_cash_advance')        return site_url('payroll/cash-advances');
            if ($table === 'pay_salary_disbursement') return site_url('payroll/salary-disbursements');
            return '';
        default:
            return '';
    }
};

$moduleLabel = static function (string $module, string $table): string {
    switch (strtoupper($module)) {
        case 'PURCHASE':          return 'Purchase';
        case 'POS':               return 'POS';
        case 'FINANCE_RECON':     return 'Rekonsiliasi Kas';
        case 'REVENUE_RECON':     return 'Rekon Pendapatan';
        case 'FINANCE_TRANSFER':  return 'Transfer';
        case 'FINANCE_PAYABLE':   return 'Utang';
        case 'FINANCE_RECEIVABLE':return 'Piutang';
        case 'FINANCE':           return 'Manual';
        case 'PAYROLL':
            if ($table === 'pay_cash_advance')        return 'Kasbon';
            if ($table === 'pay_salary_disbursement') return 'Gaji';
            return 'Payroll';
        default: return $module ?: '-';
    }
};

$moduleBadgeClass = static function (string $module): string {
    switch (strtoupper($module)) {
        case 'PURCHASE':           return 'bg-label-danger';
        case 'POS':                return 'bg-label-success';
        case 'FINANCE_RECON':      return 'bg-label-warning';
        case 'REVENUE_RECON':      return 'bg-label-primary';
        case 'FINANCE_TRANSFER':   return 'bg-label-info';
        case 'FINANCE_PAYABLE':    return 'bg-label-warning';
        case 'FINANCE_RECEIVABLE': return 'bg-label-primary';
        case 'PAYROLL':            return 'bg-label-secondary';
        default:                   return 'bg-label-secondary';
    }
};

$acctTypeMeta = static function (string $type): array {
    switch (strtoupper($type)) {
        case 'BANK':    return ['accent' => '#1d4ed8', 'soft' => 'rgba(29,78,216,.13)',  'surface' => '#eff4ff', 'icon' => 'ri-bank-line'];
        case 'CASH':    return ['accent' => '#0f766e', 'soft' => 'rgba(15,118,110,.13)', 'surface' => '#eefaf8', 'icon' => 'ri-money-dollar-circle-line'];
        case 'EWALLET': return ['accent' => '#7c3aed', 'soft' => 'rgba(124,58,237,.13)','surface' => '#f5f0ff', 'icon' => 'ri-wallet-3-line'];
        default:        return ['accent' => '#475569', 'soft' => 'rgba(71,85,105,.13)',  'surface' => '#f8fafc', 'icon' => 'ri-safe-line'];
    }
};

$netFlow     = (float)($summary['in_total'] ?? 0) - (float)($summary['out_total'] ?? 0);
$totalBalance = array_sum(array_column($accountBreakdown, 'current_balance'));
$scopeTabs = [
    'all' => [
        'label' => 'Semua',
        'desc' => 'Semua mutasi rekening, manual dan hasil sistem.',
    ],
    'manual' => [
        'label' => 'Mutasi Manual',
        'desc' => 'Mutasi IN/OUT manual, antar rekening, rekonsiliasi kas, dan rekonsiliasi pendapatan.',
    ],
];
$moduleFilterOptions = [
    'ALL' => 'Semua Modul',
    'POS' => 'POS',
    'PURCHASE' => 'PO / Purchase',
    'FINANCE' => 'Manual',
    'FINANCE_RECON' => 'Rekonsiliasi Kas',
    'REVENUE_RECON' => 'Rekonsiliasi Pendapatan',
    'FINANCE_TRANSFER' => 'Transfer',
    'FINANCE_PAYABLE' => 'Utang',
    'FINANCE_RECEIVABLE' => 'Piutang',
    'PAYROLL' => 'Payroll',
];
?>

<style>
  /* ─── Hero ─────────────────────────────────────────────────── */
  .mut-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid #e8ddd7;
    border-radius: 22px;
    background: linear-gradient(135deg, #fffdfb 0%, #fff5f2 100%);
    box-shadow: 0 10px 28px rgba(67,89,113,.08);
  }
  .mut-hero::before {
    content:'';
    position:absolute;
    width:260px;height:260px;
    top:-130px;right:-60px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(159,33,65,.07) 0%,transparent 72%);
    pointer-events:none;
  }
  .mut-hero-body { position:relative;z-index:1;padding:1rem 1.2rem; }
  .mut-hero-title {
    font-size:1.5rem;font-weight:800;color:#2c2225;
    display:flex;align-items:center;gap:.55rem;margin-bottom:.5rem;
  }
  .mut-hero-sub { color:#76605a;font-size:.8rem; }
  .mut-hero-actions { display:flex;gap:.5rem;flex-shrink:0; }

  /* ─── Summary Cards ─────────────────────────────────────────── */
  .mut-card {
    --mc-accent:#8a1538;--mc-soft:rgba(138,21,56,.13);--mc-surface:#fff5f4;
    position:relative;overflow:hidden;
    border-radius:20px;
    background:linear-gradient(145deg,var(--mc-surface) 0%,#fff 68%);
    box-shadow:0 12px 26px rgba(67,89,113,.09);
    border:1px solid rgba(210,200,195,.6);
  }
  .mut-card::before {
    content:'';position:absolute;
    inset:auto -22px -28px auto;
    width:110px;height:110px;border-radius:50%;
    background:radial-gradient(circle,var(--mc-soft) 0%,transparent 72%);
    pointer-events:none;
  }
  .mut-card .card-body { position:relative;z-index:1;padding:.9rem 1rem; }
  .mut-card--in      { --mc-accent:#0f766e;--mc-soft:rgba(15,118,110,.15);--mc-surface:#eefaf8; }
  .mut-card--out     { --mc-accent:#be123c;--mc-soft:rgba(190,18,60,.14); --mc-surface:#fff0f3; }
  .mut-card--net-pos { --mc-accent:#1d4ed8;--mc-soft:rgba(29,78,216,.14); --mc-surface:#eff4ff; }
  .mut-card--net-neg { --mc-accent:#b45309;--mc-soft:rgba(180,83,9,.14);  --mc-surface:#fff6ee; }
  .mut-card--total   { --mc-accent:#475569;--mc-soft:rgba(71,85,105,.13); --mc-surface:#f8fafc; }
  .mut-card--balance { --mc-accent:#1d4ed8;--mc-soft:rgba(29,78,216,.12); --mc-surface:#eff4ff; }
  .mut-card-kicker {
    display:block;font-size:.69rem;font-weight:800;letter-spacing:.08em;
    text-transform:uppercase;color:var(--mc-accent);margin-bottom:.25rem;
  }
  .mut-card-value {
    font-size:1rem;font-weight:800;color:#1f2a39;line-height:1.15;
    margin-bottom:.2rem;
  }
  .mut-card-sub { font-size:.71rem;color:#6b7280; }
  .mut-card-icon {
    width:2.2rem;height:2.2rem;border-radius:14px;
    display:inline-flex;align-items:center;justify-content:center;
    background:var(--mc-soft);color:var(--mc-accent);font-size:1.05rem;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.7);
    flex-shrink:0;
  }

  /* ─── Account Breakdown Cards ───────────────────────────────── */
  .mut-acct-card {
    border-radius:16px;border:1px solid rgba(210,200,195,.55);
    background:linear-gradient(145deg,var(--mc-surface) 0%,#fff 70%);
    box-shadow:0 8px 18px rgba(67,89,113,.07);
    padding:.75rem .85rem;
    position:relative;overflow:hidden;
  }
  .mut-acct-card::after {
    content:'';position:absolute;
    bottom:-18px;right:-18px;
    width:72px;height:72px;border-radius:50%;
    background:var(--mc-soft);
    pointer-events:none;
  }
  .mut-acct-name  { font-size:.78rem;font-weight:700;color:#1f2a39;line-height:1.2; }
  .mut-acct-code  { font-size:.67rem;color:#6b7280; }
  .mut-acct-bal   { font-size:.9rem;font-weight:800;color:var(--mc-accent);margin:.3rem 0 .18rem; }
  .mut-acct-chips { display:flex;gap:.3rem;flex-wrap:wrap; }
  .mut-acct-chip  {
    font-size:.63rem;font-weight:600;padding:.12rem .38rem;
    border-radius:999px;background:var(--mc-soft);color:var(--mc-accent);
  }
  .mut-acct-icon  {
    width:2rem;height:2rem;border-radius:10px;
    display:inline-flex;align-items:center;justify-content:center;
    background:var(--mc-soft);color:var(--mc-accent);font-size:.95rem;flex-shrink:0;
  }

  /* ─── Filter card ────────────────────────────────────────────── */
  .mut-filter-card.card {
    border:1px solid rgba(210,200,195,.5) !important;
    border-radius:14px !important;
    box-shadow:0 6px 16px rgba(67,89,113,.06);
    background:#fffdfb;
  }
  .mut-filter-card .form-control,
  .mut-filter-card .form-select { border-radius:8px;border-color:rgba(103,88,74,.18);background:#fffdfb;font-size:.81rem; }
  .mut-filter-card .btn { border-radius:8px;font-weight:700; }

  /* ─── Table card ─────────────────────────────────────────────── */
  .mut-board-card {
    border:0;border-radius:22px;
    box-shadow:0 14px 32px rgba(67,89,113,.1);
    /* NO overflow:hidden — it would trap position:sticky inside */
  }
  .mut-board-card > .card-body { padding:1rem 1rem 0; }

  /* ─── Scrollable table (sticky header) ──────────────────────── */
  /*
   * Single container: overflow-y auto triggers vertical scroll,
   * overflow-x auto handles wide tables.
   * position:sticky top:0 on thead works correctly because
   * we have NO horizontal sticky columns — Chrome's dual-axis
   * sticky bug only applies when sticky is needed on BOTH axes.
   */
  .mut-table-scroll {
    max-height: 68vh;
    overflow-y: auto;
    overflow-x: auto;
    border: 1px solid #eee6e1;
    border-radius: 14px;
    scrollbar-color: #c9bab2 #f7f3f1;
    scrollbar-width: thin;
  }
  .mut-table {
    table-layout: fixed;
    min-width: 1160px;
    width: 100%;
    font-size: .77rem;
  }
  .mut-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    font-size: .73rem;
    font-weight: 700;
    white-space: nowrap;
    border-bottom: 2px solid #e9e3dc;
    padding: .5rem .55rem;
    vertical-align: middle;
  }
  .mut-table tbody td {
    padding: .42rem .55rem;
    vertical-align: middle;
    border-bottom: 1px solid rgba(233,227,220,.6);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .mut-table tbody tr:hover td { background: rgba(255,245,242,.7); }
  .mut-table tbody tr:last-child td { border-bottom: 0; }

  .mut-col-date  { width: 100px; }
  .mut-col-posted { width: 132px; }
  .mut-col-no    { width: 145px; }
  .mut-col-acct  { width: 150px; }
  .mut-col-type  { width: 58px;  text-align: center; }
  .mut-col-mod   { width: 105px; text-align: center; }
  .mut-col-amt   { width: 135px; text-align: right; }
  .mut-col-bal   { width: 120px; text-align: right; }
  .mut-col-ref   { width: 135px; }
  .mut-col-notes { width: 192px; white-space: normal; word-break: break-word; overflow: visible; }

  /* amount colors */
  .mut-amt-in  { color: #0f766e; font-weight: 700; }
  .mut-amt-out { color: #be123c; font-weight: 700; }

  /* ─── Table footer ───────────────────────────────────────────── */
  .mut-table-footer {
    border-top: 1px solid rgba(67,89,113,.1);
    padding: .65rem 1rem;
    background: rgba(67,89,113,.03);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: .5rem;
    font-size: .78rem;
  }
  .mut-table-footer .pagination { margin-bottom:0; }
  .mut-table-footer .page-link { border-radius: 8px !important; font-size:.74rem; }

  /* ─── Notes hyperlink ────────────────────────────────────────── */
  .mut-ref-link {
    display: inline-flex;
    align-items: center;
    gap: .22rem;
    color: inherit;
    text-decoration: none;
    border-bottom: 1px dashed rgba(67,89,113,.3);
    transition: border-color .15s, color .15s;
  }
  .mut-ref-link:hover { color: #8a1538; border-bottom-color: #8a1538; }
  .mut-ref-link i { font-size: .78rem; opacity: .7; }

  @media (min-width: 768px) and (max-width: 1199.98px) {
    .mut-board-card > .card-body { padding:.8rem .8rem 0; }
    .mut-table { min-width:1160px; }
    .mut-table thead .mut-col-date,
    .mut-table tbody .mut-col-date { position:sticky;left:0;z-index:3;background:#fff;box-shadow:inset -1px 0 0 #e8ddd7,4px 0 8px rgba(50,35,30,.06); }
    .mut-table thead .mut-col-date { z-index:5; }
    .mut-col-notes { width:150px; }
  }

  @media (max-width: 767.98px) {
    .mut-hero { border-radius:16px; }.mut-hero-body { padding:.85rem; }.mut-hero-title { font-size:1.15rem; }
    .mut-hero-actions { width:100%; }.mut-hero-actions .btn { flex:1; }
    .mut-card { border-radius:15px; }.mut-card .card-body { padding:.72rem; }.mut-card-value { font-size:.86rem;overflow-wrap:anywhere; }.mut-card-icon { display:none; }
    .mut-acct-card { height:100%; }.mut-board-card { border-radius:16px;background:transparent;box-shadow:none; }.mut-board-card > .card-body { padding:0; }
    .mut-filter-card .card-body { padding:.75rem !important; }.mut-filter-card form > div:last-child { width:100%;justify-content:space-between; }
    .mut-table-scroll { max-height:none;overflow:visible;border:0;border-radius:0; }
    .mut-table { display:block;min-width:0;width:100%;table-layout:auto; }.mut-table thead { display:none; }.mut-table tbody { display:grid;gap:.7rem; }
    .mut-table tbody tr { display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);border:1px solid #e8ddd7;border-radius:14px;background:#fff;box-shadow:0 6px 16px rgba(67,89,113,.07);overflow:hidden; }
    .mut-table tbody td,.mut-table tbody td[class^="mut-col-"],.mut-table tbody td[class*=" mut-col-"] { display:block;width:auto;min-width:0;padding:.58rem .68rem;border:0;border-bottom:1px solid #f0e9e5;white-space:normal;overflow:visible;text-overflow:clip;text-align:left; }
    .mut-table tbody td::before { content:attr(data-label);display:block;margin-bottom:.18rem;color:#8a7d77;font-size:.58rem;font-weight:800;letter-spacing:.055em;text-transform:uppercase; }
    .mut-table tbody .mut-col-date,.mut-table tbody .mut-col-posted,.mut-table tbody .mut-col-no,.mut-table tbody .mut-col-notes { grid-column:1 / -1; }
    .mut-table tbody .mut-col-date { background:#fff8f5; }.mut-table tbody .mut-col-date > div:first-child { font-size:.82rem !important;font-weight:800;color:#2c2225; }
    .mut-table tbody .mut-col-no { font-size:.7rem !important;overflow-wrap:anywhere; }.mut-table tbody .mut-col-amt { font-size:.82rem; }.mut-table tbody .mut-col-ref { overflow-wrap:anywhere; }
    .mut-table tbody .mut-col-notes { border-bottom:0;line-height:1.45; }.mut-table tbody tr.mut-empty-row { display:block; }.mut-table tbody tr.mut-empty-row td { text-align:center;border:0; }.mut-table tbody tr.mut-empty-row td::before { display:none; }
    .mut-table-footer { padding:.75rem .25rem;justify-content:center; }.mut-table-footer > small { width:100%;text-align:center; }.mut-table-footer .pagination { flex-wrap:wrap;justify-content:center; }
  }

  /* ─── Modal ──────────────────────────────────────────────────── */
  .mut-modal .modal-content {
    border-radius: 20px;
    border: 0;
    box-shadow: 0 24px 60px rgba(0,0,0,.18);
  }
  .mut-modal .modal-header {
    border-bottom: 1px solid #f0e8e3;
    padding: 1rem 1.2rem .8rem;
    border-radius: 20px 20px 0 0;
    background: linear-gradient(135deg, #fffdfb 0%, #fff5f2 100%);
  }
  .mut-modal .modal-title { font-weight: 800; font-size: 1rem; }
  .mut-modal .modal-body  { padding: 1.1rem 1.2rem; }
  .mut-modal .modal-footer { border-top: 1px solid #f0e8e3; padding: .8rem 1.2rem; }
  .mut-modal .form-label { font-size: .8rem; font-weight: 600; margin-bottom: .25rem; }
  .mut-modal .form-control,
  .mut-modal .form-select { border-radius: 10px; border-color: rgba(103,88,74,.22); font-size: .84rem; }
</style>

<!-- ───────────────── Hero ─────────────────────────────────────── -->
<div class="card mut-hero mb-3">
  <div class="mut-hero-body d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <div class="mut-hero-title">
        <i class="ri ri-exchange-funds-line text-danger"></i>
        <?php echo html_escape($title); ?>
      </div>
      <div class="mut-hero-sub">
        Log mutasi saldo rekening. Periode: <strong><?php echo html_escape((string)$date_from); ?></strong>
        &ndash; <strong><?php echo html_escape((string)$date_to); ?></strong>
        <?php if ((int)($filter_account_id ?? 0) > 0):
            $acctName = '';
            foreach (($accounts ?? []) as $a) {
                if ((int)$a['id'] === (int)$filter_account_id) { $acctName = (string)$a['account_name']; break; }
            }
        ?>
        &bull; Rekening: <strong><?php echo html_escape($acctName); ?></strong>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <?php foreach ($scopeTabs as $tabKey => $tabMeta): ?>
          <?php $isActiveScope = $scope === $tabKey; ?>
          <a
            href="<?php echo site_url('finance/mutations?' . $buildQuery(['scope' => $tabKey, 'page' => 1])); ?>"
            class="btn btn-sm <?php echo $isActiveScope ? 'btn-danger' : 'btn-outline-danger'; ?> fw-bold"
          >
            <?php echo html_escape($tabMeta['label']); ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="mut-hero-sub mt-2">
        <?php echo html_escape((string)($scopeTabs[$scope]['desc'] ?? '')); ?>
      </div>
      <div class="mut-hero-sub mt-2">
        Urutan riwayat mengikuti <strong>waktu posting</strong> agar Saldo Sebelum/Sesudah tetap satu rantai.
        Filter tetap memakai <strong>tanggal bisnis</strong>; label <strong>Backdate</strong> berarti keduanya berbeda.
      </div>
    </div>
    <div class="mut-hero-actions">
      <a href="<?php echo site_url('finance/accounts'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri ri-bank-line me-1"></i>Master Rekening
      </a>
      <button type="button" class="btn btn-sm btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#mutInputModal">
        <i class="ri ri-add-circle-line me-1"></i>Input Mutasi
      </button>
    </div>
  </div>
</div>

<div id="mut-alert-area" class="mb-2"></div>

<div class="alert alert-info" role="note">
  <strong>Kategori menjelaskan tujuan dana.</strong> Gunakan tombol <strong>Kategori</strong> pada mutasi manual atau rekonsiliasi untuk memperbaiki pengaruhnya ke estimasi. Saldo dan nominal tidak berubah; alasan koreksi dicatat di log aktivitas. Periode yang sudah ditutup harus dibuka kembali melalui prosedur resmi.
</div>
<?php if (empty($category_schema_ready)): ?>
  <div class="alert alert-warning">Input masuk/keluar memerlukan migrasi kategori 2026-09-13a. Hubungi administrator; riwayat tetap dapat dibaca.</div>
<?php endif; ?>

<?php if ((int)($filter_account_id ?? 0) > 0): ?>
  <div class="alert <?php echo $hasAccountAsOfSnapshot && !empty($accountAsOfSnapshot['ledger_matches_live']) ? 'alert-info' : 'alert-warning'; ?> border-0 shadow-sm mb-3" role="status">
    <div class="d-flex gap-2 align-items-start">
      <i class="ri <?php echo $hasAccountAsOfSnapshot && !empty($accountAsOfSnapshot['ledger_matches_live']) ? 'ri-calendar-check-line' : 'ri-alert-line'; ?> mt-1"></i>
      <div>
        <?php if ($hasAccountAsOfSnapshot): ?>
          <div class="fw-bold">Saldo bisnis per <?php echo html_escape((string)($accountAsOfSnapshot['as_of_date'] ?? $date_to ?? '')); ?>: Rp <?php echo number_format((float)($accountAsOfSnapshot['business_balance'] ?? 0), 0, ',', '.'); ?></div>
          <div class="small mt-1">
            Saldo awal catatan Rp <?php echo number_format((float)($accountAsOfSnapshot['opening_balance'] ?? 0), 0, ',', '.'); ?>
            + net <?php echo ((float)($accountAsOfSnapshot['business_net_amount'] ?? 0) >= 0 ? '+' : ''); ?>Rp <?php echo number_format((float)($accountAsOfSnapshot['business_net_amount'] ?? 0), 0, ',', '.'); ?>
            dari <?php echo number_format((int)($accountAsOfSnapshot['business_mutation_count'] ?? 0)); ?> mutasi bertanggal bisnis sampai cut-off.
            Snapshot ini selalu memakai seluruh jurnal rekening, bukan filter modul/IN-OUT pada daftar.
          </div>
          <div class="small mt-1">
            Saldo aktif saat ini: <strong>Rp <?php echo number_format((float)($accountAsOfSnapshot['live_balance'] ?? 0), 0, ',', '.'); ?></strong>.
            <?php if (!empty($accountAsOfSnapshot['ledger_matches_live'])): ?>
              Catatan posting dan saldo aktif cocok.
            <?php else: ?>
              Catatan posting tidak cocok dengan saldo aktif; jangan melakukan rebuild otomatis, periksa audit mutasi terlebih dahulu.
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="fw-bold">Saldo bisnis per tanggal belum dapat ditampilkan</div>
          <div class="small mt-1"><?php echo html_escape((string)($accountAsOfSnapshot['message'] ?? 'Pilih rekening dan tanggal yang valid.')); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ───────────────── Summary Cards ──────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card mut-card--in">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Total IN</span>
            <div class="mut-card-value">Rp <?php echo number_format((float)($summary['in_total'] ?? 0), 0, ',', '.'); ?></div>
            <div class="mut-card-sub">Dana masuk periode ini</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-arrow-down-circle-line"></i></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card mut-card--out">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Total OUT</span>
            <div class="mut-card-value">Rp <?php echo number_format((float)($summary['out_total'] ?? 0), 0, ',', '.'); ?></div>
            <div class="mut-card-sub">Dana keluar periode ini</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-arrow-up-circle-line"></i></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card <?php echo $netFlow >= 0 ? 'mut-card--net-pos' : 'mut-card--net-neg'; ?>">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Net Cashflow</span>
            <div class="mut-card-value"><?php echo ($netFlow >= 0 ? '+' : ''); ?>Rp <?php echo number_format($netFlow, 0, ',', '.'); ?></div>
            <div class="mut-card-sub"><?php echo $netFlow >= 0 ? 'Surplus' : 'Defisit'; ?> periode ini</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-bar-chart-grouped-line"></i></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card mut-card--total">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Total Transaksi</span>
            <div class="mut-card-value"><?php echo number_format((int)($summary['rows_total'] ?? 0)); ?> log</div>
            <div class="mut-card-sub">Rp <?php echo number_format($totalBalance, 0, ',', '.'); ?> saldo total</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-list-check-2"></i></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ───────────────── Per-Account Breakdown ───────────────────────── -->
<?php if (!empty($accountBreakdown)): ?>
<div class="row g-2 mb-3">
  <?php foreach ($accountBreakdown as $ab):
    $meta = $acctTypeMeta((string)($ab['account_type'] ?? ''));
    $acIn  = (float)($ab['in_total'] ?? 0);
    $acOut = (float)($ab['out_total'] ?? 0);
    $acTx  = (int)($ab['tx_count'] ?? 0);
  ?>
  <div class="col-sm-6 col-md-4 col-lg-3">
    <div class="mut-acct-card" style="--mc-accent:<?php echo $meta['accent']; ?>;--mc-soft:<?php echo $meta['soft']; ?>;--mc-surface:<?php echo $meta['surface']; ?>;">
      <div class="d-flex align-items-start gap-2">
        <span class="mut-acct-icon"><i class="ri <?php echo $meta['icon']; ?>"></i></span>
        <div style="min-width:0;">
          <div class="mut-acct-name"><?php echo html_escape((string)($ab['account_name'] ?? '')); ?></div>
          <div class="mut-acct-code"><?php echo html_escape((string)($ab['account_code'] ?? '')); ?> &bull; <?php echo html_escape((string)($ab['account_type'] ?? '')); ?></div>
          <div class="mut-acct-bal">Rp <?php echo number_format((float)($ab['current_balance'] ?? 0), 0, ',', '.'); ?></div>
          <div class="mut-acct-chips">
            <?php if ($acTx > 0): ?>
            <span class="mut-acct-chip">+<?php echo number_format($acIn, 0, ',', '.'); ?></span>
            <span class="mut-acct-chip" style="background:rgba(190,18,60,.1);color:#be123c;">&minus;<?php echo number_format($acOut, 0, ',', '.'); ?></span>
            <span class="mut-acct-chip" style="background:rgba(71,85,105,.1);color:#475569;"><?php echo $acTx; ?> tx</span>
            <?php else: ?>
            <span class="mut-acct-chip" style="background:rgba(71,85,105,.1);color:#475569;">Tidak ada transaksi</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ───────────────── Main Table Card ────────────────────────────── -->
<div class="card mut-board-card">
  <div class="card-body">
    <!-- Filter bar -->
    <div class="mut-filter-card card mb-3">
      <div class="card-body py-2 px-3">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
          <input type="hidden" name="scope" value="<?php echo html_escape($scope); ?>">
          <div class="col-md-2 col-lg-2">
            <label class="form-label mb-1">IN / OUT</label>
            <select name="mutation_type" class="form-select form-select-sm">
              <option value="ALL" <?php echo $filterMutationType === 'ALL' ? 'selected' : ''; ?>>Semua</option>
              <option value="IN" <?php echo $filterMutationType === 'IN' ? 'selected' : ''; ?>>IN</option>
              <option value="OUT" <?php echo $filterMutationType === 'OUT' ? 'selected' : ''; ?>>OUT</option>
            </select>
          </div>
          <div class="col-md-3 col-lg-2">
            <label class="form-label mb-1">Jenis Modul</label>
            <select name="module_filter" class="form-select form-select-sm">
              <?php foreach ($moduleFilterOptions as $moduleKey => $moduleOptionLabel): ?>
                <option value="<?php echo html_escape($moduleKey); ?>" <?php echo $filterModule === $moduleKey ? 'selected' : ''; ?>>
                  <?php echo html_escape($moduleOptionLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 col-lg-3">
            <label class="form-label mb-1">Rekening</label>
            <select name="account_id" class="form-select form-select-sm">
              <option value="">Semua rekening</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>" <?php echo ((int)($filter_account_id ?? 0) === (int)$a['id']) ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$a['account_code'] . ' – ' . (string)$a['account_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">Tanggal Bisnis Dari</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">Tanggal Bisnis Sampai</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
          </div>
          <div class="col-4 col-md-1">
            <label class="form-label mb-1">Per</label>
            <select name="per_page" class="form-select form-select-sm">
              <?php foreach ([10, 25, 50, 100, 200] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($pg['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-8 col-md-auto d-flex gap-2 align-items-end">
            <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
            <a href="<?php echo site_url('finance/mutations?' . $buildQuery([
              'mutation_type' => 'ALL', 'module_filter' => 'ALL', 'account_id' => '',
              'date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d'), 'page' => 1,
            ])); ?>" class="btn btn-sm btn-outline-danger">Clear</a>
            <small class="text-muted ms-1 align-self-center">
              Hal <?php echo (int)($pg['page'] ?? 1); ?>/<?php echo (int)($pg['total_pages'] ?? 1); ?>
              &bull; <?php echo number_format((int)($pg['total'] ?? 0)); ?> baris
            </small>
          </div>
        </form>
      </div>
    </div>

    <!-- Table with sticky header -->
    <div class="mut-table-scroll" id="mutTableScroll">
        <table class="table table-hover mb-0 mut-table">
          <thead>
            <tr>
              <th class="mut-col-date">Tanggal Bisnis</th>
              <th class="mut-col-posted">Diposting</th>
              <th class="mut-col-no">No Mutasi</th>
              <th class="mut-col-acct">Rekening</th>
              <th class="mut-col-type">Tipe</th>
              <th class="mut-col-mod">Modul</th>
              <th class="mut-col-amt">Nominal</th>
              <th class="mut-col-bal">Sebelum Diposting</th>
              <th class="mut-col-bal">Sesudah Diposting</th>
              <th class="mut-col-ref">Ref No</th>
              <th class="mut-col-notes">Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr class="mut-empty-row"><td colspan="11" class="text-center text-muted py-5">
                <i class="ri ri-inbox-line" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                Belum ada data mutasi pada periode ini.
              </td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r):
                $isIn   = strtoupper((string)($r['mutation_type'] ?? '')) === 'IN';
                $mod              = strtoupper(trim((string)($r['ref_module'] ?? '')));
                $tbl              = (string)($r['ref_table'] ?? '');
                $refId            = (int)($r['ref_id'] ?? 0);
                $refNo            = (string)($r['ref_no'] ?? '');
                $notes            = (string)($r['notes'] ?? '');
                $posLineParentId  = (int)($r['pos_payment_line_parent_id'] ?? 0);
                $refUrl           = $resolveRefUrl($mod, $tbl, $refId, $posLineParentId);
              ?>
              <tr>
                <?php
                  $dt = (string)($r['mutation_date'] ?? '');
                  $postedAt = (string)($r['created_at'] ?? '');
                  $isBackdated = strlen($dt) >= 10 && strlen($postedAt) >= 10 && substr($dt, 0, 10) !== substr($postedAt, 0, 10);
                ?>
                <td class="mut-col-date" data-label="Tanggal Bisnis" title="<?php echo html_escape($dt); ?>">
                  <div style="font-size:.73rem;"><?php echo html_escape(strlen($dt) >= 10 ? substr($dt, 0, 10) : $dt); ?></div>
                  <?php if ($isBackdated): ?>
                    <span class="badge bg-label-warning mt-1" style="font-size:.59rem;">Backdate</span>
                  <?php endif; ?>
                </td>
                <td class="mut-col-posted" data-label="Diposting" title="<?php echo html_escape($postedAt); ?>" style="font-size:.71rem;color:#6b7280;">
                  <?php echo html_escape($postedAt !== '' ? substr($postedAt, 0, 16) : '–'); ?>
                </td>
                <td class="mut-col-no" data-label="No Mutasi" style="font-family:monospace;font-size:.71rem;"><?php echo html_escape((string)($r['mutation_no'] ?? '')); ?></td>
                <td class="mut-col-acct" data-label="Rekening">
                  <div style="font-weight:600;font-size:.76rem;color:#1f2a39;"><?php echo html_escape((string)($r['account_name'] ?? '')); ?></div>
                  <div style="font-size:.67rem;color:#6b7280;"><?php echo html_escape((string)($r['account_code'] ?? '')); ?></div>
                </td>
                <td class="mut-col-type" data-label="Tipe">
                  <?php if ($isIn): ?>
                    <span class="badge bg-label-success" style="font-size:.64rem;">IN</span>
                  <?php else: ?>
                    <span class="badge bg-label-danger"  style="font-size:.64rem;">OUT</span>
                  <?php endif; ?>
                </td>
                <td class="mut-col-mod" data-label="Modul">
                  <?php if ($mod !== ''): ?>
                    <span class="badge <?php echo $moduleBadgeClass($mod); ?>" style="font-size:.62rem;">
                      <?php echo html_escape($moduleLabel($mod, $tbl)); ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">–</span>
                  <?php endif; ?>
                  <?php if (in_array($mod, ['FINANCE', 'FINANCE_RECON', 'REVENUE_RECON'], true)): ?>
                    <div class="small mt-1 <?php echo empty($r['report_category']) ? 'text-warning' : 'text-muted'; ?>">
                      <?php echo html_escape((string)($reportCategories[$r['report_category'] ?? '']['label'] ?? 'Belum diklasifikasikan')); ?>
                    </div>
                    <?php if ($canClassify): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary mt-1 mutation-classify"
                        data-id="<?php echo (int)$r['id']; ?>" data-category="<?php echo html_escape((string)($r['report_category'] ?? '')); ?>"
                        data-direction="<?php echo $isIn ? 'IN' : 'OUT'; ?>" data-document="<?php echo html_escape((string)($r['mutation_no'] ?? '') . ' · ' . ($isIn ? 'Masuk ' : 'Keluar ') . number_format((float)$r['amount'], 2, ',', '.')); ?>">Kategori</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="mut-col-amt <?php echo $isIn ? 'mut-amt-in' : 'mut-amt-out'; ?>" data-label="Nominal">
                  <?php echo ($isIn ? '+' : '-'); ?>Rp&nbsp;<?php echo number_format((float)($r['amount'] ?? 0), 0, ',', '.'); ?>
                </td>
                <td class="mut-col-bal" data-label="Saldo sebelum diposting" style="color:#6b7280;font-size:.73rem;"><?php echo number_format((float)($r['balance_before'] ?? 0), 0, ',', '.'); ?></td>
                <td class="mut-col-bal" data-label="Saldo sesudah diposting" style="font-weight:600;font-size:.73rem;"><?php echo number_format((float)($r['balance_after'] ?? 0), 0, ',', '.'); ?></td>
                <td class="mut-col-ref" data-label="Referensi" style="font-size:.71rem;color:#6b7280;" title="<?php echo html_escape($refNo); ?>">
                  <?php if ($refNo !== '' && $refUrl !== ''): ?>
                    <a href="<?php echo html_escape($refUrl); ?>" class="mut-ref-link" target="_blank" style="font-size:.71rem;"><?php echo html_escape($refNo); ?></a>
                  <?php else: ?>
                    <?php echo html_escape($refNo !== '' ? $refNo : '–'); ?>
                  <?php endif; ?>
                </td>
                <td class="mut-col-notes" data-label="Catatan">
                  <?php if ($notes !== ''): ?>
                    <?php if ($refUrl !== ''): ?>
                      <a href="<?php echo html_escape($refUrl); ?>" class="mut-ref-link" target="_blank">
                        <?php echo html_escape($notes); ?>
                        <i class="ri ri-external-link-line"></i>
                      </a>
                    <?php else: ?>
                      <?php echo html_escape($notes); ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">–</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
    </div>
  </div>

  <!-- Pagination footer -->
  <div class="mut-table-footer">
    <small class="text-muted">
      Halaman <strong><?php echo (int)($pg['page'] ?? 1); ?></strong>
      dari <strong><?php echo (int)($pg['total_pages'] ?? 1); ?></strong>
      &bull; Total <strong><?php echo number_format((int)($pg['total'] ?? 0)); ?></strong> baris
    </small>
    <?php if ((int)($pg['total_pages'] ?? 1) > 1):
      $curPage   = (int)($pg['page'] ?? 1);
      $totalPgs  = (int)($pg['total_pages'] ?? 1);
      $prev = max(1, $curPage - 1);
      $next = min($totalPgs, $curPage + 1);
      $pageItems = $buildPageItems($curPage, $totalPgs);
    ?>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?php echo $curPage <= 1 ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?php echo $curPage <= 1 ? '#' : site_url('finance/mutations?' . $buildQuery(['page' => $prev])); ?>">&lsaquo;</a>
      </li>
      <?php foreach ($pageItems as $pi): ?>
        <?php if ($pi === '...'): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php else: ?>
          <li class="page-item <?php echo $curPage === (int)$pi ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo site_url('finance/mutations?' . $buildQuery(['page' => (int)$pi])); ?>"><?php echo (int)$pi; ?></a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
      <li class="page-item <?php echo $curPage >= $totalPgs ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?php echo $curPage >= $totalPgs ? '#' : site_url('finance/mutations?' . $buildQuery(['page' => $next])); ?>">&rsaquo;</a>
      </li>
    </ul>
    <?php endif; ?>
  </div>
</div>

<!-- ───────────────── Input Mutasi Modal ─────────────────────────── -->
<div class="modal fade mut-modal" id="mutInputModal" tabindex="-1" aria-labelledby="mutInputModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mutInputModalLabel">
          <i class="ri ri-add-circle-line text-danger me-2"></i>Input Mutasi Rekening
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="mut-modal-alert"></div>
        <form id="mutation-form" class="row g-3" autocomplete="off">
          <div class="col-12">
            <label class="form-label">Jenis Mutasi <span class="text-danger">*</span></label>
            <select class="form-select" id="mutation_type" required>
              <option value="IN">IN — Dana Masuk</option>
              <option value="OUT">OUT — Dana Keluar</option>
              <option value="TRANSFER">TRANSFER — Antar Rekening</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" id="account_id_label">Rekening <span class="text-danger">*</span></label>
            <select class="form-select" id="account_id" required>
              <option value="">Pilih rekening...</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                  <?php echo html_escape((string)$a['account_code'] . ' – ' . (string)$a['account_name']); ?>
                  (Rp <?php echo number_format((float)$a['current_balance'], 0, ',', '.'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 d-none" id="to_account_wrap">
            <label class="form-label">Rekening Tujuan <span class="text-danger">*</span></label>
            <select class="form-select" id="to_account_id">
              <option value="">Pilih rekening tujuan...</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                  <?php echo html_escape((string)$a['account_code'] . ' – ' . (string)$a['account_name']); ?>
                  (Rp <?php echo number_format((float)$a['current_balance'], 0, ',', '.'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="mutation_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Jumlah <span class="text-danger">*</span></label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="amount" placeholder="0" required>
          </div>
          <div class="col-12" id="mutation-category-wrap">
            <label class="form-label" for="report_category">Kategori laporan <span class="text-danger">*</span></label>
            <select id="report_category" class="form-select" required></select>
            <?php $this->load->view('finance/_settlement_select',['settlement_options'=>$settlement_options??[]]); ?>
            <small id="category-effect" class="text-muted">Pilih sesuai tujuan dana, bukan hanya arah masuk/keluar.</small>
          </div>
          <div class="col-12 d-none" id="settlement-confirm-wrap">
            <label class="d-flex gap-2 align-items-start"><input type="checkbox" id="settlement-confirmed" class="form-check-input">
              <span>Biaya ini ditanggung usaha dan <strong>belum dipotong</strong> di nilai POS, rekonsiliasi, atau mutasi lain. Saya sudah memeriksa bukti settlement.</span>
            </label>
          </div>
          <div class="col-12">
            <label class="form-label">Reference No (wajib untuk promo/biaya platform)</label>
            <input type="text" class="form-control" id="reference_no" placeholder="Opsional">
          </div>
          <div class="col-12">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" id="notes" rows="2" placeholder="Opsional"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger fw-bold px-4" id="btn-save-mutation">
          <i class="ri ri-save-line me-1"></i>Simpan
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="mutationCategoryModal" tabindex="-1" aria-labelledby="mutationCategoryTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 id="mutationCategoryTitle" class="modal-title">Klasifikasi laporan</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body">
      <p id="classification-document" class="fw-semibold"></p>
      <div class="alert alert-info">Hanya mengubah pengelompokan laporan. Nominal, saldo, tanggal, dan posting mutasi tidak berubah. Periode CLOSED tidak dapat diubah.</div>
      <label for="classification-category" class="form-label">Kategori</label><select id="classification-category" class="form-select mb-3"></select>
      <label for="classification-reason" class="form-label">Alasan perubahan</label><textarea id="classification-reason" class="form-control" maxlength="255" rows="2" required></textarea>
      <div id="classification-alert" class="mt-2" role="alert"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="classification-save">Simpan kategori</button></div>
  </div></div>
</div>

<script>
(function () {
  var categories = <?php echo json_encode($reportCategories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
  var requestKey = <?php echo json_encode(bin2hex(random_bytes(16))); ?>;
  var categoryEl = document.getElementById('report_category');
  function categoryOptions(select, direction, selected) {
    select.innerHTML = '';
    select.add(new Option('Pilih kategori laporan...', ''));
    Object.keys(categories).forEach(function (key) {
      if (categories[key].direction === 'BOTH' || categories[key].direction === direction) {
        select.add(new Option(categories[key].label, key));
      }
    });
    select.value = selected || '';
  }
  function syncCategoryEffect() {
    var option = categories[categoryEl.value];
    document.getElementById('category-effect').textContent = !option ? 'Pilih sesuai tujuan dana, bukan hanya arah masuk/keluar.'
      : option.effect === 'income' ? 'Menambah estimasi hasil operasional.' : option.effect === 'expense'
      ? 'Mengurangi estimasi hasil operasional.' : 'Hanya saldo yang berubah; tidak menjadi pendapatan atau biaya operasional.';
    var settlement = ['PROMO_EXPENSE', 'PLATFORM_FEE'].indexOf(categoryEl.value) >= 0;
    document.getElementById('settlement-confirm-wrap').classList.toggle('d-none', !settlement);
    document.getElementById('settlement-confirmed').checked = false;
  }
  categoryEl.addEventListener('change', syncCategoryEffect);
  var storeUrl      = <?php echo json_encode($storeUrl); ?>;
  var purchaseMutationCsrfToken = <?php echo json_encode($purchaseMutationCsrfToken); ?>;
  var alertArea     = document.getElementById('mut-alert-area');
  var modalAlert    = document.getElementById('mut-modal-alert');
  var mutTypeEl     = document.getElementById('mutation_type');
  var toWrapEl      = document.getElementById('to_account_wrap');
  var acctLabelEl   = document.getElementById('account_id_label');
  var toAcctEl      = document.getElementById('to_account_id');
  var submitBtn     = document.getElementById('btn-save-mutation');

  function showAlert(el, type, msg) {
    el.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible py-2 mb-2"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><span></span></div>';
    el.querySelector('span').textContent = msg;
  }

  function syncForm() {
    var isT = (mutTypeEl.value || 'IN') === 'TRANSFER';
    toWrapEl.classList.toggle('d-none', !isT);
    toAcctEl.required = isT;
    document.getElementById('mutation-category-wrap').classList.toggle('d-none', isT);
    categoryEl.required = !isT;
    categoryOptions(categoryEl, mutTypeEl.value, '');
    syncCategoryEffect();
    var lbl = isT ? 'Rekening Sumber <span class="text-danger">*</span>' : 'Rekening <span class="text-danger">*</span>';
    acctLabelEl.innerHTML = lbl;
  }
  mutTypeEl.addEventListener('change', syncForm);
  syncForm();

  /* Reset modal on close */
  document.getElementById('mutInputModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('mutation-form').reset();
    mutTypeEl.value = 'IN';
    syncForm();
    modalAlert.innerHTML = '';
  });

  submitBtn.addEventListener('click', function () {
    var type = mutTypeEl.value || 'IN';
    var payload = {
      account_id:    Number(document.getElementById('account_id').value || 0),
      to_account_id: Number(toAcctEl.value || 0),
      mutation_type: type,
      report_category: categoryEl.value,
      client_request_key: requestKey,
      settlement_control_id: type==='TRANSFER'?0:Number(document.querySelector('#mutation-form [data-role="settlement-control"]')?.value || document.querySelector('[data-role="settlement-control"]')?.value || 0),
      settlement_charge_id: type==='TRANSFER'?0:Number(document.querySelector('#mutation-form [data-role="settlement-charge"]')?.value || document.querySelector('[data-role="settlement-charge"]')?.value || 0),
      settlement_confirmed: document.getElementById('settlement-confirmed').checked,
      mutation_date: document.getElementById('mutation_date').value,
      amount:        Number(document.getElementById('amount').value || 0),
      reference_no:  document.getElementById('reference_no').value || null,
      notes:         document.getElementById('notes').value || null
    };

    if (!payload.account_id || !payload.mutation_type || !payload.mutation_date || payload.amount <= 0) {
      showAlert(modalAlert, 'warning', 'Field wajib belum lengkap.');
      return;
    }
    if (type === 'TRANSFER' && (!payload.to_account_id || payload.to_account_id === payload.account_id)) {
      showAlert(modalAlert, 'warning', 'Rekening tujuan wajib dipilih dan harus berbeda dari rekening sumber.');
      return;
    }
    if (type !== 'TRANSFER' && !payload.report_category) {
      showAlert(modalAlert, 'warning', 'Pilih kategori laporan terlebih dahulu.'); return;
    }

    var origHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';

    fetch(storeUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Purchase-Mutation-CSRF': purchaseMutationCsrfToken },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal simpan mutasi.');
      }
      showAlert(alertArea, 'success', 'Mutasi berhasil disimpan. Halaman akan dimuat ulang...');
      var modal = bootstrap.Modal.getInstance(document.getElementById('mutInputModal'));
      if (modal) modal.hide();
      window.setTimeout(function () { window.location.reload(); }, 600);
    })
    .catch(function (err) {
      showAlert(modalAlert, 'danger', err.message || 'Gagal simpan mutasi.');
      submitBtn.disabled = false;
      submitBtn.innerHTML = origHtml;
    });
  });
  var classification = null;
  document.querySelectorAll('.mutation-classify').forEach(function (button) {
    button.addEventListener('click', function () {
      classification = { mutation_id: Number(button.dataset.id), expected_category: button.dataset.category || '' };
      document.getElementById('classification-document').textContent = button.dataset.document;
      categoryOptions(document.getElementById('classification-category'), button.dataset.direction, classification.expected_category);
      document.getElementById('classification-reason').value = '';
      document.getElementById('classification-alert').textContent = '';
      bootstrap.Modal.getOrCreateInstance(document.getElementById('mutationCategoryModal')).show();
    });
  });
  document.getElementById('classification-save').addEventListener('click', function () {
    if (!classification) return;
    var button = this;
    var body = Object.assign({}, classification, {
      report_category: document.getElementById('classification-category').value,
      reason: document.getElementById('classification-reason').value.trim()
    });
    if (!body.report_category || !body.reason) {
      showAlert(document.getElementById('classification-alert'), 'warning', 'Kategori dan alasan wajib diisi.'); return;
    }
    button.disabled = true;
    fetch(<?php echo json_encode(site_url('finance/mutations/classify')); ?>, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Purchase-Mutation-CSRF': purchaseMutationCsrfToken }, body: JSON.stringify(body)
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw Error(j.message || 'Klasifikasi gagal.'); return j; }); })
      .then(function () { window.location.reload(); })
      .catch(function (e) { showAlert(document.getElementById('classification-alert'), 'danger', e.message); button.disabled = false; });
  });
})();
</script>
