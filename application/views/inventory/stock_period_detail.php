<?php
$period = is_array($period ?? null) ? $period : [];
$preflight = is_array($preflight ?? null) ? $preflight : [];
$health = is_array($preflight['health'] ?? null) ? $preflight['health'] : [];
$cutoffPreview = is_array($cutoff_preview ?? null) ? $cutoff_preview : [];
$previewSummary = is_array($cutoffPreview['summary'] ?? null) ? $cutoffPreview['summary'] : [];
$previewRows = array_values((array)($cutoffPreview['rows'] ?? []));
$previewWarnings = array_values((array)($cutoffPreview['warnings'] ?? []));
$cutoffPosting = is_array($cutoff_posting ?? null) ? $cutoff_posting : [];
$cutoffBlocks = array_values((array)($cutoffPosting['blocks'] ?? []));
$cutoffWarnings = array_values((array)($cutoffPosting['warnings'] ?? []));
$cutoffRuns = array_values((array)($cutoff_runs ?? []));
$cutoffSchemaReady = !empty($cutoff_schema_ready);
$canPostCutoff = !empty($can_post_cutoff);
$cutoffCanPost = !empty($cutoffPosting['can_post']);
$canEdit = !empty($can_edit);
$status = strtoupper((string)($period['status'] ?? ''));
$domain = strtoupper((string)($period['stock_domain'] ?? ''));
$fmtQty = static fn($value): string => number_format((float)$value, 4, ',', '.');
$fmtMoney = static fn($value): string => 'Rp ' . number_format((float)$value, 2, ',', '.');
$statusClass = match ($status) {
    'OPEN' => 'text-bg-success',
    'CLOSING' => 'text-bg-warning',
    'CLOSED' => 'text-bg-secondary',
    'REOPENED' => 'text-bg-danger',
    default => 'text-bg-light',
};
$statusLabel = match ($status) {
    'OPEN' => 'Terbuka',
    'CLOSING' => 'Sedang ditutup',
    'CLOSED' => 'Terkunci',
    'REOPENED' => 'Dibuka kembali',
    default => $status ?: '-',
};
$domainLabel = $domain === 'MATERIAL' ? 'Bahan Baku' : 'Component';
$domainHealth = $domain === 'MATERIAL' ? (array)($health['material'] ?? []) : (array)($health['component'] ?? []);
$healthUrl = (string)($health_url ?? site_url('inventory/stock/health'));
$reconcileUrl = $domain === 'MATERIAL' ? site_url('inventory/stock/division/reconcile') : site_url('production/component-reconcile');
$opnameUrl = $domain === 'MATERIAL' ? site_url('inventory/stock/opname/division/monthly') : site_url('production/component-opname');
$mutationCsrfName = (string)($inventory_control_mutation_csrf_name ?? '');
$mutationCsrfValue = (string)($inventory_control_mutation_csrf_value ?? '');
$hasMutationCsrf = $mutationCsrfName !== '' && $mutationCsrfValue !== '';
$warnings = array_values((array)($preflight['warnings'] ?? []));
$openingMonth = (string)($cutoffPreview['opening_month'] ?? '');
$openingMonthLabel = $openingMonth !== '' ? date('F Y', strtotime($openingMonth)) : 'bulan berikutnya';
?>

