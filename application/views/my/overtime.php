<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$statusOptions = $status_options ?? ['PENDING', 'APPROVED', 'REJECTED'];
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
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div><h4 class="mb-0"><?php echo html_escape($title ?? 'Lembur Saya'); ?></h4><small class="text-muted">Riwayat lembur berdasarkan entri lembur manual/approval.</small></div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/overtime'); ?>" class="d-flex gap-2">
    <select name="employee_id" class="form-select form-select-sm" style="min-width:260px"><option value="">Pilih Pegawai (Preview Superadmin)</option><?php foreach($employeeOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option><?php endforeach; ?></select>
    <button type="submit" class="btn btn-sm btn-primary">Buka</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">Data pegawai tidak ditemukan pada akun ini.</div>
<?php else: ?>
<div class="card mb-3"><div class="card-body"><form method="get" action="<?php echo site_url('my/overtime'); ?>" class="row g-2 align-items-end"><?php if ($selectedEmployeeId): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?><div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $s): ?><option value="<?php echo html_escape($s); ?>" <?php echo (($filters['status'] ?? '') === $s) ? 'selected' : ''; ?>><?php echo html_escape($s); ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div><div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div><div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="<?php echo site_url('my/overtime' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="btn btn-outline-secondary">Reset</a></div></form></div></div>

<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Tanggal</th><th>Jam</th><th>Standard</th><th class="text-end">Jam Lembur</th><th class="text-end">Tarif</th><th class="text-end">Total</th><th>Status</th><th>Catatan</th></tr></thead><tbody><?php if(empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada data lembur.</td></tr><?php else: foreach($rows as $r): ?><tr><td><?php echo html_escape((string)$r['overtime_date']); ?></td><td><?php echo html_escape((string)$r['start_time']); ?> - <?php echo html_escape((string)$r['end_time']); ?></td><td><?php echo html_escape((string)($r['overtime_standard_name'] ?? '-')); ?></td><td class="text-end"><?php echo number_format((float)($r['overtime_hours'] ?? 0), 2, ',', '.'); ?></td><td class="text-end"><?php echo number_format((float)($r['overtime_rate'] ?? 0), 2, ',', '.'); ?></td><td class="text-end fw-semibold"><?php echo number_format((float)($r['total_overtime_pay'] ?? 0), 2, ',', '.'); ?></td><td><span class="badge bg-<?php echo strtoupper((string)($r['status'] ?? 'PENDING')) === 'APPROVED' ? 'success' : (strtoupper((string)($r['status'] ?? 'PENDING')) === 'REJECTED' ? 'danger' : 'warning'); ?>"><?php echo html_escape((string)($r['status'] ?? 'PENDING')); ?></span></td><td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<?php endif; ?>
