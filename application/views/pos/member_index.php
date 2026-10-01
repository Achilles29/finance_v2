<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$tiers = is_array($filterOptions['tiers'] ?? null) ? $filterOptions['tiers'] : [];
?>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Member POS</h4>
      <p class="fin-page-subtitle mb-0">Kelola member yang dipakai kasir untuk pencarian cepat, loyalty, voucher, dan kanal aplikasi member.</p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'member']); ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Database Member</h5>
        <button id="btn-new" type="button" class="btn btn-primary btn-sm">Tambah Member</button>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-2" id="status-tabs">
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ALL">Semua</button>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-3" id="member-status-tabs">
        <button class="btn btn-sm btn-outline-secondary pos-member-status-tab" data-member-status="ALL">Semua Status</button>
        <button class="btn btn-sm btn-outline-secondary pos-member-status-tab" data-member-status="ACTIVE">Active</button>
        <button class="btn btn-sm btn-outline-secondary pos-member-status-tab" data-member-status="SUSPENDED">Suspended</button>
        <button class="btn btn-sm btn-outline-secondary pos-member-status-tab" data-member-status="CLOSED">Closed</button>
      </div>

      <form id="filter-form" class="row g-2 mb-3">
        <div class="col-md-3">
          <select class="form-select" id="tier">
            <option value="">Semua Tier</option>
            <?php foreach ($tiers as $tier): ?>
              <option value="<?php echo html_escape((string)$tier); ?>"><?php echo html_escape((string)$tier); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-7">
          <input id="q" class="form-control" placeholder="Cari nomor member / nama / no HP / email">
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
          <button type="button" id="btn-clear-filter" class="btn btn-outline-danger">Clear Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr>
              <th>No Member</th>
              <th>Nama</th>
              <th>Kontak</th>
              <th>Tier</th>
              <th>Joined</th>
              <th>Expired</th>
              <th class="text-center">Status Member</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width:132px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
      <div id="empty-state" class="text-muted py-3 d-none">Data member tidak ditemukan.</div>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <small id="pagination-info" class="text-muted"></small>
        <div class="d-flex gap-1" id="pagination"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="memberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="memberModalLabel">Tambah Member</h5>
          <div class="small text-muted">Nomor member dibuat otomatis dan langsung jadi identitas loyalitas utama di POS.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-save" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">No Member</label>
            <input class="form-control" name="member_no" placeholder="Otomatis saat simpan" readonly>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1 small text-muted">Nama Member</label>
            <input class="form-control" name="member_name" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">No HP</label>
            <input class="form-control" name="mobile_phone">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Email</label>
            <input class="form-control" name="email">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Tier</label>
            <input class="form-control" name="member_tier" placeholder="Mis. Silver / Gold / VIP">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Tanggal Bergabung</label>
            <input type="datetime-local" class="form-control" name="joined_at">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Expired At</label>
            <input type="datetime-local" class="form-control" name="expired_at">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Status Member</label>
            <select class="form-select" name="member_status">
              <option value="ACTIVE">ACTIVE</option>
              <option value="SUSPENDED">SUSPENDED</option>
              <option value="CLOSED">CLOSED</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Tanggal Lahir</label>
            <input type="date" class="form-control" name="birth_date">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Gender</label>
            <select class="form-select" name="gender">
              <option value="">-</option>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1 small text-muted">Catatan</label>
            <textarea class="form-control" rows="3" name="notes" placeholder="Catatan internal member, preferensi, atau informasi loyalitas tambahan."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const state = {
    q: initialFilters.q || '',
    status: initialFilters.status || 'ACTIVE',
    member_status: initialFilters.member_status || 'ALL',
    tier: initialFilters.tier || '',
    page: parseInt(initialFilters.page || 1, 10),
    limit: parseInt(initialFilters.limit || 50, 10) || 50
  };

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const paginationInfo = document.getElementById('pagination-info');
  const pagination = document.getElementById('pagination');
  const modalEl = document.getElementById('memberModal');
  const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('form-save');
  const modalTitle = document.getElementById('memberModalLabel');

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }

  function fmtDateTime(v) {
    if (!v) return '-';
    const safe = String(v).replace(' ', 'T');
    const dt = new Date(safe);
    if (Number.isNaN(dt.getTime())) return escapeHtml(String(v));
    return new Intl.DateTimeFormat('id-ID', {dateStyle: 'medium', timeStyle: 'short'}).format(dt);
  }

  function statusBadge(status) {
    const v = String(status || '').toUpperCase();
    if (v === 'SUSPENDED') return '<span class="badge bg-warning-subtle text-warning-emphasis">SUSPENDED</span>';
    if (v === 'CLOSED') return '<span class="badge bg-danger-subtle text-danger-emphasis">CLOSED</span>';
    return '<span class="badge bg-success-subtle text-success-emphasis">ACTIVE</span>';
  }

  function activeBadge(flag) {
    return Number(flag || 0) === 1
      ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>'
      : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>';
  }

  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('status', state.status);
    p.set('member_status', state.member_status);
    p.set('tier', state.tier);
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 50));
    return p.toString();
  }

  function syncControls() {
    document.getElementById('q').value = state.q;
    document.getElementById('tier').value = state.tier;
    document.getElementById('limit').value = String(state.limit || 50);
    document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === state.status));
    document.querySelectorAll('.pos-member-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.memberStatus === state.member_status));
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
        <td class="text-nowrap">
          <div>${escapeHtml(r.member_no || '-')}</div>
        </td>
        <td>${escapeHtml(r.member_name || '-')}</td>
        <td>
          <div>${escapeHtml(r.mobile_phone || '-')}</div>
          <div class="small text-muted mt-1">${escapeHtml(r.email || '-')}</div>
        </td>
        <td>${escapeHtml(r.member_tier || '-')}</td>
        <td class="text-nowrap">${fmtDateTime(r.joined_at)}</td>
        <td class="text-nowrap">${fmtDateTime(r.expired_at)}</td>
        <td class="text-center">${statusBadge(r.member_status)}</td>
        <td class="text-center">${activeBadge(r.is_active)}</td>
        <td class="text-center">
          <div class="d-inline-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-row='${JSON.stringify(r).replace(/'/g, '&#039;')}'>Edit</button>
            <button type="button" class="btn btn-sm ${Number(r.is_active || 0) === 1 ? 'btn-outline-danger' : 'btn-outline-success'} btn-toggle" data-id="${Number(r.id || 0)}">
              ${Number(r.is_active || 0) === 1 ? 'Nonaktifkan' : 'Aktifkan'}
            </button>
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
    paginationInfo.textContent = total ? `Menampilkan ${start}-${end} dari ${total} member` : 'Belum ada data member';
    const buttons = [];
    for (let p = 1; p <= totalPages; p++) {
      buttons.push(`<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'} btn-page" data-page="${p}">${p}</button>`);
    }
    pagination.innerHTML = buttons.join('');
  }

  async function loadRows() {
    syncControls();
    const json = await getJson('<?php echo site_url('pos/members/data'); ?>?' + qsFromState());
    renderRows(json.rows || []);
    renderPagination(json.meta || {});
    history.replaceState(null, '', '<?php echo site_url('pos/members'); ?>?' + qsFromState());
  }

  function openNew() {
    form.reset();
    form.elements.id.value = '';
    form.elements.member_no.value = '';
    form.elements.joined_at.value = new Date().toISOString().slice(0, 16);
    form.elements.member_status.value = 'ACTIVE';
    modalTitle.textContent = 'Tambah Member';
    modal && modal.show();
  }

  function openEdit(row) {
    form.reset();
    Object.keys(row || {}).forEach((key) => {
      if (!form.elements[key]) return;
      let value = row[key];
      if ((key === 'joined_at' || key === 'expired_at') && value) {
        value = String(value).slice(0, 16).replace(' ', 'T');
      }
      form.elements[key].value = value == null ? '' : value;
    });
    modalTitle.textContent = `Edit Member: ${row.member_name || ''}`;
    modal && modal.show();
  }

  document.getElementById('btn-new').addEventListener('click', openNew);

  let searchTimer = null;
  document.getElementById('q').addEventListener('input', (e) => {
    state.q = e.target.value || '';
    state.page = 1;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadRows().catch((err) => alert(err.message)), 250);
  });
  document.getElementById('tier').addEventListener('change', (e) => { state.tier = e.target.value || ''; state.page = 1; loadRows().catch((err) => alert(err.message)); });
  document.getElementById('limit').addEventListener('change', (e) => { state.limit = parseInt(e.target.value || '50', 10) || 50; state.page = 1; loadRows().catch((err) => alert(err.message)); });
  document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.addEventListener('click', () => { state.status = btn.dataset.status; state.page = 1; loadRows().catch((err) => alert(err.message)); }));
  document.querySelectorAll('.pos-member-status-tab').forEach((btn) => btn.addEventListener('click', () => { state.member_status = btn.dataset.memberStatus; state.page = 1; loadRows().catch((err) => alert(err.message)); }));

  document.getElementById('btn-clear-filter').addEventListener('click', () => {
    state.q = '';
    state.status = 'ACTIVE';
    state.member_status = 'ALL';
    state.tier = '';
    state.page = 1;
    state.limit = 50;
    loadRows().catch((err) => alert(err.message));
  });

  pagination.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-page');
    if (!btn) return;
    state.page = parseInt(btn.dataset.page || '1', 10) || 1;
    loadRows().catch((err) => alert(err.message));
  });

  tableBody.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.btn-edit');
    if (editBtn) {
      openEdit(JSON.parse(editBtn.dataset.row));
      return;
    }
    const toggleBtn = e.target.closest('.btn-toggle');
    if (!toggleBtn) return;
    if (!confirm('Ubah status aktif member ini?')) return;
    try {
      await postJson(`<?php echo site_url('pos/members/toggle'); ?>/${toggleBtn.dataset.id}`, {});
      await loadRows();
    } catch (err) {
      alert(err.message);
    }
  });

  document.getElementById('btn-save').addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(form).entries());
    try {
      await postJson('<?php echo site_url('pos/members/save'); ?>', payload);
      modal && modal.hide();
      await loadRows();
    } catch (err) {
      alert(err.message);
    }
  });

  loadRows().catch((err) => alert(err.message));
});
</script>
