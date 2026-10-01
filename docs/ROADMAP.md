# Roadmap Pengembangan - Finance

Terakhir diperbarui: 2026-06-07
Status roadmap: aktif

Dokumen ini sekarang bukan lagi roadmap “fitur baru per tanggal”, tetapi roadmap stabilisasi dan konsolidasi arsitektur repo sesuai kondisi riil codebase.

## Arah Besar Repo

Repo ini sedang bergerak ke satu target utama:

**semua domain stok dan HPP live harus konsisten dengan arsitektur item-centric**

Maknanya:

1. `item_id` adalah identity utama create/write stok
2. `material_id` adalah marker/bridge produksi
3. `usage_purpose` adalah penentu perilaku bisnis
4. `line_kind` tidak lagi dipakai sebagai decision source
5. `stock_domain` tidak lagi dipakai sebagai decision source
6. `daily_rollup` dan `stock_balance` tidak lagi dipakai runtime aktif
7. POS live, stock commit audit, gudang, divisi, bahan baku, component, produk, dan HPP live harus sinkron satu sama lain

## Definisi Selesai Program Stabilisasi

Program ini dianggap mendekati selesai jika:

1. tidak ada lagi create/write snapshot aktif yang membelah `ITEM` vs `MATERIAL`
2. tidak ada lagi pemakaian aktif `line_kind` sebagai decision source
3. tidak ada lagi pemakaian aktif `stock_domain` sebagai decision source
4. `/pos/stock-live` dan `/pos/stock-commit-audit` sama-sama bersih
5. job gagal POS tidak lagi muncul karena legacy identity drift
6. HPP live lintas gudang/divisi/component/POS memakai rumus yang konsisten
7. tabel legacy `daily_rollup` dan `stock_balance` sudah dideprecate aman
8. arah schema final sudah jelas menuju penghapusan aktif kolom/field/tabel legacy `MATERIAL` sebagai identity stok utama

## Status Overview Saat Ini

```
Fondasi auth/master/HR/payroll             -> stabil
Purchase/procurement write path            -> cukup sehat, masih hardening reader
Inventory monthly + movement-first         -> aktif
Daily rollup / stock_balance runtime       -> sudah diputus
Component runtime                          -> banyak sudah pindah ke monthly/log
POS live availability                      -> aktif dan membaik
POS stock commit audit                     -> masih perlu verifikasi akhir
Item-centric cleanup                       -> masih berjalan
Legacy schema deprecation                  -> siap fase audit/rename
```

## Stream Prioritas

### Stream 1. Item-Centric Stabilization

Ini stream utama.

Target:

1. semua write path aktif item-centric
2. semua compare/audit item-centric
3. tidak ada lagi snapshot aktif yang bergantung pada `MATERIAL` sebagai identity

Status:

1. berjalan
2. belum selesai

Hotspot:

1. `Purchase_model.php`
2. `Inventory_tools.php`
3. `Master_relation.php`

### Stream 2. POS Runtime Consistency

Target:

1. `/pos/stock-live` sinkron
2. `/pos/stock-commit-audit` sinkron
3. retry job gagal POS tidak lagi terhambat drift identity

Status:

1. membaik
2. perlu verifikasi pasca patch terbaru

Hotspot:

1. `PosOrderStockService.php`
2. `Pos_model.php`
3. `PosAvailabilityRebuildService.php`
4. `InventoryLedger.php`

### Stream 3. Legacy Table Deprecation

Target:

1. `daily_rollup` dan `stock_balance` tidak lagi dipakai runtime
2. dependency DB diaudit
3. tabel legacy di-rename ke backup
4. drop final dilakukan setelah masa observasi

Status:

1. runtime aktif sudah bersih
2. audit/rename SQL sudah siap
3. eksekusi DB belum dilakukan

### Stream 4. Formula & HPP Consistency

Target:

1. gudang
2. divisi
3. bahan baku
4. component
5. produk
6. POS

semuanya membaca cost source dan HPP live dengan rumus yang konsisten.

Status:

1. sebagian besar jalur utama sudah mendekat
2. masih perlu hardening di compare/rebuild/reader tertentu

## Status Per Area

### 1. Purchase

Status:

1. write path sudah cukup sehat
2. read/report/audit masih perlu hardening

Sudah:

1. purchase write utama item-centric
2. `material_id` dipertahankan sebagai marker
3. beberapa compare/audit sudah mulai dibersihkan

Belum:

1. semua helper legacy di `Purchase_model.php`
2. semua compare material yang masih hybrid

### 2. Procurement / Request

