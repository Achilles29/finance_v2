<?php
$broadcast  = (array)($broadcast ?? []);
$lines      = (array)($lines ?? []);
$canEdit    = (bool)($can_edit ?? false);
$canDelete  = (bool)($can_delete ?? false);
$mutationCsrf = (string)($wa_broadcast_mutation_csrf ?? '');
$personalOutboundEnabled = (bool)($personal_outbound_enabled ?? false);
$personalOutboundLockMessage = (string)($personal_outbound_lock_message ?? 'Pengiriman WhatsApp personal sedang dikunci sementara.');

$statusLabel = ['DRAFT'=>'Draft','QUEUED'=>'Dijadwalkan','SENDING'=>'Berjalan','DONE'=>'Selesai','FAILED'=>'Gagal','CANCELLED'=>'Dibatalkan'];
$statusBadge = ['DRAFT'=>'bg-secondary','QUEUED'=>'bg-info','SENDING'=>'bg-warning','DONE'=>'bg-success','FAILED'=>'bg-danger','CANCELLED'=>'bg-light text-dark'];
$lineBadge   = ['PENDING'=>'bg-secondary','SENT'=>'bg-success','FAILED'=>'bg-danger','SKIPPED'=>'bg-light text-dark'];
$targetTypeLabel = [
  'MANUAL' => 'Manual',
  'SELECTED_MEMBERS' => 'Member Terpilih',
  'ALL_MEMBERS' => 'Semua Member',
  'MEMBER_ACTIVE' => 'Member Aktif',
  'CUSTOM' => 'Custom',
];

$currentStatus = $broadcast['status'] ?? 'DRAFT';
$lineStatusCounts = ['PENDING' => 0, 'SENT' => 0, 'FAILED' => 0, 'SKIPPED' => 0];
foreach ($lines as $lineForCount) {
    $lineStatus = strtoupper((string)($lineForCount['status'] ?? 'PENDING'));
    $lineStatusCounts[$lineStatus] = (int)($lineStatusCounts[$lineStatus] ?? 0) + 1;
}
$pendingLineCount = (int)($lineStatusCounts['PENDING'] ?? 0);
$failedLineCount = (int)($lineStatusCounts['FAILED'] ?? 0);
$canStart = $canEdit && $personalOutboundEnabled && (
    $currentStatus === 'DRAFT'
    || ($currentStatus === 'FAILED' && ($pendingLineCount > 0 || $failedLineCount > 0))
    || ($currentStatus === 'SENDING' && $pendingLineCount > 0)
);
$canUpdate = $canEdit && in_array($currentStatus, ['DRAFT','FAILED','CANCELLED'], true);
$startButtonLabel = $currentStatus === 'FAILED'
    ? ($pendingLineCount > 0 ? 'Lanjutkan Pending' : 'Kirim Ulang Gagal')
    : ($currentStatus === 'SENDING' ? 'Lanjutkan Kirim' : 'Mulai Kirim');
$bcId = (int)($broadcast['id'] ?? 0);
$delayPattern = (array)($broadcast['delay_pattern'] ?? [1=>2,2=>2,3=>2,4=>2,5=>2,6=>2,7=>2,8=>2,9=>2,10=>2]);
$failedLineIds = [];
foreach ($lines as $lineForRetry) {
    if (strtoupper((string)($lineForRetry['status'] ?? '')) === 'FAILED') {
        $failedLineIds[] = (int)$lineForRetry['id'];
    }
}
?>

