<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];

$fmtQty = static function ($v): string {
  $f = round((float)$v, 2);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 2, ',', '.');
};
$fmtCost = static function ($v): string {
  $f = round((float)$v, 0);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 0, ',', '.');
};

$summaryRows      = count($rows);
$summaryDivisions = count(array_unique(array_column($rows, 'division_name')));
$summaryClosing   = 0.0;
$summaryValue     = 0.0;
foreach ($rows as $r) {
  $closing = (float)($r['closing_qty_content'] ?? 0);
  $summaryClosing += $closing;
  $storedVal = isset($r['total_value']) ? (float)$r['total_value'] : 0.0;
  $summaryValue += $storedVal > 0 ? $storedVal : ($closing * (float)($r['avg_cost_per_content'] ?? 0));
}

$activeMonth = (string)($filters['month'] ?? date('Y-m'));
$limitValue  = (int)($filters['limit'] ?? 200);
?>

<style>
  .sdop-scroll-wrap {
    max-height: 72vh;
    overflow-y: auto;
    overflow-x: auto;
  }
  .sdop-scroll-wrap table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    border-bottom: 2px solid rgba(0,0,0,.08);
    white-space: nowrap;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-file-list-3-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Opname Bahan Baku'); ?></h4>
  <small class="text-muted">Snapshot stok divisi hasil generate opname bulanan. Data mencerminkan posisi stok akhir bulan per divisi dan tipe destinasi.</small>
</div>

<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'opname_monthly']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $activeMonth, 'division_id' => (string)($filters['division_id'] ?? ''), 'destination_type' => (string)($filters['destination_type'] ?? '')],
]); ?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo site_url('inventory/stock/opname/division/monthly'); ?>" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" name="month" class="form-control" value="<?php echo html_escape($activeMonth); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisions as $div): ?>
            <option value="<?php echo (int)($div['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($div['id'] ?? 0)) ? 'selected' : ''; ?>>
              <?php echo html_escape(trim((string)($div['division_code'] ?? $div['code'] ?? '')) . ' - ' . trim((string)($div['division_name'] ?? $div['name'] ?? ''))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Tujuan</label>
        <select name="destination_type" class="form-select">
          <option value="">Semua</option>
          <option value="REGULER" <?php echo ($filters['destination_type'] ?? '') === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT"   <?php echo ($filters['destination_type'] ?? '') === 'EVENT'   ? 'selected' : ''; ?>>Event</option>
          <option value="BAR"     <?php echo ($filters['destination_type'] ?? '') === 'BAR'     ? 'selected' : ''; ?>>Bar Reguler</option>
          <option value="KITCHEN" <?php echo ($filters['destination_type'] ?? '') === 'KITCHEN' ? 'selected' : ''; ?>>Kitchen Reguler</option>
          <option value="ROASTERY" <?php echo ($filters['destination_type'] ?? '') === 'ROASTERY' ? 'selected' : ''; ?>>Roastery Reguler</option>
          <option value="BAR_EVENT"     <?php echo ($filters['destination_type'] ?? '') === 'BAR_EVENT'     ? 'selected' : ''; ?>>Bar Event</option>
          <option value="KITCHEN_EVENT" <?php echo ($filters['destination_type'] ?? '') === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>Kitchen Event</option>
          <option value="ROASTERY_EVENT" <?php echo ($filters['destination_type'] ?? '') === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>Roastery Event</option>
          <option value="OFFICE"  <?php echo ($filters['destination_type'] ?? '') === 'OFFICE'  ? 'selected' : ''; ?>>Office</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama item / profile / divisi">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" name="limit" class="form-control" min="1" max="2000" value="<?php echo $limitValue; ?>">
      </div>
      <div class="col-auto d-grid">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
      </div>
      <div class="col-auto d-grid">
        <a href="<?php echo site_url('inventory/stock/opname/division/monthly'); ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php if ($summaryRows > 0): ?>
<?php $this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan opname bahan baku',
  'stock_summary_cards' => [
    ['label' => 'Baris Opname', 'value' => number_format($summaryRows), 'tone' => 'violet', 'icon' => 'ri-file-list-3-line'],
    ['label' => 'Divisi', 'value' => number_format($summaryDivisions), 'tone' => 'aqua', 'icon' => 'ri-building-2-line'],
    ['label' => 'Total Closing', 'value' => number_format($summaryClosing, 2, ',', '.'), 'detail' => 'isi', 'tone' => 'amber', 'icon' => 'ri-scales-3-line'],
    ['label' => 'Nilai Closing', 'value' => 'Rp ' . number_format($summaryValue, 0, ',', '.'), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
  ],
]); ?>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="alert alert-info">
    Belum ada data opname bulanan divisi untuk bulan <?php echo html_escape($activeMonth); ?>.
    Klik <strong>Generate Opname &amp; Stok Awal</strong> untuk membuat snapshot dari data stok bulan ini.
  </div>
