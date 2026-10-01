<?php
$this->load->view('assets/_nav', ['asset_nav_active' => $asset_nav_active ?? '']);
$type = strtoupper((string)($type ?? ''));
$config = $config ?? [];
$filters = $filters ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$rows = $rows ?? [];
$statusLabels = $status_labels ?? [];
$priorityLabels = $priority_labels ?? [];
$fmtMoney = static function ($value): string {
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$statusBadge = static function ($status): string {
  $status = strtoupper((string)$status);
  return [
    'PENDING' => 'warning',
    'APPROVED' => 'primary',
    'REJECTED' => 'danger',
    'POSTED' => 'success',
    'DONE' => 'success',
    'CANCELLED' => 'secondary',
  ][$status] ?? 'secondary';
};
$priorityBadge = static function ($priority): string {
  $priority = strtoupper((string)$priority);
  return ['LOW' => 'secondary', 'NORMAL' => 'primary', 'HIGH' => 'warning', 'URGENT' => 'danger'][$priority] ?? 'secondary';
};
$detailText = static function (array $row, string $type, callable $fmtMoney, array $priorityLabels): string {
  if ($type === 'TRANSFER') {
    $from = trim((string)($row['from_division_name'] ?? '') . ' / ' . (string)($row['from_location'] ?? ''), ' /');
    $to = trim((string)($row['to_division_name'] ?? '') . ' / ' . (string)($row['to_location'] ?? ''), ' /');
    return 'Dari ' . ($from ?: '-') . ' ke ' . ($to ?: '-');
  }
  if ($type === 'HANDOVER') {
    return 'Dari ' . ((string)($row['from_employee_name'] ?? '') ?: '-') . ' ke ' . ((string)($row['to_employee_name'] ?? '') ?: '-');
  }
  if ($type === 'MAINTENANCE') {
    $priority = strtoupper((string)($row['priority'] ?? 'NORMAL'));
    return trim((string)($row['maintenance_type'] ?? 'Maintenance') . ' | ' . ($priorityLabels[$priority] ?? $priority) . ' | Estimasi ' . $fmtMoney($row['estimated_cost'] ?? 0), ' |');
  }
  if ($type === 'DISPOSAL') {
    return (string)($row['disposal_type'] ?? 'DISPOSAL') . ' | Nilai realisasi ' . $fmtMoney($row['disposal_value'] ?? 0);
  }
  return '-';
};
?>
<style>
.asset-work-title{display:flex;align-items:center;gap:.6rem}
.asset-title-icon{width:36px;height:36px;flex:0 0 36px;border-radius:8px;display:grid;place-items:center;background:#f3faf7;color:#18745c}
.asset-title-icon i{font-size:1.1rem;line-height:1}
.asset-panel{border:1px solid #e7d8ce;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(35,24,18,.04)}
.asset-table-scroll{max-height:610px;overflow:auto}
.asset-work-table{table-layout:fixed;min-width:1160px}
.asset-work-table thead th{position:sticky;top:0;z-index:2;background:#f8f4f0}
.asset-work-table th{font-size:.75rem;text-transform:uppercase;color:#77665c;letter-spacing:.02em;white-space:nowrap}
.asset-work-table td{vertical-align:middle}
.asset-thumb{width:50px;height:50px;min-width:50px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
.asset-empty-thumb{width:50px;height:50px;min-width:50px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-actions{display:inline-flex;justify-content:flex-end;gap:.3rem;min-width:184px;white-space:nowrap}
.asset-actions form{display:inline-flex;margin:0}
.asset-actions .btn{width:32px;height:32px;padding:0;display:grid;place-items:center}
@media (max-width: 991.98px){.asset-work-table{table-layout:auto}.asset-actions{justify-content:flex-start}.asset-work-table td{white-space:normal}}
</style>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1 asset-work-title"><span class="asset-title-icon"><i class="ri <?= html_escape($config['icon'] ?? 'ri-archive-2-line') ?>"></i></span><span><?= html_escape($config['label'] ?? 'Workflow Aset') ?></span></h4>
    <div class="text-muted">
      <?php if ($type === 'TRANSFER'): ?>Mutasi aset antar divisi, outlet, lokasi, atau PIC dengan approval sebelum data aset berubah.
      <?php elseif ($type === 'HANDOVER'): ?>Catat serah terima aset antar pegawai agar PIC terakhir selalu jelas.
      <?php elseif ($type === 'MAINTENANCE'): ?>Kelola jadwal preventive/corrective maintenance dan bukti biaya realisasi.
      <?php else: ?>Ajukan pensiun, buang, jual, atau donasi aset sebelum status aset dinonaktifkan.
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($can_create)): ?>
    <a href="<?= site_url(($config['url'] ?? 'asset-management') . '/create') ?>" class="btn btn-primary">
      <i class="ri ri-add-line me-1"></i>Tambah
    </a>
  <?php endif; ?>
</div>

<?php if (empty($extension_ready)): ?>
  <div class="alert alert-warning">Tabel ekstensi aset belum siap. Jalankan SQL <code>2026-08-09b_asset_management_extensions.sql</code>.</div>
<?php endif; ?>

<div class="asset-panel mb-3">
  <div class="p-3">
    <form class="row g-2 align-items-end" method="get" action="<?= site_url($config['url'] ?? 'asset-management') ?>">
      <div class="col-12 col-lg-3">
        <label class="form-label">Cari</label>
        <input type="search" name="q" class="form-control" value="<?= html_escape($filters['q'] ?? '') ?>" placeholder="Nomor workflow, kode/nama aset, alasan, vendor, PIC">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['ALL' => 'Semua'] + $statusLabels as $key => $label): ?>
            <option value="<?= html_escape($key) ?>" <?= (string)($filters['status'] ?? 'ALL') === (string)$key ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Dari tanggal</label>
        <input type="date" name="date_from" class="form-control" value="<?= html_escape($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Sampai tanggal</label>
        <input type="date" name="date_to" class="form-control" value="<?= html_escape($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-6 col-lg-1">
        <label class="form-label">Baris</label>
        <select name="per_page" class="form-select">
          <?php foreach ([10,25,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= (int)($pg['per_page'] ?? 25) === $pp ? 'selected' : '' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-2 asset-filter-actions">
        <button class="btn btn-outline-primary" type="submit" title="Terapkan filter" aria-label="Terapkan filter"><i class="ri ri-search-line" aria-hidden="true"></i></button>
        <a class="btn btn-outline-secondary" href="<?= site_url($config['url'] ?? 'asset-management') ?>" title="Bersihkan filter" aria-label="Bersihkan filter"><i class="ri ri-refresh-line" aria-hidden="true"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="asset-panel">
  <div class="asset-table-scroll">
    <table class="table table-hover asset-work-table mb-0">
      <colgroup>
        <col style="width:15%">
        <col style="width:26%">
        <col style="width:19%">
        <col style="width:12%">
        <col style="width:10%">
        <col style="width:8%">
        <col style="width:10%">
      </colgroup>
      <thead>
        <tr>
          <th>Workflow</th>
          <th>Aset</th>
          <th>Detail</th>
          <th>Target</th>
          <th>Pemohon</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-5">Belum ada data pada filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $id = (int)($row['id'] ?? 0);
            $status = strtoupper((string)($row['status'] ?? ''));
            $photo = trim((string)($row['photo_path'] ?? ''));
            $url = (string)($config['url'] ?? 'asset-management');
          ?>
          <tr>
            <td>
              <div class="fw-semibold text-truncate"><?= html_escape($row['workflow_no'] ?? '-') ?></div>
              <div class="small text-muted"><?= html_escape($row['workflow_date'] ?? '-') ?></div>
              <?php if (!empty($row['due_date'])): ?><div class="small text-muted">Due <?= html_escape($row['due_date']) ?></div><?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-2 align-items-center">
                <?php if ($photo !== ''): ?><img class="asset-thumb" src="<?= html_escape(base_url($photo)) ?>" alt="<?= html_escape($row['asset_name'] ?? '') ?>"><?php else: ?><span class="asset-empty-thumb"><i class="ri ri-file-text-line"></i></span><?php endif; ?>
                <div class="min-w-0">
                  <div class="fw-semibold text-truncate"><?= html_escape($row['asset_name'] ?? '-') ?></div>
                  <div class="small text-muted text-truncate"><?= html_escape($row['asset_code'] ?? '-') ?> | <?= html_escape($row['category_name'] ?? '-') ?></div>
                  <a class="small" href="<?= site_url('asset-management/detail/' . (int)($row['asset_id'] ?? 0)) ?>">Detail aset</a>
                </div>
              </div>
            </td>
            <td>
              <div class="text-truncate"><?= html_escape($detailText($row, $type, $fmtMoney, $priorityLabels)) ?></div>
              <?php if (!empty($row['reason'])): ?><div class="small text-muted text-truncate"><?= html_escape($row['reason']) ?></div><?php endif; ?>
              <?php if ($type === 'MAINTENANCE'): ?><span class="badge bg-<?= $priorityBadge($row['priority'] ?? 'NORMAL') ?> mt-1"><?= html_escape($priorityLabels[strtoupper((string)($row['priority'] ?? 'NORMAL'))] ?? ($row['priority'] ?? 'NORMAL')) ?></span><?php endif; ?>
            </td>
            <td>
              <?php if ($type === 'MAINTENANCE'): ?>
                <div class="text-truncate"><?= html_escape($row['vendor_name'] ?: 'Internal') ?></div>
                <div class="small text-muted">Aktual <?= $fmtMoney($row['actual_cost'] ?? 0) ?></div>
              <?php elseif ($type === 'DISPOSAL'): ?>
                <div class="text-truncate"><?= html_escape($row['disposal_type'] ?? '-') ?></div>
                <div class="small text-muted"><?= $fmtMoney($row['disposal_value'] ?? 0) ?></div>
              <?php else: ?>
                <div class="text-truncate"><?= html_escape($row['to_employee_name'] ?: ($row['to_outlet_name'] ?? '-')) ?></div>
                <div class="small text-muted text-truncate"><?= html_escape($row['to_division_name'] ?? '-') ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="text-truncate"><?= html_escape($row['requested_by_name'] ?? '-') ?></div>
              <div class="small text-muted text-truncate"><?= html_escape($row['approved_by_name'] ? 'OK ' . $row['approved_by_name'] : '') ?></div>
            </td>
            <td><span class="badge bg-<?= $statusBadge($status) ?>"><?= html_escape($statusLabels[$status] ?? $status) ?></span></td>
            <td class="text-end">
              <span class="asset-actions">
                <?php if ($status === 'PENDING' && !empty($can_edit)): ?>
                  <form method="post" action="<?= site_url($url . '/' . $id . '/approve') ?>"><button class="btn btn-sm btn-outline-primary" type="submit" title="Approve"><i class="ri ri-check-line"></i></button></form>
                  <form method="post" action="<?= site_url($url . '/' . $id . '/reject') ?>" onsubmit="return confirm('Tolak workflow ini?')"><button class="btn btn-sm btn-outline-danger" type="submit" title="Reject"><i class="ri ri-close-line"></i></button></form>
                <?php endif; ?>
                <?php if ($type !== 'MAINTENANCE' && $status === 'APPROVED' && !empty($can_edit)): ?>
                  <form method="post" action="<?= site_url($url . '/' . $id . '/post') ?>" onsubmit="return confirm('Posting workflow ini dan update data aset?')"><button class="btn btn-sm btn-outline-success" type="submit" title="Posting"><i class="ri ri-arrow-up-circle-line"></i></button></form>
                <?php endif; ?>
                <?php if ($type === 'MAINTENANCE' && in_array($status, ['PENDING','APPROVED'], true) && !empty($can_edit)): ?>
                  <form method="post" action="<?= site_url($url . '/' . $id . '/complete') ?>" class="asset-complete-form"><input type="hidden" name="actual_cost" value="<?= html_escape((string)($row['actual_cost'] ?? 0)) ?>"><button class="btn btn-sm btn-outline-success" type="submit" title="Selesaikan"><i class="ri ri-checkbox-circle-line"></i></button></form>
                <?php endif; ?>
                <?php if (in_array($status, ['PENDING','APPROVED'], true) && !empty($can_delete)): ?>
                  <form method="post" action="<?= site_url($url . '/' . $id . '/cancel') ?>" onsubmit="return confirm('Batalkan workflow ini?')"><button class="btn btn-sm btn-outline-secondary" type="submit" title="Cancel"><i class="ri ri-close-circle-line"></i></button></form>
                <?php endif; ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="text-muted small">Total <?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?> workflow</span>
    <div class="btn-group">
      <?php
        $query = $_GET;
        $prev = max(1, (int)$pg['page'] - 1);
        $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1);
        $query['page'] = $prev;
      ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] <= 1 ? 'disabled' : '' ?>" href="<?= site_url(($config['url'] ?? 'asset-management') . '?' . http_build_query($query)) ?>">Prev</a>
      <button class="btn btn-sm btn-outline-secondary" type="button" disabled>Page <?= (int)$pg['page'] ?>/<?= (int)$pg['total_pages'] ?></button>
      <?php $query['page'] = $next; ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] >= (int)$pg['total_pages'] ? 'disabled' : '' ?>" href="<?= site_url(($config['url'] ?? 'asset-management') . '?' . http_build_query($query)) ?>">Next</a>
    </div>
  </div>
</div>

<script>
(function(){
  document.querySelectorAll('.asset-complete-form').forEach(function(form){
    form.addEventListener('submit', function(event){
      var input = form.querySelector('input[name="actual_cost"]');
      var current = input ? input.value : '0';
      var value = prompt('Biaya aktual maintenance', current || '0');
      if (value === null) {
        event.preventDefault();
        return;
      }
      if (input) input.value = value;
      return confirm('Selesaikan maintenance ini?');
    });
  });
})();
</script>
