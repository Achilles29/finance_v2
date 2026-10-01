<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$reconDate    = $opname_date   ?? date('Y-m-d');
$selLocType   = $location_type ?? '';
$selDivId     = (int)($division_id ?? 0);
$selType      = $type          ?? '';
$selQ         = $q             ?? '';
$selComponentId = (int)($component_id ?? 0);
$divisionList = $divisions     ?? [];
$canCreate    = !empty($can_create);

// Sama persis dengan /production/component-adjustments
$REASONS = function_exists('component_adjustment_reason_options')
    ? component_adjustment_reason_options()
    : [];
?>
<?php $this->load->view('layout/header', ['title' => $page_title ?? 'Daily Recon Stok Component']); ?>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-check-double-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Daily Recon Stok Component'); ?></h4>
      <small class="text-muted">Input stok fisik harian component, bandingkan dengan stok sistem, lalu posting adjustment langsung.</small>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'daily_recon']); ?>
<?php $this->load->view('production/_component_type_tabs', [
    'component_type_base_url' => site_url('production/component-daily-recon'),
    'component_type_filters'  => [
        'opname_date'   => $reconDate,
        'location_type' => $selLocType,
        'division_id'   => $selDivId > 0 ? $selDivId : null,
        'q'             => $selQ,
        'component_id'  => $selComponentId > 0 ? $selComponentId : null,
    ],
    'component_type_active' => $selType,
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
    'component_action_params' => array_filter([
        'month'         => substr($reconDate, 0, 7),
        'location_type' => $selLocType,
        'division_id'   => $selDivId > 0 ? $selDivId : '',
        'component_id'  => $selComponentId > 0 ? $selComponentId : '',
    ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<div class="alert alert-light border mb-3 py-2 small">
  <strong>Daily Recon dipakai saat stok fisik component sudah dihitung.</strong>
  Isi stok fisik terakhir, bukan jumlah selisih. Saat diposting, sistem menghitung perbedaannya lalu menyamakan lot component dengan stok akhir tersebut.
  Untuk waste, spoil, atau perubahan tertentu tanpa hitung fisik menyeluruh, gunakan <a href="<?= site_url('production/component-adjustments') ?>">Adjustment Base/Prepare</a>.
</div>

<style>
#cmpTableWrap {
    width:100%;
    max-width:100%;
    overflow:auto;
    scrollbar-gutter:stable both-edges;
}
#cmpTableWrap table  {
    border-collapse:collapse;
    width:max-content;
    min-width:1380px;
    table-layout:fixed;
}
#cmpTableWrap thead th {
    position:sticky; top:0; z-index:2;
    font-size:.73rem; font-weight:700; color:#334155;
    background:#e2e8f0; border-bottom:2px solid #94a3b8 !important;
    padding:6px 8px; white-space:nowrap;
}
#cmpTbody tr           { border-bottom:1px solid #dee2e6; }
#cmpTbody tr > td      { border:none; padding:5px 8px; vertical-align:middle; }
#cmpTbody tr:nth-child(even) > td { background:#f8fafc; }
#cmpTbody tr:nth-child(odd)  > td { background:#ffffff; }
#cmpTbody tr:hover > td      { background:#eff6ff !important; }
.cmp-row-minus > td          { background:#fff1f2 !important; }
.cmp-row-minus > td:first-child { border-left:3px solid #f87171; }

.cmp-div-cell  { font-size:.7rem; color:#1e40af; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:104px; }
.cmp-tipe-reguler { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-tipe-event   { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-jenis-base { background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-jenis-prep { background:#ede9fe; color:#6d28d9; border:1px solid #c4b5fd; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-name-cell  { display:flex; align-items:flex-start; min-width:0; }
.cmp-name-body  { min-width:0; }
.cmp-adj-col    { width:220px; min-width:220px; max-width:220px; }
.cmp-action-col { width:150px; min-width:150px; max-width:150px; }
.filter-on      { background:#2563eb !important; color:#fff !important; border-color:#2563eb !important; }
.cmp-cost-cell  { font-size:.76rem; white-space:nowrap; }
.cmp-value-cell { font-size:.76rem; white-space:nowrap; }
#cmpTableWrap th:last-child,
#cmpTableWrap td[id^="acell-"] {
    position: sticky;
    right: 0;
    z-index: 3;
    background:#fff;
    box-shadow: -10px 0 18px rgba(15,23,42,.10);
}
#cmpTableWrap thead th:last-child {
    z-index: 5;
    background:#a70f25;
    color:#fff;
}
#cmpTbody tr:nth-child(even) > td[id^="acell-"] { background:#f8fafc; }
#cmpTbody tr:nth-child(odd) > td[id^="acell-"]  { background:#ffffff; }
#cmpTbody tr:hover > td[id^="acell-"]           { background:#eff6ff !important; }
.cmp-row-minus > td[id^="acell-"]               { background:#fff1f2 !important; }
.cmp-row-confirm .btn { font-size:.66rem; padding:.05rem .28rem; }
.cmp-action-stack {
    display:flex;
    flex-direction:column;
    align-items:stretch;
    gap:.35rem;
    min-width:126px;
}
.cmp-action-stack .btn {
    min-height:30px;
    white-space:nowrap;
}
.cmp-action-stack .badge {
    white-space:nowrap;
}
.cmp-row-confirm > .d-flex {
    flex-direction:column;
}
.cmp-lot-toggle-btn {
    width:100%;
    min-height:30px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.2rem;
    line-height:1.1;
}
.cmp-flash { border-radius:12px; border:1px solid #c7e7d3; background:#edf9f1; color:#166534; padding:.65rem .85rem; font-size:.82rem; }
.cmp-flash.error { border-color:#f3c1c1; background:#fff1f2; color:#b42318; }
.cmp-required-warning {
    display:grid;
    grid-template-columns:auto 1fr;
    gap:.75rem;
    align-items:flex-start;
}
.cmp-required-warning-icon {
    width:34px;
    height:34px;
    border-radius:12px;
    display:grid;
    place-items:center;
    color:#fff;
    background:linear-gradient(135deg,#b42318,#f59e0b);
}
.cmp-required-warning-title {
    font-weight:800;
    color:#8f1d16;
    margin-bottom:.15rem;
}
.cmp-required-warning-subtitle {
    color:#7f544d;
    font-size:.78rem;
    margin-bottom:.6rem;
}
.cmp-required-warning-list {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.4rem;
}
.cmp-required-warning-item {
    border:1px solid #f2c6c6;
    background:#fffafa;
    border-radius:12px;
    padding:.45rem .55rem;
    min-width:0;
}
.cmp-required-warning-name {
    color:#3b2421;
    font-weight:800;
    font-size:.78rem;
    line-height:1.18;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.cmp-required-warning-reason {
    display:inline-block;
    margin-top:.25rem;
    border-radius:999px;
    background:#fff4cf;
    color:#8a5b00;
    padding:.08rem .42rem;
    font-size:.68rem;
    font-weight:800;
}
.cmp-required-warning-more {
    color:#8f1d16;
    font-size:.76rem;
    font-weight:800;
    margin-top:.55rem;
}
@media (max-width:768px) {
    .cmp-required-warning-list { grid-template-columns:1fr; }
}
</style>

<!-- Filter -->
  <form id="cmpForm" method="get" action="<?php echo site_url('production/component-daily-recon'); ?>"
        class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
      <label class="form-label form-label-sm mb-1">Tanggal</label>
      <input type="date" name="opname_date" class="form-control form-control-sm"
             value="<?php echo html_escape($reconDate); ?>">
    </div>
    <div class="col-auto">
      <label class="form-label form-label-sm mb-1">Divisi</label>
      <select name="division_id" class="form-select form-select-sm" style="min-width:130px">
        <option value="0">Semua Divisi</option>
        <?php foreach ($divisionList as $dv): ?>
          <option value="<?php echo (int)$dv['id']; ?>"
            <?php echo $selDivId === (int)$dv['id'] ? 'selected' : ''; ?>>
            <?php echo html_escape($dv['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label form-label-sm mb-1">Lokasi</label>
      <select name="location_type" class="form-select form-select-sm">
        <option value="" <?php echo $selLocType === '' ? 'selected' : ''; ?>>Semua</option>
        <option value="REGULER" <?php echo $selLocType === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
        <option value="EVENT"   <?php echo $selLocType === 'EVENT'   ? 'selected' : ''; ?>>Event</option>
      </select>
    </div>
    <input type="hidden" name="type" value="<?php echo html_escape($selType); ?>">
    <input type="hidden" name="component_id" value="<?php echo (int)$selComponentId; ?>">
    <div class="col">
      <label class="form-label form-label-sm mb-1">Cari</label>
      <input type="text" name="q" class="form-control form-control-sm"
             placeholder="Nama / kode component..." value="<?php echo html_escape($selQ); ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="ri ri-search-line me-1"></i>Tampilkan
      </button>
      <a href="<?php echo site_url('production/component-daily-recon'); ?>"
         class="btn btn-sm btn-outline-warning ms-1">
        <i class="ri ri-refresh-line"></i> Clear
      </a>
    </div>
  </form>

  <!-- Summary cards -->
  <div class="row g-2 mb-3">
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">Total</div><div class="fw-bold fs-5" id="smTotal">-</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">BASE</div><div class="fw-bold fs-5 text-primary" id="smBase">-</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">PREPARE</div><div class="fw-bold fs-5" style="color:#7c3aed" id="smPrep">-</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">Dihitung</div><div class="fw-bold fs-5 text-success" id="smCounted">-</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">Stok Minus</div><div class="fw-bold fs-5 text-danger" id="smMinus">-</div>
    </div></div></div>
  </div>

  <!-- Toolbar -->
  <div class="d-flex gap-2 mb-2 align-items-center">
    <?php if ($canCreate): ?>
      <button id="btnConfirmOpenRecon" type="button" class="btn btn-sm btn-warning">
        <i class="ri ri-door-open-line me-1"></i>Konfirmasi Buka
      </button>
      <button id="btnConfirmCloseRecon" type="button" class="btn btn-sm btn-success">
        <i class="ri ri-door-closed-line me-1"></i>Konfirmasi Tutup
      </button>
    <?php endif; ?>
    <button id="btnFilterConfirm" type="button" class="btn btn-sm btn-outline-primary" style="display:none">
      <i class="ri ri-checkbox-circle-line me-1"></i>Perlu Konfirmasi
      <span id="cmpConfirmBadge" class="badge bg-primary ms-1" style="display:none">0</span>
    </button>
    <button id="btnFilterMinus" class="btn btn-sm btn-outline-danger" style="display:none">
      <i class="ri ri-arrow-down-line me-1"></i>Tampilkan Minus
      <span id="cmpMinusBadge" class="badge bg-danger ms-1" style="display:none">0</span>
    </button>
    <div id="cmpSpinner" class="spinner-border spinner-border-sm text-secondary" style="display:none"></div>
    <span id="cmpErrMsg" class="text-danger small" style="display:none"></span>
  </div>
  <div id="cmpFlash" class="cmp-flash mb-2" style="display:none"></div>

  <!-- Table -->
  <div id="cmpTableWrap" class="table-responsive rounded border shadow-sm" style="max-height:68vh;overflow:auto">
    <table class="table table-sm mb-0">
      <thead>
        <tr>
          <th style="width:112px">Divisi / Lokasi</th>
          <th style="width:74px">Jenis</th>
          <th class="text-start" style="width:218px">Nama Component</th>
          <th style="width:58px">UOM</th>
          <th class="text-end" style="width:88px">Stok</th>
          <th class="text-end" style="width:92px">HPP Live</th>
          <th class="text-end" style="width:112px">Nilai Sistem</th>
          <th class="text-end" style="width:92px">Fisik</th>
          <th class="text-end" style="width:82px">Selisih</th>
          <th class="cmp-adj-col">Jenis &amp; Alasan</th>
          <th class="cmp-action-col">Aksi</th>
        </tr>
      </thead>
      <tbody id="cmpTbody">
        <tr><td colspan="11" class="text-center text-muted py-4">Memuat data...</td></tr>
      </tbody>
    </table>
  </div>

<div class="modal fade" id="componentDailyReconStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi Posting Daily Recon</h5>
          <div class="small text-muted">Masukkan password akun Anda sebelum hitungan fisik membuat adjustment pada stok, lot, dan nilai component.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="component_daily_recon_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="component_daily_recon_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada adjustment Daily Recon.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="btn-component-daily-recon-step-up-post">Verifikasi &amp; Post</button>
      </div>
    </div>
  </div>
</div>

<script>
const DATA_URL       = '<?php echo site_url('production/component-daily-recon/data'); ?>';
const USAGE_BASE_URL = '<?php echo site_url('production/component-masters/usage/'); ?>';
const SAVE_URL     = '<?php echo site_url('production/component-daily-recon/save-physical'); ?>';
const ADJ_URL      = '<?php echo site_url('production/component-daily-recon/quick-adjust'); ?>';
const CONFIRM_RECON_URL = '<?php echo site_url('production/component-daily-recon/confirm'); ?>';
const ADJ_STEP_UP_URL = '<?php echo site_url('production/component-daily-recon/step-up/verify'); ?>';
const ADJ_PAGE_URL = '<?php echo site_url('production/component-adjustments'); ?>';
const COMPONENT_DAILY_RECON_CSRF_TOKEN = <?php echo json_encode((string)($component_daily_recon_csrf_token ?? ''), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const CAN_CREATE   = <?php echo $canCreate ? 'true' : 'false'; ?>;
const RECON_DATE   = '<?php echo html_escape($reconDate); ?>';
const LOC_TYPE     = '<?php echo html_escape($selLocType); ?>';
const DIV_ID       = <?php echo (int)$selDivId; ?>;
const COMP_TYPE    = '<?php echo html_escape($selType); ?>';
const COMP_Q       = <?php echo json_encode($selQ); ?>;
const COMP_ID      = <?php echo (int)$selComponentId; ?>;

// Reason codes - sama persis dengan /production/component-adjustments
const REASONS = <?php echo json_encode($REASONS); ?>;

const ADJ_TYPES_NEG = [
    { val: 'ADJUSTMENT_MINUS', lbl: 'Adjustment Minus' },
    { val: 'WASTE',            lbl: 'Waste'            },
    { val: 'SPOILAGE',         lbl: 'Spoil'            },
];
const ADJ_TYPES_POS = [
    { val: 'ADJUSTMENT_PLUS', lbl: 'Adjustment Plus' },
];

// -- Helpers ---------------------------------------------------
const el  = id => document.getElementById(id);
const esc = s  => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; };
const fmt4 = v => isNaN(parseFloat(v)) ? '-' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
const fmtCost = v => isNaN(parseFloat(v)) ? '-' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtValue = v => isNaN(parseFloat(v)) ? '-' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function cssid(s) { return String(s).replace(/[^a-zA-Z0-9_-]/g, '_'); }
function round4(v) { return Math.round((parseFloat(v) || 0) * 10000) / 10000; }
function calcValue(qty, unitCost) { return round4(qty) * (parseFloat(unitCost) || 0); }
function dailyReconMutationHeaders() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Production-Component-Daily-Recon-Csrf': COMPONENT_DAILY_RECON_CSRF_TOKEN
    };
}
function postDailyReconJson(url, payload) {
    return fetch(url, {
        method: 'POST',
        headers: dailyReconMutationHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    }).then(parseJsonResponse);
}
function showFlash(message, isError = false, asHtml = false) {
    const box = el('cmpFlash');
    if (!box) return;
    if (asHtml) {
        box.innerHTML = message || '';
    } else {
        box.textContent = message || '';
    }
    box.classList.toggle('error', !!isError);
    box.style.display = message ? '' : 'none';
}

async function confirmDailyRecon(stage) {
    if (DIV_ID <= 0) {
        showFlash('Pilih satu divisi dulu sebelum konfirmasi daily recon.', true);
        return;
    }
    const label = stage === 'OPEN' ? 'buka kasir' : 'tutup kasir';
    const pending = requiredReconPending(stage);
    if (pending.length > 0) {
        showRequiredReconWarning(stage, pending);
        return;
    }
    if (!confirm('Konfirmasi daily recon component untuk ' + label + ' pada tanggal ' + RECON_DATE + '?')) {
        return;
    }
    try {
        const response = await fetch(CONFIRM_RECON_URL, {
            method: 'POST',
            headers: dailyReconMutationHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                opname_date: RECON_DATE,
                division_id: DIV_ID,
                stage: stage,
                notes: 'Konfirmasi dari halaman daily recon component'
            }),
        });
        const data = await parseJsonResponse(response);
        if (!data.ok) {
            showFlash(data.message || 'Konfirmasi daily recon gagal.', true);
            return;
        }
        showFlash(data.message || 'Daily recon component sudah dikonfirmasi.', false);
    } catch (err) {
        showFlash('Konfirmasi daily recon gagal: ' + err.message, true);
    }
}

function requiredReconPending(stage) {
    const pending = [];
    Object.values(profileMap).forEach(function (row) {
        if (!row || !row.must_row_confirm) return;
        const ok = stage === 'OPEN' ? !!row.confirmed_open : !!row.confirmed_close;
        if (ok) return;
        pending.push({
            name: row.lot_no ? ((row.component_name || 'Component') + ' / Lot ' + row.lot_no) : (row.component_name || row.recon_line_key || 'Component'),
            reason: row.must_row_confirm_reason || 'wajib recon per baris',
        });
    });
    return pending;
}

function showRequiredReconWarning(stage, pending) {
    const label = stage === 'OPEN' ? 'buka kasir' : 'tutup kasir';
    const items = pending.slice(0, 8).map(function (item) {
        return '<div class="cmp-required-warning-item">'
            + '<div class="cmp-required-warning-name" title="' + esc(item.name || '') + '">' + esc(item.name || 'Component') + '</div>'
            + '<span class="cmp-required-warning-reason">' + esc(item.reason || 'wajib recon') + '</span>'
            + '</div>';
    }).join('');
    const more = pending.length > 8
        ? '<div class="cmp-required-warning-more">+' + (pending.length - 8) + ' item lainnya wajib dicek satu per satu.</div>'
        : '';
    showFlash(
        '<div class="cmp-required-warning">'
        + '<div class="cmp-required-warning-icon"><i class="ri ri-alert-line"></i></div>'
        + '<div>'
        + '<div class="cmp-required-warning-title">Konfirmasi semua untuk ' + esc(label) + ' belum bisa disimpan</div>'
        + '<div class="cmp-required-warning-subtitle">' + pending.length + ' baris wajib direkon satu per satu dulu karena multi-lot, stok minus, atau masuk daftar wajib recon.</div>'
        + '<div class="cmp-required-warning-list">' + items + '</div>'
        + more
        + '</div>'
        + '</div>',
        true,
        true
    );
}

window.cmpConfirmReconRow = function (iid, stage) {
    const row = profileMap[iid];
    if (!row) return;
    if (DIV_ID <= 0) {
        showFlash('Pilih satu divisi dulu sebelum konfirmasi baris recon.', true);
        return;
    }
    fetch(CONFIRM_RECON_URL, {
        method: 'POST',
        headers: dailyReconMutationHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            scope: 'ROW',
            opname_date: RECON_DATE,
            division_id: DIV_ID,
            stage: stage,
            line_key: row.recon_line_key || '',
            line_label: row.lot_no ? ((row.component_name || 'Component') + ' / Lot ' + row.lot_no) : (row.component_name || ''),
            component_id: row.component_id || 0,
            uom_id: row.uom_id || 0,
            lot_id: row.lot_id || 0,
            required_reason: row.must_row_confirm_reason || '',
            notes: 'Konfirmasi baris dari daily recon component'
        }),
    })
    .then(parseJsonResponse)
    .then(data => {
        if (!data.ok) {
            showFlash(data.message || 'Konfirmasi baris gagal.', true);
            return;
        }
        if (stage === 'OPEN') row.confirmed_open = true;
        if (stage === 'CLOSE') row.confirmed_close = true;
        const actCell = el('acell-' + iid);
        if (actCell) actCell.outerHTML = actionCell(row, iid);
        showFlash(data.message || 'Baris recon berhasil dikonfirmasi.', false);
    })
    .catch(err => showFlash('Konfirmasi baris gagal: ' + err.message, true));
};
async function parseJsonResponse(response) {
    const raw = await response.text();
    try {
        const data = JSON.parse(raw);
        if (!response.ok && data && typeof data === 'object' && !data.message) {
            data.message = 'Server menolak permintaan (HTTP ' + response.status + ').';
        }
        return data;
    } catch (err) {
        const detail = raw
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 220);
        throw new Error(
            'Server tidak mengirim respons yang dapat dibaca (HTTP ' + response.status + ').'
            + (detail ? ' Ringkasan: ' + detail : ' Muat ulang halaman lalu cek log server bila berulang.')
        );
    }
}

const profileMap = {};
let currentGroups = [];
let reconConfirmMode = 'BULK_ALLOWED';

function selisihHtml(sel, iid) {
    if (sel === null || sel === undefined) return `<span id="sel-${iid}" class="text-muted small">-</span>`;
    const v = Number(sel);
    if (Math.abs(v) < 0.001) return `<span id="sel-${iid}" class="text-success fw-bold">+/- 0</span>`;
    const cls = v < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
    return `<span id="sel-${iid}" class="${cls}">${v > 0 ? '+' : ''}${fmt4(v)}</span>`;
}

function tipeBadge(lt) {
    return lt === 'EVENT'
        ? `<span class="cmp-tipe-event">Event</span>`
        : `<span class="cmp-tipe-reguler">Reguler</span>`;
}
function jenisBadge(ct) {
    return ct === 'BASE'
        ? `<span class="cmp-jenis-base">BASE</span>`
        : `<span class="cmp-jenis-prep">PREP</span>`;
}

function buildLabel(row) {
    const usageUrl = USAGE_BASE_URL + (parseInt(row.component_id, 10) || 0);
    let inner = `<div class="fw-semibold" style="font-size:.82rem"><a href="${usageUrl}" class="text-decoration-none text-body">${esc(row.component_name)}</a></div>`;
    const sub = [row.component_code, row.category_name].filter(Boolean);
    if (sub.length) inner += `<div class="text-muted" style="font-size:.74rem">${esc(sub.join(' / '))}</div>`;
    return `<div class="cmp-name-cell"><div class="cmp-name-body">${inner}</div></div>`;
}

function buildCostCell(unitCost, extraClass = '') {
    return `<td class="text-end cmp-cost-cell ${extraClass}">${fmtCost(unitCost)}</td>`;
}

function buildValueCell(id, qty, unitCost, extraClass = '') {
    return `<td id="${id}" class="text-end cmp-value-cell ${extraClass}">${fmtValue(calcValue(qty, unitCost))}</td>`;
}

// ?????? Adjustment col ??????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????? ---------------------------------------------
function adjColHtml(row, iid) {
    if (row.adjustment_id) {
        return `<td id="adjcol-${iid}" class="cmp-adj-col">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="ri ri-check-double-line me-1"></i>Adj #${row.adjustment_id}
            </span>
        </td>`;
    }
    const sel = row.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        const isNeg  = Number(sel) < 0;
        const types  = isNeg ? ADJ_TYPES_NEG : ADJ_TYPES_POS;
        const typeOpts = `<option value="">- pilih jenis -</option>` +
            types.map(t => `<option value="${t.val}">${t.lbl}</option>`).join('');
        return `<td id="adjcol-${iid}" class="cmp-adj-col py-1">
            <div class="d-flex flex-column gap-1">
                <select id="adjtype-${iid}" class="form-select form-select-sm" style="font-size:.72rem"
                        onchange="cmpTypeChange('${iid}')">
                    ${typeOpts}
                </select>
                <input type="number" id="adjcost-${iid}" class="form-control form-control-sm d-none"
                       min="0" step="0.000001" placeholder="HPP / Unit Cost"
                       style="font-size:.72rem" title="HPP wajib diisi untuk adjustment plus">
                <select id="adjreason-${iid}" class="form-select form-select-sm d-none" style="font-size:.72rem"></select>
                <input type="text" id="adjnotes-${iid}" class="form-control form-control-sm d-none"
                       placeholder="Catatan (opsional)" style="font-size:.72rem">
            </div>
        </td>`;
    }
    return `<td id="adjcol-${iid}" class="cmp-adj-col"></td>`;
}

function actionCell(row, iid) {
    if (!CAN_CREATE) return `<td id="acell-${iid}" class="cmp-action-col"></td>`;
    const reconHtml = reconRowButtons(row, iid);
    if (row.adjustment_id) return `<td id="acell-${iid}" class="cmp-action-col"><div class="cmp-action-stack">${reconHtml}</div></td>`;
    const sel = row.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        return `<td id="acell-${iid}" class="cmp-action-col">
            <div class="cmp-action-stack">
            <button class="btn btn-sm btn-danger w-100" id="adjbtn-${iid}"
                    onclick="cmpPostAdj('${iid}')" title="Posting adjustment">
                <i class="ri ri-upload-2-line me-1"></i>Post
            </button>
            ${reconHtml}
            </div>
        </td>`;
    }
    return `<td id="acell-${iid}" class="cmp-action-col"><div class="cmp-action-stack">${reconHtml}</div></td>`;
}

function reconRowButtons(row, iid) {
    if (!row || !row.must_row_confirm) return '';
    const reason = row.must_row_confirm_reason
        ? `<div class="text-muted" style="font-size:.62rem;line-height:1.1">${esc(row.must_row_confirm_reason)}</div>`
        : '';
    const openBtn = row.confirmed_open
        ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Buka OK</span>`
        : `<button type="button" class="btn btn-sm btn-outline-warning py-0" onclick="event.stopPropagation();cmpConfirmReconRow('${iid}','OPEN')">Buka</button>`;
    const closeBtn = row.confirmed_close
        ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Tutup OK</span>`
        : `<button type="button" class="btn btn-sm btn-outline-success py-0" onclick="event.stopPropagation();cmpConfirmReconRow('${iid}','CLOSE')">Tutup</button>`;
    return `<div class="cmp-row-confirm d-flex flex-column gap-1">${reason}<div class="d-flex gap-1 justify-content-center">${openBtn}${closeBtn}</div></div>`;
}

function cmpNeedsConfirm(row) {
    return !!(row && row.must_row_confirm && !row.confirmed_close);
}

// -- Expand / collapse lots ------------------------------------
window.cmpToggleLots = function (iid) {
    const btn  = el('expand-' + iid);
    const open = btn && btn.dataset.expanded === 'true';
    document.querySelectorAll('.lot-child-' + iid).forEach(function (r) {
        r.style.display = open ? 'none' : '';
    });
    if (btn) {
        btn.dataset.expanded = open ? 'false' : 'true';
        btn.innerHTML = open
            ? '<i class="ri ri-arrow-right-s-line"></i> ' + btn.dataset.lotCount + ' lot'
            : '<i class="ri ri-arrow-down-s-line"></i> ' + btn.dataset.lotCount + ' lot';
    }
    if (showOnlyMinus || showOnlyConfirm) {
        applyCmpFilters();
    }
};

// -- Render table ----------------------------------------------
function renderTable(groups) {
    const tbody = el('cmpTbody');
    if (!tbody) return;

    let html = '';
    let total = 0, base = 0, prep = 0, counted = 0, minus = 0, confirmNeed = 0;
    Object.keys(profileMap).forEach(function (key) { delete profileMap[key]; });

    groups.forEach(function (grp) {
        grp.rows.forEach(function (row) {
            const iid = cssid(row.identity_key);
            const isMultiLot = row.lot_count > 1 && Array.isArray(row.lots) && row.lots.length > 1;
            const rowAvgCost = parseFloat(row.balance_avg_cost || row.avg_cost || 0);

            profileMap[iid] = row;
            profileMap[iid].avg_cost = rowAvgCost;
            total++;
            if (row.component_type === 'BASE') base++; else prep++;

            if (isMultiLot) {
                let parentPhysicalQty = null;
                if (row.lots.every(function (lot) { return lot.physical_qty !== null && lot.physical_qty !== undefined && lot.physical_qty !== ''; })) {
                    parentPhysicalQty = row.lots.reduce(function (sum, lot) {
                        return sum + (parseFloat(lot.physical_qty) || 0);
                    }, 0);
                }
                const parentNeedConfirm = cmpNeedsConfirm(row) || row.lots.some(function (lot) { return cmpNeedsConfirm(lot); });
                if (parentNeedConfirm) confirmNeed++;

                html += `<tr id="row-${iid}" data-system-val="${row.system_qty}" data-div-id="${row.division_id}" data-confirm-required="${parentNeedConfirm ? '1' : '0'}" data-has-lots="1" style="background:#fdf6f0">
                    <td class="cmp-div-cell">
                        <div>${esc(row.division_name)}</div>
                        <div class="mt-1">${tipeBadge(row.location_type)}</div>
                    </td>
                    <td>${jenisBadge(row.component_type)}</td>
                    <td class="text-start">${buildLabel(row)}</td>
                    <td class="text-muted" style="font-size:.8rem">${esc(row.uom_code || '-')}</td>
                    <td class="text-end" style="font-size:.85rem;color:#64748b">${fmt4(row.system_qty)}</td>
                    ${buildCostCell(rowAvgCost)}
                    ${buildValueCell(`sysval-${iid}`, row.system_qty, rowAvgCost)}
                    <td class="text-end cmp-value-cell text-muted">${parentPhysicalQty === null ? '-' : fmt4(parentPhysicalQty)}</td>
                    <td class="text-end text-muted small">Lot detail</td>
                    <td class="cmp-adj-col"></td>
                    <td id="acell-${iid}" class="text-center cmp-action-col">
                        <div class="cmp-action-stack">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 cmp-lot-toggle-btn"
                                id="expand-${iid}" data-expanded="true" data-lot-count="${row.lot_count}"
                                onclick="cmpToggleLots('${iid}')">
                            <i class="ri ri-arrow-down-s-line"></i> ${row.lot_count} lot
                        </button>
                        ${reconRowButtons(row, iid)}
                        </div>
                    </td>
                </tr>`;

                row.lots.forEach(function (lot) {
                    const lotIid = cssid(lot.identity_key);
                    profileMap[lotIid] = {
                        location_type: row.location_type,
                        division_id: row.division_id,
                        division_name: row.division_name,
                        division_code: row.division_code,
                        component_id: row.component_id,
                        uom_id: row.uom_id,
                        uom_code: row.uom_code,
                        component_type: row.component_type,
                        lot_id: lot.lot_id,
                        lot_no: lot.lot_no,
                        identity_key: lot.identity_key,
                        system_qty: lot.system_qty,
                        avg_cost: lot.unit_cost,
                        physical_qty: lot.physical_qty,
                        selisih: lot.selisih,
                        adjustment_id: lot.adjustment_id,
                        recon_line_key: lot.recon_line_key,
                        must_row_confirm: lot.must_row_confirm,
                        must_row_confirm_reason: lot.must_row_confirm_reason,
                        confirmed_open: lot.confirmed_open,
                        confirmed_close: lot.confirmed_close,
                    };

                    if (lot.physical_qty !== null) counted++;
                    if (parseFloat(lot.system_qty) < -0.001) minus++;
                    if (cmpNeedsConfirm(profileMap[lotIid])) confirmNeed++;

                    const lotPhysVal = lot.physical_qty !== null ? lot.physical_qty : '';
                    const lotCls = parseFloat(lot.system_qty) < -0.001 ? 'cmp-row-minus' : '';

                    html += `<tr id="row-${lotIid}" class="lot-child lot-child-${iid} ${lotCls}"
                                 data-system-val="${lot.system_qty}" data-div-id="${row.division_id}" data-parent="${iid}" data-confirm-required="${cmpNeedsConfirm(profileMap[lotIid]) ? '1' : '0'}">
                        <td class="cmp-div-cell" style="padding-left:20px;border-left:3px solid #e2e8f0">
                            <div class="text-muted fw-semibold" style="font-size:.72rem">Lot ${esc(lot.lot_no)}</div>
                            <div class="text-muted" style="font-size:.67rem">${esc(lot.receipt_date || '')}</div>
                        </td>
                        <td></td>
                        <td class="text-muted" style="font-size:.76rem;padding-left:4px">${esc(lot.lot_no)}</td>
                        <td class="text-muted" style="font-size:.8rem">${esc(row.uom_code || '-')}</td>
                        <td class="text-end" style="font-size:.85rem">${fmt4(lot.system_qty)}</td>
                        ${buildCostCell(lot.unit_cost)}
                        ${buildValueCell(`sysval-${lotIid}`, lot.system_qty, lot.unit_cost)}
                        <td class="text-end">
                            <input type="number" class="form-control form-control-sm text-end"
                                   style="width:88px;display:inline-block"
                                   step="0.01" value="${esc(lotPhysVal)}" placeholder="-"
                                   data-iid="${lotIid}" data-sys="${lot.system_qty}"
                                   oninput="cmpLiveCalc(this,'${lotIid}')"
                                   onchange="cmpSavePhys('${lotIid}')"
                                   ${!CAN_CREATE ? 'disabled' : ''}>
                        </td>
                        <td class="text-end">${selisihHtml(lot.selisih, lotIid)}</td>
                        ${adjColHtml(profileMap[lotIid], lotIid)}
                        ${actionCell(profileMap[lotIid], lotIid)}
                    </tr>`;
                });
            } else {
                if (row.physical_qty !== null) counted++;
                if (parseFloat(row.system_qty) < -0.001) minus++;
                if (cmpNeedsConfirm(row)) confirmNeed++;

                const rowCls = parseFloat(row.system_qty) < -0.001 ? 'cmp-row-minus' : '';
                const physVal = row.physical_qty !== null ? row.physical_qty : '';

                html += `<tr id="row-${iid}" class="${rowCls}"
                             data-system-val="${row.system_qty}" data-div-id="${row.division_id}" data-confirm-required="${cmpNeedsConfirm(row) ? '1' : '0'}">
                    <td class="cmp-div-cell">
                        <div>${esc(row.division_name)}</div>
                        <div class="mt-1">${tipeBadge(row.location_type)}</div>
                    </td>
                    <td>${jenisBadge(row.component_type)}</td>
                    <td class="text-start">${buildLabel(row)}</td>
                    <td class="text-muted" style="font-size:.8rem">${esc(row.uom_code || '-')}</td>
                    <td class="text-end" style="font-size:.85rem">${fmt4(row.system_qty)}</td>
                    ${buildCostCell(rowAvgCost)}
                    ${buildValueCell(`sysval-${iid}`, row.system_qty, rowAvgCost)}
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm text-end"
                               style="width:88px;display:inline-block"
                               step="0.01" value="${esc(physVal)}" placeholder="-"
                               data-iid="${iid}" data-sys="${row.system_qty}"
                               oninput="cmpLiveCalc(this,'${iid}')"
                               onchange="cmpSavePhys('${iid}')"
                               ${!CAN_CREATE ? 'disabled' : ''}>
                    </td>
                    <td class="text-end">${selisihHtml(row.selisih, iid)}</td>
                    ${adjColHtml(row, iid)}
                    ${actionCell(row, iid)}
                </tr>`;
            }
        });
    });

    tbody.innerHTML = html || `<tr><td colspan="11" class="text-center text-muted py-4">Tidak ada data.</td></tr>`;

    const s = (id, v) => { const e = el(id); if (e) e.textContent = v; };
    s('smTotal', total);
    s('smBase', base);
    s('smPrep', prep);
    s('smCounted', counted + ' / ' + total);
    s('smMinus', minus);
    s('cmpBadgeTotal', total + ' item');

    const minusBadge = el('cmpMinusBadge');
    const filterBtn = el('btnFilterMinus');
    const confirmBadge = el('cmpConfirmBadge');
    const confirmBtn = el('btnFilterConfirm');
    if (minusBadge) { minusBadge.textContent = minus; minusBadge.style.display = minus > 0 ? '' : 'none'; }
    if (filterBtn) filterBtn.style.display = minus > 0 ? '' : 'none';
    if (confirmBadge) { confirmBadge.textContent = confirmNeed; confirmBadge.style.display = confirmNeed > 0 ? '' : 'none'; }
    if (confirmBtn) confirmBtn.style.display = confirmNeed > 0 ? '' : 'none';
    applyCmpFilters();
}

// ?????? Type / reason change ?????????????????????????????????????????????????????????????????????????????????????????????????????????????????? --------------------------------------
window.cmpTypeChange = function (iid) {
    const typeSel   = el('adjtype-'   + iid);
    const costInp   = el('adjcost-'   + iid);
    const reasonSel = el('adjreason-' + iid);
    const notesInp  = el('adjnotes-'  + iid);
    if (!typeSel || !reasonSel) return;
    if (!typeSel.value) {
        if (costInp) costInp.classList.add('d-none');
        reasonSel.classList.add('d-none');
        if (notesInp) notesInp.classList.add('d-none');
        return;
    }
    const isPlus = typeSel.value === 'ADJUSTMENT_PLUS';
    if (costInp) {
        costInp.classList.toggle('d-none', !isPlus);
        if (isPlus && !costInp.value) {
            const row = profileMap[iid];
            const autoHpp = parseFloat(row?.avg_cost || row?.balance_avg_cost || 0);
            if (autoHpp > 0) costInp.value = String(autoHpp);
        }
    }
    const opts = REASONS[typeSel.value] || { other: 'Other' };
    reasonSel.innerHTML = Object.entries(opts)
        .map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
    reasonSel.classList.remove('d-none');
    if (notesInp) notesInp.classList.remove('d-none');
};

// -- Live selisih ----------------------------------------------
window.cmpLiveCalc = function (inp, iid) {
    const row = profileMap[iid];
    const sys = parseFloat(inp.dataset.sys) || 0;
    const phys = inp.value.trim() !== '' ? parseFloat(inp.value) : null;
    const selEl = el('sel-' + iid);
    const pValEl = el('pval-' + iid);
    if (!selEl) return;

    row.selisih = phys !== null ? phys - sys : null;
    row.physical_qty = phys;

    if (phys === null) {
        selEl.textContent = '-';
        selEl.className = 'text-muted small';
    } else {
        const diff = phys - sys;
        selEl.textContent = (diff > 0 ? '+' : '') + fmt4(diff);
        selEl.className = Math.abs(diff) < 0.001 ? 'text-success fw-bold' : diff < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
    }

    if (pValEl) {
        pValEl.textContent = phys === null ? '-' : fmtValue(calcValue(phys, row.avg_cost || row.balance_avg_cost || 0));
    }

    const adjCell = el('adjcol-' + iid);
    if (adjCell) adjCell.outerHTML = adjColHtml(row, iid);
    const actCell = el('acell-' + iid);
    if (actCell) actCell.outerHTML = actionCell(row, iid);

    const tr = el('row-' + iid);
    if (tr) tr.classList.toggle('cmp-row-minus', sys < -0.001);
};

// ?????? Save physical ??????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????? ---------------------------------------------
window.cmpSavePhys = function (iid) {
    if (!CAN_CREATE) return;
    const row = profileMap[iid];
    if (!row) return;
    const inp = document.querySelector(`input[data-iid="${iid}"]`);
    if (!inp) return;
    const physVal = inp.value.trim() !== '' ? parseFloat(inp.value) : null;

    fetch(SAVE_URL, {
        method: 'POST',
        headers: dailyReconMutationHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            opname_date: RECON_DATE,
            location_type: row.location_type,
            division_id: row.division_id || null,
            division_code: row.division_code || '',
            component_id: row.component_id,
            uom_id: row.uom_id,
            lot_id: row.lot_id || 0,
            system_qty: row.system_qty,
            physical_qty: physVal,
        }),
    })
    .then(parseJsonResponse)
    .then(data => {
        if (!data.ok) {
            showFlash(data.message || 'Gagal menyimpan stok fisik.', true);
            return;
        }
        if (data.system_qty !== undefined) {
            row.system_qty = Number(data.system_qty) || 0;
        }
        row.physical_qty = physVal;
        row.selisih = data.selisih !== undefined ? data.selisih : (physVal !== null ? round4(physVal - row.system_qty) : null);
        inp.dataset.sys = String(row.system_qty);
        window.cmpLiveCalc(inp, iid);
        showFlash('Stok fisik tersimpan.', false);
    })
    .catch(err => {
        console.error('Save error:', err);
        showFlash('Gagal menyimpan stok fisik: ' + err.message, true);
    });
};

const dailyReconStepUpModalEl = el('componentDailyReconStepUpModal');
const dailyReconStepUpPassword = el('component_daily_recon_step_up_password');
const btnDailyReconStepUpPost = el('btn-component-daily-recon-step-up-post');
let pendingDailyReconAdjustment = null;
let dailyReconStepUpSubmitting = false;

function restoreDailyReconAdjustmentButton(pending) {
    if (pending?.button) {
        pending.button.disabled = false;
        pending.button.innerHTML = pending.originalLabel;
    }
}

function setDailyReconStepUpDismissDisabled(disabled) {
    dailyReconStepUpModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => { button.disabled = disabled; });
}

// Post adjustment after a one-use password proof bound to this component.
window.cmpPostAdj = function (iid) {
    const row = profileMap[iid];
    if (!row) return;

    const typeVal = el('adjtype-' + iid)?.value;
    if (!typeVal) {
        alert('Pilih jenis adjustment terlebih dahulu.');
        el('adjtype-' + iid)?.focus();
        return;
    }

    const isPlus = typeVal === 'ADJUSTMENT_PLUS';
    const costInp = el('adjcost-' + iid);
    const userHpp = parseFloat(costInp?.value || 0);
    if (isPlus && !(userHpp > 0)) {
        alert('HPP / Unit Cost wajib diisi untuk adjustment plus. Masukkan nilai HPP terlebih dahulu.');
        costInp?.focus();
        return;
    }
    if (!confirm('Posting hasil Daily Recon akan mengubah stok, lot, dan nilai component. Lanjutkan ke verifikasi password?')) {
        return;
    }

    const modal = dailyReconStepUpModalEl && window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(dailyReconStepUpModalEl)
        : null;
    const btn = el('adjbtn-' + iid);
    if (!modal || !btn) {
        showFlash('Verifikasi posting belum siap. Muat ulang halaman lalu coba kembali.', true);
        return;
    }

    pendingDailyReconAdjustment = {
        iid,
        row,
        button: btn,
        originalLabel: btn.innerHTML,
        payload: {
            opname_date: RECON_DATE,
            location_type: row.location_type,
            division_id: row.division_id || null,
            division_code: row.division_code,
            component_id: row.component_id,
            uom_id: row.uom_id,
            lot_id: row.lot_id || 0,
            input_mode: 'PHYSICAL_COUNT',
            system_qty: row.system_qty,
            physical_qty: row.physical_qty,
            settle_open_deficit: Number(row.selisih || 0) > 0.0001 ? 1 : 0,
            avg_cost: isPlus ? userHpp : (parseFloat(row.avg_cost || row.balance_avg_cost || 0)),
            adjustment_type: typeVal,
            reason_code: el('adjreason-' + iid)?.value || 'other',
            notes: el('adjnotes-' + iid)?.value?.trim() || '',
        }
    };
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    if (dailyReconStepUpPassword) dailyReconStepUpPassword.value = '';
    modal.show();
    window.setTimeout(() => dailyReconStepUpPassword?.focus(), 150);
};

btnDailyReconStepUpPost?.addEventListener('click', async () => {
    if (dailyReconStepUpSubmitting) return;
    const pending = pendingDailyReconAdjustment;
    try {
        if (!pending || !(Number(pending.payload.component_id) > 0)) throw new Error('Data Daily Recon tidak valid. Tutup modal lalu coba lagi.');
        const password = String(dailyReconStepUpPassword?.value || '');
        if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi posting Daily Recon.');
        if (dailyReconStepUpPassword) dailyReconStepUpPassword.value = '';
        dailyReconStepUpSubmitting = true;
        setDailyReconStepUpDismissDisabled(true);
        btnDailyReconStepUpPost.disabled = true;
        btnDailyReconStepUpPost.textContent = 'Memverifikasi...';
        const stepUp = await postDailyReconJson(ADJ_STEP_UP_URL, {
            component_id: pending.payload.component_id,
            password
        });
        if (!stepUp.ok || !/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) {
            throw new Error(stepUp.message || 'Bukti verifikasi ulang tidak valid. Coba lagi.');
        }
        const data = await postDailyReconJson(ADJ_URL, {
            ...pending.payload,
            step_up_proof: String(stepUp.step_up_proof)
        });
        if (!data.ok) throw new Error(data.message || 'Adjustment gagal diposting.');

        pending.row.adjustment_id = data.adjustment_id;
        const adjCell = el('adjcol-' + pending.iid);
        if (adjCell) adjCell.outerHTML = adjColHtml(pending.row, pending.iid);
        const actCell = el('acell-' + pending.iid);
        if (actCell) actCell.outerHTML = actionCell(pending.row, pending.iid);
        const successMessage = (data.message || 'Adjustment berhasil diposting.')
            + (data.warning ? ' ' + data.warning : '');
        showFlash(successMessage, !!data.warning);
        dailyReconStepUpSubmitting = false;
        setDailyReconStepUpDismissDisabled(false);
        window.bootstrap?.Modal.getInstance(dailyReconStepUpModalEl)?.hide();
    } catch (err) {
        dailyReconStepUpSubmitting = false;
        setDailyReconStepUpDismissDisabled(false);
        if (btnDailyReconStepUpPost) {
            btnDailyReconStepUpPost.disabled = false;
            btnDailyReconStepUpPost.textContent = 'Verifikasi & Post';
        }
        restoreDailyReconAdjustmentButton(pending);
        showFlash('Request gagal: ' + (err.message || 'Verifikasi atau posting Daily Recon gagal.'), true);
    }
});

dailyReconStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (dailyReconStepUpSubmitting) return;
    if (dailyReconStepUpPassword) dailyReconStepUpPassword.value = '';
    restoreDailyReconAdjustmentButton(pendingDailyReconAdjustment);
    pendingDailyReconAdjustment = null;
    if (btnDailyReconStepUpPost) {
        btnDailyReconStepUpPost.disabled = false;
        btnDailyReconStepUpPost.textContent = 'Verifikasi & Post';
    }
});

