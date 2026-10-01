<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$divisionOptions = $division_options ?? [];
$eventOptions = $event_options ?? [];
$sourceOptions = $source_options ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'event_type' => $filters['event_type'] ?? '',
        'source_type' => $filters['source_type'] ?? '',
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
  <h4 class="mb-0"><?php echo html_escape($title ?? 'Log Presensi'); ?></h4>
  <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/logs'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Shift/Lokasi"></div>
      <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Event</label><select name="event_type" class="form-select"><option value="">Semua</option><?php foreach($eventOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['event_type'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Source</label><select name="source_type" class="form-select"><option value="">Semua</option><?php foreach($sourceOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['source_type'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><button type="submit" class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/logs'); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Waktu</th><th>Tanggal Logis</th><th>NIP</th><th>Nama</th><th>Divisi</th><th>Shift</th><th>Event</th><th>Source</th><th>Lokasi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?php echo html_escape((string)$r['attendance_at']); ?></td>
          <td><?php echo html_escape((string)$r['attendance_date']); ?></td>
          <td><?php echo html_escape((string)$r['employee_code']); ?></td>
          <td><?php echo html_escape((string)$r['employee_name']); ?></td>
          <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['shift_code'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)$r['event_type']); ?></td>
          <td><?php echo html_escape((string)$r['source_type']); ?></td>
          <td><?php echo html_escape((string)($r['location_name'] ?? '-')); ?></td>
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
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('attendance/logs?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/logs?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('attendance/logs?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
