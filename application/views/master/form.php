<?php
$autoCodeSource = $cfg['auto_code_source'] ?? '';
$autoCodeInput = $cfg['code_input'] ?? '';
$vcProductDefault = (float)($variable_cost_defaults['product'] ?? 20);
$vcComponentDefault = (float)($variable_cost_defaults['component'] ?? 20);
$isPayrollMaster = in_array($entity, ['pay-component', 'pay-profile', 'pay-profile-line', 'pay-assignment'], true);
$isAttLocationMaster = ($entity === 'att-location');
$isExtraMaster = ($entity === 'extra');
$employeeAccessRoles = $employee_access_roles ?? [];
$employeeSelectedRoleIds = array_map('intval', (array)($employee_selected_role_ids ?? []));
$employeeProtectedRoleIds = array_map('intval', (array)($employee_protected_role_ids ?? []));
$employeeCanManageProtectedRoles = !empty($employee_can_manage_protected_roles);
$employeeLinkedUsers = $employee_linked_users ?? [];
$employeeLoginUser = $employee_login_user ?? null;
$employeeLoginUserCount = (int)($employee_login_user_count ?? count($employeeLinkedUsers));
$employeeHasLoginUser = !empty($employeeLoginUser);
$employeeLoginChecked = $employeeHasLoginUser || (string)set_value('create_login_account') === '1';
$employeeLoginUsernameValue = set_value('login_username', (string)($employeeLoginUser['username'] ?? ''));
$employeeRoleStorageReady = !empty($employee_role_storage_ready);
$hasAjaxLookupField = false;
foreach (($cfg['fields'] ?? []) as $fieldCfg) {
  if (($fieldCfg['type'] ?? '') === 'ajax_lookup') {
    $hasAjaxLookupField = true;
    break;
  }
}
$current = function ($name, $default = '') use ($row) {
    if (set_value($name) !== '') {
        return set_value($name);
    }
    if (!empty($row) && array_key_exists($name, $row)) {
        return $row[$name];
    }
    return $default;
};

$hasFileField = false;
foreach (($cfg['fields'] ?? []) as $fieldCfg) {
  if (($fieldCfg['type'] ?? '') === 'file' && empty($fieldCfg['readonly'])) {
    $hasFileField = true;
    break;
  }
}

$renderReadonlyValue = static function ($value, string $type): string {
    if ($value === null || $value === '') {
        return '';
    }
    if ($type === 'checkbox') {
        return (int)$value === 1 ? '1' : '0';
    }
    return (string)$value;
};
?>

<?php if ($isPayrollMaster): ?>
<style>
  .master-form--payroll .master-form-title {
    font-size: 1.45rem;
    letter-spacing: 0.01em;
  }
  .master-form--payroll .master-form-card {
    border: 0;
    box-shadow: 0 0.25rem 0.9rem rgba(67, 30, 30, 0.08);
  }
  .master-form--payroll .form-control,
  .master-form--payroll .form-select {
    min-height: 40px;
  }
</style>
<?php endif; ?>

