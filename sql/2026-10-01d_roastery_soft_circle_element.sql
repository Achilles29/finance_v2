-- Add the soft, feathered circular disc from the label artwork as a reusable element.
INSERT INTO `coffee_packaging_label_element`
  (`element_key`,`element_name`,`category`,`element_json`,`is_system`,`is_active`)
VALUES
  ('soft-circle-halo','Lingkaran lembut','SHAPE','{"type":"circle","x":52,"y":12,"w":44,"h":65,"fill":"#ef7750","stroke":"transparent","strokeWidth":0,"opacity":0.9,"soft":true}',1,1)
ON DUPLICATE KEY UPDATE
  `element_name`=VALUES(`element_name`),
  `category`=VALUES(`category`),
  `element_json`=VALUES(`element_json`),
  `is_active`=1,
  `updated_at`=CURRENT_TIMESTAMP;
