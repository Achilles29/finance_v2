<div class="container-fluid py-3"><h3>Paket &amp; Upgrade</h3>
<div class="card mb-3"><div class="card-body"><h5>Paket saat ini: <?= html_escape($policy->editionName()) ?></h5>
<p>Menu bergembok → lihat informasi upgrade → setelah upgrade disetujui dan lisensi tersinkron, fitur terbuka.</p>
<ol><li>Hubungi penjual melalui kontak pada pesan pengiriman ZIP atau bukti pembelian Anda. Jika pembelian diurus kantor, teruskan permintaan kepada administrator yang menerima paket tersebut.</li><li>Sampaikan alamat aplikasi, paket saat ini, dan nama fitur yang ingin dibuka.</li><li>Penjual memproses perubahan paket. Setelah sinkronisasi lisensi berhasil, muat ulang halaman ini. Tidak perlu logout, impor database, atau memasang ulang.</li></ol>
<p class="text-muted mb-0">Belum tersedia pengajuan upgrade otomatis dari aplikasi. Halaman ini tidak mengirim permintaan kepada penjual dan tidak membuka halaman admin Control.</p></div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fitur</th><th>Hak paket terverifikasi</th></tr></thead><tbody>
<?php foreach ($policy->catalog() as $code=>$feature): if (($feature['value_type'] ?? '') !== 'BOOLEAN') continue; ?>
<tr><td><?= html_escape($feature['name']) ?></td><td><?= $policy->allows($code) ? 'Termasuk paket' : 'Memerlukan upgrade' ?></td></tr>
<?php endforeach; ?></tbody></table></div></div><a class="btn btn-outline-secondary mt-3" href="<?= site_url('dashboard') ?>">Kembali ke beranda</a></div>
