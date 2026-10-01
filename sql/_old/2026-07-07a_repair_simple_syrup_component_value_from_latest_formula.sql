SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-07a_repair_simple_syrup_component_value_from_latest_formula.sql
-- Tujuan :
-- 1) Memperbaiki nilai rupiah SIMPLE SYRUP yang menjadi minus /
--    sangat besar akibat unit cost abnormal di histori component.
-- 2) Menggunakan HPP resep terbaru sebagai sumber kebenaran biaya.
-- 3) Menyelaraskan nilai biaya dari batch, lot, issue, movement,
--    commit POS, dan monthly stock mulai opening sampai closing.
--
-- Scope sengaja sempit:
-- - Component : SIMPLE SYRUP / BASE-DASH-00001
-- - Mulai     : 2026-06-01
--
-- Catatan:
-- - HPP resep terbaru dihitung dari mst_component_formula:
--   total line cost / yield_qty component.
-- - Script ini TIDAK mengubah qty. Yang direpair adalah nilai rupiah
--   dan unit_cost agar tidak minus / tidak absurd.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair SIMPLE SYRUP value from latest formula HPP 2026-07-07';
SET @component_code := 'BASE-DASH-00001';
SET @component_name := 'SIMPLE SYRUP';
SET @start_date := '2026-06-01';
SET @start_month := '2026-06-01';

SELECT
  x.component_id,
  x.yield_qty,
  ROUND(x.formula_total_cost / NULLIF(x.yield_qty, 0), 6)
INTO
  @component_id,
  @yield_qty,
  @target_unit_cost
FROM (
  SELECT
    c.id AS component_id,
    COALESCE(c.yield_qty, 0) AS yield_qty,
    ROUND(SUM(
      CASE
        WHEN f.line_type = 'MATERIAL' THEN COALESCE(f.qty, 0) * COALESCE(m.hpp_standard, 0)
        WHEN f.line_type = 'COMPONENT' THEN COALESCE(f.qty, 0) * COALESCE(sc.hpp_standard, 0)
        ELSE 0
      END
    ), 6) AS formula_total_cost
  FROM mst_component c
  LEFT JOIN mst_component_formula f ON f.component_id = c.id
  LEFT JOIN mst_material m ON m.id = f.material_id
  LEFT JOIN mst_component sc ON sc.id = f.sub_component_id
  WHERE c.component_code = @component_code
     OR c.component_name = @component_name
  GROUP BY c.id, c.yield_qty
  ORDER BY c.id
  LIMIT 1
) x;

SET @target_unit_cost := COALESCE(@target_unit_cost, 0);

-- Guard agar script tidak jalan kalau component / HPP resep tidak valid.
DROP TEMPORARY TABLE IF EXISTS tmp_simple_syrup_repair_guard;
CREATE TEMPORARY TABLE tmp_simple_syrup_repair_guard (
  component_id BIGINT(20) UNSIGNED NOT NULL,
  target_unit_cost DECIMAL(18,6) NOT NULL,
  CHECK (component_id > 0),
  CHECK (target_unit_cost > 0)
);

INSERT INTO tmp_simple_syrup_repair_guard (component_id, target_unit_cost)
VALUES (@component_id, @target_unit_cost);

-- ------------------------------------------------------------
-- A. Backup permanen sebelum repair
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_component_monthly_20260707 AS
SELECT * FROM inv_component_monthly_stock WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_component_lot_20260707 AS
SELECT * FROM inv_component_lot WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_component_movement_20260707 AS
SELECT * FROM inv_component_movement_log WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_component_issue_20260707 AS
SELECT * FROM inv_component_lot_issue_log WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_component_issue_line_20260707 AS
SELECT * FROM inv_component_lot_issue_line WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_pos_commit_line_20260707 AS
SELECT * FROM pos_stock_commit_line WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_component_batch_20260707 AS
SELECT * FROM inv_component_batch WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zz_backup_simple_syrup_component_batch_input_20260707 AS
SELECT * FROM inv_component_batch_input WHERE 1 = 0;

