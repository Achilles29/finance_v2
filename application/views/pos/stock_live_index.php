<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
$divisions = is_array($filterOptions['divisions'] ?? null) ? $filterOptions['divisions'] : [];
?>
<style>
  .pos-stock-live-page {
    background:
      radial-gradient(circle at top left, rgba(255, 227, 202, .36), transparent 26%),
      linear-gradient(180deg, #fff9f5 0%, #fff 22%, #fffdfa 100%);
    min-height: calc(100vh - 120px);
    border-radius: 30px;
    padding: 1rem;
  }
  .pos-stock-live-hero {
    border: 1px solid #f0dfd2;
    border-radius: 26px;
    background: linear-gradient(135deg, rgba(255,255,255,.98) 0%, rgba(255,246,238,.98) 100%);
    box-shadow: 0 18px 48px rgba(126, 73, 35, .08);
    padding: 1.15rem 1.2rem;
  }
  .pos-stock-live-hero-title {
    font-size: 1.68rem;
    font-weight: 800;
    color: #3b261c;
    letter-spacing: -.02em;
  }
  .pos-stock-live-hero-copy {
    color: #7f6658;
    max-width: 920px;
  }
  .pos-stock-live-chip {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .5rem .8rem;
    border-radius: 999px;
    border: 1px solid #ead3c6;
    background: #fff;
    color: #7c6053;
    font-size: .82rem;
    font-weight: 700;
  }
  .pos-stock-live-shell {
    border: 1px solid #f0dfd2;
    border-radius: 24px;
    background: linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    box-shadow: 0 16px 42px rgba(126, 73, 35, .08);
  }
  .pos-stock-live-filter-box {
    border: 1px solid #efddd1;
    border-radius: 22px;
    background: rgba(255,255,255,.9);
    padding: .95rem;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.65);
  }
  .pos-stock-live-metric {
    border: 1px solid #efddd1;
    border-radius: 20px;
    background: linear-gradient(180deg, #fffefd 0%, #fff6ef 100%);
    padding: .95rem 1rem;
    min-height: 108px;
  }
  .pos-stock-live-metric-label {
    color: #8d7366;
    text-transform: uppercase;
    font-size: .74rem;
    letter-spacing: .08em;
    font-weight: 800;
  }
  .pos-stock-live-metric-note {
    color: #8b7469;
    font-size: .78rem;
  }
  .pos-stock-live-summary {
    font-size: .82rem;
    color: #7b655b;
  }
  .pos-stock-live-table-wrap {
    border: 1px solid #efddd1;
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
  }
  .pos-stock-live-table thead th {
    font-size: .79rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: #7c6053;
    border-bottom-color: #ecd9cd;
    background: linear-gradient(180deg, #fff7f1 0%, #fff 100%);
    position: sticky;
    top: 0;
    z-index: 1;
  }
  .pos-stock-live-table tbody td {
    border-bottom-color: #f3e7df;
    padding-top: .9rem;
    padding-bottom: .9rem;
    vertical-align: top;
  }
  .pos-stock-live-table tbody tr:hover {
    background: #fffaf6;
  }
  .pos-stock-live-badge {
    display: inline-flex;
    align-items: center;
    padding: .25rem .65rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: .78rem;
  }
  .pos-stock-live-badge.available { background: #e9f8ef; color: #1e7a45; }
  .pos-stock-live-badge.limited { background: #fff3dd; color: #8d5e00; }
  .pos-stock-live-badge.out { background: #fde8e8; color: #b42318; }
  .pos-stock-live-badge.hidden { background: #eef2f6; color: #475467; }
  .pos-stock-live-badge.mismatch { background: #fff1f3; color: #c01048; }
  .pos-stock-live-badge.match { background: #eefaf1; color: #1f7a3d; }
  .pos-runtime-job-shell {
    border: 1px solid #efddd1;
    border-radius: 20px;
    padding: 1rem 1.1rem;
    background: linear-gradient(135deg, #fffaf7 0%, #fff 100%);
  }
  .pos-runtime-job-list {
    display: grid;
    gap: .75rem;
  }
  .pos-runtime-job-card {
    border: 1px solid rgba(224,209,198,.78);
    border-radius: 16px;
    padding: .82rem .95rem;
    background: #fff;
  }
  .pos-runtime-job-top {
    display: flex;
    justify-content: space-between;
    gap: .8rem;
    align-items: flex-start;
    flex-wrap: wrap;
  }
  .pos-runtime-job-title {
    font-weight: 800;
    color: #382a2b;
  }
  .pos-runtime-job-meta {
    font-size: .78rem;
    color: #8a776d;
  }
  .pos-runtime-job-error {
    margin-top: .55rem;
    padding: .62rem .74rem;
    border-radius: 12px;
    background: #fff1f2;
    color: #9f1239;
    font-size: .78rem;
    line-height: 1.45;
  }
  .pos-runtime-job-badges {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    justify-content: flex-end;
  }
  .pos-runtime-job-badge {
    display: inline-flex;
    align-items: center;
    gap: .28rem;
    padding: .2rem .55rem;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 900;
  }
  .pos-runtime-job-badge.failed { background: #fee2e2; color: #b91c1c; }
  .pos-runtime-job-badge.queued { background: #e0f2fe; color: #075985; }
  .pos-runtime-job-badge.processing { background: #ede9fe; color: #5b21b6; }
  .pos-runtime-job-badge.success { background: #dcfce7; color: #166534; }
  .pos-runtime-job-badge.order { background: #f1f5f9; color: #334155; }
  .pos-stock-live-note {
    font-size: .82rem;
    color: #8a7063;
  }
  .pos-stock-live-kv {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: .18rem .55rem;
    align-items: start;
  }
  .pos-stock-live-kv strong {
    color: #4c3429;
    font-weight: 800;
  }
  .pos-stock-live-tabs .btn.active {
    background: #1f2937;
    color: #fff;
    border-color: #1f2937;
  }
  .pos-stock-live-section-tabs {
    display: flex;
    gap: .55rem;
    flex-wrap: wrap;
  }
  .pos-stock-live-section-tabs .btn {
    border-radius: 999px;
    padding-inline: .95rem;
  }
  .pos-stock-live-section-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.5rem;
    margin-left: .4rem;
    border-radius: 999px;
    background: rgba(255,255,255,.18);
    color: inherit;
    font-size: .72rem;
    font-weight: 800;
    padding: 0 .35rem;
  }
  .pos-stock-live-section-pane.d-none {
    display: none !important;
  }
  .pos-stock-live-attention {
    border: 1px solid #f6d8c3;
    border-radius: 20px;
    background: linear-gradient(135deg, #fff7f1 0%, #fff2e8 100%);
    padding: 1rem 1.1rem;
    box-shadow: 0 14px 34px rgba(126, 73, 35, .08);
  }
  .pos-stock-live-attention-title {
    font-weight: 800;
    color: #6d3f17;
  }
  .pos-stock-live-attention-copy {
    color: #8a6450;
    font-size: .88rem;
  }
  .pos-stock-live-attention-count {
    font-size: 2rem;
    line-height: 1;
    font-weight: 900;
    color: #7c2d12;
  }
  .pos-stock-live-probe-note {
    border: 1px dashed #ecd7ca;
    border-radius: 16px;
    background: #fffaf6;
    color: #7b6458;
    font-size: .84rem;
    padding: .8rem .95rem;
  }
  .pos-stock-live-spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255,255,255,.35);
    border-top-color: #fff;
    border-radius: 50%;
    display: inline-block;
    animation: posStockSpin .8s linear infinite;
    vertical-align: middle;
  }
  @keyframes posStockSpin { to { transform: rotate(360deg); } }
</style>

<div class="container-xxl py-3">
  <div class="pos-stock-live-page">
    <div class="pos-stock-live-hero mb-3">
      <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
          <div class="pos-stock-live-hero-title">Stock Live POS</div>
        </div>
      </div>
      <div class="mt-2">
        <div class="pos-stock-live-hero-copy mt-1">Bandingkan cache database dengan kalkulasi live dari recipe dan stok aktual. Halaman ini fokus pada <strong>produk POS per outlet</strong>. Jika ingin audit bahan baku/material divisi, lanjutkan ke halaman reconcile bahan.</div>
        <div class="d-flex gap-2 flex-wrap mt-3">
          <span class="pos-stock-live-chip">Audit cache vs live</span>
          <span class="pos-stock-live-chip">Default outlet aktif</span>
          <span class="pos-stock-live-chip">HPP live = 0 jika qty live = 0</span>
        </div>
      </div>
    </div>

    <div class="pos-stock-live-attention mb-3 d-none" id="pending-queue-card">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
          <div class="small text-uppercase fw-bold text-muted">Pending Queue POS</div>
          <div class="pos-stock-live-attention-title mt-1">Masih ada stock commit POS yang belum terposting</div>
          <div class="pos-stock-live-attention-copy mt-2">Antrean pending jangan tenggelam di bawah tabel. Buka tab pending untuk melihat dan memproses order yang masih `QUEUED` atau `PROCESSING`.</div>
        </div>
        <div class="text-end">
          <div class="pos-stock-live-attention-count" id="pending-queue-count">0</div>
          <div class="small text-muted">job pending</div>
          <button type="button" class="btn btn-sm btn-dark mt-2" id="btn-open-pending-tab">Buka Pending Queue POS</button>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm pos-stock-live-shell">
      <div class="card-body">
        <div class="pos-stock-live-filter-box mb-3">
          <form id="filter-form" class="row g-2">
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">Outlet</label>
              <select class="form-select" id="outlet_id">
                <option value="">Pilih outlet</option>
                <?php foreach ($outlets as $outlet): ?>
                  <option value="<?php echo (int)($outlet['id'] ?? 0); ?>" <?php echo (int)($filters['outlet_id'] ?? 0) === (int)($outlet['id'] ?? 0) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)($outlet['outlet_name'] ?? $outlet['outlet_code'] ?? '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">Divisi</label>
              <select class="form-select" id="division_id">
                <option value="">Semua divisi</option>
                <?php foreach ($divisions as $division): ?>
                  <option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo (int)($filters['division_id'] ?? 0) === (int)($division['id'] ?? 0) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)($division['name'] ?? $division['code'] ?? '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">Status Cache</label>
              <select class="form-select" id="status">
                <option value="ALL">Semua status cache</option>
                <option value="AVAILABLE">Available</option>
                <option value="LIMITED">Limited</option>
                <option value="OUT">Out</option>
                <option value="HIDDEN">Hidden</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">Cari Produk</label>
              <input id="q" class="form-control" placeholder="Cari kode / nama produk">
            </div>
            <div class="col-md-1">
              <label class="form-label small text-muted mb-1">Baris</label>
              <select class="form-select" id="limit">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
              </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <button type="button" class="btn btn-sm btn-outline-danger w-100" id="btn-clear-filter">Clear</button>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" id="dirty_only">
                <label class="form-check-label small" for="dirty_only">Dirty only</label>
              </div>
              <div class="small text-muted">Urutan: divisi, klasifikasi, kategori, produk.</div>
            </div>
          </form>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div class="pos-stock-live-section-tabs">
            <button type="button" class="btn btn-sm btn-dark btn-section-tab" data-section="stock">Stock Live</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-section-tab" data-section="pending">Pending Queue POS<span class="pos-stock-live-section-badge" id="tab-pending-badge">0</span></button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-section-tab" data-section="failed">Job Gagal POS<span class="pos-stock-live-section-badge" id="tab-failed-badge">0</span></button>
          </div>
        </div>

        <div class="pos-stock-live-section-pane" id="section-stock">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div class="d-flex gap-2 flex-wrap pos-stock-live-tabs">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-compare-tab active" data-tab="all">Semua</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-compare-tab" data-tab="match">Match</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-compare-tab" data-tab="mismatch">Mismatch Produk</button>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-dark" id="btn-rebuild-all">Rebuild Total</button>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="pos-stock-live-metric">
              <div class="pos-stock-live-metric-label">Total Produk</div>
              <div class="fs-4 fw-bold mt-1" id="metric-total">0</div>
              <div class="pos-stock-live-metric-note mt-2">Jumlah row sesuai filter aktif.</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="pos-stock-live-metric">
              <div class="pos-stock-live-metric-label">Mismatch Produk POS</div>
              <div class="fs-4 fw-bold text-danger mt-1" id="metric-mismatch">0</div>
              <div class="pos-stock-live-metric-note mt-2">Produk dengan cache DB berbeda dari hitung live saat ini.</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="pos-stock-live-metric">
              <div class="pos-stock-live-metric-label">Dirty Cache</div>
              <div class="fs-4 fw-bold text-warning mt-1" id="metric-dirty">0</div>
              <div class="pos-stock-live-metric-note mt-2">Row terdampak event stok tapi belum bersih penuh.</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="pos-stock-live-metric">
              <div class="pos-stock-live-metric-label">Row Live Error</div>
              <div class="fs-4 fw-bold text-secondary mt-1" id="metric-error">0</div>
              <div class="pos-stock-live-metric-note mt-2">Line yang gagal dihitung live dan perlu dicek recipe/data sumber.</div>
            </div>
          </div>
        </div>

        <div class="pos-stock-live-table-wrap">
          <div class="table-responsive">
            <table class="table table-sm align-middle pos-stock-live-table mb-0">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th>Cache DB</th>
                  <th>Live Calc</th>
                  <th>Selisih</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody id="table-body"></tbody>
            </table>
          </div>
        </div>
        <div id="empty-state" class="text-muted py-3 d-none">Belum ada data untuk filter ini.</div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <small id="pagination-info" class="text-muted"></small>
          <div id="pagination" class="d-flex gap-1"></div>
        </div>
        </div>

        <div class="pos-runtime-job-shell mt-1 pos-stock-live-section-pane d-none" id="section-pending">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <div class="small text-uppercase fw-bold text-muted">Pending Queue POS</div>
              <h5 class="mb-1 mt-2">Job Stock Commit Antre / Diproses</h5>
              <p class="mb-0 text-muted">Pantau order yang stoknya masih menunggu posting atau sedang diproses, lalu jalankan proses manual bila perlu.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <input type="text" class="form-control form-control-sm" id="active_job_q" placeholder="Cari order / commit / job" style="min-width:280px;">
              <button type="button" class="btn btn-sm btn-dark" id="btn-process-all-active-jobs">Proses Semua Pending</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reload-active-jobs">Refresh</button>
            </div>
          </div>
          <div id="active_job_list" class="pos-runtime-job-list"></div>
          <div id="active_job_empty" class="text-muted small d-none">Belum ada job stock commit POS yang pending.</div>
        </div>

        <div class="pos-runtime-job-shell mt-1 pos-stock-live-section-pane d-none" id="section-failed">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <div class="small text-uppercase fw-bold text-muted">Audit Queue POS</div>
              <h5 class="mb-1 mt-2">Job Stock Commit Gagal</h5>
              <p class="mb-0 text-muted">Pantau order confirm yang tersimpan tetapi stoknya belum berhasil diposting, lalu jalankan retry dari sini.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <input type="text" class="form-control form-control-sm" id="failed_job_q" placeholder="Cari order / commit / job / error" style="min-width:280px;">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reload-failed-jobs">Refresh</button>
            </div>
          </div>
          <div id="failed_job_list" class="pos-runtime-job-list"></div>
          <div id="failed_job_empty" class="text-muted small d-none">Belum ada job stock commit POS yang gagal.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="probeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="probeModalLabel">Probe Stock Live Produk</h5>
          <div class="small text-muted">Ringkasan cache database dibandingkan dengan hasil hitung live per source recipe produk POS.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="pos-stock-live-probe-note mb-3">
          Cost line di bawah adalah <strong>cost parsial recipe</strong> berdasarkan source yang masih terbaca saat hitung live. Ini bukan otomatis berarti <strong>HPP producible</strong>. Kalau qty live produk = 0, headline HPP produk tetap dianggap 0.
        </div>
        <div id="probe-content" class="small text-muted">Belum ada data probe.</div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const posTransactionCsrfToken = <?php echo json_encode((string)($pos_transaction_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const defaultOutletId = parseInt(initialFilters.outlet_id || 0, 10) || 0;
  const state = {
    q: initialFilters.q || '',
    outlet_id: defaultOutletId,
    division_id: parseInt(initialFilters.division_id || 0, 10) || 0,
    status: initialFilters.status || 'ALL',
    dirty_only: !!initialFilters.dirty_only,
    compare_tab: 'all',
    page: parseInt(initialFilters.page || 1, 10) || 1,
    limit: parseInt(initialFilters.limit || 25, 10) || 25,
  };

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const paginationInfo = document.getElementById('pagination-info');
  const pagination = document.getElementById('pagination');
  const pendingQueueCard = document.getElementById('pending-queue-card');
  const pendingQueueCount = document.getElementById('pending-queue-count');
  const openPendingTabBtn = document.getElementById('btn-open-pending-tab');
  const pendingTabBadge = document.getElementById('tab-pending-badge');
  const failedTabBadge = document.getElementById('tab-failed-badge');
  const sectionPanes = {
    stock: document.getElementById('section-stock'),
    pending: document.getElementById('section-pending'),
    failed: document.getElementById('section-failed')
  };
  const processAllActiveJobsBtn = document.getElementById('btn-process-all-active-jobs');
  const activeJobList = document.getElementById('active_job_list');
  const activeJobEmpty = document.getElementById('active_job_empty');
  const failedJobList = document.getElementById('failed_job_list');
  const failedJobEmpty = document.getElementById('failed_job_empty');
  const probeModalEl = document.getElementById('probeModal');
  const probeModal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(probeModalEl) : null;
  state.section_tab = 'stock';

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }
  function number(v, digits = 4) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(v || 0));
  }
  function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
  }
  function fmtDateTime(v) {
    if (!v) return '-';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return escapeHtml(String(v));
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(dt);
  }
  function badgeClass(status) {
    const s = String(status || '').toUpperCase();
    if (s === 'AVAILABLE') return 'available';
    if (s === 'LIMITED') return 'limited';
    if (s === 'OUT') return 'out';
    return 'hidden';
  }
  function statusBadge(status) {
    const label = String(status || '-').toUpperCase() || '-';
    return `<span class="pos-stock-live-badge ${badgeClass(label)}">${escapeHtml(label)}</span>`;
  }
  function compareBadge(flag) {
    return flag
      ? '<span class="pos-stock-live-badge mismatch">Mismatch Produk</span>'
      : '<span class="pos-stock-live-badge match">Match</span>';
  }
  function materialAuditUrl(row) {
    const bottleneck = String(((row.live && row.live.bottleneck) || (row.cache && row.cache.bottleneck) || '')).trim();
    if (!bottleneck) return '';
    const params = new URLSearchParams();
    params.set('as_of_date', new Date().toISOString().slice(0, 10));
    params.set('q', bottleneck);
    const divisionId = Number(row.default_operational_division_id || row.product_division_id || 0);
    if (divisionId > 0) params.set('division_id', String(divisionId));
    return '<?php echo site_url('inventory/stock/division/reconcile'); ?>?' + params.toString();
  }
  function runtimeJobBadge(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      FAILED: ['failed', 'FAILED'],
      QUEUED: ['queued', 'QUEUED'],
      PROCESSING: ['processing', 'PROCESSING'],
      SUCCESS: ['success', 'SUCCESS'],
      CANCELLED: ['order', 'CANCELLED']
    };
    const entry = map[value] || ['order', value || '-'];
    return `<span class="pos-runtime-job-badge ${entry[0]}">${escapeHtml(entry[1])}</span>`;
  }
  function stockCommitBadge(status) {
    const value = String(status || '').toUpperCase();
    if (!value) return '';
    return `<span class="pos-runtime-job-badge order">Stok ${escapeHtml(value)}</span>`;
  }
  function qs() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('outlet_id', String(state.outlet_id || 0));
    p.set('division_id', String(state.division_id || 0));
    p.set('status', state.status || 'ALL');
    p.set('dirty_only', state.dirty_only ? '1' : '0');
    p.set('mismatch_only', state.compare_tab === 'mismatch' ? '1' : '0');
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 25));
    return p.toString();
  }
  function syncControls() {
    document.getElementById('q').value = state.q;
    document.getElementById('outlet_id').value = String(state.outlet_id || '');
    document.getElementById('division_id').value = String(state.division_id || '');
    document.getElementById('status').value = state.status || 'ALL';
    document.getElementById('dirty_only').checked = !!state.dirty_only;
    document.getElementById('limit').value = String(state.limit || 25);
    document.querySelectorAll('.btn-compare-tab').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.tab === state.compare_tab);
      btn.classList.toggle('btn-dark', btn.dataset.tab === state.compare_tab);
      btn.classList.toggle('btn-outline-secondary', btn.dataset.tab !== state.compare_tab);
    });
    document.querySelectorAll('.btn-section-tab').forEach((btn) => {
      const active = btn.dataset.section === state.section_tab;
      btn.classList.toggle('btn-dark', active);
      btn.classList.toggle('btn-outline-secondary', !active);
    });
    Object.keys(sectionPanes).forEach((key) => {
      if (sectionPanes[key]) {
        sectionPanes[key].classList.toggle('d-none', key !== state.section_tab);
      }
    });
  }
  async function getJson(url) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memuat data.');
    return json;
  }
  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memproses aksi.');
    return json;
  }
  async function postPosTransactionJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Pos-Transaction-CSRF': posTransactionCsrfToken },
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memproses aksi.');
    return json;
  }
  function renderMetrics(rows) {
    let mismatch = 0;
    let dirty = 0;
    let error = 0;
    rows.forEach((row) => {
      if (row.comparison && Number(row.comparison.mismatch_flag || 0) === 1) mismatch++;
      if (row.cache && Number(row.cache.is_dirty || 0) === 1) dirty++;
      if (row.live_error) error++;
    });
    document.getElementById('metric-total').textContent = String(rows.length);
    document.getElementById('metric-mismatch').textContent = String(mismatch);
    document.getElementById('metric-dirty').textContent = String(dirty);
    document.getElementById('metric-error').textContent = String(error);
  }
  function applyCompareTab(rows) {
    if (state.compare_tab === 'match') {
      return rows.filter((row) => Number(row?.comparison?.mismatch_flag || 0) === 0);
    }
    if (state.compare_tab === 'mismatch') {
      return rows.filter((row) => Number(row?.comparison?.mismatch_flag || 0) === 1);
    }
    return rows;
  }
  function renderRows(rows) {
    rows = applyCompareTab(rows);
    renderMetrics(rows);
    if (!rows.length) {
      tableBody.innerHTML = '';
      emptyState.classList.remove('d-none');
      return;
    }
    emptyState.classList.add('d-none');
    tableBody.innerHTML = rows.map((row) => {
      const auditUrl = materialAuditUrl(row);
      return `
      <tr>
        <td>
          <div class="fw-semibold">${escapeHtml(row.product_name || '-')}</div>
          <div class="pos-stock-live-note">${escapeHtml(row.product_code || '-')} | ${escapeHtml(row.product_division_name || '-')}</div>
          <div class="pos-stock-live-note">${escapeHtml(row.classification_name || '-')} | ${escapeHtml(row.product_category_name || '-')}</div>
          <div class="pos-stock-live-note mt-1">Harga jual ${money(row.selling_price || 0)}</div>
        </td>
        <td>
          <div>${statusBadge(row.cache ? row.cache.status : '')}</div>
          <div class="mt-2 pos-stock-live-kv pos-stock-live-summary">
            <strong>Qty cache</strong><span>${number(row.cache ? row.cache.qty : 0)}</span>
            <strong>HPP cache</strong><span>${number(row.cache ? row.cache.hpp : 0, 6)}</span>
            <strong>Bottleneck</strong><span>${escapeHtml((row.cache && row.cache.bottleneck) ? row.cache.bottleneck : '-')}</span>
            <strong>Event cache</strong><span>${escapeHtml((row.cache && row.cache.last_commit_event) ? row.cache.last_commit_event : '-')}</span>
            <strong>Computed</strong><span>${escapeHtml(row.cache ? fmtDateTime(row.cache.computed_at) : '-')}</span>
          </div>
          ${(row.cache && Number(row.cache.is_dirty || 0) === 1) ? '<div class="pos-stock-live-note text-warning mt-1">Cache masih dirty.</div>' : ''}
        </td>
        <td>
          ${row.live
            ? `
              <div>${statusBadge(row.live.status)}</div>
              <div class="mt-2 pos-stock-live-kv pos-stock-live-summary">
                <strong>Qty live</strong><span>${number(row.live.qty)}</span>
                <strong>HPP live</strong><span>${number(row.live.hpp, 6)}</span>
                <strong>Bottleneck</strong><span>${escapeHtml(row.live.bottleneck || '-')}</span>
                <strong>Recipe sources</strong><span>${escapeHtml(String(row.live.line_count || 0))}</span>
              </div>
              ${(Number(row.live.override_allowed || 0) === 1) ? '<div class="pos-stock-live-note text-warning mt-1">Masih bisa override karena kekosongan non-main.</div>' : ''}
            `
            : `<div class="text-danger small">${escapeHtml(row.live_error || 'Live calc gagal')}</div>`
          }
        </td>
        <td>
          <div>${compareBadge(row.comparison ? Number(row.comparison.mismatch_flag || 0) === 1 : false)}</div>
          <div class="pos-stock-live-note mt-2">${escapeHtml((row.comparison && row.comparison.note) ? row.comparison.note : '-')}</div>
          <div class="mt-2 pos-stock-live-kv pos-stock-live-summary">
            <strong>Last rebuild</strong><span>${escapeHtml((row.latest_log && row.latest_log.rebuilt_at) ? fmtDateTime(row.latest_log.rebuilt_at) : '-')}</span>
            <strong>Last source</strong><span>${escapeHtml((row.latest_log && row.latest_log.event_source) ? row.latest_log.event_source : '-')}</span>
            <strong>Last note</strong><span>${escapeHtml((row.latest_log && row.latest_log.mismatch_note) ? row.latest_log.mismatch_note : '-')}</span>
            <strong>Audit bahan</strong><span>${auditUrl ? `<a href="${auditUrl}" target="_blank" rel="noopener">Buka reconcile bahan</a>` : '-'}</span>
          </div>
        </td>
        <td class="text-end">
          <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
            ${auditUrl ? `<a href="${auditUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">Audit Bahan</a>` : ''}
            <button type="button" class="btn btn-sm btn-outline-secondary btn-probe" data-product-id="${Number(row.product_id || 0)}">Probe Live</button>
            <button type="button" class="btn btn-sm btn-primary btn-rebuild" data-product-id="${Number(row.product_id || 0)}">Rebuild</button>
          </div>
        </td>
      </tr>
    `; }).join('');
  }
  function renderPagination(meta) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || state.limit || 25);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    paginationInfo.textContent = total ? `Menampilkan ${start}-${end} dari ${total} produk` : 'Belum ada data';
    const buttons = [];
    for (let p = 1; p <= totalPages; p++) {
      buttons.push(`<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'} btn-page" data-page="${p}">${p}</button>`);
    }
    pagination.innerHTML = buttons.join('');
  }
  function renderProbe(result) {
    const live = result.live || {};
    const cache = result.cache || {};
    const lines = Array.isArray(live.lines) ? live.lines : [];
    document.getElementById('probe-content').innerHTML = `
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Cache DB</div>
            <div class="mt-2">${statusBadge(cache.availability_status || '')}</div>
            <div class="small mt-2">Qty: ${number(cache.estimated_available_qty || 0)}</div>
            <div class="small">HPP: ${number(cache.hpp_live_snapshot || 0, 6)}</div>
            <div class="small">Bottleneck: ${escapeHtml(cache.bottleneck_name_snapshot || '-')}</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Live Calc</div>
            <div class="mt-2">${statusBadge(live.availability_status || '')}</div>
            <div class="small mt-2">Qty: ${number(live.estimated_available_qty || 0)}</div>
            <div class="small">HPP: ${number(live.hpp_live_snapshot || 0, 6)}</div>
            <div class="small">Bottleneck: ${escapeHtml(live.bottleneck_name_snapshot || '-')}</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Hasil Compare</div>
            <div class="mt-2">${compareBadge(result.comparison ? Number(result.comparison.mismatch_flag || 0) === 1 : false)}</div>
            <div class="small mt-2">${escapeHtml((result.comparison && result.comparison.note) ? result.comparison.note : '-')}</div>
            <div class="small mt-2">Probe ID: ${escapeHtml(String(result.probe_id || '-'))}</div>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Source</th>
              <th>Role</th>
              <th>Required / Unit</th>
              <th>Available Live</th>
              <th>HPP / Unit</th>
            </tr>
          </thead>
          <tbody>
            ${lines.map((line) => `
              <tr>
                <td>
                  <div class="fw-semibold">${escapeHtml(line.source_name_snapshot || '-')}</div>
                  <div class="small text-muted">${escapeHtml(line.line_type || '-')}</div>
                </td>
                <td>${escapeHtml(line.source_role || '-')}</td>
                <td>${number(line.required_qty_per_unit || 0)}</td>
                <td>${number(line.available_qty_live || 0)}</td>
                <td>${number(line.total_cost_live_per_unit || 0, 6)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  }
  async function loadRows() {
    syncControls();
    const json = await getJson('<?php echo site_url('pos/stock-live/data'); ?>?' + qs());
    const rows = Array.isArray(json.rows) ? json.rows : [];
    renderRows(rows);
    renderPagination(json.meta || {});
    history.replaceState(null, '', '<?php echo site_url('pos/stock-live'); ?>?' + qs());
  }

  async function loadFailedJobs() {
    const p = new URLSearchParams();
    p.set('outlet_id', String(state.outlet_id || 0));
    p.set('q', String(document.getElementById('failed_job_q').value || ''));
    p.set('limit', '12');
    const json = await getJson('<?php echo site_url('pos/orders/runtime-jobs/failed'); ?>?' + p.toString());
    const rows = Array.isArray(json.rows) ? json.rows : [];
    if (failedTabBadge) {
      failedTabBadge.textContent = String(rows.length);
    }
    if (!rows.length) {
      failedJobList.innerHTML = '';
      failedJobEmpty.classList.remove('d-none');
      return;
    }
    failedJobEmpty.classList.add('d-none');
    failedJobList.innerHTML = rows.map((row) => `
      <div class="pos-runtime-job-card" data-job-id="${Number(row.id || 0)}">
        <div class="pos-runtime-job-top">
          <div>
            <div class="pos-runtime-job-title">${escapeHtml(row.order_no || '-')} | ${escapeHtml(row.commit_no || '-')}</div>
            <div class="pos-runtime-job-meta">Job ${escapeHtml(row.job_code || '-')} | Outlet ${escapeHtml(row.outlet_name || '-')} | Kasir ${escapeHtml(row.cashier_employee_name || '-')}</div>
            <div class="pos-runtime-job-meta">Percobaan ${Number(row.attempts || 0)} / ${Number(row.max_attempts || 0)} | Retry setelah ${fmtDateTime(row.run_after || row.updated_at || row.created_at || '')}</div>
          </div>
          <div class="text-end">
            <div class="pos-runtime-job-badges">
              ${runtimeJobBadge(row.status || '')}
              ${stockCommitBadge(row.stock_commit_status || '')}
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-retry-job" data-job-id="${Number(row.id || 0)}">Retry</button>
          </div>
        </div>
        <div class="pos-runtime-job-error">${escapeHtml(row.last_error || 'Job gagal tanpa pesan detail.')}</div>
      </div>
    `).join('');
  }

  async function loadActiveJobs() {
    const p = new URLSearchParams();
    p.set('outlet_id', String(state.outlet_id || 0));
    p.set('q', String(document.getElementById('active_job_q').value || ''));
    p.set('limit', '12');
    const json = await getJson('<?php echo site_url('pos/orders/runtime-jobs/active'); ?>?' + p.toString());
    const rows = Array.isArray(json.rows) ? json.rows : [];
    if (pendingTabBadge) {
      pendingTabBadge.textContent = String(rows.length);
    }
    if (pendingQueueCount) {
      pendingQueueCount.textContent = String(rows.length);
    }
    if (pendingQueueCard) {
      pendingQueueCard.classList.toggle('d-none', rows.length <= 0);
    }
    if (!rows.length) {
      activeJobList.innerHTML = '';
      activeJobEmpty.classList.remove('d-none');
      return;
    }
    activeJobEmpty.classList.add('d-none');
    activeJobList.innerHTML = rows.map((row) => `
      <div class="pos-runtime-job-card" data-job-id="${Number(row.id || 0)}" data-order-id="${Number(row.order_id || 0)}">
        <div class="pos-runtime-job-top">
          <div>
            <div class="pos-runtime-job-title">${escapeHtml(row.order_no || '-')} | ${escapeHtml(row.commit_no || '-')}</div>
            <div class="pos-runtime-job-meta">Job ${escapeHtml(row.job_code || '-')} | Outlet ${escapeHtml(row.outlet_name || '-')} | Kasir ${escapeHtml(row.cashier_employee_name || '-')}</div>
            <div class="pos-runtime-job-meta">Status ${escapeHtml(row.status || '-')} | Percobaan ${Number(row.attempts || 0)} / ${Number(row.max_attempts || 0)} | Antre sejak ${fmtDateTime(row.created_at || '')}</div>
          </div>
          <div class="text-end">
            <div class="pos-runtime-job-badges">
              ${runtimeJobBadge(row.status || '')}
              ${stockCommitBadge(row.stock_commit_status || '')}
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-process-job" data-job-id="${Number(row.id || 0)}" data-order-id="${Number(row.order_id || 0)}">Proses Sekarang</button>
          </div>
        </div>
        <div class="pos-runtime-job-error">${escapeHtml(row.last_error || (String(row.status || '').toUpperCase() === 'PROCESSING' ? 'Job sedang diproses.' : 'Job menunggu diposting ke stok.'))}</div>
      </div>
    `).join('');
  }

  let searchTimer = null;
  document.getElementById('q').addEventListener('input', function () {
    state.q = this.value || '';
    state.page = 1;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadRows().catch((e) => alert(e.message)), 280);
  });
  document.getElementById('outlet_id').addEventListener('change', function () {
    state.outlet_id = parseInt(this.value || '0', 10) || 0;
    state.page = 1;
    Promise.all([
      loadRows(),
      loadActiveJobs(),
      loadFailedJobs()
    ]).catch((e) => alert(e.message));
  });
  document.getElementById('division_id').addEventListener('change', function () { state.division_id = parseInt(this.value || '0', 10) || 0; state.page = 1; loadRows().catch((e) => alert(e.message)); });
  document.getElementById('status').addEventListener('change', function () { state.status = this.value || 'ALL'; state.page = 1; loadRows().catch((e) => alert(e.message)); });
  document.getElementById('limit').addEventListener('change', function () { state.limit = parseInt(this.value || '25', 10) || 25; state.page = 1; loadRows().catch((e) => alert(e.message)); });
  document.getElementById('dirty_only').addEventListener('change', function () { state.dirty_only = this.checked; state.page = 1; loadRows().catch((e) => alert(e.message)); });
  document.querySelectorAll('.btn-compare-tab').forEach((btn) => {
    btn.addEventListener('click', function () {
      state.compare_tab = this.dataset.tab || 'all';
      state.page = 1;
      loadRows().catch((e) => alert(e.message));
    });
  });
  document.querySelectorAll('.btn-section-tab').forEach((btn) => {
    btn.addEventListener('click', function () {
      state.section_tab = this.dataset.section || 'stock';
      syncControls();
    });
  });
  if (openPendingTabBtn) {
    openPendingTabBtn.addEventListener('click', function () {
      state.section_tab = 'pending';
      syncControls();
    });
  }
  document.getElementById('btn-clear-filter').addEventListener('click', function () {
    state.q = '';
    state.outlet_id = defaultOutletId;
    state.division_id = 0;
    state.status = 'ALL';
    state.dirty_only = false;
    state.compare_tab = 'all';
    state.page = 1;
    state.limit = 25;
    document.getElementById('failed_job_q').value = '';
    Promise.all([
      loadRows(),
      loadActiveJobs(),
      loadFailedJobs()
    ]).catch((e) => alert(e.message));
  });
  document.getElementById('btn-rebuild-all').addEventListener('click', async function () {
    if (!state.outlet_id) {
      alert('Pilih outlet dulu sebelum rebuild total.');
      return;
    }
    if (!confirm('Rebuild total cache untuk semua produk POS di outlet ini sekarang?')) return;
    const originalHtml = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<span class="pos-stock-live-spinner me-2"></span>Rebuild total...';
    try {
      const result = await postPosTransactionJson('<?php echo site_url('pos/stock-live/rebuild-all'); ?>', {
        outlet_id: state.outlet_id,
        division_id: state.division_id
      });
      await loadRows();
      alert('Rebuild total selesai. Success: ' + String(result.success_count || 0) + ', failed: ' + String(result.failed_count || 0));
    } catch (e) {
      alert(e.message);
    } finally {
      this.disabled = false;
      this.innerHTML = originalHtml;
    }
  });

  pagination.addEventListener('click', function (event) {
    const btn = event.target.closest('.btn-page');
    if (!btn) return;
    state.page = parseInt(btn.dataset.page || '1', 10) || 1;
    loadRows().catch((e) => alert(e.message));
  });

  tableBody.addEventListener('click', async function (event) {
    const probeBtn = event.target.closest('.btn-probe');
    if (probeBtn) {
      try {
        const result = await getJson('<?php echo site_url('pos/stock-live/probe'); ?>?outlet_id=' + encodeURIComponent(String(state.outlet_id || 0)) + '&product_id=' + encodeURIComponent(probeBtn.dataset.productId || '0'));
        renderProbe(result);
        probeModal && probeModal.show();
      } catch (e) {
        alert(e.message);
      }
      return;
    }

    const rebuildBtn = event.target.closest('.btn-rebuild');
    if (rebuildBtn) {
      if (!confirm('Rebuild cache untuk produk ini sekarang?')) return;
      const originalHtml = rebuildBtn.innerHTML;
      rebuildBtn.disabled = true;
      rebuildBtn.innerHTML = '<span class="pos-stock-live-spinner me-2"></span>Rebuild...';
      try {
        await postPosTransactionJson('<?php echo site_url('pos/stock-live/rebuild'); ?>', {
          outlet_id: state.outlet_id,
          product_id: parseInt(rebuildBtn.dataset.productId || '0', 10) || 0
        });
        await loadRows();
      } catch (e) {
        alert(e.message);
      } finally {
        rebuildBtn.disabled = false;
        rebuildBtn.innerHTML = originalHtml;
      }
    }
  });

  let failedJobTimer = null;
  let activeJobTimer = null;
  document.getElementById('active_job_q').addEventListener('input', function () {
    clearTimeout(activeJobTimer);
    activeJobTimer = setTimeout(() => loadActiveJobs().catch((e) => alert(e.message)), 260);
  });
  document.getElementById('btn-reload-active-jobs').addEventListener('click', function () {
    loadActiveJobs().catch((e) => alert(e.message));
  });
  processAllActiveJobsBtn.addEventListener('click', async function () {
    if (!state.outlet_id) {
      alert('Pilih outlet dulu sebelum memproses semua pending queue POS.');
      return;
    }
    if (!confirm('Proses semua pending queue POS untuk outlet ini sekarang? Maksimal 25 job akan diproses per klik.')) return;
    const original = processAllActiveJobsBtn.innerHTML;
    processAllActiveJobsBtn.disabled = true;
    processAllActiveJobsBtn.innerHTML = '<span class="pos-stock-live-spinner me-2"></span>Memproses pending...';
    try {
      const result = await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/process-all'); ?>', {
        outlet_id: state.outlet_id,
        limit: 25
      });
      await Promise.all([loadActiveJobs(), loadFailedJobs(), loadRows()]);
      alert('Proses pending selesai. Diproses: ' + String(result.processed_count || 0) + ', sukses: ' + String(result.success_count || 0) + ', gagal: ' + String(result.failed_count || 0));
    } catch (e) {
      alert(e.message);
    } finally {
      processAllActiveJobsBtn.disabled = false;
      processAllActiveJobsBtn.innerHTML = original;
    }
  });
  activeJobList.addEventListener('click', async function (event) {
    const processBtn = event.target.closest('.btn-process-job');
    if (!processBtn) return;
    if (!confirm('Proses job stock commit POS ini sekarang?')) return;
    const original = processBtn.innerHTML;
    processBtn.disabled = true;
    processBtn.innerHTML = '<span class="pos-stock-live-spinner me-2"></span>Proses...';
    try {
      await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/trigger'); ?>/' + encodeURIComponent(processBtn.dataset.orderId || '0'), {
        job_id: parseInt(processBtn.dataset.jobId || '0', 10) || 0,
        limit: 1
      });
      await Promise.all([loadActiveJobs(), loadFailedJobs(), loadRows()]);
    } catch (e) {
      alert(e.message);
    } finally {
      processBtn.disabled = false;
      processBtn.innerHTML = original;
    }
  });
  document.getElementById('failed_job_q').addEventListener('input', function () {
    clearTimeout(failedJobTimer);
    failedJobTimer = setTimeout(() => loadFailedJobs().catch((e) => alert(e.message)), 260);
  });
  document.getElementById('btn-reload-failed-jobs').addEventListener('click', function () {
    loadFailedJobs().catch((e) => alert(e.message));
  });
  failedJobList.addEventListener('click', async function (event) {
    const retryBtn = event.target.closest('.btn-retry-job');
    if (!retryBtn) return;
    if (!confirm('Retry job stock commit POS ini sekarang?')) return;
    const original = retryBtn.innerHTML;
    retryBtn.disabled = true;
    retryBtn.innerHTML = '<span class="pos-stock-live-spinner me-2"></span>Retry...';
    try {
      await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/' + encodeURIComponent(retryBtn.dataset.jobId || '0'), {});
      await loadFailedJobs();
    } catch (e) {
      alert(e.message);
    } finally {
      retryBtn.disabled = false;
      retryBtn.innerHTML = original;
    }
  });

  syncControls();
  Promise.all([
    loadRows(),
    loadActiveJobs(),
    loadFailedJobs()
  ]).catch((e) => alert(e.message));
});
</script>
