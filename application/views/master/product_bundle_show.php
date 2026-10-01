<?php
$bundle = is_array($bundle ?? null) ? $bundle : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pricingPreview = is_array($pricing_preview ?? null) ? $pricing_preview : [];
$pricingSummary = is_array($pricingPreview['summary'] ?? null) ? $pricingPreview['summary'] : [];
$pricingLines = is_array($pricingPreview['lines'] ?? null) ? $pricingPreview['lines'] : [];
$scope = strtoupper((string)($bundle['pos_scope'] ?? 'REGULAR'));
$isActive = (int)($bundle['is_active'] ?? 0) === 1;
$bundleDivisionLabel = trim((string)($bundle['product_division_name'] ?? ''));
if ($bundleDivisionLabel === '' && !empty($rows)) {
    $bundleDivisionLabel = 'Campuran Divisi';
}
?>

<style>
  .product-bundle-show .bundle-show-card,
  .product-bundle-show .bundle-show-table {
    border: 1px solid rgba(170, 95, 78, 0.16);
    border-radius: 18px;
    box-shadow: 0 14px 32px rgba(70, 30, 28, 0.08);
  }
  .product-bundle-show .bundle-stat {
    padding: .9rem 1rem;
    border: 1px solid rgba(170, 95, 78, 0.14);
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(255,249,246,.95) 0%, rgba(255,255,255,.98) 100%);
    height: 100%;
  }
  .product-bundle-show .bundle-stat .label {
    display: block;
    font-size: .76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8d6f6a;
    margin-bottom: .3rem;
  }
  .product-bundle-show .bundle-stat .value {
    font-size: 1.15rem;
    font-weight: 800;
    color: #3f3134;
  }
</style>

