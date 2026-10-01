<?php
$filters = is_array($filters ?? null) ? $filters : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
$showOutletChip = !array_key_exists('show_outlet_chip', get_defined_vars()) || !empty($show_outlet_chip);

$formatDate = static function ($value): string {
    $time = $value ? strtotime((string)$value) : false;
    return $time ? date('d M Y', $time) : '-';
};

$outletMap = [];
foreach ($outlets as $outlet) {
    $outletId = (int)($outlet['id'] ?? 0);
    if ($outletId > 0) {
        $outletMap[$outletId] = (string)($outlet['outlet_name'] ?? ('Outlet #' . $outletId));
    }
}

$chips = [];
$chips[] = ['label' => 'Periode', 'value' => $formatDate($filters['date_from'] ?? '') . ' s/d ' . $formatDate($filters['date_to'] ?? '')];
if ($showOutletChip) {
    $chips[] = ['label' => 'Outlet', 'value' => ((int)($filters['outlet_id'] ?? 0) > 0) ? ($outletMap[(int)$filters['outlet_id']] ?? ('Outlet #' . (int)$filters['outlet_id'])) : 'Semua Outlet'];
}

if (trim((string)($filters['q'] ?? '')) !== '') {
    $chips[] = ['label' => 'Cari', 'value' => trim((string)$filters['q'])];
}
if (!empty($filters['status']) && strtoupper((string)$filters['status']) !== 'ALL') {
    $chips[] = ['label' => 'Status', 'value' => str_replace('_', ' ', strtoupper((string)$filters['status']))];
}
if (!empty($filters['order_scope']) && strtoupper((string)$filters['order_scope']) !== 'ALL') {
    $chips[] = ['label' => 'Scope', 'value' => strtoupper((string)$filters['order_scope'])];
}
if (!empty($filters['service_type']) && strtoupper((string)$filters['service_type']) !== 'ALL') {
    $chips[] = ['label' => 'Service', 'value' => str_replace('_', ' ', strtoupper((string)$filters['service_type']))];
}
if (!empty($filters['payment_type']) && strtoupper((string)$filters['payment_type']) !== 'ALL') {
    $chips[] = ['label' => 'Tipe Bayar', 'value' => strtoupper((string)$filters['payment_type'])];
}
if (!empty($filters['void_scope']) && strtoupper((string)$filters['void_scope']) !== 'ALL') {
    $chips[] = ['label' => 'Void Scope', 'value' => strtoupper((string)$filters['void_scope'])];
}
$chips[] = ['label' => 'Limit', 'value' => (string)max(1, (int)($filters['limit'] ?? 25))];
?>
<div class="pos-report-chip-row mb-3">
  <?php foreach ($chips as $chip): ?>
    <span class="pos-report-chip">
      <strong><?php echo html_escape((string)($chip['label'] ?? '')); ?>:</strong>
      <?php echo html_escape((string)($chip['value'] ?? '')); ?>
    </span>
  <?php endforeach; ?>
</div>