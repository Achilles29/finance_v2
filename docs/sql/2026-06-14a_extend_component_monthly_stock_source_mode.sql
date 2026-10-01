-- ============================================================
-- 2026-06-14a  Extend ENUM source_mode di inv_component_monthly_stock
--
-- Generate function sekarang juga menulis ke inv_component_monthly_stock
-- dengan source_mode baru: OPNAME_GENERATE (bulan M) dan
-- OPENING_CARRY_FORWARD (bulan M+1 opening).
--
-- Idempotent: MODIFY COLUMN pada ENUM tidak merusak data yang ada.
-- Nilai LIVE dan REBUILD tetap terjaga; hanya menambah opsi baru.
-- ============================================================

ALTER TABLE `inv_component_monthly_stock`
MODIFY COLUMN `source_mode`
    ENUM('LIVE','REBUILD','OPNAME_GENERATE','OPENING_CARRY_FORWARD')
    NOT NULL DEFAULT 'LIVE';

-- Verifikasi
/*
SELECT DISTINCT source_mode, COUNT(*) AS rows_count
FROM inv_component_monthly_stock
GROUP BY source_mode
ORDER BY source_mode;
*/