INSERT INTO zz_backup_simple_syrup_component_monthly_20260707
SELECT s.*
FROM inv_component_monthly_stock s
WHERE s.component_id = @component_id
  AND s.month_key >= @start_month;

INSERT INTO zz_backup_simple_syrup_component_lot_20260707
SELECT l.*
FROM inv_component_lot l
WHERE l.component_id = @component_id
  AND l.receipt_date >= @start_date;

INSERT INTO zz_backup_simple_syrup_component_movement_20260707
SELECT m.*
FROM inv_component_movement_log m
WHERE m.component_id = @component_id
  AND m.movement_date >= @start_date;

INSERT INTO zz_backup_simple_syrup_component_issue_20260707
SELECT i.*
FROM inv_component_lot_issue_log i
WHERE i.component_id = @component_id
  AND i.issue_date >= @start_date;

INSERT INTO zz_backup_simple_syrup_component_issue_line_20260707
SELECT il.*
FROM inv_component_lot_issue_line il
JOIN inv_component_lot_issue_log i ON i.id = il.issue_id
WHERE i.component_id = @component_id
  AND i.issue_date >= @start_date;

INSERT INTO zz_backup_simple_syrup_pos_commit_line_20260707
SELECT cl.*
FROM pos_stock_commit_line cl
WHERE cl.source_kind = 'COMPONENT'
  AND cl.component_id = @component_id
  AND cl.created_at >= @start_date;

INSERT INTO zz_backup_simple_syrup_component_batch_20260707
SELECT b.*
FROM inv_component_batch b
WHERE b.component_id = @component_id
  AND b.batch_date >= @start_date;

INSERT INTO zz_backup_simple_syrup_component_batch_input_20260707
SELECT bi.*
FROM inv_component_batch_input bi
JOIN inv_component_batch b ON b.id = bi.batch_id
WHERE b.component_id = @component_id
  AND b.batch_date >= @start_date;

-- ------------------------------------------------------------
-- B. Selaraskan master HPP component ke HPP resep terbaru
-- ------------------------------------------------------------
UPDATE mst_component c
SET
  c.hpp_standard = @target_unit_cost,
  c.updated_at = CURRENT_TIMESTAMP
WHERE c.id = @component_id;

