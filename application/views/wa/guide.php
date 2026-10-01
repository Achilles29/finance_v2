<?php
$session  = (array)($session ?? []);
$waStatus = strtoupper($session['status'] ?? 'UNKNOWN');
$waPhone  = $session['phone_number'] ?? '';
?>

<div class="container-xxl py-3">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-book-open-line me-1"></i>Panduan WhatsApp Bot</h4>
      <p class="text-muted mb-0 small">Instalasi, konfigurasi, cara menautkan WA, dan panduan penggunaan.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= site_url('wa/settings') ?>" class="btn btn-outline-primary btn-sm">
        <i class="ri ri-settings-3-line me-1"></i>Pengaturan
      </a>
      <a href="<?= site_url('wa/dashboard') ?>" class="btn btn-success btn-sm">
        <i class="ri ri-dashboard-line me-1"></i>Dashboard
      </a>
    </div>
  </div>

  <!-- Status -->
  <?php
  $sBadge = match($waStatus) { 'CONNECTED' => 'bg-success', 'WAITING_QR' => 'bg-warning', 'DISCONNECTED', 'LOGGED_OUT' => 'bg-danger', default => 'bg-secondary' };
  $sLabel = match($waStatus) { 'CONNECTED' => 'Terhubung', 'WAITING_QR' => 'Menunggu QR', 'DISCONNECTED' => 'Terputus', 'LOGGED_OUT' => 'Sesi Keluar', default => 'Tidak Diketahui' };
  ?>
  <div class="alert alert-light border d-flex align-items-center gap-3 mb-4">
    <span class="badge <?= $sBadge ?> fs-6"><?= $sLabel ?></span>
    <div>
      Status bot: <strong><?= $sLabel ?></strong>
      <?= $waPhone ? ' — Nomor: <strong>' . html_escape($waPhone) . '</strong>' : '' ?>
    </div>
    <?php if ($waStatus !== 'CONNECTED'): ?>
    <a href="#section-connect" class="btn btn-warning btn-sm ms-auto">
      <i class="ri ri-qr-code-line me-1"></i>Scan QR Sekarang →
    </a>
    <?php endif; ?>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- STRUKTUR FOLDER -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ri ri-folder-line me-2"></i>Struktur Folder</h5>
    </div>
    <div class="card-body">
      <p class="small mb-3">
        Semua kode ada dalam satu root: <code>finance/</code>.
        Engine WhatsApp (Node.js) ada di subfolder <code>finance/wa-engine/</code> — dijalankan terpisah dari Apache, tapi tetap satu direktori proyek.
      </p>
      <pre class="bg-dark text-white rounded p-3 small">finance/                        ← root proyek
