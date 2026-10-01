<?php
$money = static function ($value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$paymentMethods = is_array($payment_methods ?? null) ? $payment_methods : [];
$overview = is_array($overview ?? null) ? $overview : [];
$pageTitle = trim((string)($page_title ?? '')) !== '' ? (string)$page_title : 'Laporan Penjualan POS';
$totalDiscount = (float)($overview['discount_amount'] ?? 0)
  + (float)($overview['promo_amount'] ?? 0)
  + (float)($overview['voucher_amount'] ?? 0)
  + (float)($overview['point_redeem_amount'] ?? 0)
  + (float)($overview['compliment_amount'] ?? 0);
$depositAppliedAmount = (float)($overview['deposit_applied_amount'] ?? 0);
$finalPaymentAmount = (float)($overview['paid_total'] ?? 0);
$settlementAmount = $finalPaymentAmount + $depositAppliedAmount;
$qty = static function ($value, int $decimals = 0): string {
    return number_format((float)$value, $decimals, ',', '.');
};
$dt = static function ($value): string {
    if (empty($value)) {
        return '-';
    }

    $time = strtotime((string)$value);
    return $time ? date('d M Y H:i', $time) : (string)$value;
};
$statusBadge = static function (string $status, ?string $label = null): string {
  $status = strtoupper(trim($status));
    $class = 'secondary';
    if (in_array($status, ['PAID', 'READY', 'SERVED'], true)) {
        $class = 'success';
    } elseif (in_array($status, ['PAID_PARTIAL', 'CONFIRMED', 'IN_KITCHEN'], true)) {
        $class = 'warning';
    } elseif (in_array($status, ['REFUND_PARTIAL', 'REFUND_FULL'], true)) {
        $class = 'info';
    }

    return '<span class="pos-report-badge ' . $class . '">' . html_escape($label !== null && $label !== '' ? $label : ($status !== '' ? $status : '-')) . '</span>';
};
$statusLabel = static function ($status): string {
  $value = strtoupper(trim((string)$status));
  $map = [
    'PAID' => 'Lunas',
    'READY' => 'Siap saji',
    'SERVED' => 'Sudah disajikan',
    'PAID_PARTIAL' => 'Bayar sebagian',
    'CONFIRMED' => 'Terkonfirmasi',
    'IN_KITCHEN' => 'Sedang diproses',
    'REFUND_PARTIAL' => 'Refund sebagian',
    'REFUND_FULL' => 'Refund penuh',
  ];

  return $map[$value] ?? ($value !== '' ? str_replace('_', ' ', $value) : '-');
};
$summaryCards = [
    ['label' => 'Penjualan Kotor', 'value' => $money($overview['gross_sales'] ?? 0), 'icon' => 'ri-stack-line', 'value_class' => '', 'icon_class' => 'text-primary'],
    ['label' => 'Potongan Penjualan', 'value' => $money($overview['sales_discount_amount'] ?? $totalDiscount), 'icon' => 'ri-price-tag-3-line', 'value_class' => 'text-danger', 'icon_class' => 'text-danger'],
    ['label' => 'Refund', 'value' => $money($overview['refund_amount'] ?? 0), 'icon' => 'ri-arrow-go-back-line', 'value_class' => 'text-warning', 'icon_class' => 'text-warning'],
    ['label' => 'Penjualan Bersih', 'value' => $money($overview['net_sales'] ?? 0), 'icon' => 'ri-line-chart-line', 'value_class' => 'text-success', 'icon_class' => 'text-success'],
    ['label' => 'HPP Saat Jual', 'value' => $money($overview['hpp_sale_amount'] ?? 0), 'icon' => 'ri-archive-stack-line', 'value_class' => '', 'icon_class' => 'text-secondary'],
    ['label' => 'Koreksi HPP Defisit', 'value' => $money($overview['hpp_deficit_correction_amount'] ?? 0), 'icon' => 'ri-scales-3-line', 'value_class' => '', 'icon_class' => 'text-warning'],
    ['label' => 'HPP Terkini', 'value' => $money($overview['hpp_final_amount'] ?? 0), 'icon' => 'ri-calculator-line', 'value_class' => '', 'icon_class' => 'text-secondary'],
    ['label' => 'Laba Kotor', 'value' => $money($overview['gross_profit'] ?? 0), 'icon' => 'ri-funds-line', 'value_class' => ((float)($overview['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'), 'icon_class' => ((float)($overview['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success')],
    ['label' => 'Total Transaksi', 'value' => number_format((int)($overview['order_count'] ?? 0)), 'icon' => 'ri-shopping-bag-3-line', 'value_class' => '', 'icon_class' => 'text-secondary'],
    ['label' => 'Uang Diterima / Sebelum Refund', 'value' => $money($settlementAmount), 'icon' => 'ri-money-dollar-circle-line', 'value_class' => 'text-success', 'icon_class' => 'text-success', 'note' => 'Pembayaran nota terpilih, belum dikurangi refund. Bukan kas bersih rekening.'],
    ['label' => 'Total Piutang', 'value' => $money($overview['balance_due'] ?? 0), 'icon' => 'ri-file-warning-line', 'value_class' => '', 'icon_class' => 'text-dark'],
    ['label' => 'Total Void', 'value' => $money($overview['void_amount'] ?? 0), 'icon' => 'ri-close-circle-line', 'value_class' => 'text-danger', 'icon_class' => 'text-danger'],
];
?>
<?php $this->load->view('pos/_report_styles'); ?>
<style>
  .pos-report-sales-hero .fin-page-title {
    font-size: 1.6rem;
    line-height: 1.2;
    color: #3b261c;
  }
  .pos-report-sales-hero .fin-page-title i {
    font-size: 1.25rem;
    vertical-align: -1px;
  }
  .pos-report-sales-card {
    position: relative;
    overflow: hidden;
    min-height: 88px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: .3rem;
    padding: .75rem .85rem;
  }
  .pos-report-sales-summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: .8rem;
  }
  .pos-report-sales-card-icon {
    position: absolute;
    top: .65rem;
    right: .65rem;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.92);
    box-shadow: 0 10px 24px rgba(126, 73, 35, .08);
    font-size: .74rem;
  }
  .pos-report-sales-card .pos-report-card-label {
    font-size: .68rem;
    letter-spacing: .06em;
    line-height: 1.15;
  }
  .pos-report-sales-card .pos-report-card-value {
    margin-top: 0;
    padding-right: 1.9rem;
    line-height: 1.1;
    font-size: 1rem;
  }
  .pos-report-sales-filter-box {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) minmax(105px, .9fr) minmax(105px, .95fr) minmax(145px, 1.15fr) minmax(112px, .88fr) minmax(112px, .88fr) minmax(86px, .72fr) minmax(112px, .82fr) minmax(112px, .82fr);
    gap: .6rem;
    align-items: end;
  }
  .pos-report-sales-filter-field {
    min-width: 0;
  }
  .pos-report-sales-filter-field .form-label {
    white-space: nowrap;
  }
  .pos-report-sales-filter-field .form-control,
  .pos-report-sales-filter-field .form-select,
  .pos-report-sales-filter-action .btn {
    height: 42px;
  }
  .pos-report-sales-filter-action {
    min-width: 0;
  }
  .pos-report-sales-filter-action .btn {
    width: 100%;
  }
  .pos-sales-action-icon {
    position: relative;
    width: 42px;
    height: 38px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .pos-sales-action-icon svg {
    width: 22px;
    height: 22px;
    stroke: currentColor;
    stroke-width: 2.4;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
  .pos-sales-action-icon::after {
    content: attr(data-action-label);
    position: absolute;
    right: 0;
    bottom: calc(100% + 7px);
    z-index: 20;
    padding: 4px 7px;
    border-radius: 6px;
    background: #34231f;
    color: #fff;
    font-size: .68rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transform: translateY(3px);
    transition: opacity .15s ease, transform .15s ease;
  }
  .pos-sales-action-icon:hover::after,
  .pos-sales-action-icon:focus-visible::after {
    opacity: 1;
    transform: translateY(0);
  }
  @media (max-width: 1399.98px) {
    .pos-report-sales-summary-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }
  @media (max-width: 1199.98px) {
    .pos-report-sales-filter-box {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .pos-report-sales-filter-action {
      min-width: 0;
    }
  }
  @media (max-width: 991.98px) {
    .pos-report-sales-summary-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .pos-report-sales-filter-box {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  .pos-report-sales-mobile-list {
    display: none;
  }
  .pos-report-sales-mobile-card {
    border: 1px solid #efddd1;
    border-radius: 18px;
    background: linear-gradient(180deg, #fffefd 0%, #fff7f1 100%);
    padding: .95rem 1rem;
    box-shadow: 0 12px 28px rgba(126, 73, 35, .06);
  }
  .pos-report-sales-mobile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
  }
  .pos-report-sales-mobile-label {
    color: #8a7063;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: .2rem;
  }
  @media (max-width: 767.98px) {
    .pos-report-shell {
      border-radius: 22px;
      padding: .8rem;
    }
    .pos-report-hero {
      border-radius: 20px;
      padding: 1rem;
    }
    .pos-report-sales-hero .fin-page-title {
      font-size: 1.25rem;
    }
    .pos-report-copy {
      font-size: .88rem;
    }
    .pos-report-card {
      border-radius: 18px;
    }
    .pos-report-sales-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .8rem;
    }
    .pos-report-sales-filter-box {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .7rem;
    }
    .pos-report-sales-card {
      min-height: 84px;
      padding-right: .2rem;
      padding: .72rem .78rem;
    }
    .pos-report-sales-card-icon {
      width: 1.55rem;
      height: 1.55rem;
      border-radius: 9px;
      font-size: .72rem;
    }
    .pos-report-sales-card .pos-report-card-label {
      font-size: .65rem;
    }
    .pos-report-sales-card .pos-report-card-value {
      font-size: .96rem;
      padding-right: 1.8rem;
    }
    .pos-report-filter-box {
      padding: .85rem;
    }
    .pos-report-sales-filter-action {
      grid-column: span 1;
    }
    .pos-report-chip-row {
      gap: .45rem;
    }
    .pos-report-chip {
      width: 100%;
      justify-content: space-between;
    }
    .pos-report-sales-mobile-list {
      display: grid;
      gap: .8rem;
    }
    .pos-report-sales-desktop-table {
      display: none;
    }
  }
  @media (min-width: 768px) {
    .pos-report-sales-desktop-table {
      display: block;
    }
  }
</style>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="pos-report-shell">
    <div class="pos-report-hero pos-report-sales-hero mb-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h4 class="fin-page-title mb-1"><i class="ri ri-receipt-line me-2 text-primary"></i><?php echo html_escape($pageTitle); ?></h4>
          <p class="pos-report-copy mb-0">Ringkasan penjualan POS per transaksi dengan fokus ke nilai terbayar, refund, dan akses audit detail transaksi.</p>
        </div>
      </div>
    </div>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'sales']); ?>
    <?php $this->load->view('pos/_report_filter_summary', ['filters' => $filters, 'outlets' => $outlets, 'show_outlet_chip' => false]); ?>

    <div class="pos-report-sales-summary-grid mb-3">
      <?php foreach ($summaryCards as $card): ?>
        <div>
          <div class="pos-report-card pos-report-sales-card h-100">
            <span class="pos-report-sales-card-icon <?php echo html_escape((string)($card['icon_class'] ?? 'text-primary')); ?>"><i class="ri <?php echo html_escape((string)($card['icon'] ?? 'ri-bar-chart-line')); ?>"></i></span>
            <div class="pos-report-card-label"><?php echo html_escape((string)($card['label'] ?? '-')); ?></div>
            <div class="pos-report-card-value <?php echo html_escape((string)($card['value_class'] ?? '')); ?>"><?php echo (string)($card['value'] ?? '-'); ?></div>
            <?php if (!empty($card['note'])): ?><small class="text-muted"><?php echo html_escape($card['note']); ?></small><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="pos-report-section p-3 mb-3">
      <form method="get" class="pos-report-filter-box pos-report-sales-filter-box">
        <div class="pos-report-sales-filter-field">
          <label class="form-label small text-muted mb-1">Cari</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Order / customer / meja">
        </div>
        <div class="pos-report-sales-filter-field">
          <label class="form-label small text-muted mb-1">Scope</label>
          <select name="order_scope" class="form-select">
            <?php $scopeOptions = ['ALL' => 'Semua', 'REGULAR' => 'Reguler', 'EVENT' => 'Event']; ?>
            <?php foreach ($scopeOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['order_scope'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pos-report-sales-filter-field">
          <label class="form-label small text-muted mb-1">Service</label>
          <select name="service_type" class="form-select">
            <?php $serviceOptions = ['ALL' => 'Semua', 'DINE_IN' => 'Dine In', 'TAKE_AWAY' => 'Take Away', 'DELIVERY' => 'Delivery', 'PICKUP' => 'Pickup']; ?>
            <?php foreach ($serviceOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['service_type'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pos-report-sales-filter-field">
          <label class="form-label small text-muted mb-1">Metode Pembayaran</label>
          <select name="payment_method_id" class="form-select">
            <option value="0">Semua metode</option>
            <?php foreach ($paymentMethods as $method): ?>
              <?php $methodId = (int)($method['id'] ?? 0); ?>
              <option value="<?php echo $methodId; ?>"<?php echo $methodId === (int)($filters['payment_method_id'] ?? 0) ? ' selected' : ''; ?>>
                <?php echo html_escape((string)($method['method_name'] ?? '-')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pos-report-sales-filter-field">
          <label class="form-label small text-muted mb-1">Dari</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>">
        </div>
        <div class="pos-report-sales-filter-field">
          <label class="form-label small text-muted mb-1">Sampai</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>">
        </div>
        <div class="pos-report-sales-filter-field">
          <label class="form-label small text-muted mb-1">Baris</label>
          <select name="limit" class="form-select">
            <?php foreach ([25, 50, 100, 200] as $limit): ?>
              <option value="<?php echo $limit; ?>"<?php echo $limit === (int)($filters['limit'] ?? 25) ? ' selected' : ''; ?>><?php echo $limit; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pos-report-sales-filter-action">
          <button type="submit" class="btn btn-dark">Terapkan</button>
        </div>
        <div class="pos-report-sales-filter-action">
          <a href="<?php echo site_url('pos/reports/sales'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>

    <div class="pos-report-section p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h5 class="mb-1">Daftar Penjualan</h5>
          <div class="pos-report-meta">Diurutkan berdasarkan waktu order terbaru, bukan waktu pembayaran. Berlaku pada tabel desktop dan kartu mobile.</div>
        </div>
      </div>

      <div class="pos-report-sales-mobile-list mb-3">
        <?php if (empty($rows)): ?>
          <div class="pos-report-sales-mobile-card text-center pos-report-empty">Tidak ada data penjualan untuk filter yang dipilih.</div>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
            $scope = strtoupper((string)($row['order_scope'] ?? ''));
            $service = strtoupper((string)($row['service_type'] ?? ''));
            $customerName = trim((string)($row['customer_display_name'] ?? ''));
            $metaLine = [];
            if (!empty($row['outlet_name'])) {
                $metaLine[] = $row['outlet_name'];
            }
            if ($customerName !== '') {
                $metaLine[] = $customerName;
            }
            ?>
            <div class="pos-report-sales-mobile-card">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['order_no'] ?? '-')); ?></div>
                  <div class="pos-report-meta"><?php echo html_escape(implode(' | ', $metaLine)); ?></div>
                </div>
                <div><?php echo $statusBadge((string)($row['status'] ?? ''), $statusLabel((string)($row['status'] ?? ''))); ?></div>
              </div>
              <div class="pos-report-sales-mobile-grid mb-3">
                <div>
                  <div class="pos-report-sales-mobile-label">Ordered</div>
                  <div><?php echo html_escape($dt($row['ordered_at'] ?? null)); ?></div>
                </div>
                <div>
                  <div class="pos-report-sales-mobile-label">Paid</div>
                  <div><?php echo html_escape($dt($row['paid_at'] ?? null)); ?></div>
                </div>
                <div>
                  <div class="pos-report-sales-mobile-label">Tipe</div>
                  <div><?php echo html_escape(($scope !== '' ? $scope : '-') . ' / ' . ($service !== '' ? $service : '-')); ?></div>
                </div>
                <div>
                  <div class="pos-report-sales-mobile-label">Penjualan Bersih</div>
                  <div class="fw-semibold"><?php echo $money($row['net_sales'] ?? 0); ?></div>
                </div>
                <div>
                  <div class="pos-report-sales-mobile-label">HPP Terkini</div>
                  <div><?php echo $money($row['hpp_final_amount'] ?? 0); ?></div>
                </div>
                <div>
                  <div class="pos-report-sales-mobile-label">Laba Kotor</div>
                  <div class="<?php echo (float)($row['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($row['gross_profit'] ?? 0); ?></div>
                </div>
              </div>
              <div class="pos-report-inline-list mb-3">
                <div><?php echo html_escape((string)($row['payment_method_names'] ?? '-')); ?></div>
                <div class="muted">Tagihan sebelum refund <?php echo $money($row['billing_before_refund'] ?? $row['grand_total'] ?? 0); ?> | Refund <?php echo $money($row['refund_amount'] ?? 0); ?> | Void <?php echo $money($row['void_amount'] ?? 0); ?></div>
              </div>
              <div class="d-grid">
                <div class="pos-report-actions justify-content-start">
                  <a href="<?php echo site_url('pos/reports/sales-detail/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary pos-sales-action-icon" data-action-label="Detail" aria-label="Detail"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></a>
                  <a href="<?php echo site_url('pos/reports/sales/print/' . (int)($row['id'] ?? 0) . '/invoice'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary pos-sales-action-icon" data-action-label="Invoice" aria-label="Cetak Invoice"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2.5h8l4 4V21.5H6z"/><path d="M14 2.5v4h4M9 11h6M9 15h6"/></svg></a>
                  <a href="<?php echo site_url('pos/reports/sales/print/' . (int)($row['id'] ?? 0) . '/receipt'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary pos-sales-action-icon" data-action-label="Kwitansi" aria-label="Cetak Kwitansi"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3.5h14v17l-2.3-1.6-2.3 1.6-2.4-1.6-2.3 1.6-2.4-1.6L5 20.5z"/><path d="M8.5 8h7M8.5 12h7"/></svg></a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="pos-report-table-wrap pos-report-sales-desktop-table">
        <div class="table-responsive pos-report-table-scroll">
          <table class="table table-sm align-middle mb-0 pos-report-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Waktu</th>
                <th>Tipe</th>
                <th>Pembayaran</th>
                <th class="text-end">Penjualan Bersih</th>
                <th class="text-end">HPP Terkini</th>
                <th class="text-end">Laba Kotor</th>
                <th class="text-end">Margin</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="9" class="text-center pos-report-empty">Tidak ada data penjualan untuk filter yang dipilih.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                  $scope = strtoupper((string)($row['order_scope'] ?? ''));
                  $service = strtoupper((string)($row['service_type'] ?? ''));
                    $customerName = trim((string)($row['customer_display_name'] ?? ''));
                  $metaLine = [];
                  if (!empty($row['outlet_name'])) {
                      $metaLine[] = $row['outlet_name'];
                  }
                    if ($customerName !== '') {
                      $metaLine[] = $customerName;
                  }
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo html_escape((string)($row['order_no'] ?? '-')); ?></div>
                      <div class="pos-report-meta"><?php echo html_escape(implode(' | ', $metaLine)); ?></div>
                    </td>
                    <td>
                      <div><?php echo html_escape($dt($row['ordered_at'] ?? null)); ?></div>
                      <div class="pos-report-meta">Bayar <?php echo html_escape($dt($row['paid_at'] ?? null)); ?></div>
                    </td>
                    <td>
                      <div class="pos-report-inline-list">
                        <div><?php echo $statusBadge((string)($row['status'] ?? ''), $statusLabel((string)($row['status'] ?? ''))); ?></div>
                        <div class="muted"><?php echo html_escape(($scope !== '' ? $scope : '-') . ' / ' . ($service !== '' ? $service : '-')); ?></div>
                      </div>
                    </td>
                    <td>
                      <div class="pos-report-inline-list">
                        <div><?php echo html_escape((string)($row['payment_method_names'] ?? '-')); ?></div>
                        <div class="muted">Refund <?php echo $money($row['refund_amount'] ?? 0); ?> · Void <?php echo $money($row['void_amount'] ?? 0); ?></div>
                      </div>
                    </td>
                    <td class="text-end">
                      <div class="fw-semibold"><?php echo $money($row['net_sales'] ?? 0); ?></div>
                      <div class="pos-report-meta">Tagihan sebelum refund <?php echo $money($row['billing_before_refund'] ?? $row['grand_total'] ?? 0); ?> | Refund <?php echo $money($row['refund_amount'] ?? 0); ?></div>
                    </td>
                    <td class="text-end">
                      <div><?php echo $money($row['hpp_final_amount'] ?? 0); ?></div>
                      <?php if (abs((float)($row['hpp_deficit_correction_amount'] ?? 0)) > 0.009): ?>
                        <div class="pos-report-meta">Koreksi <?php echo $money($row['hpp_deficit_correction_amount'] ?? 0); ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end <?php echo (float)($row['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($row['gross_profit'] ?? 0); ?></td>
                    <td class="text-end <?php echo (float)($row['margin_percent'] ?? 0) < 0 ? 'text-danger' : ''; ?>"><?php echo number_format((float)($row['margin_percent'] ?? 0), 2, ',', '.'); ?>%</td>
                    <td>
                      <div class="pos-report-actions">
                        <a href="<?php echo site_url('pos/reports/sales-detail/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary pos-sales-action-icon" data-action-label="Detail" aria-label="Detail"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></a>
						<a href="<?php echo site_url('pos/reports/sales/print/' . (int)($row['id'] ?? 0) . '/invoice'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary pos-sales-action-icon" data-action-label="Invoice" aria-label="Cetak Invoice"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2.5h8l4 4V21.5H6z"/><path d="M14 2.5v4h4M9 11h6M9 15h6"/></svg></a>
						<a href="<?php echo site_url('pos/reports/sales/print/' . (int)($row['id'] ?? 0) . '/receipt'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary pos-sales-action-icon" data-action-label="Kwitansi" aria-label="Cetak Kwitansi"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3.5h14v17l-2.3-1.6-2.3 1.6-2.4-1.6-2.3 1.6-2.4-1.6L5 20.5z"/><path d="M8.5 8h7M8.5 12h7"/></svg></a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php $this->load->view('pos/_report_pager', ['meta' => $meta, 'filters' => $filters, 'pager_path' => 'pos/reports/sales']); ?>
    </div>
  </div>
</div>
