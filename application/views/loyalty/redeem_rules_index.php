<?php
$filters            = is_array($filters ?? null) ? $filters : [];
$stampCampaignOpts  = is_array($stamp_campaign_opts ?? null) ? $stamp_campaign_opts : [];
$voucherCampaignOpts= is_array($voucher_campaign_opts ?? null) ? $voucher_campaign_opts : [];

$COST_LABELS   = ['POINT' => 'Poin', 'STAMP' => 'Stamp', 'BOTH' => 'Poin + Stamp'];
$REWARD_LABELS = [
    'VOUCHER'          => 'Voucher',
    'PRODUCT'          => 'Produk',
    'MERCHANDISE'      => 'Merchandise',
    'DISCOUNT_AMOUNT'  => 'Diskon (Rp)',
    'DISCOUNT_PERCENT' => 'Diskon (%)',
    'FREE_PRODUCT'     => 'Produk Gratis',
    'OTHER'            => 'Reward Lainnya',
];
?>
<style>
.loyalty-filter-strip,.loyalty-table-card { border:1px solid #f0dfd2;border-radius:22px;background:#fff;box-shadow:0 14px 36px rgba(126,73,35,.06); }
.loyalty-status-tab.active,.loyalty-status-tab:hover { background:#8f353a;color:#fff;border-color:#8f353a; }
.loyalty-status-tab { border-radius:999px;border:1px solid #dcb7ab;background:#fffaf6;color:#81584d;font-weight:700; }
.loyalty-table thead th { color:#7a6055;font-size:.8rem;text-transform:uppercase;letter-spacing:.03em;border-bottom-color:#eddcd0; }
.loyalty-table tbody td { padding-top:.85rem;padding-bottom:.85rem;border-bottom-color:#f4e8df; }
/* cost / reward badges */
.rr-cost-badge  { display:inline-flex;padding:.18rem .55rem;border-radius:999px;font-size:.65rem;font-weight:800;letter-spacing:.02em; }
.rr-cost-badge.point   { background:#fff8e0;color:#7a5800; }
.rr-cost-badge.stamp   { background:#e8f0fb;color:#1a4a7a; }
.rr-cost-badge.both    { background:#f0e8fb;color:#5a1a7a; }
.rr-reward-badge { display:inline-flex;padding:.18rem .55rem;border-radius:999px;font-size:.65rem;font-weight:800;background:#e8f7ee;color:#1a5a3a;letter-spacing:.02em; }
/* ── Modal section headers ── */
.rr-section-head {
  display:flex;align-items:center;gap:.5rem;
  background:linear-gradient(90deg,#fdf0ec 0%,#fff8f5 100%);
  border-left:3px solid #c0434d;border-radius:0 10px 10px 0;
  padding:.55rem .9rem;margin-bottom:1rem;
  font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#7c1f2d;
}
.rr-section-head i { font-size:.95rem;opacity:.75; }
/* ── Field cards (Cost / Reward / Kondisi) ── */
.rr-block {
  background:#fffaf7;border:1px solid #f0dfd2;border-radius:14px;
  padding:1.1rem 1.1rem .85rem;margin-bottom:1.25rem;
}
/* ── Conditional field rows ── */
.rr-field-row { display:none; }
.rr-field-row.is-visible { display:flex; }
/* ── Number input — hide browser spinners ── */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none;margin:0; }
input[type=number] { -moz-appearance:textfield; }
/* ── Modal header accent ── */
#rrModal .modal-header {
  background:linear-gradient(135deg,#7c1f2d 0%,#a03040 100%);
  color:#fff;border-radius:calc(var(--bs-modal-border-radius) - 1px) calc(var(--bs-modal-border-radius) - 1px) 0 0;
  padding:1.1rem 1.4rem;
}
#rrModal .modal-header .btn-close { filter:invert(1) brightness(2); }
#rrModal .modal-header .modal-subtitle { color:rgba(255,255,255,.72);font-size:.8rem;margin-top:.15rem; }
/* ── ajax product lookup ── */
.loyalty-ajax-box { position:relative; }
.loyalty-ajax-result { position:absolute;z-index:20;inset:calc(100% + 6px) 0 auto 0;background:#fff;border:1px solid #ead7c8;border-radius:16px;box-shadow:0 18px 38px rgba(70,44,31,.14);max-height:240px;overflow:auto;display:none; }
.loyalty-ajax-result.is-open { display:block; }
.loyalty-ajax-item { padding:.75rem .9rem;border-bottom:1px solid #f4e7de;cursor:pointer;font-size:.82rem; }
.loyalty-ajax-item:last-child { border-bottom:none; }
.loyalty-ajax-item:hover { background:#fff7f0; }
.loyalty-ajax-selected { display:none;margin-top:.4rem;padding:.5rem .75rem;border:1px solid #bde6cc;border-radius:10px;background:#f0faf4;font-size:.8rem;color:#1a5a3a; }
.loyalty-ajax-selected.is-show { display:flex;align-items:center;gap:.4rem; }
.loyalty-ajax-selected::before { content:"✓";font-weight:700;color:#2e8b57; }
/* ── Switch toggle styling ── */
.rr-switch-wrap { background:#f8f8f8;border:1px solid #e8e0da;border-radius:12px;padding:.65rem .9rem;display:flex;align-items:center;gap:.6rem;height:100%; }
.rr-switch-wrap .form-check-input { width:2.2em;height:1.15em;cursor:pointer; }
.rr-switch-wrap .form-check-input:checked { background-color:#2e8b57;border-color:#2e8b57; }
/* ── Form label style ── */
#rrModal .form-label { font-size:.78rem;font-weight:600;color:#5a4540;margin-bottom:.3rem; }
#rrModal .form-text  { font-size:.68rem;color:#9a8880;margin-top:.25rem; }
#rrModal .form-control, #rrModal .form-select { font-size:.85rem;border-color:#ddd0c8;border-radius:8px; }
#rrModal .form-control:focus, #rrModal .form-select:focus { border-color:#c0434d;box-shadow:0 0 0 3px rgba(192,67,77,.12); }
#rrModal .input-group-text { font-size:.82rem;background:#f5ede8;border-color:#ddd0c8;color:#7a5850; }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1"><i class="ri ri-settings-3-line me-1"></i>Pengaturan Redeem</h4>
      <p class="fin-page-subtitle mb-0">Tentukan apa yang bisa ditukar dengan poin atau stamp oleh member — katalog ini jadi referensi saat proses redeem di halaman Member.</p>
    </div>
  </div>

  <?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => 'redeem-rule']); ?>

  <div class="loyalty-filter-strip p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h5 class="mb-0">Katalog Reward Redeem</h5>
      <button id="btn-new" type="button" class="btn btn-primary">+ Tambah Rule Redeem</button>
    </div>
    <div class="d-flex gap-2 flex-wrap mb-3" id="status-tabs">
      <button class="btn btn-sm loyalty-status-tab <?php echo ($filters['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'active' : ''; ?>" data-status="ACTIVE">Aktif</button>
      <button class="btn btn-sm loyalty-status-tab <?php echo ($filters['status'] ?? '') === 'INACTIVE' ? 'active' : ''; ?>" data-status="INACTIVE">Nonaktif</button>
      <button class="btn btn-sm loyalty-status-tab <?php echo ($filters['status'] ?? '') === 'ALL' ? 'active' : ''; ?>" data-status="ALL">Semua</button>
    </div>
    <div class="row g-2">
      <div class="col-lg-2">
        <select id="filter-cost-type" class="form-select form-select-sm">
          <option value="ALL">Semua Cara Bayar</option>
          <option value="POINT" <?php echo ($filters['cost_type'] ?? '') === 'POINT' ? 'selected' : ''; ?>>Poin</option>
          <option value="STAMP" <?php echo ($filters['cost_type'] ?? '') === 'STAMP' ? 'selected' : ''; ?>>Stamp</option>
          <option value="BOTH"  <?php echo ($filters['cost_type'] ?? '') === 'BOTH'  ? 'selected' : ''; ?>>Poin + Stamp</option>
        </select>
      </div>
      <div class="col-lg-3">
        <select id="filter-reward-type" class="form-select form-select-sm">
          <option value="ALL">Semua Jenis Reward</option>
          <?php foreach ($REWARD_LABELS as $v => $l): ?>
            <option value="<?php echo $v; ?>" <?php echo ($filters['reward_type'] ?? '') === $v ? 'selected' : ''; ?>><?php echo html_escape($l); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-5">
        <input id="filter-q" class="form-control form-control-sm" placeholder="Cari nama rule, kode, catatan reward…" value="<?php echo html_escape($filters['q'] ?? ''); ?>">
      </div>
      <div class="col-lg-2 d-flex gap-1">
        <button id="btn-search" type="button" class="btn btn-secondary btn-sm flex-fill"><i class="ri ri-search-2-line"></i> Cari</button>
        <button id="btn-reset" type="button" class="btn btn-outline-danger btn-sm">Reset</button>
      </div>
    </div>
  </div>

  <div class="loyalty-table-card p-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle loyalty-table mb-0">
        <thead>
          <tr>
            <th>Nama Rule</th>
            <th style="width:110px">Cara Bayar</th>
            <th style="width:120px">Biaya</th>
            <th style="width:120px">Jenis Reward</th>
            <th>Nilai Reward</th>
            <th style="width:80px" class="text-center">Stok</th>
            <th style="width:90px">Berlaku</th>
            <th style="width:90px">Status</th>
            <th style="width:140px" class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody id="table-body"></tbody>
      </table>
    </div>
    <div id="empty-state" class="text-muted py-3 text-center d-none" style="font-size:.85rem">Belum ada rule redeem yang sesuai filter.</div>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <small id="pagination-info" class="text-muted"></small>
      <div class="d-flex gap-1" id="pagination"></div>
    </div>
  </div>
</div>

<!-- ── Modal Form Rule Redeem ─────────────────────────────── -->
<div class="modal fade" id="rrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden">

      <!-- Header -->
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <i class="ri ri-gift-2-line" style="font-size:1.35rem;opacity:.85"></i>
          <div>
            <h5 class="modal-title mb-0 fw-bold" id="rrModalTitle" style="font-size:1rem;letter-spacing:.01em">Tambah Rule Redeem</h5>
            <div class="modal-subtitle">Tentukan biaya (poin/stamp) dan reward yang diterima member</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <!-- Body -->
      <div class="modal-body px-4 py-3" style="background:#f9f5f2">
        <input type="hidden" id="rr-id" value="">

        <!-- ① Identitas -->
        <div class="rr-section-head"><i class="ri ri-price-tag-3-line"></i> Identitas Rule</div>
        <div class="rr-block">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Kode Internal</label>
              <input type="text" id="rr-code" class="form-control" placeholder="Otomatis jika kosong">
              <div class="form-text">Biarkan kosong agar kode digenerate otomatis</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama Rule <span class="text-danger">*</span></label>
              <input type="text" id="rr-name" class="form-control" placeholder="Contoh: Tukar 500 Poin → Voucher Rp 50.000">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <div class="rr-switch-wrap form-check form-switch ms-0 ps-0">
                <input class="form-check-input ms-0" type="checkbox" role="switch" id="rr-active" checked>
                <label class="form-check-label ms-2 fw-semibold" for="rr-active" style="font-size:.85rem">Aktif</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Deskripsi</label>
              <textarea id="rr-desc" class="form-control" rows="2" placeholder="Penjelasan singkat untuk operator, misal: syarat, kondisi, atau catatan khusus…"></textarea>
            </div>
          </div>
        </div>

        <!-- ② Cara Bayar -->
        <div class="rr-section-head"><i class="ri ri-coins-line"></i> Cara Bayar (Cost)</div>
        <div class="rr-block">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Member Bayar Dengan <span class="text-danger">*</span></label>
              <select id="rr-cost-type" class="form-select">
                <option value="POINT">🟡 Poin saja</option>
                <option value="STAMP">🔵 Stamp saja</option>
                <option value="BOTH">🟣 Poin + Stamp</option>
              </select>
            </div>

            <!-- Poin -->
            <div class="col-md-3 rr-field-row rr-cost-point" id="field-point-cost">
              <label class="form-label">Poin Dibutuhkan <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" id="rr-point-cost" class="form-control" min="0" step="1" placeholder="500">
                <span class="input-group-text">poin</span>
              </div>
            </div>

            <!-- Stamp campaign -->
            <div class="col-md-4 rr-field-row rr-cost-stamp" id="field-stamp-campaign">
              <label class="form-label">Campaign Stamp <span class="text-danger">*</span></label>
              <select id="rr-stamp-campaign" class="form-select">
                <option value="">— pilih campaign stamp —</option>
                <?php foreach ($stampCampaignOpts as $sc): ?>
                  <option value="<?php echo (int)$sc['value']; ?>"><?php echo html_escape($sc['label']); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Campaign stamp yang berlaku untuk rule ini</div>
            </div>
            <!-- Stamp cost -->
            <div class="col-md-2 rr-field-row rr-cost-stamp" id="field-stamp-cost">
              <label class="form-label">Stamp Dibutuhkan <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" id="rr-stamp-cost" class="form-control" min="0" step="1" placeholder="8">
                <span class="input-group-text">stamp</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ③ Reward -->
        <div class="rr-section-head"><i class="ri ri-trophy-line"></i> Reward yang Diterima Member</div>
        <div class="rr-block">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Jenis Reward <span class="text-danger">*</span></label>
              <select id="rr-reward-type" class="form-select">
                <option value="DISCOUNT_AMOUNT">💰 Diskon nominal (Rp)</option>
                <option value="DISCOUNT_PERCENT">📉 Diskon persen (%)</option>
                <option value="VOUCHER">🎫 Voucher (dari campaign)</option>
                <option value="PRODUCT">📦 Produk / item</option>
                <option value="FREE_PRODUCT">🎁 Produk Gratis</option>
                <option value="MERCHANDISE">🛍️ Merchandise</option>
                <option value="OTHER">✨ Reward lainnya</option>
              </select>
            </div>

            <!-- Diskon Nominal -->
            <div class="col-md-4 rr-field-row rr-reward-discount-amt" id="field-discount-amount">
              <label class="form-label">Nilai Diskon <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" id="rr-discount-amount" class="form-control" min="0" step="1000" placeholder="50000">
              </div>
            </div>

            <!-- Diskon Persen -->
            <div class="col-md-4 rr-field-row rr-reward-discount-pct" id="field-discount-percent">
              <label class="form-label">Persentase Diskon <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" id="rr-discount-percent" class="form-control" min="0" max="100" step="0.5" placeholder="10">
                <span class="input-group-text">%</span>
              </div>
            </div>

            <!-- Voucher Campaign -->
            <div class="col-md-7 rr-field-row rr-reward-voucher" id="field-voucher-campaign">
              <label class="form-label">Campaign Voucher <span class="text-danger">*</span></label>
              <select id="rr-voucher-campaign" class="form-select">
                <option value="">— pilih campaign voucher —</option>
                <?php foreach ($voucherCampaignOpts as $vc): ?>
                  <option value="<?php echo (int)$vc['value']; ?>"><?php echo html_escape($vc['label']); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Sistem akan menerbitkan voucher dari campaign ini ke member saat redeem</div>
            </div>

            <!-- Produk search -->
            <div class="col-md-6 rr-field-row rr-reward-product" id="field-product">
              <label class="form-label">Produk Reward <span class="text-danger">*</span></label>
              <div class="loyalty-ajax-box" id="product-ajax-box">
                <input type="hidden" id="rr-product-id" value="">
                <input type="text" id="rr-product-q" class="form-control" placeholder="Ketik nama atau kode produk…" autocomplete="off">
                <div class="loyalty-ajax-result" id="product-ajax-result"></div>
                <div class="loyalty-ajax-selected" id="product-ajax-selected"></div>
              </div>
            </div>
            <div class="col-md-2 rr-field-row rr-reward-product" id="field-product-qty">
              <label class="form-label">Jumlah <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" id="rr-product-qty" class="form-control" min="0.0001" step="1" placeholder="1">
                <span class="input-group-text">unit</span>
              </div>
            </div>

            <!-- Merchandise / Other -->
            <div class="col-md-8 rr-field-row rr-reward-notes" id="field-reward-notes">
              <label class="form-label">Deskripsi Reward <span class="text-danger">*</span></label>
              <input type="text" id="rr-reward-notes" class="form-control" placeholder="Contoh: Tote bag branded, T-shirt M, Gratis 1 bulan berlangganan…">
            </div>
          </div>
        </div>

        <!-- ④ Kondisi & Masa Berlaku -->
        <div class="rr-section-head"><i class="ri ri-calendar-check-line"></i> Kondisi &amp; Masa Berlaku</div>
        <div class="rr-block mb-0">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Min. Belanja <span class="text-muted fw-normal">(opsional)</span></label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" id="rr-min-spend" class="form-control" min="0" step="1000" placeholder="Kosong = tanpa syarat">
              </div>
              <div class="form-text">Min. nilai transaksi agar member bisa pakai rule ini</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Stok Reward <span class="text-muted fw-normal">(opsional)</span></label>
              <div class="input-group">
                <input type="number" id="rr-stock-qty" class="form-control" min="0" step="1" placeholder="∞">
                <span class="input-group-text">buah</span>
              </div>
              <div class="form-text">Kosong = tidak terbatas</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Masa Berlaku <span class="text-muted fw-normal">(opsional)</span></label>
              <div class="input-group">
                <input type="number" id="rr-valid-days" class="form-control" min="1" step="1" placeholder="∞">
                <span class="input-group-text">hari</span>
              </div>
              <div class="form-text">Kosong = berlaku selamanya</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer" style="background:#fff;border-top:1px solid #f0dfd2;padding:.9rem 1.4rem">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="ri ri-close-line me-1"></i>Batal
        </button>
        <button type="button" id="btn-save" class="btn btn-primary px-4">
          <i class="ri ri-save-line me-1"></i><span class="btn-save-label">Simpan Rule</span>
        </button>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const dataUrl      = <?php echo json_encode(site_url('loyalty/redeem-rules/data')); ?>;
  const saveUrl      = <?php echo json_encode(site_url('loyalty/redeem-rules/save')); ?>;
  const toggleBase   = <?php echo json_encode(site_url('loyalty/redeem-rules/toggle')); ?>;
  const deleteBase   = <?php echo json_encode(site_url('loyalty/redeem-rules/delete')); ?>;
  const productSearchUrl = <?php echo json_encode(site_url('loyalty/product-search')); ?>;

  const COST_LABELS   = {POINT:'Poin',STAMP:'Stamp',BOTH:'Poin + Stamp'};
  const REWARD_LABELS = {
    VOUCHER:'Voucher',PRODUCT:'Produk',MERCHANDISE:'Merchandise',
    DISCOUNT_AMOUNT:'Diskon (Rp)',DISCOUNT_PERCENT:'Diskon (%)',
    FREE_PRODUCT:'Produk Gratis',OTHER:'Reward Lainnya'
  };

  const fmtMoney = v => 'Rp ' + Number(v||0).toLocaleString('id-ID');
  const fmtNum   = v => Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:4});
  const fmtDate  = v => v ? new Date(v+'T00:00:00').toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '—';
  const esc      = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

  let state = {
    q: <?php echo json_encode($filters['q'] ?? ''); ?>,
    status: <?php echo json_encode($filters['status'] ?? 'ACTIVE'); ?>,
    cost_type: <?php echo json_encode($filters['cost_type'] ?? 'ALL'); ?>,
    reward_type: <?php echo json_encode($filters['reward_type'] ?? 'ALL'); ?>,
    page: 1, limit: 25,
  };

  const tbody    = document.getElementById('table-body');
  const emptyEl  = document.getElementById('empty-state');
  const pgInfo   = document.getElementById('pagination-info');
  const pgEl     = document.getElementById('pagination');
  const modalEl  = document.getElementById('rrModal');
  const modal    = new bootstrap.Modal(modalEl);

  // ── Fetch helpers ──
  async function getJson(url) {
    const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.message || 'Error');
    return j;
  }
  async function postJson(url, payload) {
    const r = await fetch(url, {method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.message || 'Error');
    return j;
  }

  // ── Reward value renderer ──
  function rewardValueCell(row) {
    const rt = row.reward_type || '';
    if (rt === 'DISCOUNT_AMOUNT') return fmtMoney(row.discount_amount);
    if (rt === 'DISCOUNT_PERCENT') return (row.discount_percent || 0) + '%';
    if (rt === 'VOUCHER') return esc(row.voucher_campaign_name || '—');
    if (rt === 'PRODUCT' || rt === 'FREE_PRODUCT') return fmtNum(row.product_qty||1) + ' unit produk';
    return esc(row.reward_notes || '—');
  }
  function costCell(row) {
    const ct = row.cost_type || 'POINT';
    const parts = [];
    if (ct === 'POINT' || ct === 'BOTH') parts.push(fmtNum(row.point_cost) + ' poin');
    if (ct === 'STAMP' || ct === 'BOTH') {
      const sc = row.stamp_campaign_name ? ' (' + esc(row.stamp_campaign_name) + ')' : '';
      parts.push(fmtNum(row.stamp_cost) + ' stamp' + sc);
    }
    return parts.join('<br>') || '—';
  }

  // ── Render table ──
  function renderRows(rows) {
    if (!rows.length) { tbody.innerHTML=''; emptyEl.classList.remove('d-none'); return; }
    emptyEl.classList.add('d-none');
    tbody.innerHTML = rows.map(r => {
      const ct  = (r.cost_type  || 'POINT').toLowerCase();
      const rt  = (r.reward_type|| '').toLowerCase().replace(/_/g,'-');
      const stock = r.stock_qty != null
        ? `${Number(r.stock_qty).toLocaleString('id-ID')} <small class="text-muted">(sisa)</small>`
        : '<span class="text-muted">∞</span>';
      const statusBadge = Number(r.is_active) === 1
        ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>'
        : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>';
      return `<tr>
        <td>
          <div class="fw-semibold" style="font-size:.82rem">${esc(r.rule_name)}</div>
          <div style="font-size:.68rem;color:#888">${esc(r.rule_code)}</div>
          ${r.description ? `<div style="font-size:.68rem;color:#aaa;margin-top:.1rem">${esc(r.description)}</div>` : ''}
        </td>
        <td><span class="rr-cost-badge ${ct}">${esc(COST_LABELS[r.cost_type]||r.cost_type)}</span></td>
        <td style="font-size:.77rem">${costCell(r)}</td>
        <td><span class="rr-reward-badge">${esc(REWARD_LABELS[r.reward_type]||r.reward_type)}</span></td>
        <td style="font-size:.77rem">${rewardValueCell(r)}</td>
        <td class="text-center" style="font-size:.77rem">${stock}</td>
        <td style="font-size:.75rem">${r.valid_days ? Number(r.valid_days).toLocaleString('id-ID') + ' hari' : '<span class="text-muted">∞</span>'}</td>
        <td>${statusBadge}</td>
        <td class="text-center">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary btn-edit" data-row="${esc(JSON.stringify(r))}">Edit</button>
            <button class="btn btn-outline-${Number(r.is_active)===1?'danger':'success'} btn-toggle" data-id="${r.id}">${Number(r.is_active)===1?'Nonaktifkan':'Aktifkan'}</button>
            <button class="btn btn-outline-dark btn-delete" data-id="${r.id}">Hapus</button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  // ── Load data ──
  async function loadData() {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3" style="font-size:.8rem">Memuat…</td></tr>';
    emptyEl.classList.add('d-none');
    try {
      const params = new URLSearchParams({
        q: state.q, status: state.status,
        cost_type: state.cost_type, reward_type: state.reward_type,
        page: state.page, limit: state.limit,
      });
      const data = await getJson(dataUrl + '?' + params);
      renderRows(data.rows || []);
      const meta = data.meta || {};
      const total = meta.total || 0;
      pgInfo.textContent = `${(data.rows||[]).length} dari ${total.toLocaleString('id-ID')} rule · Hal ${meta.page||1}/${meta.total_pages||1}`;
      pgEl.innerHTML = '';
      if ((meta.total_pages||1) > 1) {
        if (meta.page > 1) {
          const b=document.createElement('button'); b.className='btn btn-outline-secondary btn-sm'; b.textContent='‹';
          b.onclick=()=>{state.page--;loadData();};pgEl.appendChild(b);
        }
        if (meta.page < meta.total_pages) {
          const b=document.createElement('button'); b.className='btn btn-outline-secondary btn-sm'; b.textContent='›';
          b.onclick=()=>{state.page++;loadData();};pgEl.appendChild(b);
        }
      }
    } catch(e) {
      tbody.innerHTML=`<tr><td colspan="9" class="text-center text-danger py-3" style="font-size:.8rem">Gagal memuat: ${esc(e.message)}</td></tr>`;
    }
  }

  // ── Filter controls ──
  document.getElementById('btn-search').addEventListener('click', () => {
    state.q           = document.getElementById('filter-q').value.trim();
    state.cost_type   = document.getElementById('filter-cost-type').value;
    state.reward_type = document.getElementById('filter-reward-type').value;
    state.page = 1;
    loadData();
  });
  document.getElementById('btn-reset').addEventListener('click', () => {
    document.getElementById('filter-q').value = '';
    document.getElementById('filter-cost-type').value = 'ALL';
    document.getElementById('filter-reward-type').value = 'ALL';
    state.q=''; state.cost_type='ALL'; state.reward_type='ALL'; state.page=1;
    loadData();
  });
  document.querySelectorAll('.loyalty-status-tab').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.loyalty-status-tab').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      state.status = this.dataset.status; state.page=1; loadData();
    });
  });
  document.getElementById('filter-q').addEventListener('keydown', e => { if(e.key==='Enter'){state.page=1;loadData();} });

  // ── Table action delegation ──
  tbody.addEventListener('click', async function(e) {
    const editBtn   = e.target.closest('.btn-edit');
    const toggleBtn = e.target.closest('.btn-toggle');
    const deleteBtn = e.target.closest('.btn-delete');

    if (editBtn) {
      const row = JSON.parse(decodeURIComponent(editBtn.dataset.row));
      openForm(row);
      return;
    }
    if (toggleBtn) {
      const id = toggleBtn.dataset.id;
      if (!confirm('Ubah status rule ini?')) return;
      try { await postJson(toggleBase + '/' + id, {}); loadData(); }
      catch(e) { alert('Error: '+e.message); }
      return;
    }
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      if (!confirm('Hapus rule redeem ini? Tindakan ini tidak bisa dibatalkan.')) return;
      try { await postJson(deleteBase + '/' + id, {}); loadData(); }
      catch(e) { alert('Gagal menghapus: '+e.message); }
    }
  });

  // ── Conditional field visibility ──
  function updateCostVisibility() {
    const ct = document.getElementById('rr-cost-type').value;
    const showPoint = ct==='POINT'||ct==='BOTH';
    const showStamp = ct==='STAMP'||ct==='BOTH';
    document.querySelectorAll('.rr-cost-point').forEach(el=>el.classList.toggle('is-visible',showPoint));
    document.querySelectorAll('.rr-cost-stamp').forEach(el=>el.classList.toggle('is-visible',showStamp));
  }

  function updateRewardVisibility() {
    const rt = document.getElementById('rr-reward-type').value;
    const showDiscAmt  = rt==='DISCOUNT_AMOUNT';
    const showDiscPct  = rt==='DISCOUNT_PERCENT';
    const showVoucher  = rt==='VOUCHER';
    const showProduct  = rt==='PRODUCT'||rt==='FREE_PRODUCT';
    const showNotes    = rt==='MERCHANDISE'||rt==='OTHER';

    document.querySelectorAll('.rr-reward-discount-amt').forEach(el=>el.classList.toggle('is-visible',showDiscAmt));
    document.querySelectorAll('.rr-reward-discount-pct').forEach(el=>el.classList.toggle('is-visible',showDiscPct));
    document.querySelectorAll('.rr-reward-voucher').forEach(el=>el.classList.toggle('is-visible',showVoucher));
    document.querySelectorAll('.rr-reward-product').forEach(el=>el.classList.toggle('is-visible',showProduct));
    document.querySelectorAll('.rr-reward-notes').forEach(el=>el.classList.toggle('is-visible',showNotes));
  }

  document.getElementById('rr-cost-type').addEventListener('change', updateCostVisibility);
  document.getElementById('rr-reward-type').addEventListener('change', updateRewardVisibility);

  // ── Product AJAX search ──
  let productSearchTimer = null;
  document.getElementById('rr-product-q').addEventListener('input', function() {
    clearTimeout(productSearchTimer);
    const q = this.value.trim();
    const resultEl = document.getElementById('product-ajax-result');
    if (!q) { resultEl.classList.remove('is-open'); resultEl.innerHTML=''; return; }
    productSearchTimer = setTimeout(async () => {
      const data = await (await fetch(productSearchUrl+'?q='+encodeURIComponent(q))).json();
      const rows = (data.rows||[]);
      if (!rows.length) { resultEl.innerHTML='<div class="loyalty-ajax-item" style="color:#999">Produk tidak ditemukan</div>'; resultEl.classList.add('is-open'); return; }
      resultEl.innerHTML = rows.map(r=>`<div class="loyalty-ajax-item" data-id="${r.id}" data-name="${esc(r.product_name)}">
        <strong>${esc(r.product_name)}</strong> <span style="font-size:.75rem;color:#888">${esc(r.product_code||'')}</span>
      </div>`).join('');
      resultEl.classList.add('is-open');
    }, 280);
  });
  document.getElementById('product-ajax-result').addEventListener('click', function(e) {
    const item = e.target.closest('.loyalty-ajax-item[data-id]');
    if (!item) return;
    document.getElementById('rr-product-id').value = item.dataset.id;
    document.getElementById('rr-product-q').value  = item.dataset.name || '';
    document.getElementById('product-ajax-selected').textContent = item.dataset.name || '';
    document.getElementById('product-ajax-selected').classList.add('is-show');
    this.classList.remove('is-open'); this.innerHTML='';
  });
  document.addEventListener('click', e => {
    if (!document.getElementById('product-ajax-box').contains(e.target)) {
      document.getElementById('product-ajax-result').classList.remove('is-open');
    }
  });

  // ── Open form ──
  function resetForm() {
    document.getElementById('rr-id').value='';
    document.getElementById('rr-code').value='';
    document.getElementById('rr-name').value='';
    document.getElementById('rr-desc').value='';
    document.getElementById('rr-active').checked=true;
    document.getElementById('rr-cost-type').value='POINT';
    document.getElementById('rr-point-cost').value='';
    document.getElementById('rr-stamp-campaign').value='';
    document.getElementById('rr-stamp-cost').value='';
    document.getElementById('rr-reward-type').value='DISCOUNT_AMOUNT';
    document.getElementById('rr-discount-amount').value='';
    document.getElementById('rr-discount-percent').value='';
    document.getElementById('rr-voucher-campaign').value='';
    document.getElementById('rr-product-id').value='';
    document.getElementById('rr-product-q').value='';
    document.getElementById('product-ajax-selected').classList.remove('is-show');
    document.getElementById('product-ajax-selected').textContent='';
    document.getElementById('rr-product-qty').value='';
    document.getElementById('rr-reward-notes').value='';
    document.getElementById('rr-min-spend').value='';
    document.getElementById('rr-stock-qty').value='';
    document.getElementById('rr-valid-days').value='';
    updateCostVisibility();
    updateRewardVisibility();
  }

  function openForm(row) {
    resetForm();
    document.getElementById('rrModalTitle').textContent = row ? 'Edit Rule Redeem' : 'Tambah Rule Redeem';
    if (row) {
      document.getElementById('rr-id').value             = row.id || '';
      document.getElementById('rr-code').value           = row.rule_code || '';
      document.getElementById('rr-name').value           = row.rule_name || '';
      document.getElementById('rr-desc').value           = row.description || '';
      document.getElementById('rr-active').checked       = Number(row.is_active) === 1;
      document.getElementById('rr-cost-type').value      = row.cost_type || 'POINT';
      document.getElementById('rr-point-cost').value     = row.point_cost || '';
      document.getElementById('rr-stamp-campaign').value = row.stamp_campaign_id || '';
      document.getElementById('rr-stamp-cost').value     = row.stamp_cost || '';
      document.getElementById('rr-reward-type').value    = row.reward_type || 'DISCOUNT_AMOUNT';
      document.getElementById('rr-discount-amount').value= row.discount_amount || '';
      document.getElementById('rr-discount-percent').value= row.discount_percent || '';
      document.getElementById('rr-voucher-campaign').value= row.voucher_campaign_id || '';
      if (row.product_id) {
        document.getElementById('rr-product-id').value  = row.product_id;
        document.getElementById('rr-product-q').value   = '(produk ID ' + row.product_id + ')';
        document.getElementById('product-ajax-selected').textContent = 'Produk ID: ' + row.product_id;
        document.getElementById('product-ajax-selected').classList.add('is-show');
      }
      document.getElementById('rr-product-qty').value  = row.product_qty || '';
      document.getElementById('rr-reward-notes').value = row.reward_notes || '';
      document.getElementById('rr-min-spend').value    = row.min_spend_amount || '';
      document.getElementById('rr-stock-qty').value    = row.stock_qty || '';
      document.getElementById('rr-valid-days').value   = row.valid_days  || '';
      updateCostVisibility();
      updateRewardVisibility();
    }
    modal.show();
  }

  document.getElementById('btn-new').addEventListener('click', () => openForm(null));

  // ── Save ──
  document.getElementById('btn-save').addEventListener('click', async function() {
    const name = document.getElementById('rr-name').value.trim();
    if (!name) { alert('Nama rule wajib diisi.'); return; }

    const payload = {
      id:                 parseInt(document.getElementById('rr-id').value) || 0,
      rule_code:          document.getElementById('rr-code').value.trim(),
      rule_name:          name,
      description:        document.getElementById('rr-desc').value.trim(),
      is_active:          document.getElementById('rr-active').checked ? 1 : 0,
      cost_type:          document.getElementById('rr-cost-type').value,
      point_cost:         parseFloat(document.getElementById('rr-point-cost').value) || null,
      stamp_campaign_id:  parseInt(document.getElementById('rr-stamp-campaign').value) || null,
      stamp_cost:         parseFloat(document.getElementById('rr-stamp-cost').value) || null,
      reward_type:        document.getElementById('rr-reward-type').value,
      discount_amount:    parseFloat(document.getElementById('rr-discount-amount').value) || null,
      discount_percent:   parseFloat(document.getElementById('rr-discount-percent').value) || null,
      voucher_campaign_id:parseInt(document.getElementById('rr-voucher-campaign').value) || null,
      product_id:         parseInt(document.getElementById('rr-product-id').value) || null,
      product_qty:        parseFloat(document.getElementById('rr-product-qty').value) || null,
      reward_notes:       document.getElementById('rr-reward-notes').value.trim(),
      min_spend_amount:   parseFloat(document.getElementById('rr-min-spend').value) || null,
      stock_qty:          parseInt(document.getElementById('rr-stock-qty').value) || null,
      valid_days:         parseInt(document.getElementById('rr-valid-days').value) || null,
    };

    const btn = this; btn.disabled=true;
    const lbl = btn.querySelector('.btn-save-label');
    lbl.textContent='Menyimpan…';
    try {
      await postJson(saveUrl, payload);
      modal.hide();
      loadData();
    } catch(e) {
      alert('Gagal menyimpan: '+e.message);
    } finally {
      btn.disabled=false; lbl.textContent='Simpan Rule';
    }
  });

  // ── Init ──
  loadData();
});
</script>
