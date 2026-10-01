<?php
$relationType = $relation_type;
$isProductRecipe = $relationType === 'product-recipe';
$isComponentFormula = $relationType === 'component-formula';
$isProductExtra = $relationType === 'product-extra';
$summary = is_array($summary ?? null) ? $summary : [];
$defaultSourceDivision = is_array($default_source_division ?? null) ? $default_source_division : [];
$productVariableCost = is_array($product_variable_cost ?? null) ? $product_variable_cost : [];

if ($isProductRecipe) {
    $base = site_url('master/relation/product-recipe/' . (int)$parent['id']);
    $createUrl = site_url('master/relation/product-recipe/' . (int)$parent['id'] . '/create');
    $backUrl = site_url('master/product');
} elseif ($isComponentFormula) {
    $base = site_url('production/component-formulas/detail/' . (int)$parent['id']);
    $createUrl = site_url('production/component-formulas/edit/' . (int)$parent['id']);
    $backUrl = site_url('production/component-masters');
} else {
    $base = site_url('master/relation/product-extra/' . (int)$parent['id']);
    $createUrl = site_url('master/relation/product-extra/' . (int)$parent['id'] . '/create');
    $backUrl = site_url('master/product');
}
?>

<?php if ($isProductExtra): ?>
  <?php $extraTabActive = 'product-extra'; ?>
  <?php $this->load->view('master/_extra_tabs', compact('extraTabActive')); ?>
<?php endif; ?>

<?php if ($isProductRecipe): ?>
<style>
  .product-recipe-page .table > :not(caption) > * > * {
    padding-top: 0.48rem;
    padding-bottom: 0.48rem;
  }
  .product-recipe-page .product-recipe-ref {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
  }
  .product-recipe-page .product-recipe-ref small {
    line-height: 1.2;
  }
</style>

