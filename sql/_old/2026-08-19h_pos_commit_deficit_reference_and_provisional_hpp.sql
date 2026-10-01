SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-19h_pos_commit_deficit_reference_and_provisional_hpp.sql
-- Tujuan :
-- 1) Menambahkan label INVENTORY_DEFICIT pada referensi line stock POS
-- 2) Menambahkan sumber biaya DEFICIT_PENDING untuk HPP sementara
-- 3) Merapikan metadata defisit pada BULAN AKTIF saja
-- 4) Mengembalikan HPP sementara penuh untuk defisit POS yang sudah terjadi
--
-- Aman dijalankan berulang:
-- - tidak mengubah qty stok, lot, movement, atau defisit
-- - hanya memperjelas referensi commit dan nilai HPP snapshot POS bulan aktif
-- - harga aktual saat defisit ditutup tetap dicatat terpisah oleh
--   inv_stock_deficit_cogs_adjustment
-- ============================================================

SET @schema_name := DATABASE();

-- Posisi lama hanya mengenal MATERIAL_LEDGER, COMPONENT_LEDGER, dan NONE.
-- Tambahkan tipe referensi eksplisit agar ID defisit tidak terlihat seperti
-- referensi movement yang putus.
SET @movement_ref_type := (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'pos_stock_commit_line'
    AND COLUMN_NAME = 'movement_ref_type'
  LIMIT 1
);
SET @has_inventory_deficit_ref := IF(
  COALESCE(@movement_ref_type, '') LIKE '%''INVENTORY_DEFICIT''%',
  1,
  0
);
SET @sql := IF(
  @movement_ref_type IS NOT NULL AND @has_inventory_deficit_ref = 0,
  CONCAT(
    'ALTER TABLE pos_stock_commit_line MODIFY COLUMN movement_ref_type ',
    LEFT(@movement_ref_type, CHAR_LENGTH(@movement_ref_type) - 1),
    ',''INVENTORY_DEFICIT'') NULL DEFAULT NULL'
  ),
  'SELECT ''skip add INVENTORY_DEFICIT movement reference'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Simpan asal HPP sementara secara jujur. Sebelum kolom enum ini diperluas,
-- MariaDB dapat menyimpan string kosong untuk DEFICIT_PENDING.
SET @cost_source_type := (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'pos_stock_commit_line'
    AND COLUMN_NAME = 'cost_source'
  LIMIT 1
);
SET @has_deficit_pending_cost := IF(
  COALESCE(@cost_source_type, '') LIKE '%''DEFICIT_PENDING''%',
  1,
  0
);
SET @sql := IF(
  @cost_source_type IS NOT NULL AND @has_deficit_pending_cost = 0,
  CONCAT(
    'ALTER TABLE pos_stock_commit_line MODIFY COLUMN cost_source ',
    LEFT(@cost_source_type, CHAR_LENGTH(@cost_source_type) - 1),
    ',''DEFICIT_PENDING'') NOT NULL DEFAULT ''FIFO'''
  ),
  'SELECT ''skip add DEFICIT_PENDING cost source'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

START TRANSACTION;

SET @active_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- Perbaiki hanya line bulan aktif yang memang memiliki defisit POS tepat
-- pada source line-nya. Untuk defisit penuh, HPP menjadi qty penuh x biaya
-- sementara. Untuk defisit parsial, biaya kekurangan ditambahkan satu kali
-- pada biaya lot yang sudah keluar.
UPDATE pos_stock_commit_line cl
INNER JOIN pos_stock_commit c
  ON c.id = cl.commit_id
INNER JOIN inv_stock_deficit d
  ON d.source_table = 'pos_stock_commit'
 AND d.source_id = c.id
 AND d.source_line_id = cl.id
SET
  cl.movement_ref_type = CASE
    WHEN COALESCE(cl.movement_ref_type, 'NONE') = 'NONE' THEN 'INVENTORY_DEFICIT'
    ELSE cl.movement_ref_type
  END,
  cl.movement_ref_id = CASE
    WHEN COALESCE(cl.movement_ref_type, 'NONE') IN ('NONE', 'INVENTORY_DEFICIT') THEN d.id
    ELSE cl.movement_ref_id
  END,
  cl.total_cost_live = CASE
    WHEN COALESCE(d.issued_qty, 0) <= 0.0001 THEN ROUND(
      COALESCE(cl.committed_qty, d.requested_qty, 0)
      * COALESCE(NULLIF(d.estimated_unit_cost, 0), cl.unit_cost_live, 0),
      6
    )
    WHEN ABS(
      COALESCE(cl.total_cost_live, 0)
      - (COALESCE(d.issued_qty, 0) * COALESCE(cl.unit_cost_live, 0))
    ) <= 0.01 THEN ROUND(
      COALESCE(cl.total_cost_live, 0)
      + GREATEST(0, COALESCE(d.requested_qty, 0) - COALESCE(d.issued_qty, 0))
        * COALESCE(NULLIF(d.estimated_unit_cost, 0), cl.unit_cost_live, 0),
      6
    )
    ELSE cl.total_cost_live
  END,
  cl.unit_cost_live = CASE
    WHEN COALESCE(cl.committed_qty, 0) > 0.0001
     AND COALESCE(d.requested_qty, 0) > COALESCE(d.issued_qty, 0) + 0.0001
      -- MySQL evaluates SET assignments from left to right. At this point
      -- total_cost_live already contains both the issued FIFO part and the
      -- provisional deficit part, so divide it once by the full recipe qty.
      THEN ROUND(COALESCE(cl.total_cost_live, 0) / cl.committed_qty, 6)
    ELSE cl.unit_cost_live
  END,
  cl.cost_source = 'DEFICIT_PENDING',
  cl.updated_at = NOW()
WHERE d.deficit_date >= @active_month
  AND d.deficit_date < DATE_ADD(@active_month, INTERVAL 1 MONTH)
  AND c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
  AND (
    COALESCE(cl.cost_source, '') <> 'DEFICIT_PENDING'
    OR COALESCE(cl.movement_ref_type, 'NONE') = 'NONE'
  );

COMMIT;

SELECT
  COUNT(*) AS active_month_deficit_pos_lines,
  SUM(CASE WHEN cl.movement_ref_type = 'INVENTORY_DEFICIT' THEN 1 ELSE 0 END) AS full_deficit_reference_lines,
  SUM(CASE WHEN cl.cost_source = 'DEFICIT_PENDING' THEN 1 ELSE 0 END) AS provisional_hpp_lines,
  ROUND(SUM(COALESCE(cl.total_cost_live, 0)), 2) AS provisional_hpp_total
FROM pos_stock_commit_line cl
INNER JOIN pos_stock_commit c ON c.id = cl.commit_id
INNER JOIN inv_stock_deficit d
  ON d.source_table = 'pos_stock_commit'
 AND d.source_id = c.id
 AND d.source_line_id = cl.id
WHERE d.deficit_date >= @active_month
  AND d.deficit_date < DATE_ADD(@active_month, INTERVAL 1 MONTH);
