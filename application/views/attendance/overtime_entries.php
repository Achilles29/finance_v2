<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];
$employeeOptions = $employee_options ?? [];
$overtimeStandardOptions = $overtime_standard_options ?? [];
$statusOptions = $status_options ?? ['PENDING', 'APPROVED', 'REJECTED'];
$editRow = $edit_row ?? null;
$isEdit = !empty($editRow);
$summary = $summary ?? [];
$summaryTotalEntries = (int)($summary['total_entries'] ?? $pg['total'] ?? 0);
$summaryTotalHours = (float)($summary['total_hours'] ?? 0);
$summaryTotalAmount = (float)($summary['total_amount'] ?? 0);
$summaryApprovedEntries = (int)($summary['approved_entries'] ?? 0);
$summaryPendingEntries = (int)($summary['pending_entries'] ?? 0);
$summaryRejectedEntries = (int)($summary['rejected_entries'] ?? 0);
$summaryCancelledEntries = (int)($summary['cancelled_entries'] ?? 0);
$summaryApprovedAmount = (float)($summary['approved_amount'] ?? 0);

$editStartTime = '';
$editEndTime = '';
if ($isEdit) {
    $startTs = !empty($editRow['start_at']) ? strtotime((string)$editRow['start_at']) : 0;
    $endTs = !empty($editRow['end_at']) ? strtotime((string)$editRow['end_at']) : 0;
    $editStartTime = $startTs ? date('H:i', $startTs) : '';
    $editEndTime = $endTs ? date('H:i', $endTs) : '';
}

$initialEditPayload = $isEdit ? [
    'id' => (int)($editRow['id'] ?? 0),
    'employee_id' => (int)($editRow['employee_id'] ?? 0),
    'overtime_date' => (string)($editRow['overtime_date'] ?? ''),
    'start_time' => $editStartTime,
    'end_time' => $editEndTime,
    'overtime_standard_id' => (int)($editRow['overtime_standard_id'] ?? 0),
    'status' => strtoupper((string)($editRow['status'] ?? 'APPROVED')),
    'notes' => (string)($editRow['notes'] ?? ''),
] : null;

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'employee_id' => $filters['employee_id'] ?? '',
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