<?php else: ?>
  <div class="card">
    <div class="sdop-scroll-wrap">
      <table class="table table-sm table-hover align-middle small mb-0">
        <thead>
          <tr>
            <th>Divisi</th>
            <th>Destinasi</th>
            <th>Nama Item</th>
            <th>UOM (Isi)</th>
            <th class="text-end">Opening</th>
            <th class="text-end">Masuk</th>
            <th class="text-end">Keluar</th>
            <th class="text-end">Waste</th>
            <th class="text-end">Spoil</th>
            <th class="text-end">Adj+</th>
            <th class="text-end">Adj-</th>
            <th class="text-end">Closing</th>
            <th class="text-end">Avg Cost</th>
            <th>Di-generate</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
              <td><span class="badge bg-secondary-subtle text-secondary"><?php echo html_escape((string)($row['destination_type'] ?? '-')); ?></span></td>
              <td>
                <div class="fw-semibold"><?php $matIdOp = (int)($row['material_id'] ?? 0); echo $matIdOp > 0 ? '<a href="' . html_escape(site_url('master/material/usage/' . $matIdOp)) . '" class="text-decoration-none text-body">' . html_escape((string)($row['profile_name'] ?? '-')) . '</a>' : html_escape((string)($row['profile_name'] ?? '-')); ?></div>
                <?php if (!empty($row['profile_brand'])): ?>
                  <div class="text-muted" style="font-size:.72rem"><?php echo html_escape((string)$row['profile_brand']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo html_escape((string)($row['profile_content_uom_code'] ?? '-')); ?></td>
              <td class="text-end"><?php echo $fmtQty($row['opening_qty_content'] ?? 0); ?></td>
              <td class="text-end"><?php echo $fmtQty($row['in_qty_content'] ?? 0); ?></td>
              <td class="text-end"><?php echo $fmtQty($row['out_qty_content'] ?? 0); ?></td>
              <td class="text-end"><?php echo $fmtQty($row['waste_qty_content'] ?? 0); ?></td>
              <td class="text-end"><?php echo $fmtQty($row['spoil_qty_content'] ?? 0); ?></td>
              <td class="text-end"><?php echo $fmtQty($row['adjustment_plus_qty_content'] ?? 0); ?></td>
              <td class="text-end"><?php echo $fmtQty($row['adjustment_minus_qty_content'] ?? $row['variance_qty_content'] ?? 0); ?></td>
              <td class="text-end fw-semibold"><?php echo $fmtQty($row['closing_qty_content'] ?? 0); ?></td>
              <td class="text-end text-muted"><?php echo $fmtCost($row['avg_cost_per_content'] ?? 0); ?></td>
              <td class="text-muted" style="white-space:nowrap">
                <?php if (!empty($row['generated_by_name'])): ?>
                  <div><?php echo html_escape((string)$row['generated_by_name']); ?></div>
                <?php endif; ?>
                <?php if (!empty($row['generated_at'])): ?>
                  <div style="font-size:.7rem"><?php echo html_escape(substr((string)$row['generated_at'], 0, 16)); ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer text-muted small py-1"><?php echo $summaryRows; ?> baris<?php echo $summaryRows >= $limitValue ? ' (limit ' . $limitValue . ' — tambah limit jika belum semua tampil)' : ''; ?></div>
  </div>
<?php endif; ?>
