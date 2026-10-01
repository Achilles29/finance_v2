<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$divisionOptions = $division_options ?? [];
$statusOptions = $status_options ?? [];
$requestTypeOptions = $request_type_options ?? [];
$approvalHistoryMap = $approval_history_map ?? [];
$existingDailyMap = $existing_daily_map ?? [];
$scheduleMap = $schedule_map ?? [];
$currentUser = $current_user ?? [];
$userPerms = $user_perms ?? [];
$statusTabs = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];

$isSuperadmin = !empty($currentUser['is_superadmin']);
$canEdit = $isSuperadmin || !empty($userPerms['attendance.pending.index']['can_edit']);
$actorEmployeeId = (int)($currentUser['employee_id'] ?? 0);

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
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

$formatClock = static function ($datetime): string {
    $raw = trim((string)$datetime);
    if ($raw === '') {
        return '-';
    }
    $ts = strtotime($raw);
    return $ts ? date('H:i', $ts) : $raw;
};
?>
<style>
  .pending-table-wrap {
    max-height: 72vh;
    overflow: auto;
  }
  .pending-table {
    min-width: 1240px;
  }
  .pending-table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa;
    white-space: nowrap;
    box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.06);
  }
  .pending-table th:last-child,
  .pending-table td:last-child {
    min-width: 320px;
  }
  .pending-table th:nth-child(7),
  .pending-table td:nth-child(7) {
    min-width: 260px;
  }
  .pending-table th:nth-child(9),
  .pending-table td:nth-child(9) {
    min-width: 230px;
  }
  .pending-table td:last-child {
    vertical-align: top;
  }
  .pending-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: center;
    justify-content: center;
    width: 100%;
  }
  .pending-actions form {
    margin: 0;
    flex: 0 0 auto;
  }
  .pending-actions .btn {
    width: 38px !important;
    min-width: 38px;
    height: 34px;
    padding: 0 !important;
    justify-content: center;
    text-align: center;
    border-radius: 10px;
  }
  .pending-actions .span-2 {
    flex: 0 0 auto;
  }
  .pending-actions-note {
    margin-top: .4rem;
    font-size: 11px;
    color: #7a6b65;
    line-height: 1.35;
  }
  .pending-record-box {
    font-size: 12px;
    line-height: 1.4;
  }
  .pending-record-box .meta {
    color: #7a6b65;
    font-size: 11px;
  }
  .pending-record-box .schedule-box {
    background: #f8f5ff;
    border: 1px solid #ddd4ff;
    border-radius: 10px;
    padding: .45rem .6rem;
    margin-bottom: .45rem;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo html_escape($title ?? 'Pengajuan & Approval Absensi'); ?></h4>
  <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
</div>

<?php
$workspace_tabs = [];
foreach ($statusTabs as $statusTab) {
  $isActiveTab = strtoupper((string)($filters['status'] ?? 'PENDING')) === $statusTab;
  $workspace_tabs[] = [
    'label' => ucfirst(strtolower((string)$statusTab)),
    'url' => site_url('attendance/pending-requests?' . $buildQuery(['status' => $statusTab, 'page' => 1])),
    'active' => $isActiveTab,
  ];
}
$workspace_tab_label = 'Status';
$workspace_tab_aria_label = 'Filter status pengajuan absensi';
$this->load->view('layout/_workspace_tabs', get_defined_vars());
?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/pending-requests'); ?>" class="row g-2 align-items-end">
      <input type="hidden" name="status" value="<?php echo html_escape((string)($filters['status'] ?? 'PENDING')); ?>">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Pegawai/Alasan"></div>
      <div class="col-md-3"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Jenis</label><select name="request_type" class="form-select"><option value="">Semua</option><?php foreach($requestTypeOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['request_type'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><button type="submit" class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/pending-requests'); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body border-bottom">
    <?php if ($canEdit): ?>
    <form method="post" action="<?php echo site_url('attendance/pending-requests/bulk-action?' . $buildQuery()); ?>" id="bulkApproveForm" class="d-flex flex-wrap gap-2 align-items-center">
      <input type="hidden" name="bulk_action" value="APPROVE">
      <button type="submit" class="btn btn-sm btn-success" data-loading-label="Bulk Approve...">Bulk Approve</button>
      <?php if ($isSuperadmin): ?>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1" id="bulkForceFinal" name="bulk_force_final">
        <label class="form-check-label small" for="bulkForceFinal">Override final (langsung ACC)</label>
      </div>
      <?php endif; ?>
      <input type="text" class="form-control form-control-sm" name="bulk_notes" placeholder="Catatan bulk (opsional)" style="max-width: 320px;">
      <small class="text-muted">Centang pengajuan di kolom paling kiri.</small>
      <div id="bulkPendingSelectedIds"></div>
    </form>
    <?php else: ?>
    <small class="text-muted">Bulk aksi hanya untuk user dengan izin edit.</small>
    <?php endif; ?>
  </div>
  <div class="pending-table-wrap">
    <table class="table table-striped mb-0 pending-table">
      <thead>
        <tr>
          <th style="width: 38px;">
            <?php if ($canEdit): ?>
            <input type="checkbox" id="selectAllPending">
            <?php endif; ?>
          </th>
          <th>Tanggal</th><th>Nama</th><th>Jenis</th><th>Record Absen</th><th>Status</th><th>Level</th><th>Alasan</th><th style="min-width:220px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <?php
          $status = strtoupper((string)($r['status'] ?? 'PENDING'));
          $approvedLevels = (int)($r['approved_levels'] ?? 0);
          $levelLabel = $status === 'PENDING' ? ('L' . ($approvedLevels + 1) . ' berikutnya') : ('Selesai (' . $approvedLevels . ' level)');
          $actionUrl = site_url('attendance/pending-requests/action/' . (int)$r['id'] . '?' . $buildQuery());
          $canCancel = $status === 'PENDING' && ($isSuperadmin || ((int)($r['employee_id'] ?? 0) === $actorEmployeeId && $actorEmployeeId > 0));
          $requestId = (int)($r['id'] ?? 0);
          $historyRows = (array)($approvalHistoryMap[$requestId] ?? []);
          $historyByLevel = [];
          foreach ($historyRows as $historyRow) {
              $historyByLevel[(int)($historyRow['approval_level'] ?? 0)] = $historyRow;
          }
          $dailyKey = (int)($r['employee_id'] ?? 0) . '|' . (string)($r['request_date'] ?? '');
          $existingDaily = is_array($existingDailyMap[$dailyKey] ?? null) ? $existingDailyMap[$dailyKey] : null;
          $scheduleRow = is_array($scheduleMap[$dailyKey] ?? null) ? $scheduleMap[$dailyKey] : null;
        ?>
        <tr>
          <td>
            <?php if ($status === 'PENDING' && $canEdit): ?>
            <input type="checkbox" class="pending-check" name="request_ids[]" value="<?php echo (int)$r['id']; ?>">
            <?php endif; ?>
          </td>
          <td><?php echo html_escape((string)$r['request_date']); ?></td>
          <td>
            <div class="fw-semibold"><?php echo html_escape((string)$r['employee_name']); ?></div>
          </td>
          <td><?php echo html_escape((string)$r['request_type']); ?></td>
          <td>
            <?php if ($scheduleRow || $existingDaily): ?>
              <div class="pending-record-box">
                <?php if ($scheduleRow): ?>
                  <div class="schedule-box">
                    <div class="fw-semibold">
                      Jadwal Shift:
                      <?php echo html_escape(trim((string)($scheduleRow['shift_code'] ?? '')) !== '' ? (string)($scheduleRow['shift_code'] ?? '-') : '-'); ?>
                      <?php if (trim((string)($scheduleRow['shift_name'] ?? '')) !== ''): ?>
                        <span class="text-muted">- <?php echo html_escape((string)($scheduleRow['shift_name'] ?? '')); ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="meta">
                      Jam:
                      <?php echo html_escape(trim((string)($scheduleRow['start_time'] ?? '')) !== '' ? substr((string)$scheduleRow['start_time'], 0, 5) : '-'); ?>
                      -
                      <?php echo html_escape(trim((string)($scheduleRow['end_time'] ?? '')) !== '' ? substr((string)$scheduleRow['end_time'], 0, 5) : '-'); ?>
                      <?php if ((int)($scheduleRow['is_overnight'] ?? 0) === 1): ?>
                        <span class="badge bg-dark ms-1">Overnight</span>
                      <?php endif; ?>
                    </div>
                    <?php if (trim((string)($scheduleRow['notes'] ?? '')) !== ''): ?>
                      <div class="meta">Catatan jadwal: <?php echo html_escape((string)($scheduleRow['notes'] ?? '')); ?></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if ($existingDaily): ?>
                <div class="fw-semibold">
                  <?php echo html_escape((string)($existingDaily['shift_code'] ?? 'Tanpa Shift')); ?>
                  <span class="badge bg-light text-dark border ms-1"><?php echo html_escape((string)($existingDaily['attendance_status'] ?? '')); ?></span>
                </div>
                <div class="meta">
                  IN: <?php echo html_escape($formatClock($existingDaily['checkin_at'] ?? '')); ?>
                  | OUT: <?php echo html_escape($formatClock($existingDaily['checkout_at'] ?? '')); ?>
                </div>
                <div class="meta">Sumber: <?php echo html_escape((string)($existingDaily['source_type'] ?? 'AUTO')); ?></div>
                <?php else: ?>
                <div class="meta">Belum ada record absensi pada tanggal ini.</div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="text-muted small">Belum ada record</span>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-<?php echo $statusClass($status); ?>"><?php echo html_escape($status); ?></span></td>
          <td>
            <small><?php echo html_escape($levelLabel); ?></small>
            <?php if (!empty($r['approval_timeline'])): ?>
              <div class="text-muted mt-1" style="font-size:11px;"><?php echo html_escape((string)$r['approval_timeline']); ?></div>
            <?php endif; ?>
            <div class="mt-1">
              <button class="btn btn-sm btn-outline-secondary py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#pendingTimeline<?php echo $requestId; ?>" aria-expanded="false">
                Timeline L1/L2/L3
              </button>
            </div>
          </td>
          <td><?php echo html_escape((string)($r['reason'] ?? '-')); ?></td>
          <td>
            <?php if ($status === 'PENDING' && $canEdit): ?>
              <div class="pending-actions action-cell">
                <form method="post" action="<?php echo $actionUrl; ?>" class="pending-action-form" data-action="APPROVE">
                  <input type="hidden" name="action" value="APPROVE">
                  <input type="hidden" name="notes" value="">
                  <button type="submit" class="btn btn-sm btn-success action-icon-btn" data-bs-toggle="tooltip" title="Approve" aria-label="Approve" data-loading-label="Menyetujui..."><i class="ri ri-check-line"></i></button>
                </form>
                <form method="post" action="<?php echo $actionUrl; ?>" class="pending-action-form" data-action="REJECT">
                  <input type="hidden" name="action" value="REJECT">
                  <input type="hidden" name="notes" value="">
                  <button type="submit" class="btn btn-sm btn-danger action-icon-btn" data-bs-toggle="tooltip" title="Reject" aria-label="Reject" data-loading-label="Menolak..."><i class="ri ri-close-line"></i></button>
                </form>
                <?php if ($canCancel): ?>
                <form method="post" action="<?php echo $actionUrl; ?>" class="pending-action-form span-2" data-action="CANCEL">
                  <input type="hidden" name="action" value="CANCEL">
                  <input type="hidden" name="notes" value="">
                  <button type="submit" class="btn btn-sm btn-outline-secondary action-icon-btn" data-bs-toggle="tooltip" title="Cancel" aria-label="Cancel" data-loading-label="Membatalkan..."><i class="ri ri-close-circle-line"></i></button>
                </form>
                <?php endif; ?>
                <?php if ($isSuperadmin): ?>
                <form method="post" action="<?php echo $actionUrl; ?>" class="pending-action-form span-2" data-action="APPROVE_OVERRIDE">
                  <input type="hidden" name="action" value="APPROVE">
                  <input type="hidden" name="force_final" value="1">
                  <input type="hidden" name="notes" value="">
                  <button type="submit" class="btn btn-sm btn-warning action-icon-btn" data-bs-toggle="tooltip" title="Override ACC" aria-label="Override ACC" data-loading-label="Override..."><i class="ri ri-shield-check-line"></i></button>
                </form>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <small class="text-muted">Tidak ada aksi</small>
            <?php endif; ?>
          </td>
        </tr>
        <tr class="collapse bg-light" id="pendingTimeline<?php echo $requestId; ?>">
          <td colspan="9">
            <div class="px-2 py-2">
              <div class="small fw-bold mb-2">Riwayat Approval Per Level</div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr><th style="width:80px;">Level</th><th style="width:120px;">Status</th><th style="width:220px;">Verifier</th><th style="width:170px;">Waktu</th><th>Catatan</th></tr>
                  </thead>
                  <tbody>
                    <?php for ($lv = 1; $lv <= 3; $lv++): ?>
                      <?php $h = $historyByLevel[$lv] ?? null; ?>
                      <tr>
                        <td><span class="badge bg-secondary">L<?php echo $lv; ?></span></td>
                        <td>
                          <?php if (!empty($h)): ?>
                            <?php $hStatus = strtoupper((string)($h['action'] ?? '')); ?>
                            <span class="badge bg-<?php echo $hStatus === 'APPROVED' ? 'success' : 'danger'; ?>"><?php echo html_escape($hStatus); ?></span>
                          <?php else: ?>
                            <span class="badge bg-warning text-dark">PENDING</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if (!empty($h)): ?>
                            <?php echo html_escape((string)($h['approver_name'] ?? '-')); ?>
                            <div class="text-muted small"><?php echo html_escape((string)($h['approver_code'] ?? '')); ?></div>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo !empty($h['acted_at']) ? html_escape((string)$h['acted_at']) : '<span class="text-muted">-</span>'; ?></td>
                        <td><?php echo !empty($h['notes']) ? html_escape((string)$h['notes']) : '<span class="text-muted">-</span>'; ?></td>
                      </tr>
                    <?php endfor; ?>
                  </tbody>
                </table>
              </div>
            </div>
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
      <?php $prev=max(1,(int)$pg['page']-1); $next=min((int)$pg['total_pages'],(int)$pg['page']+1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('attendance/pending-requests?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/pending-requests?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('attendance/pending-requests?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  function uiAlert(message, title) {
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return window.FinanceUI.alert(message, { title: title || 'Informasi' });
    }
    window.alert(String(message || ''));
    return Promise.resolve();
  }

  function uiConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      return window.FinanceUI.confirm(message, options || {});
    }
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', { title: 'UI Belum Siap' })
        .then(function () { return false; });
    }
    return Promise.resolve(false);
  }

  function setSubmitLoading(form, label) {
    var submitter = form.querySelector('button[type="submit"],input[type="submit"]');
    if (submitter) {
      submitter.disabled = true;
      submitter.classList.add('is-loading');
      if (submitter.tagName === 'BUTTON') {
        var oldHtml = submitter.innerHTML;
        submitter.setAttribute('data-old-html', oldHtml);
        submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (label || 'Memproses...');
      }
    }
    form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(function (btn) {
      btn.disabled = true;
    });
  }

  var selectAll = document.getElementById('selectAllPending');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.pending-check').forEach(function (el) {
        el.checked = !!selectAll.checked;
      });
    });
  }

  var bulkForm = document.getElementById('bulkApproveForm');
  if (bulkForm) {
    bulkForm.addEventListener('submit', function (event) {
      if (bulkForm.dataset.confirmed === '1') {
        return;
      }
      var checked = document.querySelectorAll('.pending-check:checked').length;
      if (checked <= 0) {
        event.preventDefault();
        event.stopImmediatePropagation();
        uiAlert('Pilih minimal satu pengajuan untuk bulk approve.', 'Validasi');
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      var hiddenWrap = document.getElementById('bulkPendingSelectedIds');
      if (hiddenWrap) {
        hiddenWrap.innerHTML = '';
        document.querySelectorAll('.pending-check:checked').forEach(function (el) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'request_ids[]';
          input.value = String(el.value || '');
          hiddenWrap.appendChild(input);
        });
      }
      uiConfirm('Proses bulk approve untuk ' + checked + ' pengajuan?').then(function (ok) {
        if (!ok) return;
        bulkForm.dataset.confirmed = '1';
        setSubmitLoading(bulkForm, 'Bulk Approve...');
        bulkForm.submit();
      });
    });
  }

  document.querySelectorAll('.pending-action-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.confirmed === '1') {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      var action = String(form.getAttribute('data-action') || 'APPROVE');
      var labelMap = {
        APPROVE: 'approve',
        REJECT: 'reject',
        CANCEL: 'cancel',
        APPROVE_OVERRIDE: 'override final approve'
      };
      var label = labelMap[action] || 'proses';
      uiConfirm('Yakin proses aksi ' + label + ' untuk pengajuan ini?', {
        title: 'Konfirmasi Aksi',
        okText: 'Ya, Proses'
      }).then(function (ok) {
        if (!ok) return;
        form.dataset.confirmed = '1';
        var submitBtn = form.querySelector('button[type="submit"],input[type="submit"]');
        var loadingLabel = submitBtn ? (submitBtn.getAttribute('data-loading-label') || 'Memproses...') : 'Memproses...';
        setSubmitLoading(form, loadingLabel);
        form.submit();
      });
    });
  });
})();
</script>
