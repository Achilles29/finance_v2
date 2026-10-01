<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
?>

<style>
  .self-order-shell { display:grid; gap:1rem; }
  .self-order-card { border:0; border-radius:22px; box-shadow:0 18px 40px rgba(58,38,30,.08); }
  .self-order-filter-card { border:1px solid rgba(224,209,198,.72); border-radius:18px; padding:1rem 1.05rem; background:#fffdfb; }
  .self-order-pill-row { display:flex; flex-wrap:wrap; gap:.55rem; }
  .self-order-type-pill,
  .self-order-page-pill {
    display:inline-flex; align-items:center; justify-content:center; gap:.38rem;
    min-height:38px; padding:.45rem .92rem; border-radius:999px; border:1px solid rgba(191,173,160,.9);
    font-size:.86rem; font-weight:700; cursor:pointer;
  }
  .self-order-type-pill {
    background:#eef3f1;
    border-color:#d6e0dc;
    color:#34534f;
  }
  .self-order-type-pill.is-active {
    background:#1f5d54;
    border-color:#1f5d54;
    color:#fff;
    box-shadow:0 12px 24px rgba(31,93,84,.16);
  }
  .self-order-page-pill {
    background:#efe8e2;
    border-color:#cec2b8;
    color:#544740;
  }
  .self-order-page-pill.is-active {
    background:#2f2a4f;
    border-color:#2f2a4f;
    color:#fff;
    box-shadow:0 12px 24px rgba(47,42,79,.16);
  }
  .self-order-pill-count { font-size:.72rem; opacity:.88; }
  .self-order-section-label { font-size:.72rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#8a776d; margin-bottom:.5rem; }
  .self-order-summary-grid { display:grid; grid-template-columns:repeat(5, minmax(0,1fr)); gap:.75rem; }
  .self-order-summary-card {
    border:1px solid rgba(224,209,198,.72); border-radius:16px; padding:.85rem .95rem;
    background:linear-gradient(135deg,#fffaf6 0%,#fff 100%);
  }
  .self-order-summary-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#8a776d; margin-bottom:.15rem; }
  .self-order-summary-value { font-size:1.3rem; font-weight:900; color:#36292a; }
  .self-order-summary-note { font-size:.76rem; color:#8b7a70; }
  .self-order-empty {
    border:1px dashed rgba(189,170,154,.6); border-radius:16px; padding:1.4rem; text-align:center;
    color:#8b7a70; background:#fffaf6;
  }
  .self-order-table-region.is-loading .self-order-table { opacity:.48; pointer-events:none; transition:opacity .16s ease; }
  .self-order-list-state { border:1px solid rgba(224,209,198,.82); border-radius:16px; padding:.9rem 1rem; color:#78655d; background:#fffdfb; text-align:center; }
  .self-order-list-state.is-error { border-color:#efb7bc; color:#9e2936; background:#fff5f5; }
  .self-order-status-chip, .self-order-payment-chip {
    display:inline-flex; align-items:center; gap:.3rem; padding:.22rem .58rem; border-radius:999px;
    font-size:.72rem; font-weight:800; white-space:nowrap;
  }
  .self-order-status-chip.verify { background:#fff4dd; color:#8d5a00; }
  .self-order-status-chip.waiting { background:#e0f2fe; color:#075985; }
  .self-order-status-chip.active { background:#ede9fe; color:#5b21b6; }
  .self-order-status-chip.paid { background:#dcfce7; color:#166534; }
  .self-order-status-chip.rejected { background:#fee2e2; color:#991b1b; }
  .self-order-payment-chip.cashier { background:#fff4dd; color:#8d5a00; }
  .self-order-payment-chip.qris { background:#ede9fe; color:#5b21b6; }
  .self-order-subtle { font-size:.78rem; color:#8b7a70; }
  .self-order-table td { vertical-align:middle; }
  .self-order-table th { white-space:nowrap; font-size:.78rem; }
  .self-order-order-no { font-weight:800; color:#33272a; }
  .self-order-action-stack { display:flex; flex-wrap:wrap; gap:.4rem; justify-content:center; }
  .self-order-detail-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:.65rem; }
  .self-order-detail-item { border:1px solid rgba(224,209,198,.72); border-radius:12px; padding:.65rem .75rem; background:#fffaf6; }
  .self-order-detail-label { font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:#8b7a70; margin-bottom:.1rem; }
  .self-order-detail-value { font-size:.86rem; font-weight:700; color:#3c2d2d; line-height:1.35; }
  .self-order-line-card, .self-order-payment-card {
    border:1px solid rgba(224,209,198,.7); border-radius:14px; padding:.75rem .85rem; background:#fffdfb;
  }
  .self-order-line-card + .self-order-line-card, .self-order-payment-card + .self-order-payment-card { margin-top:.6rem; }
  .self-order-bundle-card { border:1px solid rgba(194,34,43,.22); border-radius:15px; padding:.75rem; background:#fff9f5; }
  .self-order-bundle-title { color:#8f1924; font-size:.78rem; font-weight:900; letter-spacing:.035em; text-transform:uppercase; }
  .self-order-bundle-code { color:#9a7f70; font-size:.7rem; margin-top:.12rem; }
  .self-order-bundle-lines { display:grid; gap:.45rem; margin-top:.65rem; }
  .self-order-bundle-lines .self-order-line-card { border-color:rgba(224,209,198,.85); box-shadow:none; margin-top:0; }
  .self-order-line-card.is-bundle-item { padding-left:1rem; }
  .self-order-line-preview { display:grid; gap:.6rem; }
  .self-order-extra-list { margin-top:.45rem; display:grid; gap:.35rem; }
  .self-order-extra-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.18rem .5rem; border-radius:999px; background:#fff1e8; color:#9a4e0f; font-size:.72rem; font-weight:700; margin-right:.35rem; }
  .self-order-modal-note {
    border:1px solid rgba(224,209,198,.7); border-radius:14px; padding:.75rem .85rem; background:#fff7f2; color:#755f56;
  }
  .self-order-toast-wrap {
    position:fixed; top:88px; right:24px; z-index:2000; display:grid; gap:.55rem; width:min(340px, calc(100vw - 32px));
  }
  .self-order-toast {
    border-radius:16px; padding:.8rem .95rem; box-shadow:0 16px 30px rgba(61,38,27,.16);
    color:#fff; font-size:.86rem; font-weight:600;
  }
  .self-order-toast.success { background:#176b3a; }
  .self-order-toast.info { background:#2f4b8f; }
  .self-order-toast.warning { background:#9a4e0f; }
  .self-order-btn-spinner {
    width:1rem; height:1rem; border:.15em solid currentColor; border-right-color:transparent;
    border-radius:50%; display:inline-block; animation:selfOrderSpin .7s linear infinite;
  }
  @keyframes selfOrderSpin { to { transform:rotate(360deg); } }
  @media (max-width: 991.98px) {
    .self-order-summary-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .self-order-detail-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
  }
  @media (max-width: 575.98px) {
    .self-order-summary-grid, .self-order-detail-grid { grid-template-columns:1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'self-order']); ?>
  <?php $this->load->view('pos/_self_order_tabs', ['self_order_tab_active' => 'orders']); ?>

  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Orderan Self Order</h4>
      <p class="fin-page-subtitle mb-0">Pantau order meja, dengarkan notifikasi order masuk, verifikasi, lalu cetak KOT sesuai pengaturan outlet.</p>
    </div>
  </div>

  <div class="self-order-shell">
    <div class="card self-order-card">
      <div class="card-body p-4">
        <div class="self-order-filter-card mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-lg-4">
              <label class="form-label small text-muted mb-1">Cari Order</label>
              <input type="text" class="form-control" id="self_order_q" placeholder="Order no, customer, nomor HP, meja, atau metode bayar">
            </div>
            <div class="col-md-3 col-lg-2">
              <label class="form-label small text-muted mb-1">Outlet</label>
              <select class="form-select" id="self_order_outlet_id">
                <option value="0">Semua Outlet</option>
                <?php foreach ($outlets as $outlet): ?>
                  <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 col-lg-2">
              <label class="form-label small text-muted mb-1">Tanggal Awal</label>
              <input type="date" class="form-control" id="self_order_date_from" value="<?php echo html_escape((string)($filters['date_from'] ?? date('Y-m-d'))); ?>">
            </div>
            <div class="col-md-3 col-lg-2">
              <label class="form-label small text-muted mb-1">Tanggal Akhir</label>
              <input type="date" class="form-control" id="self_order_date_to" value="<?php echo html_escape((string)($filters['date_to'] ?? date('Y-m-d'))); ?>">
            </div>
            <div class="col-md-3 col-lg-2">
              <label class="form-label small text-muted mb-1">Baris</label>
              <select class="form-select" id="self_order_limit">
                <?php foreach ([10, 20, 50, 100] as $rowLimit): ?>
                  <option value="<?php echo $rowLimit; ?>" <?php echo (int)($filters['limit'] ?? 20) === $rowLimit ? 'selected' : ''; ?>><?php echo $rowLimit; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-lg-2 d-grid">
              <button type="button" class="btn btn-outline-danger" id="self_order_reset_filter">Reset Filter</button>
            </div>
          </div>

          <div class="mt-3">
            <div class="self-order-section-label">Mode Pembayaran</div>
            <div class="self-order-pill-row" id="self_order_payment_tabs"></div>
          </div>
          <div class="mt-3">
            <div class="self-order-section-label">Status Flow Order</div>
            <div class="self-order-pill-row" id="self_order_status_tabs"></div>
          </div>
        </div>

        <div class="self-order-summary-grid mb-3" id="self_order_summary_grid"></div>

        <div class="table-responsive self-order-table-region" id="self_order_table_region" aria-busy="false">
          <table class="table table-sm align-middle table-hover self-order-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Pembayaran</th>
                <th>Status Flow</th>
                <th class="text-end">Nominal</th>
                <th class="text-center" style="width:240px;">Aksi</th>
              </tr>
            </thead>
            <tbody id="self_order_body"></tbody>
          </table>
        </div>
        <div id="self_order_empty_state" class="self-order-empty d-none" role="status" aria-live="polite">Belum ada order self order pada filter ini.</div>
        <div id="self_order_list_state" class="self-order-list-state d-none mt-3" role="status" aria-live="polite"></div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <small id="self_order_pagination_info" class="text-muted" aria-live="polite"></small>
          <div class="d-flex gap-1" id="self_order_pagination" aria-label="Navigasi halaman self order"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="self-order-toast-wrap" id="self_order_toast_wrap"></div>
<audio id="self_order_notify_audio" preload="auto" class="d-none">
  <source src="<?php echo base_url('assets/sounds/notifikasi.mp3'); ?>" type="audio/mpeg">
</audio>

<div class="modal fade" id="selfOrderDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Detail Order Self Order</h5>
          <div class="small text-muted" id="self_order_detail_meta">Order belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="self-order-detail-grid mb-3" id="self_order_detail_grid"></div>
        <div class="self-order-modal-note mb-3" id="self_order_detail_note">Belum ada catatan order.</div>
        <div class="row g-3">
          <div class="col-lg-7">
            <div class="self-order-section-label mb-2">Line Order</div>
            <div id="self_order_detail_lines"></div>
          </div>
          <div class="col-lg-5">
            <div class="self-order-section-label mb-2">Jejak Pembayaran</div>
            <div id="self_order_detail_payments"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="selfOrderVerifyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi Order Self Order</h5>
          <div class="small text-muted" id="self_order_verify_meta">Siapkan order untuk dicetak dan diproses.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="self-order-detail-grid mb-3" id="self_order_verify_grid"></div>
        <div class="mb-3 d-none" id="self_order_verify_destination_wrap">
          <div class="self-order-modal-note">
            <div class="fw-semibold text-dark mb-1">Tujuan Verifikasi</div>
            <div id="self_order_verify_destination_note">Order QRIS yang sudah lunas akan langsung masuk ke workspace pesanan terbayar setelah diverifikasi.</div>
          </div>
        </div>
        <div class="self-order-section-label mb-2">Preview Line Order</div>
        <div class="self-order-line-preview" id="self_order_verify_lines">
          <div class="self-order-empty">Memuat line order...</div>
        </div>
        <div class="self-order-modal-note" id="self_order_verify_hint">Order yang diverifikasi akan langsung dicetak sesuai setting printer outlet.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="self_order_submit_verify">Verifikasi &amp; Cetak</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="selfOrderRejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Tolak Order Self Order</h5>
          <div class="small text-muted" id="self_order_reject_meta">Order belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="self-order-detail-grid mb-3" id="self_order_reject_grid"></div>
        <div class="self-order-modal-note mb-3" id="self_order_reject_hint">Order yang ditolak tidak akan masuk ke workspace kasir.</div>
        <div>
          <label class="form-label small text-muted mb-1" for="self_order_reject_reason">Alasan Penolakan</label>
          <textarea class="form-control" id="self_order_reject_reason" rows="4" placeholder="Contoh: menu habis, outlet tutup, atau pesanan tidak bisa diproses."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="self_order_submit_reject">Tolak Transaksi</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="selfOrderInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <h5 class="modal-title" id="self_order_info_title">Informasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted" id="self_order_info_message">-</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const posTransactionCsrfToken = <?php echo json_encode((string)($pos_transaction_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const state = {
    q: <?php echo json_encode((string)($filters['q'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    outlet_id: <?php echo (int)($filters['outlet_id'] ?? 0); ?>,
    payment_tab: <?php echo json_encode((string)($filters['payment_tab'] ?? 'ALL')); ?>,
    status_tab: <?php echo json_encode((string)($filters['status_tab'] ?? 'NEEDS_VERIFY')); ?>,
    date_from: <?php echo json_encode((string)($filters['date_from'] ?? date('Y-m-d'))); ?>,
    date_to: <?php echo json_encode((string)($filters['date_to'] ?? date('Y-m-d'))); ?>,
    page: <?php echo (int)($filters['page'] ?? 1); ?>,
    limit: <?php echo (int)($filters['limit'] ?? 20); ?>,
  };
  const paymentTabDefs = [
    { key: 'ALL', label: 'Semua Mode' },
    { key: 'KASIR', label: 'Bayar di Kasir' },
    { key: 'QRIS', label: 'QRIS' }
  ];
  const statusTabDefs = [
    { key: 'ALL', label: 'Semua Flow' },
    { key: 'NEEDS_VERIFY', label: 'Perlu Verifikasi' },
    { key: 'WAITING_PAYMENT', label: 'Menunggu QRIS' },
    { key: 'ACTIVE_CASHIER', label: 'Order Aktif' },
    { key: 'PAID_ORDER', label: 'Terbayar' },
    { key: 'REJECTED', label: 'Ditolak' }
  ];
  const summaryDefs = [
    { key: 'ALL', label: 'Total Self Order', note: 'Semua order sesuai filter tanggal/outlet.' },
    { key: 'NEEDS_VERIFY', label: 'Perlu Verifikasi', note: 'Siap diproses kasir dan siap cetak KOT.' },
    { key: 'WAITING_PAYMENT', label: 'Menunggu QRIS', note: 'Customer belum menyelesaikan pembayaran QRIS.' },
    { key: 'ACTIVE_CASHIER', label: 'Masuk Order Aktif', note: 'Sudah diverifikasi, menunggu settlement di kasir.' },
    { key: 'PAID_ORDER', label: 'Masuk Terbayar', note: 'Sudah lunas dan ikut workspace order terbayar.' },
    { key: 'REJECTED', label: 'Ditolak', note: 'Transaksi dibatalkan sebelum diproses kasir.' }
  ];

  let summaryCounts = { ALL: 0, NEEDS_VERIFY: 0, WAITING_PAYMENT: 0, ACTIVE_CASHIER: 0, PAID_ORDER: 0, REJECTED: 0 };
  let verifyRow = null;
  let verifyBusy = false;
  let rejectRow = null;
  let rejectBusy = false;
  let listSearchTimer = null;
  let listRequestController = null;
  let listRequestId = 0;
  const orderDetailCache = new Map();
  let incomingPollBusy = false;
  let incomingBaselineReady = false;
  let incomingSnapshot = '';
  let audioReady = false;
  const seenIncomingOrderIds = new Set();
  const notifyAudio = document.getElementById('self_order_notify_audio');

  const detailModalEl = document.getElementById('selfOrderDetailModal');
  const verifyModalEl = document.getElementById('selfOrderVerifyModal');
  const rejectModalEl = document.getElementById('selfOrderRejectModal');
  const infoModalEl = document.getElementById('selfOrderInfoModal');
  const detailModal = (detailModalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(detailModalEl) : null;
  const verifyModal = (verifyModalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(verifyModalEl) : null;
  const rejectModal = (rejectModalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(rejectModalEl) : null;
  const infoModal = (infoModalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(infoModalEl) : null;

  function showModal(modalEl, modalInstance) {
    if (modalInstance && typeof modalInstance.show === 'function') {
      modalInstance.show();
      return;
    }
    if (window.jQuery && modalEl) {
      window.jQuery(modalEl).modal('show');
      return;
    }
  }

  function hideModal(modalEl, modalInstance) {
    if (modalInstance && typeof modalInstance.hide === 'function') {
      modalInstance.hide();
      return;
    }
    if (window.jQuery && modalEl) {
      window.jQuery(modalEl).modal('hide');
      return;
    }
  }

  function money(value) {
    const number = Number(value || 0);
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getJson(url, signal = null) {
    return fetch(url, { credentials: 'same-origin', signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(async (res) => {
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch (e) { json = null; }
      if (!res.ok || !json || json.ok === false) {
        throw new Error(json && json.message ? json.message : (text || ('HTTP ' + res.status)));
      }
      return json;
    });
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    }).then(async (res) => {
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch (e) { json = null; }
      if (!res.ok || !json || json.ok === false) {
        throw new Error(json && json.message ? json.message : (text || ('HTTP ' + res.status)));
      }
      return json;
    });
  }

  function postPosTransactionJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Pos-Transaction-CSRF': posTransactionCsrfToken },
      body: JSON.stringify(payload || {})
    }).then(async (res) => {
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch (e) { json = null; }
      if (!res.ok || !json || json.ok === false) {
        throw new Error(json && json.message ? json.message : (text || ('HTTP ' + res.status)));
      }
      return json;
    });
  }

  function showToast(message, type = 'info') {
    const wrap = document.getElementById('self_order_toast_wrap');
    if (!wrap) return;
    const toast = document.createElement('div');
    toast.className = `self-order-toast ${type}`;
    toast.textContent = message;
    wrap.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-6px)';
      toast.style.transition = 'all .2s ease';
      setTimeout(() => toast.remove(), 220);
    }, 2600);
  }

  function unlockNotifyAudio() {
    audioReady = true;
    if (!notifyAudio) return;
    try {
      notifyAudio.volume = 1;
      const playPromise = notifyAudio.play();
      if (playPromise && typeof playPromise.then === 'function') {
        playPromise.then(() => {
          notifyAudio.pause();
          notifyAudio.currentTime = 0;
        }).catch(() => {});
      } else {
        notifyAudio.pause();
        notifyAudio.currentTime = 0;
      }
    } catch (e) {
      // ignore autoplay guard; next user action will keep audio unlocked if browser allows it
    }
  }

  function playIncomingSound() {
    if (!notifyAudio || !audioReady) {
      return;
    }
    try {
      notifyAudio.pause();
      notifyAudio.currentTime = 0;
      const playPromise = notifyAudio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(() => {});
      }
    } catch (e) {
      // ignore browser audio restrictions
    }
  }

  function showInfoModal(message, title = 'Informasi') {
    document.getElementById('self_order_info_title').textContent = title;
    document.getElementById('self_order_info_message').innerHTML = String(message || '').replace(/\n/g, '<br>');
    if (infoModal || (window.jQuery && infoModalEl)) {
      showModal(infoModalEl, infoModal);
      return;
    }
    alert(message);
  }

  function normalizePrintFailureEntry(entry) {
    if (entry && typeof entry === 'object') {
      return {
        name: String(entry.name || entry.printer_name || 'Printer').trim() || 'Printer',
        reason: String(entry.reason || entry.message || 'gagal cetak').trim() || 'gagal cetak'
      };
    }
    const raw = String(entry || '').trim();
    const separatorIndex = raw.indexOf(':');
    if (separatorIndex > 0) {
      return {
        name: raw.slice(0, separatorIndex).trim() || 'Printer',
        reason: raw.slice(separatorIndex + 1).trim() || 'gagal cetak'
      };
    }
    return { name: 'Printer', reason: raw || 'gagal cetak' };
  }

  async function acknowledgeDirectPrintTarget(target, status, message) {
    const attemptId = Number(target && target.print_attempt_id || 0);
    if (!attemptId) return;
    try {
      await fetch('<?= site_url('pos/printers/attempts/ack') ?>', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({attempt_id: attemptId, status, message: message || ''}) });
    } catch (error) { /* Monitor tidak boleh menghambat self order. */ }
  }

  async function directPrintTargets(rows) {
    if (!Array.isArray(rows) || !rows.length) {
      return { successCount: 0, failed: [] };
    }
    const failed = [];
    let successCount = 0;
    const jobs = [];
    for (const target of rows) {
      const printMode = String(target.print_mode || 'AUTO').toUpperCase();
      if (printMode === 'OFF') {
        void acknowledgeDirectPrintTarget(target, 'SKIPPED', 'Aturan cetak sedang tidak aktif.');
        continue;
      }
      if (printMode === 'ASK') {
        const approved = await window.PosDirectPrintPrompt.ask(target);
        if (!approved) {
          void acknowledgeDirectPrintTarget(target, 'SKIPPED', 'Cetak dilewati oleh kasir.');
          continue;
        }
      }
      const copies = Math.max(1, Number(target.copies || 1));
      const pythonPort = Number(target.python_port || 0);
      if (!pythonPort) {
        const message = 'python port belum valid';
        failed.push(`${target.printer_name || target.printer_code || 'Printer'}: ${message}`);
        void acknowledgeDirectPrintTarget(target, 'FAILED', message);
        continue;
      }
      jobs.push(window.PosDirectAgentPrint.send(target).then((body) => {
        successCount += copies;
        void acknowledgeDirectPrintTarget(target, 'SENT', body.message || 'Perintah diterima Local Agent.');
      }).catch((e) => {
        const message = e && e.message ? e.message : 'gagal cetak';
        failed.push(`${target.printer_name || target.printer_code || 'Printer'}: ${message}`);
        void acknowledgeDirectPrintTarget(target, 'FAILED', message);
      }));
    }
    await Promise.all(jobs);
    return { successCount, failed };
  }

  async function kickoffRuntimeJobSync(orderId, jobId) {
    const safeOrderId = Number(orderId || 0);
    const safeJobId = Number(jobId || 0);
    if (safeOrderId <= 0) {
      return;
    }
    try {
      if (safeJobId > 0) {
        await postPosTransactionJson(`<?php echo site_url('pos/orders/runtime-jobs/trigger'); ?>/${safeOrderId}`, { job_id: safeJobId, limit: 1 });
      }
      await postPosTransactionJson(`<?php echo site_url('pos/orders/runtime-sync'); ?>/${safeOrderId}`, {
        event_source: 'ORDER_CONFIRM',
        event_id: safeOrderId
      });
      showToast('Stok self order sudah tersinkron.', 'success');
    } catch (e) {
      showToast(e && e.message ? `Sinkron stok belum selesai: ${e.message}` : 'Sinkron stok background belum selesai.', 'warning');
    }
  }

  function summaryValue(key) {
    return Number(summaryCounts[key] || 0);
  }

  function renderPaymentTabs() {
    const host = document.getElementById('self_order_payment_tabs');
    host.innerHTML = paymentTabDefs.map((tab) => `
      <button type="button" class="self-order-type-pill ${state.payment_tab === tab.key ? 'is-active' : ''}" data-payment-tab="${tab.key}">
        <span>${escapeHtml(tab.label)}</span>
      </button>
    `).join('');
    host.querySelectorAll('[data-payment-tab]').forEach((btn) => btn.addEventListener('click', () => {
      state.payment_tab = btn.dataset.paymentTab || 'ALL';
      state.page = 1;
      loadRows();
    }));
  }

  function renderStatusTabs() {
    const host = document.getElementById('self_order_status_tabs');
    host.innerHTML = statusTabDefs.map((tab) => `
      <button type="button" class="self-order-page-pill ${state.status_tab === tab.key ? 'is-active' : ''}" data-status-tab="${tab.key}">
        <span>${escapeHtml(tab.label)}</span>
        <span class="self-order-pill-count">${summaryValue(tab.key)}</span>
      </button>
    `).join('');
    host.querySelectorAll('[data-status-tab]').forEach((btn) => btn.addEventListener('click', () => {
      state.status_tab = btn.dataset.statusTab || 'ALL';
      state.page = 1;
      loadRows();
    }));
  }

  function renderSummaryGrid() {
    const host = document.getElementById('self_order_summary_grid');
    host.innerHTML = summaryDefs.map((item) => `
      <div class="self-order-summary-card">
        <div class="self-order-summary-label">${escapeHtml(item.label)}</div>
        <div class="self-order-summary-value">${summaryValue(item.key)}</div>
        <div class="self-order-summary-note">${escapeHtml(item.note)}</div>
      </div>
    `).join('');
  }

  function orderFlowChip(row) {
    const code = String(row.flow_code || '').toUpperCase();
    const label = row.flow_label || '-';
    const cls = code === 'WAITING_PAYMENT'
      ? 'waiting'
      : (code === 'REJECTED' ? 'rejected' : (code === 'ACTIVE_CASHIER' ? 'active' : (code === 'PAID_ORDER' ? 'paid' : 'verify')));
    return `<span class="self-order-status-chip ${cls}">${escapeHtml(label)}</span>`;
  }

  function paymentModeChip(row) {
    const mode = String(row.payment_mode || 'KASIR').toUpperCase();
    const label = mode === 'QRIS' ? 'QRIS' : 'Bayar di Kasir';
    const status = String(row.payment_status || 'PENDING').toUpperCase();
    const statusText = status === 'PAID' ? 'PAID' : status;
    return `
      <div class="d-flex flex-column gap-1 align-items-start">
        <span class="self-order-payment-chip ${mode === 'QRIS' ? 'qris' : 'cashier'}">${escapeHtml(label)}</span>
        <span class="self-order-subtle">${escapeHtml(statusText)}</span>
      </div>
    `;
  }

  function paymentMetaText(row) {
    const pieces = [];
    if (row.payment_method_name) pieces.push(row.payment_method_name);
    if (row.payment_provider) pieces.push(row.payment_provider);
    if (row.payment_reference) pieces.push(row.payment_reference);
    return pieces.join(' | ');
  }

  async function loadOrderDetailOnce(orderId) {
    const safeOrderId = Number(orderId || 0);
    if (safeOrderId <= 0) {
      throw new Error('Order self order tidak valid.');
    }
    if (orderDetailCache.has(safeOrderId)) {
      return orderDetailCache.get(safeOrderId);
    }
    const detail = await getJson(`<?php echo site_url('pos/self-order/orders/detail'); ?>/${safeOrderId}`);
    orderDetailCache.set(safeOrderId, detail);
    return detail;
  }

  function buildOrderLineEntries(lines) {
    const entries = [];
    const bundles = new Map();
    (Array.isArray(lines) ? lines : []).forEach((line) => {
      const bundleId = Number(line && line.bundle_id ? line.bundle_id : 0);
      const lineType = String(line && line.line_type ? line.line_type : 'PRODUCT').toUpperCase();
      if (bundleId <= 0) {
        if (lineType !== 'BUNDLE_HEADER') {
          entries.push({ type: 'LINE', line });
        }
        return;
      }

      const bundleKey = `bundle-${bundleId}`;
      if (!bundles.has(bundleKey)) {
        const bundle = {
          type: 'BUNDLE',
          bundle_id: bundleId,
          bundle_name: line.bundle_name || 'Paket bundle',
          bundle_code: line.bundle_code || '',
          lines: []
        };
        bundles.set(bundleKey, bundle);
        entries.push(bundle);
      }

      const bundle = bundles.get(bundleKey);
      if (line.bundle_name) bundle.bundle_name = line.bundle_name;
      if (line.bundle_code) bundle.bundle_code = line.bundle_code;
      if (lineType !== 'BUNDLE_HEADER') {
        bundle.lines.push(line);
      }
    });
    return entries.filter((entry) => entry.type !== 'BUNDLE' || entry.lines.length > 0);
  }

  function renderOrderLineCard(line, isBundleItem = false) {
    const extras = Array.isArray(line.extras) ? line.extras : [];
    return `
      <div class="self-order-line-card${isBundleItem ? ' is-bundle-item' : ''}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-semibold">${isBundleItem ? '-> ' : ''}${escapeHtml(line.product_name || line.bundle_name || '-')}</div>
            <div class="self-order-subtle">${escapeHtml(line.product_code || line.bundle_code || '')}</div>
          </div>
          <div class="text-end">
            <div class="fw-semibold">x${Number(line.qty || 0)}</div>
            <div class="self-order-subtle">${money(line.net_amount || 0)}</div>
          </div>
        </div>
        ${String(line.notes || '').trim() !== '' ? `<div class="self-order-subtle mt-2">Catatan: ${escapeHtml(line.notes)}</div>` : ''}
        ${extras.length ? `<div class="self-order-extra-list">${extras.map((extra) => {
          const extraLabel = `${escapeHtml(extra.extra_name || '-')} x${Number(extra.qty || 0)}`;
          const extraNote = String(extra.notes || '').trim() !== '' ? ` | ${escapeHtml(extra.notes)}` : '';
          return `<span class="self-order-extra-chip">${extraLabel}${extraNote}</span>`;
        }).join('')}</div>` : ''}
      </div>
    `;
  }

  function renderOrderLineCards(lines, emptyMessage = 'Belum ada line order.') {
    if (!Array.isArray(lines) || !lines.length) {
      return `<div class="self-order-empty">${escapeHtml(emptyMessage)}</div>`;
    }
    return buildOrderLineEntries(lines).map((entry) => {
      if (entry.type !== 'BUNDLE') {
        return renderOrderLineCard(entry.line || {}, false);
      }
      const bundleLabel = entry.bundle_name || entry.bundle_code || 'Paket bundle';
      return `
        <div class="self-order-bundle-card">
          <div class="self-order-bundle-title">Paket: ${escapeHtml(bundleLabel)}</div>
          ${entry.bundle_code ? `<div class="self-order-bundle-code">${escapeHtml(entry.bundle_code)}</div>` : ''}
          <div class="self-order-bundle-lines">${entry.lines.map((line) => renderOrderLineCard(line || {}, true)).join('')}</div>
        </div>
      `;
    }).join('');
  }

  function renderPager(meta) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || state.limit || 20);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    document.getElementById('self_order_pagination_info').textContent = total ? `Menampilkan ${start}-${end} dari ${total} order` : 'Belum ada order';
    const pager = document.getElementById('self_order_pagination');
    pager.innerHTML = Array.from({ length: totalPages }, (_, idx) => {
      const currentPage = idx + 1;
      return `<button type="button" class="btn btn-sm ${currentPage === page ? 'btn-dark' : 'btn-outline-secondary'}" data-page="${currentPage}" aria-label="Halaman ${currentPage}" ${currentPage === page ? 'aria-current="page"' : ''}>${currentPage}</button>`;
    }).join('');
    pager.querySelectorAll('[data-page]').forEach((btn) => btn.addEventListener('click', () => {
      state.page = Number(btn.dataset.page || 1);
      loadRows();
    }));
  }

  function renderRows(rows) {
    const body = document.getElementById('self_order_body');
    const empty = document.getElementById('self_order_empty_state');
    if (!Array.isArray(rows) || !rows.length) {
      body.innerHTML = '';
      empty.classList.remove('d-none');
      empty.innerHTML = '<strong>Belum ada order self order pada filter ini.</strong><div class="small mt-1">Periksa mode pembayaran, status, rentang tanggal, outlet, atau pencarian.</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-self-order-clear>Bersihkan filter</button>';
      empty.querySelector('[data-self-order-clear]')?.addEventListener('click', resetListFilters);
      return;
    }
    empty.classList.add('d-none');
    body.innerHTML = rows.map((row) => {
      const openHref = String(row.flow_code || '') === 'PAID_ORDER'
        ? `<?php echo site_url('pos/orders/paid'); ?>?q=${encodeURIComponent(String(row.order_no || ''))}`
        : `<?php echo site_url('pos/orders/draft'); ?>?q=${encodeURIComponent(String(row.order_no || ''))}`;
      const canVerify = Number(row.can_verify || 0) === 1;
      const canReject = Number(row.can_reject || 0) === 1;
      const verifyMessage = String(row.verify_message || 'Order self order belum siap diverifikasi.');
      const isRejected = Number(row.is_rejected || 0) === 1;
      const rejectReason = String(row.rejected_reason || '').trim();
      return `
        <tr>
          <td>
            <div class="self-order-order-no">${escapeHtml(row.order_no || '-')}</div>
            <div class="self-order-subtle">${escapeHtml(row.ordered_at || '-')}</div>
          </td>
          <td>
            <div>${escapeHtml(row.customer_name_display || 'Walk in')}</div>
            <div class="self-order-subtle">${escapeHtml(row.table_no || 'Tanpa Meja')} | ${escapeHtml(row.member_mobile_phone || '-')}</div>
          </td>
          <td>
            ${paymentModeChip(row)}
            <div class="self-order-subtle mt-1">${escapeHtml(paymentMetaText(row) || 'Belum ada reference')}</div>
          </td>
          <td>
            ${orderFlowChip(row)}
            <div class="self-order-subtle mt-1">Stock ${escapeHtml(row.stock_commit_status || '-')}</div>
            ${rejectReason ? `<div class="self-order-subtle mt-1 text-danger">Alasan: ${escapeHtml(rejectReason)}</div>` : ''}
          </td>
          <td class="text-end">
            <div class="fw-semibold">${money(row.grand_total || 0)}</div>
            <div class="self-order-subtle">${escapeHtml(row.cashier_name_display || '-')}</div>
          </td>
          <td class="text-center">
            <div class="self-order-action-stack">
              <button type="button" class="btn btn-sm btn-outline-info btn-self-order-detail" data-id="${Number(row.id || 0)}">Detail</button>
              ${!isRejected ? `<button type="button" class="btn btn-sm ${canVerify ? 'btn-primary' : 'btn-outline-secondary'} btn-self-order-verify" data-id="${Number(row.id || 0)}" data-can-verify="${canVerify ? '1' : '0'}" data-verify-message="${escapeHtml(verifyMessage)}">${canVerify ? 'Verifikasi' : 'Cek Status'}</button>` : ''}
              ${canReject ? `<button type="button" class="btn btn-sm btn-outline-danger btn-self-order-reject" data-id="${Number(row.id || 0)}">Tolak</button>` : ''}
              ${!isRejected ? `<a class="btn btn-sm btn-outline-secondary" href="${openHref}">Buka</a>` : ''}
            </div>
          </td>
        </tr>
      `;
    }).join('');

    body.querySelectorAll('.btn-self-order-detail').forEach((btn) => btn.addEventListener('click', () => {
      openDetail(Number(btn.dataset.id || 0)).catch((e) => showInfoModal(e.message));
    }));
    body.querySelectorAll('.btn-self-order-verify').forEach((btn) => btn.addEventListener('click', () => {
      const row = rows.find((item) => Number(item.id || 0) === Number(btn.dataset.id || 0));
      if (!row) return;
      if (String(btn.dataset.canVerify || '0') !== '1') {
        showInfoModal(String(btn.dataset.verifyMessage || row.verify_message || 'Order self order belum siap diverifikasi.'), 'Status Verifikasi');
        return;
      }
      openVerify(row).catch((e) => showInfoModal(e && e.message ? e.message : 'Gagal membuka verifikasi.'));
    }));
    body.querySelectorAll('.btn-self-order-reject').forEach((btn) => btn.addEventListener('click', () => {
      const row = rows.find((item) => Number(item.id || 0) === Number(btn.dataset.id || 0));
      if (!row) return;
      openReject(row);
    }));
  }

  function setListState(type = '', message = '', retry = false) {
    const region = document.getElementById('self_order_table_region');
    const box = document.getElementById('self_order_list_state');
    const empty = document.getElementById('self_order_empty_state');
    if (!region || !box) return;
    region.classList.toggle('is-loading', type === 'loading');
    region.setAttribute('aria-busy', type === 'loading' ? 'true' : 'false');
    box.className = `self-order-list-state mt-3 ${type ? `is-${type}` : 'd-none'}`;
    if (type === 'loading' && empty) empty.classList.add('d-none');
    if (!type) {
      box.innerHTML = '';
      return;
    }
    const spinner = type === 'loading' ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' : '';
    const retryButton = retry ? '<button type="button" class="btn btn-sm btn-outline-danger ms-2" data-self-order-retry>Coba lagi</button>' : '';
    box.innerHTML = `${spinner}${escapeHtml(message)}${retryButton}`;
    box.querySelector('[data-self-order-retry]')?.addEventListener('click', () => loadRows());
  }

  function resetListFilters() {
    state.q = '';
    state.outlet_id = 0;
    state.payment_tab = 'ALL';
    state.status_tab = 'NEEDS_VERIFY';
    state.date_from = '<?php echo date('Y-m-d'); ?>';
    state.date_to = '<?php echo date('Y-m-d'); ?>';
    state.page = 1;
    state.limit = 20;
    document.getElementById('self_order_q').value = '';
    document.getElementById('self_order_outlet_id').value = '0';
    document.getElementById('self_order_date_from').value = state.date_from;
    document.getElementById('self_order_date_to').value = state.date_to;
    document.getElementById('self_order_limit').value = String(state.limit);
    loadRows();
  }

  async function loadRows() {
    const requestId = ++listRequestId;
    if (listRequestController) listRequestController.abort();
    listRequestController = window.AbortController ? new AbortController() : null;
    const qs = new URLSearchParams();
    qs.set('q', state.q || '');
    qs.set('outlet_id', String(state.outlet_id || 0));
    qs.set('payment_tab', state.payment_tab || 'ALL');
    qs.set('status_tab', state.status_tab || 'ALL');
    qs.set('date_from', state.date_from || '');
    qs.set('date_to', state.date_to || '');
    qs.set('page', String(state.page || 1));
    qs.set('limit', String(state.limit || 20));
    setListState('loading', 'Memuat order self order...');
    try {
      const json = await getJson(
        '<?php echo site_url('pos/self-order/orders/data'); ?>?' + qs.toString(),
        listRequestController ? listRequestController.signal : null
      );
      if (requestId !== listRequestId) return;
      summaryCounts = Object.assign({ ALL: 0, NEEDS_VERIFY: 0, WAITING_PAYMENT: 0, ACTIVE_CASHIER: 0, PAID_ORDER: 0, REJECTED: 0 }, json.counts || {});
      setListState();
      renderPaymentTabs();
      renderStatusTabs();
      renderSummaryGrid();
      renderRows(Array.isArray(json.rows) ? json.rows : []);
      renderPager(json.meta || {});
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (requestId !== listRequestId) return;
      incomingSnapshot = ''; // Retry the list on the next poll even if order state is unchanged.
      setListState('error', error.message || 'Order self order tidak dapat dimuat.', true);
    } finally {
      if (requestId === listRequestId) {
        listRequestController = null;
        const region = document.getElementById('self_order_table_region');
        if (region) {
          region.classList.remove('is-loading');
          region.setAttribute('aria-busy', 'false');
        }
      }
    }
  }

  async function pollIncomingOrders() {
    if (incomingPollBusy) {
      return;
    }
    incomingPollBusy = true;
    try {
      const qs = new URLSearchParams();
      qs.set('q', '');
      qs.set('outlet_id', String(state.outlet_id || 0));
      qs.set('payment_tab', 'ALL');
      qs.set('status_tab', 'ALL');
      qs.set('date_from', state.date_from || '');
      qs.set('date_to', state.date_to || '');
      qs.set('page', '1');
      qs.set('limit', '20');
      const json = await getJson('<?php echo site_url('pos/self-order/orders/data'); ?>?' + qs.toString());
      const rows = Array.isArray(json.rows) ? json.rows : [];
      // A QRIS payment changes an existing order, not its ID. Refresh the
      // verification list on state changes as well as newly created orders.
      const snapshot = JSON.stringify([json.counts || {}, rows.map((row) => [
        row.id, row.status, row.flow_code, row.payment_status, row.paid_total,
        row.stock_commit_status, row.can_verify, row.can_reject, row.grand_total
      ])]);
      const changed = snapshot !== incomingSnapshot;
      incomingSnapshot = snapshot;
      const newRows = [];
      rows.forEach((row) => {
        const orderId = Number(row.id || 0);
        if (orderId <= 0) {
          return;
        }
        if (!seenIncomingOrderIds.has(orderId)) {
          if (incomingBaselineReady) {
            newRows.push(row);
          }
          seenIncomingOrderIds.add(orderId);
        }
      });
      if (!incomingBaselineReady) {
        incomingBaselineReady = true;
        await loadRows();
        return;
      }
      if (newRows.length) {
        playIncomingSound();
        const newest = newRows[0] || {};
        const tableLabel = String(newest.table_no || '').trim() !== '' ? ` | ${newest.table_no}` : '';
        showToast(`Order baru masuk: ${newest.order_no || 'SELF-ORDER'}${tableLabel}`, 'info');
      }
      if (changed) await loadRows();
    } catch (e) {
      // polling failure should not disturb cashier
    } finally {
      incomingPollBusy = false;
    }
  }

  async function openDetail(orderId) {
    const json = await loadOrderDetailOnce(orderId);
    const header = json.header || {};
    const lines = Array.isArray(json.lines) ? json.lines : [];
    const payments = Array.isArray(json.payments) ? json.payments : [];
    document.getElementById('self_order_detail_meta').textContent = `${header.order_no || '-'} | ${header.customer_display_name || header.member_name || 'Walk in'} | ${header.table_no || 'Tanpa Meja'}`;
    document.getElementById('self_order_detail_grid').innerHTML = [
      ['Customer', header.customer_display_name || header.member_name || 'Walk in'],
      ['Nomor Meja', header.table_no || 'Tanpa Meja'],
      ['Status Order', header.status || '-'],
      ['Stock Commit', header.stock_commit_status || '-'],
      ['Service', header.service_type || '-'],
      ['Guest', header.guest_count || 1],
      ['Grand Total', money(header.grand_total || 0)],
      ['Kasir', header.cashier_username || header.cashier_employee_name || '-']
    ].map((item) => `<div class="self-order-detail-item"><div class="self-order-detail-label">${escapeHtml(item[0])}</div><div class="self-order-detail-value">${escapeHtml(item[1])}</div></div>`).join('');
    const detailNotes = [];
    if (String(header.notes || '').trim() !== '') {
      detailNotes.push(String(header.notes).trim());
    }
    if (String(header.reject_reason || '').trim() !== '') {
      const rejectActor = String(header.reject_actor_name || '').trim();
      detailNotes.push(`Pesanan ditolak${rejectActor ? ' oleh ' + rejectActor : ''}: ${String(header.reject_reason).trim()}`);
    } else if (String(header.status || '').toUpperCase() === 'REJECTED') {
      detailNotes.push('Pesanan ini sudah ditolak kasir.');
    }
    document.getElementById('self_order_detail_note').textContent = detailNotes.length ? detailNotes.join(' | ') : 'Tidak ada catatan order.';
    document.getElementById('self_order_detail_lines').innerHTML = renderOrderLineCards(lines);
    document.getElementById('self_order_detail_payments').innerHTML = payments.length ? payments.map((payment) => {
      const paymentLines = Array.isArray(payment.lines) ? payment.lines : [];
      const paymentLineHtml = paymentLines.length
        ? paymentLines.map((line) => escapeHtml([line.method_name || '-', line.reference_no || ''].filter(Boolean).join(' | '))).join('<br>')
        : 'Belum ada line pembayaran.';
      return `
        <div class="self-order-payment-card">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="fw-semibold">${escapeHtml(payment.payment_no || '-')}</div>
              <div class="self-order-subtle">${escapeHtml(payment.payment_status || '-')} | ${escapeHtml(payment.payment_type || '-')}</div>
            </div>
            <div class="text-end fw-semibold">${money(payment.net_amount || 0)}</div>
          </div>
          <div class="self-order-subtle mt-2">${paymentLineHtml}</div>
        </div>
      `;
    }).join('') : '<div class="self-order-empty">Belum ada jejak pembayaran.</div>';
    showModal(detailModalEl, detailModal);
  }

  async function openVerify(row) {
    verifyRow = row;
    const destinationWrap = document.getElementById('self_order_verify_destination_wrap');
    const verifyLinesEl = document.getElementById('self_order_verify_lines');
    const hasPaidQris = row.payment_mode === 'QRIS' && Number(row.is_paid || 0) === 1;
    document.getElementById('self_order_verify_meta').textContent = `${row.order_no || '-'} | ${row.customer_name_display || 'Walk in'} | ${row.payment_mode === 'QRIS' ? 'QRIS' : 'Bayar di Kasir'}`;
    if (verifyLinesEl) {
      verifyLinesEl.innerHTML = '<div class="self-order-empty">Memuat line order...</div>';
    }
    if (destinationWrap) {
      destinationWrap.classList.toggle('d-none', !hasPaidQris);
    }
    syncVerifyDestinationSummary();
    document.getElementById('self_order_verify_hint').textContent = hasPaidQris
      ? 'Order QRIS yang sudah lunas akan langsung masuk ke workspace pesanan terbayar beserta metode pembayaran yang sudah dipilih customer.'
      : (row.payment_mode === 'QRIS'
      ? 'Order QRIS belum bisa diverifikasi sebelum pembayaran diterima.'
      : 'Order akan masuk ke workspace order aktif. Kasir tetap bisa menagih customer di payment panel setelah pesanan diverifikasi.');
    try {
      const detail = await loadOrderDetailOnce(Number(row.id || 0));
      if (verifyLinesEl) {
        verifyLinesEl.innerHTML = renderOrderLineCards(Array.isArray(detail.lines) ? detail.lines : [], 'Line order belum tersedia.');
      }
    } catch (e) {
      if (verifyLinesEl) {
        verifyLinesEl.innerHTML = `<div class="self-order-empty">${escapeHtml(e && e.message ? e.message : 'Gagal memuat line order.')}</div>`;
      }
    }
    showModal(verifyModalEl, verifyModal);
  }

  function syncVerifyDestinationSummary() {
    if (!verifyRow) return;
    const destination = (verifyRow.payment_mode === 'QRIS' && Number(verifyRow.is_paid || 0) === 1)
      ? 'PAID_ORDER'
      : 'ACTIVE_CASHIER';
    const destinationNoteEl = document.getElementById('self_order_verify_destination_note');
    if (destinationNoteEl) {
      destinationNoteEl.textContent = 'Order QRIS yang sudah lunas akan langsung masuk ke workspace pesanan terbayar setelah diverifikasi.';
    }
    document.getElementById('self_order_verify_grid').innerHTML = [
      ['Customer', verifyRow.customer_name_display || 'Walk in'],
      ['Nomor Meja', verifyRow.table_no || 'Tanpa Meja'],
      ['Pembayaran', verifyRow.payment_mode === 'QRIS' ? 'QRIS' : 'Bayar di Kasir'],
      ['Status Bayar', verifyRow.payment_status || '-'],
      ['Nominal', money(verifyRow.grand_total || 0)],
      ['Order Akan Masuk', destination === 'PAID_ORDER' ? 'Pesanan Terbayar' : 'Order Aktif']
    ].map((item) => `<div class="self-order-detail-item"><div class="self-order-detail-label">${escapeHtml(item[0])}</div><div class="self-order-detail-value">${escapeHtml(item[1])}</div></div>`).join('');
  }

  function openReject(row) {
    rejectRow = row;
    document.getElementById('self_order_reject_meta').textContent = `${row.order_no || '-'} | ${row.customer_name_display || 'Walk in'} | ${row.payment_mode === 'QRIS' ? 'QRIS' : 'Bayar di Kasir'}`;
    document.getElementById('self_order_reject_grid').innerHTML = [
      ['Customer', row.customer_name_display || 'Walk in'],
      ['Nomor Meja', row.table_no || 'Tanpa Meja'],
      ['Pembayaran', row.payment_mode === 'QRIS' ? 'QRIS' : 'Bayar di Kasir'],
      ['Status Bayar', row.payment_status || '-'],
      ['Nominal', money(row.grand_total || 0)],
      ['Flow Saat Ini', row.flow_label || 'Perlu Verifikasi Kasir']
    ].map((item) => `<div class="self-order-detail-item"><div class="self-order-detail-label">${escapeHtml(item[0])}</div><div class="self-order-detail-value">${escapeHtml(item[1])}</div></div>`).join('');
    document.getElementById('self_order_reject_hint').textContent = row.payment_mode === 'QRIS'
      ? 'Tolak hanya dipakai saat QRIS belum dibayar. Order tidak akan lanjut ke workspace kasir.'
      : 'Order kasir yang ditolak tidak akan masuk ke workspace order aktif.';
    document.getElementById('self_order_reject_reason').value = '';
    showModal(rejectModalEl, rejectModal);
  }

  async function submitVerify() {
    if (verifyBusy || !verifyRow) return;
    verifyBusy = true;
    const btn = document.getElementById('self_order_submit_verify');
    const verifyDestination = (verifyRow.payment_mode === 'QRIS' && Number(verifyRow.is_paid || 0) === 1)
      ? 'PAID_ORDER'
      : 'ACTIVE_CASHIER';
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="self-order-btn-spinner" aria-hidden="true"></span> Memproses';
    try {
      const json = await postPosTransactionJson(`<?php echo site_url('pos/self-order/orders/verify'); ?>/${Number(verifyRow.id || 0)}`, {
        verify_destination: verifyDestination
      });
      hideModal(verifyModalEl, verifyModal);

      let printFailures = [];
      try {
        const printResult = await directPrintTargets(json.direct_print_targets || []);
        printFailures = (printResult.failed || []).map(normalizePrintFailureEntry);
      } catch (e) {
        printFailures = [normalizePrintFailureEntry({ name: 'Printer', reason: e && e.message ? e.message : 'Gagal menyiapkan direct print' })];
      }

      showToast(
        String(json.workspace_bucket || '') === 'PAID_ORDER'
          ? 'Order self order berhasil diverifikasi dan masuk ke pesanan terbayar.'
          : 'Order self order berhasil diverifikasi dan masuk ke order aktif.',
        'success'
      );
      if (Number(json.runtime_job_id || 0) > 0) {
        showToast('Sinkronisasi stok berjalan di background.', 'info');
        void kickoffRuntimeJobSync(Number(json.id || 0), Number(json.runtime_job_id || 0));
      }
      if (printFailures.length) {
        const message = 'Order sudah diverifikasi, tetapi sebagian printer gagal menerima tiket:\n\n'
          + printFailures.map((entry) => `- ${entry.name}: ${entry.reason}`).join('\n');
        showInfoModal(message, 'Printer Self Order');
      }

      await loadRows();
    } catch (e) {
      showInfoModal(e && e.message ? e.message : 'Gagal memverifikasi order self order.', 'Verifikasi Gagal');
    } finally {
      verifyBusy = false;
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }

  async function submitReject() {
    if (rejectBusy || !rejectRow) return;
    const reasonEl = document.getElementById('self_order_reject_reason');
    const reason = String(reasonEl ? reasonEl.value : '').trim();
    if (reason === '') {
      showInfoModal('Alasan penolakan wajib diisi.', 'Alasan Penolakan');
      if (reasonEl) reasonEl.focus();
      return;
    }
    rejectBusy = true;
    const btn = document.getElementById('self_order_submit_reject');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="self-order-btn-spinner" aria-hidden="true"></span> Memproses';
    try {
      await postPosTransactionJson(`<?php echo site_url('pos/self-order/orders/reject'); ?>/${Number(rejectRow.id || 0)}`, { reason });
      hideModal(rejectModalEl, rejectModal);
      showToast('Order self order berhasil ditolak.', 'warning');
      state.payment_tab = 'ALL';
      state.status_tab = 'REJECTED';
      state.page = 1;
      await loadRows();
    } catch (e) {
      showInfoModal(e && e.message ? e.message : 'Gagal menolak order self order.', 'Penolakan Gagal');
    } finally {
      rejectBusy = false;
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }

  function bindFilters() {
    const q = document.getElementById('self_order_q');
    const outlet = document.getElementById('self_order_outlet_id');
    const dateFrom = document.getElementById('self_order_date_from');
    const dateTo = document.getElementById('self_order_date_to');
    const limit = document.getElementById('self_order_limit');
    q.value = state.q || '';
    outlet.value = String(state.outlet_id || 0);

    q.addEventListener('input', () => {
      state.q = q.value;
      state.page = 1;
      clearTimeout(listSearchTimer);
      listSearchTimer = setTimeout(() => loadRows(), 250);
    });
    outlet.addEventListener('change', () => {
      state.outlet_id = Number(outlet.value || 0);
      state.page = 1;
      loadRows();
    });
    dateFrom.addEventListener('change', () => {
      state.date_from = dateFrom.value;
      state.page = 1;
      loadRows();
    });
    dateTo.addEventListener('change', () => {
      state.date_to = dateTo.value;
      state.page = 1;
      loadRows();
    });
    limit.addEventListener('change', () => {
      state.limit = Number(limit.value || 20);
      state.page = 1;
      loadRows();
    });
    document.getElementById('self_order_reset_filter').addEventListener('click', () => {
      resetListFilters();
    });
    const verifyDestination = document.getElementById('self_order_verify_destination');
    if (verifyDestination) {
      verifyDestination.addEventListener('change', syncVerifyDestinationSummary);
    }
    document.getElementById('self_order_submit_verify').addEventListener('click', submitVerify);
    document.getElementById('self_order_submit_reject').addEventListener('click', submitReject);
  }

  bindFilters();
  renderPaymentTabs();
  renderStatusTabs();
  renderSummaryGrid();
  document.addEventListener('pointerdown', unlockNotifyAudio, { once: true, passive: true });
  document.addEventListener('keydown', unlockNotifyAudio, { once: true });
  loadRows();
  pollIncomingOrders();
  window.setInterval(pollIncomingOrders, 12000);
})();
</script>
<?php $this->load->view('pos/_direct_print_prompt'); ?>
