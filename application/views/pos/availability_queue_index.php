<?php
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
$queueReady = !empty($queue_ready);
$canProcessQueue = !empty($can_process_queue);
$cronCommand = trim((string)($cron_command ?? ''));
$availabilityQueueCsrfToken = (string)($pos_availability_queue_csrf_token ?? '');
$baseUrl = site_url('pos/availability-queue');
$csrfEnabled = (bool)$this->config->item('csrf_protection');
$csrfName = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();

$statusMeta = [
    'ALL' => ['label' => 'Semua', 'class' => 'neutral'],
    'QUEUED' => ['label' => 'Menunggu', 'class' => 'queued'],
    'PROCESSING' => ['label' => 'Diproses', 'class' => 'processing'],
    'FAILED' => ['label' => 'Gagal', 'class' => 'failed'],
    'SUCCESS' => ['label' => 'Selesai', 'class' => 'success'],
    'CANCELLED' => ['label' => 'Dibatalkan', 'class' => 'neutral'],
];
$statusLabel = static function ($status) use ($statusMeta): string {
    $status = strtoupper(trim((string)$status));
    return (string)($statusMeta[$status]['label'] ?? ($status !== '' ? $status : '-'));
};
$statusClass = static function ($status) use ($statusMeta): string {
    $status = strtoupper(trim((string)$status));
    return (string)($statusMeta[$status]['class'] ?? 'neutral');
};
$dateTime = static function ($value): string {
    $time = trim((string)$value);
    $timestamp = $time !== '' ? strtotime($time) : false;
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
};
$number = static function ($value, int $decimal = 2): string {
    return number_format((float)$value, $decimal, ',', '.');
};
$pageLink = static function (int $page) use ($baseUrl, $filters): string {
    $query = [
        'status' => strtoupper((string)($filters['status'] ?? 'ALL')),
        'outlet_id' => (int)($filters['outlet_id'] ?? 0),
        'q' => trim((string)($filters['q'] ?? '')),
        'per_page' => (int)($filters['per_page'] ?? 25),
        'page' => max(1, $page),
    ];
    $query = array_filter($query, static function ($value, string $key): bool {
        return in_array($key, ['status', 'per_page', 'page'], true) || $value !== '' && $value !== 0;
    }, ARRAY_FILTER_USE_BOTH);
    return $baseUrl . '?' . http_build_query($query);
};
$returnInputs = static function () use ($filters): string {
    $fields = [
        'return_status' => strtoupper((string)($filters['status'] ?? 'ALL')),
        'return_outlet_id' => (int)($filters['outlet_id'] ?? 0),
        'return_q' => trim((string)($filters['q'] ?? '')),
        'return_per_page' => (int)($filters['per_page'] ?? 25),
        'return_page' => (int)($filters['page'] ?? 1),
    ];
    $html = '';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . html_escape($name) . '" value="' . html_escape((string)$value) . '">';
    }
    return $html;
};
?>
<?php $this->load->view('pos/_report_styles'); ?>
<style>
  .pos-availability-hero { position:relative; overflow:hidden; }
  .pos-availability-hero:after { content:''; position:absolute; width:220px; height:220px; border-radius:50%; right:-96px; top:-132px; background:rgba(159,33,65,.08); }
  .pos-availability-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem; }
  .pos-availability-card { min-height:106px; border:1px solid #ecdcd1; border-radius:16px; padding:.9rem 1rem; background:#fff; box-shadow:0 8px 20px rgba(80,53,37,.04); }
  .pos-availability-card-label { font-size:.73rem; text-transform:uppercase; letter-spacing:.045em; color:#8c7163; font-weight:800; }
  .pos-availability-card-value { margin-top:.28rem; color:#3f2a1f; font-size:1.55rem; font-weight:800; line-height:1; }
  .pos-availability-card-note { margin-top:.48rem; color:#876f61; font-size:.78rem; line-height:1.35; }
  .pos-availability-card.failed .pos-availability-card-value { color:#bc2f2a; }
  .pos-availability-card.queued .pos-availability-card-value { color:#9a5b00; }
  .pos-availability-guide { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(260px,.75fr); gap:1rem; align-items:stretch; }
  .pos-availability-guide-card { border:1px solid #efdbc8; border-radius:17px; background:#fff9f1; padding:1rem; color:#6b4e39; }
  .pos-availability-guide-card h6 { color:#4c3324; margin-bottom:.35rem; }
  .pos-availability-command { display:flex; gap:.55rem; align-items:stretch; margin-top:.75rem; }
  .pos-availability-command code { flex:1; display:block; min-width:0; white-space:normal; overflow-wrap:anywhere; border:1px solid #e9d7c9; border-radius:11px; padding:.68rem .75rem; background:#fff; color:#573d2e; font-size:.78rem; }
  .pos-availability-process-card { border:1px solid #d9e8dd; border-radius:17px; padding:1rem; background:#f6fbf7; }
  .pos-availability-process-card h6 { color:#285939; margin-bottom:.35rem; }
  .pos-availability-process-form { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.55rem; align-items:end; margin-top:.8rem; }
  .pos-availability-process-form label, .pos-availability-filter label { color:#765d4e; font-size:.75rem; font-weight:800; margin-bottom:.28rem; }
  .pos-availability-process-form .form-select, .pos-availability-process-form .btn { height:41px; }
  .pos-availability-filter { display:grid; grid-template-columns:minmax(220px,1.5fr) minmax(160px,.8fr) minmax(130px,.58fr) auto; gap:.65rem; align-items:end; }
  .pos-availability-filter .form-control, .pos-availability-filter .form-select, .pos-availability-filter .btn { height:42px; }
  .pos-availability-tabs { display:flex; gap:.45rem; flex-wrap:wrap; margin-top:.85rem; }
  .pos-availability-tab { text-decoration:none; border:1px solid #e4d4c8; border-radius:999px; color:#684e3f; background:#fff; font-size:.78rem; font-weight:800; padding:.43rem .7rem; }
  .pos-availability-tab:hover { color:#7c1730; border-color:#cfa99f; background:#fff8f4; }
  .pos-availability-tab.active { background:#9f2141; border-color:#9f2141; color:#fff; }
  .pos-availability-table-wrap { margin-top:.9rem; border:1px solid #eedfd4; border-radius:16px; overflow:auto; max-height:560px; background:#fff; }
  .pos-availability-table { width:100%; min-width:1110px; margin:0; border-collapse:separate; border-spacing:0; }
  .pos-availability-table thead th { position:sticky; top:0; z-index:3; background:#9f2141; color:#fff; border-color:#9f2141; font-size:.72rem; letter-spacing:.035em; text-transform:uppercase; white-space:nowrap; padding:.72rem .7rem; }
  .pos-availability-table tbody td { padding:.72rem .7rem; border-color:#f0e2d9; vertical-align:top; font-size:.83rem; color:#533d31; }
  .pos-availability-table tbody tr:nth-child(even) td { background:#fffaf7; }
  .pos-availability-table tbody tr:hover td { background:#fff5ef; }
  .pos-availability-status { display:inline-flex; border-radius:999px; font-size:.69rem; font-weight:800; padding:.3rem .54rem; white-space:nowrap; }
  .pos-availability-status.queued { color:#945a00; background:#fff0d8; }
  .pos-availability-status.processing { color:#245b91; background:#e7f1fe; }
  .pos-availability-status.failed { color:#a52a24; background:#fde8e6; }
  .pos-availability-status.success { color:#207547; background:#e7f7ec; }
  .pos-availability-status.neutral { color:#6b5b51; background:#ece7e3; }
  .pos-availability-product { display:grid; gap:.12rem; min-width:180px; }
  .pos-availability-product strong { color:#3f2a1f; }
  .pos-availability-product small, .pos-availability-muted { color:#927769; font-size:.75rem; }
  .pos-availability-stack { display:grid; gap:.16rem; }
  .pos-availability-error { color:#b42318; max-width:280px; overflow-wrap:anywhere; }
  .pos-availability-result { display:grid; gap:.15rem; }
  .pos-availability-result strong { color:#3d5e48; }
  .pos-availability-result.is-dirty strong { color:#9a5b00; }
  .pos-availability-action { display:flex; flex-direction:column; align-items:stretch; gap:.4rem; min-width:124px; }
  .pos-availability-action form { margin:0; }
  .pos-availability-empty { padding:2rem 1rem; color:#82695d; text-align:center; }
  .pos-availability-pager { display:flex; gap:.4rem; justify-content:flex-end; align-items:center; flex-wrap:wrap; margin-top:.85rem; }
  .pos-availability-pager a, .pos-availability-pager span { border:1px solid #ddcfc6; color:#71584a; background:#fff; border-radius:8px; padding:.4rem .63rem; text-decoration:none; font-size:.78rem; }
  .pos-availability-pager span { background:#f5eee9; color:#9d897e; }
  @media (max-width: 991.98px) { .pos-availability-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } .pos-availability-guide { grid-template-columns:1fr; } .pos-availability-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width: 575.98px) { .pos-availability-summary, .pos-availability-filter, .pos-availability-process-form { grid-template-columns:1fr; } .pos-availability-command { flex-direction:column; } }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
  <section class="pos-report-shell">
    <section class="pos-report-hero pos-availability-hero mb-3">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 position-relative" style="z-index:1;">
        <div>
          <div class="pos-report-title"><i class="ri-refresh-line me-2"></i><?php echo html_escape((string)($page_title ?? 'Ketersediaan POS')); ?></div>
          <p class="pos-report-copy mb-0">Pantau pembaruan menu yang bisa dijual setelah stok berubah. Halaman ini hanya mengelola cache ketersediaan POS, bukan stok, lot, HPP transaksi, atau saldo kas.</p>
        </div>
        <a class="btn btn-outline-danger" href="<?php echo site_url('pos/stock-live'); ?>"><i class="ri-pulse-line me-1"></i>Buka Stock Live POS</a>
      </div>
    </section>

    <?php if ($this->session->flashdata('success')): ?>
      <div class="alert alert-success mb-3"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
    <?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?>
      <div class="alert alert-danger mb-3"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
    <?php endif; ?>

    <?php if (!$queueReady): ?>
      <section class="alert alert-warning mb-3">
        <strong>Antrean belum siap dibaca.</strong> Jalankan migration <code>2026-08-21e_pos_product_availability_queue.sql</code> terlebih dahulu. Sampai itu dilakukan, aplikasi tetap memakai pengaman rebuild sinkron bila dibutuhkan.
      </section>
    <?php else: ?>
      <section class="pos-availability-summary mb-3">
        <div class="pos-availability-card queued"><div class="pos-availability-card-label">Menunggu</div><div class="pos-availability-card-value"><?php echo number_format((int)($summary['queued_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-availability-card-note">Job siap diambil worker. Yang paling lama: <?php echo html_escape($dateTime($summary['oldest_waiting_at'] ?? '')); ?>.</div></div>
        <div class="pos-availability-card"><div class="pos-availability-card-label">Sedang Diproses</div><div class="pos-availability-card-value"><?php echo number_format((int)($summary['processing_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-availability-card-note">Worker sedang menghitung ulang cache produk ini.</div></div>
        <div class="pos-availability-card failed"><div class="pos-availability-card-label">Perlu Dicek</div><div class="pos-availability-card-value"><?php echo number_format((int)($summary['failed_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-availability-card-note">Tidak mengubah stok. Buka error lalu ulangi job jika penyebabnya sudah diperbaiki.</div></div>
        <div class="pos-availability-card"><div class="pos-availability-card-label">Selesai Hari Ini</div><div class="pos-availability-card-value"><?php echo number_format((int)($summary['success_today_count'] ?? 0), 0, ',', '.'); ?></div><div class="pos-availability-card-note">Sukses terakhir: <?php echo html_escape($dateTime($summary['last_success_at'] ?? '')); ?>.</div></div>
      </section>

      <section class="pos-availability-guide mb-3">
        <article class="pos-availability-guide-card">
          <h6><i class="ri-terminal-box-line me-1"></i>Cron server: pasang sekali, berjalan otomatis</h6>
          <div class="small">Baris ini dipasang pada cron Ubuntu oleh pemilik server/panel hosting. Sistem sengaja tidak diberi tombol untuk mengubah cron dari web, supaya halaman aplikasi tidak memiliki akses menjalankan perintah server.</div>
          <div class="pos-availability-command">
            <code id="posAvailabilityCronCommand"><?php echo html_escape($cronCommand); ?></code>
            <button type="button" class="btn btn-outline-secondary" id="copyPosAvailabilityCron"><i class="ri-file-copy-line me-1"></i>Salin</button>
          </div>
          <div class="pos-availability-muted mt-2">Jika lokasi aplikasi atau lokasi PHP server berubah, sesuaikan kedua path tersebut sebelum memasang cron.</div>
        </article>
        <article class="pos-availability-process-card">
          <h6><i class="ri-play-circle-line me-1"></i>Proses Sekali</h6>
          <div class="small text-muted">Untuk mengecek hasil tanpa menunggu menit berikutnya. Tombol ini memakai antrean yang sama dengan cron dan dibatasi maksimal 100 job.</div>
          <?php if ($canProcessQueue): ?>
            <form method="post" action="<?php echo site_url('pos/availability-queue/process'); ?>" class="pos-availability-process-form">
              <input type="hidden" name="pos_availability_queue_csrf" value="<?php echo html_escape($availabilityQueueCsrfToken); ?>">
              <?php if ($csrfEnabled): ?><input type="hidden" name="<?php echo html_escape($csrfName); ?>" value="<?php echo html_escape($csrfHash); ?>"><?php endif; ?>
              <?php echo $returnInputs(); ?>
              <div><label for="availabilityProcessLimit">Jumlah job</label><select id="availabilityProcessLimit" name="limit" class="form-select"><option value="10">10 job</option><option value="25" selected>25 job</option><option value="50">50 job</option><option value="100">100 job</option></select></div>
              <button type="submit" class="btn btn-success"><i class="ri-play-line me-1"></i>Proses Sekali</button>
            </form>
          <?php else: ?>
            <div class="small text-muted mt-2">Akun ini hanya dapat melihat antrean. Minta admin yang berwenang untuk menjalankan proses manual.</div>
          <?php endif; ?>
        </article>
      </section>

      <section class="pos-report-section p-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div><h5 class="mb-1">Daftar Antrean Ketersediaan POS</h5><div class="pos-report-meta">Perubahan berulang pada produk dan outlet yang sama digabung menjadi satu job terbaru agar transaksi stok tidak terbebani rebuild resep berulang.</div></div>
          <div class="pos-report-meta">Menampilkan <?php echo number_format((int)($pagination['total'] ?? 0), 0, ',', '.'); ?> job.</div>
        </div>

        <form method="get" class="pos-availability-filter mt-3">
          <div><label for="availabilityQueueSearch">Cari produk, outlet, atau sumber</label><input id="availabilityQueueSearch" type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Contoh: kopi, BAR, adjustment"></div>
          <div><label for="availabilityQueueOutlet">Outlet</label><select id="availabilityQueueOutlet" name="outlet_id" class="form-select"><option value="0">Semua outlet</option><?php foreach ($outlets as $outlet): ?><?php $outletId = (int)($outlet['id'] ?? 0); ?><option value="<?php echo $outletId; ?>"<?php echo $outletId === (int)($filters['outlet_id'] ?? 0) ? ' selected' : ''; ?>><?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?></option><?php endforeach; ?></select></div>
          <div><label for="availabilityQueuePerPage">Baris</label><select id="availabilityQueuePerPage" name="per_page" class="form-select"><?php foreach ([25, 50, 100] as $perPage): ?><option value="<?php echo $perPage; ?>"<?php echo $perPage === (int)($filters['per_page'] ?? 25) ? ' selected' : ''; ?>><?php echo $perPage; ?> baris</option><?php endforeach; ?></select></div>
          <div class="d-flex gap-2"><input type="hidden" name="status" value="<?php echo html_escape(strtoupper((string)($filters['status'] ?? 'ALL'))); ?>"><button type="submit" class="btn btn-danger flex-fill">Terapkan</button><a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a></div>
        </form>

        <div class="pos-availability-tabs" aria-label="Filter status antrean">
          <?php foreach ($statusMeta as $statusKey => $meta): ?>
            <?php $query = ['status' => $statusKey, 'outlet_id' => (int)($filters['outlet_id'] ?? 0), 'q' => trim((string)($filters['q'] ?? '')), 'per_page' => (int)($filters['per_page'] ?? 25)]; $query = array_filter($query, static function ($value, string $key): bool { return in_array($key, ['status', 'per_page'], true) || $value !== '' && $value !== 0; }, ARRAY_FILTER_USE_BOTH); ?>
            <a class="pos-availability-tab<?php echo strtoupper((string)($filters['status'] ?? 'ALL')) === $statusKey ? ' active' : ''; ?>" href="<?php echo html_escape($baseUrl . '?' . http_build_query($query)); ?>"><?php echo html_escape((string)$meta['label']); ?></a>
          <?php endforeach; ?>
        </div>

        <div class="pos-availability-table-wrap">
          <table class="table align-middle pos-availability-table">
            <thead><tr><th>Status</th><th>Produk</th><th>Outlet</th><th>Perubahan Terakhir</th><th>Antrean</th><th>Hasil Cache / Error</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="pos-availability-empty">Belum ada job yang sesuai filter ini.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                    $jobId = (int)($row['id'] ?? 0);
                    $jobStatus = strtoupper((string)($row['status'] ?? ''));
                    $stockLiveUrl = site_url('pos/stock-live?' . http_build_query([
                        'outlet_id' => (int)($row['outlet_id'] ?? 0),
                        'q' => trim((string)($row['product_code'] ?? $row['product_name'] ?? '')),
                    ]));
                    $cacheStatus = strtoupper(trim((string)($row['cache_availability_status'] ?? '')));
                    $resultStatus = strtoupper(trim((string)($row['result_availability_status'] ?? '')));
                    $isDirty = !empty($row['cache_is_dirty']);
                    $availabilityQty = $resultStatus !== ''
                        ? (float)($row['result_available_qty'] ?? 0)
                        : (float)($row['cache_estimated_available_qty'] ?? 0);
                  ?>
                  <tr>
                    <td><span class="pos-availability-status <?php echo html_escape($statusClass($jobStatus)); ?>"><?php echo html_escape($statusLabel($jobStatus)); ?></span></td>
                    <td><div class="pos-availability-product"><strong><?php echo html_escape((string)($row['product_name'] ?? '-')); ?></strong><small><?php echo html_escape((string)($row['product_code'] ?? '-')); ?> | Produk #<?php echo (int)($row['product_id'] ?? 0); ?></small></div></td>
                    <td><div class="fw-semibold"><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></div><small class="pos-availability-muted">Outlet #<?php echo (int)($row['outlet_id'] ?? 0); ?></small></td>
                    <td><div class="pos-availability-stack"><strong><?php echo html_escape((string)($row['event_source'] ?? 'INVENTORY_CHANGE')); ?></strong><span class="pos-availability-muted"><?php echo html_escape((string)($row['event_table'] ?? '-')); ?><?php echo (int)($row['event_id'] ?? 0) > 0 ? ' #' . (int)$row['event_id'] : ''; ?></span><span class="pos-availability-muted">Masuk <?php echo html_escape($dateTime($row['created_at'] ?? '')); ?></span></div></td>
                    <td><div class="pos-availability-stack"><strong><?php echo html_escape($dateTime($row['run_after'] ?? '')); ?></strong><span class="pos-availability-muted">Event digabung: <?php echo number_format((int)($row['event_count'] ?? 0), 0, ',', '.'); ?> | Revisi: <?php echo number_format((int)($row['revision'] ?? 0), 0, ',', '.'); ?></span><span class="pos-availability-muted">Coba <?php echo (int)($row['attempts'] ?? 0); ?>/<?php echo (int)($row['max_attempts'] ?? 0); ?></span></div></td>
                    <td>
                      <?php if ($jobStatus === 'FAILED'): ?>
                        <div class="pos-availability-error"><strong>Gagal:</strong> <?php echo html_escape((string)($row['last_error'] ?? 'Tidak ada detail error.')); ?></div>
                      <?php elseif ($resultStatus !== '' || $cacheStatus !== ''): ?>
                        <div class="pos-availability-result<?php echo $isDirty ? ' is-dirty' : ''; ?>"><strong><?php echo html_escape($resultStatus !== '' ? $resultStatus : $cacheStatus); ?><?php echo $isDirty ? ' | perlu refresh' : ''; ?></strong><span class="pos-availability-muted">Cache <?php echo html_escape($dateTime($row['cache_computed_at'] ?? '')); ?> | Estimasi <?php echo html_escape($number($availabilityQty, 4)); ?></span></div>
                      <?php else: ?>
                        <span class="pos-availability-muted">Belum ada hasil cache yang tersimpan.</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><div class="pos-availability-action"><a class="btn btn-sm btn-outline-secondary" href="<?php echo html_escape($stockLiveUrl); ?>">Stock Live</a><?php if ($canProcessQueue && $jobStatus === 'FAILED'): ?><form method="post" action="<?php echo site_url('pos/availability-queue/retry/' . $jobId); ?>"><input type="hidden" name="pos_availability_queue_csrf" value="<?php echo html_escape($availabilityQueueCsrfToken); ?>"><?php if ($csrfEnabled): ?><input type="hidden" name="<?php echo html_escape($csrfName); ?>" value="<?php echo html_escape($csrfHash); ?>"><?php endif; ?><?php echo $returnInputs(); ?><button type="submit" class="btn btn-sm btn-outline-danger w-100">Ulangi Job</button></form><?php endif; ?></div></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php $currentPage = max(1, (int)($pagination['page'] ?? 1)); $totalPages = max(1, (int)($pagination['total_pages'] ?? 1)); ?>
        <?php if ($totalPages > 1): ?>
          <div class="pos-availability-pager"><span>Hal <?php echo $currentPage; ?>/<?php echo $totalPages; ?></span><?php if ($currentPage > 1): ?><a href="<?php echo html_escape($pageLink($currentPage - 1)); ?>">Sebelumnya</a><?php endif; ?><?php if ($currentPage < $totalPages): ?><a href="<?php echo html_escape($pageLink($currentPage + 1)); ?>">Berikutnya</a><?php endif; ?></div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </section>
</div>

<script>
  (function () {
    var button = document.getElementById('copyPosAvailabilityCron');
    var command = document.getElementById('posAvailabilityCronCommand');
    if (!button || !command) return;
    button.addEventListener('click', function () {
      var text = command.textContent || '';
      if (!navigator.clipboard || !navigator.clipboard.writeText) {
        window.prompt('Salin perintah cron ini:', text);
        return;
      }
      navigator.clipboard.writeText(text).then(function () {
        var original = button.innerHTML;
        button.innerHTML = '<i class="ri-check-line me-1"></i>Tersalin';
        window.setTimeout(function () { button.innerHTML = original; }, 1600);
      }).catch(function () {
        window.prompt('Salin perintah cron ini:', text);
      });
    });
  })();
</script>
