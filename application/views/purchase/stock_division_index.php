<?php
$baseUrl = site_url('inventory/stock/division');
$lotAuditBaseUrl = site_url('inventory/stock/division/lot');
$genMonth = preg_match('/^\d{4}-\d{2}$/', (string)($month ?? '')) ? (string)$month : date('Y-m');
$rowsData = is_array($rows ?? null) ? $rows : [];
$destinationValue = strtoupper(trim((string)($destination ?? 'ALL')));
if ($destinationValue === '') {
  $destinationValue = 'ALL';
}
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$includeZeroValue = !empty($include_zero);
$formatDivisionLabel = static function (string $code, string $name, $fallbackId = '-'): string {
  $code = trim($code);
  $name = trim($name);
  if ($code !== '' && strcasecmp($code, $name) === 0) {
    return $code;
  }
  if ($code !== '' && $name !== '') {
    return $code . ' - ' . $name;
  }
  if ($code !== '') {
    return $code;
  }
  if ($name !== '') {
    return $name;
  }
  return (string)$fallbackId;
};
$formatDestination = static function (string $group): string {
  return strtoupper(trim($group)) === 'EVENT' ? 'Event' : 'Reguler';
};
$isChildNonPositive = static function (array $row): bool {
  return round((float)($row['qty_content_balance'] ?? 0), 4) <= 0.0001;
};

$parentMap = [];
foreach ($rowsData as $row) {
  $divisionId2 = (int)($row['division_id'] ?? 0);
  $divisionCode = trim((string)($row['division_code'] ?? ''));
  $divisionName = trim((string)($row['division_name'] ?? ''));
  $destinationGroup = strtoupper(trim((string)($row['destination_group'] ?? 'REGULER')));

  $materialId = (int)($row['material_id'] ?? 0);
  $itemId = (int)($row['item_id'] ?? 0);
  $objectIdentity = $materialId > 0 ? ('M-' . $materialId) : ('I-' . $itemId);
  if ($objectIdentity === 'M-0' || $objectIdentity === 'I-0') {
    $objectIdentity .= '|' . strtoupper(trim((string)($row['material_code'] ?? '') . '|' . (string)($row['item_code'] ?? '')));
  }

  $parentKey = implode('|', [$divisionId2, $destinationGroup, $objectIdentity]);
  if (!isset($parentMap[$parentKey])) {
    $parentMap[$parentKey] = [
      'division_id' => $divisionId2,
      'division_code' => $divisionCode,
      'division_name' => $divisionName,
      'destination_group' => $destinationGroup,
      'material_id' => $materialId,
      'item_code' => trim((string)($row['item_code'] ?? '')),
      'item_name' => trim((string)($row['item_name'] ?? '')),
      'material_code' => trim((string)($row['material_code'] ?? '')),
      'material_name' => trim((string)($row['material_name'] ?? '')),
      'qty_buy_balance' => 0.0,
      'qty_content_balance' => 0.0,
      'total_value' => 0.0,
      'updated_at' => '',
      'children' => [],
    ];
  }

  $profileNameKey = strtoupper(trim((string)($row['profile_name'] ?? '')));
  $profileBrandKey = strtoupper(trim((string)($row['profile_brand'] ?? '')));
  $profileDescKey = strtoupper(trim((string)($row['profile_description'] ?? '')));
  $contentPerBuyKey = number_format((float)($row['profile_content_per_buy'] ?? 0), 6, '.', '');
  $childKey = implode('|', [
    (string)($row['profile_key'] ?? ''),
    $profileNameKey,
    $profileBrandKey,
    $profileDescKey,
    $contentPerBuyKey,
    strtoupper(trim((string)($row['profile_buy_uom_code'] ?? ''))),
    strtoupper(trim((string)($row['profile_content_uom_code'] ?? ''))),
  ]);

  if (!isset($parentMap[$parentKey]['children'][$childKey])) {
    $parentMap[$parentKey]['children'][$childKey] = [
      'profile_key' => (string)($row['profile_key'] ?? ''),
      'profile_name' => (string)($row['profile_name'] ?? '-'),
      'profile_brand' => (string)($row['profile_brand'] ?? '-'),
      'profile_description' => (string)($row['profile_description'] ?? '-'),
      'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
      'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 0),
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'qty_buy_balance' => 0.0,
      'qty_content_balance' => 0.0,
      'total_value' => 0.0,
      'updated_at' => '',
    ];
  }

  $qtyBuy = (float)($row['qty_buy_balance'] ?? 0);
  $qtyContent = (float)($row['qty_content_balance'] ?? 0);
  $avgCost = (float)($row['avg_cost_per_content'] ?? 0);
  $storedVal = isset($row['total_value']) ? (float)$row['total_value'] : 0.0;
  $rowValue = $storedVal > 0 ? $storedVal : ($qtyContent * $avgCost);
  $updatedAt = (string)($row['updated_at'] ?? '');

  $parentMap[$parentKey]['qty_buy_balance'] += $qtyBuy;
  $parentMap[$parentKey]['qty_content_balance'] += $qtyContent;
  $parentMap[$parentKey]['total_value'] += $rowValue;
  if ($updatedAt !== '' && $updatedAt > $parentMap[$parentKey]['updated_at']) {
    $parentMap[$parentKey]['updated_at'] = $updatedAt;
  }

  $parentMap[$parentKey]['children'][$childKey]['qty_buy_balance'] += $qtyBuy;
  $parentMap[$parentKey]['children'][$childKey]['qty_content_balance'] += $qtyContent;
  $parentMap[$parentKey]['children'][$childKey]['total_value'] += $rowValue;
  if ($updatedAt !== '' && $updatedAt > $parentMap[$parentKey]['children'][$childKey]['updated_at']) {
    $parentMap[$parentKey]['children'][$childKey]['updated_at'] = $updatedAt;
  }
}

