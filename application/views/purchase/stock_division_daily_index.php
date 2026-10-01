<?php
$baseUrl = site_url('inventory/stock/division/daily');
$lotAuditBaseUrl = site_url('inventory/stock/division/lot');
$genMonth = $month !== '' ? substr((string)$month, 0, 7) : date('Y-m');
$windowStart = $genMonth . '-01';
$windowEnd = date('Y-m-t', strtotime($windowStart));
$buildLotUrl = static function (array $row) use ($lotAuditBaseUrl): string {
  $searchToken = trim((string)($row['profile_key'] ?? ''));
  if ($searchToken === '') { $searchToken = trim((string)($row['item_code'] ?? '')); }
  if ($searchToken === '') { $searchToken = trim((string)($row['material_code'] ?? '')); }
  if ($searchToken === '') { $searchToken = trim((string)($row['item_name'] ?? '')); }
  if ($searchToken === '') { $searchToken = trim((string)($row['material_name'] ?? '')); }
  $destination = trim((string)($row['destination_type'] ?? ''));
  if ($destination === '') { $destination = trim((string)($row['destination_group'] ?? 'ALL')); }
  $params = [
    'q' => $searchToken,
    'profile_key' => trim((string)($row['profile_key'] ?? '')),
    'division_id' => (int)($row['division_id'] ?? 0) > 0 ? (int)($row['division_id'] ?? 0) : null,
    'destination' => $destination,
    'item_id' => (int)($row['item_id'] ?? 0) > 0 ? (int)($row['item_id'] ?? 0) : null,
    'material_id' => (int)($row['material_id'] ?? 0) > 0 ? (int)($row['material_id'] ?? 0) : null,
  ];
  $params = array_filter($params, static function ($value) { return $value !== null && $value !== ''; });
  return $lotAuditBaseUrl . (!empty($params) ? ('?' . http_build_query($params)) : '');
};
$rowsData = is_array($rows ?? null) ? $rows : [];
$monthlyMap = [];
foreach ($rowsData as $row) {
  $profileNameKey = strtoupper(trim((string)($row['profile_name'] ?? '')));
  $profileBrandKey = strtoupper(trim((string)($row['profile_brand'] ?? '')));
  $profileDescKey = strtoupper(trim((string)($row['profile_description'] ?? '')));
  $profileContentPerBuyKey = number_format((float)($row['profile_content_per_buy'] ?? 0), 6, '.', '');
  $key = implode('|', [
    (int)($row['division_id'] ?? 0),
    strtoupper((string)($row['destination_group'] ?? 'REGULER')),
    strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
    (int)($row['item_id'] ?? 0),
    (int)($row['material_id'] ?? 0),
    (int)($row['buy_uom_id'] ?? 0),
    (int)($row['content_uom_id'] ?? 0),
    (string)($row['profile_key'] ?? ''),
    $profileNameKey,
    $profileBrandKey,
    $profileDescKey,
    $profileContentPerBuyKey,
    strtoupper(trim((string)($row['profile_buy_uom_code'] ?? ''))),
    strtoupper(trim((string)($row['profile_content_uom_code'] ?? ''))),
  ]);

  if (!isset($monthlyMap[$key])) {
    $monthlyMap[$key] = [
      'division_id' => (int)($row['division_id'] ?? 0),
      'division_code' => (string)($row['division_code'] ?? ''),
      'division_name' => (string)($row['division_name'] ?? ''),
      'destination_group' => strtoupper((string)($row['destination_group'] ?? 'REGULER')),
      'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
      'item_id' => (int)($row['item_id'] ?? 0),
      'material_id' => (int)($row['material_id'] ?? 0),
      'profile_key' => (string)($row['profile_key'] ?? ''),
      'item_code' => (string)($row['item_code'] ?? ''),
      'item_name' => (string)($row['item_name'] ?? ''),
      'material_code' => (string)($row['material_code'] ?? ''),
      'material_name' => (string)($row['material_name'] ?? ''),
      'profile_name' => (string)($row['profile_name'] ?? '-'),
      'profile_brand' => (string)($row['profile_brand'] ?? '-'),
      'profile_description' => (string)($row['profile_description'] ?? '-'),
      'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
      'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 0),
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'opening_qty_content' => 0.0, 'opening_qty_pack' => 0.0,
      'in_qty_content' => 0.0, 'in_qty_pack' => 0.0,
      'out_qty_content' => 0.0, 'out_qty_pack' => 0.0,
      'adjustment_qty_content' => 0.0, 'adjustment_qty_pack' => 0.0,
      'closing_qty_content' => 0.0, 'closing_qty_pack' => 0.0,
      'total_value' => 0.0, 'avg_cost_per_content' => 0.0,
      'discard_qty_content' => 0.0, 'discard_qty_pack' => 0.0,
      'spoil_qty_content' => 0.0, 'spoil_qty_pack' => 0.0,
      'waste_qty_content' => 0.0, 'waste_qty_pack' => 0.0,
      'waste_component_qty_content' => 0.0, 'waste_component_qty_pack' => 0.0, 'waste_component_value' => 0.0,
      'spoilage_qty_content' => 0.0, 'spoilage_qty_pack' => 0.0, 'spoilage_value' => 0.0,
      'process_loss_qty_content' => 0.0, 'process_loss_qty_pack' => 0.0, 'process_loss_value' => 0.0,
      'variance_qty_content' => 0.0, 'variance_qty_pack' => 0.0, 'variance_value' => 0.0,
      'adjustment_plus_qty_content' => 0.0, 'adjustment_plus_qty_pack' => 0.0, 'adjustment_plus_value' => 0.0,
      'audit_has_mismatch' => 0, 'audit_mismatch_qty_content' => 0.0, 'audit_mismatch_notes' => '',
      '_min_date' => null, '_max_date' => null,
    ];
  }

  $movementDate = (string)($row['movement_date'] ?? '');
  $entry =& $monthlyMap[$key];
  $profileContentPerBuy = (float)($row['profile_content_per_buy'] ?? 0);
  $inQtyContent = (float)($row['in_qty_content'] ?? 0);
  $outQtyContent = (float)($row['out_qty_content'] ?? 0);
  $adjustmentQtyContent = (float)($row['adjustment_qty_content'] ?? 0);
  $discardQtyContent = (float)($row['discard_qty_content'] ?? 0);
  $spoilQtyContent = (float)($row['spoil_qty_content'] ?? 0);
  $wasteQtyContent = (float)($row['waste_qty_content'] ?? 0);
  $wasteComponentQtyContent = $wasteQtyContent + $discardQtyContent;
  $spoilageQtyContent = $spoilQtyContent;
  $processLossQtyContent = (float)($row['process_loss_qty_content'] ?? 0);
  $varianceQtyContent = (float)($row['variance_qty_content'] ?? 0);
  if ($varianceQtyContent <= 0 && $adjustmentQtyContent < 0) { $varianceQtyContent = abs($adjustmentQtyContent); }
  $adjustmentPlusQtyContent = (float)($row['adjustment_plus_qty_content'] ?? 0);
  if ($adjustmentPlusQtyContent <= 0 && $adjustmentQtyContent > 0) { $adjustmentPlusQtyContent = $adjustmentQtyContent; }

  $avgCostPerContent = (float)($row['avg_cost_per_content'] ?? 0);
  $wasteComponentValue = (float)($row['waste_total_value'] ?? 0);
  if ($wasteComponentValue <= 0 && $wasteComponentQtyContent > 0) { $wasteComponentValue = $wasteComponentQtyContent * $avgCostPerContent; }
  $spoilageValue = (float)($row['spoilage_total_value'] ?? 0);
  if ($spoilageValue <= 0 && $spoilageQtyContent > 0) { $spoilageValue = $spoilageQtyContent * $avgCostPerContent; }
  $processLossValue = (float)($row['process_loss_total_value'] ?? 0);
  if ($processLossValue <= 0 && $processLossQtyContent > 0) { $processLossValue = $processLossQtyContent * $avgCostPerContent; }
  $varianceValue = (float)($row['variance_total_value'] ?? 0);
  if ($varianceValue <= 0 && $varianceQtyContent > 0) { $varianceValue = $varianceQtyContent * $avgCostPerContent; }
  $adjustmentPlusValue = (float)($row['adjustment_plus_total_value'] ?? 0);
  if ($adjustmentPlusValue <= 0 && $adjustmentPlusQtyContent > 0) { $adjustmentPlusValue = $adjustmentPlusQtyContent * $avgCostPerContent; }

  $entry['in_qty_content'] += $inQtyContent;
  $entry['out_qty_content'] += $outQtyContent;
  $entry['adjustment_qty_content'] += $adjustmentQtyContent;
  $entry['discard_qty_content'] += $discardQtyContent;
  $entry['spoil_qty_content'] += $spoilQtyContent;
  $entry['waste_qty_content'] += $wasteQtyContent;
  $entry['waste_component_qty_content'] += $wasteComponentQtyContent;
  $entry['waste_component_value'] += $wasteComponentValue;
  $entry['spoilage_qty_content'] += $spoilageQtyContent;
  $entry['spoilage_value'] += $spoilageValue;
  $entry['process_loss_qty_content'] += $processLossQtyContent;
  $entry['process_loss_value'] += $processLossValue;
  $entry['variance_qty_content'] += $varianceQtyContent;
  $entry['variance_value'] += $varianceValue;
  $entry['adjustment_plus_qty_content'] += $adjustmentPlusQtyContent;
  $entry['adjustment_plus_value'] += $adjustmentPlusValue;
  if (!empty($row['audit_has_mismatch'])) {
    $entry['audit_has_mismatch'] = 1;
    $entry['audit_mismatch_qty_content'] = (float)($row['audit_mismatch_qty_content'] ?? $entry['audit_mismatch_qty_content'] ?? 0);
    $entry['audit_mismatch_notes'] = trim((string)($row['audit_mismatch_notes'] ?? $entry['audit_mismatch_notes'] ?? ''));
  }
  if ($profileContentPerBuy > 0) {
    $entry['in_qty_pack']                  += ($inQtyContent / $profileContentPerBuy);
    $entry['out_qty_pack']                 += ($outQtyContent / $profileContentPerBuy);
    $entry['adjustment_qty_pack']          += ($adjustmentQtyContent / $profileContentPerBuy);
    $entry['discard_qty_pack']             += ($discardQtyContent / $profileContentPerBuy);
    $entry['spoil_qty_pack']               += ($spoilQtyContent / $profileContentPerBuy);
    $entry['waste_qty_pack']               += ($wasteQtyContent / $profileContentPerBuy);
    $entry['waste_component_qty_pack']     += ($wasteComponentQtyContent / $profileContentPerBuy);
    $entry['spoilage_qty_pack']            += ($spoilageQtyContent / $profileContentPerBuy);
    $entry['process_loss_qty_pack']        += ($processLossQtyContent / $profileContentPerBuy);
    $entry['variance_qty_pack']            += ($varianceQtyContent / $profileContentPerBuy);
    $entry['adjustment_plus_qty_pack']     += ($adjustmentPlusQtyContent / $profileContentPerBuy);
  }
  if ($entry['_min_date'] === null || ($movementDate !== '' && $movementDate < $entry['_min_date'])) {
    $entry['_min_date'] = $movementDate;
    $entry['opening_qty_content'] = (float)($row['opening_qty_content'] ?? 0);
    $entry['opening_qty_pack'] = $profileContentPerBuy > 0 ? ($entry['opening_qty_content'] / $profileContentPerBuy) : 0.0;
  }
  if ($entry['_max_date'] === null || ($movementDate !== '' && $movementDate > $entry['_max_date'])) {
    $entry['_max_date'] = $movementDate;
    $entry['closing_qty_content'] = (float)($row['closing_qty_content'] ?? 0);
    $entry['closing_qty_pack'] = $profileContentPerBuy > 0 ? ($entry['closing_qty_content'] / $profileContentPerBuy) : 0.0;
    $entry['total_value'] = (float)($row['total_value'] ?? 0);
    $entry['avg_cost_per_content'] = (float)($row['avg_cost_per_content'] ?? 0);
  }
}

