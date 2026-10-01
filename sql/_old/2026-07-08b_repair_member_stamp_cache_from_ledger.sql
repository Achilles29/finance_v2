SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-08b_repair_member_stamp_cache_from_ledger.sql
-- Tujuan :
-- 1) Menormalkan stamp_balance_cache member dari ledger aktual
-- 2) Menghapus efek cache lama/stale yang membuat hampir semua
--    member terlihat punya stamp walau tidak ada pos_stamp_ledger
-- 3) Sumber kebenaran stamp adalah pos_stamp_ledger:
--    SUM(stamp_in - stamp_out) per member
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_member_stamp_cache_before;
CREATE TEMPORARY TABLE tmp_member_stamp_cache_before AS
SELECT
  m.id,
  m.member_no,
  m.member_name,
  COALESCE(m.stamp_balance_cache, 0) AS before_stamp_balance_cache,
  COALESCE(l.ledger_stamp_balance, 0) AS ledger_stamp_balance
FROM crm_member m
LEFT JOIN (
  SELECT
    member_id,
    ROUND(SUM(COALESCE(stamp_in, 0) - COALESCE(stamp_out, 0)), 4) AS ledger_stamp_balance
  FROM pos_stamp_ledger
  GROUP BY member_id
) l ON l.member_id = m.id
WHERE ROUND(COALESCE(m.stamp_balance_cache, 0), 4) <> ROUND(COALESCE(l.ledger_stamp_balance, 0), 4);

UPDATE crm_member m
LEFT JOIN (
  SELECT
    member_id,
    ROUND(SUM(COALESCE(stamp_in, 0) - COALESCE(stamp_out, 0)), 4) AS ledger_stamp_balance
  FROM pos_stamp_ledger
  GROUP BY member_id
) l ON l.member_id = m.id
SET
  m.stamp_balance_cache = COALESCE(l.ledger_stamp_balance, 0),
  m.updated_at = CURRENT_TIMESTAMP
WHERE ROUND(COALESCE(m.stamp_balance_cache, 0), 4) <> ROUND(COALESCE(l.ledger_stamp_balance, 0), 4);

COMMIT;

SELECT
  'members_cache_repaired' AS metric,
  COUNT(*) AS total
FROM tmp_member_stamp_cache_before

UNION ALL

SELECT
  'members_without_ledger_set_to_zero',
  COUNT(*)
FROM tmp_member_stamp_cache_before
WHERE ROUND(COALESCE(ledger_stamp_balance, 0), 4) = 0
  AND ROUND(COALESCE(before_stamp_balance_cache, 0), 4) <> 0;

SELECT
  id,
  member_no,
  member_name,
  before_stamp_balance_cache,
  ledger_stamp_balance
FROM tmp_member_stamp_cache_before
ORDER BY ABS(before_stamp_balance_cache - ledger_stamp_balance) DESC, id
LIMIT 50;
