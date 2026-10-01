# Core Warehouse Opening Repost Report

Tanggal: 2026-06-01

## Ringkasan

- Repost opening gudang dari staging via model menghasilkan 54 opening snapshot dan 54 movement log untuk snapshot month `2026-06-01`.
- Dari repost ini, ada 8 profile katalog baru yang auto-created oleh `Purchase_model::ensureCatalogProfileFromOpeningIdentity()`.
- Total opening untuk 8 profile baru tersebut adalah `qty_buy = 729.0000`, `qty_content = 18973.0000`, `opening_total_value = 1362300.60`.

## Daftar Profile Baru

| Catalog ID | Profile Name | Unit Price Buy | Snapshot ID | Opening Qty Buy | Opening Qty Content | Opening Total Value |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| 1184 | KNOTPIC | 24400.20 | 113 | 8.0000 | 8.0000 | 195201.60 |
| 1185 | MIE URAI | 5083.00 | 115 | 6.0000 | 840.0000 | 30498.00 |
| 1186 | PAPER BOX TA L | 799.00 | 116 | 350.0000 | 350.0000 | 279650.00 |
| 1187 | PAPER BOX TA M | 495.00 | 117 | 325.0000 | 325.0000 | 160875.00 |
| 1188 | SIRUP LYCHEE | 25000.00 | 133 | 18.0000 | 11160.0000 | 450000.00 |
| 1189 | SIRUP STRAWBERRY | 25000.00 | 137 | 7.0000 | 4340.0000 | 175000.00 |
| 1190 | SODA PLAIN | 3146.00 | 139 | 6.0000 | 1500.0000 | 18876.00 |
| 1191 | THINWALL 35ML | 5800.00 | 147 | 9.0000 | 450.0000 | 52200.00 |

## Catatan Teknis

- File bridge scan [sql/2026-06-01b_bridge_core_inventory_warehouse_profile_candidates.sql](sql/2026-06-01b_bridge_core_inventory_warehouse_profile_candidates.sql) sudah diperbarui agar kandidat match mempertimbangkan `unit_price_buy` selain text identity.
- Bucket status lama tetap dipertahankan untuk kompatibilitas script lanjutan: `AUTO_MATCH_UNIQUE_TEXT`, `AMBIGUOUS_TEXT_DUPLICATE`, dan `REVIEW_IDENTITY_ONLY`.
- Dengan aturan baru, `AUTO_MATCH_UNIQUE_TEXT` hanya boleh terjadi bila text identity dan harga sama persis.