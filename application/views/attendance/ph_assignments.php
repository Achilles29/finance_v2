<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];
$employeeOptions = $employee_options ?? [];

$buildQuery = static function (array $overrides = []) use ($filters, $pg): string {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'is_eligible' => $filters['is_eligible'] ?? '',
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

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Assignment PH Pegawai'); ?></h4>
    <small class="text-muted">Atur pegawai yang berhak mendapatkan PH dan masa berlaku default per pegawai.</small>
  </div>
  <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/ph-assignments'); ?>" class="row g-2 align-items-end">
      <div class="col-md-4"><label class="form-label mb-1">Cari</label><input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Divisi/Jabatan"></div>
      <div class="col-md-3"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach ($divisionOptions as $opt): ?><option value="<?php echo (int)$opt['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$opt['value']) ? 'selected' : ''; ?>><?php echo html_escape($opt['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="is_eligible" class="form-select"><option value="">Semua</option><option value="1" <?php echo ((string)($filters['is_eligible'] ?? '') === '1') ? 'selected' : ''; ?>>Eligible</option><option value="0" <?php echo ((string)($filters['is_eligible'] ?? '') === '0') ? 'selected' : ''; ?>>Non-Eligible</option></select></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $pp): ?><option value="<?php echo $pp; ?>" <?php echo ((int)$pg['per_page']===$pp)?'selected':''; ?>><?php echo $pp; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-flex gap-2"><button type="submit" class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/ph-assignments'); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-3">Set Assignment PH Pegawai</h6>
    <form method="post" action="<?php echo site_url('attendance/ph-assignments/save?' . $buildQuery()); ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Pegawai</label>
        <select name="employee_id" class="form-select" required>
          <option value="">Pilih pegawai...</option>
          <?php foreach ($employeeOptions as $opt): ?>
            <option value="<?php echo (int)$opt['value']; ?>"><?php echo html_escape((string)$opt['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><label class="form-label mb-1">Efektif</label><input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
      <div class="col-md-2"><label class="form-label mb-1">Expired Override (bulan)</label><input type="number" min="0" name="expiry_months_override" class="form-control" placeholder="Opsional"></div>
      <div class="col-md-3"><label class="form-label mb-1">Catatan</label><input type="text" name="notes" class="form-control" placeholder="Opsional"></div>
      <div class="col-md-1 d-flex align-items-end">
        <div class="form-check mb-2">
          <input type="checkbox" class="form-check-input" id="is_eligible" name="is_eligible" value="1" checked>
          <label class="form-check-label" for="is_eligible">On</label>
        </div>
      </div>
      <div class="col-12"><button type="submit" class="btn btn-primary">Simpan Assignment</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Divisi / Jabatan</th>
          <th>Eligible</th>
          <th>Efektif</th>
          <th>Expired Override</th>
          <th class="text-end">Grant+Adjust</th>
          <th class="text-end">Use+Expire</th>
          <th class="text-end">Saldo</th>
          <th>Catatan</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data assignment PH.</td></tr>
        <?php else: foreach ($rows as $row): ?>
          <?php
            $inVal = (float)($row['grant_adjust_days'] ?? 0);
            $outVal = (float)($row['use_expire_days'] ?? 0);
            $balance = $inVal - $outVal;
          ?>
          <tr>
            <td>
              <div class="fw-semibold"><?php echo html_escape((string)($row['employee_name'] ?? '')); ?></div>
              <small class="text-muted"><?php echo html_escape((string)($row['employee_code'] ?? '')); ?></small>
            </td>
            <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?> / <?php echo html_escape((string)($row['position_name'] ?? '-')); ?></td>
            <td><?php if ((int)($row['is_eligible'] ?? 0) === 1): ?><span class="badge bg-success">Eligible</span><?php else: ?><span class="badge bg-secondary">Non-Eligible</span><?php endif; ?></td>
            <td><?php echo html_escape((string)($row['effective_date'] ?? '-')); ?></td>
            <td><?php echo ($row['expiry_months_override'] === null || $row['expiry_months_override'] === '') ? '-' : (int)$row['expiry_months_override'] . ' bln'; ?></td>
            <td class="text-end text-success"><?php echo number_format($inVal, 2, ',', '.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format($outVal, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold <?php echo ($balance >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($balance, 2, ',', '.'); ?></td>
            <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
            <td class="text-center">
              <div class="d-flex justify-content-center gap-1">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#phAssignEdit<?php echo (int)$row['employee_id']; ?>" aria-expanded="false">
                  Ubah
                </button>
                <?php if (!empty($row['assignment_id'])): ?>
                <form method="post" action="<?php echo site_url('attendance/ph-assignments/delete/' . (int)$row['assignment_id'] . '?' . $buildQuery()); ?>" onsubmit="return confirm('Hapus assignment PH pegawai ini?');">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <tr class="collapse" id="phAssignEdit<?php echo (int)$row['employee_id']; ?>">
            <td colspan="10" class="bg-light">
              <form method="post" action="<?php echo site_url('attendance/ph-assignments/save?' . $buildQuery()); ?>" class="row g-2 align-items-end">
                <input type="hidden" name="employee_id" value="<?php echo (int)$row['employee_id']; ?>">
                <div class="col-md-3">
                  <label class="form-label mb-1">Status Hak PH</label>
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="eligible_<?php echo (int)$row['employee_id']; ?>" name="is_eligible" value="1" <?php echo ((int)($row['is_eligible'] ?? 0) === 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="eligible_<?php echo (int)$row['employee_id']; ?>">Pegawai ini berhak PH</label>
                  </div>
                </div>
                <div class="col-md-2">
                  <label class="form-label mb-1">Efektif</label>
                  <input type="date" class="form-control" name="effective_date" value="<?php echo html_escape((string)($row['effective_date'] ?? date('Y-m-d'))); ?>" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label mb-1">Expired Override</label>
                  <input type="number" min="0" class="form-control" name="expiry_months_override" value="<?php echo html_escape((string)($row['expiry_months_override'] ?? '')); ?>" placeholder="bulan">
                </div>
                <div class="col-md-4">
                  <label class="form-label mb-1">Catatan</label>
                  <input type="text" class="form-control" name="notes" value="<?php echo html_escape((string)($row['notes'] ?? '')); ?>" placeholder="Opsional">
                </div>
                <div class="col-md-1">
                  <button type="submit" class="btn btn-sm btn-primary w-100">Simpan</button>
                </div>
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
      <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('attendance/ph-assignments?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/ph-assignments?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('attendance/ph-assignments?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