<style>
.period-detail { --pd-ink:#3d2926; --pd-muted:#876d65; --pd-line:#ecdcd3; }
.period-detail .hero { border:1px solid var(--pd-line); border-radius:18px; background:linear-gradient(135deg,#fffdfa,#fff3ed); padding:1rem 1.15rem; }
.period-detail .box { border:1px solid var(--pd-line); border-radius:14px; background:#fff; padding:.72rem .82rem; height:100%; }
.period-detail .box .label { display:block; font-size:.66rem; color:var(--pd-muted); font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
.period-detail .box .value { display:block; color:var(--pd-ink); font-weight:850; margin-top:.22rem; }
.period-detail .action-card { border:1px solid var(--pd-line); border-radius:16px; overflow:hidden; }
.period-detail .action-card .card-header { background:#fffaf7; border-bottom:1px solid var(--pd-line); }
.period-detail .warning-list { margin:0; padding-left:1.2rem; }
.period-detail .warning-list li + li { margin-top:.25rem; }
.period-detail .preview-card { background:linear-gradient(180deg,#fffdf9,#fff7f2); }
.period-detail .preview-card .card-header { background:#fff4ea; }
.period-detail .preview-stat { border:1px solid #f0d9ca; border-radius:12px; background:#fff; padding:.65rem .75rem; height:100%; }
.period-detail .preview-stat .label { color:#8c6557; font-size:.64rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
.period-detail .preview-stat .value { color:#602f25; display:block; font-size:1.05rem; font-weight:850; margin-top:.12rem; }
.period-detail .preview-table-shell { max-height:330px; overflow:auto; border:1px solid #f0d9ca; border-radius:12px; background:#fff; }
.period-detail .preview-table { margin:0; min-width:760px; }
.period-detail .preview-table thead th { position:sticky; top:0; z-index:2; background:#a91429; color:#fff; border-color:#a91429; font-size:.7rem; letter-spacing:.03em; text-transform:uppercase; white-space:nowrap; }
.period-detail .preview-table td { vertical-align:middle; border-color:#f5e4da; }
.period-detail .preview-table .profile-note { color:var(--pd-muted); font-size:.76rem; }
.period-detail .preview-table .qty, .period-detail .preview-table .amount { text-align:right; white-space:nowrap; }
.period-detail .official-card { border-color:#d8c4b8; background:linear-gradient(180deg,#fff,#fff8f4); }
.period-detail .official-card .card-header { background:#fff0e8; }
.period-detail .run-table-shell { max-height:240px; overflow:auto; border:1px solid #ecdcd3; border-radius:12px; }
.period-detail .run-table { margin:0; min-width:740px; }
.period-detail .run-table thead th { position:sticky; top:0; z-index:2; background:#6d3028; color:#fff; border-color:#6d3028; font-size:.7rem; letter-spacing:.03em; text-transform:uppercase; white-space:nowrap; }
.period-detail .run-table td { vertical-align:middle; border-color:#f1e3db; }
</style>

<div class="period-detail">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
    <div>
      <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Rincian Tutup Periode Stok')); ?></h4>
      <small class="text-muted">Periksa kondisi bulan, lihat simulasi stok awal berikutnya, lalu posting cut-off resmi atau buka kembali dengan alasan yang tercatat.</small>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('inventory/stock/periods'); ?>">Kembali ke Tutup Periode</a>
  </div>

  <section class="hero mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <div class="text-muted small">Periode <?php echo $domainLabel; ?></div>
        <h5 class="mb-1"><?php echo html_escape(date('F Y', strtotime((string)($period['period_month'] ?? 'now')))); ?></h5>
        <div class="small text-muted">Mode: <?php echo html_escape((string)($period['close_mode'] ?? '-')); ?></div>
      </div>
      <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
    </div>
    <div class="row g-2 mt-2">
      <div class="col-6 col-md-3"><div class="box"><span class="label">Dibuat</span><span class="value"><?php echo html_escape((string)($period['created_at'] ?? '-')); ?></span></div></div>
      <div class="col-6 col-md-3"><div class="box"><span class="label">Ditutup</span><span class="value"><?php echo html_escape((string)($period['closed_at'] ?? '-')); ?></span></div></div>
      <div class="col-6 col-md-3"><div class="box"><span class="label">Dibuka lagi</span><span class="value"><?php echo html_escape((string)($period['reopened_at'] ?? '-')); ?></span></div></div>
      <div class="col-6 col-md-3"><div class="box"><span class="label">Catatan</span><span class="value"><?php echo html_escape((string)($period['notes'] ?? '-')); ?></span></div></div>
    </div>
  </section>

  <div class="row g-3 mb-3">
    <div class="col-lg-7">
      <div class="action-card card h-100">
        <div class="card-header"><strong>Pemeriksaan sebelum penutupan</strong></div>
        <div class="card-body">
          <div class="row g-2 mb-3">
            <div class="col-6"><div class="box"><span class="label">Defisit masih terbuka</span><span class="value <?php echo ((int)($preflight['open_deficit_count'] ?? 0) > 0) ? 'text-danger' : ''; ?>"><?php echo number_format((int)($preflight['open_deficit_count'] ?? 0), 0, ',', '.'); ?> baris / <?php echo $fmtQty($preflight['open_deficit_qty'] ?? 0); ?></span></div></div>
            <div class="col-6"><div class="box"><span class="label">Selisih stok, lot, atau nilai</span><span class="value <?php echo ((int)($domainHealth['mismatch_rows'] ?? 0) > 0) ? 'text-warning' : ''; ?>"><?php echo number_format((int)($domainHealth['mismatch_rows'] ?? 0), 0, ',', '.'); ?> baris</span></div></div>
          </div>
          <?php if (!empty($warnings)): ?>
            <div class="alert alert-warning mb-0">
              <strong>Perlu perhatian sebelum cut-off:</strong>
              <ul class="warning-list mt-2"><?php foreach ($warnings as $warning): ?><li><?php echo html_escape((string)$warning); ?></li><?php endforeach; ?></ul>
              <div class="small mt-2">Catatan ini tidak diperbaiki secara otomatis. Selesaikan yang memang perlu diperbaiki, lalu posting cut-off resmi setelah kondisi tersebut dipahami dan dicatat.</div>
            </div>
          <?php else: ?>
            <div class="alert alert-success mb-0">Tidak ada defisit terbuka atau selisih stok, lot, dan nilai pada pemeriksaan bulan ini.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="action-card card h-100">
        <div class="card-header"><strong>Urutan kerja operator</strong></div>
        <div class="card-body small">
          <ol class="mb-3 ps-3">
            <li>Periksa defisit yang masih terbuka.</li>
            <li>Periksa daftar kesehatan stok untuk selisih lot atau nilai.</li>
            <li>Jika perlu, lakukan opname dan adjustment berdasarkan stok fisik.</li>
            <li>Periksa simulasi cut-off di bawah ini.</li>
            <li>Setelah itu posting cut-off resmi agar opname dan stok awal terbentuk, lalu transaksi bulan lama terkunci.</li>
          </ol>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-danger btn-sm" href="<?php echo site_url('inventory/stock/deficits'); ?>">Lihat Defisit</a>
            <a class="btn btn-outline-danger btn-sm" href="<?php echo html_escape($healthUrl); ?>">Kesehatan Stok</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo $reconcileUrl; ?>">Buka Rekonsiliasi</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo $opnameUrl; ?>">Buka Opname</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <section class="action-card preview-card card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <strong>Simulasi Stok Awal <?php echo html_escape($openingMonthLabel); ?></strong>
        <div class="small text-muted">Baca-saja: sistem hanya membaca saldo akhir periode ini untuk melihat apa yang nantinya dapat dibawa ke bulan berikutnya.</div>
      </div>
      <span class="badge text-bg-light border">Belum menyimpan apa pun</span>
    </div>
    <div class="card-body">
      <?php if (empty($cutoffPreview['ok'])): ?>
        <div class="alert alert-danger mb-0"><?php echo html_escape((string)($cutoffPreview['message'] ?? 'Simulasi cut-off belum dapat dibaca.')); ?></div>
      <?php else: ?>
        <div class="alert alert-info small">
          Simulasi ini tidak membuat stok awal, lot, movement, atau opname. Saldo akhir yang lebih dari nol hanya ditampilkan sebagai <strong>calon</strong> stok awal. Tahap posting cut-off tetap harus menggunakan proses resmi setelah seluruh pemeriksaan selesai.
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6 col-lg-3"><div class="preview-stat"><div class="label">Baris akhir dibaca</div><span class="value"><?php echo number_format((int)($previewSummary['source_row_count'] ?? 0), 0, ',', '.'); ?></span></div></div>
          <div class="col-6 col-lg-3"><div class="preview-stat"><div class="label">Calon stok awal</div><span class="value text-success"><?php echo number_format((int)($previewSummary['candidate_opening_count'] ?? 0), 0, ',', '.'); ?> baris</span></div></div>
          <div class="col-6 col-lg-3"><div class="preview-stat"><div class="label">Sudah ada opening</div><span class="value text-warning"><?php echo number_format((int)($previewSummary['prepared_opening_count'] ?? 0), 0, ',', '.'); ?> baris</span></div></div>
          <div class="col-6 col-lg-3"><div class="preview-stat"><div class="label">Nilai calon opening</div><span class="value"><?php echo $fmtMoney($previewSummary['candidate_total_value'] ?? 0); ?></span></div></div>
        </div>
        <div class="small text-muted mb-3">
          Saldo nol yang tidak dibawa: <strong><?php echo number_format((int)($previewSummary['zero_closing_count'] ?? 0), 0, ',', '.'); ?></strong> baris.
          Saldo minus yang wajib dibereskan: <strong class="text-danger"><?php echo number_format((int)($previewSummary['negative_closing_count'] ?? 0), 0, ',', '.'); ?></strong> baris.
        </div>
        <?php if (!empty($previewWarnings)): ?>
          <div class="alert alert-warning small">
            <strong>Catatan simulasi:</strong>
            <ul class="warning-list mt-2"><?php foreach ($previewWarnings as $warning): ?><li><?php echo html_escape((string)$warning); ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        <?php if (empty($previewRows)): ?>
          <div class="alert alert-secondary mb-0">Belum ada saldo akhir positif yang dapat dijadikan calon stok awal untuk bulan berikutnya.</div>
        <?php else: ?>
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
            <strong class="small">Contoh calon stok awal</strong>
            <span class="small text-muted">Menampilkan <?php echo number_format((int)($cutoffPreview['displayed_count'] ?? count($previewRows)), 0, ',', '.'); ?> dari <?php echo number_format((int)($previewSummary['candidate_opening_count'] ?? 0), 0, ',', '.'); ?> baris calon.</span>
          </div>
          <div class="preview-table-shell">
            <table class="table table-sm preview-table">
              <thead><tr><th>Area</th><th>Barang / Profil</th><th>Satuan</th><th class="text-end">Saldo akhir</th><th class="text-end">Nilai</th><th>Status calon opening</th></tr></thead>
              <tbody>
                <?php foreach ($previewRows as $row): ?>
                  <tr>
                    <td><strong><?php echo html_escape((string)($row['area_name'] ?? '-')); ?></strong><div class="profile-note"><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></div></td>
                    <td><strong><?php echo html_escape((string)($row['inventory_name'] ?? '-')); ?></strong><div class="profile-note"><?php echo html_escape(trim((string)($row['profile_name'] ?? '') . ((string)($row['profile_brand'] ?? '') !== '' ? ' | ' . (string)$row['profile_brand'] : ''))); ?></div></td>
                    <td><?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></td>
                    <td class="qty"><?php echo $fmtQty($row['closing_qty'] ?? 0); ?></td>
                    <td class="amount"><?php echo $fmtMoney($row['total_value'] ?? 0); ?></td>
                    <td><span class="badge <?php echo !empty($row['next_opening_exists']) ? 'text-bg-warning' : 'text-bg-success'; ?>"><?php echo html_escape((string)($row['opening_status'] ?? '-')); ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="action-card official-card card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <strong>Posting Cut-off Resmi</strong>
        <div class="small text-muted">Satu proses resmi: bentuk opname bulan ini, bentuk opening bulan berikutnya, selaraskan lot, verifikasi hasil, lalu kunci periode.</div>
      </div>
      <span class="badge <?php echo $status === 'CLOSED' ? 'text-bg-success' : ($cutoffCanPost ? 'text-bg-success' : 'text-bg-warning'); ?>">
        <?php echo $status === 'CLOSED' ? 'Sudah terkunci' : ($cutoffCanPost ? 'Siap diposting' : 'Belum siap'); ?>
      </span>
    </div>
    <div class="card-body">
      <?php if (!$cutoffSchemaReady): ?>
        <div class="alert alert-warning mb-0">
          <strong>Migration audit belum dijalankan.</strong> Jalankan <code>sql/2026-08-20a_inventory_official_cutoff_run_audit.sql</code> terlebih dahulu. Sampai migration tersedia, tombol posting sengaja tidak ditampilkan agar tidak ada cut-off tanpa jejak audit.
        </div>
      <?php elseif ($status === 'CLOSED'): ?>
        <?php if (empty($cutoffRuns)): ?>
          <div class="alert alert-warning mb-0">Periode sudah terkunci, tetapi belum memiliki riwayat Posting Cut-off Resmi. Ini biasanya periode yang dikunci dengan proses lama. Jangan menganggap opening bulan berikutnya sudah terbentuk tanpa memeriksa simulasi dan data opening terlebih dahulu.</div>
        <?php else: ?>
          <div class="alert alert-success mb-0">Periode sudah terkunci. Pembukaan ulang hanya diperlukan jika ada transaksi masa lalu yang benar-benar harus diperbaiki.</div>
        <?php endif; ?>
      <?php elseif ($status === 'CLOSING'): ?>
        <div class="alert alert-warning mb-0">Periode sedang berstatus proses cut-off. Jangan menjalankan transaksi tanggal bulan ini. Jika proses memang sudah gagal atau berhenti, gunakan pemulihan periode di bawah dengan alasan resmi.</div>
      <?php elseif (!empty($cutoffBlocks)): ?>
        <div class="alert alert-danger mb-0">
          <strong>Belum boleh diposting:</strong>
          <ul class="warning-list mt-2"><?php foreach ($cutoffBlocks as $block): ?><li><?php echo html_escape((string)$block); ?></li><?php endforeach; ?></ul>
        </div>
      <?php else: ?>
        <div class="alert alert-success small">
          Data aman untuk proses cut-off. Sistem akan memakai writer opname yang sudah digunakan aplikasi, bukan membuat jalur stok baru.
        </div>
        <?php if (!empty($cutoffWarnings)): ?>
          <div class="alert alert-warning small">
            <strong>Catatan yang perlu dibaca:</strong>
            <ul class="warning-list mt-2"><?php foreach ($cutoffWarnings as $warning): ?><li><?php echo html_escape((string)$warning); ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <?php if ($canPostCutoff && $canEdit && $hasMutationCsrf): ?>
          <form method="post" action="<?php echo site_url('inventory/stock/periods/cutoff-post/' . (int)($period['id'] ?? 0)); ?>">
            <input type="hidden" name="<?php echo html_escape($mutationCsrfName); ?>" value="<?php echo html_escape($mutationCsrfValue); ?>">
            <?php if (!empty($cutoffPosting['requires_acknowledgement'])): ?>
              <div class="form-check mb-3"><input class="form-check-input" required type="checkbox" name="acknowledge_warnings" value="1" id="ackCutoffWarnings"><label class="form-check-label" for="ackCutoffWarnings">Saya sudah membaca catatan defisit dan kesehatan stok yang masih tercatat pada bulan ini.</label></div>
            <?php endif; ?>
            <div class="row g-2">
              <div class="col-md-8"><label class="form-label">Catatan resmi cut-off</label><input required class="form-control" name="notes" placeholder="Contoh: Opname final selesai, siap membawa saldo ke bulan berikutnya"></div>
              <div class="col-md-4"><label class="form-label">Ketik konfirmasi</label><input required class="form-control" name="confirmation" placeholder="POST CUT-OFF"></div>
            </div>
            <div class="small text-muted mt-2">Setelah berhasil, periode ini terkunci. Jika ada salah satu generator gagal, periode otomatis dibuka kembali dan hasil partial tersimpan pada riwayat di bawah.</div>
            <div class="text-end mt-3"><button class="btn btn-danger" type="submit">Posting Cut-off Resmi</button></div>
          </form>
        <?php elseif (!$canPostCutoff): ?>
          <div class="alert alert-secondary mb-0">Posting cut-off resmi hanya dapat dijalankan oleh Superadmin.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!empty($cutoffRuns)): ?>
    <section class="action-card card mb-3">
      <div class="card-header"><strong>Riwayat Percobaan Cut-off</strong></div>
      <div class="card-body">
        <div class="run-table-shell">
          <table class="table table-sm run-table">
            <thead><tr><th>Run</th><th>Status</th><th>Mulai</th><th>Selesai</th><th class="text-end">Opname</th><th class="text-end">Opening</th><th class="text-end">Stok Bulanan</th><th>Catatan / Error</th></tr></thead>
            <tbody>
              <?php foreach ($cutoffRuns as $run): ?>
                <?php $runStatus = strtoupper((string)($run['status'] ?? '')); $runClass = $runStatus === 'POSTED' ? 'text-bg-success' : ($runStatus === 'RUNNING' ? 'text-bg-warning' : 'text-bg-danger'); ?>
                <tr>
                  <td><strong><?php echo html_escape((string)($run['cutoff_no'] ?? '-')); ?></strong><div class="small text-muted">Percobaan <?php echo (int)($run['attempt_no'] ?? 0); ?></div></td>
                  <td><span class="badge <?php echo $runClass; ?>"><?php echo html_escape($runStatus ?: '-'); ?></span></td>
                  <td><?php echo html_escape((string)($run['started_at'] ?? '-')); ?></td>
                  <td><?php echo html_escape((string)($run['finished_at'] ?? '-')); ?></td>
                  <td class="text-end"><?php echo number_format((int)($run['generated_opname_rows'] ?? 0), 0, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((int)($run['generated_opening_rows'] ?? 0), 0, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((int)($run['generated_monthly_rows'] ?? 0), 0, ',', '.'); ?></td>
                  <td><?php echo html_escape((string)(($run['error_message'] ?? '') ?: ($run['notes'] ?? '-'))); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($canEdit && $hasMutationCsrf && in_array($status, ['CLOSED', 'CLOSING'], true)): ?>
    <div class="action-card card">
      <div class="card-header"><strong><?php echo $status === 'CLOSING' ? 'Pulihkan Periode yang Macet' : 'Buka Kembali Periode'; ?></strong></div>
      <form method="post" action="<?php echo site_url('inventory/stock/periods/reopen/' . (int)($period['id'] ?? 0)); ?>">
        <div class="card-body">
          <input type="hidden" name="<?php echo html_escape($mutationCsrfName); ?>" value="<?php echo html_escape($mutationCsrfValue); ?>">
          <div class="alert alert-danger small">
            <?php echo $status === 'CLOSING'
                ? 'Gunakan hanya bila proses cut-off benar-benar sudah berhenti. Periksa riwayat run sebelum memulihkan periode.'
                : 'Gunakan hanya jika perlu membetulkan transaksi masa lalu. Alasan pembukaan kembali akan tersimpan sebagai audit.'; ?>
          </div>
          <div class="row g-2"><div class="col-md-8"><label class="form-label">Alasan resmi</label><input required class="form-control" name="notes" placeholder="Jelaskan alasan pembukaan atau pemulihan periode"></div><div class="col-md-4"><label class="form-label">Ketik konfirmasi</label><input required class="form-control" name="confirmation" placeholder="BUKA"></div></div>
        </div>
        <div class="card-footer bg-white text-end"><button class="btn btn-outline-danger" type="submit"><?php echo $status === 'CLOSING' ? 'Pulihkan Periode' : 'Buka Kembali Periode'; ?></button></div>
      </form>
    </div>
  <?php endif; ?>
</div>
