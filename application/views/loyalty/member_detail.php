<?php
$m        = is_array($member ?? null) ? $member : [];
$pointBal = (float)($point_balance ?? 0);
$stampBal = (float)($stamp_balance ?? 0);
$vouchers = is_array($open_vouchers ?? null) ? $open_vouchers : [];

$memberId  = (int)($m['id'] ?? 0);
$tierLabel = html_escape((string)($m['member_tier'] ?? ''));
$statusMap = ['ACTIVE' => ['Aktif','success'], 'SUSPENDED' => ['Ditangguhkan','warning'], 'CLOSED' => ['Ditutup','danger']];
[$statusLabel, $statusColor] = $statusMap[$m['member_status'] ?? 'ACTIVE'] ?? ['—','secondary'];
$initial   = strtoupper(mb_substr(trim((string)($m['member_name'] ?? 'M')), 0, 1));
?>
<style>
/* ── Member card ── */
.mdet-card { border:1px solid #f0dfd2;border-radius:22px;background:#fff;box-shadow:0 14px 36px rgba(126,73,35,.07); }
.mdet-avatar { width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#8f353a,#c26060);color:#fff;font-size:1.7rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.mdet-bal-chip { display:inline-flex;flex-direction:column;align-items:center;gap:.05rem;background:#fffaf6;border:1px solid #f0dfd2;border-radius:16px;padding:.65rem 1.1rem;min-width:110px; }
.mdet-bal-chip .val { font-size:1.4rem;font-weight:800;line-height:1; }
.mdet-bal-chip .lbl { font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#999;margin-top:.1rem; }
.mdet-bal-chip.point .val { color:#7a5800; }
.mdet-bal-chip.stamp .val { color:#1a4a7a; }
.mdet-bal-chip.voucher .val { color:#1a5a3a; }
/* ── Tab ── */
.mdet-tab { border-bottom:2px solid #f0dfd2;margin-bottom:1.5rem; }
.mdet-tab-btn { background:none;border:none;border-bottom:3px solid transparent;padding:.5rem 1.1rem;font-size:.88rem;font-weight:700;color:#888;cursor:pointer;margin-bottom:-2px;transition:color .15s,border-color .15s; }
.mdet-tab-btn.active { color:#8f353a;border-bottom-color:#8f353a; }
/* ── Rule cards ── */
.rdc-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem; }
.rdc { border:1px solid #f0dfd2;border-radius:18px;background:#fff;overflow:hidden;box-shadow:0 6px 18px rgba(126,73,35,.06);display:flex;flex-direction:column;transition:box-shadow .15s,transform .1s; }
.rdc:hover { box-shadow:0 10px 28px rgba(126,73,35,.12);transform:translateY(-2px); }
.rdc.cant-afford { opacity:.52;filter:grayscale(.3); }
.rdc-head { padding:.85rem 1rem .5rem;border-bottom:1px solid #f5ece5; }
.rdc-name { font-size:.88rem;font-weight:700;color:#3a2520;line-height:1.3; }
.rdc-cost { font-size:.75rem;color:#7a5850;margin-top:.25rem; }
.rdc-reward { padding:.6rem 1rem;background:#f9f5f1;flex:1; }
.rdc-reward-label { font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9a7060;margin-bottom:.2rem; }
.rdc-reward-val { font-size:.9rem;font-weight:700;color:#3a2520; }
.rdc-footer { padding:.6rem 1rem;display:flex;align-items:center;justify-content:space-between;border-top:1px solid #f5ece5; }
.rdc-stock { font-size:.68rem;color:#aaa; }
.rdc-btn { font-size:.8rem;padding:.3rem .85rem;border-radius:9px;font-weight:700; }
/* ── History table ── */
.mdet-table thead th { font-size:.75rem;text-transform:uppercase;letter-spacing:.03em;color:#7a6055;border-bottom-color:#eddcd0; }
.mdet-table tbody td { font-size:.82rem;padding:.75rem .75rem;border-bottom-color:#f4e8df; }
/* ── Confirm modal ── */
#confirmModal .modal-header { background:linear-gradient(135deg,#7c1f2d 0%,#a03040 100%);color:#fff;border-radius:14px 14px 0 0;padding:.9rem 1.2rem; }
#confirmModal .modal-header .btn-close { filter:invert(1) brightness(2); }
.confirm-summary { background:#fdf8f5;border:1px solid #f0dfd2;border-radius:14px;padding:1rem 1.1rem; }
.confirm-row { display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid #f0e8df;font-size:.85rem; }
.confirm-row:last-child { border-bottom:none; }
.confirm-row .lk { color:#7a6055;font-weight:600; }
.confirm-row .rv { font-weight:700;color:#3a2520; }
/* ── Empty state ── */
.mdet-empty { text-align:center;padding:2.5rem 1rem;color:#bbb;font-size:.85rem; }
.mdet-empty i { font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4; }
</style>

<div class="container-xxl py-3">

  <!-- breadcrumb & back -->
  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?php echo site_url('loyalty/members'); ?>" class="btn btn-sm btn-outline-secondary">
      <i class="ri ri-arrow-left-line me-1"></i>Kembali
    </a>
    <span class="text-muted" style="font-size:.8rem">Member &amp; Loyalitas › Profil Member</span>
  </div>

  <?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => 'member']); ?>

  <!-- Member card -->
  <div class="mdet-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-start gap-3">
      <div class="mdet-avatar"><?php echo $initial; ?></div>
      <div class="flex-fill">
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
          <span class="fw-bold" style="font-size:1.15rem"><?php echo html_escape((string)($m['member_name'] ?? '—')); ?></span>
          <?php if ($tierLabel): ?>
            <span class="badge" style="background:#fff8e0;color:#7a5800;font-size:.65rem"><?php echo $tierLabel; ?></span>
          <?php endif; ?>
          <span class="badge bg-<?php echo $statusColor; ?>-subtle text-<?php echo $statusColor; ?>-emphasis" style="font-size:.65rem"><?php echo $statusLabel; ?></span>
        </div>
        <div class="d-flex flex-wrap gap-3" style="font-size:.8rem;color:#7a6055">
          <span><i class="ri ri-id-card-line me-1"></i><?php echo html_escape((string)($m['member_no'] ?? '—')); ?></span>
          <?php if (!empty($m['mobile_phone'])): ?>
            <span><i class="ri ri-phone-line me-1"></i><?php echo html_escape((string)$m['mobile_phone']); ?></span>
          <?php endif; ?>
          <?php if (!empty($m['email'])): ?>
            <span><i class="ri ri-mail-line me-1"></i><?php echo html_escape((string)$m['email']); ?></span>
          <?php endif; ?>
          <?php if (!empty($m['joined_at'])): ?>
            <span><i class="ri ri-calendar-line me-1"></i>Bergabung <?php echo date('d M Y', strtotime((string)$m['joined_at'])); ?></span>
          <?php endif; ?>
        </div>
      </div>
      <!-- Balance chips -->
      <div class="d-flex gap-2 flex-wrap">
        <div class="mdet-bal-chip point">
          <span class="val" id="chip-point"><?php echo number_format($pointBal, 0, ',', '.'); ?></span>
          <span class="lbl"><i class="ri ri-star-line"></i> Poin</span>
        </div>
        <div class="mdet-bal-chip stamp">
          <span class="val" id="chip-stamp"><?php echo number_format($stampBal, 0, ',', '.'); ?></span>
          <span class="lbl"><i class="ri ri-stamp-line"></i> Stamp</span>
        </div>
        <div class="mdet-bal-chip voucher">
          <span class="val"><?php echo count($vouchers); ?></span>
          <span class="lbl"><i class="ri ri-coupon-3-line"></i> Voucher</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="mdet-tab">
    <button class="mdet-tab-btn active" data-tab="redeem">Tukar Reward</button>
    <button class="mdet-tab-btn" data-tab="history">Riwayat Redeem</button>
  </div>

  <!-- ── Tab: Tukar Reward ── -->
  <div id="tab-redeem">
    <div id="rule-loading" class="text-center py-4 text-muted" style="font-size:.85rem">
      <div class="spinner-border spinner-border-sm text-secondary me-2"></div>Memuat katalog reward…
    </div>
    <div id="rule-grid" class="rdc-grid d-none"></div>
    <div id="rule-empty" class="mdet-empty d-none">
      <i class="ri ri-gift-line"></i>Belum ada reward yang tersedia. Tambahkan rule di <a href="<?php echo site_url('loyalty/redeem-rules'); ?>">Pengaturan Redeem</a>.
    </div>
  </div>

  <!-- ── Tab: Riwayat Redeem ── -->
  <div id="tab-history" class="d-none">
    <div class="mdet-card p-3">
      <table class="table mdet-table mb-0">
        <thead>
          <tr>
            <th>No. Redeem</th>
            <th>Kode Voucher</th>
            <th>Reward</th>
            <th>Poin / Stamp</th>
            <th>Tanggal</th>
          </tr>
        </thead>
        <tbody id="history-body">
          <tr><td colspan="5" class="text-center text-muted py-3">Memuat…</td></tr>
        </tbody>
      </table>
      <div class="d-flex justify-content-between align-items-center mt-2">
        <small id="history-info" class="text-muted"></small>
        <div id="history-pg" class="d-flex gap-1"></div>
      </div>
    </div>
  </div>

</div>

<!-- ── Confirm Redeem Modal ── -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <i class="ri ri-exchange-line" style="font-size:1.2rem"></i>
          <h6 class="modal-title mb-0 fw-bold">Konfirmasi Penukaran</h6>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4 py-3">
        <div class="confirm-summary mb-3" id="confirm-summary"></div>
        <label class="form-label fw-semibold" style="font-size:.8rem">Catatan (opsional)</label>
        <textarea id="confirm-notes" class="form-control form-control-sm" rows="2" placeholder="Catatan tambahan untuk transaksi ini…"></textarea>
      </div>
      <div class="modal-footer" style="border-top:1px solid #f0dfd2;padding:.75rem 1.2rem">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btn-confirm-redeem" class="btn btn-primary btn-sm px-4">
          <i class="ri ri-check-line me-1"></i><span id="confirm-btn-lbl">Tukar Sekarang</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Voucher Success Modal ── -->
<div class="modal fade" id="voucherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#1a6b3a 0%,#28a058 100%);color:#fff;border-radius:14px 14px 0 0;padding:.9rem 1.2rem">
        <h6 class="modal-title fw-bold mb-0"><i class="ri ri-checkbox-circle-line me-2"></i>Redeem Berhasil!</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1) brightness(2)"></button>
      </div>
      <div class="modal-body px-4 py-4 text-center">
        <p class="text-muted mb-2" style="font-size:.82rem">Kode Voucher untuk Customer:</p>
        <div id="voucher-code-box" style="background:#f0fdf4;border:2px dashed #28a058;border-radius:12px;padding:.9rem 1.2rem;margin-bottom:1rem">
          <span id="voucher-code-display" style="font-size:1.5rem;font-weight:800;letter-spacing:.12em;color:#1a6b3a;font-family:monospace"></span>
        </div>
        <p id="voucher-desc-display" class="fw-semibold mb-1" style="font-size:.88rem;color:#1a3a5a"></p>
        <p class="text-muted mb-0" style="font-size:.78rem">Tunjukkan kode ini ke kasir saat pembayaran.</p>
      </div>
      <div class="modal-footer justify-content-center" style="border-top:1px solid #d4edda;padding:.75rem">
        <button type="button" class="btn btn-success btn-sm px-5" data-bs-dismiss="modal">OK, Mengerti</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const memberId      = <?php echo $memberId; ?>;
  const rulesUrl      = <?php echo json_encode(site_url('loyalty/members/' . $memberId . '/redeem/rules')); ?>;
  const processUrl    = <?php echo json_encode(site_url('loyalty/members/' . $memberId . '/redeem/process')); ?>;
  const historyUrl    = <?php echo json_encode(site_url('loyalty/members/' . $memberId . '/redeem/history')); ?>;

  const REWARD_ICON = {
    DISCOUNT_AMOUNT:  '💰', DISCOUNT_PERCENT: '📉',
    VOUCHER:          '🎫', PRODUCT:          '📦',
    FREE_PRODUCT:     '🎁', MERCHANDISE:      '🛍️', OTHER: '✨'
  };
  const REWARD_LABEL = {
    DISCOUNT_AMOUNT:'Diskon (Rp)',DISCOUNT_PERCENT:'Diskon (%)',
    VOUCHER:'Voucher',PRODUCT:'Produk',FREE_PRODUCT:'Produk Gratis',
    MERCHANDISE:'Merchandise',OTHER:'Reward Lainnya'
  };
  const COST_LABEL = { POINT:'Poin', STAMP:'Stamp', BOTH:'Poin + Stamp' };

  const fmtNum  = v => Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:0});
  const fmtMon  = v => 'Rp ' + Number(v||0).toLocaleString('id-ID');
  const esc     = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

  async function postJson(url, payload) {
    const r = await fetch(url, {method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.message || 'Error');
    return j;
  }
  async function getJson(url) {
    const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.message || 'Error');
    return j;
  }

  // ── Tabs ──
  let activeTab = 'redeem';
  document.querySelectorAll('.mdet-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.mdet-tab-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      activeTab = this.dataset.tab;
      document.getElementById('tab-redeem').classList.toggle('d-none', activeTab !== 'redeem');
      document.getElementById('tab-history').classList.toggle('d-none', activeTab !== 'history');
      if (activeTab === 'history' && !historyLoaded) loadHistory(1);
    });
  });

  // ── Rule catalog ──
  let currentRules = [];
  let currentPointBal = <?php echo $pointBal; ?>;
  let currentStampBal = <?php echo $stampBal; ?>;

  function rewardValue(rule) {
    const rt = rule.reward_type;
    if (rt === 'DISCOUNT_AMOUNT') return fmtMon(rule.discount_amount);
    if (rt === 'DISCOUNT_PERCENT') return (rule.discount_percent || 0) + '%';
    if (rt === 'VOUCHER') return esc(rule.voucher_campaign_name || 'Voucher dari campaign');
    if (rt === 'PRODUCT' || rt === 'FREE_PRODUCT') return (rule.product_qty || 1) + ' unit produk';
    return esc(rule.reward_notes || '—');
  }

  function costText(rule) {
    const parts = [];
    if (rule.cost_type === 'POINT' || rule.cost_type === 'BOTH')
      parts.push(`<strong>${fmtNum(rule.point_cost)}</strong> poin`);
    if (rule.cost_type === 'STAMP' || rule.cost_type === 'BOTH')
      parts.push(`<strong>${fmtNum(rule.stamp_cost)}</strong> stamp`);
    return parts.join(' + ');
  }

  function renderRules(rules) {
    const grid = document.getElementById('rule-grid');
    const empty = document.getElementById('rule-empty');
    document.getElementById('rule-loading').classList.add('d-none');
    if (!rules.length) { empty.classList.remove('d-none'); return; }
    grid.classList.remove('d-none');
    grid.innerHTML = rules.map((rule, idx) => {
      const cantAfford = !rule.can_afford;
      const stockText  = rule.stock_qty != null
        ? `Sisa ${Number(rule.stock_qty - (rule.redeemed_count||0)).toLocaleString('id-ID')} buah`
        : 'Stok tidak terbatas';
      const validText  = rule.valid_days ? `Berlaku ${rule.valid_days} hari` : '';
      return `<div class="rdc ${cantAfford ? 'cant-afford' : ''}">
        <div class="rdc-head">
          <div class="rdc-name">${esc(rule.rule_name)}</div>
          <div class="rdc-cost"><i class="ri ri-coins-line me-1"></i>Biaya: ${costText(rule)}</div>
        </div>
        <div class="rdc-reward">
          <div class="rdc-reward-label">${REWARD_ICON[rule.reward_type]||'✨'} ${REWARD_LABEL[rule.reward_type]||rule.reward_type}</div>
          <div class="rdc-reward-val">${rewardValue(rule)}</div>
        </div>
        <div class="rdc-footer">
          <span class="rdc-stock">${stockText}${validText ? ' · ' + validText : ''}</span>
          <button class="btn btn-primary rdc-btn ${cantAfford ? 'disabled' : ''}"
            ${cantAfford ? 'disabled' : ''}
            data-idx="${idx}" data-id="${rule.id}">
            ${cantAfford ? 'Saldo Kurang' : 'Tukar'}
          </button>
        </div>
      </div>`;
    }).join('');
  }

  async function loadRules() {
    try {
      const d = await getJson(rulesUrl);
      currentRules = d.rules || [];
      currentPointBal = d.point_balance;
      currentStampBal = d.stamp_balance;
      renderRules(currentRules);
    } catch(e) {
      document.getElementById('rule-loading').textContent = 'Gagal memuat: ' + e.message;
    }
  }
  loadRules();

  // ── Confirm & Voucher modals ──
  const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
  const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'));
  let selectedRuleId = 0;

  document.getElementById('rule-grid').addEventListener('click', function(e) {
    const btn = e.target.closest('.rdc-btn:not(.disabled)');
    if (!btn) return;
    const idx  = parseInt(btn.dataset.idx);
    const rule = currentRules[idx];
    if (!rule) return;
    selectedRuleId = rule.id;
    document.getElementById('confirm-notes').value = '';

    const costParts = [];
    if (rule.cost_type === 'POINT' || rule.cost_type === 'BOTH')
      costParts.push(`${fmtNum(rule.point_cost)} poin`);
    if (rule.cost_type === 'STAMP' || rule.cost_type === 'BOTH')
      costParts.push(`${fmtNum(rule.stamp_cost)} stamp`);

    document.getElementById('confirm-summary').innerHTML = `
      <div class="confirm-row"><span class="lk">Rule</span><span class="rv">${esc(rule.rule_name)}</span></div>
      <div class="confirm-row"><span class="lk">Biaya</span><span class="rv">${costParts.join(' + ')}</span></div>
      <div class="confirm-row"><span class="lk">Reward</span><span class="rv">${REWARD_ICON[rule.reward_type]||''} ${rewardValue(rule)}</span></div>
      <div class="confirm-row"><span class="lk">Saldo Poin Sesudah</span><span class="rv text-warning">${fmtNum(currentPointBal - (rule.cost_type !== 'STAMP' ? (rule.point_cost||0) : 0))} poin</span></div>
      ${rule.cost_type !== 'POINT' ? `<div class="confirm-row"><span class="lk">Saldo Stamp Sesudah</span><span class="rv text-primary">${fmtNum(currentStampBal - (rule.stamp_cost||0))} stamp</span></div>` : ''}
    `;
    confirmModal.show();
  });

  document.getElementById('btn-confirm-redeem').addEventListener('click', async function() {
    if (!selectedRuleId) return;
    const btn = this;
    const lbl = document.getElementById('confirm-btn-lbl');
    btn.disabled = true; lbl.textContent = 'Memproses…';
    try {
      const res = await postJson(processUrl, {
        rule_id: selectedRuleId,
        notes:   document.getElementById('confirm-notes').value.trim(),
      });
      confirmModal.hide();
      // Update balance chips
      currentPointBal = res.point_balance;
      currentStampBal = res.stamp_balance;
      document.getElementById('chip-point').textContent = fmtNum(res.point_balance);
      document.getElementById('chip-stamp').textContent = fmtNum(res.stamp_balance);
      // Reload rules to refresh can_afford flags
      await loadRules();
      // Mark history dirty
      historyLoaded = false;
      // Tampilkan kode voucher
      document.getElementById('voucher-code-display').textContent = res.voucher_code || res.redeem_no;
      document.getElementById('voucher-desc-display').textContent = res.voucher_desc || '';
      voucherModal.show();
    } catch(e) {
      alert('Gagal: ' + e.message);
    } finally {
      btn.disabled = false; lbl.textContent = 'Tukar Sekarang';
    }
  });

  // ── Riwayat ──
  let historyLoaded = false;
  let historyPage   = 1;

  function typeLabel(t) {
    return {POINT:'<span class="badge bg-warning-subtle text-warning-emphasis">Poin</span>',STAMP:'<span class="badge bg-primary-subtle text-primary-emphasis">Stamp</span>',VOUCHER:'<span class="badge bg-success-subtle text-success-emphasis">Voucher</span>'}[t] || t;
  }
  function fmtDt(v) {
    return v ? new Date(v.replace(' ','T')).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';
  }

  async function loadHistory(pg) {
    historyPage = pg;
    const tbody = document.getElementById('history-body');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Memuat…</td></tr>';
    try {
      const d = await getJson(historyUrl + '?page=' + pg);
      const rows = d.rows || [];
      const meta = d.meta || {};
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat redeem</td></tr>';
      } else {
        tbody.innerHTML = rows.map(r => `<tr>
          <td><span class="fw-semibold" style="font-size:.78rem;color:#7c1f2d">${esc(r.redeem_no||'')}</span></td>
          <td>
            ${r.voucher_code
              ? `<span style="font-size:.8rem;font-weight:700;letter-spacing:.06em;font-family:monospace;color:#1a6b3a;background:#f0fdf4;padding:.15rem .4rem;border-radius:6px;border:1px solid #86efac">${esc(r.voucher_code)}</span>`
              : '<span class="text-muted" style="font-size:.78rem">—</span>'}
          </td>
          <td style="font-size:.8rem">${esc(r.reward_desc||r.reward_type||'—')}</td>
          <td style="font-size:.78rem;color:#7a6055">
            ${r.points_used ? fmtNum(r.points_used)+' poin' : ''}
            ${r.stamps_used ? fmtNum(r.stamps_used)+' stamp' : ''}
          </td>
          <td style="font-size:.78rem">${fmtDt(r.created_at)}</td>
        </tr>`).join('');
      }
      document.getElementById('history-info').textContent =
        `${rows.length} dari ${(meta.total||0).toLocaleString('id-ID')} transaksi`;
      const pgEl = document.getElementById('history-pg');
      pgEl.innerHTML = '';
      if ((meta.total_pages||1) > 1) {
        if (meta.page > 1) {
          const b = document.createElement('button');
          b.className='btn btn-outline-secondary btn-sm'; b.textContent='‹';
          b.onclick=()=>loadHistory(pg-1); pgEl.appendChild(b);
        }
        if (meta.page < meta.total_pages) {
          const b = document.createElement('button');
          b.className='btn btn-outline-secondary btn-sm'; b.textContent='›';
          b.onclick=()=>loadHistory(pg+1); pgEl.appendChild(b);
        }
      }
      historyLoaded = true;
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">Gagal: ${esc(e.message)}</td></tr>`;
    }
  }
});
</script>
