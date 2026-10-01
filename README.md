# Finance

Paket customer alpha.20 / profil v7 menggunakan satu folder, document root `public/`,
dan pemasang `/setup`. Mulai dari [MULAI-DI-SINI.html](MULAI-DI-SINI.html) atau
[panduan teks](MULAI-DI-SINI.txt) di folder utama. Admin menjalankan persiapan satu
perintah lalu customer melanjutkan di browser. Detail: [persiapan admin](docs/customer_single_folder_admin.md).
Release lama tidak otomatis berubah. Windows belum disetujui untuk paket ini.

Instalasi customer alpha.16 menggunakan **`config/customer.json`** (salin
template `config/customer.example.json`). Ikuti [panduan instalasi lokal](docs/customer_local_install.md)
untuk database kosong, konfigurasi web aman, lisensi dan login pertama. Jangan
menyertakan file konfigurasi berisi credential dalam Git atau paket release.

Finance adalah aplikasi operasional internal berbasis CodeIgniter 3 untuk:

1. purchase
2. inventory
3. produksi
4. POS
5. HR
6. payroll
7. fondasi finance/accounting

Repo ini adalah pengembangan lanjutan dari surface `core`, tetapi sekarang sudah punya arah arsitektur sendiri yang lebih tegas, terutama pada domain stok.

## Fokus Program Saat Ini

Fokus utama repo ini saat ini adalah **stabilisasi item-centric**.

Maknanya:

1. `item_id` menjadi identity utama create/write stok
2. `material_id` tetap dipakai sebagai marker/bridge produksi
3. `usage_purpose` menentukan perilaku bisnis
4. `line_kind` dan `stock_domain` tidak lagi boleh menjadi decision source runtime
5. runtime aktif tidak lagi boleh bergantung pada `daily_rollup` dan `stock_balance`

## Tujuan Akhir

Target akhir yang sedang dikejar:

1. tidak ada lagi create/write snapshot aktif yang membelah identity menjadi `ITEM` vs `MATERIAL`
2. tidak ada lagi penggunaan aktif `line_kind` sebagai decision source
3. tidak ada lagi penggunaan aktif `stock_domain` sebagai decision source
4. arah schema final menuju **tidak ada lagi kolom/field/tabel aktif yang memakai `MATERIAL` sebagai identity stok utama**
5. `/pos/stock-live` dan `/pos/stock-commit-audit` sinkron
6. job gagal POS tidak muncul lagi karena drift identity legacy
7. stok gudang, stok bahan baku, stok component, stok produk, dan HPP live memakai identitas serta rumus yang konsisten

## Status Saat Ini

Secara umum, kondisi repo sekarang:

1. write path purchase/procurement sudah jauh lebih item-centric
2. adjustment dan beberapa jalur posting stok sudah lebih tahan terhadap stale monthly/FIFO drift
3. runtime aktif sudah diputus dari `daily_rollup` dan `stock_balance`
4. POS live cost sudah lebih selaras dengan weighted aggregate monthly
5. audit dan compare tertentu masih perlu dibersihkan dari logika legacy

Hotspot paling penting saat ini:

1. `application/models/Purchase_model.php`
2. `application/controllers/Inventory_tools.php`
3. `application/libraries/PosOrderStockService.php`
4. `application/libraries/InventoryLedger.php`

## Mulai Dari Sini

Kalau membuka repo ini untuk pertama kali atau melanjutkan task yang terputus, baca dokumen ini berurutan:

1. [docs/README.md](/c:/xampp/htdocs/finance/docs/README.md)
2. [docs/SETUP.md](/c:/xampp/htdocs/finance/docs/SETUP.md)
3. [docs/ROADMAP.md](/c:/xampp/htdocs/finance/docs/ROADMAP.md)
4. [docs/MODULES.md](/c:/xampp/htdocs/finance/docs/MODULES.md)

Untuk konteks item-centric terbaru:

1. [docs/2026-06-07e_item_centric_progress_handover.md](/c:/xampp/htdocs/finance/docs/2026-06-07e_item_centric_progress_handover.md)
2. [docs/2026-06-07f_item_centric_runbook_short.md](/c:/xampp/htdocs/finance/docs/2026-06-07f_item_centric_runbook_short.md)
3. [docs/2026-06-07g_item_centric_hotspot_matrix.md](/c:/xampp/htdocs/finance/docs/2026-06-07g_item_centric_hotspot_matrix.md)

## Dokumentasi Aktif

Dokumen aktif yang paling penting saat ini:

1. `docs/SETUP.md`
2. `docs/CODING_STANDARDS.md`
3. `docs/ROADMAP.md`
4. `docs/MODULES.md`
5. `docs/2026-06-07e_item_centric_progress_handover.md`
6. `docs/2026-06-07f_item_centric_runbook_short.md`
7. `docs/2026-06-07g_item_centric_hotspot_matrix.md`

## Catatan Penting

Beberapa keputusan teknis dianggap final sampai ada alasan yang sangat kuat:

1. `mst_item` dan `mst_material` tetap dipisah
2. `material_id` adalah marker/bridge, bukan identity utama snapshot aktif
3. `daily_rollup` dan `stock_balance` bukan source runtime aktif
4. `line_kind` dan `stock_domain` arahnya dipensiunkan lalu dihapus
5. bulk rewrite `MATERIAL -> ITEM` tidak boleh dilakukan tanpa audit collision

## Setup Singkat

1. taruh repo di `c:\xampp\htdocs\finance`
2. siapkan database `db_finance`
3. jalankan SQL migration sesuai urutan
4. konfigurasi `application/config/database.php`
5. konfigurasi `application/config/config.php`

Detail lengkap tetap di:

1. [docs/SETUP.md](/c:/xampp/htdocs/finance/docs/SETUP.md)

## Ringkasan Arah Kerja

Kalau harus melanjutkan coding dari status sekarang, urutan aman umumnya:

1. verifikasi ulang POS retry/mismatch
2. sapu helper legacy di `Purchase_model.php`
3. sapu tool operasional di `Inventory_tools.php`
4. audit pembacaan relation/costing yang masih bergantung pada legacy
5. deprecate tabel legacy di DB

Root README ini sengaja ringkas. Semua detail progres dan keputusan operasional dipusatkan di folder `docs/`.
