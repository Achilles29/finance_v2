<?php
$loanCfg = is_array($loan_cfg ?? null) ? $loan_cfg : [];
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$recap = is_array($recap ?? null) ? $recap : [];
$pg = is_array($pg ?? null) ? $pg : ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$loanTab = (string)($loan_tab ?? 'recap');
$detailRow = is_array($detail_row ?? null) ? $detail_row : null;
$detailPayments = is_array($detail_payments ?? null) ? $detail_payments : [];
$editRow = is_array($edit_row ?? null) ? $edit_row : null;
$isEdit = !empty($editRow);
$isPayable = ($loanCfg['title'] ?? 'Utang') === 'Utang';
$dateField = $isPayable ? 'payable_date' : 'receivable_date';
$titleField = $isPayable ? 'payable_title' : 'receivable_title';
$baseUrl = site_url((string)($loanCfg['base_url'] ?? 'finance/utang'));
$storeUrl = site_url((string)($loanCfg['base_url'] ?? 'finance/utang') . '/store');
$partySearchUrl = site_url('finance/party-search');
$memberSearchUrl = site_url('finance/member-search');
$partySaveUrl = site_url('finance/relasi/save');
$accountOptions = is_array($company_account_options ?? null) ? $company_account_options : [];
$buildUrl = static function (array $overrides = []) use ($filters, $pg, $baseUrl) {
    $query = [
        'tab' => (string)($filters['tab'] ?? 'recap'),
        'q' => (string)($filters['q'] ?? ''),
        'status' => (string)($filters['status'] ?? ''),
        'party_id' => (int)($filters['party_id'] ?? 0),
        'account_id' => (int)($filters['account_id'] ?? 0),
        'impact_mode' => (string)($filters['impact_mode'] ?? ''),
        'date_start' => (string)($filters['date_start'] ?? ''),
        'date_end' => (string)($filters['date_end'] ?? ''),
        'per_page' => (int)($pg['per_page'] ?? 25),
        'page' => (int)($pg['page'] ?? 1),
    ];
    return $baseUrl . '?' . http_build_query(array_merge($query, $overrides));
};
?>

