<?php
/**
 * roles/matrix_layout.php — Konfigurasi pengelompokan halaman di matrix role.
 * Terpisah dari per-role editor; berlaku global untuk semua role.
 *
 * $pages_grouped : ['GRUP' => [['id','page_code','page_name','module','is_active','matrix_group','menu_label','menu_url','has_menu'], ...]]
 * $all_groups    : string[]  — semua kode grup yang tersedia
 * $has_group_col : bool      — apakah kolom matrix_group sudah ada di sys_page
 * $total_pages   : int       — total semua page (aktif + nonaktif)
 */
$pagesGrouped = $pages_grouped ?? [];
$allGroups    = $all_groups    ?? [];
$hasGroupCol  = !empty($has_group_col);
$totalPages   = (int)($total_pages ?? 0);
$groupMeta    = $group_meta ?? [];

// Reuse same mod_meta palette as matrix.php
$defaultModMeta = [
    'DASHBOARD'  => ['icon' => 'ri-dashboard-line',           'color' => '#1d4ed8', 'bg' => '#eff6ff'],
    'AUTH'       => ['icon' => 'ri-lock-password-line',       'color' => '#2563eb', 'bg' => '#eff6ff'],
    'SISTEM'     => ['icon' => 'ri-settings-3-line',          'color' => '#475569', 'bg' => '#f1f5f9'],
    'SYS'        => ['icon' => 'ri-settings-3-line',          'color' => '#475569', 'bg' => '#f1f5f9'],
    'MASTER'     => ['icon' => 'ri-database-2-line',          'color' => '#7c3aed', 'bg' => '#f5f3ff'],
    'PURCHASE'   => ['icon' => 'ri-shopping-cart-2-line',     'color' => '#ea580c', 'bg' => '#fff7ed'],
    'INVENTORY'  => ['icon' => 'ri-archive-drawer-line',      'color' => '#059669', 'bg' => '#f0fdf4'],
    'PRODUKSI'   => ['icon' => 'ri-flask-line',               'color' => '#0f766e', 'bg' => '#ecfeff'],
    'POS'        => ['icon' => 'ri-store-2-line',             'color' => '#be185d', 'bg' => '#fdf2f8'],
    'HR'         => ['icon' => 'ri-team-line',                'color' => '#0284c7', 'bg' => '#f0f9ff'],
    'ATTENDANCE' => ['icon' => 'ri-calendar-check-line',      'color' => '#b45309', 'bg' => '#fffbeb'],
    'PAYROLL'    => ['icon' => 'ri-money-dollar-circle-line', 'color' => '#0d9488', 'bg' => '#f0fdfa'],
    'FINANCE'    => ['icon' => 'ri-bank-line',                'color' => '#be123c', 'bg' => '#fff1f2'],
    'REPORT'     => ['icon' => 'ri-bar-chart-2-line',         'color' => '#6d28d9', 'bg' => '#faf5ff'],
    'MY_PORTAL'  => ['icon' => 'ri-user-settings-line',       'color' => '#4338ca', 'bg' => '#eef2ff'],
];
$modMeta = $defaultModMeta;
foreach ($groupMeta as $groupCode => $meta) {
    $modMeta[$groupCode] = [
        'icon' => $meta['icon'] ?? ($modMeta[$groupCode]['icon'] ?? 'ri-folder-line'),
        'color' => $meta['color'] ?? ($modMeta[$groupCode]['color'] ?? '#64748b'),
        'bg' => $meta['bg_color'] ?? ($modMeta[$groupCode]['bg'] ?? '#f8fafc'),
        'label' => $meta['group_label'] ?? $groupCode,
        'sort_order' => $meta['sort_order'] ?? 9999,
    ];
}
?>

