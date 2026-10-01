# Konsep Item-Centric untuk Inventory, Procurement, Production, dan POS
**Tanggal:** 2026-06-04  
**Status:** Draft konsep arsitektur sebelum refactor bertahap

## 1. Tujuan
Dokumen ini merumuskan arah baru yang lebih aman dan efisien:
1. `mst_item` menjadi identitas transaksi utama lintas modul.
2. `mst_material` tetap dipertahankan, tetapi tidak lagi menjadi identitas transaksi aktif harian.
3. Perbedaan `Persediaan Produksi` vs `Kebutuhan Operasional` dibedakan oleh konteks transaksi, bukan oleh dual identity `item_id` vs `material_id`.
4. Bug split domain `ITEM/MATERIAL` di stok, HPP, FIFO, dan POS dihentikan dari akarnya.

Masalah yang ingin diselesaikan:
1. Satu barang fisik bisa tercatat ganda sebagai `ITEM` dan `MATERIAL`.
2. HPP live, stok divisi, stock commit POS, dan reconcile menjadi tidak konsisten.
3. Repair data berulang menjadi mahal karena fondasinya masih ambigu.

## 2. Ringkasan keputusan yang diusulkan
### 2.1 Canonical transaction identity
Keputusan yang diusulkan:
1. Semua transaksi operasional memakai `item_id` sebagai identitas utama.
2. `material_id` tetap ada sebagai metadata turunan dari item.
3. `material_id` dipakai untuk:
   - tagging UI bahan baku
   - relasi resep
   - compatibility lama
   - audit dan laporan teknis
4. `material_id` tidak lagi menjadi identity ledger utama untuk transaksi baru.

### 2.2 Production vs operational ditentukan oleh purpose
Keputusan yang diusulkan:
1. `usage_purpose = Persediaan Produksi` berarti stok boleh masuk ke stok divisi produksi / destinasi produksi.
2. `usage_purpose = Kebutuhan Operasional` berarti stok hanya berhenti di scope operasional yang relevan dan tidak mengalir ke stok divisi produksi.
3. Perbedaan jalur tidak lagi ditentukan oleh apakah row itu `ITEM` atau `MATERIAL`.

### 2.3 Material table tidak dihapus
Keputusan yang diusulkan:
1. `mst_material` tetap dipertahankan.
2. `mst_material` berperan sebagai:
   - klasifikasi bahan baku
   - master teknis resep
   - anchor kompatibilitas lama
   - jembatan migrasi bertahap
3. Jadi kita tidak melakukan refactor ekstrem dengan menghapus tabel material di awal.

## 3. Masalah akar yang ingin diputus
### 3.1 Split identity pada stock domain
Masalah saat ini:
1. Satu item yang punya `material_id` bisa tercatat sebagai `ITEM` pada sebagian transaksi.
2. Pada transaksi lain, item yang sama tercatat sebagai `MATERIAL`.
3. Akibatnya monthly stock, daily rollup, FIFO lot, dan movement log pecah menjadi dua dunia.

Dampak:
1. availability produk membaca angka yang berbeda dari recipe page
2. retry stock commit bisa memakai snapshot cost yang sudah salah
3. reconcile material dan POS stock live sama-sama berbunyi, tetapi bukan karena alasan yang sehat

### 3.2 Retry stock commit saat ini masih snapshot-centric lama
Temuan saat ini:
1. runtime job retry di POS masih mem-posting snapshot lama
2. snapshot lama bisa membawa `unit_cost_live` yang sudah terbentuk dari data legacy kotor
3. jadi walaupun monthly stock direpair, retry tetap bisa gagal karena ia tidak me-resolve ulang line snapshot

Konsekuensi:
1. repair data saja tidak cukup
2. kita perlu blueprint baru agar retry nanti re-resolve berdasarkan canonical identity yang baru

