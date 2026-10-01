<?php
$filters = is_array($filters ?? null) ? $filters : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
$audit = is_array($audit ?? null) ? $audit : [];
$checks = is_array($audit['checks'] ?? null) ? $audit['checks'] : [];
$summary = is_array($audit['summary'] ?? null) ? $audit['summary'] : [];

$money = static function ($value): string {
    return 'Rp ' . number_format((float)$value, 2, ',', '.');
};
$dateLabel = static function ($value): string {
    $time = $value ? strtotime((string)$value) : false;
    return $time ? date('d/m/Y', $time) : '-';
};
$statusLabel = static function (string $status): string {
    $status = strtoupper($status);
    if ($status === 'ERROR') {
        return 'PERLU TINDAKAN';
    }
    if ($status === 'WARNING') {
        return 'PERLU DICEK';
    }
    return 'AMAN';
};
$nextStep = static function (string $code): array {
    $steps = [
        'REFUND_EXCEEDS_PAYMENT' => [
            'Buka detail transaksi dan periksa dokumen refund posted. Jangan membuat adjustment stok untuk membetulkan nominal refund.',
            'pos/reports/sales',
        ],
        'FINAL_HPP_NEGATIVE' => [
            'Periksa detail transaksi, HPP refund yang dibalik, dan koreksi HPP defisit. Jangan menghapus jejak refund atau koreksi hanya agar angkanya nol.',
            'pos/reports/sales',
        ],
        'ZERO_HPP_WITH_NET_SALE' => [
            'Pastikan produk memang boleh tanpa HPP. Bila produk memakai resep, telusuri stock commit dan snapshot HPP order tersebut.',
            'pos/stock-commit-audit',
        ],
        'NEGATIVE_GROSS_PROFIT' => [
            'Bandingkan harga jual, potongan, refund, dan HPP snapshot sebelum menarik kesimpulan soal laba transaksi.',
            'pos/reports/sales',
        ],
        'DEFICIT_HPP_CORRECTION_UNLINKED' => [
            'Telusuri defisit dan koreksi HPP asal. Koreksi yang kehilangan tautan harus diperbaiki terkontrol, bukan dihapus.',
            'inventory/stock/deficits',
        ],
        'DEFICIT_HPP_SCHEMA_UNAVAILABLE' => [
            'Jalankan fondasi migration HPP defisit Fase 5A terlebih dahulu, lalu ulangi audit ini.',
            '',
        ],
    ];

    return $steps[$code] ?? ['Periksa data sumber terlebih dahulu sebelum melakukan perubahan apa pun.', ''];
};
?>
<?php $this->load->view('pos/_report_styles'); ?>
<style>
  .pos-sales-audit-hero-note {
    margin-top: .8rem;
    border: 1px solid #f0d8bd;
    border-radius: 14px;
    background: #fff6e9;
    color: #6f4d28;
    padding: .7rem .85rem;
    font-size: .86rem;
  }
  .pos-sales-audit-filter {
    display: grid;
    grid-template-columns: minmax(140px, 1fr) minmax(140px, 1fr) minmax(190px, 1.4fr) minmax(130px, .8fr) minmax(132px, .75fr) minmax(110px, .62fr);
    gap: .65rem;
    align-items: end;
  }
  .pos-sales-audit-filter .form-control,
  .pos-sales-audit-filter .form-select,
  .pos-sales-audit-filter .btn { height: 42px; }
  .pos-sales-audit-filter label { font-size: .76rem; font-weight: 700; color: #785e51; margin-bottom: .3rem; }
  .pos-sales-audit-summary { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.8rem; }
  .pos-sales-audit-card { min-height:108px; position:relative; overflow:hidden; }
  .pos-sales-audit-card:after { content:''; position:absolute; width:72px; height:72px; border-radius:50%; right:-25px; bottom:-32px; background:rgba(159,33,65,.08); }
  .pos-sales-audit-card.error:after { background:rgba(180,35,24,.12); }
  .pos-sales-audit-card.warning:after { background:rgba(181,116,0,.12); }
  .pos-sales-audit-callout { border:1px solid #f0d8bd; border-radius:17px; background:#fff8ee; color:#71512c; padding:.95rem 1rem; }
  .pos-sales-audit-callout strong { color:#4d3420; }
  .pos-sales-audit-error { border:1px solid #f2c0bd; border-radius:17px; background:#fff3f2; color:#a5291e; padding:.95rem 1rem; }
  .pos-sales-audit-check { border:1px solid #efdcd0; border-radius:18px; overflow:hidden; background:#fff; margin-bottom:.75rem; }
  .pos-sales-audit-check:last-child { margin-bottom:0; }
  .pos-sales-audit-check summary { list-style:none; cursor:pointer; display:grid; grid-template-columns:auto minmax(0, 1fr) auto; gap:.75rem; align-items:center; padding:.9rem 1rem; }
  .pos-sales-audit-check summary::-webkit-details-marker { display:none; }
  .pos-sales-audit-check[open] summary { border-bottom:1px solid #f0dfd4; background:linear-gradient(90deg,#fffaf5,#fff); }
  .pos-sales-audit-status { display:inline-flex; align-items:center; justify-content:center; min-width:111px; border-radius:999px; padding:.35rem .58rem; font-size:.7rem; font-weight:800; letter-spacing:.03em; }
  .pos-sales-audit-status.pass { background:#e7f7ed; color:#1e7a45; }
  .pos-sales-audit-status.error { background:#fde8e7; color:#b42318; }
  .pos-sales-audit-status.warning { background:#fff2dc; color:#8a5a00; }
  .pos-sales-audit-title { color:#3f2a1f; font-weight:800; }
  .pos-sales-audit-code { color:#967c6f; font-size:.73rem; letter-spacing:.04em; margin-top:.12rem; }
  .pos-sales-audit-count { color:#6d5143; font-size:.84rem; font-weight:800; white-space:nowrap; }
  .pos-sales-audit-body { padding:1rem; }
  .pos-sales-audit-message { color:#70594c; line-height:1.55; }
  .pos-sales-audit-next { margin-top:.8rem; border-radius:13px; background:#fff7ef; border:1px solid #f0ddcf; padding:.7rem .8rem; display:flex; gap:.8rem; justify-content:space-between; align-items:center; flex-wrap:wrap; color:#674b3d; font-size:.84rem; }
  .pos-sales-audit-table-wrap { margin-top:.9rem; max-height:360px; overflow:auto; border:1px solid #efddd1; border-radius:15px; }
  .pos-sales-audit-table { margin:0; min-width:840px; }
  .pos-sales-audit-table thead th { position:sticky; top:0; z-index:2; background:#9f2141; color:#fff; font-size:.72rem; text-transform:uppercase; letter-spacing:.035em; border-color:#9f2141; white-space:nowrap; }
  .pos-sales-audit-table tbody td { vertical-align:top; border-color:#f3e6de; font-size:.83rem; }
  .pos-sales-audit-table tbody tr:nth-child(even) td { background:#fffaf6; }
  .pos-sales-audit-empty { color:#8a7063; text-align:center; padding:1.4rem; }
  .pos-sales-audit-metric { display:grid; gap:.15rem; }
  .pos-sales-audit-metric small { color:#8c7265; }
  @media (max-width:1199.98px) { .pos-sales-audit-filter { grid-template-columns:repeat(3,minmax(0,1fr)); } }
  @media (max-width:767.98px) {
    .pos-sales-audit-filter, .pos-sales-audit-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .pos-sales-audit-filter .pos-sales-audit-search { grid-column:span 2; }
    .pos-sales-audit-check summary { grid-template-columns:auto minmax(0,1fr); }
    .pos-sales-audit-count { grid-column:2; }
    .pos-sales-audit-status { min-width:0; }
  }
  @media (max-width:430px) { .pos-sales-audit-filter, .pos-sales-audit-summary { grid-template-columns:1fr; } .pos-sales-audit-filter .pos-sales-audit-search { grid-column:auto; } }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
  <div class="pos-report-shell">
    <section class="pos-report-hero mb-3">
      <div class="pos-report-title"><i class="ri-shield-search-line me-2"></i><?php echo html_escape((string)($page_title ?? 'Audit Penjualan & HPP POS')); ?></div>
      <p class="pos-report-copy mb-0">Pemeriksaan baca-saja untuk mendeteksi angka penjualan, refund, dan HPP yang perlu ditelusuri sebelum dipakai sebagai bahan laporan keuangan.</p>
      <div class="pos-sales-audit-hero-note"><strong>Kebijakan pendapatan:</strong> pajak dan service tetap dianggap sebagai pendapatan kafe. Karena itu keduanya tetap berada pada nilai tagihan transaksi saat audit menghitung penjualan bersih dan laba kotor.</div>
    </section>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'sales_audit']); ?>

    <section class="pos-report-section p-3 mb-3">
      <form method="get" class="pos-sales-audit-filter">
        <input type="hidden" name="run" value="1">
        <div>
          <label for="salesAuditDateFrom">Dari tanggal</label>
          <input id="salesAuditDateFrom" type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>">
        </div>
        <div>
          <label for="salesAuditDateTo">Sampai tanggal</label>
          <input id="salesAuditDateTo" type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>">
        </div>
        <div class="pos-sales-audit-search">
          <label for="salesAuditSearch">Cari order atau outlet</label>
          <input id="salesAuditSearch" type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Contoh: POS-202608 atau nama outlet">
        </div>
        <div>
          <label for="salesAuditOutlet">Outlet</label>
          <select id="salesAuditOutlet" name="outlet_id" class="form-select">
            <option value="0">Semua outlet</option>
            <?php foreach ($outlets as $outlet): ?>
              <?php $outletId = (int)($outlet['id'] ?? 0); ?>
              <option value="<?php echo $outletId; ?>"<?php echo $outletId === (int)($filters['outlet_id'] ?? 0) ? ' selected' : ''; ?>><?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="salesAuditLimit">Contoh per temuan</label>
          <select id="salesAuditLimit" name="limit" class="form-select">
            <?php foreach ([5, 10, 20, 50] as $limit): ?>
              <option value="<?php echo $limit; ?>"<?php echo $limit === (int)($filters['limit'] ?? 10) ? ' selected' : ''; ?>><?php echo $limit; ?> baris</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-danger"><i class="ri-search-eye-line me-1"></i>Jalankan</button>
          <a href="<?php echo site_url('pos/reports/sales-audit'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
      <div class="pos-report-meta mt-2">Audit hanya berjalan setelah tombol ditekan. Halaman ini tidak mengubah order, refund, stok, lot, HPP, atau kas.</div>
    </section>

    <?php if (empty($run_audit)): ?>
      <div class="pos-sales-audit-callout"><strong>Siap diperiksa.</strong> Pilih rentang tanggal lalu tekan <strong>Jalankan</strong>. Gunakan hasilnya sebagai antrean penelusuran, bukan alasan untuk melakukan adjustment massal.</div>
    <?php elseif (!empty($audit_error)): ?>
      <div class="pos-sales-audit-error"><?php echo html_escape((string)$audit_error); ?></div>
    <?php else: ?>
      <section class="pos-sales-audit-summary mb-3">
        <div class="pos-report-card pos-sales-audit-card"><div class="pos-report-card-label">Pemeriksaan</div><div class="pos-report-card-value"><?php echo number_format((int)($summary['check_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-report-card-note">Jenis pemeriksaan yang selesai dibaca.</div></div>
        <div class="pos-report-card pos-sales-audit-card error"><div class="pos-report-card-label">Butuh Tindakan</div><div class="pos-report-card-value text-danger"><?php echo number_format((int)($summary['error_issue_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-report-card-note">Baris yang berisiko membuat angka salah.</div></div>
        <div class="pos-report-card pos-sales-audit-card warning"><div class="pos-report-card-label">Perlu Ditelusuri</div><div class="pos-report-card-value text-warning"><?php echo number_format((int)($summary['warning_issue_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-report-card-note">Belum tentu salah, tetapi perlu dicek.</div></div>
        <div class="pos-report-card pos-sales-audit-card"><div class="pos-report-card-label">Total Temuan</div><div class="pos-report-card-value"><?php echo number_format((int)($summary['issue_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-report-card-note">Total baris dari semua pemeriksaan.</div></div>
      </section>

      <section class="pos-report-section p-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h5 class="mb-1">Hasil Audit Penjualan dan HPP</h5>
            <div class="pos-report-meta">Periode <?php echo html_escape($dateLabel($filters['date_from'] ?? '')); ?> s/d <?php echo html_escape($dateLabel($filters['date_to'] ?? '')); ?>. Temuan merah dibuka otomatis agar dapat diprioritaskan.</div>
          </div>
        </div>

        <?php if (empty($checks)): ?>
          <div class="pos-sales-audit-empty">Audit tidak menghasilkan baris pemeriksaan.</div>
        <?php else: ?>
          <?php foreach ($checks as $check): ?>
            <?php
              $status = strtoupper((string)($check['status'] ?? 'PASS'));
              $statusClass = strtolower($status === 'PASS' ? 'pass' : $status);
              $code = (string)($check['code'] ?? 'AUDIT');
              $sampleRows = is_array($check['sample_rows'] ?? null) ? $check['sample_rows'] : [];
              $sourceType = (string)($check['source_type'] ?? 'ORDER');
              [$nextStepText, $nextStepUrl] = $nextStep($code);
            ?>
            <details class="pos-sales-audit-check"<?php echo $status !== 'PASS' ? ' open' : ''; ?>>
              <summary>
                <span class="pos-sales-audit-status <?php echo html_escape($statusClass); ?>"><?php echo html_escape($statusLabel($status)); ?></span>
                <span><span class="pos-sales-audit-title d-block"><?php echo html_escape((string)($check['title'] ?? $code)); ?></span><span class="pos-sales-audit-code d-block"><?php echo html_escape($code); ?></span></span>
                <span class="pos-sales-audit-count"><?php echo number_format((int)($check['issue_count'] ?? 0), 0, ',', '.'); ?> temuan</span>
              </summary>
              <div class="pos-sales-audit-body">
                <div class="pos-sales-audit-message"><?php echo html_escape((string)($check['message'] ?? '')); ?></div>
                <?php if ($status !== 'PASS'): ?>
                  <div class="pos-sales-audit-next">
                    <span><strong>Langkah aman:</strong> <?php echo html_escape($nextStepText); ?></span>
                    <?php if ($nextStepUrl !== ''): ?><a class="btn btn-sm btn-outline-danger" href="<?php echo site_url($nextStepUrl); ?>">Buka Halaman Terkait</a><?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($sampleRows) && $sourceType === 'ORDER'): ?>
                  <div class="pos-sales-audit-table-wrap">
                    <table class="table table-sm align-middle pos-sales-audit-table">
                      <thead><tr><th>Order</th><th>Tanggal</th><th>Outlet</th><th class="text-end">Terbayar</th><th class="text-end">Refund</th><th class="text-end">HPP Terkini</th><th class="text-end">Laba Kotor</th><th class="text-end">Aksi</th></tr></thead>
                      <tbody>
                        <?php foreach ($sampleRows as $row): ?>
                          <tr>
                            <td><div class="fw-semibold"><?php echo html_escape((string)($row['order_no'] ?? '-')); ?></div><small><?php echo html_escape((string)($row['status'] ?? '-')); ?></small></td>
                            <td><?php echo html_escape($dateLabel($row['order_date'] ?? '')); ?></td>
                            <td><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></td>
                            <td class="text-end"><?php echo $money($row['paid_total'] ?? $row['billing_before_refund'] ?? 0); ?></td>
                            <td class="text-end"><?php echo $money($row['refund_amount'] ?? 0); ?></td>
                            <td class="text-end"><div><?php echo $money($row['hpp_final_amount'] ?? 0); ?></div><?php if (abs((float)($row['hpp_deficit_correction_amount'] ?? 0)) > 0.009): ?><small>Koreksi <?php echo $money($row['hpp_deficit_correction_amount'] ?? 0); ?></small><?php endif; ?></td>
                            <td class="text-end <?php echo (float)($row['gross_profit'] ?? 0) < 0 ? 'text-danger' : ''; ?>"><?php echo $money($row['gross_profit'] ?? 0); ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('pos/reports/sales-detail/' . (int)($row['order_id'] ?? 0)); ?>">Detail</a></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php elseif (!empty($sampleRows) && $sourceType === 'DEFICIT_HPP_ADJUSTMENT'): ?>
                  <div class="pos-sales-audit-table-wrap">
                    <table class="table table-sm align-middle pos-sales-audit-table">
                      <thead><tr><th>Tanggal Pengakuan</th><th>Koreksi HPP</th><th>Order</th><th>Line</th><th class="text-end">Selisih HPP</th><th>Alasan Temuan</th><th class="text-end">Aksi</th></tr></thead>
                      <tbody>
                        <?php foreach ($sampleRows as $row): ?>
                          <?php $orderId = (int)($row['order_id'] ?? 0); ?>
                          <tr>
                            <td><?php echo html_escape($dateLabel($row['recognition_date'] ?? '')); ?></td>
                            <td><div class="fw-semibold">#<?php echo (int)($row['adjustment_id'] ?? 0); ?></div><small><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></small></td>
                            <td><?php echo html_escape((string)($row['order_no'] ?? ($orderId > 0 ? ('Order #' . $orderId) : '-'))); ?></td>
                            <td><?php echo (int)($row['order_line_id'] ?? 0) > 0 ? '#' . (int)$row['order_line_id'] : '-'; ?></td>
                            <td class="text-end"><?php echo $money($row['variance_amount'] ?? 0); ?></td>
                            <td><?php echo html_escape((string)($row['issue_reason'] ?? '-')); ?></td>
                            <td class="text-end"><?php if ($orderId > 0): ?><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('pos/reports/sales-detail/' . $orderId); ?>">Detail</a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </details>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
