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
      <div class="pos-report-title">Laporan Penjualan Produk POS</div>
      <p class="pos-report-copy mb-0">Penjualan bersih dihitung dari nilai produk dan extra terkait, dikurangi potongan transaksi yang dibagi proporsional serta refund. HPP saat jual, pembalikan HPP refund, dan koreksi defisit ditampilkan terpisah agar margin dapat diaudit.</p>
    </div>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'sales_detail']); ?>
    <?php $this->load->view('pos/_report_filter_summary', ['filters' => $filters, 'outlets' => $outlets]); ?>

    <div class="row g-3 mb-3">
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Produk</div>
          <div class="pos-report-card-value"><?php echo number_format((int)($overview['product_count'] ?? 0)); ?></div>
          <div class="pos-report-card-note">Produk unik yang muncul di periode ini; bundle ditampilkan sebagai kelompok di tabel.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Order</div>
          <div class="pos-report-card-value"><?php echo number_format((int)($overview['order_count'] ?? 0)); ?></div>
          <div class="pos-report-card-note">Order yang ikut menyumbang ke laporan produk.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Qty Terjual</div>
          <div class="pos-report-card-value"><?php echo $qty($overview['qty_total'] ?? 0); ?></div>
          <div class="pos-report-card-note">Akumulasi qty produk sebelum refund.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Gross</div>
          <div class="pos-report-card-value"><?php echo $money($overview['gross_sales'] ?? 0); ?></div>
          <div class="pos-report-card-note">Penjualan produk plus extra terkait.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Refund</div>
          <div class="pos-report-card-value"><?php echo $money($overview['refund_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">Nilai refund untuk produk yang sudah dibayar.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Penjualan Bersih Item</div>
          <div class="pos-report-card-value"><?php echo $money($overview['net_sales'] ?? 0); ?></div>
          <div class="pos-report-card-note">Tidak termasuk pajak dan service transaksi.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">HPP Saat Jual</div>
          <div class="pos-report-card-value"><?php echo $money($overview['hpp_sale_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">HPP produk dan extra pada saat order dibuat.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Koreksi HPP Defisit</div>
          <div class="pos-report-card-value"><?php echo $money($overview['hpp_deficit_correction_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">Perubahan biaya setelah defisit terselesaikan.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">HPP Terkini</div>
          <div class="pos-report-card-value"><?php echo $money($overview['hpp_final_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">HPP setelah refund dan koreksi defisit.</div>
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
          <label class="form-label small text-muted mb-1">Cari produk</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Produk / kategori / divisi">
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
            <a href="<?php echo site_url('pos/reports/sales-detail'); ?>" class="btn btn-outline-secondary w-100">Reset</a>
            <button type="submit" class="btn btn-dark w-100">Terapkan</button>
          </div>
        </div>
      </form>
    </div>

    <div class="pos-report-section p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h5 class="mb-1">Overview Produk & Bundle</h5>
          <div class="pos-report-meta">Bundle ditampilkan sebagai satu kelompok, lalu isi produknya berada tepat di bawahnya. Penjualan bersih item tidak memasukkan pajak/service. Diskon transaksi dibagi proporsional ke produk dan extra.</div>
        </div>
      </div>

      <div class="pos-report-table-wrap">
        <div class="table-responsive pos-report-table-scroll">
          <table class="table table-sm align-middle mb-0 pos-report-table">
            <thead>
              <tr>
                <th>Produk / Bundle</th>
                <th>Kategori</th>
                <th>Divisi</th>
                <th class="text-end">Order</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Gross</th>
                <th class="text-end">Potongan</th>
                <th class="text-end">Refund</th>
                <th class="text-end">Penjualan Bersih Item</th>
                <th class="text-end">HPP Saat Jual</th>
                <th class="text-end">HPP Refund</th>
                <th class="text-end">Koreksi HPP</th>
                <th class="text-end">HPP Terkini</th>
                <th class="text-end">Laba Kotor</th>
                <th class="text-end">Margin</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $displayRows = [];
              foreach ((array)$rows as $topRow) {
                  $displayRows[] = $topRow;
                  if (strtoupper((string)($topRow['row_type'] ?? '')) === 'BUNDLE') {
                      foreach ((array)($topRow['children'] ?? []) as $bundleChild) {
                          $displayRows[] = $bundleChild;
                      }
                  }
              }
              ?>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="15" class="text-center pos-report-empty">Belum ada data produk atau bundle untuk filter yang dipilih.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($displayRows as $row): ?>
                  <?php $isBundle = strtoupper((string)($row['row_type'] ?? '')) === 'BUNDLE'; ?>
                  <tr<?php echo $isBundle ? ' class="table-warning"' : ''; ?>>
                    <td>
                      <?php if ($isBundle): ?>
                        <div class="fw-semibold text-uppercase">Paket: <?php echo html_escape((string)($row['bundle_name'] ?? $row['product_name'] ?? '-')); ?></div>
                        <div class="pos-report-meta"><?php echo html_escape((string)($row['bundle_code'] ?? $row['product_code'] ?? '-')); ?> | <?php echo number_format(count((array)($row['children'] ?? []))); ?> produk di dalam bundle</div>
                      <?php else: ?>
                        <div class="<?php echo strtoupper((string)($row['row_type'] ?? '')) === 'BUNDLE_ITEM' ? 'ps-3 fw-semibold' : 'fw-semibold'; ?>">
                          <?php echo strtoupper((string)($row['row_type'] ?? '')) === 'BUNDLE_ITEM' ? '-> ' : ''; ?><?php echo html_escape((string)($row['product_name'] ?? '-')); ?>
                        </div>
                        <div class="pos-report-meta<?php echo strtoupper((string)($row['row_type'] ?? '')) === 'BUNDLE_ITEM' ? ' ps-3' : ''; ?>"><?php echo html_escape((string)($row['product_code'] ?? '-')); ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?php echo html_escape((string)($row['category_name'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                    <td class="text-end"><?php echo number_format((int)($row['order_count'] ?? 0)); ?></td>
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
                    <td class="text-end <?php echo (float)($row['hpp_deficit_correction_amount'] ?? 0) < 0 ? 'text-success' : ''; ?>"><?php echo $money($row['hpp_deficit_correction_amount'] ?? 0); ?></td>
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

      <?php $this->load->view('pos/_report_pager', ['meta' => $meta, 'filters' => $filters, 'pager_path' => 'pos/reports/sales-detail']); ?>
    </div>
  </div>
</div>
