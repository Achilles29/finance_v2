# Mapping Refactor Item-Centric per Tabel, Dampak Modul, dan Urutan Migrasi
**Tanggal:** 2026-06-04  
**Status:** Draft implementasi bertahap lanjutan dari konsep item-centric

## 1. Tujuan
Dokumen ini menerjemahkan konsep item-centric menjadi peta kerja yang bisa dieksekusi bertahap.

Tujuan utamanya:
1. Menentukan tabel mana yang menjadi pusat refactor.
2. Menentukan modul mana yang terdampak.
3. Menentukan urutan migrasi paling aman agar operasional tidak terganggu.
4. Menentukan mana yang harus diubah dulu, mana yang bisa menunggu fase berikut.

Dokumen ini melanjutkan konsep dasar pada:
- [2026-06-04c_konsep_item_centric_inventory_procurement_production_pos.md](/c:/xampp/htdocs/finance/docs/2026-06-04c_konsep_item_centric_inventory_procurement_production_pos.md)

## 2. Prinsip Dasar Refactor
### 2.1 Canonical identity
Prinsip yang dipakai:
1. `item_id` menjadi identitas transaksi utama.
2. `material_id` tetap disimpan sebagai metadata/atribut sekunder.
3. Semua transaksi baru harus berhenti membuat dual identity `ITEM` vs `MATERIAL`.

### 2.2 Production vs operational
Pembeda alur:
1. `usage_purpose = Persediaan Produksi`
2. `usage_purpose = Kebutuhan Operasional`

Artinya:
1. Identitas transaksi tidak lagi dibedakan oleh `item_id` vs `material_id`.
2. Yang membedakan adalah tujuan stok dan policy flow.

### 2.3 Legacy compatibility
Selama masa transisi:
1. `material_id` tetap dipelihara.
2. Tabel dan UI lama masih boleh membaca atribut `material_id`.
3. Tetapi writer transaksi baru tidak boleh lagi menciptakan row ledger canonical berbasis `material_id`.

## 3. Klasifikasi Tabel
Refactor dibagi menjadi 4 lapisan:
1. `Master Mapping`
2. `Transactional Writers`
3. `Stock Ledger / Balance`
4. `Read Model / UI / Audit`

## 4. Mapping Refactor per Tabel
### 4.1 Master mapping
#### 4.1.1 `mst_item`
Peran target:
1. Canonical identity inventory.
2. Menjadi FK utama transaksi baru.

Kolom penting:
1. `id`
2. `material_id`
3. `default_usage_purpose`

Status refactor:
1. Dipertahankan.
2. Menjadi pusat canonical mapping.

#### 4.1.2 `mst_material`
Peran target:
1. Metadata/tagging bahan baku.
2. Anchor recipe/UI/compatibility.

Status refactor:
1. Tidak dihapus.
2. Tidak lagi menjadi identity transaksi harian baru.

#### 4.1.3 `mst_product_recipe`
Status saat ini:
1. Masih material-centric/component-centric.

Arah target:
1. Tetap bisa membaca `material_id` pada fase transisi.
2. Tambah bridge ke item canonical, atau service resolver yang selalu mengembalikan `item_id`.

Prioritas:
1. Tinggi, karena POS stock live dan stock commit bertumpu di sini.

#### 4.1.4 `mst_component_formula`
Status saat ini:
1. Masih bahan-centric.

Arah target:
1. Resolver formula harus menghasilkan source canonical item/component.

Prioritas:
1. Tinggi untuk modul production dan POS.

### 4.2 Procurement dan purchase transaction tables
#### 4.2.1 `pur_purchase_order_line`
Kondisi target:
1. `item_id` wajib menjadi identity utama.
2. `material_id` hanya atribut turunan bila item adalah bahan baku.
3. `line_kind` jangka panjang cukup untuk konteks UI/flow, bukan pembentuk dual-ledger identity.

Perlu diubah:
1. builder line
2. save/update line
3. mapping dari catalog/profile

#### 4.2.2 `pur_purchase_receipt_line`
Kondisi target:
1. receipt canonical memakai `item_id`
2. posting stok masuk canonical juga memakai `item_id`

Perlu diubah:
1. posting receipt ke ledger
2. inbound FIFO lot identity

#### 4.2.3 `pur_division_request_line`
Kondisi target:
1. tetap pakai `item_id`
2. `usage_purpose` menentukan jalur stok lanjutannya

#### 4.2.4 `pur_store_request_line`
Kondisi target:
1. tetap pakai `item_id`
2. `material_id` hanya metadata

#### 4.2.5 `pur_store_request_fulfillment_line`
Kondisi target:
1. fulfill canonical pakai `item_id`
2. tujuan produksi vs operasional dibedakan oleh `usage_purpose`

Catatan penting:
1. Inilah salah satu titik yang paling rawan salah posting saat ini.

