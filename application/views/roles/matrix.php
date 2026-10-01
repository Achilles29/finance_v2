<?php
/**
 * roles/matrix.php — Matrix izin per halaman, grouped & collapsible per module
 * $role            : array
 * $pages_by_module : ['MODULE' => [['page_id','page_code','page_name','can_view',...], ...]]
 * $users_in_role   : array
 */
$users_in_role   = $users_in_role ?? [];
$pages_by_module = $pages_by_module ?? [];
$group_meta      = $group_meta ?? [];

// Metadata default: ikon, warna aksen, label display
$default_mod_meta = [
    'DASHBOARD'  => ['icon' => 'ri-dashboard-line',           'color' => '#1d4ed8', 'bg' => '#eff6ff', 'label' => 'Dashboard'],
    'SISTEM'     => ['icon' => 'ri-settings-3-line',          'color' => '#475569', 'bg' => '#f1f5f9', 'label' => 'Sistem & Hak Akses'],
    'AUTH'       => ['icon' => 'ri-lock-password-line',       'color' => '#2563eb', 'bg' => '#eff6ff', 'label' => 'Auth & RBAC'],
    'SYS'        => ['icon' => 'ri-settings-3-line',          'color' => '#475569', 'bg' => '#f1f5f9', 'label' => 'Sistem'],
    'MASTER'     => ['icon' => 'ri-database-2-line',          'color' => '#7c3aed', 'bg' => '#f5f3ff', 'label' => 'Master Data'],
    'PURCHASE'   => ['icon' => 'ri-shopping-cart-2-line',     'color' => '#ea580c', 'bg' => '#fff7ed', 'label' => 'Pembelian'],
    'INVENTORY'  => ['icon' => 'ri-archive-drawer-line',      'color' => '#059669', 'bg' => '#f0fdf4', 'label' => 'Inventori'],
    'HR'         => ['icon' => 'ri-team-line',                'color' => '#0284c7', 'bg' => '#f0f9ff', 'label' => 'HR & Organisasi'],
    'PAYROLL'    => ['icon' => 'ri-money-dollar-circle-line', 'color' => '#0d9488', 'bg' => '#f0fdfa', 'label' => 'Payroll'],
    'ATTENDANCE' => ['icon' => 'ri-calendar-check-line',      'color' => '#b45309', 'bg' => '#fffbeb', 'label' => 'Absensi'],
    'FINANCE'    => ['icon' => 'ri-bank-line',                'color' => '#be123c', 'bg' => '#fff1f2', 'label' => 'Keuangan'],
    'REPORT'     => ['icon' => 'ri-bar-chart-2-line',         'color' => '#6d28d9', 'bg' => '#faf5ff', 'label' => 'Laporan'],
    'POS'        => ['icon' => 'ri-store-2-line',             'color' => '#be185d', 'bg' => '#fdf2f8', 'label' => 'Point of Sale'],
  'PRODUKSI'   => ['icon' => 'ri-flask-line',               'color' => '#0f766e', 'bg' => '#ecfeff', 'label' => 'Produksi'],
  'MY_PORTAL'  => ['icon' => 'ri-user-settings-line',       'color' => '#4338ca', 'bg' => '#eef2ff', 'label' => 'Portal Saya'],
];
$mod_meta = $default_mod_meta;
foreach ($group_meta as $groupCode => $meta) {
    $mod_meta[$groupCode] = [
        'icon' => $meta['icon'] ?? ($mod_meta[$groupCode]['icon'] ?? 'ri-apps-line'),
        'color' => $meta['color'] ?? ($mod_meta[$groupCode]['color'] ?? '#64748b'),
        'bg' => $meta['bg_color'] ?? ($mod_meta[$groupCode]['bg'] ?? '#f8fafc'),
        'label' => $meta['group_label'] ?? ($mod_meta[$groupCode]['label'] ?? $groupCode),
    ];
}

// Pre-hitung stats per modul & grand total
$mod_stats       = [];
$grand_pages     = 0;
$grand_active    = 0;
foreach ($pages_by_module as $mod => $pages) {
    $cnt = count($pages);
    $act = 0;
    foreach ($pages as $pg) {
        if ($pg['can_view'] || $pg['can_create'] || $pg['can_edit'] || $pg['can_delete'] || $pg['can_export']) $act++;
    }
    $mod_stats[$mod]  = ['total' => $cnt, 'active' => $act];
    $grand_pages     += $cnt;
    $grand_active    += $act;
}

