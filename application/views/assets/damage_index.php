<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'damage']);
$filters = $filters ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$rows = $rows ?? [];
$eventTypeLabels = $event_type_labels ?? [];
$fmtMoney = static function ($value): string {
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$eventBadge = static function ($type): string {
  $type = strtoupper((string)$type);
  $map = [
    'DAMAGE' => 'danger',
    'REPAIR' => 'warning',
    'LOST' => 'dark',
    'RETIRED' => 'secondary',
    'DISPOSED' => 'secondary',
  ];
  return $map[$type] ?? 'secondary';
};
$statusBadge = static function ($status): string {
  $status = strtoupper((string)$status);
  $map = [
    'ACTIVE' => 'success',
    'BROKEN' => 'danger',
    'REPAIR' => 'warning',
    'LOST' => 'dark',
    'RETIRED' => 'secondary',
    'DISPOSED' => 'secondary',
  ];
  return $map[$status] ?? 'secondary';
};
?>
<style>
.asset-report-thumb{width:50px;height:50px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
.asset-report-empty{width:50px;height:50px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-report-table{table-layout:fixed}
.asset-report-table th{font-size:.76rem;text-transform:uppercase;color:#77665c;letter-spacing:.02em;white-space:nowrap}
.asset-report-table td{vertical-align:middle}
.asset-report-reason{max-width:360px;white-space:normal}
.asset-report-actions{display:inline-flex;justify-content:flex-end;gap:.35rem;min-width:142px}
.asset-report-actions .btn{width:32px;height:32px;padding:0;display:inline-grid;place-items:center}
@media (max-width:991.98px){.asset-report-table{table-layout:auto}.asset-report-actions{width:100%;justify-content:flex-start!important}.asset-report-table td{white-space:normal}}
</style>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1">Laporan Kerusakan Aset</h4>
    <div class="text-muted">Daftar kejadian aset rusak, hilang, perbaikan, pensiun, atau dibuang lengkap dengan bukti dan audit trail.</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('asset-management') ?>" class="btn btn-outline-secondary">
      <i class="ri ri-list-check-2 me-1"></i>Daftar Aset
    </a>
    <?php if (!empty($can_create)): ?>
      <a href="<?= site_url('asset-management/damage/create') ?>" class="btn btn-danger">
        <i class="ri ri-add-line me-1"></i>Tambah Laporan
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?= site_url('asset-management/damage') ?>">
      <div class="col-12 col-lg-2">
        <label class="form-label">Cari laporan</label>
        <input type="text" class="form-control" name="q" value="<?= html_escape($filters['q'] ?? '') ?>" placeholder="Kode, nama, serial, alasan, lokasi">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Dari tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?= html_escape($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Sampai tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?= html_escape($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-6 col-lg-1">
        <label class="form-label">Jenis</label>
        <select class="form-select" name="event_type">
          <option value="ALL">Semua</option>
          <?php foreach ($eventTypeLabels as $key => $label): ?>
            <option value="<?= html_escape($key) ?>" <?= (($filters['event_type'] ?? '') === $key) ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Kategori</label>
        <select class="form-select" name="category_id">
          <option value="0">Semua</option>
          <?php foreach (($categories ?? []) as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= (int)($filters['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= html_escape($cat['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Divisi</label>
        <select class="form-select" name="division_id">
          <option value="0">Semua</option>
          <?php foreach (($divisions ?? []) as $div): ?>
            <option value="<?= (int)$div['id'] ?>" <?= (int)($filters['division_id'] ?? 0) === (int)$div['id'] ? 'selected' : '' ?>><?= html_escape($div['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-1">
        <label class="form-label">Baris</label>
        <select class="form-select" name="per_page">
          <?php foreach ([10,25,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= (int)($pg['per_page'] ?? 25) === $pp ? 'selected' : '' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-3 ms-lg-auto asset-filter-actions">
        <button class="btn btn-outline-primary" type="submit" title="Terapkan filter" aria-label="Terapkan filter"><i class="ri ri-search-line" aria-hidden="true"></i></button>
        <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/damage') ?>" title="Bersihkan filter" aria-label="Bersihkan filter"><i class="ri ri-refresh-line" aria-hidden="true"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover asset-report-table mb-0">
      <colgroup>
        <col style="width:10%">
        <col style="width:23%">
        <col style="width:17%">
        <col style="width:21%">
        <col style="width:8%">
        <col style="width:9%">
        <col style="width:12%">
      </colgroup>
      <thead class="table-light">
        <tr>
          <th>Tanggal</th>
          <th>Aset</th>
          <th>Kejadian</th>
          <th>Dampak</th>
          <th>Bukti</th>
          <th>Dicatat</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-5">Belum ada laporan pada filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php $photo = trim((string)($row['photo_path'] ?? '')); ?>
          <tr>
            <td style="min-width:112px">
              <div class="fw-semibold"><?= html_escape($row['event_date'] ?? '-') ?></div>
              <div class="small text-muted"><?= html_escape(substr((string)($row['event_created_at'] ?? ''), 11, 5) ?: '-') ?></div>
            </td>
            <td style="min-width:290px">
              <div class="d-flex gap-2 align-items-center">
                <?php if ($photo !== ''): ?>
                  <img class="asset-report-thumb" src="<?= html_escape(base_url($photo)) ?>" alt="<?= html_escape($row['asset_name'] ?? '') ?>">
                <?php else: ?>
                  <span class="asset-report-empty"><i class="ri ri-file-text-line"></i></span>
                <?php endif; ?>
                <div>
                  <div class="fw-semibold"><?= html_escape($row['asset_name'] ?? '-') ?></div>
                  <div class="small text-muted"><?= html_escape($row['asset_code'] ?? '-') ?><?= !empty($row['serial_no']) ? ' | SN ' . html_escape($row['serial_no']) : '' ?></div>
                  <div class="small text-muted"><?= html_escape(trim((string)($row['category_name'] ?? '') . ' / ' . (string)($row['division_name'] ?? ''), ' /') ?: '-') ?></div>
                </div>
              </div>
            </td>
            <td style="min-width:220px">
              <span class="badge bg-<?= $eventBadge($row['event_type'] ?? '') ?>"><?= html_escape($row['event_type_label'] ?? '-') ?></span>
              <div class="small mt-2">
                <span class="badge bg-label-secondary"><?= html_escape($row['event_from_status_label'] ?? '-') ?></span>
                <i class="ri ri-arrow-right-s-line mx-1 text-muted"></i>
                <span class="badge bg-<?= $statusBadge($row['event_to_status'] ?? '') ?>"><?= html_escape($row['event_to_status_label'] ?? '-') ?></span>
              </div>
              <div class="small text-muted mt-1"><?= html_escape($row['current_location'] ?: ($row['outlet_name'] ?? '-')) ?></div>
            </td>
            <td class="asset-report-reason">
              <div class="fw-semibold"><?= $fmtMoney($row['amount'] ?? 0) ?></div>
              <div class="small text-muted">Kondisi <?= (int)($row['condition_score_before'] ?? 0) ?>% -> <?= (int)($row['condition_score_after'] ?? 0) ?>%</div>
              <div class="mt-1"><?= nl2br(html_escape($row['reason'] ?? '-')) ?></div>
            </td>
            <td>
              <?php if (!empty($row['evidence_path'])): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= html_escape(base_url($row['evidence_path'])) ?>" target="_blank" rel="noopener">
                  <i class="ri ri-file-text-line me-1"></i>Lihat
                </a>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td>
              <div><?= html_escape($row['created_by_name'] ?? '-') ?></div>
              <div class="small text-muted"><?= html_escape($row['event_created_at'] ?? '-') ?></div>
            </td>
            <td class="text-end">
              <div class="asset-report-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('asset-management/detail/' . (int)$row['asset_id']) ?>" title="Detail aset"><i class="ri ri-eye-line"></i></a>
                <?php if (!empty($can_create)): ?><a class="btn btn-sm btn-outline-danger" href="<?= site_url('asset-management/damage/' . (int)$row['asset_id']) ?>" title="Lapor lagi"><i class="ri ri-alert-line"></i></a><?php endif; ?>
                <?php if (!empty($can_edit)): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('asset-management/damage/edit/' . (int)$row['event_id']) ?>" title="Edit laporan"><i class="ri ri-edit-line"></i></a><?php endif; ?>
                <?php if (!empty($can_delete)): ?><a class="btn btn-sm btn-outline-danger" href="<?= site_url('asset-management/damage/delete/' . (int)$row['event_id']) ?>" title="Hapus laporan" onclick="return confirm('Hapus laporan ini? Status aset akan dikembalikan ke kondisi sebelum laporan jika laporan ini adalah event terakhir.')"><i class="ri ri-delete-bin-line"></i></a><?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="text-muted small">Total <?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?> laporan</span>
    <div class="btn-group">
      <?php
        $query = $_GET;
        $prev = max(1, (int)$pg['page'] - 1);
        $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1);
        $query['page'] = $prev;
      ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] <= 1 ? 'disabled' : '' ?>" href="<?= site_url('asset-management/damage?' . http_build_query($query)) ?>">Prev</a>
      <button class="btn btn-sm btn-outline-secondary" type="button" disabled>Page <?= (int)$pg['page'] ?>/<?= (int)$pg['total_pages'] ?></button>
      <?php $query['page'] = $next; ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] >= (int)$pg['total_pages'] ? 'disabled' : '' ?>" href="<?= site_url('asset-management/damage?' . http_build_query($query)) ?>">Next</a>
    </div>
  </div>
</div>
