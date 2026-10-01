<?php
$outletFilters = is_array($outlet_filters ?? null) ? $outlet_filters : [];
$terminalFilters = is_array($terminal_filters ?? null) ? $terminal_filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
?>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Outlet + Terminal POS</h4>
      <p class="fin-page-subtitle mb-0">Kelola outlet penjualan dan terminal/perangkat kasir dalam satu workbench yang cepat dibaca.</p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'outlet-terminal']); ?>

  <div class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Outlet POS</h5>
            <button id="btn-new-outlet" type="button" class="btn btn-primary btn-sm">Tambah Outlet</button>
          </div>

          <div class="d-flex gap-2 flex-wrap mb-2">
            <button class="btn btn-sm btn-outline-primary outlet-status-tab" data-status="ACTIVE">Aktif</button>
            <button class="btn btn-sm btn-outline-primary outlet-status-tab" data-status="INACTIVE">Nonaktif</button>
            <button class="btn btn-sm btn-outline-primary outlet-status-tab" data-status="ALL">Semua</button>
          </div>

          <form class="row g-2 mb-3">
            <div class="col-md-8">
              <input id="outlet_q" class="form-control" placeholder="Cari kode / nama / alamat / telepon outlet">
            </div>
            <div class="col-md-2">
              <select id="outlet_limit" class="form-select">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="button" id="btn-clear-outlet" class="btn btn-outline-danger">Clear</button>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Outlet</th>
                  <th>Scope</th>
                  <th>Kontak</th>
                  <th class="text-center">Status</th>
                  <th class="text-center" style="width:132px;">Aksi</th>
                </tr>
              </thead>
              <tbody id="outlet-table-body"></tbody>
            </table>
          </div>
          <div id="outlet-empty-state" class="text-muted py-3 d-none">Outlet tidak ditemukan.</div>
          <div class="d-flex justify-content-between align-items-center mt-3">
            <small id="outlet-pagination-info" class="text-muted"></small>
            <div class="d-flex gap-1" id="outlet-pagination"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Terminal POS</h5>
            <button id="btn-new-terminal" type="button" class="btn btn-primary btn-sm">Tambah Terminal</button>
          </div>

          <div class="d-flex gap-2 flex-wrap mb-2">
            <button class="btn btn-sm btn-outline-primary terminal-status-tab" data-status="ACTIVE">Aktif</button>
            <button class="btn btn-sm btn-outline-primary terminal-status-tab" data-status="INACTIVE">Nonaktif</button>
            <button class="btn btn-sm btn-outline-primary terminal-status-tab" data-status="ALL">Semua</button>
          </div>

          <form class="row g-2 mb-3">
            <div class="col-md-5">
              <input id="terminal_q" class="form-control" placeholder="Cari terminal / device key">
            </div>
            <div class="col-md-4">
              <select id="terminal_outlet_id" class="form-select">
                <option value="0">Semua Outlet</option>
                <?php foreach ($outlets as $outlet): ?>
                  <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-1">
              <select id="terminal_limit" class="form-select">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="button" id="btn-clear-terminal" class="btn btn-outline-danger">Clear</button>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Terminal</th>
                  <th>Outlet</th>
                  <th>OS</th>
                  <th>Device Key</th>
                  <th class="text-center">Status</th>
                  <th class="text-center" style="width:132px;">Aksi</th>
                </tr>
              </thead>
              <tbody id="terminal-table-body"></tbody>
            </table>
          </div>
          <div id="terminal-empty-state" class="text-muted py-3 d-none">Terminal tidak ditemukan.</div>
          <div class="d-flex justify-content-between align-items-center mt-3">
            <small id="terminal-pagination-info" class="text-muted"></small>
            <div class="d-flex gap-1" id="terminal-pagination"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="outletModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="outletModalLabel">Tambah Outlet</h5>
          <div class="small text-muted">Gunakan outlet aktif yang benar supaya routing order, shift, dan terminal tetap rapi.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="outlet-form" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Kode</label>
            <input class="form-control" name="outlet_code" placeholder="Otomatis saat simpan" readonly>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1 small text-muted">Nama Outlet</label>
            <input class="form-control" name="outlet_name" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Scope</label>
            <select class="form-select" name="outlet_scope">
              <option value="REGULAR">REGULAR</option>
              <option value="EVENT">EVENT</option>
              <option value="MIXED">MIXED</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Telepon</label>
            <input class="form-control" name="phone">
          </div>
          <div class="col-12">
            <label class="form-label mb-1 small text-muted">Alamat</label>
            <textarea class="form-control" rows="3" name="address" placeholder="Alamat outlet atau catatan lokasi singkat."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-outlet">Simpan Outlet</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="terminalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="terminalModalLabel">Tambah Terminal</h5>
          <div class="small text-muted">Pasangkan outlet, kode terminal, dan device key dengan rapi supaya session kasir nanti tidak bentrok.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="terminal-form" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Kode</label>
            <input class="form-control" name="terminal_code" placeholder="Otomatis saat simpan" readonly>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1 small text-muted">Nama Terminal</label>
            <input class="form-control" name="terminal_name" required>
          </div>
          <div class="col-md-5">
            <label class="form-label mb-1 small text-muted">Outlet</label>
            <select class="form-select" name="outlet_id" required>
              <option value="">Pilih Outlet</option>
              <?php foreach ($outlets as $outlet): ?>
                <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">OS</label>
            <select class="form-select" name="os_type">
              <option value="WINDOWS">WINDOWS</option>
              <option value="UBUNTU">UBUNTU</option>
              <option value="ANDROID">ANDROID</option>
              <option value="IOS">IOS</option>
              <option value="WEB">WEB</option>
              <option value="OTHER">OTHER</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Device Key</label>
            <input class="form-control" name="device_key" placeholder="Mis. POS-BAR-01">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-terminal">Simpan Terminal</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const outletState = <?php echo json_encode($outletFilters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const terminalState = <?php echo json_encode($terminalFilters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  outletState.page = parseInt(outletState.page || 1, 10);
  outletState.limit = parseInt(outletState.limit || 25, 10) || 25;
  terminalState.page = parseInt(terminalState.page || 1, 10);
  terminalState.limit = parseInt(terminalState.limit || 25, 10) || 25;

  const outletModalEl = document.getElementById('outletModal');
  const outletModal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(outletModalEl) : null;
  const terminalModalEl = document.getElementById('terminalModal');
  const terminalModal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(terminalModalEl) : null;
  const outletForm = document.getElementById('outlet-form');
  const terminalForm = document.getElementById('terminal-form');

  function escapeHtml(v) { return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m])); }
  function statusBadge(flag) { return Number(flag || 0) === 1 ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>'; }

  async function getJson(url) {
    const r = await fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest'}});
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON. Cek session / permission / error backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal');
    return j;
  }

  async function postJson(url, payload) {
    const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify(payload)});
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response save bukan JSON. Kemungkinan ada warning / error PHP di backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal');
    return j;
  }

  function outletQs() {
    const p = new URLSearchParams();
    p.set('outlet_q', outletState.q || '');
    p.set('outlet_status', outletState.status || 'ACTIVE');
    p.set('outlet_page', String(outletState.page || 1));
    p.set('outlet_limit', String(outletState.limit || 25));
    return p.toString();
  }

  function terminalQs() {
    const p = new URLSearchParams();
    p.set('terminal_q', terminalState.q || '');
    p.set('terminal_status', terminalState.status || 'ACTIVE');
    p.set('terminal_outlet_id', String(terminalState.outlet_id || 0));
    p.set('terminal_page', String(terminalState.page || 1));
    p.set('terminal_limit', String(terminalState.limit || 25));
    return p.toString();
  }

  function syncOutletControls() {
    document.getElementById('outlet_q').value = outletState.q || '';
    document.getElementById('outlet_limit').value = String(outletState.limit || 25);
    document.querySelectorAll('.outlet-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === outletState.status));
  }

  function syncTerminalControls() {
    document.getElementById('terminal_q').value = terminalState.q || '';
    document.getElementById('terminal_limit').value = String(terminalState.limit || 25);
    document.getElementById('terminal_outlet_id').value = String(terminalState.outlet_id || 0);
    document.querySelectorAll('.terminal-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === terminalState.status));
  }

  function renderPager(targetId, meta, stateRef, loader, textTargetId, noun) {
    const wrap = document.getElementById(targetId);
    const info = document.getElementById(textTargetId);
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || stateRef.limit || 25);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    info.textContent = total ? `Menampilkan ${start}-${end} dari ${total} ${noun}` : `Belum ada ${noun}`;
    wrap.innerHTML = Array.from({length: totalPages}, (_, idx) => {
      const p = idx + 1;
      return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'} btn-page" data-page="${p}">${p}</button>`;
    }).join('');
    wrap.onclick = (e) => {
      const btn = e.target.closest('.btn-page');
      if (!btn) return;
      stateRef.page = parseInt(btn.dataset.page || '1', 10) || 1;
      loader().catch((err) => alert(err.message));
    };
  }

  async function loadOutlets() {
    syncOutletControls();
    const json = await getJson('<?php echo site_url('pos/outlets/data'); ?>?' + outletQs());
    const body = document.getElementById('outlet-table-body');
    const empty = document.getElementById('outlet-empty-state');
    if (!(json.rows || []).length) {
      body.innerHTML = '';
      empty.classList.remove('d-none');
    } else {
      empty.classList.add('d-none');
      body.innerHTML = json.rows.map((r) => `
        <tr>
          <td class="text-nowrap">${escapeHtml(r.outlet_code || '-')}</td>
          <td>
            <div>${escapeHtml(r.outlet_name || '-')}</div>
            <div class="small text-muted mt-1">${escapeHtml(r.address || '-')}</div>
          </td>
          <td>${escapeHtml(r.outlet_scope || '-')}</td>
          <td>${escapeHtml(r.phone || '-')}</td>
          <td class="text-center">${statusBadge(r.is_active)}</td>
          <td class="text-center"><div class="d-inline-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary btn-outlet-edit" data-row='${JSON.stringify(r).replace(/'/g, '&#039;')}'>Edit</button><button type="button" class="btn btn-sm ${Number(r.is_active || 0) === 1 ? 'btn-outline-danger' : 'btn-outline-success'} btn-outlet-toggle" data-id="${Number(r.id || 0)}">${Number(r.is_active || 0) === 1 ? 'Nonaktifkan' : 'Aktifkan'}</button></div></td>
        </tr>
      `).join('');
    }
    renderPager('outlet-pagination', json.meta || {}, outletState, loadOutlets, 'outlet-pagination-info', 'outlet');
  }

  async function loadTerminals() {
    syncTerminalControls();
    const json = await getJson('<?php echo site_url('pos/terminals/data'); ?>?' + terminalQs());
    const body = document.getElementById('terminal-table-body');
    const empty = document.getElementById('terminal-empty-state');
    if (!(json.rows || []).length) {
      body.innerHTML = '';
      empty.classList.remove('d-none');
    } else {
      empty.classList.add('d-none');
      body.innerHTML = json.rows.map((r) => `
        <tr>
          <td class="text-nowrap">${escapeHtml(r.terminal_code || '-')}</td>
          <td>${escapeHtml(r.terminal_name || '-')}</td>
          <td>${escapeHtml(r.outlet_name || '-')}</td>
          <td>${escapeHtml(r.os_type || '-')}</td>
          <td>${escapeHtml(r.device_key || '-')}</td>
          <td class="text-center">${statusBadge(r.is_active)}</td>
          <td class="text-center"><div class="d-inline-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary btn-terminal-edit" data-row='${JSON.stringify(r).replace(/'/g, '&#039;')}'>Edit</button><button type="button" class="btn btn-sm ${Number(r.is_active || 0) === 1 ? 'btn-outline-danger' : 'btn-outline-success'} btn-terminal-toggle" data-id="${Number(r.id || 0)}">${Number(r.is_active || 0) === 1 ? 'Nonaktifkan' : 'Aktifkan'}</button></div></td>
        </tr>
      `).join('');
    }
    renderPager('terminal-pagination', json.meta || {}, terminalState, loadTerminals, 'terminal-pagination-info', 'terminal');
  }

  function openOutletNew() {
    outletForm.reset();
    outletForm.elements.id.value = '';
    outletForm.elements.outlet_code.value = '';
    outletModal && outletModal.show();
  }

  function openTerminalNew() {
    terminalForm.reset();
    terminalForm.elements.id.value = '';
    terminalForm.elements.terminal_code.value = '';
    terminalModal && terminalModal.show();
  }

  function openOutletEdit(row) {
    outletForm.reset();
    Object.keys(row || {}).forEach((k) => {
      if (outletForm.elements[k]) outletForm.elements[k].value = row[k] == null ? '' : row[k];
    });
    outletModal && outletModal.show();
  }

  function openTerminalEdit(row) {
    terminalForm.reset();
    Object.keys(row || {}).forEach((k) => {
      if (terminalForm.elements[k]) terminalForm.elements[k].value = row[k] == null ? '' : row[k];
    });
    terminalModal && terminalModal.show();
  }

  let outletTimer = null;
  let terminalTimer = null;
  document.getElementById('outlet_q').addEventListener('input', (e) => {
    outletState.q = e.target.value || '';
    outletState.page = 1;
    clearTimeout(outletTimer);
    outletTimer = setTimeout(() => loadOutlets().catch((err) => alert(err.message)), 250);
  });
  document.getElementById('outlet_limit').addEventListener('change', (e) => { outletState.limit = parseInt(e.target.value || '25', 10) || 25; outletState.page = 1; loadOutlets().catch((err) => alert(err.message)); });
  document.querySelectorAll('.outlet-status-tab').forEach((btn) => btn.addEventListener('click', () => { outletState.status = btn.dataset.status; outletState.page = 1; loadOutlets().catch((err) => alert(err.message)); }));
  document.getElementById('btn-clear-outlet').addEventListener('click', () => { outletState.q=''; outletState.status='ACTIVE'; outletState.page=1; outletState.limit=25; loadOutlets().catch((err)=>alert(err.message)); });

  document.getElementById('terminal_q').addEventListener('input', (e) => {
    terminalState.q = e.target.value || '';
    terminalState.page = 1;
    clearTimeout(terminalTimer);
    terminalTimer = setTimeout(() => loadTerminals().catch((err) => alert(err.message)), 250);
  });
  document.getElementById('terminal_limit').addEventListener('change', (e) => { terminalState.limit = parseInt(e.target.value || '25', 10) || 25; terminalState.page = 1; loadTerminals().catch((err) => alert(err.message)); });
  document.getElementById('terminal_outlet_id').addEventListener('change', (e) => { terminalState.outlet_id = parseInt(e.target.value || '0', 10) || 0; terminalState.page = 1; loadTerminals().catch((err) => alert(err.message)); });
  document.querySelectorAll('.terminal-status-tab').forEach((btn) => btn.addEventListener('click', () => { terminalState.status = btn.dataset.status; terminalState.page = 1; loadTerminals().catch((err) => alert(err.message)); }));
  document.getElementById('btn-clear-terminal').addEventListener('click', () => { terminalState.q=''; terminalState.status='ACTIVE'; terminalState.outlet_id=0; terminalState.page=1; terminalState.limit=25; loadTerminals().catch((err)=>alert(err.message)); });

  document.getElementById('btn-new-outlet').addEventListener('click', openOutletNew);
  document.getElementById('btn-new-terminal').addEventListener('click', openTerminalNew);

  document.getElementById('outlet-table-body').addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.btn-outlet-edit');
    if (editBtn) { openOutletEdit(JSON.parse(editBtn.dataset.row)); return; }
    const toggleBtn = e.target.closest('.btn-outlet-toggle');
    if (!toggleBtn) return;
    if (!confirm('Ubah status outlet ini?')) return;
    try { await postJson(`<?php echo site_url('pos/outlets/toggle'); ?>/${toggleBtn.dataset.id}`, {}); await loadOutlets(); } catch (err) { alert(err.message); }
  });

  document.getElementById('terminal-table-body').addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.btn-terminal-edit');
    if (editBtn) { openTerminalEdit(JSON.parse(editBtn.dataset.row)); return; }
    const toggleBtn = e.target.closest('.btn-terminal-toggle');
    if (!toggleBtn) return;
    if (!confirm('Ubah status terminal ini?')) return;
    try { await postJson(`<?php echo site_url('pos/terminals/toggle'); ?>/${toggleBtn.dataset.id}`, {}); await loadTerminals(); } catch (err) { alert(err.message); }
  });

  document.getElementById('btn-save-outlet').addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(outletForm).entries());
    try { await postJson('<?php echo site_url('pos/outlets/save'); ?>', payload); outletModal && outletModal.hide(); await loadOutlets(); } catch (err) { alert(err.message); }
  });
  document.getElementById('btn-save-terminal').addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(terminalForm).entries());
    try { await postJson('<?php echo site_url('pos/terminals/save'); ?>', payload); terminalModal && terminalModal.hide(); await loadTerminals(); } catch (err) { alert(err.message); }
  });

  loadOutlets().catch((err)=>alert(err.message));
  loadTerminals().catch((err)=>alert(err.message));
});
</script>
