<?php
/**
 * users/permissions.php — Override izin per user
 */
?>
<style>
  .user-perm .perm-title {
    font-size: 1.45rem;
    letter-spacing: 0.01em;
  }
  .user-perm .module-title {
    font-weight: 700;
    letter-spacing: 0.06em;
  }
  .user-perm .perm-cell-icon {
    font-size: 1rem;
  }
  .user-perm .perm-actions {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
  }
  .user-perm .perm-actions .btn {
    min-width: 68px;
    padding: 0.24rem 0.45rem;
    font-size: 0.75rem;
  }
  .user-perm .code {
    font-size: 0.72rem;
    color: #6b7280;
  }
</style>

<div class="user-perm">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary">
      <i class="ri ri-arrow-left-line me-1"></i>Kembali
    </a>
    <h5 class="fw-bold mb-0 perm-title">Override Izin: <span class="text-primary"><?= htmlspecialchars($user['username']) ?></span></h5>
  </div>

  <div class="alert alert-info py-2 small mb-3">
    <i class="ri ri-information-line me-1"></i>
    Role aktif:
    <?php if (empty($user_roles)): ?>
      <em>tidak punya role</em>
    <?php else: ?>
      <?php foreach ($user_roles as $r): ?>
        <span class="badge bg-info text-dark me-1"><?= htmlspecialchars($r['role_name']) ?></span>
      <?php endforeach; ?>
    <?php endif; ?>
    <br>
    <span class="text-muted">`GRANT` menambah izin di luar role. `REVOKE` mencabut izin dari role.</span>
  </div>

  <?php if (empty($pages_by_module)): ?>
  <div class="alert alert-warning">Belum ada halaman terdaftar di `sys_page`.</div>
  <?php else: ?>
  <?php foreach ($pages_by_module as $module => $pages): ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light module-title text-uppercase small py-2">
      <?= htmlspecialchars($module) ?>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Halaman</th>
              <th class="text-center" style="width:96px;">Tipe</th>
              <th class="text-center" style="width:60px;">V</th>
              <th class="text-center" style="width:60px;">C</th>
              <th class="text-center" style="width:60px;">E</th>
              <th class="text-center" style="width:60px;">D</th>
              <th class="text-center" style="width:60px;">X</th>
              <th style="width:180px;">Alasan</th>
              <th style="width:170px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pages as $pg): ?>
            <?php $ov = $overrides[$pg['id']] ?? null; ?>
            <tr>
              <td>
                <div class="fw-semibold small"><?= htmlspecialchars($pg['page_name']) ?></div>
                <div class="code"><?= htmlspecialchars($pg['page_code']) ?></div>
              </td>
              <td class="text-center">
                <?php if ($ov): ?>
                  <span class="badge <?= $ov['override_type'] === 'GRANT' ? 'bg-success' : 'bg-danger' ?>">
                    <?= htmlspecialchars($ov['override_type']) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <?php foreach (['can_view','can_create','can_edit','can_delete','can_export'] as $flag): ?>
              <td class="text-center">
                <?php if ($ov && (int)$ov[$flag] === 1): ?>
                  <i class="ri ri-checkbox-circle-line text-success perm-cell-icon"></i>
                <?php elseif ($ov): ?>
                  <i class="ri ri-close-circle-line text-danger perm-cell-icon"></i>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <?php endforeach; ?>
              <td class="text-muted small"><?= htmlspecialchars($ov['reason'] ?? '') ?></td>
              <td>
                <div class="perm-actions">
                  <button class="btn btn-outline-primary btn-set-override"
                          data-page-id="<?= (int)$pg['id'] ?>"
                          data-page-name="<?= htmlspecialchars($pg['page_name']) ?>"
                          data-ov='<?= $ov ? htmlspecialchars(json_encode($ov), ENT_QUOTES, 'UTF-8') : 'null' ?>'
                          title="Set Override">
                    <i class="ri ri-edit-line me-1"></i>Set
                  </button>
                  <?php if ($ov): ?>
                  <a href="#" class="btn btn-outline-danger btn-del-override"
                     data-page-id="<?= (int)$pg['id'] ?>"
                     data-page-name="<?= htmlspecialchars($pg['page_name']) ?>"
                     title="Hapus Override">
                    <i class="ri ri-delete-bin-line me-1"></i>Hapus
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="modal fade" id="modalOverride" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">Set Override Izin</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?= form_open('users/save_override/' . (int)$user['id'], ['id' => 'form-override']) ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="page_id" id="ov_page_id">
      <div class="modal-body">
        <p class="fw-semibold mb-2" id="ov_page_name"></p>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Tipe Override</label>
          <div class="d-flex gap-3 flex-wrap">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="override_type" id="ov_grant" value="GRANT" checked>
              <label class="form-check-label" for="ov_grant"><span class="text-success fw-semibold">GRANT</span> — tambah izin</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="override_type" id="ov_revoke" value="REVOKE">
              <label class="form-check-label" for="ov_revoke"><span class="text-danger fw-semibold">REVOKE</span> — cabut izin</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Aksi yang di-override</label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach (['can_view'=>'View','can_create'=>'Create','can_edit'=>'Edit','can_delete'=>'Delete','can_export'=>'Export'] as $k => $lbl): ?>
            <div class="form-check form-switch">
              <input class="form-check-input ov-flag" type="checkbox" name="<?= $k ?>" id="ov_<?= $k ?>" role="switch">
              <label class="form-check-label" for="ov_<?= $k ?>"><?= $lbl ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Alasan (opsional)</label>
          <input type="text" name="reason" id="ov_reason" class="form-control" maxlength="255" placeholder="Mis: akses sementara untuk tugas tertentu">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="ri ri-save-line me-1"></i>Simpan</button>
      </div>
      <?= form_close() ?>
    </div>
  </div>
