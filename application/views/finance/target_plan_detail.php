<?php
$row = is_array($row ?? null) ? $row : [];
$metricLines = is_array($metric_lines ?? null) ? $metric_lines : [];
$realizationSummary = is_array($realization_summary ?? null) ? $realization_summary : [];
$detailUrl = site_url('finance-reports/targets/detail/' . (int)($row['id'] ?? 0));
$status = strtoupper((string)($row['status'] ?? 'DRAFT'));
?>

<style>
  .fintdetail-card,
  .fintdetail-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .fintdetail-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .fintdetail-summary {
    border-radius: 18px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .fintdetail-summary .label {
    font-size: .78rem;
    text-transform: uppercase;
    color: #8b7a6f;
    letter-spacing: .04em;
  }
  .fintdetail-summary .value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .fintdetail-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
    vertical-align: middle;
    position: sticky;
    top: 0;
    z-index: 3;
  }
  .fintdetail-table-wrap {
    max-height: 72vh;
    overflow: auto;
    border-radius: 24px;
  }
  .fintdetail-table .form-control,
  .fintdetail-table .form-select {
    min-width: 110px;
  }
  .fintdetail-table .metric-note-input {
    min-width: 180px;
  }
  .fintdetail-helper {
    border: 1px solid rgba(143, 53, 58, .10);
    background: #fff8f4;
    border-radius: 16px;
    padding: .8rem .95rem;
  }
  .fintdetail-helper .title {
    font-size: .82rem;
    font-weight: 700;
    color: #6f222a;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .fintdetail-helper .body {
    font-size: .9rem;
    color: #6f5d56;
    line-height: 1.65;
  }
  @media (max-width: 991.98px) {
    .fintdetail-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .fintdetail-summary-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
      <h4 class="mb-1">Detail & Edit Target</h4>
      <div class="text-muted">
        <?php echo html_escape((string)($row['target_name'] ?? '-')); ?> |
        <?php echo html_escape((string)($row['date_start'] ?? '-')); ?> s/d <?php echo html_escape((string)($row['date_end'] ?? '-')); ?> |
        status <?php echo html_escape($status); ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo site_url('finance-reports/targets'); ?>" class="btn btn-outline-secondary">Kembali ke daftar</a>
      <?php if ($status !== 'VOID'): ?>
        <form method="post" action="<?php echo site_url('finance-reports/targets/realize/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('Hitung actual target ini sekarang?');">
          <input type="hidden" name="redirect_to" value="<?php echo html_escape($detailUrl); ?>">
          <button type="submit" class="btn btn-primary">Hitung Realisasi</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'target-plan']); ?>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <div class="fintdetail-summary-grid mb-3">
    <div class="fintdetail-summary">
      <div class="label">Jumlah Indikator</div>
      <div class="value"><?php echo number_format((int)($realizationSummary['line_count'] ?? 0)); ?></div>
    </div>
    <div class="fintdetail-summary">
      <div class="label">Indikator Wajib</div>
      <div class="value"><?php echo number_format((int)($realizationSummary['required_count'] ?? 0)); ?></div>
    </div>
    <div class="fintdetail-summary">
      <div class="label">Total Bobot</div>
      <div class="value"><?php echo number_format((float)($realizationSummary['weight_total'] ?? 0), 2, ',', '.'); ?>%</div>
    </div>
    <div class="fintdetail-summary">
      <div class="label">Rata-rata Skor</div>
      <div class="value"><?php echo number_format((float)($realizationSummary['avg_score_percent'] ?? 0), 2, ',', '.'); ?>%</div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12">
        <div class="card fintdetail-card">
        <div class="card-body">
          <h5 class="mb-3">Pengaturan Dasar</h5>
            <div class="small text-muted mb-3">Bagian ini dipakai untuk mengubah nama target, tanggal target, status target, aturan bonus, skor minimum, cadangan bonus, persen laba untuk bonus, dan catatan target.</div>
            <form method="post" action="<?php echo site_url('finance-reports/targets/update/' . (int)($row['id'] ?? 0)); ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Nama target</label>
                  <input type="text" name="target_name" class="form-control" value="<?php echo html_escape((string)($row['target_name'] ?? '')); ?>" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tanggal mulai target</label>
                  <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($row['date_start'] ?? '')); ?>" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tanggal selesai target</label>
                  <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($row['date_end'] ?? '')); ?>" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                  <?php foreach (['DRAFT' => 'Draft', 'ACTIVE' => 'Active', 'LOCKED' => 'Locked', 'VOID' => 'Void'] as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Aturan bonus</label>
                <select name="bonus_gate_mode" class="form-select">
                  <?php foreach (['WEIGHTED_SCORE' => 'Dinilai dari total skor', 'ALL_REQUIRED' => 'Semua indikator wajib lolos', 'NONE' => 'Tidak dipakai untuk bonus'] as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo strtoupper((string)($row['bonus_gate_mode'] ?? 'WEIGHTED_SCORE')) === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Skor minimal bonus</label>
                <input type="number" step="0.01" name="min_bonus_score" class="form-control" value="<?php echo html_escape((string)($row['min_bonus_score'] ?? '100')); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Cadangan bonus bila target lolos</label>
                <input type="number" step="0.01" name="bonus_pool_amount" class="form-control" value="<?php echo html_escape((string)($row['bonus_pool_amount'] ?? '0')); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">% laba yang dialokasikan ke bonus</label>
                <input type="number" step="0.0001" name="bonus_percent_of_profit" class="form-control" value="<?php echo html_escape((string)($row['bonus_percent_of_profit'] ?? '0')); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Jenis target</label>
                <input type="text" class="form-control" value="<?php echo html_escape((string)($row['target_scope'] ?? '-')); ?>" readonly>
              </div>
              <div class="col-md-4">
                <label class="form-label">Divisi yang dinilai</label>
                <input type="text" class="form-control" value="<?php echo html_escape((string)($row['division_name'] ?? 'Global / Semua divisi')); ?>" readonly>
              </div>
              <div class="col-md-4">
                <label class="form-label">Rekening fokus</label>
                <input type="text" class="form-control" value="<?php echo html_escape((string)($row['account_name'] ?? 'Semua rekening')); ?>" readonly>
              </div>
              <div class="col-12">
                <label class="form-label">Catatan singkat</label>
                <input type="text" name="notes" class="form-control" value="<?php echo html_escape((string)($row['notes'] ?? '')); ?>" placeholder="Misal fokus jaga food cost dan kontrol adjustment">
              </div>
              <div class="col-12">
                <div class="fintdetail-helper">
                  <div class="title mb-1">Cara baca bagian bonus</div>
                  <div class="body">
                    <strong>Cadangan bonus</strong> adalah plafon manajerial yang ingin Anda siapkan jika target lolos.
                    <strong>% laba untuk bonus</strong> adalah pagar agar bonus tidak melebihi porsi laba yang Anda anggap aman.
                    Jika Anda ingin skema seperti <strong>3% omzet harian</strong> tapi <strong>cair setelah target bulanan lolos</strong>, maka target ini berfungsi sebagai gerbangnya, sedangkan angka 3% dan ambang omzet harian tetap diatur di rule bonus.
                    <br><br>
                    Jika Anda mengubah tanggal target, snapshot realisasi lama akan dibersihkan supaya ringkasan tidak tercampur dengan rentang tanggal yang lama.
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-3 d-flex justify-content-end">
              <button type="submit" class="btn btn-primary">Simpan Pengaturan Dasar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card fintdetail-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
              <h5 class="mb-1">Indikator Penilaian</h5>
              <div class="small text-muted">Di sini Anda mengatur angka target, arah penilaian, bobot, dan indikator mana yang wajib lolos.</div>
            </div>
            <div class="small text-muted">
              Lolos: <?php echo number_format((int)($realizationSummary['passed_count'] ?? 0)); ?> baris
              <?php if (!empty($realizationSummary['last_realization_date'])): ?>
                | Actual terakhir: <?php echo html_escape((string)$realizationSummary['last_realization_date']); ?>
              <?php endif; ?>
            </div>
          </div>

          <form method="post" action="<?php echo site_url('finance-reports/targets/lines-save/' . (int)($row['id'] ?? 0)); ?>">
            <div class="fintdetail-helper mb-3">
              <div class="title mb-1">Saran pengisian indikator</div>
              <div class="body">
                Gunakan indikator wajib hanya untuk hal yang benar-benar harus lolos, misalnya omzet minimal, margin profit minimal, atau batas adjustment yang tidak boleh jebol.
                Untuk stok mengendap, sementara ini paling aman memakai <strong>Stok Akhir Gudang</strong> dan <strong>Stok Akhir Divisi</strong> sebagai penjaga akhir periode.
              </div>
            </div>

            <div class="table-responsive fintdetail-table fintdetail-table-wrap">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Indikator</th>
                    <th>Arah Nilai</th>
                    <th class="text-end">Target</th>
                    <th class="text-end">Min</th>
                    <th class="text-end">Max</th>
                    <th class="text-end">Warning</th>
                    <th class="text-end">Bobot %</th>
                    <th>Wajib</th>
                    <th class="text-end">Actual</th>
                    <th class="text-end">Skor</th>
                    <th>Catatan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($metricLines)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">Belum ada metric line untuk target ini.</td></tr>
                  <?php else: ?>
                    <?php foreach ($metricLines as $idx => $line): ?>
                      <?php $lineId = (int)($line['id'] ?? 0); ?>
                      <tr>
                        <td>
                          <input type="hidden" name="line_id[]" value="<?php echo $lineId; ?>">
                          <input type="hidden" name="metric_code[]" value="<?php echo html_escape((string)($line['metric_code'] ?? '')); ?>">
                          <div class="fw-semibold"><?php echo html_escape((string)($line['metric_label'] ?? '-')); ?></div>
                          <div class="small text-muted"><?php echo html_escape((string)($line['metric_group'] ?? '-')); ?> | <?php echo html_escape((string)($line['metric_code'] ?? '-')); ?></div>
                        </td>
                        <td>
                          <?php
                            $metricCode = strtoupper(trim((string)($line['metric_code'] ?? '')));
                            $displayComparator = strtoupper(trim((string)($line['comparator'] ?? 'MIN')));
                            $forceMinDisplay = [
                              'POS_REVENUE',
                              'ESTIMATED_PROFIT_VALUE',
                              'ESTIMATED_PROFIT_PERCENT',
                              'PAYROLL_ESTIMATE_RUNNING',
                              'RAW_MATERIAL_IN_VALUE',
                              'RAW_MATERIAL_USAGE_VALUE',
                            ];
                            $forceMaxDisplay = [
                              'POS_REFUND',
                              'PURCHASE_RAW_MATERIAL',
                              'PURCHASE_OPERATIONAL',
                              'PURCHASE_UTILITY',
                              'PURCHASE_ASSET',
                              'PURCHASE_OTHER',
                              'PAYABLE_OUTSTANDING',
                              'RECEIVABLE_OUTSTANDING',
                              'CASH_ADVANCE_OUTSTANDING',
                              'LIVE_HPP_VALUE',
                              'WAREHOUSE_ADJUSTMENT_VALUE',
                              'DIVISION_ADJUSTMENT_VALUE',
                              'COMPONENT_ADJUSTMENT_VALUE',
                            ];
                            if (in_array($metricCode, $forceMinDisplay, true)) {
                              $displayComparator = 'MIN';
                            } elseif (in_array($metricCode, $forceMaxDisplay, true)) {
                              $displayComparator = 'MAX';
                            }
                          ?>
                          <select name="comparator[]" class="form-select form-select-sm">
                            <?php foreach (['MIN' => 'Semakin besar semakin baik', 'MAX' => 'Semakin kecil semakin baik', 'RANGE' => 'Harus di rentang tertentu', 'EQUAL' => 'Harus sama persis'] as $comp => $label): ?>
                              <option value="<?php echo $comp; ?>" <?php echo $displayComparator === $comp ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td><input type="number" step="0.01" name="target_value[]" class="form-control form-control-sm text-end" value="<?php echo html_escape((string)($line['target_value'] ?? '0')); ?>"></td>
                        <td><input type="number" step="0.01" name="minimum_value[]" class="form-control form-control-sm text-end" value="<?php echo html_escape((string)($line['minimum_value'] ?? '')); ?>"></td>
                        <td><input type="number" step="0.01" name="maximum_value[]" class="form-control form-control-sm text-end" value="<?php echo html_escape((string)($line['maximum_value'] ?? '')); ?>"></td>
                        <td><input type="number" step="0.01" name="warning_value[]" class="form-control form-control-sm text-end" value="<?php echo html_escape((string)($line['warning_value'] ?? '')); ?>"></td>
                        <td><input type="number" step="0.0001" name="weight_percent[]" class="form-control form-control-sm text-end" value="<?php echo html_escape((string)($line['weight_percent'] ?? '0')); ?>"></td>
                        <td class="text-center">
                          <input type="checkbox" name="is_required[<?php echo $lineId; ?>]" value="1" <?php echo (int)($line['is_required'] ?? 0) === 1 ? 'checked' : ''; ?>>
                        </td>
                        <td class="text-end">
                          <div><?php echo $line['latest_actual_value'] !== null ? number_format((float)$line['latest_actual_value'], 2, ',', '.') : '-'; ?></div>
                          <div class="small text-muted"><?php echo !empty($line['latest_realization_date']) ? html_escape((string)$line['latest_realization_date']) : 'belum ada'; ?></div>
                        </td>
                        <td class="text-end">
                          <div><?php echo $line['latest_score_percent'] !== null ? number_format((float)$line['latest_score_percent'], 2, ',', '.') . '%' : '-'; ?></div>
                          <div class="small <?php echo !empty($line['latest_is_passed']) ? 'text-success' : 'text-muted'; ?>">
                            <?php echo $line['latest_score_percent'] !== null ? (!empty($line['latest_is_passed']) ? 'lolos' : 'belum') : '-'; ?>
                          </div>
                        </td>
                        <td><input type="text" name="line_notes[]" class="form-control form-control-sm metric-note-input" value="<?php echo html_escape((string)($line['notes'] ?? '')); ?>" placeholder="Opsional"></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if (!empty($metricLines)): ?>
              <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small text-muted">Saran: total bobot idealnya 100% agar pembacaan hasil lebih seimbang.</div>
                <button type="submit" class="btn btn-primary">Simpan Indikator Target</button>
              </div>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
