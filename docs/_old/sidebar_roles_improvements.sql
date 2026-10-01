-- ============================================================
-- Sidebar & Roles Improvements — 2026-06-10
-- Jalankan sekali; aman diulang (idempotent).
-- ============================================================

-- 1. Tambah kolom matrix_group ke sys_page
--    Dipakai untuk mengelompokkan halaman di matrix role secara kustom,
--    terpisah dari kolom `module` yang menentukan logika bisnis.
ALTER TABLE `sys_page`
    ADD COLUMN IF NOT EXISTS `matrix_group` VARCHAR(50) DEFAULT NULL AFTER `module`;

-- 2. Tabel metadata group kustom (opsional, untuk label/icon/urutan)
CREATE TABLE IF NOT EXISTS `sys_matrix_group` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_code`  VARCHAR(50)  NOT NULL,
    `group_label` VARCHAR(100) NOT NULL,
    `icon`        VARCHAR(100) NOT NULL DEFAULT 'ri-apps-line',
    `color`       VARCHAR(20)  NOT NULL DEFAULT '#64748b',
    `bg_color`    VARCHAR(20)  NOT NULL DEFAULT '#f8fafc',
    `sort_order`  INT          NOT NULL DEFAULT 999,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_group_code` (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed group default (sesuaikan dengan module yang ada di sys_page)
INSERT INTO `sys_matrix_group` (`group_code`, `group_label`, `icon`, `color`, `bg_color`, `sort_order`) VALUES
    ('DASHBOARD',  'Dashboard',           'ri-dashboard-line',           '#1d4ed8', '#eff6ff',  10),
    ('AUTH',       'Auth & RBAC',         'ri-lock-password-line',       '#2563eb', '#eff6ff',  20),
    ('SISTEM',     'Sistem & Hak Akses',  'ri-settings-3-line',          '#475569', '#f1f5f9',  25),
    ('SYS',        'Sistem',              'ri-settings-3-line',          '#475569', '#f1f5f9',  30),
    ('MASTER',     'Master Data',         'ri-database-2-line',          '#7c3aed', '#f5f3ff',  40),
    ('PURCHASE',   'Pembelian',           'ri-shopping-cart-2-line',     '#ea580c', '#fff7ed',  50),
    ('INVENTORY',  'Inventori',           'ri-archive-drawer-line',      '#059669', '#f0fdf4',  60),
    ('PRODUKSI',   'Produksi',            'ri-flask-line',               '#0f766e', '#ecfeff',  65),
    ('POS',        'Point of Sale',       'ri-store-2-line',             '#be185d', '#fdf2f8',  70),
    ('HR',         'HR & Organisasi',     'ri-team-line',                '#0284c7', '#f0f9ff',  80),
    ('ATTENDANCE', 'Absensi',             'ri-calendar-check-line',      '#b45309', '#fffbeb',  85),
    ('PAYROLL',    'Payroll',             'ri-money-dollar-circle-line', '#0d9488', '#f0fdfa',  90),
    ('FINANCE',    'Keuangan',            'ri-bank-line',                '#be123c', '#fff1f2', 100),
    ('REPORT',     'Laporan',             'ri-bar-chart-2-line',         '#6d28d9', '#faf5ff', 110),
    ('MY_PORTAL',  'Portal Saya',         'ri-user-settings-line',       '#4338ca', '#eef2ff', 120)
ON DUPLICATE KEY UPDATE
    `group_label` = VALUES(`group_label`),
    `sort_order`  = VALUES(`sort_order`);
