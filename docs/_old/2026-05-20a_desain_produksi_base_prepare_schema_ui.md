# Desain Produksi Base/Prepare: Schema Database + Skema UI Operasional
**Tanggal:** 2026-05-20  
**Status:** Draft revisi untuk verifikasi (belum implementasi SQL/controller/view)

## 1) Konteks dan Tujuan
Dokumen ini menyiapkan fondasi Tahap 8 (Produksi & COGS) untuk modul Base/Prepare di repo `finance`, dengan adopsi proses bisnis dari `core` (`prd_component` dan turunannya), tetapi disesuaikan dengan arsitektur, keterbacaan UI, dan efisiensi alur kerja di `finance`.

Target halaman yang dicakup:
1. Kategori Base/Prepare
2. Master Base/Prepare
3. Resep/Formula Base/Prepare
4. Stok Base/Prepare: saldo, keluar-masuk, matrix, daily
5. Halaman terkait dari pola `core`: opening, adjustment, monthly opname/opening, batch produksi

## 2) Prinsip Adopsi dari Core ke Finance
1. Tetap pisah antara master dan operasional.
2. Master tetap memakai tabel existing `mst_component`, `mst_component_category`, `mst_component_formula`.
3. Operasional produksi memakai tabel `inv_component_*` baru agar jejak transaksi jelas dan tidak mencampur dengan master.
4. Nama FK mengikuti skema `finance`:
   - Satuan ke `mst_uom`
   - Komponen ke `mst_component`
   - Divisi operasional ke `mst_operational_division`
   - User actor ke `org_employee`
5. Pola snapshot mengikuti inventory existing (`daily rollup`, `monthly opname`, `monthly opening`) agar konsisten dengan UI stok material/gudang.

## 2.1 Keputusan Prefix Tabel Operasional
Kenapa tidak pakai `prd_` di tahap ini:
1. Di `finance`, domain stok dan mutasi saat ini dominan di prefix `inv_`.
2. Modul Base/Prepare untuk fase ini berfokus pada inventory movement yang audit-heavy.
3. Konsistensi naming memudahkan tim membaca relasi antar tabel stok.

Keputusan revisi:
1. Master tetap `mst_component*`.
2. Operasional base/prepare memakai prefix `inv_component_*`.
3. Bila nanti domain produksi produk jadi berkembang luas, tabel khusus produk jadi tetap bisa memakai `prd_*` terpisah.

## 3) Cakupan Schema yang Diusulkan

### 3.1 Tabel yang sudah ada (dipakai ulang)
1. `mst_component_category` (kategori)
2. `mst_component` (master BASE/PREPARE)
3. `mst_component_formula` (resep/formula line)

Catatan: `mst_component_formula` di `finance` sudah line-based (per row formula), sehingga untuk tahap ini tidak perlu memecah ke header+line seperti `core.prd_component_formula`.

### 3.2 Tabel baru operasional produksi (wajib)
1. `inv_component_stock_balance`
- Fungsi: saldo live per komponen-lokasi-divisi.
- Kunci unik: `(location_type, division_id, component_id, uom_id)`.
- Kolom inti: `qty_on_hand`, `avg_cost`, `total_value`, `last_txn_at`.

2. `inv_component_movement_log`
- Fungsi: ledger transaksi komponen (setara konsep `prd_component_txn` di core).
- Kolom inti:
  - Header transaksi: `movement_no`, `movement_date`, `movement_datetime`
  - Scope: `location_type`, `division_id`
  - Entitas: `component_id`, `uom_id`
  - Mutasi: `movement_type`, `qty_in`, `qty_out`, `unit_cost`, `total_cost`
  - Jejak sumber: `source_module`, `source_table`, `source_id`, `source_line_id`
  - Snapshot lot ringan: `lot_no_snapshot`, `received_date_snapshot`
- Enum `movement_type` usulan awal:
  - `OPENING`, `PRODUCTION_IN`, `PRODUCTION_OUT`, `TRANSFER_IN`, `TRANSFER_OUT`, `USAGE`, `WASTE`, `SPOIL`, `ADJUSTMENT_PLUS`, `ADJUSTMENT_MINUS`, `VOID_REVERSE`.

