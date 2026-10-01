<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$statusOptions = $status_options ?? [];
$requestTypeOptions = $request_type_options ?? [];
$statusCorrectionOptions = $status_correction_options ?? [];
$revisionRule = is_array($revision_window_rule ?? null) ? $revision_window_rule : ['enabled' => true, 'days' => 7, 'mode' => 'ON'];
$revisionWindowEnabled = !empty($revisionRule['enabled']);
$revisionWindowDays = (int)($revision_window_days ?? 7);
$revisionMinDate = (string)($revision_min_date ?? date('Y-m-d', strtotime('-' . $revisionWindowDays . ' day')));
$revisionMaxDate = (string)($revision_max_date ?? date('Y-m-d'));
$revisionNoticeText = (string)($revision_notice_text ?? '');

$buildQuery = static function ($overrides = []) use ($filters, $pg, $selectedEmployeeId) {
    $base = [
        'employee_id' => $selectedEmployeeId ?: '',
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'request_type' => $filters['request_type'] ?? '',
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

$statusClass = static function (string $status): string {
    $status = strtoupper($status);
    if ($status === 'APPROVED') return 'success';
    if ($status === 'REJECTED') return 'danger';
    if ($status === 'CANCELLED') return 'secondary';
    return 'warning';
};

$requestTypeLabel = static function (string $type): string {
    $map = [
        'LEAVE' => 'Izin/Cuti',
        'SICK' => 'Sakit',
        'MISSING_CHECKIN' => 'Lupa Check-in',
        'MISSING_CHECKOUT' => 'Lupa Check-out',
        'STATUS_CORRECTION' => 'Koreksi Status',
    ];
    $type = strtoupper(trim($type));
    return $map[$type] ?? $type;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Pengajuan Absensi Saya'); ?></h4>
    <small class="text-muted">Submit pengajuan absen, pantau status approval L1/L2/L3, dan auto apply ke rekap harian setelah final approve.</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/leave-requests'); ?>" class="d-flex gap-2">
    <select name="employee_id" class="form-select form-select-sm" style="min-width:260px">
      <option value="">Pilih Pegawai (Preview Superadmin)</option>
      <?php foreach ($employeeOptions as $o): ?>
        <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>>
          <?php echo html_escape($o['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-primary">Buka</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">Data pegawai belum terhubung ke akun ini.</div>
<?php else: ?>
<div class="card mb-3">
  <div class="card-header"><strong>Buat Pengajuan Baru</strong></div>
  <div class="card-body">
    <?php if ($revisionWindowEnabled && $revisionNoticeText !== ''): ?>
    <div class="alert alert-danger py-2 mb-3">
      <div class="fw-semibold mb-1">Perhatian</div>
      <div class="small mb-0"><?php echo html_escape($revisionNoticeText); ?></div>
    </div>
    <?php endif; ?>
    <form method="post" action="<?php echo site_url('my/leave-requests' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="row g-2" id="leaveRequestForm">
      <div class="col-md-2"><label class="form-label mb-1">Tanggal</label><input type="date" name="request_date" class="form-control" value="<?php echo html_escape($revisionMaxDate); ?>" <?php echo $revisionWindowEnabled ? ('min="' . html_escape($revisionMinDate) . '" ') : ''; ?>max="<?php echo html_escape($revisionMaxDate); ?>" required></div>
      <div class="col-md-3">
        <label class="form-label mb-1">Jenis Pengajuan</label>
        <select name="request_type" class="form-select" id="requestTypeSelect" required>
          <?php foreach($requestTypeOptions as $o): ?>
          <option value="<?php echo html_escape($o); ?>"><?php echo html_escape($requestTypeLabel((string)$o)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 req-checkin d-none"><label class="form-label mb-1">Check-in Pengganti</label><input type="datetime-local" name="requested_checkin_at" class="form-control"></div>
      <div class="col-md-3 req-checkout d-none"><label class="form-label mb-1">Check-out Pengganti</label><input type="datetime-local" name="requested_checkout_at" class="form-control"></div>
      <div class="col-md-3 req-status d-none">
        <label class="form-label mb-1">Status Koreksi</label>
        <select name="requested_status" class="form-select">
          <option value="">Pilih Status</option>
          <?php foreach($statusCorrectionOptions as $o): ?>
          <option value="<?php echo html_escape($o); ?>"><?php echo html_escape($o); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8"><label class="form-label mb-1">Alasan</label><input type="text" name="reason" class="form-control" maxlength="500" placeholder="Jelaskan alasan pengajuan" required></div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">Kirim Pengajuan</button></div>
      <div class="col-12">
        <div id="leaveRequestTypeHelp" class="alert alert-info py-2 mb-2"></div>
      </div>
      <div class="col-12">
        <div id="leaveShiftInfo" class="alert alert-secondary py-2 mb-0">Jadwal shift pada tanggal terpilih akan tampil di sini.</div>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('my/leave-requests'); ?>" class="row g-2 align-items-end">
      <?php if ($selectedEmployeeId): ?><input type="hidden" name="employee_id" value="<?php echo (int)$selectedEmployeeId; ?>"><?php endif; ?>
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="alasan/catatan"></div>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['status'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Jenis</label><select name="request_type" class="form-select"><option value="">Semua</option><?php foreach($requestTypeOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['request_type'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($requestTypeLabel((string)$o)); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><button type="submit" class="btn btn-outline-secondary">Filter</button> <a class="btn btn-outline-secondary" href="<?php echo site_url('my/leave-requests' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Tanggal</th><th>Jenis</th><th>Status</th><th>Data Pengajuan</th><th>Alasan</th><th>Timeline</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan.</td></tr>
      <?php else: foreach($rows as $r): $status = strtoupper((string)($r['status'] ?? 'PENDING')); ?>
        <tr>
          <td><?php echo html_escape((string)($r['request_date'] ?? '')); ?></td>
          <td><?php echo html_escape($requestTypeLabel((string)($r['request_type'] ?? ''))); ?></td>
          <td><span class="badge bg-<?php echo $statusClass($status); ?>"><?php echo html_escape($status); ?></span></td>
          <td>
            <?php if (!empty($r['requested_checkin_at'])): ?><div>In: <?php echo html_escape((string)$r['requested_checkin_at']); ?></div><?php endif; ?>
            <?php if (!empty($r['requested_checkout_at'])): ?><div>Out: <?php echo html_escape((string)$r['requested_checkout_at']); ?></div><?php endif; ?>
            <?php if (!empty($r['requested_status'])): ?><div>Status: <?php echo html_escape((string)$r['requested_status']); ?></div><?php endif; ?>
            <?php if (empty($r['requested_checkin_at']) && empty($r['requested_checkout_at']) && empty($r['requested_status'])): ?>-
            <?php endif; ?>
          </td>
          <td><?php echo html_escape((string)($r['reason'] ?? '-')); ?></td>
          <td>
            <?php if (!empty($r['approval_timeline'])): ?>
              <small class="text-muted"><?php echo html_escape((string)$r['approval_timeline']); ?></small>
            <?php else: ?>
              <small class="text-muted">Belum ada aksi approval</small>
            <?php endif; ?>
            <?php if (!empty($r['approval_notes'])): ?><div class="small mt-1"><?php echo html_escape((string)$r['approval_notes']); ?></div><?php endif; ?>
          </td>
          <td>
            <?php if ($status === 'PENDING'): ?>
            <form method="post" action="<?php echo site_url('my/leave-requests/cancel/' . (int)$r['id'] . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" onsubmit="return confirm('Batalkan pengajuan ini?');">
              <input type="hidden" name="notes" value="Dibatalkan pengaju dari portal">
              <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
            </form>
            <?php else: ?>
            <small class="text-muted">-</small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?> (Total <?php echo (int)$pg['total']; ?>)</small>
    <div class="btn-group">
      <?php $prev=max(1,(int)$pg['page']-1); $next=min((int)$pg['total_pages'],(int)$pg['page']+1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('my/leave-requests?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('my/leave-requests?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('my/leave-requests?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function(){
  var requestTypeEl = document.getElementById('requestTypeSelect');
  var requestDateEl = document.querySelector('input[name="request_date"]');
  var shiftInfoEl = document.getElementById('leaveShiftInfo');
  var typeHelpEl = document.getElementById('leaveRequestTypeHelp');
  var revisionWindowEnabled = <?php echo $revisionWindowEnabled ? 'true' : 'false'; ?>;
  var revisionMinDate = <?php echo json_encode($revisionMinDate); ?>;
  var revisionMaxDate = <?php echo json_encode($revisionMaxDate); ?>;
  if (!requestTypeEl) { return; }

  var checkinWrap = document.querySelector('.req-checkin');
  var checkoutWrap = document.querySelector('.req-checkout');
  var statusWrap = document.querySelector('.req-status');
  var typeHelpMap = {
    LEAVE: 'Izin/Cuti: menandai hari tidak masuk kerja secara resmi. Saat disetujui final, status harian menjadi LEAVE.',
    SICK: 'Sakit: menandai hari tidak masuk karena sakit. Saat disetujui final, status harian menjadi SICK.',
    MISSING_CHECKIN: 'Lupa Check-in: dipakai saat check-in terlewat. Isi jam check-in pengganti yang benar.',
    MISSING_CHECKOUT: 'Lupa Check-out: dipakai saat check-out terlewat. Isi jam check-out pengganti yang benar.',
    STATUS_CORRECTION: 'Koreksi Status: ubah status absensi harian (misal PRESENT/LATE/ALPHA/LEAVE/SICK) sesuai kondisi riil.'
  };

  function toggleFields() {
    var type = (requestTypeEl.value || '').toUpperCase();
    checkinWrap.classList.toggle('d-none', type !== 'MISSING_CHECKIN');
    checkoutWrap.classList.toggle('d-none', type !== 'MISSING_CHECKOUT');
    statusWrap.classList.toggle('d-none', type !== 'STATUS_CORRECTION');
    if (typeHelpEl) {
      typeHelpEl.textContent = typeHelpMap[type] || 'Pilih jenis pengajuan sesuai kasus absensi Anda.';
    }
  }

  requestTypeEl.addEventListener('change', toggleFields);
  toggleFields();

  function renderShiftInfo(text, cls) {
    if (!shiftInfoEl) { return; }
    shiftInfoEl.className = 'alert py-2 mb-0 ' + cls;
    shiftInfoEl.textContent = text;
  }

  function loadShiftInfo() {
    if (!requestDateEl || !requestDateEl.value) {
      renderShiftInfo('Pilih tanggal pengajuan untuk melihat jadwal shift.', 'alert-secondary');
      return;
    }
    if (revisionWindowEnabled && ((revisionMinDate && requestDateEl.value < revisionMinDate) || (revisionMaxDate && requestDateEl.value > revisionMaxDate))) {
      requestDateEl.value = revisionMaxDate || '';
      renderShiftInfo('Tanggal pengajuan dibatasi maksimal ' + <?php echo json_encode($revisionWindowDays); ?> + ' hari kalender ke belakang dari hari ini.', 'alert-danger');
      return;
    }
    var url = <?php echo json_encode(site_url('my/leave-requests/schedule' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : ''))); ?>;
    var sep = url.indexOf('?') >= 0 ? '&' : '?';
    fetch(url + sep + 'date=' + encodeURIComponent(requestDateEl.value), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json().then(function (j) { return {ok:r.ok, j:j}; }); })
    .then(function (res) {
      if (!res.ok || !res.j || Number(res.j.ok) !== 1) {
        renderShiftInfo((res.j && res.j.message) ? res.j.message : 'Gagal memuat jadwal shift.', 'alert-danger');
        return;
      }
      if (Number(res.j.has_schedule || 0) !== 1) {
        renderShiftInfo('Tidak ada jadwal shift pada tanggal ini.', 'alert-warning');
        return;
      }
      var text = 'Shift: ' + (res.j.shift_code || '-') + ' - ' + (res.j.shift_name || '-') + ' | Jam: ' + (res.j.start_time || '-') + ' s/d ' + (res.j.end_time || '-');
      if (Number(res.j.is_overnight || 0) === 1) {
        text += ' (overnight)';
      }
      renderShiftInfo(text, 'alert-info');
    })
    .catch(function () {
      renderShiftInfo('Terjadi kesalahan saat mengambil jadwal shift.', 'alert-danger');
    });
  }

  if (requestDateEl) {
    requestDateEl.addEventListener('change', loadShiftInfo);
    loadShiftInfo();
  }
})();
</script>
