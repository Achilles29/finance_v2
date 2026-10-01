<?php
$reportData = (array)($report ?? []);
$overview = (array)($reportData['overview'] ?? []);
$rows = (array)($reportData['rows'] ?? []);
$selectedMonth = (int)($month ?? (int)date('n'));
$selectedYear = (int)($year ?? (int)date('Y'));
$money = static fn($amount): string => 'Rp ' . number_format((float)$amount, 2, ',', '.');
$mutationUrl = site_url('finance/mutations') . '?' . http_build_query([
    'date_from' => $reportData['date_start'] ?? '', 'date_to' => $reportData['date_end'] ?? '',
]);
?>

<style>
  .fin-est-shell { display: grid; gap: 1rem; }
  .fin-est-card,
  .fin-est-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
    background: #fff;
  }
  .fin-est-filter {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1.25rem;
  }
  .fin-est-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .fin-est-kpi {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: #fff;
    padding: 1rem 1.1rem;
  }
  .fin-est-kpi .label {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8b7a6f;
    margin-bottom: .35rem;
  }
  .fin-est-kpi .value {
    font-size: 1.25rem;
    font-weight: 800;
    color: #4f1f1f;
  }
  .fin-est-kpi .value.positive { color: #16a34a; }
  .fin-est-kpi .value.negative { color: #dc2626; }
  .fin-est-note {
    border: 1px solid rgba(58, 132, 255, .18);
    background: #f8fbff;
    color: #42526e;
    border-radius: 16px;
    padding: .7rem 1rem;
    font-size: .92rem;
  }
  .fin-est-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    white-space: nowrap;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .03em;
  }
  .fin-est-table tbody td {
    vertical-align: middle;
    border-color: rgba(143, 53, 58, .08);
    font-size: .86rem;
  }
  .fin-est-table tfoot td {
    position: sticky;
    bottom: 0;
    z-index: 2;
    background: #fff7f5;
    font-size: .86rem;
    border-top: 2px solid rgba(143, 53, 58, .16);
  }
  .fin-est-scroll {
    max-height: 70vh;
    overflow: auto;
  }
  .fin-est-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 3;
  }
  .fin-est-table .table {
    min-width: 980px;
  }
  .fin-est-sub {
    display: block;
    margin-top: .2rem;
    font-size: .8rem;
    color: #7f6d62;
  }
  .fin-est-shell { min-width: 0; }
  .fin-est-kpi { overflow-wrap: anywhere; background: linear-gradient(145deg, #fff7ee, #fff); }
  .fin-est-kpi.income { background: linear-gradient(145deg, #eaf9ef, #fff); border-color: #bde2c7; }
  .fin-est-kpi.expense { background: linear-gradient(145deg, #fff0ed, #fff); border-color: #f3c5ba; }
  .fin-est-kpi.result { background: linear-gradient(145deg, #f1edff, #fff); border-color: #d7ccee; }
  .fin-est-bridge { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
  .fin-est-bridge > div { padding: 1rem; border: 1px solid #e6ddd5; border-radius: 18px; background: #fff; }
  .fin-est-bridge dl { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .4rem .8rem; margin: .6rem 0 0; }
  .fin-est-bridge dt { font-weight: 400; }
  .fin-est-bridge dd { text-align: right; margin: 0; font-weight: 600; }
  .fin-est-shell details summary { cursor: pointer; color: #773241; font-weight: 600; padding: .35rem 0; }
  .fin-est-status { display: inline-block; padding: .3rem .7rem; border-radius: 999px; background: #fff1cd; color: #785100; font-size: .8rem; }
  .fin-est-table details { white-space: normal; min-width: 190px; font-size: .8rem; }
  @media (max-width: 575.98px) {
    .fin-est-bridge { grid-template-columns: minmax(0, 1fr); }
    .fin-est-bridge dl { grid-template-columns: minmax(0, 1fr); }
    .fin-est-bridge dd { text-align: left; }
  }
  @media (max-width: 991.98px) {
    .fin-est-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .fin-est-kpis { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'financial-estimation']); ?>

  <div class="fin-est-shell">
    <div class="fin-est-filter">
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
        <div><h4 class="mb-1">Ke mana uang usaha bergerak?</h4><div class="text-muted">Estimasi operasional berbasis kas · <?php echo html_escape((string)($reportData['month_label'] ?? '')); ?></div></div>
        <a class="btn btn-outline-primary" href="<?php echo html_escape($mutationUrl); ?>">Telusuri mutasi rekening</a>
      </div>
      <p class="mt-3 mb-0">Penerimaan POS − refund + pendapatan lain − pengeluaran − estimasi gaji. Modal, prive, transfer dan koreksi saldo saja tidak menjadi pendapatan/biaya operasional.</p>
    </div>
    <div class="fin-est-filter">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-4">
          <label class="form-label mb-1">Bulan</label>
          <select name="month" class="form-select">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?php echo $m; ?>" <?php echo $m === $selectedMonth ? 'selected' : ''; ?>><?php echo html_escape(date('F', mktime(0, 0, 0, $m, 1, 2026))); ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-3">
          <label class="form-label mb-1">Tahun</label>
          <input type="number" name="year" class="form-control" min="2020" max="2100" value="<?php echo $selectedYear; ?>">
        </div>
        <div class="col-lg-2 col-md-3">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
        </div>
        <div class="col-lg-5 col-md-12">
          <div class="small text-muted pt-md-4">
            Data absen tersedia: <?php echo (int)($overview['attendance_days_with_data'] ?? 0); ?> hari dari <?php echo (int)($overview['days_in_month'] ?? 0); ?> hari bulan <?php echo html_escape((string)($reportData['month_label'] ?? '')); ?>
          </div>
        </div>
      </form>
    </div>

    <div class="fin-est-note">
      <strong>Ini estimasi, bukan laba-rugi akuntansi atau saldo rekening.</strong> Pembelian mengikuti kas keluar, belum disamakan dengan HPP barang terjual. Gaji mengikuti <a href="<?php echo site_url('attendance/estimate'); ?>">estimasi absensi harian</a> termasuk lembur; pencairan payroll tidak dikurangkan lagi. Metrik target/tutup periode memakai aturan mutasi yang sama, tetapi gajinya mengikuti payroll tergenerate bila tersedia.
    </div>

    <?php if (empty($reportData['category_schema_ready'])): ?>
      <div class="alert alert-warning mb-0">Kategori mutasi belum tersedia. Administrator perlu menjalankan migrasi 2026-09-13a. Laporan tetap dapat dibaca dengan aturan data lama.</div>
    <?php endif; ?>
    <?php if ((int)($overview['unclassified_count'] ?? 0) > 0): ?>
      <div class="alert alert-warning mb-0" role="status">
        <strong>Estimasi sementara: <?php echo (int)$overview['unclassified_count']; ?> mutasi perlu klasifikasi.</strong>
        Masuk <?php echo $money($overview['total_unclassified_in'] ?? 0); ?> belum menjadi pendapatan.
        Keluar <?php echo $money($overview['total_unclassified_out'] ?? 0); ?> masih mengurangi estimasi sesuai aturan lama.
        <a href="<?php echo html_escape($mutationUrl); ?>">Tinjau kategorinya</a> agar modal/prive/koreksi tidak tercampur biaya. Saldo tidak berubah saat kategori diperbaiki.
      </div>
    <?php endif; ?>

    <div class="fin-est-kpis">
      <div class="fin-est-kpi income">
        <div class="label">Penerimaan POS</div>
        <div class="value positive">Rp <?php echo number_format((float)($overview['total_sales'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="fin-est-kpi income">
        <div class="label">Pendapatan lain</div><div class="value positive"><?php echo $money($overview['total_other_income'] ?? 0); ?></div>
        <small>Termasuk selisih lebih yang sudah diklasifikasikan.</small>
      </div>
      <div class="fin-est-kpi expense">
        <div class="label">Total Pengeluaran</div>
        <div class="value negative">Rp <?php echo number_format((float)($overview['total_expense'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="fin-est-kpi expense">
        <div class="label">Est. Total Gaji</div>
        <div class="value">Rp <?php echo number_format((float)($overview['total_salary'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="fin-est-kpi expense">
        <div class="label">Refund POS</div><div class="value negative"><?php echo $money($overview['total_refund'] ?? 0); ?></div>
        <small>Terpisah dari pengeluaran agar tidak dihitung dua kali.</small>
      </div>
      <div class="fin-est-kpi result">
        <div class="label">Estimasi hasil operasional</div>
        <div class="value <?php echo ((float)($overview['total_final_profit'] ?? 0) < 0 ? 'negative' : 'positive'); ?>">Rp <?php echo number_format((float)($overview['total_final_profit'] ?? 0), 2, ',', '.'); ?></div>
      </div>
    </div>

    <div class="fin-est-bridge">
      <div><strong>Komposisi pengeluaran</strong><dl>
        <dt>Pembelian dibayar</dt><dd><?php echo $money($overview['total_purchase'] ?? 0); ?></dd>
        <dt>POS keluar lainnya</dt><dd><?php echo $money($overview['total_other_pos_out'] ?? 0); ?></dd>
        <dt>Biaya lain / promo / platform / selisih kurang</dt><dd><?php echo $money($overview['total_other_expense'] ?? 0); ?></dd>
        <dt>Mutasi keluar belum diklasifikasikan</dt><dd><?php echo $money($overview['total_unclassified_out'] ?? 0); ?></dd>
      </dl></div>
      <div><strong>Hanya memengaruhi saldo</strong><dl>
        <dt>Modal / koreksi saldo masuk</dt><dd><?php echo $money($overview['total_balance_only_in'] ?? 0); ?></dd>
        <dt>Prive / koreksi saldo keluar</dt><dd><?php echo $money($overview['total_balance_only_out'] ?? 0); ?></dd>
      </dl><small>Transfer, utang/piutang dan pencairan payroll tidak masuk hasil operasional ini. Lihat <a href="<?php echo site_url('finance-reports/cash-position'); ?>">Posisi Kas</a> untuk saldo rekening.</small></div>
    </div>
    <details class="fin-est-filter"><summary>Cara membaca promo online food dan rekonsiliasi</summary>
      <p class="mt-2">Jika POS sudah mencatat Rp100.000 dan dana settlement lengkap hanya Rp80.000 karena biaya usaha, catat selisih Rp20.000 sekali melalui rekonsiliasi pendapatan atau mutasi manual, bukan keduanya. Jika POS sudah mencatat Rp80.000 bersih, jangan potong Rp20.000 lagi.</p>
      <p>Gunakan kategori <strong>Promo ditanggung usaha</strong> atau <strong>Komisi / biaya platform</strong> sesuai bukti settlement. Dana yang masih menunggu cair bukan otomatis biaya. Setoran modal/prive memakai kategori tersendiri. Selisih yang belum diketahui penyebabnya tetap ditinjau, tidak ditebak dari catatan.</p>
      <p class="mb-0">Tanggal laporan mengikuti tanggal mutasi. Kategori baru memperbarui laporan berjalan, bukan otomatis menulis ulang snapshot target/tutup periode yang sudah tersimpan. Periode CLOSED harus dibuka melalui prosedur resmi bila klasifikasinya perlu diperbaiki.</p>
    </details>

    <div class="fin-est-table">
      <div class="table-responsive fin-est-scroll">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th class="text-end">Penerimaan POS</th>
              <th class="text-end">Refund</th>
              <th class="text-end">Pendapatan Lain</th>
              <th class="text-end">Pengeluaran</th>
              <th class="text-end">Hasil Sebelum Gaji</th>
              <th class="text-end">Estimasi Gaji</th>
              <th class="text-end">Hasil Operasional</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">Belum ada data untuk periode ini.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><a href="<?php echo html_escape(site_url('finance/mutations') . '?' . http_build_query(['date_from' => $row['date'], 'date_to' => $row['date']])); ?>"><?php echo html_escape((string)($row['date'] ?? '-')); ?></a>
                    <?php if ((int)($row['unclassified_count'] ?? 0) > 0): ?><span class="fin-est-sub text-warning"><?php echo (int)$row['unclassified_count']; ?> perlu klasifikasi</span><?php endif; ?>
                  </td>
                  <td class="text-end text-primary fw-semibold">Rp <?php echo number_format((float)($row['sales_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-danger">Rp <?php echo number_format((float)($row['refund_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-success"><?php echo $money($row['other_income_total'] ?? 0); ?></td>
                  <td class="text-end text-danger"><?php echo $money($row['expense_total'] ?? 0); ?>
                    <details><summary>Rincian</summary>
                      <div>Pembelian: <?php echo $money($row['purchase_total'] ?? 0); ?></div>
                      <div>Biaya lain: <?php echo $money($row['other_expense_total'] ?? 0); ?></div>
                      <div>↳ Promo: <?php echo $money($row['promo_total'] ?? 0); ?></div>
                      <div>↳ Platform: <?php echo $money($row['platform_fee_total'] ?? 0); ?></div>
                      <div>Belum diklasifikasikan: <?php echo $money($row['unclassified_out_total'] ?? 0); ?></div>
                      <div>POS keluar lainnya: <?php echo $money($row['other_pos_out_total'] ?? 0); ?></div>
                    </details>
                  </td>
                  <td class="text-end fw-semibold <?php echo ((float)($row['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($row['gross_profit'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-danger">
                    Rp <?php echo number_format((float)($row['salary_total'] ?? 0), 2, ',', '.'); ?>
                    <?php if ((float)($row['salary_total'] ?? 0) > 0): ?>
                      <span class="fin-est-sub">
                        Absen Rp <?php echo number_format((float)($row['attendance_base_total'] ?? 0), 2, ',', '.'); ?>
                        <?php if ((float)($row['overtime_total'] ?? 0) > 0): ?> + Lembur Rp <?php echo number_format((float)($row['overtime_total'] ?? 0), 2, ',', '.'); ?><?php endif; ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-bold <?php echo ((float)($row['final_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($row['final_profit'] ?? 0), 2, ',', '.'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <td class="fw-bold">Total</td>
              <td class="text-end fw-bold text-primary">Rp <?php echo number_format((float)($overview['total_sales'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold text-danger">Rp <?php echo number_format((float)($overview['total_refund'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold text-success"><?php echo $money($overview['total_other_income'] ?? 0); ?></td>
              <td class="text-end fw-bold text-danger">Rp <?php echo number_format((float)($overview['total_expense'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold <?php echo ((float)($overview['total_gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($overview['total_gross_profit'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold text-danger">Rp <?php echo number_format((float)($overview['total_salary'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold <?php echo ((float)($overview['total_final_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($overview['total_final_profit'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