<style>
  .overtime-entry-header {
    background: linear-gradient(118deg, rgba(151, 20, 42, .08), rgba(255, 255, 255, .94));
    border: 1px solid rgba(151, 20, 42, .13);
    border-radius: 18px;
    padding: 1rem 1.15rem;
  }
  .overtime-kpi-card {
    position: relative;
    height: 100%;
    overflow: hidden;
    border: 1px solid #ede4e1;
    border-radius: 16px;
    background: linear-gradient(145deg, #fff, #fff8f5);
    padding: 1rem;
  }
  .overtime-kpi-card::after {
    position: absolute;
    top: -1.75rem;
    right: -1.75rem;
    width: 5rem;
    height: 5rem;
    border-radius: 50%;
    background: rgba(151, 20, 42, .06);
    content: '';
  }
  .overtime-kpi-card.is-hours::after { background: rgba(35, 116, 103, .08); }
  .overtime-kpi-card.is-amount::after { background: rgba(185, 126, 22, .09); }
  .overtime-kpi-card.is-review::after { background: rgba(75, 74, 137, .08); }
  .overtime-kpi-top { position: relative; z-index: 1; display: flex; align-items: center; gap: .6rem; }
  .overtime-kpi-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 10px;
    background: #f8e7e8;
    color: #981c32;
  }
  .is-hours .overtime-kpi-icon { background: #e5f3ef; color: #187461; }
  .is-amount .overtime-kpi-icon { background: #fff1d8; color: #a76300; }
  .is-review .overtime-kpi-icon { background: #eeecfb; color: #58548e; }
  .overtime-kpi-label {
    color: #92746a;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .055em;
    text-transform: uppercase;
  }
  .overtime-kpi-value { color: #51212c; font-size: 1.2rem; font-weight: 800; line-height: 1.2; }
  .overtime-kpi-note { color: #937d76; font-size: .78rem; }
  .overtime-kpi-status { display: flex; flex-wrap: wrap; gap: .3rem .5rem; margin-top: .45rem; }
  .overtime-kpi-status span { color: #78625c; font-size: .72rem; white-space: nowrap; }
  .overtime-kpi-status b { color: #51212c; }
  .overtime-filter-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    align-items: end;
  }
  .overtime-filter-field { flex: 1 1 150px; min-width: 0; }
  .overtime-filter-search { flex: 1.8 1 270px; }
  .overtime-filter-employee { flex: 1.35 1 235px; }
  .overtime-filter-grid .form-label { color: #593c40; font-size: .76rem; font-weight: 800; letter-spacing: .025em; text-transform: uppercase; white-space: nowrap; }
  .overtime-filter-grid .form-control,
  .overtime-filter-grid .form-select { min-width: 0; min-height: 42px; }
  .overtime-filter-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    flex: 0 0 226px;
    gap: .5rem;
    min-width: 226px;
  }
  .overtime-filter-actions .btn { min-width: 0; white-space: nowrap; }
  @media (max-width: 767.98px) {
    .overtime-filter-field { flex-basis: 100%; }
    .overtime-filter-actions { flex: 1 1 100%; min-width: 0; }
  }
  .overtime-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 1rem 3rem rgba(49, 23, 30, .22);
  }
  .overtime-modal .modal-header {
    background: linear-gradient(125deg, #8f182d, #b33342);
    color: #fff;
    border: 0;
    padding: 1rem 1.25rem;
  }
  .overtime-modal .btn-close { filter: invert(1) grayscale(1) brightness(3); }
  .overtime-modal .form-label {
    color: #593c40;
    font-size: .76rem;
    font-weight: 800;
    letter-spacing: .025em;
    text-transform: uppercase;
  }
  .overtime-modal .form-control,
  .overtime-modal .form-select { min-height: 43px; }
  .overtime-calculation {
    background: linear-gradient(135deg, #fff7f4, #fdf0ec);
    border: 1px solid #efd7ce;
    border-radius: 13px;
  }
  .overtime-calculation .ot-label {
    color: #9b756a;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .055em;
    text-transform: uppercase;
  }
  .overtime-calculation .ot-value { color: #8f182d; font-weight: 800; }
</style>

<div class="overtime-entry-header d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="text-primary"><i class="ri-time-line fs-5"></i></span>
      <h4 class="mb-0"><?php echo html_escape($title ?? 'Input Lembur Manual'); ?></h4>
    </div>
    <small class="text-muted">Catat lembur dari satu modal ringkas. Tarif selalu diambil dari Master Standar Lembur saat disimpan.</small>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="<?php echo site_url('master/att-overtime-standard'); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri-settings-3-line me-1"></i>Master Standar Lembur
    </a>
    <button type="button" class="btn btn-primary" data-overtime-create>
      <i class="ri-add-line me-1"></i>Tambah Lembur
    </button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xxl-3 col-md-6">
    <div class="overtime-kpi-card">
      <div class="overtime-kpi-top"><span class="overtime-kpi-icon"><i class="ri-file-list-3-line"></i></span><span class="overtime-kpi-label">Total Hasil Filter</span></div>
      <div class="overtime-kpi-value mt-3"><?php echo number_format($summaryTotalEntries, 0, ',', '.'); ?> entri</div>
      <div class="overtime-kpi-note mt-1"><?php echo html_escape(date('d/m/Y', strtotime((string)($filters['date_start'] ?? date('Y-m-01'))))); ?> - <?php echo html_escape(date('d/m/Y', strtotime((string)($filters['date_end'] ?? date('Y-m-t'))))); ?></div>
    </div>
  </div>
  <div class="col-xxl-3 col-md-6">
    <div class="overtime-kpi-card is-hours">
      <div class="overtime-kpi-top"><span class="overtime-kpi-icon"><i class="ri-time-line"></i></span><span class="overtime-kpi-label">Akumulasi Jam Lembur</span></div>
      <div class="overtime-kpi-value mt-3"><?php echo number_format($summaryTotalHours, 2, ',', '.'); ?> jam</div>
      <div class="overtime-kpi-note mt-1">Seluruh durasi dari data sesuai filter.</div>
    </div>
  </div>
  <div class="col-xxl-3 col-md-6">
    <div class="overtime-kpi-card is-amount">
      <div class="overtime-kpi-top"><span class="overtime-kpi-icon"><i class="ri-money-dollar-circle-line"></i></span><span class="overtime-kpi-label">Nilai Pengajuan</span></div>
      <div class="overtime-kpi-value mt-3">Rp <?php echo number_format($summaryTotalAmount, 0, ',', '.'); ?></div>
      <div class="overtime-kpi-note mt-1">Termasuk entri yang masih menunggu keputusan.</div>
    </div>
  </div>
  <div class="col-xxl-3 col-md-6">
    <div class="overtime-kpi-card is-review">
      <div class="overtime-kpi-top"><span class="overtime-kpi-icon"><i class="ri-checkbox-circle-line"></i></span><span class="overtime-kpi-label">Persetujuan</span></div>
      <div class="overtime-kpi-value mt-3">Rp <?php echo number_format($summaryApprovedAmount, 0, ',', '.'); ?></div>
      <div class="overtime-kpi-note mt-1">Nilai lembur yang sudah disetujui.</div>
      <div class="overtime-kpi-status">
        <span>Pending <b><?php echo number_format($summaryPendingEntries, 0, ',', '.'); ?></b></span>
        <span>Disetujui <b><?php echo number_format($summaryApprovedEntries, 0, ',', '.'); ?></b></span>
        <span>Ditolak <b><?php echo number_format($summaryRejectedEntries, 0, ',', '.'); ?></b></span>
        <?php if ($summaryCancelledEntries > 0): ?><span>Dibatalkan <b><?php echo number_format($summaryCancelledEntries, 0, ',', '.'); ?></b></span><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><strong>Filter Lembur</strong><div class="small text-muted">Atur periode, pegawai, atau status. Ringkasan di atas mengikuti filter ini.</div></div>
    <span class="badge bg-light text-dark border"><i class="ri-filter-3-line me-1"></i>Filter aktif</span>
  </div>
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/overtime-entries'); ?>" class="overtime-filter-grid">
      <div class="overtime-filter-field overtime-filter-search"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama, NIP, divisi, catatan"></div>
      <div class="overtime-filter-field"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua divisi</option><?php foreach ($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
      <div class="overtime-filter-field overtime-filter-employee"><label class="form-label mb-1">Pegawai</label><select name="employee_id" class="form-select"><option value="">Semua pegawai</option><?php foreach ($employeeOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
      <div class="overtime-filter-field"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua status</option><?php foreach ($statusOptions as $s): ?><option value="<?php echo html_escape((string)$s); ?>" <?php echo (($filters['status'] ?? '') === $s) ? 'selected' : ''; ?>><?php echo html_escape((string)$s); ?></option><?php endforeach; ?></select></div>
      <div class="overtime-filter-field"><label class="form-label mb-1">Dari Tanggal</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="overtime-filter-field"><label class="form-label mb-1">Sampai Tanggal</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="overtime-filter-field"><label class="form-label mb-1">Per Halaman</label><select name="per_page" class="form-select"><?php foreach ([10, 25, 50, 100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="overtime-filter-actions"><button class="btn btn-primary" type="submit"><i class="ri-filter-3-line me-1"></i>Terapkan</button><a href="<?php echo site_url('attendance/overtime-entries'); ?>" class="btn btn-outline-secondary">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Daftar Lembur</strong>
    <span class="text-muted small">Klik ikon pensil untuk mengubah entri.</span>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Pegawai</th>
          <th>Waktu Lembur</th>
          <th class="text-end">Jam</th>
          <th>Standar</th>
          <th class="text-end">Tarif</th>
          <th class="text-end">Total</th>
          <th>Status</th>
          <th>Catatan</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data lembur.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <?php
            $status = strtoupper((string)($r['status'] ?? 'PENDING'));
            $statusClass = $status === 'APPROVED' ? 'success' : ($status === 'REJECTED' ? 'danger' : 'warning');
            $startTs = !empty($r['start_at']) ? strtotime((string)$r['start_at']) : 0;
            $endTs = !empty($r['end_at']) ? strtotime((string)$r['end_at']) : 0;
            $startTime = $startTs ? date('H:i', $startTs) : '';
            $endTime = $endTs ? date('H:i', $endTs) : '';
          ?>
          <tr>
            <td><?php echo html_escape((string)($r['overtime_date'] ?? '-')); ?></td>
            <td>
              <div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div>
              <small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></small>
            </td>
            <td>
              <?php echo html_escape($startTs ? date('d/m/Y H:i', $startTs) : '-'); ?>
              <div class="text-muted small">s/d <?php echo html_escape($endTs ? date('d/m/Y H:i', $endTs) : '-'); ?></div>
            </td>
            <td class="text-end"><?php echo number_format((float)($r['overtime_hours'] ?? 0), 2, ',', '.'); ?></td>
            <td><?php echo html_escape(trim((string)($r['overtime_standard_name'] ?? '')) ?: '-'); ?></td>
            <td class="text-end"><?php echo number_format((float)($r['overtime_rate'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format((float)($r['total_overtime_pay'] ?? 0), 2, ',', '.'); ?></td>
            <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo html_escape($status); ?></span></td>
            <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
            <td class="action-cell text-center">
              <button
                type="button"
                class="btn btn-sm btn-outline-primary action-icon-btn"
                data-bs-toggle="tooltip"
                title="Edit"
                aria-label="Edit"
                data-overtime-edit
                data-id="<?php echo (int)$r['id']; ?>"
                data-employee-id="<?php echo (int)($r['employee_id'] ?? 0); ?>"
                data-overtime-date="<?php echo html_escape((string)($r['overtime_date'] ?? '')); ?>"
                data-start-time="<?php echo html_escape($startTime); ?>"
                data-end-time="<?php echo html_escape($endTime); ?>"
                data-overtime-standard-id="<?php echo (int)($r['overtime_standard_id'] ?? 0); ?>"
                data-status="<?php echo html_escape($status); ?>"
                data-notes="<?php echo html_escape((string)($r['notes'] ?? '')); ?>"
              ><i class="ri ri-edit-line"></i></button>
              <form method="post" action="<?php echo site_url('attendance/overtime-entries/delete/' . (int)$r['id']); ?>" class="d-inline" data-confirm="Hapus data lembur ini?">
                <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" data-loading-label="Menghapus..."><i class="ri ri-delete-bin-line"></i></button>
              </form>
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
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('attendance/overtime-entries?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/overtime-entries?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('attendance/overtime-entries?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade overtime-modal" id="overtimeEntryModal" tabindex="-1" aria-labelledby="overtimeEntryModalTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="overtimeEntryModalTitle">Tambah Lembur</h5>
          <div class="small text-white-50" id="overtimeEntryModalSubtitle">Pilih pegawai, atur waktu, lalu sistem menghitung estimasinya.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <form method="post" action="<?php echo site_url('attendance/overtime-entries/store'); ?>" id="overtimeForm" data-create-url="<?php echo site_url('attendance/overtime-entries/store'); ?>" data-update-url="<?php echo site_url('attendance/overtime-entries/update'); ?>" data-default-date="<?php echo html_escape(date('Y-m-d')); ?>">
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label mb-1">Pegawai</label>
              <select name="employee_id" class="form-select" required>
                <option value="">Pilih pegawai</option>
                <?php foreach ($employeeOptions as $o): ?>
                <option value="<?php echo (int)$o['value']; ?>"><?php echo html_escape((string)$o['label']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label mb-1">Status</label>
              <select name="status" class="form-select">
                <?php foreach ($statusOptions as $s): ?>
                <option value="<?php echo html_escape((string)$s); ?>"><?php echo html_escape((string)$s); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Tanggal Lembur</label>
              <input type="date" name="overtime_date" class="form-control" required value="<?php echo html_escape(date('Y-m-d')); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Jam Mulai</label>
              <input type="time" name="start_time" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Jam Selesai</label>
              <input type="time" name="end_time" class="form-control" required>
            </div>
            <div class="col-md-8">
              <label class="form-label mb-1">Standar Lembur per Jam</label>
              <select name="overtime_standard_id" class="form-select" required>
                <option value="">Pilih standar lembur</option>
                <?php foreach ($overtimeStandardOptions as $st): ?>
                <option value="<?php echo (int)$st['value']; ?>" data-rate="<?php echo html_escape((string)$st['hourly_rate']); ?>">
                  <?php echo html_escape((string)$st['standard_code'] . ' - ' . (string)$st['standard_name'] . ' (Rp ' . number_format((float)$st['hourly_rate'], 2, ',', '.') . '/jam)'); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Tarif dari Master</label>
              <input type="text" id="overtimeRateDisplay" class="form-control" value="Rp0" readonly tabindex="-1">
            </div>
            <div class="col-12">
              <div class="overtime-calculation px-3 py-2">
                <div class="row align-items-center g-2">
                  <div class="col-sm-4">
                    <div class="ot-label">Estimasi Durasi</div>
                    <div class="ot-value" id="otHoursPreview">0,00 jam</div>
                  </div>
                  <div class="col-sm-4">
                    <div class="ot-label">Estimasi Total</div>
                    <div class="ot-value" id="otTotalPreview">Rp0</div>
                  </div>
                  <div class="col-sm-4 text-sm-end">
                    <small class="text-muted d-none" id="otOvernightHint">Jam selesai yang lebih awal dihitung sebagai hari berikutnya.</small>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label mb-1">Catatan</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: bantu closing event, persiapan stok, atau pengganti shift."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="overtimeSubmitButton" data-loading-label="Menyimpan..."><i class="ri-save-line me-1"></i>Simpan Lembur</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  function init() {
  var modalEl = document.getElementById('overtimeEntryModal');
  var form = document.getElementById('overtimeForm');
  if (!form || !modalEl) return;

  var dateEl = form.querySelector('[name="overtime_date"]');
  var startEl = form.querySelector('[name="start_time"]');
  var endEl = form.querySelector('[name="end_time"]');
  var standardEl = form.querySelector('[name="overtime_standard_id"]');
  var rateEl = document.getElementById('overtimeRateDisplay');
  var selectedRate = 0;
  var previewEl = document.getElementById('otHoursPreview');
  var totalPreviewEl = document.getElementById('otTotalPreview');
  var overnightHintEl = document.getElementById('otOvernightHint');
  var modalTitleEl = document.getElementById('overtimeEntryModalTitle');
  var modalSubtitleEl = document.getElementById('overtimeEntryModalSubtitle');
  var submitButton = document.getElementById('overtimeSubmitButton');
  var createButton = document.querySelector('[data-overtime-create]');
  var initialEditPayload = <?php echo json_encode($initialEditPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  function formatCurrency(value) {
    return 'Rp' + Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function setField(name, value) {
    var field = form.querySelector('[name="' + name + '"]');
    if (field) field.value = value === null || value === undefined ? '' : String(value);
  }

  function setRateDisplay(value) {
    selectedRate = Math.max(0, Number(value || 0));
    if (rateEl) rateEl.value = formatCurrency(selectedRate) + ' / jam';
  }

  function setModalMode(entry) {
    var isEdit = entry && Number(entry.id || 0) > 0;
    form.action = isEdit ? form.dataset.updateUrl + '/' + Number(entry.id) : form.dataset.createUrl;
    if (modalTitleEl) modalTitleEl.textContent = isEdit ? 'Edit Lembur' : 'Tambah Lembur';
    if (modalSubtitleEl) modalSubtitleEl.textContent = isEdit ? 'Perbarui waktu atau standar. Nilai lembur dihitung ulang saat disimpan.' : 'Pilih pegawai, atur waktu, lalu sistem menghitung estimasinya.';
    if (submitButton) submitButton.innerHTML = '<i class="ri-save-line me-1"></i>' + (isEdit ? 'Simpan Perubahan' : 'Simpan Lembur');
  }

  function recalcHours() {
    if (!dateEl || !startEl || !endEl || !previewEl) return;
    var date = dateEl.value || '';
    var start = startEl.value || '';
    var end = endEl.value || '';
    if (!date || !start || !end) {
      previewEl.textContent = '0,00 jam';
      if (totalPreviewEl) totalPreviewEl.textContent = 'Rp0';
      if (overnightHintEl) overnightHintEl.classList.add('d-none');
      return;
    }

    var startTs = Date.parse(date + 'T' + start + ':00');
    var endTs = Date.parse(date + 'T' + end + ':00');
    if (!Number.isFinite(startTs) || !Number.isFinite(endTs)) {
      previewEl.textContent = '0,00 jam';
      if (totalPreviewEl) totalPreviewEl.textContent = 'Rp0';
      if (overnightHintEl) overnightHintEl.classList.add('d-none');
      return;
    }

    var crossesMidnight = endTs <= startTs;
    if (crossesMidnight) endTs += 24 * 60 * 60 * 1000;
    var hours = Math.max(0, (endTs - startTs) / 3600000);
    previewEl.textContent = hours.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' jam';
    if (totalPreviewEl) totalPreviewEl.textContent = formatCurrency(hours * selectedRate);
    if (overnightHintEl) overnightHintEl.classList.toggle('d-none', !crossesMidnight);
  }

  function syncRateFromStandard() {
    if (!standardEl) return;
    var option = standardEl.options[standardEl.selectedIndex];
    setRateDisplay(option ? (option.getAttribute('data-rate') || '0') : 0);
    recalcHours();
  }

  function resetForCreate() {
    form.reset();
    setModalMode(null);
    setField('overtime_date', form.dataset.defaultDate || '');
    setField('status', 'APPROVED');
    setRateDisplay(0);
    recalcHours();
  }

  function fillForEdit(entry) {
    form.reset();
    setModalMode(entry);
    setField('employee_id', entry.employee_id || '');
    setField('overtime_date', entry.overtime_date || '');
    setField('start_time', entry.start_time || '');
    setField('end_time', entry.end_time || '');
    setField('overtime_standard_id', entry.overtime_standard_id || '');
    setField('status', entry.status || 'PENDING');
    setField('notes', entry.notes || '');
    syncRateFromStandard();
    if (!standardEl || !standardEl.value) {
      setRateDisplay(0);
      recalcHours();
    }
  }

  function showModal() {
    if (!window.bootstrap || !window.bootstrap.Modal) {
      window.alert('Komponen modal belum dimuat. Muat ulang halaman lalu coba lagi.');
      return false;
    }
    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    return true;
  }

  ['change', 'keyup'].forEach(function (eventName) {
    if (dateEl) dateEl.addEventListener(eventName, recalcHours);
    if (startEl) startEl.addEventListener(eventName, recalcHours);
    if (endEl) endEl.addEventListener(eventName, recalcHours);
  });
  if (standardEl) standardEl.addEventListener('change', syncRateFromStandard);

  if (createButton) {
    createButton.addEventListener('click', function () {
      resetForCreate();
      showModal();
    });
  }

  document.querySelectorAll('[data-overtime-edit]').forEach(function (button) {
    button.addEventListener('click', function () {
      fillForEdit({
        id: button.dataset.id,
        employee_id: button.dataset.employeeId,
        overtime_date: button.dataset.overtimeDate,
        start_time: button.dataset.startTime,
        end_time: button.dataset.endTime,
        overtime_standard_id: button.dataset.overtimeStandardId,
        status: button.dataset.status,
        notes: button.dataset.notes
      });
      showModal();
    });
  });

  if (initialEditPayload && Number(initialEditPayload.id || 0) > 0) {
    fillForEdit(initialEditPayload);
    showModal();
  } else {
    resetForCreate();
  }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
</script>
