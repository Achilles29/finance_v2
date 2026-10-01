<?php
$profile = is_array($profile ?? null) ? $profile : [];
$value = static function (string $key, string $fallback = '') use ($profile): string {
    return (string)($profile[$key] ?? $fallback);
};
$logoUrl = $value('logo_url');
$displayName = trim($value('display_name', 'Finance'));
$identityReady = $displayName !== '' && !in_array($displayName, ['Finance', 'Finance POS'], true);
$contactReady = trim($value('address')) !== '' || trim($value('phone')) !== '' || trim($value('email')) !== '';
$documentReady = $logoUrl !== '' || trim($value('document_footer')) !== '';
$setupDone = count(array_filter([$identityReady, $contactReady, $documentReady]));
$setupSteps = [
    ['number' => 1, 'title' => 'Nama usaha', 'desc' => 'Nama dagang, nama legal, dan NPWP.', 'anchor' => 'setup-identitas', 'ready' => $identityReady],
    ['number' => 2, 'title' => 'Kontak & lokalitas', 'desc' => 'Alamat, kontak, waktu, dan mata uang.', 'anchor' => 'setup-lokalitas', 'ready' => $contactReady],
    ['number' => 3, 'title' => 'Logo & dokumen', 'desc' => 'Logo utama serta footer cetak default.', 'anchor' => 'setup-dokumen', 'ready' => $documentReady],
];
?>
<style>
  .business-setup{border:1px solid var(--bs-border-color);border-radius:1rem;background:linear-gradient(135deg,rgba(105,40,56,.07),rgba(255,255,255,.95));padding:1rem}
  .business-setup-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}
  .business-setup-step{display:flex;gap:.7rem;min-width:0;padding:.82rem;border:1px solid var(--bs-border-color);border-radius:.8rem;background:#fff;color:inherit;text-decoration:none}
  .business-setup-step:hover{border-color:var(--bs-primary);color:inherit;box-shadow:0 .25rem .7rem rgba(53,24,31,.08)}
  .business-setup-number{flex:0 0 1.8rem;height:1.8rem;display:grid;place-items:center;border-radius:50%;background:#f4e7e9;color:#8d2535;font-size:.78rem;font-weight:800}
  .business-setup-step.is-ready .business-setup-number{background:#dff3e7;color:#146c43}
  .business-setup-step small{display:block;color:var(--bs-secondary-color);font-size:.74rem;line-height:1.35}
  .business-profile-section{scroll-margin-top:1.25rem}
  @media(max-width:767.98px){.business-setup-grid{grid-template-columns:1fr}.business-setup{padding:.8rem}}
</style>

<div class="container-fluid py-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h4 class="mb-1">Profil Usaha &amp; Tampilan</h4>
      <p class="text-muted mb-0">Identitas lokal customer untuk tampilan aplikasi dan fallback dokumen baru.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= site_url('pos/printers/general') ?>"><i class="ri-printer-line me-1"></i>Pengaturan Cetak POS</a>
  </div>

  <div class="business-setup mb-3" aria-labelledby="business-setup-title">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <div class="fw-semibold" id="business-setup-title"><i class="ri ri-rocket-2-line me-1"></i>Setup awal admin</div>
        <small class="text-muted">Selesaikan tiga langkah ini. Seluruhnya disimpan sekali melalui tombol di bagian bawah.</small>
      </div>
      <span class="badge <?= $setupDone === 3 ? 'bg-success' : 'bg-primary' ?> rounded-pill"><?= $setupDone ?>/3 langkah terisi</span>
    </div>
    <div class="business-setup-grid">
      <?php foreach ($setupSteps as $step): ?>
        <a class="business-setup-step <?= $step['ready'] ? 'is-ready' : '' ?>" href="#<?= html_escape($step['anchor']) ?>">
          <span class="business-setup-number"><?= $step['ready'] ? '<i class="ri ri-check-line"></i>' : (int)$step['number'] ?></span>
          <span><strong class="d-block small mb-1"><?= html_escape($step['title']) ?></strong><small><?= html_escape($step['desc']) ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="alert alert-info small">
    <i class="ri-information-line me-1"></i><strong>Dampak setelah disimpan:</strong> login, sidebar, footer, QR ulasan, label aset, dan kontrak memakai profil ini sebagai fallback. Nama/alamat outlet serta layout atau logo cetak POS yang sudah diisi tetap menjadi override dan tidak diubah otomatis.
  </div>

  <details class="card mb-3"><summary class="card-header fw-semibold">Pemeriksaan folder upload oleh server web</summary><div class="card-body">
    <p class="small text-muted">Diperiksa menggunakan akun PHP yang sedang melayani halaman ini, bukan akun root terminal. Pemeriksaan tidak membuat folder atau mengubah izin. Admin server menangani baris yang belum siap.</p>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Keperluan</th><th>Folder aplikasi</th><th>Status</th></tr></thead><tbody>
    <?php foreach ((array)($upload_storage ?? []) as $storage): ?><tr><td><?= html_escape($storage['label']) ?></td><td><code><?= html_escape($storage['path']) ?></code></td><td><span class="badge <?= $storage['status'] === 'READY' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= html_escape(['READY' => 'Siap', 'MISSING' => 'Belum dibuat', 'NOT_WRITABLE' => 'Tidak bisa ditulis', 'UNSAFE_PATH' => 'Lokasi perlu diperiksa'][$storage['status']] ?? 'Perlu diperiksa') ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div></details>
  <form method="post" enctype="multipart/form-data" class="row g-3" id="business-profile-form">
    <input type="hidden" name="business_profile_csrf" value="<?= html_escape((string)($profile_csrf ?? '')) ?>">

    <div class="col-12 col-xl-8 d-grid gap-3">
      <section class="card business-profile-section" id="setup-identitas">
        <div class="card-header d-flex align-items-center gap-2"><span class="badge bg-label-primary rounded-pill">1</span><span class="fw-semibold">Identitas usaha</span></div>
        <div class="card-body">
          <fieldset <?= !empty($can_edit) ? '' : 'disabled' ?>><div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="business-display-name">Nama dagang <span class="text-danger">*</span></label><input required id="business-display-name" maxlength="190" class="form-control" name="display_name" value="<?= html_escape($value('display_name', 'Finance')) ?>"><div class="form-text">Nama yang terlihat pada aplikasi dan dokumen baru.</div></div>
            <div class="col-md-6"><label class="form-label" for="business-legal-name">Nama legal</label><input id="business-legal-name" maxlength="190" class="form-control" name="legal_name" value="<?= html_escape($value('legal_name')) ?>"><div class="form-text">Gunakan nama badan usaha bila berbeda dari nama dagang.</div></div>
            <div class="col-md-6"><label class="form-label" for="business-short-name">Nama singkat</label><input id="business-short-name" maxlength="80" class="form-control" name="short_name" value="<?= html_escape($value('short_name')) ?>"><div class="form-text">Dipakai bila ruang di sidebar terbatas.</div></div>
            <div class="col-md-6"><label class="form-label" for="business-tax-id">NPWP / ID pajak</label><input id="business-tax-id" maxlength="100" class="form-control" name="tax_id" value="<?= html_escape($value('tax_id')) ?>"></div>
          </div></fieldset>
        </div>
      </section>

      <section class="card business-profile-section" id="setup-lokalitas">
        <div class="card-header d-flex align-items-center gap-2"><span class="badge bg-label-primary rounded-pill">2</span><span class="fw-semibold">Kontak, lokasi, dan standar lokal</span></div>
        <div class="card-body">
          <fieldset <?= !empty($can_edit) ? '' : 'disabled' ?>><div class="row g-3">
            <div class="col-12"><label class="form-label" for="business-address">Alamat</label><textarea id="business-address" maxlength="4000" rows="3" class="form-control" name="address"><?= html_escape($value('address')) ?></textarea></div>
            <div class="col-md-4"><label class="form-label" for="business-phone">Telepon</label><input id="business-phone" maxlength="50" class="form-control" name="phone" value="<?= html_escape($value('phone')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="business-email">Email</label><input id="business-email" type="email" maxlength="190" class="form-control" name="email" value="<?= html_escape($value('email')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="business-website">Website</label><input id="business-website" type="url" maxlength="255" placeholder="https://usahaanda.id" class="form-control" name="website_url" value="<?= html_escape($value('website_url')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="business-timezone">Zona waktu</label><select id="business-timezone" class="form-select" name="timezone"><?php foreach ((array)($timezones ?? []) as $timezone): ?><option value="<?= html_escape($timezone) ?>" <?= $value('timezone', 'Asia/Jakarta') === $timezone ? 'selected' : '' ?>><?= html_escape($timezone) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label" for="business-locale">Locale</label><input id="business-locale" pattern="[a-z]{2}_[A-Z]{2}" maxlength="20" class="form-control" name="locale" value="<?= html_escape($value('locale', 'id_ID')) ?>"><div class="form-text">Contoh: id_ID.</div></div>
            <div class="col-md-4"><label class="form-label" for="business-currency">Mata uang</label><input id="business-currency" pattern="[A-Z]{3}" maxlength="3" class="form-control text-uppercase" name="currency_code" value="<?= html_escape($value('currency_code', 'IDR')) ?>"></div>
          </div></fieldset>
        </div>
      </section>

      <section class="card business-profile-section" id="setup-dokumen">
        <div class="card-header d-flex align-items-center gap-2"><span class="badge bg-label-primary rounded-pill">3</span><span class="fw-semibold">Dokumen &amp; cetak default</span></div>
        <div class="card-body">
          <fieldset <?= !empty($can_edit) ? '' : 'disabled' ?>><label class="form-label" for="business-document-footer">Footer dokumen default</label><textarea id="business-document-footer" maxlength="500" rows="2" class="form-control" name="document_footer" placeholder="Contoh: Terima kasih atas kepercayaan Anda."><?= html_escape($value('document_footer')) ?></textarea><div class="form-text">Menjadi fallback footer dokumen/struk baru hanya bila template atau pengaturan cetak belum menentukan footer sendiri.</div></fieldset>
        </div>
      </section>
    </div>

    <div class="col-12 col-xl-4">
      <section class="card h-100 business-profile-section" aria-labelledby="business-logo-title">
        <div class="card-header fw-semibold" id="business-logo-title">Logo usaha</div>
        <div class="card-body">
          <fieldset <?= !empty($can_edit) ? '' : 'disabled' ?>><div class="border rounded p-3 text-center bg-light mb-3" style="min-height:180px"><img id="business-profile-logo-preview" src="<?= html_escape($logoUrl) ?>" alt="Preview logo usaha" class="img-fluid <?= $logoUrl === '' ? 'd-none' : '' ?>" style="max-height:150px"><div id="business-profile-logo-empty" class="text-muted small <?= $logoUrl !== '' ? 'd-none' : '' ?>">Belum ada logo usaha.</div></div>
            <label class="form-label" for="business-profile-logo-file">Unggah logo baru</label><input type="file" class="form-control" name="logo_file" id="business-profile-logo-file" accept="image/png,image/jpeg"><div class="form-text">PNG/JPG, maksimal 1 MB dan 2048 × 2048 piksel. Preview berubah sebelum disimpan. File lama tidak dihapus otomatis.</div>
            <?php if ($logoUrl !== ''): ?><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove-business-logo"><label class="form-check-label" for="remove-business-logo">Hapus penggunaan logo utama ini</label></div><?php endif; ?>
          </fieldset><hr><p class="small text-muted mb-0">Logo ini tampil pada login dan sidebar. Printer memakai logo ini hanya bila pengaturan cetak tidak mempunyai logo sendiri.</p>
        </div>
      </section>
    </div>

    <div class="col-12"><section class="card"><div class="card-header fw-semibold">Menu Book publik</div><div class="card-body">
      <fieldset <?= !empty($can_edit) ? '' : 'disabled' ?>>
        <label for="menu-book-template" class="form-label">Template yang dilihat pengunjung</label>
        <select id="menu-book-template" name="menu_book_template" class="form-select">
          <?php foreach (['legacy_namua' => 'Desain Namua lama — khusus instalasi lama', 'customer' => 'Katalog usaha — mengikuti profil dan produk pilihan', 'disabled' => 'Tidak dipublikasikan'] as $key => $label): ?>
            <?php if ($key === 'legacy_namua' && !Customer_publication::legacy_available()) continue; ?>
            <option value="<?= html_escape($key) ?>" <?= ($menu_book_template ?? 'legacy_namua') === $key ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="form-text mb-0">Untuk customer baru pilih Katalog usaha atau Tidak dipublikasikan. Katalog hanya menampilkan produk aktif yang dipilih pada Landing Page → Menu, dengan harga jual master; tidak menampilkan stok, HPP, atau transaksi. Mengganti template tidak menghapus desain lama atau mengubah produk.</p>
      </fieldset>
      <div class="d-flex gap-2 flex-wrap mt-3"><a href="<?= site_url('landing-page?tab=menu') ?>" class="btn btn-outline-secondary btn-sm">Pilih produk publik</a><a href="<?= site_url('menu_book') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Lihat Menu Book tersimpan</a></div>
    </div></section></div>
    <?php if (!empty($can_edit)): ?><div class="col-12 d-flex flex-wrap align-items-center gap-2"><button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i>Simpan Pengaturan Usaha</button><span class="small text-muted">Perubahan berlaku untuk tampilan baru setelah halaman dimuat ulang.</span></div><?php endif; ?>
  </form>
</div>
<script>
(() => {
  const input = document.getElementById('business-profile-logo-file');
  const image = document.getElementById('business-profile-logo-preview');
  const empty = document.getElementById('business-profile-logo-empty');
  if (!input || !image || !empty) return;
  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = event => {
      image.src = String(event.target.result || '');
      image.classList.remove('d-none');
      empty.classList.add('d-none');
    };
    reader.readAsDataURL(file);
  });
})();
</script>
