SET NAMES utf8mb4;

-- ============================================================
-- Cari BAR division_id secara dinamis
-- ============================================================
SET @bar_division_id := (
  SELECT id FROM mst_operational_division
  WHERE UPPER(TRIM(name)) = 'BAR'
  LIMIT 1
);

SELECT @bar_division_id AS bar_division_id_akan_dipakai;

-- ============================================================
-- STEP 1: Hapus duplikat commit_id=60 yang sudah ada di KITCHEN
-- ============================================================
DELETE FROM inv_stock_movement_log
WHERE id = 1453;

SELECT ROW_COUNT() AS deleted_duplicate;

-- ============================================================
-- STEP 2: Pindahkan MANAJEMEN OTHER USAGE_OUT dari pos_stock_commit
--         ke BAR (update division_id + destination_type)
--         KECUALI commit_id=60 yang sudah dihapus di step 1
-- ============================================================
UPDATE inv_stock_movement_log
SET
  division_id      = @bar_division_id,
  destination_type = 'BAR'
WHERE movement_scope   = 'DIVISION'
  AND destination_type = 'OTHER'
  AND ref_table        = 'pos_stock_commit'
  AND movement_type    = 'USAGE_OUT';

SELECT ROW_COUNT() AS moved_to_bar;

-- ============================================================
-- STEP 3: Hapus monthly stock MANAJEMEN bulan ini untuk material
--         yang sudah dipindahkan ke BAR (agar tidak stale)
--         → sistem akan rebuild saat diakses berikutnya
-- ============================================================
DELETE dms FROM inv_division_monthly_stock dms
JOIN mst_operational_division d ON d.id = dms.division_id
WHERE d.name LIKE '%MANAJEMEN%'
  AND dms.month_key = DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND dms.material_id IN (
    SELECT DISTINCT m.material_id
    FROM inv_stock_movement_log m
    WHERE m.division_id = @bar_division_id
      AND m.destination_type = 'BAR'
      AND m.ref_table = 'pos_stock_commit'
      AND m.movement_type = 'USAGE_OUT'
      AND m.material_id IS NOT NULL
  );

SELECT ROW_COUNT() AS deleted_manajemen_monthly_rows;

-- ============================================================
-- VERIFIKASI: tidak boleh ada USAGE_OUT dari pos_stock_commit
--             di destination OTHER lagi
-- ============================================================
SELECT COUNT(*) AS sisa_wrong_movement
FROM inv_stock_movement_log
WHERE movement_scope   = 'DIVISION'
  AND destination_type = 'OTHER'
  AND ref_table        = 'pos_stock_commit'
  AND movement_type    = 'USAGE_OUT';
