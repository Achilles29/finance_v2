# Item-Centric Progress Handover

Tanggal: 2026-06-07
Status dokumen: aktif
Tujuan dokumen: handover teknis dan pedoman eksekusi saat task item-centric dipause, dipindah, atau diteruskan oleh orang lain

## Ringkasan Eksekutif

Transformasi item-centric sudah berjalan cukup jauh, tetapi belum selesai total.

Fondasi utamanya sudah jelas:

1. `item_id` adalah identity utama untuk create/write stok
2. `material_id` dipertahankan sebagai marker dan bridge produksi
3. `usage_purpose` menentukan perilaku bisnis
4. `line_kind` dan `stock_domain` tidak boleh lagi menjadi sumber keputusan create/write

Progress paling besar yang sudah tercapai:

1. banyak write path purchase/procurement/request sudah diarahkan ke item-centric
2. runtime aktif sudah diputus dari `daily_rollup` dan `stock_balance`
3. audit dan compare mulai dibersihkan dari pembelahan `ITEM/MATERIAL`
4. POS live cost dan sebagian audit POS sudah diselaraskan

Sisa pekerjaan utamanya bukan lagi mendesain konsep, tetapi:

1. membersihkan helper/read model legacy yang masih memakai `line_kind` dan `stock_domain`
2. memastikan retry POS dan audit POS benar-benar bersih dari mismatch palsu
3. menutup fase deprecate/drop tabel legacy
4. mengarahkan schema ke kondisi akhir: tidak ada lagi pemakaian aktif kolom/tabel legacy untuk `MATERIAL` sebagai identity

## Latar Belakang

Sistem lama berkembang dengan dua identitas stok yang berjalan bersamaan:

1. `ITEM`
2. `MATERIAL`

Akibatnya:

1. satu entitas stok bisa punya bucket ganda
2. snapshot bisa pecah walau source transaksi sama
3. audit bisa membaca bucket yang berbeda dari posting
4. retry POS, batch component, adjustment, dan reconcile menjadi tidak deterministik
5. HPP live, saldo monthly, FIFO issue, dan stock audit bisa drift karena formula atau identity yang berbeda

Masalah besar yang sering terlihat di operasional:

1. `/pos/stock-live` dan `/pos/stock-commit-audit` tidak sinkron
2. job commit POS gagal dengan pesan saldo negatif padahal stok fisik/FIFO masih ada
3. mismatch bahan baku terlihat besar padahal sebagian berasal dari audit/read model legacy
4. stok gudang, stok divisi, stok bahan baku, stok component, dan stok produk tidak selalu memakai identitas atau rumus yang sama

Karena itu arah perbaikannya digeser ke item-centric.

## Tujuan Akhir

Ini tujuan akhirnya, dan semua keputusan lanjutan harus konsisten ke sini:

1. tidak ada lagi create/write snapshot aktif yang membelah identity menjadi `ITEM` vs `MATERIAL`
2. tidak ada lagi keputusan create/write yang didasarkan pada `line_kind`
3. tidak ada lagi keputusan create/write yang didasarkan pada `stock_domain`
4. `line_kind` dan `stock_domain` arahnya dipensiunkan lalu dihapus
5. tidak ada lagi kolom/field/tabel aktif yang memakai `MATERIAL` sebagai identity stok utama
6. `/pos/stock-live` dan `/pos/stock-commit-audit` tidak mismatch
7. job gagal POS tidak muncul karena legacy identity drift
8. stok gudang, stok bahan baku, stok component, stok produk, dan HPP live menggunakan identitas dan rumus yang konsisten
9. `daily_rollup` dan `stock_balance` tidak lagi menjadi sumber runtime aktif
10. seluruh pembacaan UI tentang bahan baku cukup berasal dari:
   - `material_id`
   - `usage_purpose`
   - konteks modul
   bukan dari snapshot `stock_domain='MATERIAL'`

## Keputusan Desain Final

### 1. Identity utama adalah `item_id`