$monthlyRows = array_values($monthlyMap);
usort($monthlyRows, static function (array $a, array $b): int {
  $cmpDiv = strcasecmp(trim((string)($a['division_name'] ?? '')), trim((string)($b['division_name'] ?? '')));
  if ($cmpDiv !== 0) { return $cmpDiv; }
  $cmpDest = strcasecmp((string)($a['destination_group'] ?? ''), (string)($b['destination_group'] ?? ''));
  if ($cmpDest !== 0) { return $cmpDest; }
  $aName = trim(($a['item_name'] ?? '') !== '' ? (string)$a['item_name'] : (string)($a['material_name'] ?? ''));
  $bName = trim(($b['item_name'] ?? '') !== '' ? (string)$b['item_name'] : (string)($b['material_name'] ?? ''));
  $cmp = strcasecmp($aName, $bName);
  if ($cmp !== 0) { return $cmp; }
  return strcasecmp((string)($a['profile_name'] ?? ''), (string)($b['profile_name'] ?? ''));
});

$parentMap = [];
foreach ($monthlyRows as $row) {
  $materialId2 = (int)($row['material_id'] ?? 0);
  $itemId2 = (int)($row['item_id'] ?? 0);
  $objectIdentity = $materialId2 > 0 ? ('M-' . $materialId2) : ('I-' . $itemId2);
  if ($objectIdentity === 'M-0' || $objectIdentity === 'I-0') {
    $objectIdentity .= '|' . strtoupper(trim((string)($row['material_code'] ?? '') . '|' . (string)($row['item_code'] ?? '')));
  }
  $parentKey = implode('|', [(int)($row['division_id'] ?? 0), strtoupper((string)($row['destination_group'] ?? 'REGULER')), $objectIdentity]);

  if (!isset($parentMap[$parentKey])) {
    $parentMap[$parentKey] = [
      'division_id' => (int)($row['division_id'] ?? 0),
      'division_code' => (string)($row['division_code'] ?? ''),
      'division_name' => (string)($row['division_name'] ?? ''),
      'destination_group' => strtoupper((string)($row['destination_group'] ?? 'REGULER')),
      'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
      'item_code' => (string)($row['item_code'] ?? ''),
      'item_name' => (string)($row['item_name'] ?? ''),
      'material_code' => (string)($row['material_code'] ?? ''),
      'material_name' => (string)($row['material_name'] ?? ''),
      'profile_count' => 0,
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'avg_content_per_buy' => 0.0,
      'opening_qty_content' => 0.0, 'opening_qty_pack' => 0.0,
      'in_qty_content' => 0.0, 'in_qty_pack' => 0.0,
      'out_qty_content' => 0.0, 'out_qty_pack' => 0.0,
      'discard_qty_content' => 0.0, 'discard_qty_pack' => 0.0,
      'spoil_qty_content' => 0.0, 'spoil_qty_pack' => 0.0,
      'waste_qty_content' => 0.0, 'waste_qty_pack' => 0.0,
      'waste_component_qty_content' => 0.0, 'waste_component_qty_pack' => 0.0, 'waste_component_value' => 0.0,
      'spoilage_qty_content' => 0.0, 'spoilage_qty_pack' => 0.0, 'spoilage_value' => 0.0,
      'process_loss_qty_content' => 0.0, 'process_loss_qty_pack' => 0.0, 'process_loss_value' => 0.0,
      'variance_qty_content' => 0.0, 'variance_qty_pack' => 0.0, 'variance_value' => 0.0,
      'adjustment_plus_qty_content' => 0.0, 'adjustment_plus_qty_pack' => 0.0, 'adjustment_plus_value' => 0.0,
      'audit_has_mismatch' => 0, 'audit_mismatch_qty_content' => 0.0, 'audit_mismatch_notes' => '',
      'adjustment_qty_content' => 0.0, 'adjustment_qty_pack' => 0.0,
      'closing_qty_content' => 0.0, 'closing_qty_pack' => 0.0,
      'total_value' => 0.0, 'avg_cost_per_content' => 0.0, 'avg_cost_per_pack' => 0.0,
      '_content_per_buy_sum' => 0.0, '_hpp_sum' => 0.0, '_hpp_pack_sum' => 0.0,
      'children' => [],
    ];
  }

  $avgCostPerContent2 = (float)($row['avg_cost_per_content'] ?? 0);
  $avgCostPerPack2 = $avgCostPerContent2 * (float)($row['profile_content_per_buy'] ?? 0);
  $parent2 =& $parentMap[$parentKey];
  $parent2['profile_count']++;
  $parent2['_content_per_buy_sum'] += (float)($row['profile_content_per_buy'] ?? 0);
  $parent2['_hpp_sum'] += $avgCostPerContent2;
  $parent2['_hpp_pack_sum'] += $avgCostPerPack2;
  if ($parent2['profile_buy_uom_code'] !== '' && (string)($row['profile_buy_uom_code'] ?? '') !== '' && $parent2['profile_buy_uom_code'] !== (string)($row['profile_buy_uom_code'] ?? '')) { $parent2['profile_buy_uom_code'] = 'MIX'; }
  if ($parent2['profile_content_uom_code'] !== '' && (string)($row['profile_content_uom_code'] ?? '') !== '' && $parent2['profile_content_uom_code'] !== (string)($row['profile_content_uom_code'] ?? '')) { $parent2['profile_content_uom_code'] = 'MIX'; }
  foreach (['opening_qty_content','opening_qty_pack','in_qty_content','in_qty_pack','out_qty_content','out_qty_pack','discard_qty_content','discard_qty_pack','spoil_qty_content','spoil_qty_pack','waste_qty_content','waste_qty_pack','waste_component_qty_content','waste_component_qty_pack','waste_component_value','spoilage_qty_content','spoilage_qty_pack','spoilage_value','process_loss_qty_content','process_loss_qty_pack','process_loss_value','variance_qty_content','variance_qty_pack','variance_value','adjustment_plus_qty_content','adjustment_plus_qty_pack','adjustment_plus_value','adjustment_qty_content','adjustment_qty_pack','closing_qty_content','closing_qty_pack','total_value'] as $mk) {
    $parent2[$mk] += (float)($row[$mk] ?? 0);
  }
  $parent2['children'][] = $row;
  if (!empty($row['audit_has_mismatch'])) {
    $parent2['audit_has_mismatch'] = 1;
    $parent2['audit_mismatch_qty_content'] += (float)($row['audit_mismatch_qty_content'] ?? 0);
    $existingNotes2 = array_filter(array_map('trim', explode(',', (string)($parent2['audit_mismatch_notes'] ?? ''))));
    $rowNotes2 = array_filter(array_map('trim', explode(',', (string)($row['audit_mismatch_notes'] ?? ''))));
    $parent2['audit_mismatch_notes'] = implode(', ', array_values(array_unique(array_merge($existingNotes2, $rowNotes2))));
  }
}
unset($parent2);

