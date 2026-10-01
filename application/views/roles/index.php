<?php
/**
 * roles/index.php — Daftar role dengan user count, page count, division scope
 */
$_is_super  = !empty($current_user['is_superadmin']);
$canCreate  = $_is_super || !empty($user_perms['auth.roles.manage']['can_create']);
$canEdit    = $_is_super || !empty($user_perms['auth.roles.manage']['can_edit']);
$canDelete  = $_is_super || !empty($user_perms['auth.roles.manage']['can_delete']);
$registryAudit = is_array($registry_audit ?? null) ? $registry_audit : [];
$activePageCount = (int)($registryAudit['active_page_count'] ?? 0);
$activeMenuCount = (int)($registryAudit['active_menu_count'] ?? 0);
$menusWithoutPageCount = (int)($registryAudit['active_menus_without_page_count'] ?? 0);
$pagesWithoutMenuCount = (int)($registryAudit['active_pages_without_menu_count'] ?? 0);
$sharedPageRegistryCount = (int)($registryAudit['shared_page_registry_count'] ?? 0);
$controllerMissingPageCount = (int)($registryAudit['controller_missing_page_count'] ?? 0);
$pagesWithoutPermissionCount = (int)($registryAudit['pages_without_permission_count'] ?? 0);
$menusWithoutPage = is_array($registryAudit['menus_without_page'] ?? null) ? $registryAudit['menus_without_page'] : [];
$pagesWithoutMenu = is_array($registryAudit['pages_without_menu'] ?? null) ? $registryAudit['pages_without_menu'] : [];
$sharedPageRegistryRows = is_array($registryAudit['shared_page_registry_rows'] ?? null) ? $registryAudit['shared_page_registry_rows'] : [];
$controllerMissingPages = is_array($registryAudit['controller_missing_pages'] ?? null) ? $registryAudit['controller_missing_pages'] : [];
$pagesWithoutPermissions = is_array($registryAudit['pages_without_permissions'] ?? null) ? $registryAudit['pages_without_permissions'] : [];
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0"><i class="ri ri-shield-keyhole-line me-2 text-primary"></i>Role & Hak Akses</h5>
    <p class="text-muted small mb-0">Pusat pengaturan akses Finance. Hak akses idealnya mengikuti nama menu yang dikenal user, bukan kode teknis.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= base_url('roles/matrix-groups') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri ri-layout-grid-line me-1"></i>Konfigurasi Grup Matrix
    </a>
    <?php if ($canCreate): ?>
    <a href="<?= base_url('roles/create') ?>" class="btn btn-primary btn-sm">
      <i class="ri ri-add-line me-1"></i> Buat Role Baru
    </a>
    <?php endif; ?>
  </div>
</div>

<style>
.role-tab-nav .btn.active {
  background:#7c2d12;
  border-color:#7c2d12;
  color:#fff;
}
</style>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
    <div class="btn-group btn-group-sm role-tab-nav" role="tablist" aria-label="Tab role dan audit">
      <button type="button" class="btn btn-outline-secondary active" data-role-tab="roles">Daftar Role</button>
      <button type="button" class="btn btn-outline-secondary" data-role-tab="audit">Audit Registry Hak Akses</button>
    </div>
    <div class="small text-muted">Tab utama sekarang langsung ke tabel role. Audit registry dipisah supaya lebih enak dibaca.</div>
  </div>
</div>