<div class="product-recipe-page container-xxl py-3 px-0">
  <div class="fin-page-header mb-3">
    <div>
      <div class="fin-breadcrumb">
        <a href="<?php echo site_url('master/product'); ?>">Master Product</a>
        <span>/</span>
        <span>Recipe Produk</span>
      </div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape($parent['product_name'] ?? ('#' . $parent['id'])); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Harga jual Rp <?php echo number_format((float)($summary['selling_price'] ?? 0), 2, ',', '.'); ?> |
        Baris <?php echo (int)($summary['line_count'] ?? 0); ?> | Recipe default | Source bahan baku <?php echo html_escape((string)($defaultSourceDivision['name'] ?? '-')); ?>
      </p>
    </div>
    <div class="fin-page-actions">
      <a class="btn btn-outline-secondary" href="<?php echo $backUrl; ?>">Kembali Master</a>
      <a class="btn btn-outline-primary" href="<?php echo site_url('master/relation/product-recipe/edit-all/' . (int)$parent['id']); ?>">Edit Resep</a>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="small text-muted">Direct Cost Standar</div>
        <div class="fw-semibold">Rp <?php echo number_format((float)($summary['direct_cost_standard'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">HPP tanpa variabel</div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Direct Cost Live</div>
        <div class="fw-semibold">Rp <?php echo number_format((float)($summary['direct_cost_live'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">HPP tanpa variabel</div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Biaya Variabel</div>
        <div class="fw-semibold"><?php echo html_escape((string)($summary['variable_cost_mode'] ?? 'DEFAULT')); ?> | <?php echo number_format((float)($summary['variable_cost_percent'] ?? 0), 2, ',', '.'); ?>%</div>
        <div class="small text-muted">Std Rp <?php echo number_format((float)($summary['variable_cost_standard'] ?? 0), 2, ',', '.'); ?> | Live Rp <?php echo number_format((float)($summary['variable_cost_live'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">HPP Produk</div>
        <div class="fw-semibold">Std Rp <?php echo number_format((float)($summary['total_hpp_standard'] ?? 0), 2, ',', '.'); ?></div>
        <div class="fw-semibold">Live Rp <?php echo number_format((float)($summary['total_hpp_live'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">% HPP Live <?php echo number_format((float)($summary['hpp_live_percent'] ?? 0), 2, ',', '.'); ?>%</div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:70px;">No</th>
            <th style="width:150px;">Tipe</th>
            <th style="width:150px;">Role</th>
            <th>Sumber</th>
            <th style="width:180px;">Source Divisi</th>
            <th class="text-end" style="width:120px;">Qty</th>
            <th style="width:110px;">Satuan</th>
            <th class="text-end" style="width:140px;">Stok Tersedia</th>
            <th class="text-end" style="width:130px;">Cost Std</th>
            <th class="text-end" style="width:180px;">Cost Live</th>
            <th class="text-end" style="width:150px;">Total Std</th>
            <th class="text-end" style="width:150px;">Total Live</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="12" class="text-center text-muted py-4">Belum ada line recipe untuk produk ini.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $index => $r): ?>
              <tr>
                <td><?php echo (int)($index + 1); ?></td>
                <td>
                  <span class="badge <?php echo strtoupper((string)($r['line_type'] ?? '')) === 'COMPONENT' ? 'bg-label-info text-info' : 'bg-label-primary text-primary'; ?>">
                    <?php echo html_escape((string)($r['line_type_label'] ?? '-')); ?>
                  </span>
                </td>
                <td><span class="badge bg-light text-dark border"><?php echo html_escape((string)($r['ingredient_role_label'] ?? 'Bahan Utama')); ?></span></td>
                <td>
                  <div class="product-recipe-ref">
                    <strong><?php echo html_escape((string)($r['reference_name'] ?? '-')); ?></strong>
                    <?php if (!empty($r['notes'])): ?><small class="text-muted">Catatan: <?php echo html_escape((string)$r['notes']); ?></small><?php endif; ?>
                  </div>
                </td>
                <td><?php echo html_escape((string)($r['resolved_source_division_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['qty'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($r['uom_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['available_qty'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['standard_unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">
                  <div class="d-inline-flex align-items-center gap-1">
                    <span><?php echo number_format((float)($r['live_unit_cost'] ?? 0), 2, ',', '.'); ?></span>
                    <span class="badge bg-light text-dark border" style="min-width:96px;"><?php echo html_escape((string)($r['live_cost_source_label'] ?? '-')); ?></span>
                  </div>
                </td>
                <td class="text-end"><?php echo number_format((float)($r['line_standard_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['line_live_total'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td colspan="10" class="text-end fw-semibold">Subtotal Direct Cost</td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($summary['direct_cost_standard'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($summary['direct_cost_live'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
            <tr>
              <td colspan="10" class="text-end fw-semibold">Biaya Variabel - <?php echo html_escape((string)($summary['variable_cost_mode'] ?? 'DEFAULT')); ?> (<?php echo number_format((float)($summary['variable_cost_percent'] ?? 0), 2, ',', '.'); ?>%)</td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($summary['variable_cost_standard'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($summary['variable_cost_live'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
            <tr>
              <td colspan="10" class="text-end fw-bold">Total HPP</td>
              <td class="text-end fw-bold"><?php echo number_format((float)($summary['total_hpp_standard'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold"><?php echo number_format((float)($summary['total_hpp_live'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
      <div class="d-flex justify-content-center gap-2 py-3 border-top">
        <a class="btn btn-outline-primary" href="<?php echo site_url('master/relation/product-recipe/edit-all/' . (int)$parent['id']); ?>">Edit Resep</a>
        <a class="btn btn-outline-secondary" href="<?php echo site_url('master/relation/product-recipe'); ?>">Kembali</a>
      </div>
    </div>
  </div>
</div>
<?php return; ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 gap-2">
  <div>
    <h4 class="mb-1"><?php echo html_escape($title); ?></h4>
    <small class="text-muted">
      Parent: <?php echo html_escape($parent['product_name'] ?? $parent['component_name'] ?? ('#' . $parent['id'])); ?>
    </small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary mr-2" href="<?php echo $backUrl; ?>">Kembali Master</a>
    <a class="btn btn-primary" href="<?php echo $createUrl; ?>">Tambah Relasi</a>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th width="60">No</th>
          <?php if ($isProductRecipe || $isComponentFormula): ?>
            <th>Line</th>
            <th>Type</th>
            <th>Referensi</th>
            <?php if ($isProductRecipe || $isComponentFormula): ?><th>Divisi Sumber</th><?php endif; ?>
            <th>Qty</th>
            <th>UOM</th>
            <th>Sort</th>
            <th>Catatan</th>
          <?php else: ?>
            <th>Extra Group</th>
            <th>Sort</th>
          <?php endif; ?>
          <th width="190">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="10" class="text-center text-muted py-4">Belum ada data relasi.</td>
          </tr>
        <?php else: ?>
          <?php $no = 1; foreach ($rows as $r): ?>
            <tr>
              <td class="number-cell"><?php echo $no++; ?></td>
              <?php if ($isProductRecipe || $isComponentFormula): ?>
                <td class="number-cell"><?php echo (int)$r['line_no']; ?></td>
                <td><?php echo ($r['line_type'] ?? '') === 'MATERIAL' ? 'BAHAN BAKU' : html_escape((string)$r['line_type']); ?></td>
                <td>
                  <?php if (($r['line_type'] ?? '') === 'MATERIAL'): ?>
                    <?php echo html_escape((string)($r['item_name'] ?? '-')); ?>
                  <?php else: ?>
                    <?php echo html_escape((string)($r['component_name'] ?? '-')); ?>
                  <?php endif; ?>
                </td>
                <?php if ($isProductRecipe || $isComponentFormula): ?><td><?php echo html_escape((string)($r['source_division_name'] ?? '-')); ?></td><?php endif; ?>
                <td class="number-cell"><span data-decimal="1" data-value="<?php echo html_escape((string)$r['qty']); ?>"><?php echo html_escape((string)$r['qty']); ?></span></td>
                <td><?php echo html_escape((string)($r['uom_name'] ?? '-')); ?></td>
                <td class="number-cell"><?php echo (int)$r['sort_order']; ?></td>
                <td><?php echo html_escape((string)($r['notes'] ?? '')); ?></td>
              <?php else: ?>
                <td><?php echo html_escape((string)($r['group_name'] ?? '-')); ?></td>
                <td class="number-cell"><?php echo (int)$r['sort_order']; ?></td>
              <?php endif; ?>
              <td class="action-cell">
                <?php if ($isProductRecipe): ?>
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit" href="<?php echo site_url('master/relation/product-recipe/edit/' . (int)$r['id']); ?>"><i class="ri ri-edit-line"></i></a>
                  <form method="post" action="<?php echo site_url('master/relation/product-recipe/delete/' . (int)$r['id']); ?>" class="d-inline" onsubmit="return confirm('Hapus relasi ini?')">
                    <input type="hidden" name="master_relation_product_recipe_mutation_csrf" value="<?php echo html_escape((string)($master_relation_product_recipe_mutation_csrf ?? '')); ?>">
                    <input type="hidden" name="product_recipe_revision" value="<?php echo html_escape((string)($product_recipe_revision ?? '')); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus"><i class="ri ri-delete-bin-line"></i></button>
                  </form>
                <?php elseif ($isComponentFormula): ?>
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Buka Editor Formula" aria-label="Buka Editor Formula" href="<?php echo site_url('production/component-formulas/edit/' . (int)$parent['id']); ?>"><i class="ri ri-edit-line"></i></a>
                <?php else: ?>
                  <form method="post" action="<?php echo site_url('master/relation/product-extra/delete/' . (int)$r['id']); ?>" class="d-inline" onsubmit="return confirm('Hapus mapping ini?')">
                    <input type="hidden" name="master_relation_product_extra_mutation_csrf" value="<?php echo html_escape((string)($master_relation_product_extra_mutation_csrf ?? '')); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus"><i class="ri ri-delete-bin-line"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
