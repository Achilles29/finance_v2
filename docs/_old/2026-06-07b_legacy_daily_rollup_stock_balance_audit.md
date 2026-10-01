# Audit Legacy `daily_rollup` / `stock_balance` 2026-06-07

## Ringkasan

- Runtime aktif aplikasi sudah tidak lagi membaca:
  - `inv_warehouse_daily_rollup`
  - `inv_division_daily_rollup`
  - `inv_component_daily_rollup`
  - `inv_warehouse_stock_balance`
  - `inv_division_stock_balance`
  - `inv_component_stock_balance`
- Referensi yang masih tersisa berada di:
  - dokumen desain / roadmap lama
  - SQL foundation / repair / audit historis

## Hasil audit kode aktif

Pencarian pada `application/controllers`, `application/models`, `application/libraries`, dan `application/views` untuk nama tabel legacy di atas menghasilkan **tidak ada referensi aktif**.

Maknanya:

- reader runtime sudah bertumpu pada:
  - movement log
  - monthly stock
  - snapshot opening yang masih relevan
- istilah `daily_rollup` yang masih ada di codebase tinggal nama view/page dan sudah tidak mengarah ke tabel legacy

## Sisa referensi non-runtime

### Dokumentasi

Masih ada referensi di dokumen lama, terutama:

- `docs/MODULES.md`
- `docs/ROADMAP.md`
- `docs/2026-05-03i_desain_tabel_stok_live_dan_daily.md`
- `docs/2026-05-14c_rencana_store_request_schema_ui.md`
- `docs/2026-05-20a_desain_produksi_base_prepare_schema_ui.md`
- `docs/2026-05-22-A-audit-lot-impact-procurement-stock.md`
- `docs/2026-05-24-A-desain-expiry-lot-po-sr.md`
- `docs/2026-06-04d_mapping_refactor_item_centric_per_tabel_dan_migrasi.md`

### SQL historis / foundation / repair

Masih ada referensi di:

- foundation schema lama
  - `sql/2026-05-03i_purchase_affected_finance_inventory_audit_foundation.sql`
  - `sql/2026-05-03j_inventory_minimal_v1_foundation.sql`
  - `sql/2026-05-20b_inv_component_operational_foundation.sql`
- migration lama
  - `sql/2026-05-04c_purchase_inventory_destination_split.sql`
  - `sql/2026-05-06a_purchase_profile_expired_date.sql`
  - `sql/2026-05-06b_inventory_adjustment_components.sql`
- audit / repair / backfill historis
  - `sql/2026-06-03b_production_domain_root_cause_audit.sql`
  - `sql/2026-06-03c_production_domain_root_cause_repair.sql`
  - `sql/2026-06-06a_repair_purchase_profile_description_global.sql`
  - `sql/2026-06-06b_finalize_purchase_profile_description_aggregate_cleanup.sql`
  - `sql/2026-06-06c_finalize_remaining_profile_description_aggregate_rows.sql`
  - `sql/2026-06-06e_item_centric_stock_domain_material_audit.sql`
  - `sql/2026-06-06g_item_centric_safe_material_to_item_candidates.sql`
  - `sql/2026-06-06h_repair_single_division_monthly_from_daily.sql`
  - `sql/2026-06-06i_repair_single_division_balance_monthly_from_fifo.sql`
  - `sql/2026-06-06k_backfill_missing_division_balance_from_fifo.sql`
  - `sql/2026-06-07a_item_centric_profile_key_collision_candidates.sql`

## Kesimpulan

Tabel legacy sudah tidak dipakai sebagai sumber runtime aktif, tetapi belum boleh langsung di-drop tanpa persiapan karena:

- masih ada SQL repair lama yang menyebutnya
- masih ada dokumen yang mengasumsikan tabel itu aktif
- beberapa audit transisi item-centric masih membaca tabel itu untuk kebutuhan forensik

## Rekomendasi deprecate / drop yang aman

1. Bekukan status tabel legacy sebagai `read-only legacy`.
2. Perbarui SQL repair aktif agar tidak lagi bergantung pada `daily_rollup` / `stock_balance`.
3. Tambahkan SQL audit final untuk memastikan:
   - row count tabel legacy sudah tidak dibutuhkan operasional
   - tidak ada stored procedure / trigger / event scheduler yang masih mengaksesnya
4. Rename atau arsipkan script repair lama yang masih bergantung pada tabel legacy.
5. Baru setelah itu siapkan migration `DROP TABLE` atau `RENAME TABLE ... TO ..._legacy_backup`.

## Rekomendasi praktik

Untuk produksi, pendekatan paling aman adalah dua tahap:

1. `RENAME TABLE` ke suffix backup sementara.
2. Pantau aplikasi dan job terjadwal.
3. Jika tidak ada error baru, lanjut ke `DROP TABLE`.
