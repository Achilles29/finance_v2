<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$productDivisionOptions = is_array($product_division_options ?? null) ? $product_division_options : [];
?>

<style>
  .product-bundle-hub .bundle-kpi-card,
  .product-bundle-hub .bundle-table-card {
    border: 1px solid rgba(170, 95, 78, 0.16);
    border-radius: 18px;
    box-shadow: 0 14px 32px rgba(70, 30, 28, 0.08);
  }
  .product-bundle-hub .bundle-kpi {
    padding: .95rem 1rem;
    border-radius: 16px;
    border: 1px solid rgba(170, 95, 78, 0.14);
    background: linear-gradient(180deg, rgba(255,249,246,.95) 0%, rgba(255,255,255,.98) 100%);
    height: 100%;
  }
  .product-bundle-hub .bundle-kpi .label {
    display: block;
    font-size: .76rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8d6f6a;
    font-weight: 800;
    margin-bottom: .3rem;
  }
  .product-bundle-hub .bundle-kpi .value {
    font-size: 1.2rem;
    font-weight: 800;
    color: #3f3134;
  }
  .product-bundle-hub .bundle-filter-strip {
    padding: .95rem;
    border: 1px solid rgba(170, 95, 78, 0.12);
    border-radius: 16px;
    background: rgba(255, 251, 248, .78);
  }
  .product-bundle-hub .bundle-name-cell .code {
    font-size: .8rem;
    color: #8d6f6a;
  }
</style>

