<?php
$tab        = $tab        ?? 'config';
$cfg        = $cfg        ?? [];
$menus      = $menus      ?? [];
$available_products = $available_products ?? [];
$product_categories = $product_categories ?? [];
$galleries  = $galleries  ?? [];
$embeds     = $embeds     ?? [];
$links      = $links      ?? [];
$landingPageMutationCsrf = $landing_page_mutation_csrf ?? '';
$businessProfile = is_array($business_profile ?? null) ? $business_profile : [];
$landingBusinessName = trim((string)($businessProfile['display_name'] ?? ''));
$landingBusinessName = $landingBusinessName !== '' ? $landingBusinessName : 'Usaha Anda';

$configuredLandingUrl = trim((string)($cfg['seo_canonical_url'] ?? ''));
$landingUrl = filter_var($configuredLandingUrl, FILTER_VALIDATE_URL) ? $configuredLandingUrl : base_url();
$defaultSeoTitle = $landingBusinessName . ' | Informasi & Pemesanan';
$defaultSeoDescription = 'Informasi, menu, dan layanan dari ' . $landingBusinessName . '.';
$menuCategoryIds = array_filter(array_map('intval', explode(',', (string)($cfg['menu_kategori_ids'] ?? ''))));
$galleryCategoryIds = array_filter(array_map('intval', explode(',', (string)($cfg['gallery_kategori_ids'] ?? ''))));

$baseUrl    = site_url('landing-page');
$cfgUrl     = site_url('landing-page/config/update');
$menuUrl    = site_url('landing-page/menu');
$galleryUrl = site_url('landing-page/gallery');
$embedUrl   = site_url('landing-page/embed');
$linkUrl    = site_url('landing-page/links');

