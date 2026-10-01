<?php
$baseUrl      = site_url('inventory/stock/division/reconcile');
$auditUrl     = site_url('inventory/stock/division/reconcile/audit');
$repairUrl    = site_url('inventory/stock/division/reconcile/repair');
$lotRepairUrl        = site_url('inventory/stock/division/reconcile/lot-repair');
$lotProfileSyncUrl   = site_url('inventory/stock/division/reconcile/lot-profile-sync');
$lotRepairAllUrl     = site_url('inventory/stock/division/reconcile/lot-repair-all');
$gapRepairAllUrl     = site_url('inventory/stock/division/reconcile/gap-repair-all');
$repairMaterialIdUrl    = site_url('inventory/stock/division/reconcile/repair-material-id');
$profileRepairUrl       = site_url('inventory/stock/division/reconcile/profile-repair');
$profileMergeUrl        = site_url('inventory/stock/division/reconcile/profile-merge');
$logRepairUrl           = site_url('inventory/stock/division/reconcile/log-repair');

$allRows     = is_array($rows ?? null) ? $rows : [];
$divisions   = is_array($divisions ?? null) ? $divisions : [];
$summary     = is_array($summary ?? null) ? $summary : [];
$orphanStock = is_array($orphan_stock ?? null) ? $orphan_stock : ['fixable' => [], 'no_material' => [], 'total' => 0];
$orphanFixable   = (array)($orphanStock['fixable'] ?? []);
$orphanNoMat     = (array)($orphanStock['no_material'] ?? []);
$orphanTotal     = (int)($orphanStock['total'] ?? 0);
$destGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];

$asOfDate    = (string)($as_of_date ?? date('Y-m-d'));
$qSearch     = (string)($q ?? '');
$selDivId    = (int)($division_id ?? 0);
$selDest     = strtoupper((string)($destination ?? 'ALL'));
$perPage     = max(10, (int)($per_page ?? 25));
$page        = max(1, (int)($page ?? 1));

$mismatchOnly = !empty($_GET['mismatch_only']);

// -- Filter zero-balance rows --------------------------------------------------
$rows = array_values(array_filter($allRows, static function (array $row) use ($mismatchOnly): bool {
    $balQty = abs(round((float)($row['balance_qty_content'] ?? 0), 2));
    $lotQty = round((float)($row['lot_qty_content'] ?? 0), 2);
    // Skip only when BOTH stock balance AND FIFO lot total are zero.
    if ($balQty < 0.01 && $lotQty < 0.01) return false;
    if ($mismatchOnly && !empty($row['is_match'])) return false;
    return true;
}));

// -- KPI ---------------------------------------------------------------------
$kpiTotal     = count($rows);
$kpiMatch     = 0;
$kpiMismatch  = 0;
$kpiMinus     = 0;
$kpiDivSet    = [];
$kpiDeltaSum  = 0.0;

// Per-division breakdown for heatmap
$divMatchMap  = [];  // div_label => [match, mismatch, minus]

foreach ($rows as $r) {
    $isMatch   = !empty($r['is_match']);
    $isMinus   = (float)($r['balance_qty_content'] ?? 0) < 0;
    $delta     = abs((float)($r['delta_balance_vs_movement'] ?? 0));
    if ($isMatch) $kpiMatch++; else $kpiMismatch++;
    if ($isMinus) $kpiMinus++;
    $kpiDeltaSum += $delta;
    $divId = (int)($r['division_id'] ?? 0);
    if ($divId > 0) $kpiDivSet[$divId] = true;
    $dc   = trim((string)($r['division_code'] ?? ''));
    $dn   = trim((string)($r['division_name'] ?? ''));
    $dkey = $dc !== '' ? $dc : ($dn ?: (string)$divId);
    if (!isset($divMatchMap[$dkey])) $divMatchMap[$dkey] = ['label' => $dkey, 'match' => 0, 'mismatch' => 0, 'minus' => 0];
    if ($isMatch) $divMatchMap[$dkey]['match']++;
    else          $divMatchMap[$dkey]['mismatch']++;
    if ($isMinus) $divMatchMap[$dkey]['minus']++;
}
$kpiDivCount = count($kpiDivSet);
$healthPct   = $kpiTotal > 0 ? round($kpiMatch / $kpiTotal * 100) : 100;

// Sort divisions by mismatch desc
uasort($divMatchMap, static fn($a,$b) => $b['mismatch'] - $a['mismatch']);

