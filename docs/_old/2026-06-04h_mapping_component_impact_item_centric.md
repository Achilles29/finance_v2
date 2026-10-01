**Tujuan**
Memetakan area modul component yang terdampak oleh transisi item-centric pada lane material, terutama untuk kasus:
- `component batch`
- `component daily preview`
- `component reconcile`
- `POS stock commit` yang memakai `source_kind = COMPONENT`

Dokumen ini juga mencatat akar masalah konkret dari batch:
- `ICB202606040001`

**Ringkasan Temuan**
1. Modul component tetap punya identity utama sendiri:
- `component_id`
- `uom_id`
- `location_type`
- `division_id`

2. Dampak skema item/material masuk hanya pada lane `source_kind = MATERIAL`.
- Jadi bukan semua modul component yang harus dirombak.
- Titik rawannya ada saat component batch membaca dan memotong stok bahan baku divisi.

3. Kasus `ICB202606040001` gagal bukan karena component output.
- Batch masih `DRAFT`
- belum menulis `inv_component_movement_log`
- belum menulis `inv_material_fifo_issue_log`
- gagal sangat awal saat input material batch diposting ke lane inventory divisi

**Akar Masalah Konkret ICB202606040001**
Batch:
- `inv_component_batch.id = 28`
- `batch_no = ICB202606040001`
- `division_id = 3`
- `location_type = KITCHEN`
- `component_id = 14`

Input material salah satu yang kritis:
- `inv_component_batch_input.id = 117`
- `item_id = 195`
- `material_id = 107`
- `qty = 130`
- `uom_id = 9`

Stok/FIFO untuk bahan ini di divisi KITCHEN:
- lot lama:
  - `inv_material_fifo_lot.id = 419`
  - `profile_key = 256d80...`
  - `qty_balance = 9.8105`
- lot baru:
  - `inv_material_fifo_lot.id = 818`
  - `profile_key = d04855...`
  - `qty_balance = 1600`

Tetapi monthly stock untuk profile lama sudah drift negatif:
- `inv_division_monthly_stock.id = 348`
- `profile_key = 256d80...`
- `closing_qty_content = -20.1895`
- notes:
  - `POS usage aggregate fallback | FIFO fallback: Saldo FIFO divisi tidak cukup...`

Akibatnya:
1. FIFO masih bisa melihat lot positif nyata
2. batch preview merasa stok cukup
3. saat posting, alokasi pertama menyentuh profile lama (`256d80...`)
4. ledger monthly membaca saldo lama yang sudah negatif
5. delta keluar membuat saldo semakin negatif
6. `InventoryLedger::post()` menolak dengan:
   - `Mutasi menyebabkan saldo bulanan negatif.`

**Kesimpulan Teknis**
Masalah utama lane component saat ini bukan pada `component_id`.
Masalah utamanya adalah:
1. preview material masih membaca agregat monthly yang bisa menyamarkan drift profile
2. posting material batch memakai FIFO lot yang benar, tetapi ledger monthly untuk profile yang sama bisa sudah rusak
3. berarti `preview`, `FIFO`, dan `monthly ledger` belum memakai sumber kebenaran yang konsisten

**Modul Component Yang Terdampak**
1. `application/models/Production_model.php`
Area:
- `component_batch_preview()`
- `component_batch_material_stock_state()`
- `save_component_batch()`

Dampak:
- preview batch untuk MATERIAL masih menghitung availability dari agregat monthly
- fallback bisa menjumlahkan lintas profile/item/UOM legacy
- ini membuat preview bisa terlihat aman walaupun profile FIFO tertentu sudah drift

2. `application/libraries/ComponentStockWriter.php`
Area:
- `post_batch()`
- `post_material_input_usage()`
- `resolve_inventory_snapshot_for_allocation()`

Dampak:
- batch MATERIAL memotong stok via `MaterialFifoManager`
- lalu tetap mem-post aggregate movement ke `InventoryLedger`
- kalau snapshot monthly profile lama sudah negatif, batch gagal sebelum sempat selesai

3. `application/libraries/MaterialFifoManager.php`
Area:
- `consumeDivisionUsage()`
- `findIssueSourceLots()`

