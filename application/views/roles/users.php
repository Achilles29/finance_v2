<?php
/**
 * roles/users.php — Assign user ke role dengan checklist interaktif
 * $role              : array
 * $all_users         : array (semua user aktif + has_role flag)
 * $users_by_division : ['Divisi X' => [...users], ...]
 */
$all_users         = $all_users ?? [];
$users_by_division = $users_by_division ?? [];

$total_users    = count($all_users);
$assigned_count = count(array_filter($all_users, fn($u) => $u['has_role']));

// Permission check
$_is_super = !empty($current_user['is_superadmin']);
$canEdit   = $_is_super || !empty($user_perms['auth.roles.manage']['can_edit']);
?>

<style>
/* ── Info bar ───────────────────────────────────────────── */
.ru-role-bar { background:#fff; border:1px solid #e9ecef; border-radius:12px; padding:14px 18px; margin-bottom:16px; }

/* ── Toolbar ────────────────────────────────────────────── */
.ru-toolbar { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:10px 14px; margin-bottom:16px; gap:8px; }

/* ── Division card ──────────────────────────────────────── */
.ru-division { border:1px solid #e9ecef; border-radius:12px; margin-bottom:10px; overflow:hidden; }
.ru-div-header {
  display:flex; align-items:center; gap:10px; padding:9px 14px;
  cursor:pointer; user-select:none; background:#f8fafc;
  transition:background 0.12s;
}
.ru-div-header:hover { background:#f1f5f9; }
.ru-div-icon { width:30px; height:30px; border-radius:8px; background:#e0f2fe; color:#0284c7;
  display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; }
.ru-div-label { font-weight:700; font-size:0.82rem; color:#1e293b; flex:1; }
.ru-div-counter { font-size:0.72rem; font-weight:600; color:#0284c7; white-space:nowrap; }
.ru-div-quick { display:flex; gap:4px; flex-shrink:0; }
.ru-div-quick .btn { font-size:0.68rem; padding:2px 7px; border-radius:6px; }
.ru-chevron { transition:transform 0.2s; color:#94a3b8; flex-shrink:0; }
.ru-chevron.open { transform:rotate(180deg); }

/* ── User row ────────────────────────────────────────────── */
.ru-user-list { border-top:1px solid #f1f5f9; }
.ru-user-row {
  display:flex; align-items:center; gap:10px; padding:9px 14px;
  border-bottom:1px solid #f8fafc; cursor:pointer;
  transition:background 0.1s;
}
.ru-user-row:last-child  { border-bottom:none; }
.ru-user-row:hover       { background:#f0f9ff; }
.ru-user-row.row-checked { border-left:3px solid #22c55e; background:#f0fdf4; }
.ru-user-row.row-checked:hover { background:#dcfce7; }

/* ── Avatar initial ─────────────────────────────────────── */
.ru-avatar {
  width:34px; height:34px; border-radius:50%; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-size:0.75rem; font-weight:700; color:#fff;
  background: linear-gradient(135deg, #64748b, #94a3b8);
}
.ru-user-row.row-checked .ru-avatar { background: linear-gradient(135deg, #16a34a, #22c55e); }

/* ── Checkbox custom ────────────────────────────────────── */
.ru-cb { width:18px; height:18px; cursor:pointer; flex-shrink:0; accent-color:#16a34a; }

/* ── User info ───────────────────────────────────────────── */
.ru-uname     { font-size:0.82rem; font-weight:600; color:#1e293b; }
.ru-email     { font-size:0.7rem; color:#94a3b8; }
.ru-emp       { font-size:0.78rem; color:#475569; }
.ru-meta      { font-size:0.7rem; color:#94a3b8; }
.ru-since     { font-size:0.68rem; color:#a3e635; white-space:nowrap; flex-shrink:0; }
.ru-no-match  { display:none; padding:18px; text-align:center; color:#94a3b8; font-size:0.82rem; }

/* ── Sticky save bar ─────────────────────────────────────── */
.ru-save-bar {
  position:sticky; bottom:0; z-index:20;
  background:rgba(255,255,255,0.95); backdrop-filter:blur(8px);
  border-top:1px solid #e9ecef; padding:12px 18px;
  display:flex; align-items:center; gap:10px; margin-top:16px;
}
.ru-dirty-dot {
  display:none; width:8px; height:8px; border-radius:50%;
  background:#f59e0b; animation:ru-pulse 1s infinite;
}
.ru-dirty-dot.show { display:inline-block; }
@keyframes ru-pulse { 0%,100%{opacity:1} 50%{opacity:0.35} }

/* ── Search ─────────────────────────────────────────────── */
.ru-search-wrap { position:relative; }
.ru-search-wrap .ri-search-line { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; }
#ru-search { padding-left:32px; border-radius:8px; font-size:0.82rem; }
</style>

<!-- ── ROLE INFO BAR ───────────────────────────────────────────── -->
<div class="ru-role-bar d-flex align-items-center gap-3 flex-wrap">
  <a href="<?= base_url('roles') ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">
    <i class="ri ri-arrow-left-line"></i>
  </a>
  <div style="flex:1; min-width:180px;">
    <div class="fw-bold" style="font-size:1rem;">
      <i class="ri ri-group-line me-1 text-primary"></i>
      Assign User — <span class="text-primary"><?= htmlspecialchars($role['role_name']) ?></span>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap mt-1" style="font-size:0.75rem; color:#64748b;">
      <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;"><?= htmlspecialchars($role['role_code']) ?></code>
      <?php if (!empty($role['division_scope_id'])): ?>
        <span class="badge bg-info-subtle text-info"><i class="ri ri-building-line me-1"></i><?= htmlspecialchars($role['division_scope_name'] ?? '') ?></span>
      <?php else: ?>
        <span class="text-muted">Lintas divisi</span>
      <?php endif; ?>
      <a href="<?= base_url('roles/matrix/' . $role['id']) ?>" class="text-decoration-none text-muted">
        <i class="ri ri-shield-keyhole-line me-1"></i>Lihat Matrix Izin
      </a>
    </div>
  </div>
  <!-- Grand counter -->
  <div class="text-end flex-shrink-0">
    <div style="font-size:1.6rem; font-weight:800; line-height:1; color:#1e293b;" id="grand-assigned"><?= $assigned_count ?></div>
    <div style="font-size:0.68rem; color:#94a3b8;">user di-assign dari <?= $total_users ?></div>
  </div>
</div>

<?php if (empty($all_users)): ?>
<div class="alert alert-info border-0 shadow-sm">
  <i class="ri ri-information-line me-2"></i>
  Belum ada user aktif di sistem. <a href="<?= base_url('users/create') ?>" class="alert-link">Buat user baru</a>.
</div>
<?php else: ?>

<!-- ── TOOLBAR ─────────────────────────────────────────────────── -->
<div class="ru-toolbar d-flex align-items-center flex-wrap">
  <!-- Search -->
  <div class="ru-search-wrap me-auto" style="min-width:180px; max-width:260px;">
    <i class="ri ri-search-line"></i>
    <input type="text" id="ru-search" class="form-control form-control-sm" placeholder="Cari user, nama, divisi…">
  </div>
  <!-- Expand/Collapse -->
  <div class="d-flex gap-2 ms-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-expand-all">
      <i class="ri ri-expand-up-down-line me-1"></i><span class="d-none d-sm-inline">Buka Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-collapse-all">
      <i class="ri ri-contract-up-down-line me-1"></i><span class="d-none d-sm-inline">Tutup Semua</span>
    </button>
  </div>
  <!-- Check/Uncheck -->
  <div class="d-flex gap-2 ms-2">
    <button type="button" class="btn btn-sm btn-outline-success" id="btn-check-all">
      <i class="ri ri-checkbox-multiple-line me-1"></i><span class="d-none d-md-inline">Pilih Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="btn-uncheck-all">
      <i class="ri ri-close-circle-line me-1"></i><span class="d-none d-md-inline">Kosongkan</span>
    </button>
  </div>
</div>

<?= form_open('roles/save_users/' . $role['id'], ['id' => 'form-users']) ?>

<!-- ── DIVISION GROUPS ──────────────────────────────────────────── -->
<?php foreach ($users_by_division as $div_name => $users):
  $div_id      = 'ru-div-' . preg_replace('/[^a-z0-9]/i', '-', strtolower($div_name));
  $div_total   = count($users);
  $div_assigned = count(array_filter($users, fn($u) => $u['has_role']));
  $is_open     = $div_assigned > 0;
?>
<div class="ru-division" data-division="<?= htmlspecialchars($div_name) ?>">

  <!-- Division Header -->
  <div class="ru-div-header" data-bs-toggle="collapse" data-bs-target="#<?= $div_id ?>"
       aria-expanded="<?= $is_open ? 'true' : 'false' ?>">
    <div class="ru-div-icon"><i class="ri ri-building-line"></i></div>
    <div class="ru-div-label"><?= htmlspecialchars($div_name) ?></div>
    <!-- Counter -->
    <div class="ru-div-counter">
      <span class="div-assigned-num" data-div="<?= htmlspecialchars($div_name) ?>"><?= $div_assigned ?></span>/<?= $div_total ?>
    </div>
    <!-- Quick -->
    <div class="ru-div-quick" onclick="event.stopPropagation();">
      <button type="button" class="btn btn-outline-success div-check-all" data-div="<?= htmlspecialchars($div_name) ?>">Semua</button>
      <button type="button" class="btn btn-outline-danger div-uncheck"    data-div="<?= htmlspecialchars($div_name) ?>">×</button>
    </div>
    <i class="ri ri-arrow-down-s-line ru-chevron <?= $is_open ? 'open' : '' ?>"></i>
  </div>

  <!-- Division Body -->
  <div id="<?= $div_id ?>" class="collapse <?= $is_open ? 'show' : '' ?>">
    <div class="ru-user-list">
      <?php foreach ($users as $u):
        $initials = strtoupper(mb_substr($u['employee_name'] ?: $u['username'], 0, 1) . (mb_substr(strrchr($u['employee_name'] ?: $u['username'], ' ') ?: '', 1, 1)));
        $initials = $initials ?: '?';
      ?>
      <div class="ru-user-row <?= $u['has_role'] ? 'row-checked' : '' ?>"
           data-div="<?= htmlspecialchars($div_name) ?>"
           data-uid="<?= $u['id'] ?>"
           data-search="<?= htmlspecialchars(strtolower($u['username'] . ' ' . $u['employee_name'] . ' ' . $u['email'] . ' ' . $div_name)) ?>">

        <input type="checkbox" class="ru-cb"
               name="user_ids[]"
               value="<?= $u['id'] ?>"
               <?= $u['has_role'] ? 'checked' : '' ?>>

        <div class="ru-avatar"><?= htmlspecialchars($initials) ?></div>

        <div style="flex:1; min-width:120px;">
          <div class="ru-uname"><?= htmlspecialchars($u['username']) ?></div>
          <?php if (!empty($u['email'])): ?>
          <div class="ru-email"><?= htmlspecialchars($u['email']) ?></div>
          <?php endif; ?>
        </div>

        <div style="flex:1; min-width:100px;" class="d-none d-sm-block">
          <div class="ru-emp"><?= htmlspecialchars($u['employee_name'] ?: '—') ?></div>
          <div class="ru-meta"><?= htmlspecialchars($u['position_name'] ?: '—') ?></div>
        </div>

        <div class="text-end flex-shrink-0">
          <?php if ($u['has_role'] && !empty($u['assigned_at'])): ?>
            <div class="ru-meta text-success"><i class="ri ri-check-line"></i> Sejak <?= date('d/m/y', strtotime($u['assigned_at'])) ?></div>
          <?php elseif (!empty($u['last_login_at'])): ?>
            <div class="ru-meta">Login: <?= date('d/m/y', strtotime($u['last_login_at'])) ?></div>
          <?php else: ?>
            <div class="ru-meta text-muted">—</div>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
      <div class="ru-no-match">Tidak ada user yang cocok.</div>
    </div>
  </div>

</div>
<?php endforeach; ?>

<!-- ── STICKY SAVE BAR ─────────────────────────────────────────── -->
<?php if ($canEdit): ?>
<div class="ru-save-bar">
  <span class="ru-dirty-dot" id="ru-dirty-dot"></span>
  <button type="submit" form="form-users" class="btn btn-primary">
    <i class="ri ri-save-line me-1"></i>Simpan Assignment
  </button>
  <a href="<?= base_url('roles') ?>" class="btn btn-outline-secondary">Batal</a>
  <span class="ms-auto text-muted small d-none d-sm-inline">
    <b id="save-bar-assigned"><?= $assigned_count ?></b> / <?= $total_users ?> user di-assign
  </span>
</div>
<?php else: ?>
<div class="alert alert-warning border-0 shadow-sm mt-3">
  <i class="ri ri-lock-line me-2"></i>Anda tidak memiliki izin untuk mengubah assignment user.
</div>
<?php endif; ?>

<?= form_close() ?>
<?php endif; ?>

<script>
(function () {
  'use strict';

  // ── Helpers ────────────────────────────────────────────────────
  function getCbsByDiv(div) {
    return document.querySelectorAll(`.ru-cb[name="user_ids[]"]`
      + ` ~ input, .ru-user-row[data-div="${CSS.escape(div)}"] .ru-cb`);
  }

  function getAllRows() {
    return document.querySelectorAll('.ru-user-row');
  }

  // ── Sync row visual after checkbox change ──────────────────────
  function syncRow(row) {
    const cb = row.querySelector('.ru-cb');
    row.classList.toggle('row-checked', cb.checked);
  }

  // ── Update division counter ────────────────────────────────────
  function syncDivCounter(div) {
    const rows  = document.querySelectorAll(`.ru-user-row[data-div="${CSS.escape(div)}"]`);
    const count = Array.from(rows).filter(r => r.querySelector('.ru-cb')?.checked).length;
    const el    = document.querySelector(`.div-assigned-num[data-div="${CSS.escape(div)}"]`);
    if (el) el.textContent = count;
  }

  // ── Update grand counter ───────────────────────────────────────
  function syncGrand() {
    const total   = document.querySelectorAll('.ru-cb').length;
    const checked = document.querySelectorAll('.ru-cb:checked').length;
    const el1 = document.getElementById('grand-assigned');
    const el2 = document.getElementById('save-bar-assigned');
    if (el1) el1.textContent = checked;
    if (el2) el2.textContent = checked;
  }

  // ── Dirty indicator ────────────────────────────────────────────
  let isDirty = false;
  function markDirty() {
    if (!isDirty) {
      isDirty = true;
      const dot = document.getElementById('ru-dirty-dot');
      if (dot) dot.classList.add('show');
    }
  }

  // ── Chevron on collapse events ────────────────────────────────
  document.querySelectorAll('.ru-division .collapse').forEach(el => {
    el.addEventListener('show.bs.collapse', () => {
      el.closest('.ru-division').querySelector('.ru-chevron')?.classList.add('open');
    });
    el.addEventListener('hide.bs.collapse', () => {
      el.closest('.ru-division').querySelector('.ru-chevron')?.classList.remove('open');
    });
  });

  // ── Click on row = toggle checkbox ────────────────────────────
  document.addEventListener('click', function (e) {
    const row = e.target.closest('.ru-user-row');
    if (!row) return;
    // If the click was directly on checkbox, don't double-toggle
    if (e.target.classList.contains('ru-cb')) return;
    // Ignore if clicking a button inside the row (none here but safety)
    if (e.target.closest('button, a')) return;

    const cb = row.querySelector('.ru-cb');
    if (!cb) return;
    cb.checked = !cb.checked;
    syncRow(row);
    syncDivCounter(row.dataset.div);
    syncGrand();
    markDirty();
  });

  // ── Checkbox direct change ─────────────────────────────────────
  document.addEventListener('change', function (e) {
    const cb = e.target;
    if (!cb.classList.contains('ru-cb')) return;
    const row = cb.closest('.ru-user-row');
    if (row) { syncRow(row); syncDivCounter(row.dataset.div); }
    syncGrand();
    markDirty();
  });

  // ── Global buttons ─────────────────────────────────────────────
  function setAll(checked) {
    document.querySelectorAll('.ru-user-row:not([style*="display: none"])').forEach(row => {
      const cb = row.querySelector('.ru-cb');
      if (cb) { cb.checked = checked; syncRow(row); }
    });
    document.querySelectorAll('.ru-division').forEach(d => syncDivCounter(d.dataset.division));
    syncGrand();
    markDirty();
  }

  document.getElementById('btn-check-all').addEventListener('click',   () => setAll(true));
  document.getElementById('btn-uncheck-all').addEventListener('click', () => setAll(false));

  document.getElementById('btn-expand-all').addEventListener('click', () => {
    document.querySelectorAll('.ru-division .collapse').forEach(el =>
      bootstrap.Collapse.getOrCreateInstance(el).show());
  });
  document.getElementById('btn-collapse-all').addEventListener('click', () => {
    document.querySelectorAll('.ru-division .collapse').forEach(el =>
      bootstrap.Collapse.getOrCreateInstance(el).hide());
  });

  // ── Per-division quick buttons ─────────────────────────────────
  document.addEventListener('click', function (e) {
    const chkAll  = e.target.closest('.div-check-all');
    const unchkAll = e.target.closest('.div-uncheck');
    if (!chkAll && !unchkAll) return;

    const div     = (chkAll || unchkAll).dataset.div;
    const checked = !!chkAll;
    document.querySelectorAll(`.ru-user-row[data-div="${CSS.escape(div)}"]`).forEach(row => {
      const cb = row.querySelector('.ru-cb');
      if (cb) { cb.checked = checked; syncRow(row); }
    });
    // Auto-expand if checking
    if (checked) {
      const divEl     = document.querySelector(`.ru-division[data-division="${CSS.escape(div)}"]`);
      const collapseEl = divEl?.querySelector('.collapse');
      if (collapseEl) bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
    }
    syncDivCounter(div);
    syncGrand();
    markDirty();
  });

  // ── Search filter ──────────────────────────────────────────────
  document.getElementById('ru-search').addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();

    document.querySelectorAll('.ru-division').forEach(divEl => {
      const div      = divEl.dataset.division;
      const rows     = divEl.querySelectorAll('.ru-user-row');
      let   visible  = 0;

      rows.forEach(row => {
        const match = !q || row.dataset.search.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });

      const noMatch = divEl.querySelector('.ru-no-match');
      if (noMatch) noMatch.style.display = (visible === 0 && q) ? 'block' : 'none';

      if (q && visible > 0) {
        const collapseEl = divEl.querySelector('.collapse');
        if (collapseEl) bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
      }

      divEl.style.display = (visible === 0 && q) ? 'none' : '';
    });
  });

  // ── Clear dirty on submit ──────────────────────────────────────
  document.getElementById('form-users')?.addEventListener('submit', () => {
    isDirty = false;
    document.getElementById('ru-dirty-dot')?.classList.remove('show');
  });

}());
</script>
