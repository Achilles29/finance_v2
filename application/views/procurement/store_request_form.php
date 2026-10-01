<?php
$mode = (string)($mode ?? 'create');
$isEdit = $mode === 'edit';
$requestId = (int)($request_id ?? 0);
$header = (array)($header ?? []);
$lines = (array)($lines ?? []);
$divisionOptions = $division_options ?? [];
$destinationOptions = $destination_options ?? [];
$destinationGuardMap = $destination_guard_map ?? [];
$procurementMutationCsrfToken = (string)($procurement_mutation_csrf_token ?? '');
$usagePurposeOptions = (array)($usage_purpose_options ?? [
  ['value' => 'BAHAN_BAKU', 'label' => 'Persediaan Produksi'],
  ['value' => 'OPERASIONAL', 'label' => 'Kebutuhan Operasional'],
]);

$initialLines = [];
foreach ($lines as $ln) {
    $initialLines[] = [
        'line_kind' => (string)($ln['line_kind'] ?? ((int)($ln['material_id'] ?? 0) > 0 ? 'MATERIAL' : 'ITEM')),
        'item_id' => (int)($ln['item_id'] ?? 0) ?: null,
        'material_id' => (int)($ln['material_id'] ?? 0) ?: null,
        'profile_key' => (string)($ln['profile_key'] ?? ''),
        'profile_name' => (string)($ln['profile_name'] ?? ''),
        'profile_brand' => (string)($ln['profile_brand'] ?? ''),
        'profile_description' => (string)($ln['profile_description'] ?? ''),
        'profile_expired_date' => (string)($ln['profile_expired_date'] ?? ''),
        'expiry_policy' => (string)($ln['expiry_policy'] ?? (!empty($ln['required_expiry_date']) || !empty($ln['profile_expired_date']) ? 'EXACT_DATE' : 'NONE')),
        'required_expiry_date' => (string)($ln['required_expiry_date'] ?? ($ln['profile_expired_date'] ?? '')),
        'min_remaining_days' => !empty($ln['min_remaining_days']) ? (int)$ln['min_remaining_days'] : null,
        'buy_uom_id' => (int)($ln['buy_uom_id'] ?? 0),
        'content_uom_id' => (int)($ln['content_uom_id'] ?? 0),
        'profile_content_per_buy' => (float)($ln['profile_content_per_buy'] ?? 1),
        'profile_buy_uom_code' => (string)($ln['profile_buy_uom_code'] ?? ''),
        'profile_content_uom_code' => (string)($ln['profile_content_uom_code'] ?? ''),
        'last_unit_price' => (float)($ln['last_unit_price'] ?? ($ln['standard_price'] ?? 0)),
        'qty_buy_balance' => (float)($ln['qty_buy_balance'] ?? 0),
        'qty_content_balance' => (float)($ln['qty_content_balance'] ?? 0),
        'qty_buy_requested' => (float)($ln['qty_buy_requested'] ?? 0),
        'qty_content_requested' => (float)($ln['qty_content_requested'] ?? 0),
        'default_usage_purpose' => (string)($ln['default_usage_purpose'] ?? ($ln['usage_purpose'] ?? 'BAHAN_BAKU')),
        'usage_purpose' => (string)($ln['usage_purpose'] ?? ($ln['default_usage_purpose'] ?? 'BAHAN_BAKU')),
    ];
}
?>

