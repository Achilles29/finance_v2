<?php
$recentLogs = (array)($recent_logs ?? []);
$canCreate  = (bool)($can_create ?? false);
$canRetryManual = (bool)($can_retry_manual ?? false);
$retryCsrf = (string)($wa_log_retry_csrf ?? '');
$mutationCsrf = (string)($wa_broadcast_mutation_csrf ?? '');
$singleSendCsrf = (string)($wa_manual_single_send_csrf ?? '');
$activeTab  = in_array((string)($active_tab ?? ''), ['single', 'bulk'], true) ? (string)$active_tab : 'single';
$bulkQueue  = (array)($bulk_queue ?? []);
$bulkLines  = (array)($bulk_lines ?? []);
$bulkCounts = (array)($bulk_line_status_counts ?? []);
$recentBulkQueues = (array)($recent_bulk_queues ?? []);
$personalOutboundEnabled = (bool)($personal_outbound_enabled ?? false);
$personalOutboundLockMessage = (string)($personal_outbound_lock_message ?? 'Pengiriman WhatsApp personal sedang dikunci sementara.');
$canPersonalCreate = $canCreate && $personalOutboundEnabled;

$bulkStatusLabel = ['DRAFT' => 'Draft', 'QUEUED' => 'Dijadwalkan', 'SENDING' => 'Berjalan', 'DONE' => 'Selesai', 'FAILED' => 'Perlu Tindakan', 'CANCELLED' => 'Dibatalkan'];
$bulkStatusBadge = ['DRAFT' => 'bg-secondary', 'QUEUED' => 'bg-info', 'SENDING' => 'bg-warning text-dark', 'DONE' => 'bg-success', 'FAILED' => 'bg-danger', 'CANCELLED' => 'bg-light text-dark'];
$lineStatusBadge = ['PENDING' => 'bg-secondary', 'SENT' => 'bg-success', 'FAILED' => 'bg-danger', 'SKIPPED' => 'bg-light text-dark'];
$bulkCurrentStatus = strtoupper((string)($bulkQueue['status'] ?? ''));
$bulkPendingCount = (int)($bulkCounts['PENDING'] ?? 0);
$bulkSentCount = (int)($bulkCounts['SENT'] ?? 0);
$bulkFailedCount = (int)($bulkCounts['FAILED'] ?? 0);
$bulkQueueId = (int)($bulkQueue['id'] ?? 0);
$bulkAutoStart = !empty($bulk_auto_start);
$bulkDelayPattern = (array)($bulkQueue['delay_pattern'] ?? [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5, 7 => 5, 8 => 5, 9 => 5, 10 => 5]);
$bulkCanStart = $bulkQueueId > 0 && $canPersonalCreate && (
    $bulkCurrentStatus === 'DRAFT'
    || ($bulkCurrentStatus === 'SENDING' && $bulkPendingCount > 0)
    || ($bulkCurrentStatus === 'FAILED' && ($bulkPendingCount > 0 || $bulkFailedCount > 0))
);
$bulkRetryMode = $bulkCurrentStatus === 'FAILED' && $bulkPendingCount === 0 && $bulkFailedCount > 0;
$bulkResumeMode = ($bulkCurrentStatus === 'SENDING' || $bulkCurrentStatus === 'FAILED') && $bulkPendingCount > 0;
$bulkStartLabel = $bulkRetryMode ? 'Kirim Ulang Gagal' : ($bulkResumeMode ? 'Lanjutkan Pending' : 'Mulai Kirim');
$bulkFailedLineIds = [];
foreach ($bulkLines as $bulkLineForRetry) {
    if (strtoupper((string)($bulkLineForRetry['status'] ?? '')) === 'FAILED') {
        $bulkFailedLineIds[] = (int)$bulkLineForRetry['id'];
    }
}
?>

