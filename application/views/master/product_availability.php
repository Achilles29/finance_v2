<?php
$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$outletOptions = is_array($outlet_options ?? null) ? $outlet_options : [];
$productDivisionOptions = is_array($product_division_options ?? null) ? $product_division_options : [];
$statusOptions = is_array($status_options ?? null) ? $status_options : [];
$stockModeOptions = is_array($stock_mode_options ?? null) ? $stock_mode_options : [];
$currency = static function ($value, int $decimals = 2): string {
  return number_format((float)$value, $decimals, ',', '.');
};
$number = static function ($value, int $decimals = 2): string {
  return number_format((float)$value, $decimals, ',', '.');
};
?>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
    <div>
      <h4 class="mb-1"><i class="ri ri-line-chart-line page-title-icon"></i><?php echo html_escape($title ?? 'Monitoring Stok Produk'); ?></h4>
      <p class="text-muted mb-0">Halaman ini sekarang memakai engine live yang sama dengan POS stock-live. Qty dan HPP dihitung dari recipe serta stok riil per outlet, tanpa fallback ke HPP standar saat stok kosong.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo site_url('master/relation/product-recipe'); ?>" class="btn btn-outline-primary btn-sm">Halaman Resep</a>
      <a href="<?php echo site_url('master/product'); ?>" class="btn btn-outline-secondary btn-sm">Master Product</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-2 col-sm-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Total Produk</small>
          <div class="fs-4 fw-semibold"><?php echo (int)($summary['total_product'] ?? 0); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-sm-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Ready</small>
          <div class="fs-4 fw-semibold text-success"><?php echo (int)($summary['ready_count'] ?? 0); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-sm-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Warning</small>
          <div class="fs-4 fw-semibold text-warning"><?php echo (int)($summary['warning_count'] ?? 0); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-sm-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Terbatas / Habis</small>
          <div class="fs-4 fw-semibold text-danger"><?php echo (int)($summary['limited_count'] ?? 0); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-sm-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">HPP Alert</small>
          <div class="fs-4 fw-semibold text-warning"><?php echo (int)($summary['hpp_alert_count'] ?? 0); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-sm-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Tanpa Resep</small>
          <div class="fs-4 fw-semibold text-secondary"><?php echo (int)($summary['no_recipe_count'] ?? 0); ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3 border-0 shadow-sm">
    <div class="card-body">
      <form method="get" action="<?php echo site_url('product/availability'); ?>" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label mb-1">Cari Produk</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama / kode produk">
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Outlet</label>
          <select name="outlet_id" class="form-select">
            <option value="0">Pilih outlet</option>
            <?php foreach ($outletOptions as $option): ?>
              <option value="<?php echo (int)($option['id'] ?? 0); ?>" <?php echo (int)($filters['outlet_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($option['outlet_name'] ?? $option['outlet_code'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Divisi Produk</label>
          <select name="product_division_id" class="form-select">
            <option value="0">Semua Divisi</option>
            <?php foreach ($productDivisionOptions as $option): ?>
              <option value="<?php echo (int)($option['value'] ?? 0); ?>" <?php echo (int)($filters['product_division_id'] ?? 0) === (int)($option['value'] ?? 0) ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($option['label'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label mb-1">Status</label>
          <select name="status" class="form-select">
            <?php foreach ($statusOptions as $option): ?>
              <option value="<?php echo html_escape((string)($option['value'] ?? 'ALL')); ?>" <?php echo (string)($filters['status'] ?? 'ALL') === (string)($option['value'] ?? 'ALL') ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($option['label'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label mb-1">Mode Stok</label>
          <select name="stock_mode" class="form-select">
            <?php foreach ($stockModeOptions as $option): ?>
              <option value="<?php echo html_escape((string)($option['value'] ?? 'ALL')); ?>" <?php echo (string)($filters['stock_mode'] ?? 'ALL') === (string)($option['value'] ?? 'ALL') ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($option['label'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end">
          <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
          <a href="<?php echo site_url('product/availability'); ?>" class="btn btn-outline-danger w-100">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th width="56">No</th>
            <th>Produk</th>
            <th>Hierarki</th>
            <th class="text-center">Line</th>
            <th class="text-end">Qty Utama</th>
            <th class="text-end">Qty Semua</th>
            <th class="text-end">HPP Live</th>
            <th class="text-end">Estimasi Profit</th>
            <th class="text-end">Harga Jual</th>
            <th>Status</th>
            <th>Pembatas</th>
            <th width="120">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="12" class="text-center text-muted py-4">Tidak ada produk yang cocok dengan filter.</td>
            </tr>
          <?php else: ?>
            <?php $no = 1; foreach ($rows as $row): ?>
              <tr>
                <td><?php echo $no++; ?></td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['product_name'] ?? '-')); ?></div>
                </td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['product_division_name'] ?? '-')); ?></div>
                  <small class="text-muted d-block"><?php echo html_escape((string)($row['classification_name'] ?? '-')); ?></small>
                  <small class="text-muted d-block"><?php echo html_escape((string)($row['product_category_name'] ?? '-')); ?></small>
                  <small class="d-block mt-1"><span class="badge rounded-pill <?php echo html_escape((string)($row['stock_mode_class'] ?? 'bg-label-primary text-primary')); ?>"><?php echo html_escape((string)($row['stock_mode_label'] ?? 'Auto Resep')); ?></span></small>
                </td>
                <td class="text-center">
                  <div class="fw-semibold"><?php echo (int)($row['line_count'] ?? 0); ?></div>
                  <small class="text-muted"><?php echo (int)($row['material_count'] ?? 0); ?> material / <?php echo (int)($row['component_count'] ?? 0); ?> comp</small>
                </td>
                <td class="text-end">
                  <div class="fw-semibold"><?php echo (int)($row['availability_qty_main_floor'] ?? 0); ?></div>
                  <small class="text-muted d-block">presisi <?php echo $number((float)($row['availability_qty_main'] ?? 0), 2); ?></small>
                  <small class="text-muted d-block"><?php echo html_escape((string)($row['main_basis_label'] ?? '')); ?></small>
                </td>
                <td class="text-end">
                  <div class="fw-semibold"><?php echo (int)($row['availability_qty_all_floor'] ?? 0); ?></div>
                  <small class="text-muted d-block">presisi <?php echo $number((float)($row['availability_qty_all'] ?? 0), 2); ?></small>
                </td>
                <td class="text-end">
                  <div class="fw-semibold">Rp <?php echo $currency((float)($row['hpp_live'] ?? 0), 2); ?></div>
                  <small class="text-muted d-block"><?php echo $number((float)($row['hpp_live_percent'] ?? 0), 2); ?>%</small>
                  <small class="d-block mt-1"><span class="badge rounded-pill <?php echo html_escape((string)($row['hpp_status_class'] ?? 'bg-label-success text-success')); ?>"><?php echo html_escape((string)($row['hpp_status_label'] ?? 'Sehat')); ?></span></small>
                </td>
                <td class="text-end">
                  <div class="fw-semibold <?php echo (float)($row['estimated_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>">Rp <?php echo $currency((float)($row['estimated_profit'] ?? 0), 2); ?></div>
                </td>
                <td class="text-end">Rp <?php echo $currency((float)($row['selling_price'] ?? 0), 2); ?></td>
                <td>
                  <span class="badge rounded-pill <?php echo html_escape((string)($row['availability_status_class'] ?? 'bg-label-secondary text-secondary')); ?>"><?php echo html_escape((string)($row['availability_status_label'] ?? '-')); ?></span>
                </td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['blocking_line_label'] ?? '-')); ?></div>
                  <small class="text-muted d-block"><?php echo html_escape((string)($row['blocking_line_detail'] ?? '-')); ?></small>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="<?php echo site_url('master/relation/product-recipe/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-info" title="Detail HPP / Resep" aria-label="Detail HPP / Resep"><i class="ri ri-eye-line"></i></a>
                    <a href="<?php echo site_url('master/relation/product-recipe/edit-all/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary" title="Edit Resep" aria-label="Edit Resep"><i class="ri ri-edit-line"></i></a>
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
