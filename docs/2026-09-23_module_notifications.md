# Integrasi notifikasi modul — 23 September 2026

## Untuk pengguna

1. Buka **WhatsApp → Pengaturan** atau **Telegram → Pengaturan**. Panel **Notifikasi dari modul Finance** berada di atas pengaturan koneksi bot.
2. Daftarkan grup WA melalui menu Grup WA; daftarkan chat pribadi/grup Telegram melalui daftar tujuan/Asisten Setup. Bot harus sudah terhubung. Master switch Telegram harus aktif.
3. Aktifkan **Self order masuk**, **Order online masuk**, dan/atau **Pengajuan PO / SR divisi**. Pilih penerima untuk masing-masing kejadian, lalu simpan. Maksimal 10 tujuan per kejadian. Awalnya semuanya nonaktif; tidak memakai grup internal coding secara otomatis.
4. Self order/online order baru diperiksa oleh jadwal bot, umumnya setiap menit. Tidak perlu membuka kasir. Pesan berisi nomor, outlet/meja, waktu, status, total, ringkasan item, dan tautan aplikasi yang tetap memerlukan login. Pesan bukan bukti pembayaran. Order lama sebelum aktivasi tidak dikirim; mengganti tujuan atau mengaktifkan ulang juga tidak menyiarkan riwayat lama.
5. Setelah divisi menyimpan pengajuan, halaman detail menampilkan **Kirim WA / Kirim Telegram** sesuai kanal aktif. Tombol juga tersedia pada daftar dokumen pengajuan. Klik untuk mengantrekan ringkasan pengajuan ke tujuan yang sudah diatur admin. Tidak membuka pilihan nomor bebas kepada pengaju. Pengajuan VOID/REJECTED/DRAFT tidak dapat dikirim.
6. Buka **Status 30 notifikasi terakhir** pada pengaturan kanal. `Menunggu jadwal bot` bukan berarti terkirim. `Gagal` dapat dicoba kembali oleh pengelola pengaturan; `Belum pasti` tidak dicoba ulang otomatis karena pesan mungkin sudah diterima. Periksa chat tujuan terlebih dahulu.

**Batas WA pribadi:** aplikasi lama sengaja mengunci pengiriman WA personal untuk melindungi akun yang dibatasi (`Whatsapp::PERSONAL_OUTBOUND_ENABLED=false`), dan engine memiliki pengunci sendiri. Integrasi tidak melewati keduanya. Nomor dapat didaftarkan saat integrasi nonaktif, tetapi aktivasi dengan nomor ditolak secara jelas. Grup WA dan chat pribadi/grup Telegram didukung. Pembukaan WA personal/kanal resmi merupakan keputusan terpisah, bukan mengganti credential atau env diam-diam.

## Revisi 23 September 2026, 20:47 WIB — tab WA dan pilihan grup

