<?php
$rowsData    = is_array($rows ?? null) ? $rows : [];
$divisions   = is_array($divisions ?? null) ? $divisions : [];
$selMonth    = (string)($month ?? '');
$selDiv      = (int)($division_id ?? 0);
$selDest     = strtoupper((string)($destination ?? 'ALL'));
$qSearch     = (string)($q ?? '');
$perPage     = max(10, (int)($per_page ?? 25));
$page        = max(1, (int)($page ?? 1));

$fmtQty = static function ($v): string {
    $f = round((float)$v, 2);
    return $f == 0 ? '<span class="text-muted">–</span>' : number_format($f, 2, ',', '.');
};
$fmtVal = static function ($v): string {
    $f = round((float)$v, 0);
    return $f == 0 ? '<span class="text-muted">–</span>' : 'Rp ' . number_format($f, 0, ',', '.');
};

// KPI
$kpiTotal  = count($rowsData);
$kpiQty    = 0.0;
$kpiValue  = 0.0;
$kpiDivSet = [];
$kpiMonths = [];
foreach ($rowsData as $r) {
    $qty = (float)($r['opening_qty_content'] ?? 0);
    $val = (float)($r['opening_total_value'] ?? 0);
    $kpiQty   += $qty;
    $kpiValue += $val;
    if (!empty($r['division_id'])) $kpiDivSet[(int)$r['division_id']] = true;
    if (!empty($r['snapshot_month'])) $kpiMonths[$r['snapshot_month']] = true;
}

// Pagination
$totalRows  = count($rowsData);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$pagedRows  = array_slice($rowsData, ($page - 1) * $perPage, $perPage);
$pBase      = array_filter(['per_page' => $perPage, 'month' => $selMonth, 'q' => $qSearch, 'destination' => $selDest !== 'ALL' ? $selDest : null, 'division_id' => $selDiv ?: null]);
$pQs        = http_build_query($pBase);
$baseUrl    = site_url('inventory/stock/stok-awal/division');
?>

<style>
  .sag-table-wrap { max-height: 74vh; overflow-y: auto; overflow-x: auto; }
  .sag-table-wrap table thead th {
    position: sticky; top: 0; z-index: 2;
    background: #fff; border-bottom: 2px solid rgba(0,0,0,.08); white-space: nowrap;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-archive-drawer-line page-title-icon"></i>Stok Awal Bahan Baku</h4>
  <small class="text-muted">
    Stok awal bahan baku divisi hasil generate otomatis — carry-forward dari closing opname bulan sebelumnya.
    Data ini menjadi saldo pembuka bulan berikutnya untuk setiap profil bahan baku.
  </small>
</div>

<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'stok_awal']); ?>
</div>

