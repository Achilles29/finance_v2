# Checklist Implementasi Fase 1 dan Fase 2 Item-Centric
**Tanggal:** 2026-06-04  
**Status:** Checklist kerja untuk eksekusi awal tanpa mengganggu operasional

## 1. Tujuan
Checklist ini memecah implementasi menjadi:
1. `Fase 1`: stop the bleeding
2. `Fase 2`: bridge resolver dan retry stock commit

Target:
1. Data baru berhenti makin kotor.
2. POS retry dan monitoring punya jalur canonical yang sehat.
3. Repair historis besar bisa ditunda sampai fondasi baru cukup stabil.

## 2. Fase 1: Stop the Bleeding
### 2.1 Guard writer transaksi baru
Target:
1. semua writer baru canonical `item_id`
2. tidak ada canonical row baru berbasis `material_id`

Checklist:
1. Audit writer `Purchase_model`
2. Audit writer `Procurement_model`
3. Audit writer `Production_model`
4. Audit writer `Pos_model`
5. Audit writer `InventoryLedger`
6. Audit writer `MaterialFifoManager`

Definition of done:
1. transaksi baru tidak lagi membuat row monthly/daily canonical `MATERIAL`
2. `material_id` tetap terisi sebagai metadata jika item adalah bahan baku

### 2.2 Guard PO / receipt
Checklist:
1. pastikan PO line canonical memakai `item_id`
2. receipt line canonical memakai `item_id`
3. posting inbound ledger canonical memakai `item_id`
4. `material_id` tetap ikut tersimpan sebagai snapshot/tagging
5. inbound FIFO lot menyimpan `item_id` wajib

### 2.3 Guard SR / fulfillment
Checklist:
1. store request line canonical memakai `item_id`
2. fulfillment line canonical memakai `item_id`
3. `usage_purpose` menentukan jalur produksi vs operasional
4. operasional tidak membentuk stok divisi produksi
5. transfer gudang ke divisi untuk produksi tetap boleh memakai FIFO, tetapi canonical ledger tetap item-centric

### 2.4 Guard adjustment / opening
Checklist:
1. adjustment line canonical memakai `item_id`
2. opening snapshot canonical memakai `item_id`
3. VOID adjustment tidak meninggalkan lot/row liar baru

### 2.5 Guard POS write path
Checklist:
1. order confirm baru tidak lagi membuat snapshot line berbasis identity campuran
2. HPP snapshot baru tetap berasal dari resolver canonical
3. void/refund reversal tetap kompatibel

## 3. Fase 2: Bridge Resolver Layer
### 3.1 Bridge recipe resolver
Target:
1. recipe boleh masih refer `material_id`
2. resolver harus selalu mengembalikan canonical `item_id`

Checklist:
1. buat helper `material -> canonical item`
2. buat helper `recipe line -> canonical source`
3. pakai helper itu di:
   - product recipe page
   - stock live POS
   - stock commit snapshot builder
   - production formula issue

### 3.2 Bridge stock live resolver
Checklist:
1. monitoring product availability membaca canonical item balance
2. stock live POS membaca canonical item balance
3. bottleneck tetap bisa menampilkan material tag

Definition of done:
1. product-recipe, product/availability, dan stock live POS memakai logika monitoring yang sama

### 3.3 Retry stock commit resolver
Target:
1. retry tidak lagi memakai snapshot lama mentah

Checklist:
1. temukan source order + snapshot failed
2. re-resolve recipe lines dari canonical source terbaru
3. update `pos_stock_commit_line` sebelum repost
4. baru jalankan posting ulang

Definition of done:
1. failed snapshot lama bisa diproses ulang tanpa membawa `unit_cost_live` legacy yang salah

### 3.4 Bridge reconcile reader
Checklist:
1. `/pos/stock-live` membaca item-centric source dulu
2. `/inventory/stock/division/reconcile` bisa membedakan legacy vs canonical
3. `/pos/stock-commit-audit` menandai failure karena snapshot lama vs failure karena stok riil

## 4. Daftar File Prioritas
### 4.1 Writer priority
1. [Purchase_model.php](/c:/xampp/htdocs/finance/application/models/Purchase_model.php)
2. [Procurement_model.php](/c:/xampp/htdocs/finance/application/models/Procurement_model.php)
3. [Production_model.php](/c:/xampp/htdocs/finance/application/models/Production_model.php)
4. [Pos_model.php](/c:/xampp/htdocs/finance/application/models/Pos_model.php)
5. [InventoryLedger.php](/c:/xampp/htdocs/finance/application/libraries/InventoryLedger.php)
6. [MaterialFifoManager.php](/c:/xampp/htdocs/finance/application/libraries/MaterialFifoManager.php)

### 4.2 Resolver priority
1. [Master_relation.php](/c:/xampp/htdocs/finance/application/controllers/Master_relation.php)
2. [PosAvailabilityRebuildService.php](/c:/xampp/htdocs/finance/application/libraries/PosAvailabilityRebuildService.php)
3. [PosOrderStockService.php](/c:/xampp/htdocs/finance/application/libraries/PosOrderStockService.php)
4. [PosRuntimeJobService.php](/c:/xampp/htdocs/finance/application/libraries/PosRuntimeJobService.php)
5. [PosStockCommitService.php](/c:/xampp/htdocs/finance/application/libraries/PosStockCommitService.php)

### 4.3 Audit/UI priority
1. [stock_live_index.php](/c:/xampp/htdocs/finance/application/views/pos/stock_live_index.php)
2. [stock_commit_audit_index.php](/c:/xampp/htdocs/finance/application/views/pos/stock_commit_audit_index.php)
3. [stock_division_reconcile_index.php](/c:/xampp/htdocs/finance/application/views/purchase/stock_division_reconcile_index.php)

## 5. Urutan Eksekusi yang Saya Rekomendasikan
### Langkah 1
1. kunci keputusan final item-centric

### Langkah 2
1. patch writer baru agar item-centric

### Langkah 3
1. buat helper bridge `material -> canonical item`
2. hentikan penggunaan `line_kind`/`stock_domain` sebagai penentu canonical write path baru

### Langkah 4
1. patch stock live resolver
2. patch recipe monitoring resolver

### Langkah 5
1. patch retry stock commit agar re-resolve snapshot

### Langkah 6
1. baru lanjut repair historis domain legacy besar

## 6. Hal yang Jangan Dilakukan Dulu
1. jangan rename tabel besar-besaran dulu
2. jangan drop `mst_material`
3. jangan repair historis massal baru tanpa writer baru stabil
4. jangan samakan HPP monitoring dan HPP actual commit tanpa keputusan bisnis

## 7. Definition of Success
Fase 1 dan 2 dianggap berhasil jika:
1. transaksi baru tidak lagi memecah stok `ITEM/MATERIAL`
2. retry stock commit failed tidak lagi membawa snapshot cost legacy
3. stock live POS, product availability, dan recipe monitoring konsisten sebagai monitoring layer
4. repair historis berikutnya menjadi pekerjaan satu arah, bukan bolak-balik
