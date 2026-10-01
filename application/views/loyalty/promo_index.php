<?php
$filters = is_array($filters ?? null) ? $filters : [];
$config = is_array($promo_config ?? null) ? $promo_config : [];
$fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
$columns = is_array($config['columns'] ?? null) ? $config['columns'] : [];
$primaryFilterOptions = is_array($config['primary_filter_options'] ?? null) ? $config['primary_filter_options'] : [];
$primaryFilterKey = (string)($config['primary_filter_key'] ?? 'mode');
?>
<style>
  .loyalty-shell {
    background: linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    border: 1px solid #f0dfd2;
    border-radius: 26px;
    box-shadow: 0 18px 48px rgba(126, 73, 35, .08);
  }
  .loyalty-micro {
    display: inline-flex;
    align-items: center;
    padding: .35rem .75rem;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #e9d5c7;
    color: #7a5240;
    font-size: .8rem;
    font-weight: 700;
  }
  .loyalty-filter-strip,
  .loyalty-table-card {
    border: 1px solid #f0dfd2;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 14px 36px rgba(126, 73, 35, .06);
  }
  .loyalty-status-tab.active,
  .loyalty-status-tab:hover {
    background: #8f353a;
    color: #fff;
    border-color: #8f353a;
  }
  .loyalty-status-tab {
    border-radius: 999px;
    border: 1px solid #dcb7ab;
    background: #fffaf6;
    color: #81584d;
    font-weight: 700;
  }
  .loyalty-metric {
    border: 1px dashed #e7cbbd;
    border-radius: 18px;
    background: #fffaf6;
    padding: .95rem 1rem;
  }
  .loyalty-ajax-box {
    position: relative;
  }
  .loyalty-ajax-result {
    position: absolute;
    z-index: 20;
    inset: calc(100% + 6px) 0 auto 0;
    background: #fff;
    border: 1px solid #ead7c8;
    border-radius: 16px;
    box-shadow: 0 18px 38px rgba(70, 44, 31, .14);
    max-height: 240px;
    overflow: auto;
    display: none;
  }
  .loyalty-ajax-result.is-open {
    display: block;
  }
  .loyalty-ajax-item {
    padding: .8rem .9rem;
    border-bottom: 1px solid #f4e7de;
    cursor: pointer;
  }
  .loyalty-ajax-item:last-child {
    border-bottom: 0;
  }
  .loyalty-ajax-item:hover {
    background: #fff7f0;
  }
  .loyalty-ajax-item-title {
    font-weight: 700;
    color: #50352b;
  }
  .loyalty-ajax-item-sub {
    font-size: .82rem;
    color: #8a7063;
    margin-top: .15rem;
  }
  .loyalty-ajax-item-layout {
    display: flex;
    align-items: center;
    gap: .75rem;
  }
  .loyalty-ajax-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 12px;
    background: #f9efe8;
    border: 1px solid #ead7c8;
    flex: 0 0 auto;
  }
  .loyalty-ajax-selected {
    display: none;
    margin-top: .55rem;
    padding: .7rem .8rem;
    border: 1px solid #ead7c8;
    border-radius: 14px;
    background: #fffaf6;
  }
  .loyalty-ajax-selected.is-show {
    display: block;
  }
  .loyalty-multi-product-list { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.55rem; }
  .loyalty-multi-product-chip { display:inline-flex; align-items:center; gap:.45rem; border:1px solid #ead7c8; border-radius:999px; background:#fffaf6; padding:.3rem .55rem; font-size:.85rem; }
  .loyalty-save-spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255,255,255,.35);
    border-top-color: #fff;
    border-radius: 50%;
    display: inline-block;
    animation: loyaltySpin .8s linear infinite;
    vertical-align: middle;
  }
  @keyframes loyaltySpin {
    to { transform: rotate(360deg); }
  }
  .loyalty-table thead th {
    color: #7a6055;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    border-bottom-color: #eddcd0;
  }
  .loyalty-table tbody td {
    padding-top: .85rem;
    padding-bottom: .85rem;
    border-bottom-color: #f4e8df;
  }
  .loyalty-voucher-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-weight: 800;
    color: #7f1d1d;
    letter-spacing: .02em;
  }
  .loyalty-owner-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border: 1px solid #ead7c8;
    border-radius: 999px;
    padding: .25rem .6rem;
    background: #fffaf6;
    color: #6f5144;
    font-size: .78rem;
    font-weight: 700;
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($config['title'] ?? 'Promo Loyalty')); ?></h4>
      <p class="fin-page-subtitle mb-0"><?php echo html_escape((string)($config['subtitle'] ?? 'Atur promo loyalty yang akan dipakai POS.')); ?></p>
    </div>
  </div>

  <?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => $promo_tab_active ?? '']); ?>

  <div class="loyalty-filter-strip p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div><h5 class="mb-1"><?php echo html_escape((string)($config['entity_label'] ?? 'Data')); ?></h5></div>
        <button id="btn-new" type="button" class="btn btn-primary"><?php echo html_escape((string)($config['new_label'] ?? 'Tambah Data')); ?></button>
      </div>

    <div class="d-flex gap-2 flex-wrap mb-3" id="status-tabs">
      <button class="btn btn-sm loyalty-status-tab" data-status="ACTIVE">Aktif</button>
      <button class="btn btn-sm loyalty-status-tab" data-status="INACTIVE">Nonaktif</button>
      <button class="btn btn-sm loyalty-status-tab" data-status="ALL">Semua</button>
    </div>

    <form id="filter-form" class="row g-2">
      <div class="col-lg-3">
        <select class="form-select" id="primary_filter">
          <?php foreach ($primaryFilterOptions as $option): ?>
            <option value="<?php echo html_escape((string)($option['value'] ?? '')); ?>"><?php echo html_escape((string)($option['label'] ?? '')); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-7">
        <input id="q" class="form-control" placeholder="Cari nama aturan, nama promo, voucher, atau nama produk terkait">
      </div>
      <div class="col-lg-1">
        <select class="form-select" id="limit">
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
          <option value="200">200</option>
        </select>
      </div>
      <div class="col-lg-1 d-grid">
        <button type="button" id="btn-clear-filter" class="btn btn-outline-danger">Reset</button>
      </div>
    </form>
  </div>

  <div class="loyalty-table-card p-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle loyalty-table mb-0">
        <thead>
          <tr>
            <?php foreach ($columns as $column): ?>
              <th><?php echo html_escape((string)($column['label'] ?? '')); ?></th>
            <?php endforeach; ?>
            <th class="text-center" style="width:132px;">Aksi</th>
          </tr>
        </thead>
        <tbody id="table-body"></tbody>
      </table>
    </div>
    <div id="empty-state" class="text-muted py-3 d-none">Belum ada data yang cocok dengan filter ini.</div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <small id="pagination-info" class="text-muted"></small>
      <div class="d-flex gap-1" id="pagination"></div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="promoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="promoModalLabel"><?php echo html_escape((string)($config['new_label'] ?? 'Tambah Data')); ?></h5>
          <div class="small text-muted">Aturan ini akan dibaca ulang oleh POS saat payment, redeem, atau pemberian benefit sudah aktif penuh.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-save" class="row g-3">
          <input type="hidden" name="id" value="">
          <?php foreach ($fields as $field): ?>
            <?php
              $type = (string)($field['type'] ?? 'text');
              $colClass = (string)($field['col'] ?? (($type === 'textarea') ? 'col-12' : 'col-md-4'));
              $name = (string)($field['name'] ?? '');
              $searchUrl = (string)($field['search_url'] ?? (in_array($type, ['ajax_product', 'ajax_product_multi'], true) ? ($config['product_search_url'] ?? '') : ($type === 'ajax_category_multi' ? ($config['category_search_url'] ?? '') : ($type === 'ajax_member' ? ($config['member_search_url'] ?? '') : ''))));
            ?>
            <div class="<?php echo html_escape($colClass); ?>">
              <?php if ($type === 'checkbox'): ?>
                <div class="form-check form-switch border rounded-4 px-3 py-2 h-100 d-flex align-items-center">
                  <input class="form-check-input" type="checkbox" id="field_<?php echo html_escape($name); ?>" name="<?php echo html_escape($name); ?>">
                  <label class="form-check-label ms-2" for="field_<?php echo html_escape($name); ?>"><?php echo html_escape((string)($field['label'] ?? '')); ?></label>
                </div>
              <?php else: ?>
                <label class="form-label mb-1 small text-muted"><?php echo html_escape((string)($field['label'] ?? '')); ?></label>
                <?php if ($type === 'select'): ?>
                  <select class="form-select" name="<?php echo html_escape($name); ?>" <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                    <?php foreach ((array)($field['options'] ?? []) as $option): ?>
                      <option value="<?php echo html_escape((string)($option['value'] ?? '')); ?>"><?php echo html_escape((string)($option['label'] ?? '')); ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($type === 'textarea'): ?>
                  <textarea class="form-control" rows="3" name="<?php echo html_escape($name); ?>" placeholder="<?php echo html_escape((string)($field['placeholder'] ?? '')); ?>"></textarea>
                <?php elseif (in_array($type, ['ajax_product', 'ajax_member', 'ajax_product_multi', 'ajax_category_multi'], true)): ?>
                  <div class="loyalty-ajax-box" data-ajax-field="<?php echo html_escape($name); ?>" data-search-url="<?php echo html_escape($searchUrl); ?>" data-display-key="<?php echo html_escape((string)($field['display_key'] ?? '')); ?>" data-kind="<?php echo html_escape($type); ?>">
                    <?php if (!in_array($type, ['ajax_product_multi', 'ajax_category_multi'], true)): ?><input type="hidden" name="<?php echo html_escape($name); ?>" value=""><?php endif; ?>
                    <input
                      class="form-control loyalty-ajax-input"
                      type="text"
                      data-display-input="<?php echo html_escape($name); ?>"
                      placeholder="<?php echo html_escape((string)($field['placeholder'] ?? '')); ?>"
                      autocomplete="off"
                    >
                    <div class="loyalty-ajax-result" data-result="<?php echo html_escape($name); ?>"></div>
                    <?php if (in_array($type, ['ajax_product_multi', 'ajax_category_multi'], true)): ?><div class="loyalty-multi-product-list" data-multi-selected="<?php echo html_escape($name); ?>"></div><?php else: ?><div class="loyalty-ajax-selected" data-selected-preview="<?php echo html_escape($name); ?>"></div><?php endif; ?>
                  </div>
                <?php else: ?>
                  <input
                    class="form-control"
                    type="<?php echo html_escape($type); ?>"
                    name="<?php echo html_escape($name); ?>"
                    placeholder="<?php echo html_escape((string)($field['placeholder'] ?? '')); ?>"
                    <?php echo !empty($field['step']) ? 'step="' . html_escape((string)$field['step']) . '"' : ''; ?>
                    <?php echo !empty($field['readonly']) ? 'readonly' : ''; ?>
                    <?php echo !empty($field['required']) ? 'required' : ''; ?>
                  >
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save">
          <span class="btn-label">Simpan</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const config = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const fields = Array.isArray(config.fields) ? config.fields : [];
  const columns = Array.isArray(config.columns) ? config.columns : [];
  const primaryFilterKey = String(config.primary_filter_key || 'mode');
  const actionMode = String(config.action_mode || 'default');
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const state = {
    q: initialFilters.q || '',
    status: initialFilters.status || 'ACTIVE',
    page: parseInt(initialFilters.page || 1, 10),
    limit: parseInt(initialFilters.limit || 25, 10) || 25,
  };
  state[primaryFilterKey] = initialFilters[primaryFilterKey] || 'ALL';

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const paginationInfo = document.getElementById('pagination-info');
  const pagination = document.getElementById('pagination');
  const modalEl = document.getElementById('promoModal');
  const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('form-save');
  const modalTitle = document.getElementById('promoModalLabel');
  const ajaxBoxes = Array.from(form.querySelectorAll('[data-ajax-field]'));
  const saveButton = document.getElementById('btn-save');
  const saveButtonLabel = saveButton ? saveButton.querySelector('.btn-label') : null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }
  function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
  }
  function percent(v, digits = 2) {
    return `${number(v || 0, digits)}%`;
  }
  function number(v, digits = 2) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(v || 0));
  }
  function fmtDate(v) {
    if (!v) return '-';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return escapeHtml(String(v));
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(dt);
  }
  function statusBadge(flag) {
    return Number(flag || 0) === 1
      ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>'
      : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>';
  }
  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('status', state.status);
    p.set(primaryFilterKey, state[primaryFilterKey] || 'ALL');
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 25));
    return p.toString();
  }
  function syncControls() {
    document.getElementById('q').value = state.q;
    document.getElementById('limit').value = String(state.limit || 25);
    document.getElementById('primary_filter').value = state[primaryFilterKey] || 'ALL';
    document.querySelectorAll('.loyalty-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === state.status));
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
  function formatCell(row, column) {
    const key = String(column.key || '');
    const type = String(column.type || 'text');
    const value = row[key];
    if (type === 'status') return statusBadge(value);
    if (type === 'money') return money(value || 0);
    if (type === 'number') return number(value || 0, 2);
    if (type === 'date') return fmtDate(value);
    if (type === 'voucher_campaign_benefit') {
      const voucherType = String(row.voucher_type || '').toUpperCase();
      if (voucherType === 'PERCENT') return percent(value || 0, 2);
      if (voucherType === 'FREE_PRODUCT') return `${number(row.free_qty || 0, 0)} produk`;
      return money(value || 0);
    }
    if (type === 'voucher_issue_benefit') {
      const voucherType = String(row.voucher_type || '').toUpperCase();
      if (voucherType === 'PERCENT') return percent(row.percent_snapshot || 0, 2);
      if (voucherType === 'FREE_PRODUCT') return 'Produk gratis';
      return money(row.amount_snapshot || 0);
    }
    if (key === 'voucher_code') {
      return `<span class="loyalty-voucher-code">${escapeHtml(value == null || value === '' ? '-' : String(value))}</span>`;
    }
    if (key === 'member_name') {
      const raw = value == null || value === '' ? 'Umum / non-member' : String(value);
      const isPublic = raw === 'Umum / non-member';
      return `<span class="loyalty-owner-chip"><i class="ri ${isPublic ? 'ri-user-add-line' : 'ri-user-star-line'}"></i>${escapeHtml(raw)}</span>`;
    }
    return escapeHtml(value == null || value === '' ? '-' : String(value));
  }
  function rowActionButtons(row) {
    const id = Number(row.id || 0);
    if (actionMode === 'voucher_issue') {
      const status = String(row.voucher_status || '').toUpperCase();
      const toggleButton = status === 'OPEN'
        ? `<button type="button" class="btn btn-outline-danger btn-toggle" data-id="${id}">Nonaktifkan</button>`
        : (status === 'VOID' ? `<button type="button" class="btn btn-outline-success btn-toggle" data-id="${id}">Aktifkan</button>` : '');
      return `
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-primary btn-edit" data-row="${encodeURIComponent(JSON.stringify(row))}">Edit</button>
          ${toggleButton}
          <button type="button" class="btn btn-outline-dark btn-delete" data-id="${id}" data-gowes-claim="${Number(row.is_gowes_claim || 0)}" ${status === 'REDEEMED' ? 'disabled title="Voucher yang sudah dipakai tidak bisa dihapus"' : ''}>Hapus</button>
        </div>
      `;
    }
    return `
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-primary btn-edit" data-row="${encodeURIComponent(JSON.stringify(row))}">Edit</button>
        <button type="button" class="btn btn-outline-${Number(row.is_active || 0) === 1 ? 'danger' : 'success'} btn-toggle" data-id="${id}">${Number(row.is_active || 0) === 1 ? 'Nonaktifkan' : 'Aktifkan'}</button>
        <button type="button" class="btn btn-outline-dark btn-delete" data-id="${id}">Hapus</button>
      </div>
    `;
  }
  function renderAjaxSelected(box, row) {
    const preview = box.querySelector('[data-selected-preview]');
    if (!preview) return;
    if (!row) {
      preview.classList.remove('is-show');
      preview.innerHTML = '';
      return;
    }
    const kind = String(box.dataset.kind || 'ajax_product');
    const title = kind === 'ajax_member' ? String(row.member_name || '') : String(row.product_name || '');
    const meta = kind === 'ajax_member'
      ? String(row.mobile_phone || '')
      : [String(row.product_code || ''), String(row.product_division_name || ''), row.selling_price != null ? money(row.selling_price) : ''].filter(Boolean).join(' | ');
    const thumb = row.photo_path ? `<img class="loyalty-ajax-thumb" src="${escapeHtml(row.photo_path)}" alt="${escapeHtml(title)}">` : '';
    preview.innerHTML = `<div class="loyalty-ajax-item-layout">${thumb}<div><div class="loyalty-ajax-item-title">${escapeHtml(title)}</div>${meta ? `<div class="loyalty-ajax-item-sub">${escapeHtml(meta)}</div>` : ''}</div></div>`;
    preview.classList.add('is-show');
  }
  function renderRows(rows) {
    if (!rows.length) {
      tableBody.innerHTML = '';
      emptyState.classList.remove('d-none');
      return;
    }
    emptyState.classList.add('d-none');
    tableBody.innerHTML = rows.map((row) => `
      <tr>
        ${columns.map((column) => `<td>${formatCell(row, column)}</td>`).join('')}
        <td class="text-center">
          ${rowActionButtons(row)}
        </td>
      </tr>
    `).join('');

    tableBody.querySelectorAll('.btn-edit').forEach((btn) => btn.addEventListener('click', () => openEdit(JSON.parse(decodeURIComponent(btn.dataset.row)))));
    tableBody.querySelectorAll('.btn-toggle').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Ubah status data ini?')) return;
      try {
        await postJson(config.toggle_base_url + '/' + Number(btn.dataset.id || 0), {});
        await loadData();
      } catch (e) {
        alert(e.message || 'Gagal mengubah status');
      }
    }));
    tableBody.querySelectorAll('.btn-delete').forEach((btn) => btn.addEventListener('click', async () => {
      if (btn.disabled) return;
      const message = actionMode === 'voucher_issue' && Number(btn.dataset.gowesClaim || 0) === 1
        ? 'Hapus voucher GOWES ini? Kode lama tidak dapat digunakan lagi. Data peserta tetap tersimpan dan peserta dapat klaim ulang untuk mendapatkan kode baru. Tindakan ini tidak bisa dibatalkan.'
        : 'Hapus data ini? Tindakan ini tidak bisa dibatalkan.';
      if (!confirm(message)) return;
      btn.disabled = true;
      try {
        await postJson(config.delete_base_url + '/' + Number(btn.dataset.id || 0), {});
        await loadData();
      } catch (e) {
        alert(e.message || 'Gagal menghapus data');
      } finally {
        btn.disabled = false;
      }
    }));
  }
  function renderPagination(meta) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || state.limit || 25);
    if (!total) {
      paginationInfo.textContent = '0 data';
      pagination.innerHTML = '';
      return;
    }
    const from = ((page - 1) * limit) + 1;
    const to = Math.min(total, from + limit - 1);
    paginationInfo.textContent = `${from}-${to} dari ${total} data`;
    const buttons = [];
    for (let p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p += 1) {
      buttons.push(`<button type="button" class="btn btn-sm ${p === page ? 'btn-primary' : 'btn-outline-secondary'} promo-page-btn" data-page="${p}">${p}</button>`);
    }
    pagination.innerHTML = buttons.join('');
    pagination.querySelectorAll('.promo-page-btn').forEach((btn) => btn.addEventListener('click', () => {
      state.page = Number(btn.dataset.page || 1);
      loadData().catch((e) => alert(e.message || 'Gagal memuat data'));
    }));
  }
  async function loadData() {
    syncControls();
    const json = await getJson(config.data_url + '?' + qsFromState());
    renderRows(json.rows || []);
    renderPagination(json.meta || {});
  }
  function normalizeDateForInput(v) {
    if (!v) return '';
    return String(v).substring(0, 10);
  }
  function normalizeValueForInput(field, value) {
    const type = String(field.type || 'text');
    if (type === 'date') return normalizeDateForInput(value);
    return value == null ? '' : value;
  }
  function closeAllAjaxResults() {
    ajaxBoxes.forEach((box) => {
      const resultEl = box.querySelector('.loyalty-ajax-result');
      if (resultEl) {
        resultEl.classList.remove('is-open');
        resultEl.innerHTML = '';
      }
    });
  }
  function setAjaxField(box, row) {
    const kind = String(box.dataset.kind || 'ajax_product');
    if (kind === 'ajax_product_multi' || kind === 'ajax_category_multi') {
      const list = box.querySelector('[data-multi-selected]');
      const id = String(row.id || '');
      if (!list || !id || list.querySelector(`[data-item-id="${CSS.escape(id)}"]`)) return;
      const chip = document.createElement('span');
      chip.className = 'loyalty-multi-product-chip';
      chip.dataset.itemId = id;
      const itemName = kind === 'ajax_category_multi' ? row.category_name : row.product_name;
      chip.innerHTML = `<span>${escapeHtml(itemName || '')}</span><button type="button" class="btn-close" aria-label="Hapus pilihan"></button><input type="hidden" data-multi-value name="${escapeHtml(box.dataset.ajaxField)}[]" value="${escapeHtml(id)}">`;
      chip.querySelector('button').addEventListener('click', () => chip.remove());
      list.appendChild(chip);
      const display = box.querySelector('.loyalty-ajax-input');
      if (display) display.value = '';
      closeAllAjaxResults();
      return;
    }
    const hidden = box.querySelector('input[type="hidden"]');
    const display = box.querySelector('.loyalty-ajax-input');
    if (hidden) hidden.value = String(row.id || '');
    if (display) {
      if (kind === 'ajax_member') {
        const phone = String(row.mobile_phone || '').trim();
        display.value = String(row.member_name || '') + (phone !== '' ? ` (${phone})` : '');
      } else {
        display.value = String(row.product_name || '');
      }
    }
    renderAjaxSelected(box, row);
    closeAllAjaxResults();
  }
  function clearAjaxField(box) {
    if (['ajax_product_multi', 'ajax_category_multi'].includes(String(box.dataset.kind || ''))) {
      const list = box.querySelector('[data-multi-selected]');
      const display = box.querySelector('.loyalty-ajax-input');
      if (list) list.innerHTML = '';
      if (display) display.value = '';
      return;
    }
    const hidden = box.querySelector('input[type="hidden"]');
    const display = box.querySelector('.loyalty-ajax-input');
    if (hidden) hidden.value = '';
    if (display) display.value = '';
    renderAjaxSelected(box, null);
  }
  function fillForm(row) {
    form.reset();
    ajaxBoxes.forEach(clearAjaxField);
    fields.forEach((field) => {
      const input = form.querySelector(`[name="${field.name}"]`);
      if (field.type === 'ajax_product_multi' || field.type === 'ajax_category_multi') {
        const box = form.querySelector(`[data-ajax-field="${field.name}"]`);
        if (!box) return;
        const isCategory = field.type === 'ajax_category_multi';
        const selected = isCategory
          ? (Array.isArray(row.trigger_categories) ? row.trigger_categories : [])
          : (Array.isArray(row.trigger_products) ? row.trigger_products : []);
        selected.forEach((product) => setAjaxField(box, product));
        if (!isCategory && !selected.length && Number(row.trigger_product_id || 0) > 0) {
          setAjaxField(box, {id: row.trigger_product_id, product_name: row.trigger_product_name || `Produk #${row.trigger_product_id}`});
        }
        return;
      }
      if (field.type === 'ajax_product' || field.type === 'ajax_member') {
        const box = form.querySelector(`[data-ajax-field="${field.name}"]`);
        if (!box) return;
        if (row[field.name]) {
          const display = field.display_key ? row[field.display_key] : '';
          const payload = field.type === 'ajax_member'
            ? {id: row[field.name], member_name: display || row.member_name || '', mobile_phone: row.mobile_phone || ''}
            : {id: row[field.name], product_name: display || ''};
          setAjaxField(box, payload);
        } else {
          clearAjaxField(box);
        }
        return;
      }
      if (!input) return;
      if (field.type === 'checkbox') {
        input.checked = Number(row[field.name] || 0) === 1;
      } else {
        input.value = normalizeValueForInput(field, row[field.name]);
      }
    });
    const idInput = form.querySelector('[name="id"]');
    if (idInput) idInput.value = String(row.id || '');
  }
  function collectPayload() {
    const payload = {};
    fields.forEach((field) => {
      if (field.type === 'ajax_product_multi' || field.type === 'ajax_category_multi') {
        const box = form.querySelector(`[data-ajax-field="${field.name}"]`);
        payload[field.name] = box ? Array.from(box.querySelectorAll('[data-multi-value]')).map((input) => Number(input.value)).filter((id) => id > 0) : [];
        return;
      }
      const input = form.querySelector(`[name="${field.name}"]`);
      if (!input) return;
      payload[field.name] = field.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
    });
    const idInput = form.querySelector('[name="id"]');
    payload.id = idInput ? (idInput.value || '') : '';
    return payload;
  }
  function openNew() {
    form.reset();
    const idInput = form.querySelector('[name="id"]');
    if (idInput) idInput.value = '';
    ajaxBoxes.forEach(clearAjaxField);
    modalTitle.textContent = config.new_label || 'Tambah Data';
    modal.show();
  }
  function openEdit(row) {
    fillForm(row);
    modalTitle.textContent = `Edit ${config.entity_label || 'Data'}: ${row.rule_name || row.campaign_name || row.voucher_code || row.member_name || '-'}`;
    modal.show();
  }
  function bindAjaxBox(box) {
    const input = box.querySelector('.loyalty-ajax-input');
    const hidden = box.querySelector('input[type="hidden"]');
    const resultEl = box.querySelector('.loyalty-ajax-result');
    const searchUrl = String(box.dataset.searchUrl || '');
    const kind = String(box.dataset.kind || 'ajax_product');
    let timer = null;

    const multi = kind === 'ajax_product_multi' || kind === 'ajax_category_multi';
    if (!input || (!hidden && !multi) || !resultEl || searchUrl === '') return;

    input.addEventListener('input', () => {
      if (hidden) hidden.value = '';
      const q = input.value.trim();
      if (timer) window.clearTimeout(timer);
      if (q.length < 2) {
        resultEl.classList.remove('is-open');
        resultEl.innerHTML = '';
        return;
      }
      timer = window.setTimeout(async () => {
        try {
          const json = await getJson(searchUrl + '?q=' + encodeURIComponent(q));
          const rows = Array.isArray(json.rows) ? json.rows : [];
          if (!rows.length) {
            resultEl.innerHTML = '<div class="loyalty-ajax-item"><div class="loyalty-ajax-item-sub">Tidak ada hasil yang cocok.</div></div>';
            resultEl.classList.add('is-open');
            return;
          }
          resultEl.innerHTML = rows.map((row) => {
            const category = kind === 'ajax_category_multi';
            const title = kind === 'ajax_member' ? String(row.member_name || '') : (category ? String(row.category_name || '') : String(row.product_name || ''));
            const sub = kind === 'ajax_member'
              ? String(row.mobile_phone || '')
              : category
                ? [String(row.category_code || ''), String(row.product_division_name || '')].filter(Boolean).join(' | ')
                : [String(row.product_code || ''), String(row.product_division_name || ''), row.selling_price != null ? money(row.selling_price) : ''].filter(Boolean).join(' | ');
            const thumb = row.photo_path ? `<img class="loyalty-ajax-thumb" src="${escapeHtml(row.photo_path)}" alt="${escapeHtml(title)}">` : '';
            return `
              <div class="loyalty-ajax-item" data-row="${encodeURIComponent(JSON.stringify(row))}">
                <div class="loyalty-ajax-item-layout">
                  ${thumb}
                  <div>
                    <div class="loyalty-ajax-item-title">${escapeHtml(title)}</div>
                    ${sub !== '' ? `<div class="loyalty-ajax-item-sub">${escapeHtml(sub)}</div>` : ''}
                  </div>
                </div>
              </div>
            `;
          }).join('');
          resultEl.classList.add('is-open');
          resultEl.querySelectorAll('.loyalty-ajax-item[data-row]').forEach((item) => {
            item.addEventListener('click', () => setAjaxField(box, JSON.parse(decodeURIComponent(item.dataset.row))));
          });
        } catch (e) {
          resultEl.innerHTML = `<div class="loyalty-ajax-item"><div class="loyalty-ajax-item-sub">${escapeHtml(e.message || 'Gagal memuat hasil pencarian.')}</div></div>`;
          resultEl.classList.add('is-open');
        }
      }, 280);
    });

    input.addEventListener('focus', () => {
      if (resultEl.innerHTML.trim() !== '') {
        resultEl.classList.add('is-open');
      }
    });
  }

  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-ajax-field]')) {
      closeAllAjaxResults();
    }
  });

  ajaxBoxes.forEach(bindAjaxBox);
  document.getElementById('btn-new').addEventListener('click', openNew);
  document.getElementById('q').addEventListener('input', () => { state.q = document.getElementById('q').value.trim(); state.page = 1; loadData().catch((e) => alert(e.message)); });
  document.getElementById('limit').addEventListener('change', () => { state.limit = Number(document.getElementById('limit').value || 25); state.page = 1; loadData().catch((e) => alert(e.message)); });
  document.getElementById('primary_filter').addEventListener('change', () => { state[primaryFilterKey] = document.getElementById('primary_filter').value || 'ALL'; state.page = 1; loadData().catch((e) => alert(e.message)); });
  document.querySelectorAll('.loyalty-status-tab').forEach((btn) => btn.addEventListener('click', () => { state.status = btn.dataset.status || 'ACTIVE'; state.page = 1; loadData().catch((e) => alert(e.message)); }));
  document.getElementById('btn-clear-filter').addEventListener('click', () => {
    state.q = '';
    state.status = 'ACTIVE';
    state.page = 1;
    state.limit = 25;
    state[primaryFilterKey] = 'ALL';
    loadData().catch((e) => alert(e.message));
  });
  document.getElementById('btn-save').addEventListener('click', async () => {
    if (saveButton) {
      saveButton.disabled = true;
      if (saveButtonLabel) saveButtonLabel.innerHTML = '<span class="loyalty-save-spinner me-2"></span>Menyimpan...';
    }
    try {
      await postJson(config.save_url, collectPayload());
      modal.hide();
      await loadData();
    } catch (e) {
      alert(e.message || 'Gagal menyimpan data');
    } finally {
      if (saveButton) {
        saveButton.disabled = false;
        if (saveButtonLabel) saveButtonLabel.textContent = 'Simpan';
      }
    }
  });
  syncControls();
  loadData().catch((e) => alert(e.message || 'Gagal memuat data'));
});
</script>
