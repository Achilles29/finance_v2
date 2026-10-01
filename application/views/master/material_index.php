<?php
// ── Persiapan data ────────────────────────────────────────────────────────────
$baseUrl       = site_url('master/material');
$rows          = is_array($rows ?? null) ? $rows : [];
$materialExt   = is_array($material_extended ?? null) ? $material_extended : [];
$divisionOpts  = is_array($material_division_options ?? null) ? $material_division_options : [];
$listFilterOpts = is_array($list_filter_options ?? null) ? $list_filter_options : [];

$statusParam   = (string)($status ?? 'active');
$qParam        = (string)($q ?? '');
$perPageParam  = (int)($per_page ?? 25);
$pageParam     = (int)($page ?? 1);
$catFilterId   = (int)($cat_filter_value ?? $list_filter_values['item_category_id'] ?? 0);
$divisionFilter = (int)($division_filter_value ?? 0);
$sortBy        = (string)($sort_by_value ?? 'cat_id');
$totalPages    = (int)($total_pages ?? 1);
$total         = (int)($total ?? 0);
$masterMutationCsrfToken = (string)($master_mutation_csrf_token ?? '');

$queryBase = ['q' => $qParam, 'status' => $statusParam, 'per_page' => $perPageParam, 'sort_by' => $sortBy];
if ($catFilterId > 0)    $queryBase['item_category_id'] = $catFilterId;
if ($divisionFilter > 0) $queryBase['division_filter']  = $divisionFilter;

$matNum = static function(float $v, int $dec = 2): string {
    return number_format($v, $dec, ',', '.');
};
$matRp = static function(float $v): string {
    if ($v <= 0) return '<span class="text-muted">-</span>';
    return 'Rp ' . number_format($v, 2, ',', '.');
};
?>