├── application/                ← Finance App (PHP / CodeIgniter)
│   ├── controllers/
│   │   └── Whatsapp.php        ← controller modul WA
│   └── views/wa/               ← tampilan modul WA
│       ├── dashboard.php
│       ├── broadcast.php
│       ├── settings.php
│       └── guide.php  ← halaman ini
│
├── wa-engine/                  ← Engine WA (Node.js) — npm install di sini
│   ├── index.js                ← file utama, jalankan: node index.js
│   ├── package.json
│   ├── .env.example            ← salin ke .env dan isi password DB
│   └── auth_info/              ← sesi WA (dibuat otomatis saat pertama login)
│
├── sql/
│   └── 2026-07-13a_wa_module.sql  ← jalankan di MySQL sebelum pakai modul
└── ...</pre>

      <div class="alert alert-info small mb-0 mt-3">
        <i class="ri ri-information-line me-1"></i>
        <strong>Finance App (PHP)</strong> tidak perlu <code>npm install</code> — berjalan di Apache/XAMPP seperti biasa.<br>
        <strong>wa-engine (Node.js)</strong> dijalankan sekali via terminal, lalu tetap hidup di background (PM2/systemd).
        Finance App berkomunikasi dengan wa-engine lewat HTTP internal di port <code>3070</code>.
      </div>
    </div>
  </div>

  <!-- Navigasi -->
  <div class="row g-2 mb-4">
    <?php $navItems = [
      ['#section-prereq',         'ri-list-check',        'Prasyarat',         'bg-label-dark'],
      ['#section-install-windows','ri-windows-line',      'Windows',           'bg-label-primary'],
      ['#section-install-ubuntu', 'ri-terminal-box-line', 'Ubuntu',            'bg-label-success'],
      ['#section-connect',        'ri-qr-code-line',      'Scan QR',           'bg-label-warning'],
      ['#section-broadcast',      'ri-broadcast-line',    'Broadcast',         'bg-label-danger'],
      ['#section-group',          'ri-group-2-line',      'Grup WA',           'bg-label-secondary'],
    ]; ?>
    <?php foreach ($navItems as [$href, $icon, $label, $bg]): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <a href="<?= $href ?>" class="card border-0 shadow-sm text-decoration-none h-100">
        <div class="card-body text-center py-3">
          <div class="avatar avatar-md mx-auto mb-1">
            <span class="avatar-initial rounded-circle <?= $bg ?>"><i class="ri <?= $icon ?>"></i></span>
          </div>
          <div class="small fw-semibold"><?= $label ?></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- PRASYARAT -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-prereq">
    <div class="card-header">
      <h5 class="mb-0"><i class="ri ri-list-check me-2"></i>Prasyarat</h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="fw-bold">Yang harus sudah tersedia</h6>
          <ul class="small">
            <li>Finance App sudah berjalan di Apache/XAMPP</li>
            <li>MySQL sudah aktif dan database <code>db_finance</code> ada</li>
            <li>SQL migration sudah dijalankan:
              <code>finance/sql/2026-07-13a_wa_module.sql</code></li>
            <li>Node.js terinstall (lihat bagian instalasi sesuai OS)</li>
          </ul>
        </div>
        <div class="col-md-6">
          <h6 class="fw-bold">Langkah pertama kali (urutan)</h6>
          <ol class="small">
            <li>Jalankan SQL migration di MySQL</li>
            <li>Install Node.js (Windows atau Ubuntu)</li>
            <li>Masuk folder <code>finance/wa-engine/</code> → <code>npm install</code></li>
            <li>Jalankan <code>node index.js</code> → scan QR</li>
            <li>Buka <a href="<?= site_url('wa/settings') ?>">Pengaturan WA</a> → Ping Bot → harus ✓</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- INSTALASI WINDOWS -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-install-windows">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="ri ri-windows-line me-2"></i>Instalasi di Windows</h5>
    </div>
    <div class="card-body">

      <h6 class="fw-bold">1. Install Node.js</h6>
      <ol class="small mb-3">
        <li>Download <strong>Node.js LTS</strong> dari <strong>nodejs.org</strong> (Windows Installer .msi)</li>
        <li>Jalankan installer, pastikan centang <em>"Add to PATH"</em></li>
        <li>Verifikasi di Command Prompt / PowerShell:</li>
      </ol>
      <pre class="bg-dark text-white rounded p-3 small">node --version
npm --version</pre>

      <h6 class="fw-bold mt-4">2. Konfigurasi database</h6>
      <p class="small">Buat file <code>.env</code> dari contoh yang sudah ada, lalu isi password MySQL:</p>
      <pre class="bg-dark text-white rounded p-3 small"># Di Command Prompt / PowerShell
cd C:\xampp\htdocs\finance\wa-engine
copy .env.example .env
notepad .env</pre>
      <p class="small">Isi file <code>.env</code>:</p>
      <pre class="bg-dark text-white rounded p-3 small">WA_PORT=3070

DB_HOST=localhost
DB_USER=root
DB_PASS=password_mysql_anda    ← ganti ini
DB_NAME=db_finance</pre>

      <h6 class="fw-bold mt-4">3. Install dependensi & jalankan</h6>
      <pre class="bg-dark text-white rounded p-3 small"># Masuk ke folder wa-engine (BUKAN root finance, BUKAN application/)
cd C:\xampp\htdocs\finance\wa-engine

# Install paket Node.js — hanya sekali
npm install

# Jalankan wa-engine
node index.js</pre>
      <p class="small text-muted">Terminal akan menampilkan QR code. Scan dengan WA, atau gunakan panel Scan QR di halaman ini.</p>

      <h6 class="fw-bold mt-4">4. (Opsional) Jalankan otomatis dengan PM2</h6>
      <p class="small">Agar wa-engine tetap hidup meski terminal ditutup:</p>
      <pre class="bg-dark text-white rounded p-3 small">npm install -g pm2

