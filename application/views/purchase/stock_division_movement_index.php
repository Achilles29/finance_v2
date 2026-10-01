<?php
$baseUrl  = site_url('inventory/stock/division/movement');
$genMonth = !empty($date_from ?? '') ? date('Y-m', strtotime((string)$date_from)) : date('Y-m');
$rows     = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];

$dateFrom = (string)($date_from ?? '');
$dateTo   = (string)($date_to   ?? '');
$qSearch  = (string)($q         ?? '');
$perPage  = max(10, (int)($per_page ?? 25));
$page     = max(1, (int)($page ?? 1));
$hasMore  = !empty($has_more);
$selDiv   = (int)($division_id ?? 0);
$selDest  = strtoupper((string)($destination ?? 'ALL'));

// ── KPI ──────────────────────────────────────────────────────────────────────
$kpiRows    = count($rows);
$kpiDivSet  = [];
$kpiIn      = 0.0;
$kpiOut     = 0.0;
$kpiValue   = 0.0;
$typeCounts = [];          // for breakdown bar
$typeValues = [];          // nilai per type
foreach ($rows as $r) {
    $divId = (int)($r['division_id'] ?? 0);
    if ($divId > 0) $kpiDivSet[$divId] = true;
    $delta = (float)($r['qty_content_delta'] ?? 0);
    $cost  = (float)($r['unit_cost'] ?? 0);
    $val   = abs($delta) * $cost;
    $kpiValue += $val;
    if ($delta >= 0) $kpiIn  += $delta;
    else             $kpiOut += abs($delta);
    $typeRaw = strtoupper(trim((string)($r['movement_type_label'] ?? $r['movement_type'] ?? 'UNKNOWN')));
    $typeCounts[$typeRaw] = ($typeCounts[$typeRaw] ?? 0) + 1;
    $typeValues[$typeRaw] = ($typeValues[$typeRaw] ?? 0.0) + $val;
}
$kpiNet = $kpiIn - $kpiOut;
$kpiDivCount = count($kpiDivSet);
arsort($typeCounts);

// ── Movement type metadata ───────────────────────────────────────────────────
$movTypeLabel = [
    'TRANSFER_IN'          => 'Transfer Masuk',
    'TRANSFER_OUT'         => 'Transfer Keluar',
    'RECEIPT_IN'           => 'Penerimaan PO',
    'FULFILLMENT_OUT'      => 'Fulfillment SR',
    'WASTE_OUT'            => 'Waste',
    'DISCARDED_OUT'        => 'Discarded',
    'SPOIL_OUT'            => 'Spoilage',
    'PROCESS_LOSS_OUT'     => 'Process Loss',
    'VARIANCE_OUT'         => 'Variance',
    'ADJUSTMENT_IN'        => 'Adj Plus',
    'ADJUSTMENT_OUT'       => 'Adj Minus',
    'ADJUSTMENT'           => 'Adjustment',
    'OPENING_STOK_AWAL'    => 'Opening Awal',
    'POS_OUT'              => 'POS Usage',
    'VOID_REVERSE'         => 'Void Return',
];
$movTypeColor = [
    'TRANSFER_IN'       => '#0284c7',
    'TRANSFER_OUT'      => '#7c3aed',
    'RECEIPT_IN'        => '#059669',
    'FULFILLMENT_OUT'   => '#d97706',
    'WASTE_OUT'         => '#dc2626',
    'DISCARDED_OUT'     => '#b91c1c',
    'SPOIL_OUT'         => '#ea580c',
    'PROCESS_LOSS_OUT'  => '#ca8a04',
    'VARIANCE_OUT'      => '#c2410c',
    'ADJUSTMENT_IN'     => '#0d9488',
    'ADJUSTMENT_OUT'    => '#be185d',
    'ADJUSTMENT'        => '#6d28d9',
    'OPENING_STOK_AWAL' => '#475569',
    'POS_OUT'           => '#854d0e',
    'VOID_REVERSE'      => '#0369a1',
];
$movTypeRowClass = [
    'TRANSFER_IN'    => 'mvt-row-in',
    'RECEIPT_IN'     => 'mvt-row-in',
    'ADJUSTMENT_IN'  => 'mvt-row-adj-in',
    'VOID_REVERSE'   => 'mvt-row-in',
    'OPENING_STOK_AWAL' => 'mvt-row-opening',
    'WASTE_OUT'      => 'mvt-row-shrink',
    'DISCARDED_OUT'  => 'mvt-row-shrink',
    'SPOIL_OUT'      => 'mvt-row-shrink',
    'PROCESS_LOSS_OUT' => 'mvt-row-shrink',
    'VARIANCE_OUT'   => 'mvt-row-shrink',
];
$getMovLabel  = static fn(string $t): string => $movTypeLabel[$t] ?? ucwords(strtolower(str_replace('_', ' ', $t)));
$getMovColor  = static fn(string $t): string => $movTypeColor[$t] ?? '#6b7280';
$getMovClass  = static fn(string $t): string => $movTypeRowClass[$t] ?? '';
$isInMovement = static fn(string $t): bool   => in_array($t, ['TRANSFER_IN','RECEIPT_IN','ADJUSTMENT_IN','OPENING_STOK_AWAL'], true) || str_contains($t,'_IN');