-- ------------------------------------------------------------
-- C. Selaraskan batch SIMPLE SYRUP dari resep terbaru
-- ------------------------------------------------------------
UPDATE inv_component_batch_input bi
JOIN inv_component_batch b ON b.id = bi.batch_id
LEFT JOIN mst_material m ON m.id = bi.material_id AND bi.source_kind = 'MATERIAL'
LEFT JOIN mst_component sc ON sc.id = bi.component_id AND bi.source_kind = 'COMPONENT'
SET
  bi.unit_cost = CASE
    WHEN bi.source_kind = 'MATERIAL' THEN ROUND(COALESCE(m.hpp_standard, bi.unit_cost, 0), 6)
    WHEN bi.source_kind = 'COMPONENT' THEN ROUND(COALESCE(sc.hpp_standard, bi.unit_cost, 0), 6)
    ELSE bi.unit_cost
  END,
  bi.total_cost = ROUND(
    COALESCE(bi.qty, 0) *
    CASE
      WHEN bi.source_kind = 'MATERIAL' THEN COALESCE(m.hpp_standard, bi.unit_cost, 0)
      WHEN bi.source_kind = 'COMPONENT' THEN COALESCE(sc.hpp_standard, bi.unit_cost, 0)
      ELSE bi.unit_cost
    END
  , 2),
  bi.notes = LEFT(TRIM(CONCAT(
    COALESCE(bi.notes, ''),
    CASE WHEN COALESCE(bi.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | input cost normalized'
  )), 255)
WHERE b.component_id = @component_id
  AND b.batch_date >= @start_date;

UPDATE inv_component_batch b
LEFT JOIN (
  SELECT
    batch_id,
    ROUND(SUM(COALESCE(total_cost, 0)), 2) AS rebuilt_total_input_cost
  FROM inv_component_batch_input
  GROUP BY batch_id
) bi ON bi.batch_id = b.id
SET
  b.total_input_cost = ROUND(COALESCE(bi.rebuilt_total_input_cost, COALESCE(b.output_qty, 0) * @target_unit_cost), 2),
  b.unit_cost = @target_unit_cost,
  b.notes = LEFT(TRIM(CONCAT(
    COALESCE(b.notes, ''),
    CASE WHEN COALESCE(b.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | output unit cost normalized to latest formula HPP'
  )), 255),
  b.updated_at = CURRENT_TIMESTAMP
WHERE b.component_id = @component_id
  AND b.batch_date >= @start_date;

-- ------------------------------------------------------------
-- D. Selaraskan lot FIFO component
-- ------------------------------------------------------------
UPDATE inv_component_lot l
SET
  l.unit_cost = @target_unit_cost,
  l.updated_at = CURRENT_TIMESTAMP
WHERE l.component_id = @component_id
  AND l.receipt_date >= @start_date;

-- ------------------------------------------------------------
-- E. Selaraskan issue FIFO component dan detail lot issue
-- ------------------------------------------------------------
UPDATE inv_component_lot_issue_line il
JOIN inv_component_lot_issue_log i ON i.id = il.issue_id
SET
  il.unit_cost = @target_unit_cost,
  il.total_cost = ROUND(COALESCE(il.qty_out, 0) * @target_unit_cost, 2)
WHERE i.component_id = @component_id
  AND i.issue_date >= @start_date;

UPDATE inv_component_lot_issue_log i
LEFT JOIN (
  SELECT
    issue_id,
    ROUND(SUM(COALESCE(total_cost, 0)), 2) AS rebuilt_total_cost
  FROM inv_component_lot_issue_line
  GROUP BY issue_id
) il ON il.issue_id = i.id
SET
  i.total_cost = ROUND(COALESCE(il.rebuilt_total_cost, COALESCE(i.issue_qty, 0) * @target_unit_cost), 2),
  i.notes = LEFT(TRIM(CONCAT(
    COALESCE(i.notes, ''),
    CASE WHEN COALESCE(i.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | issue cost normalized'
  )), 65535),
  i.updated_at = CURRENT_TIMESTAMP
WHERE i.component_id = @component_id
  AND i.issue_date >= @start_date;

-- ------------------------------------------------------------
-- F. Selaraskan movement log component
-- ------------------------------------------------------------
UPDATE inv_component_movement_log m
SET
  m.unit_cost = @target_unit_cost,
  m.total_cost = ROUND((COALESCE(m.qty_in, 0) + COALESCE(m.qty_out, 0)) * @target_unit_cost, 2),
  m.notes = LEFT(TRIM(CONCAT(
    COALESCE(m.notes, ''),
    CASE WHEN COALESCE(m.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | movement cost normalized'
  )), 255)
WHERE m.component_id = @component_id
  AND m.movement_date >= @start_date;

-- ------------------------------------------------------------
-- G. Selaraskan POS commit line component SIMPLE SYRUP
-- ------------------------------------------------------------
UPDATE pos_stock_commit_line cl
SET
  cl.unit_cost_live = @target_unit_cost,
  cl.total_cost_live = ROUND(COALESCE(cl.committed_qty, cl.required_qty, 0) * @target_unit_cost, 6),
  cl.cost_source = 'MANUAL',
  cl.notes = LEFT(TRIM(CONCAT(
    COALESCE(cl.notes, ''),
    CASE WHEN COALESCE(cl.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | POS commit cost normalized'
  )), 255),
  cl.updated_at = CURRENT_TIMESTAMP
WHERE cl.source_kind = 'COMPONENT'
  AND cl.component_id = @component_id
  AND cl.created_at >= @start_date;

-- ------------------------------------------------------------
-- H. Hitung ulang nilai rupiah monthly stock dari opening sampai closing
-- ------------------------------------------------------------
UPDATE inv_component_monthly_stock s
SET
  s.opening_total_value = ROUND(COALESCE(s.opening_qty, 0) * @target_unit_cost, 2),
  s.in_total_value = ROUND(COALESCE(s.in_qty, 0) * @target_unit_cost, 2),
  s.out_total_value = ROUND(COALESCE(s.out_qty, 0) * @target_unit_cost, 2),
  s.waste_total_value = ROUND(COALESCE(s.waste_qty, 0) * @target_unit_cost, 2),
  s.spoil_total_value = ROUND(COALESCE(s.spoil_qty, 0) * @target_unit_cost, 2),
  s.adjustment_plus_total_value = ROUND(COALESCE(s.adjustment_plus_qty, 0) * @target_unit_cost, 2),
  s.adjustment_minus_total_value = ROUND(COALESCE(s.adjustment_minus_qty, 0) * @target_unit_cost, 2),
  s.avg_cost = @target_unit_cost,
  s.total_value = ROUND(COALESCE(s.closing_qty, 0) * @target_unit_cost, 2),
  s.source_mode = 'REBUILD',
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | monthly value rebuilt from opening to closing'
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.component_id = @component_id
  AND s.month_key >= @start_month;

COMMIT;

-- ------------------------------------------------------------
-- I. Verifikasi ringkas
-- ------------------------------------------------------------
SELECT
  'target_component' AS metric,
  CONCAT(@component_id, ' | ', @component_code, ' | ', @component_name) AS value
UNION ALL
SELECT 'formula_hpp_unit_cost', CAST(@target_unit_cost AS CHAR)
UNION ALL
SELECT 'backup_monthly_rows', CAST(COUNT(*) AS CHAR)
FROM zz_backup_simple_syrup_component_monthly_20260707
UNION ALL
SELECT 'backup_lot_rows', CAST(COUNT(*) AS CHAR)
FROM zz_backup_simple_syrup_component_lot_20260707
UNION ALL
SELECT 'backup_movement_rows', CAST(COUNT(*) AS CHAR)
FROM zz_backup_simple_syrup_component_movement_20260707
UNION ALL
SELECT 'backup_issue_rows', CAST(COUNT(*) AS CHAR)
FROM zz_backup_simple_syrup_component_issue_20260707
UNION ALL
SELECT 'backup_pos_commit_line_rows', CAST(COUNT(*) AS CHAR)
FROM zz_backup_simple_syrup_pos_commit_line_20260707;

SELECT
  s.month_key,
  s.location_type,
  s.division_id,
  s.component_id,
  s.uom_id,
  s.opening_qty,
  s.opening_total_value,
  s.in_qty,
  s.in_total_value,
  s.out_qty,
  s.out_total_value,
  s.adjustment_plus_qty,
  s.adjustment_plus_total_value,
  s.adjustment_minus_qty,
  s.adjustment_minus_total_value,
  s.closing_qty,
  s.avg_cost,
  s.total_value
FROM inv_component_monthly_stock s
WHERE s.component_id = @component_id
  AND s.month_key >= @start_month
ORDER BY s.month_key, s.location_type, s.division_id;

SELECT
  'negative_monthly_value_after_repair' AS audit_key,
  COUNT(*) AS total_rows
FROM inv_component_monthly_stock s
WHERE s.component_id = @component_id
  AND s.month_key >= @start_month
  AND (
    s.opening_total_value < 0
    OR s.in_total_value < 0
    OR s.out_total_value < 0
    OR s.waste_total_value < 0
    OR s.spoil_total_value < 0
    OR s.adjustment_plus_total_value < 0
    OR s.adjustment_minus_total_value < 0
    OR (s.closing_qty >= 0 AND s.total_value < 0)
  )

UNION ALL

SELECT
  'abnormal_lot_unit_cost_after_repair',
  COUNT(*)
FROM inv_component_lot l
WHERE l.component_id = @component_id
  AND l.receipt_date >= @start_date
  AND (l.unit_cost < 0 OR l.unit_cost > @target_unit_cost * 10)

UNION ALL

SELECT
  'abnormal_movement_cost_after_repair',
  COUNT(*)
FROM inv_component_movement_log m
WHERE m.component_id = @component_id
  AND m.movement_date >= @start_date
  AND (m.unit_cost < 0 OR m.unit_cost > @target_unit_cost * 10 OR m.total_cost < 0);
