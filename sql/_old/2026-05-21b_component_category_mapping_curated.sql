SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-21b_component_category_mapping_curated.sql
-- Tujuan :
-- 1) Menambah kategori komponen yang lebih relevan untuk list base/prepare terbaru.
-- 2) Mapping komponen ke kategori berdasarkan component_name.
-- Catatan:
-- - Aman di-run berulang (idempotent) via ON DUPLICATE KEY UPDATE.
-- - Scope default PREPARE untuk mayoritas kategori olahan.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Seed kategori curated
-- ------------------------------------------------------------
INSERT INTO mst_component_category (code, name, scope_type, sort_order, is_active)
VALUES
  ('CAT_BEV_BASE', 'Beverage Base & Infusion', 'PREPARE', 10, 1),
  ('CAT_SAUCE_COND', 'Sauce, Sambal & Condiment', 'PREPARE', 20, 1),
  ('CAT_PASTE_SEASON', 'Paste, Marinasi & Bumbu', 'PREPARE', 30, 1),
  ('CAT_BROTH_SOUP', 'Broth, Kuah & Soup Base', 'PREPARE', 40, 1),
  ('CAT_DRESSING', 'Dressing & Salad Prep', 'PREPARE', 50, 1),
  ('CAT_DOUGH_COAT', 'Dough, Batter & Coating', 'PREPARE', 60, 1),
  ('CAT_STAPLE_BASE', 'Staple Base (Rice/Noodle)', 'BASE', 70, 1),
  ('CAT_DESSERT_BASE', 'Dessert & Sweet Base', 'PREPARE', 80, 1),
  ('CAT_PROTEIN_PREP', 'Protein Prep & Filling', 'PREPARE', 90, 1),
  ('CAT_PICKLE_ACID', 'Pickle, Acar & Acid Prep', 'PREPARE', 100, 1),
  ('CAT_POWDER_DRY', 'Dry Mix, Powder & Crumble', 'PREPARE', 110, 1),
  ('CAT_MISC_PREP', 'Misc Prep', 'ALL', 120, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  scope_type = VALUES(scope_type),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- helper CTE-like via variable lookups
SET @cat_bev_base      := (SELECT id FROM mst_component_category WHERE code = 'CAT_BEV_BASE' LIMIT 1);
SET @cat_sauce_cond    := (SELECT id FROM mst_component_category WHERE code = 'CAT_SAUCE_COND' LIMIT 1);
SET @cat_paste_season  := (SELECT id FROM mst_component_category WHERE code = 'CAT_PASTE_SEASON' LIMIT 1);
SET @cat_broth_soup    := (SELECT id FROM mst_component_category WHERE code = 'CAT_BROTH_SOUP' LIMIT 1);
SET @cat_dressing      := (SELECT id FROM mst_component_category WHERE code = 'CAT_DRESSING' LIMIT 1);
SET @cat_dough_coat    := (SELECT id FROM mst_component_category WHERE code = 'CAT_DOUGH_COAT' LIMIT 1);
SET @cat_staple_base   := (SELECT id FROM mst_component_category WHERE code = 'CAT_STAPLE_BASE' LIMIT 1);
SET @cat_dessert_base  := (SELECT id FROM mst_component_category WHERE code = 'CAT_DESSERT_BASE' LIMIT 1);
SET @cat_protein_prep  := (SELECT id FROM mst_component_category WHERE code = 'CAT_PROTEIN_PREP' LIMIT 1);
SET @cat_pickle_acid   := (SELECT id FROM mst_component_category WHERE code = 'CAT_PICKLE_ACID' LIMIT 1);
SET @cat_powder_dry    := (SELECT id FROM mst_component_category WHERE code = 'CAT_POWDER_DRY' LIMIT 1);
SET @cat_misc_prep     := (SELECT id FROM mst_component_category WHERE code = 'CAT_MISC_PREP' LIMIT 1);

-- ------------------------------------------------------------
-- B. Mapping komponen ke kategori
-- ------------------------------------------------------------

-- 1) Beverage Base & Infusion
UPDATE mst_component
SET component_category_id = @cat_bev_base
WHERE component_name IN (
  'SIMPLE SYRUP',
  'INFUSED BLUE PEA TEA',
  'ESPRESSO BLEND',
  'COLD BREW',
  'BASE TEA',
  'BASED LEMONGRASS',
  'INFUSE CINNAMON'
);

-- 2) Sauce, Sambal & Condiment
UPDATE mst_component
SET component_category_id = @cat_sauce_cond
WHERE component_name IN (
  'TERIYAKI SAUCE',
  'BARBEQUE SAUCE',
  'SAUCE BANGKOK',
  'HOT VOLCANO MAYO',
  'SAMBAL KECAP',
  'SAMBAL MATAH',
  'SAMBAL REBUS IGA',
  'SAMBAL BAWANG GEPREK',
  'SAMBAL DABU-DABU',
  'SAMBAL IDJO',
  'MIE AYAM SAUCE',
  'CUKO',
  'HONEY SAUCE',
  'CHEESE SAUCE',
  'GARLIC OIL'
);

-- 3) Paste, Marinasi & Bumbu
UPDATE mst_component
SET component_category_id = @cat_paste_season
WHERE component_name IN (
  'BUMBU BAKAR MADU',
  'BUMBU BAKAR PARAPE',
  'BUMBU MERAH PASTE TERASI',
  'BUMBU KUNING MARINASI AYAM',
  'TONGSENG PASTE',
  'MARINASI AYAM BAKAR',
  'MARINASI SONGKEM',
  'MARINASI BEBEK IRENG',
  'MIX SAUCE FRIED RICE & NOODLES',
  'KALIO PASTE',
  'BUMBU IRENG MADURA',
  'TOMYUM PASTE'
);

