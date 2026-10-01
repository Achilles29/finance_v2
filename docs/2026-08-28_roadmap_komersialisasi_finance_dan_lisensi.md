# Roadmap Komersialisasi Finance POS

**23 September 2026 — Daily Sales PDF:** [x] renderer/library tercantum allowlist dan hash profil baru; tidak mengubah entitlement/kontrak lisensi. [ ] Sebelum build resmi: katalog/proof migrasi SQL lampiran `2026-09-23b` existing yang belum terdaftar, pemetaan aksi Procurement existing, review cutoff Control dan UAT izin renderer/worker. Ini bukan klaim release siap. [Rincian](2026-09-23_module_notifications.md).

**23 September 2026 — revisi UX WA:** [x] tab/checklist dan pemisahan grup balasan/notifikasi pada file yang sudah di-allowlist; tidak ada SQL/kontrak profil baru. [ ] review cutoff/build resmi tetap di Control; gate global pada HEAD sekarang masih terhambat konfigurasi lokal dan scanner fixture existing (lihat [laporan](2026-09-23_module_notifications.md)), bukan klaim release-ready.

**23 September 2026 — integrasi notifikasi:** [x] allowlist library/model/view/JS dan SQL `2026-09-23a` pada clean-install/upgrade; default OFF dan dua tabel kosong tanpa tujuan/pesan customer. [x] gate `AUTOMATION_MESSAGING` + modul asal. [ ] review cutoff/hash baru dan build resmi Control; tidak publish/push atau mengubah paket terbit. Fungsional/UAT dicatat di `_30` dan [laporan modul](2026-09-23_module_notifications.md); versi/layout kontrak kandidat tidak diubah.

**20 September 2026 — follow-up procurement:** [x] lengkapi allowlist panel/library/JS pemeriksaan stok dan cocokkan hash profil pada manifest. Kandidat alpha.22/v10 belum dipublish; revisi inventaris wajib direview Control bersama cutoff baru. [Laporan, hash dan UAT](2026-09-20_procurement_current_stock.md). Tidak ada perubahan entitlement/aktivasi, SQL baru, publish atau rollout customer oleh batch ini.

Update 20 September 2026: **alpha.22 / CUSTOMER_CLEAN v10 / REVIEW_REQUIRED / NOT_PUBLISHED**. Lihat [handoff terbaru](2026-09-20_customer_v10_migrations_update_control_handoff.md). Kelengkapan jurnal/panduan, enam migrasi customer, proof adopsi dan regresi Starter menjadi lingkup kandidat ini. Kontrol paket tetap melalui entitlement terverifikasi dan kuota server, tanpa tenggat mulai instalasi. Control perlu review cutoff/profil/build resmi; jalur update atomik instalasi lama belum selesai dan rollout harus ditahan. Detail status teknis tetap hanya pada `_30` dan handoff, bukan menganggap catatan lama di bawah sebagai status terbaru.

Batch 270 — 2026-09-18: **alpha.18 / CUSTOMER_CLEAN v7 / FINANCE_GUIDED_SETUP_V1 / REVIEW_REQUIRED / NOT_PUBLISHED**.
[Handoff guided setup](2026-09-18_guided_setup_control_handoff.md). Alpha.17/v6 sudah diterbitkan Control menurut kondisi terbaru; catatan Batch 269 di bawah adalah riwayat cutoff Finance, bukan status publikasi terkini.

- [x] Audit jalur alpha.17: ZIP sudah lengkap, tetapi pendamping/permission/jadwal masih perlu disiapkan; pesan lama belum membedakan sebab kegagalan.
- [x] Satu entry point admin Linux `sudo sh tools/install/portable/prepare.sh`, konfirmasi target, akun terpisah, permission terlokalisasi, verifikasi paket/izin, jadwal idempotent, bukti pemanggilan ketiga tugas scheduler.
- [x] Wizard Indonesia: kesiapan, kode setup, URL/DB/admin, toggle password, probe DB kosong, ringkasan, pasang/aktifkan, progres/resume dan login. Config tetap di dalam folder; tidak meminta parameter lisensi ulang.
- [x] Batas signature/core/permit/kuota/fingerprint, journal SQL, credential privat dan legacy conflict dipertahankan; tidak ada SQL baru atau perubahan master/Control/core2.
- [x] Panduan utama satu halaman mengikuti ZIP aktual; customer tidak menyalin delivery/cron atau menjalankan check/prepare/run/sync manual.
- [x] Cutoff `9ca2d389b060e43c6ac61d77d6a94eda9b1d557a`: 47 acceptance Chrome + ZIP/scheduler/nginx/FPM/DB disposable PASS, termasuk reload/resume, login pertama dan sinkronisasi; delapan gate build + verifier independen PASS. Hasil/hash di handoff; Control HTTPS sintetis, bukan integrasi live. Akun OS baru/crontab host bersama tidak diubah untuk tes.
- [ ] Control review/pin v7, issuer permit mengambil versi profil dari release terverifikasi, petunjuk ZIP diperbarui, build release baru dan walkthrough trial real-Control.
- [ ] Windows nyata, Apache/IIS dan onboarding manusia nonprogrammer; tidak dinyatakan selesai dari mock/Linux.

Batch 269 — 2026-09-18: **alpha.17 / CUSTOMER_CLEAN v6 / FINANCE_SINGLE_FOLDER_V1 / NOT_PUBLISHED**.
[Handoff installer satu folder](2026-09-18_single_folder_installer_control_handoff.md),
[panduan customer](customer_single_folder.md), [panduan admin](customer_single_folder_admin.md).

- [x] Layout paket diubah saat build saja: satu parent Finance; hanya `public/` document root. Master/instalasi aktif tidak dipindah.
- [x] `/setup` berbahasa Indonesia; DB/URL/admin sekali isi; config/key/journal lokal, pendamping non-root terpisah dari akun web, setup dikunci setelah sukses.
- [x] Baseline + 20 migrasi terdaftar, DB kosong, journal tanpa replay DDL tidak pasti, signature/core/aktivasi/kuota/fingerprint tetap diperiksa. Tidak ada SQL baru untuk DB operasional.
- [x] Linux HTTPS nginx/FPM/MariaDB disposable: instalasi sampai login admin nyata, proteksi file, pemulihan, lease/heartbeat; **59 pemeriksaan PASS dari checkout bersih `1bff31e1…`**, termasuk kuota/reissue sebelum SQL. Regresi legacy/integritas/preflight/static lulus; batas pembuktian di handoff.
- [x] Adaptor Windows ACL/MachineGuid/Task Scheduler dan template IIS/Apache tersedia **sebagai implementasi belum acceptance**; bukan klaim platform siap jual.
- [x] Build terisolasi dari cutoff yang sama: delapan gate + verifier independen PASS; 1.164 file, 306 tabel/20 migrasi, 735 referensi/0 customer-demo-secret; backup/restore checksum cocok. Fixture bukan release publishable.
- [ ] Control review cutoff `1bff31e1…`, pin/profile/layout dan kontrak signed setup permit; penerbitan/reissue/pengiriman customer masih perlu dikerjakan thread Control.
- [ ] Build penuh delapan gate + backup/restore v6 dan praktik Control nyata setelah review; evidence alpha.16 tidak berlaku otomatis untuk v6.
- [ ] Acceptance Windows nyata, Apache/IIS, scheduler OS dan walkthrough pengguna awam. Jangan membuka platform Windows/publish sebelum hasil tersebut disetujui.

Batch 268 — 2026-09-18: **alpha.16 / CUSTOMER_CLEAN v5 / REVIEW_REQUIRED / NOT_PUBLISHED**.
[Handoff konfigurasi lokal customer](2026-09-18_customer_local_config_control_handoff.md).

- [x] Satu `config/customer.json` untuk DB/URL/encryption/runtime; template ikut paket, secret tidak masuk Git/TAR; legacy kompatibel dan konflik sumber ditolak.
- [x] Konfigurasi otomatis di CLI/web tanpa environment PHP-FPM; nginx deny /config, Apache deny/listing protection; path/symlink/permission/JSON divalidasi.
- [x] Clean-install DB kosong memakai baseline + 20 migrasi terdaftar; journal parsial tidak diulang; signature/core/aktivasi/kuota/anti-clone dipertahankan.
- [x] 41 pemeriksaan end-to-end nginx/FPM/MariaDB disposable lulus termasuk login pertama dan health; 58 pemeriksaan config + suite integritas/legacy lulus.
- [x] Delapan gate build terisolasi + restore dari checkout bersih cutoff `018c7173…` PASS: 1.142 file, 306 tabel, 0 data customer/secret. End-to-end 41/41 juga diulang dari cutoff itu. Bukti di handoff, bukan approval publish.
- [ ] Thread Control mereview cutoff `018c7173…`, pin/hash/profil v5 dan job `configuration_source=customer_local`, lalu build release baru/praktik UI. Alpha.15 tetap immutable.
- [ ] Apache HTTP acceptance pada server target; instalasi subpath tidak didukung kontrak ini. Lima SQL belum managed tetap isu terpisah di `_30`.

Batch 267 — 2026-09-17: kandidat **alpha.14 / CUSTOMER_CLEAN v4 / ISOLATED_BUILD_PASS / NOT_APPROVED / NOT_RELEASED**. [Handoff integrasi installer dan heartbeat](2026-09-17_installer_heartbeat_control_handoff.md) menjadi acuan review cutoff baru; release 66 PUBLISHED tidak ditimpa. Build akhir delapan gate + verifier independen PASS; 1.135 anggota profil cocok dengan snapshot, tanpa mismatch.

- [x] Domain NULL/kosong/berbeda/berubah diterima sebagai metadata, bukan batas lisensi; FINANCE_BASE_URL tetap konfigurasi lokal customer.
- [x] Replacement credential dengan journal/evidence lama dipertahankan, binding release/plan/instance/identitas tetap, tidak ada replay SQL otomatis. Kuota server dan masa hak tetap otoritas Control; tidak ada deadline mulai instalasi baru.
- [x] Heartbeat runtime opsional primary_domain/region, allowlist/ukuran/karakter tervalidasi dan ditandatangani; sender lama kompatibel.
- [x] Tes terisolasi delivery HTTPS, profil/hash/signature, agent/kuota, URL lokal dan validator Control read-only lulus; rincian/batas pembuktian pada handoff.
- [ ] Control review commit final dan hash/dependency, uji cutoff persis, approve/build sebagai release baru. Tidak mengubah trust atau source/database Control dari Finance.

Riwayat Batch 266 — 2026-09-16: audit cutoff `db76e809…`, kandidat alpha.13/profile v4 lulus delapan gate + validator independen termasuk clean-install/restore disposable. Ini bukti historis batch sebelumnya, bukan approval otomatis alpha.14 atau status release 66. [Laporan v4](2026-09-16_finance_customer_clean_v4_handoff.md).

Profil v3/alpha.12 historis tidak ditimpa; user tidak perlu menjalankan SQL ke Finance aktif. Register lima SQL development yang belum masuk paket dan bug bisnis tetap terbuka di `_30`. Control perlu mereview commit/hash/profil v4 serta menjalankan ulang gate pada cutoff final yang persis, baru scan/impor produk yang sama dan praktik build melalui UI. Kelulusan build terisolasi bukan izin publish.

Review Batch 265 — 2026-09-16: [checklist penerimaan lintas aplikasi](2026-09-16_checklist_uat_seluruh_perbaikan_finance.md) kini menyatukan 98 skenario / 23 kelompok, termasuk identitas customer, panduan, perangkat dan clean-install/update/lisensi. Detail bug/kode tetap di `_30`; daftar ini bukan roadmap ketiga dan belum menandakan UAT lulus atau C0–C5 selesai.

Pemeriksaan read-only katalog/profil mengonfirmasi SQL `2026-09-14c`, `15a`, `15b`, `15c`, `16a` belum managed/allowlisted; SQL `2026-09-12a` sudah di katalog tetapi belum allowlist SQL customer. Contoh kode belum allowlisted: `Finance_accounting_model`, `Procurement_stock_review`, `User_guide`, `Finance_user_guide`. Sebelum paket berikut dirilis: selaraskan dependency/checksum/allowlist, tutup temuan review, lalu buktikan clean-install/upgrade dan penerimaan customer. Tidak mengubah Control, katalog/profil/artifact atau database dalam review; 22 suite source/fixture PASS tidak membuktikan isi artifact/deployment. Riwayat release lama harus dibaca sesuai cutoff, bukan dianggap memuat seluruh working tree terbaru.

Review Batch 264 — 2026-09-16: handoff procurement **REVIEW_OPEN / NOT_RELEASED**. PR-01 (edit-verifikasi bersamaan), PR-02 (lookup material gagal) dan PR-03 (preview menggantung) menjadi acceptance blocker sebelum fitur ini dibawa ke customer. Rincian bug/reproduksi dan 19 checklist UAT hanya pada `_30` dan laporan modul, tidak diduplikasi di roadmap penjualan. Apply SQL oleh pengguna tidak menutup acceptance kode atau membuktikan paket customer sudah diperbarui.

Handoff Batch 263, status diperbarui Batch 264 — 2026-09-16 (**IMPLEMENTED / REVIEW_OPEN / NOT_RELEASED**): kontrol stok pada verifikasi pengajuan divisi dan riwayat terkait SR/PO ditambahkan di source. Checklist teknis/UAT hanya di `_30` dan [laporan modul](2026-09-16_konfirmasi_stok_pengajuan_divisi.md). SQL `2026-09-16a` **USER_REPORTED_APPLIED**, dicatat 06:21 WIB berdasarkan konfirmasi pengguna; belum postcheck langsung dan belum managed/allowlisted. Paket berikut harus membawa library, controller/model/view, route dan JS bersama migrasi tabel bukti; clean-install menggunakan tabel kosong, tidak menyalin nama pihak/alasan/IP/snapshot staging. Sebelum rilis: perbaiki temuan review, registrasi dependency/checksum/allowlist, replay MariaDB, clean-install/upgrade dan UAT. Tidak mengubah Control, manifest/profil, registry atau artifact; perubahan source bukan bukti artifact customer sudah diperbarui.

Handoff Batch 262 — 2026-09-15: paket berikut perlu membawa pembaruan `Pos_report_model.php` dan view `pos/report_sales_index.php` untuk urutan laporan penjualan berdasarkan waktu order. Rincian teknis/checklist tetap hanya di `_30` dan execution log. Tidak ada SQL baru, perubahan Control atau build/push/deploy; perubahan source bukan bukti artifact customer sudah diperbarui.

Pembaruan Batch 261 — 2026-09-15 (**C5 panduan, CODE_READY / NOT_RELEASED**): pusat Panduan Aplikasi `/guide` tersedia di source: 26 bab, UI/peran dan admin server terpisah, navigasi mobile/pencarian, contoh konfigurasi/scheduler dan alur sampai laporan/jurnal. Detail teknis/checklist hanya pada `_30` dan laporan modul. SQL `2026-09-15c` metadata menu/izin **USER_REPORTED_APPLIED**, konfirmasi pengguna dicatat 11:24 WIB; postcheck belum diterima. Agent tidak mengubah DB/Control, checksum atau mengulang apply. Paket berikut perlu membawa controller/library/catalog/view/CSS/JS/routes dan seed izin yang sudah direview; migrasi belum managed/allowlisted. Versi yang tampil adalah versi sumber manifest, bukan bukti paket terpublikasi. C5 tetap **IN_PROGRESS**: verifikasi sidebar/izin, browser/print, walkthrough awam dan validasi artifact customer/clean-install/upgrade belum selesai.

