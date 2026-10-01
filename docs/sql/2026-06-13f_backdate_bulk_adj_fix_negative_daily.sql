-- ============================================================
-- FIX: Bulk backdate ADJ_PLUS ke 2026-06-01
--
-- Masalah: ADJ_PLUS sudah dibuat dari UI tapi semua bertanggal
-- 2026-06-13, sedangkan hari yang minus ada di 2026-06-01 s/d 12.
-- Adjustment bertanggal 06-13 hanya menambah saldo hari 13, tidak
-- memperbaiki hari sebelumnya.
--
-- Solusi:
--   PART A — Backdate 17 entry movement_log + lot → 2026-06-01
--   PART B — Top-up 4 komponen yang qty-nya tidak cukup
--   PART C — Insert baru 5 komponen yang belum ada adj-nya
--
-- Setelah commit: jalankan Repair di /production/component-reconcile
-- untuk semua komponen yang diubah.
-- ============================================================

START TRANSACTION;

-- ══════════════════════════════════════════════════════════════
-- PART A — Backdate movement_log → 2026-06-01
--   Ini memindahkan ADJ_PLUS ke awal bulan sehingga rolling
--   balance dari hari 1 sudah termasuk stok yang di-adjust.
-- ══════════════════════════════════════════════════════════════

-- 15 entry dari 2026-06-13
UPDATE inv_component_movement_log
SET movement_date = '2026-06-01', movement_datetime = '2026-06-01 07:00:00'
WHERE id IN (
  4241,  -- SIMPLE SYRUP (qty 215)
  4240,  -- JELLY CHOCOLATE BASE (qty 480)
  4249,  -- SAMBAL REBUS IGA (qty 10 — top-up di PART B)
  4248,  -- SAMBAL DABU-DABU (qty 139)
  4246,  -- POACHED EEG (qty 1)
  4251,  -- CHICKEN CUBE 15 (qty 1 — top-up di PART B)
  4252,  -- CHICKEN SLICE (KATSU) (qty 4)
  4253,  -- CHICKEN SLICE SUSHI (qty 3)
  4250,  -- BUN BURGER (qty 1 — top-up di PART B)
  4255,  -- NUGET (qty 15)
  4256,  -- PISANG (qty 1 — top-up di PART B)
  4244,  -- PISANG BAR (qty 5)
  4245,  -- SEMANGKA (qty 5)
  4243,  -- LIME SLICE (qty 21)
  4242   -- LEMON SLICE (qty 33)
);
SELECT ROW_COUNT() AS a1_ml_backdated_from_0613;

-- 1 entry dari 2026-06-11 (CHICKEN CUBE 40, cukup qty)
UPDATE inv_component_movement_log
SET movement_date = '2026-06-01', movement_datetime = '2026-06-01 07:00:00'
WHERE id = 3133;
SELECT ROW_COUNT() AS a2_ml_backdated_from_0611;

-- 1 entry dari 2026-06-30 (BROTH SOYU — tanggal masa depan)
UPDATE inv_component_movement_log
SET movement_date = '2026-06-01', movement_datetime = '2026-06-01 07:00:00'
WHERE id = 251;
SELECT ROW_COUNT() AS a3_ml_backdated_from_0630;

-- Backdate lot receipt_date untuk semua 17 entry
UPDATE inv_component_lot
SET receipt_date = '2026-06-01', updated_at = NOW()
WHERE id IN (
  391,  -- SIMPLE SYRUP
  390,  -- JELLY CHOCOLATE BASE
  399,  -- SAMBAL REBUS IGA
  398,  -- SAMBAL DABU-DABU
  396,  -- POACHED EEG
  401,  -- CHICKEN CUBE 15
  402,  -- CHICKEN SLICE (KATSU)
  403,  -- CHICKEN SLICE SUSHI
  400,  -- BUN BURGER
  405,  -- NUGET
  406,  -- PISANG
  394,  -- PISANG BAR
  395,  -- SEMANGKA
  393,  -- LIME SLICE
  392,  -- LEMON SLICE
  327,  -- CHICKEN CUBE 40 (dari 06-11)
  193   -- BROTH SOYU (dari 06-30)
);
SELECT ROW_COUNT() AS a4_lots_backdated;

-- ══════════════════════════════════════════════════════════════
-- PART B — Top-up: komponen yang qty adj-nya tidak cukup
--   Cukup tambah movement_log. Lot baru dibuat agar FIFO valid.
-- ══════════════════════════════════════════════════════════════

INSERT INTO inv_component_movement_log
  (movement_no, movement_date, movement_datetime, location_type, division_id,
   component_id, uom_id, movement_type, qty_in, qty_out, unit_cost, total_cost,
   source_module, source_table, source_id, lot_no_snapshot, notes, created_by, created_at)
