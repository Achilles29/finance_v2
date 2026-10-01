-- Editable SEO settings for namuacoffee.com.

ALTER TABLE `lp_config`
  ADD COLUMN IF NOT EXISTS `seo_title` VARCHAR(255) NULL AFTER `footer_text`,
  ADD COLUMN IF NOT EXISTS `seo_description` VARCHAR(320) NULL AFTER `seo_title`,
  ADD COLUMN IF NOT EXISTS `seo_canonical_url` VARCHAR(500) NULL AFTER `seo_description`,
  ADD COLUMN IF NOT EXISTS `seo_share_image` VARCHAR(500) NULL AFTER `seo_canonical_url`,
  ADD COLUMN IF NOT EXISTS `seo_indexing` ENUM('index','noindex') NOT NULL DEFAULT 'index' AFTER `seo_share_image`,
  ADD COLUMN IF NOT EXISTS `seo_google_verification` VARCHAR(255) NULL AFTER `seo_indexing`;

UPDATE `lp_config`
SET `hero_subtitle` = REPLACE(`hero_subtitle`, 'Coffee & Eatery', 'Coffee & Roastery'),
    `hero_badges` = REPLACE(`hero_badges`, 'All Day Eatery', 'Small Batch Roastery'),
    `seo_title` = COALESCE(NULLIF(`seo_title`, ''), 'Namua Coffee & Roastery Rembang | Kopi & Comfort Food'),
    `seo_description` = COALESCE(NULLIF(`seo_description`, ''), 'Nikmati kopi pilihan, hasil roasting, dan comfort food di Namua Coffee & Roastery Rembang. Buka setiap hari pukul 09.00-23.00.'),
    `seo_canonical_url` = COALESCE(NULLIF(`seo_canonical_url`, ''), 'https://namuacoffee.com/'),
    `seo_share_image` = COALESCE(NULLIF(`seo_share_image`, ''), 'assets/hero/americano_hot.jpg'),
    `seo_indexing` = 'index',
    `updated_at` = NOW()
WHERE `id` = 1;
