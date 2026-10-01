<?php
$baseUrl            = (string)($base_url ?? site_url('inventory/stock/division/lot'));
$rows               = is_array($rows ?? null) ? $rows : [];
$divisionOptions    = is_array($divisions ?? null) ? $divisions : [];
$destGuardMap       = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$qSearch            = (string)($q ?? '');
$dateFrom           = (string)($date_from ?? '');
$dateTo             = (string)($date_to   ?? '');
$statusValue        = strtoupper((string)($status ?? 'OPEN'));
$selDivisionId      = (int)($division_id ?? 0);
$selDestination     = strtoupper((string)($destination ?? 'ALL'));
$selProfileKey      = (string)($profile_key ?? '');
$perPage            = max(10, (int)($per_page ?? 25));
$page               = max(1, (int)($page ?? 1));
$now                = new DateTime();

// ── Lot display meta (identical logic to lot_audit_index) ─────────────────
$resolveLotDisplay = static function (array $row): array {
    $qtyIn  = round((float)($row['qty_in']      ?? 0), 4);
    $qtyOut = round((float)($row['qty_out']     ?? 0), 4);
    $qtyBal = round((float)($row['qty_balance'] ?? 0), 4);
    $factor = round(max(0.000001, (float)($row['identity_content_per_buy'] ?? 1)), 6);
    $idLotBal = round((float)($row['identity_lot_qty_balance']   ?? 0), 4);
    $idClBuy  = round((float)($row['identity_closing_qty_buy']   ?? 0), 4);
    $idClCnt  = round((float)($row['identity_closing_qty_content'] ?? 0), 4);
    $buyUomId = (int)($row['buy_uom_id']     ?? 0);
    $cntUomId = (int)($row['content_uom_id'] ?? 0);
    $legacyBuyBased = false;
    if ($buyUomId > 0 && $cntUomId > 0 && $buyUomId !== $cntUomId) {
        $matchesBuy     = abs($idLotBal - $idClBuy) < 0.0001;
        $differsFromCnt = abs($idLotBal - $idClCnt) >= 0.0001;
        $legacyBuyBased = $matchesBuy && $differsFromCnt;
    }
    return [
        'legacy'      => $legacyBuyBased,
        'qty_in'      => $legacyBuyBased ? round($qtyIn  * $factor, 4) : $qtyIn,
        'qty_out'     => $legacyBuyBased ? round($qtyOut * $factor, 4) : $qtyOut,
        'qty_balance' => $legacyBuyBased ? round($qtyBal * $factor, 4) : $qtyBal,
        'qty_balance_raw' => $qtyBal,
        'content_uom' => trim((string)($row['content_uom_code'] ?? '')),
        'buy_uom'     => trim((string)($row['buy_uom_code'] ?? '')),
        'factor'      => $factor,
    ];
};

// ── Expiry status ─────────────────────────────────────────────────────────
$expiryClass = static function (string $expDate) use ($now): string {
    if ($expDate === '' || $expDate === '0000-00-00') return '';
    try {
        $exp  = new DateTime($expDate);
        $diff = (int)$now->diff($exp)->days * ($exp > $now ? 1 : -1);
    } catch (Exception $e) { return ''; }
    if ($diff <  0)  return 'lot-expired';
    if ($diff <= 7)  return 'lot-exp-critical';
    if ($diff <= 30) return 'lot-exp-warn';
    if ($diff <= 90) return 'lot-exp-notice';
    return '';
};
$expiryDaysLabel = static function (string $expDate) use ($now): string {
    if ($expDate === '' || $expDate === '0000-00-00') return '';
    try {
        $exp  = new DateTime($expDate);
        $diff = (int)$now->diff($exp)->days * ($exp > $now ? 1 : -1);
    } catch (Exception $e) { return ''; }
    if ($diff < 0)  return 'Kadaluwarsa '  . abs($diff) . 'h lalu';
    if ($diff === 0) return 'Kadaluwarsa hari ini!';
    return 'Exp ' . $diff . 'h lagi';
};

