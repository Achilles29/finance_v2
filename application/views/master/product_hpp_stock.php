<?php
$product = is_array($product ?? null) ? $product : [];
$lines   = is_array($lines ?? null) ? $lines : [];
$summary = is_array($summary ?? null) ? $summary : [];
$vc      = is_array($variable_cost ?? null) ? $variable_cost : [];

$sellingPrice   = (float)($summary['selling_price'] ?? 0);
$totalStock     = (float)($summary['total_hpp_stock'] ?? 0);
$totalFormula   = (float)($summary['total_hpp_formula'] ?? 0);
$pctStock       = (float)($summary['hpp_pct_stock'] ?? 0);
$pctFormula     = (float)($summary['hpp_pct_formula'] ?? 0);
$varPct         = (float)($summary['variable_cost_percent'] ?? 0);
$varMode        = (string)($summary['variable_cost_mode'] ?? 'DEFAULT');
?>
<style>
  .hpp-stock-page .badge-source-lot    { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
  .hpp-stock-page .badge-source-stock  { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
  .hpp-stock-page .badge-source-fallback{ background: #fff8e1; color: #f57f17; border: 1px solid #ffcc80; }
  .hpp-stock-page .badge-source-std    { background: #f3e5f5; color: #6a1b9a; border: 1px solid #ce93d8; }
  .hpp-stock-page .badge-source-nodata { background: #fafafa; color: #757575; border: 1px solid #bdbdbd; }
  .hpp-stock-page .diff-positive { color: #388e3c; font-weight: 600; }
  .hpp-stock-page .diff-negative { color: #c62828; font-weight: 600; }
  .hpp-stock-page .diff-zero     { color: #9e9e9e; }
  .hpp-stock-page .hpp-link { color: inherit; text-decoration: none; }
  .hpp-stock-page .hpp-link:hover { text-decoration: underline; }
</style>

<div class="hpp-stock-page container-xxl py-3">

  <div class="fin-page-header mb-3">
    <div>
      <div class="fin-breadcrumb">
        <a href="<?php echo site_url('master/product'); ?>">Master Produk</a>
        <span>/</span>
        <a href="<?php echo site_url('master/relation/product-recipe/' . (int)($product['id'] ?? 0)); ?>">Resep</a>
        <span>/</span>
        <span>COGS Stok</span>
      </div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($product['product_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Harga jual Rp <?php echo number_format($sellingPrice, 2, ',', '.'); ?> |
        <?php echo (int)($summary['line_count'] ?? 0); ?> baris resep |
        Biaya variabel <?php echo html_escape($varMode); ?> <?php echo number_format($varPct, 2, ',', '.'); ?>%
      </p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('master/product'); ?>">Kembali</a>
      <a class="btn btn-outline-info btn-sm" href="<?php echo site_url('master/relation/product-recipe/' . (int)($product['id'] ?? 0)); ?>">Lihat Resep</a>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted mb-1">COGS Stok Riil <span class="badge bg-label-primary text-primary ms-1">Digunakan</span></div>
          <div class="fw-bold fs-5">Rp <?php echo number_format($totalStock, 2, ',', '.'); ?></div>
          <div class="small text-muted">Komponen dari avg stok &rarr; fallback resep</div>
          <?php if ($sellingPrice > 0): ?>
            <div class="small mt-1">% HPP: <strong><?php echo number_format($pctStock, 2, ',', '.'); ?>%</strong></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted mb-1">COGS Formula (Ekspansi Resep)</div>
          <div class="fw-bold fs-5">Rp <?php echo number_format($totalFormula, 2, ',', '.'); ?></div>
          <div class="small text-muted">Komponen dihitung dari formula bahan baku</div>
          <?php if ($sellingPrice > 0): ?>
            <div class="small mt-1">% HPP: <strong><?php echo number_format($pctFormula, 2, ',', '.'); ?>%</strong></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted mb-1">Selisih (Stok vs Formula)</div>
          <?php
            $diff = $totalStock - $totalFormula;
            $diffClass = $diff > 0.001 ? 'diff-positive' : ($diff < -0.001 ? 'diff-negative' : 'diff-zero');
            $diffSign = $diff > 0 ? '+' : '';
          ?>
          <div class="fw-bold fs-5 <?php echo $diffClass; ?>"><?php echo $diffSign; ?>Rp <?php echo number_format($diff, 2, ',', '.'); ?></div>
          <div class="small text-muted">
            <?php if (abs($diff) < 0.01): ?>Tidak ada selisih signifikan
            <?php elseif ($diff > 0): ?>Stok lebih mahal <?php echo number_format(abs($diff / max($totalFormula, 0.0001) * 100), 1, ',', '.'); ?>%
            <?php else: ?>Stok lebih murah <?php echo number_format(abs($diff / max($totalFormula, 0.0001) * 100), 1, ',', '.'); ?>%
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Detail table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px;">No</th>
              <th style="width:110px;">Tipe</th>
              <th>Sumber</th>
              <th style="width:120px;">Divisi</th>
              <th class="text-end" style="width:100px;">Qty</th>
              <th style="width:80px;">Satuan</th>
              <th class="text-end" style="width:130px;">Stok Tersedia</th>
              <th class="text-end" style="width:145px;">Cost Stok</th>
              <th class="text-end" style="width:145px;">Cost Formula</th>
              <th style="width:130px;">Sumber Cost</th>
              <th class="text-end" style="width:145px;">Total Stok</th>
              <th class="text-end" style="width:145px;">Total Formula</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($lines)): ?>
              <tr><td colspan="12" class="text-center text-muted py-4">Belum ada baris resep untuk produk ini.</td></tr>
            <?php else: ?>
              <?php foreach ($lines as $i => $line):
                $isComponent = (string)($line['line_type'] ?? '') === 'COMPONENT';
                $referenceName = (string)($line['reference_name'] ?? '-');
                $resolvedDivisionId = (int)($line['resolved_division_id'] ?? 0);
                $componentId = (int)($line['component_id'] ?? 0);
                $materialId = (int)($line['material_id'] ?? 0);
                $itemId = (int)($line['item_id'] ?? 0);
                $costDiff = (float)($line['cost_by_stock'] ?? 0) - (float)($line['cost_by_formula'] ?? 0);
                $src = (string)($line['stock_source'] ?? 'NO_DATA');
                $usageUrl = '';
                $stockUrl = '';
                if ($isComponent && $componentId > 0) {
                  $usageUrl = site_url('production/component-masters/usage/' . $componentId);
                  $stockParams = [
                    'component_id' => $componentId,
                    'q' => $referenceName,
                  ];
                  if ($resolvedDivisionId > 0) {
                    $stockParams['division_id'] = $resolvedDivisionId;
                  }
                  $stockUrl = site_url('production/component-daily') . '?' . http_build_query($stockParams);
                } elseif (!$isComponent && $materialId > 0) {
                  $usageUrl = site_url('master/material/usage/' . $materialId);
                  $stockParams = [
                    'material_id' => $materialId,
                    'q' => $referenceName,
                  ];
                  if ($resolvedDivisionId > 0) {
                    $stockParams['division_id'] = $resolvedDivisionId;
                  }
                  if ($itemId > 0) {
                    $stockParams['item_id'] = $itemId;
                  }
                  $stockUrl = site_url('inventory-material-daily') . '?' . http_build_query($stockParams);
                }
                if (strpos($src, 'LOT') !== false) $badgeClass = 'badge-source-lot';
                elseif (strpos($src, 'STOCK') !== false) $badgeClass = 'badge-source-stock';
                elseif (strpos($src, 'FALLBACK_FORMULA') !== false) $badgeClass = 'badge-source-fallback';
                elseif (strpos($src, 'FALLBACK') !== false || strpos($src, 'STD') !== false) $badgeClass = 'badge-source-std';
                else $badgeClass = 'badge-source-nodata';
              ?>
              <tr>
                <td><?php echo $i + 1; ?></td>
                <td>
                  <span class="badge <?php echo $isComponent ? 'bg-label-info text-info' : 'bg-label-primary text-primary'; ?>">
                    <?php echo $isComponent ? 'COMPONENT' : 'BAHAN BAKU'; ?>
                  </span>
                </td>
                <td>
                  <?php if ($usageUrl !== ''): ?>
                    <a href="<?php echo html_escape($usageUrl); ?>" class="hpp-link fw-semibold"><?php echo html_escape($referenceName); ?></a>
                  <?php else: ?>
                    <strong><?php echo html_escape($referenceName); ?></strong>
                  <?php endif; ?>
                  <?php if ($isComponent && !empty($line['formula_has_no_lines'])): ?>
                    <span class="badge bg-light text-warning border border-warning ms-1" title="Tidak ada formula">No Formula</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted"><?php echo html_escape((string)($line['source_division_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['qty'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($line['uom_name'] ?? '-')); ?></td>
                <td class="text-end small">
                  <?php
                    $stockQty = (float)($line['stock_qty'] ?? 0);
                    $stockClass = $stockQty > 0 ? 'text-success' : 'text-danger';
                  ?>
                  <?php if ($stockUrl !== ''): ?>
                    <a href="<?php echo html_escape($stockUrl); ?>" class="hpp-link <?php echo $stockClass; ?> fw-semibold"><?php echo number_format($stockQty, 2, ',', '.'); ?></a>
                  <?php else: ?>
                    <span class="<?php echo $stockClass; ?>"><?php echo number_format($stockQty, 2, ',', '.'); ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php echo number_format((float)($line['cost_by_stock'] ?? 0), 2, ',', '.'); ?>
                </td>
                <td class="text-end">
                  <?php if ($isComponent): ?>
                    <?php
                      $diffClass2 = $costDiff > 0.001 ? 'diff-positive' : ($costDiff < -0.001 ? 'diff-negative' : 'diff-zero');
                    ?>
                    <?php echo number_format((float)($line['cost_by_formula'] ?? 0), 2, ',', '.'); ?>
                    <?php if (abs($costDiff) > 0.001): ?>
                      <br><small class="<?php echo $diffClass2; ?>"><?php echo $costDiff > 0 ? '+' : ''; ?><?php echo number_format($costDiff, 2, ',', '.'); ?></small>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge small <?php echo $badgeClass; ?>"><?php echo html_escape((string)($line['stock_source_label'] ?? '-')); ?></span>
                </td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($line['line_total_stock'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-muted"><?php echo $isComponent ? number_format((float)($line['line_total_formula'] ?? 0), 2, ',', '.') : '<span class="text-muted">—</span>'; ?></td>
              </tr>
              <?php endforeach; ?>

              <!-- Subtotal direct -->
              <tr class="table-light">
                <td colspan="10" class="text-end fw-semibold">Direct Cost</td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($summary['direct_cost_stock'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-muted"><?php echo number_format((float)($summary['direct_cost_formula'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
              <!-- Variable cost -->
              <tr>
                <td colspan="10" class="text-end fw-semibold">Biaya Variabel — <?php echo html_escape($varMode); ?> (<?php echo number_format($varPct, 2, ',', '.'); ?>%)</td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($summary['variable_cost_stock'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-muted"><?php echo number_format((float)($summary['variable_cost_formula'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
              <!-- Total HPP -->
              <tr class="table-primary">
                <td colspan="10" class="text-end fw-bold">Total HPP</td>
                <td class="text-end fw-bold">Rp <?php echo number_format($totalStock, 2, ',', '.'); ?></td>
                <td class="text-end fw-bold text-muted">Rp <?php echo number_format($totalFormula, 2, ',', '.'); ?></td>
              </tr>
              <?php if ($sellingPrice > 0): ?>
              <tr>
                <td colspan="10" class="text-end small text-muted">% HPP (dari harga jual Rp <?php echo number_format($sellingPrice, 0, ',', '.'); ?>)</td>
                <td class="text-end fw-bold"><?php echo number_format($pctStock, 2, ',', '.'); ?>%</td>
                <td class="text-end text-muted"><?php echo number_format($pctFormula, 2, ',', '.'); ?>%</td>
              </tr>
              <?php endif; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
        <div class="small text-muted">
          <strong>Kolom Stok</strong>: cost dari stok komponen aktual (lot/monthly avg) &rarr; fallback ke formula jika kosong.<br>
          <strong>Kolom Formula</strong>: cost dari ekspansi resep komponen ke bahan baku dasar.
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-info btn-sm" href="<?php echo site_url('master/relation/product-recipe/' . (int)($product['id'] ?? 0)); ?>">Lihat Resep</a>
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('master/product'); ?>">Kembali</a>
        </div>
      </div>
    </div>
  </div>
</div>
