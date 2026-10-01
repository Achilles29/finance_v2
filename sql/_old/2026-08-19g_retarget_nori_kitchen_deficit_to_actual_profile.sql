SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-19g_retarget_nori_kitchen_deficit_to_actual_profile.sql
-- Tujuan :
-- 1) Memperbaiki identitas profil pada 12 defisit POS NORI Kitchen
--    yang tersimpan memakai profil katalog/gudang lama.
-- 2) Mengarahkan jejak defisit ke profil NORI Kitchen yang benar,
--    sehingga saldo dan lot aktif Kitchen dapat terbaca oleh halaman
--    Defisit Stok dan Recon.
--
-- Yang TIDAK diubah:
-- - qty defisit, stok bulanan, lot FIFO, movement, HPP, maupun kas.
-- - status defisit tetap OPEN; operator tetap wajib mengonfirmasi
--   penyelesaian melalui Recon Stok Fisik bila memang sudah selesai.
--
-- Guard:
-- - hanya memproses 12 source line POS yang telah diaudit.
-- - bila jumlah kandidat/target profil berbeda, UPDATE sengaja tidak jalan.
-- ============================================================

START TRANSACTION;

SET @old_profile_key := 'a45c1c094396395423b50e23d7ad1cbaad1df90192fd278b7322de42503e8154';
SET @actual_kitchen_profile_key := '6cce2b824802936592d5da1266fbc0bcd9748ee1';
SET @expected_rows := 12;

DROP TEMPORARY TABLE IF EXISTS tmp_nori_kitchen_deficit_profile_repair;
CREATE TEMPORARY TABLE tmp_nori_kitchen_deficit_profile_repair AS
SELECT
  d.id,
  SHA2(CONCAT(
    d.stock_domain, '|',
    DATE_FORMAT(d.deficit_date, '%Y-%m-%d'), '|',
    d.location_scope, '|',
    CAST(COALESCE(d.division_id, 0) AS CHAR), '|',
    COALESCE(d.destination_type, ''), '|',
    CAST(COALESCE(d.item_id, 0) AS CHAR), '|',
    CAST(COALESCE(d.material_id, 0) AS CHAR), '|',
    CAST(COALESCE(d.component_id, 0) AS CHAR), '|',
    CAST(COALESCE(d.content_uom_id, 0) AS CHAR), '|',
    @actual_kitchen_profile_key, '|',
    d.source_table, '|',
    CAST(COALESCE(d.source_id, 0) AS CHAR), '|',
    CAST(COALESCE(d.source_line_id, 0) AS CHAR)
  ), 256) AS next_deficit_key
FROM inv_stock_deficit d
WHERE d.status = 'OPEN'
  AND d.stock_domain = 'MATERIAL'
  AND d.location_scope = 'DIVISION'
  AND d.division_id = 3
  AND d.destination_type = 'KITCHEN'
  AND d.item_id = 122
  AND d.material_id = 148
  AND d.buy_uom_id = 3
  AND d.content_uom_id = 11
  AND d.profile_key = @old_profile_key
  AND d.source_table = 'pos_stock_commit'
  AND d.source_line_id IN (
    148489, 148819, 148837, 149129, 149281, 149535,
    149909, 149943, 150605, 150621, 150627, 151099
  );

ALTER TABLE tmp_nori_kitchen_deficit_profile_repair
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_nori_next_key (next_deficit_key);

SELECT
  'candidate_rows' AS check_name,
  COUNT(*) AS total,
  @expected_rows AS expected_total,
  CASE WHEN COUNT(*) = @expected_rows THEN 'SAFE_TO_CONTINUE' ELSE 'STOP_NO_UPDATE' END AS result
FROM tmp_nori_kitchen_deficit_profile_repair
UNION ALL
SELECT
  'target_kitchen_profile_rows',
  COUNT(*),
  1,
  CASE WHEN COUNT(*) = 1 THEN 'SAFE_TO_CONTINUE' ELSE 'STOP_NO_UPDATE' END
FROM inv_division_monthly_stock s
WHERE s.month_key = '2026-08-01'
  AND s.division_id = 3
  AND s.destination_type = 'KITCHEN'
  AND s.item_id = 122
  AND s.material_id = 148
  AND s.buy_uom_id = 3
  AND s.content_uom_id = 11
  AND s.profile_key = @actual_kitchen_profile_key
  AND COALESCE(s.closing_qty_content, 0) > 0.0001
UNION ALL
SELECT
  'new_key_conflicts',
  COUNT(*),
  0,
  CASE WHEN COUNT(*) = 0 THEN 'SAFE_TO_CONTINUE' ELSE 'STOP_NO_UPDATE' END
FROM tmp_nori_kitchen_deficit_profile_repair t
JOIN inv_stock_deficit d ON d.deficit_key = t.next_deficit_key
 AND d.id <> t.id;

UPDATE inv_stock_deficit d
JOIN tmp_nori_kitchen_deficit_profile_repair t ON t.id = d.id
SET
  d.profile_key = @actual_kitchen_profile_key,
  d.deficit_key = t.next_deficit_key,
  d.notes = LEFT(CONCAT_WS(' | ', NULLIF(d.notes, ''), 'Repair 2026-08-19: profile defisit diarahkan ke profil NORI Kitchen yang dipakai stok aktif.'), 255),
  d.updated_at = CURRENT_TIMESTAMP
WHERE (SELECT COUNT(*) FROM tmp_nori_kitchen_deficit_profile_repair) = @expected_rows
  AND EXISTS (
    SELECT 1
    FROM inv_division_monthly_stock s
    WHERE s.month_key = '2026-08-01'
      AND s.division_id = 3
      AND s.destination_type = 'KITCHEN'
      AND s.item_id = 122
      AND s.material_id = 148
      AND s.buy_uom_id = 3
      AND s.content_uom_id = 11
      AND s.profile_key = @actual_kitchen_profile_key
      AND COALESCE(s.closing_qty_content, 0) > 0.0001
  )
  AND NOT EXISTS (
    SELECT 1
    FROM inv_stock_deficit conflict_row
    WHERE conflict_row.deficit_key = t.next_deficit_key
      AND conflict_row.id <> d.id
  );

COMMIT;

SELECT
  profile_key,
  COUNT(*) AS open_event_count,
  ROUND(SUM(qty_remaining), 4) AS open_qty,
  MIN(deficit_date) AS first_date,
  MAX(deficit_date) AS last_date
FROM inv_stock_deficit
WHERE status = 'OPEN'
  AND stock_domain = 'MATERIAL'
  AND location_scope = 'DIVISION'
  AND division_id = 3
  AND destination_type = 'KITCHEN'
  AND item_id = 122
  AND material_id = 148
GROUP BY profile_key
ORDER BY open_qty DESC;
