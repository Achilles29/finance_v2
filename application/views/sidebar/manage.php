<?php
$saveUrl = site_url('sidebar/manage/save');
$manageBase = site_url('sidebar/manage');
$storeUrl = site_url('sidebar/manage/menu/store');
$type = (string)($sidebar_type_selected ?? 'MAIN');
$summary = $favorite_summary ?? ['total_rows' => 0, 'active_users' => 0, 'top_menus' => [], 'top_users' => []];
$tree = $sidebar_tree_raw ?? [];
$treeEditor = $sidebar_tree_preview ?? [];
$flatMenus = $sidebar_flat_raw ?? [];
$editMenu = $edit_menu ?? null;
$parentCandidates = $parent_candidates ?? [];
$structureCsrfToken = (string)($sidebar_structure_csrf_token ?? '');
$registryValidation = isset($registry_validation) && is_array($registry_validation) ? $registry_validation : [];
$registryIssueCounts = isset($registryValidation['issue_counts']) && is_array($registryValidation['issue_counts']) ? $registryValidation['issue_counts'] : [];
$registryDetails = isset($registryValidation['details']) && is_array($registryValidation['details']) ? $registryValidation['details'] : [];
$registryTotalIssues = max(0, (int)($registryValidation['total_issues'] ?? 0));
$registryIssueLabels = [
  'missing_page' => 'Page tidak valid',
  'missing_icon' => 'Icon kosong',
  'duplicate_url' => 'URL duplikat',
  'duplicate_code' => 'Kode duplikat',
  'sort_collision' => 'Urutan bentrok',
  'invalid_favorite' => 'Favorite tidak valid',
  'invalid_group_url' => 'Grup memakai URL',
];
$isEdit = !empty($editMenu);
$submitUrl = $isEdit ? site_url('sidebar/manage/menu/update/' . (int)$editMenu['id']) : $storeUrl;
$activeTab = (string)($this->input->get('tab', true) ?? '');
if ($activeTab !== 'menu-data' && $activeTab !== 'structure') {
  $activeTab = $isEdit ? 'menu-data' : 'structure';
}
$savedStructure = ((int)$this->input->get('saved', true) === 1);
?>

<?php if ($savedStructure): ?>
  <div class="alert alert-success py-2 mb-3" role="status" aria-live="polite">
    Struktur sidebar berhasil disimpan.
  </div>
<?php endif; ?>

<header class="finance-page-header mb-3">
  <div>
    <h4 class="mb-1"><i class="ri ri-node-tree page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Atur urutan menu, naik-turun, dan parent submenu dengan drag & drop. Simpan untuk menerapkan.</small>
  </div>
  <div class="finance-action-bar" aria-label="Pilih tipe sidebar">
    <a href="<?php echo html_escape($manageBase . '?type=MAIN'); ?>" class="btn <?php echo $type === 'MAIN' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm"<?php echo $type === 'MAIN' ? ' aria-current="page"' : ''; ?>>Sidebar MAIN</a>
    <a href="<?php echo html_escape($manageBase . '?type=MY'); ?>" class="btn <?php echo $type === 'MY' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm"<?php echo $type === 'MY' ? ' aria-current="page"' : ''; ?>>Sidebar MY</a>
  </div>
</header>

<div class="row g-3 mb-3">
  <div class="col-md-3 col-6">
    <div class="card finance-card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block">Total Pin</small>
        <div class="fs-4 fw-bold"><?php echo (int)$summary['total_rows']; ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card finance-card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block">User Aktif Pin</small>
        <div class="fs-4 fw-bold"><?php echo (int)$summary['active_users']; ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-12">
    <div class="card finance-card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block mb-2">Top Menu Dipin</small>
        <?php if (empty($summary['top_menus'])): ?>
          <div class="finance-empty-state finance-empty-state-compact">Belum ada data.</div>
        <?php else: ?>
          <?php foreach ($summary['top_menus'] as $m): ?>
            <div class="small d-flex justify-content-between"><span><?php echo html_escape((string)$m['menu_label']); ?></span><strong><?php echo (int)$m['total_pin']; ?></strong></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-12">
    <div class="card finance-card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block mb-2">Top User Pin</small>
        <?php if (empty($summary['top_users'])): ?>
          <div class="finance-empty-state finance-empty-state-compact">Belum ada data.</div>
        <?php else: ?>
          <?php foreach ($summary['top_users'] as $u): ?>
            <div class="small d-flex justify-content-between"><span><?php echo html_escape((string)($u['username'] ?? 'Unknown')); ?></span><strong><?php echo (int)$u['total_pin']; ?></strong></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($registryValidation !== []): ?>