$parentRows = array_values($parentMap);
foreach ($parentRows as &$parentRow) {
  $children = array_values($parentRow['children']);
  usort($children, static function (array $a, array $b) use ($isChildNonPositive): int {
    $aNonPositive = $isChildNonPositive($a);
    $bNonPositive = $isChildNonPositive($b);
    if ($aNonPositive !== $bNonPositive) {
      return $aNonPositive ? 1 : -1;
    }
    $cmp = strcasecmp((string)($a['profile_name'] ?? ''), (string)($b['profile_name'] ?? ''));
    if ($cmp !== 0) {
      return $cmp;
    }
    $cmp = strcasecmp((string)($a['profile_brand'] ?? ''), (string)($b['profile_brand'] ?? ''));
    if ($cmp !== 0) {
      return $cmp;
    }
    return strcasecmp((string)($a['profile_description'] ?? ''), (string)($b['profile_description'] ?? ''));
  });

  $parentRow['children'] = $children;
  $parentRow['profile_count'] = count($children);
  $parentRow['avg_cost_per_content'] = ((float)$parentRow['qty_content_balance'] !== 0.0)
    ? ((float)$parentRow['total_value'] / (float)$parentRow['qty_content_balance'])
    : 0.0;
}
unset($parentRow);

usort($parentRows, static function (array $a, array $b): int {
  $cmp = strcasecmp((string)($a['division_name'] ?? ''), (string)($b['division_name'] ?? ''));
  if ($cmp !== 0) {
    return $cmp;
  }
  $cmp = strcasecmp((string)($a['destination_group'] ?? ''), (string)($b['destination_group'] ?? ''));
  if ($cmp !== 0) {
    return $cmp;
  }

  $aName = trim((string)($a['item_name'] ?? ''));
  if ($aName === '') {
    $aName = trim((string)($a['material_name'] ?? ''));
  }
  $bName = trim((string)($b['item_name'] ?? ''));
  if ($bName === '') {
    $bName = trim((string)($b['material_name'] ?? ''));
  }
  return strcasecmp($aName, $bName);
});