Pembaruan Batch 260 — 2026-09-15: pengguna mengonfirmasi SQL jurnal 15b sudah dijalankan; status terkini **USER_REPORTED_APPLIED** (dicatat 10:25 WIB), menggantikan BELUM DIJALANKAN pada riwayat Batch 259 di bawah. Tidak mengulang SQL/checksum; postcheck/izin/UAT dan handoff katalog/customer tetap terbuka. Perbaikan regresi PH pada `My_portal_model` + view `my/attendance` dicatat pada `_30`/laporan modul PH; required gate baru `attendance-auto-ph`. Tidak ada SQL PH baru, perubahan profil/Control atau paket/deploy pada batch ini. Perbaikan workspace bukan bukti artifact customer sudah membawa perubahan.

Pembaruan Batch 259 — 2026-09-15 (**C3 handoff, NOT_RELEASED**): UI akun/pemetaan dan asisten/panduan jurnal sudah ditambahkan; rincian/checklist teknis tetap pada `_30` dan laporan modul akuntansi. Dependency kode tambahan: `Finance_accounting_setup.php`, `Finance_journal_assistant.php`, partial view `accounting_settings.php`/`accounting_guide.php`, pembaruan controller/model/routes/JS. SQL baru `2026-09-15b_finance_journal_assistant.sql` **BELUM DIJALANKAN**, membutuhkan 15a (USER_REPORTED_APPLIED, belum postcheck). Tabel mapping harus kosong pada paket baru; pemetaan customer tidak boleh diambil dari staging. Seed izin pengaturan terpisah, tanpa saldo/jurnal/transaksi bisnis. Kedua SQL dan dependency kode accounting belum didaftarkan/dibuktikan lewat allowlist/release; bukan janji fitur sudah dibawa alpha.10. Registrasi hash/dependency, replay MariaDB, clean-install/upgrade dan UAT tetap terbuka. Tidak mengubah Control, paket, registry, credential, atau menjalankan deployment.

Pembaruan 2026-09-15 09:35 WIB / tindak lanjut Batch 258: pengguna mengonfirmasi SQL `2026-09-15a` sudah dijalankan; status terkini **USER_REPORTED_APPLIED**, menggantikan BELUM DIJALANKAN pada catatan historis berikut. Postcheck/schema/sidebar/RBAC, UAT, pengakuan otomatis lintas modul dan registrasi paket customer tetap belum selesai. Tidak mengulang SQL, mengubah registry/Control atau menyatakan rilis siap.

Catatan terkini 2026-09-15 / Batch 258 (C3 handoff, **belum dirilis**): fondasi arus kas/jurnal/laporan akuntansi dicatat pada `_30` dan laporan modul `2026-09-15_akuntansi_jurnal_dan_arus_kas.md`. SQL `2026-09-15a_finance_general_ledger.sql` **BELUM DIJALANKAN**, belum managed/allowlisted; jangan memasukkannya ke paket customer sebagai fitur siap pakai. Paket setelah review harus membawa controller `Finance_accounting`, model `Finance_accounting_model`, library `Finance_journal_policy`, view `finance/accounting`, JS `finance-accounting`, perubahan route/tab, serta seed COA/sidebar/izin metadata-only. Gate required `finance-accounting` telah ditambahkan, tetapi DDL/replay/concurrency, browser/UAT, registrasi hash/dependency/allowlist, clean-install/upgrade dan aktivasi belum selesai. Belum ada auto-post akrual semua modul/penerbitan laporan standar; tidak mengubah Control, profil/manifest atau membuat artifact/push/deploy. Detail status teknis tetap hanya di `_30`/laporan modul, bukan checklist fase baru paralel.

Catatan terkini 2026-09-14 / Batch 257 (bukti staging, bukan rilis): pemeriksaan langsung MariaDB mengonfirmasi struktur SQL `2026-09-14c` sudah terpasang; tidak ada SQL upgrade tertunda dalam 19 entri katalog yang diperiksa. Status 14c **SCHEMA_VERIFIED_LEDGER_PENDING**: pencatatan apply manual dan registrasi katalog/allowlist customer masih perlu ditinjau, dilanjutkan uji clean-install/upgrade, concurrency dan UAT. Tidak mengulang SQL, membuat paket atau mengubah Control. Detail bukti hanya di `_30` dan laporan modul; catatan batch sebelumnya di bawah adalah status pada waktu pengerjaannya.

Catatan 2026-09-14 / Batch 256 (alat bukti staging, bukan aktivasi/rilis): postcheck read-only 95 kondisi disiapkan beserta required gate 241 tes sintetis. Detail/cara menjalankan tetap pada `_30` dan laporan modul. Hasil DB belum diterima; status SQL 2026-09-14c masih USER_REPORTED_APPLIED. Alat membedakan schema sesuai dari ledger manual yang belum tercatat; tidak menulis registry atau mengubah migrasi/allowlist/profil/Control. Verifikasi pascamigrasi menjadi masukan review registrasi customer, bukan pengganti uji clean-install/upgrade, concurrency atau UAT.

Catatan 2026-09-14 / Batch 255 (C3 handoff kode, bukan rilis): perbaikan transfer Rekon Kas ke saldo tujuan negatif dan hasil integrasi dicatat pada `_30`/laporan modul. Paket berikutnya perlu membawa `Finance_cash_reconciliation_model.php` terbaru; gate `finance-allocation-bank` kini 392 pemeriksaan termasuk kasus lintas laporan. Tidak ada SQL baru, perubahan Control/profil/allowlist, build atau deploy. SQL `2026-09-14c` tetap USER_REPORTED_APPLIED; validasi schema/ledger, concurrency, browser/UAT dan registrasi migrasi customer belum selesai.

Catatan 2026-09-14 / Batch 254 (C3 handoff, bukan fase/rilis baru): aturan Rekonsiliasi Pendapatan harian diperbaiki pada `_30`; paket berikutnya harus mengambil model, trait transfer dan view terbaru beserta gate 175 pemeriksaan. Tidak mengubah kontrak/allowlist/profil Control dan tidak menambah SQL. Pengguna mengonfirmasi SQL `2026-09-14c` sudah dijalankan di staging `db_finance` (**USER_REPORTED_APPLIED**, belum diverifikasi schema/ledger). SQL belum didaftarkan auto-apply/allowlist customer; validasi migrasi MariaDB, browser/UAT dan blocker distribusi Batch 253 tetap terbuka. Panduan pengguna terbaru ada di [laporan modul](2026-09-14_finance_alokasi_bank_rekonsiliasi.md).

Catatan 2026-09-14 / Batch 253 (C3 handoff, bukan rilis/aktivasi):

- [x] Library/view/JS alokasi, pembanding bank dan transfer rekonsiliasi masuk allowlist **kode** customer agar dependency runtime tidak hilang. Profil tetap v3; hash kerja `02940e1fa103fc99211e531d02f954ab131e5c35282918b5c276cc8627750466`, terikat manifest. Hash batch sebelumnya adalah cutoff historis.
- [x] Required gate `finance-allocation-bank` menguji data sintetis in-memory; semua perbaikan bisnis/checklist tetap di delta Batch 253 `_30` dan laporan modul, tidak diduplikasi sebagai fase komersialisasi baru.
- [ ] SQL `2026-09-14c` **belum didaftarkan auto-apply/katalog/allowlist SQL**. Eksekusi staging sudah dilaporkan pengguna (pembaruan Batch 254), tetapi bukan bukti kesiapan distribusi. Wajib verifikasi schema/ledger staging, MariaDB disposable, registrasi hash/dependency serta kebersihan tabel metadata pada clean install/upgrade sebelum distribusi fitur ini.
- [ ] Belum browser UAT (sandbox tidak mengizinkan Chromium), concurrency MariaDB, artifact/cutoff, build, deploy, push atau perubahan Control. Blocker distribusi terdahulu tetap berlaku; paket global tidak dinyatakan siap jual oleh batch ini.

Catatan 2026-09-14 / Batch 252 (C3 handoff regresi POS, bukan fase baru): perbaikan void/refund produk tanpa resep dicatat di `_30` / A1. Paket berikutnya perlu mengambil `Pos_model.php` yang sudah menangani `NOT_REQUIRED` tanpa snapshot dan lulus gate `a1-pos-reversal-no-stock`. Tidak ada SQL/migrasi, perubahan kontrak profil/Control/APK, build, deploy atau publish pada batch ini; UAT pengguna tetap terbuka.

Catatan 2026-09-14 / Batch 249 (C3 handoff lanjutan Kontrol Keuangan):

- [x] Runtime rincian transfer/biaya, realisasi rencana, bukti privat dan persetujuan opsional beserta SQL `2026-09-14b` masuk katalog/allowlist Finance. Schema diterapkan di staging; **tidak mengemas bukti, konfirmasi, biaya, pengajuan, rencana atau transaksi development**. Clean install hanya mendapat tabel kosong dan satu kebijakan awal (approval/bukti wajib OFF, tanggal proyeksi payroll 1).
- [x] Profil kerja dinaikkan menjadi **CUSTOMER_CLEAN v3**, SHA `e167cc4f826f90976a59b023606d8af5aa0a43304e73c85ac346226238c48781`, terikat manifest. Finance mengenali v1/v2/v3 dengan verifikasi hash/signature/plan yang tetap ketat; versi asing ditolak. Catatan v2 di Batch 248 adalah cutoff historis, bukan profil kerja terkini.
- [ ] Control: scan ulang source/digest dan gunakan versi profil hasil inspeksi (sekarang 3), bukan nilai hardcode. Sebelum mengaktifkan unggah bukti di customer, siapkan direktori privat per instance dan izin PHP-FPM/open_basedir sesuai panduan modul. `.user.ini` staging bukan file distribusi dan tidak boleh disalin mentah ke customer.
- [ ] Blocker Roast Connect belum berubah: migration `2026-09-12a` milik thread lain masih belum disetujui dalam allowlist paket customer. Tidak menambahkan/menghapus modul tersebut atau mengubah Control untuk meloloskan gate.
- [ ] Setelah pemilik modul menyelesaikan blocker: cutoff/version baru → gate → build → clean-install/upgrade disposable → UAT owner. Tidak ada commit/push/publish, perubahan artifact alpha.12, atau eksekusi server utama dalam Batch 249. Checklist bisnis tetap di `_30`.

Catatan 2026-09-14 / Batch 248 (C3 handoff untuk pengembangan finance 1–5, bukan membuka fase baru):

- [x] Kode Kontrol Keuangan dan SQL `2026-09-14a` masuk allowlist/katalog Finance. Migration sudah applied/replay hanya di staging, tanpa isi data customer/master/transaksi contoh.
- [x] Revisi isi profil dibuat eksplisit sebagai **CUSTOMER_CLEAN profile_version 2**, sesuai aturan versioning profil. SHA profil `b9de222acc42e1a2ecffd70607e4d7560999900db4bfd874e5e6243f86669b9b`, terikat `app-manifest.json`. Ini menggantikan profil kerja v1 Batch 247, bukan mengubah artifact/tag lama. Verifier/installer Finance menerima versi 1 atau 2 yang dikenal, tetap memeriksa trusted-local hash, signature dan kesesuaian plan/artifact; tidak mempercayai allowlist buatan archive.
- [ ] Handoff Control sebelum build/publish berikutnya: scan ulang cutoff/digest, baca `distribution_profile_version` dari hasil inspeksi Finance (sekarang 2), jangan hardcode 1. Thread ini tidak mengedit aplikasi Control atau mem-publish paket.
- [ ] Blocker paket customer Roast Connect dari Batch 247 tetap: SQL `2026-09-12a` ada di katalog tetapi belum dalam allowlist customer. Pemilik modul perlu memutuskan kelayakan penyertaan dan menguji; tidak disisipkan atau dihapus diam-diam dalam pekerjaan finance ini.
- [ ] Setelah blocker tersebut selesai: cutoff/version baru, ulang gate, build, install/upgrade disposable dan UAT owner. Tidak mengklaim alpha.12 yang sudah diterbitkan otomatis memuat perubahan ini. Perubahan bisnis/UAT detail hanya di `_30` dan laporan modul.

Catatan 2026-09-13 / Batch 247 (C3 handoff, bukan fase baru): perbaikan klasifikasi mutasi/estimasi dicatat di `_30`. Migration `2026-09-13a` sudah terpasang hanya di staging; paket customer berikutnya harus menyertakan `Finance_mutation_policy.php` dan SQL tersebut melalui katalog `clean_install`/`upgrade`. Allowlist dan binding `app-manifest.json` diperbarui; kontrak profil tetap versi 1, identitas konten berubah melalui SHA-256 `4d92a4c7fafd9204cdeef93860f4b4945a763c476021171210da3e413b16dba4`. Tidak mengubah Control, versi/tag release terdahulu, menjalankan SQL di server utama, commit/push, atau publish.

- [x] Kode dan SQL perubahan estimasi diakui allowlist Finance; data transaksi customer/source tidak dibundel atau dibersihkan.
- [ ] Sebelum build customer berikutnya, pemilik Roast Connect menyelaraskan migration `2026-09-12a` dengan profil customer dan menuntaskan validasinya. Gate paket customer masih fail-closed pada `MIGRATION_CHECKSUM` karena SQL tersebut belum ada di allowlist; tidak dilonggarkan dalam batch laporan.
- [ ] Setelah integrasi tersebut, tetapkan versi/cutoff release baru, scan ulang source/digest melalui Control, lalu ulang clean-install/upgrade/UAT customer. Artefak alpha.12 lama tidak otomatis memuat perubahan working tree ini.

Catatan 2026-09-13 / Batch 246: perbaikan tujuan SR Roastery dan layout mobile POS/PO dicatat pada `_30` (A2/A3), bukan fase komersialisasi baru. Tidak mengubah kontrak Control, profil distribusi, SQL atau status publish; paket rilis berikutnya perlu mengambil cutoff yang memuat perbaikan ini.

## Status praktik owner — Batch 243, 2026-09-09

**Barang siap untuk praktik penjualan web Linux terbatas di UI Control.**
Mulai dari **Release → Panduan praktik Finance** (`/finance/practice`).
Kandidat **alpha.10**, release **38**, masih **DRAFT/ALPHA**; 3 artefak dan 5
evidence wajib tersedia. Owner tetap memegang keputusan review/publish,
customer, subscription, domain dan aktivasi nyata. Tidak ada push Git.

- [x] Paket signed cutoff `15f9f62`: **113 gate release PASS**, 1.645 file,
  verifier Finance/Control PASS; byte alpha.9 dan tag percobaan tetap utuh.
- [x] Pemasangan alpha.10 pada DB baru: 296 tabel, 16 migrasi, owner, health dan
  22 tes HTTPS UI PASS. Tidak mengambil data transaksi aplikasi lama.
- [x] Upgrade alpha.9 → alpha.10 pada DB salinan, 296 checksum tabel sama,
  upload identik; web baru + cache lisensi aktual terbaca; rollback ke web/DB
  lama PASS dan database lama tidak berubah selama uji versi baru.
- [x] HTTPS Control terisolasi: 18 lisensi, 9 delivery/receipt, 12 form UI dan
  2 akses panduan PASS. Kunci penerbit Finance disiapkan, bukan lisensi customer.
- [x] DRAFT Control menyimpan SOURCE_CLEAN, SECURITY_SCAN, INSTALL_TEST,
  BACKUP_RESTORE, SIGNATURE_VERIFY; import evidence ulang UNCHANGED.
- [x] Panduan owner berupa halaman web berurutan; admin server mempunyai
  perintah konkret di `customer_setup_and_release_guide.md` bagian 10.
- [ ] Praktik owner/customer nyata, domain publik dan vhost/scheduler target:
  dilakukan pada saat latihan setelah target dipilih, tidak ditebak oleh engineer.
- [ ] Release produksi penuh: UAT per peran/perangkat/printer, APK/Windows,
  native guard/pairing/limit/enforcement, audit branding legacy menyeluruh,
  kontrak/support final dan pilot. **Tidak diiklankan sudah selesai.**

