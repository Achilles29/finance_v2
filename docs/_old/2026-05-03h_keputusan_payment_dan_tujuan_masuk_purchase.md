# Tahap 6H - Keputusan Payment dan Tujuan Masuk Purchase
Tanggal: 2026-05-03
Status: DECIDED + DB FOUNDATION READY
Masuk Tahap: Tahap 6 - Purchase
Referensi:
- docs/2026-05-03f_tahap6_purchase_foundation.md
- sql/2026-05-03h_purchase_payment_receipt_foundation.sql
- sql/2026-05-03i_purchase_affected_finance_inventory_audit_foundation.sql

---

## Jawaban singkat pertanyaan Anda

1. Purchase perlu metode pembayaran?
Ya, perlu dari sekarang agar PO bisa lanjut ke lifecycle yang realistis (tunai, transfer, giro, tempo).

2. Harus buat modul keuangan penuh dulu?
Tidak wajib. Cukup buat fondasi payment di domain purchase dulu, lalu posting akuntansi penuh disambungkan di Tahap 10.

3. Karena PO bisa masuk ke gudang/divisi, perlu DB sekarang?
Ya, perlu. Fondasi penerimaan harus disiapkan sekarang agar alur masuk barang konsisten sejak awal.

---

## Keputusan arsitektur

## A. Payment dipisah 2 level

1. mst_payment_method
- Master metode pembayaran.
- Menyimpan aturan seperti butuh due date atau reference no.

2. pur_purchase_payment_plan
- Rencana pembayaran per PO.
- Bisa DP, partial, atau full.
- Menyimpan planned amount, paid amount, due date, status.

Catatan:
- Tabel ini belum posting jurnal akuntansi.
- Integrasi jurnal dilakukan di Tahap 10.

Tambahan penguatan (2026-05-03):
- `fin_company_account` sebagai akun perusahaan tunggal (BANK/EWALLET/CASH).
- `pur_payment_channel` agar metode bayar purchase mengikuti akun yang benar-benar dimiliki perusahaan.

---

## B. Tujuan masuk PO dipastikan lewat receipt

1. pur_purchase_receipt
- Header penerimaan barang.
- Menyimpan destination_type (GUDANG/BAR/KITCHEN/dll) dan destination_division_id bila perlu.

2. pur_purchase_receipt_line
- Detail penerimaan per line PO.
- Tetap simpan dual-uom:
  - qty_buy_received + buy_uom_id
  - qty_content_received + content_uom_id
- Simpan juga conversion_factor_to_content, brand_name, line_description, profile_key.

Tujuan:
- Rekonsiliasi fisik botol/dus tetap bisa.
- Perhitungan konsumsi isi (ml/gram/pcs) tetap akurat.

Tambahan penguatan (2026-05-03):
- `inv_warehouse_stock_balance` sebagai saldo stok gudang.
- `inv_division_stock_balance` sebagai saldo stok per divisi.
- `inv_stock_movement_log` sebagai log mutasi stok.
- `aud_transaction_log` sebagai audit trail lintas modul.

---

## Kenapa ini efisien dan profesional

1. Tidak menunda purchase karena menunggu modul keuangan penuh.
2. Tidak memaksa desain inventori Tahap 7 dibuat buru-buru.
3. Data transaksi sejak awal sudah siap di-link ke:
- posting stok Tahap 7
- jurnal/AP Tahap 10

---

## Batas implementasi batch ini

Termasuk:
- DDL payment method + payment plan
- DDL receipt header + receipt line
- seed default payment method
- DDL akun perusahaan + payment channel
- DDL saldo stok gudang/divisi + log mutasi
- DDL audit transaksi

Belum termasuk:
- UI payment plan
- workflow approval pembayaran
- posting jurnal akuntansi final
- posting ledger stok final
