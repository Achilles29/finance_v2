# Item-Centric Hotspot Matrix

Tanggal: 2026-06-07
Tujuan: matrix teknis file-to-problem untuk mempercepat eksekusi lanjutan

## Cara Baca

Kolom:

1. `Area`
2. `File`
3. `Masalah Utama`
4. `Risiko`
5. `Arah Perbaikan`
6. `Prioritas`

## Matrix

| Area | File | Masalah Utama | Risiko | Arah Perbaikan | Prioritas |
|---|---|---|---|---|---|
| Purchase | [Purchase_model.php](/c:/xampp/htdocs/finance/application/models/Purchase_model.php) | Masih banyak helper/audit/compare yang memakai `stock_domain`, `line_kind`, dan pola material-centric | False positive mismatch, drift audit, snapshot compare pecah | Cabut decision source legacy, samakan identity compare ke item-centric, rapikan audit POS/material | Sangat Tinggi |
| Inventory Tooling | [Inventory_tools.php](/c:/xampp/htdocs/finance/application/controllers/Inventory_tools.php) | Masih ada payload/tool/smoke yang mengisi `stock_domain` atau `line_kind='MATERIAL'` | Tool operasional bisa menghidupkan pola lama lagi | Normalisasi semua tool ke item-centric, buang input legacy dari contoh dan repair tool | Sangat Tinggi |
| POS Posting | [PosOrderStockService.php](/c:/xampp/htdocs/finance/application/libraries/PosOrderStockService.php) | Retry/posting material POS sensitif terhadap exact identity, FIFO issue, dan guard ledger | Job gagal POS, saldo negatif palsu, commit gagal | Pastikan semua jalur posting konsisten item-centric dan exact profile sync | Sangat Tinggi |
| Ledger | [InventoryLedger.php](/c:/xampp/htdocs/finance/application/libraries/InventoryLedger.php) | Monthly identity dan negative balance guard masih jadi titik bentur utama | Posting gagal walau source stock cukup | Jaga identity key item-centric, kurangi ketergantungan ke legacy storage domain | Sangat Tinggi |
| Procurement | [Procurement_model.php](/c:/xampp/htdocs/finance/application/models/Procurement_model.php) | Sebagian search/review/fulfillment masih punya compat layer lama | Lane request bisa kembali mendorong user ke pola lama | Pertahankan item-centric write, rapikan helper display/search yang masih hybrid | Tinggi |
| Recipe/Relation | [Master_relation.php](/c:/xampp/htdocs/finance/application/controllers/Master_relation.php) | Masih ada pembacaan yang mem-filter `stock_domain='ITEM'` | Read model recipe/costing bisa sempit atau bias legacy | Ubah pembacaan ke item-centric + `material_id` marker | Tinggi |
| POS Live | [PosAvailabilityRebuildService.php](/c:/xampp/htdocs/finance/application/libraries/PosAvailabilityRebuildService.php) | Live availability dan HPP harus tetap konsisten dengan monthly weighted aggregate | Drift HPP live vs commit audit | Pertahankan satu formula cost source | Tinggi |
| POS Model | [Pos_model.php](/c:/xampp/htdocs/finance/application/models/Pos_model.php) | Resolver cost material/component dulu beda rumus dengan live | HPP commit vs live drift | Pastikan semua resolver cost tetap weighted aggregate | Tinggi |
| Component | [Production_model.php](/c:/xampp/htdocs/finance/application/models/Production_model.php) | Masih ada compat path lama walau daily/stock balance aktif sudah diputus | Potensi confusion saat rebuild component | Jaga monthly/log sebagai source tunggal | Menengah |
| Component Writer | [ComponentStockWriter.php](/c:/xampp/htdocs/finance/application/libraries/ComponentStockWriter.php) | Perlu tetap sinkron dengan konsep item-centric untuk material bridge | Component consume bisa drift dari lane bahan baku | Pastikan material marker tidak jadi identity snapshot | Menengah |
| FIFO Material | [MaterialFifoManager.php](/c:/xampp/htdocs/finance/application/libraries/MaterialFifoManager.php) | Source truth exact profile ada di sini, tapi masih harus dipanggil dengan identity yang tepat | Monthly stale, retry gagal, adjustment gagal | Pertahankan FIFO sebagai truth, snapshot hanya sinkronisasi | Menengah |
| POS Runtime | [PosRuntimeJobService.php](/c:/xampp/htdocs/finance/application/libraries/PosRuntimeJobService.php) | Worker/retry orchestration perlu diamati pasca patch | Job retry bisa tetap memproses payload lama | Fokus verifikasi setelah patch posting | Menengah |
| UI Audit POS | [stock_commit_audit_index.php](/c:/xampp/htdocs/finance/application/views/pos/stock_commit_audit_index.php) | UI harus jujur terhadap makna mismatch | User bisa salah diagnosis | Pastikan label audit mengikuti item-centric | Menengah |

