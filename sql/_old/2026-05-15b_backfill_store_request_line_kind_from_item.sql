SET NAMES utf8mb4;

START TRANSACTION;

UPDATE pur_store_request_line l
JOIN mst_item i ON i.id = l.item_id
SET l.line_kind = 'MATERIAL',
    l.material_id = i.material_id,
    l.updated_at = CURRENT_TIMESTAMP
WHERE UPPER(COALESCE(l.line_kind, 'ITEM')) = 'ITEM'
  AND (l.material_id IS NULL OR l.material_id = 0)
  AND i.material_id IS NOT NULL;

COMMIT;