- [x] `/wa/settings`: empat tab Notifikasi, Koneksi & QR, Pengujian, Teknis & pemulihan. ID kontrol, CSRF, pembatas edit, dan fungsi lama dipertahankan. Tab terakhir kembali setelah simpan/reload; tampilan ponsel tidak melebar.
- [x] Checklist grup terpisah per modul, dapat memilih lebih dari satu; semua grup terdaftar terlihat termasuk nonaktif. Grup ber-ID tidak valid tetap terlihat dengan keterangan, tetapi tidak dapat dipilih/kirim. Urut nama grup, maksimal 10 tujuan tetap diperiksa server.
- [x] `available_targets()` pada validasi/worker WA tidak lagi memakai flag balasan `wa_group_map.is_active`. Flag tidak diubah oleh penyimpanan atau pengiriman; Telegram tetap mensyaratkan tujuan aktif. ID tujuan yang berubah tetap membatalkan payload lama. Perubahan flag balasan tidak mereset cutoff order.
- File runtime: `application/views/wa/settings.php`, `application/views/notifications/settings.php`, `application/models/Module_notification_model.php`, `application/libraries/Module_notification.php`. File ini sudah masuk allowlist customer; tidak menambah SQL, kontrak profil, atau dependensi.
- Uji terbaru: `module_notifications_smoke.php --disposable` **62 PASS**, MariaDB 10.11.10 nyata, sender/entitlement sintetis; database sementara `/var/lib/finance-notification-test-0662f7326e6687d4`, proses dihentikan. `module_notifications_ui_smoke.php` **74 HTML/DOM + 15 JS PASS**. `node tools/tests/wa_settings_browser.cjs` **19 PASS**, Chrome nyata dengan data/HTTP fixture lokal, termasuk pilihan ganda, readonly, tab restore, dan lebar layar 390px; screenshot `/tmp/finance-wa-settings-browser-P5VKjL/wa-settings-mobile.png`.
- Regresi WA settings CSRF **123**, env-save **71**, engine-control **160**, send-test **91**, inbound mutation disabled **18**, service-auth **31**, secret boundary **20**, log boundary **24**, CLI schedule **30** semuanya PASS. PHP lint dan diff whitespace PASS.
- **Quality gate global belum hijau pada HEAD sekarang:** `parallel` 133/136 PASS; `gap01-repository-runtime-boundary`, `deployment-secret-config`, `a4-release-preflight` gagal karena konfigurasi lokal `application/config/database.php` / `.user.ini` serta temuan scanner username fixture disposable pada baris 102. Koneksi fixture tersebut identik dengan HEAD sebelum revisi ini. Konfigurasi lokal sengaja tidak diubah sesuai instruksi pengguna; scanner tidak dilemahkan. Angka 136/136 pada bagian bukti implementasi awal di bawah bersifat historis, bukan hasil revisi sekarang.
- Parent HEAD revisi `f96dea8`; perubahan belum commit/push. Tidak membaca/mengubah credential, menjalankan SQL aktif, mengubah status grup/penerima aktif, menjadwalkan ulang bot, atau mengirim pesan sungguhan. UAT penerimaan pesan nyata tetap dilakukan operator dengan grup uji.

## Tambahan 23 September 2026 — PDF Daily Sales

