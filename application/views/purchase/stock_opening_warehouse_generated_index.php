<?php
$rowsData = is_array($rows ?? null) ? $rows : [];
$selMonth = (string)($month ?? '');
$qSearch = (string)($q ?? '');
$perPage = max(10, (int)($per_page ?? 25));
$page = max(1, (int)($page ?? 1));

$fmtQty = static function ($v): string {
    $f = round((float)$v, 2);
    return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 2, ',', '.');
};
$fmtVal = static function ($v): string {
    $f = round((float)$v, 0);
    return $f == 0 ? '<span class="text-muted">-</span>' : 'Rp ' . number_format($f, 0, ',', '.');
};

$kpiTotal = count($rowsData);
$kpiQty = 0.0;
$kpiValue = 0.0;
$kpiMonths = [];
foreach ($rowsData as $row) {
    $kpiQty += (float)($row['opening_qty_content'] ?? 0);
    $kpiValue += (float)($row['opening_total_value'] ?? 0);
    if (!empty($row['snapshot_month'])) {
        $kpiMonths[(string)$row['snapshot_month']] = true;
    }
}

$totalRows = count($rowsData);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$pagedRows = array_slice($rowsData, ($page - 1) * $perPage, $perPage);
$baseParams = array_filter([
    'per_page' => $perPage,
    'month' => $selMonth,
    'q' => $qSearch,
]);
$baseUrl = site_url('inventory/stock/stok-awal/warehouse');
$sourceMonthLabel = '';
if (preg_match('/^\d{4}-\d{2}$/', $selMonth)) {
    $sourceMonthLabel = date('Y-m', strtotime($selMonth . '-01 -1 month'));
}
?>

<style>
  .swg-table-wrap { max-height: 74vh; overflow-y: auto; overflow-x: auto; }
  .swg-table-wrap table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    border-bottom: 2px solid rgba(0,0,0,.08);
    white-space: nowrap;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-archive-drawer-line page-title-icon"></i>Stok Awal Gudang</h4>
  <small class="text-muted">
    Stok awal gudang hasil generate otomatis dari closing opname bulan sebelumnya.
    Halaman ini khusus menampilkan output carry-forward, terpisah dari opening manual gudang.
  </small>
</div>

<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'WAREHOUSE', 'active_tab' => 'stok_awal']); ?>
</div>
<?php $this->load->view('purchase/_warehouse_stock_generate_btn', [
  'warehouse_action_params' => ['month' => $selMonth],
]); ?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan Opening</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?php echo html_escape($selMonth); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control form-control-sm" value="<?php echo html_escape($qSearch); ?>" placeholder="Nama item / profile / profile key">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Baris</label>
        <input type="number" name="per_page" class="form-control form-control-sm" min="10" max="200" value="<?php echo $perPage; ?>">
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php if ($kpiTotal > 0): ?>
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Profil Gudang</div><div class="h5 mb-0"><?php echo number_format($kpiTotal); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Total Stok Awal (Isi)</div><div class="h5 mb-0"><?php echo number_format($kpiQty, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Total Nilai</div><div class="h5 mb-0">Rp <?php echo number_format($kpiValue, 0, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Bulan Output</div><div class="h5 mb-0"><?php echo !empty($kpiMonths) ? number_format(count($kpiMonths)) : 0; ?></div></div></div></div>
</div>
<?php endif; ?>

<?php if ($selMonth === ''): ?>
  <div class="alert alert-info small">
    Pilih bulan opening untuk melihat stok awal gudang hasil generate.
    Jika Anda generate opname bulan sumber, output stok awal akan masuk ke bulan berikutnya.
  </div>
<?php elseif (empty($pagedRows)): ?>
  <div class="alert alert-warning small">
    Belum ada stok awal gudang otomatis untuk bulan <strong><?php echo html_escape($selMonth); ?></strong>.
    <?php if ($sourceMonthLabel !== ''): ?>
      Generate opname gudang bulan <strong><?php echo html_escape($sourceMonthLabel); ?></strong> terlebih dahulu.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="alert alert-info small">
    Bulan opening <strong><?php echo html_escape($selMonth); ?></strong>
    <?php if ($sourceMonthLabel !== ''): ?>
      berasal dari closing opname bulan <strong><?php echo html_escape($sourceMonthLabel); ?></strong>.
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="swg-table-wrap">
      <table class="table table-sm table-hover align-middle small mb-0">
        <thead>
          <tr>
            <th>Bulan Opening</th>
            <th>Profil Gudang</th>
            <th>UOM</th>
            <th class="text-end">Qty Stok Awal (Isi)</th>
            <th class="text-end">Qty Stok Awal (Pack)</th>
            <th class="text-end">Avg Cost / Isi</th>
            <th class="text-end">Nilai Stok Awal</th>
            <th>Di-update</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pagedRows as $row): ?>
            <tr>
              <td class="fw-semibold" style="white-space:nowrap">
                <?php echo !empty($row['snapshot_month']) ? html_escape(date('M Y', strtotime((string)$row['snapshot_month']))) : '-'; ?>
              </td>
              <td>
                <div class="fw-semibold"><?php echo html_escape((string)($row['profile_name'] ?? $row['material_name'] ?? $row['item_name'] ?? '-')); ?></div>
                <?php if (!empty($row['profile_brand'])): ?>
                  <div class="text-muted" style="font-size:.72rem"><?php echo html_escape((string)$row['profile_brand']); ?></div>
                <?php endif; ?>
                <?php if (!empty($row['profile_key'])): ?>
                  <div class="text-muted" style="font-size:.68rem"><?php echo html_escape((string)$row['profile_key']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo html_escape((string)($row['profile_content_uom_code'] ?? '-')); ?></td>
              <td class="text-end fw-semibold"><?php echo $fmtQty($row['opening_qty_content'] ?? 0); ?></td>
              <td class="text-end text-muted"><?php echo $fmtQty($row['opening_qty_buy'] ?? 0); ?></td>
              <td class="text-end text-muted">
                <?php
                  $cost = (float)($row['opening_avg_cost_per_content'] ?? 0);
                  echo $cost > 0 ? number_format($cost, 2, ',', '.') : '<span class="text-muted">-</span>';
                ?>
              </td>
              <td class="text-end"><?php echo $fmtVal($row['opening_total_value'] ?? 0); ?></td>
              <td class="text-muted" style="white-space:nowrap;font-size:.72rem">
                <?php echo !empty($row['updated_at']) ? html_escape(substr((string)$row['updated_at'], 0, 16)) : '-'; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer py-2 d-flex align-items-center gap-2 flex-wrap">
      <span class="text-muted small"><?php echo $totalRows; ?> baris · halaman <?php echo $page; ?>/<?php echo $totalPages; ?></span>
      <nav class="ms-auto">
        <ul class="pagination pagination-sm mb-0">
          <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge(array_map('strval', $baseParams), ['page' => $page - 1])); ?>">‹</a></li>
          <?php endif; ?>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge(array_map('strval', $baseParams), ['page' => $p])); ?>"><?php echo $p; ?></a>
            </li>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge(array_map('strval', $baseParams), ['page' => $page + 1])); ?>">›</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
    <?php else: ?>
    <div class="card-footer text-muted small py-1"><?php echo $totalRows; ?> baris stok awal gudang</div>
    <?php endif; ?>
  </div>
<?php endif; ?>