// Helpers
function lp_img(string $src, string $alt = '', string $style = 'width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6'): string {
    if ($src === '') return '<span class="text-muted small">—</span>';
    $full = preg_match('#^https?://#i', $src) ? $src : base_url(ltrim($src, '/'));
    return '<img src="' . html_escape($full) . '" alt="' . html_escape($alt) . '" style="' . $style . '">';
}
function lp_badge(int $active): string {
    return $active
        ? '<span class="badge bg-success">Aktif</span>'
        : '<span class="badge bg-secondary">Nonaktif</span>';
}
?>
<style>
  .lp-tab-bar .nav-link { color: #6c757d; font-size: 0.9rem; }
  .lp-tab-bar .nav-link.active { color: #8d0f1d; border-color: #dee2e6 #dee2e6 #fff; }
  .lp-section-title { font-size: 0.72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #8d0f1d; margin-bottom: .75rem; }
  .lp-reorder-handle { cursor: grab; color: #adb5bd; }
  .lp-sort-row.is-dragging { opacity: .5; }
  .lp-sort-row.drag-over-top    { box-shadow: inset 0  3px 0 #c0392b; }
  .lp-sort-row.drag-over-bottom { box-shadow: inset 0 -3px 0 #c0392b; }
  .lp-cfg-card { border: 0; box-shadow: 0 .15rem .6rem rgba(80,40,30,.09); margin-bottom: 1.25rem; }
  .lp-cfg-card .card-header { background: #fdf9f5; border-bottom: 1px solid #f0e6da; font-weight: 600; font-size: .88rem; }
  .lp-embed-preview { max-height: 60px; overflow: hidden; font-size: .78rem; color: #6c757d; background: #f8f9fa; border-radius: 6px; padding: .35rem .6rem; font-family: monospace; }
  .lp-source-lock { background: #f4fbf7; border: 1px solid #cfe9da; border-radius: .65rem; padding: .8rem .9rem; }
  .lp-product-thumb { background: #f6f1eb; }
  .lp-category-select { min-height: 150px; }
</style>

<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
  <div>
    <h4 class="mb-1">Pengaturan Landing Page</h4>
    <small class="text-muted">Kelola konten halaman utama <?= html_escape($landingBusinessName) ?>.</small>
  </div>
  <a href="<?= html_escape($landingUrl) ?>" target="_blank" rel="noreferrer" class="btn btn-outline-secondary btn-sm">
    <i class="ri ri-external-link-line me-1"></i>Lihat Landing Page
  </a>
</div>

<div class="alert alert-info py-2 small"><i class="ri-information-line me-1"></i>Nama usaha diambil dari <a class="alert-link" href="<?= site_url('system/business-profile') ?>">Profil Usaha</a>. Konten, URL publik, SEO, dan gambar landing page tetap Anda atur di halaman ini agar tidak menimpa materi promosi yang sudah ada.</div>

<!-- Tab nav -->
<ul class="nav nav-tabs lp-tab-bar mb-0">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'config'  ? 'active' : '' ?>" href="<?= $baseUrl ?>?tab=config">
      <i class="ri ri-settings-3-line me-1"></i>Pengaturan Umum
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'menu'    ? 'active' : '' ?>" href="<?= $baseUrl ?>?tab=menu">
      <i class="ri ri-restaurant-2-line me-1"></i>Menu
      <span class="badge bg-secondary ms-1"><?= count($menus) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'gallery' ? 'active' : '' ?>" href="<?= $baseUrl ?>?tab=gallery">
      <i class="ri ri-image-2-line me-1"></i>Gallery
      <span class="badge bg-secondary ms-1"><?= count($galleries) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'links'   ? 'active' : '' ?>" href="<?= $baseUrl ?>?tab=links">
      <i class="ri ri-links-line me-1"></i>Halaman Links
      <span class="badge bg-secondary ms-1"><?= count($links) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'embed'   ? 'active' : '' ?>" href="<?= $baseUrl ?>?tab=embed">
      <i class="ri ri-instagram-line me-1"></i>Instagram Embed
      <span class="badge bg-secondary ms-1"><?= count($embeds) ?></span>
    </a>
  </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom p-3 bg-white">

<!-- ═══════════════════ TAB CONFIG ═══════════════════ -->
<?php if ($tab === 'config'): ?>
<form method="post" action="<?= $cfgUrl ?>">
  <input type="hidden" name="landing_page_mutation_csrf" value="<?= html_escape($landingPageMutationCsrf) ?>">

  <div class="row g-3">
    <div class="col-lg-8">

      <!-- Hero -->
      <div class="card lp-cfg-card">
        <div class="card-header"><i class="ri ri-image-line me-1"></i>Hero Section</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Judul Utama</label>
            <input type="text" name="hero_title" class="form-control" value="<?= html_escape($cfg['hero_title'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Subjudul / Lead</label>
            <textarea name="hero_subtitle" class="form-control" rows="2"><?= html_escape($cfg['hero_subtitle'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Badge Pills <small class="text-muted">(satu per baris)</small></label>
            <textarea name="hero_badges_raw" class="form-control font-monospace" rows="3"><?= html_escape(implode("\n", (array)($cfg['hero_badges'] ?? []))) ?></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label">Gambar Hero <small class="text-muted">(URL atau path relatif)</small></label>
            <input type="text" name="hero_image" class="form-control" value="<?= html_escape($cfg['hero_image'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- About -->
      <div class="card lp-cfg-card">
        <div class="card-header"><i class="ri ri-store-2-line me-1"></i>Tentang Kami</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Judul About</label>
            <input type="text" name="about_title" class="form-control" value="<?= html_escape($cfg['about_title'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea name="about_text" class="form-control" rows="3"><?= html_escape($cfg['about_text'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Poin-Poin Keunggulan <small class="text-muted">(satu per baris)</small></label>
            <textarea name="about_points_raw" class="form-control font-monospace" rows="3"><?= html_escape(implode("\n", (array)($cfg['about_points'] ?? []))) ?></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label">Gambar About</label>
            <input type="text" name="about_image" class="form-control" value="<?= html_escape($cfg['about_image'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- CTA & Footer -->
      <div class="card lp-cfg-card">
        <div class="card-header"><i class="ri ri-megaphone-line me-1"></i>CTA &amp; Footer</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Judul CTA</label>
              <input type="text" name="cta_title" class="form-control" value="<?= html_escape($cfg['cta_title'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Teks Footer</label>
              <input type="text" name="footer_text" class="form-control" value="<?= html_escape($cfg['footer_text'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Teks CTA</label>
              <textarea name="cta_text" class="form-control" rows="2"><?= html_escape($cfg['cta_text'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /col-lg-8 -->

    <div class="col-lg-4">

      <!-- SEO -->
      <div class="card lp-cfg-card">
        <div class="card-header"><i class="ri ri-search-eye-line me-1"></i>SEO &amp; Social Preview</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">SEO Title</label>
            <input type="text" name="seo_title" id="seoTitle" class="form-control" maxlength="255" value="<?= html_escape($cfg['seo_title'] ?? $defaultSeoTitle) ?>">
            <div class="form-text"><span id="seoTitleCount">0</span> karakter. Buat spesifik dan mudah dipahami.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Meta Description</label>
            <textarea name="seo_description" id="seoDescription" class="form-control" rows="3" maxlength="320"><?= html_escape($cfg['seo_description'] ?? $defaultSeoDescription) ?></textarea>
            <div class="form-text"><span id="seoDescriptionCount">0</span> karakter. Ringkas isi halaman untuk hasil pencarian.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Canonical URL</label>
            <input type="url" name="seo_canonical_url" class="form-control form-control-sm" value="<?= html_escape($cfg['seo_canonical_url'] ?? base_url()) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Share Image <small class="text-muted">(URL/path)</small></label>
            <input type="text" name="seo_share_image" class="form-control form-control-sm" value="<?= html_escape($cfg['seo_share_image'] ?? 'assets/hero/americano_hot.jpg') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Indexing</label>
            <select name="seo_indexing" class="form-select form-select-sm">
              <option value="index" <?= ($cfg['seo_indexing'] ?? 'index') === 'index' ? 'selected' : '' ?>>Index, follow</option>
              <option value="noindex" <?= ($cfg['seo_indexing'] ?? '') === 'noindex' ? 'selected' : '' ?>>Noindex (sembunyikan dari mesin pencari)</option>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label">Google Site Verification <small class="text-muted">(opsional)</small></label>
            <input type="text" name="seo_google_verification" class="form-control form-control-sm" placeholder="Kode content dari Search Console" value="<?= html_escape($cfg['seo_google_verification'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Kontak -->
      <div class="card lp-cfg-card">
        <div class="card-header"><i class="ri ri-map-pin-line me-1"></i>Kontak &amp; Lokasi</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Alamat</label>
            <textarea name="address" class="form-control" rows="2"><?= html_escape($cfg['address'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">No. Telepon</label>
            <input type="text" name="phone" class="form-control" value="<?= html_escape($cfg['phone'] ?? '') ?>">
          </div>
          <div class="mb-0">
            <label class="form-label">WhatsApp <small class="text-muted">(format intl)</small></label>
            <input type="text" name="whatsapp" class="form-control" placeholder="6285150737377" value="<?= html_escape($cfg['whatsapp'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- URL -->
      <div class="card lp-cfg-card">
        <div class="card-header"><i class="ri ri-link-m me-1"></i>URL Tautan</div>
        <div class="card-body">
          <?php foreach ([
            'order_url'    => 'Order URL',
            'member_url'   => 'Member App URL',
            'instagram_url'=> 'Instagram',
            'linktree_url' => 'Linktree',
            'map_url'      => 'Google Maps',
          ] as $field => $label): ?>
          <div class="mb-2">
            <label class="form-label mb-1 small"><?= html_escape($label) ?></label>
            <input type="text" name="<?= $field ?>" class="form-control form-control-sm" value="<?= html_escape($cfg[$field] ?? '') ?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Sumber Data -->
      <div class="card lp-cfg-card">
        <div class="card-header"><i class="ri ri-database-2-line me-1"></i>Sumber Data</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label mb-1 small fw-semibold">Sumber Menu</label>
            <input type="hidden" name="menu_source" value="produk">
            <div class="lp-source-lock small">
              <div class="fw-semibold text-success mb-1"><i class="ri ri-checkbox-circle-fill me-1"></i>Sinkron langsung dengan mst_product</div>
              <span class="text-muted">Landing membaca nama, foto, deskripsi, kategori, harga, status, dan flag <code>show_landing</code> tanpa menyalin data.</span>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label mb-1 small">Limit Menu</label>
              <input type="number" name="menu_limit" class="form-control form-control-sm" min="4" max="20" value="<?= (int)($cfg['menu_limit'] ?? 8) ?>">
            </div>
            <div class="col-6">
              <label class="form-label mb-1 small">Top Best Seller</label>
              <input type="number" name="menu_best_seller_top" class="form-control form-control-sm" min="1" max="10" value="<?= (int)($cfg['menu_best_seller_top'] ?? 3) ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label mb-1 small">Filter Kategori Menu</label>
            <select name="menu_kategori_ids[]" class="form-select form-select-sm lp-category-select" multiple>
              <?php foreach ($product_categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= in_array((int)$category['id'], $menuCategoryIds, true) ? 'selected' : '' ?>>
                  <?= html_escape($category['name']) ?> (<?= (int)$category['landing_count'] ?> tampil)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Kosongkan pilihan untuk menampilkan semua kategori.</div>
          </div>
          <hr class="my-2">
          <div class="mb-3">
            <label class="form-label mb-1 small fw-semibold">Sumber Gallery</label>
            <select name="gallery_source" class="form-select form-select-sm">
              <option value="manual" <?= ($cfg['gallery_source'] ?? 'manual') === 'manual' ? 'selected' : '' ?>>Manual (tabel lp_gallery)</option>
              <option value="produk" <?= ($cfg['gallery_source'] ?? '') === 'produk'  ? 'selected' : '' ?>>Foto mst_product</option>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label mb-1 small">Limit Gallery</label>
              <input type="number" name="gallery_limit" class="form-control form-control-sm" min="4" max="12" value="<?= (int)($cfg['gallery_limit'] ?? 6) ?>">
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label mb-1 small">Filter Kategori Gallery</label>
            <select name="gallery_kategori_ids[]" class="form-select form-select-sm lp-category-select" multiple>
              <?php foreach ($product_categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= in_array((int)$category['id'], $galleryCategoryIds, true) ? 'selected' : '' ?>>
                  <?= html_escape($category['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Dipakai hanya jika sumber gallery adalah foto mst_product.</div>
          </div>
        </div>
      </div>

    </div><!-- /col-lg-4 -->
  </div><!-- /row -->

  <div class="d-flex justify-content-end gap-2 mt-2">
    <button type="submit" class="btn btn-primary">
      <i class="ri ri-save-line me-1"></i>Simpan Pengaturan
    </button>
  </div>
</form>

<!-- ═══════════════════ TAB MENU ═══════════════════ -->
<?php elseif ($tab === 'menu'): ?>
<div class="alert alert-success border-success-subtle d-flex gap-2 align-items-start py-2" role="status">
  <i class="ri ri-database-2-line mt-1"></i>
  <div class="small">
    <strong>Sumber tunggal: mst_product.</strong>
    Landing menampilkan maksimal <?= (int)($cfg['menu_limit'] ?? 8) ?> produk dan mengurutkannya dari penjualan 90 hari. Perubahan nama, harga, foto, atau deskripsi dilakukan di Master Produk dan langsung ikut tampil.
  </div>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <small class="text-muted"><?= count($menus) ?> produk ditandai <code>show_landing = 1</code> &nbsp;·&nbsp; <?= count($available_products) ?> produk aktif tersedia</small>
  <button class="btn btn-primary btn-sm" data-lp-open-modal="menu" data-lp-mode="add">
    <i class="ri ri-add-line me-1"></i>Pilih Produk
  </button>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="menuTable">
      <thead class="table-light">
        <tr>
          <th width="44">No</th>
          <th width="72">Foto</th>
          <th>Produk</th>
          <th>Kategori</th>
          <th width="120">Harga</th>
          <th width="110">Terjual 90 hari</th>
          <th width="110">Kelengkapan</th>
          <th width="120">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($menus)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Belum ada produk yang ditandai tampil di landing page.</td></tr>
        <?php else: ?>
        <?php foreach ($menus as $i => $m): ?>
        <tr>
          <td class="text-muted small"><?= $i + 1 ?></td>
          <td><?= lp_img((string)($m['image'] ?? ''), html_escape($m['title'])) ?></td>
          <td>
            <div class="fw-semibold"><?= html_escape($m['title']) ?></div>
            <small class="text-muted"><?= html_escape($m['product_code']) ?> &middot; ID <?= (int)$m['id'] ?></small>
          </td>
          <td><span class="badge bg-light text-dark border"><?= html_escape($m['category_name'] ?? 'Tanpa kategori') ?></span></td>
          <td class="small"><?= $m['price'] !== null ? 'Rp '.number_format((float)$m['price'],0,',','.') : '<span class="text-muted">—</span>' ?></td>
          <td class="text-center fw-semibold"><?= number_format((float)($m['sales_qty'] ?? 0), 0, ',', '.') ?></td>
          <td class="small">
            <?php if (!empty($m['image']) && !empty($m['description'])): ?>
              <span class="badge bg-success">Lengkap</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark"><?= empty($m['image']) ? 'Foto' : 'Deskripsi' ?> belum ada</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a class="btn btn-sm btn-outline-primary action-icon-btn" title="Edit di Master Produk"
                href="<?= site_url('master/product/edit/' . (int)$m['id']) ?>">
                <i class="ri ri-edit-line"></i>
              </a>
              <button class="btn btn-sm btn-outline-danger action-icon-btn" title="Sembunyikan dari landing"
                data-lp-delete="menu" data-id="<?= (int)$m['id'] ?>" data-name="<?= html_escape($m['title']) ?>">
                <i class="ri ri-eye-off-line"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══════════════════ TAB GALLERY ═══════════════════ -->
<?php elseif ($tab === 'gallery'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <small class="text-muted">Total: <?= count($galleries) ?> foto &nbsp;·&nbsp; Drag baris untuk atur urutan</small>
  <button class="btn btn-primary btn-sm" data-lp-open-modal="gallery" data-lp-mode="add">
    <i class="ri ri-add-line me-1"></i>Tambah Foto
  </button>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="galleryTable">
      <thead class="table-light">
        <tr>
          <th width="36"></th>
          <th width="44">No</th>
          <th width="72">Preview</th>
          <th>Caption</th>
          <th width="90">Status</th>
          <th width="110">Aksi</th>
        </tr>
      </thead>
      <tbody id="galleryTbody">
        <?php if (empty($galleries)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada foto gallery.</td></tr>
        <?php else: ?>
        <?php foreach ($galleries as $i => $g): ?>
        <tr class="lp-sort-row" draggable="true" data-row-id="<?= (int)$g['id'] ?>">
          <td class="lp-reorder-handle text-center"><i class="ri ri-drag-move-2-line"></i></td>
          <td class="text-muted small"><?= $i + 1 ?></td>
          <td><?= lp_img((string)($g['image'] ?? ''), html_escape($g['caption'] ?? '')) ?></td>
          <td><?= html_escape($g['caption'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
          <td>
            <button class="btn btn-sm <?= (int)$g['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
              data-lp-toggle="gallery" data-id="<?= (int)$g['id'] ?>" style="min-width:70px">
              <?= (int)$g['is_active'] ? 'Aktif' : 'Nonaktif' ?>
            </button>
          </td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-primary action-icon-btn" title="Edit"
                data-lp-open-modal="gallery" data-lp-mode="edit"
                data-id="<?= (int)$g['id'] ?>"
                data-image="<?= html_escape($g['image'] ?? '') ?>"
                data-caption="<?= html_escape($g['caption'] ?? '') ?>"
                data-is_active="<?= (int)$g['is_active'] ?>">
                <i class="ri ri-edit-line"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger action-icon-btn" title="Hapus"
                data-lp-delete="gallery" data-id="<?= (int)$g['id'] ?>" data-name="<?= html_escape($g['caption'] ?? 'foto ini') ?>">
                <i class="ri ri-delete-bin-line"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══════════════════ TAB LINKS ═══════════════════ -->
<?php elseif ($tab === 'links'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <small class="text-muted">
    Total: <?= count($links) ?> link &nbsp;·&nbsp; Drag untuk atur urutan &nbsp;·&nbsp;
    <a href="<?= html_escape($landingUrl . 'links.php') ?>" target="_blank" rel="noreferrer" class="text-decoration-none">
      <i class="ri ri-external-link-line"></i> Preview halaman
    </a>
  </small>
  <button class="btn btn-primary btn-sm" data-lp-open-modal="link" data-lp-mode="add">
    <i class="ri ri-add-line me-1"></i>Tambah Link
  </button>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th width="36"></th>
          <th width="44">No</th>
          <th width="60">Ikon</th>
          <th>Label</th>
          <th>URL</th>
          <th width="90">Status</th>
          <th width="110">Aksi</th>
        </tr>
      </thead>
      <tbody id="linksTbody">
        <?php if (empty($links)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada link.</td></tr>
        <?php else: ?>
        <?php foreach ($links as $i => $lnk): ?>
        <tr class="lp-sort-row" draggable="true" data-row-id="<?= (int)$lnk['id'] ?>">
          <td class="lp-reorder-handle text-center"><i class="ri ri-drag-move-2-line"></i></td>
          <td class="text-muted small"><?= $i + 1 ?></td>
          <td class="text-center" style="font-size:1.4rem"><?= html_escape($lnk['icon'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
          <td class="fw-semibold"><?= html_escape($lnk['label']) ?></td>
          <td class="small text-muted" style="max-width:260px;word-break:break-all"><?= html_escape($lnk['url']) ?></td>
          <td>
            <button class="btn btn-sm <?= (int)$lnk['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
              data-lp-toggle="link" data-id="<?= (int)$lnk['id'] ?>" style="min-width:70px">
              <?= (int)$lnk['is_active'] ? 'Aktif' : 'Nonaktif' ?>
            </button>
          </td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-primary action-icon-btn" title="Edit"
                data-lp-open-modal="link" data-lp-mode="edit"
                data-id="<?= (int)$lnk['id'] ?>"
                data-label="<?= html_escape($lnk['label']) ?>"
                data-url="<?= html_escape($lnk['url']) ?>"
                data-icon="<?= html_escape($lnk['icon'] ?? '') ?>"
                data-is_active="<?= (int)$lnk['is_active'] ?>">
                <i class="ri ri-edit-line"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger action-icon-btn" title="Hapus"
                data-lp-delete="link" data-id="<?= (int)$lnk['id'] ?>" data-name="<?= html_escape($lnk['label']) ?>">
                <i class="ri ri-delete-bin-line"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══════════════════ TAB EMBED ═══════════════════ -->
<?php elseif ($tab === 'embed'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <small class="text-muted">Total: <?= count($embeds) ?> embed</small>
  <button class="btn btn-primary btn-sm" data-lp-open-modal="embed" data-lp-mode="add">
    <i class="ri ri-add-line me-1"></i>Tambah Embed
  </button>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th width="44">No</th>
          <th width="90">Tipe</th>
          <th>Preview Kode</th>
          <th width="90">Status</th>
          <th width="110">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($embeds)): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada embed Instagram.</td></tr>
        <?php else: ?>
        <?php foreach ($embeds as $i => $e): ?>
        <tr>
          <td class="text-muted small"><?= $i + 1 ?></td>
          <td>
            <span class="badge <?= $e['embed_type'] === 'reel' ? 'bg-primary' : 'bg-info text-dark' ?>">
              <?= $e['embed_type'] === 'reel' ? 'Reel' : 'Foto' ?>
            </span>
          </td>
          <td><div class="lp-embed-preview"><?= html_escape(mb_strimwidth((string)$e['embed_html'], 0, 120, '...')) ?></div></td>
          <td>
            <button class="btn btn-sm <?= (int)$e['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
              data-lp-toggle="embed" data-id="<?= (int)$e['id'] ?>" style="min-width:70px">
              <?= (int)$e['is_active'] ? 'Aktif' : 'Nonaktif' ?>
            </button>
          </td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-primary action-icon-btn" title="Edit"
                data-lp-open-modal="embed" data-lp-mode="edit"
                data-id="<?= (int)$e['id'] ?>"
                data-embed_type="<?= html_escape($e['embed_type']) ?>"
                data-embed_html="<?= html_escape($e['embed_html']) ?>"
                data-is_active="<?= (int)$e['is_active'] ?>">
                <i class="ri ri-edit-line"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger action-icon-btn" title="Hapus"
                data-lp-delete="embed" data-id="<?= (int)$e['id'] ?>" data-name="embed #<?= (int)$e['id'] ?>">
                <i class="ri ri-delete-bin-line"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div><!-- /tab-content -->

<!-- ══════════════ MODAL: MENU ══════════════ -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="menuModalTitle">Pilih Produk mst_product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="productSearch">Cari produk</label>
          <input type="search" id="productSearch" class="form-control" placeholder="Ketik nama, kode, atau kategori..." autocomplete="off">
        </div>
        <label class="form-label" for="menuProductId">Produk aktif yang belum tampil <span class="text-danger">*</span></label>
        <select id="menuProductId" class="form-select" size="11" <?= empty($available_products) ? 'disabled' : '' ?>>
          <?php foreach ($available_products as $product): ?>
            <?php $searchText = strtolower(trim(($product['product_code'] ?? '') . ' ' . ($product['product_name'] ?? '') . ' ' . ($product['category_name'] ?? ''))); ?>
            <option value="<?= (int)$product['id'] ?>" data-search="<?= html_escape($searchText) ?>">
              [<?= html_escape($product['category_name'] ?? 'Tanpa kategori') ?>] <?= html_escape($product['product_name']) ?> &middot; Rp <?= number_format((float)$product['selling_price'], 0, ',', '.') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($available_products)): ?>
          <div class="alert alert-info mt-3 mb-0 small">Semua produk aktif sudah ditandai tampil.</div>
        <?php else: ?>
          <div class="form-text">Data produk tidak disalin. Tombol ini hanya mengaktifkan <code>show_landing</code>.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="menuSaveBtn" <?= empty($available_products) ? 'disabled' : '' ?>>Tampilkan Produk</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════ MODAL: GALLERY ══════════════ -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="galleryModalTitle">Tambah Foto Gallery</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="galleryId">
        <div class="mb-3">
          <label class="form-label">URL / Path Gambar <span class="text-danger">*</span></label>
          <input type="text" id="galleryImage" class="form-control" placeholder="assets/gallery/foto.jpg">
        </div>
        <div class="mb-3">
          <label class="form-label">Caption</label>
          <input type="text" id="galleryCaption" class="form-control" placeholder="Suasana kedai">
        </div>
        <div class="mb-0">
          <label class="form-label">Status</label>
          <select id="galleryIsActive" class="form-select">
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="gallerySaveBtn">Simpan</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════ MODAL: EMBED ══════════════ -->
<div class="modal fade" id="embedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="embedModalTitle">Tambah Embed Instagram</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="embedId">
        <div class="mb-3">
          <label class="form-label">Tipe</label>
          <select id="embedType" class="form-select">
            <option value="reel">Reel</option>
            <option value="photo">Foto</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Kode HTML Embed <span class="text-danger">*</span></label>
          <textarea id="embedHtml" class="form-control font-monospace" rows="6" placeholder="Paste kode <blockquote> dari Instagram..."></textarea>
          <div class="form-text">Salin dari tombol "Embed" di postingan Instagram.</div>
        </div>
        <div class="mb-0">
          <label class="form-label">Status</label>
          <select id="embedIsActive" class="form-select">
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="embedSaveBtn">Simpan</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════ MODAL: LINK ══════════════ -->
<div class="modal fade" id="linkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="linkModalTitle">Tambah Link</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="linkId">
        <div class="mb-3">
          <label class="form-label">Ikon <small class="text-muted">(emoji, misal: ☕ 🏪 💬 🛵)</small></label>
          <input type="text" id="linkIcon" class="form-control" placeholder="☕" maxlength="10" style="font-size:1.4rem">
        </div>
        <div class="mb-3">
          <label class="form-label">Label Tombol <span class="text-danger">*</span></label>
          <input type="text" id="linkLabel" class="form-control" placeholder="MENU BOOK">
        </div>
        <div class="mb-3">
          <label class="form-label">URL Tujuan <span class="text-danger">*</span></label>
          <input type="url" id="linkUrl" class="form-control" placeholder="https://...">
        </div>
        <div class="mb-0">
          <label class="form-label">Status</label>
          <select id="linkIsActive" class="form-select">
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="linkSaveBtn">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var MENU_STORE   = <?= json_encode(site_url('landing-page/menu/store')) ?>;
  var MENU_URL     = <?= json_encode(site_url('landing-page/menu')) ?>;
  var GALLERY_STORE= <?= json_encode(site_url('landing-page/gallery/store')) ?>;
  var GALLERY_URL  = <?= json_encode(site_url('landing-page/gallery')) ?>;
  var EMBED_STORE  = <?= json_encode(site_url('landing-page/embed/store')) ?>;
  var LINK_STORE   = <?= json_encode(site_url('landing-page/links/store')) ?>;
  var LINK_URL     = <?= json_encode(site_url('landing-page/links')) ?>;
  var EMBED_URL    = <?= json_encode(site_url('landing-page/embed')) ?>;
  var TAB          = <?= json_encode($tab) ?>;
  var LANDING_PAGE_MUTATION_CSRF = <?= json_encode($landingPageMutationCsrf) ?>;

  // ── Helpers ────────────────────────────────────────────────────────
  function req(url, options) {
    var request = options || {};
    var headers = Object.assign({
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      'X-Landing-Page-Csrf': LANDING_PAGE_MUTATION_CSRF
    }, request.headers || {});
    return fetch(url, Object.assign({}, request, { headers: headers }))
      .then(function (r) { return r.json(); });
  }

  function formData(obj) {
    var fd = new FormData();
    Object.keys(obj).forEach(function (k) { if (obj[k] !== null && obj[k] !== undefined) fd.append(k, obj[k]); });
    fd.append('landing_page_mutation_csrf', LANDING_PAGE_MUTATION_CSRF);
    return fd;
  }

  function toast(msg, ok) {
    var el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 18px;border-radius:10px;font-size:.9rem;font-weight:600;color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.18);transition:opacity .4s';
    el.style.background = ok ? '#198754' : '#dc3545';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 420); }, 2800);
  }

  function reload() { window.location.reload(); }

  function getModal(id) {
    var el = document.getElementById(id);
    return el && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(el) : null;
  }

  // ── Toggle ─────────────────────────────────────────────────────────
  document.querySelectorAll('[data-lp-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var entity = btn.getAttribute('data-lp-toggle');
      var id     = btn.getAttribute('data-id');
      var baseUrl = entity === 'menu' ? MENU_URL : entity === 'gallery' ? GALLERY_URL : entity === 'link' ? LINK_URL : EMBED_URL;
      btn.disabled = true;
      req(baseUrl + '/toggle/' + id, { method: 'POST' })
        .then(function (p) {
          if (!p || !p.ok) throw new Error(p && p.message ? p.message : 'Gagal');
          reload();
        })
        .catch(function (e) { toast(e.message || 'Gagal mengubah status.', false); btn.disabled = false; });
    });
  });

  // ── Delete ─────────────────────────────────────────────────────────
  document.querySelectorAll('[data-lp-delete]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var entity = btn.getAttribute('data-lp-delete');
      var id     = btn.getAttribute('data-id');
      var name   = btn.getAttribute('data-name') || 'item ini';
      var baseUrl = entity === 'menu' ? MENU_URL : entity === 'gallery' ? GALLERY_URL : entity === 'link' ? LINK_URL : EMBED_URL;
      var prompt = entity === 'menu'
        ? 'Sembunyikan ' + name + ' dari landing page? Data Master Produk tidak akan dihapus.'
        : 'Hapus ' + name + '?';
      if (!confirm(prompt)) return;
      btn.disabled = true;
      req(baseUrl + '/delete/' + id, { method: 'POST' })
        .then(function (p) {
          if (!p || !p.ok) throw new Error(p && p.message ? p.message : 'Gagal');
          toast(p.message || 'Berhasil dihapus.', true);
          reload();
        })
        .catch(function (e) { toast(e.message || 'Gagal menghapus.', false); btn.disabled = false; });
    });
  });

  // ── Modal: Menu ────────────────────────────────────────────────────
  document.querySelectorAll('[data-lp-open-modal="menu"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var search = document.getElementById('productSearch');
      var select = document.getElementById('menuProductId');
      if (search) search.value = '';
      if (select) {
        select.selectedIndex = -1;
        Array.prototype.forEach.call(select.options, function (option) { option.hidden = false; });
      }
      var m = getModal('menuModal'); if (m) m.show();
    });
  });

  var productSearch = document.getElementById('productSearch');
  var productSelect = document.getElementById('menuProductId');
  if (productSearch && productSelect) {
    productSearch.addEventListener('input', function () {
      var query = productSearch.value.trim().toLowerCase();
      Array.prototype.forEach.call(productSelect.options, function (option) {
        option.hidden = query !== '' && (option.getAttribute('data-search') || '').indexOf(query) === -1;
      });
    });
  }

  document.getElementById('menuSaveBtn').addEventListener('click', function () {
    var productId = productSelect ? productSelect.value : '';
    if (!productId) { alert('Pilih satu produk.'); return; }

    document.getElementById('menuSaveBtn').disabled = true;
    req(MENU_STORE, { method: 'POST', body: formData({ product_id: productId }) })
      .then(function (p) {
        if (!p || !p.ok) throw new Error(p && p.message ? p.message : 'Gagal');
        var m = getModal('menuModal'); if (m) m.hide();
        toast(p.message || 'Berhasil.', true);
        reload();
      })
      .catch(function (e) { toast(e.message || 'Gagal menambahkan produk.', false); document.getElementById('menuSaveBtn').disabled = false; });
  });

  // ── Modal: Gallery ─────────────────────────────────────────────────
  document.querySelectorAll('[data-lp-open-modal="gallery"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var mode = btn.getAttribute('data-lp-mode');
      document.getElementById('galleryModalTitle').textContent = mode === 'edit' ? 'Edit Foto Gallery' : 'Tambah Foto Gallery';
      document.getElementById('galleryId').value       = mode === 'edit' ? btn.getAttribute('data-id') : '';
      document.getElementById('galleryImage').value    = mode === 'edit' ? (btn.getAttribute('data-image') || '') : '';
      document.getElementById('galleryCaption').value  = mode === 'edit' ? (btn.getAttribute('data-caption') || '') : '';
      document.getElementById('galleryIsActive').value = mode === 'edit' ? btn.getAttribute('data-is_active') : '1';
      var m = getModal('galleryModal'); if (m) m.show();
    });
  });

  document.getElementById('gallerySaveBtn').addEventListener('click', function () {
    var id    = document.getElementById('galleryId').value;
    var image = document.getElementById('galleryImage').value.trim();
    if (!image) { alert('URL gambar tidak boleh kosong.'); return; }

    var payload = {
      image:     image,
      caption:   document.getElementById('galleryCaption').value,
      is_active: document.getElementById('galleryIsActive').value,
    };

    var url = id ? GALLERY_URL + '/update/' + id : GALLERY_STORE;
    document.getElementById('gallerySaveBtn').disabled = true;
    req(url, { method: 'POST', body: formData(payload) })
      .then(function (p) {
        if (!p || !p.ok) throw new Error(p && p.message ? p.message : 'Gagal');
        var m = getModal('galleryModal'); if (m) m.hide();
        toast(p.message || 'Berhasil.', true);
        reload();
      })
      .catch(function (e) { toast(e.message || 'Gagal menyimpan.', false); document.getElementById('gallerySaveBtn').disabled = false; });
  });

  // ── Modal: Embed ───────────────────────────────────────────────────
  document.querySelectorAll('[data-lp-open-modal="embed"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var mode = btn.getAttribute('data-lp-mode');
      document.getElementById('embedModalTitle').textContent = mode === 'edit' ? 'Edit Embed' : 'Tambah Embed';
      document.getElementById('embedId').value       = mode === 'edit' ? btn.getAttribute('data-id') : '';
      document.getElementById('embedType').value     = mode === 'edit' ? (btn.getAttribute('data-embed_type') || 'photo') : 'photo';
      document.getElementById('embedHtml').value     = mode === 'edit' ? (btn.getAttribute('data-embed_html') || '') : '';
      document.getElementById('embedIsActive').value = mode === 'edit' ? btn.getAttribute('data-is_active') : '1';
      var m = getModal('embedModal'); if (m) m.show();
    });
  });

  document.getElementById('embedSaveBtn').addEventListener('click', function () {
    var id   = document.getElementById('embedId').value;
    var html = document.getElementById('embedHtml').value.trim();
    if (!html) { alert('Kode embed tidak boleh kosong.'); return; }

    var payload = {
      embed_type: document.getElementById('embedType').value,
      embed_html: html,
      is_active:  document.getElementById('embedIsActive').value,
    };

    var url = id ? EMBED_URL + '/update/' + id : EMBED_STORE;
    document.getElementById('embedSaveBtn').disabled = true;
    req(url, { method: 'POST', body: formData(payload) })
      .then(function (p) {
        if (!p || !p.ok) throw new Error(p && p.message ? p.message : 'Gagal');
        var m = getModal('embedModal'); if (m) m.hide();
        toast(p.message || 'Berhasil.', true);
        reload();
      })
      .catch(function (e) { toast(e.message || 'Gagal menyimpan.', false); document.getElementById('embedSaveBtn').disabled = false; });
  });

  // ── Modal: Link ────────────────────────────────────────────────────
  document.querySelectorAll('[data-lp-open-modal="link"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var mode = btn.getAttribute('data-lp-mode');
      document.getElementById('linkModalTitle').textContent = mode === 'edit' ? 'Edit Link' : 'Tambah Link';
      document.getElementById('linkId').value       = mode === 'edit' ? btn.getAttribute('data-id') : '';
      document.getElementById('linkIcon').value     = mode === 'edit' ? (btn.getAttribute('data-icon') || '') : '';
      document.getElementById('linkLabel').value    = mode === 'edit' ? (btn.getAttribute('data-label') || '') : '';
      document.getElementById('linkUrl').value      = mode === 'edit' ? (btn.getAttribute('data-url') || '') : '';
      document.getElementById('linkIsActive').value = mode === 'edit' ? btn.getAttribute('data-is_active') : '1';
      var m = getModal('linkModal'); if (m) m.show();
    });
  });

  document.getElementById('linkSaveBtn').addEventListener('click', function () {
    var id    = document.getElementById('linkId').value;
    var label = document.getElementById('linkLabel').value.trim();
    var url   = document.getElementById('linkUrl').value.trim();
    if (!label) { alert('Label tidak boleh kosong.'); return; }
    if (!url)   { alert('URL tidak boleh kosong.'); return; }

    var payload = {
      icon:      document.getElementById('linkIcon').value,
      label:     label,
      url:       url,
      is_active: document.getElementById('linkIsActive').value,
    };

    var reqUrl = id ? LINK_URL + '/update/' + id : LINK_STORE;
    document.getElementById('linkSaveBtn').disabled = true;
    req(reqUrl, { method: 'POST', body: formData(payload) })
      .then(function (p) {
        if (!p || !p.ok) throw new Error(p && p.message ? p.message : 'Gagal');
        var m = getModal('linkModal'); if (m) m.hide();
        toast(p.message || 'Berhasil.', true);
        reload();
      })
      .catch(function (e) { toast(e.message || 'Gagal menyimpan.', false); document.getElementById('linkSaveBtn').disabled = false; });
  });

  // ── Drag-to-reorder (menu, gallery, links) ────────────────────────
  function initReorder(tbodyId, reorderUrl) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('.lp-sort-row'));
    if (rows.length <= 1) return;

    var dragged = null;
    var saving  = false;
    var initialOrder = '';

    function clearMarkers() {
      rows.forEach(function (r) { r.classList.remove('drag-over-top', 'drag-over-bottom'); });
    }

    function currentIds() {
      return Array.prototype.slice.call(tbody.querySelectorAll('.lp-sort-row')).map(function (r) { return r.getAttribute('data-row-id'); });
    }

    function saveOrder() {
      if (saving) return;
      saving = true;
      req(reorderUrl, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: currentIds() })
      }).then(function (p) {
        if (!p || !p.ok) throw new Error(p && p.message ? p.message : 'Gagal');
        toast(p.message || 'Urutan disimpan.', true);
      }).catch(function (e) {
        toast(e.message || 'Gagal menyimpan urutan.', false);
        reload();
      }).finally(function () { saving = false; });
    }

    rows.forEach(function (row) {
      row.addEventListener('dragstart', function (e) {
        if (saving) { e.preventDefault(); return; }
        dragged = row;
        initialOrder = currentIds().join(',');
        row.classList.add('is-dragging');
        if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', row.getAttribute('data-row-id') || ''); }
      });
      row.addEventListener('dragover', function (e) {
        if (!dragged || dragged === row || saving) return;
        e.preventDefault();
        clearMarkers();
        var rect = row.getBoundingClientRect();
        row.classList.add((e.clientY - rect.top) > rect.height / 2 ? 'drag-over-bottom' : 'drag-over-top');
      });
      row.addEventListener('dragleave', function () { row.classList.remove('drag-over-top', 'drag-over-bottom'); });
      row.addEventListener('drop', function (e) {
        if (!dragged || dragged === row || saving) return;
        e.preventDefault();
        var rect = row.getBoundingClientRect();
        var after = (e.clientY - rect.top) > rect.height / 2;
        tbody.insertBefore(dragged, after ? row.nextSibling : row);
        clearMarkers();
      });
      row.addEventListener('dragend', function () {
        row.classList.remove('is-dragging');
        clearMarkers();
        var next = currentIds();
        dragged = null;
        if (next.join(',') !== initialOrder) saveOrder();
      });
    });
  }

  initReorder('galleryTbody', <?= json_encode(site_url('landing-page/gallery/reorder')) ?>);
  initReorder('linksTbody',   <?= json_encode(site_url('landing-page/links/reorder')) ?>);

  function bindCharacterCount(inputId, outputId) {
    var input = document.getElementById(inputId);
    var output = document.getElementById(outputId);
    if (!input || !output) return;
    function update() { output.textContent = String(input.value.length); }
    input.addEventListener('input', update);
    update();
  }
  bindCharacterCount('seoTitle', 'seoTitleCount');
  bindCharacterCount('seoDescription', 'seoDescriptionCount');

})();
</script>
