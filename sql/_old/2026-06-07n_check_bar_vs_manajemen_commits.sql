SET NAMES utf8mb4;

-- Cek apakah commit yang sama sudah punya movement di BAR
-- Kalau sudah ada di BAR → MANAJEMEN adalah duplikat, cukup hapus
-- Kalau TIDAK ada di BAR → perlu dipindahkan (insert BAR + delete MANAJEMEN)
SELECT
  m_wrong.id         AS manajemen_movement_id,
  m_wrong.movement_date,
  m_wrong.ref_id     AS commit_id,
  d_wrong.name       AS wrong_division,
  m_wrong.destination_type AS wrong_dest,
  COALESCE(mi.item_name, '-')     AS item_name,
  COALESCE(mm.material_name, '-') AS material_name,
  m_wrong.profile_key,
  m_wrong.qty_content_delta AS wrong_qty,
  m_bar.id           AS bar_movement_id,
  d_bar.name         AS bar_division,
  m_bar.qty_content_delta AS bar_qty
FROM inv_stock_movement_log m_wrong
JOIN mst_operational_division d_wrong ON d_wrong.id = m_wrong.division_id
LEFT JOIN mst_item     mi ON mi.id = m_wrong.item_id
LEFT JOIN mst_material mm ON mm.id = m_wrong.material_id
-- Cari apakah ada movement BAR untuk commit_id yang sama
LEFT JOIN inv_stock_movement_log m_bar
  ON m_bar.ref_table  = m_wrong.ref_table
 AND m_bar.ref_id     = m_wrong.ref_id
 AND m_bar.movement_type = m_wrong.movement_type
 AND m_bar.movement_scope = 'DIVISION'
 AND m_bar.destination_type IN ('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT')
 AND (
   (m_bar.item_id <=> m_wrong.item_id AND m_bar.material_id <=> m_wrong.material_id)
   OR m_bar.profile_key = m_wrong.profile_key
 )
LEFT JOIN mst_operational_division d_bar ON d_bar.id = m_bar.division_id
WHERE m_wrong.movement_scope   = 'DIVISION'
  AND m_wrong.destination_type = 'OTHER'
  AND m_wrong.ref_table        = 'pos_stock_commit'
  AND m_wrong.movement_type    = 'USAGE_OUT'
ORDER BY m_wrong.ref_id, m_wrong.id;