Semua create/write snapshot aktif harus berpikir dengan `item_id` sebagai identity utama.

Berlaku untuk:

1. purchase order
2. purchase receipt
3. store request
4. division request
5. gudang
6. divisi
7. component bridge yang membaca bahan/item
8. POS
9. monthly stock
10. opening snapshot
11. movement projection
12. rebuild stock live

### 2. `material_id` tetap ada, tetapi bukan identity utama

`material_id` tetap dipertahankan untuk:

1. marker bahan baku
2. bridge ke recipe
3. bridge ke FIFO material
4. bridge ke costing/consumption produksi
5. tag UI dan audit

Rumus final:

1. `item_id` = identity utama
2. `material_id` = marker/bridge

### 3. `usage_purpose` menentukan perilaku bisnis

`usage_purpose` adalah decision source yang benar untuk perilaku stok.

Makna utamanya:

1. `BAHAN_BAKU` default = `Persediaan Produksi`
2. user boleh ubah ke `Kebutuhan Operasional`
3. jika diubah ke operasional:
   - line tetap item-centric
   - tidak diarahkan ke lane stok bahan baku produksi

### 4. `line_kind` dan `stock_domain`

Keputusan final terhadap keduanya:

1. saat ini masih ada sebagai legacy artifact
2. tidak boleh lagi dipakai untuk create/write decision
3. tidak boleh lagi dipakai untuk membelah snapshot aktif
4. untuk pembacaan histori lama masih boleh dibaca sementara
5. arahnya ke depan adalah dipensiunkan lalu dihapus

Catatan penting:

1. UI masih boleh menampilkan label “bahan baku/material”
2. tetapi itu harus dibaca dari `material_id` dan konteks, bukan dari `stock_domain`

## Non-Negotiable Decisions

Keputusan di bawah ini jangan dibatalkan saat melanjutkan task:

1. jangan bulk flip `MATERIAL -> ITEM` tanpa audit collision
2. jangan hidupkan lagi `daily_rollup` sebagai source runtime aktif
3. jangan hidupkan lagi `stock_balance` sebagai source runtime aktif
4. jangan gunakan `stock_domain` untuk create snapshot baru
5. jangan gunakan `line_kind` untuk create snapshot baru
6. jangan menganggap `BAHAN_BAKU` berarti otomatis harus lane `MATERIAL`
7. jangan membuat audit baru yang membandingkan source legacy yang sudah tidak lagi diisi

## Strategi Umum

Pendekatan yang dipakai dan tetap dianggap benar:

1. perbaiki script dulu
2. audit data
3. migrasi data terkontrol
4. normalisasi read model dan audit
5. deprecate legacy table
6. drop final hanya setelah jalur runtime benar-benar aman

Alasannya:

1. bulk rewrite data rawan bentrok `identity_key`
2. masih ada row histori yang perlu merge/rebuild, bukan flip langsung
3. banyak mismatch ternyata berasal dari audit legacy, bukan source transaksi aktif

## Progress yang Sudah Dilakukan

### A. Fondasi item-centric

Sudah diputuskan dan dipakai sebagai arah implementasi:

1. `item_id` = canonical identity
2. `material_id` = marker
3. `usage_purpose` = behavior
4. `line_kind` dan `stock_domain` = legacy only, bukan decision source

### B. Purchase lane

Sudah banyak dibersihkan supaya purchase write/search utama tidak lagi memecah snapshot aktif menjadi `MATERIAL`.

Yang sudah dilakukan:

1. purchase write path digeser ke item-centric
2. `material_id` tetap disimpan sebagai marker bahan baku
3. opening snapshot, adjustment, dan beberapa helper purchase dipaksa lebih dekat ke item-centric
4. daily compare tertentu mulai dibersihkan dari pembelahan `stock_domain`
5. audit POS yang memakai helper purchase mulai disesuaikan

Status:

1. write path membaik
2. `Purchase_model.php` masih hotspot legacy terbesar

### C. Procurement / division-po-sr / store request

Sudah dilakukan pembersihan di lane ini:

1. candidate/search request dipaksa lebih item-centric
2. payload write untuk line yang punya `item_id` diprioritaskan sebagai `ITEM`
3. fulfillment source context diprioritaskan ke lane item-centric
4. raw material linked item masih boleh tampil sebagai bahan baku di UI, tetapi bukan berarti write snapshot jadi `MATERIAL`

Status:

1. write path sudah cukup sehat
2. display dan helper tertentu masih bisa hybrid

### D. Adjustment / inventory posting

Sudah dilakukan:

1. pre-sync monthly exact profile dari FIFO truth sebelum negative mutation diposting
2. adjustment identity diarahkan ke item-centric
3. UI adjustment division dibuat bertingkat:
   - pilih jenis dulu
   - baru reason dan field lain muncul
4. alasan/jenis adjustment mulai diselaraskan agar tidak membingungkan user

Status:

1. pengalaman operasional lebih baik
2. posting negative adjustment lebih tahan terhadap monthly stale

### E. Daily rollup / stock_balance

Keputusan penting:

1. `daily_rollup` dan `stock_balance` bukan lagi source runtime aktif

Yang sudah dilakukan:

1. query runtime aktif sudah diputus dari tabel-tabel tersebut
2. method aktif yang masih bernama `daily_rollup` sudah di-rename
3. UI label diubah agar istilahnya jujur
4. audit dependency DB dan SQL deprecate disiapkan

Dokumen/SQL terkait:

1. `docs/2026-06-07b_legacy_daily_rollup_stock_balance_audit.md`
2. `sql/2026-06-07c_audit_legacy_table_db_dependencies.sql`
3. `sql/2026-06-07d_deprecate_legacy_daily_rollup_stock_balance_tables.sql`

Status:

1. runtime aktif sudah bersih
2. deprecate/drop DB belum dieksekusi

### F. Audit dan migrasi data legacy

SQL penting yang sudah dibuat:

1. `sql/2026-06-06e_item_centric_stock_domain_material_audit.sql`
2. `sql/2026-06-06g_item_centric_safe_material_to_item_candidates.sql`
3. `sql/2026-06-07a_item_centric_profile_key_collision_candidates.sql`

Makna hasil penting:

1. phase `06g` tidak menemukan lagi kandidat aman yang bisa di-flip langsung
2. artinya sisa masalah bukan lagi “flip gampang”, tetapi collision/read-model cleanup
3. sebagian besar mismatch yang tersisa bukan source movement aktif, tetapi pembacaan snapshot/audit yang masih legacy

### G. POS stock-live / stock-commit-audit

Ini salah satu fokus paling penting.

Yang sudah dilakukan:

1. rumus HPP live POS diselaraskan dengan weighted aggregate monthly
2. pembacaan live material snapshot tidak lagi bergantung pada `stock_domain='ITEM'`
3. audit POS diubah bahasanya agar tidak lagi menuntun ke pola target = `MATERIAL`
4. `PosOrderStockService` di jalur FIFO per-lot sudah ditambah:
   - `allow_negative_balance => true`
   - `force_avg_cost_per_content`
5. audit bahan baku di `Purchase_model` diubah:
   - identity harian tidak lagi memasukkan `stock_domain`
   - source compare legacy dinetralkan ke snapshot harian berbasis movement aktif
   - root cause audit hanya menghitung drift sungguhan

Status terakhir yang diketahui:

1. `/pos/stock-live` dilaporkan sudah tidak mismatch
2. `/pos/stock-commit-audit` dan job gagal POS masih perlu diverifikasi ulang setelah patch terbaru dan retry job

## Problem yang Sudah Berhasil Dipersempit

Masalah besar yang sekarang bentuknya sudah lebih jelas:

1. tidak semua mismatch adalah bug stok nyata
2. sebagian mismatch berasal dari audit/read model legacy
3. sebagian job gagal POS berasal dari jalur FIFO issue yang sukses, tetapi ledger per-lot masih ditolak guard saldo negatif
4. sebagian drift monthly berasal dari row exact profile yang stale dan perlu sync dari FIFO truth
5. beberapa compare material masih sempat memakai source lama yang sebenarnya sudah tidak aktif

