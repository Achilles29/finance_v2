-- =============================================================================
-- REPAIR: Consolidate duplicate profile_keys for same-product inventory rows
-- Root cause: pur_purchase_order_line.profile_key hash included vendor_id &
--             unit_price, causing same product to appear under different keys
--             when the hash formula changed between PO creations.
--
-- Affected items: item_id 30 (BERAS RAJA LELE), 108 (KOPI LELET), 135 (SINGLE ORIGIN)
--
-- Canonical keys (latest last_purchase_date in mst_purchase_catalog):
--   item 30  → a2ec5ca7fe25cbeb8d17d45753321577c8d5c25a3e6d0ee167bda96bddf67633
--   item 108 → 9e800f288a9d01b27f0e521726583e05b86d4812d54d21794d7293147ab6a486
--   item 135 → 49433b59332a8829d64ec38b7cf694946bbcc7e7046ace93b5ec43923b4e7966
-- =============================================================================

START TRANSACTION;

-- ===========================================================================
-- 1. KOPI LELET (item_id=108)
--    Old key e82376... has qty=0 (zeroed out). Simply delete old balance row
--    and remap daily rollup + movement log to canonical key.
-- ===========================================================================

-- 1a. Delete zero-balance row for old key
DELETE FROM inv_warehouse_stock_balance
WHERE item_id = 108
  AND profile_key = 'e82376fabf4b8d3cbe2bd575871bd13a76b19f74017e2770c3f8d471b8a53d9d';

-- 1b. Remap daily rollup (e82376 dates: 2026-05-04, 2026-05-14 — no conflict with 9e800f which only has 2026-05-18)
UPDATE inv_warehouse_daily_rollup
SET profile_key = '9e800f288a9d01b27f0e521726583e05b86d4812d54d21794d7293147ab6a486'
WHERE item_id = 108
  AND profile_key = 'e82376fabf4b8d3cbe2bd575871bd13a76b19f74017e2770c3f8d471b8a53d9d';

-- 1c. Remap movement log
UPDATE inv_stock_movement_log
SET profile_key = '9e800f288a9d01b27f0e521726583e05b86d4812d54d21794d7293147ab6a486'
WHERE item_id = 108
  AND profile_key = 'e82376fabf4b8d3cbe2bd575871bd13a76b19f74017e2770c3f8d471b8a53d9d';

-- 1d. Remap PO order lines
UPDATE pur_purchase_order_line
SET profile_key = '9e800f288a9d01b27f0e521726583e05b86d4812d54d21794d7293147ab6a486'
WHERE profile_key = 'e82376fabf4b8d3cbe2bd575871bd13a76b19f74017e2770c3f8d471b8a53d9d';

-- 1e. Deactivate duplicate catalog entries (keep 9e800f which is newest)
UPDATE mst_purchase_catalog
SET is_active = 0
WHERE item_id = 108
  AND profile_key IN (
    'e82376fabf4b8d3cbe2bd575871bd13a76b19f74017e2770c3f8d471b8a53d9d',
    '7d256a2e8bc2f8f6ea7bc9d6e643627734a4ef62'
  );

-- ===========================================================================
-- 2. SINGLE ORIGIN HAYATI (item_id=135)
--    49433b... = 2000g at 300.00 (KEEP — canonical, latest catalog 2026-05-05)
--    6b01fb... = 1000g at 300.00 (MERGE → weighted avg = 300.00, delete after)
--    55856a... = 0g (SHA1 legacy key, delete)
-- ===========================================================================

-- 2a. Merge 6b01fb balance quantities into canonical 49433b
--     Weighted avg_cost: same price 300.00 on both → stays 300.00
UPDATE inv_warehouse_stock_balance
SET qty_buy_balance     = qty_buy_balance     + 1.0000,
    qty_content_balance = qty_content_balance + 1000.0000
    -- avg_cost_per_content stays 300.000000 (same for both legs)
WHERE item_id = 135
  AND profile_key = '49433b59332a8829d64ec38b7cf694946bbcc7e7046ace93b5ec43923b4e7966';

