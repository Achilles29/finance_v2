-- Reusable building blocks for the Roastery Label Studio canvas editor.
CREATE TABLE IF NOT EXISTS `coffee_packaging_label_element` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `element_key` varchar(80) NOT NULL,
  `element_name` varchar(120) NOT NULL,
  `category` varchar(32) NOT NULL,
  `element_json` mediumtext NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coffee_packaging_label_element_key` (`element_key`),
  KEY `idx_coffee_packaging_label_element_active` (`is_active`,`category`,`element_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reusable canvas elements for Roastery Label Studio';

INSERT INTO `coffee_packaging_label_element` (`element_key`,`element_name`,`category`,`element_json`,`is_system`,`is_active`) VALUES
('text-free','Teks bebas','TEXT','{"type":"text","x":10,"y":10,"w":55,"h":12,"text":"Teks baru","field":"","font":"Jost","size":18,"color":"#fff3df","bold":true,"align":"left","opacity":1}',1,1),
('text-coffee-name','Nama kopi dinamis','TEXT','{"type":"text","x":8,"y":18,"w":70,"h":14,"text":"","field":"coffee_name","font":"Cormorant Garamond","size":30,"color":"#fff3df","bold":true,"align":"left","opacity":1}',1,1),
('text-origin','Origin dinamis','TEXT','{"type":"text","x":8,"y":38,"w":55,"h":8,"text":"","field":"origin","font":"Space Grotesk","size":10,"color":"#ffe1b5","bold":true,"align":"left","opacity":1}',1,1),
('mountain-ridge','Garis gunung','ORNAMENT','{"type":"mountain","pathKey":"ridge","x":0,"y":42,"w":100,"h":48,"stroke":"#ffe3bb","strokeWidth":1.2,"opacity":0.78}',1,1),
('mountain-contours','Kontur pegunungan','ORNAMENT','{"type":"mountain","pathKey":"contours","x":0,"y":48,"w":100,"h":42,"stroke":"#f4ba83","strokeWidth":0.8,"opacity":0.58}',1,1),
('circle-outline','Lingkaran kontur','SHAPE','{"type":"circle","x":60,"y":8,"w":34,"h":48,"fill":"transparent","stroke":"#ffdda8","strokeWidth":0.8,"opacity":0.7}',1,1),
('sun-disc','Cakrawala','SHAPE','{"type":"circle","x":66,"y":12,"w":28,"h":40,"fill":"#ef7750","stroke":"#ffdda8","strokeWidth":0.5,"opacity":0.24}',1,1),
('line-fine','Garis tipis','SHAPE','{"type":"line","x":8,"y":65,"w":84,"h":1,"stroke":"#ffe3bb","strokeWidth":0.7,"opacity":0.8}',1,1),
('rect-frame','Bingkai sudut','SHAPE','{"type":"rect","x":4,"y":4,"w":92,"h":92,"fill":"transparent","stroke":"#ffe3bb","strokeWidth":0.5,"opacity":0.48}',1,1)
ON DUPLICATE KEY UPDATE
  `element_name`=VALUES(`element_name`),
  `category`=VALUES(`category`),
  `element_json`=VALUES(`element_json`),
  `is_active`=1,
  `updated_at`=CURRENT_TIMESTAMP;