cd C:\xampp\htdocs\finance\wa-engine
pm2 start index.js --name wa-engine
pm2 save
pm2 startup</pre>

      <div class="alert alert-success small mt-3 mb-0">
        <i class="ri ri-checkbox-circle-line me-1"></i>
        <strong>Checklist:</strong> Finance App (PHP) berjalan di Apache XAMPP seperti biasa — tidak ada yang berubah.
        Hanya <code>finance/wa-engine/</code> yang perlu <code>npm install</code> dan <code>node index.js</code>.
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- INSTALASI UBUNTU -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-install-ubuntu">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="ri ri-terminal-box-line me-2"></i>Instalasi di Ubuntu (Server Produksi)</h5>
    </div>
    <div class="card-body">

      <div class="alert alert-light border small mb-3">
        Finance App sudah berjalan di Apache/XAMPP Ubuntu. Yang perlu ditambahkan hanya Node.js
        untuk menjalankan <code>wa-engine</code> dari dalam folder <code>finance/</code>.
      </div>

      <h6 class="fw-bold">1. Cek lokasi folder finance di server</h6>
      <pre class="bg-dark text-white rounded p-3 small"># XAMPP di Ubuntu
ls /opt/lampp/htdocs/finance/wa-engine/

# Apache standar
ls /var/www/html/finance/wa-engine/</pre>

      <h6 class="fw-bold mt-4">2. Install Node.js via NVM</h6>
      <pre class="bg-dark text-white rounded p-3 small">curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
source ~/.bashrc

nvm install --lts
nvm use --lts

node --version</pre>

      <h6 class="fw-bold mt-4">3. Konfigurasi database</h6>
      <pre class="bg-dark text-white rounded p-3 small"># Sesuaikan path ke lokasi finance di server
cd /opt/lampp/htdocs/finance/wa-engine

cp .env.example .env
nano .env</pre>
      <p class="small">Isi file <code>.env</code> dengan password MySQL produksi Anda.</p>

      <h6 class="fw-bold mt-4">4. Install dependensi</h6>
      <pre class="bg-dark text-white rounded p-3 small">cd /opt/lampp/htdocs/finance/wa-engine   # sesuaikan path
npm install</pre>

      <h6 class="fw-bold mt-4">5. Jalankan pertama kali (untuk scan QR)</h6>
      <pre class="bg-dark text-white rounded p-3 small">node index.js</pre>
      <p class="small text-muted">Atau gunakan panel <a href="#section-connect">Scan QR di bawah</a> — tidak perlu akses terminal.</p>

      <h6 class="fw-bold mt-4">6. Jalankan permanen dengan PM2</h6>
      <pre class="bg-dark text-white rounded p-3 small">npm install -g pm2

cd /opt/lampp/htdocs/finance/wa-engine
pm2 start index.js --name wa-engine
pm2 save
pm2 startup systemd
# Jalankan perintah yang ditampilkan PM2

# Monitoring
pm2 status
pm2 logs wa-engine</pre>

      <h6 class="fw-bold mt-4">7. Izin folder auth_info</h6>
      <pre class="bg-dark text-white rounded p-3 small">mkdir -p /opt/lampp/htdocs/finance/wa-engine/auth_info
