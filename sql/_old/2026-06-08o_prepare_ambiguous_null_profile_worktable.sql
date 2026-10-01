-- 2026-06-08o_prepare_ambiguous_null_profile_worktable.sql
-- Tujuan:
-- 1) Menandai sisa movement DIVISION material dengan profile_key NULL/kosong ke tabel kerja.
-- 2) Menyediakan summary per item/identity agar profile canonical bisa dipilih manual.
--
-- Urutan pakai di server:
-- 1. Jalankan 2026-06-08n_report_inventory_material_daily_oreo_crumb_split.sql terlebih dahulu.
-- 2. Jalankan file ini untuk membuat/memperbarui tabel kerja.
-- 3. Review hasil SELECT summary di bagian bawah file ini.
-- 4. Setelah profile canonical dipilih, baru siapkan SQL canonicalization final per item.

CREATE TABLE IF NOT EXISTS z_work_inv_null_profile_movement_20260608 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  audit_date DATE NOT NULL,
  movement_scope VARCHAR(20) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  destination_type VARCHAR(30) NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  movement_count INT NOT NULL DEFAULT 0,
  qty_buy_delta_sum DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_content_delta_sum DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  first_movement_date DATE NULL,
  last_movement_date DATE NULL,
  candidate_profile_key_active VARCHAR(100) NULL,
  candidate_profile_key_any VARCHAR(100) NULL,
  candidate_catalog_name VARCHAR(255) NULL,
  candidate_brand_name VARCHAR(255) NULL,
  candidate_line_description VARCHAR(255) NULL,
  candidate_active_count INT NOT NULL DEFAULT 0,
  candidate_any_count INT NOT NULL DEFAULT 0,
  decision_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  chosen_profile_key VARCHAR(100) NULL,
  chosen_notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_z_work_inv_null_profile_20260608_identity (
    audit_date,
    movement_scope,
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO z_work_inv_null_profile_movement_20260608 (
  audit_date,
  movement_scope,
  division_id,
  destination_type,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  movement_count,
  qty_buy_delta_sum,
  qty_content_delta_sum,
  first_movement_date,
  last_movement_date,
  candidate_profile_key_active,
  candidate_profile_key_any,
  candidate_catalog_name,
  candidate_brand_name,
  candidate_line_description,
  candidate_active_count,
  candidate_any_count,
  decision_status,
  chosen_profile_key,
  chosen_notes
)
SELECT
  CURDATE() AS audit_date,
  x.movement_scope,
  x.division_id,
  x.destination_type,
  x.item_id,
  x.material_id,
  x.buy_uom_id,
  x.content_uom_id,
  x.movement_count,
  x.qty_buy_delta_sum,
  x.qty_content_delta_sum,
  x.first_movement_date,
  x.last_movement_date,
  x.candidate_profile_key_active,
  x.candidate_profile_key_any,
  x.candidate_catalog_name,
  x.candidate_brand_name,
  x.candidate_line_description,
  x.candidate_active_count,
  x.candidate_any_count,
  CASE
    WHEN x.candidate_active_count = 1 THEN 'READY_SINGLE_ACTIVE'
    WHEN x.candidate_active_count > 1 THEN 'AMBIGUOUS_ACTIVE'
    WHEN x.candidate_any_count = 1 THEN 'READY_SINGLE_INACTIVE'
    WHEN x.candidate_any_count > 1 THEN 'AMBIGUOUS_INACTIVE'
    ELSE 'NO_CANDIDATE'
  END AS decision_status,
  CASE
    WHEN x.candidate_active_count = 1 THEN x.candidate_profile_key_active
    WHEN x.candidate_active_count = 0 AND x.candidate_any_count = 1 THEN x.candidate_profile_key_any
    ELSE NULL
  END AS chosen_profile_key,
  CASE
    WHEN x.candidate_active_count = 1 THEN 'Auto-suggest from single active catalog candidate'
    WHEN x.candidate_active_count = 0 AND x.candidate_any_count = 1 THEN 'Auto-suggest from single inactive catalog candidate'
    WHEN x.candidate_any_count = 0 THEN 'No exact catalog candidate, manual decision required'
    ELSE 'Multiple exact catalog candidates, manual decision required'
  END AS chosen_notes
FROM (
  SELECT
    g.movement_scope,
    g.division_id,
    g.destination_type,
    g.item_id,
    g.material_id,
    g.buy_uom_id,
    g.content_uom_id,
    g.movement_count,
    g.qty_buy_delta_sum,
    g.qty_content_delta_sum,
    g.first_movement_date,
    g.last_movement_date,
    (
      SELECT c.profile_key
      FROM mst_purchase_catalog c
      WHERE c.item_id = g.item_id
        AND COALESCE(c.material_id, 0) = g.material_id
        AND COALESCE(c.buy_uom_id, 0) = g.buy_uom_id
        AND COALESCE(c.content_uom_id, 0) = g.content_uom_id
        AND COALESCE(c.is_active, 1) = 1
      ORDER BY c.id DESC
      LIMIT 1
    ) AS candidate_profile_key_active,
    (
      SELECT c.profile_key
      FROM mst_purchase_catalog c
      WHERE c.item_id = g.item_id
        AND COALESCE(c.material_id, 0) = g.material_id
        AND COALESCE(c.buy_uom_id, 0) = g.buy_uom_id
        AND COALESCE(c.content_uom_id, 0) = g.content_uom_id
      ORDER BY COALESCE(c.is_active, 1) DESC, c.id DESC
      LIMIT 1
    ) AS candidate_profile_key_any,
    (
      SELECT c.catalog_name
      FROM mst_purchase_catalog c
      WHERE c.item_id = g.item_id
        AND COALESCE(c.material_id, 0) = g.material_id
        AND COALESCE(c.buy_uom_id, 0) = g.buy_uom_id
        AND COALESCE(c.content_uom_id, 0) = g.content_uom_id
      ORDER BY COALESCE(c.is_active, 1) DESC, c.id DESC
      LIMIT 1
    ) AS candidate_catalog_name,
    (
      SELECT c.brand_name
      FROM mst_purchase_catalog c
      WHERE c.item_id = g.item_id
        AND COALESCE(c.material_id, 0) = g.material_id
        AND COALESCE(c.buy_uom_id, 0) = g.buy_uom_id
        AND COALESCE(c.content_uom_id, 0) = g.content_uom_id
      ORDER BY COALESCE(c.is_active, 1) DESC, c.id DESC
      LIMIT 1
    ) AS candidate_brand_name,
    (
      SELECT c.line_description
      FROM mst_purchase_catalog c
      WHERE c.item_id = g.item_id
        AND COALESCE(c.material_id, 0) = g.material_id
        AND COALESCE(c.buy_uom_id, 0) = g.buy_uom_id
        AND COALESCE(c.content_uom_id, 0) = g.content_uom_id
      ORDER BY COALESCE(c.is_active, 1) DESC, c.id DESC
      LIMIT 1
    ) AS candidate_line_description,
    (
      SELECT COUNT(*)
      FROM mst_purchase_catalog c
      WHERE c.item_id = g.item_id
        AND COALESCE(c.material_id, 0) = g.material_id
        AND COALESCE(c.buy_uom_id, 0) = g.buy_uom_id
        AND COALESCE(c.content_uom_id, 0) = g.content_uom_id
        AND COALESCE(c.is_active, 1) = 1
    ) AS candidate_active_count,
    (
      SELECT COUNT(*)
      FROM mst_purchase_catalog c
      WHERE c.item_id = g.item_id
        AND COALESCE(c.material_id, 0) = g.material_id
        AND COALESCE(c.buy_uom_id, 0) = g.buy_uom_id
        AND COALESCE(c.content_uom_id, 0) = g.content_uom_id
    ) AS candidate_any_count
  FROM (
    SELECT
      'DIVISION' AS movement_scope,
      l.division_id,
      COALESCE(l.destination_type, '') AS destination_type,
      l.item_id,
      COALESCE(l.material_id, 0) AS material_id,
      l.buy_uom_id,
      l.content_uom_id,
      COUNT(*) AS movement_count,
      ROUND(SUM(l.qty_buy_delta), 4) AS qty_buy_delta_sum,
      ROUND(SUM(l.qty_content_delta), 4) AS qty_content_delta_sum,
      MIN(l.movement_date) AS first_movement_date,
      MAX(l.movement_date) AS last_movement_date
    FROM inv_stock_movement_log l
    WHERE l.movement_scope = 'DIVISION'
      AND COALESCE(l.material_id, 0) > 0
      AND COALESCE(l.profile_key, '') = ''
    GROUP BY
      l.division_id,
      COALESCE(l.destination_type, ''),
      l.item_id,
      COALESCE(l.material_id, 0),
      l.buy_uom_id,
      l.content_uom_id
  ) g
) x
ON DUPLICATE KEY UPDATE
  movement_count = VALUES(movement_count),
  qty_buy_delta_sum = VALUES(qty_buy_delta_sum),
  qty_content_delta_sum = VALUES(qty_content_delta_sum),
  first_movement_date = VALUES(first_movement_date),
  last_movement_date = VALUES(last_movement_date),
  candidate_profile_key_active = VALUES(candidate_profile_key_active),
  candidate_profile_key_any = VALUES(candidate_profile_key_any),
  candidate_catalog_name = VALUES(candidate_catalog_name),
  candidate_brand_name = VALUES(candidate_brand_name),
  candidate_line_description = VALUES(candidate_line_description),
  candidate_active_count = VALUES(candidate_active_count),
  candidate_any_count = VALUES(candidate_any_count),
  decision_status = VALUES(decision_status),
  chosen_profile_key = VALUES(chosen_profile_key),
  chosen_notes = VALUES(chosen_notes),
  updated_at = CURRENT_TIMESTAMP;

SELECT
  decision_status,
  COUNT(*) AS identity_count,
  SUM(movement_count) AS movement_rows,
  ROUND(SUM(qty_content_delta_sum), 4) AS qty_content_delta_sum
FROM z_work_inv_null_profile_movement_20260608
WHERE audit_date = CURDATE()
GROUP BY decision_status
ORDER BY decision_status;

SELECT
  w.id,
  w.decision_status,
  w.division_id,
  w.destination_type,
  w.item_id,
  i.item_name,
  w.material_id,
  m.material_name,
  w.buy_uom_id,
  bu.code AS buy_uom_code,
  w.content_uom_id,
  cu.code AS content_uom_code,
  w.movement_count,
  w.qty_content_delta_sum,
  w.first_movement_date,
  w.last_movement_date,
  w.candidate_active_count,
  w.candidate_any_count,
  COALESCE(w.candidate_profile_key_active, w.candidate_profile_key_any, '') AS suggested_profile_key,
  COALESCE(w.candidate_catalog_name, '') AS suggested_catalog_name,
  COALESCE(w.candidate_brand_name, '') AS suggested_brand_name,
  COALESCE(w.candidate_line_description, '') AS suggested_line_description,
  COALESCE(w.chosen_profile_key, '') AS chosen_profile_key,
  COALESCE(w.chosen_notes, '') AS chosen_notes
FROM z_work_inv_null_profile_movement_20260608 w
LEFT JOIN mst_item i ON i.id = w.item_id
LEFT JOIN mst_material m ON m.id = w.material_id
LEFT JOIN mst_uom bu ON bu.id = w.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = w.content_uom_id
WHERE w.audit_date = CURDATE()
ORDER BY w.division_id, w.destination_type, m.material_name, i.item_name, w.buy_uom_id, w.content_uom_id;
