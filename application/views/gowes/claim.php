<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0085cc">
  <meta name="description" content="Gowes selesai, mampir Namua! Klaim voucher Namua Coffee &amp; Eatery diskon 15% untuk peserta GOWES VOL9 dalam rangkaian JAJAN FEST 3X.">
  <title>Voucher Namua 15% | GOWES VOL9 &amp; JAJAN FEST 3X</title>
  <link rel="icon" href="<?= base_url('assets/gowes/namua-logo.png') ?>" type="image/png">
  <link rel="stylesheet" href="<?= base_url('assets/gowes/claim.css') ?>?v=<?= filemtime(FCPATH . 'assets/gowes/claim.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/gowes/namua-voucher.css') ?>?v=<?= filemtime(FCPATH . 'assets/gowes/namua-voucher.css') ?>">
  <script src="<?= base_url('assets/gowes/claim.js') ?>?v=<?= filemtime(FCPATH . 'assets/gowes/claim.js') ?>" defer></script>
</head>
<body>
<div class="page-shell">
  <header class="masthead">
    <a href="<?= base_url() ?>" class="namua-brand" aria-label="Namua Coffee &amp; Eatery, halaman utama">
      <img id="namua-logo" src="<?= base_url('assets/gowes/namua-logo.png') ?>" alt="Logo Namua Coffee &amp; Eatery" width="68" height="68">
      <span><strong>NAMUA</strong><small>COFFEE &amp; EATERY</small></span>
    </a>
    <div class="event-lockup"><span>GOWES VOL9<br><strong>JAJAN FEST 3X</strong></span><img id="festival-art" src="<?= base_url('assets/gowes/jajan-fest.png') ?>" alt="Jajan Fest 3X Semen Gresik" width="78" height="58"></div>
  </header>

  <main id="main-content">
    <div class="claim-shell">
      <section class="event-poster" aria-labelledby="event-heading">
        <div class="poster-top"><span class="eyebrow">NAMUA RIDER REWARD</span><span class="edition">VOL / 09</span></div>
        <h1 id="event-heading">GAS GOWES.<br><span>GAS JAJAN.</span></h1>
        <p class="poster-caption">Kayuhan seru, jajan makin hemat.<br>Isi energi lagi di Namua Coffee &amp; Eatery.</p>
        <div class="art-stage">
          <div class="orbit orbit-one" aria-hidden="true"></div><div class="orbit orbit-two" aria-hidden="true"></div>
          <img id="gowes-art" src="<?= base_url('assets/gowes/gowesday.png') ?>" alt="Maskot Gowesday Vol.9 mengendarai sepeda" width="321" height="272">
          <div class="reward-sticker" aria-label="Diskon 15 persen"><span>YOUR NEXT TREAT</span><strong>15<small>%</small></strong><span>OFF, LET'S GO!</span></div>
          <span class="spark spark-one" aria-hidden="true">+</span><span class="spark spark-two" aria-hidden="true">+</span>
        </div>
        <div class="poster-bottom"><span>GOWES VOL9</span><span class="separator" aria-hidden="true"></span><span>JAJAN FEST 3X</span><svg viewBox="0 0 70 20" aria-hidden="true"><path d="M0 10h65M55 1l12 9-12 9"/></svg></div>
      </section>

      <section class="claim-panel" aria-labelledby="claim-heading">
        <div id="claim-intro">
          <p class="step-label"><span>01</span> KLAIM HADIAHMU</p>
          <h2 id="claim-heading">Gowes selesai.<br><em>Saatnya jajan.</em></h2>
          <p class="intro-copy">Ada hadiah dari Namua buat teman gowes. Masukkan email yang dipakai saat mendaftar GOWES VOL9 untuk klaim potongan belanjamu.</p>
          <div class="benefits" aria-label="Ketentuan voucher">
            <div><strong>15%</strong><span>Potongan belanja</span></div>
            <div><strong>Rp0</strong><span>Minimum belanja</span></div>
            <div><strong>7 hari</strong><span>Sejak klaim</span></div>
          </div>
          <form id="claim-form" action="<?= site_url('gowes-vol9/claim') ?>" method="post">
            <input type="hidden" name="claim_csrf" value="<?= html_escape($claim_csrf) ?>">
            <div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" type="text" name="website" tabindex="-1" autocomplete="off" value=""></div>
            <label for="email">Email pendaftaran</label>
            <input type="email" id="email" name="email" placeholder="nama@email.com" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="254" required aria-describedby="email-hint form-message">
            <p id="email-hint" class="field-hint">Pastikan sama dengan email saat registrasi, ya.</p>
            <p id="form-message" class="form-message" role="alert" hidden></p>
            <button id="claim-button" class="primary-button" type="submit"><span>Klaim voucher 15%</span><span aria-hidden="true">&#8599;</span></button>
          </form>
          <p class="privacy-note"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="4" y="8" width="12" height="10" rx="2"/><path d="M6 8V5a4 4 0 0 1 8 0v3m-4 4v3"/></svg>Satu email, satu voucher. Email hanya digunakan untuk mencocokkan data peserta.</p>
          <noscript><p class="form-message">Aktifkan JavaScript agar voucher dapat ditampilkan dan diunduh.</p></noscript>
        </div>

        <div id="voucher-result" hidden>
          <p class="step-label"><span>02</span> SIMPAN &amp; PAKAI</p>
          <h2 id="result-heading" tabindex="-1">Hadiahmu<br><em>sudah siap!</em></h2>
          <p id="result-message" class="intro-copy" role="status"></p>
          <article class="reward-ticket" aria-label="Voucher Namua Coffee &amp; Eatery untuk GOWES VOL9">
            <div class="reward-brand">
              <img src="<?= base_url('assets/gowes/namua-logo.png') ?>" alt="Namua Coffee &amp; Eatery" width="46" height="46">
              <div><strong>NAMUA</strong><span>COFFEE &amp; EATERY</span></div>
              <span class="reward-edition">RIDER<br>REWARD / 09</span>
            </div>
            <div class="reward-offer">
              <span class="reward-event">GOWES VOL9 <span aria-hidden="true">/</span> JAJAN FEST 3X</span>
              <div class="reward-offer-body">
                <div><strong class="reward-percent">15<small>% OFF</small></strong><p>Gowes dulu.<br>Jajannya di Namua.</p></div>
                <img class="reward-art" src="<?= base_url('assets/gowes/gowesday.png') ?>" alt="" width="98" height="83">
              </div>
              <span class="reward-no-minimum">TANPA MINIMUM BELANJA</span>
            </div>
            <div class="reward-details">
              <span class="reward-label">KODE VOUCHER PERSONAL</span>
              <div class="reward-code-well"><strong id="voucher-code" class="reward-code"></strong></div>
              <span id="voucher-status" class="voucher-status reward-status"></span>
              <p class="reward-expiry"><span>Berlaku sampai</span><strong id="voucher-expiry"></strong></p>
              <div class="reward-footnote"><span>7 hari sejak klaim</span><span>1 kali transaksi</span></div>
            </div>
          </article>
          <button class="primary-button" id="download-button" type="button"><span>Unduh voucher (PNG)</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg></button>
          <div class="secondary-actions"><button type="button" id="copy-button">Salin kode</button><button type="button" id="another-email">Gunakan email lain</button></div>
          <p class="field-hint" id="download-message" role="status">Tunjukkan kode voucher kepada kasir Namua saat pembayaran.</p>
        </div>
        <div class="panel-footer"><span class="tiny-wheel" aria-hidden="true">&#9678;</span> A LITTLE REWARD FOR YOUR RIDE.</div>
      </section>
    </div>

    <section class="how-to" aria-labelledby="how-heading">
      <h2 id="how-heading">DARI PEDAL<br>KE MEJA JAJAN.</h2>
      <div><span class="step-number">01</span><h3>Masukkan email</h3><p>Gunakan email yang terdaftar sebagai peserta.</p></div>
      <div><span class="step-number">02</span><h3>Simpan vouchernya</h3><p>Unduh gambar atau salin kode unik milikmu.</p></div>
      <div><span class="step-number">03</span><h3>Mampir ke Namua</h3><p>Tunjukkan ke kasir. Berlaku 7 hari dan satu kali pakai.</p></div>
    </section>
    <section class="faq" aria-label="Informasi klaim">
      <details><summary>Sudah klaim, tetapi voucher belum tersimpan?</summary><p>Masukkan kembali email yang sama untuk melihat dan mengunduh voucher yang sudah diklaim. Kode dan tanggal kedaluwarsa tidak berubah.</p></details>
      <details><summary>Email belum ditemukan?</summary><p>Periksa ejaan email dan gunakan alamat yang sama dengan pendaftaran GOWES VOL9. Jika masih belum ditemukan, hubungi panitia untuk memeriksa data peserta.</p></details>
    </section>
  </main>
  <footer class="site-footer"><div class="namua-footer"><img src="<?= base_url('assets/gowes/namua-logo.png') ?>" alt="" width="36" height="36"><strong>NAMUA COFFEE &amp; EATERY<br><span>GOWES VOL9 &times; JAJAN FEST 3X</span></strong></div><span>Good rides. Good coffee. Good company.</span></footer>
</div>
</body>
</html>
