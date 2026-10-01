<?php
$dateFrom = (string)($date_from ?? date('Y-m-01'));
$dateTo = (string)($date_to ?? date('Y-m-d'));
$status = strtoupper(trim((string)($status ?? 'ALL')));
$purchaseTypeId = (int)($purchase_type_id ?? 0);
$q = (string)($q ?? '');
$page = max(1, (int)($page ?? 1));
$perPage = max(25, (int)($per_page ?? 50));
$initialPayload = is_array($initial_payload ?? null) ? $initial_payload : [];
$selectedPurchaseTypeName = (string)($selected_purchase_type_name ?? 'Semua Tipe');
?>

<style>
  .pur-detail-card { border: 0; box-shadow: 0 8px 24px rgba(67, 89, 113, .08); border-radius: 16px; }
  .pur-detail-kpi { font-size: 1.25rem; font-weight: 800; color: #233243; line-height: 1.1; }
  .pur-detail-kpi-label { font-size: .75rem; color: #6c7a89; text-transform: uppercase; letter-spacing: .06em; }
  .pur-detail-filter .form-label { font-size: .78rem; font-weight: 700; margin-bottom: .35rem; }
  .pur-detail-table-wrap {
    max-height: 68vh;
    overflow: auto;
    border: 1px solid #eadfd8;
    border-radius: 12px;
    background: #fff;
  }
  .pur-detail-table th,
  .pur-detail-table td { white-space: nowrap; font-size: .78rem; vertical-align: middle; }
  .pur-detail-table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #fff;
    box-shadow: inset 0 -1px 0 #eadfd8;
  }
  .pur-detail-table .col-name {
    min-width: 260px;
    white-space: normal;
  }
  .pur-detail-link { color: inherit; text-decoration: none; font-weight: 600; }
  .pur-detail-link:hover { text-decoration: underline; }
  .pur-detail-pager { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
  .pur-detail-pager-buttons { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; }
  .pur-detail-pager-buttons .btn { min-width: 40px; }
  .pur-detail-muted { color: #7b8794; font-size: .82rem; }
  .pur-detail-alert { display: none; }
  .pur-detail-alert.is-visible { display: block; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-file-list-3-line page-title-icon"></i>Rincian Belanja Purchase</h4>
    <small class="text-muted">Filter detail belanja per tipe purchase, rentang tanggal, dan pencarian baris purchase.</small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?php echo site_url('purchase-orders/report') . '?' . http_build_query([
      'report_tab' => 'ringkasan',
      'date_from' => $dateFrom,
      'date_to' => $dateTo,
      'status' => $status,
      'purchase_type_id' => $purchaseTypeId,
    ]); ?>">Kembali ke Ringkasan</a>
  </div>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'report-purchase']); ?>

<div class="card pur-detail-card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <div class="fw-semibold" id="purDetailTypeText"><?php echo html_escape($selectedPurchaseTypeName); ?></div>
        <div class="pur-detail-muted" id="purDetailRangeText"><?php echo html_escape($dateFrom); ?> s/d <?php echo html_escape($dateTo); ?> | Status <?php echo html_escape($status); ?></div>
      </div>
    </div>
    <form id="purDetailFilterForm" class="row g-2 pur-detail-filter">
      <div class="col-xl-2 col-md-3">
        <label class="form-label">Dari</label>
        <input type="date" name="date_from" id="purDetailDateFrom" class="form-control" value="<?php echo html_escape($dateFrom); ?>">
      </div>
      <div class="col-xl-2 col-md-3">
        <label class="form-label">Sampai</label>
        <input type="date" name="date_to" id="purDetailDateTo" class="form-control" value="<?php echo html_escape($dateTo); ?>">
      </div>
      <div class="col-xl-2 col-md-2">
        <label class="form-label">Status</label>
        <select name="status" id="purDetailStatus" class="form-select">
          <?php foreach (($status_options ?? ['ALL']) as $st): $val = strtoupper((string)$st); ?>
            <option value="<?php echo html_escape($val); ?>" <?php echo $val === $status ? 'selected' : ''; ?>><?php echo html_escape($val); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-xl-3 col-md-4">
        <label class="form-label">Tipe Purchase</label>
        <select name="purchase_type_id" id="purDetailPurchaseType" class="form-select">
          <option value="0">Semua Tipe</option>
          <?php foreach (($purchase_types ?? []) as $pt): ?>
            <option value="<?php echo (int)($pt['id'] ?? 0); ?>" <?php echo (int)($pt['id'] ?? 0) === $purchaseTypeId ? 'selected' : ''; ?>>
              <?php echo html_escape((string)($pt['type_name'] ?? '-')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-xl-1 col-md-2">
        <label class="form-label">Baris</label>
        <select name="per_page" id="purDetailPerPage" class="form-select">
          <?php foreach ([25, 50, 100, 200] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $pp === $perPage ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-xl-10 col-md-9">
        <label class="form-label">Pencarian</label>
        <input type="text" name="q" id="purDetailQ" class="form-control" value="<?php echo html_escape($q); ?>" placeholder="Cari nomor PO, vendor, tipe, item, brand, atau keterangan line...">
      </div>
      <div class="col-xl-2 col-md-3 d-flex align-items-end justify-content-end gap-2">
        <button type="button" id="purDetailResetBtn" class="btn btn-outline-secondary">Reset</button>
        <button type="submit" class="btn btn-primary">Terapkan</button>
      </div>
    </form>
  </div>
</div>

<div id="purDetailAlert" class="alert alert-danger pur-detail-alert mb-3"></div>

<div class="row g-2 mb-3">
  <div class="col-md-3"><div class="card pur-detail-card"><div class="card-body"><div class="pur-detail-kpi-label">Total PO</div><div class="pur-detail-kpi" id="purDetailTotalPo">0</div></div></div></div>
  <div class="col-md-3"><div class="card pur-detail-card"><div class="card-body"><div class="pur-detail-kpi-label">Total Baris</div><div class="pur-detail-kpi" id="purDetailTotalLine">0</div></div></div></div>
  <div class="col-md-3"><div class="card pur-detail-card"><div class="card-body"><div class="pur-detail-kpi-label">Total Vendor</div><div class="pur-detail-kpi" id="purDetailTotalVendor">0</div></div></div></div>
  <div class="col-md-3"><div class="card pur-detail-card"><div class="card-body"><div class="pur-detail-kpi-label">Nilai Purchase</div><div class="pur-detail-kpi" id="purDetailTotalValue">Rp 0,00</div><div class="pur-detail-muted mt-1">Qty: <span id="purDetailTotalQty">0,00</span></div></div></div></div>
</div>

<div class="card pur-detail-card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h6 class="mb-0">Rincian Belanja</h6>
      <div class="pur-detail-muted" id="purDetailPaginationInfo">Memuat data...</div>
    </div>
    <div class="pur-detail-table-wrap">
      <table class="table table-sm table-striped pur-detail-table mb-0">
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>PO</th>
            <th>Vendor</th>
            <th>Tipe</th>
            <th class="col-name">Rincian</th>
            <th>Merk</th>
            <th class="text-end">Qty Beli</th>
            <th class="text-end">UOM Isi</th>
            <th class="text-end">Nilai</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="purDetailRows"></tbody>
      </table>
    </div>
    <div class="pur-detail-pager mt-3">
      <div class="pur-detail-muted" id="purDetailPagerText">Total data: 0</div>
      <div class="pur-detail-pager-buttons" id="purDetailPagerButtons"></div>
    </div>
  </div>
</div>

<script>
(function () {
  const dataUrl = <?php echo json_encode((string)($detail_data_url ?? site_url('purchase-orders/report/detail-data'))); ?>;
  const pageUrl = <?php echo json_encode(site_url('purchase-orders/report/detail')); ?>;
  const orderDetailBaseUrl = <?php echo json_encode(rtrim(site_url('purchase-orders/detail'), '/')); ?>;
  const defaultState = {
    date_from: <?php echo json_encode($dateFrom); ?>,
    date_to: <?php echo json_encode($dateTo); ?>,
    status: <?php echo json_encode($status); ?>,
    purchase_type_id: <?php echo json_encode($purchaseTypeId); ?>,
    q: <?php echo json_encode($q); ?>,
    per_page: <?php echo json_encode($perPage); ?>,
    page: <?php echo json_encode($page); ?>
  };
  const state = Object.assign({}, defaultState);
  const initialPayload = <?php echo json_encode($initialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
  const numberFmt = new Intl.NumberFormat('id-ID');
  const decimalFmt = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  let debounceTimer = null;

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function buildQuery(currentState) {
    const params = new URLSearchParams();
    params.set('date_from', currentState.date_from || '');
    params.set('date_to', currentState.date_to || '');
    params.set('status', currentState.status || 'ALL');
    params.set('purchase_type_id', String(Number(currentState.purchase_type_id || 0)));
    params.set('q', currentState.q || '');
    params.set('per_page', String(Number(currentState.per_page || 50)));
    params.set('page', String(Number(currentState.page || 1)));
    return params.toString();
  }

  function syncFormFromState(currentState) {
    document.getElementById('purDetailDateFrom').value = currentState.date_from || '';
    document.getElementById('purDetailDateTo').value = currentState.date_to || '';
    document.getElementById('purDetailStatus').value = currentState.status || 'ALL';
    document.getElementById('purDetailPurchaseType').value = String(Number(currentState.purchase_type_id || 0));
    document.getElementById('purDetailQ').value = currentState.q || '';
    document.getElementById('purDetailPerPage').value = String(Number(currentState.per_page || 50));
  }

  function syncStateFromForm() {
    state.date_from = document.getElementById('purDetailDateFrom').value || '';
    state.date_to = document.getElementById('purDetailDateTo').value || '';
    state.status = document.getElementById('purDetailStatus').value || 'ALL';
    state.purchase_type_id = Number(document.getElementById('purDetailPurchaseType').value || 0);
    state.q = document.getElementById('purDetailQ').value || '';
    state.per_page = Number(document.getElementById('purDetailPerPage').value || 50);
  }

  function showAlert(message) {
    const alertEl = document.getElementById('purDetailAlert');
    alertEl.textContent = message || '';
    alertEl.classList.toggle('is-visible', !!message);
  }

  function computeLineName(row) {
    const itemName = String(row.snapshot_item_name || '').trim();
    if (itemName !== '') {
      return itemName;
    }
    const materialName = String(row.snapshot_material_name || '').trim();
    if (materialName !== '') {
      return materialName;
    }
    const lineDesc = String(row.snapshot_line_description || '').trim();
    return lineDesc !== '' ? lineDesc : '-';
  }

  function renderSummary(summary) {
    const safe = summary || {};
    document.getElementById('purDetailTotalPo').textContent = numberFmt.format(Number(safe.total_po || 0));
    document.getElementById('purDetailTotalLine').textContent = numberFmt.format(Number(safe.total_line || 0));
    document.getElementById('purDetailTotalVendor').textContent = numberFmt.format(Number(safe.total_vendor || 0));
    document.getElementById('purDetailTotalValue').textContent = 'Rp ' + decimalFmt.format(Number(safe.total_value || 0));
    document.getElementById('purDetailTotalQty').textContent = decimalFmt.format(Number(safe.total_qty_buy || 0));
  }

  function renderHeaderText(payload) {
    const typeName = String(payload.selected_purchase_type_name || 'Semua Tipe');
    document.getElementById('purDetailTypeText').textContent = typeName;
    document.getElementById('purDetailRangeText').textContent =
      (state.date_from || '-') + ' s/d ' + (state.date_to || '-') + ' | Status ' + (state.status || 'ALL');
  }

  function renderRows(rows, meta) {
    const tbody = document.getElementById('purDetailRows');
    const list = Array.isArray(rows) ? rows : [];
    const page = Number((meta || {}).page || 1);
    const limit = Number((meta || {}).limit || state.per_page || 50);
    const startNo = ((page - 1) * limit) + 1;
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Tidak ada data detail.</td></tr>';
      return;
    }

    tbody.innerHTML = list.map(function (row, idx) {
      const poUrl = orderDetailBaseUrl + '/' + encodeURIComponent(row.purchase_order_id || 0);
      const qtyText = decimalFmt.format(Number(row.qty_buy || 0)) + ' ' + escapeHtml(row.snapshot_buy_uom_code || '-');
      const contentText = decimalFmt.format(Number(row.content_per_buy || 0)) + ' ' + escapeHtml(row.snapshot_content_uom_code || '-');
      return '<tr>'
        + '<td>' + numberFmt.format(startNo + idx) + '</td>'
        + '<td>' + escapeHtml(row.request_date || '-') + '</td>'
        + '<td><a class="pur-detail-link" href="' + escapeHtml(poUrl) + '">' + escapeHtml(row.po_no || '-') + '</a></td>'
        + '<td>' + escapeHtml(row.vendor_name || '-') + '</td>'
        + '<td>' + escapeHtml(row.purchase_type_name || '-') + '</td>'
        + '<td class="col-name">' + escapeHtml(computeLineName(row)) + '</td>'
        + '<td>' + escapeHtml(row.snapshot_brand_name || '-') + '</td>'
        + '<td class="text-end">' + qtyText + '</td>'
        + '<td class="text-end">' + contentText + '</td>'
        + '<td class="text-end">Rp ' + decimalFmt.format(Number(row.line_subtotal || 0)) + '</td>'
        + '<td>' + escapeHtml(row.status || '-') + '</td>'
        + '</tr>';
    }).join('');
  }

  function renderPager(meta) {
    const safe = meta || {};
    const total = Number(safe.total || 0);
    const page = Number(safe.page || 1);
    const totalPages = Math.max(1, Number(safe.total_pages || 1));
    const limit = Math.max(1, Number(safe.limit || state.per_page || 50));
    const start = total > 0 ? ((page - 1) * limit) + 1 : 0;
    const end = total > 0 ? Math.min(total, page * limit) : 0;
    document.getElementById('purDetailPaginationInfo').textContent = total > 0
      ? ('Menampilkan ' + numberFmt.format(start) + '-' + numberFmt.format(end) + ' dari ' + numberFmt.format(total) + ' baris')
      : 'Tidak ada data';
    document.getElementById('purDetailPagerText').textContent = 'Total data: ' + numberFmt.format(total);

    const holder = document.getElementById('purDetailPagerButtons');
    if (totalPages <= 1) {
      holder.innerHTML = '';
      return;
    }

    const buttons = [];
    buttons.push('<button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + Math.max(1, page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>Prev</button>');
    const firstPage = Math.max(1, page - 2);
    const lastPage = Math.min(totalPages, page + 2);
    for (let p = firstPage; p <= lastPage; p += 1) {
      buttons.push('<button type="button" class="btn btn-sm ' + (p === page ? 'btn-primary' : 'btn-outline-secondary') + '" data-page="' + p + '">' + p + '</button>');
    }
    buttons.push('<button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + Math.min(totalPages, page + 1) + '" ' + (page >= totalPages ? 'disabled' : '') + '>Next</button>');
    holder.innerHTML = buttons.join('');
    holder.querySelectorAll('button[data-page]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const nextPage = Number(btn.getAttribute('data-page') || 1);
        if (nextPage === page || btn.disabled) {
          return;
        }
        state.page = nextPage;
        loadData(true);
      });
    });
  }

  function renderPayload(payload) {
    renderHeaderText(payload || {});
    renderSummary((payload || {}).summary || {});
    renderRows((payload || {}).rows || [], (payload || {}).meta || {});
    renderPager((payload || {}).meta || {});
  }

  function setLoading(isLoading) {
    const info = document.getElementById('purDetailPaginationInfo');
    if (isLoading) {
      info.textContent = 'Memuat data...';
    }
  }

  async function loadData(replaceUrl) {
    syncStateFromForm();
    setLoading(true);
    showAlert('');
    try {
      const res = await fetch(dataUrl + '?' + buildQuery(state), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const payload = await res.json();
      if (!res.ok || !payload || payload.ok !== true) {
        throw new Error(payload && payload.message ? payload.message : 'Gagal memuat rincian purchase.');
      }
      renderPayload(payload);
      if (replaceUrl) {
        window.history.replaceState(null, '', pageUrl + '?' + buildQuery(state));
      }
    } catch (err) {
      showAlert(err && err.message ? err.message : 'Gagal memuat rincian purchase.');
      renderRows([], { page: 1, limit: state.per_page || 50 });
      renderPager({ total: 0, page: 1, total_pages: 1, limit: state.per_page || 50 });
    }
  }

  document.getElementById('purDetailFilterForm').addEventListener('submit', function (event) {
    event.preventDefault();
    state.page = 1;
    loadData(true);
  });

  document.getElementById('purDetailPerPage').addEventListener('change', function () {
    state.page = 1;
    loadData(true);
  });

  document.getElementById('purDetailStatus').addEventListener('change', function () {
    state.page = 1;
    loadData(true);
  });

  document.getElementById('purDetailPurchaseType').addEventListener('change', function () {
    state.page = 1;
    loadData(true);
  });

  document.getElementById('purDetailDateFrom').addEventListener('change', function () {
    state.page = 1;
    loadData(true);
  });

  document.getElementById('purDetailDateTo').addEventListener('change', function () {
    state.page = 1;
    loadData(true);
  });

  document.getElementById('purDetailQ').addEventListener('input', function () {
    state.q = this.value || '';
    state.page = 1;
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(function () {
      loadData(true);
    }, 300);
  });

  document.getElementById('purDetailResetBtn').addEventListener('click', function () {
    Object.assign(state, defaultState);
    syncFormFromState(state);
    loadData(true);
  });

  syncFormFromState(state);
  renderPayload(initialPayload);
})();
</script>
