# Verifikasi UOM Beli Kanonik

Tanggal: 2026-06-08 17:35:51

Sumber keputusan: `docs/_UOM_BELI.MD`

## Ringkasan

| Status | Total | Arti |
| --- | ---: | --- |
| OK_STRONG | 182 | Pilihan sudah jadi satu-satunya buy UOM aktif di catalog. |
| OK_WITH_LEGACY_CLEANUP | 1 | Pilihan sudah didukung catalog aktif, tapi masih ada jejak UOM lain di tabel aktif. |
| REVIEW_MIXED_CATALOG | 1 | Pilihan ada di catalog aktif, tapi sibling buy UOM aktif lain masih hidup. |
| REVIEW_NO_CATALOG_MATCH | 4 | Pilihan belum cocok dengan buy UOM catalog aktif sekarang. |
| REVIEW_NOT_FOUND | 0 | Nama material belum ketemu exact ke mst_material. |

## Detail

| Material | Pilihan | Material ID | Catalog Aktif | Item Aktif | Jejak Tabel Aktif | Exact Mismatch | Status | Catatan |
| --- | --- | ---: | --- | --- | --- | ---: | --- | --- |
| BOTOL PLASTIK | PCS | 238 | - | 1:PCS | 1:PCS | 0 | REVIEW_NO_CATALOG_MATCH | Belum ada catalog aktif yang bisa dijadikan pegangan. |
| DRIED LIME | PACK | 64 | - | 3:PACK | 3:PACK | 0 | REVIEW_NO_CATALOG_MATCH | Belum ada catalog aktif yang bisa dijadikan pegangan. |
| LEMON | PACK | 132 | - | 3:PACK | 3:PACK | 0 | REVIEW_NO_CATALOG_MATCH | Belum ada catalog aktif yang bisa dijadikan pegangan. |
| WEDANG UWUH | PCS | 214 | - | 1:PCS | 1:PCS | 0 | REVIEW_NO_CATALOG_MATCH | Belum ada catalog aktif yang bisa dijadikan pegangan. |
| AIR MINERAL BOTOL | DUS | 4 | 6:BTL, 30:DUS | 6:BTL | 6:BTL, 30:DUS | 0 | REVIEW_MIXED_CATALOG | Pilihan ada di catalog aktif, tetapi sibling buy UOM aktif lain masih hidup. |
| AIR MINERAL GALON | GLN | 2 | 35:GLN | 9:ML | 9:ML, 35:GLN | 0 | OK_WITH_LEGACY_CLEANUP | Catalog aktif sudah tunggal, tetapi row legacy di tabel aktif masih perlu disapu. |
| ABON SAPI | PACK | 1 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| AIR | JRG | 3 | 64:JRG | 64:JRG | 64:JRG | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| AIR ISI ULANG GALON | GLN | 264 | 35:GLN | 35:GLN | 35:GLN | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ALMOND | PACK | 5 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| AYAM DADA FILLET | PACK | 7 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| AYAM PAHA FILLET | PACK | 8 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BAKING POWDER | PACK | 10 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BAWANG BOMBAY | PACK | 11 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BAWANG MERAH | PACK | 12 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BAWANG MERAH GORENG | PACK | 13 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BAWANG PUTIH | PACK | 14 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BAWANG PUTIH BUBUK | PCS | 15 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BAWANG PUTIH GORENG | PACK | 16 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BBQ KNORR | PCS | 17 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BEEF PATTIES | PACK | 19 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BEEF SHORTPLATE | PACK | 20 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BERAS | PACK | 21 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BISKUAT | PACK | 222 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BLACKPEPPER | PACK | 23 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BOWL TA | PCS | 24 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BREAD CRUMB | PACK | 25 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BROWN SUGAR | PACK | 226 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BUNCIS | PACK | 26 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| BUNGA LAWANG | PACK | 27 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CABAI BUBUK | PACK | 30 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CABAI HIJAU KERITING | PACK | 31 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CABAI HIJAU TEROPONG | PACK | 32 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CABAI KERING | PACK | 33 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CABAI MERAH KERITING | PACK | 34 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CABAI RAWIT HIJAU | PACK | 36 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CABAI RAWIT MERAH | PACK | 37 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CARAMEL CRUMB | PCS | 42 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CARAMEL SAUCE | BTL | 43 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CARNATION EVAPORASI | CAN | 44 | 7:CAN | 7:CAN | 7:CAN | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CHIKUWA | PACK | 46 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CHOCO BALL | PACK | 47 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CHOCOLATE PASTE | PACK | 51 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CHOCOLATE POWDER | PACK | 48 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CIRENG | PACK | 49 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CITRUN | PACK | 50 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| COOKING CREAM | PCS | 53 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CUKA | PCS | 54 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| CUMI | PACK | 55 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| DAUN BAWANG | PACK | 58 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| DAUN JERUK | PACK | 59 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| DAUN SALAM | PACK | 61 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| DORI FILLET | PACK | 62 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| DRIED LEMON | PACK | 63 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| DRY BAY LEAF | PACK | 65 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| EMPING | PACK | 66 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ESPRESSO ARABIKA | PACK | 229 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| FRENCH FRIES | PACK | 68 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| FRESH MILK | DUS | 69 | 30:DUS | 30:DUS | 30:DUS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| GARAM | PCS | 70 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| GREEN TEA POWDER | PACK | 73 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| GROUND BEEF | PACK | 74 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| GULA JAWA | PACK | 75 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| GULA PASIR BAR | PACK | 78 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| GULA PASIR KITCHEN | PACK | 76 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| HOUSE BLEND 70 | PACK | 223 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ICE CREAM COKLAT | PACK | 82 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ICE CREAM VANILLA | PACK | 83 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ICE CUBE | PACK | 84 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| IGA SAPI | PACK | 86 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JAHE EMPRIT | PACK | 90 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JAHE GAJAH | PACK | 91 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JAMUR ENOKI | PACK | 92 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JAMUR KUPING | PACK | 93 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JELLY CHOCOLATE | PCS | 94 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JELLY LYCHEE | PCS | 95 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JERUK LIMAU | PACK | 96 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JERUK NIPIS | PACK | 97 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| JINTEN | PACK | 98 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KACANG TANAH | PACK | 99 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KALDU AYAM | PCS | 100 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KALDU SAPI | PCS | 101 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KANI STICK | PACK | 102 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KAPULAGA | PACK | 103 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KECAMBAH | PACK | 106 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KECAP ASIN | BTL | 107 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KECAP MANIS | BTL | 108 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KEJU CHEDDAR | PCS | 109 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KEJU MOZARELLA | PCS | 110 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KEJU SPREADY | PCS | 111 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KEMANGI | PACK | 112 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KEMENYAN BALI | PACK | 113 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KEMIRI | PACK | 114 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KENCUR | PACK | 115 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KENTANG | PACK | 116 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KERUPUK BAWANG | PACK | 117 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KERUPUK UDANG | PACK | 242 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KETUMBAR | PACK | 118 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KLUWEK | PACK | 119 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KOL PUTIH | PACK | 120 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KOL UNGU | PACK | 121 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KOPI LELET | PACK | 122 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KULIT AYAM | PACK | 123 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KULIT DIMSUM | PACK | 124 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KULIT LUMPIA | PACK | 125 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KULIT PANGSIT | PACK | 126 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KUNYIT | PACK | 127 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| KUNYIT BUBUK | PACK | 128 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| LADA PUTIH | PACK | 129 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| LAMB CHOP | PACK | 131 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| LENGKUAS | PACK | 133 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| LYCHEE FRUIT | CAN | 134 | 7:CAN | 7:CAN | 7:CAN | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MADU | PACK | 135 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MAKARONI | PCS | 136 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MARGARIN | PACK | 137 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MARSHMELLOW | PACK | 138 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MAYONAISE | PACK | 140 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MIE URAI | PACK | 142 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MINYAK GORENG | PCS | 143 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MINYAK WIJEN | PCS | 145 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| MSG | PCS | 146 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| NORI | PACK | 148 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| OATMILK | PCS | 149 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ORANGE CHEESE | PACK | 150 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| OREO CRUMB | PACK | 152 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| PAKCOY | PACK | 153 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| PALA | PACK | 154 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| PAPER FILTER KALITA WAVE | PACK | 155 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| PAPER FILTER V60 | PACK | 156 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| PEWARNA MAKANAN HIJAU | PCS | 244 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| PISANG BAR | PACK | 160 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| PISANG KITCHEN | SSR | 241 | 25:SSR | 25:SSR | 25:SSR | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| POPCORN | PACK | 225 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| RED VELVET POWDER | PACK | 161 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ROTI BUN BURGER | PACK | 162 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| ROTI TAWAR | PACK | 163 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SARI LEMON | BTL | 243 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SAUS BANGKOK | PCS | 167 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SAUS SAMBAL | PCS | 168 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SAUS TIRAM | PCS | 169 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SAUS TOMAT | PCS | 166 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SELADA | PACK | 170 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SEMANGKA | PACK | 171 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SEREH | PACK | 172 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SINGLE ORIGIN | PACK | 173 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRLOIN MELTIQUE | PACK | 174 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP AREN | BTL | 175 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP BUTTERSCOTCH | BTL | 176 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP CARAMEL | BTL | 177 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP HAZELNUT | BTL | 178 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP KAWIS | BTL | 179 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP LYCHEE | BTL | 180 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP MOJITO | BTL | 181 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP ROSE | BTL | 183 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP RUM | BTL | 184 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP STRAWBERRY | BTL | 185 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SIRUP VANILLA | BTL | 186 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SKM PUTIH | CAN | 188 | 7:CAN | 7:CAN | 7:CAN | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SMOKED BEEF | PACK | 189 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SMOKED FISH PE | PACK | 190 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SO ARGOPURO LYCHEE SORBET | PACK | 228 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SO WANOJA FP | PACK | 230 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SODA PLAIN | BTL | 191 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SOSIS | PACK | 219 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| SPAGHETTI | PCS | 192 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| STRAWBERRY | PACK | 193 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| STRAWBERRY SAUCE | UNIT | 194 | 2:UNIT | 2:UNIT | 2:UNIT | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TAHU | PACK | 196 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TAHU PONG | PACK | 197 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TEH DANDANG | PACK | 198 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TEH GOPEK | PACK | 199 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TELUR | KG | 200 | 10:KG | 10:KG | 10:KG | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TEMPE | PCS | 201 | 1:PCS | 1:PCS | 1:PCS | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TEPUNG BERAS | PACK | 234 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TEPUNG MAIZENA | PACK | 202 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TEPUNG TAPIOKA | PACK | 203 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TEPUNG TERIGU | PACK | 204 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TERASI | PACK | 205 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TIMUN | PACK | 206 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TIRAMITSU POWDER | PACK | 207 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TOBIKO | PACK | 208 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TOMAT | PACK | 209 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| TUSUK SATE | PACK | 212 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| UDANG | PACK | 213 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| WHIPPED CREAM | PACK | 215 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| WIJEN | PACK | 216 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| WORTEL | PACK | 217 | 3:PACK | 3:PACK | 3:PACK | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |
| YELLOW MUSTARD | BTL | 218 | 6:BTL | 6:BTL | 6:BTL | 0 | OK_STRONG | Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain. |

## Prioritas Praktis

1. `REVIEW_NO_CATALOG_MATCH`: jangan direpair massal dulu, karena pilihan belum hidup di catalog aktif.
2. `REVIEW_MIXED_CATALOG`: pilihan boleh jadi benar, tapi sibling UOM aktif lain perlu diputuskan nasibnya.
3. `OK_WITH_LEGACY_CLEANUP`: keputusan sudah masuk akal, tinggal sapu row legacy di movement/monthly/opening/lot.
4. `OK_STRONG`: aman dijadikan dasar repair massal.

