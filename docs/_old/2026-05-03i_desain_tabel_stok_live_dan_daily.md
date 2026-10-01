# Desain Tabel Stok Live dan Daily Bulanan
Tanggal: 2026-05-03
Status: Draft final minimal-v1 untuk review sebelum eksekusi SQL
Masuk Tahap: Tahap 6 menuju Tahap 7
Referensi:
- sql/2026-05-03i_purchase_affected_finance_inventory_audit_foundation.sql
- sql/2026-05-03j_inventory_minimal_v1_foundation.sql

---

## Jawaban inti pertanyaan Anda

1. inv_warehouse_stock_balance dan inv_division_stock_balance itu stok live atau bulanan?
- Itu tabel stok live (current position saat ini), bukan tabel bulanan.
- Fungsi utamanya untuk baca saldo cepat sekarang.

2. Untuk tampilan daily bulanan seperti di core, apakah perlu tabel terpisah?
- Ya, tabel harian dipisah fisik:
	- `inv_warehouse_daily_rollup` untuk gudang
	- `inv_division_daily_rollup` untuk divisi
- Sumber kebenaran tetap dari movement log + opening snapshot.

3. Agar tidak kerja dua kali, format apa yang disiapkan sekarang?
- Tetap pertahankan tabel live yang sudah ada.
- Tambahkan tabel minimal: opening snapshot bulanan + daily rollup.
- Tabel monthly summary dan lot ditunda ke batch berikut jika benar-benar sudah dibutuhkan.

4. Apakah terlalu banyak tabel bisa rawan bug?
- Ya, kalau sejak awal dibuat banyak tabel turunan, risiko inkonsistensi naik.
- Minimal-v1 sengaja dibuat ringkas: 1 source of truth + 1 projection harian.

---

## Model data minimal-v1 yang disarankan

## A. Layer live (sudah ada)

1. inv_warehouse_stock_balance
- Saldo live gudang per profil pack.

2. inv_division_stock_balance
- Saldo live per divisi per profil pack.

3. inv_stock_movement_log
- Log mutasi sebagai sumber audit dan source perhitungan harian/bulanan.

## B. Layer periodik (baru)

1. inv_stock_opening_snapshot
- Snapshot stok awal bulan per scope/profile.
- Menjadi anchor untuk perhitungan daily bulanan.

2. inv_warehouse_daily_rollup
- Ringkasan per hari untuk gudang.
- Kolom siap untuk UI: awal, masuk, keluar, terbuang, spoil, waste, penyesuaian, akhir.

3. inv_division_daily_rollup
- Ringkasan per hari untuk divisi.
- Kolom sama dengan gudang, tetapi dibedakan per `division_id`.

Tidak masuk minimal-v1 (ditunda):
- inv_stock_monthly_summary
- inv_stock_lot_balance
- inv_stock_lot_movement

---

## Grain dan kunci data

## 1) Scope
- WAREHOUSE: stok gudang utama.
- DIVISION: stok pada divisi operasional.

## 2) Profile
- Basis profile_key untuk membedakan pack profile.
- Untuk menghindari ketergantungan hash saja, profil juga dijabarkan di kolom:
	- profile_name
	- profile_brand
	- profile_description
	- profile_content_per_buy
	- profile_buy_uom_code
	- profile_content_uom_code

## 3) Domain item/material
- Domain disimpan eksplisit agar satu model bisa melayani gudang item dan bahan baku.

## 4) Dual UOM
- Semua tabel periodik tetap simpan qty_buy dan qty_content.
- Ini penting untuk rekonsiliasi fisik dan konsumsi resep.

---

## Catatan operasional

1. Rebuild bulanan
- Proses rebuild mengambil opening snapshot lalu replay movement log per hari.

2. Generate opname + stok awal
- Menulis ke inv_stock_opening_snapshot (source_type = OPNAME/MANUAL).

3. Performa UI
- UI gudang membaca `inv_warehouse_daily_rollup`.
- UI divisi membaca `inv_division_daily_rollup`.
- Keduanya tidak scan log mentah saat render halaman.

4. Audit
- Jika ada anomali, drill-down ke inv_stock_movement_log.

---

## Rekomendasi implementasi bertahap

1. Eksekusi fondasi minimal-v1 dulu (opening snapshot + daily rollup gudang/divisi).
2. Buat service rebuild monthly -> daily rollup.
3. Integrasikan posting receipt purchase ke live balance + movement log.
4. Tambah endpoint read untuk UI matrix daily.
5. Setelah stabil 1-2 siklus bulan, baru evaluasi perlu tidaknya lot/monthly summary fisik.

## Perilaku baris harian (agar hemat dan konsisten)

1. Daily table bukan tabel live.
2. Daily table adalah tabel periodik per bulan (`month_key`) dan per hari (`movement_date`).
3. Baris harian di-upsert per kombinasi unik (hari + profile + scope/division), bukan baris baru per transaksi.
4. Detail per transaksi tetap di `inv_stock_movement_log`.

## Aturan profile consistency dari purchase

1. Data profil dari purchase harus terbawa konsisten ke live balance, movement log, dan daily rollup.
2. Jika profile baru (kombinasi profil berbeda), buat baris baru.
3. Jika profile sama, update saldo pada baris yang sama (upsert), tidak menambah baris baru.
