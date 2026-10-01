# Rencana Store Request (Schema + UI)

Tanggal: 2026-05-14  
Status: Draft untuk review sebelum eksekusi SQL + CRUD

---

## 1) Prinsip Desain

Store Request di `finance` diposisikan sebagai proses **permintaan barang dari gudang ke divisi** (regular/event) dengan prinsip:

1. Identitas stok wajib mengikuti `profile_key` (bukan hanya item/material).
2. Dual UOM wajib terbawa end-to-end:
   - `buy_uom` (kemasan beli)
   - `content_uom` (isi dasar)
3. Mutasi harus seimbang:
   - Gudang berkurang (`TRANSFER_OUT`)
   - Divisi bertambah (`TRANSFER_IN`)
4. Data profil ikut terbawa ke divisi:
   - nama, brand, deskripsi, isi/kemasan, **expired_date/profile_expired_date**
5. Void wajib rollback mutasi (stok balik ke gudang, keluar dari divisi).

Desain ini memanfaatkan fondasi existing:
- `inv_warehouse_stock_balance`
- `inv_division_stock_balance` (sudah support `destination_type`)
- `inv_stock_movement_log`
- `InventoryLedger` (scope warehouse/division + movement type)

---

## 2) Rencana Tabel

## 2.1 `pur_store_request` (header)

Fungsi: dokumen permintaan utama.

Kolom inti:
- `id` BIGINT PK
- `sr_no` VARCHAR(50) UNIQUE
- `request_date` DATE
- `needed_date` DATE NULL
- `request_division_id` BIGINT FK `mst_operational_division`
- `destination_type` ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER')
- `status` ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','PARTIAL_FULFILLED','FULFILLED','VOID') default `DRAFT`
- `notes` VARCHAR(255) NULL
- `created_by`, `approved_by`, `voided_by` BIGINT NULL
- `approved_at`, `voided_at` DATETIME NULL
- `void_reason` VARCHAR(255) NULL
- `created_at`, `updated_at`

Index:
- unique `sr_no`
- index `request_date`, `status`
- index `(request_division_id, destination_type)`

---

## 2.2 `pur_store_request_line` (line permintaan)

Fungsi: item/profil yang diminta.  
Catatan: untuk menjaga kontrol kemasan + gramasi, line menyimpan snapshot lengkap profile.

Kolom inti:
- `id` BIGINT PK
- `store_request_id` BIGINT FK `pur_store_request`
- `line_no` INT
- `line_kind` ENUM('ITEM','MATERIAL') default `ITEM`
- `item_id` BIGINT NULL FK `mst_item`
- `material_id` BIGINT NULL FK `mst_material`
- `profile_key` CHAR(64) NOT NULL
- `profile_name` VARCHAR(150) NULL
- `profile_brand` VARCHAR(120) NULL
- `profile_description` VARCHAR(255) NULL
- `profile_expired_date` DATE NULL
- `buy_uom_id` BIGINT NOT NULL FK `mst_uom`
- `content_uom_id` BIGINT NOT NULL FK `mst_uom`
- `profile_content_per_buy` DECIMAL(18,6) NOT NULL
- `profile_buy_uom_code` VARCHAR(40) NULL
- `profile_content_uom_code` VARCHAR(40) NULL
- `qty_buy_requested` DECIMAL(18,4) NOT NULL default 0
- `qty_content_requested` DECIMAL(18,4) NOT NULL default 0
- `qty_buy_approved` DECIMAL(18,4) NOT NULL default 0
- `qty_content_approved` DECIMAL(18,4) NOT NULL default 0
- `qty_buy_fulfilled` DECIMAL(18,4) NOT NULL default 0
- `qty_content_fulfilled` DECIMAL(18,4) NOT NULL default 0
- `line_status` ENUM('OPEN','PARTIAL','DONE','CANCELLED') default `OPEN`
- `notes` VARCHAR(255) NULL
- `created_at`, `updated_at`

Aturan sinkron `item_id/material_id`:
- `line_kind='ITEM'` => `item_id` wajib isi, `material_id` boleh null (contoh barang operasional: Baygon).
- `line_kind='MATERIAL'` => `material_id` wajib isi.
- Bila mapping `item -> material` tersedia di master (`mst_material_item_source`), sistem menyimpan keduanya agar jejak konversi konsumsi/produksi tetap konsisten.
- Bila mapping tidak ada, sistem tetap valid untuk jalur ITEM-only (non-bahan baku).

Index:
- unique `(store_request_id, line_no)`
- index `(profile_key, buy_uom_id, content_uom_id)`

---

