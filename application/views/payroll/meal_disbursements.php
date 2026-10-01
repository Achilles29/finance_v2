<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$statusOptions = $status_options ?? ['DRAFT', 'POSTED', 'PAID', 'VOID'];
$accounts = $company_account_options ?? [];
$detailHeader = $detail_header ?? null;
$detailLines = $detail_lines ?? [];
$detailEmployeeRows = $detail_employee_rows ?? [];
$detailEmployeeDailyRows = $detail_employee_daily_rows ?? [];
$detailEmployeeId = (int)($detail_employee_id ?? 0);

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
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
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Pencairan Uang Makan'); ?></h4>
    <small class="text-muted">Hanya untuk mode uang makan <strong>Custom</strong>. Generate kandidat dari `att_daily`, validasi anti-duplikat, lalu posting paid. Mode Bulanan dibayarkan melalui payroll.</small>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><strong>Generate Batch Uang Makan Custom</strong></div>
      <div class="card-body">
        <form method="post" action="<?php echo site_url('payroll/meal-disbursements/generate'); ?>" class="row g-2">
          <div class="col-md-6"><label class="form-label mb-1">Periode Mulai</label><input type="date" name="period_start" class="form-control" required value="<?php echo date('Y-m-01'); ?>"></div>
          <div class="col-md-6"><label class="form-label mb-1">Periode Akhir</label><input type="date" name="period_end" class="form-control" required value="<?php echo date('Y-m-t'); ?>"></div>
          <div class="col-md-6"><label class="form-label mb-1">Tgl Pencairan</label><input type="date" name="disbursement_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
          <div class="col-md-6"><label class="form-label mb-1">Rekening</label><select name="company_account_id" class="form-select"><option value="">(Opsional)</option><?php foreach($accounts as $a): ?><option value="<?php echo (int)$a['value']; ?>"><?php echo html_escape((string)$a['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label mb-1">Catatan</label><textarea name="notes" class="form-control" rows="2" placeholder="Opsional"></textarea></div>
          <div class="col-12"><button type="submit" class="btn btn-primary" data-loading-label="Generating...">Generate Kandidat</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-body"><form method="get" action="<?php echo site_url('payroll/meal-disbursements'); ?>" class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No/notes"></div><div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $s): ?><option value="<?php echo html_escape($s); ?>" <?php echo (($filters['status'] ?? '') === $s) ? 'selected' : ''; ?>><?php echo html_escape($s); ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div><div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div><div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach ([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="<?php echo site_url('payroll/meal-disbursements'); ?>" class="btn btn-outline-secondary">Reset</a></div></form></div></div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th>No Batch</th><th>Periode</th><th>Tgl Cair</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Baris</th><th class="text-center">Aksi</th></tr></thead>
          <tbody>
          <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada batch pencairan uang makan.</td></tr><?php else: foreach($rows as $r): $status=strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
          <tr>
            <td><?php echo html_escape((string)$r['disbursement_no']); ?></td>
            <td><?php echo html_escape((string)$r['period_start']); ?> s/d <?php echo html_escape((string)$r['period_end']); ?></td>
            <td><?php echo html_escape((string)$r['disbursement_date']); ?></td>
            <td><span class="badge bg-<?php echo $status === 'PAID' ? 'success' : ($status === 'VOID' ? 'danger' : 'warning'); ?>"><?php echo html_escape($status); ?></span></td>
            <td class="text-end fw-semibold"><?php echo number_format((float)($r['total_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($r['paid_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo (int)($r['line_count'] ?? 0); ?></td>
            <td class="action-cell text-center">
              <a href="<?php echo site_url('payroll/meal-disbursements?' . $buildQuery(['detail_id' => (int)$r['id'], 'detail_employee_id' => ''])); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Detail"><i class="ri ri-eye-line"></i></a>
              <?php if ($status !== 'PAID' && $status !== 'VOID'): ?>
              <form method="post" action="<?php echo site_url('payroll/meal-disbursements/mark-paid/' . (int)$r['id']); ?>" class="d-inline">
                <button type="submit" class="btn btn-sm btn-outline-success action-icon-btn" data-bs-toggle="tooltip" title="Tandai Paid" data-loading-label="Posting..."><i class="ri ri-money-dollar-circle-line"></i></button>
              </form>
              <?php endif; ?>
              <?php if ($status !== 'VOID'): ?>
              <form method="post" action="<?php echo site_url('payroll/meal-disbursements/void/' . (int)$r['id']); ?>" class="d-inline" data-confirm="VOID batch ini?">
                <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Void" data-loading-label="Void..."><i class="ri ri-close-circle-line"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($detailHeader)): ?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Detail Batch <?php echo html_escape((string)$detailHeader['disbursement_no']); ?></strong>
    <span class="text-muted">Total: <?php echo number_format((float)($detailHeader['total_amount'] ?? 0), 2, ',', '.'); ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Pegawai</th><th>Divisi</th><th class="text-end">Hari</th><th class="text-end">Nominal</th><th class="text-end">Paid</th><th class="text-center">Aksi</th></tr></thead>
      <tbody>
      <?php if (empty($detailEmployeeRows)): ?><tr><td colspan="6" class="text-center text-muted py-3">Tidak ada detail pegawai.</td></tr><?php else: foreach($detailEmployeeRows as $l): ?>
        <tr>
          <td><?php echo html_escape((string)$l['employee_name']); ?><div class="small text-muted"><?php echo html_escape((string)$l['employee_code']); ?></div></td>
          <td><?php echo html_escape((string)($l['division_name'] ?? '-')); ?></td>
          <td class="text-end"><?php echo (int)($l['day_count'] ?? 0); ?></td>
          <td class="text-end"><?php echo number_format((float)($l['meal_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($l['paid_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-center">
            <a href="<?php echo site_url('payroll/meal-disbursements?' . $buildQuery(['detail_id' => (int)$detailHeader['id'], 'detail_employee_id' => (int)$l['employee_id']])); ?>" class="btn btn-sm btn-outline-secondary action-icon-btn" data-bs-toggle="tooltip" title="Detail Harian"><i class="ri ri-list-check-2"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($detailEmployeeId > 0): ?>
<div class="card mt-3">
  <div class="card-header"><strong>Detail Harian Pegawai</strong></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Tanggal</th><th class="text-end">Nominal</th><th>Status</th><th>Ref</th><th>Paid At</th></tr></thead>
      <tbody>
      <?php if (empty($detailEmployeeDailyRows)): ?><tr><td colspan="5" class="text-center text-muted py-3">Tidak ada detail harian.</td></tr><?php else: foreach($detailEmployeeDailyRows as $d): ?>
        <tr>
          <td><?php echo html_escape((string)$d['attendance_date']); ?></td>
          <td class="text-end"><?php echo number_format((float)($d['meal_amount'] ?? 0), 2, ',', '.'); ?></td>
          <td><?php echo html_escape((string)($d['transfer_status'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($d['transfer_ref_no'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($d['paid_at'] ?? '-')); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
