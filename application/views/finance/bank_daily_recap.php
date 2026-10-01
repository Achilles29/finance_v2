<?php
$reportData = (array)($report ?? []);
$overview = (array)($reportData['overview'] ?? []);
$rows = (array)($reportData['rows'] ?? []);
$accounts = (array)($reportData['accounts'] ?? []);
?>

<style>
  .bank-recap-shell { display: grid; gap: 1rem; }
  .bank-recap-filter,
  .bank-recap-card,
  .bank-recap-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .bank-recap-filter {
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1.25rem;
  }
  .bank-recap-kpis {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 1rem;
  }
  .bank-recap-kpi {
    border: 1px solid rgba(143, 53, 58, .08);
    border-radius: 20px;
    padding: 1rem 1.1rem;
    background: #fff;
  }
  .bank-recap-kpi .label {
    font-size: .78rem;
    text-transform: uppercase;
    color: #8b7a6f;
    letter-spacing: .04em;
    margin-bottom: .35rem;
  }
  .bank-recap-kpi .value {
    font-size: 1.3rem;
    font-weight: 800;
    color: #4f1f1f;
  }
  .bank-recap-table .table thead th {
    border: 0;
    vertical-align: middle;
    white-space: nowrap;
    font-size: .74rem;
  }
  .bank-recap-scroll {
    max-height: 70vh;
    overflow: auto;
  }
  .bank-recap-scroll thead tr:first-child th {
    position: sticky;
    top: 0;
    z-index: 4;
  }
  .bank-recap-scroll thead tr:nth-child(2) th {
    position: sticky;
    top: 54px;
    z-index: 4;
  }
  .bank-recap-date-head {
    background: linear-gradient(135deg, #7b1d2a, #8f353a);
    color: #fff;
    min-width: 96px;
  }
  .bank-recap-subhead {
    background: #fff6f4;
    color: #4f1f1f;
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .03em;
  }
  .bank-recap-date-cell {
    background: #fff;
    min-width: 110px;
  }
  .bank-recap-cell {
    min-width: 155px;
    border-color: rgba(143, 53, 58, .08);
    font-size: .83rem;
  }
  .bank-recap-main {
    font-weight: 800;
    color: #1f2a44;
    font-size: .95rem;
  }
  .bank-recap-main.negative { color: #dc2626; }
  .bank-recap-side {
    font-size: .73rem;
    line-height: 1.35;
    margin-top: .2rem;
  }
  .bank-recap-side .h { color: #d97706; }
  .bank-recap-side .p { color: #0891b2; }
  .bank-recap-side .dp { color: #b91c1c; }
  .bank-recap-note {
    padding: 1rem 1.15rem;
    border-radius: 999px;
    background: #fffef8;
    border: 1px solid rgba(245, 158, 11, .25);
    color: #8a5b00;
    font-size: .92rem;
  }
  @media (max-width: 991.98px) {
    .bank-recap-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .bank-recap-kpis { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'bank-daily-recap']); ?>

  <div class="bank-recap-shell">
    <div class="bank-recap-filter">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-4">
          <label class="form-label mb-1">Bulan</label>
          <input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($month ?? date('Y-m'))); ?>">
        </div>
        <div class="col-lg-2 col-md-3">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
        </div>
        <div class="col-lg-7 col-md-12">
          <div class="small text-muted pt-md-4">Tampilan ini membedakan <strong>Saldo Bersih</strong> dan <strong>Rekening Riil</strong> per hari supaya perubahan kas, hutang, dan piutang lebih cepat terbaca.</div>
        </div>
      </form>
    </div>

    <div class="bank-recap-kpis">
      <div class="bank-recap-kpi">
        <div class="label">Total Saldo Bersih</div>
        <div class="value <?php echo ((float)($overview['total_net_balance'] ?? 0) < 0 ? 'text-danger' : ''); ?>">Rp <?php echo number_format((float)($overview['total_net_balance'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">Posisi bersih perusahaan setelah hutang dan piutang diperhitungkan</div>
      </div>
      <div class="bank-recap-kpi">
        <div class="label">Total Rekening Riil</div>
        <div class="value">Rp <?php echo number_format((float)($overview['total_physical_balance'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">Saldo nyata yang saat ini benar-benar ada di rekening</div>
      </div>
      <div class="bank-recap-kpi">
        <div class="label">Total Hutang (AP)</div>
        <div class="value text-warning">Rp <?php echo number_format((float)($overview['total_payable'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">Kewajiban yang masih menempel ke rekening asal</div>
      </div>
      <div class="bank-recap-kpi">
        <div class="label">Total Piutang</div>
        <div class="value text-info">Rp <?php echo number_format((float)($overview['total_receivable'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">AR dan kasbon outstanding yang menambah posisi bersih</div>
      </div>
      <div class="bank-recap-kpi">
        <div class="label">Total DP Aktif</div>
        <div class="value text-danger">Rp <?php echo number_format((float)($overview['total_deposit'] ?? 0), 2, ',', '.'); ?></div>
        <div class="small text-muted">DP member yang belum terpakai penuh dan masih jadi kewajiban</div>
      </div>
    </div>

    <div class="bank-recap-note">
      Saldo = Rekening riil - Hutang - DP aktif + Piutang. Piutang pada halaman ini menggabungkan AR dan kasbon outstanding. Pelunasan kasbon dengan potong gaji menurunkan kasbon outstanding tanpa menambah saldo riil rekening.
    </div>

    <div class="bank-recap-table">
      <div class="p-3 border-bottom">
        <div class="fw-semibold">Rekap Harian - <?php echo html_escape((string)($reportData['month_label'] ?? '')); ?></div>
        <div class="small text-muted">Kolom kiri menunjukkan tanggal, setiap rekening menampilkan dua angka: Saldo Bersih dan Rekening Riil.</div>
      </div>
      <div class="table-responsive bank-recap-scroll">
        <table class="table mb-0 align-middle text-center">
          <thead>
            <tr>
              <th class="bank-recap-date-head" rowspan="2">Tanggal</th>
              <?php foreach ($accounts as $account): ?>
                <th colspan="2" style="background: <?php echo html_escape((string)($account['head_color'] ?? '#8f353a')); ?>; color:#fff;">
                  <div class="fw-bold"><?php echo html_escape((string)($account['account_name'] ?? '-')); ?></div>
                  <div class="small opacity-75"><?php echo html_escape((string)($account['bank_name'] ?? '-')); ?> - <?php echo html_escape((string)($account['account_code'] ?? '-')); ?></div>
                </th>
              <?php endforeach; ?>
            </tr>
            <tr>
              <?php foreach ($accounts as $account): ?>
                <th class="bank-recap-subhead">Saldo Bersih</th>
                <th class="bank-recap-subhead">Rekening Riil</th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="<?php echo 1 + (count($accounts) * 2); ?>" class="text-center text-muted py-4">Belum ada data untuk periode ini.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td class="bank-recap-date-cell">
                    <div class="fw-bold"><?php echo html_escape((string)($row['day_label'] ?? '-')); ?></div>
                    <div class="small text-muted"><?php echo html_escape((string)($row['weekday_label'] ?? '-')); ?></div>
                  </td>
                  <?php foreach ($accounts as $account): ?>
                    <?php $cell = (array)($row['cells'][(int)($account['id'] ?? 0)] ?? []); ?>
                    <td class="bank-recap-cell" style="background: <?php echo html_escape((string)($account['cell_color'] ?? '#fff')); ?>;">
                      <div class="bank-recap-main <?php echo ((float)($cell['net_balance'] ?? 0) < 0 ? 'negative' : ''); ?>">
                        Rp <?php echo number_format((float)($cell['net_balance'] ?? 0), 2, ',', '.'); ?>
                      </div>
                      <div class="bank-recap-side">
                        <div class="h">H: Rp <?php echo number_format((float)($cell['payable_total'] ?? 0), 2, ',', '.'); ?></div>
                        <div class="p">AR: Rp <?php echo number_format((float)($cell['receivable_only_total'] ?? 0), 2, ',', '.'); ?></div>
                        <div class="p">K: Rp <?php echo number_format((float)($cell['cash_advance_total'] ?? 0), 2, ',', '.'); ?></div>
                        <div class="dp">DP: Rp <?php echo number_format((float)($cell['deposit_total'] ?? 0), 2, ',', '.'); ?></div>
                      </div>
                    </td>
                    <td class="bank-recap-cell" style="background: <?php echo html_escape((string)($account['cell_color'] ?? '#fff')); ?>;">
                      <div class="bank-recap-main <?php echo ((float)($cell['physical_balance'] ?? 0) < 0 ? 'negative' : ''); ?>">
                        Rp <?php echo number_format((float)($cell['physical_balance'] ?? 0), 2, ',', '.'); ?>
                      </div>
                      <div class="small text-muted mt-1">Saldo kas/bank nyata</div>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
