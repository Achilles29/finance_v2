# Audit Legacy Expiry: Adjustment dan Void

## Status saat ini

Batch 2 sekarang sudah mulai menulis `expiry_policy`, `required_expiry_date`, dan `min_remaining_days` pada line transaksi jika kolom schema sudah tersedia. Legacy `profile_expired_date` masih dipertahankan sementara sebagai jembatan kompatibilitas.

## Flow yang sudah ikut membawa field requirement baru

- `application/models/Procurement_model.php`
  - normalisasi line division request
  - normalisasi line store request
  - routing division request -> SR gudang
  - insert/update line `pur_division_request_line`
  - insert/update line `pur_store_request_line`
  - insert line `pur_store_request_fulfillment_line`
- `application/models/Purchase_model.php`
  - build/save/update line PO
  - fallback `profile_key` PO tidak lagi memasukkan expiry requirement
  - upsert catalog purchase tidak lagi menulis `expired_date` dari line PO
- `application/views/purchase/order_create.php`
  - line object edit/create sudah mengirim field requirement transaksi
- `application/views/purchase/stock_adjustment_index.php`
  - adjustment tidak lagi mengirim `profile_expired_date` sebagai payload identity
  - default inbound expiry kosong, harus diisi eksplisit bila adjustment plus membentuk lot baru
- `application/views/purchase/inventory_warehouse_daily_index.php`
  - modal adjustment gudang tidak lagi mengirim `profile_expired_date`
  - kartu profil tidak lagi menampilkan expiry sebagai bagian profil identity
- `application/views/purchase/inventory_material_daily_index.php`
  - modal adjustment divisi tidak lagi mengirim `profile_expired_date`
  - kartu profil tidak lagi menampilkan expiry sebagai bagian profil identity
- `application/views/purchase/stock_warehouse_daily_index.php`
  - grouping parent/child tidak lagi pecah karena `profile_expired_date`
- `application/views/purchase/stock_warehouse_index.php`
  - grouping stok warehouse tidak lagi pecah karena `profile_expired_date`
- `application/views/purchase/stock_division_daily_index.php`
  - grouping parent/child tidak lagi pecah karena `profile_expired_date`
- `application/views/purchase/stock_division_index.php`
  - grouping stok divisi tidak lagi pecah karena `profile_expired_date`
- `application/views/procurement/division_po_sr_form.php`
  - line object sudah membawa field requirement transaksi
- `application/views/procurement/store_request_form.php`
  - line object sudah membawa field requirement transaksi
- `application/views/procurement/store_requests.php`
  - create-modal line object sudah membawa field requirement transaksi
- `application/controllers/Inventory_tools.php`
  - CLI alias baru:
    - `audit_purchase_catalog_expiry_phase1`
    - `normalize_purchase_catalog_expiry_phase1`

## Layar/flow legacy yang masih perlu dibersihkan

### Adjustment
- Adjustment utama sudah dibersihkan dari ketergantungan identity `profile_expired_date`.
- Yang tersisa hanya snapshot display di beberapa dataset stok lama, bukan sebagai key/filter rebuild.

### Void / rebuild / repair
- `application/models/Procurement_model.php`
  - `repair_void_store_request_history()` masih merekonstruksi identitas memakai `profile_expired_date`
  - rollback fulfillment tetap mengandalkan legacy expiry snapshot
- `application/models/Purchase_model.php`
  - repair opening/history dan beberapa query rollup/log masih membaca `profile_expired_date`
  - ini sengaja belum diubah karena menyentuh tabel agregat stok dan butuh aturan merge yang eksplisit

### Display / listing legacy
- `application/views/procurement/store_request_form.php`
  - kolom tabel masih menampilkan legacy `profile_expired_date`
- `application/views/procurement/store_requests.php`
  - kolom tabel masih menampilkan legacy `profile_expired_date`
- `application/views/procurement/division_po_sr_form.php`
  - label/kolom masih berbasis legacy expiry snapshot
- `application/views/purchase/order_create.php`
  - label field masih `expired_date`, walau payload backend sudah dianggap sebagai requirement transaksi

## Catatan rollout

- Jalankan SQL batch 2 lebih dulu: `sql/2026-05-24d_expiry_requirement_batch2_columns.sql`.
- Jika batch 2 sudah telanjur dijalankan sebelumnya, drop kolom katalog pakai file terpisah: `sql/2026-05-24e_drop_catalog_expired_date.sql`.
- Untuk audit phase 1 katalog tanpa mutasi, gunakan CLI:
  - `php index.php inventory_tools audit_purchase_catalog_expiry_phase1 dry_run 1`
- Untuk normalisasi katalog phase 1 setelah review:
  - `php index.php inventory_tools normalize_purchase_catalog_expiry_phase1 dry_run 0`

## Batas aman saat ini

Belum aman melakukan remap tulis langsung ke tabel agregat stok (`inv_*_balance`, `inv_*_daily_rollup`) hanya dengan replace `profile_key`, karena baris beda expiry bisa bertabrakan dan perlu aturan merge qty/nilai/cost. Untuk area itu perlu batch tersendiri.