$adjCategoryLabel = [
    'WASTE'            => ['Waste',        '#dc2626'],
    'SPOILAGE'         => ['Spoil',        '#ea580c'],
    'PROCESS_LOSS'     => ['Process Loss', '#ca8a04'],
    'VARIANCE'         => ['Variance',     '#c2410c'],
    'ADJUSTMENT_PLUS'  => ['Adj Plus',     '#0d9488'],
    'ADJUSTMENT_MINUS' => ['Adj Minus',    '#7c3aed'],
];
$fmtMoney = static fn($v): string => 'Rp ' . number_format((float)$v, 0, ',', '.');

// ── Server pagination ────────────────────────────────────────────────────────
// Controller mengambil satu baris tambahan untuk mengetahui apakah ada halaman
// berikutnya; $rows sendiri sudah hanya berisi halaman aktif.
$pageOffset = ($page - 1) * $perPage;
$pageFirstRow = $kpiRows > 0 ? $pageOffset + 1 : 0;
$pageLastRow = $pageOffset + $kpiRows;
$pBase = ['per_page' => $perPage, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'q' => $qSearch, 'destination' => $selDest];
if ($selDiv > 0) $pBase['division_id'] = $selDiv;
$pQs = http_build_query($pBase);

// ── Source helpers ───────────────────────────────────────────────────────────
$sourceMap = [
    'pos_stock_commit'                      => 'POS Commit',
    'pur_purchase_receipt'                  => 'Receipt PO',
    'pur_store_request_fulfillment'         => 'Fulfillment SR',
    'inv_division_stock_adjustment'         => 'Adj Bahan Baku',
    'inv_stock_adjustment'                  => 'Adj Stok',
    'inv_division_stock_opening_snapshot'   => 'Opening Snapshot',
    'inv_warehouse_stock_opening_snapshot'  => 'Opening Snapshot',
    'inv_component_adjustment'              => 'Component Adj',
];
$buildSource = static function (array $r) use ($sourceMap): array {
    $tbl = strtolower(trim((string)($r['ref_table'] ?? '')));
    $rid = (int)($r['ref_id'] ?? 0);
    $lbl = $sourceMap[$tbl] ?? ($tbl !== '' ? strtoupper(str_replace('_',' ',$tbl)) : '-');
    if ($rid > 0) $lbl .= ' #' . $rid;
    $meta = array_filter([
        (int)($r['receipt_id']      ?? 0) > 0 ? 'Rcpt #' . (int)$r['receipt_id']      : '',
        (int)($r['receipt_line_id'] ?? 0) > 0 ? 'Line #' . (int)$r['receipt_line_id'] : '',
    ]);
    return ['label' => $lbl, 'meta' => implode(' | ', $meta)];
};
$formatDivLabel = static function (array $r): string {
    $code = trim((string)($r['division_code'] ?? ''));
    $name = trim((string)($r['division_name'] ?? ''));
    if ($code !== '' && strcasecmp($code,$name) === 0) return $code;
    if ($code !== '' && $name !== '') return $code . ' · ' . $name;
    return $name !== '' ? $name : (string)($r['division_id'] ?? '-');
};
?>

