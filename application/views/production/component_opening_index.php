<?php
$rows        = is_array($rows ?? null) ? $rows : [];
$monthlyRows = is_array($monthly_rows ?? null) ? $monthly_rows : [];
$components  = is_array($components ?? null) ? $components : [];
$uoms        = is_array($uoms ?? null) ? $uoms : [];
$divisions   = is_array($divisions ?? null) ? $divisions : [];
$editOpening = is_array($edit_opening ?? null) ? $edit_opening : [];
$editHeader  = is_array($editOpening['header'] ?? null) ? $editOpening['header'] : [];
$editLines   = is_array($editOpening['lines'] ?? null) ? $editOpening['lines'] : [];
$detailOpening            = is_array($detail_opening ?? null) ? $detail_opening : [];
$detailHeader             = is_array($detailOpening['header'] ?? null) ? $detailOpening['header'] : [];
$detailLines              = is_array($detailOpening['lines'] ?? null) ? $detailOpening['lines'] : [];
$detailMovementRows       = is_array($detailOpening['movement_rows'] ?? null) ? $detailOpening['movement_rows'] : [];
$detailEffectiveMovementRows = is_array($detailOpening['effective_movement_rows'] ?? null) ? $detailOpening['effective_movement_rows'] : [];
$detailLotRows            = is_array($detailOpening['lot_rows'] ?? null) ? $detailOpening['lot_rows'] : [];
$detailActiveLotRows      = is_array($detailOpening['active_lot_rows'] ?? null) ? $detailOpening['active_lot_rows'] : [];
$detailSummary            = is_array($detailOpening['summary'] ?? null) ? $detailOpening['summary'] : [];
$locationOptions          = is_array($location_options ?? null) ? $location_options : [];
$componentOpeningExportUrl         = (string)($component_opening_export_url ?? site_url('production/component-openings/export-template'));
$componentOpeningExportExistingUrl = (string)($component_opening_export_existing_url ?? site_url('production/component-openings/export-existing'));
$componentOpeningImportUrl         = (string)($component_opening_import_url ?? site_url('production/component-openings/import'));
$q                   = (string)($q ?? '');
$month               = preg_match('/^\d{4}-\d{2}$/', (string)($month ?? '')) ? (string)$month : date('Y-m');
$dateFrom            = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($date_from ?? '')) ? (string)$date_from : date('Y-m-01');
$dateTo              = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($date_to ?? ''))   ? (string)$date_to   : date('Y-m-t');
$perPage             = in_array((int)($per_page ?? 25), [10, 25, 50, 100], true) ? (int)$per_page : 25;
$selectedLocationType = (string)($selected_location_type ?? '');
$selectedDivisionId   = (int)($selected_division_id ?? 0);
$openingTab = in_array((string)($opening_tab ?? ''), ['documents', 'detail', 'snapshot'], true) ? (string)$opening_tab : (!empty($detailHeader) ? 'detail' : 'documents');
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT') return 'Event';
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY') return 'Reguler';
  return $value !== '' ? $value : '-';
};
$componentMap = [];
foreach ($components as $component) {
  $cid = (int)($component['id'] ?? 0);
  if ($cid > 0) $componentMap[$cid] = $component;
}
$divisionMap = [];
foreach ($divisions as $division) {
  $did = (int)($division['id'] ?? 0);
  if ($did > 0) $divisionMap[$did] = $division;
}
$editPayload = null;
if (!empty($editHeader) && strtoupper((string)($editHeader['status'] ?? '')) === 'DRAFT') {
  $editPayload = [
    'id' => (int)($editHeader['id'] ?? 0),
    'opening_no' => (string)($editHeader['opening_no'] ?? ''),
    'opening_month' => substr((string)($editHeader['opening_date'] ?? date('Y-m-d')), 0, 7),
    'notes' => (string)($editHeader['notes'] ?? ''),
    'location_group' => $locationGroupLabel((string)($editHeader['location_type'] ?? '')) === 'Event' ? 'EVENT' : 'REGULER',
    'lines' => [],
  ];
  foreach ($editLines as $line) {
    $componentId = (int)($line['component_id'] ?? 0);
    $component   = $componentMap[$componentId] ?? [];
    $division    = $divisionMap[(int)($component['operational_division_id'] ?? ($editHeader['division_id'] ?? 0))] ?? [];
    $editPayload['lines'][] = [
      'component_id'           => $componentId > 0 ? (string)$componentId : '',
      'component_label'        => (string)($line['component_name'] ?? $component['component_name'] ?? ''),
      'component_division_id'  => (string)($component['operational_division_id'] ?? ($editHeader['division_id'] ?? '')),
      'component_division_code'=> (string)($division['code'] ?? $component['division_code'] ?? ''),
      'component_division_name'=> (string)($division['name'] ?? $component['division_name'] ?? ($editHeader['division_name'] ?? '')),
      'uom_id'       => (string)($line['uom_id'] ?? $component['uom_id'] ?? ''),
      'opening_qty'  => (string)($line['opening_qty'] ?? ''),
      'unit_cost'    => (string)($line['unit_cost'] ?? ''),
      'note'         => (string)($line['note'] ?? ''),
    ];
  }
}
$editBaseUrl  = site_url('production/component-openings');
$filterQuery  = [
  'date_from'     => $dateFrom,
  'date_to'       => $dateTo,
  'location_type' => $selectedLocationType,
  'division_id'   => $selectedDivisionId > 0 ? $selectedDivisionId : '',
  'per_page'      => $perPage,
  'q'             => $q,
];
$tabBaseQuery = $filterQuery;
if (!empty($editPayload['id'])) $tabBaseQuery['edit'] = (int)$editPayload['id'];
if (!empty($detailHeader['id'])) $tabBaseQuery['detail'] = (int)$detailHeader['id'];
$tabUrl = static function (string $tab, array $extra = []) use ($editBaseUrl, $tabBaseQuery): string {
  return $editBaseUrl . '?' . http_build_query(array_merge($tabBaseQuery, ['tab' => $tab], $extra));
};
$detailAdjustmentUrl = '';
$detailReopenUrl     = '';
if (!empty($detailHeader) && strtoupper((string)($detailHeader['status'] ?? '')) === 'POSTED') {
  $detailAdjustmentUrl = site_url('production/component-adjustments') . '?' . http_build_query([
    'adjustment_date' => (string)($detailHeader['opening_date'] ?? date('Y-m-d')),
    'location_type'   => (string)($detailHeader['location_type'] ?? ''),
    'division_id'     => (int)($detailHeader['division_id'] ?? 0),
    'notes'           => 'Koreksi kekurangan opening ' . (string)($detailHeader['opening_no'] ?? ''),
    'source_opening_no' => (string)($detailHeader['opening_no'] ?? ''),
  ]);
  $detailReopenUrl = site_url('production/component-openings/reopen/' . (int)($detailHeader['id'] ?? 0));
}

// — Summary computations —
$totalDocs   = count($rows);
$postedDocs  = 0; $draftDocs = 0; $voidDocs = 0;
$regulerDocs = 0; $eventDocs = 0;
foreach ($rows as $r) {
  $st = strtoupper((string)($r['status'] ?? ''));
  if ($st === 'POSTED')     $postedDocs++;
  elseif ($st === 'DRAFT')  $draftDocs++;
  elseif ($st === 'VOID')   $voidDocs++;
  $lt = strtoupper((string)($r['location_type'] ?? ''));
  if (in_array($lt, ['BAR', 'KITCHEN', 'ROASTERY'])) $regulerDocs++;
  else $eventDocs++;
}
$snapQty   = 0.0; $snapValue = 0.0;
$snapValueReguler = 0.0; $snapValueEvent = 0.0;
$divisionValues = []; $uniqueComponentIds = [];
foreach ($monthlyRows as $mr) {
  $snapQty   += (float)($mr['opening_qty'] ?? 0);
  $snapValue += (float)($mr['total_value'] ?? 0);
  $lt = strtoupper((string)($mr['location_type'] ?? ''));
  if (in_array($lt, ['BAR', 'KITCHEN', 'ROASTERY'])) $snapValueReguler += (float)($mr['total_value'] ?? 0);
  else $snapValueEvent += (float)($mr['total_value'] ?? 0);
  $dn = (string)($mr['division_name'] ?? '-');
  $divisionValues[$dn] = ($divisionValues[$dn] ?? 0.0) + (float)($mr['total_value'] ?? 0);
  $cid = (string)($mr['component_id'] ?? '');
  if ($cid !== '') $uniqueComponentIds[$cid] = true;
}
$uniqueComponents = count($uniqueComponentIds);
arsort($divisionValues);
$topDivisions = array_slice($divisionValues, 0, 4, true);
?>

