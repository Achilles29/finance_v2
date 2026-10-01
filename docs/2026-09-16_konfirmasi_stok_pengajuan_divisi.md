# Kontrol stok pada pengajuan divisi — Batch 263

Status review 2026-09-16 06:21 WIB (Batch 264): **IMPLEMENTED / REVIEW_OPEN / NOT_RELEASED**. SQL **USER_REPORTED_APPLIED** sesuai konfirmasi pengguna, belum postcheck langsung. Ada tiga temuan terbuka di bawah; belum layak dinyatakan bebas bug. Tidak mengakses atau mengubah DB aplikasi. Pegangan progress tetap `_30`; handoff penjualan `_28`.

## Perubahan untuk pengguna

1. Buka **Pengajuan Divisi / PO–SR** (`/procurement/division-po-sr`), pilih pengajuan SUBMITTED lalu **Verifikasi Pengajuan**.
2. Tinjau panel **Cek stok sebelum menyetujui kebutuhan**. Setiap material menampilkan jumlah yang diminta, stok divisi peminta, stok gudang, satuan dan waktu pembaruan sumber. Kolom **Snapshot Gudang** pada tabel tetap terpisah; bukan saldo divisi.
3. Contoh: Kopi diminta 500 GR, divisi masih memiliki 1.000 GR. Hubungi penanggung jawab divisi, isi namanya dan alasan kebutuhan (misalnya event esok hari), lalu centang pernyataan konfirmasi. Pengajuan tidak otomatis ditolak karena stok masih ada.
4. Stok negatif atau **Belum diketahui** juga memerlukan konfirmasi. Tidak ada catatan saldo/pemetaan/konversi bukan berarti stok nol. Jika saldo divisi benar-benar nol dan gudang terbaca normal, alasan tambahan tidak wajib.
5. Selesaikan review baris/vendor seperti sebelumnya, lalu simpan verifikasi. Bila baris, lokasi atau saldo berubah, atau tinjauan lebih dari 15 menit, klik **Perbarui cek stok**, periksa dan konfirmasi ulang.
6. Bukti tampil pada detail pengajuan dan detail SR/PO yang dibentuk darinya: pelaku verifikasi, waktu, nama pihak divisi, alasan dan angka saat verifikasi. Angka riwayat bukan saldo terbaru. Dokumen lama tidak diberi bukti persetujuan buatan.

Tersedia pula panel informasi saat membuat/mengedit pengajuan. Kewajiban konfirmasi diterapkan ketika Purchase **memverifikasi**, bukan saat divisi baru mengajukan. Ini pencatatan pernyataan operator setelah berkomunikasi dengan divisi, bukan sistem persetujuan kedua atau pengiriman pesan otomatis.

## Aktivasi SQL — sudah dijalankan menurut konfirmasi pengguna

- [x] `sql/2026-09-16a_procurement_stock_review.sql` — **USER_REPORTED_APPLIED**, dicatat 2026-09-16 06:21 WIB. Waktu pencatatan bukan waktu eksekusi terverifikasi. Target perintah sebelumnya `db_finance`; output postcheck belum diterima.
- [ ] Cocokkan kolom/index/FK pada instance aplikasi dan uji penyimpanan. Konfirmasi apply belum sama dengan **SCHEMA_VERIFIED**.
- Membuat satu tabel `pur_division_stock_review`, unique per request dan foreign key ke `pur_division_request`. Memerlukan tabel induk InnoDB dengan ID BIGINT UNSIGNED seperti baseline.
- Tidak ada perubahan saldo, master, menu, izin, kredensial atau perbaikan data lama. Menggunakan hak akses procurement yang sudah ada.
- Perintah apply berikut hanya arsip batch sebelumnya, **jangan dijalankan ulang untuk review ini**:

```bash
mysql -u root -p db_finance < /www/wwwroot/finance/sql/2026-09-16a_procurement_stock_review.sql
```

Tidak ada SQL baru pada review ini. Agent tidak menjalankan ulang SQL, mengganti checksum, atau mengubah status header file migrasi yang merupakan artefak statis. Postcheck yang dibutuhkan adalah output `SHOW COLUMNS`, `SHOW INDEX` dan `SHOW CREATE TABLE pur_division_stock_review` pada database aplikasi. Tidak perlu membaca isi transaksi. Jika UI masih mengatakan pencatatan belum aktif, cek target database/file aplikasi lewat IDE; jangan mengulang semua SQL.

