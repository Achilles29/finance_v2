# Desain Detail POS: Stock Commit + Availability Rebuild Flow
**Tanggal:** 2026-05-28  
**Status:** Draft operasional untuk implementasi service POS

## 1) Tujuan
Dokumen ini merinci kapan stok POS dikurangi, kapan availability produk dihitung ulang, dan bagaimana alur override bekerja agar:
1. POS tetap cepat dibuka di desktop maupun mobile.
2. Ketersediaan produk tetap near real-time.
3. Audit stok tetap jelas saat ada void, refund, dan perubahan proses produksi.

## 2) Prinsip Utama
1. Pengurangan stok POS tidak menunggu payment.
2. Pengurangan stok dilakukan saat order sudah masuk fase `stock commit`.
3. Daftar produk kasir membaca `pos_product_availability_cache`, bukan menghitung resep dari nol di setiap request.
4. Cache wajib di-update langsung setiap ada event yang mengubah stok/resep/override.
5. Override hanya boleh melampaui kekosongan bahan non-main/pelengkap, tidak boleh melampaui kekosongan bahan `MAIN`.

## 3) State Operasional Order POS
State yang disarankan pada level proses:
1. `DRAFT`
- Item masih di keranjang.
- Belum ada pengurangan stok.
- Belum ada print kitchen.

2. `CONFIRMED`
- Order sudah dikirim/simpan sebagai transaksi aktif.
- Sistem menjalankan validasi recipe dan `stock commit`.
- Availability produk lain yang terdampak ikut diperbarui.

3. `PROCESSED_PARTIAL`
- Sebagian line sudah diproses dapur/bar.
- Void/refund mulai butuh keputusan return-to-stock vs adjustment.

4. `PROCESSED_FULL`
- Semua line sudah diproses.
- Return bahan ke stok normal tidak lagi aman.

5. `PAID`
- Payment selesai.
- Tidak memicu pengurangan stok utama karena itu sudah terjadi di `CONFIRMED`.

6. `VOID` / `REFUND`
- Memicu reversal stok atau adjustment otomatis tergantung status proses line.

## 4) Momen Stock Commit
### 4.1 Trigger commit
Stock commit dijalankan saat:
1. Kasir men-submit order menjadi transaksi aktif.
2. Order dine-in/takeaway/delivery disimpan ke POS dan sudah dianggap pesanan nyata.
3. Bukan saat payment akhir.

### 4.2 Langkah stock commit
1. Ambil seluruh `pos_order_line` dan `pos_order_line_extra`.
2. Turunkan kebutuhan bahan dari:
- `mst_product_recipe`
- `mst_component_formula`
3. Resolusi kebutuhan per line:
- material langsung
- component bertingkat
- konversi UOM
4. Tandai tiap recipe line:
- `MAIN`
- `SUPPORT`
- `COMPLEMENT`
- `OPTIONAL`
5. Cek stok tersedia dari balance/lot aktif.
6. Hitung rencana konsumsi FIFO.
7. Jika ada bahan `MAIN` yang tidak cukup:
- line ditolak
- produk tidak boleh dioverride available
8. Jika yang kurang hanya bahan non-main:
- sistem bisa menandai `override_allowed = 1`
- kasir diberi tahu bahan kosong apa saja
9. Tulis movement/usage stok.
10. Simpan snapshot commit untuk audit line.
11. Tandai order:
- `stock_commit_status = COMMITTED`
- `stock_committed_at = NOW()`
12. Rebuild availability produk terdampak.

## 5) Informasi yang Harus Muncul di POS Saat Override
Saat produk masih bisa dijual dengan override, POS harus menampilkan:
1. bahan yang kosong
2. role bahan tersebut
3. alasan kenapa masih bisa dijual
4. konfirmasi kasir sebelum item masuk order

Contoh:
1. `NASI AYAM GORENG`
2. Bahan kosong:
- `TIMUN` → `COMPLEMENT`
3. Status:
- boleh override
4. Pesan:
- Produk utama tetap tersedia, tetapi pelengkap kosong.

Jika bahan kosong adalah `MAIN`, contoh `NASI` atau `AYAM`, maka:
1. produk ditandai `OUT`
2. tidak ada tombol override available

## 6) Availability Cache yang Wajib Disimpan
Minimal kolom/konsep di `pos_product_availability_cache`:
1. `availability_status`
2. `source_mode`
3. `estimated_available_qty`
4. `bottleneck_kind`
5. `bottleneck_material_id`
6. `bottleneck_component_id`
7. `bottleneck_name_snapshot`
8. `main_missing_count`
9. `optional_missing_count`
10. `override_allowed`
11. `hpp_live_snapshot`
12. `stock_reference_at`
13. `last_commit_event`
14. `computed_at`

## 7) Event yang Wajib Memicu Rebuild Availability
### 7.1 Event stok bahan baku
1. purchase receipt posted
2. store request fulfillment posted
3. stock adjustment material
4. stock opening material
5. material void/reversal

### 7.2 Event stok component
1. component opening posted
2. component adjustment posted
3. component batch posted
4. component void/reversal

### 7.3 Event POS
1. order `stock commit`
2. void dengan `return_to_stock = 1`
3. refund dengan `return_to_stock = 1`
4. reversal transaksi POS yang mengembalikan stok

### 7.4 Event master/resep
1. perubahan `mst_product_recipe`
2. perubahan `mst_component_formula`
3. perubahan `pos_product_availability_override`
4. perubahan flag/role recipe line yang memengaruhi `MAIN` vs pelengkap

## 8) Strategi Rebuild Availability
### 8.1 Jangan rebuild semua produk
Rebuild massal hanya untuk maintenance. Dalam request operasional normal:
1. cari produk yang terdampak
2. rebuild produk itu saja

