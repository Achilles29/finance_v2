<?php
$order = is_array($order ?? null) ? $order : [];
$header = is_array($order['header'] ?? null) ? $order['header'] : [];
$lines = is_array($order['lines'] ?? null) ? $order['lines'] : [];
$displayLineEntries = [];
$bundleIndexes = [];
foreach ($lines as $line) {
	if (!is_array($line)) {
		continue;
	}
	$bundleId = max(0, (int)($line['bundle_id'] ?? 0));
	$lineType = strtoupper(trim((string)($line['line_type'] ?? 'PRODUCT')));
	if ($bundleId <= 0) {
		if ($lineType !== 'BUNDLE_HEADER') {
			$displayLineEntries[] = ['type' => 'LINE', 'line' => $line];
		}
		continue;
	}
	$bundleKey = 'B:' . $bundleId;
	if (!isset($bundleIndexes[$bundleKey])) {
		$bundleIndexes[$bundleKey] = count($displayLineEntries);
		$displayLineEntries[] = [
			'type' => 'BUNDLE',
			'bundle_name' => trim((string)($line['bundle_name'] ?? '')),
			'bundle_code' => trim((string)($line['bundle_code'] ?? '')),
			'header_line' => null,
			'lines' => [],
		];
	}
	$bundleIndex = $bundleIndexes[$bundleKey];
	if ($displayLineEntries[$bundleIndex]['bundle_name'] === '') {
		$displayLineEntries[$bundleIndex]['bundle_name'] = trim((string)($line['bundle_name'] ?? ''));
	}
	if ($displayLineEntries[$bundleIndex]['bundle_code'] === '') {
		$displayLineEntries[$bundleIndex]['bundle_code'] = trim((string)($line['bundle_code'] ?? ''));
	}
	if ($lineType === 'BUNDLE_HEADER') {
		$displayLineEntries[$bundleIndex]['header_line'] = $line;
	} else {
		$displayLineEntries[$bundleIndex]['lines'][] = $line;
	}
}
$displayLineEntries = array_values(array_filter($displayLineEntries, static function (array $entry): bool {
	return $entry['type'] !== 'BUNDLE' || !empty($entry['lines']);
}));
$payments = is_array($payments ?? null) ? $payments : [];
$refunds = is_array($refunds ?? null) ? $refunds : [];
$voids = is_array($voids ?? null) ? $voids : [];
$pointLedgers = is_array($point_ledgers ?? null) ? $point_ledgers : [];
$stampLedgers = is_array($stamp_ledgers ?? null) ? $stamp_ledgers : [];
$voucherRedemptions = is_array($voucher_redemptions ?? null) ? $voucher_redemptions : [];
$voucherIssues = is_array($voucher_issues ?? null) ? $voucher_issues : [];
$marginAudit = is_array($margin_audit ?? null) ? $margin_audit : [];
$marginLineMap = is_array($marginAudit['lines'] ?? null) ? $marginAudit['lines'] : [];
$paymentMethodOptions = is_array($payment_method_options ?? null) ? $payment_method_options : [];
$canEditPaymentMethod = !empty($can_edit_payment_method);
$customerDisplayName = trim((string)($header['customer_display_name'] ?? '')) !== ''
	? (string)($header['customer_display_name'] ?? '')
	: ((trim((string)($header['customer_name'] ?? '')) !== '' ? (string)($header['customer_name'] ?? '') : (string)($header['member_name'] ?? 'Walk in')));