Tabel riwayat dipertahankan bila rollback kode; jangan drop atau menghapus bukti sebagai jalan pintas.

## Dasar teknis dan batas scope

- Reader memakai `inv_division_monthly_stock` dan `inv_warehouse_monthly_stock`, bukan lot/FIFO atau rebuild ledger. Sama-sama basis profile canonical/latest month hingga bulan berjalan seperti UI inventory. Memilih bulan terbaru per identity, deduplikasi shadow profil, menjumlahkan profil material; tidak menjumlahkan snapshot antarbulan.
- Pemetaan melalui `mst_item.material_id`/`mst_material`, konversi aktif langsung atau kebalikan pada `mst_uom_conversion`. Konversi hilang/konflik menghasilkan UNKNOWN; tidak menerka faktor. Satuan beli tidak dibandingkan langsung dengan gram/kg isi.
- Saldo **sistem** material lintas profil di divisi/lokasi yang dipilih; bukan hasil hitung fisik, estimasi kebutuhan, stok sudah direservasi, atau jaminan ketersediaan profil/lot tertentu untuk SR. BAR dan BAR_EVENT tidak digabung. Riwayat bulan lama yang masih menjadi sumber tetap dilabeli waktu sumber.
- Snapshot dibaca ulang saat save; tanda tangan terikat pada sesi, actor, request/revisi, divisi, lokasi, bulan, semua baris dan bukti saldo. Input berubah membatalkan konfirmasi UI. Response preview lama yang datang terlambat tidak menimpa hasil lebih baru.
- Preview POST + CSRF + scope existing, JSON dibatasi 100 baris/128 KiB; stok tidak dibocorkan untuk request divisi lain. Bukti disimpan dalam transaksi verifikasi sebelum link/dokumen dibuat, disertai lock request dan unique evidence. Kegagalan transaksi tidak dilaporkan sukses.
- Batas freshness: snapshot point-in-time, bukan lock/reservasi seluruh stok. Stock writer lain dapat berjalan setelah pemeriksaan; guard ketersediaan saat pemenuhan SR tetap diperlukan. Tes SQLite tidak membuktikan locking/isolasi MariaDB.
- **Belum batch ini**: daftar pengajuan lain/SR/PO outstanding dan pengaman pada SR/PO yang dibuat langsung tanpa pengajuan divisi. Tahap berikut wajib membedakan pending approval, approved-not-fulfilled dan received/void agar tidak menghitung satu kebutuhan dua kali.
- SQL belum terdaftar sebagai migrasi managed/allowlisted; tidak ada perubahan Control/release/deploy. Data konfirmasi adalah data operasional: tabel customer baru harus kosong, tidak diambil dari staging.

## File batch

- Library baru `application/libraries/Procurement_stock_review.php` dan SQL `2026-09-16a`.
- `application/controllers/Procurement.php`, `Purchase.php`; `application/models/Procurement_model.php`; `application/config/routes.php`.
- `application/views/procurement/division_po_sr_form.php`, `division_po_sr_detail.php`, `store_request_detail.php`, `_stock_review_panel.php`, `_stock_review_history.php`; `application/views/purchase/order_detail.php`.
- `assets/js/procurement-stock-review.js`.
- Tes `tools/tests/procurement_stock_review_smoke.php`, `procurement_stock_review_verify_smoke.php`, `procurement_stock_review_client_smoke.cjs`, dua file quality gate dan `roadmap_consistency_smoke.php`.
- Dua roadmap, laporan ini, execution log Batch 263. Perubahan existing di file yang sama dipertahankan.

## Validasi dan acceptance

