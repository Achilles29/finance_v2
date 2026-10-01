# Index Dokumentasi - Finance

Terakhir diperbarui: 2026-09-03

Dokumentasi repo ini sekarang dibagi menjadi dua lapis:

1. **dokumen fondasi repo**
2. **dokumen program item-centric**

Kalau mulai sesi baru atau pindah task, baca indeks ini dulu.

## Bacaan Wajib

Urutan baca yang paling aman:

1. [SETUP.md](SETUP.md)
2. [CODING_STANDARDS.md](CODING_STANDARDS.md)
3. [ROADMAP.md](ROADMAP.md)
4. [MODULES.md](MODULES.md)
5. [release_runtime_contract.md](release_runtime_contract.md)

## Handoff Codex Terbaru

1. [2026-09-03_codex_handoff_b01-b68.md](2026-09-03_codex_handoff_b01-b68.md)
2. [2026-09-03_codex_server_sql_runbook_b01-b68.md](2026-09-03_codex_server_sql_runbook_b01-b68.md)
3. [2026-09-03_progress_roadmap_user_finance.md](2026-09-03_progress_roadmap_user_finance.md)

## Paket Dokumentasi Item-Centric

Ini adalah paket dokumen paling penting untuk kondisi repo saat ini:

1. [2026-06-07e_item_centric_progress_handover.md](2026-06-07e_item_centric_progress_handover.md)
2. [2026-06-07f_item_centric_runbook_short.md](2026-06-07f_item_centric_runbook_short.md)
3. [2026-06-07g_item_centric_hotspot_matrix.md](2026-06-07g_item_centric_hotspot_matrix.md)

Cara pakainya:

1. baca `07e` untuk konteks besar
2. baca `07f` untuk orientasi cepat
3. buka `07g` saat mau langsung eksekusi teknis

## Target Repo Saat Ini

Target besar repo sekarang bukan sekadar menambah fitur, tetapi menstabilkan satu arsitektur stok yang konsisten.

Target akhirnya:

1. `item_id` menjadi identity utama create/write stok
2. `material_id` tetap ada sebagai marker/bridge produksi
3. `usage_purpose` menjadi decision source perilaku bisnis
4. `line_kind` tidak lagi menjadi decision source
5. `stock_domain` tidak lagi menjadi decision source
6. arah akhirnya menuju penghapusan aktif kolom/field/tabel legacy `MATERIAL` sebagai identity stok utama
7. `/pos/stock-live` dan `/pos/stock-commit-audit` harus sinkron
8. stok gudang, stok bahan baku, stok component, stok produk, dan HPP live harus memakai rumus yang konsisten

## Dokumen Fondasi Repo

### 1. [SETUP.md](SETUP.md)

Berisi:

1. setup lokal
2. konfigurasi aplikasi
3. database
4. migration SQL
5. keputusan arsitektur yang sudah final

### 2. [CODING_STANDARDS.md](CODING_STANDARDS.md)

Berisi:

1. pola controller/model/view
2. AJAX/fetch
3. form, table, filter, modal
4. standar penulisan SQL dan UI

### 3. [ROADMAP.md](ROADMAP.md)

Berisi:

1. status program saat ini
2. stream prioritas
3. modul yang sudah cukup stabil
4. modul yang masih perlu hardening
5. target item-centric dan deprecate legacy

### 4. [MODULES.md](MODULES.md)

Berisi:

1. peta modul
2. tabel kunci
3. alur bisnis
4. file-file utama per modul

## Dokumen Program Item-Centric

### 1. [2026-06-07e_item_centric_progress_handover.md](2026-06-07e_item_centric_progress_handover.md)

Dokumen utama handover.

Berisi:

1. latar belakang
2. tujuan akhir
3. keputusan desain final
4. progress
5. hotspot file
6. SQL penting
7. unresolved issues
8. runbook diagnosis

### 2. [2026-06-07f_item_centric_runbook_short.md](2026-06-07f_item_centric_runbook_short.md)

Versi singkat untuk dibaca cepat.

### 3. [2026-06-07g_item_centric_hotspot_matrix.md](2026-06-07g_item_centric_hotspot_matrix.md)

Matrix teknis file-to-problem.

## Dokumen Aktif Lain yang Masih Relevan

Dokumen berikut masih berguna sebagai referensi desain atau histori transisi:

1. `2026-06-04c_konsep_item_centric_inventory_procurement_production_pos.md`
2. `2026-06-04d_mapping_refactor_item_centric_per_tabel_dan_migrasi.md`
3. `2026-06-04e_keputusan_final_item_centric.md`
4. `2026-06-04f_checklist_implementasi_fase1_fase2_item_centric.md`
5. `2026-06-04h_mapping_component_impact_item_centric.md`
6. `2026-06-06a_item_centric_gap_map_reader_writer_schema.md`
7. `2026-06-07b_legacy_daily_rollup_stock_balance_audit.md`

## Dokumen SQL / Repair yang Sering Dirujuk

Untuk investigasi atau repair item-centric, SQL yang sering dipakai:

1. `../sql/2026-06-06e_item_centric_stock_domain_material_audit.sql`
2. `../sql/2026-06-06g_item_centric_safe_material_to_item_candidates.sql`
3. `../sql/2026-06-07a_item_centric_profile_key_collision_candidates.sql`
4. `../sql/2026-06-07c_audit_legacy_table_db_dependencies.sql`
5. `../sql/2026-06-07d_deprecate_legacy_daily_rollup_stock_balance_tables.sql`

## Aturan Update Dokumentasi

1. jika ada perubahan arah program, update `ROADMAP.md`
2. jika ada keputusan desain item-centric baru, update `07e`
3. jika ada hotspot file baru, update `07g`
4. jika ada perubahan setup atau keputusan arsitektur global, update `SETUP.md`
5. jangan buat dokumen baru kalau informasi seharusnya masuk ke dokumen aktif yang sudah ada

## Ringkasan Cepat

Kalau sedang buru-buru:

1. baca [2026-06-07f_item_centric_runbook_short.md](2026-06-07f_item_centric_runbook_short.md)
2. lalu buka [2026-06-07g_item_centric_hotspot_matrix.md](2026-06-07g_item_centric_hotspot_matrix.md)