-- 4) Broth, Kuah & Soup Base
UPDATE mst_component
SET component_category_id = @cat_broth_soup
WHERE component_name IN (
  'KUAH IGA',
  'TORI PAITAN RAMEN BROTH',
  'KELO MRICO BROTH',
  'SOYU RAMEN BROTH',
  'SOTO BETAWI'
);

-- 5) Dressing & Salad Prep
UPDATE mst_component
SET component_category_id = @cat_dressing
WHERE component_name IN (
  'THAI DRESSING',
  'HONEY MUSTARD',
  'TAR-TAR DRESSING',
  'SESAME DRESSING',
  'COLESLAW SALAD'
);

-- 6) Dough, Batter & Coating
UPDATE mst_component
SET component_category_id = @cat_dough_coat
WHERE component_name IN (
  'MIX FLOUR',
  'KREMESAN',
  'SKIN CREPES',
  'ADONAN MENDOAN'
);

-- 7) Staple Base (Rice/Noodle)
UPDATE mst_component
SET component_category_id = @cat_staple_base
WHERE component_name IN (
  'NASI BASE GEDE',
  'BASE MIE LEVEL',
  'BASE GEDE'
);

-- 8) Dessert & Sweet Base
UPDATE mst_component
SET component_category_id = @cat_dessert_base
WHERE component_name IN (
  'JELLY CHOCOLATE BASE',
  'JELLY LYCHEE BASE',
  'SALTED CARAMEL',
  'PANNACOTTA',
  'CREAM EGG FRANCH TOAST',
  'FILL PISANG CARAMEL'
);

-- 9) Protein Prep & Filling
UPDATE mst_component
SET component_category_id = @cat_protein_prep
WHERE component_name IN (
  'CHICKEN HONEY GARLIC MARINATED',
  'DIMSUM FILL',
  'SATE LILIT',
  'MINCED CHICKEN',
  'NUGET',
  'POACHED EEG'
);

-- 10) Pickle, Acar & Acid Prep
UPDATE mst_component
SET component_category_id = @cat_pickle_acid
WHERE component_name IN (
  'LEMON JUICE',
  'ACAR PICKLED',
  'RED GINGER PICKLED',
  'SARI SUSHI'
);

-- 11) Dry Mix, Powder & Crumble
UPDATE mst_component
SET component_category_id = @cat_powder_dry
WHERE component_name IN (
  'MIX SEASONING POWDER',
  'ARABIAN MAGIC POWDER',
  'MAGIC POWDER SHORT RIBS',
  'TING TING CRUMBLE'
);

-- 12) Misc Prep
UPDATE mst_component
SET component_category_id = @cat_misc_prep
WHERE component_name IN (
  'ADU RAMU',
  'WHIPPING CREAM',
  'CREAM CHEESE',
  'BOLOGNESE',
  'CELURY'
);

COMMIT;

-- Quick check hasil mapping
SELECT
  c.component_name,
  cc.code AS category_code,
  cc.name AS category_name,
  cc.scope_type
FROM mst_component c
LEFT JOIN mst_component_category cc ON cc.id = c.component_category_id
WHERE c.component_name IN (
  'SIMPLE SYRUP','INFUSED BLUE PEA TEA','WHIPPING CREAM','ESPRESSO BLEND','LEMON JUICE','COLD BREW',
  'ADU RAMU','BASE TEA','BASED LEMONGRASS','JELLY CHOCOLATE BASE','JELLY LYCHEE BASE','CREAM CHEESE',
  'BOLOGNESE','TERIYAKI SAUCE','BUMBU BAKAR MADU','BARBEQUE SAUCE','SAUCE BANGKOK','BUMBU BAKAR PARAPE',
  'BUMBU MERAH PASTE TERASI','HOT VOLCANO MAYO','SAMBAL KECAP','SAMBAL MATAH','KUAH IGA','MIX SEASONING POWDER',
  'SAMBAL REBUS IGA','BUMBU KUNING MARINASI AYAM','MIX FLOUR','KREMESAN','SKIN CREPES','FILL PISANG CARAMEL',
  'TONGSENG PASTE','TORI PAITAN RAMEN BROTH','CHICKEN HONEY GARLIC MARINATED','RED GINGER PICKLED','SARI SUSHI',
  'COLESLAW SALAD','NASI BASE GEDE','BASE MIE LEVEL','CUKO','THAI DRESSING','SALTED CARAMEL','HONEY MUSTARD',
  'BASE GEDE','GARLIC OIL','CHEESE SAUCE','KALIO PASTE','BUMBU IRENG MADURA','TAR-TAR DRESSING','HONEY SAUCE',
  'SAMBAL BAWANG GEPREK','SAMBAL DABU-DABU','DIMSUM FILL','ARABIAN MAGIC POWDER','SATE LILIT','MARINASI AYAM BAKAR',
  'MIX SAUCE FRIED RICE & NOODLES','ADONAN MENDOAN','KELO MRICO BROTH','SOYU RAMEN BROTH','MIE AYAM SAUCE','PANNACOTTA',
  'TING TING CRUMBLE','CREAM EGG FRANCH TOAST','ACAR PICKLED','TOMYUM PASTE','MARINASI SONGKEM','MINCED CHICKEN',
  'SESAME DRESSING','MARINASI BEBEK IRENG','SAMBAL IDJO','SOTO BETAWI','NUGET','MAGIC POWDER SHORT RIBS','CELURY',
  'POACHED EEG','INFUSE CINNAMON'
)
ORDER BY c.component_name;