<div class="role-tab-pane" data-role-tab-pane="audit" style="display:none;">
<div class="row g-3 mb-3">
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Halaman Terdaftar</div>
        <div class="fs-4 fw-bold"><?= $activePageCount ?></div>
        <div class="small text-muted">Page aktif di registry hak akses</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Menu Aktif</div>
        <div class="fs-4 fw-bold"><?= $activeMenuCount ?></div>
        <div class="small text-muted">Menu/sidebar aktif di database</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100 border-warning-subtle">
      <div class="card-body">
        <div class="text-muted small mb-1">Menu Belum Masuk Hak Akses</div>
        <div class="fs-4 fw-bold text-warning"><?= $menusWithoutPageCount ?></div>
        <div class="small text-muted">Menu aktif punya URL tapi belum terhubung ke `sys_page`</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100 border-info-subtle">
      <div class="card-body">
        <div class="text-muted small mb-1">Halaman Teknis / Review</div>
        <div class="fs-4 fw-bold text-info"><?= $pagesWithoutMenuCount ?></div>
        <div class="small text-muted">Page aktif yang belum terhubung ke menu aktif</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100 border-danger-subtle">
      <div class="card-body">
        <div class="text-muted small mb-1">Page Dipakai Banyak Menu</div>
        <div class="fs-4 fw-bold text-danger"><?= $sharedPageRegistryCount ?></div>
        <div class="small text-muted">Perlu dibedakan mana shared permission dan mana salah taut page</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100 border-primary-subtle">
      <div class="card-body">
        <div class="text-muted small mb-1">Page Controller Belum Terdaftar</div>
        <div class="fs-4 fw-bold text-primary"><?= $controllerMissingPageCount ?></div>
        <div class="small text-muted">Ini sumber utama kasus role diberi akses tapi halaman tetap tidak bisa dibuka</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100 border-secondary-subtle">
      <div class="card-body">
        <div class="text-muted small mb-1">Page Tanpa Permission Rows</div>
        <div class="fs-4 fw-bold text-secondary"><?= $pagesWithoutPermissionCount ?></div>
        <div class="small text-muted">Page aktif yang belum punya baris `auth_role_permission` sama sekali</div>
      </div>
    </div>
  </div>
</div>