<style>
/* ── Header bar ─────────────────────────────────────────── */
.mgl-topbar { background:#fff; border:1px solid #e9ecef; border-radius:12px; padding:14px 18px; margin-bottom:16px; }

/* ── Toolbar ─────────────────────────────────────────────── */
.mgl-toolbar { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:10px 14px; margin-bottom:14px; gap:8px; }

/* ── Group card ─────────────────────────────────────────── */
.mgl-group { border:1px solid #e9ecef; border-radius:12px; margin-bottom:10px; overflow:hidden; }
.mgl-group-header {
  display:flex; align-items:center; gap:10px; padding:10px 14px;
  cursor:pointer; user-select:none; transition:background 0.15s;
}
.mgl-group-header:hover { filter:brightness(0.97); }
.mgl-group-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; }
.mgl-group-label { font-weight:700; font-size:.85rem; }
.mgl-group-count { font-size:.72rem; opacity:.65; }
.mgl-chevron { transition:transform .2s; color:#94a3b8; font-size:.85rem; flex-shrink:0; }
.mgl-chevron.open { transform:rotate(180deg); }

/* ── Page rows (CSS grid) ───────────────────────────────── */
.mgl-page-list { border-top:1px solid #f1f5f9; }
.mgl-list-header,
.mgl-page-row {
  display:grid;
  grid-template-columns: 1fr 80px minmax(100px,180px) 120px 175px;
  align-items:center;
  gap:8px;
  padding:6px 14px;
}
.mgl-list-header {
  background:#f8fafc;
  border-bottom:1px solid #e9ecef;
  font-size:.68rem;
  font-weight:700;
  color:#64748b;
  text-transform:uppercase;
  letter-spacing:.04em;
}
.mgl-page-row { border-bottom:1px solid #f8fafc; transition:background .1s; }
.mgl-page-row:last-child { border-bottom:none; }
.mgl-page-row:hover { background:#f8fafc; }

.mgl-page-name { font-size:.82rem; font-weight:600; color:#1e293b; }
.mgl-page-code { font-size:.68rem; color:#94a3b8; font-family:monospace; }
.mgl-page-badge { display:inline-flex; align-items:center; gap:3px; padding:1px 7px; border-radius:999px; font-size:.67rem; font-weight:700; border:1px solid transparent; }
.mgl-page-badge.menu     { background:#ecfdf5; color:#166534; border-color:#bbf7d0; }
.mgl-page-badge.tech     { background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
.mgl-page-badge.active   { background:#f0fdf4; color:#15803d; border-color:#86efac; }
.mgl-page-badge.inactive { background:#f8fafc; color:#94a3b8; border-color:#cbd5e1; }

/* Inactive rows */
.mgl-page-row.is-inactive { opacity:.6; }
.mgl-page-row.is-inactive .mgl-page-name { text-decoration:line-through; color:#94a3b8; }

/* URL cell */
.mgl-url-link   { font-size:.72rem; color:#6366f1; font-family:monospace; text-decoration:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; }
.mgl-url-link:hover { text-decoration:underline; }
.mgl-url-parent { font-size:.7rem; color:#94a3b8; }
.mgl-url-none   { color:#e2e8f0; }

/* Status + toggle */
.mgl-status-cell { display:flex; align-items:center; gap:5px; }
.mgl-toggle-btn  { background:none; border:none; padding:0 2px; cursor:pointer; line-height:1; }
.mgl-toggle-btn .ri-toggle-fill { color:#22c55e; font-size:1.25rem; }
.mgl-toggle-btn .ri-toggle-line { color:#cbd5e1; font-size:1.25rem; }
.mgl-toggle-btn:hover .ri-toggle-fill { color:#16a34a; }
.mgl-toggle-btn:hover .ri-toggle-line { color:#94a3b8; }
.mgl-toggle-btn.saving { opacity:.4; pointer-events:none; }

/* ── Group selector ─────────────────────────────────────── */
.mgl-group-select-wrap { display:flex; align-items:center; gap:4px; }
.mgl-group-select { font-size:.78rem; padding:3px 6px; border-radius:8px; border:1px solid #e2e8f0; background:#fff; height:28px; width:100%; transition:border-color .15s; cursor:pointer; }
.mgl-group-select:focus { border-color:#6366f1; outline:none; box-shadow:0 0 0 2px rgba(99,102,241,.15); }
.mgl-group-select.saving { opacity:.6; pointer-events:none; }
.mgl-new-group-row { display:none; align-items:center; gap:6px; margin-top:4px; }
.mgl-new-group-row.show { display:flex; }
.mgl-save-indicator { font-size:.72rem; color:#22c55e; display:none; }
.mgl-save-indicator.show { display:inline; }
.mgl-err-indicator  { font-size:.72rem; color:#ef4444; display:none; }
.mgl-err-indicator.show  { display:inline; }

/* ── No-match placeholder ───────────────────────────────── */
.mgl-no-match { display:none; padding:16px 14px; text-align:center; color:#94a3b8; font-size:.8rem; }

/* ── Migration warning ──────────────────────────────────── */
.mgl-migration-warn { background:#fffbeb; border:1px solid #fcd34d; border-radius:10px; padding:12px 16px; margin-bottom:14px; font-size:.84rem; }
</style>

<!-- ── TOP BAR ──────────────────────────────────────────────────── -->
<div class="mgl-topbar d-flex align-items-center gap-3 flex-wrap">
  <a href="<?= base_url('roles') ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">
    <i class="ri ri-arrow-left-line"></i>
  </a>
  <div style="flex:1; min-width:200px;">
    <div class="fw-bold" style="font-size:1rem;">
      <i class="ri ri-layout-grid-line me-1 text-primary"></i>
      Konfigurasi Grup Matrix Role
    </div>
    <div class="small text-muted mt-1">
      Atur pengelompokan halaman di matrix izin — berlaku untuk <strong>semua role</strong>.
      Perubahan tersimpan per-halaman secara AJAX.
    </div>
  </div>
  <div class="text-end flex-shrink-0">
    <?php
      $activeCount = 0;
      foreach ($pagesGrouped as $grpPages) {
          foreach ($grpPages as $p) { if (!empty($p['is_active'])) $activeCount++; }
      }
    ?>
    <div style="font-size:1.5rem; font-weight:800; line-height:1; color:#1e293b;"><?= $totalPages ?></div>
    <div id="mgl-stats" style="font-size:.68rem; color:#94a3b8;">
      <?= $activeCount ?> aktif · <?= $totalPages - $activeCount ?> nonaktif · <?= count($pagesGrouped) ?> grup
    </div>
  </div>
</div>

<?php if (!$hasGroupCol): ?>
<div class="mgl-migration-warn">
  <i class="ri ri-alert-line me-2 text-warning"></i>
  <strong>Kolom <code>matrix_group</code> belum ada di tabel <code>sys_page</code>.</strong>
  Jalankan <code>docs/sidebar_roles_improvements.sql</code> terlebih dahulu agar fitur ini berfungsi.
  Saat ini halaman dikelompokkan berdasarkan kolom <code>module</code> (read-only).
</div>
<?php endif; ?>

<!-- ── TOOLBAR ──────────────────────────────────────────────────── -->
<div class="mgl-toolbar d-flex align-items-center flex-wrap">
  <div class="position-relative me-auto" style="min-width:200px; max-width:280px;">
    <i class="ri ri-search-line position-absolute" style="left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none;"></i>
    <input type="text" id="mgl-search" class="form-control form-control-sm" style="padding-left:32px; border-radius:8px;"
           placeholder="Cari nama halaman / kode / grup…">
  </div>

  <?php if ($hasGroupCol): ?>
  <div class="d-flex align-items-center gap-2 ms-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-expand-all-mgl">
      <i class="ri ri-expand-up-down-line me-1"></i><span class="d-none d-sm-inline">Buka Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-collapse-all-mgl">
      <i class="ri ri-contract-up-down-line me-1"></i><span class="d-none d-sm-inline">Tutup Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-group-mgl">
      <i class="ri ri-folder-add-line me-1"></i>Grup Baru
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- ── NEW GROUP INLINE FORM (hidden by default) ────────────────── -->
<?php if ($hasGroupCol): ?>
<div id="mgl-new-group-form" class="card border-primary mb-3" style="display:none;">
  <div class="card-body py-2 px-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <label class="small fw-semibold mb-0">Nama Grup Baru:</label>
      <input type="text" id="mgl-new-group-input" class="form-control form-control-sm"
             style="max-width:200px; font-family:monospace; text-transform:uppercase;"
             placeholder="CONTOH: KITCHEN, FINANCE2">
      <div class="small text-muted">Gunakan UPPERCASE. Halaman bisa dipindah ke grup ini setelah dibuat.</div>
      <button type="button" class="btn btn-sm btn-primary" id="btn-confirm-new-group">Tambahkan</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-cancel-new-group">Batal</button>
    </div>
  </div>
</div>
<div id="mgl-groups-container">
<?php else: ?>
<div id="mgl-groups-container">
<?php endif; ?>

<?php foreach ($pagesGrouped as $grp => $pages):
  $meta   = $modMeta[$grp] ?? ['icon' => 'ri-apps-line', 'color' => '#64748b', 'bg' => '#f8fafc', 'label' => $grp];
  $grpId  = 'mgl-grp-' . preg_replace('/[^a-z0-9]/i', '-', strtolower($grp));
  $isOpen = true;
?>
<div class="mgl-group" data-group="<?= htmlspecialchars($grp) ?>" id="grp-card-<?= htmlspecialchars($grpId) ?>">

  <div class="mgl-group-header" data-bs-toggle="collapse" data-bs-target="#<?= $grpId ?>"
       aria-expanded="true" style="background:<?= $meta['bg'] ?>;">
    <div class="mgl-group-icon" style="background:<?= $meta['color'] ?>1a; color:<?= $meta['color'] ?>;">
      <i class="ri <?= $meta['icon'] ?>"></i>
    </div>
    <div style="flex:1; min-width:80px;">
      <div class="mgl-group-label" style="color:<?= $meta['color'] ?>;"><?= htmlspecialchars($meta['label']) ?></div>
      <div class="mgl-group-count"><?= htmlspecialchars($grp) ?> · <?= count($pages) ?> halaman</div>
    </div>
    <i class="ri ri-arrow-down-s-line mgl-chevron open"></i>
  </div>

  <div id="<?= $grpId ?>" class="collapse show">
    <div class="mgl-page-list">

      <!-- Header row -->
      <div class="mgl-list-header">
        <div>Halaman</div>
        <div>Sidebar</div>
        <div>URL / Akses</div>
        <div>Status</div>
        <div><?= $hasGroupCol ? 'Pindah Grup' : 'Grup' ?></div>
      </div>

      <?php foreach ($pages as $pg):
        $hasMenu     = !empty($pg['has_menu']);
        $isActive    = !empty($pg['is_active']);
        $menuLabel   = trim((string)($pg['menu_label'] ?? ''));
        $menuUrl     = trim((string)($pg['menu_url']   ?? ''));
        $displayName = $menuLabel !== '' ? $menuLabel : (string)$pg['page_name'];
        $currentGrp  = $hasGroupCol && !empty($pg['matrix_group']) ? (string)$pg['matrix_group'] : (string)$pg['module'];
        $statusText  = $isActive ? 'aktif' : 'nonaktif';
        $isParentUrl = ($menuUrl === '#');
        $hasRealUrl  = ($menuUrl !== '' && !$isParentUrl);
      ?>
      <div class="mgl-page-row<?= $isActive ? '' : ' is-inactive' ?>"
           data-page-code="<?= htmlspecialchars((string)$pg['page_code']) ?>"
           data-group="<?= htmlspecialchars($currentGrp) ?>"
           data-search="<?= htmlspecialchars(strtolower($displayName . ' ' . $pg['page_code'] . ' ' . $pg['module'] . ' ' . $currentGrp . ' ' . $statusText)) ?>">

        <!-- Col 1: Halaman -->
        <div>
          <div class="mgl-page-name"><?= htmlspecialchars($displayName) ?></div>
          <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
            <span class="mgl-page-code"><?= htmlspecialchars((string)$pg['page_code']) ?></span>
            <?php if ($pg['module'] !== $currentGrp): ?>
              <span style="font-size:.65rem; color:#cbd5e1;">mod: <?= htmlspecialchars($pg['module']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Col 2: Sidebar badge -->
        <div>
          <span class="mgl-page-badge <?= $hasMenu ? 'menu' : 'tech' ?>">
            <i class="ri <?= $hasMenu ? 'ri-menu-line' : 'ri-code-s-slash-line' ?>"></i>
            <?= $hasMenu ? 'Menu' : 'Teknis' ?>
          </span>
        </div>

        <!-- Col 3: URL -->
        <div>
          <?php if ($hasRealUrl): ?>
            <a href="<?= base_url(ltrim($menuUrl, '/')) ?>" target="_blank" class="mgl-url-link" title="<?= htmlspecialchars($menuUrl) ?>">
              <i class="ri ri-external-link-line me-1"></i><?= htmlspecialchars($menuUrl) ?>
            </a>
          <?php elseif ($isParentUrl): ?>
            <span class="mgl-url-parent" title="Menu induk — URL navigasi ada di sub-menu"><i class="ri ri-folder-open-line me-1"></i>Induk menu</span>
          <?php else: ?>
            <span class="mgl-url-none">—</span>
          <?php endif; ?>
        </div>

        <!-- Col 4: Status + toggle -->
        <div class="mgl-status-cell">
          <span class="mgl-page-badge mgl-status-badge <?= $isActive ? 'active' : 'inactive' ?>">
            <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
          </span>
          <button type="button"
                  class="mgl-toggle-btn"
                  data-page-code="<?= htmlspecialchars((string)$pg['page_code']) ?>"
                  data-state="<?= $isActive ? '1' : '0' ?>"
                  title="<?= $isActive ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan' ?>">
            <i class="ri <?= $isActive ? 'ri-toggle-fill' : 'ri-toggle-line' ?>"></i>
          </button>
        </div>

        <!-- Col 5: Group selector -->
        <div>
          <?php if ($hasGroupCol): ?>
            <div class="mgl-group-select-wrap">
              <select class="mgl-group-select"
                      data-page-code="<?= htmlspecialchars((string)$pg['page_code']) ?>"
                      data-current-group="<?= htmlspecialchars($currentGrp) ?>">
                <option value="<?= htmlspecialchars($currentGrp) ?>" selected disabled><?= htmlspecialchars($currentGrp) ?></option>
                <?php foreach ($allGroups as $g): if ($g === $currentGrp) continue; ?>
                  <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                <?php endforeach; ?>
                <option value="__new__">+ Grup baru…</option>
              </select>
              <span class="mgl-save-indicator"><i class="ri ri-check-line"></i></span>
              <span class="mgl-err-indicator"><i class="ri ri-error-warning-line"></i></span>
            </div>
          <?php else: ?>
            <span class="badge bg-light text-dark border" style="font-size:.72rem;"><?= htmlspecialchars($currentGrp) ?></span>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
      <div class="mgl-no-match">Tidak ada halaman yang cocok.</div>
    </div>
  </div>

</div>
<?php endforeach; ?>

<?php if (empty($pagesGrouped)): ?>
<div class="text-center text-muted py-5">
  <i class="ri ri-pages-line" style="font-size:2rem;"></i>
  <div class="mt-2">Belum ada halaman terdaftar di <code>sys_page</code>.</div>
</div>
<?php endif; ?>

</div><!-- #mgl-groups-container -->

<script>
(function () {
  'use strict';

  var SAVE_URL        = <?= json_encode(base_url('roles/save-page-group')) ?>;
  var TOGGLE_PAGE_URL = <?= json_encode(base_url('roles/toggle-page-active')) ?>;
  var hasGroupCol     = <?= $hasGroupCol ? 'true' : 'false' ?>;

  // ── Chevron sync ─────────────────────────────────────────────
  document.querySelectorAll('.mgl-group .collapse').forEach(function (el) {
    el.addEventListener('show.bs.collapse', function () {
      var ch = el.closest('.mgl-group').querySelector('.mgl-chevron');
      if (ch) ch.classList.add('open');
    });
    el.addEventListener('hide.bs.collapse', function () {
      var ch = el.closest('.mgl-group').querySelector('.mgl-chevron');
      if (ch) ch.classList.remove('open');
    });
  });

  // ── Expand / Collapse all ─────────────────────────────────────
  var expandBtn   = document.getElementById('btn-expand-all-mgl');
  var collapseBtn = document.getElementById('btn-collapse-all-mgl');
  if (expandBtn) {
    expandBtn.addEventListener('click', function () {
      document.querySelectorAll('.mgl-group .collapse').forEach(function (el) {
        bootstrap.Collapse.getOrCreateInstance(el).show();
      });
    });
  }
  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      document.querySelectorAll('.mgl-group .collapse').forEach(function (el) {
        bootstrap.Collapse.getOrCreateInstance(el).hide();
      });
    });
  }

  // ── Search ───────────────────────────────────────────────────
  var searchEl = document.getElementById('mgl-search');
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      document.querySelectorAll('.mgl-group').forEach(function (grpEl) {
        var rows    = grpEl.querySelectorAll('.mgl-page-row');
        var visible = 0;
        rows.forEach(function (row) {
          var match = !q || (row.dataset.search || '').indexOf(q) !== -1;
          row.style.display = match ? '' : 'none';
          if (match) visible++;
        });
        var noMatch = grpEl.querySelector('.mgl-no-match');
        if (noMatch) noMatch.style.display = visible === 0 ? 'block' : 'none';
        grpEl.style.display = (visible === 0 && q) ? 'none' : '';
        // Auto-expand when searching
        if (q && visible > 0) {
          var colEl = grpEl.querySelector('.collapse');
          if (colEl) bootstrap.Collapse.getOrCreateInstance(colEl).show();
        }
      });
    });
  }

  // ── Grup Baru form ────────────────────────────────────────────
  var addGroupBtn    = document.getElementById('btn-add-group-mgl');
  var newGroupForm   = document.getElementById('mgl-new-group-form');
  var newGroupInput  = document.getElementById('mgl-new-group-input');
  var confirmNewBtn  = document.getElementById('btn-confirm-new-group');
  var cancelNewBtn   = document.getElementById('btn-cancel-new-group');

  if (addGroupBtn && newGroupForm) {
    addGroupBtn.addEventListener('click', function () {
      newGroupForm.style.display = '';
      if (newGroupInput) newGroupInput.focus();
    });
  }
  if (cancelNewBtn && newGroupForm) {
    cancelNewBtn.addEventListener('click', function () {
      newGroupForm.style.display = 'none';
      if (newGroupInput) newGroupInput.value = '';
    });
  }
  if (confirmNewBtn) {
    confirmNewBtn.addEventListener('click', function () {
      if (!newGroupInput) return;
      var newGrp = newGroupInput.value.trim().toUpperCase();
      if (!newGrp) { newGroupInput.focus(); return; }

      // Inject new group into all selects (before __new__ option)
      document.querySelectorAll('.mgl-group-select').forEach(function (sel) {
        // Skip if option already exists
        var exists = Array.from(sel.options).some(function (o) { return o.value === newGrp; });
        if (!exists) {
          var opt = new Option(newGrp, newGrp);
          var newOpt = sel.querySelector('option[value="__new__"]');
          if (newOpt) sel.insertBefore(opt, newOpt);
          else sel.appendChild(opt);
        }
      });

      // Create a new group card in the DOM (empty placeholder)
      addGroupCardToDOM(newGrp);

      newGroupForm.style.display = 'none';
      newGroupInput.value = '';
    });
  }

  function addGroupCardToDOM(grpCode) {
    var container = document.getElementById('mgl-groups-container');
    if (!container) return;
    // Check if already exists
    if (document.querySelector('.mgl-group[data-group="' + grpCode + '"]')) return;

    var grpId = 'mgl-grp-' + grpCode.toLowerCase().replace(/[^a-z0-9]/g, '-');
    var card = document.createElement('div');
    card.className = 'mgl-group';
    card.dataset.group = grpCode;
    card.innerHTML =
      '<div class="mgl-group-header" data-bs-toggle="collapse" data-bs-target="#' + grpId + '" aria-expanded="true" style="background:#f8fafc;">' +
        '<div class="mgl-group-icon" style="background:#64748b1a; color:#64748b;"><i class="ri ri-folder-line"></i></div>' +
        '<div style="flex:1;"><div class="mgl-group-label" style="color:#64748b;">' + grpCode + '</div><div class="mgl-group-count">0 halaman</div></div>' +
        '<i class="ri ri-arrow-down-s-line mgl-chevron open"></i>' +
      '</div>' +
      '<div id="' + grpId + '" class="collapse show">' +
        '<div class="mgl-page-list">' +
          '<div class="mgl-list-header"><div>Halaman</div><div>Sidebar</div><div>URL / Akses</div><div>Status</div><div>Pindah Grup</div></div>' +
          '<div class="mgl-no-match" style="display:block;">Belum ada halaman. Pindahkan halaman dari grup lain ke sini.</div>' +
        '</div>' +
      '</div>';

    container.appendChild(card);

    // Wire chevron sync
    var colEl = card.querySelector('.collapse');
    if (colEl) {
      colEl.addEventListener('show.bs.collapse', function () {
        var ch = card.querySelector('.mgl-chevron');
        if (ch) ch.classList.add('open');
      });
      colEl.addEventListener('hide.bs.collapse', function () {
        var ch = card.querySelector('.mgl-chevron');
        if (ch) ch.classList.remove('open');
      });
    }
  }

  // ── Group select change (move page) ───────────────────────────
  if (hasGroupCol) {
    document.addEventListener('change', function (e) {
      var sel = e.target;
      if (!sel.classList.contains('mgl-group-select')) return;

      var selectedVal = sel.value;
      if (!selectedVal) return;

      var wrap     = sel.closest('.mgl-group-select-wrap');
      var saveInd  = wrap ? wrap.querySelector('.mgl-save-indicator') : null;
      var errInd   = wrap ? wrap.querySelector('.mgl-err-indicator')  : null;
      var pageCode = sel.dataset.pageCode;
      var oldGroup = sel.dataset.currentGroup;

      // "Grup baru..." option — ask for name
      if (selectedVal === '__new__') {
        var newName = prompt('Nama grup baru (UPPERCASE):', '');
        if (!newName || !newName.trim()) { sel.value = oldGroup; return; }
        newName = newName.trim().toUpperCase();

        // Add new group to this select and all other selects
        document.querySelectorAll('.mgl-group-select').forEach(function (s) {
          var exists = Array.from(s.options).some(function (o) { return o.value === newName; });
          if (!exists) {
            var opt = new Option(newName, newName);
            var newOpt = s.querySelector('option[value="__new__"]');
            if (newOpt) s.insertBefore(opt, newOpt);
            else s.appendChild(opt);
          }
        });
        addGroupCardToDOM(newName);
        sel.value = newName;
        selectedVal = newName;
      }

      saveGroup(sel, pageCode, oldGroup, selectedVal, saveInd, errInd);
    });
  }

  function saveGroup(sel, pageCode, oldGroup, newGroup, saveInd, errInd) {
    if (saveInd) { saveInd.classList.remove('show'); }
    if (errInd)  { errInd.classList.remove('show'); }
    sel.classList.add('saving');

    $.post(SAVE_URL, { page_code: pageCode, group_code: newGroup }, null, 'json')
      .done(function (resp) {
        sel.classList.remove('saving');
        if (!resp || !resp.ok) {
          if (errInd) errInd.classList.add('show');
          sel.value = oldGroup;
          return;
        }

        // ── Move page row DOM to target group card ────────────
        var pageRow     = sel.closest('.mgl-page-row');
        var targetCard  = document.querySelector('.mgl-group[data-group="' + CSS.escape(newGroup) + '"]');
        var targetList  = targetCard ? targetCard.querySelector('.mgl-page-list') : null;

        if (pageRow && targetList) {
          // Update row's data attributes
          pageRow.dataset.group  = newGroup;
          pageRow.dataset.search = pageRow.dataset.search.replace(
            new RegExp('\\b' + oldGroup.toLowerCase() + '\\b'), newGroup.toLowerCase()
          );

          // Update the select in the moved row
          var movedSel = pageRow.querySelector('.mgl-group-select');
          if (movedSel) {
            movedSel.dataset.currentGroup = newGroup;
            // Swap: current group becomes new, add old group option if not present
            Array.from(movedSel.options).forEach(function (o) {
              if (o.value === newGroup) { o.selected = true; o.disabled = true; }
              else { o.disabled = false; }
            });
            // Add old group to moved row's select if not present
            var hasOld = Array.from(movedSel.options).some(function (o) { return o.value === oldGroup; });
            if (!hasOld) {
              var opt = new Option(oldGroup, oldGroup);
              var newOpt = movedSel.querySelector('option[value="__new__"]');
              if (newOpt) movedSel.insertBefore(opt, newOpt);
              else movedSel.appendChild(opt);
            }
          }

          // Insert before .mgl-no-match
          var noMatch = targetList.querySelector('.mgl-no-match');
          if (noMatch) targetList.insertBefore(pageRow, noMatch);
          else targetList.appendChild(pageRow);

          // Update group counts
          updateGroupCount(oldGroup);
          updateGroupCount(newGroup);

          // Expand target
          var targetCollapse = targetCard ? targetCard.querySelector('.collapse') : null;
          if (targetCollapse) bootstrap.Collapse.getOrCreateInstance(targetCollapse).show();

          // Show noMatch in old group if empty
          var oldCard = document.querySelector('.mgl-group[data-group="' + CSS.escape(oldGroup) + '"]');
          var oldList = oldCard ? oldCard.querySelector('.mgl-page-list') : null;
          if (oldList) {
            var remaining = oldList.querySelectorAll('.mgl-page-row').length;
            var oldNoMatch = oldList.querySelector('.mgl-no-match');
            if (oldNoMatch) oldNoMatch.style.display = remaining === 0 ? 'block' : 'none';
          }
          if (noMatch) noMatch.style.display = 'none';
        }

        if (saveInd) {
          saveInd.classList.add('show');
          setTimeout(function () { saveInd.classList.remove('show'); }, 2000);
        }
      })
      .fail(function (xhr) {
        sel.classList.remove('saving');
        if (errInd) {
          var msg = 'Gagal';
          try { var r = JSON.parse(xhr.responseText); if (r && r.message) msg = r.message; } catch(ex) {}
          errInd.textContent = msg;
          errInd.classList.add('show');
        }
        sel.value = oldGroup;
      });
  }

  function updateGroupCount(grpCode) {
    var card  = document.querySelector('.mgl-group[data-group="' + CSS.escape(grpCode) + '"]');
    if (!card) return;
    var count = card.querySelectorAll('.mgl-page-row').length;
    var el    = card.querySelector('.mgl-group-count');
    if (el) el.textContent = count + ' halaman';
  }

  // ── Summary stats ─────────────────────────────────────────────
  function updateSummaryStats() {
    var allRows     = document.querySelectorAll('.mgl-page-row');
    var active      = 0, inactive = 0;
    allRows.forEach(function (r) {
      if (r.classList.contains('is-inactive')) inactive++; else active++;
    });
    var el = document.getElementById('mgl-stats');
    if (el) el.textContent = active + ' aktif · ' + inactive + ' nonaktif · <?= count($pagesGrouped) ?> grup';
  }

  // ── Page active toggle ────────────────────────────────────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.mgl-toggle-btn');
    if (!btn) return;

    var pageCode = btn.dataset.pageCode;
    var row      = btn.closest('.mgl-page-row');

    btn.classList.add('saving');

    $.post(TOGGLE_PAGE_URL, { page_code: pageCode }, null, 'json')
      .done(function (resp) {
        btn.classList.remove('saving');
        if (!resp || !resp.ok) return;

        var isActive = resp.is_active === 1;

        // Update toggle button
        btn.dataset.state = isActive ? '1' : '0';
        btn.title = isActive ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan';
        var icon = btn.querySelector('i');
        if (icon) icon.className = 'ri ' + (isActive ? 'ri-toggle-fill' : 'ri-toggle-line');

        // Update row class + name styling
        if (row) {
          row.classList.toggle('is-inactive', !isActive);
          var search = row.dataset.search || '';
          row.dataset.search = search.replace(/\b(aktif|nonaktif)\b/, isActive ? 'aktif' : 'nonaktif');
          // Update status badge
          var badge = row.querySelector('.mgl-status-badge');
          if (badge) {
            badge.className = 'mgl-page-badge mgl-status-badge ' + (isActive ? 'active' : 'inactive');
            badge.textContent = isActive ? 'Aktif' : 'Nonaktif';
          }
        }

        updateSummaryStats();
      })
      .fail(function () { btn.classList.remove('saving'); });
  });

}());
</script>