chown -R $(whoami):$(whoami) /opt/lampp/htdocs/finance/wa-engine/auth_info</pre>

      <div class="alert alert-info small mt-3 mb-0">
        <i class="ri ri-information-line me-1"></i>
        File <code>auth_info/</code> menyimpan sesi WA — jangan hapus kecuali ingin login ulang.
        File ini sudah ada di <code>.gitignore</code> sehingga tidak ikut ter-commit.
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- SCAN QR -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-connect">
    <div class="card-header bg-warning">
      <h5 class="mb-0"><i class="ri ri-qr-code-line me-2"></i>Menautkan WhatsApp (Scan QR)</h5>
    </div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-5">
          <h6 class="fw-bold">Cara Menautkan</h6>
          <ol class="small">
            <li class="mb-2"><strong>wa-engine harus sudah berjalan</strong>. Verifikasi: klik <a href="<?= site_url('wa/settings') ?>">Pengaturan</a> → Ping Bot.</li>
            <li class="mb-2">Klik <strong>"Muat QR Code"</strong> di panel kanan.</li>
            <li class="mb-2">Buka <strong>WhatsApp</strong> di HP → <strong>Perangkat Tertaut</strong> → <strong>Tautkan Perangkat</strong>.</li>
            <li class="mb-2">Arahkan kamera ke QR Code yang tampil.</li>
            <li class="mb-2">Status otomatis berubah ke <span class="badge bg-success">Terhubung</span>.</li>
          </ol>

          <div class="alert alert-info small mb-0">
            <strong>Catatan:</strong>
            <ul class="mb-0 ps-3 mt-1">
              <li>QR berlaku ±60 detik — klik lagi jika kadaluarsa.</li>
              <li>Sesi disimpan di <code>wa-engine/auth_info/</code>. Bot tidak perlu scan ulang setelah restart.</li>
              <li>Untuk logout/ganti nomor: hapus folder <code>auth_info/</code> lalu restart wa-engine.</li>
            </ul>
          </div>
        </div>

        <div class="col-md-7">
          <div class="card border shadow-none">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <span class="fw-semibold">QR Code Live</span>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary" id="guide-qr-status-badge">Belum dimuat</span>
                <button class="btn btn-success btn-sm" id="guide-btn-load-qr">
                  <i class="ri ri-qr-code-line me-1"></i>Muat QR Code
                </button>
              </div>
            </div>
            <div class="card-body text-center py-4">
              <div id="guide-qr-msg" class="text-muted small mb-3">
                Klik "Muat QR Code" untuk memulai. wa-engine harus berjalan dan belum terhubung.
              </div>
              <div id="guide-qr-container" class="d-flex justify-content-center mb-3"></div>
              <div id="guide-qr-countdown" class="text-muted small d-none">
                <i class="ri ri-time-line me-1"></i>QR kadaluarsa dalam <span id="guide-qr-seconds">60</span>s — refresh otomatis…
              </div>
              <div id="guide-qr-connected" class="d-none py-3">
                <i class="ri ri-checkbox-circle-line text-success" style="font-size:3rem;"></i>
                <div class="text-success fw-bold fs-5 mt-2">WhatsApp Berhasil Terhubung!</div>
                <div class="text-muted small mt-1" id="guide-qr-phone"></div>
                <a href="<?= site_url('wa/dashboard') ?>" class="btn btn-primary btn-sm mt-3">
                  <i class="ri ri-dashboard-line me-1"></i>Buka Dashboard
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- PENGGUNAAN END USER -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-broadcast">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ri ri-broadcast-line me-2"></i>Broadcast — Kirim Pesan Massal</h5>
      <a href="<?= site_url('wa/broadcast/create') ?>" class="btn btn-light btn-sm">Buat Sekarang</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="fw-bold">Langkah Membuat Broadcast</h6>
          <ol class="small">
            <li class="mb-1">Buka <strong>Broadcast</strong> → <strong>Buat Broadcast</strong></li>
            <li class="mb-1">Isi nama, pilih tipe target:<br>
              <code>Manual</code> — input nomor sendiri<br>
              <code>Semua Member</code> / <code>Member Aktif</code> — dari loyalty
            </li>
            <li class="mb-1">Pilih template atau ketik pesan custom</li>
            <li class="mb-1">Simpan → status <span class="badge bg-secondary">DRAFT</span></li>
            <li class="mb-1">Buka detail → klik <strong>Mulai Kirim</strong></li>
          </ol>
        </div>
        <div class="col-md-6">
          <h6 class="fw-bold">Format Nomor Manual</h6>
          <pre class="bg-light rounded p-2 small">081234567890
081234567890|Budi Santoso
6281234567890|Sari Dewi</pre>
          <p class="small text-muted">Nomor <code>08xxx</code> otomatis jadi <code>62xxx</code>. Nama setelah <code>|</code> bisa dipakai sebagai <code>&#123;&#123;nama&#125;&#125;</code>.</p>
          <div class="d-flex gap-1 flex-wrap">
            <span class="badge bg-secondary">DRAFT</span>
            <span class="badge bg-warning text-dark">SENDING</span>
            <span class="badge bg-success">DONE</span>
            <span class="badge bg-danger">FAILED</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4" id="section-group">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ri ri-group-2-line me-2"></i>Grup WhatsApp</h5>
      <a href="<?= site_url('wa/group') ?>" class="btn btn-light btn-sm">Kelola Grup</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="fw-bold">Cara Mendapat Group JID</h6>
          <ol class="small">
            <li class="mb-1">Nomor bot harus menjadi anggota grup</li>
            <li class="mb-1">Dari <a href="<?= site_url('wa/settings') ?>">Pengaturan</a>, gunakan fitur <strong>Kirim Pesan Test</strong> dengan pesan <code>!listgroup</code> ke nomor admin — atau gunakan API <code>POST /internal/list-groups</code></li>
            <li class="mb-1">Copy JID format <code>120363xxx@g.us</code> ke halaman Grup WA</li>
          </ol>
        </div>
        <div class="col-md-6">
          <h6 class="fw-bold">Template Pesan</h6>
          <p class="small">Gunakan <code>&#123;&#123;variabel&#125;&#125;</code> dalam isi template:</p>
          <pre class="bg-light rounded p-2 small" style="white-space:pre-wrap;">Halo *&#123;&#123;nama&#125;&#125;*! 👋
