<?php
$filters = $filters ?? [];
$employeeRows = $employee_rows ?? [];
$scheduleDetail = $schedule_detail ?? null;
$recapRows = $recap_rows ?? [];
$nationalHolidays = $national_holidays ?? [];
$scheduleTab = $schedule_tab ?? 'people';
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];
$shiftOptions = $shift_options ?? [];
$employeeOptions = $employee_options ?? [];
$currentUser = $current_user ?? [];
$userPerms = $user_perms ?? [];

$isSuperadmin = !empty($currentUser['is_superadmin']);
$canEdit = $isSuperadmin || !empty($userPerms['attendance.schedules.index']['can_edit']);
$canDelete = $isSuperadmin || !empty($userPerms['attendance.schedules.index']['can_delete']);
$canMonthlyScheduleOverride = !empty($can_monthly_schedule_override);
$detailRows = (array)($scheduleDetail['rows'] ?? []);
$detailEmployee = (array)($scheduleDetail['employee'] ?? []);

$buildQuery = static function (array $overrides = []) use ($filters, $pg, $scheduleTab) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'shift_code' => $filters['shift_code'] ?? '',
        'date_start' => $filters['date_start'] ?? '',
        'date_end' => $filters['date_end'] ?? '',
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
        'tab' => $scheduleTab,
        'detail_employee_id' => 0,
    ];
    return http_build_query(array_merge($base, $overrides));
};

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) return range(1, $totalPages);
    $items = [1];
    $start = max(2, $page - 1);
    $end = min($totalPages - 1, $page + 1);
    if ($start > 2) $items[] = '...';
    for ($i = $start; $i <= $end; $i++) $items[] = $i;
    if ($end < $totalPages - 1) $items[] = '...';
    $items[] = $totalPages;
    return $items;
};
?>

