<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$statusOptions = $status_options ?? [];
$statusCounts = $status_counts ?? [];
$contractTypeOptions = $contract_type_options ?? [];
$employeeOptions = $employee_options ?? [];
$templateOptions = $template_options ?? [];
$canCreate = !empty($can_create);
$canEdit = !empty($can_edit);
$ctx = $ctx ?? 'finance';

$buildQuery = static function ($overrides = []) use ($filters, $pg, $ctx) {
    $base = [
        'ctx' => $ctx,
        'q' => $filters['q'] ?? '',
        'employee_id' => $filters['employee_id'] ?? '',
        'template_id' => $filters['template_id'] ?? '',
        'status' => $filters['status'] ?? '',
        'contract_type' => $filters['contract_type'] ?? '',
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
  .hr-contract-index .hc-title {
    font-size: 1.5rem;
    letter-spacing: 0.01em;
  }
  .hr-contract-index .card {
    border: 0;
    box-shadow: 0 2px 12px rgba(31, 41, 55, 0.08);
  }
  .hr-contract-index .table th,
  .hr-contract-index .table td {
    padding: 0.72rem 0.85rem;
    vertical-align: middle;
  }
  .hr-contract-index .table td {
    font-size: 0.92rem;
  }
  .hr-contract-index .filter-form .form-control,
  .hr-contract-index .filter-form .form-select {
    min-height: 40px;
  }
  .hr-contract-index .contract-actions {
    display: flex;
    gap: 0.35rem;
    flex-wrap: nowrap;
    align-items: center;
  }
  .hr-contract-index .contract-actions .btn {
    width: 34px;
    height: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
  }
  .hr-contract-index .contract-actions .btn i {
    font-size: 1rem;
    margin: 0;
  }
  .hr-contract-index .status-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
  }
  .hr-contract-index .status-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid #d9dee6;
    border-radius: 999px;
    padding: 0.52rem 0.85rem;
    color: #56606c;
    background: #fff;
    font-weight: 600;
    text-decoration: none;
    line-height: 1;
  }
  .hr-contract-index .status-tab:hover {
    border-color: #9aa4b2;
    color: #2f3844;
  }
  .hr-contract-index .status-tab.active {
    background: #a3262a;
    border-color: #a3262a;
    color: #fff;
    box-shadow: 0 6px 14px rgba(163, 38, 42, 0.18);
  }
  .hr-contract-index .status-tab .count {
    min-width: 1.55rem;
    height: 1.55rem;
    padding: 0 0.45rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f2f4f7;
    color: #4b5563;
    font-size: 0.78rem;
  }
  .hr-contract-index .status-tab.active .count {
    background: rgba(255,255,255,0.18);
    color: #fff;
  }
</style>

<div class="hr-contract-index">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0 hc-title"><?php echo html_escape($title ?? 'Operasional Kontrak Pegawai'); ?></h4>
    <small class="text-muted">Flow: draft -> generated -> signed -> active, dengan approval + TTE.</small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?php echo site_url('hr-contracts/templates?' . http_build_query(['ctx' => $ctx])); ?>">Template</a>
    <?php if ($canCreate): ?>
      <a class="btn btn-primary" href="<?php echo site_url('hr-contracts/generate?' . http_build_query(['ctx' => $ctx])); ?>">+ Generate Kontrak</a>
    <?php endif; ?>
  </div>
</div>

<div class="alert alert-light border mb-3 py-2 small">
  <strong>Satu sumber nominal:</strong> 1) sepakati komponen di <em>Generate Kontrak</em>, 2) generate dokumen lalu approval dan TTE kedua pihak, 3) kontrak masuk <em>ACTIVE</em> pada tanggal berlaku, 4) Master Pegawai hanya tersinkron sebagai cache, dan 5) absensi membekukan snapshot per hari untuk payroll. Salary profile, standar gaji, dan objective tetap hanya pembanding negosiasi.
</div>