SQL managed tidak bertambah. Upgrade yang diuji melintasi **versi kode**, dengan
schema tetap finance-20260907; SQL bisnis baru di masa depan harus diuji lagi.
Untuk praktik pertama pilih instalasi kosong. Import data aplikasi berjalan
nanti harus ke **database salinan**, bukan menjalankan SQL pada server lama.
Rincian cutoff/hash/lingkungan uji di `control_release_delivery.md` dan log.
Bagian batch di bawah adalah riwayat; **gunakan tabel 0.1 untuk status fase**.

**Batch 242 — persiapan praktik, 2026-09-09:** sambungan aktual ke **salinan
Control via HTTPS** lulus 18 tes lisensi dan 9 tes delivery/receipt paket alpha.9;
12 tes UI deployment/maker-checker/penggantian token juga PASS. Installer baru
memasang alpha.9 pada DB kosong: health + 22 tes HTTPS profil/logo/login PASS.
Recovery aktivasi ambigu dan perpanjangan lease setelah offline sudah diperbaiki.
Source alpha.10 menambahkan executor layanan dan salinan upload untuk upgrade;
hasil paket alpha.10/cutover/rollback dicatat setelah test, bukan disamakan dengan
hasil alpha.9. Tidak ada customer/publish/aktivasi nyata; kunci penerbit Finance
disiapkan root-only tanpa menjalankan penerbit pada subscription operasional.

- [x] C3: claim/download terverifikasi, receipt ber-ID migrasi Finance,
  idempotensi dan penolakan penyelesaian prematur setelah migrasi.
- [x] C4: HTTPS request/poll/recover/revoke/replay/renewal pada Control terisolasi;
  58 unit agen/model/izin PASS, termasuk key tetap setelah recovery.
- [x] Panduan UI owner (bagian 9) dipisahkan dari executor admin (bagian 10).
- [ ] Final kandidat alpha.10, upgrade/cutover/rollback dan bukti DRAFT terikat
  ke paket terbaru; ini langkah penuntasan praktik, bukan menunggu harga customer.

Ringkasan batch terbaru mengoreksi checklist historis di bawah; tabel 0.1 tetap
sumber status fase. **Siap praktik terbatas tidak sama dengan C0–C5 DONE produksi.**

**Batch 241:** kandidat privat alpha.9 sudah build/sign/verify, termasuk
verifier Control read-only, dari cutoff `4d31548`: **112 gate release PASS**
dan 54 tes agen lengkap PASS. **Belum diregistrasi/publish/diaktivasi** di
Control pada batch ini. Paket alpha.8 dan bukti HTTPS/DB-nya tetap utuh;
hasil alpha.8 tidak otomatis dianggap acceptance deployment alpha.9.

**Arahan owner dan Batch 240 — 2026-09-09:** siapkan barang/engineering lebih
dahulu; customer, paket, kontrak, aktivasi dan praktik penjualan akan dilakukan
owner melalui UI Control. Harga/domain/pilot bukan penghambat persiapan teknis.
Jangan membuat customer, melakukan aktivasi nyata, atau mem-publish atas nama owner.

- [x] C4 source alpha.9: agen Linux AMD64 `init/activate/poll`, identitas sekali
  buat, signed request ke kontrak Control, cache atomik di luar webroot.
- [x] Cache/model: signature + binding, offline lease/grace, replay lintas restart,
  jam mundur, revoke dan pemisahan izin root/web; **54 pemeriksaan fixture PASS**.
  Tes protokol/cache 22 checks juga dimasukkan ke quality gate wajib.
- [x] UI `/system/license` menampilkan sambungan/identitas/waktu sinkron; tidak
  menampilkan secret, mengubah RBAC atau mengaktifkan enforcement.
- [x] Panduan penjual UI vs admin server dipisahkan; perintah konkret dan template
  service/timer tersedia. Template belum dipasang pada layanan operasional.
- [ ] C3: installer layanan final, cutover/rollback dan upgrade lintas versi.
- [ ] C4: acceptance HTTPS ke Control nyata, recovery aktivasi ambigu, native
  guard, pairing/limit/enforcement dan Windows. Root snapshot rollback bukan
  jaminan yang diberikan cache PHP. Bug operasional/build/UAT APK tetap ditunda.
- [ ] C0/C1/C5: persetujuan/publikasi dan praktik customer melalui UI setelah
  persiapan teknis; panduan per modul/walkthrough dan pilot belum dinyatakan lulus.

Checklist di atas dan tabel 0.1 menggantikan status historis batch sebelumnya.
Detail bukti/cutoff paket dicatat di log eksekusi; **C0–C5 belum seluruhnya DONE**.

**Riwayat final Batch 239 (2026-09-09): alpha.8 `SIGNED_WEB_UPGRADE_TRIAL_PASS`.**
Cutoff `39a8210`, 111 gate otomatis PASS, paket signed 1.626 file, DRAFT Control.
Web dari ekstraksi paket tanpa edit kode lulus 22 tes HTTPS dan health setelah
upgrade pada salinan DB sintetis. Tidak mem-publish atau mengganti aplikasi utama.
Installer layanan final, migrasi lintas versi, C4 aktivasi, keputusan kontrak
dan pilot tetap terbuka; jangan membaca hasil ini sebagai C0–C5 DONE.

**Update 2026-09-09 — Batch 236–238:** permintaan pengerjaan C0–C5 dilanjutkan
pada fondasi customer, uji HTTPS, backup/restore dan panduan serah-terima.
**C0–C5 belum seluruhnya selesai.** Kelulusan pengujian internal tidak
menggantikan pekerjaan lisensi, installer final, keputusan owner dan pilot.

- [x] C2/C3: konfigurasi per instalasi untuk URL tetap, cookie/session/log/cache,
  JSON privat tanpa edit source, serta profile Nginx/PHP-FPM loopback HTTPS.
- [x] C2: owner percobaan login, simpan profil/unggah logo lewat UI, lalu login
  publik memakai identitas/logo itu; CSRF dan file internal ditolak. 22 tes PASS.
- [x] C3: backup 296 tabel percobaan dipulihkan ke dua database baru dengan
  checksum sama. Upgrade katalog yang sama idempotent dan health PASS, tanpa
  menimpa seed/owner/profil. Bukan bukti migrasi lintas versi atau web cutover.
- [x] C0/C1/C5: panduan alpha.8 memisahkan admin usaha/admin server, daftar
  keputusan paket-harga-kontrak-domain, latihan penerimaan dan rancangan SOP
  support. Status keputusan tetap belum disetujui; SLA tidak dikarang.
- [x] C4: 26 tes verifier lisensi lulus kembali; maintenance berakhir tidak
  disamakan dengan lease berakhir. Tidak ada aktivasi/enforcement baru.
- [x] C3: alpha.8 dari cutoff final `39a8210` lulus gate/build/sign/verify,
  DRAFT Control, upgrade salinan DB dan HTTPS dari paket. Cutoff percobaan
  `d5ff56e` digantikan sebelum signing; alpha.7 tetap immutable.
- [ ] Sisa engineering: installer layanan/customer, upgrade lintas versi,
  aktivasi/polling/cache writer, anti-replay lintas restart, pairing/limit dan
  enforcement teruji. Windows dan UAT/bug operasional APK belum lulus.
- [ ] Keputusan owner: harga, kontrak/EULA-SLA/data policy, customer/domain
  pilot, penerimaan risiko dan persetujuan publikasi. Pilot nyata belum dijalankan.

Rujukan operator: `docs/customer_setup_and_release_guide.md` bagian 6–8.
Tidak ada SQL baru untuk aplikasi utama lama, tidak ada publish/push, dan
layanan/database operasional tetap utuh. Database trial hanya berisi seed,
owner serta identitas/logo sintetis hasil pengujian UI.

**Update 2026-09-09 — Batch 232–235:** kandidat **alpha.7** memakai PHP 8.1
dan MariaDB `>=10.11 <10.12`, sesuai persetujuan owner. Instalasi database
kosong dari paket signed telah **PASS**, dan paket tercatat **DRAFT/ALPHA**
di Control. Ini belum deployment web atau kesiapan jual penuh.
Schema bisnis tetap finance-20260907; baseline clean-install direvisi
clean-install-20260909. Detail bug teknis hanya di `_30`, bukti/hash di log.

- [x] Kontrak manifest/policy/installer selaras; Control mengambil compatibility
  dari manifest tervalidasi, bukan literal 10.6 untuk semua kandidat.
- [x] Tes runtime/bridge/installer serta registrar Control lulus; versi lintas
  kontrak dan metadata yang tidak cocok ditolak.
- [x] Alpha.7 build/sign/verify: 1.623 file, cutoff `68e0114`, quality release
  109 entry PASS. Alpha.3 tetap utuh; alpha.4–6 yang gagal disimpan sebagai bukti.
- [x] Clean-install aktual dari executor paket: 296 tabel, 16 migrasi, satu
  owner, 209 halaman/permission, 249 menu; health/seed exact PASS pada DB disposable.
- [x] Password owner, dua indeks hasil migrasi, tujuh kasus SQL permission dan
  larangan reinstall pada DB nonempty teruji. Tidak mengambil data Finance lama.
- [x] Control DRAFT alpha.7: 3 artefak privat, 2 evidence SOURCE_CLEAN/SIGNATURE_VERIFY;
  replay UNCHANGED. Bukti DB install ada di log, bukan evidence full web INSTALL_TEST.
- [ ] Deploy web lengkap, upgrade/rollback disposable, claim/receipt, dan UAT.
- [ ] Identitas/aset legacy C2, aktivasi C4 dan keputusan rilis/pilot C5 tetap terbuka.

**Riwayat Batch 229–231:** cold-cache tooling diperbaiki dan
alpha.3 sudah **DRAFT/ALPHA** di Control (3 artefak, 2 evidence, tanpa publish).
Executor database clean-install tersedia, tetapi trial berhenti **sebelum DDL**:
server sekarang MariaDB 10.11.10, kontrak alpha.3 masih 10.6. Keputusan runtime
kemudian disetujui pada Batch 232; manifest/signature alpha.3 tidak diubah.

- [x] Gate cold-cache 360s/outer 420s, cache terpisah per checkout; scope tetap
  seluruh application dan baseline nol. Cold run aktual PASS.
- [x] Registrasi DRAFT privat, transaksi/audit, kontrol peran operator,
  idempotensi dan larangan overwrite versi; 18 tes fixture PASS.
- [x] Tiga artefak dapat dibaca web Control; import ulang UNCHANGED.
- [x] Executor DB: signature/source exact, DB kosong, runtime dan lock diperiksa
  sebelum baseline, migration, owner dan health yang wajib dijalankan.
- [x] Tetapkan runtime kandidat berikutnya: 10.11, disetujui owner Batch 232.
- [x] Install DB/first-owner/health lulus pada alpha.7 (Batch 235).
- [ ] Upgrade/rollback dan deployment web/customer tetap belum lulus.
- [x] Kode Batch 229–231 sudah masuk kandidat alpha.7; byte alpha.3 tidak diganti.

**Arahan terbaru 2026-09-09 — Batch 228:** pekerjaan komersialisasi APK
(identitas, packaging, kompatibilitas, aktivasi) boleh dilanjutkan. Yang
ditunda hanya bug operasional/build APK dan UAT perangkat; APK tidak otomatis
siap jual. Batch ini berfokus pada cutoff lokal terseleksi dan adapter
verifikasi artefak Finance di Control. Tidak merge/push, publish, aktivasi,
atau deployment customer. Installer nyata tetap langkah berikutnya.

- [x] Selaraskan manifest runtime dengan kontrak yang diuji: PHP 8.1,
  MariaDB 10.6; versi source `0.1.0-alpha.3`, tanpa perubahan schema.
- [x] Adapter manifest/tanda tangan release Control dengan katalog SQL Finance,
  baseline/checksum, dan status APK terpisah dari paket web.
- [x] Cutoff commit lokal `b10fa37a40a06b1867800327812ac1dc1490c176`;
  199 file berubah/bertambah sejak checkpoint `d462d4a`, tanpa merge/push.
- [x] Paket 1.621 file dari checkout bersih berhasil dibangun, ditandatangani,
  dan diverifikasi melalui CLI Control; status `INTERNAL_CANDIDATE`, bukan publish.
- [x] Tindak lanjut cold-cache Batch 228 selesai dalam kode Batch 229;
  budget bertingkat/cache per checkout perlu masuk paket berikutnya.
- [x] Registrasi DRAFT Finance selesai Batch 230.
- [ ] Publish, deployment web dan upgrade disposable belum lulus; blokir runtime
  dan clean-install DB Batch 231 sudah dituntaskan pada kandidat alpha.7.

**Riwayat pelaksanaan 2026-09-08, Batch 224–227:** lanjut non-APK melalui
Menu Book customer (C2), perlindungan upload dan plan/runtime instalasi (C3),
serta verifikasi signed entitlement Control (C4). Tabel 0.1 telah diperbarui;
tidak ada fase C0–C5 yang ditutup hanya karena tes kode lulus. APK ditunda
owner. Panduan setup awal berada di `customer_setup_and_release_guide.md`;
installer/deployment/pilot nyata dan kontrak penjualan tetap terbuka.
Validasi gabungan 107 entry lulus (Batch 227); ini bukan persetujuan rilis
atau penutupan fase. Pada penutupan Batch 227 cutoff/delivery masih menunggu
persetujuan; cutoff dan verifikasi paket kemudian dilaksanakan pada Batch 228.

**Status:** Keputusan produk dan urutan implementasi menuju siap jual.
Diperbarui 31 Agustus 2026 berdasarkan
`docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`.
Snapshot handoff setelah A5.16: `AUDIT_GATE=TECH-HANDOFF-A0-A5`,
`status=BLOCKED`, `checked_at=2026-09-05`. Detail status teknis hanya berada di
`docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`;
dokumen komersialisasi ini tidak mengulang persentase atau checklist A0–A5.

Pembaruan 2026-09-05, Batch 151: alat simulasi akses dan pembandingan baseline
RBAC sudah tersedia di Finance (detail P0-04 pada `_30`). Isi izin standar per
jabatan/paket belum disetujui owner; baseline ini bukan entitlement/lisensi
dan tidak mengubah izin customer. Gerbang komersialisasi tetap `BLOCKED`.

Batch 152–153 menambahkan pengamanan ulasan publik, privasi respons, dan CSRF
pengelolaan ulasan/QR admin. Persyaratan runtime single-server serta sisa UAT
berada di P2-06 `_30`;
ini perbaikan fondasi, bukan modul lisensi atau kenaikan status siap jual.

Batch 154–155 mengamankan mutasi Tutup Periode Keuangan dari request palsu,
redirect kiriman pengguna, serta reopen ganda/gagal commit. Batch 156–164
menambah reauth proof satu-kali untuk Void/Refund/Reprint POS web, Reopen
periode, Post/VOID Adjustment Base/Prepare, serta Post/VOID Adjustment Stok
Gudang/Divisi, Component Batch Produksi, quick-adjust Daily Recon Component,
dan Transfer Stok Divisi web. Acceptance finance,
step-up aksi lain, dan APK tetap berada di
`_30`; status komersialisasi tidak berubah.

Batch 165 menutup CSRF scoped pada input, VOID, dan import massal Stock
Opening Gudang/Divisi. Reauth untuk writer opening belum diklaim selesai dan
tetap berada pada backlog A1 di `_30`; tidak ada dampak pada lisensi atau
paket komersial.

Batch 166 menambah reauth password one-use untuk simpan opening manual dan
VOID snapshot. Import Excel massal sengaja tetap hanya memakai CSRF sampai
desain verifikasi batch lintas divisi tersedia. Ini memperkuat fondasi audit,
tanpa mengubah paket, lisensi, atau status komersialisasi.

Batch 167 menyelesaikan batas import tersebut: import opening memerlukan
reauth one-use yang terikat satu divisi aktif, dan file tidak lagi dapat
menulis baris divisi lain. Ini fondasi audit, bukan perubahan paket, lisensi,
atau status komersialisasi.

