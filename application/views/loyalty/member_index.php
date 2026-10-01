<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$tiers = is_array($filterOptions['tiers'] ?? null) ? $filterOptions['tiers'] : [];
?>
<style>
  .loyalty-member-shell {
    border: 1px solid #f0dfd2;
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 14px 36px rgba(126, 73, 35, .06);
  }
  .loyalty-member-status-tab.active,
  .loyalty-member-status-tab:hover,
  .loyalty-member-active-tab.active,
  .loyalty-member-active-tab:hover {
    background: #8f353a;
    color: #fff;
    border-color: #8f353a;
  }
  .loyalty-member-status-tab,
  .loyalty-member-active-tab {
    border-radius: 999px;
    border: 1px solid #dcb7ab;
    background: #fffaf6;
    color: #81584d;
    font-weight: 700;
  }
  .loyalty-member-save-spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255,255,255,.35);
    border-top-color: #fff;
    border-radius: 50%;
    display: inline-block;
    animation: loyaltyMemberSpin .8s linear infinite;
    vertical-align: middle;
  }
  #member-feedback:not(.d-none) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
  }
  @keyframes loyaltyMemberSpin {
    to { transform: rotate(360deg); }
  }
  .member-sort {
    border: 0;
    background: transparent;
    padding: 0;
    color: inherit;
    font: inherit;
    font-weight: 800;
    text-transform: inherit;
    display: inline-flex;
    align-items: center;
    gap: .25rem;
  }
  .member-sort i {
    color: #b89a88;
    font-size: .9rem;
  }
  .member-sort.is-active i {
    color: #8f353a;
  }
  .member-name-link {
    color: #7b1d2a;
    font-weight: 800;
    text-decoration: none;
    border-bottom: 1px dashed rgba(123, 29, 42, .45);
  }
  .member-name-link:hover {
    color: #b4233c;
    border-bottom-color: #b4233c;
  }
  .member-stamp-historic {
    display: block;
    margin-top: .08rem;
    font-size: .62rem;
    font-weight: 700;
    color: #8b7a70;
  }
  #histModal .modal-content {
    border-radius: 22px !important;
    overflow: hidden;
    border: 1px solid rgba(143,53,58,.12);
    box-shadow: 0 24px 70px rgba(31, 24, 20, .22);
  }
  #histModal .modal-header {
    background:
      radial-gradient(circle at 94% 18%, rgba(255,255,255,.22), transparent 20%),
      linear-gradient(135deg, #6f1f2c, #193a5a) !important;
    padding: 1rem 1.25rem !important;
  }
  #histModal .hist-tab {
    border: 1px solid rgba(143,53,58,.14) !important;
    background: #fff !important;
    color: #7d675e !important;
    border-radius: 999px !important;
    padding: .42rem .75rem;
  }
  #histModal .hist-tab.active {
    background: #8f353a !important;
    border-color: #8f353a !important;
    color: #fff !important;
    box-shadow: 0 10px 20px rgba(143,53,58,.14);
  }
  #histModal .history-table-wrap {
    max-height: 58vh;
    overflow: auto;
  }
  #histModal thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #a70f22 !important;
    color: #fff !important;
    border-color: #a70f22 !important;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-size: .72rem;
  }
  .member-order-toggle {
    width: 28px;
    height: 28px;
    display: inline-grid;
    place-items: center;
    border-radius: 9px;
  }
  .member-order-detail-row.d-none {
    display: none;
  }
  .member-order-detail {
    background: linear-gradient(180deg, #fffaf6, #fff);
    border: 1px solid #ecd8ca;
    border-radius: 14px;
    padding: .75rem;
    margin: .25rem .75rem .75rem;
  }
  .member-order-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 70px 105px;
    gap: .75rem;
    padding: .55rem 0;
    border-bottom: 1px dashed #ead9ce;
    align-items: start;
  }
  .member-order-item:last-child {
    border-bottom: 0;
  }
  .member-order-extra {
    margin-top: .3rem;
    color: #7f6d63;
    font-size: .76rem;
  }
</style>

