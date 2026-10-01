<?php
$groups    = (array)($groups ?? []);
$canCreate = (bool)($can_create ?? false);
$canEdit   = (bool)($can_edit ?? false);
$canDelete = (bool)($can_delete ?? false);
$mutationCsrf = (string)($wa_template_group_mutation_csrf ?? '');
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
      <h4 class="mb-1 fw-bold"><i class="ri ri-group-2-line me-1"></i>Manajemen Grup WA</h4>
      <p class="text-muted mb-0 small">Mapping grup WhatsApp untuk pengiriman pesan terjadwal dan otomatis.</p>
    </div>
    <?php if ($canCreate): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-group">
      <i class="ri ri-add-line me-1"></i>Tambah Grup
    </button>
    <?php endif; ?>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($groups as $grp): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100 <?= $grp['is_active'] ? '' : 'opacity-50' ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <strong><?= html_escape($grp['group_name']) ?></strong>
            <?php if ($grp['purpose']): ?>
            <span class="badge bg-label-primary ms-1"><?= html_escape($grp['purpose']) ?></span>
            <?php endif; ?>
          </div>
          <?php if (!$grp['is_active']): ?>
          <span class="badge bg-secondary">Nonaktif</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div class="mb-1 small">
            <span class="text-muted me-1">Key:</span>
            <code><?= html_escape($grp['group_key']) ?></code>
          </div>
          <div class="mb-1 small">
            <span class="text-muted me-1">JID:</span>
            <span class="font-monospace"><?= $grp['group_jid'] ? html_escape($grp['group_jid']) : '<em class="text-danger">Belum diset</em>' ?></span>
          </div>
          <?php if ($grp['notes']): ?>
          <div class="text-muted small mt-1"><?= html_escape($grp['notes']) ?></div>
          <?php endif; ?>
          <?php if ($grp['last_sent_at']): ?>
          <div class="text-muted small mt-1">Terakhir kirim: <?= html_escape($grp['last_sent_at']) ?></div>
          <?php endif; ?>
        </div>
        <div class="card-footer d-flex gap-1 flex-wrap">
          <?php if ($canCreate && $grp['group_jid'] && $grp['is_active']): ?>
          <button class="btn btn-xs btn-success"
            onclick="openSendModal(<?= (int)$grp['id'] ?>, <?= htmlspecialchars(json_encode($grp['group_name']), ENT_QUOTES) ?>)">
            <i class="ri ri-send-plane-line me-1"></i>Kirim Pesan
          </button>
          <?php endif; ?>
          <?php if ($canEdit): ?>
          <button class="btn btn-xs btn-outline-secondary"
            onclick="editGroup(<?= htmlspecialchars(json_encode($grp), ENT_QUOTES) ?>)">
            <i class="ri ri-edit-line"></i>
          </button>
          <form method="post" action="<?= site_url('wa/group') ?>" class="d-inline">
            <?php if ($mutationCsrf !== ''): ?>
            <input type="hidden" name="wa_template_group_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$grp['id'] ?>">
            <button type="submit" class="btn btn-xs <?= $grp['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
              <?= $grp['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
            </button>
          </form>
          <?php endif; ?>
          <?php if ($canDelete): ?>
          <form method="post" action="<?= site_url('wa/group') ?>" class="d-inline ms-auto">
            <?php if ($mutationCsrf !== ''): ?>
            <input type="hidden" name="wa_template_group_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$grp['id'] ?>">
            <button type="submit" class="btn btn-xs btn-outline-danger" onclick="return confirm('Hapus grup ini?')">
              <i class="ri ri-delete-bin-line"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($groups)): ?>
    <div class="col-12">
      <div class="alert alert-info text-center">Belum ada grup. Tambahkan grup WhatsApp pertama Anda.</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Form Grup -->