-- 2b. Delete merged and zero-balance rows
DELETE FROM inv_warehouse_stock_balance
WHERE item_id = 135
  AND profile_key IN (
    '6b01fbe62f67acfad30443f6100c32be760cfcc445e8c9a92e97680438a93fb4',
    '55856a9786b4ca0851053eca0465208f2de3421f'
  );

-- 2c. Daily rollup: 6b01fb has 2026-05-04 row that conflicts with 49433b 2026-05-04.
--     Merge by adding quantities to the canonical row, then delete the old row.
UPDATE inv_warehouse_daily_rollup dst
JOIN (
  SELECT in_qty_buy, in_qty_content, closing_qty_buy, closing_qty_content
  FROM inv_warehouse_daily_rollup
  WHERE item_id = 135
    AND profile_key = '6b01fbe62f67acfad30443f6100c32be760cfcc445e8c9a92e97680438a93fb4'
    AND movement_date = '2026-05-04'
) src ON 1=1
SET dst.in_qty_buy          = dst.in_qty_buy          + src.in_qty_buy,
    dst.in_qty_content      = dst.in_qty_content      + src.in_qty_content,
    dst.closing_qty_buy     = dst.closing_qty_buy     + src.closing_qty_buy,
    dst.closing_qty_content = dst.closing_qty_content + src.closing_qty_content
WHERE dst.item_id = 135
  AND dst.profile_key  = '49433b59332a8829d64ec38b7cf694946bbcc7e7046ace93b5ec43923b4e7966'
  AND dst.movement_date = '2026-05-04';

-- 2d. Cascade: 49433b 2026-05-05 opening must reflect the merged 2026-05-04 closing
UPDATE inv_warehouse_daily_rollup
SET opening_qty_buy     = opening_qty_buy     + 1.0000,
    opening_qty_content = opening_qty_content + 1000.0000,
    closing_qty_buy     = closing_qty_buy     + 1.0000,
    closing_qty_content = closing_qty_content + 1000.0000
WHERE item_id = 135
  AND profile_key = '49433b59332a8829d64ec38b7cf694946bbcc7e7046ace93b5ec43923b4e7966'
  AND movement_date = '2026-05-05';

-- 2e. Delete merged 6b01fb daily rollup row
DELETE FROM inv_warehouse_daily_rollup
WHERE item_id = 135
  AND profile_key = '6b01fbe62f67acfad30443f6100c32be760cfcc445e8c9a92e97680438a93fb4'
  AND movement_date = '2026-05-04';

-- 2f. Remap 55856a (SHA1 legacy) daily rollup 2026-05-14 → 49433b
--     (no 49433b row for 2026-05-14, so UPDATE is safe — no conflict)
UPDATE inv_warehouse_daily_rollup
SET profile_key = '49433b59332a8829d64ec38b7cf694946bbcc7e7046ace93b5ec43923b4e7966'
WHERE item_id = 135
  AND profile_key = '55856a9786b4ca0851053eca0465208f2de3421f';

-- 2g. Remap movement log for 6b01fb and 55856a
UPDATE inv_stock_movement_log
SET profile_key = '49433b59332a8829d64ec38b7cf694946bbcc7e7046ace93b5ec43923b4e7966'
WHERE item_id = 135
  AND profile_key IN (
    '6b01fbe62f67acfad30443f6100c32be760cfcc445e8c9a92e97680438a93fb4',
    '55856a9786b4ca0851053eca0465208f2de3421f'
  );

-- 2h. Remap PO order lines
UPDATE pur_purchase_order_line
SET profile_key = '49433b59332a8829d64ec38b7cf694946bbcc7e7046ace93b5ec43923b4e7966'
WHERE profile_key IN (
    '6b01fbe62f67acfad30443f6100c32be760cfcc445e8c9a92e97680438a93fb4',
    '55856a9786b4ca0851053eca0465208f2de3421f'
  );

