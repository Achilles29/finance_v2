<?php
$row = is_array($row ?? null) ? $row : [];
$snapshotRows = is_array($snapshot_rows ?? null) ? $snapshot_rows : [];
$metricRows = is_array($metric_rows ?? null) ? $metric_rows : [];
$snapshotSummary = is_array($snapshot_summary ?? null) ? $snapshot_summary : [];
$metricSummary = is_array($metric_summary ?? null) ? $metric_summary : [];
$status = strtoupper((string)($row['status'] ?? 'OPEN'));
$csrf = is_array($period_close_csrf ?? null) ? $period_close_csrf : [];
$canEdit = !empty($can_edit);
?>

<style>
  .finclose-card,
  .finclose-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .finclose-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .finclose-summary {
    border-radius: 18px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .finclose-summary .label {
    font-size: .78rem;
    text-transform: uppercase;
    color: #8b7a6f;
    letter-spacing: .04em;
  }
  .finclose-summary .value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .finclose-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
  }
  @media (max-width: 991.98px) {
    .finclose-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .finclose-summary-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
      <h4 class="mb-1">Detail Tutup Periode</h4>
      <div class="text-muted">
        <?php echo html_escape((string)($row['period_code'] ?? '-')); ?> |
        <?php echo html_escape((string)($row['period_start'] ?? '-')); ?> s/d <?php echo html_escape((string)($row['period_end'] ?? '-')); ?> |
        status <?php echo html_escape($status); ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo site_url('finance-reports/period-close'); ?>" class="btn btn-outline-secondary">Kembali ke daftar</a>
      <?php if ($canEdit && in_array($status, ['OPEN', 'REOPENED'], true)): ?>
        <form method="post" action="<?php echo site_url('finance-reports/period-close/process/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('Proses tutup periode ini sekarang? Snapshot lama untuk draft ini akan ditimpa.');">
          <input type="hidden" name="<?php echo html_escape((string)($csrf['name'] ?? '')); ?>" value="<?php echo html_escape((string)($csrf['value'] ?? '')); ?>">
          <button type="submit" class="btn btn-primary">Proses Close</button>
        </form>
      <?php elseif ($canEdit && $status === 'CLOSED'): ?>
        <form method="post" class="d-flex flex-wrap gap-2 align-items-center" action="<?php echo site_url('finance-reports/period-close/reopen/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('Buka ulang period ini? Setelah itu Anda bisa close ulang untuk rebuild snapshot.');">
          <input type="hidden" name="<?php echo html_escape((string)($csrf['name'] ?? '')); ?>" value="<?php echo html_escape((string)($csrf['value'] ?? '')); ?>">
          <label class="visually-hidden" for="period_reopen_step_up_password">Konfirmasi password</label>
          <input type="password" class="form-control" style="max-width:220px" id="period_reopen_step_up_password" name="step_up_password" autocomplete="current-password" maxlength="72" required placeholder="Konfirmasi password">
          <button type="submit" class="btn btn-warning">Reopen</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'period-close']); ?>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <div class="finclose-summary-grid mb-3">
    <div class="finclose-summary">
      <div class="label">Akun Tersnapshot</div>
      <div class="value"><?php echo number_format((int)($snapshotSummary['account_count'] ?? 0)); ?></div>
    </div>
    <div class="finclose-summary">
      <div class="label">Saldo Fisik Total</div>
      <div class="value">Rp <?php echo number_format((float)($snapshotSummary['physical_total'] ?? 0), 2, ',', '.'); ?></div>
    </div>
    <div class="finclose-summary">
      <div class="label">Saldo Riil Total</div>
      <div class="value">Rp <?php echo number_format((float)($snapshotSummary['real_total'] ?? 0), 2, ',', '.'); ?></div>
    </div>
    <div class="finclose-summary">
      <div class="label">Metric Tersimpan</div>
      <div class="value"><?php echo number_format((int)($metricSummary['metric_count'] ?? 0)); ?></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-5">
      <div class="card finclose-card h-100">
        <div class="card-body">
          <h5 class="mb-3">Info Draft</h5>
          <div class="small text-muted mb-2">Jenis period</div>
          <div class="fw-semibold mb-3"><?php echo html_escape((string)($row['period_type'] ?? '-')); ?> | versi <?php echo (int)($row['snapshot_version'] ?? 1); ?></div>

          <div class="small text-muted mb-2">Mode close</div>
          <div class="fw-semibold mb-3"><?php echo html_escape((string)($row['close_mode'] ?? '-')); ?></div>

          <div class="small text-muted mb-2">Eksposur snapshot</div>
          <div class="mb-2">Utang: <strong>Rp <?php echo number_format((float)($snapshotSummary['payable_total'] ?? 0), 2, ',', '.'); ?></strong></div>
          <div class="mb-2">Piutang: <strong>Rp <?php echo number_format((float)($snapshotSummary['receivable_total'] ?? 0), 2, ',', '.'); ?></strong></div>
          <div class="mb-2">Kasbon: <strong>Rp <?php echo number_format((float)($snapshotSummary['cash_advance_total'] ?? 0), 2, ',', '.'); ?></strong></div>
          <div class="mb-3">Payroll pending: <strong>Rp <?php echo number_format((float)($snapshotSummary['payroll_pending_total'] ?? 0), 2, ',', '.'); ?></strong></div>

          <div class="small text-muted mb-2">Distribusi metric</div>
          <div class="mb-2">Global: <strong><?php echo number_format((int)($metricSummary['global_count'] ?? 0)); ?></strong></div>
          <div class="mb-2">Divisi: <strong><?php echo number_format((int)($metricSummary['division_count'] ?? 0)); ?></strong></div>
          <div class="mb-3">Rekening: <strong><?php echo number_format((int)($metricSummary['account_count'] ?? 0)); ?></strong></div>

          <div class="small text-muted mb-2">Catatan</div>
          <div><?php echo html_escape((string)($row['notes'] ?? '-')); ?></div>
        </div>
      </div>
    </div>

    <div class="col-xl-7">
      <div class="card finclose-card h-100">
        <div class="card-body">
          <h5 class="mb-3">Snapshot Rekening</h5>
          <div class="table-responsive finclose-table">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Rekening</th>
                  <th class="text-end">Saldo Fisik</th>
                  <th class="text-end">Saldo Riil</th>
                  <th class="text-end">Utang</th>
                  <th class="text-end">Piutang</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($snapshotRows)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">Belum ada snapshot rekening untuk draft ini.</td></tr>
                <?php else: ?>
                  <?php foreach ($snapshotRows as $snap): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?php echo html_escape((string)($snap['account_name_snapshot'] ?? '-')); ?></div>
                        <div class="small text-muted"><?php echo html_escape((string)($snap['bank_name_snapshot'] ?? '-')); ?></div>
                      </td>
                      <td class="text-end">Rp <?php echo number_format((float)($snap['closing_balance_physical'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-end">Rp <?php echo number_format((float)($snap['closing_balance_real'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-end">Rp <?php echo number_format((float)($snap['payable_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-end">Rp <?php echo number_format((float)($snap['receivable_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card finclose-card">
        <div class="card-body">
          <h5 class="mb-3">Metric Period Close</h5>
          <div class="table-responsive finclose-table">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Scope</th>
                  <th>Group</th>
                  <th>Metric</th>
                  <th class="text-end">Amount</th>
                  <th class="text-end">Qty</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($metricRows)): ?>
                  <tr><td colspan="6" class="text-center text-muted py-4">Belum ada metric tersimpan untuk draft ini.</td></tr>
                <?php else: ?>
                  <?php foreach ($metricRows as $metric): ?>
                    <tr>
                      <td><?php echo html_escape((string)($metric['scope_type'] ?? '-')); ?><?php echo (int)($metric['scope_ref_id'] ?? 0) > 0 ? ' #' . (int)$metric['scope_ref_id'] : ''; ?></td>
                      <td><?php echo html_escape((string)($metric['metric_group'] ?? '-')); ?></td>
                      <td>
                        <div class="fw-semibold"><?php echo html_escape((string)($metric['metric_label'] ?? '-')); ?></div>
                        <div class="small text-muted"><?php echo html_escape((string)($metric['metric_code'] ?? '-')); ?></div>
                      </td>
                      <td class="text-end">Rp <?php echo number_format((float)($metric['metric_amount'] ?? 0), 2, ',', '.'); ?></td>
                      <td class="text-end"><?php echo number_format((float)($metric['metric_qty'] ?? 0), 4, ',', '.'); ?></td>
                      <td><?php echo html_escape((string)($metric['notes'] ?? '-')); ?></td>
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
</div>