<div class="modal fade wa-modal" id="modal-group" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex gap-3">
          <span class="wa-modal-icon"><i class="ri ri-group-2-line"></i></span>
          <div>
            <h5 class="modal-title" id="modal-group-title">Tambah Grup</h5>
            <div class="small text-muted mt-1">Mapping grup WhatsApp agar pengiriman otomatis lebih terarah.</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= site_url('wa/group') ?>">
        <?php if ($mutationCsrf !== ''): ?>
        <input type="hidden" name="wa_template_group_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
        <?php endif; ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="grp-id" value="0">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Key (unik) <span class="text-danger">*</span></label>
            <input type="text" name="group_key" id="grp-key" class="form-control font-monospace" required
              placeholder="omzet_harian">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Grup <span class="text-danger">*</span></label>
            <input type="text" name="group_name" id="grp-name" class="form-control" required
              placeholder="CAFE PUSAT">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Group JID</label>
            <input type="text" name="group_jid" id="grp-jid" class="form-control font-monospace"
              placeholder="120363147815009475@g.us">
            <div class="form-text">JID grup WA dari bot. Lihat di wa-bot untuk mendapatkan JID grup.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Tujuan / Label</label>
            <input type="text" name="purpose" id="grp-purpose" class="form-control" placeholder="OMZET / HOD / TEAM / PROMO">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Catatan</label>
            <textarea name="notes" id="grp-notes" class="form-control" rows="2"></textarea>
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

<!-- Modal Kirim Pesan ke Grup -->
<div class="modal fade wa-modal" id="modal-send-group" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex gap-3">
          <span class="wa-modal-icon"><i class="ri ri-send-plane-line"></i></span>
          <div>
            <h5 class="modal-title">Kirim Pesan ke <span id="send-group-name"></span></h5>
            <div class="small text-muted mt-1">Pesan teks bisa dikirim sendiri atau menjadi caption gambar.</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" enctype="multipart/form-data" action="<?= site_url('wa/group') ?>">
        <?php if ($mutationCsrf !== ''): ?>
        <input type="hidden" name="wa_template_group_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
        <?php endif; ?>
        <input type="hidden" name="action" value="send_group">
        <input type="hidden" name="id" id="send-group-id" value="">
        <div class="modal-body">
          <label class="form-label fw-semibold">Pesan</label>
          <textarea name="message" class="form-control" rows="6"
            placeholder="Ketik pesan di sini…"></textarea>
          <div class="form-text">Format markdown WA didukung: *bold*, _italic_, ~coret~. Jika mengirim gambar, pesan menjadi caption.</div>
          <div class="mt-3">
            <label class="form-label fw-semibold">Gambar (opsional)</label>
            <input type="file" name="media_image" id="group-media-image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="form-text">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.</div>
            <div id="group-media-preview" class="mt-2 d-none">
              <img src="" alt="Preview gambar" class="img-fluid rounded border" style="max-height:200px;">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="ri ri-send-plane-line me-1"></i>Kirim</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editGroup(grp) {
  document.getElementById('modal-group-title').textContent = 'Edit Grup';
  document.getElementById('grp-id').value = grp.id;
  document.getElementById('grp-key').value = grp.group_key;
  document.getElementById('grp-name').value = grp.group_name;
  document.getElementById('grp-jid').value = grp.group_jid || '';
  document.getElementById('grp-purpose').value = grp.purpose || '';
  document.getElementById('grp-notes').value = grp.notes || '';
  new bootstrap.Modal(document.getElementById('modal-group')).show();
}

function openSendModal(id, name) {
  document.getElementById('send-group-id').value = id;
  document.getElementById('send-group-name').textContent = name;
  new bootstrap.Modal(document.getElementById('modal-send-group')).show();
}

document.getElementById('group-media-image')?.addEventListener('change', function () {
  const wrap = document.getElementById('group-media-preview');
  const img = wrap?.querySelector('img');
  const file = this.files && this.files[0] ? this.files[0] : null;
  if (!wrap || !img || !file) {
    wrap?.classList.add('d-none');
    return;
  }
  img.src = URL.createObjectURL(file);
  wrap.classList.remove('d-none');
});

document.getElementById('modal-group')?.addEventListener('hidden.bs.modal', function () {
  document.getElementById('modal-group-title').textContent = 'Tambah Grup';
  this.querySelector('form').reset();
  document.getElementById('grp-id').value = 0;
});

document.getElementById('modal-send-group')?.addEventListener('hidden.bs.modal', function () {
  this.querySelector('form')?.reset();
  document.getElementById('group-media-preview')?.classList.add('d-none');
});
</script>