- [x] Reader/policy/actual partial view: **52 PASS** (SQLite memory). Konversi, event/scope, canonical/latest month, negative net-zero, unknown, token tampering/expiry/actor/session/revisi, XSS, panel create/verify.
- [x] Actual model verify/normalizer/identity + HTTP controller: **114 PASS**. PO failure boundary terkontrol; evidence/link/line/status rollback, duplicate verification, begin/commit failure, missing SQL, operasional murni, POST/CSRF/scope/payload, generic error. Writer PO di-stub; bukan end-to-end stock/SR/PO runtime.
- [x] Actual JS + DOM sintetis: **19 PASS**. Mandatory reason, refresh, changing lines/location, stale response race, HTTP failure, migration pending, create/operational mode. Bukan browser/visual UAT.
- [x] Regresi Roastery/mobile layout **48 PASS**, quality-gate manifest contract **28 PASS**, roadmap consistency **26 PASS / 32 SQL top-level**; PHP lint, JS syntax dan diff whitespace scoped lulus setelah perbaikan penutup kondisi view yang terdeteksi lint.
- [x] Apply dilaporkan pengguna pada Batch 264. Belum ada SQL tambahan.
- [ ] Postcheck MariaDB dan browser desktop/mobile. Uji pengguna berhak vs di luar scope; contoh divisi stok 1.000 GR/ajuan 500 GR, gudang 2 KG ditampilkan 2.000 GR; stok nol, negatif, unit tidak terpetakan, profil sama lintas lokasi.
- [ ] Uji dua verifier bersamaan; ubah saldo/baris setelah preview; pastikan permintaan cek ulang jelas. Simpan sukses sekali dan lihat bukti yang sama di SR/PO terkait; kegagalan tidak meninggalkan bukti/dokumen parsial.
- [ ] Validasi artifact customer, migrasi/replay clean-install/upgrade dan tabel bukti kosong. Tidak menganggap source siap sama dengan release siap.

## Hasil review ulang — temuan masih terbuka

Review ini tidak mengimplementasikan fix runtime. Angka PASS di atas adalah cakupan tes lama, bukan bukti semua kondisi aman. Seluruh reproduksi di bawah menggunakan SQLite memory/DOM sintetis, tanpa data transaksi nyata atau koneksi DB Finance.

### PR-01 — Tinggi: edit dapat menimpa pengajuan yang baru diverifikasi

- Lokasi: `application/models/Procurement_model.php:1513` dan `:1546`, method `update_division_request()`.
- Penyebab: status dan jumlah dokumen terkait diperiksa sebelum transaksi. Sesudah normalisasi baris, update hanya memakai ID tanpa lock/recheck status/link. Verifikasi sudah memakai lock, tetapi jalur edit lama tidak mengikuti pengaman yang sama.
- Reproduksi terisolasi: setelah pemeriksaan edit lolos, fixture menyisipkan keadaan sudah VERIFIED + PO/link/bukti. Method edit asli tetap sukses, status kembali SUBMITTED dan qty berubah menjadi 900, sementara tautan PO/bukti lama tetap ada. Ini simulasi urutan dua proses, bukan hasil load test dua koneksi MariaDB.
- Dampak: pengajuan dan hasil verifikasi bisa berbeda. Tidak cukup diperbaiki dengan mengulang SQL.
- Mitigasi sementara: satu operator menangani satu pengajuan; jangan edit bersamaan dengan Purchase memverifikasi. Dua tab dibuka saja belum tentu mereproduksi: bentrokan terjadi di antara cek awal dan penulisan server.
- [ ] Fix prioritas pertama: lock request dan periksa status/revisi/link kembali di transaksi jalur edit; sertakan reject/void dalam review writer terkait; lalu test konkurensi MariaDB pada data uji.

### PR-02 — Sedang: gagal baca pemetaan dapat melewatkan konfirmasi

- Lokasi: `application/libraries/Procurement_stock_review.php:35`.
- Terbatas pada barang berlabel **OPERASIONAL**, `material_id` pada payload kosong, tetapi item sebenarnya terhubung material. Bila lookup pemetaan gagal sesaat, exception ditelan dan item dianggap bukan material, sehingga baris hilang dari review. Validator menerima snapshot kosong tanpa konfirmasi.
- Reproduksi: item fixture terhubung Kopi muncul sebagai satu baris sebelum error; ketika query pemetaan sengaja dibuat gagal, hasil menjadi nol baris dan validasi tanpa bukti diterima.
- Ini bukan berarti seluruh alur BAHAN_BAKU bisa dilewati; kasus spesifik yang belum tercakup tes sebelumnya.
- [ ] Bedakan "diketahui bukan material" dengan "pemetaan gagal dibaca". Kasus kedua harus UNKNOWN/wajib ditinjau atau retry, bukan dilewati.

### PR-03 — Sedang: permintaan preview menggantung tanpa pemulihan UI

