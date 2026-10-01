<?php
$baseUrl = (string)($base_url ?? site_url('inventory/lot-audit'));
$rows = is_array($rows ?? null) ? $rows : [];
$divisionOptions = is_array($divisions ?? null) ? $divisions : [];
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$scopeValue = strtoupper(trim((string)($scope ?? 'ALL')));
$pageScope = strtoupper(trim((string)($page_scope ?? $scopeValue)));
$scopeLocked = !empty($scope_locked);
$statusValue = strtoupper(trim((string)($status ?? 'OPEN')));
$destinationValue = strtoupper(trim((string)($destination ?? 'ALL')));
if ($destinationValue === '') {
    $destinationValue = 'ALL';
}

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

$buildQueryUrl = static function (string $route, array $params = []): string {
    $query = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }
    $url = site_url($route);
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
};

$resolveSearchToken = static function (array $row): string {
    $profileKeyValue = trim((string)($row['profile_key'] ?? ''));
    if ($profileKeyValue !== '') {
        return $profileKeyValue;
    }

    $objectCodeValue = trim((string)($row['item_code'] ?? ''));
    if ($objectCodeValue === '') {
        $objectCodeValue = trim((string)($row['material_code'] ?? ''));
    }
    if ($objectCodeValue !== '') {
        return $objectCodeValue;
    }

    $objectNameValue = trim((string)($row['item_name'] ?? ''));
    if ($objectNameValue === '') {
        $objectNameValue = trim((string)($row['material_name'] ?? ''));
    }
    return $objectNameValue;
};

$resolveMonthValue = static function (array $row, string $fallback = ''): string {
    $receiptDateValue = trim((string)($row['receipt_date'] ?? ''));
    if ($receiptDateValue !== '' && preg_match('/^\d{4}-\d{2}/', $receiptDateValue) === 1) {
        return substr($receiptDateValue, 0, 7);
    }
    if ($fallback !== '' && preg_match('/^\d{4}-\d{2}/', $fallback) === 1) {
        return substr($fallback, 0, 7);
    }
    return date('Y-m');
};

$resolveLotDisplayMeta = static function (array $row): array {
    $qtyInRaw = round((float)($row['qty_in'] ?? 0), 4);
    $qtyOutRaw = round((float)($row['qty_out'] ?? 0), 4);
    $qtyBalanceRaw = round((float)($row['qty_balance'] ?? 0), 4);
    $factor = round(max(0.000001, (float)($row['identity_content_per_buy'] ?? 1)), 6);
    $identityLotBalance = round((float)($row['identity_lot_qty_balance'] ?? 0), 4);
    $identityClosingBuy = round((float)($row['identity_closing_qty_buy'] ?? 0), 4);
    $identityClosingContent = round((float)($row['identity_closing_qty_content'] ?? 0), 4);
    $buyUomId = (int)($row['buy_uom_id'] ?? 0);
    $contentUomId = (int)($row['content_uom_id'] ?? 0);
    $isDivision = strtoupper(trim((string)($row['location_scope'] ?? ''))) === 'DIVISION';

    $legacyBuyBased = false;
    if ($isDivision && $buyUomId > 0 && $contentUomId > 0 && $buyUomId !== $contentUomId) {
        $matchesBuy = abs($identityLotBalance - $identityClosingBuy) < 0.0001;
        $differsFromContent = abs($identityLotBalance - $identityClosingContent) >= 0.0001;
        $legacyBuyBased = $matchesBuy && $differsFromContent;
    }

    $qtyInDisplay = $legacyBuyBased ? round($qtyInRaw * $factor, 4) : $qtyInRaw;
    $qtyOutDisplay = $legacyBuyBased ? round($qtyOutRaw * $factor, 4) : $qtyOutRaw;
    $qtyBalanceDisplay = $legacyBuyBased ? round($qtyBalanceRaw * $factor, 4) : $qtyBalanceRaw;

    return [
        'legacy_buy_based' => $legacyBuyBased,
        'qty_in_display' => $qtyInDisplay,
        'qty_out_display' => $qtyOutDisplay,
        'qty_balance_display' => $qtyBalanceDisplay,
        'content_uom_code' => trim((string)($row['content_uom_code'] ?? '')),
        'buy_uom_code' => trim((string)($row['buy_uom_code'] ?? '')),
        'factor' => $factor,
        'qty_balance_raw' => $qtyBalanceRaw,
    ];
};

