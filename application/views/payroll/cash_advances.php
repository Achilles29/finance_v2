<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$statusOptions = $status_options ?? ['DRAFT', 'APPROVED', 'REJECTED', 'SETTLED', 'VOID'];
$employeeOptions = $employee_options ?? [];
$companyAccountOptions = $company_account_options ?? [];
$tab = $tab ?? 'transaction';
$recapRows = $recap_rows ?? [];
$recapEmployeeId = (int)($recap_employee_id ?? 0);
$recapEmployeeHistory = $recap_employee_history ?? [];
$editRow = $edit_row ?? null;
$isEdit = !empty($editRow);
$transactionBaseUrl = site_url('payroll/cash-advances?tab=transaction');
$currentPerPage = max(1, (int)($pg['per_page'] ?? 25));
$perPageOptions = [25, 50, 100];
$baseFilters = [
    'tab' => $tab,
    'q' => (string)($filters['q'] ?? ''),
    'employee_id' => (int)($filters['employee_id'] ?? 0),
    'date_start' => (string)($filters['date_start'] ?? ''),
    'date_end' => (string)($filters['date_end'] ?? ''),
    'per_page' => $currentPerPage,
];
if ($tab === 'transaction') {
    $baseFilters['status'] = (string)($filters['status'] ?? '');
}
if ($tab === 'recap' && $recapEmployeeId > 0) {
    $baseFilters['recap_employee_id'] = $recapEmployeeId;
}
$buildCashAdvanceUrl = static function (array $params) {
    return site_url('payroll/cash-advances') . '?' . http_build_query($params);
};
$renderPager = static function (array $meta, array $query, callable $urlBuilder): string {
    $page = max(1, (int)($meta['page'] ?? 1));
    $totalPages = max(1, (int)($meta['total_pages'] ?? 1));
    $total = max(0, (int)($meta['total'] ?? 0));
    $limit = max(1, (int)($meta['per_page'] ?? 25));
    $start = $total > 0 ? (($page - 1) * $limit) + 1 : 0;
    $end = $total > 0 ? min($total, $page * $limit) : 0;
    $firstPage = max(1, $page - 2);
    $lastPage = min($totalPages, $page + 2);

    ob_start();
    ?>
    <div class="ca-pager d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
      <div class="small text-muted">
        <?php if ($total > 0): ?>
          Menampilkan <?php echo number_format($start); ?>-<?php echo number_format($end); ?> dari <?php echo number_format($total); ?> data
        <?php else: ?>
          Belum ada data
        <?php endif; ?>
      </div>
      <nav aria-label="Pagination kasbon">
        <ul class="pagination pagination-sm mb-0">
          <?php
            $prevQuery = $query;
            $prevQuery['page'] = max(1, $page - 1);
          ?>
          <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $page <= 1 ? '#' : $urlBuilder($prevQuery); ?>">Prev</a>
          </li>
          <?php for ($i = $firstPage; $i <= $lastPage; $i++): ?>
            <?php $pageQuery = $query; $pageQuery['page'] = $i; ?>
            <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
              <a class="page-link" href="<?php echo $urlBuilder($pageQuery); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
          <?php
            $nextQuery = $query;
            $nextQuery['page'] = min($totalPages, $page + 1);
          ?>
          <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : $urlBuilder($nextQuery); ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
    <?php
    return (string)ob_get_clean();
};
$selectedRecapRow = null;
if ($tab === 'recap' && $recapEmployeeId > 0) {
    foreach ((array)$recapRows as $candidate) {
        if ((int)($candidate['employee_id'] ?? 0) === $recapEmployeeId) {
            $selectedRecapRow = $candidate;
            break;
        }
    }
}
?>

