-- =============================================================================
-- REPAIR: Mismatch Lot Component setelah Repair Rekonsiliasi (2026-08-03)
-- Masalah:
--   1. Pengguna menjalankan "lot-only adjustment" dari /production/component-reconcile
--      yang menguras 200 ml dari lot ADU RAMU (BAR) tanpa entri movement log.
--      Akibatnya: stok=200, log=200, tapi lot_balance=0 → mismatch di dashboard.
--   2. Script perbaikan pertama (step 3c) terlalu luas — ikut memodifikasi
--      45 lot lain yang status CLOSED, menyebabkan lot_balance lebih dari stok.
--   3. Script revert step 3c juga terlalu luas — menutup 104 lot yang sah OPEN.
--
-- Solusi akhir:
--   - Hapus lot-only adjustment yang salah (ADU RAMU).
--   - Pulihkan lot 2171 (ADU RAMU Aug) ke OPEN, balance = 200.
--   - Untuk semua identity di mana lot_balance < stok (setelah kerusakan revert),
--     buat satu lot RECONFIX dengan qty = selisih.
--
-- Acuan: Stok (inv_component_monthly_stock) adalah sumber kebenaran.
--        Lot dan log menyesuaikan stok. Stok minus DIABAIKAN.
-- =============================================================================

-- -----------------------------------------------------------------------
-- LANGKAH 1: DIAGNOSIS — mismatch stok vs lot (bulan berjalan, stok > 0)
-- -----------------------------------------------------------------------
SELECT
    s.location_type,
    od.name                                                      AS division_name,
    c.component_name,
    u.code                                                       AS uom_code,
    s.closing_qty                                                AS stock_qty,
    ROUND(COALESCE(log_t.log_running, 0), 4)                   AS log_running_qty,
    ROUND(COALESCE(aug_t.aug_balance, 0), 4)                   AS aug_lot_balance,
    ROUND(s.closing_qty - COALESCE(aug_t.aug_balance, 0), 4)   AS delta_stock_vs_lot
FROM inv_component_monthly_stock s
JOIN (
    SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
    FROM inv_component_monthly_stock
    GROUP BY location_type, division_id, component_id, uom_id
) latest ON latest.location_type = s.location_type
    AND latest.division_id <=> s.division_id
    AND latest.component_id = s.component_id
    AND latest.uom_id = s.uom_id
    AND latest.max_month = s.month_key
JOIN mst_component c ON c.id = s.component_id
JOIN mst_uom u ON u.id = s.uom_id
LEFT JOIN mst_operational_division od ON od.id = s.division_id
LEFT JOIN (
    SELECT location_type, division_id, component_id, uom_id,
           ROUND(SUM(qty_in) - SUM(qty_out), 4) AS log_running
    FROM inv_component_movement_log
    GROUP BY location_type, division_id, component_id, uom_id
) log_t ON log_t.location_type = s.location_type
    AND log_t.division_id <=> s.division_id
    AND log_t.component_id = s.component_id
    AND log_t.uom_id = s.uom_id
LEFT JOIN (
    SELECT location_type, division_id, component_id, uom_id, SUM(qty_balance) AS aug_balance
    FROM inv_component_lot
    WHERE status = 'OPEN' AND qty_balance > 0.0001
      AND receipt_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND receipt_date <= CURDATE()
    GROUP BY location_type, division_id, component_id, uom_id
) aug_t ON aug_t.location_type = s.location_type
    AND aug_t.division_id <=> s.division_id
    AND aug_t.component_id = s.component_id
    AND aug_t.uom_id = s.uom_id
WHERE s.closing_qty > 0
  AND (
      ABS(s.closing_qty - COALESCE(log_t.log_running, 0)) > 0.001
   OR ABS(s.closing_qty - COALESCE(aug_t.aug_balance, 0)) > 0.001
  )
ORDER BY ABS(s.closing_qty - COALESCE(aug_t.aug_balance, 0)) DESC;

-- -----------------------------------------------------------------------
-- LANGKAH 2: HAPUS lot-only adjustment yang salah (source: COMPONENT_RECONCILE)
--            dari bulan berjalan untuk identity yang stoknya masih positif.
--            Hanya aman dijalankan jika lot adjustment tersebut tidak didukung
--            oleh entri movement log yang sesuai.
-- -----------------------------------------------------------------------