// Filter baris minus dan wajib recon.
let showOnlyMinus = false;
let showOnlyConfirm = false;

function cmpRowPassesFilters(row) {
    if (showOnlyMinus && !(parseFloat(row.dataset.systemVal || 0) < -0.001)) {
        return false;
    }
    if (showOnlyConfirm && row.dataset.confirmRequired !== '1') {
        return false;
    }
    return true;
}

function applyCmpFilters() {
    const minusBtn = el('btnFilterMinus');
    const confirmBtn = el('btnFilterConfirm');
    if (minusBtn) minusBtn.classList.toggle('filter-on', showOnlyMinus);
    if (confirmBtn) confirmBtn.classList.toggle('filter-on', showOnlyConfirm);

    const rows = Array.from(document.querySelectorAll('#cmpTbody tr'));
    rows.forEach(function (row) {
        if (!row.dataset.hasLots) {
            row.style.display = cmpRowPassesFilters(row) ? '' : 'none';
        }
    });

    rows.forEach(function (row) {
        if (!row.dataset.hasLots) return;
        const parentId = row.id.replace('row-', '');
        const children = rows.filter(function (child) { return child.dataset.parent === parentId; });
        const hasVisibleChild = children.some(function (child) { return child.style.display !== 'none'; });
        const parentPass = cmpRowPassesFilters(row);
        row.style.display = (parentPass || hasVisibleChild) ? '' : 'none';
    });
}

