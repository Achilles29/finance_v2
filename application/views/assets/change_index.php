<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'changes']);
$filters = $filters ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$rows = $rows ?? [];
$statusLabels = $status_labels ?? [];
$statusClass = static function (string $status): string {
    return [
        'PENDING' => 'warning',
        'APPROVED' => 'primary',
        'REJECTED' => 'danger',
        'POSTED' => 'success',
        'CANCELLED' => 'secondary',
    ][strtoupper($status)] ?? 'secondary';
};
?>
<style>
.asset-change-table{table-layout:fixed}
.asset-change-table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}
.asset-change-table td{vertical-align:middle}
.asset-change-summary{max-width:280px;white-space:normal}
@media (max-width:991.98px){.asset-change-table{table-layout:auto}.asset-change-table td{white-space:normal}}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h4 class="mb-1">Perubahan Data Aset</h4>
    <div class="text-muted">Riwayat pengajuan perubahan data awal aset yang sudah dikunci.</div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= site_url('asset-management') ?>"><i class="ri ri-archive-2-line me-1"></i>Daftar Aset</a>
</div>

<?php if (empty($master_lock_ready)): ?>
  <div class="alert alert-warning">Fitur pengajuan perubahan belum siap. Jalankan SQL <code>2026-08-24b_asset_master_lock_and_change_request_foundation.sql</code>.</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?= site_url('asset-management/changes') ?>">
      <div class="col-12 col-lg-3">
        <label class="form-label">Cari pengajuan atau aset</label>
        <input type="search" name="q" class="form-control" value="<?= html_escape($filters['q'] ?? '') ?>" placeholder="Nomor pengajuan, kode aset, nama aset, alasan">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="ALL">Semua</option>
          <?php foreach ($statusLabels as $key => $label): ?><option value="<?= html_escape($key) ?>" <?= strtoupper((string)($filters['status'] ?? 'ALL')) === $key ? 'selected' : '' ?>><?= html_escape($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2"><label class="form-label">Dari tanggal</label><input type="date" name="date_from" class="form-control" value="<?= html_escape($filters['date_from'] ?? '') ?>"></div>
      <div class="col-6 col-lg-2"><label class="form-label">Sampai tanggal</label><input type="date" name="date_to" class="form-control" value="<?= html_escape($filters['date_to'] ?? '') ?>"></div>
      <div class="col-6 col-lg-1"><label class="form-label">Baris</label><select name="per_page" class="form-select"><?php foreach ([10,25,50,100] as $size): ?><option value="<?= $size ?>" <?= (int)($pg['per_page'] ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
      <div class="col-12 col-lg-2 asset-filter-actions">
        <button type="submit" class="btn btn-outline-primary" title="Terapkan filter" aria-label="Terapkan filter"><i class="ri ri-search-line" aria-hidden="true"></i></button>
        <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/changes') ?>" title="Bersihkan filter" aria-label="Bersihkan filter"><i class="ri ri-refresh-line" aria-hidden="true"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover asset-change-table mb-0">
      <colgroup><col style="width:15%"><col style="width:24%"><col style="width:13%"><col style="width:15%"><col style="width:19%"><col style="width:8%"><col style="width:6%"></colgroup>
      <thead class="table-light"><tr><th>Pengajuan</th><th>Aset</th><th>Divisi</th><th>Pemohon</th><th>Perubahan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-5">Belum ada pengajuan perubahan pada filter ini.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><div class="fw-semibold"><?= html_escape($row['request_no'] ?? '-') ?></div><div class="small text-muted"><?= html_escape($row['created_at'] ?? '-') ?></div></td>
            <td><div class="fw-semibold"><?= html_escape($row['asset_code'] ?? '-') ?></div><div class="small text-muted"><?= html_escape($row['asset_name'] ?? '-') ?></div></td>
            <td><?= html_escape($row['division_name'] ?? '-') ?></td>
            <td><?= html_escape($row['requested_by_name'] ?? '-') ?></td>
            <td class="asset-change-summary"><div><?= html_escape($row['change_summary'] ?? '-') ?></div><div class="small text-muted"><?= (int)($row['change_count'] ?? 0) ?> data berubah</div></td>
            <td><span class="badge bg-<?= $statusClass((string)($row['status'] ?? '')) ?>"><?= html_escape($row['status_label'] ?? '-') ?></span></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('asset-management/changes/' . (int)$row['id']) ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="small text-muted">Total <?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?> pengajuan</span>
    <div class="btn-group">
      <?php $query = $_GET; $query['page'] = max(1, (int)$pg['page'] - 1); ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] <= 1 ? 'disabled' : '' ?>" href="<?= site_url('asset-management/changes?' . http_build_query($query)) ?>">Prev</a>
      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Page <?= (int)$pg['page'] ?>/<?= (int)$pg['total_pages'] ?></button>
      <?php $query['page'] = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] >= (int)$pg['total_pages'] ? 'disabled' : '' ?>" href="<?= site_url('asset-management/changes?' . http_build_query($query)) ?>">Next</a>
    </div>
  </div>
</div>
