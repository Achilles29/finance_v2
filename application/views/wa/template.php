<?php
$templates = (array)($templates ?? []);
$canCreate = (bool)($can_create ?? false);
$canEdit   = (bool)($can_edit ?? false);
$canDelete = (bool)($can_delete ?? false);
$mutationCsrf = (string)($wa_template_group_mutation_csrf ?? '');

$categories = ['BROADCAST','GROUP','PROMO','INFO','REMINDER','CUSTOM'];
$catLabel   = ['BROADCAST'=>'Broadcast','GROUP'=>'Grup','PROMO'=>'Promo','INFO'=>'Info','REMINDER'=>'Reminder','CUSTOM'=>'Custom'];
$catBadge   = ['BROADCAST'=>'bg-primary','GROUP'=>'bg-info','PROMO'=>'bg-warning text-dark','INFO'=>'bg-secondary','REMINDER'=>'bg-success','CUSTOM'=>'bg-light text-dark'];
?>

<style>
.wa-modal .modal-content {
  border: 0;
  border-radius: 1.25rem;
  overflow: hidden;
  box-shadow: 0 1.2rem 3rem rgba(74, 38, 35, .22);
}
.wa-modal .modal-header {
  align-items: flex-start;
  background: linear-gradient(135deg, #fff7f2, #fff);
  border-bottom: 1px solid #f0d9d2;
  padding: 1.25rem 1.4rem;
}
.wa-modal .modal-body {
  padding: 1.4rem;
}
.wa-modal .modal-footer {
  background: #fffaf7;
  border-top: 1px solid #f0d9d2;
  padding: 1rem 1.4rem;
}
.wa-modal-icon {
  display: inline-flex;
  width: 2.35rem;
  height: 2.35rem;
  align-items: center;
  justify-content: center;
  border-radius: .85rem;
  background: #ffe7df;
  color: #9b2f28;
  font-size: 1.25rem;
  flex: 0 0 auto;
}
.wa-modal .form-control,
.wa-modal .form-select {
  border-radius: .8rem;
}
</style>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-file-text-line me-1"></i>Template Pesan WA</h4>
      <p class="text-muted mb-0 small">Kelola template pesan untuk broadcast dan pengiriman grup.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= site_url('wa/template/schedules') ?>" class="btn btn-outline-primary btn-sm">
        <i class="ri ri-calendar-schedule-line me-1"></i>Jadwal Laporan
      </a>
    <?php if ($canCreate): ?>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-template">
        <i class="ri ri-add-line me-1"></i>Tambah Template
      </button>
    <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($templates as $tpl): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100 <?= $tpl['is_active'] ? '' : 'opacity-50' ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <span class="badge <?= $catBadge[$tpl['category']] ?? 'bg-secondary' ?> me-1">
              <?= $catLabel[$tpl['category']] ?? $tpl['category'] ?>
            </span>
            <strong><?= html_escape($tpl['name']) ?></strong>
          </div>
          <div class="d-flex gap-1">
            <?php if ($canEdit): ?>
            <button class="btn btn-xs btn-outline-secondary" onclick="editTemplate(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES) ?>)">
              <i class="ri ri-edit-line"></i>
            </button>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <code class="small text-muted"><?= html_escape($tpl['template_code']) ?></code>
          <div class="mt-2 small" style="white-space:pre-wrap;max-height:120px;overflow:hidden;">
            <?= html_escape(mb_substr($tpl['body'], 0, 200)) ?><?= strlen($tpl['body']) > 200 ? '…' : '' ?>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <?php if ($canEdit): ?>
          <form method="post" action="<?= site_url('wa/template') ?>" class="d-inline">
            <?php if ($mutationCsrf !== ''): ?>
            <input type="hidden" name="wa_template_group_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
            <button type="submit" class="btn btn-xs <?= $tpl['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
              <?= $tpl['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
            </button>
          </form>
          <?php endif; ?>
          <?php if ($canDelete): ?>
          <form method="post" action="<?= site_url('wa/template') ?>" class="d-inline ms-auto">
            <?php if ($mutationCsrf !== ''): ?>
            <input type="hidden" name="wa_template_group_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
            <button type="submit" class="btn btn-xs btn-outline-danger" onclick="return confirm('Hapus template ini?')">
              <i class="ri ri-delete-bin-line"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($templates)): ?>
    <div class="col-12">
      <div class="alert alert-info text-center">Belum ada template. Tambahkan template pertama Anda.</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Form Template -->
<div class="modal fade wa-modal" id="modal-template" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex gap-3">
          <span class="wa-modal-icon"><i class="ri ri-file-text-line"></i></span>
          <div>
            <h5 class="modal-title" id="modal-template-title">Tambah Template</h5>
            <div class="small text-muted mt-1">Template dipakai untuk broadcast, grup, dan pesan otomatis.</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= site_url('wa/template') ?>">
        <?php if ($mutationCsrf !== ''): ?>
        <input type="hidden" name="wa_template_group_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
        <?php endif; ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="tpl-id" value="0">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Kode Template <span class="text-danger">*</span></label>
              <input type="text" name="template_code" id="tpl-code" class="form-control font-monospace" required
                placeholder="contoh: promo_weekend">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nama Tampilan <span class="text-danger">*</span></label>
              <input type="text" name="name" id="tpl-name" class="form-control" required placeholder="Promo Weekend">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Kategori</label>
              <select name="category" id="tpl-category" class="form-select">
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>"><?= $catLabel[$cat] ?? $cat ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Isi Template <span class="text-danger">*</span></label>
              <textarea name="body" id="tpl-body" class="form-control font-monospace" rows="10" required
                placeholder="Halo {{nama}}! Promo spesial untuk Anda…"></textarea>
              <div class="form-text">Gunakan <code>&#123;&#123;variabel&#125;&#125;</code> untuk substitusi dinamis.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Contoh Variabel (JSON)</label>
              <input type="text" name="sample_variables" id="tpl-sample" class="form-control font-monospace"
                placeholder='{"nama":"Budi","promo":"Diskon 20%"}'>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editTemplate(tpl) {
  document.getElementById('modal-template-title').textContent = 'Edit Template';
  document.getElementById('tpl-id').value = tpl.id;
  document.getElementById('tpl-code').value = tpl.template_code;
  document.getElementById('tpl-name').value = tpl.name;
  document.getElementById('tpl-category').value = tpl.category;
  document.getElementById('tpl-body').value = tpl.body;
  document.getElementById('tpl-sample').value = tpl.sample_variables || '';
  new bootstrap.Modal(document.getElementById('modal-template')).show();
}

document.getElementById('modal-template')?.addEventListener('hidden.bs.modal', function () {
  document.getElementById('modal-template-title').textContent = 'Tambah Template';
  this.querySelector('form').reset();
  document.getElementById('tpl-id').value = 0;
});
</script>