Batch 168 menambah revision, lock, dan audit atomik pada editor massal Resep
Produk agar perubahan lama tidak menimpa resep terbaru. Ini perbaikan fondasi
teknis di `_30`, bukan perubahan paket, lisensi, atau status komersialisasi.

Batch 169 menerapkan pola yang sama pada editor massal Formula Component.
Perubahan formula dari tab lama kini ditolak, dan snapshot sebelum/sesudah
tercatat dalam audit yang sama dengan penggantian line. Ini tetap fondasi
teknis di `_30`; tidak mengubah paket, lisensi, harga, atau status
komersialisasi.

Batch 170 menutup jalur lama tambah, ubah, dan hapus satu line Resep Produk
dengan snapshot dan audit atomik yang sama. Ini perbaikan fondasi teknis di
`_30`; paket, lisensi, harga, dan status komersialisasi tidak berubah.

Batch 171 menambahkan audit atomik pada tambah dan hapus mapping Product Extra,
tanpa mengganti prepared statement, lock, atau aturan divisi yang telah ada.
Ini tetap fondasi teknis di `_30`; paket, lisensi, harga, dan status
komersialisasi tidak berubah.

Batch 172 menutup writer Bundle Produk: tab editor lama ditolak dengan snapshot
revision, header/line dikunci saat transaksi, dan tambah/ganti isi/ubah status
dicatat sebagai audit atomik. Ini tetap fondasi teknis di `_30`; paket,
lisensi, harga, dan status komersialisasi tidak berubah.

Batch 173 menambahkan riwayat versi formula append-only pada editor Formula
Component kanonis dan migration schema yang telah diuji replay di staging.
Ini menjaga jejak perubahan resep produksi, bukan fitur lisensi atau paket.
Batch 174 kemudian mengalihkan jalur Master lama dan memensiunkan endpoint
Formula per-baris agar semua perubahan memakai snapshot kanonis tersebut.
Batch 175 menambahkan restore versi berotorisasi dengan proof password
satu-kali, lock, history, dan audit; UAT tetap menjadi backlog audit `_30`.
Batch 176–177 menyamakan Void/Refund/Reprint POS Mobile/APK dengan boundary
reauth web: proof satu-kali kini terikat perangkat, aksi, dan order, sehingga
APK kandidat harus mengikuti kontrak verify-proof tersebut. Ini fondasi teknis
di `_30`, bukan fitur lisensi, paket, atau kenaikan status komersialisasi. Status
komersialisasi tidak berubah.

Dokumen ini adalah sumber utama keputusan komersialisasi. Ia tidak menjadi
daftar bug aplikasi dan tidak menggantikan audit `_30`.

## 0. Batas Dokumen dan Checklist Status

`docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
menjadi sumber tunggal untuk bug, security, RBAC, integritas data/transaksi,
HPP, navigasi, UI, test, dependency, schema, backup, dan release foundation.
Dokumen ini hanya memakai hasil audit tersebut sebagai prasyarat, lalu mengatur
produk yang akan dijual dan operasi vendor/customer.

Laporan batch/modul dan execution log hanya menjawab apa yang sudah dikerjakan;
status roadmap tetap diperbarui di dua dokumen induk ini.

Legenda: `[x]` keputusan/artefak sudah ada; `[~]` sebagian atau menunggu
gerbang audit; `[ ]` belum dibangun atau belum dibuktikan.

- `[x]` Model penjualan on-premise perpetual per organisasi sudah diputuskan.
- `[x]` Paket awal, add-on, masa maintenance, offline grace period, dan aturan
  device sudah menjadi keputusan konsep.
- `[~]` Product readiness belum lulus `TECH-HANDOFF-A0-A5`; status dan bukti
  setiap child dibaca hanya dari control board `_30`.
- `[x]` Katalog fitur machine-readable dan dependency awal: `app-manifest.json`
  Finance telah diimpor sebagai draft terjejak pada Control Center.
- `[ ]` Harga final, EULA/SLA, data policy, serta dokumen penawaran yang telah
  ditinjau pihak berwenang.
- `[~]` Profil usaha/customer-facing productization dan onboarding generik.
  Batch 220 menyelesaikan jalur inti login, shell, QR ulasan, dokumen, label,
  fallback cetak, dan setup admin tiga langkah; template marketing/Menu Book
  serta install profile customer tetap pekerjaan C2 terbuka.
- `[~]` Installer Linux, artifact signed, claim/receipt, DB-copy upgrade dan web
  rollback sudah teruji Batch 243. Domain/scheduler target, migrasi bisnis baru,
  Windows dan penerimaan produksi tetap terpisah dari kelulusan trial Linux.
- `[~]` Signed entitlement dan agen init/activate/recover/poll/cache sudah lulus
  HTTPS Control terisolasi. FeatureGate tetap audit-only; aktivasi customer,
  pairing/native guard dan enforcement produksi belum dijalankan.
- `[~]` Product Control Center multi-produk tersedia terpisah di
  `control.namuaprojects.com`; adopsi Finance tetap fase tersendiri dan belum
  boleh diklaim selesai.
- `[~]` Panduan aplikasi versi release: `/guide` sudah diimplementasikan pada
  source (Batch 261), mencakup peran/modul, admin UI/server dan troubleshooting.
  Aktivasi/sidebar, walkthrough pengguna awam dan pembuktian panduan + migrasi
  benar-benar ikut artifact customer tetap terbuka; belum penerimaan C5 penuh.
- `[ ]` Pilot berbayar, support operation, dan penjualan resmi.

### 0.1 Status kanonis fase C0–C5

Tabel ini adalah sumber status komersialisasi yang dapat dibaca dashboard
internal. Status teknis A0–A5 tetap hanya berasal dari control board `_30`.

| Fase | Implementasi | Validasi tertinggi | Release/data | Status fase | Alasan/gerbang berikutnya |
| --- | --- | --- | --- | --- | --- |
| C0 — handoff/go-no-go | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `PRACTICE_HANDOFF_READY` | Batch 243: barang dan panduan UI siap untuk latihan web Linux terbatas. Bukan go-live; UAT/handoff produksi dan persetujuan owner tetap wajib. |
| C1 — paket/katalog/kontrak | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `CATALOG_DRAFT_READY` | Manifest v2 NAMUA_FINANCE di Control: 28 feature, 4 edition, 29 dependency, PERPETUAL dan maintenance awal 365 hari. Kandidat praktik terbaru alpha.10 DRAFT (Batch 243), bukan publish. Harga, EULA/SLA, data policy, add-on/override per customer dan kontrak pilot belum final. |
| C2 — productization/onboarding | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `CORE_BRANDING_HTTP_PASS` | Batch 243: profil/logo/login alpha.10 benar melalui 22 tes HTTPS pada clean install dan upgrade; upload lama terbawa. Marketing/legacy branding menyeluruh, preset demo, pajak/service/integrasi/privacy serta penerimaan customer tetap terbuka. |
| C3 — artifact/installer/update | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `LINUX_DELIVERY_TRIAL_PASS` | Histori trial alpha.10 Batch 243 tetap valid untuk paket itu saja. Batch 245 alpha.12 menambahkan deklarasi CUSTOMER_CLEAN, adapter build dan penerimaan manifest Control; guard validator Control serta build/publish/UAT customer aktual masih pending. Lihat checklist C3 terbaru di bawah. |
| C4 — License Hub/entitlement | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `CONTROL_HTTPS_CACHE_PASS` | Batch 242–243: 58 unit agen/model/izin, 18 HTTPS Control terisolasi PASS, recovery identitas tetap, renewal setelah offline, revoke/replay, cache terbaca web aktual. Kunci Finance root-only siap. Mode audit-only; aktivasi customer, Windows/native guard, pairing/limit dan enforcement belum diterima. Bug operasional/build/UAT APK ditunda. |
| C5 — pilot/operasi penjualan | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `UI_PRACTICE_GUIDE_READY` | Batch 243: halaman Control /finance/practice dan tombol dari Release tersedia, panduan owner dan admin server terpisah. Praktik customer/domain, pelatihan per modul, kontrak/SLA dan go-live tetap dilaksanakan owner, tidak otomatis oleh engineer. |

**Aturan urutan:** tidak ada enforcement lisensi atau pembangunan License Hub
yang menutupi temuan P0/P1. Pekerjaan teknis yang masih menjadi bug tetap
ditulis dan diselesaikan di `_30`.

### 0.2 Sinkronisasi dengan Namua Application Control Center — 2026-09-07

Control Center pada `https://control.namuaprojects.com` adalah control plane
vendor terpisah; database dan source-nya bukan bagian dari Finance. Pemeriksaan
terhadap roadmap/source Control menunjukkan fondasi berikut sudah tersedia dan
tetap sejalan dengan arah produk ini:

| Area | Kondisi Control Center | Kondisi Finance | Status sinkronisasi |
| --- | --- | --- | --- |
| Batas data/monitoring | Customer/instance terpisah; heartbeat signed, outbound, tanpa database atau data bisnis customer. | Sender heartbeat Finance memakai kontrak HMAC/nonce/idempotency yang sama. Pilot `NAMUA_FINANCE` telah diarsipkan dari Control, jadi ini bukti kontrak, bukan integrasi aktif. | `SELARAS`, jangan aktifkan ulang tanpa instance runtime terpisah. |
| C1 katalog | Manifest v2, scanner allowlist, preview/import produk-edition-feature, dependency, hak perpetual, maintenance, dan audit tersedia. | Katalog draft: 28 feature, 4 edition, 29 dependency, limit outlet/terminal awal. Alpha.3 cutoff b10fa37 sudah diregistrasikan sebagai DRAFT, belum publish. | `TERHUBUNG_DRAFT`; catalog bukan FeatureGate dan belum mengubah runtime customer. |
| C2 identitas customer | Registry customer/PIC/instance vendor tersedia. | Profil lokal, setup admin, branding shell/login/QR/dokumen/label, locale/currency/timezone, dan fallback printer tersedia. | `TIDAK TUMPANG TINDIH`; registry vendor bukan pengganti profil usaha lokal. Template marketing/install profile tetap Finance-side. |
| C3 delivery | Maker-checker tetap; receipt migrasi tidak menutup deployment. Alpha.12: profil clean, adapter unsigned dan format signed Control telah diselaraskan di Finance. | Histori alpha.10: trial installer/upgrade/rollback/HTTPS PASS. Bukti ini bukan bukti clean-customer alpha.12; lihat validasi Batch 245. | `LINUX_DELIVERY_TRIAL_PASS` historis; integrasi validator Control dan publish/pilot alpha.12 masih terpisah. |
| C4 lisensi | Recovery bertanda tangan mempertahankan instalasi/slot; issuer memeriksa eligibility kembali dan dapat renew setelah offline. Kunci Finance terpisah siap. | 18 tes HTTPS Control terisolasi + 58 unit PASS, web Finance membaca cache signed nyata. | `CONTROL_HTTPS_CACHE_PASS`; bukan aktivasi customer. Native guard, pairing/limit/enforcement masih wajib sebelum klaim proteksi penuh. |
| C5 operasi | Halaman /finance/practice dari menu Release, urutan customer→instance→subscription→release→deployment→aktivasi. | Panduan setup/pemulihan admin dan latihan owner tersedia; keputusan harga/domain/pilot milik owner. | `UI_PRACTICE_GUIDE_READY`; bukan pilot produksi PASS. |

**Keputusan sinkronisasi yang mengikat:** Finance tetap memakai lisensi
**perpetual**. Hak menjalankan versi yang sudah dibeli tidak boleh berubah
menjadi read-only hanya karena masa maintenance berakhir. Control perlu
mempunyai dua tanggal/keadaan berbeda: hak pakai perpetual dan
`maintenance_ends_at` untuk update/support. Lease, grace, revoke, dan
FeatureGate hanya boleh menegakkan hak produk yang memang dibeli—bukan
menyandera transaksi atau data customer.

**Urutan adopsi Finance yang benar:**

1. C1: matangkan katalog `app-manifest.json` Finance, finalkan add-on/override
   per customer serta dokumen komersial; model Control sudah memisahkan hak
   perpetual dari maintenance.
2. C2: bangun profil usaha/onboarding Finance; data ini tetap lokal pada
   instalasi customer dan tidak disalin ke Control.
3. C3: hubungkan builder Finance ke release SemVer, artifact manifest,
   signature, installer/claim, serta receipt Control.
4. C4: baru tambahkan verifier/FeatureGate, aktivasi server, cache offline,
   batas outlet/terminal, dan kontrak APK yang fail-safe.
5. C5: jalankan DEMO/pilot Finance dari artifact—bukan dari folder source atau
   database staging yang sedang dipakai.

## Keputusan Final Singkat

1. Finance dijual sebagai **lisensi perpetual per organisasi dan instalasi**,
   bukan penjualan source code.
2. Semua paket memakai **satu codebase, satu skema database, dan satu jalur
   update**. Kita tidak membuat source code Starter, Operations, dan Control
   secara terpisah.
3. Paket Starter dimulai dari **1 outlet dan 1 terminal POS aktif**. Paket
   Operations menjadi titik awal yang disarankan untuk 3 terminal. Outlet,
   terminal, APK POS, dan modul tertentu dapat ditambah sebagai add-on.
4. Fitur berbayar hanya dapat diaktifkan oleh entitlement bertanda tangan dari
   License Hub. Pengaturan lokal customer tidak boleh menaikkan paket sendiri.
5. Customer tetap dapat memakai versi yang telah dibeli setelah masa support
   berakhir. Yang berhenti adalah update dan dukungan baru, bukan akses data
   atau transaksi kasir.
6. Masa update dan dukungan standar adalah **12 bulan** sejak aktivasi.
   Perpanjangan maintenance membuka kembali akses release dan support tanpa
   mengubah hak pakai perpetual yang sudah dimiliki.
7. Lisensi menentukan paket dan kapasitas customer. RBAC tetap menentukan
   pegawai mana di customer tersebut yang boleh memakai fitur.
8. Setiap customer pada tahap awal memakai database dan instalasi sendiri.
   Multi-tenant cloud bukan bagian dari rilis komersial pertama.
9. Update dilakukan melalui paket rilis bertanda tangan dan migration yang
   diaudit, bukan melalui `git pull` di server customer.
10. Aplikasi tidak boleh mati mendadak ketika internet putus. Lisensi memakai
   cache bertanda tangan dengan grace period offline 30 hari dan jalur aktivasi
   offline untuk lokasi tanpa internet stabil.
11. Nama usaha, logo, ikon, warna, alamat, tautan publik, dan identitas dokumen
    menjadi pengaturan customer. Tidak boleh ada identitas Namua, MPP, Pemkab,
    domain, atau alamat tertentu yang menjadi hardcode runtime.
12. Implementasi dimulai dari remediasi keamanan, RBAC, integritas transaksi,
    pembersihan artefak rilis, dan automated test. License Hub tidak boleh
    dibangun untuk menutupi fondasi aplikasi yang belum lolos audit.
13. License Hub dikembangkan sebagai bagian dari aplikasi vendor terpisah
    bernama sementara **Product Control Center**. Control plane ini menangani
    banyak produk: katalog, repository privat, build, artefak, generator
    instalasi, paket, lisensi, update, monitoring, approval, dan audit.
14. Source tetap berada di Git privat dan artefak berada di object/artifact
    storage. Database Product Control Center hanya menyimpan metadata,
    referensi commit/tag immutable, manifest, checksum, lokasi artefak, dan
    audit; source tidak disimpan sebagai blob database biasa.
15. Database customer dibuat dari migration dan seed kanonis. Dump produksi,
    backup, log, upload, transaksi, pegawai, customer, stok, payroll, credential,
    dan konfigurasi device tidak pernah menjadi data awal paket.

### Prasyarat Sebelum Menjual Lisensi Pertama