$buildStockLinks = static function (array $row) use ($buildQueryUrl, $resolveSearchToken, $resolveMonthValue, $date_from, $date_to): array {
    $links = [];
    $searchToken = $resolveSearchToken($row);
    $monthValue = $resolveMonthValue($row, (string)($date_from ?? ''));
    $scopeName = strtoupper(trim((string)($row['location_scope'] ?? '')));

    if ($scopeName === 'DIVISION') {
        $divisionIdValue = (int)($row['division_id'] ?? 0);
        $destinationParam = strtoupper(trim((string)($row['destination_type'] ?? 'ALL')));
        $baseParams = [
            'q' => $searchToken,
            'division_id' => $divisionIdValue > 0 ? $divisionIdValue : null,
            'destination' => $destinationParam !== '' ? $destinationParam : 'ALL',
            'date_from' => (string)($date_from ?? ''),
            'date_to' => (string)($date_to ?? ''),
        ];

        $links[] = ['label' => 'Stok Divisi', 'url' => $buildQueryUrl('inventory/stock/division', $baseParams)];
        $links[] = ['label' => 'Bulanan', 'url' => $buildQueryUrl('inventory/stock/division/daily', $baseParams + ['month' => $monthValue])];
        $links[] = ['label' => 'Daily Matrix', 'url' => $buildQueryUrl('inventory-material-daily', $baseParams + ['month' => $monthValue])];
        return $links;
    }

    $baseParams = [
        'q' => $searchToken,
        'date_from' => (string)($date_from ?? ''),
        'date_to' => (string)($date_to ?? ''),
    ];
    $links[] = ['label' => 'Stok Gudang', 'url' => $buildQueryUrl('inventory/stock/warehouse', $baseParams)];
    $links[] = ['label' => 'Bulanan', 'url' => $buildQueryUrl('inventory/stock/warehouse/daily', $baseParams + ['month' => $monthValue])];
    $links[] = ['label' => 'Daily Matrix', 'url' => $buildQueryUrl('inventory-warehouse-daily', $baseParams + ['month' => $monthValue])];
    return $links;
};

$buildSourceLink = static function (array $row) use ($buildQueryUrl): array {
    $sourceTableValue = trim((string)($row['source_table'] ?? ''));
    $sourceIdValue = (int)($row['source_id'] ?? 0);
    if ($sourceTableValue === '' || $sourceIdValue <= 0) {
        return ['label' => '', 'url' => ''];
    }

    if ($sourceTableValue === 'pur_purchase_receipt') {
        $purchaseOrderId = (int)($row['receipt_purchase_order_id'] ?? 0);
        $receiptId = (int)($row['receipt_id'] ?? $sourceIdValue);
        if ($purchaseOrderId > 0 && $receiptId > 0) {
            return [
                'label' => trim((string)($row['receipt_no'] ?? '')) !== '' ? ('Receipt ' . (string)$row['receipt_no']) : 'Detail Receipt',
                'url' => site_url('purchase-orders/detail/' . $purchaseOrderId) . '#po-receipt-' . $receiptId,
            ];
        }
    }

    if ($sourceTableValue === 'pur_store_request_fulfillment') {
        $requestId = (int)($row['source_store_request_id'] ?? 0);
        if ($requestId > 0) {
            $fulfillmentNo = trim((string)($row['source_fulfillment_no'] ?? ''));
            return [
                'label' => $fulfillmentNo !== '' ? ('Fulfillment ' . $fulfillmentNo) : 'Detail Fulfillment',
                'url' => site_url('store-requests/detail/' . $requestId) . '#sr-fulfillment-' . $sourceIdValue,
            ];
        }
    }

    if ($sourceTableValue === 'inv_component_batch') {
        $batchNo = trim((string)($row['source_batch_no'] ?? ''));
        return [
            'label' => $batchNo !== '' ? ('Batch ' . $batchNo) : 'Batch Produksi',
            'url' => $buildQueryUrl('production/component-batches', [
                'q' => $batchNo !== '' ? $batchNo : $sourceIdValue,
            ]) . '#component-batch-' . $sourceIdValue,
        ];
    }

    if ($sourceTableValue === 'inv_stock_adjustment') {
      $scopeName = strtoupper(trim((string)($row['location_scope'] ?? 'WAREHOUSE')));
      $route = $scopeName === 'DIVISION'
        ? 'inventory/stock/adjustment/division'
        : 'inventory/stock/adjustment/warehouse';
      return [
        'label' => 'Adjustment Stok',
        'url' => site_url($route) . '#stock-adjustment-' . $sourceIdValue,
      ];
    }

    return ['label' => '', 'url' => ''];
};