$parentRows = array_values($parentMap);
usort($parentRows, static function (array $a, array $b): int {
  $cmpDiv = strcasecmp(trim((string)($a['division_name'] ?? '')), trim((string)($b['division_name'] ?? '')));
  if ($cmpDiv !== 0) { return $cmpDiv; }
  $cmpDest = strcasecmp((string)($a['destination_group'] ?? ''), (string)($b['destination_group'] ?? ''));
  if ($cmpDest !== 0) { return $cmpDest; }
  $aName = trim(($a['item_name'] ?? '') !== '' ? (string)$a['item_name'] : (string)($a['material_name'] ?? ''));
  $bName = trim(($b['item_name'] ?? '') !== '' ? (string)$b['item_name'] : (string)($b['material_name'] ?? ''));
  return strcasecmp($aName, $bName);
});
foreach ($parentRows as &$parentRow) {
  $parentRow['children'] = array_values(array_filter((array)($parentRow['children'] ?? []), static function (array $child): bool {
    return abs(round((float)($child['closing_qty_content'] ?? 0), 2)) >= 0.01;
  }));
  $parentRow['profile_count'] = count((array)($parentRow['children'] ?? []));
  usort($parentRow['children'], static function (array $a, array $b): int {
    $aNP = (float)($a['closing_qty_content'] ?? 0) <= 0.0001;
    $bNP = (float)($b['closing_qty_content'] ?? 0) <= 0.0001;
    if ($aNP !== $bNP) { return $aNP ? 1 : -1; }
    return strcasecmp((string)($a['profile_name'] ?? ''), (string)($b['profile_name'] ?? ''));
  });
}
unset($parentRow);
$parentRows = array_values(array_filter($parentRows, static function (array $p): bool {
  return abs(round((float)($p['closing_qty_content'] ?? 0), 2)) >= 0.01;
}));
foreach ($parentRows as &$parentRow) {
  $cnt = max(1, (int)($parentRow['profile_count'] ?? 0));
  $parentRow['avg_content_per_buy']   = (float)$parentRow['_content_per_buy_sum'] / $cnt;
  $parentRow['avg_cost_per_content']  = (float)$parentRow['_hpp_sum'] / $cnt;
  $parentRow['avg_cost_per_pack']     = (float)$parentRow['_hpp_pack_sum'] / $cnt;
}
unset($parentRow);