Finance saat ini merupakan aplikasi internal yang kaya fitur dan belum boleh
dipaketkan langsung untuk customer. Status bug, security, RBAC, integritas
transaksi, HPP, test, dependency, schema, backup, dan release foundation
dimiliki serta dinilai di `_30`.

Roadmap ini hanya mengambil hasil `_30` sebagai **gerbang prasyarat**. Setelah
gerbang tersebut lulus, pekerjaan komersial dimulai dari katalog penawaran,
productization customer, delivery release, entitlement, License Hub, device,
pilot, dan operasi penjualan. Tidak ada perbaikan bug atau bypass keamanan yang
boleh disembunyikan di dalam fase komersialisasi.

### Bentuk Produk yang Dijual

Customer menerima installer atau deployment package resmi, dokumentasi,
lisensi, dan akses support sesuai paketnya. Repository Git, private key
lisensi, dan source code pengembangan tetap milik produk Finance. Bila suatu
customer membutuhkan source escrow, hal itu hanya tersedia sebagai kontrak
Enterprise khusus dan bukan bagian dari jual beli putus standar.

## 1. Tujuan Produk

Finance akan dijual sebagai aplikasi operasional dan kasir yang lengkap, dengan
POS web dan APK POS sebagai satu ekosistem. Customer membeli hak pakai aplikasi
untuk organisasinya, bukan kepemilikan repository atau hak menjual ulang.

Target awal yang disarankan adalah **lisensi perpetual per instalasi**:

- Customer tetap dapat memakai versi yang telah dibeli tanpa batas waktu.
- Lisensi menentukan jumlah outlet, terminal POS, dan fitur yang boleh dipakai.
- Update fitur dan dukungan dapat diberikan selama masa maintenance tertentu,
  misalnya 12 bulan, lalu diperpanjang secara terpisah.
- Data customer tetap milik customer dan selalu dapat diekspor. Lisensi tidak
  boleh menjadi alat untuk menyandera data operasional.

## 2. Batas Penting: Source Code Tidak Bisa Dikunci Penuh

Jika customer menerima source code PHP, akses Git, atau salinan server yang
lengkap, tidak ada mekanisme teknis yang dapat menjamin source code tidak akan
dipakai ulang atau diperjualbelikan. Obfuscation, Docker, dan penguncian MAC
hanya membuat penyalinan lebih sulit, bukan mustahil.

Karena itu model yang sehat adalah:

1. Customer menerima **aplikasi yang sudah dipaketkan untuk dipasang**, bukan
   repository Git dan bukan kredensial pengembangan.
2. Hak penggunaan, larangan menyalin, larangan memindahtangankan, dan batas
   support ditulis jelas dalam perjanjian lisensi. Dokumen ini perlu ditinjau
   konsultan hukum sebelum dipakai komersial.
3. Lisensi digital, aktivasi perangkat, tanda tangan update, dan audit
   instalasi menjadi penguat teknis dari perjanjian tersebut.
4. Bila suatu saat ada penjualan source code atau source escrow, itu menjadi
   paket Enterprise terpisah dengan harga dan kontrak yang jauh berbeda.

Rekomendasi: jangan mulai dari obfuscation. Mulai dari release yang rapi,
paket instalasi, lisensi bertanda tangan, dan proses support yang jelas. PHP
encoder dapat dipertimbangkan setelah build dan updater stabil, tetapi tidak
boleh menjadi satu-satunya perlindungan.

## 3. Model Lisensi yang Direkomendasikan

### Lisensi utama

Satu lisensi mewakili satu organisasi dan satu instalasi Finance. Isi lisensi
minimal:

- `license_id` dan kode aktivasi.
- identitas customer dan nama usaha.
- edisi produk dan daftar fitur yang dibeli.
- batas outlet, terminal POS, dan akun aktif bila diperlukan.
- masa maintenance atau hak menerima update.
- tanggal terbit, status, serta tanda tangan digital.

Lisensi ditandatangani oleh server penerbit milik kita. Aplikasi Finance hanya
menyimpan public key untuk memverifikasi tanda tangan. Dengan pola ini aplikasi
tetap dapat memeriksa keaslian lisensi saat offline tanpa menyimpan rahasia
penerbit di server customer.

### Terminal adalah device, bukan akun

Jumlah slot mengikuti paket atau add-on. Starter memiliki satu slot terminal,
sedangkan Operations direkomendasikan memiliki tiga. Definisinya:

- maksimal sejumlah **terminal POS aktif** yang tertulis pada lisensi;
- satu terminal dapat memiliki beberapa akun kasir sesuai RBAC;
- akun web admin tidak otomatis dihitung sebagai terminal POS;
- customer dapat menonaktifkan device lama dan mengaktifkan device pengganti
  melalui halaman berjejak, dengan batas dan persetujuan yang jelas.

Jangan memakai MAC address sebagai satu-satunya identitas device. MAC dapat
berubah, mudah dipalsukan, dan tidak cocok untuk browser. Untuk POS web atau
APK, gunakan pasangan kunci perangkat saat aktivasi. APK dapat menyimpan kunci
di Android Keystore. POS web dapat memakai device certificate yang dipasangkan
melalui Local Agent atau aplikasi terminal, bukan hanya cookie browser.

### Perlakuan saat lisensi bermasalah

- Lisensi perpetual yang sah tetap membuka fitur yang telah dibeli pada versi
  yang sudah terpasang.
- Masa maintenance berakhir hanya menghentikan akses update dan support baru,
  bukan menutup transaksi atau data lama.
- Koneksi ke server lisensi putus tidak boleh langsung mematikan kasir.
  Gunakan grace period offline yang wajar dan status peringatan di admin.
- Jika terjadi indikasi lisensi tidak sah, sistem masuk mode pembatasan yang
  aman dan memberi waktu perbaikan. Laporan dan ekspor data tetap tersedia.

## 4. Level Fitur dan Paket Produk

RBAC dan lisensi mempunyai tugas berbeda:

- **RBAC** menjawab: siapa yang boleh memakai fitur.
- **Entitlement lisensi** menjawab: fitur apa yang dibeli instalasi tersebut.

Menyembunyikan menu saja tidak cukup. Endpoint, controller, job CLI, dan APK
harus melalui satu `FeatureGate` di server. Jika fitur tidak dibeli, endpoint
ditolak dengan pesan yang jelas; bukan hanya tombolnya yang hilang.

Paket komersial awal yang dipakai sebagai standar:

| Paket | Cakupan kandidat |
| --- | --- |
| Starter POS | Kasir, katalog, produk, pembayaran, printer dasar, laporan penjualan dasar, user/role dasar, 1 outlet, dan 1 terminal POS. |
| Operations | Semua Starter ditambah gudang, purchase order, store request, adjustment, stok harian, member, promo, voucher, self order, reservasi, 1 outlet, dan sampai 3 terminal POS. |
| Control | Semua Operations ditambah recipe, produksi component, HPP, defisit stok, period lock, stock health, audit penjualan/HPP, laporan keuangan lanjutan, attendance, dan payroll. |
| Enterprise | Semua Control ditambah APK POS, integrasi API, online order, automasi khusus, SSO, custom report, serta source escrow hanya bila disepakati secara terpisah. |

Peta awal modul dalam bahasa user:

| Yang dikerjakan customer | Modul yang diterima | Paket minimum kandidat |
| --- | --- | --- |
| Menata usaha dan pegawai aplikasi | Profil usaha, outlet, user, role, metode bayar, rekening dasar | Starter POS |
| Melayani penjualan di kasir | Katalog, produk, extra, bundle, draft, pembayaran, void dasar, printer kasir | Starter POS |
| Melihat hasil penjualan | Penjualan harian, transaksi, produk/extra, metode bayar, tutup kasir | Starter POS |
| Mengelola pelanggan dan promosi | Member, point, stamp, promo, voucher, pemakaian voucher, review | Operations |
| Menerima pesanan selain kasir | Self order, reservasi, online order dasar sesuai add-on | Operations |
| Membeli dan meminta barang | Purchase Order, penerimaan, Store Request, fulfillment | Operations |
| Menjaga stok operasional | Gudang, stok divisi, adjustment, lot, stok harian, recon dasar | Operations |
| Membuat bahan prepare/component | Resep, formula, batch produksi, trace pemakaian | Control |
| Mengendalikan biaya dan HPP | HPP produk, HPP penjualan, cost control, defisit, Stock Health | Control |
| Menutup dan mengaudit periode | Period lock, audit commit, audit penjualan/HPP, koreksi berjejak | Control |
| Mengelola uang dan laporan | Kas/bank, mutasi, hutang/piutang, laporan keuangan lanjutan | Control |
| Mengelola kehadiran dan gaji | Jadwal, presensi, PH, pengajuan, payroll | Control atau add-on HR |
| Mengelola aset | Pendataan, lock aset, laporan kejadian, perubahan berapproval | Control atau add-on Asset |
| Memakai perangkat/mobile tambahan | APK POS, API, pairing device, terminal tambahan | Add-on/Enterprise |
| Memakai automasi/integrasi khusus | WhatsApp, Telegram Bot, SSO, custom report, marketplace/perangkat | Add-on/Enterprise |

Peta ini masih kandidat komersial. Finalisasi dilakukan setelah dependency
fitur dan biaya support diuji; pemindahan satu modul antar paket cukup mengubah
entitlement, bukan membuat source code baru.

### Hipotesis harga awal untuk pilot

Harga berikut adalah pegangan pengujian pasar, bukan harga permanen di dalam
source code. Nilainya wajib dievaluasi kembali setelah dua atau tiga customer
pilot dan setelah biaya instalasi/support nyata diketahui.

| Paket | Harga pengenalan kandidat | Arah harga normal kandidat |
| --- | ---: | ---: |
| Starter POS | Mulai Rp1.000.000 | Rp1.500.000 |
| Operations | Rp3.500.000 | Rp4.500.000 |
| Control | Rp7.500.000 | Rp9.500.000 |
| Enterprise | Mulai Rp15.000.000 | Berdasarkan kebutuhan dan kontrak |

Biaya implementasi, migrasi data, perangkat, perjalanan, integrasi khusus,
terminal tambahan, outlet tambahan, dan support di luar cakupan tidak otomatis
masuk harga lisensi. Semua harus terlihat terpisah pada penawaran agar margin
support tidak hilang.

### Keputusan pemisahan fitur: jangan memisahkan source code

Kita tidak membuat folder, branch, atau build manual yang berbeda untuk setiap
paket. Pola itu akan menyebabkan perbaikan bug harus disalin berkali-kali,
migration mudah berbeda, dan customer lama berisiko tertinggal.

Pola implementasi yang disepakati:

1. Semua customer menerima artefak release yang sama sesuai versi produknya.
2. License Hub menerbitkan daftar entitlement dan limit yang ditandatangani.
3. Finance menyimpan cache lisensi yang terverifikasi untuk operasi offline.
4. Satu `FeatureGate` memeriksa lisensi pada menu, route/controller, API/APK,
   export, laporan, cron, worker, dan proses latar belakang.
5. RBAC diperiksa setelah FeatureGate: lisensi menjawab apakah perusahaan
   membeli modul, RBAC menjawab pegawai mana yang boleh menggunakannya.
6. Pengaturan lokal hanya menampilkan status paket dan menerima activation
   file bertanda tangan. Superadmin customer tidak dapat mencentang sendiri
   fitur berbayar yang belum dibeli.
7. Feature flag teknis untuk pilot/rollback dipisahkan dari entitlement
   komersial dan tidak tersedia sebagai bypass pada UI customer.

Jika paket diturunkan atau add-on berakhir, data modul tidak dihapus. Modul
masuk keadaan `READ_ONLY` bila diperlukan agar riwayat dapat dibaca/diekspor,
tetapi transaksi baru ditolak. Saat paket dinaikkan, modul aktif kembali tanpa
mengganti source code atau memindahkan database.

Setiap fitur juga mempunyai dependency eksplisit. Contoh: produksi resep tidak
boleh aktif tanpa inventory dan recipe; payroll yang memakai kehadiran tidak
boleh aktif tanpa HR dan attendance. License Hub menolak kombinasi paket yang
tidak valid sebelum lisensi diterbitkan.

Fitur yang cocok menjadi add-on terpisah:

- APK POS dan jumlah terminal tambahan.
- Multi outlet tambahan.
- Online order atau integrasi marketplace.
- Attendance, payroll, dan HR.
- Asset management.
- Integrasi akuntansi, WhatsApp, Telegram Bot, pembayaran, atau perangkat khusus.

Harga tidak disimpan di source code; harga adalah kebijakan komersial yang dapat
berubah. Yang dibuat stabil adalah kode fitur, misalnya `pos.cashier`,
`inventory.production`, `hr.payroll`, dan `mobile.pos`, sehingga isi paket dapat
diatur dari portal lisensi tanpa memecah aplikasi customer.

### Aturan add-on yang final

| Add-on | Satuan lisensi |
| --- | --- |
| Terminal POS tambahan | Per terminal aktif. |
| APK POS | Per device Android aktif; dihitung sebagai terminal POS. |
| Outlet tambahan | Per outlet aktif. |
| Online order / self order eksternal | Per organisasi atau per outlet sesuai integrasi. |
| Attendance dan payroll | Per organisasi. |
| Integrasi API / perangkat khusus | Per integrasi. |
| Source escrow | Kontrak Enterprise satuan proyek, bukan fitur biasa. |

## 5. Arsitektur Teknis Lisensi

Tahap awal tidak perlu mengubah seluruh tabel bisnis menjadi multi-tenant.
Setiap customer tetap memakai database dan instalasi sendiri. Multi-tenant
cloud adalah proyek berbeda yang baru layak dikerjakan saat memang dibutuhkan.

Fondasi database yang disarankan pada instalasi customer:

| Tabel konsep | Fungsi |
| --- | --- |
| `lic_installation` | Identitas unik instalasi, public key perangkat/server, versi aplikasi, dan status aktivasi. |
| `lic_license_cache` | Salinan payload lisensi dan signature terakhir yang terverifikasi, edisi, batas pemakaian, masa maintenance, grace period, dan status lokal. |
| `lic_feature` | Daftar kode fitur resmi aplikasi. |
| `lic_feature_cache` | Fitur, mode akses, dependency, dan limit yang aktif pada lisensi customer. |
| `lic_device_activation` | Terminal POS terdaftar, public key device, outlet, status, waktu aktivasi, dan jejak penggantian. |
| `lic_activation_audit` | Jejak aktivasi, deaktifasi, reset, serta alasan. |
| `lic_heartbeat_queue` | Antrean metadata kesehatan lisensi minimum saat koneksi License Hub sedang putus. |
| `lic_runtime_audit` | Jejak hasil verifikasi, penolakan feature gate, perubahan mode, dan kejadian grace period. |
| `lic_update_history` | Riwayat pemeriksaan, download, backup, apply, dan rollback release. |

Server lisensi pusat hanya perlu menyimpan data minimum: customer, lisensi,
status aktivasi, daftar device, versi aplikasi, dan metadata update. Jangan
mengirim transaksi, nama customer kasir, atau data keuangan customer ke server
lisensi kecuali mereka memberi persetujuan eksplisit untuk support bundle.

### Runtime lisensi yang dilindungi

Komponen verifikasi lisensi boleh dipaketkan dengan PHP encoder setelah alur
release stabil. Ia memverifikasi signature, instalasi, device, entitlement,
masa maintenance, dan grace period. Bagian penting aplikasi memanggil kontrak
runtime yang sama sehingga menghapus satu file membuat health check gagal.

Namun dependensi tidak boleh dibuat sebagai satu titik kegagalan yang langsung
mematikan kasir. Runtime harus mempunyai cache offline sah, pesan diagnostik,
grace period, dan mode pemulihan. Encoder adalah hambatan teknis untuk pengguna
awam, bukan jaminan bahwa programmer tidak dapat menulis ulang sistem. Kontrak,
distribusi release, private repository, signature, dan License Hub tetap
menjadi lapisan perlindungan utama.