## Hal yang Belum Selesai

### 1. `Purchase_model.php` masih hotspot terbesar

Masih banyak logic legacy yang perlu disapu:

1. helper compare/audit dengan asumsi `stock_domain`
2. builder identity yang masih menyisakan pola lama
3. query/report tertentu yang masih berpikir material-centric
4. jalur repair/compatibility yang belum semua item-centric

### 2. `Inventory_tools.php` masih perlu sapuan lanjut

Masih ada banyak indikasi:

1. payload contoh/smoke tool masih menyetel `stock_domain`
2. beberapa tool masih mengisi `line_kind='MATERIAL'`
3. beberapa report/audit CLI bisa memunculkan arah pikir lama

### 3. `Master_relation.php` masih punya jejak legacy

Masih ada query yang mem-filter `stock_domain='ITEM'` untuk pembacaan tertentu.
Ini perlu dicek agar tidak membatasi pembacaan item-centric secara salah.

### 4. POS retry/fail queue belum diverifikasi setelah patch terakhir

Masih perlu:

1. retry semua job gagal
2. refresh audit
3. ukur ulang angka:
   - failed jobs
   - mismatch bahan baku
4. jika masih ada sisa, bedah satu job gagal terakhir secara spesifik

### 5. Deprecate/drop tabel legacy belum dijalankan

Yang belum dilakukan:

1. audit dependency DB aktual via `07c`
2. rename tabel legacy via `07d`
3. observasi pasca-rename
4. drop final setelah masa aman

### 6. Schema final belum bersih

Masih ada target jangka lanjut:

1. semua decision source yang masih memakai `line_kind` harus dicabut
2. semua decision source yang masih memakai `stock_domain` harus dicabut
3. arah akhirnya adalah tidak ada lagi pemakaian aktif kolom/field/tabel identity legacy `MATERIAL`

## Hotspot File yang Harus Dianggap Berisiko

File-file ini paling penting untuk dilihat bila lanjut task:

### Hotspot sangat tinggi

1. `application/models/Purchase_model.php`
2. `application/controllers/Inventory_tools.php`
3. `application/libraries/PosOrderStockService.php`
4. `application/libraries/InventoryLedger.php`

### Hotspot tinggi

1. `application/models/Procurement_model.php`
2. `application/controllers/Master_relation.php`
3. `application/libraries/PosAvailabilityRebuildService.php`
4. `application/libraries/PosRuntimeJobService.php`
5. `application/libraries/MaterialFifoManager.php`

### Hotspot menengah

1. `application/controllers/Pos.php`
2. `application/models/Pos_model.php`
3. `application/libraries/ComponentStockWriter.php`
4. `application/models/Production_model.php`

## Daftar SQL Penting

### Audit / investigasi

1. `sql/2026-06-06e_item_centric_stock_domain_material_audit.sql`
2. `sql/2026-06-07a_item_centric_profile_key_collision_candidates.sql`
3. `sql/2026-06-07c_audit_legacy_table_db_dependencies.sql`

### Migrasi / cleanup terkontrol

1. `sql/2026-06-06g_item_centric_safe_material_to_item_candidates.sql`
2. `sql/2026-06-07d_deprecate_legacy_daily_rollup_stock_balance_tables.sql`
3. `sql/2026-06-08g_audit_division_monthly_fifo_profile_gaps.sql`
4. `sql/2026-06-08h_cleanup_division_monthly_fifo_targeted.sql`
5. `sql/2026-06-08i_cleanup_null_profile_safe_subset.sql`
6. `sql/2026-06-08j_balance_remaining_division_movement_mismatch.sql`

### Repair kasus spesifik yang relevan secara historis

1. `sql/2026-06-05e_repair_jahe_gajah_kitchen_exact_profiles.sql`
2. `sql/2026-06-06f_replace_lada_putih_bubuk_with_lada_putih_in_recipe_formula.sql`

