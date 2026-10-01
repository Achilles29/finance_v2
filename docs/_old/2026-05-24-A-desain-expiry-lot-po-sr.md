# Desain Expiry Keluar dari Profile, Masuk ke Layer LOT

Tanggal: 2026-05-24

## 1. Ringkasan keputusan

Keputusan yang disarankan:

- `expired_date` tidak lagi menjadi bagian dari identitas profile catalog.
- `profile_key` hanya mewakili identitas produk tetap: item/material, nama, merk, deskripsi, UOM beli, UOM isi, dan rasio isi.
- Expiry dipindahkan ke layer lot inbound/outbound.
- Halaman katalog, profile search, dan lookup SR/PO tetap mencari produk per profile, bukan per expiry.
- Stok summary warehouse/division tetap agregat per profile agar tidak meledak jadi banyak baris.
- Detail expiry dan FIFO/FEFO dilayani dari tabel lot.

Ini adalah skema paling aman untuk kondisi repo saat ini karena:

- flow division request -> split SR/PO -> PO -> receipt -> stok sudah berjalan dengan `profile_key`
- tabel lot dasar sudah ada: `inv_material_fifo_lot`, `inv_material_fifo_issue_log`, `inv_material_fifo_issue_line`
- balance dan daily rollup aktif saat ini masih agregat per profile, belum lot-aware penuh

## 2. Fakta kondisi saat ini

Saat ini `expired_date` memang masih ikut identitas profile.

Bukti di repo:

- `mst_purchase_catalog.expired_date` ditambahkan oleh migrasi `2026-05-06a_purchase_profile_expired_date.sql`
- `resolveCatalogProfileKeyByIdentity()` di `Purchase_model` masih mencocokkan catalog berdasarkan `expired_date`
- `ensureCatalogProfileFromOpeningIdentity()` masih membentuk hash `profile_key` dengan memasukkan `profileExpiredDate`
- tabel stok aktif juga masih menyimpan `profile_expired_date` sebagai bagian snapshot/profile payload

Akibatnya:

- barang identik dengan expiry berbeda bisa jadi profile berbeda
- catalog search akan makin kotor kalau receipt makin banyak
- stok agregat terlihat rapi hanya karena expiry diserap ke identity profile, bukan karena lot dikelola dengan benar
- kalau expiry nanti dihapus mendadak tanpa lot layer, stok akan tercampur dan jejak FIFO hilang

## 3. Prinsip desain target

### 3.1 Identitas profile

Profile harus hanya mewakili identitas produk yang stabil:

- `line_kind`
- `item_id` / `material_id`
- `catalog_name`
- `brand_name`
- `line_description`
- `buy_uom_id`
- `content_uom_id`
- `content_per_buy`
- `conversion_factor_to_content`

Yang tidak boleh masuk identitas profile:

- `expired_date`
- `receipt_date`
- `lot_no`
- tanggal produksi
- dokumen asal receipt tertentu

### 3.2 Expiry sebagai atribut lot

Expiry adalah atribut batch fisik barang, bukan atribut master.

Artinya:

- PO line boleh menyimpan kebutuhan expiry sebagai requirement transaksi
- receipt line menyimpan expiry aktual barang yang datang
- lot menyimpan expiry final yang dipakai saat stok bergerak
- warehouse/division balance summary cukup agregat per profile
- audit detail expiry diambil dari lot detail

### 3.3 Sinkron dengan flow yang sudah ada

Flow yang harus tetap nyambung:

1. Division request membuat kebutuhan barang berdasarkan profile
2. Buyer verify dan split menjadi SR gudang / PO vendor
3. PO line tetap memakai profile yang sama
4. Receipt PO membuat lot inbound
5. Fulfillment SR memakai lot eligible dari warehouse
6. Saat stok pindah ke division, lot yang sama diteruskan atau dibuat child lot

## 4. Skema data yang disarankan

## 4.1 Master catalog

Tabel utama: `mst_purchase_catalog`

Target:

- `expired_date` tidak lagi dipakai dalam canonical identity
- pencarian catalog tidak dibedakan oleh expiry
- bila tetap ingin menyimpan informasi shelf life default, simpan sebagai metadata produk, bukan identity

Saran kolom:

- pertahankan `profile_key`
- pertahankan `catalog_name`, `brand_name`, `line_description`, `buy_uom_id`, `content_uom_id`, `content_per_buy`
- `expired_date` dihapus dari generator profile key
- opsional tambah salah satu dari ini bila dibutuhkan bisnis:
  - `default_shelf_life_days`
  - `requires_expiry_tracking` TINYINT(1)
  - `expiry_control_mode` ENUM('NONE','OPTIONAL','MANDATORY')

Catatan penting:

