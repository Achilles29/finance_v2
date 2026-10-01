<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];
$positionOptions = $position_options ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'position_id' => $filters['position_id'] ?? '',
        'as_of' => $filters['as_of'] ?? date('Y-m-d'),
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
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Preview THP'); ?></h4>
    <small class="text-muted">Read-only simulasi THP berdasarkan assignment payroll dan kontrak aktif.</small>
  </div>
  <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('payroll/preview-thp'); ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" placeholder="Kode/nama/divisi/jabatan" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="">Semua</option>
          <?php foreach ($divisionOptions as $o): ?>
            <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Jabatan</label>
        <select name="position_id" class="form-select">
          <option value="">Semua</option>
          <?php foreach ($positionOptions as $o): ?>
            <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['position_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">As Of Date</label>
        <input type="date" name="as_of" class="form-control" value="<?php echo html_escape((string)($filters['as_of'] ?? date('Y-m-d'))); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Per</label>
        <select name="per_page" class="form-select">
          <?php foreach ([10, 25, 50, 100] as $p): ?>
            <option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-primary">Filter</button>
      </div>
      <div class="col-12">
        <a class="btn btn-outline-secondary" href="<?php echo site_url('payroll/preview-thp'); ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Pegawai</th>
          <th>Divisi / Jabatan</th>
          <th>Status / Masa Kerja</th>
          <th>Sumber</th>
          <th class="text-end">Gaji Pokok</th>
          <th class="text-end">Tunj. Jabatan</th>
          <th class="text-end">Tunj. Objektif</th>
          <th class="text-end">THP Kontrak</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php else: ?>
        <?php $no = (($pg['page'] ?? 1) - 1) * ($pg['per_page'] ?? 25) + 1; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$no++; ?></td>
            <td>
              <div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div>
              <small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '-')); ?></small>
            </td>
            <td>
              <div><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></div>
              <small class="text-muted"><?php echo html_escape((string)($r['position_name'] ?? '-')); ?></small>
            </td>
            <td>
              <div><?php echo html_escape((string)($r['employment_status'] ?? '-')); ?></div>
              <small class="text-muted"><?php echo (int)($r['years_of_service'] ?? 0); ?> tahun</small>
            </td>
            <td>
              <span class="badge bg-label-primary"><?php echo html_escape((string)($r['source_type'] ?? '-')); ?></span><br>
              <small class="text-muted"><?php echo html_escape((string)($r['source_ref'] ?? '-')); ?></small>
              <?php if (trim((string)($r['reference_info'] ?? '')) !== ''): ?>
                <div class="small text-muted mt-1">Acuan: <?php echo html_escape((string)$r['reference_info']); ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end"><?php echo number_format((float)($r['basic_salary'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($r['position_allowance'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($r['objective_allowance'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format((float)($r['fixed_total'] ?? 0), 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
    <div class="btn-group">
      <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('payroll/preview-thp?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('payroll/preview-thp?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('payroll/preview-thp?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
