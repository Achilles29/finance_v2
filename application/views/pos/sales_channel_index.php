<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$serviceTypes = is_array($filterOptions['service_types'] ?? null) ? $filterOptions['service_types'] : ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'];
?>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Sales Channel POS</h4>
      <p class="fin-page-subtitle mb-0">Kelola asal order seperti Walk In, GrabFood, ShopeeFood, atau WhatsApp. Service type tetap dipisah dan dijaga relasinya per channel.</p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'sales-channel']); ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Master Sales Channel</h5>
        <button id="btn-new" type="button" class="btn btn-primary btn-sm">Tambah Channel</button>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-2">
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ALL">Semua</button>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="ALL">Semua Service Type</button>
        <?php foreach ($serviceTypes as $serviceType): ?>
          <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="<?php echo html_escape((string)$serviceType); ?>"><?php echo html_escape((string)$serviceType); ?></button>
        <?php endforeach; ?>
      </div>

      <form class="row g-2 mb-3">
        <div class="col-md-10">
          <input id="q" class="form-control" placeholder="Cari kode channel / nama channel / catatan">
        </div>
        <div class="col-md-1">
          <select class="form-select" id="limit">
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
              <th>Kode</th>
              <th>Nama</th>
              <th>Default</th>
              <th>Diizinkan</th>
              <th>Fee</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width:180px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
      <div id="empty-state" class="text-muted py-3 d-none">Sales channel tidak ditemukan.</div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <small id="pagination-info" class="text-muted"></small>
        <div class="d-flex gap-1" id="pagination"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="salesChannelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="salesChannelModalLabel">Tambah Sales Channel</h5>
          <div class="small text-muted">Pisahkan channel penjualan dari service type. Channel boleh membatasi service type yang valid untuk transaksi itu.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-save" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Kode</label>
            <input class="form-control" name="channel_code" placeholder="Otomatis saat simpan" readonly>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1 small text-muted">Nama Channel</label>
            <input class="form-control" name="channel_name" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Service Type Default</label>
            <select class="form-select" name="service_type_default" id="service_type_default">
              <?php foreach ($serviceTypes as $serviceType): ?>
                <option value="<?php echo html_escape((string)$serviceType); ?>"><?php echo html_escape((string)$serviceType); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Fee Marketplace (%)</label>
            <input type="number" min="0" step="0.0001" class="form-control" name="marketplace_fee_percent" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Urutan</label>
            <input type="number" min="0" step="1" class="form-control" name="sort_order" value="0">
          </div>
          <div class="col-12">
            <label class="form-label mb-2 small text-muted">Service Type yang Diizinkan</label>
            <div class="row g-2">
              <?php foreach ($serviceTypes as $serviceType): ?>
                <div class="col-md-3 col-6">
                  <label class="border rounded-3 px-3 py-2 d-flex align-items-center gap-2 w-100">
                    <input class="form-check-input mt-0 service-type-check" type="checkbox" name="allowed_service_types[]" value="<?php echo html_escape((string)$serviceType); ?>">
                    <span><?php echo html_escape((string)$serviceType); ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="small text-muted mt-2">Guard aktif: service type default wajib termasuk dalam daftar yang diizinkan.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1 small text-muted">Default Channel</label>
            <div class="form-check form-switch border rounded-3 px-3 py-2">
              <input class="form-check-input" type="checkbox" role="switch" name="is_default" value="1" id="channel_is_default">
              <label class="form-check-label ms-2" for="channel_is_default">Jadikan default untuk transaksi baru</label>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1 small text-muted">Status</label>
            <div class="form-check form-switch border rounded-3 px-3 py-2">
              <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="channel_is_active" checked>
              <label class="form-check-label ms-2" for="channel_is_active">Aktif</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label mb-1 small text-muted">Catatan</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Opsional"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save">
          <span class="btn-label">Simpan</span>
          <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const serviceTypes = <?php echo json_encode(array_values($serviceTypes), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const state = {
    q: initialFilters.q || '',
    status: initialFilters.status || 'ACTIVE',
    service_type: initialFilters.service_type || 'ALL',
    page: parseInt(initialFilters.page || 1, 10),
    limit: parseInt(initialFilters.limit || 50, 10) || 50
  };

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const paginationInfo = document.getElementById('pagination-info');
  const pagination = document.getElementById('pagination');
  const modalEl = document.getElementById('salesChannelModal');
  const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('form-save');
  const modalTitle = document.getElementById('salesChannelModalLabel');
  const saveButton = document.getElementById('btn-save');
  const saveLabel = saveButton.querySelector('.btn-label');
  const saveSpinner = saveButton.querySelector('.spinner-border');
  const defaultTypeSelect = document.getElementById('service_type_default');

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }

  function statusBadge(flag) {
    return Number(flag || 0) === 1
      ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>'
      : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>';
  }

  function allowedTypeLabel(row) {
    const list = Array.isArray(row.allowed_service_type_list) ? row.allowed_service_type_list : [];
    return list.length ? escapeHtml(list.join(', ')) : '-';
  }

  function toggleSaveLoading(loading) {
    saveButton.disabled = !!loading;
    saveSpinner.classList.toggle('d-none', !loading);
    saveLabel.textContent = loading ? 'Menyimpan...' : 'Simpan';
  }

  function collectAllowedTypes() {
    return Array.from(form.querySelectorAll('.service-type-check:checked')).map((el) => el.value);
  }

  function ensureDefaultTypeAllowed() {
    const allowed = collectAllowedTypes();
    const current = String(defaultTypeSelect.value || '').toUpperCase();
    if (!allowed.length) return;
    if (!allowed.includes(current)) {
      defaultTypeSelect.value = allowed[0];
    }
  }

  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('status', state.status);
    p.set('service_type', state.service_type);
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 50));
    return p.toString();
  }

  function syncControls() {
    document.getElementById('q').value = state.q;
    document.getElementById('limit').value = String(state.limit || 50);
    document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === state.status));
    document.querySelectorAll('.pos-type-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.type === state.service_type));
  }

  async function getJson(url) {
    const r = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON. Cek session / permission / error backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data');
    return j;
  }

  async function postJson(url, payload) {
    const r = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify(payload)
    });
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response save bukan JSON. Kemungkinan ada warning / error PHP di backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data');
    return j;
  }

  function renderRows(rows) {
    if (!rows.length) {
      tableBody.innerHTML = '';
      emptyState.classList.remove('d-none');
      return;
    }
    emptyState.classList.add('d-none');
    tableBody.innerHTML = rows.map((r) => `
      <tr>
        <td class="text-nowrap">${escapeHtml(r.channel_code || '-')}</td>
        <td>
          <div class="fw-semibold">${escapeHtml(r.channel_name || '-')}</div>
          <div class="small text-muted">${escapeHtml(r.notes || '')}</div>
        </td>
        <td>${escapeHtml(r.service_type_default || '-')}</td>
        <td>${allowedTypeLabel(r)}</td>
        <td>${Number(r.marketplace_fee_percent || 0)}%</td>
        <td class="text-center">${statusBadge(r.is_active)}</td>
        <td class="text-center">
          <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-row='${JSON.stringify(r).replace(/'/g, '&#039;')}'>Edit</button>
            <button type="button" class="btn btn-sm ${Number(r.is_active || 0) === 1 ? 'btn-outline-danger' : 'btn-outline-success'} btn-toggle" data-id="${Number(r.id || 0)}">${Number(r.is_active || 0) === 1 ? 'Nonaktifkan' : 'Aktifkan'}</button>
            <button type="button" class="btn btn-sm btn-outline-dark btn-delete" data-id="${Number(r.id || 0)}">Hapus</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  function renderPagination(meta) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || state.limit || 50);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    paginationInfo.textContent = total ? `Menampilkan ${start}-${end} dari ${total} sales channel` : 'Belum ada data';
    pagination.innerHTML = Array.from({length: totalPages}, (_, idx) => {
      const p = idx + 1;
      return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'} btn-page" data-page="${p}">${p}</button>`;
    }).join('');
  }

  async function loadRows() {
    syncControls();
    const json = await getJson('<?php echo site_url('pos/sales-channels/data'); ?>?' + qsFromState());
    renderRows(json.rows || []);
    renderPagination(json.meta || {});
    history.replaceState(null, '', '<?php echo site_url('pos/sales-channels'); ?>?' + qsFromState());
  }

  function openNew() {
    form.reset();
    form.elements.id.value = '';
    form.elements.channel_code.value = '';
    form.elements.marketplace_fee_percent.value = '0';
    form.elements.sort_order.value = '0';
    form.elements.is_active.checked = true;
    form.elements.is_default.checked = false;
    form.querySelectorAll('.service-type-check').forEach((el, idx) => {
      el.checked = idx === 0;
    });
    defaultTypeSelect.value = serviceTypes[0] || 'DINE_IN';
    modalTitle.textContent = 'Tambah Sales Channel';
    if (modal) modal.show();
  }

  function openEdit(row) {
    form.reset();
    form.elements.id.value = row.id || '';
    form.elements.channel_code.value = row.channel_code || '';
    form.elements.channel_name.value = row.channel_name || '';
    form.elements.marketplace_fee_percent.value = Number(row.marketplace_fee_percent || 0);
    form.elements.sort_order.value = Number(row.sort_order || 0);
    form.elements.notes.value = row.notes || '';
    form.elements.is_active.checked = Number(row.is_active || 0) === 1;
    form.elements.is_default.checked = Number(row.is_default || 0) === 1;
    defaultTypeSelect.value = row.service_type_default || 'DINE_IN';
    const allowed = Array.isArray(row.allowed_service_type_list) ? row.allowed_service_type_list : [];
    form.querySelectorAll('.service-type-check').forEach((el) => {
      el.checked = allowed.includes(el.value);
    });
    ensureDefaultTypeAllowed();
    modalTitle.textContent = 'Edit Sales Channel';
    if (modal) modal.show();
  }

  function collectPayload() {
    return {
      id: Number(form.elements.id.value || 0),
      channel_code: form.elements.channel_code.value || '',
      channel_name: form.elements.channel_name.value || '',
      service_type_default: defaultTypeSelect.value || 'DINE_IN',
      allowed_service_types: collectAllowedTypes(),
      marketplace_fee_percent: Number(form.elements.marketplace_fee_percent.value || 0),
      sort_order: Number(form.elements.sort_order.value || 0),
      is_active: form.elements.is_active.checked ? 1 : 0,
      is_default: form.elements.is_default.checked ? 1 : 0,
      notes: form.elements.notes.value || ''
    };
  }

  async function saveRow() {
    const payload = collectPayload();
    if (!payload.channel_name.trim()) throw new Error('Nama sales channel wajib diisi.');
    if (!payload.allowed_service_types.length) throw new Error('Minimal pilih 1 service type yang diizinkan.');
    if (!payload.allowed_service_types.includes(payload.service_type_default)) {
      throw new Error('Service type default harus termasuk dalam daftar yang diizinkan.');
    }
    toggleSaveLoading(true);
    try {
      await postJson('<?php echo site_url('pos/sales-channels/save'); ?>', payload);
      if (modal) modal.hide();
      await loadRows();
    } finally {
      toggleSaveLoading(false);
    }
  }

  async function toggleRow(id) {
    await getJson('<?php echo site_url('pos/sales-channels/toggle'); ?>/' + id);
    await loadRows();
  }

  async function deleteRow(id) {
    if (!window.confirm('Hapus sales channel ini?')) return;
    await getJson('<?php echo site_url('pos/sales-channels/delete'); ?>/' + id);
    await loadRows();
  }

  defaultTypeSelect.addEventListener('change', () => {
    const target = String(defaultTypeSelect.value || '');
    const checkbox = form.querySelector(`.service-type-check[value="${target}"]`);
    if (checkbox) checkbox.checked = true;
  });
  form.querySelectorAll('.service-type-check').forEach((el) => el.addEventListener('change', ensureDefaultTypeAllowed));

  document.getElementById('btn-new').addEventListener('click', openNew);
  document.getElementById('btn-save').addEventListener('click', () => saveRow().catch((e) => alert(e.message || 'Gagal menyimpan sales channel')));
  document.getElementById('q').addEventListener('input', (e) => { state.q = e.target.value || ''; state.page = 1; loadRows().catch((err) => alert(err.message)); });
  document.getElementById('limit').addEventListener('change', (e) => { state.limit = Number(e.target.value || 50); state.page = 1; loadRows().catch((err) => alert(err.message)); });
  document.getElementById('btn-clear-filter').addEventListener('click', () => {
    state.q = '';
    state.status = 'ACTIVE';
    state.service_type = 'ALL';
    state.page = 1;
    state.limit = 50;
    loadRows().catch((err) => alert(err.message));
  });
  document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.addEventListener('click', () => {
    state.status = btn.dataset.status || 'ACTIVE';
    state.page = 1;
    loadRows().catch((e) => alert(e.message || 'Gagal memuat data'));
  }));
  document.querySelectorAll('.pos-type-tab').forEach((btn) => btn.addEventListener('click', () => {
    state.service_type = btn.dataset.type || 'ALL';
    state.page = 1;
    loadRows().catch((e) => alert(e.message || 'Gagal memuat data'));
  }));

  pagination.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-page');
    if (!btn) return;
    state.page = Number(btn.dataset.page || 1);
    loadRows().catch((err) => alert(err.message));
  });

  tableBody.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.btn-edit');
    if (editBtn) {
      openEdit(JSON.parse(editBtn.dataset.row.replace(/&#039;/g, "'")));
      return;
    }
    const toggleBtn = e.target.closest('.btn-toggle');
    if (toggleBtn) {
      toggleRow(Number(toggleBtn.dataset.id || 0)).catch((err) => alert(err.message));
      return;
    }
    const deleteBtn = e.target.closest('.btn-delete');
    if (deleteBtn) {
      deleteRow(Number(deleteBtn.dataset.id || 0)).catch((err) => alert(err.message));
    }
  });

  loadRows().catch((e) => alert(e.message || 'Gagal memuat sales channel'));
});
</script>
