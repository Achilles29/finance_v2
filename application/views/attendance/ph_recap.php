<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];
$selectedMonth = (string)($filters['month'] ?? date('Y-m'));
$monthLabels = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
$selectedMonthLabel = $selectedMonth;
if (preg_match('/^(\d{4})-(\d{2})$/', $selectedMonth, $monthMatch)) {
    $selectedMonthLabel = ($monthLabels[(int)$monthMatch[2]] ?? $selectedMonth) . ' ' . $monthMatch[1];
}

$buildQuery = static function (array $overrides = []) use ($filters, $pg): string {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'is_eligible' => $filters['is_eligible'] ?? '',
        'month' => $filters['month'] ?? date('Y-m'),
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }
    $items = [1];
    $start = max(2, $page - 1);
    $end = min($totalPages - 1, $page + 1);
    if ($start > 2) {
        $items[] = '...';
    }
    for ($i = $start; $i <= $end; $i++) {
        $items[] = $i;
    }
    if ($end < $totalPages - 1) {
        $items[] = '...';
    }
    $items[] = $totalPages;
    return $items;
};
?>

<style>
  .ph-recap-page{--ph-ink:#38272d;--ph-muted:#8f7c81;--ph-line:#ecdfda;--ph-panel:#fffdfb;--ph-red:#a71e32;--ph-green:#177a56;--ph-blue:#286fa8;--ph-amber:#a96018}
  .ph-recap-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.ph-recap-head h4{color:var(--ph-ink);font-weight:800;letter-spacing:-.02em}.ph-recap-head p{max-width:660px;margin:.3rem 0 0;color:var(--ph-muted);font-size:.84rem;line-height:1.45}.ph-recap-head-meta{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.45rem}.ph-recap-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.38rem .6rem;border:1px solid var(--ph-line);border-radius:999px;background:var(--ph-panel);color:#6d5258;font-size:.74rem;font-weight:700;white-space:nowrap}.ph-recap-chip i{color:var(--ph-red)}
  .ph-recap-filter-card{border:1px solid var(--ph-line);border-radius:16px;background:rgba(255,253,251,.88);box-shadow:0 8px 22px rgba(86,45,51,.04)}.ph-recap-filter-card .card-body{padding:1rem 1.1rem}.ph-recap-filter-card .form-label{margin-bottom:.3rem;color:#70575d;font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.ph-recap-filter-card .form-control,.ph-recap-filter-card .form-select{min-height:38px;border-color:#e7d8d2;border-radius:10px;font-size:.85rem}
  .ph-recap-guide{display:flex;align-items:flex-start;gap:.65rem;margin:0 0 .85rem;padding:.75rem .9rem;border:1px solid #f0e3cd;border-radius:13px;background:linear-gradient(90deg,#fffaf0,#fffdfb);color:#755f50;font-size:.78rem;line-height:1.45}.ph-recap-guide i{margin-top:.08rem;color:var(--ph-amber);font-size:1.05rem}.ph-recap-guide strong{color:#5a3b40}
  .ph-recap-list{display:grid;gap:.8rem}.ph-recap-item{display:grid;grid-template-columns:minmax(205px,1.1fr) minmax(135px,.62fr) minmax(255px,1.05fr) minmax(220px,.92fr);overflow:hidden;border:1px solid var(--ph-line);border-radius:16px;background:var(--ph-panel);box-shadow:0 8px 22px rgba(86,45,51,.04)}.ph-recap-item>section{min-width:0;padding:1rem}.ph-recap-item>section+section{border-left:1px solid var(--ph-line)}
  .ph-recap-person{display:flex;align-items:flex-start;gap:.7rem}.ph-recap-avatar{display:grid;width:36px;height:36px;flex:0 0 36px;place-items:center;border-radius:11px;background:linear-gradient(135deg,#a51f32,#751828);color:#fff5e9;font-size:.86rem;font-weight:900}.ph-recap-person-name{margin:0;color:var(--ph-ink);font-size:.92rem;font-weight:800;line-height:1.25;overflow-wrap:anywhere}.ph-recap-person-code{display:block;margin-top:.14rem;color:var(--ph-muted);font-size:.72rem}.ph-recap-role{margin:.6rem 0 0;color:#765e64;font-size:.73rem;line-height:1.4;overflow-wrap:anywhere}.ph-recap-status{margin-top:.5rem;font-size:.67rem;letter-spacing:.03em}
  .ph-recap-label{display:block;margin-bottom:.24rem;color:var(--ph-muted);font-size:.66rem;font-weight:800;letter-spacing:.075em;text-transform:uppercase}.ph-recap-balance{display:flex;flex-direction:column;justify-content:center;background:linear-gradient(145deg,#fff7ef,#fffdfb)}.ph-recap-balance-value{color:var(--ph-green);font-size:1.55rem;font-weight:900;letter-spacing:-.055em;line-height:1}.ph-recap-balance.is-negative .ph-recap-balance-value{color:var(--ph-red)}.ph-recap-balance-note{margin-top:.42rem;color:#826d70;font-size:.71rem;line-height:1.35}
  .ph-recap-total-grid,.ph-recap-month-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}.ph-recap-metric{min-width:0;padding:.48rem .55rem;border:1px solid #f0e6e1;border-radius:10px;background:#fff}.ph-recap-metric dt{margin:0;color:#8f7b80;font-size:.64rem;font-weight:800;letter-spacing:.055em;text-transform:uppercase}.ph-recap-metric dd{margin:.18rem 0 0;color:#4e3940;font-size:.88rem;font-weight:800;line-height:1.15}.ph-recap-metric.grant dd{color:var(--ph-green)}.ph-recap-metric.use dd{color:var(--ph-blue)}.ph-recap-metric.expire dd{color:var(--ph-red)}.ph-recap-metric.adjust dd{color:#0e8395}
  .ph-recap-period-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.55rem}.ph-recap-period-head .ph-recap-label{margin:0}.ph-recap-period-total{color:#866c71;font-size:.69rem;font-weight:700;white-space:nowrap}.ph-recap-period-total.is-empty{color:#a49295}.ph-recap-empty{padding:3rem 1rem;border:1px dashed #dfcfca;border-radius:16px;background:var(--ph-panel);color:var(--ph-muted);text-align:center}.ph-recap-empty i{display:block;margin-bottom:.45rem;color:#be8b65;font-size:1.5rem}.ph-recap-pagination{display:flex;flex-wrap:wrap;gap:.3rem;justify-content:flex-end}
  @media (max-width:1299px){.ph-recap-item{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.ph-recap-item>section:nth-child(3){border-top:1px solid var(--ph-line);border-left:0}.ph-recap-item>section:nth-child(4){border-top:1px solid var(--ph-line)}}
  @media (max-width:767.98px){.ph-recap-head{display:block}.ph-recap-head-meta{justify-content:flex-start;margin-top:.7rem}.ph-recap-filter-card .card-body{padding:.9rem}.ph-recap-item{grid-template-columns:1fr}.ph-recap-item>section+section,.ph-recap-item>section:nth-child(3),.ph-recap-item>section:nth-child(4){border-top:1px solid var(--ph-line);border-left:0}.ph-recap-total-grid,.ph-recap-month-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ph-recap-pagination{justify-content:flex-start}.ph-recap-card-footer{align-items:flex-start!important;flex-direction:column}}
</style>

<div class="ph-recap-page">
  <div class="ph-recap-head">
    <div>
      <h4 class="mb-0"><?php echo html_escape($title ?? 'Rekap PH Pegawai'); ?></h4>
      <p>Saldo dihitung dari seluruh riwayat PH pegawai. Bagian aktivitas menunjukkan mutasi yang terjadi pada periode yang sedang dipilih.</p>
    </div>
    <div class="ph-recap-head-meta">
      <span class="ph-recap-chip"><i class="ri-calendar-line"></i><?php echo html_escape($selectedMonthLabel); ?></span>
      <span class="ph-recap-chip"><i class="ri-team-line"></i><?php echo (int)$pg['total']; ?> pegawai</span>
    </div>
  </div>

<div class="card ph-recap-filter-card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/ph-recap'); ?>" class="row g-2 align-items-end">
      <div class="col-md-4"><label class="form-label mb-1">Cari</label><input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Divisi/Jabatan"></div>
      <div class="col-md-3"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach ($divisionOptions as $opt): ?><option value="<?php echo (int)$opt['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$opt['value']) ? 'selected' : ''; ?>><?php echo html_escape($opt['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Eligible</label><select name="is_eligible" class="form-select"><option value="">Semua</option><option value="1" <?php echo ((string)($filters['is_eligible'] ?? '') === '1') ? 'selected' : ''; ?>>Eligible</option><option value="0" <?php echo ((string)($filters['is_eligible'] ?? '') === '0') ? 'selected' : ''; ?>>Non-Eligible</option></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Bulan</label><input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $pp): ?><option value="<?php echo $pp; ?>" <?php echo ((int)$pg['per_page']===$pp)?'selected':''; ?>><?php echo $pp; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-12 d-flex gap-2"><button type="submit" class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/ph-recap'); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="ph-recap-guide"><i class="ri-information-line"></i><div><strong>Cara baca:</strong> saldo = grant + penyesuaian - penggunaan - expire. Nilai pada kolom <strong>Aktivitas <?php echo html_escape($selectedMonthLabel); ?></strong> hanya untuk periode yang dipilih.</div></div>

<div class="ph-recap-list">
  <?php if (empty($rows)): ?>
    <div class="ph-recap-empty"><i class="ri-calendar-close-line"></i>Belum ada data rekap PH yang sesuai filter.</div>
  <?php else: foreach ($rows as $row): ?>
    <?php
      $grant = (float)($row['grant_total'] ?? 0);
      $use = (float)($row['use_total'] ?? 0);
      $expire = (float)($row['expire_total'] ?? 0);
      $adjust = (float)($row['adjust_total'] ?? 0);
      $grantMonth = (float)($row['grant_month'] ?? 0);
      $useMonth = (float)($row['use_month'] ?? 0);
      $expireMonth = (float)($row['expire_month'] ?? 0);
      $balance = ($grant + $adjust) - ($use + $expire);
      $monthActivity = $grantMonth + $useMonth + $expireMonth;
      $employeeName = trim((string)($row['employee_name'] ?? ''));
      $employeeInitial = strtoupper(substr($employeeName !== '' ? $employeeName : '?', 0, 1));
    ?>
    <article class="ph-recap-item">
      <section>
        <div class="ph-recap-person">
          <span class="ph-recap-avatar"><?php echo html_escape($employeeInitial); ?></span>
          <div>
            <h5 class="ph-recap-person-name"><?php echo html_escape($employeeName !== '' ? $employeeName : '-'); ?></h5>
            <span class="ph-recap-person-code"><?php echo html_escape((string)($row['employee_code'] ?? '-')); ?></span>
          </div>
        </div>
        <p class="ph-recap-role"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?><br><?php echo html_escape((string)($row['position_name'] ?? '-')); ?></p>
        <?php if ((int)($row['is_eligible'] ?? 0) === 1): ?>
          <span class="badge bg-success ph-recap-status">Eligible PH</span>
        <?php else: ?>
          <span class="badge bg-secondary ph-recap-status">Non-Eligible</span>
        <?php endif; ?>
      </section>
      <section class="ph-recap-balance <?php echo $balance < 0 ? 'is-negative' : ''; ?>">
        <span class="ph-recap-label">Saldo PH</span>
        <strong class="ph-recap-balance-value"><?php echo number_format($balance, 2, ',', '.'); ?> hari</strong>
        <span class="ph-recap-balance-note">Hak yang tersedia setelah seluruh mutasi tercatat.</span>
      </section>
      <section>
        <span class="ph-recap-label">Riwayat keseluruhan</span>
        <dl class="ph-recap-total-grid mb-0">
          <div class="ph-recap-metric grant"><dt>Grant</dt><dd><?php echo number_format($grant, 2, ',', '.'); ?></dd></div>
          <div class="ph-recap-metric use"><dt>Digunakan</dt><dd><?php echo number_format($use, 2, ',', '.'); ?></dd></div>
          <div class="ph-recap-metric expire"><dt>Expired</dt><dd><?php echo number_format($expire, 2, ',', '.'); ?></dd></div>
          <div class="ph-recap-metric adjust"><dt>Penyesuaian</dt><dd><?php echo number_format($adjust, 2, ',', '.'); ?></dd></div>
        </dl>
      </section>
      <section>
        <div class="ph-recap-period-head">
          <span class="ph-recap-label">Aktivitas <?php echo html_escape($selectedMonthLabel); ?></span>
          <span class="ph-recap-period-total <?php echo $monthActivity <= 0 ? 'is-empty' : ''; ?>"><?php echo $monthActivity <= 0 ? 'Tidak ada mutasi' : 'Ada mutasi'; ?></span>
        </div>
        <dl class="ph-recap-month-grid mb-0">
          <div class="ph-recap-metric grant"><dt>Grant</dt><dd><?php echo number_format($grantMonth, 2, ',', '.'); ?></dd></div>
          <div class="ph-recap-metric use"><dt>Dipakai</dt><dd><?php echo number_format($useMonth, 2, ',', '.'); ?></dd></div>
          <div class="ph-recap-metric expire"><dt>Expired</dt><dd><?php echo number_format($expireMonth, 2, ',', '.'); ?></dd></div>
          <div class="ph-recap-metric"><dt>Saldo akhir</dt><dd><?php echo number_format($balance, 2, ',', '.'); ?></dd></div>
        </dl>
      </section>
    </article>
  <?php endforeach; endif; ?>
</div>

<?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card mt-3">
    <div class="card-footer ph-recap-card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
      <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
      <div class="ph-recap-pagination">
        <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
        <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('attendance/ph-recap?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
        <?php foreach ($pageItems as $item): ?>
          <?php if ($item === '...'): ?>
            <span class="btn btn-sm btn-outline-secondary disabled">...</span>
          <?php else: ?>
            <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/ph-recap?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('attendance/ph-recap?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>
