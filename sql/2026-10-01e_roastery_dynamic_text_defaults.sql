-- Keep reusable dynamic text visible on the blank canvas and lighter by default.
UPDATE `coffee_packaging_label_element`
SET `element_json` = CASE `element_key`
  WHEN 'text-free' THEN '{"type":"text","x":10,"y":10,"w":55,"h":12,"text":"Teks baru","field":"","font":"Jost","size":18,"color":"#50302a","bold":false,"fontWeight":500,"align":"left","opacity":1}'
  WHEN 'text-coffee-name' THEN '{"type":"text","x":8,"y":18,"w":70,"h":14,"text":"","field":"coffee_name","font":"Cormorant Garamond","size":30,"color":"#50302a","bold":false,"fontWeight":500,"align":"left","opacity":1}'
  WHEN 'text-origin' THEN '{"type":"text","x":8,"y":38,"w":55,"h":8,"text":"","field":"origin","font":"Space Grotesk","size":10,"color":"#50302a","bold":false,"fontWeight":500,"align":"left","opacity":1}'
  ELSE `element_json`
END,
`updated_at` = CURRENT_TIMESTAMP
WHERE `element_key` IN ('text-free','text-coffee-name','text-origin');