### 4.3 Inventory transaction tables
#### 4.3.1 `inv_stock_adjustment`
Tidak banyak berubah di header.

#### 4.3.2 `inv_stock_adjustment_line`
Kondisi target:
1. `item_id` jadi identity utama
2. `material_id` turunan

Perlu diubah:
1. plus/minus writer
2. inbound adjustment lot
3. rollback VOID

#### 4.3.3 `inv_stock_movement_log`
Kondisi target:
1. ledger canonical berbasis `item_id`
2. `material_id` jadi atribut sekunder

Catatan:
1. Ini salah satu tabel paling sensitif.
2. Tidak boleh dibongkar tergesa-gesa.

### 4.4 FIFO / lot tables
#### 4.4.1 `inv_material_fifo_lot`
Kondisi target jangka menengah:
1. Tetap dipakai dulu supaya tidak memutus sistem.
2. Namun identitas lot inbound/outbound dibangun dari canonical `item_id`.
3. `material_id` tetap diisi sebagai metadata untuk item bahan baku.

Catatan:
1. Nama tabel boleh tetap, walau nanti isinya menjadi item-centric.
2. Rename tabel bukan prioritas awal.

#### 4.4.2 `inv_material_fifo_issue_log`
Kondisi target:
1. source consume berbasis item canonical
2. cost split per lot tetap dipertahankan

#### 4.4.3 `inv_material_fifo_issue_line`
Kondisi target:
1. tetap menyimpan proporsi lot dan cost
2. tidak lagi ambigu apakah source item atau material

### 4.5 Stock balance / rollup tables
#### 4.5.1 `inv_division_monthly_stock`
Kondisi target:
1. canonical row baru berbasis `item_id`
2. `material_id` atribut sekunder
3. `identity_key` jangka panjang dibangun dari item-centric identity

Catatan:
1. Ini adalah titik sentral reconcile dan stock live.

#### 4.5.2 `inv_warehouse_monthly_stock`
Kondisi target sama:
1. canonical row baru berbasis `item_id`
2. `material_id` hanya metadata

#### 4.5.3 `inv_division_daily_rollup`
Kondisi target:
1. item-centric canonical
2. tetap membawa `material_id` untuk UI dan audit

#### 4.5.4 `inv_warehouse_daily_rollup`
Kondisi target:
1. item-centric canonical
2. tetap membawa `material_id` untuk UI dan audit

#### 4.5.5 Opening snapshot / monthly opname
Tabel terdampak:
1. `inv_division_stock_opening_snapshot`
2. `inv_warehouse_stock_opening_snapshot`
3. `inv_stock_opening_snapshot`
4. `inv_division_monthly_opname`
5. `inv_warehouse_monthly_opname`

Kondisi target:
1. identity opening canonical berbasis `item_id`
2. `material_id` tetap disimpan untuk labeling dan traceability

### 4.6 POS tables
#### 4.6.1 `pos_stock_commit`
Header relatif aman.

#### 4.6.2 `pos_stock_commit_line`
Kondisi target:
1. source material line harus diturunkan dari canonical item source
2. retry wajib bisa re-resolve line dari source canonical terbaru

#### 4.6.3 `pos_product_availability_cache`
Kondisi target:
1. HPP live monitoring membaca item-centric balance
2. bottleneck masih boleh menampilkan material tag

#### 4.6.4 `pos_order_line`
Kondisi target:
1. HPP snapshot tetap disimpan
2. tetapi sumber snapshot harus berasal dari canonical item-centric resolver

### 4.7 Production tables
#### 4.7.1 `inv_component_monthly_stock`
Tetap berjalan sebagai domain component.

#### 4.7.2 `mst_component_formula`
Harus bisa resolve bahan canonical item.

#### 4.7.3 Batch/issue/output tables di production
Harus dipastikan:
1. pemakaian bahan baku ke batch membaca item canonical
2. output component tetap normal

## 5. Daftar Impact per Modul
### 5.1 Purchase
Dampak:
1. builder PO line
2. save/update receipt
3. posting inbound ke ledger
4. inbound FIFO lot
5. audit dan detail receipt

Risiko:
1. harga satuan dan konversi UOM bisa ikut berubah bila mapping item-profile tidak rapi

### 5.2 Procurement / SR
Dampak:
1. search profile stok gudang
2. preview split
3. fulfill otomatis
4. flow produksi vs operasional

Risiko:
1. jika tidak dipisah jelas, operasional bisa salah masuk stok divisi atau sebaliknya

### 5.3 Inventory
Dampak:
1. adjustment
2. opening
3. movement log
4. monthly/daily rollup
5. reconcile

Risiko:
1. legacy data bisa semakin sulit dibaca kalau refactor writer dilakukan tanpa bridge reader

