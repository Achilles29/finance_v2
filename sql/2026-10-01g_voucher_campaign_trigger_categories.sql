CREATE TABLE IF NOT EXISTS `pos_voucher_campaign_trigger_category` (
  `campaign_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`campaign_id`, `category_id`),
  KEY `idx_voucher_campaign_trigger_category_category` (`category_id`),
  CONSTRAINT `fk_voucher_campaign_trigger_category_campaign`
    FOREIGN KEY (`campaign_id`) REFERENCES `pos_voucher_campaign` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voucher_campaign_trigger_category_category`
    FOREIGN KEY (`category_id`) REFERENCES `mst_product_category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