<div id="member-feedback" class="alert d-none mb-3" role="status" aria-live="polite"></div>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Member & Loyalitas</h4>
      <p class="fin-page-subtitle mb-0">Kelola member, saldo loyalitas, dan identitas pelanggan yang akan dipakai di POS, voucher, dan program retensi pelanggan.</p>
    </div>
  </div>

  <?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => 'member']); ?>

  <div class="card border-0 shadow-sm loyalty-member-shell">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Database Member</h5>
        <button id="btn-new" type="button" class="btn btn-primary btn-sm">Tambah Member</button>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-2" id="status-tabs">
        <button class="btn btn-sm loyalty-member-active-tab pos-status-tab" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm loyalty-member-active-tab pos-status-tab" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm loyalty-member-active-tab pos-status-tab" data-status="ALL">Semua</button>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-3" id="member-status-tabs">
        <button class="btn btn-sm loyalty-member-status-tab pos-member-status-tab" data-member-status="ALL">Semua Status</button>
        <button class="btn btn-sm loyalty-member-status-tab pos-member-status-tab" data-member-status="ACTIVE">Active</button>
        <button class="btn btn-sm loyalty-member-status-tab pos-member-status-tab" data-member-status="SUSPENDED">Suspended</button>
        <button class="btn btn-sm loyalty-member-status-tab pos-member-status-tab" data-member-status="CLOSED">Closed</button>
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
              <th><button type="button" class="member-sort" data-sort="member_no">No Member <i class="ri ri-expand-up-down-line"></i></button></th>
              <th><button type="button" class="member-sort" data-sort="member_name">Nama <i class="ri ri-expand-up-down-line"></i></button></th>
              <th><button type="button" class="member-sort" data-sort="contact">Kontak <i class="ri ri-expand-up-down-line"></i></button></th>
              <th>Tier</th>
              <th><button type="button" class="member-sort" data-sort="joined_at">Joined <i class="ri ri-expand-up-down-line"></i></button></th>
              <th>Expired</th>
              <th class="text-center" style="width:110px">Status</th>
              <th class="text-end" style="width:90px" title="Saldo Poin"><button type="button" class="member-sort" data-sort="point"><i class="ri ri-star-line"></i> Poin <i class="ri ri-expand-up-down-line"></i></button></th>
              <th class="text-end" style="width:88px" title="Saldo stamp historis dari ledger/cache member"><button type="button" class="member-sort" data-sort="stamp"><i class="ri ri-stamp-line"></i> Stamp <i class="ri ri-expand-up-down-line"></i></button></th>
              <th class="text-center" style="width:65px" title="Voucher Aktif"><i class="ri ri-coupon-3-line"></i></th>
              <th class="text-center" style="width:120px">Aksi</th>
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
        <button type="button" class="btn btn-primary" id="btn-save"><span class="btn-label">Simpan</span></button>
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
    sort_by: initialFilters.sort_by || 'member_name',
    sort_dir: initialFilters.sort_dir || 'asc',
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
  const saveButton = document.getElementById('btn-save');
  const saveButtonLabel = saveButton ? saveButton.querySelector('.btn-label') : null;
  const memberFeedback = document.getElementById('member-feedback');

  function showMemberFeedback(message, type = 'success') {
    if (!memberFeedback) return;
    memberFeedback.className = `alert alert-${type} mb-3`;
    memberFeedback.textContent = String(message || 'Perubahan member berhasil disimpan.');
    window.clearTimeout(showMemberFeedback.timeout);
    showMemberFeedback.timeout = window.setTimeout(() => {
      memberFeedback.className = 'alert d-none mb-3';
      memberFeedback.textContent = '';
    }, 4500);
  }

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

  function memberStatusBadge(memberStatus, isActive) {
    if (!Number(isActive)) return '<span class="badge bg-secondary-subtle text-secondary-emphasis">Nonaktif</span>';
    const v = String(memberStatus || '').toUpperCase();
    if (v === 'SUSPENDED') return '<span class="badge bg-warning-subtle text-warning-emphasis">Ditangguhkan</span>';
    if (v === 'CLOSED')    return '<span class="badge bg-danger-subtle text-danger-emphasis">Ditutup</span>';
    return '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>';
  }

  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('status', state.status);
    p.set('member_status', state.member_status);
    p.set('tier', state.tier);
    p.set('sort_by', state.sort_by);
    p.set('sort_dir', state.sort_dir);
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
    document.querySelectorAll('.member-sort').forEach((btn) => {
      const active = btn.dataset.sort === state.sort_by;
      btn.classList.toggle('is-active', active);
      const icon = btn.querySelector('i:last-child');
      if (icon) {
        icon.className = active
          ? (state.sort_dir === 'desc' ? 'ri ri-sort-desc' : 'ri ri-sort-asc')
          : 'ri ri-expand-up-down-line';
      }
    });
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
    tableBody.innerHTML = rows.map((r) => {
      const poin    = Number(r.point_balance  || r.point_balance_cache  || 0).toLocaleString('id-ID', {maximumFractionDigits:0});
      const rawStamp = Number(r.stamp_balance || 0);
      const stamp   = rawStamp.toLocaleString('id-ID', {maximumFractionDigits:0});
      const hasStampLedger = Number(r.stamp_has_ledger || 0) === 1;
      const voucher = Number(r.open_voucher_count   || 0);
      const isActive = Number(r.is_active || 0) === 1;
      return `<tr>
        <td class="text-nowrap">
          <div>${escapeHtml(r.member_no || '-')}</div>
        </td>
        <td>
          <a href="#"
             class="member-name-link btn-history"
             data-id="${Number(r.id||0)}"
             data-name="${escapeHtml(r.member_name||'')}"
             title="Buka riwayat transaksi, poin, stamp, dan voucher">
            ${escapeHtml(r.member_name || '-')}
          </a>
        </td>
        <td>
          <div>${escapeHtml(r.mobile_phone || '-')}</div>
          <div class="small text-muted mt-1">${escapeHtml(r.email || '-')}</div>
        </td>
        <td>${escapeHtml(r.member_tier || '-')}</td>
        <td class="text-nowrap">${fmtDateTime(r.joined_at)}</td>
        <td class="text-nowrap">${fmtDateTime(r.expired_at)}</td>
        <td class="text-center">${memberStatusBadge(r.member_status, r.is_active)}</td>
        <td class="text-end text-nowrap" style="font-size:.8rem;font-weight:600;color:#7a5800">${poin}</td>
        <td class="text-end text-nowrap" style="font-size:.8rem;font-weight:600;color:#1a4a7a" title="Saldo historis dari pos_stamp_ledger/cache member">
          ${stamp}${hasStampLedger && rawStamp > 0 ? '<span class="member-stamp-historic">ledger</span>' : ''}
        </td>
        <td class="text-center">
          ${voucher > 0 ? `<span class="badge rounded-pill bg-success-subtle text-success-emphasis">${voucher}</span>` : '<span class="text-muted" style="font-size:.75rem">—</span>'}
        </td>
        <td class="text-center">
          <div class="d-inline-flex gap-1">
            <a href="<?php echo site_url('loyalty/members'); ?>/${Number(r.id||0)}"
               class="btn btn-sm btn-outline-secondary" title="Profil &amp; Redeem">
              <i class="ri ri-gift-2-line"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-info btn-history"
              data-id="${Number(r.id||0)}" data-name="${escapeHtml(r.member_name||'')}"
              title="Riwayat Redeem">
              <i class="ri ri-history-line"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
              data-row='${JSON.stringify(r).replace(/'/g, '&#039;')}' title="Edit">
              <i class="ri ri-edit-line"></i>
            </button>
            <button type="button"
              class="btn btn-sm ${isActive ? 'btn-outline-danger' : 'btn-outline-success'} btn-toggle"
              data-id="${Number(r.id||0)}"
              title="${isActive ? 'Nonaktifkan' : 'Aktifkan'}">
              <i class="ri ${isActive ? 'ri-pause-circle-line' : 'ri-play-circle-line'}"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-dark btn-delete"
              data-id="${Number(r.id||0)}" title="Hapus">
              <i class="ri ri-delete-bin-line"></i>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
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
    const json = await getJson('<?php echo site_url('loyalty/members/data'); ?>?' + qsFromState());
    renderRows(json.rows || []);
    renderPagination(json.meta || {});
    history.replaceState(null, '', '<?php echo site_url('loyalty/members'); ?>?' + qsFromState());
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
  document.querySelectorAll('.member-sort').forEach((btn) => btn.addEventListener('click', () => {
    const key = btn.dataset.sort || 'member_name';
    if (state.sort_by === key) {
      state.sort_dir = state.sort_dir === 'asc' ? 'desc' : 'asc';
    } else {
      state.sort_by = key;
      state.sort_dir = key === 'joined_at' || key === 'point' || key === 'stamp' ? 'desc' : 'asc';
    }
    state.page = 1;
    loadRows().catch((err) => alert(err.message));
  }));

  document.getElementById('btn-clear-filter').addEventListener('click', () => {
    state.q = '';
    state.status = 'ACTIVE';
    state.member_status = 'ALL';
    state.tier = '';
    state.sort_by = 'member_name';
    state.sort_dir = 'asc';
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
    const histBtn = e.target.closest('.btn-history');
    if (histBtn) {
      e.preventDefault();
      openHistory(histBtn.dataset.id, histBtn.dataset.name);
      return;
    }

    const editBtn = e.target.closest('.btn-edit');
    if (editBtn) {
      openEdit(JSON.parse(editBtn.dataset.row));
      return;
    }
    const toggleBtn = e.target.closest('.btn-toggle');
    if (toggleBtn) {
      if (!confirm('Ubah status aktif member ini?')) return;
      try {
        await postJson(`<?php echo site_url('loyalty/members/toggle'); ?>/${toggleBtn.dataset.id}`, {});
        await loadRows();
      } catch (err) {
        alert(err.message);
      }
      return;
    }

    const deleteBtn = e.target.closest('.btn-delete');
    if (deleteBtn) {
      if (!confirm('Hapus member ini? Tindakan ini tidak bisa dibatalkan.')) return;
      try {
        await postJson(`<?php echo site_url('loyalty/members/delete'); ?>/${deleteBtn.dataset.id}`, {});
        await loadRows();
      } catch (err) {
        alert(err.message);
      }
    }
  });

  document.getElementById('btn-save').addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(form).entries());
    if (saveButton) {
      saveButton.disabled = true;
      if (saveButtonLabel) saveButtonLabel.innerHTML = '<span class="loyalty-member-save-spinner me-2"></span>Menyimpan...';
    }
    try {
      const saved = await postJson('<?php echo site_url('loyalty/members/save'); ?>', payload);
      modal && modal.hide();
      await loadRows();
      showMemberFeedback(saved.message, 'success');
    } catch (err) {
      showMemberFeedback(err.message, 'danger');
      alert(err.message);
    } finally {
      if (saveButton) {
        saveButton.disabled = false;
        if (saveButtonLabel) saveButtonLabel.textContent = 'Simpan';
      }
    }
  });

  loadRows().catch((err) => alert(err.message));

  // ── History modal ──
  const histModal  = new bootstrap.Modal(document.getElementById('histModal'));
  const histTitle  = document.getElementById('hist-modal-name');
  let histMemberId = 0;
  let activeHTab   = 'orders';
  const baseUrl    = '<?php echo site_url('loyalty/members'); ?>';

  const esc  = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const fmtN = v => Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:0});
  const fmtM = v => 'Rp ' + Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:0});
  const fmtD = v => v ? new Date(v.replace(' ','T')).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '—';
  const fmtQty = v => Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:2});

  const ORDER_STATUS = {
    PAID:'<span class="badge bg-success-subtle text-success-emphasis">Lunas</span>',
    PAID_PARTIAL:'<span class="badge bg-warning-subtle text-warning-emphasis">Lunas Sebagian</span>',
    SERVED:'<span class="badge bg-success-subtle text-success-emphasis">Selesai</span>',
    VOID:'<span class="badge bg-danger-subtle text-danger-emphasis">Void</span>',
    DRAFT:'<span class="badge bg-secondary-subtle text-secondary-emphasis">Draft</span>',
    CONFIRMED:'<span class="badge bg-info-subtle text-info-emphasis">Dikonfirmasi</span>',
  };
  const REDEEM_TYPE = {
    POINT:'<span class="badge bg-warning-subtle text-warning-emphasis">Poin</span>',
    STAMP:'<span class="badge bg-primary-subtle text-primary-emphasis">Stamp</span>',
    VOUCHER:'<span class="badge bg-success-subtle text-success-emphasis">Voucher</span>',
  };
  const VOUCHER_STATUS = {
    OPEN:'<span class="badge bg-success-subtle text-success-emphasis">Siap dipakai</span>',
    REDEEMED:'<span class="badge bg-primary-subtle text-primary-emphasis">Sudah dipakai</span>',
    EXPIRED:'<span class="badge bg-secondary-subtle text-secondary-emphasis">Kadaluarsa</span>',
    VOID:'<span class="badge bg-danger-subtle text-danger-emphasis">Dibatalkan</span>',
  };

  function setPg(pgEl, meta, loader) {
    pgEl.innerHTML = '';
    if ((meta.total_pages||1) <= 1) return;
    if (meta.page > 1) {
      const b = document.createElement('button');
      b.className='btn btn-outline-secondary btn-sm'; b.textContent='‹';
      b.onclick=()=>loader(meta.page-1); pgEl.appendChild(b);
    }
    if (meta.page < (meta.total_pages||1)) {
      const b = document.createElement('button');
      b.className='btn btn-outline-secondary btn-sm'; b.textContent='›';
      b.onclick=()=>loader(meta.page+1); pgEl.appendChild(b);
    }
  }

  function orderDetailHtml(row) {
    const items = Array.isArray(row.items) ? row.items : [];
    if (!items.length) {
      return '<div class="member-order-detail text-muted">Belum ada detail item untuk transaksi ini.</div>';
    }
    return `<div class="member-order-detail">${items.map((item) => {
      const extras = Array.isArray(item.extras) && item.extras.length
        ? `<div class="member-order-extra">${item.extras.map((extra) => `<div>+ ${esc(extra.extra_name || 'Extra')} <span class="text-muted">x ${fmtQty(extra.qty)}</span> <strong>${fmtM(extra.net_amount)}</strong></div>`).join('')}</div>`
        : '';
      const notes = item.notes ? `<div class="small text-muted mt-1">Catatan: ${esc(item.notes)}</div>` : '';
      return `<div class="member-order-item">
        <div>
          <div class="fw-bold">${esc(item.item_name || 'Produk')}</div>
          <div class="small text-muted">Status ${esc(item.line_status || '-')}${notes}</div>
          ${extras}
        </div>
        <div class="text-end">
          <div class="small text-muted">Qty</div>
          <div class="fw-bold">${fmtQty(item.qty)}</div>
        </div>
        <div class="text-end">
          <div class="small text-muted">Net</div>
          <div class="fw-bold">${fmtM(item.net_amount)}</div>
        </div>
      </div>`;
    }).join('')}</div>`;
  }
  async function loadOrders(pg) {
    const tbody = document.getElementById('hist-orders-body');
    const info  = document.getElementById('hist-orders-info');
    const pgEl  = document.getElementById('hist-orders-pg');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</td></tr>';
    try {
      const d    = await getJson(`${baseUrl}/${histMemberId}/orders?page=${pg}`);
      const rows = d.rows || [], meta = d.meta || {};
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada transaksi</td></tr>';
      } else {
        tbody.innerHTML = rows.map(r => {
          const orderId = Number(r.id || 0);
          return `<tr class="member-order-row" data-order-id="${orderId}">
            <td class="ps-3 text-center"><button type="button" class="btn btn-sm btn-outline-secondary member-order-toggle" data-order-toggle="${orderId}" title="Lihat detail transaksi"><i class="ri ri-arrow-down-s-line"></i></button></td>
            <td><span style="font-size:.78rem;font-weight:600">${esc(r.order_no||'')}</span></td>
            <td style="font-size:.78rem">${fmtD(r.ordered_at)}</td>
            <td>${ORDER_STATUS[r.status] || `<span class="badge bg-secondary-subtle text-secondary-emphasis">${esc(r.status)}</span>`}</td>
            <td class="text-end" style="font-size:.78rem">${fmtM(r.subtotal_amount)}</td>
            <td class="text-end" style="font-size:.78rem;color:#c0434d">${r.discount_amount > 0 ? '-'+fmtM(r.discount_amount) : '-'}</td>
            <td class="text-end pe-3" style="font-size:.8rem;font-weight:700">${fmtM(r.grand_total)}</td>
          </tr><tr class="member-order-detail-row d-none" data-order-detail="${orderId}"><td colspan="7">${orderDetailHtml(r)}</td></tr>`;
        }).join('');
      }
      info.textContent = `${rows.length} dari ${(meta.total||0).toLocaleString('id-ID')} transaksi`;
      setPg(pgEl, meta, loadOrders);
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3" style="font-size:.8rem">Gagal: ${esc(e.message)}</td></tr>`;
    }
  }

  document.getElementById('hist-orders-body').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-order-toggle]');
    if (!btn) return;
    const orderId = btn.dataset.orderToggle;
    const detail = document.querySelector(`[data-order-detail="${orderId}"]`);
    if (!detail) return;
    const isOpen = !detail.classList.contains('d-none');
    detail.classList.toggle('d-none', isOpen);
    const icon = btn.querySelector('i');
    if (icon) icon.className = isOpen ? 'ri ri-arrow-down-s-line' : 'ri ri-arrow-up-s-line';
  });
  async function loadRedeem(pg) {
    const tbody = document.getElementById('hist-redeem-body');
    const info  = document.getElementById('hist-redeem-info');
    const pgEl  = document.getElementById('hist-redeem-pg');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat…</td></tr>';
    try {
      const d    = await getJson(`${baseUrl}/${histMemberId}/redeem/history?page=${pg}`);
      const rows = d.rows || [], meta = d.meta || {};
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat redeem</td></tr>';
      } else {
        tbody.innerHTML = rows.map(r => `<tr>
          <td class="ps-3"><span style="font-size:.78rem;font-weight:600">${esc(r.redeem_no||'')}</span></td>
          <td>${REDEEM_TYPE[r.redeem_type]||r.redeem_type}</td>
          <td style="font-size:.78rem">${esc(r.reward_desc||r.reward_type||'—')}</td>
          <td style="font-size:.78rem;color:#7a6055">
            ${r.points_used ? fmtN(r.points_used)+' poin' : ''}
            ${r.stamps_used ? fmtN(r.stamps_used)+' stamp' : ''}
          </td>
          <td style="font-size:.78rem">${fmtD(r.created_at)}</td>
        </tr>`).join('');
      }
      info.textContent = `${rows.length} dari ${(meta.total||0).toLocaleString('id-ID')} transaksi`;
      setPg(pgEl, meta, loadRedeem);
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3" style="font-size:.8rem">Gagal: ${esc(e.message)}</td></tr>`;
    }
  }

  async function loadPoints(pg) {
    const tbody = document.getElementById('hist-points-body');
    const info  = document.getElementById('hist-points-info');
    const pgEl  = document.getElementById('hist-points-pg');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</td></tr>';
    try {
      const d = await getJson(`${baseUrl}/${histMemberId}/points?page=${pg}`);
      const rows = d.rows || [], meta = d.meta || {};
      tbody.innerHTML = rows.length ? rows.map(r => `<tr>
        <td class="ps-3">${esc(r.order_no || '-')}</td>
        <td>${esc(r.ledger_type || '-')}</td>
        <td class="text-end text-success">${fmtN(r.points_in)}</td>
        <td class="text-end text-danger">${fmtN(r.points_out)}</td>
        <td class="text-end fw-bold">${fmtN(r.balance_after)}</td>
        <td>${fmtD(r.created_at)}</td>
      </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada riwayat poin</td></tr>';
      info.textContent = `${rows.length} dari ${(meta.total||0).toLocaleString('id-ID')} baris`;
      setPg(pgEl, meta, loadPoints);
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">Gagal: ${esc(e.message)}</td></tr>`;
    }
  }

  async function loadStamps(pg) {
    const tbody = document.getElementById('hist-stamps-body');
    const info  = document.getElementById('hist-stamps-info');
    const pgEl  = document.getElementById('hist-stamps-pg');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</td></tr>';
    try {
      const d = await getJson(`${baseUrl}/${histMemberId}/stamps?page=${pg}`);
      const rows = d.rows || [], meta = d.meta || {};
      tbody.innerHTML = rows.length ? rows.map(r => `<tr>
        <td class="ps-3">${esc(r.order_no || '-')}</td>
        <td>${esc(r.campaign_name || '-')}</td>
        <td>${esc(r.ledger_type || '-')}</td>
        <td class="text-end text-success">${fmtN(r.stamp_in)}</td>
        <td class="text-end text-danger">${fmtN(r.stamp_out)}</td>
        <td class="text-end fw-bold">${fmtN(r.balance_after)}</td>
        <td>${fmtD(r.created_at)}</td>
      </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada riwayat stamp</td></tr>';
      info.textContent = `${rows.length} dari ${(meta.total||0).toLocaleString('id-ID')} baris`;
      setPg(pgEl, meta, loadStamps);
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">Gagal: ${esc(e.message)}</td></tr>`;
    }
  }

  async function loadVouchers(pg) {
    const tbody = document.getElementById('hist-vouchers-body');
    const info  = document.getElementById('hist-vouchers-info');
    const pgEl  = document.getElementById('hist-vouchers-pg');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</td></tr>';
    try {
      const d = await getJson(`${baseUrl}/${histMemberId}/vouchers?page=${pg}`);
      const rows = d.rows || [], meta = d.meta || {};
      tbody.innerHTML = rows.length ? rows.map(r => `<tr>
        <td class="ps-3 fw-bold">${esc(r.voucher_code || r.voucher_issue_no || '-')}</td>
        <td>${esc(r.campaign_name || '-')}</td>
        <td>${VOUCHER_STATUS[String(r.voucher_status || '').toUpperCase()] || esc(r.voucher_status || '-')}</td>
        <td class="text-end">${String(r.voucher_type || '').toUpperCase() === 'PERCENT' ? fmtN(r.percent_snapshot) + '%' : fmtM(r.amount_snapshot)}</td>
        <td>${fmtD(r.issued_at)}</td>
        <td>${fmtD(r.expired_at)}</td>
      </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada voucher member</td></tr>';
      info.textContent = `${rows.length} dari ${(meta.total||0).toLocaleString('id-ID')} voucher`;
      setPg(pgEl, meta, loadVouchers);
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">Gagal: ${esc(e.message)}</td></tr>`;
    }
  }

  // Tab switching
  document.querySelectorAll('.hist-tab').forEach(btn => {
    btn.addEventListener('click', function() {
      activeHTab = this.dataset.htab;
      document.querySelectorAll('.hist-tab').forEach(b => {
        const on = b.dataset.htab === activeHTab;
        b.style.borderBottomColor = on ? '#1a3a5a' : 'transparent';
        b.style.color = on ? '#1a3a5a' : '#888';
        b.style.fontWeight = on ? '700' : '600';
      });
      document.getElementById('htab-orders').classList.toggle('d-none', activeHTab !== 'orders');
      document.getElementById('htab-redeem').classList.toggle('d-none', activeHTab !== 'redeem');
      document.getElementById('htab-points').classList.toggle('d-none', activeHTab !== 'points');
      document.getElementById('htab-stamps').classList.toggle('d-none', activeHTab !== 'stamps');
      document.getElementById('htab-vouchers').classList.toggle('d-none', activeHTab !== 'vouchers');
      if (activeHTab === 'redeem') loadRedeem(1);
      if (activeHTab === 'points') loadPoints(1);
      if (activeHTab === 'stamps') loadStamps(1);
      if (activeHTab === 'vouchers') loadVouchers(1);
    });
  });

  async function openHistory(id, name) {
    histMemberId = parseInt(id);
    histTitle.textContent = name || 'Member';
    document.getElementById('hist-detail-link').href = `${baseUrl}/${id}`;
    // reset to orders tab
    activeHTab = 'orders';
    document.querySelectorAll('.hist-tab').forEach(b => {
      const on = b.dataset.htab === 'orders';
      b.style.borderBottomColor = on ? '#1a3a5a' : 'transparent';
      b.style.color = on ? '#1a3a5a' : '#888';
      b.style.fontWeight = on ? '700' : '600';
    });
    document.getElementById('htab-orders').classList.remove('d-none');
    document.getElementById('htab-redeem').classList.add('d-none');
    document.getElementById('htab-points').classList.add('d-none');
    document.getElementById('htab-stamps').classList.add('d-none');
    document.getElementById('htab-vouchers').classList.add('d-none');
    histModal.show();
    loadOrders(1);
  }
});
</script>

<!-- ── History Modal ── -->
<div class="modal fade" id="histModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#1a3a5a,#2a5a8a);color:#fff;padding:.85rem 1.2rem">
        <div class="d-flex align-items-center gap-2">
          <i class="ri ri-history-line" style="font-size:1.1rem"></i>
          <div>
            <h6 class="modal-title mb-0 fw-bold">Riwayat</h6>
            <div style="font-size:.75rem;opacity:.8" id="hist-modal-name"></div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1) brightness(2)"></button>
      </div>
      <!-- Tab nav -->
      <div class="px-3 pt-2 pb-0" style="background:#f8f5f2;border-bottom:1px solid #e8ddd6">
        <div class="d-flex gap-1">
          <button class="btn btn-sm hist-tab active" data-htab="orders" style="border-radius:8px 8px 0 0;font-size:.8rem;font-weight:700;border-bottom:2px solid #1a3a5a;color:#1a3a5a;background:none">
            <i class="ri ri-shopping-bag-3-line me-1"></i>Transaksi POS
          </button>
          <button class="btn btn-sm hist-tab" data-htab="redeem" style="border-radius:8px 8px 0 0;font-size:.8rem;font-weight:600;border-bottom:2px solid transparent;color:#888;background:none">
            <i class="ri ri-gift-2-line me-1"></i>Riwayat Redeem
          </button>
          <button class="btn btn-sm hist-tab" data-htab="points" style="border-radius:8px 8px 0 0;font-size:.8rem;font-weight:600;border-bottom:2px solid transparent;color:#888;background:none">
            <i class="ri ri-star-line me-1"></i>Poin
          </button>
          <button class="btn btn-sm hist-tab" data-htab="stamps" style="border-radius:8px 8px 0 0;font-size:.8rem;font-weight:600;border-bottom:2px solid transparent;color:#888;background:none">
            <i class="ri ri-stamp-line me-1"></i>Stamp
          </button>
          <button class="btn btn-sm hist-tab" data-htab="vouchers" style="border-radius:8px 8px 0 0;font-size:.8rem;font-weight:600;border-bottom:2px solid transparent;color:#888;background:none">
            <i class="ri ri-coupon-3-line me-1"></i>Voucher
          </button>
        </div>
      </div>
      <!-- Tab: Transaksi POS -->
      <div id="htab-orders" class="modal-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead style="background:#f8f5f2;position:sticky;top:0">
            <tr>
              <th class="ps-3 text-center" style="width:46px;font-size:.7rem;color:#4a6080;text-transform:uppercase;letter-spacing:.03em"></th>
              <th style="font-size:.7rem;color:#4a6080;text-transform:uppercase;letter-spacing:.03em">No. Order</th>
              <th style="font-size:.7rem;color:#4a6080;text-transform:uppercase;letter-spacing:.03em">Tanggal</th>
              <th style="font-size:.7rem;color:#4a6080;text-transform:uppercase;letter-spacing:.03em">Status</th>
              <th class="text-end" style="font-size:.7rem;color:#4a6080;text-transform:uppercase;letter-spacing:.03em">Subtotal</th>
              <th class="text-end" style="font-size:.7rem;color:#4a6080;text-transform:uppercase;letter-spacing:.03em">Diskon</th>
              <th class="text-end pe-3" style="font-size:.7rem;color:#4a6080;text-transform:uppercase;letter-spacing:.03em">Total</th>
            </tr>
          </thead>
          <tbody id="hist-orders-body">
            <tr><td colspan="6" class="text-center text-muted py-3">Memuat…</td></tr>
          </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="background:#fafafa">
          <small id="hist-orders-info" class="text-muted"></small>
          <div id="hist-orders-pg" class="d-flex gap-1"></div>
        </div>
      </div>
      <!-- Tab: Riwayat Redeem -->
      <div id="htab-redeem" class="modal-body p-0 d-none">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead style="background:#f8f5f2;position:sticky;top:0">
            <tr>
              <th class="ps-3" style="font-size:.7rem;color:#7a6055;text-transform:uppercase;letter-spacing:.03em">No. Redeem</th>
              <th style="font-size:.7rem;color:#7a6055;text-transform:uppercase;letter-spacing:.03em">Tipe</th>
              <th style="font-size:.7rem;color:#7a6055;text-transform:uppercase;letter-spacing:.03em">Reward</th>
              <th style="font-size:.7rem;color:#7a6055;text-transform:uppercase;letter-spacing:.03em">Digunakan</th>
              <th style="font-size:.7rem;color:#7a6055;text-transform:uppercase;letter-spacing:.03em">Tanggal</th>
            </tr>
          </thead>
          <tbody id="hist-redeem-body">
            <tr><td colspan="5" class="text-center text-muted py-3">Memuat…</td></tr>
          </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="background:#fafafa">
          <small id="hist-redeem-info" class="text-muted"></small>
          <div id="hist-redeem-pg" class="d-flex gap-1"></div>
        </div>
      </div>
      <div id="htab-points" class="modal-body p-0 d-none">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead style="background:#f8f5f2;position:sticky;top:0">
            <tr>
              <th class="ps-3">Order</th>
              <th>Tipe</th>
              <th class="text-end">Masuk</th>
              <th class="text-end">Keluar</th>
              <th class="text-end">Saldo</th>
              <th>Tanggal</th>
            </tr>
          </thead>
          <tbody id="hist-points-body"><tr><td colspan="6" class="text-center text-muted py-3">Memuat...</td></tr></tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="background:#fafafa">
          <small id="hist-points-info" class="text-muted"></small>
          <div id="hist-points-pg" class="d-flex gap-1"></div>
        </div>
      </div>
      <div id="htab-stamps" class="modal-body p-0 d-none">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead style="background:#f8f5f2;position:sticky;top:0">
            <tr>
              <th class="ps-3">Order</th>
              <th>Campaign</th>
              <th>Tipe</th>
              <th class="text-end">Masuk</th>
              <th class="text-end">Keluar</th>
              <th class="text-end">Saldo</th>
              <th>Tanggal</th>
            </tr>
          </thead>
          <tbody id="hist-stamps-body"><tr><td colspan="7" class="text-center text-muted py-3">Memuat...</td></tr></tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="background:#fafafa">
          <small id="hist-stamps-info" class="text-muted"></small>
          <div id="hist-stamps-pg" class="d-flex gap-1"></div>
        </div>
      </div>
      <div id="htab-vouchers" class="modal-body p-0 d-none">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead style="background:#f8f5f2;position:sticky;top:0">
            <tr>
              <th class="ps-3">Kode</th>
              <th>Campaign</th>
              <th>Status</th>
              <th class="text-end">Benefit</th>
              <th>Terbit</th>
              <th>Kadaluarsa</th>
            </tr>
          </thead>
          <tbody id="hist-vouchers-body"><tr><td colspan="6" class="text-center text-muted py-3">Memuat...</td></tr></tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="background:#fafafa">
          <small id="hist-vouchers-info" class="text-muted"></small>
          <div id="hist-vouchers-pg" class="d-flex gap-1"></div>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid #e8ddd6;padding:.55rem 1.2rem;justify-content:flex-end">
        <a id="hist-detail-link" href="#" class="btn btn-sm btn-outline-secondary">
          <i class="ri ri-external-link-line me-1"></i>Profil &amp; Redeem Lengkap
        </a>
      </div>
    </div>
  </div>
</div>