### 5.4 Production
Dampak:
1. formula resolver
2. issue bahan ke batch
3. HPP component
4. lot component audit

Risiko:
1. production akan rusak jika recipe bridge item-material tidak disiapkan lebih dulu

### 5.5 POS
Dampak:
1. product recipe cost live
2. stock live cache
3. stock commit snapshot
4. retry failed stock commit
5. void/refund reversal

Risiko:
1. kalau retry tetap snapshot-centric lama, refactor stok tidak akan terasa selesai

### 5.6 Reporting dan Audit
Dampak:
1. stock live POS
2. stock division reconcile
3. stock commit audit
4. material daily
5. daily division
6. profit report jika HPP source berubah

## 6. Urutan Migrasi Paling Aman
### Fase 0: Freeze keputusan desain
Yang harus disetujui dulu:
1. canonical transaction identity = `item_id`
2. `material_id` = metadata/tag/compatibility
3. `usage_purpose` = pembeda flow produksi vs operasional

Tanpa ini, patch berikutnya akan bolak-balik.

### Fase 1: Stop the bleeding
Target:
1. transaksi baru tidak lagi membuat canonical row `MATERIAL`
2. writer baru selalu item-centric

Pekerjaan:
1. patch PO writer
2. patch receipt writer
3. patch SR/fulfillment writer
4. patch adjustment writer
5. patch opening writer

Catatan:
1. fase ini tidak perlu menyentuh semua histori lama dulu
2. fokusnya menghentikan data baru menjadi lebih kotor

### Fase 2: Bridge resolver layer
Target:
1. recipe, stock live, dan commit resolver membaca canonical item source

Pekerjaan:
1. buat resolver item-centric untuk recipe material
2. buat bridge material -> canonical item
3. buat retry stock commit melakukan re-resolve snapshot line

Catatan:
1. ini fase paling penting untuk POS
2. tanpa fase ini, retry gagal akan tetap mahal

### Fase 3: Read-model alignment
Target:
1. halaman audit dan monitoring membaca sumber item-centric yang sama

Pekerjaan:
1. samakan logic:
   - product recipe
   - product availability
   - stock live POS
2. putuskan policy variable cost
3. rapikan wording UI agar user tidak melihat dual identity yang membingungkan

### Fase 4: Historical repair
Target:
1. repair data lama yang pecah domain

Pekerjaan:
1. monthly stock legacy
2. daily rollup legacy
3. movement legacy
4. FIFO/lot legacy
5. snapshot commit gagal

Catatan:
1. ini dikerjakan setelah writer dan resolver baru stabil
2. supaya repair tidak perlu diulang dua kali

### Fase 5: Cleanup dan deprecation
Target:
1. domain `MATERIAL` lama tinggal compatibility
2. writer baru tidak lagi memerlukannya

Pekerjaan:
1. tandai code path lama deprecated
2. audit modul mana yang masih material-centric
3. putuskan apakah ada tabel yang cukup dibiarkan, di-bridge, atau akhirnya dipensiunkan

## 7. Langkah Praktis yang Saya Rekomendasikan Sekarang
Urutan paling aman dari kondisi hari ini:
1. setujui dokumen konsep item-centric
2. setujui dokumen mapping ini
3. patch **retry stock commit** agar re-resolve snapshot line dari canonical source
4. patch **writer transaksi baru** agar item-centric
5. baru lanjut repair historis yang lebih luas

## 8. Keputusan yang Masih Perlu Dipilih
### 8.1 Recipe reference
Opsi:
1. tetap `material_id` sementara, dengan bridge ke item
2. mulai tambah `item_id` di recipe dan migrasi bertahap

Rekomendasi saya:
1. pilih opsi `bridge dulu`, supaya tidak terlalu mengganggu POS/production aktif

### 8.2 Variable cost policy
Opsi:
1. `OFF` untuk monitoring dan transaksi
2. `DEFAULT` global
3. `CUSTOM` per product

Rekomendasi saya:
1. monitoring stock live = direct live cost dulu
2. recipe page boleh tampilkan pilihan:
   - tanpa variable
   - dengan variable default/custom

### 8.3 Domain naming
Opsi:
1. tetap simpan nama tabel lama untuk transisi
2. rename tabel besar-besaran

Rekomendasi saya:
1. jangan rename tabel di fase awal
2. ubah makna canonical lebih dulu, nama belakangan

## 9. Ringkasan Rekomendasi
Saya merekomendasikan:
1. `item_id` jadi canonical identity transaksi
2. `material_id` tetap hidup sebagai metadata/bridge
3. writer baru dipatch dulu
4. retry stock commit dipatch sebelum repair lanjutan
5. repair historis besar dilakukan setelah writer + resolver baru stabil

Ini jalur paling aman supaya:
1. operasional tetap jalan
2. data baru tidak makin kotor
3. kerja repair tidak diulang dua kali
