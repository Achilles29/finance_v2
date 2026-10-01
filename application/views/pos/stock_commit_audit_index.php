<?php
$asOfDate = (string)($as_of_date ?? date('Y-m-d'));
$auditTab = strtolower(trim((string)($audit_tab ?? 'material')));
if (!in_array($auditTab, ['material', 'component'], true)) {
    $auditTab = 'material';
}
$materialCompare = is_array($material_compare ?? null) ? $material_compare : [];
$componentCompare = is_array($component_compare ?? null) ? $component_compare : [];
$materialRows = is_array($materialCompare['rows'] ?? null) ? $materialCompare['rows'] : [];
$materialSummary = is_array($materialCompare['summary'] ?? null) ? $materialCompare['summary'] : [];
$materialSummaryAll = is_array($materialCompare['summary_all'] ?? null) ? $materialCompare['summary_all'] : $materialSummary;
$materialOptions = is_array($materialCompare['options'] ?? null) ? $materialCompare['options'] : [];
$materialPagination = is_array($materialCompare['pagination'] ?? null) ? $materialCompare['pagination'] : [];
$materialFilters = is_array($materialCompare['filters'] ?? null) ? $materialCompare['filters'] : [];
$componentRows = is_array($componentCompare['rows'] ?? null) ? $componentCompare['rows'] : [];
$componentSummary = is_array($componentCompare['summary'] ?? null) ? $componentCompare['summary'] : [];
$componentSummaryAll = is_array($componentCompare['summary_all'] ?? null) ? $componentCompare['summary_all'] : $componentSummary;
$componentOptions = is_array($componentCompare['options'] ?? null) ? $componentCompare['options'] : [];
$componentPagination = is_array($componentCompare['pagination'] ?? null) ? $componentCompare['pagination'] : [];
$componentFilters = is_array($componentCompare['filters'] ?? null) ? $componentCompare['filters'] : [];
$domainAudit = is_array($domain_audit ?? null) ? $domain_audit : [];
$domainAuditRows = is_array($domainAudit['rows'] ?? null) ? $domainAudit['rows'] : [];
$domainAuditSummary = is_array($domainAudit['summary'] ?? null) ? $domainAudit['summary'] : [];
$failedJobs = is_array($failed_jobs ?? null) ? $failed_jobs : [];
$activeJobs = is_array($active_jobs ?? null) ? $active_jobs : [];
$failedCommitSnapshots = is_array($failed_commit_snapshots ?? null) ? $failed_commit_snapshots : [];
$voidMaterialLotAudit = is_array($void_material_lot_audit ?? null) ? $void_material_lot_audit : [];
$voidMaterialLotAuditRows = is_array($voidMaterialLotAudit['rows'] ?? null) ? $voidMaterialLotAudit['rows'] : [];
$voidMaterialLotAuditSummary = is_array($voidMaterialLotAudit['summary'] ?? null) ? $voidMaterialLotAudit['summary'] : [];
$divisionOptions = is_array($division_options ?? null) ? $division_options : [];
$auditMonthFrom = (string)($audit_month_from ?? date('Y-m-01', strtotime($asOfDate)));
$auditMonthTo = (string)($audit_month_to ?? date('Y-m-t', strtotime($asOfDate)));

$fmtQty = static function ($value): string {
    return number_format((float)$value, 2, ',', '.');
};
$buildAuditUrl = static function (array $params = []): string {
    $base = [
        'as_of_date' => (string)($GLOBALS['asOfDate'] ?? ''),
        'tab' => (string)($GLOBALS['auditTab'] ?? 'material'),
    ];
    $query = array_merge($base, $params);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }
    return site_url('pos/stock-commit-audit') . (!empty($query) ? ('?' . http_build_query($query)) : '');
};
$renderPagination = static function (array $pagination, callable $urlBuilder): string {
    $totalPages = (int)($pagination['total_pages'] ?? 1);
    $currentPage = (int)($pagination['current_page'] ?? 1);
    if ($totalPages <= 1) {
        return '';
    }

    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    $html = '<nav aria-label="Pagination audit"><ul class="pagination pagination-sm mb-0">';
    $prevClass = $currentPage <= 1 ? ' disabled' : '';
    $html .= '<li class="page-item' . $prevClass . '"><a class="page-link" href="' . html_escape($urlBuilder(max(1, $currentPage - 1))) . '">Prev</a></li>';
    for ($pageNo = $start; $pageNo <= $end; $pageNo++) {
        $activeClass = $pageNo === $currentPage ? ' active' : '';
        $html .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . html_escape($urlBuilder($pageNo)) . '">' . $pageNo . '</a></li>';
    }
    $nextClass = $currentPage >= $totalPages ? ' disabled' : '';
    $html .= '<li class="page-item' . $nextClass . '"><a class="page-link" href="' . html_escape($urlBuilder(min($totalPages, $currentPage + 1))) . '">Next</a></li>';
    $html .= '</ul></nav>';
    return $html;
};
$GLOBALS['asOfDate'] = $asOfDate;
$GLOBALS['auditTab'] = $auditTab;
?>

