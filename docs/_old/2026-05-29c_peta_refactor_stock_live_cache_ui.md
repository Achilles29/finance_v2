# Peta Refactor: Stock Live Cache + Halaman DB vs Kalkulasi Live
**Tanggal:** 2026-05-29  
**Status:** Working map sebelum implementasi service rebuild cache dan UI audit stock live

## 1. Tujuan
Kita butuh dua hal yang berjalan bareng:
1. `Database cache` stok/availability yang cepat dibaca POS.
2. `Kalkulasi live` dari transaksi dan master resep agar bisa dibandingkan dengan cache saat ada miss.

Tujuan UI:
1. Menampilkan angka `DB cache` berdampingan dengan `hasil hitung live`.
2. Menunjukkan selisih, bottleneck, dan event terakhir yang membuat angka berubah.
3. Mempercepat audit saat ada keluhan:
   - produk tampil available padahal bahan habis
   - produk tampil out padahal seharusnya masih bisa jual
   - void/refund tidak terasa mengembalikan stok

## 2. Kondisi Saat Ini
Fondasi yang **sudah ada**:
1. Tabel cache dan override sudah ada di SQL:
   - `pos_product_availability_cache`
   - `pos_product_availability_override`
   - lihat [2026-05-28a3_pos_payment_bundle_availability_foundation.sql](/c:/xampp/htdocs/finance/sql/2026-05-28a3_pos_payment_bundle_availability_foundation.sql)
2. POS katalog sudah membaca cache bila tersedia:
   - [Pos_model.php](/c:/xampp/htdocs/finance/application/models/Pos_model.php)
3. Order confirm POS sudah memotong stok nyata:
   - [Pos.php](/c:/xampp/htdocs/finance/application/controllers/Pos.php)
   - [PosOrderStockService.php](/c:/xampp/htdocs/finance/application/libraries/PosOrderStockService.php)
4. Snapshot commit/reversal POS sudah ada:
   - [PosStockCommitService.php](/c:/xampp/htdocs/finance/application/libraries/PosStockCommitService.php)
5. Void/refund POS sudah memanggil reversal stok:
   - [Pos_model.php](/c:/xampp/htdocs/finance/application/models/Pos_model.php)

Yang **belum ada / belum final**:
1. Service tunggal `PosAvailabilityRebuildService`.
2. Worker/event log rebuild cache.
3. Halaman audit `DB cache vs kalkulasi live`.
4. Mekanisme konsisten untuk menandai affected products dari semua transaksi stok.
5. Rebuild otomatis dari semua domain non-POS yang menulis ke stok.

## 3. Masalah Arsitektur yang Harus Diselesaikan
### 3.1 Cache sudah dibaca, tapi belum punya “source of confidence”
Saat ini cache ada, tetapi:
1. belum ada jejak event rebuild yang rapi
2. belum ada halaman yang membuktikan angka cache vs angka live
3. bila ada mismatch, user operasional tidak punya alat audit cepat

### 3.2 Semua writer stok belum mengirim “affected product rebuild”
Saat ini stok berubah dari banyak jalur:
1. purchase receipt
2. store request fulfillment
3. stock adjustment
4. production batch / opening / adjustment
5. POS confirm
6. POS void/refund

Tetapi belum semua jalur punya kontrak yang sama:
1. stok diposting
2. cari product terdampak
3. rebuild cache per outlet
4. simpan log hasil rebuild

### 3.3 Void/refund berisiko bikin cache miss
Ini paling penting.

Saat void/refund:
1. stok bisa `return_to_stock`
2. atau hanya `adjustment_only`
3. atau `no_return`

Kalau cache tidak dibedakan berdasarkan policy itu, hasilnya bisa keliru:
1. stok fisik sudah balik, cache belum naik
2. stok tidak balik, tapi cache seolah naik

## 4. Domain Transaksi yang Mempengaruhi Stock Live
### 4.1 Domain POS
Event yang harus memicu rebuild:
1. `order confirm / stock commit`
2. `void` dengan `return_to_stock = 1`
3. `refund` dengan `return_to_stock = 1`
4. `void/refund` dengan `adjustment_only`
5. perubahan line extra yang punya source material/component

File saat ini:
1. [Pos.php](/c:/xampp/htdocs/finance/application/controllers/Pos.php)
2. [Pos_model.php](/c:/xampp/htdocs/finance/application/models/Pos_model.php)
3. [PosOrderStockService.php](/c:/xampp/htdocs/finance/application/libraries/PosOrderStockService.php)
4. [PosStockCommitService.php](/c:/xampp/htdocs/finance/application/libraries/PosStockCommitService.php)

### 4.2 Domain Purchase / Warehouse
Event yang harus memicu rebuild:
1. purchase receipt posted
2. purchase reversal / void receipt
3. stock adjustment warehouse/division
4. opening stock material

