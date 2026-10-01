<?php
$phase=$update['phase']??'WAITING_SERVICE';
$labels=['MASTER_SOURCE'=>'Ini adalah source master','WAITING_SERVICE'=>'Menunggu pendamping','WAITING_INSTALLATION'=>'Selesaikan pemasangan terlebih dahulu',
 'NO_UPDATE'=>'Belum ada pembaruan baru','PREPARING'=>'Menyiapkan pembaruan','DOWNLOADING'=>'Mengunduh pembaruan','READY'=>'Pembaruan siap dipasang',
 'BACKUP'=>'Menyimpan cadangan data','MIGRATING'=>'Memperbarui database','SWITCHING'=>'Memasang file baru','VERIFYING'=>'Memeriksa hasil pemasangan','COMPLETE'=>'Pembaruan selesai','ATTENTION'=>'Perlu pemeriksaan administrator'];
?>
<div class="container-fluid py-3"><div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Pembaruan aplikasi</h1><p class="text-muted mb-0">Versi baru, tanpa memasang ulang usaha Anda.</p></div><a class="btn btn-outline-secondary" href="<?= site_url('system/license') ?>">Kembali ke lisensi</a></div>
<div class="card shadow-sm"><div class="card-body p-4"><h2 class="h4"><?= html_escape($labels[$phase]??'Memeriksa pembaruan') ?></h2>
<?php if($phase==='READY'): ?>
<p class="lead mb-2"><?= html_escape($update['from_version']??'') ?> → <strong><?= html_escape($update['to_version']??'') ?></strong></p>
<p>Data, pengaturan, unggahan, dan lisensi Anda tetap digunakan. Sistem membuat cadangan sebelum mengganti file dan database.</p>
<form method="post" action="<?= site_url('system/updates/confirm') ?>">
<input type="hidden" name="update_csrf" value="<?= html_escape($update_csrf) ?>"><input type="hidden" name="plan_sha256" value="<?= html_escape($update['plan_sha256']) ?>">
<input type="hidden" name="<?= html_escape($this->security->get_csrf_token_name()) ?>" value="<?= html_escape($this->security->get_csrf_hash()) ?>">
<label class="d-block mb-3"><input type="checkbox" name="confirmed" value="1" required> Semua pengguna sudah menyelesaikan transaksi. Saya setuju aplikasi berhenti sementara untuk pembaruan.</label>
<button class="btn btn-primary" type="submit">Pasang pembaruan</button></form>
<?php elseif($phase==='ATTENTION'): ?>
<p>Proses ditahan agar data tetap aman. Jangan memasang ulang atau menghapus database. Hubungi penjual dengan kode berikut.</p><code><?= html_escape($update['code']??'UPDATE_REVIEW_REQUIRED') ?></code>
<?php elseif($phase==='COMPLETE'): ?><p>Versi <?= html_escape($update['version']??'baru') ?> sudah terpasang. Silakan lanjutkan aktivitas seperti biasa.</p>
<?php elseif($phase==='MASTER_SOURCE'): ?><p>Pembaruan digunakan pada instalasi customer. Source master dikelola melalui proses rilis di Control.</p>
<?php elseif($phase==='NO_UPDATE'): ?><p>Jika penjual menyediakan versi baru untuk instalasi ini, pembaruan akan tampil di sini.</p>
<?php else: ?><p>Pendamping sedang memeriksa atau mengerjakan pembaruan. Klik Periksa lagi untuk melihat status terbaru.</p><?php endif; ?>
<a class="btn btn-outline-secondary mt-3" href="<?= site_url('system/updates') ?>">Periksa lagi</a>
</div></div><div class="mt-3 text-muted small">Pembaruan tidak menambah jumlah server aktif dan tidak mengganti paket lisensi yang Anda beli.</div></div>