<style>
/* ── Filter ── */
.mvt-filter-grid {
  display:grid;
  grid-template-columns: minmax(120px,1.4fr) 95px 110px 110px minmax(150px,2fr) 64px auto auto;
  gap:.5rem; align-items:end;
}
@media(max-width:1199px) { .mvt-filter-grid { grid-template-columns:minmax(100px,1fr) 88px 105px 105px minmax(120px,2fr) 58px auto auto; } }
@media(max-width:991px)  { .mvt-filter-grid { grid-template-columns:1fr 1fr 1fr 1fr; } }
@media(max-width:575px)  { .mvt-filter-grid { grid-template-columns:1fr 1fr; } }

/* ── KPI ── */
.mvt-kpi-row { display:grid; grid-template-columns:repeat(6,1fr); gap:.6rem; margin-bottom:1rem; }
@media(max-width:1199px) { .mvt-kpi-row { grid-template-columns:repeat(3,1fr); } }
@media(max-width:575px)  { .mvt-kpi-row { grid-template-columns:repeat(2,1fr); } }
.mvt-kpi {
  border-radius:14px; padding:.95rem 1.1rem .9rem; color:#fff;
  position:relative; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.13);
}
.mvt-kpi::before { content:''; position:absolute; right:-16px; bottom:-16px; width:76px; height:76px; border-radius:50%; background:rgba(255,255,255,.12); }
.mvt-kpi::after  { content:''; position:absolute; right:12px; top:-20px; width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.08); }
.mvt-kpi-icon { font-size:1.2rem; opacity:.82; margin-bottom:.3rem; display:block; }
.mvt-kpi-val  { font-size:1.35rem; font-weight:800; line-height:1.1; }
.mvt-kpi-sub  { font-size:.7rem; opacity:.75; margin-top:.1rem; }
.mvt-kpi-lbl  { font-size:.67rem; opacity:.82; text-transform:uppercase; letter-spacing:.06em; margin-top:.2rem; }
.mvt-kpi-1 { background:linear-gradient(135deg,#667eea,#764ba2); }
.mvt-kpi-2 { background:linear-gradient(135deg,#0c7cba,#0fcdba); }
.mvt-kpi-3 { background:linear-gradient(135deg,#11998e,#38ef7d); }
.mvt-kpi-4 { background:linear-gradient(135deg,#e55d2b,#f7b733); }
.mvt-kpi-5 { background:linear-gradient(135deg,#1c7ed6,#4dabf7); }
.mvt-kpi-net-pos { background:linear-gradient(135deg,#2f9e44,#69db7c); }
.mvt-kpi-net-neg { background:linear-gradient(135deg,#c92a2a,#fa5252); }

/* ── Breakdown bar ── */
.mvt-breakdown { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; padding:.6rem 1rem; background:#faf6f3; border-bottom:1px solid #f0ddd4; }
.mvt-type-chip {
  display:inline-flex; align-items:center; gap:.35rem; padding:.22rem .7rem; border-radius:999px;
  font-size:.72rem; font-weight:700; border:1px solid; white-space:nowrap;
}
.mvt-type-chip-count  { background:rgba(0,0,0,.12); border-radius:999px; padding:0 .45rem; font-size:.66rem; }

/* ── Table ── */
.mvt-table-wrap { overflow-x:auto; overflow-y:auto; max-height:68vh; }
.mvt-table-wrap table { min-width:1600px; border-collapse:separate; border-spacing:0; }
.mvt-table-wrap thead th {
  position:sticky; top:0; z-index:4;
  background:linear-gradient(180deg,#6a2d3c 0%,#8a3d50 100%);
  color:#fff8f5; white-space:nowrap;
  border-bottom:1px solid #7f3040; font-size:.73rem; font-weight:700; letter-spacing:.03em;
}
/* Row color coding */
.mvt-row-in      > td:first-child { border-left:3px solid #2f9e44; }
.mvt-row-adj-in  > td:first-child { border-left:3px solid #0d9488; }
.mvt-row-shrink  > td:first-child { border-left:3px solid #c92a2a; }
.mvt-row-opening > td:first-child { border-left:3px solid #475569; }

/* Movement type badge */
.mvt-type-badge {
  display:inline-flex; align-items:center; gap:.28rem; font-size:.68rem; font-weight:700;
  padding:.22rem .6rem; border-radius:6px; white-space:nowrap; border:1px solid;
}
.mvt-delta-pos { color:#2f9e44 !important; font-weight:800; }
.mvt-delta-neg { color:#c92a2a !important; font-weight:800; }

/* ── Pagination ── */
.mvt-pagination { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; }
.mvt-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 .5rem;
  border:1px solid #ddd; border-radius:8px; background:#fff; color:#555;
  font-size:.8rem; font-weight:600; text-decoration:none; transition:all .15s;
}
.mvt-page-btn:hover   { background:#f5f5f5; border-color:#bbb; color:#333; }
.mvt-page-btn.active  { background:#6a2d3c; border-color:#6a2d3c; color:#fff; }
.mvt-page-btn.disabled { opacity:.4; pointer-events:none; }
.mvt-empty-state { display:grid; place-items:center; min-height:220px; padding:2rem; text-align:center; color:#766862; background:#fffdfb; }
.mvt-empty-state .mvt-empty-icon { display:grid; place-items:center; width:52px; height:52px; margin-bottom:.7rem; border-radius:17px; background:#f6eee9; color:#8b6251; font-size:1.45rem; }
.mvt-empty-state b { color:#493730; }.mvt-filter-status { grid-column:1 / -1; min-height:1rem; color:#7d6d65; font-size:.72rem; }
</style>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-arrow-left-right-line page-title-icon"></i><?php echo html_escape((string)($title ?? 'Log Bahan Baku')); ?></h4>
    <small class="text-muted">Riwayat pergerakan bahan baku: transfer, penerimaan, adjustment, dan pemakaian POS. Data hanya dibaca dari log stok.</small>
  </div>
  <a href="<?php echo site_url('inventory/fifo-audit'); ?>" class="btn btn-sm btn-outline-secondary align-self-start">
    <i class="ri ri-bar-chart-line me-1"></i>Audit FIFO
  </a>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'movement']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $genMonth, 'division_id' => (string)$selDiv, 'destination_type' => $selDest],
]); ?>

<!-- ── Filter 1-row ── -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" id="mvt-filter-form">
      <div class="mvt-filter-grid">
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id">
            <option value="">Semua Divisi</option>
            <?php foreach ($divisions as $d): ?>
              <?php $did = (int)($d['id']??0); $dc = trim((string)($d['code']??'')); $dn = trim((string)($d['name']??'')); $dl = $dc !== '' ? $dc.' - '.$dn : ($dn ?: (string)$did); ?>
              <option value="<?php echo $did; ?>" <?php echo $selDiv === $did ? 'selected' : ''; ?>><?php echo html_escape($dl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination">
            <option value="ALL"     <?php echo $selDest==='ALL'     ?'selected':''; ?>>Semua</option>
            <option value="REGULER" <?php echo $selDest==='REGULER' ?'selected':''; ?>>Reguler</option>
            <option value="EVENT"   <?php echo $selDest==='EVENT'   ?'selected':''; ?>>Event</option>
            <option value="BAR"     <?php echo $selDest==='BAR'     ?'selected':''; ?>>BAR</option>
            <option value="KITCHEN" <?php echo $selDest==='KITCHEN' ?'selected':''; ?>>KITCHEN</option>
            <option value="OFFICE"  <?php echo $selDest==='OFFICE'  ?'selected':''; ?>>OFFICE</option>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Dari</label>
          <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo html_escape($dateFrom); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Sampai</label>
          <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo html_escape($dateTo); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Cari</label>
          <input type="text" class="form-control form-control-sm" name="q" value="<?php echo html_escape($qSearch); ?>" placeholder="No mutasi / item / material / tipe / divisi" id="mvt-q-input" autocomplete="off">
        </div>
        <div>
          <label class="form-label mb-1">/ Hal</label>
          <input type="number" class="form-control form-control-sm" name="per_page" min="10" max="200" value="<?php echo $perPage; ?>">
        </div>
        <div style="display:flex;gap:.4rem;align-items:flex-end">
          <button type="submit" class="btn btn-sm btn-outline-primary" id="mvt-apply-btn"><i class="ri ri-filter-3-line me-1"></i>Terapkan</button>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-danger">Clear</a>
        </div>
        <div class="mvt-filter-status" id="mvt-filter-status" role="status" aria-live="polite"></div>
      </div>
    </form>
  </div>
</div>

<!-- ── KPI Cards ── -->
<?php if ($kpiRows > 0): ?>
<div class="mvt-kpi-row">
  <div class="mvt-kpi mvt-kpi-1">
    <span class="mvt-kpi-icon"><i class="ri ri-arrow-left-right-line"></i></span>
    <div class="mvt-kpi-val"><?php echo number_format($kpiRows); ?></div>
    <div class="mvt-kpi-sub"><?php echo count($typeCounts); ?> tipe · halaman <?php echo $page; ?></div>
    <div class="mvt-kpi-lbl">Baris Ditampilkan</div>
  </div>
  <div class="mvt-kpi mvt-kpi-2">
    <span class="mvt-kpi-icon"><i class="ri ri-building-2-line"></i></span>
    <div class="mvt-kpi-val"><?php echo number_format($kpiDivCount); ?></div>
    <div class="mvt-kpi-sub">pada halaman ini</div>
    <div class="mvt-kpi-lbl">Divisi Ditampilkan</div>
  </div>
  <div class="mvt-kpi mvt-kpi-3">
    <span class="mvt-kpi-icon"><i class="ri ri-arrow-down-circle-line"></i></span>
    <div class="mvt-kpi-val"><?php echo number_format($kpiIn, 2, ',', '.'); ?></div>
    <div class="mvt-kpi-sub">isi masuk · halaman ini</div>
    <div class="mvt-kpi-lbl">Masuk</div>
  </div>
  <div class="mvt-kpi mvt-kpi-4">
    <span class="mvt-kpi-icon"><i class="ri ri-arrow-up-circle-line"></i></span>
    <div class="mvt-kpi-val"><?php echo number_format($kpiOut, 2, ',', '.'); ?></div>
    <div class="mvt-kpi-sub">isi keluar · halaman ini</div>
    <div class="mvt-kpi-lbl">Keluar</div>
  </div>
  <div class="mvt-kpi mvt-kpi-5">
    <span class="mvt-kpi-icon"><i class="ri ri-money-dollar-circle-line"></i></span>
    <div class="mvt-kpi-val"><?php echo 'Rp '.number_format($kpiValue, 0, ',', '.'); ?></div>
    <div class="mvt-kpi-sub">abs(delta) × cost · halaman ini</div>
    <div class="mvt-kpi-lbl">Nilai Pergerakan</div>
  </div>
  <div class="mvt-kpi <?php echo $kpiNet >= 0 ? 'mvt-kpi-net-pos' : 'mvt-kpi-net-neg'; ?>">
    <span class="mvt-kpi-icon"><i class="ri ri-scales-3-line"></i></span>
    <div class="mvt-kpi-val"><?php echo ($kpiNet >= 0 ? '+' : '') . number_format($kpiNet, 2, ',', '.'); ?></div>
    <div class="mvt-kpi-sub">masuk dikurangi keluar · halaman ini</div>
    <div class="mvt-kpi-lbl">Net Delta</div>
  </div>
</div>
<?php endif; ?>

<!-- ── Table card ── -->
<div class="card">

  <!-- Movement type breakdown chips -->
  <?php if (!empty($typeCounts)): ?>
  <div class="mvt-breakdown">
    <span class="small text-muted me-1">Ringkasan tipe halaman:</span>
    <span class="mvt-type-chip" style="background:#f1f5f9;border-color:#cbd5e1;color:#334155">
      Semua <span class="mvt-type-chip-count"><?php echo $kpiRows; ?></span>
    </span>
    <?php foreach ($typeCounts as $typeKey => $typeCount): ?>
      <?php
        $typeColor = $getMovColor($typeKey);
        $typeDisplayLabel = $getMovLabel($typeKey);
        $typeValueFmt = isset($typeValues[$typeKey]) && $typeValues[$typeKey] > 0
          ? ' · Rp'.number_format($typeValues[$typeKey],0,',','.') : '';
      ?>
      <span class="mvt-type-chip" style="background:<?php echo $typeColor; ?>1a;border-color:<?php echo $typeColor; ?>55;color:<?php echo $typeColor; ?>">
        <?php echo html_escape($typeDisplayLabel); ?>
        <span class="mvt-type-chip-count"><?php echo $typeCount; ?></span>
      </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="mvt-table-wrap">
    <table class="table table-sm table-hover mb-0" id="mvt-table">
      <thead>
        <tr>
          <th style="min-width:90px">Tanggal</th>
          <th style="min-width:130px">Divisi</th>
          <th style="min-width:90px">Tujuan</th>
          <th style="min-width:160px">No Mutasi</th>
          <th style="min-width:160px">Tipe</th>
          <th style="min-width:140px">Kategori / Alasan</th>
          <th style="min-width:200px">Objek</th>
          <th style="min-width:180px">Profil</th>
          <th class="text-end" style="min-width:100px">Before (Isi)</th>
          <th class="text-end" style="min-width:110px">Delta (Isi)</th>
          <th class="text-end" style="min-width:100px">After (Isi)</th>
          <th class="text-end" style="min-width:85px">Cost/Isi</th>
          <th class="text-end" style="min-width:110px">Nilai</th>
          <th style="min-width:160px">Sumber / Ref</th>
          <th style="min-width:180px">Catatan</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="15" class="p-0"><div class="mvt-empty-state"><span class="mvt-empty-icon"><i class="ri ri-inbox-line"></i></span><b><?php echo $page > 1 ? 'Tidak ada data pada halaman ini.' : 'Belum ada data mutasi untuk filter ini.'; ?></b><span class="small mt-1">Coba ubah periode, kata kunci, divisi, atau tujuan stok.</span><?php if ($page > 1): ?><a class="btn btn-sm btn-outline-primary mt-3" href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page-1)); ?>"><i class="ri ri-arrow-left-line me-1"></i>Kembali ke halaman sebelumnya</a><?php else: ?><a class="btn btn-sm btn-outline-primary mt-3" href="<?php echo html_escape($baseUrl); ?>"><i class="ri ri-refresh-line me-1"></i>Reset filter</a><?php endif; ?></div></td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $typeRaw    = strtoupper(trim((string)($r['movement_type_label'] ?? $r['movement_type'] ?? 'UNKNOWN')));
            $typeLabel  = $getMovLabel($typeRaw);
            $typeColor  = $getMovColor($typeRaw);
            $typeClass  = $getMovClass($typeRaw);
            $sourceData = $buildSource((array)$r);
            $divLabel   = $formatDivLabel((array)$r);

            $delta     = (float)($r['qty_content_delta'] ?? 0);
            $after     = (float)($r['qty_content_after'] ?? 0);
            $before    = $after - $delta;
            $deltaBuy  = (float)($r['qty_buy_delta'] ?? 0);
            $afterBuy  = (float)($r['qty_buy_after'] ?? 0);
            $beforeBuy = $afterBuy - $deltaBuy;
            $cost      = (float)($r['unit_cost'] ?? 0);
            $mutVal    = abs($delta) * $cost;
            $uom       = (string)($r['profile_content_uom_code'] ?? ($r['profile_buy_uom_code'] ?? ''));

            $matName   = trim((string)($r['material_name'] ?? ''));
            $itemName  = trim((string)($r['item_name'] ?? ''));
            $objName   = $matName !== '' ? $matName : ($itemName ?: '-');

            $profileParts = array_filter([trim((string)($r['profile_name']??'')), trim((string)($r['profile_brand']??''))]);
            $profileLabel = implode(' · ', $profileParts) ?: '-';
            $profileDesc  = trim((string)($r['profile_description'] ?? ''));

            $adjCat    = strtoupper(trim((string)($r['adjustment_category'] ?? '')));
            $adjReason = strtolower(trim((string)($r['adjustment_reason_code'] ?? '')));
            [$adjCatLabel, $adjCatColor] = $adjCategoryLabel[$adjCat] ?? [$adjCat, '#6b7280'];

            $destType  = strtoupper(trim((string)($r['destination_type'] ?? 'OTHER')));
            $destGroup = strtoupper(trim((string)($r['destination_group'] ?? 'REGULER')));
            $destNames = ['BAR'=>'Bar','KITCHEN'=>'Kitchen','ROASTERY'=>'Roastery','BAR_EVENT'=>'Bar Event','KITCHEN_EVENT'=>'Kitchen Ev.','ROASTERY_EVENT'=>'Roastery Ev.','OFFICE'=>'Office','GUDANG'=>'Gudang'];
            $destDisplay = ($destNames[$destType] ?? $destType) . ' · ' . ($destGroup === 'EVENT' ? 'Event' : 'Reguler');
          ?>
          <tr class="<?php echo $typeClass; ?>" data-mvt-type="<?php echo html_escape($typeRaw); ?>">
            <td class="small text-nowrap"><?php echo html_escape((string)($r['movement_date'] ?? '-')); ?></td>
            <td class="small"><?php echo html_escape($divLabel); ?></td>
            <td class="small text-nowrap">
              <div><?php echo html_escape($destType); ?></div>
              <small class="text-muted"><?php echo $destGroup === 'EVENT' ? 'Event' : 'Reguler'; ?></small>
            </td>
            <td class="small fw-semibold text-nowrap"><?php echo html_escape((string)($r['movement_no'] ?? '-')); ?></td>
            <td>
              <span class="mvt-type-badge" style="background:<?php echo $typeColor; ?>15;border-color:<?php echo $typeColor; ?>44;color:<?php echo $typeColor; ?>">
                <i class="ri <?php echo $delta >= 0 ? 'ri-arrow-down-circle-line' : 'ri-arrow-up-circle-line'; ?>"></i>
                <?php echo html_escape($typeLabel); ?>
              </span>
            </td>
            <td class="small">
              <?php if ($adjCat !== ''): ?>
                <span class="badge fw-semibold" style="background:<?php echo $adjCatColor; ?>22;border:1px solid <?php echo $adjCatColor; ?>44;color:<?php echo $adjCatColor; ?>"><?php echo html_escape((string)($adjCatLabel ?: $adjCat)); ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              <?php if ($adjReason !== ''): ?><div class="text-muted" style="font-size:.7rem;margin-top:.1rem"><?php echo html_escape(str_replace('_',' ',$adjReason)); ?></div><?php endif; ?>
            </td>
            <td>
              <div class="small fw-semibold"><?php echo html_escape($objName); ?></div>
              <?php if (trim((string)($r['material_code'] ?? '')) !== '' || trim((string)($r['item_code'] ?? '')) !== ''): ?>
                <small class="text-muted"><?php echo html_escape(trim((string)($r['material_code'] ?? ($r['item_code'] ?? '')))); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <div class="small"><?php echo html_escape($profileLabel); ?></div>
              <?php if ($profileDesc !== ''): ?><small class="text-muted"><?php echo html_escape($profileDesc); ?></small><?php endif; ?>
            </td>
            <td class="text-end small">
              <div><?php echo number_format($before, 2, ',', '.'); ?></div>
              <?php if ($beforeBuy != 0): ?><small class="text-muted"><?php echo number_format($beforeBuy, 3, ',', '.'); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small><?php endif; ?>
            </td>
            <td class="text-end small <?php echo $delta >= 0 ? 'mvt-delta-pos' : 'mvt-delta-neg'; ?>">
              <div><?php echo ($delta >= 0 ? '+' : '') . number_format($delta, 2, ',', '.'); ?></div>
              <?php if ($deltaBuy != 0): ?><small style="opacity:.7;font-weight:normal"><?php echo ($deltaBuy >= 0 ? '+' : '') . number_format($deltaBuy, 3, ',', '.'); ?></small><?php endif; ?>
            </td>
            <td class="text-end small">
              <div><?php echo number_format($after, 2, ',', '.'); ?></div>
              <?php if ($afterBuy != 0): ?><small class="text-muted"><?php echo number_format($afterBuy, 3, ',', '.'); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small><?php endif; ?>
            </td>
            <td class="text-end small"><?php echo $cost > 0 ? number_format($cost, 4, ',', '.') : '—'; ?></td>
            <td class="text-end small fw-semibold"><?php echo $mutVal > 0 ? 'Rp '.number_format($mutVal, 0, ',', '.') : '—'; ?></td>
            <td class="small">
              <div class="fw-semibold"><?php echo html_escape($sourceData['label']); ?></div>
              <?php if ($sourceData['meta'] !== ''): ?><small class="text-muted"><?php echo html_escape($sourceData['meta']); ?></small><?php endif; ?>
            </td>
            <td class="small text-muted"><?php echo html_escape((string)($r['notes'] ?? '—')); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination footer -->
  <?php if ($kpiRows > 0): ?>
  <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="mvt-pagination">
      <?php if ($page > 1): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page-1)); ?>" class="mvt-page-btn" aria-label="Halaman sebelumnya">&#8249;<span class="visually-hidden">Halaman sebelumnya</span></a><?php else: ?><span class="mvt-page-btn disabled" aria-disabled="true">&#8249;<span class="visually-hidden">Halaman sebelumnya</span></span><?php endif; ?>
      <span class="mvt-page-btn active" aria-current="page">Halaman <?php echo $page; ?></span>
      <?php if ($hasMore): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page+1)); ?>" class="mvt-page-btn" aria-label="Halaman berikutnya">&#8250;<span class="visually-hidden">Halaman berikutnya</span></a><?php else: ?><span class="mvt-page-btn disabled" aria-disabled="true">&#8250;<span class="visually-hidden">Halaman berikutnya</span></span><?php endif; ?>
    </div>
    <span class="text-muted small">Baris <?php echo "{$pageFirstRow}–{$pageLastRow}"; ?> · <?php echo $hasMore ? 'masih ada transaksi berikutnya' : 'halaman terakhir untuk filter ini'; ?></span>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const filterForm = document.getElementById('mvt-filter-form');
  const filterButton = document.getElementById('mvt-apply-btn');
  const filterStatus = document.getElementById('mvt-filter-status');
  function setPageLoading(message) {
    if (filterStatus) filterStatus.textContent = message || 'Memuat data mutasi…';
    if (filterForm) filterForm.setAttribute('aria-busy', 'true');
    if (filterButton) { filterButton.disabled = true; filterButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat'; }
  }
  if (filterForm) filterForm.addEventListener('submit', function () { setPageLoading(); });
  document.querySelectorAll('.mvt-page-btn[href]').forEach(function (link) {
    link.addEventListener('click', function () { setPageLoading('Memuat halaman mutasi…'); });
  });

  // AJAX debounce on search
  const qInput = document.getElementById('mvt-q-input');
  if (qInput) {
    qInput.addEventListener('input', function () {
      clearTimeout(window._mvtQTimer);
      window._mvtQTimer = setTimeout(function () {
        document.getElementById('mvt-filter-form')?.submit();
      }, 450);
    });
  }

})();
</script>
