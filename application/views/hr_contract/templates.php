<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$contractTypeOptions = $contract_type_options ?? [];
$canCreate = !empty($can_create);
$canEdit = !empty($can_edit);
$canDelete = !empty($can_delete);
$ctx = $ctx ?? 'finance';

$buildQuery = static function ($overrides = []) use ($filters, $pg, $ctx) {
    $base = [
        'ctx' => $ctx,
        'q' => $filters['q'] ?? '',
        'is_active' => $filters['is_active'] ?? '',
        'contract_type' => $filters['contract_type'] ?? '',
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
  .hr-contract-templates .card {
    border: 0;
    box-shadow: 0 2px 12px rgba(31, 41, 55, 0.08);
  }
  .hr-contract-templates .table th,
  .hr-contract-templates .table td {
    padding: 0.72rem 0.85rem;
    vertical-align: middle;
  }
  .hr-contract-templates .template-actions {
    display: flex;
    gap: 0.35rem;
    flex-wrap: nowrap;
    align-items: center;
  }
  .hr-contract-templates .template-actions .btn {
    width: 34px;
    height: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
  }
  .hr-contract-templates .template-actions .btn i {
    font-size: 1rem;
    margin: 0;
  }
</style>

<div class="hr-contract-templates">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0">Template Kontrak</h4>
    <small class="text-muted">Kelola template, preview dokumen, dan placeholder kontrak.</small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?php echo site_url('hr/contracts?' . http_build_query(['ctx' => $ctx])); ?>">Kontrak</a>
    <?php if ($canCreate): ?>
      <a class="btn btn-primary" href="<?php echo site_url('hr-contracts/template-edit?' . http_build_query(['ctx' => $ctx])); ?>">+ Template Baru</a>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('hr-contracts/templates'); ?>" class="row g-2 align-items-end">
      <input type="hidden" name="ctx" value="<?php echo html_escape($ctx); ?>">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Kode / nama template">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Jenis Kontrak</label>
        <select name="contract_type" class="form-select">
          <option value="">Semua</option>
          <?php foreach ($contractTypeOptions as $type): ?>
            <option value="<?php echo html_escape($type); ?>" <?php echo (($filters['contract_type'] ?? '') === $type) ? 'selected' : ''; ?>><?php echo html_escape($type); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select name="is_active" class="form-select">
          <option value="">Semua</option>
          <option value="1" <?php echo (($filters['is_active'] ?? '') === '1') ? 'selected' : ''; ?>>Aktif</option>
          <option value="0" <?php echo (($filters['is_active'] ?? '') === '0') ? 'selected' : ''; ?>>Nonaktif</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Per Halaman</label>
        <select name="per_page" class="form-select">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo ((int)$pg['per_page'] === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100">Filter</button>
        <a class="btn btn-outline-secondary" href="<?php echo site_url('hr-contracts/templates?' . http_build_query(['ctx' => $ctx])); ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Kode</th>
          <th>Nama Template</th>
          <th>Jenis</th>
          <th>Durasi</th>
          <th>Dipakai</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Belum ada template kontrak.</td></tr>
      <?php else: ?>
        <?php $no = (($pg['page'] ?? 1) - 1) * ($pg['per_page'] ?? 25) + 1; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$no++; ?></td>
            <td class="fw-semibold"><?php echo html_escape((string)$r['template_code']); ?></td>
            <td>
              <div class="fw-semibold"><?php echo html_escape((string)$r['template_name']); ?></div>
              <small class="text-muted">By: <?php echo html_escape((string)($r['created_by_username'] ?? '-')); ?></small>
            </td>
            <td><?php echo html_escape((string)$r['contract_type']); ?></td>
            <td><?php echo (int)($r['duration_months'] ?? 0); ?> bulan</td>
            <td><?php echo (int)($r['contract_count'] ?? 0); ?></td>
            <td>
              <span class="badge <?php echo !empty($r['is_active']) ? 'bg-label-success' : 'bg-label-secondary'; ?>">
                <?php echo !empty($r['is_active']) ? 'Aktif' : 'Nonaktif'; ?>
              </span>
            </td>
            <td>
              <div class="template-actions">
                <?php if ($canEdit): ?>
                  <a class="btn btn-outline-primary" href="<?php echo site_url('hr-contracts/template-edit/' . (int)$r['id'] . '?' . http_build_query(['ctx' => $ctx])); ?>" title="Edit Template">
                    <i class="ri ri-edit-line"></i>
                  </a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                  <form method="post" action="<?php echo site_url('hr-contracts/template-delete/' . (int)$r['id'] . '?' . http_build_query(['ctx' => $ctx])); ?>" class="d-inline" onsubmit="return confirm('Hapus template ini?');">
                    <button type="submit" class="btn btn-outline-danger" title="Hapus Template"><i class="ri ri-delete-bin-line"></i></button>
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
        <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('hr-contracts/templates?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
        <?php foreach ($pageItems as $item): ?>
          <?php if ($item === '...'): ?>
            <span class="btn btn-sm btn-outline-secondary disabled">...</span>
          <?php else: ?>
            <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('hr-contracts/templates?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('hr-contracts/templates?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
      </div>
    </div>
  <?php endif; ?>
</div>
</div>
