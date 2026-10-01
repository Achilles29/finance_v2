-- ============================================================
-- Tambah tabel inv_component_stock_opname
-- Digunakan untuk menyimpan hasil hitung fisik stok component
-- per tanggal opname (harian), mirip inv_division_stock_opname.
--
-- Aman diulang (idempotent) — CREATE TABLE IF NOT EXISTS.
-- ============================================================

CREATE TABLE IF NOT EXISTS `inv_component_stock_opname` (
  `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `opname_date`   DATE             NOT NULL,
  `location_type` VARCHAR(20)      NOT NULL DEFAULT 'REGULER'
                  COMMENT 'REGULER atau EVENT (grup dari inv_component_monthly_stock)',
  `division_id`   INT(11) UNSIGNED DEFAULT NULL,
  `component_id`  INT(11) UNSIGNED NOT NULL,
  `uom_id`        INT(11) UNSIGNED NOT NULL,
  `system_qty`    DECIMAL(18,4)    DEFAULT NULL
                  COMMENT 'Qty sistem saat opname diambil',
  `physical_qty`  DECIMAL(18,4)    DEFAULT NULL
                  COMMENT 'Qty fisik hasil hitung',
  `notes`         TEXT             DEFAULT NULL,
  `adjustment_id` INT(11) UNSIGNED DEFAULT NULL
                  COMMENT 'ID adjustment yang dibuat dari opname ini',
  `created_by`    INT(11) UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP        NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmp_opname`
    (`opname_date`, `location_type`, `division_id`, `component_id`, `uom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CEK HASIL ────────────────────────────────────────────────
-- SELECT * FROM inv_component_stock_opname LIMIT 10;
