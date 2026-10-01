<?php
$baseUrl                     = site_url('inventory/stock/opening/division');
$storeUrl                    = site_url('inventory/stock/opening/store');
$stepUpUrl                   = site_url('inventory/stock/opening/step-up/verify');
$voidUrlBase                 = site_url('inventory/stock/opening/void');
$itemSearchUrl               = site_url('inventory/stock/opening/item-search');
$stockOpeningExportUrl       = (string)($stock_opening_export_url ?? site_url('inventory/stock/opening/division/export-template'));
$stockOpeningExportExistingUrl = (string)($stock_opening_export_existing_url ?? site_url('inventory/stock/opening/division/export-existing'));
$stockOpeningImportUrl       = (string)($stock_opening_import_url ?? site_url('inventory/stock/opening/division/import'));
$stockOpeningCsrfToken       = (string)($stock_opening_csrf_token ?? '');

$rowsData         = is_array($rows ?? null) ? $rows : [];
$divisions        = is_array($divisions ?? null) ? $divisions : [];
$uoms             = is_array($uoms ?? null) ? $uoms : [];
$qSearch          = (string)($q ?? '');
$selDivisionId    = (int)($division_id ?? 0);
$selDestination   = strtoupper((string)($destination ?? 'ALL'));
$selMonth         = (string)($month ?? '');
$perPage          = max(10, (int)($per_page ?? 25));
$page             = max(1, (int)($page ?? 1));

// ── KPI ──────────────────────────────────────────────────────────────────────
$kpiTotal      = count($rowsData);
$kpiQtyContent = 0.0;
$kpiValue      = 0.0;
$kpiDivSet     = [];
$kpiMonthSet   = [];
$kpiItemSet    = [];
$divQtyMap     = [];   // division_code => [label, qty_content, value, color]
$divColors     = ['#667eea','#0c7cba','#11998e','#e55d2b','#6d28d9','#be185d','#059669','#d97706','#0891b2','#7c3aed'];
$divColorIdx   = 0;
foreach ($rowsData as $r) {
    $qtyC  = (float)($r['opening_qty_content'] ?? 0);
    $val   = (float)($r['opening_total_value']  ?? 0);
    $kpiQtyContent += $qtyC;
    $kpiValue      += $val;
    $divId  = (int)($r['division_id'] ?? 0);
    $code   = trim((string)($r['division_code'] ?? ''));
    $name   = trim((string)($r['division_name'] ?? ''));
    $dkey   = $code !== '' ? $code : (string)$divId;
    if ($divId > 0) $kpiDivSet[$divId] = true;
    if (!isset($divQtyMap[$dkey])) {
        $divQtyMap[$dkey] = ['label' => ($code !== '' ? $code : $name), 'qty' => 0.0, 'val' => 0.0, 'color' => $divColors[$divColorIdx++ % count($divColors)]];
    }
    $divQtyMap[$dkey]['qty'] += $qtyC;
    $divQtyMap[$dkey]['val'] += $val;
    if (!empty($r['snapshot_month'])) $kpiMonthSet[$r['snapshot_month']] = true;
    $itemKey = ($r['item_id'] ?? '') . ':' . ($r['material_id'] ?? '') . ':' . ($r['profile_key'] ?? '');
    $kpiItemSet[$itemKey] = true;
}
$kpiDivCount   = count($kpiDivSet);
$kpiMonthCount = count($kpiMonthSet);
$kpiItemCount  = count($kpiItemSet);
arsort($divQtyMap);

// ── Pagination ───────────────────────────────────────────────────────────────
$totalRows  = count($rowsData);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$pagedRows  = array_slice($rowsData, ($page - 1) * $perPage, $perPage);
$pBase      = array_filter(['per_page' => $perPage, 'month' => $selMonth, 'q' => $qSearch, 'destination' => $selDestination, 'division_id' => $selDivisionId ?: null]);
$pQs        = http_build_query($pBase);
?>

<style>
/* ── Filter ── */
.opn-filter-grid {
  display:grid;
  grid-template-columns: minmax(120px,1.6fr) 90px 110px minmax(140px,2fr) 58px auto auto;
  gap:.5rem; align-items:end;
}
@media(max-width:991px)  { .opn-filter-grid { grid-template-columns: 1fr 1fr 1fr 1fr; } }
@media(max-width:575px)  { .opn-filter-grid { grid-template-columns: 1fr 1fr; } }

