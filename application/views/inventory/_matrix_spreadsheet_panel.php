<?php
$ci = get_instance();
$perms = (array)$ci->session->userdata('user_perms');
$needed = ['purchase.stock.warehouse.matrix.index', 'purchase.stock.material.matrix.index', 'production.component.daily.index'];
$user = (array)$ci->session->userdata('auth_user');
$isSuperadmin = !empty($user['is_superadmin']);
$allowed = $isSuperadmin;
if (!$isSuperadmin) { $allowed = true; foreach ($needed as $page) $allowed = $allowed && !empty($perms[$page]['can_view']); }
if ($allowed):
  $csrf = (string)$ci->session->userdata('inventory_matrix_spreadsheet_csrf');
  if (!preg_match('/\A[0-9a-f]{64}\z/D', $csrf)) { $csrf = bin2hex(random_bytes(32)); $ci->session->set_userdata('inventory_matrix_spreadsheet_csrf', $csrf); }
  $syncUrl = site_url('inventory/daily-matrix/spreadsheet/sync');
  $settingsUrl = site_url('inventory/daily-matrix/spreadsheet/settings');
  $monthSelector = (string)($matrix_month_selector ?? '#pmdMonth');
?>
<div class="card mb-3" data-inventory-matrix-sheet data-sync-url="<?php echo html_escape($syncUrl); ?>" data-settings-url="<?php echo html_escape($settingsUrl); ?>" data-csrf="<?php echo html_escape($csrf); ?>" data-month-selector="<?php echo html_escape($monthSelector); ?>">
  <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div><strong class="d-block">Ekspor Daily Matrix</strong><small class="text-muted" data-inventory-matrix-sheet-status>Pilih bulan dari filter, lalu perbarui snapshot spreadsheet.</small></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="<?php echo html_escape($settingsUrl); ?>">Pengaturan &amp; Panduan</a><button type="button" class="btn btn-primary btn-sm" data-inventory-matrix-sheet-sync>Perbarui Spreadsheet</button></div>
  </div>
</div>
<?php endif; ?>
