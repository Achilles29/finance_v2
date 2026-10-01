<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$overview = is_array($overview ?? null) ? $overview : [];
$meta = is_array($meta ?? null) ? $meta : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
$accounts = is_array($accounts ?? null) ? $accounts : [];
$money = static function ($value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$dt = static function ($value): string {
    $time = $value ? strtotime((string)$value) : false;
    return $time ? date('d M Y H:i', $time) : '-';
};
$statusBadge = static function ($status): string {
    $value = strtoupper(trim((string)$status));
    $class = 'secondary';
    if ($value === 'CLOSED') {
        $class = 'success';
    } elseif ($value === 'OPEN') {
        $class = 'warning';
    } elseif ($value === 'VOID') {
        $class = 'danger';
    }

    return '<span class="pos-report-badge ' . $class . '">' . html_escape($value !== '' ? $value : '-') . '</span>';
};
$this->load->view('pos/_report_styles');
?>

<div class="container-xxl py-3">
    <div class="pos-report-shell">
        <div class="pos-report-hero mb-3">
            <div class="pos-report-title">Riwayat Tutup Kasir POS</div>
            <p class="pos-report-copy mb-0">Daftar shift POS yang sudah dibuka dan ditutup. Buka detail shift untuk melihat ringkasan tutup kasir, snapshot rekening, dan pembanding mutasi brankas yang tercatat.</p>
        </div>

        <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'cashier_close']); ?>

        <div class="row g-3 mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="pos-report-card">
                    <div class="pos-report-card-label">Shift Tutup</div>
                    <div class="pos-report-card-value"><?php echo number_format((int)($overview['shift_count'] ?? 0)); ?></div>
                    <div class="pos-report-card-note">Jumlah sesi kasir berstatus tutup.</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pos-report-card">
                    <div class="pos-report-card-label">Net Sales</div>
                    <div class="pos-report-card-value"><?php echo $money($overview['total_net_sales'] ?? 0); ?></div>
                    <div class="pos-report-card-note">Penjualan bersih dari shift-filter ini.</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pos-report-card">
                    <div class="pos-report-card-label">Expected Cash</div>
                    <div class="pos-report-card-value"><?php echo $money($overview['total_expected_cash'] ?? $overview['expected_cash'] ?? 0); ?></div>
                    <div class="pos-report-card-note">Akumulasi kas yang seharusnya ada saat tutup.</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pos-report-card">
                    <div class="pos-report-card-label">Shift Selisih</div>
                    <div class="pos-report-card-value"><?php echo number_format((int)($overview['variance_shift_count'] ?? $overview['cash_variance_shift_count'] ?? 0)); ?></div>
                    <div class="pos-report-card-note">Jumlah shift dengan selisih kas saat tutup.</div>
                </div>
            </div>
        </div>

        <div class="pos-report-section p-3 mb-3">
            <form method="get" class="pos-report-filter-box row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No shift / outlet / terminal / kasir">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Outlet</label>
                    <select name="outlet_id" class="form-select">
                        <option value="0">Semua Outlet</option>
                        <?php foreach ($outlets as $outlet): ?>
                            <option value="<?php echo (int)($outlet['id'] ?? 0); ?>"<?php echo (int)($filters['outlet_id'] ?? 0) === (int)($outlet['id'] ?? 0) ? ' selected' : ''; ?>><?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Rekening Fokus</label>
                    <select name="account_id" class="form-select">
                        <option value="0">Default akun kas / brankas</option>
                        <?php foreach ($accounts as $account): ?>
                            <?php
                            $parts = [];
                            if (!empty($account['account_code'])) {
                                $parts[] = trim((string)$account['account_code']);
                            }
                            if (!empty($account['account_name'])) {
                                $parts[] = trim((string)$account['account_name']);
                            }
                            $label = implode(' - ', $parts);
                            if (!empty($account['bank_name'])) {
                                $label .= ($label !== '' ? ' | ' : '') . trim((string)$account['bank_name']);
                            }
                            ?>
                            <option value="<?php echo (int)($account['id'] ?? 0); ?>"<?php echo (int)($filters['account_id'] ?? 0) === (int)($account['id'] ?? 0) ? ' selected' : ''; ?>><?php echo html_escape($label !== '' ? $label : 'Rekening #' . (int)($account['id'] ?? 0)); ?></option>
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
                                <th>No Shift</th>
                                <th>Outlet</th>
                                <th>Kasir</th>
                                <th>Buka / Tutup</th>
                                <th>Kas</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="7" class="text-center pos-report-empty">Data shift tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr class="<?php echo !empty($row['has_any_variance']) ? 'pos-report-tone-warning' : ''; ?>">
                                        <td>
                                            <div class="fw-semibold"><?php echo html_escape((string)($row['shift_no'] ?? '-')); ?></div>
                                            <div class="pos-report-meta"><?php echo number_format((int)($row['total_order_count'] ?? 0)); ?> transaksi</div>
                                        </td>
                                        <td>
                                            <div><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></div>
                                            <div class="pos-report-meta"><?php echo html_escape((string)($row['terminal_name'] ?? '-')); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo html_escape((string)($row['cashier_open_name'] ?? '-')); ?></div>
                                            <div class="pos-report-meta">Tutup: <?php echo html_escape((string)($row['cashier_close_name'] ?? '-')); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo html_escape($dt($row['opened_at'] ?? null)); ?></div>
                                            <div class="pos-report-meta"><?php echo html_escape($dt($row['closed_at'] ?? null)); ?></div>
                                        </td>
                                        <td class="small">
                                            <div>Awal: <?php echo $money($row['opening_cash'] ?? 0); ?></div>
                                            <div>Expected: <?php echo $money($row['expected_cash'] ?? 0); ?></div>
                                            <div>Aktual: <?php echo $money($row['actual_cash'] ?? 0); ?></div>
                                            <div class="<?php echo !empty($row['has_cash_variance']) ? 'text-danger fw-semibold' : 'pos-report-meta'; ?>">Selisih: <?php echo $money($row['variance_cash'] ?? 0); ?></div>
                                        </td>
                                        <td><?php echo $statusBadge((string)($row['status'] ?? '')); ?></td>
                                        <td class="text-end">
                                            <a href="<?php echo site_url('pos/reports/cashier-close/' . (int)($row['id'] ?? 0) . '?account_id=' . (int)($filters['account_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php $this->load->view('pos/_report_pager', ['meta' => $meta, 'filters' => $filters, 'pager_path' => 'pos/reports/cashier-close']); ?>
        </div>
    </div>
</div>
