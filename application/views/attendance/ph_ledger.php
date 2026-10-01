<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$summary = $summary ?? ['grant' => 0, 'use' => 0, 'expire' => 0, 'adjust' => 0, 'balance' => 0];
$employeeOptions = $employee_options ?? [];
$txTypeOptions = $tx_type_options ?? ['GRANT', 'USE', 'EXPIRE', 'ADJUST', 'VOID'];
$manualTxTypeOptions = $manual_tx_type_options ?? ['GRANT', 'USE', 'EXPIRE', 'ADJUST'];
$editRow = $edit_row ?? null;
$currentMonthStart = date('Y-m-01');
$syncDateStart = max($currentMonthStart, (string)($filters['date_start'] ?? $currentMonthStart));
$syncDateEnd = (string)($filters['date_end'] ?? date('Y-m-t'));
if ($syncDateEnd < $syncDateStart) {
    $syncDateEnd = date('Y-m-t');
}

$buildQuery = static function (array $overrides = []) use ($filters, $pg): string {
    $base = [
        'q' => $filters['q'] ?? '',
        'employee_id' => $filters['employee_id'] ?? '',
        'tx_type' => $filters['tx_type'] ?? '',
        'expired_state' => $filters['expired_state'] ?? 'ALL',
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

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Ledger & Log PH'); ?></h4>
    <small class="text-muted">Mutasi PH dari grant absensi, pemakaian, expire, dan penyesuaian manual.</small>
  </div>
  <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
</div>

<div class="alert alert-warning py-2 small mb-3">
  <strong>Catatan saldo:</strong> angka ledger di bawah adalah riwayat yang pernah tercatat. Saat admin menjadwalkan PH baru, sistem memakai saldo efektif: grant otomatis lama yang keliru dari shift PH tidak dipakai, sedangkan hak dari presensi libur nasional yang valid tetapi belum tercatat tetap diperhitungkan untuk mencegah jadwal salah. Riwayat lama tidak diubah otomatis.
</div>

<div class="row g-3 mb-3">
  <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Grant</small><div class="fw-semibold text-success"><?php echo number_format((float)$summary['grant'], 2, ',', '.'); ?></div></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Use</small><div class="fw-semibold text-danger"><?php echo number_format((float)$summary['use'], 2, ',', '.'); ?></div></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Expire</small><div class="fw-semibold text-danger"><?php echo number_format((float)$summary['expire'], 2, ',', '.'); ?></div></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Adjust</small><div class="fw-semibold text-info"><?php echo number_format((float)$summary['adjust'], 2, ',', '.'); ?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body py-2"><small class="text-muted d-block">Saldo</small><div class="fw-bold <?php echo ((float)$summary['balance'] >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format((float)$summary['balance'], 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <a class="btn btn-sm <?php echo (($filters['expired_state'] ?? 'ALL') === 'ALL') ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/ph-ledger?' . $buildQuery(['expired_state' => 'ALL', 'page' => 1])); ?>">Semua Riwayat</a>
      <a class="btn btn-sm <?php echo (($filters['expired_state'] ?? 'ALL') === 'ACTIVE') ? 'btn-success' : 'btn-outline-success'; ?>" href="<?php echo site_url('attendance/ph-ledger?' . $buildQuery(['expired_state' => 'ACTIVE', 'page' => 1])); ?>">Grant Aktif</a>
      <a class="btn btn-sm <?php echo (($filters['expired_state'] ?? 'ALL') === 'EXPIRED') ? 'btn-danger' : 'btn-outline-danger'; ?>" href="<?php echo site_url('attendance/ph-ledger?' . $buildQuery(['expired_state' => 'EXPIRED', 'page' => 1])); ?>">Grant Expired</a>
    </div>
    <form method="get" action="<?php echo site_url('attendance/ph-ledger'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Divisi/Jabatan/Catatan"></div>
      <div class="col-md-3"><label class="form-label mb-1">Pegawai</label><select name="employee_id" class="form-select"><option value="">Semua pegawai</option><?php foreach ($employeeOptions as $opt): ?><option value="<?php echo (int)$opt['value']; ?>" <?php echo ((int)($filters['employee_id'] ?? 0) === (int)$opt['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$opt['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Jenis</label><select name="tx_type" class="form-select"><option value="">Semua</option><?php foreach ($txTypeOptions as $type): ?><option value="<?php echo html_escape($type); ?>" <?php echo ((string)($filters['tx_type'] ?? '') === $type) ? 'selected' : ''; ?>><?php echo html_escape($type); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Status Expired</label><select name="expired_state" class="form-select"><option value="ALL" <?php echo (($filters['expired_state'] ?? 'ALL') === 'ALL') ? 'selected' : ''; ?>>Semua</option><option value="ACTIVE" <?php echo (($filters['expired_state'] ?? '') === 'ACTIVE') ? 'selected' : ''; ?>>Grant Aktif</option><option value="EXPIRED" <?php echo (($filters['expired_state'] ?? '') === 'EXPIRED') ? 'selected' : ''; ?>>Grant Expired</option></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" class="form-control" name="date_start" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" class="form-control" name="date_end" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $pp): ?><option value="<?php echo $pp; ?>" <?php echo ((int)$pg['per_page']===$pp)?'selected':''; ?>><?php echo $pp; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-12 d-flex gap-2"><button type="submit" class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/ph-ledger'); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3"><?php echo !empty($editRow) ? 'Edit Mutasi PH Manual' : 'Input Mutasi PH Manual'; ?></h6>
        <form method="post" action="<?php echo !empty($editRow) ? site_url('attendance/ph-ledger/update/' . (int)$editRow['id']) : site_url('attendance/ph-ledger/store'); ?>" class="row g-2 align-items-end">
          <div class="col-md-5"><label class="form-label mb-1">Pegawai</label><select class="form-select" name="employee_id" required><option value="">Pilih pegawai...</option><?php foreach ($employeeOptions as $opt): ?><option value="<?php echo (int)$opt['value']; ?>" <?php echo (!empty($editRow) && (int)$editRow['employee_id'] === (int)$opt['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$opt['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Tanggal</label><input type="date" name="tx_date" class="form-control" value="<?php echo html_escape((string)($editRow['tx_date'] ?? date('Y-m-d'))); ?>" required></div>
          <div class="col-md-2"><label class="form-label mb-1">Jenis</label><select name="tx_type" class="form-select" required><?php foreach ($manualTxTypeOptions as $type): ?><option value="<?php echo html_escape($type); ?>" <?php echo (!empty($editRow) && strtoupper((string)($editRow['tx_type'] ?? '')) === $type) ? 'selected' : ''; ?>><?php echo html_escape($type); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Qty Hari</label><input type="number" min="0.01" step="0.01" name="qty_days" class="form-control" value="<?php echo html_escape((string)($editRow['qty_days'] ?? '1')); ?>" required></div>
          <div class="col-md-12"><label class="form-label mb-1">Catatan</label><input type="text" name="notes" class="form-control" value="<?php echo html_escape((string)($editRow['notes'] ?? '')); ?>" placeholder="Keterangan mutasi manual"></div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo !empty($editRow) ? 'Update Mutasi' : 'Simpan Mutasi'; ?></button>
            <?php if (!empty($editRow)): ?><a href="<?php echo site_url('attendance/ph-ledger'); ?>" class="btn btn-outline-secondary">Batal Edit</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Sinkron Grant PH Bulan Berjalan</h6>
        <form method="post" action="<?php echo site_url('attendance/ph-ledger/sync-grants'); ?>" class="row g-2 align-items-end">
          <div class="col-md-6"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" min="<?php echo html_escape($currentMonthStart); ?>" class="form-control" value="<?php echo html_escape($syncDateStart); ?>" required></div>
          <div class="col-md-6"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" min="<?php echo html_escape($currentMonthStart); ?>" class="form-control" value="<?php echo html_escape($syncDateEnd); ?>" required></div>
          <div class="col-12"><button type="submit" class="btn btn-warning">Sinkron Grant PH</button></div>
        </form>
        <div class="form-text mt-2">Hanya untuk bulan berjalan. Riwayat bulan lalu dibaca melalui Audit PH terlebih dahulu agar payroll lama tidak berubah tanpa persetujuan.</div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Pegawai</th>
          <th>Divisi/Jabatan</th>
          <th>Jenis</th>
          <th class="text-end">Qty</th>
          <th>Ref</th>
          <th>Berlaku s.d.</th>
          <th>Mode</th>
          <th>Catatan</th>
          <th>Dibuat Oleh</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">Belum ada mutasi PH.</td></tr>
        <?php else: foreach ($rows as $row): ?>
          <tr>
            <td><?php echo html_escape((string)($row['tx_date'] ?? '-')); ?></td>
            <td><div class="fw-semibold"><?php echo html_escape((string)($row['employee_name'] ?? '')); ?></div><small class="text-muted"><?php echo html_escape((string)($row['employee_code'] ?? '')); ?></small></td>
            <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?> / <?php echo html_escape((string)($row['position_name'] ?? '-')); ?></td>
            <td>
              <?php $type = strtoupper((string)($row['tx_type'] ?? '')); ?>
              <?php if ($type === 'GRANT'): ?><span class="badge bg-success">GRANT</span>
              <?php elseif ($type === 'USE'): ?><span class="badge bg-primary">USE</span>
              <?php elseif ($type === 'EXPIRE'): ?><span class="badge bg-danger">EXPIRE</span>
              <?php elseif ($type === 'VOID'): ?><span class="badge bg-secondary">VOID</span>
              <?php else: ?><span class="badge bg-info">ADJUST</span><?php endif; ?>
            </td>
            <td class="text-end fw-semibold"><?php echo number_format((float)($row['qty_days'] ?? 0), 2, ',', '.'); ?></td>
            <td>
              <?php
                $refTable = (string)($row['ref_table'] ?? '');
                $refId = (int)($row['ref_id'] ?? 0);
              ?>
              <?php echo $refTable !== '' ? html_escape($refTable . ($refId > 0 ? ('#' . $refId) : '')) : '-'; ?>
            </td>
            <td>
              <?php
                $exp = trim((string)($row['expired_at'] ?? ''));
                $isExpired = ($exp !== '' && $exp < date('Y-m-d'));
              ?>
              <?php if ($exp === ''): ?>
                <span class="text-muted">-</span>
              <?php else: ?>
                <div><?php echo html_escape($exp); ?></div>
                <?php if (strtoupper((string)($row['tx_type'] ?? '')) === 'GRANT'): ?>
                  <small class="<?php echo $isExpired ? 'text-danger' : 'text-success'; ?>"><?php echo $isExpired ? 'Expired' : 'Aktif'; ?></small>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?php echo html_escape((string)($row['entry_mode'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)($row['created_by_username'] ?? '-')); ?></td>
            <td class="text-center">
              <?php if (strtoupper((string)($row['entry_mode'] ?? 'AUTO')) === 'MANUAL'): ?>
                <div class="d-inline-flex gap-1">
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('attendance/ph-ledger?' . $buildQuery(['edit_id' => (int)$row['id'], 'page' => $pg['page']])); ?>">Edit</a>
                  <form method="post" action="<?php echo site_url('attendance/ph-ledger/delete/' . (int)$row['id']); ?>" onsubmit="return confirm('Hapus mutasi PH manual ini?');">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                  </form>
                </div>
              <?php else: ?>
                <span class="text-muted small">Auto</span>
              <?php endif; ?>
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
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('attendance/ph-ledger?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/ph-ledger?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('attendance/ph-ledger?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
