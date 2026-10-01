<?php
$reportData = (array)($report ?? []);
$rows = (array)($reportData['rows'] ?? []);
$totals = (array)($reportData['totals'] ?? []);
$selectedAccountId = (int)($selected_account_id ?? 0);
$selectedAccountLabel = '-';
foreach ((array)($accounts ?? []) as $accountRow) {
    if ((int)($accountRow['id'] ?? 0) === $selectedAccountId) {
        $selectedAccountLabel = trim(
            (string)($accountRow['account_code'] ?? '-') . ' • ' .
            (string)($accountRow['account_name'] ?? '-') . ' • ' .
            (string)($accountRow['bank_name'] ?? '-')
        );
        break;
    }
}
?>

<div class="page-wrapper">
  <div class="container-xl py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-1 fw-bold"><i class="ri ri-safe-2-line page-title-icon"></i><?= html_escape($page_title ?? 'Laporan Brankas Harian') ?></h4>
        <small class="text-muted">Monitoring pemasukan tunai, mutasi manual, transfer antar rekening, refund, dan pengeluaran per hari.</small>
      </div>
      <span class="badge bg-label-primary text-primary">Rekening: <?= html_escape($selectedAccountLabel) ?></span>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Bulan</label>
            <input type="month" name="month" class="form-control" value="<?= html_escape($month ?? date('Y-m')) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">Rekening Brankas/Kas</label>
            <select name="account_id" class="form-select">
              <?php foreach ((array)($accounts ?? []) as $accountRow): ?>
                <?php $accountId = (int)($accountRow['id'] ?? 0); ?>
                <option value="<?= $accountId ?>" <?= $accountId === $selectedAccountId ? 'selected' : '' ?>>
                  <?= html_escape((string)($accountRow['account_code'] ?? '-') . ' - ' . (string)($accountRow['account_name'] ?? '-') . ' - ' . (string)($accountRow['bank_name'] ?? '-')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Tampilkan</button>
            <a href="<?= site_url('finance-reports/cash-vault-daily') ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
        <div class="small text-muted mt-2">
          Periode <?= html_escape($reportData['date_start'] ?? '-') ?> s/d <?= html_escape($reportData['date_end'] ?? '-') ?>.
          Pendapatan diambil dari pembayaran POS yang masuk ke rekening ini. Kas masuk/keluar mencatat mutasi manual non-penjualan,
          sedangkan rekening masuk/keluar dipakai untuk transfer antar rekening perusahaan.
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Saldo Awal Bulan</small>
          <h5 class="mb-0">Rp <?= number_format((float)($totals['opening_balance'] ?? 0), 2, ',', '.') ?></h5>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Saldo Akhir Bulan</small>
          <h5 class="mb-0 <?= ((float)($totals['closing_balance'] ?? 0) < 0 ? 'text-danger' : 'text-primary') ?>">Rp <?= number_format((float)($totals['closing_balance'] ?? 0), 2, ',', '.') ?></h5>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Total Pendapatan Tunai</small>
          <h5 class="mb-0 text-success">Rp <?= number_format((float)($totals['pendapatan'] ?? 0), 2, ',', '.') ?></h5>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Hari Ada Aktivitas</small>
          <h5 class="mb-0"><?= number_format((int)($totals['active_days'] ?? 0)) ?> hari</h5>
        </div></div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Kas Masuk</small>
          <h6 class="mb-0 text-success">Rp <?= number_format((float)($totals['kas_masuk'] ?? 0), 2, ',', '.') ?></h6>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Kas Keluar</small>
          <h6 class="mb-0 text-danger">Rp <?= number_format((float)($totals['kas_keluar'] ?? 0), 2, ',', '.') ?></h6>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Transfer Bersih</small>
          <?php $transferNet = (float)($totals['rekening_masuk'] ?? 0) - (float)($totals['rekening_keluar'] ?? 0); ?>
          <h6 class="mb-0 <?= ($transferNet < 0 ? 'text-danger' : 'text-success') ?>">Rp <?= number_format($transferNet, 2, ',', '.') ?></h6>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Net Mutasi Bulan Ini</small>
          <h6 class="mb-0 <?= ((float)($totals['net_movement'] ?? 0) < 0 ? 'text-danger' : 'text-success') ?>">Rp <?= number_format((float)($totals['net_movement'] ?? 0), 2, ',', '.') ?></h6>
        </div></div>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr>
              <th class="text-center">Tanggal</th>
              <th class="text-end">Saldo Awal</th>
              <th class="text-end">Pendapatan</th>
              <th class="text-end">Kas Masuk</th>
              <th class="text-end">Kas Keluar</th>
              <th class="text-end">Rekening Masuk</th>
              <th class="text-end">Rekening Keluar</th>
              <th class="text-end">Refund</th>
              <th class="text-end">Belanja</th>
              <th class="text-end">Saldo Akhir</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-4">Data brankas harian belum tersedia.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr class="<?= !empty($row['has_activity']) ? '' : 'text-muted' ?>">
                  <td class="text-center"><?= html_escape(date('d-m-Y', strtotime((string)($row['tanggal'] ?? date('Y-m-d'))))) ?></td>
                  <td class="text-end">Rp <?= number_format((float)($row['saldo_awal'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end text-success">Rp <?= number_format((float)($row['pendapatan'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end text-success">Rp <?= number_format((float)($row['kas_masuk'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end text-danger">Rp <?= number_format((float)($row['kas_keluar'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end text-success">Rp <?= number_format((float)($row['rekening_masuk'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end text-danger">Rp <?= number_format((float)($row['rekening_keluar'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end text-danger">Rp <?= number_format((float)($row['refund'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end text-danger">Rp <?= number_format((float)($row['belanja'] ?? 0), 2, ',', '.') ?></td>
                  <td class="text-end fw-bold <?= ((float)($row['saldo_akhir'] ?? 0) < 0 ? 'text-danger' : 'text-primary') ?>">Rp <?= number_format((float)($row['saldo_akhir'] ?? 0), 2, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <th class="text-center">TOTAL</th>
              <th class="text-end">Rp <?= number_format((float)($totals['opening_balance'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end text-success">Rp <?= number_format((float)($totals['pendapatan'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end text-success">Rp <?= number_format((float)($totals['kas_masuk'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end text-danger">Rp <?= number_format((float)($totals['kas_keluar'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end text-success">Rp <?= number_format((float)($totals['rekening_masuk'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end text-danger">Rp <?= number_format((float)($totals['rekening_keluar'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end text-danger">Rp <?= number_format((float)($totals['refund'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end text-danger">Rp <?= number_format((float)($totals['belanja'] ?? 0), 2, ',', '.') ?></th>
              <th class="text-end fw-bold <?= ((float)($totals['closing_balance'] ?? 0) < 0 ? 'text-danger' : 'text-primary') ?>">Rp <?= number_format((float)($totals['closing_balance'] ?? 0), 2, ',', '.') ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>