// ── Summary (computed from ALL rows before pagination) ──
$summaryProfiles = 0;
$summaryQtyContent = 0.0;
$summaryTotalValue = 0.0;
$summaryDivisions = [];
$summaryAlertCount = 0;
$uniqueMaterialCodes = [];
foreach ($parentRows as $parentRow) {
  $summaryProfiles += (int)($parentRow['profile_count'] ?? 0);
  $summaryQtyContent += (float)($parentRow['qty_content_balance'] ?? 0);
  $summaryTotalValue += (float)($parentRow['total_value'] ?? 0);
  $divId = (int)($parentRow['division_id'] ?? 0);
  if ($divId > 0) {
    $summaryDivisions[$divId] = true;
  }
  if ((float)($parentRow['qty_content_balance'] ?? 0) <= 0.0001) {
    $summaryAlertCount++;
  }
  $mc = trim((string)($parentRow['material_code'] ?? ''));
  if ($mc !== '') {
    $uniqueMaterialCodes[$mc] = true;
  }
}
$summaryDivisionCount = count($summaryDivisions);
$summaryUniqueMaterialCount = count($uniqueMaterialCodes);

// ── Pagination ──
$perPage = max(10, (int)($limit ?? 100));
$currentPage = max(1, (int)($page ?? 1));
$totalParentCount = count($parentRows);
$totalPages = $totalParentCount > 0 ? (int)ceil($totalParentCount / $perPage) : 1;
$currentPage = min($currentPage, max(1, $totalPages));
$parentRows = array_slice($parentRows, ($currentPage - 1) * $perPage, $perPage);

$pParams = ['limit' => $perPage];
if (!empty($q)) $pParams['q'] = $q;
if ((int)($division_id ?? 0) > 0) $pParams['division_id'] = (int)$division_id;
if ($destinationValue !== 'ALL') $pParams['destination'] = $destinationValue;
$pParams['month'] = $genMonth;
if ($includeZeroValue) $pParams['include_zero'] = 1;
$paginationQs = http_build_query($pParams);
?>

<style>
/* ── Filter form ── */
.dv-filter-grid {
  display: grid;
  grid-template-columns: minmax(140px,1.2fr) 100px minmax(160px,2fr) 104px 118px 118px 68px auto;
  gap: 0.5rem;
  align-items: end;
}
@media (max-width: 1199px) {
  .dv-filter-grid {
    grid-template-columns: minmax(130px,1fr) 95px minmax(140px,2fr) 100px 110px 110px 64px auto;
  }
}
@media (max-width: 991px) {
  .dv-filter-grid {
    grid-template-columns: 1fr 1fr 1fr 1fr;
  }
  .dv-filter-btn-wrap { grid-column: span 2; display: flex; gap: 0.5rem; }
}
@media (max-width: 767px) {
  .dv-filter-grid { grid-template-columns: 1fr 1fr; }
  .dv-filter-btn-wrap { grid-column: span 2; }
}