<style>
  .sr-scroll { max-height: 300px; overflow: auto; }
  .sr-form-card .table td, .sr-form-card .table th { vertical-align: middle; }
  .sr-action-btn { border-radius: 9px; padding: 4px 10px !important; display: inline-flex; align-items: center; justify-content: center; min-height: 32px; font-size: 12px; font-weight: 600; line-height: 1.2; }
  .sr-form-card .table { min-width: 1300px; }
  .sr-form-card .table th { white-space: nowrap; }
  .sr-form-card .action-icon-btn { width: 30px !important; height: 30px !important; min-width: 30px !important; border-radius: 8px !important; padding: 0 !important; }
  .sr-form-card .action-icon-btn i { font-size: .92rem !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><i class="ri-inbox-archive-line page-title-icon me-1"></i><?php echo html_escape($title ?? ($isEdit ? 'Edit Store Request' : 'Buat Store Request')); ?></h4>
    <small class="text-muted">Mode full page. Guarding divisi-tujuan dan validasi line sama dengan mode modal.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('store-requests'); ?>" class="btn btn-outline-secondary sr-action-btn"><i class="ri ri-arrow-left-line me-1"></i><span>Kembali</span></a>
  </div>
</div>

<div id="srFormAlert"></div>

<div class="card sr-form-card">
  <div class="card-body">
    <div class="row g-2 mb-2">
      <div class="col-md-2">
        <label class="form-label mb-1">Tgl Request</label>
        <input type="date" id="sr_request_date" class="form-control" value="<?php echo html_escape((string)($header['request_date'] ?? date('Y-m-d'))); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Tgl Butuh</label>
        <input type="date" id="sr_needed_date" class="form-control" value="<?php echo html_escape((string)($header['needed_date'] ?? date('Y-m-d'))); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select id="sr_division_id" class="form-select">
          <?php foreach($divisionOptions as $d): ?>
            <option value="<?php echo (int)$d['id']; ?>" <?php echo (int)($header['request_division_id'] ?? 0) === (int)$d['id'] ? 'selected' : ''; ?>>
              <?php echo html_escape((string)($d['division_name'] ?? $d['name'] ?? ('DIV#'.$d['id']))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Tujuan</label>
        <select id="sr_destination_type" class="form-select">
          <?php foreach($destinationOptions as $op): ?>
            <option value="<?php echo html_escape((string)$op['value']); ?>" <?php echo (string)($header['destination_type'] ?? '') === (string)$op['value'] ? 'selected' : ''; ?>>
              <?php echo html_escape((string)$op['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Catatan</label>
        <input type="text" id="sr_notes" class="form-control" placeholder="Opsional" value="<?php echo html_escape((string)($header['notes'] ?? '')); ?>">
      </div>
    </div>

    <?php if ($isEdit): ?>
    <div class="mb-2 small text-muted">
      SR No: <strong><?php echo html_escape((string)($header['sr_no'] ?? '-')); ?></strong>
      | Status: <strong><?php echo html_escape((string)($header['status'] ?? '-')); ?></strong>
    </div>
    <?php endif; ?>

    <div class="border rounded p-2 mb-2">
      <label class="form-label mb-1">Cari Profile Stok Gudang (AJAX)</label>
      <div class="d-flex gap-2">
        <input type="text" id="sr_profile_q" class="form-control" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="Nama profile / item / material">
        <button type="button" id="btnSearchProfile" class="btn btn-outline-primary">Cari</button>
      </div>
      <div class="sr-scroll mt-2">
        <table class="table table-sm mb-0">
          <thead><tr><th>Profile</th><th>Keterangan</th><th>UOM</th><th class="text-end">Stok Gudang</th><th class="text-end">Harga Satuan</th><th>Exp Date</th><th>Tgl Beli Terakhir</th><th style="width:80px">Aksi</th></tr></thead>
          <tbody id="srProfileResults"><tr><td colspan="8" class="text-muted text-center py-2">Belum ada pencarian.</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead><tr><th>Profile</th><th>Stok sekarang<br><small>Divisi / Gudang • satuan isi</small></th><th>Keterangan</th><th>Jenis</th><th>Pemakaian</th><th>UOM</th><th class="text-end">Snapshot profil gudang</th><th class="text-end">Harga Satuan</th><th>Exp Date</th><th>Qty Beli Req</th><th>Qty Isi Req</th><th>Aksi</th></tr></thead>
        <tbody id="srLineTableBody"><tr><td colspan="12" class="text-muted text-center py-2">Belum ada line.</td></tr></tbody>
      </table>
    </div>
    <?php $this->load->view('procurement/_manual_stock_toolbar'); ?>
    <div class="d-flex gap-2 justify-content-end mt-3">
      <a href="<?php echo site_url('store-requests'); ?>" class="btn btn-outline-secondary">Batal</a>
      <button type="button" class="btn btn-outline-primary" id="btnSaveSrSubmit"><?php echo $isEdit ? 'Update & Submit' : 'Simpan & Submit'; ?></button>
      <button type="button" class="btn btn-primary" id="btnSaveSr" data-status="DRAFT"><?php echo $isEdit ? 'Update DRAFT' : 'Simpan DRAFT'; ?></button>
    </div>
  </div>
</div>

<script>
(function(){
  'use strict';
  var isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
  var requestId = <?php echo (int)$requestId; ?>;
  var searchUrl = <?php echo json_encode(site_url('procurement/store-request/profile-search')); ?>;
  var storeUrl = <?php echo json_encode(site_url('procurement/store-request/store')); ?>;
  var updateBaseUrl = <?php echo json_encode(site_url('procurement/store-request/update/')); ?>;
  var listUrl = <?php echo json_encode(site_url('store-requests')); ?>;
  var procurementMutationCsrfToken = <?php echo json_encode($procurementMutationCsrfToken); ?>;
  var destinationGuardMap = <?php echo json_encode($destinationGuardMap); ?>;
  var usagePurposeOptions = <?php echo json_encode($usagePurposeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  var initialLines = <?php echo json_encode($initialLines); ?>;
  var createLines = Array.isArray(initialLines) ? initialLines : [];
  var profileSearchTimer = null;
  var profileSearchAbort = null;
  var alertBox = document.getElementById('srFormAlert');
  var stockControl = window.ProcurementCurrentStock.create({
    url: <?php echo json_encode(site_url('procurement/store-request/stock-preview')); ?>,
    csrfHeader: 'X-Procurement-Mutation-Csrf', csrf: procurementMutationCsrfToken,
    payload: function () { return {header:{division_id:Number(document.getElementById('sr_division_id').value),
      destination_type:document.getElementById('sr_destination_type').value}, lines:createLines}; }
  });
  document.addEventListener('change', function () { queueMicrotask(function () { stockControl.changed(); }); });

  function flash(type, msg){
    if (!alertBox) return;
    alertBox.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-2">' + msg + '</div>';
  }
  function num(v){ var n = Number(v || 0); return Number.isFinite(n) ? n : 0; }
  function esc(s){ return String(s || '').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function fmtMoney(v){ return 'Rp ' + num(v).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  function fmtDate(v){ return v ? esc(v) : '-'; }
  function normalizeUsagePurpose(value){ return String(value || '').toUpperCase().trim() === 'OPERASIONAL' ? 'OPERASIONAL' : 'BAHAN_BAKU'; }
  function defaultUsagePurposeForRow(row){
    var lineKind = String(row && row.line_kind || '').toUpperCase().trim();
    return lineKind === 'MATERIAL' || num(row && row.material_id) > 0 ? 'BAHAN_BAKU' : 'OPERASIONAL';
  }
  function resolvedUsagePurposeForRow(row){
    if (row && String(row.usage_purpose || '').trim() !== '') return normalizeUsagePurpose(row.usage_purpose);
    return defaultUsagePurposeForRow(row);
  }
  function renderUsagePurposeOptions(selected){
    var normalized = normalizeUsagePurpose(selected);
    var html = '';
    var rows = usagePurposeOptions.length ? usagePurposeOptions : [
      { value: 'BAHAN_BAKU', label: 'Persediaan Produksi' },
      { value: 'OPERASIONAL', label: 'Kebutuhan Operasional' }
    ];
    rows.forEach(function(option){
      var value = normalizeUsagePurpose(option.value);
      html += '<option value="' + esc(value) + '"' + (value === normalized ? ' selected' : '') + '>' + esc(option.label || value) + '</option>';
    });
    return html;
  }
  function fetchJson(url, opts){
    opts = opts || {};
    if (String(opts.method || 'GET').toUpperCase() === 'POST') {
      opts.headers = Object.assign({'X-Procurement-Mutation-CSRF': procurementMutationCsrfToken}, opts.headers || {});
    }
    return fetch(url, opts).then(function(res){
      return res.text().then(function(t){
        var d = {};
        var invalidJson = false;
        try { d = t ? JSON.parse(t) : {}; } catch (e) { d = {}; invalidJson = true; }
        if (!res.ok && !d.ok) {
          d.ok = false;
          d.message = d.message || (invalidJson
            ? 'Server gagal memproses permintaan. Muat ulang halaman lalu coba kembali.'
            : ('Request gagal (' + res.status + ')'));
        }
        return d;
      });
    });
  }

  function parseDestinationOptionMeta(){
    var destEl = document.getElementById('sr_destination_type');
    var meta = [];
    if (!destEl) return meta;
    for (var i = 0; i < destEl.options.length; i++) {
      var op = destEl.options[i];
      meta.push({ value: String(op.value || ''), label: String(op.text || op.value || '') });
    }
    return meta;
  }
  var destinationOptionMeta = parseDestinationOptionMeta();

  function applyDivisionDestinationGuard(){
    var divisionEl = document.getElementById('sr_division_id');
    var destinationEl = document.getElementById('sr_destination_type');
    if (!divisionEl || !destinationEl) return;

    var divisionId = String(divisionEl.value || '');
    var allowed = destinationGuardMap[divisionId] || [];
    var allowedSet = {};
    for (var i = 0; i < allowed.length; i++) allowedSet[String(allowed[i] || '')] = true;

    var current = String(destinationEl.value || '');
    var html = [];
    destinationOptionMeta.forEach(function(op){
      if (allowedSet[op.value]) {
        html.push('<option value="' + esc(op.value) + '">' + esc(op.label) + '</option>');
      }
    });
    destinationEl.innerHTML = html.join('');
    if (current && allowedSet[current]) destinationEl.value = current;
    if (!destinationEl.value && destinationEl.options.length > 0) destinationEl.selectedIndex = 0;
  }

  function renderCreateSearch(rows){
    var tb = document.getElementById('srProfileResults'); if(!tb) return;
    if(!rows || !rows.length){ tb.innerHTML = '<tr><td colspan="8" class="text-muted text-center py-2">Tidak ada data.</td></tr>'; return; }
    var html = '';
    rows.forEach(function(row){
      var buyCode = esc(row.profile_buy_uom_code || '-');
      var contentCode = esc(row.profile_content_uom_code || '-');
      var stockBuy = num(row.qty_buy_balance).toFixed(2);
      var stockContent = num(row.qty_content_balance).toFixed(2);
      var brandText = row.profile_brand ? '<div class="small text-muted">Brand: ' + esc(row.profile_brand) + '</div>' : '';
      var descriptionText = row.profile_description ? esc(row.profile_description) : '<span class="text-muted">-</span>';
      var expDateText = fmtDate(row.profile_expired_date || '');
      var lastPurchaseDate = row.last_purchase_date ? esc(row.last_purchase_date) : '-';
      html += '<tr>'
        + '<td><strong>' + esc(row.profile_name || '-') + '</strong>' + brandText + '</td>'
        + '<td class="small">' + descriptionText + '</td>'
        + '<td>' + buyCode + ' -> ' + contentCode + '</td>'
        + '<td class="text-end"><div class="fw-semibold">' + stockBuy + ' ' + buyCode + '</div><div class="small text-muted">' + stockContent + ' ' + contentCode + '</div></td>'
        + '<td class="text-end"><div class="fw-semibold">' + fmtMoney(row.last_unit_price) + '</div><div class="small text-muted">/ ' + buyCode + '</div></td>'
        + '<td>' + expDateText + '</td>'
        + '<td>' + lastPurchaseDate + '</td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-primary sr-pick-profile" data-row="' + esc(JSON.stringify(row)) + '">Pilih</button></td>'
        + '</tr>';
    });
    tb.innerHTML = html;
  }

  function renderCreateLines(){
    var tb = document.getElementById('srLineTableBody'); if(!tb) return;
    if(!createLines.length){ tb.innerHTML = '<tr><td colspan="12" class="text-muted text-center py-2">Belum ada line.</td></tr>'; stockControl.changed(); return; }
    var html = '';
    createLines.forEach(function(line, idx){
      var lineBrand = line.profile_brand ? '<div class="small text-muted">Brand: ' + esc(line.profile_brand) + '</div>' : '';
      var lineDescription = line.profile_description ? esc(line.profile_description) : '<span class="text-muted">-</span>';
      html += '<tr>'
        + '<td><strong>' + esc(line.profile_name || '-') + '</strong>' + lineBrand + '</td>'
        + '<td data-live-stock-line="' + (idx + 1) + '">Menunggu cek stok…</td>'
        + '<td class="small">' + lineDescription + '</td>'
        + '<td>' + esc(line.line_kind || '-') + '</td>'
        + '<td><select class="form-select form-select-sm sr-usage-purpose" data-idx="' + idx + '">' + renderUsagePurposeOptions(line.usage_purpose || line.default_usage_purpose) + '</select></td>'
        + '<td>' + esc(line.profile_buy_uom_code || '-') + ' -> ' + esc(line.profile_content_uom_code || '-') + '</td>'
        + '<td class="text-end"><div class="fw-semibold">' + num(line.qty_buy_balance).toFixed(2) + ' ' + esc(line.profile_buy_uom_code || '-') + '</div><div class="small text-muted">' + num(line.qty_content_balance).toFixed(2) + ' ' + esc(line.profile_content_uom_code || '-') + '</div></td>'
        + '<td class="text-end"><div class="fw-semibold">' + fmtMoney(line.last_unit_price) + '</div><div class="small text-muted">/ ' + esc(line.profile_buy_uom_code || '-') + '</div></td>'
        + '<td>' + fmtDate(line.profile_expired_date || '') + '</td>'
        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm sr-qty-buy" data-idx="' + idx + '" value="' + num(line.qty_buy_requested).toFixed(2) + '"></td>'
        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm sr-qty-content" data-idx="' + idx + '" value="' + num(line.qty_content_requested).toFixed(2) + '"></td>'
        + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn sr-remove-line" data-idx="' + idx + '" title="Hapus Baris" aria-label="Hapus Baris"><i class="ri ri-delete-bin-line"></i></button></td>'
        + '</tr>';
    });
    tb.innerHTML = html;
    stockControl.changed();
  }

  function addCreateLine(row){
    var key = [row.profile_key||'', row.item_id||0, row.material_id||0, row.buy_uom_id||0, row.content_uom_id||0].join('|');
    for (var i = 0; i < createLines.length; i++) {
      var x = createLines[i];
      var xk = [x.profile_key||'', x.item_id||0, x.material_id||0, x.buy_uom_id||0, x.content_uom_id||0].join('|');
      if (xk === key) { flash('warning', 'Line dengan profile dan UOM yang sama sudah ada.'); return; }
    }
    var cpb = num(row.profile_content_per_buy); if(cpb <= 0) cpb = 1;
    var canonicalKind = num(row.item_id) > 0 ? 'ITEM' : ((num(row.material_id) > 0) ? 'MATERIAL' : String(row.line_kind || 'ITEM').toUpperCase());
    createLines.push({
      line_kind: canonicalKind,
      item_id: num(row.item_id)||null,
      material_id: num(row.material_id)||null,
      profile_key: row.profile_key || '',
      profile_name: row.profile_name || '',
      profile_brand: row.profile_brand || '',
      profile_description: row.profile_description || '',
      profile_expired_date: row.profile_expired_date || '',
      expiry_policy: row.expiry_policy || ((row.required_expiry_date || row.profile_expired_date) ? 'EXACT_DATE' : 'NONE'),
      required_expiry_date: row.required_expiry_date || row.profile_expired_date || '',
      min_remaining_days: num(row.min_remaining_days || 0) || null,
      buy_uom_id: num(row.buy_uom_id),
      content_uom_id: num(row.content_uom_id),
      profile_content_per_buy: cpb,
      profile_buy_uom_code: row.profile_buy_uom_code || '',
      profile_content_uom_code: row.profile_content_uom_code || '',
      last_unit_price: num(row.last_unit_price || row.standard_price),
      qty_buy_balance: num(row.qty_buy_balance),
      qty_content_balance: num(row.qty_content_balance),
      qty_buy_requested: 1,
      qty_content_requested: Number(cpb.toFixed(2)),
      default_usage_purpose: defaultUsagePurposeForRow(row),
      usage_purpose: resolvedUsagePurposeForRow(row)
    });
    renderCreateLines();
  }

  function runProfileSearch(){
    var q = (document.getElementById('sr_profile_q') || {}).value || '';
    q = String(q).trim();
    if (q.length < 2) { renderCreateSearch([]); return; }

    if (profileSearchAbort && typeof profileSearchAbort.abort === 'function') {
      profileSearchAbort.abort();
    }
    profileSearchAbort = new AbortController();

    fetchJson(searchUrl + '?q=' + encodeURIComponent(q) + '&limit=20', {
      credentials: 'same-origin',
      signal: profileSearchAbort.signal
    }).then(function(res){
      if (!res || res.ok === false) {
        flash('danger', (res && res.message) ? res.message : 'Gagal memuat profile gudang.');
        return;
      }
      renderCreateSearch(res.rows || []);
    }).catch(function(err){
      if (err && err.name === 'AbortError') return;
      flash('danger', 'Gagal memuat profile gudang.');
    });
  }

  document.addEventListener('click', function(e){
    var pick = e.target.closest('.sr-pick-profile');
    if (pick){
      e.preventDefault();
      try { addCreateLine(JSON.parse(pick.getAttribute('data-row') || '{}')); } catch(err){ flash('danger', 'Data profile tidak valid.'); }
      return;
    }
    var del = e.target.closest('.sr-remove-line');
    if (del){
      e.preventDefault();
      var idxDel = parseInt(del.getAttribute('data-idx') || '-1', 10);
      if (idxDel >= 0){ createLines.splice(idxDel, 1); renderCreateLines(); }
      return;
    }
  });

  document.addEventListener('input', function(e){
    var buy = e.target.closest('.sr-qty-buy');
    if (buy){
      var idxB = parseInt(buy.getAttribute('data-idx') || '-1', 10);
      if (idxB >= 0 && createLines[idxB]){
        var cpbB = num(createLines[idxB].profile_content_per_buy); if(cpbB <= 0) cpbB = 1;
        createLines[idxB].qty_buy_requested = num(buy.value);
        createLines[idxB].qty_content_requested = createLines[idxB].qty_buy_requested * cpbB;
        renderCreateLines();
      }
      return;
    }
    var content = e.target.closest('.sr-qty-content');
    if (content){
      var idxC = parseInt(content.getAttribute('data-idx') || '-1', 10);
      if (idxC >= 0 && createLines[idxC]){
        var cpbC = num(createLines[idxC].profile_content_per_buy); if(cpbC <= 0) cpbC = 1;
        createLines[idxC].qty_content_requested = num(content.value);
        createLines[idxC].qty_buy_requested = createLines[idxC].qty_content_requested / cpbC;
        renderCreateLines();
      }
    }
  });

  document.addEventListener('change', function(e){
    var usage = e.target.closest('.sr-usage-purpose');
    if (usage){
      var idxU = parseInt(usage.getAttribute('data-idx') || '-1', 10);
      if (idxU >= 0 && createLines[idxU]){
        createLines[idxU].usage_purpose = normalizeUsagePurpose(usage.value);
      }
    }
  });

  var divEl = document.getElementById('sr_division_id');
  if (divEl) divEl.addEventListener('change', applyDivisionDestinationGuard);
  applyDivisionDestinationGuard();
  renderCreateLines();

  var btnSearch = document.getElementById('btnSearchProfile');
  if (btnSearch) btnSearch.addEventListener('click', runProfileSearch);

  var profileQEl = document.getElementById('sr_profile_q');
  if (profileQEl){
    profileQEl.addEventListener('input', function(){
      if (profileSearchTimer) window.clearTimeout(profileSearchTimer);
      profileSearchTimer = window.setTimeout(runProfileSearch, 280);
    });
    profileQEl.addEventListener('keydown', function(ev){
      if (ev.key === 'Enter'){ ev.preventDefault(); runProfileSearch(); }
    });
  }

  var btnSave = document.getElementById('btnSaveSr');
  if (btnSave){
    btnSave.addEventListener('click', async function(){
      if (!createLines.length) { flash('warning', 'Line Store Request belum ada.'); return; }
      var old = btnSave.innerHTML;
      btnSave.disabled = true;
      btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

      var desiredStatus = (btnSave.getAttribute('data-status') || 'DRAFT').toUpperCase();
      var payload = {
        header: {
          request_date: (document.getElementById('sr_request_date') || {}).value || '',
          needed_date: (document.getElementById('sr_needed_date') || {}).value || '',
          request_division_id: Number((document.getElementById('sr_division_id') || {}).value || 0),
          destination_type: (document.getElementById('sr_destination_type') || {}).value || '',
          notes: (document.getElementById('sr_notes') || {}).value || '',
          status: desiredStatus
        },
        lines: createLines
      };

      var divisionId = String(payload.header.request_division_id || '');
      var allowed = destinationGuardMap[divisionId] || [];
      if (allowed.indexOf(payload.header.destination_type) === -1) {
        flash('warning', 'Tujuan tidak sesuai dengan divisi yang dipilih.');
        btnSave.disabled = false;
        btnSave.innerHTML = old;
        return;
      }

      var submitUrl = isEdit ? (updateBaseUrl + requestId) : storeUrl;
      var submittedLines = JSON.stringify(payload.lines);
      if (!(await stockControl.confirmBeforeSave())) { btnSave.disabled=false; btnSave.innerHTML=old; return; }
      if (submittedLines !== JSON.stringify(createLines)
          || payload.header.request_division_id !== Number(document.getElementById('sr_division_id').value)
          || payload.header.destination_type !== document.getElementById('sr_destination_type').value) {
        flash('warning','Pengajuan berubah saat pemeriksaan. Periksa lagi lalu simpan ulang.'); btnSave.disabled=false; btnSave.innerHTML=old; return;
      }
      fetchJson(submitUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function(res){
        if (!res || !res.ok) {
          flash('danger', (res && res.message) ? res.message : 'Gagal menyimpan Store Request.');
          btnSave.disabled = false;
          btnSave.innerHTML = old;
          return;
        }
        window.location.href = listUrl;
      }).catch(function(){
        flash('danger', 'Gagal menyimpan Store Request.');
        btnSave.disabled = false;
        btnSave.innerHTML = old;
      });
    });
  }

  var btnSaveSubmit = document.getElementById('btnSaveSrSubmit');
  if (btnSaveSubmit && btnSave){
    btnSaveSubmit.addEventListener('click', function(){
      btnSave.setAttribute('data-status', 'SUBMITTED');
      btnSave.click();
      btnSave.setAttribute('data-status', 'DRAFT');
    });
  }
})();
</script>
