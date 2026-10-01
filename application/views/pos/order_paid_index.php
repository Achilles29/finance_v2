<?php
$filters              = is_array($filters ?? null) ? $filters : [];
$filterOptions        = is_array($filter_options ?? null) ? $filter_options : [];
$outlets              = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
$refundPaymentMethods = is_array($filterOptions['refund_payment_methods'] ?? null) ? $filterOptions['refund_payment_methods'] : [];
$reversalReasonOptions = is_array($filterOptions['reversal_reason_options'] ?? null) ? $filterOptions['reversal_reason_options'] : [];
$paymentMethodOptions = is_array($payment_method_options ?? null) ? $payment_method_options : [];
$canEditPaymentMethod = !empty($can_edit_payment_method);

$today = date('Y-m-d');
$initDateFrom = ($filters['date_from'] ?? '') !== '' ? $filters['date_from'] : $today;
$initDateTo   = ($filters['date_to']   ?? '') !== '' ? $filters['date_to']   : $today;
?>

<style>
  .pos-paid-shell { display:grid; gap:1rem; }
  .pos-paid-main { border:0; border-radius:22px; box-shadow:0 18px 40px rgba(58, 38, 30, .08); }
  .pos-paid-empty {
    border:1px dashed rgba(189, 170, 154, .6); border-radius:16px; padding:1.4rem; text-align:center;
    color:#8b7a70; background:#fffaf6;
  }
  .pos-paid-mini-note { font-size:.78rem; color:#89756c; }
  .pos-paid-status-chip {
    display:inline-flex; align-items:center; gap:.28rem; padding:.2rem .58rem; border-radius:999px;
    font-size:.72rem; font-weight:900; white-space:nowrap;
  }
  .pos-paid-status-chip.status-paid { background:#dcfce7; color:#166534; }
  .pos-paid-status-chip.status-partial { background:#fff4dd; color:#8d5a00; }
  .pos-paid-status-chip.status-refund { background:#fee2e2; color:#b91c1c; }
  .pos-paid-action-stack { display:flex; gap:.45rem; justify-content:center; }
  .pos-paid-summary-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.55rem; }
  .pos-paid-summary-card {
    border:1px solid rgba(224, 209, 198, .72); border-radius:14px; padding:.7rem .85rem;
    background:#fffdfb;
  }
  .pos-paid-summary-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; color:#8b7a70; margin-bottom:.12rem; }
  .pos-paid-summary-value { font-size:1.05rem; font-weight:900; color:#36292a; }
  .pos-paid-detail-panel {
    border:1px solid rgba(224, 209, 198, .72); border-radius:16px; padding:.8rem .9rem; background:#fffdfb;
  }
  .pos-paid-detail-history-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.8rem; }
  .pos-paid-detail-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.5rem; }
  .pos-paid-detail-item {
    border:1px solid rgba(224, 209, 198, .65); border-radius:12px; padding:.55rem .65rem; background:#fffaf6;
  }
  .pos-paid-history-list { display:grid; gap:.6rem; }
  .pos-paid-history-empty {
    border:1px dashed rgba(214, 195, 182, .85); border-radius:12px; padding:.75rem .85rem; color:#8b7a70; background:#fffaf6;
  }
  .pos-paid-history-item {
    border:1px solid rgba(224, 209, 198, .65); border-radius:12px; padding:.7rem .8rem; background:#fffaf6;
  }
  .pos-paid-history-head { display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem; }
  .pos-paid-history-title { font-size:.86rem; font-weight:800; color:#3c2d2d; }
  .pos-paid-history-meta { font-size:.76rem; color:#8b7a70; }
  .pos-paid-history-amount { font-size:.86rem; font-weight:800; color:#2f4b3c; white-space:nowrap; }
  .pos-paid-history-lines { margin-top:.45rem; font-size:.78rem; color:#6f5f57; }
  .pos-paid-detail-label { font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:#8b7a70; margin-bottom:.1rem; }
  .pos-paid-detail-value { font-size:.86rem; font-weight:700; color:#3c2d2d; line-height:1.35; word-break:break-word; }
  .pos-paid-line-table { margin-bottom:0; }
  .pos-paid-line-table td { vertical-align:top; padding:.7rem .55rem; }
  .pos-paid-line-table th { padding:.7rem .55rem; font-size:.76rem; white-space:nowrap; }
  .pos-paid-refund-help { background:#fff7f2; border:1px solid rgba(224, 209, 198, .85); border-radius:16px; padding:.8rem .95rem; color:#755f56; }
  .pos-paid-policy-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.85rem; }
  .pos-paid-policy-card {
    border:1px solid rgba(224, 209, 198, .75); border-radius:18px; padding:1rem 1.1rem;
    background:linear-gradient(135deg,#fffaf6 0%,#fff 100%); cursor:pointer;
  }
  .pos-paid-policy-card.active { border-color:#8f3d33; box-shadow:0 12px 24px rgba(143,61,51,.12); }
  .pos-paid-policy-title { font-weight:800; color:#3a2b2b; }
  .pos-paid-policy-note { font-size:.8rem; color:#7b6b63; }
  .pos-paid-section-title { font-size:.78rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#8a776d; }
  .pos-paid-refund-line {
    border:1px solid rgba(224, 209, 198, .75); border-radius:14px; padding:.85rem 1rem;
    background:linear-gradient(135deg,#fffaf6 0%,#fff 100%);
  }
  .pos-paid-refund-line + .pos-paid-refund-line { margin-top:.7rem; }
  .pos-paid-refund-toolbar { display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; }
  .pos-paid-refund-flag {
    display:inline-flex; align-items:center; gap:.35rem; padding:.18rem .55rem; border-radius:999px;
    font-size:.72rem; font-weight:800;
  }
  .pos-paid-refund-flag.return { background:#e8f8ec; color:#1d7f45; }
  .pos-paid-refund-flag.adjust { background:#fff4dd; color:#8d5a00; }
  .pos-paid-refund-extra-list { margin-top:.85rem; display:grid; gap:.55rem; }
  .pos-paid-refund-extra-row {
    border:1px dashed rgba(214, 195, 182, .9); border-radius:14px; padding:.7rem .85rem; background:#fff;
  }
  .pos-paid-account-info {
    border:1px solid rgba(224, 209, 198, .8); border-radius:14px; background:#fffaf7; padding:.75rem .9rem;
  }
  .pos-paid-btn-spinner {
    width:1rem; height:1rem; border:.15em solid currentColor; border-right-color:transparent;
    border-radius:50%; display:inline-block; animation:posPaidSpin .7s linear infinite;
  }
  /* payment line edit */
  .pos-paid-pay-line-item { padding:.55rem .7rem; border-bottom:1px solid rgba(224,209,198,.5); }
  .pos-paid-pay-line-item:last-child { border-bottom:0; }
  .pos-paid-pay-line-head { display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem; }
  .pos-paid-pay-line-method { font-size:.86rem; font-weight:700; color:#3c2d2d; }
  .pos-paid-pay-line-account { font-size:.75rem; color:#7a6b62; }
  .pos-paid-pay-line-meta { font-size:.72rem; color:#8b7a70; }
  .pos-paid-pay-line-amount { font-size:.86rem; font-weight:800; color:#2f4b3c; white-space:nowrap; }
  .pos-paid-pay-edit-btn { padding:.2rem .5rem; font-size:.75rem; }
  @keyframes posPaidSpin { to { transform:rotate(360deg); } }
  @media (max-width: 991.98px) {
    .pos-paid-summary-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .pos-paid-detail-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .pos-paid-detail-history-grid { grid-template-columns:1fr; }
    .pos-paid-policy-grid { grid-template-columns:1fr; }
  }
  @media (max-width: 575.98px) {
    .pos-paid-summary-grid, .pos-paid-detail-grid { grid-template-columns:1fr; }
  }
</style>

<div class="container-xxl py-3">

  <?php $this->load->view('pos/_order_workspace_tabs', ['order_workspace_active' => 'paid']); ?>

  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Pesanan Terbayar POS</h4>
      <p class="fin-page-subtitle mb-0">Review transaksi lunas, proses refund, dan koreksi metode pembayaran.</p>
    </div>
  </div>

  <div class="pos-paid-shell">
    <div class="card pos-paid-main">
      <div class="card-body p-4">

        <!-- Filter -->
        <form class="row g-2 mb-3" id="paid-filter-form" onsubmit="return false;">
          <!-- Search -->
          <div class="col-md-4 col-lg-3">
            <label class="form-label small text-muted mb-1">Cari</label>
            <input id="recent_q" class="form-control form-control-sm"
                   placeholder="No order, customer, member, meja"
                   value="<?php echo html_escape($filters['q'] ?? ''); ?>">
          </div>
          <!-- Outlet -->
          <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-muted mb-1">Outlet</label>
            <select id="recent_outlet_id" class="form-select form-select-sm">
              <option value="0">Semua Outlet</option>
              <?php foreach ($outlets as $outlet): ?>
                <option value="<?php echo (int)$outlet['id']; ?>"
                  <?php echo ((int)($filters['outlet_id'] ?? 0) === (int)$outlet['id']) ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$outlet['outlet_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Tanggal Dari -->
          <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-muted mb-1">Dari Tanggal</label>
            <input type="date" id="recent_date_from" class="form-control form-control-sm"
                   value="<?php echo html_escape($initDateFrom); ?>">
          </div>
          <!-- Tanggal Sampai -->
          <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
            <input type="date" id="recent_date_to" class="form-control form-control-sm"
                   value="<?php echo html_escape($initDateTo); ?>">
          </div>
          <!-- Actions -->
          <div class="col-6 col-md-3 col-lg-3 d-flex align-items-end gap-2">
            <button type="button" id="btn-apply-filter" class="btn btn-sm btn-outline-primary flex-fill">
              <i class="ri ri-search-line me-1"></i>Filter
            </button>
            <button type="button" id="btn-clear-recent" class="btn btn-sm btn-outline-danger">
              <i class="ri ri-close-line"></i>
            </button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm align-middle table-hover">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Outlet</th>
                <th>Kasir</th>
                <th class="text-center">Status</th>
                <th class="text-end">Grand Total</th>
                <th class="text-center" style="width:160px;">Aksi</th>
              </tr>
            </thead>
            <tbody id="recent_body"></tbody>
          </table>
        </div>
        <div id="recent_empty_state" class="pos-paid-empty d-none">Belum ada order terbayar pada filter ini.</div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <small id="recent_pagination_info" class="text-muted"></small>
          <div class="d-flex gap-1" id="recent_pagination"></div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- Modal Detail Order                                           -->
<!-- ============================================================ -->
<div class="modal fade" id="paidDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Detail Order Terbayar</h5>
          <div class="small text-muted" id="paid_detail_meta">Order belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="paid_detail_alert" class="mb-3"></div>
        <div class="pos-paid-summary-grid mb-3">
          <div class="pos-paid-summary-card">
            <div class="pos-paid-summary-label">Grand Total</div>
            <div class="pos-paid-summary-value" id="paid_detail_total">Rp 0</div>
          </div>
          <div class="pos-paid-summary-card">
            <div class="pos-paid-summary-label">Sudah Dibayar</div>
            <div class="pos-paid-summary-value" id="paid_detail_paid_total">Rp 0</div>
          </div>
          <div class="pos-paid-summary-card">
            <div class="pos-paid-summary-label">Kembalian</div>
            <div class="pos-paid-summary-value" id="paid_detail_change_total">Rp 0</div>
          </div>
          <div class="pos-paid-summary-card">
            <div class="pos-paid-summary-label">Sisa</div>
            <div class="pos-paid-summary-value" id="paid_detail_remaining">Rp 0</div>
          </div>
        </div>
        <div class="pos-paid-detail-panel mb-3">
          <div class="pos-paid-section-title mb-2">Info Order</div>
          <div class="pos-paid-detail-grid" id="paid_detail_header_grid"></div>
        </div>
        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle pos-paid-line-table">
            <thead>
              <tr>
                <th>Item</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Total</th>
                <th>Status</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody id="paid_detail_line_body"></tbody>
          </table>
        </div>
        <div class="pos-paid-detail-history-grid mt-3">
          <!-- Riwayat Pembayaran + Edit Metode -->
          <div class="pos-paid-detail-panel">
            <div class="pos-paid-section-title mb-2">Riwayat Pembayaran</div>
            <div id="paid_detail_payment_history"></div>
          </div>
          <!-- Riwayat Refund -->
          <div class="pos-paid-detail-panel">
            <div class="pos-paid-section-title mb-2">Riwayat Refund</div>
            <div class="pos-paid-history-list" id="paid_detail_refund_history"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- Modal Edit Metode Pembayaran                                 -->
<!-- ============================================================ -->
<?php if ($canEditPaymentMethod): ?>
<div class="modal fade" id="paymentMethodEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Metode Pembayaran</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="payment_edit_flash" class="mb-3 d-none"></div>
        <div class="mb-3 p-3 rounded-3" style="background:#f8f5f2;">
          <div class="fw-semibold mb-1" id="payment_edit_title">-</div>
          <div class="small text-muted" id="payment_edit_meta">-</div>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted mb-1">Metode Saat Ini</label>
          <div class="form-control bg-light" id="payment_edit_current_method">-</div>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted mb-1">Metode Baru <span class="text-danger">*</span></label>
          <select class="form-select" id="payment_edit_method_id">
            <option value="">Pilih metode pembayaran...</option>
            <?php foreach ($paymentMethodOptions as $method): ?>
              <option value="<?php echo (int)($method['id'] ?? 0); ?>">
                <?php echo html_escape((string)($method['method_name'] ?? '-')); ?>
                <?php echo !empty($method['method_type']) ? ' • ' . html_escape((string)$method['method_type']) : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-0">
          <label class="form-label small text-muted mb-1">Rekening Tujuan</label>
          <div class="form-control bg-light" id="payment_edit_account_preview">
            Pilih metode untuk melihat rekening perusahaan yang terkait.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-payment-method">Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- Modal Refund                                                 -->
<!-- ============================================================ -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Refund POS</h5>
          <div class="small text-muted" id="refund_modal_meta">Order belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning border-0 d-none" id="refund_empty_hint">Snapshot refund belum tersedia untuk order ini.</div>
        <div class="pos-paid-refund-help mb-3">
          <div class="fw-semibold mb-1">Refund hanya untuk order yang sudah dibayar.</div>
          <div class="small mb-0">Pilih line yang akan direfund dulu. Setelah itu tentukan apakah sistem hanya rollback stok dan LOT, atau rollback dulu lalu posting adjustment, kemudian pilih rekening pengembalian.</div>
        </div>
        <div class="mb-3">
          <div class="pos-paid-refund-toolbar mb-2">
            <div class="pos-paid-section-title mb-0">Line Refund</div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="refund_uncheck_all">Uncek Semua</button>
              <button type="button" class="btn btn-sm btn-outline-dark" id="refund_check_all">Cek Semua</button>
            </div>
          </div>
          <div id="refund_line_list"></div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="pos-paid-section-title mb-2">Kebijakan Refund</div>
            <div class="pos-paid-policy-grid">
              <label class="pos-paid-policy-card active" id="refund_policy_return_card">
                <input class="d-none" type="radio" name="refund_stock_policy" id="refund_policy_return" value="RETURN_TO_STOCK" checked>
                <div class="pos-paid-policy-title">Kembalikan ke stok</div>
              </label>
              <label class="pos-paid-policy-card" id="refund_policy_adjust_card">
                <input class="d-none" type="radio" name="refund_stock_policy" id="refund_policy_adjust" value="ADJUSTMENT_ONLY">
                <div class="pos-paid-policy-title">Jangan kembalikan ke stok</div>
              </label>
            </div>
          </div>
          <div class="col-md-6 d-none" id="refund_adjustment_wrap">
            <label class="form-label small text-muted mb-1">Adjustment Mode</label>
            <select class="form-select" id="refund_adjustment_mode">
              <option value="NONE">Pilih tipe adjustment...</option>
              <option value="AUTO_WASTE">Waste otomatis</option>
              <option value="AUTO_SPOIL">Spoil otomatis</option>
              <option value="AUTO_ADJUSTMENT">Penyesuaian otomatis</option>
            </select>
          </div>
          <div class="col-md-6 d-none" id="refund_reason_wrap">
            <label class="form-label small text-muted mb-1">Alasan</label>
            <select class="form-select" id="refund_reason_code">
              <option value="">Pilih alasan...</option>
            </select>
            <input type="text" class="form-control mt-2 d-none" id="refund_reason_other" placeholder="Tulis alasan lainnya">
            <div class="small text-muted mt-1" id="refund_reason_hint"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Metode Refund</label>
            <select class="form-select" id="refund_payment_method_id">
              <option value="">Pilih metode refund...</option>
              <?php foreach ($refundPaymentMethods as $method): ?>
                <option value="<?php echo (int)($method['id'] ?? 0); ?>"><?php echo html_escape((string)($method['method_name'] ?? '-')); ?><?php echo !empty($method['method_type']) ? ' • ' . html_escape((string)$method['method_type']) : ''; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Referensi Refund</label>
            <input type="text" class="form-control" id="refund_reference_no" placeholder="No referensi transfer / catatan kasir (opsional)">
          </div>
          <div class="col-12">
            <div class="pos-paid-account-info" id="refund_account_info">Pilih metode refund untuk melihat rekening perusahaan yang akan dipakai.</div>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Catatan Audit</label>
            <textarea class="form-control" id="refund_reason" rows="2" placeholder="Catatan tambahan untuk audit POS (opsional)"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1" for="refund_step_up_password">Konfirmasi password</label>
            <input type="password" class="form-control" id="refund_step_up_password" autocomplete="current-password" maxlength="72" placeholder="Masukkan password Anda untuk menyimpan refund">
            <div class="small text-muted mt-1">Password hanya dipakai untuk verifikasi ulang aksi ini dan tidak disimpan pada dokumen refund.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-danger" id="btn-save-refund">Simpan Refund</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialFilters       = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const refundPaymentMethods = <?php echo json_encode($refundPaymentMethods, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const reversalReasonOptions = <?php echo json_encode($reversalReasonOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const posTransactionCsrfToken = <?php echo json_encode((string)($pos_transaction_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const paymentMethodOptions = <?php echo json_encode(array_values($paymentMethodOptions), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const canEditPaymentMethod = <?php echo $canEditPaymentMethod ? 'true' : 'false'; ?>;
  const paymentMethodMap = Object.fromEntries(paymentMethodOptions.map((row) => [String(row.id || ''), row]));

  const today = new Date().toISOString().slice(0, 10);
  const recentState = {
    q:            initialFilters.q            || '',
    status:       initialFilters.status       || 'PAID',
    workspace_mode: 'PAID',
    outlet_id:    parseInt(initialFilters.outlet_id  || 0, 10)  || 0,
    date_from:    initialFilters.date_from    || today,
    date_to:      initialFilters.date_to      || today,
    page:         parseInt(initialFilters.page  || 1, 10)  || 1,
    limit:        parseInt(initialFilters.limit || 20, 10) || 20
  };
  let refundPreview = null;
  let refundSubmitInFlight = false;

  const detailModalEl = document.getElementById('paidDetailModal');
  const detailModal   = detailModalEl && window.bootstrap ? new bootstrap.Modal(detailModalEl) : null;
  const refundModalEl = document.getElementById('refundModal');
  const refundModal   = refundModalEl && window.bootstrap ? new bootstrap.Modal(refundModalEl) : null;
  const refundReasonCode  = document.getElementById('refund_reason_code');
  const refundReasonOther = document.getElementById('refund_reason_other');
  const refundReasonHint  = document.getElementById('refund_reason_hint');
  const refundReasonWrap  = document.getElementById('refund_reason_wrap');
  const refundAdjustmentWrap = document.getElementById('refund_adjustment_wrap');
  const refundMethodField    = document.getElementById('refund_payment_method_id');
  const refundReferenceField = document.getElementById('refund_reference_no');
  const refundAccountInfo    = document.getElementById('refund_account_info');
  const refundSubmitButton   = document.getElementById('btn-save-refund');
  const refundStepUpPassword = document.getElementById('refund_step_up_password');

  /* Edit payment method fields */
  const payMethodEditModalEl = document.getElementById('paymentMethodEditModal');
  const payMethodEditModal   = payMethodEditModalEl && window.bootstrap ? new bootstrap.Modal(payMethodEditModalEl) : null;
  const payMethodField       = document.getElementById('payment_edit_method_id');
  const payMethodSaveBtn     = document.getElementById('btn-save-payment-method');
  const payMethodTitleEl     = document.getElementById('payment_edit_title');
  const payMethodMetaEl      = document.getElementById('payment_edit_meta');
  const payMethodCurrentEl   = document.getElementById('payment_edit_current_method');
  const payMethodAccountEl   = document.getElementById('payment_edit_account_preview');
  const payMethodFlashEl     = document.getElementById('payment_edit_flash');
  let activePaymentTrigger   = null;

  /* ── helpers ────────────────────────────────────────────── */
  function escapeHtml(v) { return String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
  function money(v) { return new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', maximumFractionDigits:0 }).format(Number(v || 0)); }
  function number(v, d=2) { return new Intl.NumberFormat('id-ID', { minimumFractionDigits:d, maximumFractionDigits:d }).format(Number(v || 0)); }

  function setFlashIn(el, type, msg) {
    if (!el) return;
    el.className = 'alert alert-' + type + ' py-2';
    el.textContent = msg;
    el.classList.remove('d-none');
  }

  function getRefundMethodMeta() {
    const methodId = Number(refundMethodField?.value || 0);
    if (methodId <= 0) return null;
    return (refundPaymentMethods || []).find((row) => Number(row.id || 0) === methodId) || null;
  }

  function accountTextFromMethod(method) {
    if (!method) return 'Rekening belum dihubungkan';
    const parts = [];
    if (String(method.account_name   || '').trim()) parts.push(String(method.account_name));
    if (String(method.bank_name      || '').trim()) parts.push(String(method.bank_name));
    if (String(method.account_no     || '').trim()) parts.push(String(method.account_no));
    if (String(method.account_holder || '').trim()) parts.push('a/n ' + String(method.account_holder));
    return parts.length ? parts.join(' • ') : 'Rekening belum dihubungkan';
  }

  function orderStatusLabel(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      DRAFT:'Draft', PENDING:'Pending', CONFIRMED:'Terkonfirmasi', PAID:'Lunas',
      PAID_PARTIAL:'Bayar sebagian', READY:'Ready', SERVED:'Served',
      REFUND_PARTIAL:'Refund sebagian', REFUND_FULL:'Refund penuh',
      REFUNDED_FULL:'Refund penuh', VOID:'Void penuh'
    };
    return map[value] || (value ? value.replace(/_/g, ' ') : '-');
  }

  function orderStatusChip(status) {
    const value = String(status || '').toUpperCase();
    let klass = 'status-paid';
    if (value === 'PAID_PARTIAL' || value === 'READY' || value === 'SERVED') klass = 'status-partial';
    if (value.indexOf('REFUND') === 0) klass = 'status-refund';
    return `<span class="pos-paid-status-chip ${klass}">${escapeHtml(orderStatusLabel(value))}</span>`;
  }

  function lineStatusLabel(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      OPEN:'Aktif', ACTIVE:'Aktif', VOID:'Void penuh', VOID_PARTIAL:'Void sebagian',
      REFUNDED_FULL:'Refund penuh', REFUNDED_PARTIAL:'Refund sebagian'
    };
    return map[value] || (value ? value.replace(/_/g, ' ') : '-');
  }

  async function getJson(url) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response bukan JSON. ' + String(text || '').replace(/\s+/g, ' ').trim().slice(0, 240)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memuat data');
    return json;
  }

  async function postJson(url, payload, scopedOptions) {
    const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (scopedOptions && scopedOptions.headers && typeof scopedOptions.headers === 'object') {
      Object.assign(headers, scopedOptions.headers);
    }
    const response = await fetch(url, {
      method: 'POST',
      headers,
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response save bukan JSON. ' + String(text || '').replace(/\s+/g, ' ').trim().slice(0, 240)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal menyimpan data');
    return json;
  }

  function postPosTransactionJson(url, payload) {
    return postJson(url, payload, {
      headers: { 'X-Pos-Transaction-CSRF': posTransactionCsrfToken }
    });
  }

  async function postForm(url, params) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(params).toString()
    });
    const result = await response.json();
    if (!response.ok || !result || result.ok !== true) throw new Error((result && result.message) ? result.message : 'Gagal menyimpan data');
    return result;
  }

  /* ── Print helpers ──────────────────────────────────────── */
  function formatPrintFailureReason(reason) {
    const normalized = String(reason || '').trim();
    if (!normalized) return 'Servis printer lokal menolak permintaan cetak.';
    if (/^HTTP 500$/i.test(normalized)) return 'Servis printer lokal error internal (HTTP 500).';
    if (/^HTTP 404$/i.test(normalized)) return 'Endpoint cetak tidak ditemukan.';
    if (/failed to fetch|networkerror|load failed/i.test(normalized)) return 'Browser tidak bisa menjangkau servis printer lokal.';
    return normalized;
  }

  function normalizePrintFailureEntry(entry) {
    if (entry && typeof entry === 'object') {
      return { name: String(entry.name || entry.printer_name || 'Printer').trim() || 'Printer', reason: formatPrintFailureReason(entry.reason || entry.message || '') };
    }
    const raw = String(entry || '').trim();
    const sep = raw.indexOf(':');
    if (sep > 0) return { name: raw.slice(0, sep).trim() || 'Printer', reason: formatPrintFailureReason(raw.slice(sep + 1).trim()) };
    return { name: 'Printer', reason: formatPrintFailureReason(raw) };
  }

  async function acknowledgeDirectPrintTarget(target, status, message) {
    const attemptId = Number(target && target.print_attempt_id || 0);
    if (!attemptId) return;
    try {
      await fetch('<?= site_url('pos/printers/attempts/ack') ?>', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({attempt_id: attemptId, status, message: message || ''}) });
    } catch (error) { /* Monitor tidak boleh menghambat pembayaran. */ }
  }

  async function directPrintTargets(rows) {
    if (!Array.isArray(rows) || !rows.length) return { successCount: 0, failed: [] };
    const failed = []; let successCount = 0; const jobs = [];
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
      if (!pythonPort) { const message = 'python port belum valid'; failed.push(`${target.printer_name || target.printer_code || 'Printer'}: ${message}`); void acknowledgeDirectPrintTarget(target, 'FAILED', message); continue; }
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

  async function triggerRefundDirectPrint(refundId) {
    const safeId = Number(refundId || 0);
    if (safeId <= 0) return [];
    const payloadJson = await getJson(`<?php echo site_url('pos/orders/refund-print-targets'); ?>/${safeId}`);
    const printResult = await directPrintTargets(payloadJson.direct_print_targets || []);
    return (printResult.failed || []).map(normalizePrintFailureEntry);
  }

  async function triggerReceiptReprint(orderId) {
    const safeId = Number(orderId || 0);
    if (safeId <= 0) throw new Error('Order tidak valid untuk cetak ulang struk.');
    const payloadJson = await getJson(`<?php echo site_url('pos/orders/receipt-print-targets'); ?>/${safeId}`);
    const printResult = await directPrintTargets(payloadJson.direct_print_targets || []);
    return { successCount: Number(printResult.successCount || 0), failed: (printResult.failed || []).map(normalizePrintFailureEntry) };
  }

  /* ── Filter & paging ────────────────────────────────────── */
  function recentQs() {
    const p = new URLSearchParams();
    p.set('q',              recentState.q);
    p.set('status',         recentState.status);
    p.set('workspace_mode', recentState.workspace_mode);
    p.set('outlet_id',      recentState.outlet_id);
    p.set('date_from',      recentState.date_from);
    p.set('date_to',        recentState.date_to);
    p.set('page',           recentState.page);
    p.set('limit',          recentState.limit);
    return p.toString();
  }

  function syncRecentControls() {
    document.getElementById('recent_q').value         = recentState.q;
    document.getElementById('recent_outlet_id').value = String(recentState.outlet_id || 0);
    document.getElementById('recent_date_from').value = recentState.date_from;
    document.getElementById('recent_date_to').value   = recentState.date_to;
  }

  function renderRecentPager(meta) {
    const total = Number(meta.total || 0);
    const page  = Number(meta.page  || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || recentState.limit || 20);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end   = Math.min(total, page * limit);
    document.getElementById('recent_pagination_info').textContent = total
      ? `Menampilkan ${start}-${end} dari ${total} order`
      : 'Belum ada order';
    document.getElementById('recent_pagination').innerHTML = Array.from({ length: totalPages }, (_, idx) => {
      const p = idx + 1;
      return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`;
    }).join('');
    document.querySelectorAll('#recent_pagination button').forEach((btn) => btn.addEventListener('click', () => {
      recentState.page = Number(btn.dataset.page || 1);
      loadRecents().catch((e) => alert(e.message));
    }));
  }

  /* ── Payment history (with edit button) ────────────────── */
  function renderPaymentHistory(payments) {
    const target = document.getElementById('paid_detail_payment_history');
    if (!target) return;
    if (!Array.isArray(payments) || !payments.length) {
      target.innerHTML = '<div class="pos-paid-history-empty">Belum ada riwayat pembayaran.</div>';
      return;
    }
    target.innerHTML = payments.map((payment) => {
      const methodRows = Array.isArray(payment.lines) ? payment.lines : [];
      const paymentVoid = String(payment.payment_status || '').toUpperCase() === 'VOID';
      const linesHtml = methodRows.map((line) => {
        const lineStatus   = String(line.status || '').toUpperCase();
        const linePaid     = lineStatus === 'PAID';
        const showEditBtn  = canEditPaymentMethod && !paymentVoid && linePaid;
        return `
          <div class="pos-paid-pay-line-item" data-payment-line-row="${Number(line.id || 0)}">
            <div class="pos-paid-pay-line-head">
              <div>
                <div class="pos-paid-pay-line-method" data-method-target>${escapeHtml(line.method_name || '-')}</div>
                <div class="pos-paid-pay-line-account" data-account-target>${escapeHtml(line.company_account_name || 'Tanpa rekening')}</div>
                <div class="pos-paid-pay-line-meta">Ref ${escapeHtml(line.reference_no || '-')} • Status ${escapeHtml(line.status || '-')}</div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="pos-paid-pay-line-amount">${money(line.amount || 0)}</div>
                ${showEditBtn ? `<button type="button"
                  class="btn btn-sm btn-outline-primary pos-paid-pay-edit-btn js-edit-payment-line"
                  data-edit-trigger="1"
                  data-payment-line-id="${Number(line.id || 0)}"
                  data-payment-no="${escapeHtml(String(payment.payment_no || '-'))}"
                  data-line-no="${Number(line.line_no || 0)}"
                  data-current-method-id="${Number(line.payment_method_id || 0)}"
                  data-current-method-name="${escapeHtml(String(line.method_name || '-'))}"
                  data-current-account="${escapeHtml(String(line.company_account_name || 'Tanpa rekening'))}"
                  data-amount="${Number(line.amount || 0)}"
                  data-reference-no="${escapeHtml(String(line.reference_no || ''))}"
                  title="Edit metode pembayaran" aria-label="Edit metode pembayaran">
                  <i class="ri ri-edit-line"></i>
                </button>` : ''}
              </div>
            </div>
          </div>
        `;
      }).join('');
      return `
        <div class="pos-paid-history-item mb-2">
          <div class="pos-paid-history-head">
            <div>
              <div class="pos-paid-history-title">${escapeHtml(payment.payment_no || '-')}</div>
              <div class="pos-paid-history-meta">${escapeHtml(payment.paid_at || payment.created_at || '-')} • ${escapeHtml(payment.cashier_name || '-')}</div>
            </div>
            <div class="pos-paid-history-amount">${money(payment.net_amount || 0)}</div>
          </div>
          <div class="mt-2" style="border-top:1px solid rgba(224,209,198,.5)">${linesHtml}</div>
        </div>
      `;
    }).join('');

    target.querySelectorAll('.js-edit-payment-line').forEach((btn) => {
      btn.addEventListener('click', function () {
        openPaymentMethodEdit(btn);
      });
    });
  }

  /* ── Detail modal ───────────────────────────────────────── */
  async function openDetailModal(orderId) {
    if (orderId <= 0) throw new Error('Order tidak valid.');
    const json = await getJson('<?php echo site_url('pos/orders/draft/load'); ?>/' + orderId);
    const header   = json.header  || {};
    const lines    = Array.isArray(json.lines)    ? json.lines    : [];
    const payments = Array.isArray(json.payments) ? json.payments : [];
    const refunds  = Array.isArray(json.refunds)  ? json.refunds  : [];
    const customerName = header.customer_display_name || header.member_name || header.customer_name || 'Walk in';
    const paidTotal    = Number(header.paid_total  || 0);
    const grandTotal   = Number(header.grand_total || 0);
    const refundTotal  = refunds.reduce((sum, row) => sum + Number(row.refund_amount || 0), 0);
    const remaining    = Math.max(0, grandTotal - paidTotal);

    document.getElementById('paid_detail_meta').textContent = `${header.order_no || '-'} • ${customerName} • ${orderStatusLabel(header.status || '')}`;
    document.getElementById('paid_detail_total').textContent        = money(grandTotal);
    document.getElementById('paid_detail_paid_total').textContent   = money(paidTotal);
    document.getElementById('paid_detail_change_total').textContent = money(header.change_total || 0);
    document.getElementById('paid_detail_remaining').textContent    = money(remaining);
    document.getElementById('paid_detail_alert').innerHTML          = '';

    const headerGrid = document.getElementById('paid_detail_header_grid');
    const headerRows = [
      ['Customer', customerName],
      ['Member', header.member_no || '-'],
      ['Outlet', header.outlet_name || '-'],
      ['Terminal', header.terminal_name || 'Tanpa Terminal'],
      ['Status Order', orderStatusLabel(header.status || '-')],
      ['Service', header.service_type || '-'],
      ['Scope', header.order_scope || '-'],
      ['Guest', number(header.guest_count || 0, 0)],
      ['Meja', header.table_no || '-'],
      ['Kasir', header.cashier_employee_name || header.cashier_username || '-'],
      ['Order Time', header.ordered_at || '-'],
      ['Paid At', header.paid_at || '-'],
      ['Sales Channel', header.sales_channel_name || header.sales_channel_code || '-'],
      ['Stock Commit', header.stock_commit_status || '-'],
      ['Total Payment', `${payments.length} transaksi`],
      ['Total Refund', `${refunds.length} refund • ${money(refundTotal)}`],
      ['Catatan', header.notes || '-']
    ];
    headerGrid.innerHTML = headerRows.map((row) => `
      <div class="pos-paid-detail-item">
        <div class="pos-paid-detail-label">${escapeHtml(row[0])}</div>
        <div class="pos-paid-detail-value">${escapeHtml(row[1])}</div>
      </div>
    `).join('');

    document.getElementById('paid_detail_line_body').innerHTML = lines.map((line) => {
      const extras = Array.isArray(line.extras) ? line.extras : [];
      const extraHtml = extras.length
        ? `<div class="pos-paid-mini-note mt-1">${extras.map((extra) => `+ ${escapeHtml(extra.extra_name || '-')} (${number(extra.qty || 0, 2)} x ${money(extra.unit_price || 0)})`).join('<br>')}</div>`
        : '';
      return `
        <tr>
          <td>
            <div class="fw-semibold">${escapeHtml(line.product_name || '-')}</div>
            <div class="pos-paid-mini-note">${escapeHtml(line.product_code || '-')} | ${escapeHtml(line.product_division_name || '-')} | ${escapeHtml(line.uom_code || '-')}</div>
            ${extraHtml}
          </td>
          <td class="text-center">${number(line.qty || 0, 2)}</td>
          <td class="text-end">${money(line.unit_price || 0)}</td>
          <td class="text-end fw-semibold">${money(Number(line.net_amount || 0) + extras.reduce((sum, extra) => sum + Number(extra.net_amount || 0), 0))}</td>
          <td>${escapeHtml(lineStatusLabel(line.line_status || '-'))}</td>
          <td>${escapeHtml(line.notes || '-')}</td>
        </tr>
      `;
    }).join('');

    renderPaymentHistory(payments);

    const refundHistTarget = document.getElementById('paid_detail_refund_history');
    if (!Array.isArray(refunds) || !refunds.length) {
      refundHistTarget.innerHTML = '<div class="pos-paid-history-empty">Belum ada riwayat refund.</div>';
    } else {
      refundHistTarget.innerHTML = refunds.map((refund) => `
        <div class="pos-paid-history-item">
          <div class="pos-paid-history-head">
            <div>
              <div class="pos-paid-history-title">${escapeHtml(refund.refund_no || '-')}</div>
              <div class="pos-paid-history-meta">${escapeHtml(refund.refunded_at || '-')} • ${escapeHtml(refund.refunded_by_name || '-')}</div>
            </div>
            <div class="pos-paid-history-amount">${money(refund.refund_amount || 0)}</div>
          </div>
          <div class="pos-paid-history-lines">${escapeHtml(refund.method_name || '-')} • ${escapeHtml(refund.company_account_name || 'Tanpa rekening')}<br>${escapeHtml(refund.reason || '-')}</div>
        </div>
      `).join('');
    }

    if (detailModal) detailModal.show();
  }

  /* ── Edit payment method ─────────────────────────────────── */
  function updatePayMethodAccountPreview() {
    if (!payMethodField || !payMethodAccountEl) return;
    payMethodAccountEl.textContent = accountTextFromMethod(paymentMethodMap[String(payMethodField.value || '')] || null);
  }

  function openPaymentMethodEdit(trigger) {
    if (!payMethodEditModal) return;
    activePaymentTrigger = trigger;
    if (payMethodTitleEl) payMethodTitleEl.textContent = (trigger.dataset.paymentNo || '-') + ' • line ' + (trigger.dataset.lineNo || '-');
    if (payMethodMetaEl)  payMethodMetaEl.textContent  = 'Nominal ' + money(trigger.dataset.amount || 0) + ' • Referensi ' + (trigger.dataset.referenceNo || '-');
    if (payMethodCurrentEl) payMethodCurrentEl.textContent = (trigger.dataset.currentMethodName || '-') + ' • ' + (trigger.dataset.currentAccount || 'Rekening belum dihubungkan');
    if (payMethodField) {
      payMethodField.value = String(trigger.dataset.currentMethodId || '');
    }
    if (payMethodFlashEl) payMethodFlashEl.classList.add('d-none');
    updatePayMethodAccountPreview();
    payMethodEditModal.show();
  }

  if (payMethodField) {
    payMethodField.addEventListener('change', updatePayMethodAccountPreview);
  }

  if (payMethodSaveBtn) {
    payMethodSaveBtn.addEventListener('click', async function () {
      if (!activePaymentTrigger) return;
      const paymentLineId   = Number(activePaymentTrigger.dataset.paymentLineId || 0);
      const paymentMethodId = Number(payMethodField?.value || 0);
      if (paymentLineId <= 0) {
        setFlashIn(payMethodFlashEl, 'danger', 'Line pembayaran tidak valid.');
        return;
      }
      if (paymentMethodId <= 0) {
        setFlashIn(payMethodFlashEl, 'warning', 'Pilih metode pembayaran terlebih dahulu.');
        return;
      }
      const origHtml = payMethodSaveBtn.innerHTML;
      payMethodSaveBtn.disabled = true;
      payMethodSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
      try {
        const result = await postForm('<?php echo site_url('pos/reports/sales/payment-line/update'); ?>/' + paymentLineId, { payment_method_id: String(paymentMethodId) });
        const line = result.line || {};
        /* Update DOM di modal detail */
        const row = document.querySelector('[data-payment-line-row="' + paymentLineId + '"]');
        if (row) {
          const methodTarget  = row.querySelector('[data-method-target]');
          const accountTarget = row.querySelector('[data-account-target]');
          const methodName    = String(line.method_name || '-');
          const accountName   = [line.company_account_name || '', line.company_bank_name || '', line.company_account_code || ''].filter((p) => String(p).trim()).join(' • ') || 'Tanpa rekening';
          if (methodTarget)  methodTarget.textContent  = methodName;
          if (accountTarget) accountTarget.textContent = accountName;
          const editTrigger = row.querySelector('[data-edit-trigger]');
          if (editTrigger) {
            editTrigger.dataset.currentMethodId   = String(line.payment_method_id || paymentMethodId);
            editTrigger.dataset.currentMethodName = methodName;
            editTrigger.dataset.currentAccount    = accountName;
          }
        }
        setFlashIn(document.getElementById('paid_detail_alert'), 'success', result.message || 'Metode pembayaran berhasil diperbarui.');
        if (payMethodEditModal) payMethodEditModal.hide();
      } catch (error) {
        setFlashIn(payMethodFlashEl, 'danger', error && error.message ? error.message : 'Gagal memperbarui metode pembayaran.');
      } finally {
        payMethodSaveBtn.disabled = false;
        payMethodSaveBtn.innerHTML = origHtml;
      }
    });
  }

  /* ── Refund helpers ─────────────────────────────────────── */
  function fillRefundReasonOptions() {
    const rows = Array.isArray(reversalReasonOptions.REFUND) ? reversalReasonOptions.REFUND : [];
    refundReasonCode.innerHTML = ['<option value="">Pilih alasan...</option>']
      .concat(rows.map((row) => `<option value="${escapeHtml(row.code || '')}">${escapeHtml(row.label || '')}</option>`))
      .join('');
    refundReasonOther.value = '';
    refundReasonOther.classList.add('d-none');
  }

  function refundUsesStockReturn() { return !!document.getElementById('refund_policy_return')?.checked; }

  function sanitizeWholeQty(rawValue, maxValue) {
    const parsed = Number(String(rawValue ?? '').replace(',', '.'));
    const roundedMax = Math.max(0, Math.round(Number(maxValue || 0)));
    if (!Number.isFinite(parsed) || parsed <= 0) return 0;
    const rounded = Math.round(parsed);
    if (roundedMax <= 0) return Math.max(0, rounded);
    return Math.min(roundedMax, Math.max(0, rounded));
  }

  function normalizeRefundQtyInput(input) {
    if (!input) return;
    const maxValue = Number(input.dataset.maxQty || 0);
    input.value = String(sanitizeWholeQty(input.value, maxValue));
  }

  function refreshRefundPolicyCards() {
    const usesReturn = refundUsesStockReturn();
    document.getElementById('refund_policy_return_card')?.classList.toggle('active', usesReturn);
    document.getElementById('refund_policy_adjust_card')?.classList.toggle('active', !usesReturn);
    refundReasonWrap.classList.toggle('d-none', usesReturn);
    refundAdjustmentWrap?.classList.toggle('d-none', usesReturn);
    const adjustmentField = document.getElementById('refund_adjustment_mode');
    if (adjustmentField) {
      if (usesReturn) adjustmentField.value = 'NONE';
      adjustmentField.disabled = usesReturn;
    }
    refreshRefundReasonHint();
  }

  function refreshRefundReasonHint() {
    if (!refundReasonHint) return;
    if (refundUsesStockReturn()) {
      refundReasonHint.textContent = '';
      return;
    }
    const adjustmentMode = String(document.getElementById('refund_adjustment_mode')?.value || 'AUTO_WASTE').toUpperCase();
    if (adjustmentMode === 'AUTO_WASTE') {
      refundReasonHint.textContent = 'Alasan ini disimpan sebagai reason waste. Bahan baku: waste_reason_code. Komponen: waste_reason_code.';
      return;
    }
    if (adjustmentMode === 'AUTO_SPOIL') {
      refundReasonHint.textContent = 'Alasan ini disimpan sebagai reason spoil. Bahan baku: spoil_reason_code. Komponen: spoil_reason_code.';
      return;
    }
    refundReasonHint.textContent = 'Alasan ini disimpan sebagai reason penyesuaian minus. Bahan baku: variance_reason_code. Komponen: adjustment_minus_reason_code.';
  }

  function refreshRefundReasonOther() {
    refundReasonOther.classList.toggle('d-none', String(refundReasonCode.value || '').toUpperCase() !== 'OTHER');
  }

  function refreshRefundAccountInfo() {
    const method = getRefundMethodMeta();
    if (!method) { refundAccountInfo.textContent = 'Pilih metode refund untuk melihat rekening perusahaan yang akan dipakai.'; return; }
    refundAccountInfo.textContent = 'Refund akan diposting ke rekening: ' + accountTextFromMethod(method);
  }

  function updateRefundSubmitState() {
    if (!refundSubmitButton) return;
    refundSubmitButton.disabled = refundSubmitInFlight;
    refundSubmitButton.innerHTML = refundSubmitInFlight
      ? '<span class="pos-paid-btn-spinner me-2" aria-hidden="true"></span>Menyimpan...'
      : 'Simpan Refund';
  }

  function resetRefundForm() {
    document.getElementById('refund_policy_return').checked = true;
    document.getElementById('refund_policy_adjust').checked = false;
    document.getElementById('refund_adjustment_mode').value = 'NONE';
    document.getElementById('refund_reason').value = '';
    if (refundReasonCode) refundReasonCode.value = '';
    if (refundReasonOther) {
      refundReasonOther.value = '';
      refundReasonOther.classList.add('d-none');
    }
    refundMethodField.value = '';
    refundReferenceField.value = '';
    if (refundStepUpPassword) refundStepUpPassword.value = '';
    fillRefundReasonOptions();
    refreshRefundPolicyCards();
    refreshRefundAccountInfo();
  }

  function processStatusLabel(status) {
    return String(status || '').toUpperCase() === 'NOT_PROCESSED' ? 'Belum diproses' : 'Sudah diproses';
  }

  function toggleAllRefundLines(checked) {
    document.querySelectorAll('.pos-paid-refund-line').forEach((card) => {
      const productToggle = card.querySelector('.refund-product-toggle');
      const extraToggles = card.querySelectorAll('.refund-extra-toggle');
      if (productToggle) productToggle.checked = checked;
      if (!checked) extraToggles.forEach((t) => { t.checked = false; });
    });
    syncRefundSelections();
  }

  function syncRefundSelections() {
    document.querySelectorAll('.pos-paid-refund-line').forEach((card) => {
      const productToggle = card.querySelector('.refund-product-toggle');
      const productQty    = card.querySelector('.refund-product-qty');
      const extraRows     = card.querySelectorAll('.pos-paid-refund-extra-row');
      if (!productToggle) return;
      const productSelected = productToggle.checked;
      if (productQty) { normalizeRefundQtyInput(productQty); productQty.disabled = !productSelected; }
      extraRows.forEach((row) => {
        const extraToggle = row.querySelector('.refund-extra-toggle');
        const extraQty    = row.querySelector('.refund-extra-qty');
        const autoHint    = row.querySelector('.refund-extra-auto-hint');
        if (!extraToggle || !extraQty) return;
        if (productSelected) {
          extraToggle.checked = true; extraToggle.disabled = true;
          normalizeRefundQtyInput(extraQty); extraQty.disabled = true;
          autoHint?.classList.remove('d-none');
        } else {
          extraToggle.disabled = false; normalizeRefundQtyInput(extraQty);
          extraQty.disabled = !extraToggle.checked; autoHint?.classList.add('d-none');
        }
      });
    });
  }

  function finalRefundReason() {
    const auditNote = String(document.getElementById('refund_reason').value || '').trim();
    if (refundUsesStockReturn()) return auditNote;
    const selectedCode = String(refundReasonCode.value || '').trim();
    if (selectedCode === '') throw new Error('Pilih alasan refund ketika stok tidak dikembalikan.');
    const rows = Array.isArray(reversalReasonOptions.REFUND) ? reversalReasonOptions.REFUND : [];
    const matched = rows.find((row) => String(row.code || '') === selectedCode);
    let reasonText = matched && matched.label ? String(matched.label) : selectedCode;
    if (selectedCode === 'OTHER') {
      const otherText = String(refundReasonOther.value || '').trim();
      if (otherText === '') throw new Error('Isi alasan lainnya untuk refund ini.');
      reasonText = otherText;
    }
    return auditNote ? `${reasonText} | ${auditNote}` : reasonText;
  }

  function renderRefundPreview(json) {
    refundPreview = json;
    const orderHeader = json.order && json.order.header ? json.order.header : {};
    document.getElementById('refund_modal_meta').textContent = `${orderHeader.order_no || '-'} • ${orderStatusLabel(orderHeader.status || '')} • ${orderHeader.customer_name || orderHeader.member_name || 'Walk in'}`;
    const list = document.getElementById('refund_line_list');
    const emptyHint = document.getElementById('refund_empty_hint');
    const orderLines = Array.isArray(json.order?.lines) ? json.order.lines : [];
    if (!orderLines.length) { emptyHint.classList.remove('d-none'); list.innerHTML = ''; return; }
    emptyHint.classList.add('d-none');
    resetRefundForm();
    list.innerHTML = orderLines.map((line) => {
      const processed  = String(line.process_status || 'NOT_PROCESSED').toUpperCase();
      const isProcessed = processed !== 'NOT_PROCESSED';
      const extras = Array.isArray(line.extras) ? line.extras : [];
      return `
        <div class="pos-paid-refund-line" data-line-id="${Number(line.id || 0)}">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <input class="form-check-input refund-product-toggle" type="checkbox">
                <div class="fw-semibold">${escapeHtml(line.product_name || '-')}</div>
              </div>
              <div class="pos-paid-mini-note">${escapeHtml(line.product_code || '-')} | Qty ${number(line.qty || 0, 0)} | Status item ${escapeHtml(lineStatusLabel(line.line_status || '-'))}</div>
              ${extras.length ? '<div class="pos-paid-mini-note mt-2">Pilih produk = extra ikut otomatis. Untuk refund extra saja, kosongkan produk lalu pilih extra di bawah.</div>' : ''}
            </div>
            <span class="pos-paid-refund-flag ${isProcessed ? 'adjust' : 'return'}">${escapeHtml(isProcessed ? 'Sudah Diproses' : 'Belum Diproses')}</span>
          </div>
          <div class="row g-2 mt-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">Qty Produk</label>
              <input type="number" class="form-control form-control-sm refund-product-qty" min="0" step="1" inputmode="numeric" data-max-qty="${sanitizeWholeQty(line.qty || 0, line.qty || 0)}" value="${sanitizeWholeQty(line.qty || 0, line.qty || 0)}">
            </div>
            <div class="col-md-9">
              <div class="small text-muted">Status proses fisik: <strong>${escapeHtml(processStatusLabel(processed))}</strong>${isProcessed ? ' • Sistem tetap rollback stok dan LOT lebih dulu. Jika dipilih tidak kembalikan stok, sistem lanjut posting adjustment.' : ''}</div>
            </div>
          </div>
          ${extras.length ? `<div class="pos-paid-refund-extra-list">${extras.map((extra) => `
            <div class="pos-paid-refund-extra-row" data-extra-id="${Number(extra.id || 0)}">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <label class="d-flex align-items-center gap-2 mb-0">
                  <input class="form-check-input refund-extra-toggle" type="checkbox">
                  <span class="fw-semibold">${escapeHtml(extra.extra_name || '-')}</span>
                </label>
                <span class="refund-extra-auto-hint small text-success d-none">Ikut otomatis saat produk dipilih</span>
              </div>
              <div class="d-flex flex-wrap gap-3 align-items-center mt-2">
                <div>
                  <div class="small text-muted">Qty Extra</div>
                  <input type="number" class="form-control form-control-sm refund-extra-qty" min="0" step="1" inputmode="numeric" data-max-qty="${sanitizeWholeQty(extra.qty || 0, extra.qty || 0)}" value="${sanitizeWholeQty(extra.qty || 0, extra.qty || 0)}" disabled>
                </div>
                <div class="small text-muted">Harga tambahan ${money(extra.unit_price || 0)}</div>
              </div>
            </div>`).join('')}</div>` : ''}
        </div>
      `;
    }).join('');
    syncRefundSelections();
    list.querySelectorAll('.refund-product-toggle, .refund-extra-toggle').forEach((field) => field.addEventListener('change', syncRefundSelections));
    list.querySelectorAll('.refund-product-qty, .refund-extra-qty').forEach((field) => {
      field.addEventListener('input', () => normalizeRefundQtyInput(field));
      field.addEventListener('change', () => normalizeRefundQtyInput(field));
    });
  }

  async function openRefundModal(orderId) {
    if (orderId <= 0) throw new Error('Order tidak valid.');
    const json = await getJson('<?php echo site_url('pos/orders/reversal-preview'); ?>/' + orderId);
    renderRefundPreview(json);
    if (refundModal) refundModal.show();
  }

  function buildRefundPayload() {
    if (!refundPreview || !refundPreview.order || !refundPreview.order.header) throw new Error('Preview refund belum dimuat.');
    const orderLineMap = new Map((refundPreview.order.lines || []).map((line) => [Number(line.id || 0), line]));
    const reasonCode = String(refundReasonCode?.value || 'OTHER').trim() || 'OTHER';
    const reason = finalRefundReason();
    const returnToStock = refundUsesStockReturn();
    const adjustmentMode = returnToStock ? 'NONE' : (document.getElementById('refund_adjustment_mode').value || 'NONE');
    if (!returnToStock && adjustmentMode === 'NONE') throw new Error('Pilih tipe adjustment ketika stok tidak dikembalikan.');
    const lines = [];
    document.querySelectorAll('.pos-paid-refund-line').forEach((card) => {
      const orderLineId = Number(card.dataset.lineId || 0);
      const sourceLine  = orderLineMap.get(orderLineId);
      if (!sourceLine || orderLineId <= 0) return;
      const productToggle  = card.querySelector('.refund-product-toggle');
      const productQty     = card.querySelector('.refund-product-qty');
      const productSelected = !!(productToggle && productToggle.checked);
      const qty = productSelected ? sanitizeWholeQty(productQty?.value || 0, sourceLine.qty || 0) : 0;
      const extraSelections = [];
      card.querySelectorAll('.pos-paid-refund-extra-row').forEach((row) => {
        const extraToggle      = row.querySelector('.refund-extra-toggle');
        const extraQty         = row.querySelector('.refund-extra-qty');
        const orderLineExtraId = Number(row.dataset.extraId || 0);
        if (!extraToggle || !extraToggle.checked || orderLineExtraId <= 0) return;
        const sourceExtra = (Array.isArray(sourceLine.extras) ? sourceLine.extras : []).find((extra) => Number(extra.id || 0) === orderLineExtraId) || null;
        extraSelections.push({
          order_line_extra_id: orderLineExtraId,
          qty: sanitizeWholeQty(extraQty?.value || 0, sourceExtra?.qty || 0),
          processed_state: String(sourceLine.process_status || 'NOT_PROCESSED').toUpperCase(),
          return_to_stock: returnToStock,
          notes: reason,
        });
      });
      if (!productSelected && !extraSelections.length) return;
      lines.push({
        order_line_id: orderLineId,
        qty,
        processed_state: String(sourceLine.process_status || 'NOT_PROCESSED').toUpperCase(),
        return_to_stock: returnToStock,
        notes: reason,
        extras: extraSelections.filter((extra) => extra.order_line_extra_id > 0 && extra.qty > 0),
      });
    });
    if (!refundMethodField.value) throw new Error('Pilih metode refund terlebih dulu.');
    return {
      kind: 'REFUND',
      order_id: Number(refundPreview.order.header.id || 0),
      return_to_stock: returnToStock ? 1 : 0,
      adjustment_mode: adjustmentMode,
      reason_code: reasonCode,
      reason,
      payment_method_id: Number(refundMethodField.value || 0),
      reference_no: String(refundReferenceField.value || ''),
      lines: lines.filter((line) => line.order_line_id > 0 && (line.qty > 0 || (Array.isArray(line.extras) && line.extras.length > 0)))
    };
  }

  async function submitRefund() {
    if (refundSubmitInFlight) throw new Error('Refund sedang diproses.');
    refundSubmitInFlight = true;
    updateRefundSubmitState();
    let completed = false;
    const payload = buildRefundPayload();
    try {
      if (!payload.lines.length) throw new Error('Tidak ada line yang bisa diproses untuk refund.');
      const password = String(refundStepUpPassword?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi ulang refund.');
      if (refundStepUpPassword) refundStepUpPassword.value = '';
      const stepUp = await postPosTransactionJson('<?php echo site_url('pos/orders/reversal-step-up/verify'); ?>', {
        action: 'REFUND',
        order_id: payload.order_id,
        password
      });
      if (!/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) {
        throw new Error('Bukti verifikasi ulang tidak valid. Coba lagi.');
      }
      payload.step_up_proof = String(stepUp.step_up_proof);
      const json = await postPosTransactionJson('<?php echo site_url('pos/orders/refund/save'); ?>', payload);
      if (refundModal) refundModal.hide();
      let printFailures = [];
      try { printFailures = await triggerRefundDirectPrint(Number(json.id || 0)); }
      catch (e) { printFailures = [normalizePrintFailureEntry({ name: 'Printer', reason: e && e.message ? e.message : 'Gagal menyiapkan direct print refund' })]; }
      let message = `Refund berhasil disimpan.\nNo Refund: ${json.refund_no || '-'}`;
      if (Number(json.adjustment_doc_count || 0) > 0) {
        message += `\nAdjustment terposting: ${Number(json.adjustment_doc_count || 0)}`;
      }
      if (printFailures.length) { message += '\n\nSebagian printer gagal menerima slip refund:\n'; message += printFailures.map((entry) => `- ${entry.name}: ${entry.reason}`).join('\n'); }
      alert(message);
      await loadRecents();
      completed = true;
    } finally {
      if (!completed) {
        refundSubmitInFlight = false;
        updateRefundSubmitState();
      }
    }
  }

  /* ── Load data ──────────────────────────────────────────── */
  async function loadRecents() {
    syncRecentControls();
    const json = await getJson('<?php echo site_url('pos/orders/draft/data'); ?>?' + recentQs());
    const rows  = Array.isArray(json.rows) ? json.rows : [];
    const body  = document.getElementById('recent_body');
    const empty = document.getElementById('recent_empty_state');
    if (!rows.length) {
      body.innerHTML = '';
      empty.classList.remove('d-none');
    } else {
      empty.classList.add('d-none');
      body.innerHTML = rows.map((row) => {
        const rowStatus    = String(row.status || '').toUpperCase();
        const disableRefund = ['REFUND_FULL', 'REFUNDED_FULL', 'VOID'].includes(rowStatus);
        const customerName  = row.customer_display_name || row.member_name || 'Walk in';
        return `
          <tr>
            <td>
              <div class="fw-semibold">${escapeHtml(row.order_no || '-')}</div>
              <div class="pos-paid-mini-note">${escapeHtml(row.service_type || '-')} | ${escapeHtml(row.ordered_at || '-')}</div>
            </td>
            <td>
              <div>${escapeHtml(customerName)}</div>
              <div class="pos-paid-mini-note">${escapeHtml(row.member_no || row.table_no || '-')}</div>
            </td>
            <td>
              <div>${escapeHtml(row.outlet_name || '-')}</div>
              <div class="pos-paid-mini-note">${escapeHtml(row.terminal_name || 'Tanpa Terminal')}</div>
            </td>
            <td>${escapeHtml(row.employee_name || '-')}</td>
            <td class="text-center">${orderStatusChip(row.status || '-')}</td>
            <td class="text-end fw-semibold">${money(row.grand_total || 0)}</td>
            <td class="text-center">
              <div class="pos-paid-action-stack">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-paid-print" data-id="${Number(row.id || 0)}" title="Cetak Struk" aria-label="Cetak Struk"><i class="ri ri-printer-line"></i></button>
                <button type="button" class="btn btn-sm btn-outline-info btn-paid-detail" data-id="${Number(row.id || 0)}" title="Detail" aria-label="Detail"><i class="ri ri-eye-line"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-paid-refund" data-id="${Number(row.id || 0)}" ${disableRefund ? 'disabled' : ''} title="Refund" aria-label="Refund"><i class="ri ri-arrow-go-back-line"></i></button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }
    renderRecentPager(json.meta || {});
    document.querySelectorAll('.btn-paid-print').forEach((btn) => btn.addEventListener('click', async () => {
      const originalHtml = btn.innerHTML;
      btn.disabled = true; btn.innerHTML = '<span class="pos-paid-btn-spinner" aria-hidden="true"></span>';
      try {
        const result = await triggerReceiptReprint(Number(btn.dataset.id || 0));
        let message = result.successCount > 0 ? `Struk berhasil dikirim ke ${result.successCount} printer.` : 'Tidak ada printer yang menerima struk.';
        if (result.failed.length) { message += '\n\nSebagian printer gagal:\n'; message += result.failed.map((entry) => `- ${entry.name}: ${entry.reason}`).join('\n'); }
        alert(message);
      } catch (e) { alert(e && e.message ? e.message : 'Gagal menyiapkan cetak ulang struk.'); }
      finally { btn.disabled = false; btn.innerHTML = originalHtml; }
    }));
    document.querySelectorAll('.btn-paid-detail').forEach((btn) => btn.addEventListener('click', async () => {
      try { await openDetailModal(Number(btn.dataset.id || 0)); } catch (e) { alert(e.message); }
    }));
    document.querySelectorAll('.btn-paid-refund').forEach((btn) => btn.addEventListener('click', async () => {
      try { await openRefundModal(Number(btn.dataset.id || 0)); } catch (e) { alert(e.message); }
    }));
  }

  /* ── Event bindings ─────────────────────────────────────── */
  document.getElementById('btn-apply-filter').addEventListener('click', () => {
    recentState.q         = document.getElementById('recent_q').value;
    recentState.outlet_id = Number(document.getElementById('recent_outlet_id').value || 0);
    recentState.date_from = document.getElementById('recent_date_from').value || today;
    recentState.date_to   = document.getElementById('recent_date_to').value   || today;
    recentState.page = 1;
    loadRecents().catch((err) => alert(err.message));
  });

  document.getElementById('recent_q').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      recentState.q = e.target.value;
      recentState.page = 1;
      loadRecents().catch((err) => alert(err.message));
    }
  });

  document.getElementById('btn-clear-recent').addEventListener('click', () => {
    recentState.q         = '';
    recentState.status    = 'PAID';
    recentState.outlet_id = 0;
    recentState.date_from = today;
    recentState.date_to   = today;
    recentState.page      = 1;
    loadRecents().catch((err) => alert(err.message));
  });

  document.getElementById('refund_policy_return')?.addEventListener('change', refreshRefundPolicyCards);
  document.getElementById('refund_policy_adjust')?.addEventListener('change', refreshRefundPolicyCards);
  document.getElementById('refund_adjustment_mode')?.addEventListener('change', refreshRefundReasonHint);
  refundReasonCode?.addEventListener('change', refreshRefundReasonOther);
  refundMethodField?.addEventListener('change', refreshRefundAccountInfo);
  document.getElementById('refund_check_all')?.addEventListener('click', () => toggleAllRefundLines(true));
  document.getElementById('refund_uncheck_all')?.addEventListener('click', () => toggleAllRefundLines(false));
  document.getElementById('btn-save-refund').addEventListener('click', async () => {
    try { await submitRefund(); } catch (e) { alert(e.message); }
  });
  refundModalEl?.addEventListener('hidden.bs.modal', () => {
    refundSubmitInFlight = false;
    updateRefundSubmitState();
  });

  /* ── Init ───────────────────────────────────────────────── */
  fillRefundReasonOptions();
  refreshRefundPolicyCards();
  refreshRefundAccountInfo();
  updateRefundSubmitState();
  loadRecents().catch((e) => alert(e.message));
});
</script>
<?php $this->load->view('pos/_direct_print_prompt'); ?>
