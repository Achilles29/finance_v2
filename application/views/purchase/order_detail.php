<?php
$detail = (array)($detail ?? []);
$order = (array)($detail['order'] ?? []);
$lines = (array)($detail['lines'] ?? []);
$payments = (array)($detail['payments'] ?? []);
$receipts = (array)($detail['receipts'] ?? []);
$receiptRows = (array)($detail['receipt_rows'] ?? []);
$outstanding = (float)($detail['outstanding'] ?? 0);
$txnRows = (array)($detail['txn_rows'] ?? []);
$auditRows = (array)($detail['audit_rows'] ?? []);
$editability = (array)($editability ?? []);
$editabilityData = (array)($editability['data'] ?? []);
$editMode = (string)($editabilityData['mode'] ?? '');
$purchaseMutationCsrfToken = (string)($purchase_mutation_csrf_token ?? '');

$currentStatus = strtoupper((string)($order['status'] ?? 'DRAFT'));
$timelineStatus = $currentStatus === 'CLOSED' ? 'PAID' : $currentStatus;
$canEditData = !empty($editability['ok']);
$isPaymentOnlyEdit = $editMode === 'payment_only';
$statusOrder = ['DRAFT', 'APPROVED', 'ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID'];
$statusRank = array_flip($statusOrder);
$currentRank = $statusRank[$timelineStatus] ?? -1;
$isTerminal = in_array($currentStatus, ['REJECTED', 'VOID'], true);

$statusBadgeClass = function (string $status): string {
    $status = strtoupper(trim($status));
    $map = [
        'DRAFT' => 'bg-secondary',
        'APPROVED' => 'bg-info',
        'ORDERED' => 'bg-primary',
        'REJECTED' => 'bg-danger',
        'PARTIAL_RECEIVED' => 'bg-warning text-dark',
        'RECEIVED' => 'bg-success',
        'PAID' => 'bg-info text-dark',
        'CLOSED' => 'bg-dark',
        'VOID' => 'bg-secondary',
    ];
    return $map[$status] ?? 'bg-secondary';
};

$canEditPo = !empty($current_user['is_superadmin']) || !empty($user_perms['purchase.order.index']['can_edit']);

$timeline = [
    ['code' => 'DRAFT', 'label' => 'PO dibuat', 'at' => $order['created_at'] ?? null],
    ['code' => 'APPROVED', 'label' => 'PO disetujui', 'at' => $order['approved_at'] ?? null],
    ['code' => 'ORDERED', 'label' => 'PO dipesan', 'at' => null],
    ['code' => 'PARTIAL_RECEIVED', 'label' => 'Barang diterima sebagian', 'at' => $receipts['last_receipt_at'] ?? null],
    ['code' => 'RECEIVED', 'label' => 'Barang diterima penuh', 'at' => $receipts['last_receipt_at'] ?? null],
    ['code' => 'PAID', 'label' => 'Pembayaran lunas', 'at' => $payments['last_payment_date'] ?? null],
];