- [x] Tambah event WA-only `DAILY_SALES`, switch dan penerima pada panel existing, serta `POST pos/reports/daily-sales/notify` / tombol **Kirim WA (PDF)**. Tidak mengubah `wa/settings.php`, `Whatsapp.php`, konfigurasi koneksi, engine, maupun layout/tab pengaturan hasil revisi pengguna. Partial pengaturan hanya ditambah bantuan Daily Sales dan label riwayat event baru.
- [x] Memakai `Pos_report_model::daily_sales_report()` dan template cetak yang sama, tanpa mengubah rumus/kueri bisnis. Filter tanggal/outlet divalidasi; pilihan outlet harus tersedia. Mode PDF menghilangkan tombol/auto-print dan memakai A4 landscape. Mode cetak browser lama tetap tersedia.
- [x] PDF immutable berdasarkan hash data/filter; tersimpan privat di `application/cache/wa-attachments`, file 0640; jalur/symlink/ukuran/header diperiksa. Scratch renderer terisolasi per panggilan dan hanya scratch milik sendiri dibersihkan. Cache mencegah regenerasi berkas pada klik ulang laporan sama. Prasyarat runtime sama dengan renderer yang sudah ada: Linux, PHP exec, timeout, `/usr/bin/google-chrome`, izin web/worker membaca folder lampiran.
- [x] POST + POS CSRF + hak view laporan existing + entitlement `SALES_REPORTING` dan `AUTOMATION_MESSAGING`; tujuan hanya dari pengaturan admin. Worker tidak memperlakukan ID tanggal laporan sebagai order POS. Flag aktif grup untuk balasan chat tidak diubah. OFF/downgrade membatalkan antrean; status ambigu tidak dicoba ulang otomatis.
- [x] Pengiriman melalui worker WA existing, tidak ada jadwal baru/pesan nyata saat pengujian. Tidak ada SQL baru dan tidak menjalankan SQL aktif. Menggunakan kolom lampiran dari SQL **existing** `2026-09-23b_module_notification_pdf_attachment.sql`.
- Uji aktual: **71 PASS** pada MariaDB 10.11.10 disposable (sender/entitlement sintetis, checksum tabel bisnis tetap); **74 HTML/DOM + 15 JS** panel existing; **19 Chrome UI** untuk tab/pilihan grup termasuk event baru; Daily Sales controller/filters/CSRF/RBAC/entitlement/PDF tests dan **10 JS** tombol. PDF nyata dari Chrome berhasil; `pdfinfo` A4 landscape 1 halaman tanpa JavaScript, `pdftotext` memuat seluruh bagian dan nilai fixture. File contoh `/tmp/finance-daily-sales-36175098a2698467/cache/wa-attachments/daily-sales-e45d7e95602aa8aa72e99b1fa3ceefffe7d522d0e2fc5b0216a354468446a81c.pdf`.
- Regresi khusus: WA CSRF 123, worker CLI 30, sales order sort 18, quality gate contract 28 PASS; PHP lint/diff check PASS. Daily Sales smoke akhir **58 PASS** termasuk renderer nyata, dan **10 JS PASS**; clean-package contract **125 PASS** (fixture, bukan instalasi live; tidak membuktikan gap SQL lampiran selesai). Tes client pengajuan diselaraskan dengan respons `text()` renderer revisi server lain tanpa mengubah client pengajuan produksi.
- **Batas validasi:** belum mengirim ke grup nyata atau menguji izin user PHP-FPM/wa-engine pada instalasi aktif. `feature_boundary_contract_smoke.php` global masih gagal pada aksi existing `procurement/division_po_sr_line_action` yang belum terpetakan; source Procurement tidak disentuh. Gate khusus endpoint Daily Sales untuk izin lengkap/entitlement hilang lulus; tidak mengklaim seluruh release siap.
- **Handoff Control:** library baru `Daily_sales_pdf.php` masuk allowlist, rules hash manifest diperbarui; source kandidat tetap alpha.23 / profil v11, parent `e0a42c2`, belum commit/push/publish. Hash profil `269e8ee19d6627b3dd8b519aca93e0edfdff66f097d4542104fc57e1d0486cd0`; manifest `9ec16685e302bf9e3b0fc55f2c0cf830ce9fe977e5acfecc6ae75c8ff275d415`. **Gap existing:** SQL lampiran `23b` dari revisi server lain belum masuk katalog/profil clean-install pada HEAD ini. Sebelum build customer baru, perlu registrasi/proof upgrade terpisah, bukan mengimpor ulang SQL non-idempotent atau menimpa artefak release. Batch ini tidak memperluas tugas ke migrasi/release.

UAT tambahan:

- [ ] Aktifkan Daily Sales dengan satu/beberapa grup, termasuk grup tanpa balasan bot; pilih tanggal/outlet, klik Tampilkan lalu Kirim WA.
- [ ] Pastikan PDF yang diterima sama nilai/filter dengan laporan, seluruh tabel terbaca; status antrean menjadi Terkirim.
- [ ] Klik ulang data sama tidak menambah pesan; data yang berubah boleh dikirim sebagai snapshot baru.
- [ ] Matikan Daily Sales sebelum worker berjalan: antrean tidak terkirim. Hak laporan/CSRF/entitlement yang tidak cukup ditolak.

## Koreksi PDF pengajuan PO/SR WA — 23 September 2026

