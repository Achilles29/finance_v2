<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT') return 'Event';
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY') return 'Reguler';
  return $value !== '' ? $value : '-';
};
$movementOptions = [
  ''                 => 'Semua Jenis',
  'OPENING'          => 'Opening',
  'PRODUCTION_IN'    => 'Hasil Produksi',
  'PRODUCTION_OUT'   => 'Pemakaian Produksi',
  'TRANSFER_IN'      => 'Transfer Masuk',
  'TRANSFER_OUT'     => 'Transfer Keluar',
  'USAGE'            => 'Pemakaian',
  'WASTE'            => 'Waste',
  'SPOIL'            => 'Spoilage',
  'ADJUSTMENT_PLUS'  => 'Adjustment Plus',
  'ADJUSTMENT_MINUS' => 'Adjustment Minus',
  'VOID_REVERSE'     => 'Pembatalan Void',
];
$movementTypeLabel = static function (string $type) use ($movementOptions): string {
  return $movementOptions[$type] ?? $type;
};
$formatQty = static function ($value): string {
  return number_format((float)$value, 2, ',', '.');
};
$buildSourceLabel = static function (array $row): array {
  $sourceTable  = strtolower(trim((string)($row['source_table'] ?? '')));
  $sourceModule = strtoupper(trim((string)($row['source_module'] ?? '')));
  $sourceId     = (int)($row['source_id'] ?? 0);
  $sourceLineId = (int)($row['source_line_id'] ?? 0);
  $labelMap = [
    'pos_stock_commit'        => 'POS Commit',
    'inv_component_batch'     => 'Batch Produksi',
    'inv_component_adjustment'=> 'Adjustment Component',
    'inv_component_opening'   => 'Opening Component',
  ];
  $label = $labelMap[$sourceTable] ?? ($sourceTable !== '' ? strtoupper(str_replace('_', ' ', $sourceTable)) : ($sourceModule !== '' ? $sourceModule : '-'));
  if ($sourceId > 0) $label .= ' #' . $sourceId;
  $meta = [];
  if ($sourceModule !== '') $meta[] = 'Module ' . $sourceModule;
  if ($sourceLineId > 0)    $meta[] = 'Line #' . $sourceLineId;
  if (!empty($row['lot_no_snapshot'])) $meta[] = 'Lot ' . trim((string)$row['lot_no_snapshot']);
  if (!empty($row['received_date_snapshot'])) $meta[] = 'Terima ' . trim((string)$row['received_date_snapshot']);
  return ['label' => $label, 'meta' => implode(' | ', $meta)];
};
$perPage      = in_array((int)($filters['per_page'] ?? 25), [10, 25, 50, 100], true) ? (int)$filters['per_page'] : 25;
$dateFrom     = (string)($filters['date_from'] ?? date('Y-m-01'));
$dateTo       = (string)($filters['date_to']   ?? date('Y-m-d'));
$selectedLoc  = (string)($filters['location_type'] ?? '');
$selectedMov  = (string)($filters['movement_type'] ?? '');
$selectedDiv  = (int)($filters['division_id'] ?? 0);
$searchVal    = (string)($filters['q'] ?? '');

// — Summary computations —
$totalRows = count($rows);
$countIn = 0; $countOut = 0;
$valueIn = 0.0; $valueOut = 0.0;
$qtyInTotal = 0.0; $qtyOutTotal = 0.0;
$byType = []; $byDivision = [];
$uniqueComponents = [];
foreach ($rows as $r) {
  $delta    = (float)($r['qty_delta'] ?? ((float)($r['qty_in'] ?? 0) - (float)($r['qty_out'] ?? 0)));
  $cost     = (float)($r['total_cost'] ?? 0);
  $type     = (string)($r['movement_type'] ?? '');
  $typeLabel= $movementTypeLabel($type);
  $divName  = (string)($r['division_name'] ?? '-');
  $compId   = (string)($r['component_id'] ?? '');
  if ($compId !== '') $uniqueComponents[$compId] = true;
  if ($delta >= 0) {
    $countIn++; $valueIn  += $cost;  $qtyInTotal  += $delta;
  } else {
    $countOut++; $valueOut += abs($cost); $qtyOutTotal += abs($delta);
  }
  $byType[$typeLabel] = ($byType[$typeLabel] ?? 0) + 1;
  $byDivision[$divName] = ($byDivision[$divName] ?? 0.0) + abs($cost);
}
$netValue = $valueIn - $valueOut;
arsort($byType);
arsort($byDivision);
$topTypes     = array_slice($byType, 0, 5, true);
$topDivisions = array_slice($byDivision, 0, 4, true);
$uniqueComponentCount = count($uniqueComponents);
?>

