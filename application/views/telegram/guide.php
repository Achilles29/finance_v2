<?php $webhookUrl = trim((string)($webhook_url ?? '')); ?>
<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="ri-question-line me-1"></i>Panduan Setup Telegram</h4>
      <p class="text-muted small mb-0">Panduan sederhana untuk menghubungkan bot ke grup atau channel internal.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= html_escape(site_url('telegram/settings')) ?>"><i class="ri-settings-3-line me-1"></i>Buka Asisten Setup</a>
  </div>

  <div class="alert alert-warning" role="alert"><strong>Gunakan hanya grup atau channel internal tepercaya.</strong> Anggota target aktif dapat menjalankan perintah laporan. Chat pribadi dan grup publik tidak didukung.</div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent pb-0">
      <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto" id="telegram-guide-tabs" role="tablist" aria-label="Panduan Telegram">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="tg-owner-tab" data-bs-toggle="pill" data-bs-target="#tg-owner-pane" type="button" role="tab" aria-controls="tg-owner-pane" aria-selected="true">Pemilik/Operator</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="tg-admin-tab" data-bs-toggle="pill" data-bs-target="#tg-admin-pane" type="button" role="tab" aria-controls="tg-admin-pane" aria-selected="false">Admin Server</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="tg-help-tab" data-bs-toggle="pill" data-bs-target="#tg-help-pane" type="button" role="tab" aria-controls="tg-help-pane" aria-selected="false">Jika Bermasalah</button></li>
      </ul>
    </div>

    <div class="card-body tab-content" id="telegram-guide-content">
      <div class="tab-pane fade show active" id="tg-owner-pane" role="tabpanel" aria-labelledby="tg-owner-tab" tabindex="0">
        <h5>A. Cara membuat bot</h5>
        <ol>
          <li class="mb-2">Buka Telegram, cari <strong>@BotFather</strong>, dan pastikan akun yang dibuka adalah akun resmi Telegram.</li>
          <li class="mb-2">Tekan <strong>Start</strong>, lalu kirim <code>/newbot</code>.</li>
          <li class="mb-2">Masukkan nama bot yang mudah dikenali, misalnya <em>Finance Toko Saya</em>.</li>
          <li class="mb-2">Masukkan username unik yang diakhiri <code>bot</code>, misalnya <code>finance_toko_saya_bot</code>.</li>
          <li>BotFather akan memberikan token. Token adalah password bot: jangan tempel ke aplikasi, grup, tiket publik, atau tangkapan layar. Serahkan hanya kepada admin server melalui jalur rahasia. Jika token pernah terlanjur dibagikan, cabut token lama melalui BotFather dan gunakan token baru.</li>
        </ol>
        <h5 class="mt-4">B. Saya sudah membuat bot, lalu apa?</h5>
        <ol>
          <li class="mb-2">Tunggu admin server menyiapkan tiga konfigurasi dan cron. Buka <a href="<?= html_escape(site_url('telegram/settings')) ?>">Asisten Setup</a>; lanjutkan setelah ketiga status berubah menjadi <strong>Siap</strong>.</li>
          <li class="mb-2">Klik <strong>Periksa Bot</strong>. Pastikan nama dan username yang ditampilkan adalah bot milik Anda.</li>
          <li class="mb-2">Tambahkan bot ke grup atau channel internal tepercaya. Untuk channel, beri bot izin mengirim pesan. Lalu kirim <code>/menu</code> di sana.</li>
          <li class="mb-2">Kembali ke Asisten Setup, klik <strong>Temukan</strong>, periksa nama tujuan, lalu klik <strong>Simpan target ini</strong>. Anda tidak perlu mencari atau mengetik Chat ID.</li>
          <li class="mb-2">Klik <strong>Kirim Test</strong>. Test dapat dilakukan ketika switch Bot aktif masih mati.</li>
          <li class="mb-2">Setelah pesan diterima, nyalakan switch <strong>Bot aktif</strong>, lalu klik <strong>Pasang Webhook</strong> dan <strong>Periksa Status Webhook</strong>.</li>
          <li>Buka <a href="<?= html_escape(site_url('telegram/delivery')) ?>">Jadwal &amp; Delivery</a> untuk membuat jadwal. Pantau hasil pada <a href="<?= html_escape(site_url('telegram/log')) ?>">Log Telegram</a>.</li>
        </ol>
        <div class="alert alert-info mb-0">Urutan penting: temukan dan simpan target sebelum memasang webhook.</div>
        <p class="mt-3 mb-0">Rujukan resmi: <a href="https://core.telegram.org/bots/tutorial" target="_blank" rel="noopener noreferrer">Tutorial bot Telegram</a> dan <a href="https://core.telegram.org/bots/features#privacy-mode" target="_blank" rel="noopener noreferrer">Privacy Mode Telegram</a>.</p>
      </div>

      <div class="tab-pane fade" id="tg-admin-pane" role="tabpanel" aria-labelledby="tg-admin-tab" tabindex="0">
        <h5>Yang dapat dan tidak dapat diisi lewat aplikasi</h5>
        <div class="row g-3 mb-3">
          <div class="col-lg-6"><div class="border rounded p-3 h-100"><strong class="text-success">Dikerjakan lewat UI</strong><p class="small mb-0 mt-2">Periksa bot, temukan grup, simpan target, test kirim, hidupkan bot, pasang webhook, dan buat jadwal.</p></div></div>
          <div class="col-lg-6"><div class="border rounded p-3 h-100"><strong class="text-danger">Dikerjakan satu kali di server</strong><p class="small mb-0 mt-2">Simpan token dan secret di file privat, izinkan PHP-FPM membacanya, lalu aktifkan scheduler. Credential sengaja tidak disediakan sebagai isian UI agar tidak tersimpan di database atau terlihat operator.</p></div></div>
        </div>

        <h5>Contoh langsung untuk server aaPanel ini</h5>
        <p>Masuk ke <strong>aaPanel → Terminal</strong> sebagai <code>root</code>. Token dipasang di file <code>/var/lib/finance-telegram/runtime.env</code>, <strong>bukan</strong> di <code>database.php</code>, <code>config.php</code>, atau halaman web.</p>
        <ol>
          <li class="mb-3">Buat folder dan buka file credential:
            <pre class="bg-dark text-white rounded p-3 small mt-2 mb-2"><code>install -d -m 700 /var/lib/finance-telegram