<?php if ($entity === 'org-employee'): ?>
<style>
  .employee-login-box {
    border: 1px solid #e3d5ca;
    border-radius: 14px;
    padding: 1rem;
    background: #fffdf9;
  }
  .employee-login-fields {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed #e8d7cb;
  }
  .employee-access-box {
    border: 1px solid #e3d5ca;
    border-radius: 14px;
    padding: 1rem;
    background: #fffaf7;
  }
  .employee-access-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
  }
  .employee-access-card {
    border: 1px solid #e4d9d0;
    border-radius: 10px;
    padding: 0.75rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: #fff;
    transition: all .14s ease;
  }
  .employee-access-card.is-checked {
    border-color: #b22f3a;
    background: #fff7f7;
  }
  .employee-access-card.is-locked {
    opacity: 0.88;
    background: #f5f1ee;
  }
  .employee-access-name {
    font-weight: 700;
    color: #3a2a26;
    line-height: 1.2;
  }
  .employee-access-meta {
    margin-top: 0.25rem;
    display: inline-block;
    font-size: 0.68rem;
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    background: #eceff3;
    color: #51606f;
  }
  .employee-access-lock {
    display: inline-block;
    margin-top: 0.35rem;
    font-size: 0.72rem;
    color: #8b3b3b;
  }
  .employee-access-check {
    width: 18px;
    height: 18px;
    accent-color: #b22f3a;
    flex-shrink: 0;
  }
  @media (max-width: 767.98px) {
    .employee-access-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
<?php endif; ?>

<?php if ($hasAjaxLookupField): ?>
<style>
  .master-ajax-box {
    position: relative;
  }
  .master-ajax-result {
    position: absolute;
    z-index: 25;
    inset: calc(100% + 6px) 0 auto 0;
    background: #fff;
    border: 1px solid #e5d2c6;
    border-radius: 14px;
    box-shadow: 0 18px 34px rgba(63, 35, 24, 0.12);
    max-height: 240px;
    overflow: auto;
    display: none;
  }
  .master-ajax-result.is-open {
    display: block;
  }
  .master-ajax-item {
    padding: .75rem .9rem;
    border-bottom: 1px solid #f1e4db;
    cursor: pointer;
  }
  .master-ajax-item:last-child {
    border-bottom: 0;
  }
  .master-ajax-item:hover {
    background: #fff7f1;
  }
  .master-ajax-item-title {
    font-weight: 600;
    color: #4d352c;
  }
  .master-ajax-item-meta {
    font-size: .84rem;
    color: #7b6a61;
    margin-top: .15rem;
    line-height: 1.35;
  }
  .master-ajax-item-layout {
    display: flex;
    gap: .75rem;
    align-items: center;
  }
  .master-ajax-item-thumb {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
    background: #f6efe9;
    border: 1px solid #ead8cd;
    flex: 0 0 auto;
  }
  .master-ajax-selected {
    margin-top: .5rem;
    padding: .6rem .75rem;
    border: 1px solid #ead8cd;
    border-radius: 12px;
    background: #fff8f3;
  }
  .master-ajax-selected.is-empty {
    display: none;
  }
</style>
<?php endif; ?>

<?php if ($isAttLocationMaster): ?>
<link
  rel="stylesheet"
  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
  crossorigin=""
>

<style>
  .att-location-map-wrap {
    border: 1px solid #dec7c7;
    border-radius: 14px;
    background: #fff;
    padding: 12px;
  }
  .att-location-map {
    width: 100%;
    min-height: 360px;
    border: 1px solid #d9c8c8;
    border-radius: 12px;
  }
  .att-location-map-toolbar {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }
  .att-location-map-status {
    font-size: 0.9rem;
    color: #4d5966;
  }
</style>
<?php endif; ?>

<div class="master-form <?php echo $isPayrollMaster ? 'master-form--payroll' : ''; ?>">
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0 master-form-title"><?php echo html_escape($title); ?></h4>
  <div class="d-flex gap-2 flex-wrap justify-content-end">
    <?php if ($entity === 'product' && !empty($is_edit) && !empty($row['id'])): ?>
      <a href="<?php echo site_url('master/product/detail/' . (int)$row['id']); ?>" class="btn btn-outline-info">Detail Product</a>
    <?php endif; ?>
    <a href="<?php echo site_url('master/' . $entity); ?>" class="btn btn-outline-secondary">Kembali</a>
  </div>
</div>

<div class="card master-form-card">
  <div class="card-body">
    <?php if ($entity === 'org-employee'): ?>
      <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
        <i class="ri ri-file-text-line mt-1"></i>
        <div>
          <strong>Nominal gaji diatur melalui Kontrak Pegawai.</strong>
          Gaji pokok, tunjangan, dan uang makan harian tidak lagi diinput di Master Pegawai agar absensi serta payroll membaca satu sumber yang sama. Tarif lembur diatur terpisah melalui Master Standar Lembur.
          <?php if (!empty($row['id'])): ?>
            <a class="alert-link" href="<?php echo site_url('hr-contracts/generate?employee_id=' . (int)$row['id']); ?>">Buat perubahan kontrak</a>.
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <form method="post" action="<?php echo site_url($form_action); ?>" <?php echo $hasFileField ? 'enctype="multipart/form-data"' : ''; ?>>
      <input type="hidden" name="master_mutation_csrf" value="<?php echo html_escape((string)($master_mutation_csrf_token ?? '')); ?>">
      <div class="row">
        <?php foreach ($cfg['fields'] as $f): ?>
          <?php
            $name = $f['name'];
            $label = $f['label'];
            $type = $f['type'] ?? 'text';
            $val = $current($name, $type === 'checkbox' ? 1 : '');
            $isReadonly = !empty($f['readonly']);
            if ($name === 'variable_cost_mode' && in_array($entity, ['product', 'component'], true) && (string)$val === '') {
                $val = 'DEFAULT';
            }
            $inputId = '';
            if ($name === $autoCodeSource || $name === $autoCodeInput) {
                $inputId = 'id_' . $name;
            }
          ?>

          <div
            class="col-md-6 mb-3 <?php echo in_array($type, ['textarea'], true) ? 'col-12' : ''; ?>"
            <?php echo $isExtraMaster ? 'data-extra-field="' . html_escape($name) . '"' : ''; ?>
          >
            <?php if ($type !== 'checkbox'): ?>
              <label class="form-label mb-1"><?php echo html_escape($label); ?></label>
            <?php endif; ?>

            <?php if ($type === 'textarea'): ?>
              <textarea name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" rows="3" <?php echo $isReadonly ? 'readonly' : ''; ?>><?php echo html_escape((string)$val); ?></textarea>
            <?php elseif ($type === 'select'): ?>
              <?php $selectOptions = $options[$name] ?? ($f['options'] ?? []); ?>
              <select name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                <option value="">- pilih -</option>
                <?php foreach ($selectOptions as $opt): ?>
                  <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$val === (string)$opt['value'] ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$opt['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($type === 'ajax_lookup'): ?>
              <div class="master-ajax-box" data-master-ajax-field="<?php echo html_escape($name); ?>" data-search-url="<?php echo html_escape(site_url('master/lookup-search/' . $entity . '/' . $name)); ?>" data-show-thumb="<?php echo $isExtraMaster ? '0' : '1'; ?>">
                <input type="hidden" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" value="<?php echo html_escape((string)$val); ?>">
                <input type="text" class="form-control master-ajax-input" data-display-input="<?php echo html_escape($name); ?>" placeholder="<?php echo html_escape((string)($f['placeholder'] ?? 'Cari data...')); ?>" <?php echo $isReadonly ? 'readonly' : ''; ?>>
                <div class="master-ajax-result" data-result="<?php echo html_escape($name); ?>"></div>
                <div class="master-ajax-selected is-empty" data-selected-preview="<?php echo html_escape($name); ?>"></div>
              </div>
            <?php elseif ($type === 'date'): ?>
              <input
                type="date"
                name="<?php echo html_escape($name); ?>"
                id="id_<?php echo html_escape($name); ?>"
                class="form-control"
                value="<?php echo html_escape((string)$val); ?>"
                <?php echo $isReadonly ? 'readonly' : ''; ?>
              >
            <?php elseif ($type === 'number'): ?>
              <input
                type="number"
                name="<?php echo html_escape($name); ?>"
                id="id_<?php echo html_escape($name); ?>"
                class="form-control"
                value="<?php echo html_escape((string)$val); ?>"
                <?php echo !empty($f['step']) ? 'step="' . html_escape($f['step']) . '"' : ''; ?>
                <?php echo ($isAttLocationMaster && in_array($name, ['latitude', 'longitude'], true)) ? 'data-map-coordinate="1"' : ''; ?>
                <?php echo $isReadonly ? 'readonly' : ''; ?>
              >
            <?php elseif ($type === 'file'): ?>
              <?php if (!$isReadonly): ?>
              <input type="file" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" accept="image/*">
              <?php endif; ?>
              <?php if ($name === 'photo_file'): ?>
                <?php
                  $currentPhotoPath = trim((string)($row['photo_path'] ?? ''));
                  $currentPhotoUrl = $currentPhotoPath !== '' ? base_url(ltrim($currentPhotoPath, '/')) : '';
                ?>
                <div class="mt-2" id="productPhotoPreviewWrap" style="<?php echo $currentPhotoUrl !== '' ? '' : 'display:none;'; ?>">
                  <img
                    id="productPhotoPreviewImg"
                    src="<?php echo html_escape($currentPhotoUrl); ?>"
                    alt="Preview foto produk"
                    style="max-height:120px; max-width:100%; border:1px solid #dee2e6; border-radius:8px; padding:2px;"
                  >
                </div>
                <small class="text-muted d-block mt-1">Pilih file untuk mengganti foto produk.</small>
              <?php elseif (!empty($row['photo_path'])): ?>
                <small class="text-muted d-block mt-1">File saat ini: <?php echo html_escape((string)$row['photo_path']); ?></small>
              <?php endif; ?>
            <?php elseif ($type === 'checkbox'): ?>
              <div class="form-check mt-3">
                <input type="checkbox" name="<?php echo html_escape($name); ?>" class="form-check-input" id="id_<?php echo html_escape($name); ?>" value="1" <?php echo (int)$val === 1 ? 'checked' : ''; ?> <?php echo $isReadonly ? 'disabled' : ''; ?>>
                <label class="form-check-label" for="id_<?php echo html_escape($name); ?>"><?php echo html_escape($label); ?></label>
              </div>
            <?php else: ?>
              <input type="text" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" value="<?php echo html_escape((string)$val); ?>" <?php echo $isReadonly ? 'readonly' : ''; ?>>
            <?php endif; ?>

            <?php if ($isReadonly && $type === 'select'): ?>
              <input type="hidden" value="<?php echo html_escape($renderReadonlyValue($val, $type)); ?>">
            <?php endif; ?>

            <?php if ($name === $autoCodeInput && $autoCodeSource !== ''): ?>
              <small class="text-muted">Otomatis diisi dari inisial nama, tetap bisa diedit manual.</small>
            <?php endif; ?>

            <?php if ($isReadonly): ?>
              <small class="text-muted d-block mt-1">Field ini hanya tampil sebagai informasi dari database.</small>
            <?php endif; ?>

            <?php if ($entity === 'org-employee' && $name === 'employee_code'): ?>
              <small class="text-muted">Kode pegawai dibuat otomatis saat simpan (skema internal EMP-YYYYMM####).</small>
            <?php endif; ?>

            <?php if ($entity === 'org-employee' && $name === 'employee_nip'): ?>
              <small class="text-muted">NIP otomatis: tanggal gabung (YYYYMMDD) + tanggal lahir (YYYYMMDD) + urutan 3 digit.</small>
            <?php endif; ?>

            <?php if ($name === 'variable_cost_percent' && in_array($entity, ['product', 'component'], true)): ?>
              <small class="text-muted">Mode DEFAULT: nilai otomatis dari pengaturan, CUSTOM: isi manual, NONE: disembunyikan.</small>
            <?php endif; ?>

            <?php if ($entity === 'extra' && $name === 'extra_type'): ?>
              <small class="text-muted">ADD untuk tambahan berbayar/opsional, REMOVE untuk menghilangkan komponen standar, CHOICE untuk memilih salah satu opsi pengganti, INFO untuk catatan operasional tanpa efek harga atau stok.</small>
            <?php endif; ?>

            <?php if ($entity === 'extra' && $name === 'source_kind'): ?>
              <small class="text-muted">Pilih sumber yang benar-benar dipotong saat extra ini dipakai. Jika tidak memotong stok sama sekali, pilih "Tidak potong stok".</small>
            <?php endif; ?>

            <?php if ($entity === 'extra' && $name === 'replacement_kind'): ?>
              <small class="text-muted">Pakai hanya jika extra ini menukar recipe lama dengan sumber lain. Jika extra hanya tambahan biasa, biarkan "Tidak mengganti recipe lama".</small>
            <?php endif; ?>

            <?php if ($isAttLocationMaster && in_array($name, ['latitude', 'longitude'], true)): ?>
              <small class="text-muted">Koordinat dipilih dari peta (drag pin / klik peta).</small>
            <?php endif; ?>
          </div>

          <?php if ($isAttLocationMaster && $name === 'longitude'): ?>
            <div class="col-12 mb-3">
              <label class="form-label mb-2">Pilih Titik Lokasi</label>
              <div class="att-location-map-wrap">
                <div class="att-location-map-toolbar">
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="attLocationUseCurrent">Gunakan Lokasi Saat Ini</button>
                  <div id="attLocationMapStatus" class="att-location-map-status">Geser pin atau klik peta untuk mengisi koordinat.</div>
                </div>
                <div id="attLocationMap" class="att-location-map"></div>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php if ($entity === 'item'): ?>
        <div class="border rounded p-3 mb-3" id="newMaterialBox">
          <h6 class="mb-2">Auto-create Bahan Baku (opsional jika Item ini Bahan Baku)</h6>
          <div class="row">
            <div class="col-md-4 mb-2">
              <label class="form-label mb-1">Kode Bahan Baku Baru</label>
              <input type="text" name="new_material_code" class="form-control" value="<?php echo html_escape((string)set_value('new_material_code')); ?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label mb-1">Nama Bahan Baku Baru</label>
              <input type="text" name="new_material_name" class="form-control" value="<?php echo html_escape((string)set_value('new_material_name')); ?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label mb-1">HPP Bahan Baku Baru</label>
              <input type="number" step="0.0001" name="new_material_hpp" class="form-control" value="<?php echo html_escape((string)set_value('new_material_hpp')); ?>">
            </div>
          </div>
          <small class="text-muted">Isi bagian ini hanya jika Bahan Baku Existing belum dipilih.</small>
        </div>
      <?php endif; ?>

      <?php if ($entity === 'org-employee'): ?>
        <div class="employee-login-box mb-3">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div>
              <h6 class="mb-1">Akun Login</h6>
              <div class="text-muted small">Centang untuk membuat akun login pegawai agar otomatis muncul di halaman <strong>finance/users</strong>.</div>
            </div>
            <?php if ($employeeHasLoginUser): ?>
              <span class="badge text-bg-success">Sudah punya akun login</span>
            <?php endif; ?>
          </div>

          <?php if ($employeeHasLoginUser): ?>
            <div class="alert alert-light border small mt-3 mb-0">
              Akun login utama sudah tertaut sebagai <strong><?php echo html_escape((string)($employeeLoginUser['username'] ?? '-')); ?></strong>.
              <?php if ($employeeLoginUserCount > 1): ?>
                Ada <?php echo (int)$employeeLoginUserCount; ?> akun login tertaut ke pegawai ini; form ini mengelola akun pertama yang sudah ada.
              <?php else: ?>
                Anda bisa isi password baru jika ingin reset password dari form pegawai.
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="form-check mt-3">
            <input type="checkbox" class="form-check-input" name="create_login_account" id="id_create_login_account" value="1" <?php echo $employeeLoginChecked ? 'checked' : ''; ?> <?php echo $employeeHasLoginUser ? 'disabled' : ''; ?>>
            <label class="form-check-label" for="id_create_login_account">
              <?php echo $employeeHasLoginUser ? 'Akun login aktif untuk pegawai ini' : 'Buat akun login untuk pegawai ini'; ?>
            </label>
          </div>

          <div class="employee-login-fields" id="employeeLoginFields" style="<?php echo $employeeLoginChecked ? '' : 'display:none;'; ?>">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label mb-1">Username Login<?php echo !$employeeHasLoginUser ? ' <span class="text-danger">*</span>' : ''; ?></label>
                <input type="text" name="login_username" id="id_login_username" class="form-control" maxlength="60" pattern="[a-zA-Z0-9_\-]+" title="Hanya huruf, angka, _ dan -" value="<?php echo html_escape((string)$employeeLoginUsernameValue); ?>" <?php echo $employeeHasLoginUser ? 'readonly' : ''; ?> >
                <small class="text-muted d-block mt-1"><?php echo $employeeHasLoginUser ? 'Username dikelola dari user yang sudah ada dan tidak diubah dari form pegawai.' : 'Username dipakai untuk login ke aplikasi.'; ?></small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label mb-1">Password Login<?php echo !$employeeHasLoginUser ? ' <span class="text-danger">*</span>' : ''; ?></label>
                <input type="password" name="login_password" id="id_login_password" class="form-control" maxlength="72" minlength="8" placeholder="<?php echo $employeeHasLoginUser ? 'Kosongkan jika tidak ingin reset password' : 'Minimal 8 karakter'; ?>">
                <small class="text-muted d-block mt-1"><?php echo $employeeHasLoginUser ? 'Isi hanya jika ingin mengganti password akun login yang sudah tertaut.' : 'Password awal untuk akun login baru.'; ?></small>
              </div>
            </div>
          </div>
        </div>

        <div class="employee-access-box mb-3">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
            <div>
              <h6 class="mb-1">Hak Akses Login</h6>
              <div class="text-muted small">Checklist ini menjadi role default pegawai dan otomatis disinkronkan ke akun user yang tertaut.</div>
            </div>
            <?php if (!$employeeCanManageProtectedRoles): ?>
              <span class="badge text-bg-light border">ADMIN &amp; SUPERADMIN terkunci</span>
            <?php endif; ?>
          </div>

          <?php if (!$employeeRoleStorageReady): ?>
            <div class="alert alert-warning mb-0">Hak akses pegawai belum aktif. Jalankan SQL <strong>2026-06-02a_org_employee_role_assignment.sql</strong> terlebih dahulu.</div>
          <?php else: ?>
            <?php if (empty($employeeLinkedUsers)): ?>
              <div class="alert alert-light border small mb-3">Belum ada akun user yang tertaut. Role yang dipilih tetap disimpan di profil pegawai dan akan diterapkan otomatis saat akun user ditautkan.</div>
            <?php else: ?>
              <?php $linkedNames = array_map(static function ($user): string {
                  $label = (string)($user['username'] ?? '-');
                  if ((int)($user['is_active'] ?? 0) !== 1) {
                      $label .= ' (nonaktif)';
                  }
                  return $label;
              }, $employeeLinkedUsers); ?>
              <div class="alert alert-light border small mb-3">Sinkron aktif ke user: <strong><?php echo html_escape(implode(', ', $linkedNames)); ?></strong></div>
            <?php endif; ?>

            <?php if (empty($employeeAccessRoles)): ?>
              <p class="text-muted small mb-0">Belum ada role aktif.</p>
            <?php else: ?>
              <div class="employee-access-grid">
                <?php foreach ($employeeAccessRoles as $role): ?>
                  <?php
                    $roleId = (int)($role['id'] ?? 0);
                    $isProtectedRole = in_array($roleId, $employeeProtectedRoleIds, true);
                    $isLockedRole = $isProtectedRole && !$employeeCanManageProtectedRoles;
                    $isCheckedRole = in_array($roleId, $employeeSelectedRoleIds, true);
                  ?>
                  <label class="employee-access-card <?php echo $isCheckedRole ? 'is-checked' : ''; ?> <?php echo $isLockedRole ? 'is-locked' : ''; ?>" for="employee_role_<?php echo $roleId; ?>">
                    <span>
                      <span class="employee-access-name"><?php echo html_escape((string)($role['role_name'] ?? '')); ?></span><br>
                      <span class="employee-access-meta"><?php echo html_escape((string)($role['role_code'] ?? '')); ?></span>
                      <?php if ($isLockedRole): ?>
                        <span class="employee-access-lock d-block">Hanya superadmin yang boleh mengubah role ini.</span>
                      <?php endif; ?>
                    </span>
                    <input class="employee-access-check" type="checkbox" name="employee_role_ids[]" id="employee_role_<?php echo $roleId; ?>" value="<?php echo $roleId; ?>" <?php echo $isCheckedRole ? 'checked' : ''; ?> <?php echo $isLockedRole ? 'disabled' : ''; ?>>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="mt-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="<?php echo site_url('master/' . $entity); ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
</div>

<?php if ($entity === 'item'): ?>
<script>
(function () {
  var check = document.getElementById('id_is_material');
  var box = document.getElementById('newMaterialBox');
  if (!check || !box) return;
  var sync = function () {
    box.style.display = check.checked ? 'block' : 'none';
  };
  check.addEventListener('change', sync);
  sync();
})();
</script>
<?php endif; ?>

<?php if ($entity === 'org-employee'): ?>
<script>
(function () {
  var loginToggle = document.getElementById('id_create_login_account');
  var loginFields = document.getElementById('employeeLoginFields');
  if (loginToggle && loginFields) {
    var syncLoginFields = function () {
      loginFields.style.display = loginToggle.checked ? '' : 'none';
    };
    loginToggle.addEventListener('change', syncLoginFields);
    syncLoginFields();
  }

  var checks = Array.prototype.slice.call(document.querySelectorAll('.employee-access-check'));
  checks.forEach(function (check) {
    check.addEventListener('change', function () {
      var card = check.closest('.employee-access-card');
      if (!card) return;
      card.classList.toggle('is-checked', check.checked);
    });
  });
})();
</script>
<?php endif; ?>

<script>
(function () {
  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-master-ajax-field]'));
  if (!boxes.length) return;

  function escapeHtml(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m];
    });
  }

  function closeAll() {
    boxes.forEach(function (box) {
      var result = box.querySelector('.master-ajax-result');
      if (result) {
        result.classList.remove('is-open');
        result.innerHTML = '';
      }
    });
  }

  function renderSelectedPreview(box, row) {
    var preview = box.querySelector('[data-selected-preview]');
    var showThumb = box.getAttribute('data-show-thumb') !== '0';
    if (!preview) return;
    if (!row || (!row.label && !row.meta && !row.thumb_url)) {
      preview.classList.add('is-empty');
      preview.innerHTML = '';
      return;
    }
    var thumb = showThumb && row.thumb_url ? '<img class="master-ajax-item-thumb" src="' + escapeHtml(row.thumb_url) + '" alt="' + escapeHtml(row.label || '') + '">' : '';
    var meta = row.meta ? '<div class="master-ajax-item-meta">' + escapeHtml(row.meta) + '</div>' : '';
    preview.innerHTML = '<div class="master-ajax-item-layout">' + thumb + '<div><div class="master-ajax-item-title">' + escapeHtml(row.label || '') + '</div>' + meta + '</div></div>';
    preview.classList.remove('is-empty');
  }

  function setSelected(box, row) {
    var hidden = box.querySelector('input[type="hidden"]');
    var display = box.querySelector('.master-ajax-input');
    if (hidden) hidden.value = String(row.value || '');
    if (display) display.value = String(row.label || '');
    renderSelectedPreview(box, row);
    closeAll();
  }

  function bindBox(box) {
    var hidden = box.querySelector('input[type="hidden"]');
    var display = box.querySelector('.master-ajax-input');
    var result = box.querySelector('.master-ajax-result');
    var searchUrl = box.getAttribute('data-search-url') || '';
    var showThumb = box.getAttribute('data-show-thumb') !== '0';
    var timer = null;
    if (!hidden || !display || !result || !searchUrl) return;

    if (hidden.value) {
      fetch(searchUrl + '?id=' + encodeURIComponent(hidden.value), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (t) {
          var json = JSON.parse(t);
          var rows = Array.isArray(json.rows) ? json.rows : [];
          if (rows.length) {
            display.value = String(rows[0].label || '');
            renderSelectedPreview(box, rows[0]);
          }
        })
        .catch(function () {});
    }

    function buildItemHtml(row) {
      var thumb = showThumb && row.thumb_url ? '<img class="master-ajax-item-thumb" src="' + escapeHtml(row.thumb_url) + '" alt="' + escapeHtml(row.label || '') + '">' : '';
      var meta = row.meta ? '<div class="master-ajax-item-meta">' + escapeHtml(row.meta) + '</div>' : '';
      return '<div class="master-ajax-item" data-value="' + escapeHtml(row.value) + '" data-label="' + escapeHtml(row.label) + '" data-meta="' + escapeHtml(row.meta || '') + '" data-thumb-url="' + escapeHtml(row.thumb_url || '') + '"><div class="master-ajax-item-layout">' + thumb + '<div><div class="master-ajax-item-title">' + escapeHtml(row.label) + '</div>' + meta + '</div></div></div>';
    }

    function attachItemClicks() {
      Array.prototype.forEach.call(result.querySelectorAll('.master-ajax-item[data-value]'), function (item) {
        item.addEventListener('click', function () {
          setSelected(box, {
            value: item.getAttribute('data-value'),
            label: item.getAttribute('data-label'),
            meta: item.getAttribute('data-meta'),
            thumb_url: item.getAttribute('data-thumb-url')
          });
        });
      });
    }

    function doSearch(q) {
      fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (t) {
          var json = JSON.parse(t);
          var rows = Array.isArray(json.rows) ? json.rows : [];
          if (!rows.length) {
            result.innerHTML = '<div class="master-ajax-item"><div class="master-ajax-item-title">Tidak ada hasil.</div></div>';
            result.classList.add('is-open');
            return;
          }
          result.innerHTML = rows.map(buildItemHtml).join('');
          result.classList.add('is-open');
          attachItemClicks();
        })
        .catch(function () {
          result.innerHTML = '<div class="master-ajax-item"><div class="master-ajax-item-title">Gagal memuat hasil.</div></div>';
          result.classList.add('is-open');
        });
    }

    display.addEventListener('input', function () {
      hidden.value = '';
      renderSelectedPreview(box, null);
      var q = display.value.trim();
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function () { doSearch(q); }, 280);
    });

    display.addEventListener('focus', function () {
      if (result.innerHTML.trim() !== '') {
        result.classList.add('is-open');
      } else if (!hidden.value) {
        doSearch('');
      }
    });
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-master-ajax-field]')) {
      closeAll();
    }
  });

  boxes.forEach(bindBox);
})();
</script>

<?php if ($autoCodeSource !== '' && $autoCodeInput !== ''): ?>
<script>
(function () {
  var nameInput = document.getElementById('id_<?php echo html_escape($autoCodeSource); ?>');
  var codeInput = document.getElementById('id_<?php echo html_escape($autoCodeInput); ?>');
  if (!nameInput || !codeInput) return;

  var userTouchedCode = codeInput.value.trim() !== '';

  function buildCode(rawName) {
    var clean = rawName
      .toUpperCase()
      .replace(/[^A-Z0-9 ]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();

    if (!clean) return '';

    var parts = clean.split(' ');
    var initials = '';
    for (var i = 0; i < parts.length; i++) {
      if (!parts[i]) continue;
      initials += parts[i].charAt(0);
    }

    if (initials.length < 3) {
      initials = clean.replace(/\s+/g, '').slice(0, 3);
    }

    return initials;
  }

  function syncCode() {
    if (userTouchedCode && codeInput.value.trim() !== '') return;
    codeInput.value = buildCode(nameInput.value);
  }

  nameInput.addEventListener('input', syncCode);
  codeInput.addEventListener('input', function () {
    userTouchedCode = codeInput.value.trim() !== '';
  });

  syncCode();
})();
</script>
<?php endif; ?>

<?php if (in_array($entity, ['product', 'component'], true)): ?>
<script>
(function () {
  var mode = document.getElementById('id_variable_cost_mode');
  var percent = document.getElementById('id_variable_cost_percent');
  if (!mode || !percent) return;

  var defaultPercent = <?php echo $entity === 'product' ? json_encode($vcProductDefault) : json_encode($vcComponentDefault); ?>;
  var fieldWrap = percent.closest('.col-md-6') || percent.closest('.col-12') || percent.parentElement;

  function sync() {
    var modeVal = (mode.value || '').trim().toUpperCase();
    if (modeVal === '') {
      modeVal = 'DEFAULT';
      mode.value = 'DEFAULT';
    }

    var isDefault = modeVal === 'DEFAULT';
    var isNone = modeVal === 'NONE';

    if (fieldWrap) {
      if (isNone) {
        fieldWrap.style.setProperty('display', 'none', 'important');
      } else {
        fieldWrap.style.removeProperty('display');
      }
    }

    if (isDefault) {
      percent.value = defaultPercent;
      percent.readOnly = true;
    } else if (isNone) {
      percent.value = '';
      percent.readOnly = true;
    } else {
      percent.readOnly = false;
    }
  }

  mode.addEventListener('change', sync);
  mode.addEventListener('input', sync);
  sync();
})();
</script>
<?php endif; ?>

<?php if ($entity === 'product'): ?>
<script>
(function () {
  var fileInput = document.getElementById('id_photo_file');
  var previewWrap = document.getElementById('productPhotoPreviewWrap');
  var previewImg = document.getElementById('productPhotoPreviewImg');
  if (!fileInput || !previewWrap || !previewImg) return;

  fileInput.addEventListener('change', function () {
    var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!file) {
      if (!previewImg.getAttribute('src')) {
        previewWrap.style.display = 'none';
      }
      return;
    }
    if (!file.type || file.type.indexOf('image/') !== 0) {
      return;
    }

    var objectUrl = URL.createObjectURL(file);
    previewImg.src = objectUrl;
    previewWrap.style.display = '';
    previewImg.onload = function () {
      URL.revokeObjectURL(objectUrl);
    };
  });
})();
</script>
<?php endif; ?>

<?php if ($entity === 'extra'): ?>
<script>
(function () {
  var sourceKind = document.getElementById('id_source_kind');
  var replacementKind = document.getElementById('id_replacement_kind');
  if (!sourceKind || !replacementKind) return;

  function fieldWrap(name) {
    return document.querySelector('[data-extra-field="' + name + '"]');
  }

  function showField(name, visible) {
    var wrap = fieldWrap(name);
    if (!wrap) return;
    if (visible) {
      wrap.style.removeProperty('display');
    } else {
      wrap.style.setProperty('display', 'none', 'important');
    }
  }

  function clearLookupValue(name) {
    var hidden = document.getElementById('id_' + name);
    if (hidden) hidden.value = '';
    var box = hidden ? hidden.closest('[data-master-ajax-field]') : null;
    if (!box) return;
    var textInput = box.querySelector('.master-ajax-input');
    var preview = box.querySelector('[data-selected-preview]');
    var result = box.querySelector('[data-result]');
    if (textInput) textInput.value = '';
    if (preview) {
      preview.innerHTML = '';
      preview.classList.add('is-empty');
    }
    if (result) {
      result.innerHTML = '';
      result.classList.remove('is-open');
    }
  }

  function clearNumericValue(name) {
    var input = document.getElementById('id_' + name);
    if (input) input.value = '';
  }

  function syncSourceFields() {
    var kind = String(sourceKind.value || 'NONE').toUpperCase();
    showField('source_product_id', kind === 'PRODUCT');
    showField('source_component_id', kind === 'COMPONENT');
    showField('source_material_id', kind === 'MATERIAL');
    showField('source_qty', kind !== 'NONE');

    if (kind !== 'PRODUCT') clearLookupValue('source_product_id');
    if (kind !== 'COMPONENT') clearLookupValue('source_component_id');
    if (kind !== 'MATERIAL') clearLookupValue('source_material_id');
    if (kind === 'NONE') clearNumericValue('source_qty');
  }

  function syncReplacementFields() {
    var kind = String(replacementKind.value || 'NONE').toUpperCase();
    showField('replacement_product_id', kind === 'PRODUCT');
    showField('replacement_component_id', kind === 'COMPONENT');
    showField('replacement_material_id', kind === 'MATERIAL');
    showField('replacement_qty', kind !== 'NONE');

    if (kind !== 'PRODUCT') clearLookupValue('replacement_product_id');
    if (kind !== 'COMPONENT') clearLookupValue('replacement_component_id');
    if (kind !== 'MATERIAL') clearLookupValue('replacement_material_id');
    if (kind === 'NONE') clearNumericValue('replacement_qty');
  }

  function syncAll() {
    syncSourceFields();
    syncReplacementFields();
  }

  sourceKind.addEventListener('change', syncSourceFields);
  sourceKind.addEventListener('input', syncSourceFields);
  replacementKind.addEventListener('change', syncReplacementFields);
  replacementKind.addEventListener('input', syncReplacementFields);
  syncAll();
})();
</script>
<?php endif; ?>

<?php if ($isAttLocationMaster): ?>
<script
  src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
  crossorigin=""
></script>
<script>
(function () {
  var latInput = document.getElementById('id_latitude');
  var lngInput = document.getElementById('id_longitude');
  var mapEl = document.getElementById('attLocationMap');
  var statusEl = document.getElementById('attLocationMapStatus');
  var useCurrentBtn = document.getElementById('attLocationUseCurrent');
  if (!latInput || !lngInput || !mapEl) return;

  function setStatus(message) {
    if (statusEl) statusEl.textContent = message;
  }

  function parseCoordinate(value) {
    var parsed = parseFloat(String(value || '').replace(',', '.'));
    return Number.isFinite(parsed) ? parsed : null;
  }

  var lat = parseCoordinate(latInput.value);
  var lng = parseCoordinate(lngInput.value);
  var hasInitial = lat !== null && lng !== null;
  var defaultPoint = hasInitial ? [lat, lng] : [-6.2000000, 106.8166667];

  if (typeof L === 'undefined') {
    setStatus('Peta gagal dimuat. Isi koordinat manual untuk sementara.');
    latInput.readOnly = false;
    lngInput.readOnly = false;
    return;
  }

  latInput.readOnly = true;
  lngInput.readOnly = true;

  var map = L.map(mapEl, {
    center: defaultPoint,
    zoom: hasInitial ? 16 : 12,
    zoomControl: true
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 20,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var marker = L.marker(defaultPoint, { draggable: true }).addTo(map);

  function syncInputs(latlng, message) {
    latInput.value = Number(latlng.lat).toFixed(7);
    lngInput.value = Number(latlng.lng).toFixed(7);
    if (message) setStatus(message);
  }

  function updatePoint(latlng, message) {
    marker.setLatLng(latlng);
    map.panTo(latlng);
    syncInputs(latlng, message);
  }

  marker.on('dragend', function (event) {
    updatePoint(event.target.getLatLng(), 'Koordinat diperbarui dari drag pin.');
  });

  map.on('click', function (event) {
    updatePoint(event.latlng, 'Koordinat diperbarui dari klik peta.');
  });

  if (useCurrentBtn) {
    useCurrentBtn.addEventListener('click', function () {
      if (!navigator.geolocation) {
        setStatus('Browser tidak mendukung geolokasi.');
        return;
      }
      useCurrentBtn.disabled = true;
      setStatus('Mengambil lokasi saat ini...');
      navigator.geolocation.getCurrentPosition(function (position) {
        var latlng = L.latLng(position.coords.latitude, position.coords.longitude);
        map.setZoom(18);
        updatePoint(latlng, 'Lokasi saat ini berhasil dipakai.');
        useCurrentBtn.disabled = false;
      }, function () {
        setStatus('Lokasi saat ini gagal diambil. Cek izin GPS browser.');
        useCurrentBtn.disabled = false;
      }, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      });
    });
  }

  function tryUseCurrentLocationOnOpen() {
    if (!navigator.geolocation) return;
    if (hasInitial) return;
    setStatus('Mendeteksi lokasi saat ini...');
    navigator.geolocation.getCurrentPosition(function (position) {
      var latlng = L.latLng(position.coords.latitude, position.coords.longitude);
      map.setZoom(18);
      updatePoint(latlng, 'Lokasi saat ini dipakai otomatis.');
    }, function () {
      setStatus('Lokasi saat ini tidak tersedia, gunakan drag pin / klik peta.');
    }, {
      enableHighAccuracy: true,
      timeout: 8000,
      maximumAge: 0
    });
  }

  syncInputs(marker.getLatLng(), hasInitial ? 'Koordinat awal dimuat dari data existing.' : 'Set titik lokasi untuk mengisi koordinat.');
  setTimeout(function () {
    map.invalidateSize();
    tryUseCurrentLocationOnOpen();
  }, 180);
})();
</script>
<?php endif; ?>
