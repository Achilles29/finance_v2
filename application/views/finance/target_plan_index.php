<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$progressRows = is_array($progress_rows ?? null) ? $progress_rows : [];
$tab = in_array(($tab ?? 'list'), ['list', 'progress', 'guide'], true) ? $tab : 'list';
$pg = is_array($pg ?? null) ? $pg : ['page' => 1, 'total_pages' => 1, 'per_page' => 50, 'total' => 0];
$divisionOptions = is_array($division_options ?? null) ? $division_options : [];
$companyAccounts = is_array($company_accounts ?? null) ? $company_accounts : [];
$metricCatalog = is_array($metric_catalog ?? null) ? $metric_catalog : [];
$baseUrl = site_url('finance-reports/targets');
$statusTab = strtoupper((string)($filters['status'] ?? ''));
$buildUrl = static function (array $overrides = []) use ($filters, $pg, $baseUrl, $tab) {
    $query = [
        'q' => (string)($filters['q'] ?? ''),
        'status' => (string)($filters['status'] ?? ''),
        'target_scope' => (string)($filters['target_scope'] ?? ''),
        'division_id' => (int)($filters['division_id'] ?? 0),
        'date_start' => (string)($filters['date_start'] ?? ''),
        'date_end' => (string)($filters['date_end'] ?? ''),
        'tab' => $tab,
        'per_page' => (int)($pg['per_page'] ?? 50),
        'page' => (int)($pg['page'] ?? 1),
    ];
    return $baseUrl . '?' . http_build_query(array_merge($query, $overrides));
};
?>

