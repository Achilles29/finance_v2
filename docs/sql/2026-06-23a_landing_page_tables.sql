-- ============================================================
-- Landing Page Tables — Namua Coffee & Eatery
-- 2026-06-23
-- ============================================================

-- lp_config : konfigurasi umum landing page (single-row)
CREATE TABLE IF NOT EXISTS `lp_config` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Hero
  `hero_title`           TEXT,
  `hero_subtitle`        TEXT,
  `hero_badges`          JSON         COMMENT 'JSON array: ["badge1","badge2",...]',
  `hero_image`           VARCHAR(500),
  -- About
  `about_title`          VARCHAR(255),
  `about_text`           TEXT,
  `about_points`         JSON         COMMENT 'JSON array: ["poin1","poin2",...]',
  `about_image`          VARCHAR(500),
  -- Kontak & URL
  `address`              TEXT,
  `phone`                VARCHAR(30),
  `whatsapp`             VARCHAR(30)  COMMENT 'Format internasional tanpa +, mis: 6285150737377',
  `order_url`            VARCHAR(500),
  `member_url`           VARCHAR(500),
  `instagram_url`        VARCHAR(500),
  `linktree_url`         VARCHAR(500),
  `map_url`              VARCHAR(500),
  -- CTA & Footer
  `cta_title`            VARCHAR(255),
  `cta_text`             TEXT,
  `footer_text`          TEXT,
  -- Pengaturan sumber data
  `menu_source`          ENUM('manual','produk') NOT NULL DEFAULT 'manual',
  `menu_limit`           TINYINT UNSIGNED        NOT NULL DEFAULT 8,
  `menu_best_seller_top` TINYINT UNSIGNED        NOT NULL DEFAULT 3,
  `menu_kategori_ids`    VARCHAR(255)            COMMENT 'Comma-separated kategori_id untuk filter produk POS',
  `gallery_source`       ENUM('manual','produk') NOT NULL DEFAULT 'manual',
  `gallery_limit`        TINYINT UNSIGNED        NOT NULL DEFAULT 6,
  `gallery_kategori_ids` VARCHAR(255),
  -- Audit
  `updated_at`           DATETIME,
  `updated_by`           INT UNSIGNED,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Konfigurasi umum landing page Namua';

-- Row default (hanya dimasukkan sekali)
INSERT IGNORE INTO `lp_config` (
  `id`, `hero_title`, `hero_subtitle`, `hero_badges`, `hero_image`,
  `about_title`, `about_text`, `about_points`, `about_image`,
  `address`, `phone`, `whatsapp`,
  `order_url`, `member_url`, `instagram_url`, `linktree_url`, `map_url`,
  `cta_title`, `cta_text`, `footer_text`
) VALUES (
  1,
  'Hangat, elegan, dan penuh aroma kopi.',
  'Namua Coffee & Eatery menghadirkan kopi pilihan dan sajian comfort food dalam suasana yang intim dan berkelas.',
  '["Signature Coffee","All Day Eatery","Cozy & Elegant"]',
  'assets/hero/americano_hot.jpg',
  'About Namua',
  'Didesain untuk menjadi ruang singgah yang hangat, Namua memadukan interior bernuansa kayu, pencahayaan temaram, dan aroma kopi segar. Cocok untuk kerja santai, kumpul keluarga, hingga menikmati momen spesial.',
  '["Bar kopi dengan racikan signature.","Menu makanan pendamping yang kaya rasa.","Suasana elegan dan menenangkan."]',
  'assets/menu/cafe_latte_hot.jpg',
  'Jl. Magnolia, Sawah, Kabongan Kidul, Kec. Rembang, Kabupaten Rembang, Jawa Tengah 59218',
  '0851-5073-7377',
  '6285150737377',
  'https://member.namuacoffee.com/order',
  'https://member.namuacoffee.com',
  'https://www.instagram.com/namuacoffee/',
  'https://linktr.ee/namua',
  'https://g.co/kgs/zeHCAHH',
  'Siap menikmati kopi terbaik di Rembang?',
  'Reservasi meja, pemesanan grup, atau info event spesial bisa langsung melalui WhatsApp.',
  'Elegan, hangat, dan penuh cerita dalam setiap cangkir.'
);

-- lp_menu : item menu carousel
CREATE TABLE IF NOT EXISTS `lp_menu` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`          VARCHAR(255)    NOT NULL,
  `description`    TEXT,
  `image`          VARCHAR(500),
  `price`          DECIMAL(12,0)   DEFAULT NULL COMMENT 'NULL = tidak ditampilkan',
  `is_best_seller` TINYINT(1)      NOT NULL DEFAULT 0,
  `sort_order`     SMALLINT        NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`     INT UNSIGNED,
  `updated_by`     INT UNSIGNED,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Item menu carousel landing page';

-- lp_gallery : foto gallery
CREATE TABLE IF NOT EXISTS `lp_gallery` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `image`       VARCHAR(500)    NOT NULL,
  `caption`     VARCHAR(255),
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`  INT UNSIGNED,
  `updated_by`  INT UNSIGNED,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Foto gallery landing page';

-- lp_embed : kode embed Instagram
CREATE TABLE IF NOT EXISTS `lp_embed` (
  `id`          INT UNSIGNED         NOT NULL AUTO_INCREMENT,
  `embed_type`  ENUM('reel','photo') NOT NULL DEFAULT 'photo',
  `embed_html`  TEXT                 NOT NULL COMMENT 'Kode HTML embed dari Instagram',
  `sort_order`  SMALLINT             NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)           NOT NULL DEFAULT 1,
  `created_at`  DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`  INT UNSIGNED,
  `updated_by`  INT UNSIGNED,
  PRIMARY KEY (`id`),
  KEY `idx_active_type_sort` (`is_active`, `embed_type`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kode embed Instagram landing page';

-- Registrasi sidebar & permission: lihat 2026-06-23b_landing_page_menu_and_permissions.sql
