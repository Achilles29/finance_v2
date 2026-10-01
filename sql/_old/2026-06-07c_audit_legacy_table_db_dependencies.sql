SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07c_audit_legacy_table_db_dependencies.sql
-- Tujuan :
-- 1) Audit object database yang masih menyebut tabel legacy
--    daily_rollup / stock_balance
-- 2) Memberi pre-check aman sebelum rename/drop tabel legacy
--
-- Catatan:
-- - Script ini TIDAK mengubah data atau schema
-- - Fokus hanya pada dependency level database:
--   views, routines, triggers, events
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_table_names;
CREATE TEMPORARY TABLE tmp_legacy_table_names (
  table_name VARCHAR(100) NOT NULL PRIMARY KEY
);

INSERT INTO tmp_legacy_table_names (table_name) VALUES
  ('inv_warehouse_daily_rollup'),
  ('inv_division_daily_rollup'),
  ('inv_component_daily_rollup'),
  ('inv_warehouse_stock_balance'),
  ('inv_division_stock_balance'),
  ('inv_component_stock_balance');

-- ------------------------------------------------------------
-- A. Status keberadaan tabel legacy
-- ------------------------------------------------------------
SELECT
  t.table_name,
  CASE WHEN x.TABLE_NAME IS NOT NULL THEN 1 ELSE 0 END AS table_exists,
  x.TABLE_ROWS AS approx_rows,
  x.CREATE_TIME,
  x.UPDATE_TIME
FROM tmp_legacy_table_names t
LEFT JOIN information_schema.TABLES x
  ON x.TABLE_SCHEMA = DATABASE()
 AND x.TABLE_NAME = t.table_name
ORDER BY t.table_name;

-- ------------------------------------------------------------
-- B. View yang masih menyebut tabel legacy
-- ------------------------------------------------------------
SELECT
  'VIEW' AS object_type,
  v.TABLE_NAME AS object_name,
  t.table_name AS referenced_legacy_table
FROM information_schema.VIEWS v
JOIN tmp_legacy_table_names t
  ON UPPER(COALESCE(v.VIEW_DEFINITION, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
WHERE v.TABLE_SCHEMA = DATABASE()
ORDER BY v.TABLE_NAME, t.table_name;

-- ------------------------------------------------------------
-- C. Stored function / procedure yang masih menyebut tabel legacy
-- ------------------------------------------------------------
SELECT
  r.ROUTINE_TYPE AS object_type,
  r.ROUTINE_NAME AS object_name,
  t.table_name AS referenced_legacy_table
FROM information_schema.ROUTINES r
JOIN tmp_legacy_table_names t
  ON UPPER(COALESCE(r.ROUTINE_DEFINITION, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
WHERE r.ROUTINE_SCHEMA = DATABASE()
ORDER BY r.ROUTINE_TYPE, r.ROUTINE_NAME, t.table_name;

-- ------------------------------------------------------------
-- D. Trigger yang masih menyebut tabel legacy
-- ------------------------------------------------------------
SELECT
  'TRIGGER' AS object_type,
  tr.TRIGGER_NAME AS object_name,
  t.table_name AS referenced_legacy_table,
  tr.EVENT_OBJECT_TABLE AS event_object_table
FROM information_schema.TRIGGERS tr
JOIN tmp_legacy_table_names t
  ON UPPER(COALESCE(tr.ACTION_STATEMENT, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
WHERE tr.TRIGGER_SCHEMA = DATABASE()
ORDER BY tr.TRIGGER_NAME, t.table_name;

-- ------------------------------------------------------------
-- E. Event scheduler yang masih menyebut tabel legacy
-- ------------------------------------------------------------
SELECT
  'EVENT' AS object_type,
  e.EVENT_NAME AS object_name,
  t.table_name AS referenced_legacy_table
FROM information_schema.EVENTS e
JOIN tmp_legacy_table_names t
  ON UPPER(COALESCE(e.EVENT_DEFINITION, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
WHERE e.EVENT_SCHEMA = DATABASE()
ORDER BY e.EVENT_NAME, t.table_name;

-- ------------------------------------------------------------
-- F. Ringkasan singkat
-- ------------------------------------------------------------
SELECT 'legacy_tables_existing' AS metric, COUNT(*) AS total
FROM information_schema.TABLES x
JOIN tmp_legacy_table_names t
  ON t.table_name = x.TABLE_NAME
WHERE x.TABLE_SCHEMA = DATABASE()

UNION ALL

SELECT 'views_referencing_legacy_tables', COUNT(*)
FROM (
  SELECT DISTINCT v.TABLE_NAME, t.table_name
  FROM information_schema.VIEWS v
  JOIN tmp_legacy_table_names t
    ON UPPER(COALESCE(v.VIEW_DEFINITION, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
  WHERE v.TABLE_SCHEMA = DATABASE()
) x

UNION ALL

SELECT 'routines_referencing_legacy_tables', COUNT(*)
FROM (
  SELECT DISTINCT r.ROUTINE_NAME, t.table_name
  FROM information_schema.ROUTINES r
  JOIN tmp_legacy_table_names t
    ON UPPER(COALESCE(r.ROUTINE_DEFINITION, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
  WHERE r.ROUTINE_SCHEMA = DATABASE()
) x

UNION ALL

SELECT 'triggers_referencing_legacy_tables', COUNT(*)
FROM (
  SELECT DISTINCT tr.TRIGGER_NAME, t.table_name
  FROM information_schema.TRIGGERS tr
  JOIN tmp_legacy_table_names t
    ON UPPER(COALESCE(tr.ACTION_STATEMENT, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
  WHERE tr.TRIGGER_SCHEMA = DATABASE()
) x

UNION ALL

SELECT 'events_referencing_legacy_tables', COUNT(*)
FROM (
  SELECT DISTINCT e.EVENT_NAME, t.table_name
  FROM information_schema.EVENTS e
  JOIN tmp_legacy_table_names t
    ON UPPER(COALESCE(e.EVENT_DEFINITION, '')) LIKE CONCAT('%', UPPER(t.table_name), '%')
  WHERE e.EVENT_SCHEMA = DATABASE()
) x;