<section class="card finance-card finance-registry-health mb-3" aria-labelledby="registry-health-title">
  <div class="card-body py-3">
    <div class="finance-page-header finance-page-header-compact mb-2">
      <div>
        <h5 class="mb-0" id="registry-health-title">Kesehatan Page Registry</h5>
        <small class="text-muted">Ringkasan sinkronisasi menu, page, favorite, dan urutan.</small>
      </div>
      <span class="badge <?php echo $registryTotalIssues > 0 ? 'bg-warning-subtle text-warning border' : 'bg-success-subtle text-success border border-success-subtle'; ?>">
        <?php echo $registryTotalIssues > 0 ? html_escape((string)$registryTotalIssues) . ' isu' : 'Sehat'; ?>
      </span>
    </div>
    <div class="finance-registry-metrics" aria-label="Jumlah validasi registry">
      <?php foreach ($registryIssueLabels as $issueKey => $issueLabel): ?>
        <span><strong><?php echo max(0, (int)($registryIssueCounts[$issueKey] ?? 0)); ?></strong> <?php echo html_escape($issueLabel); ?></span>
      <?php endforeach; ?>
    </div>
    <?php foreach ($registryIssueLabels as $detailKey => $detailLabel): ?>
      <?php $detailRows = isset($registryDetails[$detailKey]) && is_array($registryDetails[$detailKey]) ? $registryDetails[$detailKey] : []; ?>
      <?php if ($detailRows !== []): ?>
        <details class="finance-registry-detail">
          <summary><?php echo html_escape($detailLabel); ?> (<?php echo count($detailRows); ?>)</summary>
          <ul class="mb-0 mt-2">
            <?php foreach (array_slice($detailRows, 0, 8) as $detailRow): ?>
              <?php
                $detailParts = [];
                if (is_array($detailRow)) {
                  foreach ($detailRow as $detailField => $detailValue) {
                    if (is_scalar($detailValue) && trim((string)$detailValue) !== '') {
                      $detailParts[] = str_replace('_', ' ', (string)$detailField) . ': ' . trim((string)$detailValue);
                    }
                  }
                } elseif (is_scalar($detailRow)) {
                  $detailParts[] = (string)$detailRow;
                }
                $detailText = $detailParts !== [] ? implode(' — ', array_values(array_unique($detailParts))) : 'Detail tidak tersedia';
              ?>
              <li><?php echo html_escape($detailText); ?></li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $activeTab === 'structure' ? 'active' : ''; ?>" id="tab-structure-btn" data-bs-toggle="tab" data-bs-target="#tab-structure" type="button" role="tab" aria-controls="tab-structure" aria-selected="<?php echo $activeTab === 'structure' ? 'true' : 'false'; ?>">Struktur Sidebar <?php echo html_escape($type); ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $activeTab === 'menu-data' ? 'active' : ''; ?>" id="tab-menu-data-btn" data-bs-toggle="tab" data-bs-target="#tab-menu-data" type="button" role="tab" aria-controls="tab-menu-data" aria-selected="<?php echo $activeTab === 'menu-data' ? 'true' : 'false'; ?>">Data Menu (CRUD)</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade <?php echo $activeTab === 'structure' ? 'show active' : ''; ?>" id="tab-structure" role="tabpanel" aria-labelledby="tab-structure-btn" tabindex="0">
    <div class="card finance-card">
      <div class="card-header finance-card-header">
        <div>
          <strong>Struktur Aktual Sidebar <?php echo html_escape($type); ?></strong>
          <div class="text-muted small">Drag-drop langsung di sini untuk atur urutan/parent menu.</div>
        </div>
        <div class="finance-action-bar">
          <button type="button" id="btn_expand_all_sidebar" class="btn btn-outline-secondary btn-sm">Expand All</button>
          <button type="button" id="btn_collapse_all_sidebar" class="btn btn-outline-secondary btn-sm">Collapse All</button>
          <button type="button" id="btn_save_sidebar_structure" class="btn btn-primary btn-sm" data-loading-label="Menyimpan...">Simpan Struktur</button>
        </div>
      </div>
      <div class="card-body finance-scroll-panel finance-scroll-panel-lg">
        <div id="sidebar-tree-root">
          <?php if ($treeEditor === []): ?>
            <div class="finance-empty-state">Belum ada struktur sidebar untuk tipe ini.</div>
          <?php endif; ?>
        </div>
        <div id="sidebar-save-alert" class="mt-3" role="status" aria-live="polite"></div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade <?php echo $activeTab === 'menu-data' ? 'show active' : ''; ?>" id="tab-menu-data" role="tabpanel" aria-labelledby="tab-menu-data-btn" tabindex="0">
    <?php
      $pageRegistryById = [];
      foreach (($page_registry ?? []) as $pr) {
          $pageRegistryById[(int)$pr['id']] = $pr;
      }
      $pageRegistryByModule = [];
      foreach (($page_registry ?? []) as $pr) {
          $pageRegistryByModule[$pr['module']][] = $pr;
      }
    ?>
    <div class="row g-3">

      <!-- ── FORM PANEL ──────────────────────────────────────────── -->
      <div class="col-lg-5">
        <div class="card finance-card">
          <div class="card-header finance-card-header">
            <strong><?php echo $isEdit ? '<i class="ri ri-edit-line me-1"></i>Edit Menu Sidebar' : '<i class="ri ri-add-circle-line me-1"></i>Tambah Menu Sidebar'; ?></strong>
            <?php if ($isEdit): ?>
              <a href="<?php echo html_escape($manageBase . '?type=' . urlencode($type) . '&tab=menu-data'); ?>" class="btn btn-outline-secondary btn-xs px-2 py-1">Batal</a>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <form method="post" action="<?php echo $submitUrl; ?>" class="row g-2" id="sidebar-menu-form">
              <input type="hidden" name="sidebar_structure_csrf" value="<?php echo html_escape($structureCsrfToken); ?>">
              <input type="hidden" name="sidebar_type" value="<?php echo html_escape($type); ?>">

              <div class="col-12">
                <label class="form-label mb-1 small fw-semibold">Nama Menu <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="menu_label" required
                       placeholder="mis. Laporan Penjualan"
                       value="<?php echo html_escape((string)($editMenu['menu_label'] ?? '')); ?>">
              </div>

              <div class="col-12">
                <label class="form-label mb-1 small fw-semibold">Kode Menu <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm font-monospace" name="menu_code" required
                       placeholder="mis. pos.report.sales"
                       value="<?php echo html_escape((string)($editMenu['menu_code'] ?? '')); ?>">
                <div class="form-text" style="font-size:.7rem;">Unik, lowercase + titik. Contoh: <code>grp.inventory</code>, <code>pos.cashier</code></div>
              </div>

              <div class="col-md-7">
                <label class="form-label mb-1 small fw-semibold">URL</label>
                <input type="text" class="form-control form-control-sm" name="url"
                       placeholder="pos/reports/sales"
                       value="<?php echo html_escape((string)($editMenu['url'] ?? '')); ?>">
              </div>

              <div class="col-md-5">
                <label class="form-label mb-1 small fw-semibold">Icon <small class="text-muted fw-normal">(Remix)</small></label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text px-2" id="menu-icon-preview"><i class="ri <?php echo html_escape((string)($editMenu['icon'] ?? 'ri-circle-line')); ?>"></i></span>
                  <input type="text" class="form-control form-control-sm font-monospace" name="icon" id="menu-icon-input"
                         placeholder="ri-circle-line"
                         value="<?php echo html_escape((string)($editMenu['icon'] ?? 'ri-circle-line')); ?>">
                </div>
              </div>

              <div class="col-12">
                <label class="form-label mb-1 small fw-semibold">Halaman Terkait (Page Registry)</label>
                <select name="page_id" class="form-select form-select-sm" id="menu-page-select">
                  <option value="0">— Tidak dihubungkan (grup/heading) —</option>
                  <?php foreach ($pageRegistryByModule as $mod => $pages): ?>
                    <optgroup label="<?php echo html_escape($mod); ?>">
                      <?php foreach ($pages as $pr): ?>
                        <option value="<?php echo (int)$pr['id']; ?>"
                                <?php echo ((int)($editMenu['page_id'] ?? 0) === (int)$pr['id']) ? 'selected' : ''; ?>>
                          <?php echo html_escape($pr['page_name']); ?> — <small><?php echo html_escape($pr['page_code']); ?></small>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
                <div class="form-text" style="font-size:.7rem;">Hubungkan menu ke page registry agar muncul di matrix role &amp; permission.</div>
              </div>

              <div class="col-md-7">
                <label class="form-label mb-1 small fw-semibold">Parent</label>
                <select name="parent_id" class="form-select form-select-sm">
                  <option value="0">(Root — tanpa parent)</option>
                  <?php foreach ($parentCandidates as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>" <?php echo ((int)($editMenu['parent_id'] ?? 0) === (int)$p['id']) ? 'selected' : ''; ?>>
                      <?php echo html_escape((string)$p['menu_label']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-5">
                <label class="form-label mb-1 small fw-semibold">Sort Order</label>
                <input type="number" min="1" class="form-control form-control-sm" name="sort_order"
                       value="<?php echo (int)($editMenu['sort_order'] ?? 999); ?>">
              </div>

              <?php if ($isEdit): ?>
              <div class="col-12">
                <label class="form-check form-switch mb-0">
                  <input type="checkbox" class="form-check-input" name="is_active"
                         <?php echo !isset($editMenu['is_active']) || (int)$editMenu['is_active'] === 1 ? 'checked' : ''; ?>>
                  <span class="form-check-label small">Menu Aktif</span>
                </label>
              </div>
              <?php endif; ?>

              <div class="col-12 pt-1">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="ri <?php echo $isEdit ? 'ri-save-line' : 'ri-add-line'; ?> me-1"></i>
                  <?php echo $isEdit ? 'Update Menu' : 'Simpan Menu'; ?>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ── TABLE PANEL ─────────────────────────────────────────── -->
      <div class="col-lg-7">
        <div class="card finance-card">
          <div class="card-header finance-card-header">
            <strong>Daftar Menu Sidebar <?php echo html_escape($type); ?></strong>
            <div class="input-group input-group-sm finance-filter-control">
              <span class="input-group-text"><i class="ri ri-search-line"></i></span>
              <input type="text" id="menu-list-search" class="form-control form-control-sm" placeholder="Cari nama / kode / URL…">
            </div>
          </div>

          <div class="finance-table-region finance-scroll-panel">
            <table class="table table-hover table-sm align-middle mb-0 finance-table" id="menu-list-table">
              <thead class="table-light sticky-top" style="z-index:1;">
                <tr>
                  <th style="width:36px;" class="text-center">#</th>
                  <th>Nama / Kode</th>
                  <th>Page Link</th>
                  <th style="width:72px;" class="text-center">Status</th>
                  <th style="width:72px;" class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody id="menu-list-tbody">
                <?php if (empty($flatMenus)): ?>
                  <tr><td colspan="5"><div class="finance-empty-state">Belum ada menu.</div></td></tr>
                <?php else: ?>
                  <?php foreach ($flatMenus as $m):
                    $linkedPage = !empty($m['page_id']) ? ($pageRegistryById[(int)$m['page_id']] ?? null) : null;
                    $isActive = (int)($m['is_active'] ?? 0) === 1;
                    $searchText = strtolower(trim((string)($m['menu_label'] ?? '') . ' ' . (string)($m['menu_code'] ?? '') . ' ' . (string)($m['url'] ?? '')));
                  ?>
                    <tr class="menu-row" data-id="<?php echo (int)$m['id']; ?>"
                        data-search="<?php echo html_escape($searchText); ?>"
                        data-active="<?php echo $isActive ? '1' : '0'; ?>">
                      <td class="text-center text-muted small"><?php echo (int)$m['id']; ?></td>
                      <td>
                        <div class="fw-semibold small"><?php echo html_escape((string)$m['menu_label']); ?></div>
                        <div class="font-monospace text-muted" style="font-size:.68rem;"><?php echo html_escape((string)$m['menu_code']); ?></div>
                        <?php if (!empty($m['url'])): ?>
                          <div class="text-muted" style="font-size:.68rem;"><?php echo html_escape((string)$m['url']); ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($linkedPage): ?>
                          <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.67rem; max-width:130px; white-space:normal; line-height:1.3;">
                            <i class="ri ri-pages-line me-1"></i><?php echo html_escape((string)$linkedPage['page_name']); ?>
                          </span>
                        <?php elseif (!empty($m['url'])): ?>
                          <span class="badge bg-warning-subtle text-warning border" style="font-size:.67rem;">Belum di-link</span>
                        <?php else: ?>
                          <span class="text-muted" style="font-size:.68rem;">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <span class="menu-status-badge badge <?php echo $isActive ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border'; ?>"
                              style="font-size:.67rem; cursor:default;"
                              data-active="<?php echo $isActive ? '1' : '0'; ?>">
                          <?php echo $isActive ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                          <a href="<?php echo $manageBase . '?type=' . urlencode($type) . '&tab=menu-data&edit_id=' . (int)$m['id']; ?>"
                             class="btn btn-xs btn-outline-primary p-1 finance-icon-action" title="Edit" aria-label="Edit menu <?php echo html_escape((string)$m['menu_label']); ?>">
                            <i class="ri ri-edit-line"></i>
                          </a>
                          <button type="button"
                                  class="btn btn-xs menu-toggle-btn p-1 finance-icon-action <?php echo $isActive ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                  title="<?php echo $isActive ? 'Nonaktifkan' : 'Aktifkan'; ?>"
                                  aria-label="<?php echo $isActive ? 'Nonaktifkan' : 'Aktifkan'; ?> menu <?php echo html_escape((string)$m['menu_label']); ?>"
                                  data-id="<?php echo (int)$m['id']; ?>"
                                  data-active="<?php echo $isActive ? '1' : '0'; ?>">
                            <i class="ri <?php echo $isActive ? 'ri-toggle-line' : 'ri-toggle-fill'; ?>"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination bar -->
          <div class="card-footer py-2 d-flex align-items-center justify-content-between gap-2 flex-wrap" id="menu-pagination-bar">
            <div class="text-muted small" id="menu-pagination-info"></div>
            <div class="d-flex gap-1" id="menu-pagination-btns"></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function () {
  var rootEl = document.getElementById('sidebar-tree-root');
  var saveBtn = document.getElementById('btn_save_sidebar_structure');
  var expandAllBtn = document.getElementById('btn_expand_all_sidebar');
  var collapseAllBtn = document.getElementById('btn_collapse_all_sidebar');
  var alertBox = document.getElementById('sidebar-save-alert');
  var saveUrl = <?php echo json_encode($saveUrl); ?>;
  var manageBase = <?php echo json_encode($manageBase); ?>;
  var sidebarType = <?php echo json_encode($type); ?>;
  var structureCsrfToken = <?php echo json_encode($structureCsrfToken); ?>;
  var treeData = <?php echo json_encode($treeEditor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  var dragState = {
    draggedLi: null,
    dropMode: null,
    dropTargetLi: null
  };

  function createNodeLabel(item, depth, hasChild) {
    var isVirtual = Number(item.is_virtual || 0) === 1;
    var isActive = isVirtual ? true : Number(item.is_active || 0) === 1;

    var wrap = document.createElement('div');
    wrap.className = 'sidebar-sort-item d-flex align-items-center justify-content-between gap-2';
    wrap.classList.toggle('is-inactive', !isActive);
    wrap.dataset.depth = String(depth || 0);

    var left = document.createElement('div');
    left.className = 'd-flex align-items-center gap-2';

    var expander = document.createElement('button');
    expander.type = 'button';
    expander.className = 'btn btn-xs btn-outline-secondary sidebar-node-expander finance-icon-action';
    expander.innerHTML = '<i class="ri ri-arrow-down-s-line"></i>';
    expander.title = 'Expand / Collapse';
    expander.setAttribute('aria-label', 'Expand atau collapse submenu');
    expander.dataset.collapsed = '0';
    expander.style.visibility = hasChild ? 'visible' : 'hidden';
    left.appendChild(expander);

    var levelBadge = document.createElement('span');
    levelBadge.className = 'badge text-bg-light border';
    levelBadge.style.fontSize = '0.65rem';
    levelBadge.style.minWidth = '36px';
    levelBadge.style.textAlign = 'center';
    if ((depth || 0) <= 0) {
      levelBadge.textContent = 'PARENT';
    } else if ((depth || 0) === 1) {
      levelBadge.textContent = 'CHILD';
    } else {
      levelBadge.textContent = 'CH-2';
    }
    left.appendChild(levelBadge);

    if (!isVirtual) {
      var handle = document.createElement('i');
      handle.className = 'ri ri-drag-move-2-line text-muted sidebar-drag-handle';
      handle.style.cursor = 'grab';
      left.appendChild(handle);
    }

    var icon = document.createElement('i');
    icon.className = 'ri ' + (item.icon || 'ri-circle-line') + ' text-primary';

    var txt = document.createElement('span');
    txt.textContent = item.menu_label + ' (' + item.menu_code + ')';
    txt.style.fontSize = '0.9rem';

    left.appendChild(icon);
    left.appendChild(txt);

    if (!isActive && !isVirtual) {
      var off = document.createElement('small');
      off.className = 'badge bg-danger text-white ms-2';
      off.textContent = 'Nonaktif';
      left.appendChild(off);
    }

    // Right panel: URL + status badge + actions
    var rightPanel = document.createElement('div');
    rightPanel.className = 'd-flex align-items-center gap-2 flex-shrink-0';

    var urlSpan = document.createElement('small');
    urlSpan.className = 'text-muted text-truncate';
    urlSpan.style.maxWidth = '140px';
    urlSpan.title = (item.url && String(item.url).trim() !== '') ? String(item.url) : '';
    urlSpan.textContent = (item.url && String(item.url).trim() !== '') ? String(item.url) : '—';
    rightPanel.appendChild(urlSpan);

    if (!isVirtual) {
      // Status badge
      var statusBadge = document.createElement('span');
      statusBadge.className = 'node-status-badge badge ' + (isActive
        ? 'bg-success-subtle text-success border border-success-subtle'
        : 'bg-secondary-subtle text-secondary border');
      statusBadge.style.fontSize = '0.67rem';
      statusBadge.style.whiteSpace = 'nowrap';
      statusBadge.textContent = isActive ? 'Aktif' : 'Nonaktif';
      rightPanel.appendChild(statusBadge);

      // Actions
      var actDiv = document.createElement('div');
      actDiv.className = 'd-flex gap-1';

      var editLink = document.createElement('a');
      editLink.href = manageBase + '?type=' + encodeURIComponent(sidebarType) + '&tab=menu-data&edit_id=' + item.id;
      editLink.className = 'btn btn-xs btn-outline-primary p-1 finance-icon-action';
      editLink.title = 'Edit';
      editLink.setAttribute('aria-label', 'Edit menu ' + item.menu_label);
      editLink.innerHTML = '<i class="ri ri-edit-line"></i>';
      actDiv.appendChild(editLink);

      var togBtn = document.createElement('button');
      togBtn.type = 'button';
      togBtn.className = 'btn btn-xs p-1 node-toggle-btn finance-icon-action ' + (isActive ? 'btn-outline-warning' : 'btn-outline-success');
      togBtn.title = isActive ? 'Nonaktifkan' : 'Aktifkan';
      togBtn.setAttribute('aria-label', (isActive ? 'Nonaktifkan' : 'Aktifkan') + ' menu ' + item.menu_label);
      togBtn.dataset.id     = String(item.id);
      togBtn.dataset.active = isActive ? '1' : '0';
      togBtn.innerHTML = '<i class="ri ' + (isActive ? 'ri-toggle-line' : 'ri-toggle-fill') + '"></i>';
      actDiv.appendChild(togBtn);

      rightPanel.appendChild(actDiv);
    }

    wrap.appendChild(left);
    wrap.appendChild(rightPanel);
    return wrap;
  }

  function buildList(items, depth) {
    var level = Number(depth || 0);
    var ul = document.createElement('ul');
    ul.className = 'list-unstyled sidebar-sortable-list';
    ul.classList.toggle('sidebar-list-root', level <= 0);
    ul.dataset.depth = String(level);

    (items || []).forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'mb-2 sidebar-tree-node';
      li.dataset.id = String(item.id || 0);
      li.dataset.virtual = String(Number(item.is_virtual || 0));
      li.dataset.depth = String(level);
      li.draggable = Number(item.is_virtual || 0) !== 1;
      if (Number(item.is_virtual || 0) === 1) {
        li.classList.add('sidebar-virtual-node');
      }
      var children = Array.isArray(item.children) ? item.children : [];
      li.appendChild(createNodeLabel(item, level, children.length > 0));

      var childList = buildList(children, level + 1);
      li.appendChild(childList);

      ul.appendChild(li);
    });
    return ul;
  }

  function isDescendant(parent, maybeChild) {
    if (!parent || !maybeChild) return false;
    return parent.contains(maybeChild);
  }

  function clearDropMarks() {
    rootEl.querySelectorAll('.drop-before, .drop-after, .drop-inside').forEach(function (el) {
      el.classList.remove('drop-before', 'drop-after', 'drop-inside');
    });
    dragState.dropMode = null;
    dragState.dropTargetLi = null;
  }

  function ensureChildList(li) {
    var ul = li.querySelector(':scope > ul.sidebar-sortable-list');
    if (ul) return ul;
    ul = document.createElement('ul');
    ul.className = 'list-unstyled sidebar-sortable-list';
    ul.dataset.depth = String(Number(li.dataset.depth || 0) + 1);
    li.appendChild(ul);
    return ul;
  }

  function refreshDepth(node, depth) {
    if (!(node instanceof HTMLElement)) return;
    node.dataset.depth = String(depth);
    var label = node.querySelector(':scope > .sidebar-sort-item');
    if (label) {
      label.dataset.depth = String(depth);
      var badge = label.querySelector('.badge.text-bg-light');
      if (badge) {
        if (depth <= 0) badge.textContent = 'PARENT';
        else if (depth === 1) badge.textContent = 'CHILD';
        else badge.textContent = 'CH-2';
      }
    }
    var childUl = node.querySelector(':scope > ul.sidebar-sortable-list');
    if (!childUl) return;
    childUl.dataset.depth = String(depth + 1);
    Array.prototype.slice.call(childUl.children).forEach(function (childLi) {
      refreshDepth(childLi, depth + 1);
    });
  }

  function updateExpanderForNode(li) {
    var btn = li.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
    if (!btn) return;
    var childUl = li.querySelector(':scope > ul.sidebar-sortable-list');
    var hasChild = !!childUl && childUl.children.length > 0;
    btn.style.visibility = hasChild ? 'visible' : 'hidden';
    if (!hasChild) {
      btn.dataset.collapsed = '0';
      btn.innerHTML = '<i class=\"ri ri-arrow-down-s-line\"></i>';
      li.classList.remove('is-collapsed');
    }
  }

  function updateAllExpanders() {
    rootEl.querySelectorAll('li.sidebar-tree-node').forEach(function (li) {
      updateExpanderForNode(li);
    });
  }

  function applyDrop(draggedLi, targetLi, mode) {
    if (!draggedLi || !targetLi || !mode) return;
    var originParent = draggedLi.parentElement;
    var targetParent = targetLi.parentElement;
    if (!originParent || !targetParent) return;
    if (draggedLi === targetLi) return;
    if (isDescendant(draggedLi, targetLi)) return;

    if (mode === 'before') {
      targetParent.insertBefore(draggedLi, targetLi);
    } else if (mode === 'after') {
      targetParent.insertBefore(draggedLi, targetLi.nextSibling);
    } else {
      var targetChildUl = ensureChildList(targetLi);
      targetChildUl.appendChild(draggedLi);
      targetLi.classList.remove('is-collapsed');
      var expander = targetLi.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
      if (expander) {
        expander.dataset.collapsed = '0';
        expander.innerHTML = '<i class=\"ri ri-arrow-down-s-line\"></i>';
      }
    }

    var parentNode = draggedLi.parentElement ? draggedLi.parentElement.closest('li.sidebar-tree-node') : null;
    var parentDepth = parentNode ? Number(parentNode.getAttribute('data-depth') || 0) : -1;
    refreshDepth(draggedLi, parentDepth + 1);
    if (originParent.closest('li.sidebar-tree-node')) {
      updateExpanderForNode(originParent.closest('li.sidebar-tree-node'));
    }
    if (targetLi.closest('li.sidebar-tree-node')) {
      updateExpanderForNode(targetLi.closest('li.sidebar-tree-node'));
    }
    updateAllExpanders();
  }

  function serializeListForSave(ul) {
    var out = [];
    Array.prototype.slice.call(ul.children).forEach(function (li) {
      var childUl = li.querySelector(':scope > ul.sidebar-sortable-list');
      var children = childUl ? serializeListForSave(childUl) : [];
      var isVirtual = String(li.dataset.virtual || '0') === '1';

      if (isVirtual) {
        children.forEach(function (c) { out.push(c); });
      } else {
        out.push({
          id: Number(li.dataset.id || 0),
          children: children
        });
      }
    });
    return out;
  }

  function showAlert(type, text) {
    var alert = document.createElement('div');
    alert.className = 'alert alert-' + (type === 'danger' ? 'danger' : 'success') + ' py-2 mb-0';
    alert.textContent = String(text || '');
    alertBox.replaceChildren(alert);
  }

  if (!Array.isArray(treeData)) {
    showAlert('danger', 'Data sidebar tidak valid.');
    return;
  }

  function renderEditor() {
    if (treeData.length === 0) return;
    rootEl.innerHTML = '';
    var rootList = buildList(treeData, 0);
    rootEl.appendChild(rootList);
    updateAllExpanders();

    rootEl.addEventListener('click', function (event) {
      var expanderBtn = event.target.closest('.sidebar-node-expander');
      if (expanderBtn) {
        var nodeLi = expanderBtn.closest('li.sidebar-tree-node');
        if (!nodeLi) return;
        var childUl = nodeLi.querySelector(':scope > ul.sidebar-sortable-list');
        if (!childUl || childUl.children.length === 0) return;
        var collapsed = expanderBtn.dataset.collapsed === '1';
        expanderBtn.dataset.collapsed = collapsed ? '0' : '1';
        expanderBtn.innerHTML = collapsed
          ? '<i class=\"ri ri-arrow-down-s-line\"></i>'
          : '<i class=\"ri ri-arrow-right-s-line\"></i>';
        nodeLi.classList.toggle('is-collapsed', !collapsed);
        return;
      }
    });

    rootEl.addEventListener('dragstart', function (event) {
      var li = event.target.closest('li.sidebar-tree-node');
      if (!li || li.classList.contains('sidebar-virtual-node')) {
        event.preventDefault();
        return;
      }
      dragState.draggedLi = li;
      li.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', li.dataset.id || '');
    });

    rootEl.addEventListener('dragend', function () {
      if (dragState.draggedLi) {
        dragState.draggedLi.classList.remove('is-dragging');
      }
      dragState.draggedLi = null;
      clearDropMarks();
    });

    rootEl.addEventListener('dragover', function (event) {
      if (!dragState.draggedLi) return;
      var targetLi = event.target.closest('li.sidebar-tree-node');
      if (!targetLi || targetLi.classList.contains('sidebar-virtual-node')) return;
      if (targetLi === dragState.draggedLi) return;
      if (isDescendant(dragState.draggedLi, targetLi)) return;

      event.preventDefault();
      var rect = targetLi.getBoundingClientRect();
      var y = event.clientY - rect.top;
      var ratio = rect.height > 0 ? (y / rect.height) : 0.5;
      var mode = ratio < 0.28 ? 'before' : (ratio > 0.72 ? 'after' : 'inside');

      clearDropMarks();
      targetLi.classList.add(mode === 'before' ? 'drop-before' : (mode === 'after' ? 'drop-after' : 'drop-inside'));
      dragState.dropMode = mode;
      dragState.dropTargetLi = targetLi;
    });

    rootEl.addEventListener('drop', function (event) {
      if (!dragState.draggedLi || !dragState.dropTargetLi || !dragState.dropMode) return;
      event.preventDefault();
      applyDrop(dragState.draggedLi, dragState.dropTargetLi, dragState.dropMode);
      clearDropMarks();
    });

    if (expandAllBtn) {
      expandAllBtn.addEventListener('click', function () {
        rootEl.querySelectorAll('li.sidebar-tree-node').forEach(function (li) {
          li.classList.remove('is-collapsed');
          var btn = li.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
          if (btn) {
            btn.dataset.collapsed = '0';
            btn.innerHTML = '<i class=\"ri ri-arrow-down-s-line\"></i>';
          }
        });
      });
    }

    if (collapseAllBtn) {
      collapseAllBtn.addEventListener('click', function () {
        rootEl.querySelectorAll('li.sidebar-tree-node').forEach(function (li) {
          var childUl = li.querySelector(':scope > ul.sidebar-sortable-list');
          if (!childUl || childUl.children.length === 0) return;
          li.classList.add('is-collapsed');
          var btn = li.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
          if (btn) {
            btn.dataset.collapsed = '1';
            btn.innerHTML = '<i class=\"ri ri-arrow-right-s-line\"></i>';
          }
        });
      });
    }

    saveBtn.addEventListener('click', function () {
      var payload = serializeListForSave(rootList);
      saveBtn.disabled = true;
      saveBtn.classList.add('is-loading');
      saveBtn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-1\" role=\"status\" aria-hidden=\"true\"></span>Menyimpan...';

      $.ajax({
        url: saveUrl,
        type: 'POST',
        dataType: 'json',
        headers: {
          'X-Sidebar-Structure-CSRF': structureCsrfToken
        },
        data: {
          sidebar_type: sidebarType,
          tree_json: JSON.stringify(payload)
        }
      }).done(function (resp) {
        if (resp && resp.ok) {
          window.location.href = manageBase + '?type=' + encodeURIComponent(sidebarType) + '&tab=structure&saved=1';
          return;
        } else {
          showAlert('danger', (resp && resp.message) ? resp.message : 'Gagal menyimpan struktur sidebar.');
        }
      }).fail(function () {
        showAlert('danger', 'Gagal menyimpan struktur sidebar.');
      }).always(function () {
        saveBtn.disabled = false;
        saveBtn.classList.remove('is-loading');
        saveBtn.textContent = 'Simpan Struktur';
      });
    });
  }

  // ── Inline toggle for tree nodes ──────────────────────────────
  var TREE_TOGGLE_URL = <?php echo json_encode(site_url('sidebar/manage/menu/toggle/')); ?>;

  rootEl.addEventListener('click', function (e) {
    var btn = e.target.closest('.node-toggle-btn');
    if (!btn) return;
    e.stopPropagation();

    var id       = btn.dataset.id;
    var isActive = btn.dataset.active === '1';
    if (!confirm((isActive ? 'Nonaktifkan' : 'Aktifkan') + ' menu ini?')) return;

    btn.disabled = true;
    var origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.75rem;height:.75rem;"></span>';

    $.ajax({
      url: TREE_TOGGLE_URL + id,
      type: 'POST',
      dataType: 'json',
      headers: { 'X-Sidebar-Structure-CSRF': structureCsrfToken }
    })
      .done(function (resp) {
        if (!resp || !resp.ok) {
          alert(resp && resp.message ? resp.message : 'Gagal mengubah status.');
          btn.innerHTML = origHtml;
          btn.disabled  = false;
          return;
        }

        var nowActive = resp.is_active === 1;
        btn.dataset.active = nowActive ? '1' : '0';
        btn.className      = 'btn btn-xs p-1 node-toggle-btn finance-icon-action ' + (nowActive ? 'btn-outline-warning' : 'btn-outline-success');
        btn.title          = nowActive ? 'Nonaktifkan' : 'Aktifkan';
        btn.innerHTML      = '<i class="ri ' + (nowActive ? 'ri-toggle-line' : 'ri-toggle-fill') + '"></i>';
        btn.disabled       = false;

        // Update status badge
        var wrap   = btn.closest('.sidebar-sort-item');
        var badge  = wrap ? wrap.querySelector('.node-status-badge') : null;
        if (badge) {
          badge.textContent = nowActive ? 'Aktif' : 'Nonaktif';
          badge.className   = 'node-status-badge badge ' + (nowActive
            ? 'bg-success-subtle text-success border border-success-subtle'
            : 'bg-secondary-subtle text-secondary border');
        }

        // Update "Nonaktif" inline badge on the left
        var offBadge = wrap ? wrap.querySelector('.badge.bg-danger') : null;
        if (nowActive && offBadge) {
          offBadge.remove();
        } else if (!nowActive && !offBadge && wrap) {
          var left = wrap.querySelector('.d-flex.align-items-center');
          if (left) {
            var nb = document.createElement('small');
            nb.className   = 'badge bg-danger text-white ms-2';
            nb.textContent = 'Nonaktif';
            left.appendChild(nb);
          }
        }

        // Toggle is-inactive class on the wrap
        if (wrap) {
          wrap.classList.toggle('is-inactive', !nowActive);
        }
      })
      .fail(function (xhr) {
        var msg = 'Gagal mengubah status menu.';
        try { var r = JSON.parse(xhr.responseText); if (r && r.message) msg = r.message; } catch(ex) {}
        alert(msg);
        btn.innerHTML = origHtml;
        btn.disabled  = false;
      });
  });

  renderEditor();
})();
</script>

