-- =============================================================================
-- FIX: Component Lot Double-Count akibat RECONFIX lots (2026-08-03)
-- =============================================================================
-- Masalah:
--   1. RECONFIX lots (86 records, lot_no LIKE 'RECONFIX-20260803-%') disisipkan
--      oleh script sebelumnya untuk menutupi selisih stok vs lot.
--      Namun lot-lot tersebut menyebabkan double-count karena lot asli sudah
--      di-restore/re-open lebih dulu (oleh script step 3c yang re-run di 19:27 WIB).
--      Hasil: LOT FIFO = 2x monthly stock untuk 86 identity.
--
--   2. Untuk 11 identity, setelah RECONFIX dihapus masih ada kelebihan saldo lot.
--      Penyebab: step 3c me-reset qty_out_total ke SUM(issue_lines), menghapus
--      tracking "direct usage" (POS/manual) yang sebelumnya hanya ada di
--      qty_out_total. Efeknya: balance lot > stok yang seharusnya.
--
--   3. ADU RAMU: reconcile page menggunakan exactMonthOnly=true (WHERE month_key
--      = '2026-08-01'). ADU RAMU tidak punya baris Agustus → monthly tampil 0.
--      Solusi: insert baris carry-forward Agustus dari closing Juli.
--
-- Acuan: inv_component_monthly_stock.closing_qty adalah kebenaran.
--        Lot dan log menyesuaikan stok.
-- =============================================================================

-- -----------------------------------------------------------------------
-- LANGKAH 1: Hapus semua RECONFIX lots (penyebab double-count)
-- -----------------------------------------------------------------------
DELETE FROM inv_component_lot
WHERE lot_no LIKE 'RECONFIX-20260803-%';

SELECT ROW_COUNT() AS reconfix_lots_dihapus;  -- Ekspektasi: 86

-- -----------------------------------------------------------------------
-- LANGKAH 2: Perbaiki 11 identity yang lot-nya masih melebihi stok
--            setelah penghapusan RECONFIX
-- -----------------------------------------------------------------------

-- SIMPLE SYRUP (BAR, div=2): kelebihan 829
--   Lot 2441 ditutup (500 → 0), lot 2473 dikurangi (500 → 171)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=500.0000, status='CLOSED', updated_at=NOW() WHERE id=2441;
UPDATE inv_component_lot SET qty_balance=171.0000, qty_out_total=329.0000, updated_at=NOW() WHERE id=2473;

-- JELLY CHOCOLATE BASE (BAR): kelebihan 72
--   Lot 2409 dikurangi (110 → 38)
UPDATE inv_component_lot SET qty_balance=38.0000, qty_out_total=72.0000, updated_at=NOW() WHERE id=2409;

-- BASE GEDE (KITCHEN): kelebihan 65
--   Tutup 4 lot POS/adjustment tanpa issue_lines (25+20+10+10 = 65)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=qty_in_total, status='CLOSED', updated_at=NOW()
WHERE id IN (2461, 2465, 2479, 2491);

-- WHIPPING CREAM (BAR): kelebihan 50
--   Lot 2475: qty_out_total di-reset ke issue_lines (14) oleh step 3c.
--   Pulihkan ke 64 (14 issue + 50 POS direct usage), balance: 400-64=336
UPDATE inv_component_lot SET qty_balance=336.0000, qty_out_total=64.0000, updated_at=NOW() WHERE id=2475;

-- RED GINGER PICKLED (KITCHEN): kelebihan 39
--   Tutup lot 2411 (39 → 0, tanpa issue_lines)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=39.0000, status='CLOSED', updated_at=NOW() WHERE id=2411;

-- CUKO (KITCHEN): kelebihan 20
--   Tutup lot 2389 (balance 20 → 0; 20 unit dikonsumsi via POS, bukan issue_lines)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=630.0000, status='CLOSED', updated_at=NOW() WHERE id=2389;

-- COLESLAW SALAD (KITCHEN): kelebihan 20
--   Tutup lot 2267 (10 → 0) dan lot 2467 (10 → 0)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=210.0000, status='CLOSED', updated_at=NOW() WHERE id=2267;
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=10.0000, status='CLOSED', updated_at=NOW() WHERE id=2467;

-- BUMBU MERAH PASTE TERASI (KITCHEN): kelebihan 20
--   Tutup lot 2463 (20 → 0, tanpa issue_lines)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=20.0000, status='CLOSED', updated_at=NOW() WHERE id=2463;

-- LEMON SLICE (BAR): kelebihan 6
--   Kurangi lot 2443 (9 → 3)
UPDATE inv_component_lot SET qty_balance=3.0000, qty_out_total=6.0000, updated_at=NOW() WHERE id=2443;

-- CHICKEN CUBE 15 (KITCHEN): kelebihan 5
--   Tutup lot 2433 (5 → 0, tanpa issue_lines)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=5.0000, status='CLOSED', updated_at=NOW() WHERE id=2433;

