<?php
$adjustDate  = (string)($opname_date ?? date('Y-m-d'));
$divisionId  = (int)($division_id    ?? 0);
$destination = strtoupper((string)($destination ?? 'ALL'));
$q           = (string)($q ?? '');
$profileKey  = (string)($profile_key ?? '');
$divisions   = is_array($divisions ?? null) ? $divisions : [];

$isSuperadmin = !empty($current_user['is_superadmin']);
$canCreate    = $isSuperadmin || !empty($can_create);

$baseUrl      = site_url('inventory/stock/daily-recon/division');
$dataUrl      = site_url('inventory/stock/daily-recon/division/data');
$quickAdjUrl  = site_url('inventory/stock/daily-recon/division/quick-adjust');
$confirmReconUrl = site_url('inventory/stock/daily-recon/division/confirm');
$profileMergeUrl = site_url('inventory/stock/division/reconcile/profile-merge');

$destOptions = [
    'ALL'     => 'Semua',
    'REGULER' => 'Reguler',
    'EVENT'   => 'Event',
];

$REASONS = [
    'WASTE' => [
        'cancel_order'     => 'Cancel Order',
        'kitchen_error'    => 'Kitchen Error',
        'overproduction'   => 'Overproduction',
        'spillage'         => 'Spillage / Tumpah',
        'prep_trim_excess' => 'Prep Trim Excess',
        'expired_opened'   => 'Expired Opened',
        'other'            => 'Other',
    ],
    'SPOIL' => [
        'expired'           => 'Expired',
        'temperature_abuse' => 'Temperature Abuse',
        'contamination'     => 'Contamination',
        'improper_storage'  => 'Improper Storage',
        'overstock'         => 'Overstock',
        'other'             => 'Other',
    ],
    'PROCESS_LOSS' => [
        'defrost_loss'                => 'Defrost Loss',
        'trimming_standard'           => 'Trimming Standard',
        'cooking_loss'                => 'Cooking Loss',
        'evaporation'                 => 'Evaporation',
        'brew_loss'                   => 'Brew Loss',
        'absorption_loss'             => 'Absorption Loss',
        'process_residue'             => 'Process Residue',
        'variable_process_consumable' => 'Variable Process Consumable',
        'other'                       => 'Other',
    ],
    'VARIANCE' => [
        'over_usage'        => 'Over Usage',
        'under_usage'       => 'Under Usage',
        'unrecorded_usage'  => 'Unrecorded Usage',
        'counting_error'    => 'Counting Error',
        'system_mismatch'   => 'System Mismatch',
        'theft_suspected'   => 'Theft Suspected',
        'unknown_shrinkage' => 'Unknown Shrinkage',
        'other'             => 'Other',
    ],
    'ADJUSTMENT_PLUS' => [
        'opening_correction' => 'Opening Correction',
        'stock_found'        => 'Stock Found',
        'manual_reclass'     => 'Manual Reclass',
        'other'              => 'Other',
    ],
];
?>

<style>
/* ── Filter card & grid ───────────────────────────────────── */
.opn-filter-card {
  border: 1px solid #bcd0e8;
  border-radius: 16px;
  box-shadow: 0 10px 22px rgba(30,68,128,.06);
}
.opn-filter-grid {
  display: grid;
  grid-template-columns: 148px minmax(110px,1fr) minmax(130px,1.2fr) minmax(160px,2fr) 44px auto;
  gap: .5rem;
  align-items: end;
}
.opn-filter-field { min-width: 0; }
.opn-filter-field label {
  display: block;
  margin-bottom: .28rem;
  font-size: .69rem;
  font-weight: 800;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: #2d5fa0;
}
.opn-filter-actions { display: grid; grid-template-columns: 1fr auto; gap: .4rem; }
@media (max-width:991.98px) {
  .opn-filter-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
  .opn-filter-actions { grid-column: span 2; }
}
@media (max-width:575.98px) {
  .opn-filter-grid { grid-template-columns: 1fr 1fr; }
  .opn-filter-actions { grid-column: span 2; }
}

