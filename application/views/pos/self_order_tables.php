<?php
$filters = is_array($filters ?? null) ? $filters : [];
$settings = is_array($settings ?? null) ? $settings : [];
?>

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'self-order']); ?>
  <?php $this->load->view('pos/_self_order_tabs', ['self_order_tab_active' => 'tables']); ?>

  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">QR Meja Self Order</h4>
      <p class="fin-page-subtitle mb-0">Kelola meja aktif, preview QR scan, dan cetak kartu QR untuk order mandiri pelanggan.</p>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h5 class="mb-1">Daftar Meja</h5>
          <div class="small text-muted">Base URL member aktif: <span class="fw-semibold"><?php echo html_escape((string)($settings['member_base_url'] ?? '-')); ?></span></div>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo site_url('pos/self-order/tables/print'); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Print QR</a>
          <button id="btn-bulk-new" type="button" class="btn btn-outline-primary btn-sm">Bulk Tambah</button>
          <button id="btn-new" type="button" class="btn btn-primary btn-sm">Tambah Meja</button>
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ALL">Semua</button>
      </div>

      <form class="row g-2 mb-3">
        <div class="col-md-10">
          <input id="q" class="form-control" placeholder="Cari nama meja atau label QR">
        </div>
        <div class="col-md-2 d-grid">
          <button type="button" id="btn-clear-filter" class="btn btn-outline-danger">Clear</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th style="width:90px;">QR</th>
              <th>Meja</th>
              <th class="text-center">Kapasitas</th>
              <th>URL Scan</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width:180px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
      <div id="empty-state" class="text-muted py-3 d-none">Belum ada meja self order.</div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="tableModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="tableModalLabel">Tambah Meja</h5>
          <div class="small text-muted">Nomor / nama meja akan disimpan ke `db_finance` dan dipakai saat scan QR.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-save" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-7">
            <label class="form-label small text-muted mb-1">Nama Meja</label>
            <input class="form-control" name="nama_meja" required>
          </div>
          <div class="col-md-5">
            <label class="form-label small text-muted mb-1">Label QR</label>
            <input class="form-control" name="qr_label" placeholder="Opsional">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Kapasitas</label>
            <input type="number" min="0" step="1" class="form-control" name="capacity" value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Urutan</label>
            <input type="number" min="0" step="1" class="form-control" name="sort_order" value="0">
          </div>
          <div class="col-12">
            <div class="form-check form-switch border rounded-3 px-3 py-2">
              <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="table_is_active" checked>
              <label class="form-check-label ms-2" for="table_is_active">Aktif</label>
            </div>
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