$headerMonth = '';
if (!empty($date_from ?? '') && preg_match('/^\d{4}-\d{2}/', (string)$date_from) === 1) {
    $headerMonth = substr((string)$date_from, 0, 7);
} else {
    $headerMonth = date('Y-m');
}

$summaryLotCount = count($rows);
$summaryBalance = 0.0;
$summaryValue = 0.0;
foreach ($rows as $row) {
    $displayMeta = $resolveLotDisplayMeta($row);
    $summaryBalance += (float)($displayMeta['qty_balance_display'] ?? 0);
    $summaryValue += (float)($displayMeta['qty_balance_display'] ?? 0) * (float)($row['unit_cost'] ?? 0);
}
?>

<style>
  .lot-audit-filter-card,
  .lot-audit-table-card {
    border:1px solid rgba(226,212,200,.88);
    border-radius:22px;
    box-shadow:0 14px 30px rgba(58,38,30,.06);
  }
  .lot-audit-ref-links {
    display:flex;
    gap:.35rem;
    flex-wrap:wrap;
    margin-top:.3rem;
  }
  .lot-audit-ref-links .btn {
    --bs-btn-padding-y:.16rem;
    --bs-btn-padding-x:.48rem;
    --bs-btn-font-size:.68rem;
    border-radius:999px;
  }
  .lot-audit-table-card .table-responsive {
    max-height: 72vh;
    overflow-y: auto;
    overflow-x: auto;
  }
  .lot-audit-table-card .table-responsive table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    border-bottom: 2px solid rgba(226,212,200,.88);
  }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-stack-line page-title-icon"></i><?php echo html_escape($title ?? ($pageScope === 'WAREHOUSE' ? 'Audit Profil Gudang' : 'Audit Lot Material')); ?></h4>
  <small class="text-muted"><?php echo html_escape((string)($subtitle ?? ($pageScope === 'WAREHOUSE' ? 'Snapshot profil gudang per barang dan identity stok.' : 'Posisi lot FIFO per scope, profile, dan lokasi stok.'))); ?></small>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php if ($pageScope === 'WAREHOUSE'): ?>
    <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'WAREHOUSE', 'active_tab' => 'lot']); ?>
  <?php elseif ($pageScope === 'DIVISION'): ?>
    <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'lot']); ?>
  <?php else: ?>
    <a href="<?php echo site_url('inventory/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang</a>
    <a href="<?php echo site_url('inventory/stock/division/lot'); ?>" class="btn btn-outline-secondary">Lot Bahan Baku</a>
  <?php endif; ?>
  <a href="<?php echo site_url('inventory/fifo-audit?scope=' . ($pageScope === 'WAREHOUSE' ? 'WAREHOUSE' : 'DIVISION')); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-bar-chart-line me-1"></i><?php echo $pageScope === 'WAREHOUSE' ? 'Audit Profil Gudang' : 'Audit FIFO'; ?>
  </a>
