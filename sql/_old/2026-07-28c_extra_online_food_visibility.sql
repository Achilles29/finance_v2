SET NAMES utf8mb4;

-- Tambah flag visibilitas extra untuk channel Online Food / Online Order.
-- Default mengikuti perilaku self order lama agar extra yang sudah aktif tidak tiba-tiba hilang.

START TRANSACTION;

SET @had_show_online_food = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_extra'
    AND COLUMN_NAME = 'show_online_food'
);

ALTER TABLE mst_extra
  ADD COLUMN IF NOT EXISTS show_online_food TINYINT(1) NOT NULL DEFAULT 1 AFTER show_in_self_order,
  ADD KEY IF NOT EXISTS idx_mst_extra_show_online_food (show_online_food);

UPDATE mst_extra
SET show_online_food = show_in_self_order
WHERE @had_show_online_food = 0;

COMMIT;

SELECT 'mst_extra.show_online_food' AS object_name, COUNT(*) AS visible_rows
FROM mst_extra
WHERE show_online_food = 1;
