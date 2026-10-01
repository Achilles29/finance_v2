<?php
$material  = is_array($material ?? null) ? $material : [];
$usageRows = is_array($usage_rows ?? null) ? $usage_rows : [];

$matName = html_escape($material['material_name'] ?? '-');
$matCode = html_escape($material['material_code'] ?? '-');
$catName = html_escape($material['category_name'] ?? '-');
$uomName = html_escape($material['uom_name'] ?? '-');
$hppStd  = (float)($material['hpp_standard'] ?? 0);

// Separate components vs products
$compRows    = array_filter($usageRows, static function($r) { return strtoupper($r['usage_type'] ?? '') === 'COMPONENT'; });
$productRows = array_filter($usageRows, static function($r) { return strtoupper($r['usage_type'] ?? '') === 'PRODUCT'; });

// Group components by type
$grouped = ['BASE' => [], 'PREPARE' => [], 'OTHER' => []];
foreach ($compRows as $row) {
    $type = strtoupper(trim((string)($row['component_type'] ?? 'OTHER')));
    if (!isset($grouped[$type])) $grouped[$type] = [];
    $grouped[$type][] = $row;
}

$typeLabels = ['BASE' => 'Base', 'PREPARE' => 'Prepare', 'OTHER' => 'Lainnya'];
?>

<style>
  .mat-usage-card { border:1px solid rgba(224,209,198,.65); border-radius:16px; padding:.9rem 1rem; background:#fffdfb; }
  .mat-usage-type-badge {
    display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .65rem; border-radius:999px;
    font-size:.72rem; font-weight:800;
  }
  .mat-usage-type-badge.BASE    { background:#e8f8ec; color:#1d7f45; }
  .mat-usage-type-badge.PREPARE { background:#e0f2fe; color:#075985; }
  .mat-usage-type-badge.OTHER   { background:#f3f4f6; color:#6b7280; }
  .mat-usage-table th { font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .mat-usage-table td { vertical-align:middle; font-size:.88rem; }
  .mat-usage-div-badge { font-size:.7rem; font-weight:700; background:#eef3f1; color:#1f5d54; border:1px solid #d6e0dc; border-radius:999px; padding:.1rem .45rem; }
  .mat-usage-action { width:1%; white-space:nowrap; text-align:right; }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <p class="fin-breadcrumb">
        <a href="<?php echo site_url('master/material'); ?>">Master Bahan Baku</a> / Penggunaan
      </p>
      <h4 class="fin-page-title"><?php echo $matName; ?></h4>
      <p class="fin-page-subtitle mb-0">
        Kode: <?php echo $matCode; ?>
        &nbsp;·&nbsp; Satuan: <?php echo $uomName; ?>
        &nbsp;·&nbsp; Kategori: <?php echo $catName; ?>
        <?php if ($hppStd > 0): ?>
          &nbsp;·&nbsp; HPP Standar: Rp <?php echo number_format($hppStd, 2, ',', '.'); ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="fin-page-actions">
      <a href="<?php echo site_url('master/material'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ri ri-arrow-left-line me-1"></i>Kembali
      </a>
      <a href="<?php echo site_url('master/material/edit/' . (int)($material['id'] ?? 0)); ?>"
         class="btn btn-outline-primary btn-sm">
        <i class="ri ri-edit-line me-1"></i>Edit
      </a>
    </div>
  </div>

  <?php foreach ($grouped as $type => $typeRows): ?>
      <?php if (empty($typeRows)) continue; ?>

      <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
          <span class="mat-usage-type-badge <?php echo $type; ?>">
            <i class="ri ri-layers-line"></i>
            <?php echo html_escape($typeLabels[$type] ?? $type); ?>
          </span>
          <small class="text-muted"><?php echo count($typeRows); ?> komponen</small>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0 mat-usage-table">
            <thead class="table-light">
              <tr>
                <th>Komponen</th>
                <th>Tipe</th>
                <th>Divisi</th>
                <th class="text-end">Qty dalam Formula</th>
                <th>Satuan</th>
                <th class="mat-usage-action">Buka</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($typeRows as $row): ?>
                <?php $componentId = (int)($row['component_id'] ?? 0); ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo html_escape($row['component_name'] ?? '-'); ?></div>
                  </td>
                  <td>
                    <span class="mat-usage-type-badge <?php echo html_escape(strtoupper($row['component_type'] ?? '')); ?>">
                      <?php echo html_escape($row['component_type'] ?? '-'); ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($row['division_name'])): ?>
                      <span class="mat-usage-div-badge"><?php echo html_escape($row['division_name']); ?></span>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-semibold">
                    <?php echo number_format((float)($row['qty'] ?? 0), 4, ',', '.'); ?>
                  </td>
                  <td class="text-muted">
                    <?php echo html_escape($row['uom_code'] ?? $uomName); ?>
                  </td>
                  <td class="mat-usage-action">
                    <?php if ($componentId > 0): ?>
                      <a class="btn btn-sm btn-outline-info action-icon-btn"
                         href="<?php echo site_url('production/component-formulas/detail/' . $componentId); ?>"
                         data-bs-toggle="tooltip"
                         title="Buka Formula Component"
                         aria-label="Buka Formula Component">
                        <i class="ri ri-eye-line"></i>
                      </a>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php endforeach; ?>

  <?php if (!empty($productRows)): ?>
  <div class="card mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
      <span class="mat-usage-type-badge" style="background:#fef3c7;color:#92400e;">
        <i class="ri ri-restaurant-2-line"></i> Resep Produk
      </span>
      <small class="text-muted"><?php echo count($productRows); ?> produk</small>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 mat-usage-table">
        <thead class="table-light">
          <tr>
            <th>Produk</th>
            <th>Divisi</th>
            <th class="text-end">Qty dalam Resep</th>
            <th>Satuan</th>
            <th class="mat-usage-action">Buka</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($productRows as $row): ?>
            <?php $productId = (int)($row['product_id'] ?? 0); ?>
            <tr>
              <td>
                <div class="fw-semibold"><?php echo html_escape($row['product_name'] ?? '-'); ?></div>
                <div class="small text-muted"><?php echo html_escape($row['product_code'] ?? ''); ?></div>
              </td>
              <td>
                <?php if (!empty($row['division_name'])): ?>
                  <span class="mat-usage-div-badge"><?php echo html_escape($row['division_name']); ?></span>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-end fw-semibold">
                <?php echo number_format((float)($row['qty'] ?? 0), 4, ',', '.'); ?>
              </td>
              <td class="text-muted">
                <?php echo html_escape($row['uom_code'] ?? $uomName); ?>
              </td>
              <td class="mat-usage-action">
                <?php if ($productId > 0): ?>
                  <a class="btn btn-sm btn-outline-info action-icon-btn"
                     href="<?php echo site_url('master/relation/product-recipe/' . $productId); ?>"
                     data-bs-toggle="tooltip"
                     title="Buka Resep Produk"
                     aria-label="Buka Resep Produk">
                    <i class="ri ri-eye-line"></i>
                  </a>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($usageRows)): ?>
  <div class="card">
    <div class="card-body text-center py-4 text-muted">
      <i class="ri ri-inbox-line ri-2x d-block mb-2"></i>
      Bahan ini belum digunakan di formula komponen maupun resep produk.
    </div>
  </div>
  <?php endif; ?>
</div>
