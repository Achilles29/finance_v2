# Keputusan Final Item-Centric
**Tanggal:** 2026-06-04  
**Status:** Draft keputusan final untuk dikunci sebelum implementasi fase 1 dan 2

## 1. Tujuan
Dokumen ini merangkum keputusan inti yang harus dianggap sebagai acuan bersama sebelum implementasi refactor item-centric dimulai.

Dokumen pendukung:
1. [2026-06-04c_konsep_item_centric_inventory_procurement_production_pos.md](/c:/xampp/htdocs/finance/docs/2026-06-04c_konsep_item_centric_inventory_procurement_production_pos.md)
2. [2026-06-04d_mapping_refactor_item_centric_per_tabel_dan_migrasi.md](/c:/xampp/htdocs/finance/docs/2026-06-04d_mapping_refactor_item_centric_per_tabel_dan_migrasi.md)

## 2. Keputusan Inti
### 2.1 Canonical identity transaksi
Keputusan:
1. `item_id` menjadi identitas transaksi utama untuk seluruh writer baru.
2. Writer baru tidak lagi membuat canonical transaction row berbasis `material_id`.

Implikasi:
1. PO, receipt, SR, fulfillment, adjustment, opening, POS stock commit, dan movement baru harus berpikir item-centric.

### 2.2 Peran `material_id`
Keputusan:
1. `material_id` tetap dipertahankan.
2. `material_id` berperan sebagai:
   - metadata bahan baku
   - tag UI
   - relasi recipe
   - compatibility lama
   - jembatan migrasi

Keputusan tegas:
1. `material_id` bukan lagi identity transaksi utama untuk data baru.
2. `material_id` tetap ikut disimpan di line transaksi baru sebagai snapshot/tagging bila item tersebut bahan baku.

### 2.3 Perbedaan produksi vs operasional
Keputusan:
1. `usage_purpose` menjadi pembeda flow stok.
2. `Persediaan Produksi` boleh mengalir ke stok divisi produksi.
3. `Kebutuhan Operasional` tidak membentuk stok divisi produksi.

Implikasi:
1. Perbedaan alur tidak lagi ditentukan oleh dual identity `ITEM` vs `MATERIAL`.
2. `line_kind` dan `stock_domain` tidak lagi menjadi pengambil keputusan canonical untuk writer baru.
3. Kolom enum legacy tetap dipertahankan sementara untuk kompatibilitas baca, audit, dan migrasi bertahap.

### 2.4 Recipe phase transisi
Keputusan:
1. Recipe tetap boleh merujuk `material_id` pada fase transisi.
2. Resolver recipe wajib selalu bisa memetakan ke canonical `item_id`.

Implikasi:
1. Kita tidak perlu membongkar recipe schema besar-besaran di fase awal.
2. Tetapi service resolver harus menjadi jembatan resmi.

### 2.5 HPP monitoring vs HPP actual commit
Keputusan:
1. `Monitoring HPP live` boleh tetap berupa estimated live cost.
2. `Actual commit cost` tetap mengikuti cost riil saat posting FIFO/ledger.

Implikasi:
1. Kita tidak memaksa monitoring dan actual commit menjadi angka yang identik setiap saat.
2. Yang harus identik adalah logika monitoring lintas halaman monitoring.

### 2.6 Retry stock commit
Keputusan:
1. Retry stock commit tidak boleh lagi mem-post snapshot lama mentah-mentah.
2. Retry wajib bisa me-resolve ulang line snapshot dari canonical source terbaru sebelum repost.

Implikasi:
1. Ini menjadi prioritas awal fase 2.

## 3. Keputusan Tambahan
### 3.1 Tabel tidak di-rename dulu
Keputusan:
1. Nama tabel legacy tidak diubah pada fase awal.
2. Yang diubah lebih dulu adalah makna canonical write path.

Alasan:
1. Mengurangi risiko gangguan operasional.
2. Mengurangi noise refactor yang tidak langsung memberi nilai.

### 3.2 Legacy domain tetap dibaca sementara
Keputusan:
1. Reader/audit/reconcile tetap boleh membaca row legacy selama masa transisi.
2. Tetapi semua writer baru harus menuju item-centric.

### 3.3 Historical repair tidak dikerjakan lebih dulu
Keputusan:
1. Repair historis besar dikerjakan setelah writer dan resolver baru stabil.

Alasan:
1. Agar kerja repair tidak perlu diulang dua kali.

## 4. Prioritas Keputusan yang Dikunci
Urutan prioritas:
1. `item_id` = canonical write identity
2. `material_id` = metadata + bridge
3. `usage_purpose` = pembeda produksi vs operasional
4. retry stock commit = re-resolve snapshot
5. repair historis besar setelah stop-the-bleeding

## 5. Hal yang Sengaja Belum Diputuskan Final
Belum diputuskan final:
1. apakah recipe nanti tetap permanen via `material_id` + bridge
2. atau akan ditambah `item_id` native di schema recipe
3. apakah monitoring HPP akan punya mode:
   - direct only
   - direct + variable default
   - direct + variable custom

Catatan:
1. Tiga hal ini bisa diputuskan sesudah fase 1 dan 2 berjalan.

## 6. Rekomendasi Saya
Saya merekomendasikan keputusan ini dianggap aktif mulai sekarang:
1. semua patch baru mengikuti model item-centric
2. jangan menambah jalur baru yang kembali material-centric
3. semua repair/bridge berikutnya mengacu ke keputusan ini