3. `inv_component_batch`
- Fungsi: header batch produksi base/prepare.
- Kolom inti: `batch_no`, `batch_date`, `location_type`, `division_id`, `component_id`, `output_qty`, `output_uom_id`, `total_input_cost`, `unit_cost`, `status`, `notes`, `created_by`.
- Status: `DRAFT`, `POSTED`, `VOID`.

4. `inv_component_batch_input`
- Fungsi: detail input bahan saat produksi batch.
- Kolom inti:
  - `batch_id`, `line_no`, `source_kind` (`MATERIAL`/`COMPONENT`)
  - referensi input (`material_id`/`component_id`)
  - `qty`, `uom_id`, `unit_cost`, `total_cost`, `notes`.

5. `inv_component_adjustment`
- Fungsi: header dokumen penyesuaian stok base/prepare.

6. `inv_component_adjustment_line`
- Fungsi: line penyesuaian.
- Kolom inti: `available_qty`, `qty_spoil`, `qty_waste`, `qty_adjust_pos`, `qty_adjust_neg`, `note`.

7. `inv_component_opening`
- Fungsi: dokumen input stok awal (manual opening bulan/tahap awal).

8. `inv_component_opening_line`
- Fungsi: line opening per komponen.
- Kolom inti: `opening_qty`, `unit_cost`, `total_value`, `note`.

9. `inv_component_daily_rollup`
- Fungsi: tabel matrix harian base/prepare (setara pola `inv_*_daily_rollup`).
- Kolom inti:
  - Dimensi: `month_key`, `movement_date`, `location_type`, `division_id`, `component_id`, `uom_id`
  - Angka harian: `opening_qty`, `in_qty`, `out_qty`, `waste_qty`, `spoil_qty`, `adjustment_qty`, `closing_qty`
  - Nilai: `avg_cost`, `total_value`, `mutation_count`, `last_movement_at`, `rebuild_batch_no`.

10. `inv_component_monthly_opname`
- Fungsi: closing bulanan per komponen-lokasi-divisi.

11. `inv_component_monthly_opening`
- Fungsi: opening bulanan hasil carry forward dari opname.

### 3.3 Tabel opsional tahap lanjutan (disiapkan desain, implementasi belakangan)
1. `inv_component_stock_lot`
2. `inv_component_stock_lot_usage`

Status: opsional; aktifkan saat kebutuhan FIFO/trace lot sudah final agar fase awal tidak terlalu berat.

## 4) Relasi Antar Tabel (Ringkas)
1. `mst_component (1) -> (N) inv_component_stock_balance`
2. `mst_component (1) -> (N) inv_component_movement_log`
3. `inv_component_batch (1) -> (N) inv_component_batch_input`
4. `inv_component_adjustment (1) -> (N) inv_component_adjustment_line`
5. `inv_component_opening (1) -> (N) inv_component_opening_line`
6. Snapshot tabel (`daily_rollup`, `monthly_opname`, `monthly_opening`) refer ke `mst_component`, `mst_uom`, `mst_operational_division`.

## 5) Alur Operasional yang Diusulkan

### 5.1 Opening
1. User input opening (header+line).
2. Sistem insert dokumen opening.
3. Sistem posting ke `inv_component_movement_log` (`movement_type=OPENING`).
4. Sistem upsert `inv_component_stock_balance`.

### 5.2 Batch Produksi Base/Prepare
1. User pilih komponen output + lokasi + tanggal + qty output.
2. Sistem tarik formula dari `mst_component_formula`.
3. User konfirmasi/ubah qty input aktual.
4. Saat `POSTED`:
   - Input berkurang (material/component) sesuai sumber.
   - Output bertambah ke stok komponen.
   - Ledger masuk ke `inv_component_movement_log` untuk IN/OUT.
   - Saldo live update di `inv_component_stock_balance`.

