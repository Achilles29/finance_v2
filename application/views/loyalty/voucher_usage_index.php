<?php
$filters = is_array($filters ?? null) ? $filters : [];
$outletOptions = is_array($outlet_options ?? null) ? $outlet_options : [];
?>
<style>
  .voucher-usage-shell {
    border: 1px solid #f0dfd2;
    border-radius: 24px;
    background: linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    box-shadow: 0 18px 48px rgba(126, 73, 35, .08);
  }
  .voucher-usage-filter,
  .voucher-usage-table-card {
    border: 1px solid #f0dfd2;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(126, 73, 35, .05);
  }
  .voucher-usage-table-wrap {
    max-height: min(62vh, 650px);
    overflow: auto;
  }
  .voucher-usage-table {
    min-width: 1120px;
  }
  .voucher-usage-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #8f353a;
    color: #fff;
    border-color: #8f353a;
    font-size: .77rem;
    letter-spacing: .035em;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .voucher-usage-table tbody td {
    vertical-align: middle;
    border-color: #f2e4da;
  }
  .voucher-usage-code {
    color: #7f1d1d;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .83rem;
    font-weight: 800;
  }
  .voucher-usage-sub {
    color: #92776b;
    font-size: .78rem;
    margin-top: .18rem;
  }
  .voucher-usage-amount {
    color: #7f1d1d;
    font-weight: 800;
    white-space: nowrap;
  }
  .voucher-usage-modal-card {
    border: 1px solid #f0dfd2;
    border-radius: 16px;
    padding: .9rem 1rem;
    background: #fffaf6;
    height: 100%;
  }
  .voucher-usage-modal-label {
    color: #92776b;
    font-size: .73rem;
    font-weight: 800;
    letter-spacing: .03em;
    text-transform: uppercase;
  }
  .voucher-usage-modal-value {
    color: #4c342b;
    font-weight: 750;
    margin-top: .2rem;
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Laporan Pemakaian Voucher</h4>
      <p class="fin-page-subtitle mb-0">Lihat voucher atau promo yang benar-benar dipakai, nilainya pada nota, customer, outlet, dan kasir yang memprosesnya.</p>
    </div>
  </div>

  <?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => $promo_tab_active ?? 'voucher-usage']); ?>

  <div class="voucher-usage-filter p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
      <div>
        <h5 class="mb-1">Riwayat Pemakaian</h5>
        <div class="small text-muted">Nilai "Dipakai" adalah nilai yang masuk ke potongan nota. Jika nominal voucher lebih besar dari tagihan, yang tercatat hanya sebesar tagihannya.</div>
      </div>
      <button type="button" class="btn btn-outline-danger" id="voucher-usage-reset">Reset Filter</button>
    </div>
    <form class="row g-2" id="voucher-usage-filter-form">
      <div class="col-lg-3">
        <label class="form-label small text-muted mb-1">Cari voucher, nota, atau customer</label>
        <input type="text" class="form-control" id="voucher-usage-q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Kode voucher, nota, customer">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small text-muted mb-1">Dari tanggal</label>
        <input type="date" class="form-control" id="voucher-usage-date-from" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small text-muted mb-1">Sampai tanggal</label>
        <input type="date" class="form-control" id="voucher-usage-date-to" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small text-muted mb-1">Jenis voucher</label>
        <select class="form-select" id="voucher-usage-kind">
          <option value="ALL">Semua jenis</option>
          <option value="ISSUE">Voucher diterbitkan</option>
          <option value="CAMPAIGN">Promo voucher umum</option>
        </select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small text-muted mb-1">Status pemakaian</label>
        <select class="form-select" id="voucher-usage-status">
          <option value="ALL">Semua status</option>
          <option value="APPLIED">Dipakai</option>
          <option value="REVERSED">Dibalik</option>
          <option value="VOID">Dibatalkan</option>
        </select>
      </div>
      <div class="col-lg-3">
        <label class="form-label small text-muted mb-1">Outlet</label>
        <select class="form-select" id="voucher-usage-outlet">
          <option value="0">Semua outlet</option>
          <?php foreach ($outletOptions as $outlet): ?>
            <option value="<?php echo (int)($outlet['id'] ?? 0); ?>"><?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small text-muted mb-1">Baris halaman</label>
        <select class="form-select" id="voucher-usage-limit">
          <option value="25">25 baris</option>
          <option value="50">50 baris</option>
          <option value="100">100 baris</option>
        </select>
      </div>
      <div class="col-sm-6 col-lg-2 d-grid align-self-end">
        <button class="btn btn-primary" type="submit" id="voucher-usage-filter-button">Terapkan</button>
      </div>
    </form>
  </div>

  <div class="alert alert-warning d-none" id="voucher-usage-schema-message"></div>

  <div class="voucher-usage-table-card p-3">
    <div class="voucher-usage-table-wrap rounded-3 border">
      <table class="table table-hover voucher-usage-table mb-0">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Voucher</th>
            <th>Nota</th>
            <th>Customer</th>
            <th>Outlet / Kasir</th>
            <th class="text-end">Nilai Voucher</th>
            <th class="text-end">Dipakai</th>
            <th>Status</th>
            <th class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody id="voucher-usage-table-body"></tbody>
      </table>
    </div>
    <div class="text-muted py-3 d-none" id="voucher-usage-empty">Belum ada pemakaian voucher yang sesuai filter ini.</div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
      <small class="text-muted" id="voucher-usage-pagination-info"></small>
      <div class="d-flex gap-1" id="voucher-usage-pagination"></div>
    </div>
  </div>