- Penyebab: `createDivisionNotificationPdf()` langsung memasukkan baris detail ke template, tanpa `current_stock_rows()` yang dipakai PDF unduhan. Data `current_stock` tidak ada sehingga partial menampilkan “Tidak terkait bahan baku” untuk seluruh baris; konteks `division_id` dan alias catatan `line_notes` juga belum disertakan.
- Perbaikan: `prepareDivisionPoSrPrintRows()` dipakai bersama oleh jalur laporan/unduhan dan WA. Detail dilengkapi konteks pengajuan/divisi/lokasi serta alias catatan, lalu stok dibaca melalui reader kanonis yang sama. Template, rumus stok, status bahan baku, dan tata letak PDF tidak diubah. Tidak menandai semua item sebagai bahan baku secara paksa.
- Stok bahan baku/operasional yang terhubung material ditampilkan; stok tak tersedia tetap “Belum diketahui”, bukan nol atau non-bahan baku. Item operasional tanpa material tetap “Tidak terkait bahan baku”. Pembacaan hanya SELECT/PRAGMA pada tes, tidak memperbaiki saldo/data transaksi.
- Revisi deduplikasi lampiran menjadi `pdf-v2-stock`: pengguna boleh menekan Kirim WA lagi untuk versi terkoreksi setelah PDF lama. Klik berulang versi baru tetap dedup; riwayat/file/pesan lama tidak dihapus atau diganti otomatis. Telegram teks tidak berubah.
- Regresi antrean akhir `module_notifications_smoke.php --disposable`: **74 PASS** pada MariaDB 10.11.10, termasuk kirim versi terkoreksi setelah PDF lama, dedup versi baru, dan bukti SENT lama tetap utuh. Hanya sender sintetis; data aktif tidak diakses. Bukti database sementara `/var/lib/finance-notification-test-a8a483d21aa0aa02` (proses dihentikan).
- Validasi: `procurement_notification_pdf_stock_smoke.php --pdf` **52 pemeriksaan tambahan PASS**, setelah **52 stock-review PASS**, SQLite memory + renderer Chrome nyata. HTML PDF WA/unduhan identik untuk baris dan waktu baca yang sama; PDF asli diperiksa dengan `pdftotext` (saldo divisi/gudang, unknown, non-material, catatan). Bukti `/tmp/finance-po-sr-pdf-de8bd7395c11742b/cache/wa-attachments/100-20260923231249-9751fe885acc.pdf`. Regresi current stock **91 cumulative PASS**; panel notifikasi **74 DOM + 15 JS PASS**; quality gate contract **28 PASS**. Tes parity masuk gate required.
- Tidak menjalankan SQL aktif, mengirim WA nyata, mengubah pengaturan bot, commit/push, atau menyentuh perubahan Daily Sales yang masih belum commit. UAT: kirim ulang satu pengajuan uji lalu bandingkan kolom stok/catatan dengan unduhan pengajuan yang sama; angka wajar berbeda jika stok berubah di antara waktu pembuatan kedua PDF.

## Pemasangan / administrator

- Migrasi baru: `sql/2026-09-23a_module_notifications.sql`, klasifikasi schema, kebijakan **clean_install + upgrade**, bergantung pada foundation Telegram. Membuat dua tabel InnoDB kosong: `app_notification_rule`, `app_notification_queue`. Tidak membawa seed penerima, pesan, nomor, identitas usaha, atau credential.
- Status database aktif: **BELUM DIJALANKAN** pada saat implementasi/uji; konfirmasi operator diperlukan. Pengujian hanya menggunakan MariaDB disposable. Halaman bot lama tetap berfungsi tanpa migrasi dan menjelaskan migrasi yang diperlukan.
- Untuk database staging yang sudah dipastikan bernama `db_finance`, admin dapat menjalankan berikut (password diminta interaktif, tidak diletakkan dalam command):

```sh
mysql -u root -p db_finance < /www/wwwroot/finance/sql/2026-09-23a_module_notifications.sql
```

