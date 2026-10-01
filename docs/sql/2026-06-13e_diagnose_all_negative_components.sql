-- ============================================================
-- DIAGNOSA: Semua component dengan saldo harian minus bulan ini
--
-- Cara baca: gunakan ini sebelum / sesudah perbaikan data untuk
-- melihat component mana saja yang perlu ADJ_PLUS sebelum
-- Generate Opname bisa berjalan.
--
-- Logika: opening bulan ini = closing monthly_stock bulan lalu
--         + pergerakan movement_log bulan ini → saldo harian.
-- ============================================================

-- ── STEP 1: Ringkasan per component (biasanya cukup ini) ─────
WITH seed AS (
  -- Opening bulan ini dari monthly_stock bulan lalu
  SELECT
    ms.location_type,
    IFNULL(ms.division_id, 0)  AS division_id,
    ms.component_id,
    ms.uom_id,
    ms.closing_qty              AS opening_qty
  FROM inv_component_monthly_stock ms
  WHERE ms.month_key = DATE_FORMAT(DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 1 MONTH), '%Y-%m-01')
),
daily_net AS (
  SELECT
    location_type,
    IFNULL(division_id, 0)     AS division_id,
    component_id,
    uom_id,
    movement_date,
    SUM(qty_in - qty_out)      AS net_day
  FROM inv_component_movement_log
  WHERE movement_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
  GROUP BY location_type, IFNULL(division_id, 0), component_id, uom_id, movement_date
),
running AS (
  SELECT
    dn.location_type,
    dn.division_id,
    dn.component_id,
    dn.uom_id,
    dn.movement_date,
    dn.net_day,
    COALESCE(s.opening_qty, 0)
      + SUM(dn.net_day) OVER (
          PARTITION BY dn.location_type, dn.division_id, dn.component_id, dn.uom_id
          ORDER BY dn.movement_date
          ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS closing_qty
  FROM daily_net dn
  LEFT JOIN seed s
    ON  s.location_type = dn.location_type
    AND s.division_id   = dn.division_id
    AND s.component_id  = dn.component_id
    AND s.uom_id        = dn.uom_id
)
SELECT
  c.component_code,
  c.component_name,
  r.location_type,
  COALESCE(d.name, '—')                               AS division_name,
  u.code AS uom_code,
  COUNT(*)                                             AS negative_days,
  MIN(r.closing_qty)                                   AS worst_closing,
  MAX(r.closing_qty)                                   AS best_negative_closing,
  GROUP_CONCAT(r.movement_date ORDER BY r.movement_date SEPARATOR ', ') AS tanggal_minus,
  c.id                                                 AS component_id,
  c.operational_division_id                            AS master_div_id,
  od.name                                              AS master_div_name,
  IF(c.operational_division_id = r.division_id OR (c.operational_division_id IS NULL AND r.division_id = 0),
     'OK', 'BEDA ← cek divisi')                       AS div_match
FROM running r
JOIN mst_component c  ON c.id  = r.component_id
LEFT JOIN mst_operational_division d  ON d.id  = r.division_id
LEFT JOIN mst_operational_division od ON od.id = c.operational_division_id
LEFT JOIN mst_uom u ON u.id = r.uom_id
WHERE r.closing_qty < 0
GROUP BY c.id, r.location_type, r.division_id, r.uom_id
ORDER BY worst_closing ASC;


-- ── STEP 2: Detail per hari (jika perlu investigasi spesifik) ─
-- Ganti @comp_id dengan ID component yang dicurigai.
-- Bisa juga hapus WHERE c.id = @comp_id untuk semua.

SET @comp_id = 8;   -- << ganti dengan component_id target

WITH seed AS (
  SELECT
    ms.location_type,
    IFNULL(ms.division_id, 0)  AS division_id,
    ms.component_id,
    ms.uom_id,
    ms.closing_qty              AS opening_qty
  FROM inv_component_monthly_stock ms
  WHERE ms.month_key = DATE_FORMAT(DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 1 MONTH), '%Y-%m-01')
    AND ms.component_id = @comp_id
),
daily_net AS (
  SELECT
    location_type,
    IFNULL(division_id, 0)     AS division_id,
    component_id,
    uom_id,
    movement_date,
    SUM(qty_in)                AS in_day,
    SUM(qty_out)               AS out_day,
    SUM(qty_in - qty_out)      AS net_day
  FROM inv_component_movement_log
  WHERE component_id = @comp_id
    AND movement_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
  GROUP BY location_type, IFNULL(division_id, 0), component_id, uom_id, movement_date
),
running AS (
  SELECT
    dn.*,
    COALESCE(s.opening_qty, 0)
      + SUM(dn.net_day) OVER (
          PARTITION BY dn.location_type, dn.division_id, dn.component_id, dn.uom_id
          ORDER BY dn.movement_date
          ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS closing_qty
  FROM daily_net dn
  LEFT JOIN seed s
    ON  s.location_type = dn.location_type
    AND s.division_id   = dn.division_id
    AND s.component_id  = dn.component_id
    AND s.uom_id        = dn.uom_id
)
SELECT
  c.component_code,
  c.component_name,
  r.location_type,
  COALESCE(d.name, '—')   AS division_name,
  r.movement_date,
  r.in_day,
  r.out_day,
  r.net_day,
  COALESCE(s2.opening_qty, 0)   AS opening,
  r.closing_qty,
  IF(r.closing_qty < 0, '✗ MINUS', '✓') AS status
FROM running r
JOIN mst_component c ON c.id = r.component_id
LEFT JOIN mst_operational_division d ON d.id = r.division_id
LEFT JOIN seed s2
  ON  s2.location_type = r.location_type
  AND s2.division_id   = r.division_id
  AND s2.component_id  = r.component_id
  AND s2.uom_id        = r.uom_id
ORDER BY r.location_type, r.division_id, r.movement_date;
