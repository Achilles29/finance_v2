<?php
$rows        = is_array($rows ?? null) ? $rows : [];
$lineRows    = is_array($line_rows ?? null) ? $line_rows : [];
$components  = is_array($components ?? null) ? $components : [];
$uoms        = is_array($uoms ?? null) ? $uoms : [];
$divisions   = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
$prefill     = is_array($prefill ?? null) ? $prefill : [];
$listFilters = is_array($list_filters ?? null) ? $list_filters : [];
$activeListTab = strtolower(trim((string)($active_list_tab ?? 'nota')));
if (!in_array($activeListTab, ['nota', 'rincian'], true)) {
  $activeListTab = 'nota';
}
$q = trim((string)($q ?? ''));
$prefillDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($prefill['adjustment_date'] ?? '')) ? (string)$prefill['adjustment_date'] : date('Y-m-d');
$prefillLocationType = (string)($prefill['location_type'] ?? '');
$prefillDivisionId = (int)($prefill['division_id'] ?? 0);
$prefillNotes = (string)($prefill['notes'] ?? '');
$sourceOpeningNo = trim((string)($prefill['source_opening_no'] ?? ''));
$adjustmentReasonOptions = function_exists('component_adjustment_reason_options')
  ? component_adjustment_reason_options()
  : [];
$summaryDraft = 0;
$summaryPosted = 0;
$summaryVoid = 0;
foreach ($rows as $summaryRow) {
  $status = strtoupper((string)($summaryRow['status'] ?? 'DRAFT'));
  if ($status === 'POSTED') {
    $summaryPosted++;
  } elseif ($status === 'VOID') {
    $summaryVoid++;
  } else {
    $summaryDraft++;
  }
}
$moneyDraft = 0.0;
$moneyPosted = 0.0;
$moneySpoil = 0.0;
$moneyWaste = 0.0;
$moneyPlus = 0.0;
$moneyMinus = 0.0;
$moneyPostedSpoil = 0.0;
$moneyPostedWaste = 0.0;
$moneyPostedPlus = 0.0;
$moneyPostedMinus = 0.0;
foreach ($lineRows as $moneyRow) {
  $status = strtoupper((string)($moneyRow['status'] ?? 'DRAFT'));
  if ($status === 'VOID') {
    continue;
  }
  $lineTotalValue = round((float)($moneyRow['total_adjustment_value'] ?? 0), 2);
  if ($status === 'POSTED') {
    $moneyPosted += $lineTotalValue;
    $moneyPostedSpoil += round((float)($moneyRow['value_spoil'] ?? 0), 2);
    $moneyPostedWaste += round((float)($moneyRow['value_waste'] ?? 0), 2);
    $moneyPostedPlus  += round((float)($moneyRow['value_plus'] ?? 0), 2);
    $moneyPostedMinus += round((float)($moneyRow['value_minus'] ?? 0), 2);
  } else {
    $moneyDraft += $lineTotalValue;
  }
  $moneySpoil += round((float)($moneyRow['value_spoil'] ?? 0), 2);
  $moneyWaste += round((float)($moneyRow['value_waste'] ?? 0), 2);
  $moneyPlus += round((float)($moneyRow['value_plus'] ?? 0), 2);
  $moneyMinus += round((float)($moneyRow['value_minus'] ?? 0), 2);
}
$formatMoney = static function (float $value): string {
  return number_format($value, 2, ',', '.');
};
$transactionTime = static function (array $row, string $prefix = ''): string {
  $raw = trim((string)($row[$prefix . 'created_at'] ?? ($row[$prefix . 'posted_at'] ?? ($row[$prefix . 'updated_at'] ?? ''))));
  if ($raw === '' || $raw === '0000-00-00 00:00:00') {
    return '';
  }
  $ts = strtotime($raw);
  return $ts ? date('H:i', $ts) : '';
};
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT') {
    return 'Event';
  }
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY') {
    return 'Reguler';
  }
  return $value !== '' ? $value : '-';
};
$locationOptionLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  $labels = [
    'BAR' => 'Bar Reguler',
    'KITCHEN' => 'Kitchen Reguler',
    'BAR_EVENT' => 'Bar Event',
    'KITCHEN_EVENT' => 'Kitchen Event',
    'ROASTERY' => 'Roastery',
    'ROASTERY_EVENT' => 'Roastery Event',
  ];
  return (string)($labels[$value] ?? ($value !== '' ? $value : '-'));
};
$resolveReasonLabel = static function (string $category, ?string $value) use ($adjustmentReasonOptions): string {
  $key = trim((string)$value);
  if ($key === '') {
    return '-';
  }
  return (string)($adjustmentReasonOptions[$category][$key] ?? $key);
};
$reasonSummary = static function (array $row) use ($resolveReasonLabel): string {
  $parts = [];
  if ((float)($row['qty_waste'] ?? 0) > 0) {
    $parts[] = 'Waste: ' . $resolveReasonLabel('WASTE', $row['waste_reason_code'] ?? null);
  }
  if ((float)($row['qty_spoil'] ?? 0) > 0) {
    $parts[] = 'Spoil: ' . $resolveReasonLabel('SPOILAGE', $row['spoil_reason_code'] ?? null);
  }
  if ((float)($row['qty_adjust_pos'] ?? 0) > 0) {
    $parts[] = 'Plus: ' . $resolveReasonLabel('ADJUSTMENT_PLUS', $row['adjustment_plus_reason_code'] ?? null);
  }
  if ((float)($row['qty_adjust_neg'] ?? 0) > 0) {
    $parts[] = 'Minus: ' . $resolveReasonLabel('ADJUSTMENT_MINUS', $row['adjustment_minus_reason_code'] ?? null);
  }
  return !empty($parts) ? implode(' | ', $parts) : '-';
};
$filterDateFrom    = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($listFilters['date_from'] ?? '')) ? (string)$listFilters['date_from'] : date('Y-m-01');
$filterDateTo      = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($listFilters['date_to'] ?? '')) ? (string)$listFilters['date_to'] : date('Y-m-d');
$filterDivisionId  = (int)($listFilters['division_id'] ?? 0);
$filterLocType     = (string)($listFilters['location_type'] ?? '');
$filterPerPage     = max(10, (int)($listFilters['per_page'] ?? 25));
$tabBaseParams = ['date_from' => $filterDateFrom, 'date_to' => $filterDateTo, 'division_id' => $filterDivisionId > 0 ? $filterDivisionId : '', 'location_type' => $filterLocType];
$buildTabUrl = static function (string $tab) use ($tabBaseParams): string {
  $params = $tabBaseParams;
  $params['tab'] = $tab;
  return site_url('production/component-adjustments') . '?' . http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null));
};
$notaTabUrl = $buildTabUrl('nota');
$rincianTabUrl = $buildTabUrl('rincian');
?>