<!-- Bulk Tambah Modal -->
<div class="modal fade finance-ui-modal" id="bulkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">Bulk Tambah Meja</h5>
          <div class="small text-muted">Buat beberapa meja sekaligus dengan penomoran otomatis.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-bulk" class="row g-3">
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Prefix Nama Meja <span class="text-danger">*</span></label>
            <input class="form-control" id="bulk-prefix" placeholder="Contoh: Meja, Table, T" required>
            <div class="form-text">Nama meja akan menjadi <em>[prefix] [nomor]</em>, misal: Meja 1, Meja 2, …</div>
          </div>
          <div class="col-6">
            <label class="form-label small text-muted mb-1">Dari Nomor</label>
            <input type="number" min="1" step="1" class="form-control" id="bulk-from" value="1">
          </div>
          <div class="col-6">
            <label class="form-label small text-muted mb-1">Sampai Nomor</label>
            <input type="number" min="1" step="1" class="form-control" id="bulk-to" value="10">
          </div>
          <div class="col-6">
            <label class="form-label small text-muted mb-1">Kapasitas</label>
            <input type="number" min="0" step="1" class="form-control" id="bulk-capacity" value="0">
          </div>
          <div class="col-6">
            <label class="form-label small text-muted mb-1">Urutan Awal</label>
            <input type="number" min="0" step="1" class="form-control" id="bulk-sort-start" value="0">
          </div>
          <div class="col-12">
            <div class="form-check form-switch border rounded-3 px-3 py-2">
              <input class="form-check-input" type="checkbox" role="switch" id="bulk-is-active" checked>
              <label class="form-check-label ms-2" for="bulk-is-active">Aktif</label>
            </div>
          </div>
          <div class="col-12" id="bulk-preview-wrap" style="display:none">
            <div class="small text-muted mb-1 fw-semibold">Preview nama meja yang akan dibuat:</div>
            <div id="bulk-preview" class="border rounded-3 p-2 bg-light" style="font-size:.82rem;max-height:140px;overflow-y:auto;line-height:1.8"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-bulk-save">
          <span class="btn-bulk-label">Buat Meja</span>
          <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const state = {
    q: initialFilters.q || '',
    status: initialFilters.status || 'ACTIVE'
  };

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const modalEl = document.getElementById('tableModal');
  const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('form-save');
  const modalTitle = document.getElementById('tableModalLabel');
  const saveButton = document.getElementById('btn-save');
  const saveLabel = saveButton.querySelector('.btn-label');
  const saveSpinner = saveButton.querySelector('.spinner-border');

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }

  function statusBadge(flag) {
    return Number(flag || 0) === 1
      ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>'
      : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>';
  }

  function toggleSaveLoading(loading) {
    saveButton.disabled = !!loading;
    saveSpinner.classList.toggle('d-none', !loading);
    saveLabel.textContent = loading ? 'Menyimpan...' : 'Simpan';
  }

  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('status', state.status);
    return p.toString();
  }

  function syncControls() {
    document.getElementById('q').value = state.q;
    document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === state.status));
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
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response save bukan JSON. Kemungkinan ada warning / error backend.'); }
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
    tableBody.innerHTML = rows.map((row) => {
      const qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=96x96&data=' + encodeURIComponent(row.qr_url || '');
      return `
        <tr>
          <td><img src="${qrImg}" alt="QR ${escapeHtml(row.nama_meja || '')}" class="rounded border" style="width:72px;height:72px;object-fit:cover;"></td>
          <td>
            <div class="fw-semibold">${escapeHtml(row.nama_meja || '-')}</div>
            <div class="small text-muted">${escapeHtml(row.qr_label || 'Tanpa label QR')}</div>
          </td>
          <td class="text-center">${Number(row.capacity || 0)}</td>
          <td>
            <div class="small text-break">${escapeHtml(row.qr_url || '')}</div>
          </td>
          <td class="text-center">${statusBadge(row.is_active)}</td>
          <td class="text-center">
            <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
              <button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-row='${JSON.stringify(row).replace(/'/g, '&#39;')}'>Edit</button>
              <a href="${escapeHtml(row.qr_url || '#')}" target="_blank" class="btn btn-sm btn-outline-secondary">Buka QR</a>
              <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="${Number(row.id || 0)}">Hapus</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    tableBody.querySelectorAll('.btn-edit').forEach((btn) => btn.addEventListener('click', () => openEdit(JSON.parse(btn.dataset.row))));
    tableBody.querySelectorAll('.btn-delete').forEach((btn) => btn.addEventListener('click', () => deleteRow(Number(btn.dataset.id || 0))));
  }

  async function loadRows() {
    syncControls();
    const json = await getJson('<?php echo site_url('pos/self-order/tables/data'); ?>?' + qsFromState());
    renderRows(Array.isArray(json.rows) ? json.rows : []);
    history.replaceState(null, '', '<?php echo site_url('pos/self-order/tables'); ?>?' + qsFromState());
  }

  function resetForm() {
    form.reset();
    form.id.value = '';
    form.capacity.value = '0';
    form.sort_order.value = '0';
    form.is_active.checked = true;
  }

  function openCreate() {
    resetForm();
    modalTitle.textContent = 'Tambah Meja';
    modal && modal.show();
  }

  function openEdit(row) {
    resetForm();
    form.id.value = row.id || '';
    form.nama_meja.value = row.nama_meja || '';
    form.qr_label.value = row.qr_label || '';
    form.capacity.value = Number(row.capacity || 0);
    form.sort_order.value = Number(row.sort_order || 0);
    form.is_active.checked = Number(row.is_active || 0) === 1;
    modalTitle.textContent = 'Edit Meja';
    modal && modal.show();
  }

  async function saveRow() {
    const payload = {
      id: Number(form.id.value || 0),
      nama_meja: String(form.nama_meja.value || '').trim(),
      qr_label: String(form.qr_label.value || '').trim(),
      capacity: Number(form.capacity.value || 0),
      sort_order: Number(form.sort_order.value || 0),
      is_active: form.is_active.checked ? 1 : 0
    };
    if (!payload.nama_meja) throw new Error('Nama meja wajib diisi.');
    toggleSaveLoading(true);
    try {
      await postJson('<?php echo site_url('pos/self-order/tables/save'); ?>', payload);
      modal && modal.hide();
      await loadRows();
    } finally {
      toggleSaveLoading(false);
    }
  }

  async function deleteRow(id) {
    if (!id) return;
    if (!window.confirm('Hapus meja ini?')) return;
    await getJson('<?php echo site_url('pos/self-order/tables/delete'); ?>/' + id);
    await loadRows();
  }

  document.getElementById('btn-new').addEventListener('click', openCreate);
  document.getElementById('btn-save').addEventListener('click', () => saveRow().catch((e) => alert(e.message || 'Gagal menyimpan meja')));
  document.getElementById('q').addEventListener('input', function () { state.q = this.value; loadRows().catch((e) => alert(e.message || 'Gagal memuat meja')); });
  document.getElementById('btn-clear-filter').addEventListener('click', function () { state.q = ''; state.status = 'ACTIVE'; loadRows().catch((e) => alert(e.message || 'Gagal memuat meja')); });
  document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.addEventListener('click', function () { state.status = this.dataset.status; loadRows().catch((e) => alert(e.message || 'Gagal memuat meja')); }));

  // ── Bulk Tambah ────────────────────────────────────────────────────────────
  const bulkModalEl = document.getElementById('bulkModal');
  const bulkModal   = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(bulkModalEl) : null;
  const bulkSaveBtn = document.getElementById('btn-bulk-save');
  const bulkLabel   = bulkSaveBtn.querySelector('.btn-bulk-label');
  const bulkSpinner = bulkSaveBtn.querySelector('.spinner-border');

  function bulkGenNames() {
    const prefix = document.getElementById('bulk-prefix').value.trim();
    const from   = parseInt(document.getElementById('bulk-from').value, 10) || 1;
    const to     = parseInt(document.getElementById('bulk-to').value, 10)   || 1;
    if (!prefix || from < 1 || to < from) return [];
    const count = Math.min(to - from + 1, 100);
    const names = [];
    for (let i = 0; i < count; i++) names.push(prefix + ' ' + (from + i));
    return names;
  }

  function bulkUpdatePreview() {
    const names   = bulkGenNames();
    const wrap    = document.getElementById('bulk-preview-wrap');
    const preview = document.getElementById('bulk-preview');
    const btn     = document.getElementById('btn-bulk-save');
    const lbl     = btn.querySelector('.btn-bulk-label');
    if (!names.length) {
      wrap.style.display = 'none';
      lbl.textContent = 'Buat Meja';
      return;
    }
    wrap.style.display = '';
    lbl.textContent = 'Buat ' + names.length + ' Meja';
    preview.innerHTML = names.map((n, i) => {
      const sort = (parseInt(document.getElementById('bulk-sort-start').value, 10) || 0) + i;
      return '<span class="badge bg-secondary-subtle text-secondary-emphasis me-1 mb-1" style="font-size:.8rem">' +
        n.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])) +
        (sort > 0 ? ' <span class="text-muted">#' + sort + '</span>' : '') +
        '</span>';
    }).join('');
  }

  ['bulk-prefix', 'bulk-from', 'bulk-to', 'bulk-sort-start'].forEach(id =>
    document.getElementById(id)?.addEventListener('input', bulkUpdatePreview)
  );

  document.getElementById('btn-bulk-new').addEventListener('click', function () {
    document.getElementById('bulk-prefix').value    = 'Meja';
    document.getElementById('bulk-from').value      = '1';
    document.getElementById('bulk-to').value        = '10';
    document.getElementById('bulk-capacity').value  = '0';
    document.getElementById('bulk-sort-start').value = '0';
    document.getElementById('bulk-is-active').checked = true;
    bulkUpdatePreview();
    bulkModal ? bulkModal.show() : (bulkModalEl.style.display = 'block');
  });

  async function runBulkSave() {
    const prefix    = document.getElementById('bulk-prefix').value.trim();
    const from      = parseInt(document.getElementById('bulk-from').value, 10) || 1;
    const to        = parseInt(document.getElementById('bulk-to').value, 10)   || 1;
    const capacity  = parseInt(document.getElementById('bulk-capacity').value, 10)   || 0;
    const sortStart = parseInt(document.getElementById('bulk-sort-start').value, 10) || 0;
    const isActive  = document.getElementById('bulk-is-active').checked ? 1 : 0;

    if (!prefix) throw new Error('Prefix nama meja wajib diisi.');
    if (from < 1 || to < from) throw new Error('Rentang nomor tidak valid (Dari harus ≤ Sampai).');
    if (to - from >= 100) throw new Error('Maksimal 100 meja sekaligus.');

    bulkSaveBtn.disabled = true;
    bulkLabel.textContent = 'Menyimpan...';
    bulkSpinner.classList.remove('d-none');
    try {
      const json = await postJson('<?php echo site_url('pos/self-order/tables/bulk-save'); ?>', {
        prefix, from, to, capacity, sort_order_start: sortStart, is_active: isActive
      });
      bulkModal ? bulkModal.hide() : (bulkModalEl.style.display = 'none');
      await loadRows();
      alert(json.message || (json.data?.count || (to - from + 1)) + ' meja berhasil dibuat.');
    } finally {
      bulkSaveBtn.disabled = false;
      bulkSpinner.classList.add('d-none');
      bulkUpdatePreview();
    }
  }

  bulkSaveBtn.addEventListener('click', () => runBulkSave().catch(e => alert(e.message || 'Gagal bulk tambah meja')));

  loadRows().catch((e) => alert(e.message || 'Gagal memuat meja'));
});
</script>
