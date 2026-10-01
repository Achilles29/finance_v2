<?php
$baseUrl = site_url('master/relation/extra-item-group');
$extraTabActive = 'extra-link';
?>

<?php $this->load->view('master/_extra_tabs', compact('extraTabActive')); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-links-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Pilih master extra, lalu checklist group extra yang boleh memakai extra tersebut.</small>
  </div>
  <a href="<?php echo site_url('master/extra'); ?>" class="btn btn-outline-secondary">Kembali ke Master Extra</a>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-8 mb-2">
        <label class="form-label mb-1">Pencarian</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Cari kode / nama extra...">
      </div>
      <div class="col-md-4 mb-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th width="60">No</th>
          <th>Kode</th>
          <th>Nama Extra</th>
          <th>Tipe</th>
          <th>Total Group</th>
          <th width="120">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">Belum ada data master extra.</td>
          </tr>
        <?php else: ?>
          <?php $no = 1; foreach ($rows as $r): ?>
            <tr>
              <td class="number-cell"><?php echo $no++; ?></td>
              <td class="text-cell"><?php echo html_escape((string)$r['extra_code']); ?></td>
              <td class="text-cell"><?php echo html_escape((string)$r['extra_name']); ?></td>
              <td><?php echo html_escape((string)($r['extra_type'] ?? '-')); ?></td>
              <td class="number-cell"><?php echo (int)($r['total_group'] ?? 0); ?></td>
              <td class="action-cell">
                <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Checklist Group" aria-label="Checklist Group" href="<?php echo site_url('master/relation/extra-item-group/' . (int)$r['id']); ?>"><i class="ri ri-checkbox-multiple-line"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