/* ── KPI cards ────────────────────────────────────────────── */
.opn-kpi-row {
  display: grid;
  grid-template-columns: repeat(6, minmax(0,1fr));
  gap: .65rem;
}
@media (max-width:991.98px) { .opn-kpi-row { grid-template-columns: repeat(3,1fr); } }
@media (max-width:575.98px) { .opn-kpi-row { grid-template-columns: repeat(2,1fr); } }
.opn-kpi-card {
  border-radius: 18px;
  padding: .9rem 1rem;
  position: relative;
  overflow: hidden;
  color: #fff;
  min-height: 86px;
}
.opn-kpi-card::before,.opn-kpi-card::after {
  content: ''; position: absolute; border-radius: 50%; opacity: .13; background: #fff;
}
.opn-kpi-card::before { width: 88px; height: 88px; right: -22px; top: -22px; }
.opn-kpi-card::after  { width: 54px; height: 54px; right: 18px;  bottom: -16px; }
.opn-kpi-label { font-size: .67rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; opacity: .88; }
.opn-kpi-value { font-size: 1.55rem; font-weight: 900; line-height: 1.15; margin-top: .18rem; }
.opn-kpi-sub   { font-size: .7rem; opacity: .82; margin-top: .1rem; }
.opn-kpi-1 { background: linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%); }
.opn-kpi-2 { background: linear-gradient(135deg,#14532d 0%,#16a34a 100%); padding-right: 72px; }
.opn-kpi-3 { background: linear-gradient(135deg,#7c2d12 0%,#dc2626 100%); }
.opn-kpi-4 { background: linear-gradient(135deg,#312e81 0%,#6366f1 100%); }
.opn-kpi-5 { background: linear-gradient(135deg,#881337 0%,#f43f5e 100%); }
.opn-kpi-6 { background: linear-gradient(135deg,#134e4a 0%,#0d9488 100%); }

/* Completion ring — inside KPI card 2 */
.opn-ring-wrap {
  position: absolute; right: 8px; top: 50%;
  transform: translateY(-50%); width: 52px; height: 52px; z-index: 1;
}
.opn-ring-track { fill: none; stroke: rgba(255,255,255,.22); stroke-width: 5; }
.opn-ring-fill  {
  fill: none; stroke: rgba(255,255,255,.92); stroke-width: 5;
  stroke-linecap: round;
  stroke-dasharray: 138.23;
  stroke-dashoffset: 138.23;
  transform-origin: 26px 26px;
  transform: rotate(-90deg);
  transition: stroke-dashoffset .75s cubic-bezier(.4,0,.2,1);
}
.opn-ring-text {
  font-size: 8.5px; font-weight: 900;
  fill: #fff; text-anchor: middle; dominant-baseline: middle;
}

/* ── Division heatmap (surprise) ─────────────────────────── */
.opn-div-heatmap {
  border: 1px solid #bcd0e8;
  border-radius: 14px;
  padding: .6rem .9rem;
  background: linear-gradient(135deg,#f0f8ff 0%,#e8f0fe 100%);
  box-shadow: 0 6px 14px rgba(30,58,138,.055);
}
.opn-div-heatmap-title {
  font-size: .67rem; font-weight: 800; letter-spacing: .07em;
  text-transform: uppercase; color: #1e3a8a; margin-bottom: .42rem;
}
.opn-div-strip { display: flex; flex-wrap: wrap; gap: .38rem; }
.opn-div-badge {
  display: inline-flex; flex-direction: column; align-items: center;
  padding: .28rem .58rem; border-radius: 10px;
  font-size: .68rem; font-weight: 800; line-height: 1.22;
  cursor: pointer; border: none; color: #fff;
  transition: transform .14s, box-shadow .14s, opacity .14s;
}
.opn-div-badge:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,.18); }
.opn-div-badge .bdg-name { font-size: .6rem; opacity: .84; }
.opn-div-badge .bdg-stat { font-size: .86rem; font-weight: 900; }
.opn-div-badge-ok   { background: linear-gradient(135deg,#14532d,#16a34a); }
.opn-div-badge-part { background: linear-gradient(135deg,#78350f,#d97706); }
.opn-div-badge-miss { background: linear-gradient(135deg,#7c2d12,#dc2626); }
.opn-div-badge-none { background: linear-gradient(135deg,#374151,#6b7280); }

/* ── Table card ────────────────────────────────────────────── */
.opn-table-card {
  border: 1px solid #bcd0e8;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 12px 24px rgba(30,68,128,.07);
}
.opn-table-card-head {
  background: linear-gradient(135deg,#1e3a8a,#2563eb);
  padding: .65rem 1rem;
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: .5rem; color: #fff;
}
.opn-table-wrap { max-height: 68vh; overflow-y: auto; }
.opn-table-wrap table { border-collapse: collapse; }
.opn-table-wrap thead th {
  position: sticky; top: 0; z-index: 4;
  background: linear-gradient(135deg,#1e3a8a,#2563eb);
  color: #fff; font-size: .72rem; font-weight: 800;
  letter-spacing: .04em; text-transform: uppercase; white-space: nowrap;
  padding: .55rem .6rem;
  border-bottom: 2px solid #1d4ed8 !important; border-top: none !important;
}
#opnTbody tr            { border-bottom: 1px solid #e2e8f0; }
#opnTbody tr > td       { border: none; padding-top: 5px; padding-bottom: 5px; font-size: .84rem; }
#opnTbody tr:nth-child(even) > td { background: #f8fafc; }
#opnTbody tr:nth-child(odd)  > td { background: #fff; }
#opnTbody tr:hover > td      { background: #eff6ff !important; }
.opn-row-minus > td { background: #fff1f2 !important; }
.opn-row-minus > td:first-child { border-left: 3px solid #f87171; }
.opn-row-minus:hover > td { background: #fee2e2 !important; }
.opn-row-minus .fw-semibold,.opn-row-minus .fw-bold { color: #991b1b !important; }
.opn-row-minus .text-muted { color: #b91c1c !important; opacity: .75; }
.opn-grp-arrow { transition: transform .18s, color .18s; }
tr.opn-grp-header.expanded .opn-grp-arrow { transform: rotate(90deg); color: #3b82f6 !important; }
.opn-arrow-wrap { display: inline-flex; width: 16px; flex-shrink: 0; align-items: center; }
.opn-name-cell { display: flex; align-items: flex-start; }
.opn-name-body { min-width: 0; }
.opn-stock-in-badge {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    margin-top: .18rem;
    padding: .12rem .42rem;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: .68rem;
    font-weight: 700;
}
.opn-tipe-event   { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.opn-tipe-reguler { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.opn-kat-cell { font-size:.72rem; color:#374151; white-space:nowrap; }
.opn-mat-link { color:#1e40af; text-decoration:none; font-weight:600; }
.opn-mat-link:hover { text-decoration:underline; color:#1d4ed8; }
.opn-div-cell { font-size:.72rem; color:#1e40af; font-weight:600; white-space:nowrap; max-width:110px; vertical-align:middle; }
#btnFilterMinus.filter-on { background:#dc3545; border-color:#dc3545; color:#fff; }
#btnFilterConfirm.filter-on { background:#2563eb; border-color:#2563eb; color:#fff; }
</style>

<!-- Page header -->
<div class="fin-page-header">
  <div>
    <p class="fin-breadcrumb">
      <a href="<?= base_url('dashboard') ?>">Dashboard</a> / Bahan Baku Divisi / Daily Recon
    </p>
    <h4 class="fin-page-title">
      <i class="ri ri-check-double-line me-1 text-primary"></i>Daily Recon
    </h4>
    <p class="fin-page-subtitle">Input stok fisik harian &middot; selisih vs sistem &middot; posting adjustment langsung</p>
  </div>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'daily_recon']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => substr($adjustDate, 0, 7), 'division_id' => (string)$divisionId, 'destination_type' => $destination],
]); ?>

<div class="alert alert-light border mb-2 py-2 small">
  <strong>Daily Recon dipakai saat stok fisik sudah dihitung.</strong>
  Isi jumlah stok fisik terakhir, bukan selisihnya. Sistem menghitung selisih terhadap stok sistem lalu menyamakan lot FIFO pada saat posting.
  Untuk waste, spoil, atau perubahan tertentu tanpa hitung fisik menyeluruh, gunakan <a href="<?= site_url('inventory/stock/adjustment/division') ?>">Adjustment Bahan Baku Divisi</a>.
</div>

<?php if ($profileKey !== ''): ?>
<div class="alert alert-warning border mb-2 py-2 small">
  <strong>Recon profil dari Defisit Stok aktif.</strong> Daftar di bawah dibatasi ke profil yang dipilih agar hitung fisik dan koreksinya tidak mengenai profil NORI/barang lain yang namanya serupa.
  <a class="alert-link ms-1" href="<?= $baseUrl ?>">Lepaskan filter profil</a>.
</div>
<?php endif; ?>

<div id="alert-area" class="mb-2"></div>

<!-- Filter -->
<div class="card opn-filter-card mb-2">
  <div class="card-body py-3">
    <form method="get" action="<?= $baseUrl ?>" class="opn-filter-grid" id="opnForm">
      <input type="hidden" name="profile_key" value="<?= html_escape($profileKey) ?>">
      <div class="opn-filter-field">
        <label>Tanggal</label>
        <input type="date" name="opname_date" class="form-control"
               value="<?= html_escape($adjustDate) ?>">
      </div>
      <div class="opn-filter-field">
        <label>Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua</option>
          <?php foreach ($divisions as $div): ?>
          <option value="<?= (int)($div['id'] ?? 0) ?>"
                  <?= (int)($div['id'] ?? 0) === $divisionId ? 'selected' : '' ?>>
            <?= html_escape((string)($div['name'] ?? $div['division_name'] ?? '')) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="opn-filter-field">
        <label>Tujuan</label>
        <select name="destination" class="form-select">
          <?php foreach ($destOptions as $val => $lbl): ?>
          <option value="<?= html_escape($val) ?>" <?= $val === $destination ? 'selected' : '' ?>>
            <?= html_escape($lbl) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="opn-filter-field">
        <label>Cari Bahan</label>
        <input type="text" name="q" id="opnQ" class="form-control"
               placeholder="nama bahan, merek..."
               value="<?= html_escape($q) ?>">
      </div>
      <div class="opn-filter-field d-flex align-items-end">
        <button type="button" class="btn btn-outline-secondary w-100" id="opnRefresh" title="Refresh">
          <i class="ri ri-refresh-line"></i>
        </button>
      </div>
      <div class="opn-filter-actions">
        <button type="button" class="btn btn-primary" id="opnLoad">
          <i class="ri ri-search-line me-1"></i>Muat Data
        </button>
        <a href="<?= $baseUrl ?>" class="btn btn-outline-danger" title="Reset filter">
          <i class="ri ri-close-line"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- KPI cards (shown after data loads) -->
<div id="opnKpiRow" class="opn-kpi-row mb-2" style="display:none">
  <div class="opn-kpi-card opn-kpi-1">
    <div class="opn-kpi-label">Total Profil</div>
    <div class="opn-kpi-value" id="smTotal">0</div>
    <div class="opn-kpi-sub">item &times; profil</div>
  </div>
  <div class="opn-kpi-card opn-kpi-2">
    <div class="opn-kpi-label">Sudah Diisi</div>
    <div class="opn-kpi-value" id="smFilled">0</div>
    <div class="opn-kpi-sub" id="smFilledPct">0% selesai</div>
    <!-- Surprise: animated completion ring -->
    <div class="opn-ring-wrap">
      <svg viewBox="0 0 52 52" width="52" height="52">
        <circle class="opn-ring-track" cx="26" cy="26" r="22"/>
        <circle class="opn-ring-fill" id="opnRingFill" cx="26" cy="26" r="22"/>
        <text class="opn-ring-text" id="opnRingText" x="26" y="26">0%</text>
      </svg>
    </div>
  </div>
  <div class="opn-kpi-card opn-kpi-3">
    <div class="opn-kpi-label">Ada Selisih</div>
    <div class="opn-kpi-value" id="smMiss">0</div>
    <div class="opn-kpi-sub">profil butuh adj</div>
  </div>
  <div class="opn-kpi-card opn-kpi-4">
    <div class="opn-kpi-label">Disesuaikan</div>
    <div class="opn-kpi-value" id="smAdj">0</div>
    <div class="opn-kpi-sub">sudah di-post</div>
  </div>
  <div class="opn-kpi-card opn-kpi-5">
    <div class="opn-kpi-label">Stok Minus</div>
    <div class="opn-kpi-value" id="smMinus">0</div>
    <div class="opn-kpi-sub">profil negatif</div>
  </div>
  <div class="opn-kpi-card opn-kpi-6">
    <div class="opn-kpi-label">Nilai Stok Sistem</div>
    <div class="opn-kpi-value" style="font-size:1.08rem" id="smNilai">&mdash;</div>
    <div class="opn-kpi-sub">estimasi HPP</div>
  </div>
</div>

<!-- Surprise: Division completion heatmap strip -->
<div id="opnDivHeatmap" class="opn-div-heatmap mb-2" style="display:none">
  <div class="opn-div-heatmap-title">
    <i class="ri ri-map-pin-line me-1"></i>Status Opname per Divisi &mdash; klik untuk filter
  </div>
  <div id="opnDivStrip" class="opn-div-strip"></div>
</div>

<!-- Table card -->
<div class="opn-table-card">
  <div id="opnLoading" class="text-center py-5 text-muted d-none">
    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memuat data stok divisi...
  </div>
  <div id="opnEmpty" class="text-center py-5 text-muted">
    <i class="ri ri-scales-3-line ri-3x d-block mb-2 opacity-25"></i>
    Klik <strong>Muat Data</strong> untuk memulai koreksi stok.
  </div>
  <div id="opnNoResult" class="text-center py-5 text-muted d-none">
    <i class="ri ri-inbox-line ri-3x d-block mb-2 opacity-25"></i>
    Tidak ada bahan baku ditemukan untuk filter ini.
  </div>

  <div id="opnTableWrap" style="display:none">
    <div class="opn-table-card-head">
      <div class="d-flex align-items-center gap-2">
        <small id="opnInfo" class="fw-semibold" style="color:rgba(255,255,255,.92)"></small>
        <span id="opnMinusBadge" class="badge bg-danger" style="display:none;font-size:.68rem;cursor:pointer"></span>
      </div>
      <div class="d-flex gap-2">
        <?php if ($canCreate): ?>
          <button class="btn btn-sm btn-warning" id="opnConfirmOpen" type="button" style="font-size:.74rem">
            <i class="ri ri-door-open-line me-1"></i>Konfirmasi Buka
          </button>
          <button class="btn btn-sm btn-success" id="opnConfirmClose" type="button" style="font-size:.74rem">
            <i class="ri ri-door-closed-line me-1"></i>Konfirmasi Tutup
          </button>
        <?php endif; ?>
        <button class="btn btn-sm btn-light" id="btnFilterConfirm" style="display:none;font-size:.74rem">
          <i class="ri ri-checkbox-circle-line me-1"></i>Perlu Konfirmasi
          <span id="opnConfirmBadge" class="badge bg-primary ms-1" style="display:none">0</span>
        </button>
        <button class="btn btn-sm btn-light" id="btnFilterMinus" style="display:none;font-size:.74rem">
          <i class="ri ri-arrow-down-circle-line me-1"></i>Hanya Minus
        </button>
      </div>
    </div>
    <div class="opn-table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:108px">Divisi / Tipe</th>
            <th style="width:86px">Kategori</th>
            <th class="text-start" style="min-width:190px">Bahan Baku / Profil</th>
            <th style="width:60px">UOM</th>
            <th class="text-end" style="width:96px">Sistem</th>
            <th class="text-end" style="width:100px">Fisik</th>
            <th class="text-end" style="width:82px">Selisih</th>
            <th style="min-width:220px">Jenis &amp; Alasan</th>
            <th style="width:90px">Aksi</th>
          </tr>
        </thead>
        <tbody id="opnTbody"></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="opnProfileMergeModal" tabindex="-1" aria-labelledby="opnProfileMergeTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#1f2937,#374151)">
        <h6 class="modal-title text-white mb-0" id="opnProfileMergeTitle">Join Profile</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small mb-3">
          Pilih 1 profil target, lalu centang profil child yang akan dipindah ke target tersebut. Sistem akan menggabungkan movement, monthly stock, dan FIFO lot ke profil target.
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="text-center" style="width:80px">Target</th>
                <th class="text-center" style="width:90px">Gabung</th>
                <th>Profil</th>
                <th class="text-end">Stok</th>
              </tr>
            </thead>
            <tbody id="opnProfileMergeRows"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-dark" id="opnProfileMergeSubmit">Join Profile</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {

const DATA_URL   = '<?= $dataUrl ?>';
const ADJ_URL    = '<?= $quickAdjUrl ?>';
const CONFIRM_RECON_URL = '<?= $confirmReconUrl ?>';
const PROFILE_MERGE_URL = '<?= $profileMergeUrl ?>';
const MATERIAL_USAGE_BASE = '<?= site_url('master/material/usage/') ?>';
const CAN_CREATE = <?= $canCreate ? 'true' : 'false' ?>;

const REASONS = <?= json_encode($REASONS) ?>;
const ADJ_TYPES_NEG = [
    { val: 'WASTE',         lbl: 'Waste'            },
    { val: 'SPOIL',         lbl: 'Spoil'            },
    { val: 'PROCESS_LOSS',  lbl: 'Process Loss'     },
    { val: 'VARIANCE',      lbl: 'Variance / Minus' },
];
const ADJ_TYPES_POS = [
    { val: 'ADJUSTMENT_PLUS', lbl: 'Adjustment Plus' },
];

let currentData   = [];
let divFilterId   = null;
let showOnlyMinus = false;
let showOnlyConfirm = false;
let reconConfirmMode = 'BULK_ALLOWED';
const profileMap  = {};
const materialGroupMap = {};

const el    = id => document.getElementById(id);
const esc   = s  => { const d = document.createElement('div'); d.textContent = String(s || ''); return d.innerHTML; };
const fmt4  = v  => v == null ? '—' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
const fmtRp = v  => 'Rp ' + Number(v || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
function cssid(s) { return String(s || '').replace(/[^a-zA-Z0-9_-]/g, '_'); }
function buildProfileToken(v) { return String(v || '').trim() === '' ? '__EMPTY_PROFILE__' : String(v || ''); }
function round4(v) { return Math.round((Number(v || 0) + Number.EPSILON) * 10000) / 10000; }
function fmtDateOnly(s) {
    s = String(s || '').trim();
    if (!s || s === '0000-00-00' || s === '0000-00-00 00:00:00') return '';
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? (m[3] + '/' + m[2] + '/' + m[1]) : s;
}
function stockInSourceLabel(s) {
    s = String(s || '').toLowerCase();
    var labels = [];
    if (s.indexOf('pur_purchase_receipt') >= 0) labels.push('PO');
    if (s.indexOf('store_request') >= 0 || s.indexOf('fulfillment') >= 0) labels.push('SR');
    if (s.indexOf('opening') >= 0) labels.push('Stok awal');
    if (s.indexOf('adjustment') >= 0) labels.push('Adjustment');
    if (!labels.length && s.trim() !== '') labels.push('Lot');
    return Array.from(new Set(labels)).join('/');
}
function stockInLabel(p) {
    var first = fmtDateOnly(p.stock_in_first_date || '');
    var last  = fmtDateOnly(p.stock_in_last_date || '');
    if (!first && !last) return '';
    var source = stockInSourceLabel(p.stock_in_sources || '');
    var prefix = source ? ('Masuk ' + source + ': ') : 'Masuk: ';
    if (first && last && first !== last) return prefix + first + ' s/d ' + last;
    return prefix + (last || first);
}
function opnNeedsConfirm(row) {
    return !!(row && row.must_row_confirm && !row.confirmed_close);
}

function initTooltips(root) {
    (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (e) {
        try { bootstrap.Tooltip.getOrCreateInstance(e); } catch(ex) {}
    });
}

function showAlert(type, msg) {
    var host = el('alert-area');
    var alertTypeMap = {
        success: 'success',
        danger: 'danger',
        warning: 'warning',
        info: 'info'
    };
    if (host) {
        var cls = alertTypeMap[type] || 'info';
        host.innerHTML = ''
            + '<div class="alert alert-' + cls + ' alert-dismissible fade show shadow-sm mb-0" role="alert">'
            + msg
            + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            + '</div>';
        host.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        window.setTimeout(function () {
            var alertEl = host.querySelector('.alert');
            if (!alertEl) return;
            try {
                bootstrap.Alert.getOrCreateInstance(alertEl).close();
            } catch (ex) {
                host.innerHTML = '';
            }
        }, cls === 'danger' ? 8000 : 3500);
        return;
    }
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
        window.FinanceUI.alert(String(msg || ''), { title: 'Informasi' });
        return;
    }
    window.alert(String(msg || ''));
}

/* ── Surprise #1: animated completion ring ────────────────── */
function updateRing(filled, total) {
    const pct        = total > 0 ? Math.round(filled / total * 100) : 0;
    const circumference = 2 * Math.PI * 22; // 138.23
    const offset     = circumference * (1 - pct / 100);
    const ringFill   = el('opnRingFill');
    const ringText   = el('opnRingText');
    if (ringFill) ringFill.style.strokeDashoffset = offset;
    if (ringText) ringText.textContent = pct + '%';
    const pctEl = el('smFilledPct');
    if (pctEl) pctEl.textContent = pct + '% selesai';
}

/* ── Surprise #2: division completion heatmap ─────────────── */
function renderDivHeatmap(divisions) {
    const wrap  = el('opnDivHeatmap');
    const strip = el('opnDivStrip');
    if (!wrap || !strip || !divisions.length) { if (wrap) wrap.style.display = 'none'; return; }

    const divStats = [];
    divisions.forEach(function (div) {
        let total = 0, filled = 0, miss = 0;
        div.materials.forEach(function (mat) {
            mat.profiles.forEach(function (p) {
                total++;
                if (p.physical_qty_content !== null) {
                    filled++;
                    if (p.selisih !== null && Math.abs(p.selisih) >= 0.001) miss++;
                }
            });
        });
        divStats.push({ id: String(div.division_id), name: div.division_name, total: total, filled: filled, miss: miss });
    });

    strip.innerHTML = divStats.map(function (d) {
        const pct = d.total > 0 ? Math.round(d.filled / d.total * 100) : 0;
        let cls = 'opn-div-badge-none';
        if (d.miss > 0)       cls = 'opn-div-badge-miss';
        else if (pct === 100) cls = 'opn-div-badge-ok';
        else if (pct >= 50)   cls = 'opn-div-badge-part';
        return '<button class="opn-div-badge ' + cls + '" data-filter-div="' + d.id + '" title="Filter ' + esc(d.name) + '">'
            + '<span class="bdg-name">' + esc(d.name) + '</span>'
            + '<span class="bdg-stat">' + d.filled + '/' + d.total + '</span>'
            + '</button>';
    }).join('');

    wrap.style.display = '';

    strip.querySelectorAll('[data-filter-div]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const divId = btn.dataset.filterDiv || '';
            const allBtns = strip.querySelectorAll('[data-filter-div]');
            if (divFilterId === divId) {
                divFilterId = null;
                allBtns.forEach(function (b) { b.style.opacity = ''; });
                restoreAllRows();
            } else {
                divFilterId = divId;
                allBtns.forEach(function (b) { b.style.opacity = b.dataset.filterDiv === divId ? '1' : '.4'; });
                filterByDiv(divId);
            }
        });
    });
}

function filterByDiv(divId) {
    const tbody = el('opnTbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(function (row) {
        row.style.display = String(row.dataset.divId || '') === divId ? '' : 'none';
    });
}
function restoreAllRows() {
    const tbody = el('opnTbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(function (row) {
        if (row.dataset.grp) {
            const icon = el('grp-icon-' + row.dataset.grp);
            row.style.display = (icon && icon.classList.contains('ri-arrow-down-s-line')) ? '' : 'none';
        } else {
            row.style.display = '';
        }
    });
}

/* ── Summary / KPI update ─────────────────────────────────── */
function updateSummary() {
    let total = 0, filled = 0, miss = 0, adj = 0, minus = 0, nilaiTotal = 0;
    currentData.forEach(function (div) {
        div.materials.forEach(function (mat) {
            mat.profiles.forEach(function (p) {
                total++;
                const qty  = parseFloat(p.system_qty_content) || 0;
                const cost = parseFloat(p.avg_cost_per_content || p.total_value_per_content || 0);
                if (qty < -0.001) minus++;
                nilaiTotal += (p.total_value != null ? parseFloat(p.total_value) : qty * cost);
                if (p.physical_qty_content !== null) {
                    filled++;
                    if (p.selisih !== null && Math.abs(p.selisih) >= 0.001) miss++;
                    if (p.adjustment_id) adj++;
                }
            });
        });
    });
    el('smTotal').textContent  = total;
    el('smFilled').textContent = filled;
    el('smMiss').textContent   = miss;
    el('smAdj').textContent    = adj;
    const smMinus = el('smMinus'); if (smMinus) smMinus.textContent = minus;
    const smNilai = el('smNilai'); if (smNilai) smNilai.textContent = 'Rp ' + nilaiTotal.toLocaleString('id-ID', { maximumFractionDigits: 0 });
    updateRing(filled, total);
    const kpiRow = el('opnKpiRow'); if (kpiRow) kpiRow.style.display = total > 0 ? '' : 'none';
}

/* ── Table cell helpers ───────────────────────────────────── */
function selisihHtml(sel, iid) {
    if (sel === null) return '<span id="sel-' + iid + '" class="text-muted small">—</span>';
    const v = Number(sel);
    if (Math.abs(v) < 0.001) return '<span id="sel-' + iid + '" class="text-success fw-bold">± 0</span>';
    const cls = v < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
    return '<span id="sel-' + iid + '" class="' + cls + '">' + (v > 0 ? '+' : '') + fmt4(v) + '</span>';
}

function adjColHtml(p, iid) {
    if (p.adjustment_id) {
        return '<td id="adjcol-' + iid + '" class="adj-col">'
            + '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">'
            + '<i class="ri ri-check-double-line me-1"></i>Adj #' + p.adjustment_id + '</span></td>';
    }
    const sel = p.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        const isNeg    = Number(sel) < 0;
        const types    = isNeg ? ADJ_TYPES_NEG : ADJ_TYPES_POS;
        const typeOpts = '<option value="">— pilih jenis —</option>'
            + types.map(function (t) { return '<option value="' + t.val + '">' + t.lbl + '</option>'; }).join('');
        return '<td id="adjcol-' + iid + '" class="adj-col py-2"><div class="d-flex flex-column gap-1">'
            + '<select id="adjtype-' + iid + '" class="form-select form-select-sm" onchange="opnTypeChange(\'' + iid + '\')">' + typeOpts + '</select>'
            + '<select id="adjreason-' + iid + '" class="form-select form-select-sm d-none"></select>'
            + '<input type="text" id="adjnotes-' + iid + '" class="form-control form-control-sm d-none" placeholder="Catatan (opsional)">'
            + '</div></td>';
    }
    return '<td id="adjcol-' + iid + '" class="adj-col"></td>';
}

function actionCell(p, iid) {
    if (!CAN_CREATE) return '<td class="action-cell"></td>';
    var reconHtml = reconRowButtons(p, iid);
    if (p.adjustment_id) return '<td class="action-cell">' + reconHtml + '</td>';
    const sel = p.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        return '<td class="action-cell"><div class="d-flex flex-column gap-1">'
            + '<button class="btn btn-sm btn-danger w-100" id="adjbtn-' + iid + '" onclick="opnPostAdj(\'' + iid + '\')">'
            + '<i class="ri ri-upload-2-line me-1"></i>Posting</button>'
            + reconHtml
            + '</div></td>';
    }
    if (sel !== null) {
        return '<td class="action-cell text-center"><div class="d-flex flex-column gap-1 align-items-stretch">'
            + '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">'
            + '<i class="ri ri-check-line me-1"></i>Match</span>'
            + reconHtml
            + '</div></td>';
    }
    return '<td class="action-cell">' + reconHtml + '</td>';
}

function lotDetailRows(p, parentIid, grpIid) {
    var lots = Array.isArray(p && p.lots) ? p.lots : [];
    if (lots.length <= 1) return '';
    return lots.map(function (lot) {
        var lotIid = 'lot_' + parentIid + '_' + cssid(lot.lot_id || lot.lot_no || '');
        var qty = Number(lot.qty_balance || 0);
        var cost = Number(lot.unit_cost || 0);
        var val = Number(lot.total_value || (qty * cost));
        var source = stockInSourceLabel(lot.source_table || '') || 'Lot';
        var grpAttr = grpIid ? ' data-grp="' + grpIid + '"' : '';
        return '<tr id="row-' + lotIid + '" class="opn-lot-child text-muted"'
            + grpAttr
            + ' data-system-val="' + qty + '"'
            + ' data-div-id="' + esc(String(p.division_id || '')) + '"'
            + ' data-confirm-required="0">'
            + '<td class="opn-div-cell" style="padding-left:18px;border-left:3px solid #e2e8f0">'
            + '<div style="font-size:.7rem;font-weight:700">Lot</div>'
            + '<div style="font-size:.66rem">' + esc(source) + '</div>'
            + '</td>'
            + '<td></td>'
            + '<td class="text-start">'
            + '<div class="fw-semibold" style="font-size:.76rem">' + esc(lot.lot_no || '-') + '</div>'
            + '<div class="text-muted" style="font-size:.68rem">Masuk: ' + esc(fmtDateOnly(lot.receipt_date || '') || '-') + '</div>'
            + '</td>'
            + '<td class="text-muted" style="font-size:.8rem">' + esc(p.profile_content_uom_code || '-') + '</td>'
            + '<td class="text-end" style="font-size:.82rem">' + fmt4(qty) + '</td>'
            + '<td></td>'
            + '<td class="text-end text-muted small">Rp ' + val.toLocaleString('id-ID', { maximumFractionDigits: 0 }) + '</td>'
            + '<td></td><td></td>'
            + '</tr>';
    }).join('');
}

function reconRowButtons(p, iid) {
    if (!p || !p.must_row_confirm) return '';
    var reason = p.must_row_confirm_reason ? '<div class="text-muted" style="font-size:.62rem;line-height:1.1">' + esc(p.must_row_confirm_reason) + '</div>' : '';
    var closeBtn = p.confirmed_close
        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Tutup OK</span>'
        : '<button type="button" class="btn btn-sm btn-outline-success py-0" onclick="event.stopPropagation();opnConfirmReconRow(\'' + iid + '\',\'CLOSE\')">Tutup</button>';
    return '<div class="opn-row-confirm d-flex flex-column gap-1">' + reason
        + '<div class="d-flex gap-1 justify-content-center">' + closeBtn + '</div></div>';
}

function requiredReconPending(stage) {
    var pending = [];
    currentData.forEach(function (div) {
        (div.materials || []).forEach(function (mat) {
            (mat.profiles || []).forEach(function (p) {
                if (!p.must_row_confirm) return;
                var ok = stage === 'OPEN' ? !!p.confirmed_open : !!p.confirmed_close;
                if (ok) return;
                pending.push({
                    name: p.material_name || p.item_name || p.profile_name || p.recon_line_key || 'Bahan baku',
                    reason: p.must_row_confirm_reason || 'wajib recon per baris'
                });
            });
        });
    });
    return pending;
}

function showRequiredReconWarning(stage, pending) {
    var label = stage === 'OPEN' ? 'buka kasir' : 'tutup kasir';
    var rows = pending.slice(0, 12).map(function (item) {
        return '<li><strong>' + esc(item.name) + '</strong> <span class="text-muted">(' + esc(item.reason) + ')</span></li>';
    }).join('');
    var more = pending.length > 12 ? '<li>+' + (pending.length - 12) + ' item lainnya</li>' : '';
    showAlert('warning',
        'Konfirmasi semua untuk ' + label + ' belum bisa disimpan. Baris berikut wajib dicek satu per satu dulu:<ul class="mb-0 mt-2">'
        + rows + more + '</ul>'
    );
}

window.opnConfirmReconRow = function (iid, stage) {
    var p = profileMap[iid];
    var form = el('opnForm');
    if (!p || !form) return;
    var date = form.querySelector('[name=opname_date]')?.value || '';
    var formDivisionId = Number(form.querySelector('[name=division_id]')?.value || 0);
    var divisionId = formDivisionId > 0 ? formDivisionId : Number(p.division_id || 0);
    if (divisionId <= 0) {
        showAlert('warning', 'Pilih satu divisi dulu sebelum konfirmasi baris recon.');
        return;
    }
    fetch(CONFIRM_RECON_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            scope: 'ROW',
            opname_date: date,
            division_id: divisionId,
            stage: stage,
            line_key: p.recon_line_key || '',
            line_label: p.material_name || p.item_name || p.profile_name || '',
            item_id: p.item_id || 0,
            material_id: p.material_id || 0,
            profile_key: p.profile_key || '',
            required_reason: p.must_row_confirm_reason || '',
            notes: 'Konfirmasi baris dari daily recon bahan baku'
        })
    })
    .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
    .then(function (res) {
        if (res.status >= 400 || !res.json.ok) {
            showAlert('danger', res.json.message || 'Konfirmasi baris gagal.');
            return;
        }
        if (stage === 'OPEN') p.confirmed_open = true;
        if (stage === 'CLOSE') p.confirmed_close = true;
        renderTable(currentData);
        showAlert('success', res.json.message || 'Baris recon berhasil dikonfirmasi.');
    })
    .catch(function (e) { showAlert('danger', 'Konfirmasi baris gagal: ' + e.message); });
};

/* ── Render table ─────────────────────────────────────────── */
function renderTable(divisions) {
    const tbody = el('opnTbody');
    Object.keys(profileMap).forEach(function (key) { delete profileMap[key]; });
    Object.keys(materialGroupMap).forEach(function (key) { delete materialGroupMap[key]; });
    if (!divisions.length) {
        el('opnTableWrap').style.display   = 'none';
        el('opnNoResult').classList.remove('d-none');
        el('opnKpiRow').style.display      = 'none';
        el('opnDivHeatmap').style.display  = 'none';
        return;
    }
    el('opnNoResult').classList.add('d-none');
    el('opnTableWrap').style.display = '';

    let totalMat = 0, totalProf = 0;
    divisions.forEach(function (d) {
        totalMat += d.materials.length;
        d.materials.forEach(function (m) { totalProf += m.profiles.length; });
    });
    el('opnInfo').textContent = divisions.length + ' divisi · ' + totalMat + ' material · ' + totalProf + ' profil';

    function destInfo(destType) {
        var map = {
            'BAR':           { kat: 'Bar',     event: false },
            'KITCHEN':       { kat: 'Kitchen', event: false },
            'BAR_EVENT':     { kat: 'Bar',     event: true  },
            'KITCHEN_EVENT': { kat: 'Kitchen', event: true  },
            'OFFICE':        { kat: 'Office',  event: false },
            'OTHER':         { kat: 'Other',   event: false },
        };
        return map[destType] || { kat: destType || '—', event: false };
    }
    function tipeBadge(destType) {
        return destInfo(destType).event
            ? '<span class="opn-tipe-event">Event</span>'
            : '<span class="opn-tipe-reguler">Reguler</span>';
    }
    function katCell(categoryName) { return '<td class="opn-kat-cell">' + esc(categoryName || '—') + '</td>'; }

    function buildLabelSingle(p, contentUom) {
        var matId   = Number(p.material_id || 0);
        var matName = esc(p.material_name || p.item_name);
        var nameStr = matId > 0
            ? '<a href="' + MATERIAL_USAGE_BASE + matId + '" class="opn-mat-link" target="_blank">' + matName + '</a>'
            : matName;
        var inner   = '<div class="fw-semibold">' + nameStr + '</div>';
        var subParts = [p.profile_name, p.profile_brand, p.profile_description].filter(Boolean);
        var expBadge = p.profile_expired_date
            ? ' <span class="badge bg-danger-subtle text-danger" style="font-size:.62rem">exp ' + esc(p.profile_expired_date) + '</span>' : '';
        if (subParts.length) {
            inner += '<div class="text-muted" style="font-size:.76rem">' + esc(subParts.join(' · ')) + expBadge + '</div>';
        } else if (expBadge) {
            inner += '<div>' + expBadge + '</div>';
        }
        if (p.avg_cost_per_content > 0) {
            inner += '<div class="text-muted" style="font-size:.72rem">' + fmtRp(p.avg_cost_per_content) + '/' + contentUom + '</div>';
        }
        if (p.cost_reference_source) {
            inner += '<div class="text-muted" style="font-size:.68rem">Referensi biaya: ' + esc(p.cost_reference_source) + '</div>';
        }
        if (p.is_deficit_virtual) {
            var deficitQty = Number(p.deficit_qty_remaining || 0);
            inner += '<div><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:.6rem">Profil defisit | stok sistem 0'
                + (deficitQty > 0 ? ' | sisa ' + fmt4(deficitQty) + ' ' + esc(contentUom) : '')
                + '</span></div>';
        }
        var masukAt = stockInLabel(p);
        if (masukAt) {
            inner += '<div><span class="opn-stock-in-badge">' + esc(masukAt) + '</span></div>';
        }
        if (p.is_recipe_only) {
            inner += '<div><span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.6rem">dari resep</span></div>';
        }
        return '<div class="opn-name-cell"><span class="opn-arrow-wrap"></span><div class="opn-name-body">' + inner + '</div></div>';
    }

    function buildLabelSub(p) {
        var expBadge = p.profile_expired_date
            ? ' <span class="badge bg-danger-subtle text-danger" style="font-size:.62rem">exp ' + esc(p.profile_expired_date) + '</span>' : '';
        var subParts = [p.profile_brand, p.profile_description].filter(Boolean);
        var inner = '<span class="fw-semibold" style="font-size:.82rem">' + esc(p.profile_name || '') + expBadge + '</span>';
        if (subParts.length) inner += ' <span class="text-muted" style="font-size:.74rem">· ' + esc(subParts.join(' · ')) + '</span>';
        if (p.avg_cost_per_content > 0) inner += ' <span class="text-muted" style="font-size:.72rem">· ' + fmtRp(p.avg_cost_per_content) + '</span>';
        if (p.cost_reference_source) {
            inner += '<div class="text-muted" style="font-size:.67rem">Referensi biaya: ' + esc(p.cost_reference_source) + '</div>';
        }
        if (p.is_deficit_virtual) {
            var deficitQty = Number(p.deficit_qty_remaining || 0);
            inner += '<div><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:.6rem">Profil defisit | stok sistem 0'
                + (deficitQty > 0 ? ' | sisa ' + fmt4(deficitQty) : '')
                + '</span></div>';
        }
        var masukAt = stockInLabel(p);
        if (masukAt) inner += '<div><span class="opn-stock-in-badge">' + esc(masukAt) + '</span></div>';
        return '<div class="opn-name-cell"><span class="opn-arrow-wrap"></span><div class="opn-name-body" style="padding-left:.6rem">' + inner + '</div></div>';
    }

    var html = '';
    var minusCount = 0;
    var confirmNeedCount = 0;

    divisions.forEach(function (div) {
        div.materials.forEach(function (mat) {
            var multiProf = mat.profiles.length > 1;
            var groupDest = mat.destination_type || ((mat.profiles[0] && mat.profiles[0].destination_type) ? mat.profiles[0].destination_type : 'ALL');
            var grpIid   = multiProf ? cssid(div.division_id + '_grp_' + groupDest + '_' + mat.material_id + '_' + mat.item_id) : null;

            if (multiProf) {
                var sysTotal  = mat.system_total;
                var physTotal = mat.physical_total;
                var selTotal  = physTotal !== null ? physTotal - sysTotal : null;
                var grpNeg    = mat.profiles.some(function (profileRow) {
                    return parseFloat(profileRow.system_qty_content || 0) < -0.001;
                });
                var grpNeedConfirm = mat.profiles.some(function (profileRow) {
                    return opnNeedsConfirm(profileRow);
                });
                if (grpNeg) minusCount++;
                materialGroupMap[grpIid] = {
                    group_id: grpIid,
                    division_id: div.division_id,
                    division_name: div.division_name,
                    material_id: mat.material_id,
                    material_name: mat.material_name,
                    destination: (mat.profiles[0] && mat.profiles[0].destination_type) ? mat.profiles[0].destination_type : 'ALL',
                    profiles: mat.profiles.map(function (profileRow) {
                        return {
                            profile_key: profileRow.profile_key || '',
                            profile_name: profileRow.profile_name || '',
                            stock_balance: Number(profileRow.system_qty_content || 0),
                            movement_balance: Number(profileRow.system_qty_content || 0),
                            lot_balance: Number(profileRow.system_qty_content || 0)
                        };
                    })
                };
                var joinBtn = CAN_CREATE
                    ? '<button type="button" class="btn btn-sm btn-outline-dark" onclick="event.stopPropagation();openOpmProfileMerge(\'' + grpIid + '\')"><i class="ri ri-links-line me-1"></i>Join</button>'
                    : '';
                html += '<tr id="grp-row-' + grpIid + '" class="opn-grp-header expanded' + (grpNeg ? ' opn-row-minus' : '') + '"'
                    + ' data-system-val="' + sysTotal + '" data-div-id="' + esc(String(div.division_id)) + '"'
                    + ' data-confirm-required="' + (grpNeedConfirm ? '1' : '0') + '"'
                    + ' onclick="opnToggleGrp(\'' + grpIid + '\')">'
                    + '<td class="opn-div-cell">' + esc(div.division_name)
                    + (mat.profiles.length ? '<div style="margin-top:2px">' + tipeBadge(mat.profiles[0].destination_type) + '</div>' : '')
                    + '</td>'
                    + '<td class="opn-kat-cell" style="font-size:.8rem">' + esc(mat.category_name || '—') + '</td>'
                    + '<td class="text-start" style="font-size:.8rem"><div class="opn-name-cell">'
                    + '<span class="opn-arrow-wrap"><i id="grp-icon-' + grpIid + '" class="ri ri-arrow-down-s-line opn-grp-arrow text-muted" style="font-size:1.05rem"></i></span>'
                    + '<div class="opn-name-body"><span class="fw-semibold">'
                    + (mat.material_id > 0 ? '<a href="' + MATERIAL_USAGE_BASE + mat.material_id + '" class="opn-mat-link" target="_blank">' + esc(mat.material_name) + '</a>' : esc(mat.material_name))
                    + '</span>'
                    + '<span class="text-muted fw-normal ms-1" style="font-size:.74rem">(' + mat.profiles.length + ' profil)</span>'
                    + '</div></div></td>'
                    + '<td></td>'
                    + '<td class="text-end">' + fmt4(sysTotal) + '</td>'
                    + '<td></td>'
                    + '<td class="text-end">' + selisihHtml(selTotal, grpIid) + '</td>'
                    + '<td></td><td class="text-center">' + joinBtn + '</td></tr>';
            }

            mat.profiles.forEach(function (p) {
                var iid      = cssid(p.recon_line_key || (p.division_id + '_' + p.destination_type + '_' + p.identity_key));
                profileMap[iid] = p;
                var physVal  = p.physical_qty_content !== null ? p.physical_qty_content : '';
                var contentUom = esc(p.profile_content_uom_code || '—');
                var buyUom     = esc(p.profile_buy_uom_code || '');
                var cpb        = parseFloat(p.profile_content_per_buy) || 0;
                var showBuy    = buyUom && buyUom !== contentUom;

                var uomCell = showBuy
                    ? '<td style="font-size:.8rem">' + contentUom + '<div class="text-muted" style="font-size:.72rem">' + buyUom + '</div></td>'
                    : '<td class="text-muted" style="font-size:.8rem">' + contentUom + '</td>';

                var sysBuy = p.system_qty_buy > 0
                    ? p.system_qty_buy
                    : (cpb > 0 ? p.system_qty_content / cpb : null);
                var sistemCell = showBuy && sysBuy !== null
                    ? '<td class="text-end" style="font-size:.85rem">' + fmt4(p.system_qty_content) + '<div class="text-muted" style="font-size:.72rem">' + fmt4(sysBuy) + ' ' + buyUom + '</div></td>'
                    : '<td class="text-end">' + fmt4(p.system_qty_content) + '</td>';

                var physBuyInit = physVal !== '' && cpb > 0 && showBuy
                    ? fmt4(parseFloat(physVal) / cpb) + ' ' + buyUom : '';

                var matLabel  = !multiProf ? buildLabelSingle(p, contentUom) : buildLabelSub(p);
                var profNeg   = parseFloat(p.system_qty_content) < -0.001;
                if (profNeg && !multiProf) minusCount++;
                var profNeedConfirm = opnNeedsConfirm(p);
                if (profNeedConfirm) confirmNeedCount++;
                var profClass = profNeg ? 'opn-row-minus' : '';
                var profAttrs = multiProf
                    ? 'class="' + profClass + '" data-grp="' + grpIid + '" data-system-val="' + p.system_qty_content + '" data-div-id="' + esc(String(div.division_id)) + '" data-confirm-required="' + (profNeedConfirm ? '1' : '0') + '"'
                    : 'class="' + profClass + '" data-system-val="' + p.system_qty_content + '" data-div-id="' + esc(String(div.division_id)) + '" data-confirm-required="' + (profNeedConfirm ? '1' : '0') + '"';

                if (p.is_recipe_only) {
                    html += '<tr id="row-' + iid + '" ' + profAttrs + '>'
                        + '<td class="opn-div-cell">' + esc(div.division_name) + '<div style="margin-top:2px">' + tipeBadge(p.destination_type) + '</div></td>'
                        + katCell(p.category_name)
                        + '<td class="text-start">' + matLabel + '</td>'
                        + uomCell
                        + '<td class="text-end text-muted" style="font-size:.82rem">0</td>'
                        + '<td class="text-center"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:.65rem">Stok Kosong</span></td>'
                        + '<td></td><td></td><td></td>'
                        + '</tr>';
                    return;
                }

                html += '<tr id="row-' + iid + '" ' + profAttrs + '>'
                    + '<td class="opn-div-cell">' + esc(div.division_name) + '<div style="margin-top:2px">' + tipeBadge(p.destination_type) + '</div></td>'
                    + katCell(p.category_name)
                    + '<td class="text-start">' + matLabel + '</td>'
                    + uomCell + sistemCell
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm text-end" style="width:82px;display:inline-block"'
                    + ' step="0.01" min="0" value="' + esc(physVal) + '" placeholder="—"'
                    + ' data-iid="' + iid + '" data-sys="' + p.system_qty_content + '"'
                    + ' data-cpb="' + cpb + '" data-buyuom="' + buyUom + '"'
                    + ' oninput="opnLiveCalc(this,\'' + iid + '\')"'
                    + (!CAN_CREATE ? ' disabled' : '') + '>'
                    + '<div id="phys-buy-' + iid + '" class="text-muted text-end" style="font-size:.72rem;min-height:.9rem">' + physBuyInit + '</div>'
                    + '</td>'
                    + '<td class="text-end">' + selisihHtml(p.selisih, iid) + '</td>'
                    + adjColHtml(p, iid)
                    + actionCell(p, iid)
                    + '</tr>';
                html += lotDetailRows(p, iid, multiProf ? grpIid : null);
            });
        });
    });

    tbody.innerHTML = html;
    initTooltips(tbody);

    var minusBadge = el('opnMinusBadge');
    var filterBtn  = el('btnFilterMinus');
    var confirmBadge = el('opnConfirmBadge');
    var confirmBtn = el('btnFilterConfirm');
    if (minusBadge) { minusBadge.textContent = minusCount + ' stok minus'; minusBadge.style.display = minusCount > 0 ? '' : 'none'; }
    if (filterBtn)  filterBtn.style.display = minusCount > 0 ? '' : 'none';
    if (confirmBadge) { confirmBadge.textContent = confirmNeedCount; confirmBadge.style.display = confirmNeedCount > 0 ? '' : 'none'; }
    if (confirmBtn) confirmBtn.style.display = confirmNeedCount > 0 ? '' : 'none';

    renderDivHeatmap(divisions);
    applyReconFilters();
}

/* ── Live calc ────────────────────────────────────────────── */
window.opnLiveCalc = function (inp, iid) {
    var p    = profileMap[iid];
    var sys  = parseFloat(inp.dataset.sys) || 0;
    var phys = inp.value.trim() !== '' ? parseFloat(inp.value) : null;
    var selEl = el('sel-' + iid);
    if (!selEl) return;
    var buyEl  = el('phys-buy-' + iid);
    var cpb    = parseFloat(inp.dataset.cpb) || 0;
    var buyUom = inp.dataset.buyuom || '';
    if (phys === null) {
        selEl.textContent = '—'; selEl.className = 'text-muted small';
        if (buyEl) buyEl.textContent = '';
        if (p) { p.selisih = null; _liveUpdateAdjRow(iid, p); }
        return;
    }
    var v = phys - sys;
    if (p) p.physical_qty_content = phys;
    selEl.className   = Math.abs(v) < 0.001 ? 'text-success fw-bold' : (v < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold');
    selEl.textContent = Math.abs(v) < 0.001 ? '± 0' : (v > 0 ? '+' : '') + v.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    if (buyEl && cpb > 0 && buyUom) buyEl.textContent = fmt4(phys / cpb) + ' ' + buyUom;
    if (p) { p.selisih = Math.abs(v) >= 0.001 ? v : null; _liveUpdateAdjRow(iid, p); }
};

function _liveUpdateAdjRow(iid, p) {
    if (!p || p.adjustment_id) return;
    var existingType = el('adjtype-' + iid);
    if (existingType && existingType.value) return;
    var adjColEl = el('adjcol-' + iid);
    if (adjColEl) adjColEl.outerHTML = adjColHtml(p, iid);
    var row = el('row-' + iid);
    if (row) { var tdAction = row.querySelector('td.action-cell'); if (tdAction) tdAction.outerHTML = actionCell(p, iid); }
}

function confirmDailyRecon(stage) {
    var form = el('opnForm');
    if (!form) return;
    var date = form.querySelector('[name=opname_date]')?.value || '';
    var divisionId = Number(form.querySelector('[name=division_id]')?.value || 0);
    if (divisionId <= 0) {
        showAlert('warning', 'Pilih satu divisi dulu sebelum konfirmasi daily recon.');
        return;
    }
    var label = stage === 'OPEN' ? 'buka kasir' : 'tutup kasir';
    var pending = stage === 'CLOSE' ? requiredReconPending(stage) : [];
    if (pending.length > 0) {
        showRequiredReconWarning(stage, pending);
        return;
    }
    if (!confirm('Konfirmasi daily recon bahan baku untuk ' + label + ' pada tanggal ' + date + '?')) {
        return;
    }
    fetch(CONFIRM_RECON_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            opname_date: date,
            division_id: divisionId,
            stage: stage,
            notes: 'Konfirmasi dari halaman daily recon bahan baku'
        })
    })
    .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
    .then(function (res) {
        if (res.status >= 400 || !res.json.ok) {
            showAlert('danger', res.json.message || 'Konfirmasi daily recon gagal.');
            return;
        }
        showAlert('success', res.json.message || 'Daily recon bahan baku sudah dikonfirmasi.');
    })
    .catch(function (e) { showAlert('danger', 'Konfirmasi daily recon gagal: ' + e.message); });
}

/* ── Group toggle ─────────────────────────────────────────── */
window.opnToggleGrp = function (grpIid) {
    var children = document.querySelectorAll('[data-grp="' + grpIid + '"]');
    var icon     = el('grp-icon-' + grpIid);
    var hdrRow   = el('grp-row-' + grpIid);
    if (!children.length) return;
    var willExpand = children[0].style.display === 'none';
    children.forEach(function (row) {
        row.style.display = willExpand ? '' : 'none';
    });
    if (icon) {
        icon.className = 'ri opn-grp-arrow me-1 ' + (willExpand ? 'ri-arrow-down-s-line text-primary' : 'ri-arrow-right-s-line text-muted');
        icon.style.fontSize = '1.05rem';
    }
    if (hdrRow) hdrRow.classList.toggle('expanded', willExpand);
    if (showOnlyMinus || showOnlyConfirm) applyReconFilters();
};

/* ── Adj type change ──────────────────────────────────────── */
window.opnTypeChange = function (iid) {
    var typeSel   = el('adjtype-'   + iid);
    var reasonSel = el('adjreason-' + iid);
    var notesInp  = el('adjnotes-'  + iid);
    if (!typeSel || !reasonSel) return;
    if (!typeSel.value) {
        reasonSel.classList.add('d-none');
        if (notesInp) notesInp.classList.add('d-none');
        return;
    }
    var opts = REASONS[typeSel.value] || { other: 'Other' };
    reasonSel.innerHTML = Object.entries(opts).map(function (e) { return '<option value="' + e[0] + '">' + e[1] + '</option>'; }).join('');
    reasonSel.classList.remove('d-none');
    if (notesInp) notesInp.classList.remove('d-none');
};

/* ── Post adjustment ──────────────────────────────────────── */
window.opnPostAdj = function (iid) {
    var p = profileMap[iid];
    if (!p) return;
    // List filters such as EVENT/REGULER are not stock destinations.
    var rowDestination = String(p.destination_type || '').trim().toUpperCase();
    if (!rowDestination || ['ALL', 'EVENT', 'REGULER'].indexOf(rowDestination) !== -1) {
        showAlert('warning', 'Tujuan stok baris tidak lengkap. Muat ulang Daily Recon lalu pilih profil yang tepat.');
        return;
    }
    var row = el('row-' + iid);
    var qtyInput = row ? row.querySelector('input[type="number"]') : null;
    var physRaw = qtyInput ? qtyInput.value.trim() : '';
    if (physRaw === '') {
        showAlert('warning', 'Isi stok fisik terlebih dahulu sebelum posting.');
        if (qtyInput) qtyInput.focus();
        return;
    }
    p.physical_qty_content = parseFloat(physRaw);
    p.selisih = round4(p.physical_qty_content - Number(p.system_qty_content || 0));
    var typeEl  = el('adjtype-' + iid);
    var typeVal = typeEl ? typeEl.value : '';
    if (!typeVal) { showAlert('warning', 'Pilih jenis adjustment terlebih dahulu.'); if (typeEl) typeEl.focus(); return; }
    var btn = el('adjbtn-' + iid);
    if (!btn) return;
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    var form    = el('opnForm');
    var date    = form.querySelector('[name=opname_date]').value;
    var reasonEl = el('adjreason-' + iid);
    var notesEl  = el('adjnotes-' + iid);
    fetch(ADJ_URL, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(Object.assign({}, p, {
            opname_date:      date,
            destination_type: rowDestination,
            adjustment_type:  typeVal,
            reason_code:      (reasonEl && reasonEl.value) || 'other',
            notes:            (notesEl && notesEl.value && notesEl.value.trim()) || '',
            // A positive physical-count adjustment can close only a deficit
            // with this same profile. Other profiles stay open for review.
            settle_open_deficit: p.selisih > 0.0001 ? 1 : 0,
        })),
    })
    .then(function (r) {
        return r.text().then(function (text) {
            var json;
            try {
                json = JSON.parse(String(text || '').replace(/^\uFEFF/, '').trim());
            } catch (e) {
                var plain = String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                if (r.status === 401 || r.status === 403 || /login|masuk/i.test(plain)) {
                    throw new Error('Sesi Anda sudah berakhir. Muat ulang halaman lalu masuk kembali sebelum mengulangi recon.');
                }
                throw new Error(plain || ('Server tidak mengembalikan respons Daily Recon yang valid (HTTP ' + r.status + ').'));
            }
            return { s: r.status, j: json };
        });
    })
    .then(function (res) {
        btn.disabled = false; btn.innerHTML = orig;
        if (res.s >= 400 || !res.j.ok) { showAlert('danger', res.j.message || 'Terjadi kesalahan.'); return; }
        var adjId = res.j.data && res.j.data.adjustment_id;
        p.adjustment_id = adjId;
        var row = el('row-' + iid);
        if (row) {
            var tdAction = row.querySelector('td.action-cell'); if (tdAction) tdAction.outerHTML = actionCell(p, iid);
            var tdAdj = el('adjcol-' + iid); if (tdAdj) tdAdj.outerHTML = adjColHtml(p, iid);
            initTooltips(row);
        }
        updateSummary();
        showAlert('success', res.j.message || ('Adjustment #' + adjId + ' berhasil diposting.'));
        loadData(true);
    })
    .catch(function (e) { btn.disabled = false; btn.innerHTML = orig; showAlert('danger', 'Error: ' + e.message); });
};

var opnProfileMergeModalEl = el('opnProfileMergeModal');
var opnProfileMergeModal = opnProfileMergeModalEl && typeof bootstrap !== 'undefined'
    ? new bootstrap.Modal(opnProfileMergeModalEl)
    : null;

window.openOpmProfileMerge = function (groupId) {
    var group = materialGroupMap[groupId];
    if (!group || !Array.isArray(group.profiles) || group.profiles.length < 2) {
        showAlert('warning', 'Minimal harus ada 2 profil untuk digabung.');
        return;
    }
    if (!opnProfileMergeModalEl) {
        showAlert('danger', 'Modal Join Profile tidak tersedia.');
        return;
    }
    opnProfileMergeModalEl.dataset.groupId = groupId;
    var titleEl = el('opnProfileMergeTitle');
    if (titleEl) titleEl.textContent = 'Join Profile — ' + (group.material_name || 'Material');
    var rowsEl = el('opnProfileMergeRows');
    if (rowsEl) {
        rowsEl.innerHTML = group.profiles.map(function (profile, index) {
            var token = buildProfileToken(profile.profile_key || '');
            var pname = profile.profile_name || (token === '__EMPTY_PROFILE__' ? '(tanpa profil)' : token.substring(0, 12) + '...');
            return '<tr>'
                + '<td class="text-center"><input class="form-check-input" type="radio" name="opn_profile_target" value="' + esc(token) + '"' + (index === 0 ? ' checked' : '') + '></td>'
                + '<td class="text-center"><input class="form-check-input" type="checkbox" name="opn_profile_source" value="' + esc(token) + '"' + (index === 0 ? '' : ' checked') + '></td>'
                + '<td><div class="fw-semibold">' + esc(pname) + '</div><div class="text-muted small">' + esc(token === '__EMPTY_PROFILE__' ? '(tanpa profile_key)' : token) + '</div></td>'
                + '<td class="text-end">' + fmt4(profile.stock_balance || 0) + '</td>'
                + '</tr>';
        }).join('');
    }
    if (opnProfileMergeModal) opnProfileMergeModal.show();
    else if (typeof $ !== 'undefined') $(opnProfileMergeModalEl).modal('show');
}

var opnProfileMergeSubmit = el('opnProfileMergeSubmit');
if (opnProfileMergeSubmit) {
    opnProfileMergeSubmit.addEventListener('click', function () {
        var groupId = opnProfileMergeModalEl ? (opnProfileMergeModalEl.dataset.groupId || '') : '';
        var group = materialGroupMap[groupId];
        if (!group) {
            showAlert('warning', 'Data material tidak ditemukan. Muat ulang halaman ini.');
            return;
        }
        var targetEl = document.querySelector('input[name="opn_profile_target"]:checked');
        var sourceEls = Array.from(document.querySelectorAll('input[name="opn_profile_source"]:checked'));
        var targetToken = targetEl ? String(targetEl.value || '') : '';
        var sourceTokens = sourceEls.map(function (node) { return String(node.value || ''); }).filter(function (token) { return token !== targetToken; });
        if (!targetToken) {
            showAlert('warning', 'Pilih profil target terlebih dahulu.');
            return;
        }
        if (!sourceTokens.length) {
            showAlert('warning', 'Pilih minimal 1 profil child yang akan digabung.');
            return;
        }

        var orig = opnProfileMergeSubmit.innerHTML;
        opnProfileMergeSubmit.disabled = true;
        opnProfileMergeSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Join...';

        fetch(PROFILE_MERGE_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                division_id: Number(group.division_id || 0),
                material_id: Number(group.material_id || 0),
                destination: String(group.destination || 'ALL'),
                target_profile_key: targetToken,
                source_profile_keys: sourceTokens,
                as_of_date: document.querySelector('#opnForm [name="opname_date"]').value || ''
            })
        })
        .then(function (response) {
            return response.text().then(function (text) {
                try {
                    return { status: response.status, json: JSON.parse(text) };
                } catch (err) {
                    throw new Error('Response tidak valid: ' + text.substring(0, 220));
                }
            });
        })
        .then(function (result) {
            if (result.status >= 400 || !result.json || !result.json.ok) {
                throw new Error((result.json && result.json.message) || 'Join Profile gagal.');
            }
            if (opnProfileMergeModal) opnProfileMergeModal.hide();
            else if (typeof $ !== 'undefined') $(opnProfileMergeModalEl).modal('hide');
            showAlert('success', result.json.message || 'Join Profile berhasil.');
            loadData(true);
        })
        .catch(function (error) {
            showAlert('danger', error.message || 'Join Profile gagal.');
        })
        .finally(function () {
            opnProfileMergeSubmit.disabled = false;
            opnProfileMergeSubmit.innerHTML = orig;
        });
    });
}

/* ── Minus filter ─────────────────────────────────────────── */
function opnRowPassesFilters(row) {
    if (showOnlyMinus && !(parseFloat(row.dataset.systemVal || 0) < -0.001)) {
        return false;
    }
    if (showOnlyConfirm && row.dataset.confirmRequired !== '1') {
        return false;
    }
    return true;
}

function applyReconFilters() {
    var tbody = el('opnTbody');
    if (!tbody) return;
    var rows       = Array.from(tbody.querySelectorAll('tr'));
    var minusBtn   = el('btnFilterMinus');
    var confirmBtn = el('btnFilterConfirm');
    var badge      = el('opnMinusBadge');
    if (minusBtn) minusBtn.classList.toggle('filter-on', showOnlyMinus);
    if (confirmBtn) confirmBtn.classList.toggle('filter-on', showOnlyConfirm);
    if (badge) badge.style.fontWeight = showOnlyMinus ? '700' : '';

    if (!showOnlyMinus && !showOnlyConfirm) {
        rows.forEach(function (r) {
            if (r.classList.contains('opn-grp-header')) { r.style.display = ''; return; }
            if (r.dataset.grp) {
                var icon = el('grp-icon-' + r.dataset.grp);
                r.style.display = (icon && icon.classList.contains('ri-arrow-down-s-line')) ? '' : 'none';
                return;
            }
            r.style.display = '';
        });
        return;
    }

    var visibleGrps = new Set();
    rows.forEach(function (r) {
        if (r.classList.contains('opn-grp-header')) return;
        var pass = opnRowPassesFilters(r);
        r.style.display = pass ? '' : 'none';
        if (pass && r.dataset.grp) visibleGrps.add(r.dataset.grp);
    });

    rows.forEach(function (r) {
        if (!r.classList.contains('opn-grp-header')) return;
        var grpIid = r.id.replace('grp-row-', '');
        if (visibleGrps.has(grpIid)) {
            r.style.display = '';
            var icon = el('grp-icon-' + grpIid);
            if (icon) icon.className = 'ri opn-grp-arrow me-1 ri-arrow-down-s-line text-primary';
            r.classList.add('expanded');
        } else {
            r.style.display = opnRowPassesFilters(r) ? '' : 'none';
        }
    });
}

document.addEventListener('click', function (e) {
    if (e.target.closest('#btnFilterConfirm') || e.target.closest('#opnConfirmBadge')) {
        showOnlyConfirm = !showOnlyConfirm;
        applyReconFilters();
        return;
    }
    if (e.target.closest('#btnFilterMinus') || e.target.closest('#opnMinusBadge')) {
        showOnlyMinus = !showOnlyMinus;
        applyReconFilters();
    }
});

/* ── Load data ────────────────────────────────────────────── */
function loadData(showSpinner) {
    var form  = el('opnForm');
    var date  = form.querySelector('[name=opname_date]').value;
    var divId = form.querySelector('[name=division_id]').value;
    var dest  = form.querySelector('[name=destination]').value;
    var q     = form.querySelector('[name=q]').value;
    var profileKey = form.querySelector('[name=profile_key]')?.value || '';

    el('opnEmpty').classList.add('d-none');
    el('opnNoResult').classList.add('d-none');
    if (showSpinner !== false) {
        showOnlyMinus = false;
        showOnlyConfirm = false;
        var fb = el('btnFilterMinus'); if (fb) fb.classList.remove('filter-on');
        var cb = el('btnFilterConfirm'); if (cb) cb.classList.remove('filter-on');
        el('opnLoading').classList.remove('d-none');
        el('opnTableWrap').style.display  = 'none';
        el('opnKpiRow').style.display     = 'none';
        el('opnDivHeatmap').style.display = 'none';
    }

    fetch(DATA_URL
        + '?opname_date='   + encodeURIComponent(date)
        + '&division_id='   + encodeURIComponent(divId)
        + '&destination='   + encodeURIComponent(dest)
        + '&q='             + encodeURIComponent(q)
        + '&profile_key='   + encodeURIComponent(profileKey),
        { credentials: 'same-origin' })
    .then(function (r) {
        return r.text().then(function (text) {
            try {
                var parsed = JSON.parse(text);
                if (!r.ok && parsed && parsed.message) {
                    parsed.ok = false;
                }
                return parsed;
            } catch (err) {
                var clean = String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(clean
                    ? ('Response bukan JSON. ' + clean.substring(0, 220))
                    : 'Response kosong atau sesi login sudah berakhir.');
            }
        });
    })
    .then(function (data) {
        el('opnLoading').classList.add('d-none');
        if (!data.ok) { showAlert('danger', data.message || 'Gagal memuat data.'); return; }
        currentData = data.rows || [];
        reconConfirmMode = (data.meta && data.meta.confirm_mode) || 'BULK_ALLOWED';
        renderTable(currentData);
        updateSummary();
    })
    .catch(function (e) {
        el('opnLoading').classList.add('d-none');
        showAlert('danger', 'Error memuat data: ' + e.message);
    });
}

/* ── Event bindings ───────────────────────────────────────── */
el('opnLoad').addEventListener('click', function () { loadData(true); });
el('opnRefresh').addEventListener('click', function () { loadData(false); });
if (el('opnConfirmOpen')) el('opnConfirmOpen').addEventListener('click', function () { confirmDailyRecon('OPEN'); });
if (el('opnConfirmClose')) el('opnConfirmClose').addEventListener('click', function () { confirmDailyRecon('CLOSE'); });

// AJAX debounce on search (450ms)
var searchTimer = null;
el('opnQ').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { loadData(true); }, 450);
});
el('opnQ').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); clearTimeout(searchTimer); loadData(true); }
});

initTooltips();
loadData(true);

})();
</script>
