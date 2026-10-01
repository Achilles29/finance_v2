-- ============================================================
-- 2026-06-23c  Tabel lp_links — Tombol halaman Linktree Namua
-- ============================================================

CREATE TABLE IF NOT EXISTS `lp_links` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `label`       VARCHAR(100)  NOT NULL              COMMENT 'Teks tombol',
  `url`         VARCHAR(500)  NOT NULL              COMMENT 'URL tujuan',
  `icon`        VARCHAR(50)   DEFAULT NULL          COMMENT 'Emoji atau teks singkat untuk ikon',
  `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`  INT UNSIGNED,
  `updated_by`  INT UNSIGNED,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tombol halaman links (Linktree) Namua';

-- Default links
INSERT IGNORE INTO `lp_links` (`id`, `label`, `url`, `icon`, `sort_order`) VALUES
(1, 'MENU BOOK',     'http://localhost/finance/menu_book',      '☕', 1),
(2, 'Location',      'https://g.co/kgs/zeHCAHH',               '🏪', 2),
(3, 'WA Namua',      'https://wa.me/6285150737377',             '💬', 3),
(4, 'Order by GRAB', 'https://member.namuacoffee.com/order',    '🛵', 4);