Promo: *&#123;&#123;judul_promo&#125;&#125;*
📅 &#123;&#123;tanggal_promo&#125;&#125;

_Namua Coffee_</pre>
        </div>
      </div>
      <hr>
      <h6 class="fw-bold">Jadwal Laporan Otomatis dari Database</h6>
      <p class="small mb-2">
        Gunakan <a href="<?= site_url('wa/template/schedules') ?>">Jadwal Laporan WA</a> untuk mengirim laporan otomatis ke grup yang dipilih.
        Tipe laporan awal yang tersedia: omzet hari ini, rincian belanja hari ini, rincian adjustment hari ini, dan pengajuan PO/SR divisi hari ini.
      </p>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="small text-muted mb-1">Placeholder khusus laporan:</div>
          <pre class="bg-light rounded p-2 small" style="white-space:pre-wrap;">&#123;&#123;report_title&#125;&#125;
&#123;&#123;report_body&#125;&#125;
&#123;&#123;tanggal&#125;&#125;
&#123;&#123;generated_at&#125;&#125;
&#123;&#123;nama_grup&#125;&#125;</pre>
        </div>
        <div class="col-md-6">
          <div class="small text-muted mb-1">Cron server agar jadwal berjalan otomatis:</div>
          <pre class="bg-dark text-white rounded p-2 small" style="white-space:pre-wrap;">* * * * * cd /path/ke/finance &amp;&amp; php index.php whatsapp api_schedule_run &gt;/dev/null 2&gt;&amp;1</pre>
          <p class="small text-muted mb-0">Jalankan perintah CLI lokal tersebut dari root aplikasi dan sesuaikan <code>/path/ke/finance</code> dengan lokasi instalasi.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- TROUBLESHOOTING -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ri ri-tools-line me-2"></i>Troubleshooting</h5>
    </div>
    <div class="card-body">
      <div class="accordion" id="ts-acc">

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts0">
              Module not found / error saat <code>node index.js</code>
            </button>
          </h2>
          <div id="ts0" class="accordion-collapse collapse" data-bs-parent="#ts-acc">
            <div class="accordion-body small">
              <p>Folder <code>node_modules/</code> belum ada. Jalankan dari dalam <code>wa-engine/</code>:</p>
              <pre class="bg-dark text-white rounded p-2">cd C:\xampp\htdocs\finance\wa-engine   # Windows
# atau
cd /opt/lampp/htdocs/finance/wa-engine  # Ubuntu

npm install</pre>
              <p class="mb-0">Pastikan terminal berada di folder <code>wa-engine/</code>, bukan <code>finance/</code> atau <code>finance/application/</code>.</p>
            </div>
          </div>
        </div>

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts1">
              QR Code tidak muncul
            </button>
          </h2>
          <div id="ts1" class="accordion-collapse collapse" data-bs-parent="#ts-acc">
            <div class="accordion-body small">
              <ul>
                <li>wa-engine belum berjalan — masuk <code>finance/wa-engine/</code> dan jalankan <code>node index.js</code></li>
                <li>Folder <code>auth_info/</code> sudah ada (sesi lama) → bot langsung CONNECTED, tidak tampil QR. Hapus folder <code>auth_info/</code> jika ingin scan ulang</li>
                <li>Cek URL di <a href="<?= site_url('wa/settings') ?>">Pengaturan</a>: URL harus <code>http://127.0.0.1:3070</code>. Credential API internal wajib tersedia pada environment proses PHP/FPM dan wa-engine.</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts2">
              Ping Bot gagal / "Tidak dapat menghubungi WA Bot"
            </button>
          </h2>
          <div id="ts2" class="accordion-collapse collapse" data-bs-parent="#ts-acc">
            <div class="accordion-body small">
              <ul>
                <li>wa-engine tidak berjalan. Jalankan <code>node index.js</code> dari <code>finance/wa-engine/</code></li>
                <li>URL salah di pengaturan — default: <code>http://127.0.0.1:3070</code></li>
                <li>Token tidak cocok antara halaman Pengaturan dan file <code>.env</code></li>
              </ul>
            </div>
          </div>
        </div>

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts3">
              Status CONNECTED tapi pesan gagal
            </button>
          </h2>
          <div id="ts3" class="accordion-collapse collapse" data-bs-parent="#ts-acc">
            <div class="accordion-body small">
              <ul>
                <li>Nomor harus format <code>62xxx</code> tanpa <code>+</code> atau <code>0</code> di awal</li>
                <li>Nomor harus aktif di WhatsApp</li>
                <li>Group JID harus masih valid (<code>120363xxx@g.us</code>)</li>
                <li>Cek detail error di <a href="<?= site_url('wa/log') ?>">Log Pengiriman</a></li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
