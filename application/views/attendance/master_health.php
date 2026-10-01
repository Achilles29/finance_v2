<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$summary = $summary ?? [];
$issueOptions = $issue_options ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'issue_type' => $filters['issue_type'] ?? '',
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
  <h4 class="mb-0"><?php echo html_escape($title ?? 'Kesehatan Data Master HR'); ?></h4>
  <span class="text-muted small">Issue Rows: <?php echo (int)$pg['total']; ?></span>
</div>

<div class="row g-2 mb-3">
  <div class="col-md-2"><div class="card"><div class="card-body py-2"><small class="text-muted">Pegawai Aktif</small><div class="h5 mb-0"><?php echo (int)($summary['active_employee'] ?? 0); ?></div></div></div></div>
  <div class="col-md-2"><div class="card border-warning"><div class="card-body py-2"><small class="text-muted">Pegawai tanpa User</small><div class="h5 mb-0 text-warning"><?php echo (int)($summary['employee_without_user'] ?? 0); ?></div></div></div></div>
  <div class="col-md-2"><div class="card border-warning"><div class="card-body py-2"><small class="text-muted">User tanpa Pegawai</small><div class="h5 mb-0 text-warning"><?php echo (int)($summary['user_without_employee'] ?? 0); ?></div></div></div></div>
  <div class="col-md-2"><div class="card border-danger"><div class="card-body py-2"><small class="text-muted">Tanpa Divisi/Jabatan</small><div class="h5 mb-0 text-danger"><?php echo ((int)($summary['employee_without_division'] ?? 0) + (int)($summary['employee_without_position'] ?? 0)); ?></div></div></div></div>
  <div class="col-md-2"><div class="card border-danger"><div class="card-body py-2"><small class="text-muted">Tanpa Jadwal Bulan Ini</small><div class="h5 mb-0 text-danger"><?php echo (int)($summary['employee_without_month_schedule'] ?? 0); ?></div></div></div></div>
  <div class="col-md-2"><div class="card border-danger"><div class="card-body py-2"><small class="text-muted">Tanpa Kontrak Aktif</small><div class="h5 mb-0 text-danger"><?php echo (int)($summary['employee_without_active_contract'] ?? 0); ?></div></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/master-health'); ?>" class="row g-2 align-items-end">
      <div class="col-md-4"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="NIP/Nama/Username/Email"></div>
      <div class="col-md-4"><label class="form-label mb-1">Jenis Isu</label><select name="issue_type" class="form-select"><option value="">Semua</option><?php foreach($issueOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['issue_type'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
      <div class="col-12"><a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('attendance/master-health'); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Jenis Isu</th><th>NIP</th><th>Nama</th><th>Username</th><th>Email</th><th>Divisi</th><th>Jabatan</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada isu pada filter ini.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><span class="badge bg-warning text-dark"><?php echo html_escape((string)$r['issue_type']); ?></span></td>
          <td><?php echo html_escape((string)$r['employee_code']); ?></td>
          <td><?php echo html_escape((string)$r['employee_name']); ?></td>
          <td><?php echo html_escape((string)$r['username']); ?></td>
          <td><?php echo html_escape((string)$r['email']); ?></td>
          <td><?php echo html_escape((string)$r['division_name']); ?></td>
          <td><?php echo html_escape((string)$r['position_name']); ?></td>
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
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('attendance/master-health?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/master-health?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('attendance/master-health?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