<style>
  .component-adjustment-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
  }
  .component-adjustment-summary {
    background: #f8faf6;
    border: 1px solid #d8e4cc;
    border-radius: 16px;
    padding: 1rem 1.1rem;
  }
  .component-adjustment-summary strong {
    font-size: 1.12rem;
    color: #304125;
  }
  .component-adjustment-chip {
    border: 1px solid rgba(48, 65, 37, .12);
    border-radius: 14px;
    padding: .8rem .9rem;
    background: linear-gradient(180deg, #fff 0%, #faf8f4 100%);
    height: 100%;
  }
  .component-adjustment-table {
    min-width: 1460px;
  }
  .component-adjustment-table .component-adjustment-col-uom,
  .component-adjustment-table .component-adjustment-col-uom .form-select {
    min-width: 110px;
  }
  .component-adjustment-table .component-adjustment-col-available,
  .component-adjustment-table .component-adjustment-col-available .form-control {
    min-width: 120px;
  }
  .component-adjustment-table .component-adjustment-col-qty,
  .component-adjustment-table .component-adjustment-col-qty .form-control {
    min-width: 96px;
  }
  .component-adjustment-table .component-adjustment-col-price,
  .component-adjustment-table .component-adjustment-col-price .form-control {
    min-width: 120px;
  }
  .component-adjustment-table .component-adjustment-col-type,
  .component-adjustment-table .component-adjustment-col-type .form-select {
    min-width: 148px;
  }
  .component-adjustment-table .component-adjustment-col-reason,
  .component-adjustment-table .component-adjustment-col-reason .form-select {
    min-width: 132px;
  }
  .component-adjustment-type-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .2rem .55rem;
    border-radius: 999px;
    background: #fff7f0;
    border: 1px solid #ead6c7;
    font-size: .72rem;
    font-weight: 700;
    color: #7a4f3c;
  }
  .component-adjustment-price-muted {
    background: #f8f5f2;
    color: #958477;
  }
  .component-adjustment-stage {
    border: 1px solid #e6d9ce;
    border-radius: 16px;
    padding: 1rem 1.1rem;
    background: linear-gradient(180deg, #fff 0%, #fff9f4 100%);
  }
  .component-adjustment-list-tabs .nav-link {
    font-weight: 600;
  }
  .component-adjustment-detail-table td {
    vertical-align: top;
  }
  .component-adjustment-lot-note {
    margin-top: .35rem;
    font-size: .72rem;
    line-height: 1.35;
    color: #7b6a60;
  }
  .component-adjustment-lot-note .btn {
    --bs-btn-padding-y: 0;
    --bs-btn-padding-x: 0;
    font-size: .72rem;
    font-weight: 700;
    vertical-align: baseline;
  }
  .component-adjustment-value-cell {
    min-width: 130px;
  }
  .component-adjustment-lot-choice-row.is-active {
    background: #fff8eb;
  }
  .component-adjustment-lot-choice-card {
    border: 1px solid #e7d7ca;
    border-radius: 14px;
    padding: .75rem .85rem;
    background: linear-gradient(180deg, #fff 0%, #fff9f4 100%);
  }
  .component-adjustment-lot-choice-card strong {
    color: #5d3528;
  }
  .component-adjustment-header-card,
  .component-adjustment-line-card {
    border: 1px solid #e6d9ce;
    border-radius: 16px;
    background: linear-gradient(180deg, #fff 0%, #fff9f4 100%);
  }
  .component-adjustment-header-card {
    padding: .85rem .95rem;
    height: 100%;
  }
  .component-adjustment-header-card .label {
    display: block;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
    margin-bottom: .2rem;
  }
  .component-adjustment-header-card strong {
    color: #503125;
  }
  .component-adjustment-line-empty {
    border: 1px dashed #d8c8bb;
    border-radius: 16px;
    padding: 1.1rem 1rem;
    color: #7f675f;
    background: #fffdfb;
  }
  .component-adjustment-line-card {
    padding: .95rem 1rem;
  }
  .component-adjustment-line-card .line-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .8rem;
    flex-wrap: wrap;
  }
  .component-adjustment-line-card .line-title {
    color: #503125;
    font-weight: 700;
  }
  .component-adjustment-line-card .line-sub {
    font-size: .78rem;
    color: #7b6a60;
  }
  .component-adjustment-line-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .55rem;
    margin-top: .8rem;
  }
  .component-adjustment-line-metric {
    border: 1px solid #eadccf;
    border-radius: 12px;
    background: #fffaf6;
    padding: .45rem .55rem;
  }
  .component-adjustment-line-metric .label {
    display: block;
    font-size: .67rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
  }
  .component-adjustment-line-metric strong {
    display: block;
    color: #503125;
  }
  .component-adjustment-line-actions {
    display: inline-flex;
    gap: .45rem;
  }
  .component-adjustment-header-trigger {
    background: linear-gradient(180deg, #fff8e9 0%, #fff0cd 100%);
    border-color: #e4bf6d;
    color: #7a5312;
  }
  .component-adjustment-header-trigger:hover,
  .component-adjustment-header-trigger:focus {
    background: linear-gradient(180deg, #fff4dc 0%, #ffe8b2 100%);
    border-color: #d4aa53;
    color: #6a470f;
  }
  .component-adjustment-add-trigger {
    background: linear-gradient(180deg, #effcf8 0%, #dff6ef 100%);
    border-color: #76b8a3;
    color: #155847;
  }
  .component-adjustment-add-trigger:hover,
  .component-adjustment-add-trigger:focus {
    background: linear-gradient(180deg, #e2f8f0 0%, #cdeee3 100%);
    border-color: #5ca28c;
    color: #11483b;
  }
  .component-adjustment-modal {
    --bs-modal-margin: 1.5rem;
  }
  .component-adjustment-modal .modal-dialog {
    margin-top: 1.5rem;
    margin-bottom: 1rem;
  }
  .component-adjustment-modal .modal-content {
    box-shadow: 0 1.25rem 2.5rem rgba(56, 29, 16, .18);
  }
  .component-adjustment-save-spinner {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
  }
  .component-adjustment-plus-destination {
    border: 1px solid #d7e7da;
    border-radius: 14px;
    padding: .75rem .85rem;
    background: linear-gradient(180deg, #fbfffc 0%, #f0faf3 100%);
  }
  .component-adjustment-plus-destination.is-warning {
    border-color: #e8d39e;
    background: linear-gradient(180deg, #fffdf6 0%, #fff6dc 100%);
  }
  @media (max-width: 991.98px) {
    .component-adjustment-line-metrics {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .component-adjustment-modal .modal-dialog {
      margin-top: .85rem;
    }
  }
</style>

<div class="mb-3">
  <div class="component-adjustment-hero">
    <div>
      <h4 class="mb-1"><i class="ri ri-scales-3-line page-title-icon"></i>Adjustment Base/Prepare</h4>
      <small class="text-muted">Pecah per component untuk waste, spoil, plus, dan minus. Reason operasional tetap tercatat per bucket, tetapi input dijaga ringkas supaya cepat dipakai di lapangan.</small>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'adjustment']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-adjustments'),
  'component_type_filters'  => ['q' => $q],
  'component_type_active'   => '',
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'location_type' => $prefillLocationType,
  ], static fn($v) => $v !== ''),
]); ?>

<div class="alert alert-light border mb-3 py-2 small">
  <strong>Gunakan Adjustment bila perubahan component sudah diketahui.</strong>
  Contoh: waste, spoil, hasil ditemukan, atau koreksi plus/minus tertentu. Isi <strong>jumlah perubahan</strong>.
  Jika menghitung stok fisik akhir semua component, gunakan <a href="<?= site_url('production/component-daily-recon') ?>">Daily Recon Component</a> agar stok sistem dan lot disamakan bersama-sama.
</div>

<div id="component-adjustment-alert" class="mb-3"></div>

<?php
$moneyPostedNet = $moneyPostedSpoil + $moneyPostedWaste + $moneyPostedMinus - $moneyPostedPlus;
?>
<div class="row g-2 mb-3">
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="card card-body py-2 px-3 text-center h-100">
      <div class="small text-muted mb-1">Posted</div>
      <div class="fw-bold text-success"><?php echo number_format($summaryPosted, 0, ',', '.'); ?> dok</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="card card-body py-2 px-3 text-center h-100">
      <div class="small text-muted mb-1">Nilai Posted</div>
      <div class="fw-bold text-success" style="font-size:.82rem"><?php echo $formatMoney($moneyPosted); ?></div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="card card-body py-2 px-3 text-center h-100">
      <div class="small text-muted mb-1">Net Impact</div>
      <div class="fw-bold <?php echo $moneyPostedNet >= 0 ? 'text-danger' : 'text-success'; ?>" style="font-size:.82rem"><?php echo $formatMoney($moneyPostedNet); ?></div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="card card-body py-2 px-3 text-center h-100">
      <div class="small text-muted mb-1">Spoil + Waste</div>
      <div class="fw-bold text-danger" style="font-size:.82rem"><?php echo $formatMoney($moneyPostedSpoil + $moneyPostedWaste); ?></div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="card card-body py-2 px-3 text-center h-100">
      <div class="small text-muted mb-1">Adj Plus</div>
      <div class="fw-bold text-success" style="font-size:.82rem"><?php echo $formatMoney($moneyPostedPlus); ?></div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="card card-body py-2 px-3 text-center h-100">
      <div class="small text-muted mb-1">Adj Minus</div>
      <div class="fw-bold text-danger" style="font-size:.82rem"><?php echo $formatMoney($moneyPostedMinus); ?></div>
    </div>
  </div>
</div>

<?php if ($sourceOpeningNo !== ''): ?>
  <div class="alert alert-warning mb-3">
    Koreksi stok ini diprefill dari opening <?php echo html_escape($sourceOpeningNo); ?> yang sudah diposting. Tambahkan selisih yang kurang lewat adjustment, bukan opening baru.
  </div>
<?php endif; ?>

<div class="mb-3 d-flex gap-2">
  <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#componentAdjustmentFormModal">
    <i class="ri ri-add-line me-1"></i>Tambah Adjustment
  </button>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-adjustments'); ?>" id="adj-filter-form">
      <input type="hidden" name="tab" value="<?php echo html_escape($activeListTab); ?>">
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label form-label-sm mb-1">Dari</label>
          <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo html_escape($filterDateFrom); ?>">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label form-label-sm mb-1">Sampai</label>
          <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo html_escape($filterDateTo); ?>">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label form-label-sm mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id">
            <option value="">Semua Divisi</option>
            <?php foreach ($divisions as $div): ?>
              <option value="<?php echo (int)$div['id']; ?>" <?php echo $filterDivisionId === (int)$div['id'] ? 'selected' : ''; ?>><?php echo html_escape(!empty($div['code']) ? $div['code'] . ' - ' . $div['name'] : $div['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label form-label-sm mb-1">Lokasi</label>
          <select class="form-select form-select-sm" name="location_type">
            <option value="">Semua Lokasi</option>
            <option value="REGULER" <?php echo $filterLocType === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
            <option value="EVENT" <?php echo $filterLocType === 'EVENT' ? 'selected' : ''; ?>>Event</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label form-label-sm mb-1">Per Halaman</label>
          <select class="form-select form-select-sm" name="per_page" id="adj-per-page">
            <?php foreach ([10, 25, 50, 100] as $pp): ?>
              <option value="<?php echo $pp; ?>" <?php echo $filterPerPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-primary btn-sm flex-fill">Filter</button>
          <a href="<?php echo site_url('production/component-adjustments'); ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h5 class="mb-1">Riwayat Adjustment</h5>
        <small class="text-muted">Per Nota: ringkasan dokumen. Per Rincian: line operasional &amp; reason.</small>
      </div>
      <input type="text" class="form-control form-control-sm" id="adj-search" style="width:240px" placeholder="Cari no, komponen, divisi, catatan…" value="<?php echo html_escape($q); ?>">
    </div>

    <ul class="nav nav-tabs component-adjustment-list-tabs mb-3" role="tablist">
      <li class="nav-item" role="presentation"><a class="nav-link <?php echo $activeListTab === 'nota' ? 'active' : ''; ?>" href="<?php echo html_escape($notaTabUrl); ?>">Per Nota</a></li>
      <li class="nav-item" role="presentation"><a class="nav-link <?php echo $activeListTab === 'rincian' ? 'active' : ''; ?>" href="<?php echo html_escape($rincianTabUrl); ?>">Per Rincian</a></li>
    </ul>

    <div class="tab-content p-0 border-0">
      <div class="tab-pane fade <?php echo $activeListTab === 'nota' ? 'show active' : ''; ?>" role="tabpanel">
        <div style="overflow:auto;max-height:70vh">
          <table class="table table-bordered table-hover table-sm align-middle mb-0" style="table-layout:fixed;min-width:800px">
            <thead class="table-dark" style="position:sticky;top:0;z-index:2">
              <tr>
                <th style="width:160px;white-space:nowrap">No</th>
                <th style="width:92px;white-space:nowrap">Tanggal</th>
                <th style="width:80px;white-space:nowrap">Lokasi</th>
                <th style="width:120px;white-space:nowrap">Divisi</th>
                <th style="width:180px;white-space:nowrap">Catatan</th>
                <th style="width:82px;white-space:nowrap">Status</th>
                <th style="width:110px;white-space:nowrap">Aksi</th>
              </tr>
            </thead>
            <tbody id="adj-nota-tbody">
              <?php if (empty($rows)): ?>
                <tr class="adj-nota-row"><td colspan="7" class="text-center text-muted py-4">Belum ada dokumen adjustment.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php $adjNo = (string)($row['adjustment_no'] ?? ''); ?>
                  <tr class="adj-nota-row" data-search="<?php echo html_escape(strtolower(implode(' ', [
                    $adjNo,
                    $row['adjustment_date'] ?? '',
                    $locationGroupLabel((string)($row['location_type'] ?? '')),
                    $row['division_name'] ?? '',
                    $row['notes'] ?? '',
                    $row['status'] ?? '',
                  ]))); ?>">
                    <td style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo html_escape($adjNo); ?></td>
                    <td style="font-size:.8rem;white-space:nowrap;overflow:hidden">
                      <div><?php echo html_escape((string)($row['adjustment_date'] ?? '')); ?></div>
                      <?php $notaTime = $transactionTime((array)$row); ?>
                      <?php if ($notaTime !== ''): ?>
                        <div class="text-muted" style="font-size:.7rem;line-height:1.15">Jam <?php echo html_escape($notaTime); ?></div>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:.8rem;white-space:nowrap;overflow:hidden"><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></td>
                    <td style="font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                    <td style="font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['notes'] ?? '-')); ?>"><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                    <td><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></td>
                    <td class="component-action-cell">
                      <div class="component-action-stack">
                        <button type="button" class="btn btn-outline-info action-icon-btn btn-detail-nota" data-no="<?php echo html_escape($adjNo); ?>" title="Detail Rincian" aria-label="Detail"><i class="ri ri-eye-line"></i></button>
                        <?php if (strtoupper((string)($row['status'] ?? '')) === 'DRAFT'): ?>
                          <button type="button" class="btn btn-outline-success action-icon-btn component-action-btn btn-post" data-id="<?php echo (int)$row['id']; ?>" title="Post" aria-label="Post"><i class="ri ri-checkbox-circle-line"></i></button>
                          <button type="button" class="btn btn-outline-danger action-icon-btn component-action-btn btn-del" data-id="<?php echo (int)$row['id']; ?>" title="Delete" aria-label="Delete"><i class="ri ri-delete-bin-line"></i></button>
                        <?php elseif (strtoupper((string)($row['status'] ?? '')) === 'POSTED'): ?>
                          <button type="button" class="btn btn-outline-warning action-icon-btn component-action-btn btn-void" data-id="<?php echo (int)$row['id']; ?>" title="Void" aria-label="Void"><i class="ri ri-close-circle-line"></i></button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-2 gap-2">
          <div class="small text-muted" id="adj-nota-info"></div>
          <div id="adj-nota-pager" class="d-flex gap-1 flex-wrap"></div>
        </div>
      </div>
      <div class="tab-pane fade <?php echo $activeListTab === 'rincian' ? 'show active' : ''; ?>" role="tabpanel">
        <div style="overflow:auto;max-height:70vh">
          <table class="table table-bordered table-hover table-sm align-middle mb-0" style="table-layout:fixed;min-width:1150px">
            <thead class="table-dark" style="position:sticky;top:0;z-index:2">
              <tr>
                <th style="width:168px;white-space:nowrap">No</th>
                <th style="width:100px;white-space:nowrap">Tanggal</th>
                <th style="width:225px;white-space:nowrap">Komponen / Produk</th>
                <th style="width:105px;white-space:nowrap">Divisi</th>
                <th style="width:90px;white-space:nowrap" class="text-end">QTY</th>
                <th style="width:105px;white-space:nowrap" class="text-end">NILAI (Rp)</th>
                <th style="width:145px;white-space:nowrap">Lot</th>
                <th style="width:190px;white-space:nowrap">Jenis &amp; Alasan</th>
                <th style="width:78px;white-space:nowrap">Status</th>
              </tr>
            </thead>
            <tbody id="adj-rincian-tbody">
              <?php if (empty($lineRows)): ?>
                <tr class="adj-rincian-row"><td colspan="9" class="text-center text-muted py-4">Belum ada rincian adjustment.</td></tr>
              <?php else: ?>
                <?php foreach ($lineRows as $lineRow): ?>
                  <?php
                    $qSpoil  = (float)($lineRow['qty_spoil'] ?? 0);
                    $qWaste  = (float)($lineRow['qty_waste'] ?? 0);
                    $qPlus   = (float)($lineRow['qty_adjust_pos'] ?? 0);
                    $qMinus  = (float)($lineRow['qty_adjust_neg'] ?? 0);
                    $uomCode = strtolower((string)($lineRow['uom_code'] ?? ''));
                    $uomSuffix = $uomCode ? ' ' . $uomCode : '';
                    $qtyParts = [];
                    if ($qSpoil > 0) $qtyParts[] = number_format($qSpoil, 2, ',', '.') . $uomSuffix;
                    if ($qWaste > 0) $qtyParts[] = number_format($qWaste, 2, ',', '.') . $uomSuffix;
                    if ($qPlus > 0)  $qtyParts[] = number_format($qPlus,  2, ',', '.') . $uomSuffix;
                    if ($qMinus > 0) $qtyParts[] = number_format($qMinus, 2, ',', '.') . $uomSuffix;
                    $jenisTeks = $reasonSummary((array)$lineRow);
                    $lotPreview = trim((string)($lineRow['lot_issue_preview'] ?? ''));
                  ?>
                  <tr class="adj-rincian-row" data-search="<?php echo html_escape(strtolower(implode(' ', [
                    $lineRow['adjustment_no'] ?? '',
                    $lineRow['adjustment_date'] ?? '',
                    $lineRow['component_name'] ?? '',
                    $lineRow['component_type'] ?? '',
                    $lineRow['division_name'] ?? '',
                    $lineRow['note'] ?? '',
                    $lineRow['status'] ?? '',
                    $jenisTeks,
                  ]))); ?>">
                    <td style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($lineRow['adjustment_no'] ?? '')); ?></td>
                    <td style="font-size:.78rem;white-space:nowrap;overflow:hidden">
                      <div><?php echo html_escape((string)($lineRow['adjustment_date'] ?? '')); ?></div>
                      <?php $lineTime = $transactionTime((array)$lineRow, 'adjustment_'); ?>
                      <?php if ($lineTime !== ''): ?>
                        <div class="text-muted" style="font-size:.68rem;line-height:1.15">Jam <?php echo html_escape($lineTime); ?></div>
                      <?php endif; ?>
                    </td>
                    <td style="overflow:hidden">
                      <div class="fw-semibold" style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($lineRow['component_name'] ?? '-')); ?>"><?php echo html_escape((string)($lineRow['component_name'] ?? '-')); ?></div>
                      <?php $relatedProducts = trim((string)($lineRow['related_product_names'] ?? '')); ?>
                      <?php if ($relatedProducts !== ''): ?>
                        <?php $relatedProductParts = array_values(array_filter(explode(' | ', $relatedProducts))); $relatedProductPreview = implode(', ', array_slice($relatedProductParts, 0, 3)); ?>
                        <div class="text-primary" style="font-size:.66rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape($relatedProducts); ?>">Produk: <?php echo html_escape($relatedProductPreview); ?><?php echo count($relatedProductParts) > 3 ? ' +' . (count($relatedProductParts) - 3) . ' lainnya' : ''; ?></div>
                      <?php endif; ?>
                      <div class="text-muted" style="font-size:.68rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($lineRow['component_type'] ?? '-')); ?><?php if ($uomCode): ?> · <?php echo html_escape($uomCode); ?><?php endif; ?></div>
                    </td>
                    <td style="overflow:hidden">
                      <div class="text-muted" style="font-size:.68rem;white-space:nowrap;overflow:hidden"><?php echo html_escape($locationGroupLabel((string)($lineRow['location_type'] ?? ''))); ?></div>
                      <div style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($lineRow['division_name'] ?? '-')); ?>"><?php echo html_escape((string)($lineRow['division_name'] ?? '-')); ?></div>
                    </td>
                    <td class="text-end" style="overflow:hidden">
                      <?php foreach ($qtyParts as $qp): ?>
                        <div style="font-size:.78rem;white-space:nowrap"><?php echo html_escape($qp); ?></div>
                      <?php endforeach; ?>
                      <?php if (empty($qtyParts)): ?><div class="text-muted" style="font-size:.78rem">-</div><?php endif; ?>
                    </td>
                    <td class="text-end" style="overflow:hidden">
                      <div style="font-size:.78rem;white-space:nowrap"><?php echo number_format((float)($lineRow['total_adjustment_value'] ?? 0), 2, ',', '.'); ?></div>
                    </td>
                    <td style="overflow:hidden">
                      <?php if ($lotPreview !== '' && $lotPreview !== '-'): ?>
                        <div style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#155724" title="<?php echo html_escape($lotPreview); ?>"><?php echo html_escape($lotPreview); ?></div>
                      <?php else: ?>
                        <div class="text-muted" style="font-size:.72rem"><?php echo strtoupper((string)($lineRow['status'] ?? '')) === 'DRAFT' ? 'Belum dipost' : '-'; ?></div>
                      <?php endif; ?>
                    </td>
                    <td style="overflow:hidden">
                      <div style="font-size:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape($jenisTeks); ?>"><?php echo html_escape($jenisTeks); ?></div>
                      <?php if (!empty($lineRow['note'])): ?>
                        <div class="text-muted" style="font-size:.68rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)$lineRow['note']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?php echo ui_status_badge((string)($lineRow['status'] ?? 'DRAFT')); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-2 gap-2">
          <div class="small text-muted" id="adj-rincian-info"></div>
          <div id="adj-rincian-pager" class="d-flex gap-1 flex-wrap"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade component-adjustment-modal" id="componentAdjustmentFormModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Tambah Adjustment</h5>
          <div class="small text-muted">Isi header lalu tambahkan baris per komponen.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="component-adjustment-stage mb-3">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div class="small text-muted">Header &amp; baris adjustment diisi masing-masing lewat modal terpisah.</div>
            <div class="d-flex gap-2 flex-wrap">
              <button type="button" class="btn btn-sm component-adjustment-header-trigger" id="btn-edit-adjustment-header"><i class="ri ri-settings-3-line me-1"></i>Header Adjustment</button>
              <button type="button" class="btn btn-sm component-adjustment-add-trigger" id="btn-add-adjustment-line"><i class="ri ri-add-line me-1"></i>Tambah Baris</button>
            </div>
          </div>
        </div>

        <form id="frmAdjustment" autocomplete="off">
          <input type="hidden" name="adjustment_date" value="<?php echo html_escape($prefillDate); ?>">
          <input type="hidden" name="location_type" value="<?php echo html_escape($prefillLocationType); ?>">
          <input type="hidden" name="division_id" value="<?php echo $prefillDivisionId > 0 ? (int)$prefillDivisionId : ''; ?>">
          <input type="hidden" name="notes" value="<?php echo html_escape($prefillNotes); ?>">

          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <div class="component-adjustment-header-card">
                <span class="label">Tanggal</span>
                <strong id="adjustment-header-date">-</strong>
              </div>
            </div>
            <div class="col-md-3">
              <div class="component-adjustment-header-card">
                <span class="label">Lokasi Tujuan</span>
                <strong id="adjustment-header-location">-</strong>
              </div>
            </div>
            <div class="col-md-3">
              <div class="component-adjustment-header-card">
                <span class="label">Divisi</span>
                <strong id="adjustment-header-division">-</strong>
              </div>
            </div>
            <div class="col-md-3">
              <div class="component-adjustment-header-card">
                <span class="label">Catatan</span>
                <strong id="adjustment-header-notes">-</strong>
              </div>
            </div>
          </div>

          <div id="adjustment-line-empty" class="component-adjustment-line-empty mb-2">
            Belum ada baris adjustment. Gunakan tombol <strong>Tambah Baris</strong> untuk input lewat modal.
          </div>
          <div id="adjustment-line-list" class="d-grid gap-2"></div>

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
            <div class="component-adjustment-summary d-flex flex-wrap gap-4">
              <div><div class="small text-muted">Baris</div><strong id="adj-line-count">0</strong></div>
              <div><div class="small text-muted">Total Spoil</div><strong id="adj-total-spoil">0,00</strong></div>
              <div><div class="small text-muted">Total Waste</div><strong id="adj-total-waste">0,00</strong></div>
              <div><div class="small text-muted">Total Plus</div><strong id="adj-total-plus">0,00</strong></div>
              <div><div class="small text-muted">Total Minus</div><strong id="adj-total-minus">0,00</strong></div>
              <div><div class="small text-muted">Est. Nilai</div><strong id="adj-total-value">0,00</strong></div>
            </div>
            <button type="submit" class="btn btn-primary">Simpan DRAFT</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade component-adjustment-modal" id="componentAdjustmentHeaderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title mb-0">Header Adjustment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Tanggal Adjustment</label>
            <input type="date" class="form-control" id="modal-adjustment-date" value="<?php echo html_escape($prefillDate); ?>" max="<?php echo date('Y-m-d'); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Divisi</label>
            <select class="form-select" id="modal-adjustment-division">
              <option value="">Pilih divisi...</option>
              <?php foreach ($divisions as $division): ?>
                <option value="<?php echo (int)$division['id']; ?>" <?php echo $prefillDivisionId === (int)$division['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)($division['code'] ?? '')); ?><?php echo !empty($division['code']) ? ' - ' : ''; ?><?php echo html_escape((string)($division['name'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Pilih divisi dulu, lalu sistem akan menampilkan pilihan lokasi Reguler atau Event yang sesuai.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Lokasi Tujuan Stock</label>
            <select class="form-select" id="modal-adjustment-location"></select>
            <div class="form-text">Dipakai sebagai tujuan stok untuk adjustment plus, dan sebagai konteks stok untuk snapshot adjustment lainnya.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Catatan Header</label>
            <input type="text" class="form-control" id="modal-adjustment-notes" value="<?php echo html_escape($prefillNotes); ?>" placeholder="Contoh: penyesuaian akhir shift">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="btn-save-adjustment-header">Simpan Header</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade component-adjustment-modal" id="componentAdjustmentLineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="component-adjustment-line-modal-title">Baris Adjustment</h5>
          <div class="small text-muted">Semua input baris adjustment diisi dari modal ini.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Component</label>
            <input type="text" class="form-control" id="modal-line-component" placeholder="Ketik nama component...">
            <div class="component-adjustment-lot-note mt-2" id="modal-line-lot-note">Belum ada lot aktif</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">UOM</label>
            <select class="form-select" id="modal-line-uom"></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Available</label>
            <input type="number" class="form-control bg-light text-end" id="modal-line-available" readonly tabindex="-1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Lot Adjustment</label>
            <div class="d-grid gap-2">
              <button type="button" class="btn btn-outline-secondary" id="btn-open-line-lot-picker">Pilih Lot</button>
              <div class="small text-muted" id="modal-line-selected-lot">Belum ada lot dipilih.</div>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Jenis Adjustment</label>
            <select class="form-select" id="modal-line-type"></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Jumlah</label>
            <input type="number" min="0" step="0.01" class="form-control text-end" id="modal-line-qty">
          </div>
          <div class="col-md-4">
            <label class="form-label">Harga Plus</label>
            <input type="number" min="0" step="0.0001" class="form-control text-end" id="modal-line-unit-cost" placeholder="Harga/unit">
          </div>
          <div class="col-12 d-none" id="modal-line-plus-destination-wrap">
            <div class="component-adjustment-plus-destination" id="modal-line-plus-destination-card">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                  <div class="small text-muted text-uppercase">Tujuan Stok Plus</div>
                  <div class="fw-semibold" id="modal-line-plus-destination">Belum dipilih</div>
                  <div class="small text-muted mt-1" id="modal-line-plus-destination-help">Plus akan masuk ke lokasi yang dipilih di Header Adjustment.</div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-edit-plus-destination">Ubah Header</button>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Reason</label>
            <select class="form-select" id="modal-line-reason"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Est. Nilai</label>
            <div class="component-adjustment-header-card h-100 d-flex flex-column justify-content-center">
              <strong id="modal-line-estimated-value">0,00</strong>
              <span class="small text-muted mt-1" id="modal-line-estimated-meta">@ 0,00</span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" rows="2" id="modal-line-note" placeholder="Catatan operasional"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="btn-save-adjustment-line">Simpan Baris</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade component-adjustment-modal" id="componentLotPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Pilih Lot Adjustment</h5>
          <div class="small text-muted" id="component-lot-picker-meta">Pilih lot yang paling sesuai untuk adjustment ini.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="component-lot-picker-body" class="d-grid gap-2"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade component-adjustment-modal" id="componentAdjustmentStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi Posting Adjustment</h5>
          <div class="small text-muted">Masukkan password akun Anda untuk melanjutkan posting ke stok dan ledger component.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="component_adjustment_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="component_adjustment_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada dokumen adjustment.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-component-adjustment-step-up-post">Verifikasi &amp; Post</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade component-adjustment-modal" id="componentAdjustmentVoidStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi VOID Adjustment</h5>
          <div class="small text-muted">Masukkan password akun Anda untuk membalik lot, stok, nilai, dan defisit dari dokumen ini.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="component_adjustment_void_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="component_adjustment_void_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada dokumen adjustment.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="btn-component-adjustment-void-step-up-post">Verifikasi &amp; VOID</button>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const uomOptions = <?php echo json_encode(array_values(array_map(static function ($uom) {
      return [
          'id' => (int)($uom['id'] ?? 0),
          'label' => trim((string)($uom['code'] ?? '') !== '' ? (string)($uom['code'] ?? '') : (string)($uom['name'] ?? '')),
      ];
  }, $uoms)), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    const divisionOptions = <?php echo json_encode(array_values(array_map(static function ($division) {
      return [
        'id' => (int)($division['id'] ?? 0),
        'code' => (string)($division['code'] ?? ''),
        'name' => (string)($division['name'] ?? ''),
      ];
    }, $divisions)), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const adjustmentReasonOptions = <?php echo json_encode($adjustmentReasonOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const saveUrl = '<?php echo site_url('production/component-adjustments/save'); ?>';
  const componentAdjustmentStepUpUrl = '<?php echo site_url('production/component-adjustments/step-up/verify'); ?>';
  const componentAdjustmentVoidStepUpUrl = '<?php echo site_url('production/component-adjustments/void-step-up/verify'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-adjustments/post'); ?>';
  const componentAdjustmentCsrfToken = <?php echo json_encode((string)($component_adjustment_csrf_token ?? ''), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const voidBaseUrl = '<?php echo site_url('production/component-adjustments/void'); ?>';
  const deleteBaseUrl = '<?php echo site_url('production/component-adjustments/delete'); ?>';
  const stockSnapshotUrl = '<?php echo site_url('production/component-stock-snapshot'); ?>';
  const adjustmentTypeOptions = [
    { value: 'WASTE', label: 'Waste', icon: 'ri-delete-back-2-line' },
    { value: 'SPOILAGE', label: 'Spoil', icon: 'ri-error-warning-line' },
    { value: 'ADJUSTMENT_PLUS', label: 'Plus', icon: 'ri-add-circle-line' },
    { value: 'ADJUSTMENT_MINUS', label: 'Minus', icon: 'ri-subtract-line' }
  ];

  const alertHost = document.getElementById('component-adjustment-alert');
  const lineList = document.getElementById('adjustment-line-list');
  const lineEmpty = document.getElementById('adjustment-line-empty');
  const form = document.getElementById('frmAdjustment');
  const btnEditHeader = document.getElementById('btn-edit-adjustment-header');
  const headerDatePreview = document.getElementById('adjustment-header-date');
  const headerLocationPreview = document.getElementById('adjustment-header-location');
  const headerDivisionPreview = document.getElementById('adjustment-header-division');
  const headerNotesPreview = document.getElementById('adjustment-header-notes');
  const headerModalEl = document.getElementById('componentAdjustmentHeaderModal');
  const headerDateInput = document.getElementById('modal-adjustment-date');
  const headerLocationInput = document.getElementById('modal-adjustment-location');
  const headerDivisionInput = document.getElementById('modal-adjustment-division');
  const headerNotesInput = document.getElementById('modal-adjustment-notes');
  const btnSaveHeader = document.getElementById('btn-save-adjustment-header');
  const lineModalEl = document.getElementById('componentAdjustmentLineModal');
  const lineModalTitle = document.getElementById('component-adjustment-line-modal-title');
  const lineComponentInput = document.getElementById('modal-line-component');
  const lineUomInput = document.getElementById('modal-line-uom');
  const lineAvailableInput = document.getElementById('modal-line-available');
  const lineLotNote = document.getElementById('modal-line-lot-note');
  const lineSelectedLot = document.getElementById('modal-line-selected-lot');
  const btnOpenLineLotPicker = document.getElementById('btn-open-line-lot-picker');
  const lineTypeInput = document.getElementById('modal-line-type');
  const lineQtyInput = document.getElementById('modal-line-qty');
  const lineUnitCostInput = document.getElementById('modal-line-unit-cost');
  const plusDestinationWrap = document.getElementById('modal-line-plus-destination-wrap');
  const plusDestinationCard = document.getElementById('modal-line-plus-destination-card');
  const plusDestinationText = document.getElementById('modal-line-plus-destination');
  const plusDestinationHelp = document.getElementById('modal-line-plus-destination-help');
  const btnEditPlusDestination = document.getElementById('btn-edit-plus-destination');
  const lineReasonInput = document.getElementById('modal-line-reason');
  const lineEstimatedValue = document.getElementById('modal-line-estimated-value');
  const lineEstimatedMeta = document.getElementById('modal-line-estimated-meta');
  const lineNoteInput = document.getElementById('modal-line-note');
  const btnSaveLine = document.getElementById('btn-save-adjustment-line');
  const adjustmentStepUpModalEl = document.getElementById('componentAdjustmentStepUpModal');
  const adjustmentStepUpPassword = document.getElementById('component_adjustment_step_up_password');
  const btnAdjustmentStepUpPost = document.getElementById('btn-component-adjustment-step-up-post');
  const adjustmentVoidStepUpModalEl = document.getElementById('componentAdjustmentVoidStepUpModal');
  const adjustmentVoidStepUpPassword = document.getElementById('component_adjustment_void_step_up_password');
  const btnAdjustmentVoidStepUpPost = document.getElementById('btn-component-adjustment-void-step-up-post');
  const lotPickerModalEl = document.getElementById('componentLotPickerModal');
  const lotPickerBody = document.getElementById('component-lot-picker-body');
  const lotPickerMeta = document.getElementById('component-lot-picker-meta');
  let headerModal = null;
  let lineModal = null;
  let lotPickerModal = null;
  let adjustmentStepUpModal = null;
  let adjustmentVoidStepUpModal = null;
  let lotPickerTarget = null;
  let pendingAdjustmentPostId = 0;
  let pendingAdjustmentPostButton = null;
  let adjustmentStepUpSubmitting = false;
  let pendingAdjustmentVoidId = 0;
  let pendingAdjustmentVoidButton = null;
  let adjustmentVoidStepUpSubmitting = false;
  let lines = [];
  let lineDraft = null;
  let editingLineIndex = -1;
  let pendingLineOpenIndex = null;

  function headerContext() {
    const formData = form ? new FormData(form) : new FormData();
    return {
      location_type: String(formData.get('location_type') || ''),
      division_id: String(formData.get('division_id') || ''),
      adjustment_date: String(formData.get('adjustment_date') || ''),
      notes: String(formData.get('notes') || '')
    };
  }

  function headerField(name) {
    return form ? form.querySelector('[name="' + name + '"]') : null;
  }

  function findDivisionOption(divisionId) {
    return divisionOptions.find((option) => String(option.id) === String(divisionId || '')) || null;
  }

  function divisionLocationBase(divisionId) {
    const division = findDivisionOption(divisionId);
    const raw = [division?.code || '', division?.name || ''].join(' ').toUpperCase();
    if (raw.includes('KITCHEN')) {
      return 'KITCHEN';
    }
    if (raw.includes('ROASTERY')) {
      return 'ROASTERY';
    }
    if (raw.includes('BAR')) {
      return 'BAR';
    }
    return '';
  }

  function locationChoicesForDivision(divisionId) {
    const base = divisionLocationBase(divisionId);
    if (base === 'KITCHEN') {
      return [
        { value: 'KITCHEN', label: 'Reguler' },
        { value: 'KITCHEN_EVENT', label: 'Event' }
      ];
    }
    if (base === 'BAR') {
      return [
        { value: 'BAR', label: 'Reguler' },
        { value: 'BAR_EVENT', label: 'Event' }
      ];
    }
    if (base === 'ROASTERY') {
      return [
        { value: 'ROASTERY', label: 'Reguler' },
        { value: 'ROASTERY_EVENT', label: 'Event' }
      ];
    }
    return [];
  }

  function renderHeaderLocationOptions(selectedValue) {
    if (!headerLocationInput) {
      return;
    }
    const divisionId = String(headerDivisionInput?.value || '');
    const choices = locationChoicesForDivision(divisionId);
    const options = [];
    if (!divisionId) {
      options.push('<option value="">Pilih divisi dulu...</option>');
    } else if (!choices.length) {
      options.push('<option value="">Divisi ini belum punya mapping lokasi</option>');
    } else {
      options.push('<option value="">Pilih lokasi...</option>');
      choices.forEach((choice) => {
        options.push('<option value="' + choice.value + '">' + escapeHtml(choice.label) + '</option>');
      });
    }
    headerLocationInput.innerHTML = options.join('');
    headerLocationInput.disabled = !choices.length;
    if (choices.some((choice) => choice.value === String(selectedValue || ''))) {
      headerLocationInput.value = String(selectedValue || '');
    } else {
      headerLocationInput.value = '';
    }
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    if (alertHost) {
      alertHost.innerHTML = '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>';
      alertHost.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      throw new Error('Respons server bukan JSON valid.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Permintaan gagal diproses.');
    }
    return json;
  }

  async function postComponentAdjustmentJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Production-Component-Adjustment-Csrf': componentAdjustmentCsrfToken
      },
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      throw new Error('Respons server bukan JSON valid.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Permintaan gagal diproses.');
    }
    return json;
  }

  async function loadJson(url) {
    const response = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      throw new Error('Respons server bukan JSON valid.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Permintaan gagal diproses.');
    }
    return json;
  }

  function uiConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      return window.FinanceUI.confirm(message, options || {});
    }
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', { title: 'UI Belum Siap' })
        .then(function () { return false; });
    }
    return Promise.resolve(false);
  }

  function blankLine() {
    return {
      component_id: '',
      component_label: '',
      uom_id: '',
      selected_lot_id: '',
      selected_lot_profile: '',
      available_qty: '',
      stock_unit_cost: '',
      lot_preview: '',
      lot_count: 0,
      lot_rows: [],
      adjustment_type: 'WASTE',
      qty: '',
      reason_code: 'other',
      unit_cost: '',
      note: ''
    };
  }

  function cloneLine(line) {
    return {
      ...blankLine(),
      ...(line || {}),
      lot_rows: Array.isArray(line?.lot_rows) ? line.lot_rows.map((lotRow) => ({...lotRow})) : []
    };
  }

  function componentPickerLabel(row) {
    return String(row.name || row.code || '');
  }

  function componentPickerSubLabel(row) {
    const lotLabel = Number(row.lot_count || 0) > 0
      ? String(row.lot_count) + ' lot aktif'
      : 'Belum ada lot aktif';
    return [row.entity_type || '', row.uom_code || '', row.category_name || '', lotLabel].filter(Boolean).join(' | ');
  }

  function uomSelectOptions(selectedValue) {
    const options = ['<option value="">Pilih UOM...</option>'];
    uomOptions.forEach((uom) => {
      options.push('<option value="' + uom.id + '"' + (String(selectedValue) === String(uom.id) ? ' selected' : '') + '>' + escapeHtml(uom.label) + '</option>');
    });
    return options.join('');
  }

  function reasonSelectOptions(category, selectedValue) {
    const options = adjustmentReasonOptions?.[category] || {};
    return Object.keys(options).map((key) => {
      return '<option value="' + escapeHtml(key) + '"' + (String(selectedValue || 'other') === key ? ' selected' : '') + '>' + escapeHtml(options[key]) + '</option>';
    }).join('');
  }

  function adjustmentTypeMeta(type) {
    return adjustmentTypeOptions.find((option) => option.value === String(type || '').toUpperCase()) || adjustmentTypeOptions[0];
  }

  function typeSelectOptions(selectedValue) {
    return adjustmentTypeOptions.map((option) => {
      return '<option value="' + option.value + '"' + (String(selectedValue || 'WASTE').toUpperCase() === option.value ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
    }).join('');
  }

  function renderSummary() {
    const validLines = lines.filter((line) => Number(line.component_id) > 0 && Number(line.uom_id) > 0);
    const totalOfType = (type) => validLines.reduce((sum, line) => {
      return sum + (String(line.adjustment_type || '').toUpperCase() === type ? (parseFloat(line.qty) || 0) : 0);
    }, 0);
    const totalValue = validLines.reduce((sum, line) => sum + estimateLineValue(line), 0);
    const formatter = (value) => value.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('adj-line-count').textContent = String(validLines.length);
    document.getElementById('adj-total-spoil').textContent = formatter(totalOfType('SPOILAGE'));
    document.getElementById('adj-total-waste').textContent = formatter(totalOfType('WASTE'));
    document.getElementById('adj-total-plus').textContent = formatter(totalOfType('ADJUSTMENT_PLUS'));
    document.getElementById('adj-total-minus').textContent = formatter(totalOfType('ADJUSTMENT_MINUS'));
    document.getElementById('adj-total-value').textContent = formatter(totalValue);
  }

  function resolveLineUnitCost(line) {
    const type = String(line.adjustment_type || '').toUpperCase();
    if (type === 'ADJUSTMENT_PLUS') {
      return parseFloat(line.unit_cost) || 0;
    }
    return parseFloat(line.stock_unit_cost) || 0;
  }

  function estimateLineValue(line) {
    return (parseFloat(line.qty) || 0) * resolveLineUnitCost(line);
  }

  function formatNumber(value, decimals = 2) {
    return Number(value || 0).toLocaleString('id-ID', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
  }

  function formatDate(value) {
    const safeValue = String(value || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(safeValue)) {
      return safeValue || '-';
    }
    const [year, month, day] = safeValue.split('-');
    return day + '/' + month + '/' + year;
  }

  function lotLocationLabel(locationType) {
    const value = String(locationType || '').toUpperCase();
    if (value === 'BAR' || value === 'KITCHEN' || value === 'ROASTERY') {
      return 'Reguler';
    }
    if (value === 'BAR_EVENT' || value === 'KITCHEN_EVENT' || value === 'ROASTERY_EVENT') {
      return 'Event';
    }
    return value || '-';
  }

  function locationTypeLabel(locationType) {
    const value = String(locationType || '').toUpperCase();
    if (value === 'BAR') {
      return 'Bar Reguler';
    }
    if (value === 'KITCHEN') {
      return 'Kitchen Reguler';
    }
    if (value === 'ROASTERY') {
      return 'Roastery Reguler';
    }
    if (value === 'BAR_EVENT') {
      return 'Bar Event';
    }
    if (value === 'KITCHEN_EVENT') {
      return 'Kitchen Event';
    }
    if (value === 'ROASTERY_EVENT') {
      return 'Roastery Event';
    }
    return value || '-';
  }

  function plusDestinationMeta() {
    const header = headerContext();
    if (!header.location_type) {
      return {
        label: 'Belum dipilih',
        help: 'Untuk adjustment plus, tentukan dulu apakah stok masuk Reguler atau Event di Header Adjustment.',
        warning: true
      };
    }
    return {
      label: locationTypeLabel(header.location_type),
      help: 'Plus akan menambah stok ke ' + locationTypeLabel(header.location_type) + ' (' + lotLocationLabel(header.location_type) + '). Bila butuh tujuan lain, pisahkan ke draft berbeda.',
      warning: false
    };
  }

  function requiresLotSelection(line) {
    return Number(line.lot_count || 0) > 1 && String(line.adjustment_type || '').toUpperCase() !== 'ADJUSTMENT_PLUS';
  }

  function selectedLotProfileText(lotRow) {
    const parts = [];
    if (String(lotRow.receipt_date || '').trim() !== '') {
      parts.push('Terima ' + formatDate(lotRow.receipt_date));
    }
    parts.push('Qty ' + formatNumber(lotRow.qty_balance, 2));
    parts.push('Cost ' + formatNumber(lotRow.unit_cost, 2));
    if (String(lotRow.expiry_date || '').trim() !== '') {
      parts.push('Exp ' + formatDate(lotRow.expiry_date));
    }
    if (String(lotRow.location_type || '').trim() !== '') {
      parts.push(lotLocationLabel(lotRow.location_type));
    }
    return parts.join(' | ');
  }

  function lotPreviewText(line) {
    const type = String(line.adjustment_type || '').toUpperCase();
    if (type === 'ADJUSTMENT_PLUS') {
      const plusMeta = plusDestinationMeta();
      return plusMeta.warning
        ? 'Adjustment plus akan membuat lot inbound baru. Pilih dulu tujuan Reguler atau Event di Header Adjustment.'
        : 'Adjustment plus akan membuat lot inbound baru ke ' + plusMeta.label + '.';
    }
    if (Number(line.selected_lot_id || 0) > 0 && String(line.selected_lot_profile || '').trim() !== '') {
      return 'Lot adj: ' + line.selected_lot_profile;
    }
    if (requiresLotSelection(line)) {
      return 'Pilih lot adjustment agar available dan cost mengikuti lot terpilih.';
    }
    if (Number(line.lot_count || 0) === 1 && Array.isArray(line.lot_rows) && line.lot_rows.length === 1) {
      return 'Lot adj: ' + selectedLotProfileText(line.lot_rows[0]);
    }
    return 'Belum ada lot aktif';
  }

  function ensureHeaderModal() {
    if (!headerModalEl || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }
    if (!headerModal) {
      headerModal = window.bootstrap.Modal.getOrCreateInstance(headerModalEl);
    }
    return headerModal;
  }

  function ensureLineModal() {
    if (!lineModalEl || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }
    if (!lineModal) {
      lineModal = window.bootstrap.Modal.getOrCreateInstance(lineModalEl);
    }
    return lineModal;
  }

  function ensureLotPickerModal() {
    if (!lotPickerModalEl || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }
    if (!lotPickerModal) {
      lotPickerModal = window.bootstrap.Modal.getOrCreateInstance(lotPickerModalEl);
    }
    return lotPickerModal;
  }

  function ensureAdjustmentStepUpModal() {
    if (!adjustmentStepUpModalEl || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }
    if (!adjustmentStepUpModal) {
      adjustmentStepUpModal = window.bootstrap.Modal.getOrCreateInstance(adjustmentStepUpModalEl);
    }
    return adjustmentStepUpModal;
  }

  function ensureAdjustmentVoidStepUpModal() {
    if (!adjustmentVoidStepUpModalEl || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }
    if (!adjustmentVoidStepUpModal) {
      adjustmentVoidStepUpModal = window.bootstrap.Modal.getOrCreateInstance(adjustmentVoidStepUpModalEl);
    }
    return adjustmentVoidStepUpModal;
  }

  function fillHeaderModal() {
    const header = headerContext();
    if (headerDateInput) {
      headerDateInput.value = header.adjustment_date || '';
    }
    if (headerDivisionInput) {
      headerDivisionInput.value = header.division_id || '';
    }
    renderHeaderLocationOptions(header.location_type || '');
    if (headerNotesInput) {
      headerNotesInput.value = header.notes || '';
    }
  }

  function selectedText(selectEl, fallback = '-') {
    if (!selectEl || !selectEl.selectedOptions || !selectEl.selectedOptions.length) {
      return fallback;
    }
    return String(selectEl.selectedOptions[0].textContent || '').trim() || fallback;
  }

  function renderHeaderSummary() {
    const header = headerContext();
    if (headerDatePreview) {
      headerDatePreview.textContent = header.adjustment_date ? formatDate(header.adjustment_date) : '-';
    }
    if (headerLocationPreview) {
      renderHeaderLocationOptions(header.location_type || '');
      headerLocationPreview.textContent = header.location_type ? selectedText(headerLocationInput) : '-';
    }
    if (headerDivisionPreview) {
      if (headerDivisionInput) {
        headerDivisionInput.value = header.division_id || '';
      }
      headerDivisionPreview.textContent = header.division_id ? selectedText(headerDivisionInput) : '-';
    }
    if (headerNotesPreview) {
      headerNotesPreview.textContent = header.notes || '-';
    }
  }

  function applyHeaderValues(values) {
    const prev = headerContext();
    const dateField = headerField('adjustment_date');
    const locationField = headerField('location_type');
    const divisionField = headerField('division_id');
    const notesField = headerField('notes');
    if (dateField) {
      dateField.value = String(values.adjustment_date || '');
    }
    if (locationField) {
      locationField.value = String(values.location_type || '');
    }
    if (divisionField) {
      divisionField.value = String(values.division_id || '');
    }
    if (notesField) {
      notesField.value = String(values.notes || '');
    }
    renderHeaderSummary();

    const next = headerContext();
    if (prev.location_type !== next.location_type || prev.division_id !== next.division_id) {
      lines = lines.map((line) => ({
        ...line,
        selected_lot_id: '',
        selected_lot_profile: '',
        available_qty: '',
        stock_unit_cost: '',
        lot_preview: '',
        lot_count: 0,
        lot_rows: []
      }));
      renderLineCards();
      refreshAllLineAvailability();
      if (lineDraft && Number(lineDraft.component_id || 0) > 0) {
        lineDraft.selected_lot_id = '';
        lineDraft.selected_lot_profile = '';
        lineDraft.lot_rows = [];
        refreshDraftSnapshot();
      } else if (lineDraft) {
        syncLineModal();
      }
    } else if (lineDraft) {
      syncLineModal();
    }
  }

  function openHeaderModal() {
    fillHeaderModal();
    ensureHeaderModal()?.show();
  }

  function headerReadyForLineEntry() {
    const header = headerContext();
    return String(header.adjustment_date || '').trim() !== ''
      && String(header.division_id || '').trim() !== ''
      && String(header.location_type || '').trim() !== '';
  }

  function applySelectedLotToDraft(lotRow) {
    if (!lineDraft || !lotRow) {
      return;
    }
    lineDraft.selected_lot_id = String(lotRow.id || '');
    lineDraft.selected_lot_profile = selectedLotProfileText(lotRow);
    lineDraft.available_qty = String(lotRow.qty_balance != null ? lotRow.qty_balance : '0');
    lineDraft.stock_unit_cost = String(lotRow.unit_cost != null ? lotRow.unit_cost : '0');
    if (String(lineDraft.adjustment_type || '').toUpperCase() !== 'ADJUSTMENT_PLUS') {
      lineDraft.unit_cost = String(lotRow.unit_cost != null ? lotRow.unit_cost : '0');
    }
    syncLineModal();
  }

  function openLotPicker() {
    if (!lineDraft || !Array.isArray(lineDraft.lot_rows) || lineDraft.lot_rows.length <= 1) {
      return;
    }
    const modal = ensureLotPickerModal();
    if (!modal || !lotPickerBody || !lotPickerMeta) {
      return;
    }
    const uomOption = uomOptions.find((option) => String(option.id) === String(lineDraft.uom_id || ''));
    lotPickerTarget = 'draft';
    lotPickerMeta.textContent = (lineDraft.component_label || 'Component') + ' • ' + (uomOption ? uomOption.label : '-') + ' • pilih lot yang akan dipakai untuk adjustment.';
    lotPickerBody.innerHTML = lineDraft.lot_rows.map((lotRow, lotIndex) => {
      const isActive = Number(lineDraft.selected_lot_id || 0) > 0 && Number(lineDraft.selected_lot_id || 0) === Number(lotRow.id || 0);
      return '<div class="component-adjustment-lot-choice-card component-adjustment-lot-choice-row' + (isActive ? ' is-active' : '') + '">' +
        '<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">' +
          '<div>' +
            '<strong>Lot ' + (lotIndex + 1) + '</strong>' +
            '<div class="small text-muted mt-1">Terima ' + escapeHtml(formatDate(lotRow.receipt_date)) + (lotRow.expiry_date ? ' • Exp ' + escapeHtml(formatDate(lotRow.expiry_date)) : '') + '</div>' +
            '<div class="small text-muted">Lokasi ' + escapeHtml(lotLocationLabel(lotRow.location_type)) + '</div>' +
          '</div>' +
          '<div class="text-end">' +
            '<div><strong>' + escapeHtml(formatNumber(lotRow.qty_balance, 2)) + '</strong></div>' +
            '<div class="small text-muted">Qty tersedia</div>' +
          '</div>' +
          '<div class="text-end">' +
            '<div><strong>' + escapeHtml(formatNumber(lotRow.unit_cost, 2)) + '</strong></div>' +
            '<div class="small text-muted">Cost / unit</div>' +
          '</div>' +
          '<div class="text-end">' +
            '<button type="button" class="btn btn-outline-primary btn-sm" data-action="choose-lot" data-lot-index="' + lotIndex + '">Pilih Lot Ini</button>' +
          '</div>' +
        '</div>' +
      '</div>';
    }).join('');
    modal.show();
  }

  function bindLineComponentPicker() {
    if (!lineComponentInput || !window.ProductionAjaxPicker) {
      return;
    }
    window.ProductionAjaxPicker.bind(lineComponentInput, {
      entity: 'COMPONENT',
      params: () => headerContext(),
      renderLabel: componentPickerLabel,
      renderSubLabel: componentPickerSubLabel,
      onType: (value) => {
        if (!lineDraft) {
          return;
        }
        lineDraft.component_label = value;
        lineDraft.component_id = '';
        lineDraft.uom_id = '';
        lineDraft.selected_lot_id = '';
        lineDraft.selected_lot_profile = '';
        lineDraft.available_qty = '';
        lineDraft.stock_unit_cost = '';
        lineDraft.lot_preview = '';
        lineDraft.lot_count = 0;
        lineDraft.lot_rows = [];
        syncLineModal();
      },
      onSelect: (result) => {
        if (!lineDraft) {
          return;
        }
        lineDraft.component_id = String(result.id || '');
        lineDraft.component_label = componentPickerLabel(result);
        lineDraft.uom_id = String(result.uom_id || '');
        lineDraft.selected_lot_id = '';
        lineDraft.selected_lot_profile = '';
        lineDraft.lot_preview = String(result.lot_preview || '');
        lineDraft.lot_count = Number(result.lot_count || 0);
        lineDraft.lot_rows = [];
        lineDraft.stock_unit_cost = String(result.avg_cost || '');
        syncLineModal();
        refreshDraftSnapshot();
      }
    });
  }

  async function refreshDraftSnapshot() {
    if (!lineDraft) {
      return;
    }
    const componentId = Number(lineDraft.component_id || 0);
    const uomId = Number(lineDraft.uom_id || 0);
    if (componentId <= 0) {
      lineDraft.available_qty = '';
      lineDraft.stock_unit_cost = '';
      lineDraft.selected_lot_id = '';
      lineDraft.selected_lot_profile = '';
      lineDraft.lot_preview = '';
      lineDraft.lot_count = 0;
      lineDraft.lot_rows = [];
      syncLineModal();
      return;
    }

    const context = headerContext();
    const params = new URLSearchParams({
      component_id: String(componentId),
      uom_id: String(uomId || ''),
      location_type: context.location_type,
      division_id: context.division_id,
      lot_id: String(lineDraft.selected_lot_id || '')
    });
    const token = String(Date.now()) + ':draft:' + String(componentId);
    lineDraft._snapshotToken = token;
    try {
      const result = await loadJson(stockSnapshotUrl + '?' + params.toString());
      if (!lineDraft || lineDraft._snapshotToken !== token) {
        return;
      }
      const snapshot = result && result.snapshot ? result.snapshot : {};
      const lotSummary = snapshot && snapshot.lot_summary ? snapshot.lot_summary : {};
      lineDraft.available_qty = String(snapshot.available_qty != null ? snapshot.available_qty : '0');
      lineDraft.stock_unit_cost = String(snapshot.unit_cost != null ? snapshot.unit_cost : '0');
      lineDraft.lot_preview = String(snapshot.lot_preview || '');
      lineDraft.lot_count = Number(lotSummary && lotSummary.lot_count ? lotSummary.lot_count : 0);
      lineDraft.lot_rows = Array.isArray(lotSummary.rows) ? lotSummary.rows : [];
      if (snapshot.selected_lot && Number(snapshot.selected_lot.id || 0) > 0) {
        lineDraft.selected_lot_id = String(snapshot.selected_lot.id || '');
        lineDraft.selected_lot_profile = String(snapshot.selected_lot.profile_label || '');
      } else {
        lineDraft.selected_lot_id = '';
        lineDraft.selected_lot_profile = '';
      }
      if (!(parseFloat(lineDraft.unit_cost) > 0) && (parseFloat(lineDraft.stock_unit_cost) > 0)) {
        lineDraft.unit_cost = String(lineDraft.stock_unit_cost);
      }
      syncLineModal();
      if (requiresLotSelection(lineDraft) && !(Number(lineDraft.selected_lot_id || 0) > 0)) {
        openLotPicker();
      }
    } catch (error) {
      if (lineDraft && lineDraft._snapshotToken === token) {
        lineDraft.selected_lot_id = '';
        lineDraft.selected_lot_profile = '';
        lineDraft.available_qty = '';
        lineDraft.stock_unit_cost = '';
        lineDraft.lot_preview = '';
        lineDraft.lot_count = 0;
        lineDraft.lot_rows = [];
        syncLineModal();
      }
    }
  }

  function refreshAllLineAvailability() {
    lines.forEach((line, index) => {
      if (Number(line.component_id || 0) > 0) {
        refreshLineSnapshot(index);
      }
    });
  }

  async function refreshLineSnapshot(index) {
    if (index < 0 || !lines[index]) {
      return;
    }
    const draftBackup = lineDraft;
    const editingBackup = editingLineIndex;
    lineDraft = cloneLine(lines[index]);
    editingLineIndex = index;
    await refreshDraftSnapshot();
    if (lineDraft) {
      lines[index] = cloneLine(lineDraft);
      renderLineCards();
    }
    lineDraft = draftBackup;
    editingLineIndex = editingBackup;
    if (lineDraft) {
      syncLineModal();
    }
  }

  function lineReasonLabel(line) {
    const options = adjustmentReasonOptions?.[String(line.adjustment_type || '').toUpperCase()] || {};
    return options[String(line.reason_code || 'other')] || line.reason_code || '-';
  }

  function lineTypeLabel(line) {
    const meta = adjustmentTypeMeta(line.adjustment_type);
    return meta ? meta.label : '-';
  }

  function uomLabelById(uomId) {
    const selected = uomOptions.find((option) => String(option.id) === String(uomId || ''));
    return selected ? selected.label : '-';
  }

  function renderLineCards() {
    if (!lineList || !lineEmpty) {
      renderSummary();
      return;
    }
    lineEmpty.classList.toggle('d-none', lines.length > 0);
    if (!lines.length) {
      lineList.innerHTML = '';
      renderSummary();
      return;
    }
    lineList.innerHTML = lines.map((line, index) => {
      const typeMeta = adjustmentTypeMeta(line.adjustment_type);
      const estimatedValue = estimateLineValue(line);
      const lineUnitCost = resolveLineUnitCost(line);
      return '<div class="component-adjustment-line-card" data-index="' + index + '">' +
        '<div class="line-head">' +
          '<div>' +
            '<div class="line-title">' + escapeHtml(line.component_label || ('Baris ' + (index + 1))) + '</div>' +
            '<div class="line-sub">' + escapeHtml(uomLabelById(line.uom_id)) + ' • ' + escapeHtml(lineTypeLabel(line)) + '</div>' +
            '<div class="line-sub mt-1">' + escapeHtml(lotPreviewText(line)) + '</div>' +
          '</div>' +
          '<div class="component-adjustment-line-actions">' +
            '<button type="button" class="btn btn-outline-primary btn-sm" data-action="edit-line"><i class="ri ri-edit-line me-1"></i>Edit</button>' +
            '<button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-line"><i class="ri ri-delete-bin-line me-1"></i>Hapus</button>' +
          '</div>' +
        '</div>' +
        '<div class="component-adjustment-line-metrics">' +
          '<div class="component-adjustment-line-metric"><span class="label">Available</span><strong>' + escapeHtml(formatNumber(line.available_qty, 2)) + '</strong></div>' +
          '<div class="component-adjustment-line-metric"><span class="label">Jumlah</span><strong>' + escapeHtml(formatNumber(line.qty, 2)) + '</strong></div>' +
          '<div class="component-adjustment-line-metric"><span class="label">Cost</span><strong>' + escapeHtml(formatNumber(lineUnitCost, 2)) + '</strong></div>' +
          '<div class="component-adjustment-line-metric"><span class="label">Est. Nilai</span><strong>' + escapeHtml(formatNumber(estimatedValue, 2)) + '</strong></div>' +
        '</div>' +
        '<div class="line-sub mt-2">Reason: ' + escapeHtml(lineReasonLabel(line)) + (String(line.note || '').trim() !== '' ? ' • Catatan: ' + escapeHtml(line.note) : '') + '</div>' +
      '</div>';
    }).join('');
    renderSummary();
  }

  function renderReasonOptionsForLine(line) {
    if (!lineReasonInput) {
      return;
    }
    lineReasonInput.innerHTML = reasonSelectOptions(String(line.adjustment_type || 'WASTE').toUpperCase(), line.reason_code || 'other');
  }

  function syncLineModal() {
    if (!lineDraft) {
      return;
    }
    const typeMeta = adjustmentTypeMeta(lineDraft.adjustment_type);
    const isPlus = typeMeta.value === 'ADJUSTMENT_PLUS';
    if (lineModalTitle) {
      lineModalTitle.textContent = editingLineIndex >= 0 ? 'Edit Baris Adjustment' : 'Tambah Baris Adjustment';
    }
    if (lineComponentInput) {
      lineComponentInput.value = String(lineDraft.component_label || '');
      if (Number(lineDraft.component_id || 0) > 0) {
        lineComponentInput.setAttribute('data-selected-label', String(lineDraft.component_label || ''));
        lineComponentInput.setAttribute('data-selected-sub-label', componentPickerSubLabel(lineDraft));
      } else {
        lineComponentInput.removeAttribute('data-selected-label');
        lineComponentInput.removeAttribute('data-selected-sub-label');
      }
    }
    if (lineUomInput) {
      lineUomInput.innerHTML = uomSelectOptions(lineDraft.uom_id);
      lineUomInput.value = String(lineDraft.uom_id || '');
    }
    if (lineAvailableInput) {
      lineAvailableInput.value = String(lineDraft.available_qty || '');
    }
    if (lineLotNote) {
      lineLotNote.textContent = lotPreviewText(lineDraft);
    }
    if (lineSelectedLot) {
      lineSelectedLot.textContent = lineDraft.selected_lot_profile || 'Belum ada lot dipilih.';
    }
    if (btnOpenLineLotPicker) {
      btnOpenLineLotPicker.classList.toggle('d-none', !requiresLotSelection(lineDraft));
    }
    if (lineTypeInput) {
      lineTypeInput.innerHTML = typeSelectOptions(lineDraft.adjustment_type);
      lineTypeInput.value = String(lineDraft.adjustment_type || 'WASTE').toUpperCase();
    }
    if (lineQtyInput) {
      lineQtyInput.value = String(lineDraft.qty || '');
    }
    if (lineUnitCostInput) {
      lineUnitCostInput.value = String(lineDraft.unit_cost || '');
      lineUnitCostInput.readOnly = !isPlus;
      lineUnitCostInput.classList.toggle('component-adjustment-price-muted', !isPlus);
    }
    if (plusDestinationWrap) {
      const plusMeta = plusDestinationMeta();
      plusDestinationWrap.classList.toggle('d-none', !isPlus);
      if (plusDestinationCard) {
        plusDestinationCard.classList.toggle('is-warning', !!plusMeta.warning && isPlus);
      }
      if (plusDestinationText) {
        plusDestinationText.textContent = plusMeta.label;
      }
      if (plusDestinationHelp) {
        plusDestinationHelp.textContent = plusMeta.help;
      }
    }
    renderReasonOptionsForLine(lineDraft);
    if (lineReasonInput) {
      lineReasonInput.value = String(lineDraft.reason_code || 'other');
    }
    if (lineNoteInput) {
      lineNoteInput.value = String(lineDraft.note || '');
    }
    if (lineEstimatedValue) {
      lineEstimatedValue.textContent = formatNumber(estimateLineValue(lineDraft), 2);
    }
    if (lineEstimatedMeta) {
      lineEstimatedMeta.textContent = '@ ' + formatNumber(resolveLineUnitCost(lineDraft), 2) + (Number(lineDraft.selected_lot_id || 0) > 0 ? ' • lot dipilih' : (Number(lineDraft.lot_count || 0) > 1 ? ' • pilih lot' : ''));
    }
  }

  function openLineEditor(index) {
    if (!headerReadyForLineEntry()) {
      pendingLineOpenIndex = index >= 0 ? index : -1;
      renderAlert('warning', 'Pilih tanggal, divisi, lalu lokasi adjustment terlebih dahulu supaya stok dan lot mengikuti header yang dipilih.');
      openHeaderModal();
      return;
    }
    editingLineIndex = index >= 0 ? index : -1;
    lineDraft = editingLineIndex >= 0 && lines[editingLineIndex]
      ? cloneLine(lines[editingLineIndex])
      : blankLine();
    syncLineModal();
    ensureLineModal()?.show();
    if (Number(lineDraft.component_id || 0) > 0 && (!Array.isArray(lineDraft.lot_rows) || !lineDraft.lot_rows.length)) {
      refreshDraftSnapshot();
    }
  }

  function saveDraftLine() {
    if (!lineDraft) {
      return;
    }
    if (!(Number(lineDraft.component_id || 0) > 0)) {
      renderAlert('warning', 'Pilih component terlebih dahulu.');
      return;
    }
    if (!(Number(lineDraft.uom_id || 0) > 0)) {
      renderAlert('warning', 'Pilih UOM untuk baris adjustment ini.');
      return;
    }
    if (!((parseFloat(lineDraft.qty) || 0) > 0)) {
      renderAlert('warning', 'Jumlah adjustment harus lebih besar dari nol.');
      return;
    }
    if (requiresLotSelection(lineDraft) && !(Number(lineDraft.selected_lot_id || 0) > 0)) {
      renderAlert('warning', 'Pilih lot untuk component multi-lot sebelum menyimpan baris.');
      return;
    }
    if (String(lineDraft.adjustment_type || '').toUpperCase() === 'ADJUSTMENT_PLUS' && !headerContext().location_type) {
      renderAlert('warning', 'Adjustment plus membutuhkan lokasi tujuan di Header Adjustment. Pilih dulu Reguler atau Event.');
      openHeaderModal();
      return;
    }
    if (String(lineDraft.adjustment_type || '').toUpperCase() === 'ADJUSTMENT_PLUS' && !((parseFloat(lineDraft.unit_cost) || 0) > 0)) {
      renderAlert('warning', 'Harga Plus wajib diisi untuk adjustment plus.');
      return;
    }
    const savedLine = cloneLine(lineDraft);
    if (editingLineIndex >= 0 && lines[editingLineIndex]) {
      lines[editingLineIndex] = savedLine;
    } else {
      lines.push(savedLine);
    }
    renderLineCards();
    ensureLineModal()?.hide();
  }

  function serializeLines() {
    return lines
      .filter((line) => Number(line.component_id) > 0 && Number(line.uom_id) > 0)
      .map((line) => {
        const type = String(line.adjustment_type || 'WASTE').toUpperCase();
        const qty = parseFloat(line.qty) || 0;
        return {
          component_id: Number(line.component_id),
          uom_id: Number(line.uom_id),
          selected_lot_id: Number(line.selected_lot_id || 0),
          available_qty: parseFloat(line.available_qty) || 0,
          qty_spoil: type === 'SPOILAGE' ? qty : 0,
          spoil_reason_code: type === 'SPOILAGE' ? String(line.reason_code || 'other') : 'other',
          qty_waste: type === 'WASTE' ? qty : 0,
          waste_reason_code: type === 'WASTE' ? String(line.reason_code || 'other') : 'other',
          qty_adjust_pos: type === 'ADJUSTMENT_PLUS' ? qty : 0,
          adjustment_plus_reason_code: type === 'ADJUSTMENT_PLUS' ? String(line.reason_code || 'other') : 'other',
          unit_cost: type === 'ADJUSTMENT_PLUS' ? (parseFloat(line.unit_cost) || 0) : 0,
          qty_adjust_neg: type === 'ADJUSTMENT_MINUS' ? qty : 0,
          adjustment_minus_reason_code: type === 'ADJUSTMENT_MINUS' ? String(line.reason_code || 'other') : 'other',
          note: String(line.note || '')
        };
      })
      .filter((line) => line.available_qty > 0 || line.qty_spoil > 0 || line.qty_waste > 0 || line.qty_adjust_pos > 0 || line.qty_adjust_neg > 0);
  }

  document.getElementById('btn-add-adjustment-line')?.addEventListener('click', () => {
    openLineEditor(-1);
  });

  lineList?.addEventListener('click', (event) => {
    const editButton = event.target.closest('button[data-action="edit-line"]');
    if (editButton) {
      const card = editButton.closest('[data-index]');
      const index = Number(card?.dataset.index || -1);
      if (index >= 0) {
        openLineEditor(index);
      }
      return;
    }
    const button = event.target.closest('button[data-action="remove-line"]');
    if (!button) {
      return;
    }
    const card = button.closest('[data-index]');
    const index = Number(card?.dataset.index || -1);
    if (index >= 0) {
      lines.splice(index, 1);
      renderLineCards();
    }
  });

  btnEditHeader?.addEventListener('click', openHeaderModal);

  headerDivisionInput?.addEventListener('change', () => {
    renderHeaderLocationOptions('');
  });

  btnSaveHeader?.addEventListener('click', () => {
    const adjustmentDate = String(headerDateInput?.value || '');
    const divisionId = String(headerDivisionInput?.value || '');
    const locationType = String(headerLocationInput?.value || '');
    if (adjustmentDate === '' || divisionId === '' || locationType === '') {
      renderAlert('warning', 'Tanggal, divisi, dan lokasi adjustment wajib diisi di header.');
      return;
    }
    if (adjustmentDate > '<?php echo date('Y-m-d'); ?>') {
      renderAlert('warning', 'Tanggal adjustment component tidak boleh melewati hari ini.');
      return;
    }
    applyHeaderValues({
      adjustment_date: adjustmentDate,
      location_type: locationType,
      division_id: divisionId,
      notes: String(headerNotesInput?.value || '')
    });
    ensureHeaderModal()?.hide();
    if (pendingLineOpenIndex !== null) {
      const targetIndex = pendingLineOpenIndex;
      pendingLineOpenIndex = null;
      openLineEditor(targetIndex);
    }
  });

  lineUomInput?.addEventListener('change', () => {
    if (!lineDraft) {
      return;
    }
    lineDraft.uom_id = String(lineUomInput.value || '');
    lineDraft.selected_lot_id = '';
    lineDraft.selected_lot_profile = '';
    lineDraft.lot_rows = [];
    refreshDraftSnapshot();
  });

  lineTypeInput?.addEventListener('change', () => {
    if (!lineDraft) {
      return;
    }
    lineDraft.adjustment_type = String(lineTypeInput.value || 'WASTE').toUpperCase();
    lineDraft.reason_code = 'other';
    if (lineDraft.adjustment_type !== 'ADJUSTMENT_PLUS') {
      lineDraft.unit_cost = '';
    }
    syncLineModal();
    if (lineDraft.adjustment_type === 'ADJUSTMENT_PLUS' && !headerContext().location_type) {
      renderAlert('warning', 'Untuk adjustment plus, pilih dulu lokasi tujuan di Header Adjustment: Reguler atau Event.');
      openHeaderModal();
      return;
    }
    if (requiresLotSelection(lineDraft) && !(Number(lineDraft.selected_lot_id || 0) > 0)) {
      openLotPicker();
    }
  });

  btnEditPlusDestination?.addEventListener('click', () => {
    openHeaderModal();
  });

  lineQtyInput?.addEventListener('input', () => {
    if (!lineDraft) {
      return;
    }
    lineDraft.qty = String(lineQtyInput.value || '');
    syncLineModal();
  });

  lineUnitCostInput?.addEventListener('input', () => {
    if (!lineDraft) {
      return;
    }
    lineDraft.unit_cost = String(lineUnitCostInput.value || '');
    syncLineModal();
  });

  lineReasonInput?.addEventListener('change', () => {
    if (!lineDraft) {
      return;
    }
    lineDraft.reason_code = String(lineReasonInput.value || 'other');
  });

  lineNoteInput?.addEventListener('input', () => {
    if (!lineDraft) {
      return;
    }
    lineDraft.note = String(lineNoteInput.value || '');
  });

  btnOpenLineLotPicker?.addEventListener('click', () => {
    openLotPicker();
  });

  btnSaveLine?.addEventListener('click', saveDraftLine);

  function setButtonBusy(button, label) {
    if (!button) {
      return;
    }
    if (!button.dataset.originalHtml) {
      button.dataset.originalHtml = button.innerHTML;
    }
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>' + escapeHtml(label || 'Memproses...') + '</span>';
    button.classList.add('component-adjustment-save-spinner');
  }

  function clearButtonBusy(button) {
    if (!button) {
      return;
    }
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button);
      return;
    }
    button.disabled = false;
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
    }
    button.classList.remove('component-adjustment-save-spinner');
  }

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = event.submitter || form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    const payload = {
      adjustment_date: String(formData.get('adjustment_date') || ''),
      location_type: String(formData.get('location_type') || ''),
      division_id: String(formData.get('division_id') || ''),
      notes: String(formData.get('notes') || ''),
      lines: serializeLines()
    };
    if (payload.adjustment_date === '' || payload.division_id === '' || payload.location_type === '') {
      renderAlert('warning', 'Lengkapi header adjustment terlebih dahulu lewat modal Header Adjustment.');
      openHeaderModal();
      return;
    }
    if (payload.adjustment_date > '<?php echo date('Y-m-d'); ?>') {
      renderAlert('warning', 'Tanggal adjustment component tidak boleh melewati hari ini karena posting langsung mengubah stok live.');
      openHeaderModal();
      return;
    }
    if (!payload.lines.length) {
      renderAlert('warning', 'Tambahkan minimal satu baris adjustment yang berisi angka perubahan.');
      openLineEditor(-1);
      return;
    }
    const missingSelectedLot = payload.lines.some((line) => (line.qty_spoil > 0 || line.qty_waste > 0 || line.qty_adjust_neg > 0) && !(line.selected_lot_id > 0));
    if (missingSelectedLot) {
      renderAlert('warning', 'Untuk spoil, waste, atau minus pada component multi-lot, pilih dulu lot yang akan di-adjust.');
      const targetIndex = lines.findIndex((line) => (parseFloat(line.qty_spoil) || 0) > 0 || (parseFloat(line.qty_waste) || 0) > 0 || (parseFloat(line.qty_adjust_neg) || 0) > 0);
      if (targetIndex >= 0) {
        openLineEditor(targetIndex);
      }
      return;
    }
    const missingPlusCost = payload.lines.some((line) => line.qty_adjust_pos > 0 && !(line.unit_cost > 0));
    if (missingPlusCost) {
      renderAlert('warning', 'Harga Plus wajib diisi untuk setiap baris bertipe Plus.');
      const plusIndex = lines.findIndex((line) => String(line.adjustment_type || '').toUpperCase() === 'ADJUSTMENT_PLUS' && !((parseFloat(line.unit_cost) || 0) > 0));
      if (plusIndex >= 0) {
        openLineEditor(plusIndex);
      }
      return;
    }
    setButtonBusy(submitButton, 'Menyimpan adjustment...');
    try {
      await postComponentAdjustmentJson(saveUrl, payload);
      window.location.reload();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal menyimpan adjustment.');
      clearButtonBusy(submitButton);
    }
  });

  lotPickerBody?.addEventListener('click', (event) => {
    const chooseButton = event.target.closest('button[data-action="choose-lot"]');
    if (!chooseButton || lotPickerTarget !== 'draft' || !lineDraft) {
      return;
    }
    const lotIndex = Number(chooseButton.dataset.lotIndex || -1);
    const lotRow = Array.isArray(lineDraft.lot_rows) ? lineDraft.lot_rows[lotIndex] : null;
    if (!lotRow) {
      return;
    }
    applySelectedLotToDraft(lotRow);
    ensureLotPickerModal()?.hide();
  });

  document.querySelectorAll('.btn-post').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Posting adjustment akan menulis mutasi spoil, waste, plus, dan minus ke ledger component.', {
        title: 'Post Dokumen Adjustment',
        okText: 'Post Adjustment',
        cancelText: 'Batal'
      }))) {
        return;
      }
      const adjustmentId = Number(button.dataset.id || 0);
      const modal = ensureAdjustmentStepUpModal();
      if (!(adjustmentId > 0) || !modal) {
        renderAlert('danger', 'Verifikasi posting belum siap. Muat ulang halaman lalu coba kembali.');
        return;
      }
      pendingAdjustmentPostId = adjustmentId;
      pendingAdjustmentPostButton = button;
      if (adjustmentStepUpPassword) adjustmentStepUpPassword.value = '';
      modal.show();
      window.setTimeout(() => adjustmentStepUpPassword?.focus(), 150);
    });
  });

  btnAdjustmentStepUpPost?.addEventListener('click', async () => {
    if (adjustmentStepUpSubmitting) {
      return;
    }
    const adjustmentId = pendingAdjustmentPostId;
    const button = pendingAdjustmentPostButton;
    try {
      if (!(adjustmentId > 0) || !button) throw new Error('Dokumen adjustment tidak valid. Tutup modal lalu coba lagi.');
      const password = String(adjustmentStepUpPassword?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi posting adjustment.');
      if (adjustmentStepUpPassword) adjustmentStepUpPassword.value = '';
      adjustmentStepUpSubmitting = true;
      adjustmentStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((dismissButton) => {
        dismissButton.disabled = true;
      });
      setButtonBusy(btnAdjustmentStepUpPost, 'Memverifikasi...');
      const stepUp = await postComponentAdjustmentJson(componentAdjustmentStepUpUrl, {
        adjustment_id: adjustmentId,
        password
      });
      if (!/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) {
        throw new Error('Bukti verifikasi ulang tidak valid. Coba lagi.');
      }
      setButtonBusy(button, 'Posting...');
      await postComponentAdjustmentJson(postBaseUrl + '/' + adjustmentId, {
        step_up_proof: String(stepUp.step_up_proof)
      });
      window.location.reload();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal post adjustment.');
      adjustmentStepUpSubmitting = false;
      adjustmentStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((dismissButton) => {
        dismissButton.disabled = false;
      });
      clearButtonBusy(button);
      clearButtonBusy(btnAdjustmentStepUpPost);
    }
  });

  adjustmentStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (adjustmentStepUpSubmitting) {
      return;
    }
    if (adjustmentStepUpPassword) adjustmentStepUpPassword.value = '';
    clearButtonBusy(btnAdjustmentStepUpPost);
    if (pendingAdjustmentPostButton) {
      clearButtonBusy(pendingAdjustmentPostButton);
    }
    pendingAdjustmentPostId = 0;
    pendingAdjustmentPostButton = null;
  });

  btnAdjustmentVoidStepUpPost?.addEventListener('click', async () => {
    if (adjustmentVoidStepUpSubmitting) {
      return;
    }
    const adjustmentId = pendingAdjustmentVoidId;
    const button = pendingAdjustmentVoidButton;
    try {
      if (!(adjustmentId > 0) || !button) throw new Error('Dokumen adjustment tidak valid. Tutup modal lalu coba lagi.');
      const password = String(adjustmentVoidStepUpPassword?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi VOID adjustment.');
      if (adjustmentVoidStepUpPassword) adjustmentVoidStepUpPassword.value = '';
      adjustmentVoidStepUpSubmitting = true;
      adjustmentVoidStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((dismissButton) => {
        dismissButton.disabled = true;
      });
      setButtonBusy(btnAdjustmentVoidStepUpPost, 'Memverifikasi...');
      const stepUp = await postComponentAdjustmentJson(componentAdjustmentVoidStepUpUrl, {
        adjustment_id: adjustmentId,
        password
      });
      if (!/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) {
        throw new Error('Bukti verifikasi ulang tidak valid. Coba lagi.');
      }
      setButtonBusy(button, 'VOID...');
      await postComponentAdjustmentJson(voidBaseUrl + '/' + adjustmentId, {
        step_up_proof: String(stepUp.step_up_proof)
      });
      window.location.reload();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal VOID adjustment.');
      adjustmentVoidStepUpSubmitting = false;
      adjustmentVoidStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((dismissButton) => {
        dismissButton.disabled = false;
      });
      clearButtonBusy(button);
      clearButtonBusy(btnAdjustmentVoidStepUpPost);
    }
  });

  adjustmentVoidStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (adjustmentVoidStepUpSubmitting) {
      return;
    }
    if (adjustmentVoidStepUpPassword) adjustmentVoidStepUpPassword.value = '';
    clearButtonBusy(btnAdjustmentVoidStepUpPost);
    if (pendingAdjustmentVoidButton) {
      clearButtonBusy(pendingAdjustmentVoidButton);
    }
    pendingAdjustmentVoidId = 0;
    pendingAdjustmentVoidButton = null;
  });

  document.querySelectorAll('.btn-del').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Draft adjustment ini akan dihapus permanen.', {
        title: 'Hapus Draft Adjustment',
        okText: 'Hapus Draft',
        cancelText: 'Batal'
      }))) {
        return;
      }
      setButtonBusy(button, 'Menghapus...');
      try {
        await postComponentAdjustmentJson(deleteBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal menghapus adjustment.');
        clearButtonBusy(button);
      }
    });
  });

  document.querySelectorAll('.btn-void').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('VOID adjustment akan membatalkan movement spoil, waste, plus, dan minus yang sudah diposting.', {
        title: 'VOID Dokumen Adjustment',
        okText: 'VOID Adjustment',
        cancelText: 'Batal'
      }))) {
        return;
      }
      const adjustmentId = Number(button.dataset.id || 0);
      const modal = ensureAdjustmentVoidStepUpModal();
      if (!(adjustmentId > 0) || !modal) {
        renderAlert('danger', 'Verifikasi VOID belum siap. Muat ulang halaman lalu coba kembali.');
        return;
      }
      pendingAdjustmentVoidId = adjustmentId;
      pendingAdjustmentVoidButton = button;
      if (adjustmentVoidStepUpPassword) adjustmentVoidStepUpPassword.value = '';
      modal.show();
      window.setTimeout(() => adjustmentVoidStepUpPassword?.focus(), 150);
    });
  });

  bindLineComponentPicker();
  renderHeaderSummary();
  renderLineCards();

  const formModalEl = document.getElementById('componentAdjustmentFormModal');
  if (formModalEl) {
    formModalEl.addEventListener('shown.bs.modal', () => {
      if (!headerContext().location_type) {
        fillHeaderModal();
      }
    });
  }
})();
</script>
<script>
(function () {
  const perPage = parseInt(document.getElementById('adj-per-page')?.value || '25', 10) || 25;

  function buildPager(containerId, infoId, totalVisible, currentPage, onGo) {
    const info = document.getElementById(infoId);
    const pager = document.getElementById(containerId);
    if (!pager) return;
    const pages = Math.max(1, Math.ceil(totalVisible / perPage));
    if (info) info.textContent = totalVisible + ' baris';
    pager.innerHTML = '';
    for (let p = 1; p <= pages; p++) {
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm ' + (p === currentPage ? 'btn-primary' : 'btn-outline-secondary');
      btn.style.minWidth = '32px';
      btn.textContent = p;
      btn.addEventListener('click', () => onGo(p));
      pager.appendChild(btn);
    }
  }

  function applyFilter(tbodyId, rowClass, infoId, pagerId, searchVal, page) {
    const rows = Array.from(document.querySelectorAll('#' + tbodyId + ' tr.' + rowClass));
    const needle = searchVal.trim().toLowerCase();
    const visible = rows.filter(r => !needle || (r.dataset.search || '').includes(needle));
    rows.forEach(r => { r.style.display = 'none'; });
    const start = (page - 1) * perPage;
    visible.forEach((r, i) => { r.style.display = (i >= start && i < start + perPage) ? '' : 'none'; });
    buildPager(pagerId, infoId, visible.length, page, p => applyFilter(tbodyId, rowClass, infoId, pagerId, searchVal, p));
  }

  let notaPage = 1, rincianPage = 1;
  const searchInput = document.getElementById('adj-search');

  function refresh(resetPage) {
    if (resetPage) { notaPage = 1; rincianPage = 1; }
    const val = searchInput ? searchInput.value : '';
    applyFilter('adj-nota-tbody', 'adj-nota-row', 'adj-nota-info', 'adj-nota-pager', val, notaPage);
    applyFilter('adj-rincian-tbody', 'adj-rincian-row', 'adj-rincian-info', 'adj-rincian-pager', val, rincianPage);
  }

  if (searchInput) {
    searchInput.addEventListener('input', () => refresh(true));
  }

  // Detail button: switch to rincian tab and filter by adjustment_no
  document.querySelectorAll('.btn-detail-nota').forEach(btn => {
    btn.addEventListener('click', () => {
      const adjNo = btn.dataset.no || '';
      const rincianTabLink = document.querySelector('.component-adjustment-list-tabs .nav-link[href*="tab=rincian"], .component-adjustment-list-tabs .nav-link:last-child');
      if (searchInput) {
        searchInput.value = adjNo;
      }
      if (rincianTabLink) {
        rincianTabLink.click();
      } else {
        refresh(true);
      }
    });
  });

  refresh(false);
})();
</script>