$money = static function ($value): string {
	return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$qty = static function ($value, int $decimals = 2): string {
	return number_format((float)$value, $decimals, ',', '.');
};
$dt = static function ($value): string {
	$time = $value ? strtotime((string)$value) : false;
	return $time ? date('d M Y H:i', $time) : '-';
};
$statusTone = static function ($status): string {
	$value = strtoupper(trim((string)$status));
	if (in_array($value, ['PAID', 'READY', 'SERVED'], true)) {
		return 'success';
	}
	if (in_array($value, ['PAID_PARTIAL', 'CONFIRMED', 'IN_KITCHEN'], true)) {
		return 'warning';
	}
	if (in_array($value, ['REFUND_PARTIAL', 'REFUND_FULL', 'REFUNDED_FULL'], true)) {
		return 'info';
	}
	if (in_array($value, ['VOID'], true)) {
		return 'danger';
	}
	return 'secondary';
};
$lineFinancials = static function (array $line) use ($marginLineMap, $marginAudit): array {
	$lineId = (int)($line['id'] ?? 0);
	$lineAudit = is_array($marginLineMap[$lineId] ?? null) ? $marginLineMap[$lineId] : [];
	$lineExtras = is_array($line['extras'] ?? null) ? $line['extras'] : [];
	$extraSales = 0.0;
	foreach ($lineExtras as $extra) {
		$extraSales += (float)($extra['net_amount'] ?? 0);
	}
	$lineSalesCurrent = (float)($line['net_amount'] ?? 0) + $extraSales;
	$lineSalesBase = (float)($lineAudit['gross_sales'] ?? $lineSalesCurrent);
	$lineDiscount = abs((float)($marginAudit['gross_sales'] ?? 0)) > 0.00001
		? $lineSalesBase / (float)$marginAudit['gross_sales'] * (float)($marginAudit['sales_discount_amount'] ?? 0)
		: 0.0;
	$lineRefund = (float)($lineAudit['refund_amount'] ?? 0);
	$lineHppSale = (float)($lineAudit['hpp_sale_amount'] ?? 0);
	$lineHppRefund = (float)($lineAudit['hpp_refund_reversed_amount'] ?? 0);
	$lineHppCorrection = (float)($lineAudit['hpp_deficit_correction_amount'] ?? 0);

	return [
		'line_extras' => $lineExtras,
		'sales_base' => $lineSalesBase,
		'discount' => $lineDiscount,
		'refund' => $lineRefund,
		'net_sales' => $lineSalesBase - $lineDiscount - $lineRefund,
		'hpp_sale' => $lineHppSale,
		'hpp_refund' => $lineHppRefund,
		'hpp_correction' => $lineHppCorrection,
		'hpp_final' => (float)($lineAudit['hpp_final_amount'] ?? ($lineHppSale - $lineHppRefund + $lineHppCorrection)),
	];
};
$withoutBundleMarker = static function ($notes): string {
	$keptLines = [];
	foreach (preg_split('/\r\n|\r|\n/', trim((string)$notes)) ?: [] as $noteLine) {
		$noteLine = trim((string)$noteLine);
		if ($noteLine === '' || preg_match('/^\[BUNDLE\](?:\s|$)/i', $noteLine)) {
			continue;
		}
		$keptLines[] = $noteLine;
	}

	return implode("\n", $keptLines);
};
$accountText = static function (array $row): string {
	$parts = [];
	if (trim((string)($row['company_account_name'] ?? '')) !== '') {
		$parts[] = (string)($row['company_account_name'] ?? '');
	}
	if (trim((string)($row['company_bank_name'] ?? '')) !== '') {
		$parts[] = (string)($row['company_bank_name'] ?? '');
	}
	if (trim((string)($row['account_no'] ?? '')) !== '') {
		$parts[] = (string)($row['account_no'] ?? '');
	}
	if (trim((string)($row['company_account_code'] ?? '')) !== '') {
		$parts[] = (string)($row['company_account_code'] ?? '');
	}
	return $parts ? implode(' • ', $parts) : 'Rekening belum dihubungkan';
};
$discountBundle = (float)($header['discount_amount'] ?? 0)
	+ (float)($header['promo_amount'] ?? 0)
	+ (float)($header['voucher_amount'] ?? 0)
	+ (float)($header['point_redeem_amount'] ?? 0)
	+ (float)($header['compliment_amount'] ?? 0);
$refundTotal = array_reduce($refunds, static function ($carry, array $row): float {
	return $carry + (float)($row['refund_amount'] ?? 0);
}, 0.0);
$voidTotal = array_reduce($voids, static function ($carry, array $row): float {
	return $carry + (float)($row['amount_void'] ?? 0);
}, 0.0);
$paymentDocCount = count($payments);
$paymentLineCount = array_reduce($payments, static function ($carry, array $payment): int {
	return $carry + count((array)($payment['lines'] ?? []));
}, 0);
$remainingAmount = max(0, (float)($header['grand_total'] ?? 0) - (float)($header['paid_total'] ?? 0));
$this->load->view('pos/_report_styles');
?>

<style>
	.pos-tx-page,
	.pos-tx-page .pos-report-shell,
	.pos-tx-page .pos-report-section {
		min-width: 0;
		max-width: 100%;
	}
	.pos-tx-page .pos-report-kv {
		grid-template-columns: auto minmax(0, 1fr);
	}
	.pos-tx-page .pos-report-kv > span {
		overflow-wrap: anywhere;
	}
	.pos-tx-page .pos-report-hero,
	.pos-tx-page .pos-report-card,
	.pos-tx-mini-card,
	.pos-tx-doc {
		overflow-wrap: anywhere;
	}
	.pos-tx-header-actions {
		display: flex;
		flex-wrap: wrap;
		gap: .5rem;
		align-items: center;
	}
	.pos-tx-header-actions .btn {
		min-height: 44px !important;
		white-space: normal;
	}
	.pos-tx-grid {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: .8rem;
	}
	.pos-tx-mini-card {
		border: 1px solid #efddd1;
		border-radius: 18px;
		background: linear-gradient(180deg, #fffefd 0%, #fff6ef 100%);
		padding: .85rem .95rem;
	}
	.pos-tx-mini-label {
		color: #8d7366;
		text-transform: uppercase;
		font-size: .72rem;
		letter-spacing: .07em;
		font-weight: 800;
	}
	.pos-tx-mini-value {
		font-size: 1.08rem;
		font-weight: 800;
		color: #3b261c;
		margin-top: .35rem;
	}
	.pos-tx-mini-note {
		color: #8b7469;
		font-size: .78rem;
		margin-top: .25rem;
	}
	.pos-tx-breakdown {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: .7rem;
	}
	.pos-tx-breakdown-item {
		border: 1px solid #efddd1;
		border-radius: 16px;
		background: rgba(255, 250, 245, .95);
		padding: .8rem .9rem;
	}
	.pos-tx-breakdown-head {
		display: flex;
		justify-content: space-between;
		gap: .75rem;
		align-items: center;
	}
	.pos-tx-breakdown-label {
		font-size: .78rem;
		font-weight: 800;
		color: #4c3429;
	}
	.pos-tx-breakdown-value {
		font-size: .95rem;
		font-weight: 800;
		color: #3b261c;
		white-space: nowrap;
	}
	.pos-tx-breakdown-note {
		font-size: .78rem;
		color: #8a7063;
		margin-top: .28rem;
	}
	.pos-tx-stack {
		display: grid;
		gap: .8rem;
	}
	.pos-tx-doc {
		border: 1px solid #efddd1;
		border-radius: 18px;
		background: #fff;
		padding: .9rem 1rem;
	}
	.pos-tx-doc-head {
		display: flex;
		justify-content: space-between;
		gap: .8rem;
		align-items: flex-start;
		margin-bottom: .65rem;
	}
	.pos-tx-doc-title {
		font-size: .95rem;
		font-weight: 800;
		color: #3b261c;
	}
	.pos-tx-doc-amount {
		font-size: 1rem;
		font-weight: 800;
		color: #1e7a45;
		white-space: nowrap;
	}
	.pos-tx-doc-meta {
		font-size: .8rem;
		color: #8a7063;
	}
	.pos-tx-line-list {
		display: grid;
		gap: .6rem;
	}
	.pos-tx-line-item {
		border: 1px solid #f0e3db;
		border-radius: 16px;
		background: #fffaf6;
		padding: .75rem .85rem;
	}
	.pos-tx-bundle-row > td {
		background: #fff6e9 !important;
		border-top: 1px solid #eccbab;
		border-bottom: 1px solid #eccbab;
	}
	.pos-tx-bundle-kind {
		display: inline-flex;
		align-items: center;
		padding: .22rem .48rem;
		border-radius: 999px;
		background: #f4d7ae;
		color: #754114;
		font-size: .72rem;
		font-weight: 800;
		text-transform: uppercase;
	}
	.pos-tx-bundle-child > td {
		background: #fffdfb;
	}
	.pos-tx-bundle-child > td:nth-child(2) {
		position: relative;
		padding-left: 2.35rem !important;
	}
	.pos-tx-bundle-child > td:nth-child(2)::before {
		content: '';
		position: absolute;
		left: 1.15rem;
		top: -.1rem;
		bottom: -.1rem;
		border-left: 2px solid #f0cbb5;
	}
	.pos-tx-bundle-child-name::before {
		content: '';
		display: inline-block;
		width: .55rem;
		height: .55rem;
		margin-right: .45rem;
		border-left: 2px solid #d98b60;
		border-bottom: 2px solid #d98b60;
		vertical-align: .18rem;
	}
	.pos-tx-line-head {
		display: flex;
		justify-content: space-between;
		gap: .75rem;
		align-items: flex-start;
	}
	.pos-tx-line-method {
		font-weight: 800;
		color: #3b261c;
	}
	.pos-tx-line-account,
	.pos-tx-line-meta {
		font-size: .79rem;
		color: #8a7063;
	}
	.pos-tx-line-amount {
		font-size: .95rem;
		font-weight: 800;
		color: #3b261c;
		white-space: nowrap;
	}
	.pos-tx-line-actions {
		display: flex;
		justify-content: flex-end;
		margin-top: .55rem;
	}
	.pos-tx-action-btn {
		width: 2.1rem;
		height: 2.1rem;
		border-radius: 999px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}
	.pos-tx-section-title {
		font-size: 1rem;
		font-weight: 800;
		color: #3b261c;
	}
	.pos-tx-flag-list {
		display: flex;
		flex-wrap: wrap;
		gap: .5rem;
	}
	.pos-tx-flag {
		display: inline-flex;
		align-items: center;
		gap: .4rem;
		padding: .45rem .8rem;
		border-radius: 999px;
		border: 1px solid #efddd1;
		background: rgba(255, 250, 245, .95);
		font-size: .8rem;
		color: #6f5649;
	}
	.pos-tx-flag strong {
		color: #3f2a1f;
	}
	.pos-tx-loyalty-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: .8rem;
	}
	.pos-tx-loyalty-item {
		border: 1px solid #efddd1;
		border-radius: 16px;
		background: #fffaf6;
		padding: .8rem .9rem;
	}
	.pos-tx-loyalty-name {
		font-size: .88rem;
		font-weight: 800;
		color: #3b261c;
	}
	.pos-tx-loyalty-meta {
		font-size: .79rem;
		color: #8a7063;
		margin-top: .15rem;
	}
	.pos-tx-modal-note {
		border: 1px solid #efddd1;
		border-radius: 14px;
		background: #fffaf6;
		padding: .8rem .9rem;
		font-size: .82rem;
		color: #7b6254;
	}
	@media (max-width: 991.98px) {
		.pos-tx-grid,
		.pos-tx-loyalty-grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}
	@media (max-width: 575.98px) {
		.pos-tx-page .pos-report-shell {
			padding: .5rem;
			border-radius: 18px;
		}
		.pos-tx-page .pos-report-hero {
			padding: 1rem;
		}
		.pos-tx-page .pos-report-title {
			font-size: 1.3rem;
		}
		.pos-tx-header-actions {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			width: 100%;
		}
		.pos-tx-header-actions .btn {
			padding: .6rem .4rem;
			font-size: .82rem;
		}
		.pos-tx-page .pos-report-kv {
			grid-template-columns: minmax(0, 1fr);
		}
		.pos-tx-page .pos-report-kv > span {
			margin-bottom: .4rem;
			overflow-wrap: anywhere;
		}
		.pos-tx-page .pos-report-chip {
			max-width: 100%;
			flex-wrap: wrap;
			overflow-wrap: anywhere;
		}
		.pos-tx-doc-head,
		.pos-tx-line-head,
		.pos-tx-breakdown-head {
			flex-wrap: wrap;
		}
		.pos-tx-doc-head > *,
		.pos-tx-line-head > * {
			min-width: 0;
			max-width: 100%;
			overflow-wrap: anywhere;
		}
		.pos-tx-grid,
		.pos-tx-breakdown,
		.pos-tx-loyalty-grid {
			grid-template-columns: 1fr;
		}
	}
</style>

<div class="container-xxl py-3 pos-tx-page">
	<div class="pos-report-shell">
		<div class="pos-report-hero mb-3">
			<div class="d-flex flex-wrap justify-content-between gap-3">
				<div>
					<div class="pos-report-title">Detail Transaksi POS</div>
					<p class="pos-report-copy mb-0">Lihat nilai tagihan, penjualan bersih, HPP saat transaksi dibuat, pembalikan HPP refund, dan koreksi biaya setelah defisit stok terselesaikan dalam satu audit transaksi.</p>
				</div>
				<div class="pos-tx-header-actions" role="group" aria-label="Dokumen dan navigasi transaksi">
					<a href="<?php echo site_url('pos/reports/sales/print/' . (int)($header['id'] ?? 0) . '/invoice'); ?>" target="_blank" rel="noopener" class="btn btn-primary"><i class="ri-file-text-line me-1"></i>Invoice</a>
					<a href="<?php echo site_url('pos/reports/sales/print/' . (int)($header['id'] ?? 0) . '/receipt'); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="ri-bill-line me-1"></i>Kwitansi</a>
					<a href="<?php echo site_url('pos/reports/sales'); ?>" class="btn btn-outline-secondary">Kembali ke Penjualan</a>
					<a href="<?php echo site_url('pos/reports/sales-detail'); ?>" class="btn btn-outline-dark">Ke Penjualan Produk</a>
				</div>
			</div>
		</div>

		<?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'sales']); ?>

		<div id="tx_flash" class="alert d-none mb-3" role="alert"></div>

		<div class="pos-report-chip-row mb-3">
			<div class="pos-report-chip"><strong>Order</strong><?php echo html_escape((string)($header['order_no'] ?? '-')); ?></div>
			<div class="pos-report-chip"><strong>Status</strong><span class="pos-report-badge <?php echo html_escape($statusTone($header['status'] ?? '')); ?>"><?php echo html_escape((string)($header['status'] ?? '-')); ?></span></div>
			<div class="pos-report-chip"><strong>Customer</strong><?php echo html_escape($customerDisplayName); ?></div>
			<div class="pos-report-chip"><strong>Member</strong><?php echo html_escape((string)($header['member_no'] ?? '-')); ?></div>
			<div class="pos-report-chip"><strong>Outlet</strong><?php echo html_escape((string)($header['outlet_name'] ?? '-')); ?></div>
			<div class="pos-report-chip"><strong>Terminal</strong><?php echo html_escape((string)($header['terminal_name'] ?? 'Tanpa Terminal')); ?></div>
			<div class="pos-report-chip"><strong>Service</strong><?php echo html_escape((string)($header['service_type'] ?? '-')); ?></div>
			<div class="pos-report-chip"><strong>Guest</strong><?php echo (int)($header['guest_count'] ?? 1); ?></div>
			<div class="pos-report-chip"><strong>Channel</strong><?php echo html_escape((string)($header['sales_channel_name'] ?? $header['sales_channel_code'] ?? '-')); ?></div>
			<div class="pos-report-chip"><strong>Stock</strong><?php echo html_escape((string)($header['stock_commit_status'] ?? '-')); ?></div>
		</div>

		<div class="pos-report-section p-3 mb-3">
			<div class="mb-3">
				<div class="pos-tx-section-title">Audit Penjualan & HPP</div>
				<div class="pos-report-meta">Penjualan bersih tagihan sudah memperhitungkan refund. Pajak dan service tetap terlihat pada nilai tagihan; laporan produk dan extra menampilkan nilai item tanpa komponen tersebut.</div>
			</div>
			<div class="pos-tx-breakdown">
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Penjualan Kotor Item</div><div class="pos-tx-breakdown-value"><?php echo $money($marginAudit['gross_sales'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Subtotal item dan extra sebelum potongan.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Potongan Penjualan</div><div class="pos-tx-breakdown-value text-danger"><?php echo $money($marginAudit['sales_discount_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Diskon, promo, voucher, poin, dan compliment.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Nilai Tagihan Sebelum Refund</div><div class="pos-tx-breakdown-value"><?php echo $money($marginAudit['sales_after_discount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Termasuk pajak, service, dan pembulatan bila ada.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Refund Posted</div><div class="pos-tx-breakdown-value text-warning"><?php echo $money($marginAudit['refund_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Hanya refund yang sudah sah diposting.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Penjualan Bersih Tagihan</div><div class="pos-tx-breakdown-value text-success"><?php echo $money($marginAudit['net_sales'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Nilai tagihan final dikurangi refund.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">HPP Saat Jual</div><div class="pos-tx-breakdown-value"><?php echo $money($marginAudit['hpp_sale_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Snapshot biaya produk dan extra ketika order dibuat.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">HPP Refund Dibalik</div><div class="pos-tx-breakdown-value text-success"><?php echo $money($marginAudit['hpp_refund_reversed_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Biaya dari barang yang sudah direfund.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Koreksi HPP Defisit</div><div class="pos-tx-breakdown-value"><?php echo $money($marginAudit['hpp_deficit_correction_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Muncul saat biaya barang defisit sudah terselesaikan.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">HPP Terkini</div><div class="pos-tx-breakdown-value"><?php echo $money($marginAudit['hpp_final_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">HPP saat jual dikurangi refund dan ditambah koreksi defisit.</div></div>
				<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Laba Kotor</div><div class="pos-tx-breakdown-value <?php echo (float)($marginAudit['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($marginAudit['gross_profit'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Margin <?php echo number_format((float)($marginAudit['margin_percent'] ?? 0), 2, ',', '.'); ?>% dari penjualan bersih tagihan.</div></div>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-lg-5">
				<div class="pos-report-section p-3 h-100">
					<div class="d-flex justify-content-between align-items-start gap-2 mb-3">
						<div class="pos-tx-section-title">Identitas Order</div>
						<div class="pos-report-meta"><?php echo html_escape((string)($header['order_scope'] ?? '-')); ?></div>
					</div>
					<div class="pos-report-kv">
						<strong>Kasir</strong>
						<span><?php echo html_escape((string)($header['cashier_employee_name'] ?? $header['cashier_username'] ?? '-')); ?></span>
						<strong>Ordered At</strong>
						<span><?php echo html_escape($dt($header['ordered_at'] ?? null)); ?></span>
						<strong>Paid At</strong>
						<span><?php echo html_escape($dt($header['paid_at'] ?? null)); ?></span>
						<strong>Meja</strong>
						<span><?php echo html_escape((string)($header['table_no'] ?? '-')); ?></span>
						<strong>No. HP Member</strong>
						<span><?php echo html_escape((string)($header['member_mobile_phone'] ?? '-')); ?></span>
						<strong>Catatan Order</strong>
						<span><?php echo html_escape((string)($header['notes'] ?? '-')); ?></span>
					</div>
				</div>
			</div>
			<div class="col-lg-7">
				<div class="pos-tx-grid">
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Subtotal</div><div class="pos-tx-mini-value"><?php echo $money($header['subtotal_amount'] ?? 0); ?></div><div class="pos-tx-mini-note">Nilai item sebelum potongan.</div></div>
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Total Potongan</div><div class="pos-tx-mini-value"><?php echo $money($discountBundle); ?></div><div class="pos-tx-mini-note">Diskon, promo, voucher, poin, compliment.</div></div>
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Pajak + Service</div><div class="pos-tx-mini-value"><?php echo $money((float)($header['tax_amount'] ?? 0) + (float)($header['service_amount'] ?? 0)); ?></div><div class="pos-tx-mini-note">Komponen non-item pada transaksi.</div></div>
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Grand Total</div><div class="pos-tx-mini-value"><?php echo $money($header['grand_total'] ?? 0); ?></div><div class="pos-tx-mini-note">Nilai akhir order.</div></div>
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Terbayar</div><div class="pos-tx-mini-value text-success"><?php echo $money($header['paid_total'] ?? 0); ?></div><div class="pos-tx-mini-note"><?php echo number_format($paymentDocCount); ?> dokumen, <?php echo number_format($paymentLineCount); ?> line pembayaran.</div></div>
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Kembalian</div><div class="pos-tx-mini-value"><?php echo $money($header['change_total'] ?? 0); ?></div><div class="pos-tx-mini-note">Nilai cash back ke customer.</div></div>
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Refund</div><div class="pos-tx-mini-value text-warning"><?php echo $money($refundTotal); ?></div><div class="pos-tx-mini-note">Tetap dicatat terpisah dari pembayaran masuk.</div></div>
					<div class="pos-tx-mini-card"><div class="pos-tx-mini-label">Void</div><div class="pos-tx-mini-value text-danger"><?php echo $money($voidTotal); ?></div><div class="pos-tx-mini-note">Pembatalan setelah order dibuat.</div></div>
				</div>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-lg-6">
				<div class="pos-report-section p-3 h-100">
					<div class="mb-3"><div class="pos-tx-section-title">Komponen Nilai Transaksi</div><div class="pos-report-meta">Rincian nominal agar voucher, poin, promo, compliment, pajak, dan service terlihat terpisah.</div></div>
					<div class="pos-tx-breakdown">
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Diskon Manual</div><div class="pos-tx-breakdown-value"><?php echo $money($header['discount_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Potongan langsung dari kasir.</div></div>
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Promo</div><div class="pos-tx-breakdown-value"><?php echo $money($header['promo_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Promo transaksi aktif saat pembayaran.</div></div>
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Voucher Dipakai</div><div class="pos-tx-breakdown-value"><?php echo $money($header['voucher_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Nominal voucher yang mengurangi tagihan.</div></div>
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Poin Dipakai</div><div class="pos-tx-breakdown-value"><?php echo $money($header['point_redeem_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Penukaran poin member ke transaksi ini.</div></div>
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Compliment</div><div class="pos-tx-breakdown-value"><?php echo $money($header['compliment_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Potongan goodwill / complimentary.</div></div>
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Pajak</div><div class="pos-tx-breakdown-value"><?php echo $money($header['tax_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Nilai tax pada order final.</div></div>
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Service</div><div class="pos-tx-breakdown-value"><?php echo $money($header['service_amount'] ?? 0); ?></div></div><div class="pos-tx-breakdown-note">Biaya layanan terhitung di transaksi.</div></div>
						<div class="pos-tx-breakdown-item"><div class="pos-tx-breakdown-head"><div class="pos-tx-breakdown-label">Sisa Tagihan</div><div class="pos-tx-breakdown-value"><?php echo $money($remainingAmount); ?></div></div><div class="pos-tx-breakdown-note">Grand total dikurangi nilai yang sudah masuk.</div></div>
					</div>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="pos-report-section p-3 h-100">
					<div class="mb-3"><div class="pos-tx-section-title">Loyalty & Voucher</div><div class="pos-report-meta">Jejak voucher yang dipakai/terbit dan poin atau stamp yang tercatat dari transaksi ini.</div></div>
					<div class="pos-tx-loyalty-grid">
						<div class="pos-tx-loyalty-item">
							<div class="pos-tx-mini-label">Voucher Dipakai</div>
							<div class="pos-tx-mini-value"><?php echo $money($header['voucher_amount'] ?? 0); ?></div>
							<div class="pos-report-inline-list mt-2">
								<?php if (empty($voucherRedemptions)): ?><div class="pos-report-empty py-0">Tidak ada voucher redemption pada order ini.</div><?php else: foreach ($voucherRedemptions as $row): ?><div><div class="pos-tx-loyalty-name"><?php echo html_escape((string)($row['voucher_code'] ?? $row['voucher_issue_no'] ?? '-')); ?></div><div class="pos-tx-loyalty-meta"><?php echo html_escape((string)($row['campaign_name'] ?? '-')); ?> • <?php echo $money($row['redeem_amount'] ?? 0); ?> • <?php echo html_escape($dt($row['redeemed_at'] ?? null)); ?></div></div><?php endforeach; endif; ?>
							</div>
						</div>
						<div class="pos-tx-loyalty-item">
							<div class="pos-tx-mini-label">Poin & Stamp Tercatat</div>
							<div class="pos-tx-mini-value"><?php echo $qty(array_reduce($pointLedgers, static function ($carry, array $row): float { return $carry + (float)($row['points_in'] ?? 0) - (float)($row['points_out'] ?? 0); }, 0.0), 0); ?> pts / <?php echo $qty(array_reduce($stampLedgers, static function ($carry, array $row): float { return $carry + (float)($row['stamp_in'] ?? 0) - (float)($row['stamp_out'] ?? 0); }, 0.0), 0); ?> stp</div>
							<div class="pos-report-inline-list mt-2">
								<?php if (empty($pointLedgers) && empty($stampLedgers)): ?><div class="pos-report-empty py-0">Belum ada ledger poin atau stamp untuk order ini.</div><?php else: foreach ($pointLedgers as $row): ?><div><div class="pos-tx-loyalty-name">Poin <?php echo html_escape((string)($row['ledger_type'] ?? '-')); ?> • <?php echo $qty((float)($row['points_in'] ?? 0) - (float)($row['points_out'] ?? 0), 0); ?></div><div class="pos-tx-loyalty-meta"><?php echo html_escape((string)($row['rule_name'] ?? '-')); ?> • <?php echo html_escape($dt($row['created_at'] ?? null)); ?></div></div><?php endforeach; foreach ($stampLedgers as $row): ?><div><div class="pos-tx-loyalty-name">Stamp <?php echo html_escape((string)($row['ledger_type'] ?? '-')); ?> • <?php echo $qty((float)($row['stamp_in'] ?? 0) - (float)($row['stamp_out'] ?? 0), 0); ?></div><div class="pos-tx-loyalty-meta"><?php echo html_escape((string)($row['campaign_name'] ?? '-')); ?> • <?php echo html_escape($dt($row['created_at'] ?? null)); ?></div></div><?php endforeach; endif; ?>
							</div>
						</div>
						<div class="pos-tx-loyalty-item">
							<div class="pos-tx-mini-label">Voucher Terbit Dari Pembayaran</div>
							<div class="pos-tx-mini-value"><?php echo number_format(count($voucherIssues)); ?> voucher</div>
							<div class="pos-report-inline-list mt-2">
								<?php if (empty($voucherIssues)): ?><div class="pos-report-empty py-0">Tidak ada voucher baru yang diterbitkan.</div><?php else: foreach ($voucherIssues as $row): ?><div><div class="pos-tx-loyalty-name"><?php echo html_escape((string)($row['voucher_code'] ?? '-')); ?></div><div class="pos-tx-loyalty-meta"><?php echo html_escape((string)($row['campaign_name'] ?? '-')); ?> • <?php echo html_escape($dt($row['issued_at'] ?? null)); ?> • <?php echo html_escape((string)($row['voucher_status'] ?? '-')); ?></div></div><?php endforeach; endif; ?>
							</div>
						</div>
						<div class="pos-tx-loyalty-item">
							<div class="pos-tx-mini-label">Tag Audit Cepat</div>
							<div class="pos-tx-flag-list mt-2"><div class="pos-tx-flag"><strong>Voucher</strong><?php echo $money($header['voucher_amount'] ?? 0); ?></div><div class="pos-tx-flag"><strong>Poin Dipakai</strong><?php echo $money($header['point_redeem_amount'] ?? 0); ?></div><div class="pos-tx-flag"><strong>Refund</strong><?php echo $money($refundTotal); ?></div><div class="pos-tx-flag"><strong>Void</strong><?php echo $money($voidTotal); ?></div></div>
							<div class="pos-tx-mini-note mt-3">Cocok untuk audit cepat sebelum tracing lebih jauh ke detail pembayaran atau histori refund.</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="pos-report-section p-3 mb-3">
			<div class="mb-3">
				<div class="pos-tx-section-title">Line Produk & HPP</div>
				<div class="pos-report-meta">Setiap baris memperlihatkan penjualan bersih item setelah alokasi potongan transaksi dan refund, beserta perubahan HPP yang benar-benar terkait dengan baris tersebut.</div>
			</div>
			<div class="pos-report-table-wrap">
				<div class="table-responsive pos-report-table-scroll">
					<table class="table table-sm align-middle mb-0 pos-report-table">
						<thead>
							<tr>
								<th>Line</th>
								<th>Produk / Extra</th>
								<th class="text-end">Qty</th>
								<th class="text-end">Penjualan Bersih Item</th>
								<th class="text-end">HPP Saat Jual</th>
								<th class="text-end">HPP Refund</th>
								<th class="text-end">Koreksi Defisit</th>
								<th class="text-end">HPP Terkini</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($lines)): ?>
								<tr><td colspan="9" class="text-center pos-report-empty">Belum ada line order.</td></tr>
							<?php else: ?>
								<?php foreach ($displayLineEntries as $entry): ?>
									<?php
									$isBundleEntry = $entry['type'] === 'BUNDLE';
									$entryLines = $isBundleEntry
										? (array)($entry['lines'] ?? [])
										: [(array)($entry['line'] ?? [])];
									?>
									<?php if ($isBundleEntry): ?>
										<?php
										$bundleLabel = trim((string)($entry['bundle_name'] ?? ''))
											?: (trim((string)($entry['bundle_code'] ?? '')) ?: 'Paket bundle');
										$bundleHeader = is_array($entry['header_line'] ?? null) ? $entry['header_line'] : [];
										$bundleSummary = [
											'sales_base' => 0.0,
											'discount' => 0.0,
											'refund' => 0.0,
											'net_sales' => 0.0,
											'hpp_sale' => 0.0,
											'hpp_refund' => 0.0,
											'hpp_correction' => 0.0,
											'hpp_final' => 0.0,
										];
										$bundleStatuses = [];
										foreach ($entryLines as $bundleLine) {
											$bundleLineFinancials = $lineFinancials($bundleLine);
											foreach (array_keys($bundleSummary) as $metric) {
												$bundleSummary[$metric] += (float)($bundleLineFinancials[$metric] ?? 0);
											}
											$bundleLineStatus = strtoupper(trim((string)($bundleLine['line_status'] ?? '')));
											$bundleStatuses[$bundleLineStatus !== '' ? $bundleLineStatus : 'UNKNOWN'] = true;
										}
										$bundleQty = (float)($bundleHeader['qty'] ?? 0);
										if ($bundleQty <= 0) {
											foreach ($entryLines as $bundleLine) {
												$componentQty = (float)($bundleLine['bundle_component_qty'] ?? 0);
												if ($componentQty > 0 && (float)($bundleLine['qty'] ?? 0) > 0) {
													$bundleQty = (float)$bundleLine['qty'] / $componentQty;
													break;
												}
											}
										}
										if ($bundleQty <= 0 && !empty($entryLines)) {
											$bundleQty = (float)($entryLines[0]['qty'] ?? 0);
										}
										$bundleLineNo = (int)($bundleHeader['line_no'] ?? ($entryLines[0]['line_no'] ?? 0));
										$bundleStatus = count($bundleStatuses) === 1 ? (string)array_key_first($bundleStatuses) : 'CAMPURAN';
										?>
										<tr class="pos-tx-bundle-row">
											<td>
												<span class="pos-tx-bundle-kind">Paket</span>
												<?php if ($bundleLineNo > 0): ?><div class="pos-report-meta mt-1">Line <?php echo $bundleLineNo; ?></div><?php endif; ?>
											</td>
											<td>
												<div class="fw-semibold text-uppercase">Paket: <?php echo html_escape($bundleLabel); ?></div>
												<div class="pos-report-meta"><?php echo html_escape((string)($entry['bundle_code'] ?? '')); ?> | <?php echo number_format(count($entryLines)); ?> rincian produk</div>
											</td>
											<td class="text-end"><?php echo $bundleQty > 0 ? $qty($bundleQty, 0) : '-'; ?></td>
											<td class="text-end">
												<div class="fw-semibold"><?php echo $money($bundleSummary['net_sales']); ?></div>
												<div class="pos-report-meta">Dasar <?php echo $money($bundleSummary['sales_base']); ?> | Potongan <?php echo $money($bundleSummary['discount']); ?> | Refund <?php echo $money($bundleSummary['refund']); ?></div>
											</td>
											<td class="text-end"><?php echo $money($bundleSummary['hpp_sale']); ?></td>
											<td class="text-end text-success"><?php echo $money($bundleSummary['hpp_refund']); ?></td>
											<td class="text-end <?php echo $bundleSummary['hpp_correction'] < 0 ? 'text-success' : ''; ?>"><?php echo $money($bundleSummary['hpp_correction']); ?></td>
											<td class="text-end fw-semibold"><?php echo $money($bundleSummary['hpp_final']); ?></td>
											<td><span class="pos-report-badge <?php echo html_escape($statusTone($bundleStatus)); ?>"><?php echo html_escape($bundleStatus); ?></span></td>
										</tr>
									<?php endif; ?>
									<?php foreach ($entryLines as $line): ?>
									<?php
									$lineFinancial = $lineFinancials($line);
									$lineExtras = (array)($lineFinancial['line_extras'] ?? []);
									$lineSalesBase = (float)($lineFinancial['sales_base'] ?? 0);
									$lineDiscount = (float)($lineFinancial['discount'] ?? 0);
									$lineRefund = (float)($lineFinancial['refund'] ?? 0);
									$lineNetSales = (float)($lineFinancial['net_sales'] ?? 0);
									$lineHppSale = (float)($lineFinancial['hpp_sale'] ?? 0);
									$lineHppRefund = (float)($lineFinancial['hpp_refund'] ?? 0);
									$lineHppCorrection = (float)($lineFinancial['hpp_correction'] ?? 0);
									$lineHppFinal = (float)($lineFinancial['hpp_final'] ?? 0);
									?>
									<tr<?php echo $isBundleEntry ? ' class="pos-tx-bundle-child"' : ''; ?>>
										<td><?php echo $isBundleEntry ? '-' : (int)($line['line_no'] ?? 0); ?></td>
										<td>
											<div class="<?php echo $isBundleEntry ? 'pos-tx-bundle-child-name fw-semibold' : 'fw-semibold'; ?>"><?php echo html_escape((string)($line['product_name'] ?? $line['bundle_name'] ?? '-')); ?></div>
											<div class="pos-report-meta"><?php echo html_escape((string)($line['product_code'] ?? $line['bundle_code'] ?? '-')); ?> | <?php echo html_escape((string)($line['uom_code'] ?? '-')); ?></div>
											<?php if (!empty($lineExtras)): ?>
												<div class="mt-2 pos-report-inline-list">
													<?php foreach ($lineExtras as $extra): ?>
														<?php $extraHpp = (float)($extra['qty'] ?? 0) * (float)($extra['cost_amount_snapshot'] ?? 0); ?>
														<span class="muted">+ <?php echo html_escape((string)($extra['extra_name'] ?? '-')); ?> | <?php echo $qty($extra['qty'] ?? 0, 0); ?> x <?php echo $money($extra['unit_price'] ?? 0); ?> | HPP <?php echo $money($extraHpp); ?></span>
													<?php endforeach; ?>
												</div>
											<?php endif; ?>
											<?php $lineNotes = $withoutBundleMarker($line['notes'] ?? ''); ?>
											<?php if ($lineNotes !== ''): ?><div class="pos-report-meta mt-1"><?php echo html_escape($lineNotes); ?></div><?php endif; ?>
										</td>
										<td class="text-end"><?php echo $qty($line['qty'] ?? 0, 0); ?></td>
										<td class="text-end">
											<div class="fw-semibold"><?php echo $money($lineNetSales); ?></div>
											<div class="pos-report-meta">Dasar <?php echo $money($lineSalesBase); ?> | Potongan <?php echo $money($lineDiscount); ?> | Refund <?php echo $money($lineRefund); ?></div>
										</td>
										<td class="text-end"><?php echo $money($lineHppSale); ?></td>
										<td class="text-end text-success"><?php echo $money($lineHppRefund); ?></td>
										<td class="text-end <?php echo $lineHppCorrection < 0 ? 'text-success' : ''; ?>"><?php echo $money($lineHppCorrection); ?></td>
										<td class="text-end fw-semibold"><?php echo $money($lineHppFinal); ?></td>
										<td><span class="pos-report-badge <?php echo html_escape($statusTone($line['line_status'] ?? '')); ?>"><?php echo html_escape((string)($line['line_status'] ?? '-')); ?></span></td>
									</tr>
									<?php endforeach; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="row g-3">
			<div class="col-lg-6">
				<div class="pos-report-section p-3 h-100">
					<div class="mb-3"><div class="pos-tx-section-title">Pembayaran</div><div class="pos-report-meta">Dokumen pembayaran dan line metode bayar. Metode dapat dikoreksi dari sini bila user punya izin edit.</div></div>
					<?php if (empty($payments)): ?><div class="pos-report-empty">Belum ada pembayaran tercatat.</div><?php else: ?><div class="pos-tx-stack"><?php foreach ($payments as $payment): ?><div class="pos-tx-doc"><div class="pos-tx-doc-head"><div><div class="pos-tx-doc-title"><?php echo html_escape((string)($payment['payment_no'] ?? '-')); ?></div><div class="pos-tx-doc-meta"><?php echo html_escape($dt($payment['paid_at'] ?? $payment['created_at'] ?? null)); ?> • <?php echo html_escape((string)($payment['cashier_name'] ?? '-')); ?> • <?php echo html_escape((string)($payment['payment_type'] ?? '-')); ?> / <?php echo html_escape((string)($payment['payment_status'] ?? '-')); ?></div></div><div class="pos-tx-doc-amount"><?php echo $money($payment['net_amount'] ?? 0); ?></div></div><div class="pos-tx-flag-list mb-3"><div class="pos-tx-flag"><strong>Voucher</strong><?php echo $money($payment['voucher_amount'] ?? 0); ?></div><div class="pos-tx-flag"><strong>Poin</strong><?php echo $money($payment['point_redeem_amount'] ?? 0); ?></div><div class="pos-tx-flag"><strong>Compliment</strong><?php echo $money($payment['compliment_amount'] ?? 0); ?></div><div class="pos-tx-flag"><strong>Kembalian</strong><?php echo $money($payment['change_amount'] ?? 0); ?></div></div><div class="pos-tx-line-list"><?php foreach ((array)($payment['lines'] ?? []) as $line): ?><div class="pos-tx-line-item" data-payment-line-row="<?php echo (int)($line['id'] ?? 0); ?>"><div class="pos-tx-line-head"><div><div class="pos-tx-line-method" data-method-target><?php echo html_escape((string)($line['method_name'] ?? '-')); ?></div><div class="pos-tx-line-account" data-account-target><?php echo html_escape($accountText($line)); ?></div><div class="pos-tx-line-meta">Ref <?php echo html_escape((string)($line['reference_no'] ?? '-')); ?> • Status <?php echo html_escape((string)($line['status'] ?? '-')); ?> • Diterima <?php echo html_escape($dt($line['received_at'] ?? null)); ?></div></div><div class="pos-tx-line-amount"><?php echo $money($line['amount'] ?? 0); ?></div></div><?php if ($canEditPaymentMethod && strtoupper((string)($payment['payment_status'] ?? '')) !== 'VOID' && strtoupper((string)($line['status'] ?? '')) === 'PAID'): ?><div class="pos-tx-line-actions"><button type="button" class="btn btn-sm btn-outline-primary pos-tx-action-btn js-edit-payment-line" data-edit-trigger="1" data-payment-line-id="<?php echo (int)($line['id'] ?? 0); ?>" data-payment-no="<?php echo html_escape((string)($payment['payment_no'] ?? '-')); ?>" data-line-no="<?php echo (int)($line['line_no'] ?? 0); ?>" data-current-method-id="<?php echo (int)($line['payment_method_id'] ?? 0); ?>" data-current-method-name="<?php echo html_escape((string)($line['method_name'] ?? '-')); ?>" data-current-account="<?php echo html_escape($accountText($line)); ?>" data-amount="<?php echo (float)($line['amount'] ?? 0); ?>" data-reference-no="<?php echo html_escape((string)($line['reference_no'] ?? '')); ?>" title="Edit metode pembayaran"><i class="ri ri-edit-line"></i></button></div><?php endif; ?></div><?php endforeach; ?></div></div><?php endforeach; ?></div><?php endif; ?>
				</div>
			</div>
			<div class="col-lg-3">
				<div class="pos-report-section p-3 h-100">
					<div class="pos-tx-section-title mb-3">Refund</div>
					<?php if (empty($refunds)): ?><div class="pos-report-empty">Belum ada refund untuk order ini.</div><?php else: ?><div class="pos-tx-stack"><?php foreach ($refunds as $refund): ?><div class="pos-tx-doc"><div class="pos-tx-doc-head"><div><div class="pos-tx-doc-title"><?php echo html_escape((string)($refund['refund_no'] ?? '-')); ?></div><div class="pos-tx-doc-meta"><?php echo html_escape($dt($refund['refunded_at'] ?? null)); ?> • <?php echo html_escape((string)($refund['refunded_by_name'] ?? '-')); ?></div></div><div class="pos-tx-doc-amount text-warning"><?php echo $money($refund['refund_amount'] ?? 0); ?></div></div><div class="pos-report-inline-list"><div class="muted"><?php echo html_escape((string)($refund['method_name'] ?? '-')); ?> • <?php echo html_escape((string)($refund['company_account_name'] ?? 'Tanpa rekening')); ?></div><div class="muted"><?php echo html_escape((string)($refund['refund_status'] ?? '-')); ?> • <?php echo html_escape((string)($refund['reason'] ?? $refund['notes'] ?? '-')); ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
				</div>
			</div>
			<div class="col-lg-3">
				<div class="pos-report-section p-3 h-100">
					<div class="pos-tx-section-title mb-3">Void</div>
					<?php if (empty($voids)): ?><div class="pos-report-empty">Belum ada void untuk order ini.</div><?php else: ?><div class="pos-tx-stack"><?php foreach ($voids as $void): ?><div class="pos-tx-doc"><div class="pos-tx-doc-head"><div><div class="pos-tx-doc-title"><?php echo html_escape((string)($void['void_no'] ?? '-')); ?></div><div class="pos-tx-doc-meta"><?php echo html_escape($dt($void['created_at'] ?? null)); ?> • <?php echo html_escape((string)($void['actor_name'] ?? '-')); ?></div></div><div class="pos-tx-doc-amount text-danger"><?php echo $money($void['amount_void'] ?? 0); ?></div></div><div class="pos-report-inline-list"><div class="muted"><?php echo html_escape((string)($void['void_scope'] ?? '-')); ?> • <?php echo html_escape((string)($void['reason_code'] ?? $void['reason'] ?? '-')); ?></div><div class="muted"><?php echo html_escape((string)($void['notes'] ?? '-')); ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php if ($canEditPaymentMethod): ?>
<div class="modal fade" id="paymentMethodEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Metode Pembayaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="pos-tx-modal-note mb-3"><div class="fw-semibold mb-1" id="payment_edit_title">-</div><div id="payment_edit_meta">-</div></div><div class="mb-3"><label class="form-label small text-muted mb-1">Metode Saat Ini</label><div class="form-control bg-light" id="payment_edit_current_method">-</div></div><div class="mb-3"><label class="form-label small text-muted mb-1">Metode Baru</label><select class="form-select" id="payment_edit_method_id"><option value="">Pilih metode pembayaran...</option><?php foreach ($paymentMethodOptions as $method): ?><option value="<?php echo (int)($method['id'] ?? 0); ?>"><?php echo html_escape((string)($method['method_name'] ?? '-')); ?><?php echo !empty($method['method_type']) ? ' • ' . html_escape((string)$method['method_type']) : ''; ?></option><?php endforeach; ?></select></div><div class="mb-0"><label class="form-label small text-muted mb-1">Rekening Tujuan</label><div class="form-control bg-light" id="payment_edit_account_preview">Pilih metode untuk melihat rekening perusahaan yang terkait.</div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button><button type="button" class="btn btn-primary" id="btn-save-payment-method">Simpan Perubahan</button></div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	const flashEl = document.getElementById('tx_flash');
	const modalEl = document.getElementById('paymentMethodEditModal');
	const methodField = document.getElementById('payment_edit_method_id');
	const saveButton = document.getElementById('btn-save-payment-method');
	const titleEl = document.getElementById('payment_edit_title');
	const metaEl = document.getElementById('payment_edit_meta');
	const currentMethodEl = document.getElementById('payment_edit_current_method');
	const accountPreviewEl = document.getElementById('payment_edit_account_preview');
	const paymentMethodOptions = <?php echo json_encode(array_values($paymentMethodOptions), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
	const paymentMethodMap = Object.fromEntries(paymentMethodOptions.map((row) => [String(row.id || ''), row]));
	const editModal = window.bootstrap && modalEl ? new bootstrap.Modal(modalEl) : null;
	let activeTrigger = null;
	function setFlash(type, message) {
		if (!flashEl) return;
		flashEl.className = 'alert alert-' + type + ' mb-3';
		flashEl.textContent = message;
	}
	function money(value) {
		const amount = Number(value || 0);
		return 'Rp ' + amount.toLocaleString('id-ID', { maximumFractionDigits: 0 });
	}
	function accountText(method) {
		if (!method) return 'Rekening belum dihubungkan';
		const parts = [];
		if (String(method.account_name || '').trim() !== '') parts.push(String(method.account_name));
		if (String(method.bank_name || '').trim() !== '') parts.push(String(method.bank_name));
		if (String(method.account_no || '').trim() !== '') parts.push(String(method.account_no));
		if (String(method.account_holder || '').trim() !== '') parts.push(String(method.account_holder));
		return parts.length ? parts.join(' • ') : 'Rekening belum dihubungkan';
	}
	function updateAccountPreview() {
		accountPreviewEl.textContent = accountText(paymentMethodMap[String(methodField.value || '')] || null);
	}
	function setButtonLoading(button, loading) {
		if (!button) return;
		if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function' && typeof window.FinanceUI.clearButtonLoading === 'function') {
			if (loading) {
				window.FinanceUI.setButtonLoading(button, 'Menyimpan...');
			} else {
				window.FinanceUI.clearButtonLoading(button);
			}
			return;
		}
		if (loading) {
			button.dataset.originalHtml = button.innerHTML;
			button.disabled = true;
			button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
		} else {
			button.disabled = false;
			if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
		}
	}
	function fillModal(trigger) {
		activeTrigger = trigger;
		titleEl.textContent = (trigger.dataset.paymentNo || '-') + ' • line ' + (trigger.dataset.lineNo || '-');
		metaEl.textContent = 'Nominal ' + money(trigger.dataset.amount || 0) + ' • Referensi ' + (trigger.dataset.referenceNo || '-');
		currentMethodEl.textContent = (trigger.dataset.currentMethodName || '-') + ' • ' + (trigger.dataset.currentAccount || 'Rekening belum dihubungkan');
		methodField.value = String(trigger.dataset.currentMethodId || '');
		updateAccountPreview();
	}
	document.querySelectorAll('.js-edit-payment-line').forEach((button) => {
		button.addEventListener('click', function () {
			fillModal(button);
			if (editModal) editModal.show();
		});
	});
	methodField.addEventListener('change', updateAccountPreview);
	saveButton.addEventListener('click', async function () {
		if (!activeTrigger) return;
		const paymentLineId = Number(activeTrigger.dataset.paymentLineId || 0);
		const paymentMethodId = Number(methodField.value || 0);
		if (paymentLineId <= 0) {
			setFlash('danger', 'Line pembayaran tidak valid.');
			return;
		}
		if (paymentMethodId <= 0) {
			setFlash('warning', 'Pilih metode pembayaran terlebih dahulu.');
			return;
		}
		setButtonLoading(saveButton, true);
		try {
			const response = await fetch('<?php echo site_url('pos/reports/sales/payment-line/update'); ?>/' + paymentLineId, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
				body: new URLSearchParams({ payment_method_id: String(paymentMethodId) }).toString()
			});
			const result = await response.json();
			if (!response.ok || !result || result.ok !== true) throw new Error((result && result.message) ? result.message : 'Gagal memperbarui metode pembayaran.');
			const line = result.line || {};
			const row = document.querySelector('[data-payment-line-row="' + paymentLineId + '"]');
			if (row) {
				const methodTarget = row.querySelector('[data-method-target]');
				const accountTarget = row.querySelector('[data-account-target]');
				const methodName = String(line.method_name || '-');
				const accountName = [line.company_account_name || '', line.company_bank_name || '', line.company_account_code || ''].filter((part) => String(part).trim() !== '').join(' • ') || 'Rekening belum dihubungkan';
				if (methodTarget) methodTarget.textContent = methodName;
				if (accountTarget) accountTarget.textContent = accountName;
				const trigger = row.querySelector('[data-edit-trigger]');
				if (trigger) {
					trigger.dataset.currentMethodId = String(line.payment_method_id || paymentMethodId);
					trigger.dataset.currentMethodName = methodName;
					trigger.dataset.currentAccount = accountName;
				}
			}
			setFlash('success', result.message || 'Metode pembayaran berhasil diperbarui.');
			if (editModal) editModal.hide();
		} catch (error) {
			setFlash('danger', error && error.message ? error.message : 'Gagal memperbarui metode pembayaran.');
		} finally {
			setButtonLoading(saveButton, false);
		}
	});
});
</script>
<?php endif; ?>