## 4. Arsitektur target item-centric
### 4.1 Master utama
Master yang dipakai:
1. `mst_item` = canonical inventory identity
2. `mst_material` = metadata bahan baku / recipe tag / compatibility
3. `mst_component` = hasil produksi / base / prepare
4. `mst_uom` = satuan

Aturan relasi:
1. `mst_item.material_id` boleh nullable
2. jika item adalah bahan baku, `material_id` terisi
3. jika item non-bahan baku, `material_id` boleh null
4. recipe produk dan formula component tetap boleh merujuk `material_id` pada fase transisi
5. target akhir: recipe juga bisa direlasikan aman ke `item_id` atau minimal punya canonical bridge ke item

### 4.2 Ledger dan stock balance
Keputusan target:
1. ledger harian/bulanan memakai `item_id` sebagai kunci utama transaksi
2. `material_id` disimpan sebagai atribut turunan, bukan identity utama
3. `identity_key` dibangun dari basis item-centric
4. `stock_domain` dipersempit secara makna:
   - `ITEM` = canonical transaction domain
   - `MATERIAL` = legacy/compatibility domain selama masa migrasi

Target akhirnya:
1. transaksi baru hanya menciptakan row canonical `ITEM`
2. row `MATERIAL` lama dipelihara hanya selama masa transisi
3. setelah migrasi stabil, UI dan service membaca domain item-centric dulu

### 4.3 FIFO lot
Keputusan target:
1. lot FIFO inbound/outbound dibangun di atas `item_id`
2. `material_id` tetap ikut disimpan sebagai metadata bila item adalah bahan baku
3. pemakaian lot tetap menghitung cost per lot secara proporsional
4. FIFO tidak lagi perlu menebak apakah identitas stok sumber adalah item atau material

### 4.4 Procurement dan inventory flow
Alur yang diusulkan:
1. PO
   - line canonical memakai `item_id`
2. receipt
   - posting ledger canonical memakai `item_id`
3. SR/fulfillment
   - canonical memakai `item_id`
   - `usage_purpose` menentukan jalur stok lanjutannya
4. adjustment
   - canonical memakai `item_id`
5. opening
   - canonical memakai `item_id`

### 4.5 POS recipe dan stock live
Keputusan target:
1. recipe/extra resolution harus berujung ke canonical inventory item
2. stock live POS membaca canonical item balance
3. stock commit POS saat retry harus me-resolve ulang line cost dan qty dari canonical item-centric source
4. HPP live monitoring dan HPP commit tetap dibedakan:
   - monitoring = estimated live cost
   - commit = realized cost saat posting FIFO/ledger

## 5. Bagaimana membedakan produksi vs operasional
Perbedaan tidak lagi memakai dua identity, tetapi memakai policy transaksi.

### 5.1 Purpose utama
1. `Persediaan Produksi`
2. `Kebutuhan Operasional`

### 5.2 Rule yang diusulkan
1. Jika purpose = `Persediaan Produksi`
   - item boleh masuk stok gudang bahan
   - item boleh ditransfer ke stok divisi produksi
   - item boleh dipakai recipe POS/production
2. Jika purpose = `Kebutuhan Operasional`
   - item tetap tercatat di gudang secara canonical
   - fulfill operasional mengurangi gudang
   - tidak membentuk stok divisi produksi
   - tidak ikut menjadi source recipe produksi/POS kecuali ada mapping khusus

### 5.3 UI
1. Di halaman stok bahan baku/divisi, user tidak perlu dibebani label `ITEM/MATERIAL`.
2. Yang penting bagi user adalah:
   - nama item
   - apakah ini bahan baku
   - tujuan stok
   - purpose transaksi
3. Label `material` cukup tampil sebagai kategori/tag, bukan identitas transaksi.

## 6. Dampak ke modul-modul
### 6.1 Purchase / warehouse
Kena dampak:
1. purchase order line
2. purchase receipt line
3. stock opening
4. stock adjustment
5. warehouse monthly/daily rollup

### 6.2 Procurement / SR
Kena dampak:
1. division request line
2. store request line
3. fulfillment line
4. preview split / fulfill auto