<script>
// ── CRUD Tab: search, pagination, AJAX toggle, icon preview ───────────
(function () {
  'use strict';

  var TOGGLE_URL = <?php echo json_encode(site_url('sidebar/manage/menu/toggle/')); ?>;
  var structureCsrfToken = <?php echo json_encode($structureCsrfToken); ?>;
  var PAGE_SIZE  = 25;
  var currentPage = 1;

  // --- Icon preview ---
  var iconInput   = document.getElementById('menu-icon-input');
  var iconPreview = document.getElementById('menu-icon-preview');
  if (iconInput && iconPreview) {
    iconInput.addEventListener('input', function () {
      var cls = this.value.trim();
      var i = iconPreview.querySelector('i');
      if (i) {
        i.className = 'ri ' + (cls || 'ri-circle-line');
      }
    });
  }

  // --- Search & Pagination for menu list ---
  var searchEl    = document.getElementById('menu-list-search');
  var infoEl      = document.getElementById('menu-pagination-info');
  var btnsEl      = document.getElementById('menu-pagination-btns');
  var allRows     = Array.from(document.querySelectorAll('#menu-list-tbody .menu-row'));

  function getFilteredRows() {
    var q = searchEl ? searchEl.value.trim().toLowerCase() : '';
    return allRows.filter(function (tr) {
      return !q || (tr.dataset.search || '').indexOf(q) !== -1;
    });
  }

  function renderPage(rows, page) {
    var total  = rows.length;
    var pages  = Math.max(1, Math.ceil(total / PAGE_SIZE));
    page = Math.min(Math.max(1, page), pages);

    var from = (page - 1) * PAGE_SIZE;
    var to   = Math.min(from + PAGE_SIZE, total);

    // Hide all, show only current slice
    allRows.forEach(function (tr) { tr.style.display = 'none'; });
    rows.forEach(function (tr, idx) {
      tr.style.display = (idx >= from && idx < to) ? '' : 'none';
    });

    // Info text
    if (infoEl) {
      infoEl.textContent = total === 0
        ? 'Tidak ada hasil'
        : 'Menampilkan ' + (from + 1) + '–' + to + ' dari ' + total + ' menu';
    }

    // Page buttons
    if (btnsEl) {
      btnsEl.innerHTML = '';
      if (pages <= 1) return;
      var start = Math.max(1, page - 2);
      var end   = Math.min(pages, page + 2);
      if (page > 1) appendBtn('«', page - 1, false);
      for (var p = start; p <= end; p++) { appendBtn(String(p), p, p === page); }
      if (page < pages) appendBtn('»', page + 1, false);
    }
  }

  function appendBtn(label, toPage, isActive) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = label;
    btn.className   = 'btn btn-sm ' + (isActive ? 'btn-primary' : 'btn-outline-secondary') + ' px-2 py-0';
    btn.addEventListener('click', function () {
      currentPage = toPage;
      renderPage(getFilteredRows(), currentPage);
    });
    btnsEl.appendChild(btn);
  }

  function refresh() {
    currentPage = 1;
    renderPage(getFilteredRows(), currentPage);
  }

  if (searchEl) {
    searchEl.addEventListener('input', refresh);
  }

  // Initial render
  refresh();

  // --- AJAX Toggle ---
  document.getElementById('menu-list-tbody').addEventListener('click', function (e) {
    var btn = e.target.closest('.menu-toggle-btn');
    if (!btn) return;

    var id       = btn.dataset.id;
    var isActive = btn.dataset.active === '1';
    var label    = isActive ? 'Nonaktifkan' : 'Aktifkan';

    if (!confirm(label + ' menu ini?')) return;

    btn.disabled = true;
    var origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    $.ajax({
      url:  TOGGLE_URL + id,
      type: 'POST',
      dataType: 'json',
      headers: { 'X-Sidebar-Structure-CSRF': structureCsrfToken }
    }).done(function (resp) {
      if (!resp || !resp.ok) {
        alert(resp && resp.message ? resp.message : 'Gagal mengubah status menu.');
        btn.innerHTML = origHtml;
        btn.disabled  = false;
        return;
      }

      var nowActive = resp.is_active === 1;
      btn.dataset.active = nowActive ? '1' : '0';

      // Update button style & icon
      btn.className = 'btn btn-xs p-1 menu-toggle-btn finance-icon-action ' + (nowActive ? 'btn-outline-warning' : 'btn-outline-success');
      btn.title     = nowActive ? 'Nonaktifkan' : 'Aktifkan';
      btn.innerHTML = '<i class="ri ' + (nowActive ? 'ri-toggle-line' : 'ri-toggle-fill') + '"></i>';
      btn.disabled  = false;

      // Update status badge
      var row    = btn.closest('.menu-row');
      var badge  = row ? row.querySelector('.menu-status-badge') : null;
      if (badge) {
        badge.textContent   = nowActive ? 'Aktif' : 'Nonaktif';
        badge.dataset.active = nowActive ? '1' : '0';
        badge.className     = 'menu-status-badge badge ' + (nowActive
          ? 'bg-success-subtle text-success border border-success-subtle'
          : 'bg-secondary-subtle text-secondary border');
      }
      if (row) {
        row.dataset.active = nowActive ? '1' : '0';
      }
    }).fail(function (xhr) {
      var msg = 'Gagal mengubah status menu.';
      try { var r = JSON.parse(xhr.responseText); if (r && r.message) msg = r.message; } catch(ex) {}
      alert(msg);
      btn.innerHTML = origHtml;
      btn.disabled  = false;
    });
  });

})();
</script>
