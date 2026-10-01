# Item-Centric Gap Map

Tanggal: 2026-06-06

## Tujuan

Dokumen ini merangkum posisi implementasi item-centric saat ini berdasarkan codebase aktual, lalu memetakan:

1. writer yang sudah mulai item-centric
2. reader yang masih hybrid
3. schema/SQL yang masih membawa dual identity lama
4. urutan cleanup paling aman

## Status Ringkas

Posisi saat ini:

1. `item_id` sudah mulai menjadi canonical identity pada write path baru
2. `material_id` masih dipertahankan sebagai bridge/tag/metadata
3. `line_kind` dan `stock_domain` sudah turun menjadi compatibility layer
4. lane `PO / receipt / SR / component batch / adjustment` sudah banyak disentuh
5. bottleneck utama sekarang bukan lagi keputusan desain, tetapi:
   - reader lama yang masih hybrid
   - schema lama yang masih memuat `stock_domain` dalam unique key
   - beberapa helper yang masih infer `ITEM/MATERIAL`

## A. Yang Sudah Relatif Sehat

### 1. Canonical identity resolver

File:

- `application/libraries/ItemIdentityResolver.php`

Status:

- sudah menjadi fondasi pemetaan `item_id`, `material_id`, `profile_key`
- sudah dipakai untuk membelokkan sebagian write path baru

### 2. Inventory ledger compatibility

File:

- `application/libraries/InventoryLedger.php`

Status:

- writer sudah menerima `item_id` dan `material_id`
- `stock_domain` sudah mulai diperlakukan sebagai legacy storage fallback
- masih ada infer/fallback lama, tapi fondasi item-centric sudah terpasang

### 3. Purchase / Procurement write path

File:

- `application/models/Purchase_model.php`
- `application/models/Procurement_model.php`

Status:

- PO line, receipt line, dan fulfilment line baru sudah mulai diarahkan ke canonical identity
- `material_id` tetap ikut disimpan
- nullable legacy enum sudah didukung

### 4. Component lane

File:

- `application/models/Production_model.php`
- `application/libraries/ComponentStockWriter.php`
- `application/libraries/MaterialFifoManager.php`

Status:

- component batch sudah memakai `item_id/material_id` pada posting material usage
- FIFO lot dan exact-profile sync sudah lebih sehat
- daily adjustment dan component adjustment sudah punya auto-resync/rebuild

## B. Reader Yang Masih Hybrid

Area ini adalah prioritas utama berikutnya.

### 1. Purchase model

File:

- `application/models/Purchase_model.php`

Gejala hybrid yang masih kuat:

1. masih banyak helper yang menurunkan `stock_domain`
   - `resolveLineStockDomain(...)`
   - `resolveLineMaterialIdForStock(...)`
   - `buildCanonicalStockWriteContext(...)`

2. masih ada banyak fallback:
   - kalau `material_id` ada maka `MATERIAL`
   - kalau tidak maka `ITEM`

3. catalog search dan catalog reconcile masih membawa `line_kind` sebagai field aktif

Contoh jejak:

- sekitar line `12233`, `12580`, `16911`
- `legacyLineKindForStorage(...)`
- `resolveLineStockDomain(...)`

Penilaian:

- file ini adalah reader/writer hybrid terbesar di purchase lane
- belum bisa disebut full item-centric selama helper infer ini masih dominan

### 2. Purchase controller

File:

- `application/controllers/Purchase.php`

Gejala hybrid:

1. beberapa endpoint filter masih menerima:
   - `stock_domain`
   - `line_kind`

2. beberapa import/opening helper masih membangun row dengan:
   - `stock_domain = MATERIAL/ITEM`

3. beberapa halaman item/material masih memakai infer lama untuk drilldown

Contoh jejak:

- sekitar line `1048`
- filter pada line `2240`, `2403`
- halaman histori/lookup material-item sekitar `2922+`

Penilaian:

- controller ini masih menjadi pintu masuk UI yang menghidupkan pola lama

### 3. Inventory tools

File:

- `application/controllers/Inventory_tools.php`

Gejala hybrid:

1. CLI smoke dan repair tools masih menulis/membaca:
   - `line_kind`
   - `stock_domain`

2. ada payload hardcoded seperti:
   - `stock_domain = MATERIAL`

3. beberapa helper profile masih menentukan line berdasarkan:
   - `line_kind`
   - `material_id > 0`

Contoh jejak:

- line `477`, `652`
- line `1640+`
- line `1782+`, `1805+`

Penilaian:

- tool operasional ini berbahaya kalau dibiarkan hybrid terlalu lama
- karena repair/CLI bisa memperkenalkan bentuk data lama lagi

### 4. Master relation / recipe reader

File:

- `application/controllers/Master_relation.php`

Gejala hybrid:

1. relasi recipe masih memakai konsep `line_type = MATERIAL/COMPONENT`
2. beberapa lookup cost/live stock masih fallback ke:
   - `s.stock_domain = ITEM`
   - lalu join `mst_item.material_id`

Contoh jejak:

- sekitar line `2144` sampai `2165`

Penilaian:

- ini bukan write path inventory utama
- tapi ini reader penting untuk recipe/live cost
- perlu dibersihkan agar monitoring dan stok live tidak membaca dengan lensa lama

## C. Schema / SQL Yang Masih Legacy

Area ini belum layak dihapus sekarang, tapi harus ditandai jelas.

### 1. Purchase schema

File:

- `sql/2026-05-03f_purchase_schema_foundation.sql`
- `sql/2026-05-03h_purchase_payment_receipt_foundation.sql`
- `sql/2026-05-14d_procurement_workbench_store_request.sql`

Status:

- `line_kind` masih didefinisikan sebagai enum aktif di schema awal

### 2. Inventory schema

File:

- `sql/2026-05-03j_inventory_minimal_v1_foundation.sql`
- `sql/2026-05-06c_inventory_monthly_opname_and_opening_flow.sql`
- `sql/2026-05-06f_inventory_opening_snapshot_split_tables.sql`
- `sql/2026-05-31b_inventory_monthly_unified_projection_foundation.sql`

Status:

- `stock_domain` masih menjadi bagian dari unique key dan identity lama

Implikasi:

1. walau aplikasi baru boleh menulis `NULL`
2. model storage masih belum benar-benar bebas dari dual identity

### 3. Compatibility migration yang sudah membantu

File:

- `sql/2026-06-04g_item_centric_nullable_legacy_enum_columns.sql`

Status:

- sudah menurunkan `line_kind` / `stock_domain` menjadi nullable compatibility columns
- ini langkah transisi yang benar
- tapi belum menghapus keterikatan schema lama

## D. Area Yang Masih Menyimpan Infer Lama

### 1. Component writer

File:

- `application/libraries/ComponentStockWriter.php`

Catatan:

- lane ini sudah banyak sehat
- tetapi masih ada snapshot/helper yang mengisi:
  - `stock_domain` fallback
  - `source_kind = MATERIAL/COMPONENT`

Ini masih wajar untuk saat ini karena:

1. `source_kind` di component batch memang masih dibutuhkan untuk membedakan input material vs input component
2. yang harus dipensiunkan adalah `stock_domain`/`line_kind` sebagai canonical inventory identity

Kesimpulan:

- component lane bukan lagi blocker desain
- blocker utamanya sekarang ada pada reader purchase/inventory/reporting

## E. Urutan Cleanup Yang Paling Aman

### Fase R1 - Reader cleanup

Prioritas:

1. `application/models/Purchase_model.php`
2. `application/controllers/Purchase.php`
3. `application/controllers/Inventory_tools.php`
4. `application/controllers/Master_relation.php`

Target:

1. hentikan infer `MATERIAL/ITEM` sebagai keputusan canonical read
2. biasakan reader membaca:
   - `item_id`
   - `material_id`
   - `profile_key`
   - `usage_purpose`
   - `destination_type`

### Fase R2 - Tooling cleanup

Prioritas:

1. smoke test CLI
2. repair tool CLI
3. import/opening helper

Target:

1. jangan ada tool baru yang “menghidupkan kembali” `stock_domain = MATERIAL`
2. semua tool repair baru harus menganggap:
   - `item_id` canonical
   - `material_id` bridge

### Fase R3 - Schema de-legacy

Prioritas:

1. evaluasi unique key yang masih memakai `stock_domain`
2. siapkan migration khusus storage identity baru

Target:

1. `stock_domain` keluar dari unique key canonical
2. `line_kind` keluar dari keputusan transaksi
3. kolom lama tetap boleh tinggal sementara untuk audit/read-only

## F. Rekomendasi Eksekusi Berikutnya

Kalau lanjut disiplin ke item-centric, urutan kerja terbaik sekarang:

1. bersihkan `Purchase_model` lebih dulu
   - karena ini titik paling besar yang masih hybrid

2. lanjut ke `Purchase.php`
   - supaya filter dan UI tidak memaksa pola lama

3. lanjut ke `Inventory_tools.php`
   - supaya tool CLI tidak menghasilkan data campuran

4. terakhir `Master_relation.php`
   - supaya live cost / recipe reader ikut seragam

## G. Kesimpulan

Item-centric saat ini:

1. sudah berhasil sebagai arah write path
2. sudah mulai stabil di lane component
3. belum selesai karena reader layer masih membaca dengan pola lama

Jadi status paling jujur:

- desain: sudah jelas
- write path inti: banyak yang sudah sehat
- reader dan schema: masih transisi

Blok kerja berikutnya yang paling penting adalah:

- `reader cleanup`

bukan lagi debat desain dasar.