-- 2i. Deactivate duplicate catalog entries (keep 49433b which is newest)
UPDATE mst_purchase_catalog
SET is_active = 0
WHERE item_id = 135
  AND profile_key IN (
    '6b01fbe62f67acfad30443f6100c32be760cfcc445e8c9a92e97680438a93fb4',
    '55856a9786b4ca0851053eca0465208f2de3421f',
    '57db99d5e701faae5cccbf2218867cf694b75985'
  );

-- ===========================================================================
-- 3. BERAS RAJA LELE (item_id=30)
--    a2ec5c... = 10000g at 16.00 (KEEP — canonical, latest catalog 2026-05-06)
--    373ef6... = 40000g at 15.50  \
--    bab224... = 20000g at 15.00   > MERGE all into a2ec5c
--    e72419... = 10000g at 16.00  /
--
--    Weighted avg_cost = (40000*15.50 + 20000*15.00 + 10000*16.00 + 10000*16.00) / 80000
--                      = 1,240,000 / 80,000 = 15.500000
-- ===========================================================================

-- 3a. Merge all 3 old balance rows into canonical a2ec5c
UPDATE inv_warehouse_stock_balance
SET qty_buy_balance     = qty_buy_balance     + 7.0000,   -- 4 + 2 + 1
    qty_content_balance = qty_content_balance + 70000.0000, -- 40000+20000+10000
    avg_cost_per_content = 15.500000
WHERE item_id = 30
  AND profile_key = 'a2ec5ca7fe25cbeb8d17d45753321577c8d5c25a3e6d0ee167bda96bddf67633';

-- 3b. Delete merged balance rows
DELETE FROM inv_warehouse_stock_balance
WHERE item_id = 30
  AND profile_key IN (
    '373ef6881fa3b2e9064db798340cb69240a27d66f34633bafa53205df3f8676e',
    'bab224d9f1782f7697c86696388d061e952fcb8f3c2e7660f8e897682fd50851',
    'e7241937b50a5ef9177ead2a1fef7cc3b6bee25d2f21a429f98d80caa58f89d1'
  );

-- 3c. Daily rollup for BERAS:
--     e72419 2026-05-04 (in=40000) and bab224 2026-05-04 (in=20000) both need merging
--     into a2ec5c 2026-05-04. a2ec5c currently has no 2026-05-04 row.
--     So: rename e72419 2026-05-04 row → a2ec5c, then add bab224 2026-05-04 quantities.

