SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-25e_drop_legacy_pos_printer_tables.sql
-- Tujuan : Menghapus sumber konfigurasi printer POS lama setelah
--          runtime berpindah sepenuhnya ke tabel pos_print_*.
--
-- Prasyarat:
-- 1) 2026-08-24c_pos_print_configuration_single_source_foundation.sql
-- 2) 2026-08-24d_pos_print_layout_customer_visibility.sql
-- 3) 2026-08-25a_pos_print_rule_mode_and_customer_review_foundation.sql
-- 4) 2026-08-25b_normalize_pos_print_paper_width_and_columns.sql
-- 5) 2026-08-25c_pos_customer_review_station_and_printable_qr.sql
-- 6) 2026-08-25d_seed_customer_review_print_settings.sql
-- 7) Kode Finance terbaru yang tidak lagi menggunakan fallback printer lama.
--
-- Catatan:
-- - Backup database sebelum menjalankan migration ini.
-- - pos_print_connection.legacy_printer_id dipertahankan sebagai nilai
--   historis biasa, bukan foreign key ke tabel yang dihapus.
-- - Order, payment, void, refund, shift, stok, dan riwayat transaksi tidak
--   ikut dihapus.
-- - DDL MySQL melakukan implicit commit; DROP TABLE dijalankan berurutan
--   dari tabel anak ke tabel induk.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS pos_printer_job_log;
DROP TABLE IF EXISTS pos_printer_job;
DROP TABLE IF EXISTS pos_printer_content_setting;
DROP TABLE IF EXISTS pos_printer_route_rule;
DROP TABLE IF EXISTS pos_printer_event_setting;
DROP TABLE IF EXISTS pos_printer_profile;
DROP TABLE IF EXISTS pos_printer_template;
DROP TABLE IF EXISTS pos_printer_template_master;
DROP TABLE IF EXISTS pos_printer_desktop_device;
DROP TABLE IF EXISTS pos_printer;

SET FOREIGN_KEY_CHECKS = 1;

SELECT TABLE_NAME, 'legacy printer table masih ada' AS status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'pos_printer',
    'pos_printer_content_setting',
    'pos_printer_desktop_device',
    'pos_printer_event_setting',
    'pos_printer_job',
    'pos_printer_job_log',
    'pos_printer_profile',
    'pos_printer_route_rule',
    'pos_printer_template',
    'pos_printer_template_master'
  );

SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'pos_print_%'
ORDER BY TABLE_NAME;
