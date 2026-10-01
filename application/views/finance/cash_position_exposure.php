<?php
$reportData = (array)($report ?? []);
$overview = (array)($reportData['overview'] ?? []);
$accountRows = (array)($reportData['accounts'] ?? []);
$moduleRows = (array)($reportData['module_rows'] ?? []);
$historicalRows = (array)($reportData['historical_rows'] ?? []);
$planning = (array)($reportData['planning_summary'] ?? []);
$selectedAccountId = (int)($selected_account_id ?? 0);
$viewMode = strtoupper((string)($view_mode ?? 'REAL'));
$selectedAccountLabel = 'Semua rekening';
foreach ((array)($accounts ?? []) as $accountRow) {
    if ((int)($accountRow['id'] ?? 0) === $selectedAccountId) {
        $selectedAccountLabel = trim(
            (string)($accountRow['account_code'] ?? '-') . ' - ' .
            (string)($accountRow['account_name'] ?? '-') . ' - ' .
            (string)($accountRow['bank_name'] ?? '-')
        );
        break;
    }
}

$modeMeta = [
    'PHYSICAL' => [
        'title' => 'Saldo Fisik',
        'subtitle' => 'Menyorot saldo rekening yang benar-benar tercatat di kas/bank saat ini.',
        'accent' => 'text-primary',
    ],
    'REAL' => [
        'title' => 'Saldo Riil Kafe',
        'subtitle' => 'Menyorot kekuatan kas setelah utang, piutang, kasbon, dan batch gaji belum cair ikut dibaca.',
        'accent' => 'text-success',
    ],
    'HISTORICAL' => [
        'title' => 'Historis Saldo Tetap',
        'subtitle' => 'Menyorot transaksi historis yang tidak mengubah saldo rekening, tetapi tetap memengaruhi posisi riil.',
        'accent' => 'text-warning',
    ],
];
$activeMode = $modeMeta[$viewMode] ?? $modeMeta['REAL'];
?>