</div>

<?= form_open('users/save_override/' . (int)$user['id'], ['id' => 'form-del-override']) ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="override_type" value="GRANT">
<input type="hidden" name="page_id" id="del_page_id">
<?= form_close() ?>

<script>
$(function(){
  const modalEl = document.getElementById('modalOverride');
  let modalInstance = null;
  if (window.bootstrap && bootstrap.Modal && modalEl) {
    modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  function normalizeOverride(raw) {
    if (!raw || raw === 'null') return null;
    if (typeof raw === 'object') return raw;
    if (typeof raw === 'string') {
      try {
        return JSON.parse(raw);
      } catch (e) {
        return null;
      }
    }
    return null;
  }

  $('.btn-set-override').on('click', function(){
    const $btn = $(this);
    const pageId = $(this).data('page-id');
    const pageName = $(this).data('page-name');
    const ov = normalizeOverride($btn.attr('data-ov') || $(this).data('ov'));

    $('#ov_page_id').val(pageId);
    $('#ov_page_name').text(pageName);
    $('input[name=override_type][value=GRANT]').prop('checked', true);
    $('.ov-flag').prop('checked', false);
    $('#ov_reason').val('');

    if (ov && ov !== 'null') {
      $('input[name=override_type][value=' + ov.override_type + ']').prop('checked', true);
      $('#ov_can_view').prop('checked', ov.can_view == 1);
      $('#ov_can_create').prop('checked', ov.can_create == 1);
      $('#ov_can_edit').prop('checked', ov.can_edit == 1);
      $('#ov_can_delete').prop('checked', ov.can_delete == 1);
      $('#ov_can_export').prop('checked', ov.can_export == 1);
      $('#ov_reason').val(ov.reason || '');
    }

    if (modalInstance) {
      modalInstance.show();
    } else if (modalEl) {
      // Fallback sederhana jika bootstrap object tidak terdeteksi
      modalEl.style.display = 'block';
      modalEl.classList.add('show');
      modalEl.removeAttribute('aria-hidden');
      modalEl.setAttribute('aria-modal', 'true');
      modalEl.setAttribute('role', 'dialog');
    }
  });

  $('.btn-del-override').on('click', function(e){
    e.preventDefault();
    const pageName = $(this).data('page-name');
    if (!confirm('Hapus override untuk "' + pageName + '"?')) return;
    $('#del_page_id').val($(this).data('page-id'));
    $('#form-del-override').submit();
  });
});
</script>