nano /var/lib/finance-telegram/runtime.env</code></pre>
          </li>
          <li class="mb-3">Isi tepat tiga baris berikut. Ganti bagian contoh, lalu tekan <strong>Ctrl+O</strong>, Enter, dan <strong>Ctrl+X</strong>:
            <pre class="bg-dark text-white rounded p-3 small mt-2 mb-2"><code>FINANCE_TELEGRAM_BOT_TOKEN=PASTE_TOKEN_DARI_BOTFATHER
FINANCE_TELEGRAM_WEBHOOK_SECRET=PASTE_SECRET_ACAK_64_KARAKTER
FINANCE_TELEGRAM_WEBHOOK_URL=https://domain-anda.com/telegram_webhook</code></pre>
            Buat secret acak dengan <code>openssl rand -hex 32</code>. Secret ini bukan token bot dan tidak perlu diberikan kepada operator.
          </li>
          <li class="mb-3">Kunci file agar hanya root yang dapat membacanya:
            <pre class="bg-dark text-white rounded p-3 small mt-2 mb-2"><code>chown root:root /var/lib/finance-telegram/runtime.env
chmod 600 /var/lib/finance-telegram/runtime.env</code></pre>
          </li>
          <li class="mb-3">Pada <code>/www/server/php/81/etc/php-fpm.conf</code>, di bawah <code>user = www</code> dan <code>group = www</code>, pastikan ada:
            <pre class="bg-dark text-white rounded p-3 small mt-2 mb-2"><code>env[FINANCE_TELEGRAM_BOT_TOKEN] = $FINANCE_TELEGRAM_BOT_TOKEN
