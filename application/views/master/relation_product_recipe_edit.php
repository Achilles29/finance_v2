<?php
$parent = is_array($parent ?? null) ? $parent : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$options = is_array($options ?? null) ? $options : [];
$productVariableCost = is_array($product_variable_cost ?? null) ? $product_variable_cost : [];
$backUrl = site_url('master/relation/product-recipe/' . (int)($parent['id'] ?? 0));
$saveUrl = site_url('master/relation/product-recipe/edit-all/' . (int)($parent['id'] ?? 0) . '/save');

$initialLines = [];
foreach ($rows as $row) {
    $initialLines[] = [
        'line_type' => (string)($row['line_type'] ?? 'MATERIAL'),
        'ingredient_role' => (string)($row['ingredient_role'] ?? 'MAIN'),
        'material_item_id' => (int)($row['material_item_id'] ?? 0),
        'component_id' => (int)($row['component_id'] ?? 0),
        'source_division_id' => (int)($row['source_division_id'] ?? 0),
        'qty' => round((float)($row['qty'] ?? 0), 4),
        'sort_order' => (int)($row['sort_order'] ?? 0),
        'notes' => (string)($row['notes'] ?? ''),
        'source_option' => [
            'value' => (int)((string)($row['line_type'] ?? 'MATERIAL') === 'COMPONENT' ? ($row['component_id'] ?? 0) : ($row['material_item_id'] ?? 0)),
            'label' => (string)((string)($row['line_type'] ?? 'MATERIAL') === 'COMPONENT' ? ($row['component_name'] ?? '') : ($row['item_name'] ?? '')),
            'meta' => (string)((string)($row['line_type'] ?? 'MATERIAL') === 'COMPONENT'
                ? trim((string)($row['component_operational_division_name'] ?? '-') . ' | ' . (string)($row['uom_name'] ?? '-'))
                : trim((string)($row['resolved_source_division_name'] ?? '-') . ' | ' . (string)($row['uom_name'] ?? '-'))),
            'uom_label' => (string)($row['uom_name'] ?? '-'),
            'source_division_id' => (int)((string)($row['line_type'] ?? 'MATERIAL') === 'COMPONENT'
                ? ($row['component_operational_division_id'] ?? 0)
                : ($row['resolved_source_division_id'] ?? 0)),
            'source_division_name' => (string)((string)($row['line_type'] ?? 'MATERIAL') === 'COMPONENT'
                ? ($row['component_operational_division_name'] ?? '-')
                : ($row['resolved_source_division_name'] ?? '-')),
        ],
    ];
}
?>
<style>
  #recipe-table {
    table-layout: fixed;
    width: 100%;
    min-width: 1180px;
  }
  #recipe-table th,
  #recipe-table td {
    vertical-align: top;
    overflow: visible;
  }
  #recipe-table td {
    position: relative;
  }
  #recipe-table .col-type {
    width: 128px;
  }
  #recipe-table .col-role {
    width: 118px;
  }
  #recipe-table .col-source-division {
    width: 152px;
  }
  #recipe-table .col-source {
    width: 270px;
  }
  #recipe-table .col-qty {
    width: 88px;
  }
  #recipe-table .col-uom {
    width: 82px;
  }
  #recipe-table .col-sort {
    width: 86px;
  }
  #recipe-table .col-notes {
    width: 150px;
  }
  #recipe-table .col-action {
    width: 84px;
  }
  .recipe-ajax-box {
    position: relative;
    min-width: 0;
    z-index: 2;
  }
  .recipe-ajax-box:focus-within {
    z-index: 80;
  }
  .recipe-ajax-result {
    display: none;
  }
  .recipe-ajax-floating {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    z-index: 1085;
    display: none;
    background: #fff;
    border: 1px solid #e2d6cc;
    border-radius: 12px;
    box-shadow: 0 16px 28px rgba(58, 34, 24, .12);
    max-height: 240px;
    overflow: auto;
  }
  .recipe-ajax-floating.is-open {
    display: block;
  }
  .recipe-ajax-item {
    padding: .65rem .8rem;
    border-bottom: 1px solid #f2e8e1;
    cursor: pointer;
  }
  .recipe-ajax-item:last-child {
    border-bottom: 0;
  }
  .recipe-ajax-item:hover {
    background: #fff8f3;
  }
  .recipe-ajax-item-title {
    font-weight: 700;
    color: #423028;
  }
  .recipe-ajax-item-meta {
    font-size: .78rem;
    color: #7a675f;
    margin-top: .15rem;
  }
  .recipe-ajax-selected {
    margin-top: .35rem;
    padding: .45rem .55rem;
    border: 1px solid #eadfd6;
    border-radius: 10px;
    background: #fffaf7;
    min-height: 42px;
  }
  .recipe-ajax-selected.is-empty {
    color: #8e7b72;
  }
  .recipe-ajax-selected-title {
    font-size: .83rem;
    font-weight: 700;
    color: #41312a;
  }
  .recipe-ajax-selected-meta,
  .recipe-ajax-empty {
    font-size: .75rem;
    color: #7d6c64;
    margin-top: .1rem;
  }
  .recipe-table-wrap {
    overflow-x: auto;
    overflow-y: visible;
    padding-bottom: 8rem;
  }