### Purchase profile / cleanup historis

1. `sql/2026-06-06a_repair_purchase_profile_description_global.sql`
2. `sql/2026-06-06b_finalize_purchase_profile_description_aggregate_cleanup.sql`
3. `sql/2026-06-06c_finalize_remaining_profile_description_aggregate_rows.sql`
4. `sql/2026-06-06d_purchase_catalog_activate_latest_and_disable_zero_duplicates.sql`

## Unresolved Issues yang Harus Dianggap Masih Open

### 1. Verifikasi angka POS belum final

Status sebelum patch terakhir:

1. `/pos/stock-live` sudah tidak mismatch
2. job gagal POS masih ada
3. `/pos/stock-commit-audit` masih menunjukkan mismatch bahan baku

Status setelah patch terakhir:

1. belum diverifikasi ulang di lapangan
2. perlu retry dan refresh

### 2. Masih ada helper/query yang memproduksi atau membaca `stock_domain`

Walau banyak sudah dibersihkan, grep terakhir masih menunjukkan:

1. `Purchase_model.php` masih punya banyak jejak `stock_domain`
2. `Inventory_tools.php` masih punya payload/tool yang menyetel `stock_domain`
3. beberapa view/form masih menampilkan `line_kind`

### 3. Masih ada query yang menganggap `material_id` berarti `MATERIAL`

Ini harus terus dicurigai dan dihapus bertahap.

### 4. Audit data division monthly/FIFO/profile masih menyisakan gap riil

Snapshot audit lokal 2026-06-08 menunjukkan:

1. `inv_division_monthly_stock` berisi `10` row material tanpa `profile_key`
2. `inv_material_fifo_lot` tidak punya `lot_no` kosong, tetapi masih ada `1` open lot tanpa `profile_key`
3. `inv_stock_movement_log` masih punya `81` movement divisi material tanpa `profile_key`
4. ada `3` identity monthly material dengan saldo positif tetapi tidak punya open lot pasangan
5. ada `3` open lot material yang tidak punya monthly positif pasangan
6. masih ada `15` mismatch latest monthly vs cumulative movement dengan total selisih absolut `1517`

Contoh identity yang muncul dalam audit:

1. `AIR MINERAL GALON` BAR memiliki monthly `(profile_key null)` `5685`, movement `4925`, FIFO `5850`
2. `OREO CRUMB` BAR dan `SINGLE ORIGIN` BAR memiliki monthly positif tetapi tidak punya open lot pasangan
3. `SEREH`, `CARAMEL CRUMB`, dan `KANI STICK` punya open lot, tetapi latest monthly tidak punya saldo positif pasangan

Makna operasional:

1. problem lot saat ini lebih dominan berupa `saldo monthly tanpa pasangan lot` atau `lot stale tanpa saldo monthly`, bukan `lot_no` kosong
2. `profile_key` kosong pada material masih berisiko membuat exact identity FIFO tidak ketemu
3. fallback aggregate bisa tetap berjalan di beberapa kasus, tetapi exact FIFO issue/retry bisa meleset atau gagal bila identitas profile tidak sinkron

Eksekusi targeted cleanup 2026-06-08:

1. dibuat `3` open lot exact baru dari latest monthly positif yang sebelumnya belum punya pasangan lot:
   - `LEMON` BAR
   - `OREO CRUMB` BAR
   - `SINGLE ORIGIN` BAR
2. ditutup `3` stale open lot yang tidak punya latest monthly positif pasangan:
   - `CARAMEL CRUMB` BAR
   - `SEREH` BAR
   - `KANI STICK` KITCHEN
3. lot `AIR MINERAL GALON` BAR `id=839` disinkronkan dari `5850` menjadi `5685` agar sama dengan latest monthly
4. hasil setelah cleanup:
   - `positive monthly without open lot` = `0`
   - `open lot without positive monthly` = `0`
5. mismatch audit `latest monthly vs cumulative movement` tetap `15` row dengan total selisih absolut `1517`