- Untuk customer gunakan jalur migrasi resmi upgrade/clean-install, bukan import seluruh folder SQL atau clean installer ulang.
- Memakai worker **yang sudah ada**: `php index.php whatsapp api_schedule_run` dan `php index.php telegram run_due` (`telegram process_queue` juga memproses integrasi). Tidak menambah cron ganda bila jadwal tersebut sudah berjalan. Jika belum, admin menjadwalkan tiap menit dengan PHP/runtime/user layanan Finance yang sesuai pada panduan bot. Thread ini tidak mengubah cron atau layanan host.
- Pemeriksaan read-only root crontab, `/etc/cron.d`, `/var/spool/cron`, dan `/www/server/cron` pada 23 September **tidak menemukan referensi langsung** worker WA/Telegram tersebut. Wrapper/jadwal eksternal belum diverifikasi. Karena itu aktivasi live perlu memastikan jadwal worker; bukan cukup apply SQL lalu menganggap pengiriman otomatis sudah berjalan. Cron internal bridge coding bukan pengganti worker aplikasi.
- Waktu worker terakhir tampil di panel; belum terdeteksi / lebih dari 5 menit ditandai peringatan. Telegram master switch OFF menghentikan pengiriman, bukan menghapus antrean/bukti.
- Tidak ada perubahan website publik, session APK, stok, transaksi, saldo, credential, Telegram bridge, atau Control. Tidak ada push/publish/deploy.

## Kontrak dan pengamanan

- Source order: `pos_order.order_channel=SELF_ORDER` atau `DELIVERY`, dibaca dari database aplikasi seperti reader POS saat ini. Tidak mengubah aplikasi member terpisah. Polling hanya membaca order tersimpan yang mempunyai line aktif; bukan jalur simpan/pembayaran.
- Switch per channel/per event dan pemeriksaan penerima dilakukan kembali di server. WA memakai grup terdaftar dengan JID valid tanpa memfilter `is_active` (flag ini untuk balasan chat masuk); Telegram tetap memakai target aktif. Penerima yang JID/chat ID-nya diganti tidak menerima payload lama. Menonaktifkan integrasi tidak dapat menarik pesan yang sudah terkirim.
- `AUTOMATION_MESSAGING` dan hak modul asal (`SELF_ORDER`, `ONLINE_ORDER`, `PROCUREMENT`) diperiksa; upgrade/downgrade lisensi tidak dilewati oleh SUPERADMIN. RBAC/settings edit dan CSRF existing tetap digunakan. Endpoint kirim memeriksa scope divisi sebelum membaca item pengajuan. Pengguna hanya punya view tidak mendapat kewenangan konfigurasi.
- Unique delivery key mencegah pengiriman ganda akibat polling/klik ulang. Revisi ringkasan pengajuan membentuk key baru. Worker menggunakan advisory lock per database/kanal; save settings menolak jika pengiriman sedang berjalan. Proses yang terputus setelah claim ditandai UNKNOWN, bukan langsung diulang.
- Tidak menjanjikan exactly-once pada jaringan eksternal. Timeout WA/Telegram tetap ambigu. Retry otomatis UNKNOWN sengaja dilarang. Hanya penolakan pasti boleh diproses ulang dari UI. Tidak menyalin error provider/secret mentah ke log.
- WA pribadi belum dibuka. Rate limit provider, liveness cron host, dan penerimaan oleh bot/grup nyata masih memerlukan UAT operator. Latensi bukan real-time push; normal mengikuti interval jadwal dan jumlah antrean (maksimal 10 delivery per run).

## Checklist uji manual

- [ ] Sesudah migrasi, pengaturan tampil normal; semua integrasi default OFF.
- [ ] Daftarkan/pilih tujuan uji milik sendiri dan hidupkan hanya kanal yang ingin diuji.
- [ ] Buat self order baru dan order online baru; nomor/item/outlet benar, pesan sekali per tujuan. Order kasir biasa dan order lama tidak ikut dikirim.
- [ ] Kirim pengajuan dari divisi yang sesuai melalui daftar/detail. Klik ulang tidak menggandakan; revisi pengajuan dapat dikirim lagi.
- [ ] Akun divisi lain tidak bisa mengirim pengajuan di luar scope melalui URL/API.
- [ ] Matikan switch, hapus centang grup WA, nonaktifkan target Telegram, atau batalkan pengajuan sebelum worker berjalan: tidak terkirim.
- [ ] Buka keempat tab WA; simpan pengaturan dan pastikan tab kembali sesuai bagian yang disimpan.
- [ ] Pilih lebih dari satu grup, termasuk grup WA nonaktif. Semua grup terpilih menerima; bot tetap tidak membalas chat di grup nonaktif.
- [ ] Putuskan koneksi bot: pengajuan/order tetap tersimpan. Periksa status gagal/ambigu; jangan menyimpulkan antrean berarti terkirim.
- [ ] Pastikan jadwal worker terakhir terus diperbarui dan penerimaan nyata di chat sesuai.

