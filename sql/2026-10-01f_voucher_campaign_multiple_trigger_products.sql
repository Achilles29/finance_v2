CREATE TABLE IF NOT EXISTS `pos_voucher_campaign_trigger_product` (
  `campaign_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`campaign_id`, `product_id`),
  KEY `idx_voucher_campaign_trigger_product_product` (`product_id`),
  CONSTRAINT `fk_voucher_campaign_trigger_product_campaign`
    FOREIGN KEY (`campaign_id`) REFERENCES `pos_voucher_campaign` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voucher_campaign_trigger_product_product`
    FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `pos_voucher_campaign_trigger_product` (`campaign_id`, `product_id`)
SELECT `id`, `trigger_product_id`
FROM `pos_voucher_campaign`
WHERE `trigger_product_id` IS NOT NULL AND `trigger_product_id` > 0;
