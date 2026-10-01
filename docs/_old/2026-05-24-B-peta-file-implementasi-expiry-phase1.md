# Peta File Implementasi Tahap 1: Expiry Keluar dari Identity Profile

Tanggal: 2026-05-24

## Fokus tahap 1

Tahap 1 hanya membekukan identity profile agar tidak lagi dibedakan oleh expiry.

Belum termasuk:

- lot-aware fulfillment penuh
- FEFO picking penuh
- migrasi UI expiry requirement
- pemindahan source of truth expiry ke lot secara total

## File yang sudah diubah pada tahap 1 ini

### 1. `application/models/Purchase_model.php`

Status: sudah diubah.

Titik yang diubah:

- `normalizePurchaseCatalogProfileKeys()`
- `resolveCatalogProfileKeyByIdentity()`
- `ensureCatalogProfileFromOpeningIdentity()`
- fallback catalog lookup di `resolveExistingOpeningProfileKey()`

Perubahan inti:

- duplicate identity catalog sekarang dikelompokkan tanpa `expired_date`
- canonical catalog profile key tidak lagi dicocokkan berdasarkan `expired_date`
- hash profile key auto-create dari opening identity tidak lagi memasukkan expiry
- auto-upsert catalog dari opening identity tidak lagi mengupdate `expired_date`
- fallback lookup ke catalog juga tidak lagi memfilter `expired_date`

Dampak:

- catalog dengan nama/merk/UOM sama tetapi expiry beda akan cenderung berkumpul ke identity yang sama
- canonicalisasi profile key baru tidak lagi memecah row hanya karena expiry

## File draft yang ditambahkan untuk rollout tahap 1

### 2. `sql/2026-05-24c_purchase_profile_expiry_phase1_draft.sql`

Status: baru, draft review.

Fungsi:

- backup ringan `mst_purchase_catalog`
- membentuk mapping old/new profile key tanpa expiry
- menampilkan duplicate group yang sebelumnya pecah karena expiry
- menjadi acuan review sebelum rekey lintas tabel

### 3. `docs/2026-05-24-A-desain-expiry-lot-po-sr.md`

Status: sudah ada dari tahap desain.

Fungsi:

- keputusan arsitektur expiry keluar dari profile
- target schema dan flow PO/SR/gudang/divisi
- tahapan migrasi dari phase 1 sampai lot-aware penuh

## File yang perlu disentuh di batch berikutnya

### A. Batch 2: expiry requirement transaksi

#### `application/models/Procurement_model.php`

Alasan:

- division request dan store request masih menyimpan `profile_expired_date`
- perlu digeser menjadi requirement transaksi: `expiry_policy`, `required_expiry_date`, `min_remaining_days`

Area penting:

- save/edit division request
- verify division request
- build SR from division request
- fulfill SR validation

#### `application/controllers/Procurement.php`

Alasan:

- payload request/edit/search perlu disesuaikan dengan kolom expiry requirement baru

#### `application/views/procurement/division_po_sr_form.php`

Alasan:

- form verify/edit harus menampilkan expiry sebagai requirement transaksi, bukan profile identity

#### `application/views/procurement/store_request_form.php`

Alasan:

- jika ada input/edit SR, expiry requirement perlu tampil eksplisit

### B. Batch 3: PO line dan receipt

#### `application/views/purchase/order_create.php`

Alasan:

- line PO perlu menampilkan requirement expiry baru
- jangan lagi menurunkan expiry dari profile catalog sebagai identitas item

#### `application/models/Purchase_model.php`

Alasan:

- save PO line harus menyimpan requirement expiry baru
- receipt harus menganggap expiry sebagai actual receipt attribute

#### `application/controllers/Purchase.php`

Alasan:

- parsing payload JSON PO/receipt perlu mengikuti kolom baru

### C. Batch 4: lot-aware inbound dan fulfillment

#### `application/libraries/InventoryLedger.php`

Alasan:

- perlu mulai menerima `lot_id`, `lot_no`, `expiry_date`, `receipt_date`
- summary stock tetap agregat, tetapi source detail sudah lot-aware

#### `application/models/Procurement_model.php`

Alasan:

- `store_request_fulfill()` dan void reversal harus lot-preserving

#### `application/models/Purchase_model.php`

Alasan:

- receipt PO perlu membuat lot inbound dan mengaitkan ke `pur_purchase_receipt_line.lot_id`

## Risiko yang perlu diingat saat rollout tahap 1

- masih ada kolom legacy seperti `mst_purchase_catalog.expired_date` dan `profile_expired_date` di banyak tabel; tahap 1 belum menghapusnya
- search/list lama bisa masih menampilkan expiry snapshot, walau identity barunya sudah tidak tergantung expiry
- rekey lintas tabel tidak aman jika dijalankan dari SQL mentah tanpa review mapping dan tanpa batch aplikasi

## Urutan rollout yang disarankan

1. Deploy patch PHP tahap 1 lebih dulu.
2. Jalankan draft audit SQL dan review duplicate mapping.
3. Siapkan batch aplikasi untuk rekey/reference remap.
4. Baru lanjut ke batch expiry requirement transaksi.
5. Setelah itu baru aktifkan lot inbound dan fulfillment lot-aware.
