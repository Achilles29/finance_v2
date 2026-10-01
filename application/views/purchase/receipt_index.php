<?php
$poLinesUrl = site_url('purchase/receipt/po-lines');
$storeUrl = site_url('purchase/receipt/store');
$purchaseMutationCsrfToken = (string)($purchase_mutation_csrf_token ?? '');
?>

<div class="mb-2">
  <h4 class="mb-1"><i class="ri ri-inbox-archive-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
  <small class="text-muted">Posting penerimaan barang purchase ke gudang atau divisi, sekaligus update stok bulanan dan movement log.</small>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'receipt-purchase']); ?>

<div id="alert-area"></div>

<div class="card mb-3">
  <div class="card-body">
    <form id="receipt-form" class="row g-3" autocomplete="off">
      <div class="col-md-4">
        <label class="form-label">Purchase Order</label>
        <select class="form-select" id="purchase_order_id" name="purchase_order_id" required>
          <option value="">Pilih PO...</option>
          <?php foreach (($po_options ?? []) as $po): ?>
            <option value="<?php echo (int)$po['id']; ?>">
              <?php echo html_escape((string)$po['po_no'] . ' | ' . (string)($po['vendor_name'] ?? '-') . ' | ' . (string)$po['status']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Tanggal Receipt</label>
        <input class="form-control" type="date" id="receipt_date" name="receipt_date" value="<?php echo date('Y-m-d'); ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Tujuan Masuk</label>
        <select class="form-select" id="destination_type" name="destination_type" required>
          <option value="GUDANG">GUDANG</option>
          <option value="BAR">BAR</option>
          <option value="KITCHEN">KITCHEN</option>
          <option value="ROASTERY">ROASTERY</option>
          <option value="BAR_EVENT">BAR_EVENT</option>
          <option value="KITCHEN_EVENT">KITCHEN_EVENT</option>
          <option value="ROASTERY_EVENT">ROASTERY_EVENT</option>
          <option value="OFFICE">OFFICE</option>
          <option value="OTHER">OTHER</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Destination Division ID (opsional utk GUDANG)</label>
        <input class="form-control" type="number" min="1" id="destination_division_id" name="destination_division_id" placeholder="Contoh: 3">
      </div>

      <div class="col-12">
        <label class="form-label">Catatan Header</label>
        <input class="form-control" type="text" id="header_notes" name="header_notes" placeholder="Opsional">
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-outline-primary" type="button" id="btn-load-lines">Load Line PO</button>
        <button class="btn btn-primary" type="submit" id="btn-submit">Post Receipt + Update Stok</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h6 class="mb-3">Line PO untuk Diterima</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle" id="line-table">
        <thead>
          <tr>
            <th width="40">#</th>
            <th width="60">Pilih</th>
            <th>Line</th>
            <th>Profile</th>
            <th class="text-end">Qty PO</th>
            <th class="text-end">Qty Sudah Diterima</th>
            <th class="text-end">Qty Sisa</th>
            <th width="180">Qty Diterima Sekarang</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="9" class="text-center text-muted py-4">Pilih PO lalu klik Load Line PO.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  var poLinesUrl = <?php echo json_encode($poLinesUrl); ?>;
  var storeUrl = <?php echo json_encode($storeUrl); ?>;

  var form = document.getElementById('receipt-form');
  var poSelect = document.getElementById('purchase_order_id');
  var destinationType = document.getElementById('destination_type');
  var destinationDivision = document.getElementById('destination_division_id');
  var btnLoad = document.getElementById('btn-load-lines');
  var btnSubmit = document.getElementById('btn-submit');
  var tableBody = document.querySelector('#line-table tbody');
  var alertArea = document.getElementById('alert-area');

  function setAlert(type, message) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + message
      + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
      + '</div>';
  }

  function esc(value) {
    var d = document.createElement('div');
    d.textContent = value == null ? '' : String(value);
    return d.innerHTML;
  }

  function toNum(v) {
    var n = Number(v || 0);
    return Number.isFinite(n) ? n : 0;
  }

  function updateDivisionRequired() {
    var isGudang = String(destinationType.value || '').toUpperCase() === 'GUDANG';
    destinationDivision.required = !isGudang;
  }

  destinationType.addEventListener('change', updateDivisionRequired);
  updateDivisionRequired();

  btnLoad.addEventListener('click', function () {
    var poId = Number(poSelect.value || 0);
    if (!poId) {
      setAlert('warning', 'Silakan pilih Purchase Order terlebih dahulu.');
      return;
    }

    var loadOrigHtml = btnLoad.innerHTML;
    btnLoad.disabled = true;
    btnLoad.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memuat...';
    fetch(poLinesUrl + '?purchase_order_id=' + encodeURIComponent(poId), {
      headers: { 'Accept': 'application/json' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res || !res.ok) {
        throw new Error((res && res.message) ? res.message : 'Gagal load line PO.');
      }

      var items = Array.isArray(res.items) ? res.items : [];
      if (!items.length) {
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Tidak ada line PO.</td></tr>';
        return;
      }

      var rows = [];
      for (var i = 0; i < items.length; i++) {
        var it = items[i] || {};
        var profile = (it.snapshot_item_name || it.snapshot_material_name || '-')
          + (it.snapshot_brand_name ? ' | ' + it.snapshot_brand_name : '')
          + (it.snapshot_line_description ? ' | ' + it.snapshot_line_description : '');

        var qtyPo = toNum(it.qty_buy);
        var qtyRcv = toNum(it.qty_buy_received_total);
        var qtyRemain = toNum(it.qty_buy_remaining);
        var lineLabel = 'Line #' + esc(it.line_no || '-') + ' - ' + esc(it.line_kind || '-');

        rows.push('<tr data-line-id="' + esc(it.purchase_order_line_id) + '">' +
          '<td class="text-center">' + (i + 1) + '</td>' +
          '<td class="text-center"><input type="checkbox" class="form-check-input line-check" ' + (qtyRemain > 0 ? 'checked' : '') + '></td>' +
          '<td>' + lineLabel + '</td>' +
          '<td>' + esc(profile) + '</td>' +
            '<td class="text-end">' + qtyPo.toFixed(2) + '</td>' +
            '<td class="text-end">' + qtyRcv.toFixed(2) + '</td>' +
            '<td class="text-end">' + qtyRemain.toFixed(2) + '</td>' +
            '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm line-qty" value="' + qtyRemain.toFixed(2) + '"></td>' +
          '<td><input type="text" class="form-control form-control-sm line-notes" placeholder="Opsional"></td>' +
          '</tr>');
      }

      tableBody.innerHTML = rows.join('');
      setAlert('success', 'Line PO berhasil dimuat. Pilih line yang ingin diterima, lalu klik Post Receipt.');
    })
    .catch(function (err) {
      setAlert('danger', err.message || 'Gagal load line PO.');
    })
    .finally(function () {
      btnLoad.disabled = false;
      btnLoad.innerHTML = loadOrigHtml;
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var poId = Number(poSelect.value || 0);
    if (!poId) {
      setAlert('warning', 'Purchase Order wajib dipilih.');
      return;
    }

    var selectedLines = [];
    var rows = tableBody.querySelectorAll('tr[data-line-id]');
    rows.forEach(function (tr) {
      var checked = tr.querySelector('.line-check');
      if (!checked || !checked.checked) return;

      var lineId = Number(tr.getAttribute('data-line-id') || 0);
      var qtyInput = tr.querySelector('.line-qty');
      var noteInput = tr.querySelector('.line-notes');
      var qty = toNum(qtyInput ? qtyInput.value : 0);
      if (!lineId || qty <= 0) return;

      selectedLines.push({
        purchase_order_line_id: lineId,
        qty_buy_received: qty,
        notes: noteInput ? noteInput.value : ''
      });
    });

    if (!selectedLines.length) {
      setAlert('warning', 'Tidak ada line valid yang dipilih.');
      return;
    }

    var payload = {
      header: {
        purchase_order_id: poId,
        receipt_date: document.getElementById('receipt_date').value,
        destination_type: destinationType.value,
        destination_division_id: destinationDivision.value || null,
        notes: document.getElementById('header_notes').value || ''
      },
      lines: selectedLines
    };

    var submitOrigHtml = btnSubmit.innerHTML;
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memposting...';
    fetch(storeUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Purchase-Mutation-CSRF': <?php echo json_encode($purchaseMutationCsrfToken); ?>
      },
      body: JSON.stringify(payload)
    })
    .then(function (r) {
      return r.json().then(function (j) {
        return { status: r.status, json: j };
      });
    })
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal posting receipt.');
      }
      var data = res.json.data || {};
      setAlert('success', 'Receipt posted. No: ' + esc(data.receipt_no || '-') + ', line: ' + esc(data.line_count || 0));
      btnLoad.click();
    })
    .catch(function (err) {
      setAlert('danger', err.message || 'Gagal posting receipt.');
    })
    .finally(function () {
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = submitOrigHtml;
    });
  });
})();
</script>