## Cluster Masalah

### Cluster 1. Legacy Decision Source

Ciri:

1. code masih memutuskan berdasarkan `line_kind`
2. code masih memutuskan berdasarkan `stock_domain`
3. `material_id` langsung dianggap berarti lane `MATERIAL`

File utama:

1. [Purchase_model.php](/c:/xampp/htdocs/finance/application/models/Purchase_model.php)
2. [Inventory_tools.php](/c:/xampp/htdocs/finance/application/controllers/Inventory_tools.php)
3. [Master_relation.php](/c:/xampp/htdocs/finance/application/controllers/Master_relation.php)

### Cluster 2. POS Failure / Retry

Ciri:

1. job gagal POS
2. pesan saldo bulanan negatif
3. commit audit tidak sinkron dengan stock live

File utama:

1. [PosOrderStockService.php](/c:/xampp/htdocs/finance/application/libraries/PosOrderStockService.php)
2. [InventoryLedger.php](/c:/xampp/htdocs/finance/application/libraries/InventoryLedger.php)
3. [Pos_model.php](/c:/xampp/htdocs/finance/application/models/Pos_model.php)
4. [PosAvailabilityRebuildService.php](/c:/xampp/htdocs/finance/application/libraries/PosAvailabilityRebuildService.php)

### Cluster 3. Legacy Audit / False Positive

Ciri:

1. mismatch muncul besar tetapi source movement sehat
2. compare membaca source yang sudah tidak lagi aktif
3. audit masih menghukum profile item-centric sehat

File utama:

1. [Purchase_model.php](/c:/xampp/htdocs/finance/application/models/Purchase_model.php)
2. [stock_commit_audit_index.php](/c:/xampp/htdocs/finance/application/views/pos/stock_commit_audit_index.php)

### Cluster 4. Legacy Table Deprecation

Ciri:

1. runtime aktif sudah bersih
2. DB object dependency masih harus diverifikasi
3. rename/drop legacy belum dilakukan

File/dokumen utama:

1. [2026-06-07b_legacy_daily_rollup_stock_balance_audit.md](/c:/xampp/htdocs/finance/docs/2026-06-07b_legacy_daily_rollup_stock_balance_audit.md)
2. [2026-06-07c_audit_legacy_table_db_dependencies.sql](/c:/xampp/htdocs/finance/sql/2026-06-07c_audit_legacy_table_db_dependencies.sql)
3. [2026-06-07d_deprecate_legacy_daily_rollup_stock_balance_tables.sql](/c:/xampp/htdocs/finance/sql/2026-06-07d_deprecate_legacy_daily_rollup_stock_balance_tables.sql)

## Checklist Lanjut Kerja

### Saat fokus ke POS

1. cek `job gagal`
2. cek `mismatch bahan baku`
3. cek 1 job gagal terakhir
4. pastikan HPP live dan commit pakai rumus yang sama

### Saat fokus ke audit stok

1. cek compare source apakah aktif atau legacy
2. cek identity key apakah masih terpecah oleh `stock_domain`
3. cek apakah audit menghitung drift nyata atau cuma tagging lama

### Saat fokus ke procurement/purchase

1. cek write payload canonical `item_id`
2. cek `material_id` hanya marker
3. cek `usage_purpose` sebagai behavior source
4. cek tidak ada create snapshot baru ke lane `MATERIAL`

## Outcome yang Diinginkan dari Matrix Ini

1. developer berikutnya tahu harus masuk dari file mana
2. setiap mismatch bisa dipetakan ke cluster masalah yang benar
3. arah perbaikan tidak kembali ke pola dual identity lama