// ── Summary (from all rows, before pagination) ──
$summaryProfiles    = count($monthlyRows);
$summaryDivisions   = [];
$summaryInPack      = 0.0;
$summaryOutPack     = 0.0;
$summaryClosingPack = 0.0;
$summaryIn          = 0.0;
$summaryOut         = 0.0;
$summaryClosing     = 0.0;
$summaryValue       = 0.0;
$summaryLosses      = 0.0;
$uniqueMaterialCodes = [];
foreach ($monthlyRows as $mRow) {
  $mDivId = (int)($mRow['division_id'] ?? 0);
  if ($mDivId > 0) { $summaryDivisions[$mDivId] = true; }
  $summaryInPack      += (float)($mRow['in_qty_pack'] ?? 0);
  $summaryOutPack     += (float)($mRow['out_qty_pack'] ?? 0);
  $summaryClosingPack += (float)($mRow['closing_qty_pack'] ?? 0);
  $summaryIn          += (float)($mRow['in_qty_content'] ?? 0);
  $summaryOut         += (float)($mRow['out_qty_content'] ?? 0);
  $summaryClosing     += (float)($mRow['closing_qty_content'] ?? 0);
  $summaryValue       += (float)($mRow['total_value'] ?? 0);
  $summaryLosses      += (float)($mRow['waste_component_qty_content'] ?? 0)
                       + (float)($mRow['spoilage_qty_content'] ?? 0)
                       + (float)($mRow['process_loss_qty_content'] ?? 0);
  $mc = trim((string)($mRow['material_code'] ?? ''));
  if ($mc !== '') $uniqueMaterialCodes[$mc] = true;
}
$summaryDivisionCount       = count($summaryDivisions);
$summaryUniqueMaterialCount = count($uniqueMaterialCodes);
$summaryAlertCount = 0;
foreach ($parentRows as $pRow) {
  if ((float)($pRow['closing_qty_content'] ?? 0) <= 0.0001) { $summaryAlertCount++; }
}

$destinationValue = strtoupper(trim((string)($destination ?? 'ALL')));
if ($destinationValue === '') { $destinationValue = 'ALL'; }
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$formatDivisionLabel = static function (string $code, string $name, $fallbackId = '-'): string {
  $code = trim($code); $name = trim($name);
  if ($code !== '' && strcasecmp($code, $name) === 0) { return $code; }
  if ($code !== '' && $name !== '') { return $code . ' - ' . $name; }
  if ($code !== '') { return $code; }
  if ($name !== '') { return $name; }
  return (string)$fallbackId;
};
$formatDestination = static function (string $group): string {
  return strtoupper(trim($group)) === 'EVENT' ? 'Event' : 'Reguler';
};

// ── Pagination ──
$perPage      = max(10, (int)($limit ?? 100));
$currentPage  = max(1, (int)($page ?? 1));
$totalParentCount = count($parentRows);
$totalPages   = $totalParentCount > 0 ? (int)ceil($totalParentCount / $perPage) : 1;
$currentPage  = min($currentPage, max(1, $totalPages));
$parentRows   = array_slice($parentRows, ($currentPage - 1) * $perPage, $perPage);

$pParams = ['limit' => $perPage];
if (!empty($q)) $pParams['q'] = $q;
if ($genMonth !== '') $pParams['month'] = $genMonth;
if ((int)($division_id ?? 0) > 0) $pParams['division_id'] = (int)$division_id;
if ($destinationValue !== 'ALL') $pParams['destination'] = $destinationValue;
if (!empty($date_from)) $pParams['date_from'] = $date_from;
if (!empty($date_to))   $pParams['date_to']   = $date_to;
$paginationQs = http_build_query($pParams);
?>