<div class="product-bundle-hub container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <div class="fin-breadcrumb">
        <a href="<?php echo site_url('master/product'); ?>">Master Product</a>
        <span>/</span>
        <span>Bundle Produk</span>
      </div>
      <h4 class="fin-page-title mb-1">Bundle Produk</h4>
      <p class="fin-page-subtitle mb-0">Kelola paket penjualan berbasis produk aktif. Master ini dipakai POS, tetapi tetap tinggal di domain Produk agar ownership datanya jelas.</p>
    </div>
    <div class="fin-page-actions">
      <a class="btn btn-primary" href="<?php echo site_url('master/relation/product-bundle/create'); ?>"><i class="ri ri-add-line me-1"></i>Tambah Bundle</a>
    </div>
  </div>

  <div class="card bundle-kpi-card border-0 shadow-sm mb-3">
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="bundle-kpi">
          <span class="label">Total Bundle</span>
          <div class="value"><?php echo (int)($summary['total_bundle'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-kpi">
          <span class="label">Bundle Aktif</span>
          <div class="value text-success"><?php echo (int)($summary['active_bundle'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-kpi">
          <span class="label">Bundle Event</span>
          <div class="value text-warning"><?php echo (int)($summary['event_scope'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-kpi">
          <span class="label">Total Line Produk</span>
          <div class="value"><?php echo (int)($summary['line_total'] ?? 0); ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card bundle-table-card border-0 shadow-sm">
    <div class="card-body">
      <form method="get" class="row g-2 mb-3 bundle-filter-strip">
        <div class="col-md-3">
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach (['ACTIVE' => 'Aktif', 'INACTIVE' => 'Nonaktif', 'ALL' => 'Semua'] as $value => $label): ?>
              <button type="submit" name="status" value="<?php echo $value; ?>" class="btn btn-sm <?php echo (($filters['status'] ?? 'ACTIVE') === $value) ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $label; ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="pos_scope">
            <option value="ALL" <?php echo (($filters['pos_scope'] ?? 'ALL') === 'ALL') ? 'selected' : ''; ?>>Semua Scope</option>
            <option value="REGULAR" <?php echo (($filters['pos_scope'] ?? '') === 'REGULAR') ? 'selected' : ''; ?>>Regular</option>
            <option value="EVENT" <?php echo (($filters['pos_scope'] ?? '') === 'EVENT') ? 'selected' : ''; ?>>Event</option>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" name="product_division_id">
            <option value="0">Semua Divisi Produk</option>
            <?php foreach ($productDivisionOptions as $opt): ?>
              <option value="<?php echo (int)$opt['id']; ?>" <?php echo (int)($filters['product_division_id'] ?? 0) === (int)$opt['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)$opt['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Cari kode / nama / deskripsi bundle">
        </div>
        <div class="col-md-1 d-grid">
          <button type="submit" class="btn btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Bundle</th>
              <th>Divisi</th>
              <th class="text-center">Scope</th>
              <th class="text-end">Harga Bundle</th>
              <th class="text-end">Nilai Isi</th>
              <th class="text-center">Line</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width: 164px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-4">Belum ada bundle produk.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $active = (int)($row['is_active'] ?? 0) === 1;
                  $scope = strtoupper((string)($row['pos_scope'] ?? 'REGULAR'));
                ?>
                <tr>
                  <td class="text-nowrap"><?php echo html_escape((string)($row['bundle_code'] ?? '-')); ?></td>
                  <td>
                    <div class="bundle-name-cell">
                      <div class="fw-semibold"><?php echo html_escape((string)($row['bundle_name'] ?? '-')); ?></div>
                      <div class="code"><?php echo html_escape((string)($row['bundle_code'] ?? '-')); ?></div>
                    </div>
                    <?php if (!empty($row['description'])): ?><div class="small text-muted"><?php echo html_escape((string)$row['description']); ?></div><?php endif; ?>
                  </td>
                  <td>
                    <?php
                      $divisionLabel = trim((string)($row['product_division_name'] ?? ''));
                      if ($divisionLabel === '' && (int)($row['total_line'] ?? 0) > 0) {
                          $divisionLabel = 'Campuran Divisi';
                      }
                    ?>
                    <?php echo html_escape($divisionLabel !== '' ? $divisionLabel : '-'); ?>
                  </td>
                  <td class="text-center">
                    <span class="badge <?php echo $scope === 'EVENT' ? 'bg-warning-subtle text-warning-emphasis' : ($scope === 'ALL' ? 'bg-info-subtle text-info-emphasis' : 'bg-primary-subtle text-primary-emphasis'); ?>">
                      <?php echo html_escape($scope); ?>
                    </span>
                  </td>
                  <td class="text-end"><?php echo number_format((float)($row['selling_price'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)($row['line_value_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-center"><?php echo (int)($row['total_line'] ?? 0); ?></td>
                  <td class="text-center">
                    <span class="badge <?php echo $active ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis'; ?>">
                      <?php echo $active ? 'Aktif' : 'Nonaktif'; ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <div class="d-inline-flex gap-1">
                      <a class="btn btn-sm btn-outline-info action-icon-btn" href="<?php echo site_url('master/relation/product-bundle/' . (int)$row['id']); ?>" title="Detail"><i class="ri ri-eye-line"></i></a>
                      <a class="btn btn-sm btn-outline-primary action-icon-btn" href="<?php echo site_url('master/relation/product-bundle/edit/' . (int)$row['id']); ?>" title="Edit"><i class="ri ri-edit-box-line"></i></a>
                      <form method="post" action="<?php echo site_url('master/relation/product-bundle/toggle/' . (int)$row['id']); ?>" class="d-inline" onsubmit="return confirm('Ubah status bundle ini?')">
                        <input type="hidden" name="master_relation_product_bundle_mutation_csrf" value="<?php echo html_escape((string)($master_relation_product_bundle_mutation_csrf ?? '')); ?>">
                        <button type="submit" class="btn btn-sm <?php echo $active ? 'btn-outline-danger' : 'btn-outline-success'; ?> action-icon-btn" title="<?php echo $active ? 'Nonaktifkan' : 'Aktifkan'; ?>"><i class="ri <?php echo $active ? 'ri-toggle-line' : 'ri-checkbox-circle-line'; ?>"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