### Product Control Center multi-produk

Dashboard vendor tidak dibatasi sebagai `Finance License Hub`. Ia dibuat
sebagai control plane yang dapat melayani Finance, APK POS, dan aplikasi lain
di masa depan. Source code tetap dikelola oleh Git privat yang terintegrasi;
Product Control Center menyimpan metadata repository dan memerintahkan build
runner terisolasi untuk mengambil commit/tag yang telah disetujui.

| Modul pusat | Fungsi pengguna |
| --- | --- |
| Product Catalog | Mendaftarkan produk, versi, requirement, lifecycle, serta adapter build/install. |
| Source Integration | Menghubungkan repository privat, branch policy, commit/tag immutable, webhook, dan approval. |
| Build Orchestrator | Membuat clean checkout, menjalankan test/scan, dan menghasilkan build yang dapat diulang. |
| Artifact Registry | Menyimpan metadata paket, checksum, SBOM, signature, channel, serta lokasi artifact storage. |
| Installation Generator | Menggabungkan artefak generik dengan profil OS, migration, seed, onboarding, dan lisensi customer. |
| Package & Feature Catalog | Mengelola paket jual, add-on, dependency fitur, kapasitas, dan versi komersial. |
| License Hub | Mengelola customer, instalasi, entitlement, maintenance, aktivasi online/offline, device, revoke, dan transfer. |
| Release & Update Center | Mengelola pilot/stable/critical-fix, compatibility, rollout, rollback, dan status update. |
| Installation Monitor | Menampilkan versi, health minimal, penggunaan slot, heartbeat, dan hasil update berdasarkan persetujuan. |
| Signing & Secret Service | Menjaga private key dan secret build agar tidak masuk repository atau server customer. |
| Approval & Audit | Mencatat pembuat build, reviewer, penerbit lisensi, downloader, perubahan paket, dan operasi sensitif. |

Database pusat menyimpan konsep seperti `prd_product`, `prd_repository`,
`prd_build_definition`, `rel_release`, `rel_artifact`, `ins_profile`,
`ins_seed_manifest`, `pkg_feature`, `pkg_plan`, `lic_license`,
`lic_device_activation`, `ops_installation`, `ops_health_event`, dan
`audit_event`. Nama final ditetapkan saat desain schema; batas datanya sudah
ditetapkan sejak awal.

Repository privat, artifact storage, database control plane, build runner, dan
signing service harus dipisahkan secara akses. Build runner memakai credential
jangka pendek, tidak menerima database produksi customer, dan dibuang setelah
build. Private key signing tidak pernah dikirim ke runner umum atau aplikasi
customer.

Monitoring normal hanya memerlukan installation ID pseudonim, produk/versi,
status lisensi, jumlah slot terpakai, health service, dan hasil update. Data
transaksi, omzet, stok, resep, payroll, identitas pegawai/customer, dan dokumen
customer tidak dikirim secara default. Diagnostic support yang lebih rinci
harus dibuat terpisah, disamarkan, dibatasi waktu, dan disetujui admin customer.

### Generator instalasi dan database awal

Satu produk dan satu versi hanya menghasilkan satu artefak kode generik. Paket
Starter, Operations, Control, atau Enterprise tidak dibuat dengan menyalin dan
menghapus source secara manual. Generator memilih artefak yang sama, lalu
menambahkan install profile, seed profile, onboarding profile, dan signed
entitlement sesuai pesanan customer.

Klasifikasi database instalasi baru:

| Kelas | Contoh | Aturan |
| --- | --- | --- |
| Schema | Tabel, index, foreign key, view, registry migration | Selalu dari migration terurut dan ber-checksum. |
| Seed platform wajib | Page/menu kanonis, action permission, status sistem | Netral, idempotent, deklaratif, dan dapat mencabut default lama yang salah. |
| Seed referensi produk | Satuan, alasan transaksi, tipe dokumen, role/template default | Hanya dipasang bila dependency produk/fitur terpenuhi. |
| Entitlement | Feature code, batas outlet/terminal, maintenance | Berasal dari lisensi bertanda tangan, bukan toggle/seed lokal. |
| Onboarding | Profil usaha, owner, outlet, timezone, rekening, metode bayar | Diisi saat instalasi; secret dan password dibuat saat itu. |
| Demo opsional | Data fiktif untuk demonstrasi | Artefak terpisah dan dapat dihapus penuh. |
| Runtime | Queue, session, cache, log, upload, audit | Dibuat setelah instalasi; folder disiapkan kosong dengan permission yang tepat. |
| Terlarang | Dump, backup, log lama, upload, transaksi, member/customer, pegawai, presensi, payroll, stok/lot/HPP, mutasi rekening, token, secret, PID, dan config device | Scanner menggagalkan build bila data ini ditemukan. |

Setiap migration dan seed mempunyai ID stabil, versi produk, dependency,
checksum, mode `insert/update/reconcile`, serta perilaku clean install dan
upgrade. SQL audit/repair historis berada di jalur support terpisah dan tidak
ikut update umum. Tidak ada seed yang menyalin isi database aktif Finance saat
ini melalui dump atau `INSERT ... SELECT`.

Alur generator:

1. Operator memilih produk, versi, release channel, customer, paket, target OS,
   mode online/offline, dan preset yang diizinkan.
2. Sistem mengunci commit/tag sumber dan membuat clean checkout pada runner.
3. Quality gate menjalankan test, lint, dependency scan, secret scan, data/PII
   scan, serta pemeriksaan file terlarang.
4. Sistem membangun artefak aplikasi generik dan memverifikasi reproducibility.
5. Registry menyelesaikan dependency migration, seed, service, cron, Printer
   Agent, WhatsApp, dan requirement lainnya.
6. Sistem membuat `dry-run report` berisi seluruh file/data yang masuk dan
   keluar, requirement, warning, serta alasan penolakan.
7. Setelah approval, generator membuat profil onboarding dan entitlement
   customer bertanda tangan tanpa menaruh private key di paket.
8. Generator menyusun installer online atau bundle offline, SBOM, checksum,
   signature, changelog, compatibility matrix, serta petunjuk instalasi.
9. Artefak disimpan di registry, seluruh langkah diaudit, dan link download
   dibatasi waktu serta customer.
10. Installer menjalankan preflight, membuat database dari schema/seed kanonis,
    meminta data onboarding, memasang service/cron, lalu mengirim hasil health
    check minimum ke Product Control Center bila customer menyetujuinya.

Hasil clean install, upgrade, dan repair/support adalah tiga jenis artefak
berbeda. Build tidak boleh mengambil workspace developer yang sedang kotor.
Ia wajib berasal dari tag/commit immutable dan gagal bila dump, log, upload,
secret, identitas customer lama, atau file di luar allowlist masuk ke paket.

## 6. Halaman yang Perlu Ada

### Di aplikasi Finance customer

1. **System > Lisensi & Aktivasi**
   - status lisensi, edisi, fitur aktif, batas outlet dan terminal;
   - masa update/support;
   - aktivasi offline bila instalasi tidak punya internet;
   - peringatan yang jelas, tanpa menghambat operasi mendadak.

2. **System > Device POS**
   - daftar terminal terdaftar dan outletnya;
   - pairing device baru, nonaktifkan device lama, dan alasan penggantian;
   - akses dibatasi Superadmin/owner customer.

3. **System > Pembaruan Aplikasi**
   - versi terpasang, release tersedia, changelog, dampak migration;
   - preflight, backup, jadwal maintenance, apply, dan hasil health check;
   - hanya akun berwenang yang dapat menerapkan update.

4. **System > Paket Dukungan**
   - membuat support bundle yang telah disamarkan;
   - memuat versi, schema, health check, error code, dan konfigurasi teknis;
   - tidak mengirim data bisnis penuh tanpa persetujuan admin.

### Di Product Control Center milik vendor

1. Katalog seluruh produk, repository, build definition, versi, requirement,
   dan lifecycle.
2. Generator clean install/upgrade berdasarkan package, customer, target OS,
   migration manifest, dan seed profile.
3. Pengelolaan customer, lisensi, paket, add-on, device, dan masa maintenance.
4. Penerbitan aktivasi, reset device, revoke darurat, dan riwayat audit.
5. Artifact registry, release approval, signature, rollout, serta rollback.
6. Dashboard versi dan health minimum agar support mengetahui instalasi yang
   tertinggal migration atau memiliki backend/APK yang tidak kompatibel.

## 7. Pola Update yang Aman

Update tidak boleh berupa instruksi `git pull` ke server customer. Setiap rilis
harus berupa paket yang jelas dan bertanda tangan.

Alur update yang disarankan:

1. Kita membuat build rilis dengan nomor versi, catatan perubahan, checksum,
   signature, daftar migration SQL, dan kebutuhan minimum PHP/MySQL.
2. Aplikasi customer memeriksa manifest update dan menampilkan dampaknya.
3. Admin memilih jadwal maintenance. Sistem melakukan preflight schema,
   ruang disk, permission folder, worker/cron, dan versi APK yang kompatibel.
4. Sistem membuat backup database dan file konfigurasi yang dapat diverifikasi.
5. Aplikasi masuk maintenance mode, menerapkan paket kode dan migration secara
   berurutan, lalu menjalankan health check otomatis.
6. Bila health check gagal, rollback dilakukan dari backup yang sama. Rollback
   database tidak boleh sekadar mengganti kode lama.
7. Riwayat update, hasil, dan error disimpan di `lic_update_history`.

Untuk APK POS, manifest juga membawa `minimum_backend_version` dan
`minimum_apk_version`. Backend tidak boleh menerima APK yang skemanya sudah
tidak kompatibel tanpa pesan pembaruan yang jelas.

## 8. Keamanan dan Operasional Minimum Sebelum Dijual

- Simpan password database, API key, dan private key di environment secret,
  bukan di Git dan bukan di file yang ikut paket customer.
- Gunakan HTTPS untuk aktivasi dan update.
- Tanda tangani lisensi dan paket update dengan asymmetric signature. Jangan
  memakai checksum biasa sebagai satu-satunya validasi.
- Pisahkan environment development, staging, dan production.
- Siapkan build release yang dapat diulang. Server customer tidak boleh menjadi
  tempat coding langsung.
- Setiap release wajib mempunyai migration manifest, backup plan, rollback
  plan, changelog, dan smoke test yang terdokumentasi.
- Audit log lisensi dan device tidak boleh bisa dihapus melalui UI biasa.
- Buat kebijakan reset device agar customer yang ganti HP, PC, atau printer
  tidak terkunci dari kasir saat jam operasional.

## 9. Productization dan Penghapusan Hardcode Identitas

Finance belum boleh dijual sebagai template customer umum selama identitas
Namua, MPP, Pemkab, alamat, domain, warna, atau path agent tertentu masih
menjadi keputusan hardcode runtime. Satu perubahan identitas harus berlaku
konsisten pada login, sidebar, browser title, favicon, laporan, dokumen, struk,
QR ulasan, member/self order, landing page, email/WhatsApp, dan APK.

### Tiga kelompok konfigurasi yang tidak boleh dicampur

| Kelompok | Contoh | Siapa yang boleh mengubah |
| --- | --- | --- |
| Profil usaha dan branding | Nama usaha, nama aplikasi yang tampil, logo, favicon, warna, alamat, kontak, tagline, footer, tautan publik. | Owner/Superadmin customer melalui UI dan audit trail. |
| Deployment dan secret | Database, encryption key, URL internal, storage, mail/WhatsApp credential, cron/worker, backup path, printer agent host/port. | Installer atau operator server melalui environment/config deployment, bukan UI umum. |
| Paket dan entitlement | Modul aktif, batas outlet, terminal, APK, maintenance, mode read-only. | License Hub melalui payload bertanda tangan; UI customer hanya membaca status. |

Dengan pemisahan ini, mengganti logo tidak membutuhkan developer, tetapi
customer juga tidak dapat mengaktifkan payroll atau multi-outlet hanya dengan
mengubah baris database pengaturan biasa.

### Halaman pengaturan yang perlu dibangun

`Settings` yang ada sekarang terutama mengelola akun/password, sedangkan
`System Tools` mengelola backup dan replikasi. Keduanya belum menjadi pusat
konfigurasi produk. Tambahkan **System > Profil Usaha & Tampilan** dengan bagian
berikut:

- Identitas usaha: nama legal, nama dagang, nama singkat, NPWP bila dipakai,
  alamat, telepon, email, website, zona waktu, bahasa, dan mata uang.
- Logo dan ikon: logo terang/gelap, favicon, gambar login, serta aset dokumen.
- Warna dan tampilan: warna utama, warna aksen, latar, sidebar, serta pilihan
  kontras yang tetap menjaga keterbacaan.
- Tautan publik: member, self order, reservasi, ulasan, landing page, bantuan,
  kebijakan privasi, dan WhatsApp.
- Dokumen dan cetak: identitas default nota/laporan, header/footer, tanda tangan,
  serta fallback yang kemudian dapat dioverride per outlet atau layout printer.
- Preview: login, sidebar, struk 58/80 mm, QR ulasan, laporan, dan halaman publik
  sebelum perubahan diterapkan.
- Riwayat perubahan dan tombol kembali ke tema netral bawaan produk.

Tambahkan pula kelompok System berikut agar customer tidak perlu mengubah
source code:

- **Lokalisasi & Dokumen:** timezone, locale, mata uang, format angka/tanggal,
  prefix dokumen, penomoran, rounding, dan override per outlet.
- **Pajak & Service:** tarif, inklusif/eksklusif, service, tanggal berlaku,
  akun tujuan, dan audit perubahan.
- **Keamanan:** password policy, idle/absolute session timeout, MFA, session
  device, re-authentication, dan audit login. Minimum vendor tidak boleh dapat
  diturunkan oleh customer.
- **Integrasi:** printer, WhatsApp, Telegram Bot, email, member/self order, payment gateway,
  storage, dan URL publik. Secret bersifat write-only dan disimpan di secret
  store, bukan tabel pengaturan bisnis.
- **Data & Privasi:** retention log/review/bukti, consent, ekspor data, purge
  terkontrol, telemetry opt-in, dan support bundle anonim.
- **Job & Kesehatan:** database, queue, cron, worker POS, Printer Agent,
  WhatsApp, storage, backup, serta error terakhir.
- **Update:** versi aplikasi/schema, channel, preflight, backup, migration,
  rollback, dan riwayat update.
- **Lisensi & Aktivasi:** paket, entitlement, kuota, terminal, maintenance,
  status offline, dan aktivasi. Entitlement hanya dapat dibaca dari payload
  bertanda tangan.
- **Onboarding:** checklist profil, outlet, akun owner, role, rekening,
  printer, backup/restore, dan transaksi uji sebelum go-live.

Fondasi teknis, panduan onboarding, dan Setup Assistant Telegram Bot internal
dicatat dan diuji di `_30`. Setup Assistant memindahkan operasi aman ke UI,
sedangkan token, webhook secret, URL publik kanonis, dan cron tetap berada pada
boundary deployment. Penentuan paket, entitlement, harga add-on, serta dukungan
operasional Telegram tetap pekerjaan C1–C5 pada roadmap ini; keberadaan modul
dan panduan tidak berarti fitur tersebut sudah siap dijual atau menambah progres
fase komersialisasi.

Staging Batch 135–137 sudah memprovision boundary deployment, memverifikasi bot,
menautkan grup Namua, memasang webhook, dan meluluskan pengiriman nyata melalui
client, hook Codex, serta worker queue. Bukti staging ini tetap tidak menaikkan
progres C1–C5 sebelum keamanan pengirim, packaging, support, dan acceptance
komersial diselesaikan.

