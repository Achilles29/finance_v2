<?php
/**
 * roles/form.php — Buat / edit role
 * $role: array|null
 * $edit_mode: bool
 * $form_action: string
 * $division_options: array
 */
$role             = $role ?? null;
$division_options = $division_options ?? [];
$selected_div_id  = (int)($role['division_scope_id'] ?? 0);
?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('roles') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-arrow-left-line me-1"></i>Kembali
  </a>
  <h5 class="fw-bold mb-0"><?= htmlspecialchars($title ?? '') ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:540px;">
  <div class="card-body">
    <?= form_open($form_action) ?>

    <!-- Kode role (hanya saat buat) -->
    <?php if (!$edit_mode): ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Kode Role <span class="text-danger">*</span></label>
      <input type="text" name="role_code" class="form-control text-uppercase" maxlength="50" required
             pattern="[a-zA-Z0-9_\-]+" title="Hanya huruf besar, angka, _ dan -"
             placeholder="contoh: KITCHEN_CREW"
             value="<?= set_value('role_code') ?>"
             oninput="this.value = this.value.toUpperCase()">
      <div class="form-text">Tidak bisa diubah setelah dibuat.</div>
    </div>
    <?php else: ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Kode Role</label>
      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($role['role_code']) ?>" disabled>
    </div>
    <?php endif; ?>

    <!-- Nama -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Nama Role <span class="text-danger">*</span></label>
      <input type="text" name="role_name" class="form-control" maxlength="100" required
             placeholder="contoh: Kitchen Crew"
             value="<?= set_value('role_name', htmlspecialchars($role['role_name'] ?? '')) ?>">
    </div>

    <!-- Deskripsi -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Deskripsi</label>
      <input type="text" name="description" class="form-control" maxlength="255"
             placeholder="Penjelasan singkat role ini"
             value="<?= set_value('description', htmlspecialchars($role['description'] ?? '')) ?>">
    </div>

    <!-- Scope Divisi (opsional) -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Scope Divisi <span class="text-muted fw-normal">(opsional)</span></label>
      <select name="division_scope_id" class="form-select">
        <option value="">— Lintas divisi (tidak terikat satu divisi) —</option>
        <?php foreach ($division_options as $div): ?>
          <option value="<?= $div['id'] ?>" <?= $selected_div_id === (int)$div['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($div['division_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">
        Role seperti "Kitchen Crew" bisa ditandai scope KITCHEN agar data difilter per divisi.
      </div>
      <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
        <i class="ri ri-information-line me-1"></i>
        <strong>Cara kerja scope:</strong> Setelah dipilih, user dengan role ini akan otomatis dibatasi ke divisi tersebut
        saat mengakses halaman yang mendukung filter divisi (stok, laporan, produksi, dll).
        Halaman yang <em>belum</em> mengimplementasi filter tidak akan terpengaruh — perlu ditambahkan manual per halaman.
        Gunakan <code>$this->active_division_id()</code> di controller untuk membaca scope ini.
      </div>
    </div>

    <!-- Status (hanya saat edit) -->
    <?php if ($edit_mode): ?>
    <div class="mb-4">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" role="switch"
               value="1" <?= ($role['is_active'] ?? 1) ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_active">Role aktif</label>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary px-4">
        <i class="ri ri-save-line me-1"></i> <?= $edit_mode ? 'Simpan' : 'Buat Role' ?>
      </button>
      <a href="<?= base_url('roles') ?>" class="btn btn-outline-secondary">Batal</a>
    </div>

    <?= form_close() ?>
  </div>
</div>