/* ── KPI ── */
.opn-kpi-row { display:grid; grid-template-columns:repeat(6,1fr); gap:.6rem; margin-bottom:1rem; }
@media(max-width:1199px) { .opn-kpi-row { grid-template-columns:repeat(3,1fr); } }
@media(max-width:575px)  { .opn-kpi-row { grid-template-columns:repeat(2,1fr); } }
.opn-kpi {
  border-radius:14px; padding:.95rem 1.1rem .9rem; color:#fff;
  position:relative; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.13);
}
.opn-kpi::before { content:''; position:absolute; right:-16px; bottom:-16px; width:76px; height:76px; border-radius:50%; background:rgba(255,255,255,.12); }
.opn-kpi::after  { content:''; position:absolute; right:12px; top:-20px; width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.08); }
.opn-kpi-icon { font-size:1.2rem; opacity:.82; margin-bottom:.3rem; display:block; }
.opn-kpi-val  { font-size:1.35rem; font-weight:800; line-height:1.1; }
.opn-kpi-sub  { font-size:.7rem; opacity:.75; margin-top:.1rem; }
.opn-kpi-lbl  { font-size:.67rem; opacity:.82; text-transform:uppercase; letter-spacing:.06em; margin-top:.2rem; }
.opn-kpi-1 { background:linear-gradient(135deg,#667eea,#764ba2); }
.opn-kpi-2 { background:linear-gradient(135deg,#0c7cba,#0fcdba); }
.opn-kpi-3 { background:linear-gradient(135deg,#11998e,#38ef7d); color:#0b3b34; }
.opn-kpi-4 { background:linear-gradient(135deg,#e55d2b,#f7b733); }
.opn-kpi-5 { background:linear-gradient(135deg,#6d28d9,#a78bfa); }
.opn-kpi-6 { background:linear-gradient(135deg,#1c7ed6,#4dabf7); }

/* ── Distribution bar ── */
.opn-dist-bar {
  display:flex; height:8px; border-radius:999px; overflow:hidden; gap:2px; margin:.5rem 0 .25rem;
}
.opn-dist-seg { height:100%; border-radius:999px; transition:flex .3s; }
.opn-dist-legend { display:flex; flex-wrap:wrap; gap:.35rem .7rem; }
.opn-dist-dot { width:10px; height:10px; border-radius:50%; display:inline-block; flex-shrink:0; }

/* ── Table ── */
.opn-table-wrap { overflow-x:auto; overflow-y:auto; max-height:68vh; }
.opn-table-wrap table { min-width:1160px; border-collapse:separate; border-spacing:0; }
.opn-table-wrap thead th {
  position:sticky; top:0; z-index:4;
  background:linear-gradient(180deg,#2d5a27 0%,#3d7a35 100%);
  color:#f0faf0; white-space:nowrap;
  border-bottom:1px solid #3a6e32; font-size:.73rem; font-weight:700; letter-spacing:.03em;
}
.opn-row-zero td { opacity:.5; }
.btn-quickfill {
  opacity:0; transition:opacity .15s; padding:.1rem .4rem;
  font-size:.65rem; border-radius:5px;
}
tr:hover .btn-quickfill { opacity:1; }

/* ── Import/Export accordion ── */
.opn-io-panel { border:1px solid #d1e7dd; border-radius:12px; background:#f0fdf4; overflow:hidden; margin-bottom:1rem; }
.opn-io-header {
  display:flex; align-items:center; justify-content:space-between; cursor:pointer;
  padding:.65rem 1rem; background:linear-gradient(135deg,#d1fae5,#a7f3d0);
  border-bottom:1px solid #86efac; user-select:none;
}
.opn-io-body { display:none; padding:1rem; }
.opn-io-body.open { display:block; }

/* ── Pagination ── */
.opn-pagination { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; }
.opn-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 .5rem;
  border:1px solid #ddd; border-radius:8px; background:#fff; color:#555;
  font-size:.8rem; font-weight:600; text-decoration:none; transition:all .15s;
}
.opn-page-btn:hover   { background:#f5f5f5; border-color:#bbb; color:#333; }
.opn-page-btn.active  { background:#2d5a27; border-color:#2d5a27; color:#fff; }
.opn-page-btn.disabled { opacity:.4; pointer-events:none; }

/* Modal form label */
.opn-form-label { font-size:.8rem; font-weight:600; color:#374151; margin-bottom:.25rem; }
</style>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-archive-stack-line page-title-icon"></i><?php echo html_escape((string)($title ?? 'Opening Manual Bahan Baku')); ?></h4>
    <small class="text-muted">Snapshot opening stok per divisi. Tanggal posting otomatis tanggal 1 dari Snapshot Month.</small>
  </div>
  <button type="button" class="btn btn-success btn-sm align-self-start" data-bs-toggle="modal" data-bs-target="#openingFormModal">
    <i class="ri ri-add-circle-line me-1"></i>Tambah Opening
  </button>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'opening']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $selMonth !== '' ? substr($selMonth, 0, 7) : date('Y-m'), 'division_id' => (string)$selDivisionId, 'destination_type' => $selDestination],
]); ?>

<div id="opn-alert-area"></div>
<?php if ($this->session->flashdata('success')): ?><div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div><?php endif; ?>
<?php if ($this->session->flashdata('warning')): ?><div class="alert alert-warning"><?php echo html_escape((string)$this->session->flashdata('warning')); ?></div><?php endif; ?>
<?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div><?php endif; ?>

<!-- ── Import/Export (collapsible) ── -->
<div class="opn-io-panel">
  <div class="opn-io-header" id="opn-io-toggle">
    <span class="fw-semibold text-success"><i class="ri ri-file-excel-2-line me-2"></i>Import / Export Opening Massal</span>
    <span class="badge bg-success" id="opn-io-chevron"><i class="ri ri-arrow-down-s-line"></i></span>
  </div>
  <div class="opn-io-body" id="opn-io-body">
    <div class="d-flex flex-wrap gap-2 align-items-start mb-3">
      <small class="text-muted flex-fill">Template mengikuti divisi, tujuan, dan snapshot month yang aktif. Isi nilai opening di Excel lalu upload kembali dalam format XLSX.</small>
      <div class="d-flex gap-2 flex-wrap">
        <form method="get" action="<?php echo html_escape($stockOpeningExportUrl); ?>" id="opn-export-form">
          <input type="hidden" name="division_id" value="<?php echo $selDivisionId; ?>">
          <input type="hidden" name="destination" value="<?php echo html_escape($selDestination); ?>">
          <input type="hidden" name="month" value="<?php echo html_escape($selMonth !== '' ? substr($selMonth,0,7) : date('Y-m')); ?>">
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="ri ri-download-2-line me-1"></i>Export Template
          </button>
        </form>
        <?php if (!empty($can_export_existing)): ?>
        <form method="get" action="<?php echo html_escape($stockOpeningExportExistingUrl); ?>" id="opn-export-existing-form">
          <input type="hidden" name="division_id" value="<?php echo $selDivisionId; ?>">
          <input type="hidden" name="destination" value="<?php echo html_escape($selDestination); ?>">
          <input type="hidden" name="month" value="<?php echo html_escape($selMonth !== '' ? substr($selMonth,0,7) : date('Y-m')); ?>">
          <input type="hidden" name="q" value="<?php echo html_escape($qSearch); ?>">
          <button type="submit" class="btn btn-outline-dark btn-sm">
            <i class="ri ri-file-list-3-line me-1"></i>Export Existing
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <form method="post" action="<?php echo html_escape($stockOpeningImportUrl); ?>" enctype="multipart/form-data" id="opn-import-form" class="row g-3 align-items-end">
      <input type="hidden" name="stock_opening_csrf" value="<?php echo html_escape($stockOpeningCsrfToken); ?>">
      <input type="hidden" name="division_id" value="<?php echo $selDivisionId; ?>">
      <input type="hidden" name="destination" value="<?php echo html_escape($selDestination); ?>">
      <input type="hidden" name="month" value="<?php echo html_escape($selMonth !== '' ? substr($selMonth,0,7) : date('Y-m')); ?>">
      <div class="col-lg-9">
        <label class="form-label mb-1">File Excel (.xlsx)</label>
        <input type="file" name="import_file" class="form-control form-control-sm" accept=".xlsx" required>
        <small class="text-muted">Satu file hanya untuk divisi yang dipilih. Kolom: item_code / material_code, buy_uom_code, opening_qty_buy, opening_avg_cost_per_content</small>
      </div>
      <div class="col-lg-3 d-grid">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="ri ri-upload-2-line me-1"></i>Import Excel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Filter 1-row ── -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" id="opn-filter-form">
      <div class="opn-filter-grid">
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id">
            <option value="">Semua Divisi</option>
            <?php foreach ($divisions as $d): ?>
              <?php $did = (int)($d['id']??0); $dc = trim((string)($d['code']??'')); $dn = trim((string)($d['name']??'')); $dl = $dc!==''?$dc.' - '.$dn:($dn?:(string)$did); ?>
              <option value="<?php echo $did; ?>" <?php echo $selDivisionId===$did?'selected':''; ?>><?php echo html_escape($dl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination">
            <option value="ALL"     <?php echo $selDestination==='ALL'    ?'selected':''; ?>>Semua</option>
            <option value="REGULER" <?php echo $selDestination==='REGULER'?'selected':''; ?>>Reguler</option>
            <option value="EVENT"   <?php echo $selDestination==='EVENT'  ?'selected':''; ?>>Event</option>
            <option value="BAR"     <?php echo $selDestination==='BAR'    ?'selected':''; ?>>BAR</option>
            <option value="KITCHEN" <?php echo $selDestination==='KITCHEN'?'selected':''; ?>>KITCHEN</option>
            <option value="OFFICE"  <?php echo $selDestination==='OFFICE' ?'selected':''; ?>>OFFICE</option>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Bulan</label>
          <input type="month" class="form-control form-control-sm" name="month" value="<?php echo html_escape($selMonth !== '' ? substr($selMonth,0,7) : date('Y-m')); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Cari</label>
          <input type="text" class="form-control form-control-sm" name="q" id="opn-q-input" value="<?php echo html_escape($qSearch); ?>" placeholder="Item / profil / merk / keterangan" autocomplete="off">
        </div>
        <div>
          <label class="form-label mb-1">/ Hal</label>
          <input type="number" class="form-control form-control-sm" name="per_page" min="10" max="200" value="<?php echo $perPage; ?>">
        </div>
        <div style="display:flex;gap:.4rem;align-items:flex-end">
          <button type="submit" class="btn btn-sm btn-outline-primary">Terapkan</button>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-danger">Clear</a>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ── KPI Cards ── -->
<?php if ($kpiTotal > 0): ?>
<div class="opn-kpi-row">
  <div class="opn-kpi opn-kpi-1">
    <span class="opn-kpi-icon"><i class="ri ri-archive-stack-line"></i></span>
    <div class="opn-kpi-val"><?php echo number_format($kpiTotal); ?></div>
    <div class="opn-kpi-sub"><?php echo $kpiMonthCount; ?> snapshot bulan</div>
    <div class="opn-kpi-lbl">Total Entri</div>
  </div>
  <div class="opn-kpi opn-kpi-2">
    <span class="opn-kpi-icon"><i class="ri ri-building-2-line"></i></span>
    <div class="opn-kpi-val"><?php echo number_format($kpiDivCount); ?></div>
    <div class="opn-kpi-sub">divisi terdaftar</div>
    <div class="opn-kpi-lbl">Divisi Aktif</div>
  </div>
  <div class="opn-kpi opn-kpi-3">
    <span class="opn-kpi-icon"><i class="ri ri-box-3-line"></i></span>
    <div class="opn-kpi-val"><?php echo number_format($kpiQtyContent, 2, ',', '.'); ?></div>
    <div class="opn-kpi-sub">total qty isi opening</div>
    <div class="opn-kpi-lbl">Qty Isi</div>
  </div>
  <div class="opn-kpi opn-kpi-4">
    <span class="opn-kpi-icon"><i class="ri ri-money-dollar-circle-line"></i></span>
    <div class="opn-kpi-val"><?php echo 'Rp '.number_format($kpiValue, 0, ',', '.'); ?></div>
    <div class="opn-kpi-sub">total nilai opening</div>
    <div class="opn-kpi-lbl">Total Nilai</div>
  </div>
  <div class="opn-kpi opn-kpi-5">
    <span class="opn-kpi-icon"><i class="ri ri-price-tag-3-line"></i></span>
    <div class="opn-kpi-val"><?php echo number_format($kpiItemCount); ?></div>
    <div class="opn-kpi-sub">profil item unik</div>
    <div class="opn-kpi-lbl">Item Unik</div>
  </div>
  <div class="opn-kpi opn-kpi-6">
    <span class="opn-kpi-icon"><i class="ri ri-calendar-check-line"></i></span>
    <div class="opn-kpi-val"><?php echo $kpiMonthCount > 0 ? implode(', ', array_slice(array_keys($kpiMonthSet), 0, 2)) : '—'; ?></div>
    <div class="opn-kpi-sub"><?php echo $kpiMonthCount > 2 ? '+'.($kpiMonthCount-2).' bulan lain' : 'bulan aktif'; ?></div>
    <div class="opn-kpi-lbl">Snapshot Month</div>
  </div>
</div>

<!-- ── SURPRISE: Per-division distribution bar ── -->
<?php if (count($divQtyMap) > 1 && $kpiQtyContent > 0): ?>
<div class="card mb-3">
  <div class="card-body py-2 px-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <small class="fw-semibold text-muted text-uppercase" style="letter-spacing:.05em;font-size:.68rem">Distribusi Qty Isi per Divisi</small>
      <small class="text-muted"><?php echo number_format($kpiQtyContent, 2, ',', '.'); ?> total isi</small>
    </div>
    <div class="opn-dist-bar">
      <?php foreach ($divQtyMap as $dk => $dv): ?>
        <?php $pct = $kpiQtyContent > 0 ? max(0.5, round($dv['qty'] / $kpiQtyContent * 100, 2)) : 0; ?>
        <div class="opn-dist-seg" title="<?php echo html_escape($dv['label']); ?>: <?php echo number_format($dv['qty'],2,',','.'); ?> (<?php echo number_format($pct,1); ?>%)"
          style="flex:<?php echo $pct; ?>;background:<?php echo $dv['color']; ?>"></div>
      <?php endforeach; ?>
    </div>
    <div class="opn-dist-legend">
      <?php foreach ($divQtyMap as $dk => $dv): ?>
        <?php $pct = $kpiQtyContent > 0 ? round($dv['qty'] / $kpiQtyContent * 100, 1) : 0; ?>
        <span class="d-inline-flex align-items-center gap-1" style="font-size:.7rem">
          <span class="opn-dist-dot" style="background:<?php echo $dv['color']; ?>"></span>
          <span class="fw-semibold"><?php echo html_escape($dv['label']); ?></span>
          <span class="text-muted"><?php echo $pct; ?>%</span>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Table ── -->
<div class="card">
  <div class="card-header py-2 d-flex justify-content-between align-items-center">
    <span class="fw-semibold small">Daftar Opening Stok</span>
    <span class="text-muted small"><?php echo number_format($totalRows); ?> entri</span>
  </div>
  <div class="opn-table-wrap">
    <table class="table table-sm table-hover mb-0" id="opn-table">
      <thead>
        <tr>
          <th style="min-width:90px">Bulan</th>
          <th style="min-width:140px">Divisi / Tujuan</th>
          <th style="min-width:200px">Item</th>
          <th style="min-width:160px">Profil</th>
          <th style="min-width:70px">UOM Beli</th>
          <th class="text-end" style="min-width:90px">Qty Beli</th>
          <th class="text-end" style="min-width:90px">Qty Isi</th>
          <th class="text-end" style="min-width:90px">Avg Cost</th>
          <th class="text-end" style="min-width:100px">Total Nilai</th>
          <th style="min-width:90px">Sumber</th>
          <th style="min-width:90px">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($pagedRows)): ?>
        <tr><td colspan="11" class="text-center text-muted py-4">Belum ada opening stok untuk filter ini.</td></tr>
      <?php else: ?>
        <?php foreach ($pagedRows as $r): ?>
          <?php
            $rid   = (int)($r['id'] ?? 0);
            $qtyC  = (float)($r['opening_qty_content'] ?? 0);
            $qtyB  = (float)($r['opening_qty_buy']     ?? 0);
            $avgC  = (float)($r['opening_avg_cost_per_content'] ?? 0);
            $totV  = (float)($r['opening_total_value'] ?? 0);
            $divC  = trim((string)($r['division_code'] ?? ''));
            $divN  = trim((string)($r['division_name'] ?? ''));
            $divLabel = $divC !== '' ? ($divC.' - '.$divN) : ($divN ?: (string)($r['division_id'] ?? '-'));
            $dest  = strtoupper(trim((string)($r['destination_type'] ?? '')));
            $domain = strtoupper(trim((string)($r['stock_domain'] ?? 'ITEM')));
            $matCode = trim((string)($r['material_code'] ?? ''));
            $matName = trim((string)($r['material_name'] ?? ''));
            $itmCode = trim((string)($r['item_code'] ?? ''));
            $itmName = trim((string)($r['item_name'] ?? ''));
            $preferMat = ($domain === 'MATERIAL' || (int)($r['material_id'] ?? 0) > 0);
            $objCode   = $preferMat ? ($matCode ?: $itmCode) : ($itmCode ?: $matCode);
            $objName   = $preferMat ? ($matName ?: $itmName) : ($itmName ?: $matName);
            $profName  = trim((string)($r['profile_name'] ?? ''));
            $profBrand = trim((string)($r['profile_brand'] ?? ''));
            $profDesc  = trim((string)($r['profile_description'] ?? ''));
            $expDate   = trim((string)($r['profile_expired_date'] ?? ''));
            $buyUomCode = trim((string)($r['buy_uom_code'] ?? ($r['profile_buy_uom_code'] ?? '')));
            $cprBuy    = (float)($r['profile_content_per_buy'] ?? 1);
            $srcType   = trim((string)($r['source_type'] ?? '-'));
            $repMode   = (int)($r['replace_mode'] ?? 1);
            $rowNotes  = trim((string)($r['notes'] ?? ''));
            $monthSnap = trim((string)($r['snapshot_month'] ?? '-'));

            // data-* for quick-fill
            $qfData = [
              'item_id'       => (int)($r['item_id'] ?? 0),
              'material_id'   => (int)($r['material_id'] ?? 0),
              'division_id'   => (int)($r['division_id'] ?? 0),
              'destination'   => $dest,
              'snapshot_month'=> $monthSnap,
              'buy_uom_id'    => (int)($r['buy_uom_id'] ?? 0),
              'content_uom_id'=> (int)($r['content_uom_id'] ?? 0),
              'qty_buy'       => $qtyB,
              'ratio'         => $cprBuy > 0 ? $cprBuy : 1,
              'qty_content'   => $qtyC,
              'avg_cost'      => $avgC,
              'profile_name'  => $profName,
              'profile_brand' => $profBrand,
              'profile_desc'  => $profDesc,
              'exp_date'      => $expDate,
              'replace_mode'  => $repMode,
              'notes'         => $rowNotes,
              'item_label'    => $objCode !== '' ? $objCode.' - '.$objName : $objName,
            ];
          ?>
          <tr class="<?php echo $qtyC <= 0 ? 'opn-row-zero' : ''; ?>">
            <td class="small fw-semibold"><?php echo html_escape($monthSnap); ?></td>
            <td class="small">
              <div><?php echo html_escape($divLabel); ?></div>
              <small class="text-muted"><?php echo html_escape($dest); ?></small>
            </td>
            <td class="small">
              <div class="fw-semibold"><?php echo html_escape($objName ?: '-'); ?></div>
              <?php if ($objCode !== ''): ?><small class="text-muted"><?php echo html_escape($objCode); ?></small><?php endif; ?>
            </td>
            <td class="small">
              <div><?php echo html_escape($profName ?: '-'); ?></div>
              <small class="text-muted"><?php echo implode(' · ', array_filter([$profBrand, $profDesc])) ?: '-'; ?></small>
              <?php if ($expDate !== ''): ?><div><span class="badge bg-warning text-dark" style="font-size:.62rem">Exp <?php echo html_escape($expDate); ?></span></div><?php endif; ?>
            </td>
            <td class="small text-center"><?php echo html_escape($buyUomCode ?: '-'); ?></td>
            <td class="text-end small"><?php echo number_format($qtyB, 3, ',', '.'); ?></td>
            <td class="text-end small fw-semibold"><?php echo number_format($qtyC, 3, ',', '.'); ?></td>
            <td class="text-end small"><?php echo $avgC > 0 ? number_format($avgC, 4, ',', '.') : '—'; ?></td>
            <td class="text-end small fw-semibold"><?php echo $totV > 0 ? 'Rp '.number_format($totV, 0, ',', '.') : '—'; ?></td>
            <td class="small text-muted"><?php echo html_escape($srcType); ?></td>
            <td>
              <div class="d-flex gap-1 align-items-center">
                <button type="button" class="btn btn-xs btn-outline-secondary btn-quickfill"
                  title="Salin ke form opening"
                  data-qf="<?php echo html_escape(json_encode($qfData)); ?>"
                  data-bs-toggle="modal" data-bs-target="#openingFormModal">
                  <i class="ri ri-file-copy-line"></i>
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger btn-void-opening" data-id="<?php echo $rid; ?>">Void</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination footer -->
  <?php if ($totalRows > 0): ?>
  <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <?php $fromR = ($page-1)*$perPage+1; $toR = min($page*$perPage,$totalRows); ?>
    <span class="text-muted small">Baris <?php echo "{$fromR}–{$toR}"; ?> dari <?php echo number_format($totalRows); ?></span>
    <?php if ($totalPages > 1): ?>
    <div class="opn-pagination">
      <?php
        $ws = max(1,$page-2); $we = min($totalPages,$page+2);
        if ($we-$ws < 4) { if ($ws===1) $we=min($totalPages,$ws+4); else $ws=max(1,$we-4); }
      ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page-1)); ?>" class="opn-page-btn<?php echo $page>1?'':' disabled'; ?>">&#8249;</a>
      <?php if ($ws>1): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page=1'); ?>" class="opn-page-btn">1</a><?php if ($ws>2): ?><span class="text-muted small px-1">…</span><?php endif; ?><?php endif; ?>
      <?php for ($pn=$ws;$pn<=$we;$pn++): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$pn); ?>" class="opn-page-btn<?php echo $pn===$page?' active':''; ?>"><?php echo $pn; ?></a><?php endfor; ?>
      <?php if ($we<$totalPages): ?><?php if ($we<$totalPages-1): ?><span class="text-muted small px-1">…</span><?php endif; ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$totalPages); ?>" class="opn-page-btn"><?php echo $totalPages; ?></a><?php endif; ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page+1)); ?>" class="opn-page-btn<?php echo $page<$totalPages?'':' disabled'; ?>">&#8250;</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Modal: Form Opening ── -->
<div class="modal fade" id="openingFormModal" tabindex="-1" aria-labelledby="openingFormModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#2d5a27,#3d7a35);color:#f0faf0;">
        <h5 class="modal-title" id="openingFormModalLabel"><i class="ri ri-archive-stack-line me-2"></i>Form Opening Stok Manual</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <!-- Col: Divisi & Bulan -->
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header py-2"><small class="fw-semibold">Divisi & Periode</small></div>
              <div class="card-body">
                <div class="row g-2">
                  <div class="col-6">
                    <label class="opn-form-label">Divisi</label>
                    <select class="form-select form-select-sm" id="division_id">
                      <option value="">Pilih divisi...</option>
                      <?php foreach ($divisions as $d): ?>
                        <?php
                          $did = (int)($d['id']??0);
                          $dc  = trim((string)($d['code']??''));
                          $dn  = trim((string)($d['name']??''));
                          $dl  = $dc!==''?($dc.' - '.$dn):($dn?:(string)$did);
                          $destDef = strtoupper(trim((string)($d['destination_default']??'BAR')));
                          $destAlw = trim((string)($d['destination_allowed']??'BAR,KITCHEN,ROASTERY,BAR_EVENT,KITCHEN_EVENT,ROASTERY_EVENT,OFFICE'));
                        ?>
                        <option value="<?php echo $did; ?>"
                          data-code="<?php echo html_escape($dc); ?>"
                          data-name="<?php echo html_escape($dn); ?>"
                          data-destination-default="<?php echo html_escape($destDef); ?>"
                          data-destination-allowed="<?php echo html_escape($destAlw); ?>"
                          <?php echo $selDivisionId===$did?'selected':''; ?>><?php echo html_escape($dl); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="opn-form-label">Tujuan</label>
                    <select class="form-select form-select-sm" id="destination_type">
                      <option value="BAR">BAR (Reguler)</option>
                      <option value="KITCHEN">KITCHEN (Reguler)</option>
                      <option value="ROASTERY">ROASTERY (Reguler)</option>
                      <option value="BAR_EVENT">BAR_EVENT</option>
                      <option value="KITCHEN_EVENT">KITCHEN_EVENT</option>
                      <option value="ROASTERY_EVENT">ROASTERY_EVENT</option>
                      <option value="OFFICE">OFFICE</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="opn-form-label">Snapshot Month</label>
                    <input type="month" class="form-control form-control-sm" id="snapshot_month" value="<?php echo html_escape($selMonth !== '' ? substr($selMonth,0,7) : date('Y-m')); ?>" required>
                  </div>
                  <div class="col-6">
                    <label class="opn-form-label">Tanggal Posting (otomatis)</label>
                    <input type="date" class="form-control form-control-sm bg-light" id="movement_date_preview" readonly disabled>
                  </div>
                  <div class="col-12">
                    <label class="opn-form-label">Mode Posting</label>
                    <select class="form-select form-select-sm" id="replace_mode">
                      <option value="1">Set saldo live persis ke angka opening (disarankan)</option>
                      <option value="0">Tambah ke saldo live yang sekarang (koreksi)</option>
                    </select>
                    <small class="text-muted">Menentukan apakah opening menjadi saldo final atau ditambahkan sebagai delta.</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Col: Item & Profil -->
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header py-2"><small class="fw-semibold">Item & Profil</small></div>
              <div class="card-body">
                <div class="row g-2">
                  <div class="col-12">
                    <label class="opn-form-label">Item</label>
                    <input type="hidden" id="item_id">
                    <input type="hidden" id="profile_key">
                    <input type="text" class="form-control form-control-sm" id="item_search" placeholder="Ketik kode/nama item minimal 2 huruf..." autocomplete="off">
                    <div id="item_search_preview" class="list-group mt-1" style="max-height:190px;overflow:auto;"></div>
                    <small id="item_search_hint" class="text-muted d-block mt-1">Belum ada item dipilih.</small>
                  </div>
                  <div class="col-12">
                    <label class="opn-form-label">Profile Name</label>
                    <input type="text" class="form-control form-control-sm" id="profile_name" placeholder="Contoh: AIR MINERAL BOTOL">
                  </div>
                  <div class="col-6">
                    <label class="opn-form-label">Merk</label>
                    <input type="text" class="form-control form-control-sm" id="profile_brand" placeholder="Contoh: CLEO">
                  </div>
                  <div class="col-6">
                    <label class="opn-form-label">Exp Date</label>
                    <input type="date" class="form-control form-control-sm" id="profile_expired_date">
                  </div>
                  <div class="col-12">
                    <label class="opn-form-label">Keterangan</label>
                    <input type="text" class="form-control form-control-sm" id="profile_description" placeholder="Opsional">
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Col: Qty & Cost -->
          <div class="col-12">
            <div class="card">
              <div class="card-header py-2"><small class="fw-semibold">Qty & Cost</small></div>
              <div class="card-body">
                <div class="row g-2 align-items-end">
                  <div class="col-md-2 col-6">
                    <label class="opn-form-label">UOM Beli</label>
                    <select class="form-select form-select-sm" id="buy_uom_id" required>
                      <option value="">Pilih...</option>
                      <?php foreach ($uoms as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape((string)$u['code'].' - '.(string)$u['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2 col-6">
                    <label class="opn-form-label">UOM Isi</label>
                    <select class="form-select form-select-sm" id="content_uom_id" required>
                      <option value="">Pilih...</option>
                      <?php foreach ($uoms as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape((string)$u['code'].' - '.(string)$u['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2 col-6">
                    <label class="opn-form-label">Qty Beli</label>
                    <input type="number" class="form-control form-control-sm" id="opening_qty_buy" min="0" step="0.01" value="0">
                  </div>
                  <div class="col-md-2 col-6">
                    <label class="opn-form-label">Konversi (Isi per 1 Beli)</label>
                    <input type="number" class="form-control form-control-sm" id="profile_content_per_buy" min="0.001" step="0.001" value="1">
                  </div>
                  <div class="col-md-2 col-6">
                    <label class="opn-form-label">Qty Isi <small class="text-muted">(otomatis)</small></label>
                    <input type="number" class="form-control form-control-sm bg-light" id="opening_qty_content" min="0" step="0.001" value="0" readonly>
                  </div>
                  <div class="col-md-2 col-6">
                    <label class="opn-form-label" id="cost_label">Avg Cost / Isi</label>
                    <input type="number" class="form-control form-control-sm" id="opening_avg_cost_per_content" min="0" step="0.01" value="0">
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Notes -->
          <div class="col-12">
            <label class="opn-form-label">Catatan</label>
            <textarea class="form-control form-control-sm" id="notes" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btn-save-opening">
          <i class="ri ri-save-line me-1"></i>Simpan Opening
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var storeUrl       = <?php echo json_encode($storeUrl); ?>;
  var stockOpeningStepUpUrl = <?php echo json_encode($stepUpUrl); ?>;
  var voidUrlBase    = <?php echo json_encode($voidUrlBase); ?>;
  var itemSearchUrl  = <?php echo json_encode($itemSearchUrl); ?>;
  var stockOpeningCsrfToken = <?php echo json_encode($stockOpeningCsrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var stockScope     = 'DIVISION';
  var alertArea      = document.getElementById('opn-alert-area');

  // ── Form elements ──────────────────────────────────────────────────────────
  var divisionEl         = document.getElementById('division_id');
  var destinationEl      = document.getElementById('destination_type');
  var snapshotEl         = document.getElementById('snapshot_month');
  var movPreviewEl       = document.getElementById('movement_date_preview');
  var itemIdEl           = document.getElementById('item_id');
  var profileKeyEl       = document.getElementById('profile_key');
  var itemSearchEl       = document.getElementById('item_search');
  var itemPreviewEl      = document.getElementById('item_search_preview');
  var itemHintEl         = document.getElementById('item_search_hint');
  var profileNameEl      = document.getElementById('profile_name');
  var profileBrandEl     = document.getElementById('profile_brand');
  var profileExpiredEl   = document.getElementById('profile_expired_date');
  var profileDescEl      = document.getElementById('profile_description');
  var buyUomEl           = document.getElementById('buy_uom_id');
  var contentUomEl       = document.getElementById('content_uom_id');
  var qtyBuyEl           = document.getElementById('opening_qty_buy');
  var qtyContentEl       = document.getElementById('opening_qty_content');
  var ratioEl            = document.getElementById('profile_content_per_buy');
  var costInputEl        = document.getElementById('opening_avg_cost_per_content');
  var replaceModeEl      = document.getElementById('replace_mode');
  var notesEl            = document.getElementById('notes');
  var saveBtnEl          = document.getElementById('btn-save-opening');

  var destLabels = { BAR:'BAR (Reguler)', KITCHEN:'KITCHEN (Reguler)', ROASTERY:'ROASTERY (Reguler)', BAR_EVENT:'BAR_EVENT', KITCHEN_EVENT:'KITCHEN_EVENT', ROASTERY_EVENT:'ROASTERY_EVENT', OFFICE:'OFFICE' };
  var itemSearchTimer = null;
  var itemLastQ = '';
  var selectedItemMeta = null;

  function stockOpeningMutationHeaders() {
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Stock-Opening-Csrf': stockOpeningCsrfToken
    };
  }

  function postStockOpeningJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: stockOpeningMutationHeaders(),
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.text().then(function (text) {
        var parsed = null;
        try { parsed = text ? JSON.parse(text) : null; } catch (e) { parsed = null; }
        if (r.status >= 400 || !parsed || !parsed.ok) {
          var fallbackMessage = (text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
          throw new Error((parsed && parsed.message) ? parsed.message : (fallbackMessage || 'Permintaan opening gagal.'));
        }
        return parsed;
      });
    });
  }

  function requestOpeningStepUp(operation, payload) {
    if (!window.Swal) {
      return Promise.reject(new Error('Dialog verifikasi ulang belum tersedia. Muat ulang halaman lalu coba kembali.'));
    }
    var isVoid = operation === 'VOID';
    var isImport = operation === 'IMPORT';
    return window.Swal.fire({
      title: isVoid ? 'Verifikasi VOID Opening' : (isImport ? 'Verifikasi Import Opening' : 'Verifikasi Simpan Opening'),
      text: isVoid
        ? 'Masukkan password akun untuk membatalkan opening dan menjalankan rollback stok.'
        : (isImport
          ? 'Masukkan password akun untuk mengimpor opening pada satu divisi yang dipilih.'
          : 'Masukkan password akun untuk menyimpan dan memposting opening stok.'),
      input: 'password',
      inputAttributes: { autocomplete: 'current-password', autocapitalize: 'off' },
      inputPlaceholder: 'Password akun Anda',
      showCancelButton: true,
      confirmButtonText: 'Verifikasi & Lanjutkan',
      cancelButtonText: 'Batal',
      focusConfirm: false,
      preConfirm: function (password) {
        if (!String(password || '').trim()) {
          window.Swal.showValidationMessage('Password akun wajib diisi.');
          return false;
        }
        return String(password);
      }
    }).then(function (dialog) {
      if (!dialog.isConfirmed) return null;
      return postStockOpeningJson(stockOpeningStepUpUrl, {
        operation: operation,
        stock_scope: stockScope,
        division_id: Number(payload.division_id || 0),
        snapshot_id: Number(payload.snapshot_id || 0),
        password: String(dialog.value || '')
      }).then(function (result) {
        return result.step_up_proof || null;
      });
    });
  }

  function bindOpeningImportStepUp(form) {
    if (!form) return;
    form.addEventListener('submit', function (event) {
      if (form.dataset.stepUpReady === '1') {
        form.dataset.stepUpReady = '';
        return;
      }
      event.preventDefault();
      var divisionField = form.querySelector('[name="division_id"]');
      var divisionId = Number(divisionField ? divisionField.value : 0);
      if (!divisionId) {
        showAlert('warning', 'Pilih satu divisi aktif sebelum import opening.');
        return;
      }
      requestOpeningStepUp('IMPORT', { division_id: divisionId })
        .then(function (proof) {
          if (!proof) return;
          var proofField = form.querySelector('[name="step_up_proof"]');
          if (!proofField) {
            proofField = document.createElement('input');
            proofField.type = 'hidden';
            proofField.name = 'step_up_proof';
            form.appendChild(proofField);
          }
          proofField.value = proof;
          form.dataset.stepUpReady = '1';
          form.submit();
        })
        .catch(function (error) {
          showAlert('danger', error.message || 'Verifikasi import opening gagal.');
        });
    });
  }

  bindOpeningImportStepUp(document.getElementById('opn-import-form'));

  // ── Import/Export accordion ──────────────────────────────────────────────
  var ioToggle = document.getElementById('opn-io-toggle');
  var ioBody   = document.getElementById('opn-io-body');
  var ioChevron = document.getElementById('opn-io-chevron');
  if (ioToggle && ioBody) {
    ioToggle.addEventListener('click', function () {
      var open = ioBody.classList.toggle('open');
      if (ioChevron) ioChevron.innerHTML = open ? '<i class="ri ri-arrow-up-s-line"></i>' : '<i class="ri ri-arrow-down-s-line"></i>';
    });
  }

  // ── AJAX debounce on search filter ─────────────────────────────────────────
  var qInput = document.getElementById('opn-q-input');
  if (qInput) {
    qInput.addEventListener('input', function () {
      clearTimeout(window._opnQTimer);
      window._opnQTimer = setTimeout(function () {
        document.getElementById('opn-filter-form')?.submit();
      }, 450);
    });
  }

  // ── Destination rules ────────────────────────────────────────────────────
  function sanitizeDestList(raw) {
    var allowed = [];
    String(raw || '').split(',').forEach(function (p) {
      var c = p.trim().toUpperCase();
      if (destLabels[c] && allowed.indexOf(c) === -1) allowed.push(c);
    });
    return allowed.length ? allowed : ['BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE'];
  }
  function applyDivisionDestRule(forceDefault) {
    if (!destinationEl) return;
    var prev = String(destinationEl.value || '').toUpperCase();
    var allowed = ['BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE'];
    var defDest = 'BAR';
    if (divisionEl && divisionEl.value) {
      var opt = divisionEl.options[divisionEl.selectedIndex];
      if (opt) {
        allowed = sanitizeDestList(opt.getAttribute('data-destination-allowed'));
        var preset = String(opt.getAttribute('data-destination-default') || '').toUpperCase();
        defDest = (preset && allowed.indexOf(preset) !== -1) ? preset : allowed[0];
      }
    }
    destinationEl.innerHTML = allowed.map(function (c) { return '<option value="' + c + '">' + (destLabels[c] || c) + '</option>'; }).join('');
    destinationEl.value = (!forceDefault && prev && allowed.indexOf(prev) !== -1) ? prev : defDest;
  }
  if (divisionEl && destinationEl) {
    divisionEl.addEventListener('change', function () { applyDivisionDestRule(true); syncIoForms(); });
    applyDivisionDestRule(true);
  }

  // ── Qty / date sync ────────────────────────────────────────────────────────
  function refreshQtyContent() {
    var qtyBuy = Number(qtyBuyEl.value || 0);
    var ratio  = Number(ratioEl.value || 0);
    if (ratio <= 0) { ratio = 1; ratioEl.value = '1'; }
    qtyContentEl.value = String(Math.max(0, qtyBuy * ratio));
  }
  qtyBuyEl.addEventListener('input', refreshQtyContent);
  ratioEl.addEventListener('input', refreshQtyContent);
  refreshQtyContent();

  function monthStartDate(mv) {
    var raw = String(mv || '').trim();
    if (!/^\d{4}-\d{2}$/.test(raw)) { var n = new Date(); return n.getFullYear() + '-' + String(n.getMonth()+1).padStart(2,'0') + '-01'; }
    return raw + '-01';
  }
  function syncMovPreview() {
    if (movPreviewEl && snapshotEl) movPreviewEl.value = monthStartDate(snapshotEl.value);
  }
  if (snapshotEl) {
    snapshotEl.addEventListener('change', function () { syncMovPreview(); syncIoForms(); });
    snapshotEl.addEventListener('input',  function () { syncMovPreview(); syncIoForms(); });
  }
  syncMovPreview();

  function syncIoForms() {
    var mv  = snapshotEl  ? String(snapshotEl.value || '')  : '';
    var dv  = divisionEl  ? String(divisionEl.value || '')  : '';
    var dst = destinationEl ? String(destinationEl.value || '') : 'BAR';
    ['opn-export-form','opn-export-existing-form','opn-import-form'].forEach(function (fid) {
      var form = document.getElementById(fid);
      if (!form) return;
      var mi = form.querySelector('input[name="month"]');
      var di = form.querySelector('input[name="division_id"]');
      var dsi = form.querySelector('input[name="destination"]');
      if (mi) mi.value = mv;
      if (di) di.value = dv;
      if (dsi) dsi.value = dst;
    });
  }
  syncIoForms();

  ['opn-export-form','opn-export-existing-form','opn-import-form'].forEach(function (fid) {
    var form = document.getElementById(fid);
    if (!form) return;
    form.addEventListener('submit', function (ev) {
      syncIoForms();
      if (divisionEl && !divisionEl.value) {
        ev.preventDefault();
        showAlert('warning', 'Pilih divisi dulu sebelum export/import opening massal.');
      }
    });
  });

  // ── Item search ────────────────────────────────────────────────────────────
  function escHtml(v) { return String(v||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function fmtNum(v, d) { var n=Number(v||0); return n>0?n.toLocaleString('id-ID',{minimumFractionDigits:d,maximumFractionDigits:d}):''; }

  function renderItemList(items) {
    if (!Array.isArray(items) || items.length === 0) {
      itemPreviewEl.innerHTML = '<div class="list-group-item text-muted small">Item tidak ditemukan.</div>';
      return;
    }
    var html = '';
    items.forEach(function (it) {
      var id = Number(it.id || 0);
      var name = String(it.item_name || '');
      var sugCost = Number(it.suggested_avg_cost_per_content || 0);
      var cPB = Number(it.default_content_per_buy || 1);
      var buyC = String(it.default_buy_uom_code || '');
      var cntC = String(it.default_content_uom_code || '');
      html += '<button type="button" class="list-group-item list-group-item-action py-1 px-2"'
        + ' data-item-id="' + id + '"'
        + ' data-item-name="' + escHtml(name) + '"'
        + ' data-profile-key="' + escHtml(it.profile_key || '') + '"'
        + ' data-profile-name="' + escHtml(it.profile_name || '') + '"'
        + ' data-profile-brand="' + escHtml(it.profile_brand || '') + '"'
        + ' data-profile-description="' + escHtml(it.profile_description || '') + '"'
        + ' data-profile-expired-date="' + escHtml(it.profile_expired_date || '') + '"'
        + ' data-buy-uom-id="' + Number(it.default_buy_uom_id || 0) + '"'
        + ' data-content-uom-id="' + Number(it.default_content_uom_id || 0) + '"'
        + ' data-content-per-buy="' + cPB + '"'
        + ' data-is-material="' + Number(it.is_material || 0) + '"'
        + ' data-material-id="' + Number(it.material_id || 0) + '"'
        + ' data-suggested-unit-price="' + Number(it.suggested_unit_price || 0) + '"'
        + ' data-suggested-avg-cost="' + sugCost + '"'
        + ' data-suggested-price-source="' + escHtml(it.suggested_price_source || '') + '">'
        + '<div class="fw-semibold small">' + escHtml(name) + '</div>'
        + '<div class="text-muted" style="font-size:.72rem">'
        + escHtml(buyC || cntC || '-') + (cPB > 0 ? ' | isi ' + fmtNum(cPB,3) + ' ' + escHtml(cntC) : '')
        + (sugCost > 0 ? ' | avg cost Rp' + fmtNum(sugCost,2) : '')
        + '</div>'
        + '</button>';
    });
    itemPreviewEl.innerHTML = html;
  }

  function doItemSearch() {
    var q = (itemSearchEl.value || '').trim();
    itemIdEl.value = '';
    if (profileKeyEl) profileKeyEl.value = '';
    selectedItemMeta = null;
    contentUomEl.disabled = false;
    itemHintEl.textContent = 'Belum ada item dipilih.';
    if (q.length < 2) { itemPreviewEl.innerHTML = ''; return; }
    itemLastQ = q;
    fetch(itemSearchUrl + '?q=' + encodeURIComponent(q) + '&limit=20', { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) { if (q !== itemLastQ) return; renderItemList((res && res.items) ? res.items : []); })
      .catch(function () { itemPreviewEl.innerHTML = '<div class="list-group-item text-danger small">Gagal ambil data.</div>'; });
  }
  itemSearchEl.addEventListener('input', function () {
    clearTimeout(itemSearchTimer);
    itemSearchTimer = setTimeout(doItemSearch, 250);
  });

  itemPreviewEl.addEventListener('click', function (ev) {
    var t = ev.target;
    while (t && t !== itemPreviewEl && !t.getAttribute('data-item-id')) t = t.parentElement;
    if (!t || t === itemPreviewEl) return;
    var id = Number(t.getAttribute('data-item-id') || 0);
    if (id <= 0) return;
    selectedItemMeta = {
      id: id,
      profile_key: String(t.getAttribute('data-profile-key') || ''),
      profile_name: String(t.getAttribute('data-profile-name') || ''),
      profile_brand: String(t.getAttribute('data-profile-brand') || ''),
      profile_description: String(t.getAttribute('data-profile-description') || ''),
      profile_expired_date: String(t.getAttribute('data-profile-expired-date') || ''),
      item_name: String(t.getAttribute('data-item-name') || ''),
      default_buy_uom_id: Number(t.getAttribute('data-buy-uom-id') || 0),
      default_content_uom_id: Number(t.getAttribute('data-content-uom-id') || 0),
      default_content_per_buy: Number(t.getAttribute('data-content-per-buy') || 1),
      is_material: Number(t.getAttribute('data-is-material') || 0),
      material_id: Number(t.getAttribute('data-material-id') || 0),
      suggested_avg_cost: Number(t.getAttribute('data-suggested-avg-cost') || 0),
    };
    applyItemMeta(selectedItemMeta, String(t.getAttribute('data-item-name') || ''));
    itemPreviewEl.innerHTML = '';
  });

  function applyItemMeta(meta, label) {
    if (meta.default_buy_uom_id > 0)     buyUomEl.value = String(meta.default_buy_uom_id);
    if (meta.default_content_uom_id > 0) contentUomEl.value = String(meta.default_content_uom_id);
    if (meta.default_content_per_buy > 0) { ratioEl.value = String(meta.default_content_per_buy); refreshQtyContent(); }
    if (meta.suggested_avg_cost > 0) costInputEl.value = meta.suggested_avg_cost.toFixed(6);
    contentUomEl.disabled = (meta.is_material === 1 || meta.material_id > 0);
    if (profileNameEl)    profileNameEl.value    = meta.profile_name    || meta.item_name || '';
    if (profileBrandEl)   profileBrandEl.value   = meta.profile_brand   || '';
    if (profileDescEl)    profileDescEl.value    = meta.profile_description || '';
    if (profileExpiredEl) profileExpiredEl.value = meta.profile_expired_date || '';
    if (profileKeyEl)     profileKeyEl.value     = meta.profile_key || '';
    itemIdEl.value = String(meta.id);
    if (label) { itemSearchEl.value = label; itemHintEl.textContent = 'Item terpilih: ' + label; }
  }

  [profileNameEl, profileBrandEl, profileDescEl, profileExpiredEl, buyUomEl, contentUomEl, ratioEl].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input',  function () { if (profileKeyEl) profileKeyEl.value = ''; });
    el.addEventListener('change', function () { if (profileKeyEl) profileKeyEl.value = ''; });
  });

  // ── SURPRISE: Quick-fill from row ───────────────────────────────────────
  document.querySelectorAll('.btn-quickfill').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var raw = btn.getAttribute('data-qf');
      if (!raw) return;
      try {
        var d = JSON.parse(raw);
        if (divisionEl && d.division_id) {
          divisionEl.value = String(d.division_id);
          applyDivisionDestRule(false);
        }
        if (destinationEl && d.destination) destinationEl.value = d.destination;
        if (snapshotEl && d.snapshot_month) { snapshotEl.value = d.snapshot_month; syncMovPreview(); }
        if (buyUomEl && d.buy_uom_id)       buyUomEl.value = String(d.buy_uom_id);
        if (contentUomEl && d.content_uom_id) contentUomEl.value = String(d.content_uom_id);
        if (ratioEl) ratioEl.value = String(d.ratio || 1);
        if (qtyBuyEl)  qtyBuyEl.value  = String(d.qty_buy || 0);
        if (qtyContentEl) qtyContentEl.value = String(d.qty_content || 0);
        if (costInputEl) costInputEl.value = String(d.avg_cost || 0);
        if (profileNameEl) profileNameEl.value = d.profile_name || '';
        if (profileBrandEl) profileBrandEl.value = d.profile_brand || '';
        if (profileDescEl)  profileDescEl.value  = d.profile_desc || '';
        if (profileExpiredEl) profileExpiredEl.value = d.exp_date || '';
        if (replaceModeEl) replaceModeEl.value = String(d.replace_mode ?? 1);
        if (notesEl) notesEl.value = d.notes || '';
        itemIdEl.value = String(d.item_id || 0);
        if (profileKeyEl) profileKeyEl.value = '';
        if (d.item_label) { itemSearchEl.value = d.item_label; itemHintEl.textContent = 'Item tersalin: ' + d.item_label; }
        syncIoForms();
      } catch (e) {}
    });
  });

  // ── Save ─────────────────────────────────────────────────────────────────
  function showAlert(type, msg) {
    alertArea.innerHTML = '<div class="alert alert-'+type+' alert-dismissible fade show"><span>'+msg+'</span><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }
  function uiConfirm(msg, opts) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') return window.FinanceUI.confirm(msg, opts || {});
    return Promise.resolve(window.confirm(msg));
  }
  function setSaving(v) {
    if (!saveBtnEl) return;
    saveBtnEl.disabled = v;
    saveBtnEl.innerHTML = v
      ? '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...'
      : '<i class="ri ri-save-line me-1"></i>Simpan Opening';
  }

  saveBtnEl.addEventListener('click', function () {
    if (saveBtnEl.disabled) return;
    var snapM   = snapshotEl ? String(snapshotEl.value || '') : '';
    var divId   = divisionEl ? Number(divisionEl.value || 0) : 0;
    var destVal = destinationEl ? String(destinationEl.value || 'BAR') : 'BAR';
    var iId     = Number(itemIdEl.value || 0);
    var bUom    = Number(buyUomEl.value || 0);
    var cUom    = Number(contentUomEl.value || 0);
    var isMat   = selectedItemMeta ? (selectedItemMeta.is_material === 1 || selectedItemMeta.material_id > 0) : false;
    var matId   = selectedItemMeta ? Number(selectedItemMeta.material_id || 0) : 0;

    if (!iId || !bUom || !cUom || !snapM) { showAlert('warning', 'Field wajib belum lengkap (item, UOM, snapshot month).'); return; }
    if (!divId) { showAlert('warning', 'Pilih divisi dulu.'); return; }

    var ratio   = Number(ratioEl.value || 1);
    var rawCost = Number(costInputEl.value || 0);
    var payload = {
      stock_scope: stockScope, stock_domain: isMat ? 'MATERIAL' : 'ITEM',
      snapshot_month: snapM, movement_date: snapM.replace(/^(\d{4}-\d{2})$/, '$1-01'),
      item_id: iId, material_id: matId, division_id: divId, destination_type: destVal,
      buy_uom_id: bUom, content_uom_id: cUom,
      opening_qty_buy:     Number(qtyBuyEl.value || 0),
      opening_qty_content: Number(qtyContentEl.value || 0),
      opening_avg_cost_per_content: rawCost,
      profile_key:          profileKeyEl ? (profileKeyEl.value || null) : null,
      profile_name:         profileNameEl ? (profileNameEl.value || null) : null,
      profile_brand:        profileBrandEl ? (profileBrandEl.value || null) : null,
      profile_description:  profileDescEl ? (profileDescEl.value || null) : null,
      profile_expired_date: (profileExpiredEl && profileExpiredEl.value) ? profileExpiredEl.value : null,
      profile_content_per_buy: ratio,
      replace_mode: Number(replaceModeEl ? replaceModeEl.value : 1),
      notes: notesEl ? (notesEl.value || null) : null,
    };

    setSaving(true);
    requestOpeningStepUp('POST', payload)
      .then(function (proof) {
        if (!proof) return null;
        payload.step_up_proof = proof;
        return postStockOpeningJson(storeUrl, payload);
      })
      .then(function (res) {
        if (!res) return;
        showAlert('success', 'Opening berhasil disimpan. Memuat ulang...');
        var modal = document.getElementById('openingFormModal');
        if (modal) { var bsModal = bootstrap.Modal.getInstance(modal); if (bsModal) bsModal.hide(); }
        setTimeout(function () { window.location.reload(); }, 700);
      })
      .catch(function (err) { showAlert('danger', err.message || 'Gagal simpan.'); setSaving(false); });
  });

  // ── Void ────────────────────────────────────────────────────────────────
  document.querySelectorAll('.btn-void-opening').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      btn.blur();
      uiConfirm('VOID opening ini akan menghapus snapshot, rollback lot awal, dan rebuild histori stok. Lanjutkan?', { title:'Void Opening', okText:'Void', cancelText:'Batal' })
        .then(function (ok) {
          if (!ok) return null;
          if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') window.FinanceUI.setButtonLoading(btn, 'Void...');
          else btn.disabled = true;
          return requestOpeningStepUp('VOID', {
            stock_scope: stockScope,
            snapshot_id: Number(btn.getAttribute('data-id') || 0)
          });
        })
        .then(function (proof) {
          if (!proof) return null;
          return postStockOpeningJson(voidUrlBase + '/' + encodeURIComponent(btn.getAttribute('data-id') || ''), {
            stock_scope: stockScope,
            step_up_proof: proof
          });
        })
        .then(function (res) {
          if (!res) return;
          showAlert('success', 'Opening berhasil di-void. Memuat ulang...');
          setTimeout(function () { window.location.reload(); }, 700);
        })
        .catch(function (err) { showAlert('danger', err.message || 'Gagal void.'); })
        .finally(function () {
          if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') window.FinanceUI.clearButtonLoading(btn);
          else btn.disabled = false;
        });
    });
  });
})();
</script>
