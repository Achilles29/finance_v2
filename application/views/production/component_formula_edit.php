<?php
$detail = is_array($detail ?? null) ? $detail : [];
$component = is_array($detail['component'] ?? null) ? $detail['component'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
$lines = is_array($detail['lines'] ?? null) ? $detail['lines'] : [];
$materials = is_array($materials ?? null) ? $materials : [];
$components = is_array($components ?? null) ? $components : [];
$source_divisions = is_array($source_divisions ?? null) ? $source_divisions : [];
$production_component_formula_mutation_csrf = (string)($production_component_formula_mutation_csrf ?? '');
$component_formula_revision = (string)($component_formula_revision ?? '');
?>
<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Edit Formula: <?php echo html_escape((string)($component['component_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0"><?php echo html_escape((string)($component['component_type'] ?? '-')); ?> • Hasil 1x produksi <?php echo number_format((float)($summary['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-info btn-sm" href="<?php echo site_url('production/component-formulas/detail/' . (int)($component['id'] ?? 0)); ?>">Detail</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-formulas'); ?>">Kembali</a>
    </div>
  </div>

  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'formula']); ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2">
      <div class="col-md-2"><small class="text-muted d-block">Total Baris</small><strong id="sum-line"><?php echo (int)($summary['line_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Line Material</small><strong id="sum-material"><?php echo (int)($summary['material_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Line Component</small><strong id="sum-component"><?php echo (int)($summary['component_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Hasil 1x Produksi</small><strong><?php echo number_format((float)($summary['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Direct Std</small><strong><?php echo number_format((float)($summary['direct_cost_standard'] ?? 0), 2, ',', '.'); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Direct Live</small><strong><?php echo number_format((float)($summary['direct_cost_live'] ?? 0), 2, ',', '.'); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Total COGS Live</small><strong><?php echo number_format((float)($summary['total_cogs_live'] ?? 0), 2, ',', '.'); ?></strong></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-12">
          <div class="alert alert-light border py-2 mb-1">
            <strong>Catatan:</strong> UOM otomatis mengikuti sumber. Kolom <strong>Divisi Sumber</strong> menentukan dari divisi mana bahan dikurangi saat batch produksi — kosongkan untuk pakai divisi default component.
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
          <button type="button" id="btn-save" class="btn btn-primary">Simpan Formula</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle" id="formula-table">
          <thead>
            <tr>
              <th style="width:140px;">Tipe</th>
              <th style="width:152px;">Divisi Sumber</th>
              <th style="width:360px;">Sumber</th>
              <th style="width:130px;">Qty</th>
              <th style="width:100px;">UOM</th>
              <th>Catatan</th>
              <th style="width:90px;"></th>
            </tr>
          </thead>
          <tbody id="line-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const componentId = <?php echo (int)($component['id'] ?? 0); ?>;
  const componentFormulaMutationCsrf = <?php echo json_encode($production_component_formula_mutation_csrf); ?>;
  const componentFormulaRevision = <?php echo json_encode($component_formula_revision); ?>;
  const seedLines = <?php echo json_encode($lines, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const sourceDivisions = <?php echo json_encode(array_values($source_divisions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const body = document.getElementById('line-body');
  const searchEl = document.getElementById('line-search');
  const filterTypeEl = document.getElementById('line-type-filter');
  let activeSourceRow = null;
  const sourceOverlay = document.createElement('div');
  sourceOverlay.className = 'list-group d-none border rounded-2 bg-white shadow-sm';
  sourceOverlay.style.position = 'fixed';
  sourceOverlay.style.zIndex = '4000';
  sourceOverlay.style.maxHeight = '240px';
  sourceOverlay.style.overflow = 'auto';
  document.body.appendChild(sourceOverlay);

  function esc(v) { return String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  function sourceDivisionOptionsHtml(selected) {
    const current = Number(selected || 0);
    let html = '<option value="0">- default -</option>';
    sourceDivisions.forEach(function (opt) {
      const sel = Number(opt.value || 0) === current ? ' selected' : '';
      html += `<option value="${esc(String(opt.value))}"${sel}>${esc(opt.label || '-')}</option>`;
    });
    return html;
  }

  function rowTemplate(line = {}) {
    const t = String(line.line_type || 'MATERIAL').toUpperCase();
    const sourceName = t === 'MATERIAL' ? (line.material_name || '') : (line.sub_component_name || '');
    const sourceId = t === 'MATERIAL' ? Number(line.material_id || 0) : Number(line.sub_component_id || 0);
    const materialItemId = t === 'MATERIAL' ? Number(line.material_item_id || 0) : 0;
    const uomLabel = esc(line.uom_code || '-');
    const sourceDivId = Number(line.source_division_id || 0);
    return `
      <tr data-source-uom="${uomLabel}">
        <td>
          <select class="form-select form-select-sm line-type">
            <option value="MATERIAL" ${t==='MATERIAL'?'selected':''}>MATERIAL</option>
            <option value="COMPONENT" ${t==='COMPONENT'?'selected':''}>COMPONENT</option>
          </select>
        </td>
        <td>
          <select class="form-select form-select-sm line-source-division">
            ${sourceDivisionOptionsHtml(sourceDivId)}
          </select>
        </td>
        <td>
          <div class="position-relative">
            <input class="form-control form-control-sm line-source-name" placeholder="Ketik nama sumber..." value="${esc(sourceName)}" autocomplete="off">
            <input class="line-source-id" type="hidden" value="${sourceId}">
            <input class="line-material-item-id" type="hidden" value="${materialItemId}">
          </div>
        </td>
        <td><input class="form-control form-control-sm line-qty" type="number" min="0" step="0.01" value="${Number(line.qty||0).toFixed(2)}"></td>
        <td><span class="badge bg-secondary-subtle text-secondary-emphasis line-uom-view">${uomLabel}</span></td>
        <td><input class="form-control form-control-sm line-notes" value="${esc(line.notes||'')}"></td>
        <td class="component-action-cell"><button type="button" class="btn btn-outline-danger action-icon-btn component-action-btn js-del" title="Hapus baris" aria-label="Hapus baris"><i class="ri ri-delete-bin-line"></i></button></td>
      </tr>`;
  }

  async function searchSource(lineType, q, sourceDivisionId) {
    const p = new URLSearchParams();
    p.set('line_type', lineType);
    p.set('q', q || '');
    p.set('component_id', String(componentId));
    p.set('limit', '20');
    if (Number(sourceDivisionId || 0) > 0) {
      p.set('source_division_id', String(Number(sourceDivisionId)));
    }
    const r = await fetch('<?php echo site_url('production/component-formulas/source-search'); ?>?' + p.toString(), {headers: {'X-Requested-With':'XMLHttpRequest'}});
    const t = await r.text();
    let j = {};
    try { j = JSON.parse(t); } catch (e) { return []; }
    if (!r.ok || !j.ok) return [];
    return Array.isArray(j.rows) ? j.rows : [];
  }

  function placeSourceBox(tr) {
    const input = tr.querySelector('.line-source-name');
    if (!input) return;
    const rect = input.getBoundingClientRect();
    sourceOverlay.style.left = rect.left + 'px';
    sourceOverlay.style.top = (rect.bottom + 2) + 'px';
    sourceOverlay.style.width = rect.width + 'px';
  }

  function assignRowKeys() {
    body.querySelectorAll('tr').forEach((tr, idx) => {
      tr.setAttribute('data-row-key', 'row-' + idx);
    });
  }

  function refreshUomTag(tr) {
    const selectedUom = tr.getAttribute('data-source-uom') || '-';
    tr.querySelector('.line-uom-view').textContent = selectedUom;
  }

  function syncType(tr, resetSource) {
    if (resetSource) {
      tr.querySelector('.line-source-name').value = '';
      tr.querySelector('.line-source-id').value = '0';
      tr.querySelector('.line-material-item-id').value = '0';
      tr.setAttribute('data-source-uom', '-');
    }
    refreshUomTag(tr);
  }

  function renderSeed() {
    const rows = Array.isArray(seedLines) && seedLines.length ? seedLines : [{line_type:'MATERIAL', qty:1}];
    body.innerHTML = rows.map((r) => rowTemplate(r)).join('');
    assignRowKeys();
    body.querySelectorAll('tr').forEach((tr) => syncType(tr, false));
  }

  function applyFilter() {
    const q = (searchEl.value || '').toLowerCase();
    const t = filterTypeEl.value;
    body.querySelectorAll('tr').forEach((tr) => {
      const rowType = tr.querySelector('.line-type').value;
      const src = (tr.querySelector('.line-source-name')?.value || '').toLowerCase();
      const notes = (tr.querySelector('.line-notes').value || '').toLowerCase();
      const showType = (t === 'ALL' || rowType === t);
      const showQ = (q === '' || src.includes(q) || notes.includes(q));
      tr.classList.toggle('d-none', !(showType && showQ));
    });
  }

  function collectLines() {
    const lines = [];
    let materialCount = 0;
    let componentCount = 0;
    body.querySelectorAll('tr').forEach((tr, idx) => {
      const lineType = tr.querySelector('.line-type').value;
      const sourceDivId = Number(tr.querySelector('.line-source-division')?.value || 0);
      const row = {
        line_type: lineType,
        source_division_id: sourceDivId > 0 ? sourceDivId : 0,
        qty: Number(tr.querySelector('.line-qty').value || 0),
        notes: tr.querySelector('.line-notes').value || '',
        sort_order: idx
      };
      if (lineType === 'MATERIAL') {
        row.material_id = Number(tr.querySelector('.line-source-id').value || 0);
        row.material_item_id = Number(tr.querySelector('.line-material-item-id').value || 0);
        materialCount += 1;
      } else {
        row.sub_component_id = Number(tr.querySelector('.line-source-id').value || 0);
        componentCount += 1;
      }
      lines.push(row);
    });
    document.getElementById('sum-line').textContent = String(lines.length);
    document.getElementById('sum-material').textContent = String(materialCount);
    document.getElementById('sum-component').textContent = String(componentCount);
    return lines;
  }

  async function postJson(url, payload) {
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-Production-Component-Formula-Csrf':componentFormulaMutationCsrf}, body: JSON.stringify(payload) });
    const t = await r.text();
    let j; try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Request gagal');
    return j;
  }

  function setButtonBusy(button, label) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.disabled = true;
  }

  function clearButtonBusy(button) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button);
      return;
    }
    button.disabled = false;
  }

  body.addEventListener('change', (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.classList.contains('line-type')) syncType(tr, true);
    applyFilter();
  });
  body.addEventListener('input', async (e) => {
    if (!e.target.classList.contains('line-source-name')) return;
    const tr = e.target.closest('tr');
    activeSourceRow = tr;
    const keyword = (e.target.value || '').trim();
    tr.querySelector('.line-source-id').value = '0';
    tr.querySelector('.line-material-item-id').value = '0';
    tr.setAttribute('data-source-uom', '-');
    refreshUomTag(tr);
    if (keyword.length < 2) {
      sourceOverlay.classList.add('d-none');
      sourceOverlay.innerHTML = '';
      return;
    }
    const lineType = tr.querySelector('.line-type').value;
    const sourceDivId = Number(tr.querySelector('.line-source-division')?.value || 0);
    const rows = await searchSource(lineType, keyword, sourceDivId);
    sourceOverlay.innerHTML = rows.map((r) => `<button type="button" class="list-group-item list-group-item-action py-1 js-pick-source bg-white" data-id="${Number(r.id||0)}" data-name="${esc(r.name||'')}" data-uom="${esc(r.uom_code||'-')}">${esc((r.name||'-') + ' (' + (r.uom_code||'-') + ')')}</button>`).join('');
    placeSourceBox(tr);
    sourceOverlay.classList.toggle('d-none', rows.length === 0);
  });
  document.addEventListener('click', (e) => {
    const pick = e.target.closest('.js-pick-source');
    if (pick) {
      const tr = activeSourceRow;
      if (!tr) return;
      tr.querySelector('.line-source-id').value = String(Number(pick.getAttribute('data-id') || 0));
      tr.querySelector('.line-source-name').value = pick.getAttribute('data-name') || '';
      tr.setAttribute('data-source-uom', pick.getAttribute('data-uom') || '-');
      sourceOverlay.classList.add('d-none');
      sourceOverlay.innerHTML = '';
      refreshUomTag(tr);
      return;
    }
    if (!e.target.closest('.line-source-name')) {
      sourceOverlay.classList.add('d-none');
    }
    const b = e.target.closest('.js-del');
    if (!b) return;
    b.closest('tr')?.remove();
    if (body.querySelectorAll('tr').length === 0) body.insertAdjacentHTML('beforeend', rowTemplate({line_type:'MATERIAL',qty:1}));
    assignRowKeys();
    body.querySelectorAll('tr').forEach((tr) => syncType(tr, false));
    applyFilter();
  });

  document.getElementById('btn-add-line').addEventListener('click', () => {
    body.insertAdjacentHTML('beforeend', rowTemplate({line_type:'MATERIAL', qty:1}));
    assignRowKeys();
    body.querySelectorAll('tr').forEach((tr) => syncType(tr, false));
    applyFilter();
  });

  window.addEventListener('resize', () => {
    if (activeSourceRow && !sourceOverlay.classList.contains('d-none')) placeSourceBox(activeSourceRow);
  });

  window.addEventListener('scroll', () => {
    if (activeSourceRow && !sourceOverlay.classList.contains('d-none')) placeSourceBox(activeSourceRow);
  }, true);

  searchEl.addEventListener('input', applyFilter);
  filterTypeEl.addEventListener('change', applyFilter);

  document.getElementById('btn-save').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const lines = collectLines();
    setButtonBusy(button, 'Menyimpan...');
    try {
      await postJson('<?php echo site_url('production/component-formulas/save-bulk'); ?>', {
        component_id: componentId,
        component_formula_revision: componentFormulaRevision,
        lines
      });
      alert('Formula berhasil disimpan.');
      window.location.reload();
    } catch (err) {
      alert(err.message || 'Gagal simpan formula.');
      clearButtonBusy(button);
    }
  });

  renderSeed();
  body.querySelectorAll('tr').forEach((tr) => {
    const u = tr.querySelector('.line-uom-view').textContent || '-';
    tr.setAttribute('data-source-uom', u);
  });
  applyFilter();
});
</script>
