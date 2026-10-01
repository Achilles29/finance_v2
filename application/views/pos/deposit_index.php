<?php
$filters = is_array($filters ?? null) ? $filters : [];
$paymentMethods = is_array($payment_methods ?? null) ? $payment_methods : [];
?>

<style>
  .deposit-member-search-wrap { position:relative; }
  .deposit-member-dropdown {
    position:absolute;
    left:0;
    right:0;
    top:calc(100% + .45rem);
    z-index:1055;
    background:#fff;
    border:1px solid rgba(201, 183, 168, .72);
    border-radius:18px;
    box-shadow:0 16px 36px rgba(61, 38, 27, .14);
    overflow:hidden;
  }
  .deposit-member-item {
    display:flex;
    justify-content:space-between;
    gap:.8rem;
    padding:.85rem 1rem;
    cursor:pointer;
    border-bottom:1px solid rgba(232,220,210,.9);
  }
  .deposit-member-item:last-child { border-bottom:0; }
  .deposit-member-item:hover { background:#fff7f2; }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h4 class="fin-page-title mb-1">Deposit / DP POS</h4>
      <p class="fin-page-subtitle mb-0">Input DP atas nama customer/member, pantau sisa DP, dan siapkan fondasi sebelum dipakai otomatis saat pelunasan di kasir.</p>
    </div>
    <button type="button" class="btn btn-primary" id="btn-open-deposit-modal">
      <i class="ri-add-line me-1"></i>Tambah DP
    </button>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'deposit']); ?>

  <div class="alert alert-info border-0 shadow-sm mb-3">
    <strong>Konsep saat ini:</strong> DP berdiri sendiri atas nama customer/member. Nanti saat pembayaran final di kasir, sistem akan cek DP terbuka milik customer yang sama lalu menghitung otomatis sisa bayar atau kelebihan dana.
  </div>

  <div class="row g-3 mb-3" id="summary-cards">
    <div class="col-md-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Total DP</div><div class="h4 mb-0" id="sum-count">0</div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Nilai Deposit</div><div class="h4 mb-0" id="sum-net">Rp 0</div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Sudah Terpakai</div><div class="h4 mb-0" id="sum-used">Rp 0</div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Sisa Terbuka</div><div class="h4 mb-0" id="sum-open">Rp 0</div><div class="small text-muted mt-1" id="sum-open-count">0 deposit masih terbuka</div></div></div></div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex gap-2 flex-wrap mb-2">
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="PAID">Paid</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="PENDING">Pending</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="VOID">Void</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ALL">Semua</button>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-sm btn-outline-secondary pos-settlement-tab" data-settlement="ALL">Semua Sisa</button>
        <button class="btn btn-sm btn-outline-secondary pos-settlement-tab" data-settlement="OPEN">Belum Dipakai</button>
        <button class="btn btn-sm btn-outline-secondary pos-settlement-tab" data-settlement="PARTIAL">Terpakai Sebagian</button>
        <button class="btn btn-sm btn-outline-secondary pos-settlement-tab" data-settlement="FULL">Sudah Habis</button>
      </div>

      <form class="row g-2 mb-3" onsubmit="return false;">
        <div class="col-md-10">
          <input id="q" class="form-control" placeholder="Cari nomor payment / nomor order / nama member / no HP">
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
              <th>Deposit No</th>
              <th>Member</th>
              <th>Metode</th>
              <th>Nilai DP</th>
              <th>Terpakai</th>
              <th>Sisa</th>
              <th>Apply</th>
              <th>Status</th>
              <th>Tanggal</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
      <div id="empty-state" class="text-muted py-3 d-none">Belum ada data deposit / DP.</div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <small id="pagination-info" class="text-muted"></small>
        <div class="d-flex gap-1" id="pagination"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="depositModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Input Deposit / DP</h5>
          <div class="small text-muted">Kasir bisa memilih member yang sudah ada atau membuat customer baru otomatis saat simpan DP.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-7">
            <label class="form-label small text-muted mb-1">Cari Member</label>
            <div class="deposit-member-search-wrap">
              <input type="text" class="form-control" id="deposit_member_search" placeholder="Cari nama member atau nomor HP...">
              <div class="deposit-member-dropdown d-none" id="deposit_member_result"></div>
            </div>
            <div class="border rounded-4 px-3 py-2 mt-2 bg-light-subtle" id="deposit_member_selected">
              <div class="text-muted small">Belum ada member dipilih. Isi nama dan nomor HP di bawah untuk auto-create member baru saat simpan.</div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small text-muted mb-1">Nama Customer</label>
                <input type="text" class="form-control" id="deposit_member_name" placeholder="Wajib jika belum pilih member">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted mb-1">No HP</label>
                <input type="text" class="form-control" id="deposit_mobile_phone" placeholder="Opsional, tapi disarankan">
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Nominal DP</label>
            <input type="number" min="1" step="1000" class="form-control" id="deposit_amount" placeholder="Contoh 150000">
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Metode Pembayaran</label>
            <select class="form-select" id="deposit_payment_method">
              <option value="">Pilih metode</option>
              <?php foreach ($paymentMethods as $method): ?>
                <option value="<?php echo (int)($method['id'] ?? 0); ?>"><?php echo html_escape((string)($method['method_name'] ?? '-')); ?> · <?php echo html_escape((string)($method['method_type'] ?? 'OTHER')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Reference No</label>
            <input type="text" class="form-control" id="deposit_reference_no" placeholder="Opsional">
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Catatan</label>
            <textarea class="form-control" id="deposit_notes" rows="2" placeholder="Catatan tambahan untuk audit DP"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-deposit">
          <span class="label">Simpan DP</span>
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
    payment_status: initialFilters.payment_status || 'PAID',
    settlement_status: initialFilters.settlement_status || 'ALL',
    page: parseInt(initialFilters.page || 1, 10),
    limit: parseInt(initialFilters.limit || 50, 10) || 50,
    selectedMember: null,
  };

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const paginationInfo = document.getElementById('pagination-info');
  const pagination = document.getElementById('pagination');
  const depositModalEl = document.getElementById('depositModal');
  const depositModal = depositModalEl ? new bootstrap.Modal(depositModalEl) : null;
  const saveBtn = document.getElementById('btn-save-deposit');
  const memberSearchInput = document.getElementById('deposit_member_search');
  const memberResult = document.getElementById('deposit_member_result');
  const memberSelected = document.getElementById('deposit_member_selected');

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }
  function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
  }
  function statusBadge(status) {
    const s = String(status || '').toUpperCase();
    if (s === 'PAID') return '<span class="badge bg-success-subtle text-success-emphasis">Paid</span>';
    if (s === 'PENDING') return '<span class="badge bg-warning-subtle text-warning-emphasis">Pending</span>';
    if (s === 'VOID') return '<span class="badge bg-danger-subtle text-danger-emphasis">Void</span>';
    return `<span class="badge bg-secondary-subtle text-secondary-emphasis">${escapeHtml(s || '-')}</span>`;
  }
  function settlementBadge(row) {
    const net = Number(row.net_amount || 0);
    const used = Number(row.deposit_applied_amount || 0);
    const rem = Number(row.remaining_amount || 0);
    if (net <= 0) return '<span class="badge bg-secondary-subtle text-secondary-emphasis">-</span>';
    if (used <= 0 && rem > 0) return '<span class="badge bg-info-subtle text-info-emphasis">Open</span>';
    if (rem <= 0) return '<span class="badge bg-success-subtle text-success-emphasis">Full</span>';
    return '<span class="badge bg-warning-subtle text-warning-emphasis">Partial</span>';
  }
  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('payment_status', state.payment_status);
    p.set('settlement_status', state.settlement_status);
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 50));
    return p.toString();
  }
  async function getJson(url, options) {
    const r = await fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, options || {}));
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON. Cek session / permission / error backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data');
    return j;
  }
  function syncControls() {
    document.getElementById('q').value = state.q;
    document.getElementById('limit').value = String(state.limit || 50);
    document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === state.payment_status));
    document.querySelectorAll('.pos-settlement-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.settlement === state.settlement_status));
  }
  function renderSummary(summary) {
    document.getElementById('sum-count').textContent = Number(summary.deposit_count || 0);
    document.getElementById('sum-net').textContent = money(summary.total_deposit_amount || 0);
    document.getElementById('sum-used').textContent = money(summary.total_applied_amount || 0);
    document.getElementById('sum-open').textContent = money(summary.total_remaining_amount || 0);
    document.getElementById('sum-open-count').textContent = `${Number(summary.open_deposit_count || 0)} deposit masih terbuka`;
  }
  function renderRows(rows) {
    if (!rows.length) {
      tableBody.innerHTML = '';
      emptyState.classList.remove('d-none');
      return;
    }
    emptyState.classList.add('d-none');
    tableBody.innerHTML = rows.map((r) => {
      const canVoid = String(r.payment_status || '').toUpperCase() !== 'VOID' && Number(r.deposit_applied_amount || 0) <= 0;
      return `
        <tr>
          <td class="text-nowrap fw-semibold">${escapeHtml(r.payment_no || '-')}</td>
          <td>
            <div class="fw-semibold">${escapeHtml(r.member_name || 'Walk in')}</div>
            <div class="small text-muted">${escapeHtml(r.mobile_phone || '-')}</div>
          </td>
          <td>${escapeHtml(r.payment_method_names || '-')}</td>
          <td class="text-nowrap">${money(r.net_amount || 0)}</td>
          <td class="text-nowrap">${money(r.deposit_applied_amount || 0)}</td>
          <td class="text-nowrap fw-semibold">${money(r.remaining_amount || 0)}</td>
          <td class="text-center">${Number(r.apply_count || 0)}</td>
          <td>${statusBadge(r.payment_status)} ${settlementBadge(r)}</td>
          <td class="text-nowrap">${escapeHtml(r.paid_at || r.created_at || '-')}</td>
          <td class="text-end">
            ${canVoid ? `<button type="button" class="btn btn-sm btn-outline-danger btn-void-deposit" data-id="${Number(r.id || 0)}">Void</button>` : '<span class="text-muted small">-</span>'}
          </td>
        </tr>
      `;
    }).join('');

    tableBody.querySelectorAll('.btn-void-deposit').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Void deposit ini? DP yang sudah terpakai tidak bisa di-void langsung.')) return;
      try {
        btn.disabled = true;
        await getJson('<?php echo site_url('pos/deposits/void'); ?>/' + Number(btn.dataset.id || 0), { method: 'POST' });
        await loadRows();
      } catch (err) {
        alert(err.message || 'Gagal void deposit');
      } finally {
        btn.disabled = false;
      }
    }));
  }
  function renderPagination(meta) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const limit = Number(meta.limit || 50);
    const totalPages = Number(meta.total_pages || 1);
    const start = total ? ((page - 1) * limit) + 1 : 0;
    const end = total ? Math.min(total, start + limit - 1) : 0;
    paginationInfo.textContent = total ? `Menampilkan ${start}-${end} dari ${total} deposit` : 'Belum ada data';
    pagination.innerHTML = Array.from({ length: totalPages }, (_, i) => {
      const n = i + 1;
      return `<button type="button" class="btn btn-sm ${n === page ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${n}">${n}</button>`;
    }).join('');
    pagination.querySelectorAll('[data-page]').forEach((btn) => btn.addEventListener('click', () => {
      state.page = Number(btn.dataset.page || 1);
      loadRows().catch((e) => alert(e.message || 'Gagal memuat data deposit'));
    }));
  }
  async function loadRows() {
    syncControls();
    const json = await getJson('<?php echo site_url('pos/deposits/data'); ?>?' + qsFromState());
    renderRows(json.rows || []);
    renderSummary(json.summary || {});
    renderPagination(json.meta || {});
  }
  function renderSelectedMember() {
    if (!state.selectedMember) {
      memberSelected.innerHTML = '<div class="text-muted small">Belum ada member dipilih. Isi nama dan nomor HP di bawah untuk auto-create member baru saat simpan.</div>';
      return;
    }
    memberSelected.innerHTML = `
      <div class="d-flex justify-content-between gap-3 align-items-start">
        <div>
          <div class="fw-semibold">${escapeHtml(state.selectedMember.member_name || '-')}</div>
          <div class="small text-muted">${escapeHtml(state.selectedMember.mobile_phone || '-')}</div>
          <div class="small text-muted">${escapeHtml(state.selectedMember.member_no || '-')}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="deposit_unselect_member">Lepas</button>
      </div>`;
    const releaseBtn = document.getElementById('deposit_unselect_member');
    if (releaseBtn) {
      releaseBtn.addEventListener('click', () => {
        state.selectedMember = null;
        renderSelectedMember();
      });
    }
  }
  async function loadMemberSearch(q) {
    if (!q || q.trim().length < 2) {
      memberResult.classList.add('d-none');
      memberResult.innerHTML = '';
      return;
    }
    const json = await getJson('<?php echo site_url('pos/deposits/member-search'); ?>?q=' + encodeURIComponent(q.trim()) + '&limit=8');
    const rows = Array.isArray(json.rows) ? json.rows : [];
    if (!rows.length) {
      memberResult.innerHTML = '<div class="p-3 text-muted small">Member tidak ditemukan.</div>';
      memberResult.classList.remove('d-none');
      return;
    }
    memberResult.innerHTML = rows.map((row) => `
      <div class="deposit-member-item deposit-member-option" data-id="${Number(row.id || 0)}" data-name="${escapeHtml(row.member_name || '')}" data-phone="${escapeHtml(row.mobile_phone || '')}" data-no="${escapeHtml(row.member_no || '')}">
        <div>
          <div class="fw-semibold">${escapeHtml(row.member_name || '-')}</div>
          <div class="small text-muted">${escapeHtml(row.mobile_phone || '-')}</div>
        </div>
        <div class="small text-muted text-end">${escapeHtml(row.member_no || '-')}</div>
      </div>
    `).join('');
    memberResult.classList.remove('d-none');
    memberResult.querySelectorAll('.deposit-member-option').forEach((el) => el.addEventListener('click', () => {
      state.selectedMember = {
        id: Number(el.dataset.id || 0),
        member_name: el.dataset.name || '',
        mobile_phone: el.dataset.phone || '',
        member_no: el.dataset.no || '',
      };
      document.getElementById('deposit_member_name').value = state.selectedMember.member_name || '';
      document.getElementById('deposit_mobile_phone').value = state.selectedMember.mobile_phone || '';
      memberSearchInput.value = '';
      memberResult.classList.add('d-none');
      renderSelectedMember();
    }));
  }
  function resetDepositForm() {
    state.selectedMember = null;
    memberSearchInput.value = '';
    memberResult.classList.add('d-none');
    document.getElementById('deposit_member_name').value = '';
    document.getElementById('deposit_mobile_phone').value = '';
    document.getElementById('deposit_amount').value = '';
    document.getElementById('deposit_payment_method').value = '';
    document.getElementById('deposit_reference_no').value = '';
    document.getElementById('deposit_notes').value = '';
    renderSelectedMember();
  }
  async function saveDeposit() {
    const payload = {
      member_id: state.selectedMember ? Number(state.selectedMember.id || 0) : null,
      member_name: document.getElementById('deposit_member_name').value || '',
      mobile_phone: document.getElementById('deposit_mobile_phone').value || '',
      amount: document.getElementById('deposit_amount').value || 0,
      payment_method_id: document.getElementById('deposit_payment_method').value || '',
      reference_no: document.getElementById('deposit_reference_no').value || '',
      notes: document.getElementById('deposit_notes').value || '',
    };
    const spinner = saveBtn.querySelector('.spinner-border');
    const label = saveBtn.querySelector('.label');
    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    label.textContent = 'Menyimpan...';
    try {
      await getJson('<?php echo site_url('pos/deposits/save'); ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });
      depositModal.hide();
      resetDepositForm();
      await loadRows();
    } catch (err) {
      alert(err.message || 'Gagal menyimpan deposit');
    } finally {
      saveBtn.disabled = false;
      spinner.classList.add('d-none');
      label.textContent = 'Simpan DP';
    }
  }

  document.getElementById('btn-open-deposit-modal').addEventListener('click', () => {
    resetDepositForm();
    depositModal.show();
  });
  saveBtn.addEventListener('click', saveDeposit);
  memberSearchInput.addEventListener('input', () => {
    loadMemberSearch(memberSearchInput.value || '').catch((err) => {
      memberResult.innerHTML = `<div class="p-3 text-danger small">${escapeHtml(err.message || 'Gagal memuat member')}</div>`;
      memberResult.classList.remove('d-none');
    });
  });
  document.addEventListener('click', (event) => {
    if (!memberResult.contains(event.target) && event.target !== memberSearchInput) {
      memberResult.classList.add('d-none');
    }
  });

  document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.addEventListener('click', () => {
    state.payment_status = btn.dataset.status || 'PAID';
    state.page = 1;
    loadRows().catch((e) => alert(e.message || 'Gagal memuat data deposit'));
  }));
  document.querySelectorAll('.pos-settlement-tab').forEach((btn) => btn.addEventListener('click', () => {
    state.settlement_status = btn.dataset.settlement || 'ALL';
    state.page = 1;
    loadRows().catch((e) => alert(e.message || 'Gagal memuat data deposit'));
  }));
  document.getElementById('q').addEventListener('input', (e) => {
    state.q = e.target.value || '';
    state.page = 1;
    loadRows().catch((err) => alert(err.message || 'Gagal memuat data deposit'));
  });
  document.getElementById('limit').addEventListener('change', (e) => {
    state.limit = Number(e.target.value || 50);
    state.page = 1;
    loadRows().catch((err) => alert(err.message || 'Gagal memuat data deposit'));
  });
  document.getElementById('btn-clear-filter').addEventListener('click', () => {
    state.q = '';
    state.payment_status = 'PAID';
    state.settlement_status = 'ALL';
    state.page = 1;
    state.limit = 50;
    loadRows().catch((err) => alert(err.message || 'Gagal memuat data deposit'));
  });

  renderSelectedMember();
  loadRows().catch((e) => alert(e.message || 'Gagal memuat data deposit'));
});
</script>