VALUES
  -- SAMBAL REBUS IGA: was 10, need 40 → +30
  ('ICM20260601F001', '2026-06-01', '2026-06-01 07:05:00', 'KITCHEN', 3,
   25, 11, 'ADJUSTMENT_PLUS', 30.0000, 0, 12.000000, 360.00,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100025P', 'Bulk fix: top-up saldo minus Juni 2026', 1, NOW()),

  -- BUN BURGER: was 1, need 3 → +2
  ('ICM20260601F002', '2026-06-01', '2026-06-01 07:05:00', 'KITCHEN', 3,
   100, 26, 'ADJUSTMENT_PLUS', 2.0000, 0, 2500.000000, 5000.00,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100100P', 'Bulk fix: top-up saldo minus Juni 2026', 1, NOW()),

  -- CHICKEN CUBE 15: was 1, need 2 → +1
  ('ICM20260601F003', '2026-06-01', '2026-06-01 07:05:00', 'KITCHEN', 3,
   78, 26, 'ADJUSTMENT_PLUS', 1.0000, 0, 645.000000, 645.00,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100078P', 'Bulk fix: top-up saldo minus Juni 2026', 1, NOW()),

  -- PISANG: was 1, need 8 → +7
  ('ICM20260601F004', '2026-06-01', '2026-06-01 07:05:00', 'KITCHEN', 3,
   127, 26, 'ADJUSTMENT_PLUS', 7.0000, 0, 2500.000000, 17500.00,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100127P', 'Bulk fix: top-up saldo minus Juni 2026', 1, NOW());

SELECT ROW_COUNT() AS b1_topup_ml_inserted;

INSERT INTO inv_component_lot
  (location_type, division_id, component_id, uom_id, lot_no, receipt_date,
   unit_cost, qty_in_total, qty_out_total, qty_balance,
   source_module, source_table, source_id, status, created_at, updated_at)
VALUES
  ('KITCHEN', 3, 25,  11, 'ICAFIX2026060100025P', '2026-06-01', 12.000000,     30.0000, 0, 30.0000, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW()),
  ('KITCHEN', 3, 100, 26, 'ICAFIX2026060100100P', '2026-06-01', 2500.000000,    2.0000, 0,  2.0000, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW()),
  ('KITCHEN', 3, 78,  26, 'ICAFIX2026060100078P', '2026-06-01', 645.000000,     1.0000, 0,  1.0000, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW()),
  ('KITCHEN', 3, 127, 26, 'ICAFIX2026060100127P', '2026-06-01', 2500.000000,    7.0000, 0,  7.0000, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW());

SELECT ROW_COUNT() AS b2_topup_lots_inserted;

-- ══════════════════════════════════════════════════════════════
-- PART C — Insert baru: 5 komponen tanpa adj apapun di Juni
-- ══════════════════════════════════════════════════════════════

INSERT INTO inv_component_movement_log
  (movement_no, movement_date, movement_datetime, location_type, division_id,
   component_id, uom_id, movement_type, qty_in, qty_out, unit_cost, total_cost,
   source_module, source_table, source_id, lot_no_snapshot, notes, created_by, created_at)
VALUES
  -- JELLY LYCHEE BASE (BAR, div=2, uom=11): +180
  ('ICM20260601F005', '2026-06-01', '2026-06-01 07:10:00', 'BAR', 2,
   11, 11, 'ADJUSTMENT_PLUS', 180.0000, 0, 30.501753, 5490.32,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100011P', 'Bulk fix: ADJ_PLUS saldo minus Juni 2026', 1, NOW()),

  -- SAUCE BANGKOK (KITCHEN, div=3, uom=11): +227.0756
  ('ICM20260601F006', '2026-06-01', '2026-06-01 07:10:00', 'KITCHEN', 3,
   17, 11, 'ADJUSTMENT_PLUS', 227.0756, 0, 19.597442, 4449.44,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100017P', 'Bulk fix: ADJ_PLUS saldo minus Juni 2026', 1, NOW()),

  -- COLESLAW SALAD (KITCHEN, div=3, uom=11): +180.0120
  ('ICM20260601F007', '2026-06-01', '2026-06-01 07:10:00', 'KITCHEN', 3,
   36, 11, 'ADJUSTMENT_PLUS', 180.0120, 0, 22.970445, 4135.05,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100036P', 'Bulk fix: ADJ_PLUS saldo minus Juni 2026', 1, NOW()),

  -- INDOMIE REBUS (KITCHEN, div=3, uom=1): +3
  ('ICM20260601F008', '2026-06-01', '2026-06-01 07:10:00', 'KITCHEN', 3,
   102, 1, 'ADJUSTMENT_PLUS', 3.0000, 0, 3514.287024, 10542.86,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100102P', 'Bulk fix: ADJ_PLUS saldo minus Juni 2026', 1, NOW()),

  -- INDOMIE GORENG (KITCHEN, div=3, uom=1): +2
  ('ICM20260601F009', '2026-06-01', '2026-06-01 07:10:00', 'KITCHEN', 3,
   103, 1, 'ADJUSTMENT_PLUS', 2.0000, 0, 3500.000000, 7000.00,
   'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL,
   'ICAFIX2026060100103P', 'Bulk fix: ADJ_PLUS saldo minus Juni 2026', 1, NOW());

SELECT ROW_COUNT() AS c1_new_ml_inserted;

INSERT INTO inv_component_lot
  (location_type, division_id, component_id, uom_id, lot_no, receipt_date,
   unit_cost, qty_in_total, qty_out_total, qty_balance,
   source_module, source_table, source_id, status, created_at, updated_at)
VALUES
  ('BAR',     2, 11,  11, 'ICAFIX2026060100011P', '2026-06-01', 30.501753,  180.0000, 0, 180.0000, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW()),
  ('KITCHEN', 3, 17,  11, 'ICAFIX2026060100017P', '2026-06-01', 19.597442,  227.0756, 0, 227.0756, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW()),
  ('KITCHEN', 3, 36,  11, 'ICAFIX2026060100036P', '2026-06-01', 22.970445,  180.0120, 0, 180.0120, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW()),
  ('KITCHEN', 3, 102,  1, 'ICAFIX2026060100102P', '2026-06-01', 3514.287024,  3.0000, 0,   3.0000, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW()),
  ('KITCHEN', 3, 103,  1, 'ICAFIX2026060100103P', '2026-06-01', 3500.000000,  2.0000, 0,   2.0000, 'PRODUCTION_ADJUSTMENT', 'BULK_FIX_20260613', NULL, 'OPEN', NOW(), NOW());

SELECT ROW_COUNT() AS c2_new_lots_inserted;

-- ══════════════════════════════════════════════════════════════
-- VERIFIKASI: ulang hitung saldo harian untuk memastikan 0 minus
-- ══════════════════════════════════════════════════════════════
WITH seed AS (
  SELECT ms.location_type, IFNULL(ms.division_id,0) AS division_id,
    ms.component_id, ms.uom_id, ms.closing_qty AS opening_qty
  FROM inv_component_monthly_stock ms
  WHERE ms.month_key = DATE_FORMAT(DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 1 MONTH),'%Y-%m-01')
),
daily_net AS (
  SELECT location_type, IFNULL(division_id,0) AS division_id,
    component_id, uom_id, movement_date,
    SUM(qty_in - qty_out) AS net_day
  FROM inv_component_movement_log
  WHERE movement_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
  GROUP BY location_type, IFNULL(division_id,0), component_id, uom_id, movement_date
),
running AS (
  SELECT dn.location_type, dn.division_id, dn.component_id, dn.uom_id, dn.movement_date,
    COALESCE(s.opening_qty,0) + SUM(dn.net_day) OVER (
      PARTITION BY dn.location_type, dn.division_id, dn.component_id, dn.uom_id
      ORDER BY dn.movement_date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
    ) AS closing_qty
  FROM daily_net dn
  LEFT JOIN seed s ON s.location_type=dn.location_type AND s.division_id=dn.division_id
    AND s.component_id=dn.component_id AND s.uom_id=dn.uom_id
)
SELECT
  c.component_code, c.component_name, r.location_type, COALESCE(d.name,'--') AS division,
  COUNT(*) AS hari_minus, ROUND(MIN(r.closing_qty),2) AS worst
FROM running r
JOIN mst_component c ON c.id=r.component_id
LEFT JOIN mst_operational_division d ON d.id=r.division_id
WHERE ROUND(r.closing_qty,2) < 0
GROUP BY c.id, r.location_type, r.division_id, r.uom_id
ORDER BY worst ASC;

-- Jika query di atas mengembalikan 0 baris → COMMIT
-- Jika masih ada baris minus → ROLLBACK dan investigasi

COMMIT;
-- atau: ROLLBACK;

-- ============================================================
-- SETELAH COMMIT:
-- Buka /production/component-reconcile → klik Repair pada
-- semua komponen yang diubah untuk rebuild monthly_stock.
-- Atau cukup Repair semua (filter BAR dan KITCHEN bergantian).
-- ============================================================