</div>
<?php if ($pageScope === 'WAREHOUSE'): ?>
<?php $this->load->view('purchase/_warehouse_stock_generate_btn', [
  'warehouse_action_params' => ['month' => !empty($date_from) ? date('Y-m', strtotime((string)$date_from)) : date('Y-m'), 'date_from' => (string)($date_from ?? '')],
]); ?>
<?php endif; ?>
<?php if ($pageScope === 'DIVISION'): ?>
<?php
$lotGenMonth = !empty($date_from) ? date('Y-m', strtotime((string)$date_from)) : date('Y-m');
$this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $lotGenMonth, 'division_id' => (string)(int)($division_id ?? 0), 'destination_type' => (string)($destination ?? '')],
]);
?>
<?php endif; ?>

<div class="card mb-3 lot-audit-filter-card">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <?php if ($scopeLocked): ?>
        <input type="hidden" name="scope" value="<?php echo html_escape($scopeValue); ?>">
      <?php else: ?>
      <div class="col-md-2">
        <label class="form-label mb-1">Scope</label>
        <select class="form-select" name="scope">
          <option value="ALL" <?php echo $scopeValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="WAREHOUSE" <?php echo $scopeValue === 'WAREHOUSE' ? 'selected' : ''; ?>>Warehouse</option>
          <option value="DIVISION" <?php echo $scopeValue === 'DIVISION' ? 'selected' : ''; ?>>Division</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select class="form-select" name="status">
          <option value="OPEN" <?php echo $statusValue === 'OPEN' ? 'selected' : ''; ?>>Open</option>
          <option value="ALL" <?php echo $statusValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="CLOSED" <?php echo $statusValue === 'CLOSED' ? 'selected' : ''; ?>>Closed</option>
        </select>
      </div>
      <?php if ($scopeValue !== 'WAREHOUSE'): ?>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select class="form-select" name="division_id" id="lotDivision">
          <option value="">Semua Divisi</option>
          <?php foreach ($divisionOptions as $divisionRow): ?>
            <?php
              $id = (int)($divisionRow['id'] ?? 0);
              $label = $formatDivisionLabel((string)($divisionRow['code'] ?? ''), (string)($divisionRow['name'] ?? ''), $id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo ((int)($division_id ?? 0) === $id) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Tujuan</label>
        <select class="form-select" name="destination" id="lotDestination">
          <option value="ALL" <?php echo $destinationValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="REGULER" <?php echo $destinationValue === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT" <?php echo $destinationValue === 'EVENT' ? 'selected' : ''; ?>>Event</option>
          <option value="BAR" <?php echo $destinationValue === 'BAR' ? 'selected' : ''; ?>>Bar Reguler</option>
          <option value="KITCHEN" <?php echo $destinationValue === 'KITCHEN' ? 'selected' : ''; ?>>Kitchen Reguler</option>
          <option value="BAR_EVENT" <?php echo $destinationValue === 'BAR_EVENT' ? 'selected' : ''; ?>>Bar Event</option>
          <option value="KITCHEN_EVENT" <?php echo $destinationValue === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>Kitchen Event</option>
          <option value="ROASTERY" <?php echo $destinationValue === 'ROASTERY' ? 'selected' : ''; ?>>Roastery Reguler</option>
          <option value="ROASTERY_EVENT" <?php echo $destinationValue === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>Roastery Event</option>
          <option value="OFFICE" <?php echo $destinationValue === 'OFFICE' ? 'selected' : ''; ?>>Office</option>
          <option value="GUDANG" <?php echo $destinationValue === 'GUDANG' ? 'selected' : ''; ?>>Gudang</option>
          <option value="OTHER" <?php echo $destinationValue === 'OTHER' ? 'selected' : ''; ?>>Other</option>
        </select>
      </div>
      <?php else: ?>
        <input type="hidden" name="division_id" value="<?php echo (int)($division_id ?? 0); ?>">
        <input type="hidden" name="destination" value="ALL">
      <?php endif; ?>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($q ?? '')); ?>" placeholder="Lot / profile / item / material / source ref">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Profile Key</label>
        <input type="text" class="form-control" name="profile_key" value="<?php echo html_escape((string)($profile_key ?? '')); ?>" placeholder="Exact profile key">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" min="1" max="500" name="limit" value="<?php echo (int)($limit ?? 200); ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php $this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan audit lot',
  'stock_summary_cards' => [
    ['label' => 'Jumlah Lot', 'value' => number_format($summaryLotCount), 'tone' => 'violet', 'icon' => 'ri-stack-line'],
    ['label' => 'Qty Balance Total', 'value' => number_format($summaryBalance, 2, ',', '.'), 'tone' => 'amber', 'icon' => 'ri-scales-3-line'],
    ['label' => 'Nilai Estimasi', 'value' => 'Rp ' . number_format($summaryValue, 2, ',', '.'), 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
  ],
]); ?>