<style>
  .ca-page-head small { max-width: 760px; display: inline-block; }
  .ca-filter-card, .ca-table-card, .ca-history-card, .ca-summary-card { border: 1px solid rgba(122, 24, 36, .08); box-shadow: 0 16px 34px rgba(85, 55, 35, .06); border-radius: 22px; overflow: hidden; }
  .ca-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
  .ca-summary-pill { background: linear-gradient(180deg, #fff, #fff8f5); border: 1px solid rgba(122, 24, 36, .08); border-radius: 18px; padding: 1rem 1.1rem; }
  .ca-summary-pill .label { font-size: .78rem; color: #8a7a6c; text-transform: uppercase; letter-spacing: .04em; }
  .ca-summary-pill .value { font-size: 1.2rem; font-weight: 700; color: #5a1720; }
  .ca-summary-pill .subvalue { font-size: .85rem; color: #7c6c5c; }
  .ca-table-card thead th { background: linear-gradient(135deg, #b11226, #8f1021); color: #fff; text-transform: uppercase; letter-spacing: .03em; font-size: .82rem; border: 0; }
  .ca-table-card tbody tr td { vertical-align: middle; }
  .ca-doc-title { font-weight: 700; color: #4c1720; }
  .ca-doc-sub { color: #8a7a6c; font-size: .85rem; }
  .ca-money-main { font-weight: 700; color: #4a1820; }
  .ca-money-outstanding { font-weight: 700; color: #b11226; }
  .ca-status-badge { display:inline-flex; align-items:center; justify-content:center; padding:.35rem .7rem; border-radius:999px; font-size:.76rem; font-weight:700; letter-spacing:.02em; }
  .ca-status-badge.approved { background:#e7f9ef; color:#11804a; }
  .ca-status-badge.settled { background:#e7efff; color:#2857c5; }
  .ca-status-badge.rejected { background:#ffe8e8; color:#c62828; }
  .ca-status-badge.void { background:#f3f4f6; color:#6b7280; }
  .ca-status-badge.draft { background:#fff4de; color:#b26a00; }
  .action-icon-btn {
    width: 42px;
    height: 42px;
    padding: 0 !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.15rem;
  }
  .ca-pager .pagination .page-link { border-radius: 10px; margin-left: .2rem; border-color: rgba(122,24,36,.12); color: #7a1824; }
  .ca-pager .pagination .page-item.active .page-link { background: #8f1021; border-color: #8f1021; }
  .ca-history-card .card-header, .ca-summary-card .card-header { background: linear-gradient(180deg, #fff, #fff8f5); border-bottom: 1px solid rgba(122,24,36,.08); }
  @media (max-width: 991.98px) { .ca-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 575.98px) { .ca-summary-grid { grid-template-columns: 1fr; } }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 ca-page-head">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Kasbon Pegawai'); ?></h4>
    <small class="text-muted">Tenor bersifat opsional. Isi `0` jika kasbon tanpa tenor tetap (fleksibel sesuai pembayaran aktual).</small>
  </div>
  <?php if ($tab === 'transaction'): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cashAdvanceModal">
      <i class="ri ri-add-line me-1"></i>Tambah Kasbon
    </button>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-12">
    <?php
    $workspace_tabs = [
      ['label' => 'Transaksi Kasbon', 'url' => site_url('payroll/cash-advances?tab=transaction'), 'active' => $tab === 'transaction'],
      ['label' => 'Rekap Pegawai', 'url' => site_url('payroll/cash-advances?tab=recap'), 'active' => $tab === 'recap'],
    ];
    $workspace_tab_label = 'Kasbon';
    $workspace_tab_aria_label = 'Pilih tampilan kasbon';
    $this->load->view('layout/_workspace_tabs', get_defined_vars());
    ?>

    <div class="card mb-3 ca-filter-card">
      <div class="card-body">
        <form method="get" action="<?php echo site_url('payroll/cash-advances'); ?>" class="row g-2 align-items-end">
          <input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
          <?php if ($tab === 'recap' && $recapEmployeeId > 0): ?>
            <input type="hidden" name="recap_employee_id" value="<?php echo (int)$recapEmployeeId; ?>">
          <?php endif; ?>
          <div class="col-md-3">
            <label class="form-label mb-1">Cari</label>
            <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No/Nama/NIP">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Pegawai</label>
            <select name="employee_id" class="form-select">
              <option value="">Semua</option>
              <?php foreach($employeeOptions as $o): ?>
                <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($tab === 'transaction'): ?>
          <div class="col-md-2">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select">
              <option value="">Semua</option>
              <?php foreach($statusOptions as $s): ?>
                <option value="<?php echo html_escape($s); ?>" <?php echo (($filters['status'] ?? '') === $s) ? 'selected' : ''; ?>><?php echo html_escape($s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-2">
            <label class="form-label mb-1">Dari</label>
            <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Sampai</label>
            <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Baris</label>
            <select name="per_page" class="form-select">
              <?php foreach ($perPageOptions as $option): ?>
                <option value="<?php echo $option; ?>" <?php echo $currentPerPage === $option ? 'selected' : ''; ?>><?php echo $option; ?> / halaman</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a href="<?php echo site_url('payroll/cash-advances?tab=' . $tab); ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <?php if ($tab === 'recap'): ?>
      <?php if ($selectedRecapRow): ?>
      <div class="card ca-summary-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <strong><?php echo html_escape((string)($selectedRecapRow['employee_name'] ?? '-')); ?></strong>
            <div class="small text-muted"><?php echo html_escape((string)($selectedRecapRow['employee_code'] ?? '')); ?><?php echo !empty($selectedRecapRow['division_name']) ? ' | ' . html_escape((string)$selectedRecapRow['division_name']) : ''; ?></div>
          </div>
          <a href="<?php echo $buildCashAdvanceUrl(array_diff_key($baseFilters, ['recap_employee_id' => true])); ?>" class="btn btn-sm btn-outline-secondary">Tutup Detail Pegawai</a>
        </div>
        <div class="card-body">
          <div class="ca-summary-grid">
            <div class="ca-summary-pill">
              <div class="label">Total Kasbon</div>
              <div class="value"><?php echo number_format((float)($selectedRecapRow['total_amount'] ?? 0), 2, ',', '.'); ?></div>
              <div class="subvalue"><?php echo (int)($selectedRecapRow['doc_count'] ?? 0); ?> dokumen aktif/terhitung</div>
            </div>
            <div class="ca-summary-pill">
              <div class="label">Sudah Terbayar</div>
              <div class="value text-success"><?php echo number_format((float)($selectedRecapRow['paid_total'] ?? 0), 2, ',', '.'); ?></div>
              <div class="subvalue">Akumulasi pembayaran yang sudah masuk</div>
            </div>
            <div class="ca-summary-pill">
              <div class="label">Sisa Kasbon</div>
              <div class="value text-danger"><?php echo number_format((float)($selectedRecapRow['outstanding_total'] ?? 0), 2, ',', '.'); ?></div>
              <div class="subvalue">Nilai yang masih harus dilunasi</div>
            </div>
            <div class="ca-summary-pill">
              <div class="label">Rasio Pelunasan</div>
              <?php
                $recapTotalAmount = (float)($selectedRecapRow['total_amount'] ?? 0);
                $recapPaidAmount = (float)($selectedRecapRow['paid_total'] ?? 0);
                $paidRatio = $recapTotalAmount > 0 ? round(($recapPaidAmount / $recapTotalAmount) * 100, 1) : 0;
              ?>
              <div class="value"><?php echo number_format($paidRatio, 1, ',', '.'); ?>%</div>
              <div class="subvalue">Progress pelunasan kasbon pegawai</div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card ca-table-card">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Pegawai</th><th>Divisi</th><th class="text-end">Total Kasbon</th><th class="text-end">Terbayar</th><th class="text-end">Sisa</th><th class="text-end">Dokumen</th><th class="text-center">Detail</th></tr></thead>
            <tbody>
            <?php if (empty($recapRows)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada rekap kasbon.</td></tr>
            <?php else: foreach($recapRows as $r): ?>
              <tr>
                <td><div class="ca-doc-title"><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div><div class="ca-doc-sub"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></div></td>
                <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
                <td class="text-end ca-money-main"><?php echo number_format((float)($r['total_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-success"><?php echo number_format((float)($r['paid_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end ca-money-outstanding"><?php echo number_format((float)($r['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo (int)($r['doc_count'] ?? 0); ?></td>
                <td class="text-center"><a href="<?php echo site_url('payroll/cash-advances?tab=recap&recap_employee_id=' . (int)($r['employee_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Lihat Detail"><i class="ri ri-eye-line"></i></a></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body pt-0">
          <?php echo $renderPager($pg, $baseFilters, $buildCashAdvanceUrl); ?>
        </div>
      </div>

      <?php if ($recapEmployeeId > 0): ?>
      <div class="card mt-3 ca-history-card">
        <div class="card-header"><strong>Detail Histori Kasbon Pegawai</strong></div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>No Kasbon</th><th>Tanggal</th><th>Status</th><th class="text-end">Nominal</th><th class="text-end">Terbayar</th><th class="text-end">Sisa</th><th class="text-end">Tenor</th><th>Cicilan</th></tr></thead>
            <tbody>
            <?php if (empty($recapEmployeeHistory)): ?>
              <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada histori kasbon pegawai ini.</td></tr>
            <?php else: foreach($recapEmployeeHistory as $h): ?>
              <tr>
                <td><div class="ca-doc-title"><?php echo html_escape((string)($h['advance_no'] ?? '-')); ?></div></td>
                <td><?php echo html_escape((string)($h['request_date'] ?? '-')); ?></td>
                <td>
                  <?php $historyStatus = strtolower((string)($h['status'] ?? 'draft')); ?>
                  <span class="ca-status-badge <?php echo html_escape($historyStatus); ?>"><?php echo html_escape((string)($h['status'] ?? '-')); ?></span>
                </td>
                <td class="text-end ca-money-main"><?php echo number_format((float)($h['amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-success"><?php echo number_format((float)($h['installment_paid_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end ca-money-outstanding"><?php echo number_format((float)($h['outstanding_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo (int)($h['tenor_month'] ?? 0); ?></td>
                <td class="small text-muted">
                  <?php
                    $parts = [];
                    foreach ((array)($h['installments'] ?? []) as $it) {
                        $parts[] = (string)$it['due_period'] . ' [' . number_format((float)$it['paid_amount'], 0, ',', '.') . '/' . number_format((float)$it['plan_amount'], 0, ',', '.') . ']';
                    }
                    echo !empty($parts) ? html_escape(implode(' | ', $parts)) : '-';
                  ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="card ca-table-card">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>No</th>
                <th>Pegawai</th>
                <th>Status</th>
                <th class="text-end">Nominal</th>
                <th class="text-end">Outstanding</th>
                <th class="text-end">Rencana/Bln</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data kasbon.</td></tr>
              <?php else: ?>
                <?php foreach($rows as $r): ?>
                  <?php $status = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
                  <?php
                    $canPayInstallment = $status === 'APPROVED';
                    $canVoidSettled = $status === 'SETTLED'
                      && (float)($r['installment_paid_total'] ?? 0) <= 0.0001
                      && (int)($r['account_mutation_count'] ?? 0) === 0;
                    $detailPayload = [
                      'id' => (int)($r['id'] ?? 0),
                      'advance_no' => (string)($r['advance_no'] ?? '-'),
                      'employee_name' => (string)($r['employee_name'] ?? '-'),
                      'employee_code' => (string)($r['employee_code'] ?? ''),
                      'status' => $status,
                      'request_date' => (string)($r['request_date'] ?? '-'),
                      'approved_date' => (string)($r['approved_date'] ?? ''),
                      'amount' => (float)($r['amount'] ?? 0),
                      'outstanding_amount' => (float)($r['outstanding_amount'] ?? 0),
                      'monthly_deduction_plan' => (float)($r['monthly_deduction_plan'] ?? 0),
                      'installments' => array_values((array)($r['installments'] ?? [])),
                    ];
                  ?>
                  <tr>
                    <td>
                      <div class="ca-doc-title"><?php echo html_escape((string)($r['advance_no'] ?? '-')); ?></div>
                      <div class="ca-doc-sub"><?php echo html_escape((string)($r['request_date'] ?? '-')); ?></div>
                    </td>
                    <td>
                      <div class="ca-doc-title"><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div>
                      <div class="ca-doc-sub"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></div>
                    </td>
                    <td><span class="ca-status-badge <?php echo html_escape(strtolower($status)); ?>"><?php echo html_escape((string)($r['status'] ?? 'DRAFT')); ?></span></td>
                    <td class="text-end ca-money-main"><?php echo number_format((float)($r['amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end ca-money-outstanding"><?php echo number_format((float)($r['outstanding_amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end"><?php echo number_format((float)($r['monthly_deduction_plan'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="action-cell text-center">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-info action-icon-btn js-ca-detail-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#cashAdvanceDetailModal"
                        data-row="<?php echo html_escape(json_encode($detailPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
                        data-can-pay="<?php echo $canPayInstallment ? '1' : '0'; ?>"
                        data-can-void-test="<?php echo $canVoidSettled ? '1' : '0'; ?>"
                        title="Detail Kasbon"
                        aria-label="Detail Kasbon"
                      ><i class="ri ri-eye-line"></i></button>
                      <?php if ($status === 'SETTLED' && $canVoidSettled): ?>
                        <form method="post" action="<?php echo site_url('payroll/cash-advances/void/' . (int)$r['id']); ?>" class="d-inline" data-confirm="VOID kasbon test ini? Tidak ada mutasi uang yang akan dibalik.">
                          <input type="hidden" name="notes" value="Void data test settled tanpa mutasi uang">
                          <button type="submit" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Void data test" data-loading-label="Void..."><i class="ri ri-close-circle-line"></i></button>
                        </form>
                      <?php elseif ($status !== 'SETTLED'): ?>
                        <a href="<?php echo site_url('payroll/cash-advances?tab=transaction&edit_id=' . (int)$r['id']); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit"><i class="ri ri-edit-line"></i></a>
                        <form method="post" action="<?php echo site_url('payroll/cash-advances/void/' . (int)$r['id']); ?>" class="d-inline" data-confirm="VOID kasbon ini?">
                          <button type="submit" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Void" data-loading-label="Void..."><i class="ri ri-close-circle-line"></i></button>
                        </form>
                        <form method="post" action="<?php echo site_url('payroll/cash-advances/delete/' . (int)$r['id']); ?>" class="d-inline" data-confirm="Hapus kasbon ini?">
                          <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" data-loading-label="Hapus..."><i class="ri ri-delete-bin-line"></i></button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body pt-0">
          <?php echo $renderPager($pg, $baseFilters, $buildCashAdvanceUrl); ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="cashAdvanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><?php echo $isEdit ? 'Edit Kasbon' : 'Tambah Kasbon'; ?></h5>
          <small class="text-muted">Isi data kasbon, lalu simpan. Halaman utama tetap fokus ke daftar transaksi.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?php echo $isEdit ? site_url('payroll/cash-advances/update/' . (int)$editRow['id']) : site_url('payroll/cash-advances/store'); ?>" class="row g-3" id="cashAdvanceForm">
          <div class="col-12">
            <label class="form-label mb-1">Pegawai</label>
            <select name="employee_id" class="form-select" required>
              <option value="">Pilih pegawai</option>
              <?php foreach($employeeOptions as $o): ?>
                <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($editRow['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Tanggal Request</label>
            <input type="date" name="request_date" class="form-control" required value="<?php echo html_escape((string)($editRow['request_date'] ?? date('Y-m-d'))); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Tanggal Approved</label>
            <input type="date" name="approved_date" class="form-control" value="<?php echo html_escape((string)($editRow['approved_date'] ?? '')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Nominal Kasbon</label>
            <input type="number" step="0.01" min="0" name="amount" class="form-control" required value="<?php echo html_escape((string)($editRow['amount'] ?? '')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Tenor (bulan)</label>
            <input type="number" min="0" max="60" name="tenor_month" class="form-control" value="<?php echo html_escape((string)($editRow['tenor_month'] ?? 0)); ?>">
            <small class="text-muted">`0` berarti fleksibel, tanpa jadwal cicilan tetap.</small>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1">Rekening Sumber Kasbon</label>
            <select name="company_account_id" class="form-select">
              <option value="">Pilih rekening (opsional)</option>
              <?php foreach($companyAccountOptions as $a): ?>
                <option value="<?php echo (int)$a['value']; ?>" <?php echo ((int)($editRow['company_account_id'] ?? 0) === (int)$a['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$a['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select" required>
              <?php foreach($statusOptions as $s): ?>
                <option value="<?php echo html_escape($s); ?>" <?php echo (strtoupper((string)($editRow['status'] ?? 'DRAFT')) === $s) ? 'selected' : ''; ?>><?php echo html_escape($s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea name="notes" class="form-control" rows="3"><?php echo html_escape((string)($editRow['notes'] ?? '')); ?></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <?php if ($isEdit): ?>
          <a href="<?php echo $transactionBaseUrl; ?>" class="btn btn-outline-secondary">Batal</a>
        <?php else: ?>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <?php endif; ?>
        <button type="submit" form="cashAdvanceForm" class="btn btn-primary" data-loading-label="Menyimpan..."><?php echo $isEdit ? 'Update Kasbon' : 'Simpan Kasbon'; ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashAdvanceDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="cashAdvanceDetailTitle">Detail Kasbon</h5>
          <small class="text-muted" id="cashAdvanceDetailSubtitle">Ringkasan dokumen dan cicilan kasbon.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Status</div><div class="fw-semibold" id="caDetailStatus">-</div></div></div>
          <div class="col-md-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Nominal</div><div class="fw-semibold" id="caDetailAmount">-</div></div></div>
          <div class="col-md-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Outstanding</div><div class="fw-semibold" id="caDetailOutstanding">-</div></div></div>
          <div class="col-md-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Rencana / Bulan</div><div class="fw-semibold" id="caDetailPlan">-</div></div></div>
        </div>

        <div class="card border-0 bg-light mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
              <strong>Cicilan Kasbon</strong>
              <span class="small text-muted" id="caDetailInstallmentHint">Belum ada jadwal cicilan.</span>
            </div>
            <div id="caDetailInstallments" class="row g-2"></div>
          </div>
        </div>

        <div class="card border-0 bg-light" id="caDetailPaymentCard">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <strong>Posting Pembayaran</strong>
              <span class="small text-muted">Gunakan modal ini kalau ingin mencatat pembayaran tanpa memenuhi tabel utama.</span>
            </div>
            <div class="alert alert-warning py-2 px-3 mb-3 d-none" id="caDetailPaymentNotice"></div>
            <form method="post" action="" class="row g-3 js-ca-pay-form" id="cashAdvanceDetailPayForm">
              <div class="col-md-4">
                <label class="form-label mb-1">Cicilan</label>
                <select name="installment_no" class="form-select js-ca-installment-no">
                  <option value="0">Auto / fleksibel</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Metode Bayar</label>
                <select name="payment_method" class="form-select js-ca-method">
                  <option value="CASH">Cair Tunai</option>
                  <option value="TRANSFER">Transfer</option>
                  <option value="SALARY_CUT">Potong Gaji</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Rekening Sumber</label>
                <select name="company_account_id" class="form-select js-ca-account">
                  <option value="">Rek sumber (tunai/transfer)</option>
                  <?php foreach($companyAccountOptions as $a): ?>
                    <?php $optType = strtoupper((string)($a['account_type'] ?? '')); ?>
                    <option value="<?php echo (int)$a['value']; ?>" data-type="<?php echo html_escape($optType); ?>"><?php echo html_escape((string)$a['label']); ?><?php echo $optType !== '' ? ' [' . html_escape($optType) . ']' : ''; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Tanggal Bayar</label>
                <input type="date" name="payment_date" class="form-control js-ca-paydate" value="<?php echo date('Y-m-d'); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Periode Potong Dari</label>
                <input type="date" name="salary_cut_period_start" class="form-control js-ca-period-start" value="<?php echo date('Y-m-01'); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Periode Potong Sampai</label>
                <input type="date" name="salary_cut_period_end" class="form-control js-ca-period-end" value="<?php echo date('Y-m-t'); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Nominal</label>
                <input type="number" step="0.01" min="0" name="paid_amount" class="form-control" placeholder="Nominal" required>
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Ref Transfer</label>
                <input type="text" name="transfer_ref_no" class="form-control js-ca-ref" placeholder="Ref transfer">
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Catatan</label>
                <input type="text" name="notes" class="form-control" placeholder="Catatan">
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" form="cashAdvanceDetailPayForm" class="btn btn-success" id="caDetailSubmitBtn" data-loading-label="Posting...">Posting Pembayaran</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatIdr(value) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
  }

  var cashAdvanceModalEl = document.getElementById('cashAdvanceModal');
  if (cashAdvanceModalEl && <?php echo $isEdit ? 'true' : 'false'; ?> && typeof bootstrap !== 'undefined') {
    var initialModal = bootstrap.Modal.getOrCreateInstance(cashAdvanceModalEl);
    initialModal.show();
  }

  var detailModalEl = document.getElementById('cashAdvanceDetailModal');
  if (detailModalEl) {
    var detailTitleEl = document.getElementById('cashAdvanceDetailTitle');
    var detailSubtitleEl = document.getElementById('cashAdvanceDetailSubtitle');
    var detailStatusEl = document.getElementById('caDetailStatus');
    var detailAmountEl = document.getElementById('caDetailAmount');
    var detailOutstandingEl = document.getElementById('caDetailOutstanding');
    var detailPlanEl = document.getElementById('caDetailPlan');
    var detailInstallmentsEl = document.getElementById('caDetailInstallments');
    var detailInstallmentHintEl = document.getElementById('caDetailInstallmentHint');
    var detailPaymentCardEl = document.getElementById('caDetailPaymentCard');
    var detailPaymentNoticeEl = document.getElementById('caDetailPaymentNotice');
    var detailSubmitBtnEl = document.getElementById('caDetailSubmitBtn');
    var detailPayFormEl = document.getElementById('cashAdvanceDetailPayForm');
    var detailInstallmentSelectEl = detailPayFormEl ? detailPayFormEl.querySelector('.js-ca-installment-no') : null;

    document.querySelectorAll('.js-ca-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var raw = btn.getAttribute('data-row') || '{}';
        var row = {};
        try {
          row = JSON.parse(raw);
        } catch (err) {
          row = {};
        }
        var status = String(row.status || 'DRAFT').toUpperCase();
        var canPay = btn.getAttribute('data-can-pay') === '1';
        var canVoidTest = btn.getAttribute('data-can-void-test') === '1';
        var installments = Array.isArray(row.installments) ? row.installments : [];

        if (detailTitleEl) {
          detailTitleEl.textContent = row.advance_no ? ('Detail ' + row.advance_no) : 'Detail Kasbon';
        }
        if (detailSubtitleEl) {
          detailSubtitleEl.textContent = (row.employee_name || '-') + (row.employee_code ? (' | ' + row.employee_code) : '');
        }
        if (detailStatusEl) detailStatusEl.textContent = status || '-';
        if (detailAmountEl) detailAmountEl.textContent = formatIdr(row.amount || 0);
        if (detailOutstandingEl) detailOutstandingEl.textContent = formatIdr(row.outstanding_amount || 0);
        if (detailPlanEl) detailPlanEl.textContent = formatIdr(row.monthly_deduction_plan || 0);

        if (detailInstallmentsEl) {
          if (!installments.length) {
            detailInstallmentsEl.innerHTML = '<div class="col-12 text-muted small">Belum ada detail cicilan.</div>';
          } else {
            detailInstallmentsEl.innerHTML = installments.map(function (it) {
              var label = '#' + Number(it.installment_no || 0) + ' | ' + escapeHtml(it.due_period || '-');
              var value = formatIdr(it.paid_amount || 0) + ' / ' + formatIdr(it.plan_amount || 0);
              var itemStatus = escapeHtml((it.status || 'OPEN').toUpperCase());
              return '' +
                '<div class="col-md-6 col-xl-4">' +
                  '<div class="border rounded-3 p-3 h-100 bg-white">' +
                    '<div class="small text-muted mb-1">' + label + '</div>' +
                    '<div class="fw-semibold">' + value + '</div>' +
                    '<div class="small mt-1">Status: ' + itemStatus + '</div>' +
                  '</div>' +
                '</div>';
            }).join('');
          }
        }
        if (detailInstallmentHintEl) {
          detailInstallmentHintEl.textContent = installments.length
            ? ('Total ' + installments.length + ' jadwal cicilan tercatat.')
            : 'Belum ada jadwal cicilan.';
        }

        if (detailPayFormEl) {
          detailPayFormEl.action = '<?php echo site_url('payroll/cash-advances/pay-installment'); ?>/' + Number(row.id || 0);
        }
        if (detailInstallmentSelectEl) {
          var options = ['<option value="0">Auto / fleksibel</option>'];
          installments.forEach(function (it) {
            options.push('<option value="' + Number(it.installment_no || 0) + '">#' + Number(it.installment_no || 0) + ' (' + escapeHtml(it.due_period || '-') + ')</option>');
          });
          detailInstallmentSelectEl.innerHTML = options.join('');
        }

        if (detailPaymentNoticeEl) {
          detailPaymentNoticeEl.classList.add('d-none');
          detailPaymentNoticeEl.textContent = '';
        }
        if (detailPaymentCardEl) detailPaymentCardEl.classList.remove('d-none');
        if (detailSubmitBtnEl) detailSubmitBtnEl.classList.remove('d-none');

        if (!canPay) {
          if (detailPaymentCardEl) detailPaymentCardEl.classList.remove('d-none');
          if (detailPaymentNoticeEl) {
            detailPaymentNoticeEl.classList.remove('d-none');
            if (status === 'SETTLED') {
              detailPaymentNoticeEl.textContent = canVoidTest
                ? 'Dokumen ini berstatus SETTLED test tanpa mutasi uang. Tidak perlu dibayar lagi; jika salah input, gunakan VOID dari tabel utama.'
                : 'Dokumen ini sudah SETTLED atau lunas. Form pembayaran dinonaktifkan.';
            } else if (status === 'VOID') {
              detailPaymentNoticeEl.textContent = 'Dokumen ini sudah VOID, jadi form pembayaran dinonaktifkan.';
            } else if (status === 'REJECTED') {
              detailPaymentNoticeEl.textContent = 'Dokumen ini berstatus REJECTED, jadi belum bisa dibayarkan.';
            } else {
              detailPaymentNoticeEl.textContent = 'Dokumen ini belum APPROVED, jadi form pembayaran masih dinonaktifkan.';
            }
          }
          if (detailSubmitBtnEl) detailSubmitBtnEl.classList.add('d-none');
          if (detailPayFormEl) {
            detailPayFormEl.querySelectorAll('input, select, button').forEach(function (el) {
              el.disabled = true;
            });
          }
        } else if (detailPayFormEl) {
          detailPayFormEl.querySelectorAll('input, select, button').forEach(function (el) {
            el.disabled = false;
          });
        }
      });
    });
  }

  var forms = document.querySelectorAll('.js-ca-pay-form');
  forms.forEach(function (form) {
    var methodEl = form.querySelector('.js-ca-method');
    var accountEl = form.querySelector('.js-ca-account');
    var payDateEl = form.querySelector('.js-ca-paydate');
    var periodStartEl = form.querySelector('.js-ca-period-start');
    var periodEndEl = form.querySelector('.js-ca-period-end');
    var refEl = form.querySelector('.js-ca-ref');
    if (!methodEl || !accountEl || !payDateEl || !periodStartEl || !periodEndEl || !refEl) {
      return;
    }

    var resetAccountByType = function (expectedType) {
      if (!expectedType) {
        return;
      }
      var selected = accountEl.options[accountEl.selectedIndex];
      if (!selected || !selected.value) {
        return;
      }
      var selectedType = (selected.getAttribute('data-type') || '').toUpperCase();
      if (selectedType !== expectedType) {
        accountEl.value = '';
      }
    };

    var applyGuard = function () {
      var method = (methodEl.value || '').toUpperCase();
      var periodLabel = 'Periode gaji (potong gaji)';
      if (method === 'SALARY_CUT') {
        accountEl.value = '';
        accountEl.disabled = true;
        accountEl.required = false;
        payDateEl.disabled = true;
        payDateEl.required = false;
        periodStartEl.disabled = false;
        periodStartEl.required = true;
        periodStartEl.title = periodLabel;
        periodEndEl.disabled = false;
        periodEndEl.required = true;
        periodEndEl.title = periodLabel;
        refEl.disabled = true;
        refEl.required = false;
        refEl.value = '';
      } else if (method === 'TRANSFER') {
        accountEl.disabled = false;
        accountEl.required = true;
        payDateEl.disabled = false;
        payDateEl.required = true;
        periodStartEl.disabled = true;
        periodStartEl.required = false;
        periodEndEl.disabled = true;
        periodEndEl.required = false;
        refEl.disabled = false;
        refEl.required = true;
        resetAccountByType('BANK');
      } else {
        accountEl.disabled = false;
        accountEl.required = true;
        payDateEl.disabled = false;
        payDateEl.required = true;
        periodStartEl.disabled = true;
        periodStartEl.required = false;
        periodEndEl.disabled = true;
        periodEndEl.required = false;
        refEl.disabled = true;
        refEl.required = false;
        refEl.value = '';
        resetAccountByType('CASH');
      }
    };

    methodEl.addEventListener('change', applyGuard);
    periodStartEl.addEventListener('change', function () {
      if (!periodStartEl.value) {
        return;
      }
      var start = new Date(periodStartEl.value + 'T00:00:00');
      if (isNaN(start.getTime())) {
        return;
      }
      var end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
      var m = String(end.getMonth() + 1).padStart(2, '0');
      var d = String(end.getDate()).padStart(2, '0');
      periodEndEl.value = end.getFullYear() + '-' + m + '-' + d;
    });
    accountEl.addEventListener('change', function () {
      var method = (methodEl.value || '').toUpperCase();
      var selected = accountEl.options[accountEl.selectedIndex];
      var selectedType = (selected ? (selected.getAttribute('data-type') || '').toUpperCase() : '');
      if (method === 'CASH' && selected.value && selectedType !== 'CASH') {
        accountEl.value = '';
      }
      if (method === 'TRANSFER' && selected.value && selectedType !== 'BANK') {
        accountEl.value = '';
      }
    });
    applyGuard();
  });
});
</script>
