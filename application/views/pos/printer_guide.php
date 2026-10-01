<?php
$downloadFiles = is_array($download_files ?? null) ? $download_files : [];
$canAgentProvision = !empty($can_agent_provision);
?>

<style>
  .printer-guide-hero {
    border: 0;
    border-radius: 24px;
    background:
      radial-gradient(circle at top right, rgba(212, 69, 100, .12), transparent 24%),
      linear-gradient(135deg, #fffdfb 0%, #fff6ee 100%);
    box-shadow: 0 18px 42px rgba(61, 43, 32, .08);
  }
  .printer-guide-kicker {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .38rem .72rem;
    border-radius: 999px;
    background: #fff4ee;
    color: #a64050;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
  }
  .printer-guide-card {
    border: 0;
    border-radius: 22px;
    box-shadow: 0 14px 34px rgba(57, 39, 32, .07);
  }
  .printer-guide-step {
    display: grid;
    grid-template-columns: 44px 1fr;
    gap: .9rem;
    align-items: start;
  }
  .printer-guide-step-no {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #b4233c 0%, #d24a4a 100%);
    box-shadow: 0 10px 18px rgba(180, 35, 60, .2);
  }
  .printer-guide-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .3rem .62rem;
    border-radius: 999px;
    background: #f7eee8;
    color: #7f6759;
    font-size: .78rem;
    font-weight: 700;
  }
  .printer-guide-flow {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .8rem;
  }
  .printer-guide-flow-item {
    border: 1px solid rgba(187, 165, 150, .35);
    border-radius: 18px;
    background: rgba(255, 255, 255, .9);
    padding: 1rem;
  }
  .printer-guide-flow-item i {
    font-size: 1.2rem;
    color: #b4233c;
  }
  .printer-guide-table td:first-child {
    width: 210px;
    font-weight: 700;
    color: #3c2f2f;
  }
  .printer-guide-downloads .btn {
    text-align: left;
    justify-content: flex-start;
    gap: .55rem;
  }
  .printer-guide-code {
    border-radius: 18px;
    background: #271f24;
    color: #fff7f2;
    padding: 1rem 1.1rem;
    font-size: .88rem;
    overflow: auto;
  }
  @media (max-width: 991.98px) {
    .printer-guide-flow {
      grid-template-columns: 1fr 1fr;
    }
  }
  @media (max-width: 575.98px) {
    .printer-guide-flow {
      grid-template-columns: 1fr;
    }
    .printer-guide-step {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Panduan Pengaturan Printer POS</h4>
      <p class="fin-page-subtitle mb-0">Pola ini mengadopsi alur printer bluetooth dari core: konfigurasi tetap di database, lalu laptop kasir menjalankan Python agent lokal untuk mengirim raw ESC/POS ke printer.</p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>
  <?php $this->load->view('pos/_printer_tabs', ['printer_tab_active' => 'guide']); ?>

  <div class="card printer-guide-hero mb-4">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
        <div>
          <div class="printer-guide-kicker mb-3"><i class="ri-bluetooth-line"></i> Bluetooth + Python Agent</div>
          <h3 class="mb-2">Arsitektur yang kita pakai sederhana, stabil, dan siap untuk kasir desktop.</h3>
          <p class="text-muted mb-3">Browser POS tidak bicara langsung ke hardware. Browser hanya menembak <code>localhost</code>, lalu Python agent lokal yang mendeteksi COM dari MAC Address printer bluetooth dan mengirim teks ESC/POS ke printer.</p>
          <div class="d-flex flex-wrap gap-2">
            <span class="printer-guide-chip"><i class="ri-database-2-line"></i> Setting utama di database</span>
            <span class="printer-guide-chip"><i class="ri-computer-line"></i> Agent jalan di laptop kasir</span>
            <span class="printer-guide-chip"><i class="ri-printer-line"></i> Cetak via raw ESC/POS</span>
          </div>
        </div>
        <div class="d-grid gap-2 printer-guide-downloads" style="min-width:260px;">
          <?php if ($canAgentProvision): ?><a class="btn btn-primary" href="<?= site_url('pos/printers/download/config_json') ?>"><i class="ri-download-2-line"></i>Download config.json</a><?php endif; ?>
          <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/agent_py') ?>"><i class="ri-file-code-line"></i>Download agent.py</a>
          <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/check_saved_printers') ?>"><i class="ri-file-search-line"></i>Download check_saved_printers.py</a>
          <a class="btn btn-outline-secondary" href="<?= site_url('pos/printers/download/detect_windows') ?>"><i class="ri-terminal-box-line"></i>Download detect_windows.bat</a>
          <a class="btn btn-outline-secondary" href="<?= site_url('pos/printers/download/run_windows') ?>"><i class="ri-play-circle-line"></i>Download run_windows.bat</a>
        </div>
      </div>
    </div>
  </div>

  <div class="printer-guide-flow mb-4">
    <div class="printer-guide-flow-item">
      <i class="ri-settings-3-line"></i>
      <div class="fw-bold mt-2">1. Simpan Device</div>
      <div class="small text-muted mt-1">Isi outlet, terminal, mode bluetooth, MAC Address, agent host, dan Python port di master printer finance.</div>
    </div>
    <div class="printer-guide-flow-item">
      <i class="ri-download-cloud-2-line"></i>
      <div class="fw-bold mt-2">2. Download Agent</div>
      <div class="small text-muted mt-1">Laptop kasir mengambil <code>config.json</code>, <code>agent.py</code>, dan script bantu deteksi dari halaman ini.</div>
    </div>
    <div class="printer-guide-flow-item">
      <i class="ri-radar-line"></i>
      <div class="fw-bold mt-2">3. Agent Bootstrap</div>
      <div class="small text-muted mt-1">Python agent membaca printer aktif dari endpoint finance dan menyiapkan port lokal untuk browser POS.</div>
    </div>
    <div class="printer-guide-flow-item">
      <i class="ri-printer-cloud-line"></i>
      <div class="fw-bold mt-2">4. Browser Cetak</div>
      <div class="small text-muted mt-1">Saat order, payment, refund, atau void, browser mengirim payload ke <code>http://127.0.0.1:{python_port}/cetak</code>.</div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-7">
      <div class="card printer-guide-card">
        <div class="card-body p-4">
          <h5 class="mb-3">Langkah Windows</h5>
          <div class="d-grid gap-3">
            <div class="printer-guide-step">
              <div class="printer-guide-step-no">1</div>
              <div>
                <div class="fw-bold">Pair printer bluetooth di Windows</div>
                <div class="text-muted small mt-1">Pastikan printer sudah muncul di daftar Bluetooth Windows dan test print dasar dari sisi pairing/driver tidak bermasalah.</div>
              </div>
            </div>
            <div class="printer-guide-step">
              <div class="printer-guide-step-no">2</div>
              <div>
                <div class="fw-bold">Deteksi MAC dan COM port</div>
                <div class="text-muted small mt-1">Download <code>detect_printers.py</code> dan <code>detect_windows.bat</code>, simpan di folder yang sama, lalu jalankan <code>detect_windows.bat</code>. Script akan membuat <code>printer_detect.json</code>.</div>
              </div>
            </div>
            <div class="printer-guide-step">
              <div class="printer-guide-step-no">3</div>
              <div>
                <div class="fw-bold">Isi master Device Printer di finance</div>
                <div class="text-muted small mt-1">Gunakan <strong>Driver = ESC_POS</strong> dan <strong>Connection = BLUETOOTH</strong>, lalu isi MAC Address, Agent Host, dan Python Port sesuai laptop kasir.</div>
              </div>
            </div>
            <div class="printer-guide-step">
              <div class="printer-guide-step-no">4</div>
              <div>
                <div class="fw-bold">Install Python agent</div>
                <div class="text-muted small mt-1">Buat folder misalnya <code>C:\pos_printer_agent</code>, salin file download, lalu install dependency dari <code>requirements.txt</code>.</div>
                <div class="printer-guide-code mt-2">cd C:\pos_printer_agent
python -m venv .venv
.venv\Scripts\activate
python -m pip install -r requirements.txt</div>
              </div>
            </div>
            <div class="printer-guide-step">
              <div class="printer-guide-step-no">5</div>
              <div>
                <div class="fw-bold">Validasi bootstrap lalu jalankan service</div>
                <div class="text-muted small mt-1">Jalankan agent sekali untuk memastikan printer aktif terbaca dari finance, lalu jalankan mode service.</div>
                <div class="printer-guide-code mt-2">python agent.py --config config.json --once
python agent.py --config config.json</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card printer-guide-card mb-4">
        <div class="card-body p-4">
          <h5 class="mb-3">Field yang Harus Diisi di Device Printer</h5>
          <div class="table-responsive">
            <table class="table printer-guide-table align-middle mb-0">
              <tbody>
                <tr><td>Driver</td><td><strong>ESC_POS</strong> untuk mode bluetooth agent Python.</td></tr>
                <tr><td>Connection</td><td><strong>BLUETOOTH</strong> agar sistem tahu device ini memakai pola localhost agent.</td></tr>
                <tr><td>Agent Host</td><td>Nama laptop / agent, misalnya <code>POS-PRINTER-AGENT-01</code>.</td></tr>
                <tr><td>MAC Address</td><td>Alamat bluetooth printer. Format aman: <code>86677A7B9914</code>.</td></tr>
                <tr><td>Python Port</td><td>Port localhost unik per printer di laptop itu, misalnya <code>3000</code>, <code>3001</code>.</td></tr>
                <tr><td>Paper Width</td><td>Umumnya <code>58</code> atau <code>80</code> mm.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card printer-guide-card">
        <div class="card-body p-4">
          <h5 class="mb-3">File Download</h5>
          <div class="d-grid gap-2 printer-guide-downloads">
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/readme') ?>"><i class="ri-book-open-line"></i>README Agent</a>
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/requirements') ?>"><i class="ri-file-list-3-line"></i>requirements.txt</a>
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/agent_py') ?>"><i class="ri-file-code-line"></i>agent.py</a>
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/check_saved_printers') ?>"><i class="ri-file-search-line"></i>check_saved_printers.py</a>
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/detect_py') ?>"><i class="ri-search-eye-line"></i>detect_printers.py</a>
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/detect_windows') ?>"><i class="ri-terminal-box-line"></i>detect_windows.bat</a>
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/run_windows') ?>"><i class="ri-play-circle-line"></i>run_windows.bat</a>
            <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/download/config_example') ?>"><i class="ri-file-settings-line"></i>config.example.json</a>
            <?php if ($canAgentProvision): ?><a class="btn btn-primary" href="<?= site_url('pos/printers/download/config_json') ?>"><i class="ri-download-2-line"></i>config.json dari Finance</a><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card printer-guide-card mt-4">
    <div class="card-body p-4">
      <h5 class="mb-3">Catatan Operasional</h5>
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="printer-guide-chip mb-2"><i class="ri-information-line"></i>Realtime Bootstrap</div>
          <div class="small text-muted">Perubahan printer aktif, MAC, atau routing dibaca ulang oleh agent dari finance. Kalau <code>python_port</code> berubah, service lama tetap perlu restart.</div>
        </div>
        <div class="col-lg-4">
          <div class="printer-guide-chip mb-2"><i class="ri-shield-keyhole-line"></i>Key Bootstrap</div>
          <div class="small text-muted">Environment <code>POS_PRINTER_BOOTSTRAP_KEY</code> di PHP-FPM wajib berisi key bootstrap; agent mengirimkannya hanya melalui header <code>X-Printer-Key</code>. Endpoint fail-closed bila environment kosong atau header tidak valid.</div>
        </div>
        <div class="col-lg-4">
          <div class="printer-guide-chip mb-2"><i class="ri-smartphone-line"></i>Mobile Nanti Terpisah</div>
          <div class="small text-muted">Halaman ini fokus untuk kasir desktop bluetooth + Python agent. Mode printer mobile akan kita desain terpisah saat fase aplikasi POS mobile dimulai.</div>
        </div>
      </div>
    </div>
  </div>
</div>
