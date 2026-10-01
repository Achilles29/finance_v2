-- Roastery Label Studio: one reusable design document for every label template.
-- The two previous model choices are seeded as normal system templates; custom
-- templates use the same canvas, block, and print configuration.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `coffee_packaging_label_template` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_key` varchar(80) NOT NULL,
  `template_name` varchar(160) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `design_json` mediumtext NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coffee_packaging_label_template_key` (`template_key`),
  KEY `idx_coffee_packaging_label_template_active` (`is_active`,`is_system`,`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reusable design templates for Roastery Label Studio';

INSERT INTO `coffee_packaging_label_template`
  (`template_key`, `template_name`, `description`, `design_json`, `is_system`, `is_active`)
VALUES
  (
    'classic-portrait',
    'Classic Portrait',
    'Template awal model 1: label tegak 90 x 140 mm.',
    '{"schema":"roastery-label-template-v1","canvas":{"width":90,"height":140,"theme":"heritage-cream","artworkMode":"full","artworkFit":"stretch","patternMode":"contour"},"print":{"paper":"A4","orientation":"portrait","paperW":210,"paperH":297,"perSheet":4,"margin":6,"gap":3,"cutLine":true}}',
    1,
    1
  ),
  (
    'retail-wide',
    'Retail Wide',
    'Template awal model 2: label lebar 100 x 68 mm.',
    '{"schema":"roastery-label-template-v1","canvas":{"width":100,"height":68,"theme":"clean-white","artworkMode":"full","artworkFit":"cover","patternMode":"none"},"print":{"paper":"A4","orientation":"portrait","paperW":210,"paperH":297,"perSheet":4,"margin":5,"gap":2.5,"cutLine":true},"blocks":{"logo":{"x":28,"y":5,"w":44},"coffee_name":{"x":15,"y":26,"w":70,"size":20},"roastery_kicker":{"x":15,"y":19,"w":70},"taste_icons":{"x":15,"y":48,"w":70,"size":13},"info_panel":{"x":10,"y":67,"w":80,"h":27,"size":5.5}}}',
    1,
    1
  )
ON DUPLICATE KEY UPDATE
  `template_name` = VALUES(`template_name`),
  `description` = VALUES(`description`),
  `design_json` = VALUES(`design_json`),
  `is_system` = 1,
  `is_active` = 1,
  `updated_at` = CURRENT_TIMESTAMP;