## 2.3 `pur_store_request_approval` (riwayat approval)

Fungsi: jejak siapa approve/reject/override.

Kolom inti:
- `id` BIGINT PK
- `store_request_id` BIGINT FK
- `action` ENUM('SUBMIT','APPROVE','REJECT','OVERRIDE_APPROVE','VOID')
- `actor_user_id` BIGINT NULL FK `auth_user`
- `actor_name_snapshot` VARCHAR(150) NULL
- `notes` VARCHAR(255) NULL
- `created_at` DATETIME

Index:
- `(store_request_id, created_at)`

---

## 2.4 `pur_store_request_fulfillment` (header serah barang)

Fungsi: dokumen realisasi keluar gudang (1 SR bisa beberapa fulfillment/parsial).

Kolom inti:
- `id` BIGINT PK
- `store_request_id` BIGINT FK
- `fulfillment_no` VARCHAR(60) UNIQUE
- `fulfillment_date` DATE
- `status` ENUM('DRAFT','POSTED','VOID') default `DRAFT`
- `notes` VARCHAR(255) NULL
- `posted_by`, `voided_by` BIGINT NULL
- `posted_at`, `voided_at` DATETIME NULL
- `void_reason` VARCHAR(255) NULL
- `created_at`, `updated_at`

---

## 2.5 `pur_store_request_fulfillment_line` (line realisasi mutasi)

Fungsi: qty aktual yang diposting ke inventory ledger.

Kolom inti:
- `id` BIGINT PK
- `fulfillment_id` BIGINT FK `pur_store_request_fulfillment`
- `store_request_line_id` BIGINT FK `pur_store_request_line`
- `item_id`, `material_id`
- `profile_key`
- `profile_name`, `profile_brand`, `profile_description`, `profile_expired_date`
- `buy_uom_id`, `content_uom_id`
- `profile_content_per_buy`, `profile_buy_uom_code`, `profile_content_uom_code`
- `qty_buy_posted` DECIMAL(18,4) NOT NULL
- `qty_content_posted` DECIMAL(18,4) NOT NULL
- `unit_cost_snapshot` DECIMAL(18,6) NOT NULL default 0
- `notes` VARCHAR(255) NULL
- `created_at`, `updated_at`

Constraint penting:
- Unique `(fulfillment_id, store_request_line_id)` agar satu line tidak double post dalam dokumen yang sama.

---

## 2.6 Relasi ke PO (opsional lanjutan)

Jika diminta “buat PO dari SR”:
- Tambah nullable `source_store_request_id` di `pur_purchase_order`
- Tambah nullable `source_store_request_line_id` di `pur_purchase_order_line`

Ini opsional fase 2 Store Request, bukan blocker fase awal mutasi gudang->divisi.

---

## 2.7 Catatan desain: `event_name` tidak dipakai

`event_name` sengaja dihapus dari SR karena:
1. Pembedaan regular vs event cukup melalui `destination_type` (`BAR` vs `BAR_EVENT`, `KITCHEN` vs `KITCHEN_EVENT`).
2. Analisa HPP event direncanakan di layer produksi/resep/costing, bukan di identitas dokumen SR/PO.
3. Mengurangi noise field bebas ketik yang rawan tidak konsisten.

---

## 3) Aturan Posting Inventori

Saat fulfillment `POSTED`:

1. Validasi stok gudang by exact identity:
   - `item/material`
   - `profile_key`
   - `buy_uom_id`
   - `content_uom_id`
2. Jika stok cukup:
   - Post `TRANSFER_OUT` scope `WAREHOUSE`
   - Post `TRANSFER_IN` scope `DIVISION` + `division_id` + `destination_type`
3. Bawa snapshot profile termasuk `profile_expired_date`.
4. Update qty fulfilled pada `pur_store_request_line`.
5. Update status header:
   - semua line full => `FULFILLED`
   - sebagian => `PARTIAL_FULFILLED`

Saat fulfillment `VOID`:
- Reversal simetris:
  - warehouse `TRANSFER_IN`
  - division `TRANSFER_OUT`
- qty fulfilled dikurangi balik.
- status SR dihitung ulang (`APPROVED`/`PARTIAL_FULFILLED`/`FULFILLED`).

---

## 4) Rencana UI

UI mengikuti pola modul existing (`users/material/payroll`):
- card ringkasan
- filter + search ajax
- pagination konsisten
- kolom aksi icon-only + tooltip
- tombol submit pakai spinner loading

## 4.0 Prinsip UX Modul Terpadu (SR + PO)

