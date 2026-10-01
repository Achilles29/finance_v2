<?php
/**
 * users/form.php — Form buat / edit user
 * $edit_mode: bool
 * $user: array|null (untuk edit)
 * $all_roles: array
 * $user_roles: array  (list role_id yang sudah dipunya user, untuk edit)
 * $form_action: string
 */
$user       = $user ?? null;
$user_roles = $user_roles ?? [];
$employee_options = $employee_options ?? [];
$protected_role_ids = array_map('intval', (array)($protected_role_ids ?? []));
$can_manage_protected_roles = !empty($can_manage_protected_roles);
$selected_employee_id = (int)set_value('employee_id', (int)($user['employee_id'] ?? 0));
?>
<style>
  .users-form .users-form-title {
    font-size: 1.45rem;
    letter-spacing: 0.01em;
  }
  .users-form .card {
    max-width: 940px;
  }
  .users-form .form-control,
  .users-form .form-select {
    min-height: 46px;
  }
  .users-form .roles-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
  }
  .users-form .role-card {
    border: 1px solid #e4d9d0;
    border-radius: 10px;
    padding: 0.75rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    cursor: pointer;
    transition: all .14s ease;
    background: #fff;
  }
  .users-form .role-card:hover {
    border-color: #cdaea0;
    box-shadow: 0 3px 10px rgba(82,42,27,0.06);
  }
  .users-form .role-card.is-checked {
    border-color: #b22f3a;
    background: #fff8f8;
  }
  .users-form .role-card.is-locked {
    opacity: 0.88;
    background: #f4f0ed;
  }
  .users-form .role-name {
    font-weight: 700;
    color: #3a2a26;
    line-height: 1.2;
  }
  .users-form .role-code {
    margin-top: 0.25rem;
    display: inline-block;
    font-size: 0.68rem;
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    background: #eceff3;
    color: #51606f;
  }
  .users-form .role-check {
    width: 18px;
    height: 18px;
    accent-color: #b22f3a;
    flex-shrink: 0;
  }
  .users-form .role-lock {
    display: inline-block;
    margin-top: 0.35rem;
    font-size: 0.72rem;
    color: #8b3b3b;
  }
  @media (max-width: 767.98px) {
    .users-form .roles-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="users-form">
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary">
    <i class="ri ri-arrow-left-line me-1"></i>Kembali
  </a>
  <h5 class="fw-bold mb-0 users-form-title"><?= htmlspecialchars($title ?? '') ?></h5>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <?= form_open($form_action) ?>

    <!-- Username (hanya tampil saat buat, tidak bisa diubah) -->
    <?php if (!$edit_mode): ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
      <input type="text" name="username" class="form-control" maxlength="60" required
             pattern="[a-zA-Z0-9_\-]+" title="Hanya huruf, angka, _ dan -"
             placeholder="contoh: kasir_01"
             value="<?= set_value('username') ?>">
      <div class="form-text">Tidak bisa diubah setelah dibuat.</div>
    </div>
    <?php else: ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Username</label>
      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" disabled>
    </div>
    <?php endif; ?>

    <!-- Email -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Tautkan Pegawai</label>
      <select name="employee_id" class="form-select">
        <option value="">Tanpa tautan pegawai</option>
        <?php foreach ($employee_options as $emp): ?>
          <?php
            $empId = (int)($emp['id'] ?? 0);
            $linkedUserId = (int)($emp['linked_user_id'] ?? 0);
            $isCurrent = $edit_mode && $linkedUserId > 0 && $linkedUserId === (int)($user['id'] ?? 0);
            $isLockedByOther = $linkedUserId > 0 && !$isCurrent;
            $label = trim((string)($emp['employee_code'] ?? '') . ' - ' . (string)($emp['employee_name'] ?? ''));
            if (!empty($emp['division_name'])) {
              $label .= ' (' . (string)$emp['division_name'] . ')';
            }
            if ($isLockedByOther && !empty($emp['linked_username'])) {
              $label .= ' [dipakai: ' . (string)$emp['linked_username'] . ']';
            }
          ?>
          <option value="<?= $empId ?>"
            <?= $selected_employee_id === $empId ? 'selected' : '' ?>
            <?= $isLockedByOther ? 'disabled' : '' ?>>
            <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Akun pegawai bisa login portal `my/*` jika field ini terisi.</div>
    </div>

    <!-- Email -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Email</label>
      <input type="email" name="email" class="form-control" maxlength="150"
             placeholder="opsional"
             value="<?= set_value('email', htmlspecialchars($user['email'] ?? '')) ?>">
    </div>

    <!-- Password -->
    <div class="mb-3">
      <label class="form-label fw-semibold">
        Password <?= !$edit_mode ? '<span class="text-danger">*</span>' : '' ?>
      </label>
      <div class="input-group">
        <input type="password" name="password" id="password" class="form-control" maxlength="72"
               <?= !$edit_mode ? 'required' : '' ?>
               placeholder="<?= $edit_mode ? 'Kosongkan jika tidak ingin diubah' : 'Minimal 8 karakter' ?>"
               minlength="8">
        <button type="button" class="btn btn-outline-secondary px-3" id="toggle-pass" tabindex="-1">
          <i class="ri ri-eye-line" id="eye-icon"></i>
        </button>
      </div>
    </div>

    <!-- Role -->
    <div class="mb-4">
      <label class="form-label fw-semibold">Role</label>
      <?php if (!$can_manage_protected_roles): ?>
        <div class="form-text mb-2">Role `ADMIN` dan `SUPERADMIN` hanya bisa diubah oleh akun superadmin.</div>
      <?php endif; ?>
      <?php if (empty($all_roles)): ?>
        <p class="text-muted small">Belum ada role aktif.</p>
      <?php else: ?>
      <div class="roles-grid">
        <?php foreach ($all_roles as $role): ?>
        <?php
          $roleId = (int)($role['id'] ?? 0);
          $isProtected = in_array($roleId, $protected_role_ids, true);
          $isLocked = $isProtected && !$can_manage_protected_roles;
          $isChecked = in_array($roleId, $user_roles);
        ?>
        <label class="role-card <?= $isChecked ? 'is-checked' : '' ?> <?= $isLocked ? 'is-locked' : '' ?>" for="role_<?= $roleId ?>">
          <span>
            <span class="role-name"><?= htmlspecialchars($role['role_name']) ?></span><br>
            <span class="role-code"><?= htmlspecialchars($role['role_code']) ?></span>
            <?php if ($isLocked): ?>
              <span class="role-lock">Hanya superadmin yang boleh mengubah role ini.</span>
            <?php endif; ?>
          </span>
          <input class="role-check" type="checkbox" name="role_ids[]"
                 id="role_<?= $roleId ?>" value="<?= $roleId ?>"
                 <?= $isChecked ? 'checked' : '' ?>
                 <?= $isLocked ? 'disabled' : '' ?>>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary px-4">
        <i class="ri ri-save-line me-1"></i> <?= $edit_mode ? 'Simpan Perubahan' : 'Buat User' ?>
      </button>
      <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary">Batal</a>
    </div>

    <?= form_close() ?>
  </div>
</div>
</div>

<script>
$('#toggle-pass').on('click', function(){
  const inp = $('#password');
  const icon = $('#eye-icon');
  if (inp.attr('type') === 'password') {
    inp.attr('type', 'text');
    icon.removeClass('ri-eye-line').addClass('ri-eye-off-line');
  } else {
    inp.attr('type', 'password');
    icon.removeClass('ri-eye-off-line').addClass('ri-eye-line');
  }
});

$('.users-form').on('change', '.role-check', function(){
  const card = $(this).closest('.role-card');
  if ($(this).is(':checked')) {
    card.addClass('is-checked');
  } else {
    card.removeClass('is-checked');
  }
});
</script>
