<?php
$pageTitle = (string)($page_title ?? 'Redeem Poin / Voucher / Stamp');
?>
<style>
.rdm-shell { border:1px solid #f0dfd2;border-radius:20px;background:#fff;box-shadow:0 14px 36px rgba(126,73,35,.06); }
.rdm-section-title { font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#9a7060;margin-bottom:.5rem; }
/* Member search card */
.rdm-search-card { border:1px solid #e8ddd4;border-radius:16px;background:#fffaf7;padding:1.1rem 1.25rem; }
.rdm-member-card { border:1px solid #b5d3b2;border-radius:16px;background:linear-gradient(135deg,#f0fbee 0%,#fff 100%);padding:1rem 1.2rem;display:none; }
.rdm-member-card.is-visible { display:block; }
.rdm-member-name { font-size:1.05rem;font-weight:800;color:#1d3c1c; }
.rdm-member-sub  { font-size:.72rem;color:#5a7a58;margin-top:.1rem; }
/* Balance chips */
.rdm-bal-chip { display:inline-flex;flex-direction:column;align-items:center;justify-content:center;
  min-width:100px;padding:.5rem .8rem;border-radius:12px;border:1px solid;text-align:center; }
.rdm-bal-chip.point { border-color:#c9a84c;background:#fffbf0; }
.rdm-bal-chip.stamp { border-color:#7faed6;background:#f0f6ff; }
.rdm-bal-chip.voucher { border-color:#9cbf9c;background:#f0fbee; }
.rdm-bal-chip .lbl { font-size:.59rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;opacity:.7; }
.rdm-bal-chip .val { font-size:1rem;font-weight:900;line-height:1.1;margin-top:.15rem; }
.rdm-bal-chip.point .val { color:#8a6200; }
.rdm-bal-chip.stamp .val { color:#1a4a7a; }
.rdm-bal-chip.voucher .val { color:#1f5e1f; }
/* Action buttons */
.rdm-action-btn { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .85rem;border-radius:10px;
  font-size:.8rem;font-weight:700;border:1px solid;cursor:pointer;transition:all .15s; }
.rdm-action-btn.point  { border-color:#c9a84c;color:#7a5800;background:#fffbf0; }
.rdm-action-btn.stamp  { border-color:#7faed6;color:#1a4a7a;background:#f0f6ff; }
.rdm-action-btn.voucher{ border-color:#9cbf9c;color:#1f5e1f;background:#f0fbee; }
.rdm-action-btn:hover  { opacity:.82;transform:translateY(-1px); }
/* Log table */
.rdm-tbl-wrap { overflow:auto;max-height:60vh; }
.rdm-tbl { table-layout:fixed;min-width:700px;margin-bottom:0;border-collapse:separate;border-spacing:0; }
.rdm-tbl thead th { position:sticky;top:0;z-index:4;
  background:linear-gradient(180deg,#7c1f2d 0%,#9f2f3e 100%);
  color:#fff8f5;border-bottom:1px solid #7f2936;white-space:nowrap;font-size:.75rem; }
.rdm-tbl td,.rdm-tbl th { vertical-align:middle;border-right:1px solid #efddd2;border-bottom:1px solid #f3e4da;font-size:.77rem; }
.rdm-tbl tbody td { background:#fff; }
.rdm-tbl tbody tr:nth-child(even) td { background:#fffaf6; }
.rdm-type-badge { display:inline-flex;align-items:center;padding:.18rem .5rem;border-radius:999px;font-size:.64rem;font-weight:800;white-space:nowrap; }
.rdm-type-badge.point   { background:#fff8e0;color:#7a5800; }
.rdm-type-badge.stamp   { background:#e8f0fb;color:#1a4a7a; }
.rdm-type-badge.voucher { background:#e5f7e5;color:#1f5e1f; }
/* Voucher list in modal */
.rdm-vch-row { border:1px solid #d7e8d7;border-radius:10px;background:#f8fcf8;padding:.6rem .8rem;margin-bottom:.4rem;display:flex;align-items:center;gap:.7rem; }
.rdm-vch-code { font-size:.8rem;font-weight:800;color:#1a4a1a;flex:0 0 auto; }
.rdm-vch-desc { flex:1 1 0;min-width:0;font-size:.74rem;color:#3a5c3a; }
.rdm-vch-exp  { font-size:.65rem;color:#8a9a8a;white-space:nowrap;flex:0 0 auto; }
.rdm-empty-state { text-align:center;padding:2rem;color:#bbb; }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1"><i class="ri ri-gift-2-line me-1"></i><?php echo html_escape($pageTitle); ?></h4>
      <p class="fin-page-subtitle mb-0">Tukarkan poin, stamp, atau voucher milik member secara manual.</p>
    </div>
  </div>

  <?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => 'redeem']); ?>

  <!-- Member Search -->
  <div class="rdm-search-card mb-3">
    <div class="rdm-section-title">Cari Member</div>
    <div class="d-flex gap-2 align-items-end flex-wrap">
      <div style="flex:1 1 240px;max-width:360px">
        <label class="form-label form-label-sm mb-1">Nama / No HP / No Member</label>
        <input type="text" id="rdmMemberQ" class="form-control form-control-sm" placeholder="Ketik lalu pilih dari dropdown…" autocomplete="off">
        <div id="rdmMemberDropdown" style="position:relative;display:none">
          <ul id="rdmMemberList" class="list-unstyled mb-0" style="position:absolute;z-index:99;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);width:100%;max-height:220px;overflow-y:auto;padding:.25rem 0;margin:0"></ul>
        </div>
      </div>
      <button id="rdmClearBtn" type="button" class="btn btn-outline-secondary btn-sm" style="display:none">
        <i class="ri ri-close-line"></i> Clear
      </button>
    </div>

    <!-- Member Info Card -->
    <div id="rdmMemberCard" class="rdm-member-card mt-3">
      <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
        <div>
          <div class="rdm-member-name" id="rdmMemberName">—</div>
          <div class="rdm-member-sub" id="rdmMemberSub">—</div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <div class="rdm-bal-chip point">
            <span class="lbl">Poin</span>
            <span class="val" id="rdmBalPoint">0</span>
          </div>
          <div class="rdm-bal-chip stamp">
            <span class="lbl">Stamp</span>
            <span class="val" id="rdmBalStamp">0</span>
          </div>
          <div class="rdm-bal-chip voucher">
            <span class="lbl">Voucher</span>
            <span class="val" id="rdmBalVoucher">0</span>
          </div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="rdm-action-btn point" id="rdmBtnPoint">
          <i class="ri ri-coin-line"></i> Redeem Poin
        </button>
        <button type="button" class="rdm-action-btn stamp" id="rdmBtnStamp">
          <i class="ri ri-coupon-3-line"></i> Redeem Stamp
        </button>
        <button type="button" class="rdm-action-btn voucher" id="rdmBtnVoucher">
          <i class="ri ri-ticket-2-line"></i> Redeem Voucher
        </button>
      </div>
    </div>
  </div>

  <!-- Log Table -->
  <div class="rdm-shell">
    <div class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-end justify-content-between">
      <div class="rdm-section-title mb-0">Log Redeem</div>
      <div class="d-flex flex-wrap gap-2 align-items-end">
        <div>
          <label class="form-label form-label-sm mb-1">Dari</label>
          <input type="date" id="rdmLogFrom" class="form-control form-control-sm" style="width:130px" value="<?php echo date('Y-m-01'); ?>">
        </div>
        <div>
          <label class="form-label form-label-sm mb-1">Sampai</label>
          <input type="date" id="rdmLogTo" class="form-control form-control-sm" style="width:130px" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div>
          <label class="form-label form-label-sm mb-1">Tipe</label>
          <select id="rdmLogType" class="form-select form-select-sm" style="width:130px">
            <option value="ALL">Semua</option>
            <option value="POINT">Poin</option>
            <option value="STAMP">Stamp</option>
            <option value="VOUCHER">Voucher</option>
          </select>
        </div>
        <button type="button" id="rdmLogSearchBtn" class="btn btn-secondary btn-sm">
          <i class="ri ri-search-2-line"></i> Tampilkan
        </button>
      </div>
    </div>
    <div class="rdm-tbl-wrap">
      <table class="table table-hover rdm-tbl">
        <thead>
          <tr>
            <th style="width:150px" class="ps-3">No Redeem</th>
            <th style="width:160px">Member</th>
            <th style="width:90px">Tipe</th>
            <th style="width:80px" class="text-end">Digunakan</th>
            <th style="width:200px">Keterangan Reward</th>
            <th style="width:110px" class="text-end">Nilai Reward</th>
            <th style="width:130px">Tanggal</th>
          </tr>
        </thead>
        <tbody id="rdmLogBody">
          <tr><td colspan="7" class="rdm-empty-state text-muted py-4">Klik Tampilkan untuk memuat data</td></tr>
        </tbody>
      </table>
    </div>
    <div class="p-2 border-top d-flex justify-content-between align-items-center" style="font-size:.75rem;color:#888">
      <span id="rdmLogMeta">—</span>
      <div id="rdmLogPager" class="d-flex gap-1"></div>
    </div>
  </div>
</div>

<!-- ── Modal Redeem Poin ──────────────────────────────── -->
<div class="modal fade" id="rdmPointModal" tabindex="-1" aria-labelledby="rdmPointModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rdmPointModalLabel"><i class="ri ri-coin-line text-warning me-1"></i>Redeem Poin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-light border mb-3 p-2" style="font-size:.82rem">
          Member: <strong id="rdmPointMemberName">—</strong><br>
          Saldo Poin: <strong id="rdmPointBalance" class="text-warning">0</strong>
        </div>
        <div class="mb-3">
          <label class="form-label form-label-sm">Jumlah Poin Ditukar <span class="text-danger">*</span></label>
          <input type="number" id="rdmPointQty" class="form-control form-control-sm" min="0.01" step="0.01" placeholder="Contoh: 100">
          <div class="form-text text-muted" style="font-size:.7rem" id="rdmPointSisaInfo"></div>
        </div>
        <div class="mb-3">
          <label class="form-label form-label-sm">Keterangan Reward / Benefit <span class="text-danger">*</span></label>
          <input type="text" id="rdmPointRewardDesc" class="form-control form-control-sm" placeholder="Contoh: Tukar 100 poin dapat diskon Rp 10.000">
        </div>
        <div class="mb-3">
          <label class="form-label form-label-sm">Nilai Reward (opsional)</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text">Rp</span>
            <input type="number" id="rdmPointRewardAmt" class="form-control" min="0" step="100" placeholder="Nilai diskon / reward dalam rupiah">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Catatan (opsional)</label>
          <textarea id="rdmPointNotes" class="form-control form-control-sm" rows="2" placeholder="Catatan tambahan…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="rdmPointSubmitBtn" class="btn btn-warning btn-sm text-dark fw-bold">
          <i class="ri ri-check-line me-1"></i>Proses Redeem Poin
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Redeem Stamp ─────────────────────────────── -->
<div class="modal fade" id="rdmStampModal" tabindex="-1" aria-labelledby="rdmStampModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rdmStampModalLabel"><i class="ri ri-coupon-3-line text-primary me-1"></i>Redeem Stamp</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-light border mb-3 p-2" style="font-size:.82rem">
          Member: <strong id="rdmStampMemberName">—</strong><br>
          Saldo Stamp: <strong id="rdmStampBalance" class="text-primary">0</strong>
        </div>
        <div class="mb-3">
          <label class="form-label form-label-sm">Pilih Campaign Stamp <span class="text-danger">*</span></label>
          <select id="rdmStampCampaign" class="form-select form-select-sm">
            <option value="">— pilih campaign —</option>
          </select>
          <div class="form-text" id="rdmStampCampaignInfo" style="font-size:.7rem"></div>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Catatan (opsional)</label>
          <textarea id="rdmStampNotes" class="form-control form-control-sm" rows="2" placeholder="Catatan tambahan…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="rdmStampSubmitBtn" class="btn btn-primary btn-sm fw-bold">
          <i class="ri ri-check-line me-1"></i>Proses Redeem Stamp
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Redeem Voucher ───────────────────────────── -->
<div class="modal fade" id="rdmVoucherModal" tabindex="-1" aria-labelledby="rdmVoucherModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rdmVoucherModalLabel"><i class="ri ri-ticket-2-line text-success me-1"></i>Redeem Voucher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-light border mb-3 p-2" style="font-size:.82rem">
          Member: <strong id="rdmVoucherMemberName">—</strong>
        </div>
        <div class="rdm-section-title">Voucher Aktif Milik Member</div>
        <div id="rdmVoucherList">
          <div class="rdm-empty-state text-muted py-3">Tidak ada voucher aktif</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Konfirmasi Redeem Voucher ────────────────── -->
<div class="modal fade" id="rdmVoucherConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Konfirmasi Redeem Voucher</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1" style="font-size:.85rem">Voucher berikut akan ditandai sebagai <strong>SUDAH DIGUNAKAN</strong>:</p>
        <div class="rdm-vch-row mt-2">
          <div>
            <div class="rdm-vch-code" id="rdmConfirmVchCode">—</div>
            <div class="rdm-vch-desc" id="rdmConfirmVchDesc">—</div>
            <div class="rdm-vch-exp" id="rdmConfirmVchExp"></div>
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label form-label-sm">Catatan (opsional)</label>
          <textarea id="rdmConfirmVchNotes" class="form-control form-control-sm" rows="2" placeholder="Catatan…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="rdmConfirmVchSubmitBtn" class="btn btn-success btn-sm fw-bold">
          <i class="ri ri-check-double-line me-1"></i>Konfirmasi Redeem
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const memberSearchUrl  = <?php echo json_encode(site_url('loyalty/member-search')); ?>;
  const memberInfoUrl    = <?php echo json_encode(site_url('loyalty/redeem/member-info')); ?>;
  const redeemDataUrl    = <?php echo json_encode(site_url('loyalty/redeem/data')); ?>;
  const redeemPointUrl   = <?php echo json_encode(site_url('loyalty/redeem/point')); ?>;
  const redeemStampUrl   = <?php echo json_encode(site_url('loyalty/redeem/stamp')); ?>;
  const redeemVoucherUrl = <?php echo json_encode(site_url('loyalty/redeem/voucher')); ?>;

  let currentMemberId   = 0;
  let currentMemberInfo = null;
  let logPage           = 1;
  let logMemberId       = 0;
  let activeVoucherIssueId = 0;

  const fmtNum  = v => Number(v || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
  const fmtMoney = v => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
  const fmtDate  = v => v ? new Date(v).toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric'}) : '—';
  const fmtDateTime = v => v ? new Date(v.replace(' ','T')).toLocaleString('id-ID', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';

  // ── Member Search ──────────────────────────────────────
  const memberQ        = document.getElementById('rdmMemberQ');
  const memberDropdown = document.getElementById('rdmMemberDropdown');
  const memberList     = document.getElementById('rdmMemberList');
  const clearBtn       = document.getElementById('rdmClearBtn');
  const memberCard     = document.getElementById('rdmMemberCard');

  let searchTimer = null;
  memberQ.addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = memberQ.value.trim();
    if (q.length < 1) { memberDropdown.style.display = 'none'; return; }
    searchTimer = setTimeout(async () => {
      const res = await fetch(memberSearchUrl + '?q=' + encodeURIComponent(q));
      if (!res.ok) return;
      const data = await res.json();
      const rows = data.rows || [];
      memberList.innerHTML = rows.length === 0
        ? '<li style="padding:.5rem 1rem;color:#999;font-size:.8rem">Tidak ditemukan</li>'
        : rows.map(r => `<li data-id="${r.id}" data-name="${r.member_name}" style="padding:.45rem 1rem;cursor:pointer;font-size:.82rem;border-bottom:1px solid #f0e8e0">
            <strong>${r.member_name}</strong> <span style="color:#888">${r.mobile_phone || ''}</span>
          </li>`).join('');
      memberDropdown.style.display = 'block';
    }, 260);
  });

  memberList.addEventListener('click', function (e) {
    const li = e.target.closest('li[data-id]');
    if (!li) return;
    memberDropdown.style.display = 'none';
    memberQ.value = li.dataset.name || '';
    loadMemberInfo(parseInt(li.dataset.id, 10));
  });

  document.addEventListener('click', function (e) {
    if (!memberQ.contains(e.target) && !memberDropdown.contains(e.target)) {
      memberDropdown.style.display = 'none';
    }
  });

  clearBtn.addEventListener('click', function () {
    memberQ.value = '';
    memberDropdown.style.display = 'none';
    memberCard.classList.remove('is-visible');
    currentMemberId   = 0;
    currentMemberInfo = null;
    clearBtn.style.display = 'none';
    logMemberId = 0;
  });

  async function loadMemberInfo(memberId) {
    const res = await fetch(memberInfoUrl + '?member_id=' + memberId);
    if (!res.ok) { alert('Gagal memuat info member.'); return; }
    const data = await res.json();
    if (!data.ok) { alert(data.message || 'Error.'); return; }

    currentMemberId   = memberId;
    currentMemberInfo = data;
    logMemberId       = memberId;

    const m = data.member || {};
    document.getElementById('rdmMemberName').textContent = m.member_name || '—';
    document.getElementById('rdmMemberSub').textContent  = [m.member_no, m.mobile_phone].filter(Boolean).join(' · ') || '—';
    document.getElementById('rdmBalPoint').textContent   = fmtNum(data.point_balance);
    document.getElementById('rdmBalStamp').textContent   = fmtNum(data.stamp_balance);
    document.getElementById('rdmBalVoucher').textContent = data.open_voucher_count || 0;
    memberCard.classList.add('is-visible');
    clearBtn.style.display = '';

    loadLog();
  }

  // ── Log Table ─────────────────────────────────────────
  function buildLogUrl(page) {
    const params = new URLSearchParams({
      date_from: document.getElementById('rdmLogFrom').value,
      date_to:   document.getElementById('rdmLogTo').value,
      type:      document.getElementById('rdmLogType').value,
      member_id: logMemberId || '',
      page:      page,
      limit:     25,
    });
    return redeemDataUrl + '?' + params.toString();
  }

  async function loadLog(page) {
    logPage = page || 1;
    const tbody = document.getElementById('rdmLogBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3" style="font-size:.8rem">Memuat…</td></tr>';

    const res = await fetch(buildLogUrl(logPage));
    if (!res.ok) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3" style="font-size:.8rem">Gagal memuat data.</td></tr>';
      return;
    }
    const data = await res.json();
    const rows = data.rows || [];
    const meta = data.meta || {};

    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="rdm-empty-state text-muted py-4">Belum ada transaksi redeem</td></tr>';
    } else {
      tbody.innerHTML = rows.map(r => {
        const typeClass = r.redeem_type === 'POINT' ? 'point' : r.redeem_type === 'STAMP' ? 'stamp' : 'voucher';
        const typeLabel = r.redeem_type === 'POINT' ? 'Poin' : r.redeem_type === 'STAMP' ? 'Stamp' : 'Voucher';
        let used = '—';
        if (r.redeem_type === 'POINT' && r.points_used) used = fmtNum(r.points_used) + ' poin';
        else if (r.redeem_type === 'STAMP' && r.stamps_used) used = fmtNum(r.stamps_used) + ' stamp';
        else if (r.redeem_type === 'VOUCHER' && r.voucher_code) used = r.voucher_code;
        return `<tr>
          <td class="ps-3" style="font-size:.72rem;font-weight:700">${r.redeem_no || '—'}</td>
          <td><div style="font-weight:600">${r.member_name || '—'}</div><div style="font-size:.65rem;color:#888">${r.member_no || ''}</div></td>
          <td><span class="rdm-type-badge ${typeClass}">${typeLabel}</span></td>
          <td class="text-end" style="font-size:.75rem">${used}</td>
          <td style="font-size:.74rem">${r.reward_desc || '—'}</td>
          <td class="text-end" style="font-size:.74rem">${r.reward_amount ? fmtMoney(r.reward_amount) : '—'}</td>
          <td style="font-size:.72rem;color:#666">${fmtDateTime(r.created_at)}</td>
        </tr>`;
      }).join('');
    }

    const total = meta.total || 0;
    const totalPages = meta.total_pages || 1;
    document.getElementById('rdmLogMeta').textContent =
      `Menampilkan ${rows.length} dari ${total.toLocaleString('id-ID')} transaksi · Halaman ${logPage}/${totalPages}`;

    const pager = document.getElementById('rdmLogPager');
    pager.innerHTML = '';
    if (totalPages > 1) {
      if (logPage > 1) {
        const b = document.createElement('button');
        b.className = 'btn btn-outline-secondary btn-sm'; b.textContent = '‹';
        b.addEventListener('click', () => loadLog(logPage - 1));
        pager.appendChild(b);
      }
      if (logPage < totalPages) {
        const b = document.createElement('button');
        b.className = 'btn btn-outline-secondary btn-sm'; b.textContent = '›';
        b.addEventListener('click', () => loadLog(logPage + 1));
        pager.appendChild(b);
      }
    }
  }

  document.getElementById('rdmLogSearchBtn').addEventListener('click', () => loadLog(1));
  loadLog(1);

  // ── Modal Redeem Poin ──────────────────────────────────
  const pointModal = new bootstrap.Modal(document.getElementById('rdmPointModal'));
  document.getElementById('rdmBtnPoint').addEventListener('click', function () {
    if (!currentMemberId || !currentMemberInfo) return;
    const m = currentMemberInfo.member || {};
    document.getElementById('rdmPointMemberName').textContent = m.member_name || '—';
    document.getElementById('rdmPointBalance').textContent    = fmtNum(currentMemberInfo.point_balance);
    document.getElementById('rdmPointQty').value              = '';
    document.getElementById('rdmPointRewardDesc').value       = '';
    document.getElementById('rdmPointRewardAmt').value        = '';
    document.getElementById('rdmPointNotes').value            = '';
    document.getElementById('rdmPointSisaInfo').textContent   = '';
    pointModal.show();
  });

  document.getElementById('rdmPointQty').addEventListener('input', function () {
    const used    = parseFloat(this.value) || 0;
    const balance = parseFloat(currentMemberInfo && currentMemberInfo.point_balance || 0);
    const sisa    = balance - used;
    const info    = document.getElementById('rdmPointSisaInfo');
    if (used > 0) {
      info.textContent = `Sisa setelah redeem: ${fmtNum(sisa)} poin`;
      info.className = sisa < -0.001 ? 'form-text text-danger' : 'form-text text-muted';
    } else {
      info.textContent = '';
    }
  });

  document.getElementById('rdmPointSubmitBtn').addEventListener('click', async function () {
    const qty  = parseFloat(document.getElementById('rdmPointQty').value);
    const desc = document.getElementById('rdmPointRewardDesc').value.trim();
    if (!qty || qty <= 0) { alert('Jumlah poin harus lebih dari 0.'); return; }
    if (!desc) { alert('Keterangan reward wajib diisi.'); return; }

    const btn = this; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses…';
    try {
      const res  = await fetch(redeemPointUrl, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
        member_id:   currentMemberId,
        points_used: qty,
        reward_desc: desc,
        reward_amount: parseFloat(document.getElementById('rdmPointRewardAmt').value) || null,
        notes: document.getElementById('rdmPointNotes').value.trim(),
      })});
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Gagal.');
      pointModal.hide();
      await loadMemberInfo(currentMemberId);
      loadLog(1);
      alert('Redeem poin berhasil! No: ' + data.redeem_no);
    } catch (e) {
      alert('Error: ' + e.message);
    } finally {
      btn.disabled = false; btn.innerHTML = '<i class="ri ri-check-line me-1"></i>Proses Redeem Poin';
    }
  });

  // ── Modal Redeem Stamp ─────────────────────────────────
  const stampModal = new bootstrap.Modal(document.getElementById('rdmStampModal'));
  document.getElementById('rdmBtnStamp').addEventListener('click', function () {
    if (!currentMemberId || !currentMemberInfo) return;
    const m = currentMemberInfo.member || {};
    document.getElementById('rdmStampMemberName').textContent = m.member_name || '—';
    document.getElementById('rdmStampBalance').textContent    = fmtNum(currentMemberInfo.stamp_balance);
    document.getElementById('rdmStampNotes').value            = '';

    const sel      = document.getElementById('rdmStampCampaign');
    const balance  = parseFloat(currentMemberInfo.stamp_balance || 0);
    const campaigns = currentMemberInfo.stamp_campaigns || [];
    sel.innerHTML  = '<option value="">— pilih campaign —</option>';
    campaigns.forEach(c => {
      const required = parseFloat(c.redeem_required_stamp || 0);
      const canRedeem = balance >= required - 0.0001;
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.campaign_name + ' (' + fmtNum(required) + ' stamp diperlukan)' + (canRedeem ? '' : ' ✗ stamp kurang');
      opt.disabled = !canRedeem;
      opt.dataset.required = required;
      sel.appendChild(opt);
    });
    document.getElementById('rdmStampCampaignInfo').textContent = '';
    stampModal.show();
  });

  document.getElementById('rdmStampCampaign').addEventListener('change', function () {
    const opt  = this.options[this.selectedIndex];
    const info = document.getElementById('rdmStampCampaignInfo');
    if (!this.value) { info.textContent = ''; return; }
    const req  = parseFloat(opt.dataset.required || 0);
    const bal  = parseFloat(currentMemberInfo && currentMemberInfo.stamp_balance || 0);
    info.textContent = `Stamp digunakan: ${fmtNum(req)} · Sisa: ${fmtNum(bal - req)}`;
    info.className   = (bal - req) < -0.001 ? 'form-text text-danger' : 'form-text text-muted';
  });

  document.getElementById('rdmStampSubmitBtn').addEventListener('click', async function () {
    const campaignId = parseInt(document.getElementById('rdmStampCampaign').value);
    if (!campaignId) { alert('Pilih campaign stamp terlebih dahulu.'); return; }

    const btn = this; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses…';
    try {
      const res  = await fetch(redeemStampUrl, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
        member_id:   currentMemberId,
        campaign_id: campaignId,
        notes: document.getElementById('rdmStampNotes').value.trim(),
      })});
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Gagal.');
      stampModal.hide();
      await loadMemberInfo(currentMemberId);
      loadLog(1);
      alert('Redeem stamp berhasil! No: ' + data.redeem_no);
    } catch (e) {
      alert('Error: ' + e.message);
    } finally {
      btn.disabled = false; btn.innerHTML = '<i class="ri ri-check-line me-1"></i>Proses Redeem Stamp';
    }
  });

  // ── Modal Redeem Voucher ───────────────────────────────
  const voucherModal = new bootstrap.Modal(document.getElementById('rdmVoucherModal'));
  document.getElementById('rdmBtnVoucher').addEventListener('click', function () {
    if (!currentMemberId || !currentMemberInfo) return;
    const m = currentMemberInfo.member || {};
    document.getElementById('rdmVoucherMemberName').textContent = m.member_name || '—';

    const list     = document.getElementById('rdmVoucherList');
    const vouchers = currentMemberInfo.open_vouchers || [];
    if (vouchers.length === 0) {
      list.innerHTML = '<div class="rdm-empty-state text-muted py-3">Member ini tidak memiliki voucher aktif.</div>';
    } else {
      list.innerHTML = vouchers.map(v => {
        const benefit = v.voucher_type === 'PERCENT'
          ? v.percent_snapshot + '%'
          : (v.amount_snapshot ? fmtMoney(v.amount_snapshot) : (v.discount_value ? fmtMoney(v.discount_value) : '—'));
        const exp = v.expired_at ? 'Berlaku sampai ' + fmtDate(v.expired_at) : 'Tidak ada kadaluarsa';
        return `<div class="rdm-vch-row" data-id="${v.id}" data-code="${v.voucher_code || ''}" data-desc="${(v.campaign_name || 'Voucher') + ' — Benefit: ' + benefit}" data-exp="${exp}">
          <div style="flex:1 1 0;min-width:0">
            <div class="rdm-vch-code">${v.voucher_code || '(tanpa kode)'}</div>
            <div class="rdm-vch-desc">${v.campaign_name || '—'} &nbsp;·&nbsp; Benefit: <strong>${benefit}</strong></div>
            ${v.min_spend_amount ? `<div style="font-size:.65rem;color:#8a9a8a">Min. belanja: ${fmtMoney(v.min_spend_amount)}</div>` : ''}
          </div>
          <div class="rdm-vch-exp">${exp}</div>
          <button type="button" class="btn btn-success btn-sm rdm-vch-redeem-btn" data-id="${v.id}">
            <i class="ri ri-check-line"></i> Pakai
          </button>
        </div>`;
      }).join('');
    }
    voucherModal.show();
  });

  document.getElementById('rdmVoucherList').addEventListener('click', function (e) {
    const btn = e.target.closest('.rdm-vch-redeem-btn');
    if (!btn) return;
    const row = btn.closest('.rdm-vch-row');
    if (!row) return;
    activeVoucherIssueId = parseInt(btn.dataset.id, 10);
    document.getElementById('rdmConfirmVchCode').textContent  = row.dataset.code || '—';
    document.getElementById('rdmConfirmVchDesc').textContent  = row.dataset.desc || '—';
    document.getElementById('rdmConfirmVchExp').textContent   = row.dataset.exp  || '';
    document.getElementById('rdmConfirmVchNotes').value       = '';
    const confirmModal = new bootstrap.Modal(document.getElementById('rdmVoucherConfirmModal'));
    confirmModal.show();
  });

  document.getElementById('rdmConfirmVchSubmitBtn').addEventListener('click', async function () {
    if (!activeVoucherIssueId) return;
    const btn = this; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses…';
    try {
      const res  = await fetch(redeemVoucherUrl, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
        member_id:        currentMemberId,
        voucher_issue_id: activeVoucherIssueId,
        notes: document.getElementById('rdmConfirmVchNotes').value.trim(),
      })});
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Gagal.');

      bootstrap.Modal.getInstance(document.getElementById('rdmVoucherConfirmModal'))?.hide();
      voucherModal.hide();
      await loadMemberInfo(currentMemberId);
      loadLog(1);
      alert('Redeem voucher berhasil! No: ' + data.redeem_no);
    } catch (e) {
      alert('Error: ' + e.message);
    } finally {
      btn.disabled = false; btn.innerHTML = '<i class="ri ri-check-double-line me-1"></i>Konfirmasi Redeem';
    }
  });

})();
</script>
