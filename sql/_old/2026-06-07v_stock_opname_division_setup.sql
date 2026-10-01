SET NAMES utf8mb4;

-- ============================================================
-- Setup: Halaman Opname Stok Bahan Baku Harian Divisi
-- Tanggal: 2026-06-07
-- URL: /inventory/stock/opname/division
-- Page code: inventory.stock.opname.division.index
-- ============================================================

-- ============================================================
-- 1. Tabel penyimpanan sesi opname harian
-- ============================================================
CREATE TABLE IF NOT EXISTS inv_division_stock_opname (
    id             BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    opname_date    DATE               NOT NULL,
    division_id    BIGINT UNSIGNED    NOT NULL,
    destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER')
                                      NOT NULL DEFAULT 'OTHER',
    item_id        BIGINT UNSIGNED    NULL,
    material_id    BIGINT UNSIGNED    NULL,
    buy_uom_id     BIGINT UNSIGNED    NULL,
    content_uom_id BIGINT UNSIGNED    NOT NULL,
    profile_key    CHAR(64)           NOT NULL DEFAULT '',
    identity_key   VARCHAR(255)       NOT NULL DEFAULT '',
    profile_name   VARCHAR(150)       NULL,
    profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
    profile_buy_uom_code    VARCHAR(40) NULL,
    profile_content_uom_code VARCHAR(40) NULL,
    system_qty_content       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    physical_qty_content     DECIMAL(18,4) NULL,
    notes          VARCHAR(255)       NULL,
    adjustment_id  BIGINT UNSIGNED    NULL,
    created_by     BIGINT UNSIGNED    NULL,
    created_at     DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME           NULL     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_opname_identity (opname_date, division_id, destination_type, identity_key),
    INDEX idx_opname_date       (opname_date),
    INDEX idx_opname_division   (division_id),
    INDEX idx_opname_adjustment (adjustment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sesi opname stok fisik bahan baku divisi harian';

-- ============================================================
-- 2. Daftarkan halaman di sys_page
-- ============================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
    'inventory.stock.opname.division.index',
    'Opname Stok Bahan Baku Divisi',
    'PURCHASE',
    'Halaman kroscek dan rekonsiliasi stok bahan baku harian per divisi dengan input fisik dan aksi penyesuaian.',
    1
)
ON DUPLICATE KEY UPDATE
    page_name   = VALUES(page_name),
    description = VALUES(description),
    is_active   = 1;

-- ============================================================
-- 3. Tambahkan menu di sidebar grup Stok Divisi (parent_id = 222)
-- ============================================================
INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, sort_order, is_active, sidebar_type)
VALUES (
    222,
    'purchase.stock.opname.division',
    'Opname Stok Divisi',
    'ri-clipboard-check-line',
    '/inventory/stock/opname/division',
    8,
    1,
    'MAIN'
)
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label),
    icon       = VALUES(icon),
    url        = VALUES(url),
    sort_order = VALUES(sort_order),
    is_active  = 1;

-- ============================================================
-- 4. Beri akses ke Super Admin (role_id = 1)
--    can_view=1, can_create=1, can_edit=1
-- ============================================================
INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export)
SELECT
    1,
    p.id,
    1, 1, 1, 0, 1
FROM sys_page p
WHERE p.page_code = 'inventory.stock.opname.division.index'
ON DUPLICATE KEY UPDATE
    can_view   = 1,
    can_create = 1,
    can_edit   = 1,
    can_export = 1;

-- ============================================================
-- 5. Verifikasi
-- ============================================================
SELECT 'sys_page'  AS tabel, page_code, page_name FROM sys_page WHERE page_code = 'inventory.stock.opname.division.index'
UNION ALL
SELECT 'sys_menu', menu_code, menu_label FROM sys_menu WHERE menu_code = 'purchase.stock.opname.division';