<div class="card lot-audit-table-card">
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle mb-0 fin-audit-table">
      <thead>
        <tr>
          <th>Lot</th>
          <th>Scope</th>
          <th>Objek</th>
          <th>Profile</th>
          <th>Receipt</th>
          <th class="text-end col-balance">Qty In</th>
          <th class="text-end col-delta">Qty Out</th>
          <th class="text-end col-balance">Balance</th>
          <th class="text-end col-amount">Unit Cost</th>
          <th>Ref</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada lot yang sesuai filter.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $divisionText = $formatDivisionLabel((string)($row['division_code'] ?? ''), (string)($row['division_name'] ?? ''), (string)($row['division_id'] ?? '-'));
              $objectText = trim((string)($row['item_name'] ?? ''));
              if ($objectText === '') {
                  $objectText = trim((string)($row['material_name'] ?? ''));
              }
              $objectCode = trim((string)($row['item_code'] ?? ''));
              if ($objectCode === '') {
                  $objectCode = trim((string)($row['material_code'] ?? ''));
              }
              $profileText = trim((string)($row['profile_key'] ?? ''));
              if ($profileText === '') {
                  $profileText = '-';
              }
              $sourceLink = $buildSourceLink($row);
              $stockLinks = $buildStockLinks($row);
              $displayMeta = $resolveLotDisplayMeta($row);
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?php echo html_escape((string)($row['lot_no'] ?? '-')); ?></div>
                <div class="small text-muted"><?php echo !empty($row['parent_lot_no']) ? 'Parent: ' . html_escape((string)$row['parent_lot_no']) : 'Root lot'; ?></div>
              </td>
              <td>
                <div><?php echo html_escape((string)($row['location_scope'] ?? '-')); ?></div>
                <div class="small text-muted"><?php echo html_escape((string)($row['destination_name'] ?? '-')); ?><?php echo !empty($row['division_id']) ? ' | ' . html_escape($divisionText) : ''; ?></div>
              </td>
              <td>
                <div class="fw-semibold"><?php echo html_escape($objectText !== '' ? $objectText : '-'); ?></div>
                <div class="small text-muted"><?php echo html_escape($objectCode !== '' ? $objectCode : '-'); ?></div>
              </td>
              <td>
                <div class="small text-break"><?php echo html_escape($profileText); ?></div>
                <div class="small text-muted"><?php echo html_escape((string)($row['buy_uom_code'] ?? '-')); ?> -> <?php echo html_escape((string)($row['content_uom_code'] ?? '-')); ?></div>
                <?php if (!empty($displayMeta['legacy_buy_based'])): ?>
                  <div class="small text-warning">Lot legacy berbasis buy qty, ditampilkan sebagai ekuivalen isi.</div>
                <?php endif; ?>
              </td>
              <td>
                <div><?php echo html_escape((string)($row['receipt_date'] ?? '-')); ?></div>
                <div class="small text-muted"><?php echo !empty($row['expiry_date']) ? 'Exp ' . html_escape((string)$row['expiry_date']) : 'Tanpa expiry'; ?></div>
              </td>
              <td class="text-end col-balance">
                <?php echo number_format((float)($displayMeta['qty_in_display'] ?? 0), 2, ',', '.'); ?>
                <?php if (!empty($displayMeta['content_uom_code'])): ?><div class="small text-muted"><?php echo html_escape($displayMeta['content_uom_code']); ?></div><?php endif; ?>
              </td>
              <td class="text-end col-delta fin-audit-delta-negative">
                <?php echo number_format((float)($displayMeta['qty_out_display'] ?? 0) * -1, 2, ',', '.'); ?>
                <?php if (!empty($displayMeta['content_uom_code'])): ?><div class="small text-muted"><?php echo html_escape($displayMeta['content_uom_code']); ?></div><?php endif; ?>
              </td>
              <td class="text-end col-balance fw-semibold">
                <?php echo number_format((float)($displayMeta['qty_balance_display'] ?? 0), 2, ',', '.'); ?>
                <?php if (!empty($displayMeta['content_uom_code'])): ?><div class="small text-muted"><?php echo html_escape($displayMeta['content_uom_code']); ?></div><?php endif; ?>
                <?php if (!empty($displayMeta['legacy_buy_based'])): ?>
                  <div class="small text-muted">raw <?php echo number_format((float)($displayMeta['qty_balance_raw'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape($displayMeta['buy_uom_code'] !== '' ? $displayMeta['buy_uom_code'] : '-'); ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end col-amount">Rp <?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td>
                <div class="small"><?php echo html_escape((string)($row['source_table'] ?? '-')); ?><?php echo !empty($row['source_id']) ? ' #' . (int)$row['source_id'] : ''; ?></div>
                <div class="lot-audit-ref-links">
                  <a href="<?php echo html_escape(site_url('inventory/stock/lot/usage/' . (int)($row['id'] ?? 0))); ?>" class="btn btn-outline-primary btn-sm">Pemakaian</a>
                  <?php if (!empty($sourceLink['url']) && !empty($sourceLink['label'])): ?>
                    <a href="<?php echo html_escape($sourceLink['url']); ?>" class="btn btn-outline-secondary btn-sm"><?php echo html_escape($sourceLink['label']); ?></a>
                  <?php endif; ?>
                  <?php foreach ($stockLinks as $stockLink): ?>
                    <a href="<?php echo html_escape((string)($stockLink['url'] ?? '#')); ?>" class="btn btn-outline-secondary btn-sm"><?php echo html_escape((string)($stockLink['label'] ?? 'Stok')); ?></a>
                  <?php endforeach; ?>
                </div>
                <div class="small text-muted mt-1">Issue line: <?php echo number_format((int)($row['issue_line_count'] ?? 0)); ?> | Qty keluar: <?php echo number_format((float)($row['issue_qty_total'] ?? 0), 2, ',', '.'); ?></div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var divisionEl = document.getElementById('lotDivision');
  var destinationEl = document.getElementById('lotDestination');
  var guardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  if (!divisionEl || !destinationEl) {
    return;
  }

  function applyDestinationGuard() {
    var divisionId = String(divisionEl.value || '');
    var allowed = guardMap[divisionId] || [];
    Array.prototype.forEach.call(destinationEl.options, function (opt) {
      var value = String(opt.value || '').toUpperCase();
      if (!divisionId || value === 'ALL' || value === 'REGULER' || value === 'EVENT' || value === 'GUDANG') {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      var keep = allowed.indexOf(value) >= 0;
      opt.hidden = !keep;
      opt.disabled = !keep;
    });
    if (destinationEl.selectedOptions.length === 0 || destinationEl.selectedOptions[0].disabled) {
      destinationEl.value = 'ALL';
    }
  }

  divisionEl.addEventListener('change', applyDestinationGuard);
  applyDestinationGuard();
})();
</script>