<style>
.wa-manual-member-results {
  position: absolute;
  z-index: 50;
  left: 0;
  right: 0;
  max-height: 280px;
  overflow-y: auto;
  border: 1px solid #ead6d0;
  border-radius: .75rem;
  background: #fff;
  box-shadow: 0 .75rem 2rem rgba(80, 40, 40, .12);
}
.wa-manual-result-item {
  cursor: pointer;
  padding: .7rem .9rem;
  border-bottom: 1px solid #f3e8e4;
}
.wa-manual-result-item:hover { background: #fff5f2; }
.wa-target-chip {
  display: inline-flex;
  gap: .35rem;
  align-items: center;
  border: 1px solid #f0d2ca;
  border-radius: 999px;
  padding: .35rem .65rem;
  background: #fff7f4;
  color: #7f2722;
  font-size: .78rem;
  margin: .2rem;
}
.wa-target-chip button {
  border: 0;
  background: transparent;
  color: #b42318;
  padding: 0;
  line-height: 1;
}
.wa-manual-tabs { gap: .5rem; border-bottom: 1px solid #eaded9; padding-bottom: .85rem; }
.wa-manual-tabs .nav-link { border: 1px solid #e2d4cf; border-radius: .7rem; color: #6f5852; font-weight: 700; padding: .55rem .9rem; }
.wa-manual-tabs .nav-link.active { background: #a71f2b; border-color: #a71f2b; color: #fff; box-shadow: 0 .35rem .8rem rgba(167, 31, 43, .17); }
.wa-bulk-target-panel { border: 1px dashed #d9c7bf; border-radius: .9rem; background: linear-gradient(135deg, #fffaf7, #fff); padding: 1rem; }
.wa-bulk-summary { border: 1px solid #e8ddd8; border-radius: .75rem; background: #fff; min-height: 72px; padding: .65rem .75rem; }
.wa-delay-slot { border: 1px solid #eee1dc; border-radius: .75rem; background: #fffaf8; padding: .55rem; }
.wa-delay-slot .form-label { color: #7e5850; font-size: .72rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; }
.wa-queue-stat { border: 1px solid #eadfd9; border-radius: .8rem; background: #fff; padding: .7rem .8rem; }
.wa-queue-stat .value { display: block; font-size: 1.25rem; font-weight: 800; color: #2b2523; line-height: 1.1; }
.wa-queue-stat .label { color: #88736c; font-size: .73rem; font-weight: 700; text-transform: uppercase; }
.wa-picker-table td, .wa-picker-table th { vertical-align: middle; }
.wa-picker-table tbody tr:hover { background: #fff8f5; }
.wa-picker-footer { background: #fffaf8; border-top: 1px solid #eaded9; }
.wa-bulk-queue-table { max-height: 340px; overflow: auto; }
</style>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-send-plane-line me-1"></i>Kirim Pesan Manual WA</h4>
      <p class="text-muted mb-0 small">Kirim cepat ke nomor manual/member, atau buat antrean bulk dengan jeda terukur.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= site_url('wa/log?source=MANUAL') ?>" class="btn btn-outline-primary btn-sm">
        <i class="ri ri-history-line me-1"></i>Log Manual
      </a>
      <a href="<?= site_url('wa/settings') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ri ri-settings-3-line me-1"></i>Pengaturan
      </a>
    </div>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <?php if (!$personalOutboundEnabled): ?>
  <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
    <i class="ri ri-shield-keyhole-line fs-5"></i>
    <div><strong>Pengiriman personal dihentikan.</strong><br><?= html_escape($personalOutboundLockMessage) ?></div>
  </div>
  <?php endif; ?>

  <nav class="nav wa-manual-tabs mb-3" aria-label="Mode kirim pesan manual">
    <a class="nav-link <?= $activeTab === 'single' ? 'active' : '' ?>" href="<?= site_url('wa/manual') ?>?tab=single"><i class="ri ri-flashlight-line me-1"></i>Kirim Cepat</a>
    <a class="nav-link <?= $activeTab === 'bulk' ? 'active' : '' ?>" href="<?= site_url('wa/manual') ?>?tab=bulk"><i class="ri ri-list-check-2 me-1"></i>Kirim Pesan Bulk</a>
  </nav>

  <?php if ($activeTab === 'single'): ?>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Form Pesan Cepat</h5>
        </div>
        <form method="post" enctype="multipart/form-data" action="<?= site_url('wa/manual') ?>" id="waManualForm">
          <input type="hidden" name="wa_manual_single_send_csrf" value="<?= html_escape($singleSendCsrf) ?>">
          <input type="hidden" name="selected_member_ids" id="selectedMemberIds" value="">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Pesan</label>
              <textarea name="message" class="form-control" rows="7" placeholder="Tulis pesan WhatsApp di sini..."><?= html_escape((string)$this->input->post('message', false)) ?></textarea>
              <div class="form-text">Jika mengirim gambar, pesan ini akan menjadi caption. Isi pesan atau gambar wajib ada.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Gambar (opsional)</label>
              <input type="file" name="media_image" id="mediaImage" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.</div>
              <div id="mediaPreview" class="mt-2 d-none">
                <img src="" alt="Preview gambar" class="img-fluid rounded border" style="max-height:220px;">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Nomor Manual</label>
              <textarea name="manual_numbers" class="form-control font-monospace" rows="5" placeholder="6281234567890&#10;081234567890 | Nama Customer"><?= html_escape((string)$this->input->post('manual_numbers', false)) ?></textarea>
              <div class="form-text">Satu nomor per baris. Format opsional: <code>nomor | nama</code>. Nomor 08 otomatis diubah ke 628.</div>
            </div>

            <div class="mb-2 position-relative">
              <label class="form-label fw-semibold">Ambil Nomor Dari Member</label>
              <input type="text" id="memberSearch" class="form-control" autocomplete="off" placeholder="Cari no member / nama / nomor HP...">
              <div id="memberResults" class="wa-manual-member-results d-none"></div>
              <div class="form-text">Hanya member aktif dan punya nomor HP yang ditampilkan.</div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label fw-semibold mb-0">Member Dipilih</label>
                <button type="button" class="btn btn-link btn-sm p-0 text-danger" id="clearMembers">Bersihkan</button>
              </div>
              <div id="selectedMembers" class="border rounded p-2 min-vh-25 text-muted small">Belum ada member dipilih.</div>
            </div>

            <div class="alert alert-info small mb-0">
              <i class="ri ri-information-line me-1"></i>
              Pesan manual langsung dikirim dan tercatat di Log Pengiriman dengan sumber <strong>MANUAL</strong>.
            </div>
          </div>
          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= site_url('wa/dashboard') ?>" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" <?= !$canPersonalCreate ? 'disabled' : '' ?> id="btnManualSubmit">
              <i class="ri ri-send-plane-line me-1"></i>Kirim Pesan
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Log Manual Terakhir</h5>
          <span class="badge bg-label-secondary"><?= number_format(count($recentLogs)) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:520px;overflow:auto;">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Waktu</th>
                  <th>Tujuan</th>
                  <th>Status</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                  <td class="small text-muted"><?= html_escape(date('d/m H:i', strtotime($log['sent_at']))) ?></td>
                  <td class="small">
                    <div class="fw-semibold"><?= html_escape($log['display_name'] ?: '-') ?></div>
                    <div class="text-muted font-monospace"><?= html_escape($log['phone_number'] ?: '-') ?></div>
                  </td>
                  <td>
                    <?php if (($log['status'] ?? '') === 'SENT'): ?>
                      <span class="badge bg-success">Terkirim</span>
                    <?php else: ?>
                      <span class="badge bg-danger" title="<?= html_escape($log['error_detail'] ?? '') ?>">Gagal</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php if (($log['status'] ?? '') === 'FAILED' && $canRetryManual): ?>
                      <button type="button" class="btn btn-outline-danger btn-sm" data-retry-log="<?= (int)$log['id'] ?>">
                        Retry
                      </button>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentLogs)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pengiriman manual.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($activeTab === 'bulk'): ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <form method="post" enctype="multipart/form-data" action="<?= site_url('wa/manual') ?>" id="waBulkForm">
        <input type="hidden" name="delivery_mode" value="bulk">
        <input type="hidden" name="wa_broadcast_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
        <input type="hidden" name="selected_member_ids" id="bulkSelectedMemberIds" value="">
        <div class="card border-0 shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h5 class="mb-0">Kirim Pesan Bulk</h5>
              <div class="small text-muted mt-1">Nomor manual dan member terpilih dikirim satu per satu dengan jeda yang Anda atur.</div>
            </div>
            <span class="badge bg-warning text-dark"><i class="ri ri-timer-line me-1"></i>Diproses Serial</span>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Pesan</label>
              <textarea name="message" class="form-control" rows="6" placeholder="Tulis pesan WhatsApp di sini..."></textarea>
              <div class="form-text">Gunakan <code>&#123;&#123;nama&#125;&#125;</code> bila ingin menyapa nama member pada pesan.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Gambar (opsional)</label>
              <input type="file" name="media_image" id="bulkMediaImage" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB. Pesan menjadi caption gambar.</div>
              <div id="bulkMediaPreview" class="mt-2 d-none"><img src="" alt="Preview gambar" class="img-fluid rounded border" style="max-height:180px;"></div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Nomor Manual</label>
              <textarea name="manual_numbers" id="bulkManualNumbers" class="form-control font-monospace" rows="4" placeholder="6281234567890 | Nama Customer&#10;081234567890"></textarea>
              <div class="form-text">Opsional. Satu nomor per baris. Nomor ganda hanya akan dibuat satu target.</div>
            </div>

            <div class="wa-bulk-target-panel">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div>
                  <div class="fw-semibold">Target Member</div>
                  <div class="small text-muted">Pilih member dari modal dengan filter dan pagination.</div>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-outline-danger btn-sm" id="bulkClearMembers">Bersihkan</button>
                  <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkMemberModal"><i class="ri ri-user-add-line me-1"></i>Pilih Member</button>
                </div>
              </div>
              <div id="bulkSelectedMembers" class="wa-bulk-summary text-muted small">Belum ada member dipilih.</div>
            </div>
          </div>
          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= site_url('wa/dashboard') ?>" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" <?= !$canPersonalCreate ? 'disabled' : '' ?> id="btnBulkCreate"><i class="ri ri-send-plane-line me-1"></i>Kirim Pesan Bulk</button>
          </div>
        </div>
      </form>
    </div>

    <div class="col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header"><h5 class="mb-0">Pola Jeda Pesan</h5></div>
        <div class="card-body">
          <p class="small text-muted mb-3">Setelah satu nomor selesai, sistem menunggu sesuai slot urutan. Slot 1 sampai 10 diulang sampai antrean habis.</p>
          <div class="row g-2">
            <?php for ($slot = 1; $slot <= 10; $slot++): ?>
            <div class="col-6 col-md-4 col-xl-6">
              <div class="wa-delay-slot">
                <label class="form-label mb-1" for="bulkDelay<?= $slot ?>">Pesan <?= $slot ?></label>
                <div class="input-group input-group-sm">
                  <input form="waBulkForm" type="number" name="delay_pattern[<?= $slot ?>]" id="bulkDelay<?= $slot ?>" class="form-control" min="1" max="60" required value="5">
                  <span class="input-group-text">dtk</span>
                </div>
              </div>
            </div>
            <?php endfor; ?>
          </div>
          <div class="alert alert-warning small mt-3 mb-0"><i class="ri ri-shield-check-line me-1"></i>Setelah tombol kirim ditekan, antrean dibuat cepat lalu pengiriman dimulai otomatis. Nomor yang ditolak WhatsApp dicatat gagal dan antrean lanjut; bot terputus menghentikan proses.</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($bulkQueueId > 0): ?>
  <div class="card border-0 shadow-sm mt-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h5 class="mb-0"><i class="ri ri-list-check-2 me-1"></i>Antrean Bulk #<?= $bulkQueueId ?></h5>
        <div class="small text-muted mt-1"><?= html_escape($bulkQueue['name'] ?? '-') ?></div>
      </div>
      <span class="badge <?= $bulkStatusBadge[$bulkCurrentStatus] ?? 'bg-secondary' ?>"><?= html_escape($bulkStatusLabel[$bulkCurrentStatus] ?? $bulkCurrentStatus ?: '-') ?></span>
    </div>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="wa-queue-stat"><span class="label">Target</span><span class="value"><?= number_format(count($bulkLines)) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="wa-queue-stat"><span class="label">Terkirim</span><span class="value text-success"><?= number_format($bulkSentCount) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="wa-queue-stat"><span class="label">Pending</span><span class="value text-secondary"><?= number_format($bulkPendingCount) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="wa-queue-stat"><span class="label">Gagal</span><span class="value text-danger"><?= number_format($bulkFailedCount) ?></span></div></div>
      </div>
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div class="small text-muted">
          <strong>Pola jeda:</strong> <?= html_escape(implode(', ', array_map(static fn($value, $slot) => $slot . '=' . $value . ' dtk', $bulkDelayPattern, array_keys($bulkDelayPattern)))) ?><br>
          <strong>Dibuat:</strong> <?= !empty($bulkQueue['created_at']) ? html_escape(date('d/m/Y H:i', strtotime($bulkQueue['created_at']))) : '-' ?>
        </div>
        <?php if ($bulkCanStart): ?>
          <button type="button" class="btn btn-success" id="btnBulkStart"><i class="ri ri-send-plane-line me-1"></i><?= html_escape($bulkStartLabel) ?></button>
        <?php endif; ?>
      </div>
      <div id="bulkProgressWrap" class="d-none mb-3">
        <div class="progress" style="height:7px;"><div id="bulkProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%"></div></div>
        <div id="bulkProgressText" class="small text-muted mt-1">Menyiapkan antrean...</div>
      </div>
      <div class="wa-bulk-queue-table border rounded">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light sticky-top"><tr><th>#</th><th>Nama</th><th>Nomor</th><th class="text-center">Status</th><th>Waktu</th><th>Error</th></tr></thead>
          <tbody>
            <?php foreach ($bulkLines as $index => $line): $lineStatus = strtoupper((string)($line['status'] ?? 'PENDING')); ?>
            <tr id="bulkLine<?= (int)$line['id'] ?>">
              <td class="text-muted"><?= $index + 1 ?></td>
              <td><?= html_escape($line['display_name'] ?: '-') ?></td>
              <td class="font-monospace small"><?= html_escape($line['phone_number'] ?? '-') ?></td>
              <td class="text-center"><span class="badge <?= $lineStatusBadge[$lineStatus] ?? 'bg-secondary' ?>"><?= html_escape($lineStatus) ?></span></td>
              <td class="small text-muted"><?= !empty($line['sent_at']) ? html_escape(date('H:i:s', strtotime($line['sent_at']))) : '-' ?></td>
              <td class="small text-danger"><?= html_escape($line['error_msg'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($bulkLines)): ?><tr><td colspan="6" class="text-center text-muted py-3">Tidak ada penerima pada antrean ini.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm mt-3">
    <div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Antrean Bulk Terbaru</h5><span class="badge bg-label-secondary"><?= number_format(count($recentBulkQueues)) ?></span></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light"><tr><th>Dibuat</th><th>Antrean</th><th class="text-center">Target</th><th class="text-center">Terkirim</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recentBulkQueues as $queue): $queueStatus = strtoupper((string)($queue['status'] ?? '')); ?>
            <tr>
              <td class="small text-muted"><?= !empty($queue['created_at']) ? html_escape(date('d/m H:i', strtotime($queue['created_at']))) : '-' ?></td>
              <td><div class="fw-semibold"><?= html_escape($queue['name'] ?? '-') ?></div><div class="small text-muted">#<?= (int)$queue['id'] ?></div></td>
              <td class="text-center"><?= number_format((int)($queue['total_targets'] ?? 0)) ?></td>
              <td class="text-center text-success fw-semibold"><?= number_format((int)($queue['total_sent'] ?? 0)) ?></td>
              <td><span class="badge <?= $bulkStatusBadge[$queueStatus] ?? 'bg-secondary' ?>"><?= html_escape($bulkStatusLabel[$queueStatus] ?? $queueStatus ?: '-') ?></span></td>
              <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="<?= site_url('wa/manual') ?>?tab=bulk&amp;bulk_id=<?= (int)$queue['id'] ?>">Buka</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentBulkQueues)): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada antrean pesan bulk.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="modal fade" id="bulkMemberModal" tabindex="-1" aria-labelledby="bulkMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header">
        <div><h5 class="modal-title" id="bulkMemberModalLabel">Pilih Member Penerima</h5><div class="small text-muted">Centang seluruh member pada halaman aktif dengan satu klik.</div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-0">
        <div class="p-3 border-bottom">
          <div class="row g-2 align-items-end">
            <div class="col-md-7"><label class="form-label small fw-semibold mb-1" for="bulkMemberSearch">Cari Member</label><input type="search" id="bulkMemberSearch" class="form-control" placeholder="No. member, nama, atau nomor HP"></div>
            <div class="col-7 col-md-3"><label class="form-label small fw-semibold mb-1" for="bulkMemberPerPage">Baris per halaman</label><select id="bulkMemberPerPage" class="form-select"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select></div>
            <div class="col-5 col-md-2 d-grid"><button type="button" class="btn btn-outline-primary" id="bulkMemberSearchBtn"><i class="ri ri-search-line me-1"></i>Cari</button></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover wa-picker-table mb-0">
            <thead class="table-light"><tr><th class="text-center" style="width:44px;"><input type="checkbox" class="form-check-input" id="bulkSelectPage" title="Pilih semua member di halaman ini"></th><th>No. Member</th><th>Nama</th><th>Nomor HP</th><th>Tier</th></tr></thead>
            <tbody id="bulkMemberPickerRows"><tr><td colspan="5" class="text-center text-muted py-4">Membuka daftar member...</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer wa-picker-footer d-flex justify-content-between flex-wrap gap-2">
        <div id="bulkMemberPickerMeta" class="small text-muted">Memuat...</div>
        <div class="d-flex align-items-center gap-2"><div id="bulkMemberPagination" class="btn-group btn-group-sm"></div><button type="button" class="btn btn-primary" id="bulkMemberApply" data-bs-dismiss="modal"><i class="ri ri-check-line me-1"></i>Pakai 0 Member</button></div>
      </div>
    </div></div>
  </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const memberSearch = document.getElementById('memberSearch');
  const memberResults = document.getElementById('memberResults');
  const selectedMembersEl = document.getElementById('selectedMembers');
  const selectedMemberIds = document.getElementById('selectedMemberIds');
  const clearMembers = document.getElementById('clearMembers');
  const form = document.getElementById('waManualForm');
  const submitBtn = document.getElementById('btnManualSubmit');
  const mediaImage = document.getElementById('mediaImage');
  const mediaPreview = document.getElementById('mediaPreview');
  const selected = new Map();
  let timer = null;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
  }

  function renderSelected() {
    selectedMemberIds.value = Array.from(selected.keys()).join(',');
    if (selected.size === 0) {
      selectedMembersEl.className = 'border rounded p-2 min-vh-25 text-muted small';
      selectedMembersEl.textContent = 'Belum ada member dipilih.';
      return;
    }
    selectedMembersEl.className = 'border rounded p-2';
    selectedMembersEl.innerHTML = Array.from(selected.values()).map(row => `
      <span class="wa-target-chip" data-id="${Number(row.id)}">
        <span>${escapeHtml(row.member_name || '-')} · <span class="font-monospace">${escapeHtml(row.mobile_phone || '')}</span></span>
        <button type="button" title="Hapus" data-remove-member="${Number(row.id)}">×</button>
      </span>
    `).join('');
  }

  function hideResults() {
    if (!memberResults) return;
    memberResults.classList.add('d-none');
    memberResults.innerHTML = '';
  }

  function searchMembers(q) {
    if (q.length < 2) {
      hideResults();
      return;
    }
    memberResults.classList.remove('d-none');
    memberResults.innerHTML = '<div class="p-3 text-muted small">Mencari member...</div>';
    fetch(`<?= site_url('wa/api/member-search') ?>?q=${encodeURIComponent(q)}&limit=12`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        const rows = Array.isArray(data.rows) ? data.rows : [];
        if (!rows.length) {
          memberResults.innerHTML = '<div class="p-3 text-muted small">Member tidak ditemukan.</div>';
          return;
        }
        memberResults.innerHTML = rows.map(row => `
          <div class="wa-manual-result-item" data-member-id="${Number(row.id)}"
               data-member='${escapeHtml(JSON.stringify(row))}'>
            <div class="fw-semibold">${escapeHtml(row.member_name || '-')}</div>
            <div class="small text-muted">
              ${escapeHtml(row.member_no || '-')} · <span class="font-monospace">${escapeHtml(row.mobile_phone || '-')}</span>
            </div>
          </div>
        `).join('');
      })
      .catch(() => {
        memberResults.innerHTML = '<div class="p-3 text-danger small">Gagal mencari member.</div>';
      });
  }

  memberSearch?.addEventListener('input', function() {
    clearTimeout(timer);
    const q = this.value.trim();
    timer = setTimeout(() => searchMembers(q), 250);
  });

  memberResults?.addEventListener('click', function(e) {
    const item = e.target.closest('[data-member-id]');
    if (!item) return;
    try {
      const row = JSON.parse(item.dataset.member || '{}');
      if (row.id) {
        selected.set(String(row.id), row);
        renderSelected();
        memberSearch.value = '';
        hideResults();
      }
    } catch (err) {
      hideResults();
    }
  });

  selectedMembersEl?.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-remove-member]');
    if (!btn) return;
    selected.delete(String(btn.dataset.removeMember));
    renderSelected();
  });

  clearMembers?.addEventListener('click', function() {
    selected.clear();
    renderSelected();
  });

  document.addEventListener('click', function(e) {
    if (!e.target.closest('#memberSearch') && !e.target.closest('#memberResults')) {
      hideResults();
    }
  });

  form?.addEventListener('submit', function() {
    if (!submitBtn) return;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Mengirim...';
  });

  mediaImage?.addEventListener('change', function() {
    const img = mediaPreview?.querySelector('img');
    const file = this.files && this.files[0] ? this.files[0] : null;
    if (!mediaPreview || !img || !file) {
      mediaPreview?.classList.add('d-none');
      return;
    }
    img.src = URL.createObjectURL(file);
    mediaPreview.classList.remove('d-none');
  });

  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-retry-log]');
    if (!btn) return;
    if (!confirm('Kirim ulang pesan manual ini?')) return;

    const oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Mengirim...';

    fetch('<?= site_url('wa/api/log-retry/') ?>' + btn.getAttribute('data-retry-log'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-Wa-Log-Retry-CSRF': <?= json_encode($retryCsrf) ?>
      }
    })
      .then(r => r.json())
      .then(data => {
        alert(data.ok ? 'Pesan berhasil dikirim ulang.' : ('Gagal kirim ulang: ' + (data.message || 'Error tidak diketahui')));
        window.location.reload();
      })
      .catch(err => {
        alert('Koneksi error: ' + err);
        btn.disabled = false;
        btn.textContent = oldText;
      });
  });
})();
</script>

