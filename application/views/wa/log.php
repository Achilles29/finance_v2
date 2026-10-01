<?php
$logs          = (array)($logs ?? []);
$dateFrom      = (string)($date_from ?? date('Y-m-01'));
$dateTo        = (string)($date_to ?? date('Y-m-d'));
$filterStatus  = (string)($filter_status ?? '');
$filterSource  = (string)($filter_source ?? '');
$canRetryManual = (bool)($can_retry_manual ?? false);
$canRetryGroup = (bool)($can_retry_group ?? false);
$canBroadcastEdit = (bool)($can_broadcast_edit ?? false);
$retryCsrf = (string)($wa_log_retry_csrf ?? '');

$statusBadge = ['SENT' => 'bg-success', 'FAILED' => 'bg-danger', 'PENDING' => 'bg-secondary'];
$sourceBadge = ['BROADCAST' => 'bg-primary', 'MANUAL' => 'bg-info', 'GROUP' => 'bg-warning text-dark', 'SYSTEM' => 'bg-secondary', 'SCHEDULED' => 'bg-dark'];
?>

<div class="container-xxl py-3">
  <div class="mb-3">
    <h4 class="mb-1 fw-bold"><i class="ri ri-history-line me-1"></i>Log Pengiriman WA</h4>
    <p class="text-muted mb-0 small">Riwayat semua pesan yang dikirim via WhatsApp Bot.</p>
  </div>

  <!-- Filter -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label form-label-sm">Dari Tanggal</label>
          <input type="date" name="date_from" class="form-control form-control-sm" value="<?= html_escape($dateFrom) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Sampai Tanggal</label>
          <input type="date" name="date_to" class="form-control form-control-sm" value="<?= html_escape($dateTo) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="SENT"    <?= $filterStatus === 'SENT'    ? 'selected' : '' ?>>Terkirim</option>
            <option value="FAILED"  <?= $filterStatus === 'FAILED'  ? 'selected' : '' ?>>Gagal</option>
            <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Sumber</label>
          <select name="source" class="form-select form-select-sm">
            <option value="">Semua</option>
            <?php foreach (['BROADCAST','MANUAL','GROUP','SYSTEM','SCHEDULED'] as $src): ?>
            <option value="<?= $src ?>" <?= $filterSource === $src ? 'selected' : '' ?>><?= $src ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="<?= site_url('wa/log') ?>" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
        </div>
        <div class="col-auto ms-auto">
          <span class="text-muted small"><?= number_format(count($logs)) ?> baris ditampilkan (maks 200)</span>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Waktu</th>
              <th>Sumber</th>
              <th>Tujuan</th>
              <th>Pesan</th>
              <th class="text-center">Status</th>
              <th>Error</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <?php
            $logSource = strtoupper(trim((string)($log['source'] ?? '')));
            $isBroadcastLog = $logSource === 'BROADCAST';
            $isGroupLog = $logSource === 'GROUP' || trim((string)($log['group_jid'] ?? '')) !== '';
            ?>
            <tr>
              <td class="text-muted small text-nowrap">
                <?= html_escape(date('d/m/y H:i:s', strtotime($log['sent_at']))) ?>
              </td>
              <td>
                <span class="badge <?= $sourceBadge[$log['source']] ?? 'bg-secondary' ?>">
                  <?= html_escape($log['source']) ?>
                </span>
                <?php if ($log['broadcast_id']): ?>
                <a href="<?= site_url('wa/broadcast/detail/' . (int)$log['broadcast_id']) ?>" class="badge bg-label-primary text-decoration-none ms-1">
                  #<?= (int)$log['broadcast_id'] ?>
                </a>
                <?php endif; ?>
              </td>
              <td class="small">
                <div><?= html_escape($log['display_name'] ?: '-') ?></div>
                <code class="text-muted"><?= html_escape($log['phone_number'] ?: $log['group_jid'] ?: '') ?></code>
              </td>
              <td class="small text-muted" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= html_escape($log['message_preview'] ?? '') ?>">
                <?= html_escape(mb_substr($log['message_preview'] ?? '', 0, 80)) ?>
              </td>
              <td class="text-center">
                <span class="badge <?= $statusBadge[$log['status']] ?? 'bg-secondary' ?>">
                  <?= html_escape($log['status']) ?>
                </span>
              </td>
              <td class="small text-danger">
                <?php if ($log['error_detail']): ?>
                <span title="<?= html_escape($log['error_detail']) ?>"><?= html_escape(mb_substr($log['error_detail'], 0, 50)) ?>…</span>
                <?php endif; ?>
              </td>
              <td class="text-center text-nowrap">
                <?php if (($log['status'] ?? '') === 'FAILED'): ?>
                  <?php if ($isBroadcastLog && !empty($log['broadcast_id']) && $canBroadcastEdit): ?>
                    <a href="<?= site_url('wa/broadcast/detail/' . (int)$log['broadcast_id']) ?>" class="btn btn-outline-danger btn-sm">
                      Kirim ulang
                    </a>
                  <?php elseif ($isGroupLog && $canRetryGroup): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-retry-log="<?= (int)$log['id'] ?>">
                      Kirim ulang
                    </button>
                  <?php elseif (!$isBroadcastLog && !$isGroupLog && $canRetryManual): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-retry-log="<?= (int)$log['id'] ?>">
                      Kirim ulang
                    </button>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada log pada periode ini.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-retry-log]');
  if (!button) return;

  if (!confirm('Kirim ulang pesan ini sekarang?')) return;

  const originalText = button.textContent;
  button.disabled = true;
  button.textContent = 'Mengirim...';

  fetch('<?= site_url('wa/api/log-retry/') ?>' + button.getAttribute('data-retry-log'), {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-Wa-Log-Retry-CSRF': <?= json_encode($retryCsrf) ?>
    }
  })
    .then(response => response.json())
    .then(data => {
      if (data.open_broadcast && data.broadcast_id) {
        window.location.href = '<?= site_url('wa/broadcast/detail/') ?>' + data.broadcast_id;
        return;
      }
      alert(data.ok ? 'Pesan berhasil dikirim ulang.' : ('Gagal kirim ulang: ' + (data.message || 'Error tidak diketahui')));
      window.location.reload();
    })
    .catch(error => {
      alert('Koneksi error: ' + error);
      button.disabled = false;
      button.textContent = originalText;
    });
});
</script>
