<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$month = $month ?? date('Y-m');
$date = $date ?? date('Y-m-d');
$tab = $tab ?? 'summary';
$summary = $summary ?? [];
$dailyRows = $daily_rows ?? [];
$pendingPeerFeedback = $pending_peer_feedback ?? [];
$peerHistoryRows = $peer_history_rows ?? [];
$penaltyTypeRows = $penalty_type_rows ?? [];
$penaltyRows = $penalty_rows ?? [];
$dailyFilters = $daily_filters ?? ['q' => ''];
$historyFilters = $history_filters ?? ['q' => ''];
$penaltyTypeFilters = $penalty_type_filters ?? ['q' => ''];
$penaltyFilters = $penalty_filters ?? ['q' => '', 'date_start' => '', 'date_end' => ''];
$dailyPg = $daily_pg ?? ['total' => count($dailyRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1];
$historyPg = $history_pg ?? ['total' => count($peerHistoryRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1];
$penaltyTypePg = $penalty_type_pg ?? ['total' => count($penaltyTypeRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1];
$penaltyPg = $penalty_pg ?? ['total' => count($penaltyRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1];
$isPublished = !empty($summary['is_published']);
$estimatedPenaltyAmount = (float)($summary['estimated_penalty_amount'] ?? 0);
$estimatedRawAmount = (float)($summary['estimated_raw_amount'] ?? 0);
$estimatedFinalAmount = (float)($summary['estimated_final_amount'] ?? 0);
if ($estimatedPenaltyAmount <= 0 && (float)($summary['total_penalty_amount'] ?? 0) > 0) {
    $estimatedPenaltyAmount = (float)$summary['total_penalty_amount'];
}
if ($estimatedRawAmount <= 0 && (float)($summary['total_raw_amount'] ?? 0) > 0) {
    $estimatedRawAmount = (float)$summary['total_raw_amount'];
}
if ($estimatedFinalAmount <= 0 && (float)($summary['total_final_amount'] ?? 0) > 0) {
    $estimatedFinalAmount = (float)$summary['total_final_amount'];
}
$estimatedManualPenaltyAmount = (float)($summary['estimated_manual_penalty_amount'] ?? 0);
$displayStatus = $isPublished ? (string)($summary['payout_status'] ?? 'APPROVED') : ($estimatedRawAmount > 0 || $estimatedPenaltyAmount > 0 ? 'ESTIMASI / DRAFT' : 'MENUNGGU POOL');
$buildUrl = static function (array $overrides = []) use ($selectedEmployeeId, $month, $date, $tab) {
    $base = [
        'employee_id' => $selectedEmployeeId ?: '',
        'month' => $month,
        'date' => $date,
        'tab' => $tab,
    ];
    return site_url('my/bonus') . '?' . http_build_query(array_merge($base, $overrides));
};
?>

<style>
  .my-bonus-hero { background: radial-gradient(circle at top left, rgba(177,18,38,.16), transparent 42%), linear-gradient(135deg, #fff7f2 0%, #ffffff 64%, #fff3ef 100%); border: 1px solid rgba(122,24,36,.08); border-radius: 28px; box-shadow: 0 20px 44px rgba(122,24,36,.08); }
  .my-bonus-nav .nav-link { border-radius: 999px; border: 1px solid rgba(122,24,36,.14); color: #7a1824; font-weight: 700; }
  .my-bonus-nav .nav-link.active { background: linear-gradient(135deg, #b11226, #7a1824); border-color: transparent; color: #fff; }
  .my-bonus-kpi { border: 1px solid rgba(122,24,36,.08); border-radius: 20px; padding: 1rem 1.1rem; background: linear-gradient(180deg, #fff, #fff8f5); }
  .my-bonus-kpi .label { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: #8a7266; }
  .my-bonus-kpi .value { font-size: 1.2rem; font-weight: 800; color: #5e1820; }
  .my-bonus-soft { display:inline-flex; padding:.34rem .7rem; border-radius:999px; font-size:.75rem; font-weight:700; }
  .my-bonus-soft.ok { background:#e8f8ef; color:#157347; }
  .my-bonus-soft.warn { background:#fff4df; color:#b26b00; }
  .my-bonus-table-wrap { max-height: 520px; overflow: auto; }
  .my-bonus-table-wrap thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Bonus Saya'); ?></h4>
    <small class="text-muted">Ringkasan bonus pribadi, histori harian, dan penilaian rekan kerja di hari yang sama.</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my/bonus'); ?>" class="d-flex gap-2">
    <select name="employee_id" class="form-select form-select-sm" style="min-width:260px">
      <option value="">Pilih Pegawai (Preview Superadmin)</option>
      <?php foreach ($employeeOptions as $o): ?>
        <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-primary">Buka</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
  <div class="alert alert-warning">Data pegawai belum terhubung ke akun ini.</div>
<?php else: ?>
  <div class="my-bonus-hero p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <div class="small text-uppercase text-muted fw-semibold mb-2">Bonus Pribadi</div>
        <h5 class="mb-1"><?php echo html_escape((string)($employee['employee_name'] ?? '-')); ?></h5>
        <div class="text-muted small"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?> · <?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></div>
      </div>
      <form method="get" action="<?php echo site_url('my/bonus'); ?>" class="d-flex gap-2 align-items-end flex-wrap">
        <?php if ($selectedEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?>
        <input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
        <div>
          <label class="form-label small text-muted mb-1">Bulan bonus</label>
          <input type="month" name="month" class="form-control" value="<?php echo html_escape($month); ?>">
        </div>
        <div>
          <label class="form-label small text-muted mb-1">Tanggal penilaian</label>
          <input type="date" name="date" class="form-control" value="<?php echo html_escape($date); ?>">
        </div>
        <div><button type="submit" class="btn btn-primary">Muat</button></div>
      </form>
    </div>
  </div>

  <ul class="nav nav-pills my-bonus-nav gap-2 mb-4">
    <li class="nav-item"><a class="nav-link <?php echo $tab === 'summary' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'summary']); ?>">Ringkasan Bonus</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab === 'penalties' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'penalties']); ?>">Master Penalti</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab === '360' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => '360']); ?>">Penilaian 360</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab === 'history' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'history']); ?>">Riwayat</a></li>
  </ul>

  <?php if ($tab === 'summary'): ?>
    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="my-bonus-kpi"><div class="label">Bonus Bruto</div><div class="value"><?php echo 'Rp ' . number_format($estimatedRawAmount, 2, ',', '.'); ?></div><div class="small text-muted">Akumulasi bonus draft dan publish bulan ini</div></div></div>
      <div class="col-md-3"><div class="my-bonus-kpi"><div class="label">Total Potongan</div><div class="value text-danger"><?php echo 'Rp ' . number_format($estimatedPenaltyAmount, 2, ',', '.'); ?></div><div class="small text-muted">Penalty harian + manual<?php echo $estimatedManualPenaltyAmount > 0 ? ' (manual Rp ' . number_format($estimatedManualPenaltyAmount, 2, ',', '.') . ')' : ''; ?></div></div></div>
      <div class="col-md-3"><div class="my-bonus-kpi"><div class="label">Estimasi Diterima</div><div class="value text-success"><?php echo 'Rp ' . number_format($estimatedFinalAmount, 2, ',', '.'); ?></div><div class="small text-muted">Perkiraan bersih sebelum pencairan kas</div></div></div>
      <div class="col-md-3"><div class="my-bonus-kpi"><div class="label">Nilai Rekan</div><div class="value"><?php echo number_format((float)($summary['peer_avg_star'] ?? 0), 2, ',', '.'); ?></div><div class="small text-muted">Rata-rata bintang yang masuk</div></div></div>
      <div class="col-md-3"><div class="my-bonus-kpi"><div class="label">Status Rekap</div><div class="value"><?php echo html_escape($displayStatus); ?></div><div class="small text-muted">Bonus tetap perhitungan dulu, kas baru bergerak saat pencairan dibuat</div></div></div>
      <div class="col-md-3"><div class="my-bonus-kpi"><div class="label">Target Harian Tercapai</div><div class="value"><?php echo number_format((int)($target_summary['daily_hit'] ?? 0)); ?> / <?php echo number_format((int)($target_summary['daily_total'] ?? 0)); ?></div><div class="small text-muted">Kurang Rp <?php echo number_format((float)($target_summary['daily_shortfall_amount'] ?? 0), 2, ',', '.'); ?> pada target harian yang belum lolos</div></div></div>
      <div class="col-md-3"><div class="my-bonus-kpi"><div class="label">Target Bulanan</div><div class="value"><?php echo number_format((int)($target_summary['monthly_hit'] ?? 0)); ?> / <?php echo number_format((int)($target_summary['monthly_total'] ?? 0)); ?></div><div class="small text-muted">Kurang Rp <?php echo number_format((float)($target_summary['monthly_shortfall_amount'] ?? 0), 2, ',', '.'); ?> pada target bulanan yang belum lolos</div></div></div>
    </div>

    <?php if (empty($summary['is_published'])): ?>
      <div class="alert alert-warning border-0 shadow-sm">Nominal di atas masih estimasi karena rekap bonus belum dipublish atau belum dicairkan. Nilai akhir mengikuti approval perusahaan.</div>
    <?php endif; ?>

    <?php if (!empty($target_summary['daily_notes']) || !empty($target_summary['monthly_notes'])): ?>
      <div class="alert alert-light border shadow-sm">
        <div class="fw-semibold mb-2">Ringkasan kekurangan target</div>
        <?php if (!empty($target_summary['daily_notes'])): ?>
          <div class="small mb-1"><strong>Harian:</strong> <?php echo html_escape(implode(' | ', (array)$target_summary['daily_notes'])); ?></div>
        <?php endif; ?>
        <?php if (!empty($target_summary['monthly_notes'])): ?>
          <div class="small"><strong>Bulanan:</strong> <?php echo html_escape(implode(' | ', (array)$target_summary['monthly_notes'])); ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="mb-1">Penalti yang Masuk Bulan Ini</h6>
        <div class="small text-muted">Baris ini membantu membaca potongan bonus yang sudah tercatat.</div>
      </div>
      <div class="card-body">
        <form method="get" action="<?php echo site_url('my/bonus'); ?>" class="row g-2 align-items-end mb-3">
          <?php if ($selectedEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?>
          <input type="hidden" name="tab" value="summary">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <input type="hidden" name="date" value="<?php echo html_escape($date); ?>">
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Cari penalti</label>
            <input type="text" name="penalty_q" class="form-control" value="<?php echo html_escape((string)($penaltyFilters['q'] ?? '')); ?>" placeholder="Cari pegawai, divisi, jenis penalti, kode, alasan, atau status">
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Tanggal awal</label>
            <input type="date" name="penalty_date_start" class="form-control" value="<?php echo html_escape((string)($penaltyFilters['date_start'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Tanggal akhir</label>
            <input type="date" name="penalty_date_end" class="form-control" value="<?php echo html_escape((string)($penaltyFilters['date_end'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Status</label>
            <select name="penalty_status" class="form-select">
              <?php foreach (['ALL' => 'Semua status', 'APPROVED' => 'Approved', 'DRAFT' => 'Draft', 'REJECTED' => 'Rejected'] as $statusValue => $statusLabel): ?>
                <option value="<?php echo html_escape($statusValue); ?>" <?php echo strtoupper((string)($penaltyFilters['status'] ?? 'ALL')) === $statusValue ? 'selected' : ''; ?>><?php echo html_escape($statusLabel); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label small text-muted mb-1">Baris</label>
            <select name="penalty_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($penaltyPg['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?php echo $buildUrl([
              'tab' => 'summary',
              'penalty_q' => '',
              'penalty_date_start' => $month . '-01',
              'penalty_date_end' => date('Y-m-t', strtotime($month . '-01')),
              'penalty_status' => 'ALL',
              'penalty_page' => 1,
              'penalty_per_page' => 25,
            ]); ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
        <div class="table-responsive my-bonus-table-wrap">
          <table class="table align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Shift</th><th class="text-end">Poin</th><th class="text-end">Nominal</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($penaltyRows)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada penalti yang tercatat pada bulan ini.</td></tr>
            <?php else: foreach ($penaltyRows as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['penalty_date'] ?? '-')); ?></td>
                <td><strong><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?></div></td>
                <td><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['points_deducted'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-danger">Rp <?php echo number_format((float)($row['amount_deducted'] ?? 0), 2, ',', '.'); ?></td>
                <td><span class="my-bonus-soft <?php echo strtoupper((string)($row['status'] ?? 'DRAFT')) === 'APPROVED' ? 'ok' : 'warn'; ?>"><?php echo html_escape((string)($row['status'] ?? '-')); ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
          <div class="small text-muted">Total <?php echo number_format((int)($penaltyPg['total'] ?? 0)); ?> baris</div>
          <div class="btn-group">
            <?php $penaltyPrev = max(1, (int)($penaltyPg['page'] ?? 1) - 1); $penaltyNext = min((int)($penaltyPg['total_pages'] ?? 1), (int)($penaltyPg['page'] ?? 1) + 1); ?>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($penaltyPg['page'] ?? 1) <= 1) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'summary', 'penalty_q' => (string)($penaltyFilters['q'] ?? ''), 'penalty_date_start' => (string)($penaltyFilters['date_start'] ?? ''), 'penalty_date_end' => (string)($penaltyFilters['date_end'] ?? ''), 'penalty_status' => (string)($penaltyFilters['status'] ?? 'ALL'), 'penalty_per_page' => (int)($penaltyPg['per_page'] ?? 25), 'penalty_page' => $penaltyPrev]); ?>">Sebelumnya</a>
            <span class="btn btn-sm btn-light disabled">Hal. <?php echo (int)($penaltyPg['page'] ?? 1); ?> / <?php echo (int)($penaltyPg['total_pages'] ?? 1); ?></span>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($penaltyPg['page'] ?? 1) >= (int)($penaltyPg['total_pages'] ?? 1)) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'summary', 'penalty_q' => (string)($penaltyFilters['q'] ?? ''), 'penalty_date_start' => (string)($penaltyFilters['date_start'] ?? ''), 'penalty_date_end' => (string)($penaltyFilters['date_end'] ?? ''), 'penalty_status' => (string)($penaltyFilters['status'] ?? 'ALL'), 'penalty_per_page' => (int)($penaltyPg['per_page'] ?? 25), 'penalty_page' => $penaltyNext]); ?>">Berikutnya</a>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="mb-1">Detail bonus harian</h6>
        <div class="small text-muted">Baris draft ikut ditampilkan sebagai estimasi. Angka harian di sini sudah membaca pembagian bonus dari irisan transaksi, bukan blok shift penuh.</div>
      </div>
      <div class="card-body">
        <form method="get" action="<?php echo site_url('my/bonus'); ?>" class="row g-2 align-items-end mb-3">
          <?php if ($selectedEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?>
          <input type="hidden" name="tab" value="summary">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <input type="hidden" name="date" value="<?php echo html_escape($date); ?>">
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Cari baris</label>
            <input type="text" name="daily_q" class="form-control" value="<?php echo html_escape((string)($dailyFilters['q'] ?? '')); ?>" placeholder="Cari aturan, shift, atau status...">
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Baris</label>
            <select name="daily_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($dailyPg['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?php echo $buildUrl(['tab' => 'summary', 'daily_q' => '', 'daily_page' => 1, 'daily_per_page' => 25]); ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
        <div class="table-responsive my-bonus-table-wrap">
          <table class="table align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Shift Kerja</th><th>Aturan</th><th class="text-center">Irisan</th><th class="text-end">Bonus Bruto</th><th class="text-end">Potongan</th><th class="text-end">Estimasi Diterima</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($dailyRows)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Belum ada baris bonus harian untuk bulan ini.</td></tr>
            <?php else: foreach ($dailyRows as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['attendance_date'] ?? $row['bonus_date'] ?? '-')); ?></td>
                <td><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></td>
                <td><?php echo html_escape((string)($row['rule_name'] ?? '-')); ?></td>
                <td class="text-center"><?php echo number_format((int)($row['slice_count'] ?? 0)); ?>x</td>
                <td class="text-end">Rp <?php echo number_format((float)($row['raw_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-danger">Rp <?php echo number_format((float)($row['penalty_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-success">Rp <?php echo number_format((float)($row['final_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td><span class="my-bonus-soft <?php echo strtoupper((string)($row['approval_status'] ?? 'DRAFT')) === 'APPROVED' ? 'ok' : 'warn'; ?>"><?php echo html_escape((string)($row['approval_status'] ?? 'DRAFT')); ?></span></td>
                <td><a href="<?php echo site_url('my/bonus/daily-detail/' . (int)($row['id'] ?? 0) . ($selectedEmployeeId > 0 ? ('?employee_id=' . (int)$selectedEmployeeId) : '')); ?>" class="btn btn-sm btn-outline-secondary">Detail Audit</a></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
          <div class="small text-muted">Total <?php echo number_format((int)($dailyPg['total'] ?? 0)); ?> baris</div>
          <div class="btn-group">
            <?php $dailyPrev = max(1, (int)($dailyPg['page'] ?? 1) - 1); $dailyNext = min((int)($dailyPg['total_pages'] ?? 1), (int)($dailyPg['page'] ?? 1) + 1); ?>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($dailyPg['page'] ?? 1) <= 1) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'summary', 'daily_q' => (string)($dailyFilters['q'] ?? ''), 'daily_per_page' => (int)($dailyPg['per_page'] ?? 25), 'daily_page' => $dailyPrev]); ?>">Sebelumnya</a>
            <span class="btn btn-sm btn-light disabled">Hal. <?php echo (int)($dailyPg['page'] ?? 1); ?> / <?php echo (int)($dailyPg['total_pages'] ?? 1); ?></span>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($dailyPg['page'] ?? 1) >= (int)($dailyPg['total_pages'] ?? 1)) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'summary', 'daily_q' => (string)($dailyFilters['q'] ?? ''), 'daily_per_page' => (int)($dailyPg['per_page'] ?? 25), 'daily_page' => $dailyNext]); ?>">Berikutnya</a>
          </div>
        </div>
      </div>
    </div>
  <?php elseif ($tab === 'penalties'): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="mb-1">Master Penalti Bonus</h6>
        <div class="small text-muted">Halaman ini dipakai agar pegawai tahu hal-hal apa saja yang bisa mengurangi poin atau bonus. Ini hanya panduan baca, bukan tempat mengubah data.</div>
      </div>
      <div class="card-body">
        <div class="alert alert-light border shadow-sm mb-3">
          <div class="fw-semibold mb-1">Cara baca cepat</div>
          <div class="small text-muted">
            `Otomatis` berarti sistem bisa membuat penalti sendiri dari absensi, layanan, atau hasil moderasi.
            `Manual` berarti leader / superadmin mencatat kejadian tertentu.
            `Semi manual` berarti butuh verifikasi admin terlebih dulu.
          </div>
        </div>
        <form method="get" action="<?php echo site_url('my/bonus'); ?>" class="row g-2 align-items-end mb-3">
          <?php if ($selectedEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?>
          <input type="hidden" name="tab" value="penalties">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <input type="hidden" name="date" value="<?php echo html_escape($date); ?>">
          <div class="col-md-8">
            <label class="form-label small text-muted mb-1">Cari master penalti</label>
            <input type="text" name="penalty_type_q" class="form-control" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>" placeholder="Cari nama, kode, kategori, atau sumber otomatis">
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Baris</label>
            <select name="penalty_type_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($penaltyTypePg['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
          </div>
        </form>
        <div class="table-responsive my-bonus-table-wrap">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Penalti</th>
                <th>Kategori</th>
                <th>Mode</th>
                <th>Sumber</th>
                <th class="text-end">Poin Default</th>
                <th class="text-end">Nominal Default</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($penaltyTypeRows)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Belum ada master penalti yang aktif.</td></tr>
            <?php else: foreach ($penaltyTypeRows as $row): ?>
              <?php
                $behavior = strtoupper((string)($row['behavior_mode'] ?? 'MANUAL'));
                $behaviorLabel = $behavior === 'AUTO' ? 'Otomatis' : ($behavior === 'SEMI_MANUAL' ? 'Semi manual' : 'Manual');
                $sourceLabel = trim((string)($row['auto_source'] ?? ''));
                if ($sourceLabel === '') {
                    $sourceLabel = 'Input admin';
                }
              ?>
              <tr>
                <td><strong><?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?></strong></td>
                <td>
                  <strong><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?></strong>
                  <div class="small text-muted"><?php echo (int)($row['approval_required'] ?? 0) === 1 ? 'Butuh persetujuan admin' : 'Bisa langsung dipakai'; ?></div>
                </td>
                <td><?php echo html_escape((string)($row['category'] ?? '-')); ?></td>
                <td><span class="my-bonus-soft <?php echo $behavior === 'AUTO' ? 'ok' : 'warn'; ?>"><?php echo html_escape($behaviorLabel); ?></span></td>
                <td><?php echo html_escape($sourceLabel); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['default_points_deducted'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-danger">Rp <?php echo number_format((float)($row['default_amount_deducted'] ?? 0), 2, ',', '.'); ?></td>
                <td class="small text-muted"><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
          <div class="small text-muted">Total <?php echo number_format((int)($penaltyTypePg['total'] ?? 0)); ?> baris</div>
          <div class="btn-group">
            <?php $penaltyPrev = max(1, (int)($penaltyTypePg['page'] ?? 1) - 1); $penaltyNext = min((int)($penaltyTypePg['total_pages'] ?? 1), (int)($penaltyTypePg['page'] ?? 1) + 1); ?>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($penaltyTypePg['page'] ?? 1) <= 1) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'penalties', 'penalty_type_q' => (string)($penaltyTypeFilters['q'] ?? ''), 'penalty_type_per_page' => (int)($penaltyTypePg['per_page'] ?? 25), 'penalty_type_page' => $penaltyPrev]); ?>">Sebelumnya</a>
            <span class="btn btn-sm btn-light disabled">Hal. <?php echo (int)($penaltyTypePg['page'] ?? 1); ?> / <?php echo (int)($penaltyTypePg['total_pages'] ?? 1); ?></span>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($penaltyTypePg['page'] ?? 1) >= (int)($penaltyTypePg['total_pages'] ?? 1)) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'penalties', 'penalty_type_q' => (string)($penaltyTypeFilters['q'] ?? ''), 'penalty_type_per_page' => (int)($penaltyTypePg['per_page'] ?? 25), 'penalty_type_page' => $penaltyNext]); ?>">Berikutnya</a>
          </div>
        </div>
      </div>
    </div>
  <?php elseif ($tab === '360'): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="mb-1">Nilai rekan kerja hari ini</h6>
        <div class="small text-muted">Penilaian ini hanya bisa diberikan ke rekan yang hadir di hari yang sama. Nilai mentah hanya dibaca superadmin untuk moderasi bonus.</div>
      </div>
      <div class="card-body">
        <?php if (empty($pendingPeerFeedback)): ?>
          <div class="alert alert-success mb-0">Tidak ada penilaian yang menunggu dari Anda untuk tanggal ini.</div>
        <?php else: ?>
          <form method="post" action="<?php echo site_url('my/bonus/peer-submit' . ($selectedEmployeeId ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>">
            <input type="hidden" name="date" value="<?php echo html_escape($date); ?>">
            <div class="d-grid gap-3">
              <?php foreach ($pendingPeerFeedback as $row): ?>
                <div class="border rounded-4 p-3">
                  <input type="hidden" name="to_employee_id[]" value="<?php echo (int)($row['employee_id'] ?? 0); ?>">
                  <input type="hidden" name="shift_id[]" value="<?php echo (int)($row['shift_id'] ?? 0); ?>">
                  <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <div>
                      <div class="fw-semibold"><?php echo html_escape((string)($row['employee_name'] ?? '-')); ?></div>
                      <div class="small text-muted"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?> · <?php echo html_escape((string)($row['position_name'] ?? '-')); ?></div>
                    </div>
                    <div class="small text-muted"><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></div>
                  </div>
                  <div class="row g-2">
                    <div class="col-md-3">
                      <label class="form-label">Bintang</label>
                      <select name="star_rating[]" class="form-select" required>
                        <option value="">Pilih nilai</option>
                        <option value="5">5 - Sangat baik</option>
                        <option value="4">4 - Baik</option>
                        <option value="3">3 - Cukup</option>
                        <option value="2">2 - Kurang</option>
                        <option value="1">1 - Buruk</option>
                      </select>
                    </div>
                    <div class="col-md-9">
                      <label class="form-label">Alasan / catatan</label>
                      <input type="text" name="reason_text[]" class="form-control" placeholder="Wajib diisi kalau nilai 1-3. Boleh diisi juga untuk apresiasi atau catatan positif.">
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mt-3 d-flex justify-content-end">
              <button type="submit" class="btn btn-primary">Simpan Penilaian 360</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="mb-1">Riwayat penilaian 360</h6>
        <div class="small text-muted">Riwayat ini hanya menampilkan penilaian 360 yang Anda kirim pada bulan ini.</div>
      </div>
      <div class="card-body">
        <form method="get" action="<?php echo site_url('my/bonus'); ?>" class="row g-2 align-items-end mb-3">
          <?php if ($selectedEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo $selectedEmployeeId; ?>"><?php endif; ?>
          <input type="hidden" name="tab" value="history">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <input type="hidden" name="date" value="<?php echo html_escape($date); ?>">
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Cari riwayat</label>
            <input type="text" name="history_q" class="form-control" value="<?php echo html_escape((string)($historyFilters['q'] ?? '')); ?>" placeholder="Cari nama atau catatan...">
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Baris</label>
            <select name="history_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($historyPg['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?php echo $buildUrl(['tab' => 'history', 'history_q' => '', 'history_page' => 1, 'history_per_page' => 25]); ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
        <div class="table-responsive my-bonus-table-wrap">
          <table class="table align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th class="text-center">Bintang</th><th>Status</th><th>Catatan</th></tr></thead>
            <tbody>
            <?php if (empty($peerHistoryRows)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat penilaian 360 bulan ini.</td></tr>
            <?php else: foreach ($peerHistoryRows as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['feedback_date'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['from_employee_name'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['to_employee_name'] ?? '-')); ?></td>
                <td class="text-center"><?php echo str_repeat('*', (int)($row['star_rating'] ?? 0)); ?></td>
                <td><span class="my-bonus-soft <?php echo strtoupper((string)($row['status'] ?? 'SUBMITTED')) === 'APPROVED' ? 'ok' : 'warn'; ?>"><?php echo html_escape((string)($row['status'] ?? 'SUBMITTED')); ?></span></td>
                <td><?php echo html_escape((string)($row['reason_text'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
          <div class="small text-muted">Total <?php echo number_format((int)($historyPg['total'] ?? 0)); ?> baris</div>
          <div class="btn-group">
            <?php $historyPrev = max(1, (int)($historyPg['page'] ?? 1) - 1); $historyNext = min((int)($historyPg['total_pages'] ?? 1), (int)($historyPg['page'] ?? 1) + 1); ?>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($historyPg['page'] ?? 1) <= 1) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'history', 'history_q' => (string)($historyFilters['q'] ?? ''), 'history_per_page' => (int)($historyPg['per_page'] ?? 25), 'history_page' => $historyPrev]); ?>">Sebelumnya</a>
            <span class="btn btn-sm btn-light disabled">Hal. <?php echo (int)($historyPg['page'] ?? 1); ?> / <?php echo (int)($historyPg['total_pages'] ?? 1); ?></span>
            <a class="btn btn-sm btn-outline-secondary <?php echo ((int)($historyPg['page'] ?? 1) >= (int)($historyPg['total_pages'] ?? 1)) ? 'disabled' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'history', 'history_q' => (string)($historyFilters['q'] ?? ''), 'history_per_page' => (int)($historyPg['per_page'] ?? 25), 'history_page' => $historyNext]); ?>">Berikutnya</a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