<script>
(() => {
  const bulkForm = document.getElementById('waBulkForm');
  if (!bulkForm) return;

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
  const bulkSelectedIds = document.getElementById('bulkSelectedMemberIds');
  const bulkSelectedSummary = document.getElementById('bulkSelectedMembers');
  const bulkClearMembers = document.getElementById('bulkClearMembers');
  const bulkModalEl = document.getElementById('bulkMemberModal');
  const bulkRowsEl = document.getElementById('bulkMemberPickerRows');
  const bulkSearch = document.getElementById('bulkMemberSearch');
  const bulkPerPage = document.getElementById('bulkMemberPerPage');
  const bulkSearchButton = document.getElementById('bulkMemberSearchBtn');
  const bulkSelectPage = document.getElementById('bulkSelectPage');
  const bulkPagination = document.getElementById('bulkMemberPagination');
  const bulkPickerMeta = document.getElementById('bulkMemberPickerMeta');
  const bulkMemberApply = document.getElementById('bulkMemberApply');
  const bulkMediaImage = document.getElementById('bulkMediaImage');
  const bulkMediaPreview = document.getElementById('bulkMediaPreview');
  const bulkCreate = document.getElementById('btnBulkCreate');
  const bulkSelected = new Map();
  const picker = { rows: [], page: 1, perPage: 25, totalRows: 0, totalPages: 1, query: '', loaded: false };
  const bulkModal = bulkModalEl && window.bootstrap && bootstrap.Modal ? new bootstrap.Modal(bulkModalEl) : null;

  function renderBulkSelection() {
    if (bulkSelectedIds) bulkSelectedIds.value = Array.from(bulkSelected.keys()).join(',');
    if (bulkMemberApply) bulkMemberApply.innerHTML = `<i class="ri ri-check-line me-1"></i>Pakai ${bulkSelected.size} Member`;
    if (!bulkSelectedSummary) return;
    if (!bulkSelected.size) {
      bulkSelectedSummary.className = 'wa-bulk-summary text-muted small';
      bulkSelectedSummary.textContent = 'Belum ada member dipilih.';
      return;
    }
    const rows = Array.from(bulkSelected.values());
    const preview = rows.slice(0, 8).map(row => `<span class="wa-target-chip"><span>${escapeHtml(row.member_name || '-')}</span><button type="button" title="Hapus" data-bulk-remove-member="${Number(row.id)}">×</button></span>`).join('');
    const remainder = rows.length - 8;
    bulkSelectedSummary.className = 'wa-bulk-summary';
    bulkSelectedSummary.innerHTML = `<div class="small fw-semibold mb-1">${rows.length} member dipilih</div>${preview}${remainder > 0 ? `<span class="small text-muted ms-1">+${remainder} member lainnya</span>` : ''}`;
  }

  function renderPicker() {
    if (!bulkRowsEl) return;
    if (!picker.rows.length) {
      bulkRowsEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Member tidak ditemukan.</td></tr>';
    } else {
      bulkRowsEl.innerHTML = picker.rows.map(row => `
        <tr>
          <td class="text-center"><input type="checkbox" class="form-check-input" data-bulk-member-id="${Number(row.id)}" ${bulkSelected.has(String(row.id)) ? 'checked' : ''}></td>
          <td class="small">${escapeHtml(row.member_no || '-')}</td>
          <td><div class="fw-semibold">${escapeHtml(row.member_name || '-')}</div><div class="small text-muted">${escapeHtml(row.member_status || '')}</div></td>
          <td class="font-monospace small">${escapeHtml(row.mobile_phone || '-')}</td>
          <td class="small">${escapeHtml(row.member_tier || '-')}</td>
        </tr>`).join('');
    }
    const selectedOnPage = picker.rows.filter(row => bulkSelected.has(String(row.id))).length;
    if (bulkSelectPage) {
      bulkSelectPage.checked = picker.rows.length > 0 && selectedOnPage === picker.rows.length;
      bulkSelectPage.indeterminate = selectedOnPage > 0 && selectedOnPage < picker.rows.length;
    }
    if (bulkPickerMeta) {
      const first = picker.rows.length ? ((picker.page - 1) * picker.perPage + 1) : 0;
      const last = (picker.page - 1) * picker.perPage + picker.rows.length;
      bulkPickerMeta.textContent = `Menampilkan ${first}-${last} dari ${picker.totalRows} member · ${bulkSelected.size} dipilih`;
    }
    if (bulkPagination) {
      bulkPagination.innerHTML = `<button type="button" class="btn btn-outline-secondary" data-bulk-page="${picker.page - 1}" ${picker.page <= 1 ? 'disabled' : ''}><i class="ri ri-arrow-left-s-line"></i></button><button type="button" class="btn btn-outline-secondary disabled">${picker.page} / ${picker.totalPages}</button><button type="button" class="btn btn-outline-secondary" data-bulk-page="${picker.page + 1}" ${picker.page >= picker.totalPages ? 'disabled' : ''}><i class="ri ri-arrow-right-s-line"></i></button>`;
    }
    renderBulkSelection();
  }

  function loadBulkMembers(page = 1) {
    if (!bulkRowsEl) return;
    picker.page = Math.max(1, page);
    picker.query = bulkSearch?.value.trim() || '';
    picker.perPage = Number(bulkPerPage?.value || 25);
    bulkRowsEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat member...</td></tr>';
    if (bulkPickerMeta) bulkPickerMeta.textContent = 'Memuat daftar member...';
    fetch(`<?= site_url('wa/api/member-picker') ?>?q=${encodeURIComponent(picker.query)}&page=${picker.page}&per_page=${picker.perPage}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(response => response.json())
      .then(data => {
        if (!data.ok) throw new Error(data.message || 'Gagal memuat member.');
        picker.rows = Array.isArray(data.rows) ? data.rows : [];
        picker.page = Number(data.page || 1);
        picker.perPage = Number(data.per_page || 25);
        picker.totalRows = Number(data.total_rows || 0);
        picker.totalPages = Math.max(1, Number(data.total_pages || 1));
        picker.loaded = true;
        renderPicker();
      })
      .catch(error => {
        bulkRowsEl.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(error.message || 'Gagal memuat member.')}</td></tr>`;
        if (bulkPickerMeta) bulkPickerMeta.textContent = 'Daftar member belum tersedia.';
      });
  }

  bulkModalEl?.addEventListener('shown.bs.modal', () => { if (!picker.loaded) loadBulkMembers(1); });
  bulkSearchButton?.addEventListener('click', () => loadBulkMembers(1));
  bulkSearch?.addEventListener('keydown', event => {
    if (event.key === 'Enter') { event.preventDefault(); loadBulkMembers(1); }
  });
  bulkPerPage?.addEventListener('change', () => loadBulkMembers(1));
  bulkRowsEl?.addEventListener('change', event => {
    const input = event.target.closest('[data-bulk-member-id]');
    if (!input) return;
    const row = picker.rows.find(item => String(item.id) === String(input.dataset.bulkMemberId));
    if (!row) return;
    if (input.checked) bulkSelected.set(String(row.id), row);
    else bulkSelected.delete(String(row.id));
    renderPicker();
  });
  bulkSelectPage?.addEventListener('change', function() {
    picker.rows.forEach(row => {
      if (this.checked) bulkSelected.set(String(row.id), row);
      else bulkSelected.delete(String(row.id));
    });
    renderPicker();
  });
  bulkPagination?.addEventListener('click', event => {
    const button = event.target.closest('[data-bulk-page]');
    if (button && !button.disabled) loadBulkMembers(Number(button.dataset.bulkPage));
  });
  bulkMemberApply?.addEventListener('click', () => { if (bulkModal) bulkModal.hide(); });
  bulkClearMembers?.addEventListener('click', () => {
    bulkSelected.clear();
    renderBulkSelection();
    if (picker.loaded) renderPicker();
  });
  bulkSelectedSummary?.addEventListener('click', event => {
    const button = event.target.closest('[data-bulk-remove-member]');
    if (!button) return;
    bulkSelected.delete(String(button.dataset.bulkRemoveMember));
    renderBulkSelection();
    if (picker.loaded) renderPicker();
  });
  bulkMediaImage?.addEventListener('change', function() {
    const image = bulkMediaPreview?.querySelector('img');
    const file = this.files?.[0];
    if (!bulkMediaPreview || !image || !file) { bulkMediaPreview?.classList.add('d-none'); return; }
    image.src = URL.createObjectURL(file);
    bulkMediaPreview.classList.remove('d-none');
  });
  bulkForm.addEventListener('submit', event => {
    const manualNumbers = document.getElementById('bulkManualNumbers')?.value.trim() || '';
    if (!manualNumbers && !bulkSelected.size) {
      event.preventDefault();
      alert('Isi minimal satu nomor manual atau pilih member penerima.');
      return;
    }
    if (bulkCreate) {
      bulkCreate.disabled = true;
      bulkCreate.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Menyiapkan pengiriman...';
    }
  });
  renderBulkSelection();

  const bulkStartButton = document.getElementById('btnBulkStart');
  bulkStartButton?.addEventListener('click', function() {
    const retryMode = <?= json_encode($bulkRetryMode) ?>;
    const resumeMode = <?= json_encode($bulkResumeMode) ?>;
    const retryLineIds = <?= json_encode($bulkFailedLineIds) ?>;
    const confirmation = retryMode ? 'Kirim ulang semua nomor yang gagal?' : (resumeMode ? 'Lanjutkan nomor yang masih pending?' : 'Mulai mengirim antrean bulk sekarang?');
    const autoStart = this.dataset.autoStart === '1';
    delete this.dataset.autoStart;
    if (!autoStart && !confirm(confirmation)) return;
    const button = this;
    const progressWrap = document.getElementById('bulkProgressWrap');
    const progressBar = document.getElementById('bulkProgressBar');
    const progressText = document.getElementById('bulkProgressText');
    button.disabled = true;
    button.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Mengirim...';
    progressWrap?.classList.remove('d-none');
    let retryIndex = 0;

    const runNext = () => {
      const isRetry = retryMode && retryLineIds.length > 0;
      const retryLast = isRetry && retryIndex === retryLineIds.length - 1;
      const query = isRetry ? `?retry=1&line_id=${encodeURIComponent(retryLineIds[retryIndex])}&retry_last=${retryLast ? '1' : '0'}` : '';
      fetch('<?= site_url('wa/api/broadcast-start/' . $bulkQueueId) ?>' + query, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-Wa-Broadcast-CSRF': <?= json_encode($mutationCsrf) ?>
        }
      })
        .then(async response => {
          const body = await response.text();
          try { return JSON.parse(body); }
          catch (error) { throw new Error(`Respons server HTTP ${response.status}: ${body.slice(0, 300)}`); }
        })
        .then(data => {
          if (!data.ok) {
            const message = 'Proses dihentikan: ' + (data.message || 'Error tidak diketahui');
            alert(message);
            if (progressText) progressText.textContent = message;
            button.disabled = false;
            button.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Coba Lagi';
            return;
          }
          const sent = Number(data.total_sent || 0);
          const failed = Number(data.total_failed || 0);
          const pending = Number(data.total_pending || 0);
          const total = sent + failed + pending;
          const percent = total > 0 ? Math.min(100, Math.round(((sent + failed) / total) * 100)) : 100;
          if (progressBar) progressBar.style.width = percent + '%';
          if (progressText) progressText.textContent = `Terkirim ${sent} · Gagal ${failed} · Pending ${pending} (${percent}%)`;
          if (isRetry) retryIndex += 1;
          const shouldContinue = isRetry ? retryIndex < retryLineIds.length : Boolean(data.has_more);
          if (shouldContinue) {
            const delaySeconds = Math.max(1, Number(data.delay_seconds || 1));
            if (progressText) progressText.textContent += ` · Menunggu ${delaySeconds} detik untuk nomor berikutnya`;
            setTimeout(runNext, delaySeconds * 1000);
            return;
          }
          alert(`Antrean bulk selesai. Terkirim: ${sent}, gagal: ${failed}.`);
          window.location.reload();
        })
        .catch(error => {
          const message = 'Koneksi error: ' + error.message + '. Antrean dapat dilanjutkan dari nomor yang masih pending.';
          alert(message);
          if (progressText) progressText.textContent = message;
          button.disabled = false;
          button.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Coba Lagi';
        });
    };
    runNext();
  });

  if (<?= json_encode($bulkAutoStart) ?> && bulkStartButton && !bulkStartButton.disabled) {
    if (window.history?.replaceState && window.URL) {
      const url = new URL(window.location.href);
      url.searchParams.delete('autostart');
      window.history.replaceState({}, '', url.toString());
    }
    window.setTimeout(() => {
      bulkStartButton.dataset.autoStart = '1';
      bulkStartButton.click();
    }, 350);
  }
})();
</script>