Dampak:
- FIFO lot adalah sumber qty yang lebih otoritatif
- tetapi hasil alokasi belum otomatis menyembuhkan monthly profile yang sudah drift

4. `application/libraries/InventoryLedger.php`
Area:
- `post()`
- `updateInventoryMonthlyStock()`

Dampak:
- ledger menolak saldo negatif, itu benar
- tetapi saat authoritative source sebenarnya adalah FIFO lot, monthly row lama yang drift perlu direkonsiliasi dulu sebelum delta diposting

5. `application/controllers/Production.php`
Area:
- `component_batch_post()`

Dampak:
- belum ada preflight health check sebelum batch diposting
- kalau profile monthly tertentu drift, user baru tahu saat post gagal

**Modul Yang Tidak Langsung Terdampak Oleh Skema Item/Material**
1. `component opening`
- lane utamanya component-only
- tidak memotong material divisi

2. `component adjustment`
- lane utamanya component-only
- tidak memakai `item_id/material_id` material input
- bisa tetap gagal, tetapi problemnya lebih ke drift component monthly sendiri, bukan transisi item/material

3. `component master / formula`
- relasi formula tetap penting
- tetapi bukan titik gagal negatif bulanan pada contoh ini

**Yang Harus Diubah**
**Prioritas 1: Batch preview MATERIAL harus pakai sumber truth yang sama dengan posting**
Ubah:
- `Production_model::component_batch_material_stock_state()`

Target:
- jangan hanya baca agregat `inv_division_monthly_stock`
- baca authoritative availability dari FIFO lot divisi
- preview harus tahu profile/lot yang benar-benar akan dipakai

Manfaat:
- preview shortage akan lebih jujur
- user tidak diberi kesan stok cukup padahal profile yang akan dipotong sedang drift

**Prioritas 2: Sebelum `InventoryLedger::post()`, batch material harus sinkronkan monthly profile dari FIFO source**
Ubah:
- `ComponentStockWriter::post_material_input_usage()`

Target:
- untuk setiap allocation hasil FIFO:
  - ambil identity source lot
  - sinkronkan atau rebuild monthly row profile itu dari posisi lot nyata
  - baru post aggregate movement keluar

Manfaat:
- monthly drift lama tidak langsung menjatuhkan batch baru
- ledger tetap strict, tetapi diberi state awal yang benar

**Prioritas 3: Tambah health check sebelum batch post**
Ubah:
- `Production::component_batch_post()`
- atau helper baru di `Production_model` / `ComponentStockWriter`

Target:
- cek apakah ada monthly profile negatif untuk material input yang akan dipakai
- kalau ada:
  - tampilkan pesan spesifik
  - atau jalankan rebuild targeted

Manfaat:
- user dapat error yang lebih jelas
- tidak lagi hanya pesan generik saldo bulanan negatif

**Prioritas 4: Tambah audit khusus drift profile material yang dipakai component batch**
Area:
- halaman `production/component-reconcile`
- atau tab audit baru

Target:
- bukan hanya reconcile component monthly
- tetapi juga perlihatkan material input yang dipakai component dan profile yang drift

Manfaat:
- akar masalah lane component terlihat dari UI

**Prioritas 5: POS component usage tetap dicek setelah batch lane stabil**
Area:
- `application/libraries/PosOrderStockService.php`

Catatan:
- lane ini component-centric
- tetapi kalau component stock dibentuk dari batch yang gagal/drift, efek turunannya bisa sampai ke POS

**Usulan Urutan Pengerjaan**
1. perbaiki `component_batch_material_stock_state()` agar preview pakai FIFO truth
2. perbaiki `post_material_input_usage()` agar monthly profile disinkronkan sebelum ledger post
3. tambah preflight batch health check
4. baru evaluasi apakah `component adjustment` masih perlu lane terpisah

**Kesimpulan**
Untuk refactor item-centric saat ini:
- lane component tidak perlu dibalik total
- yang perlu dibetulkan adalah jembatan `COMPONENT -> MATERIAL INPUT`

Artinya fokus paling efisien sekarang adalah:
1. `component batch`
2. `preview material component batch`
3. `monthly profile drift repair per allocation`

Setelah tiga titik ini beres, baru masuk akal mengejar:
- `component adjustment`
- `component reconcile`
- `POS component stock commit`