</div>

<div class="modal fade finance-ui-modal" id="voucherUsageDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">Detail Pemakaian Voucher</h5>
          <div class="small text-muted">Rincian voucher, nota, customer, dan pembayaran pada transaksi ini.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="voucher-usage-detail-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const config = {
    dataUrl: <?php echo json_encode(site_url('loyalty/voucher-usages/data')); ?>,
    detailBaseUrl: <?php echo json_encode(site_url('loyalty/voucher-usages/detail')); ?>,
    initial: <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>
  };
  const state = {
    q: String(config.initial.q || ''),
    date_from: String(config.initial.date_from || ''),
    date_to: String(config.initial.date_to || ''),
    voucher_kind: String(config.initial.voucher_kind || 'ALL').toUpperCase(),
    usage_status: String(config.initial.usage_status || 'ALL').toUpperCase(),
    outlet_id: Number(config.initial.outlet_id || 0),
    page: Math.max(1, Number(config.initial.page || 1)),
    limit: Math.max(1, Number(config.initial.limit || 25))
  };
  const defaultDateFrom = state.date_from;
  const defaultDateTo = state.date_to;
  const body = document.getElementById('voucher-usage-table-body');
  const empty = document.getElementById('voucher-usage-empty');
  const pagination = document.getElementById('voucher-usage-pagination');
  const paginationInfo = document.getElementById('voucher-usage-pagination-info');
  const schemaMessage = document.getElementById('voucher-usage-schema-message');
  const detailBody = document.getElementById('voucher-usage-detail-body');
  const detailModalEl = document.getElementById('voucherUsageDetailModal');
  const detailModal = window.bootstrap && window.bootstrap.Modal ? new window.bootstrap.Modal(detailModalEl) : null;

  const controls = {
    q: document.getElementById('voucher-usage-q'),
    dateFrom: document.getElementById('voucher-usage-date-from'),
    dateTo: document.getElementById('voucher-usage-date-to'),
    kind: document.getElementById('voucher-usage-kind'),
    status: document.getElementById('voucher-usage-status'),
    outlet: document.getElementById('voucher-usage-outlet'),
    limit: document.getElementById('voucher-usage-limit'),
    button: document.getElementById('voucher-usage-filter-button')
  };

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
      return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[ch];
    });
  }
  function money(value) {
    return new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2}).format(Number(value || 0));
  }
  function number(value, digits) {
    return new Intl.NumberFormat('id-ID', {minimumFractionDigits: digits || 0, maximumFractionDigits: digits || 0}).format(Number(value || 0));
  }
  function dateTime(value) {
    if (!value) return '-';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return escapeHtml(value);
    return new Intl.DateTimeFormat('id-ID', {dateStyle: 'medium', timeStyle: 'short'}).format(date);
  }
  function kindLabel(kind) {
    return String(kind || '').toUpperCase() === 'CAMPAIGN' ? 'Promo voucher umum' : 'Voucher diterbitkan';
  }
  function usageBadge(status) {
    const normalized = String(status || 'APPLIED').toUpperCase();
    const map = {
      APPLIED: ['Dipakai', 'bg-success-subtle text-success-emphasis'],
      REVERSED: ['Dibalik', 'bg-warning-subtle text-warning-emphasis'],
      VOID: ['Dibatalkan', 'bg-danger-subtle text-danger-emphasis']
    };
    const item = map[normalized] || [normalized, 'bg-secondary-subtle text-secondary-emphasis'];
    return `<span class="badge ${item[1]}">${item[0]}</span>`;
  }
  function orderBadge(status) {
    const normalized = String(status || '').toUpperCase();
    if (!normalized) return '';
    const isProblem = normalized === 'VOID' || normalized === 'REFUND_FULL';
    return `<span class="badge ${isProblem ? 'bg-danger-subtle text-danger-emphasis' : 'bg-light text-dark border'} ms-1">${escapeHtml(normalized)}</span>`;
  }
  function voucherFace(row) {
    const amount = Number(row.face_value_amount || 0);
    const percent = Number(row.face_value_percent || 0);
    if (amount > 0) return money(amount);
    if (percent > 0) return number(percent, 2) + '%';
    return '-';
  }
  function syncControls() {
    controls.q.value = state.q;
    controls.dateFrom.value = state.date_from;
    controls.dateTo.value = state.date_to;
    controls.kind.value = state.voucher_kind;
    controls.status.value = state.usage_status;
    controls.outlet.value = String(state.outlet_id || 0);
    controls.limit.value = String(state.limit || 25);
  }
  function queryString() {
    const p = new URLSearchParams();
    Object.keys(state).forEach(function (key) { p.set(key, String(state[key] ?? '')); });
    return p.toString();
  }
  async function getJson(url) {
    const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Respons server bukan JSON. Periksa session atau log server.'); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memuat laporan voucher.');
    return json;
  }
  function renderRows(rows) {
    body.innerHTML = rows.map(function (row) {
      const voucherName = escapeHtml(row.voucher_label || 'Voucher');
      const code = escapeHtml(row.voucher_code || '-');
      const issueNo = row.voucher_issue_no ? `<div class="voucher-usage-sub">${escapeHtml(row.voucher_issue_no)}</div>` : '';
      const order = row.order_no ? `<strong>${escapeHtml(row.order_no)}</strong>${orderBadge(row.order_status)}<div class="voucher-usage-sub">${escapeHtml(dateTime(row.paid_at || row.ordered_at || row.used_at))}</div>` : '<span class="text-muted">Di luar nota POS</span>';
      const customer = `<strong>${escapeHtml(row.customer_display || 'Walk in')}</strong>${row.member_phone ? `<div class="voucher-usage-sub">${escapeHtml(row.member_phone)}</div>` : ''}`;
      const outlet = `<strong>${escapeHtml(row.outlet_name || '-')}</strong><div class="voucher-usage-sub">Kasir: ${escapeHtml(row.cashier_name || '-')}</div>`;
      return `<tr>
        <td><strong>${escapeHtml(dateTime(row.used_at))}</strong><div class="voucher-usage-sub">${escapeHtml(kindLabel(row.voucher_kind))}</div></td>
        <td><div class="voucher-usage-code">${code}</div><div class="fw-semibold mt-1">${voucherName}</div>${issueNo}</td>
        <td>${order}</td>
        <td>${customer}</td>
        <td>${outlet}</td>
        <td class="text-end">${escapeHtml(voucherFace(row))}</td>
        <td class="text-end"><span class="voucher-usage-amount">${escapeHtml(money(row.applied_amount))}</span></td>
        <td>${usageBadge(row.usage_status)}</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-detail-id="${Number(row.id || 0)}">Detail</button></td>
      </tr>`;
    }).join('');
    empty.classList.toggle('d-none', rows.length > 0);
    body.querySelectorAll('[data-detail-id]').forEach(function (button) {
      button.addEventListener('click', function () { openDetail(Number(button.dataset.detailId || 0)); });
    });
  }
  function renderPagination(meta) {
    const total = Number(meta && meta.total || 0);
    const page = Number(meta && meta.page || state.page || 1);
    const limit = Number(meta && meta.limit || state.limit || 25);
    const pages = Math.max(1, Number(meta && meta.total_pages || 1));
    const start = total > 0 ? ((page - 1) * limit) + 1 : 0;
    const end = Math.min(total, page * limit);
    paginationInfo.textContent = total > 0 ? `Menampilkan ${start}-${end} dari ${total} pemakaian voucher.` : 'Belum ada data.';
    pagination.innerHTML = '';
    const makeButton = function (label, target, disabled) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-sm btn-outline-secondary';
      button.textContent = label;
      button.disabled = disabled;
      button.addEventListener('click', function () { state.page = target; loadRows(); });
      return button;
    };
    pagination.appendChild(makeButton('Sebelumnya', Math.max(1, page - 1), page <= 1));
    const pageLabel = document.createElement('span');
    pageLabel.className = 'btn btn-sm btn-light border disabled';
    pageLabel.textContent = `Hal ${page}/${pages}`;
    pagination.appendChild(pageLabel);
    pagination.appendChild(makeButton('Berikutnya', Math.min(pages, page + 1), page >= pages));
  }
  async function loadRows() {
    controls.button.disabled = true;
    controls.button.textContent = 'Memuat...';
    try {
      const json = await getJson(config.dataUrl + '?' + queryString());
      // Loyalty controller returns its payload at the top level. Keep the
      // wrapped fallback so this page also remains compatible with helpers
      // that use { ok: true, data: {...} }.
      const result = json.data && typeof json.data === 'object' ? json.data : json;
      schemaMessage.classList.toggle('d-none', result.schema_ready !== false);
      schemaMessage.textContent = result.schema_ready === false ? String(result.message || 'Log voucher belum tersedia.') : '';
      renderRows(Array.isArray(result.rows) ? result.rows : []);
      renderPagination(result.meta || {});
    } catch (error) {
      body.innerHTML = '';
      empty.textContent = error && error.message ? error.message : 'Gagal memuat laporan voucher.';
      empty.classList.remove('d-none');
      pagination.innerHTML = '';
      paginationInfo.textContent = '';
    } finally {
      controls.button.disabled = false;
      controls.button.textContent = 'Terapkan';
    }
  }
  function detailCard(label, value) {
    return `<div class="col-sm-6 col-lg-3"><div class="voucher-usage-modal-card"><div class="voucher-usage-modal-label">${escapeHtml(label)}</div><div class="voucher-usage-modal-value">${value || '-'}</div></div></div>`;
  }
  async function openDetail(id) {
    if (id <= 0) return;
    detailBody.innerHTML = '<div class="text-muted py-3">Memuat detail pemakaian voucher...</div>';
    if (detailModal) detailModal.show();
    try {
      const json = await getJson(config.detailBaseUrl + '/' + id);
      const data = json.data && typeof json.data === 'object' ? json.data : json;
      const usage = data.usage || {};
      const lines = Array.isArray(data.order_lines) ? data.order_lines : [];
      const payments = Array.isArray(data.payment_lines) ? data.payment_lines : [];
      const usageVoucher = escapeHtml(usage.voucher_label || usage.voucher_code || 'Voucher');
      const code = escapeHtml(usage.voucher_code || '-');
      const lineHtml = lines.length ? lines.map(function (line) {
        return `<tr><td>${Number(line.line_no || 0)}</td><td><strong>${escapeHtml(line.product_name || '-')}</strong><div class="small text-muted">${escapeHtml(line.line_status || '-')}</div></td><td class="text-end">${escapeHtml(number(line.qty, 2))}</td><td class="text-end">${escapeHtml(money(line.net_amount))}</td></tr>`;
      }).join('') : '<tr><td colspan="4" class="text-muted text-center py-3">Tidak ada rincian produk karena voucher ini tidak dipakai pada nota POS.</td></tr>';
      const paymentHtml = payments.length ? payments.map(function (line) {
        return `<div class="d-flex justify-content-between border-bottom py-2"><span>${escapeHtml(line.method_name || 'Metode pembayaran')}${line.reference_no ? `<span class="text-muted small ms-2">${escapeHtml(line.reference_no)}</span>` : ''}</span><strong>${escapeHtml(money(line.amount))}</strong></div>`;
      }).join('') : '<div class="text-muted">Tidak ada metode pembayaran tambahan. Tagihan dapat tertutup penuh oleh voucher atau potongan lain.</div>';
      detailBody.innerHTML = `
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
          <div><div class="voucher-usage-code">${code}</div><h5 class="mb-0 mt-1">${usageVoucher}</h5></div>
          <div>${usageBadge(usage.usage_status)}${orderBadge(usage.order_status)}</div>
        </div>
        <div class="row g-2 mb-3">
          ${detailCard('Nilai voucher', escapeHtml(voucherFace(usage)))}
          ${detailCard('Nilai dipakai', escapeHtml(money(usage.applied_amount)))}
          ${detailCard('Dipakai pada', escapeHtml(dateTime(usage.used_at)))}
          ${detailCard('Jenis', escapeHtml(kindLabel(usage.voucher_kind)))}
          ${detailCard('Nota', escapeHtml(usage.order_no || 'Di luar nota POS'))}
          ${detailCard('Customer', escapeHtml(usage.customer_display || 'Walk in'))}
          ${detailCard('Outlet', escapeHtml(usage.outlet_name || '-'))}
          ${detailCard('Kasir', escapeHtml(usage.cashier_name || '-'))}
        </div>
        <div class="row g-3">
          <div class="col-lg-8">
            <div class="border rounded-4 overflow-hidden">
              <div class="px-3 py-2 border-bottom bg-light"><strong>Rincian Pesanan</strong></div>
              <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Baris</th><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Nilai</th></tr></thead><tbody>${lineHtml}</tbody></table></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="border rounded-4 p-3 h-100">
              <strong>Ringkasan Pembayaran</strong>
              <div class="small text-muted mt-1 mb-2">Dokumen: ${escapeHtml(usage.payment_no || '-')}</div>
              ${paymentHtml}
              <div class="border-top mt-3 pt-3 small text-muted">Catatan: ${escapeHtml(usage.notes || '-')}</div>
            </div>
          </div>
        </div>`;
    } catch (error) {
      detailBody.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error && error.message ? error.message : 'Gagal memuat detail voucher.')}</div>`;
    }
  }

  document.getElementById('voucher-usage-filter-form').addEventListener('submit', function (event) {
    event.preventDefault();
    state.q = controls.q.value.trim();
    state.date_from = controls.dateFrom.value;
    state.date_to = controls.dateTo.value;
    state.voucher_kind = controls.kind.value;
    state.usage_status = controls.status.value;
    state.outlet_id = Number(controls.outlet.value || 0);
    state.limit = Number(controls.limit.value || 25);
    state.page = 1;
    loadRows();
  });
  document.getElementById('voucher-usage-reset').addEventListener('click', function () {
    state.q = '';
    state.date_from = defaultDateFrom;
    state.date_to = defaultDateTo;
    state.voucher_kind = 'ALL';
    state.usage_status = 'ALL';
    state.outlet_id = 0;
    state.page = 1;
    state.limit = 25;
    syncControls();
    loadRows();
  });

  syncControls();
  loadRows();
});
</script>
