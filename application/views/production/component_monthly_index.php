<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];

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
$typeBadge = static function ($v): string {
  $v = strtoupper(trim((string)$v));
  if ($v === 'BASE')    return '<span class="badge bg-primary-subtle text-primary px-1 py-0" style="font-size:.65rem">Base</span>';
  if ($v === 'PREPARE') return '<span class="badge bg-warning-subtle text-warning px-1 py-0" style="font-size:.65rem">Prep</span>';
  return '<span class="badge bg-secondary-subtle text-secondary px-1 py-0" style="font-size:.65rem">' . htmlspecialchars($v) . '</span>';
};
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
$divisionLabel = static function (array $row): string {
  $code = trim((string)($row['division_code'] ?? $row['code'] ?? ''));
  $name = trim((string)($row['division_name'] ?? $row['name'] ?? ''));
  if ($code !== '' && $name !== '') return $code . ' - ' . $name;
  return $name !== '' ? $name : ($code !== '' ? $code : '-');
};
$lotAvgCost = static function (array $ls): float {
  $qty = (float)($ls['balance_qty'] ?? 0);
  return $qty > 0 ? round((float)($ls['total_value'] ?? 0) / $qty, 6) : 0.0;
};
$fmt  = static fn($v) => number_format((float)$v, 2, ',', '.');
$fmtC = static fn($v) => number_format((float)$v, 0, ',', '.');

/* Summary stats */
$totalRows     = count($rows);
$totalNilai    = array_sum(array_column($rows, 'total_value'));
$totalClosing  = array_sum(array_column($rows, 'closing_qty'));
$totalIn       = array_sum(array_column($rows, 'in_qty'));
$totalOut      = array_sum(array_column($rows, 'out_qty'));
$countNegative = count(array_filter($rows, fn($r) => (float)($r['closing_qty'] ?? 0) < 0));
?>