### 5.3 Adjustment
1. User buat dokumen adjustment.
2. Per line dapat isi spoil/waste/plus/minus.
3. Posting ke movement log sesuai jenis, lalu update saldo.

### 5.4 Daily Matrix
1. Harian dibaca dari `inv_component_daily_rollup`.
2. Jika belum ada rollup, jalankan rebuild dari movement log per bulan aktif.
3. UI matrix mengikuti pola halaman daily material existing (disederhanakan agar lebih cepat dipakai).

### 5.5 Monthly Opname dan Opening
1. Tutup bulan: generate `inv_component_monthly_opname` dari saldo akhir bulan.
2. Awal bulan berikutnya: generate `inv_component_monthly_opening`.
3. Opening bulan jadi basis perhitungan daily bulan berjalan.

## 6) Skema UI Operasional (Blueprint)

### 6.0 Prinsip UI Revisi (bukan copy pola core)
1. Gunakan pola workbench 3 level: `Ringkasan -> Tabel utama -> Panel detail cepat`.
2. Kurangi halaman yang terlalu pecah; utamakan aksi inline modal/drawer untuk operasi harian.
3. Filter sticky di atas, data refresh cepat tanpa alur form panjang berulang.
4. Fokus keterbacaan: angka penting fixed column, status pakai badge warna konsisten.
5. Audit ketat tetap ada: setiap aksi tulis menampilkan `doc no`, `actor`, `timestamp`, dan link log.

### 6.1 Master Kategori Base/Prepare
- Tujuan: kelola kategori untuk `mst_component_category`.
- Fitur:
  1. List + search
  2. Add/Edit/Delete
  3. Aktif/nonaktif
  4. Filter parent/scope bila diperlukan

### 6.2 Master Base/Prepare
- Tujuan: kelola `mst_component`.
- Fitur:
  1. List + filter: jenis (BASE/PREPARE), divisi produk, divisi operasional, kategori, status
  2. Form create/edit
  3. Toggle aktif
  4. Link cepat ke resep dan stok harian komponen

### 6.3 Resep Base/Prepare
- Tujuan: kelola formula line (`mst_component_formula`).
- Fitur:
  1. Halaman hub daftar komponen
  2. Detail formula per komponen
  3. Tambah line sumber MATERIAL/COMPONENT
  4. Edit/hapus line
  5. Validasi anti-loop sederhana untuk component->component

### 6.4 Stok Base/Prepare (Live)
- Tujuan: lihat saldo real-time.
- Sumber: `inv_component_stock_balance`.
- Fitur:
  1. Filter lokasi, divisi, jenis komponen, kategori, keyword
  2. Kolom saldo qty, avg cost, total value
  3. Tombol drilldown ke mutasi

### 6.5 Keluar/Masuk (Ledger Mutasi)
- Tujuan: audit transaksi.
- Sumber: `inv_component_movement_log`.
- Fitur:
  1. Filter tanggal, lokasi, komponen, movement_type, source_module
  2. Export CSV
  3. Drilldown ke dokumen sumber (opening/adjustment/batch)

### 6.6 Matrix Daily Base/Prepare
- Tujuan: tampilan matriks per tanggal satu bulan.
- Sumber: `inv_component_daily_rollup`.
- Fitur:
  1. Filter bulan, lokasi, jenis, kategori
  2. Tabel per komponen dengan kolom tanggal
  3. Aksi cepat tambah adjustment/produksi dari sel tanggal
  4. Side panel detail transaksi per sel

### 6.7 Daily Detail Base/Prepare
- Tujuan: detail mutasi harian per komponen.
- Sumber: gabungan daily rollup + movement log.
- Fitur:
  1. Ringkasan opening/in/out/adjust/closing
  2. Daftar dokumen penyusun hari tersebut

### 6.8 Opening & Adjustment (Form Operasional)
- Halaman opening:
  1. Index dokumen opening
  2. Create/edit/delete sebelum posted
- Halaman adjustment:
  1. Index dokumen adjustment
  2. Create/edit/delete
  3. Validasi qty negatif
  4. Template input cepat (paste baris)