<style>
  .fin-loan-hero,
  .fin-loan-filter,
  .fin-loan-table,
  .fin-loan-detail {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 26px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .fin-loan-hero {
    background:
      radial-gradient(circle at top left, rgba(214, 139, 88, .12), transparent 36%),
      linear-gradient(180deg, #fffefc, #fff);
  }
  .fin-loan-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .fin-loan-summary-card {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .fin-loan-summary-card .label {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8b7a6f;
  }
  .fin-loan-summary-card .value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .fin-loan-summary-card .subvalue {
    font-size: .86rem;
    color: #806e62;
  }
  .fin-loan-mode-pill,
  .fin-loan-party-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: .35rem .8rem;
    font-size: .78rem;
    font-weight: 700;
    border: 1px solid #ead7ca;
    background: #fff7f1;
    color: #7e5548;
  }
  .fin-loan-table thead th,
  .fin-loan-detail thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .fin-loan-name {
    font-weight: 700;
    color: #4c1c1d;
  }
  .fin-loan-sub {
    color: #88786b;
    font-size: .86rem;
  }
  .fin-loan-money {
    font-weight: 700;
    color: #4d1d1d;
  }
  .fin-loan-money.outstanding {
    color: #b42318;
  }
  .fin-loan-search-wrap {
    position: relative;
  }
  .fin-search-results {
    position: absolute;
    inset: calc(100% + 4px) 0 auto 0;
    z-index: 1060;
    background: #fff;
    border: 1px solid rgba(143, 53, 58, .16);
    border-radius: 18px;
    box-shadow: 0 18px 40px rgba(80, 47, 26, .16);
    max-height: 260px;
    overflow: auto;
    display: none;
  }
  .fin-search-results.show {
    display: block;
  }
  .fin-search-option {
    padding: .8rem .95rem;
    border-bottom: 1px solid rgba(143, 53, 58, .08);
    cursor: pointer;
  }
  .fin-search-option:last-child {
    border-bottom: 0;
  }
  .fin-search-option:hover {
    background: #fff6ef;
  }
  .fin-selected-box {
    border: 1px dashed rgba(143, 53, 58, .22);
    border-radius: 18px;
    padding: .85rem .95rem;
    background: #fffaf6;
  }
  @media (max-width: 991.98px) {
    .fin-loan-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (max-width: 575.98px) {
    .fin-loan-summary-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($loanCfg['page_title'] ?? 'Transaksi')); ?></h4>
      <p class="fin-page-subtitle mb-0"><?php echo html_escape((string)($isPayable ? 'Kelola utang ke pihak luar, pantau outstanding, dan catat pembayaran dengan dua mode: saldo bergerak untuk mutasi riil, saldo tetap untuk histori yang tetap ditautkan ke rekening.' : 'Kelola piutang ke pihak luar, pantau sisa tagihan, dan catat penerimaan dengan dua mode: saldo bergerak untuk mutasi riil, saldo tetap untuk histori yang tetap ditautkan ke rekening.')); ?></p>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => $isPayable ? 'payable' : 'receivable']); ?>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php
      $loanTabs = [
          'recap' => 'Rekap',
          'all' => 'Semua',
          'party' => 'Rekap Per Orang',
          'account' => 'Rekap Per Rekening',
          'party_account' => 'Per Orang Per Rekening',
      ];
    ?>
    <?php foreach ($loanTabs as $tabKey => $tabLabel): ?>
      <a href="<?php echo $buildUrl(['tab' => $tabKey, 'page' => 1, 'detail_id' => null, 'edit_id' => null]); ?>" class="btn btn-sm <?php echo $loanTab === $tabKey ? 'btn-primary' : 'btn-outline-primary'; ?>">
        <?php echo html_escape($tabLabel); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="card fin-loan-hero mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <h5 class="mb-1"><?php echo html_escape((string)($loanCfg['title_plural'] ?? 'Transaksi')); ?></h5>
          <div class="text-muted small"><?php echo html_escape((string)($isPayable ? 'Saldo bergerak dipakai saat uang memang masuk ke rekening sekarang. Saldo tetap dipakai untuk utang lama, tetapi rekening tetap dipilih supaya laporan nanti bisa membedakan saldo rekening fisik dan posisi riil kafe.' : 'Saldo bergerak dipakai saat uang memang keluar dari rekening sekarang. Saldo tetap dipakai untuk piutang lama, tetapi rekening tetap dipilih supaya laporan nanti bisa membedakan saldo rekening fisik dan posisi riil kafe.')); ?></div>
        </div>
        <button type="button" class="btn btn-primary" id="btn-new-loan" data-bs-toggle="modal" data-bs-target="#loanModal"><?php echo html_escape((string)($loanCfg['create_label'] ?? 'Tambah')); ?></button>
      </div>

      <div class="fin-loan-summary-grid">
        <div class="fin-loan-summary-card">
          <div class="label">Total Dokumen</div>
          <div class="value"><?php echo number_format((int)($summary['doc_total'] ?? 0)); ?></div>
          <div class="subvalue">Semua dokumen non-void sesuai filter</div>
        </div>
        <div class="fin-loan-summary-card">
          <div class="label">Total Nominal</div>
          <div class="value">Rp <?php echo number_format((float)($summary['amount_total'] ?? 0), 2, ',', '.'); ?></div>
          <div class="subvalue">Akumulasi pokok transaksi</div>
        </div>
        <div class="fin-loan-summary-card">
          <div class="label">Masih Outstanding</div>
          <div class="value">Rp <?php echo number_format((float)($summary['outstanding_total'] ?? 0), 2, ',', '.'); ?></div>
          <div class="subvalue">Yang masih perlu diselesaikan</div>
        </div>
        <div class="fin-loan-summary-card">
          <div class="label">Sudah Tertutup</div>
          <div class="value">Rp <?php echo number_format((float)($summary['paid_total'] ?? 0), 2, ',', '.'); ?></div>
          <div class="subvalue"><?php echo number_format((int)($summary['historical_doc_total'] ?? 0)); ?> dokumen mode saldo tetap</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card fin-loan-filter mb-3">
    <div class="card-body">
      <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label mb-1">Cari</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No dokumen / judul / nama pihak">
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Status</label>
          <select name="status" class="form-select">
            <option value="">Semua status</option>
            <?php foreach (['OPEN' => 'Open', 'PARTIAL' => 'Sebagian', 'SETTLED' => 'Lunas', 'VOID' => 'Void'] as $value => $label): ?>
              <option value="<?php echo $value; ?>" <?php echo (($filters['status'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Mode Saldo</label>
          <select name="impact_mode" class="form-select">
            <option value="">Semua mode</option>
            <option value="APPLY_ACCOUNT" <?php echo (($filters['impact_mode'] ?? '') === 'APPLY_ACCOUNT') ? 'selected' : ''; ?>>Saldo bergerak</option>
            <option value="KEEP_BALANCE" <?php echo (($filters['impact_mode'] ?? '') === 'KEEP_BALANCE') ? 'selected' : ''; ?>>Saldo tetap</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Rekening</label>
          <select name="account_id" class="form-select">
            <option value="0">Semua rekening</option>
            <?php foreach ($accountOptions as $account): ?>
              <option value="<?php echo (int)($account['id'] ?? 0); ?>" <?php echo ((int)($filters['account_id'] ?? 0) === (int)($account['id'] ?? 0)) ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($account['account_name'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Dari</label>
          <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Sampai</label>
          <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>">
        </div>
        <div class="col-md-1">
          <label class="form-label mb-1">Baris</label>
          <select name="per_page" class="form-select">
            <?php foreach ([10, 25, 50, 100] as $opt): ?>
              <option value="<?php echo $opt; ?>" <?php echo ((int)($pg['per_page'] ?? 25) === $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
          <button type="submit" class="btn btn-primary">Filter</button>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <?php if ($loanTab === 'recap'): ?>
    <div class="row g-3 mb-3">
      <div class="col-lg-6">
        <div class="card fin-loan-table h-100">
          <div class="card-body border-bottom">
            <h5 class="mb-1">Rekap Status</h5>
            <div class="small text-muted">Ringkasan posisi dokumen berdasarkan status.</div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Status</th>
                  <th class="text-end">Dokumen</th>
                  <th class="text-end">Nominal</th>
                  <th class="text-end">Outstanding</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recap['status_rows'])): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                  <?php foreach ((array)$recap['status_rows'] as $row): ?>
                    <tr>
                      <td><div class="fin-loan-name"><?php echo html_escape((string)($row['status'] ?? '-')); ?></div></td>
                      <td class="text-end"><?php echo number_format((int)($row['doc_total'] ?? 0)); ?></td>
                      <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['amount_total'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card fin-loan-table h-100">
          <div class="card-body border-bottom">
            <h5 class="mb-1">Rekap Mode Saldo</h5>
            <div class="small text-muted">Membedakan transaksi riil vs histori saldo tetap.</div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Mode</th>
                  <th class="text-end">Dokumen</th>
                  <th class="text-end">Nominal</th>
                  <th class="text-end">Outstanding</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recap['mode_rows'])): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                  <?php foreach ((array)$recap['mode_rows'] as $row): ?>
                    <tr>
                      <td><span class="fin-loan-mode-pill"><?php echo (($row['account_impact_mode'] ?? '') === 'KEEP_BALANCE') ? 'Saldo tetap' : 'Saldo bergerak'; ?></span></td>
                      <td class="text-end"><?php echo number_format((int)($row['doc_total'] ?? 0)); ?></td>
                      <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['amount_total'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card fin-loan-table h-100">
          <div class="card-body border-bottom">
            <h5 class="mb-1">Top Per Orang</h5>
            <div class="small text-muted">Pihak dengan outstanding terbesar pada filter saat ini.</div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Orang / Pihak</th>
                  <th class="text-end">Dokumen</th>
                  <th class="text-end">Outstanding</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recap['top_party_rows'])): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                  <?php foreach ((array)$recap['top_party_rows'] as $row): ?>
                    <tr>
                      <td>
                        <div class="fin-loan-name"><?php echo html_escape((string)($row['party_name'] ?? '-')); ?></div>
                        <div class="fin-loan-sub"><?php echo html_escape((string)($row['party_mobile_phone'] ?? $row['party_code'] ?? '-')); ?></div>
                      </td>
                      <td class="text-end"><?php echo number_format((int)($row['doc_total'] ?? 0)); ?></td>
                      <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-center"><a href="<?php echo $buildUrl(['tab' => 'all', 'party_id' => (int)($row['party_id'] ?? 0), 'page' => 1]); ?>" class="btn btn-sm btn-outline-primary">Lihat Semua</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card fin-loan-table h-100">
          <div class="card-body border-bottom">
            <h5 class="mb-1">Top Per Rekening</h5>
            <div class="small text-muted">Rekening yang paling besar eksposurnya pada filter saat ini.</div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Rekening</th>
                  <th class="text-end">Dokumen</th>
                  <th class="text-end">Outstanding</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recap['top_account_rows'])): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                  <?php foreach ((array)$recap['top_account_rows'] as $row): ?>
                    <tr>
                      <td>
                        <div class="fin-loan-name"><?php echo html_escape((string)($row['account_name'] ?? '-')); ?></div>
                        <div class="fin-loan-sub"><?php echo html_escape((string)($row['account_code'] ?? '-')); ?></div>
                      </td>
                      <td class="text-end"><?php echo number_format((int)($row['doc_total'] ?? 0)); ?></td>
                      <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-center"><a href="<?php echo $buildUrl(['tab' => 'all', 'account_id' => (int)($row['company_account_id'] ?? 0), 'page' => 1]); ?>" class="btn btn-sm btn-outline-primary">Lihat Semua</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="card fin-loan-table mb-3">
      <div class="table-responsive">
        <?php if ($loanTab === 'all'): ?>
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>No Dokumen</th>
                <th>Pihak</th>
                <th>Status</th>
                <th class="text-end">Nominal</th>
                <th class="text-end">Outstanding</th>
                <th>Mode</th>
                <th>Rekening</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php $status = strtoupper((string)($row['status'] ?? 'OPEN')); ?>
                  <tr>
                    <td>
                      <div class="fin-loan-name"><?php echo html_escape((string)($row[$isPayable ? 'payable_no' : 'receivable_no'] ?? '-')); ?></div>
                      <div class="fin-loan-sub"><?php echo html_escape((string)($row[$dateField] ?? '-')); ?></div>
                    </td>
                    <td>
                      <div class="fin-loan-name"><?php echo html_escape((string)($row['party_name'] ?? '-')); ?></div>
                      <div class="fin-loan-sub"><?php echo html_escape((string)($row[$titleField] ?? '-')); ?></div>
                    </td>
                    <td>
                      <?php if ($status === 'SETTLED'): ?>
                        <span class="badge bg-success-subtle text-success-emphasis">Lunas</span>
                      <?php elseif ($status === 'PARTIAL'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Sebagian</span>
                      <?php elseif ($status === 'VOID'): ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Void</span>
                      <?php else: ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis">Open</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td>
                      <span class="fin-loan-mode-pill"><?php echo (($row['account_impact_mode'] ?? '') === 'KEEP_BALANCE') ? 'Saldo tetap' : 'Saldo bergerak'; ?></span>
                    </td>
                    <td>
                      <?php if (!empty($row['account_name'])): ?>
                        <div class="fin-loan-name"><?php echo html_escape((string)($row['account_name'] ?? '')); ?></div>
                        <div class="fin-loan-sub"><?php echo html_escape((string)($row['account_code'] ?? '')); ?></div>
                      <?php else: ?>
                        <span class="text-muted">Tidak ada dampak saldo</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <a href="<?php echo $buildUrl(['detail_id' => (int)$row['id'], 'page' => (int)($pg['page'] ?? 1)]); ?>" class="btn btn-sm btn-outline-info">Detail</a>
                      <?php if ($status !== 'VOID'): ?>
                        <a href="<?php echo $buildUrl(['edit_id' => (int)$row['id'], 'page' => (int)($pg['page'] ?? 1)]); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="post" action="<?php echo site_url((string)($loanCfg['base_url'] ?? 'finance/utang') . '/void/' . (int)$row['id']); ?>" class="d-inline" onsubmit="return confirm('VOID transaksi ini?');">
                          <button type="submit" class="btn btn-sm btn-outline-warning">Void</button>
                        </form>
                        <form method="post" action="<?php echo site_url((string)($loanCfg['base_url'] ?? 'finance/utang') . '/delete/' . (int)$row['id']); ?>" class="d-inline" onsubmit="return confirm('Hapus transaksi ini?');">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        <?php elseif ($loanTab === 'party'): ?>
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Orang / Pihak</th>
                <th class="text-end">Dokumen</th>
                <th class="text-end">Rekening</th>
                <th class="text-end">Nominal</th>
                <th class="text-end">Outstanding</th>
                <th class="text-end">Sudah Dibayar</th>
                <th>Tanggal Terakhir</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada rekap per orang.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fin-loan-name"><?php echo html_escape((string)($row['party_name'] ?? '-')); ?></div>
                      <div class="fin-loan-sub"><?php echo html_escape((string)($row['party_mobile_phone'] ?? $row['party_code'] ?? '-')); ?></div>
                    </td>
                    <td class="text-end"><?php echo number_format((int)($row['doc_total'] ?? 0)); ?></td>
                    <td class="text-end"><?php echo number_format((int)($row['account_total'] ?? 0)); ?></td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['amount_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['paid_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td><?php echo html_escape((string)($row['last_doc_date'] ?? '-')); ?></td>
                    <td class="text-center"><a href="<?php echo $buildUrl(['tab' => 'all', 'party_id' => (int)($row['party_id'] ?? 0), 'page' => 1]); ?>" class="btn btn-sm btn-outline-primary">Lihat Transaksi</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        <?php elseif ($loanTab === 'account'): ?>
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Rekening</th>
                <th class="text-end">Orang</th>
                <th class="text-end">Dokumen</th>
                <th class="text-end">Nominal</th>
                <th class="text-end">Outstanding</th>
                <th class="text-end">Sudah Dibayar</th>
                <th>Tanggal Terakhir</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada rekap per rekening.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fin-loan-name"><?php echo html_escape((string)($row['account_name'] ?? '-')); ?></div>
                      <div class="fin-loan-sub"><?php echo html_escape((string)($row['account_code'] ?? '-')); ?> • <?php echo html_escape((string)($row['bank_name'] ?? '-')); ?></div>
                    </td>
                    <td class="text-end"><?php echo number_format((int)($row['party_total'] ?? 0)); ?></td>
                    <td class="text-end"><?php echo number_format((int)($row['doc_total'] ?? 0)); ?></td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['amount_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['paid_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td><?php echo html_escape((string)($row['last_doc_date'] ?? '-')); ?></td>
                    <td class="text-center"><a href="<?php echo $buildUrl(['tab' => 'all', 'account_id' => (int)($row['company_account_id'] ?? 0), 'page' => 1]); ?>" class="btn btn-sm btn-outline-primary">Lihat Transaksi</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        <?php else: ?>
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Orang / Pihak</th>
                <th>Rekening</th>
                <th class="text-end">Dokumen</th>
                <th class="text-end">Nominal</th>
                <th class="text-end">Outstanding</th>
                <th class="text-end">Sudah Dibayar</th>
                <th>Tanggal Terakhir</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada rekap orang per rekening.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fin-loan-name"><?php echo html_escape((string)($row['party_name'] ?? '-')); ?></div>
                      <div class="fin-loan-sub"><?php echo html_escape((string)($row['party_mobile_phone'] ?? $row['party_code'] ?? '-')); ?></div>
                    </td>
                    <td>
                      <div class="fin-loan-name"><?php echo html_escape((string)($row['account_name'] ?? '-')); ?></div>
                      <div class="fin-loan-sub"><?php echo html_escape((string)($row['account_code'] ?? '-')); ?></div>
                    </td>
                    <td class="text-end"><?php echo number_format((int)($row['doc_total'] ?? 0)); ?></td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['amount_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fin-loan-money outstanding">Rp <?php echo number_format((float)($row['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($row['paid_total'] ?? 0), 2, ',', '.'); ?></td>
                    <td><?php echo html_escape((string)($row['last_doc_date'] ?? '-')); ?></td>
                    <td class="text-center"><a href="<?php echo $buildUrl(['tab' => 'all', 'party_id' => (int)($row['party_id'] ?? 0), 'account_id' => (int)($row['company_account_id'] ?? 0), 'page' => 1]); ?>" class="btn btn-sm btn-outline-primary">Lihat Transaksi</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <?php if ($loanTab !== 'recap' && ($pg['total_pages'] ?? 1) > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <small class="text-muted">Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?>. Total <?php echo (int)$pg['total']; ?> data.</small>
          <div class="btn-group btn-group-sm">
            <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
            <a class="btn btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : $buildUrl(['page' => $prev]); ?>">Prev</a>
            <a class="btn btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : $buildUrl(['page' => $next]); ?>">Next</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($detailRow): ?>
    <div class="card fin-loan-detail">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <h5 class="mb-1">Detail <?php echo html_escape((string)($loanCfg['title'] ?? 'Transaksi')); ?>: <?php echo html_escape((string)($detailRow[$isPayable ? 'payable_no' : 'receivable_no'] ?? '-')); ?></h5>
            <div class="text-muted small"><?php echo html_escape((string)($detailRow['party_name'] ?? '-')); ?> | <?php echo html_escape((string)($detailRow[$titleField] ?? '-')); ?></div>
          </div>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-secondary">Tutup Detail</a>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="fin-loan-summary-card h-100">
              <div class="label">Nominal Pokok</div>
              <div class="value">Rp <?php echo number_format((float)($detailRow['amount'] ?? 0), 2, ',', '.'); ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="fin-loan-summary-card h-100">
              <div class="label">Sudah Tercatat</div>
              <div class="value">Rp <?php echo number_format((float)($detailRow['paid_amount'] ?? 0), 2, ',', '.'); ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="fin-loan-summary-card h-100">
              <div class="label">Masih Outstanding</div>
              <div class="value">Rp <?php echo number_format((float)($detailRow['outstanding_amount'] ?? 0), 2, ',', '.'); ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="fin-loan-summary-card h-100">
              <div class="label">Mode Saldo</div>
              <div class="value" style="font-size:1rem;"><?php echo (($detailRow['account_impact_mode'] ?? '') === 'KEEP_BALANCE') ? 'Saldo tetap' : 'Saldo bergerak'; ?></div>
              <div class="subvalue"><?php echo !empty($detailRow['account_name']) ? html_escape((string)$detailRow['account_name']) : 'Belum tertaut rekening'; ?></div>
            </div>
          </div>
        </div>

        <?php if (($detailRow['status'] ?? '') !== 'VOID' && (float)($detailRow['outstanding_amount'] ?? 0) > 0.0001): ?>
          <div class="card mb-3 border-0" style="background:#fffaf6;">
            <div class="card-body">
              <h6 class="mb-3"><?php echo html_escape((string)($loanCfg['payment_label'] ?? 'Pembayaran')); ?></h6>
              <form method="post" action="<?php echo site_url((string)($loanCfg['base_url'] ?? 'finance/utang') . '/payment/' . (int)$detailRow['id']); ?>" class="row g-2">
                <div class="col-md-2">
                  <label class="form-label mb-1">Tanggal</label>
                  <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label mb-1">Nominal</label>
                  <input type="number" step="0.01" min="0.01" max="<?php echo html_escape((string)($detailRow['outstanding_amount'] ?? 0)); ?>" name="amount" class="form-control" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label mb-1">Rekening</label>
                  <select name="company_account_id" class="form-select">
                    <option value="">Pilih rekening...</option>
                    <?php foreach ($accountOptions as $account): ?>
                      <option value="<?php echo (int)($account['id'] ?? 0); ?>"><?php echo html_escape((string)($account['label'] ?? '')); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label mb-1">Mode</label>
                  <select name="account_impact_mode" class="form-select">
                    <option value="APPLY_ACCOUNT">Saldo bergerak</option>
                    <option value="KEEP_BALANCE">Saldo tetap</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label mb-1">Ref transfer / catatan singkat</label>
                  <input type="text" name="transfer_ref_no" class="form-control" placeholder="Opsional">
                </div>
                <div class="col-12">
                  <textarea name="notes" class="form-control" rows="2" placeholder="Opsional. Bisa dipakai untuk menjelaskan pembayaran historis yang tidak mengubah saldo."></textarea>
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-primary"><?php echo html_escape((string)($loanCfg['payment_label'] ?? 'Simpan Pembayaran')); ?></button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead>
              <tr>
                <th>No Payment</th>
                <th>Tanggal</th>
                <th>Mode</th>
                <th>Rekening</th>
                <th class="text-end">Nominal</th>
                <th>Ref</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($detailPayments)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pembayaran.</td></tr>
              <?php else: ?>
                <?php foreach ($detailPayments as $payment): ?>
                  <tr>
                    <td>
                      <div class="fin-loan-name"><?php echo html_escape((string)($payment['payment_no'] ?? '-')); ?></div>
                      <div class="fin-loan-sub"><?php echo !empty($payment['mutation_no']) ? html_escape((string)$payment['mutation_no']) : 'Tanpa mutasi saldo'; ?></div>
                    </td>
                    <td><?php echo html_escape((string)($payment['payment_date'] ?? '-')); ?></td>
                    <td><span class="fin-loan-party-pill"><?php echo (($payment['account_impact_mode'] ?? '') === 'KEEP_BALANCE') ? 'Saldo tetap' : 'Saldo bergerak'; ?></span></td>
                    <td><?php echo !empty($payment['account_name']) ? html_escape((string)$payment['account_name']) : '<span class="text-muted">Belum tertaut rekening</span>'; ?></td>
                    <td class="text-end fin-loan-money">Rp <?php echo number_format((float)($payment['amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td><?php echo html_escape((string)($payment['transfer_ref_no'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($payment['notes'] ?? '-')); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="loanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><?php echo $isEdit ? 'Edit ' . html_escape((string)($loanCfg['title'] ?? 'Transaksi')) : html_escape((string)($loanCfg['create_label'] ?? 'Tambah')); ?></h5>
          <div class="small text-muted">Pilih pihak luar, tentukan apakah saldo rekening ikut bergerak sekarang atau hanya mencatat posisi historis.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?php echo $isEdit ? site_url((string)($loanCfg['base_url'] ?? 'finance/utang') . '/update/' . (int)($editRow['id'] ?? 0)) : $storeUrl; ?>" class="row g-3" id="loanForm">
          <input type="hidden" name="party_id" id="party_id" value="<?php echo (int)($editRow['party_id'] ?? 0); ?>">
          <div class="col-12">
            <label class="form-label mb-1"><?php echo html_escape((string)($isPayable ? 'Pemberi utang / kreditur' : 'Penerima piutang / debitur')); ?></label>
            <div class="fin-loan-search-wrap">
              <div class="input-group">
                <input type="text" id="party_search_input" class="form-control" placeholder="Cari nama pihak, kode, atau no HP" value="<?php echo html_escape((string)($editRow['party_name'] ?? '')); ?>">
                <button type="button" class="btn btn-outline-primary" id="btn-open-party-modal">Tambah pihak baru</button>
              </div>
              <div class="fin-search-results" id="party_search_results"></div>
            </div>
            <div class="fin-selected-box mt-2" id="party_selected_box">
              <?php if (!empty($editRow['party_id'])): ?>
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <div class="fin-loan-name"><?php echo html_escape((string)($editRow['party_name'] ?? '-')); ?></div>
                    <div class="fin-loan-sub"><?php echo html_escape((string)($editRow['party_mobile_phone'] ?? '-')); ?></div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-party">Ganti</button>
                </div>
              <?php else: ?>
                <span class="text-muted small">Belum ada pihak yang dipilih.</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Tanggal Transaksi</label>
            <input type="date" name="<?php echo $dateField; ?>" class="form-control" value="<?php echo html_escape((string)($editRow[$dateField] ?? date('Y-m-d'))); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Jatuh Tempo</label>
            <input type="date" name="due_date" class="form-control" value="<?php echo html_escape((string)($editRow['due_date'] ?? '')); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Nominal</label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?php echo html_escape((string)($editRow['amount'] ?? '')); ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Judul / Keperluan</label>
            <input type="text" name="<?php echo $titleField; ?>" class="form-control" value="<?php echo html_escape((string)($editRow[$titleField] ?? '')); ?>" placeholder="<?php echo html_escape((string)($isPayable ? 'Contoh: Pinjaman modal operasional dari relasi' : 'Contoh: Piutang talangan untuk event partner')); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Mode Dampak Saldo</label>
            <select name="account_impact_mode" id="account_impact_mode" class="form-select">
              <option value="APPLY_ACCOUNT" <?php echo (($editRow['account_impact_mode'] ?? 'APPLY_ACCOUNT') === 'APPLY_ACCOUNT') ? 'selected' : ''; ?>>Saldo bergerak sekarang</option>
              <option value="KEEP_BALANCE" <?php echo (($editRow['account_impact_mode'] ?? '') === 'KEEP_BALANCE') ? 'selected' : ''; ?>>Saldo tetap / historis</option>
            </select>
          </div>
          <div class="col-md-8" id="company_account_wrap">
            <label class="form-label mb-1">Rekening / Brankas</label>
            <select name="company_account_id" class="form-select">
              <option value="">Pilih rekening...</option>
              <?php foreach ($accountOptions as $account): ?>
                <option value="<?php echo (int)($account['id'] ?? 0); ?>" <?php echo ((int)($editRow['company_account_id'] ?? 0) === (int)($account['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo html_escape((string)($account['label'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Rekening tetap wajib dipilih, termasuk untuk saldo tetap, supaya utang/piutang tetap terbaca per rekening di laporan.</div>
          </div>
          <div class="col-12">
            <div class="alert alert-warning mb-0" id="impact_help_box">
              <?php echo html_escape((string)($loanCfg['impact_help_apply'] ?? '')); ?>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Opsional. Misalnya asal utang/piutang, nomor perjanjian, atau konteks historis."><?php echo html_escape((string)($editRow['notes'] ?? '')); ?></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" form="loanForm"><?php echo $isEdit ? 'Simpan Perubahan' : html_escape((string)($loanCfg['create_label'] ?? 'Simpan')); ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="quickPartyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">Tambah Pihak Baru</h5>
          <div class="small text-muted">Kalau pihak belum ada di master, buat cepat dari sini lalu langsung lanjutkan transaksi.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="quickPartyForm" class="row g-3">
          <input type="hidden" name="linked_member_id" id="quick_linked_member_id">
          <div class="col-md-4">
            <label class="form-label mb-1">Tipe</label>
            <select class="form-select" name="party_type">
              <option value="BUSINESS">Usaha / Vendor</option>
              <option value="PERSON">Orang</option>
              <option value="MEMBER">Member</option>
              <option value="OTHER">Lainnya</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1">Nama Pihak</label>
            <input type="text" class="form-control" name="party_name" required>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Tautkan ke Member (opsional)</label>
            <div class="fin-loan-search-wrap">
              <input type="text" id="quick_member_search_input" class="form-control" placeholder="Cari nama member atau no HP">
              <div class="fin-search-results" id="quick_member_search_results"></div>
            </div>
            <div class="fin-selected-box mt-2" id="quick_member_selected_box"><span class="text-muted small">Belum ada member yang dipilih.</span></div>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">No HP</label>
            <input type="text" class="form-control" name="mobile_phone">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Email</label>
            <input type="email" class="form-control" name="email">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">PIC / Contact Person</label>
            <input type="text" class="form-control" name="contact_person">
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Alamat</label>
            <textarea class="form-control" name="address" rows="2"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea class="form-control" name="notes" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-quick-party">Simpan & Pakai</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const loanModalEl = document.getElementById('loanModal');
  const quickPartyModalEl = document.getElementById('quickPartyModal');
  const loanModal = (window.bootstrap && loanModalEl) ? new window.bootstrap.Modal(loanModalEl) : null;
  const quickPartyModal = (window.bootstrap && quickPartyModalEl) ? new window.bootstrap.Modal(quickPartyModalEl) : null;
  const partySearchUrl = <?php echo json_encode($partySearchUrl); ?>;
  const memberSearchUrl = <?php echo json_encode($memberSearchUrl); ?>;
  const partySaveUrl = <?php echo json_encode($partySaveUrl); ?>;
  const impactHelpApply = <?php echo json_encode((string)($loanCfg['impact_help_apply'] ?? '')); ?>;
  const impactHelpKeep = <?php echo json_encode((string)($loanCfg['impact_help_keep'] ?? '')); ?>;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, options || {});
    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Request gagal.');
    }
    return json;
  }

  const impactModeEl = document.getElementById('account_impact_mode');
  const impactHelpBox = document.getElementById('impact_help_box');
  function syncImpactMode() {
    const keep = impactModeEl.value === 'KEEP_BALANCE';
    impactHelpBox.textContent = keep ? impactHelpKeep : impactHelpApply;
  }
  if (impactModeEl) {
    impactModeEl.addEventListener('change', syncImpactMode);
    syncImpactMode();
  }

  const partyIdInput = document.getElementById('party_id');
  const partyInput = document.getElementById('party_search_input');
  const partyResults = document.getElementById('party_search_results');
  const partySelectedBox = document.getElementById('party_selected_box');

  function renderSelectedParty(row) {
    if (!row || !row.id) {
      partyIdInput.value = '';
      partySelectedBox.innerHTML = '<span class="text-muted small">Belum ada pihak yang dipilih.</span>';
      return;
    }
    partyIdInput.value = String(row.id || '');
    const sub = row.mobile_phone || row.member_name || row.party_code || '-';
    partySelectedBox.innerHTML = `
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
          <div class="fin-loan-name">${escapeHtml(row.party_name || '-')}</div>
          <div class="fin-loan-sub">${escapeHtml(sub)}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-party">Ganti</button>
      </div>
    `;
    const clearBtn = document.getElementById('btn-clear-party');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        renderSelectedParty(null);
        partyInput.value = '';
      });
    }
  }

  let partyTimer = null;
  partyInput.addEventListener('input', function () {
    const q = partyInput.value.trim();
    clearTimeout(partyTimer);
    if (q.length < 2) {
      partyResults.classList.remove('show');
      partyResults.innerHTML = '';
      return;
    }
    partyTimer = setTimeout(async function () {
      try {
        const json = await fetchJson(partySearchUrl + '?q=' + encodeURIComponent(q), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const rows = Array.isArray(json.rows) ? json.rows : [];
        partyResults.innerHTML = rows.length ? rows.map((row) => `
          <div class="fin-search-option" data-id="${Number(row.id || 0)}" data-name="${escapeHtml(row.party_name || '')}" data-phone="${escapeHtml(row.mobile_phone || '')}" data-member="${escapeHtml(row.member_name || '')}" data-code="${escapeHtml(row.party_code || '')}">
            <div class="fin-loan-name">${escapeHtml(row.party_name || '-')}</div>
            <div class="fin-loan-sub">${escapeHtml(row.mobile_phone || row.member_name || row.party_code || '-')}</div>
          </div>
        `).join('') : '<div class="fin-search-option text-muted">Pihak belum ditemukan.</div>';
        partyResults.classList.add('show');
      } catch (err) {
        partyResults.innerHTML = '<div class="fin-search-option text-danger">Gagal mencari pihak.</div>';
        partyResults.classList.add('show');
      }
    }, 250);
  });

  partyResults.addEventListener('click', function (event) {
    const option = event.target.closest('.fin-search-option[data-id]');
    if (!option) return;
    renderSelectedParty({
      id: option.dataset.id,
      party_name: option.dataset.name,
      mobile_phone: option.dataset.phone,
      member_name: option.dataset.member,
      party_code: option.dataset.code
    });
    partyResults.classList.remove('show');
    partyResults.innerHTML = '';
    partyInput.value = option.dataset.name || '';
  });

  document.addEventListener('click', function (event) {
    if (!partyResults.contains(event.target) && event.target !== partyInput) {
      partyResults.classList.remove('show');
    }
  });

  document.getElementById('btn-open-party-modal').addEventListener('click', function () {
    if (quickPartyModal) {
      quickPartyModal.show();
    }
  });

  const quickPartyForm = document.getElementById('quickPartyForm');
  const quickMemberIdInput = document.getElementById('quick_linked_member_id');
  const quickMemberInput = document.getElementById('quick_member_search_input');
  const quickMemberResults = document.getElementById('quick_member_search_results');
  const quickMemberSelectedBox = document.getElementById('quick_member_selected_box');

  function renderQuickMember(row) {
    if (!row || !row.id) {
      quickMemberIdInput.value = '';
      quickMemberSelectedBox.innerHTML = '<span class="text-muted small">Belum ada member yang dipilih.</span>';
      return;
    }
    quickMemberIdInput.value = String(row.id || '');
    quickMemberSelectedBox.innerHTML = `
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
          <div class="fin-loan-name">${escapeHtml(row.member_name || '-')}</div>
          <div class="fin-loan-sub">${escapeHtml(row.mobile_phone || '-')}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-quick-member">Lepas</button>
      </div>
    `;
    const clearBtn = document.getElementById('btn-clear-quick-member');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        renderQuickMember(null);
        quickMemberInput.value = '';
      });
    }
  }

  let quickMemberTimer = null;
  quickMemberInput.addEventListener('input', function () {
    const q = quickMemberInput.value.trim();
    clearTimeout(quickMemberTimer);
    if (q.length < 2) {
      quickMemberResults.classList.remove('show');
      quickMemberResults.innerHTML = '';
      return;
    }
    quickMemberTimer = setTimeout(async function () {
      try {
        const json = await fetchJson(memberSearchUrl + '?q=' + encodeURIComponent(q), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const rows = Array.isArray(json.rows) ? json.rows : [];
        quickMemberResults.innerHTML = rows.length ? rows.map((row) => `
          <div class="fin-search-option" data-id="${Number(row.id || 0)}" data-name="${escapeHtml(row.member_name || '')}" data-phone="${escapeHtml(row.mobile_phone || '')}">
            <div class="fin-loan-name">${escapeHtml(row.member_name || '-')}</div>
            <div class="fin-loan-sub">${escapeHtml(row.mobile_phone || '-')}</div>
          </div>
        `).join('') : '<div class="fin-search-option text-muted">Member belum ditemukan.</div>';
        quickMemberResults.classList.add('show');
      } catch (err) {
        quickMemberResults.innerHTML = '<div class="fin-search-option text-danger">Gagal mencari member.</div>';
        quickMemberResults.classList.add('show');
      }
    }, 250);
  });

  quickMemberResults.addEventListener('click', function (event) {
    const option = event.target.closest('.fin-search-option[data-id]');
    if (!option) return;
    renderQuickMember({
      id: option.dataset.id,
      member_name: option.dataset.name,
      mobile_phone: option.dataset.phone
    });
    if (!quickPartyForm.elements.party_name.value.trim()) {
      quickPartyForm.elements.party_name.value = option.dataset.name || '';
    }
    if (!quickPartyForm.elements.mobile_phone.value.trim()) {
      quickPartyForm.elements.mobile_phone.value = option.dataset.phone || '';
    }
    quickMemberResults.classList.remove('show');
    quickMemberResults.innerHTML = '';
    quickMemberInput.value = option.dataset.name || '';
  });

  document.addEventListener('click', function (event) {
    if (!quickMemberResults.contains(event.target) && event.target !== quickMemberInput) {
      quickMemberResults.classList.remove('show');
    }
  });

  document.getElementById('btn-save-quick-party').addEventListener('click', async function () {
    const button = this;
    const payload = Object.fromEntries(new FormData(quickPartyForm).entries());
    button.disabled = true;
    try {
      const json = await fetchJson(partySaveUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify(payload)
      });
      if (quickPartyModal) quickPartyModal.hide();
      renderSelectedParty(json.row || {id: json.id});
      partyInput.value = String((json.row && json.row.party_name) || '');
      alert(json.message || 'Pihak berhasil ditambahkan.');
      quickPartyForm.reset();
      renderQuickMember(null);
    } catch (err) {
      alert(err.message || 'Gagal menyimpan pihak.');
    } finally {
      button.disabled = false;
    }
  });

  <?php if ($isEdit): ?>
    if (loanModal) {
      loanModal.show();
    }
    renderSelectedParty({
      id: <?php echo (int)($editRow['party_id'] ?? 0); ?>,
      party_name: <?php echo json_encode((string)($editRow['party_name'] ?? '')); ?>,
      mobile_phone: <?php echo json_encode((string)($editRow['party_mobile_phone'] ?? '')); ?>,
      member_name: <?php echo json_encode((string)($editRow['member_name'] ?? '')); ?>,
      party_code: <?php echo json_encode((string)($editRow['party_code'] ?? '')); ?>
    });
  <?php endif; ?>
});
</script>
