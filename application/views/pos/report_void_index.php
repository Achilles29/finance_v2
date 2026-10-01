<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$overview = is_array($overview ?? null) ? $overview : [];
$meta = is_array($meta ?? null) ? $meta : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
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
				'ALL' => 'Semua',
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
$orderStatusLabel = static function ($status): string {
		$value = strtoupper(trim((string)$status));
		$map = [
				'DRAFT' => 'Draft',
				'CONFIRMED' => 'Terkonfirmasi',
				'PAID' => 'Lunas',
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
			<div class="pos-report-title">Laporan Void POS</div>
			<p class="pos-report-copy mb-0">Lacak dokumen void POS beserta scope, qty, nilai yang dibatalkan, dan status proses stoknya.</p>
		</div>

		<?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'void']); ?>
		<?php $this->load->view('pos/_report_filter_summary', ['filters' => $filters, 'outlets' => $outlets]); ?>

		<div class="row g-3 mb-3">
			<div class="col-md-3 col-sm-6">
				<div class="pos-report-card">
					<div class="pos-report-card-label">Void</div>
					<div class="pos-report-card-value"><?php echo number_format((int)($overview['void_count'] ?? 0)); ?></div>
					<div class="pos-report-card-note">Dokumen void pada filter ini.</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6">
				<div class="pos-report-card">
					<div class="pos-report-card-label">Amount Void</div>
					<div class="pos-report-card-value"><?php echo $money($overview['amount_void'] ?? 0); ?></div>
					<div class="pos-report-card-note">Nilai total yang di-void.</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6">
				<div class="pos-report-card">
					<div class="pos-report-card-label">Qty Void</div>
					<div class="pos-report-card-value"><?php echo $qty($overview['total_qty_void'] ?? 0, 0); ?></div>
					<div class="pos-report-card-note">Akumulasi qty yang dibatalkan.</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6">
				<div class="pos-report-card">
					<div class="pos-report-card-label">Full Void</div>
					<div class="pos-report-card-value"><?php echo number_format((int)($overview['full_void_count'] ?? 0)); ?></div>
					<div class="pos-report-card-note">Order yang dibatalkan penuh.</div>
				</div>
			</div>
		</div>

		<div class="pos-report-section p-3 mb-3">
			<form method="get" class="pos-report-filter-box row g-2 align-items-end">
				<div class="col-lg-4 col-md-6">
					<label class="form-label small text-muted mb-1">Cari</label>
					<input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Void / order / member / alasan">
				</div>
				<div class="col-lg-2 col-md-6">
					<label class="form-label small text-muted mb-1">Outlet</label>
					<select name="outlet_id" class="form-select">
						<option value="0">Semua Outlet</option>
						<?php foreach ($outlets as $outlet): ?>
							<option value="<?php echo (int)($outlet['id'] ?? 0); ?>"<?php echo (int)($filters['outlet_id'] ?? 0) === (int)($outlet['id'] ?? 0) ? ' selected' : ''; ?>>
								<?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-lg-2 col-md-4">
					<label class="form-label small text-muted mb-1">Scope</label>
					<select name="void_scope" class="form-select">
						<?php foreach (['ALL', 'FULL', 'PARTIAL'] as $scope): ?>
							<option value="<?php echo $scope; ?>"<?php echo (string)($filters['void_scope'] ?? 'ALL') === $scope ? ' selected' : ''; ?>><?php echo html_escape($scopeLabel($scope)); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-lg-1 col-md-4">
					<label class="form-label small text-muted mb-1">Dari</label>
					<input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>">
				</div>
				<div class="col-lg-1 col-md-4">
					<label class="form-label small text-muted mb-1">Sampai</label>
					<input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>">
				</div>
				<div class="col-lg-1 col-md-4">
					<label class="form-label small text-muted mb-1">Limit</label>
					<select name="limit" class="form-select">
						<?php foreach ([25, 50, 100, 200] as $limit): ?>
							<option value="<?php echo $limit; ?>"<?php echo (int)($filters['limit'] ?? 25) === $limit ? ' selected' : ''; ?>><?php echo $limit; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-lg-1 col-md-4 d-grid">
					<button type="submit" class="btn btn-dark">Filter</button>
				</div>
			</form>
		</div>

		<div class="pos-report-section p-3">
			<div class="pos-report-table-wrap">
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0 pos-report-table">
						<thead>
							<tr>
								<th>Tanggal</th>
								<th>Void</th>
								<th>Order</th>
								<th>Member</th>
								<th>Outlet</th>
								<th>Scope</th>
								<th class="text-end">Qty</th>
								<th class="text-end">Amount</th>
								<th>Proses Stok</th>
								<th class="text-end">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($rows)): ?>
								<tr>
									<td colspan="10" class="text-center pos-report-empty">Belum ada void pada filter ini.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($rows as $row): ?>
									<tr>
										<td><?php echo html_escape($dt($row['created_at'] ?? null)); ?></td>
										<td>
											<div class="fw-semibold"><?php echo html_escape((string)($row['void_no'] ?? '-')); ?></div>
											<div class="pos-report-meta"><?php echo html_escape((string)($row['actor_name'] ?? '-')); ?></div>
										</td>
										<td>
											<div><?php echo html_escape((string)($row['order_no_snapshot'] ?? '-')); ?></div>
											<div class="pos-report-meta">
												<?php echo html_escape($orderStatusLabel($row['order_status_before'] ?? '-')); ?>
												|
												<?php echo html_escape($orderStatusLabel($row['order_status_after'] ?? '-')); ?>
											</div>
										</td>
										<td>
											<div><?php echo html_escape((string)($row['member_name_snapshot'] ?? $row['member_name'] ?? '-')); ?></div>
											<div class="pos-report-meta"><?php echo html_escape((string)($row['member_no'] ?? '-')); ?></div>
										</td>
										<td><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></td>
										<td><?php echo html_escape($scopeLabel($row['void_scope'] ?? '-')); ?></td>
										<td class="text-end"><?php echo $qty($row['total_qty_void'] ?? 0, 0); ?></td>
										<td class="text-end fw-semibold"><?php echo $money($row['amount_void'] ?? 0); ?></td>
										<td><?php echo html_escape($processLabel($row['processed_state'] ?? '-')); ?></td>
										<td>
											<div class="pos-report-actions">
												<a href="<?php echo site_url('pos/reports/voids/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<?php $this->load->view('pos/_report_pager', ['meta' => $meta, 'filters' => $filters, 'pager_path' => 'pos/reports/voids']); ?>
		</div>
	</div>
</div>