<?php if ($menusWithoutPageCount > 0 || $pagesWithoutMenuCount > 0 || $sharedPageRegistryCount > 0 || $controllerMissingPageCount > 0 || $pagesWithoutPermissionCount > 0): ?>
<div class="card border-0 shadow-sm mb-3" id="audit-card">
  <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
    <div>
      <span class="fw-semibold">Audit Registry Hak Akses</span>
      <span class="badge bg-light text-dark border ms-2" style="font-size:.7rem;">sys_page + sys_menu</span>
    </div>
    <div class="small text-muted">Pilih aksi per item — perubahan AJAX, langsung aktif.</div>
  </div>
  <div class="card-body">
    <div class="row g-3">

      <!-- Menu tanpa page_id -->
      <div class="col-lg-6">
        <div class="fw-semibold small text-warning mb-2">
          <i class="ri ri-error-warning-line me-1"></i>
          Menu aktif belum punya page registry
          <span class="badge bg-warning text-dark ms-1"><?= $menusWithoutPageCount ?></span>
        </div>
        <?php if (empty($menusWithoutPage)): ?>
          <div class="small text-muted">Tidak ada temuan.</div>
        <?php else: ?>
          <div class="small text-muted mb-2">Tanpa page registry, menu tidak muncul di matrix role.
            <?php if ($menusWithoutPageCount > count($menusWithoutPage)): ?>
              Menampilkan <?= count($menusWithoutPage) ?> dari <?= $menusWithoutPageCount ?>.
            <?php endif; ?>
          </div>
          <div class="d-flex flex-column gap-2" id="audit-menus-list">
            <?php foreach ($menusWithoutPage as $row):
              $rowMenuCode  = (string)($row['menu_code'] ?? '');
              $rowMenuLabel = (string)($row['menu_label'] ?? '-');
              $rowMenuUrl   = (string)($row['url'] ?? '');
              $rowMenuId    = (int)($row['id'] ?? 0);
            ?>
              <div class="audit-menu-row border rounded-2 px-2 py-2 small"
                   data-menu-code="<?= htmlspecialchars($rowMenuCode) ?>"
                   data-menu-label="<?= htmlspecialchars($rowMenuLabel) ?>"
                   data-menu-url="<?= htmlspecialchars($rowMenuUrl) ?>"
                   id="audit-menu-<?= $rowMenuId ?>">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars($rowMenuLabel) ?></div>
                    <div class="text-muted font-monospace" style="font-size:.68rem;"><?= htmlspecialchars($rowMenuCode) ?></div>
                    <?php if ($rowMenuUrl !== ''): ?>
                      <div class="text-muted" style="font-size:.68rem;"><?= htmlspecialchars($rowMenuUrl) ?></div>
                    <?php endif; ?>
                    <div class="audit-feedback mt-1" style="display:none;"></div>
                  </div>
                  <div class="d-flex gap-1 flex-shrink-0 flex-wrap">
                    <button type="button" class="btn btn-xs btn-outline-success audit-register-btn" style="font-size:.7rem; padding:2px 8px;"
                            title="Daftarkan ke sys_page lalu link menu ini"
                            data-menu-code="<?= htmlspecialchars($rowMenuCode) ?>"
                            data-menu-label="<?= htmlspecialchars($rowMenuLabel) ?>"
                            data-menu-url="<?= htmlspecialchars($rowMenuUrl) ?>">
                      <i class="ri ri-file-add-line"></i> Daftarkan &amp; Link
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-danger audit-deactivate-menu-btn" style="font-size:.7rem; padding:2px 8px;"
                            title="Nonaktifkan menu ini"
                            data-menu-code="<?= htmlspecialchars($rowMenuCode) ?>">
                      <i class="ri ri-eye-off-line"></i> Nonaktifkan
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Page tanpa menu aktif -->
      <div class="col-lg-6">
        <div class="fw-semibold small text-info mb-2">
          <i class="ri ri-information-line me-1"></i>
          Page aktif tanpa menu aktif
          <span class="badge bg-info text-white ms-1"><?= $pagesWithoutMenuCount ?></span>
        </div>
        <?php if (empty($pagesWithoutMenu)): ?>
          <div class="small text-muted">Tidak ada temuan.</div>
        <?php else: ?>
          <div class="small text-muted mb-2">Halaman teknis/internal tidak perlu menu.
            Nonaktifkan yang sudah tidak relevan.
            <?php if ($pagesWithoutMenuCount > count($pagesWithoutMenu)): ?>
              Menampilkan <?= count($pagesWithoutMenu) ?> dari <?= $pagesWithoutMenuCount ?>.
            <?php endif; ?>
          </div>
          <div class="d-flex flex-column gap-2" id="audit-pages-list">
            <?php foreach ($pagesWithoutMenu as $row):
              $rowPageCode = (string)($row['page_code'] ?? '');
              $rowPageName = (string)($row['page_name'] ?? '-');
              $rowModule   = (string)($row['module'] ?? '-');
              $rowPageId   = (int)($row['id'] ?? 0);
            ?>
              <div class="audit-page-row border rounded-2 px-2 py-2 small"
                   data-page-code="<?= htmlspecialchars($rowPageCode) ?>"
                   id="audit-page-<?= $rowPageId ?>">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars($rowPageName) ?></div>
                    <div class="text-muted" style="font-size:.68rem;">
                      <code><?= htmlspecialchars($rowPageCode) ?></code>
                      <span class="ms-1 badge bg-light text-dark border" style="font-size:.65rem;"><?= htmlspecialchars($rowModule) ?></span>
                      <span class="ms-1 badge <?= ($row['usage_type'] ?? '') === 'controller' ? 'bg-success-subtle text-success border' : 'bg-warning-subtle text-warning border' ?>" style="font-size:.65rem;">
                        <?= ($row['usage_type'] ?? '') === 'controller' ? 'Dipakai controller' : 'Legacy / orphan' ?>
                      </span>
                    </div>
                    <div class="audit-feedback mt-1" style="display:none;"></div>
                  </div>
                  <div class="d-flex gap-1 flex-shrink-0">
                    <button type="button" class="btn btn-xs btn-outline-danger audit-deactivate-page-btn" style="font-size:.7rem; padding:2px 8px;"
                            title="Nonaktifkan page ini dari sys_page"
                            data-page-code="<?= htmlspecialchars($rowPageCode) ?>">
                      <i class="ri ri-eye-off-line"></i> Nonaktifkan
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-6">
        <div class="fw-semibold small text-danger mb-2">
          <i class="ri ri-links-line me-1"></i>
          Page registry dipakai lebih dari satu menu
          <span class="badge bg-danger ms-1"><?= $sharedPageRegistryCount ?></span>
        </div>
        <?php if (empty($sharedPageRegistryRows)): ?>
          <div class="small text-muted">Tidak ada temuan.</div>
        <?php else: ?>
          <div class="small text-muted mb-2">Sebagian memang sengaja share permission, sebagian lain salah link ke page lama. Ini daftar yang perlu kita putuskan.</div>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($sharedPageRegistryRows as $row): ?>
              <div class="border rounded-2 px-2 py-2 small">
                <div class="fw-semibold"><?= htmlspecialchars((string)$row['page_name']) ?></div>
                <div class="text-muted" style="font-size:.68rem;">
                  <code><?= htmlspecialchars((string)$row['page_code']) ?></code>
                  <span class="ms-1 badge bg-light text-dark border"><?= htmlspecialchars((string)($row['resolved_group_code'] ?? $row['module'] ?? '-')) ?></span>
                </div>
                <div class="mt-1">
                  <?php foreach (($row['menu_items'] ?? []) as $menu): ?>
                    <div class="text-muted" style="font-size:.68rem;">
                      <span class="fw-semibold"><?= htmlspecialchars((string)($menu['menu_code'] ?? '-')) ?></span>
                      <span class="ms-1"><?= htmlspecialchars((string)($menu['url'] ?? '')) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-6">
        <div class="fw-semibold small text-primary mb-2">
          <i class="ri ri-code-box-line me-1"></i>
          Page dipanggil controller tetapi belum ada di `sys_page`
          <span class="badge bg-primary ms-1"><?= $controllerMissingPageCount ?></span>
        </div>
        <?php if (empty($controllerMissingPages)): ?>
          <div class="small text-muted">Tidak ada temuan.</div>
        <?php else: ?>
          <div class="small text-muted mb-2">Ini wajib dibereskan karena role matrix tidak akan pernah bisa memberi akses ke halaman yang belum terdaftar.</div>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($controllerMissingPages as $row): ?>
              <div class="border rounded-2 px-2 py-2 small">
                <div class="fw-semibold"><code><?= htmlspecialchars((string)$row['page_code']) ?></code></div>
                <div class="text-muted" style="font-size:.68rem;">Saran grup: <?= htmlspecialchars((string)($row['suggested_group'] ?? '-')) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-6">
        <div class="fw-semibold small text-secondary mb-2">
          <i class="ri ri-shield-cross-line me-1"></i>
          Page aktif tanpa baris permission
          <span class="badge bg-secondary ms-1"><?= $pagesWithoutPermissionCount ?></span>
        </div>
        <?php if (empty($pagesWithoutPermissions)): ?>
          <div class="small text-muted">Tidak ada temuan.</div>
        <?php else: ?>
          <div class="small text-muted mb-2">Page ini aktif tetapi belum pernah disebar ke `auth_role_permission`, jadi role non-superadmin tidak akan bisa diatur dengan normal.</div>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($pagesWithoutPermissions as $row): ?>
              <div class="border rounded-2 px-2 py-2 small">
                <div class="fw-semibold"><?= htmlspecialchars((string)$row['page_name']) ?></div>
                <div class="text-muted" style="font-size:.68rem;">
                  <code><?= htmlspecialchars((string)$row['page_code']) ?></code>
                  <span class="ms-1 badge bg-light text-dark border"><?= htmlspecialchars((string)$row['module']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var REGISTER_URL   = <?= json_encode(base_url('roles/quick-register-menu')) ?>;
  var DEACT_MENU_URL = <?= json_encode(base_url('roles/deactivate-menu')) ?>;
  var DEACT_PAGE_URL = <?= json_encode(base_url('roles/deactivate-page')) ?>;

  function feedback(btn, msg, ok) {
    var row = btn.closest('.audit-menu-row, .audit-page-row');
    var fb  = row ? row.querySelector('.audit-feedback') : null;
    if (fb) {
      fb.innerHTML  = '<span class="' + (ok ? 'text-success' : 'text-danger') + '">' + msg + '</span>';
      fb.style.display = 'block';
    }
  }

  function disableRow(btn) {
    var row = btn.closest('.audit-menu-row, .audit-page-row');
    if (row) {
      row.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    }
  }

  function fadeRow(btn) {
    var row = btn.closest('.audit-menu-row, .audit-page-row');
    if (row) {
      row.style.opacity = '0.4';
      row.style.pointerEvents = 'none';
    }
  }

  // Daftarkan & Link
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.audit-register-btn');
    if (!btn) return;
    if (!confirm('Daftarkan menu "' + btn.dataset.menuLabel + '" ke sys_page dan link otomatis?')) return;

    btn.disabled = true;
    var origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    $.post(REGISTER_URL, {
      menu_code:  btn.dataset.menuCode,
      menu_label: btn.dataset.menuLabel,
      url:        btn.dataset.menuUrl
    }, null, 'json').done(function (resp) {
      if (resp && resp.ok) {
        feedback(btn, '✓ Terdaftar sebagai ' + resp.page_code, true);
        disableRow(btn);
        setTimeout(function () { fadeRow(btn); }, 800);
      } else {
        feedback(btn, '✗ ' + (resp && resp.message ? resp.message : 'Gagal'), false);
        btn.innerHTML = origHtml;
        btn.disabled  = false;
      }
    }).fail(function (xhr) {
      var msg = 'Gagal mendaftarkan.';
      try { var r = JSON.parse(xhr.responseText); if (r && r.message) msg = r.message; } catch(ex) {}
      feedback(btn, '✗ ' + msg, false);
      btn.innerHTML = origHtml;
      btn.disabled  = false;
    });
  });

  // Nonaktifkan Menu
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.audit-deactivate-menu-btn');
    if (!btn) return;
    if (!confirm('Nonaktifkan menu "' + btn.dataset.menuCode + '"?')) return;

    btn.disabled = true;
    $.post(DEACT_MENU_URL, { menu_code: btn.dataset.menuCode }, null, 'json')
      .done(function (resp) {
        if (resp && resp.ok) {
          feedback(btn, '✓ Dinonaktifkan', true);
          disableRow(btn);
          setTimeout(function () { fadeRow(btn); }, 800);
        } else {
          feedback(btn, '✗ ' + (resp && resp.message ? resp.message : 'Gagal'), false);
          btn.disabled = false;
        }
      }).fail(function () {
        feedback(btn, '✗ Gagal', false);
        btn.disabled = false;
      });
  });

  // Nonaktifkan Page
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.audit-deactivate-page-btn');
    if (!btn) return;
    if (!confirm('Nonaktifkan page "' + btn.dataset.pageCode + '"?')) return;

    btn.disabled = true;
    $.post(DEACT_PAGE_URL, { page_code: btn.dataset.pageCode }, null, 'json')
      .done(function (resp) {
        if (resp && resp.ok) {
          feedback(btn, '✓ Dinonaktifkan', true);
          disableRow(btn);
          setTimeout(function () { fadeRow(btn); }, 800);
        } else {
          feedback(btn, '✗ ' + (resp && resp.message ? resp.message : 'Gagal'), false);
          btn.disabled = false;
        }
      }).fail(function () {
        feedback(btn, '✗ Gagal', false);
        btn.disabled = false;
      });
  });
})();
</script>
<?php endif; ?>

