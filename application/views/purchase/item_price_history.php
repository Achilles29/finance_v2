<?php
$preselected   = is_array($preselected ?? null) ? $preselected : null;
$preselectedId = (int)($preselected_id ?? 0);
?>
<script src="<?php echo base_url('assets/libs/apex-charts/apexcharts.js'); ?>"></script>

<style>
  /* ── Layout ─────────────────────────── */
  .iph-card { border:0; border-radius:18px; box-shadow:0 6px 20px rgba(58,38,30,.07); }
  /* ── Item search ─────────────────────── */
  .iph-search-wrap { position:relative; }
  .iph-search-results {
    display:none; position:absolute; top:calc(100% + .3rem); left:0; right:0; z-index:1050;
    background:#fff; border:1px solid rgba(201,183,168,.72); border-radius:14px;
    box-shadow:0 16px 36px rgba(61,38,27,.14); overflow:hidden; max-height:280px; overflow-y:auto;
  }
  .iph-search-item { padding:.7rem .9rem; cursor:pointer; border-bottom:1px solid rgba(232,220,210,.9); }
  .iph-search-item:last-child { border-bottom:0; }
  .iph-search-item:hover, .iph-search-item.is-active { background:#fff7f2; }
  .iph-search-name { font-weight:700; color:#382a2b; font-size:.9rem; }
  .iph-search-meta { font-size:.75rem; color:#8a776d; }
  /* ── Preview card ────────────────────── */
  .iph-preview { border:1px solid rgba(224,209,198,.6); border-radius:14px; background:#fffdfb; padding:.8rem 1rem; display:none; }
  .iph-preview-name { font-size:1rem; font-weight:800; color:#2d1f1c; }
  .iph-preview-meta { font-size:.8rem; color:#7a6a60; }
  .iph-preview-badge { background:#e8f3ef; color:#1a6450; border-radius:999px; font-size:.72rem; font-weight:700; padding:.1rem .5rem; }
  /* ── State / feedback ─────────────────── */
  .iph-state { display:grid; grid-template-columns:auto minmax(0,1fr); align-items:center; gap:.9rem; margin:0 0 1rem; padding:1rem 1.1rem; border:1px dashed #d9c9bc; border-radius:16px; background:#fffdfb; color:#7a6a60; }
  .iph-state-icon { width:42px; height:42px; display:grid; place-items:center; border-radius:14px; background:#f7eee8; color:#835e4d; font-size:1.2rem; }
  .iph-state[data-state="loading"] .iph-state-icon { background:#eef5f2; color:#1a6450; }
  .iph-state[data-state="error"] { border-style:solid; border-color:#f1c5c2; background:#fff8f7; }
  .iph-state[data-state="error"] .iph-state-icon { background:#fdeceb; color:#b42318; }
  .iph-state-title { display:block; color:#47342d; font-size:.9rem; font-weight:800; }
  .iph-state-copy { display:block; margin-top:.12rem; font-size:.78rem; line-height:1.4; }
  .iph-state-action { grid-column:2; justify-self:start; margin-top:.08rem; }
  /* ── Table ───────────────────────────── */
  .iph-table th { font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
  .iph-table td { font-size:.86rem; vertical-align:middle; }
  .iph-div-badge { background:#e8f3ef; color:#1a6450; border-radius:999px; font-size:.72rem; font-weight:700; padding:.1rem .4rem; }
  .iph-table-footer { display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding:.75rem 1rem; border-top:1px solid #f0e8e2; }
  .iph-table-footer small { color:#7a6a60; }
  @media (max-width:575.98px) { .iph-state { grid-template-columns:auto minmax(0,1fr); padding:.85rem; } .iph-state-action { grid-column:1 / -1; width:100%; } .iph-state-action .btn { width:100%; } .iph-table-footer { align-items:stretch; flex-direction:column; } .iph-table-footer .btn { width:100%; } }
</style>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'price-history']); ?>

<div class="fin-page-header mb-3">
  <div>
    <h4 class="fin-page-title"><i class="ri ri-line-chart-line me-1 text-primary"></i>Riwayat Harga Item</h4>
    <p class="fin-page-subtitle mb-0">Tren harga beli &amp; HPP/satuan isi per item dari purchase yang sudah lunas</p>
  </div>
</div>

<!-- Filter Card -->
<div class="card iph-card mb-3">
  <div class="card-body py-3">
    <div class="row g-3 align-items-start">

      <!-- Item search -->
      <div class="col-md-5">
        <label class="form-label small mb-1 fw-semibold">Cari Item <span class="text-danger">*</span></label>
        <div class="iph-search-wrap">
          <input type="text" id="iph-item-input" class="form-control"
                 placeholder="Ketik nama item atau bahan baku..." autocomplete="off">
          <div class="iph-search-results" id="iph-search-results"></div>
          <input type="hidden" id="iph-item-id" value="<?php echo $preselectedId; ?>">
        </div>
        <!-- Preview item terpilih -->
        <div class="iph-preview mt-2" id="iph-preview"></div>
      </div>

      <!-- Jumlah per halaman -->
      <div class="col-md-2">
        <label class="form-label small mb-1" for="iph-limit">Per halaman</label>
        <select id="iph-limit" class="form-select">
          <?php foreach ([10, 20, 30, 50, 100] as $l): ?>
            <option value="<?php echo $l; ?>" <?php echo $l === 20 ? 'selected' : ''; ?>><?php echo $l; ?> transaksi</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Mode -->
      <div class="col-md-3">
        <label class="form-label small mb-1">Tampilkan</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="iph_mode" id="iph_hpp" value="hpp" checked>
          <label class="btn btn-outline-primary btn-sm" for="iph_hpp">HPP / Isi</label>
          <input type="radio" class="btn-check" name="iph_mode" id="iph_buy" value="buy">
          <label class="btn btn-outline-primary btn-sm" for="iph_buy">Harga / Pack</label>
        </div>
      </div>

      <!-- Load button -->
      <div class="col-md-2 d-flex align-items-end">
        <button type="button" id="iph-load-btn" class="btn btn-primary w-100" disabled>
          <i class="ri ri-refresh-line me-1"></i>Tampilkan
        </button>
      </div>
    </div>
  </div>
</div>

<div class="iph-state" id="iph-state" data-state="initial" role="status" aria-live="polite">
  <span class="iph-state-icon" id="iph-state-icon"><i class="ri ri-search-line"></i></span>
  <div><strong class="iph-state-title" id="iph-state-title">Pilih item untuk melihat riwayat</strong><span class="iph-state-copy" id="iph-state-copy">Cari item atau bahan baku pada filter di atas. Hanya pembelian yang sudah lunas yang ditampilkan.</span></div>
  <div class="iph-state-action" id="iph-state-action" hidden><button class="btn btn-sm btn-outline-danger" type="button" id="iph-retry-btn"><i class="ri ri-refresh-line me-1"></i>Coba lagi</button></div>
</div>

<!-- Chart -->
<div class="card iph-card mb-3" id="iph-chart-card" style="display:none">
  <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold" id="iph-chart-title">Tren Harga</span>
    <small class="text-muted" id="iph-chart-meta"></small>
  </div>
  <div class="card-body py-3">
    <div id="iph-chart-container"></div>
  </div>
</div>

<!-- Table -->
<div class="card iph-card" id="iph-table-card" style="display:none">
  <div class="card-header py-2 d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Detail Transaksi</span>
    <small class="text-muted" id="iph-table-meta"></small>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0 iph-table">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Tanggal</th><th>Nama / Brand</th><th>Divisi</th><th>Sumber</th>
          <th class="text-end">Qty Isi</th><th class="text-end">Qty Pack</th>
          <th class="text-end">HPP / Isi</th><th class="text-end">Harga / Pack</th>
        </tr>
      </thead>
      <tbody id="iph-table-body"></tbody>
    </table>
  </div>
  <div class="iph-table-footer" id="iph-table-footer">
    <small id="iph-pagination-meta"></small>
    <button type="button" class="btn btn-sm btn-outline-primary" id="iph-load-more-btn" hidden><i class="ri ri-add-line me-1"></i>Muat transaksi berikutnya</button>
  </div>
</div>

<script>
(function () {
  const BASE = '<?php echo site_url(); ?>';
  let chart = null;
  let searchTimer = null;
  let selectedItem = <?php echo $preselected ? json_encode($preselected) : 'null'; ?>;
  let historyRows = [];
  let historyTotal = 0;
  let historyHasMore = false;
  let historyRequest = null;
  let historyRequestSerial = 0;

  /* ── helpers ──────────────────────────────────────────────── */
  function money(v) { return 'Rp ' + Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function num(v, d) { return Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:d??2,maximumFractionDigits:d??2}); }
  function esc(v) { const d = document.createElement('div'); d.textContent = String(v??''); return d.innerHTML; }
  function getMode() { return document.querySelector('input[name="iph_mode"]:checked')?.value || 'hpp'; }

  /* ── DOM refs ─────────────────────────────────────────────── */
  const itemInput    = document.getElementById('iph-item-input');
  const itemIdInput  = document.getElementById('iph-item-id');
  const searchResults = document.getElementById('iph-search-results');
  const preview      = document.getElementById('iph-preview');
  const loadBtn      = document.getElementById('iph-load-btn');
  const stateBox     = document.getElementById('iph-state');
  const stateIcon    = document.getElementById('iph-state-icon');
  const stateTitle   = document.getElementById('iph-state-title');
  const stateCopy    = document.getElementById('iph-state-copy');
  const stateAction  = document.getElementById('iph-state-action');
  const retryBtn     = document.getElementById('iph-retry-btn');
  const chartCard    = document.getElementById('iph-chart-card');
  const tableCard    = document.getElementById('iph-table-card');
  const tableBody    = document.getElementById('iph-table-body');
  const chartMeta    = document.getElementById('iph-chart-meta');
  const tableMeta    = document.getElementById('iph-table-meta');
  const chartTitle   = document.getElementById('iph-chart-title');
  const paginationMeta = document.getElementById('iph-pagination-meta');
  const loadMoreBtn  = document.getElementById('iph-load-more-btn');

  function showState(type, copy) {
    const options = {
      initial: { icon: 'ri-search-line', title: 'Pilih item untuk melihat riwayat', copy: 'Cari item atau bahan baku pada filter di atas. Hanya pembelian yang sudah lunas yang ditampilkan.' },
      loading: { icon: 'ri-loader-4-line ri-spin', title: 'Memuat riwayat harga', copy: 'Menyiapkan transaksi pembelian dan tren harga item.' },
      empty: { icon: 'ri-inbox-line', title: 'Belum ada riwayat pembelian lunas', copy: 'Item ini belum memiliki PO berstatus PAID, receipt POSTED, atau catatan pembelian lama yang dapat ditampilkan.' },
      error: { icon: 'ri-error-warning-line', title: 'Riwayat belum dapat dimuat', copy: 'Periksa koneksi atau hak akses, lalu coba lagi.' },
    };
    const option = options[type] || options.initial;
    stateBox.dataset.state = type;
    stateIcon.innerHTML = '<i class="ri ' + option.icon + '"></i>';
    stateTitle.textContent = option.title;
    stateCopy.textContent = copy || option.copy;
    stateAction.hidden = type !== 'error';
    stateBox.style.display = 'grid';
  }

  function hideState() { stateBox.style.display = 'none'; }

  function abortHistoryRequest() {
    historyRequestSerial++;
    if (historyRequest) {
      historyRequest.abort();
      historyRequest = null;
    }
  }

  function resetHistory() {
    historyRows = [];
    historyTotal = 0;
    historyHasMore = false;
    tableBody.innerHTML = '';
    chartCard.style.display = 'none';
    tableCard.style.display = 'none';
    loadMoreBtn.hidden = true;
    loadMoreBtn.disabled = false;
    paginationMeta.textContent = '';
  }

  /* ── Item preview ─────────────────────────────────────────── */
  function showItemPreview(item) {
    if (!item) { preview.style.display = 'none'; return; }
    const matText = item.material_name ? `<span class="iph-preview-badge me-1">${esc(item.material_name)}</span>` : '';
    const catText = item.category_name ? esc(item.category_name) : '';
    const uomText = [item.buy_uom, item.content_uom].filter(Boolean).join(' / ');
    preview.innerHTML = `
      <div class="iph-preview-name">${esc(item.item_name)}</div>
      <div class="iph-preview-meta mt-1">
        ${matText}${catText ? `<span class="text-muted">${catText}</span>` : ''}
        ${uomText ? `<span class="text-muted ms-2">${esc(uomText)}</span>` : ''}
      </div>`;
    preview.style.display = 'block';
  }

  /* ── Item search ──────────────────────────────────────────── */
  function selectItem(item) {
    abortHistoryRequest();
    selectedItem = item;
    itemInput.value  = item.item_name;
    itemIdInput.value = item.id;
    searchResults.style.display = 'none';
    searchResults.innerHTML = '';
    showItemPreview(item);
    loadBtn.disabled = false;
    history.replaceState({}, '', BASE + 'purchase/item-price-history/' + item.id);
    resetHistory();
    loadData(false);
  }

  function clearSelection() {
    abortHistoryRequest();
    selectedItem = null;
    itemIdInput.value = '0';
    loadBtn.disabled = true;
    preview.style.display = 'none';
    resetHistory();
    showState('initial');
  }

  async function runSearch(q) {
    if (q.length < 2) { searchResults.style.display = 'none'; return; }
    searchResults.innerHTML = '<div class="p-3 text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Mencari...</div>';
    searchResults.style.display = 'block';
    try {
      const res  = await fetch(BASE + 'purchase/item-price-history/item-search?q=' + encodeURIComponent(q), {
        headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'}
      });
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); } catch(pe) {
        searchResults.innerHTML = '<div class="p-3 text-danger small">Response error. Cek permission.</div>';
        return;
      }
      const rows = Array.isArray(json.rows) ? json.rows : [];
      if (!rows.length) {
        searchResults.innerHTML = '<div class="p-3 text-muted small">Tidak ada item cocok untuk "<em>' + esc(q) + '</em>".</div>';
        return;
      }
      searchResults.innerHTML = rows.map(function(r, idx) {
        const meta = [r.material_name, r.category_name, r.content_uom].filter(Boolean).join(' · ');
        return `<div class="iph-search-item" data-idx="${idx}">
          <div class="iph-search-name">${esc(r.item_name)}</div>
          ${meta ? `<div class="iph-search-meta">${esc(meta)}</div>` : ''}
        </div>`;
      }).join('');
      searchResults.querySelectorAll('.iph-search-item').forEach(function(el) {
        el.addEventListener('mousedown', function(ev) {
          ev.preventDefault(); // prevent blur before click registers
          selectItem(rows[Number(el.dataset.idx)]);
        });
      });
    } catch(e) {
      searchResults.innerHTML = '<div class="p-3 text-danger small">' + esc(e.message || 'Gagal mencari') + '</div>';
    }
  }

  itemInput.addEventListener('input', function() {
    if (!this.value.trim()) { clearSelection(); searchResults.style.display = 'none'; return; }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { runSearch(itemInput.value.trim()); }, 260);
  });

  document.addEventListener('click', function(e) {
    if (!searchResults.contains(e.target) && e.target !== itemInput) {
      searchResults.style.display = 'none';
    }
  });

  /* ── Chart render ─────────────────────────────────────────── */
  function renderChart(rows, mode) {
    const reversed = [...rows].reverse();
    const dates  = reversed.map(r => r.movement_date);
    const values = reversed.map(r => parseFloat(mode === 'hpp' ? r.unit_cost : r.price_per_buy) || 0);
    const label  = mode === 'hpp' ? 'HPP / Satuan Isi' : 'Harga Beli / Pack';

    const container = document.getElementById('iph-chart-container');
    container.innerHTML = '';
    if (chart) { try { chart.destroy(); } catch(e){} chart = null; }
    if (!window.ApexCharts || !values.length) return;

    chart = new ApexCharts(container, {
      chart: { type: 'area', height: 280, toolbar: {show:false}, zoom: {enabled:false} },
      series: [{ name: label, data: values }],
      xaxis: { categories: dates, tickAmount: Math.min(dates.length, 10), labels: { rotate: -35, style:{fontSize:'11px'} } },
      yaxis: { labels: { formatter: v => money(v) } },
      dataLabels: { enabled: values.length <= 15 },
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { opacityFrom:.35, opacityTo:.05 } },
      colors: ['#1a6450'],
      tooltip: { y: { formatter: v => money(v) } },
      markers: { size: values.length <= 40 ? 4 : 0, strokeWidth: 0 },
      grid: { borderColor: '#f0ebe6', strokeDashArray: 3 },
    });
    chart.render();
  }

  /* ── Table render ─────────────────────────────────────────── */
  function renderTable(rows, total) {
    tableMeta.textContent = rows.length + ' dari ' + total + ' total';
    if (!rows.length) { tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Belum ada data.</td></tr>'; return; }
    tableBody.innerHTML = rows.map(function(r, i) {
      const brand = r.brand && r.brand !== r.item_name ? r.brand : '';
      return `<tr>
        <td class="text-muted small">${i+1}</td>
        <td class="small">${esc(r.movement_date||'-')}</td>
        <td>
          <div class="fw-semibold">${esc(r.item_name||'-')}</div>
          ${brand ? `<div class="small text-muted">${esc(brand)}</div>` : ''}
        </td>
        <td>${r.division_name ? `<span class="iph-div-badge">${esc(r.division_name)}</span>` : '<span class="text-muted">-</span>'}</td>
        <td><span class="small ${r.source_type === 'PURCHASE_RECEIPT' ? 'text-success' : (r.source_type === 'PAID_PURCHASE_ORDER' ? 'text-primary' : 'text-muted')}">${esc(r.source_type === 'PURCHASE_RECEIPT' ? (r.source_ref || 'Receipt') : (r.source_type === 'PAID_PURCHASE_ORDER' ? (r.source_ref || 'PO lunas') : 'Ledger lama'))}</span></td>
        <td class="text-end">${num(r.qty_content_delta)} <span class="text-muted small">${esc(r.content_uom||'')}</span></td>
        <td class="text-end">${parseFloat(r.qty_buy_delta||0)>0 ? num(r.qty_buy_delta)+' <span class="text-muted small">pack</span>' : '<span class="text-muted">-</span>'}</td>
        <td class="text-end fw-semibold">${money(r.unit_cost)}</td>
        <td class="text-end">${parseFloat(r.price_per_buy||0)>0 ? money(r.price_per_buy) : '<span class="text-muted">-</span>'}</td>
      </tr>`;
    }).join('');
  }

  function updatePagination() {
    const shown = historyRows.length;
    paginationMeta.textContent = historyHasMore
      ? 'Menampilkan ' + shown + ' dari ' + historyTotal + ' transaksi terbaru.'
      : 'Semua ' + shown + ' dari ' + historyTotal + ' transaksi telah ditampilkan.';
    loadMoreBtn.hidden = !historyHasMore;
    loadMoreBtn.disabled = false;
    loadMoreBtn.innerHTML = '<i class="ri ri-add-line me-1"></i>Muat transaksi berikutnya';
  }

  /* ── Load data ────────────────────────────────────────────── */
  async function loadData(append) {
    const itemId = Number(itemIdInput.value || 0);
    if (itemId <= 0) return;

    const shouldAppend = append === true && historyRows.length > 0;
    abortHistoryRequest();
    const request = new AbortController();
    historyRequest = request;
    const requestSerial = ++historyRequestSerial;
    const offset = shouldAppend ? historyRows.length : 0;

    if (shouldAppend) {
      loadMoreBtn.disabled = true;
      loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat...';
    } else {
      resetHistory();
      showState('loading');
    }

    try {
      const limit = Number(document.getElementById('iph-limit').value || 20);
      const mode  = getMode();
      const res  = await fetch(BASE + 'purchase/item-price-history/data?item_id=' + itemId + '&limit=' + limit + '&offset=' + offset + '&mode=' + mode, {
        headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'}, signal: request.signal
      });
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); } catch(pe) { throw new Error('Response bukan JSON. Cek permission/route.'); }
      if (!json.ok) throw new Error(json.message || 'Gagal memuat data');
      if (requestSerial !== historyRequestSerial) return;

      const rows  = json.rows  || [];
      const total = json.meta?.total || 0;
      historyRows = shouldAppend ? historyRows.concat(rows) : rows;
      historyTotal = Number(total || 0);
      historyHasMore = Boolean(json.meta?.has_more);
      const modeLabel = mode === 'hpp' ? 'HPP / Satuan Isi' : 'Harga Beli / Pack';
      chartTitle.textContent = (selectedItem?.item_name || 'Item') + ' — ' + modeLabel;
      chartMeta.textContent  = historyRows.length + ' dari ' + historyTotal + ' total transaksi';

      if (!historyRows.length) { showState('empty'); return; }

      hideState();
      chartCard.style.display = 'block';
      tableCard.style.display = 'block';
      renderChart(historyRows, mode);
      renderTable(historyRows, historyTotal);
      updatePagination();
    } catch(e) {
      if (e.name === 'AbortError') return;
      if (shouldAppend && historyRows.length) {
        paginationMeta.textContent = 'Transaksi berikutnya belum dapat dimuat. Coba lagi.';
        loadMoreBtn.hidden = false;
        loadMoreBtn.disabled = false;
        loadMoreBtn.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Coba muat lagi';
        return;
      }
      resetHistory();
      showState('error', e.message || String(e));
    } finally {
      if (historyRequest === request) historyRequest = null;
    }
  }

  loadBtn.addEventListener('click', () => loadData(false));
  retryBtn.addEventListener('click', () => loadData(false));
  loadMoreBtn.addEventListener('click', () => loadData(true));
  document.querySelectorAll('input[name="iph_mode"]').forEach(el => el.addEventListener('change', () => { if (Number(itemIdInput.value) > 0) loadData(false); }));
  document.getElementById('iph-limit').addEventListener('change', () => { if (Number(itemIdInput.value) > 0) loadData(false); });

  /* ── Pre-populate if item already selected ─────────────────── */
  <?php if ($preselected): ?>
  itemInput.value = '<?php echo html_escape($preselected['item_name']); ?>';
  loadBtn.disabled = false;
  showItemPreview(selectedItem);
  loadData(false);
  <?php endif; ?>
})();
</script>