Catatan penting:

1. mismatch `latest monthly vs cumulative movement` tidak dibersihkan paksa karena ada indikasi sebagian row adalah efek reset/repair historis dan aggregate fallback, sehingga `SUM movement` tidak selalu mewakili saldo runtime yang paling aman
2. `profile_key` kosong masih tersisa:
   - sebelum cleanup subset aman: `10` row monthly material, `1` open lot, `81` movement divisi material
   - setelah cleanup subset aman: `0` row monthly material, `0` open lot, `54` movement divisi material
3. cluster `AIR MINERAL GALON` BAR null-profile sudah di-canonical-kan ke `profile_key=13d3bbf64f3dcb772b1924e4f8938c3766cd9460`
4. placeholder `REBUILD` qty `0` dengan `profile_key null` yang tidak punya open lot pasangan sudah dihapus `9` row dari `inv_division_monthly_stock`
5. balancing movement targeted untuk `6` mismatch terakhir sudah diinsert dan hasil akhirnya:
   - mismatch `latest monthly vs cumulative movement` = `0`
   - gap `monthly positif tanpa lot` = `0`
   - gap `open lot tanpa monthly positif` = `0`

Status pasca cleanup 2026-06-08:

1. saldo aktif `monthly` dan `FIFO lot` untuk material divisi sudah jauh lebih sehat untuk exact identity
2. mismatch yang tersisa bukan lagi di saldo aktif, tetapi di histori movement null-profile yang belum semuanya punya canonical profile aman
3. movement history null-profile yang masih tersisa `54` row perlu dibersihkan bertahap hanya bila kandidat profile canonical-nya tidak ambigu

## Runbook Diagnosis

Kalau nanti muncul masalah baru, gunakan urutan diagnosis ini.

### A. Jika `/pos/stock-live` mismatch

Urutan cek:

1. cek apakah mismatch ada di qty, HPP, atau keduanya
2. cek `PosAvailabilityRebuildService` versus `Pos_model` resolver cost
3. cek apakah pembacaan live masih terseret `stock_domain`
4. cek apakah monthly source stale
5. cek apakah compare memakai source legacy yang sudah tidak aktif

### B. Jika `/pos/stock-commit-audit` mismatch bahan baku

Urutan cek:

1. cek apakah mismatch nyata atau false positive audit
2. cek balance vs movement
3. cek compare source apakah masih memakai source legacy
4. cek apakah identity harian masih terpecah karena `stock_domain`
5. cek root cause audit apakah masih menghukum profile item-centric yang sehat

### C. Jika job commit POS gagal

Urutan cek:

1. lihat pesan error paling akhir
2. cek apakah gagal di jalur FIFO per-lot atau fallback aggregate
3. cek `PosOrderStockService`
4. cek `InventoryLedger` negative balance guard
5. cek exact profile monthly stale atau tidak
6. cek apakah item/material/profile identity yang dipakai konsisten

### D. Jika adjustment gagal dengan saldo negatif

Urutan cek:

1. cek apakah monthly exact stale
2. cek apakah pre-sync FIFO lot sudah dipanggil
3. cek identity yang dipakai adjustment
4. cek apakah posting masih terpecah oleh `stock_domain`

### E. Jika HPP live drift

Urutan cek:

1. pastikan formula live memakai weighted aggregate yang sama
2. cek apakah source component/material sama
3. cek apakah cache live belum direbuild
4. cek apakah ada latest-row logic lama yang masih dipakai

## Prinsip Kerja yang Harus Dijaga

### 1. UI boleh membedakan bahan baku

Tetapi pembedaan itu hanya untuk:

1. badge
2. label
3. grouping
4. filter

Bukan untuk create/write identity.

### 2. Semua write baru harus item-centric

Artinya:

1. bila `item_id` ada, identity write harus item-centric
2. `material_id` tetap ikut bila ada
3. `usage_purpose` menentukan perilaku bisnis

### 3. Audit yang masih membandingkan source legacy harus dicurigai