// -- Pagination ---------------------------------------------------------------
$totalRows  = count($rows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$pagedRows  = array_slice($rows, ($page - 1) * $perPage, $perPage);
$pBase      = array_filter(['as_of_date' => $asOfDate, 'division_id' => $selDivId ?: null, 'destination' => $selDest !== 'ALL' ? $selDest : null, 'q' => $qSearch, 'per_page' => $perPage, 'mismatch_only' => $mismatchOnly ? '1' : null]);
$pQs        = http_build_query($pBase);
$mismatchToggleBase = $pBase;
if ($mismatchOnly) { unset($mismatchToggleBase['mismatch_only']); } else { $mismatchToggleBase['mismatch_only'] = '1'; }
$mismatchToggleUrl  = $baseUrl . '?' . http_build_query($mismatchToggleBase);

$fmtQty     = static fn($v): string => number_format((float)$v, 2, ',', '.');
$fmtText    = static fn($v, string $fb = '-'): string => trim((string)$v) !== '' ? trim((string)$v) : $fb;
$renderSelisih = static function (string $text): string {
    $text = trim($text);
    if ($text === '' || $text === '-' || $text === '-') {
        return '<span class="rec-selisih-ok">-</span>';
    }
    $shortMap = [
        'Lot vs Stok:' => 'Lot-Stok',
        'Nilai Lot vs Stok:' => 'Nilai Lot',
        'Stok vs Mvt:' => 'Stok-Mvt',
        'Mat. Daily vs Mvt:' => 'Daily-Mvt',
        'Snapshot vs Mvt:' => 'Snap-Mvt',
        'Gap Histori:' => 'Histori',
        'Log Gap:' => 'Log',
    ];
    $parts = array_values(array_filter(array_map('trim', explode(';', $text)), static fn($v): bool => $v !== ''));
    $html = '<div class="rec-selisih-stack">';
    foreach ($parts as $part) {
        $label = $part;
        foreach ($shortMap as $from => $to) {
            if (stripos($part, $from) === 0) {
                $label = $to . ': ' . trim(substr($part, strlen($from)));
                break;
            }
        }
        $html .= '<span class="rec-selisih-chip">' . html_escape($label) . '</span>';
    }
    return $html . '</div>';
};
?>

<style>
/* -- Filter -- */
.rec-filter-grid {
  display:grid;
  grid-template-columns: minmax(100px,1fr) minmax(130px,1.6fr) minmax(96px,1fr) minmax(150px,2fr) 84px auto;
  gap:.5rem; align-items:end;
}
@media(max-width:991px)  { .rec-filter-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:575px)  { .rec-filter-grid { grid-template-columns:1fr 1fr; } }

/* -- KPI -- */
.rec-kpi-row { display:grid; grid-template-columns:repeat(5,1fr) 180px; gap:.6rem; margin-bottom:1rem; align-items:stretch; }
@media(max-width:1199px) { .rec-kpi-row { grid-template-columns:repeat(3,1fr); } }
@media(max-width:575px)  { .rec-kpi-row { grid-template-columns:1fr 1fr; } }
.rec-kpi {
  border-radius:14px; padding:.95rem 1.1rem .9rem; color:#fff;
  position:relative; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.13);
}
.rec-kpi::before { content:''; position:absolute; right:-16px; bottom:-16px; width:76px; height:76px; border-radius:50%; background:rgba(255,255,255,.12); }
.rec-kpi::after  { content:''; position:absolute; right:12px; top:-20px; width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.08); }
.rec-kpi-icon { font-size:1.2rem; opacity:.82; margin-bottom:.3rem; display:block; }
.rec-kpi-val  { font-size:1.35rem; font-weight:800; line-height:1.1; }
.rec-kpi-sub  { font-size:.7rem; opacity:.75; margin-top:.1rem; }
.rec-kpi-lbl  { font-size:.67rem; opacity:.82; text-transform:uppercase; letter-spacing:.06em; margin-top:.2rem; }
.rec-kpi-1 { background:linear-gradient(135deg,#667eea,#764ba2); }
.rec-kpi-2 { background:linear-gradient(135deg,#11998e,#38ef7d); color:#0b3b34; }
.rec-kpi-3-ok   { background:linear-gradient(135deg,#374151,#6b7280); }
.rec-kpi-3-warn { background:linear-gradient(135deg,#c92a2a,#fa5252); }
.rec-kpi-4 { background:linear-gradient(135deg,#e55d2b,#f7b733); }
.rec-kpi-5 { background:linear-gradient(135deg,#0c7cba,#0fcdba); }

/* -- SURPRISE: Health ring KPI -- */
.rec-health-card {
  border-radius:14px; padding:.9rem 1.1rem; color:#fff;
  display:flex; align-items:center; gap:.9rem;
  box-shadow:0 4px 18px rgba(0,0,0,.13);
  position:relative; overflow:hidden;
}
.rec-health-ring-wrap { flex-shrink:0; position:relative; width:68px; height:68px; }
.rec-health-ring {
  width:68px; height:68px; border-radius:50%;
  background: conic-gradient(
    var(--ring-fill) 0% var(--ring-pct),
    rgba(255,255,255,.18) var(--ring-pct) 100%
  );
  display:flex; align-items:center; justify-content:center;
}
.rec-health-ring-inner {
  width:48px; height:48px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-weight:900; font-size:.9rem; line-height:1;
}
.rec-health-ok   { background:linear-gradient(135deg,#2f9e44,#69db7c); color:#0b3b34; }
.rec-health-warn { background:linear-gradient(135deg,#d97706,#fbbf24); color:#1a0a00; }
.rec-health-bad  { background:linear-gradient(135deg,#c92a2a,#fa5252); }
.rec-health-ok   .rec-health-ring-inner  { background:rgba(0,0,0,.12); color:#f0fff4; }
.rec-health-warn .rec-health-ring-inner  { background:rgba(0,0,0,.10); color:#1a0a00; }
.rec-health-bad  .rec-health-ring-inner  { background:rgba(0,0,0,.12); color:#fff; }

/* -- Division mismatch bar -- */
.rec-div-bar-wrap { padding:.7rem 1rem; background:#faf6f3; border-bottom:1px solid #f0e0d4; }
.rec-div-bar { display:flex; flex-direction:column; gap:.3rem; }
.rec-div-bar-row { display:flex; align-items:center; gap:.6rem; }
.rec-div-bar-label { width:80px; font-size:.72rem; font-weight:700; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0; }
.rec-div-bar-track { flex:1; height:8px; border-radius:999px; background:#e9ecef; overflow:hidden; display:flex; }
.rec-div-bar-match    { height:100%; background:#2f9e44; }
.rec-div-bar-mismatch { height:100%; background:#c92a2a; }
.rec-div-bar-count { font-size:.69rem; color:#6b7280; white-space:nowrap; flex-shrink:0; width:60px; text-align:right; }

/* -- POS Jobs accordion -- */
.rec-pos-panel { border:1px solid #bfdbfe; border-radius:12px; background:#eff6ff; overflow:hidden; margin-bottom:1rem; }
.rec-pos-header {
  display:flex; align-items:center; justify-content:space-between; cursor:pointer;
  padding:.65rem 1rem; background:linear-gradient(135deg,#dbeafe,#bfdbfe);
  user-select:none;
}
.rec-pos-body { display:none; padding:1rem; }
.rec-pos-body.open { display:block; }

/* -- Table -- */
.rec-table-wrap { overflow-x:auto; overflow-y:auto; max-height:65vh; }
.rec-table-wrap table { min-width:1240px; border-collapse:separate; border-spacing:0; }
.rec-table-wrap > table > thead > tr > th:nth-child(7),
.rec-table-wrap > table > tbody > tr:not(.rec-profile-breakdown-row) > td:nth-child(7) {
  width:118px;
  max-width:118px;
}
.rec-table-wrap > table > thead > tr > th:nth-child(9),
.rec-table-wrap > table > tbody > tr:not(.rec-profile-breakdown-row) > td:nth-child(9) {
  width:132px;
  min-width:132px;
  max-width:132px;
}
.rec-table-wrap thead th {
  position:sticky; top:0; z-index:4;
  background:linear-gradient(180deg,#312028 0%,#4a2f3a 100%);
  color:#ffe4dc; white-space:nowrap;
  border-bottom:1px solid #3d2430; font-size:.73rem; font-weight:700; letter-spacing:.03em;
}
.rec-row-negative td { background:#fff0f0 !important; }
.rec-row-negative td:first-child { border-left:3px solid #c92a2a; }
.rec-chip {
  display:inline-flex; align-items:center; padding:.22rem .6rem;
  border-radius:999px; font-size:.68rem; font-weight:800; white-space:nowrap;
}
.rec-chip-ok   { background:#d3f9d8; color:#2f9e44; border:1px solid #b2f2bb; }
.rec-chip-bad  { background:#fde9e8; color:#b42318; border:1px solid #fca5a5; }
.rec-chip-warn { background:#fff4e5; color:#e67700; border:1px solid #ffd8a8; }
.rec-delta-ok  { color:#2f9e44; font-weight:800; }
.rec-delta-bad { color:#b42318; font-weight:800; }
.rec-col-selisih {
  width:112px;
  min-width:112px !important;
  max-width:112px;
  white-space:normal;
  word-break:normal;
  overflow-wrap:anywhere;
  line-height:1.25;
}
.rec-col-selisih-child {
  width:126px;
  min-width:126px !important;
  max-width:126px;
  white-space:normal;
  overflow-wrap:anywhere;
  line-height:1.2;
}
.rec-selisih-stack {
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:.18rem;
}
.rec-selisih-chip {
  display:inline-flex;
  max-width:100%;
  padding:.12rem .38rem;
  border-radius:999px;
  background:#fff1f2;
  border:1px solid #fecdd3;
  color:#b42318;
  font-weight:800;
  font-size:.6rem;
  line-height:1.12;
  text-align:right;
  white-space:normal;
  overflow-wrap:anywhere;
}
.rec-selisih-ok {
  display:inline-flex;
  color:#2f9e44;
  font-weight:800;
}
.rec-breakdown-table {
  min-width:1120px !important;
  table-layout:auto;
}
.rec-breakdown-actions,
.rec-action-cell {
  min-width:178px;
  white-space:normal;
}
.rec-breakdown-actions .btn,
.rec-action-cell .btn {
  line-height:1.15;
}
.rec-action-stack {
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  justify-content:center;
  gap:.28rem;
  max-width:100%;
}
.rec-action-stack .rec-icon-btn {
  flex:0 0 auto;
}
.rec-action-stack .btn {
  flex:0 0 auto;
  max-width:100%;
  white-space:nowrap;
}
.rec-action-btn {
  min-height:1.55rem;
  border-radius:.5rem;
  font-size:.59rem !important;
  padding:.12rem .38rem !important;
}
.rec-action-btn i {
  margin-right:.12rem;
}

/* -- Material audit drawer -- */
.rec-audit-drawer {
  border:1px solid rgba(225,210,199,.82); border-radius:22px;
  background:#fff; box-shadow:0 16px 34px rgba(58,38,30,.08);
}
.rec-audit-summary {
  display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.8rem; margin-bottom:1rem;
}
@media(max-width:991px) { .rec-audit-summary { grid-template-columns:repeat(2,1fr); } }
.rec-audit-metric { border:1px solid rgba(225,210,199,.82); border-radius:14px; background:#fffaf7; padding:.8rem .9rem; }
.rec-audit-metric .label { display:block; font-size:.7rem; color:#8a776f; text-transform:uppercase; letter-spacing:.04em; font-weight:800; margin-bottom:.15rem; }
.rec-audit-metric .value { font-size:1.1rem; font-weight:900; color:#2f2628; }

/* -- Pagination -- */
.rec-pagination { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; }
.rec-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 .5rem;
  border:1px solid #ddd; border-radius:8px; background:#fff; color:#555;
  font-size:.8rem; font-weight:600; text-decoration:none; transition:all .15s;
}
.rec-page-btn:hover   { background:#f5f5f5; border-color:#bbb; color:#333; }
.rec-page-btn.active  { background:#312028; border-color:#312028; color:#fff; }
.rec-page-btn.disabled { opacity:.4; pointer-events:none; }

/* -- Icon action buttons -- */
.rec-icon-btn {
  display:inline-flex; align-items:center; justify-content:center;
  width:1.75rem; height:1.75rem; padding:0; font-size:.85rem;
  border-radius:6px; border-width:1px; border-style:solid;
  background:transparent; cursor:pointer; text-decoration:none;
  transition:background .12s, color .12s;
}
.rec-icon-btn:hover { filter:brightness(.92); }

/* Reconcile is read-only for stock repair. Physical corrections use Daily Recon. */
#src_gap_repair_all_btn,
#src_lot_repair_all_btn,
.src-lot-repair-btn,
.src-log-repair-btn,
.src-material-repair-btn {
  display:none !important;
}
</style>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-git-merge-line page-title-icon"></i>Rekonsiliasi Stok Divisi</h4>
    <small class="text-muted">Patokan stok akhir mengikuti snapshot monthly divisi. Material daily dan snapshot harian diperlakukan sebagai turunan tampilan - movement mentah dipakai untuk menandai mismatch yang belum sejalan dengan stok aktif.</small>
  </div>
  <div class="text-muted small align-self-center">Acuan: <strong><?php echo html_escape($asOfDate); ?></strong></div>
</div>


<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'compare']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => date('Y-m'), 'division_id' => (string)$selDivId, 'destination_type' => $selDest],
]); ?>

<!-- -- Filter 1-row -- -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" id="rec-filter-form">
      <div class="rec-filter-grid">
        <div>
          <label class="form-label mb-1">Tanggal</label>
          <input type="date" class="form-control form-control-sm" name="as_of_date" value="<?php echo html_escape($asOfDate); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id" id="recDivision">
            <option value="0">Semua Divisi</option>
            <?php foreach ($divisions as $d): ?>
              <?php $did=(int)($d['id']??0); $dc=trim((string)($d['code']??'')); $dn=trim((string)($d['name']??($d['division_name']??$d['division_code']??''))); $dl=$dc!==''?($dc.' - '.$dn):($dn?:(string)$did); ?>
              <option value="<?php echo $did; ?>" <?php echo $selDivId===$did?'selected':''; ?>><?php echo html_escape($dl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination" id="recDestination">
            <option value="ALL"     <?php echo $selDest==='ALL'    ?'selected':''; ?>>Semua</option>
            <option value="REGULER" <?php echo $selDest==='REGULER'?'selected':''; ?>>Reguler</option>
            <option value="EVENT"   <?php echo $selDest==='EVENT'  ?'selected':''; ?>>Event</option>
            <option value="BAR"     <?php echo $selDest==='BAR'    ?'selected':''; ?>>BAR</option>
            <option value="KITCHEN" <?php echo $selDest==='KITCHEN'?'selected':''; ?>>KITCHEN</option>
            <option value="OFFICE"  <?php echo $selDest==='OFFICE' ?'selected':''; ?>>OFFICE</option>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Cari Material</label>
          <input type="text" class="form-control form-control-sm" name="q" id="rec-q-input" value="<?php echo html_escape($qSearch); ?>" placeholder="Kode / nama material" autocomplete="off">
        </div>
        <div>
          <label class="form-label mb-1">/ Hal</label>
          <input type="number" class="form-control form-control-sm" name="per_page" min="10" max="200" value="<?php echo $perPage; ?>" style="max-width:84px">
        </div>
        <div style="display:flex;gap:.4rem;align-items:flex-end">
          <button type="submit" class="btn btn-sm btn-outline-primary">Terapkan</button>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-danger">Clear</a>
        </div>
      </div>
    </form>
    <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top" style="flex-wrap:wrap">
      <span class="text-muted" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em">Filter Cepat:</span>
      <a href="<?php echo html_escape($mismatchToggleUrl); ?>"
         class="btn btn-xs <?php echo $mismatchOnly ? 'btn-warning' : 'btn-outline-secondary'; ?>"
         style="font-size:.75rem;padding:.22rem .65rem;border-radius:999px">
        <i class="ri ri-error-warning-line me-1"></i>Hanya Mismatch<?php echo $mismatchOnly ? ' &times;' : ''; ?>
      </a>
      <?php if ($mismatchOnly): ?>
      <span class="text-warning" style="font-size:.72rem;font-weight:600"><i class="ri ri-filter-3-line me-1"></i>Menampilkan <?php echo number_format($kpiMismatch); ?> baris mismatch</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- -- Warning: Monthly Stock tanpa Material ID --------------------------- -->
<?php if ($orphanTotal > 0): ?>
<div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-start gap-3" style="border-radius:8px;font-size:.82rem">
  <i class="ri ri-alert-line mt-1" style="font-size:1.1rem;color:#b45309;flex-shrink:0"></i>
  <div style="flex:1;min-width:0">
    <strong><?php echo $orphanTotal; ?> baris monthly stock tidak punya material_id</strong>
    - baris ini tersembunyi dari tabel rekonsiliasi dan tidak bisa di-Repair Lot.
    <?php if (count($orphanFixable) > 0): ?>
      <span class="text-success fw-bold"><?php echo count($orphanFixable); ?> bisa otomatis diperbaiki</span>
      (item sudah punya material di katalog):
      <span class="text-muted"><?php
        $names = array_slice(array_unique(array_column($orphanFixable, 'item_name')), 0, 5);
        echo implode(', ', array_map('htmlspecialchars', $names));
        if (count($orphanFixable) > 5) echo ' ...+' . (count($orphanFixable) - 5) . ' lainnya';
      ?></span>.
    <?php endif; ?>
    <?php if (count($orphanNoMat) > 0): ?>
      <span class="text-muted"><?php echo count($orphanNoMat); ?> item non-material</span>
      (misal kemasan, plastik) memang tidak punya kode bahan baku - tidak perlu diperbaiki.
    <?php endif; ?>
    <?php if (count($orphanFixable) > 0): ?>
    <div class="mt-2">
      <button type="button" id="src_repair_material_id_btn"
        class="btn btn-sm btn-warning" style="font-size:.77rem;padding:.25rem .7rem">
        <i class="ri ri-tools-line me-1"></i>Repair Material ID (<?php echo count($orphanFixable); ?> baris)
      </button>
      <span class="text-muted ms-2" style="font-size:.72rem">
        Mengisi material_id dari katalog item. Setelah repair, baris muncul di tabel rekonsiliasi dan Repair Lot bisa dijalankan.
      </span>
    </div>
    <?php endif; ?>
    <?php if (count($orphanNoMat) > 0): ?>
    <details class="mt-1" style="font-size:.75rem">
      <summary class="text-muted" style="cursor:pointer">Lihat item non-material (<?php echo count($orphanNoMat); ?>)</summary>
      <ul class="mb-0 mt-1" style="padding-left:1.2rem">
        <?php foreach ($orphanNoMat as $nm): ?>
        <li><?php echo html_escape((string)($nm['item_name'] ?? '-')); ?>
          @ <?php echo html_escape((string)($nm['division_name'] ?? '-')); ?>
          - <?php echo number_format((float)($nm['closing_qty_content'] ?? 0), 2); ?> sisa stok</li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- -- KPI Cards (6: 5 standard + 1 health ring) -- -->
<?php
$healthCls   = $healthPct >= 90 ? 'rec-health-ok' : ($healthPct >= 70 ? 'rec-health-warn' : 'rec-health-bad');
$ringFill    = $healthPct >= 90 ? '#69db7c' : ($healthPct >= 70 ? '#fbbf24' : '#fa5252');
?>
<div class="rec-kpi-row">
  <div class="rec-kpi rec-kpi-1">
    <span class="rec-kpi-icon"><i class="ri ri-list-check-3"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiTotal); ?></div>
    <div class="rec-kpi-sub">stok aktif (balance != 0)</div>
    <div class="rec-kpi-lbl">Total Baris</div>
  </div>
  <div class="rec-kpi rec-kpi-2">
    <span class="rec-kpi-icon"><i class="ri ri-checkbox-circle-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiMatch); ?></div>
    <div class="rec-kpi-sub">stok sejalan movement</div>
    <div class="rec-kpi-lbl">Match</div>
  </div>
  <div class="rec-kpi <?php echo $kpiMismatch > 0 ? 'rec-kpi-3-warn' : 'rec-kpi-3-ok'; ?>">
    <span class="rec-kpi-icon"><i class="ri ri-error-warning-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiMismatch); ?></div>
    <div class="rec-kpi-sub">perlu investigasi</div>
    <div class="rec-kpi-lbl">Mismatch</div>
  </div>
  <div class="rec-kpi rec-kpi-4">
    <span class="rec-kpi-icon"><i class="ri ri-arrow-down-circle-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiMinus); ?></div>
    <div class="rec-kpi-sub">stok di bawah nol</div>
    <div class="rec-kpi-lbl">Stok Minus</div>
  </div>
  <div class="rec-kpi rec-kpi-5">
    <span class="rec-kpi-icon"><i class="ri ri-building-2-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiDivCount); ?></div>
    <div class="rec-kpi-sub">divisi dengan stok aktif</div>
    <div class="rec-kpi-lbl">Divisi</div>
  </div>
  <!-- SURPRISE: Health Ring -->
  <div class="rec-health-card <?php echo $healthCls; ?>" style="--ring-pct:<?php echo $healthPct; ?>%;--ring-fill:<?php echo $ringFill; ?>">
    <div class="rec-health-ring-wrap">
      <div class="rec-health-ring">
        <div class="rec-health-ring-inner"><?php echo $healthPct; ?>%</div>
      </div>
    </div>
    <div>
      <div style="font-size:1rem;font-weight:900;line-height:1.1">
        <?php if ($healthPct >= 90): ?>Stok Sehat<?php elseif ($healthPct >= 70): ?>Perlu Perhatian<?php else: ?>Kritikal<?php endif; ?>
      </div>
      <div style="font-size:.7rem;opacity:.82;margin-top:.2rem"><?php echo $kpiMatch; ?> dari <?php echo $kpiTotal; ?> row match</div>
      <div style="font-size:.67rem;opacity:.7;text-transform:uppercase;letter-spacing:.06em;margin-top:.2rem">Health Score</div>
    </div>
  </div>
</div>

<!-- SURPRISE: Per-division mismatch bar -->
<?php if (count($divMatchMap) > 1): ?>
<div class="card mb-3">
  <div class="rec-div-bar-wrap">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <small class="fw-semibold text-muted text-uppercase" style="font-size:.68rem;letter-spacing:.05em">Distribusi Rekonsiliasi per Divisi</small>
      <small class="text-muted"><span style="color:#2f9e44">*</span> Match &nbsp; <span style="color:#c92a2a">*</span> Mismatch</small>
    </div>
    <div class="rec-div-bar">
      <?php foreach (array_slice($divMatchMap, 0, 8) as $dk => $dv):
        $tot = $dv['match'] + $dv['mismatch'];
        $mPct = $tot > 0 ? round($dv['match']    / $tot * 100) : 0;
        $xPct = $tot > 0 ? round($dv['mismatch'] / $tot * 100) : 0;
      ?>
      <div class="rec-div-bar-row">
        <span class="rec-div-bar-label" title="<?php echo html_escape($dv['label']); ?>"><?php echo html_escape($dv['label']); ?></span>
        <div class="rec-div-bar-track">
          <div class="rec-div-bar-match"    style="flex:<?php echo $mPct; ?>"></div>
          <div class="rec-div-bar-mismatch" style="flex:<?php echo $xPct; ?>"></div>
        </div>
        <span class="rec-div-bar-count"><?php echo $dv['mismatch']; ?> mismatch</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- -- POS Job Queue (collapsible) -- -->
<div class="rec-pos-panel mb-3">
  <div class="rec-pos-header" id="rec-pos-toggle">
    <span class="fw-semibold text-primary"><i class="ri ri-terminal-box-line me-2"></i>Audit POS Queue - Job Stock Commit Gagal</span>
    <span class="badge bg-primary" id="rec-pos-chevron"><i class="ri ri-arrow-down-s-line"></i></span>
  </div>
  <div class="rec-pos-body" id="rec-pos-body">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <input type="text" id="src_pos_job_q" class="form-control form-control-sm" placeholder="Cari order / commit / job / error" style="min-width:260px;max-width:380px;">
      <a href="<?php echo html_escape(site_url('pos/stock-live')); ?>" class="btn btn-sm btn-outline-secondary">Buka Stock Live POS</a>
      <button type="button" class="btn btn-sm btn-outline-primary" id="src_pos_job_reload">Refresh</button>
    </div>
    <div id="src_pos_job_list" style="display:grid;gap:.6rem"></div>
    <div id="src_pos_job_empty" class="text-muted small d-none">Tidak ada job stock commit POS yang gagal.</div>
  </div>
</div>

<!-- -- Reconcile Table -- -->
<div class="card mb-3">
  <div class="alert alert-info border-0 rounded-0 mb-0 py-2 small">
    <strong>Mode audit aman:</strong> halaman ini dipakai untuk membaca selisih dan menelusuri sumbernya.
    Koreksi saldo bulan aktif dilakukan lewat
    <a href="<?php echo html_escape(site_url('stock/daily-recon/division')); ?>" class="alert-link">Daily Recon Divisi</a>
    atau Adjustment fisik, agar stok, movement, lot, dan nilai berubah bersama.
    Perbaikan profil yang masih tersedia hanya untuk merapikan identitas data lama, bukan untuk mengoreksi saldo fisik.
  </div>
  <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold small">
      Rekonsiliasi Stok vs Movement
      <?php if ($mismatchOnly): ?><span class="badge bg-warning text-dark ms-2" style="font-size:.65rem;font-weight:700">Hanya Mismatch</span><?php endif; ?>
    </span>
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small"><?php echo number_format($totalRows); ?> baris<?php echo $mismatchOnly ? ' mismatch' : ' aktif'; ?></span>
    </div>
  </div>
  <div class="rec-table-wrap">
    <table class="table table-sm table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th style="min-width:210px">Material</th>
          <th class="text-end" style="min-width:100px">Stok Divisi</th>
          <th class="text-end" style="min-width:100px">Lot FIFO</th>
          <th class="text-end" style="min-width:100px">Material Daily</th>
          <th class="text-end" style="min-width:100px">Snapshot Harian</th>
          <th class="text-end" style="min-width:100px">Movement</th>
          <th class="rec-col-selisih">Selisih</th>
          <th style="min-width:100px;max-width:160px;white-space:normal">Status</th>
          <th style="min-width:140px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pagedRows)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4 small">Tidak ada baris untuk filter ini.</td></tr>
        <?php else: ?>
          <?php $shownBreakdownKeys = []; ?>
          <?php foreach ($pagedRows as $row): ?>
            <?php
              $isMatch      = !empty($row['is_match']);
              $isMinus      = (float)($row['balance_qty_content'] ?? 0) < 0;
              $dBVsM        = (float)($row['delta_balance_vs_movement'] ?? 0);
              $dMVsM        = (float)($row['delta_matrix_vs_movement']  ?? 0);
              $dDVsM        = (float)($row['delta_daily_vs_movement']   ?? 0);
              $lotQty       = $row['lot_qty_content'] ?? null;
              $hasLotData   = $lotQty !== null;
              $lotDelta     = (float)($row['lot_vs_balance_delta'] ?? 0);
              $hasLotMismatch = !empty($row['has_lot_mismatch']);
              $lotValueDelta = (float)($row['lot_vs_balance_value_delta'] ?? 0);
              $hasLotValueMismatch = !empty($row['has_lot_value_mismatch']);
              $asOf      = html_escape($asOfDate);
              $dataDivId = (int)($row['division_id'] ?? 0);
              $dataItemId= (int)($row['item_id']     ?? 0);
              $dataMatId = (int)($row['material_id'] ?? 0);
              $rawDest   = strtoupper(trim((string)($row['destination_type'] ?? '')));
              if ($rawDest === '') {
                  $rawDest = strtoupper(trim((string)($row['destination_group'] ?? ($selDest !== 'ALL' ? $selDest : 'ALL'))));
              }
              $dataDest  = html_escape($rawDest);
              $profileBreakdown = (array)($row['lot_profile_breakdown'] ?? []);
              $hasProfileMismatch = !empty($row['has_profile_lot_mismatch']);
              $mergeProfilesPayload = [];
              foreach ($profileBreakdown as $pbMerge) {
                  $pbMergePk = (string)($pbMerge['profile_key'] ?? '');
                  $pbMergeName = trim((string)($pbMerge['profile_name'] ?? ''));
                  $mergeProfilesPayload[] = [
                      'profile_key' => $pbMergePk !== '' ? $pbMergePk : '__EMPTY_PROFILE__',
                      'profile_key_raw' => $pbMergePk,
                      'label' => $pbMergeName !== '' ? $pbMergeName : ($pbMergePk !== '' ? substr($pbMergePk, 0, 8) . '...' : '(no profile)'),
                      'stock' => round((float)($pbMerge['stock_balance'] ?? 0), 4),
                      'lot' => round((float)($pbMerge['lot_balance'] ?? 0), 4),
                      'daily' => round((float)($pbMerge['daily_content'] ?? ($pbMerge['stock_balance'] ?? 0)), 4),
                      'movement' => isset($pbMerge['movement_content']) ? round((float)$pbMerge['movement_content'], 4) : null,
                      'has_mismatch' => !empty($pbMerge['has_mismatch']),
                  ];
              }
              $bKey = $dataDivId . '-' . $dataMatId . '-' . $dataDest;
              $parentSelisihParts = [];
              $profileLotDeltaSum = 0.0;
              $profileStockDeltaSum = 0.0;
              if ($hasLotMismatch) {
                  $sign = $lotDelta > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Lot vs Stok: ' . $sign . $fmtQty($lotDelta);
              }
              if ($hasLotValueMismatch) {
                  $sign = $lotValueDelta > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Nilai Lot vs Stok: ' . $sign . 'Rp ' . number_format(abs($lotValueDelta), 2, ',', '.');
              }
              if (abs($dBVsM) > 0.01) {
                  $sign = $dBVsM > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Stok vs Mvt: ' . $sign . $fmtQty($dBVsM);
              }
              if (abs($dMVsM) > 0.01) {
                  $sign = $dMVsM > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Mat. Daily vs Mvt: ' . $sign . $fmtQty($dMVsM);
              }
              if (abs($dDVsM) > 0.01) {
                  $sign = $dDVsM > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Snapshot vs Mvt: ' . $sign . $fmtQty($dDVsM);
              }
              if (!empty($row['daily_log_has_gap']) && abs((float)($row['daily_log_gap_content'] ?? 0)) > 0.01) {
                  $sign = ((float)($row['daily_log_gap_content'] ?? 0)) > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Gap Histori: ' . $sign . $fmtQty($row['daily_log_gap_content'] ?? 0);
              }
              if (empty($parentSelisihParts) && $hasProfileMismatch && !empty($profileBreakdown)) {
                  foreach ($profileBreakdown as $pb) {
                      if (empty($pb['has_mismatch'])) {
                          continue;
                      }
                      $profileLotDeltaSum += (float)($pb['delta'] ?? 0);
                      $profileStockDeltaSum += (float)($pb['delta_stock_vs_mvt'] ?? 0);
                  }
                  $profileLotDeltaSum = round($profileLotDeltaSum, 4);
                  $profileStockDeltaSum = round($profileStockDeltaSum, 4);
                  if (abs($profileLotDeltaSum) > 0.01) {
                      $sign = $profileLotDeltaSum > 0 ? '+' : '';
                      $parentSelisihParts[] = 'Lot vs Stok: ' . $sign . $fmtQty($profileLotDeltaSum) . ' (antar profil)';
                  }
                  if (abs($profileStockDeltaSum) > 0.01) {
                      $sign = $profileStockDeltaSum > 0 ? '+' : '';
                      $parentSelisihParts[] = 'Stok vs Mvt: ' . $sign . $fmtQty($profileStockDeltaSum) . ' (antar profil)';
                  }
                  if (empty($parentSelisihParts)) {
                      $profileValueDeltaSum = 0.0;
                      foreach ($profileBreakdown as $pb) {
                          if (empty($pb['has_value_mismatch'])) {
                              continue;
                          }
                          $profileValueDeltaSum += (float)($pb['value_delta'] ?? 0);
                      }
                      $profileValueDeltaSum = round($profileValueDeltaSum, 2);
                      if (abs($profileValueDeltaSum) > 0.01) {
                          $sign = $profileValueDeltaSum > 0 ? '+' : '';
                          $parentSelisihParts[] = 'Nilai Lot vs Stok: ' . $sign . 'Rp ' . number_format(abs($profileValueDeltaSum), 2, ',', '.') . ' (antar profil)';
                      }
                  }
                  if (empty($parentSelisihParts)) {
                      $parentSelisihParts[] = 'Mismatch antar profil';
                  }
              }
              $parentSelisihText = empty($parentSelisihParts) ? '-' : implode('; ', $parentSelisihParts);
              $parentMovementMismatch = abs((float)($row['delta_balance_vs_movement'] ?? 0)) > 0.01
                || abs((float)($row['delta_daily_vs_movement'] ?? 0)) > 0.01
                || abs((float)($row['delta_matrix_vs_movement'] ?? 0)) > 0.01
                || !empty($row['daily_log_has_gap'])
                || abs((float)$profileStockDeltaSum) > 0.01;
              $parentProfileLotMismatch = abs((float)$profileLotDeltaSum) > 0.01;
              $parentRepairLabel = 'Repair Bahan';
              $parentRepairTitle = 'Deteksi otomatis arah repair bahan ini.';
              $parentGapOnlyMismatch = !empty($row['daily_log_has_gap'])
                && abs((float)($row['delta_balance_vs_movement'] ?? 0)) <= 0.01
                && abs((float)($row['delta_daily_vs_movement'] ?? 0)) <= 0.01
                && abs((float)($row['delta_matrix_vs_movement'] ?? 0)) <= 0.01
                && !$hasLotMismatch
                && !$hasProfileMismatch;
              if ($parentGapOnlyMismatch) {
                  $parentRepairLabel = 'Repair Gap Histori';
                  $parentRepairTitle = 'Closing stok saat ini sudah match. Pilih bagaimana histori opening + delta movement dinormalkan.';
              } elseif ($hasProfileMismatch && $parentMovementMismatch && !$parentProfileLotMismatch && !$hasLotMismatch) {
                  $parentRepairLabel = 'Repair Stok/Mvt';
                  $parentRepairTitle = 'Fokus ke selisih stok profil terhadap movement log.';
              } elseif ($hasProfileMismatch && !$parentMovementMismatch && ($parentProfileLotMismatch || $hasLotValueMismatch)) {
                  $parentRepairLabel = 'Repair Profil';
                  $parentRepairTitle = 'Samakan distribusi qty / nilai lot per profil ke stok divisi.';
              } elseif ($hasLotMismatch && $parentMovementMismatch) {
                  $parentRepairLabel = 'Pilih Repair';
                  $parentRepairTitle = 'Pilih apakah truth source-nya stok saat ini atau movement log.';
              } elseif ($hasLotValueMismatch) {
                  $parentRepairLabel = 'Repair Nilai Lot';
                  $parentRepairTitle = 'Sinkronkan unit cost lot agar nilai FIFO mengikuti stok divisi.';
              } elseif ($parentMovementMismatch) {
                  $parentRepairLabel = 'Repair Stok/Mvt';
                  $parentRepairTitle = 'Samakan stok bahan dengan movement log.';
              }
            ?>
            <tr class="<?php echo $isMinus ? 'rec-row-negative' : ''; ?>">
              <td>
                <div class="fw-semibold small"><?php echo $dataMatId > 0 ? '<a href="' . html_escape(site_url('master/material/usage/' . $dataMatId)) . '" class="text-decoration-none text-body">' . html_escape($fmtText($row['material_name'] ?? '')) . '</a>' : html_escape($fmtText($row['material_name'] ?? '')); ?></div>
                <div class="text-muted" style="font-size:.7rem">
                  <?php echo html_escape($fmtText($row['material_code'] ?? '')); ?>
                   /  <?php echo html_escape($fmtText($row['division_name'] ?? '')); ?>
                   /  <?php echo html_escape($fmtText($row['destination_name'] ?? 'Reguler')); ?>
                </div>
              </td>
              <td class="text-end small">
                <div class="fw-semibold <?php echo $isMinus ? 'text-danger' : ''; ?>"><?php echo $fmtQty($row['balance_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['balance_qty_pack'] ?? 0); ?> pack</div>
                <?php if ($isMinus): ?><span class="rec-chip rec-chip-warn" style="margin-top:.2rem">Minus</span><?php endif; ?>
              </td>
              <td class="text-end small <?php echo ($hasLotMismatch || $hasLotValueMismatch || $hasProfileMismatch) ? 'table-warning' : ''; ?>">
                <?php if ($hasLotData): ?>
                  <div class="fw-semibold <?php echo ($hasLotMismatch || $hasLotValueMismatch) ? 'text-danger' : ''; ?>"><?php echo $fmtQty($lotQty); ?></div>
                  <?php if ($hasLotMismatch): ?>
                    <div style="font-size:.66rem;color:#b45309;" title="Selisih lot FIFO vs stok ledger">Delta  <?php echo $fmtQty(abs($lotDelta)); ?></div>
                    <span class="rec-chip rec-chip-bad" style="margin-top:.15rem;font-size:.6rem">Lot Beda</span>
                    <div style="margin-top:.25rem">
                      <button type="button" class="btn btn-xs btn-warning src-lot-repair-btn"
                        data-division-id="<?php echo $dataDivId; ?>"
                        data-material-id="<?php echo $dataMatId; ?>"
                        data-destination="<?php echo $dataDest; ?>"
                        style="font-size:.6rem;padding:.1rem .35rem">Repair Lot</button>
                    </div>
                  <?php endif; ?>
                  <?php if ($hasLotValueMismatch && !$hasLotMismatch): ?>
                    <div style="font-size:.66rem;color:#b45309;" title="Selisih nilai lot FIFO vs stok ledger">Delta  Rp <?php echo number_format(abs($lotValueDelta), 2, ',', '.'); ?></div>
                    <span class="rec-chip rec-chip-bad" style="margin-top:.15rem;font-size:.6rem">Nilai Beda</span>
                  <?php endif; ?>
                  <?php if ($hasProfileMismatch && !$hasLotMismatch): ?>
                    <span class="rec-chip rec-chip-bad" style="margin-top:.15rem;font-size:.6rem">Profil Beda</span>
                  <?php endif; ?>
                  <?php if (!empty($profileBreakdown)): ?>
                    <div style="margin-top:.25rem">
                      <button type="button" class="btn btn-xs btn-outline-secondary src-profile-breakdown-toggle"
                        data-breakdown-key="<?php echo html_escape($bKey); ?>"
                        style="font-size:.6rem;padding:.1rem .35rem">Profil v</button>
                    </div>
                  <?php endif; ?>
                <?php elseif ($hasLotMismatch): ?>
                  <?php /* Stock > 0 but no FIFO lots at all - repair will insert a correction lot */ ?>
                  <div class="fw-semibold text-danger">0</div>
                  <div style="font-size:.66rem;color:#b45309;" title="Stok ada tapi tidak ada lot FIFO">Delta  <?php echo $fmtQty(abs($lotDelta)); ?></div>
                  <span class="rec-chip rec-chip-bad" style="margin-top:.15rem;font-size:.6rem">Lot Kosong</span>
                  <div style="margin-top:.25rem">
                    <button type="button" class="btn btn-xs btn-warning src-lot-repair-btn"
                      data-division-id="<?php echo $dataDivId; ?>"
                      data-material-id="<?php echo $dataMatId; ?>"
                      data-destination="<?php echo $dataDest; ?>"
                      style="font-size:.6rem;padding:.1rem .35rem">Repair Lot</button>
                  </div>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-end small">
                <div><?php echo $fmtQty($row['matrix_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['matrix_qty_pack'] ?? 0); ?> pack</div>
              </td>
              <td class="text-end small">
                <div><?php echo $fmtQty($row['daily_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['daily_qty_pack'] ?? 0); ?> pack</div>
                <?php if (!empty($row['daily_audit_has_mismatch'])): ?><div class="text-danger" style="font-size:.66rem">Log liar +/-<?php echo $fmtQty($row['daily_audit_mismatch_qty_content'] ?? 0); ?></div><?php endif; ?>
                <?php if (!empty($row['daily_log_has_gap'])): ?><div class="text-warning" style="font-size:.66rem">Gap log +/-<?php echo $fmtQty($row['daily_log_gap_content'] ?? 0); ?></div><?php endif; ?>
              </td>
              <td class="text-end small">
                <div><?php echo $fmtQty($row['movement_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['movement_qty_pack'] ?? 0); ?> pack</div>
              </td>
              <td class="small rec-col-selisih" style="font-size:.66rem">
                <?php echo $renderSelisih($parentSelisihText); ?>
              </td>
              <td style="max-width:160px;word-break:break-word">
                <span class="rec-chip <?php echo $isMatch ? 'rec-chip-ok' : 'rec-chip-bad'; ?>"><?php echo $isMatch ? 'Match' : 'Mismatch'; ?></span>
                <?php if (!empty($row['daily_audit_has_mismatch'])): ?><div class="text-muted" style="font-size:.66rem;margin-top:.2rem">Log liar: <?php echo html_escape((string)($row['daily_audit_mismatch_notes'] ?? 'fallback identity')); ?></div><?php endif; ?>
                <?php if (!empty($row['daily_log_has_gap'])): ?><div class="text-muted" style="font-size:.66rem;margin-top:.2rem" title="Closing saat ini sudah match. Yang masih gap adalah histori opening + delta movement bulan berjalan terhadap closing monthly.">Gap histori log</div><?php endif; ?>
              </td>
              <td>
                <div class="rec-action-stack">
                  <button type="button" class="rec-icon-btn btn-outline-primary src-material-audit-btn"
                    data-as-of-date="<?php echo $asOf; ?>"
                    data-division-id="<?php echo $dataDivId; ?>"
                    data-item-id="<?php echo $dataItemId; ?>"
                    data-material-id="<?php echo $dataMatId; ?>"
                    data-destination="<?php echo $dataDest; ?>"
                    title="Audit selisih bahan ini"><i class="ri ri-search-eye-line"></i></button>
                  <button type="button" class="rec-icon-btn btn-outline-danger src-material-repair-btn"
                    data-as-of-date="<?php echo $asOf; ?>"
                    data-division-id="<?php echo $dataDivId; ?>"
                    data-item-id="<?php echo $dataItemId; ?>"
                    data-material-id="<?php echo $dataMatId; ?>"
                    data-destination="<?php echo $dataDest; ?>"
                    title="<?php echo html_escape($parentRepairTitle); ?>"><i class="ri ri-tools-line"></i></button>
                  <?php if (count($mergeProfilesPayload) >= 2): ?>
                  <button type="button" class="rec-icon-btn btn-outline-dark src-profile-merge-btn"
                    data-as-of-date="<?php echo $asOf; ?>"
                    data-division-id="<?php echo $dataDivId; ?>"
                    data-material-id="<?php echo $dataMatId; ?>"
                    data-destination="<?php echo $dataDest; ?>"
                    data-material-name="<?php echo html_escape((string)($row['material_name'] ?? '')); ?>"
                    data-profiles="<?php echo html_escape(json_encode($mergeProfilesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
                    title="Gabungkan beberapa child profile menjadi satu profile target"><i class="ri ri-links-line"></i></button>
                  <?php endif; ?>
                  <button type="button" class="rec-icon-btn btn-outline-warning src-quick-adj-btn"
                    data-division-id="<?php echo $dataDivId; ?>"
                    data-item-id="<?php echo $dataItemId; ?>"
                    data-material-id="<?php echo $dataMatId; ?>"
                    data-destination="<?php echo $dataDest; ?>"
                    data-destination-type="<?php echo html_escape($row['destination_type'] ?? ''); ?>"
                    data-content-uom-id="<?php echo (int)($row['content_uom_id'] ?? 0); ?>"
                    data-material-name="<?php echo html_escape($row['material_name'] ?? ''); ?>"
                    data-system-qty="<?php echo (float)($row['balance_qty_content'] ?? 0); ?>"
                    data-avg-cost="<?php echo (float)($row['avg_cost_per_content'] ?? 0); ?>"
                    title="Adjustment manual stok bahan ini"><i class="ri ri-scales-3-line"></i></button>
                  <a href="<?php echo html_escape(site_url('inventory/stock/division/movement') . '?q=' . rawurlencode($row['material_name'] ?? '') . '&division_id=' . $dataDivId . '&destination=' . rawurlencode($dataDest)); ?>"
                    class="rec-icon-btn btn-outline-info" target="_blank" title="Lihat movement log bahan ini"><i class="ri ri-history-line"></i></a>
                  <a href="<?php echo html_escape(site_url('inventory/stock/division/lot') . '?q=' . rawurlencode($row['material_name'] ?? '') . '&division_id=' . $dataDivId . '&destination=' . rawurlencode($dataDest)); ?>"
                    class="rec-icon-btn btn-outline-secondary" target="_blank" title="Lihat lot FIFO bahan ini"><i class="ri ri-stack-line"></i></a>
                </div>
              </td>
            </tr>
            <?php if ($hasLotData && !empty($profileBreakdown) && !isset($shownBreakdownKeys[$bKey])): ?>
              <?php $shownBreakdownKeys[$bKey] = true; ?>
              <tr class="rec-profile-breakdown-row" data-breakdown-key="<?php echo html_escape($bKey); ?>" style="display:<?php echo $hasProfileMismatch ? '' : 'none'; ?>">
                <td colspan="9" style="padding:.2rem .5rem .5rem 1.8rem;background:#f0f4f8;border-top:none;">
                  <div style="font-size:.62rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;padding:.3rem 0 .2rem">Breakdown per Profil</div>
                  <div style="overflow-x:auto">
                  <table class="table table-sm table-borderless mb-0 rec-breakdown-table" style="font-size:.68rem;">
                    <thead>
                      <tr style="border-bottom:2px solid #cbd5e1;background:#e2e8f0;">
                        <th style="min-width:140px;font-weight:700;padding:.3rem .5rem">Profil</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Stok Divisi</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Lot FIFO</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Mat. Daily</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Movement</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px" title="Selisih closing monthly_stock vs (opening + sum movement_log). Bukan nol = ada movement phantom atau hilang.">Log Gap</th>
                        <th class="rec-col-selisih-child" style="font-weight:700;padding:.3rem .4rem">Selisih</th>
                        <th class="text-center" style="font-weight:700;padding:.3rem .4rem;min-width:72px">Status</th>
                        <th class="text-center rec-action-cell" style="font-weight:700;padding:.3rem .4rem">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($profileBreakdown as $pb): ?>
                        <?php
                          $pbPk     = (string)($pb['profile_key'] ?? '');
                          $pbName   = trim((string)($pb['profile_name'] ?? ''));
                          $pbLabel  = $pbName !== '' ? $pbName : ($pbPk !== '' ? substr($pbPk, 0, 8).'...' : '(no profile)');
                          $pbStock    = (float)($pb['stock_balance'] ?? 0);
                          $pbLot      = (float)($pb['lot_balance'] ?? 0);
                          $pbDaily    = isset($pb['daily_content']) ? (float)$pb['daily_content'] : $pbStock;
                          $pbMvt      = isset($pb['movement_content']) ? (float)$pb['movement_content'] : 0.0;
                          $pbHasMvt   = isset($pb['movement_content']);
                          $pbLogGap   = round((float)($pb['log_gap_content'] ?? 0), 4);
                          $pbLogBad   = !empty($pb['log_has_gap']);
                          $pbDStk     = round($pbStock - $pbMvt, 4);
                          $pbDLot     = round($pbStock - $pbLot, 4);
                          $pbStockValue = round((float)($pb['stock_value'] ?? 0), 2);
                          $pbLotValue   = round((float)($pb['lot_value'] ?? 0), 2);
                          $pbDVal       = round((float)($pb['value_delta'] ?? 0), 2);
                          $pbValBad     = !empty($pb['has_value_mismatch']);
                          $dStkBad    = $pbHasMvt && abs($pbDStk) > 0.01;
                          $dLotBad    = abs($pbDLot) > 0.01;
                          $pbMis      = $dStkBad || $dLotBad || $pbLogBad || $pbValBad;

                          // Build smart Selisih text
                          $selisihParts = [];
                          if ($dLotBad) {
                              $sign = $pbDLot > 0 ? '+' : '';
                              $selisihParts[] = 'Lot vs Stok: ' . $sign . $fmtQty($pbDLot);
                          }
                          if ($dStkBad) {
                              $sign = $pbDStk > 0 ? '+' : '';
                              $selisihParts[] = 'Stok vs Mvt: ' . $sign . $fmtQty($pbDStk);
                          }
                          if ($pbLogBad) {
                              $sign = $pbLogGap > 0 ? '+' : '';
                              $selisihParts[] = 'Log Gap: ' . $sign . $fmtQty($pbLogGap);
                          }
                          if ($pbValBad) {
                              $sign = $pbDVal > 0 ? '+' : '';
                              $selisihParts[] = 'Nilai Lot vs Stok: ' . $sign . 'Rp ' . number_format(abs($pbDVal), 2, ',', '.');
                          }
                          $selisihText = empty($selisihParts) ? '-' : implode('; ', $selisihParts);

                          $pbStatusLbl = $pbMis ? 'Mismatch' : 'Match';
                          $pbStatusCls = $pbMis ? 'rec-chip-bad' : 'rec-chip-ok';
                          $profileRepairLabel = 'Repair';
                          $profileRepairTitle = 'Repair profil ini.';
                          if (($dLotBad || $pbValBad) && !$dStkBad) {
                              $profileRepairLabel = 'Repair Lot';
                              $profileRepairTitle = $pbValBad && !$dLotBad
                                  ? 'Samakan nilai lot profil ini ke stok divisi.'
                                  : 'Samakan lot profil ini ke stok divisi.';
                          } elseif (!$dLotBad && !$pbValBad && $dStkBad) {
                              $profileRepairLabel = 'Repair Mvt';
                              $profileRepairTitle = 'Samakan stok profil ini ke movement log.';
                          } elseif (($dLotBad || $pbValBad) && $dStkBad) {
                              $profileRepairLabel = 'Pilih Repair';
                              $profileRepairTitle = 'Pilih apakah profil ini mengikuti stok saat ini atau movement log.';
                          }
                        ?>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                          <td style="padding:.3rem .5rem">
                            <div style="font-weight:600"><?php echo html_escape($pbLabel); ?></div>
                            <code title="<?php echo html_escape($pbPk); ?>" style="font-size:.59rem;color:#94a3b8"><?php echo html_escape(substr($pbPk,0,12)); ?><?php echo strlen($pbPk)>12?'...':''; ?></code>
                          </td>
                          <td class="text-end" style="padding:.3rem .4rem;font-weight:600;<?php echo $pbStock < 0 ? 'color:#b42318' : ''; ?>"><?php echo $fmtQty($pbStock); ?></td>
                          <td class="text-end" style="padding:.3rem .4rem;<?php echo $dLotBad ? 'color:#b42318;font-weight:600' : ''; ?>"><?php echo $fmtQty($pbLot); ?></td>
                          <td class="text-end" style="padding:.3rem .4rem"><?php echo $fmtQty($pbDaily); ?></td>
                          <td class="text-end" style="padding:.3rem .4rem<?php echo !$pbHasMvt ? ';color:#94a3b8' : ''; ?>"><?php echo $pbHasMvt ? $fmtQty($pbMvt) : '-'; ?></td>
                          <td class="text-end" style="padding:.3rem .4rem;<?php
                            echo $pbLogBad
                              ? ($pbLogGap > 0 ? 'color:#1d4ed8;font-weight:600' : 'color:#b42318;font-weight:600')
                              : 'color:#94a3b8'; ?>">
                            <?php if ($pbLogBad): ?>
                              <?php echo ($pbLogGap > 0 ? '+' : '') . $fmtQty($pbLogGap); ?>
                            <?php else: ?>-<?php endif; ?>
                          </td>
                          <td class="rec-col-selisih-child" style="padding:.3rem .4rem;font-size:.63rem">
                            <?php echo $renderSelisih($selisihText); ?>
                          </td>
                          <td class="text-center rec-breakdown-actions" style="padding:.3rem .4rem">
                            <span class="rec-chip <?php echo $pbStatusCls; ?>" style="font-size:.59rem"><?php echo $pbStatusLbl; ?></span>
                          </td>
                          <td class="text-center rec-action-cell" style="padding:.3rem .4rem">
                            <div class="rec-action-stack">
                            <?php if ($pbMis): ?>
                            <button type="button" class="btn btn-xs btn-outline-warning rec-action-btn src-profile-repair-btn"
                              data-as-of-date="<?php echo html_escape($asOfDate); ?>"
                              data-division-id="<?php echo $dataDivId; ?>"
                              data-material-id="<?php echo $dataMatId; ?>"
                              data-destination="<?php echo html_escape($dataDest); ?>"
                              data-profile-key="<?php echo html_escape($pbPk); ?>"
                              data-profile-name="<?php echo html_escape($pbLabel); ?>"
                              data-stock="<?php echo $pbStock; ?>"
                              data-lot="<?php echo $pbLot; ?>"
                              data-daily="<?php echo $pbDaily; ?>"
                              data-movement="<?php echo $pbHasMvt ? $pbMvt : ''; ?>"
                              data-has-mvt="<?php echo $pbHasMvt ? '1' : '0'; ?>"
                              data-d-lot="<?php echo $pbDLot; ?>"
                              data-d-stk="<?php echo $pbDStk; ?>"
                              data-stock-value="<?php echo $pbStockValue; ?>"
                              data-lot-value="<?php echo $pbLotValue; ?>"
                              data-d-value="<?php echo $pbDVal; ?>"
                              title="<?php echo html_escape($profileRepairTitle); ?>"
                              style="font-size:.59rem;padding:.1rem .35rem"><?php echo html_escape($profileRepairLabel); ?></button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-xs btn-outline-primary rec-action-btn src-quick-adj-btn"
                              data-division-id="<?php echo $dataDivId; ?>"
                              data-item-id="<?php echo $dataItemId; ?>"
                              data-material-id="<?php echo $dataMatId; ?>"
                              data-destination="<?php echo html_escape($dataDest); ?>"
                              data-destination-type="<?php echo html_escape($row['destination_type'] ?? ''); ?>"
                              data-content-uom-id="<?php echo (int)($row['content_uom_id'] ?? 0); ?>"
                              data-material-name="<?php echo html_escape($row['material_name'] ?? ''); ?>"
                              data-system-qty="<?php echo $pbStock; ?>"
                              data-avg-cost="<?php echo (float)($row['avg_cost_per_content'] ?? 0); ?>"
                              data-profile-key="<?php echo html_escape($pbPk); ?>"
                              data-profile-name="<?php echo html_escape($pbLabel); ?>"
                              title="Adjustment manual profil ini"
                              style="font-size:.59rem;padding:.1rem .35rem"><i class="ri ri-scales-3-line"></i> Adj</button>
                            <?php if ($pbLogBad): ?>
                            <button type="button" class="btn btn-xs btn-outline-danger rec-action-btn src-log-repair-btn"
                              data-division-id="<?php echo $dataDivId; ?>"
                              data-material-id="<?php echo $dataMatId; ?>"
                              data-destination="<?php echo html_escape($dataDest); ?>"
                              data-content-uom-id="<?php echo (int)($row['content_uom_id'] ?? 0); ?>"
                              data-material-name="<?php echo html_escape($row['material_name'] ?? ''); ?>"
                              data-profile-key="<?php echo html_escape($pbPk); ?>"
                              data-profile-name="<?php echo html_escape($pbLabel); ?>"
                              data-log-gap="<?php echo $pbLogGap; ?>"
                              data-stock="<?php echo $pbStock; ?>"
                              data-avg-cost="<?php echo (float)($row['avg_cost_per_content'] ?? 0); ?>"
                              title="Repair log gap: tambah entry ADJUSTMENT ke movement_log supaya opening + sum delta foot ke closing monthly_stock"
                              style="font-size:.59rem;padding:.1rem .35rem"><i class="ri ri-git-pull-request-line"></i> Repair Log</button>
                            <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination footer -->
  <?php if ($totalRows > 0): ?>
  <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <?php $fromR = ($page-1)*$perPage+1; $toR = min($page*$perPage,$totalRows); ?>
    <span class="text-muted small">Baris <?php echo "{$fromR}-{$toR}"; ?> dari <?php echo number_format($totalRows); ?></span>
    <?php if ($totalPages > 1): ?>
    <div class="rec-pagination">
      <?php
        $ws = max(1,$page-2); $we = min($totalPages,$page+2);
        if ($we-$ws < 4) { if ($ws===1) $we=min($totalPages,$ws+4); else $ws=max(1,$we-4); }
      ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page-1)); ?>" class="rec-page-btn<?php echo $page>1?'':' disabled'; ?>">&#8249;</a>
      <?php if ($ws>1): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page=1'); ?>" class="rec-page-btn">1</a><?php if ($ws>2): ?><span class="text-muted small px-1">...</span><?php endif; ?><?php endif; ?>
      <?php for ($pn=$ws;$pn<=$we;$pn++): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$pn); ?>" class="rec-page-btn<?php echo $pn===$page?' active':''; ?>"><?php echo $pn; ?></a><?php endfor; ?>
      <?php if ($we<$totalPages): ?><?php if ($we<$totalPages-1): ?><span class="text-muted small px-1">...</span><?php endif; ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$totalPages); ?>" class="rec-page-btn"><?php echo $totalPages; ?></a><?php endif; ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page+1)); ?>" class="rec-page-btn<?php echo $page<$totalPages?'':' disabled'; ?>">&#8250;</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- -- Material Audit Drawer -- -->
<div class="rec-audit-drawer p-3 p-lg-4" id="src_material_audit_card">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
      <div class="small text-uppercase fw-bold text-muted">Lacak Selisih per Bahan</div>
      <h5 class="mb-1 mt-1">Detail Audit Material</h5>
      <div class="text-muted small">Klik tombol <strong>Audit</strong> pada salah satu baris untuk melihat bucket OPENING, PO, SR, VOID, REFUND, ADJUSTMENT, POS, dan detail movement sumbernya.</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger d-none" id="src_material_repair_current">Repair Bahan Ini</button>
  </div>
  <div id="src_material_audit_state" class="text-muted small">Belum ada bahan yang dipilih. Klik tombol Audit di tabel di atas.</div>
  <div id="src_material_audit_body" class="d-none">
    <div id="src_material_audit_summary" class="rec-audit-summary mb-3"></div>
    <div class="table-responsive mb-3">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Profil</th>
            <th class="text-end">Opening Monthly</th>
            <th class="text-end">Closing Bulan Lalu</th>
            <th class="text-end">Delta Log</th>
            <th class="text-end">Closing Monthly</th>
            <th class="text-end">Gap</th>
            <th>Saran Repair</th>
          </tr>
        </thead>
        <tbody id="src_material_audit_gap_profiles"></tbody>
      </table>
    </div>
    <div class="table-responsive mb-3">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Bucket</th>
            <th class="text-end">Jumlah Log</th>
            <th class="text-end">Delta Content</th>
            <th class="text-end">Delta Buy</th>
            <th class="text-end">Nilai Mutasi</th>
            <th>Log Terakhir</th>
          </tr>
        </thead>
        <tbody id="src_material_audit_buckets"></tbody>
      </table>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Tanggal</th><th>No</th><th>Sumber</th>
            <th class="text-end">Before</th><th class="text-end">Delta</th><th class="text-end">After</th>
            <th>Jenis</th><th>Catatan</th>
          </tr>
        </thead>
        <tbody id="src_material_audit_movements"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const inventoryDivisionReconcileCsrfToken = <?php echo json_encode((string)($inventory_division_reconcile_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const posTransactionCsrfToken = <?php echo json_encode((string)($pos_transaction_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  // -- POS accordion ----------------------------------------------------------
  var posToggle  = document.getElementById('rec-pos-toggle');
  var posBody    = document.getElementById('rec-pos-body');
  var posChevron = document.getElementById('rec-pos-chevron');
  if (posToggle && posBody) {
    posToggle.addEventListener('click', function () {
      var open = posBody.classList.toggle('open');
      if (posChevron) posChevron.innerHTML = open ? '<i class="ri ri-arrow-up-s-line"></i>' : '<i class="ri ri-arrow-down-s-line"></i>';
      if (open) loadFailedJobs().catch(function(e){ showAlert(e.message,'Audit POS Queue'); });
    });
  }

  // -- Search: submit only on Enter -----------------------------------------
  var qInput = document.getElementById('rec-q-input');
  if (qInput) {
    qInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('rec-filter-form')?.submit();
      }
    });
  }

  // -- Destination guard ----------------------------------------------------
  var divEl  = document.getElementById('recDivision');
  var destEl = document.getElementById('recDestination');
  var guardMap = <?php echo json_encode($destGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  if (divEl && destEl) {
    function applyDestGuard() {
      var did = String(divEl.value || '');
      var allowed = guardMap[did] || [];
      Array.prototype.forEach.call(destEl.options, function (opt) {
        var v = String(opt.value || '').toUpperCase();
        if (!did || v === 'ALL' || v === 'REGULER' || v === 'EVENT') { opt.hidden = false; opt.disabled = false; return; }
        var keep = allowed.indexOf(v) >= 0;
        opt.hidden = !keep; opt.disabled = !keep;
      });
      if (destEl.selectedOptions[0] && destEl.selectedOptions[0].disabled) destEl.value = 'ALL';
    }
    divEl.addEventListener('change', applyDestGuard);
    applyDestGuard();
  }

  // -- Shared helpers --------------------------------------------------------
  var listEl      = document.getElementById('src_pos_job_list');
  var emptyEl     = document.getElementById('src_pos_job_empty');
  var searchEl    = document.getElementById('src_pos_job_q');
  var reloadBtn   = document.getElementById('src_pos_job_reload');
  var auditCard   = document.getElementById('src_material_audit_card');
  var auditState  = document.getElementById('src_material_audit_state');
  var auditBody   = document.getElementById('src_material_audit_body');
  var auditSumEl  = document.getElementById('src_material_audit_summary');
  var auditGapEl  = document.getElementById('src_material_audit_gap_profiles');
  var auditBktEl  = document.getElementById('src_material_audit_buckets');
  var auditMvtEl  = document.getElementById('src_material_audit_movements');
  var repairCurBtn = document.getElementById('src_material_repair_current');
  var searchTimer = null;
  var currentMaterialIdentity = null;
  var repairDecisionModal = document.getElementById('repairDecisionModal');
  var repairDecisionBs = repairDecisionModal ? (typeof bootstrap !== 'undefined' ? new bootstrap.Modal(repairDecisionModal) : null) : null;

  function escHtml(v) { return String(v??'').replace(/[&<>"']/g,function(m){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);}); }
  function fmtQty(v) { return new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:4}).format(Number(v||0)); }
  function fmtDT(v) { if(!v)return'-'; var d=new Date(String(v).replace(' ','T')); return isNaN(d.getTime())?escHtml(String(v)):new Intl.DateTimeFormat('id-ID',{dateStyle:'medium',timeStyle:'short'}).format(d); }
  function showAlert(msg,title) { if(window.FinanceUI&&typeof window.FinanceUI.alert==='function')return Promise.resolve(window.FinanceUI.alert(msg,{title:title||'Informasi'})); return Promise.resolve(); }
  function askConfirm(msg,opts) { if(window.FinanceUI&&typeof window.FinanceUI.confirm==='function')return Promise.resolve(window.FinanceUI.confirm(msg,opts||{})); return showAlert('Modal konfirmasi tidak tersedia.','UI Belum Siap').then(function(){return false;}); }
  function showFlare(message, type) {
    var hostId = 'reconcile-flare-host';
    var host = document.getElementById(hostId);
    if (!host) {
      host = document.createElement('div');
      host.id = hostId;
      host.style.position = 'fixed';
      host.style.top = '18px';
      host.style.right = '18px';
      host.style.zIndex = '2000';
      host.style.display = 'flex';
      host.style.flexDirection = 'column';
      host.style.gap = '10px';
      document.body.appendChild(host);
    }
    var palette = type === 'danger'
      ? { bg: '#7f1d1d', bd: '#ef4444' }
      : { bg: '#14532d', bd: '#22c55e' };
    var flare = document.createElement('div');
    flare.style.minWidth = '280px';
    flare.style.maxWidth = '420px';
    flare.style.padding = '12px 14px';
    flare.style.borderRadius = '14px';
    flare.style.color = '#fff';
    flare.style.background = palette.bg;
    flare.style.border = '1px solid ' + palette.bd;
    flare.style.boxShadow = '0 12px 34px rgba(15,23,42,.22)';
    flare.style.fontSize = '.88rem';
    flare.style.lineHeight = '1.45';
    flare.style.opacity = '0';
    flare.style.transform = 'translateY(-8px)';
    flare.style.transition = 'all .22s ease';
    flare.textContent = String(message || '');
    host.appendChild(flare);
    requestAnimationFrame(function () {
      flare.style.opacity = '1';
      flare.style.transform = 'translateY(0)';
    });
    setTimeout(function () {
      flare.style.opacity = '0';
      flare.style.transform = 'translateY(-8px)';
      setTimeout(function () { flare.remove(); }, 240);
    }, 1800);
  }
  async function getJson(url) {
    var r = await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});
    var t = await r.text(); var j=null; try{j=JSON.parse(t);}catch(e){throw new Error('Response tidak valid: '+String(t||'').slice(0,180));}
    if(!r.ok||!j.ok) throw new Error(j.message||'Gagal memuat data.'); return j;
  }
  async function postJson(url,payload) {
    var r = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload||{})});
    var t = await r.text(); var j=null; try{j=JSON.parse(t);}catch(e){throw new Error('Response tidak valid: '+String(t||'').slice(0,180));}
    if(!r.ok||(!j.ok && !j.needs_choice)) throw new Error(j.message||'Gagal memproses.'); return j;
  }
  async function postPosTransactionJson(url,payload) {
    var r = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-Pos-Transaction-CSRF':posTransactionCsrfToken},body:JSON.stringify(payload||{})});
    var t = await r.text(); var j=null; try{j=JSON.parse(t);}catch(e){throw new Error('Response tidak valid: '+String(t||'').slice(0,180));}
    if(!r.ok||!j.ok) throw new Error(j.message||'Gagal memproses.'); return j;
  }
  async function postInventoryReconcileJson(url,payload) {
    var r = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-Inventory-Reconcile-CSRF':inventoryDivisionReconcileCsrfToken},body:JSON.stringify(payload||{})});
    var t = await r.text(); var j=null; try{j=JSON.parse(t);}catch(e){throw new Error('Response tidak valid: '+String(t||'').slice(0,180));}
    if(!r.ok||(!j.ok && !j.needs_choice)) throw new Error(j.message||'Gagal memproses.'); return j;
  }
  function setButtonLoading(btn,lbl) {
    if(!btn)return; if(window.FinanceUI&&typeof window.FinanceUI.setButtonLoading==='function'){window.FinanceUI.setButtonLoading(btn,lbl);return;}
    btn.dataset.orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>'+escHtml(lbl||'...');
  }
  function clearButtonLoading(btn) {
    if(!btn)return; if(window.FinanceUI&&typeof window.FinanceUI.clearButtonLoading==='function'){window.FinanceUI.clearButtonLoading(btn);return;}
    btn.disabled=false; if(btn.dataset.orig){btn.innerHTML=btn.dataset.orig;delete btn.dataset.orig;}
  }
  function buttonIdentity(btn) {
    return {as_of_date:String(btn?.dataset.asOfDate||''),division_id:Number(btn?.dataset.divisionId||0),item_id:Number(btn?.dataset.itemId||0),material_id:Number(btn?.dataset.materialId||0),destination:String(btn?.dataset.destination||'ALL')};
  }
  function setAuditState(msg,loading) {
    auditState.textContent=msg; auditBody.classList.add('d-none'); auditState.classList.remove('d-none');
    if(repairCurBtn){repairCurBtn.classList.toggle('d-none',!currentMaterialIdentity||!!loading);repairCurBtn.disabled=!!loading||!currentMaterialIdentity;}
  }

  function hideRepairDecisionModal() {
    if (!repairDecisionModal) return;
    if (repairDecisionBs) { repairDecisionBs.hide(); return; }
    if (typeof $ !== 'undefined') { $(repairDecisionModal).modal('hide'); return; }
    repairDecisionModal.style.display = 'none';
    repairDecisionModal.classList.remove('show');
  }

  function openRepairDecisionModal(identity, response, sourceButton) {
    if (!repairDecisionModal) {
      showAlert('Pilihan repair membutuhkan modal, tetapi modal belum tersedia.', 'Repair Bahan');
      return;
    }
    var plan = response?.data?.repair_plan || {};
    var summary = response?.data?.summary || {};
    repairDecisionModal.dataset.identity = JSON.stringify(identity || {});
    repairDecisionModal.dataset.sourceButtonId = sourceButton?.id || '';

    var titleEl = document.getElementById('repairDecisionTitle');
    var bodyEl = document.getElementById('repairDecisionBody');
    var actionEl = document.getElementById('repairDecisionActions');
    if (titleEl) {
      titleEl.textContent = 'Pilih Arah Repair - ' + String(summary.material_name || 'Bahan');
    }
    var repairModeLabels = {
      noop: 'Tidak ada repair',
      lot_repair: 'Repair Lot',
      profile_sync: 'Repair Profil',
      profile_to_movement: 'Repair Stok/Mvt per Profil',
      repair_log_gap_to_stock: 'Pakai Stok Saat Ini',
      repair_log_gap_opening: 'Repair Gap Opening',
      rebuild_from_movement: 'Repair Stok dari Movement',
      rebuild_then_lot_repair: 'Ikuti Movement lalu Repair Lot',
      rebuild_then_profile_sync: 'Ikuti Movement lalu Repair Profil'
    };
    if (bodyEl) {
      var verdict = String(summary.suspect_table || 'UNKNOWN');
      var reason = String(summary.suspect_reason || response.message || '');
      var recommended = String(plan.recommended_mode || '');
      bodyEl.innerHTML =
        '<div class="small text-muted mb-2">Sistem mendeteksi mismatch yang masih ambigu.</div>' +
        '<div class="border rounded p-2 mb-2" style="background:#fffaf5">' +
          '<div class="small"><strong>Verdict:</strong> ' + escHtml(verdict) + '</div>' +
          '<div class="small mt-1"><strong>Analisis:</strong> ' + escHtml(reason) + '</div>' +
          (recommended ? '<div class="small mt-1"><strong>Rekomendasi:</strong> ' + escHtml(repairModeLabels[recommended] || recommended) + '</div>' : '') +
        '</div>';
    }
    if (actionEl) {
      var choices = Array.isArray(plan.choices) ? plan.choices : [];
      actionEl.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary" data-role="cancel">Batal</button>' +
        choices.map(function(choice){
          var recommendedBadge = choice.recommended ? ' <span class="badge bg-warning text-dark ms-1">Rekomendasi</span>' : '';
          var btnClass = choice.recommended ? 'btn-danger' : 'btn-outline-primary';
          return '<button type="button" class="btn btn-sm ' + btnClass + ' repair-choice-btn"'
            + ' data-mode="' + escHtml(String(choice.mode || '')) + '">'
            + escHtml(String(choice.label || choice.mode || 'Pilih')) + recommendedBadge + '</button>'
            + '<div class="w-100 small text-muted" style="margin-top:-.25rem;margin-bottom:.35rem">' + escHtml(String(choice.description || '')) + '</div>';
        }).join('');
    }

    if (repairDecisionBs) { repairDecisionBs.show(); }
    else if (typeof $ !== 'undefined') { $(repairDecisionModal).modal('show'); }
    else { repairDecisionModal.style.display = 'block'; repairDecisionModal.classList.add('show'); }
  }

  // -- Material audit render ------------------------------------------------
  function renderMaterialAudit(identity,json) {
    currentMaterialIdentity=identity;
    var summary=json.summary||{}; var buckets=Array.isArray(json.buckets)?json.buckets:[]; var movements=Array.isArray(json.movements)?json.movements:[]; var gapProfiles=Array.isArray(json.gap_profiles)?json.gap_profiles:[];
    auditState.classList.add('d-none'); auditBody.classList.remove('d-none');
    if(repairCurBtn){repairCurBtn.classList.remove('d-none');repairCurBtn.disabled=false;}
    auditSumEl.innerHTML=[
      ['Material', escHtml(summary.material_name||'-'), escHtml(summary.material_code||'-')],
      ['Verdict',  escHtml(summary.suspect_table||'MATCH'), escHtml(summary.suspect_reason||'Semua tabel masih sinkron.')],
      ['Stok vs Mvt', fmtQty(summary.delta_balance_vs_movement||0),'selisih content'],
      ['Daily vs Mvt', fmtQty(summary.delta_daily_vs_movement||0),'closing daily '+escHtml(summary.daily_date||'-')],
      ['Gap Log', fmtQty(summary.daily_log_gap_content||0), (summary.daily_log_has_gap ? 'opening + delta log tidak foot ke closing monthly' : 'tidak ada gap')],
      ['Identity Repair', escHtml(String(json.repair_identity_count||0)),'identity sumber'],
    ].map(function(it){return '<div class="rec-audit-metric"><span class="label">'+it[0]+'</span><div class="value">'+it[1]+'</div><div class="text-muted small">'+it[2]+'</div></div>';}).join('');
    var gapPathLabel = {
      NO_GAP: 'Sudah foot',
      SEED_OPENING_FROM_PREV_MONTH_CLOSING: 'Isi opening dari closing bulan lalu',
      REVIEW_MOVEMENT_HISTORY: 'Review histori movement',
      NO_MONTH_MOVEMENT_REVIEW_MONTHLY: 'Review monthly tanpa movement bulan ini'
    };
    auditGapEl.innerHTML=gapProfiles.map(function(g){
      var gap = Number(g.gap_from_monthly_opening||0);
      var gapCls = Math.abs(gap) > 0.01 ? 'text-danger fw-bold' : 'text-success fw-bold';
      return '<tr>'
        + '<td>'+escHtml(g.profile_name||g.profile_key||'-')+'<div class="text-muted small">'+escHtml(String(g.profile_key||'').slice(0,12))+'</div></td>'
        + '<td class="text-end">'+fmtQty(g.monthly_opening_qty_content||0)+'</td>'
        + '<td class="text-end">'+(g.prev_closing_qty_content==null?'<span class="text-muted">-</span>':fmtQty(g.prev_closing_qty_content))+'</td>'
        + '<td class="text-end">'+fmtQty(g.net_non_opening_delta||0)+'</td>'
        + '<td class="text-end">'+fmtQty(g.monthly_closing_qty_content||0)+'</td>'
        + '<td class="text-end '+gapCls+'">'+(gap>0?'+':'')+fmtQty(gap)+'</td>'
        + '<td>'+escHtml(gapPathLabel[g.suggested_repair_path] || g.suggested_repair_path || '-')+'</td>'
        + '</tr>';
    }).join('')||'<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada gap movement log per profil.</td></tr>';
    auditBktEl.innerHTML=buckets.map(function(b){return '<tr><td>'+escHtml(b.bucket_label||b.bucket_code||'-')+'</td><td class="text-end">'+Number(b.count||0)+'</td><td class="text-end">'+fmtQty(b.delta_content||0)+'</td><td class="text-end">'+fmtQty(b.delta_buy||0)+'</td><td class="text-end">'+fmtQty(b.mutation_value||0)+'</td><td>'+escHtml(b.last_movement_date||'-')+'<div class="text-muted small">'+escHtml(b.last_movement_no||'-')+'</div></td></tr>';}).join('')||'<tr><td colspan="6" class="text-center text-muted py-3">Belum ada bucket log.</td></tr>';
    auditMvtEl.innerHTML=movements.map(function(r){return '<tr><td>'+escHtml(r.movement_date||'-')+'</td><td>'+escHtml(r.movement_no||'-')+'</td><td>'+escHtml(r.source_label||'-')+'<div class="text-muted small">'+escHtml(r.source_bucket_label||'-')+'</div></td><td class="text-end">'+fmtQty(r.qty_content_before||0)+'</td><td class="text-end">'+fmtQty(r.qty_content_delta||0)+'</td><td class="text-end">'+fmtQty(r.qty_content_after||0)+'</td><td>'+escHtml(r.movement_type_label||r.movement_type||'-')+'</td><td>'+escHtml(r.notes||'-')+'</td></tr>';}).join('')||'<tr><td colspan="8" class="text-center text-muted py-3">Belum ada movement sumber.</td></tr>';
    auditCard.scrollIntoView({behavior:'smooth',block:'start'});
  }
  async function loadMaterialAudit(identity) {
    currentMaterialIdentity=identity;
    setAuditState('Memuat audit bahan...',true);
    var p=new URLSearchParams(); Object.keys(identity||{}).forEach(function(k){p.set(k,String(identity[k]??''));});
    var json=await getJson('<?php echo $auditUrl; ?>?'+p.toString());
    renderMaterialAudit(identity,json);
  }
  async function runMaterialRepair(identity,button,forceMode) {
    var ok=await askConfirm(
      forceMode
        ? 'Sistem akan memaksa repair mengikuti pilihan yang Anda tentukan. Lanjutkan?'
        : 'Sistem akan mendeteksi masalah lalu menjalankan repair yang paling cocok. Jika ambigu, Anda akan diminta memilih truth source. Lanjutkan?',
      {title:'Repair Reconcile Bahan',confirmText:'Repair',cancelText:'Batal'}
    );
    if(!ok)return;
    setButtonLoading(button,'Repair...');
    setAuditState('Menjalankan repair bahan...',true);
    try {
      var payload = Object.assign({}, identity || {});
      if (forceMode) payload.force_mode = forceMode;
      var json=await postJson('<?php echo $repairUrl; ?>', payload);
      if (json && json.needs_choice) {
        clearButtonLoading(button);
        setAuditState('Memerlukan keputusan user untuk menentukan truth source repair.', false);
        openRepairDecisionModal(identity, json, button);
        return;
      }
      await showAlert(json.message||'Repair selesai.','Repair Reconcile Bahan');
      window.location.reload();
    } catch(e) { clearButtonLoading(button); throw e; }
  }
  async function runLotRepair(identity,button) {
    var ok=await askConfirm('Repair lot FIFO akan menyesuaikan saldo lot agar cocok dengan saldo stok ledger. Lanjutkan?',{title:'Repair Lot FIFO',confirmText:'Repair Lot',cancelText:'Batal'});
    if(!ok)return;
    var orig=button.innerHTML; button.disabled=true; button.textContent='Repair...';
    try {
      var json=await postJson('<?php echo $lotRepairUrl; ?>',{division_id:identity.division_id,material_id:identity.material_id,destination:identity.destination});
      if(json.needs_profile_sync) {
        button.disabled=false; button.innerHTML=orig;
        var doSync=await askConfirm(
          (json.message||'Ada ketidaksesuaian profil lot.')+'\n\nSamakan lot per profil sekarang?',
          {title:'Profil Lot Silang Terdeteksi',confirmText:'Ya, Samakan Profil',cancelText:'Batal'}
        );
        if(!doSync)return;
        button.disabled=true; button.textContent='Sinkronisasi...';
        var syncJson=await postJson('<?php echo $lotProfileSyncUrl; ?>',{division_id:identity.division_id,material_id:identity.material_id,destination:identity.destination});
        await showAlert(syncJson.message||'Sinkronisasi profil selesai.','Samakan Lot per Profil');
        window.location.reload();
      } else {
        await showAlert(json.message||'Lot repair selesai.','Repair Lot FIFO');
        window.location.reload();
      }
    } catch(e) { button.disabled=false; button.innerHTML=orig; throw e; }
  }

  // -- Repair Lot Semua ------------------------------------------------------
  var gapRepairAllBtn = document.getElementById('src_gap_repair_all_btn');
  if (gapRepairAllBtn) {
    gapRepairAllBtn.addEventListener('click', async function() {
      var ok = await askConfirm(
        'Repair semua gap movement log yang anchor-nya aman sesuai filter aktif?\n\nSaat ini sistem hanya akan memperbaiki kasus yang bisa dipulihkan dari closing bulan sebelumnya. Kasus lain tetap dibiarkan untuk review manual.',
        {title:'Repair Gap Opening', confirmText:'Repair Gap', cancelText:'Batal'}
      );
      if (!ok) return;
      var orig = gapRepairAllBtn.innerHTML;
      gapRepairAllBtn.disabled = true; gapRepairAllBtn.textContent = 'Memproses...';
      try {
        var json = await postJson('<?php echo $gapRepairAllUrl; ?>', {
          as_of_date:  '<?php echo $asOfDate; ?>',
          division_id: <?php echo (int)$selDivId; ?>,
          destination: '<?php echo addslashes($selDest ?? 'ALL'); ?>'
        });
        var data = json.data || {};
        var detail = json.message || 'Selesai.';
        if (Array.isArray(data.updated_profiles) && data.updated_profiles.length) {
          detail += '\n\nDirepair:\n' + data.updated_profiles.slice(0, 12).map(function(r) {
            return ' -  ' + (r.profile_name || r.profile_key || ('monthly#' + r.monthly_id)) + ' - ' + (r.repair_path || '-');
          }).join('\n');
        }
        if (Array.isArray(data.skipped_profiles) && data.skipped_profiles.length) {
          detail += '\n\nMasih perlu review:\n' + data.skipped_profiles.slice(0, 12).map(function(r) {
            return ' -  ' + (r.profile_name || r.profile_key || ('monthly#' + r.monthly_id)) + ' - ' + (r.repair_path || '-');
          }).join('\n');
        }
        await showAlert(detail, 'Repair Gap Opening');
        window.location.reload();
      } catch(e) {
        gapRepairAllBtn.disabled = false; gapRepairAllBtn.innerHTML = orig;
        await showAlert(e.message || 'Gagal menjalankan repair gap opening.', 'Repair Gap Opening');
      }
    });
  }

  // -- Repair Lot Semua ------------------------------------------------------
  var lotRepairAllBtn = document.getElementById('src_lot_repair_all_btn');
  if (lotRepairAllBtn) {
    lotRepairAllBtn.addEventListener('click', async function() {
      var ok = await askConfirm(
        'Repair semua lot FIFO yang mismatch sesuai filter aktif?\n\nProses ini otomatis menjalankan profile sync jika ada profil silang.',
        {title:'Repair Lot Semua', confirmText:'Repair Semua', cancelText:'Batal'}
      );
      if (!ok) return;
      var orig = lotRepairAllBtn.innerHTML;
      lotRepairAllBtn.disabled = true; lotRepairAllBtn.textContent = 'Memproses...';
      try {
        var payload = {
          as_of_date:  '<?php echo $asOfDate; ?>',
          division_id: <?php echo (int)$selDivId; ?>,
          destination: '<?php echo addslashes($selDest ?? 'ALL'); ?>',
          q:           <?php echo json_encode($qSearch ?? ''); ?>
        };
        var json = await postJson('<?php echo $lotRepairAllUrl; ?>', payload);
        var d = json.data || {};
        var detail = json.message || 'Selesai.';
        if ((d.failed||0) > 0 && Array.isArray(d.results)) {
          var failLines = d.results.filter(function(r){return r.status==='failed';})
            .map(function(r){return ' -  '+r.label+(r.message?' - '+r.message:'');});
          if (failLines.length) detail += '\n\nGagal:\n' + failLines.join('\n');
        }
        await showAlert(detail, 'Repair Lot Semua');
        window.location.reload();
      } catch(e) {
        lotRepairAllBtn.disabled = false; lotRepairAllBtn.innerHTML = orig;
        await showAlert(e.message || 'Gagal menjalankan repair lot semua.', 'Repair Lot Semua');
      }
    });
  }

  // -- Repair Material ID ---------------------------------------------------
  var repairMatIdBtn = document.getElementById('src_repair_material_id_btn');
  if (repairMatIdBtn) {
    repairMatIdBtn.addEventListener('click', async function() {
      var ok = await askConfirm(
        'Repair Material ID akan mengisi kolom material_id di monthly stock untuk item yang sudah punya data bahan baku di katalog.\n\nProses ini aman dan tidak mengubah nilai stok.',
        {title: 'Repair Material ID', confirmText: 'Repair', cancelText: 'Batal'}
      );
      if (!ok) return;
      var orig = repairMatIdBtn.innerHTML;
      repairMatIdBtn.disabled = true; repairMatIdBtn.textContent = 'Memproses...';
      try {
        var json = await postInventoryReconcileJson('<?php echo $repairMaterialIdUrl; ?>', {
          division_id: <?php echo (int)($selDivId ?? 0); ?>
        });
        await showAlert(
          (json.message || 'Selesai.') + '\n\n' + (json.data && json.data.repaired ? json.data.repaired + ' baris diperbaiki.' : ''),
          'Repair Material ID'
        );
        window.location.reload();
      } catch(e) {
        repairMatIdBtn.disabled = false; repairMatIdBtn.innerHTML = orig;
        await showAlert(e.message || 'Gagal repair material ID.', 'Repair Material ID');
      }
    });
  }

  // -- POS Jobs --------------------------------------------------------------
  function jobBadge(status) {
    var v=String(status||'').toUpperCase(); var m={FAILED:['danger','FAILED'],QUEUED:['info','QUEUED'],PROCESSING:['warning','PROCESSING'],SUCCESS:['success','SUCCESS'],CANCELLED:['secondary','CANCELLED']};
    var r=m[v]||['secondary',v||'-']; return '<span class="badge bg-'+r[0]+'">'+escHtml(r[1])+'</span>';
  }
  async function loadFailedJobs() {
    var p=new URLSearchParams(); p.set('q',String(searchEl?(searchEl.value||''):'')); p.set('limit','8');
    var json=await getJson('<?php echo site_url('pos/orders/runtime-jobs/failed'); ?>?'+p.toString());
    var rows=Array.isArray(json.rows)?json.rows:[];
    if(!rows.length){listEl.innerHTML='';emptyEl.classList.remove('d-none');return;}
    emptyEl.classList.add('d-none');
    listEl.innerHTML=rows.map(function(row){return '<div class="card p-3 mb-0"><div class="d-flex justify-content-between flex-wrap gap-2"><div><div class="fw-semibold small">'+escHtml(row.order_no||'-')+' | '+escHtml(row.commit_no||'-')+'</div><div class="text-muted small">Job '+escHtml(row.job_code||'-')+'  /  '+escHtml(row.outlet_name||'-')+'  /  '+escHtml(row.cashier_employee_name||'-')+'</div><div class="text-muted small">'+Number(row.attempts||0)+'/'+Number(row.max_attempts||0)+' percobaan  /  '+fmtDT(row.run_after||row.updated_at||row.created_at||'')+'</div></div><div class="d-flex flex-column align-items-end gap-1">'+jobBadge(row.status)+'<span class="badge bg-secondary">Stok '+escHtml(row.stock_commit_status||'-')+'</span><button type="button" class="btn btn-xs btn-outline-primary mt-1 src-pos-job-retry" data-job-id="'+Number(row.id||0)+'">Retry</button></div></div><div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">'+escHtml(row.last_error||'Job gagal tanpa detail.')+'</div></div>';}).join('');
  }

  if(searchEl){searchEl.addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){loadFailedJobs().catch(function(e){showAlert(e.message,'Audit POS Queue');});},260);});}
  if(reloadBtn){reloadBtn.addEventListener('click',function(){loadFailedJobs().catch(function(e){showAlert(e.message,'Audit POS Queue');});});}
  if(listEl){listEl.addEventListener('click',async function(ev){
    var retryBtn=ev.target.closest('.src-pos-job-retry'); if(!retryBtn)return;
    var ok=await askConfirm('Retry job stock commit POS ini?',{title:'Retry Stock Commit POS',confirmText:'Retry',cancelText:'Batal'}); if(!ok)return;
    var orig=retryBtn.innerHTML; retryBtn.disabled=true; retryBtn.textContent='Retry...';
    try{await postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/'+encodeURIComponent(retryBtn.dataset.jobId||'0'),{});await loadFailedJobs();}catch(e){await showAlert(e.message,'Retry Stock Commit POS');}finally{retryBtn.disabled=false;retryBtn.innerHTML=orig;}
  });}
  if(repairCurBtn){repairCurBtn.addEventListener('click',function(){if(!currentMaterialIdentity)return;runMaterialRepair(currentMaterialIdentity,repairCurBtn).catch(function(e){clearButtonLoading(repairCurBtn);setAuditState(e.message,false);});});}
  document.addEventListener('click',function(ev){
    var ab=ev.target.closest('.src-material-audit-btn');
    if(ab){loadMaterialAudit(buttonIdentity(ab)).catch(function(e){setAuditState(e.message,false);}); return;}
    var rb=ev.target.closest('.src-material-repair-btn');
    if(rb){runMaterialRepair(buttonIdentity(rb),rb).catch(function(e){clearButtonLoading(rb);setAuditState(e.message,false);}); return;}
    var lb=ev.target.closest('.src-lot-repair-btn');
    if(lb){runLotRepair(buttonIdentity(lb),lb).catch(function(e){showAlert(e.message,'Repair Lot FIFO');});}
    var pb=ev.target.closest('.src-profile-breakdown-toggle');
    if(pb){
      var key=pb.getAttribute('data-breakdown-key');
      var bdRows=document.querySelectorAll('.rec-profile-breakdown-row[data-breakdown-key="'+key+'"]');
      bdRows.forEach(function(r){r.style.display=r.style.display==='none'?'':'none';});
      pb.textContent=pb.textContent.includes('v')?pb.textContent.replace('v','^'):pb.textContent.replace('^','v');
    }
    var prb=ev.target.closest('.src-profile-repair-btn');
    if(prb){ openProfileRepairModal(prb); }
    var pmb=ev.target.closest('.src-profile-merge-btn');
    if(pmb){ openProfileMergeModal(pmb); }
  });

  // -- Profile Repair Modal --------------------------------------------------
  var profileRepairUrl = <?php echo json_encode($profileRepairUrl); ?>;
  var profileMergeUrl  = <?php echo json_encode($profileMergeUrl); ?>;
  var prModal = document.getElementById('profileRepairModal');
  var prModalBs = prModal ? (typeof bootstrap !== 'undefined' ? new bootstrap.Modal(prModal) : null) : null;
  var pmModal = document.getElementById('profileMergeModal');
  var pmModalBs = pmModal ? (typeof bootstrap !== 'undefined' ? new bootstrap.Modal(pmModal) : null) : null;

  function openProfileRepairModal(btn) {
    if (!prModal) { showAlert('Modal tidak tersedia.', 'Error'); return; }
    var pk       = btn.dataset.profileKey  || '';
    var pname    = btn.dataset.profileName || (pk.slice(0,8)+'...');
    var stock    = Number(btn.dataset.stock  || 0);
    var lot      = Number(btn.dataset.lot    || 0);
    var daily    = Number(btn.dataset.daily  || 0);
    var stockValue = Number(btn.dataset.stockValue || 0);
    var lotValue   = Number(btn.dataset.lotValue   || 0);
    var hasMvt   = btn.dataset.hasMvt === '1';
    var mvt      = hasMvt ? Number(btn.dataset.movement || 0) : null;
    var dLot     = Number(btn.dataset.dLot || 0);  // stock - lot
    var dStk     = hasMvt ? Number(btn.dataset.dStk || 0) : null; // stock - mvt
    var dVal     = Number(btn.dataset.dValue || 0); // stock value - lot value
    var lotBad   = Math.abs(dLot) > 0.01;
    var mvtBad   = hasMvt && Math.abs(dStk) > 0.01;
    var valBad   = Math.abs(dVal) > 0.01;

    // Store context on modal for use in repair
    prModal.dataset.divisionId  = btn.dataset.divisionId  || '0';
    prModal.dataset.materialId  = btn.dataset.materialId  || '0';
    prModal.dataset.destination = btn.dataset.destination || 'ALL';
    prModal.dataset.profileKey  = pk;
    prModal.dataset.asOfDate    = btn.dataset.asOfDate    || '';

    // Populate header
    var titleEl = document.getElementById('prModalTitle');
    if (titleEl) titleEl.textContent = 'Repair Profil - ' + pname;

    // Build analysis HTML
    var rows = [
      ['Stok Divisi', fmtQty(stock), '(monthly_stock closing)'],
      ['Lot FIFO',    fmtQty(lot),   lotBad ? '<span style="color:#b42318;font-weight:700">Selisih ' + (dLot > 0 ? '+' : '') + fmtQty(dLot) + '</span>' : 'OK'],
      ['Nilai Stok',  'Rp ' + fmtQty(stockValue), '(monthly_stock total_value)'],
      ['Nilai Lot',   'Rp ' + fmtQty(lotValue), valBad ? '<span style="color:#b42318;font-weight:700">Selisih ' + (dVal > 0 ? '+' : '') + 'Rp ' + fmtQty(Math.abs(dVal)) + '</span>' : 'OK'],
      ['Mat. Daily',  fmtQty(daily), '(= Stok Divisi)'],
      ['Movement',    hasMvt ? fmtQty(mvt) : '-', hasMvt && mvtBad ? '<span style="color:#b42318;font-weight:700">Selisih ' + (dStk > 0 ? '+' : '') + fmtQty(dStk) + '</span>' : (hasMvt ? 'OK' : 'Tidak ada data movement')],
    ];
    var analysisEl = document.getElementById('prModalAnalysis');
    if (analysisEl) {
      analysisEl.innerHTML = '<table style="width:100%;font-size:.8rem;border-collapse:collapse">'
        + rows.map(function(r){return '<tr><td style="padding:.3rem .5rem;font-weight:600;width:110px">'+escHtml(r[0])+'</td><td style="padding:.3rem .5rem;text-align:right;width:90px">'+escHtml(r[1])+'</td><td style="padding:.3rem .5rem;color:#64748b">'+r[2]+'</td></tr>';}).join('')
        + '</table>';
    }

    // Build action section
    var actionEl = document.getElementById('prModalActions');
    var toStockBtn   = document.getElementById('prRepairToStock');
    var toMvtBtn     = document.getElementById('prRepairToMovement');
    var explanEl     = document.getElementById('prModalExplanation');

    if (actionEl && toStockBtn && toMvtBtn && explanEl) {
      if (!lotBad && !mvtBad && !valBad) {
        // Shouldn't happen (button only shows when mismatch)
        explanEl.innerHTML = '<div class="alert alert-success py-2 mb-0">Tidak ada selisih yang perlu direpair.</div>';
        toStockBtn.style.display = 'none';
        toMvtBtn.style.display   = 'none';
      } else if ((lotBad || valBad) && !mvtBad) {
        // Only lot mismatch - unambiguous: repair lot to match stock
        var dir = lotBad
          ? (dLot > 0 ? 'dikurangi ' + fmtQty(dLot) : 'ditambah CORR lot ' + fmtQty(Math.abs(dLot)))
          : ('diubah cost-nya sebesar Rp ' + fmtQty(Math.abs(dVal)));
        explanEl.innerHTML = '<div class="alert alert-warning py-2 mb-0 small">'
          + (lotBad
              ? 'Lot FIFO tidak sesuai stok. Lot akan ' + escHtml(dir) + ' agar cocok dengan stok <strong>' + escHtml(fmtQty(stock)) + '</strong>.'
              : 'Qty lot sudah sama, tetapi nilai lot FIFO tidak sesuai stok. Sistem akan sinkronkan <strong>unit cost</strong> lot agar nilai lot cocok dengan stok <strong>Rp ' + escHtml(fmtQty(stockValue)) + '</strong>.')
          + '</div>';
        toStockBtn.style.display = '';
        toStockBtn.textContent   = valBad && !lotBad ? 'Repair Nilai Lot -> Ikuti Stok' : 'Repair Lot -> Ikuti Stok';
        toMvtBtn.style.display   = 'none';
      } else if (!lotBad && !valBad && mvtBad) {
        // Only stock vs movement mismatch: only movement-based repair changes data
        explanEl.innerHTML = '<div class="alert alert-info py-2 mb-0 small">Lot sudah sama dengan stok. Yang perlu dibetulkan adalah <strong>stok profil</strong> agar mengikuti closing <strong>movement</strong> (<strong>' + escHtml(fmtQty(mvt)) + '</strong>).</div>';
        toStockBtn.style.display = 'none';
        toMvtBtn.style.display   = '';
        toMvtBtn.textContent     = 'Repair Stok -> Movement (' + fmtQty(mvt) + ')';
      } else {
        // Both mismatched
        explanEl.innerHTML = '<div class="alert alert-danger py-2 mb-0 small">'
          + (valBad && !lotBad
              ? 'Nilai lot (<strong>Rp ' + escHtml(fmtQty(lotValue)) + '</strong>) tidak cocok stok (<strong>Rp ' + escHtml(fmtQty(stockValue)) + '</strong>), dan stok berbeda dari movement (<strong>' + escHtml(fmtQty(mvt)) + '</strong>). Pilih referensi:'
              : 'Lot (<strong>' + escHtml(fmtQty(lot)) + '</strong>) tidak cocok stok (<strong>' + escHtml(fmtQty(stock)) + '</strong>), dan stok berbeda dari movement (<strong>' + escHtml(fmtQty(mvt)) + '</strong>). Pilih referensi:')
          + '</div>';
        toStockBtn.style.display = '';
        toStockBtn.textContent   = valBad && !lotBad ? 'Pertahankan Nilai Stok' : 'Pertahankan Stok (' + fmtQty(stock) + ')';
        toMvtBtn.style.display   = '';
        toMvtBtn.textContent     = 'Ikuti Movement (' + fmtQty(mvt) + ')';
      }
    }

    if (prModalBs) { prModalBs.show(); }
    else if (typeof $ !== 'undefined') { $(prModal).modal('show'); }
    else { prModal.style.display = 'block'; prModal.classList.add('show'); }
  }

  async function runProfileRepair(repairMode) {
    var divisionId  = Number(prModal.dataset.divisionId  || 0);
    var materialId  = Number(prModal.dataset.materialId  || 0);
    var destination = prModal.dataset.destination || 'ALL';
    var profileKey  = prModal.dataset.profileKey  || '';
    var asOfDate    = prModal.dataset.asOfDate    || '';

    var actionBtn = repairMode === 'to_movement'
      ? document.getElementById('prRepairToMovement')
      : document.getElementById('prRepairToStock');

    if (prModalBs) { prModalBs.hide(); }
    else if (typeof $ !== 'undefined') { $(prModal).modal('hide'); }

    setButtonLoading(actionBtn, 'Repair...');
    try {
      var json = await postInventoryReconcileJson(profileRepairUrl, {
        division_id: divisionId,
        material_id: materialId,
        destination: destination,
        profile_key: profileKey,
        repair_mode: repairMode,
        as_of_date:  asOfDate,
      });
      await showAlert(json.message || 'Repair selesai.', 'Repair Profil');
      window.location.reload();
    } catch(e) {
      clearButtonLoading(actionBtn);
      await showAlert(e.message || 'Gagal repair profil.', 'Repair Profil');
    }
  }

  var prToStockBtn = document.getElementById('prRepairToStock');
  var prToMvtBtn   = document.getElementById('prRepairToMovement');
  if (prToStockBtn) { prToStockBtn.addEventListener('click', function() { runProfileRepair('lot_repair'); }); }
  if (prToMvtBtn)   { prToMvtBtn.addEventListener('click',   function() { runProfileRepair('to_movement'); }); }

  function recalcProfileMergeSelection() {
    if (!pmModal) return;
    var targetKey = '';
    var targetRadio = pmModal.querySelector('input[name="pm_target_profile"]:checked');
    if (targetRadio) targetKey = String(targetRadio.value || '');
    pmModal.querySelectorAll('.pm-source-check').forEach(function(cb) {
      var isTarget = String(cb.value || '') === targetKey;
      cb.disabled = isTarget;
      if (isTarget) cb.checked = false;
    });
  }

  function openProfileMergeModal(btn) {
    if (!pmModal) {
      showAlert('Modal join profile belum tersedia.', 'Join Profile');
      return;
    }
    var profiles = [];
    try { profiles = JSON.parse(btn.dataset.profiles || '[]'); } catch (e) { profiles = []; }
    if (!Array.isArray(profiles) || profiles.length < 2) {
      showAlert('Minimal harus ada 2 child profile untuk digabung.', 'Join Profile');
      return;
    }

    pmModal.dataset.divisionId = btn.dataset.divisionId || '0';
    pmModal.dataset.materialId = btn.dataset.materialId || '0';
    pmModal.dataset.destination = btn.dataset.destination || 'ALL';
    pmModal.dataset.asOfDate = btn.dataset.asOfDate || '';
    pmModal.dataset.materialName = btn.dataset.materialName || '';

    var titleEl = document.getElementById('pmModalTitle');
    if (titleEl) {
      titleEl.textContent = 'Join Profile - ' + (btn.dataset.materialName || 'Bahan');
    }

    var defaultTarget = profiles.slice().sort(function(a, b) {
      return Math.abs(Number(b.stock || 0)) - Math.abs(Number(a.stock || 0));
    })[0] || profiles[0];

    var rowsHtml = profiles.map(function(profile) {
      var key = String(profile.profile_key || '');
      var rawKey = String(profile.profile_key_raw || '');
      var isTarget = key === String(defaultTarget.profile_key || '');
      return '<tr>'
        + '<td class="text-center"><input type="radio" name="pm_target_profile" value="' + escHtml(key) + '" ' + (isTarget ? 'checked' : '') + '></td>'
        + '<td class="text-center"><input type="checkbox" class="pm-source-check" value="' + escHtml(key) + '" ' + (!isTarget ? 'checked' : '') + '></td>'
        + '<td><div class="fw-semibold">' + escHtml(profile.label || rawKey || '(no profile)') + '</div><code style="font-size:.62rem;color:#94a3b8">' + escHtml(rawKey || '(blank)') + '</code></td>'
        + '<td class="text-end">' + escHtml(fmtQty(profile.stock || 0)) + '</td>'
        + '<td class="text-end">' + escHtml(fmtQty(profile.lot || 0)) + '</td>'
        + '<td class="text-end">' + (profile.movement === null ? '-' : escHtml(fmtQty(profile.movement || 0))) + '</td>'
        + '</tr>';
    }).join('');

    var rowsEl = document.getElementById('pmModalRows');
    if (rowsEl) rowsEl.innerHTML = rowsHtml;
    var helpEl = document.getElementById('pmModalHelp');
    if (helpEl) {
      helpEl.innerHTML = 'Pilih <strong>1 profil target</strong>, lalu centang profil child yang akan dipindah ke target tersebut. Sistem akan memindahkan identity di <strong>movement log</strong>, <strong>monthly stock</strong>, dan <strong>FIFO lot</strong>. Snapshot cost / HPP live lama yang terlanjur terbentuk sebelum stok masuk akan diabaikan.';
    }

    pmModal.querySelectorAll('input[name="pm_target_profile"]').forEach(function(radio) {
      radio.addEventListener('change', recalcProfileMergeSelection);
    });
    recalcProfileMergeSelection();

    if (pmModalBs) { pmModalBs.show(); }
    else if (typeof $ !== 'undefined') { $(pmModal).modal('show'); }
    else { pmModal.style.display = 'block'; pmModal.classList.add('show'); }
  }

  async function runProfileMerge() {
    if (!pmModal) return;
    var targetRadio = pmModal.querySelector('input[name="pm_target_profile"]:checked');
    if (!targetRadio) {
      await showAlert('Pilih profil target terlebih dahulu.', 'Join Profile');
      return;
    }
    var sourceProfileKeys = [];
    pmModal.querySelectorAll('.pm-source-check:checked').forEach(function(cb) {
      sourceProfileKeys.push(String(cb.value || ''));
    });
    if (!sourceProfileKeys.length) {
      await showAlert('Pilih minimal 1 source profile yang mau digabung.', 'Join Profile');
      return;
    }

    var submitBtn = document.getElementById('pmSubmitBtn');
    setButtonLoading(submitBtn, 'Join...');
    try {
      var json = await postInventoryReconcileJson(profileMergeUrl, {
        division_id: Number(pmModal.dataset.divisionId || 0),
        material_id: Number(pmModal.dataset.materialId || 0),
        destination: String(pmModal.dataset.destination || 'ALL'),
        target_profile_key: String(targetRadio.value || ''),
        source_profile_keys: sourceProfileKeys,
        as_of_date: String(pmModal.dataset.asOfDate || '')
      });
      if (pmModalBs) { pmModalBs.hide(); }
      else if (typeof $ !== 'undefined') { $(pmModal).modal('hide'); }
      clearButtonLoading(submitBtn);
      showFlare(json.message || 'Join profile selesai.', 'success');
      setTimeout(function () { window.location.reload(); }, 900);
    } catch (e) {
      clearButtonLoading(submitBtn);
      await showAlert(e.message || 'Gagal join profile.', 'Join Profile');
    }
  }

  var pmSubmitBtn = document.getElementById('pmSubmitBtn');
  if (pmSubmitBtn) {
    pmSubmitBtn.addEventListener('click', runProfileMerge);
  }

  // -- Quick Adjustment Manual dari Reconcile -------------------------------
  var qAdjModal   = document.getElementById('quickAdjModal');
  var qAdjModalBs = qAdjModal && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(qAdjModal) : null;
  var qAdjUrl     = <?php echo json_encode(site_url('inventory/stock/daily-recon/division/quick-adjust')); ?>;

  var qaReasonMap = <?php echo json_encode([
    'ADJUSTMENT_MINUS' => ['over_usage' => 'Over Usage', 'unrecorded_usage' => 'Unrecorded Usage', 'counting_error' => 'Counting Error', 'system_mismatch' => 'System Mismatch', 'other' => 'Other'],
    'ADJUSTMENT_PLUS'  => ['opening_correction' => 'Opening Correction', 'stock_found' => 'Stock Found', 'manual_reclass' => 'Manual Reclass', 'other' => 'Other'],
    'WASTE'            => ['cancel_order' => 'Cancel Order', 'kitchen_error' => 'Kitchen Error', 'overproduction' => 'Overproduction', 'spillage' => 'Spillage / Tumpah', 'prep_trim_excess' => 'Prep Trim Excess', 'expired_opened' => 'Expired Opened', 'other' => 'Other'],
    'SPOIL'            => ['expired' => 'Expired', 'temperature_abuse' => 'Temperature Abuse', 'contamination' => 'Contamination', 'overstock' => 'Overstock', 'improper_storage' => 'Improper Storage', 'other' => 'Other'],
    'PROCESS_LOSS'     => ['defrost_loss' => 'Defrost Loss', 'trimming_standard' => 'Trimming Standard', 'cooking_loss' => 'Cooking Loss', 'evaporation' => 'Evaporation', 'brew_loss' => 'Brew Loss', 'other' => 'Other'],
    'VARIANCE'         => ['over_usage' => 'Over Usage', 'under_usage' => 'Under Usage', 'unrecorded_usage' => 'Unrecorded Usage', 'counting_error' => 'Counting Error', 'system_mismatch' => 'System Mismatch', 'unknown_shrinkage' => 'Unknown Shrinkage', 'other' => 'Other'],
  ], JSON_UNESCAPED_UNICODE); ?>;

  var validAdjDests = ['BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER'];
  function qaMapDest(d) { return validAdjDests.indexOf(String(d).toUpperCase()) >= 0 ? String(d).toUpperCase() : 'OTHER'; }

  function qaUpdateReasonOpts(adjType) {
    var sel = document.getElementById('qaReason');
    if (!sel) return;
    var opts = qaReasonMap[adjType] || {'other': 'Other'};
    sel.innerHTML = Object.entries(opts).map(function(kv) {
      return '<option value="' + kv[0] + '">' + kv[1] + '</option>';
    }).join('');
    sel.value = 'other';
    var hppRow = document.getElementById('qaHppRow');
    if (hppRow) hppRow.style.display = adjType === 'ADJUSTMENT_PLUS' ? '' : 'none';
  }

  function qaFmtQty(v) { var n = parseFloat(v); return isNaN(n) ? '0' : n.toLocaleString('id-ID', {maximumFractionDigits:4}); }

  function qaUpdateSelisih() {
    var sysQty  = parseFloat(qAdjModal?.dataset.systemQty || 0);
    var physQty = parseFloat(document.getElementById('qaPhysicalQty')?.value || '');
    var sdEl    = document.getElementById('qaSelisihDisplay');
    var atEl    = document.getElementById('qaAdjType');
    if (isNaN(physQty)) {
      if (sdEl) sdEl.textContent = '-';
      return;
    }
    var delta = physQty - sysQty;
    if (sdEl) {
      var sign = delta > 0 ? '+' : '';
      sdEl.textContent = sign + qaFmtQty(delta);
      sdEl.style.color = delta > 0 ? '#2563eb' : delta < 0 ? '#b42318' : '#6b7280';
    }
    // Auto-suggest adj type hanya bila user belum manually memilih
    if (atEl && !atEl.dataset.manuallySet) {
      var suggestedType;
      if (delta > 0)      suggestedType = 'ADJUSTMENT_PLUS';
      else if (delta < 0) suggestedType = 'ADJUSTMENT_MINUS';
      else                suggestedType = atEl.value;
      if (suggestedType !== atEl.value) {
        atEl.value = suggestedType;
        qaUpdateReasonOpts(suggestedType);
      }
    }
  }

  var qaPhysEl = document.getElementById('qaPhysicalQty');
  if (qaPhysEl) { qaPhysEl.addEventListener('input', qaUpdateSelisih); }

  var qaAdjTypeEl = document.getElementById('qaAdjType');
  if (qaAdjTypeEl) {
    qaAdjTypeEl.addEventListener('change', function() {
      this.dataset.manuallySet = '1';
      qaUpdateReasonOpts(this.value);
    });
  }

  document.addEventListener('click', function(ev) {
    var btn = ev.target.closest('.src-quick-adj-btn');
    if (!btn) return;
    var divId        = Number(btn.dataset.divisionId      || 0);
    var matId        = Number(btn.dataset.materialId      || 0);
    var itemId       = Number(btn.dataset.itemId          || 0);
    var dest         = String(btn.dataset.destination     || 'OTHER');
    var destType     = String(btn.dataset.destinationType || '');
    var contentUomId = Number(btn.dataset.contentUomId    || 0);
    var matName      = String(btn.dataset.materialName    || '');
    var sysQty       = parseFloat(btn.dataset.systemQty  || 0);
    var avgCost      = parseFloat(btn.dataset.avgCost     || 0);
    var profileKey   = String(btn.dataset.profileKey      || '');
    var profileName  = String(btn.dataset.profileName     || '');
    var g = function(id) { return document.getElementById(id); };
    if (g('qaMatName'))  g('qaMatName').textContent  = matName;
    if (g('qaSubtitle')) g('qaSubtitle').textContent = profileName ? 'Profil: ' + profileName : '';
    if (g('qaSaldoSaatIni')) g('qaSaldoSaatIni').textContent = qaFmtQty(sysQty);
    if (g('qaSelisihDisplay')) g('qaSelisihDisplay').textContent = '-';
    if (g('qaPhysicalQty')) { g('qaPhysicalQty').value = ''; }
    if (g('qaNotes'))      g('qaNotes').value          = '';
    if (g('qaDate'))       g('qaDate').value           = new Date().toISOString().slice(0, 10);
    var defaultType = sysQty < 0 ? 'ADJUSTMENT_PLUS' : 'ADJUSTMENT_MINUS';
    if (g('qaAdjType'))  { g('qaAdjType').value = defaultType; delete g('qaAdjType').dataset.manuallySet; }
    qaUpdateReasonOpts(defaultType);
    var ucEl = document.getElementById('qaUnitCost');
    if (ucEl) ucEl.value = avgCost > 0 ? avgCost : '';
    if (qAdjModal) {
      qAdjModal.dataset.divisionId     = divId;
      qAdjModal.dataset.materialId     = matId;
      qAdjModal.dataset.itemId         = itemId;
      qAdjModal.dataset.destination    = dest;
      qAdjModal.dataset.destinationType = destType;
      qAdjModal.dataset.contentUomId   = contentUomId;
      qAdjModal.dataset.systemQty      = sysQty;
      qAdjModal.dataset.profileKey     = profileKey;
    }
    if (qAdjModalBs) { qAdjModalBs.show(); } else if (qAdjModal) { qAdjModal.style.display = 'flex'; }
    setTimeout(function() { var pq = document.getElementById('qaPhysicalQty'); if (pq) pq.focus(); }, 350);
  });

  var qaSubmitBtn = document.getElementById('qaSubmitBtn');
  if (qaSubmitBtn) {
    qaSubmitBtn.addEventListener('click', async function() {
      var g        = function(id) { return document.getElementById(id); };
      var physQty  = parseFloat(g('qaPhysicalQty')?.value || '');
      var sysQty   = parseFloat(qAdjModal?.dataset.systemQty   || 0);
      var adjType  = g('qaAdjType')?.value  || 'ADJUSTMENT_MINUS';
      var reason   = g('qaReason')?.value   || 'other';
      var unitCost = parseFloat(g('qaUnitCost')?.value || 0);
      var notes    = (g('qaNotes')?.value   || '').trim();
      var date     = g('qaDate')?.value     || new Date().toISOString().slice(0, 10);
      var divId        = Number(qAdjModal?.dataset.divisionId       || 0);
      var matId        = Number(qAdjModal?.dataset.materialId       || 0);
      var itemId       = Number(qAdjModal?.dataset.itemId           || 0);
      var contentUomId = Number(qAdjModal?.dataset.contentUomId     || 0);
      var profileKey   = String(qAdjModal?.dataset.profileKey       || '');
      var destType     = qaMapDest(qAdjModal?.dataset.destinationType || qAdjModal?.dataset.destination || 'OTHER');
      if (divId <= 0) { await showAlert('Division tidak valid.', 'Adjustment'); return; }
      if (contentUomId <= 0) { await showAlert('UOM bahan tidak ditemukan. Tidak bisa menyimpan adjustment.', 'Adjustment'); return; }
      if (isNaN(physQty)) { await showAlert('Saldo target belum diisi.', 'Adjustment'); g('qaPhysicalQty')?.focus(); return; }
      if (physQty === sysQty) { await showAlert('Saldo target sama dengan saldo saat ini. Selisih = 0, tidak ada yang di-adjust.', 'Adjustment'); return; }
      setButtonLoading(qaSubmitBtn, 'Menyimpan...');
      try {
        var payload = {
          opname_date:          date,
          division_id:          divId,
          destination_type:     destType,
          identity_key:         'rec_' + matId + '_' + divId + '_' + destType + (profileKey ? '_' + profileKey : ''),
          physical_qty_content: physQty,
          system_qty_content:   sysQty,
          adjustment_type:      adjType,
          reason_code:          reason,
          notes:                notes,
          avg_cost_per_content: adjType === 'ADJUSTMENT_PLUS' ? unitCost : 0,
          material_id:          matId  || null,
          item_id:              itemId || null,
          content_uom_id:       contentUomId,
        };
        if (profileKey) payload.profile_key = profileKey;
        var json = await postJson(qAdjUrl, payload);
        if (qAdjModalBs) { qAdjModalBs.hide(); } else if (qAdjModal) { qAdjModal.style.display = 'none'; }
        await showAlert(json.message || 'Adjustment berhasil diposting.', 'Adjustment');
        window.location.reload();
      } catch(e) {
        clearButtonLoading(qaSubmitBtn);
        await showAlert(e.message || 'Gagal menyimpan adjustment.', 'Adjustment');
      }
    });
  }

  var lrModal   = document.getElementById('logRepairModal');
  var lrModalBs = lrModal && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(lrModal) : null;
  var lrRepairUrl = <?php echo json_encode($logRepairUrl); ?>;

  function lrFmt(v) { var n = parseFloat(v); return isNaN(n) ? '0' : n.toLocaleString('id-ID', {maximumFractionDigits:4}); }
  function lrUpdateCorr() {
    var v = parseFloat(document.getElementById('lrCorrQty')?.value || '');
    var el = document.getElementById('lrCorrDisplay');
    if (el) { el.textContent = isNaN(v) ? '-' : (v > 0 ? '+' : '') + lrFmt(v); el.style.color = v > 0 ? '#1d4ed8' : v < 0 ? '#b42318' : '#6b7280'; }
  }

  document.addEventListener('click', function(ev) {
    var btn = ev.target.closest('.src-log-repair-btn');
    if (!btn) return;
    var divId     = Number(btn.dataset.divisionId || 0);
    var matId     = Number(btn.dataset.materialId || 0);
    var dest      = String(btn.dataset.destination || '');
    var contentUomId = Number(btn.dataset.contentUomId || 0);
    var matName   = String(btn.dataset.materialName || '');
    var profileKey = String(btn.dataset.profileKey || '');
    var profileName= String(btn.dataset.profileName || '');
    var logGap    = Number(btn.dataset.logGap || 0);
    var stock     = Number(btn.dataset.stock || 0);
    var avgCost   = Number(btn.dataset.avgCost || 0);

    var g = function(id) { return document.getElementById(id); };
    var subtitle = dest + (divId ? '  /  Div #' + divId : '') + (profileName ? '  /  ' + profileName : '');
    if (g('lrModalTitle')) g('lrModalTitle').textContent = 'Repair Log Gap';
    if (g('lrSubtitle'))   g('lrSubtitle').textContent  = subtitle;
    if (g('lrMatName'))    g('lrMatName').textContent   = matName;
    if (g('lrGapDisplay')) { g('lrGapDisplay').textContent = (logGap > 0 ? '+' : '') + lrFmt(logGap); g('lrGapDisplay').style.color = logGap > 0 ? '#1d4ed8' : '#b42318'; }
    if (g('lrStockDisplay')) g('lrStockDisplay').textContent = lrFmt(stock);
    if (g('lrCorrDisplay')) { g('lrCorrDisplay').textContent = (logGap > 0 ? '+' : '') + lrFmt(logGap); g('lrCorrDisplay').style.color = logGap > 0 ? '#1d4ed8' : '#b42318'; }
    if (g('lrCorrQty'))    g('lrCorrQty').value = logGap !== 0 ? logGap : '';
    if (g('lrUnitCost'))   g('lrUnitCost').value = avgCost > 0 ? avgCost : '';
    if (g('lrNotes'))      g('lrNotes').value = '';
    if (g('lrDate'))       g('lrDate').value = new Date().toISOString().slice(0, 10);
    if (lrModal) {
      lrModal.dataset.divisionId   = divId;
      lrModal.dataset.materialId   = matId;
      lrModal.dataset.destination  = dest;
      lrModal.dataset.contentUomId = contentUomId;
      lrModal.dataset.profileKey   = profileKey;
      lrModal.dataset.logGap       = logGap;
    }
    if (lrModalBs) { lrModalBs.show(); } else if (lrModal) { lrModal.style.display = 'flex'; }
    setTimeout(function() { var cq = g('lrCorrQty'); if (cq) cq.focus(); }, 350);
  });

  if (document.getElementById('lrCorrQty')) {
    document.getElementById('lrCorrQty').addEventListener('input', lrUpdateCorr);
  }

  var lrAutoFillBtn = document.getElementById('lrAutoFillBtn');
  if (lrAutoFillBtn) {
    lrAutoFillBtn.addEventListener('click', function() {
      var gap = Number(lrModal?.dataset.logGap || 0);
      var el = document.getElementById('lrCorrQty');
      if (el) { el.value = gap; lrUpdateCorr(); el.focus(); }
    });
  }

  var lrSubmitBtn = document.getElementById('lrSubmitBtn');
  if (lrSubmitBtn) {
    lrSubmitBtn.addEventListener('click', async function() {
      var g           = function(id) { return document.getElementById(id); };
      var corrQty     = parseFloat(g('lrCorrQty')?.value || '');
      var unitCost    = parseFloat(g('lrUnitCost')?.value || 0);
      var notes       = (g('lrNotes')?.value || '').trim();
      var date        = g('lrDate')?.value || new Date().toISOString().slice(0, 10);
      var divId       = Number(lrModal?.dataset.divisionId  || 0);
      var matId       = Number(lrModal?.dataset.materialId  || 0);
      var dest        = String(lrModal?.dataset.destination || '');
      var contentUomId = Number(lrModal?.dataset.contentUomId || 0);
      var profileKey  = String(lrModal?.dataset.profileKey  || '');

      if (divId <= 0) { await showAlert('Division tidak valid.', 'Repair Log'); return; }
      if (isNaN(corrQty) || Math.abs(corrQty) < 0.0001) { await showAlert('Qty koreksi tidak boleh nol.', 'Repair Log'); g('lrCorrQty')?.focus(); return; }

      setButtonLoading(lrSubmitBtn, 'Menyimpan...');
      try {
        var payload = {
          adjustment_date: date,
          division_id:     divId,
          material_id:     matId,
          destination:     dest,
          content_uom_id:  contentUomId || null,
          correction_qty:  corrQty,
          unit_cost:       unitCost || null,
          notes:           notes,
        };
        if (profileKey) payload.profile_key = profileKey;
        var json = await postJson(lrRepairUrl, payload);
        if (lrModalBs) { lrModalBs.hide(); } else if (lrModal) { lrModal.style.display = 'none'; }
        await showAlert(json.message || 'Repair log berhasil.', 'Repair Log');
        window.location.reload();
      } catch(e) {
        clearButtonLoading(lrSubmitBtn);
        await showAlert(e.message || 'Gagal menyimpan repair log.', 'Repair Log');
      }
    });
  }

  if (repairDecisionModal) {
    repairDecisionModal.addEventListener('click', function(ev) {
      var cancelBtn = ev.target.closest('[data-role="cancel"]');
      if (cancelBtn) {
        hideRepairDecisionModal();
        return;
      }
      var choiceBtn = ev.target.closest('.repair-choice-btn');
      if (!choiceBtn) return;
      var rawIdentity = repairDecisionModal.dataset.identity || '{}';
      var identity = {};
      try { identity = JSON.parse(rawIdentity); } catch (e) { identity = {}; }
      hideRepairDecisionModal();
      var fallbackBtn = repairCurBtn || document.querySelector('.src-material-repair-btn');
      runMaterialRepair(identity, fallbackBtn, choiceBtn.dataset.mode || '').catch(function(e){
        showAlert(e.message || 'Gagal menjalankan repair paksa.', 'Repair Bahan');
      });
    });
  }
});
</script>

<!-- Profile Repair Modal -->
<div class="modal fade" id="profileRepairModal" tabindex="-1" aria-labelledby="prModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#312028,#4a2f3a)">
        <h6 class="modal-title text-white mb-0" id="prModalTitle">Repair Profil</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body pb-2">
        <div class="small text-muted mb-2">Analisis selisih untuk profil ini:</div>
        <div id="prModalAnalysis" class="mb-3" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden"></div>
        <div id="prModalExplanation" class="mb-3"></div>
        <div id="prModalActions" class="d-flex gap-2 flex-wrap justify-content-end">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" id="prRepairToStock"    class="btn btn-sm btn-warning">Repair sesuai Stok</button>
          <button type="button" id="prRepairToMovement" class="btn btn-sm btn-info text-white">Repair sesuai Movement</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="profileMergeModal" tabindex="-1" aria-labelledby="pmModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#1f2937,#374151)">
        <h6 class="modal-title text-white mb-0" id="pmModalTitle">Join Profile</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="pmModalHelp" class="alert alert-warning py-2 small mb-3"></div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="text-center" style="width:80px">Target</th>
                <th class="text-center" style="width:90px">Gabung</th>
                <th>Profil</th>
                <th class="text-end">Stok</th>
                <th class="text-end">Lot</th>
                <th class="text-end">Movement</th>
              </tr>
            </thead>
            <tbody id="pmModalRows"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-dark" id="pmSubmitBtn">Join Profile</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="repairDecisionModal" tabindex="-1" aria-labelledby="repairDecisionTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#2b2430,#46354b)">
        <h6 class="modal-title text-white mb-0" id="repairDecisionTitle">Pilih Arah Repair</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="repairDecisionBody"></div>
        <div id="repairDecisionActions" class="d-flex flex-wrap gap-2 justify-content-end align-items-start"></div>
      </div>
    </div>
  </div>
</div>

<!-- Log Gap Repair Modal -->
<div class="modal fade" id="logRepairModal" tabindex="-1" aria-labelledby="lrModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#7f1d1d,#dc2626)">
        <div>
          <h6 class="modal-title text-white mb-0" id="lrModalTitle">Repair Log Gap</h6>
          <div class="text-white" id="lrSubtitle" style="font-size:.72rem;opacity:.85;margin-top:.1rem"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body pb-2">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="fw-semibold" id="lrMatName" style="font-size:.95rem"></div>
          <input type="date" class="form-control form-control-sm w-auto" id="lrDate">
        </div>
        <div class="small text-danger mb-3" style="background:#fff1f2;border:1px solid #fecaca;border-radius:6px;padding:.5rem .75rem">
          Mode ini menambah entry <strong>ADJUSTMENT</strong> ke movement log agar
          <code>opening + sum delta</code> foot ke <code>closing monthly_stock</code>.
          Monthly stock dan lot FIFO <strong>tidak</strong> diubah.
        </div>
        <div class="rounded p-2 mb-3" style="background:#f8f9fa;border:1px solid #e2e8f0;font-size:.82rem">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Log Gap (closing - predicted)</span>
            <span class="fw-semibold" id="lrGapDisplay" style="color:#b42318">-</span>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Closing monthly_stock</span>
            <span class="fw-semibold" id="lrStockDisplay">-</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Correction qty (akan diinsert)</span>
            <span class="fw-semibold" id="lrCorrDisplay" style="color:#1d4ed8">-</span>
          </div>
        </div>
        <div class="mb-2">
          <div class="d-flex align-items-center gap-2 mb-1">
            <label class="form-label small mb-0 fw-semibold">Qty Koreksi</label>
            <button type="button" class="btn btn-xs btn-outline-secondary" id="lrAutoFillBtn" style="font-size:.65rem;padding:.05rem .4rem">Auto dari Gap</button>
          </div>
          <input type="number" step="0.0001" class="form-control form-control-sm" id="lrCorrQty" placeholder="Positif = tambah, negatif = kurangi">
        </div>
        <div class="mb-2" id="lrCostRow">
          <label class="form-label small mb-1">Unit Cost <span class="text-muted fw-normal small">(opsional, untuk koreksi plus)</span></label>
          <input type="number" step="0.000001" min="0" class="form-control form-control-sm" id="lrUnitCost" placeholder="0">
        </div>
        <div class="mb-1">
          <label class="form-label small mb-1">Catatan</label>
          <input type="text" class="form-control form-control-sm" id="lrNotes" placeholder="Alasan koreksi log gap">
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-danger" id="lrSubmitBtn">Simpan Repair Log</button>
      </div>
    </div>
  </div>
</div>

<!-- Quick Adjustment Manual Modal -->
<div class="modal fade" id="quickAdjModal" tabindex="-1" aria-labelledby="qaModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#78350f,#b45309)">
        <div>
          <h6 class="modal-title text-white mb-0" id="qaModalTitle">Adjustment Manual Stok</h6>
          <div class="text-white" id="qaSubtitle" style="font-size:.72rem;opacity:.85;margin-top:.1rem"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body pb-2">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="fw-semibold" id="qaMatName" style="font-size:.95rem"></div>
          <input type="date" class="form-control form-control-sm w-auto" id="qaDate">
        </div>

        <!-- Saldo info -->
        <div class="rounded p-2 mb-3" style="background:#f8f9fa;border:1px solid #e2e8f0;font-size:.82rem">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Saldo saat ini</span>
            <span class="fw-semibold" id="qaSaldoSaatIni">0</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Selisih adjustment</span>
            <span class="fw-semibold" id="qaSelisihDisplay" style="color:#b45309">-</span>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label small mb-1 fw-semibold">Saldo Target <span class="text-muted fw-normal">(setelah adjustment)</span></label>
          <input type="number" step="0.0001" class="form-control form-control-sm" id="qaPhysicalQty" placeholder="Masukkan saldo yang diinginkan">
        </div>

        <!-- Tipe & Alasan - auto-suggest tapi bisa diubah -->
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small mb-1">Tipe</label>
            <select class="form-select form-select-sm" id="qaAdjType">
              <option value="ADJUSTMENT_MINUS">Adj Minus</option>
              <option value="ADJUSTMENT_PLUS">Adj Plus</option>
              <option value="WASTE">Waste</option>
              <option value="SPOIL">Spoil</option>
              <option value="PROCESS_LOSS">Process Loss</option>
              <option value="VARIANCE">Variance</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small mb-1">Alasan</label>
            <select class="form-select form-select-sm" id="qaReason"></select>
          </div>
        </div>

        <div class="mb-2" id="qaHppRow" style="display:none">
          <label class="form-label small mb-1">HPP / Unit Cost <span class="text-danger">*</span></label>
          <input type="number" step="0.000001" min="0.000001" class="form-control form-control-sm" id="qaUnitCost" placeholder="Harga per satuan isi">
        </div>

        <div class="mb-1">
          <label class="form-label small mb-1">Catatan</label>
          <input type="text" class="form-control form-control-sm" id="qaNotes" placeholder="Opsional">
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-warning" id="qaSubmitBtn">Simpan Adjustment</button>
      </div>
    </div>
  </div>
</div>
