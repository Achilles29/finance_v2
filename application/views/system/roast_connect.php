<?php
$connector = is_array($connector ?? null) ? $connector : [];
$base = rtrim(base_url(),'/');
?>
<style>
.roast-connect-hero{background:#f5ede5;border:1px solid #d9c6bd;border-radius:14px;padding:25px;color:#382b29}
.roast-connect-hero h3{font-family:Georgia,serif;color:#75283d;letter-spacing:.06em}
.roast-connect-shell{max-width:1250px}.roast-connect-shell .form-text{color:#585852}.roast-connect-token{font-family:monospace;word-break:break-all;background:#fffdf8}
.roast-connect-steps li{margin-bottom:.7rem}.roast-connect-shell [hidden]{display:none!important}
.roast-connect-shell .alert-info{background:#e5f0e9!important;border-color:#b6cbbb;color:#244c37!important}.roast-connect-shell .badge.bg-success{background:#28583d!important;color:#fff!important}
</style>
<div class="container-fluid py-3 roast-connect-shell" id="roastConnectAdmin"
     data-settings="<?= html_escape(json_encode($connector,JSON_UNESCAPED_UNICODE)) ?>"
     data-save-url="<?= html_escape(site_url('system/roast-connect/save')) ?>"
     data-rotate-url="<?= html_escape(site_url('system/roast-connect/token')) ?>">
  <div class="roast-connect-hero mb-4 d-flex flex-wrap justify-content-between gap-3">
    <div><div class="small fw-bold mb-2">FINANCE CONNECT</div><h3>NAMUA × FINANCE</h3><p class="mb-0">Katalog stok Finance untuk Roast Studio. Terhubung lewat HTTPS, termasuk di server berbeda.</p></div>
    <div><span class="badge bg-secondary" id="rcStatus">Memuat…</span><div class="small mt-2">Dikelola superadmin</div></div>
  </div>
  <div class="row g-4">
    <div class="col-lg-7">
      <section class="card mb-4"><div class="card-header fw-semibold">1. Atur akses katalog</div><div class="card-body">
        <form id="rcForm">
          <input type="hidden" name="connector_csrf" value="<?= html_escape($connector_csrf) ?>">
          <div class="mb-3"><label for="rcName" class="form-label">Nama koneksi yang tampil di Roast Studio</label><input id="rcName" class="form-control" maxlength="160" required></div>
          <div class="row g-3 mb-3"><div class="col-md-7"><label for="rcDivision" class="form-label">Divisi pemilik stok</label><select id="rcDivision" class="form-select" required><option value="">Pilih divisi</option><?php foreach ($divisions as $division): ?><option value="<?= (int)$division['id'] ?>"><?= html_escape($division['name'].' · '.$division['code']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-5"><label for="rcDestination" class="form-label">Lokasi penggunaan</label><select id="rcDestination" class="form-select"><?php foreach (['ROASTERY','BAR','KITCHEN','EVENT'] as $location): ?><option><?= $location ?></option><?php endforeach; ?></select></div></div>
          <p class="form-text">Pilih divisi dan lokasi yang sama dengan stok green bean Anda. Token hanya membaca saldo pada lingkup ini.</p>
          <div class="form-check my-3"><input class="form-check-input" id="rcEnabled" type="checkbox"><label class="form-check-label" for="rcEnabled">Izinkan Roast Studio membaca katalog dan saldo</label></div>
          <button class="btn btn-primary" type="submit" id="rcSave">Simpan pengaturan</button>
        </form>
      </div></section>
      <section class="card mb-4"><div class="card-header fw-semibold">2. Buat token untuk Roast Studio</div><div class="card-body">
        <p id="rcTokenState"></p><p class="form-text">Token merupakan kunci koneksi khusus. Gunakan tombol di bawah; Anda tidak perlu mencari token login atau password database.</p>
        <label for="rcValidity" class="form-label">Masa berlaku token baru</label><select id="rcValidity" class="form-select mb-3"><option value="365">365 hari</option><option value="90">90 hari</option><option value="30">30 hari</option></select>
        <button class="btn btn-outline-primary" type="button" id="rcRotate">Buat token koneksi</button>
        <p class="form-text mt-2" id="rcRotateHint">Pembuatan token sekaligus menyimpan pilihan divisi dan status akses pada formulir di atas.</p>
        <div id="rcTokenPanel" class="border rounded p-3 mt-3" hidden>
          <label for="rcNewToken" class="form-label fw-bold">Token baru — ditampilkan satu kali</label>
          <textarea id="rcNewToken" class="form-control roast-connect-token" rows="3" readonly autocomplete="off" spellcheck="false"></textarea>
          <div class="d-flex gap-2 flex-wrap mt-2"><button type="button" class="btn btn-primary btn-sm" id="rcCopyToken">Salin token</button><button type="button" class="btn btn-outline-secondary btn-sm" id="rcHideToken">Sembunyikan token</button></div>
          <p class="form-text mb-0 mt-2">Tempel di Roast Studio → Integrasi → Token koneksi. Setelah ditutup atau dimuat ulang, token ini tidak dapat ditampilkan lagi.</p>
        </div>
      </div></section>
      <div id="rcMessage" role="status" class="alert" hidden></div>
    </div>
    <div class="col-lg-5"><section class="card h-100"><div class="card-header fw-semibold">3. Hubungkan di Roast Studio</div><div class="card-body">
      <label class="form-label" for="rcFinanceUrl">Alamat Finance untuk kolom Integrasi</label><input id="rcFinanceUrl" readonly class="form-control mb-3" value="<?= html_escape($base) ?>">
      <ol class="roast-connect-steps ps-3"><li>Buat token pada halaman ini dan aktifkan akses katalog.</li><li>Di Roast Studio → Integrasi, masukkan alamat Finance di atas dan token baru.</li><li>Klik <strong>Simpan &amp; uji koneksi</strong>, lalu <strong>Tarik katalog Finance</strong>.</li><li>Tandai bahan yang benar-benar green bean dan simpan pilihan.</li><li>Aktifkan penggunaan katalog Finance saat mulai roasting.</li></ol>
      <div class="alert alert-info small">Konektor ini membaca katalog dan saldo. Pembuatan batch produksi, reservasi, dan adjustment belum dilakukan otomatis. Batch produksi tetap mengikuti formula Finance.</div>
      <p class="form-text">Jika Finance pindah server atau domain, masukkan alamat HTTPS barunya di Roast Studio. Database kedua aplikasi tetap terpisah. Penggantian token di Finance perlu diikuti pengisian token baru di Studio.</p>
      <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('settings') ?>">Kembali ke pengaturan akun</a>
    </div></section></div>
  </div>
  <section class="card mt-4"><div class="card-header fw-semibold">Aktivitas pengaturan konektor</div><div class="card-body"><p class="form-text">20 aktivitas terbaru. Isi token tidak dicatat. Muat ulang halaman untuk melihat aktivitas baru.</p><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Waktu (UTC)</th><th>Aktivitas</th><th>ID admin</th><th>Lingkup</th></tr></thead><tbody>
  <?php foreach ($connector_audit as $event): $detail=json_decode($event['details'],true)?:[]; ?><tr><td><?= html_escape($event['created_at']) ?></td><td><?= html_escape(['TOKEN_CREATED'=>'Token dibuat','TOKEN_ROTATED'=>'Token diganti','SETTINGS_SAVED'=>'Pengaturan disimpan'][$event['event_type']]??$event['event_type']) ?></td><td>#<?= (int)$event['actor_id'] ?></td><td><?= html_escape('Divisi #'.($detail['division_id']??'').' / '.($detail['destination_type']??'')) ?></td></tr><?php endforeach; ?>
  <?php if (!$connector_audit): ?><tr><td colspan="4">Belum ada aktivitas konektor.</td></tr><?php endif; ?>
  </tbody></table></div></div></section>
</div>
<script src="<?= base_url('assets/js/roast-connect-admin.js') ?>?v=<?= filemtime(FCPATH.'assets/js/roast-connect-admin.js') ?>" defer></script>
