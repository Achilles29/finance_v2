<?php
$rows = is_array($rows ?? null) ? $rows : [];
$q = (string)($q ?? '');
$componentsForMapping = is_array($components_for_mapping ?? null) ? $components_for_mapping : [];
$unmappedComponents = is_array($unmapped_components ?? null) ? $unmapped_components : [];

$totalRows = count($rows);
$activeCount = 0;
$inactiveCount = 0;
$totalComponents = 0;
foreach ($rows as $r) {
  $isActive = (int)($r['is_active'] ?? 0) === 1;
  if ($isActive) {
    $activeCount++;
  } else {
    $inactiveCount++;
  }
  $totalComponents += (int)($r['component_count'] ?? 0);
}
?>
<div class="container-xxl py-3">
  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'category']); ?>

  <div class="card border-0 shadow-sm mb-3" style="background:linear-gradient(120deg,#fff8f2 0%,#ffffff 55%,#f4f9ff 100%);">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h4 class="mb-1">Kategori Base/Prepare</h4>
          <small class="text-muted">Kelola struktur kategori dengan scope yang jelas untuk BASE, PREPARE, atau keduanya.</small>
        </div>
        <button id="btn-add" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
          <i class="ri-add-line me-1"></i>Kategori Baru
        </button>
      </div>

      <div class="row g-2 mt-3">
        <div class="col-md-3">
          <div class="p-2 rounded border bg-white">
            <div class="small text-muted">Total Kategori</div>
            <div class="fw-bold fs-5"><?php echo number_format($totalRows, 0, ',', '.'); ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="p-2 rounded border bg-white">
            <div class="small text-muted">Kategori Aktif</div>
            <div class="fw-bold fs-5 text-success"><?php echo number_format($activeCount, 0, ',', '.'); ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="p-2 rounded border bg-white">
            <div class="small text-muted">Kategori Nonaktif</div>
            <div class="fw-bold fs-5 text-secondary"><?php echo number_format($inactiveCount, 0, ',', '.'); ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="p-2 rounded border bg-white">
            <div class="small text-muted">Total Component Terpetakan</div>
            <div class="fw-bold fs-5 text-primary"><?php echo number_format($totalComponents, 0, ',', '.'); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
          <h5 class="mb-1">Quick Mapping Component -> Kategori</h5>
          <small class="text-muted">Map cepat untuk komponen yang belum tepat kategorinya.</small>
        </div>
      </div>
      <form id="quick-map-form" class="row g-2 mt-1">
        <div class="col-md-6">
          <label class="form-label mb-1">Component</label>
          <input type="hidden" name="component_id" id="quick-map-component-id" value="">
          <input type="text" class="form-control" id="quick-map-component-search" placeholder="Ketik nama component..." autocomplete="off" required>
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Target Kategori</label>
          <select class="form-select" name="component_category_id" required>
            <option value="">Pilih kategori...</option>
            <?php foreach ($rows as $r): ?>
              <option value="<?php echo (int)$r['id']; ?>">
                <?php echo html_escape((string)$r['name']); ?> (<?php echo html_escape((string)($r['scope_type'] ?? 'ALL')); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <label class="form-label mb-1 invisible">map</label>
          <button type="submit" class="btn btn-outline-primary">Map Sekarang</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 text-warning-emphasis">Warning: Komponen Belum Terpetakan Sesuai Kategori</h5>
        <span class="badge text-bg-warning"><?php echo number_format(count($unmappedComponents), 0, ',', '.'); ?> item</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Tipe</th>
              <th>Kategori Saat Ini</th>
              <th>Scope Kategori</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($unmappedComponents)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada warning. Semua komponen sudah sesuai.</td></tr>
            <?php else: foreach ($unmappedComponents as $u): ?>
              <tr>
                <td><?php echo html_escape((string)$u['component_name']); ?></td>
                <td><?php echo html_escape((string)$u['component_type']); ?></td>
                <td><?php echo html_escape((string)($u['category_name'] ?? '-')); ?></td>
                <td><span class="badge text-bg-light border"><?php echo html_escape((string)($u['category_scope'] ?? '-')); ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="get" class="row g-2 mb-3">
        <div class="col-md-5">
          <input class="form-control" name="q" value="<?php echo html_escape($q); ?>" placeholder="Cari kode / nama kategori...">
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-outline-primary">Filter</button>
        </div>
        <div class="col-md-2 d-grid">
          <a href="<?php echo site_url('production/component-categories'); ?>" class="btn btn-outline-danger">Clear</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th style="width:180px;">Kode</th>
              <th>Nama</th>
              <th style="width:140px;">Scope</th>
              <th class="text-end" style="width:110px;">Urutan</th>
              <th class="text-end" style="width:160px;">Jumlah Component</th>
              <th style="width:140px;">Status</th>
              <th style="width:210px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">Belum ada data kategori.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><code><?php echo html_escape((string)$r['code']); ?></code></td>
                  <td class="fw-semibold"><?php echo html_escape((string)$r['name']); ?></td>
                  <td>
                    <span class="badge text-bg-light border"><?php echo html_escape((string)($r['scope_type'] ?? 'ALL')); ?></span>
                  </td>
                  <td class="text-end"><?php echo number_format((int)($r['sort_order'] ?? 0), 0, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((int)($r['component_count'] ?? 0), 0, ',', '.'); ?></td>
                  <td><?php echo ui_status_badge((int)($r['is_active'] ?? 0) === 1 ? 'ACTIVE' : 'INACTIVE', 'active'); ?></td>
                  <td class="component-action-cell">
                    <div class="component-action-stack">
                      <button
                        type="button"
                        class="btn btn-outline-primary action-icon-btn component-action-btn js-edit"
                        data-row="<?php echo html_escape(json_encode($r, JSON_INVALID_UTF8_SUBSTITUTE)); ?>"
                        data-bs-toggle="modal"
                        data-bs-target="#categoryModal"
                        title="Edit"
                        aria-label="Edit"
                      >
                        <i class="ri ri-edit-line"></i>
                      </button>
                      <button type="button" class="btn btn-outline-warning action-icon-btn component-action-btn js-toggle" data-id="<?php echo (int)$r['id']; ?>" title="Toggle Status" aria-label="Toggle Status"><i class="ri ri-refresh-line"></i></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="categoryModalTitle">Kategori Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-save" class="row g-2">
          <input type="hidden" name="id" value="">
          <div class="col-md-6">
            <label class="form-label mb-1">Nama Kategori</label>
            <input class="form-control" name="name" placeholder="Contoh: SAUCE BASE" required>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Scope</label>
            <select class="form-select" name="scope_type">
              <option value="ALL">ALL (Base+Prepare)</option>
              <option value="BASE">BASE</option>
              <option value="PREPARE">PREPARE</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Sort</label>
            <input class="form-control" type="number" name="sort_order" value="0" min="0">
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1">Kode (otomatis)</label>
            <input class="form-control font-monospace" name="code" id="code" placeholder="AUTO" readonly>
            <small class="text-muted">Kode dibuat otomatis dari nama. Saat edit, kode existing dipertahankan.</small>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Status</label>
            <select class="form-select" name="is_active">
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save">Simpan</button>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const form = document.getElementById('form-save');
  const modalTitle = document.getElementById('categoryModalTitle');
  const btnAdd = document.getElementById('btn-add');
  const nameEl = form.querySelector('[name="name"]');
  const codeEl = form.querySelector('[name="code"]');
  const quickMapComponentId = document.getElementById('quick-map-component-id');
  const quickMapComponentSearch = document.getElementById('quick-map-component-search');

  const slugifyCode = (name) => String(name || '')
    .normalize('NFKD')
    .replace(/[^\w\s-]/g, '')
    .trim()
    .replace(/[\s-]+/g, '_')
    .toUpperCase()
    .slice(0, 50);

  const postJson = async (url, payload) => {
    const r = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal');
    return j;
  };

  const pickerLabel = (row) => [row.code || '', row.name || ''].filter(Boolean).join(' - ');
  const pickerSubLabel = (row) => [row.entity_type || '', row.category_name || '', row.uom_code || ''].filter(Boolean).join(' | ');

  window.ProductionAjaxPicker.bind(quickMapComponentSearch, {
    entity: 'COMPONENT',
    renderLabel: pickerLabel,
    renderSubLabel: pickerSubLabel,
    onType: () => {
      quickMapComponentId.value = '';
    },
    onSelect: (result) => {
      quickMapComponentId.value = String(result.id || '');
      quickMapComponentSearch.value = pickerLabel(result);
    }
  });

  const resetForm = () => {
    form.reset();
    form.querySelector('[name="id"]').value = '';
    form.querySelector('[name="scope_type"]').value = 'ALL';
    form.querySelector('[name="sort_order"]').value = '0';
    form.querySelector('[name="is_active"]').value = '1';
    codeEl.value = '';
    modalTitle.textContent = 'Kategori Baru';
  };

  btnAdd?.addEventListener('click', resetForm);

  nameEl.addEventListener('input', () => {
    const id = form.querySelector('[name="id"]').value;
    if (id) return; // edit: kode existing dipertahankan
    codeEl.value = slugifyCode(nameEl.value);
  });

  document.querySelectorAll('.js-edit').forEach((btn) => {
    btn.addEventListener('click', () => {
      const row = JSON.parse(btn.dataset.row || '{}');
      form.querySelector('[name="id"]').value = row.id || '';
      form.querySelector('[name="name"]').value = row.name || '';
      form.querySelector('[name="scope_type"]').value = row.scope_type || 'ALL';
      form.querySelector('[name="sort_order"]').value = row.sort_order || 0;
      form.querySelector('[name="is_active"]').value = String((row.is_active || 0) == 1 ? 1 : 0);
      codeEl.value = row.code || '';
      modalTitle.textContent = `Edit Kategori: ${row.name || row.code || row.id}`;
    });
  });

  function setButtonBusy(button, label) {
    if (!button) {
      return;
    }
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.disabled = true;
  }

  function clearButtonBusy(button) {
    if (!button) {
      return;
    }
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button);
      return;
    }
    button.disabled = false;
  }

  document.getElementById('btn-save').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());
    if (!payload.id) {
      payload.code = slugifyCode(payload.name);
    }
    setButtonBusy(button, 'Menyimpan...');
    try {
      await postJson('<?php echo site_url('production/component-categories/save'); ?>', payload);
      location.reload();
    } catch (err) {
      alert(err.message);
      clearButtonBusy(button);
    }
  });

  document.querySelectorAll('.js-toggle').forEach((btn) => {
    btn.addEventListener('click', async () => {
      setButtonBusy(btn, 'Memproses...');
      try {
        await postJson('<?php echo site_url('production/component-categories/toggle'); ?>/' + btn.dataset.id, {});
        location.reload();
      } catch (err) {
        alert(err.message);
        clearButtonBusy(btn);
      }
    });
  });

  document.getElementById('quick-map-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitButton = e.submitter || e.currentTarget.querySelector('button[type="submit"]');
    const payload = Object.fromEntries(new FormData(e.currentTarget).entries());
    if (!payload.component_id) {
      alert('Pilih component dari hasil pencarian terlebih dahulu.');
      quickMapComponentSearch?.focus();
      return;
    }
    setButtonBusy(submitButton, 'Menyimpan...');
    try {
      await postJson('<?php echo site_url('production/component-categories/quick-map'); ?>', payload);
      location.reload();
    } catch (err) {
      alert(err.message);
      clearButtonBusy(submitButton);
    }
  });
})();
</script>