## Bukti validasi / paket

- `module_notifications_smoke.php --disposable`: 51 pemeriksaan lulus, CI query builder dan **MariaDB 10.11.10 nyata** dengan baseline asli, termasuk penolakan schema tanpa unique index deduplikasi. Pengiriman dan entitlement pada fixture sintetis; bukan pengiriman bot asli. Dua tabel bisnis order/line serta pengajuan mempertahankan checksum setelah worker berjalan. Bukti akhir: `/var/lib/finance-notification-test-1e9abdfccf46a15a` (database sementara dihentikan).
- `module_notifications_ui_smoke.php`: 50 pemeriksaan HTML/DOM; client JS 15 pemeriksaan dengan network/DOM sintetis. Pengamanan CSRF/role dan daftar route juga diuji regresi yang sudah ada.
- SQL didaftarkan dengan checksum dalam katalog, bukan pengecualian legacy. File library/model/view/JS/panduan ditambahkan ke allowlist customer, SQL ke `sql_sha256`; hash rules pada manifest diperbarui. Kontrak layout/profil tidak diubah: source kandidat tetap alpha.23 / CUSTOMER_CLEAN v11, dengan **cutoff/hash baru yang perlu direview Control**. Bukan perubahan artefak terbit.
- Belum commit/push; parent source saat mulai `077a35fc873b60880cce78196f822c38781c9bbb`. Tidak mengubah commit installer lokal atau konfigurasi `database.php`/`.user.ini`.
- Build resmi, review cutoff Control dan UAT penerimaan chat bukan bagian bukti sintetis ini.
- Quality gate `parallel`: **136/136 PASS** (130 required, 4 development, 1 release-contract, 1 preflight), exit 0. Pemeriksaan runtime/browser seluruh aplikasi, security dependency dan static release tidak dijalankan oleh profil parallel; ini bukan klaim full release-ready. Build terkurasi sintetis pada tes `c3_customer_clean_release_smoke.php` lulus termasuk allowlist/integritas dan penolakan kontaminasi; build resmi Control tidak dijalankan.
- PHP lint seluruh 32 file PHP baru/berubah dan `git diff --check` lulus. Tidak ada perubahan `application/config/database.php` atau `.user.ini`. Composer/dependency tidak diubah.
- `managed_migration_database_smoke.php --disposable`: PASS/exit 0 pada MariaDB 10.11.10, mencakup baseline + katalog baru, upgrade, replay, adopsi schema lama, serta penolakan drift/partial tanpa mutasi data aktif.

Hash untuk review cutoff (working tree, belum commit):

| File | SHA-256 |
|---|---|
| `app-manifest.json` | `a0acb6e5c5c84a9070eadc59e154b3fde69aaaf6fa6945f785c189fa8617c398` |
| `tools/release/customer_clean_profile.json` | `d16d7ee76a9ff2db2b72047f6a62d060950c37a07601fba62560364b477f2e38` |
| `tools/db/migration_catalog.json` | `ea73ff711b8a47943da0d4869635dd657ebd33bcf07b5679cc470a4fb74eca8c` |
| `sql/2026-09-23a_module_notifications.sql` | `4eda52e6ade8be12aeb22a8a3036f3084588394f48acaadb59fab4499639cd7f` |
