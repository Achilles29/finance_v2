<?php
$money = static function ($value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$qty = static function ($value, int $decimals = 0): string {
    return number_format((float)$value, $decimals, ',', '.');
};
?>
<?php $this->load->view('pos/_report_styles'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="pos-report-shell">
    <div class="pos-report-hero mb-3">
      <div class="pos-report-title">Laporan Penjualan Extra POS</div>
      <p class="pos-report-copy mb-0">Ringkasan penjualan extra per item. Nilai extra dibaca dari transaksi POS, lalu dikurangi bagian potongan transaksi dan refund extra yang benar-benar sudah diposting.</p>
    </div>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'sales_extra']); ?>
    <?php $this->load->view('pos/_report_filter_summary', ['filters' => $filters, 'outlets' => $outlets]); ?>

    <div class="row g-3 mb-3">
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Extra</div>
          <div class="pos-report-card-value"><?php echo number_format((int)($overview['extra_count'] ?? 0)); ?></div>
          <div class="pos-report-card-note">Jumlah extra unik yang muncul.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Order</div>
          <div class="pos-report-card-value"><?php echo number_format((int)($overview['order_count'] ?? 0)); ?></div>
          <div class="pos-report-card-note">Order yang memakai extra.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Produk Terkait</div>
          <div class="pos-report-card-value"><?php echo number_format((int)($overview['product_count'] ?? 0)); ?></div>
          <div class="pos-report-card-note">Produk induk yang memakai extra.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Qty Extra</div>
          <div class="pos-report-card-value"><?php echo $qty($overview['qty_total'] ?? 0); ?></div>
          <div class="pos-report-card-note">Akumulasi qty extra sebelum refund.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Refund</div>
          <div class="pos-report-card-value"><?php echo $money($overview['refund_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">Refund khusus extra.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Penjualan Bersih Item</div>
          <div class="pos-report-card-value"><?php echo $money($overview['net_sales'] ?? 0); ?></div>
          <div class="pos-report-card-note">Gross dikurangi bagian potongan dan refund extra.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">HPP Saat Jual</div>
          <div class="pos-report-card-value"><?php echo $money($overview['hpp_sale_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">Biaya extra pada saat order dibuat.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">HPP Terkini</div>
          <div class="pos-report-card-value"><?php echo $money($overview['hpp_final_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">HPP saat jual dikurangi HPP extra yang direfund.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Laba Kotor</div>
          <div class="pos-report-card-value <?php echo (float)($overview['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($overview['gross_profit'] ?? 0); ?></div>
          <div class="pos-report-card-note">Margin <?php echo number_format((float)($overview['margin_percent'] ?? 0), 2, ',', '.'); ?>%.</div>
        </div>
      </div>
    </div>

    <div class="pos-report-section p-3 mb-3">
      <form method="get" class="pos-report-filter-box row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
          <label class="form-label small text-muted mb-1">Cari extra</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Extra / produk / kategori / divisi">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Outlet</label>
          <select name="outlet_id" class="form-select">
            <option value="0">Semua outlet</option>
            <?php foreach ((array)$outlets as $outlet): ?>
              <?php $outletId = (int)($outlet['id'] ?? 0); ?>
              <option value="<?php echo $outletId; ?>"<?php echo $outletId === (int)($filters['outlet_id'] ?? 0) ? ' selected' : ''; ?>>
                <?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Status</label>
          <select name="status" class="form-select">
            <?php $statusOptions = ['ALL' => 'Semua', 'CONFIRMED' => 'Confirmed', 'PAID_PARTIAL' => 'Paid Partial', 'PAID' => 'Paid', 'IN_KITCHEN' => 'In Kitchen', 'READY' => 'Ready', 'SERVED' => 'Served', 'REFUND_PARTIAL' => 'Refund Partial', 'REFUND_FULL' => 'Refund Full']; ?>
            <?php foreach ($statusOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['status'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Scope</label>
          <select name="order_scope" class="form-select">
            <?php $scopeOptions = ['ALL' => 'Semua', 'REGULAR' => 'Reguler', 'EVENT' => 'Event']; ?>
            <?php foreach ($scopeOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['order_scope'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label small text-muted mb-1">Service</label>
          <select name="service_type" class="form-select">
            <?php $serviceOptions = ['ALL' => 'Semua', 'DINE_IN' => 'Dine In', 'TAKE_AWAY' => 'Take Away', 'DELIVERY' => 'Delivery', 'PICKUP' => 'Pickup']; ?>
            <?php foreach ($serviceOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['service_type'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Dari</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Sampai</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Baris</label>
          <select name="limit" class="form-select">
            <?php foreach ([25, 50, 100, 200] as $limit): ?>
              <option value="<?php echo $limit; ?>"<?php echo $limit === (int)($filters['limit'] ?? 25) ? ' selected' : ''; ?>><?php echo $limit; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-4 col-md-12">
          <div class="d-flex gap-2 justify-content-lg-end">
            <a href="<?php echo site_url('pos/reports/sales-extra'); ?>" class="btn btn-outline-secondary w-100">Reset</a>
            <button type="submit" class="btn btn-dark w-100">Terapkan</button>
          </div>
        </div>
      </form>
    </div>

    <div class="pos-report-section p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h5 class="mb-1">Overview Extra</h5>
          <div class="pos-report-meta">Nilai di sini hanya untuk extra. Koreksi HPP defisit tidak dibagi paksa ke extra karena sumber koreksi persediaan dicatat per line produk induk.</div>
        </div>
      </div>

      <div class="pos-report-table-wrap">
        <div class="table-responsive pos-report-table-scroll">
          <table class="table table-sm align-middle mb-0 pos-report-table">
            <thead>
              <tr>
                <th>Extra</th>
                <th>Tipe</th>
                <th>Sumber</th>
                <th class="text-end">Order</th>
                <th class="text-end">Produk</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Gross</th>
                <th class="text-end">Potongan</th>
                <th class="text-end">Refund</th>
                <th class="text-end">Penjualan Bersih</th>
                <th class="text-end">HPP Saat Jual</th>
                <th class="text-end">HPP Refund</th>
                <th class="text-end">HPP Terkini</th>
                <th class="text-end">Laba Kotor</th>
                <th class="text-end">Margin</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="15" class="text-center pos-report-empty">Belum ada data extra untuk filter yang dipilih.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo html_escape((string)($row['extra_name'] ?? '-')); ?></div>
                      <div class="pos-report-meta"><?php echo html_escape((string)($row['extra_code'] ?? '-')); ?></div>
                    </td>
                    <td><?php echo html_escape((string)($row['extra_type'] ?? '-')); ?></td>
                    <td>
                      <div><?php echo html_escape((string)($row['source_kind'] ?? '-')); ?></div>
                      <div class="pos-report-meta">Harga master <?php echo $money($row['selling_price'] ?? 0); ?></div>
                    </td>
                    <td class="text-end"><?php echo number_format((int)($row['order_count'] ?? 0)); ?></td>
                    <td class="text-end"><?php echo number_format((int)($row['product_count'] ?? 0)); ?></td>
                    <td class="text-end">
                      <div><?php echo $qty($row['qty_total'] ?? 0); ?></div>
                      <div class="pos-report-meta">Refund qty <?php echo $qty($row['refund_qty'] ?? 0); ?></div>
                    </td>
                    <td class="text-end"><?php echo $money($row['gross_sales'] ?? 0); ?></td>
                    <td class="text-end text-danger"><?php echo $money($row['sales_discount_amount'] ?? 0); ?></td>
                    <td class="text-end text-danger"><?php echo $money($row['refund_amount'] ?? 0); ?></td>
                    <td class="text-end fw-semibold"><?php echo $money($row['net_sales'] ?? 0); ?></td>
                    <td class="text-end"><?php echo $money($row['hpp_sale_amount'] ?? 0); ?></td>
                    <td class="text-end text-success"><?php echo $money($row['hpp_refund_reversed_amount'] ?? 0); ?></td>
                    <td class="text-end"><?php echo $money($row['hpp_final_amount'] ?? 0); ?></td>
                    <td class="text-end <?php echo (float)($row['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($row['gross_profit'] ?? 0); ?></td>
                    <td class="text-end <?php echo (float)($row['margin_percent'] ?? 0) < 0 ? 'text-danger' : ''; ?>"><?php echo number_format((float)($row['margin_percent'] ?? 0), 2, ',', '.'); ?>%</td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php $this->load->view('pos/_report_pager', ['meta' => $meta, 'filters' => $filters, 'pager_path' => 'pos/reports/sales-extra']); ?>
    </div>
  </div>
</div>
