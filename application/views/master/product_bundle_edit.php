<?php
$bundle = is_array($bundle ?? null) ? $bundle : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$saveUrl = (string)($save_url ?? '#');
$backUrl = (string)($back_url ?? site_url('master/relation/product-bundle'));

$initialLines = [];
foreach ($rows as $row) {
    $initialLines[] = [
        'product_id' => (int)($row['product_id'] ?? 0),
        'product_code' => (string)($row['product_code'] ?? ''),
        'product_name' => (string)($row['product_name'] ?? ''),
        'division_id' => (int)($row['product_division_id'] ?? 0),
        'division_name' => (string)($row['product_division_name'] ?? '-'),
        'selling_price' => round((float)($row['product_selling_price'] ?? 0), 2),
        'uom_code' => (string)($row['uom_code'] ?? $row['uom_name'] ?? ''),
        'qty' => round((float)($row['qty'] ?? 0), 4),
        'unit_price_override' => $row['unit_price_override'] !== null ? round((float)$row['unit_price_override'], 2) : '',
        'sort_order' => (int)($row['sort_order'] ?? 0),
    ];
}
?>

<style>
  .bundle-editor .bundle-summary-card,
  .bundle-editor .bundle-meta-card,
  .bundle-editor .bundle-lines-card {
    border: 1px solid rgba(170, 95, 78, 0.16);
    border-radius: 18px;
    box-shadow: 0 14px 32px rgba(70, 30, 28, 0.08);
  }
  .bundle-editor .bundle-summary-stat {
    padding: .9rem 1rem;
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(255,250,247,.95) 0%, rgba(255,255,255,.98) 100%);
    border: 1px solid rgba(170, 95, 78, 0.14);
  }
  .bundle-editor .bundle-summary-label {
    display: block;
    font-size: .76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8d6f6a;
    margin-bottom: .28rem;
  }
  .bundle-editor .bundle-summary-value {
    font-size: 1.15rem;
    font-weight: 800;
    color: #3f3134;
  }
  .bundle-editor .bundle-meta-card .form-label,
  .bundle-editor .bundle-lines-card .form-label {
    font-size: .76rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7f5d57;
    margin-bottom: .45rem;
  }
  .bundle-editor .bundle-help-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem .8rem;
    border-radius: 999px;
    border: 1px solid rgba(170, 95, 78, 0.18);
    background: rgba(255, 249, 245, 0.95);
    font-size: .84rem;
    color: #6f5953;
  }
  .bundle-editor .bundle-line-row {
    border: 1px solid rgba(170, 95, 78, 0.14);
    border-radius: 16px;
    padding: .95rem;
    background: linear-gradient(180deg, rgba(255,255,255,.96) 0%, rgba(255,250,247,.98) 100%);
  }
  .bundle-editor .bundle-line-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    margin-bottom: .85rem;
  }
  .bundle-editor .bundle-line-index {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-weight: 800;
    color: #5b1f1f;
  }
  .bundle-editor .bundle-line-index .icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(192, 57, 43, 0.1);
    color: #b02a37;
  }
  .bundle-editor .bundle-picker {
    position: relative;
  }
  .bundle-editor .bundle-picker-results {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 8px);
    z-index: 30;
    background: #fff;
    border: 1px solid rgba(170, 95, 78, 0.2);
    border-radius: 14px;
    box-shadow: 0 20px 38px rgba(62, 27, 24, 0.16);
    max-height: 280px;
    overflow: auto;
  }
  .bundle-editor .bundle-picker-item {
    width: 100%;
    border: 0;
    background: transparent;
    padding: .78rem .85rem;
    text-align: left;
    border-bottom: 1px solid rgba(170, 95, 78, 0.1);
  }
  .bundle-editor .bundle-picker-item:last-child {
    border-bottom: 0;
  }
  .bundle-editor .bundle-picker-item:hover,
  .bundle-editor .bundle-picker-item:focus {
    background: rgba(192, 57, 43, 0.06);
  }
  .bundle-editor .bundle-picker-item strong {
    display: block;
    color: #3f3134;
  }
  .bundle-editor .bundle-picker-item small {
    color: #8d6f6a;
  }
  .bundle-editor .bundle-selected-card {
    margin-top: .55rem;
    padding: .75rem .85rem;
    border-radius: 14px;
    border: 1px solid rgba(170, 95, 78, 0.14);
    background: rgba(255, 249, 245, 0.92);
  }
  .bundle-editor .bundle-selected-card .meta {
    font-size: .84rem;
    color: #7d6661;
  }
  .bundle-editor .bundle-inline-note {
    font-size: .8rem;
    color: #8d6f6a;
  }
  .bundle-editor .bundle-total-pill {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    min-height: 48px;
    width: 100%;
    padding: 0 .9rem;
    border-radius: 14px;
    background: rgba(52, 50, 94, 0.06);
    border: 1px solid rgba(52, 50, 94, 0.12);
    font-weight: 800;
    color: #34325e;
  }
  .bundle-editor .btn-add-line-accent {
    background: linear-gradient(180deg, #fff5e8 0%, #ffedd3 100%);
    border: 1px solid #ebb67f;
    color: #9b5817;
    font-weight: 700;
  }
  .bundle-editor .btn-add-line-accent:hover,
  .bundle-editor .btn-add-line-accent:focus {
    background: linear-gradient(180deg, #ffe8c4 0%, #ffd79f 100%);
    border-color: #de9b53;
    color: #7c420d;
  }
  @media (max-width: 767.98px) {
    .bundle-editor .bundle-line-row {
      padding: .85rem;
    }
    .bundle-editor .bundle-line-topbar {
      align-items: flex-start;
      flex-direction: column;
    }
  }
</style>

<div class="bundle-editor container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <div class="fin-breadcrumb">
        <a href="<?php echo site_url('master/product'); ?>">Master Product</a>
        <span>/</span>
        <a href="<?php echo site_url('master/relation/product-bundle'); ?>">Bundle Produk</a>
        <span>/</span>
        <span><?php echo !empty($bundle) ? 'Edit Bundle' : 'Tambah Bundle'; ?></span>
      </div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($title ?? 'Bundle Produk')); ?></h4>
      <p class="fin-page-subtitle mb-0">Editor bundle dibuat fokus pada pengalaman buyer/operator: produk dicari cepat, harga terlihat jelas, dan urutan tampil bisa otomatis jika tidak ingin diatur manual.</p>
    </div>
    <div class="fin-page-actions">
      <a class="btn btn-outline-secondary" href="<?php echo $backUrl; ?>"><i class="ri ri-arrow-left-line me-1"></i>Kembali</a>
    </div>
  </div>

  <div class="card bundle-summary-card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="bundle-summary-stat">
            <span class="bundle-summary-label">Jumlah Line</span>
            <div class="bundle-summary-value" id="sum-line"><?php echo (int)($summary['line_count'] ?? 0); ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="bundle-summary-stat">
            <span class="bundle-summary-label">Total Qty</span>
            <div class="bundle-summary-value" id="sum-qty"><?php echo number_format((float)($summary['line_qty_total'] ?? 0), 2, ',', '.'); ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="bundle-summary-stat">
            <span class="bundle-summary-label">Nilai Isi</span>
            <div class="bundle-summary-value" id="sum-line-value">Rp <?php echo number_format((float)($summary['line_value_total'] ?? 0), 2, ',', '.'); ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="bundle-summary-stat">
            <span class="bundle-summary-label">Harga Bundle</span>
            <div class="bundle-summary-value" id="sum-bundle-price">Rp <?php echo number_format((float)($summary['bundle_price'] ?? 0), 2, ',', '.'); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <form method="post" action="<?php echo $saveUrl; ?>" id="productBundleForm">
    <input type="hidden" name="master_relation_product_bundle_mutation_csrf" value="<?php echo html_escape((string)($master_relation_product_bundle_mutation_csrf ?? '')); ?>">
    <input type="hidden" name="product_bundle_revision" value="<?php echo html_escape((string)($product_bundle_revision ?? '')); ?>">
    <input type="hidden" name="lines_json" id="lines_json" value="">

    <div class="card bundle-meta-card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Kode Bundle</label>
            <input type="text" class="form-control" name="bundle_code" value="<?php echo html_escape((string)($bundle['bundle_code'] ?? '')); ?>" placeholder="Otomatis saat simpan">
          </div>
          <div class="col-md-5">
            <label class="form-label">Nama Bundle</label>
            <input type="text" class="form-control" name="bundle_name" value="<?php echo html_escape((string)($bundle['bundle_name'] ?? '')); ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Scope POS</label>
            <select class="form-select" name="pos_scope">
              <?php foreach (['REGULAR', 'EVENT', 'ALL'] as $scope): ?>
                <option value="<?php echo $scope; ?>" <?php echo (($bundle['pos_scope'] ?? 'REGULAR') === $scope) ? 'selected' : ''; ?>><?php echo $scope; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select class="form-select" name="is_active">
              <option value="1" <?php echo ((int)($bundle['is_active'] ?? 1) === 1) ? 'selected' : ''; ?>>Aktif</option>
              <option value="0" <?php echo ((int)($bundle['is_active'] ?? 1) === 0) ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Cakupan Divisi</label>
            <input type="text" class="form-control" id="bundle_division_label" value="<?php echo html_escape((string)($bundle['product_division_name'] ?? 'Mengikuti line produk')); ?>" readonly>
            <div class="bundle-inline-note mt-1">Bundle boleh campuran lintas divisi, misalnya makanan dan minuman dalam satu paket.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Harga Bundle</label>
            <input type="number" step="0.01" class="form-control" name="selling_price" id="bundle_selling_price" value="<?php echo html_escape((string)($bundle['selling_price'] ?? '0')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Urutan Tampil</label>
            <input type="number" class="form-control" name="sort_order" id="bundle_sort_order" value="<?php echo !empty($bundle) ? html_escape((string)($bundle['sort_order'] ?? '')) : ''; ?>" placeholder="Kosong = otomatis">
            <div class="bundle-inline-note mt-1">Dipakai jika kamu ingin bundle tertentu tampil lebih dulu di daftar POS. Jika kosong, sistem isi otomatis.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Deskripsi</label>
            <textarea class="form-control" rows="2" name="description" placeholder="Catatan singkat paket, positioning, atau highlight promo."><?php echo html_escape((string)($bundle['description'] ?? '')); ?></textarea>
          </div>

          <div class="col-12 pt-1">
            <div class="d-flex flex-wrap gap-2">
              <span class="bundle-help-chip"><i class="ri ri-information-line"></i>Bundle boleh lintas divisi produk, cocok untuk paket makan + minum atau combo promo.</span>
              <span class="bundle-help-chip"><i class="ri ri-search-eye-line"></i>Pilih produk memakai pencarian ajax, jadi tidak ada dropdown panjang yang melelahkan.</span>
              <span class="bundle-help-chip"><i class="ri ri-price-tag-3-line"></i>Kalau override kosong, sistem akan membagi harga jual bundle secara proporsional ke line saat transaksi dan laporan profit.</span>
              <span class="bundle-help-chip"><i class="ri ri-sort-desc"></i>Urutan line boleh kosong, nanti sistem atur otomatis 10, 20, 30, dst.</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card bundle-lines-card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h5 class="mb-1">Line Bundle</h5>
            <div class="text-muted small">Setiap baris mewakili produk yang masuk ke paket. Override harga per baris bersifat opsional.</div>
          </div>
          <div class="d-flex gap-2">
            <input id="line-search" class="form-control" style="min-width:260px;" placeholder="Filter line yang sudah dipilih...">
            <button type="button" id="btn-add-line" class="btn btn-add-line-accent"><i class="ri ri-add-line me-1"></i>Tambah Baris</button>
          </div>
        </div>

        <div id="line-body" class="d-flex flex-column gap-3"></div>

        <div class="d-flex justify-content-end gap-2 pt-3 mt-3 border-top">
          <a class="btn btn-light" href="<?php echo $backUrl; ?>">Batal</a>
          <button type="submit" class="btn btn-primary"><i class="ri ri-save-line me-1"></i>Simpan Bundle</button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
(function () {
  var initialLines = <?php echo json_encode(array_values($initialLines), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var searchUrl = <?php echo json_encode(site_url('master/relation/product-bundle/product-search')); ?>;
  var lineBody = document.getElementById('line-body');
  var lineSearch = document.getElementById('line-search');
  var form = document.getElementById('productBundleForm');
  var linesInput = document.getElementById('lines_json');
  var bundleSellingPrice = document.getElementById('bundle_selling_price');
  var bundleDivisionLabel = document.getElementById('bundle_division_label');
  var bundleSortOrder = document.getElementById('bundle_sort_order');
  var sumLine = document.getElementById('sum-line');
  var sumQty = document.getElementById('sum-qty');
  var sumLineValue = document.getElementById('sum-line-value');
  var sumBundlePrice = document.getElementById('sum-bundle-price');
  var pickerCache = {};
  var searchTimers = {};

  var state = {
    lines: Array.isArray(initialLines) && initialLines.length ? initialLines : [{product_id: 0, product_code: '', product_name: '', division_id: 0, division_name: '', selling_price: 0, uom_code: '', qty: 1, unit_price_override: '', sort_order: ''}]
  };

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fmtMoney(value) {
    return new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0));
  }

  function currentSelectedIds(excludeIndex) {
    return state.lines
      .map(function (line, index) { return index === excludeIndex ? 0 : Number(line.product_id || 0); })
      .filter(function (id) { return id > 0; });
  }

  function lineTotal(line) {
    var unit = line.unit_price_override !== '' && line.unit_price_override !== null && typeof line.unit_price_override !== 'undefined'
      ? Number(line.unit_price_override || 0)
      : Number(line.selling_price || 0);
    return Number(line.qty || 0) * unit;
  }

  function rowMatchesSearch(line) {
    var q = String(lineSearch.value || '').trim().toLowerCase();
    if (!q) return true;
    var hay = [line.product_name, line.product_code, line.division_name, line.uom_code].join(' ').toLowerCase();
    return hay.indexOf(q) !== -1;
  }

  function render() {
    var html = '';
    state.lines.forEach(function (line, index) {
      if (!rowMatchesSearch(line)) return;
      var total = lineTotal(line);
      html += '<div class="bundle-line-row" data-index="' + index + '">';
      html += '  <div class="bundle-line-topbar">';
      html += '    <div class="bundle-line-index"><span class="icon-wrap"><i class="ri ri-gift-2-line"></i></span><span>Produk #' + (index + 1) + '</span></div>';
      html += '    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="ri ri-delete-bin-line me-1"></i>Hapus</button>';
      html += '  </div>';
      html += '  <div class="row g-3">';
      html += '    <div class="col-lg-5">';
      html += '      <label class="form-label">Produk</label>';
      html += '      <div class="bundle-picker">';
      html += '        <input type="text" class="form-control line-product-search" placeholder="Ketik nama / kode produk..." value="' + esc(line.product_name || '') + '" autocomplete="off">';
      html += '        <div class="bundle-picker-results d-none"></div>';
      html += '      </div>';
      if (line.product_id) {
        html += '      <div class="bundle-selected-card"><div class="fw-semibold">' + esc(line.product_name || '-') + '</div><div class="meta">' + esc((line.product_code || '-') + ' | ' + (line.division_name || '-') + ' | ' + (line.uom_code || '-')) + '</div></div>';
      } else {
        html += '      <div class="bundle-inline-note mt-2">Belum ada produk dipilih.</div>';
      }
      html += '    </div>';
      html += '    <div class="col-lg-2 col-md-4">';
      html += '      <label class="form-label">Qty</label>';
      html += '      <input type="number" step="0.0001" class="form-control text-end line-qty" value="' + esc(line.qty || 0) + '">';
      html += '    </div>';
      html += '    <div class="col-lg-2 col-md-4">';
      html += '      <label class="form-label">Harga Dasar</label>';
      html += '      <div class="bundle-total-pill">' + fmtMoney(line.selling_price || 0) + '</div>';
      html += '      <div class="bundle-inline-note mt-1">Dipakai sebagai harga acuan sebelum alokasi bundle.</div>';
      html += '    </div>';
      html += '    <div class="col-lg-2 col-md-4">';
      html += '      <label class="form-label">Override Harga</label>';
      html += '      <input type="number" step="0.01" class="form-control text-end line-price-override" value="' + esc(line.unit_price_override) + '" placeholder="Opsional">';
      html += '    </div>';
      html += '    <div class="col-lg-1 col-md-4">';
      html += '      <label class="form-label">Urutan</label>';
      html += '      <input type="number" class="form-control text-end line-sort" value="' + esc(line.sort_order) + '" placeholder="Auto">';
      html += '    </div>';
      html += '    <div class="col-lg-12">';
      html += '      <div class="row g-3 align-items-end">';
      html += '        <div class="col-md-4"><div class="bundle-inline-note">Divisi: <strong>' + esc(line.division_name || 'Mengikuti produk') + '</strong></div></div>';
      html += '        <div class="col-md-4"><div class="bundle-inline-note">UOM: <strong>' + esc(line.uom_code || '-') + '</strong></div></div>';
      html += '        <div class="col-md-4 text-md-end"><span class="bundle-total-pill">Total ' + fmtMoney(total) + '</span></div>';
      html += '      </div>';
      html += '    </div>';
      html += '  </div>';
      html += '</div>';
    });
    lineBody.innerHTML = html || '<div class="text-center text-muted py-4">Belum ada line bundle yang cocok dengan filter.</div>';
    bindRowEvents();
    updateSummary();
  }

  async function fetchProducts(index, query) {
    var selectedIds = currentSelectedIds(index);
    var key = [query, selectedIds.join(',')].join('|');
    if (pickerCache[key]) {
      return pickerCache[key];
    }
    var params = new URLSearchParams();
    params.set('q', query);
    selectedIds.forEach(function (id) { params.append('selected_ids[]', String(id)); });
    var response = await fetch(searchUrl + '?' + params.toString(), {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    var text = await response.text();
    var json = JSON.parse(text);
    if (!response.ok || !json.ok) {
      throw new Error((json && json.message) || 'Gagal mencari produk.');
    }
    pickerCache[key] = json.results || [];
    return pickerCache[key];
  }

  function closeAllPickers(exceptIndex) {
    lineBody.querySelectorAll('.bundle-line-row').forEach(function (rowEl) {
      var index = Number(rowEl.getAttribute('data-index'));
      if (typeof exceptIndex !== 'undefined' && index === exceptIndex) return;
      var box = rowEl.querySelector('.bundle-picker-results');
      if (box) {
        box.classList.add('d-none');
        box.innerHTML = '';
      }
    });
  }

  function renderPickerResults(rowEl, index, items) {
    var box = rowEl.querySelector('.bundle-picker-results');
    if (!box) return;
    if (!items.length) {
      box.innerHTML = '<div class="p-3 small text-muted">Produk tidak ditemukan.</div>';
      box.classList.remove('d-none');
      return;
    }
    box.innerHTML = items.map(function (item) {
      return '<button type="button" class="bundle-picker-item" data-product=\'' + esc(JSON.stringify(item)) + '\'>'
        + '<strong>' + esc(item.label) + '</strong>'
        + '<small>' + esc((item.product_code || '-') + ' | ' + (item.division_name || '-') + ' | ' + (item.uom_code || '-') + ' | Rp ' + fmtMoney(item.selling_price || 0)) + '</small>'
        + '</button>';
    }).join('');
    box.classList.remove('d-none');
    box.querySelectorAll('.bundle-picker-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var product = JSON.parse(this.getAttribute('data-product'));
        state.lines[index].product_id = Number(product.id || 0);
        state.lines[index].product_code = String(product.product_code || '');
        state.lines[index].product_name = String(product.product_name || product.label || '');
        state.lines[index].division_id = Number(product.division_id || 0);
        state.lines[index].division_name = String(product.division_name || '-');
        state.lines[index].selling_price = Number(product.selling_price || 0);
        state.lines[index].uom_code = String(product.uom_code || '');
        if (String(state.lines[index].sort_order || '').trim() === '') {
          state.lines[index].sort_order = (index + 1) * 10;
        }
        closeAllPickers();
        render();
      });
    });
  }

  function bindRowEvents() {
    lineBody.querySelectorAll('.bundle-line-row').forEach(function (rowEl) {
      var index = Number(rowEl.getAttribute('data-index'));
      var searchInput = rowEl.querySelector('.line-product-search');
      searchInput.addEventListener('input', function () {
        var query = String(this.value || '').trim();
        state.lines[index].product_name = query;
        clearTimeout(searchTimers[index]);
        if (query.length < 2) {
          closeAllPickers(index);
          return;
        }
        searchTimers[index] = setTimeout(function () {
          fetchProducts(index, query).then(function (items) {
            renderPickerResults(rowEl, index, items);
          }).catch(function () {
            renderPickerResults(rowEl, index, []);
          });
        }, 220);
      });
      searchInput.addEventListener('focus', function () {
        if (String(this.value || '').trim().length >= 2) {
          fetchProducts(index, String(this.value || '').trim()).then(function (items) {
            renderPickerResults(rowEl, index, items);
          }).catch(function () {
            renderPickerResults(rowEl, index, []);
          });
        }
      });

      rowEl.querySelector('.line-qty').addEventListener('input', function () {
        state.lines[index].qty = Number(this.value || 0);
        updateSummary();
      });
      rowEl.querySelector('.line-price-override').addEventListener('input', function () {
        state.lines[index].unit_price_override = this.value;
        updateSummary();
      });
      rowEl.querySelector('.line-sort').addEventListener('input', function () {
        state.lines[index].sort_order = this.value;
      });
      rowEl.querySelector('.btn-remove').addEventListener('click', function () {
        state.lines.splice(index, 1);
        if (!state.lines.length) {
          state.lines.push({product_id: 0, product_code: '', product_name: '', division_id: 0, division_name: '', selling_price: 0, uom_code: '', qty: 1, unit_price_override: '', sort_order: ''});
        }
        render();
      });
    });
  }

  function updateSummary() {
    var totalQty = 0;
    var totalValue = 0;
    state.lines.forEach(function (line) {
      totalQty += Number(line.qty || 0);
      totalValue += lineTotal(line);
    });
    sumLine.textContent = state.lines.length;
    sumQty.textContent = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(totalQty);
    sumLineValue.textContent = 'Rp ' + fmtMoney(totalValue);
    sumBundlePrice.textContent = 'Rp ' + fmtMoney(bundleSellingPrice.value || 0);
    var divisionNames = state.lines
      .map(function (line) { return String(line.division_name || '').trim(); })
      .filter(function (name) { return name !== '' && name !== '-'; });
    divisionNames = Array.from(new Set(divisionNames));
    if (!divisionNames.length) {
      bundleDivisionLabel.value = 'Mengikuti line produk';
    } else if (divisionNames.length === 1) {
      bundleDivisionLabel.value = divisionNames[0];
    } else {
      bundleDivisionLabel.value = 'Campuran Divisi';
    }
  }

  document.getElementById('btn-add-line').addEventListener('click', function () {
    state.lines.push({product_id: 0, product_code: '', product_name: '', division_id: 0, division_name: '', selling_price: 0, uom_code: '', qty: 1, unit_price_override: '', sort_order: ''});
    render();
  });

  lineSearch.addEventListener('input', render);
  bundleSellingPrice.addEventListener('input', updateSummary);

  document.addEventListener('click', function (event) {
    if (!event.target.closest('.bundle-picker')) {
      closeAllPickers();
    }
  });

  form.addEventListener('submit', function (e) {
    var validLines = state.lines.filter(function (line) {
      return Number(line.product_id || 0) > 0 && Number(line.qty || 0) > 0;
    }).map(function (line, index) {
      var sortOrder = String(line.sort_order || '').trim() === '' ? ((index + 1) * 10) : Number(line.sort_order || 0);
      return {
        product_id: Number(line.product_id || 0),
        qty: Number(line.qty || 0),
        unit_price_override: line.unit_price_override === '' ? '' : Number(line.unit_price_override || 0),
        sort_order: sortOrder
      };
    });
    if (!validLines.length) {
      e.preventDefault();
      alert('Minimal harus ada 1 line produk di dalam bundle.');
      return;
    }
    if (String(bundleSortOrder.value || '').trim() === '') {
      bundleSortOrder.value = '10';
    }
    linesInput.value = JSON.stringify(validLines);
  });

  render();
})();
</script>