- Lokasi: `assets/js/procurement-stock-review.js:63`.
- Fetch menonaktifkan tombol Perbarui, tetapi belum memiliki timeout/abort aplikasi. Selama request tidak selesai, tombol tetap nonaktif dan verifikasi tertahan. Berbeda dengan respons HTTP error yang sudah ditangani.
- Reproduksi DOM sintetis: promise fetch sengaja tidak selesai; tidak ada timer recovery terdaftar, tombol refresh nonaktif dan submit ditolak. Durasi timeout jaringan browser asli tidak diuji.
- Mitigasi sementara: catat input yang belum tersimpan, pastikan jaringan pulih, lalu muat ulang. Jangan menganggap verifikasi sudah tersimpan bila belum ada hasil.
- [ ] Tambahkan timeout/abort, pesan retry yang jelas dan pemulihan tombol tanpa memakai snapshot lama.

Probes review yang dapat dijalankan tanpa DB:

```bash
php tools/tests/procurement_stock_review_review_probe.php
node tools/tests/procurement_stock_review_review_client_probe.cjs
```

Pada review ini keduanya **exit 1 karena menemukan bug** (PHP PR-01/PR-02, JS PR-03). Bukan kegagalan SQL atau tes yang dinyatakan PASS. Belum dimasukkan sebagai quality gate kelulusan; saat diperbaiki, ubah menjadi regression assertion atas perilaku yang benar dan daftarkan gate. Tidak ada runtime atau test lama yang diubah untuk membuat hasil hijau.

## Checklist tes manual pengguna

Gunakan staging dan pengajuan uji terpisah dengan catatan `UAT-STOK-<tanggal>`. Buka `/procurement/division-po-sr`. Catat nama material, lokasi, nomor pengajuan, tanggal/jam, hasil dan screenshot. Jangan mengubah stok/master produksi demi menciptakan kasus. Bila kasus tidak tersedia, tandai **BELUM DIUJI**, bukan LULUS. Semua kotak di bawah menunggu tes Anda, bukan ditandai otomatis oleh agent.

### A. Pengecekan tampilan — tanpa menyimpan verifikasi

- [ ] **U01 — Aktivasi:** buka pengajuan SUBMITTED sebagai Purchase. Panel cek stok tampil dan tidak ada pesan "pencatatan belum aktif". Bila masih ada, catat screenshot; bukan instruksi apply ulang.
- [ ] **U02 — Saldo lokasi:** pilih satu bahan baku yang dikenal, catat pengajuan/stok divisi/stok gudang. Cocokkan dengan halaman stok untuk **material, lokasi dan satuan yang sama**; jumlahkan profil bila lebih dari satu. Jangan membandingkan satu profil dengan total material atau saldo fisik dengan saldo sistem.
- [ ] **U03 — Konversi:** bila tersedia kasus gudang 2 KG dan pengajuan GR, panel seharusnya menunjukkan 2.000 GR, bukan 2 GR. Jika konversi belum ada, harus "Belum diketahui", bukan nol.
- [ ] **U04 — Reguler/event:** ganti lokasi BAR ke BAR_EVENT (atau divisi lain yang relevan). Saldo yang ditampilkan mengikuti lokasi tersebut; stok gudang pusat boleh tetap sama. Konfirmasi sebelumnya harus dibatalkan.
- [ ] **U05 — Nol vs tidak diketahui:** stok nol yang diketahui tampil 0; stok tidak terbaca tidak ditulis 0. Jika divisi 0 dan gudang normal, nama/alasan tambahan tidak wajib. Jika stok negatif atau belum diketahui, konfirmasi wajib.
- [ ] **U06 — Ponsel:** buka di ponsel dan desktop; kartu stok, nama/alasan, checkbox dan tombol dapat dibaca/dipakai. Tabel panjang boleh digeser horizontal di dalam tabel; tombol aksi terakhir harus terjangkau.

### B. Validasi isian — sebelum hasil akhir tersimpan

- [ ] **U07 — Blok persetujuan tanpa alasan:** pilih material dengan stok divisi positif (misalnya 1.000 GR). Review seluruh baris, lalu coba simpan dengan nama/alasan/checkbox kosong bergantian. Masing-masing harus ditolak; tidak terbentuk SR/PO. Jangan menyelesaikan ketiganya sampai siap U10.
- [ ] **U08 — Isi berubah:** setelah mengisi konfirmasi, ubah qty/barang/vendor atau lokasi lalu simpan review baris. Konfirmasi lama harus kosong/tidak berlaku; tunggu cek baru dan konfirmasi ulang. Tidak cukup mengubah field modal tanpa menyimpan review baris.
- [ ] **U09 — Kedaluwarsa:** biarkan tinjauan lebih dari 15 menit tanpa mengubah isian, lalu coba simpan. Server harus meminta cek ulang atau login ulang bila sesi habis; tidak menggunakan bukti lama. Refresh stok, tinjau dan ulangi konfirmasi.

