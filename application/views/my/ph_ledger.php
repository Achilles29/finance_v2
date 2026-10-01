<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$filters = $filters ?? [];
$rows = $rows ?? [];
$summary = $summary ?? ['balance' => 0, 'grant_total' => 0, 'use_total' => 0, 'expire_total' => 0, 'adjust_total' => 0];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$txTypeOptions = $tx_type_options ?? ['GRANT', 'USE', 'EXPIRE', 'ADJUST', 'VOID'];
$expiredState = strtoupper((string)($filters['expired_state'] ?? 'ALL'));
$baseQuery = http_build_query(array_filter([
    'employee_id' => $selectedEmployeeId ?: null,
    'tx_type' => (string)($filters['tx_type'] ?? ''),
    'date_start' => (string)($filters['date_start'] ?? ''),
    'date_end' => (string)($filters['date_end'] ?? ''),
    'per_page' => (int)($pg['per_page'] ?? 25),
], static function ($v) {
    return $v !== null;
}));
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div><h4 class="mb-0"><?php echo html_escape($title ?? 'PH Saya'); ?></h4><small class="text-muted">Saldo PH dan histori grant/use/expire per tanggal.</small></div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/ph-ledger'); ?>" class="d-flex gap-2"><select name="employee_id" class="form-select form-select-sm" style="min-width:260px"><option value="">Pilih Pegawai (Preview Superadmin)</option><?php foreach($employeeOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-sm btn-primary">Buka</button></form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">Data pegawai tidak ditemukan pada akun ini.</div>
<?php else: ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted d-block">Saldo PH</small><h5 class="mb-0 text-primary"><?php echo number_format((float)$summary['balance'], 2, ',', '.'); ?></h5></div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted d-block">Total Grant</small><h5 class="mb-0 text-success"><?php echo number_format((float)$summary['grant_total'], 2, ',', '.'); ?></h5></div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted d-block">Total Use</small><h5 class="mb-0 text-danger"><?php echo number_format((float)$summary['use_total'], 2, ',', '.'); ?></h5></div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted d-block">Expire + Adjust</small><h5 class="mb-0"><?php echo number_format((float)$summary['expire_total'] + (float)$summary['adjust_total'], 2, ',', '.'); ?></h5></div></div></div>
</div>

<div class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-sm <?php echo $expiredState === 'ALL' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('my/ph-ledger' . ($baseQuery !== '' ? ('?' . $baseQuery . '&expired_state=ALL') : '?expired_state=ALL')); ?>">Semua Riwayat</a><a class="btn btn-sm <?php echo $expiredState === 'ACTIVE' ? 'btn-success' : 'btn-outline-success'; ?>" href="<?php echo site_url('my/ph-ledger' . ($baseQuery !== '' ? ('?' . $baseQuery . '&expired_state=ACTIVE') : '?expired_state=ACTIVE')); ?>">Grant Aktif</a><a class="btn btn-sm <?php echo $expiredState === 'EXPIRED' ? 'btn-danger' : 'btn-outline-danger'; ?>" href="<?php echo site_url('my/ph-ledger' . ($baseQuery !== '' ? ('?' . $baseQuery . '&expired_state=EXPIRED') : '?expired_state=EXPIRED')); ?>">Grant Expired</a></div><form method="get" action="<?php echo site_url('my/ph-ledger'); ?>" class="row g-2 align-items-end"><?php if ($selectedEmployeeId): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?><div class="col-md-2"><label class="form-label mb-1">Jenis TX</label><select name="tx_type" class="form-select"><option value="">Semua</option><?php foreach($txTypeOptions as $tx): ?><option value="<?php echo html_escape($tx); ?>" <?php echo (($filters['tx_type'] ?? '') === $tx) ? 'selected' : ''; ?>><?php echo html_escape($tx); ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label mb-1">Status Expired</label><select name="expired_state" class="form-select"><option value="ALL" <?php echo $expiredState === 'ALL' ? 'selected' : ''; ?>>Semua</option><option value="ACTIVE" <?php echo $expiredState === 'ACTIVE' ? 'selected' : ''; ?>>Grant Aktif</option><option value="EXPIRED" <?php echo $expiredState === 'EXPIRED' ? 'selected' : ''; ?>>Grant Expired</option></select></div><div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div><div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div><div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="<?php echo site_url('my/ph-ledger' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="btn btn-outline-secondary">Reset</a></div></form></div></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Tanggal</th><th>Jenis</th><th class="text-end">Qty</th><th class="text-end">Saldo Berjalan</th><th>Expired</th><th>Sumber</th><th>Catatan</th></tr></thead><tbody><?php if(empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada ledger PH.</td></tr><?php else: foreach($rows as $r): ?><tr><td><?php echo html_escape((string)($r['tx_date'] ?? '-')); ?></td><td><?php echo html_escape((string)($r['tx_type'] ?? '-')); ?></td><td class="text-end"><?php echo number_format((float)($r['qty_days'] ?? 0), 2, ',', '.'); ?></td><td class="text-end fw-semibold"><?php echo number_format((float)($r['running_balance'] ?? 0), 2, ',', '.'); ?></td><td><?php $exp=(string)($r['expired_at'] ?? ''); $isExpired=($exp!=='' && $exp < date('Y-m-d')); if($exp===''){ echo '-'; } else { echo html_escape($exp); if(strtoupper((string)($r['tx_type'] ?? ''))==='GRANT'){ echo '<br><small class="'.($isExpired?'text-danger':'text-success').'">'.($isExpired?'Expired':'Aktif').'</small>'; } } ?></td><td><?php echo html_escape((string)($r['ref_table'] ?? '-')); ?>#<?php echo html_escape((string)($r['ref_id'] ?? '-')); ?></td><td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<?php endif; ?>
