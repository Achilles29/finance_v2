<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$csrf = is_array($period_close_csrf ?? null) ? $period_close_csrf : [];
$canCreate = !empty($can_create);
$canEdit = !empty($can_edit);
$pg = is_array($pg ?? null) ? $pg : ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$baseUrl = site_url('finance-reports/period-close');
$buildUrl = static function (array $overrides = []) use ($filters, $pg, $baseUrl) {
    $query = [
        'q' => (string)($filters['q'] ?? ''),
        'status' => (string)($filters['status'] ?? ''),
        'period_type' => (string)($filters['period_type'] ?? ''),
        'per_page' => (int)($pg['per_page'] ?? 25),
        'page' => (int)($pg['page'] ?? 1),
    ];
    return $baseUrl . '?' . http_build_query(array_merge($query, $overrides));
};
?>

<style>
  .finbox-card,
  .finbox-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 26px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .finbox-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .finbox-summary {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .finbox-summary .label {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8b7a6f;
  }
  .finbox-summary .value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .finbox-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
  }
  @media (max-width: 991.98px) {
    .finbox-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .finbox-summary-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($page_title ?? 'Tutup Periode Keuangan')); ?></h4>
      <p class="fin-page-subtitle mb-0">Workspace untuk menyiapkan cut off bulanan/tahunan. Draft periode dibuat dulu di sini, lalu nanti dipakai untuk snapshot laporan yang sudah dikunci.</p>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'period-close']); ?>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <div class="finbox-summary-grid mb-3">
    <div class="finbox-summary">
      <div class="label">Total Period</div>
      <div class="value"><?php echo number_format((int)($summary['total_rows'] ?? 0)); ?></div>
    </div>
    <div class="finbox-summary">
      <div class="label">Sudah Closed</div>
      <div class="value"><?php echo number_format((int)($summary['closed_rows'] ?? 0)); ?></div>
    </div>
    <div class="finbox-summary">
      <div class="label">Masih Open</div>
      <div class="value"><?php echo number_format((int)($summary['open_rows'] ?? 0)); ?></div>
    </div>
    <div class="finbox-summary">
      <div class="label">Reopened</div>
      <div class="value"><?php echo number_format((int)($summary['reopened_rows'] ?? 0)); ?></div>
    </div>
  </div>

  <div class="card finbox-card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <h5 class="mb-1">Daftar Period Close</h5>
          <div class="small text-muted">Period close bulanan dan tahunan akan menjadi jangkar laporan yang sudah dibekukan.</div>
        </div>
        <?php if ($canCreate): ?><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#periodCloseModal">Buat Draft Period</button><?php endif; ?>
      </div>

      <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
          <label class="form-label mb-1">Cari</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Kode period atau catatan">
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Status</label>
          <select name="status" class="form-select">
            <option value="">Semua status</option>
            <?php foreach (['OPEN' => 'Open', 'CLOSED' => 'Closed', 'REOPENED' => 'Reopened', 'VOID' => 'Void'] as $k => $v): ?>
              <option value="<?php echo $k; ?>" <?php echo (($filters['status'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Jenis Period</label>
          <select name="period_type" class="form-select">
            <option value="">Semua jenis</option>
            <option value="MONTHLY" <?php echo (($filters['period_type'] ?? '') === 'MONTHLY') ? 'selected' : ''; ?>>Bulanan</option>
            <option value="YEARLY" <?php echo (($filters['period_type'] ?? '') === 'YEARLY') ? 'selected' : ''; ?>>Tahunan</option>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <a href="<?php echo site_url('finance-reports/period-close'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="table-responsive finbox-table">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Period</th>
              <th>Jenis</th>
              <th>Rentang</th>
              <th>Versi</th>
              <th>Status</th>
              <th>Mode</th>
              <th>Snapshot</th>
              <th>Catatan</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Belum ada draft period close.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo html_escape((string)($row['period_code'] ?? '-')); ?></div>
                    <div class="small text-muted">Dibuat: <?php echo html_escape((string)($row['created_at'] ?? '-')); ?></div>
                  </td>
                  <td><?php echo html_escape((string)($row['period_type'] ?? '-')); ?></td>
                  <td><?php echo html_escape((string)($row['period_start'] ?? '-')); ?> s/d <?php echo html_escape((string)($row['period_end'] ?? '-')); ?></td>
                  <td>v<?php echo (int)($row['snapshot_version'] ?? 1); ?></td>
                  <td><span class="badge bg-light text-dark border"><?php echo html_escape((string)($row['status'] ?? '-')); ?></span></td>
                  <td><?php echo html_escape((string)($row['close_mode'] ?? '-')); ?></td>
                  <td>
                    <div class="small">Rekening: <?php echo number_format((int)($row['snapshot_count'] ?? 0)); ?></div>
                    <div class="small text-muted">Metric: <?php echo number_format((int)($row['metric_count'] ?? 0)); ?></div>
                  </td>
                  <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                  <td class="text-end">
                    <a href="<?php echo site_url('finance-reports/period-close/detail/' . (int)$row['id']); ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                    <?php $status = strtoupper((string)($row['status'] ?? '')); ?>
                    <?php if ($canEdit && in_array($status, ['OPEN', 'REOPENED'], true)): ?>
                      <form method="post" action="<?php echo site_url('finance-reports/period-close/process/' . (int)$row['id']); ?>" onsubmit="return confirm('Proses tutup periode ini sekarang? Snapshot lama untuk draft ini akan ditimpa.');">
                        <input type="hidden" name="<?php echo html_escape((string)($csrf['name'] ?? '')); ?>" value="<?php echo html_escape((string)($csrf['value'] ?? '')); ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Proses Close</button>
                      </form>
                    <?php elseif (!in_array($status, ['OPEN', 'REOPENED'], true)): ?>
                      <span class="small text-muted">Sudah dikunci</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <div class="small text-muted">Menampilkan <?php echo number_format((int)($pg['total'] ?? 0)); ?> data, default 25 baris per halaman.</div>
        <div class="btn-group">
          <a class="btn btn-sm btn-outline-secondary <?php echo (($pg['page'] ?? 1) <= 1) ? 'disabled' : ''; ?>" href="<?php echo (($pg['page'] ?? 1) <= 1) ? '#' : $buildUrl(['page' => max(1, (int)$pg['page'] - 1)]); ?>">Prev</a>
          <button class="btn btn-sm btn-outline-secondary disabled">Hal <?php echo (int)($pg['page'] ?? 1); ?> / <?php echo (int)($pg['total_pages'] ?? 1); ?></button>
          <a class="btn btn-sm btn-outline-secondary <?php echo (($pg['page'] ?? 1) >= ($pg['total_pages'] ?? 1)) ? 'disabled' : ''; ?>" href="<?php echo (($pg['page'] ?? 1) >= ($pg['total_pages'] ?? 1)) ? '#' : $buildUrl(['page' => (int)$pg['page'] + 1]); ?>">Next</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($canCreate): ?>
<div class="modal fade" id="periodCloseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?php echo site_url('finance-reports/period-close/store'); ?>">
        <input type="hidden" name="<?php echo html_escape((string)($csrf['name'] ?? '')); ?>" value="<?php echo html_escape((string)($csrf['value'] ?? '')); ?>">
        <div class="modal-header">
          <h5 class="modal-title">Buat Draft Period Close</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Jenis Period</label>
              <select name="period_type" class="form-select" required>
                <option value="MONTHLY">Bulanan</option>
                <option value="YEARLY">Tahunan</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tanggal Awal</label>
              <input type="date" name="period_start" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tanggal Akhir</label>
              <input type="date" name="period_end" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Mode Snapshot</label>
              <select name="close_mode" class="form-select">
                <option value="AUTO_REBUILD">Auto rebuild</option>
                <option value="MANUAL_LOCK">Manual lock</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Catatan</label>
              <input type="text" name="notes" class="form-control" placeholder="Opsional, misal close Juni sebelum audit bulanan">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
