<?php
/**
 * users/index.php — Daftar user
 */
$canCreate = !empty($current_user['is_superadmin']) || !empty($user_perms['auth.users.manage']['can_create']);
$status = $status ?? 'active';
$searchValue = trim((string)($filter['search'] ?? ''));
$buildUserTabUrl = static function (string $tabStatus) use ($searchValue): string {
    $query = ['status' => $tabStatus];
    if ($searchValue !== '') {
        $query['search'] = $searchValue;
    }
    return base_url('users' . (!empty($query) ? '?' . http_build_query($query) : ''));
};
?>
<style>
  .users-index .users-header-title {
    font-size: 1.55rem;
    letter-spacing: 0.01em;
  }
  .users-index .status-tabs {
    display: inline-flex;
    gap: 0.4rem;
    padding: 0.3rem;
    border-radius: 999px;
    background: #f7efe8;
    border: 1px solid #ead8cc;
  }
  .users-index .status-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 88px;
    padding: 0.42rem 0.9rem;
    border-radius: 999px;
    text-decoration: none;
    color: #7b5a4c;
    font-weight: 600;
    font-size: 0.82rem;
  }
  .users-index .status-tab.is-active {
    background: #b11f2d;
    color: #fff;
    box-shadow: 0 8px 18px rgba(177, 31, 45, 0.18);
  }
  .users-index .filter-wrap .form-control,
  .users-index .filter-wrap .form-select {
    min-height: 40px;
  }
  .users-index .table th,
  .users-index .table td {
    padding: 0.72rem 0.85rem;
    vertical-align: middle;
  }
  .users-index .table td {
    font-size: 0.92rem;
  }
  .users-index .username-cell {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    justify-content: space-between;
    flex-wrap: wrap;
  }
  .users-index .username-main {
    min-width: 0;
    flex: 1 1 220px;
  }
  .users-index .username-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
  }
  .users-index .username-label {
    font-weight: 700;
    color: #2f2f2f;
    letter-spacing: 0.01em;
    line-height: 1.2;
  }
  .users-index .user-meta-row {
    margin-top: 0.35rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }
  .users-index .user-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.72rem;
    line-height: 1;
    padding: 0.34rem 0.52rem;
    border-radius: 999px;
    background: #f6eee7;
    color: #7b5e50;
    border: 1px solid #ead9cd;
    white-space: nowrap;
  }
  .users-index .user-meta-sep {
    color: #bea393;
  }
  .users-index .emp-badge {
    display: inline-block;
    font-size: 0.67rem;
    line-height: 1;
    padding: 0.28rem 0.48rem;
    border-radius: 999px;
    border: 1px solid #8ec7f8;
    background: #e8f4ff;
    color: #2169ad;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    white-space: nowrap;
    align-self: flex-start;
  }
</style>

<div class="users-index">
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="fw-bold mb-0 users-header-title"><i class="ri ri-user-settings-line me-2 text-primary"></i>Manajemen User</h5>
  <?php if (!empty($current_user['is_superadmin']) || !empty($user_perms['auth.users.permissions']['can_view'])): ?>
  <a href="<?= base_url('users/access-audit') ?>" class="btn btn-outline-primary">Simulasi Akses</a>
  <?php endif; ?>
  <?php if ($canCreate): ?>
  <a href="<?= base_url('users/create') ?>" class="btn btn-primary">
    <i class="ri ri-add-line me-1"></i> Tambah User
  </a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-3 filter-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="status-tabs">
        <a href="<?= htmlspecialchars($buildUserTabUrl('active')) ?>" class="status-tab <?= $status === 'active' ? 'is-active' : '' ?>">Aktif</a>
        <a href="<?= htmlspecialchars($buildUserTabUrl('inactive')) ?>" class="status-tab <?= $status === 'inactive' ? 'is-active' : '' ?>">Nonaktif</a>
        <a href="<?= htmlspecialchars($buildUserTabUrl('all')) ?>" class="status-tab <?= $status === 'all' ? 'is-active' : '' ?>">Semua</a>
      </div>

      <?= form_open('users', ['method' => 'get', 'class' => 'd-flex flex-wrap gap-2 align-items-center']) ?>
      <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
      <input type="text" name="search" class="form-control" style="max-width:280px;"
             placeholder="Cari username / email..." value="<?= htmlspecialchars($searchValue) ?>">
      <button type="submit" class="btn btn-outline-secondary"><i class="ri ri-search-line me-1"></i>Filter</button>
      <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary">Reset</a>
      <?= form_close() ?>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;">#</th>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Login Terakhir</th>
            <th>Dibuat</th>
            <th style="width:170px;" class="action-cell">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data user.</td></tr>
          <?php else: ?>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="username-cell">
                <div class="username-main">
                  <div class="username-head">
                    <span class="username-label"><?= htmlspecialchars($u['username']) ?></span>
                  </div>
                  <?php if (!empty($u['division_name']) || !empty($u['position_name'])): ?>
                  <div class="user-meta-row">
                    <?php if (!empty($u['division_name'])): ?>
                      <span class="user-meta-pill"><?= htmlspecialchars((string)$u['division_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($u['position_name'])): ?>
                      <span class="user-meta-pill"><?= htmlspecialchars((string)$u['position_name']) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                </div>
                <?php if (!empty($u['employee_id'])): ?>
                <span class="emp-badge">Pegawai</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="text-muted small"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td>
              <?php if ((int)$u['is_active'] === 1): ?>
              <span class="badge bg-success-subtle text-success">Aktif</span>
              <?php else: ?>
              <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small">
              <?= !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—' ?>
            </td>
            <td class="text-muted small"><?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?></td>
            <td class="action-cell">
              <div class="d-flex gap-1 flex-nowrap justify-content-end">
                <a href="<?= base_url('users/detail/' . (int)$u['id']) ?>" class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Detail" aria-label="Detail">
                  <i class="ri ri-eye-line"></i>
                </a>
                <a href="<?= base_url('users/edit/' . (int)$u['id']) ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                  <i class="ri ri-edit-line"></i>
                </a>
                <a href="<?= base_url('users/permissions/' . (int)$u['id']) ?>" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Override Izin" aria-label="Override Izin">
                  <i class="ri ri-shield-keyhole-line"></i>
                </a>
                <?php if ((int)$u['id'] !== (int)($current_user['id'] ?? 0)): ?>
                <a href="<?= base_url('users/toggle/' . (int)$u['id']) ?>"
                   class="btn btn-sm btn-outline-warning action-icon-btn"
                   data-bs-toggle="tooltip"
                   title="<?= (int)$u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                   aria-label="<?= (int)$u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                   onclick="return confirm('<?= (int)$u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> user ini?')">
                  <i class="ri ri-refresh-line"></i>
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
