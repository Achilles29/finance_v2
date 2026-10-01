<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];
$employeeOptions = $employee_options ?? [];
$statusOptions = $status_options ?? ['PENDING', 'APPROVED', 'REJECTED'];
$kindOptions = $kind_options ?? ['ADDITION', 'DEDUCTION'];
$editRow = $edit_row ?? null;
$isEdit = !empty($editRow);

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'employee_id' => $filters['employee_id'] ?? '',
        'status' => $filters['status'] ?? '',
        'adjustment_kind' => $filters['adjustment_kind'] ?? '',
        'date_start' => $filters['date_start'] ?? '',
        'date_end' => $filters['date_end'] ?? '',
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }
    $items = [1];
    $start = max(2, $page - 1);
    $end = min($totalPages - 1, $page + 1);
    if ($start > 2) {
        $items[] = '...';
    }
    for ($i = $start; $i <= $end; $i++) {
        $items[] = $i;
    }
    if ($end < $totalPages - 1) {
        $items[] = '...';
    }
    $items[] = $totalPages;
    return $items;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Penyesuaian Gaji Manual'); ?></h4>
    <small class="text-muted">Tambahan / pengurangan manual bisa untuk bonus, denda, koreksi, dan lainnya.</small>
  </div>
  <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><strong><?php echo $isEdit ? 'Edit Penyesuaian' : 'Tambah Penyesuaian'; ?></strong></div>
      <div class="card-body">
        <form method="post" action="<?php echo $isEdit ? site_url('payroll/manual-adjustments/update/' . (int)$editRow['id']) : site_url('payroll/manual-adjustments/store'); ?>" class="row g-2">
          <div class="col-12">
            <label class="form-label mb-1">Pegawai</label>
            <select name="employee_id" class="form-select" required>
              <option value="">Pilih pegawai</option>
              <?php foreach ($employeeOptions as $o): ?>
              <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($editRow['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Tanggal</label>
            <input type="date" name="adjustment_date" class="form-control" required value="<?php echo html_escape((string)($editRow['adjustment_date'] ?? date('Y-m-d'))); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Jenis</label>
            <select name="adjustment_kind" class="form-select" required>
              <?php foreach ($kindOptions as $k): ?>
              <option value="<?php echo html_escape((string)$k); ?>" <?php echo (strtoupper((string)($editRow['adjustment_kind'] ?? 'ADDITION')) === $k) ? 'selected' : ''; ?>><?php echo $k === 'ADDITION' ? 'Tambahan' : 'Pengurangan'; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Nama Komponen Manual</label>
            <input type="text" name="adjustment_name" class="form-control" required placeholder="Contoh: Bonus Target / Denda Keterlambatan" value="<?php echo html_escape((string)($editRow['adjustment_name'] ?? '')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Nominal</label>
            <input type="number" step="0.01" min="0" name="amount" class="form-control" required value="<?php echo html_escape((string)($editRow['amount'] ?? '')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select">
              <?php foreach ($statusOptions as $s): ?>
              <option value="<?php echo html_escape((string)$s); ?>" <?php echo (strtoupper((string)($editRow['status'] ?? 'APPROVED')) === $s) ? 'selected' : ''; ?>><?php echo html_escape((string)$s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Opsional"><?php echo html_escape((string)($editRow['notes'] ?? '')); ?></textarea>
          </div>
          <div class="col-12 d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary" data-loading-label="Menyimpan...">
              <?php echo $isEdit ? 'Update Penyesuaian' : 'Simpan Penyesuaian'; ?>
            </button>
            <?php if ($isEdit): ?>
            <a href="<?php echo site_url('payroll/manual-adjustments'); ?>" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-body">
        <form method="get" action="<?php echo site_url('payroll/manual-adjustments'); ?>" class="row g-2 align-items-end">
          <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Komponen"></div>
          <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label mb-1">Pegawai</label><select name="employee_id" class="form-select"><option value="">Semua</option><?php foreach($employeeOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['employee_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Jenis</label><select name="adjustment_kind" class="form-select"><option value="">Semua</option><?php foreach($kindOptions as $k): ?><option value="<?php echo html_escape((string)$k); ?>" <?php echo (($filters['adjustment_kind'] ?? '')===$k)?'selected':''; ?>><?php echo $k === 'ADDITION' ? 'Tambahan' : 'Pengurangan'; ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $s): ?><option value="<?php echo html_escape((string)$s); ?>" <?php echo (($filters['status'] ?? '')===$s)?'selected':''; ?>><?php echo html_escape((string)$s); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
          <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
          <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="<?php echo site_url('payroll/manual-adjustments'); ?>" class="btn btn-outline-secondary">Reset</a></div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Pegawai</th>
              <th>Komponen</th>
              <th>Jenis</th>
              <th class="text-end">Nominal</th>
              <th>Status</th>
              <th>Catatan</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada penyesuaian manual.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <?php
                $status = strtoupper((string)($r['status'] ?? 'APPROVED'));
                $statusClass = $status === 'APPROVED' ? 'success' : ($status === 'REJECTED' ? 'danger' : 'warning');
                $kind = strtoupper((string)($r['adjustment_kind'] ?? 'ADDITION'));
                $kindClass = $kind === 'DEDUCTION' ? 'text-danger' : 'text-success';
              ?>
              <tr>
                <td><?php echo html_escape((string)($r['adjustment_date'] ?? '-')); ?></td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></small>
                </td>
                <td><?php echo html_escape((string)($r['adjustment_name'] ?? '-')); ?></td>
                <td><span class="<?php echo $kindClass; ?> fw-semibold"><?php echo $kind === 'DEDUCTION' ? 'Pengurangan' : 'Tambahan'; ?></span></td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($r['amount'] ?? 0), 2, ',', '.'); ?></td>
                <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo html_escape($status); ?></span></td>
                <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
                <td class="action-cell text-center">
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit" href="<?php echo site_url('payroll/manual-adjustments?' . $buildQuery(['edit_id' => (int)$r['id']])); ?>"><i class="ri ri-edit-line"></i></a>
                  <form method="post" action="<?php echo site_url('payroll/manual-adjustments/delete/' . (int)$r['id']); ?>" class="d-inline" data-confirm="Hapus data penyesuaian ini?">
                    <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" data-loading-label="Menghapus..."><i class="ri ri-delete-bin-line"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pg['total_pages'] ?? 1) > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
        <div class="btn-group">
          <?php $prev=max(1,(int)$pg['page']-1); $next=min((int)$pg['total_pages'],(int)$pg['page']+1); ?>
          <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('payroll/manual-adjustments?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
          <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '...'): ?>
              <span class="btn btn-sm btn-outline-secondary disabled">...</span>
            <?php else: ?>
              <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('payroll/manual-adjustments?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('payroll/manual-adjustments?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
