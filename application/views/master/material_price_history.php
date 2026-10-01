<?php
$materialList = is_array($material_list ?? null) ? $material_list : [];
$selectedId   = (int)($selected_id ?? 0);
?>
<script src="<?php echo base_url('assets/libs/apex-charts/apexcharts.js'); ?>"></script>

<style>
  .mph-filter-card,
  .mph-chart-card,
  .mph-table-card { border:0; border-radius:18px; box-shadow:0 6px 20px rgba(58,38,30,.07); }
  .mph-spinner { display:none; text-align:center; padding:2rem; color:#7a6a60; }
  .mph-empty  { display:none; text-align:center; padding:2rem; color:#7a6a60; border:1px dashed #d9c9bc; border-radius:16px; }
  .mph-table th { font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .mph-table td { font-size:.86rem; vertical-align:middle; }
  .mph-item-badge { font-size:.72rem; font-weight:700; background:#f0edff; color:#3730a3; border-radius:999px; padding:.12rem .5rem; white-space:nowrap; }
  .mph-div-badge  { font-size:.72rem; font-weight:700; background:#e8f3ef; color:#1a6450; border-radius:999px; padding:.12rem .5rem; white-space:nowrap; }
</style>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'price-history']); ?>

<div class="fin-page-header mb-3">
  <div>
    <h4 class="fin-page-title"><i class="ri ri-line-chart-line me-1 text-primary"></i>Riwayat Harga Bahan Baku</h4>
    <p class="fin-page-subtitle mb-0">Tren harga beli &amp; HPP per satuan isi dari data purchase receipt, dikelompokkan per item sumber</p>
  </div>
  <div class="fin-page-actions">
    <a href="<?php echo site_url('master/material'); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri ri-arrow-left-line me-1"></i>Master Bahan
    </a>
  </div>
</div>

<!-- Filter Card -->
<div class="card mph-filter-card mb-3">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small mb-1 fw-semibold">Bahan Baku <span class="text-danger">*</span></label>
        <select id="mph-material-select" class="form-select">
          <option value="0">-- Pilih bahan baku --</option>
          <?php foreach ($materialList as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>"
              <?php echo $selectedId === (int)$m['id'] ? 'selected' : ''; ?>>
              <?php echo html_escape($m['material_name']); ?>
              <?php if (!empty($m['material_code'])): ?>(<?php echo html_escape($m['material_code']); ?>)<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Filter Item</label>
        <select id="mph-item-select" class="form-select" disabled>
          <option value="0">Semua Item</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">N Transaksi Terakhir</label>
        <select id="mph-limit-select" class="form-select">
          <?php foreach ([10, 20, 30, 50, 100] as $l): ?>
            <option value="<?php echo $l; ?>" <?php echo $l === 20 ? 'selected' : ''; ?>><?php echo $l; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Tampilkan</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="mph_mode" id="mph_hpp" value="hpp" checked>
          <label class="btn btn-outline-primary btn-sm" for="mph_hpp">HPP / Satuan Isi</label>
          <input type="radio" class="btn-check" name="mph_mode" id="mph_buy" value="buy">
          <label class="btn btn-outline-primary btn-sm" for="mph_buy">Harga Beli / Pack</label>
        </div>
      </div>
      <div class="col-md-2">
        <button type="button" id="mph-load-btn" class="btn btn-primary w-100 btn-sm">
          <i class="ri ri-refresh-line me-1"></i>Tampilkan
        </button>
      </div>
    </div>
  </div>
</div>

<div class="mph-spinner" id="mph-spinner">
  <div class="spinner-border text-secondary" role="status"></div>
  <div class="mt-2 small">Memuat data...</div>
</div>
<div class="mph-empty" id="mph-empty">
  <i class="ri ri-inbox-line ri-2x d-block mb-2"></i>
  Belum ada data purchase untuk bahan &amp; filter ini.
</div>

<!-- Chart -->
<div class="card mph-chart-card mb-3" id="mph-chart-card" style="display:none">
  <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="fw-semibold" id="mph-chart-title">Tren Harga</div>
    <div class="d-flex gap-2 align-items-center">
      <small class="text-muted" id="mph-chart-meta"></small>
    </div>
  </div>
  <div class="card-body py-3">
    <div id="mph-chart-container"></div>
  </div>
</div>

<!-- Tabel -->
<div class="card mph-table-card" id="mph-table-card" style="display:none">
  <div class="card-header py-2 d-flex justify-content-between align-items-center">
    <div class="fw-semibold">Detail Transaksi</div>
    <small class="text-muted" id="mph-table-meta"></small>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0 mph-table">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Tgl</th>
          <th>Item / Brand</th>
          <th>Divisi</th>
          <th class="text-end">Qty Isi</th>
          <th class="text-end">Qty Pack</th>
          <th class="text-end">HPP / Isi</th>
          <th class="text-end">Harga / Pack</th>
        </tr>
      </thead>
      <tbody id="mph-table-body"></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const BASE = '<?php echo site_url(); ?>';
  const initMaterialId = <?php echo $selectedId > 0 ? (int)$selectedId : 0; ?>;
  const ITEM_COLORS = ['#1a6450','#7c3aed','#c0392b','#d97706','#0369a1','#065f46','#6d28d9','#b45309'];
  let mphChart = null;
  let currentItems = []; // distinct items from last load

  const selMaterial = document.getElementById('mph-material-select');
  const selItem     = document.getElementById('mph-item-select');
  const selLimit    = document.getElementById('mph-limit-select');
  const btnLoad     = document.getElementById('mph-load-btn');
  const spinner     = document.getElementById('mph-spinner');
  const emptyBox    = document.getElementById('mph-empty');
  const chartCard   = document.getElementById('mph-chart-card');
  const tableCard   = document.getElementById('mph-table-card');
  const tableBody   = document.getElementById('mph-table-body');
  const chartMeta   = document.getElementById('mph-chart-meta');
  const tableMeta   = document.getElementById('mph-table-meta');
  const chartTitle  = document.getElementById('mph-chart-title');

  function money(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  function num(v, d) { return Number(v || 0).toLocaleString('id-ID', {minimumFractionDigits: d ?? 2, maximumFractionDigits: d ?? 2}); }
  function esc(v) { const d = document.createElement('div'); d.textContent = String(v ?? ''); return d.innerHTML; }
  function getMode() { return document.querySelector('input[name="mph_mode"]:checked')?.value || 'hpp'; }

  function populateItemFilter(items) {
    currentItems = items || [];
    selItem.innerHTML = '<option value="0">Semua Item (' + currentItems.length + ')</option>';
    currentItems.forEach(function(it) {
      const opt = document.createElement('option');
      opt.value = it.item_id || 0;
      opt.textContent = (it.item_label || 'Item') + (it.brand && it.brand !== it.item_label ? ' · ' + it.brand : '');
      selItem.appendChild(opt);
    });
    selItem.disabled = currentItems.length <= 1;
  }

  function setLoading(loading) {
    spinner.style.display   = loading ? 'block' : 'none';
    emptyBox.style.display  = 'none';
    if (loading) { chartCard.style.display = 'none'; tableCard.style.display = 'none'; }
  }

  function buildChartSeries(rows, mode) {
    // Group by item_id
    const groups = {};
    rows.forEach(function(r) {
      const key = r.item_id || 0;
      if (!groups[key]) {
        groups[key] = {
          label: r.item_name || ('Item #' + key),
          data: [],
        };
      }
      groups[key].data.unshift({
        x: r.movement_date,
        y: parseFloat(mode === 'hpp' ? r.unit_cost : r.price_per_buy) || 0,
      });
    });

    return Object.values(groups).map(function(g, i) {
      return { name: g.label, data: g.data, color: ITEM_COLORS[i % ITEM_COLORS.length] };
    });
  }

  function renderChart(series, mode, matName) {
    const container = document.getElementById('mph-chart-container');
    container.innerHTML = '';
    if (mphChart) { try { mphChart.destroy(); } catch(e){} mphChart = null; }
    if (!series.length) return;

    const axisLabel = mode === 'hpp' ? 'HPP / Satuan Isi' : 'Harga Beli / Pack';
    chartTitle.textContent = matName + ' — ' + axisLabel;

    const options = {
      chart: { type: 'line', height: 300, toolbar: {show: false}, zoom: {enabled: false} },
      series: series,
      stroke: { curve: 'smooth', width: 2 },
      markers: { size: series.length === 1 ? 5 : 3, strokeWidth: 0 },
      xaxis: {
        type: 'category',
        tickAmount: 10,
        labels: { rotate: -35, style: {fontSize:'11px'} },
      },
      yaxis: { labels: { formatter: function(v) { return money(v); } } },
      dataLabels: { enabled: false },
      fill: { type: series.length === 1 ? 'gradient' : 'solid',
              gradient: { opacityFrom:.3, opacityTo:.05 } },
      legend: { position: 'top', fontSize: '12px' },
      tooltip: { y: { formatter: function(v) { return money(v); } } },
      grid: { borderColor: '#f0ebe6', strokeDashArray: 3 },
    };

    if (window.ApexCharts) {
      mphChart = new ApexCharts(container, options);
      mphChart.render();
    } else {
      container.innerHTML = '<div class="text-muted text-center py-3">ApexCharts tidak tersedia.</div>';
    }
  }

  function renderTable(rows, total) {
    tableMeta.textContent = rows.length + ' dari ' + total + ' total transaksi';
    if (!rows.length) {
      tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada data.</td></tr>';
      return;
    }
    tableBody.innerHTML = rows.map(function(r, i) {
      const brand = r.brand && r.brand !== r.item_name ? r.brand : '';
      return '<tr>' +
        '<td class="text-muted small">' + (i + 1) + '</td>' +
        '<td class="small">' + esc(r.movement_date || '-') + '</td>' +
        '<td><div class="fw-semibold">' + esc(r.item_name || '-') + '</div>' +
            (brand ? '<div class="small text-muted">' + esc(brand) + '</div>' : '') +
            (r.item_code ? '<div class="small text-muted">' + esc(r.item_code) + '</div>' : '') +
        '</td>' +
        '<td>' + (r.division_name ? '<span class="mph-div-badge">' + esc(r.division_name) + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
        '<td class="text-end">' + num(r.qty_content_delta) + ' <span class="text-muted small">' + esc(r.content_uom || '') + '</span></td>' +
        '<td class="text-end">' + (parseFloat(r.qty_buy_delta || 0) > 0 ? num(r.qty_buy_delta) + ' <span class="text-muted small">pack</span>' : '<span class="text-muted">-</span>') + '</td>' +
        '<td class="text-end fw-semibold">' + money(r.unit_cost) + '</td>' +
        '<td class="text-end">' + (parseFloat(r.price_per_buy || 0) > 0 ? money(r.price_per_buy) : '<span class="text-muted">-</span>') + '</td>' +
        '</tr>';
    }).join('');
  }

  async function loadData() {
    const materialId = Number(selMaterial.value || 0);
    const itemId     = Number(selItem.value || 0);
    const limit      = Number(selLimit.value || 20);
    const mode       = getMode();
    if (materialId <= 0) return;

    setLoading(true);
    try {
      const qs  = 'material_id=' + materialId + '&item_id=' + itemId + '&limit=' + limit + '&mode=' + mode;
      const res = await fetch(BASE + 'master/material/price-history-data?' + qs, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Gagal memuat data');

      const rows  = Array.isArray(json.rows)  ? json.rows  : [];
      const items = Array.isArray(json.items) ? json.items : [];
      const total = Number(json.meta?.total || 0);
      const matName = selMaterial.options[selMaterial.selectedIndex]?.text || '';

      // Update item filter if first load (no item filter applied)
      if (itemId === 0) populateItemFilter(items);

      chartMeta.textContent = rows.length + ' dari ' + total + ' transaksi, ' + items.length + ' item sumber';

      const hasData = rows.length > 0;
      chartCard.style.display = hasData ? 'block' : 'none';
      tableCard.style.display = hasData ? 'block' : 'none';
      emptyBox.style.display  = hasData ? 'none'  : 'block';

      if (hasData) {
        const series = buildChartSeries(rows, mode);
        renderChart(series, mode, matName);
        renderTable(rows, total);
      }

      history.replaceState({}, '', BASE + 'master/material/price-history/' + materialId);
    } catch(e) {
      emptyBox.style.display = 'block';
      emptyBox.innerHTML = '<i class="ri ri-error-warning-line ri-2x d-block mb-2"></i>' + esc(e.message || String(e));
    } finally {
      setLoading(false);
    }
  }

  btnLoad.addEventListener('click', loadData);
  selMaterial.addEventListener('change', function() {
    selItem.innerHTML = '<option value="0">Semua Item</option>';
    selItem.disabled = true;
    loadData();
  });
  selItem.addEventListener('change', loadData);
  document.querySelectorAll('input[name="mph_mode"]').forEach(function(el) {
    el.addEventListener('change', function() {
      if (chartCard.style.display !== 'none') loadData();
    });
  });

  if (initMaterialId > 0) loadData();
})();
</script>
