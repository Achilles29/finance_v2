<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$filters = $filters ?? [];
$rows = $rows ?? [];
$summary = $summary ?? ['eligible_total' => 0, 'paid_total' => 0, 'pending_total' => 0];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$statusOptions = $status_options ?? ['UNPAID', 'PENDING', 'PAID', 'FAILED', 'VOID'];

$buildQuery = static function ($overrides = []) use ($filters, $pg, $selectedEmployeeId) {
    $base = [
        'employee_id' => $selectedEmployeeId ?: '',
        'status' => $filters['status'] ?? '',
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
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Uang Makan Saya'); ?></h4>
    <small class="text-muted">Rekap hak uang makan harian dan status pencairannya.</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/meal-ledger'); ?>" class="d-flex gap-2">
    <select name="employee_id" class="form-select form-select-sm" style="min-width:260px">
      <option value="">Pilih Pegawai (Preview Superadmin)</option>
      <?php foreach ($employeeOptions as $o): ?>
      <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>>
        <?php echo html_escape((string)$o['label']); ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-primary">Buka</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">Data pegawai tidak ditemukan pada akun ini.</div>
<?php else: ?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted d-block">Hak Uang Makan</small><h5 class="mb-0"><?php echo number_format((float)$summary['eligible_total'], 2, ',', '.'); ?></h5></div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted d-block">Sudah Dibayar</small><h5 class="mb-0 text-success"><?php echo number_format((float)$summary['paid_total'], 2, ',', '.'); ?></h5></div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted d-block">Belum Dibayar / Proses</small><h5 class="mb-0 text-warning"><?php echo number_format((float)$summary['pending_total'], 2, ',', '.'); ?></h5></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('my/meal-ledger'); ?>" class="row g-2 align-items-end">
      <?php if ($selectedEmployeeId): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $s): ?><option value="<?php echo html_escape($s); ?>" <?php echo (($filters['status'] ?? '') === $s) ? 'selected' : ''; ?>><?php echo html_escape($s); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach ([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="<?php echo site_url('my/meal-ledger' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="btn btn-outline-secondary">Reset</a></div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>Tanggal</th><th>Status Hadir</th><th class="text-end">Hak Uang Makan</th><th>Status Pencairan</th><th>No Pencairan</th><th>Tgl Transfer</th><th>Ref Transfer</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data uang makan.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php
          $status = (int)($r['disbursement_line_id'] ?? 0) > 0 ? strtoupper((string)($r['transfer_status'] ?? 'PENDING')) : 'UNPAID';
          $cls = $status === 'PAID' ? 'success' : ($status === 'UNPAID' ? 'secondary' : ($status === 'FAILED' ? 'danger' : 'warning'));
        ?>
      <tr>
        <td><?php echo html_escape((string)$r['attendance_date']); ?></td>
        <td><?php echo html_escape((string)($r['attendance_status'] ?? '-')); ?></td>
        <td class="text-end fw-semibold"><?php echo number_format((float)($r['meal_amount'] ?? 0), 2, ',', '.'); ?></td>
        <td><span class="badge bg-<?php echo $cls; ?>"><?php echo html_escape($status); ?></span></td>
        <td><?php echo html_escape((string)($r['disbursement_no'] ?? '-')); ?></td>
        <td><?php echo html_escape((string)($r['paid_at'] ?? '-')); ?></td>
        <td><?php echo html_escape((string)($r['transfer_ref_no'] ?? '-')); ?></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?> (Total <?php echo (int)$pg['total']; ?>)</small>
    <div class="btn-group">
      <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('my/meal-ledger?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('my/meal-ledger?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('my/meal-ledger?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