<div class="product-bundle-show container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <div class="fin-breadcrumb">
        <a href="<?php echo site_url('master/product'); ?>">Master Product</a>
        <span>/</span>
        <a href="<?php echo site_url('master/relation/product-bundle'); ?>">Bundle Produk</a>
        <span>/</span>
        <span><?php echo html_escape((string)($bundle['bundle_name'] ?? '-')); ?></span>
      </div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($bundle['bundle_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0"><?php echo html_escape((string)($bundle['bundle_code'] ?? '-')); ?> | Divisi <?php echo html_escape($bundleDivisionLabel !== '' ? $bundleDivisionLabel : '-'); ?> | Scope <?php echo html_escape($scope); ?></p>
    </div>
    <div class="fin-page-actions">
      <a class="btn btn-outline-secondary" href="<?php echo site_url('master/relation/product-bundle'); ?>"><i class="ri ri-arrow-left-line me-1"></i>Kembali</a>
      <a class="btn btn-outline-primary" href="<?php echo site_url('master/relation/product-bundle/edit/' . (int)($bundle['id'] ?? 0)); ?>"><i class="ri ri-edit-box-line me-1"></i>Edit Bundle</a>
    </div>
  </div>

  <div class="card bundle-show-card border-0 shadow-sm mb-3">
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="bundle-stat">
          <span class="label">Status</span>
          <div><span class="badge <?php echo $isActive ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis'; ?>"><?php echo $isActive ? 'Aktif' : 'Nonaktif'; ?></span></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-stat">
          <span class="label">Jumlah Line</span>
          <div class="value"><?php echo (int)($summary['line_count'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-stat">
          <span class="label">Harga Bundle</span>
          <div class="value">Rp <?php echo number_format((float)($summary['bundle_price'] ?? 0), 2, ',', '.'); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-stat">
          <span class="label">Total Harga Isi</span>
          <div class="value">Rp <?php echo number_format((float)($summary['line_value_total'] ?? 0), 2, ',', '.'); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-stat">
          <span class="label">Revenue Alokasi</span>
          <div class="value">Rp <?php echo number_format((float)($pricingSummary['allocated_total'] ?? 0), 2, ',', '.'); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-stat">
          <span class="label">Profit Live Preview</span>
          <div class="value <?php echo (float)($pricingSummary['profit_live_total'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>">Rp <?php echo number_format((float)($pricingSummary['profit_live_total'] ?? 0), 2, ',', '.'); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bundle-stat">
          <span class="label">Mode Pricing</span>
          <div class="small text-muted">Default proporsional jika override kosong</div>
        </div>
      </div>
      <div class="col-12">
        <div class="small text-muted">Deskripsi</div>
        <div><?php echo !empty($bundle['description']) ? html_escape((string)$bundle['description']) : '<span class="text-muted">Tidak ada deskripsi.</span>'; ?></div>
      </div>
      <div class="col-12">
        <div class="alert alert-light border mb-0">
          Revenue line bundle untuk preview profit memakai standar <strong>AUTO_PROPORTIONAL</strong>. Jika override harga di suatu line diisi, line itu memakai nilai override dan sisa revenue bundle dibagi proporsional ke line lain.
        </div>
      </div>
    </div>
  </div>

  <div class="card bundle-show-table border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:60px;">No</th>
              <th>Produk</th>
              <th>Divisi</th>
              <th class="text-end">Qty</th>
              <th>UOM</th>
              <th class="text-end">Harga Dasar</th>
              <th class="text-end">Override</th>
              <th class="text-end">Revenue Alokasi</th>
              <th class="text-end">HPP Live</th>
              <th class="text-end">Profit Live</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="10" class="text-center text-muted py-4">Belum ada line produk.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $idx => $row): ?>
                <?php
                  $pricingLine = $pricingLines[$idx] ?? [];
                  $allocationMode = (string)($pricingLine['allocation_mode'] ?? 'AUTO_PROPORTIONAL');
                ?>
                <tr>
                  <td><?php echo (int)($idx + 1); ?></td>
                  <td>
                    <div class="fw-semibold"><?php echo html_escape((string)($row['product_name'] ?? '-')); ?></div>
                    <div class="small text-muted"><?php echo html_escape((string)($row['product_code'] ?? '-')); ?></div>
                  </td>
                  <td><?php echo html_escape((string)($row['product_division_name'] ?? '-')); ?></td>
                  <td class="text-end"><?php echo number_format((float)($row['qty'] ?? 0), 2, ',', '.'); ?></td>
                  <td><?php echo html_escape((string)($row['uom_code'] ?? $row['uom_name'] ?? '-')); ?></td>
                  <td class="text-end"><?php echo number_format((float)($row['product_selling_price'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end">
                    <?php echo $row['unit_price_override'] !== null ? number_format((float)$row['unit_price_override'], 2, ',', '.') : '<span class="text-muted">-</span>'; ?>
                    <small class="d-block text-muted"><?php echo strpos($allocationMode, 'MANUAL') === 0 ? 'Manual' : 'Auto'; ?></small>
                  </td>
                  <td class="text-end fw-semibold">
                    <?php echo number_format((float)($pricingLine['allocated_total'] ?? 0), 2, ',', '.'); ?>
                    <small class="d-block text-muted">Unit <?php echo number_format((float)($pricingLine['allocated_unit_price'] ?? 0), 2, ',', '.'); ?></small>
                  </td>
                  <td class="text-end">
                    <?php echo number_format((float)($pricingLine['hpp_live_total'] ?? 0), 2, ',', '.'); ?>
                    <small class="d-block text-muted"><?php echo html_escape((string)($pricingLine['cost_source_label'] ?? '-')); ?></small>
                  </td>
                  <td class="text-end fw-semibold <?php echo (float)($pricingLine['profit_live'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo number_format((float)($pricingLine['profit_live'] ?? 0), 2, ',', '.'); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr>
                <td colspan="8" class="text-end fw-semibold">Total Revenue Alokasi</td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($pricingSummary['allocated_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold">-</td>
              </tr>
              <tr>
                <td colspan="8" class="text-end fw-semibold">Total HPP Live</td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($pricingSummary['hpp_live_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold">-</td>
              </tr>
              <tr>
                <td colspan="8" class="text-end fw-bold">Profit Live Preview</td>
                <td class="text-end fw-bold <?php echo (float)($pricingSummary['profit_live_total'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>">
                  <?php echo number_format((float)($pricingSummary['profit_live_total'] ?? 0), 2, ',', '.'); ?>
                </td>
                <td class="text-end fw-bold">-</td>
              </tr>
              <tr>
                <td colspan="8" class="text-end fw-bold">Gap Harga Bundle vs Nilai Referensi</td>
                <td class="text-end fw-bold <?php echo (float)($summary['bundle_gap'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>">
                  <?php echo number_format((float)($summary['bundle_gap'] ?? 0), 2, ',', '.'); ?>
                </td>
                <td class="text-end fw-bold">-</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