-- Step 1: Rename e72419 2026-05-04 to a2ec5c (it's the first to be merged)
UPDATE inv_warehouse_daily_rollup
SET profile_key = 'a2ec5ca7fe25cbeb8d17d45753321577c8d5c25a3e6d0ee167bda96bddf67633'
WHERE item_id = 30
  AND profile_key = 'e7241937b50a5ef9177ead2a1fef7cc3b6bee25d2f21a429f98d80caa58f89d1'
  AND movement_date = '2026-05-04';

-- Step 2: Add bab224 2026-05-04 quantities to the newly renamed a2ec5c 2026-05-04
UPDATE inv_warehouse_daily_rollup dst
JOIN (
  SELECT in_qty_buy, in_qty_content, closing_qty_buy, closing_qty_content
  FROM inv_warehouse_daily_rollup
  WHERE item_id = 30
    AND profile_key = 'bab224d9f1782f7697c86696388d061e952fcb8f3c2e7660f8e897682fd50851'
    AND movement_date = '2026-05-04'
) src ON 1=1
SET dst.in_qty_buy          = dst.in_qty_buy          + src.in_qty_buy,
    dst.in_qty_content      = dst.in_qty_content      + src.in_qty_content,
    dst.closing_qty_buy     = dst.closing_qty_buy     + src.closing_qty_buy,
    dst.closing_qty_content = dst.closing_qty_content + src.closing_qty_content
WHERE dst.item_id = 30
  AND dst.profile_key  = 'a2ec5ca7fe25cbeb8d17d45753321577c8d5c25a3e6d0ee167bda96bddf67633'
  AND dst.movement_date = '2026-05-04';

-- Step 3: Delete bab224 2026-05-04 row (merged into a2ec5c)
DELETE FROM inv_warehouse_daily_rollup
WHERE item_id = 30
  AND profile_key = 'bab224d9f1782f7697c86696388d061e952fcb8f3c2e7660f8e897682fd50851'
  AND movement_date = '2026-05-04';

-- Step 4: Rename e72419 2026-05-05 → a2ec5c (no conflict, a2ec5c has only 2026-05-06)
UPDATE inv_warehouse_daily_rollup
SET profile_key = 'a2ec5ca7fe25cbeb8d17d45753321577c8d5c25a3e6d0ee167bda96bddf67633'
WHERE item_id = 30
  AND profile_key = 'e7241937b50a5ef9177ead2a1fef7cc3b6bee25d2f21a429f98d80caa58f89d1'
  AND movement_date = '2026-05-05';

-- 3d. Remap movement log (373ef6 and bab224 have no movement log entries — OK to skip;
--     e72419 has 4 PURCHASE_IN entries)
UPDATE inv_stock_movement_log
SET profile_key = 'a2ec5ca7fe25cbeb8d17d45753321577c8d5c25a3e6d0ee167bda96bddf67633'
WHERE item_id = 30
  AND profile_key IN (
    '373ef6881fa3b2e9064db798340cb69240a27d66f34633bafa53205df3f8676e',
    'bab224d9f1782f7697c86696388d061e952fcb8f3c2e7660f8e897682fd50851',
    'e7241937b50a5ef9177ead2a1fef7cc3b6bee25d2f21a429f98d80caa58f89d1'
  );

-- 3e. Remap PO order lines
UPDATE pur_purchase_order_line
SET profile_key = 'a2ec5ca7fe25cbeb8d17d45753321577c8d5c25a3e6d0ee167bda96bddf67633'
WHERE profile_key IN (
    '373ef6881fa3b2e9064db798340cb69240a27d66f34633bafa53205df3f8676e',
    'bab224d9f1782f7697c86696388d061e952fcb8f3c2e7660f8e897682fd50851',
    'e7241937b50a5ef9177ead2a1fef7cc3b6bee25d2f21a429f98d80caa58f89d1'
  );

-- 3f. Deactivate duplicate catalog entries (keep a2ec5c which is newest)
UPDATE mst_purchase_catalog
SET is_active = 0
WHERE item_id = 30
  AND content_per_buy = 10000.000000
  AND profile_key IN (
    '373ef6881fa3b2e9064db798340cb69240a27d66f34633bafa53205df3f8676e',
    'bab224d9f1782f7697c86696388d061e952fcb8f3c2e7660f8e897682fd50851',
    'e7241937b50a5ef9177ead2a1fef7cc3b6bee25d2f21a429f98d80caa58f89d1',
    'ca33de1a2954acd4064bbac146b45c4ded1f498f',
    'ca2ab9807cf49cf81f1103d117590f842c9eb245'
  );

-- ===========================================================================
-- VERIFICATION (run before COMMIT to review changes)
-- ===========================================================================
SELECT 'Balance after fix:' AS step;
SELECT item_id, profile_name, profile_brand, profile_content_per_buy,
       LEFT(profile_key,12) AS key_prefix,
       qty_buy_balance, qty_content_balance, avg_cost_per_content
FROM inv_warehouse_stock_balance
WHERE item_id IN (30, 108, 135)
ORDER BY item_id, profile_name;

SELECT 'Daily rollup after fix:' AS step;
SELECT item_id, LEFT(profile_key,12) AS key_pfx, movement_date,
       opening_qty_content, in_qty_content, out_qty_content, closing_qty_content
FROM inv_warehouse_daily_rollup
WHERE item_id IN (30, 108, 135)
ORDER BY item_id, movement_date;

-- If results look correct:
COMMIT;

-- On error or unexpected results:
-- ROLLBACK;