</div>

<div class="role-tab-pane" data-role-tab-pane="roles">
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:36px;">#</th>
            <th>Kode</th>
            <th>Nama Role</th>
            <th>Scope Divisi</th>
            <th class="text-center" style="width:80px;">User</th>
            <th class="text-center" style="width:80px;">Akses</th>
            <th>Status</th>
            <th style="width:150px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($roles)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada role.</td></tr>
          <?php else: ?>
          <?php foreach ($roles as $i => $r): ?>
          <tr>
            <td class="number-cell text-muted small"><?= $i + 1 ?></td>
            <td>
              <code class="bg-light px-2 py-1 rounded small"><?= htmlspecialchars($r['role_code']) ?></code>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($r['role_name']) ?></div>
              <?php if (!empty($r['description'])): ?>
              <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($r['description']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($r['division_scope_id'])): ?>
                <span class="badge bg-info-subtle text-info">
                  <i class="ri ri-building-line me-1"></i><?= htmlspecialchars($r['division_scope_name']) ?>
                </span>
              <?php else: ?>
                <span class="text-muted">Lintas divisi</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php $uc = (int)($r['user_count'] ?? 0); ?>
              <?php if ($uc > 0): ?>
                <a href="<?= base_url('roles/users/' . $r['id']) ?>" class="badge bg-primary-subtle text-primary text-decoration-none">
                  <?= $uc ?>
                </a>
              <?php else: ?>
                <span class="text-muted small">0</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php $pc = (int)($r['page_count'] ?? 0); ?>
              <?php if ($pc > 0): ?>
                <a href="<?= base_url('roles/matrix/' . $r['id']) ?>" class="badge bg-success-subtle text-success text-decoration-none">
                  <?= $pc ?>
                </a>
              <?php else: ?>
                <span class="text-muted small">0</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['is_active']): ?>
                <span class="badge bg-success-subtle text-success">Aktif</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="action-cell">
              <div class="d-flex gap-1 flex-nowrap justify-content-end">
                <a href="<?= base_url('roles/matrix/' . $r['id']) ?>" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Atur Hak Akses" aria-label="Atur Hak Akses">
                  <i class="ri ri-shield-keyhole-line"></i>
                </a>
                <a href="<?= base_url('roles/users/' . $r['id']) ?>" class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Atur Pengguna Role" aria-label="Atur Pengguna Role">
                  <i class="ri ri-group-line"></i>
                </a>
                <?php if ($canEdit): ?>
                <a href="<?= base_url('roles/edit/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                  <i class="ri ri-edit-line"></i>
                </a>
                <?php endif; ?>
                <?php if ($canDelete && $r['role_code'] !== 'SUPERADMIN'): ?>
                <a href="<?= base_url('roles/delete/' . $r['id']) ?>" class="btn btn-sm btn-outline-danger action-icon-btn"
                   data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus"
                   onclick="return confirm('Hapus role <?= htmlspecialchars(addslashes($r['role_name'])) ?>? Tidak bisa jika masih ada user.')">
                  <i class="ri ri-delete-bin-line"></i>
                </a>
                <?php endif; ?>
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

<script>
(function () {
  'use strict';

  function setRoleTab(tab) {
    document.querySelectorAll('[data-role-tab]').forEach(function (btn) {
      btn.classList.toggle('active', btn.dataset.roleTab === tab);
    });

    document.querySelectorAll('[data-role-tab-pane]').forEach(function (pane) {
      pane.style.display = pane.dataset.roleTabPane === tab ? '' : 'none';
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-role-tab]');
    if (!btn) return;
    setRoleTab(btn.dataset.roleTab || 'roles');
  });

  setRoleTab('roles');
})();
</script>
