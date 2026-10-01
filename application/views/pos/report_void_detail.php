<?php
$row = is_array($row ?? null) ? $row : [];
$lines = is_array($lines ?? null) ? $lines : [];
$extrasByVoidLine = is_array($extras_by_void_line ?? null) ? $extras_by_void_line : [];
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
$scopeLabel = static function ($scope): string {
		$value = strtoupper(trim((string)$scope));
		$map = [
				'FULL' => 'Penuh',
				'PARTIAL' => 'Sebagian',
		];
		return $map[$value] ?? ($value !== '' ? str_replace('_', ' ', $value) : '-');
};
$processLabel = static function ($status): string {
		$value = strtoupper(trim((string)$status));
		$map = [
				'NOT_PROCESSED' => 'Belum diproses',
				'PROCESSED' => 'Sudah diproses',
				'PARTIAL' => 'Diproses sebagian',
		];
		return $map[$value] ?? ($value !== '' ? str_replace('_', ' ', $value) : '-');
};
$lineStatusLabel = static function ($status): string {
		$value = strtoupper(trim((string)$status));
		$map = [
				'OPEN' => 'Aktif',
				'ACTIVE' => 'Aktif',
				'VOID' => 'Void penuh',
				'VOID_PARTIAL' => 'Void sebagian',
				'REFUNDED_FULL' => 'Refund penuh',
				'REFUNDED_PARTIAL' => 'Refund sebagian',
		];
		return $map[$value] ?? ($value !== '' ? str_replace('_', ' ', $value) : '-');
};
$this->load->view('pos/_report_styles');
?>

<div class="container-xxl py-3">
	<div class="pos-report-shell">
		<div class="pos-report-hero mb-3">
			<div class="d-flex flex-wrap justify-content-between gap-3">
				<div>
					<div class="pos-report-title">Detail Void POS</div>
					<p class="pos-report-copy mb-0">Rincian line produk dan extra yang dibatalkan dari dokumen void POS.</p>
				</div>
				<a href="<?php echo site_url('pos/reports/voids'); ?>" class="btn btn-outline-secondary">Kembali</a>
			</div>
		</div>

		<?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'void']); ?>

		<div class="row g-3 mb-3">
			<div class="col-lg-5">
				<div class="pos-report-section p-3 h-100">
					<div class="pos-report-kv">
						<strong>Void</strong>
						<span><?php echo html_escape((string)($row['void_no'] ?? '-')); ?></span>

						<strong>Order</strong>
						<span><?php echo html_escape((string)($row['order_no_snapshot'] ?? '-')); ?></span>

						<strong>Member</strong>
						<span>
							<?php echo html_escape((string)($row['member_name_snapshot'] ?? $row['member_name'] ?? '-')); ?>
							<?php echo !empty($row['member_no']) ? ' | ' . html_escape((string)$row['member_no']) : ''; ?>
						</span>

						<strong>Outlet</strong>
						<span><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></span>

						<strong>Actor</strong>
						<span><?php echo html_escape((string)($row['actor_name'] ?? '-')); ?></span>

						<strong>Scope</strong>
						<span><?php echo html_escape($scopeLabel($row['void_scope'] ?? '-')); ?></span>

						<strong>Proses Stok</strong>
						<span><?php echo html_escape($processLabel($row['processed_state'] ?? '-')); ?></span>

						<strong>Created At</strong>
						<span><?php echo html_escape($dt($row['created_at'] ?? null)); ?></span>

						<strong>Reason</strong>
						<span><?php echo html_escape((string)($row['reason'] ?? '-')); ?></span>
					</div>
				</div>
			</div>

			<div class="col-lg-7">
				<div class="row g-3">
					<div class="col-md-4">
						<div class="pos-report-card">
							<div class="pos-report-card-label">Amount Void</div>
							<div class="pos-report-card-value"><?php echo $money($row['amount_void'] ?? 0); ?></div>
							<div class="pos-report-card-note">Nilai pembatalan.</div>
						</div>
					</div>
					<div class="col-md-4">
						<div class="pos-report-card">
							<div class="pos-report-card-label">Qty Void</div>
							<div class="pos-report-card-value"><?php echo $qty($row['total_qty_void'] ?? 0, 0); ?></div>
							<div class="pos-report-card-note">Qty yang dibatalkan.</div>
						</div>
					</div>
					<div class="col-md-4">
						<div class="pos-report-card">
							<div class="pos-report-card-label">Lines</div>
							<div class="pos-report-card-value"><?php echo number_format((int)($row['line_count'] ?? 0)); ?></div>
							<div class="pos-report-card-note">Produk dan extra terdampak.</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="pos-report-section p-3">
			<h5 class="mb-3">Line Void</h5>
			<div class="pos-report-table-wrap">
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0 pos-report-table">
						<thead>
							<tr>
								<th>Line</th>
								<th>Item</th>
								<th class="text-end">Qty Before</th>
								<th class="text-end">Qty Void</th>
								<th class="text-end">Qty After</th>
								<th class="text-end">Subtotal</th>
								<th>Proses</th>
								<th>Status After</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($lines)): ?>
								<tr>
									<td colspan="8" class="text-center pos-report-empty">Belum ada line void.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($lines as $line): ?>
									<?php $lineId = (int)($line['id'] ?? 0); ?>
									<tr>
										<td><?php echo (int)($line['line_no_snapshot'] ?? 0); ?></td>
										<td>
											<div class="fw-semibold"><?php echo html_escape((string)($line['item_name_snapshot'] ?? '-')); ?></div>
											<?php if (!empty($extrasByVoidLine[$lineId])): ?>
												<div class="mt-2 pos-report-inline-list">
													<?php foreach ((array)$extrasByVoidLine[$lineId] as $extra): ?>
														<span class="muted">
															+ <?php echo html_escape((string)($extra['extra_name_snapshot'] ?? '-')); ?>
															| <?php echo $qty($extra['line_qty_affected'] ?? 0, 0); ?> x <?php echo $money($extra['unit_price'] ?? 0); ?>
														</span>
													<?php endforeach; ?>
												</div>
											<?php endif; ?>
										</td>
										<td class="text-end"><?php echo $qty($line['qty_before'] ?? 0, 0); ?></td>
										<td class="text-end fw-semibold"><?php echo $qty($line['qty_void'] ?? 0, 0); ?></td>
										<td class="text-end"><?php echo $qty($line['qty_after'] ?? 0, 0); ?></td>
										<td class="text-end"><?php echo $money($line['subtotal_void'] ?? 0); ?></td>
										<td><?php echo html_escape($processLabel($line['line_process_state'] ?? '-')); ?></td>
										<td><?php echo html_escape($lineStatusLabel($line['line_status_after'] ?? '-')); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>