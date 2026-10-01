<?php
$row = $row ?? [];
$entity = $entity ?? 'product';
$recipeCount = (int)($recipe_count ?? 0);
$extraGroupCount = (int)($extra_group_count ?? 0);
$variableDefaults = $variable_cost_defaults ?? [];
$defaultVariablePercent = (float)($variableDefaults['product'] ?? 0);
$photoPath = trim((string)($row['photo_path'] ?? ''));
$photoSrc = '';
if ($photoPath !== '') {
    if (preg_match('#^https?://#i', $photoPath)) {
        $photoSrc = $photoPath;
    } else {
        $photoSrc = base_url(ltrim($photoPath, '/'));
    }
}
$currency = static function ($value, int $precision = 2): string {
    return number_format((float)$value, $precision, ',', '.');
};
$stockModeText = static function ($value): string {
  $normalized = strtoupper(trim((string)$value));
  if ($normalized === 'MANUAL_AVAILABLE') {
    return 'Tersedia';
  }
  if ($normalized === 'MANUAL_OUT') {
    return 'Habis';
  }
  return 'Auto';
};
$flagText = static function ($value): string {
    return (int)$value === 1 ? 'Ya' : 'Tidak';
};
?>

<style>
  .product-detail-hero {
    border: 1px solid #efe2d9;
    border-radius: 18px;
    background: linear-gradient(135deg, #fff8f3 0%, #fff 58%, #f7f1ec 100%);
    overflow: hidden;
  }
  .product-detail-hero .product-photo {
    width: 140px;
    height: 140px;
    border-radius: 18px;
    object-fit: cover;
    border: 1px solid #eadbd2;
    background: #fff;
  }
  .product-metric-card {
    border: 1px solid #eee2d8;
    border-radius: 16px;
    background: #fff;
    height: 100%;
  }
  .product-kv small {
    display: block;
    color: #8c7a73;
    margin-bottom: 0.2rem;
  }
  .product-kv .value {
    font-weight: 600;
    color: #3c2f2a;
    word-break: break-word;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Detail Product'); ?></h4>
    <small class="text-muted">ID Product: <?php echo (int)($row['id'] ?? 0); ?></small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?php echo site_url('master/' . $entity); ?>" class="btn btn-outline-secondary">Kembali</a>
    <a href="<?php echo site_url('master/' . $entity . '/edit/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-primary">Edit Product</a>
    <a href="<?php echo site_url('master/relation/product-recipe/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-outline-info">Resep Product</a>
    <a href="<?php echo site_url('master/relation/product-extra/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-outline-info">Mapping Extra</a>
  </div>
</div>

<div class="card product-detail-hero mb-3">
  <div class="card-body p-4">
    <div class="row g-4 align-items-center">
      <div class="col-lg-8">
        <div class="d-flex gap-3 align-items-start flex-wrap">
          <?php if ($photoSrc !== ''): ?>
            <img src="<?php echo html_escape($photoSrc); ?>" alt="Foto Product" class="product-photo">
          <?php else: ?>
            <div class="product-photo d-flex align-items-center justify-content-center text-muted">No Photo</div>
          <?php endif; ?>
          <div>
            <div class="text-muted small mb-1"><?php echo html_escape((string)($row['product_division_name'] ?? '-')); ?> / <?php echo html_escape((string)($row['classification_name'] ?? '-')); ?></div>
            <h3 class="mb-2"><?php echo html_escape((string)($row['product_name'] ?? '-')); ?></h3>
            <div class="mb-2"><span class="badge bg-label-secondary">Kode: <?php echo html_escape((string)($row['product_code'] ?? '-')); ?></span></div>
            <p class="mb-0 text-muted"><?php echo nl2br(html_escape(trim((string)($row['description'] ?? '')) !== '' ? (string)$row['description'] : '-')); ?></p>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="row g-2">
          <div class="col-6">
            <div class="product-metric-card p-3">
              <small class="text-muted d-block mb-1">Harga Jual</small>
              <div class="fs-5 fw-semibold">Rp <?php echo $currency($row['selling_price'] ?? 0, 2); ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="product-metric-card p-3">
              <small class="text-muted d-block mb-1">HPP Standar</small>
              <div class="fs-5 fw-semibold">Rp <?php echo $currency($row['hpp_standard'] ?? 0, 4); ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="product-metric-card p-3">
              <small class="text-muted d-block mb-1">HPP Live Cache</small>
              <div class="fs-5 fw-semibold">Rp <?php echo $currency($row['hpp_live_cache'] ?? 0, 4); ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="product-metric-card p-3">
              <small class="text-muted d-block mb-1">Relasi Aktif</small>
              <div class="fs-6 fw-semibold"><?php echo $recipeCount; ?> resep / <?php echo $extraGroupCount; ?> extra group</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Struktur Product</h6>
        <div class="row g-3 product-kv">
          <div class="col-md-6"><small>Divisi Produk</small><div class="value"><?php echo html_escape((string)($row['product_division_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small>Klasifikasi</small><div class="value"><?php echo html_escape((string)($row['classification_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small>Kategori</small><div class="value"><?php echo html_escape((string)($row['category_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small>Satuan</small><div class="value"><?php echo html_escape((string)($row['uom_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small>Divisi Operasional Default</small><div class="value"><?php echo html_escape((string)($row['operational_division_name'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small>Status</small><div class="value"><?php echo (int)($row['is_active'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif'; ?></div></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Costing dan Visibility</h6>
        <div class="row g-3 product-kv">
          <div class="col-md-6"><small>Mode Variable Cost</small><div class="value"><?php echo html_escape((string)($row['variable_cost_mode'] ?? '-')); ?></div></div>
          <div class="col-md-6"><small>Variable Cost %</small><div class="value"><?php echo $currency($row['variable_cost_percent'] ?? 0, 4); ?>%</div></div>
          <div class="col-md-6"><small>Default Variable Cost</small><div class="value"><?php echo $currency($defaultVariablePercent, 4); ?>%</div></div>
          <div class="col-md-6"><small>Mode Stok</small><div class="value"><?php echo html_escape($stockModeText($row['stock_mode'] ?? '-')); ?></div></div>
          <div class="col-md-4"><small>Tampil di POS</small><div class="value"><?php echo $flagText($row['show_pos'] ?? 0); ?></div></div>
          <div class="col-md-4"><small>Tampil di Member</small><div class="value"><?php echo $flagText($row['show_member'] ?? 0); ?></div></div>
          <div class="col-md-4"><small>Tampil di Landing</small><div class="value"><?php echo $flagText($row['show_landing'] ?? 0); ?></div></div>
          <div class="col-md-6"><small>HPP Dirty</small><div class="value"><?php echo $flagText($row['hpp_dirty'] ?? 0); ?></div></div>
          <div class="col-md-6"><small>HPP Live At</small><div class="value"><?php echo html_escape((string)($row['hpp_live_at'] ?? '-')); ?></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h6 class="mb-3">Metadata Sistem</h6>
    <div class="row g-3 product-kv">
      <div class="col-md-3"><small>ID</small><div class="value"><?php echo (int)($row['id'] ?? 0); ?></div></div>
      <div class="col-md-3"><small>Photo Path</small><div class="value"><?php echo html_escape((string)($row['photo_path'] ?? '-')); ?></div></div>
      <div class="col-md-3"><small>Photo Mime</small><div class="value"><?php echo html_escape((string)($row['photo_mime'] ?? '-')); ?></div></div>
      <div class="col-md-3"><small>Dibuat Pada</small><div class="value"><?php echo html_escape((string)($row['created_at'] ?? '-')); ?></div></div>
      <div class="col-md-3"><small>Diubah Pada</small><div class="value"><?php echo html_escape((string)($row['updated_at'] ?? '-')); ?></div></div>
    </div>
  </div>
</div>