<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => [
    'month'        => $selMonth,
    'division_id'  => (string)$selDiv,
    'destination_type' => $selDest !== 'ALL' ? $selDest : '',
  ],
]); ?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan Opening</label>
        <input type="month" name="month" class="form-control form-control-sm"
               value="<?php echo html_escape($selMonth); ?>"
               placeholder="YYYY-MM">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select form-select-sm">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisions as $div): ?>
            <option value="<?php echo (int)($div['id'] ?? 0); ?>"
              <?php echo $selDiv === (int)($div['id'] ?? 0) ? 'selected' : ''; ?>>
              <?php echo html_escape(trim(($div['division_code'] ?? $div['code'] ?? '') . ' – ' . ($div['division_name'] ?? $div['name'] ?? ''))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Destinasi</label>
        <select name="destination" class="form-select form-select-sm">
          <option value="ALL">Semua</option>
          <option value="REGULER"      <?php echo $selDest === 'REGULER'      ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT"        <?php echo $selDest === 'EVENT'        ? 'selected' : ''; ?>>Event</option>
          <option value="BAR"          <?php echo $selDest === 'BAR'          ? 'selected' : ''; ?>>Bar Reguler</option>
          <option value="KITCHEN"      <?php echo $selDest === 'KITCHEN'      ? 'selected' : ''; ?>>Kitchen Reguler</option>
          <option value="BAR_EVENT"    <?php echo $selDest === 'BAR_EVENT'    ? 'selected' : ''; ?>>Bar Event</option>
          <option value="KITCHEN_EVENT"<?php echo $selDest === 'KITCHEN_EVENT'? 'selected' : ''; ?>>Kitchen Event</option>
          <option value="ROASTERY"     <?php echo $selDest === 'ROASTERY'     ? 'selected' : ''; ?>>Roastery Reguler</option>
          <option value="ROASTERY_EVENT"<?php echo $selDest === 'ROASTERY_EVENT'? 'selected' : ''; ?>>Roastery Event</option>
          <option value="OFFICE"       <?php echo $selDest === 'OFFICE'       ? 'selected' : ''; ?>>Office</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control form-control-sm"
               value="<?php echo html_escape($qSearch); ?>"
               placeholder="Nama item / profil / divisi">
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
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-2">
      <div class="small text-muted">Profil Bahan Baku</div>
      <div class="h5 mb-0"><?php echo number_format($kpiTotal); ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-2">
      <div class="small text-muted">Divisi</div>
      <div class="h5 mb-0"><?php echo number_format(count($kpiDivSet)); ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-2">
      <div class="small text-muted">Total Stok Awal (Isi)</div>
      <div class="h5 mb-0"><?php echo number_format($kpiQty, 2, ',', '.'); ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-2">
      <div class="small text-muted">Total Nilai</div>
      <div class="h5 mb-0">Rp <?php echo number_format($kpiValue, 0, ',', '.'); ?></div>
    </div></div>
  </div>
</div>
<?php endif; ?>

<?php if ($selMonth === ''): ?>
  <div class="alert alert-info small">
    Pilih bulan opening di filter untuk melihat stok awal.
    Stok awal ini di-generate otomatis dari closing opname bulan sebelumnya.
    Klik <strong>Generate Opname &amp; Stok Awal</strong> dengan bulan sumber untuk membuat atau memperbarui data.
  </div>
<?php elseif (empty($pagedRows)): ?>
  <div class="alert alert-warning small">
    Belum ada stok awal otomatis untuk bulan <strong><?php echo html_escape($selMonth); ?></strong>.
    Generate opname bulan <?php
      $prevMonthTs = strtotime($selMonth . '-01 -1 month');
      echo $prevMonthTs ? date('Y-m', $prevMonthTs) : 'sebelumnya';
    ?> terlebih dahulu — hasilnya akan menjadi stok awal bulan ini.
  </div>
<?php else: ?>

  <div class="card">
    <div class="sag-table-wrap">
      <table class="table table-sm table-hover align-middle small mb-0">
        <thead>
          <tr>
            <th>Bulan Opening</th>
            <th>Divisi</th>
            <th>Destinasi</th>
            <th>Profil / Bahan Baku</th>
            <th>UOM</th>
            <th class="text-end">Qty Stok Awal (Isi)</th>
            <th class="text-end">Qty Stok Awal (Pack)</th>
            <th class="text-end">Avg Cost / Isi</th>
            <th class="text-end">Nilai Stok Awal</th>
            <th>Di-update</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pagedRows as $r): ?>
            <?php
              $destType  = (string)($r['destination_type'] ?? 'OTHER');
              $destBadge = match($destType) {
                'BAR'          => 'bg-info-subtle text-info',
                'BAR_EVENT'    => 'bg-info-subtle text-info',
                'KITCHEN'      => 'bg-warning-subtle text-warning',
                'KITCHEN_EVENT'=> 'bg-warning-subtle text-warning',
                'EVENT'        => 'bg-purple-subtle text-purple',
                'OFFICE'       => 'bg-secondary-subtle text-secondary',
                default        => 'bg-secondary-subtle text-secondary',
              };
              $qtyContent = (float)($r['opening_qty_content'] ?? 0);
              $qtyBuy     = (float)($r['opening_qty_buy'] ?? 0);
              $cost       = (float)($r['opening_avg_cost_per_content'] ?? 0);
              $value      = (float)($r['opening_total_value'] ?? 0);
            ?>
            <tr>
              <td class="fw-semibold" style="white-space:nowrap">
                <?php echo html_escape((string)($r['snapshot_month'] ? date('M Y', strtotime((string)$r['snapshot_month'])) : '–')); ?>
              </td>
              <td style="white-space:nowrap">
                <div><?php echo html_escape((string)($r['division_name'] ?? '–')); ?></div>
                <div class="text-muted" style="font-size:.7rem"><?php echo html_escape((string)($r['division_code'] ?? '')); ?></div>
              </td>
              <td><span class="badge <?php echo $destBadge; ?>"><?php echo html_escape($destType); ?></span></td>
              <td>
                <div class="fw-semibold"><?php echo html_escape((string)($r['profile_name'] ?? $r['material_name'] ?? $r['item_name'] ?? '–')); ?></div>
                <?php if (!empty($r['profile_brand'])): ?>
                  <div class="text-muted" style="font-size:.7rem"><?php echo html_escape((string)$r['profile_brand']); ?></div>
                <?php endif; ?>
                <?php if (!empty($r['material_code'])): ?>
                  <div class="text-muted" style="font-size:.68rem"><?php echo html_escape((string)$r['material_code']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo html_escape((string)($r['profile_content_uom_code'] ?? '–')); ?></td>
              <td class="text-end fw-semibold <?php echo $qtyContent <= 0 ? 'text-danger' : ''; ?>">
                <?php echo $fmtQty($qtyContent); ?>
              </td>
              <td class="text-end text-muted"><?php echo $fmtQty($qtyBuy); ?></td>
              <td class="text-end text-muted"><?php echo $cost > 0 ? number_format($cost, 2, ',', '.') : '<span class="text-muted">–</span>'; ?></td>
              <td class="text-end"><?php echo $fmtVal($value); ?></td>
              <td class="text-muted" style="white-space:nowrap;font-size:.72rem">
                <?php echo !empty($r['updated_at']) ? html_escape(substr((string)$r['updated_at'], 0, 16)) : '–'; ?>
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
            <li class="page-item"><a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge(array_map('strval', $pBase), ['page' => $page - 1])); ?>">‹</a></li>
          <?php endif; ?>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge(array_map('strval', $pBase), ['page' => $p])); ?>"><?php echo $p; ?></a>
            </li>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge(array_map('strval', $pBase), ['page' => $page + 1])); ?>">›</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
    <?php else: ?>
    <div class="card-footer text-muted small py-1">
      <?php echo $totalRows; ?> baris stok awal
      <?php echo !empty($kpiMonths) ? '· bulan opening: ' . implode(', ', array_map(fn($m) => date('M Y', strtotime($m)), array_keys($kpiMonths))) : ''; ?>
    </div>
    <?php endif; ?>
  </div>

<?php endif; ?>