<style>
.component-movement-wrap {
  max-height:74vh; overflow:auto;
  border:1px solid #e8d2c3; border-radius:18px;
  background:linear-gradient(180deg,#fffaf5 0%,#fff 100%);
  box-shadow:0 18px 36px -30px rgba(95,53,39,.45);
}
.component-movement-table { min-width:1240px;margin-bottom:0;border-collapse:separate;border-spacing:0; }
.component-movement-table thead th {
  position:sticky;top:0;z-index:4;
  background:linear-gradient(180deg,#7c1f2d 0%,#9f2f3e 100%);
  color:#fff8f5;border-bottom:1px solid #7f2936;white-space:nowrap;
}
.component-movement-table td,
.component-movement-table th { vertical-align:top;border-right:1px solid #efddd2;border-bottom:1px solid #f3e4da;font-size:.83rem; }
.component-movement-table tbody td { background:#fff; }
.component-movement-table tbody tr:nth-child(even) td { background:#fffaf6; }
.component-movement-metric { line-height:1.2; }
.component-movement-metric strong { display:block;font-size:.88rem;color:#2f2628; }
.component-movement-metric small { color:#8a776f; }
.component-movement-delta-plus strong { color:#166534; }
.component-movement-delta-minus strong { color:#b42318; }
.movement-source-no { font-weight:800;color:#2f2628;font-size:.82rem;display:block; }
.movement-source-type { font-weight:600;color:#5a3a30;font-size:.77rem;display:block;margin-top:.15rem; }
.movement-source-meta { display:block;color:#8a776f;font-size:.7rem;line-height:1.35;margin-top:.15rem; }
.movement-source-note { display:block;color:#9a7a60;font-size:.7rem;font-style:italic;margin-top:.1rem; }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-exchange-funds-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Mutasi Base/Prepare'); ?></h4>
  <small class="text-muted">Read-only ledger keluar masuk base/prepare — <?php echo html_escape($dateFrom); ?> s/d <?php echo html_escape($dateTo); ?>, <?php echo $totalRows; ?> baris.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'movement']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-movements'),
  'component_type_filters'  => $filters,
  'component_type_active'   => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'date_from'     => $dateFrom,
    'division_id'   => $selectedDiv ?: '',
    'location_type' => $selectedLoc,
  ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<!-- Filter card -->
<div class="card mb-3 border-0 shadow-sm">
  <div class="card-body py-2">
    <form method="get" action="<?php echo site_url('production/component-movements'); ?>" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Dari</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo html_escape($dateFrom); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Sampai</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo html_escape($dateTo); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Lokasi</label>
        <select name="location_type" class="form-select form-select-sm" style="min-width:100px">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape($key); ?>" <?php echo $selectedLoc === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Jenis</label>
        <select name="movement_type" class="form-select form-select-sm" style="min-width:130px">
          <?php foreach ($movementOptions as $key => $label): ?>
            <option value="<?php echo html_escape($key); ?>" <?php echo $selectedMov === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Divisi</label>
        <select name="division_id" class="form-select form-select-sm" style="min-width:100px">
          <option value="">Semua</option>
          <?php foreach ($divisions as $div): ?>
            <option value="<?php echo (int)$div['id']; ?>" <?php echo $selectedDiv === (int)$div['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)($div['name'] ?? '')); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Per Hal</label>
        <select name="per_page" class="form-select form-select-sm" style="min-width:70px">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Cari</label>
        <input type="text" name="q" id="movement-search" class="form-control form-control-sm" value="<?php echo html_escape($searchVal); ?>" placeholder="No / component / catatan" style="min-width:170px">
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
        <a href="<?php echo site_url('production/component-movements'); ?>" class="btn btn-outline-danger btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php
$topMovementType = '-';
$topMovementCount = 0;
if (!empty($topTypes)) {
  $topMovementType = (string)array_key_first($topTypes);
  $topMovementCount = (int)($topTypes[$topMovementType] ?? 0);
}
$this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan mutasi komponen',
  'stock_summary_cards' => [
    ['label' => 'Total Mutasi', 'value' => number_format($totalRows, 0, ',', '.'), 'detail' => $countIn . ' masuk · ' . $countOut . ' keluar', 'tone' => 'violet', 'icon' => 'ri-arrow-left-right-line'],
    ['label' => 'Nilai Masuk', 'value' => 'Rp ' . number_format($valueIn, 0, ',', '.'), 'detail' => number_format($qtyInTotal, 2, ',', '.') . ' unit · ' . $countIn . ' transaksi', 'tone' => 'aqua', 'icon' => 'ri-arrow-down-circle-line'],
    ['label' => 'Nilai Keluar', 'value' => 'Rp ' . number_format($valueOut, 0, ',', '.'), 'detail' => number_format($qtyOutTotal, 2, ',', '.') . ' unit · ' . $countOut . ' transaksi', 'tone' => 'blue', 'icon' => 'ri-arrow-up-circle-line'],
    ['label' => 'Net Nilai', 'value' => ($netValue >= 0 ? '+' : '') . 'Rp ' . number_format($netValue, 0, ',', '.'), 'detail' => $netValue >= 0 ? 'Net positif (masuk lebih besar)' : 'Net negatif (keluar lebih besar)', 'tone' => $netValue < 0 ? 'danger' : 'amber', 'icon' => 'ri-scales-3-line'],
    ['label' => 'Component Unik', 'value' => number_format($uniqueComponentCount, 0, ',', '.'), 'detail' => 'dalam periode filter', 'tone' => 'teal', 'icon' => 'ri-stack-line'],
    ['label' => 'Jenis Terbanyak', 'value' => $topMovementType, 'detail' => $topMovementCount > 0 ? $topMovementCount . '× transaksi' : 'belum ada data', 'tone' => 'aqua', 'icon' => 'ri-bar-chart-grouped-line'],
  ],
]); ?>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="component-movement-wrap">
    <table class="table table-striped table-hover component-movement-table" style="table-layout:fixed">
      <thead>
        <tr>
          <th style="width:108px">Tanggal</th>
          <th style="width:82px">Lokasi</th>
          <th style="width:175px">Komponen</th>
          <th style="width:148px">Jenis</th>
          <th style="width:92px" class="text-end">Before</th>
          <th style="width:92px" class="text-end">Delta</th>
          <th style="width:92px" class="text-end">After</th>
          <th style="width:88px" class="text-end">Unit Cost</th>
          <th style="width:98px" class="text-end">Total Cost</th>
          <th style="width:165px">Sumber</th>
        </tr>
      </thead>
      <tbody id="movement-tbody">
        <?php if (empty($rows)): ?>
          <tr class="mov-row"><td colspan="10" class="text-center text-muted py-4">Belum ada data mutasi komponen.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $source   = $buildSourceLabel((array)$row);
              $deltaQty = (float)($row['qty_delta'] ?? 0);
              $searchStr = strtolower(implode(' ', [
                $row['movement_no'] ?? '',
                $row['movement_date'] ?? '',
                $locationGroupLabel((string)($row['location_type'] ?? '')),
                $row['component_name'] ?? '',
                $row['component_code'] ?? '',
                $row['division_name'] ?? '',
                $row['movement_type'] ?? '',
                $row['movement_type_label'] ?? '',
                $source['label'] ?? '',
                $row['notes'] ?? '',
              ]));
            ?>
            <tr class="mov-row" data-search="<?php echo html_escape($searchStr); ?>">
              <td style="white-space:nowrap;overflow:hidden">
                <div style="font-size:.8rem"><?php echo html_escape((string)($row['movement_date'] ?? '-')); ?></div>
                <small class="text-muted"><?php echo html_escape(substr((string)($row['movement_datetime'] ?? ''), 11, 8) ?: ''); ?></small>
              </td>
              <td style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? '-'))); ?></td>
              <td style="overflow:hidden">
                <div style="font-size:.8rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['component_name'] ?? '-')); ?>"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                <small class="text-muted" style="font-size:.69rem;white-space:nowrap;overflow:hidden;display:block"><?php echo html_escape((string)($row['component_code'] ?? '')); ?><?php if (!empty($row['division_name'])): ?> · <?php echo html_escape((string)$row['division_name']); ?><?php endif; ?></small>
              </td>
              <td style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($row['movement_type_label'] ?? $row['movement_type'] ?? '-')); ?></td>
              <td class="text-end" style="overflow:hidden">
                <div class="component-movement-metric">
                  <strong><?php echo $formatQty($row['qty_before'] ?? 0); ?></strong>
                  <small><?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end <?php echo $deltaQty >= 0 ? 'component-movement-delta-plus' : 'component-movement-delta-minus'; ?>" style="overflow:hidden">
                <div class="component-movement-metric">
                  <strong><?php echo ($deltaQty >= 0 ? '+' : '') . $formatQty($deltaQty); ?></strong>
                  <small><?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end" style="overflow:hidden">
                <div class="component-movement-metric">
                  <strong><?php echo $formatQty($row['qty_after'] ?? 0); ?></strong>
                  <small><?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end" style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end" style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($row['total_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td style="overflow:hidden">
                <span class="movement-source-no"><?php echo html_escape((string)($row['movement_no'] ?? '-')); ?></span>
                <span class="movement-source-type"><?php echo html_escape((string)($source['label'] ?? '-')); ?></span>
                <?php if (!empty($source['meta'])): ?><span class="movement-source-meta"><?php echo html_escape((string)$source['meta']); ?></span><?php endif; ?>
                <?php if (!empty($row['notes'])): ?><span class="movement-source-note"><?php echo html_escape((string)$row['notes']); ?></span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-between align-items-center px-3 py-2 flex-wrap gap-2">
    <div id="movement-info" class="text-muted" style="font-size:.78rem"></div>
    <div id="movement-pager" class="d-flex gap-1 flex-wrap"></div>
  </div>
</div>

<script>
(() => {
  const perPage = <?php echo (int)$perPage; ?>;

  function buildPager(pagerId, infoId, total, page, onClick) {
    const pager = document.getElementById(pagerId);
    const info  = document.getElementById(infoId);
    if (!pager) return;
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    if (info) {
      const s = total > 0 ? ((page - 1) * perPage + 1) : 0;
      const e = Math.min(page * perPage, total);
      info.textContent = total > 0 ? ('Menampilkan ' + s.toLocaleString('id-ID') + '–' + e.toLocaleString('id-ID') + ' dari ' + total.toLocaleString('id-ID') + ' baris') : 'Tidak ada data';
    }
    if (totalPages <= 1) { pager.innerHTML = ''; return; }
    const pages = new Set([1]);
    for (let p = Math.max(2, page - 1); p <= Math.min(totalPages - 1, page + 1); p++) pages.add(p);
    pages.add(totalPages);
    let html = '';
    let last = 0;
    for (const p of pages) {
      if (last > 0 && p - last > 1) html += '<span class="btn btn-sm btn-light disabled px-2">…</span>';
      html += '<button class="btn btn-sm ' + (p === page ? 'btn-dark' : 'btn-outline-secondary') + ' px-2" data-p="' + p + '">' + p + '</button>';
      last = p;
    }
    pager.innerHTML = html;
    pager.querySelectorAll('button[data-p]').forEach(btn => btn.addEventListener('click', () => onClick(+btn.dataset.p)));
  }

  function applyFilter(searchVal, page) {
    const allRows = Array.from(document.querySelectorAll('#movement-tbody tr.mov-row'));
    const needle  = searchVal.trim().toLowerCase();
    const visible = allRows.filter(r => !needle || (r.dataset.search || '').includes(needle));
    allRows.forEach(r => { r.style.display = 'none'; });
    const start = (page - 1) * perPage;
    visible.forEach((r, i) => { r.style.display = (i >= start && i < start + perPage) ? '' : 'none'; });
    buildPager('movement-pager', 'movement-info', visible.length, page, p => applyFilter(searchVal, p));
  }

  const searchInput = document.getElementById('movement-search');
  searchInput?.addEventListener('input', () => applyFilter(searchInput.value, 1));
  applyFilter(searchInput ? searchInput.value : '', 1);
})();
</script>