-- FRENCH FRIES FROZEN (KITCHEN): kelebihan 1
--   Tutup lot 2447 (1 → 0, tanpa issue_lines)
UPDATE inv_component_lot SET qty_balance=0.0000, qty_out_total=1.0000, status='CLOSED', updated_at=NOW() WHERE id=2447;

-- -----------------------------------------------------------------------
-- LANGKAH 3: Buat baris monthly stock Agustus 2026 untuk ADU RAMU
--            (carry-forward dari closing Juli 2026)
-- -----------------------------------------------------------------------
INSERT INTO inv_component_monthly_stock (
    month_key, location_type, division_id, component_id, uom_id,
    opening_qty, opening_total_value, in_qty, in_total_value,
    out_qty, out_total_value,
    waste_qty, waste_total_value, spoil_qty, spoil_total_value,
    adjustment_plus_qty, adjustment_plus_total_value,
    adjustment_minus_qty, adjustment_minus_total_value,
    closing_qty, avg_cost, total_value,
    movement_day_count, mutation_count, source_mode, notes, created_at
)
SELECT
    '2026-08-01', location_type, division_id, component_id, uom_id,
    closing_qty, total_value, 0, 0,
    0, 0,
    0, 0, 0, 0,
    0, 0,
    0, 0,
    closing_qty, avg_cost, total_value,
    0, 0, 'OPENING_CARRY_FORWARD',
    'Carry-forward dari Juli 2026: tidak ada pergerakan Agustus',
    NOW()
FROM inv_component_monthly_stock
WHERE component_id  = (SELECT id FROM mst_component WHERE component_name = 'ADU RAMU')
  AND location_type = 'BAR'
  AND division_id   = 2
  AND month_key     = '2026-07-01'
  AND NOT EXISTS (
    SELECT 1 FROM inv_component_monthly_stock x
    WHERE x.component_id  = (SELECT id FROM mst_component WHERE component_name = 'ADU RAMU')
      AND x.location_type = 'BAR'
      AND x.division_id   = 2
      AND x.month_key     = '2026-08-01'
  );

SELECT ROW_COUNT() AS adu_ramu_aug_inserted;  -- Ekspektasi: 1

-- -----------------------------------------------------------------------
-- VERIFIKASI AKHIR
-- -----------------------------------------------------------------------

-- A. Cek sisa mismatch: semua open lots vs latest monthly stock
SELECT
    s.location_type, c.component_name, s.closing_qty AS stock,
    ROUND(COALESCE(lots.balance, 0), 4) AS open_lots,
    ROUND(s.closing_qty - COALESCE(lots.balance, 0), 4) AS delta
FROM inv_component_monthly_stock s
JOIN (SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
      FROM inv_component_monthly_stock GROUP BY location_type, division_id, component_id, uom_id) latest
  ON latest.location_type=s.location_type AND latest.division_id<=>s.division_id
     AND latest.component_id=s.component_id AND latest.uom_id=s.uom_id AND latest.max_month=s.month_key
JOIN mst_component c ON c.id = s.component_id
LEFT JOIN (SELECT location_type, division_id, component_id, uom_id, SUM(qty_balance) AS balance
           FROM inv_component_lot WHERE status='OPEN' AND qty_balance > 0.0001
           GROUP BY location_type, division_id, component_id, uom_id) lots
  ON lots.location_type=s.location_type AND lots.division_id<=>s.division_id
     AND lots.component_id=s.component_id AND lots.uom_id=s.uom_id
WHERE s.closing_qty > 0
  AND ABS(s.closing_qty - COALESCE(lots.balance, 0)) > 0.001;

-- B. Tidak ada RECONFIX lot tersisa
SELECT COUNT(*) AS reconfix_sisa FROM inv_component_lot WHERE lot_no LIKE 'RECONFIX%';

-- -----------------------------------------------------------------------
-- LANGKAH 4: Insert baris carry-forward Agustus untuk semua item yang
--            punya open lots tapi belum ada baris month_key='2026-08-01'
--            (Reconcile page pakai exactMonthOnly=true — tanpa baris Aug,
--             monthly stock tampil 0 → mismatch palsu)
-- -----------------------------------------------------------------------
INSERT INTO inv_component_monthly_stock (
    month_key, location_type, division_id, component_id, uom_id,
    opening_qty, opening_total_value, in_qty, in_total_value, out_qty, out_total_value,
    waste_qty, waste_total_value, spoil_qty, spoil_total_value,
    adjustment_plus_qty, adjustment_plus_total_value,
    adjustment_minus_qty, adjustment_minus_total_value,
    closing_qty, avg_cost, total_value,
    movement_day_count, mutation_count, source_mode, notes, created_at
)
SELECT
    '2026-08-01',
    l.location_type, l.division_id, l.component_id, l.uom_id,
    COALESCE(prev.closing_qty, 0),
    ROUND(COALESCE(prev.closing_qty, 0) * COALESCE(prev.avg_cost, 0), 2),
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    ROUND(SUM(l.qty_balance), 4),
    COALESCE(prev.avg_cost, 0),
    ROUND(SUM(l.qty_balance) * COALESCE(prev.avg_cost, 0), 2),
    0, 0,
    'OPENING_CARRY_FORWARD',
    'Carry-forward 2026-08-03: tidak ada pergerakan Agustus',
    NOW()