### 8.2 Dependency map
Perlu ada lookup dependency:
1. material -> produk mana yang memakai material itu
2. component -> produk mana yang memakai component itu
3. component -> component parent mana yang memakai component itu

Cara aman fase awal:
1. query `mst_product_recipe`
2. query `mst_component_formula`
3. build affected product list
4. rebuild cache per product

### 8.3 Prioritas rebuild
Urutan aman:
1. rebuild component/child dependency dulu bila perlu
2. rebuild product final
3. update cache row per outlet

## 9) FIFO dan HPP Live
### 9.1 HPP live saat stock commit
Saat commit konsumsi:
1. bahan `MATERIAL` memakai layer FIFO aktif
2. component memakai biaya live component yang tersedia
3. jika stok fisik nol tapi masih diizinkan override non-main, cost line fallback sesuai kebijakan

### 9.2 Fallback cost
Urutan fallback yang disarankan:
1. FIFO actual layer aktif
2. last live cost material/component
3. standard cost master

Catatan:
1. Fallback cost hanya untuk kalkulasi, bukan pembenaran stok fiktif.
2. Jika bahan `MAIN` kosong, fallback cost tidak membuat produk otomatis boleh dijual.

## 10) Void dan Refund terhadap Stok
### 10.1 Pertanyaan wajib di modal
Saat void/refund line:
1. apakah produk sudah diproses?
2. jika belum diproses, apakah bahan dikembalikan ke stok?

### 10.2 Jika belum diproses
1. user boleh checklist `return_to_stock`
2. sistem reversal movement sesuai snapshot stock commit
3. availability cache di-rebuild untuk produk terdampak

### 10.3 Jika sudah diproses
1. return normal ke stok tidak boleh diasumsikan aman
2. sistem buat adjustment otomatis/disposition:
- waste
- spoil
- manual review
3. line ditandai processed, supaya audit jelas

### 10.4 Extra/modifier
1. extra yang belum diproses bisa direversal normal
2. extra yang sudah diproses ikut aturan adjustment

## 11) Snapshot yang Sebaiknya Disimpan Saat Commit
Walaupun tabel phase awal belum menampung semua, service POS sebaiknya menyiapkan snapshot:
1. bahan apa saja yang dikonsumsi
2. qty per bahan
3. UOM sumber
4. cost source:
- FIFO
- last live
- fallback standard
5. line mana yang `MAIN`
6. line mana yang dioverride

Jika perlu hardening phase 2, kita bisa tambah tabel:
1. `pos_stock_commit`
2. `pos_stock_commit_line`

Supaya reversal void/refund tidak perlu menghitung ulang dari nol.

## 11.1 Hardening yang Sudah Disiapkan
Phase hardening sekarang memakai dua tabel:
1. `pos_stock_commit`
2. `pos_stock_commit_line`

Tujuannya:
1. menyimpan snapshot konsumsi stok saat order masuk fase commit
2. menyimpan source bahan/component per line order
3. menyimpan role source (`MAIN`, `SUPPORT`, `COMPLEMENT`, `OPTIONAL`)
4. menyimpan policy reversal yang dipilih saat void/refund
5. mengurangi risiko reversal yang salah karena hitung ulang resep setelah master sudah berubah

Service yang disiapkan:
1. `create_snapshot`
2. `build_reversal_plan`
3. `apply_reversal_plan`
4. `mark_committed`
5. `mark_reversed`

Artinya, saat void/refund nanti UI tidak perlu menebak dari nol.
UI cukup:
1. ambil snapshot order
2. cek apakah line sudah diproses
3. tampilkan saran `return_to_stock` atau `adjustment_only`
4. simpan keputusan reversal ke snapshot sebelum writer stok dijalankan

## 12) Flow Ringkas End-to-End
1. Kasir buka POS.
2. POS baca `pos_product_availability_cache`.
3. Kasir pilih produk.
4. Sistem cek apakah produk:
- available normal
- available via override non-main
- out karena bahan `MAIN`
5. Kasir submit order.
6. Sistem jalankan stock commit.
7. Sistem tulis usage stok.
8. Sistem update order `stock_commit_status`.
9. Sistem rebuild availability produk terdampak.
10. Payment diselesaikan kemudian, tanpa menjadi trigger utama pengurangan stok.

## 12.1 Bundle pricing untuk revenue dan profit line
Jika order line adalah bundle:
1. sistem tetap menghitung HPP per item penyusun bundle dari resep/item aslinya
2. revenue per item **tidak** boleh otomatis memakai harga jual normal penuh jika harga bundle lebih rendah/berbeda
3. revenue item bundle dialokasikan dari harga jual riil bundle

Standar yang dipakai:
1. default = `AUTO_PROPORTIONAL`
2. jika semua line bundle tanpa override, alokasi revenue line dilakukan proporsional terhadap harga normal item
3. jika ada override manual pada line tertentu, line itu memakai nilai override
4. sisa revenue bundle dibagikan proporsional ke line lain yang belum override
5. hasil pembulatan akhir harus dijaga agar total revenue line = revenue bundle

Tujuan:
1. laporan profit per produk tetap realistis
2. discount bundle tidak “hilang” di level line
3. refund parsial dan analitik produk lebih presisi

## 13) Rekomendasi Implementasi Tahap Lanjut
1. Tambahkan role bahan di recipe produk jika belum eksplisit.
2. Siapkan service tunggal `PosStockCommitService`.
3. Siapkan service `PosBundlePricingService` untuk alokasi revenue bundle per line.
4. Siapkan service `PosAvailabilityRebuildService`.
5. Siapkan worker opsional untuk rebuild batch, tetapi request normal tetap update affected product secara langsung.
6. Hardening audit commit snapshot pada phase 2.
