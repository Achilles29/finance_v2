<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
$terminals = is_array($filterOptions['terminals'] ?? null) ? $filterOptions['terminals'] : [];
$refundPaymentMethods = is_array($filterOptions['refund_payment_methods'] ?? null) ? $filterOptions['refund_payment_methods'] : [];
$reversalReasonOptions = is_array($filterOptions['reversal_reason_options'] ?? null) ? $filterOptions['reversal_reason_options'] : [];
$workspaceMode = strtoupper(trim((string)($workspace_mode ?? 'UNPAID')));
$isPaidWorkspace = $workspaceMode === 'PAID';
?>

<style>
  .pos-order-shell { display:grid; gap:1rem; }
  .pos-order-main { border:0; border-radius:22px; box-shadow:0 18px 40px rgba(58, 38, 30, .08); overflow:visible; }
  .pos-order-search-wrap { position:relative; }
  .pos-order-search-result {
    position:absolute; top:calc(100% + .45rem); left:0; right:0; z-index:1050;
    background:#fff; border:1px solid rgba(201, 183, 168, .72); border-radius:16px;
    box-shadow:0 16px 36px rgba(61, 38, 27, .14); overflow:hidden;
  }
  .pos-order-search-item { display:flex; justify-content:space-between; gap:.8rem; padding:.85rem 1rem; cursor:pointer; border-bottom:1px solid rgba(232, 220, 210, .9); }
  .pos-order-search-item:last-child { border-bottom:0; }
  .pos-order-search-item:hover { background:#fff7f2; }
  .pos-order-search-name { font-weight:800; color:#382a2b; }
  .pos-order-search-meta { font-size:.78rem; color:#8a776d; }
  .pos-order-member-card {
    border:1px solid rgba(224, 209, 198, .7); border-radius:16px; padding:.85rem 1rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .pos-order-member-empty { color:#8b7a70; font-size:.86rem; }
  .pos-order-member-title { font-weight:800; color:#3a2b2b; }
  .pos-order-member-meta { font-size:.8rem; color:#7b6b63; }
  .pos-order-search-mode .btn.active { background:#8f3d33; color:#fff; border-color:#8f3d33; }
  .pos-order-bundle-chip {
    display:inline-flex; align-items:center; gap:.35rem; padding:.18rem .5rem; border-radius:999px;
    background:#fff0de; color:#9a4e0f; font-size:.72rem; font-weight:800;
  }
  .pos-order-line-table th { white-space:nowrap; }
  .pos-order-line-table td { vertical-align:middle; }
  .pos-order-availability { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700; }
  .pos-order-availability.ok { background:#e8f8ec; color:#1d7f45; }
  .pos-order-availability.warn { background:#fff4dd; color:#8d5a00; }
  .pos-order-availability.out { background:#fde8e8; color:#b42318; }
  .pos-order-summary-card { background:linear-gradient(135deg,#fff9f5 0%,#fff 100%); border:1px solid rgba(224, 209, 198, .7); border-radius:18px; padding:1rem 1.2rem; }
  .pos-order-summary-kpi { font-size:1.55rem; font-weight:900; color:#33272a; }
  .pos-order-empty {
    border:1px dashed rgba(189, 170, 154, .6); border-radius:16px; padding:1.4rem; text-align:center;
    color:#8b7a70; background:#fffaf6;
  }
  .pos-order-draft-row { cursor:pointer; }
  .pos-order-draft-row:hover { background:#fff7f2; }
  .pos-order-mini-note { font-size:.78rem; color:#89756c; }
  .pos-order-status-stack {
    display:inline-flex;
    flex-direction:column;
    align-items:center;
    gap:.35rem;
  }
  .pos-order-status-chip {
    display:inline-flex;
    align-items:center;
    gap:.28rem;
    padding:.2rem .58rem;
    border-radius:999px;
    font-size:.7rem;
    font-weight:900;
    white-space:nowrap;
  }
  .pos-order-status-chip.order-draft { background:#fff3cd; color:#8a5700; }
  .pos-order-status-chip.order-confirmed { background:#dcfce7; color:#166534; }
  .pos-order-status-chip.commit-queued { background:#e0f2fe; color:#075985; }
  .pos-order-status-chip.commit-processing { background:#ede9fe; color:#5b21b6; }
  .pos-order-status-chip.commit-posted { background:#dcfce7; color:#166534; }
  .pos-order-status-chip.commit-failed { background:#fee2e2; color:#b91c1c; }
  .pos-order-status-chip.commit-reversed { background:#f1f5f9; color:#334155; }
  .pos-reversal-line {
    border:1px solid rgba(224, 209, 198, .75); border-radius:14px; padding:.85rem 1rem;
    background:linear-gradient(135deg,#fffaf6 0%,#fff 100%);
  }
  .pos-reversal-line + .pos-reversal-line { margin-top:.7rem; }
  .pos-reversal-flag {
    display:inline-flex; align-items:center; gap:.35rem; padding:.18rem .55rem; border-radius:999px;
    font-size:.72rem; font-weight:800;
  }
  .pos-reversal-flag.return { background:#e8f8ec; color:#1d7f45; }
  .pos-reversal-flag.adjust { background:#fff4dd; color:#8d5a00; }
  .pos-reversal-policy-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.85rem; }
  .pos-reversal-policy-card {
    border:1px solid rgba(224, 209, 198, .75); border-radius:18px; padding:1rem 1.1rem;
    background:linear-gradient(135deg,#fffaf6 0%,#fff 100%); cursor:pointer;
  }
  .pos-reversal-policy-card.active { border-color:#8f3d33; box-shadow:0 12px 24px rgba(143,61,51,.12); }
  .pos-reversal-policy-title { font-weight:800; color:#3a2b2b; }
  .pos-reversal-policy-note { font-size:.8rem; color:#7b6b63; }
  .pos-reversal-section-title { font-size:.78rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#8a776d; }
  .pos-reversal-extra-list { margin-top:.85rem; display:grid; gap:.55rem; }
  .pos-reversal-extra-row {
    border:1px dashed rgba(214, 195, 182, .9); border-radius:14px; padding:.7rem .85rem;
    background:#fff;
  }
  .pos-reversal-qty-input { width:92px; }
  .pos-reversal-help { background:#fff7f2; border:1px solid rgba(224, 209, 198, .85); border-radius:16px; padding:.8rem .95rem; color:#755f56; }
  .pos-reversal-summary-note { font-size:.78rem; color:#8b7a70; }
  @media (max-width: 991.98px) {
    .pos-order-main .btn { width:100%; }
    .pos-reversal-policy-grid { grid-template-columns:1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_order_workspace_tabs', ['order_workspace_active' => $isPaidWorkspace ? 'paid' : 'draft']); ?>
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo $isPaidWorkspace ? 'Pesanan Terbayar POS' : 'Draft Order POS'; ?></h4>
      <p class="fin-page-subtitle mb-0"><?php echo $isPaidWorkspace
        ? 'Halaman ini memusatkan order yang sudah dibayar untuk refund, audit pembayaran, dan kebutuhan cetak ulang struk pembayaran.'
        : 'Susun order kasir lebih dulu, commit snapshot konsumsi saat konfirmasi, lalu pakai fondasi ini untuk alur payment, void, dan refund yang lebih presisi.'; ?></p>
    </div>
  </div>

  <?php if (empty($outlets)): ?>
    <div class="alert alert-warning border-0 shadow-sm">
      Outlet POS lokal belum tersedia. Jalankan setup outlet POS lokal dulu sebelum mulai memakai draft order.
    </div>
  <?php endif; ?>

  <div class="pos-order-shell">
    <div class="card pos-order-main">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-4">
          <div>
            <div class="small text-uppercase fw-bold text-muted"><?php echo $isPaidWorkspace ? 'Refund Workspace' : 'Workbench Kasir'; ?></div>
            <h5 class="mb-1 mt-2"><?php echo $isPaidWorkspace ? 'Refund dan Review Pembayaran' : 'Susun Draft, lalu Commit Snapshot'; ?></h5>
            <p class="mb-0 text-muted"><?php echo $isPaidWorkspace
              ? 'Order yang tampil di sini sudah punya jejak pembayaran, sehingga reversal diarahkan ke refund. Gunakan daftar ini untuk review payment trail sebelum refund atau saat perlu cetak ulang bukti bayar.'
              : 'Versi awal ini fokus ke line produk, outlet/terminal, dan snapshot konsumsi stok berbasis recipe.'; ?></p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="btn-reset-order"><?php echo $isPaidWorkspace ? 'Kosongkan Preview' : 'Reset Draft'; ?></button>
            <button type="button" class="btn btn-outline-dark" id="btn-reversal-preview" disabled><?php echo $isPaidWorkspace ? 'Preview Refund' : 'Preview Void'; ?></button>
            <button type="button" class="btn btn-outline-danger<?php echo $isPaidWorkspace ? ' d-none' : ''; ?>" id="btn-delete-draft" disabled>Hapus Draft</button>
            <button type="button" class="btn btn-outline-primary<?php echo $isPaidWorkspace ? ' d-none' : ''; ?>" id="btn-save-order" <?php echo empty($outlets) ? 'disabled' : ''; ?>>Simpan Draft</button>
            <button type="button" class="btn btn-primary<?php echo $isPaidWorkspace ? ' d-none' : ''; ?>" id="btn-confirm-order" <?php echo empty($outlets) ? 'disabled' : ''; ?>>Confirm + Stock Commit</button>
          </div>
        </div>

        <form id="order-header-form" class="row g-3 mb-4">
          <input type="hidden" name="id" value="">
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Outlet POS</label>
            <select class="form-select" name="outlet_id" id="outlet_id" <?php echo empty($outlets) ? 'disabled' : 'required'; ?> <?php echo $isPaidWorkspace ? 'disabled' : ''; ?>>
              <option value="">Pilih Outlet</option>
              <?php foreach ($outlets as $outlet): ?>
                <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Terminal</label>
            <select class="form-select" name="terminal_id" id="terminal_id" <?php echo empty($outlets) ? 'disabled' : ''; ?> <?php echo $isPaidWorkspace ? 'disabled' : ''; ?>>
              <option value="">Tanpa Terminal</option>
              <?php foreach ($terminals as $terminal): ?>
                <option value="<?php echo (int)$terminal['id']; ?>" data-outlet-id="<?php echo (int)($terminal['outlet_id'] ?? 0); ?>"><?php echo html_escape((string)$terminal['terminal_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">Service</label>
            <select class="form-select" name="service_type" id="service_type" <?php echo $isPaidWorkspace ? 'disabled' : ''; ?>>
              <option value="DINE_IN">DINE_IN</option>
              <option value="TAKE_AWAY">TAKE_AWAY</option>
              <option value="DELIVERY">DELIVERY</option>
              <option value="PICKUP">PICKUP</option>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label mb-1 small text-muted">Guest</label>
            <input type="number" class="form-control" name="guest_count" id="guest_count" min="1" value="1" <?php echo $isPaidWorkspace ? 'readonly' : ''; ?>>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">Order No</label>
            <input type="text" class="form-control" id="order_no_preview" value="Otomatis saat simpan" readonly>
          </div>
          <div class="col-lg-7">
            <label class="form-label mb-1 small text-muted">Member</label>
            <div class="pos-order-search-wrap">
              <input type="text" class="form-control" id="member_search" placeholder="Ketik nama / no HP / nomor member untuk transaksi member..." <?php echo $isPaidWorkspace ? 'disabled' : ''; ?>>
              <div class="pos-order-search-result d-none" id="member_search_result"></div>
            </div>
          </div>
          <div class="col-lg-5">
            <label class="form-label mb-1 small text-muted">Member Terpilih</label>
            <div class="pos-order-member-card" id="member_selected_state">
              <div class="pos-order-member-empty">Walk in customer. Transaksi ini belum memakai member.</div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label mb-1 small text-muted">Catatan Order</label>
            <input type="text" class="form-control" name="notes" id="notes" placeholder="Catatan meja, request kasir, catatan layanan, atau marker internal." <?php echo $isPaidWorkspace ? 'readonly' : ''; ?>>
          </div>
        </form>

        <div class="row g-3 align-items-start mb-4<?php echo $isPaidWorkspace ? ' d-none' : ''; ?>">
          <div class="col-lg-8">
            <div class="pos-order-search-wrap">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                <label class="form-label mb-0 small text-muted">Cari Item POS</label>
                <div class="btn-group btn-group-sm pos-order-search-mode" role="group">
                  <button type="button" class="btn btn-outline-secondary active" data-search-mode="PRODUCT">Produk</button>
                  <button type="button" class="btn btn-outline-secondary" data-search-mode="BUNDLE">Bundle</button>
                </div>
              </div>
              <input type="text" class="form-control" id="product_search" placeholder="Ketik kode / nama produk kasir lalu pilih dari hasil AJAX...">
              <div class="pos-order-search-result d-none" id="product_search_result"></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="pos-order-summary-card">
              <div class="small text-uppercase fw-bold text-muted mb-1">Ringkasan Draft</div>
              <div class="pos-order-summary-kpi" id="summary_grand_total">Rp 0</div>
              <div class="pos-order-mini-note mt-1" id="summary_line_info">Belum ada baris produk</div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle pos-order-line-table">
            <thead>
              <tr>
                <th>Produk</th>
                <th class="text-center">Availability</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Harga</th>
                <th class="text-end">HPP Live</th>
                <th class="text-end">Total</th>
                <th>Catatan</th>
                <th class="text-center" style="width:72px;">Aksi</th>
              </tr>
            </thead>
            <tbody id="order_line_body"></tbody>
          </table>
        </div>
        <div id="order_empty_state" class="pos-order-empty">Belum ada produk di draft ini. Pilih outlet, lalu cari produk untuk menambah baris order.</div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Draft Terbaru</div>
            <h5 class="mb-1 mt-2">Riwayat Draft / Confirm</h5>
            <p class="mb-0 text-muted">Klik salah satu draft untuk dilanjutkan. Ini membantu kasir melanjutkan order yang belum selesai dibayar.</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <?php if (!$isPaidWorkspace): ?>
              <button type="button" class="btn btn-sm btn-outline-primary order-status-tab" data-status="DRAFT">Draft</button>
              <button type="button" class="btn btn-sm btn-outline-primary order-status-tab" data-status="CONFIRMED">Confirmed</button>
            <?php endif; ?>
            <?php if ($isPaidWorkspace): ?>
              <button type="button" class="btn btn-sm btn-outline-primary order-status-tab" data-status="PAID">Terbayar</button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-primary order-status-tab" data-status="ALL">Semua</button>
          </div>
        </div>

        <form class="row g-2 mb-3">
          <div class="col-lg-4">
            <input id="recent_q" class="form-control" placeholder="Cari order no / outlet / terminal / cashier">
          </div>
          <div class="col-lg-2 col-md-4">
            <select id="recent_outlet_id" class="form-select">
              <option value="0">Semua Outlet</option>
              <?php foreach ($outlets as $outlet): ?>
                <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-lg-2 col-md-4">
            <label for="recent_date_from" class="form-label mb-1" style="font-size:.78rem">Dari tanggal</label>
            <input type="date" id="recent_date_from" class="form-control" placeholder="Dari tanggal">
          </div>
          <div class="col-6 col-lg-2 col-md-4">
            <label for="recent_date_to" class="form-label mb-1" style="font-size:.78rem">Sampai tanggal</label>
            <input type="date" id="recent_date_to" class="form-control" placeholder="Sampai tanggal">
          </div>
          <div class="col-lg-1 col-md-2">
            <select id="recent_limit" class="form-select">
              <option value="10">10</option>
              <option value="20" selected>20</option>
              <option value="50">50</option>
            </select>
          </div>
          <div class="col-lg-1 col-md-2 d-grid">
            <button type="button" id="btn-clear-recent" class="btn btn-outline-danger">Clear</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm align-middle table-hover">
            <thead>
              <tr>
                <th>Order</th>
                <th>Outlet</th>
                <th>Kasir</th>
                <th class="text-center">Status</th>
                <th class="text-end">Grand Total</th>
              </tr>
            </thead>
            <tbody id="recent_body"></tbody>
          </table>
        </div>
        <div id="recent_empty_state" class="pos-order-empty d-none">Belum ada draft order pada filter ini.</div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <small id="recent_pagination_info" class="text-muted"></small>
          <div class="d-flex gap-1" id="recent_pagination"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="reversalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1"><?php echo $isPaidWorkspace ? 'Preview Refund POS' : 'Preview Void POS'; ?></h5>
          <div class="small text-muted" id="reversal_modal_meta">Order belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning border-0 d-none" id="reversal_empty_hint">
          Snapshot reversal belum tersedia untuk order ini.
        </div>
        <div class="pos-reversal-help mb-3">
          <div class="fw-semibold mb-1"><?php echo $isPaidWorkspace ? 'Refund hanya untuk order yang sudah dibayar.' : 'Void hanya untuk order yang belum dibayar.'; ?></div>
          <div class="small mb-0">Jika produk punya extra, pilih produk berarti extra ikut otomatis. Jika ingin extra saja, kosongkan produk lalu centang extra yang ingin diproses.</div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="pos-reversal-section-title mb-2">Kebijakan Reversal</div>
            <div class="pos-reversal-policy-grid">
              <label class="pos-reversal-policy-card active" id="reversal_policy_return_card">
                <input class="d-none" type="radio" name="reversal_stock_policy" id="reversal_policy_return" value="RETURN_TO_STOCK" checked>
                <div class="pos-reversal-policy-title">Kembalikan ke stok</div>
                <div class="pos-reversal-policy-note mt-1">Rollback pemakaian stok. Bahan baku, komponen, dan LOT dibatalkan ke kondisi sebelum transaksi.</div>
              </label>
              <label class="pos-reversal-policy-card" id="reversal_policy_adjust_card">
                <input class="d-none" type="radio" name="reversal_stock_policy" id="reversal_policy_adjust" value="ADJUSTMENT_ONLY">
                <div class="pos-reversal-policy-title">Jangan kembalikan ke stok</div>
                <div class="pos-reversal-policy-note mt-1">Gunakan saat barang sudah terlanjur terpakai, rusak, atau perlu dicatat sebagai adjustment.</div>
              </label>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Adjustment Mode</label>
            <select class="form-select" id="reversal_adjustment_mode">
              <option value="NONE">Pilih tipe adjustment...</option>
              <option value="AUTO_WASTE">Waste otomatis</option>
              <option value="AUTO_SPOIL">Spoil otomatis</option>
              <option value="AUTO_ADJUSTMENT">Penyesuaian otomatis</option>
            </select>
            <div class="small text-muted mt-1">Dipakai saat kasir memilih tidak kembalikan stok. Sistem akan rollback stok dan LOT dulu, lalu posting adjustment sesuai pilihan ini.</div>
          </div>
          <div class="col-md-6 d-none" id="reversal_reason_wrap">
            <label class="form-label small text-muted mb-1">Alasan</label>
            <select class="form-select" id="reversal_reason_code">
              <option value="">Pilih alasan...</option>
            </select>
            <input type="text" class="form-control mt-2 d-none" id="reversal_reason_other" placeholder="Tulis alasan lainnya">
          </div>
          <?php if ($isPaidWorkspace): ?>
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">Metode Pengembalian</label>
              <select class="form-select" id="refund_payment_method_id">
                <option value="">Pilih metode refund...</option>
                <?php foreach ($refundPaymentMethods as $method): ?>
                  <option value="<?php echo (int)($method['id'] ?? 0); ?>"><?php echo html_escape((string)($method['method_name'] ?? '-')); ?><?php echo !empty($method['method_type']) ? ' • ' . html_escape((string)$method['method_type']) : ''; ?></option>
                <?php endforeach; ?>
              </select>
              <div class="small text-muted mt-1">Boleh berbeda dari metode pembayaran saat customer membayar.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">Referensi Refund</label>
              <input type="text" class="form-control" id="refund_reference_no" placeholder="No referensi transfer / catatan kasir (opsional)">
            </div>
          <?php endif; ?>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Catatan Audit</label>
            <textarea class="form-control" id="reversal_reason" rows="2" placeholder="Catatan tambahan untuk audit POS (opsional)"></textarea>
          </div>
        </div>
        <div id="reversal_line_list"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-outline-danger<?php echo $isPaidWorkspace ? ' d-none' : ''; ?>" id="btn-save-void">Simpan Void</button>
        <button type="button" class="btn btn-danger<?php echo $isPaidWorkspace ? '' : ' d-none'; ?>" id="btn-save-refund">Simpan Refund</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const workspaceMode = <?php echo json_encode($workspaceMode, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const isPaidWorkspace = workspaceMode === 'PAID';
  const posTransactionCsrfToken = <?php echo json_encode((string)($pos_transaction_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const reversalReasonOptions = <?php echo json_encode($reversalReasonOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const recentState = {
    q: initialFilters.q || '',
    status: initialFilters.status || (isPaidWorkspace ? 'PAID' : 'DRAFT'),
    outlet_id: parseInt(initialFilters.outlet_id || 0, 10) || 0,
    date_from: initialFilters.date_from || '',
    date_to: initialFilters.date_to || '',
    page: parseInt(initialFilters.page || 1, 10) || 1,
    limit: parseInt(initialFilters.limit || 20, 10) || 20
  };

  const order = { id: null, order_no: '', status: 'DRAFT', outlet_id: '', terminal_id: '', service_type: 'DINE_IN', guest_count: 1, member_id: null, member_no: '', member_name: '', member_mobile_phone: '', notes: '', lines: [] };
  let reversalPreview = null;
  const reversalModalEl = document.getElementById('reversalModal');
  const reversalModal = reversalModalEl && window.bootstrap ? new bootstrap.Modal(reversalModalEl) : null;
  const outletSelect = document.getElementById('outlet_id');
  const terminalSelect = document.getElementById('terminal_id');
  const serviceType = document.getElementById('service_type');
  const guestCount = document.getElementById('guest_count');
  const notesInput = document.getElementById('notes');
  const memberSearchInput = document.getElementById('member_search');
  const memberSearchWrap = document.getElementById('member_search_result');
  const memberSelectedState = document.getElementById('member_selected_state');
  const orderIdInput = document.querySelector('#order-header-form input[name="id"]');
  const orderNoPreview = document.getElementById('order_no_preview');
  const searchInput = document.getElementById('product_search');
  const searchWrap = document.getElementById('product_search_result');
  const lineBody = document.getElementById('order_line_body');
  const emptyState = document.getElementById('order_empty_state');
  const reversalButton = document.getElementById('btn-reversal-preview');
  const deleteDraftButton = document.getElementById('btn-delete-draft');
  const reversalReasonWrap = document.getElementById('reversal_reason_wrap');
  const reversalReasonCode = document.getElementById('reversal_reason_code');
  const reversalReasonOther = document.getElementById('reversal_reason_other');
  const refundPaymentMethodField = document.getElementById('refund_payment_method_id');
  const refundReferenceField = document.getElementById('refund_reference_no');
  let searchMode = 'PRODUCT';

  function escapeHtml(v) { return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m])); }
  function money(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0)); }
  function number(v, digits = 2) { return new Intl.NumberFormat('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(v || 0)); }
  function statusBadge(status) {
    const s = String(status || '').toUpperCase();
    if (s === 'AVAILABLE' || s === 'OK') return '<span class="pos-order-availability ok">Tersedia</span>';
    if (s === 'OUT' || s === 'EMPTY') return '<span class="pos-order-availability out">Kosong</span>';
    return '<span class="pos-order-availability warn">Perlu Cek</span>';
  }

  async function getJson(url) {
    const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON. Cek warning/error backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data');
    return j;
  }

  async function postJson(url, payload, scopedOptions) {
    const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (scopedOptions && scopedOptions.headers && typeof scopedOptions.headers === 'object') {
      Object.assign(headers, scopedOptions.headers);
    }
    const r = await fetch(url, { method: 'POST', headers, body: JSON.stringify(payload) });
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response save bukan JSON. Kemungkinan ada warning / error PHP di backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data');
    return j;
  }

  function postPosTransactionJson(url, payload) {
    return postJson(url, payload, {
      headers: { 'X-Pos-Transaction-CSRF': posTransactionCsrfToken }
    });
  }

  function syncHeaderToOrder() {
    order.outlet_id = outletSelect.value || '';
    order.terminal_id = terminalSelect.value || '';
    order.service_type = serviceType.value || 'DINE_IN';
    order.guest_count = Math.max(1, Number(guestCount.value || 1));
    order.notes = notesInput.value || '';
  }

  function syncOrderToHeader() {
    orderIdInput.value = order.id || '';
    orderNoPreview.value = order.order_no || 'Otomatis saat simpan';
    outletSelect.value = order.outlet_id || '';
    filterTerminalOptions();
    terminalSelect.value = order.terminal_id || '';
    serviceType.value = order.service_type || 'DINE_IN';
    guestCount.value = order.guest_count || 1;
    notesInput.value = order.notes || '';
    renderMemberSelection();
    updateActionButtons();
  }

  function canDeleteDraft() {
    const status = String(order.status || 'DRAFT').toUpperCase();
    return !!order.id && !isPaidWorkspace && ['DRAFT', 'PENDING'].includes(status);
  }

  function updateActionButtons() {
    if (reversalButton) {
      reversalButton.disabled = !order.id;
    }
    if (deleteDraftButton) {
      deleteDraftButton.disabled = !canDeleteDraft();
    }
  }

  function renderMemberSelection() {
    if (!order.member_id) {
      memberSelectedState.innerHTML = '<div class="pos-order-member-empty">Walk in customer. Transaksi ini belum memakai member.</div>';
      return;
    }
    memberSelectedState.innerHTML = `
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="pos-order-member-title">${escapeHtml(order.member_name || '-')}</div>
          <div class="pos-order-member-meta">${escapeHtml(order.member_no || '-')} | ${escapeHtml(order.member_mobile_phone || '-')}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-member">Lepas</button>
      </div>
    `;
    const clearBtn = document.getElementById('btn-clear-member');
    if (clearBtn) {
      clearBtn.addEventListener('click', clearMemberSelection);
    }
  }

  function clearMemberSelection() {
    order.member_id = null;
    order.member_no = '';
    order.member_name = '';
    order.member_mobile_phone = '';
    memberSearchInput.value = '';
    memberSearchWrap.classList.add('d-none');
    renderMemberSelection();
  }

  function pickMember(row) {
    order.member_id = Number(row.id || 0) || null;
    order.member_no = row.member_no || '';
    order.member_name = row.member_name || '';
    order.member_mobile_phone = row.mobile_phone || '';
    memberSearchInput.value = '';
    memberSearchWrap.classList.add('d-none');
    renderMemberSelection();
  }

  function filterTerminalOptions() {
    const outletId = Number(outletSelect.value || 0);
    Array.from(terminalSelect.options).forEach((opt, idx) => {
      if (idx === 0) {
        opt.hidden = false;
        return;
      }
      const optionOutletId = Number(opt.dataset.outletId || 0);
      const allow = outletId === 0 || optionOutletId === 0 || optionOutletId === outletId;
      opt.hidden = !allow;
    });
  }

  function recalcSummary() {
    const total = order.lines.reduce((sum, line) => sum + (Number(line.qty || 0) * Number(line.unit_price || 0)), 0);
    document.getElementById('summary_grand_total').textContent = money(total);
    document.getElementById('summary_line_info').textContent = order.lines.length ? `${order.lines.length} baris item | Guest ${order.guest_count || 1}` : 'Belum ada baris produk';
  }

  function renderLines() {
    if (!order.lines.length) {
      lineBody.innerHTML = '';
      emptyState.classList.remove('d-none');
      recalcSummary();
      return;
    }
    emptyState.classList.add('d-none');
    lineBody.innerHTML = order.lines.map((line, idx) => {
      const avail = String(line.availability_status || '').toUpperCase();
      const note = line.bottleneck_name_snapshot ? `<div class="pos-order-mini-note mt-1">Bottleneck: ${escapeHtml(line.bottleneck_name_snapshot)}</div>` : '';
      const bundleChip = line.bundle_id ? `<div class="pos-order-bundle-chip mt-1"><i class="ri-gift-2-line"></i> ${escapeHtml(line.bundle_name || 'Bundle')}</div>` : '';
      return `
        <tr>
          <td>
            <div class="fw-semibold">${escapeHtml(line.product_name || '-')}</div>
            <div class="pos-order-mini-note">${escapeHtml(line.product_code || '-')} | ${escapeHtml(line.product_division_name || '-')} | ${escapeHtml(line.uom_code || '-')}</div>
            ${bundleChip}
            ${note}
          </td>
          <td class="text-center">
            ${statusBadge(avail)}
            <div class="pos-order-mini-note mt-1">${number(line.estimated_available_qty || 0, 2)} ${escapeHtml(line.uom_code || '')}</div>
          </td>
          <td class="text-center" style="width:120px;">
            <input type="number" class="form-control form-control-sm text-center order-line-qty" data-index="${idx}" min="0.01" step="0.01" value="${Number(line.qty || 1)}" ${isPaidWorkspace ? 'disabled' : ''}>
          </td>
          <td class="text-end">${money(line.unit_price || 0)}</td>
          <td class="text-end">${money(line.hpp_live_snapshot || line.hpp_standard || 0)}</td>
          <td class="text-end fw-semibold">${money((Number(line.qty || 0) * Number(line.unit_price || 0)))}</td>
          <td style="min-width:180px;"><input type="text" class="form-control form-control-sm order-line-note" data-index="${idx}" value="${escapeHtml(line.notes || '')}" placeholder="Catatan line" ${isPaidWorkspace ? 'disabled' : ''}></td>
          <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger order-line-remove" data-index="${idx}" ${isPaidWorkspace ? 'disabled' : ''}><i class="ri-delete-bin-line"></i></button></td>
        </tr>
      `;
    }).join('');
    if (isPaidWorkspace) {
      recalcSummary();
      return;
    }
    lineBody.querySelectorAll('.order-line-qty').forEach((el) => el.addEventListener('input', () => {
      const idx = Number(el.dataset.index || 0);
      const qty = Math.max(0, Number(el.value || 0));
      order.lines[idx].qty = qty;
      renderLines();
    }));
    lineBody.querySelectorAll('.order-line-note').forEach((el) => el.addEventListener('input', () => {
      const idx = Number(el.dataset.index || 0);
      order.lines[idx].notes = el.value || '';
    }));
    lineBody.querySelectorAll('.order-line-remove').forEach((el) => el.addEventListener('click', () => {
      const idx = Number(el.dataset.index || 0);
      const line = order.lines[idx];
      if (line && line.bundle_id) {
        order.lines = order.lines.filter((row) => Number(row.bundle_id || 0) !== Number(line.bundle_id || 0));
      } else {
        order.lines.splice(idx, 1);
      }
      renderLines();
    }));
    recalcSummary();
  }

  function addProductRow(row) {
    const existing = order.lines.find((line) => Number(line.product_id) === Number(row.id) && !line.bundle_id);
    if (existing) {
      existing.qty = Number(existing.qty || 0) + 1;
    } else {
      order.lines.push({
        product_id: Number(row.id),
        bundle_id: null,
        bundle_name: '',
        product_code: row.product_code || '',
        product_name: row.product_name || '',
        product_division_name: row.product_division_name || '-',
        uom_code: row.uom_code || '-',
        availability_status: row.availability_status || 'CHECK',
        estimated_available_qty: Number(row.estimated_available_qty || 0),
        bottleneck_name_snapshot: row.bottleneck_name_snapshot || '',
        qty: 1,
        unit_price: Number(row.selling_price || 0),
        hpp_standard: Number(row.hpp_standard || 0),
        hpp_live_snapshot: Number(row.availability_hpp_live_snapshot || row.hpp_live_cache || row.hpp_standard || 0),
        notes: ''
      });
    }
    searchInput.value = '';
    searchWrap.classList.add('d-none');
    renderLines();
  }

  function addBundleRows(bundle) {
    const existingBundleLines = order.lines.filter((line) => Number(line.bundle_id || 0) === Number(bundle.id || 0));
    if (existingBundleLines.length) {
      existingBundleLines.forEach((line) => {
        const source = (bundle.items || []).find((item) => Number(item.product_id || 0) === Number(line.product_id || 0));
        if (source) {
          line.qty = Number(line.qty || 0) + Number(source.qty || 0);
        }
      });
    } else {
      (bundle.items || []).forEach((item) => {
        order.lines.push({
          product_id: Number(item.product_id || 0),
          bundle_id: Number(bundle.id || 0),
          bundle_name: bundle.bundle_name || '',
          product_code: item.product_code || '',
          product_name: item.product_name || '',
          product_division_name: item.product_division_name || '-',
          uom_code: item.uom_code || '-',
          availability_status: item.availability_status || bundle.availability_status || 'CHECK',
          estimated_available_qty: Number(item.estimated_available_qty || 0),
          bottleneck_name_snapshot: item.bottleneck_name_snapshot || bundle.bottleneck_name_snapshot || '',
          qty: Number(item.qty || 0),
          unit_price: Number(item.unit_price || 0),
          hpp_standard: Number(item.hpp_standard || 0),
          hpp_live_snapshot: Number(item.hpp_live_snapshot || item.hpp_standard || 0),
          notes: bundle.bundle_name ? `[Bundle] ${bundle.bundle_name}` : ''
        });
      });
    }
    searchInput.value = '';
    searchWrap.classList.add('d-none');
    renderLines();
  }

  let searchTimer = null;
  let memberSearchTimer = null;

  document.querySelectorAll('[data-search-mode]').forEach((btn) => btn.addEventListener('click', () => {
    searchMode = btn.dataset.searchMode || 'PRODUCT';
    document.querySelectorAll('[data-search-mode]').forEach((rowBtn) => rowBtn.classList.toggle('active', rowBtn === btn));
    searchInput.placeholder = searchMode === 'BUNDLE'
      ? 'Ketik kode / nama bundle lalu pilih paket yang ingin dimasukkan...'
      : 'Ketik kode / nama produk kasir lalu pilih dari hasil AJAX...';
    searchInput.value = '';
    searchWrap.classList.add('d-none');
  }));

  memberSearchInput.addEventListener('input', () => {
    const q = memberSearchInput.value.trim();
    clearTimeout(memberSearchTimer);
    if (q.length < 2) {
      memberSearchWrap.classList.add('d-none');
      return;
    }
    memberSearchTimer = setTimeout(async () => {
      try {
        const json = await getJson('<?php echo site_url('pos/orders/draft/member-search'); ?>?q=' + encodeURIComponent(q));
        const rows = json.rows || [];
        if (!rows.length) {
          memberSearchWrap.innerHTML = '<div class="p-3 text-muted">Member tidak ditemukan.</div>';
          memberSearchWrap.classList.remove('d-none');
          return;
        }
        memberSearchWrap.innerHTML = rows.map((row) => `
          <div class="pos-order-search-item" data-row="${encodeURIComponent(JSON.stringify(row))}">
            <div>
              <div class="pos-order-search-name">${escapeHtml(row.member_name || '-')}</div>
              <div class="pos-order-search-meta">${escapeHtml(row.member_no || '-')} | ${escapeHtml(row.mobile_phone || '-')}</div>
            </div>
            <div class="text-end">
              <div class="fw-semibold">${escapeHtml(row.member_tier || '-')}</div>
              <div class="pos-order-search-meta">${escapeHtml(row.member_status || '-')}</div>
            </div>
          </div>
        `).join('');
        memberSearchWrap.classList.remove('d-none');
        memberSearchWrap.querySelectorAll('.pos-order-search-item').forEach((item) => item.addEventListener('click', () => pickMember(JSON.parse(decodeURIComponent(item.dataset.row)))));
      } catch (e) {
        memberSearchWrap.innerHTML = `<div class="p-3 text-danger">${escapeHtml(e.message)}</div>`;
        memberSearchWrap.classList.remove('d-none');
      }
    }, 250);
  });

  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim();
    syncHeaderToOrder();
    if (!order.outlet_id) {
      searchWrap.classList.add('d-none');
      return;
    }
    clearTimeout(searchTimer);
    if (q.length < 2) {
      searchWrap.classList.add('d-none');
      return;
    }
    searchTimer = setTimeout(async () => {
      try {
        const endpoint = searchMode === 'BUNDLE'
          ? '<?php echo site_url('pos/orders/draft/bundle-search'); ?>'
          : '<?php echo site_url('pos/orders/draft/product-search'); ?>';
        const json = await getJson(endpoint + '?q=' + encodeURIComponent(q) + '&outlet_id=' + encodeURIComponent(order.outlet_id));
        const rows = json.rows || [];
        if (!rows.length) {
          searchWrap.innerHTML = `<div class="p-3 text-muted">${searchMode === 'BUNDLE' ? 'Bundle tidak ditemukan.' : 'Produk tidak ditemukan.'}</div>`;
          searchWrap.classList.remove('d-none');
          return;
        }
        searchWrap.innerHTML = rows.map((row) => {
          if (searchMode === 'BUNDLE') {
            return `
              <div class="pos-order-search-item" data-row="${encodeURIComponent(JSON.stringify(row))}" data-kind="bundle">
                <div>
                  <div class="pos-order-search-name">${escapeHtml(row.bundle_name || '-')}</div>
                  <div class="pos-order-search-meta">${escapeHtml(row.bundle_code || '-')} | ${escapeHtml(row.product_division_name || 'Campuran Divisi')} | ${Number(row.line_count || 0)} item</div>
                </div>
                <div class="text-end">
                  <div class="fw-semibold">${money(row.selling_price || 0)}</div>
                  <div class="pos-order-search-meta">${statusBadge(String(row.availability_status || '').toUpperCase())}</div>
                </div>
              </div>
            `;
          }
          return `
            <div class="pos-order-search-item" data-row="${encodeURIComponent(JSON.stringify(row))}" data-kind="product">
              <div>
                <div class="pos-order-search-name">${escapeHtml(row.product_name || '-')}</div>
                <div class="pos-order-search-meta">${escapeHtml(row.product_code || '-')} | ${escapeHtml(row.product_division_name || '-')} | ${escapeHtml(row.uom_code || '-')}</div>
              </div>
              <div class="text-end">
                <div class="fw-semibold">${money(row.selling_price || 0)}</div>
                <div class="pos-order-search-meta">${statusBadge(String(row.availability_status || '').toUpperCase())}</div>
              </div>
            </div>
          `;
        }).join('');
        searchWrap.classList.remove('d-none');
        searchWrap.querySelectorAll('.pos-order-search-item').forEach((item) => item.addEventListener('click', () => {
          const row = JSON.parse(decodeURIComponent(item.dataset.row));
          if (item.dataset.kind === 'bundle') {
            addBundleRows(row);
          } else {
            addProductRow(row);
          }
        }));
      } catch (e) {
        searchWrap.innerHTML = `<div class="p-3 text-danger">${escapeHtml(e.message)}</div>`;
        searchWrap.classList.remove('d-none');
      }
    }, 250);
  });

  document.addEventListener('click', (e) => {
    if (!searchWrap.contains(e.target) && e.target !== searchInput) {
      searchWrap.classList.add('d-none');
    }
    if (!memberSearchWrap.contains(e.target) && e.target !== memberSearchInput) {
      memberSearchWrap.classList.add('d-none');
    }
  });

  function recentQs() {
    const p = new URLSearchParams();
    p.set('q', recentState.q);
    p.set('status', recentState.status);
    p.set('workspace_mode', workspaceMode);
    p.set('outlet_id', recentState.outlet_id);
    p.set('date_from', recentState.date_from || '');
    p.set('date_to', recentState.date_to || '');
    p.set('page', recentState.page);
    p.set('limit', recentState.limit);
    return p.toString();
  }

  function orderStatusChip(status) {
    const value = String(status || '').toUpperCase();
    const label = orderStatusLabel(value);
    const kind = value === 'CONFIRMED' ? 'order-confirmed' : 'order-draft';
    return `<span class="pos-order-status-chip ${kind}">${escapeHtml(label)}</span>`;
  }

  function orderStatusLabel(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      DRAFT: 'Draft',
      CONFIRMED: 'Terkonfirmasi',
      PAID: 'Lunas',
      VOID: 'Void penuh',
      PARTIAL: 'Sebagian',
      REFUNDED_FULL: 'Refund penuh',
      REFUNDED_PARTIAL: 'Refund sebagian',
      CANCELLED: 'Dibatalkan'
    };
    return map[value] || (value ? value.replace(/_/g, ' ') : '-');
  }

  function lineStatusLabel(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      OPEN: 'Aktif',
      ACTIVE: 'Aktif',
      VOID: 'Void penuh',
      VOID_PARTIAL: 'Void sebagian',
      REFUNDED_FULL: 'Refund penuh',
      REFUNDED_PARTIAL: 'Refund sebagian'
    };
    return map[value] || (value ? value.replace(/_/g, ' ') : '-');
  }

  function processStatusLabel(status) {
    return String(status || '').toUpperCase() === 'NOT_PROCESSED'
      ? 'Belum diproses'
      : 'Sudah diproses';
  }

  function stockCommitChip(status) {
    const value = String(status || '').toUpperCase();
    if (!value) return '';
    const map = {
      PENDING: ['commit-queued', 'Sinkron stok menunggu'],
      QUEUED: ['commit-queued', 'Sinkron stok antre'],
      PROCESSING: ['commit-processing', 'Sinkron stok diproses'],
      POSTED: ['commit-posted', 'Stok sudah sinkron'],
      NOT_REQUIRED: ['commit-failed', 'Belum bisa stock commit'],
      FAILED: ['commit-failed', 'Sinkron stok gagal'],
      REVERSED: ['commit-reversed', 'Sinkron stok dibatalkan']
    };
    const entry = map[value] || ['commit-queued', value ? ('Status stok: ' + value.replace(/_/g, ' ')) : '-'];
    return `<span class="pos-order-status-chip ${entry[0]}">${escapeHtml(entry[1])}</span>`;
  }

  function kickoffRuntimeJobSync(orderId, runtimeJobId) {
    const safeOrderId = Number(orderId || 0);
    const safeJobId = Number(runtimeJobId || 0);
    if (safeOrderId <= 0 || safeJobId <= 0) {
      return;
    }
    window.setTimeout(() => {
      postPosTransactionJson(`<?php echo site_url('pos/orders/runtime-jobs/trigger'); ?>/${safeOrderId}`, {
        job_id: safeJobId,
        limit: 1
      }).then(() => {
        void loadDraft(safeOrderId).catch(() => {});
        void loadRecents().catch(() => {});
      }).catch(() => {
        void loadRecents().catch(() => {});
      });
    }, 350);
  }

  function syncRecentControls() {
    document.getElementById('recent_q').value = recentState.q;
    document.getElementById('recent_outlet_id').value = String(recentState.outlet_id || 0);
    document.getElementById('recent_date_from').value = recentState.date_from || '';
    document.getElementById('recent_date_to').value = recentState.date_to || '';
    document.getElementById('recent_limit').value = String(recentState.limit || 20);
    document.querySelectorAll('.order-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === recentState.status));
  }

  function renderRecentPager(meta) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || recentState.limit || 20);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    document.getElementById('recent_pagination_info').textContent = total ? `Menampilkan ${start}-${end} dari ${total} draft/order` : 'Belum ada draft/order';
    document.getElementById('recent_pagination').innerHTML = Array.from({ length: totalPages }, (_, idx) => {
      const p = idx + 1;
      return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`;
    }).join('');
    document.querySelectorAll('#recent_pagination button').forEach((btn) => btn.addEventListener('click', () => { recentState.page = Number(btn.dataset.page || 1); loadRecents(); }));
  }

  async function loadRecents() {
    syncRecentControls();
    const json = await getJson('<?php echo site_url('pos/orders/draft/data'); ?>?' + recentQs());
    const rows = json.rows || [];
    const body = document.getElementById('recent_body');
    const empty = document.getElementById('recent_empty_state');
    if (!rows.length) {
      body.innerHTML = ''; empty.classList.remove('d-none');
    } else {
      empty.classList.add('d-none');
      body.innerHTML = rows.map((row) => `
        <tr class="pos-order-draft-row" data-id="${Number(row.id || 0)}">
          <td><div class="fw-semibold">${escapeHtml(row.order_no || '-')}</div><div class="pos-order-mini-note">${escapeHtml(row.service_type || '-')} | ${escapeHtml(row.ordered_at || '-')}</div></td>
          <td><div>${escapeHtml(row.outlet_name || '-')}</div><div class="pos-order-mini-note">${escapeHtml(row.terminal_name || 'Tanpa Terminal')}</div></td>
          <td><div>${escapeHtml(row.employee_name || '-')}</div><div class="pos-order-mini-note">${escapeHtml(row.member_name || 'Walk in')}</div></td>
          <td class="text-center">
            <div class="pos-order-status-stack">
              ${orderStatusChip(row.status || '-')}
              ${stockCommitChip(row.stock_commit_status || '')}
            </div>
          </td>
          <td class="text-end fw-semibold">${money(row.grand_total || 0)}</td>
        </tr>
      `).join('');
    }
    renderRecentPager(json.meta || {});
    body.querySelectorAll('.pos-order-draft-row').forEach((tr) => tr.addEventListener('click', () => loadDraft(Number(tr.dataset.id || 0))));
  }

  async function loadDraft(id) {
    if (id <= 0) return;
    const json = await getJson('<?php echo site_url('pos/orders/draft/load'); ?>/' + id);
    const header = json.header || {};
    const lines = Array.isArray(json.lines) ? json.lines : [];
    order.id = Number(header.id || 0) || null;
    order.order_no = header.order_no || '';
    order.status = header.status || 'DRAFT';
    order.outlet_id = String(header.outlet_id || '');
    order.terminal_id = String(header.terminal_id || '');
    order.service_type = header.service_type || 'DINE_IN';
    order.guest_count = Number(header.guest_count || 1);
    order.member_id = Number(header.member_id || 0) || null;
    order.member_no = header.member_no || '';
    order.member_name = header.member_name || '';
    order.member_mobile_phone = header.member_mobile_phone || '';
    order.notes = header.notes || '';
    order.lines = lines.map((line) => ({
      product_id: Number(line.product_id || 0),
      bundle_id: Number(line.bundle_id || 0) || null,
      bundle_name: line.bundle_name || '',
      product_code: line.product_code || '',
      product_name: line.product_name || '',
      product_division_name: line.product_division_name || '-',
      uom_code: line.uom_code || '-',
      availability_status: 'CHECK',
      estimated_available_qty: 0,
      bottleneck_name_snapshot: '',
      qty: Number(line.qty || 0),
      unit_price: Number(line.unit_price || 0),
      hpp_standard: Number(line.hpp_standard_snapshot || 0),
      hpp_live_snapshot: Number(line.hpp_live_snapshot || line.hpp_standard_snapshot || 0),
      notes: line.notes || ''
    }));
    syncOrderToHeader();
    renderLines();
  }

  async function saveDraft(silent = false) {
    syncHeaderToOrder();
    const payload = {
      id: order.id,
      outlet_id: order.outlet_id,
      terminal_id: order.terminal_id,
      service_type: order.service_type,
      guest_count: order.guest_count,
      member_id: order.member_id,
      notes: order.notes,
      lines: order.lines.map((line) => ({
        product_id: line.product_id,
        bundle_id: line.bundle_id,
        qty: line.qty,
        unit_price: line.unit_price,
        hpp_live_snapshot: line.hpp_live_snapshot,
        notes: line.notes || ''
      }))
    };
    const json = await postPosTransactionJson('<?php echo site_url('pos/orders/draft/save'); ?>', payload);
    order.id = Number(json.id || 0) || order.id;
    order.order_no = json.order_no || order.order_no;
    syncOrderToHeader();
    if (!silent) alert('Draft order berhasil disimpan.');
    await loadRecents();
  }

  async function deleteDraft() {
    if (!canDeleteDraft()) {
      alert('Pilih draft order dulu sebelum menghapusnya.');
      return;
    }
    if (!window.confirm(`Hapus draft ${order.order_no || 'transaksi ini'}?`)) {
      return;
    }
    await postPosTransactionJson(`<?php echo site_url('pos/orders/draft/delete'); ?>/${Number(order.id || 0)}`, {});
    resetDraft();
    await loadRecents();
    alert('Draft order berhasil dihapus.');
  }

  async function confirmDraft() {
    if (!order.lines.length) {
      alert('Tambahkan minimal 1 produk sebelum confirm.');
      return;
    }
    await saveDraft(true);
    const json = await postPosTransactionJson('<?php echo site_url('pos/orders/draft/confirm'); ?>/' + order.id, {});
    kickoffRuntimeJobSync(order.id, Number(json.runtime_job_id || 0));
    const warningMessage = String(json.warning_message || '').trim();
    const stockCommitStatus = String(json.stock_commit_status || '').toUpperCase();
    const confirmMessage = stockCommitStatus === 'NOT_REQUIRED'
      ? `Order berhasil dikonfirmasi tanpa stock commit.\nCommit No: -\nResolved Line: 0\nPrint Job: 0`
      : `Order berhasil dikonfirmasi.\nCommit No: ${json.commit_no || '-'}\nResolved Line: ${Number(json.resolved_line_count || 0)}\nPrint Job: ${Number(json.print_job_count || 0)}`;
    alert(warningMessage ? `${confirmMessage}\n\nCatatan:\n${warningMessage}` : confirmMessage);
    await loadDraft(order.id);
    await loadRecents();
  }

  function resetDraft() {
    order.id = null; order.order_no = ''; order.status = 'DRAFT'; order.outlet_id = ''; order.terminal_id = ''; order.service_type = 'DINE_IN'; order.guest_count = 1; order.member_id = null; order.member_no = ''; order.member_name = ''; order.member_mobile_phone = ''; order.notes = ''; order.lines = [];
    reversalPreview = null;
    syncOrderToHeader();
    renderLines();
  }

  function reversalUsesStockReturn() {
    return !!document.getElementById('reversal_policy_return')?.checked;
  }

  function activeReversalKind() {
    return isPaidWorkspace ? 'REFUND' : 'VOID';
  }

  function refreshReversalPolicyCards() {
    const usesReturn = reversalUsesStockReturn();
    document.getElementById('reversal_policy_return_card')?.classList.toggle('active', usesReturn);
    document.getElementById('reversal_policy_adjust_card')?.classList.toggle('active', !usesReturn);
    reversalReasonWrap?.classList.toggle('d-none', usesReturn);
  }

  function fillReversalReasonOptions() {
    if (!reversalReasonCode) {
      return;
    }
    const rows = Array.isArray(reversalReasonOptions[activeReversalKind()]) ? reversalReasonOptions[activeReversalKind()] : [];
    reversalReasonCode.innerHTML = ['<option value="">Pilih alasan...</option>']
      .concat(rows.map((row) => `<option value="${escapeHtml(row.code || '')}">${escapeHtml(row.label || '')}</option>`))
      .join('');
    if (reversalReasonOther) {
      reversalReasonOther.value = '';
      reversalReasonOther.classList.add('d-none');
    }
  }

  function refreshReasonOtherVisibility() {
    if (!reversalReasonOther || !reversalReasonCode) {
      return;
    }
    reversalReasonOther.classList.toggle('d-none', String(reversalReasonCode.value || '').toUpperCase() !== 'OTHER');
  }

  function resetReversalForm() {
    document.getElementById('reversal_policy_return').checked = true;
    document.getElementById('reversal_policy_adjust').checked = false;
    document.getElementById('reversal_adjustment_mode').value = 'NONE';
    document.getElementById('reversal_reason').value = '';
    if (refundPaymentMethodField) refundPaymentMethodField.value = '';
    if (refundReferenceField) refundReferenceField.value = '';
    fillReversalReasonOptions();
    refreshReversalPolicyCards();
  }

  function syncReversalSelections() {
    document.querySelectorAll('.pos-reversal-line').forEach((card) => {
      const productToggle = card.querySelector('.reversal-product-toggle');
      const productQty = card.querySelector('.reversal-product-qty');
      const extraRows = card.querySelectorAll('.pos-reversal-extra-row');
      if (!productToggle) {
        return;
      }
      const productSelected = productToggle.checked;
      if (productQty) {
        productQty.disabled = !productSelected;
      }
      extraRows.forEach((row) => {
        const extraToggle = row.querySelector('.reversal-extra-toggle');
        const extraQty = row.querySelector('.reversal-extra-qty');
        const autoHint = row.querySelector('.reversal-extra-auto-hint');
        if (!extraToggle || !extraQty) {
          return;
        }
        if (productSelected) {
          extraToggle.checked = true;
          extraToggle.disabled = true;
          extraQty.disabled = true;
          autoHint?.classList.remove('d-none');
        } else {
          extraToggle.disabled = false;
          extraQty.disabled = !extraToggle.checked;
          autoHint?.classList.add('d-none');
        }
      });
    });
  }

  function finalReversalReason() {
    const auditNote = String(document.getElementById('reversal_reason').value || '').trim();
    if (reversalUsesStockReturn()) {
      return auditNote;
    }
    const selectedCode = String(reversalReasonCode?.value || '').trim();
    if (selectedCode === '') {
      throw new Error('Pilih alasan reversal ketika stok tidak dikembalikan.');
    }
    const rows = Array.isArray(reversalReasonOptions[activeReversalKind()]) ? reversalReasonOptions[activeReversalKind()] : [];
    const matched = rows.find((row) => String(row.code || '') === selectedCode);
    let reasonText = matched && rowHasLabel(matched) ? String(matched.label) : selectedCode;
    if (selectedCode === 'OTHER') {
      const otherText = String(reversalReasonOther?.value || '').trim();
      if (otherText === '') {
        throw new Error('Isi alasan lainnya untuk reversal ini.');
      }
      reasonText = otherText;
    }
    return auditNote ? `${reasonText} | ${auditNote}` : reasonText;
  }

  function rowHasLabel(row) {
    return !!(row && typeof row === 'object' && row.label);
  }

  function buildReversalPayload(kind) {
    if (!reversalPreview || !reversalPreview.order || !reversalPreview.order.header) {
      throw new Error('Preview reversal belum dimuat.');
    }
    const returnToStock = reversalUsesStockReturn();
    const adjustmentMode = returnToStock ? 'NONE' : (document.getElementById('reversal_adjustment_mode').value || 'NONE');
    if (!returnToStock && adjustmentMode === 'NONE') {
      throw new Error('Pilih tipe adjustment ketika stok tidak dikembalikan.');
    }
    const reasonCode = String(reversalReasonCode?.value || 'OTHER').trim() || 'OTHER';
    const reason = finalReversalReason();
    const orderLineMap = new Map((reversalPreview.order.lines || []).map((line) => [Number(line.id || 0), line]));
    const lines = [];

    document.querySelectorAll('.pos-reversal-line').forEach((card) => {
      const orderLineId = Number(card.dataset.lineId || 0);
      const sourceLine = orderLineMap.get(orderLineId);
      if (!sourceLine || orderLineId <= 0) {
        return;
      }
      const productToggle = card.querySelector('.reversal-product-toggle');
      const productQty = card.querySelector('.reversal-product-qty');
      const productSelected = !!(productToggle && productToggle.checked);
      const extraSelections = [];

      card.querySelectorAll('.pos-reversal-extra-row').forEach((row) => {
        const extraToggle = row.querySelector('.reversal-extra-toggle');
        const extraQty = row.querySelector('.reversal-extra-qty');
        const orderLineExtraId = Number(row.dataset.extraId || 0);
        if (!extraToggle || !extraToggle.checked || orderLineExtraId <= 0) {
          return;
        }
        extraSelections.push({
          order_line_extra_id: orderLineExtraId,
          qty: Math.max(0, Number(extraQty?.value || 0)),
          processed_state: String(sourceLine.process_status || 'NOT_PROCESSED').toUpperCase(),
          return_to_stock: returnToStock,
          notes: reason,
        });
      });

      const qty = productSelected ? Math.max(0, Number(productQty?.value || 0)) : 0;
      if (!productSelected && !extraSelections.length) {
        return;
      }
      lines.push({
        order_line_id: orderLineId,
        qty,
        processed_state: String(sourceLine.process_status || 'NOT_PROCESSED').toUpperCase(),
        return_to_stock: returnToStock,
        notes: reason,
        extras: extraSelections.filter((extra) => extra.order_line_extra_id > 0 && extra.qty > 0),
      });
    });

    if (kind === 'REFUND' && !refundPaymentMethodField?.value) {
      throw new Error('Pilih metode pembayaran pengembalian untuk refund ini.');
    }

    return {
      kind,
      order_id: Number(reversalPreview.order.header.id || 0),
      return_to_stock: returnToStock ? 1 : 0,
      adjustment_mode: adjustmentMode,
      reason_code: reasonCode,
      reason,
      payment_method_id: kind === 'REFUND' ? Number(refundPaymentMethodField?.value || 0) : 0,
      reference_no: kind === 'REFUND' ? String(refundReferenceField?.value || '') : '',
      lines: lines.filter((line) => line.order_line_id > 0 && (line.qty > 0 || (Array.isArray(line.extras) && line.extras.length > 0)))
    };
  }

  function renderReversalPreview(json) {
    reversalPreview = json;
    document.getElementById('reversal_modal_meta').textContent = `${json.order?.header?.order_no || '-'} • ${orderStatusLabel(json.order?.header?.status || '')} • ${json.order?.header?.customer_name || json.order?.header?.member_name || 'Walk in'}`;
    const list = document.getElementById('reversal_line_list');
    const emptyHint = document.getElementById('reversal_empty_hint');
    const orderLines = Array.isArray(json.order?.lines) ? json.order.lines : [];
    if (!orderLines.length) {
      emptyHint.classList.remove('d-none');
      list.innerHTML = '';
      return;
    }
    emptyHint.classList.add('d-none');
    resetReversalForm();
    list.innerHTML = orderLines.map((line) => {
      const processed = String(line.process_status || 'NOT_PROCESSED').toUpperCase();
      const isProcessed = processed !== 'NOT_PROCESSED';
      const flagClass = isProcessed ? 'adjust' : 'return';
      const flagLabel = isProcessed ? 'Sudah Diproses' : 'Belum Diproses';
      const processedLabel = processStatusLabel(processed);
      const extras = Array.isArray(line.extras) ? line.extras : [];
      return `
        <div class="pos-reversal-line" data-line-id="${Number(line.id || 0)}">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <input class="form-check-input reversal-product-toggle" type="checkbox" ${Number(line.qty || 0) > 0 ? 'checked' : ''}>
                <div class="fw-semibold">${escapeHtml(line.product_name || '-')}</div>
              </div>
              <div class="pos-order-mini-note">${escapeHtml(line.product_code || '-')} | Qty ${number(line.qty || 0, 2)} | Status item ${escapeHtml(lineStatusLabel(line.line_status || '-'))}</div>
              ${extras.length ? '<div class="pos-reversal-summary-note mt-2">Pilih produk = extra ikut otomatis. Untuk extra saja, kosongkan produk lalu pilih extra di bawah.</div>' : ''}
            </div>
            <span class="pos-reversal-flag ${flagClass}">${escapeHtml(flagLabel)}</span>
          </div>
          <div class="row g-2 mt-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">Qty Produk</label>
              <input type="number" class="form-control form-control-sm reversal-product-qty pos-reversal-qty-input" min="0" step="0.01" value="${Number(line.qty || 0)}">
            </div>
            <div class="col-md-9">
              <div class="small text-muted">Status proses fisik: <strong>${escapeHtml(processedLabel)}</strong>${isProcessed ? ' • Sistem tetap rollback stok dan LOT lebih dulu. Jika dipilih tidak kembalikan stok, sistem lanjut posting adjustment.' : ''}</div>
            </div>
          </div>
          ${extras.length ? `<div class="pos-reversal-extra-list">${extras.map((extra) => `
            <div class="pos-reversal-extra-row" data-extra-id="${Number(extra.id || 0)}">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <label class="d-flex align-items-center gap-2 mb-0">
                  <input class="form-check-input reversal-extra-toggle" type="checkbox">
                  <span class="fw-semibold">${escapeHtml(extra.extra_name || '-')}</span>
                </label>
                <span class="reversal-extra-auto-hint small text-success d-none">Ikut otomatis saat produk dipilih</span>
              </div>
              <div class="d-flex flex-wrap gap-3 align-items-center mt-2">
                <div>
                  <div class="small text-muted">Qty Extra</div>
                  <input type="number" class="form-control form-control-sm reversal-extra-qty pos-reversal-qty-input" min="0" step="0.01" value="${Number(extra.qty || 0)}" disabled>
                </div>
                <div class="small text-muted">Harga tambahan ${money(extra.unit_price || 0)}</div>
              </div>
            </div>`).join('')}</div>` : ''}
        </div>
      `;
    }).join('');
    syncReversalSelections();
    list.querySelectorAll('.reversal-product-toggle, .reversal-extra-toggle').forEach((field) => field.addEventListener('change', syncReversalSelections));
  }

  async function openReversalPreview() {
    if (!order.id) {
      alert('Simpan atau pilih order dulu sebelum preview void/refund.');
      return;
    }
    const json = await getJson('<?php echo site_url('pos/orders/reversal-preview'); ?>/' + order.id);
    renderReversalPreview(json);
    if (reversalModal) reversalModal.show();
  }

  async function submitReversal(kind) {
    if (kind !== 'VOID') {
      throw new Error('Workspace draft hanya mendukung void.');
    }
    const payload = buildReversalPayload(kind);
    if (!payload.lines.length) {
      throw new Error('Tidak ada line yang bisa diproses untuk ' + kind.toLowerCase() + '.');
    }
    const endpoint = '<?php echo site_url('pos/orders/void/save'); ?>';
    const json = await postPosTransactionJson(endpoint, payload);
    if (reversalModal) reversalModal.hide();
    let message = `Void berhasil disimpan.\nNo Void: ${json.void_no || '-'}`;
    if (Number(json.adjustment_doc_count || 0) > 0) {
      message += `\nAdjustment terposting: ${Number(json.adjustment_doc_count || 0)}`;
    }
    alert(message);
    await loadDraft(order.id);
    await loadRecents();
  }

  document.querySelectorAll('.order-status-tab').forEach((btn) => btn.addEventListener('click', () => { recentState.status = btn.dataset.status; recentState.page = 1; loadRecents(); }));
  document.getElementById('recent_q').addEventListener('input', (e) => { recentState.q = e.target.value; recentState.page = 1; loadRecents(); });
  document.getElementById('recent_outlet_id').addEventListener('change', (e) => { recentState.outlet_id = Number(e.target.value || 0); recentState.page = 1; loadRecents(); });
  document.getElementById('recent_date_from').addEventListener('change', (e) => { recentState.date_from = e.target.value || ''; recentState.page = 1; loadRecents(); });
  document.getElementById('recent_date_to').addEventListener('change', (e) => { recentState.date_to = e.target.value || ''; recentState.page = 1; loadRecents(); });
  document.getElementById('recent_limit').addEventListener('change', (e) => { recentState.limit = Number(e.target.value || 20); recentState.page = 1; loadRecents(); });
  document.getElementById('btn-clear-recent').addEventListener('click', () => { recentState.q = ''; recentState.status = isPaidWorkspace ? 'PAID' : 'DRAFT'; recentState.outlet_id = 0; recentState.date_from = isPaidWorkspace ? '<?php echo date('Y-m-d'); ?>' : '<?php echo date('Y-m-01'); ?>'; recentState.date_to = isPaidWorkspace ? '<?php echo date('Y-m-d'); ?>' : '<?php echo date('Y-m-t'); ?>'; recentState.page = 1; recentState.limit = 20; loadRecents(); });

  outletSelect.addEventListener('change', () => { filterTerminalOptions(); syncHeaderToOrder(); });
  terminalSelect.addEventListener('change', syncHeaderToOrder);
  serviceType.addEventListener('change', syncHeaderToOrder);
  guestCount.addEventListener('input', () => { syncHeaderToOrder(); recalcSummary(); });
  notesInput.addEventListener('input', syncHeaderToOrder);

  document.getElementById('btn-save-order').addEventListener('click', async () => {
    try { await saveDraft(false); } catch (e) { alert(e.message); }
  });
  document.getElementById('btn-delete-draft')?.addEventListener('click', async () => {
    try { await deleteDraft(); } catch (e) { alert(e.message); }
  });
  document.getElementById('btn-confirm-order').addEventListener('click', async () => {
    try { await confirmDraft(); } catch (e) { alert(e.message); }
  });
  document.getElementById('btn-reset-order').addEventListener('click', resetDraft);
  if (reversalButton) {
    reversalButton.addEventListener('click', async () => {
      try { await openReversalPreview(); } catch (e) { alert(e.message); }
    });
  }
  document.getElementById('btn-save-void').addEventListener('click', async () => {
    try { await submitReversal('VOID'); } catch (e) { alert(e.message); }
  });
  document.getElementById('btn-save-refund').addEventListener('click', async () => {
    try { await submitReversal('REFUND'); } catch (e) { alert(e.message); }
  });
  document.getElementById('reversal_policy_return')?.addEventListener('change', refreshReversalPolicyCards);
  document.getElementById('reversal_policy_adjust')?.addEventListener('change', refreshReversalPolicyCards);
  reversalReasonCode?.addEventListener('change', refreshReasonOtherVisibility);

  syncOrderToHeader();
  fillReversalReasonOptions();
  refreshReversalPolicyCards();
  renderLines();
  loadRecents().catch((e) => alert(e.message));
});
</script>