<?php if ($canCreate): ?>
<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-2">Buat Draft Cepat</h6>
    <form method="post" action="<?php echo site_url('hr/contracts/create-draft?' . http_build_query(['ctx' => $ctx])); ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Pegawai</label>
        <select name="employee_id" class="form-select" required>
          <option value="">Pilih pegawai</option>
          <?php foreach ($employeeOptions as $o): ?>
            <option value="<?php echo (int)$o['value']; ?>"><?php echo html_escape((string)$o['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Template</label>
        <select name="template_id" class="form-select">
          <option value="">Tanpa template</option>
          <?php foreach ($templateOptions as $o): ?>
            <option value="<?php echo (int)$o['value']; ?>"><?php echo html_escape((string)$o['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Jenis</label>
        <select name="contract_type" class="form-select">
          <option value="">Auto</option>
          <?php foreach ($contractTypeOptions as $o): ?>
            <option value="<?php echo html_escape((string)$o); ?>"><?php echo html_escape((string)$o); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Mulai</label>
        <input type="date" name="start_date" class="form-control" required>
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Akhir</label>
        <input type="date" name="end_date" class="form-control">
      </div>
      <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-outline-primary">Buat</button>
      </div>
      <div class="col-12">
        <input type="text" name="notes" class="form-control" placeholder="Catatan draft (opsional)">
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <div class="status-tabs">
      <?php $activeStatus = strtoupper((string)($filters['status'] ?? '')); ?>
      <a class="status-tab <?php echo $activeStatus === '' ? 'active' : ''; ?>" href="<?php echo site_url('hr/contracts?' . $buildQuery(['status' => 'ALL', 'page' => 1])); ?>">
        <span>Semua</span>
        <span class="count"><?php echo (int)($statusCounts['ALL'] ?? 0); ?></span>
      </a>
      <?php foreach ($statusOptions as $statusOption): ?>
        <?php $statusOption = strtoupper((string)$statusOption); ?>
        <a class="status-tab <?php echo $activeStatus === $statusOption ? 'active' : ''; ?>" href="<?php echo site_url('hr/contracts?' . $buildQuery(['status' => $statusOption, 'page' => 1])); ?>">
          <span><?php echo html_escape($statusOption); ?></span>
          <span class="count"><?php echo (int)($statusCounts[$statusOption] ?? 0); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('hr/contracts'); ?>" class="row g-2 align-items-end filter-form">
      <input type="hidden" name="ctx" value="<?php echo html_escape($ctx); ?>">
      <input type="hidden" name="status" value="<?php echo html_escape((string)($filters['status'] ?? '')); ?>">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No kontrak / pegawai / catatan">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Pegawai</label>
        <select name="employee_id" class="form-select">
          <option value="">Semua</option>
          <?php foreach ($employeeOptions as $o): ?>
            <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Template</label>
        <select name="template_id" class="form-select">
          <option value="">Semua</option>
          <?php foreach ($templateOptions as $o): ?>
            <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['template_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Jenis</label>
        <select name="contract_type" class="form-select">
          <option value="">Semua</option>
          <?php foreach ($contractTypeOptions as $o): ?>
            <option value="<?php echo html_escape((string)$o); ?>" <?php echo (($filters['contract_type'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo html_escape((string)$o); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a class="btn btn-outline-secondary" href="<?php echo site_url('hr/contracts?' . http_build_query(['ctx' => $ctx])); ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>No Kontrak</th>
          <th>Pegawai</th>
          <th>Template</th>
          <th>Jenis</th>
          <th>Periode</th>
          <th>Status</th>
          <th>TTE</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data kontrak.</td></tr>
      <?php else: ?>
        <?php $no = (($pg['page'] ?? 1) - 1) * ($pg['per_page'] ?? 25) + 1; ?>
        <?php foreach ($rows as $r): ?>
          <?php $status = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
          <?php
            $badgeClass = 'bg-label-secondary';
            if ($status === 'DRAFT') $badgeClass = 'bg-label-warning';
            if ($status === 'GENERATED') $badgeClass = 'bg-label-info';
            if ($status === 'SIGNED') $badgeClass = 'bg-label-primary';
            if ($status === 'ACTIVE') $badgeClass = 'bg-label-success';
            if ($status === 'EXPIRED') $badgeClass = 'bg-label-dark';
            if ($status === 'TERMINATED' || $status === 'CANCELLED') $badgeClass = 'bg-label-danger';
          ?>
          <tr>
            <td><?php echo (int)$no++; ?></td>
            <td>
              <div class="fw-semibold"><?php echo html_escape((string)$r['contract_number']); ?></div>
              <small class="text-muted">Created: <?php echo html_escape((string)($r['created_at'] ?? '-')); ?></small>
            </td>
            <td>
              <div><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div>
              <small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '-')); ?></small>
            </td>
            <td><?php echo html_escape((string)($r['template_name'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)$r['contract_type']); ?></td>
            <td><?php echo html_escape((string)$r['start_date']); ?> s/d <?php echo html_escape((string)$r['end_date']); ?></td>
            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo html_escape($status); ?></span></td>
            <td><?php echo !empty($r['verification_token']) ? '<span class="badge bg-label-success">Ready</span>' : '<span class="badge bg-label-warning">Pending</span>'; ?></td>
            <td>
              <div class="contract-actions">
                <a class="btn btn-outline-primary" href="<?php echo site_url('hr-contracts/view/' . (int)$r['id'] . '?' . http_build_query(['ctx' => $ctx])); ?>" title="Lihat Detail">
                  <i class="ri ri-eye-line"></i>
                </a>
                <a class="btn btn-outline-dark" href="<?php echo site_url('hr-contracts/print/' . (int)$r['id'] . '?' . http_build_query(['ctx' => $ctx])); ?>" target="_blank" title="Cetak Dokumen">
                  <i class="ri ri-printer-line"></i>
                </a>
                <?php if ($canEdit && $status === 'DRAFT'): ?>
                  <form method="post" action="<?php echo site_url('hr/contracts/' . (int)$r['id'] . '/generate?' . http_build_query(['ctx' => $ctx])); ?>" class="d-inline">
                    <button type="submit" class="btn btn-outline-secondary" title="Generate Kontrak">
                      <i class="ri ri-magic-line"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
    <div class="btn-group">
      <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('hr/contracts?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('hr/contracts?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('hr/contracts?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
</div>
