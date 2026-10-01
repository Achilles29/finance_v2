<?php
$user = $user ?? [];
$user_roles = $user_roles ?? [];
$override_modules = $override_modules ?? [];
$canManage = !empty($current_user['is_superadmin']) || !empty($user_perms['auth.users.manage']['can_edit']);
$canPerms = !empty($current_user['is_superadmin']) || !empty($user_perms['auth.users.permissions']['can_edit']);
?>
<style>
  .user-detail .detail-title {
    font-size: 1.45rem;
    letter-spacing: 0.01em;
  }
  .user-detail .detail-card {
    border: 0;
    box-shadow: 0 0.25rem 0.9rem rgba(67, 30, 30, 0.08);
  }
  .user-detail .stat-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #8b6f62;
  }
  .user-detail .stat-value {
    margin-top: 0.2rem;
    font-weight: 700;
    color: #2f2f2f;
  }
  .user-detail .chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .user-detail .chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #e4d9d0;
    background: #fff8f4;
    color: #6d5649;
    font-size: 0.76rem;
    line-height: 1;
  }
  .user-detail .summary-box {
    border: 1px solid #eadbcf;
    border-radius: 14px;
    padding: 1rem;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6ef 100%);
  }
</style>

<div class="user-detail">
  <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary">
        <i class="ri ri-arrow-left-line me-1"></i>Kembali
      </a>
      <h5 class="fw-bold mb-0 detail-title">Detail User: <span class="text-primary"><?= htmlspecialchars((string)($user['username'] ?? '')) ?></span></h5>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if (!empty($current_user['is_superadmin']) || !empty($user_perms['auth.users.permissions']['can_view'])): ?>
      <a href="<?= base_url('users/access-audit/' . (int)($user['id'] ?? 0)) ?>" class="btn btn-outline-primary">Simulasi Akses</a>
      <?php endif; ?>
      <?php if ($canManage): ?>
      <a href="<?= base_url('users/edit/' . (int)($user['id'] ?? 0)) ?>" class="btn btn-outline-primary">
        <i class="ri ri-edit-line me-1"></i>Edit
      </a>
      <?php endif; ?>
      <?php if ($canPerms): ?>
      <a href="<?= base_url('users/permissions/' . (int)($user['id'] ?? 0)) ?>" class="btn btn-outline-warning">
        <i class="ri ri-shield-keyhole-line me-1"></i>Override Izin
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card detail-card mb-3">
    <div class="card-body">
      <div class="summary-box mb-3">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
          <div>
            <div class="text-muted small mb-1">Username</div>
            <div class="h4 mb-1"><?= htmlspecialchars((string)($user['username'] ?? '-')) ?></div>
            <?php if (!empty($user['employee_name'])): ?>
              <div class="text-muted"><?= htmlspecialchars((string)$user['employee_name']) ?></div>
            <?php endif; ?>
          </div>
          <div>
            <?php if ((int)($user['is_active'] ?? 0) === 1): ?>
              <span class="badge bg-success-subtle text-success fs-6">Aktif</span>
            <?php else: ?>
              <span class="badge bg-danger-subtle text-danger fs-6">Nonaktif</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-3 col-sm-6">
          <div class="stat-label">Email</div>
          <div class="stat-value"><?= htmlspecialchars((string)($user['email'] ?? '—')) ?></div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="stat-label">Login Terakhir</div>
          <div class="stat-value"><?= !empty($user['last_login_at']) ? date('d/m/Y H:i', strtotime($user['last_login_at'])) : '—' ?></div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="stat-label">Dibuat</div>
          <div class="stat-value"><?= !empty($user['created_at']) ? date('d/m/Y H:i', strtotime($user['created_at'])) : '—' ?></div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="stat-label">Diupdate</div>
          <div class="stat-value"><?= !empty($user['updated_at']) ? date('d/m/Y H:i', strtotime($user['updated_at'])) : '—' ?></div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card border-0 bg-light h-100">
            <div class="card-body">
              <h6 class="fw-bold mb-3">Tautan Pegawai</h6>
              <?php if (empty($user['employee_id'])): ?>
                <div class="text-muted small">User ini belum ditautkan ke data pegawai.</div>
              <?php else: ?>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="stat-label">Nama Pegawai</div>
                    <div class="stat-value"><?= htmlspecialchars((string)($user['employee_name'] ?? '-')) ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="stat-label">Kode Pegawai</div>
                    <div class="stat-value"><?= htmlspecialchars((string)($user['employee_code'] ?? '—')) ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="stat-label">Divisi</div>
                    <div class="stat-value"><?= htmlspecialchars((string)($user['division_name'] ?? '—')) ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="stat-label">Jabatan</div>
                    <div class="stat-value"><?= htmlspecialchars((string)($user['position_name'] ?? '—')) ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="stat-label">NIP</div>
                    <div class="stat-value"><?= htmlspecialchars((string)($user['employee_nip'] ?? '—')) ?></div>
                  </div>
                </div>
                <div class="mt-3">
                  <a href="<?= base_url('master/org-employee/detail/' . (int)$user['employee_id']) ?>" class="btn btn-sm btn-outline-info">
                    <i class="ri ri-id-card-line me-1"></i>Buka Detail Pegawai
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card border-0 bg-light h-100">
            <div class="card-body">
              <h6 class="fw-bold mb-3">Role dan Override</h6>
              <div class="mb-3">
                <div class="stat-label mb-2">Role Aktif</div>
                <?php if (empty($user_roles)): ?>
                  <div class="text-muted small">Belum ada role.</div>
                <?php else: ?>
                  <div class="chip-row">
                    <?php foreach ($user_roles as $role): ?>
                      <span class="chip"><?= htmlspecialchars((string)($role['role_name'] ?? '-')) ?> <span class="text-muted"><?= htmlspecialchars((string)($role['role_code'] ?? '')) ?></span></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div>
                <div class="stat-label">Jumlah Override</div>
                <div class="stat-value"><?= (int)$override_count ?></div>
                <?php if (!empty($override_modules)): ?>
                  <div class="chip-row mt-2">
                    <?php foreach ($override_modules as $module): ?>
                      <span class="chip"><?= htmlspecialchars((string)$module) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="text-muted small mt-2">Belum ada override izin tersimpan.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