/* ── Table scroll / sticky ── */
.dv-table-wrap {
  overflow-x: auto;
  overflow-y: auto;
  max-height: 72vh;
}
.dv-stock-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #fff8f4;
  box-shadow: inset 0 -1px 0 #e8d1c5;
  white-space: nowrap;
}
.dv-stock-table th:nth-child(1),
.dv-stock-table td:nth-child(1) {
  width: 42px; text-align: center;
}
.dv-stock-table th:nth-child(3),
.dv-stock-table td:nth-child(3) {
  min-width: 200px;
}
.dv-parent-row {
  background: #fff6ef;
  border-top: 2px solid #f0d8ca;
}
.dv-child-row td {
  background: #fff;
}
.dv-stock-row-alert td {
  background: linear-gradient(180deg, #fff1ef 0%, #fff8f7 100%) !important;
  color: #8a2f2a;
}
.dv-stock-row-alert .dv-item-name,
.dv-stock-row-alert .fw-semibold {
  color: #8a2f2a !important;
}
.dv-alert-chip {
  display: inline-flex;
  align-items: center;
  padding: .16rem .48rem;
  border-radius: 999px;
  background: #c0392b;
  color: #fff;
  font-size: .64rem;
  font-weight: 800;
}
.dv-name-cell {
  display: flex;
  align-items: flex-start;
  gap: .55rem;
  min-width: 0;
}
.dv-name-cell-child {
  padding-left: 1.4rem;
  position: relative;
}
.dv-name-cell-child::before {
  content: '';
  position: absolute;
  left: .55rem; top: .2rem; bottom: .2rem;
  width: 3px;
  border-radius: 999px;
  background: linear-gradient(180deg, #ebd7cc 0%, #d9b6a4 100%);
}
.dv-name-body  { min-width: 0; display: grid; gap: .22rem; }
.dv-name-meta  { display: flex; flex-wrap: wrap; gap: .32rem; }
.dv-name-chip  {
  display: inline-flex; align-items: center;
  padding: .15rem .48rem; font-size: .68rem; font-weight: 800; max-width: 100%;
  color: #855346; border: 1px solid #ead5ca; background: #fff8f3;
}
.dv-name-chip.is-parent { border-radius: 999px; }
.dv-name-chip.is-child  { border-radius: 10px; border-style: dashed; background: #fffaf7; }
.dv-toggle-arrow {
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border-radius: 8px;
  border: 2px solid #c8a090; background: #fff8f4; color: #7a2e1c;
  font-size: .85rem; cursor: pointer;
  transition: transform .2s ease, background .15s, border-color .15s;
  box-shadow: 0 1px 4px rgba(120,60,30,.12);
}
.dv-toggle-arrow:hover { background: #fde8de; border-color: #b07060; color: #5c1a0a; }
.dv-toggle-arrow[aria-expanded="true"] { transform: rotate(90deg); background: #fde8de; border-color: #b07060; }
.dv-object-name { font-weight: 700; color: #4e1f2e; line-height: 1.25; }
.dv-object-code { color: #876a65; font-size: .8rem; }
.dv-child-label {
  font-size: .68rem; font-weight: 800; letter-spacing: .04em;
  text-transform: uppercase; color: #9a6f60;
}

/* ── Pagination ── */
.dv-pagination { display: flex; align-items: center; flex-wrap: wrap; gap: .35rem; margin-top: .75rem; }
.dv-page-btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 32px; height: 32px; padding: 0 .5rem;
  border: 1px solid #ddd; border-radius: 8px;
  background: #fff; color: #555; font-size: .8rem; font-weight: 600;
  text-decoration: none; cursor: pointer; transition: all .15s;
}
.dv-page-btn:hover { background: #f5f5f5; border-color: #bbb; color: #333; }
.dv-page-btn.is-active { background: #6a2d3c; border-color: #6a2d3c; color: #fff; }
.dv-page-btn.is-disabled { opacity: .4; pointer-events: none; }
.dv-page-info { font-size: .8rem; color: #777; }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-store-2-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
  <small class="text-muted">Posisi stok bahan baku per divisi pada akhir bulan snapshot yang dipilih.</small>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'stock']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $genMonth, 'division_id' => (string)(int)($division_id ?? 0), 'destination_type' => $destinationValue],
]); ?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>">
      <div class="dv-filter-grid">
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id">
            <option value="">Semua Divisi</option>
            <?php foreach (($divisions ?? []) as $d): ?>
              <?php
                $dId = (int)($d['id'] ?? 0);
                $dCode = trim((string)($d['code'] ?? ''));
                $dName = trim((string)($d['name'] ?? ''));
                $dLabel = $formatDivisionLabel($dCode, $dName, $dId);
              ?>
              <option value="<?php echo $dId; ?>" <?php echo ((int)($division_id ?? 0) === $dId) ? 'selected' : ''; ?>><?php echo html_escape($dLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination" id="sdDestination">
            <option value="ALL" <?php echo $destinationValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
            <option value="REGULER" <?php echo $destinationValue === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
            <option value="EVENT" <?php echo $destinationValue === 'EVENT' ? 'selected' : ''; ?>>Event</option>
            <option value="BAR" <?php echo $destinationValue === 'BAR' ? 'selected' : ''; ?>>Bar Reg</option>
            <option value="KITCHEN" <?php echo $destinationValue === 'KITCHEN' ? 'selected' : ''; ?>>Kitchen Reg</option>
            <option value="ROASTERY" <?php echo $destinationValue === 'ROASTERY' ? 'selected' : ''; ?>>Roastery Reg</option>
            <option value="BAR_EVENT" <?php echo $destinationValue === 'BAR_EVENT' ? 'selected' : ''; ?>>Bar Event</option>
            <option value="KITCHEN_EVENT" <?php echo $destinationValue === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>Kitchen Evt</option>
            <option value="ROASTERY_EVENT" <?php echo $destinationValue === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>Roastery Evt</option>
            <option value="OFFICE" <?php echo $destinationValue === 'OFFICE' ? 'selected' : ''; ?>>Office</option>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Cari Stok Divisi</label>
          <input type="text" class="form-control form-control-sm" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Divisi / Item / Material / Profile / Merk">
        </div>
        <div>
          <label class="form-label mb-1">Saldo</label>
          <select class="form-select form-select-sm" name="include_zero">
            <option value="0" <?php echo !$includeZeroValue ? 'selected' : ''; ?>>Aktif saja</option>
            <option value="1" <?php echo $includeZeroValue ? 'selected' : ''; ?>>Termasuk nol</option>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Bulan Snapshot</label>
          <input type="month" class="form-control form-control-sm" name="month" value="<?php echo html_escape($genMonth); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Per Halaman</label>
          <select class="form-select form-select-sm" name="limit">
            <?php foreach ([25, 50, 100, 200] as $pageSize): ?>
              <option value="<?php echo $pageSize; ?>" <?php echo $perPage === $pageSize ? 'selected' : ''; ?>><?php echo $pageSize; ?> baris</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="dv-filter-btn-wrap" style="display:flex;gap:.4rem;">
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">Terapkan</button>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-danger w-100">Clear</a>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var guardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var destinationEl = document.getElementById('sdDestination');
  var divisionEl = document.querySelector('select[name="division_id"]');
  if (!destinationEl || !divisionEl) { return; }
  var allOptions = [
    { value: 'ALL', label: 'Semua' },
    { value: 'REGULER', label: 'Reguler' },
    { value: 'EVENT', label: 'Event' },
    { value: 'BAR', label: 'Bar Reg' },
    { value: 'KITCHEN', label: 'Kitchen Reg' },
    { value: 'ROASTERY', label: 'Roastery Reg' },
    { value: 'BAR_EVENT', label: 'Bar Event' },
    { value: 'KITCHEN_EVENT', label: 'Kitchen Evt' },
    { value: 'ROASTERY_EVENT', label: 'Roastery Evt' },
    { value: 'OFFICE', label: 'Office' }
  ];
  function esc(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function syncDestinationOptions(){
    var divisionId = parseInt(divisionEl.value||'0',10);
    var current = String(destinationEl.value||'ALL').toUpperCase();
    var options = allOptions.slice();
    if (Number.isFinite(divisionId) && divisionId > 0 && guardMap[String(divisionId)]) {
      var allowed = (guardMap[String(divisionId)]||[]).map(function(x){ return String(x||'').toUpperCase(); });
      options = allOptions.filter(function(opt){
        if (opt.value==='ALL'||opt.value==='REGULER'||opt.value==='EVENT') { return true; }
        return allowed.indexOf(opt.value) !== -1;
      });
    }
    destinationEl.innerHTML = options.map(function(opt){
      return '<option value="'+esc(opt.value)+'">'+esc(opt.label)+'</option>';
    }).join('');
    var exists = options.some(function(opt){ return opt.value===current; });
    destinationEl.value = exists ? current : 'ALL';
  }
  divisionEl.addEventListener('change', syncDestinationOptions);
  syncDestinationOptions();
})();
</script>

<?php $this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan stok bahan baku',
  'stock_summary_cards' => [
    ['label' => 'Item Stok', 'value' => number_format($totalParentCount, 0, ',', '.'), 'tone' => 'violet', 'icon' => 'ri-box-3-line'],
    ['label' => 'Divisi Aktif', 'value' => number_format($summaryDivisionCount, 0, ',', '.'), 'tone' => 'aqua', 'icon' => 'ri-building-2-line'],
    ['label' => 'Material Unik', 'value' => number_format($summaryUniqueMaterialCount, 0, ',', '.'), 'tone' => 'blue', 'icon' => 'ri-flask-line'],
    ['label' => 'Total Qty Isi', 'value' => number_format($summaryQtyContent, 1, ',', '.'), 'tone' => 'amber', 'icon' => 'ri-scales-3-line'],
    ['label' => 'Total Nilai HPP', 'value' => 'Rp ' . number_format($summaryTotalValue, 0, ',', '.'), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
    ['label' => 'Stok Habis / Minus', 'value' => number_format($summaryAlertCount, 0, ',', '.'), 'tone' => $summaryAlertCount > 0 ? 'danger' : 'blue', 'icon' => 'ri-alarm-warning-line'],
  ],
]); ?>

<!-- Table -->
<div class="card">
  <div class="dv-table-wrap" role="region" aria-label="Daftar stok bahan baku">
    <table class="table table-striped table-hover mb-0 dv-stock-table" id="dvStockTable">
      <thead>
        <tr>
          <th></th>
          <th>Divisi / Tujuan</th>
          <th>Nama Barang</th>
          <th>Merk</th>
          <th>Keterangan</th>
          <th>Ukuran Isi</th>
          <th class="text-end">QTY (Isi / Beli)</th>
          <th class="text-end">Avg Cost/Isi</th>
          <th class="text-end">Total Nilai</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parentRows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4" role="status">Belum ada data stok bahan baku pada filter ini.</td></tr>
        <?php else: ?>
          <?php foreach ($parentRows as $idx => $parent): ?>
            <?php
              $divisionText = $formatDivisionLabel(
                (string)($parent['division_code'] ?? ''),
                (string)($parent['division_name'] ?? ''),
                (string)($parent['division_id'] ?? '-')
              );
              $destinationText = $formatDestination((string)($parent['destination_group'] ?? 'REGULER'));

              $itemName = trim((string)($parent['item_name'] ?? ''));
              $materialName = trim((string)($parent['material_name'] ?? ''));
              $objectText = $itemName !== '' ? $itemName : ($materialName !== '' ? $materialName : '-');

              $collapseClass = 'dv-parent-' . ($idx + 1) . '-p' . $currentPage;
              $isExpandable = ((int)($parent['profile_count'] ?? 0) > 1);
              $singleChild = (!$isExpandable && !empty($parent['children'])) ? $parent['children'][0] : null;
              $singleChildNonPositive = is_array($singleChild) ? $isChildNonPositive($singleChild) : false;

              $profileLine = '';
              $brandCol = '-';
              $descCol = '-';
              $sizeCol = '-';
              $qtyIsiStr = ui_num((float)($parent['qty_content_balance'] ?? 0));
              $qtyBeliStr = ui_num((float)($parent['qty_buy_balance'] ?? 0));

              if (is_array($singleChild)) {
                $pName = trim((string)($singleChild['profile_name'] ?? '-'));
                $lotUrl = $lotAuditBaseUrl
                  . '?scope=DIVISION&status=ALL&division_id=' . (int)($parent['division_id'] ?? 0)
                  . '&destination=' . rawurlencode((string)($parent['destination_group'] ?? 'REGULER'))
                  . '&profile_key=' . rawurlencode((string)($singleChild['profile_key'] ?? ''));
                $profileLine = html_escape($pName) . ' &nbsp;<a href="' . html_escape($lotUrl) . '" class="small">Lihat Lot</a>';
                $brandCol = html_escape((string)($singleChild['profile_brand'] ?? '-'));
                $descCol = html_escape((string)($singleChild['profile_description'] ?? '-'));
                $cUom = html_escape((string)($singleChild['profile_content_uom_code'] ?? ''));
                $bUom = html_escape((string)($singleChild['profile_buy_uom_code'] ?? ''));
                $sizeCol = number_format((float)($singleChild['profile_content_per_buy'] ?? 0), 2, ',', '.') . ' ' . $cUom . ' / ' . $bUom;
                $qtyIsiStr  = '<span class="fw-semibold">' . ui_num((float)($parent['qty_content_balance'] ?? 0)) . '</span> <span class="text-muted">' . $cUom . '</span>';
                $qtyBeliStr = ui_num((float)($parent['qty_buy_balance'] ?? 0)) . ' <span class="text-muted">' . $bUom . '</span>';
              } else {
                $profileLine = '<span class="text-muted small">' . (int)($parent['profile_count'] ?? 0) . ' profil</span>';
              }
            ?>
            <tr class="dv-parent-row<?php echo $singleChildNonPositive ? ' dv-stock-row-alert' : ''; ?>">
              <td>
                <?php if ($isExpandable): ?>
                  <button type="button" class="dv-toggle-arrow" data-bs-toggle="collapse" data-bs-target=".<?php echo html_escape($collapseClass); ?>" aria-expanded="false">&#9658;</button>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold small"><?php echo html_escape($divisionText); ?></div>
                <div class="text-muted" style="font-size:.72rem"><?php echo html_escape($destinationText); ?></div>
              </td>
              <td>
                <div class="dv-object-name"><?php $matIdLink = (int)($parent['material_id'] ?? 0); echo $matIdLink > 0 ? '<a href="' . html_escape(site_url('master/material/usage/' . $matIdLink)) . '" class="text-decoration-none text-body">' . html_escape($objectText) . '</a>' : html_escape($objectText); ?></div>
                <div class="small mt-1"><?php echo $profileLine; ?></div>
                <?php if ($singleChildNonPositive): ?>
                  <span class="dv-alert-chip mt-1">Stok Habis / Minus</span>
                <?php endif; ?>
              </td>
              <td class="small"><?php echo $brandCol; ?></td>
              <td class="small"><?php echo $descCol; ?></td>
              <td class="small" style="white-space:nowrap"><?php echo $sizeCol; ?></td>
              <td class="text-end">
                <div><?php echo $qtyIsiStr; ?></div>
                <div class="text-muted small"><?php echo $qtyBeliStr; ?></div>
              </td>
              <td class="text-end fw-semibold small"><?php echo ui_num((float)($parent['avg_cost_per_content'] ?? 0)); ?></td>
              <td class="text-end fw-semibold small"><?php echo number_format((float)($parent['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-muted small" style="white-space:nowrap"><?php echo html_escape(substr((string)($parent['updated_at'] ?? ''), 0, 16)); ?></td>
            </tr>

            <?php if ($isExpandable): ?>
            <?php foreach (($parent['children'] ?? []) as $child): ?>
              <?php
                $cProfileName = trim((string)($child['profile_name'] ?? '-'));
                $lotUrl2 = $lotAuditBaseUrl
                  . '?scope=DIVISION&status=ALL&division_id=' . (int)($parent['division_id'] ?? 0)
                  . '&destination=' . rawurlencode((string)($parent['destination_group'] ?? 'REGULER'))
                  . '&profile_key=' . rawurlencode((string)($child['profile_key'] ?? ''));
                $childAvgCost = ((float)($child['qty_content_balance'] ?? 0) !== 0.0)
                  ? ((float)($child['total_value'] ?? 0) / (float)($child['qty_content_balance'] ?? 0))
                  : 0.0;
                $childClass = 'collapse ' . $collapseClass;
                $childNonPositive = $isChildNonPositive($child);
                $cChildUom = html_escape((string)($child['profile_content_uom_code'] ?? ''));
                $bChildUom = html_escape((string)($child['profile_buy_uom_code'] ?? ''));
                $childSizeStr = number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.') . ' ' . $cChildUom . ' / ' . $bChildUom;
              ?>
              <tr class="dv-child-row <?php echo html_escape($childClass); ?><?php echo $childNonPositive ? ' dv-stock-row-alert' : ''; ?>">
                <td></td>
                <td></td>
                <td>
                  <div class="dv-name-cell-child">
                    <div class="small fw-semibold"><?php echo html_escape($cProfileName); ?></div>
                    <div class="small"><a href="<?php echo html_escape($lotUrl2); ?>">Lihat Lot</a></div>
                    <?php if ($childNonPositive): ?>
                      <span class="dv-alert-chip">Stok Habis / Minus</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="small"><?php echo html_escape((string)($child['profile_brand'] ?? '-')); ?></td>
                <td class="small"><?php echo html_escape((string)($child['profile_description'] ?? '-')); ?></td>
                <td class="small" style="white-space:nowrap"><?php echo $childSizeStr; ?></td>
                <td class="text-end">
                  <div><span class="fw-semibold"><?php echo ui_num((float)($child['qty_content_balance'] ?? 0)); ?></span> <span class="text-muted small"><?php echo $cChildUom; ?></span></div>
                  <div class="text-muted small"><?php echo ui_num((float)($child['qty_buy_balance'] ?? 0)); ?> <?php echo $bChildUom; ?></div>
                </td>
                <td class="text-end small"><?php echo ui_num($childAvgCost); ?></td>
                <td class="text-end small"><?php echo number_format((float)($child['total_value'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-muted small" style="white-space:nowrap"><?php echo html_escape(substr((string)($child['updated_at'] ?? ''), 0, 16)); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalParentCount > 0): ?>
  <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span class="text-muted small">
      <?php
        $fromRow = ($currentPage - 1) * $perPage + 1;
        $toRow   = min($currentPage * $perPage, $totalParentCount);
        echo "Item {$fromRow}–{$toRow} dari {$totalParentCount}";
        if ($summaryAlertCount > 0) {
          echo ' &mdash; <span class="text-danger fw-semibold">' . $summaryAlertCount . ' stok habis/minus</span>';
        }
      ?>
    </span>
    <?php if ($totalPages > 1): ?>
    <nav class="dv-pagination" aria-label="Navigasi halaman stok bahan baku">
      <?php
        $prevPage = $currentPage - 1;
        $nextPage = $currentPage + 1;
        $prevUrl = $baseUrl . '?' . $paginationQs . '&page=' . $prevPage;
        $nextUrl = $baseUrl . '?' . $paginationQs . '&page=' . $nextPage;

        $showPrev = $currentPage > 1;
        $showNext = $currentPage < $totalPages;

        // Build page number window (max 5 numbers)
        $winStart = max(1, $currentPage - 2);
        $winEnd   = min($totalPages, $currentPage + 2);
        if ($winEnd - $winStart < 4) {
          if ($winStart === 1) {
            $winEnd = min($totalPages, $winStart + 4);
          } else {
            $winStart = max(1, $winEnd - 4);
          }
        }
      ?>
      <?php if ($showPrev): ?>
        <a href="<?php echo html_escape($prevUrl); ?>" class="dv-page-btn" aria-label="Halaman sebelumnya">&#8249;</a>
      <?php else: ?>
        <span class="dv-page-btn is-disabled" aria-disabled="true">&#8249;</span>
      <?php endif; ?>
      <?php if ($winStart > 1): ?>
        <a href="<?php echo html_escape($baseUrl . '?' . $paginationQs . '&page=1'); ?>" class="dv-page-btn">1</a>
        <?php if ($winStart > 2): ?><span class="dv-page-info px-1">…</span><?php endif; ?>
      <?php endif; ?>
      <?php for ($pn = $winStart; $pn <= $winEnd; $pn++): ?>
        <a href="<?php echo html_escape($baseUrl . '?' . $paginationQs . '&page=' . $pn); ?>" class="dv-page-btn<?php echo $pn === $currentPage ? ' is-active' : ''; ?>"<?php echo $pn === $currentPage ? ' aria-current="page"' : ''; ?>><?php echo $pn; ?></a>
      <?php endfor; ?>
      <?php if ($winEnd < $totalPages): ?>
        <?php if ($winEnd < $totalPages - 1): ?><span class="dv-page-info px-1">…</span><?php endif; ?>
        <a href="<?php echo html_escape($baseUrl . '?' . $paginationQs . '&page=' . $totalPages); ?>" class="dv-page-btn"><?php echo $totalPages; ?></a>
      <?php endif; ?>
      <?php if ($showNext): ?>
        <a href="<?php echo html_escape($nextUrl); ?>" class="dv-page-btn" aria-label="Halaman berikutnya">&#8250;</a>
      <?php else: ?>
        <span class="dv-page-btn is-disabled" aria-disabled="true">&#8250;</span>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