Kalau ada mismatch:

1. cek apakah source itu masih aktif diisi
2. kalau tidak, jangan anggap itu bug stok nyata

### 4. `line_kind` dan `stock_domain` harus dipensiunkan

Tujuan akhirnya bukan sekadar “diabaikan”, tetapi:

1. tidak dipakai lagi di runtime decision
2. tidak dipakai lagi di create/write
3. akhirnya dihapus

## Larangan Teknis

Hal berikut sebaiknya tidak dilakukan:

1. bulk update `stock_domain='MATERIAL'` menjadi `ITEM` tanpa collision audit
2. menghidupkan kembali compare berbasis `daily_rollup`
3. menghidupkan kembali compare berbasis `stock_balance`
4. membuat helper baru yang menganggap `material_id > 0` berarti identity harus `MATERIAL`
5. membuat repair script yang menulis snapshot baru sebagai `MATERIAL`

## Rencana Kerja Ke Depan

### Prioritas 1. Verifikasi ulang POS setelah patch terakhir

Langkah:

1. buka `/pos/stock-commit-audit`
2. jalankan `Retry Semua Gagal`
3. refresh job
4. bandingkan lagi dengan `/pos/stock-live`
5. catat angka:
   - failed jobs
   - mismatch bahan baku
   - mismatch base/prepare

Kalau masih ada sisa:

1. ambil satu job gagal terakhir
2. trace identity exact profile/division/material
3. bedah itu sebagai kasus nyata

### Prioritas 2. Sapu besar `Purchase_model.php`

Target:

1. cabut helper compare/audit yang masih memakai `stock_domain` sebagai pembelah
2. cabut helper/report yang masih material-centric
3. samakan formula dan identity compare lintas gudang/divisi/POS

### Prioritas 3. Sapu `Inventory_tools.php`

Target:

1. hilangkan payload tool yang masih menulis `stock_domain`
2. hilangkan smoke/test payload yang masih memaksa `line_kind='MATERIAL'`
3. pastikan tool operasional tidak lagi menyesatkan arah item-centric

### Prioritas 4. Audit `Master_relation.php`

Target:

1. hapus filter pembacaan yang masih terlalu bergantung pada `stock_domain`
2. pastikan pembacaan recipe/costing konsisten item-centric + `material_id` marker

### Prioritas 5. Deprecate DB legacy

Urutan aman:

1. jalankan `07c`
2. jika aman, jalankan `07d`
3. observasi sistem
4. setelah aman, siapkan SQL final drop backup legacy

## Definisi Selesai

Task item-centric ini dianggap mendekati selesai bila:

1. tidak ada lagi create/write aktif yang membelah identity menjadi `ITEM/MATERIAL`
2. `line_kind` tidak lagi dipakai sebagai decision source
3. `stock_domain` tidak lagi dipakai sebagai decision source
4. `/pos/stock-live` dan `/pos/stock-commit-audit` sama-sama bersih
5. job gagal POS tidak lagi muncul karena drift identity legacy
6. HPP live gudang/divisi/component/POS memakai rumus yang konsisten
7. `daily_rollup` dan `stock_balance` sudah dideprecate aman
8. arah schema final sudah jelas menuju penghapusan aktif kolom/tabel legacy `MATERIAL`

## Handover Cepat

Kalau harus pindah task sekarang, pegangan singkatnya adalah:

1. konsep final sudah jelas: `item_id` identity, `material_id` marker
2. `usage_purpose` adalah decision source perilaku yang benar
3. `line_kind` dan `stock_domain` tidak boleh lagi jadi decision source, arahnya kehapus
4. runtime aktif sudah diputus dari `daily_rollup` dan `stock_balance`
5. banyak mismatch sisa sekarang lebih condong ke audit/read model legacy
6. POS sudah dipatch di titik penting, tetapi perlu verifikasi ulang via retry dan refresh
7. hotspot terbesar berikutnya adalah `Purchase_model.php`, `Inventory_tools.php`, dan deprecate DB legacy