- tidak perlu lagi membuat 10 profile catalog hanya karena barang yang sama datang dengan 10 tanggal expiry berbeda
- lookup profile untuk division request, PO edit, dan profile search tetap bersih

## 4.2 Requirement expiry di level dokumen

Expiry yang diminta user sebaiknya disimpan sebagai requirement dokumen, bukan identity profile.

### A. Division request line

Tabel: `pur_division_request_line`

Status saat ini:

- masih ada `profile_expired_date`

Saran pengganti:

- `expiry_policy` ENUM('NONE','EXACT_DATE','MIN_REMAINING_DAYS') NOT NULL DEFAULT 'NONE'`
- `required_expiry_date` DATE NULL
- `min_remaining_days` INT NULL

Makna:

- `NONE`: divisi tidak mensyaratkan expiry tertentu, asal valid
- `EXACT_DATE`: divisi memang meminta expiry tertentu
- `MIN_REMAINING_DAYS`: divisi minta sisa umur minimal, misalnya 30 hari

Kenapa ini lebih baik:

- user jarang tahu exact expiry lot yang akan datang atau yang ada di gudang
- kebutuhan operasional biasanya lebih cocok dinyatakan sebagai "minimal sisa umur" daripada tanggal exact

### B. Store request line

Tabel: `pur_store_request_line`

Saran sama:

- `expiry_policy`
- `required_expiry_date`
- `min_remaining_days`

Fungsi:

- saat shortage dari division request diarahkan ke SR gudang, requirement expiry ikut tersalin
- warehouse fulfillment hanya boleh memakai lot yang lolos requirement tersebut

### C. Purchase order line

Tabel: `pur_purchase_order_line`

Status saat ini:

- masih ada `expired_date` dan `snapshot_expired_date`

Saran arah baru:

- ubah makna `expired_date` lama menjadi `required_expiry_date`
- atau tambah kolom baru yang eksplisit:
  - `expiry_policy`
  - `required_expiry_date`
  - `min_remaining_days`
- `snapshot_expired_date` cukup jadi snapshot requirement saat line dibuat, bukan identity catalog

Fungsi:

- buyer bisa tetap menaruh syarat expiry di PO ke vendor
- tetapi profile tetap tunggal

### D. Purchase receipt line

Tabel: `pur_purchase_receipt_line`

Saran:

- `received_expiry_date` DATE NULL
- `lot_id` BIGINT UNSIGNED NULL
- `lot_no` VARCHAR(80) NULL
- opsional `manufactured_date` DATE NULL

Kalau ingin minimal perubahan, kolom `expired_date` yang sudah ada di receipt line boleh dipertahankan sebagai expiry aktual receipt, bukan expiry profile.

## 4.3 Tabel lot sebagai source of truth expiry

Tabel dasar yang sudah ada: `inv_material_fifo_lot`

Kolom penting yang sudah cocok:

- `location_scope`
- `division_id`
- `destination_type`
- `receipt_date`
- `expiry_date`
- `item_id`
- `material_id`
- `content_uom_id`
- `profile_key`
- `qty_in`, `qty_out`, `qty_balance`
- `receipt_id`, `receipt_line_id`
- `parent_lot_id`

Keputusan pragmatis:

- untuk sekarang pakai tabel ini sebagai lot layer utama, walau namanya masih `inv_material_fifo_lot`
- tidak perlu rename dulu kalau tujuannya menjaga perubahan tetap terkontrol
- nanti setelah stabil baru boleh dipertimbangkan rename ke nama yang lebih generik seperti `inv_stock_lot`

Peran tabel ini:

- setiap receipt PO membuat 1 lot inbound per baris fisik yang diterima
- expiry final barang tersimpan di sini
- pergerakan keluar warehouse/division mengurangi `qty_balance` lot ini
- transfer warehouse -> division membuat child lot di scope tujuan dengan `parent_lot_id`

## 4.4 Balance dan stok harian

### Balance summary tetap agregat

Tabel aktif:

- `inv_warehouse_stock_balance`
- `inv_division_stock_balance`

Saran:

- tetap pertahankan tabel ini sebagai summary per profile
- hapus ketergantungan identity pada `profile_expired_date`
- saldo summary diupdate dari movement yang sumber detailnya lot-aware

Artinya:

- list stok utama tetap 1 baris per profile
- expiry detail tidak dicampur di UI summary
- kebutuhan audit diambil dari lot drill-down

### Daily rollup tetap agregat, lot jadi drill-down

Tabel aktif:

- `inv_warehouse_daily_rollup`
- `inv_division_daily_rollup`

Saran:

- untuk fase awal tetap agregat per profile
- jangan tambah expiry ke identity rollup utama
- jika nanti dibutuhkan audit harian per lot, buat tabel terpisah:
  - `inv_warehouse_daily_rollup_lot`
  - `inv_division_daily_rollup_lot`

Ini lebih aman daripada memaksa semua list utama langsung lot-aware.

## 5. Alur end-to-end yang disarankan

## 5.1 Division request

User divisi membuat request berdasarkan profile biasa.

Line menyimpan:

- `profile_key`
- qty request
- `expiry_policy`
- `required_expiry_date` atau `min_remaining_days`

Tidak menyimpan expiry sebagai identity profile.

## 5.2 Verify division request

Buyer tetap bekerja seperti sekarang:

- koreksi profile/brand/deskripsi/harga/vendor
- sistem split ke `SR`, `PO`, atau `MIXED`

Tambahan aturan:

- requirement expiry ikut diteruskan ke hasil split
- profile search tetap bersih karena tidak terfragmentasi oleh expiry

## 5.3 Route ke store request gudang

Jika line diarahkan ke SR:

- `pur_store_request_line` menerima `expiry_policy`, `required_expiry_date`, `min_remaining_days`
- availability check gudang boleh tetap pakai agregat summary untuk cepat
- tetapi saat posting fulfillment, sistem harus memilih lot yang eligible

Rule picking yang disarankan:

- exclude lot yang sudah expired pada tanggal fulfill
- jika ada `required_expiry_date`, hanya lot dengan `expiry_date = required_expiry_date` atau sesuai rule bisnis yang disepakati
- jika ada `min_remaining_days`, hanya lot dengan `DATEDIFF(expiry_date, fulfillment_date) >= min_remaining_days`
- urutan pilih: FEFO lebih cocok daripada FIFO murni untuk barang expiry
  - prioritas 1: expiry terdekat yang masih valid
  - prioritas 2: receipt_date tertua
  - prioritas 3: id lot terkecil

## 5.4 Route ke purchase order

Jika line diarahkan ke PO:

- `pur_purchase_order_line` menyimpan requirement expiry, bukan identity expiry
- vendor menerima instruksi expiry dari PO line
- profile_key PO tetap profile biasa tanpa expiry

## 5.5 Receipt purchase

Saat receipt diposting:

- sistem membaca profile PO line
- sistem membaca expiry aktual barang datang dari receipt line
- sistem membuat record di `inv_material_fifo_lot`
- `pur_purchase_receipt_line.lot_id` dan `lot_no` diisi
- balance warehouse agregat bertambah per profile
- lot balance bertambah per lot

Jika 1 receipt line datang dengan beberapa expiry berbeda, UI receipt harus mendukung split per sub-line lot.

Itu jauh lebih benar daripada memaksa 1 profile catalog per expiry.

## 5.6 Fulfillment warehouse ke division

Saat SR fulfillment diposting:

- sistem pilih lot warehouse yang eligible
- sistem catat issue ke `inv_material_fifo_issue_log` dan `inv_material_fifo_issue_line`
- `pur_store_request_fulfillment_line.fifo_issue_id` mengikat ke issue header
- untuk stok division, sistem membuat child lot baru di `inv_material_fifo_lot` dengan:
  - scope `DIVISION`
  - `parent_lot_id` = lot warehouse asal
  - expiry diwarisi dari lot asal

Hasilnya:

- stok division tetap bisa dilihat agregat per profile
- tetapi setiap saldo division tetap punya jejak lot asal
- kalau nanti ada void fulfillment, lot yang dikembalikan bisa tepat, tidak asal profile

## 5.7 Direct PO ke division

Untuk purchase type yang receipt-nya langsung ke division:

- receipt langsung membuat lot scope `DIVISION`
- tidak perlu parkir dulu ke warehouse
- requirement expiry dari PO line tetap bisa divalidasi saat receipt

## 6. Rekomendasi perubahan kolom

## 6.1 Kolom yang dipertahankan sementara sebagai legacy snapshot

Kolom berikut jangan dihapus di tahap awal, tetapi statusnya diturunkan menjadi snapshot/legacy:

- `mst_purchase_catalog.expired_date`
- `inv_* .profile_expired_date`
- `pur_* .profile_expired_date`

Gunanya:

- transisi bertahap
- kompatibilitas report lama
- memudahkan audit dan rollback

Tetapi untuk logika baru:

- jangan pakai lagi kolom-kolom ini sebagai identity utama

## 6.2 Kolom baru yang disarankan

Minimal set:

- `pur_division_request_line.expiry_policy`
- `pur_division_request_line.required_expiry_date`
- `pur_division_request_line.min_remaining_days`
- `pur_store_request_line.expiry_policy`
- `pur_store_request_line.required_expiry_date`
- `pur_store_request_line.min_remaining_days`
- `pur_purchase_order_line.expiry_policy`
- `pur_purchase_order_line.required_expiry_date`
- `pur_purchase_order_line.min_remaining_days`
- `pur_purchase_receipt_line.received_expiry_date` atau gunakan `expired_date` existing sebagai actual expiry
- `pur_purchase_receipt_line.lot_id`
- `pur_purchase_receipt_line.lot_no`

## 7. Perubahan kode yang wajib disentuh

## 7.1 Purchase_model

Area penting:

- generator / resolver profile key
- catalog search
- receipt posting
- inventory posting
- rebuild opening / canonicalization logic

Perubahan inti:

- `resolveCatalogProfileKeyByIdentity()` jangan lagi filter berdasarkan `expired_date`
- `ensureCatalogProfileFromOpeningIdentity()` jangan lagi memasukkan expiry ke hash `profile_key`
- alur receipt harus membuat lot dan mengisi `lot_id`
- `profile_expired_date` hanya jadi snapshot transisi bila masih diperlukan

## 7.2 Procurement_model

Area penting:

- division request save/edit/verify
- split shortage ke SR dan PO
- store request fulfill
- reverse fulfillments before void

Perubahan inti:

- ganti `profile_expired_date` menjadi expiry requirement transaksi
- routing SR/PO harus mewariskan requirement expiry
- fulfillment harus memilih lot eligible dan simpan jejak issue/lot

## 7.3 InventoryLedger

Perubahan inti:

- payload inventory harus menerima konteks lot
- update aggregate summary tetap jalan
- lot balance juga harus ter-update
- reversal wajib lot-preserving

## 7.4 UI utama

Halaman yang terdampak:

- profile search procurement
- division request form
- store request fulfillment form
- purchase order create/edit
- purchase receipt form
- stock warehouse/division list
- daily matrix warehouse/division/material

Arah UI:

- profile picker tetap tanpa expiry split
- request/PO line menampilkan requirement expiry bila perlu
- receipt/fulfillment menampilkan actual lot dan expiry
- stock list utama tetap agregat
- sediakan drill-down lot per profile

## 8. Strategi migrasi yang paling aman

## Tahap 1 - Freeze identity baru

- hentikan pemakaian `expired_date` dalam resolver/generator `profile_key`
- catalog baru dan canonicalization baru tidak lagi membedakan expiry
- kolom expiry lama masih tetap disimpan sebagai legacy snapshot

## Tahap 2 - Tambah requirement expiry dokumen

- tambah `expiry_policy`, `required_expiry_date`, `min_remaining_days`
- UI division request, SR, dan PO mulai memakai kolom baru ini
- copy data lama seperlunya dari `profile_expired_date` ke `required_expiry_date`

## Tahap 3 - Aktifkan lot inbound receipt

- saat receipt, buat lot inbound
- isi `lot_id` pada receipt line
- actual expiry dibaca dari receipt, bukan dari profile

## Tahap 4 - Aktifkan fulfillment lot-aware

- warehouse fulfill pakai FEFO/FIFO eligible lot
- division menerima child lot
- void fulfillment membalik lot yang sama

## Tahap 5 - Rapikan summary dan report

- balance/daily summary tetap agregat profile
- drill-down lot tersedia untuk audit
- `profile_expired_date` di report lama diperlakukan sebagai legacy display, bukan identity

## Tahap 6 - Cleanup legacy

Setelah beberapa siklus stabil:

- stop menampilkan `expired_date` di catalog master
- evaluasi hapus `profile_expired_date` dari tabel-tabel summary baru
- pertahankan hanya di histori lama bila masih dibutuhkan audit

## 9. Rekomendasi final

Skema terbaik untuk repo ini bukan "expiry di master profile" dan juga bukan "semua layar langsung lot-aware penuh".

Skema terbaik adalah:

- profile tetap bersih dan stabil
- expiry pindah ke lot
- request/PO hanya menyimpan kebutuhan expiry
- receipt menyimpan expiry aktual dan membuat lot
- fulfillment warehouse/division memakai lot eligible
- list stok utama tetap agregat per profile
- audit expiry/FIFO disediakan lewat drill-down lot

Ini menjaga tiga hal sekaligus:

- master catalog tidak kotor
- stok fisik tidak tercampur buta
- flow SR gudang, PO, dan stok division yang sudah jadi tetap bisa dilanjutkan tanpa dibongkar total

## 10. Saran implementasi tahap pertama

Kalau ingin mulai dari langkah paling aman dan paling berdampak, urutannya:

1. Lepaskan `expired_date` dari generator dan resolver `profile_key`
2. Tambah kolom requirement expiry di division request, SR, dan PO line
3. Jadikan `pur_purchase_receipt_line.expired_date` sebagai actual receipt expiry
4. Aktifkan pembuatan lot inbound saat receipt
5. Baru setelah itu aktifkan fulfillment lot-aware warehouse -> division