### 6.9 Monthly Opname & Opening
- Tujuan: tutup buka bulan untuk komponen.
- Fitur:
  1. Tab `ledger` / `opname` / `opening`
  2. Tombol `Generate Opname` dan `Generate Opening Bulan Berikutnya`
  3. Gate: bulan hanya bisa diproses jika prasyarat terpenuhi

### 6.10 Batch Produksi (Wizard Ringkas)
- Langkah 1: pilih output, tanggal, lokasi, qty.
- Langkah 2: review konsumsi formula vs stok tersedia.
- Langkah 3: post batch + tampilkan log transaksi yang terbentuk.

## 7) Mapping Route (Usulan)
1. `/master/component-category` (existing, diperkuat)
2. `/master/component` (existing, diperkuat)
3. `/master/relation/component-formula` (existing, diperkuat)
4. `/production/component-stock`
5. `/production/component-movements`
6. `/production/component-daily`
7. `/production/component-openings`
8. `/production/component-adjustments`
9. `/production/component-batches`
10. `/production/component-monthly`

## 8) Urutan Implementasi Setelah Verifikasi
1. SQL schema operasional `inv_component_*` + seed menu/permission.
2. Model ledger posting terpusat (service/library agar writer tunggal).
3. Halaman stok live + movement log (read-only dulu).
4. Halaman opening + adjustment.
5. Halaman batch produksi + posting.
6. Halaman daily matrix + monthly opname/opening.

## 9) Poin yang Perlu Approval
1. Tetap gunakan master existing (`mst_component*`) dan tambah operasional di `inv_component*` (bukan migrasi ulang ke struktur master core).
2. Tahap awal tanpa lot tracking detail (`inv_component_stock_lot*`) agar delivery cepat; lot bisa ditambah tahap hardening.
3. Lokasi operasional tetap pakai enum: `BAR`, `KITCHEN`, `BAR_EVENT`, `KITCHEN_EVENT` (konsisten core).
4. Daily matrix komponen mengikuti pola inventory material (`daily_rollup`) agar UI konsisten.
5. UI dibuat ulang dengan pola workbench ringkas (bukan copy tampilan/alur core), sambil mempertahankan log audit lengkap.

## 10) POS/Kasir Performance Guardrails (Wajib)
Tujuan: saat sinkron stok live ke POS/Kasir, loading kasir tetap ringan dan tidak mengganggu transaksi.

1. Sumber baca kasir hanya tabel saldo ringkas.
- Kasir membaca stok dari `inv_component_stock_balance` (dan tabel balance lain yang setara), bukan dari tabel log mutasi mentah.

2. Jangan hitung ulang stok saat request kasir.
- Perubahan stok dihitung saat posting transaksi (write-time), bukan saat halaman kasir dibuka (read-time).

3. Query kasir dibatasi per lokasi aktif.
- Semua query stok kasir wajib filter `location_type` + `division_id/outlet` agar data yang dibaca kecil.

4. Index wajib untuk jalur baca kasir.
- Pastikan index komposit pada kolom filter utama kasir, minimal:
  - `(location_type, division_id, component_id)`
  - unique key saldo aktif per lokasi-komponen.

5. Cache pendek untuk tampilan kasir.
- Gunakan cache singkat (5-15 detik) untuk badge/info stok di UI kasir.
- Invalidasi cache saat ada posting transaksi kasir/produksi/adjustment yang relevan.

6. Proses berat dipindah ke background.
- Rebuild daily rollup, generate opname, dan rekalkulasi bulanan tidak boleh berjalan di request kasir.

7. Fallback aman saat lock kontensi.
- Jika update saldo sedang lock, kasir tetap lanjut transaksi dengan mekanisme retry singkat di worker posting, bukan membuat UI freeze.

8. Monitoring performa endpoint kasir.
- Tetapkan target SLA internal endpoint stok kasir (misalnya p95 < 200ms di jaringan lokal).
- Log query lambat dan siapkan alert sederhana untuk degradasi performa.
