<?php
$this->load->view('notifications/settings', [
    'notification_channel' => 'TELEGRAM', 'notification_can_edit' => !empty($can_edit),
    'notification_csrf_name' => 'tg_settings_csrf', 'notification_csrf' => (string)($settings_csrf ?? ''),
    'notification_action' => 'telegram/notification-settings',
]);
$botCheck = (array)($bot_check ?? []);
$webhookCheck = (array)($webhook_check ?? []);
$discoveredTargets = (array)($discovered_targets ?? []);
$activeTargets = max(0, (int)($active_target_count ?? 0));
$isEnabled = !empty($enabled);
$tokenReady = !empty($token_configured);
$secretReady = !empty($secret_configured);
$webhookUrlReady = !empty($webhook_url_configured);
$serverReady = $tokenReady && $secretReady && $webhookUrlReady;

$checkSucceeded = static function (array $check): bool {
    if (array_key_exists('ok', $check)) {
        return $check['ok'] === true || $check['ok'] === 1 || $check['ok'] === '1';
    }

    $status = strtoupper(trim((string)($check['status'] ?? '')));
    return in_array($status, ['OK', 'READY', 'SUCCESS', 'VERIFIED', 'CONNECTED'], true);
};

$botReady = $checkSucceeded($botCheck);
$botDetails = is_array($botCheck['bot'] ?? null) ? $botCheck['bot'] : [];
$botDisplayName = trim((string)($botDetails['first_name'] ?? ''));
$botUsername = trim((string)($botDetails['username'] ?? ''));
$webhookDetails = is_array($webhookCheck['webhook'] ?? null) ? $webhookCheck['webhook'] : [];
$webhookReady = $checkSucceeded($webhookCheck)
    && !empty($webhookDetails['configured'])
    && !empty($webhookDetails['url_matches_configured']);
$canEnable = $serverReady && $activeTargets > 0;
$canChangeSwitch = !empty($can_edit) && ($isEnabled || $canEnable);
$canCheckBotNow = !empty($can_test) && $serverReady;
$canDiscoverNow = !empty($can_test) && $serverReady;
$canInstallWebhookNow = !empty($can_edit) && $serverReady && $activeTargets > 0 && $isEnabled;
$canCheckWebhookNow = !empty($can_test) && $serverReady && $isEnabled;
$canSendTestNow = !empty($can_test) && $serverReady && $activeTargets > 0;
$discoveryExpiresLabel = '';
if (!empty($discovery_expires_at)) {
    $discoveryExpiresLabel = (new DateTimeImmutable('@' . (int)$discovery_expires_at))
        ->setTimezone(new DateTimeZone('Asia/Jakarta'))
        ->format('d-m-Y H:i') . ' WIB';
}

if (!$serverReady) {
    $nextTitle = 'Minta admin server melengkapi 3 konfigurasi';
    $nextText = 'Token bot, webhook secret, dan URL webhook kanonis harus berstatus Siap sebelum setup dilanjutkan.';
} elseif ($activeTargets < 1 && $discoveredTargets) {
    $nextTitle = 'Simpan target tepercaya';
    $nextText = 'Periksa nama dan tipe kandidat, lalu simpan hanya grup atau channel yang benar-benar Anda kenal.';
} elseif (!$botReady && $activeTargets < 1) {
    $nextTitle = 'Periksa bot';
    $nextText = 'Tambahkan bot ke grup atau channel internal tepercaya, kirim /menu, lalu klik Periksa Bot.';
} elseif ($activeTargets < 1 && !$discoveredTargets) {
    $nextTitle = 'Temukan grup atau channel';
    $nextText = 'Setelah /menu dikirim di Telegram, klik Temukan. Hasil penemuan hanya tersedia sementara.';
} elseif (!$isEnabled) {
    $nextTitle = 'Kirim test saat bot masih nonaktif';
    $nextText = 'Kirim pesan test ke target aktif. Setelah pesan diterima, nyalakan Bot aktif dan simpan.';
} elseif (!$webhookReady) {
    $nextTitle = 'Pasang dan periksa webhook';
    $nextText = 'Klik Pasang Webhook, lalu klik Periksa Status Webhook sampai status verifikasi hijau.';
} else {
    $nextTitle = 'Buat jadwal pengiriman';
    $nextText = 'Setup utama sudah siap. Buat jadwal, lalu pantau hasil pengiriman melalui log.';
}