File yang kena:
1. [Purchase_model.php](/c:/xampp/htdocs/finance/application/models/Purchase_model.php)
2. [InventoryLedger.php](/c:/xampp/htdocs/finance/application/libraries/InventoryLedger.php)
3. [MaterialFifoManager.php](/c:/xampp/htdocs/finance/application/libraries/MaterialFifoManager.php)

### 4.3 Domain Procurement / Store Request
Event yang harus memicu rebuild:
1. fulfillment posted
2. reverse/void fulfillment

File yang kena:
1. [Procurement_model.php](/c:/xampp/htdocs/finance/application/models/Procurement_model.php)
2. [InventoryLedger.php](/c:/xampp/htdocs/finance/application/libraries/InventoryLedger.php)
3. [MaterialFifoManager.php](/c:/xampp/htdocs/finance/application/libraries/MaterialFifoManager.php)

### 4.4 Domain Production / Component
Event yang harus memicu rebuild:
1. component opening
2. component adjustment
3. component batch posted
4. reverse/void component output or issue

File yang kena:
1. [Production_model.php](/c:/xampp/htdocs/finance/application/models/Production_model.php)
2. [ComponentStockWriter.php](/c:/xampp/htdocs/finance/application/libraries/ComponentStockWriter.php)
3. [ComponentLotManager.php](/c:/xampp/htdocs/finance/application/libraries/ComponentLotManager.php)
4. [InventoryLedger.php](/c:/xampp/htdocs/finance/application/libraries/InventoryLedger.php)

### 4.5 Domain Master Recipe
Event yang harus memicu rebuild:
1. perubahan `mst_product_recipe`
2. perubahan `mst_component_formula`
3. perubahan role bahan `MAIN/SUPPORT/COMPLEMENT/OPTIONAL`
4. perubahan `pos_product_availability_override`
5. perubahan extra yang mengubah source material/component

File yang kena:
1. [Master_relation.php](/c:/xampp/htdocs/finance/application/controllers/Master_relation.php)
2. [Master_model.php](/c:/xampp/htdocs/finance/application/models/Master_model.php)
3. [Master.php](/c:/xampp/htdocs/finance/application/controllers/Master.php)

## 5. Database yang Perlu Ditambahkan
### 5.1 Wajib: log rebuild cache
Tabel baru yang disarankan:
1. `pos_product_availability_rebuild_log`

Fungsi:
1. menyimpan kapan rebuild dijalankan
2. sumber event apa yang memicu
3. produk/outlet mana yang terdampak
4. angka cache sebelum dan sesudah rebuild
5. hasil hitung live saat itu
6. status `MATCH / MISMATCH / ERROR`

Kolom minimal:
1. `id`
2. `event_source`
3. `event_table`
4. `event_id`
5. `outlet_id`
6. `product_id`
7. `cache_status_before`
8. `cache_qty_before`
9. `cache_status_after`
10. `cache_qty_after`
11. `live_status`
12. `live_qty`
13. `live_hpp`
14. `mismatch_flag`
15. `mismatch_note`
16. `rebuilt_at`
17. `actor_employee_id`

### 5.2 Wajib: log probe UI
Tabel baru yang disarankan:
1. `pos_product_availability_probe`
2. `pos_product_availability_probe_line`

Fungsi:
1. menyimpan hasil klik “cek live sekarang” dari UI
2. supaya kita bisa audit kapan user menemukan miss
3. menyimpan detail bottleneck material/component

`probe` header:
1. `id`
2. `outlet_id`
3. `product_id`
4. `cache_status`
5. `cache_qty`
6. `live_status`
7. `live_qty`
8. `mismatch_flag`
9. `trigger_context`
10. `created_by`
11. `created_at`

`probe_line` detail:
1. `probe_id`
2. `source_kind`
3. `source_id`
4. `source_name_snapshot`
5. `source_role`
6. `required_qty`
7. `available_qty_live`
8. `short_qty`
9. `is_bottleneck`
10. `cost_source`

### 5.3 Opsional tapi bagus: queue event rebuild
Kalau nanti event makin ramai, siapkan:
1. `pos_product_availability_event_queue`

Fungsi:
1. writer stok cukup enqueue event
2. service/worker bisa proses async
3. UI audit tetap punya jejak event mentah

## 6. Service Baru yang Perlu Lahir
### 6.1 `PosAvailabilityRebuildService`
Ini service inti yang saat ini belum ada.

Tugas:
1. cari affected products dari event
2. hitung availability live dari recipe + stock nyata + override
3. update `pos_product_availability_cache`
4. tulis `rebuild_log`

Method minimum:
1. `rebuild_product(outletId, productId, context)`
2. `rebuild_products(outletId, productIds, context)`
3. `mark_dirty(outletId, productIds, context)`
4. `resolve_live_availability(outletId, productId)`
5. `resolve_affected_products_from_material(materialId)`
6. `resolve_affected_products_from_component(componentId)`
7. `probe_compare(outletId, productId, context)`

### 6.2 `PosAvailabilityDependencyService`
Opsional, tapi akan mengurangi query spaghetti.