<style>
  .att-schedules .table th, .att-schedules .table td { padding: .72rem .85rem; vertical-align: middle; }
  .att-schedules .schedule-tabs { display: flex; gap: .45rem; border-bottom: 1px solid #e4e7ec; padding: 0 .25rem; }
  .att-schedules .schedule-tab { color: #667085; text-decoration: none; font-weight: 700; padding: .7rem .9rem; border-bottom: 3px solid transparent; }
  .att-schedules .schedule-tab.active { color: #ad0d23; border-bottom-color: #ad0d23; }
  .att-schedules .person-days { color: #ad0d23; font-weight: 800; font-size: 1.05rem; }
  .att-schedules .shift-summary { max-width: 280px; color: #667085; font-size: .85rem; }
  .att-schedules .detail-header { background: linear-gradient(135deg, #fff7ed, #fffdfb); border: 1px solid #f1d6bd; }
  .att-schedules .detail-meta { color: #667085; font-size: .84rem; }
</style>

<div class="att-schedules">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-0"><i class="ri-calendar-check-line me-2 text-primary"></i><?php echo html_escape($title ?? 'Jadwal Shift Pegawai'); ?></h5>
      <small class="text-muted">Daftar utama diringkas per pegawai; buka detail untuk melihat dan mengubah jadwal harian.</small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?php echo site_url('attendance/schedules-v2'); ?>" class="btn btn-outline-primary"><i class="ri-table-line me-1"></i>Jadwal V2</a>
      <?php if ($canEdit): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#createScheduleBox" aria-expanded="false" aria-controls="createScheduleBox"><i class="ri-add-line me-1"></i>Tambah Jadwal</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($canEdit): ?>
  <div class="collapse mb-3" id="createScheduleBox">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <form method="post" action="<?php echo site_url('attendance/schedules/store?' . $buildQuery()); ?>" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label mb-1">Pegawai</label><select name="employee_id" class="form-select" required><option value="">Pilih Pegawai</option><?php foreach ($employeeOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>"><?php echo html_escape((string)$o['label']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label mb-1">Shift</label><select name="shift_id" class="form-select" required><option value="">Pilih Shift</option><?php foreach ($shiftOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>"><?php echo html_escape((string)$o['label']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label mb-1">Tanggal</label><input type="date" name="schedule_date" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label mb-1">Catatan</label><input type="text" name="notes" class="form-control" maxlength="255" placeholder="Opsional"></div>
        <div class="col-md-1 d-grid"><button class="btn btn-primary" type="submit" title="Simpan"><i class="ri-check-line"></i></button></div>
        <?php if ($canMonthlyScheduleOverride): ?>
          <div class="col-12 small text-muted"><label class="me-3"><input type="checkbox" name="allow_monthly_override" value="1"> Izinkan override bila total jadwal melewati batas bulanan</label><input type="text" name="override_reason" class="form-control form-control-sm d-inline-block" style="max-width:380px" maxlength="255" placeholder="Alasan override (jika diperlukan)"></div>
        <?php endif; ?>
      </form>
    </div></div>
  </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm mb-3"><div class="card-body py-3">
    <form method="get" action="<?php echo site_url('attendance/schedules'); ?>" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="<?php echo html_escape((string)$scheduleTab); ?>">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Shift"></div>
      <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach ($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Shift Code</label><input type="text" name="shift_code" class="form-control" value="<?php echo html_escape((string)($filters['shift_code'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach ([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line me-1"></i>Filter</button> <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/schedules'); ?>">Reset</a></div>
    </form>
  </div></div>

  <div class="card border-0 shadow-sm mb-3"><div class="card-body p-0">
    <div class="schedule-tabs">
      <a class="schedule-tab <?php echo $scheduleTab === 'people' ? 'active' : ''; ?>" href="<?php echo site_url('attendance/schedules?' . $buildQuery(['tab' => 'people', 'detail_employee_id' => 0])); ?>"><i class="ri-team-line me-1"></i>Daftar Pegawai</a>
      <a class="schedule-tab <?php echo $scheduleTab === 'recap' ? 'active' : ''; ?>" href="<?php echo site_url('attendance/schedules?' . $buildQuery(['tab' => 'recap', 'detail_employee_id' => 0])); ?>"><i class="ri-bar-chart-grouped-line me-1"></i>Rekap Shift</a>
    </div>
  </div></div>

  <?php if ($scheduleTab === 'recap'): ?>
    <?php $this->load->view('attendance/_schedule_recap', ['recap_rows' => $recapRows, 'national_holidays' => $nationalHolidays]); ?>
  <?php else: ?>
    <?php if (!empty($scheduleDetail)): ?>
      <div class="card detail-header shadow-sm mb-3" id="schedule-detail">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
              <h6 class="mb-1"><i class="ri-user-search-line me-1"></i><?php echo html_escape((string)($detailEmployee['employee_name'] ?? '-')); ?></h6>
              <div class="detail-meta"><?php echo html_escape((string)($detailEmployee['employee_code'] ?? '')); ?> · <?php echo html_escape((string)($detailEmployee['division_name'] ?? 'Tanpa Divisi')); ?> · <?php echo count($detailRows); ?> jadwal pada filter aktif</div>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('attendance/schedules?' . $buildQuery(['detail_employee_id' => 0])); ?>"><i class="ri-close-line me-1"></i>Tutup Detail</a>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Tanggal</th><th>Shift</th><th>Jam</th><th>Catatan</th><?php if ($canEdit || $canDelete): ?><th class="text-end" style="width:95px;">Aksi</th><?php endif; ?></tr></thead>
              <tbody>
                <?php if (empty($detailRows)): ?>
                  <tr><td colspan="<?php echo ($canEdit || $canDelete) ? 5 : 4; ?>" class="text-center text-muted py-3">Tidak ada jadwal yang cocok dengan filter.</td></tr>
                <?php else: foreach ($detailRows as $r): ?>
                  <tr>
                    <td class="fw-semibold"><?php echo html_escape((string)$r['schedule_date']); ?></td>
                    <td><strong><?php echo html_escape((string)($r['shift_code'] ?? '-')); ?></strong> <span class="text-muted">· <?php echo html_escape((string)($r['shift_name'] ?? '')); ?></span></td>
                    <td><?php echo html_escape((string)($r['start_time'] ?? '-')); ?>-<?php echo html_escape((string)($r['end_time'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
                    <?php if ($canEdit || $canDelete): ?><td class="text-end"><div class="d-flex gap-1 justify-content-end">
                      <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editScheduleModal<?php echo (int)$r['id']; ?>" title="Edit"><i class="ri-edit-line"></i></button><?php endif; ?>
                      <?php if ($canDelete): ?><form method="post" action="<?php echo site_url('attendance/schedules/delete/' . (int)$r['id'] . '?' . $buildQuery(['detail_employee_id' => (int)($detailEmployee['employee_id'] ?? 0)])); ?>" onsubmit="return confirm('Hapus jadwal ini?');"><button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="ri-delete-bin-line"></i></button></form><?php endif; ?>
                    </div></td><?php endif; ?>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><strong>Daftar Pegawai Terjadwal</strong><span class="badge bg-label-primary"><?php echo number_format((int)($pg['total'] ?? 0), 0, ',', '.'); ?> pegawai</span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Pegawai</th><th>Divisi</th><th class="text-center">Hari Terjadwal</th><th>Rentang Jadwal</th><th>Shift Digunakan</th><th class="text-end" style="width:100px;">Aksi</th></tr></thead>
          <tbody>
            <?php if (empty($employeeRows)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pegawai dengan jadwal pada filter ini.</td></tr>
            <?php else: foreach ($employeeRows as $r): ?>
              <tr>
                <td><div class="fw-semibold"><?php echo html_escape((string)$r['employee_name']); ?></div><small class="text-muted"><?php echo html_escape((string)$r['employee_code']); ?></small></td>
                <td><?php echo html_escape((string)($r['division_name'] ?? 'Tanpa Divisi')); ?></td>
                <td class="text-center"><span class="person-days"><?php echo (int)($r['scheduled_days'] ?? 0); ?></span><div class="small text-muted">hari</div></td>
                <td><div><?php echo html_escape((string)($r['first_schedule_date'] ?? '-')); ?></div><small class="text-muted">s/d <?php echo html_escape((string)($r['last_schedule_date'] ?? '-')); ?></small></td>
                <td><div class="shift-summary"><?php echo html_escape((string)($r['shift_codes'] ?? '-')); ?></div></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('attendance/schedules?' . $buildQuery(['detail_employee_id' => (int)$r['employee_id']])); ?>#schedule-detail"><i class="ri-list-check-2 me-1"></i>Detail</a></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (($pg['total_pages'] ?? 1) > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center"><small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small><div class="btn-group">
          <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('attendance/schedules?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
          <?php foreach ($buildPageItems((int)$pg['page'], (int)$pg['total_pages']) as $item): ?>
            <?php if ($item === '...'): ?><span class="btn btn-sm btn-outline-secondary disabled">...</span><?php else: ?><a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/schedules?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a><?php endif; ?>
          <?php endforeach; ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('attendance/schedules?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
        </div></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($detailRows) && $canEdit): ?>
  <?php foreach ($detailRows as $r): ?>
    <div class="modal fade" id="editScheduleModal<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
      <form method="post" action="<?php echo site_url('attendance/schedules/update/' . (int)$r['id'] . '?' . $buildQuery(['detail_employee_id' => (int)($detailEmployee['employee_id'] ?? 0)])); ?>">
        <div class="modal-header"><h6 class="modal-title">Edit Jadwal: <?php echo html_escape((string)($detailEmployee['employee_name'] ?? '')); ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="schedule_date" class="form-control" value="<?php echo html_escape((string)$r['schedule_date']); ?>" required></div>
          <div class="mb-2"><label class="form-label">Shift</label><select name="shift_id" class="form-select" required><?php foreach ($shiftOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === (int)($r['shift_id'] ?? 0)) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option><?php endforeach; ?></select></div>
          <div><label class="form-label">Catatan</label><input type="text" name="notes" class="form-control" value="<?php echo html_escape((string)($r['notes'] ?? '')); ?>" maxlength="255"></div>
          <?php if ($canMonthlyScheduleOverride): ?><div class="mt-2 small text-muted"><label><input type="checkbox" name="allow_monthly_override" value="1"> Izinkan override bila total jadwal melewati batas bulanan</label><input type="text" name="override_reason" class="form-control form-control-sm mt-1" maxlength="255" placeholder="Alasan override (jika diperlukan)"></div><?php endif; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
      </form>
    </div></div></div>
  <?php endforeach; ?>
<?php endif; ?>