document.addEventListener('click', function (e) {
    if (e.target.closest('#btnConfirmOpenRecon')) {
        confirmDailyRecon('OPEN');
        return;
    }
    if (e.target.closest('#btnConfirmCloseRecon')) {
        confirmDailyRecon('CLOSE');
        return;
    }
    if (e.target.closest('#btnFilterConfirm') || e.target.closest('#cmpConfirmBadge')) {
        showOnlyConfirm = !showOnlyConfirm;
        applyCmpFilters();
        return;
    }
    if (e.target.closest('#btnFilterMinus') || e.target.closest('#cmpMinusBadge')) {
        showOnlyMinus = !showOnlyMinus;
        applyCmpFilters();
    }
});

// -- Load data -------------------------------------------------
function loadData() {
    const spinner = el('cmpSpinner');
    const errMsg = el('cmpErrMsg');
    const tbody = el('cmpTbody');
    if (spinner) spinner.style.display = '';
    if (errMsg) errMsg.style.display = 'none';

    const params = new URLSearchParams({
        opname_date: RECON_DATE,
        location_type: LOC_TYPE,
        division_id: DIV_ID,
        type: COMP_TYPE,
        q: COMP_Q,
        component_id: COMP_ID,
    });

    fetch(DATA_URL + '?' + params, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then(parseJsonResponse)
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            if (!data.ok) {
                if (errMsg) { errMsg.textContent = data.message || 'Gagal.'; errMsg.style.display = ''; }
                if (tbody) tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-4">Gagal memuat data.</td></tr>`;
                return;
            }
            currentGroups = data.rows || [];
            reconConfirmMode = (data.meta && data.meta.confirm_mode) || 'BULK_ALLOWED';
            renderTable(currentGroups);
            applyCmpFilters();
        })
        .catch(err => {
            if (spinner) spinner.style.display = 'none';
            if (errMsg) { errMsg.textContent = 'Request gagal: ' + err.message; errMsg.style.display = ''; }
            if (tbody) tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-4">Request gagal.</td></tr>`;
        });
}

loadData();
</script>

<?php $this->load->view('layout/footer'); ?>