Status:

1. cukup sehat
2. masih ada compat layer display/search yang perlu dijaga

Sudah:

1. request payload lebih canonical ke `item_id`
2. fulfillment source lebih item-centric

Belum:

1. semua helper review/search benar-benar bebas asumsi legacy

### 3. Inventory

Status:

1. monthly stock + movement-first aktif
2. stale legacy source besar sudah diputus

Sudah:

1. runtime aktif tidak lagi memakai `daily_rollup`
2. runtime aktif tidak lagi memakai `stock_balance`
3. rebuild dan repair tertentu sudah diarahkan ke monthly/log

Belum:

1. semua tool inventory/repair dibersihkan dari payload legacy
2. deprecate table legacy di DB

### 4. Component / Production

Status:

1. cukup baik
2. masih perlu konsistensi penuh dengan item-centric inventory

Sudah:

1. banyak reader/writer aktif pindah ke monthly/log
2. carry-forward dan workbench operasional lebih rapi

Belum:

1. hardening penuh lintas costing/rebuild/repair

### 5. POS

Status:

1. aktif
2. paling sensitif terhadap drift stok/HPP

Sudah:

1. stock live membaik
2. resolver HPP live lebih konsisten
3. audit POS mulai dibersihkan dari mismatch palsu

Belum:

1. verifikasi akhir job gagal POS
2. verifikasi akhir mismatch bahan baku commit audit

### 6. HR / Payroll / Finance Foundation

Status:

1. stabil
2. bukan blocker utama item-centric saat ini

## Roadmap Taktis Berikutnya

Urutan kerja paling aman dari kondisi sekarang:

### Prioritas 1. Verifikasi POS pasca patch terakhir

Checklist:

1. retry semua job gagal POS
2. refresh job
3. cek ulang `/pos/stock-live`
4. cek ulang `/pos/stock-commit-audit`
5. catat sisa mismatch dan sisa failed jobs

Jika masih ada sisa:

1. bedah 1 job gagal terakhir
2. trace exact identity/profile/division/material

### Prioritas 2. Sapu `Purchase_model.php`

Checklist:

1. cabut compare yang masih memasukkan `stock_domain` ke identity
2. cabut helper yang masih menganggap `material_id` berarti lane `MATERIAL`
3. rapikan audit dan compare supaya hanya menghitung drift nyata

### Prioritas 3. Sapu `Inventory_tools.php`

Checklist:

1. buang payload contoh yang masih menulis `stock_domain`
2. buang payload contoh yang masih memaksa `line_kind='MATERIAL'`
3. pastikan tool operasional tidak memproduksi snapshot legacy baru

### Prioritas 4. Audit `Master_relation.php`

Checklist:

1. cek filter `stock_domain`
2. ubah pembacaan relation/costing agar item-centric + `material_id` marker

### Prioritas 5. Deprecate DB Legacy

Checklist:

1. jalankan `2026-06-07c_audit_legacy_table_db_dependencies.sql`
2. jika aman, jalankan `2026-06-07d_deprecate_legacy_daily_rollup_stock_balance_tables.sql`
3. observasi
4. siapkan SQL final drop backup legacy

## Roadmap yang Tidak Lagi Berlaku

Beberapa asumsi roadmap lama sekarang harus dianggap obsolete:

1. target live 1 Juni 2026 sebagai garis finish tunggal
2. `daily_rollup` sebagai bagian arsitektur aktif
3. `stock_balance` sebagai bagian arsitektur aktif
4. pembedaan aktif `ITEM` vs `MATERIAL` untuk write snapshot baru

## Dokumen Pendamping Wajib

Untuk melanjutkan roadmap ini, selalu rujuk:

1. [2026-06-07e_item_centric_progress_handover.md](2026-06-07e_item_centric_progress_handover.md)
2. [2026-06-07f_item_centric_runbook_short.md](2026-06-07f_item_centric_runbook_short.md)
3. [2026-06-07g_item_centric_hotspot_matrix.md](2026-06-07g_item_centric_hotspot_matrix.md)
4. [2026-06-07b_legacy_daily_rollup_stock_balance_audit.md](2026-06-07b_legacy_daily_rollup_stock_balance_audit.md)

## Ringkasan Singkat

Roadmap repo sekarang bisa diringkas menjadi:

1. pertahankan modul operasional yang sudah aktif
2. selesaikan stabilisasi item-centric
3. bersihkan semua decision source legacy
4. samakan formula HPP dan stok lintas modul
5. deprecate legacy table dan arahkan schema ke kondisi akhir yang lebih sederhana

