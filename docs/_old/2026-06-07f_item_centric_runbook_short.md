# Item-Centric Runbook Short

Tanggal: 2026-06-07
Tujuan: versi singkat untuk orientasi cepat sebelum melanjutkan task item-centric

## Inti Konsep

1. `item_id` = identity utama
2. `material_id` = marker / bridge produksi
3. `usage_purpose` = penentu perilaku bisnis
4. `line_kind` dan `stock_domain` = legacy, arahnya dipensiunkan lalu dihapus

## Tujuan Akhir

1. tidak ada lagi create/write snapshot aktif yang membelah `ITEM` vs `MATERIAL`
2. tidak ada lagi pemakaian aktif `line_kind` sebagai decision source
3. tidak ada lagi pemakaian aktif `stock_domain` sebagai decision source
4. tidak ada lagi mismatch di `/pos/stock-live`
5. tidak ada lagi mismatch di `/pos/stock-commit-audit`
6. tidak ada lagi job gagal POS karena drift identity legacy
7. stok gudang, stok divisi, stok bahan baku, stok component, stok produk, dan HPP live sinkron rumusnya
8. `daily_rollup` dan `stock_balance` tidak lagi dipakai runtime aktif

## Yang Sudah Beres

1. banyak write path purchase/procurement sudah item-centric
2. procurement request/fulfillment sudah lebih konsisten memakai `item_id`
3. adjustment negatif sudah pre-sync ke FIFO truth sebelum posting
4. runtime aktif sudah diputus dari `daily_rollup` dan `stock_balance`
5. istilah method aktif `daily_rollup` sudah direname
6. POS live cost sudah lebih selaras dengan weighted aggregate monthly
7. audit POS mulai dibersihkan dari pembacaan legacy `ITEM/MATERIAL`

## Yang Masih Jadi Fokus

1. `Purchase_model.php`
2. `Inventory_tools.php`
3. `Master_relation.php`
4. verifikasi ulang `/pos/stock-commit-audit` setelah retry job
5. deprecate tabel legacy di DB

## Hotspot Operasional Saat Ini

### Jika job gagal POS masih ada

1. cek `PosOrderStockService.php`
2. cek `InventoryLedger.php`
3. cek exact profile monthly stale atau tidak
4. cek apakah gagal di jalur FIFO per-lot atau fallback aggregate

### Jika mismatch bahan baku di `/pos/stock-commit-audit`

1. cek apakah mismatch real atau false positive audit
2. cek compare source masih memakai legacy atau tidak
3. cek helper di `Purchase_model.php`

### Jika HPP live drift

1. cek weighted aggregate source
2. cek resolver cost di POS
3. cek cache/rebuild live

## Larangan

1. jangan bulk flip `MATERIAL -> ITEM`
2. jangan hidupkan lagi `daily_rollup`
3. jangan hidupkan lagi `stock_balance`
4. jangan pakai `stock_domain` untuk create snapshot baru
5. jangan pakai `line_kind` untuk create snapshot baru
6. jangan anggap `BAHAN_BAKU` otomatis berarti lane `MATERIAL`

## Urutan Lanjut Paling Aman

1. retry dan refresh job POS
2. ukur ulang mismatch `/pos/stock-commit-audit`
3. sapu `Purchase_model.php`
4. sapu `Inventory_tools.php`
5. audit `Master_relation.php`
6. jalankan audit dependency legacy table
7. deprecate table legacy

## Referensi Utama

1. [2026-06-07e_item_centric_progress_handover.md](/c:/xampp/htdocs/finance/docs/2026-06-07e_item_centric_progress_handover.md)
2. [2026-06-07b_legacy_daily_rollup_stock_balance_audit.md](/c:/xampp/htdocs/finance/docs/2026-06-07b_legacy_daily_rollup_stock_balance_audit.md)