</style>
<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Edit Resep: <?php echo html_escape((string)($parent['product_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Divisi Produk <?php echo html_escape((string)($parent['product_division_name'] ?? '-')); ?> |
        Harga jual Rp <?php echo number_format((float)($summary['selling_price'] ?? 0), 2, ',', '.'); ?> |
        Total HPP live saat ini Rp <?php echo number_format((float)($summary['total_hpp_live'] ?? 0), 2, ',', '.'); ?>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-info btn-sm" href="<?php echo $backUrl; ?>">Detail</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('master/relation/product-recipe'); ?>">Kembali</a>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2">
      <div class="col-md-2"><small class="text-muted d-block">Total Baris</small><strong id="sum-line"><?php echo (int)($summary['line_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Line Material</small><strong id="sum-material"><?php echo (int)($summary['material_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Line Component</small><strong id="sum-component"><?php echo (int)($summary['component_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Direct Live</small><strong>Rp <?php echo number_format((float)($summary['direct_cost_live'] ?? 0), 2, ',', '.'); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Variable Cost</small><strong><?php echo html_escape((string)($productVariableCost['mode'] ?? 'DEFAULT')); ?> / <?php echo number_format((float)($productVariableCost['effective_percent'] ?? 0), 2, ',', '.'); ?>%</strong></div>
      <div class="col-md-2"><small class="text-muted d-block">HPP Live</small><strong>Rp <?php echo number_format((float)($summary['total_hpp_live'] ?? 0), 2, ',', '.'); ?></strong></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="post" action="<?php echo $saveUrl; ?>" id="productRecipeBulkForm">
        <input type="hidden" name="master_relation_product_recipe_mutation_csrf" value="<?php echo html_escape((string)($master_relation_product_recipe_mutation_csrf ?? '')); ?>">
        <input type="hidden" name="product_recipe_revision" value="<?php echo html_escape((string)($product_recipe_revision ?? '')); ?>">
        <input type="hidden" name="lines_json" id="lines_json" value="">

        <div class="row g-2 mb-3">
          <div class="col-12">
            <div class="alert alert-light border py-2 mb-1">
              <strong>Catatan:</strong> source divisi default tetap mengikuti sumber, tetapi di layar ini boleh dioverride per baris jika bahan diambil dari divisi operasional lain. Semua perubahan disimpan sekaligus.
            </div>
          </div>
          <div class="col-md-4">
            <input id="line-search" class="form-control" placeholder="Cari sumber/catatan...">
          </div>
          <div class="col-md-3">
            <select id="line-type-filter" class="form-select">
              <option value="ALL">Semua Baris</option>
              <option value="MATERIAL">MATERIAL</option>
              <option value="COMPONENT">COMPONENT</option>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="button" id="btn-add-line" class="btn btn-outline-secondary">Tambah Baris</button>
          </div>
          <div class="col-md-3 d-grid">
            <button type="submit" id="btn-save" class="btn btn-primary">Simpan Resep</button>
          </div>
        </div>

        <div class="table-responsive recipe-table-wrap">
          <table class="table table-sm align-middle" id="recipe-table">
            <colgroup>
              <col class="col-type">
              <col class="col-role">
              <col class="col-source-division">
              <col class="col-source">
              <col class="col-qty">
              <col class="col-uom">
              <col class="col-sort">
              <col class="col-notes">
              <col class="col-action">
            </colgroup>
            <thead>
              <tr>
                <th class="col-type">Tipe</th>
                <th class="col-role">Role</th>
                <th class="col-source-division">Source Divisi</th>
                <th class="col-source">Sumber</th>
                <th class="col-qty">Qty</th>
                <th class="col-uom">UOM</th>
                <th class="col-sort">Sort</th>
                <th class="col-notes">Catatan</th>
                <th class="col-action"></th>
              </tr>
            </thead>
            <tbody id="line-body"></tbody>
          </table>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var sourceDivisions = <?php echo json_encode(array_values($options['source_divisions'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var ingredientRoles = <?php echo json_encode(array_values($options['ingredient_roles'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var initialLines = <?php echo json_encode(array_values($initialLines), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var defaultSourceDivisionName = <?php echo json_encode((string)($options['default_source_division_name'] ?? '-')); ?>;
  var defaultSourceDivisionId = <?php echo json_encode((int)($options['default_source_division_id'] ?? 0)); ?>;
  var sourceLookupUrl = <?php echo json_encode(site_url('master/relation/product-recipe/source-lookup/' . (int)($parent['id'] ?? 0))); ?>;
  var lineBody = document.getElementById('line-body');
  var lineSearch = document.getElementById('line-search');
  var lineTypeFilter = document.getElementById('line-type-filter');
  var linesInput = document.getElementById('lines_json');
  var form = document.getElementById('productRecipeBulkForm');
  var btnAddLine = document.getElementById('btn-add-line');
  var sumLine = document.getElementById('sum-line');
  var sumMaterial = document.getElementById('sum-material');
  var sumComponent = document.getElementById('sum-component');
  var lookupTimer = null;
  var activeLookupIndex = null;
  var floatingLookup = document.createElement('div');
  floatingLookup.className = 'recipe-ajax-floating';
  document.body.appendChild(floatingLookup);

  var state = {
    lines: Array.isArray(initialLines) && initialLines.length ? initialLines : [emptyLine(10)]
  };

  function emptyLine(sortOrder) {
    return {
      line_type: 'MATERIAL',
      ingredient_role: 'MAIN',
      material_item_id: 0,
      component_id: 0,
      source_division_id: defaultSourceDivisionId || 0,
      qty: 1,
      sort_order: Number(sortOrder || 10),
      notes: '',
      source_option: null
    };
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function findOption(list, value) {
    var num = Number(value || 0);
    return list.find(function (item) { return Number(item.value || 0) === num; }) || null;
  }

  function currentSourceId(line) {
    return (line.line_type || 'MATERIAL') === 'COMPONENT'
      ? Number(line.component_id || 0)
      : Number(line.material_item_id || 0);
  }

  function normalizeSourceOption(option) {
    if (!option || Number(option.value || 0) <= 0) {
      return null;
    }
    return {
      value: Number(option.value || 0),
      label: String(option.label || ''),
      meta: String(option.meta || ''),
      uom_label: String(option.uom_label || '-'),
      source_division_id: Number(option.source_division_id || 0),
      source_division_name: String(option.source_division_name || '-')
    };
  }

  function setLineSource(line, option) {
    var normalized = normalizeSourceOption(option);
    if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
      line.component_id = normalized ? Number(normalized.value || 0) : 0;
      line.material_item_id = 0;
    } else {
      line.material_item_id = normalized ? Number(normalized.value || 0) : 0;
      line.component_id = 0;
    }
    line.source_option = normalized;
  }

  function ingredientRoleOptionsHtml(selected) {
    var current = String(selected || 'MAIN').toUpperCase();
    var html = '';
    ingredientRoles.forEach(function (opt) {
      var value = String(opt.value || 'MAIN').toUpperCase();
      var isSelected = value === current ? ' selected' : '';
      html += '<option value="' + esc(value) + '"' + isSelected + '>' + esc(opt.label || '-') + '</option>';
    });
    return html;
  }

  function sourceDivisionOptionsHtml(selected) {
    var current = Number(selected || 0);
    var html = '<option value="">- pilih divisi -</option>';
    sourceDivisions.forEach(function (opt) {
      var isSelected = Number(opt.value || 0) === current ? ' selected' : '';
      html += '<option value="' + esc(opt.value) + '"' + isSelected + '>' + esc(opt.label || '-') + '</option>';
    });
    return html;
  }

  function suggestedSourceDivisionId(line) {
    if ((line.line_type || 'MATERIAL') === 'COMPONENT' && line.source_option && Number(line.source_option.source_division_id || 0) > 0) {
      return Number(line.source_option.source_division_id || 0);
    }
    if ((line.line_type || 'MATERIAL') === 'MATERIAL' && line.source_option && Number(line.source_option.source_division_id || 0) > 0) {
      return Number(line.source_option.source_division_id || 0);
    }
    if (Number(line.source_division_id || 0) > 0) {
      return Number(line.source_division_id || 0);
    }
    if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
      return Number(defaultSourceDivisionId || 0);
    }
    return Number(defaultSourceDivisionId || 0);
  }

  function lineMeta(line) {
    var resolvedDivisionId = Number(line.source_division_id || 0);
    var sourceDivision = findOption(sourceDivisions, resolvedDivisionId);
    var sourceOption = normalizeSourceOption(line.source_option);
    return {
      sourceDivisionName: sourceDivision
        ? String(sourceDivision.label || '-')
        : (sourceOption ? String(sourceOption.source_division_name || '-') : (defaultSourceDivisionName || '-')),
      uomLabel: sourceOption ? String(sourceOption.uom_label || '-') : '-',
      searchText: [sourceOption ? String(sourceOption.label || '') : '', sourceOption ? String(sourceOption.meta || '') : '', String(line.notes || '')].join(' ')
    };
  }

  function sourceLookupPlaceholder(line) {
    return (line.line_type || 'MATERIAL') === 'COMPONENT'
      ? 'Cari component sumber...'
      : 'Cari bahan baku sumber...';
  }

  function sourcePreviewHtml(line) {
    var sourceOption = normalizeSourceOption(line.source_option);
    if (!sourceOption) {
      return '<div class="recipe-ajax-empty">Belum ada sumber dipilih.</div>';
    }
    return '' +
      '<div class="recipe-ajax-selected-title">' + esc(sourceOption.label || '-') + '</div>' +
      '<div class="recipe-ajax-selected-meta">' + esc(sourceOption.meta || '-') + '</div>';
  }

  function closeSourceLookupResults() {
    activeLookupIndex = null;
    floatingLookup.classList.remove('is-open');
    floatingLookup.innerHTML = '';
    floatingLookup.style.left = '0px';
    floatingLookup.style.top = '0px';
    floatingLookup.style.width = '280px';
  }

  function positionLookupResult(box) {
    if (!box) {
      return;
    }
    var input = box.querySelector('input[data-field="source_lookup"]');
    if (!input) {
      return;
    }
    var rect = input.getBoundingClientRect();
    floatingLookup.style.left = Math.max(12, rect.left) + 'px';
    floatingLookup.style.top = Math.max(12, rect.bottom + 4) + 'px';
    floatingLookup.style.width = Math.max(260, rect.width) + 'px';
  }

  function getJson(url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (response) {
        return response.text().then(function (text) {
          var json = null;
          try {
            json = JSON.parse(text);
          } catch (err) {
            throw new Error('Response sumber recipe tidak valid.');
          }
          if (!response.ok || !json.ok) {
            throw new Error(json.message || 'Gagal memuat sumber recipe.');
          }
          return json;
        });
      });
  }

  function fetchSourceLookupRows(line, query, id) {
    var params = new URLSearchParams();
    params.set('line_type', String(line.line_type || 'MATERIAL'));
    params.set('source_division_id', String(line.source_division_id || suggestedSourceDivisionId(line) || 0));
    if (Number(id || 0) > 0) {
      params.set('id', String(Number(id || 0)));
    } else {
      params.set('q', String(query || ''));
    }
    return getJson(sourceLookupUrl + '?' + params.toString()).then(function (json) {
      return Array.isArray(json.rows) ? json.rows : [];
    });
  }

  function renderSourceLookupResults(box, index, rows) {
    activeLookupIndex = index;
    positionLookupResult(box);
    if (!Array.isArray(rows) || !rows.length) {
      floatingLookup.innerHTML = '<div class="recipe-ajax-item"><div class="recipe-ajax-item-title">Tidak ada hasil</div><div class="recipe-ajax-item-meta">Coba kata kunci lain.</div></div>';
      floatingLookup.classList.add('is-open');
      return;
    }
    floatingLookup.innerHTML = rows.map(function (row) {
      return '' +
        '<div class="recipe-ajax-item" data-action="pick-source" data-index="' + index + '" data-value="' + esc(row.value) + '" data-label="' + esc(row.label || '') + '" data-meta="' + esc(row.meta || '') + '" data-uom-label="' + esc(row.uom_label || '-') + '" data-source-division-id="' + esc(row.source_division_id || 0) + '" data-source-division-name="' + esc(row.source_division_name || '-') + '">' +
          '<div class="recipe-ajax-item-title">' + esc(row.label || '-') + '</div>' +
          '<div class="recipe-ajax-item-meta">' + esc(row.meta || '-') + '</div>' +
        '</div>';
    }).join('');
    floatingLookup.classList.add('is-open');
  }

  function recalcSummary() {
    var materialCount = 0;
    var componentCount = 0;
    state.lines.forEach(function (line) {
      if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
        componentCount += 1;
      } else {
        materialCount += 1;
      }
    });
    sumLine.textContent = String(state.lines.length);
    sumMaterial.textContent = String(materialCount);
    sumComponent.textContent = String(componentCount);
  }

  function render() {
    var keyword = String(lineSearch ? lineSearch.value : '').trim().toLowerCase();
    var typeFilter = String(lineTypeFilter ? lineTypeFilter.value : 'ALL');
    lineBody.innerHTML = '';

    state.lines.forEach(function (line, index) {
      var meta = lineMeta(line);
      var haystack = meta.searchText.toLowerCase();
      var matchesKeyword = keyword === '' || haystack.indexOf(keyword) !== -1;
      var matchesType = typeFilter === 'ALL' || typeFilter === String(line.line_type || 'MATERIAL');
      if (!matchesKeyword || !matchesType) {
        return;
      }

      var row = document.createElement('tr');
      row.innerHTML = '' +
        '<td>' +
          '<select class="form-select form-select-sm" data-field="line_type" data-index="' + index + '">' +
            '<option value="MATERIAL"' + ((line.line_type || 'MATERIAL') === 'MATERIAL' ? ' selected' : '') + '>MATERIAL</option>' +
            '<option value="COMPONENT"' + ((line.line_type || 'MATERIAL') === 'COMPONENT' ? ' selected' : '') + '>COMPONENT</option>' +
          '</select>' +
        '</td>' +
        '<td><select class="form-select form-select-sm" data-field="ingredient_role" data-index="' + index + '">' + ingredientRoleOptionsHtml(line.ingredient_role || 'MAIN') + '</select></td>' +
        '<td><select class="form-select form-select-sm" data-field="source_division_id" data-index="' + index + '">' + sourceDivisionOptionsHtml(line.source_division_id || suggestedSourceDivisionId(line)) + '</select></td>' +
        '<td>' +
          '<div class="recipe-ajax-box" data-index="' + index + '">' +
            '<input type="text" class="form-control form-control-sm" data-field="source_lookup" data-index="' + index + '" value="' + esc((line.source_option && line.source_option.label) ? line.source_option.label : '') + '" placeholder="' + esc(sourceLookupPlaceholder(line)) + '" autocomplete="off">' +
            '<div class="recipe-ajax-result"></div>' +
            '<div class="recipe-ajax-selected' + (normalizeSourceOption(line.source_option) ? '' : ' is-empty') + '">' + sourcePreviewHtml(line) + '</div>' +
          '</div>' +
        '</td>' +
        '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" data-field="qty" data-index="' + index + '" value="' + esc(line.qty || 0) + '"></td>' +
        '<td><input type="text" class="form-control form-control-sm" value="' + esc(meta.uomLabel) + '" readonly></td>' +
        '<td><input type="number" class="form-control form-control-sm" data-field="sort_order" data-index="' + index + '" value="' + esc(line.sort_order || ((index + 1) * 10)) + '"></td>' +
        '<td><input type="text" class="form-control form-control-sm" data-field="notes" data-index="' + index + '" value="' + esc(line.notes || '') + '"></td>' +
        '<td><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove" data-index="' + index + '">Hapus</button></td>';
      lineBody.appendChild(row);
    });

    recalcSummary();
  }

  lineBody.addEventListener('change', function (event) {
    var target = event.target;
    if (!target || !target.dataset) {
      return;
    }
    var index = Number(target.dataset.index || -1);
    if (index < 0 || !state.lines[index]) {
      return;
    }

    if (target.dataset.field === 'line_type') {
      state.lines[index].line_type = target.value === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL';
      setLineSource(state.lines[index], null);
      state.lines[index].source_division_id = Number(state.lines[index].source_division_id || suggestedSourceDivisionId(state.lines[index]) || defaultSourceDivisionId || 0);
      render();
      return;
    }

    if (target.dataset.field === 'source_division_id') {
      state.lines[index].source_division_id = Number(target.value || 0);
      if ((state.lines[index].line_type || 'MATERIAL') === 'COMPONENT' && state.lines[index].source_option && Number(state.lines[index].source_option.source_division_id || 0) !== Number(state.lines[index].source_division_id || 0)) {
        setLineSource(state.lines[index], null);
      }
      render();
      return;
    }

    if (target.dataset.field === 'ingredient_role') {
      state.lines[index].ingredient_role = String(target.value || 'MAIN').toUpperCase();
      return;
    }

    if (target.dataset.field === 'qty') {
      state.lines[index].qty = Number(target.value || 0);
      return;
    }
    if (target.dataset.field === 'sort_order') {
      state.lines[index].sort_order = Number(target.value || 0);
      return;
    }
    if (target.dataset.field === 'notes') {
      state.lines[index].notes = target.value || '';
    }
  });

  lineBody.addEventListener('focusin', function (event) {
    var target = event.target;
    if (!target || !target.dataset || target.dataset.field !== 'source_lookup') {
      return;
    }
    var index = Number(target.dataset.index || -1);
    if (index < 0 || !state.lines[index]) {
      return;
    }
    var box = target.closest('.recipe-ajax-box');
    closeSourceLookupResults();
    fetchSourceLookupRows(state.lines[index], target.value || '', currentSourceId(state.lines[index]))
      .then(function (rows) { renderSourceLookupResults(box, index, rows); })
      .catch(function () { renderSourceLookupResults(box, index, []); });
  });

  lineBody.addEventListener('input', function (event) {
    var target = event.target;
    if (!target || !target.dataset || target.dataset.field !== 'source_lookup') {
      return;
    }
    var index = Number(target.dataset.index || -1);
    if (index < 0 || !state.lines[index]) {
      return;
    }
    setLineSource(state.lines[index], null);
    var box = target.closest('.recipe-ajax-box');
    clearTimeout(lookupTimer);
    lookupTimer = window.setTimeout(function () {
      fetchSourceLookupRows(state.lines[index], target.value || '', 0)
        .then(function (rows) { renderSourceLookupResults(box, index, rows); })
        .catch(function () { renderSourceLookupResults(box, index, []); });
    }, 220);
  });

  lineBody.addEventListener('click', function (event) {
    var button = event.target.closest('button[data-action="remove"]');
    if (!button) {
      return;
    }
    var index = Number(button.dataset.index || -1);
    if (index < 0) {
      return;
    }
    state.lines.splice(index, 1);
    if (!state.lines.length) {
      state.lines.push(emptyLine(10));
    }
    render();
  });

  btnAddLine.addEventListener('click', function () {
    state.lines.push(emptyLine((state.lines.length + 1) * 10));
    render();
  });

  document.addEventListener('click', function (event) {
    var pickSource = event.target.closest('[data-action="pick-source"]');
    if (pickSource) {
      var sourceIndex = Number(pickSource.dataset.index || -1);
      if (sourceIndex >= 0 && state.lines[sourceIndex]) {
        var option = {
          value: Number(pickSource.dataset.value || 0),
          label: String(pickSource.dataset.label || ''),
          meta: String(pickSource.dataset.meta || ''),
          uom_label: String(pickSource.dataset.uomLabel || '-'),
          source_division_id: Number(pickSource.dataset.sourceDivisionId || 0),
          source_division_name: String(pickSource.dataset.sourceDivisionName || '-')
        };
        setLineSource(state.lines[sourceIndex], option);
        if (Number(state.lines[sourceIndex].source_division_id || 0) <= 0) {
          state.lines[sourceIndex].source_division_id = Number(option.source_division_id || suggestedSourceDivisionId(state.lines[sourceIndex]) || defaultSourceDivisionId || 0);
        }
        closeSourceLookupResults();
        render();
      }
      return;
    }
    if (!event.target.closest('.recipe-ajax-box') && !event.target.closest('.recipe-ajax-floating')) {
      closeSourceLookupResults();
    }
  });

  window.addEventListener('resize', function () {
    if (activeLookupIndex === null) {
      return;
    }
    var box = document.querySelector('.recipe-ajax-box[data-index="' + activeLookupIndex + '"]');
    if (box) {
      positionLookupResult(box);
    }
  });

  window.addEventListener('scroll', function () {
    if (activeLookupIndex === null) {
      return;
    }
    var box = document.querySelector('.recipe-ajax-box[data-index="' + activeLookupIndex + '"]');
    if (box) {
      positionLookupResult(box);
    }
  }, true);

  if (lineSearch) {
    lineSearch.addEventListener('input', render);
  }
  if (lineTypeFilter) {
    lineTypeFilter.addEventListener('change', render);
  }

  form.addEventListener('submit', function (event) {
    var cleaned = state.lines.filter(function (line) {
      if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
        return Number(line.component_id || 0) > 0 && Number(line.qty || 0) > 0;
      }
      return Number(line.material_item_id || 0) > 0 && Number(line.qty || 0) > 0;
    }).map(function (line) {
      return {
        line_type: (line.line_type || 'MATERIAL') === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL',
        ingredient_role: String(line.ingredient_role || 'MAIN').toUpperCase(),
        material_item_id: Number(line.material_item_id || 0),
        component_id: Number(line.component_id || 0),
        source_division_id: Number(line.source_division_id || suggestedSourceDivisionId(line) || 0),
        qty: Number(line.qty || 0),
        sort_order: Number(line.sort_order || 0),
        notes: String(line.notes || '')
      };
    });

    if (!cleaned.length) {
      event.preventDefault();
      window.alert('Minimal harus ada 1 line resep produk yang valid.');
      return;
    }

    linesInput.value = JSON.stringify(cleaned);
  });

  render();
})();
</script>
