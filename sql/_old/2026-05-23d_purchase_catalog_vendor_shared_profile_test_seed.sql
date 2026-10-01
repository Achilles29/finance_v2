SET NAMES utf8mb4;

START TRANSACTION;

SET @line_kind := 'MATERIAL';
SET @item_id := 194;
SET @material_id := 69;
SET @catalog_name := 'ZZTEST SHARED PROFILE';
SET @brand_name := 'CONTROL';
SET @line_description := 'EXACT SHARED PROFILE TWO VENDORS';
SET @buy_uom_id := 30;
SET @content_uom_id := 9;
SET @content_per_buy_num := 1000.000000;
SET @content_per_buy := '1000.000000';
SET @conversion_factor := 1000.00000000;
SET @expired_date := NULL;
SET @profile_key := SHA2(CONCAT_WS('|',
  UPPER(@line_kind),
  CAST(@item_id AS CHAR),
  CAST(@material_id AS CHAR),
  CAST(@buy_uom_id AS CHAR),
  CAST(@content_uom_id AS CHAR),
  UPPER(@catalog_name),
  @content_per_buy,
  UPPER(@brand_name),
  UPPER(@line_description),
  ''
), 256);

INSERT INTO mst_purchase_catalog (
  profile_key,
  line_kind,
  item_id,
  material_id,
  catalog_name,
  brand_name,
  line_description,
  buy_uom_id,
  content_uom_id,
  content_per_buy,
  conversion_factor_to_content,
  expired_date,
  is_active,
  created_at,
  updated_at
)
SELECT
  @profile_key,
  @line_kind,
  @item_id,
  @material_id,
  @catalog_name,
  @brand_name,
  @line_description,
  @buy_uom_id,
  @content_uom_id,
  @content_per_buy_num,
  @conversion_factor,
  @expired_date,
  1,
  NOW(),
  NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM mst_purchase_catalog c
  WHERE c.profile_key = @profile_key
);

SET @catalog_id := (
  SELECT c.id
  FROM mst_purchase_catalog c
  WHERE c.profile_key = @profile_key
  ORDER BY c.id ASC
  LIMIT 1
);

INSERT INTO mst_purchase_catalog_vendor (
  catalog_id,
  vendor_id,
  standard_price,
  last_unit_price,
  last_purchase_date,
  last_purchase_order_id,
  last_purchase_line_id,
  notes,
  is_active,
  created_at,
  updated_at
)
VALUES
  (
    @catalog_id,
    1,
    11111.00,
    12345.00,
    '2026-05-23',
    NULL,
    NULL,
    'CONTROLLED SHARED PROFILE TEST - VENDOR 1',
    1,
    NOW(),
    NOW()
  ),
  (
    @catalog_id,
    4,
    22222.00,
    23456.00,
    '2026-05-23',
    NULL,
    NULL,
    'CONTROLLED SHARED PROFILE TEST - VENDOR 4',
    1,
    NOW(),
    NOW()
  )
ON DUPLICATE KEY UPDATE
  standard_price = VALUES(standard_price),
  last_unit_price = VALUES(last_unit_price),
  last_purchase_date = VALUES(last_purchase_date),
  last_purchase_order_id = VALUES(last_purchase_order_id),
  last_purchase_line_id = VALUES(last_purchase_line_id),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT c.id, c.profile_key, c.catalog_name, c.brand_name, c.line_description, cv.vendor_id, cv.last_unit_price, cv.standard_price, cv.notes
FROM mst_purchase_catalog c
JOIN mst_purchase_catalog_vendor cv ON cv.catalog_id = c.id
WHERE c.profile_key = @profile_key
ORDER BY cv.vendor_id;