### C. Penyimpanan — membuat dokumen uji, lakukan terencana

Verifikasi sukses dapat membuat **SR APPROVED dan/atau PO DRAFT**. Jangan lanjut fulfill/receive/pay dan jangan gunakan order nyata untuk percobaan gagal/ulang. Karena PR-01 masih terbuka, hindari dua operator melakukan edit/verifikasi pengajuan yang sama sampai fix selesai.

- [ ] **U10 — Sukses satu kali:** pada pengajuan uji isi nama pihak divisi, alasan minimal 10 karakter dan centang konfirmasi; review semua baris dan pilih vendor yang diperlukan. Simpan sekali. Status menjadi VERIFIED dan dokumen hasil sesuai split SR/PO.
- [ ] **U11 — SR murni/PO murni/campuran:** gunakan pengajuan uji terpisah sesuai kasus yang tersedia. Untuk SR, pilih sumber gudang/profil yang sesuai; stok material total tidak menjamin profil tertentu tersedia. Jumlah ke SR + ke PO harus sama dengan jumlah pengajuan. Tunda varian yang tidak punya data uji sesuai.
- [ ] **U12 — Bukti turun ke dokumen:** buka detail pengajuan dan nomor SR/PO terkait. Nama verifikator, waktu, nama pihak divisi, alasan dan qty snapshot harus sama. Snapshot dilabeli sebagai bukti saat verifikasi, bukan saldo saat ini.
- [ ] **U13 — Tidak ganda:** setelah sukses, muat ulang detail dan coba kembali ke halaman verifikasi. Tidak boleh membentuk SR/PO tambahan untuk request yang sama. Jangan memakai dua operator untuk menguji race ini sebelum PR-01 diperbaiki.
- [ ] **U14 — Bukan transaksi stok baru:** setelah verifikasi saja, bandingkan saldo material sebelum/sesudah dengan lokasi/satuan yang sama dan tanpa transaksi lain berjalan. Pencatatan konfirmasi tidak boleh menjadi mutasi stok; fulfillment/penerimaan adalah proses terpisah.
- [ ] **U15 — Operasional murni:** pengajuan uji barang operasional yang benar-benar tidak terkait material tetap dapat lewat alur lama setelah syarat baris/vendor dipenuhi. Jangan memakai item terkait material sebagai pengganti kasus ini (PR-02).

### D. Akses, sejarah, dan tes lanjutan

- [ ] **U16 — Batas divisi:** login dengan akun divisi terbatas; URL pengajuan divisi lain dan endpoint preview-nya harus ditolak. Akun Purchase yang memang diberi akses lintas divisi boleh melihat sesuai izin existing; bukan bug jika aksesnya memang luas.
- [ ] **U17 — Dokumen lama:** buka pengajuan/SR/PO sebelum fitur ini. Halaman tetap terbuka tanpa riwayat konfirmasi buatan; ketiadaan bukti lama bukan bug.
- [ ] **U18 — Saldo berubah sesudah preview:** hanya pada data uji terisolasi, sesudah meninjau stok biarkan transaksi stok uji yang sah mengubah saldo lalu coba simpan. Bukti lama harus ditolak dan diminta cek ulang. Jangan menulis query repair untuk membuat skenario ini.
- [ ] **U19 — Jaringan dan konkurensi setelah fix:** retest PR-01/PR-02/PR-03 lewat fixture/lingkungan uji; jangan memutus layanan DB/server yang dipakai bersama. Pengujian dua koneksi MariaDB dan E2E writer SR/PO tetap diperlukan.

Format laporan hasil per kasus: `Uxx | LULUS/GAGAL/BELUM DIUJI | nomor pengajuan | jam | hasil yang terlihat | screenshot`. Stop percobaan tulis bila status tidak konsisten atau muncul dokumen ganda; jangan hapus bukti atau langsung mencoba submit berulang.

Urutan berikutnya: perbaiki PR-01, PR-02, PR-03 → ulangi regression dan UAT → baru deteksi pengadaan outstanding. Outstanding, guard SR/PO manual langsung, dan packaging customer adalah batas scope yang sudah dicatat, bukan bug baru yang diam-diam dianggap selesai.