$statusTransitions = [
    'DRAFT' => ['APPROVED', 'ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
    'APPROVED' => ['ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
    'ORDERED' => ['PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
    'PARTIAL_RECEIVED' => ['RECEIVED', 'PAID', 'VOID'],
    'RECEIVED' => ['PAID', 'VOID'],
    'PAID' => ['VOID'],
    'CLOSED' => ['VOID'],
    'REJECTED' => [],
    'VOID' => [],
];
$statusUpdateOptions = [$currentStatus];
foreach (($statusTransitions[$currentStatus] ?? []) as $nextStatus) {
    if (!in_array($nextStatus, $statusUpdateOptions, true)) {
        $statusUpdateOptions[] = $nextStatus;
    }
}
?>
<?php $this->load->view('procurement/_stock_review_history', ['stock_review_history'=>$stock_review_history ?? []]); ?>

<style>
    .po-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
    }
    .po-timeline::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: rgba(168, 142, 132, 0.45);
    }
    .po-timeline-item {
        position: relative;
        padding-left: 2.2rem;
        margin-bottom: 1rem;
    }
    .po-timeline-item:last-child {
        margin-bottom: 0;
    }
    .po-timeline-dot {
        position: absolute;
        left: 6px;
        top: 4px;
        width: 14px;
        height: 14px;
        border-radius: 999px;
        border: 2px solid #d8c8c0;
        background: #fff;
    }
    .po-timeline-item.is-done .po-timeline-dot,
    .po-timeline-item.is-current .po-timeline-dot {
        border-color: #922d21;
        background: #b03025;
    }
    .po-timeline-item.is-pending {
        opacity: 0.6;
    }
    .po-lot-table td,
    .po-lot-table th {
        vertical-align: top;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Detail Purchase Order</h4>
        <small class="text-muted">Timeline status dan rincian line purchase order.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Kembali ke Purchase Orders</a>
        <a href="<?php echo site_url('purchase-orders/logs?q=' . urlencode((string)($order['po_no'] ?? ''))); ?>" class="btn btn-outline-secondary">Log Purchase</a>
        <?php if ($canEditPo && $canEditData): ?>
            <a href="<?php echo site_url('purchase-orders/edit/' . (int)($order['id'] ?? 0)); ?>" class="btn btn-outline-warning"><?php echo $isPaymentOnlyEdit ? 'Ubah Metode Bayar' : 'Edit Data PO'; ?></a>
        <?php endif; ?>
        <a href="<?php echo site_url('purchase-orders/create'); ?>" class="btn btn-primary">Create Order</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">No PO</small>
                <h5 class="mb-0"><?php echo html_escape((string)($order['po_no'] ?? '-')); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Status Saat Ini</small>
                <span class="badge <?php echo $statusBadgeClass($currentStatus); ?>"><?php echo html_escape($currentStatus); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Grand Total</small>
                <h5 class="mb-0">Rp <?php echo number_format((float)($order['grand_total'] ?? 0), 2, ',', '.'); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Outstanding</small>
                <h5 class="mb-0">Rp <?php echo number_format($outstanding, 2, ',', '.'); ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div>
            <label class="form-label mb-1">Update Status PO</label>
            <select id="po-detail-status-next" class="form-select form-select-sm" style="min-width: 220px;" <?php echo $canEditPo ? '' : 'disabled'; ?>>
                <?php foreach ($statusUpdateOptions as $st): ?>
                    <option value="<?php echo html_escape((string)$st); ?>" <?php echo $st === $currentStatus ? 'selected' : ''; ?>><?php echo html_escape((string)$st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" id="btn-po-detail-status-update" class="btn btn-outline-primary btn-sm" <?php echo $canEditPo ? '' : 'disabled'; ?>>Update Status</button>
        <?php if (in_array($currentStatus, ['RECEIVED', 'PAID'], true)): ?>
            <button
                type="button"
                id="btn-po-detail-impact-reconcile"
                class="btn btn-outline-warning btn-sm"
                data-status="<?php echo html_escape($currentStatus); ?>"
                <?php echo $canEditPo ? '' : 'disabled'; ?>
            >Sinkronkan Dampak <?php echo html_escape($currentStatus); ?></button>
        <?php endif; ?>
        <?php if ($canEditPo): ?>
            <small class="text-muted">Jika update dari halaman list gagal, gunakan form ini sebagai fallback.</small>
        <?php else: ?>
            <small class="text-muted">Akun ini belum memiliki izin edit status PO.</small>
        <?php endif; ?>
    </div>
</div>

<?php
if (!function_exists('finance_po_usage_purpose_from_notes')) {
    function finance_po_usage_purpose_from_notes($notes)
    {
        $notes = (string)$notes;
        if (preg_match('/Tujuan\s+pemakaian\s*:\s*(Kebutuhan\s+Operasional|Persediaan\s+Produksi|Operasional|Bahan\s+Baku)/i', $notes, $matches)) {
            $label = strtoupper(str_replace(' ', '_', trim((string)$matches[1])));
            return in_array($label, ['KEBUTUHAN_OPERASIONAL', 'OPERASIONAL'], true) ? 'Kebutuhan Operasional' : 'Persediaan Produksi';
        }
        return 'Persediaan Produksi';
    }
}
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Timeline Status</h6>
                <ul class="po-timeline">
                    <?php foreach ($timeline as $step): ?>
                        <?php
                            $code = (string)($step['code'] ?? '');
                            $rank = $statusRank[$code] ?? -1;
                            $stateClass = 'is-pending';
                            if ($isTerminal) {
                                $stateClass = $code === 'DRAFT' ? 'is-done' : 'is-pending';
                            } else {
                                if ($rank > -1 && $rank < $currentRank) {
                                    $stateClass = 'is-done';
                                } elseif ($rank > -1 && $rank === $currentRank) {
                                    $stateClass = 'is-current';
                                }
                            }
                        ?>
                        <li class="po-timeline-item <?php echo $stateClass; ?>">
                            <span class="po-timeline-dot"></span>
                            <div class="fw-semibold"><?php echo html_escape((string)$step['label']); ?></div>
                            <small class="text-muted"><?php echo !empty($step['at']) ? html_escape((string)$step['at']) : '-'; ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($isTerminal): ?>
                        <li class="po-timeline-item is-current">
                            <span class="po-timeline-dot"></span>
                            <div class="fw-semibold"><?php echo $currentStatus === 'REJECTED' ? 'PO ditolak' : 'PO dibatalkan'; ?></div>
                            <small class="text-muted"><?php echo html_escape((string)($order['updated_at'] ?? '-')); ?></small>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">Ringkasan Header</h6>
                <div class="row g-2">
                    <div class="col-md-6"><small class="text-muted d-block">Tanggal PO</small><strong><?php echo html_escape((string)($order['request_date'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Tipe Purchase</small><strong><?php echo html_escape((string)($order['purchase_type_name'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Vendor</small><strong><?php echo html_escape((string)($order['vendor_name'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Tujuan</small><strong><?php echo html_escape((string)($order['destination_type'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Metode Bayar</small><strong><?php echo html_escape(trim((string)($order['payment_account_code'] ?? '-') . ' ' . (string)($order['payment_account_name'] ?? ''))); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Pembayaran Tercatat</small><strong>Rp <?php echo number_format((float)($payments['total_paid'] ?? 0), 2, ',', '.'); ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="mb-2">Line Purchase Order</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Snapshot</th>
                                <th>Stok sekarang<br><small>Satuan isi</small></th>
                                <th>Merk</th>
                                <th>Keterangan</th>
                                <th>Pemakaian</th>
                                <th class="text-end">Qty Beli</th>
                                <th>UOM</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lines)): ?>
                                <tr><td colspan="10" class="text-center text-muted py-3">Tidak ada line PO.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lines as $ln): ?>
                                    <?php
                                        $lineName = trim((string)($ln['snapshot_item_name'] ?? ''));
                                        if ($lineName === '') {
                                            $lineName = trim((string)($ln['snapshot_material_name'] ?? '-'));
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo (int)($ln['line_no'] ?? 0); ?></td>
                                        <td><?php echo html_escape($lineName); ?></td>
                                        <td><?php $this->load->view('procurement/_current_stock', ['stock_line'=>$ln,'stock_warehouse_only'=>in_array($order['destination_type'] ?? '',['GUDANG','WAREHOUSE'],true)]); ?></td>
                                        <td><?php echo html_escape((string)($ln['snapshot_brand_name'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($ln['snapshot_line_description'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($ln['usage_purpose'] ?? '') !== '' ? (((string)$ln['usage_purpose'] === 'OPERASIONAL') ? 'Kebutuhan Operasional' : 'Persediaan Produksi') : finance_po_usage_purpose_from_notes((string)($ln['notes'] ?? ''))); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($ln['qty_buy'] ?? 0), 2, ',', '.'); ?></td>
                                        <td><?php echo html_escape((string)($ln['snapshot_buy_uom_code'] ?? '-')); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($ln['unit_price'] ?? 0), 2, ',', '.'); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($ln['line_subtotal'] ?? 0), 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0">Receipt & Lot</h6>
                        <small class="text-muted">Drill-down per receipt untuk melihat lot inbound yang terbentuk saat barang diterima.</small>
                    </div>
                    <span class="badge bg-light text-dark"><?php echo (int)($receipts['receipt_count'] ?? 0); ?> receipt</span>
                </div>
                <?php if (empty($receiptRows)): ?>
                    <div class="text-muted small">Belum ada receipt yang tercatat untuk PO ini.</div>
                <?php else: ?>
                    <div class="accordion" id="poReceiptAccordion">
                        <?php foreach ($receiptRows as $receiptIndex => $receiptRow): ?>
                            <?php $collapseId = 'po-receipt-' . (int)($receiptRow['id'] ?? 0); ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="<?php echo $collapseId; ?>-header">
                                    <button class="accordion-button <?php echo $receiptIndex === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="<?php echo $receiptIndex === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapseId; ?>">
                                        <span class="me-3"><strong><?php echo html_escape((string)($receiptRow['receipt_no'] ?? '-')); ?></strong></span>
                                        <span class="me-3 small text-muted">Tgl <?php echo html_escape((string)($receiptRow['receipt_date'] ?? '-')); ?></span>
                                        <span class="me-3 small text-muted">Tujuan <?php echo html_escape((string)($receiptRow['destination_type'] ?? '-')); ?><?php echo !empty($receiptRow['destination_division_name']) ? ' / ' . html_escape((string)$receiptRow['destination_division_name']) : ''; ?></span>
                                        <span class="small text-muted">Line <?php echo (int)($receiptRow['line_count'] ?? 0); ?></span>
                                    </button>
                                </h2>
                                <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $receiptIndex === 0 ? 'show' : ''; ?>" aria-labelledby="<?php echo $collapseId; ?>-header" data-bs-parent="#poReceiptAccordion">
                                    <div class="accordion-body">
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-3"><small class="text-muted d-block">Status</small><strong><?php echo html_escape((string)($receiptRow['status'] ?? '-')); ?></strong></div>
                                            <div class="col-md-3"><small class="text-muted d-block">Qty Beli Total</small><strong><?php echo number_format((float)($receiptRow['qty_buy_total'] ?? 0), 2, ',', '.'); ?></strong></div>
                                            <div class="col-md-3"><small class="text-muted d-block">Qty Isi Total</small><strong><?php echo number_format((float)($receiptRow['qty_content_total'] ?? 0), 2, ',', '.'); ?></strong></div>
                                            <div class="col-md-3"><small class="text-muted d-block">Posted At</small><strong><?php echo html_escape((string)($receiptRow['posted_at'] ?? $receiptRow['created_at'] ?? '-')); ?></strong></div>
                                            <?php if (!empty($receiptRow['notes'])): ?>
                                                <div class="col-12"><small class="text-muted d-block">Catatan</small><strong><?php echo html_escape((string)$receiptRow['notes']); ?></strong></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle po-lot-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Line</th>
                                                        <th>Profile</th>
                                                        <th class="text-end">Qty Receipt</th>
                                                        <th>Lot Inbound</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $receiptLines = (array)($receiptRow['lines'] ?? []); ?>
                                                    <?php if (empty($receiptLines)): ?>
                                                        <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada line receipt.</td></tr>
                                                    <?php else: ?>
                                                        <?php foreach ($receiptLines as $receiptLine): ?>
                                                            <?php
                                                                $profileName = trim((string)($receiptLine['item_name'] ?? ''));
                                                                if ($profileName === '') {
                                                                    $profileName = trim((string)($receiptLine['material_name'] ?? '-'));
                                                                }
                                                                $lotRows = (array)($receiptLine['lot_rows'] ?? []);
                                                            ?>
                                                            <tr>
                                                                <td>#<?php echo (int)($receiptLine['po_line_no'] ?? 0); ?></td>
                                                                <td>
                                                                    <strong><?php echo html_escape($profileName); ?></strong>
                                                                    <div class="small text-muted"><?php echo html_escape((string)($receiptLine['brand_name'] ?? '-')); ?></div>
                                                                    <div class="small text-muted"><?php echo html_escape((string)($receiptLine['line_description'] ?? '-')); ?></div>
                                                                </td>
                                                                <td class="text-end">
                                                                    <div><?php echo number_format((float)($receiptLine['qty_buy_received'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($receiptLine['buy_uom_code'] ?? '-')); ?></div>
                                                                    <div class="small text-muted"><?php echo number_format((float)($receiptLine['qty_content_received'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($receiptLine['content_uom_code'] ?? '-')); ?></div>
                                                                </td>
                                                                <td>
                                                                    <?php if (empty($lotRows)): ?>
                                                                        <span class="text-muted small">Lot belum tersedia.</span>
                                                                    <?php else: ?>
                                                                        <?php foreach ($lotRows as $lotRow): ?>
                                                                            <div class="border rounded p-2 mb-2">
                                                                                <div><strong><?php echo html_escape((string)($lotRow['lot_no'] ?? '-')); ?></strong></div>
                                                                                <div class="small text-muted">Receipt date: <?php echo html_escape((string)($lotRow['receipt_date'] ?? '-')); ?></div>
                                                                                <div class="small text-muted">Inbound audit | Before 0,00 | Delta +<?php echo number_format((float)($lotRow['qty_in'] ?? 0), 2, ',', '.'); ?> | After <?php echo number_format((float)($lotRow['qty_in'] ?? 0), 2, ',', '.'); ?></div>
                                                                                <div class="small text-muted">Saldo live <?php echo number_format((float)($lotRow['qty_balance'] ?? 0), 2, ',', '.'); ?></div>
                                                                                <div class="small text-muted">Unit cost Rp <?php echo number_format((float)($lotRow['unit_cost'] ?? 0), 2, ',', '.'); ?><?php echo !empty($lotRow['expiry_date']) ? ' | Exp ' . html_escape((string)$lotRow['expiry_date']) : ''; ?></div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($txnRows) || !empty($auditRows)): ?>
<div class="card mt-3">
    <div class="card-body">
        <h6 class="mb-2">Audit Ringkas</h6>
        <small class="text-muted d-block mb-2">Sumber utama: pur_purchase_txn_log. Jika data lama belum termigrasi, fallback dari aud_transaction_log.</small>
        <div class="table-responsive">
            <table class="table table-sm align-middle fin-audit-table">
                <thead>
                    <tr>
                        <th class="col-date">Waktu</th>
                        <th class="col-type">Aksi</th>
                        <th class="col-balance">Status Before</th>
                        <th class="col-balance">Status After</th>
                        <th class="col-ref">Ref</th>
                        <th class="text-end col-amount">Amount</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($txnRows)): ?>
                        <?php foreach ($txnRows as $tx): ?>
                            <?php
                                $statusBefore = strtoupper(trim((string)($tx['status_before'] ?? '')));
                                $statusAfter = strtoupper(trim((string)($tx['status_after'] ?? '')));
                            ?>
                            <tr>
                                <td class="col-date"><?php echo html_escape((string)($tx['created_at'] ?? '-')); ?></td>
                                <td class="col-type"><?php echo html_escape((string)($tx['action_code'] ?? '-')); ?></td>
                                <td class="col-balance"><?php echo html_escape($statusBefore !== '' ? $statusBefore : '-'); ?></td>
                                <td class="col-balance"><?php echo html_escape($statusAfter !== '' ? $statusAfter : '-'); ?></td>
                                <td class="col-ref"><?php echo html_escape((string)($tx['transaction_no'] ?? '-')); ?></td>
                                <td class="text-end col-amount"><?php echo $tx['amount'] !== null ? number_format((float)$tx['amount'], 2, ',', '.') : '-'; ?></td>
                                <td><?php echo html_escape((string)($tx['notes'] ?? '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($auditRows as $ar): ?>
                            <tr>
                                <td class="col-date"><?php echo html_escape((string)($ar['created_at'] ?? '-')); ?></td>
                                <td class="col-type"><?php echo html_escape((string)($ar['action_code'] ?? '-')); ?></td>
                                <td class="col-balance">-</td>
                                <td class="col-balance">-</td>
                                <td class="col-ref"><?php echo html_escape((string)($ar['transaction_no'] ?? '-')); ?></td>
                                <td class="text-end col-amount">-</td>
                                <td><?php echo html_escape((string)($ar['notes'] ?? '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    function openReceiptHashTarget() {
        var hash = String(window.location.hash || '').trim();
        if (!hash || hash.indexOf('#po-receipt-') !== 0) {
            return;
        }

        var target = document.querySelector(hash);
        if (!target) {
            return;
        }

        var toggleBtn = document.querySelector('[data-bs-target="' + hash + '"]');
        if (toggleBtn && window.bootstrap && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: false }).show();
        } else {
            target.classList.add('show');
        }

        window.setTimeout(function () {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 120);
    }

    function parseJsonResponse(response) {
        return response.text().then(function (txt) {
            var parsed = null;
            try {
                parsed = JSON.parse(txt);
            } catch (e) {
                var plainText = String(txt || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                var messageMatch = plainText.match(/Message:\s*([^\r\n]+)/i);
                throw new Error((messageMatch && messageMatch[1]) ? messageMatch[1].trim() : 'Server gagal memproses perubahan status. Muat ulang halaman lalu coba lagi.');
            }
            return { status: response.status, json: parsed };
        });
    }

    openReceiptHashTarget();

    var updateBtn = document.getElementById('btn-po-detail-status-update');
    var statusEl = document.getElementById('po-detail-status-next');
    if (!updateBtn || !statusEl) {
        return;
    }

    var canEditPo = <?php echo $canEditPo ? 'true' : 'false'; ?>;
    var endpoint = <?php echo json_encode(site_url('purchase/order/status-update')); ?>;
    var poId = <?php echo (int)($order['id'] ?? 0); ?>;
    var reconcileBtn = document.getElementById('btn-po-detail-impact-reconcile');

    function setButtonBusy(button, label) {
        if (!button) {
            return;
        }
        if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
            window.FinanceUI.setButtonLoading(button, label);
            return;
        }
        button.disabled = true;
    }

    function clearButtonBusy(button) {
        if (!button) {
            return;
        }
        if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
            window.FinanceUI.clearButtonLoading(button);
            return;
        }
        button.disabled = false;
    }

    updateBtn.addEventListener('click', function () {
        if (!canEditPo) {
            window.alert('Akses edit status tidak tersedia untuk akun ini.');
            return;
        }

        var nextStatus = String(statusEl.value || '').trim().toUpperCase();
        if (!poId || !nextStatus) {
            return;
        }

        setButtonBusy(updateBtn, 'Memproses...');

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Purchase-Mutation-CSRF': <?php echo json_encode($purchaseMutationCsrfToken); ?> },
            body: JSON.stringify({ purchase_order_id: poId, status: nextStatus })
        })
        .then(parseJsonResponse)
        .then(function (res) {
            if (res.status >= 400 || !res.json || !res.json.ok) {
                throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal update status');
            }
            window.location.reload();
        })
        .catch(function (err) {
            clearButtonBusy(updateBtn);
            window.alert(err.message || 'Gagal update status');
        });
    });

    if (reconcileBtn) {
        reconcileBtn.addEventListener('click', function () {
            if (!canEditPo) {
                window.alert('Akses edit status tidak tersedia untuk akun ini.');
                return;
            }
            if (!poId) {
                return;
            }

            setButtonBusy(reconcileBtn, 'Sinkron...');

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Purchase-Mutation-CSRF': <?php echo json_encode($purchaseMutationCsrfToken); ?> },
                body: JSON.stringify({ purchase_order_id: poId, status: String(reconcileBtn.getAttribute('data-status') || '').toUpperCase() })
            })
            .then(parseJsonResponse)
            .then(function (res) {
                if (res.status >= 400 || !res.json || !res.json.ok) {
                    throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal sinkronkan dampak transaksi');
                }
                window.location.reload();
            })
            .catch(function (err) {
                clearButtonBusy(reconcileBtn);
                window.alert(err.message || 'Gagal sinkronkan dampak transaksi');
            });
        });
    }
})();
</script>
