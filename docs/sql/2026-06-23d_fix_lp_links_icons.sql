-- Fix: pastikan emoji tersimpan benar (SET NAMES utf8mb4 wajib sebelum UPDATE)
-- Jalankan via phpMyAdmin atau CLI: mysql --default-character-set=utf8mb4 db_finance < file.sql

SET NAMES utf8mb4;

UPDATE `lp_links` SET `icon` = '🗞️' WHERE `id` = 1; -- MENU BOOK
UPDATE `lp_links` SET `icon` = '🍃' WHERE `id` = 2; -- Location
UPDATE `lp_links` SET `icon` = '🫶' WHERE `id` = 3; -- WA Namua
UPDATE `lp_links` SET `icon` = '🚀' WHERE `id` = 4; -- Order by GRAB

-- Verifikasi
SELECT id, label, icon, url FROM `lp_links` ORDER BY sort_order;