Batch 139 membuat notifikasi Codex memuat ringkasan jawaban akhir yang telah
dibatasi dan disaring. Peningkatan operasional internal ini tidak mengubah
status paket/add-on Telegram pada roadmap komersialisasi.

Batch 138 menambah schema-only clean-install yang terverifikasi tanpa data
customer. Batch 140 melengkapinya dengan seed navigasi netral-customer dan
bootstrap owner pertama yang lulus pada database disposable. Onboarding,
installer, upgrade/rollback, serta acceptance customer masih gerbang A5/C2;
baseline teknis ini belum berarti paket customer siap jual.

Batch 141 menutup fondasi health check pascainstalasi dan membuktikan rollback
upgrade pada database disposable, termasuk pemulihan schema, migration ledger,
dan reference seed. Ini adalah kemajuan teknis A5 di `_30`; status C1–C5 tidak
berubah karena installer dan pilot customer nyata belum selesai.

Batch 142–144 menyelesaikan matrix runtime, retention/lifecycle, dan kontrak
signature/provenance Ed25519. Verifier teknis kini dapat menolak paket unsigned,
berubah, atau berasal dari key tidak dikenal sebelum instalasi. Ini belum
menaikkan C3: private key produksi, release approval, artifact registry,
installer/updater customer, SBOM, channel delivery, dan pilot masih belum ada.

Batch 145 menutup disposition teknis tujuh SQL legacy tanpa replay: satu masuk
baseline, empat hanya dapat di-enroll melalui fingerprint exact, satu diganti,
dan satu dipensiunkan. Jalur otomatis dibatasi ke source-line
`finance-managed-v1`; instalasi pre-catalog tetap memerlukan bridge manual.
Karena installer/updater customer dan UAT lintas versi belum tersedia, status
C3 tetap `NOT_STARTED` dan `BLOCKED`.

Batch 146 memisahkan credential database staging ke file privat luar source
serta melepas payload upload/backup/log/tmp dari index Git tanpa menghapus data
server. Source preflight kini lulus tanpa temuan. Ini masih technical handoff
di `_30`; status C3 belum berubah sampai baseline commit, rotasi secret,
installer, artefak customer, dan delivery update benar-benar diuji.

Konfigurasi deployment seperti password database, encryption key, private key,
tunnel, dan credential replikasi tidak masuk halaman owner biasa. Teknisi hanya
melihat status tersamarkan dan tindakan sensitif memerlukan hak khusus,
re-authentication, serta audit.

Konsep tabel yang perlu divalidasi terhadap schema yang sudah ada:

| Tabel konsep | Fungsi |
| --- | --- |
| `sys_business_profile` | Identitas legal dan operasional organisasi. |
| `sys_branding_setting` | Nama aplikasi yang tampil, tema, footer, dan pilihan white-label. |
| `sys_brand_asset` | Logo, favicon, gambar login, versi file, serta status aktif. |
| `sys_external_url` | URL publik member, self order, reservasi, review, support, dan privasi. |
| `sys_branding_audit` | Siapa mengubah identitas, nilai sebelum/sesudah, dan waktu perubahan. |
| `pos_outlet` | Tetap menjadi sumber override nama, alamat, kontak, dan identitas per outlet. |

Jangan membuat data ganda bila struktur yang setara sudah ada. Sebagai contoh,
`pos_outlet` dan `pos_print_general_setting` sudah menyediakan sebagian data
outlet/struk. Keduanya perlu dihubungkan ke profil usaha sebagai fallback,
bukan diganti secara serampangan.

Urutan sumber identitas harus selalu jelas:

1. override outlet atau dokumen tertentu;
2. profil usaha customer;
3. default produk yang netral.

Default terakhir tidak boleh berisi nama Namua, alamat Rembang, domain customer,
atau identitas proyek lama. Nama vendor seperti `Finance POS` dapat tetap
ditampilkan sebagai `Powered by Finance POS`. Opsi white-label penuh dapat
menjadi entitlement Enterprise, bukan hardcode per customer.

### Temuan hardcode yang sudah terpetakan

| Area | Kondisi yang ditemukan | Target perbaikan |
| --- | --- | --- |
| Login dan autentikasi | `Finance App`, `NAMUA COFFEE & EATERY`, logo statis, dan title masih tersebar. | Baca nama aplikasi, nama usaha, logo, favicon, dan gambar login dari branding service. |
| Header, sidebar, dan footer | Masih ada fallback `MPP`, `Pemkab Rembang`, serta tautan DPMPTSP. | Gunakan profil usaha dan footer produk netral. Hapus template/demo yang tidak dipakai setelah dependency scan. |
| Tema visual | Warna merah/krem utama masih tetap di CSS. | Hasilkan CSS variables dari setting yang tervalidasi, dengan tema netral sebagai fallback. |
| Struk dan preview printer | Fallback nama/alamat Namua dan logo file lokal masih ada walaupun setting printer sudah tersedia. | Printer memakai outlet -> profil usaha -> default netral; layout hanya menentukan data mana yang ditampilkan. |
| Landing, member, self order, reservasi, dan ulasan | Nama Namua, domain, SEO, nomor kontak, dan URL publik masih tersebar. | Semua URL dan identitas memakai `sys_external_url` dan profil usaha. |
| Printer agent | Nama folder, hostname, base URL, dan dokumentasi masih berorientasi Namua. | Buat agent produk generik, installer, config hasil pairing, serta panduan Windows/Linux yang netral. |
| Dokumen HR, roastery, label, email/WhatsApp | Sebagian template membawa identitas Namua langsung. | Pisahkan isi bisnis customer dari template produk; sediakan preset yang dapat diedit. |
| Konfigurasi server | Beberapa secret dan mode keamanan masih berada di file konfigurasi proyek. | Pindahkan secret ke environment, sediakan installer/preflight, dan jangan tampilkan nilainya di UI. |

### Kesiapan jual di luar branding

Productization tidak cukup dengan mengganti logo. Sebelum pilot berbayar,
Finance juga harus memenuhi hal berikut:

- Temuan kritis audit transaksi, stok, HPP, void/refund, PH, payroll, RBAC, dan
  migration sudah ditutup atau dinyatakan sebagai batas produk secara jujur.
- RBAC seed, role matrix, menu, page code, endpoint, export, cron, dan worker
  mempunyai pemeriksaan konsistensi otomatis.
- Secret database/API/encryption tidak ikut Git atau paket customer; CSRF,
  secure cookie, error page production, dan environment production aktif.
- Installer meminta profil usaha, outlet pertama, akun owner, zona waktu,
  mata uang, storage, backup, printer, dan worker tanpa mengedit source code.
- Tersedia data demo opsional yang terpisah dari data customer dan dapat
  dihapus bersih.
- Backup, restore, migration, rollback, health check, dan smoke test dapat
  dijalankan berulang pada Windows dan Linux.
- Format tanggal, angka, mata uang, timezone, pajak/service, penomoran dokumen,
  serta aturan lokal tidak bergantung pada satu usaha.
- Semua error teknis memiliki correlation ID dan pesan user yang aman; stack
  trace/SQL tidak tampil kepada operator production.
- Dokumentasi onboarding, SOP support, batas paket, privasi telemetry, EULA,
  dan kepemilikan data tersedia sebelum penjualan pertama.

### Urutan implementasi productization yang disepakati

1. Buat katalog fitur dan dependency dalam dokumen/machine-readable registry;
   belum langsung menutup menu agar aplikasi aktif tidak terganggu.
2. Bangun profil usaha dan branding service terpusat beserta tema netral.
3. Ganti hardcode runtime per area dan tambahkan regression test untuk login,
   sidebar, struk, laporan, QR, halaman publik, serta printer agent.
4. Pasang `FeatureGate` dalam mode audit untuk merekam semua jalur penggunaan
   fitur sebelum enforcement dinyalakan.
5. Bangun License Hub MVP, signed entitlement, cache offline, dan activation
   file; setelah hasil audit bersih baru aktifkan enforcement paket.
6. Stabilkan installer/updater dan baru kemudian lindungi runtime inti dengan
   encoder pada artefak production.
7. Uji Starter, Operations, Control, upgrade, downgrade read-only, internet
   putus, ganti terminal, restore backup, dan rollback update pada pilot.

## 10. Roadmap Komersialisasi Setelah Fondasi Audit

Roadmap ini mulai berjalan setelah `_30` menyatakan gerbang teknisnya lulus,
atau menjalankan pekerjaan paralel yang hanya menyusun spesifikasi tanpa
mengubah jalur transaksi aplikasi. Tidak ada fase di bawah ini yang mengulang
perbaikan bug, RBAC, HPP, atau integrity yang menjadi milik `_30`.

### C0 — Handoff dan keputusan go/no-go

- Terima checklist handoff dari Fase A0–A5 pada `_30`.
- Pastikan release candidate, schema, backup/restore, regression, dan security
  evidence memiliki owner serta tanggal kedaluwarsa yang jelas.
- Catat risiko yang sengaja diterima sebagai batas produk; jangan menyamarkan
  temuan terbuka sebagai fitur paket.

**Gerbang:** product owner menyetujui bahwa fondasi cukup aman untuk masuk
productization. Jika belum, pekerjaan kembali ke `_30`.

### C1 — Paket, harga, kontrak, dan katalog fitur

- Finalkan Starter, Operations, Control, Enterprise, serta add-on.
- Tetapkan feature code, dependency, limit outlet/terminal, maintenance,
  biaya implementasi, support boundary, dan aturan upgrade/downgrade.
- Finalkan EULA, kebijakan data, SLA, kebijakan device, dan penawaran pilot
  bersama pihak yang berwenang.

**Gerbang:** satu katalog machine-readable menjadi sumber aplikasi,
dokumentasi, License Hub, dan penawaran; tidak ada paket lewat source fork.

### C2 — Productization customer dan onboarding

**Batch 244 — 2026-09-10, clean distribution:**

- [x] Menu Book customer tidak fallback ke desain Namua bila file legacy tidak dibundel; opsi legacy tidak ditawarkan pada instalasi bersih. Staging tetap memakai desain yang tersedia, tanpa mengganti pengaturan DB.
- [x] Logo/favikon netral untuk paket tanpa logo lama; login/sidebar/slip/label tidak menunjuk gambar bawaan yang hilang. Printer tanpa logo tidak dikirimi SVG atau URL gambar lama yang tidak ada.
- [x] Materi menu/produk/roastery dan logo Namua dipertahankan di sumber development, tetapi tidak masuk profil `CUSTOMER_CLEAN`. Dummy bisnis tidak disertakan (`REFERENCE_ONLY`).
- [ ] Penerimaan visual end-to-end pada instance baru hasil build Control; bukan klaim semua UAT/printer fisik sudah selesai.

- Bangun profil usaha, branding, locale, dokumen, pajak/service, integrasi,
  privacy, health, dan onboarding customer.
- Ganti hardcode identitas lama melalui urutan outlet -> profil usaha -> default
  produk netral.
- Pisahkan preset demo dari data customer; secret tetap berada di deployment
  boundary, bukan UI setting biasa.

**Gerbang:** instalasi dapat dikonfigurasi dan di-branding tanpa edit source,
serta seluruh preview/login/sidebar/dokumen/struk/QR konsisten.

**Progress Batch 219–220:** `System > Profil Usaha & Tampilan` sekarang
menjadi setup admin tiga langkah untuk nama usaha, kontak/lokalitas, serta
logo/footer dokumen. Perubahan profil mengalir sebagai fallback ke login,
sidebar, footer, QR ulasan, label aset, cetak kontrak, dan preview/struk POS;
urutan tetap `outlet atau layout cetak -> profil usaha -> default netral`.
Konfigurasi printer/outlet yang sudah ada tidak ditulis ulang. Landing page
mengambil nama awal dari profil namun konten/SEO/URL-nya tetap diatur eksplisit
melalui UI Landing Page. Menu Book lama adalah template konten Namua (produk,
gambar, sosial, dan cerita), sehingga tidak diganti otomatis oleh nama profil.
Ia harus dipisah/digeneralisasi sebagai template customer sebelum C2 ditutup.

**Progress Batch 224–225 (2026-09-08):** Profil Usaha menyediakan pilihan
template Menu Book customer, desain Namua lama, atau tidak dipublikasikan.
Pilihan disimpan bersama profil dalam transaksi/audit; semua route publik
Menu Book mengikuti pilihan itu. Katalog customer memakai produk aktif yang
dipilih pada Landing Page, tanpa query order/stok/HPP. Desain lama dipertahankan
dan pilihan staging tidak diubah otomatis. Delapan folder upload dapat dicek
langsung dari UI menggunakan akun PHP-FPM. Template marketing menyeluruh,
preset install, pajak/service, integrasi, health/privacy masih terbuka.
Panduan awal: `docs/customer_setup_and_release_guide.md`.

### C3 — Artefak customer, installer, dan delivery update

**Batch 245 — 2026-09-12, sinkronisasi kontrak Control terbaru:**

- [x] Manifest alpha.12 mengumumkan profil default `CUSTOMER_CLEAN` / Customer bersih, tanpa dummy; parser aktual Control menerima profil dan hash rules yang sama.
- [x] Adapter `NAMUA_PRODUCT_BUILD_V1` menghasilkan TAR/report unsigned, menjalankan gate kode dan instalasi/backup–restore di MariaDB disposable tanpa mengakses DB development.
- [x] Installer/verifier Finance mendukung outer manifest Control schema 1, menjaga kompatibilitas sidecar v2 lama dan memisahkan hash app-manifest dari inner release-manifest.
- [x] Entry validator TAR tepercaya untuk Control tersedia: `tools/build/verify_control_build.php`; handoff/prasyarat ada di [kontrak bersama](customer_clean_release_contract.md).
- [x] Inspeksi read-only Control: empat field distribution pada plan/claim dan nama tiga artifact Finance sudah disiapkan.
- [x] Validasi Finance: 115 entry release gate PASS; drill delapan gate adapter sebagai namua-build dan verifier result/report Control aktual PASS (library-only, fixture terisolasi). 296 tabel, 285 tabel non-reference kosong, 16 migration dan restore 296 checksum cocok; bukan publish/UAT customer aktual.
- [ ] Thread Control: pasang validator tepercaya dan selesaikan guard `FINANCE_TRUSTED_VALIDATOR_REQUIRED` / `finance_validator_unavailable`; Finance tidak mengubah kode Control.
- [ ] Scan/preview/impor source alpha.12, build/sign/register/publish dan UAT customer lewat UI Control aktual. Hasil fixture tidak menutup checklist ini.

Histori sebelumnya (bukan cutoff adapter baru):

**Batch 244 — 2026-09-10, Finance saja; Control dikerjakan thread terpisah:**

- [x] Builder default `CUSTOMER_CLEAN` v1: allowlist file kode/tool/docs, checksum aset generik dan 17 SQL (schema + 16 migration terkelola). Tidak membaca/dump/membersihkan DB sumber.
- [x] Audit isi TAR menghitung file dan mengikat hasil ke artifact SHA, inner manifest SHA dan profile SHA; boolean `contains_customer_data=false` untuk paket baru hanya setelah audit lulus.
- [x] Legacy signed tetap bisa diperiksa, tetapi `customer_clean_eligible=false`; paket alpha.10 lama tidak boleh dianggap produk bersih atau ditimpa. Versi sumber berikutnya `0.1.0-alpha.11`.
- [x] Installer clean-install menolak paket tanpa profil sebelum koneksi DB. Upgrade tetap tidak menjalankan seed instalasi pertama; metadata katalog legacy dipertahankan tetapi skrip repair lama tidak dibundel/dijalankan.
- [x] Client Finance mengikat profil/seed/hash dari install-plan Control dengan hasil verifikasi artifact. Tiga artifact dan format signature lama tetap dipakai.
- [x] Tes paket tiruan mencakup determinisme, file titipan, checksum seed/aset, kelengkapan runtime, fallback Menu Book/logo, report palsu, legacy dan mismatch delivery. Tidak menggunakan DB transaksi.
- [ ] Thread Control: UI pilihan profil, proses build, gate `CUSTOMER_CONTENT_AUDIT`, penyimpanan metadata dan field claim sesuai [kontrak bersama](customer_clean_release_contract.md).
- [ ] Build/sign/register versi baru dari cutoff bersih dan latihan clean-install/upgrade lewat Control. Unit test bukan evidence install customer; jangan mengubah status release lama otomatis.