<style>
  .cml-card {
    white-space: normal;
    line-height: 1.4;
    overflow: hidden;
  }
  .cml-body {
    display: flex;
    gap: .4rem;
    align-items: center;
  }
  .cml-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .25rem;
    flex: 1;
    min-width: 0;
  }
  .cml-metric {
    border: 1px solid #eadccf;
    border-radius: 7px;
    background: #fffaf6;
    padding: .22rem .3rem;
    min-width: 0;
    overflow: hidden;
  }
  .cml-metric .label {
    display: block;
    font-size: .58rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
  }
  .cml-metric strong {
    display: block;
    font-size: .72rem;
    color: #503125;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .cml-toggle {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border: 1px solid #e3d1c5;
    border-radius: 999px;
    padding: .1rem .4rem;
    background: #fff;
    color: #7a4c3f;
    font-size: .66rem;
    font-weight: 600;
    white-space: nowrap;
  }
  .cml-toggle i { transition: transform .18s ease; }
  .cml-toggle[aria-expanded="true"] i { transform: rotate(180deg); }
  .cml-child-row td { background: #fffcf8 !important; }
  .cml-child-card {
    padding: .65rem .85rem;
    border: 1px solid #efdfd3;
    border-radius: 12px;
    background: linear-gradient(180deg, #fffdf9 0%, #fff8f3 100%);
  }
  .cml-child-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: .55rem;
    margin-top: .45rem;
  }
  .cml-child-item {
    border: 1px solid #ecdccf;
    border-radius: 10px;
    background: #fff;
    padding: .55rem .7rem;
  }
  .cml-child-item .lot-name { font-size: .78rem; font-weight: 700; color: #5f3527; }
  .cml-child-item .lot-meta { display: flex; flex-wrap: wrap; gap: .2rem .55rem; margin-top: .12rem; font-size: .66rem; color: #8a5b4d; }
  .cml-child-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .3rem;
    margin-top: .45rem;
  }
  .cml-child-metric {
    padding: .28rem .34rem;
    border: 1px solid #f0e1d6;
    border-radius: 7px;
    background: #fffaf6;
  }
  .cml-child-metric .label { display: block; font-size: .58rem; text-transform: uppercase; letter-spacing: .04em; color: #8a5b4d; }
  .cml-child-metric strong { display: block; font-size: .72rem; color: #503125; }
</style>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-bar-chart-box-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Stok Bulanan Base/Prepare'); ?></h4>
      <small class="text-muted">Rekap harian per bulan: opening, mutasi, waste, spoil, adjustment, dan closing.</small>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'monthly']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-monthly'),
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
    <form method="get" action="<?php echo site_url('production/component-monthly'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control form-control-sm" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama komponen / divisi">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select form-select-sm">
          <option value="0">Semua</option>
          <?php foreach ($divisions as $division): ?>
            <?php $optId = (int)($division['id'] ?? 0); ?>
            <option value="<?php echo $optId; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === $optId) ? 'selected' : ''; ?>><?php echo html_escape($divisionLabel((array)$division)); ?></option>
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
      <div class="col-md-1">
        <label class="form-label mb-1">Per Hal.</label>
        <select name="per_page" id="perPageSelect" class="form-select form-select-sm">
          <?php foreach ([25, 50, 100, 0] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo ((int)($filters['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp > 0 ? $pp : 'Semua'; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-1 align-items-end">
        <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
        <a href="<?php echo site_url('production/component-monthly'); ?>" class="btn btn-sm btn-outline-warning">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php $this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan stok bulanan komponen',
  'stock_summary_cards' => [
    ['label' => 'Komponen', 'value' => number_format($totalRows), 'tone' => 'violet', 'icon' => 'ri-stack-line'],
    ['label' => 'Total Masuk', 'value' => $fmtC($totalIn), 'tone' => 'aqua', 'icon' => 'ri-arrow-down-circle-line'],
    ['label' => 'Total Keluar', 'value' => $fmtC($totalOut), 'tone' => 'blue', 'icon' => 'ri-arrow-up-circle-line'],
    ['label' => 'Total Closing', 'value' => $fmtC($totalClosing), 'tone' => 'amber', 'icon' => 'ri-scales-3-line'],
    ['label' => 'Stok Minus', 'value' => number_format($countNegative), 'tone' => $countNegative > 0 ? 'danger' : 'blue', 'icon' => 'ri-alarm-warning-line'],
    ['label' => 'Total Nilai', 'value' => 'Rp ' . $fmtC($totalNilai), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
  ],
]); ?>

<!-- Table -->
<div class="card">
  <div style="overflow:auto;max-height:70vh">
    <table class="table table-sm table-bordered table-hover mb-0" id="monthlyTable" style="min-width:1000px;border-color:#dee2e6;table-layout:fixed">
      <thead class="table-dark" style="position:sticky;top:0;z-index:2">
        <tr>
          <th style="width:70px">Tipe</th>
          <th style="width:145px">Nama Komponen</th>
          <th style="width:210px" class="text-center">Ringkasan Lot</th>
          <th class="text-end" style="width:65px">Opening</th>
          <th class="text-end" style="width:55px">In</th>
          <th class="text-end" style="width:55px">Out</th>
          <th class="text-end" style="width:55px">Adj</th>
          <th class="text-end" style="width:50px">Waste</th>
          <th class="text-end" style="width:50px">Spoil</th>
          <th class="text-end" style="width:65px">Closing</th>
          <th class="text-end" style="width:55px">Avg Cost</th>
          <th class="text-end" style="width:60px">Nilai</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="12" class="text-center text-muted py-4">Belum ada data stok bulanan component pada filter ini.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $ri => $row): ?>
            <?php
              $lotSummary  = is_array($row['lot_summary'] ?? null) ? $row['lot_summary'] : [];
              $lotRows     = array_values((array)($lotSummary['rows'] ?? []));
              $hasChildren = count($lotRows) > 1;
              $avgCost     = $lotAvgCost($lotSummary);
              $toggleId    = 'cml_' . $ri;
              $minCost     = (float)($lotSummary['min_unit_cost'] ?? 0);
              $maxCost     = (float)($lotSummary['max_unit_cost'] ?? 0);
              $uniformCost = abs($maxCost - $minCost) < 0.001;
              $closingQty  = (float)($row['closing_qty'] ?? 0);
              $compType    = strtoupper(trim((string)($row['component_type'] ?? '')));
              $uom         = html_escape((string)($row['uom_code'] ?? ''));
            ?>
            <tr class="monthly-row">
              <td class="py-1 text-center">
                <div class="fw-semibold" style="font-size:.72rem"><?php echo html_escape(substr((string)($row['division_name'] ?? '-'), 0, 16)); ?></div>
                <div class="mt-1"><?php echo $locGroup((string)($row['location_type'] ?? '')); ?></div>
                <div class="mt-1"><?php echo $typeBadge($compType); ?></div>
              </td>
              <td class="py-1">
                <a href="<?php echo html_escape(site_url('production/component-masters/usage/' . (int)($row['component_id'] ?? 0))); ?>" class="fw-semibold text-decoration-none text-body small"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></a>
                <div class="text-muted" style="font-size:.67rem">(<?php echo html_escape(strtolower((string)($row['uom_code'] ?? ''))); ?>) <a href="<?php echo html_escape($buildLotUrl((array)$row, 'ALL')); ?>" class="text-muted" style="font-size:.64rem" title="Lihat lot">lots</a></div>
              </td>
              <td class="py-1">
                <div class="cml-card">
                  <div class="cml-body">
                    <div class="cml-grid">
                      <div class="cml-metric">
                        <span class="label">Closing</span>
                        <strong><?php echo $fmt($row['closing_qty'] ?? 0); ?> <?php echo $uom; ?></strong>
                      </div>
                      <div class="cml-metric">
                        <span class="label">Nilai</span>
                        <strong><?php echo $fmtC($row['total_value'] ?? 0); ?></strong>
                      </div>
                      <div class="cml-metric">
                        <span class="label"><?php echo $hasChildren ? 'Avg Parent' : 'Avg Cost'; ?></span>
                        <strong><?php echo $fmt($hasChildren ? $avgCost : (float)($row['avg_cost'] ?? 0)); ?></strong>
                      </div>
                      <div class="cml-metric">
                        <span class="label"><?php echo $hasChildren ? 'Range Lot' : 'Lot Cost'; ?></span>
                        <strong><?php
                          echo $fmt($minCost);
                          if (!$uniformCost) echo ' – ' . $fmt($maxCost);
                        ?></strong>
                      </div>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;flex-shrink:0">
                      <?php if ($hasChildren): ?>
                        <button type="button" class="cml-toggle" data-cml-toggle="<?php echo $toggleId; ?>" aria-expanded="false">
                          <span><?php echo (int)($lotSummary['lot_count'] ?? 0); ?> lot</span>
                          <i class="ri ri-arrow-down-s-line"></i>
                        </button>
                      <?php elseif (!empty($lotSummary['lot_count'])): ?>
                        <span class="badge bg-label-secondary" style="font-size:.63rem">1 lot</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td class="py-1 text-end" style="font-size:.8rem"><?php echo $fmt($row['opening_qty'] ?? 0); ?></td>
              <td class="py-1 text-end" style="font-size:.8rem"><?php echo $fmt($row['in_qty'] ?? 0); ?></td>
              <td class="py-1 text-end" style="font-size:.8rem"><?php echo $fmt($row['out_qty'] ?? 0); ?></td>
              <td class="py-1 text-end" style="font-size:.8rem"><?php echo $fmt($row['adjustment_qty'] ?? 0); ?></td>
              <td class="py-1 text-end" style="font-size:.8rem"><?php echo $fmt($row['waste_qty'] ?? 0); ?></td>
              <td class="py-1 text-end" style="font-size:.8rem"><?php echo $fmt($row['spoil_qty'] ?? 0); ?></td>
              <td class="py-1 text-end fw-semibold <?php echo $closingQty < 0 ? 'text-danger' : ''; ?>" style="font-size:.8rem"><?php echo $fmt($closingQty); ?></td>
              <td class="py-1 text-end text-muted" style="font-size:.8rem"><?php echo $fmt($row['avg_cost'] ?? 0); ?></td>
              <td class="py-1 text-end" style="font-size:.8rem"><?php echo $fmtC($row['total_value'] ?? 0); ?></td>
            </tr>
            <?php if ($hasChildren): ?>
              <tr id="<?php echo $toggleId; ?>" class="cml-child-row d-none">
                <td colspan="12">
                  <div class="cml-child-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-1">
                      <div>
                        <div class="fw-semibold small">Rincian lot aktif</div>
                        <div class="small text-muted">Total <?php echo $fmt($lotSummary['balance_qty'] ?? 0); ?> <?php echo $uom; ?> • avg <?php echo $fmt($avgCost); ?></div>
                      </div>
                      <a href="<?php echo html_escape($buildLotUrl((array)$row, 'OPEN')); ?>" class="btn btn-outline-secondary btn-sm">Buka FIFO lot</a>
                    </div>
                    <div class="cml-child-grid">
                      <?php foreach ($lotRows as $lr): ?>
                        <div class="cml-child-item">
                          <div class="lot-name"><?php echo html_escape((string)($lr['lot_no'] ?? '-')); ?></div>
                          <div class="lot-meta">
                            <span>Terima <?php echo html_escape((string)($lr['receipt_date'] ?? '-')); ?></span>
                            <?php if (!empty($lr['expiry_date'])): ?><span>Exp <?php echo html_escape((string)$lr['expiry_date']); ?></span><?php endif; ?>
                          </div>
                          <div class="cml-child-metrics">
                            <div class="cml-child-metric">
                              <span class="label">Qty</span>
                              <strong><?php echo $fmt($lr['qty_balance'] ?? 0); ?> <?php echo $uom; ?></strong>
                            </div>
                            <div class="cml-child-metric">
                              <span class="label">HPP</span>
                              <strong><?php echo $fmt($lr['unit_cost'] ?? 0); ?></strong>
                            </div>
                            <div class="cml-child-metric">
                              <span class="label">Nilai</span>
                              <strong><?php echo $fmtC($lr['total_value'] ?? 0); ?></strong>
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
  <div class="card-footer py-1 d-flex justify-content-between align-items-center">
    <span class="text-muted small" id="monthlyTableCount"><?php echo $totalRows; ?> baris</span>
    <span class="text-muted small" id="monthlyTablePager"></span>
  </div>
</div>

<script>
(() => {
  /* expand/collapse lot children */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cml-toggle]');
    if (!btn) return;
    const row = document.getElementById(btn.getAttribute('data-cml-toggle') || '');
    if (!row) return;
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    row.classList.toggle('d-none', expanded);
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
  });

  /* client-side pagination */
  const perPageSel = document.getElementById('perPageSelect');
  const table      = document.getElementById('monthlyTable');
  const countEl    = document.getElementById('monthlyTableCount');
  const pagerEl    = document.getElementById('monthlyTablePager');

  function applyPagination() {
    if (!table) return;
    const allRows = Array.from(table.querySelectorAll('tbody tr.monthly-row'));
    const perPage = perPageSel ? parseInt(perPageSel.value, 10) || 0 : 0;
    let shown = 0;
    allRows.forEach((tr, i) => {
      const hide = perPage > 0 && i >= perPage;
      tr.style.display = hide ? 'none' : '';
      const next = tr.nextElementSibling;
      if (next && next.classList.contains('cml-child-row') && hide) next.classList.add('d-none');
      if (!hide) shown++;
    });
    if (countEl) countEl.textContent = shown + ' dari ' + allRows.length + ' baris';
    if (pagerEl) pagerEl.textContent = perPage > 0 && allRows.length > perPage
      ? 'Menampilkan ' + shown + ' baris pertama' : '';
  }

  if (perPageSel) perPageSel.addEventListener('change', applyPagination);
  applyPagination();
})();
</script>
