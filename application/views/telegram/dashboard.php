<?php
$stats = (array)($stats ?? []);
$targets = (array)($targets ?? []);
$edit = (array)($edit_target ?? []);
$canSave = empty($edit) ? !empty($can_create) : !empty($can_edit);
?>
<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1 fw-bold"><i class="ri-telegram-line me-1"></i>Telegram Bot</h4><p class="text-muted mb-0 small">Target internal aktif menjadi allowlist webhook dan delivery.</p></div>
    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('telegram/settings') ?>">Pengaturan & test</a>
  </div>
  <?php if ($flash = $this->session->flashdata('success')): ?><div class="alert alert-success"><?= html_escape($flash) ?></div><?php endif; ?>
  <?php if ($flash = $this->session->flashdata('error')): ?><div class="alert alert-danger"><?= html_escape($flash) ?></div><?php endif; ?>
  <div class="row g-3 mb-3">
    <?php foreach (['active_targets'=>'Target aktif','active_schedules'=>'Jadwal aktif','pending_queue'=>'Queue pending','unknown_queue'=>'Status UNKNOWN'] as $key=>$label): ?>
      <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small"><?= html_escape($label) ?></div><div class="fs-3 fw-bold"><?= (int)($stats[$key] ?? 0) ?></div></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-header fw-semibold">Allowlist target</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Nama</th><th>Chat ID</th><th>Tipe</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($targets as $row): ?><tr><td><?= html_escape($row['title']) ?></td><td><code><?= html_escape($row['chat_id']) ?></code></td><td><?= html_escape($row['target_type']) ?></td><td><?= !empty($row['is_active']) ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td><td><?php if (!empty($can_edit)): ?><a class="btn btn-outline-secondary btn-sm" href="<?= site_url('telegram?edit='.(int)$row['id']) ?>">Edit</a><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$targets): ?><tr><td colspan="5" class="text-muted text-center py-3">Belum ada target. Webhook akan mengabaikan semua chat.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-header fw-semibold"><?= $edit ? 'Edit target' : 'Tambah target' ?></div><div class="card-body">
      <?php if ($canSave): ?><form method="post" action="<?= site_url('telegram/target_save') ?>">
        <input type="hidden" name="tg_target_csrf" value="<?= html_escape($target_csrf ?? '') ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">Nama internal</label><input class="form-control" name="title" maxlength="150" required value="<?= html_escape($edit['title'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Chat ID</label><input class="form-control font-monospace" name="chat_id" pattern="-[0-9]{5,19}" required value="<?= html_escape($edit['chat_id'] ?? '') ?>"><div class="form-text">Hanya ID negatif group/supergroup/channel.</div></div>
        <div class="mb-2"><label class="form-label">Tipe</label><select class="form-select" name="target_type"><?php foreach (['GROUP','SUPERGROUP','CHANNEL'] as $type): ?><option value="<?= $type ?>" <?= ($edit['target_type'] ?? 'SUPERGROUP') === $type ? 'selected' : '' ?>><?= $type ?></option><?php endforeach; ?></select></div>
        <div class="form-check mb-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="tg-active" <?= !isset($edit['is_active']) || !empty($edit['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="tg-active">Aktif</label></div>
        <button class="btn btn-primary" type="submit">Simpan target</button><?php if ($edit): ?><a class="btn btn-link" href="<?= site_url('telegram') ?>">Batal</a><?php endif; ?>
      </form><?php else: ?><p class="text-muted mb-0">Anda tidak memiliki izin untuk mengubah target.</p><?php endif; ?>
    </div></div></div>
  </div>
</div>
