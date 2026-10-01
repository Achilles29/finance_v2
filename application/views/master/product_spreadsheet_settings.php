<div class="mb-4">
  <a href="<?php echo site_url('master/product'); ?>" class="btn btn-outline-secondary btn-sm mb-3">&larr; Kembali ke Produk</a>
  <h4 class="mb-1">Pengaturan Spreadsheet Produk</h4>
  <div class="text-muted">Tentukan spreadsheet dan tab tujuan untuk ekspor dari master produk.</div>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success" role="status"><?php echo html_escape($success_message); ?></div>
<?php endif; ?>
<?php if (!empty($error_message)): ?>
<div class="alert alert-danger" role="alert"><?php echo html_escape($error_message); ?></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header"><strong>Tujuan Spreadsheet</strong></div>
      <div class="card-body">
        <form method="post" action="<?php echo site_url('master/product/spreadsheet/settings/save'); ?>">
          <input type="hidden" name="master_mutation_csrf" value="<?php echo html_escape($master_mutation_csrf_token ?? ''); ?>">
          <div class="mb-3">
            <label class="form-label" for="product-spreadsheet-url">Tautan Google Spreadsheet</label>
            <input class="form-control" type="url" id="product-spreadsheet-url" name="spreadsheet_url" required
              placeholder="https://docs.google.com/spreadsheets/d/.../edit#gid=123456"
              value="<?php echo html_escape($spreadsheet_url ?? ''); ?>">
            <div class="form-text">Pilih tab yang dituju sebelum menyalin tautan. Nilai gid pada tautan menentukan tab tujuan.</div>
          </div>
          <button class="btn btn-primary" type="submit">Simpan dan Tes Koneksi</button>
          <?php if (!empty($spreadsheet_url)): ?>
          <a class="btn btn-outline-secondary ms-2" href="<?php echo html_escape($spreadsheet_url); ?>" target="_blank" rel="noopener noreferrer">Buka Tab Saat Ini</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header"><strong>Panduan Pembaruan</strong></div>
      <div class="card-body">
        <ol class="mb-3 ps-3">
          <li>Buat atau pilih tab khusus untuk data produk, lalu salin tautannya saat tab itu terbuka.</li>
          <li>Tempel tautan di pengaturan ini dan tekan <strong>Simpan dan Tes Koneksi</strong>.</li>
          <li>Bagikan spreadsheet kepada alamat service account berikut dengan akses <strong>Editor</strong>:
            <div class="mt-1"><code><?php echo html_escape($service_account_email ?? 'Alamat service account belum tersedia'); ?></code></div>
          </li>
          <li>Kembali ke <strong>Master Produk</strong>, tekan <strong>Perbarui Spreadsheet</strong> setiap selesai mengubah data produk.</li>
        </ol>
        <div class="alert alert-warning mb-0" role="note">
          Pembaruan bersifat manual. Saat pertama kali diekspor, tab tujuan harus kosong. Setelah terhubung, isi tab data sistem akan diganti setiap pembaruan; simpan analisis manual di tab lain.
        </div>
      </div>
    </div>
  </div>
</div>