Tugas:
1. material -> produk terdampak
2. component -> produk terdampak
3. component child -> parent -> produk final

Kalau belum ingin service baru, method ini bisa hidup dulu di `Pos_model`.

## 7. Halaman UI yang Dibutuhkan
### 7.1 Halaman utama audit
URL yang disarankan:
1. `/pos/stock-live`

Layout yang disarankan:
1. filter outlet
2. search produk
3. table per produk

Kolom utama:
1. produk
2. status DB cache
3. qty DB cache
4. status live calc
5. qty live calc
6. selisih
7. bottleneck
8. override
9. computed_at cache
10. aksi:
   - `Probe Live`
   - `Rebuild`
   - `Detail`

### 7.2 Drawer / modal detail compare
Saat klik detail:
1. tampilkan recipe line
2. tampilkan source role
3. tampilkan available qty live
4. tandai bottleneck
5. tampilkan cache row saat ini
6. tampilkan event transaksi terakhir yang mungkin memengaruhi

### 7.3 Halaman log rebuild
URL yang disarankan:
1. `/pos/stock-live/logs`

Isi:
1. daftar rebuild terbaru
2. filter by event source
3. filter mismatch only
4. drilldown ke produk dan source dokumen

## 8. Titik Refactor yang Pasti Kena
### 8.1 Library writer stok
Semua writer stok perlu pola callback yang sama setelah posting:
1. kumpulkan affected material/component
2. resolve affected products
3. trigger rebuild cache

File yang pasti kena:
1. [InventoryLedger.php](/c:/xampp/htdocs/finance/application/libraries/InventoryLedger.php)
2. [PosOrderStockService.php](/c:/xampp/htdocs/finance/application/libraries/PosOrderStockService.php)
3. [ComponentStockWriter.php](/c:/xampp/htdocs/finance/application/libraries/ComponentStockWriter.php)
4. [Purchase_model.php](/c:/xampp/htdocs/finance/application/models/Purchase_model.php)
5. [Procurement_model.php](/c:/xampp/htdocs/finance/application/models/Procurement_model.php)
6. [Production_model.php](/c:/xampp/htdocs/finance/application/models/Production_model.php)

### 8.2 POS order/reversal
Perlu tambahan sesudah:
1. `order_draft_confirm`
2. `save_order_void`
3. `save_order_refund`

Bukan cuma posting stok, tapi juga:
1. rebuild cache untuk produk terdampak
2. simpan log rebuild
3. kalau mismatch, simpan warning

### 8.3 Master recipe/formula
Saat save recipe/formula:
1. tandai produk affected `is_dirty = 1`
2. atau rebuild langsung jika scale kecil

### 8.4 Catalog POS
Saat baca katalog:
1. tetap baca cache untuk cepat
2. tambahkan indikator kalau row `is_dirty = 1`
3. opsional: tombol admin “cek live”

## 9. Void/Refund: Titik Risiko Khusus
Ini wajib diperlakukan khusus.

### 9.1 Void/refund yang return to stock
Harus:
1. posting reversal fisik
2. rebuild cache affected products
3. log event source = `POS_VOID_RETURN` atau `POS_REFUND_RETURN`

### 9.2 Void/refund adjustment only
Harus:
1. jangan menaikkan cache availability seolah stok balik
2. tetap log event source
3. bila adjustment posting terpisah, rebuild mengikuti output adjustment tersebut

### 9.3 Extra line
Jika extra punya source material/component:
1. extra ikut memengaruhi live calc
2. extra juga ikut reversal policy
3. probe detail harus menampilkan extra lines, bukan hanya produk utama

## 10. Kesimpulan Implementasi
### 10.1 Ini yang sudah siap
1. writer stok POS
2. snapshot commit/reversal
3. cache table foundation
4. POS catalog reader ke cache

### 10.2 Ini yang harus dibangun sebelum sinkronisasi penuh ke kasir
1. `PosAvailabilityRebuildService`
2. `rebuild_log` table
3. `probe` table
4. halaman `/pos/stock-live`
5. hook rebuild dari writer stok non-POS

### 10.3 Urutan paling aman
1. buat SQL tabel `rebuild_log` dan `probe`
2. buat `PosAvailabilityRebuildService`
3. sambungkan dulu ke event POS:
   - confirm
   - void
   - refund
4. buat halaman `/pos/stock-live`
5. baru sambungkan ke purchase/procurement/production

## 11. Keputusan yang Disarankan
1. `pos_product_availability_cache` tetap dipakai sebagai DB cepat untuk kasir.
2. `live calc` tidak dipakai untuk list kasir, tapi wajib tersedia di halaman audit.
3. `void/refund` harus ikut menjadi first-class event dalam rebuild, bukan edge case.
4. Rebuild awal fokus `affected products only`, bukan full rebuild.
5. UI audit harus menampilkan `DB vs live` berdampingan supaya miss langsung kelihatan.
