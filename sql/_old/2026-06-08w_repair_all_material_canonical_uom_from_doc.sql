SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08w_repair_all_material_canonical_uom_from_doc.sql
-- Tujuan :
-- 1) Menormalkan UOM beli kanonik untuk SEMUA material yang
--    sudah diputuskan di docs/_UOM_BELI.MD
-- 2) Menjaga qty_content sebagai source of truth, lalu
--    menghitung ulang qty_buy berdasarkan content_per_buy
-- 3) Menyapu master + tabel aktif agar siklus PO / SR /
--    monthly / movement / fifo / POS tidak pecah UOM lagi
--
-- Catatan penting:
-- - Script ini memang intervensi data secara luas
-- - Backup temporary table dibuat untuk tabel aktif utama
-- - content_per_buy per profile dipertahankan bila profile
--   catalog yang cocok masih ada dan valid
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair all canonical material buy UOM from _UOM_BELI 2026-06-08';

INSERT INTO mst_uom (code, name, description, is_active)
SELECT 'JRG', 'JERIGEN', 'UOM beli kanonik untuk material jerigen', 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM mst_uom WHERE code = 'JRG'
);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_choice_all;
CREATE TEMPORARY TABLE tmp_uom_choice_all (
  material_name VARCHAR(255) NOT NULL,
  chosen_buy_uom_code VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TIMUN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TELUR', 'KG');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TOMAT', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('GARAM', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MINYAK GORENG', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MSG', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JERUK NIPIS', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KALDU AYAM', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('PISANG KITCHEN', 'SSR');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KECAP MANIS', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SAUS TIRAM', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('PISANG BAR', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KEMENYAN BALI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BOWL TA', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ICE CUBE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('FRESH MILK', 'DUS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SELADA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('DAUN BAWANG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SKM PUTIH', 'CAN');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('AIR MINERAL GALON', 'GLN');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KEMANGI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CABAI RAWIT MERAH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BAWANG BOMBAY', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('NORI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BERAS', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BAWANG PUTIH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MAYONAISE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('AIR MINERAL BOTOL', 'DUS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('WORTEL', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TAHU', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BAWANG MERAH GORENG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BAWANG MERAH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KERUPUK BAWANG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('GULA PASIR KITCHEN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TEMPE', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('HOUSE BLEND 70', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KECAP ASIN', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SARI LEMON', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('AIR', 'JRG');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('STRAWBERRY', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CHOCOLATE POWDER', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KOL PUTIH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SEREH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KEJU MOZARELLA', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ESPRESSO ARABIKA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KECAMBAH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MARGARIN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP STRAWBERRY', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JAMUR KUPING', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('LYCHEE FRUIT', 'CAN');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP LYCHEE', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JAHE GAJAH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KEJU CHEDDAR', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CARAMEL CRUMB', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('FRENCH FRIES', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('AYAM DADA FILLET', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('GREEN TEA POWDER', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TIRAMITSU POWDER', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CABAI KERING', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TEPUNG MAIZENA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('AIR ISI ULANG GALON', 'GLN');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ICE CREAM VANILLA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KULIT PANGSIT', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('OREO CRUMB', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP AREN', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP CARAMEL', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BUNCIS', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KOL UNGU', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP VANILLA', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('AYAM PAHA FILLET', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TEPUNG TERIGU', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CHOCO BALL', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('EMPING', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('GULA PASIR BAR', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('STRAWBERRY SAUCE', 'UNIT');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BLACKPEPPER', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CHOCOLATE PASTE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MADU', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('RED VELVET POWDER', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JAMUR ENOKI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MIE URAI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CABAI MERAH KERITING', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KANI STICK', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('LENGKUAS', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ROTI TAWAR', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('COOKING CREAM', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KUNYIT', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP KAWIS', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BREAD CRUMB', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CARAMEL SAUCE', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('GULA JAWA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('WIJEN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ABON SAPI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('PAKCOY', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KULIT AYAM', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SODA PLAIN', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SAUS TOMAT', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP HAZELNUT', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TOBIKO', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BEEF SHORTPLATE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CABAI BUBUK', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JELLY LYCHEE', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KACANG TANAH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KEMIRI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KLUWEK', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('OATMILK', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BBQ KNORR', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ICE CREAM COKLAT', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KENTANG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('PAPER FILTER V60', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TEH DANDANG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CHIKUWA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('DAUN JERUK', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SINGLE ORIGIN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TUSUK SATE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BROWN SUGAR', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CABAI HIJAU TEROPONG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CIRENG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SAUS SAMBAL', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ALMOND', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BAWANG PUTIH GORENG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CABAI HIJAU KERITING', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('GROUND BEEF', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JELLY CHOCOLATE', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('LEMON', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MINYAK WIJEN', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('POPCORN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KAPULAGA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KENCUR', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KULIT LUMPIA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('LADA PUTIH', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('PALA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP RUM', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SMOKED BEEF', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TERASI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BAKING POWDER', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP ROSE', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('WEDANG UWUH', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('DAUN SALAM', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('DRIED LIME', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JERUK LIMAU', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KEJU SPREADY', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KOPI LELET', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MAKARONI', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP MOJITO', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('WHIPPED CREAM', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KERUPUK UDANG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JINTEN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CUKA', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('LAMB CHOP', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SMOKED FISH PE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TEH GOPEK', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('UDANG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KETUMBAR', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ORANGE CHEESE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('ROTI BUN BURGER', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SEMANGKA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRLOIN MELTIQUE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TAHU PONG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TEPUNG TAPIOKA', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('YELLOW MUSTARD', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BUNGA LAWANG', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KALDU SAPI', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SIRUP BUTTERSCOTCH', 'BTL');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SO WANOJA FP', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('TEPUNG BERAS', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CITRUN', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CUMI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('DORI FILLET', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('DRIED LEMON', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('DRY BAY LEAF', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KULIT DIMSUM', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SAUS BANGKOK', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SPAGHETTI', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BISKUAT', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BAWANG PUTIH BUBUK', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BEEF PATTIES', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CABAI RAWIT HIJAU', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('CARNATION EVAPORASI', 'CAN');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('PAPER FILTER KALITA WAVE', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('KUNYIT BUBUK', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SOSIS', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('JAHE EMPRIT', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('MARSHMELLOW', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('IGA SAPI', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('SO ARGOPURO LYCHEE SORBET', 'PACK');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('BOTOL PLASTIK', 'PCS');
INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('PEWARNA MAKANAN HIJAU', 'PCS');

DROP TEMPORARY TABLE IF EXISTS tmp_all_material_canonical;
CREATE TEMPORARY TABLE tmp_all_material_canonical AS
SELECT
  m.id AS material_id,
  m.material_name,
  uc.chosen_buy_uom_code,
  bu.id AS canonical_buy_uom_id,
  bu.code AS canonical_buy_uom_code,
  COALESCE(ai.content_uom_id, cc.content_uom_id, ac.content_uom_id, sm.content_uom_id) AS canonical_content_uom_id,
  COALESCE(cu.code, '?') AS canonical_content_uom_code,
  ROUND(COALESCE(
    NULLIF(cc.content_per_buy, 0),
    CASE
      WHEN COALESCE(ai.buy_uom_id, 0) = COALESCE(bu.id, 0) THEN NULLIF(ai.content_per_buy, 0)
      ELSE NULL
    END,
    NULLIF(ac.content_per_buy, 0),
    CASE
      WHEN COALESCE(sm.buy_uom_id, 0) = COALESCE(bu.id, 0) THEN NULLIF(sm.profile_content_per_buy, 0)
      ELSE NULL
    END,
    1
  ), 6) AS default_content_per_buy
FROM tmp_uom_choice_all uc
JOIN mst_material m
  ON BINARY TRIM(m.material_name) = BINARY TRIM(uc.material_name)
JOIN mst_uom bu
  ON BINARY TRIM(bu.code) = BINARY TRIM(uc.chosen_buy_uom_code)
LEFT JOIN (
  SELECT i1.material_id, i1.buy_uom_id, i1.content_uom_id, ROUND(COALESCE(NULLIF(i1.content_per_buy, 0), 1), 6) AS content_per_buy
  FROM mst_item i1
  JOIN (
    SELECT material_id, MAX(id) AS keep_id
    FROM mst_item
    WHERE COALESCE(is_active, 1) = 1
      AND COALESCE(material_id, 0) > 0
    GROUP BY material_id
  ) pick ON pick.keep_id = i1.id
) ai ON ai.material_id = m.id
LEFT JOIN (
  SELECT c1.material_id, bu1.code AS buy_uom_code, c1.content_uom_id, ROUND(COALESCE(NULLIF(c1.content_per_buy, 0), 1), 6) AS content_per_buy
  FROM mst_purchase_catalog c1
  JOIN mst_uom bu1 ON bu1.id = c1.buy_uom_id
  JOIN (
    SELECT material_id, buy_uom_id, MAX(id) AS keep_id
    FROM mst_purchase_catalog
    WHERE COALESCE(is_active, 1) = 1
      AND COALESCE(material_id, 0) > 0
    GROUP BY material_id, buy_uom_id
  ) pick ON pick.keep_id = c1.id
) cc
  ON cc.material_id = m.id
 AND BINARY TRIM(cc.buy_uom_code) = BINARY TRIM(uc.chosen_buy_uom_code)
LEFT JOIN (
  SELECT c1.material_id, c1.content_uom_id, ROUND(COALESCE(NULLIF(c1.content_per_buy, 0), 1), 6) AS content_per_buy
  FROM mst_purchase_catalog c1
  JOIN (
    SELECT material_id, MAX(id) AS keep_id
    FROM mst_purchase_catalog
    WHERE COALESCE(is_active, 1) = 1
      AND COALESCE(material_id, 0) > 0
    GROUP BY material_id
  ) pick ON pick.keep_id = c1.id
) ac ON ac.material_id = m.id
LEFT JOIN (
  SELECT s1.material_id, s1.buy_uom_id, s1.content_uom_id, ROUND(COALESCE(NULLIF(s1.profile_content_per_buy, 0), 1), 6) AS profile_content_per_buy
  FROM inv_division_monthly_stock s1
  JOIN (
    SELECT material_id, MAX(id) AS keep_id
    FROM inv_division_monthly_stock
    WHERE COALESCE(material_id, 0) > 0
    GROUP BY material_id
  ) pick ON pick.keep_id = s1.id
) sm ON sm.material_id = m.id
LEFT JOIN mst_uom cu
  ON cu.id = COALESCE(ai.content_uom_id, cc.content_uom_id, ac.content_uom_id, sm.content_uom_id);

ALTER TABLE tmp_all_material_canonical
  ADD PRIMARY KEY (material_id);

DROP TEMPORARY TABLE IF EXISTS tmp_all_catalog_snapshot;
CREATE TEMPORARY TABLE tmp_all_catalog_snapshot AS
SELECT
  c.id,
  c.material_id,
  COALESCE(c.profile_key, '') AS profile_key,
  ROUND(COALESCE(NULLIF(c.content_per_buy, 0), d.default_content_per_buy), 6) AS canonical_content_per_buy
FROM mst_purchase_catalog c
JOIN tmp_all_material_canonical d ON d.material_id = c.material_id;

ALTER TABLE tmp_all_catalog_snapshot
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_all_catalog_snapshot_profile (material_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_mst_item;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_mst_item AS
SELECT * FROM mst_item WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_mst_purchase_catalog;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_mst_purchase_catalog AS
SELECT * FROM mst_purchase_catalog WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_division_monthly_stock;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_division_monthly_stock AS
SELECT * FROM inv_division_monthly_stock WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_warehouse_monthly_stock;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_warehouse_monthly_stock AS
SELECT * FROM inv_warehouse_monthly_stock WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_division_stock_opening_snapshot;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_division_stock_opening_snapshot AS
SELECT * FROM inv_division_stock_opening_snapshot WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_warehouse_stock_opening_snapshot;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_warehouse_stock_opening_snapshot AS
SELECT * FROM inv_warehouse_stock_opening_snapshot WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_stock_movement_log;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_stock_movement_log AS
SELECT * FROM inv_stock_movement_log WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_material_fifo_lot;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_material_fifo_lot AS
SELECT * FROM inv_material_fifo_lot WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_material_fifo_issue_log;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_material_fifo_issue_log AS
SELECT * FROM inv_material_fifo_issue_log WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_stock_adjustment_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_stock_adjustment_line AS
SELECT * FROM inv_stock_adjustment_line WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_division_request_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_division_request_line AS
SELECT * FROM pur_division_request_line WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_store_request_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_store_request_line AS
SELECT * FROM pur_store_request_line WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_store_request_fulfillment_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_store_request_fulfillment_line AS
SELECT * FROM pur_store_request_fulfillment_line WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_purchase_order_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_purchase_order_line AS
SELECT * FROM pur_purchase_order_line WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_purchase_receipt_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_purchase_receipt_line AS
SELECT * FROM pur_purchase_receipt_line WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);

UPDATE mst_item i
JOIN tmp_all_material_canonical d ON d.material_id = i.material_id
SET
  i.buy_uom_id = d.canonical_buy_uom_id,
  i.content_uom_id = d.canonical_content_uom_id,
  i.content_per_buy = d.default_content_per_buy,
  i.updated_at = CURRENT_TIMESTAMP;

UPDATE mst_purchase_catalog c
JOIN tmp_all_material_canonical d ON d.material_id = c.material_id
SET
  c.buy_uom_id = d.canonical_buy_uom_id,
  c.content_uom_id = d.canonical_content_uom_id,
  c.content_per_buy = ROUND(COALESCE(NULLIF(c.content_per_buy, 0), d.default_content_per_buy), 6),
  c.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_division_monthly_stock s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_stock_movement_log l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_delta = ROUND(COALESCE(l.qty_content_delta, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_after = ROUND(COALESCE(l.qty_content_after, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

UPDATE inv_stock_adjustment_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.available_qty_buy = ROUND(COALESCE(l.available_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.note = LEFT(TRIM(CONCAT(COALESCE(l.note, ''), CASE WHEN COALESCE(l.note, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_material_fifo_lot f
JOIN tmp_all_material_canonical d ON d.material_id = f.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = f.material_id
 AND cs.profile_key = COALESCE(f.profile_key, '')
SET
  f.qty_in = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_in, 0)
        ELSE COALESCE(f.qty_in, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.qty_out = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_out, 0)
        ELSE COALESCE(f.qty_out, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.qty_balance = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_balance, 0)
        ELSE COALESCE(f.qty_balance, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.buy_uom_id = d.canonical_buy_uom_id,
  f.content_uom_id = d.canonical_content_uom_id,
  f.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_material_fifo_issue_log l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.issue_qty = ROUND(
    (
      CASE
        WHEN COALESCE(l.buy_uom_id, 0) = COALESCE(l.content_uom_id, 0) THEN COALESCE(l.issue_qty, 0)
        ELSE COALESCE(l.issue_qty, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

UPDATE pur_division_request_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_store_request_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_approved = ROUND(COALESCE(l.qty_content_approved, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_fulfilled = ROUND(COALESCE(l.qty_content_fulfilled, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_store_request_fulfillment_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_posted = ROUND(COALESCE(l.qty_content_posted, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_order_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 6),
  l.conversion_factor_to_content = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 8),
  l.snapshot_buy_uom_code = d.canonical_buy_uom_code,
  l.snapshot_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy = ROUND(COALESCE(l.qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_receipt_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.conversion_factor_to_content = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 8),
  l.qty_buy_received = ROUND(COALESCE(l.qty_content_received, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'selected_materials' AS metric, COUNT(*) AS total FROM tmp_all_material_canonical
UNION ALL
SELECT 'mst_item_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_mst_item
UNION ALL
SELECT 'mst_purchase_catalog_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_mst_purchase_catalog
UNION ALL
SELECT 'inv_division_monthly_stock_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_division_monthly_stock
UNION ALL
SELECT 'inv_warehouse_monthly_stock_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_warehouse_monthly_stock
UNION ALL
SELECT 'inv_division_stock_opening_snapshot_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_division_stock_opening_snapshot
UNION ALL
SELECT 'inv_warehouse_stock_opening_snapshot_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_warehouse_stock_opening_snapshot
UNION ALL
SELECT 'inv_stock_movement_log_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_stock_movement_log
UNION ALL
SELECT 'inv_material_fifo_lot_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_material_fifo_lot
UNION ALL
SELECT 'inv_material_fifo_issue_log_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_material_fifo_issue_log
UNION ALL
SELECT 'inv_stock_adjustment_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_stock_adjustment_line
UNION ALL
SELECT 'pur_division_request_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_division_request_line
UNION ALL
SELECT 'pur_store_request_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_store_request_line
UNION ALL
SELECT 'pur_store_request_fulfillment_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_store_request_fulfillment_line
UNION ALL
SELECT 'pur_purchase_order_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_purchase_order_line
UNION ALL
SELECT 'pur_purchase_receipt_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_purchase_receipt_line
;
