<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$profile = is_array($business_profile ?? null) ? $business_profile : [];
$name = trim((string)($profile['display_name'] ?? '')) ?: 'Menu';
$website = Customer_publication::public_url($profile['website_url'] ?? '');
$groups = [];
foreach ((array)($items ?? []) as $item) $groups[trim((string)($item['category_name'] ?? '')) ?: 'Pilihan menu'][] = $item;
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= html_escape('Menu — ' . $name) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#faf8f4;color:#302c29;font:16px/1.6 system-ui,sans-serif}main{max-width:1050px;margin:auto;padding:36px 24px}header{padding:32px 0;border-bottom:1px solid #dfd6c9}h1{font-size:clamp(2rem,5vw,3.5rem);line-height:1.2;margin:10px 0}h2{margin:0 0 20px}p{margin:8px 0}.eyebrow{letter-spacing:.18em;text-transform:uppercase;font-size:.75rem;color:#74634d}.logo{max-height:100px;max-width:240px;object-fit:contain}.nav{display:flex;gap:10px;flex-wrap:wrap;margin:24px 0}.nav a,.print{border:1px solid #d6c8b7;padding:7px 15px;border-radius:30px;text-decoration:none;color:inherit;background:white}.menu-section{margin:36px 0;scroll-margin-top:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.item{padding:20px;background:#fff;border:1px solid #e6dfd5;border-radius:16px;break-inside:avoid}.item-top{display:flex;justify-content:space-between;gap:16px}.price{white-space:nowrap;color:#845338;font-weight:600}.desc,.muted{font-size:.9rem;color:#766f68;overflow-wrap:anywhere}footer{border-top:1px solid #dfd6c9;padding:24px 0}.empty{padding:48px 0}.print{cursor:pointer;font:inherit}@media(max-width:640px){.grid{grid-template-columns:1fr}main{padding:20px 16px}.item-top{flex-wrap:wrap}}@media print{body{background:white}main{max-width:none;padding:0}.nav,.print{display:none}.item{border-color:#ddd}header{padding-top:0}}
</style></head><body><main>
<header><div class="eyebrow">Menu pilihan</div>
<?php if (!empty($profile['logo_url'])): ?><img class="logo" src="<?= html_escape((string)$profile['logo_url']) ?>" alt="<?= html_escape($name) ?>"><?php endif; ?>
<h1><?= html_escape($name) ?></h1><p class="muted"><?= nl2br(html_escape((string)($profile['address'] ?? ''))) ?></p>
<button class="print" type="button" onclick="window.print()">Cetak / simpan PDF</button></header>
<?php if (!$groups): ?><div class="empty"><h2>Menu sedang disiapkan</h2><p>Silakan hubungi tim kami untuk informasi menu yang tersedia.</p></div><?php endif; ?>
<nav class="nav" aria-label="Kategori menu"><?php $number = 0; foreach ($groups as $category => $rows): ?><a href="#category-<?= ++$number ?>"><?= html_escape((string)$category) ?></a><?php endforeach; ?></nav>
<?php $number = 0; foreach ($groups as $category => $rows): ?><section class="menu-section" id="category-<?= ++$number ?>"><h2><?= html_escape((string)$category) ?></h2><div class="grid">
<?php foreach ($rows as $item): ?><article class="item"><div class="item-top"><strong><?= html_escape((string)$item['product_name']) ?></strong><span class="price"><?= html_escape((string)($profile['currency_code'] ?? 'IDR')) ?> <?= number_format((float)$item['selling_price'], 0, ',', '.') ?></span></div><?php if (trim((string)($item['description'] ?? '')) !== ''): ?><p class="desc"><?= nl2br(html_escape((string)$item['description'])) ?></p><?php endif; ?></article><?php endforeach; ?>
</div></section><?php endforeach; ?>
<footer><strong><?= html_escape($name) ?></strong><p><?= html_escape((string)($profile['phone'] ?? '')) ?></p><?php if ($website !== ''): ?><a href="<?= html_escape($website) ?>" rel="noopener noreferrer"><?= html_escape($website) ?></a><?php endif; ?><p class="muted"><?= html_escape((string)($profile['document_footer'] ?? '')) ?></p><small class="muted">Harga jual menu dari master produk. Pajak, service, promo, dan ketersediaan dikonfirmasi saat pemesanan.</small></footer>
</main></body></html>