<style>
  .fintarget-card,
  .fintarget-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 26px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .fintarget-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .fintarget-summary {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .fintarget-summary .label {
    font-size: .78rem;
    text-transform: uppercase;
    color: #8b7a6f;
    letter-spacing: .04em;
  }
  .fintarget-summary .value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .fintarget-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
    position: sticky;
    top: 0;
    z-index: 3;
  }
  .fintarget-table-wrap {
    max-height: 68vh;
    overflow: auto;
    border-radius: 26px;
  }
  .fintarget-metric-preview {
    max-height: 240px;
    overflow: auto;
    border: 1px dashed rgba(143, 53, 58, .18);
    border-radius: 18px;
    padding: .85rem .95rem;
    background: #fffaf6;
  }
  .fintarget-guide-box {
    border: 1px dashed rgba(143, 53, 58, .18);
    border-radius: 18px;
    background: linear-gradient(180deg, #fffdfb, #fff6f1);
    padding: 1rem 1.1rem;
    height: 100%;
  }
  .fintarget-guide-box h6 {
    color: #6f222a;
    font-weight: 700;
  }
  .fintarget-guide-box .small {
    line-height: 1.6;
  }
  .fintarget-helper {
    border: 1px solid rgba(143, 53, 58, .10);
    background: #fff8f4;
    border-radius: 16px;
    padding: .75rem .9rem;
  }
  .fintarget-helper .title {
    font-size: .82rem;
    font-weight: 700;
    color: #6f222a;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .fintarget-helper .body {
    font-size: .88rem;
    color: #6f5d56;
    line-height: 1.6;
  }
  .fintarget-progress-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  .fintarget-progress-card {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 20px;
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .fintarget-progress-bar {
    height: 10px;
    border-radius: 999px;
    background: #f3dfd8;
    overflow: hidden;
  }
  .fintarget-progress-bar > span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #8f353a, #d56752);
    border-radius: 999px;
  }
  .fintarget-line-pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    padding: .34rem .6rem;
    border-radius: 999px;
    background: #fff3ef;
    border: 1px solid rgba(143, 53, 58, .10);
      font-size: .78rem;
      color: #7a4a3b;
    }
    .fintarget-progress-list {
      display: grid;
      gap: .45rem;
    }
    .fintarget-progress-line {
      border: 1px solid rgba(143, 53, 58, .08);
      border-radius: 14px;
      background: #fffaf7;
      padding: .5rem .65rem;
      font-size: .82rem;
    }
    .fintarget-progress-line .metric {
      font-weight: 600;
      color: #5e2b2b;
    }
    .fintarget-progress-line .meta {
      color: #7b6a61;
      font-size: .76rem;
    }
    .fintarget-live-badge {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .28rem .6rem;
      border-radius: 999px;
      background: #eef8f1;
      color: #237548;
      font-size: .78rem;
      font-weight: 600;
      border: 1px solid rgba(35, 117, 72, .12);
    }
  .fintarget-config-box {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 18px;
    background: #fffaf7;
    padding: .9rem;
  }
  .fintarget-config-table-wrap {
    max-height: 320px;
    overflow: auto;
    border: 1px solid rgba(143, 53, 58, .08);
    border-radius: 16px;
  }
  .fintarget-config-table {
    min-width: 1100px;
    margin-bottom: 0;
  }
  .fintarget-config-table thead th {
    position: sticky;
    top: 0;
    background: #8f353a;
    color: #fff;
    z-index: 2;
    white-space: nowrap;
  }
  .fintarget-config-table td {
    vertical-align: middle;
  }
  .fintarget-filter-pills {
    display: inline-flex;
    flex-wrap: wrap;
    gap: .5rem;
  }
  .fintarget-date-wrap,
  .fintarget-target-wrap {
    white-space: normal;
    line-height: 1.45;
  }
  .fintarget-target-wrap .code {
    display: block;
    margin-top: .2rem;
    font-size: .8rem;
    color: #8b7a6f;
  }
  @media (max-width: 991.98px) {
    .fintarget-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .fintarget-progress-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 575.98px) {
    .fintarget-summary-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($page_title ?? 'Target Keuangan')); ?></h4>
      <p class="fin-page-subtitle mb-0">Halaman ini dipakai untuk menyusun target usaha, memantau hasilnya, lalu menilai apakah performa sudah cukup untuk bonus.</p>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'target-plan']); ?>

  <?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
  <?php endif; ?>
  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <div class="fintarget-summary-grid mb-3">
    <div class="fintarget-summary">
      <div class="label">Total Target</div>
      <div class="value"><?php echo number_format((int)($summary['total_rows'] ?? 0)); ?></div>
    </div>
    <div class="fintarget-summary">
      <div class="label">Draft</div>
      <div class="value"><?php echo number_format((int)($summary['draft_rows'] ?? 0)); ?></div>
    </div>
    <div class="fintarget-summary">
      <div class="label">Aktif</div>
      <div class="value"><?php echo number_format((int)($summary['active_rows'] ?? 0)); ?></div>
    </div>
    <div class="fintarget-summary">
      <div class="label">Dikunci</div>
      <div class="value"><?php echo number_format((int)($summary['locked_rows'] ?? 0)); ?></div>
    </div>
  </div>

  <div class="card fintarget-card mb-3">
    <div class="card-body">
      <ul class="nav nav-pills gap-2 mb-3" id="targetPageTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="btn btn-sm <?php echo $tab === 'list' ? 'btn-primary' : 'btn-outline-primary'; ?> <?php echo $tab === 'list' ? 'active' : ''; ?>" id="target-list-tab" data-tab-key="list" data-bs-toggle="pill" data-bs-target="#target-list-pane" type="button" role="tab" aria-controls="target-list-pane" aria-selected="<?php echo $tab === 'list' ? 'true' : 'false'; ?>">Daftar Target</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="btn btn-sm <?php echo $tab === 'progress' ? 'btn-primary' : 'btn-outline-primary'; ?> <?php echo $tab === 'progress' ? 'active' : ''; ?>" id="target-progress-tab" data-tab-key="progress" data-bs-toggle="pill" data-bs-target="#target-progress-pane" type="button" role="tab" aria-controls="target-progress-pane" aria-selected="<?php echo $tab === 'progress' ? 'true' : 'false'; ?>">Target vs Realisasi</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="btn btn-sm <?php echo $tab === 'guide' ? 'btn-primary' : 'btn-outline-primary'; ?> <?php echo $tab === 'guide' ? 'active' : ''; ?>" id="target-guide-tab" data-tab-key="guide" data-bs-toggle="pill" data-bs-target="#target-guide-pane" type="button" role="tab" aria-controls="target-guide-pane" aria-selected="<?php echo $tab === 'guide' ? 'true' : 'false'; ?>">Panduan</button>
          </li>
        </ul>

      <div class="tab-content" id="targetPageTabsContent">
        <div class="tab-pane fade <?php echo $tab === 'list' ? 'show active' : ''; ?>" id="target-list-pane" role="tabpanel" aria-labelledby="target-list-tab" tabindex="0">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div><h5 class="mb-0">Daftar Target</h5></div>
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dailyRangeModal">Generate Target Harian</button>
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#targetModal">Buat Target Baru</button>
            </div>
          </div>

          <form method="get" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="tab" value="list">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="status" value="<?php echo html_escape((string)($filters['status'] ?? '')); ?>">
            <div class="col-md-4">
              <label class="form-label mb-1">Cari target</label>
              <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Cari nama target atau catatan singkat">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Jenis target</label>
              <select name="target_scope" class="form-select">
                <option value="">Semua</option>
                <?php foreach (['DAILY' => 'Harian', 'MONTHLY' => 'Bulanan', 'YEARLY' => 'Tahunan'] as $k => $v): ?>
                  <option value="<?php echo $k; ?>" <?php echo (($filters['target_scope'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Mulai target</label>
              <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Sampai target</label>
              <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Divisi</label>
              <select name="division_id" class="form-select">
                <option value="0">Semua divisi</option>
                <?php foreach ($divisionOptions as $division): ?>
                  <option value="<?php echo (int)$division['id']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$division['id']) ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$division['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Baris halaman</label>
              <select name="per_page" class="form-select">
                <?php foreach ([25, 50, 100] as $perPageOpt): ?>
                  <option value="<?php echo $perPageOpt; ?>" <?php echo ((int)($pg['per_page'] ?? 50) === $perPageOpt) ? 'selected' : ''; ?>><?php echo $perPageOpt; ?> baris</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
              <button type="submit" class="btn btn-primary w-100">Terapkan</button>
              <a href="<?php echo site_url('finance-reports/targets'); ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="fintarget-filter-pills">
              <a href="<?php echo $buildUrl(['status' => '', 'page' => 1]); ?>" class="btn btn-sm <?php echo $statusTab === '' ? 'btn-primary' : 'btn-outline-primary'; ?>">Semua</a>
              <a href="<?php echo $buildUrl(['status' => 'ACTIVE', 'page' => 1]); ?>" class="btn btn-sm <?php echo $statusTab === 'ACTIVE' ? 'btn-primary' : 'btn-outline-primary'; ?>">Aktif</a>
              <a href="<?php echo $buildUrl(['status' => 'NONACTIVE', 'page' => 1]); ?>" class="btn btn-sm <?php echo $statusTab === 'NONACTIVE' ? 'btn-primary' : 'btn-outline-primary'; ?>">Nonaktif</a>
            </div>
            <form method="post" action="<?php echo site_url('finance-reports/targets/realize-bulk'); ?>" id="bulkRealizeForm" class="d-flex flex-wrap gap-2 align-items-center">
              <input type="hidden" name="redirect_to" value="<?php echo html_escape($buildUrl([])); ?>">
              <button type="submit" class="btn btn-outline-primary btn-sm" onclick="return confirm('Hitung ulang hasil untuk semua target yang dicentang? Hasil lama akan ditimpa.');">Hitung Hasil Terpilih</button>
            </form>
          </div>

          <div class="table-responsive fintarget-table fintarget-table-wrap">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:46px;">
                    <input type="checkbox" class="form-check-input" id="bulkTargetToggle">
                  </th>
                  <th>Nama Target</th>
                  <th>Jenis</th>
                  <th>Periode</th>
                  <th>Fokus</th>
                  <th>Syarat Bonus</th>
                  <th>Indikator</th>
                  <th>Status</th>
                  <th>Hasil Saat Ini</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="10" class="text-center text-muted py-4">Belum ada target yang dibuat.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <?php $status = strtoupper((string)($row['status'] ?? '')); ?>
                    <tr>
                      <td>
                        <?php if ($status !== 'VOID'): ?>
                          <input
                            type="checkbox"
                            class="form-check-input js-target-bulk-item"
                            name="target_ids[]"
                            value="<?php echo (int)$row['id']; ?>"
                            form="bulkRealizeForm">
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="fintarget-target-wrap">
                          <div class="fw-semibold"><?php echo html_escape((string)($row['target_name'] ?? '-')); ?></div>
                          <span class="code"><?php echo html_escape((string)($row['target_code'] ?? '-')); ?></span>
                        </div>
                      </td>
                      <td><?php echo html_escape((string)($row['target_scope'] ?? '-')); ?></td>
                      <td>
                        <div class="fintarget-date-wrap">
                          <?php echo html_escape((string)($row['date_start'] ?? '-')); ?><br>
                          <span class="text-muted">s/d <?php echo html_escape((string)($row['date_end'] ?? '-')); ?></span>
                        </div>
                      </td>
                      <td>
                        <div><?php echo html_escape((string)($row['division_name'] ?? 'Semua divisi')); ?></div>
                        <div class="small text-muted"><?php echo html_escape((string)($row['account_name'] ?? 'Semua rekening')); ?></div>
                      </td>
                      <td>
                        <div><?php echo html_escape((string)($row['bonus_gate_mode'] ?? '-')); ?></div>
                        <div class="small text-muted">Skor minimal bonus: <?php echo number_format((float)($row['min_bonus_score'] ?? 0), 2, ',', '.'); ?></div>
                      </td>
                      <td><?php echo number_format((int)($row['metric_count'] ?? 0)); ?> indikator</td>
                      <td><span class="badge bg-light text-dark border"><?php echo html_escape((string)($row['status'] ?? '-')); ?></span></td>
                      <td>
                        <div class="small">Baris hasil: <?php echo number_format((int)($row['realization_count'] ?? 0)); ?></div>
                        <div class="small text-muted">Rata-rata skor: <?php echo number_format((float)($row['avg_score_percent'] ?? 0), 2, ',', '.'); ?>%</div>
                        <div class="small text-muted"><?php echo !empty($row['last_realization_date']) ? 'Update terakhir ' . html_escape((string)$row['last_realization_date']) : 'Belum dihitung'; ?></div>
                      </td>
                      <td class="text-end">
                        <a href="<?php echo site_url('finance-reports/targets/detail/' . (int)$row['id']); ?>" class="btn btn-sm btn-outline-secondary">Detail / Edit</a>
                        <?php if ($status !== 'VOID'): ?>
                          <form method="post" action="<?php echo site_url('finance-reports/targets/realize/' . (int)$row['id']); ?>" onsubmit="return confirm('Hitung hasil aktual target ini sekarang?');">
                            <input type="hidden" name="redirect_to" value="<?php echo html_escape($buildUrl([])); ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Hitung Hasil</button>
                          </form>
                        <?php else: ?>
                          <span class="small text-muted">VOID</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <div class="small text-muted">Menampilkan <?php echo number_format((int)($pg['total'] ?? 0)); ?> data, default 50 baris per halaman.</div>
            <div class="btn-group">
              <a class="btn btn-sm btn-outline-secondary <?php echo (($pg['page'] ?? 1) <= 1) ? 'disabled' : ''; ?>" href="<?php echo (($pg['page'] ?? 1) <= 1) ? '#' : $buildUrl(['page' => max(1, (int)$pg['page'] - 1)]); ?>">Prev</a>
              <button class="btn btn-sm btn-outline-secondary disabled">Hal <?php echo (int)($pg['page'] ?? 1); ?> / <?php echo (int)($pg['total_pages'] ?? 1); ?></button>
              <a class="btn btn-sm btn-outline-secondary <?php echo (($pg['page'] ?? 1) >= ($pg['total_pages'] ?? 1)) ? 'disabled' : ''; ?>" href="<?php echo (($pg['page'] ?? 1) >= ($pg['total_pages'] ?? 1)) ? '#' : $buildUrl(['page' => (int)$pg['page'] + 1]); ?>">Next</a>
            </div>
          </div>
        </div>

        <div class="tab-pane fade <?php echo $tab === 'progress' ? 'show active' : ''; ?>" id="target-progress-pane" role="tabpanel" aria-labelledby="target-progress-tab" tabindex="0">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h5 class="mb-0">Target vs Realisasi</h5>
          </div>

          <form method="get" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="tab" value="progress">
            <input type="hidden" name="status" value="ACTIVE">
            <input type="hidden" name="page" value="1">
            <div class="col-md-3">
              <label class="form-label mb-1">Cari target</label>
              <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Cari nama target">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Jenis target</label>
              <select name="target_scope" class="form-select">
                <option value="">Semua</option>
                <?php foreach (['MONTHLY' => 'Bulanan', 'DAILY' => 'Harian', 'YEARLY' => 'Tahunan'] as $k => $v): ?>
                  <option value="<?php echo $k; ?>" <?php echo (($filters['target_scope'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Mulai target</label>
              <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Sampai target</label>
              <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>">
            </div>
            <div class="col-md-1">
              <label class="form-label mb-1">Baris</label>
              <select name="per_page" class="form-select">
                <?php foreach ([25, 50, 100] as $perPageOpt): ?>
                  <option value="<?php echo $perPageOpt; ?>" <?php echo ((int)($pg['per_page'] ?? 50) === $perPageOpt) ? 'selected' : ''; ?>><?php echo $perPageOpt; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
              <button type="submit" class="btn btn-primary w-100">Terapkan</button>
              <a href="<?php echo site_url('finance-reports/targets?tab=progress'); ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>

          <div class="table-responsive fintarget-table fintarget-table-wrap">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Target</th>
                  <th>Realisasi</th>
                  <th>%</th>
                  <th>Bonus</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($progressRows)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data target aktif.</td></tr>
                <?php else: ?>
                  <?php foreach ($progressRows as $row): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold fintarget-date-wrap">
                          <?php echo html_escape((string)($row['date_start'] ?? '-')); ?>
                          <?php if (($row['date_end'] ?? '') !== ($row['date_start'] ?? '')): ?>
                            <br><span class="text-muted">s/d <?php echo html_escape((string)($row['date_end'] ?? '-')); ?></span>
                          <?php endif; ?>
                        </div>
                        <div class="small text-muted"><?php echo html_escape((string)($row['target_scope'] ?? '-')); ?></div>
                      </td>
                      <td>
                        <div class="fw-semibold fintarget-target-wrap"><?php echo html_escape((string)($row['target_name'] ?? '-')); ?></div>
                        <div class="small text-muted"><?php echo html_escape((string)($row['division_name'] ?? 'Semua divisi')); ?></div>
                        <?php if (!empty($row['progress_target_summary'])): ?>
                          <?php foreach ($row['progress_target_summary'] as $item): ?>
                            <div class="small mb-1">
                              <span class="fw-semibold"><?php echo html_escape((string)($item['metric_label'] ?? '-')); ?></span>
                              <span class="text-muted">: <?php echo number_format((float)($item['target_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <div class="small text-muted">Belum ada indikator target.</div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="small fw-semibold mb-1">Progress</div>
                        <?php if (!empty($row['progress_live_summary'])): ?>
                          <?php foreach ($row['progress_live_summary'] as $item): ?>
                            <div class="small mb-1">
                              <span class="fw-semibold"><?php echo html_escape((string)($item['metric_label'] ?? '-')); ?></span>
                              <span class="text-muted">: <?php echo number_format((float)($item['actual_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <div class="small text-muted mb-2">Belum ada angka berjalan.</div>
                        <?php endif; ?>

                        <div class="small fw-semibold mt-2 mb-1">Snapshot</div>
                        <?php if (!empty($row['progress_snapshot_summary'])): ?>
                          <?php foreach ($row['progress_snapshot_summary'] as $item): ?>
                            <div class="small mb-1">
                              <span class="fw-semibold"><?php echo html_escape((string)($item['metric_label'] ?? '-')); ?></span>
                              <span class="text-muted">: <?php echo number_format((float)($item['actual_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <div class="small text-muted">Belum ada snapshot.</div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="small mb-2">
                          <span class="fw-semibold">Progress:</span>
                          <span class="text-muted"><?php echo $row['progress_live_score_percent'] !== null ? number_format((float)$row['progress_live_score_percent'], 2, ',', '.') . '%' : '-'; ?></span>
                        </div>
                        <div class="small">
                          <span class="fw-semibold">Snapshot:</span>
                          <span class="text-muted"><?php echo $row['progress_snapshot_score_percent'] !== null ? number_format((float)$row['progress_snapshot_score_percent'], 2, ',', '.') . '%' : '-'; ?></span>
                        </div>
                      </td>
                      <td>
                        <?php $liveBonus = (array)($row['progress_live_bonus'] ?? []); ?>
                        <?php $snapshotBonus = (array)($row['progress_snapshot_bonus'] ?? []); ?>
                        <div class="small fw-semibold mb-1">Progress</div>
                        <div class="small mb-2">
                          <span class="fw-semibold"><?php echo html_escape((string)($liveBonus['label'] ?? '-')); ?></span>
                          <?php if (!empty($liveBonus['detail'])): ?>
                            <div class="text-muted"><?php echo html_escape((string)$liveBonus['detail']); ?></div>
                          <?php endif; ?>
                        </div>

                        <div class="small fw-semibold mt-2 mb-1">Snapshot</div>
                        <div class="small">
                          <span class="fw-semibold"><?php echo html_escape((string)($snapshotBonus['label'] ?? '-')); ?></span>
                          <?php if (!empty($snapshotBonus['detail'])): ?>
                            <div class="text-muted"><?php echo html_escape((string)$snapshotBonus['detail']); ?></div>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <div class="small text-muted">Bulanan ditampilkan lebih dulu, lalu harian. Default 50 baris per halaman.</div>
            <div class="btn-group">
              <a class="btn btn-sm btn-outline-secondary <?php echo (($pg['page'] ?? 1) <= 1) ? 'disabled' : ''; ?>" href="<?php echo (($pg['page'] ?? 1) <= 1) ? '#' : $buildUrl(['page' => max(1, (int)$pg['page'] - 1)]); ?>">Prev</a>
              <button class="btn btn-sm btn-outline-secondary disabled">Hal <?php echo (int)($pg['page'] ?? 1); ?> / <?php echo (int)($pg['total_pages'] ?? 1); ?></button>
              <a class="btn btn-sm btn-outline-secondary <?php echo (($pg['page'] ?? 1) >= ($pg['total_pages'] ?? 1)) ? 'disabled' : ''; ?>" href="<?php echo (($pg['page'] ?? 1) >= ($pg['total_pages'] ?? 1)) ? '#' : $buildUrl(['page' => (int)$pg['page'] + 1]); ?>">Next</a>
            </div>
          </div>
        </div>

        <div class="tab-pane fade <?php echo $tab === 'guide' ? 'show active' : ''; ?>" id="target-guide-pane" role="tabpanel" aria-labelledby="target-guide-tab" tabindex="0">
          <div class="row g-3">
            <div class="col-lg-4">
              <div class="fintarget-guide-box">
                <h6 class="mb-2">1. Target ini dipakai untuk apa?</h6>
                <div class="small text-muted">
                  Target dipakai untuk menetapkan angka yang ingin dicapai, misalnya omzet, kontrol belanja, HPP, atau disiplin biaya. Setelah itu sistem akan membandingkan target dengan hasil nyatanya.
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="fintarget-guide-box">
                <h6 class="mb-2">2. Cara paling mudah membuat target</h6>
                <div class="small text-muted">
                  Isi dulu nama target, pilih harian, bulanan, atau tahunan, tentukan rentangnya, lalu pilih beberapa indikator awal. Setelah tersimpan, buka detail target untuk mengisi angka target dan bobotnya.
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="fintarget-guide-box">
                <h6 class="mb-2">3. Kapan hasilnya bisa dihitung?</h6>
                <div class="small text-muted">
                  Untuk target bulanan atau tahunan, sebaiknya periode keuangannya sudah ditutup dulu. Untuk target harian, sistem masih bisa membaca data live jika period close belum ada.
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="fintarget-guide-box">
                <h6 class="mb-2">Arti istilah penting</h6>
                <div class="small text-muted">
                  <strong>Jenis target</strong>: target harian, bulanan, atau tahunan.<br>
                  <strong>Indikator</strong>: hal yang dinilai, misalnya omzet, refund, HPP, belanja operasional.<br>
                  <strong>Syarat bonus</strong>: aturan apakah target ini ikut menentukan bonus atau tidak.<br>
                  <strong>Bobot</strong>: seberapa besar pengaruh indikator terhadap total skor.
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="fintarget-guide-box">
                <h6 class="mb-2">Contoh sederhana</h6>
                <div class="small text-muted">
                  Misalnya Anda ingin membuat target bulanan Juni untuk outlet BAR:
                  <br>- omzet minimal Rp 300 juta
                  <br>- refund maksimal Rp 2 juta
                  <br>- HPP live maksimal 38%
                  <br>- adjustment gudang maksimal Rp 500 ribu
                  <br><br>
                  Setelah itu baru atur mana yang wajib lolos dan berapa bobot tiap indikator.
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="fintarget-guide-box">
                <h6 class="mb-2">Contoh bonus harian 3% tapi cairnya menunggu target bulanan</h6>
                <div class="small text-muted">
                  Jika Anda ingin bonus harian dibentuk dari <strong>3% omzet harian</strong> saat omzet minimal tercapai, tetapi <strong>baru boleh cair kalau target bulanan lolos</strong>, maka alurnya begini:
                  <br>1. Di halaman target, buat target bulanan dan aktifkan sebagai gerbang bonus bulanan.
                  <br>2. Di rule bonus, atur ambang omzet harian, misalnya minimal Rp 3.000.000.
                  <br>3. Di rule bonus, atur rumus pool harian = 3% dari omzet yang lolos.
                  <br>4. Pool bonus harian tetap dihitung di belakang layar, tetapi pencairannya ditahan sampai hasil target bulanan dinyatakan lolos.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var tabButtons = Array.prototype.slice.call(document.querySelectorAll('#targetPageTabs [data-tab-key]'));
    var currentTabKey = <?php echo json_encode($tab); ?>;
    if (!tabButtons.length) return;

    function syncTargetTabButtonState(activeBtn) {
      tabButtons.forEach(function (btn) {
        var isActive = btn === activeBtn;
        btn.classList.toggle('active', isActive);
        btn.classList.toggle('btn-primary', isActive);
        btn.classList.toggle('btn-outline-primary', !isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
    }

    function buildTargetTabUrl(tabKey) {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tabKey);
        url.searchParams.set('page', '1');
        return url.toString();
      } catch (err) {
        return '';
      }
    }

    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function (evt) {
        var nextTabKey = btn.getAttribute('data-tab-key') || 'list';
        if (nextTabKey === currentTabKey) {
          syncTargetTabButtonState(btn);
          return;
        }

        var nextUrl = buildTargetTabUrl(nextTabKey);
        if (nextUrl) {
          evt.preventDefault();
          window.location.href = nextUrl;
          return;
        }

        syncTargetTabButtonState(btn);
      });
    });

    var firstActive = document.querySelector('#targetPageTabs [data-tab-key].active') || tabButtons[0];
    if (firstActive) {
      syncTargetTabButtonState(firstActive);
    }

    var bulkToggle = document.getElementById('bulkTargetToggle');
    if (bulkToggle) {
      bulkToggle.addEventListener('change', function () {
        Array.prototype.slice.call(document.querySelectorAll('.js-target-bulk-item')).forEach(function (checkbox) {
          checkbox.checked = bulkToggle.checked;
        });
      });
    }
  })();
</script>

<div class="modal fade" id="targetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?php echo site_url('finance-reports/targets/store'); ?>">
        <div class="modal-header">
          <h5 class="modal-title">Buat Target Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-light border mb-3">
            Isi bagian dasarnya dulu di sini. Setelah tersimpan, Anda bisa buka detail target untuk mengatur angka target, bobot, dan syarat bonus lebih lengkap.
            <div class="small text-muted mt-2">Khusus target harian, sistem akan menyimpannya dalam status aktif supaya bisa langsung dibaca engine bonus atau monitoring realisasi harian.</div>
            <div class="small text-muted mt-1">Kalau butuh membuat target harian untuk banyak tanggal sekaligus, gunakan modal <strong>Generate Target Harian</strong> agar tidak perlu input satu per satu.</div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nama target</label>
              <input type="text" name="target_name" class="form-control" required placeholder="Contoh: Target Outlet BAR Juni 2026">
            </div>
            <div class="col-md-3">
              <label class="form-label">Jenis target</label>
              <select name="target_scope" class="form-select" required>
                <option value="DAILY">Target Harian</option>
                <option value="MONTHLY" selected>Target Bulanan</option>
                <option value="YEARLY">Target Tahunan</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Aturan bonus</label>
              <select name="bonus_gate_mode" class="form-select">
                <option value="WEIGHTED_SCORE">Dinilai dari total skor</option>
                <option value="ALL_REQUIRED">Semua indikator wajib lolos</option>
                <option value="NONE">Tidak dipakai untuk bonus</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Mulai berlaku</label>
              <input type="date" name="date_start" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Sampai tanggal</label>
              <input type="date" name="date_end" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Divisi yang dinilai</label>
              <select name="division_id" class="form-select">
                <option value="0">Semua divisi</option>
                <?php foreach ($divisionOptions as $division): ?>
                  <option value="<?php echo (int)$division['id']; ?>"><?php echo html_escape((string)$division['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Rekening fokus</label>
              <select name="company_account_id" class="form-select">
                <option value="0">Semua rekening</option>
                <?php foreach ($companyAccounts as $account): ?>
                  <option value="<?php echo (int)$account['id']; ?>"><?php echo html_escape((string)$account['account_name'] . ' - ' . (string)$account['bank_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Skor minimal agar bonus bisa diproses</label>
              <input type="number" step="0.01" name="min_bonus_score" class="form-control" value="100">
              <div class="form-text">Contoh: isi `100` jika bonus baru boleh jalan saat skor target minimal 100%.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cadangan bonus bila target lolos</label>
              <input type="number" step="0.01" name="bonus_pool_amount" class="form-control" value="0">
              <div class="form-text">Ini angka patokan manajerial. Cocok jika Anda sudah tahu plafon bonus maksimal yang ingin dibuka.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">% laba yang dialokasikan ke bonus</label>
              <input type="number" step="0.0001" name="bonus_percent_of_profit" class="form-control" value="0">
              <div class="form-text">Isi jika ingin punya pagar: misalnya maksimal 8% dari laba bersih estimasi boleh dibuka untuk bonus.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Catatan singkat</label>
              <input type="text" name="notes" class="form-control" placeholder="Opsional, misal fokus jaga food cost dan kurangi adjustment gudang">
            </div>
            <div class="col-12">
              <label class="form-label">Pilih indikator awal</label>
              <select name="metric_codes[]" class="form-select" multiple size="8">
                <?php foreach ($metricCatalog as $metric): ?>
                  <option value="<?php echo html_escape((string)$metric['metric_code']); ?>">
                    <?php echo html_escape((string)$metric['metric_label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Boleh pilih beberapa sekaligus. Nanti setelah target tersimpan, Anda masih bisa atur angkanya satu per satu di halaman detail.</div>
            </div>
            <div class="col-12">
              <div class="fintarget-metric-preview">
                <div class="fw-semibold mb-2">Daftar indikator yang siap dipakai</div>
                <?php if (empty($metricCatalog)): ?>
                  <div class="text-muted small">Daftar indikator belum tersedia. Jalankan SQL foundation lebih dulu.</div>
                <?php else: ?>
                  <div class="row g-2">
                    <?php foreach ($metricCatalog as $metric): ?>
                      <div class="col-md-6">
                        <div class="small">
                          <strong><?php echo html_escape((string)$metric['metric_label']); ?></strong><br>
                          <span class="text-muted"><?php echo html_escape((string)($metric['description'] ?? 'Indikator penilaian target')); ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Target Awal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="dailyRangeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?php echo site_url('finance-reports/targets/generate-daily-range'); ?>">
        <div class="modal-header">
          <h5 class="modal-title">Generate Target Harian per Rentang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-light border mb-3">
            Gunakan form ini jika Anda ingin membuat banyak target harian sekaligus. Sistem akan membuat <strong>1 target DAILY per tanggal</strong> sesuai rentang yang dipilih.
            <div class="small text-muted mt-2">Jika pada tanggal tertentu sudah ada target harian dengan nama, divisi, dan rekening yang sama, tanggal itu akan dilewati agar tidak dobel.</div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nama target harian</label>
              <input type="text" name="target_name" class="form-control" required placeholder="Contoh: Target Harian Omzet Outlet BAR">
            </div>
            <div class="col-md-3">
              <label class="form-label">Tanggal mulai</label>
              <input type="date" name="date_start" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Tanggal selesai</label>
              <input type="date" name="date_end" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Divisi yang dinilai</label>
              <select name="division_id" class="form-select">
                <option value="0">Semua divisi</option>
                <?php foreach ($divisionOptions as $division): ?>
                  <option value="<?php echo (int)$division['id']; ?>"><?php echo html_escape((string)$division['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Rekening fokus</label>
              <select name="company_account_id" class="form-select">
                <option value="0">Semua rekening</option>
                <?php foreach ($companyAccounts as $account): ?>
                  <option value="<?php echo (int)$account['id']; ?>"><?php echo html_escape((string)$account['account_name'] . ' - ' . (string)$account['bank_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Aturan bonus</label>
              <select name="bonus_gate_mode" class="form-select">
                <option value="WEIGHTED_SCORE">Dinilai dari total skor</option>
                <option value="ALL_REQUIRED">Semua indikator wajib lolos</option>
                <option value="NONE">Tidak dipakai untuk bonus</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Skor minimal bonus</label>
              <input type="number" step="0.01" name="min_bonus_score" class="form-control" value="100">
            </div>
            <div class="col-md-4">
              <label class="form-label">Cadangan bonus</label>
              <input type="number" step="0.01" name="bonus_pool_amount" class="form-control" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">% laba untuk bonus</label>
              <input type="number" step="0.0001" name="bonus_percent_of_profit" class="form-control" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status target</label>
              <input type="text" class="form-control" value="ACTIVE otomatis untuk target harian" readonly>
              <div class="form-text">Generator ini langsung membuat target harian yang siap dibaca engine.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Catatan singkat</label>
              <input type="text" name="notes" class="form-control" placeholder="Opsional, misal target harian untuk monitoring bonus outlet BAR">
            </div>
            <div class="col-12">
              <label class="form-label">Pilih indikator yang ingin disalin ke semua tanggal</label>
              <select name="metric_codes[]" class="form-select js-daily-metric-select" multiple size="8" required>
                <?php foreach ($metricCatalog as $metric): ?>
                  <option value="<?php echo html_escape((string)$metric['metric_code']); ?>">
                    <?php echo html_escape((string)$metric['metric_label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Pilihan indikator ini akan ditempel ke setiap target DAILY yang dibuat dalam rentang tersebut.</div>
            </div>
            <div class="col-12">
              <div class="fintarget-config-box">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2 flex-wrap">
                  <div>
                    <div class="fw-semibold">Angka target yang langsung dipakai saat generate</div>
                    <div class="small text-muted">Jadi setelah target harian terbentuk, Anda tidak perlu masuk satu per satu hanya untuk mengisi target, arah nilai, bobot, atau indikator wajib.</div>
                  </div>
                  <span class="badge bg-light text-dark border">Disalin ke semua tanggal terpilih</span>
                </div>
                <div class="fintarget-config-table-wrap">
                  <table class="table table-sm align-middle fintarget-config-table">
                    <thead>
                      <tr>
                        <th>Indikator</th>
                        <th>Arah Nilai</th>
                        <th>Target</th>
                        <th>Min</th>
                        <th>Max</th>
                        <th>Warning</th>
                        <th>Bobot %</th>
                        <th>Wajib</th>
                        <th>Catatan</th>
                      </tr>
                    </thead>
                    <tbody id="dailyMetricConfigBody">
                      <tr class="js-empty-config-row">
                        <td colspan="9" class="text-center text-muted py-3">Pilih indikator dulu, nanti tabel pengisiannya muncul di sini.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Generate Target Harian</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    var select = document.querySelector('.js-daily-metric-select');
    var body = document.getElementById('dailyMetricConfigBody');
    if (!select || !body) {
      return;
    }

    function esc(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function rowHtml(code, label) {
      var safeCode = esc(code);
      var safeLabel = esc(label);
      return '' +
        '<tr>' +
          '<td><div class="fw-semibold">' + safeLabel + '</div><div class="small text-muted">' + safeCode + '</div></td>' +
          '<td>' +
            '<select name="line_comparator[' + safeCode + ']" class="form-select form-select-sm">' +
              '<option value="MAX">Semakin besar semakin baik</option>' +
              '<option value="MIN">Semakin kecil semakin baik</option>' +
              '<option value="RANGE">Harus di rentang tertentu</option>' +
              '<option value="EQUAL">Harus sama persis</option>' +
            '</select>' +
          '</td>' +
          '<td><input type="number" step="0.01" name="line_target_value[' + safeCode + ']" class="form-control form-control-sm" value="0"></td>' +
          '<td><input type="number" step="0.01" name="line_minimum_value[' + safeCode + ']" class="form-control form-control-sm"></td>' +
          '<td><input type="number" step="0.01" name="line_maximum_value[' + safeCode + ']" class="form-control form-control-sm"></td>' +
          '<td><input type="number" step="0.01" name="line_warning_value[' + safeCode + ']" class="form-control form-control-sm"></td>' +
          '<td><input type="number" step="0.0001" name="line_weight_percent[' + safeCode + ']" class="form-control form-control-sm" value="100"></td>' +
          '<td class="text-center"><input type="checkbox" name="line_is_required[' + safeCode + ']" value="1"></td>' +
          '<td><input type="text" name="line_notes[' + safeCode + ']" class="form-control form-control-sm" placeholder="Opsional"></td>' +
        '</tr>';
    }

    function rebuildRows() {
      var selected = Array.prototype.slice.call(select.options).filter(function (option) {
        return option.selected;
      });

      if (!selected.length) {
        body.innerHTML = '<tr class="js-empty-config-row"><td colspan="9" class="text-center text-muted py-3">Pilih indikator dulu, nanti tabel pengisiannya muncul di sini.</td></tr>';
        return;
      }

      var defaultWeight = (100 / selected.length).toFixed(4);
      body.innerHTML = selected.map(function (option) {
        return rowHtml(option.value, option.text);
      }).join('');

      Array.prototype.slice.call(body.querySelectorAll('input[name^="line_weight_percent["]')).forEach(function (input) {
        input.value = defaultWeight;
      });
    }

    select.addEventListener('change', rebuildRows);
    rebuildRows();
  })();
</script>