Keputusan desain:
1. Tidak dipisah menu Purchase vs Divisi seperti core lama.
2. Satu modul transaksi, dibedakan hak akses & state machine.
3. User divisi fokus di request; purchase fokus di verifikasi, modifikasi, fulfill, dan PO lanjutan.

State utama SR terpadu:
- `DRAFT` -> `SUBMITTED` -> (`APPROVED` / `REJECTED`)
- `APPROVED` -> (`PARTIAL_FULFILLED` / `FULFILLED`)
- semua state non-final bisa `VOID` (dengan guard sesuai policy)

Hak akses yang disarankan:
- `DIVISION_CREW`
: create/edit own request sampai `SUBMITTED`, lihat status, cancel sebelum diproses.
- `PURCHASE_ADMIN`
: review, edit line saat verifikasi, approve/reject, fulfill parsial/full, void.
- `SUPERADMIN/CEO`
: full override + audit visibility.

## 4.1 Halaman Utama: `/store-requests`

Tab:
1. `Daftar Request`
2. `Proses Fulfillment`
3. `Riwayat Mutasi SR`
4. `Antrian Verifikasi Purchase`

Filter utama:
- Periode tanggal
- Divisi
- Tujuan (`BAR/KITCHEN/BAR_EVENT/KITCHEN_EVENT/OFFICE/OTHER`)
- Status
- Keyword (`sr_no`, nama profil, peminta)

Card ringkasan:
- Total request periode
- Menunggu approve
- Menunggu fulfillment
- Selesai
- Void

Tabel Daftar Request:
- No SR, tanggal, divisi, tujuan, status, total line, peminta, aksi

Aksi:
- Detail
- Verifikasi (edit/modifikasi line + approve/reject)
- Fulfillment
- Void (hanya jika eligible)
- Cetak dokumen SR

---

## 4.2 Halaman Buat Request: `/store-requests/create`

Form header:
- Tanggal request
- Tanggal butuh
- Divisi
- Tujuan (`BAR/KITCHEN/OFFICE/BAR_EVENT/KITCHEN_EVENT`)
- Catatan

Grid line (excel-like ringan):
- Cari profile stok gudang (ajax)
- Tampilkan:
  - profile name/brand/desc
  - exp date
  - UOM beli, UOM isi, konversi
  - stok gudang saat ini (buy/content)
- Input qty request di buy/content (auto sync konversi)

Guarding:
- Tidak boleh submit qty 0
- Profile/UOM wajib konsisten

---

## 4.3 Halaman Detail Request: `/store-requests/{id}`

Section:
1. Header + badge status
2. Timeline action (submit/approve/reject/void)
3. Line detail:
   - requested/approved/fulfilled
   - profile + exp
4. Panel fulfillment history:
   - no fulfillment, tanggal, status, total qty, aksi detail/void

---

## 4.4 Halaman Fulfillment (admin gudang)

Konsep:
- List request yang status `APPROVED` / `PARTIAL_FULFILLED`
- Pilih request, edit qty realisasi per line
- Tampilkan live stock gudang saat ini per profile
- Tombol `Post Fulfillment`

Saat post sukses:
- tampil nomor fulfillment
- tampil ringkas mutasi: gudang minus, divisi plus

---

## 4.5 Employee/Division Portal (fase berikut)

`/my/division-requests`:
- Create request
- Pantau status
- Lihat histori penerimaan divisi

Catatan:
- Endpoint dan tampilan bisa tetap satu controller/view family dengan mode role-aware.
- Tidak perlu fork modul terpisah selama ACL + query scoping sudah kuat.

---

## 5) Catatan Integrasi Event vs Reguler

Karena stok divisi sudah support `destination_type`, request event/reguler bisa dipisah jelas:
- Reguler: `BAR`, `KITCHEN`, `OFFICE`
- Event: `BAR_EVENT`, `KITCHEN_EVENT`

Ini menjaga kontrol stok per lokasi operasional tanpa menambah tabel stok baru.

---

## 6) Urutan Implementasi Disarankan

1. SQL schema (`pur_store_request*`)
2. Menu + permission page seed
3. CRUD request (header + line + approval)
4. Fulfillment posting ke `InventoryLedger`
5. Void + reversal + audit
6. UI polishing (filter/search/pagination/action icon)
7. Laporan rekap Store Request

---

## 7) Keputusan Desain Penting

1. `profile_key` wajib di line request dan line fulfillment.
2. Dual UOM disimpan sebagai data transaksi, bukan hitung on-the-fly saat laporan.
3. Mutasi stok tetap lewat satu pintu `InventoryLedger`.
4. Void bukan hapus data, tetapi reversal mutasi + status `VOID`.
