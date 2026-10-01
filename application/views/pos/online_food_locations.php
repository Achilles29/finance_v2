<?php
$filters = is_array($filters ?? null) ? $filters : [];
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<style>
  .online-location-shell { display:grid; gap:1rem; }
  .online-location-card { border:0; border-radius:18px; box-shadow:0 18px 40px rgba(58,38,30,.08); }
  .online-location-filter {
    border:1px solid rgba(224,209,198,.72);
    border-radius:16px;
    padding:1rem;
    background:#fffdfb;
  }
  .online-location-pill-row { display:flex; flex-wrap:wrap; gap:.55rem; }
  .online-location-pill {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:.45rem .92rem;
    border-radius:999px;
    border:1px solid #d6e0dc;
    background:#eef3f1;
    color:#34534f;
    font-size:.86rem;
    font-weight:700;
    cursor:pointer;
  }
  .online-location-pill.is-active {
    background:#1f5d54;
    border-color:#1f5d54;
    color:#fff;
    box-shadow:0 12px 24px rgba(31,93,84,.16);
  }
  .online-location-section-label {
    font-size:.72rem;
    font-weight:800;
    letter-spacing:.04em;
    text-transform:uppercase;
    color:#8a776d;
    margin-bottom:.5rem;
  }
  .online-location-summary {
    display:grid;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:.75rem;
  }
  .online-location-summary-item {
    border:1px solid rgba(224,209,198,.72);
    border-radius:14px;
    padding:.8rem .9rem;
    background:#fffaf6;
  }
  .online-location-summary-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#8a776d; margin-bottom:.12rem; }
  .online-location-summary-value { font-size:1.25rem; font-weight:900; color:#36292a; }
  .online-location-empty {
    border:1px dashed rgba(189,170,154,.6);
    border-radius:16px;
    padding:1.4rem;
    text-align:center;
    color:#8b7a70;
    background:#fffaf6;
  }
  .online-location-member { font-weight:800; color:#33272a; }
  .online-location-muted { font-size:.78rem; color:#8b7a70; }
  .online-location-address { max-width:380px; white-space:normal; }
  .online-location-coord { font-size:.78rem; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color:#5d514b; }
  .online-location-badge {
    display:inline-flex;
    align-items:center;
    gap:.28rem;
    padding:.25rem .58rem;
    border-radius:999px;
    font-size:.72rem;
    font-weight:800;
    white-space:nowrap;
  }
  .online-location-badge.free { background:#dcfce7; color:#166534; }
  .online-location-badge.normal { background:#f1f5f9; color:#475569; }
  .online-location-actions { display:flex; justify-content:center; gap:.4rem; flex-wrap:wrap; }
  .online-location-member-search { position:relative; }
  .online-location-member-result {
    position:absolute;
    left:0;
    right:0;
    top:calc(100% + 6px);
    z-index:1060;
    border:1px solid rgba(54,42,35,.14);
    border-radius:12px;
    overflow:hidden;
    background:#fff;
    box-shadow:0 14px 28px rgba(58,38,30,.12);
  }
  .online-location-member-option {
    width:100%;
    border:0;
    background:#fff;
    text-align:left;
    padding:.65rem .8rem;
    border-bottom:1px solid rgba(54,42,35,.08);
  }
  .online-location-member-option:last-child { border-bottom:0; }
  .online-location-member-option strong { display:block; font-size:.86rem; color:#2f2925; }
  .online-location-member-option span { display:block; font-size:.74rem; color:#7a6d66; margin-top:.12rem; }
  .online-location-map-tools { display:grid; grid-template-columns:1fr auto; gap:.5rem; }
  .online-location-search-result {
    border:1px solid rgba(54,42,35,.14);
    border-radius:12px;
    overflow:hidden;
    background:#fff;
    margin-top:.5rem;
  }
  .online-location-search-option {
    width:100%;
    border:0;
    background:#fff;
    text-align:left;
    padding:.65rem .8rem;
    border-bottom:1px solid rgba(54,42,35,.08);
  }
  .online-location-search-option:last-child { border-bottom:0; }
  .online-location-search-option strong { display:block; font-size:.84rem; color:#2f2925; }
  .online-location-search-option span { display:block; font-size:.72rem; color:#7a6d66; margin-top:.12rem; }
  .online-location-map {
    height:280px;
    border-radius:14px;
    overflow:hidden;
    border:1px solid rgba(54,42,35,.14);
    background:#eef2ec;
  }
  .online-location-toast-wrap {
    position:fixed;
    top:88px;
    right:24px;
    z-index:2000;
    display:grid;
    gap:.55rem;
    width:min(340px, calc(100vw - 32px));
  }
  .online-location-toast {
    border-radius:16px;
    padding:.8rem .95rem;
    box-shadow:0 16px 30px rgba(61,38,27,.16);
    color:#fff;
    font-size:.86rem;
    font-weight:600;
  }
  .online-location-toast.success { background:#176b3a; }
  .online-location-toast.warning { background:#9a4e0f; }
  @media (max-width: 767.98px) {
    .online-location-summary { grid-template-columns:1fr; }
    .online-location-address { max-width:none; }
    .online-location-map-tools { grid-template-columns:1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'online-food']); ?>
  <?php $this->load->view('pos/_online_food_tabs', ['online_food_tab_active' => 'locations']); ?>

  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Alamat Online Food</h4>
      <p class="fin-page-subtitle mb-0">Alamat ini bisa dibuat dari member saat order, atau dibuat manual oleh admin untuk pelanggan langganan/gratis ongkir.</p>
    </div>
    <button type="button" class="btn btn-primary" id="online_location_new">Tambah Alamat</button>
  </div>

  <div class="online-location-shell">
    <div class="card online-location-card">
      <div class="card-body p-4">
        <div class="online-location-filter mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-lg-7">
              <label class="form-label small text-muted mb-1">Cari alamat</label>
              <input type="text" class="form-control" id="online_location_q" placeholder="Nama member, nomor HP, label alamat, penerima, atau alamat">
            </div>
            <div class="col-md-3 col-lg-2">
              <label class="form-label small text-muted mb-1">Baris</label>
              <select class="form-select" id="online_location_limit">
                <?php foreach ([10, 25, 50, 100, 200] as $rowLimit): ?>
                  <option value="<?php echo $rowLimit; ?>" <?php echo (int)($filters['limit'] ?? 50) === $rowLimit ? 'selected' : ''; ?>><?php echo $rowLimit; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 col-lg-2 d-grid">
              <button type="button" class="btn btn-outline-danger" id="online_location_reset_filter">Reset</button>
            </div>
          </div>

          <div class="mt-3">
            <div class="online-location-section-label">Status gratis ongkir</div>
            <div class="online-location-pill-row">
              <button type="button" class="online-location-pill" data-free-status="ALL">Semua</button>
              <button type="button" class="online-location-pill" data-free-status="FREE">Gratis Ongkir</button>
              <button type="button" class="online-location-pill" data-free-status="NORMAL">Normal</button>
            </div>
          </div>
        </div>

        <div class="online-location-summary mb-3" id="online_location_summary"></div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead>
              <tr>
                <th>Member</th>
                <th>Label</th>
                <th>Penerima</th>
                <th>Alamat</th>
                <th>Koordinat</th>
                <th class="text-center">Gratis Ongkir</th>
                <th>Terakhir Dipakai</th>
                <th class="text-center" style="width:150px;">Aksi</th>
              </tr>
            </thead>
            <tbody id="online_location_body"></tbody>
          </table>
        </div>
        <div id="online_location_empty_state" class="online-location-empty d-none">Belum ada alamat customer pada filter ini. Gunakan tombol Tambah Alamat untuk membuat alamat khusus dari finance.</div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <small id="online_location_pagination_info" class="text-muted"></small>
          <div class="d-flex gap-1" id="online_location_pagination"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="online-location-toast-wrap" id="online_location_toast_wrap"></div>

<div class="modal fade" id="onlineLocationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="online_location_modal_title">Tambah Alamat Online Food</h5>
          <div class="small text-muted" id="online_location_modal_meta">Pilih member lalu isi alamat pengantaran.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="online_location_form" class="row g-3">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="member_id" value="">
          <div class="col-12 online-location-member-search">
            <label class="form-label small text-muted mb-1">Member</label>
            <input type="text" class="form-control" id="online_location_member_search" placeholder="Cari nama / no HP / nomor member">
            <div class="online-location-member-result d-none" id="online_location_member_result"></div>
            <div class="small text-muted mt-1" id="online_location_member_selected">Belum ada member dipilih.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Label alamat</label>
            <input type="text" class="form-control" name="label" maxlength="80" placeholder="Rumah / Kantor / Toko">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Default customer</label>
            <select class="form-select" name="is_default">
              <option value="0">Tidak</option>
              <option value="1">Ya, jadikan default</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Nama penerima</label>
            <input type="text" class="form-control" name="recipient_name" maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Nomor HP penerima</label>
            <input type="text" class="form-control" name="recipient_phone" maxlength="32">
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Alamat</label>
            <textarea class="form-control" name="address" rows="2" maxlength="255" placeholder="Alamat lengkap atau patokan utama"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Catatan/patokan</label>
            <input type="text" class="form-control" name="address_note" maxlength="255" placeholder="Contoh: pagar hitam, depan minimarket">
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Cari titik di map</label>
            <div class="online-location-map-tools">
              <input type="search" class="form-control" id="online_location_map_search" placeholder="Cari alamat, gedung, atau patokan">
              <button type="button" class="btn btn-outline-primary" id="online_location_map_find">Cari</button>
            </div>
            <div class="online-location-search-result d-none" id="online_location_map_result"></div>
          </div>
          <div class="col-12">
            <div class="online-location-map" id="online_location_map"></div>
            <div class="small text-muted mt-1">Geser pin atau klik map untuk menentukan titik pengantaran.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Latitude</label>
            <input type="number" step="0.0000001" class="form-control" name="latitude" placeholder="-6.2000000">
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Longitude</label>
            <input type="number" step="0.0000001" class="form-control" name="longitude" placeholder="106.8166667">
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Akurasi meter</label>
            <input type="number" step="0.01" min="0" class="form-control" name="location_accuracy" placeholder="Opsional">
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="free_delivery_enabled" name="free_delivery_enabled" value="1">
              <label class="form-check-label fw-semibold" for="free_delivery_enabled">Aktifkan gratis ongkir khusus untuk alamat ini</label>
            </div>
            <div class="small text-muted mt-1">Cocok untuk pelanggan langganan, area dekat yang diset manual, atau kompensasi. Ongkir tetap dicatat terpisah dari sales POS.</div>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Alasan / catatan internal</label>
            <input type="text" class="form-control" name="free_delivery_reason" maxlength="120" placeholder="Contoh: Langganan, area dekat, kompensasi">
          </div>
        </form>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger" id="online_location_delete">Hapus</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-primary" id="online_location_save">Simpan</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const state = {
    q: initialFilters.q || '',
    free_status: initialFilters.free_status || 'ALL',
    page: parseInt(initialFilters.page || 1, 10) || 1,
    limit: parseInt(initialFilters.limit || 50, 10) || 50
  };

  const dataUrl = '<?php echo site_url('pos/online-food/locations/data'); ?>';
  const memberSearchUrl = '<?php echo site_url('pos/online-food/locations/member-search'); ?>';
  const saveUrl = '<?php echo site_url('pos/online-food/locations/save'); ?>';
  const deleteUrlBase = '<?php echo site_url('pos/online-food/locations/delete'); ?>';
  const body = document.getElementById('online_location_body');
  const emptyState = document.getElementById('online_location_empty_state');
  const paginationInfo = document.getElementById('online_location_pagination_info');
  const pagination = document.getElementById('online_location_pagination');
  const summary = document.getElementById('online_location_summary');
  const toastWrap = document.getElementById('online_location_toast_wrap');
  const modalEl = document.getElementById('onlineLocationModal');
  const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('online_location_form');
  const modalTitle = document.getElementById('online_location_modal_title');
  const modalMeta = document.getElementById('online_location_modal_meta');
  const memberInput = document.getElementById('online_location_member_search');
  const memberResult = document.getElementById('online_location_member_result');
  const memberSelected = document.getElementById('online_location_member_selected');
  const deleteBtn = document.getElementById('online_location_delete');
  const mapSearchInput = document.getElementById('online_location_map_search');
  const mapFindBtn = document.getElementById('online_location_map_find');
  const mapResult = document.getElementById('online_location_map_result');
  const rowMap = {};
  let selectedMember = null;
  let modalMap = null;
  let modalMarker = null;

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
    });
  }

  function debounce(fn, delay) {
    let timer = null;
    return function () {
      const args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(null, args); }, delay);
    };
  }

  function showToast(message, type) {
    const el = document.createElement('div');
    el.className = 'online-location-toast ' + (type || 'success');
    el.textContent = message;
    toastWrap.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(function () { el.remove(); }, 250);
    }, 2600);
  }

  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q || '');
    p.set('free_status', state.free_status || 'ALL');
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 50));
    return p.toString();
  }

  async function getJson(url) {
    const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response bukan JSON. Cek session atau error PHP.'); }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Gagal memuat data alamat.');
    }
    return json;
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response simpan bukan JSON. Cek session atau error PHP.'); }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Gagal menyimpan data alamat.');
    }
    return json;
  }

  function formatDate(value) {
    if (!value) return '-';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('id-ID', {dateStyle:'medium', timeStyle:'short'});
  }

  function syncControls() {
    document.getElementById('online_location_q').value = state.q || '';
    document.getElementById('online_location_limit').value = String(state.limit || 50);
    document.querySelectorAll('.online-location-pill').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.dataset.freeStatus === state.free_status);
    });
  }

  function badge(row) {
    if (Number(row.free_delivery_enabled || 0) === 1) {
      const reason = row.free_delivery_reason ? '<div class="online-location-muted mt-1">' + escapeHtml(row.free_delivery_reason) + '</div>' : '';
      return '<span class="online-location-badge free">Gratis</span>' + reason;
    }
    return '<span class="online-location-badge normal">Normal</span>';
  }

  function renderSummary(rows, paginationData) {
    let freeCount = 0;
    rows.forEach(function (row) {
      if (Number(row.free_delivery_enabled || 0) === 1) freeCount += 1;
    });
    const totalRows = paginationData ? Number(paginationData.total_rows || 0) : rows.length;
    summary.innerHTML = [
      '<div class="online-location-summary-item"><div class="online-location-summary-label">Total Filter</div><div class="online-location-summary-value">' + totalRows + '</div></div>',
      '<div class="online-location-summary-item"><div class="online-location-summary-label">Gratis di Halaman Ini</div><div class="online-location-summary-value">' + freeCount + '</div></div>',
      '<div class="online-location-summary-item"><div class="online-location-summary-label">Mode Ongkir</div><div class="online-location-summary-value" style="font-size:1rem;">Terpisah POS</div></div>'
    ].join('');
  }

  function renderRows(rows) {
    Object.keys(rowMap).forEach(function (key) { delete rowMap[key]; });
    if (!rows.length) {
      body.innerHTML = '';
      emptyState.classList.remove('d-none');
      return;
    }
    emptyState.classList.add('d-none');
    body.innerHTML = rows.map(function (row) {
      rowMap[String(row.id)] = row;
      const memberName = row.member_name || '-';
      const memberMeta = [row.member_no || '', row.mobile_phone || ''].filter(Boolean).join(' | ');
      const recipient = [row.recipient_name || '', row.recipient_phone || ''].filter(Boolean).join(' | ') || '-';
      const addressNote = row.address_note ? '<div class="online-location-muted mt-1">' + escapeHtml(row.address_note) + '</div>' : '';
      const defaultBadge = Number(row.is_default || 0) === 1 ? '<span class="badge bg-primary-subtle text-primary-emphasis ms-1">Default</span>' : '';
      return '' +
        '<tr>' +
          '<td><div class="online-location-member">' + escapeHtml(memberName) + '</div><div class="online-location-muted">' + escapeHtml(memberMeta || '-') + '</div></td>' +
          '<td><strong>' + escapeHtml(row.label || '-') + '</strong>' + defaultBadge + '</td>' +
          '<td>' + escapeHtml(recipient) + '</td>' +
          '<td class="online-location-address">' + escapeHtml(row.address || '-') + addressNote + '</td>' +
          '<td class="online-location-coord">' + escapeHtml(row.latitude || '-') + '<br>' + escapeHtml(row.longitude || '-') + '</td>' +
          '<td class="text-center">' + badge(row) + '</td>' +
          '<td class="text-nowrap">' + escapeHtml(formatDate(row.last_used_at)) + '</td>' +
          '<td class="text-center"><div class="online-location-actions">' +
            '<button type="button" class="btn btn-sm btn-outline-primary online-location-edit" data-id="' + escapeHtml(row.id) + '">Edit</button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger online-location-remove" data-id="' + escapeHtml(row.id) + '">Hapus</button>' +
          '</div></td>' +
        '</tr>';
    }).join('');
  }

  function renderPagination(meta) {
    const page = Number(meta && meta.page ? meta.page : 1);
    const totalPages = Number(meta && meta.total_pages ? meta.total_pages : 1);
    const totalRows = Number(meta && meta.total_rows ? meta.total_rows : 0);
    const limit = Number(meta && meta.limit ? meta.limit : state.limit);
    const from = totalRows > 0 ? ((page - 1) * limit) + 1 : 0;
    const to = Math.min(page * limit, totalRows);
    paginationInfo.textContent = totalRows ? ('Menampilkan ' + from + '-' + to + ' dari ' + totalRows + ' alamat') : 'Tidak ada data';
    pagination.innerHTML = '';
    const pages = [];
    for (let p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p += 1) pages.push(p);
    if (page > 1) pages.unshift(page - 1);
    if (page < totalPages) pages.push(page + 1);
    Array.from(new Set(pages)).sort(function (a, b) { return a - b; }).forEach(function (p) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm ' + (p === page ? 'btn-primary' : 'btn-outline-secondary');
      btn.textContent = String(p);
      btn.addEventListener('click', function () {
        state.page = p;
        loadRows();
      });
      pagination.appendChild(btn);
    });
  }

  async function loadRows() {
    syncControls();
    try {
      const json = await getJson(dataUrl + '?' + qsFromState());
      const data = json.data || json;
      const rows = data.rows || [];
      const meta = data.pagination || {};
      state.page = Number(meta.page || state.page || 1);
      renderSummary(rows, meta);
      renderRows(rows);
      renderPagination(meta);
    } catch (error) {
      showToast(error.message || 'Gagal memuat data alamat.', 'warning');
      renderSummary([], {total_rows: 0});
      renderRows([]);
      renderPagination({page:1, total_pages:1, total_rows:0, limit:state.limit});
    }
  }

  function setSelectedMember(member) {
    selectedMember = member || null;
    form.elements.member_id.value = selectedMember ? String(selectedMember.id || '') : '';
    if (selectedMember) {
      memberInput.value = selectedMember.member_name || '';
      memberSelected.textContent = [selectedMember.member_no || '', selectedMember.member_name || '', selectedMember.mobile_phone || ''].filter(Boolean).join(' | ');
    } else {
      memberInput.value = '';
      memberSelected.textContent = 'Belum ada member dipilih.';
    }
  }

  function fillForm(row) {
    form.elements.id.value = row.id || '';
    form.elements.member_id.value = row.member_id || '';
    form.elements.label.value = row.label || '';
    form.elements.is_default.value = String(row.is_default || 0);
    form.elements.recipient_name.value = row.recipient_name || '';
    form.elements.recipient_phone.value = row.recipient_phone || '';
    form.elements.address.value = row.address || '';
    form.elements.address_note.value = row.address_note || '';
    form.elements.latitude.value = row.latitude || '';
    form.elements.longitude.value = row.longitude || '';
    form.elements.location_accuracy.value = row.location_accuracy || '';
    form.elements.free_delivery_reason.value = row.free_delivery_reason || '';
    form.elements.free_delivery_enabled.checked = Number(row.free_delivery_enabled || 0) === 1;
  }

  function currentPoint() {
    const lat = Number(form.elements.latitude.value || 0);
    const lng = Number(form.elements.longitude.value || 0);
    if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180 && (lat !== 0 || lng !== 0)) {
      return [lat, lng];
    }
    return [-6.2, 106.8166667];
  }

  function setPoint(lat, lng, address) {
    lat = Number(lat);
    lng = Number(lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    form.elements.latitude.value = lat.toFixed(7);
    form.elements.longitude.value = lng.toFixed(7);
    if (address && !String(form.elements.address.value || '').trim()) {
      form.elements.address.value = String(address).slice(0, 255);
    }
    if (modalMarker) modalMarker.setLatLng([lat, lng]);
    if (modalMap) modalMap.setView([lat, lng], 16);
  }

  function ensureModalMap() {
    if (typeof L === 'undefined') return;
    const point = currentPoint();
    if (!modalMap) {
      modalMap = L.map('online_location_map').setView(point, 15);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(modalMap);
      modalMarker = L.marker(point, {draggable:true}).addTo(modalMap).bindPopup('Titik pengantaran');
      modalMarker.on('dragend', function () {
        const pos = modalMarker.getLatLng();
        setPoint(pos.lat, pos.lng);
      });
      modalMap.on('click', function (event) {
        setPoint(event.latlng.lat, event.latlng.lng);
      });
    } else {
      modalMarker.setLatLng(point);
      modalMap.setView(point, 15);
    }
    setTimeout(function () { modalMap.invalidateSize(); }, 160);
  }

  function openModal(row) {
    const isEdit = !!(row && row.id);
    form.reset();
    setSelectedMember(null);
    if (isEdit) {
      fillForm(row);
      setSelectedMember({
        id: row.member_id || 0,
        member_no: row.member_no || '',
        member_name: row.member_name || '',
        mobile_phone: row.mobile_phone || ''
      });
    } else {
      form.elements.label.value = 'Rumah';
      form.elements.is_default.value = '0';
      form.elements.free_delivery_enabled.checked = true;
      form.elements.free_delivery_reason.value = 'Langganan';
    }
    modalTitle.textContent = isEdit ? 'Edit Alamat Online Food' : 'Tambah Alamat Online Food';
    modalMeta.textContent = isEdit ? 'Ubah alamat, koordinat, atau status gratis ongkir.' : 'Pilih member lalu isi alamat pengantaran.';
    deleteBtn.classList.toggle('d-none', !isEdit);
    memberResult.classList.add('d-none');
    if (modal) modal.show();
    setTimeout(ensureModalMap, 220);
  }

  function payloadFromForm() {
    return {
      id: parseInt(form.elements.id.value || '0', 10) || 0,
      member_id: parseInt(form.elements.member_id.value || '0', 10) || 0,
      label: form.elements.label.value || '',
      is_default: form.elements.is_default.value === '1' ? 1 : 0,
      recipient_name: form.elements.recipient_name.value || '',
      recipient_phone: form.elements.recipient_phone.value || '',
      address: form.elements.address.value || '',
      address_note: form.elements.address_note.value || '',
      latitude: form.elements.latitude.value || '',
      longitude: form.elements.longitude.value || '',
      location_accuracy: form.elements.location_accuracy.value || '',
      free_delivery_enabled: form.elements.free_delivery_enabled.checked ? 1 : 0,
      free_delivery_reason: form.elements.free_delivery_reason.value || ''
    };
  }

  const searchMember = debounce(async function () {
    const q = String(memberInput.value || '').trim();
    if (q.length < 2) {
      memberResult.classList.add('d-none');
      return;
    }
    try {
      const json = await getJson(memberSearchUrl + '?q=' + encodeURIComponent(q) + '&limit=8');
      const rows = (json.data && json.data.rows) ? json.data.rows : (json.rows || []);
      if (!rows.length) {
        memberResult.innerHTML = '<button type="button" class="online-location-member-option" disabled><strong>Tidak ditemukan</strong><span>Coba nama atau nomor HP lain.</span></button>';
        memberResult.classList.remove('d-none');
        return;
      }
      memberResult.innerHTML = rows.map(function (row, idx) {
        return '<button type="button" class="online-location-member-option" data-idx="' + idx + '">' +
          '<strong>' + escapeHtml(row.member_name || '-') + '</strong>' +
          '<span>' + escapeHtml([row.member_no || '', row.mobile_phone || '', row.member_tier || ''].filter(Boolean).join(' | ')) + '</span>' +
        '</button>';
      }).join('');
      memberResult.classList.remove('d-none');
      memberResult.onclick = function (event) {
        const btn = event.target.closest('.online-location-member-option');
        if (!btn || btn.disabled) return;
        const row = rows[Number(btn.getAttribute('data-idx') || 0)];
        if (row) {
          setSelectedMember(row);
          memberResult.classList.add('d-none');
        }
      };
    } catch (error) {
      showToast(error.message || 'Gagal mencari member.', 'warning');
    }
  }, 300);

  function renderMapResults(rows) {
    if (!mapResult) return;
    if (!rows.length) {
      mapResult.classList.add('d-none');
      return;
    }
    mapResult.innerHTML = rows.map(function (row, idx) {
      return '<button type="button" class="online-location-search-option" data-idx="' + idx + '">' +
        '<strong>' + escapeHtml(row.title || 'Lokasi') + '</strong>' +
        '<span>' + escapeHtml(row.address || '') + '</span>' +
      '</button>';
    }).join('');
    mapResult.classList.remove('d-none');
    mapResult.onclick = function (event) {
      const btn = event.target.closest('.online-location-search-option');
      if (!btn) return;
      const row = rows[Number(btn.getAttribute('data-idx') || 0)];
      if (!row) return;
      setPoint(row.lat, row.lng, row.address);
      form.elements.address.value = String(row.address || '').slice(0, 255);
      mapResult.classList.add('d-none');
    };
  }

  async function searchMapAddress() {
    const q = String(mapSearchInput && mapSearchInput.value || '').trim();
    if (q.length < 3) {
      showToast('Ketik minimal 3 karakter alamat.', 'warning');
      return;
    }
    try {
      const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=6&countrycodes=id&q=' + encodeURIComponent(q);
      const response = await fetch(url, {headers:{'Accept':'application/json'}});
      const json = await response.json();
      const rows = (Array.isArray(json) ? json : []).map(function (row) {
        const lat = Number(row && row.lat);
        const lng = Number(row && row.lon);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
        const address = String(row.display_name || q);
        return {
          lat: lat,
          lng: lng,
          title: String(row.name || address.split(',')[0] || 'Lokasi'),
          address: address
        };
      }).filter(Boolean);
      renderMapResults(rows);
      if (!rows.length) showToast('Lokasi tidak ditemukan. Geser pin di map atau isi koordinat manual.', 'warning');
    } catch (error) {
      showToast('Pencarian map belum tersedia. Geser pin di map atau isi koordinat manual.', 'warning');
    }
  }

  document.getElementById('online_location_new').addEventListener('click', function () {
    openModal(null);
  });

  memberInput.addEventListener('input', function () {
    form.elements.member_id.value = '';
    selectedMember = null;
    memberSelected.textContent = 'Belum ada member dipilih.';
    searchMember();
  });
  if (mapFindBtn) mapFindBtn.addEventListener('click', searchMapAddress);
  if (mapSearchInput) {
    mapSearchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchMapAddress();
      }
    });
  }
  form.elements.latitude.addEventListener('change', function () {
    const point = currentPoint();
    if (modalMarker) modalMarker.setLatLng(point);
    if (modalMap) modalMap.setView(point, 16);
  });
  form.elements.longitude.addEventListener('change', function () {
    const point = currentPoint();
    if (modalMarker) modalMarker.setLatLng(point);
    if (modalMap) modalMap.setView(point, 16);
  });

  document.getElementById('online_location_q').addEventListener('input', debounce(function (event) {
    state.q = event.target.value;
    state.page = 1;
    loadRows();
  }, 320));

  document.getElementById('online_location_limit').addEventListener('change', function (event) {
    state.limit = parseInt(event.target.value || '50', 10) || 50;
    state.page = 1;
    loadRows();
  });

  document.querySelectorAll('.online-location-pill').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.free_status = btn.dataset.freeStatus || 'ALL';
      state.page = 1;
      loadRows();
    });
  });

  document.getElementById('online_location_reset_filter').addEventListener('click', function () {
    state.q = '';
    state.free_status = 'ALL';
    state.page = 1;
    state.limit = 50;
    loadRows();
  });

  body.addEventListener('click', function (event) {
    const editBtn = event.target.closest('.online-location-edit');
    const removeBtn = event.target.closest('.online-location-remove');
    if (editBtn) {
      const row = rowMap[String(editBtn.dataset.id || '')];
      if (row) openModal(row);
      return;
    }
    if (removeBtn) {
      const row = rowMap[String(removeBtn.dataset.id || '')];
      if (row) deleteLocation(row);
    }
  });

  async function deleteLocation(row) {
    if (!row || !row.id) return;
    const label = (row.label || 'alamat') + ' - ' + (row.member_name || '');
    if (!confirm('Hapus ' + label + '?')) return;
    try {
      await postJson(deleteUrlBase + '/' + encodeURIComponent(String(row.id)), {});
      showToast('Alamat online food berhasil dihapus.', 'success');
      loadRows();
    } catch (error) {
      showToast(error.message || 'Gagal menghapus alamat.', 'warning');
    }
  }

  document.getElementById('online_location_delete').addEventListener('click', function () {
    const id = parseInt(form.elements.id.value || '0', 10) || 0;
    const row = rowMap[String(id)] || {id: id, label: form.elements.label.value || 'alamat', member_name: memberInput.value || ''};
    if (modal) modal.hide();
    deleteLocation(row);
  });

  document.getElementById('online_location_save').addEventListener('click', async function () {
    const payload = payloadFromForm();
    if (!payload.member_id) {
      showToast('Pilih member dulu.', 'warning');
      return;
    }
    if (!String(payload.label || '').trim()) {
      showToast('Label alamat wajib diisi.', 'warning');
      return;
    }
    if (!String(payload.address || '').trim()) {
      showToast('Alamat wajib diisi.', 'warning');
      return;
    }
    if (!payload.latitude || !payload.longitude) {
      showToast('Latitude dan longitude wajib diisi.', 'warning');
      return;
    }
    try {
      await postJson(saveUrl, payload);
      if (modal) modal.hide();
      showToast('Alamat online food berhasil disimpan.', 'success');
      loadRows();
    } catch (error) {
      showToast(error.message || 'Gagal menyimpan data alamat.', 'warning');
    }
  });

  document.addEventListener('click', function (event) {
    if (!memberResult.contains(event.target) && event.target !== memberInput) {
      memberResult.classList.add('d-none');
    }
  });

  loadRows();
});
</script>