<style>
  .mat-table { font-size:.875rem; }
  .mat-table th { font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; vertical-align:middle; }
  .mat-table td { vertical-align:middle; padding:.6rem .65rem; }
  .mat-div-stack { display:flex; flex-direction:column; gap:.28rem; }
  .mat-div-row { display:flex; align-items:flex-start; gap:.35rem; flex-wrap:wrap; }
  .mat-div-badge { font-size:.72rem; font-weight:700; background:#e8f3ef; color:#1a6450; border:1px solid #b6d9ce; border-radius:999px; padding:.12rem .52rem; white-space:nowrap; }
  .mat-stock-isi { font-size:.82rem; font-weight:600; color:#2d3a36; }
  .mat-stock-pack { font-size:.75rem; color:#6a7a74; margin-top:.05rem; }
  .mat-stock-zero { color:#b0b8b4; font-size:.78rem; }
  .mat-hpp-live { font-weight:700; color:#1a6450; }
  .mat-hpp-live-link { font-weight:700; color:#1a6450; text-decoration:none; border-bottom:1px dashed #1a6450; }
  .mat-hpp-live-link:hover { color:#0d4535; }
  .mat-hpp-std { color:#7a6a60; }
  .mat-cat-badge { font-size:.72rem; background:#f5ede7; color:#6b4432; border-radius:8px; padding:.15rem .55rem; white-space:nowrap; }
  .mat-warn { display:inline-flex; align-items:center; gap:.25rem; font-size:.72rem; color:#a8561a; background:#fff3e0; border:1px solid #f8c47c; border-radius:6px; padding:.1rem .4rem; }
  .action-cell { white-space:nowrap; text-align:right; width:1%; }
  /* Samakan dengan standar global action-icon-btn */
  .mat-table .action-cell { --fin-btn-icon-sz: 30px; }
  .mat-diff-up { color:#c0392b; font-size:.75rem; }
  .mat-diff-dn { color:#27ae60; font-size:.75rem; }
</style>

<div class="fin-page-header mb-3">
  <div>
    <p class="fin-breadcrumb"><a href="<?php echo site_url('master'); ?>">Master Data</a> / Bahan Baku</p>
    <h4 class="fin-page-title">Master Bahan Baku</h4>
    <p class="fin-page-subtitle">HPP live dari stok bulanan divisi · Divisi penggunaan dari resep komponen &amp; produk</p>
  </div>
  <div class="fin-page-actions">
    <a href="<?php echo site_url('purchase/item-price-history'); ?>" class="btn btn-outline-info btn-sm">
      <i class="ri ri-line-chart-line me-1"></i>Riwayat Harga
    </a>
    <a href="<?php echo site_url('master/material/create'); ?>" class="btn btn-primary btn-sm">
      <i class="ri ri-add-line me-1"></i>Tambah
    </a>
  </div>
</div>

<!-- Tab Status -->
<div class="card mb-2">
  <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
    <div class="btn-group" role="group">
      <?php foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'all' => 'Semua'] as $sk => $sl): ?>
        <?php $sq = http_build_query(array_merge($queryBase, ['status' => $sk, 'page' => 1])); ?>
        <a href="<?php echo $baseUrl . '?' . $sq; ?>"
           class="btn btn-sm <?php echo $statusParam === $sk ? 'btn-primary' : 'btn-outline-secondary'; ?>">
          <?php echo $sl; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <small class="text-muted"><?php echo number_format($total); ?> bahan</small>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <input type="hidden" name="status" value="<?php echo html_escape($statusParam); ?>">

      <!-- Search -->
      <div class="col-12 col-md-3">
        <label class="form-label mb-1 small">Cari Nama / Kode</label>
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Nama atau kode bahan..." value="<?php echo html_escape($qParam); ?>">
      </div>

      <!-- Kategori -->
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small">Kategori</label>
        <select name="item_category_id" class="form-select form-select-sm">
          <option value="">Semua</option>
          <?php foreach ((array)($listFilterOpts['item_category_id'] ?? []) as $opt): ?>
            <option value="<?php echo (int)$opt['value']; ?>"
              <?php echo $catFilterId === (int)$opt['value'] ? 'selected' : ''; ?>>
              <?php echo html_escape($opt['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Divisi -->
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small">Divisi</label>
        <select name="division_filter" class="form-select form-select-sm">
          <option value="">Semua</option>
          <?php foreach ($divisionOpts as $dOpt): ?>
            <option value="<?php echo (int)$dOpt['value']; ?>"
              <?php echo $divisionFilter === (int)$dOpt['value'] ? 'selected' : ''; ?>>
              <?php echo html_escape($dOpt['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Sort -->
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small">Urutan</label>
        <select name="sort_by" class="form-select form-select-sm">
          <option value="cat_id"   <?php echo $sortBy === 'cat_id'   ? 'selected' : ''; ?>>Kategori (default)</option>
          <option value="cat_name" <?php echo $sortBy === 'cat_name' ? 'selected' : ''; ?>>Nama Kategori</option>
          <option value="name"     <?php echo $sortBy === 'name'     ? 'selected' : ''; ?>>Nama Bahan</option>
        </select>
      </div>

      <!-- Per Page + Actions -->
      <div class="col-6 col-md-1">
        <label class="form-label mb-1 small">Per Hal</label>
        <select name="per_page" class="form-select form-select-sm">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $pp === $perPageParam ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">
          <i class="ri ri-search-line"></i> Filter
        </button>
        <a href="<?php echo $baseUrl . '?status=active'; ?>" class="btn btn-sm btn-outline-danger" title="Reset filter">
          <i class="ri ri-close-line"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Tabel -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle mb-0 mat-table">
      <thead class="table-light">
        <tr>
          <th style="width:40px">No</th>
          <th>Kategori</th>
          <th>Nama Bahan</th>
          <th>Satuan</th>
          <th class="text-end">HPP Standar</th>
          <th class="text-end">HPP Live</th>
          <th>Divisi &amp; Stok</th>
          <th class="text-center" style="width:125px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-5">
              <i class="ri ri-inbox-line ri-2x d-block mb-2"></i>
              Belum ada data bahan baku<?php echo $qParam ? ' yang cocok' : ''; ?>.
            </td>
          </tr>
        <?php else: ?>
          <?php
            $no = ($pageParam - 1) * $perPageParam + 1;
            foreach ($rows as $r):
              $rId     = (int)($r['id'] ?? 0);
              $ext     = $materialExt[$rId] ?? ['hpp_live' => 0.0, 'divisions' => [], 'stock' => []];
              $hppStd  = (float)($r['hpp_standard'] ?? 0);
              $hppLive = (float)($ext['hpp_live'] ?? 0);
              $catName = (string)($r['category_name'] ?? '-');
              $uomName = (string)($r['uom_label'] ?? ($r['content_uom_id_label'] ?? '-'));
              $isActive = (int)($r['is_active'] ?? 1);
              $divUsage = (array)($ext['divisions'] ?? []);
              $stocks   = (array)($ext['stock'] ?? []);
              $allDivIds = array_unique(array_merge(array_keys($divUsage), array_keys($stocks)));
              $inAnyRecipe = !empty($divUsage);
          ?>
          <tr>
            <td class="text-muted small"><?php echo $no++; ?></td>
            <td>
              <?php if ($catName !== '-' && $catName !== ''): ?>
                <span class="mat-cat-badge"><?php echo html_escape($catName); ?></span>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold"><?php echo html_escape($r['material_name'] ?? '-'); ?></div>
            </td>
            <td class="text-muted"><?php echo html_escape($uomName); ?></td>
            <td class="text-end mat-hpp-std small">
              <?php echo $matRp($hppStd); ?>
            </td>
            <td class="text-end">
              <?php
                $priceHistUrl = site_url('purchase/item-price-history?material_id=' . $rId);
              ?>
              <?php if ($hppLive > 0): ?>
                <a href="<?php echo $priceHistUrl; ?>" class="mat-hpp-live-link">
                  <?php echo $matRp($hppLive); ?>
                </a>
                <?php if ($hppStd > 0): ?>
                  <?php $diff = (($hppLive - $hppStd) / $hppStd) * 100; ?>
                  <div class="<?php echo $diff >= 0 ? 'mat-diff-up' : 'mat-diff-dn'; ?>">
                    <?php echo ($diff >= 0 ? '▲' : '▼') . ' ' . $matNum(abs($diff), 1) . '%'; ?>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <a href="<?php echo $priceHistUrl; ?>"
                   class="text-muted small text-decoration-none">dari std →</a>
              <?php endif; ?>
            </td>
            <td>
              <?php if (empty($allDivIds)): ?>
                <?php if (!$inAnyRecipe): ?>
                  <span class="mat-warn"><i class="ri ri-alert-line"></i>Belum di resep</span>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              <?php else: ?>
                <div class="mat-div-stack">
                  <?php foreach ($allDivIds as $dId):
                    $dId       = (int)$dId;
                    $dName     = $divUsage[$dId] ?? ($stocks[$dId]['division_name'] ?? "Divisi #{$dId}");
                    $stockInfo = $stocks[$dId] ?? null;
                    $inRecipe  = isset($divUsage[$dId]);
                    $sqContent = $stockInfo !== null ? (float)$stockInfo['stock_qty']     : 0.0;
                    $sqPack    = $stockInfo !== null ? (float)$stockInfo['stock_qty_buy']  : 0.0;
                    $buyUom    = trim((string)($stockInfo['buy_uom_code'] ?? ''));
                    $buyLabel  = $buyUom !== '' ? $buyUom : 'BELI';
                  ?>
                  <div class="mat-div-row">
                    <span class="mat-div-badge"><?php echo html_escape($dName); ?></span>
                    <div>
                      <div class="mat-stock-isi <?php echo $sqContent <= 0 ? 'mat-stock-zero' : ''; ?>">
                        <?php echo $matNum($sqContent, 2); ?> <?php echo html_escape($uomName); ?>
                      </div>
                      <div class="mat-stock-pack <?php echo $sqPack <= 0 ? 'mat-stock-zero' : ''; ?>">
                        <?php echo $matNum($sqPack, 2); ?> <?php echo html_escape($buyLabel); ?>
                      </div>
                    </div>
                    <?php if (!$inRecipe): ?>
                      <span class="mat-warn" title="Ada stok divisi ini tapi tidak terdeteksi di resep aktif">
                        <i class="ri ri-alert-line"></i>stok tanpa resep
                      </span>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="action-cell">
              <div class="d-flex gap-1 flex-nowrap justify-content-end">
                <a href="<?php echo site_url('purchase/item-price-history?material_id=' . $rId); ?>"
                   class="btn btn-sm btn-outline-secondary action-icon-btn"
                   data-bs-toggle="tooltip" title="Riwayat Harga" aria-label="Riwayat Harga">
                  <i class="ri ri-line-chart-line"></i>
                </a>
                <a href="<?php echo site_url('master/material/usage/' . $rId); ?>"
                   class="btn btn-sm btn-outline-info action-icon-btn"
                   data-bs-toggle="tooltip" title="Penggunaan di Resep" aria-label="Penggunaan di Resep">
                  <i class="ri ri-file-list-3-line"></i>
                </a>
                <a href="<?php echo site_url('master/material/edit/' . $rId); ?>"
                   class="btn btn-sm btn-outline-primary action-icon-btn"
                   data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                  <i class="ri ri-edit-line"></i>
                </a>
                <form method="post" action="<?php echo site_url('master/material/toggle/' . $rId); ?>" class="d-inline" onsubmit="return confirm('Ubah status bahan baku ini?')">
                  <input type="hidden" name="master_mutation_csrf" value="<?php echo html_escape($masterMutationCsrfToken); ?>">
                  <button type="submit"
                          class="btn btn-sm btn-outline-warning action-icon-btn"
                          data-bs-toggle="tooltip"
                          title="<?php echo $isActive ? 'Nonaktifkan' : 'Aktifkan'; ?>"
                          aria-label="Toggle Status">
                    <i class="ri ri-refresh-line"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
    <small class="text-muted">
      Hal. <?php echo $pageParam; ?>/<?php echo $totalPages; ?> · <?php echo number_format($total); ?> total
    </small>
    <div class="d-flex gap-1">
      <?php
        $prev = max(1, $pageParam - 1);
        $next = min($totalPages, $pageParam + 1);
        $prevQ = http_build_query(array_merge($queryBase, ['page' => $prev]));
        $nextQ = http_build_query(array_merge($queryBase, ['page' => $next]));
        $pageItems = $totalPages <= 7 ? range(1, $totalPages) : [];
        if ($totalPages > 7) {
            $pageItems = [1];
            if ($pageParam > 3) $pageItems[] = '...';
            foreach (range(max(2, $pageParam-1), min($totalPages-1, $pageParam+1)) as $p) $pageItems[] = $p;
            if ($pageParam < $totalPages - 2) $pageItems[] = '...';
            $pageItems[] = $totalPages;
        }
      ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo $pageParam <= 1 ? 'disabled' : ''; ?>"
         href="<?php echo $baseUrl . '?' . $prevQ; ?>"><i class="ri ri-arrow-left-s-line"></i></a>
      <?php foreach ($pageItems as $pi): ?>
        <?php if ($pi === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">…</span>
        <?php else: ?>
          <?php $piQ = http_build_query(array_merge($queryBase, ['page' => $pi])); ?>
          <a class="btn btn-sm <?php echo $pageParam === $pi ? 'btn-primary' : 'btn-outline-secondary'; ?>"
             href="<?php echo $baseUrl . '?' . $piQ; ?>"><?php echo $pi; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo $pageParam >= $totalPages ? 'disabled' : ''; ?>"
         href="<?php echo $baseUrl . '?' . $nextQ; ?>"><i class="ri ri-arrow-right-s-line"></i></a>
    </div>
  </div>
  <?php endif; ?>
</div>
