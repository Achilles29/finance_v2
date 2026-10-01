<?php
$reportData = (array)($report ?? []);
$overview = (array)($reportData['overview'] ?? []);
$rows = (array)($reportData['rows'] ?? []);
?>

<style>
  .dailyov-shell { display: grid; gap: 1rem; }
  .dailyov-filter,
  .dailyov-card,
  .dailyov-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .dailyov-filter {
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1.25rem;
  }
  .dailyov-kpis {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 1rem;
  }
  .dailyov-kpi {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    padding: 1rem 1.1rem;
    background: #fff;
  }
  .dailyov-kpi .label {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8b7a6f;
    margin-bottom: .35rem;
  }
  .dailyov-kpi .value {
    font-size: 1.22rem;
    font-weight: 800;
    color: #4f1f1f;
  }
  .dailyov-kpi .value.positive { color: #16a34a; }
  .dailyov-kpi .value.negative { color: #dc2626; }
  .dailyov-strip {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    align-items: center;
    padding: 1rem 1.1rem;
    border: 1px solid rgba(143, 53, 58, .08);
    border-radius: 20px;
    background: linear-gradient(180deg, #fff, #fff9f8);
  }
  .dailyov-chip {
    border-radius: 999px;
    padding: .45rem .8rem;
    background: #fff;
    border: 1px solid rgba(143, 53, 58, .12);
    font-size: .88rem;
  }
  .dailyov-table thead th,
  .dailyov-detail thead th {
    background: linear-gradient(135deg, #7b1d2a, #8f353a);
    color: #fff;
    border: 0;
    white-space: nowrap;
    font-size: .74rem;
    text-transform: uppercase;
  }
  .dailyov-detail thead th {
    background: #8f353a;
    font-size: .72rem;
  }
  .dailyov-table-scroll {
    max-height: 70vh;
    overflow: auto;
  }
  .dailyov-table-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 3;
  }
  .dailyov-table .table tbody td,
  .dailyov-detail .table tbody td {
    font-size: .84rem;
  }
  .dailyov-detail-wrap .table {
    min-width: 1050px;
  }
  .dailyov-table .table {
    min-width: 1180px;
  }
  .dailyov-detail-wrap {
    background: #fff9f7;
    padding: 1rem;
    border-top: 1px solid rgba(143, 53, 58, .08);
  }
  @media (max-width: 1399.98px) {
    .dailyov-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 767.98px) {
    .dailyov-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .dailyov-kpis { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'daily-overview']); ?>

  <div class="dailyov-shell">
    <div class="dailyov-filter">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-4 col-md-5">
          <label class="form-label mb-1">Bulan</label>
          <input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($month ?? date('Y-m'))); ?>">
        </div>
        <div class="col-lg-2 col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
        </div>
        <div class="col-lg-6 col-md-12">
          <div class="small text-muted pt-md-4">Expand per tanggal untuk melihat rincian mutasi per rekening.</div>
        </div>
      </form>
    </div>

    <div class="dailyov-kpis">
      <div class="dailyov-kpi">
        <div class="label">Kas Awal</div>
        <div class="value">Rp <?php echo number_format((float)($overview['opening_balance'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="dailyov-kpi">
        <div class="label">Total Masuk</div>
        <div class="value positive">Rp <?php echo number_format((float)($overview['total_in'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="dailyov-kpi">
        <div class="label">Total Keluar</div>
        <div class="value negative">Rp <?php echo number_format((float)($overview['total_out'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="dailyov-kpi">
        <div class="label">Net Bulan Ini</div>
        <div class="value <?php echo ((float)($overview['net_total'] ?? 0) < 0 ? 'negative' : 'positive'); ?>">Rp <?php echo number_format((float)($overview['net_total'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="dailyov-kpi">
        <div class="label">Kas Akhir</div>
        <div class="value positive">Rp <?php echo number_format((float)($overview['closing_balance'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="dailyov-kpi">
        <div class="label">Hari Aktif</div>
        <div class="value"><?php echo (int)($overview['active_days'] ?? 0); ?> / <?php echo count($rows); ?></div>
        <div class="small text-muted"><?php echo number_format((int)($overview['transaction_count'] ?? 0), 0, ',', '.'); ?> transaksi tercatat</div>
      </div>
    </div>

    <div class="dailyov-strip">
      <div class="fw-semibold">Sorotan & Posisi Rekening</div>
      <div class="dailyov-chip">Puncak masuk <?php echo html_escape((string)($overview['peak_inflow_date'] ?? '-')); ?> <strong class="text-success">Rp <?php echo number_format((float)($overview['peak_inflow_amount'] ?? 0), 2, ',', '.'); ?></strong></div>
      <div class="dailyov-chip">Top rekening <?php echo html_escape((string)($overview['top_account_name'] ?? '-')); ?> <strong class="text-success">Rp <?php echo number_format((float)($overview['top_account_amount'] ?? 0), 2, ',', '.'); ?></strong></div>
      <div class="dailyov-chip"><?php echo (int)($overview['active_account_count'] ?? 0); ?> rekening aktif</div>
    </div>

    <div class="dailyov-table">
      <div class="p-3 border-bottom d-flex justify-content-between flex-wrap gap-2">
        <div>
          <div class="fw-semibold">Timeline Harian (Tanggal 1 di Atas)</div>
          <div class="small text-muted">Klik ikon detail di sisi kanan untuk membuka rincian rekening per tanggal.</div>
        </div>
      </div>
      <div class="table-responsive dailyov-table-scroll">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th class="text-end">Kas Awal</th>
              <th class="text-end">Pendapatan</th>
              <th class="text-end">Masuk</th>
              <th class="text-end">Keluar</th>
              <th class="text-end">Refund</th>
              <th class="text-end">Belanja</th>
              <th class="text-end">Net</th>
              <th class="text-end">Kas Akhir</th>
              <th class="text-center">Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-4">Belum ada data untuk periode ini.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $idx => $row): ?>
                <?php $collapseId = 'daily-overview-' . $idx; ?>
                <tr>
                  <td><?php echo html_escape((string)($row['date'] ?? '-')); ?></td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['opening_balance'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end fw-semibold">Rp <?php echo number_format((float)($row['revenue'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-success">Rp <?php echo number_format((float)($row['in_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-danger">Rp <?php echo number_format((float)($row['out_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['refund'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['purchase'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end fw-semibold <?php echo ((float)($row['net_total'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($row['net_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['closing_balance'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-center">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                      <span class="fw-semibold">Lihat</span>
                    </button>
                  </td>
                </tr>
                <tr class="collapse" id="<?php echo $collapseId; ?>">
                  <td colspan="10" class="p-0">
                    <div class="dailyov-detail-wrap">
                      <div class="small text-muted mb-2">Rincian per rekening tanggal <?php echo html_escape((string)($row['date'] ?? '-')); ?></div>
                      <div class="table-responsive dailyov-detail">
                        <table class="table table-sm mb-0 align-middle">
                          <thead>
                            <tr>
                              <th>Rekening</th>
                              <th class="text-end">Kas Awal</th>
                              <th class="text-end">Pendapatan</th>
                              <th class="text-end">Masuk</th>
                              <th class="text-end">Keluar</th>
                              <th class="text-end">Refund</th>
                              <th class="text-end">Belanja</th>
                              <th class="text-end">Net</th>
                              <th class="text-end">Kas Akhir</th>
                              <th class="text-end">Txn</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ((array)($row['detail_rows'] ?? []) as $detail): ?>
                              <tr>
                                <td>
                                  <div class="fw-semibold"><?php echo html_escape((string)($detail['account_name'] ?? '-')); ?></div>
                                  <div class="small text-muted"><?php echo html_escape((string)($detail['bank_name'] ?? '-')); ?> - <?php echo html_escape((string)($detail['account_code'] ?? '-')); ?></div>
                                </td>
                                <td class="text-end">Rp <?php echo number_format((float)($detail['opening_balance'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end">Rp <?php echo number_format((float)($detail['revenue'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end text-success">Rp <?php echo number_format((float)($detail['in_total'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end text-danger">Rp <?php echo number_format((float)($detail['out_total'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end">Rp <?php echo number_format((float)($detail['refund'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end">Rp <?php echo number_format((float)($detail['purchase'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end fw-semibold <?php echo ((float)($detail['net_total'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($detail['net_total'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end">Rp <?php echo number_format((float)($detail['closing_balance'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((int)($detail['txn_count'] ?? 0), 0, ',', '.'); ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
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