env[FINANCE_TELEGRAM_WEBHOOK_SECRET] = $FINANCE_TELEGRAM_WEBHOOK_SECRET
env[FINANCE_TELEGRAM_WEBHOOK_URL] = $FINANCE_TELEGRAM_WEBHOOK_URL</code></pre>
            File startup <code>/etc/init.d/php-fpm-81</code> juga harus membaca <code>/var/lib/finance-telegram/runtime.env</code> sebelum PHP-FPM dijalankan. Pada instalasi Finance ini bagian tersebut sudah dipasang otomatis.
          </li>
          <li class="mb-3">Restart PHP 8.1 melalui <strong>aaPanel → App Store → PHP 8.1 → Service → Restart</strong>, atau jalankan:
            <pre class="bg-dark text-white rounded p-3 small mt-2 mb-2"><code>/www/server/php/81/sbin/php-fpm -t --fpm-config /www/server/php/81/etc/php-fpm.conf
/etc/init.d/php-fpm-81 restart</code></pre>
          </li>
          <li>Pastikan file <code>/etc/cron.d/finance-telegram</code> berisi scheduler berikut:
            <pre class="bg-dark text-white rounded p-3 small mt-2 mb-2"><code>* * * * * root /usr/local/sbin/finance-telegram-run-due &gt;&gt; /var/log/finance-telegram-worker.log 2&gt;&amp;1</code></pre>
          </li>
        </ol>
        <?php if ($webhookUrl !== ''): ?><div class="alert alert-light border"><div class="small text-muted">URL webhook yang diharapkan aplikasi</div><code class="text-break"><?= html_escape($webhookUrl) ?></code></div><?php endif; ?>
        <div class="alert alert-success">Jika tiga status pada Asisten Setup sudah <strong>Siap</strong>, pekerjaan admin server selesai. Operator melanjutkan seluruh langkah lain lewat UI.</div>
        <div class="alert alert-warning mb-0">Jangan tampilkan token atau secret dalam source code, database, URL, log, chat, atau argumen proses. Jika token pernah dikirim lewat chat, lakukan <em>revoke</em> di BotFather setelah pengujian lalu ganti hanya baris token pada file privat dan restart PHP-FPM.</div>
        <p class="mt-3 mb-0">Rujukan resmi: <a href="https://core.telegram.org/bots/api#setwebhook" target="_blank" rel="noopener noreferrer">Telegram Bot API — Webhook</a>.</p>
      </div>

      <div class="tab-pane fade" id="tg-help-pane" role="tabpanel" aria-labelledby="tg-help-tab" tabindex="0">
        <h5>Masalah umum</h5>
        <div class="list-group">
          <div class="list-group-item"><strong>Status server belum siap</strong><p class="mb-0 mt-1">Minta admin memeriksa tiga environment pada PHP-FPM dan CLI/cron, lalu reload layanan. Jangan meminta nilai credential sebagai bukti.</p></div>
          <div class="list-group-item"><strong>Periksa Bot gagal atau bot salah</strong><p class="mb-0 mt-1">Pastikan admin memasang token bot yang benar dan server dapat terhubung keluar melalui HTTPS. Jangan lanjutkan jika identitas bot salah.</p></div>
          <div class="list-group-item"><strong>Target tidak ditemukan</strong><p class="mb-0 mt-1">Pastikan bot sudah masuk ke grup/channel yang benar, kirim <code>/menu</code>, lalu klik Temukan lagi. Untuk channel, periksa izin kirim bot.</p></div>
          <div class="list-group-item"><strong>Test Kirim belum masuk</strong><p class="mb-0 mt-1">Switch boleh tetap mati. Pastikan target aktif, bot masih menjadi anggota, dan izin kirim tersedia. Periksa juga Log Telegram.</p></div>
          <div class="list-group-item"><strong>Webhook belum sesuai</strong><p class="mb-0 mt-1">Pastikan URL dan pengaman webhook berstatus siap. Klik Pasang Webhook lalu Periksa Status Webhook. Jika tetap gagal, minta admin memeriksa HTTPS publik.</p></div>
          <div class="list-group-item"><strong>Perintah atau jadwal tidak berjalan</strong><p class="mb-0 mt-1">Pastikan switch, target, dan jadwal aktif serta webhook terverifikasi. Admin perlu memastikan cron berjalan setiap menit.</p></div>
          <div class="list-group-item"><strong>Log berstatus UNKNOWN</strong><p class="mb-0 mt-1">Jangan langsung mengirim ulang karena pesan mungkin sudah diterima. Periksa Telegram, lalu selesaikan manual dengan alasan melalui Log Telegram.</p></div>
        </div>
      </div>
    </div>
  </div>
</div>
