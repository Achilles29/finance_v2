<?php
$filters = is_array($filters ?? null) ? $filters : [];
$categories = is_array($categories ?? null) ? $categories : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
?>
<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Resep / Formula Base-Prepare</h4>
      <p class="fin-page-subtitle mb-0">Daftar semua resep base-prepare dengan patokan hasil 1x produksi, ringkasan HPP, dan akses edit per component.</p>
    </div>
    <a class="btn btn-outline-primary btn-sm" href="<?php echo site_url('production/component-cost-variables'); ?>">Pengaturan Variable Cost</a>
  </div>

  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'formula']); ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex gap-2 flex-wrap mb-2" id="status-tabs">
        <button class="btn btn-sm btn-outline-primary tab-status" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary tab-status" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary tab-status" data-status="ALL">Semua</button>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-3" id="type-tabs">
        <button class="btn btn-sm btn-outline-secondary tab-type" data-type="ALL">Semua Tipe</button>
        <button class="btn btn-sm btn-outline-secondary tab-type" data-type="BASE">Base</button>
        <button class="btn btn-sm btn-outline-secondary tab-type" data-type="PREPARE">Prepare</button>
      </div>

      <form id="filter-form" class="row g-2 mb-3">
        <div class="col-md-3">
          <select class="form-select" id="division_id">
            <option value="0">Semua Divisi</option>
            <?php foreach ($divisions as $d): ?>
              <option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape((string)$d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" id="category_id">
            <option value="0">Semua Kategori</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>"><?php echo html_escape((string)$c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <input id="q" class="form-control" placeholder="Cari nama component...">
        </div>
        <div class="col-md-2">
          <select class="form-select" id="limit">
            <option value="0">Semua</option>
            <option value="25">25</option>
            <option value="50" selected>50</option>
            <option value="100">100</option>
            <option value="200">200</option>
          </select>
        </div>
        <div class="col-md-1 d-grid">
          <button type="button" id="btn-clear-filter" class="btn btn-outline-danger">Clear</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Tipe</th>
              <th class="text-end">Hasil 1x Produksi</th>
              <th>Divisi</th>
              <th>Kategori</th>
              <th class="text-end">Line</th>
              <th class="text-end">HPP Std</th>
              <th class="text-end">HPP Live</th>
              <th class="text-end">HPP Total</th>
              <th>Digunakan</th>
              <th>Status</th>
              <th style="width:140px;" class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
      <div id="empty-state" class="text-muted py-3 d-none">Data tidak ditemukan.</div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <small id="pagination-info" class="text-muted"></small>
        <div class="d-flex gap-1" id="pagination"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const filters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const state = {
    q: filters.q || '',
    status: filters.status || 'ACTIVE',
    type: filters.type || 'ALL',
    division_id: parseInt(filters.division_id || 0, 10),
    category_id: parseInt(filters.category_id || 0, 10),
    page: parseInt(filters.page || 1, 10),
    limit: parseInt(filters.limit || 50, 10),
    rows: [],
    meta: null
  };

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const paginationInfo = document.getElementById('pagination-info');
  const pagination = document.getElementById('pagination');

  function esc(v) { return String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
  function fmt(n, d = 2) { return Number(n || 0).toLocaleString('id-ID', {minimumFractionDigits: d, maximumFractionDigits: d}); }
  function statusBadge(v) { return Number(v || 0) === 1 ? '<span class="status-badge status-active">Aktif</span>' : '<span class="status-badge status-inactive">Nonaktif</span>'; }

  function qs() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('status', state.status);
    p.set('type', state.type);
    p.set('division_id', String(state.division_id || 0));
    p.set('category_id', String(state.category_id || 0));
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 50));
    return p.toString();
  }

  function syncControls() {
    document.getElementById('q').value = state.q;
    document.getElementById('division_id').value = String(state.division_id || 0);
    document.getElementById('category_id').value = String(state.category_id || 0);
    document.getElementById('limit').value = String(state.limit || 50);
    document.querySelectorAll('.tab-status').forEach((b) => b.classList.toggle('active', b.dataset.status === state.status));
    document.querySelectorAll('.tab-type').forEach((b) => b.classList.toggle('active', b.dataset.type === state.type));
  }

  async function getJson(url) {
    const r = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const t = await r.text();
    let j; try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Request gagal');
    return j;
  }

  function renderRows() {
    const rows = Array.isArray(state.rows) ? state.rows : [];
    if (rows.length === 0) {
      tableBody.innerHTML = '';
      emptyState.classList.remove('d-none');
      paginationInfo.textContent = '0 data';
      pagination.innerHTML = '';
      return;
    }
    emptyState.classList.add('d-none');
    tableBody.innerHTML = rows.map((r) => `
      <tr>
        <td>${esc(r.component_name || '-')}</td>
        <td>${esc(r.component_type || '-')}</td>
        <td class="text-end fw-semibold">${fmt(r.output_batch_qty || 1, 2)} ${esc(r.uom_code || '-')}</td>
        <td>${esc(r.division_name || '-')}</td>
        <td>${esc(r.category_name || '-')}</td>
        <td class="text-end">${Number(r.formula_line_count || 0)}</td>
        <td class="text-end">${fmt(r.hpp_standard || 0, 2)}</td>
        <td class="text-end">${fmt(r.hpp_live || 0, 2)}</td>
        <td class="text-end fw-semibold">${fmt(r.hpp_total || 0, 2)}</td>
        <td>
          ${parseInt(r.usage_count || 0, 10) > 0
            ? `<a href="<?php echo site_url('production/component-masters/usage'); ?>/${Number(r.id || 0)}" class="badge bg-warning-subtle text-warning-emphasis text-decoration-none">Ya (${parseInt(r.usage_count || 0, 10)})</a><div class="small text-muted mt-1">${parseInt(r.component_usage_count || 0, 10)} base/prepare • ${parseInt(r.product_usage_count || 0, 10)} produk</div>`
            : `<span class="badge bg-light text-body-secondary border">Tidak</span>`
          }
        </td>
        <td>${statusBadge(r.is_active)}</td>
        <td class="component-action-cell">
          <div class="component-action-stack">
            <a href="<?php echo site_url('production/component-formulas/detail'); ?>/${Number(r.id || 0)}" class="btn btn-outline-info action-icon-btn component-action-btn" title="Detail Formula" aria-label="Detail Formula"><i class="ri ri-eye-line"></i></a>
            <a href="<?php echo site_url('production/component-formulas/edit'); ?>/${Number(r.id || 0)}" class="btn btn-outline-primary action-icon-btn component-action-btn" title="Edit Formula" aria-label="Edit Formula"><i class="ri ri-edit-line"></i></a>
          </div>
        </td>
      </tr>
    `).join('');

    const m = state.meta || {total: rows.length, page: 1, total_pages: 1, limit: state.limit};
    const start = m.total === 0 ? 0 : ((m.page - 1) * (m.limit || m.total)) + 1;
    const end = m.limit === 0 ? m.total : Math.min(m.total, m.page * m.limit);
    paginationInfo.textContent = `${start}-${end} dari ${m.total} data`;

    if ((m.total_pages || 1) <= 1) {
      pagination.innerHTML = '';
      return;
    }
    const pages = [];
    for (let i = 1; i <= m.total_pages; i += 1) {
      if (i === 1 || i === m.total_pages || Math.abs(i - m.page) <= 2) pages.push(i);
    }
    const uniq = [...new Set(pages)];
    pagination.innerHTML = uniq.map((p) => `<button class="btn btn-sm ${p === m.page ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`).join('');
  }

  async function loadData() {
    syncControls();
    const j = await getJson('<?php echo site_url('production/component-formulas/data'); ?>?' + qs());
    state.rows = j.rows || [];
    state.meta = j.meta || null;
    renderRows();
    history.replaceState(null, '', '<?php echo site_url('production/component-formulas'); ?>?' + qs());
  }

  document.querySelectorAll('.tab-status').forEach((b) => b.addEventListener('click', () => { state.status = b.dataset.status; state.page = 1; loadData(); }));
  document.querySelectorAll('.tab-type').forEach((b) => b.addEventListener('click', () => { state.type = b.dataset.type; state.page = 1; loadData(); }));
  document.getElementById('division_id').addEventListener('change', (e) => { state.division_id = Number(e.target.value || 0); state.page = 1; loadData(); });
  document.getElementById('category_id').addEventListener('change', (e) => { state.category_id = Number(e.target.value || 0); state.page = 1; loadData(); });
  document.getElementById('limit').addEventListener('change', (e) => { state.limit = Number(e.target.value || 50); state.page = 1; loadData(); });
  let timer = null;
  document.getElementById('q').addEventListener('input', (e) => { clearTimeout(timer); timer = setTimeout(() => { state.q = e.target.value || ''; state.page = 1; loadData(); }, 220); });
  document.getElementById('btn-clear-filter').addEventListener('click', () => {
    state.q = '';
    state.status = 'ACTIVE';
    state.type = 'ALL';
    state.division_id = 0;
    state.category_id = 0;
    state.page = 1;
    state.limit = 50;
    loadData();
  });
  pagination.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-page]');
    if (!btn) return;
    state.page = Number(btn.dataset.page || 1);
    loadData();
  });

  loadData();
});
</script>
