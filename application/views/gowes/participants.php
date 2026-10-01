<?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => 'gowes']); ?>
<div class="card mb-3"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div><h4>Peserta GOWES VOL9</h4><p class="text-muted mb-0">JAJAN FEST 3X &middot; Voucher 15%, tanpa minimum belanja, berlaku 7 hari sejak klaim.</p></div>
    <a class="btn btn-primary align-self-start" href="<?= site_url('gowes-vol9') ?>" target="_blank" rel="noopener">Buka halaman publik</a>
  </div>
  <div class="row g-2 mb-3">
    <div class="col-md-4"><div class="border rounded p-3"><small>Peserta unik</small><h3 class="mb-0"><?= (int)$stats['total'] ?></h3></div></div>
    <div class="col-md-4"><div class="border rounded p-3"><small>Sudah klaim</small><h3 class="mb-0"><?= (int)$stats['claimed'] ?></h3></div></div>
    <div class="col-md-4"><div class="border rounded p-3"><small>Belum klaim</small><h3 class="mb-0"><?= (int)$stats['total'] - (int)$stats['claimed'] ?></h3></div></div>
  </div>
  <?php if (!empty($import_result)): ?>
    <div class="alert <?= !empty($import_result['ok']) ? 'alert-success' : 'alert-danger' ?>" role="status"><?= !empty($import_result['ok']) ? 'Impor berhasil. Voucher yang sudah diklaim tidak berubah.' : html_escape($import_result['message']) ?></div>
  <?php endif; ?>
  <h5>Perbarui data peserta</h5>
  <p>Unggah XLSX dengan kolom <code>email</code> dan <code>nama_lengkap</code> pada baris pertama, di sheet pertama. Maksimal 2 MB / 5.000 baris. Email dinormalisasi menjadi huruf kecil; duplikat dalam file diambil baris pertama. Email tidak valid dilewati, bukan ditebak.</p>
  <p>Impor berikutnya menambah peserta baru dan memperbarui nama peserta lama. Peserta yang tidak ada dalam file baru <strong>tidak dihapus</strong>. Kode voucher, status pemakaian, dan masa berlaku tidak direset.</p>
  <?php if ($can_import): ?>
  <form action="<?= site_url('loyalty/gowes-participants/import') ?>" method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
    <input type="hidden" name="import_csrf" value="<?= html_escape($import_csrf) ?>">
    <div class="col-md-8"><label for="participants" class="form-label">File peserta (.xlsx)</label><input class="form-control" id="participants" type="file" name="participants" accept=".xlsx" required></div>
    <div class="col-md-4"><button class="btn btn-primary" type="submit">Impor / perbarui peserta</button></div>
  </form>
  <?php endif; ?>
  <?php if ($last_import): ?>
  <div class="alert alert-info mt-3 mb-0">
    Impor terakhir: <?= html_escape($last_import['source_name']) ?> (<?= html_escape($last_import['imported_at']) ?>). Total <?= (int)$last_import['total_rows'] ?> baris;
    <?= (int)$last_import['added_rows'] ?> baru, <?= (int)$last_import['updated_rows'] ?> diperbarui,
    <?= (int)$last_import['duplicate_rows'] ?> duplikat, <?= (int)$last_import['invalid_rows'] ?> email tidak valid.
    <?php if ((int)$last_import['invalid_rows']): ?>Urutan baris data yang perlu diperiksa: <?= html_escape(implode(', ', json_decode($last_import['invalid_row_numbers'], true) ?: [])) ?>.<?php endif; ?>
  </div>
  <?php endif; ?>
  <details class="mt-3"><summary>Panduan klaim dan kasir</summary>
    <p class="mt-2">Bagikan <a href="<?= site_url('gowes-vol9') ?>"><?= html_escape(site_url('gowes-vol9')) ?></a>. Peserta memasukkan email pendaftaran dan menyimpan PNG voucher. Klaim ulang menampilkan voucher lama, termasuk jika sudah dipakai atau kedaluwarsa. Tidak membuat member baru.</p>
    <p>Kasir memasukkan <strong>kode unik 8 karakter</strong> pada input voucher POS. Kode lama yang lebih panjang tetap berlaku sesuai masa berlakunya. Jangan gunakan kode campaign. Voucher satu kali pakai; diskon 15% tanpa batas nominal potongan. Lihat hasilnya di menu <a href="<?= site_url('loyalty/vouchers') ?>">Voucher</a> dan <a href="<?= site_url('loyalty/voucher-usages') ?>">Pemakaian Voucher</a>.</p>
    <p class="mb-0">Sesuai alur email saja, tidak ada OTP: siapa pun yang mengetahui email terdaftar dapat melihat kode vouchernya. Jangan publikasikan daftar peserta. File unggahan tidak disimpan di direktori publik.</p>
  </details>
</div></div>
<div class="card"><div class="card-body">
  <form method="get" class="d-flex gap-2 mb-3"><input type="search" name="q" class="form-control" placeholder="Cari nama / email" aria-label="Cari peserta" value="<?= html_escape($search) ?>"><button class="btn btn-outline-primary">Cari</button></form>
  <div class="table-responsive"><table class="table table-striped table-bordered align-middle">
    <thead><tr><th>Nama peserta</th><th>Email</th><th>Kode voucher</th><th>Status</th><th>Diklaim</th><th>Berlaku sampai (WIB)</th></tr></thead>
    <tbody><?php foreach ($participants as $row): ?><tr>
      <td><?= html_escape($row['participant_name']) ?></td><td><?= html_escape($row['email']) ?></td>
      <td><?= html_escape($row['voucher_code'] ?? '-') ?></td><td><?= html_escape($row['voucher_status'] ?? 'Belum klaim') ?></td>
      <td><?= html_escape($row['claimed_at'] ?? '-') ?></td><td><?= html_escape($row['expired_at'] ?? '-') ?></td>
    </tr><?php endforeach; ?><?php if (!$participants): ?><tr><td colspan="6">Tidak ada peserta yang cocok.</td></tr><?php endif; ?></tbody>
  </table></div>
  <div class="d-flex justify-content-between align-items-center">
    <span>Halaman <?= (int)$page ?> &middot; Maksimal 50 peserta per halaman</span>
    <div><?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?<?= html_escape(http_build_query(['q' => $search, 'page' => $page - 1])) ?>">Sebelumnya</a><?php endif; ?>
    <?php if ($has_next): ?><a class="btn btn-sm btn-outline-secondary" href="?<?= html_escape(http_build_query(['q' => $search, 'page' => $page + 1])) ?>">Berikutnya</a><?php endif; ?></div>
  </div>
</div></div>
