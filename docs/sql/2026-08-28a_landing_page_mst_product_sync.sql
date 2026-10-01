-- Landing page product data now uses mst_product as its single source.
-- lp_menu remains available as legacy data but is no longer read by the public page.

ALTER TABLE `lp_config`
  MODIFY `menu_source` ENUM('manual','produk') NOT NULL DEFAULT 'produk';

UPDATE `lp_config`
SET `menu_source` = 'produk',
    `updated_at` = NOW()
WHERE `id` = 1;

-- The previous path contains a QR code rather than the labelled latte photo.
UPDATE `lp_config`
SET `about_image` = 'assets/brand/lambchop.jpg',
    `updated_at` = NOW()
WHERE `id` = 1
  AND `about_image` = 'assets/menu/cafe_latte_hot.jpg';
