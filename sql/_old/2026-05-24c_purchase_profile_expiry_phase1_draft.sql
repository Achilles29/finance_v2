SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-24c_purchase_profile_expiry_phase1_draft.sql
-- Status : DRAFT ONLY
-- Tujuan : Tahap 1 migrasi expiry keluar dari identity profile catalog.
-- Catatan:
-- 1) File ini sengaja fokus ke audit + mapping review.
-- 2) Jangan jalankan blok update massal lintas tabel sebelum patch PHP tahap 1
--    aktif dan hasil review mapping disetujui.
-- 3) Pada tahap ini, expiry BELUM dipindah ke lot penuh. Yang dibekukan dulu
--    hanya identity profile catalog agar tidak lagi pecah per expiry.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Backup ringan katalog aktif sebelum rekey
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zz_backup_mst_purchase_catalog_20260524_expiry_phase1 AS
SELECT *
FROM mst_purchase_catalog
WHERE 1 = 0;

INSERT INTO zz_backup_mst_purchase_catalog_20260524_expiry_phase1
SELECT *
FROM mst_purchase_catalog;

-- ------------------------------------------------------------
-- B. Mapping identity baru TANPA expiry
-- ------------------------------------------------------------
DROP TABLE IF EXISTS tmp_purchase_catalog_expiry_phase1_map;
CREATE TABLE tmp_purchase_catalog_expiry_phase1_map (
  catalog_id BIGINT UNSIGNED NOT NULL,
  old_profile_key CHAR(64) NOT NULL,
  new_profile_key CHAR(64) NOT NULL,
  canonical_catalog_id BIGINT UNSIGNED NOT NULL,
  line_kind VARCHAR(20) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  catalog_name VARCHAR(150) NULL,
  brand_name VARCHAR(120) NULL,
  line_description VARCHAR(255) NULL,
  content_per_buy DECIMAL(18,6) NOT NULL,
  expired_date DATE NULL,
  is_duplicate_identity TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (catalog_id),
  KEY idx_tmp_purchase_catalog_expiry_phase1_new_key (new_profile_key),
  KEY idx_tmp_purchase_catalog_expiry_phase1_canonical (canonical_catalog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tmp_purchase_catalog_expiry_phase1_map (
  catalog_id,
  old_profile_key,
  new_profile_key,
  canonical_catalog_id,
  line_kind,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  catalog_name,
  brand_name,
  line_description,
  content_per_buy,
  expired_date,
  is_duplicate_identity
)
SELECT
  src.id AS catalog_id,
  src.profile_key AS old_profile_key,
  SHA2(CONCAT_WS('|',
    UPPER(TRIM(COALESCE(src.line_kind, 'ITEM'))),
    COALESCE(src.item_id, 0),
    COALESCE(src.material_id, 0),
    COALESCE(src.buy_uom_id, 0),
    COALESCE(src.content_uom_id, 0),
    UPPER(TRIM(COALESCE(src.catalog_name, ''))),
    UPPER(TRIM(COALESCE(src.brand_name, ''))),
    UPPER(TRIM(COALESCE(src.line_description, ''))),
    REPLACE(FORMAT(ROUND(COALESCE(src.content_per_buy, 0), 6), 6), ',', '')
  ), 256) AS new_profile_key,
  canon.id AS canonical_catalog_id,
  COALESCE(src.line_kind, 'ITEM') AS line_kind,
  src.item_id,
  src.material_id,
  src.buy_uom_id,
  src.content_uom_id,
  src.catalog_name,
  src.brand_name,
  src.line_description,
  ROUND(COALESCE(src.content_per_buy, 0), 6) AS content_per_buy,
  src.expired_date,
  CASE WHEN src.id <> canon.id THEN 1 ELSE 0 END AS is_duplicate_identity
FROM mst_purchase_catalog src
JOIN (
  SELECT
    MIN(s0.id) AS id,
    COALESCE(s0.line_kind, 'ITEM') AS line_kind,
    COALESCE(s0.item_id, 0) AS item_id_key,
    COALESCE(s0.material_id, 0) AS material_id_key,
    COALESCE(s0.buy_uom_id, 0) AS buy_uom_id_key,
    COALESCE(s0.content_uom_id, 0) AS content_uom_id_key,
    UPPER(TRIM(COALESCE(s0.catalog_name, ''))) AS catalog_name_key,
    UPPER(TRIM(COALESCE(s0.brand_name, ''))) AS brand_name_key,
    UPPER(TRIM(COALESCE(s0.line_description, ''))) AS line_description_key,
    ROUND(COALESCE(s0.content_per_buy, 0), 6) AS cpb_key
  FROM mst_purchase_catalog s0
  WHERE TRIM(COALESCE(s0.profile_key, '')) <> ''
  GROUP BY
    COALESCE(s0.line_kind, 'ITEM'),
    COALESCE(s0.item_id, 0),
    COALESCE(s0.material_id, 0),
    COALESCE(s0.buy_uom_id, 0),
    COALESCE(s0.content_uom_id, 0),
    UPPER(TRIM(COALESCE(s0.catalog_name, ''))),
    UPPER(TRIM(COALESCE(s0.brand_name, ''))),
    UPPER(TRIM(COALESCE(s0.line_description, ''))),
    ROUND(COALESCE(s0.content_per_buy, 0), 6)
) canon
  ON canon.line_kind = COALESCE(src.line_kind, 'ITEM')
 AND canon.item_id_key = COALESCE(src.item_id, 0)
 AND canon.material_id_key = COALESCE(src.material_id, 0)
 AND canon.buy_uom_id_key = COALESCE(src.buy_uom_id, 0)
 AND canon.content_uom_id_key = COALESCE(src.content_uom_id, 0)
 AND canon.catalog_name_key = UPPER(TRIM(COALESCE(src.catalog_name, '')))
 AND canon.brand_name_key = UPPER(TRIM(COALESCE(src.brand_name, '')))
 AND canon.line_description_key = UPPER(TRIM(COALESCE(src.line_description, '')))
 AND canon.cpb_key = ROUND(COALESCE(src.content_per_buy, 0), 6)
WHERE TRIM(COALESCE(src.profile_key, '')) <> '';

-- ------------------------------------------------------------
-- C. Dry-run audit: identity yang pecah hanya karena expiry
-- ------------------------------------------------------------
SELECT
  new_profile_key,
  COUNT(*) AS row_count,
  SUM(is_duplicate_identity) AS duplicate_row_count,
  MIN(canonical_catalog_id) AS canonical_catalog_id,
  MIN(catalog_name) AS sample_catalog_name,
  GROUP_CONCAT(DISTINCT COALESCE(DATE_FORMAT(expired_date, '%Y-%m-%d'), 'NULL') ORDER BY expired_date SEPARATOR ', ') AS expiry_variants,
  GROUP_CONCAT(catalog_id ORDER BY catalog_id SEPARATOR ',') AS catalog_ids
FROM tmp_purchase_catalog_expiry_phase1_map
GROUP BY new_profile_key
HAVING COUNT(*) > 1
ORDER BY row_count DESC, canonical_catalog_id ASC;

-- ------------------------------------------------------------
-- D. Dry-run audit: row yang profile_key-nya akan berubah walau tanpa duplicate
-- ------------------------------------------------------------
SELECT
  catalog_id,
  old_profile_key,
  new_profile_key,
  canonical_catalog_id,
  catalog_name,
  expired_date,
  is_duplicate_identity
FROM tmp_purchase_catalog_expiry_phase1_map
WHERE old_profile_key <> new_profile_key
ORDER BY canonical_catalog_id ASC, catalog_id ASC;

-- ------------------------------------------------------------
-- E. Catatan rollout tahap 1
-- ------------------------------------------------------------
-- Setelah patch PHP tahap 1 aktif, lakukan urutan berikut:
-- 1. Review hasil tmp_purchase_catalog_expiry_phase1_map.
-- 2. Jalankan normalisasi duplicate profile key dari aplikasi / skrip PHP,
--    bukan dari SQL mentah, agar referensi lintas tabel aman.
-- 3. Rekey catalog ke new_profile_key hanya setelah mapping duplicate disetujui.
-- 4. Tahap ini belum menghapus kolom expired_date / profile_expired_date.
--    Kolom tersebut masih dipertahankan sebagai legacy snapshot.

COMMIT;