<style>
  .cashpos-card,
  .cashpos-filter,
  .cashpos-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 26px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .cashpos-hero {
    background:
      radial-gradient(circle at top left, rgba(214, 139, 88, .18), transparent 34%),
      linear-gradient(180deg, #fffefc, #fff);
  }
  .cashpos-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .cashpos-kpi {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .cashpos-kpi .label {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8b7a6f;
  }
  .cashpos-kpi .value {
    font-size: 1.24rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .cashpos-kpi .sub {
    font-size: .86rem;
    color: #806e62;
  }
  .cashpos-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border-radius: 999px;
    border: 1px solid #ead7ca;
    background: #fff7f1;
    color: #7e5548;
    font-size: .78rem;
    font-weight: 700;
    padding: .38rem .85rem;
  }
  .cashpos-ledger-head th,
  .cashpos-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .cashpos-amount {
    font-weight: 700;
    color: #4d1d1d;
  }
  .cashpos-amount.negative {
    color: #b42318;
  }
  .cashpos-amount.positive {
    color: #067647;
  }
  .cashpos-account-name {
    font-weight: 700;
    color: #4c1c1d;
  }
  .cashpos-account-sub {
    color: #88786b;
    font-size: .86rem;
  }
  @media (max-width: 991.98px) {
    .cashpos-kpi-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (max-width: 575.98px) {
    .cashpos-kpi-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($page_title ?? 'Posisi Kas & Eksposur')); ?></h4>
      <p class="fin-page-subtitle mb-0">Workspace ringkas untuk membaca saldo rekening fisik, posisi riil kafe, dan transaksi historis saldo tetap yang tetap memengaruhi analisa.</p>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'cash-position']); ?>

  <div class="card cashpos-card cashpos-hero mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h5 class="mb-1"><?php echo html_escape($activeMode['title']); ?></h5>
          <div class="text-muted small"><?php echo html_escape($activeMode['subtitle']); ?></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <span class="cashpos-pill"><?php echo html_escape($selectedAccountLabel); ?></span>
          <span class="cashpos-pill"><?php echo html_escape((string)($reportData['month'] ?? date('Y-m'))); ?></span>
        </div>
      </div>

      <div class="cashpos-kpi-grid">
        <div class="cashpos-kpi">
          <div class="label">Saldo Fisik</div>
          <div class="value">Rp <?php echo number_format((float)($overview['physical_balance_total'] ?? 0), 2, ',', '.'); ?></div>
          <div class="sub">Total saldo rekening aktif yang dipilih</div>
        </div>
        <div class="cashpos-kpi">
          <div class="label">Saldo Riil</div>
          <div class="value">Rp <?php echo number_format((float)($overview['real_balance_total'] ?? 0), 2, ',', '.'); ?></div>
          <div class="sub">Saldo fisik + piutang + kasbon - utang - gaji belum cair</div>
        </div>
        <div class="cashpos-kpi">
          <div class="label">Historis Saldo Tetap</div>
          <div class="value">Rp <?php echo number_format((float)($overview['historical_net_total'] ?? 0), 2, ',', '.'); ?></div>
          <div class="sub">Net eksposur histori yang tidak memutasi rekening</div>
        </div>
        <div class="cashpos-kpi">
          <div class="label">Gap Fisik vs Riil</div>
          <div class="value <?php echo ((float)($overview['physical_vs_real_gap'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($overview['physical_vs_real_gap'] ?? 0), 2, ',', '.'); ?></div>
          <div class="sub">Selisih yang perlu dibaca sebagai eksposur</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card cashpos-filter mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label mb-1">Bulan Analisa</label>
          <input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($month ?? date('Y-m'))); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Rekening</label>
          <select name="account_id" class="form-select">
            <option value="0">Semua rekening</option>
            <?php foreach ((array)($accounts ?? []) as $accountRow): ?>
              <?php $accountId = (int)($accountRow['id'] ?? 0); ?>
              <option value="<?php echo $accountId; ?>" <?php echo $accountId === $selectedAccountId ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($accountRow['account_code'] ?? '-') . ' - ' . (string)($accountRow['account_name'] ?? '-') . ' - ' . (string)($accountRow['bank_name'] ?? '-')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Mode Tampilan</label>
          <select name="view_mode" class="form-select">
            <option value="PHYSICAL" <?php echo $viewMode === 'PHYSICAL' ? 'selected' : ''; ?>>Saldo fisik</option>
            <option value="REAL" <?php echo $viewMode === 'REAL' ? 'selected' : ''; ?>>Saldo riil</option>
            <option value="HISTORICAL" <?php echo $viewMode === 'HISTORICAL' ? 'selected' : ''; ?>>Historis saldo tetap</option>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
          <a href="<?php echo site_url('finance-reports/cash-position'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
        <div class="col-12">
          <div class="small text-muted">
            Mode <strong>saldo fisik</strong> fokus ke saldo rekening saat ini.
            Mode <strong>saldo riil</strong> membaca eksposur aktif di atas saldo rekening.
            Mode <strong>historis saldo tetap</strong> fokus ke transaksi yang tetap menempel ke rekening tetapi tidak memutasi saldo saat input.
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="cashpos-kpi-grid mb-3">
    <div class="cashpos-kpi">
      <div class="label">Piutang Aktif</div>
      <div class="value">Rp <?php echo number_format((float)($overview['receivable_outstanding_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Pihak luar masih berutang ke kafe</div>
    </div>
    <div class="cashpos-kpi">
      <div class="label">Utang Aktif</div>
      <div class="value">Rp <?php echo number_format((float)($overview['payable_outstanding_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Kewajiban aktif ke pihak luar</div>
    </div>
    <div class="cashpos-kpi">
      <div class="label">Kasbon Aktif</div>
      <div class="value">Rp <?php echo number_format((float)($overview['cash_advance_outstanding_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Piutang internal ke pegawai</div>
    </div>
    <div class="cashpos-kpi">
      <div class="label">Gaji Belum Cair</div>
      <div class="value">Rp <?php echo number_format((float)($overview['payroll_pending_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Batch gaji sudah disiapkan tapi belum menekan rekening</div>
    </div>
  </div>

  <div class="cashpos-kpi-grid mb-3">
    <div class="cashpos-kpi">
      <div class="label">Estimasi Gaji Berjalan</div>
      <div class="value">Rp <?php echo number_format((float)($planning['salary_estimate_running'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Masih estimasi, belum berarti keluar dari rekening</div>
    </div>
    <div class="cashpos-kpi">
      <div class="label">Store Request Pending</div>
      <div class="value">Rp <?php echo number_format((float)($planning['store_request_pending_value'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Kebutuhan belanja yang masih menggantung di workflow SR</div>
    </div>
    <div class="cashpos-kpi">
      <div class="label">Mutasi Masuk Bulan Ini</div>
      <div class="value">Rp <?php echo number_format((float)($overview['mutation_in_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Akumulasi semua mutasi IN pada rekening terpilih</div>
    </div>
    <div class="cashpos-kpi">
      <div class="label">Mutasi Keluar Bulan Ini</div>
      <div class="value">Rp <?php echo number_format((float)($overview['mutation_out_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Akumulasi semua mutasi OUT pada rekening terpilih</div>
    </div>
  </div>

  <div class="card cashpos-table mb-3">
    <div class="card-body border-bottom">
      <h5 class="mb-1">Dashboard Ringkas Per Rekening</h5>
      <div class="small text-muted">Tabel ini membantu membaca rekening mana yang kelihatannya aman secara fisik, tetapi sebenarnya sedang berat karena utang, payroll, atau eksposur historis.</div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Rekening</th>
            <th class="text-end">Saldo Fisik</th>
            <th class="text-end">Piutang</th>
            <th class="text-end">Kasbon</th>
            <th class="text-end">Utang</th>
            <th class="text-end">Gaji Pending</th>
            <th class="text-end">Historis Tetap</th>
            <th class="text-end">Saldo Riil</th>
            <th class="text-end">Mutasi Bulan Ini</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($accountRows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada rekening aktif yang bisa ditampilkan.</td></tr>
          <?php else: ?>
            <?php foreach ($accountRows as $row): ?>
              <?php
                $historicalClass = ((float)($row['historical_net'] ?? 0) < 0) ? 'negative' : 'positive';
                $realClass = ((float)($row['real_balance'] ?? 0) < 0) ? 'negative' : 'positive';
                $monthNetClass = ((float)($row['month_net'] ?? 0) < 0) ? 'negative' : 'positive';
              ?>
              <tr>
                <td>
                  <div class="cashpos-account-name"><?php echo html_escape((string)($row['account_name'] ?? '-')); ?></div>
                  <div class="cashpos-account-sub"><?php echo html_escape((string)($row['account_code'] ?? '-')); ?> • <?php echo html_escape((string)($row['bank_name'] ?? '-')); ?> • <?php echo html_escape((string)($row['account_type'] ?? '-')); ?></div>
                </td>
                <td class="text-end cashpos-amount <?php echo $viewMode === 'PHYSICAL' ? 'text-primary' : ''; ?>">Rp <?php echo number_format((float)($row['physical_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end cashpos-amount positive">Rp <?php echo number_format((float)($row['receivable_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end cashpos-amount positive">Rp <?php echo number_format((float)($row['cash_advance_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end cashpos-amount negative">Rp <?php echo number_format((float)($row['payable_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end cashpos-amount negative">Rp <?php echo number_format((float)($row['payroll_pending'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end cashpos-amount <?php echo $historicalClass; ?> <?php echo $viewMode === 'HISTORICAL' ? 'fw-bold' : ''; ?>">Rp <?php echo number_format((float)($row['historical_net'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end cashpos-amount <?php echo $realClass; ?> <?php echo $viewMode === 'REAL' ? 'fw-bold' : ''; ?>">Rp <?php echo number_format((float)($row['real_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end cashpos-amount <?php echo $monthNetClass; ?>">Rp <?php echo number_format((float)($row['month_net'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card cashpos-table h-100">
        <div class="card-body border-bottom">
          <h5 class="mb-1">Mutasi Per Modul</h5>
          <div class="small text-muted">Ringkas untuk melihat modul mana yang paling menekan atau menambah kas pada bulan analisa.</div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 cashpos-ledger-head">
            <thead>
              <tr>
                <th>Modul</th>
                <th class="text-end">Masuk</th>
                <th class="text-end">Keluar</th>
                <th class="text-end">Net</th>
                <th class="text-center">Baris</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($moduleRows)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada mutasi untuk filter ini.</td></tr>
              <?php else: ?>
                <?php foreach ($moduleRows as $row): ?>
                  <tr>
                    <td>
                      <div class="cashpos-account-name"><?php echo html_escape((string)($row['module_label'] ?? '-')); ?></div>
                    </td>
                    <td class="text-end cashpos-amount positive">Rp <?php echo number_format((float)($row['amount_in'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end cashpos-amount negative">Rp <?php echo number_format((float)($row['amount_out'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end cashpos-amount <?php echo ((float)($row['net_amount'] ?? 0) < 0 ? 'negative' : 'positive'); ?>">Rp <?php echo number_format((float)($row['net_amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-center"><?php echo number_format((int)($row['line_count'] ?? 0)); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card cashpos-table h-100">
        <div class="card-body border-bottom">
          <h5 class="mb-1">Audit Historis Saldo Tetap</h5>
          <div class="small text-muted">Ini adalah transaksi historis yang tetap ditautkan ke rekening, tetapi tidak mengubah saldo fisik saat input.</div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Pihak</th>
                <th class="text-end">Nominal</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($historicalRows)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada histori saldo tetap untuk filter ini.</td></tr>
              <?php else: ?>
                <?php foreach ($historicalRows as $row): ?>
                  <tr>
                    <td>
                      <div class="cashpos-account-name"><?php echo html_escape((string)($row['doc_date'] ?? '-')); ?></div>
                      <div class="cashpos-account-sub"><?php echo html_escape((string)($row['doc_no'] ?? '-')); ?></div>
                    </td>
                    <td>
                      <div class="cashpos-account-name"><?php echo html_escape((string)($row['row_type'] ?? '-')); ?></div>
                      <div class="cashpos-account-sub"><?php echo html_escape((string)($row['impact_type'] ?? '-')); ?></div>
                    </td>
                    <td>
                      <div class="cashpos-account-name"><?php echo html_escape((string)($row['party_name'] ?? '-')); ?></div>
                      <div class="cashpos-account-sub"><?php echo html_escape((string)($row['notes'] ?? '-')); ?></div>
                    </td>
                    <td class="text-end cashpos-amount <?php echo (($row['impact_type'] ?? '') === 'MENGURANGI_RIIL') ? 'negative' : 'positive'; ?>">
                      Rp <?php echo number_format((float)($row['amount'] ?? 0), 2, ',', '.'); ?>
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
</div>