$statusBadge = static function (bool $ready, string $readyText = 'Siap', string $pendingText = 'Belum siap'): string {
    $class = $ready ? 'bg-success' : 'bg-secondary';
    $text = $ready ? $readyText : $pendingText;
    return '<span class="badge ' . $class . '">' . html_escape($text) . '</span>';
};
?>
<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="ri-telegram-line me-1"></i>Asisten Setup Telegram</h4>
      <p class="text-muted small mb-0">Ikuti urutan di halaman ini untuk menghubungkan bot tanpa memakai API atau perintah teknis.</p>
    </div>
    <?php if (!empty($can_guide)): ?>
      <a class="btn btn-outline-primary" href="<?= html_escape(site_url('telegram/guide')) ?>"><i class="ri-question-line me-1"></i>Buka panduan</a>
    <?php endif; ?>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success" role="alert"><?= html_escape($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger" role="alert"><?= html_escape($flash) ?></div>
  <?php endif; ?>

  <div class="alert alert-warning border-warning shadow-sm" role="alert">
    <div class="d-flex gap-2">
      <i class="ri-shield-user-line fs-4" aria-hidden="true"></i>
      <div><strong>Gunakan hanya grup atau channel internal tepercaya.</strong><br>Anggota target yang diizinkan dapat menjalankan perintah laporan. Jangan simpan grup publik, grup percobaan milik pihak lain, atau kandidat yang tidak Anda kenal.</div>
    </div>
  </div>

  <div class="card border-primary shadow-sm mb-3">
    <div class="card-body p-4">
      <div class="text-primary fw-semibold text-uppercase small mb-1">Langkah berikutnya</div>
      <h5 class="fw-bold mb-2"><?= html_escape($nextTitle) ?></h5>
      <p class="mb-0"><?= html_escape($nextText) ?></p>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent pb-0">
      <ul class="nav nav-tabs flex-nowrap overflow-auto" id="telegram-settings-tabs" role="tablist" aria-label="Pilihan panduan setup Telegram" style="white-space: nowrap;">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tg-user-tab" data-bs-toggle="tab" data-bs-target="#tg-user-pane" type="button" role="tab" aria-controls="tg-user-pane" aria-selected="true">Saya pengguna aplikasi</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tg-server-tab" data-bs-toggle="tab" data-bs-target="#tg-server-pane" type="button" role="tab" aria-controls="tg-server-pane" aria-selected="false">Tugas admin server</button>
        </li>
      </ul>
    </div>

    <div class="card-body tab-content" id="telegram-settings-content">
      <div class="tab-pane fade show active" id="tg-user-pane" role="tabpanel" aria-labelledby="tg-user-tab" tabindex="0">
        <div class="mb-4">
          <h5 class="fw-bold">Saya sudah membuat bot, lalu apa?</h5>
          <ol class="mb-0">
            <li class="mb-1">Berikan token bot secara aman kepada admin server. Jangan kirim ke grup atau menempelkannya di halaman ini.</li>
            <li class="mb-1">Tunggu tiga status server di bawah menjadi hijau.</li>
            <li class="mb-1">Klik <strong>Periksa Bot</strong> dan pastikan identitas bot benar.</li>
            <li class="mb-1">Tambahkan bot ke grup/channel internal tepercaya, lalu kirim <code>/menu</code>.</li>
            <li class="mb-1">Klik <strong>Temukan</strong>.</li>
            <li class="mb-1">Pilih kandidat yang dikenal, lalu klik <strong>Simpan target ini</strong>.</li>
            <li class="mb-1">Saat switch masih mati, klik <strong>Kirim Test</strong>.</li>
            <li class="mb-1">Setelah test diterima, aktifkan switch <strong>Bot aktif</strong>.</li>
            <li class="mb-1">Klik <strong>Pasang Webhook</strong>, lalu <strong>Periksa Status Webhook</strong>.</li>
            <li>Buat jadwal dan pantau log pengiriman.</li>
          </ol>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-lg-7">
            <div class="card h-100 border">
              <div class="card-header bg-transparent fw-semibold">1. Status server</div>
              <div class="card-body">
                <p class="text-muted small">Lanjutkan setelah ketiganya hijau. Nilai credential tidak pernah ditampilkan.</p>
                <ul class="list-group list-group-flush">
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3"><span>Token bot</span><?= $statusBadge($tokenReady) ?></li>
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3"><span>Webhook secret</span><?= $statusBadge($secretReady) ?></li>
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3"><span>URL webhook kanonis</span><?= $statusBadge($webhookUrlReady) ?></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card h-100 border">
              <div class="card-header bg-transparent fw-semibold">2. Periksa bot</div>
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2"><span>Status aman</span><?= $statusBadge($botReady, 'Terverifikasi', 'Belum terverifikasi') ?></div>
                <?php if ($botReady && $botUsername !== ''): ?>
                  <div class="alert alert-success py-2 small" role="status">
                    Bot dikenali: <strong><?= html_escape($botDisplayName !== '' ? $botDisplayName : $botUsername) ?></strong>
                    (@<?= html_escape($botUsername) ?>)
                  </div>
                <?php endif; ?>
                <p class="text-muted small">Pemeriksaan hanya menampilkan hasil verifikasi, bukan token atau respons mentah Telegram.</p>
                <form class="mt-auto" method="post" action="<?= html_escape(site_url('telegram/setup_check_bot')) ?>">
                  <input type="hidden" name="tg_bot_check_csrf" value="<?= html_escape($bot_check_csrf ?? '') ?>">
                  <button class="btn btn-primary" type="submit" <?= $canCheckBotNow ? '' : 'disabled' ?>>Periksa Bot</button>
                </form>
                <?php if (empty($can_test)): ?><div class="form-text">Anda tidak memiliki izin pemeriksaan.</div><?php elseif (!$serverReady): ?><div class="form-text">Tunggu tiga status server menjadi hijau.</div><?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card border mb-3">
          <div class="card-header bg-transparent fw-semibold">3. Temukan dan simpan target tepercaya</div>
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
              <div>
                <p class="mb-1">Kirim <code>/menu</code> di grup atau channel, kemudian temukan kandidat dari aktivitas terbaru.</p>
                <?php if ($discoveryExpiresLabel !== ''): ?><div class="text-muted small">Kandidat tersedia sampai <?= html_escape($discoveryExpiresLabel) ?>.</div><?php endif; ?>
              </div>
              <form method="post" action="<?= html_escape(site_url('telegram/setup_discover_targets')) ?>">
                <input type="hidden" name="tg_target_discovery_csrf" value="<?= html_escape($target_discovery_csrf ?? '') ?>">
                <button class="btn btn-outline-primary" type="submit" <?= $canDiscoverNow ? '' : 'disabled' ?>><i class="ri-search-line me-1"></i>Temukan</button>
              </form>
            </div>

            <?php if ($discoveredTargets): ?>
              <div class="row g-2">
                <?php foreach ($discoveredTargets as $candidate): ?>
                  <?php
                  $candidate = (array)$candidate;
                  $candidateKey = (string)($candidate['candidate_key'] ?? '');
                  ?>
                  <div class="col-md-6">
                    <div class="border rounded p-3 h-100 d-flex flex-column">
                      <div class="fw-semibold"><?= html_escape($candidate['title'] ?? 'Tanpa nama') ?></div>
                      <div class="small text-muted mb-1"><?= html_escape($candidate['target_type'] ?? '-') ?></div>
                      <code class="small mb-3"><?= html_escape($candidate['chat_id'] ?? '-') ?></code>
                      <form class="mt-auto" method="post" action="<?= html_escape(site_url('telegram/setup_save_discovered_target')) ?>">
                        <input type="hidden" name="tg_discovered_target_save_csrf" value="<?= html_escape($discovered_target_save_csrf ?? '') ?>">
                        <input type="hidden" name="candidate_key" value="<?= html_escape($candidateKey) ?>">
                        <button class="btn btn-outline-success btn-sm" type="submit" <?= !empty($can_target_create) && $candidateKey !== '' ? '' : 'disabled' ?>>Simpan target ini</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-muted small">Belum ada kandidat. Hanya grup, supergroup, atau channel yang baru berinteraksi dengan bot yang akan ditawarkan.</div>
            <?php endif; ?>
            <?php if (empty($can_target_create)): ?><div class="form-text mt-2">Anda tidak memiliki izin membuat target.</div><?php endif; ?>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-lg-7">
            <div class="card h-100 border">
              <div class="card-header bg-transparent fw-semibold">4. Kirim test saat switch mati</div>
              <div class="card-body">
                <?php if (!empty($can_test)): ?>
                  <form method="post" action="<?= html_escape(site_url('telegram/test_send')) ?>">
                    <input type="hidden" name="tg_test_send_csrf" value="<?= html_escape($test_csrf ?? '') ?>">
                    <input type="hidden" name="request_id" value="<?= html_escape($test_request_id ?? '') ?>">
                    <div class="mb-2">
                      <label class="form-label" for="tg-test-target">Target aktif</label>
                      <select class="form-select" id="tg-test-target" name="target_id" required <?= $canSendTestNow ? '' : 'disabled' ?>>
                        <option value="">Pilih target</option>
                        <?php foreach ((array)($targets ?? []) as $target): ?>
                          <?php $target = (array)$target; ?>
                          <option value="<?= (int)($target['id'] ?? 0) ?>"><?= html_escape($target['title'] ?? 'Tanpa nama') ?> (<?= html_escape($target['chat_id'] ?? '-') ?>)</option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="tg-test-message">Pesan test</label>
                      <textarea class="form-control" id="tg-test-message" name="message" rows="3" maxlength="1000" required <?= $canSendTestNow ? '' : 'disabled' ?>>Test Telegram dari Finance App</textarea>
                    </div>
                    <button class="btn btn-success" type="submit" <?= $canSendTestNow ? '' : 'disabled' ?>><i class="ri-send-plane-line me-1"></i>Kirim Test</button>
                  </form>
                  <?php if (!$canSendTestNow): ?><div class="form-text">Pastikan konfigurasi server siap dan simpan minimal satu target aktif terlebih dahulu. Switch tidak perlu dinyalakan untuk melakukan test.</div><?php endif; ?>
                <?php else: ?>
                  <p class="text-muted mb-0">Anda tidak memiliki izin mengirim test.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card h-100 border">
              <div class="card-header bg-transparent fw-semibold">5. Aktifkan bot</div>
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3"><span>Status saat ini</span><?= $statusBadge($isEnabled, 'Aktif', 'Nonaktif') ?></div>
                <?php if (!empty($can_edit)): ?>
                  <form method="post" action="<?= html_escape(site_url('telegram/settings')) ?>">
                    <input type="hidden" name="tg_settings_csrf" value="<?= html_escape($settings_csrf ?? '') ?>">
                    <input type="hidden" name="enabled" value="0">
                    <div class="form-check form-switch mb-3">
                      <input class="form-check-input" id="tg-enabled" type="checkbox" name="enabled" value="1" <?= $isEnabled ? 'checked' : '' ?> <?= $canChangeSwitch ? '' : 'disabled' ?>>
                      <label class="form-check-label" for="tg-enabled">Bot aktif</label>
                    </div>
                    <button class="btn btn-primary" type="submit" <?= $canChangeSwitch ? '' : 'disabled' ?>>Simpan switch</button>
                  </form>
                  <?php if (!$isEnabled && !$canEnable): ?><div class="form-text">Tiga status server dan minimal satu target aktif harus siap.</div><?php endif; ?>
                <?php else: ?>
                  <p class="text-muted mb-0">Anda tidak memiliki izin mengubah switch.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card border mb-3">
          <div class="card-header bg-transparent fw-semibold">6. Pasang dan periksa webhook</div>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <div class="fw-semibold">Status webhook</div>
                <div class="text-muted small">Hanya hasil verifikasi aman yang ditampilkan. URL Telegram lain, error mentah, update, pesan, dan data pengirim tidak ditampilkan.</div>
              </div>
              <?= $statusBadge($webhookReady, 'Terverifikasi', 'Belum terverifikasi') ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <form method="post" action="<?= html_escape(site_url('telegram/setup_install_webhook')) ?>">
                <input type="hidden" name="tg_webhook_install_csrf" value="<?= html_escape($webhook_install_csrf ?? '') ?>">
                <button class="btn btn-primary" type="submit" <?= $canInstallWebhookNow ? '' : 'disabled' ?>>Pasang Webhook</button>
              </form>
              <form method="post" action="<?= html_escape(site_url('telegram/setup_check_webhook')) ?>">
                <input type="hidden" name="tg_webhook_check_csrf" value="<?= html_escape($webhook_check_csrf ?? '') ?>">
                <button class="btn btn-outline-primary" type="submit" <?= $canCheckWebhookNow ? '' : 'disabled' ?>>Periksa Status Webhook</button>
              </form>
            </div>
            <?php if (!$isEnabled): ?><div class="form-text">Aktifkan bot setelah test berhasil untuk melanjutkan.</div><?php elseif (empty($can_edit) && empty($can_test)): ?><div class="form-text">Anda tidak memiliki izin memasang atau memeriksa webhook.</div><?php endif; ?>
          </div>
        </div>

        <div class="card border">
          <div class="card-header bg-transparent fw-semibold">7. Jadwal dan pemantauan</div>
          <div class="card-body d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="<?= html_escape(site_url('telegram/delivery')) ?>">Buat jadwal</a>
            <a class="btn btn-outline-secondary" href="<?= html_escape(site_url('telegram/log')) ?>">Buka log</a>
            <a class="btn btn-outline-secondary" href="<?= html_escape(site_url('telegram')) ?>">Dashboard target</a>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tg-server-pane" role="tabpanel" aria-labelledby="tg-server-tab" tabindex="0">
        <h5 class="fw-bold">Tugas admin server</h5>
        <p>Hanya konfigurasi berikut yang dikerjakan di server. Token dan webhook secret adalah password, sehingga nilainya tidak boleh dimasukkan, disalin, atau ditampilkan melalui UI aplikasi.</p>
        <ul>
          <li><code>FINANCE_TELEGRAM_BOT_TOKEN</code> — token yang diserahkan pemilik bot melalui saluran aman.</li>
          <li><code>FINANCE_TELEGRAM_WEBHOOK_SECRET</code> — credential terpisah untuk memverifikasi panggilan webhook.</li>
          <li><code>FINANCE_TELEGRAM_WEBHOOK_URL</code> — URL HTTPS kanonis aplikasi yang berakhir dengan <code>/telegram_webhook</code>.</li>
        </ul>
        <?php if (!empty($webhook_url)): ?>
          <p class="mb-1">URL kanonis yang diharapkan aplikasi:</p>
          <div class="bg-light border rounded p-3 mb-3"><code class="text-break"><?= html_escape($webhook_url) ?></code></div>
        <?php endif; ?>
        <p>Pastikan ketiga environment tersedia pada PHP-FPM dan CLI/cron, lalu reload proses terkait setelah konfigurasi berubah. Hubungan ke API Telegram selanjutnya dilakukan melalui tab pengguna aplikasi.</p>
        <p class="mb-1">Placeholder cron scheduler:</p>
        <pre class="bg-dark text-white rounded p-3 small mb-0"><code>* * * * * php &lt;APP_ROOT&gt;/index.php telegram run_due</code></pre>
      </div>
    </div>
  </div>
</div>