let guideQrInterval  = null;
let guideQrCountdown = 60;
let guideQrCdTimer   = null;

function clearGuideQrTimers() {
  if (guideQrInterval) { clearInterval(guideQrInterval);  guideQrInterval = null; }
  if (guideQrCdTimer)  { clearInterval(guideQrCdTimer);   guideQrCdTimer = null; }
}

function guideStartCountdown() {
  guideQrCountdown = 60;
  const el  = document.getElementById('guide-qr-countdown');
  const sec = document.getElementById('guide-qr-seconds');
  el.classList.remove('d-none');
  sec.textContent = guideQrCountdown;
  guideQrCdTimer = setInterval(() => {
    guideQrCountdown--;
    sec.textContent = guideQrCountdown;
    if (guideQrCountdown <= 0) { clearInterval(guideQrCdTimer); el.classList.add('d-none'); }
  }, 1000);
}

function guideRenderQr(qrString) {
  const container = document.getElementById('guide-qr-container');
  container.innerHTML = '';
  new QRCode(container, { text: qrString, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
}

function guideShowConnected(phone) {
  clearGuideQrTimers();
  document.getElementById('guide-qr-container').innerHTML = '';
  document.getElementById('guide-qr-countdown').classList.add('d-none');
  document.getElementById('guide-qr-msg').classList.add('d-none');
  document.getElementById('guide-qr-connected').classList.remove('d-none');
  if (phone) document.getElementById('guide-qr-phone').textContent = '📱 ' + phone;
  const badge = document.getElementById('guide-qr-status-badge');
  badge.className = 'badge bg-success';
  badge.textContent = 'Terhubung';
}

function guideFetchQr() {
  fetch('<?= site_url('wa/api/qr') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
      const status = (data.status || 'UNKNOWN').toUpperCase();
      const badge  = document.getElementById('guide-qr-status-badge');
      const labelMap = { CONNECTED: 'Terhubung', WAITING_QR: 'Menunggu QR', DISCONNECTED: 'Terputus', LOGGED_OUT: 'Sesi Keluar', UNKNOWN: 'Tidak Diketahui' };
      const classMap = { CONNECTED: 'bg-success', WAITING_QR: 'bg-warning text-dark', DISCONNECTED: 'bg-danger', LOGGED_OUT: 'bg-danger', UNKNOWN: 'bg-secondary' };
      badge.className = 'badge ' + (classMap[status] || 'bg-secondary');
      badge.textContent = labelMap[status] || status;

      if (status === 'CONNECTED') { guideShowConnected(data.phone || ''); return; }

      if (status === 'WAITING_QR' && data.qr) {
        document.getElementById('guide-qr-msg').textContent = 'Scan dengan WhatsApp: Perangkat Tertaut → Tautkan Perangkat';
        clearInterval(guideQrCdTimer);
        guideStartCountdown();
        guideRenderQr(data.qr);
      } else {
        document.getElementById('guide-qr-msg').textContent = 'wa-engine belum dalam mode QR. Pastikan berjalan dan belum terhubung.';
        document.getElementById('guide-qr-container').innerHTML = '';
      }
    })
    .catch(() => {
      document.getElementById('guide-qr-msg').textContent = 'Tidak dapat menghubungi wa-engine — pastikan sudah berjalan.';
    });
}

document.getElementById('guide-btn-load-qr')?.addEventListener('click', function () {
  document.getElementById('guide-qr-connected').classList.add('d-none');
  document.getElementById('guide-qr-msg').classList.remove('d-none');
  document.getElementById('guide-qr-msg').textContent = 'Menghubungi wa-engine…';
  clearGuideQrTimers();
  guideFetchQr();
  guideQrInterval = setInterval(guideFetchQr, 5000);
});

<?php if ($waStatus !== 'CONNECTED'): ?>
setTimeout(() => { document.getElementById('guide-btn-load-qr')?.click(); }, 800);
<?php endif; ?>
</script>
