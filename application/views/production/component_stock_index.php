<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$stockMeta = is_array($stock_meta ?? null) ? $stock_meta : [];
$selectedMonth = (string)($filters['month'] ?? date('Y-m'));

$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];

$locationFilterValue = static function ($v): string {
  $v = strtoupper(trim((string)$v));
  if (in_array($v, ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT', 'EVENT'], true)) return 'EVENT';
  if (in_array($v, ['BAR', 'KITCHEN', 'ROASTERY', 'REGULER'], true)) return 'REGULER';
  return '';
};
$locGroup = static function ($v): string {
  $v = strtoupper(trim((string)$v));
  if (in_array($v, ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true))
    return '<span class="badge bg-danger-subtle text-danger px-1 py-0" style="font-size:.62rem">Event</span>';
  if (in_array($v, ['BAR', 'KITCHEN', 'ROASTERY'], true))
    return '<span class="badge bg-secondary-subtle text-secondary px-1 py-0" style="font-size:.62rem">Reguler</span>';
  return '';
};
$locBadge = static function ($v): string {
  $v = strtoupper(trim((string)$v));
  switch ($v) {
    case 'BAR':           return '<span class="badge bg-info-subtle text-info px-1 py-0" style="font-size:.65rem">BAR</span>';
    case 'KITCHEN':       return '<span class="badge bg-success-subtle text-success px-1 py-0" style="font-size:.65rem">KIT</span>';
    case 'BAR_EVENT':     return '<span class="badge bg-danger-subtle text-danger px-1 py-0" style="font-size:.65rem">BAR Ev</span>';
    case 'KITCHEN_EVENT': return '<span class="badge bg-warning-subtle text-warning px-1 py-0" style="font-size:.65rem">KIT Ev</span>';
    default:              return '<span class="badge bg-secondary-subtle text-secondary px-1 py-0" style="font-size:.65rem">' . htmlspecialchars($v) . '</span>';
  }
};
$typeBadge = static function ($v): string {
  $v = strtoupper(trim((string)$v));
  if ($v === 'BASE')    return '<span class="badge bg-primary-subtle text-primary px-1 py-0" style="font-size:.65rem">Base</span>';
  if ($v === 'PREPARE') return '<span class="badge bg-warning-subtle text-warning px-1 py-0" style="font-size:.65rem">Prep</span>';
  return '<span class="badge bg-secondary-subtle text-secondary px-1 py-0" style="font-size:.65rem">' . htmlspecialchars($v) . '</span>';
};
$fmtQty  = static fn($v) => number_format((float)$v, 2, ',', '.');
$fmtCost = static fn($v) => number_format((float)$v, 0, ',', '.');

$buildLotUrl = static function (array $row, string $status = 'ALL') use ($locationFilterValue): string {
  $params = array_filter([
    'q'             => trim((string)($row['component_code'] ?? $row['component_name'] ?? '')),
    'status'        => $status,
    'location_type' => $locationFilterValue((string)($row['location_type'] ?? '')),
    'division_id'   => !empty($row['division_id']) ? (int)$row['division_id'] : null,
    'type'          => strtoupper(trim((string)($row['component_type'] ?? ''))),
  ], static fn($v) => $v !== null && $v !== '');
  return site_url('production/component-lots') . '?' . http_build_query($params);
};
$lotAvgCost = static function (array $ls): float {
  $qty = (float)($ls['balance_qty'] ?? 0);
  return $qty > 0 ? round((float)($ls['total_value'] ?? 0) / $qty, 6) : 0.0;
};

/* Summary stats are calculated before server-side pagination. */
$totalComponents = (int)($stockMeta['total_rows'] ?? count($rows));
$totalNilai      = (float)($stockMeta['total_value'] ?? array_sum(array_column($rows, 'total_value')));
$countBase       = (int)($stockMeta['base_count'] ?? count(array_filter($rows, fn($r) => strtoupper($r['component_type'] ?? '') === 'BASE')));
$countPrepare    = (int)($stockMeta['prepare_count'] ?? count(array_filter($rows, fn($r) => strtoupper($r['component_type'] ?? '') === 'PREPARE')));
$countNegative   = (int)($stockMeta['negative_count'] ?? count(array_filter($rows, fn($r) => (float)($r['qty_on_hand'] ?? 0) < 0)));
$countZero       = (int)($stockMeta['zero_count'] ?? count(array_filter($rows, fn($r) => (float)($r['qty_on_hand'] ?? 0) == 0)));
$currentPage     = max(1, (int)($stockMeta['page'] ?? $filters['page'] ?? 1));
$perPage         = max(0, (int)($stockMeta['per_page'] ?? $filters['per_page'] ?? 25));
$maxPage         = max(1, (int)($stockMeta['max_page'] ?? 1));
$rangeStart      = $totalComponents > 0 ? (($perPage > 0 ? (($currentPage - 1) * $perPage) : 0) + 1) : 0;
$rangeEnd        = $totalComponents > 0 ? min($totalComponents, $rangeStart + count($rows) - 1) : 0;
$paginationParams = [
  'q' => trim((string)($filters['q'] ?? '')),
  'month' => $selectedMonth,
  'division_id' => (int)($filters['division_id'] ?? 0),
  'location_type' => (string)($filters['location_type'] ?? ''),
  'type' => (string)($filters['type'] ?? ''),
  'per_page' => $perPage,
];
$buildPageUrl = static function (int $page) use ($paginationParams): string {
  return site_url('production/component-stock') . '?' . http_build_query(array_merge($paginationParams, ['page' => $page]));
};
?>

<style>
  .csl-card {
    white-space: normal;
    line-height: 1.4;
  }
  .csl-body {
    display: flex;
    gap: .5rem;
    align-items: center;
  }
  .csl-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .3rem;
    flex-shrink: 0;
  }
  .csl-lot-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .csl-metric {
    border: 1px solid #eadccf;
    border-radius: 8px;
    background: #fffaf6;
    padding: .28rem .38rem;
  }
  .csl-metric .label {
    display: block;
    font-size: .62rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
  }
  .csl-metric strong {
    display: block;
    font-size: .76rem;
    color: #503125;
  }
  .csl-toggle {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border: 1px solid #e3d1c5;
    border-radius: 999px;
    padding: .12rem .45rem;
    background: #fff;
    color: #7a4c3f;
    font-size: .68rem;
    font-weight: 600;
    white-space: nowrap;
  }
  .csl-toggle i { transition: transform .18s ease; }
  .csl-toggle[aria-expanded="true"] i { transform: rotate(180deg); }
  .csl-single {
    padding: .32rem .42rem;
    border-radius: 8px;
    background: #fffdfb;
    border: 1px solid #efe0d5;
    font-size: .7rem;
    color: #6b4b40;
    flex: 1;
    min-width: 0;
    border-left: 2px solid #e8d5c8;
  }
  .csl-child-row td { background: #fffcf8 !important; }
  .csl-child-card {
    padding: .7rem .9rem;
    border: 1px solid #efdfd3;
    border-radius: 12px;
    background: linear-gradient(180deg, #fffdf9 0%, #fff8f3 100%);
  }
  .csl-child-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: .6rem;
    margin-top: .5rem;
  }
  .csl-child-item {
    border: 1px solid #ecdccf;
    border-radius: 12px;
    background: #fff;
    padding: .6rem .75rem;
  }
  .csl-child-item .lot-name { font-size: .8rem; font-weight: 700; color: #5f3527; }
  .csl-child-item .lot-meta { display: flex; flex-wrap: wrap; gap: .25rem .6rem; margin-top: .15rem; font-size: .68rem; color: #8a5b4d; }
  .csl-child-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .35rem;
    margin-top: .5rem;
  }
  .csl-child-metric {
    padding: .32rem .38rem;
    border: 1px solid #f0e1d6;
    border-radius: 8px;
    background: #fffaf6;
  }
  .csl-child-metric .label { display: block; font-size: .6rem; text-transform: uppercase; letter-spacing: .04em; color: #8a5b4d; }
  .csl-child-metric strong { display: block; font-size: .74rem; color: #503125; }
</style>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-scales-3-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Stok Base/Prepare'); ?></h4>
      <small class="text-muted">Read-only saldo component per bulan sesuai snapshot/generate bulan yang dipilih.</small>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'stock']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-stock'),
  'component_type_filters'  => $filters,
  'component_type_active'   => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'month'         => (string)($filters['month'] ?? ''),
    'division_id'   => !empty($filters['division_id']) ? (int)$filters['division_id'] : '',
    'location_type' => (string)($filters['location_type'] ?? ''),
  ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo site_url('production/component-stock'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control form-control-sm" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama / kode / divisi">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?php echo html_escape($selectedMonth); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select form-select-sm">
          <option value="0">Semua</option>
          <?php foreach ($divisions as $div): ?>
            <option value="<?php echo (int)($div['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($div['id'] ?? 0)) ? 'selected' : ''; ?>>
              <?php echo html_escape(trim((string)($div['division_code'] ?? $div['code'] ?? '')) . ' - ' . trim((string)($div['division_name'] ?? $div['name'] ?? ''))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select form-select-sm">
          <?php foreach ($locationFilterOptions as $k => $lbl): ?>
            <option value="<?php echo html_escape((string)$k); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$k) ? 'selected' : ''; ?>><?php echo html_escape($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Per Halaman</label>
        <select name="per_page" id="perPageSelect" class="form-select form-select-sm">
          <?php foreach ([25, 50, 100, 200, 0] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo ((int)($filters['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp > 0 ? $pp : 'Semua'; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-1 align-items-end">
        <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
        <a href="<?php echo site_url('production/component-stock'); ?>" class="btn btn-sm btn-outline-warning">Clear</a>
        <a href="<?php echo site_url('production/component-movements'); ?>" class="btn btn-sm btn-outline-secondary">Mutasi</a>
      </div>
    </form>
  </div>
</div>

<?php $this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan stok komponen',
  'stock_summary_cards' => [
    ['label' => 'Komponen', 'value' => number_format($totalComponents, 0, ',', '.'), 'tone' => 'violet', 'icon' => 'ri-stack-line'],
    ['label' => 'Base', 'value' => number_format($countBase, 0, ',', '.'), 'tone' => 'aqua', 'icon' => 'ri-flask-line'],
    ['label' => 'Prepare', 'value' => number_format($countPrepare, 0, ',', '.'), 'tone' => 'blue', 'icon' => 'ri-restaurant-2-line'],
    ['label' => 'Stok Nol', 'value' => number_format($countZero, 0, ',', '.'), 'tone' => 'amber', 'icon' => 'ri-inbox-line'],
    ['label' => 'Stok Minus', 'value' => number_format($countNegative, 0, ',', '.'), 'tone' => $countNegative > 0 ? 'danger' : 'blue', 'icon' => 'ri-alarm-warning-line'],
    ['label' => 'Total Nilai', 'value' => 'Rp ' . $fmtCost($totalNilai), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
  ],
]); ?>

<!-- Table -->
<div class="card">
  <div style="overflow:auto;max-height:70vh">
    <table class="table table-sm table-bordered table-hover mb-0" id="stockTable" style="min-width:800px;border-color:#dee2e6">
      <thead class="table-dark" style="position:sticky;top:0;z-index:2">
        <tr>
          <th style="width:90px">Divisi / Lokasi / Tipe</th>
          <th style="width:150px">Nama Komponen</th>
          <th style="width:260px" class="text-center">Ringkasan Lot</th>
          <th style="width:50px">UOM</th>
          <th class="text-end" style="width:85px">Qty</th>
          <th class="text-end" style="width:80px">Avg Cost</th>
          <th class="text-end" style="width:95px">Total Nilai</th>
          <th style="width:75px">Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4" role="status" aria-live="polite">Belum ada data stok komponen pada filter ini.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $ri => $row): ?>
            <?php
              $lotSummary  = is_array($row['lot_summary'] ?? null) ? $row['lot_summary'] : [];
              $lotRows     = array_values((array)($lotSummary['rows'] ?? []));
              $hasChildren = count($lotRows) > 1;
              $avgCost     = $lotAvgCost($lotSummary);
              $toggleId    = 'csl_' . $ri;
              $minCost     = (float)($lotSummary['min_unit_cost'] ?? 0);
              $maxCost     = (float)($lotSummary['max_unit_cost'] ?? 0);
              $uniformCost = abs($maxCost - $minCost) < 0.001;
              $qtyOnHand   = (float)($row['qty_on_hand'] ?? 0);
              $compType    = strtoupper(trim((string)($row['component_type'] ?? '')));
            ?>
            <tr class="stock-row">
              <td class="py-1 text-center">
                <div class="fw-semibold" style="font-size:.72rem"><?php echo html_escape(substr((string)($row['division_name'] ?? '-'), 0, 16)); ?></div>
                <div class="mt-1"><?php echo $locGroup((string)($row['location_type'] ?? '')); ?></div>
                <div class="mt-1"><?php echo $typeBadge($compType); ?></div>
              </td>
              <td class="py-1" style="width:150px">
                <a href="<?php echo html_escape(site_url('production/component-masters/usage/' . (int)($row['component_id'] ?? 0))); ?>" class="fw-semibold text-decoration-none text-body small"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></a>
                <div class="text-muted" style="font-size:.68rem"><?php echo html_escape((string)($row['component_code'] ?? '')); ?> <a href="<?php echo html_escape($buildLotUrl((array)$row, 'ALL')); ?>" class="text-muted" style="font-size:.64rem" title="Lihat lot">lots</a></div>
              </td>
              <td class="py-1 text-center">
                <div class="csl-card d-inline-block text-start">
                  <div class="csl-body">
                    <div class="csl-grid">
                      <div class="csl-metric">
                        <span class="label">QTY</span>
                        <strong><?php echo $fmtQty($row['qty_on_hand'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                      </div>
                      <div class="csl-metric">
                        <span class="label">NILAI</span>
                        <strong><?php echo $fmtCost($row['total_value'] ?? 0); ?></strong>
                      </div>
                      <div class="csl-metric">
                        <span class="label">AVG COST</span>
                        <strong><?php echo $fmtQty($hasChildren ? $avgCost : (float)($row['avg_cost'] ?? 0)); ?></strong>
                      </div>
                      <div class="csl-metric">
                        <span class="label"><?php echo $hasChildren ? 'RANGE LOT' : 'LOT COST'; ?></span>
                        <strong><?php
                          echo $fmtQty($minCost);
                          if (!$uniformCost) echo ' – ' . $fmtQty($maxCost);
                        ?></strong>
                      </div>
                    </div>
                    <div class="csl-lot-indicator">
                      <?php if ($hasChildren): ?>
                        <button type="button" class="csl-toggle" data-csl-toggle="<?php echo $toggleId; ?>" aria-expanded="false">
                          <span><?php echo (int)($lotSummary['lot_count'] ?? 0); ?> lot</span>
                          <i class="ri ri-arrow-down-s-line"></i>
                        </button>
                      <?php elseif (!empty($lotSummary['lot_count'])): ?>
                        <span class="badge bg-label-secondary" style="font-size:.65rem">1 lot</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td class="py-1 small"><?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></td>
              <td class="py-1 text-end small <?php echo $qtyOnHand < 0 ? 'text-danger fw-semibold' : ($qtyOnHand == 0 ? 'text-muted' : ''); ?>"><?php echo $fmtQty($qtyOnHand); ?></td>
              <td class="py-1 text-end small text-muted"><?php echo $fmtCost($row['avg_cost'] ?? 0); ?></td>
              <td class="py-1 text-end small"><?php echo $fmtCost($row['total_value'] ?? 0); ?></td>
              <td class="py-1 text-muted" style="font-size:.68rem;white-space:nowrap"><?php echo html_escape(substr((string)($row['updated_at'] ?? '-'), 0, 10)); ?></td>
            </tr>
            <?php if ($hasChildren): ?>
              <tr id="<?php echo $toggleId; ?>" class="csl-child-row d-none">
                <td colspan="8">
                  <div class="csl-child-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-1">
                      <div>
                        <div class="fw-semibold small">Rincian lot aktif</div>
                        <div class="small text-muted">Total <?php echo $fmtQty($lotSummary['balance_qty'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?> • avg <?php echo $fmtQty($avgCost); ?></div>
                      </div>
                      <a href="<?php echo html_escape($buildLotUrl((array)$row, 'OPEN')); ?>" class="btn btn-outline-secondary btn-sm">Buka FIFO lot</a>
                    </div>
                    <div class="csl-child-grid">
                      <?php foreach ($lotRows as $lr): ?>
                        <div class="csl-child-item">
                          <div class="lot-name"><?php echo html_escape((string)($lr['lot_no'] ?? '-')); ?></div>
                          <div class="lot-meta">
                            <span>Terima <?php echo html_escape((string)($lr['receipt_date'] ?? '-')); ?></span>
                            <?php if (!empty($lr['expiry_date'])): ?><span>Exp <?php echo html_escape((string)$lr['expiry_date']); ?></span><?php endif; ?>
                          </div>
                          <div class="csl-child-metrics">
                            <div class="csl-child-metric">
                              <span class="label">Qty</span>
                              <strong><?php echo $fmtQty($lr['qty_balance'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                            </div>
                            <div class="csl-child-metric">
                              <span class="label">HPP</span>
                              <strong><?php echo $fmtQty($lr['unit_cost'] ?? 0); ?></strong>
                            </div>
                            <div class="csl-child-metric">
                              <span class="label">Nilai</span>
                              <strong><?php echo $fmtCost($lr['total_value'] ?? 0); ?></strong>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer py-2 d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <span class="text-muted small" id="stockTableCount">
      <?php if ($totalComponents <= 0): ?>Tidak ada baris
      <?php elseif ($perPage <= 0): ?>Menampilkan semua <?php echo $totalComponents; ?> baris
      <?php else: ?>Menampilkan <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> dari <?php echo $totalComponents; ?> baris<?php endif; ?>
    </span>
    <?php if ($perPage > 0 && $maxPage > 1): ?>
      <nav aria-label="Navigasi halaman stok komponen" class="d-flex align-items-center gap-2">
        <?php if ($currentPage > 1): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo html_escape($buildPageUrl($currentPage - 1)); ?>">Sebelumnya</a>
        <?php endif; ?>
        <span class="text-muted small" aria-current="page">Halaman <?php echo $currentPage; ?> dari <?php echo $maxPage; ?></span>
        <?php if ($currentPage < $maxPage): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo html_escape($buildPageUrl($currentPage + 1)); ?>">Berikutnya</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</div>

<script>
(() => {
  /* expand/collapse lot children */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-csl-toggle]');
    if (!btn) return;
    const row = document.getElementById(btn.getAttribute('data-csl-toggle') || '');
    if (!row) return;
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    row.classList.toggle('d-none', expanded);
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
  });

})();
</script>