<style>
  .sca-page {
    width:100%;
    max-width:1520px;
    margin:0 auto;
    padding:1rem 0;
    box-sizing:border-box;
    min-width:0;
  }
  .sca-shell { display:grid; gap:1rem; min-width:0; }
  .sca-card {
    border:1px solid rgba(225,210,199,.82);
    border-radius:22px;
    background:#fff;
    box-shadow:0 16px 34px rgba(58,38,30,.06);
    min-width:0;
    overflow:hidden;
  }
  .sca-card > .card-body { min-width:0; }
  .sca-filter-grid {
    display:grid;
    grid-template-columns:minmax(320px, 520px) minmax(0,1fr);
    gap:.75rem;
    align-items:end;
    min-width:0;
  }
  .sca-filter-primary {
    display:flex;
    align-items:end;
    gap:.75rem;
    flex-wrap:wrap;
  }
  .sca-filter-date {
    min-width:220px;
    flex:0 0 220px;
  }
  .sca-filter-primary-actions {
    display:flex;
    gap:.75rem;
    flex-wrap:wrap;
    align-items:end;
  }
  .sca-hero-copy { color:#78685d; font-size:.92rem; }
  .sca-kpis { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.8rem; }
  .sca-kpi {
    border:1px solid rgba(225,210,199,.82);
    border-radius:18px;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
    padding:.9rem 1rem;
  }
  .sca-kpi .label {
    display:block; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:#8a776f; font-weight:800; margin-bottom:.18rem;
  }
  .sca-kpi .value { font-size:1.3rem; font-weight:900; color:#2f2628; }
  .sca-tabs { display:flex; flex-wrap:wrap; gap:.55rem; }
  .sca-tab {
    display:inline-flex; align-items:center; gap:.4rem; min-height:40px; padding:.48rem .95rem;
    border-radius:999px; border:1px solid #cdbfb4; background:#f5efea; color:#5a4c45; text-decoration:none; font-weight:800; font-size:.88rem;
  }
  .sca-tab.is-active { background:#34325e; border-color:#34325e; color:#fff; }
  .sca-tab-count { font-size:.74rem; opacity:.9; }
  .sca-summary-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.8rem; min-width:0; }
  .sca-toolbar-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; align-items:end; min-width:0; }
  .sca-hero {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:1rem;
    flex-wrap:wrap;
    min-width:0;
  }
  .sca-hero-main {
    max-width:900px;
    min-width:0;
  }
  .sca-filter-actions {
    display:flex;
    gap:.75rem;
    flex-wrap:wrap;
    justify-content:flex-end;
    align-items:end;
    min-width:0;
  }
  .sca-summary-box {
    border:1px solid rgba(225,210,199,.82); border-radius:18px; background:#fffdfb; padding:.85rem .95rem;
  }
  .sca-summary-box .label { display:block; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:#8a776f; font-weight:800; margin-bottom:.18rem; }
  .sca-summary-box .value { font-size:1.18rem; font-weight:900; color:#2f2628; }
  .sca-toolbar-note { color:#8a776f; font-size:.78rem; }
  .sca-pagination-bar { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; }
  .sca-table-wrap {
    max-width:100%;
    max-height:520px;
    overflow:auto;
  }
  .sca-table {
    min-width:100%;
    width:max-content;
  }
  .sca-table th { white-space:nowrap; font-size:.78rem; background:#fff8f4; position:sticky; top:0; z-index:1; box-shadow:0 1px 0 rgba(0,0,0,.08); }
  .sca-table td { font-size:.82rem; vertical-align:top; }
  .sca-status-cell { min-width:280px; max-width:340px; }
  .sca-status-reason {
    display:block;
    max-width:320px;
    white-space:normal;
    line-height:1.35;
    word-break:break-word;
  }
  .sca-action-cell { width:110px; white-space:nowrap; }
  .sca-chip {
    display:inline-flex; align-items:center; padding:.22rem .58rem; border-radius:999px; font-size:.68rem; font-weight:900;
  }
  .sca-chip.ok { background:#e8f8ee; color:#1f7a49; }
  .sca-chip.bad { background:#fde9e8; color:#b42318; }
  .sca-chip.unknown { background:#f1f5f9; color:#64748b; }
  .sca-job-list { display:grid; gap:.75rem; }
  .sca-job-section-head {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.75rem;
    flex-wrap:wrap;
    margin-bottom:.75rem;
  }
  .sca-job-section-note { color:#8a776f; font-size:.78rem; }
  .sca-job-mini-kpis { display:flex; gap:.55rem; flex-wrap:wrap; }
  .sca-job-mini-kpi {
    min-width:110px;
    border:1px solid rgba(225,210,199,.82);
    border-radius:14px;
    background:#fffaf7;
    padding:.55rem .7rem;
  }
  .sca-job-mini-kpi .label {
    display:block;
    font-size:.65rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#8a776f;
    font-weight:800;
    margin-bottom:.15rem;
  }
  .sca-job-mini-kpi .value { font-size:1rem; font-weight:900; color:#2f2628; }
  .sca-job-card {
    border:1px solid rgba(225,210,199,.82); border-radius:16px; background:#fff; padding:.85rem .95rem;
  }
  .sca-job-top {
    display:flex; justify-content:space-between; gap:.8rem; align-items:flex-start; flex-wrap:wrap;
  }
  .sca-job-title { font-weight:900; color:#2f2628; }
  .sca-job-meta { font-size:.76rem; color:#8a776f; line-height:1.45; }
  .sca-job-meta-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:.45rem .8rem;
    margin-top:.45rem;
  }
  .sca-job-meta-item {
    font-size:.75rem;
    color:#5f524b;
    line-height:1.45;
  }
  .sca-job-meta-item .label {
    display:block;
    font-size:.64rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#9a877b;
    font-weight:800;
  }
  .sca-job-error {
    margin-top:.55rem; padding:.7rem .8rem; border-radius:14px; background:#fff1f2; color:#9f1239; font-size:.78rem;
    white-space:pre-wrap;
    word-break:break-word;
  }
  .sca-job-actions { margin-top:.75rem; display:flex; gap:.5rem; flex-wrap:wrap; }
  .sca-job-badges { display:flex; flex-wrap:wrap; gap:.35rem; }
  .sca-job-badge {
    display:inline-flex; align-items:center; padding:.2rem .56rem; border-radius:999px; font-size:.68rem; font-weight:900;
  }
  .sca-job-badge.failed { background:#fee2e2; color:#b91c1c; }
  .sca-job-badge.processing { background:#ede9fe; color:#5b21b6; }
  .sca-job-badge.queued { background:#e0f2fe; color:#075985; }
  .sca-job-badge.order { background:#f1f5f9; color:#334155; }
  @media (max-width: 1199.98px) {
    .sca-toolbar-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  }
  @media (max-width: 991.98px) {
    .sca-filter-grid, .sca-kpis, .sca-summary-grid, .sca-toolbar-grid { grid-template-columns:1fr; }
    .sca-status-cell, .sca-status-reason { min-width:0; max-width:none; }
    .sca-filter-actions { justify-content:flex-start; }
    .sca-filter-date { min-width:0; flex:1 1 100%; }
  }
</style>

<div class="sca-page">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'stock-commit-audit']); ?>

  <div class="sca-hero mb-3">
    <div class="sca-hero-main">
      <h4 class="mb-1">Audit Commit Stok POS</h4>
      <div class="sca-hero-copy">Satu tempat untuk memantau job stock commit POS yang aktif/gagal, lalu membandingkan mismatch bahan baku dan base/prepare tanpa nyasar ke halaman self-order.</div>
    </div>
    <div class="text-muted small">Tanggal acuan: <strong><?php echo html_escape($asOfDate); ?></strong></div>
  </div>

  <div class="sca-shell">
    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <form method="get" class="sca-filter-grid">
          <input type="hidden" name="tab" value="<?php echo html_escape($auditTab); ?>">
          <div class="sca-filter-primary">
            <div class="sca-filter-date">
              <label class="form-label small text-muted mb-1">Tanggal</label>
              <input type="date" name="as_of_date" value="<?php echo html_escape($asOfDate); ?>" class="form-control">
            </div>
            <div class="sca-filter-primary-actions">
              <button type="submit" class="btn btn-primary">Terapkan</button>
              <a href="<?php echo html_escape(site_url('pos/stock-commit-audit')); ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
          </div>
          <div class="sca-filter-actions">
            <a href="<?php echo html_escape(site_url('pos/stock-live')); ?>" class="btn btn-outline-secondary">Buka Stock Live POS</a>
            <button type="button" class="btn btn-outline-primary" id="sca_process_all_btn">Proses Pending</button>
            <button type="button" class="btn btn-outline-danger" id="sca_retry_failed_btn" data-failed-count="<?php echo (int)count($failedJobs); ?>">Retry Semua Gagal</button>
            <button type="button" class="btn btn-outline-secondary" id="sca_reload_jobs_btn">Refresh Job</button>
          </div>
        </form>
      </div>
    </div>

    <div class="sca-kpis">
      <div class="sca-kpi"><span class="label">Job Aktif</span><div class="value" id="sca_active_job_count"><?php echo number_format(count($activeJobs)); ?></div></div>
      <div class="sca-kpi"><span class="label">Job Gagal</span><div class="value" id="sca_failed_job_count"><?php echo number_format(count($failedJobs)); ?></div></div>
      <div class="sca-kpi"><span class="label">Snapshot FAILED</span><div class="value"><?php echo number_format(count($failedCommitSnapshots)); ?></div></div>
      <div class="sca-kpi"><span class="label">Void POS / Lot Perlu Audit</span><div class="value"><?php echo number_format((int)($voidMaterialLotAuditSummary['count'] ?? 0)); ?></div></div>
      <div class="sca-kpi"><span class="label">Mismatch Bahan Baku</span><div class="value"><?php echo number_format((int)($materialSummaryAll['mismatch_rows'] ?? 0)); ?></div></div>
      <div class="sca-kpi"><span class="label">Mismatch Base/Prepare</span><div class="value"><?php echo number_format((int)($componentSummaryAll['mismatched'] ?? 0)); ?></div></div>
      <div class="sca-kpi"><span class="label">Drift Monthly Bahan</span><div class="value"><?php echo number_format((int)($materialSummaryAll['drift_rows'] ?? 0)); ?></div></div>
      <div class="sca-kpi"><span class="label">Drift Monthly Base</span><div class="value"><?php echo number_format((int)($componentSummaryAll['drift_rows'] ?? 0)); ?></div></div>
    </div>

    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Blokir Generate Opname</div>
            <h5 class="mb-1 mt-2">Snapshot Stock Commit FAILED Bulan Berjalan</h5>
            <div class="text-muted small">
              Daftar ini dibaca langsung dari tabel <code>pos_stock_commit</code> untuk periode
              <strong><?php echo html_escape($auditMonthFrom); ?></strong> s/d
              <strong><?php echo html_escape($auditMonthTo); ?></strong>.
              Jika masih ada status <code>FAILED</code> di sini, generate stock opname inventory divisi akan ditolak.
            </div>
          </div>
          <div class="text-muted small">
            Database: <strong>pos_stock_commit</strong><br>
            Queue terkait: <strong>pos_runtime_job</strong>
          </div>
        </div>
        <div class="sca-job-list" id="sca_failed_snapshot_list">
          <?php if (empty($failedCommitSnapshots)): ?>
            <div class="text-muted small">Tidak ada snapshot stock commit FAILED pada bulan berjalan.</div>
          <?php else: ?>
            <?php foreach ($failedCommitSnapshots as $snapshot): ?>
              <?php
                $snapshotOrderStatus = strtoupper(trim((string)($snapshot['order_status'] ?? '')));
                $snapshotOrderCommitStatus = strtoupper(trim((string)($snapshot['stock_commit_status'] ?? '')));
                $latestJobStatus = strtoupper(trim((string)($snapshot['latest_job_status'] ?? '')));
                $canRetrySnapshot = !in_array($snapshotOrderStatus, ['VOID'], true)
                  && in_array($snapshotOrderCommitStatus, ['PENDING', 'FAILED', 'QUEUED', 'PROCESSING'], true);
                $canDismissSnapshot = $snapshotOrderStatus === 'VOID'
                  || in_array($snapshotOrderCommitStatus, ['POSTED', 'REVERSED', 'NOT_REQUIRED'], true);
                $snapshotError = trim((string)($snapshot['latest_job_error'] ?? ''));
                if ($snapshotError === '') {
                    $snapshotError = trim((string)($snapshot['notes'] ?? ''));
                }
                if ($snapshotError === '') {
                    $snapshotError = 'Snapshot FAILED tanpa detail error tambahan.';
                }
              ?>
              <div class="sca-job-card">
                <div class="sca-job-top">
                  <div>
                    <div class="sca-job-title"><?php echo html_escape((string)($snapshot['order_no'] ?? '-')); ?> | <?php echo html_escape((string)($snapshot['commit_no'] ?? '-')); ?></div>
                    <div class="sca-job-meta">
                      <?php echo html_escape((string)($snapshot['outlet_name'] ?? '-')); ?>
                      <?php if (trim((string)($snapshot['cashier_employee_name'] ?? '')) !== ''): ?>
                        | <?php echo html_escape((string)($snapshot['cashier_employee_name'] ?? '-')); ?>
                      <?php endif; ?>
                      | Ref: <?php echo html_escape((string)($snapshot['confirmed_at'] ?? ($snapshot['ordered_at'] ?? ($snapshot['committed_at'] ?? '-')))); ?>
                    </div>
                    <div class="sca-job-meta">
                      Order: <?php echo html_escape($snapshotOrderStatus !== '' ? $snapshotOrderStatus : '-'); ?>
                      | Stock: <?php echo html_escape($snapshotOrderCommitStatus !== '' ? $snapshotOrderCommitStatus : '-'); ?>
                      | Snapshot: <?php echo html_escape((string)($snapshot['commit_status'] ?? 'FAILED')); ?>
                      | Latest Job: <?php echo html_escape($latestJobStatus !== '' ? $latestJobStatus : 'BELUM ADA'); ?>
                    </div>
                  </div>
                  <div class="sca-job-badges">
                    <span class="sca-job-badge failed"><?php echo html_escape((string)($snapshot['commit_status'] ?? 'FAILED')); ?></span>
                    <span class="sca-job-badge order"><?php echo html_escape($snapshotOrderCommitStatus !== '' ? $snapshotOrderCommitStatus : '-'); ?></span>
                    <?php if ($latestJobStatus !== ''): ?>
                      <span class="sca-job-badge <?php echo strtolower($latestJobStatus) === 'failed' ? 'failed' : (strtolower($latestJobStatus) === 'queued' ? 'queued' : 'processing'); ?>">
                        <?php echo html_escape($latestJobStatus); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="sca-job-error"><?php echo nl2br(html_escape($snapshotError)); ?></div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                  <?php if ($canRetrySnapshot): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary sca_retry_snapshot_btn" data-snapshot-id="<?php echo (int)($snapshot['id'] ?? 0); ?>">Retry Snapshot</button>
                  <?php endif; ?>
                  <?php if ($canDismissSnapshot): ?>
                    <button type="button" class="btn btn-sm btn-outline-warning sca_dismiss_snapshot_btn" data-snapshot-id="<?php echo (int)($snapshot['id'] ?? 0); ?>" data-order-no="<?php echo html_escape((string)($snapshot['order_no'] ?? '-')); ?>" data-close-as="<?php echo html_escape($snapshotOrderStatus === 'VOID' ? 'VOID' : 'REVERSED'); ?>">Tutup Snapshot</button>
                  <?php endif; ?>
                  <a href="<?php echo html_escape(site_url('pos/stock-commit-audit?as_of_date=' . rawurlencode($asOfDate) . '&q=' . rawurlencode((string)($snapshot['order_no'] ?? '')))); ?>" class="btn btn-sm btn-outline-secondary">Filter Order Ini</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Audit Jejak Void POS</div>
            <h5 class="mb-1 mt-2">Void yang Berkaitan dengan Selisih Stok dan Lot</h5>
            <div class="text-muted small">Bagian ini hanya menunjuk kandidat penyebab: stok dan lot bahan baku berbeda, lalu pada bahan yang sama terdapat pengembalian stok dari Void POS di bulan aktif. Tidak ada data yang diubah dari halaman ini.</div>
          </div>
          <div class="text-muted small">Fokus bulan: <strong><?php echo html_escape($auditMonthFrom); ?></strong></div>
        </div>

        <?php if (empty($voidMaterialLotAudit['ok']) && !empty($voidMaterialLotAudit['message'])): ?>
          <div class="alert alert-warning mb-0"><?php echo html_escape((string)$voidMaterialLotAudit['message']); ?></div>
        <?php elseif (empty($voidMaterialLotAuditRows)): ?>
          <div class="alert alert-success mb-0">Tidak ada mismatch bahan baku bulan aktif yang berkaitan dengan pengembalian stok dari Void POS.</div>
        <?php else: ?>
          <div class="sca-table-wrap">
            <table class="table table-sm align-middle sca-table mb-0">
              <thead>
                <tr>
                  <th>Area</th>
                  <th>Bahan Baku</th>
                  <th class="text-end">Stok Sistem</th>
                  <th class="text-end">Lot Aktif</th>
                  <th class="text-end">Selisih Qty</th>
                  <th class="text-end">Selisih Nilai</th>
                  <th>Jejak Void POS</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($voidMaterialLotAuditRows as $auditRow): ?>
                  <tr>
                    <td><strong><?php echo html_escape((string)($auditRow['division_name'] ?? '-')); ?></strong><br><small class="text-muted"><?php echo html_escape((string)($auditRow['destination_group'] ?? '-')); ?></small></td>
                    <td><strong><?php echo html_escape((string)($auditRow['material_name'] ?? '-')); ?></strong></td>
                    <td class="text-end"><?php echo $fmtQty($auditRow['stock_qty'] ?? 0); ?></td>
                    <td class="text-end"><?php echo $fmtQty($auditRow['lot_qty'] ?? 0); ?></td>
                    <td class="text-end <?php echo abs((float)($auditRow['qty_delta'] ?? 0)) > 0.0001 ? 'text-danger fw-bold' : ''; ?>"><?php echo $fmtQty($auditRow['qty_delta'] ?? 0); ?></td>
                    <td class="text-end <?php echo abs((float)($auditRow['value_delta'] ?? 0)) > 0.99 ? 'text-danger fw-bold' : ''; ?>">Rp <?php echo number_format((float)($auditRow['value_delta'] ?? 0), 2, ',', '.'); ?></td>
                    <td><strong><?php echo html_escape((string)($auditRow['commit_refs'] ?? '-')); ?></strong><br><small class="text-muted">Qty balik <?php echo $fmtQty($auditRow['reversal_qty'] ?? 0); ?> | terakhir <?php echo html_escape((string)($auditRow['last_reversal_date'] ?? '-')); ?></small></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="text-muted small mt-2">Gunakan daftar ini untuk menjalankan repair lot terkontrol atau SQL repair server. Jangan melakukan adjustment jumlah stok hanya untuk menghilangkan selisih ini.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Reconcile Workspace</div>
            <h5 class="mb-1 mt-2">Bandingkan Mismatch yang Perlu Kita Bedah</h5>
            <div class="text-muted small">Tab ini bersifat umum untuk POS, bukan khusus self-order. Saat ada stock failed, biasanya mismatch bahan atau component akan lebih cepat terlihat di sini.</div>
          </div>
          <div class="sca-tabs">
            <a href="<?php echo html_escape($buildAuditUrl(['tab' => 'material', 'page' => 1])); ?>" class="sca-tab <?php echo $auditTab === 'material' ? 'is-active' : ''; ?>">
              <span>Bahan Baku</span>
              <span class="sca-tab-count"><?php echo number_format((int)($materialSummaryAll['mismatch_rows'] ?? 0)); ?></span>
            </a>
            <a href="<?php echo html_escape($buildAuditUrl(['tab' => 'component', 'page' => 1])); ?>" class="sca-tab <?php echo $auditTab === 'component' ? 'is-active' : ''; ?>">
              <span>Base/Prepare</span>
              <span class="sca-tab-count"><?php echo number_format((int)($componentSummaryAll['mismatched'] ?? 0)); ?></span>
            </a>
          </div>
        </div>

        <?php if ($auditTab === 'material'): ?>
          <form method="get" class="sca-toolbar-grid mb-3">
            <input type="hidden" name="as_of_date" value="<?php echo html_escape($asOfDate); ?>">
            <input type="hidden" name="tab" value="material">
            <div>
              <label class="form-label small text-muted mb-1">Cari</label>
              <input type="text" name="q" value="<?php echo html_escape((string)($materialFilters['q'] ?? '')); ?>" class="form-control" placeholder="Material / kode / divisi">
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Divisi</label>
              <select name="division_id" class="form-select">
                <option value="0">Semua Divisi</option>
                <?php foreach ($divisionOptions as $division): ?>
                  <option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo (int)($materialFilters['division_id'] ?? 0) === (int)($division['id'] ?? 0) ? 'selected' : ''; ?>>
                    <?php echo html_escape(trim((string)($division['name'] ?? 'Divisi'))); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Tujuan</label>
              <select name="destination" class="form-select">
                <?php foreach ((array)($materialOptions['destinations'] ?? ['ALL' => 'Semua Tujuan']) as $value => $label): ?>
                  <option value="<?php echo html_escape((string)$value); ?>" <?php echo strtoupper((string)($materialFilters['destination'] ?? 'ALL')) === strtoupper((string)$value) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Status</label>
              <select name="status" class="form-select">
                <option value="ALL" <?php echo strtoupper((string)($materialFilters['status'] ?? 'ALL')) === 'ALL' ? 'selected' : ''; ?>>Semua</option>
                <option value="MISMATCH" <?php echo strtoupper((string)($materialFilters['status'] ?? 'ALL')) === 'MISMATCH' ? 'selected' : ''; ?>>Mismatch</option>
                <option value="MATCH" <?php echo strtoupper((string)($materialFilters['status'] ?? 'ALL')) === 'MATCH' ? 'selected' : ''; ?>>Match</option>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Sumber Drift</label>
              <select name="suspect" class="form-select">
                <?php foreach ((array)($materialOptions['suspects'] ?? ['ALL' => 'Semua Status Audit']) as $value => $label): ?>
                  <option value="<?php echo html_escape((string)$value); ?>" <?php echo strtoupper((string)($materialFilters['suspect'] ?? 'ALL')) === strtoupper((string)$value) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Daily Check</label>
              <select name="daily_check" class="form-select">
                <option value="ALL" <?php echo strtoupper((string)($materialFilters['daily_check'] ?? 'ALL')) === 'ALL' ? 'selected' : ''; ?>>Semua</option>
                <option value="DRIFT" <?php echo strtoupper((string)($materialFilters['daily_check'] ?? 'ALL')) === 'DRIFT' ? 'selected' : ''; ?>>Drift</option>
                <option value="OK" <?php echo strtoupper((string)($materialFilters['daily_check'] ?? 'ALL')) === 'OK' ? 'selected' : ''; ?>>OK</option>
                <option value="UNKNOWN" <?php echo strtoupper((string)($materialFilters['daily_check'] ?? 'ALL')) === 'UNKNOWN' ? 'selected' : ''; ?>>N/A</option>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Per Halaman</label>
              <div class="d-flex gap-2">
                <select name="limit" class="form-select">
                  <?php foreach ([10, 25, 50, 100, 200] as $perPage): ?>
                    <option value="<?php echo $perPage; ?>" <?php echo (int)($materialFilters['limit'] ?? 25) === $perPage ? 'selected' : ''; ?>><?php echo $perPage; ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?php echo html_escape(site_url('pos/stock-commit-audit?as_of_date=' . rawurlencode($asOfDate) . '&tab=material')); ?>" class="btn btn-outline-secondary">Bersihkan</a>
              </div>
            </div>
          </form>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="sca-toolbar-note">Audit ini hanya membaca data. Koreksi qty melalui Daily Recon; selisih nilai melalui rekonsiliasi nilai ter-audit.</div>
            <div class="d-flex gap-2 flex-wrap">
              <a class="btn btn-outline-primary" href="<?php echo html_escape(site_url('inventory/stock/daily-recon/division')); ?>">Buka Daily Recon</a>
            </div>
          </div>
          <div class="sca-summary-grid mb-3">
            <div class="sca-summary-box"><span class="label">Total Filter</span><div class="value"><?php echo number_format((int)($materialSummary['total_rows'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Match Filter</span><div class="value"><?php echo number_format((int)($materialSummary['match_rows'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Mismatch Filter</span><div class="value"><?php echo number_format((int)($materialSummary['mismatch_rows'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Drift Daily Check</span><div class="value"><?php echo number_format((int)($materialSummary['drift_rows'] ?? 0)); ?></div></div>
          </div>
          <div class="sca-pagination-bar mb-3">
            <div class="sca-toolbar-note">
              Menampilkan <?php echo number_format((int)($materialPagination['from'] ?? 0)); ?>-<?php echo number_format((int)($materialPagination['to'] ?? 0)); ?>
              dari <?php echo number_format((int)($materialPagination['total_rows'] ?? 0)); ?> baris hasil filter.
              Total keseluruhan audit: <?php echo number_format((int)($materialSummaryAll['total_rows'] ?? 0)); ?> baris, mismatch <?php echo number_format((int)($materialSummaryAll['mismatch_rows'] ?? 0)); ?>.
            </div>
            <div>
              <?php
                echo $renderPagination($materialPagination, static function (int $pageNo) use ($buildAuditUrl, $materialFilters) {
                    return $buildAuditUrl(array_merge($materialFilters, ['tab' => 'material', 'page' => $pageNo]));
                });
              ?>
            </div>
          </div>
          <div class="sca-table-wrap">
            <table class="table table-sm align-middle sca-table mb-0">
              <thead>
                <tr>
                  <th>Material</th>
                  <th>Divisi</th>
                  <th class="text-end">Stok Divisi</th>
                  <th class="text-end">Lot Stok</th>
                  <th class="text-end">Movement Log</th>
                  <th>Daily Check</th>
                  <th>Status</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($materialRows)): ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data bahan baku.</td></tr>
                <?php else: ?>
                  <?php foreach ($materialRows as $row): ?>
                    <?php
                      $isMatch = !empty($row['is_match']);
                      $dcStatus = (string)($row['daily_check_status'] ?? 'UNKNOWN');
                      $dcCount  = (int)($row['daily_check_drift_count'] ?? 0);
                      if ($dcStatus === 'OK') { $dcClass = 'ok'; $dcLabel = 'OK'; }
                      elseif ($dcStatus === 'DRIFT') { $dcClass = 'bad'; $dcLabel = 'Drift (' . $dcCount . ')'; }
                      else { $dcClass = 'unknown'; $dcLabel = 'N/A'; }
                    ?>
                    <tr>
                      <td><strong><?php echo html_escape((string)($row['material_name'] ?? '-')); ?></strong><div class="text-muted small"><?php echo html_escape((string)($row['material_code'] ?? '-')); ?></div></td>
                      <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?><div class="text-muted small"><?php echo html_escape((string)($row['destination_name'] ?? 'Reguler')); ?></div></td>
                      <td class="text-end"><?php echo $fmtQty($row['balance_qty_content'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['lot_qty_content'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['movement_qty_content'] ?? 0); ?></td>
                      <td><span class="sca-chip <?php echo $dcClass; ?>"><?php echo html_escape($dcLabel); ?></span></td>
                      <td class="sca-status-cell">
                        <span class="sca-chip <?php echo $isMatch ? 'ok' : 'bad'; ?>"><?php echo $isMatch ? 'Match' : 'Mismatch'; ?></span>
                        <?php if (!$isMatch): ?>
                          <div class="text-muted small mt-1 sca-status-reason"><?php echo html_escape((string)($row['suspect_reason'] ?? '')); ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end sca-action-cell"><a href="<?php echo html_escape(site_url('inventory/stock/division/reconcile?as_of_date=' . rawurlencode($asOfDate) . '&division_id=' . (int)($row['division_id'] ?? 0) . '&q=' . rawurlencode((string)($row['material_name'] ?? '')))); ?>" class="btn btn-sm btn-outline-primary">Buka Audit</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <form method="get" class="sca-toolbar-grid mb-3">
            <input type="hidden" name="as_of_date" value="<?php echo html_escape($asOfDate); ?>">
            <input type="hidden" name="tab" value="component">
            <div>
              <label class="form-label small text-muted mb-1">Cari</label>
              <input type="text" name="q" value="<?php echo html_escape((string)($componentFilters['q'] ?? '')); ?>" class="form-control" placeholder="Component / kode / divisi">
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Divisi</label>
              <select name="division_id" class="form-select">
                <option value="0">Semua Divisi</option>
                <?php foreach ($divisionOptions as $division): ?>
                  <option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo (int)($componentFilters['division_id'] ?? 0) === (int)($division['id'] ?? 0) ? 'selected' : ''; ?>>
                    <?php echo html_escape(trim((string)($division['name'] ?? 'Divisi'))); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Lokasi</label>
              <select name="location_type" class="form-select">
                <?php foreach ((array)($componentOptions['locations'] ?? ['ALL' => 'Semua Lokasi']) as $value => $label): ?>
                  <option value="<?php echo html_escape((string)$value); ?>" <?php echo strtoupper((string)($componentFilters['location_type'] ?? 'ALL')) === strtoupper((string)$value) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Tipe</label>
              <select name="type" class="form-select">
                <?php foreach ((array)($componentOptions['types'] ?? ['ALL' => 'Semua Tipe']) as $value => $label): ?>
                  <option value="<?php echo html_escape((string)$value); ?>" <?php echo strtoupper((string)($componentFilters['type'] ?? 'ALL')) === strtoupper((string)$value) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Status</label>
              <select name="status" class="form-select">
                <option value="ALL" <?php echo strtoupper((string)($componentFilters['status'] ?? 'ALL')) === 'ALL' ? 'selected' : ''; ?>>Semua</option>
                <option value="MISMATCH" <?php echo strtoupper((string)($componentFilters['status'] ?? 'ALL')) === 'MISMATCH' ? 'selected' : ''; ?>>Mismatch</option>
                <option value="MATCH" <?php echo strtoupper((string)($componentFilters['status'] ?? 'ALL')) === 'MATCH' ? 'selected' : ''; ?>>Match</option>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Daily Check</label>
              <select name="daily_check" class="form-select">
                <option value="ALL" <?php echo strtoupper((string)($componentFilters['daily_check'] ?? 'ALL')) === 'ALL' ? 'selected' : ''; ?>>Semua</option>
                <option value="DRIFT" <?php echo strtoupper((string)($componentFilters['daily_check'] ?? 'ALL')) === 'DRIFT' ? 'selected' : ''; ?>>Drift</option>
                <option value="OK" <?php echo strtoupper((string)($componentFilters['daily_check'] ?? 'ALL')) === 'OK' ? 'selected' : ''; ?>>OK</option>
                <option value="UNKNOWN" <?php echo strtoupper((string)($componentFilters['daily_check'] ?? 'ALL')) === 'UNKNOWN' ? 'selected' : ''; ?>>N/A</option>
              </select>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Per Halaman</label>
              <div class="d-flex gap-2">
                <select name="limit" class="form-select">
                  <?php foreach ([10, 25, 50, 100, 200] as $perPage): ?>
                    <option value="<?php echo $perPage; ?>" <?php echo (int)($componentFilters['limit'] ?? 25) === $perPage ? 'selected' : ''; ?>><?php echo $perPage; ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?php echo html_escape(site_url('pos/stock-commit-audit?as_of_date=' . rawurlencode($asOfDate) . '&tab=component')); ?>" class="btn btn-outline-secondary">Bersihkan</a>
              </div>
            </div>
          </form>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="sca-toolbar-note">Repair otomatis tidak mengubah saldo component. Gunakan Daily Recon atau rekonsiliasi nilai sesuai sumber selisih.</div>
            <div class="d-flex gap-2 flex-wrap">
              <span class="text-muted small">Repair massal dinonaktifkan untuk menjaga histori stok.</span>
            </div>
          </div>
          <div class="sca-summary-grid mb-3">
            <div class="sca-summary-box"><span class="label">Total Filter</span><div class="value"><?php echo number_format((int)($componentSummary['total'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Match Filter</span><div class="value"><?php echo number_format((int)($componentSummary['matched'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Mismatch Filter</span><div class="value"><?php echo number_format((int)($componentSummary['mismatched'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Drift Daily Check</span><div class="value"><?php echo number_format((int)($componentSummary['drift_rows'] ?? 0)); ?></div></div>
          </div>
          <div class="sca-pagination-bar mb-3">
            <div class="sca-toolbar-note">
              Menampilkan <?php echo number_format((int)($componentPagination['from'] ?? 0)); ?>-<?php echo number_format((int)($componentPagination['to'] ?? 0)); ?>
              dari <?php echo number_format((int)($componentPagination['total_rows'] ?? 0)); ?> baris hasil filter.
              Total keseluruhan audit: <?php echo number_format((int)($componentSummaryAll['total'] ?? 0)); ?> baris, mismatch <?php echo number_format((int)($componentSummaryAll['mismatched'] ?? 0)); ?>.
            </div>
            <div>
              <?php
                echo $renderPagination($componentPagination, static function (int $pageNo) use ($buildAuditUrl, $componentFilters) {
                    return $buildAuditUrl(array_merge($componentFilters, ['tab' => 'component', 'page' => $pageNo]));
                });
              ?>
            </div>
          </div>
          <div class="sca-table-wrap">
            <table class="table table-sm align-middle sca-table mb-0">
              <thead>
                <tr>
                  <th>Component</th>
                  <th>Lokasi</th>
                  <th class="text-end">Stok Live</th>
                  <th class="text-end">Lot Stok</th>
                  <th class="text-end">Movement Log</th>
                  <th>Daily Check</th>
                  <th>Status</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($componentRows)): ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data base/prepare.</td></tr>
                <?php else: ?>
                  <?php foreach ($componentRows as $row): ?>
                    <?php
                      $isMatch = !empty($row['is_match']);
                      $dcStatus = (string)($row['daily_check_status'] ?? 'UNKNOWN');
                      $dcCount  = (int)($row['daily_check_drift_count'] ?? 0);
                      if ($dcStatus === 'OK') { $dcClass = 'ok'; $dcLabel = 'OK'; }
                      elseif ($dcStatus === 'DRIFT') { $dcClass = 'bad'; $dcLabel = 'Drift (' . $dcCount . ')'; }
                      else { $dcClass = 'unknown'; $dcLabel = 'N/A'; }
                    ?>
                    <tr>
                      <td><strong><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></strong><div class="text-muted small"><?php echo html_escape((string)($row['component_code'] ?? '-')); ?> | <?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></div></td>
                      <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?><div class="text-muted small"><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></div></td>
                      <td class="text-end"><?php echo $fmtQty($row['balance_qty'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['lot_qty'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['movement_qty'] ?? 0); ?></td>
                      <td><span class="sca-chip <?php echo $dcClass; ?>"><?php echo html_escape($dcLabel); ?></span></td>
                      <td class="sca-status-cell">
                        <span class="sca-chip <?php echo $isMatch ? 'ok' : 'bad'; ?>"><?php echo $isMatch ? 'Match' : 'Mismatch'; ?></span>
                        <?php if (!$isMatch): ?>
                          <div class="text-muted small mt-1 sca-status-reason"><?php echo html_escape((string)($row['suspect_reason'] ?? '')); ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end sca-action-cell"><a href="<?php echo html_escape(site_url('production/component-reconcile?as_of_date=' . rawurlencode($asOfDate) . '&division_id=' . (int)($row['division_id'] ?? 0) . '&q=' . rawurlencode((string)($row['component_name'] ?? '')))); ?>" class="btn btn-sm btn-outline-primary">Buka Audit</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Job Runtime POS</div>
            <h5 class="mb-1 mt-2">Queue Stock Commit yang Perlu Perhatian</h5>
            <div class="text-muted small">Daftar ini umum untuk semua order POS, termasuk kasir biasa dan self-order. Saat retry dari sini, backend akan memakai service runtime yang sama dengan stock-live.</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="sca-job-section-head">
              <div>
                <div class="small text-uppercase fw-bold text-muted mb-1">Sedang Antre / Diproses</div>
                <div class="sca-job-section-note">Job yang masih antri atau sedang berjalan. Jika macet lebih dari 5 menit, backend akan memperlakukannya sebagai kandidat retry.</div>
              </div>
              <div class="sca-job-mini-kpis">
                <div class="sca-job-mini-kpi"><span class="label">Aktif</span><div class="value"><?php echo number_format(count($activeJobs)); ?></div></div>
              </div>
            </div>
            <div class="sca-job-list" id="sca_active_job_list">
              <?php if (empty($activeJobs)): ?>
                <div class="text-muted small">Belum ada job aktif.</div>
              <?php else: ?>
                <?php foreach ($activeJobs as $job): ?>
                  <div class="sca-job-card">
                    <div class="sca-job-top">
                      <div>
                        <div class="sca-job-title"><?php echo html_escape((string)($job['order_no'] ?? '-')); ?></div>
                        <div class="sca-job-meta"><?php echo html_escape((string)($job['job_code'] ?? '-')); ?> | <?php echo html_escape((string)($job['commit_no'] ?? '-')); ?></div>
                        <div class="sca-job-meta-grid">
                          <div class="sca-job-meta-item"><span class="label">Outlet / Kasir</span><?php echo html_escape((string)($job['outlet_name'] ?? '-')); ?> | <?php echo html_escape((string)($job['cashier_employee_name'] ?? '-')); ?></div>
                          <div class="sca-job-meta-item"><span class="label">Percobaan</span><?php echo (int)($job['attempts'] ?? 0); ?> / <?php echo (int)($job['max_attempts'] ?? 0); ?></div>
                          <div class="sca-job-meta-item"><span class="label">Snapshot</span><?php echo html_escape((string)($job['commit_no'] ?? '-')); ?> (ID <?php echo (int)($job['snapshot_id'] ?? 0); ?>)</div>
                          <div class="sca-job-meta-item"><span class="label">Waktu Mulai</span><?php echo html_escape((string)($job['started_at'] ?? ($job['created_at'] ?? '-'))); ?></div>
                        </div>
                      </div>
                      <div class="sca-job-badges">
                        <span class="sca-job-badge <?php echo strtolower((string)($job['status'] ?? 'queued')); ?>"><?php echo html_escape((string)($job['status'] ?? '-')); ?></span>
                        <span class="sca-job-badge order"><?php echo html_escape((string)($job['stock_commit_status'] ?? '-')); ?></span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="sca-job-section-head">
              <div>
                <div class="small text-uppercase fw-bold text-muted mb-1">Job Gagal</div>
                <div class="sca-job-section-note">Kalau retry masih gagal, pesan di bawah ini sudah memuat error backend yang lebih mentah agar sumber masalah cepat ditemukan.</div>
              </div>
              <div class="sca-job-mini-kpis">
                <div class="sca-job-mini-kpi"><span class="label">Gagal</span><div class="value"><?php echo number_format(count($failedJobs)); ?></div></div>
                <div class="sca-job-mini-kpi"><span class="label">Snapshot FAILED</span><div class="value"><?php echo number_format(count($failedCommitSnapshots)); ?></div></div>
              </div>
            </div>
            <div class="sca-job-list" id="sca_failed_job_list">
              <?php if (empty($failedJobs)): ?>
                <div class="text-muted small">Belum ada job gagal.</div>
              <?php else: ?>
                <?php foreach ($failedJobs as $job): ?>
                  <div class="sca-job-card">
                    <div class="sca-job-top">
                      <div>
                        <div class="sca-job-title"><?php echo html_escape((string)($job['order_no'] ?? '-')); ?></div>
                        <div class="sca-job-meta"><?php echo html_escape((string)($job['job_code'] ?? '-')); ?> | <?php echo html_escape((string)($job['commit_no'] ?? '-')); ?></div>
                        <div class="sca-job-meta-grid">
                          <div class="sca-job-meta-item"><span class="label">Outlet / Kasir</span><?php echo html_escape((string)($job['outlet_name'] ?? '-')); ?> | <?php echo html_escape((string)($job['cashier_employee_name'] ?? '-')); ?></div>
                          <div class="sca-job-meta-item"><span class="label">Percobaan</span><?php echo (int)($job['attempts'] ?? 0); ?> / <?php echo (int)($job['max_attempts'] ?? 0); ?></div>
                          <div class="sca-job-meta-item"><span class="label">Snapshot</span><?php echo html_escape((string)($job['commit_no'] ?? '-')); ?> (ID <?php echo (int)($job['snapshot_id'] ?? 0); ?>)</div>
                          <div class="sca-job-meta-item"><span class="label">Retry Setelah</span><?php echo html_escape((string)($job['run_after'] ?? '-')); ?></div>
                        </div>
                      </div>
                      <div class="sca-job-badges">
                        <span class="sca-job-badge failed"><?php echo html_escape((string)($job['status'] ?? 'FAILED')); ?></span>
                        <span class="sca-job-badge order"><?php echo html_escape((string)($job['stock_commit_status'] ?? '-')); ?></span>
                      </div>
                    </div>
                    <div class="sca-job-error"><?php echo nl2br(html_escape((string)($job['last_error'] ?? '-'))); ?></div>
                    <div class="sca-job-actions">
                      <button type="button" class="btn btn-sm btn-outline-primary sca_retry_job_btn" data-job-id="<?php echo (int)($job['id'] ?? 0); ?>">Retry</button>
                      <a href="<?php echo html_escape(site_url('pos/orders/draft?q=' . rawurlencode((string)($job['order_no'] ?? '')))); ?>" class="btn btn-sm btn-outline-secondary">Buka Order</a>
                      <?php $jobOrderStatus = strtoupper(trim((string)($job['order_status'] ?? ''))); ?>
                      <?php if (in_array($jobOrderStatus, ['DRAFT', 'PENDING', 'CONFIRMED'], true)): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger sca_delete_draft_job_btn"
                                data-job-id="<?php echo (int)($job['id'] ?? 0); ?>"
                                data-order-no="<?php echo html_escape((string)($job['order_no'] ?? '-')); ?>"
                                data-order-status="<?php echo html_escape($jobOrderStatus); ?>">
                          <?php echo $jobOrderStatus === 'CONFIRMED' ? 'Hapus Order' : 'Hapus Draft'; ?>
                        </button>
                      <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-warning sca_dismiss_failed_job_btn"
                                data-job-id="<?php echo (int)($job['id'] ?? 0); ?>"
                                data-order-no="<?php echo html_escape((string)($job['order_no'] ?? '-')); ?>">
                          Tutup Job
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const scaPage = document.querySelector('.sca-page');
  if (scaPage) {
    const container = scaPage.closest('.container-xxl');
    const layoutPage = scaPage.closest('.layout-page');
    const contentWrapper = scaPage.closest('.content-wrapper');
    if (container) {
      container.style.minWidth = '0';
      container.style.overflowX = 'hidden';
    }
    if (layoutPage) {
      layoutPage.style.minWidth = '0';
    }
    if (contentWrapper) {
      contentWrapper.style.minWidth = '0';
      contentWrapper.style.overflowX = 'hidden';
    }
  }
  const stockCommitAuditCsrfToken = <?php echo json_encode((string)($stock_commit_audit_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const posTransactionCsrfToken = <?php echo json_encode((string)($pos_transaction_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  async function postJson(url, payload, scopedOptions) {
    const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (scopedOptions && scopedOptions.headers && typeof scopedOptions.headers === 'object') {
      Object.assign(headers, scopedOptions.headers);
    }
    const response = await fetch(url, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memproses data.');
    return json;
  }
  function postStockCommitAuditRepairJson(url, payload) {
    return postJson(url, payload, {
      headers: { 'X-Pos-Stock-Commit-CSRF': stockCommitAuditCsrfToken }
    });
  }
  async function postPosTransactionJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Pos-Transaction-CSRF': posTransactionCsrfToken },
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memproses aksi.');
    return json;
  }
  function showAlert(message, title) {
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return Promise.resolve(window.FinanceUI.alert(message, { title: title || 'Informasi' }));
    }
    alert(message);
    return Promise.resolve();
  }
  function askConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      return Promise.resolve(window.FinanceUI.confirm(message, options || {}));
    }
    return Promise.resolve(window.confirm(message));
  }
  function setButtonLoading(button, label) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (label || 'Memproses...');
  }
  function clearButtonLoading(button) {
    if (!button) return;
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

  document.querySelectorAll('.sca_retry_job_btn').forEach((button) => {
    button.addEventListener('click', async function () {
      const jobId = Number(this.dataset.jobId || 0);
      if (jobId <= 0) return;
      const confirmed = await askConfirm('Retry job stock commit POS ini sekarang?', { title: 'Retry Stock Commit POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Retry...');
      try {
        const json = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/' + jobId, {});
        await showAlert(json.message || 'Job berhasil diantrekan ulang.', 'Retry Stock Commit POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal retry job stock commit POS.', 'Retry Stock Commit POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  });

  document.querySelectorAll('.sca_delete_draft_job_btn').forEach((button) => {
    button.addEventListener('click', async function () {
      const jobId = Number(this.dataset.jobId || 0);
      const orderNo = String(this.dataset.orderNo || '-');
      const orderStatus = String(this.dataset.orderStatus || '');
      if (jobId <= 0) return;
      const isConfirmed = orderStatus === 'CONFIRMED';
      const confirmMsg = isConfirmed
        ? 'Hapus order ' + orderNo + ' (CONFIRMED, belum bayar) beserta job stock commit yang gagal? Pastikan tidak ada pembayaran terkait order ini.'
        : 'Hapus draft ' + orderNo + ' beserta snapshot/job stock commit yang gagal? Aksi ini dipakai untuk order yang masih DRAFT/PENDING.';
      const confirmTitle = isConfirmed ? 'Hapus Order dari Audit' : 'Hapus Draft dari Audit';
      const confirmed = await askConfirm(confirmMsg, { title: confirmTitle });
      if (!confirmed) return;
      setButtonLoading(this, 'Menghapus...');
      try {
        const json = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/delete-draft'); ?>/' + jobId, {});
        await showAlert(json.message || 'Order berhasil dihapus.', confirmTitle);
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Order gagal dihapus dari audit.', confirmTitle);
      } finally {
        clearButtonLoading(this);
      }
    });
  });

  document.querySelectorAll('.sca_dismiss_failed_job_btn').forEach((button) => {
    button.addEventListener('click', async function () {
      const jobId = Number(this.dataset.jobId || 0);
      const orderNo = String(this.dataset.orderNo || '-');
      if (jobId <= 0) return;
      const confirmed = await askConfirm(
        'Tutup job gagal untuk order ' + orderNo + '? Gunakan ini jika order sudah VOID / tidak perlu diproses ulang.',
        { title: 'Tutup Job Gagal POS' }
      );
      if (!confirmed) return;
      setButtonLoading(this, 'Menutup...');
      try {
        const json = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/dismiss'); ?>/' + jobId, {});
        await showAlert(json.message || 'Job gagal berhasil ditutup.', 'Tutup Job Gagal POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Job gagal tidak bisa ditutup.', 'Tutup Job Gagal POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  });

  document.querySelectorAll('.sca_retry_snapshot_btn').forEach((button) => {
    button.addEventListener('click', async function () {
      const snapshotId = Number(this.dataset.snapshotId || 0);
      if (snapshotId <= 0) return;
      const confirmed = await askConfirm(
        'Refresh snapshot FAILED ini dari order terbaru, lalu antrekan ulang stock commit sekarang?',
        { title: 'Retry Snapshot FAILED POS' }
      );
      if (!confirmed) return;
      setButtonLoading(this, 'Retry snapshot...');
      try {
        const json = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-snapshots/retry'); ?>/' + snapshotId, {});
        await showAlert(json.message || 'Snapshot FAILED berhasil diproses ulang.', 'Retry Snapshot FAILED POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Snapshot FAILED tidak bisa di-retry.', 'Retry Snapshot FAILED POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  });

  document.querySelectorAll('.sca_dismiss_snapshot_btn').forEach((button) => {
    button.addEventListener('click', async function () {
      const snapshotId = Number(this.dataset.snapshotId || 0);
      const orderNo = String(this.dataset.orderNo || '-');
      const closeAs = String(this.dataset.closeAs || 'REVERSED');
      if (snapshotId <= 0) return;
      const confirmed = await askConfirm(
        'Tutup snapshot FAILED untuk order ' + orderNo + ' sebagai ' + closeAs + '? Gunakan ini hanya bila order sudah final dan memang tidak perlu diproses ulang.',
        { title: 'Tutup Snapshot FAILED POS' }
      );
      if (!confirmed) return;
      setButtonLoading(this, 'Menutup snapshot...');
      try {
        const json = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-snapshots/dismiss'); ?>/' + snapshotId, {});
        await showAlert(json.message || 'Snapshot FAILED berhasil ditutup.', 'Tutup Snapshot FAILED POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Snapshot FAILED tidak bisa ditutup.', 'Tutup Snapshot FAILED POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  });

  const processAllBtn = document.getElementById('sca_process_all_btn');
  if (processAllBtn) {
    processAllBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm('Proses seluruh job pending stock commit POS sekarang?', { title: 'Proses Pending Job POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Memproses...');
      try {
        const json = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/process-all'); ?>', { limit: 10 });
        await showAlert(
          'Processed: ' + Number((json.processed_count || 0)) + '\n'
          + 'Success: ' + Number((json.success_count || 0)) + '\n'
          + 'Failed: ' + Number((json.failed_count || 0)),
          'Job Runtime POS'
        );
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal memproses pending job POS.', 'Job Runtime POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const retryFailedBtn = document.getElementById('sca_retry_failed_btn');
  if (retryFailedBtn) {
    retryFailedBtn.addEventListener('click', async function () {
      const totalFailed = Number(this.dataset.failedCount || 0);
      const confirmed = await askConfirm('Retry semua job FAILED yang tampil sekarang (' + totalFailed + ' job)? Jalankan ini setelah repair data/backend selesai diterapkan.', { title: 'Retry Semua Job Gagal POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Retry semua...');
      try {
        const json = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/retry-failed-all'); ?>', { limit: totalFailed > 0 ? totalFailed : 50 });
        await showAlert(scaBatchRetryDetail(json), 'Retry Semua Job Gagal POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal retry semua job FAILED.', 'Retry Semua Job Gagal POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const reloadBtn = document.getElementById('sca_reload_jobs_btn');
  if (reloadBtn) {
    reloadBtn.addEventListener('click', function () {
      window.location.reload();
    });
  }

  function scaBatchRepairDetail(json) {
    var lines = [
      'Diproses: ' + Number(json.processed_count || 0),
      'Berhasil: ' + Number(json.success_count || 0),
      'Gagal: '    + Number(json.failed_count   || 0),
    ];
    var results = Array.isArray(json.results) ? json.results : [];
    var failed = results.filter(function(r){ return !(r.result && r.result.ok); });
    if (failed.length) {
      lines.push('');
      lines.push('Detail gagal:');
      failed.slice(0, 10).forEach(function(r) {
        var msg = (r.result && r.result.message) ? r.result.message : 'tidak ada detail';
        lines.push('• ' + String(r.label || '-') + ': ' + msg);
      });
      if (failed.length > 10) lines.push('... dan ' + (failed.length - 10) + ' lainnya.');
    }
    return lines.join('\n');
  }

  function scaBatchRetryDetail(json) {
    var lines = [
      'Diproses: ' + Number(json.processed_count || 0),
      'Berhasil: ' + Number(json.success_count || 0),
      'Gagal: ' + Number(json.failed_count || 0)
    ];
    var jobs = Array.isArray(json.jobs) ? json.jobs : [];
    var failed = jobs.filter(function (row) { return !row.ok; });
    if (failed.length) {
      lines.push('');
      lines.push('Contoh gagal:');
      failed.slice(0, 10).forEach(function (row) {
        lines.push('• ' + String(row.order_no || ('Job #' + String(row.job_id || '-'))) + ': ' + String(row.message || 'tanpa detail'));
      });
      if (failed.length > 10) {
        lines.push('... dan ' + (failed.length - 10) + ' job gagal lainnya.');
      }
    }
    return lines.join('\n');
  }

  const repairMaterialBtn = document.getElementById('sca_repair_material_btn');
  if (repairMaterialBtn) {
    repairMaterialBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm('Repair semua mismatch bahan baku yang sedang sesuai filter aktif?', { title: 'Batch Repair Bahan Baku POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Repair bahan...');
      try {
        const payload = <?php echo json_encode([
            'as_of_date' => $asOfDate,
            'q' => (string)($materialFilters['q'] ?? ''),
            'division_id' => (int)($materialFilters['division_id'] ?? 0),
            'destination' => (string)($materialFilters['destination'] ?? 'ALL'),
            'suspect' => (string)($materialFilters['suspect'] ?? 'ALL'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const json = await postStockCommitAuditRepairJson('<?php echo site_url('pos/stock-commit-audit/repair-material-mismatches'); ?>', payload);
        await showAlert(scaBatchRepairDetail(json), 'Batch Repair Bahan Baku POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal repair batch mismatch bahan baku.', 'Batch Repair Bahan Baku POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const repairComponentBtn = document.getElementById('sca_repair_component_btn');
  if (repairComponentBtn) {
    repairComponentBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm('Repair semua mismatch base/prepare yang sedang sesuai filter aktif?', { title: 'Batch Repair Base/Prepare POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Repair component...');
      try {
        const payload = <?php echo json_encode([
            'as_of_date' => $asOfDate,
            'q' => (string)($componentFilters['q'] ?? ''),
            'division_id' => (int)($componentFilters['division_id'] ?? 0),
            'location_type' => (string)($componentFilters['location_type'] ?? 'ALL'),
            'type' => (string)($componentFilters['type'] ?? 'ALL'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const json = await postStockCommitAuditRepairJson('<?php echo site_url('pos/stock-commit-audit/repair-component-mismatches'); ?>', payload);
        await showAlert(scaBatchRepairDetail(json), 'Batch Repair Base/Prepare POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal repair batch mismatch base/prepare.', 'Batch Repair Base/Prepare POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const repairMaterialDriftBtn = document.getElementById('sca_repair_material_drift_btn');
  if (repairMaterialDriftBtn) {
    repairMaterialDriftBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm(
        'Repair drift monthly stock bahan baku?\n\nDrift akan diserap ke kolom variance agar equation monthly stock seimbang. Closing qty tidak diubah.',
        { title: 'Repair Drift Monthly Stock Bahan' }
      );
      if (!confirmed) return;
      setButtonLoading(this, 'Repair drift...');
      try {
        const payload = <?php echo json_encode([
            'as_of_date' => $asOfDate,
            'q' => (string)($materialFilters['q'] ?? ''),
            'division_id' => (int)($materialFilters['division_id'] ?? 0),
            'destination' => (string)($materialFilters['destination'] ?? 'ALL'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const json = await postStockCommitAuditRepairJson('<?php echo site_url('pos/stock-commit-audit/repair-material-drift'); ?>', payload);
        await showAlert(scaBatchRepairDetail(json), 'Repair Drift Monthly Stock Bahan');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal repair drift monthly stock bahan.', 'Repair Drift Monthly Stock Bahan');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const repairMaterialLotBtn = document.getElementById('sca_repair_material_lot_btn');
  if (repairMaterialLotBtn) {
    repairMaterialLotBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm(
        'Repair lot FIFO semua bahan baku yang mismatch sesuai filter aktif?\n\nProses ini otomatis menjalankan profile sync jika ada profil silang.',
        { title: 'Repair Lot Semua Bahan POS' }
      );
      if (!confirmed) return;
      setButtonLoading(this, 'Repair lot...');
      try {
        const payload = <?php echo json_encode([
            'as_of_date' => $asOfDate,
            'q' => (string)($materialFilters['q'] ?? ''),
            'division_id' => (int)($materialFilters['division_id'] ?? 0),
            'destination' => (string)($materialFilters['destination'] ?? 'ALL'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const json = await postJson('<?php echo site_url('inventory/stock/division/reconcile/lot-repair-all'); ?>', payload);
        var d = json.data || {};
        var detail = json.message || 'Selesai.';
        if ((d.failed || 0) > 0 && Array.isArray(d.results)) {
          var failLines = d.results.filter(function(r){ return r.status === 'failed'; })
            .map(function(r){ return '• ' + r.label + (r.message ? ' — ' + r.message : ''); });
          if (failLines.length) detail += '\n\nGagal:\n' + failLines.join('\n');
        }
        await showAlert(detail, 'Repair Lot Semua Bahan POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal repair lot bahan.', 'Repair Lot Semua Bahan POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const repairComponentDriftBtn = document.getElementById('sca_repair_component_drift_btn');
  if (repairComponentDriftBtn) {
    repairComponentDriftBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm(
        'Repair drift monthly stock base/prepare?\n\nDrift akan diserap ke kolom adjustment agar equation monthly stock seimbang. Closing qty tidak diubah.',
        { title: 'Repair Drift Monthly Stock Base/Prepare' }
      );
      if (!confirmed) return;
      setButtonLoading(this, 'Repair drift...');
      try {
        const payload = <?php echo json_encode([
            'as_of_date' => $asOfDate,
            'q' => (string)($componentFilters['q'] ?? ''),
            'division_id' => (int)($componentFilters['division_id'] ?? 0),
            'location_type' => (string)($componentFilters['location_type'] ?? 'ALL'),
            'type' => (string)($componentFilters['type'] ?? 'ALL'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const json = await postStockCommitAuditRepairJson('<?php echo site_url('pos/stock-commit-audit/repair-component-drift'); ?>', payload);
        await showAlert(scaBatchRepairDetail(json), 'Repair Drift Monthly Stock Base/Prepare');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal repair drift monthly stock base/prepare.', 'Repair Drift Monthly Stock Base/Prepare');
      } finally {
        clearButtonLoading(this);
      }
    });
  }
});
</script>
