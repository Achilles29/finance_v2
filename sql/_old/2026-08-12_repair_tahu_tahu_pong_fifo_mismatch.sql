START TRANSACTION;

-- Repair mismatch dashboard/reconcile untuk:
-- - TAHU (Kitchen Reguler)
-- - TAHU PONG (Kitchen Reguler)
--
-- Pola masalah:
-- - monthly_stock dan movement sudah benar
-- - tetapi ada lot FIFO yang tertutup paksa oleh issue log dengan source_table='inv_material_fifo_lot'
-- - akibatnya nilai/qty lot FIFO lebih kecil dari stok bulanan
--
-- Hasil akhir target:
-- - TAHU total lot OPEN = 21.0000 pcs
-- - TAHU PONG total lot OPEN = 10.0000 pcs
-- - issue log penutup paksa dibatalkan

CREATE TABLE IF NOT EXISTS zz_bak_20260812_tahu_lot AS
SELECT *
FROM inv_material_fifo_lot
WHERE id IN (7157, 7263, 7325);

CREATE TABLE IF NOT EXISTS zz_bak_20260812_tahu_issue_log AS
SELECT *
FROM inv_material_fifo_issue_log
WHERE id IN (52543, 52545);

CREATE TABLE IF NOT EXISTS zz_bak_20260812_tahu_issue_line AS
SELECT *
FROM inv_material_fifo_issue_line
WHERE id IN (54843, 54845);

-- Hapus issue line penutup paksa lot
DELETE FROM inv_material_fifo_issue_line
WHERE id IN (54843, 54845);

-- Hapus issue log penutup paksa lot
DELETE FROM inv_material_fifo_issue_log
WHERE id IN (52543, 52545)
  AND source_table = 'inv_material_fifo_lot';

-- Buka kembali lot TAHU yang seharusnya masih aktif 11.5 pcs
UPDATE inv_material_fifo_lot
SET qty_out = 6.5000,
    qty_balance = 11.5000,
    status = 'OPEN',
    updated_at = NOW()
WHERE id = 7263
  AND material_id = 196
  AND division_id = 3
  AND destination_type = 'KITCHEN';

-- Buka kembali lot TAHU PONG yang seharusnya masih aktif 10 pcs
UPDATE inv_material_fifo_lot
SET qty_out = 30.0000,
    qty_balance = 10.0000,
    status = 'OPEN',
    updated_at = NOW()
WHERE id = 7157
  AND material_id = 197
  AND division_id = 3
  AND destination_type = 'KITCHEN';

COMMIT;