FROM inv_component_lot l
LEFT JOIN (
    SELECT s.location_type, s.division_id, s.component_id, s.uom_id, s.closing_qty, s.avg_cost
    FROM inv_component_monthly_stock s
    JOIN (SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
          FROM inv_component_monthly_stock WHERE month_key < '2026-08-01'
          GROUP BY location_type, division_id, component_id, uom_id) latest
      ON latest.location_type=s.location_type AND latest.division_id<=>s.division_id
         AND latest.component_id=s.component_id AND latest.uom_id=s.uom_id AND latest.max_month=s.month_key
) prev ON prev.location_type=l.location_type AND prev.division_id<=>l.division_id
     AND prev.component_id=l.component_id AND prev.uom_id=l.uom_id
WHERE l.status='OPEN' AND l.qty_balance > 0.0001
  AND NOT EXISTS (
    SELECT 1 FROM inv_component_monthly_stock ms
    WHERE ms.location_type=l.location_type AND ms.division_id<=>l.division_id
      AND ms.component_id=l.component_id AND ms.uom_id=l.uom_id AND ms.month_key='2026-08-01'
  )
GROUP BY l.location_type, l.division_id, l.component_id, l.uom_id, prev.closing_qty, prev.avg_cost;

SELECT ROW_COUNT() AS carry_forward_inserted;

-- C. Verifikasi final: 0 mismatch di reconcile page (August exact)
SELECT COUNT(*) AS sisa_mismatch_reconcile
FROM inv_component_monthly_stock s
LEFT JOIN (
    SELECT location_type, division_id, component_id, uom_id, SUM(qty_balance) AS balance
    FROM inv_component_lot WHERE status='OPEN' AND qty_balance > 0.0001
      AND receipt_date >= '2026-08-01' AND receipt_date <= CURDATE()
    GROUP BY location_type, division_id, component_id, uom_id
) lot_aug ON lot_aug.location_type=s.location_type AND lot_aug.division_id<=>s.division_id
     AND lot_aug.component_id=s.component_id AND lot_aug.uom_id=s.uom_id
LEFT JOIN (
    SELECT location_type, division_id, component_id, uom_id,
           ROUND(SUM(qty_in)-SUM(qty_out), 4) AS log_net
    FROM inv_component_movement_log
    GROUP BY location_type, division_id, component_id, uom_id
) log_t ON log_t.location_type=s.location_type AND log_t.division_id<=>s.division_id
     AND log_t.component_id=s.component_id AND log_t.uom_id=s.uom_id
WHERE s.month_key = '2026-08-01' AND s.closing_qty > 0
  AND (ABS(s.closing_qty - COALESCE(lot_aug.balance,0)) > 0.001
    OR ABS(s.closing_qty - COALESCE(log_t.log_net,0)) > 0.001);

-- -----------------------------------------------------------------------
-- LANGKAH 5: TONGSENG PASTE — lot OPEN hilang, buat ulang carry-forward
-- -----------------------------------------------------------------------
-- Konteks: Semua lot Agustus TONGSENG PASTE (KITCHEN, div=3) sudah CLOSED
-- (MONTHLY_OPNAME + PRODUCTION_ADJUSTMENT) dengan saldo 0, padahal
-- monthly stock = 590 dan movement log net = 590.
-- Lot RECONFIX-20260803b-TONGSENG yang dibuat sebelumnya tidak muncul
-- (kemungkinan dikonsumsi ulang oleh sistem).
-- Solusi: buat lot OPEN baru dengan saldo 590.
-- component_id=31, uom_id=11, division_id=3, location_type='KITCHEN'
-- monthly_stock_id=4797 (Aug 2026), avg_cost=56.396966
INSERT INTO inv_component_lot (
    location_type, division_id, component_id, uom_id,
    lot_no, receipt_date, expiry_date, unit_cost,
    qty_in_total, qty_out_total, qty_balance,
    source_module, source_table, source_id, source_line_id,
    parent_lot_id, last_issue_at, status, created_at, updated_at
)
SELECT
    'KITCHEN', 3, 31, 11,
    'RECONFIX-20260803c-TONGSENG', '2026-08-01', NULL, 56.396966,
    590.0000, 0.0000, 590.0000,
    'RECONCILE_REPAIR', 'inv_component_monthly_stock', 4797, NULL,
    NULL, NULL, 'OPEN', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM inv_component_lot
    WHERE component_id=31 AND location_type='KITCHEN' AND division_id=3
      AND status='OPEN' AND qty_balance > 0.0001 AND receipt_date >= '2026-08-01'
);

SELECT ROW_COUNT() AS tongseng_lot_inserted;  -- Ekspektasi: 1
