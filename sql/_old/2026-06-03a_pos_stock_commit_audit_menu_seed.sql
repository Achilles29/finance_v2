SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-03a_pos_stock_commit_audit_menu_seed.sql
-- Tujuan :
-- 1) Menambah halaman Audit Commit Stok POS ke sidebar POS & Kasir
-- 2) Memakai permission page yang sama dengan pos.stock.live.index
-- 3) Menjadi pusat audit umum untuk bahan baku dan base/prepare
-- ============================================================

START TRANSACTION;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.stock.commit.audit',
  'Audit Commit Stok',
  'ri-scales-3-line',
  '/pos/stock-commit-audit',
  p.id,
  47,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN (
  SELECT id
  FROM sys_menu
  WHERE menu_code = 'grp.pos'
  LIMIT 1
) parent
WHERE p.page_code = 'pos.stock.live.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_menu.pos.stock.commit.audit' AS seed_key, COUNT(*) AS total_rows
FROM sys_menu
WHERE menu_code = 'pos.stock.commit.audit';