<style>
.component-doc-table select,
.component-doc-table input { min-width: 88px; }
.component-doc-table .component-picker-input { min-width: 280px; }
.component-doc-table .component-uom-select { min-width: 170px; }
.component-doc-summary {
  background: #fbf8f3;
  border: 1px solid #eadfce;
  border-radius: 16px;
  padding: 1rem 1.1rem;
}
.component-doc-summary-value { font-size: 1.2rem; font-weight: 700; color: #3d342d; }
.component-add-line-btn {
  background: #f0b429; border-color: #f0b429; color: #3b2a00; font-weight: 700;
  box-shadow: 0 0.35rem 0.9rem rgba(240,180,41,0.28);
}
.component-add-line-btn:hover, .component-add-line-btn:focus { background:#d79a11;border-color:#d79a11;color:#2e2100; }
.component-edit-banner { background:#fff5d9;border:1px solid #f0d38a;border-radius:14px;padding:.85rem 1rem; }
.component-opening-tabs .nav-link { font-weight:600; }
.component-opening-detail-empty { border:1px dashed #d7c7c2;border-radius:14px;background:#fffaf7;padding:1rem 1.1rem; }
.component-bulk-card { border:1px solid #eadfce;border-radius:18px;background:linear-gradient(135deg,#fffaf3 0%,#f7efe4 100%);box-shadow:0 .75rem 1.8rem rgba(102,73,35,.08); }
.component-bulk-actions { display:flex;flex-wrap:wrap;gap:.5rem; }
.component-bulk-actions .btn { min-width:170px; }
.component-bulk-upload { background:rgba(255,255,255,.78);border:1px solid #eadfce;border-radius:16px;padding:1rem; }
.opening-summary-card { border:1px solid #e8e0d4;border-radius:12px;background:#fff;padding:.65rem .9rem; }
.opening-summary-card .lbl { font-size:.68rem;color:#888;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap; }
.opening-summary-card .val { font-size:1.05rem;font-weight:700;color:#2d2218;line-height:1.2;margin-top:.1rem; }
.opening-summary-card .sub { font-size:.68rem;color:#aaa;margin-top:.15rem; }
.opening-summary-card.highlight { border-color:#b5cca0;background:#f0f7ea; }
.opening-summary-card.highlight .val { color:#2d6a0a; }
.doc-aksi-cell { text-align:center; vertical-align:middle !important; }
.doc-aksi-wrap { display:flex; gap:6px; justify-content:center; align-items:center; flex-wrap:wrap; }
.doc-aksi-wrap .btn { width:38px; height:38px; padding:0; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; border-radius:10px; }
</style>

<div class="mb-3">
  <h4 class="mb-1">Opening Base/Prepare</h4>
  <small class="text-muted">Stok awal component per bulan. Tampilkan, filter, dan kelola dokumen opening — form input ada di modal Tambah Opening.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'opening']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-openings'),
  'component_type_filters'  => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'q' => $q],
  'component_type_active'   => '',
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter(['date_from' => $dateFrom], static fn($v) => $v !== ''),
]); ?>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success mb-3"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('warning')): ?>
  <div class="alert alert-warning mb-3"><?php echo html_escape((string)$this->session->flashdata('warning')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger mb-3"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
<?php endif; ?>

<div id="component-opening-alert" class="mb-3"></div>

<!-- Import / Export card -->
<div class="card component-bulk-card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h5 class="mb-1">Import / Export Opening Spreadsheet</h5>
        <small class="text-muted">Template mengikuti bulan opening dan lokasi. Upload hanya XLSX — sistem simpan dan langsung post memakai logika yang sama dengan proses manual.</small>
      </div>
      <div class="component-bulk-actions">
        <form method="get" action="<?php echo html_escape($componentOpeningExportUrl); ?>" id="component-opening-export-form" class="d-inline">
          <input type="hidden" name="month" value="<?php echo html_escape((string)($editPayload['opening_month'] ?? $month)); ?>">
          <input type="hidden" name="location_group" value="<?php echo html_escape((string)($editPayload['location_group'] ?? 'REGULER')); ?>">
          <button type="submit" class="btn btn-outline-secondary btn-sm">Export Template Excel</button>
        </form>
        <form method="get" action="<?php echo html_escape($componentOpeningExportExistingUrl); ?>" id="component-opening-export-existing-form" class="d-inline">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <input type="hidden" name="location_type" value="<?php echo html_escape($selectedLocationType); ?>">
          <input type="hidden" name="division_id" value="<?php echo (int)$selectedDivisionId; ?>">
          <input type="hidden" name="q" value="<?php echo html_escape($q); ?>">
          <button type="submit" class="btn btn-outline-dark btn-sm">Export Data Existing</button>
        </form>
      </div>
    </div>
    <div class="component-bulk-upload">
      <form method="post" action="<?php echo html_escape($componentOpeningImportUrl); ?>" enctype="multipart/form-data" class="row g-3 align-items-end" id="component-opening-import-form">
        <input type="hidden" name="month" value="<?php echo html_escape((string)($editPayload['opening_month'] ?? $month)); ?>">
        <input type="hidden" name="location_group" value="<?php echo html_escape((string)($editPayload['location_group'] ?? 'REGULER')); ?>">
        <div class="col-lg-9">
          <label class="form-label mb-1">File Import Excel</label>
          <input type="file" name="import_file" class="form-control" accept=".xlsx" required>
          <small class="text-muted">Kolom utama template: opening_month, location_group, component_code, uom_code, opening_qty, unit_cost.</small>
        </div>
        <div class="col-lg-3 d-grid">
          <button type="submit" class="btn btn-primary">Import dan Post</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Summary cards -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="opening-summary-card h-100">
      <div class="lbl">Total Dokumen</div>
      <div class="val"><?php echo number_format($totalDocs, 0, ',', '.'); ?></div>
      <div class="sub">
        <span class="text-warning fw-semibold"><?php echo $draftDocs; ?> Draft</span>
        &nbsp;·&nbsp;<span class="text-success fw-semibold"><?php echo $postedDocs; ?> Posted</span>
        <?php if ($voidDocs > 0): ?>&nbsp;·&nbsp;<span class="text-muted"><?php echo $voidDocs; ?> Void</span><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="opening-summary-card highlight h-100">
      <div class="lbl">Dokumen Posted</div>
      <div class="val"><?php echo number_format($postedDocs, 0, ',', '.'); ?></div>
      <div class="sub">dari <?php echo $totalDocs; ?> total dokumen</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="opening-summary-card h-100">
      <div class="lbl">Snapshot Qty</div>
      <div class="val"><?php echo number_format($snapQty, 0, ',', '.'); ?></div>
      <div class="sub"><?php echo $uniqueComponents; ?> component unik</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="opening-summary-card h-100">
      <div class="lbl">Snapshot Nilai</div>
      <div class="val" style="font-size:.85rem">Rp <?php echo number_format($snapValue, 0, ',', '.'); ?></div>
      <div class="sub">dari inv_component_monthly_opening</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="opening-summary-card h-100">
      <div class="lbl">Reguler / Event</div>
      <div class="val"><?php echo $regulerDocs; ?> / <?php echo $eventDocs; ?></div>
      <div class="sub">
        Rp <?php echo number_format($snapValueReguler, 0, ',', '.'); ?>&nbsp;/&nbsp;Rp <?php echo number_format($snapValueEvent, 0, ',', '.'); ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="opening-summary-card h-100">
      <div class="lbl">Top Divisi (Nilai)</div>
      <?php if (empty($topDivisions)): ?>
        <div class="val text-muted" style="font-size:.8rem">—</div>
      <?php else: ?>
        <?php foreach ($topDivisions as $dn => $dv): ?>
          <div style="display:flex;justify-content:space-between;font-size:.72rem;line-height:1.6">
            <span class="text-truncate me-1" style="max-width:75px"><?php echo html_escape($dn); ?></span>
            <span class="fw-semibold text-nowrap">Rp <?php echo number_format($dv, 0, ',', '.'); ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Main card: filter + tabs -->
<div class="card border-0 shadow-sm component-opening-tabs mb-3" id="component-opening-detail-tabs">
  <div class="card-header bg-transparent pb-0">
    <!-- Title row + Tambah button -->
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
      <div>
        <h5 class="mb-0">Daftar Opening</h5>
        <small class="text-muted"><?php echo html_escape($dateFrom); ?> s/d <?php echo html_escape($dateTo); ?> &mdash; <?php echo $totalDocs; ?> dokumen ditemukan</small>
      </div>
      <button type="button" class="btn btn-success btn-sm" id="btn-open-form-modal">
        <i class="ri ri-add-line me-1"></i>Tambah Opening
      </button>
    </div>

    <!-- Filter form -->
    <form class="row g-2 align-items-end mb-2" method="get" action="<?php echo site_url('production/component-openings'); ?>" id="opening-filter-form">
      <input type="hidden" name="tab" value="<?php echo html_escape($openingTab); ?>">
      <?php if (!empty($detailHeader['id'])): ?><input type="hidden" name="detail" value="<?php echo (int)$detailHeader['id']; ?>"><?php endif; ?>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Dari</label>
        <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo html_escape($dateFrom); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Sampai</label>
        <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo html_escape($dateTo); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Lokasi</label>
        <select class="form-select form-select-sm" name="location_type" style="min-width:100px">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape($key); ?>" <?php echo $selectedLocationType === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Divisi</label>
        <select class="form-select form-select-sm" name="division_id" style="min-width:100px">
          <option value="">Semua</option>
          <?php foreach ($divisions as $division): ?>
            <option value="<?php echo (int)$division['id']; ?>" <?php echo $selectedDivisionId === (int)$division['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)($division['name'] ?? '')); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Per Hal</label>
        <select class="form-select form-select-sm" name="per_page" style="min-width:70px">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Cari</label>
        <input type="text" class="form-control form-control-sm" name="q" id="opening-search" value="<?php echo html_escape($q); ?>" placeholder="No / divisi / catatan" style="min-width:160px">
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
        <a href="<?php echo site_url('production/component-openings'); ?>" class="btn btn-outline-danger btn-sm">Reset</a>
      </div>
    </form>

    <ul class="nav nav-tabs card-header-tabs mt-1">
      <li class="nav-item"><a class="nav-link <?php echo $openingTab === 'documents' ? 'active' : ''; ?>" href="<?php echo $tabUrl('documents'); ?>#component-opening-detail-tabs">Daftar Dokumen</a></li>
      <li class="nav-item"><a class="nav-link <?php echo $openingTab === 'detail' ? 'active' : ''; ?>" href="<?php echo $tabUrl('detail'); ?>#component-opening-detail-tabs">Rincian</a></li>
      <li class="nav-item"><a class="nav-link <?php echo $openingTab === 'snapshot' ? 'active' : ''; ?>" href="<?php echo $tabUrl('snapshot'); ?>#component-opening-detail-tabs">Snapshot Bulanan</a></li>
    </ul>
  </div>

  <div class="card-body tab-content p-0 px-3 py-3">
    <!-- ====== TAB: Daftar Dokumen ====== -->
    <div class="tab-pane fade <?php echo $openingTab === 'documents' ? 'show active' : ''; ?>">
      <div style="overflow:auto;max-height:70vh">
        <table class="table table-bordered table-hover table-sm align-middle mb-0" style="table-layout:fixed;min-width:900px" id="tbl-documents">
          <thead class="table-dark" style="position:sticky;top:0;z-index:2">
            <tr>
              <th style="width:210px;white-space:nowrap">No Opening</th>
              <th style="width:80px;white-space:nowrap">Bulan</th>
              <th style="width:76px;white-space:nowrap">Lokasi</th>
              <th style="width:110px;white-space:nowrap">Divisi</th>
              <th style="width:210px;white-space:nowrap">Catatan</th>
              <th style="width:72px;white-space:nowrap">Status</th>
              <th style="width:130px;white-space:nowrap">Aksi</th>
            </tr>
          </thead>
          <tbody id="opening-docs-tbody">
            <?php if (empty($rows)): ?>
              <tr class="opening-doc-row"><td colspan="7" class="text-center text-muted py-4">Belum ada dokumen opening pada filter ini.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $rowStatus = strtoupper((string)($row['status'] ?? ''));
                  $rowSearchStr = strtolower(implode(' ', [
                    $row['opening_no'] ?? '',
                    substr((string)($row['opening_date'] ?? ''), 0, 7),
                    $locationGroupLabel((string)($row['location_type'] ?? '')),
                    $row['division_name'] ?? '',
                    $row['notes'] ?? '',
                    $row['status'] ?? '',
                  ]));
                  $rowDetailUrl = $tabUrl('detail', ['detail' => (int)($row['id'] ?? 0)]) . '#component-opening-detail-tabs';
                  $rowEditUrl   = $editBaseUrl . '?' . http_build_query(array_merge($filterQuery, ['edit' => (int)($row['id'] ?? 0), 'detail' => (int)($row['id'] ?? 0), 'tab' => 'detail'])) . '#component-opening-form-card';
                ?>
                <tr class="opening-doc-row" id="component-opening-<?php echo (int)$row['id']; ?>" data-search="<?php echo html_escape($rowSearchStr); ?>">
                  <td style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($row['opening_no'] ?? '')); ?></td>
                  <td style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo html_escape(substr((string)($row['opening_date'] ?? ''), 0, 7)); ?></td>
                  <td style="font-size:.75rem;white-space:nowrap;overflow:hidden"><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></td>
                  <td style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                  <td style="font-size:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['notes'] ?? '')); ?>"><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                  <td><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></td>
                  <td class="doc-aksi-cell">
                    <?php if ($rowStatus === 'DRAFT'): ?>
                      <div class="doc-aksi-wrap">
                        <button type="button" class="btn btn-outline-primary btn-edit-draft" data-id="<?php echo (int)$row['id']; ?>" data-url="<?php echo html_escape($rowEditUrl); ?>" title="Edit Draft"><i class="ri ri-edit-line"></i></button>
                        <a href="<?php echo $rowDetailUrl; ?>" class="btn btn-outline-info" title="Rincian"><i class="ri ri-eye-line"></i></a>
                        <button type="button" class="btn btn-outline-success btn-post" data-id="<?php echo (int)$row['id']; ?>" title="Post"><i class="ri ri-checkbox-circle-line"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-del" data-id="<?php echo (int)$row['id']; ?>" title="Hapus"><i class="ri ri-delete-bin-line"></i></button>
                      </div>
                    <?php elseif ($rowStatus === 'POSTED'): ?>
                      <div class="doc-aksi-wrap">
                        <a href="<?php echo $rowDetailUrl; ?>" class="btn btn-outline-info" title="Rincian"><i class="ri ri-eye-line"></i></a>
                        <button type="button" class="btn btn-outline-primary btn-reopen" data-reopen-url="<?php echo site_url('production/component-openings/reopen/' . (int)($row['id'] ?? 0)); ?>" data-edit-url="<?php echo html_escape($rowEditUrl); ?>" title="Draftkan & Edit"><i class="ri ri-refresh-line"></i></button>
                        <button type="button" class="btn btn-outline-warning btn-void" data-id="<?php echo (int)$row['id']; ?>" title="Void"><i class="ri ri-close-circle-line"></i></button>
                      </div>
                    <?php else: ?>
                      <div class="doc-aksi-wrap">
                        <a href="<?php echo $rowDetailUrl; ?>" class="btn btn-outline-info" title="Rincian"><i class="ri ri-eye-line"></i></a>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
        <div id="opening-docs-info" class="text-muted" style="font-size:.78rem"></div>
        <div id="opening-docs-pager" class="d-flex gap-1 flex-wrap"></div>
      </div>
    </div>

    <!-- ====== TAB: Rincian ====== -->
    <div class="tab-pane fade <?php echo $openingTab === 'detail' ? 'show active' : ''; ?>">
      <?php if (empty($detailHeader)): ?>
        <div class="component-opening-detail-empty">
          <div class="fw-semibold mb-1">Belum ada dokumen yang dipilih</div>
          <small class="text-muted">Klik tombol rincian (<i class="ri ri-eye-line"></i>) pada daftar dokumen untuk melihat isi opening, movement, dan lot inbound.</small>
        </div>
      <?php else: ?>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h5 class="mb-1">Rincian <?php echo html_escape((string)($detailHeader['opening_no'] ?? '-')); ?></h5>
            <small class="text-muted">Bulan <?php echo html_escape(substr((string)($detailHeader['opening_date'] ?? ''), 0, 7)); ?> | <?php echo html_escape($locationGroupLabel((string)($detailHeader['location_type'] ?? ''))); ?> | <?php echo html_escape((string)($detailHeader['division_name'] ?? '-')); ?></small>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <?php if (strtoupper((string)($detailHeader['status'] ?? '')) === 'DRAFT'): ?>
              <button type="button" class="btn btn-outline-primary btn-sm btn-edit-draft" data-id="<?php echo (int)($detailHeader['id'] ?? 0); ?>" data-url="<?php echo html_escape($editBaseUrl . '?' . http_build_query(array_merge($filterQuery, ['edit' => (int)($detailHeader['id'] ?? 0), 'detail' => (int)($detailHeader['id'] ?? 0), 'tab' => 'detail']))); ?>">Edit Draft</button>
            <?php endif; ?>
            <?php if ($detailReopenUrl !== ''): ?>
              <button type="button" class="btn btn-outline-primary btn-sm btn-reopen" data-reopen-url="<?php echo $detailReopenUrl; ?>" data-edit-url="<?php echo html_escape($editBaseUrl . '?' . http_build_query(array_merge($filterQuery, ['edit' => (int)($detailHeader['id'] ?? 0), 'detail' => (int)($detailHeader['id'] ?? 0), 'tab' => 'detail']))); ?>">Draftkan & Edit</button>
            <?php endif; ?>
            <?php if ($detailAdjustmentUrl !== ''): ?>
              <a href="<?php echo $detailAdjustmentUrl; ?>" class="btn btn-outline-warning btn-sm">Buat Adjustment Koreksi</a>
            <?php endif; ?>
            <a href="<?php echo site_url('production/component-openings/detail/' . (int)($detailHeader['id'] ?? 0)); ?>" class="btn btn-outline-secondary btn-sm">Detail Penuh</a>
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6 col-md-3"><div class="opening-summary-card"><div class="lbl">Status</div><div class="val" style="font-size:.9rem"><?php echo html_escape((string)($detailHeader['status'] ?? 'DRAFT')); ?></div></div></div>
          <div class="col-6 col-md-3"><div class="opening-summary-card"><div class="lbl">Total Line</div><div class="val"><?php echo number_format((int)($detailSummary['line_count'] ?? 0), 0, ',', '.'); ?></div></div></div>
          <div class="col-6 col-md-3"><div class="opening-summary-card"><div class="lbl">Total Qty</div><div class="val"><?php echo number_format((float)($detailSummary['total_qty'] ?? 0), 2, ',', '.'); ?></div></div></div>
          <div class="col-6 col-md-3"><div class="opening-summary-card highlight"><div class="lbl">Total Nilai</div><div class="val" style="font-size:.85rem">Rp <?php echo number_format((float)($detailSummary['total_value'] ?? 0), 2, ',', '.'); ?></div></div></div>
        </div>

        <div style="overflow:auto;max-height:50vh" class="mb-3">
          <table class="table table-sm table-striped align-middle mb-0" style="table-layout:fixed;min-width:700px">
            <thead class="table-dark" style="position:sticky;top:0;z-index:2">
              <tr>
                <th style="width:48px">Line</th>
                <th>Component</th>
                <th style="width:65px">UOM</th>
                <th style="width:105px" class="text-end">Qty Opening</th>
                <th style="width:105px" class="text-end">Unit Cost</th>
                <th style="width:115px" class="text-end">Total Nilai</th>
                <th style="width:150px">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($detailLines)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Dokumen ini belum memiliki baris opening.</td></tr>
              <?php else: ?>
                <?php foreach ($detailLines as $line): ?>
                  <tr>
                    <td><?php echo (int)($line['line_no'] ?? 0); ?></td>
                    <td>
                      <div style="font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($line['component_name'] ?? '-')); ?></div>
                      <small class="text-muted"><?php echo html_escape((string)($line['component_code'] ?? '')); ?></small>
                    </td>
                    <td style="font-size:.78rem"><?php echo html_escape((string)($line['uom_code'] ?? '-')); ?></td>
                    <td class="text-end" style="font-size:.78rem"><?php echo number_format((float)($line['opening_qty'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end" style="font-size:.78rem"><?php echo number_format((float)($line['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end" style="font-size:.78rem"><?php echo number_format(((float)($line['opening_qty'] ?? 0) * (float)($line['unit_cost'] ?? 0)), 2, ',', '.'); ?></td>
                    <td style="font-size:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($line['note'] ?? '-')); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card border-0 bg-light h-100">
              <div class="card-body">
                <ul class="nav nav-tabs nav-sm mb-2" role="tablist">
                  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#detail-opening-effective" type="button">Posisi Efektif</button></li>
                  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detail-opening-audit" type="button">Histori Audit</button></li>
                </ul>
                <div class="tab-content">
                  <div class="tab-pane fade show active" id="detail-opening-effective">
                    <?php if (strtoupper((string)($detailHeader['status'] ?? '')) !== 'POSTED'): ?>
                      <div class="small text-muted">Dokumen masih <?php echo html_escape((string)($detailHeader['status'] ?? 'DRAFT')); ?>. Edit draft tidak membuat movement baru di ledger.</div>
                    <?php elseif (empty($detailEffectiveMovementRows)): ?>
                      <small class="text-muted">Belum ada posisi efektif yang aktif dari opening ini.</small>
                    <?php else: ?>
                      <div class="alert alert-light border small py-2 px-3 mb-2">Tab ini hanya menampilkan kontribusi stok yang masih efektif.</div>
                      <div class="table-responsive">
                        <table class="table table-sm mb-0">
                          <thead><tr><th>Component</th><th class="text-end">Qty Efektif</th><th class="text-end">Unit Cost</th><th class="text-end">Lot Aktif</th></tr></thead>
                          <tbody>
                            <?php foreach ($detailEffectiveMovementRows as $effectiveRow): ?>
                              <?php
                                $lotCount = 0;
                                foreach ($detailActiveLotRows as $lotRow) {
                                  if ((int)($lotRow['component_id'] ?? 0) === (int)($effectiveRow['component_id'] ?? 0) && (int)($lotRow['uom_id'] ?? 0) === (int)($effectiveRow['uom_id'] ?? 0)) $lotCount++;
                                }
                              ?>
                              <tr>
                                <td><div><?php echo html_escape((string)($effectiveRow['component_name'] ?? '-')); ?></div><small class="text-muted"><?php echo html_escape((string)($effectiveRow['uom_code'] ?? '-')); ?></small></td>
                                <td class="text-end"><?php echo number_format((float)($effectiveRow['effective_qty'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)($effectiveRow['latest_unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($lotCount, 0, ',', '.'); ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="tab-pane fade" id="detail-opening-audit">
                    <?php if (empty($detailMovementRows)): ?>
                      <small class="text-muted">Belum ada movement audit yang tercatat dari dokumen ini.</small>
                    <?php else: ?>
                      <div class="alert alert-light border small py-2 px-3 mb-2">Histori audit — terbuat jika opening pernah diposting, dibuka ulang, di-void, lalu diposting lagi.</div>
                      <div class="table-responsive">
                        <table class="table table-sm mb-0">
                          <thead><tr><th>No</th><th>Jenis</th><th class="text-end">Qty In</th><th class="text-end">Qty Out</th></tr></thead>
                          <tbody>
                            <?php foreach ($detailMovementRows as $movementRow): ?>
                              <tr>
                                <td><?php echo html_escape((string)($movementRow['movement_no'] ?? '-')); ?></td>
                                <td><?php echo html_escape((string)($movementRow['movement_type_label'] ?? $movementRow['movement_type'] ?? '-')); ?></td>
                                <td class="text-end"><?php echo number_format((float)($movementRow['qty_in'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)($movementRow['qty_out'] ?? 0), 2, ',', '.'); ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card border-0 bg-light h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Lot Inbound</h6>
                  <span class="badge text-bg-light border"><?php echo number_format(count($detailLotRows), 0, ',', '.'); ?></span>
                </div>
                <?php if (empty($detailLotRows)): ?>
                  <small class="text-muted">Belum ada lot inbound dari dokumen ini.</small>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>Lot</th><th>Component</th><th class="text-end">Qty In</th><th class="text-end">Saldo</th></tr></thead>
                      <tbody>
                        <?php foreach ($detailLotRows as $lotRow): ?>
                          <tr>
                            <td><?php echo html_escape((string)($lotRow['lot_no'] ?? '-')); ?></td>
                            <td><?php echo html_escape((string)($lotRow['component_name'] ?? '-')); ?></td>
                            <td class="text-end"><?php echo number_format((float)($lotRow['qty_in'] ?? 0), 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format((float)($lotRow['qty_balance'] ?? 0), 2, ',', '.'); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ====== TAB: Snapshot Bulanan ====== -->
    <div class="tab-pane fade <?php echo $openingTab === 'snapshot' ? 'show active' : ''; ?>">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h6 class="mb-0">Snapshot Opening Bulanan</h6>
          <small class="text-muted">Basis opening awal bulan dari carry-forward. Bulan: <?php echo html_escape($month); ?></small>
        </div>
        <span class="badge text-bg-light border"><?php echo number_format(count($monthlyRows), 0, ',', '.'); ?> baris</span>
      </div>
      <div style="overflow:auto;max-height:70vh">
        <table class="table table-bordered table-hover table-sm align-middle mb-0" style="table-layout:fixed;min-width:900px">
          <thead class="table-dark" style="position:sticky;top:0;z-index:2">
            <tr>
              <th style="width:75px;white-space:nowrap">Bulan</th>
              <th style="width:72px;white-space:nowrap">Lokasi</th>
              <th style="width:100px;white-space:nowrap">Divisi</th>
              <th style="width:165px;white-space:nowrap">Component</th>
              <th style="width:58px;white-space:nowrap">UOM</th>
              <th style="width:105px;white-space:nowrap" class="text-end">Qty Opening</th>
              <th style="width:105px;white-space:nowrap" class="text-end">HPP Live</th>
              <th style="width:115px;white-space:nowrap" class="text-end">Total Nilai</th>
              <th style="width:80px;white-space:nowrap">Source</th>
            </tr>
          </thead>
          <tbody id="opening-snap-tbody">
            <?php if (empty($monthlyRows)): ?>
              <tr class="opening-snap-row"><td colspan="9" class="text-center text-muted py-4">Belum ada snapshot monthly opening untuk filter ini.</td></tr>
            <?php else: ?>
              <?php foreach ($monthlyRows as $monthlyRow): ?>
                <tr class="opening-snap-row" data-search="<?php echo html_escape(strtolower(implode(' ', [
                  $monthlyRow['month_key'] ?? '',
                  $locationGroupLabel((string)($monthlyRow['location_type'] ?? '')),
                  $monthlyRow['division_name'] ?? '',
                  $monthlyRow['component_name'] ?? '',
                  $monthlyRow['uom_code'] ?? '',
                ]))); ?>">
                  <td style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo html_escape((string)($monthlyRow['month_key'] ?? '')); ?></td>
                  <td style="font-size:.75rem;white-space:nowrap;overflow:hidden"><?php echo html_escape($locationGroupLabel((string)($monthlyRow['location_type'] ?? ''))); ?></td>
                  <td style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($monthlyRow['division_name'] ?? '-')); ?>"><?php echo html_escape((string)($monthlyRow['division_name'] ?? '-')); ?></td>
                  <td style="overflow:hidden">
                    <div style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($monthlyRow['component_name'] ?? '')); ?>"><?php echo html_escape((string)($monthlyRow['component_name'] ?? '')); ?></div>
                    <div class="text-muted" style="font-size:.68rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($monthlyRow['component_type'] ?? '')); ?></div>
                  </td>
                  <td style="font-size:.75rem;white-space:nowrap;overflow:hidden"><?php echo html_escape((string)($monthlyRow['uom_code'] ?? '')); ?></td>
                  <td class="text-end" style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($monthlyRow['opening_qty'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end" style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($monthlyRow['hpp_live'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end" style="font-size:.78rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($monthlyRow['total_value'] ?? 0), 2, ',', '.'); ?></td>
                  <td style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($monthlyRow['source_month'] ?? '-')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
        <div id="opening-snap-info" class="text-muted" style="font-size:.78rem"></div>
        <div id="opening-snap-pager" class="d-flex gap-1 flex-wrap"></div>
      </div>
    </div>
  </div><!-- /card-body -->
</div><!-- /main card -->

<!-- Carry-Forward card -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center py-2" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#carryForwardBody">
    <span class="fw-semibold">Carry-Forward Bulanan</span>
    <i class="ri ri-arrow-down-s-line"></i>
  </div>
  <div class="collapse" id="carryForwardBody">
    <div class="card-body">
      <small class="text-muted d-block mb-3">Generate opname penutup bulan terpilih dari proyeksi harian berbasis movement, lalu otomatis buat opening bulan berikutnya.</small>
      <div class="row g-2 mb-3">
        <div class="col-md-4">
          <label class="form-label">Bulan Snapshot</label>
          <input type="month" class="form-control" id="monthly-month" value="<?php echo html_escape($month); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Lokasi</label>
          <select class="form-select" id="monthly-location-type">
            <?php foreach ($locationFilterOptions as $key => $label): ?>
              <option value="<?php echo html_escape($key); ?>" <?php echo $selectedLocationType === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Divisi</label>
          <select class="form-select" id="monthly-division-id">
            <option value="">Semua divisi</option>
            <?php foreach ($divisions as $division): ?>
              <option value="<?php echo (int)$division['id']; ?>" <?php echo $selectedDivisionId === (int)$division['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)($division['code'] ?? '')); ?><?php echo !empty($division['code']) ? ' - ' : ''; ?><?php echo html_escape((string)($division['name'] ?? '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="opening-summary-card d-inline-flex gap-4 mb-3">
        <div><div class="lbl">Baris Snapshot</div><div class="val"><?php echo number_format(count($monthlyRows), 0, ',', '.'); ?></div></div>
        <div><div class="lbl">Qty Opening</div><div class="val"><?php echo number_format($snapQty, 2, ',', '.'); ?></div></div>
        <div><div class="lbl">Nilai</div><div class="val" style="font-size:.85rem">Rp <?php echo number_format($snapValue, 2, ',', '.'); ?></div></div>
      </div>
      <div>
        <button
          type="button"
          class="btn btn-outline-danger js-component-generate-trigger"
          id="btn-generate-monthly-opening"
          data-month="<?php echo html_escape($month); ?>"
          data-location-type="<?php echo html_escape($selectedLocationType); ?>"
          data-division-id="<?php echo html_escape($selectedDivisionId > 0 ? (string)$selectedDivisionId : ''); ?>"
          data-bs-toggle="modal"
          data-bs-target="#componentGenerateModal">Generate Opname + Opening</button>
      </div>
    </div>
  </div>
</div>

<!-- ====== MODAL: Form Opening ====== -->
<div class="modal fade" id="componentOpeningFormModal" tabindex="-1" aria-labelledby="componentOpeningFormModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="componentOpeningFormModalLabel">
          <?php echo !empty($editPayload) ? 'Edit Draft Opening — ' . html_escape((string)($editHeader['opening_no'] ?? '')) : 'Tambah Opening'; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (!empty($editPayload)): ?>
          <div class="component-edit-banner mb-3">
            <div class="fw-semibold mb-1">Sedang edit dokumen <?php echo html_escape((string)($editHeader['opening_no'] ?? '-')); ?></div>
            <small class="text-muted">Perubahan akan menyimpan ulang dokumen yang sama. Jika kurang, tambahkan baris sebelum di-posting.</small>
          </div>
        <?php endif; ?>

        <form id="frmOpening" autocomplete="off">
          <input type="hidden" name="id" value="<?php echo (int)($editPayload['id'] ?? 0); ?>">
          <input type="hidden" name="opening_no" value="<?php echo html_escape((string)($editPayload['opening_no'] ?? '')); ?>">
          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <label class="form-label">Bulan Opening</label>
              <input type="month" class="form-control" name="opening_month" value="<?php echo html_escape((string)($editPayload['opening_month'] ?? date('Y-m'))); ?>" required>
              <div class="form-text">Posting otomatis memakai tanggal 1 bulan yang dipilih.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Divisi</label>
              <input type="hidden" name="division_id" id="opening-division-id" value="">
              <input type="text" class="form-control" id="opening-division-name" value="Ikuti component" readonly>
              <div class="form-text" id="opening-division-help">Divisi otomatis mengikuti component.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Lokasi</label>
              <input type="hidden" name="location_type" id="opening-location-type" value="">
              <select class="form-select" id="opening-location-group" required>
                <option value="">Pilih lokasi...</option>
                <option value="REGULER" <?php echo (($editPayload['location_group'] ?? '') === 'REGULER') ? 'selected' : ''; ?>>Reguler</option>
                <option value="EVENT" <?php echo (($editPayload['location_group'] ?? '') === 'EVENT') ? 'selected' : ''; ?>>Event</option>
              </select>
              <div class="form-text" id="opening-location-help">Pilih component dulu agar lokasi diturunkan otomatis.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Catatan Header</label>
              <input type="text" class="form-control" name="notes" value="<?php echo html_escape((string)($editPayload['notes'] ?? '')); ?>" placeholder="Contoh: opening awal bulan">
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">Baris Opening</span>
            <button type="button" class="btn btn-sm component-add-line-btn" id="btn-add-opening-line">+ Tambah Baris</button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle component-doc-table mb-2">
              <thead>
                <tr>
                  <th style="width:34px">#</th>
                  <th style="width:330px">Component</th>
                  <th style="width:190px">UOM</th>
                  <th style="width:130px" class="text-end">Qty Opening</th>
                  <th style="width:150px" class="text-end">Unit Cost</th>
                  <th>Catatan</th>
                  <th style="width:52px"></th>
                </tr>
              </thead>
              <tbody id="opening-line-body"></tbody>
            </table>
          </div>

          <div class="component-doc-summary d-flex flex-wrap gap-4 mt-3">
            <div><div class="small text-muted">Total Baris</div><div class="component-doc-summary-value" id="opening-total-lines">0</div></div>
            <div><div class="small text-muted">Total Qty</div><div class="component-doc-summary-value" id="opening-total-qty">0,00</div></div>
            <div><div class="small text-muted">Total Nilai</div><div class="component-doc-summary-value" id="opening-total-value">Rp 0</div></div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary"><?php echo !empty($editPayload) ? 'Update DRAFT' : 'Simpan DRAFT'; ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const uomOptions     = <?php echo json_encode(array_values(array_map(static function ($uom) {
      return ['id' => (int)($uom['id'] ?? 0), 'label' => trim((string)($uom['name'] ?? ''))];
  }, $uoms)), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const editingOpening = <?php echo json_encode($editPayload, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const saveUrl        = '<?php echo site_url('production/component-openings/save'); ?>';
  const postBaseUrl    = '<?php echo site_url('production/component-openings/post'); ?>';
  const deleteBaseUrl  = '<?php echo site_url('production/component-openings/delete'); ?>';
  const voidBaseUrl    = '<?php echo site_url('production/component-openings/void'); ?>';
  const autoOpenModal  = <?php echo json_encode(!empty($editPayload)); ?>;
  let perPage          = <?php echo (int)$perPage; ?>;

  const alertHost      = document.getElementById('component-opening-alert');
  const lineBody       = document.getElementById('opening-line-body');
  const form           = document.getElementById('frmOpening');
  const divisionIdInput     = document.getElementById('opening-division-id');
  const divisionNameInput   = document.getElementById('opening-division-name');
  const divisionHelp        = document.getElementById('opening-division-help');
  const locationGroupInput  = document.getElementById('opening-location-group');
  const locationTypeInput   = document.getElementById('opening-location-type');
  const locationHelp        = document.getElementById('opening-location-help');
  const openingImportForm        = document.getElementById('component-opening-import-form');
  const openingExportForm        = document.getElementById('component-opening-export-form');
  const openingExportExistingForm = document.getElementById('component-opening-export-existing-form');
  const formModal = document.getElementById('componentOpeningFormModal');
  let lines = Array.isArray(editingOpening?.lines) ? editingOpening.lines.slice() : [];

  // — Pagination helpers —
  function buildPager(pagerId, infoId, total, page, onClick) {
    const pager = document.getElementById(pagerId);
    const info  = document.getElementById(infoId);
    if (!pager) return;
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    if (info) {
      const s = (page - 1) * perPage + 1;
      const e = Math.min(page * perPage, total);
      info.textContent = total > 0 ? ('Menampilkan ' + s + '–' + e + ' dari ' + total + ' baris') : 'Tidak ada data';
    }
    if (totalPages <= 1) { pager.innerHTML = ''; return; }
    const pages = [];
    pages.push(1);
    for (let p = Math.max(2, page - 1); p <= Math.min(totalPages - 1, page + 1); p++) pages.push(p);
    if (totalPages > 1) pages.push(totalPages);
    let html = '';
    let last = 0;
    for (const p of [...new Set(pages)]) {
      if (last > 0 && p - last > 1) html += '<span class="btn btn-sm btn-light disabled px-2">…</span>';
      html += '<button class="btn btn-sm ' + (p === page ? 'btn-dark' : 'btn-outline-secondary') + ' px-2" data-p="' + p + '">' + p + '</button>';
      last = p;
    }
    pager.innerHTML = html;
    pager.querySelectorAll('button[data-p]').forEach(btn => btn.addEventListener('click', () => onClick(+btn.dataset.p)));
  }

  function applyFilter(tbodyId, rowClass, infoId, pagerId, searchVal, page) {
    const rows    = Array.from(document.querySelectorAll('#' + tbodyId + ' tr.' + rowClass));
    const needle  = searchVal.trim().toLowerCase();
    const visible = rows.filter(r => !needle || (r.dataset.search || '').includes(needle));
    rows.forEach(r => { r.style.display = 'none'; });
    const start = (page - 1) * perPage;
    visible.forEach((r, i) => { r.style.display = (i >= start && i < start + perPage) ? '' : 'none'; });
    buildPager(pagerId, infoId, visible.length, page, p => applyFilter(tbodyId, rowClass, infoId, pagerId, searchVal, p));
  }

  // init both tables
  const searchInput = document.getElementById('opening-search');
  function initDocs() {
    applyFilter('opening-docs-tbody', 'opening-doc-row', 'opening-docs-info', 'opening-docs-pager', searchInput ? searchInput.value : '', 1);
  }
  function initSnap() {
    applyFilter('opening-snap-tbody', 'opening-snap-row', 'opening-snap-info', 'opening-snap-pager', searchInput ? searchInput.value : '', 1);
  }
  searchInput?.addEventListener('input', () => { initDocs(); initSnap(); });
  initDocs();
  initSnap();

  // — Utility —
  function escapeHtml(value) {
    return String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function renderAlert(type, message) {
    if (!alertHost) return;
    alertHost.innerHTML = '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>';
  }
  function renderConflictAlert(message, conflict) {
    if (!alertHost) return;
    const actions = [];
    if (conflict?.edit_url && !conflict?.reopen_url) actions.push('<a href="' + escapeHtml(conflict.edit_url) + '" class="btn btn-sm btn-outline-primary">Buka Draft Existing</a>');
    if (conflict?.reopen_url) actions.push('<button type="button" class="btn btn-sm btn-outline-primary js-reopen-opening" data-reopen-url="' + escapeHtml(conflict.reopen_url) + '" data-edit-url="' + escapeHtml(conflict.edit_url || '') + '">Draftkan & Edit</button>');
    if (conflict?.detail_url) actions.push('<a href="' + escapeHtml(conflict.detail_url) + '" class="btn btn-sm btn-outline-secondary">Lihat Rincian</a>');
    if (conflict?.adjustment_url) actions.push('<a href="' + escapeHtml(conflict.adjustment_url) + '" class="btn btn-sm btn-outline-warning">Buat Adjustment Koreksi</a>');
    alertHost.innerHTML = '<div class="alert alert-warning mb-0"><div class="fw-semibold mb-1">' + escapeHtml(message) + '</div>' + (conflict?.opening_no ? '<div class="small text-muted mb-2">Dokumen terkait: ' + escapeHtml(conflict.opening_no) + ' (' + escapeHtml(conflict.status || '') + ')</div>' : '') + (actions.length ? '<div class="d-flex flex-wrap gap-2">' + actions.join('') + '</div>' : '') + '</div>';
  }
  async function postJson(url, payload) {
    const response = await fetch(url, { method: 'POST', headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify(payload) });
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch { throw new Error('Respons server bukan JSON valid.'); }
    if (!response.ok || !json.ok) { const e = new Error(json.message || 'Permintaan gagal.'); e.payload = json; throw e; }
    return json;
  }
  function uiConfirm(message, options) {
    if (window.FinanceUI?.confirm) return window.FinanceUI.confirm(message, options || {});
    if (window.FinanceUI?.alert) return window.FinanceUI.alert('Modal konfirmasi tidak tersedia.', {title:'UI Belum Siap'}).then(() => false);
    return Promise.resolve(false);
  }
  function setButtonBusy(btn, label) {
    if (!btn) return;
    if (window.FinanceUI?.setButtonLoading) { window.FinanceUI.setButtonLoading(btn, label); return; }
    btn.disabled = true;
  }
  function clearButtonBusy(btn) {
    if (!btn) return;
    if (window.FinanceUI?.clearButtonLoading) { window.FinanceUI.clearButtonLoading(btn); return; }
    btn.disabled = false;
  }

  // — Modal open/close —
  function openFormModal() {
    if (!formModal) return;
    bootstrap.Modal.getOrCreateInstance(formModal).show();
  }
  document.getElementById('btn-open-form-modal')?.addEventListener('click', openFormModal);
  if (autoOpenModal && formModal) {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => bootstrap.Modal.getOrCreateInstance(formModal).show(), 150);
    });
  }
  // Edit draft: navigate to ?edit=ID (page reload, modal auto-opens)
  document.querySelectorAll('.btn-edit-draft').forEach(btn => {
    btn.addEventListener('click', () => { window.location.href = btn.dataset.url || '#'; });
  });

  // — Form line management —
  function blankLine() {
    return { component_id:'', component_label:'', component_division_id:'', component_division_code:'', component_division_name:'', uom_id:'', opening_qty:'', unit_cost:'', note:'' };
  }
  function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value||0);
  }
  function renderSummary() {
    const validLines = lines.filter(l => Number(l.component_id) > 0 && Number(l.uom_id) > 0);
    const totalQty   = validLines.reduce((s,l) => s + (parseFloat(l.opening_qty)||0), 0);
    const totalValue = validLines.reduce((s,l) => s + ((parseFloat(l.opening_qty)||0)*(parseFloat(l.unit_cost)||0)), 0);
    document.getElementById('opening-total-lines').textContent = String(validLines.length);
    document.getElementById('opening-total-qty').textContent   = totalQty.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('opening-total-value').textContent = formatCurrency(totalValue);
  }
  function uomSelectOptions(sel) {
    return ['<option value="">Pilih UOM...</option>', ...uomOptions.map(u => '<option value="'+u.id+'"'+(String(sel)===String(u.id)?' selected':'')+'>'+escapeHtml(u.label)+'</option>')].join('');
  }
  function componentPickerLabel(row) { return String(row.name||row.code||''); }
  function componentPickerSubLabel(row) { return [row.entity_type||'',row.division_name||row.division_code||'',row.uom_name||row.uom_code||''].filter(Boolean).join(' | '); }
  function resolveLocationType(divisionCode, locationGroup) {
    const nd = String(divisionCode||'').trim().toUpperCase();
    const ng = String(locationGroup||'').trim().toUpperCase();
    if (!nd||!ng) return '';
    if (nd==='BAR')     return ng==='EVENT'?'BAR_EVENT':'BAR';
    if (nd==='KITCHEN') return ng==='EVENT'?'KITCHEN_EVENT':'KITCHEN';
    if (nd==='ROASTERY') return ng==='EVENT'?'ROASTERY_EVENT':'ROASTERY';
    return '';
  }
  function syncHeaderDivisionState() {
    const activeLine = lines.find(l => Number(l.component_id)>0 && Number(l.component_division_id)>0);
    const divisionId   = String(activeLine?.component_division_id||'');
    const divisionCode = String(activeLine?.component_division_code||'').trim();
    const divisionName = String(activeLine?.component_division_name||'').trim();
    divisionIdInput.value   = divisionId;
    divisionNameInput.value = divisionId ? [divisionCode,divisionName].filter(Boolean).join(' - ') : 'Ikuti component';
    divisionHelp.textContent = divisionId ? 'Semua component dibatasi ke divisi yang sama.' : 'Divisi otomatis mengikuti component.';
    locationTypeInput.value  = resolveLocationType(divisionCode, locationGroupInput?.value||'');
    locationHelp.textContent = divisionId
      ? (locationTypeInput.value ? 'Lokasi: '+locationTypeInput.value : 'Pilih Reguler atau Event.')
      : 'Pilih component dulu agar lokasi diturunkan otomatis.';
    syncSpreadsheetForms();
  }
  function syncSpreadsheetForms() {
    const openingMonthValue  = String(form?.querySelector('input[name="opening_month"]')?.value||'<?php echo html_escape($month); ?>');
    const locationGroupValue = String(locationGroupInput?.value||'REGULER');
    [openingImportForm, openingExportForm].forEach(f => {
      if (!f) return;
      const mi = f.querySelector('input[name="month"]'); if (mi) mi.value = openingMonthValue;
      const li = f.querySelector('input[name="location_group"]'); if (li) li.value = locationGroupValue||'REGULER';
    });
    if (openingExportExistingForm) {
      const mi = openingExportExistingForm.querySelector('input[name="month"]'); if (mi) mi.value = openingMonthValue;
      const li = openingExportExistingForm.querySelector('input[name="location_type"]'); if (li) li.value = locationGroupValue||'REGULER';
    }
  }
  function bindComponentPickers() {
    lineBody.querySelectorAll('.component-picker-input').forEach(input => {
      window.ProductionAjaxPicker.bind(input, {
        entity: 'COMPONENT',
        params: () => { const d = String(divisionIdInput?.value||''); return d ? {division_id:d} : {}; },
        renderLabel: componentPickerLabel,
        renderSubLabel: componentPickerSubLabel,
        onType: (value, currentInput) => {
          const row = currentInput.closest('tr');
          const index = Number(row?.dataset.index||-1);
          if (index<0) return;
          lines[index].component_label = value; lines[index].component_id = ''; lines[index].component_division_id = ''; lines[index].component_division_code = ''; lines[index].component_division_name = ''; lines[index].uom_id = '';
          const uomSelect = row.querySelector('[data-field="uom_id"]'); if (uomSelect) uomSelect.value = '';
          syncHeaderDivisionState(); renderSummary();
        },
        onSelect: (result, currentInput) => {
          const row = currentInput.closest('tr');
          const index = Number(row?.dataset.index||-1);
          if (index<0) return;
          lines[index].component_id = String(result.id||''); lines[index].component_label = componentPickerLabel(result); lines[index].component_division_id = String(result.operational_division_id||''); lines[index].component_division_code = String(result.division_code||''); lines[index].component_division_name = String(result.division_name||''); lines[index].uom_id = String(result.uom_id||'');
          syncHeaderDivisionState(); renderLines();
        }
      });
    });
  }
  function renderLines() {
    if (!lineBody) return;
    if (!lines.length) lines = [blankLine()];
    lineBody.innerHTML = lines.map((line, index) => '<tr data-index="'+index+'"><td class="text-muted">'+(index+1)+'</td><td><input type="text" class="form-control form-control-sm component-picker-input" value="'+escapeHtml(line.component_label||'')+'" placeholder="Ketik nama component..."'+(Number(line.component_id)>0?' data-selected-label="'+escapeHtml(line.component_label||'')+'"':'')+' ></td><td><select class="form-select form-select-sm component-uom-select" data-field="uom_id">'+uomSelectOptions(line.uom_id)+'</select></td><td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="opening_qty" value="'+escapeHtml(line.opening_qty)+'"></td><td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="unit_cost" value="'+escapeHtml(line.unit_cost)+'"></td><td><input type="text" class="form-control form-control-sm" data-field="note" value="'+escapeHtml(line.note)+'" placeholder="Opsional"></td><td><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove">×</button></td></tr>').join('');
    bindComponentPickers();
    renderSummary();
  }
  function serializeLines() {
    return lines.filter(l => Number(l.component_id)>0 && Number(l.uom_id)>0 && (parseFloat(l.opening_qty)||0)>0)
      .map(l => ({component_id:Number(l.component_id), uom_id:Number(l.uom_id), opening_qty:parseFloat(l.opening_qty)||0, unit_cost:parseFloat(l.unit_cost)||0, note:String(l.note||'')}));
  }

  document.getElementById('btn-add-opening-line')?.addEventListener('click', () => { lines.push(blankLine()); renderLines(); });
  form?.querySelector('input[name="opening_month"]')?.addEventListener('change', syncSpreadsheetForms);
  form?.querySelector('input[name="opening_month"]')?.addEventListener('input', syncSpreadsheetForms);
  locationGroupInput?.addEventListener('change', () => { syncHeaderDivisionState(); syncSpreadsheetForms(); });
  openingImportForm?.addEventListener('submit', syncSpreadsheetForms);
  openingExportForm?.addEventListener('submit', syncSpreadsheetForms);
  openingExportExistingForm?.addEventListener('submit', syncSpreadsheetForms);

  lineBody?.addEventListener('click', event => {
    const btn = event.target.closest('button[data-action="remove"]');
    if (!btn) return;
    const row = btn.closest('tr');
    const index = Number(row?.dataset.index||-1);
    if (index<0) return;
    lines.splice(index, 1); renderLines();
  });
  lineBody?.addEventListener('change', event => {
    const field = event.target.getAttribute('data-field');
    const row = event.target.closest('tr');
    const index = Number(row?.dataset.index||-1);
    if (index<0||!field) return;
    lines[index][field] = event.target.value; renderSummary();
  });
  lineBody?.addEventListener('input', event => {
    const field = event.target.getAttribute('data-field');
    const row = event.target.closest('tr');
    const index = Number(row?.dataset.index||-1);
    if (index<0||!field) return;
    lines[index][field] = event.target.value; renderSummary();
  });

  // — Form submit —
  form?.addEventListener('submit', async event => {
    event.preventDefault();
    const submitBtn = event.submitter || form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    const payload = {
      id: String(formData.get('id')||''), opening_no: String(formData.get('opening_no')||''),
      opening_month: String(formData.get('opening_month')||''), location_type: String(formData.get('location_type')||''),
      division_id: String(formData.get('division_id')||''), notes: String(formData.get('notes')||''),
      lines: serializeLines()
    };
    if (!payload.lines.length) { syncHeaderDivisionState(); renderAlert('warning','Tambahkan minimal satu baris opening yang valid.'); return; }
    if (!payload.division_id) { renderAlert('warning','Pilih minimal satu component agar divisi opening bisa ditentukan.'); return; }
    if (!payload.location_type) { renderAlert('warning','Pilih lokasi Reguler atau Event terlebih dahulu.'); return; }
    if (!payload.opening_month) { renderAlert('warning','Pilih bulan opening terlebih dahulu.'); return; }
    setButtonBusy(submitBtn, 'Menyimpan draft...');
    try {
      await postJson(saveUrl, payload);
      window.location.reload();
    } catch (error) {
      if (error?.payload?.conflict) renderConflictAlert(error.message||'Gagal menyimpan opening.', error.payload.conflict);
      else renderAlert('danger', error.message||'Gagal menyimpan opening.');
      clearButtonBusy(submitBtn);
    }
  });

  // — Post / Delete / Void / Reopen —
  async function reopenOpening(btn) {
    const reopenUrl = btn?.dataset?.reopenUrl||'';
    if (!reopenUrl) return;
    btn.blur();
    if (!(await uiConfirm('Opening posted ini akan dibalik dari ledger dan lot, lalu kembali menjadi DRAFT.',{title:'Draftkan Ulang Opening',okText:'Draftkan & Edit',cancelText:'Batal'}))) return;
    setButtonBusy(btn,'Drafting...');
    try {
      const result = await postJson(reopenUrl,{});
      window.location.href = result.edit_url || btn.dataset.editUrl || window.location.href;
    } catch (error) { renderAlert('danger',error.message||'Gagal membuka kembali opening ke draft.'); clearButtonBusy(btn); }
  }

  document.querySelectorAll('.btn-post').forEach(btn => btn.addEventListener('click', async () => {
    btn.blur();
    if (!(await uiConfirm('Posting opening akan menulis ledger dan saldo component.',{title:'Post Dokumen Opening',okText:'Post Opening',cancelText:'Batal'}))) return;
    setButtonBusy(btn,'Posting...');
    try { await postJson(postBaseUrl+'/'+btn.dataset.id,{}); window.location.reload(); }
    catch (error) { renderAlert('danger',error.message||'Gagal post opening.'); clearButtonBusy(btn); }
  }));

  document.querySelectorAll('.btn-del').forEach(btn => btn.addEventListener('click', async () => {
    btn.blur();
    if (!(await uiConfirm('Draft opening ini akan dihapus permanen.',{title:'Hapus Draft Opening',okText:'Hapus Draft',cancelText:'Batal'}))) return;
    setButtonBusy(btn,'Menghapus...');
    try { await postJson(deleteBaseUrl+'/'+btn.dataset.id,{}); window.location.reload(); }
    catch (error) { renderAlert('danger',error.message||'Gagal menghapus opening.'); clearButtonBusy(btn); }
  }));

  document.querySelectorAll('.btn-reopen').forEach(btn => btn.addEventListener('click', async () => await reopenOpening(btn)));
  alertHost?.addEventListener('click', async event => { const btn = event.target.closest('.js-reopen-opening'); if (btn) await reopenOpening(btn); });

  document.querySelectorAll('.btn-void').forEach(btn => btn.addEventListener('click', async () => {
    btn.blur();
    if (!(await uiConfirm('VOID opening akan membatalkan saldo opening yang sudah diposting. Lanjutkan?',{title:'Void Dokumen Opening',okText:'Void Opening',cancelText:'Batal'}))) return;
    setButtonBusy(btn,'Void...');
    try { await postJson(voidBaseUrl+'/'+btn.dataset.id,{}); window.location.reload(); }
    catch (error) { renderAlert('danger',error.message||'Gagal void opening.'); clearButtonBusy(btn); }
  }));

  // — Init —
  syncSpreadsheetForms();
  if (!Array.isArray(lines)||!lines.length) lines = [blankLine()];
  syncHeaderDivisionState();
  renderLines();
})();
</script>