// ── KPI ─────────────────────────────────────────────────────────────────────
$kpiTotal    = count($rows);
$kpiOpen     = 0;
$kpiBal      = 0.0;
$kpiValue    = 0.0;
$kpiDivSet   = [];
$kpiExpiring = 0;
$kpiExpired  = 0;
foreach ($rows as $r) {
    $m    = $resolveLotDisplay($r);
    $bal  = (float)$m['qty_balance'];
    $cost = (float)($r['unit_cost'] ?? 0);
    if ($bal > 0) $kpiOpen++;
    $kpiBal   += $bal;
    $kpiValue += max(0, $bal) * $cost;
    $divId = (int)($r['division_id'] ?? 0);
    if ($divId > 0) $kpiDivSet[$divId] = true;
    $expDate = trim((string)($r['expiry_date'] ?? ''));
    $cls     = $expiryClass($expDate);
    if ($cls === 'lot-expired') $kpiExpired++;
    elseif (in_array($cls, ['lot-exp-critical','lot-exp-warn'], true)) $kpiExpiring++;
}
$kpiDivCount = count($kpiDivSet);

// ── Pagination ───────────────────────────────────────────────────────────────
$totalRows  = count($rows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$pagedRows  = array_slice($rows, ($page - 1) * $perPage, $perPage);
$pBase      = array_filter([
    'status'      => $statusValue,
    'division_id' => $selDivisionId ?: null,
    'destination' => $selDestination !== 'ALL' ? $selDestination : null,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'q'           => $qSearch,
    'profile_key' => $selProfileKey,
    'per_page'    => $perPage,
]);
$pQs = http_build_query($pBase);

// ── Source link helper ───────────────────────────────────────────────────────
$buildSourceLabel = static function (array $row): string {
    $tbl = strtolower(trim((string)($row['source_table'] ?? '')));
    $id  = (int)($row['source_id'] ?? 0);
    $map = [
        'pur_purchase_receipt'            => 'Receipt PO',
        'pur_store_request_fulfillment'   => 'Fulfillment SR',
        'inv_component_batch'             => 'Batch Produksi',
        'inv_stock_adjustment'            => 'Adj Stok',
        'inv_division_stock_adjustment'   => 'Adj Divisi',
        'inv_division_stock_opening_snapshot' => 'Opening',
    ];
    $label = $map[$tbl] ?? ($tbl !== '' ? strtoupper(str_replace('_',' ',$tbl)) : '-');
    return $id > 0 ? $label . ' #' . $id : $label;
};
?>

<style>
/* ── Filter ── */
.lot-filter-grid {
  display:grid;
  grid-template-columns: 90px minmax(120px,1.4fr) 90px 108px 108px minmax(150px,2fr) 60px auto auto;
  gap:.5rem; align-items:end;
}
@media(max-width:1199px) { .lot-filter-grid { grid-template-columns: 88px 1fr 88px 100px 100px 1fr 54px auto auto; } }
@media(max-width:991px)  { .lot-filter-grid { grid-template-columns: repeat(4,1fr); } }
@media(max-width:575px)  { .lot-filter-grid { grid-template-columns: 1fr 1fr; } }

/* ── KPI ── */
.lot-kpi-row { display:grid; grid-template-columns:repeat(6,1fr); gap:.6rem; margin-bottom:1rem; }
@media(max-width:1199px) { .lot-kpi-row { grid-template-columns:repeat(3,1fr); } }
@media(max-width:575px)  { .lot-kpi-row { grid-template-columns:repeat(2,1fr); } }
.lot-kpi {
  border-radius:14px; padding:.95rem 1.1rem .9rem; color:#fff;
  position:relative; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.13);
}
.lot-kpi::before { content:''; position:absolute; right:-16px; bottom:-16px; width:76px; height:76px; border-radius:50%; background:rgba(255,255,255,.12); }
.lot-kpi::after  { content:''; position:absolute; right:12px; top:-20px; width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.08); }
.lot-kpi-icon { font-size:1.2rem; opacity:.82; margin-bottom:.3rem; display:block; }
.lot-kpi-val  { font-size:1.35rem; font-weight:800; line-height:1.1; }
.lot-kpi-sub  { font-size:.7rem; opacity:.75; margin-top:.1rem; }
.lot-kpi-lbl  { font-size:.67rem; opacity:.82; text-transform:uppercase; letter-spacing:.06em; margin-top:.2rem; }
.lot-kpi-1 { background:linear-gradient(135deg,#667eea,#764ba2); }
.lot-kpi-2 { background:linear-gradient(135deg,#0c7cba,#0fcdba); }
.lot-kpi-3 { background:linear-gradient(135deg,#11998e,#38ef7d); color:#0b3b34; }
.lot-kpi-4 { background:linear-gradient(135deg,#e55d2b,#f7b733); }
.lot-kpi-5 { background:linear-gradient(135deg,#1c7ed6,#4dabf7); }
.lot-kpi-6-ok   { background:linear-gradient(135deg,#374151,#6b7280); }
.lot-kpi-6-warn { background:linear-gradient(135deg,#d97706,#fbbf24); color:#1a0a00; }
.lot-kpi-6-crit { background:linear-gradient(135deg,#c92a2a,#fa5252); }

/* ── Table ── */
.lot-table-wrap { overflow-x:auto; overflow-y:auto; max-height:68vh; }
.lot-table-wrap table { min-width:1260px; border-collapse:separate; border-spacing:0; }
.lot-table-wrap thead th {
  position:sticky; top:0; z-index:4;
  background:linear-gradient(180deg,#1e3a5f 0%,#2d5282 100%);
  color:#e8f4fd; white-space:nowrap;
  border-bottom:1px solid #2a4a72; font-size:.73rem; font-weight:700; letter-spacing:.03em;
}

/* ── SURPRISE: Expiry row colors ── */
.lot-expired    { background:#fff1f2 !important; }
.lot-exp-critical { background:#fff5f5 !important; }
.lot-exp-warn   { background:#fffbeb !important; }
.lot-exp-notice { background:#f0fff8 !important; }
.lot-expired    td:first-child { border-left:3px solid #c92a2a; }
.lot-exp-critical td:first-child { border-left:3px solid #e03131; }
.lot-exp-warn   td:first-child { border-left:3px solid #e67700; }
.lot-exp-notice td:first-child { border-left:3px solid #2f9e44; }

.lot-status-pill {
  display:inline-flex; align-items:center; gap:.3rem;
  font-size:.67rem; font-weight:700; padding:.18rem .55rem; border-radius:999px; white-space:nowrap;
}
.lot-status-open   { background:#d3f9d8; color:#2f9e44; border:1px solid #b2f2bb; }
.lot-status-closed { background:#f1f3f5; color:#868e96; border:1px solid #dee2e6; }

/* ── Consumption bar ── */
.lot-consump-bar { height:5px; border-radius:999px; background:#e9ecef; overflow:hidden; margin-top:3px; }
.lot-consump-fill { height:100%; border-radius:999px; }

/* ── Ref links ── */
.lot-ref-links { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.25rem; }
.lot-ref-links .btn { --bs-btn-padding-y:.14rem; --bs-btn-padding-x:.45rem; --bs-btn-font-size:.67rem; border-radius:999px; }

/* ── Pagination ── */
.lot-pagination { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; }
.lot-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 .5rem;
  border:1px solid #ddd; border-radius:8px; background:#fff; color:#555;
  font-size:.8rem; font-weight:600; text-decoration:none; transition:all .15s;
}
.lot-page-btn:hover   { background:#f5f5f5; border-color:#bbb; color:#333; }
.lot-page-btn.active  { background:#1e3a5f; border-color:#1e3a5f; color:#fff; }
.lot-page-btn.disabled { opacity:.4; pointer-events:none; }

/* ── Expiry legend ── */
.lot-exp-legend { display:flex; flex-wrap:wrap; gap:.5rem .9rem; align-items:center; }
.lot-exp-dot { width:10px; height:10px; border-radius:50%; display:inline-block; flex-shrink:0; }
</style>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-stack-line page-title-icon"></i><?php echo html_escape((string)($title ?? 'Lot Bahan Baku')); ?></h4>
    <small class="text-muted"><?php echo html_escape((string)($subtitle ?? 'Posisi lot FIFO untuk stok divisi operasional.')); ?></small>
  </div>
  <a href="<?php echo site_url('inventory/fifo-audit'); ?>" class="btn btn-sm btn-outline-secondary align-self-start">
    <i class="ri ri-bar-chart-line me-1"></i>Audit FIFO
  </a>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'lot']); ?>
</div>
<?php
$lotGenMonth = !empty($dateFrom) ? date('Y-m', strtotime($dateFrom)) : date('Y-m');
$this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $lotGenMonth, 'division_id' => (string)$selDivisionId, 'destination_type' => $selDestination],
]);
?>

<!-- ── Filter 1-row ── -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" id="lot-filter-form">
      <div class="lot-filter-grid">
        <div>
          <label class="form-label mb-1">Status</label>
          <select class="form-select form-select-sm" name="status">
            <option value="OPEN"   <?php echo $statusValue==='OPEN'  ?'selected':''; ?>>Open</option>
            <option value="ALL"    <?php echo $statusValue==='ALL'   ?'selected':''; ?>>Semua</option>
            <option value="CLOSED" <?php echo $statusValue==='CLOSED'?'selected':''; ?>>Closed</option>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id" id="lotDivision">
            <option value="">Semua</option>
            <?php foreach ($divisionOptions as $d): ?>
              <?php $did=(int)($d['id']??0); $dc=trim((string)($d['code']??'')); $dn=trim((string)($d['name']??'')); $dl=$dc!==''?$dc.' - '.$dn:($dn?:(string)$did); ?>
              <option value="<?php echo $did; ?>" <?php echo $selDivisionId===$did?'selected':''; ?>><?php echo html_escape($dl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination" id="lotDestination">
            <option value="ALL"          <?php echo $selDestination==='ALL'          ?'selected':''; ?>>Semua</option>
            <option value="REGULER"      <?php echo $selDestination==='REGULER'      ?'selected':''; ?>>Reguler</option>
            <option value="EVENT"        <?php echo $selDestination==='EVENT'        ?'selected':''; ?>>Event</option>
            <option value="BAR"          <?php echo $selDestination==='BAR'          ?'selected':''; ?>>BAR</option>
            <option value="KITCHEN"      <?php echo $selDestination==='KITCHEN'      ?'selected':''; ?>>KITCHEN</option>
            <option value="BAR_EVENT"    <?php echo $selDestination==='BAR_EVENT'    ?'selected':''; ?>>BAR Event</option>
            <option value="KITCHEN_EVENT"<?php echo $selDestination==='KITCHEN_EVENT'?'selected':''; ?>>Kitchen Ev.</option>
            <option value="ROASTERY"<?php echo $selDestination==='ROASTERY'?'selected':''; ?>>Roastery</option>
            <option value="ROASTERY_EVENT"<?php echo $selDestination==='ROASTERY_EVENT'?'selected':''; ?>>Roastery Ev.</option>
            <option value="OFFICE"       <?php echo $selDestination==='OFFICE'       ?'selected':''; ?>>OFFICE</option>
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
          <input type="text" class="form-control form-control-sm" name="q" id="lot-q-input" value="<?php echo html_escape($qSearch); ?>" placeholder="Lot No / item / material / profile key" autocomplete="off">
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
<div class="lot-kpi-row">
  <div class="lot-kpi lot-kpi-1">
    <span class="lot-kpi-icon"><i class="ri ri-stack-line"></i></span>
    <div class="lot-kpi-val"><?php echo number_format($kpiTotal); ?></div>
    <div class="lot-kpi-sub">entri lot tercatat</div>
    <div class="lot-kpi-lbl">Total Lot</div>
  </div>
  <div class="lot-kpi lot-kpi-2">
    <span class="lot-kpi-icon"><i class="ri ri-building-2-line"></i></span>
    <div class="lot-kpi-val"><?php echo number_format($kpiDivCount); ?></div>
    <div class="lot-kpi-sub">divisi dengan lot aktif</div>
    <div class="lot-kpi-lbl">Divisi Aktif</div>
  </div>
  <div class="lot-kpi lot-kpi-3">
    <span class="lot-kpi-icon"><i class="ri ri-checkbox-circle-line"></i></span>
    <div class="lot-kpi-val"><?php echo number_format($kpiOpen); ?></div>
    <div class="lot-kpi-sub">dari <?php echo number_format($kpiTotal); ?> total lot</div>
    <div class="lot-kpi-lbl">Lot Open</div>
  </div>
  <div class="lot-kpi lot-kpi-4">
    <span class="lot-kpi-icon"><i class="ri ri-box-3-line"></i></span>
    <div class="lot-kpi-val"><?php echo number_format($kpiBal, 2, ',', '.'); ?></div>
    <div class="lot-kpi-sub">total qty balance (isi)</div>
    <div class="lot-kpi-lbl">Qty Balance</div>
  </div>
  <div class="lot-kpi lot-kpi-5">
    <span class="lot-kpi-icon"><i class="ri ri-money-dollar-circle-line"></i></span>
    <div class="lot-kpi-val"><?php echo 'Rp '.number_format($kpiValue, 0, ',', '.'); ?></div>
    <div class="lot-kpi-sub">balance × unit cost</div>
    <div class="lot-kpi-lbl">Nilai Estimasi</div>
  </div>
  <?php
    $expKpiClass = $kpiExpired > 0 ? 'lot-kpi-6-crit' : ($kpiExpiring > 0 ? 'lot-kpi-6-warn' : 'lot-kpi-6-ok');
    $expKpiVal   = $kpiExpired + $kpiExpiring;
    $expKpiSub   = $kpiExpired > 0 ? $kpiExpired.' kadaluwarsa, '.$kpiExpiring.' segera' : ($kpiExpiring > 0 ? $kpiExpiring.' lot exp ≤30h' : 'semua aman');
  ?>
  <div class="lot-kpi <?php echo $expKpiClass; ?>">
    <span class="lot-kpi-icon"><i class="ri ri-alarm-warning-line"></i></span>
    <div class="lot-kpi-val"><?php echo number_format($expKpiVal); ?></div>
    <div class="lot-kpi-sub"><?php echo $expKpiSub; ?></div>
    <div class="lot-kpi-lbl">Expiry Alert</div>
  </div>
</div>

<!-- SURPRISE: Expiry legend strip -->
<?php if ($kpiExpired + $kpiExpiring > 0): ?>
<div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-3 flex-wrap" style="font-size:.8rem;border-radius:12px;">
  <i class="ri ri-error-warning-line fs-5 text-warning"></i>
  <span class="fw-semibold">Perhatian Expiry:</span>
  <div class="lot-exp-legend">
    <?php if ($kpiExpired > 0): ?>
      <span class="d-inline-flex align-items-center gap-1"><span class="lot-exp-dot" style="background:#c92a2a"></span><span class="text-danger fw-bold"><?php echo $kpiExpired; ?> lot sudah kadaluwarsa</span></span>
    <?php endif; ?>
    <?php if ($kpiExpiring > 0): ?>
      <span class="d-inline-flex align-items-center gap-1"><span class="lot-exp-dot" style="background:#e67700"></span><span class="text-warning fw-bold"><?php echo $kpiExpiring; ?> lot exp ≤30 hari</span></span>
    <?php endif; ?>
    <span class="text-muted">— Baris bertanda merah/kuning di bawah</span>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Table ── -->
<div class="card">
  <div class="card-header py-2 d-flex justify-content-between align-items-center">
    <div class="lot-exp-legend">
      <span class="d-inline-flex align-items-center gap-1 small text-muted"><span class="lot-exp-dot" style="background:#c92a2a"></span>Kadaluwarsa</span>
      <span class="d-inline-flex align-items-center gap-1 small text-muted"><span class="lot-exp-dot" style="background:#e03131"></span>≤7 hari</span>
      <span class="d-inline-flex align-items-center gap-1 small text-muted"><span class="lot-exp-dot" style="background:#e67700"></span>≤30 hari</span>
      <span class="d-inline-flex align-items-center gap-1 small text-muted"><span class="lot-exp-dot" style="background:#2f9e44"></span>≤90 hari</span>
    </div>
    <span class="text-muted small"><?php echo number_format($totalRows); ?> lot</span>
  </div>
  <div class="lot-table-wrap">
    <table class="table table-sm table-hover mb-0" id="lot-table">
      <thead>
        <tr>
          <th style="min-width:130px">Lot No</th>
          <th style="min-width:140px">Divisi / Tujuan</th>
          <th style="min-width:190px">Item / Material</th>
          <th style="min-width:140px">Profile</th>
          <th style="min-width:110px">Receipt / Expiry</th>
          <th class="text-end" style="min-width:90px">Qty In</th>
          <th class="text-end" style="min-width:90px">Qty Out</th>
          <th class="text-end" style="min-width:100px">Balance</th>
          <th class="text-end" style="min-width:85px">Unit Cost</th>
          <th class="text-end" style="min-width:95px">Nilai</th>
          <th style="min-width:190px">Ref & Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($pagedRows)): ?>
        <tr><td colspan="11" class="text-center text-muted py-4">Belum ada lot yang sesuai filter ini.</td></tr>
      <?php else: ?>
        <?php foreach ($pagedRows as $r): ?>
          <?php
            $lid      = (int)($r['id'] ?? 0);
            $m        = $resolveLotDisplay($r);
            $expDate  = trim((string)($r['expiry_date']  ?? ''));
            $recDate  = trim((string)($r['receipt_date'] ?? ''));
            $expCls   = $expiryClass($expDate);
            $expLbl   = $expiryDaysLabel($expDate);
            $cost     = (float)($r['unit_cost'] ?? 0);
            $balVal   = max(0, (float)$m['qty_balance']) * $cost;
            $isOpen   = (float)$m['qty_balance'] > 0.0001;
            $qtyIn    = (float)$m['qty_in'];
            $qtyOut   = (float)$m['qty_out'];
            $pctOut   = $qtyIn > 0 ? min(100, round($qtyOut / $qtyIn * 100)) : 0;
            $barColor = $pctOut >= 90 ? '#2f9e44' : ($pctOut >= 50 ? '#e67700' : '#1c7ed6');

            $divC = trim((string)($r['division_code'] ?? ''));
            $divN = trim((string)($r['division_name'] ?? ''));
            $divLabel = $divC !== '' ? ($divC.' - '.$divN) : ($divN ?: (string)($r['division_id'] ?? '-'));
            $destName = trim((string)($r['destination_name'] ?? ''));
            $destType = trim((string)($r['destination_type'] ?? ''));

            $objName = trim((string)($r['item_name'] ?? ''));
            if ($objName === '') $objName = trim((string)($r['material_name'] ?? ''));
            $objCode = trim((string)($r['item_code'] ?? ''));
            if ($objCode === '') $objCode = trim((string)($r['material_code'] ?? ''));

            $profileKey  = trim((string)($r['profile_key']  ?? ''));
            $buyUom      = trim((string)($r['buy_uom_code'] ?? ''));
            $cntUom      = trim((string)($r['content_uom_code'] ?? ''));

            $parentLot   = trim((string)($r['parent_lot_no'] ?? ''));
            $issueLines  = (int)($r['issue_line_count'] ?? 0);
            $issueQty    = (float)($r['issue_qty_total'] ?? 0);
            $srcLabel    = $buildSourceLabel($r);
          ?>
          <tr class="<?php echo $expCls; ?>">
            <td>
              <div class="fw-semibold small"><?php echo html_escape((string)($r['lot_no'] ?? '-')); ?></div>
              <div class="small text-muted"><?php echo $parentLot !== '' ? 'Child of ' . html_escape($parentLot) : 'Root lot'; ?></div>
              <span class="lot-status-pill <?php echo $isOpen ? 'lot-status-open' : 'lot-status-closed'; ?>">
                <i class="ri <?php echo $isOpen ? 'ri-checkbox-blank-circle-fill' : 'ri-checkbox-circle-line'; ?>" style="font-size:.55rem"></i>
                <?php echo $isOpen ? 'Open' : 'Closed'; ?>
              </span>
            </td>
            <td class="small">
              <div><?php echo html_escape($divLabel); ?></div>
              <small class="text-muted"><?php echo html_escape($destName ?: $destType ?: '-'); ?></small>
            </td>
            <td class="small">
              <div class="fw-semibold"><?php echo html_escape($objName ?: '-'); ?></div>
              <?php if ($objCode !== ''): ?><small class="text-muted"><?php echo html_escape($objCode); ?></small><?php endif; ?>
            </td>
            <td class="small">
              <?php if ($profileKey !== ''): ?><div class="text-break" style="font-size:.69rem;font-family:monospace;word-break:break-all"><?php echo html_escape($profileKey); ?></div><?php endif; ?>
              <div class="text-muted"><?php echo html_escape($buyUom); ?> → <?php echo html_escape($cntUom); ?></div>
              <?php if ($m['legacy']): ?><span class="badge bg-warning text-dark" style="font-size:.62rem">Legacy buy-based</span><?php endif; ?>
            </td>
            <td class="small">
              <div><?php echo $recDate !== '' ? html_escape($recDate) : '—'; ?></div>
              <?php if ($expDate !== '' && $expDate !== '0000-00-00'): ?>
                <small class="<?php
                  if ($expCls==='lot-expired')     echo 'text-danger fw-bold';
                  elseif ($expCls==='lot-exp-critical') echo 'text-danger';
                  elseif ($expCls==='lot-exp-warn') echo 'text-warning fw-bold';
                  else echo 'text-success';
                ?>"><?php echo html_escape($expLbl); ?></small>
              <?php else: ?>
                <small class="text-muted">Tanpa expiry</small>
              <?php endif; ?>
            </td>
            <td class="text-end small">
              <div><?php echo number_format((float)$m['qty_in'], 2, ',', '.'); ?></div>
              <?php if ($cntUom !== ''): ?><small class="text-muted"><?php echo html_escape($cntUom); ?></small><?php endif; ?>
            </td>
            <td class="text-end small" style="color:#c92a2a;font-weight:600">
              <div>-<?php echo number_format((float)$m['qty_out'], 2, ',', '.'); ?></div>
              <?php if ($cntUom !== ''): ?><small class="text-muted" style="font-weight:normal"><?php echo html_escape($cntUom); ?></small><?php endif; ?>
            </td>
            <td class="text-end small fw-semibold">
              <div><?php echo number_format((float)$m['qty_balance'], 2, ',', '.'); ?></div>
              <!-- SURPRISE: consumption progress bar -->
              <div class="lot-consump-bar" title="<?php echo $pctOut; ?>% terpakai">
                <div class="lot-consump-fill" style="width:<?php echo $pctOut; ?>%;background:<?php echo $barColor; ?>"></div>
              </div>
              <small class="text-muted"><?php echo $pctOut; ?>% terpakai</small>
              <?php if ($m['legacy']): ?><div class="small text-muted">raw <?php echo number_format((float)$m['qty_balance_raw'],2,',','.'); ?> <?php echo html_escape($m['buy_uom']); ?></div><?php endif; ?>
            </td>
            <td class="text-end small"><?php echo $cost > 0 ? 'Rp '.number_format($cost,4,',','.') : '—'; ?></td>
            <td class="text-end small fw-semibold"><?php echo $balVal > 0 ? 'Rp '.number_format($balVal,0,',','.') : '—'; ?></td>
            <td>
              <div class="small text-muted"><?php echo html_escape($srcLabel); ?></div>
              <div class="lot-ref-links">
                <?php if ($lid > 0): ?>
                  <a href="<?php echo html_escape(site_url('inventory/stock/lot/usage/'.$lid)); ?>" class="btn btn-outline-primary btn-sm">
                    <i class="ri ri-eye-line me-1"></i>Pemakaian
                  </a>
                <?php endif; ?>
              </div>
              <div class="small text-muted mt-1"><?php echo $issueLines; ?> issue line · <?php echo number_format($issueQty, 2, ',', '.'); ?> keluar</div>
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
    <span class="text-muted small">Lot <?php echo "{$fromR}–{$toR}"; ?> dari <?php echo number_format($totalRows); ?></span>
    <?php if ($totalPages > 1): ?>
    <div class="lot-pagination">
      <?php
        $ws = max(1,$page-2); $we = min($totalPages,$page+2);
        if ($we-$ws < 4) { if ($ws===1) $we=min($totalPages,$ws+4); else $ws=max(1,$we-4); }
      ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page-1)); ?>" class="lot-page-btn<?php echo $page>1?'':' disabled'; ?>">&#8249;</a>
      <?php if ($ws>1): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page=1'); ?>" class="lot-page-btn">1</a><?php if ($ws>2): ?><span class="text-muted small px-1">…</span><?php endif; ?><?php endif; ?>
      <?php for ($pn=$ws;$pn<=$we;$pn++): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$pn); ?>" class="lot-page-btn<?php echo $pn===$page?' active':''; ?>"><?php echo $pn; ?></a><?php endfor; ?>
      <?php if ($we<$totalPages): ?><?php if ($we<$totalPages-1): ?><span class="text-muted small px-1">…</span><?php endif; ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$totalPages); ?>" class="lot-page-btn"><?php echo $totalPages; ?></a><?php endif; ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page+1)); ?>" class="lot-page-btn<?php echo $page<$totalPages?'':' disabled'; ?>">&#8250;</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  // AJAX debounce on search
  var qInput = document.getElementById('lot-q-input');
  if (qInput) {
    qInput.addEventListener('input', function () {
      clearTimeout(window._lotQTimer);
      window._lotQTimer = setTimeout(function () {
        document.getElementById('lot-filter-form')?.submit();
      }, 450);
    });
  }

  // Destination guard by division
  var divisionEl   = document.getElementById('lotDivision');
  var destinationEl = document.getElementById('lotDestination');
  var guardMap     = <?php echo json_encode($destGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  if (divisionEl && destinationEl) {
    function applyDestinationGuard() {
      var divId   = String(divisionEl.value || '');
      var allowed = guardMap[divId] || [];
      Array.prototype.forEach.call(destinationEl.options, function (opt) {
        var v = String(opt.value || '').toUpperCase();
        if (!divId || v === 'ALL' || v === 'REGULER' || v === 'EVENT') {
          opt.hidden = false; opt.disabled = false; return;
        }
        var keep = allowed.indexOf(v) >= 0;
        opt.hidden = !keep; opt.disabled = !keep;
      });
      if (!destinationEl.value || (destinationEl.selectedOptions[0] && destinationEl.selectedOptions[0].disabled)) {
        destinationEl.value = 'ALL';
      }
    }
    divisionEl.addEventListener('change', applyDestinationGuard);
    applyDestinationGuard();
  }
})();
</script>
