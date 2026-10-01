# Audit Dampak LOT FIFO ke Procurement dan Stok

Tanggal: 2026-05-22

## Fakta schema aktif

- Tabel baru LOT yang sudah ada di DB aktif: `inv_material_fifo_lot`.
- Tabel produksi yang sudah ada: `inv_component_batch`, `inv_component_batch_input`.
- Tabel stok aktif yang masih dipakai purchase/procurement saat ini belum punya kolom `lot_id` / `lot_no`:
  - `inv_stock_movement_log`
  - `inv_warehouse_stock_balance`
  - `inv_division_stock_balance`
  - `inv_warehouse_daily_rollup`
  - `inv_division_daily_rollup`
- Identitas stok saat ini masih berpusat pada kombinasi:
  - item/material
  - UOM beli + UOM isi
  - `profile_key`
  - `division_id` + `destination_type` untuk scope divisi

## Temuan utama

1. Ledger belum lot-aware.
   `InventoryLedger` masih lock dan upsert balance berdasarkan `profile_key` saja, tanpa dimensi lot.

2. Receipt PO belum menyimpan lot inbound.
   Jalur `Purchase_model` saat receipt masih posting inventory memakai payload identity profile dan cost, tetapi belum punya `lot_id` / `lot_no` / tanggal produksi / tanggal masuk lot.

3. Fulfillment SR juga belum lot-aware.
   Jalur `Procurement_model::store_request_fulfill()` masih menarik dan memindah stok warehouse -> division tanpa pemilihan lot keluar.

4. Reverse void fulfillment belum lot-aware.
   Jalur `reverse_fulfillments_before_void()` masih menghapus/mengembalikan movement berbasis identity lama. Begitu LOT aktif, reversal harus mengembalikan lot yang sama, bukan sekadar profile yang sama.

5. Query stok harian dan balance akan salah agregasi bila LOT diaktifkan setengah jalan.
   Daily matrix dan stock balance saat ini agregat per `profile_key`. Jika inbound sudah lot-aware tetapi tabel balance/rollup belum ikut menambah dimensi lot, FIFO detail akan hilang dan saldo per lot tidak bisa ditelusuri.

## Area kode yang wajib disentuh saat LOT mulai diaktifkan

### 1. Source of truth ledger

- `application/libraries/InventoryLedger.php`
- Tambah payload dan penyimpanan untuk minimal:
  - `lot_id`
  - `lot_no`
  - `lot_date` atau `received_at`
  - optional `expiry_date` bila lot expiry ingin dibedakan dari profile expiry
- Query `FOR UPDATE` warehouse/division balance harus berubah dari key berbasis `profile_key` menjadi key berbasis `profile_key + lot` atau langsung `lot_id`.

### 2. Tabel balance live

- `inv_warehouse_stock_balance`
- `inv_division_stock_balance`
- Dua opsi desain:
  - Tambah dimensi `lot_id` di tabel balance utama.
  - Atau pisahkan ke tabel balance lot, lalu view/list stok tetap mengagregasi ke profile.
- Kalau target FIFO kuat, opsi yang lebih aman adalah menyimpan saldo per lot secara eksplisit.

### 3. Tabel daily rollup

- `inv_warehouse_daily_rollup`
- `inv_division_daily_rollup`
- Jika laporan harian ingin tetap bisa drill-down ke lot, rollup juga harus menyimpan dimensi lot atau memiliki tabel rollup lot terpisah.
- Jika tidak, minimal pastikan agregat harian berasal dari sum per lot, bukan overwrite saldo profile biasa.

### 4. Receipt purchase

- `application/models/Purchase_model.php`
- Titik masuk utama:
  - receipt store
  - auto post receipt on status reached
  - posting ke ledger
- Saat barang masuk, sistem harus membuat/menentukan lot inbound dan menyimpan hubungan PO line -> receipt line -> lot.

### 5. Fulfillment store request

- `application/models/Procurement_model.php`
- Titik masuk utama:
  - `store_request_fulfill()`
  - `reverse_fulfillments_before_void()`
- Saat warehouse mengeluarkan stok ke division, sistem harus consume lot dengan urutan FIFO yang konsisten.
- Reversal void fulfillment harus mengembalikan lot yang benar.

### 6. Query ketersediaan stok untuk routing SR/PO

- `application/models/Procurement_model.php`
- Fungsi yang menghitung stok gudang untuk split route saat ini memakai agregat balance by profile.
- Ini masih aman bila LOT baru dipakai di layer detail tetapi saldo agregat tetap konsisten.
- Namun bila ada rule expiry/FIFO ketat per lot, availability check perlu opsi:
  - sum saldo lot yang eligible
  - exclude lot expired / blocked

### 7. Tampilan stock list dan daily matrix

- `application/models/Purchase_model.php`
- `inventory_warehouse`, `inventory_division`, daily matrix warehouse/division/material
- Halaman ringkasan boleh tetap agregat per profile, tetapi perlu source data dari sum lot.
- Jika user nanti butuh audit detail, tambah drill-down lot per baris profile.

## Status setelah perubahan hari ini

- Verifikasi `division-po-sr/edit` sekarang sudah bisa jadi titik koreksi buyer untuk:
  - merk
  - keterangan
  - harga estimasi
  - suggestion/profile matching
- Draft PO hasil verifikasi sekarang mewarisi:
  - `brand_name`
  - `line_description`
  - `unit_price` estimasi
  - `conversion_factor_to_content` yang benar
- Jalur shortage SR -> PO juga sudah dirapikan agar provenance masuk ke `notes`, bukan ke `line_description`, dan konversi content tidak lagi dipaksa `1`.

## Rekomendasi implementasi LOT

1. Jangan aktifkan LOT hanya di tabel baru inbound tanpa mengubah ledger dan balance.
   Itu akan membuat saldo agregat terlihat benar, tetapi detail FIFO keluar tidak bisa dipertanggungjawabkan.

2. Mulai dari inbound receipt PO.
   Ini titik paling natural untuk membuat lot.

3. Setelah inbound stabil, lanjut ke outbound warehouse -> division dan usage production.
   Dua jalur ini yang menentukan FIFO benar-benar dipakai, bukan hanya disimpan.

4. Pertahankan layar stok utama tetap agregat profile.
   Tambah lot sebagai drill-down, bukan mengganti seluruh UI utama, agar operasional tidak berat.