-- 2a. Hapus issue lines
DELETE ill
FROM inv_component_lot_issue_line ill
JOIN inv_component_lot_issue_log il ON il.id = ill.issue_id
JOIN inv_component_lot l ON l.id = ill.lot_id
JOIN (
    SELECT s.location_type, s.division_id, s.component_id, s.uom_id
    FROM inv_component_monthly_stock s
    JOIN (
        SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
        FROM inv_component_monthly_stock
        GROUP BY location_type, division_id, component_id, uom_id
    ) latest ON latest.location_type = s.location_type
        AND latest.division_id <=> s.division_id
        AND latest.component_id = s.component_id
        AND latest.uom_id = s.uom_id
        AND latest.max_month = s.month_key
    WHERE s.closing_qty > 0
) valid_stock ON valid_stock.location_type = l.location_type
    AND valid_stock.division_id <=> l.division_id
    AND valid_stock.component_id = l.component_id
    AND valid_stock.uom_id = l.uom_id
WHERE il.source_module = 'COMPONENT_RECONCILE'
  AND il.issue_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01');

SELECT ROW_COUNT() AS issue_lines_dihapus;

-- 2b. Hapus orphan issue log headers
DELETE il
FROM inv_component_lot_issue_log il
LEFT JOIN inv_component_lot_issue_line ill ON ill.issue_id = il.id
WHERE il.source_module = 'COMPONENT_RECONCILE'
  AND il.issue_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND ill.id IS NULL;

SELECT ROW_COUNT() AS issue_logs_dihapus;

-- 2c. Pulihkan lot yang terpengaruh: kurangi qty_out, kembalikan balance, buka ulang
--     (Hanya lot bulan ini yang stoknya positif dan balance-nya nol akibat adjustment)
UPDATE inv_component_lot l
JOIN (
    SELECT lot.id AS lot_id, lot.qty_in_total,
           ROUND(COALESCE(iss.total_out, 0), 4) AS remaining_out
    FROM inv_component_lot lot
    LEFT JOIN (
        SELECT ill2.lot_id, SUM(ill2.qty_out) AS total_out
        FROM inv_component_lot_issue_line ill2
        GROUP BY ill2.lot_id
    ) iss ON iss.lot_id = lot.id
    JOIN (
        SELECT s.location_type, s.division_id, s.component_id, s.uom_id
        FROM inv_component_monthly_stock s
        JOIN (SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
              FROM inv_component_monthly_stock GROUP BY location_type, division_id, component_id, uom_id
        ) latest ON latest.location_type = s.location_type AND latest.division_id <=> s.division_id
            AND latest.component_id = s.component_id AND latest.uom_id = s.uom_id AND latest.max_month = s.month_key
        WHERE s.closing_qty > 0
    ) vs ON vs.location_type = lot.location_type AND vs.division_id <=> lot.division_id
        AND vs.component_id = lot.component_id AND vs.uom_id = lot.uom_id
    -- Hanya lot yang balance-nya nol namun issue_line total < qty_in (→ ada issue yg salah dihapus)
    WHERE lot.qty_balance = 0
      AND lot.status = 'CLOSED'
      AND lot.receipt_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND ROUND(lot.qty_in_total - COALESCE(iss.total_out, 0), 4) > 0.001
) corrected ON corrected.lot_id = l.id
SET
    l.qty_out_total = corrected.remaining_out,
    l.qty_balance   = ROUND(corrected.qty_in_total - corrected.remaining_out, 4),
    l.status        = 'OPEN',
    l.updated_at    = NOW();

SELECT ROW_COUNT() AS lots_dipulihkan;

-- -----------------------------------------------------------------------
-- LANGKAH 3: BUAT LOT REKONSILIASI untuk identity yang lot_balance < stok
--            (menutupi selisih yang tersisa setelah langkah 2)
--            Skip identity yang stok <= 0.
-- -----------------------------------------------------------------------
INSERT INTO inv_component_lot (
    location_type, division_id, component_id, uom_id,
    lot_no, receipt_date, expiry_date, unit_cost,
    qty_in_total, qty_out_total, qty_balance,
    source_module, source_table, source_id, source_line_id,
    parent_lot_id, last_issue_at, status, created_at, updated_at
)
SELECT
    s.location_type,
    s.division_id,
    s.component_id,
    s.uom_id,
    CONCAT('RECONFIX-', DATE_FORMAT(NOW(),'%Y%m%d'), '-C', s.component_id, '-D', COALESCE(s.division_id, 0)) AS lot_no,
    CURDATE()                                                            AS receipt_date,
    NULL                                                                 AS expiry_date,
    COALESCE(s.avg_cost, c.hpp_standard, 0)                             AS unit_cost,
    ROUND(s.closing_qty - COALESCE(aug_t.aug_balance, 0), 4)            AS qty_in_total,
    0.0                                                                  AS qty_out_total,
    ROUND(s.closing_qty - COALESCE(aug_t.aug_balance, 0), 4)            AS qty_balance,
    'RECONCILE_REPAIR'                                                   AS source_module,
    'inv_component_monthly_stock'                                        AS source_table,
    s.id                                                                 AS source_id,
    NULL                                                                 AS source_line_id,
    NULL                                                                 AS parent_lot_id,
    NULL                                                                 AS last_issue_at,
    'OPEN'                                                               AS status,
    NOW()                                                                AS created_at,
    NOW()                                                                AS updated_at