### 6.3 Production
Kena dampak:
1. formula component
2. issue bahan ke batch
3. output component
4. component cost rollup

### 6.4 POS
Kena dampak:
1. product recipe resolution
2. extra replacement source
3. stock live cache
4. stock commit snapshot
5. retry failed job
6. void/refund reversal

### 6.5 Reconcile / audit
Kena dampak:
1. stock division reconcile
2. stock live POS
3. stock commit audit
4. material daily / daily division

## 7. Kelebihan pendekatan ini
1. Satu transaksi satu identitas utama.
2. Split domain `ITEM/MATERIAL` berhenti bertambah.
3. Repair historis menjadi pekerjaan satu arah, bukan tambal sulam.
4. `material_id` tetap aman jika nanti ternyata masih dibutuhkan untuk logic tertentu.
5. Refactor lebih aman dibanding langsung menghapus `mst_material`.

## 8. Risiko dan biaya refactor
Jawaban jujur: ini tetap refactor besar.

Yang membuatnya besar:
1. banyak tabel stok memakai kombinasi `item_id/material_id`
2. FIFO lot dan snapshot commit sudah berjalan
3. reconcile UI dan laporan sudah telanjur membaca dua domain
4. recipe dan production masih sangat material-centric

Tetapi dibanding `drop mst_material`, pendekatan ini lebih aman karena:
1. tidak memutus semua FK lama sekaligus
2. memungkinkan migrasi bertahap
3. memberi fallback saat ditemukan edge case operasional baru

## 9. Strategi migrasi yang disarankan
### Fase 0: freeze desain
1. setujui bahwa canonical identity transaksi baru adalah `item_id`
2. setujui peran `material_id` hanya sebagai metadata / recipe tag / compatibility

### Fase 1: stop the bleeding
1. semua transaksi baru wajib canonical `item_id`
2. service writer stok baru tidak boleh membuat row `MATERIAL` baru untuk transaksi baru
3. retry stock commit baru wajib re-resolve snapshot dari canonical source

### Fase 2: bridge read model
1. halaman audit/reconcile membaca item-centric source dulu
2. halaman lama tetap bisa menampilkan material tag dari `mst_item.material_id`
3. stock live POS memakai item-centric availability source

### Fase 3: repair historis
1. audit semua row legacy dual-domain
2. migrasi movement/monthly/daily/lot ke item-centric canonical mapping
3. sisakan row lama hanya jika dibutuhkan untuk audit historis

### Fase 4: recipe/profit alignment
1. samakan engine perhitungan HPP monitoring lintas halaman
2. putuskan policy variable cost:
   - OFF
   - DEFAULT
   - CUSTOM
3. bedakan jelas antara estimated HPP monitoring vs realized HPP commit

## 10. Rekomendasi implementasi praktis berikutnya
Kalau konsep ini disetujui, langkah berikut paling aman:
1. buat dokumen mapping tabel mana yang akan menjadi item-centric lebih dulu
2. patch retry stock commit agar re-resolve snapshot line sebelum repost
3. patch writer transaksi baru agar canonical item-centric
4. baru lanjut repair historis domain stok yang tersisa

## 11. Keputusan yang masih perlu disepakati
1. Apakah recipe tetap direlasikan ke `material_id` selama fase transisi, atau mulai dipindah ke `item_id`?
2. Apakah monthly/daily rollup akan tetap mempertahankan kolom `material_id` sebagai atribut sekunder?
3. Apakah laporan profit POS memakai:
   - direct live cost saja
   - direct + variable default
   - direct + variable custom per product

## 12. Rekomendasi saya
Saya merekomendasikan:
1. setujui model `item-centric transaction, material as metadata`
2. jangan patch retry stock commit besar-besaran dulu di fondasi lama
3. gunakan desain ini sebagai acuan semua patch berikutnya

Dengan begitu, kerja repair kita tidak bolak-balik dua kali.
