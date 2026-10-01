<?php
$order = is_array($order ?? null) ? $order : [];
$header = is_array($order['header'] ?? null) ? $order['header'] : [];
$lines = is_array($order['lines'] ?? null) ? $order['lines'] : [];
$displayEntries = [];
$bundleIndexes = [];
foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }
    $bundleId = max(0, (int)($line['bundle_id'] ?? 0));
    $lineType = strtoupper(trim((string)($line['line_type'] ?? 'PRODUCT')));
    if ($bundleId <= 0) {
        if ($lineType !== 'BUNDLE_HEADER') {
            $displayEntries[] = ['type' => 'LINE', 'line' => $line];
        }
        continue;
    }
    $bundleKey = 'B:' . $bundleId;
    if (!isset($bundleIndexes[$bundleKey])) {
        $bundleIndexes[$bundleKey] = count($displayEntries);
        $displayEntries[] = [
            'type' => 'BUNDLE',
            'bundle_name' => trim((string)($line['bundle_name'] ?? '')),
            'bundle_code' => trim((string)($line['bundle_code'] ?? '')),
            'lines' => [],
        ];
    }
    if ($lineType !== 'BUNDLE_HEADER') {
        $displayEntries[$bundleIndexes[$bundleKey]]['lines'][] = $line;
    }
}
$displayEntries = array_values(array_filter($displayEntries, static function (array $entry): bool {
    return $entry['type'] !== 'BUNDLE' || !empty($entry['lines']);
}));
$payments = is_array($payments ?? null) ? $payments : [];
$refunds = is_array($refunds ?? null) ? $refunds : [];
$documentType = ($document_type ?? 'invoice') === 'receipt' ? 'receipt' : 'invoice';
$isReceipt = $documentType === 'receipt';
$documentLabel = $isReceipt ? 'KWITANSI' : 'INVOICE';
$reviewUrl = trim((string)($review_url ?? ''));
$money = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$qty = static function ($value): string { return number_format((float)$value, 0, ',', '.'); };
$dateTime = static function ($value): string {
    $time = $value ? strtotime((string)$value) : false;
    return $time ? date('d M Y, H:i', $time) : '-';
};
$customerName = trim((string)($header['customer_display_name'] ?? ''));
if ($customerName === '') $customerName = trim((string)($header['customer_name'] ?? '')) ?: (trim((string)($header['member_name'] ?? '')) ?: 'Walk-in Customer');
$discountTotal = (float)($header['discount_amount'] ?? 0) + (float)($header['promo_amount'] ?? 0) + (float)($header['voucher_amount'] ?? 0) + (float)($header['point_redeem_amount'] ?? 0) + (float)($header['compliment_amount'] ?? 0);
$refundTotal = array_reduce($refunds, static fn(float $total, array $row): float => $total + (float)($row['refund_amount'] ?? 0), 0.0);
$paidTotal = (float)($header['paid_total'] ?? 0);
$grandTotal = (float)($header['grand_total'] ?? 0);
$remaining = max(0, $grandTotal - $paidTotal);
$paymentRows = [];
foreach ($payments as $payment) {
    foreach ((array)($payment['lines'] ?? []) as $paymentLine) {
        $paymentRows[] = [
            'method_name' => (string)($paymentLine['method_name'] ?? '-'),
            'amount' => (float)($paymentLine['amount'] ?? 0),
            'reference_no' => (string)($paymentLine['reference_no'] ?? ''),
        ];
    }
}
$status = strtoupper((string)($header['status'] ?? 'DRAFT'));
$paymentState = $remaining <= 0.009 && $paidTotal > 0 ? 'LUNAS' : ($paidTotal > 0 ? 'SEBAGIAN' : 'BELUM LUNAS');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $documentLabel; ?> - <?php echo html_escape((string)($header['order_no'] ?? '-')); ?></title>
  <style>
    @page { size:A4; margin:14mm; }
    :root { --ink:#1d2928; --muted:#66736e; --accent:#9f2225; --accent-deep:#681518; --line:#dce5df; --wash:#f3f7f4; --gold:#b27a2a; }
    * { box-sizing:border-box; }
    body { margin:0; color:var(--ink); background:#edf1ee; font-family:"Trebuchet MS", "Segoe UI", sans-serif; font-size:12px; line-height:1.45; }
    .toolbar { position:fixed; top:18px; right:18px; z-index:5; display:flex; gap:8px; }
    .toolbar button { border:0; border-radius:8px; background:var(--accent); color:#fff; padding:10px 15px; font-weight:700; cursor:pointer; box-shadow:0 6px 18px rgba(104,21,24,.2); }
    .toolbar button:last-child { background:#fff; color:var(--accent-deep); border:1px solid #d9c3c3; }
    .sheet { width:182mm; min-height:267mm; margin:14px auto; padding:0 13mm 13mm; background:#fff; box-shadow:0 12px 35px rgba(27,44,35,.15); position:relative; overflow:hidden; }
    .sheet::before { content:""; position:absolute; top:0; right:0; left:0; height:7mm; background:linear-gradient(90deg,var(--accent-deep),var(--accent)); }
    .head { position:relative; display:flex; justify-content:space-between; align-items:center; gap:20px; border-bottom:2px solid var(--ink); padding:15mm 0 15px; margin-bottom:17px; }
    .brand { display:flex; align-items:center; gap:12px; min-width:0; }
    .logo { width:52px; height:52px; object-fit:contain; flex:0 0 auto; }
    .brand-name { font-size:17px; font-weight:900; letter-spacing:.04em; color:var(--accent-deep); text-transform:uppercase; }
    .brand-meta { color:var(--muted); margin-top:2px; max-width:310px; }
    .doc { min-width:218px; padding:12px 15px; text-align:right; color:#fff; background:linear-gradient(135deg,var(--accent-deep),var(--accent)); border-radius:12px 0 12px 12px; box-shadow:0 8px 18px rgba(104,21,24,.16); }
    .doc-label { color:#fff; font-weight:900; letter-spacing:.13em; font-size:22px; line-height:1; }
    .doc-no { margin-top:6px; font-weight:800; font-size:12px; color:rgba(255,255,255,.9); }
    .pill { display:inline-block; margin-top:7px; padding:3px 8px; border-radius:999px; background:#e7f3e8; color:#1d6b38; font-weight:800; font-size:10px; letter-spacing:.04em; }
    .pill.pending { background:#fff0d5; color:#925500; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:17px; }
    .label { color:var(--muted); text-transform:uppercase; letter-spacing:.08em; font-weight:800; font-size:9px; margin-bottom:4px; }
    .value { font-weight:700; font-size:13px; }
    .meta { color:var(--muted); margin-top:2px; }
    .panel { border:1px solid var(--line); border-radius:10px; overflow:hidden; margin-top:14px; }
    .panel-title { padding:8px 11px; background:var(--wash); color:var(--accent-deep); font-size:10px; letter-spacing:.1em; font-weight:900; text-transform:uppercase; }
    table { width:100%; border-collapse:collapse; }
    th { padding:8px 10px; text-align:left; color:var(--muted); border-bottom:1px solid var(--line); font-size:9px; text-transform:uppercase; letter-spacing:.07em; }
    td { padding:9px 10px; border-bottom:1px solid #edf1ee; vertical-align:top; }
    tr:last-child td { border-bottom:0; }
    .text-end { text-align:right; white-space:nowrap; }
    .product { font-weight:800; }
    .bundle-row td { padding:8px 10px; background:#fff4e8; color:var(--accent-deep); border-top:1px solid #efcfb6; border-bottom:1px solid #efcfb6; font-weight:900; text-transform:uppercase; letter-spacing:.03em; }
    .bundle-meta { margin-top:2px; color:#9f6b31; font-size:9px; font-weight:700; text-transform:none; }
    .bundle-item { padding-left:14px; position:relative; }
    .bundle-item::before { content:""; position:absolute; left:2px; top:7px; width:7px; height:7px; border-left:1px solid #ba8d68; border-bottom:1px solid #ba8d68; }
    .product-meta, .note { color:var(--muted); font-size:10px; margin-top:2px; }
    .extra { color:var(--muted); font-size:10px; margin-top:3px; }
    .totals { width:100%; max-width:310px; margin:15px 0 0 auto; }
    .totals td { padding:5px 0; border:0; }
    .totals .total td { padding:10px 0 2px; border-top:2px solid var(--ink); color:var(--accent-deep); font-size:15px; font-weight:900; }
    .payments { margin-top:17px; }
    .payment-row { display:flex; justify-content:space-between; gap:15px; padding:7px 0; border-bottom:1px solid #edf1ee; }
    .payment-row:last-child { border-bottom:0; }
    .payment-method { font-weight:800; }
    .payment-ref { font-size:10px; color:var(--muted); }
    .review-cta { margin-top:18px; border:1px solid #eadfd7; border-radius:14px; padding:12px 14px; background:linear-gradient(180deg,#fffdf9,#fff8f2); display:flex; align-items:center; justify-content:space-between; gap:14px; }
    .review-copy { min-width:0; }
    .review-kicker { color:var(--accent); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .review-title { margin-top:2px; font-size:15px; font-weight:900; color:var(--accent-deep); }
    .review-text { margin-top:5px; color:#6f5e58; }
    .review-link { margin-top:6px; font-size:9px; color:#9a857c; word-break:break-all; }
    .review-qr { width:96px; height:96px; flex:0 0 auto; display:grid; place-items:center; padding:6px; border:1px solid #ead2c8; border-radius:12px; background:#fff; box-shadow:0 6px 15px rgba(108,41,24,.08); }
    .review-qr img, .review-qr canvas { display:none !important; }
    .review-qr [data-pos-qr-visual="true"] { display:block !important; width:100% !important; height:100% !important; image-rendering:pixelated; }
    .review-qr.qr-render-fallback { font-size:9px; line-height:1.3; color:#9a3b32; text-align:center; }
    .notice { margin-top:18px; border-left:4px solid var(--gold); background:#fff8eb; padding:9px 11px; color:#6e5427; font-size:10px; }
    .footer { position:absolute; bottom:11mm; left:13mm; right:13mm; display:flex; justify-content:space-between; gap:10px; border-top:1px solid var(--line); padding-top:8px; color:var(--muted); font-size:9px; }
    @media (max-width:760px) { .review-cta { flex-direction:column; align-items:flex-start; } .review-qr { width:120px; height:120px; } }
    @media print { body { background:#fff; } .toolbar { display:none; } .sheet { width:auto; min-height:0; margin:0; padding:0; box-shadow:none; overflow:visible; } .footer { position:static; margin-top:24px; } }
  </style>
</head>
<body>
  <div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><button type="button" onclick="window.close()">Tutup</button></div>
  <main class="sheet">
    <header class="head">
      <div class="brand">
        <img class="logo" src="<?php echo html_escape((string)($logo_url ?? '')); ?>" alt="Logo">
        <div><div class="brand-name"><?php echo html_escape((string)($header['outlet_name'] ?? 'POS')); ?></div><div class="brand-meta">Dokumen transaksi resmi POS</div></div>
      </div>
      <div class="doc"><div class="doc-label"><?php echo $documentLabel; ?></div><div class="doc-no"><?php echo html_escape((string)($header['order_no'] ?? '-')); ?></div><span class="pill <?php echo $paymentState === 'LUNAS' ? '' : 'pending'; ?>"><?php echo $isReceipt ? $paymentState : html_escape($status); ?></span></div>
    </header>

    <section class="grid">
      <div><div class="label">Ditagihkan Kepada</div><div class="value"><?php echo html_escape($customerName); ?></div><?php if (!empty($header['member_no'])): ?><div class="meta">Member: <?php echo html_escape((string)$header['member_no']); ?></div><?php endif; ?><?php if (!empty($header['member_mobile_phone'])): ?><div class="meta"><?php echo html_escape((string)$header['member_mobile_phone']); ?></div><?php endif; ?></div>
      <div><div class="label">Informasi Transaksi</div><div class="value">Tanggal: <?php echo html_escape($dateTime($header['ordered_at'] ?? $header['created_at'] ?? null)); ?></div><div class="meta">Kasir: <?php echo html_escape((string)($header['cashier_employee_name'] ?? $header['cashier_username'] ?? '-')); ?></div><div class="meta">Layanan: <?php echo html_escape((string)($header['service_type'] ?? '-')); ?> · Terminal: <?php echo html_escape((string)($header['terminal_name'] ?? '-')); ?></div></div>
    </section>

    <section class="panel"><div class="panel-title">Rincian Pesanan</div><table><thead><tr><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Total</th></tr></thead><tbody>
      <?php foreach ($displayEntries as $entry): ?>
        <?php if ($entry['type'] === 'BUNDLE'): ?>
          <?php $bundleLabel = trim((string)($entry['bundle_name'] ?? '')) ?: (trim((string)($entry['bundle_code'] ?? '')) ?: 'Paket bundle'); ?>
          <tr class="bundle-row"><td colspan="4">Paket: <?php echo html_escape($bundleLabel); ?><div class="bundle-meta"><?php echo html_escape((string)($entry['bundle_code'] ?? '')); ?></div></td></tr>
          <?php $entryLines = (array)($entry['lines'] ?? []); $isBundleItem = true; ?>
        <?php else: ?>
          <?php $entryLines = [(array)($entry['line'] ?? [])]; $isBundleItem = false; ?>
        <?php endif; ?>
        <?php foreach ($entryLines as $line): ?>
          <?php $extras = (array)($line['extras'] ?? []); $lineAmount = (float)($line['net_amount'] ?? 0); foreach ($extras as $extra) { $lineAmount += (float)($extra['net_amount'] ?? 0); } ?>
          <tr>
            <td>
              <div class="product<?php echo $isBundleItem ? ' bundle-item' : ''; ?>"><?php echo html_escape((string)($line['product_name'] ?? '-')); ?></div>
              <div class="product-meta<?php echo $isBundleItem ? ' bundle-item' : ''; ?>"><?php echo html_escape((string)($line['product_code'] ?? '')); ?></div>
              <?php foreach ($extras as $extra): ?><div class="extra<?php echo $isBundleItem ? ' bundle-item' : ''; ?>">+ <?php echo html_escape((string)($extra['extra_name'] ?? '-')); ?> (<?php echo $qty($extra['qty'] ?? 0); ?> x <?php echo $money($extra['unit_price'] ?? 0); ?>)</div><?php endforeach; ?>
              <?php if (trim((string)($line['notes'] ?? '')) !== ''): ?><div class="note<?php echo $isBundleItem ? ' bundle-item' : ''; ?>">Catatan: <?php echo html_escape((string)$line['notes']); ?></div><?php endif; ?>
            </td>
            <td class="text-end"><?php echo $qty($line['qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $money($line['unit_price'] ?? 0); ?></td>
            <td class="text-end"><?php echo $money($lineAmount); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if (empty($displayEntries)): ?><tr><td colspan="4">Tidak ada item.</td></tr><?php endif; ?>
    </tbody></table></section>

    <table class="totals"><tbody><tr><td>Subtotal</td><td class="text-end"><?php echo $money($header['subtotal_amount'] ?? 0); ?></td></tr><?php if ($discountTotal > 0): ?><tr><td>Potongan</td><td class="text-end">- <?php echo $money($discountTotal); ?></td></tr><?php endif; ?><?php if ((float)($header['tax_amount'] ?? 0) > 0): ?><tr><td>Pajak</td><td class="text-end"><?php echo $money($header['tax_amount'] ?? 0); ?></td></tr><?php endif; ?><?php if ((float)($header['service_amount'] ?? 0) > 0): ?><tr><td>Service</td><td class="text-end"><?php echo $money($header['service_amount'] ?? 0); ?></td></tr><?php endif; ?><tr class="total"><td>Grand Total</td><td class="text-end"><?php echo $money($grandTotal); ?></td></tr></tbody></table>

    <?php if ($isReceipt): ?><section class="panel payments"><div class="panel-title">Pembayaran Diterima</div><div style="padding:0 11px;"><?php foreach ($paymentRows as $payment): ?><div class="payment-row"><div><div class="payment-method"><?php echo html_escape($payment['method_name']); ?></div><?php if ($payment['reference_no'] !== ''): ?><div class="payment-ref">Ref: <?php echo html_escape($payment['reference_no']); ?></div><?php endif; ?></div><strong><?php echo $money($payment['amount']); ?></strong></div><?php endforeach; ?><?php if (empty($paymentRows)): ?><div class="payment-row"><span>Belum ada pembayaran tercatat.</span></div><?php endif; ?><div class="payment-row"><strong>Total Dibayar</strong><strong><?php echo $money($paidTotal); ?></strong></div><?php if ((float)($header['change_total'] ?? 0) > 0): ?><div class="payment-row"><span>Kembalian</span><strong><?php echo $money($header['change_total']); ?></strong></div><?php endif; ?><?php if ($refundTotal > 0): ?><div class="payment-row"><span>Refund</span><strong>- <?php echo $money($refundTotal); ?></strong></div><?php endif; ?></div></section><?php else: ?><div class="notice">Sisa tagihan: <strong><?php echo $money($remaining); ?></strong>. Invoice ini merupakan rincian transaksi dan bukan faktur pajak.</div><?php endif; ?>
    <?php if ($isReceipt && $reviewUrl !== ''): ?>
      <section class="review-cta" aria-label="QR ulasan pelanggan">
        <div class="review-copy">
          <div class="review-kicker">Ulasan Pelanggan</div>
          <div class="review-title">Scan untuk kirim ulasan & daftar member</div>
          <div class="review-text">Arahkan kamera HP ke QR ini. Form ulasan akan terbuka tanpa perlu mengetik alamat link.</div>
          <div class="review-link"><?php echo html_escape($reviewUrl); ?></div>
        </div>
        <div class="review-qr" id="receipt-review-qr" data-qr-url="<?php echo html_escape($reviewUrl); ?>" role="img" aria-label="QR ulasan pelanggan"></div>
      </section>
    <?php endif; ?>

    <footer class="footer"><span>Dicetak <?php echo html_escape(date('d M Y, H:i')); ?></span><span><?php echo $documentLabel; ?> · <?php echo html_escape((string)($header['order_no'] ?? '-')); ?></span></footer>
  </main>
  <?php if ($isReceipt && $reviewUrl !== ''): ?>
    <script src="<?= base_url('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/pos-local-qr.js') ?>?v=20260825g"></script>
    <script>
    window.addEventListener('load', function () {
      var target = document.getElementById('receipt-review-qr');
      if (!target) return;
      if (window.PosLocalQr) {
        window.PosLocalQr.render(target, target.getAttribute('data-qr-url'), {size: 560, label: 'QR ulasan pelanggan'});
      } else {
        target.classList.add('qr-render-fallback');
        target.textContent = 'QR belum dapat dimuat.';
      }
    });
    </script>
  <?php endif; ?>
</body>
</html>