FROM inv_component_monthly_stock s
JOIN (
    SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
    FROM inv_component_monthly_stock
    GROUP BY location_type, division_id, component_id, uom_id
) latest ON latest.location_type = s.location_type
    AND latest.division_id <=> s.division_id
    AND latest.component_id = s.component_id
    AND latest.uom_id = s.uom_id
    AND latest.max_month = s.month_key
JOIN mst_component c ON c.id = s.component_id
LEFT JOIN (
    SELECT location_type, division_id, component_id, uom_id, SUM(qty_balance) AS aug_balance
    FROM inv_component_lot
    WHERE status = 'OPEN' AND qty_balance > 0.0001
      AND receipt_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND receipt_date <= CURDATE()
    GROUP BY location_type, division_id, component_id, uom_id
) aug_t ON aug_t.location_type = s.location_type
    AND aug_t.division_id <=> s.division_id
    AND aug_t.component_id = s.component_id
    AND aug_t.uom_id = s.uom_id
WHERE s.closing_qty > 0
  AND ROUND(s.closing_qty - COALESCE(aug_t.aug_balance, 0), 4) > 0.001;

SELECT ROW_COUNT() AS reconfix_lots_dibuat;

-- -----------------------------------------------------------------------
-- LANGKAH 4: VERIFIKASI AKHIR
-- -----------------------------------------------------------------------
SELECT
    s.location_type,
    od.name                                                      AS division_name,
    c.component_name,
    u.code                                                       AS uom_code,
    s.closing_qty                                                AS stock_qty,
    ROUND(COALESCE(log_t.log_running, 0), 4)                   AS log_qty,
    ROUND(COALESCE(aug_t.aug_balance, 0), 4)                   AS lot_qty,
    CASE
        WHEN ABS(s.closing_qty - COALESCE(log_t.log_running, 0)) > 0.001 THEN 'MASALAH: STOK vs LOG'
        WHEN ABS(s.closing_qty - COALESCE(aug_t.aug_balance, 0)) > 0.001 THEN 'MASALAH: STOK vs LOT'
        ELSE 'OK'
    END AS status_check
FROM inv_component_monthly_stock s
JOIN (
    SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
    FROM inv_component_monthly_stock
    GROUP BY location_type, division_id, component_id, uom_id
) latest ON latest.location_type = s.location_type
    AND latest.division_id <=> s.division_id
    AND latest.component_id = s.component_id
    AND latest.uom_id = s.uom_id
    AND latest.max_month = s.month_key
JOIN mst_component c ON c.id = s.component_id
JOIN mst_uom u ON u.id = s.uom_id
LEFT JOIN mst_operational_division od ON od.id = s.division_id
LEFT JOIN (
    SELECT location_type, division_id, component_id, uom_id,
           ROUND(SUM(qty_in) - SUM(qty_out), 4) AS log_running
    FROM inv_component_movement_log
    GROUP BY location_type, division_id, component_id, uom_id
) log_t ON log_t.location_type = s.location_type
    AND log_t.division_id <=> s.division_id
    AND log_t.component_id = s.component_id
    AND log_t.uom_id = s.uom_id
LEFT JOIN (
    SELECT location_type, division_id, component_id, uom_id, SUM(qty_balance) AS aug_balance
    FROM inv_component_lot
    WHERE status = 'OPEN' AND qty_balance > 0.0001
      AND receipt_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND receipt_date <= CURDATE()
    GROUP BY location_type, division_id, component_id, uom_id
) aug_t ON aug_t.location_type = s.location_type
    AND aug_t.division_id <=> s.division_id
    AND aug_t.component_id = s.component_id
    AND aug_t.uom_id = s.uom_id
WHERE s.closing_qty > 0
ORDER BY
    CASE WHEN ABS(s.closing_qty - COALESCE(log_t.log_running, 0)) > 0.001
              OR ABS(s.closing_qty - COALESCE(aug_t.aug_balance, 0)) > 0.001
         THEN 0 ELSE 1 END,
    c.component_name;