<div class="container-xxl py-3">
  <div class="mb-3">
    <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri ri-arrow-left-line me-1"></i>Kembali
    </a>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <?php if (!$personalOutboundEnabled): ?>
  <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
    <i class="ri ri-shield-keyhole-line fs-5"></i>
    <div><strong>Antrean personal tidak dapat dijalankan.</strong><br><?= html_escape($personalOutboundLockMessage) ?></div>
  </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Info Broadcast</h5>
          <span class="badge <?= $statusBadge[$currentStatus] ?? 'bg-secondary' ?>">
            <?= $statusLabel[$currentStatus] ?? $currentStatus ?>
          </span>
        </div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-5">Nama</dt>
            <dd class="col-7"><?= html_escape($broadcast['name'] ?? '-') ?></dd>
            <dt class="col-5">Tipe Target</dt>
            <dd class="col-7"><?= html_escape($targetTypeLabel[$broadcast['target_type'] ?? ''] ?? ($broadcast['target_type'] ?? '-')) ?></dd>
            <dt class="col-5">Total Target</dt>
            <dd class="col-7"><?= number_format(count($lines)) ?></dd>
            <dt class="col-5">Pola Jeda</dt>
            <dd class="col-7 small"><?= html_escape(implode(', ', array_map(static fn($value, $slot) => $slot . '=' . $value . ' dtk', $delayPattern, array_keys($delayPattern)))) ?></dd>
            <dt class="col-5">Terkirim</dt>
            <dd class="col-7 text-success fw-semibold"><?= number_format((int)($lineStatusCounts['SENT'] ?? 0)) ?></dd>
            <dt class="col-5">Gagal</dt>
            <dd class="col-7 text-danger fw-semibold"><?= number_format((int)($lineStatusCounts['FAILED'] ?? 0)) ?></dd>
            <dt class="col-5">Dibuat</dt>
            <dd class="col-7"><?= html_escape($broadcast['created_at'] ?? '-') ?></dd>
            <dt class="col-5">Mulai Kirim</dt>
            <dd class="col-7"><?= html_escape($broadcast['started_at'] ?? '-') ?></dd>
            <dt class="col-5">Selesai</dt>
            <dd class="col-7"><?= html_escape($broadcast['finished_at'] ?? '-') ?></dd>
            <?php if ($broadcast['notes']): ?>
            <dt class="col-5">Catatan</dt>
            <dd class="col-7"><?= nl2br(html_escape($broadcast['notes'])) ?></dd>
            <?php endif; ?>
          </dl>
        </div>
        <?php if ($canStart || $canUpdate || ($canDelete && in_array($currentStatus, ['DRAFT','FAILED','CANCELLED'], true))): ?>
        <div class="card-footer d-flex gap-2">
          <?php if ($canUpdate): ?>
          <a href="<?= site_url('wa/broadcast/edit/' . $bcId) ?>" class="btn btn-outline-secondary btn-sm <?= $canStart ? '' : 'flex-fill' ?>">
            <i class="ri ri-edit-line me-1"></i>Edit
          </a>
          <?php endif; ?>
          <?php if ($canStart): ?>
          <button class="btn btn-success btn-sm flex-fill" id="btn-start">
            <i class="ri ri-send-plane-line me-1"></i><?= html_escape($startButtonLabel) ?>
          </button>
          <?php endif; ?>
          <?php if ($canDelete && in_array($currentStatus, ['DRAFT','FAILED','CANCELLED'], true)): ?>
          <form method="post" action="<?= site_url('wa/broadcast/delete/' . $bcId) ?>" class="d-inline" onsubmit="return confirm('Hapus broadcast ini?');">
            <input type="hidden" name="wa_broadcast_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">
              <i class="ri ri-delete-bin-line"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Pesan -->
      <div class="card border-0 shadow-sm">
        <div class="card-header"><h5 class="mb-0">Pesan</h5></div>
        <div class="card-body">
          <?php $msg = $broadcast['custom_message'] ?: ($broadcast['template_body'] ?? ''); ?>
          <?php if (!empty($broadcast['media_url'])): ?>
          <div class="mb-3">
            <div class="small text-muted mb-1">Gambar</div>
            <img src="<?= html_escape($broadcast['media_url']) ?>" alt="Gambar broadcast" class="img-fluid rounded border" style="max-height:220px;">
            <div class="small text-muted mt-1"><?= html_escape($broadcast['media_name'] ?? 'gambar') ?></div>
          </div>
          <?php endif; ?>
          <pre class="small mb-0" style="white-space:pre-wrap;"><?= html_escape($msg ?: '(menggunakan template: ' . ($broadcast['template_name'] ?? '-') . ')') ?></pre>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Daftar Penerima (<?= count($lines) ?>)</h5>
          <div id="progress-bar-wrapper" class="d-none w-50">
            <div class="progress" style="height:6px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progress-bar" style="width:0%"></div>
            </div>
            <div class="text-muted small mt-1" id="progress-text">Mengirim…</div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light sticky-top">
                <tr>
                  <th>#</th>
                  <th>Nama</th>
                  <th>Nomor</th>
                  <th class="text-center">Status</th>
                  <th>Waktu Kirim</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody id="line-tbody">
                <?php foreach ($lines as $i => $line): ?>
                <tr id="line-<?= (int)$line['id'] ?>">
                  <td class="text-muted"><?= $i + 1 ?></td>
                  <td><?= html_escape($line['display_name'] ?? '-') ?></td>
                  <td class="font-monospace small"><?= html_escape($line['phone_number']) ?></td>
                  <td class="text-center">
                    <span class="badge <?= $lineBadge[$line['status']] ?? 'bg-secondary' ?>">
                      <?= html_escape($line['status']) ?>
                    </span>
                  </td>
                  <td class="small text-muted"><?= $line['sent_at'] ? html_escape(date('H:i:s', strtotime($line['sent_at']))) : '-' ?></td>
                  <td class="small text-danger"><?= html_escape($line['error_msg'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada penerima.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('btn-start')?.addEventListener('click', function () {
  const retryMode = <?= json_encode($currentStatus === 'FAILED' && $pendingLineCount === 0 && $failedLineCount > 0) ?>;
  const resumeMode = <?= json_encode(($currentStatus === 'SENDING' || $currentStatus === 'FAILED') && $pendingLineCount > 0) ?>;
  const retryLineIds = <?= json_encode($failedLineIds) ?>;
  if (!confirm(retryMode ? 'Kirim ulang semua target yang gagal?' : (resumeMode ? 'Lanjutkan broadcast yang masih pending?' : 'Mulai kirim broadcast ke semua penerima sekarang?'))) return;
  const btn = this;
  const wrapper = document.getElementById('progress-bar-wrapper');
  const bar = document.getElementById('progress-bar');
  const text = document.getElementById('progress-text');
  btn.disabled = true;
  btn.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Mengirim…';
  wrapper?.classList.remove('d-none');

  let retryIndex = 0;
  const runBatch = () => {
    const isRetry = retryMode && retryLineIds.length > 0;
    const retryLast = isRetry && retryIndex === retryLineIds.length - 1;
    const query = isRetry
      ? `?retry=1&line_id=${encodeURIComponent(retryLineIds[retryIndex])}&retry_last=${retryLast ? '1' : '0'}`
      : '';
    fetch('<?= site_url('wa/api/broadcast-start/' . $bcId) ?>' + query, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-Wa-Broadcast-CSRF': <?= json_encode($mutationCsrf) ?>
      }
    })
      .then(async r => {
        const body = await r.text();
        try {
          return JSON.parse(body);
        } catch (e) {
          throw new Error('Respons server HTTP ' + r.status + ': ' + body.slice(0, 300));
        }
      })
      .then(data => {
        if (!data.ok) {
          let msg = 'Gagal: ' + (data.message || 'Error tidak diketahui');
          if (Array.isArray(data.errors) && data.errors.length) {
            msg += '\n\nContoh error:\n' + data.errors.map(row => {
              const target = row.display_name || row.phone_number || '-';
              return '- ' + target + ': ' + (row.error_msg || 'error');
            }).join('\n');
          }
          alert(msg);
          if (text) text.textContent = msg;
          btn.disabled = false;
          btn.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Coba Lagi';
          return;
        }

        const sent = Number(data.total_sent || 0);
        const failed = Number(data.total_failed || 0);
        const pending = Number(data.total_pending || 0);
        const total = sent + failed + pending;
        const pct = total > 0 ? Math.min(100, Math.round(((sent + failed) / total) * 100)) : 100;
        if (bar) bar.style.width = pct + '%';
        if (text) text.textContent = 'Terkirim ' + sent + ' · Gagal ' + failed + ' · Pending ' + pending + ' (' + pct + '%)';

        if (isRetry) retryIndex += 1;
        const shouldContinue = isRetry ? retryIndex < retryLineIds.length : data.has_more;
        if (shouldContinue) {
          const delaySeconds = Math.max(1, Number(data.delay_seconds || 1));
          if (text) text.textContent += ' · Menunggu ' + delaySeconds + ' detik untuk penerima berikutnya';
          setTimeout(runBatch, delaySeconds * 1000);
          return;
        }

        alert('Antrean pesan personal selesai. Terkirim: ' + sent + ', Gagal: ' + failed);
        location.reload();
      })
      .catch(e => {
        const message = 'Koneksi error: ' + e.message + '. Proses dapat dilanjutkan lagi dari target yang masih pending.';
        alert(message);
        if (text) text.textContent = message;
        btn.disabled = false;
        btn.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Coba Lagi';
      });
  };

  runBatch();
});
</script>