Kontrak dan batas pekerjaan ada pada `docs/customer_clean_release_contract.md`; log eksekusi Batch 244. Tidak ada file/DB Control yang diubah oleh batch Finance ini.

- Gunakan release foundation teknis dari `_30` sebagai input, bukan workspace
  developer atau dump operasional.
- Bangun generator install profile, seed profile, clean install, migration
  bundle, backup, health check, rollback, checksum, signature, dan SBOM.
- Sediakan installer Windows/Linux, service setup, support bundle, serta channel
  `pilot`, `stable`, dan `critical-fix`.

**Gerbang:** clean install, upgrade, restore, dan rollback dapat diulang; paket
tidak memuat backup, upload, log, secret, Git, atau data customer lain.

**Progress Batch 219:** `tools/release/control_center_release_preflight.php`
memeriksa sumber Finance secara read-only dan menolak publish bila worktree
kotor. `tools/install/finance_install_plan.php` menghasilkan urutan install
tanpa menyentuh database. Keduanya adalah kontrak C3, bukan installer customer
atau artifact resmi.

**Progress Batch 225:** paket wajib mengecualikan `assets/uploads/` termasuk
file yang tidak sengaja masuk Git; delapan folder mempunyai checker bersama
web/CLI dan prepare yang menolak root. Rencana clean-install tidak disamakan
dengan upgrade; tidak ada seed atau bootstrap owner pada upgrade. Hook
Composer memakai PHP portabel dan aman bila paket development tidak ada.
Plan tetap bukan installer yang menjalankan deployment. Source dirty masih
memblokir artefak resmi. Pemeriksaan read-only Control juga menemukan
`tools/provision_release_signing_key.php` masih membatasi produk ke
`NAMUA_PENATAUSAHAAN`; provisioning/delivery **artefak Finance** harus diselaraskan
bersama pengelola Control, bukan memakai key Penatausahaan atau menganggap
format provenance Finance otomatis sama dengan format Control.

**Delta Batch 228 (2026-09-09):** keputusan cutoff lokal sudah dilaksanakan
pada `b10fa37`. Control kini mendukung provisioning key **release Finance**
terpisah serta CLI verifikasi read-only dengan trust Finance; tidak memakai
importer migration PHP Penatausahaan. Adapter membawa checksum seluruh
katalog SQL/baseline dan status APK secara eksplisit. Panduan teknis ada pada
`docs/control_release_delivery.md`. Registrasi DRAFT ke database Control,
publish/install-plan, dan installer nyata masih terbuka; jangan menjalankan
SQL di server aplikasi lama hanya karena file tersebut masuk paket.

### C4 — License Hub, entitlement, terminal, dan APK

- Bangun Product Control Center multi-produk dan katalog artefak.
- Terbitkan signed entitlement, cache offline, grace period, activation,
  revoke, transfer, dan audit device.
- Integrasikan satu `FeatureGate` dengan menu, endpoint, API, export, worker,
  cron, dan APK; RBAC tetap berjalan setelah entitlement.
- Uji batas outlet/terminal, penggantian device, internet putus, maintenance
  berakhir, dan downgrade read-only.

**Gerbang:** database lokal tidak dapat menaikkan paket dan gangguan Control
Center tidak mematikan kasir atau akses ekspor.

**Progress Batch 219:** Finance memiliki tabel cache signed, registry device,
audit runtime, halaman status, dan `FeatureGate` yang defaultnya
`AUDIT_ONLY`. Tidak ada UI/tabel lokal yang dapat menaikkan paket. Verifikasi
tanda tangan, aktivasi, lease/grace, batas perangkat, dan enforcement belum
dibuat agar staging tidak terkunci secara mendadak.

**Progress Batch 226:** reader Finance kini memverifikasi envelope nyata
Control (`schema=1`, Ed25519, konteks `NAMUA_LICENSE_V1` + hash payload asli),
key-id/fingerprint, produk, instance, installation, public-key binding,
lease/grace, dan tipe entitlement. Hak fitur diambil dari payload bertanda
tangan, tidak dari baris fitur yang dapat diedit lokal. Trust/identity harus
deployment-owned di luar webroot. Tes fixture kontrak dan negatif lulus;
belum ada aktivasi customer nyata. FeatureGate tetap AUDIT_ONLY dan flag SQL
saja tidak bisa mengaktifkan enforcement. Windows ACL, activation/polling/cache
writer, proteksi replay/rollback lease lintas restart, native guard, pairing,
dan enforcement lintas endpoint tetap terbuka. Sesuai arahan terbaru,
komersialisasi APK boleh dilanjutkan, sementara bug operasional/build/UAT
masih ditunda owner.

**Progress Batch 240 (menggantikan sisa implementasi di atas):**

- [x] Agen menyimpan identitas/kunci sebelum permintaan kode sekali pakai.
- [x] Poll signed cocok kontrak Control; TLS diverifikasi, redirect ditolak,
  timeout/ukuran respons dibatasi. Tidak membawa private key penerbit ke Finance.
- [x] Cache atomik dengan watermark bersama dokumen, offline lease/grace,
  replay lintas restart, revoke dan clock rollback; 54 tes fixture/model/izin PASS.
- [x] Model memilih cache deployment tanpa fallback SQL jika rusak; halaman
  lisensi membaca status asli tanpa secret. Tidak perlu SQL baru.
- [x] Panduan `customer_setup_and_release_guide.md` bagian 4 dan template timer
  disiapkan, tidak mengaktifkan scheduler staging atau membuat customer.
- [ ] Acceptance HTTPS terhadap Control/worker nyata dan jalur pemulihan
  aktivasi ambigu tanpa rotasi identitas buta.
- [ ] Native guard/Windows, anti-clone yang lebih kuat daripada machine-id,
  pairing/limit, enforcement endpoint/worker/API/APK dan UAT tetap terbuka.

### C5 — Pilot berbayar dan operasi penjualan

- [x] Source pusat panduan `/guide` (Batch 261): pembagian UI/admin server,
  langkah operasional, cron dan pemulihan; detail teknis hanya pada `_30`.
- [x] Pengguna mengonfirmasi apply SQL sidebar/izin 15c; dicatat 2026-09-15
  11:24 WIB sebagai **USER_REPORTED_APPLIED**, bukan auto-apply Telegram.
- [ ] Verifikasi sidebar/izin nyata dan uji role, desktop/mobile/cetak;
  konfirmasi apply bukan bukti postcheck/UAT lulus.
- [ ] Tinjau dan sertakan kode, asset serta migrasi panduan pada artifact
  customer, lalu buktikan clean-install/upgrade tanpa data/secret staging.
- [ ] Walkthrough operator non-programmer, admin server dan pemilik; catat
  feedback, keterbatasan paket serta bukti persetujuan serah-terima.

- Siapkan website/penawaran, kontrak, invoice lisensi, dokumentasi, training,
  support playbook, incident runbook, dan channel support.
- Terbitkan panduan aplikasi yang terikat versi release: panduan pengguna per
  peran/modul, admin aplikasi, admin server untuk install/backup/update/restore,
  serta troubleshooting POS Mobile/APK, printer, WhatsApp, dan Telegram.
- Validasi panduan melalui walkthrough pengguna non-programmer; langkah yang
  masih membutuhkan terminal harus dipisahkan jelas dari pengaturan melalui UI.
- Uji minimal instalasi Starter dan Operations/Control pada customer non-Namua.
- Catat waktu instalasi, biaya support, feedback, renewal, dan insiden tanpa
  mengambil data transaksi customer sebagai telemetry default.

**Gerbang:** pilot dapat install, operate, backup, update, rollback, mengganti
device, dan menutup periode tanpa edit source; seluruh P0/P1 yang menjadi
syarat produk sudah ditutup atau diterima tertulis.

## 11. Urutan Pengerjaan yang Harus Kita Jalankan

Urutan ini dimulai setelah `_30` menyerahkan fondasi teknis yang lulus. Saat
audit masih terbuka, pekerjaan `_28` hanya boleh berupa spesifikasi, katalog,
kontrak, dan desain yang tidak mengubah transaksi aplikasi.

1. **Handoff fondasi:** terima bukti Fase A0–A5 dari `_30`; jika belum lulus,
   kembalikan pekerjaan ke audit, bukan membuat bypass komersial.
2. **Definisi penawaran:** finalkan paket, add-on, harga, maintenance, EULA,
   SLA, data policy, dan SOP support/device.
3. **Productization:** bangun branding, profil usaha, onboarding, locale,
   dokumen, dan integrasi customer tanpa hardcode identitas lama.
4. **Delivery release:** buat customer installer, seed/migration profile,
   signed artifact, backup/restore, health check, update, dan rollback.
5. **Control plane:** bangun Product Control Center, katalog artefak, customer,
   entitlement, License Hub, aktivasi device, FeatureGate, dan audit.
6. **Pilot:** uji Starter dan Operations/Control pada customer non-Namua,
   termasuk device replacement, internet putus, maintenance berakhir, update,
   restore, dan ekspor data.

## 12. Kriteria Siap Jual Versi Pertama

Produk dianggap siap dijual ketika seluruh poin berikut telah terbukti pada
instalasi pilot:

- artefak customer tidak membawa backup, upload, log, secret, config device,
  identitas customer lama, Git, atau alat repair internal;
- generator menunjukkan dry-run file/data, provenance commit, dependency
  migration/seed, checksum, signature, dan hasil seluruh quality gate;
- database kosong terbentuk dari migration serta seed kanonis tanpa dump
  produksi, akun/password default, atau data operasional instalasi lain;
- customer bisa memasang aplikasi tanpa akses Git atau source code;
- nama usaha, logo, tema, locale, dokumen, URL publik, outlet, rekening, dan
  aturan pajak/service dapat disiapkan melalui onboarding tanpa edit source;
- runtime/dependency, versi aplikasi, versi schema, checksum migration, dan
  signature release dapat dibuktikan;
- lisensi membatasi outlet dan terminal sesuai paket/kontrak, termasuk APK;
- owner dapat melihat dan mengganti device dengan audit trail;
- RBAC dan FeatureGate sama-sama menolak akses yang tidak berhak;
- Printer Agent, WhatsApp, cron, worker, upload, dan endpoint publik telah
  melalui security/abuse test serta mempunyai health/retention;
- POS tetap beroperasi saat koneksi License Hub terputus dalam grace period;
- masa maintenance habis tidak mengunci transaksi maupun data;
- update memiliki preflight, backup, migration, health check, dan rollback;
- automated test transaksi, rollback, RBAC, migration, restore, dan browser
  smoke lulus pada release candidate yang sama dengan paket customer;
- support dapat membaca status versi/aktivasi tanpa menerima data transaksi
  customer secara default;
- Product Control Center dapat menghasilkan ulang artefak customer yang sama
  dari source immutable dan menambahkan produk baru melalui manifest/adapter;
- kontrak lisensi, SOP instalasi, SOP support, dan kebijakan privasi siap
  dipakai.
- panduan pengguna, admin aplikasi, admin server, serta troubleshooting telah
  diuji pada artefak/version release yang sama dengan pilot.

## 13. Hal yang Sebaiknya Tidak Dilakukan

- Jangan menjanjikan source code "tidak mungkin disalin".
- Jangan menjadikan MAC address, IP, atau cookie browser sebagai satu-satunya
  identitas terminal.
- Jangan mematikan kasir segera ketika internet putus atau maintenance habis.
- Jangan membatasi fitur hanya dari tampilan menu.
- Jangan melakukan update otomatis tanpa backup, health check, dan audit.
- Jangan memasukkan seluruh customer ke arsitektur multi-tenant sebelum model
  bisnis cloud benar-benar dibutuhkan.
- Jangan mengirim data transaksi customer ke server lisensi demi pemeriksaan
  aktivasi biasa.
- Jangan menyimpan repository source sebagai blob biasa di database Product
  Control Center; gunakan Git privat dan artifact storage dengan akses terpisah.
- Jangan membuat dump database aktif sebagai seed customer baru atau
  mencampurkan clean install, upgrade, dan repair historis dalam satu paket.

## 14. Keputusan yang Sudah Ditetapkan Sebelum Mulai Coding

1. Penjualan awal adalah on-premise per organisasi; managed server dapat
   ditawarkan sebagai layanan instalasi/support, bukan perubahan model produk.
2. Lisensi dihitung per organisasi, outlet, terminal POS, dan add-on.
3. Starter mencakup 1 outlet dan 1 terminal aktif; Operations menjadi paket
   awal dengan sampai 3 terminal. Semua memakai grace period offline 30 hari
   serta jalur penggantian device berjejak.
4. Paket awal adalah Starter POS, Operations, Control, dan Enterprise.
5. Maintenance standar 12 bulan; aplikasi perpetual tetap berjalan setelahnya,
   sedangkan update/support baru memerlukan perpanjangan.
6. Kontrak lisensi, EULA, dan SOP support harus ditinjau pihak hukum sebelum
   penjualan pertama.
7. Pekerjaan bug, keamanan/RBAC, integritas transaksi, automated test, schema,
   dan release foundation dimulai serta dinilai di `_30`. Roadmap ini mulai
   dari handoff/commercial gate; lisensi dasar tidak dibuat untuk menutupi
   fondasi yang belum lulus. Obfuscation dan APK locking tidak boleh mendahului
   release engineering.
8. Aplikasi pusat final adalah Product Control Center multi-produk. Finance
   menjadi produk pertama; produk berikutnya masuk lewat katalog, adapter,
   manifest build/install, dan registry fitur tanpa membuat control plane baru.
9. Satu artefak kode digunakan untuk seluruh paket pada versi yang sama.
   Perbedaan customer dihasilkan melalui install profile, onboarding, dan
   signed entitlement, bukan modifikasi manual source.

## 15. Ringkasan Arah Produk

Untuk versi pertama yang akan dijual, arah finalnya adalah:

- selesaikan gerbang audit, clean product build, automated test, pengaturan
  sistem, dan installer sebelum mengaktifkan enforcement lisensi;
- jual **perpetual on-premise per organisasi**; Starter membawa 1 outlet dan
  1 terminal POS, sedangkan Operations menjadi pilihan awal untuk 3 terminal;
- customer memperoleh versi saat pembelian dan 12 bulan update/support;
- update berikutnya setelah masa itu melalui renewal maintenance, tanpa
  mematikan aplikasi yang sudah dibeli;
- source repository tidak diserahkan;
- kelola source di Git privat dan seluruh build, artefak, generator instalasi,
  lisensi, update, monitoring, approval, serta audit melalui Product Control
  Center multi-produk;
- bentuk database customer dari migration dan seed kanonis; exclude dump,
  backup, log, upload, data bisnis, secret, dan konfigurasi device;
- gunakan lisensi offline-signed ditambah aktivasi terminal online/offline;
- jadikan APK POS sebagai add-on per terminal;
- pertahankan satu codebase dan aktifkan paket melalui entitlement yang
  ditandatangani, bukan melalui source code berbeda atau toggle lokal;
- selesaikan profil usaha/branding terpusat dan penghapusan hardcode identitas
  sebelum instalasi pilot customer;
- mulai feature tier dari paket yang tidak terlalu banyak agar support tetap
  sederhana, lalu perluas setelah data penggunaan nyata terkumpul.

Dengan urutan ini, Finance dapat dijual secara profesional tanpa mengganggu
fokus utama saat ini: kestabilan transaksi, stok, HPP, audit, dan pengalaman
operator.