// Definisi permission flags: [flag_key, label, ikon, warna saat aktif]
$perm_flags = [
  'can_view'   => ['label' => 'Lihat',  'icon' => 'ri-eye-line',            'cls' => 'ptog-view'],
    'can_create' => ['label' => 'Buat',   'icon' => 'ri-add-circle-line',     'cls' => 'ptog-create'],
  'can_edit'   => ['label' => 'Ubah',   'icon' => 'ri-edit-line',           'cls' => 'ptog-edit'],
    'can_delete' => ['label' => 'Hapus',  'icon' => 'ri-delete-bin-line',     'cls' => 'ptog-delete'],
  'can_export' => ['label' => 'Ekspor', 'icon' => 'ri-download-cloud-line', 'cls' => 'ptog-export'],
];
?>

<style>
/* ── Info bar ───────────────────────────────────────────── */
.mx-role-bar { background:#fff; border:1px solid #e9ecef; border-radius:12px; padding:14px 18px; margin-bottom:18px; }

/* ── Toolbar ────────────────────────────────────────────── */
.mx-toolbar { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:10px 14px; margin-bottom:16px; gap:8px; }

/* ── Module card ────────────────────────────────────────── */
.mx-module { border:1px solid #e9ecef; border-radius:12px; margin-bottom:12px; overflow:hidden; }
.mx-module-header {
  display:flex; align-items:center; gap:10px;
  padding:10px 14px; cursor:pointer; user-select:none;
  transition: background 0.15s;
}
.mx-module-header:hover { filter: brightness(0.97); }
.mx-mod-icon {
  width:34px; height:34px; border-radius:8px;
  display:flex; align-items:center; justify-content:center;
  font-size:1rem; flex-shrink:0;
}
.mx-mod-label { font-weight:700; font-size:0.85rem; }
.mx-mod-sub   { font-size:0.72rem; opacity:0.65; }
.mx-mod-bar-wrap { flex:1; min-width:60px; max-width:100px; }
.mx-mod-bar  { height:5px; border-radius:3px; background:#e2e8f0; overflow:hidden; }
.mx-mod-bar-fill { height:100%; border-radius:3px; transition:width 0.3s; }
.mx-mod-counter { font-size:0.7rem; font-weight:600; white-space:nowrap; }
.mx-mod-quick { display:flex; gap:4px; flex-shrink:0; }
.mx-mod-quick .btn { font-size:0.68rem; padding:2px 7px; border-radius:6px; }
.mx-chevron { transition:transform 0.2s; color:#94a3b8; font-size:0.85rem; flex-shrink:0; }
.mx-chevron.open { transform:rotate(180deg); }

/* ── Page rows ──────────────────────────────────────────── */
.mx-page-list { border-top:1px solid #f1f5f9; }
.mx-page-row {
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  padding:8px 14px; border-bottom:1px solid #f8fafc;
  transition:background 0.1s;
}
.mx-page-row:last-child { border-bottom:none; }
.mx-page-row:hover { background:#f8fafc; }
.mx-page-row.row-active { border-left:3px solid #22c55e; }
.mx-page-info { flex:1; min-width:140px; }
.mx-page-name { font-size:0.82rem; font-weight:600; color:#1e293b; line-height:1.3; }
.mx-page-meta { display:flex; gap:6px; flex-wrap:wrap; margin-top:2px; }
.mx-page-secondary { font-size:0.72rem; color:#64748b; }
.mx-page-code { font-size:0.67rem; color:#94a3b8; font-family:monospace; }
.mx-page-badge {
  display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px;
  font-size:0.67rem; font-weight:700; border:1px solid transparent;
}
.mx-page-badge.menu { background:#ecfdf5; color:#166534; border-color:#bbf7d0; }
.mx-page-badge.tech { background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
.mx-page-desc { font-size:0.69rem; color:#94a3b8; margin-top:2px; }
.mx-perms     { display:flex; gap:5px; flex-wrap:wrap; }

/* ── Permission toggle pills ────────────────────────────── */
.ptog { display:inline-flex; align-items:center; gap:3px; cursor:pointer; }
.ptog input { position:absolute; opacity:0; width:0; height:0; pointer-events:none; }
.ptog-pill {
  display:inline-flex; align-items:center; gap:3px;
  padding:3px 9px; border-radius:20px; font-size:0.68rem; font-weight:600;
  border:1.5px solid transparent; transition:all 0.15s; white-space:nowrap;
  background:#f1f5f9; color:#94a3b8; border-color:#e2e8f0;
}
.ptog:hover .ptog-pill { filter:brightness(0.94); }
/* Active states */
.ptog-view   .ptog-pill.on { background:#dbeafe; color:#1d4ed8; border-color:#93c5fd; }
.ptog-create .ptog-pill.on { background:#dcfce7; color:#15803d; border-color:#86efac; }
.ptog-edit   .ptog-pill.on { background:#fef9c3; color:#92400e; border-color:#fde047; }
.ptog-delete .ptog-pill.on { background:#fee2e2; color:#b91c1c; border-color:#fca5a5; }
.ptog-export .ptog-pill.on { background:#f3e8ff; color:#6d28d9; border-color:#d8b4fe; }

/* ── Row quick-all button ────────────────────────────────── */
.mx-row-quick { opacity:0; transition:opacity 0.15s; flex-shrink:0; display:flex; align-items:center; gap:3px; }
.mx-page-row:hover .mx-row-quick { opacity:1; }
.mx-row-quick .btn { font-size:0.65rem; padding:2px 6px; border-radius:6px; }

/* ── Sticky save bar ─────────────────────────────────────── */
.mx-save-bar {
  position:sticky; bottom:0; z-index:20;
  background:rgba(255,255,255,0.95); backdrop-filter:blur(8px);
  border-top:1px solid #e9ecef; padding:12px 18px;
  display:flex; align-items:center; gap:10px; margin-top:16px;
  border-radius:0 0 0 0;
}
.mx-save-bar .btn-save { min-width:140px; }
.mx-dirty-dot {
  display:none; width:8px; height:8px; border-radius:50%;
  background:#f59e0b; margin-right:2px; animation:pulse-dot 1s infinite;
}
.mx-dirty-dot.show { display:inline-block; }
@keyframes pulse-dot {
  0%,100% { opacity:1; }
  50%      { opacity:0.4; }
}

/* ── Search ─────────────────────────────────────────────── */
.mx-search-wrap { position:relative; }
.mx-search-wrap .ri-search-line {
  position:absolute; left:10px; top:50%; transform:translateY(-50%);
  color:#94a3b8; pointer-events:none;
}
#mx-search { padding-left:32px; border-radius:8px; font-size:0.82rem; }
.mx-no-match { display:none; padding:20px; text-align:center; color:#94a3b8; font-size:0.82rem; }

/* ── Grand counter ───────────────────────────────────────── */
.mx-grand-counter { font-size:0.78rem; color:#64748b; }
.mx-grand-counter b { color:#1e293b; }

.mx-toggle-lite { display:inline-flex; align-items:center; gap:8px; font-size:0.76rem; color:#475569; }
.mx-toggle-lite input { margin:0; }
</style>

<!-- ── ROLE INFO BAR ──────────────────────────────────────────── -->
<div class="mx-role-bar d-flex align-items-center gap-3 flex-wrap">
  <a href="<?= base_url('roles') ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">
    <i class="ri ri-arrow-left-line"></i>
  </a>
  <a href="<?= base_url('roles/matrix-groups') ?>" class="btn btn-sm btn-outline-primary flex-shrink-0" title="Konfigurasi pengelompokan halaman di matrix">
    <i class="ri ri-layout-grid-line me-1"></i><span class="d-none d-md-inline">Konfigurasi Grup</span>
  </a>
  <div style="flex:1; min-width:180px;">
    <div class="fw-bold" style="font-size:1rem;">
      <i class="ri ri-shield-keyhole-line me-1 text-primary"></i>
      Matrix Izin — <span class="text-primary"><?= htmlspecialchars($role['role_name']) ?></span>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap mt-1" style="font-size:0.75rem; color:#64748b;">
      <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;"><?= htmlspecialchars($role['role_code']) ?></code>
      <?php if (!empty($role['division_scope_id'])): ?>
        <span class="badge bg-info-subtle text-info"><i class="ri ri-building-line me-1"></i><?= htmlspecialchars($role['division_scope_name'] ?? '') ?></span>
      <?php else: ?>
        <span class="text-muted">Lintas divisi</span>
      <?php endif; ?>
      <a href="<?= base_url('roles/users/' . $role['id']) ?>" class="text-muted text-decoration-none">
        <i class="ri ri-group-line me-1"></i><?= count($users_in_role) ?> user
      </a>
    </div>
  </div>
  <div class="text-end flex-shrink-0">
    <div style="font-size:1.6rem; font-weight:800; line-height:1; color:#1e293b;" id="grand-active-num"><?= $grand_active ?></div>
    <div style="font-size:0.68rem; color:#94a3b8;">halaman aktif dari <span id="grand-total-num"><?= $grand_pages ?></span></div>
  </div>
</div>

<?php if (empty($pages_by_module)): ?>
<div class="alert alert-info border-0 shadow-sm">
  <i class="ri ri-information-line me-2"></i>
  Belum ada halaman terdaftar di <code>sys_page</code>. Jalankan seeder SQL terlebih dahulu.
</div>
<?php else: ?>

<!-- ── TOOLBAR ─────────────────────────────────────────────────── -->
<div class="mx-toolbar d-flex align-items-center flex-wrap">
  <!-- Search -->
  <div class="mx-search-wrap me-auto" style="min-width:180px; max-width:260px;">
    <i class="ri ri-search-line"></i>
    <input type="text" id="mx-search" class="form-control form-control-sm" placeholder="Cari nama menu / halaman / kode akses…">
  </div>
  <label class="mx-toggle-lite ms-2">
    <input type="checkbox" id="mx-show-technical">
    <span>Tampilkan halaman teknis / internal</span>
  </label>
  <!-- Expand/Collapse -->
  <div class="d-flex gap-2 ms-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-expand-all" title="Buka semua">
      <i class="ri ri-expand-up-down-line me-1"></i><span class="d-none d-sm-inline">Buka Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-collapse-all" title="Tutup semua">
      <i class="ri ri-contract-up-down-line me-1"></i><span class="d-none d-sm-inline">Tutup Semua</span>
    </button>
  </div>
  <!-- Check/Uncheck -->
  <div class="d-flex gap-2 ms-2">
    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-check-all-view" title="Centang View semua halaman">
      <i class="ri ri-eye-line me-1"></i><span class="d-none d-md-inline">View Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-outline-success" id="btn-check-all" title="Centang semua izin">
      <i class="ri ri-checkbox-multiple-line me-1"></i><span class="d-none d-md-inline">Centang Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="btn-uncheck-all" title="Kosongkan semua izin">
      <i class="ri ri-close-circle-line me-1"></i><span class="d-none d-md-inline">Kosongkan</span>
    </button>
  </div>
</div>

<?= form_open('roles/save_matrix/' . $role['id'], ['id' => 'form-matrix']) ?>

<!-- ── MODULE GROUPS ────────────────────────────────────────────── -->
<?php foreach ($pages_by_module as $mod => $pages):
  $meta    = $mod_meta[$mod] ?? ['icon' => 'ri-apps-line', 'color' => '#64748b', 'bg' => '#f8fafc', 'label' => $mod];
  $stats   = $mod_stats[$mod];
  $pct     = $stats['total'] > 0 ? round($stats['active'] / $stats['total'] * 100) : 0;
  $mod_id  = 'mx-mod-' . preg_replace('/[^a-z0-9]/i', '-', strtolower($mod));
  // Modul dengan izin aktif = terbuka by default
  $is_open = $stats['active'] > 0;
?>
<div class="mx-module" data-module="<?= htmlspecialchars($mod) ?>">

  <!-- Module Header (toggle) -->
  <div class="mx-module-header" data-bs-toggle="collapse" data-bs-target="#<?= $mod_id ?>"
       aria-expanded="<?= $is_open ? 'true' : 'false' ?>"
       style="background:<?= $meta['bg'] ?>;">
    <!-- Ikon modul -->
    <div class="mx-mod-icon" style="background:<?= $meta['color'] ?>1a; color:<?= $meta['color'] ?>;">
      <i class="ri <?= $meta['icon'] ?>"></i>
    </div>
    <!-- Label + kode -->
    <div style="flex:1; min-width:100px;">
      <div class="mx-mod-label" style="color:<?= $meta['color'] ?>;"><?= htmlspecialchars($meta['label']) ?></div>
      <div class="mx-mod-sub"><?= htmlspecialchars($mod) ?></div>
    </div>
    <!-- Progress bar -->
    <div class="mx-mod-bar-wrap">
      <div class="mx-mod-bar">
        <div class="mx-mod-bar-fill" data-mod="<?= htmlspecialchars($mod) ?>"
             style="width:<?= $pct ?>%; background:<?= $meta['color'] ?>;"></div>
      </div>
    </div>
    <!-- Counter -->
    <div class="mx-mod-counter" style="color:<?= $meta['color'] ?>;">
      <span class="mod-active-num" data-mod="<?= htmlspecialchars($mod) ?>"><?= $stats['active'] ?></span>/<span class="mod-total-num" data-mod="<?= htmlspecialchars($mod) ?>"><?= $stats['total'] ?></span>
    </div>
    <!-- Quick buttons -->
    <div class="mx-mod-quick" onclick="event.stopPropagation();">
      <button type="button" class="btn btn-outline-secondary module-check-view" data-module="<?= htmlspecialchars($mod) ?>">View</button>
      <button type="button" class="btn btn-outline-success module-check-all" data-module="<?= htmlspecialchars($mod) ?>">Semua</button>
      <button type="button" class="btn btn-outline-danger module-uncheck" data-module="<?= htmlspecialchars($mod) ?>">×</button>
    </div>
    <!-- Chevron -->
    <i class="ri ri-arrow-down-s-line mx-chevron <?= $is_open ? 'open' : '' ?>"></i>
  </div>

  <!-- Module Body (collapsible) -->
  <div id="<?= $mod_id ?>" class="collapse <?= $is_open ? 'show' : '' ?>">
    <div class="mx-page-list">
      <?php foreach ($pages as $pg):
        $pid     = $pg['page_id'];
        $row_act = $pg['can_view'] || $pg['can_create'] || $pg['can_edit'] || $pg['can_delete'] || $pg['can_export'];
        $displayName = trim((string)($pg['menu_label'] ?? '')) !== '' ? (string)$pg['menu_label'] : (string)($pg['page_name'] ?? '-');
        $secondaryName = trim((string)($pg['menu_label'] ?? '')) !== '' && trim((string)($pg['page_name'] ?? '')) !== '' && (string)$pg['menu_label'] !== (string)$pg['page_name']
            ? (string)$pg['page_name']
            : '';
        $hasMenu = !empty($pg['has_menu']);
      ?>
      <div class="mx-page-row <?= $row_act ? 'row-active' : '' ?>"
           data-module="<?= htmlspecialchars($mod) ?>" data-pid="<?= $pid ?>"
           data-has-menu="<?= $hasMenu ? '1' : '0' ?>"
           data-page-name="<?= htmlspecialchars(strtolower(trim($displayName . ' ' . $secondaryName . ' ' . ($pg['page_code'] ?? '') . ' ' . ($pg['description'] ?? '')))) ?>">
        <!-- Page info -->
        <div class="mx-page-info">
          <div class="mx-page-name"><?= htmlspecialchars($displayName) ?></div>
          <div class="mx-page-meta">
            <span class="mx-page-badge <?= $hasMenu ? 'menu' : 'tech' ?>">
              <i class="ri <?= $hasMenu ? 'ri-menu-line' : 'ri-code-s-slash-line' ?>"></i>
              <?= $hasMenu ? 'Menu aktif' : 'Teknis / internal' ?>
            </span>
            <?php if ($secondaryName !== ''): ?>
              <span class="mx-page-secondary"><?= htmlspecialchars($secondaryName) ?></span>
            <?php endif; ?>
          </div>
          <div class="mx-page-code"><?= htmlspecialchars($pg['page_code']) ?></div>
          <?php if (!empty($pg['description'])): ?>
            <div class="mx-page-desc"><?= htmlspecialchars((string)$pg['description']) ?></div>
          <?php endif; ?>
        </div>
        <!-- Permission toggles -->
        <div class="mx-perms">
          <?php foreach ($perm_flags as $flag => $fmeta): ?>
          <label class="ptog <?= $fmeta['cls'] ?>">
            <input type="checkbox" class="matrix-cb"
                   name="perms[<?= $pid ?>][<?= $flag ?>]"
                   value="1"
                   data-module="<?= htmlspecialchars($mod) ?>"
                   data-flag="<?= $flag ?>"
                   data-pid="<?= $pid ?>"
                   <?= $pg[$flag] ? 'checked' : '' ?>>
            <span class="ptog-pill <?= $pg[$flag] ? 'on' : '' ?>">
              <i class="ri <?= $fmeta['icon'] ?>"></i>
              <span class="d-none d-sm-inline"><?= $fmeta['label'] ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <!-- Row quick-all (visible on hover) -->
        <div class="mx-row-quick">
          <button type="button" class="btn btn-outline-success row-check-all" data-pid="<?= $pid ?>" title="Centang semua baris ini">
            <i class="ri ri-check-line"></i>
          </button>
          <button type="button" class="btn btn-outline-danger row-uncheck" data-pid="<?= $pid ?>" title="Kosongkan baris ini">
            <i class="ri ri-close-line"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="mx-no-match">Tidak ada halaman yang cocok.</div>
    </div>
  </div>

</div>
<?php endforeach; ?>

<!-- ── STICKY SAVE BAR ─────────────────────────────────────────── -->
<div class="mx-save-bar">
  <span class="mx-dirty-dot" id="dirty-dot"></span>
  <button type="submit" form="form-matrix" class="btn btn-primary btn-save">
    <i class="ri ri-save-line me-1"></i>Simpan Matrix Izin
  </button>
  <a href="<?= base_url('roles') ?>" class="btn btn-outline-secondary">Batal</a>
  <span class="ms-auto mx-grand-counter d-none d-sm-flex align-items-center gap-1">
    <i class="ri ri-shield-check-line text-success"></i>
    <b id="save-bar-active"><?= $grand_active ?></b> halaman aktif
  </span>
</div>

<?= form_close() ?>
<?php endif; ?>

<script>
(function () {
  'use strict';

  // ── Helpers ────────────────────────────────────────────────────
  function getCbsByPid(pid) {
    return document.querySelectorAll(`.matrix-cb[data-pid="${pid}"]`);
  }

  function isRowFilterVisible(row) {
    return row && row.dataset.filterVisible === '1';
  }

  function getModuleRows(mod, filteredOnly) {
    const rows = Array.from(document.querySelectorAll(`.mx-page-row[data-module="${mod}"]`));
    return filteredOnly ? rows.filter(isRowFilterVisible) : rows;
  }

  function getFilteredCheckboxes(selector) {
    return Array.from(document.querySelectorAll(selector)).filter(cb => {
      const row = cb.closest('.mx-page-row');
      return isRowFilterVisible(row);
    });
  }

  function applyRowFilters() {
    const searchInput = document.getElementById('mx-search');
    const showTechnical = !!document.getElementById('mx-show-technical')?.checked;
    const q = (searchInput ? searchInput.value : '').trim().toLowerCase();

    document.querySelectorAll('.mx-module').forEach(modEl => {
      const pageRows = modEl.querySelectorAll('.mx-page-row');
      let visible = 0;

      pageRows.forEach(row => {
        const matchesSearch = !q || row.dataset.pageName.includes(q);
        const isTechnical = row.dataset.hasMenu !== '1';
        const visibleRow = matchesSearch && (showTechnical || !isTechnical);
        row.style.display = visibleRow ? '' : 'none';
        row.dataset.filterVisible = visibleRow ? '1' : '0';
        if (visibleRow) visible++;
      });

      const noMatch = modEl.querySelector('.mx-no-match');
      if (noMatch) {
        noMatch.style.display = visible === 0 ? 'block' : 'none';
      }

      const collapseEl = modEl.querySelector('.collapse');
      if (q && visible > 0 && collapseEl) {
        bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
      }

      modEl.style.display = visible === 0 ? 'none' : '';
      syncModuleStats(modEl.dataset.module);
    });

    syncGrandCounter();
  }
  function getCbsByModule(mod) {
    return document.querySelectorAll(`.matrix-cb[data-module="${mod}"]`);
  }
  function getViewCb(pid) {
    return document.querySelector(`.matrix-cb[data-pid="${pid}"][data-flag="can_view"]`);
  }

  // ── Toggle pill visual ─────────────────────────────────────────
  function syncPill(cb) {
    const pill = cb.closest('label').querySelector('.ptog-pill');
    if (pill) pill.classList.toggle('on', cb.checked);
  }

  // ── Row active border ──────────────────────────────────────────
  function syncRowState(pid) {
    const row  = document.querySelector(`.mx-page-row[data-pid="${pid}"]`);
    const cbs  = getCbsByPid(pid);
    const any  = Array.from(cbs).some(c => c.checked);
    if (row) row.classList.toggle('row-active', any);
  }

  // ── Module counter + progress bar ─────────────────────────────
  function syncModuleStats(mod) {
    const rows  = getModuleRows(mod, true);
    let active  = 0;
    rows.forEach(row => {
      const pid  = row.dataset.pid;
      const cbs  = getCbsByPid(pid);
      const any  = Array.from(cbs).some(c => c.checked);
      if (any) active++;
    });
    const total = rows.length;
    const pct   = total > 0 ? Math.round(active / total * 100) : 0;

    const numEl = document.querySelector(`.mod-active-num[data-mod="${mod}"]`);
    const totalEl = document.querySelector(`.mod-total-num[data-mod="${mod}"]`);
    const barEl = document.querySelector(`.mx-mod-bar-fill[data-mod="${mod}"]`);
    if (numEl) numEl.textContent = active;
    if (totalEl) totalEl.textContent = total;
    if (barEl) barEl.style.width = pct + '%';
  }

  // ── Grand counter ──────────────────────────────────────────────
  function syncGrandCounter() {
    const allRows = Array.from(document.querySelectorAll('.mx-page-row')).filter(isRowFilterVisible);
    let active = 0;
    allRows.forEach(row => {
      const pid = row.dataset.pid;
      const cbs = getCbsByPid(pid);
      if (Array.from(cbs).some(c => c.checked)) active++;
    });
    const el1 = document.getElementById('grand-active-num');
    const elTotal = document.getElementById('grand-total-num');
    const el2 = document.getElementById('save-bar-active');
    if (el1) el1.textContent = active;
    if (elTotal) elTotal.textContent = allRows.length;
    if (el2) el2.textContent = active;
  }

  // ── Dirty indicator ────────────────────────────────────────────
  let isDirty = false;
  function markDirty() {
    if (!isDirty) {
      isDirty = true;
      const dot = document.getElementById('dirty-dot');
      if (dot) dot.classList.add('show');
    }
  }

  // ── Chevron sync on Bootstrap collapse ────────────────────────
  document.querySelectorAll('.mx-module .collapse').forEach(el => {
    el.addEventListener('show.bs.collapse', () => {
      const chevron = el.closest('.mx-module').querySelector('.mx-chevron');
      if (chevron) chevron.classList.add('open');
      const hdr = el.closest('.mx-module').querySelector('.mx-module-header');
      if (hdr) hdr.setAttribute('aria-expanded', 'true');
    });
    el.addEventListener('hide.bs.collapse', () => {
      const chevron = el.closest('.mx-module').querySelector('.mx-chevron');
      if (chevron) chevron.classList.remove('open');
      const hdr = el.closest('.mx-module').querySelector('.mx-module-header');
      if (hdr) hdr.setAttribute('aria-expanded', 'false');
    });
  });

  // ── Checkbox change (main logic) ──────────────────────────────
  document.addEventListener('change', function (e) {
    const cb = e.target;
    if (!cb.classList.contains('matrix-cb')) return;

    const pid  = cb.dataset.pid;
    const flag = cb.dataset.flag;
    const mod  = cb.dataset.module;

    // Rule: uncheck View → uncheck all for this page
    if (flag === 'can_view' && !cb.checked) {
      getCbsByPid(pid).forEach(c => { c.checked = false; syncPill(c); });
    }
    // Rule: check non-View → auto-check View
    if (flag !== 'can_view' && cb.checked) {
      const viewCb = getViewCb(pid);
      if (viewCb && !viewCb.checked) { viewCb.checked = true; syncPill(viewCb); }
    }

    syncPill(cb);
    syncRowState(pid);
    syncModuleStats(mod);
    syncGrandCounter();
    markDirty();
  });

  // ── Global buttons ─────────────────────────────────────────────
  function setAllCbs(checked) {
    getFilteredCheckboxes('.matrix-cb').forEach(cb => {
      cb.checked = checked; syncPill(cb);
    });
    Array.from(document.querySelectorAll('.mx-page-row')).filter(isRowFilterVisible).forEach(row => syncRowState(row.dataset.pid));
    document.querySelectorAll('.mx-module').forEach(m => syncModuleStats(m.dataset.module));
    syncGrandCounter();
    markDirty();
  }

  document.getElementById('btn-check-all').addEventListener('click', () => setAllCbs(true));
  document.getElementById('btn-uncheck-all').addEventListener('click', () => setAllCbs(false));
  document.getElementById('btn-check-all-view').addEventListener('click', () => {
    getFilteredCheckboxes('.matrix-cb[data-flag="can_view"]').forEach(cb => {
      cb.checked = true; syncPill(cb);
    });
    Array.from(document.querySelectorAll('.mx-page-row')).filter(isRowFilterVisible).forEach(row => syncRowState(row.dataset.pid));
    document.querySelectorAll('.mx-module').forEach(m => syncModuleStats(m.dataset.module));
    syncGrandCounter();
    markDirty();
  });

  // Expand / collapse all
  document.getElementById('btn-expand-all').addEventListener('click', () => {
    document.querySelectorAll('.mx-module .collapse').forEach(el => {
      bootstrap.Collapse.getOrCreateInstance(el).show();
    });
  });
  document.getElementById('btn-collapse-all').addEventListener('click', () => {
    document.querySelectorAll('.mx-module .collapse').forEach(el => {
      bootstrap.Collapse.getOrCreateInstance(el).hide();
    });
  });

  // ── Per-module quick buttons ───────────────────────────────────
  function setModCbs(mod, checked, viewOnly) {
    getFilteredCheckboxes(`.matrix-cb[data-module="${mod}"]`).forEach(cb => {
      if (viewOnly && cb.dataset.flag !== 'can_view') return;
      cb.checked = checked; syncPill(cb);
    });
    // If checking all → auto-open module
    if (checked) {
      const collapseEl = document.querySelector(`.mx-module[data-module="${mod}"] .collapse`);
      if (collapseEl) bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
    }
    getModuleRows(mod, true).forEach(row => syncRowState(row.dataset.pid));
    syncModuleStats(mod);
    syncGrandCounter();
    markDirty();
  }

  document.addEventListener('click', function (e) {
    // Module-level
    const modCheckView = e.target.closest('.module-check-view');
    const modCheckAll  = e.target.closest('.module-check-all');
    const modUncheck   = e.target.closest('.module-uncheck');
    if (modCheckView) { setModCbs(modCheckView.dataset.module, true, true);  return; }
    if (modCheckAll)  { setModCbs(modCheckAll.dataset.module,  true, false); return; }
    if (modUncheck)   { setModCbs(modUncheck.dataset.module, false, false);  return; }

    // Row-level
    const rowCheckAll = e.target.closest('.row-check-all');
    const rowUncheck  = e.target.closest('.row-uncheck');
    if (rowCheckAll) {
      const pid = rowCheckAll.dataset.pid;
      getCbsByPid(pid).forEach(cb => { cb.checked = true; syncPill(cb); });
      syncRowState(pid);
      const mod = document.querySelector(`.matrix-cb[data-pid="${pid}"]`)?.dataset.module;
      if (mod) { syncModuleStats(mod); syncGrandCounter(); }
      markDirty(); return;
    }
    if (rowUncheck) {
      const pid = rowUncheck.dataset.pid;
      getCbsByPid(pid).forEach(cb => { cb.checked = false; syncPill(cb); });
      syncRowState(pid);
      const mod = document.querySelector(`.matrix-cb[data-pid="${pid}"]`)?.dataset.module;
      if (mod) { syncModuleStats(mod); syncGrandCounter(); }
      markDirty(); return;
    }
  });

  // ── Search filter ──────────────────────────────────────────────
  document.getElementById('mx-search').addEventListener('input', applyRowFilters);
  document.getElementById('mx-show-technical').addEventListener('change', applyRowFilters);

  // ── Clear dirty on submit ──────────────────────────────────────
  document.getElementById('form-matrix').addEventListener('submit', () => {
    isDirty = false;
    const dot = document.getElementById('dirty-dot');
    if (dot) dot.classList.remove('show');
  });

  applyRowFilters();


}());
</script>
