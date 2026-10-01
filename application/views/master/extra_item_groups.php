<?php
$mapped = array_fill_keys(array_map('intval', $mapped_group_ids ?? []), true);
$saveUrl = site_url('master/relation/extra-item-group/' . (int)$extra['id'] . '/save');
$baseUrl = site_url('master/relation/extra-item-group/' . (int)$extra['id']);
$extraTabActive = 'extra-link';
$checklistMutationCsrf = $master_relation_extra_group_checklist_mutation_csrf ?? null;
$hasActiveFilter = trim((string)($q ?? '')) !== '';
$canEditChecklist = !$hasActiveFilter
  && is_string($checklistMutationCsrf)
  && preg_match('/\A[0-9a-f]{64}\z/D', $checklistMutationCsrf) === 1;
?>

<?php $this->load->view('master/_extra_tabs', compact('extraTabActive')); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-checkbox-multiple-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">
      Extra: <?php echo html_escape((string)($extra['extra_name'] ?? '-')); ?>
      <?php if (!empty($extra['extra_code'])): ?>
        (<?php echo html_escape((string)$extra['extra_code']); ?>)
      <?php endif; ?>
    </small>
  </div>
  <a href="<?php echo site_url('master/relation/extra-item-group'); ?>" class="btn btn-outline-secondary">Kembali ke Hub Relasi Extra</a>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-8 mb-2">
        <label class="form-label mb-1">Pencarian Group</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Cari kode / nama group extra...">
      </div>
      <div class="col-md-4 mb-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php if ($hasActiveFilter): ?>
  <div class="alert alert-warning" role="alert">
    Hasil filter hanya dapat dilihat. Reset filter untuk mengubah checklist penuh dengan aman.
  </div>
<?php endif; ?>

<form method="post" action="<?php echo $saveUrl; ?>">
  <?php if ($canEditChecklist): ?>
    <input type="hidden" name="master_relation_extra_group_checklist_mutation_csrf" value="<?php echo html_escape($checklistMutationCsrf); ?>">
  <?php endif; ?>
  <div class="card">
    <div class="card-body border-bottom py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <strong><?php echo count($rows); ?></strong> group tampil.
      </div>
      <?php if ($canEditChecklist): ?>
      <div class="d-flex align-items-center gap-2">
        <label class="form-check mb-0">
          <input type="checkbox" class="form-check-input" id="check_all_groups">
          <span class="form-check-label">Pilih semua</span>
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Simpan Checklist</button>
      </div>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr>
            <th width="70">Pilih</th>
            <th>Kode</th>
            <th>Nama Group</th>
            <th>Divisi Produk</th>
            <th>Wajib</th>
            <th width="90">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">Tidak ada group extra sesuai filter.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php $checked = !empty($mapped[(int)$r['id']]); ?>
              <tr>
                <td>
                  <input
                    type="checkbox"
                    class="form-check-input group-map-checkbox"
                    name="group_ids[]"
                    value="<?php echo (int)$r['id']; ?>"
                    <?php echo $checked ? 'checked' : ''; ?>
                    <?php echo $canEditChecklist ? '' : 'disabled'; ?>
                  >
                </td>
                <td class="text-cell"><?php echo html_escape((string)$r['group_code']); ?></td>
                <td class="text-cell"><?php echo html_escape((string)$r['group_name']); ?></td>
                <td><?php echo html_escape((string)($r['product_division_name'] ?? '-')); ?></td>
                <td><?php echo !empty($r['is_required']) ? 'Ya' : 'Tidak'; ?></td>
                <td class="action-cell">
                  <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Lihat Checklist Produk Group" aria-label="Lihat Checklist Produk Group" href="<?php echo site_url('master/relation/extra-group/' . (int)$r['id']); ?>"><i class="ri ri-links-line"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($canEditChecklist): ?>
      <div class="card-footer d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">Simpan Checklist</button>
      </div>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
  var checkAll = document.getElementById('check_all_groups');
  if (!checkAll) return;
  var checks = Array.prototype.slice.call(document.querySelectorAll('.group-map-checkbox'));

  function syncHeaderState() {
    if (!checks.length) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
      return;
    }
    var checkedCount = checks.filter(function (c) { return c.checked; }).length;
    checkAll.checked = checkedCount === checks.length;
    checkAll.indeterminate = checkedCount > 0 && checkedCount < checks.length;
  }

  checkAll.addEventListener('change', function () {
    checks.forEach(function (c) { c.checked = checkAll.checked; });
    syncHeaderState();
  });

  checks.forEach(function (c) {
    c.addEventListener('change', syncHeaderState);
  });

  syncHeaderState();
})();
</script>