<style>
/* ── Filter ── */
.sdd-filter-grid {
  display: grid;
  grid-template-columns: 92px minmax(130px,1.2fr) 95px minmax(150px,2fr) 112px 112px 64px auto auto;
  gap: .5rem;
  align-items: end;
}
@media (max-width:1199px) { .sdd-filter-grid { grid-template-columns: 88px minmax(120px,1fr) 90px minmax(130px,2fr) 105px 105px 60px auto auto; } }
@media (max-width:991px)  { .sdd-filter-grid { grid-template-columns: 1fr 1fr 1fr 1fr; } .sdd-filter-btn { grid-column: span 2; display:flex; gap:.4rem; } }
@media (max-width:767px)  { .sdd-filter-grid { grid-template-columns: 1fr 1fr; } .sdd-filter-btn { grid-column: span 2; } }

/* ── Table ── */
.sdd-table-wrap {
  overflow-x:auto; overflow-y:auto; max-height:72vh;
}
.sdd-monthly-table thead th {
  position:sticky; top:0; z-index:2;
  background:#fff8f4; box-shadow:inset 0 -1px 0 #e8d1c5;
  white-space:nowrap;
}
.sdd-monthly-table td:nth-child(1) { width:42px; text-align:center; }
.sdd-monthly-table td:nth-child(3) { min-width:190px; }
.sdd-parent-row { background:#fff6ef; border-top:2px solid #f0d8ca; }
.sdd-child-row td { background:#fff; }
.sdd-stock-row-alert td { background:linear-gradient(180deg,#fff1ef,#fff8f7) !important; color:#8a2f2a; }
.sdd-mismatch-row td { background:linear-gradient(180deg,#fffbec,#fffdf8) !important; }
.sdd-alert-chip {
  display:inline-flex; align-items:center; padding:.13rem .44rem;
  border-radius:999px; background:#c0392b; color:#fff; font-size:.62rem; font-weight:800;
}
.sdd-mismatch-chip {
  display:inline-flex; align-items:center; padding:.13rem .44rem;
  border-radius:999px; background:#d68910; color:#fff; font-size:.62rem; font-weight:800;
}
.sdd-obj-name { font-weight:700; color:#4e1f2e; line-height:1.25; }
.sdd-child-indent {
  padding-left:1.2rem; position:relative;
}
.sdd-child-indent::before {
  content:''; position:absolute; left:.45rem; top:.2rem; bottom:.2rem;
  width:3px; border-radius:999px;
  background:linear-gradient(180deg,#ebd7cc,#d9b6a4);
}
.sdd-toggle-arrow {
  display:inline-flex; align-items:center; justify-content:center;
  width:34px; height:34px; border-radius:8px;
  border:2px solid #c8a090; background:#fff8f4; color:#7a2e1c;
  font-size:.85rem; cursor:pointer;
  transition:transform .2s ease, background .15s, border-color .15s;
  box-shadow:0 1px 4px rgba(120,60,30,.12);
}
.sdd-toggle-arrow:hover { background:#fde8de; border-color:#b07060; color:#5c1a0a; }
.sdd-toggle-arrow.is-open { transform:rotate(90deg); background:#fde8de; border-color:#b07060; }

/* ── Pagination ── */
.sdd-pagination { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; margin-top:.75rem; }
.sdd-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 .5rem;
  border:1px solid #ddd; border-radius:8px;
  background:#fff; color:#555; font-size:.8rem; font-weight:600;
  text-decoration:none; cursor:pointer; transition:all .15s;
}
.sdd-page-btn:hover { background:#f5f5f5; border-color:#bbb; color:#333; }
.sdd-page-btn.is-active { background:#6a2d3c; border-color:#6a2d3c; color:#fff; }
.sdd-page-btn.is-disabled { opacity:.4; pointer-events:none; }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
  <small class="text-muted">Rekap per bulan. Rentang tampilan opsional selalu dibatasi di dalam bulan yang dipilih.</small>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'daily']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $genMonth, 'division_id' => (string)(int)($division_id ?? 0), 'destination_type' => $destinationValue],
]); ?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>">
      <div class="sdd-filter-grid">
        <div>
          <label class="form-label mb-1">Bulan</label>
          <input type="month" class="form-control form-control-sm" id="sddMonth" name="month" value="<?php echo html_escape($genMonth); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id">
            <option value="">Semua Divisi</option>
            <?php foreach (($divisions ?? []) as $d): ?>
              <?php
                $dId = (int)($d['id'] ?? 0);
                $dCode = trim((string)($d['code'] ?? ''));
                $dName = trim((string)($d['name'] ?? ''));
                $dLabel = $dCode !== '' ? $dCode . ' - ' . $dName : ($dName !== '' ? $dName : (string)$dId);
              ?>
              <option value="<?php echo $dId; ?>" <?php echo ((int)($division_id ?? 0) === $dId) ? 'selected' : ''; ?>><?php echo html_escape($dLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination" id="sddDestination">
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
          <label class="form-label mb-1">Cari</label>
          <input type="text" class="form-control form-control-sm" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / material / profile / merk">
        </div>
        <div>
          <label class="form-label mb-1">Mulai Tampilan</label>
          <input type="date" class="form-control form-control-sm" id="sddDateFrom" name="date_from" min="<?php echo html_escape($windowStart); ?>" max="<?php echo html_escape($windowEnd); ?>" value="<?php echo html_escape((string)$date_from); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Sampai Tampilan</label>
          <input type="date" class="form-control form-control-sm" id="sddDateTo" name="date_to" min="<?php echo html_escape($windowStart); ?>" max="<?php echo html_escape($windowEnd); ?>" value="<?php echo html_escape((string)$date_to); ?>">
        </div>
        <div>
          <label class="form-label mb-1">/ Hal</label>
          <input type="number" class="form-control form-control-sm" name="limit" min="10" max="500" value="<?php echo $perPage; ?>">
        </div>
        <div class="sdd-filter-btn" style="display:flex;gap:.4rem;">
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
  var destinationEl = document.getElementById('sddDestination');
  var divisionEl = document.querySelector('select[name="division_id"]');
  var allOptions = [
    {value:'ALL',label:'Semua'},{value:'REGULER',label:'Reguler'},{value:'EVENT',label:'Event'},
    {value:'BAR',label:'Bar Reg'},{value:'KITCHEN',label:'Kitchen Reg'},{value:'ROASTERY',label:'Roastery Reg'},
    {value:'BAR_EVENT',label:'Bar Event'},{value:'KITCHEN_EVENT',label:'Kitchen Evt'},{value:'ROASTERY_EVENT',label:'Roastery Evt'},
    {value:'OFFICE',label:'Office'}
  ];
  function esc(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function syncDestinationOptions(){
    if (!destinationEl || !divisionEl) { return; }
    var divId = parseInt(divisionEl.value||'0',10);
    var current = String(destinationEl.value||'ALL').toUpperCase();
    var options = allOptions.slice();
    if (Number.isFinite(divId) && divId > 0 && guardMap[String(divId)]) {
      var allowed = (guardMap[String(divId)]||[]).map(function(x){ return String(x||'').toUpperCase(); });
      options = allOptions.filter(function(opt){
        if (opt.value==='ALL'||opt.value==='REGULER'||opt.value==='EVENT') { return true; }
        return allowed.indexOf(opt.value) !== -1;
      });
    }
    destinationEl.innerHTML = options.map(function(opt){ return '<option value="'+esc(opt.value)+'">'+esc(opt.label)+'</option>'; }).join('');
    var exists = options.some(function(opt){ return opt.value===current; });
    destinationEl.value = exists ? current : 'ALL';
  }
  if (divisionEl) { divisionEl.addEventListener('change', syncDestinationOptions); }
  syncDestinationOptions();
})();
</script>
<script>
(() => {
  const month = document.getElementById('sddMonth');
  const from = document.getElementById('sddDateFrom');
  const to = document.getElementById('sddDateTo');
  if (!month || !from || !to) return;
  const syncWindow = () => {
    if (!/^\d{4}-\d{2}$/.test(month.value)) return;
    const start = `${month.value}-01`;
    const [year, monthNumber] = month.value.split('-').map(Number);
    const end = `${month.value}-${String(new Date(year, monthNumber, 0).getDate()).padStart(2, '0')}`;
    [from, to].forEach((input) => {
      input.min = start;
      input.max = end;
      if (input.value && input.value < start) input.value = start;
      if (input.value && input.value > end) input.value = end;
    });
    if (from.value && to.value && from.value > to.value) to.value = from.value;
  };
  month.addEventListener('change', syncWindow);
  syncWindow();
})();
</script>

<?php if ($totalParentCount > 0): ?>
  <?php $this->load->view('layout/_stock_summary_cards', [
    'stock_summary_label' => 'Ringkasan snapshot stok bahan baku',
    'stock_summary_cards' => [
      ['label' => 'Item Stok', 'value' => number_format($totalParentCount), 'tone' => 'violet', 'icon' => 'ri-box-3-line'],
      ['label' => 'Divisi Aktif', 'value' => number_format($summaryDivisionCount), 'tone' => 'aqua', 'icon' => 'ri-building-2-line'],
      ['label' => 'Total Masuk', 'value' => number_format($summaryIn, 1, ',', '.'), 'detail' => number_format($summaryInPack, 1, ',', '.') . ' pack', 'tone' => 'blue', 'icon' => 'ri-arrow-down-circle-line'],
      ['label' => 'Total Keluar', 'value' => number_format($summaryOut, 1, ',', '.'), 'detail' => number_format($summaryOutPack, 1, ',', '.') . ' pack', 'tone' => 'amber', 'icon' => 'ri-arrow-up-circle-line'],
      ['label' => 'Stok Akhir', 'value' => number_format($summaryClosing, 1, ',', '.'), 'detail' => number_format($summaryClosingPack, 1, ',', '.') . ' pack', 'tone' => 'teal', 'icon' => 'ri-scales-3-line'],
      ['label' => 'Total Nilai HPP', 'value' => 'Rp ' . number_format($summaryValue, 0, ',', '.'), 'tone' => 'violet', 'icon' => 'ri-money-dollar-circle-line'],
      ['label' => 'Losses', 'value' => number_format($summaryLosses, 1, ',', '.'), 'detail' => $summaryAlertCount > 0 ? $summaryAlertCount . ' item stok habis' : 'Waste + spoilage + process loss', 'tone' => $summaryAlertCount > 0 ? 'danger' : 'amber', 'icon' => 'ri-fire-line'],
    ],
  ]); ?>
<?php endif; ?>

<!-- Table -->
<div class="card">
  <div class="sdd-table-wrap">
    <table class="table table-sm table-hover mb-0 sdd-monthly-table" id="sddMonthlyTable">
      <thead>
        <tr>
          <th></th>
          <th>Divisi / Tujuan</th>
          <th>Nama Barang</th>
          <th>Merk</th>
          <th>Keterangan</th>
          <th>Ukuran Isi</th>
          <th class="text-end">Stok Awal</th>
          <th class="text-end">Masuk</th>
          <th class="text-end">Keluar</th>
          <th class="text-end">WASTE</th>
          <th class="text-end">SPOILAGE</th>
          <th class="text-end">PROCESS LOSS</th>
          <th class="text-end">VARIANCE</th>
          <th class="text-end">Adjustment +</th>
          <th class="text-end">Stok Akhir</th>
          <th class="text-end">Nilai Total</th>
          <th class="text-end">HPP/Isi</th>
          <th class="text-end">HPP/Pack</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parentRows)): ?>
          <tr><td colspan="18" class="text-center text-muted py-4">Belum ada data bulanan divisi untuk bulan <?php echo html_escape($genMonth); ?>.</td></tr>
        <?php else: ?>
          <?php foreach ($parentRows as $idx => $parent): ?>
            <?php
              $divisionText  = $formatDivisionLabel((string)($parent['division_code'] ?? ''), (string)($parent['division_name'] ?? ''), (string)($parent['division_id'] ?? '-'));
              $destinationText = $formatDestination((string)($parent['destination_group'] ?? 'REGULER'));
              $itemText      = trim((string)($parent['item_name'] ?? ''));
              $materialText  = trim((string)($parent['material_name'] ?? ''));
              $objectText    = $itemText !== '' ? $itemText : ($materialText !== '' ? $materialText : '-');
              $rowId         = 'sdd-p-' . $idx . '-pg' . $currentPage;
              $isExpandable  = ((int)($parent['profile_count'] ?? 0) > 1);
              $singleChild   = (!$isExpandable && !empty($parent['children'])) ? $parent['children'][0] : null;

              $uomPack    = (string)($parent['profile_buy_uom_code'] ?? '-');
              $uomContent = (string)($parent['profile_content_uom_code'] ?? '');
              if (is_array($singleChild)) {
                $uomPack    = (string)($singleChild['profile_buy_uom_code'] ?? $uomPack);
                $uomContent = (string)($singleChild['profile_content_uom_code'] ?? $uomContent);
              }
              $contentPerBuy = is_array($singleChild)
                ? (float)($singleChild['profile_content_per_buy'] ?? 0)
                : (float)($parent['avg_content_per_buy'] ?? 0);
              $sizeCol = number_format($contentPerBuy, 2, ',', '.') . ' ' . html_escape($uomContent) . ' / ' . html_escape($uomPack);

              $brandCol   = '-';
              $descCol    = '-';
              $profileLine = '';
              if (is_array($singleChild)) {
                $brandCol    = html_escape(trim((string)($singleChild['profile_brand'] ?? '-')));
                $descCol     = html_escape(trim((string)($singleChild['profile_description'] ?? '-')));
                $pName       = trim((string)($singleChild['profile_name'] ?? '-'));
                $profileLine = html_escape($pName) . ' &nbsp;<a href="' . html_escape($buildLotUrl($singleChild)) . '" class="small">Lihat Lot</a>';
              } else {
                $profileLine = '<span class="text-muted small">' . (int)($parent['profile_count'] ?? 0) . ' profil</span>';
              }

              $isZeroStock   = (float)($parent['closing_qty_content'] ?? 0) <= 0.0001;
              $hasMismatch   = !empty($parent['audit_has_mismatch']);
              $rowAlertClass = $hasMismatch ? ' sdd-mismatch-row' : ($isZeroStock ? ' sdd-stock-row-alert' : '');
            ?>
            <tr class="sdd-parent-row<?php echo $rowAlertClass; ?>">
              <td>
                <?php if ($isExpandable): ?>
                  <button type="button" class="sdd-toggle-arrow" data-target="<?php echo html_escape($rowId); ?>">&#9658;</button>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold small"><?php echo html_escape($divisionText); ?></div>
                <div class="text-muted" style="font-size:.72rem"><?php echo html_escape($destinationText); ?></div>
              </td>
              <td>
                <div class="sdd-obj-name"><?php $matIdLink = (int)($parent['material_id'] ?? 0); echo $matIdLink > 0 ? '<a href="' . html_escape(site_url('master/material/usage/' . $matIdLink)) . '" class="text-decoration-none text-body">' . html_escape($objectText) . '</a>' : html_escape($objectText); ?></div>
                <div class="small mt-1"><?php echo $profileLine; ?></div>
                <?php if ($hasMismatch): ?>
                  <span class="sdd-mismatch-chip mt-1">Mismatch <?php echo ui_num((float)($parent['audit_mismatch_qty_content'] ?? 0)); ?></span>
                <?php endif; ?>
                <?php if ($isZeroStock): ?>
                  <span class="sdd-alert-chip mt-1">Stok Habis</span>
                <?php endif; ?>
              </td>
              <td class="small"><?php echo $brandCol; ?></td>
              <td class="small"><?php echo $descCol; ?></td>
              <td class="small" style="white-space:nowrap"><?php echo $sizeCol; ?></td>
              <td class="text-end"><div class="fw-semibold small"><?php echo ui_num((float)($parent['opening_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($parent['opening_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div></td>
              <td class="text-end text-success"><div class="fw-semibold small"><?php echo ui_num((float)($parent['in_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($parent['in_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div></td>
              <td class="text-end text-danger"><div class="fw-semibold small"><?php echo ui_num((float)($parent['out_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($parent['out_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div></td>
              <td class="text-end"><div class="small"><?php echo ui_num((float)($parent['waste_component_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($parent['waste_component_value'] ?? 0), 0, ',', '.'); ?></div></td>
              <td class="text-end"><div class="small"><?php echo ui_num((float)($parent['spoilage_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($parent['spoilage_value'] ?? 0), 0, ',', '.'); ?></div></td>
              <td class="text-end"><div class="small"><?php echo ui_num((float)($parent['process_loss_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($parent['process_loss_value'] ?? 0), 0, ',', '.'); ?></div></td>
              <td class="text-end"><div class="small"><?php echo ui_num((float)($parent['variance_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($parent['variance_value'] ?? 0), 0, ',', '.'); ?></div></td>
              <td class="text-end"><div class="small"><?php echo ui_num((float)($parent['adjustment_plus_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($parent['adjustment_plus_value'] ?? 0), 0, ',', '.'); ?></div></td>
              <td class="text-end fw-semibold"><div class="small"><?php echo ui_num((float)($parent['closing_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo html_escape($uomContent); ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($parent['closing_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div></td>
              <td class="text-end small"><?php echo number_format((float)($parent['total_value'] ?? 0), 0, ',', '.'); ?></td>
              <td class="text-end small"><?php echo ui_num((float)($parent['avg_cost_per_content'] ?? 0)); ?></td>
              <td class="text-end small"><?php echo ui_num((float)($parent['avg_cost_per_pack'] ?? 0)); ?></td>
            </tr>
            <?php if ($isExpandable): ?>
            <?php foreach ((array)($parent['children'] ?? []) as $child): ?>
              <?php
                $cUomPack    = (string)($child['profile_buy_uom_code'] ?? '-');
                $cUomContent = (string)($child['profile_content_uom_code'] ?? '');
                $cAvgCostPack = (float)($child['avg_cost_per_content'] ?? 0) * (float)($child['profile_content_per_buy'] ?? 0);
                $cSizeStr    = number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.') . ' ' . html_escape($cUomContent) . ' / ' . html_escape($cUomPack);
                $cIsZero     = (float)($child['closing_qty_content'] ?? 0) <= 0.0001;
                $cHasMismatch = !empty($child['audit_has_mismatch']);
                $cAlertClass = $cHasMismatch ? 'sdd-mismatch-row' : ($cIsZero ? 'sdd-stock-row-alert' : '');
              ?>
              <tr class="sdd-child-row <?php echo html_escape($cAlertClass . ' ' . $rowId); ?>" style="display:none">
                <td></td>
                <td></td>
                <td>
                  <div class="sdd-child-indent">
                    <div class="small fw-semibold"><?php echo html_escape((string)($child['profile_name'] ?? '-')); ?></div>
                    <div class="small"><a href="<?php echo html_escape($buildLotUrl($child)); ?>">Lihat Lot</a></div>
                    <?php if ($cHasMismatch): ?>
                      <span class="sdd-mismatch-chip">Mismatch <?php echo ui_num((float)($child['audit_mismatch_qty_content'] ?? 0)); ?></span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="small"><?php echo html_escape(trim((string)($child['profile_brand'] ?? '-'))); ?></td>
                <td class="small"><?php echo html_escape(trim((string)($child['profile_description'] ?? '-'))); ?></td>
                <td class="small" style="white-space:nowrap"><?php echo $cSizeStr; ?></td>
                <td class="text-end"><div class="small"><?php echo ui_num((float)($child['opening_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($child['opening_qty_pack'] ?? 0)); ?> <?php echo $cUomPack; ?></div></td>
                <td class="text-end text-success"><div class="small"><?php echo ui_num((float)($child['in_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($child['in_qty_pack'] ?? 0)); ?> <?php echo $cUomPack; ?></div></td>
                <td class="text-end text-danger"><div class="small"><?php echo ui_num((float)($child['out_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($child['out_qty_pack'] ?? 0)); ?> <?php echo $cUomPack; ?></div></td>
                <td class="text-end"><div class="small"><?php echo ui_num((float)($child['waste_component_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($child['waste_component_value'] ?? 0), 0, ',', '.'); ?></div></td>
                <td class="text-end"><div class="small"><?php echo ui_num((float)($child['spoilage_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($child['spoilage_value'] ?? 0), 0, ',', '.'); ?></div></td>
                <td class="text-end"><div class="small"><?php echo ui_num((float)($child['process_loss_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($child['process_loss_value'] ?? 0), 0, ',', '.'); ?></div></td>
                <td class="text-end"><div class="small"><?php echo ui_num((float)($child['variance_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($child['variance_value'] ?? 0), 0, ',', '.'); ?></div></td>
                <td class="text-end"><div class="small"><?php echo ui_num((float)($child['adjustment_plus_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem">Rp <?php echo number_format((float)($child['adjustment_plus_value'] ?? 0), 0, ',', '.'); ?></div></td>
                <td class="text-end fw-semibold"><div class="small"><?php echo ui_num((float)($child['closing_qty_content'] ?? 0)); ?> <span class="text-muted"><?php echo $cUomContent; ?></span></div><div class="text-muted" style="font-size:.7rem"><?php echo ui_num((float)($child['closing_qty_pack'] ?? 0)); ?> <?php echo $cUomPack; ?></div></td>
                <td class="text-end small"><?php echo number_format((float)($child['total_value'] ?? 0), 0, ',', '.'); ?></td>
                <td class="text-end small"><?php echo ui_num((float)($child['avg_cost_per_content'] ?? 0)); ?></td>
                <td class="text-end small"><?php echo ui_num($cAvgCostPack); ?></td>
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
          echo ' &mdash; <span class="text-danger fw-semibold">' . $summaryAlertCount . ' stok habis</span>';
        }
      ?>
    </span>
    <?php if ($totalPages > 1): ?>
    <div class="sdd-pagination">
      <?php
        $winStart = max(1, $currentPage - 2);
        $winEnd   = min($totalPages, $currentPage + 2);
        if ($winEnd - $winStart < 4) {
          if ($winStart === 1) { $winEnd = min($totalPages, $winStart + 4); }
          else { $winStart = max(1, $winEnd - 4); }
        }
        $prevUrl = $baseUrl . '?' . $paginationQs . '&page=' . ($currentPage - 1);
        $nextUrl = $baseUrl . '?' . $paginationQs . '&page=' . ($currentPage + 1);
      ?>
      <a href="<?php echo html_escape($prevUrl); ?>" class="sdd-page-btn<?php echo $currentPage > 1 ? '' : ' is-disabled'; ?>">&#8249;</a>
      <?php if ($winStart > 1): ?>
        <a href="<?php echo html_escape($baseUrl . '?' . $paginationQs . '&page=1'); ?>" class="sdd-page-btn">1</a>
        <?php if ($winStart > 2): ?><span class="text-muted small px-1">…</span><?php endif; ?>
      <?php endif; ?>
      <?php for ($pn = $winStart; $pn <= $winEnd; $pn++): ?>
        <a href="<?php echo html_escape($baseUrl . '?' . $paginationQs . '&page=' . $pn); ?>" class="sdd-page-btn<?php echo $pn === $currentPage ? ' is-active' : ''; ?>"><?php echo $pn; ?></a>
      <?php endfor; ?>
      <?php if ($winEnd < $totalPages): ?>
        <?php if ($winEnd < $totalPages - 1): ?><span class="text-muted small px-1">…</span><?php endif; ?>
        <a href="<?php echo html_escape($baseUrl . '?' . $paginationQs . '&page=' . $totalPages); ?>" class="sdd-page-btn"><?php echo $totalPages; ?></a>
      <?php endif; ?>
      <a href="<?php echo html_escape($nextUrl); ?>" class="sdd-page-btn<?php echo $currentPage < $totalPages ? '' : ' is-disabled'; ?>">&#8250;</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  document.querySelectorAll('.sdd-toggle-arrow').forEach(function(btn){
    btn.addEventListener('click', function(){
      var target = btn.getAttribute('data-target');
      if (!target) { return; }
      var rows = document.querySelectorAll('.' + target);
      if (!rows.length) { return; }
      var willShow = rows[0].style.display === 'none';
      rows.forEach(function(row){ row.style.display = willShow ? '' : 'none'; });
      btn.classList.toggle('is-open', willShow);
    });
  });
})();
</script>
