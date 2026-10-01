# Log Eksekusi Codex Finance

## 23 September 2026 — Koreksi stok PDF pengajuan WA

- Prioritas: PDF WA salah menandai seluruh baris bukan bahan baku, berbeda dari unduhan. Review mandiri menemukan jalur WA tidak memanggil reader stok; konteks divisi dan alias catatan juga hilang.
- File: `Procurement.php`, `Module_notification_model.php`, tes parity PDF/antrean, manifest quality gate, panduan/roadmap. Perubahan yang sebelumnya belum commit tetap dipertahankan.
- Perubahan: helper persiapan baris bersama untuk unduhan dan WA, stok kanonis read-only dan catatan sama. Revisi dedup PDF memungkinkan kirim ulang versi terkoreksi, tanpa menghapus bukti kiriman lama atau menonaktifkan dedup klik ulang.
- Validasi: parity PDF 52 + stock review 52 PASS, Chrome PDF nyata + pdftotext, current stock 91 cumulative PASS, UI 74 DOM + 15 JS PASS, gate contract 28 PASS; lint/diff check. Tidak mengubah SQL/data aktif, pengaturan bot, atau mengirim WA nyata.
- Risiko/batch berikut: UAT PDF yang diterima di grup; bandingkan pengajuan sama dan perhatikan waktu baca stok. File lama tidak berubah otomatis. Rincian pada [laporan notifikasi](2026-09-23_module_notifications.md).
- Hasil akhir antrean: **74 PASS** MariaDB disposable, termasuk versi PDF baru setelah PDF lama/dedup/riwayat tetap utuh. Tidak ada kiriman nyata.

## 23 September 2026 — Daily Sales PDF via WA

- Permintaan: tambah pilihan Daily Sales pada pengaturan dan tombol kirim di laporan; pertahankan revisi modul WA pengguna. Implementasi/review mandiri; `wa/settings.php`, `Whatsapp.php`, engine dan config aktif tidak berubah.
- File: `Pos.php`, routes/feature_access, `Module_notification*`, `Daily_sales_pdf.php`, view Daily Sales/cetak dan dua penambahan kecil partial notifikasi; allowlist/manifest; tes dan panduan.
- Perubahan: PDF snapshot sesuai tanggal/outlet melalui renderer Chrome existing dependency; antrean/group recipient existing, dedup, gate paket/RBAC/CSRF, lampiran privat. Tidak mengubah perhitungan laporan atau data bisnis.
- Validasi: 71 MariaDB disposable PASS, 74 UI + 15 JS existing PASS; 19 Chrome UI PASS; Daily Sales endpoint/PDF/JS dan regresi WA/urut laporan PASS, lint/diff PASS. PDF asli diuji header, A4 landscape dan isi teks. Tidak ada pengiriman nyata.
- Risiko sisa: izin renderer/worker dan penerimaan PDF nyata perlu UAT; gate global feature boundary terhambat aksi Procurement existing yang belum dipetakan. SQL lampiran `23b` existing belum terdaftar dalam katalog customer; butuh batch migrasi/review Control terpisah sebelum packaging resmi.
- SQL baru: tidak ada; SQL aktif tidak dieksekusi. Belum commit/push/publish. Detail/bukti/checklist pada [laporan notifikasi](2026-09-23_module_notifications.md); langkah customer pada [panduan](module_notifications.md).

## 23 September 2026, 20:47 WIB — Tab pengaturan WA dan grup notifikasi

- Prioritas: rapikan pengaturan; pisahkan status balasan chat grup dari penerimaan notifikasi modul. Implementasi/review mandiri, tanpa subagent.
- File: view `wa/settings`, `notifications/settings`; model/library `Module_notification`; smoke UI/DB dan browser `wa_settings_browser.cjs`; panduan dan kedua roadmap.
- Perubahan: empat tab dengan restore pilihan, checklist banyak grup per modul; grup nonaktif tetap tersedia/dikirimi bila dipilih. Grup JID invalid terlihat tetapi disabled. Tidak mengubah flag balasan; target Telegram tetap harus aktif. Validasi server, CSRF, RBAC, lisensi, dedup/cutoff dan pengunci WA pribadi tetap dipertahankan.
- Validasi: PHP lint/diff check PASS; 62 MariaDB disposable, 74 HTML/DOM + 15 JS, 19 Chrome UI PASS; seluruh regresi keamanan WA yang dipilih PASS. Review memastikan perubahan flag inbound tidak mereset cutoff dan pengubahan JID tetap membatalkan antrean lama.
- Global parallel: 133/136 PASS, tiga gate gagal pada konfigurasi `database.php`/`.user.ini` yang diminta dipertahankan serta literal username fixture yang sudah ada sebelum revisi. Tidak mengubah config maupun melemahkan scanner. Detail di [laporan](2026-09-23_module_notifications.md).
- Tidak ada SQL baru, perubahan DB aktif, pesan nyata, cron, credential, commit atau push. Risiko/batch berikut: UAT grup operator; review konfigurasi untuk release terpisah, bukan diubah dalam tugas UI ini.

## 23 September 2026 — Notifikasi modul WA / Telegram

- Prioritas: sakelar/penerima per modul; notifikasi order masuk dan kirim manual pengajuan divisi. Implementasi fixer tunggal sesuai pola kerja terbaru, tanpa subagent diskusi berulang.
- File utama: `Module_notification`, `Module_notification_model`, controllers `Whatsapp`, `Telegram`, `Procurement`, routes/feature_access, view settings/tombol, `module-notifications.js`, katalog/profil/manifest dan SQL `2026-09-23a`.
- Perubahan: polling database order bersama pada worker bot existing; queue persisten, unique key, lock worker, pengiriman ambigu tidak retry otomatis; RBAC/CSRF/scope dan entitlement tetap aktif. Tidak mengubah aturan pembayaran/stok. WA personal sengaja tetap terkunci oleh kebijakan existing.
- Validasi: 51 checks policy + MariaDB disposable PASS, 50 HTML/DOM + 15 JS client PASS; regresi Telegram 102, feature boundary 2156, WA settings CSRF 123, env-save 71, engine-control 160, send-test 91, CLI schedule 30, service-auth 17 PASS. Validasi katalog: 27 managed / 7 legacy. Quality gate akhir `parallel`: 136/136 PASS/exit 0; runtime/security/static full-release tetap terpisah.
- Review mandiri: perbaiki pengambilan nama item dengan join produk/bundle aktual; pertahankan link/suffix pada batas byte Telegram; pastikan tujuan yang berubah tidak menerima antrean lama, dan aktivasi ulang tidak membanjiri order historis.
- SQL: BELUM pada database aktif; hanya disposable, menunggu konfirmasi operator. Tidak mengubah config/credential/cron/layanan aktif, Control, customer core2 atau bridge. Tidak kirim pesan nyata; tidak commit/push.
- Regresi migrasi global MariaDB disposable juga PASS/exit 0 (baseline, upgrade, replay, adopsi dan penolakan drift/partial). Pengecekan read-only lokasi cron umum belum menemukan referensi langsung worker bot aplikasi; wrapper eksternal belum diverifikasi.
- Risiko/batch berikut: apply schema setelah persetujuan, pasang/verifikasi jadwal bot dan UAT grup uji operator; review/paket resmi oleh Control. Checklist dan panduan: [laporan notifikasi](2026-09-23_module_notifications.md).

## 20 September 2026 — Stok sekarang pengajuan divisi / PO / SR

- Prioritas: tampilkan saldo bahan baku sekarang pada form, list/detail dan PDF; warning controlling purchase dan PO/SR manual.
- Implementasi fixer tunggal: gunakan satu reader stok, pisahkan snapshot lama, pertahankan bukti verifikasi server; tutup race edit/verifikasi, kegagalan mapping yang sempat melewatkan cek dan timeout client.
- File: Procurement/Purchase controller, Procurement_model/stock-review library, feature/route map, view pengajuan/PO/SR/print, JS review/current-stock, allowlist/manifest dan regresi. Daftar rinci/tes/UAT ada di [laporan](2026-09-20_procurement_current_stock.md).
- SQL: tidak ada. Tidak mengubah DB aktif, stok, transaksi, konfigurasi, Control/customer atau credential; tidak commit/push/deploy.
- Review: tetap read-only untuk saldo; server menjaga verifikasi divisi, dialog manual PO/SR merupakan kontrol UI. Tidak mengklaim saldo fisik, reservasi atau E2E customer berdasarkan fixture.
- Berikut: pengguna UAT dengan peran divisi/purchase, lalu Control review cutoff/hash dan build resmi. Status gate/build akhir diperbarui di laporan modul.

## Batch 270 — 2026-09-18 — Guided setup customer, alpha.18/v7

- Prioritas: hilangkan langkah terminal berulang customer; audit alpha.17/v6 dan class ZIP/izin Control secara read-only. Fixer tunggal dengan review diff/tes, bukan diskusi subagent.
- File dan kontrak: entry point prepare.sh/prepare.php/LinuxPreparation, SetupService/SetupUi/setup.php/js/css, portable installer/package/DB/store, resolver config, core inventory, manifest/profil, docs, regression/Chrome/disposable tests. Rincian di [handoff](2026-09-18_guided_setup_control_handoff.md).
- Perubahan: root hanya persiapan berkonfirmasi; aplikasi/worker non-root terpisah. Tiga jadwal idempotent, liveness nyata; wizard probe DB sebelum SQL, ringkasan tanpa password, resume, input correction sebelum journal, setup closed. Izin sementara bukan deadline membeli; tidak ada domain lock.
- Temuan yang ditangani saat uji: arsip queue web tidak boleh chmod inode milik web oleh worker; kini copy atomik sebagai worker 0600. DB auth/unknown DB/akses dibedakan; konflik env tidak memilih DB diam-diam. Formatter/quality-gate profil diselaraskan, tanpa melonggarkan security scan.
- Validasi working-tree 19:31 WIB: **47 guided E2E** (ZIP Control builder aktual, Chrome/HTTPS/FPM/DB/cron nyata, Control HTTPS sintetis), **61 portable E2E**, 60 local config, 42 portable contract, 23 guided contract, 118 clean release, 72 agent, 60 bootstrap guard, 26 verifier, 27 delivery, 39 deployment-secret, 28 quality gate contract, 14 commercial foundation PASS. PHP lint, preflight/static PASS. Build awal 8 gate + verifier PASS: 1.170 file, 306 tabel/20 migrasi, 735 referensi, 0 customer/demo/secret, restore checksum cocok. Bukti cutoff akhir dicatat terpisah pada handoff.
- Cutoff implementasi **`9ca2d389b060e43c6ac61d77d6a94eda9b1d557a`**. Pengulangan checkout bersih: **47 guided E2E PASS/exit 0**, termasuk tambahan reload browser/status ID tetap; **8 gate + verifier PASS/exit 0** pada 19:48 WIB. 1.170 file, 306 tabel, 293 tabel nonreferensi kosong, 20 migrasi, 735 referensi, 0 customer/dummy/secret dan restore checksum cocok. Hash dump/inner manifest serta hash dependency tersedia di handoff; fixture tidak diterbitkan sebagai release. Regresi config/kontrak/agent/packaging, lint 26 PHP dan JS/shell juga diulang dan lulus.
- Dokumentasi penutup tidak mengubah kode/paket cutoff. Lingkungan uji dan checkout sementara saja yang dibersihkan; bisa dibuat ulang dari commit, tidak ada backup/upload/log/data aplikasi pengguna yang dihapus. Pembuatan akun layanan baru dan crontab sistem host tidak diuji dengan memutasi host bersama; scheduler nyata menggunakan akun existing dan spool terisolasi.
- Tidak ada push/publish/live activation, perubahan source/DB Control, data development/produksi, credential global, atau instalasi core2. Hanya fixture terisolasi yang dibuat/dibersihkan. Perubahan pengguna config.php dipertahankan.
- Risiko/batch berikut: Control perlu review v7/pin/issuer dan build pengiriman baru; Windows/Apache/IIS/host customer nyata dan walkthrough awam tetap perlu acceptance.

## Batch 269 — 2026-09-18 — Installer customer satu folder, alpha.17

- Prioritas: sederhanakan pemasangan tanpa aaPanel/environment PHP-FPM manual, web bukan root, konfigurasi/identitas lokal namun privat, aktivasi/kuota/integritas tetap. Tidak memindahkan master atau mengubah proses bisnis/SQL development.
- Pola: fixer tunggal, audit kode alpha.16/v5 dan kontrak Control read-only, review internal diff+tes; tidak mengklaim diskusi subagent. Dua komentar config.php yang dihapus pengguna tetap di working tree, tidak di-commit/ditimpa.
- Cutoff implementasi `1bff31e195d44a17bb3759dd5fd396c3375bceac` (47 file), alpha.17 / CUSTOMER_CLEAN v6 / FINANCE_SINGLE_FOLDER_V1. Daftar/hash/pin di [handoff](2026-09-18_single_folder_installer_control_handoff.md); tidak push/publish/live activation.
- File utama: CustomerPlatform/CustomerLocalConfig/DeploymentConfig, cache/verifier, index dan tiga resolver path; CustomerLayout/build/bridge/profile/ControlDelivery; LicenseStateStore/agent/protocol; tools/install/portable (UI, worker, DB, Control, evidence, Linux/Windows ACL/scheduler/web templates); dua guide, template config, manifest, tes baru dan regresi lama.
- Implementasi: build-only public layout; UI setup dengan secret Control+queue sodium; worker terpisah membuat key/ID lokal; signature/hash/izin/aktivasi sebelum SQL; DB kosong dengan baseline/20 migrasi PDO; journal READY/RUNNING/COMPLETE; receipt nyata sesudah HTTP login dan ack; setup locked; sync lease berbeda dari heartbeat. Runtime exception hanya storage logs/cache/sessions; agent private tidak dapat dibaca web. Legacy external/root-owned tidak dilonggarkan.
- Temuan tes yang diperbaiki: resolver runtime lama menolak storage lokal sehingga login error; exception v6 dibatasi tiga leaf. FCPATH public memerlukan path source eksplisit pada manifest/guide/printer agent. Pemisah path Windows dinormalisasi; tidak menjadikannya bukti Windows siap. Harness worker diberi waktu 240 detik terpisah dari helper TAR agar DB terisolasi tidak dipotong timeout.
- Validasi: **59 E2E Linux PASS dari checkout bersih `1bff31e1…`**, termasuk POST login owner, quota ditolak tanpa SQL, token pengganti dengan identitas sama dan resume DB; putaran awal 56 PASS. 42 parser/layout, 58 local config, 118 build/TAR/profil, 72 agent, 26 verifier, 60 legacy bootstrap, 27 delivery, 14 commercial foundation PASS. Preflight 1.779 kandidat/983 PHP/0 finding, PHPStan baseline 0, shell lint/whitespace dan Composer valid; OSV 145 package/0 advisory. Roadmap consistency 26 dan legacy secret config 39 PASS.
- Hasil penutup build dari checkout bersih `1bff31e1…`: **exit 0 / delapan gate + verifier independen PASS**, 1.164 file, 306 tabel, 293 nonreferensi kosong, 735 referensi, 20 migrasi, 0 customer/dummy/secret; backup/restore 306 tabel checksum cocok. Inner manifest fixture `b48f3874ca454328879b7753a113bfd93b9c33965c04d2b08099398704fbf0c4`; hash dump dan batas evidence pada handoff. Ini ISOLATED_INTEGRATION_FIXTURE_NOT_RELEASE, bukan persetujuan publish/aktivasi Control.
- Review: private key Control tidak ikut artefak; signed setup permit memerlukan penerbitan oleh Control (kontrak baru, tidak mengarang endpoint). Kuota rejection/replacement tidak mereset identitas. Izin sementara bukan batas pembelian; DDL tidak pasti berhenti untuk review, bukan diulang. Pengamanan PHP bukan janji anti-bypass absolut pada server yang dikuasai customer.
- Sisa/batch berikut: thread Control review v6/pin/permit/reissue/pengiriman satu folder lalu full gates/build/backup restore dan praktik UI dengan izin owner; Windows nyata, Apache/IIS dan scheduler target belum diuji. Tidak menyatakan semua requirement cross-platform siap. Lima SQL bisnis belum managed tetap terbuka.
- Hanya runtime/socket/DB/credential/artefak fixture yang dibuat khusus untuk tes dibersihkan; tidak menghapus backup/upload/log/data/credential aplikasi berjalan. Tidak ada perubahan source/database Control, layanan global, SQL baru ke staging/utama atau notifikasi Telegram manual.

## Batch 268 — 2026-09-18 — Konfigurasi lokal customer dan clean-install alpha.16

- Prioritas: customer mengisi DB/URL/key/runtime di `config/customer.json`, tanpa edit kode inti atau environment PHP-FPM. Scope konfigurasi, installer, packaging, pengamanan dan test; tidak mengubah bisnis/DB master/Control/layanan global.
- Pola kerja: fixer tunggal, membaca status repo dan kontrak alpha.15; perubahan user dua komentar di config.php dipertahankan. Review diarahkan pada konflik sumber DB, exception inventory exact-path, signature/context dan recovery DDL.
- Commit implementasi: `018c7173d68759c700ecfb324dc5f4946b0283bb`, 30 file; versi alpha.16, profil CUSTOMER_CLEAN v5. Tidak push/publish atau menimpa alpha.15.
- File utama: CustomerLocalConfig, DeploymentConfig, database.php, index.php, Control_license_cache/License_runtime_model, heartbeat; CustomerDatabase, FinanceInstance, LinuxWebProfile, PrivateDeployment, clean_install_database, migration_runner, ControlDelivery; app-manifest/profile/policy, .gitignore/.htaccess/config template, README/guide, empat file tes. Daftar lengkap `git show --stat 018c7173`.
- Perubahan: local JSON lengkap/fail-closed; legacy env > external dipertahankan tanpa local; konflik lintas sumber ditolak; MYSQL defaults eksklusif untuk koneksi lokal. File credential sementara root-private dihapus setelah pemakaian, journal STARTED/COMPLETE tetap. Nginx/Apache blokir config, runtime di luar webroot, public signed license context autodiscovery; agent private key tidak dipindah.
- Validasi: 58 config checks; 41 end-to-end actual nginx/FPM tanpa env[] khusus + MariaDB disposable + login pertama/health; 141 release/delivery; 60 bootstrap guard; 26 verifier; 72 agent; 85 heartbeat; 39 legacy config; 15 deployment instance; 16 Linux web profile; 32 empty-DB boundaries. Preflight 968 PHP/0 finding, PHPStan baseline 0, Composer valid (deprecation versi sistem).
- Build awal dan pengulangan dari checkout bersih cutoff `018c7173…`: delapan gate + independent validator PASS, 1.142 file, 306 tabel, 293 nonreferensi kosong, 735 referensi, 20 migrasi, 0 customer/dummy/secret, restore 306 tabel checksum cocok. End-to-end 41/41 juga PASS ulang dari checkout tersebut. Hash evidence final di handoff; checkout uji terpisah dibersihkan setelah pengujian.
- Temuan saat uji: drain pipe mencegah client SQL besar macet saat kegagalan; permission log dibuat 0600 independen dari umask. Dua percobaan harness datadir melewati timeout helper TAR 30 detik saat I/O paralel; timeout initializer fixture diubah 180 detik, bukan mengubah timeout/security production.
- Review: exception hanya config/customer.json yang valid; file config lain/tamper core ditolak. DB nonempty/wrong password/DDL parsial/SQL replay diuji; key/trust/signature/kuota/fingerprint tetap. Guide/readme masuk paket dan handoff [tersedia](2026-09-18_customer_local_config_control_handoff.md).
- Risiko sisa: Apache belum tersedia untuk HTTP test; lima SQL development unacknowledged membuat suite A5 raw-source gagal dan tetap di luar paket; Control harus review pin v5/job local config dan build normal sebelum praktik/publish. Bukan klaim semua modul bisnis siap jual.
- Pembersihan: hanya runtime, credential uji, data MariaDB dan artefak yang dibuat khusus oleh fixture disposable; tidak ada backup/upload/log development yang dihapus. Tidak ada SQL baru ke staging/master. Berikutnya milik thread Control, tidak dikerjakan di sini.

## Batch 1 — Containment authorization endpoint generic

- Waktu: 2026-09-02 06:58 WIB.
- Prioritas: P0 security/RBAC — endpoint generic `Master` dan `Master_relation` dapat menjadi jalur bypass bila hanya mengandalkan menu/sidebar.
- Arah auditor: prioritaskan deny-by-default server-side; batasi batch pada dua controller, gunakan page-code registry kanonis, bedakan action `view/create/edit/delete`, dan pastikan guard terjadi sebelum query atau mutasi.
- Implementasi fixer: menambahkan mapping permission fail-closed 403 dan guard pada seluruh endpoint publik terkait; fallback permission workspace extra dihapus; business logic, route, schema, dan file sensitif tidak diubah.
- File berubah:
  - `application/controllers/Master.php`
  - `application/controllers/Master_relation.php`
- Validasi:
  - `php -l` kedua controller: lulus.
  - `git diff --check`: lulus.
  - Audit cakupan endpoint publik: seluruh endpoint selain constructor memiliki guard; `att_holiday_generate_year()` telah memiliki guard eksplisit.
  - Query read-only `sys_page`: `product.availability` dan `master.product_extra.workspace.index` aktif; tidak ada `product.monitoring.availability.index`.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
- Review auditor: PASS. Auditor mengonfirmasi mapping `product.availability` benar, seluruh endpoint tercakup, guard berada sebelum query/mutasi, dan tidak ada patch tambahan.
- Risiko sisa: endpoint/controller lain di luar dua controller ini belum tercakup; izin role/scope fail-open, mobile policy, konfigurasi produksi, dan integritas data masih terbuka. Belum ada probe HTTP dengan akun uji least-privilege.
- Batch berikutnya: audit dan perbaiki bug terisolasi `Role_model` pada penghapusan permission role, dengan transaksi dan verifikasi aman tanpa mengubah data staging.

## Batch 2A — Atomic role deletion

- Waktu: 2026-09-02 07:11 WIB.
- Prioritas: P0 RBAC/integritas — `Role_model::delete()` memakai kolom `id` untuk menghapus permission, tidak atomik, dan tidak memvalidasi postcondition.
- Arah auditor: ganti filter ke `role_id`; gunakan transaksi; tolak role tidak ditemukan, `SUPERADMIN`, dan role yang masih dipakai user; lakukan assertion sebelum dan sesudah commit.
- Implementasi fixer: menambahkan locking `SELECT ... FOR UPDATE`, dependency check berbasis result object, delete terverifikasi, assertion pre-commit, commit, dan postcondition read-only. API boolean serta caller `Roles::delete()` dipertahankan.
- File berubah:
  - `application/models/Role_model.php`
- Validasi:
  - `php -l application/models/Role_model.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Query read-only snapshot role/user/permission: 12 role terbaca tanpa perubahan.
  - Query read-only FK: `auth_user_role.role_id` dan `auth_role_permission.role_id` mereferensikan `auth_role.id`.
  - Konfigurasi staging read-only: dbprefix kosong; `Roles::delete()` satu-satunya caller dan tidak berada dalam outer transaction.
- Diskusi auditor/fixer: auditor pertama menemukan false-success karena `count_all_results()` mengembalikan 0 saat query gagal dan meminta locking/assertion pre-commit. Fixer merevisi badan metode tanpa menyentuh schema/data; auditor final memeriksa API transaksi CI3 dan menyatakan PASS.
- Review auditor: PASS. Implementasi memenuhi P0-05 pada deployment staging yang diverifikasi.
- Risiko sisa: belum dilakukan live delete fixture karena tidak diperlukan dan perubahan data staging sengaja dihindari; invariant FK dan dbprefix kosong harus dijaga pada paket customer. Rebuild role baseline, multi-role scope, dan negative permission test lintas endpoint masih terbuka.
- Batch berikutnya: auditor memilih prioritas P0 RBAC berikutnya, dengan kandidat utama fail-closed scope pada `Auth_model`/`MY_Controller` atau policy aksi `Pos_mobile`.

## Batch 3 — Fail-closed Purchase rebuild/reclassify

- Waktu: 2026-09-02 07:18 WIB.
- Prioritas: P0/P1 RBAC containment — utilitas `rebuild` dan `reclassify` masih menerima fallback izin `purchase.order.index`, sehingga writer Purchase umum dapat menjalankan tool yang memproses ulang dampak receipt/payment atau snapshot inventory.
- Diskusi auditor/fixer: auditor memilih batch kecil tanpa perubahan schema/data; fixer menghapus seluruh fallback dan menerapkan page code khusus dengan action `view` untuk halaman dan `edit` untuk endpoint run. Pemanggilan profil `finance_fixer` pada CLI tidak didukung akun, sehingga instruksi yang sama dijalankan oleh sesi fixer fallback; hasil patch tetap dibatasi pada file target.
- File berubah:
  - `application/controllers/Purchase.php`
- Perubahan utama:
  - `rebuild_impact_index()` hanya memakai `purchase.rebuild.impact.index:view`.
  - `rebuild_impact_run()` hanya memakai `purchase.rebuild.impact.index:edit`.
  - `reclassify_profile_domain_index()` hanya memakai `purchase.reclassify.profile.domain.index:view`.
  - `reclassify_profile_domain_run()` hanya memakai `purchase.reclassify.profile.domain.index:edit`.
  - Logika bisnis, route, schema, role matrix, dan data staging tidak diubah.
- Validasi:
  - `php -l application/controllers/Purchase.php`: lulus.
  - `git diff --check -- application/controllers/Purchase.php`: lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Static scan memastikan empat method tidak lagi memiliki fallback `PAGE_ORDER` atau page tool lain.
  - Query SELECT-only registry: kedua page code aktif; permission eksplisit terbaca untuk role terkait, termasuk akses view/edit sesuai output preflight.
- Review auditor: PASS. Guard berada sebelum payload/query/mutasi, diff scope tepat, dan tidak ada blocker acceptance.
- Risiko sisa: role matrix masih memberikan akses edit rebuild cukup luas; ini perlu review kebijakan akses, bukan cacat guard batch ini. Negative HTTP test dengan akun least-privilege belum dijalankan.
- Batch berikutnya: auditor mengarahkan containment P0 `Pos_mobile` untuk permission per aksi; setelah itu review fail-closed division/outlet scope pada `Auth_model`/`MY_Controller`.

## Batch 4A — POS mobile core-writer RBAC dan token fail-closed

- Waktu: 2026-09-02 07:55 WIB.
- Prioritas: P0 security/RBAC — endpoint writer POS mobile hanya memvalidasi token/session, tetapi belum memeriksa permission per aksi; token valid yang izinnya dicabut masih berisiko menulis. Review lanjutan juga menemukan token bearer invalid dapat jatuh ke jalur API-key/session.
- Diskusi auditor/fixer: auditor menetapkan batch terbatas pada writer inti POS mobile dan `printer_test`, dengan selector page/action yang sama seperti web. Actor harus berasal dari token tervalidasi atau session, permission diambil melalui `Auth_model`, dan penolakan harus terjadi sebelum writer, sinkronisasi, atau printer work. Fixer menambahkan helper permission fail-closed, cache per request, guard per aksi, lalu memperbaiki jalur bearer invalid agar tidak fallback. Profil `finance_fixer` tidak dapat dipanggil langsung oleh akun CLI, sehingga instruksi yang sama dijalankan melalui sesi fixer fallback.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - Guard `edit` pada save/confirm/payment/void/refund/cashier open/close dan `printer_test`.
  - Selector `create` versus `edit`, fallback refund paid → cashier → draft, dan printer registry → legacy diuji terhadap helper controller.
  - Permission memuat user ID dari token/session tervalidasi, mendukung `__superadmin__`, mengembalikan JSON 403 dengan `page_code`/`action`, dan tidak memakai user ID dari payload.
  - Bearer atau `X-Pos-Mobile-Token` yang disuplai tetapi invalid sekarang langsung 401; tidak dapat fallback ke session/API-key.
  - Harness fake menguji token valid/invalid, API-key tanpa user, invalid API-key/bearer, urutan guard sebelum protected work, selector nyata, dan tidak adanya writer/sync/printer call saat ditolak.
- Validasi:
  - `php -l application/controllers/Pos_mobile.php`: lulus.
  - `php -l tools/tests/pos_mobile_authorization_smoke.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/pos_mobile_authorization_smoke.php`: seluruh check lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: seluruh check lulus.
  - Tidak ada perubahan route, schema, maupun data staging pada batch ini.
- Review auditor: review pertama CHANGES karena fallback token invalid dan coverage harness belum membuktikan selector/credential edge case. Setelah fixer menutup fallback dan menambah test controller nyata, review final: PASS.
- Risiko sisa: endpoint read/print-target mobile belum mendapat permission per aksi; binding outlet/terminal/device, token refresh/rotation, dan step-up approval masih ditunda sesuai kontrak saat ini. Belum ada negative HTTP test dengan akun least-privilege terhadap server staging. Test harness baru masih untracked dan perlu ikut dipaketkan saat perubahan ini dipromosikan.
- Batch berikutnya: auditor menilai prioritas P0 berikutnya pada fail-closed division/outlet scope di `Auth_model`/`MY_Controller`, dengan bukti query dan negative test sebelum implementasi.

## Batch 5 — P0-07A isolasi backup dari repository

- Waktu: 2026-09-02 08:14 WIB.
- Prioritas: P0 operasional/security — runner backup aktif melakukan stage/commit/fetch/merge/push ke `origin/main`; histori menunjukkan commit `backup:` berkala dan repository memuat artefak dump/log. Ini berisiko membocorkan data serta mengubah source tree otomatis.
- Diskusi auditor/fixer: auditor memilih batch kecil untuk menghentikan seluruh operasi Git pada runner, mempertahankan dump lokal, retensi, dan logging. Fixer menghapus konfigurasi repository dari runner, env example/actual, controller generator, dan UI; menambahkan aturan ignore untuk dump/log baru tanpa menghapus artefak lama. Review pertama menemukan dua key stale pada `scripts/backup/.env`; fixer menghapus hanya key tersebut dan menambah coverage smoke test.
- File berubah:
  - `.gitignore`
  - `scripts/backup/.env`
  - `scripts/backup/.env.example`
  - `scripts/backup/backup_full.sh`
  - `scripts/backup/backup_full.bat`
  - `application/controllers/System_tools.php`
  - `application/views/system/dbtools.php`
  - `application/views/system/settings.php`
  - `application/views/system/backup_guide.php`
  - `tools/tests/backup_source_isolation_smoke.php`
- Perubahan utama:
  - Runner Linux/Windows hanya membuat dump dan log lokal, menjalankan retensi, lalu memberi pesan bahwa off-site terenkripsi harus dikonfigurasi terpisah.
  - Seluruh executable Git dan field repository dihapus dari generator `.env`, UI, dan contoh konfigurasi.
  - Dump/log baru di-ignore; `.gitkeep` tetap dipertahankan. Dump, log, backup, dan runtime existing tidak dihapus.
  - Smoke test membaca `.env` aktual tanpa mencetak nilai dan memastikan key repository stale tidak muncul.
- Validasi:
  - `bash -n scripts/backup/backup_full.sh`: lulus.
  - `php -l` `System_tools.php`, tiga view backup, dan smoke test: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/backup_source_isolation_smoke.php`: 32/32 lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Runner backup tidak dijalankan; tidak ada query/mutasi database.
- Review auditor: PASS. Auditor mengonfirmasi tidak ada executable Git atau repository setting tersisa pada runner/env/UI, jalur simpan tetap ada, retensi/logging dipertahankan, dan artefak existing tidak dihapus.
- Risiko sisa: backup masih lokal dan belum terenkripsi/off-site; belum ada restore drill, monitoring, atau uji Windows VM. Artefak lama yang sudah tracked tetap ada dan perlu kebijakan cleanup/migrasi terpisah. `finance_fixer` tidak tersedia sebagai profil CLI akun ini, sehingga implementasi dijalankan oleh sesi fallback dengan instruksi yang sama.
- Batch berikutnya: auditor memilih prioritas P0 berikutnya, terutama fail-closed division/outlet scope pada `Auth_model`/`MY_Controller` atau hardening konfigurasi produksi, dengan batch kecil dan negative test yang terukur.

## Batch 6 — P0-04A fail-closed division scope

- Waktu: 2026-09-02 08:54 WIB.
- Prioritas: P0 security/RBAC — scope divisi lama mengubah role tanpa scope atau multi-scope menjadi `NULL`, lalu `active_division_id()` menafsirkan `NULL` sebagai akses unrestricted.
- Diskusi auditor/fixer: auditor menetapkan policy ketat: non-superadmin hanya boleh lewat bila role aktif menghasilkan tepat satu division ID positif; duplikat divisi yang sama tetap `SINGLE`, sedangkan `NONE`, `AMBIGUOUS`, null/0/negatif/mixed ditolak. SUPERADMIN menjadi satu-satunya jalur unrestricted. Review pertama menemukan stale detector masih memfilter `r.is_active = 1`, sehingga deaktivasi role SUPERADMIN tidak terdeteksi. Fixer menghapus filter itu hanya dari stale-signal query dan menambah coverage role deactivation serta login rejection.
- File berubah:
  - `application/models/Auth_model.php`
  - `application/controllers/Auth.php`
  - `application/core/MY_Controller.php`
  - `tools/tests/auth_division_scope_smoke.php`
- Perubahan utama:
  - Menambahkan resolver state eksplisit `SINGLE`, `NONE`, dan `AMBIGUOUS`; hanya `SINGLE` dengan ID positif yang disimpan sebagai scope efektif.
  - Login menolak non-superadmin tanpa scope tunggal valid sebelum membuat session authenticated atau session log login.
  - `MY_Controller` memvalidasi scope di request entry sebelum constructor controller turunan bekerja; sesi legacy direfresh sekali dan hasil unresolved tetap 403, baik HTML maupun AJAX JSON.
  - Refresh permission memperbarui kembali `auth_user`, scope, dan status SUPERADMIN; stale detector memeriksa perubahan role termasuk role yang baru dinonaktifkan.
  - `active_division_id()` tidak lagi mengembalikan `NULL` untuk non-superadmin unresolved; ia fail-closed dengan exception di luar gate normal.
  - Smoke DB-free mencakup resolver invalid/multi-scope, role deactivation cached SUPERADMIN, child-work zero sebelum 403, ID 0/negatif, dan login rejection tanpa auth session/log.
- Validasi:
  - `php -l application/models/Auth_model.php`: lulus.
  - `php -l application/controllers/Auth.php`: lulus.
  - `php -l application/core/MY_Controller.php`: lulus.
  - `php -l tools/tests/auth_division_scope_smoke.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/auth_division_scope_smoke.php`: 39/39 lulus.
  - Regression smoke backup: 32/32 lulus; POS mobile lulus; inventory period guard lulus.
  - Verifikasi CI 3.1.13 mendukung query-builder `group_start()`; tidak ada perubahan schema/data dan tidak ada koneksi DB yang diperlukan.
- Review auditor: review pertama CHANGES karena role yang dinonaktifkan tidak masuk stale detector dan test belum membuktikan jalur itu. Setelah fixer memperbaiki query dan test, review final PASS.
- Risiko sisa: revocation masih mengikuti throttle staleness 120 detik dan timestamp beresolusi detik; pencabutan segera memerlukan invalidasi session eksplisit. `_get_role_permissions()` masih perlu ditinjau untuk memfilter role nonaktif saat refresh agar permission lama tidak diwarisi. Smoke harness baru masih untracked dan wajib ikut dipaketkan saat promosi. Pengguna existing dengan scope `NONE`/`AMBIGUOUS` akan ditolak sampai role diperbaiki.
- Batch berikutnya: auditor memilih hardening P0 berikutnya, dengan kandidat utama filter role nonaktif pada permission refresh atau P0-06A konfigurasi produksi (secret, CSRF, cookie, session, CORS) dalam batch kecil dengan test regresi.

## Batch 7 — P0-04B filter permission role nonaktif

- Waktu: 2026-09-02 09:08 WIB.
- Prioritas: P0 security/RBAC — `_get_role_permissions()` masih menggabungkan permission dari `auth_user_role` dan `auth_role_permission` tanpa memastikan role masih aktif. Role yang dinonaktifkan dapat tetap memberi akses sampai cache/session diperbarui.
- Diskusi auditor/fixer: auditor memilih hardening terpusat ini sebelum konfigurasi produksi P0-06A karena satu query memengaruhi login, refresh permission, dan jalur POS; batch dapat diuji DB-free tanpa mutasi staging. Fixer menambahkan join role dan filter aktif, mempertahankan union antar-role aktif, filter page aktif, serta urutan override GRANT lalu REVOKE.
- File berubah:
  - `application/models/Auth_model.php`
  - `tools/tests/auth_inactive_role_permission_smoke.php`
- Perubahan utama:
  - Query permission kini join `auth_role r` pada `r.id = ur.role_id` dan hanya mengambil `r.is_active = 1`.
  - Smoke test memakai `Auth_model` nyata dan fake query builder in-memory; mencakup role hanya nonaktif, kombinasi aktif/nonaktif, union dua role aktif, page aktif, override GRANT/REVOKE, SUPERADMIN nonaktif, dan refresh session.
- Validasi:
  - `php -l application/models/Auth_model.php`: lulus.
  - `php -l tools/tests/auth_inactive_role_permission_smoke.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/auth_inactive_role_permission_smoke.php`: 19/19 assertion lulus.
  - Regression smoke `auth_division_scope_smoke.php`: 39/39 lulus; POS mobile, inventory period guard, dan backup source isolation lulus.
  - Tidak ada koneksi atau mutasi database staging.
- Review auditor: review pertama menandai CHANGES hanya karena worktree mengakumulasi perubahan Batch 1–6; setelah baseline batch dijelaskan, review final PASS. Auditor mengonfirmasi delta Batch 7 tidak memiliki defect fungsional.
- Risiko sisa: permission session web yang sudah aktif tetap mengikuti refresh/re-login atau stale throttle hingga 120 detik; smoke masih DB-free sehingga konsistensi relasi/status pada database nyata perlu diverifikasi saat koneksi staging tersedia. Harness baru masih untracked dan harus ikut dipaketkan saat promosi.
- Batch berikutnya: auditor memilih sub-batch P0-06A hardening konfigurasi produksi yang paling kecil dan aman, dimulai dari kontrak/validasi konfigurasi secret, session, cookie, CSRF, dan CORS tanpa mengubah nilai staging secara membabi buta.

## Batch 8 — P0-06A-1 boundary secret dan production preflight

- Waktu: 2026-09-02 09:30 WIB.
- Prioritas: P0 security/deployment — encryption key dan credential database masih berada di konfigurasi aplikasi; aplikasi membutuhkan boundary secret yang aman dan fail-closed sebelum bootstrap production.
- Diskusi auditor/fixer: auditor memilih sub-batch paling kecil: resolver environment-only, preflight production sebelum bootstrap CodeIgniter, wiring config/database, dokumentasi kontrak provisioning, dan smoke DB-free. CSRF, cookie, session, CORS, service config, dan License Hub sengaja tidak disentuh pada batch ini. Profil `finance_fixer` tidak dapat dipanggil langsung oleh akun CLI, sehingga implementasi dijalankan oleh sesi fallback dengan instruksi yang sama.
- File berubah:
  - `application/libraries/DeploymentConfig.php`
  - `application/config/config.php`
  - `application/config/database.php`
  - `index.php`
  - `docs/deployment_secret_contract.md`
  - `tools/tests/deployment_secret_config_smoke.php`
- Perubahan utama:
  - Resolver membaca lima nama konfigurasi dari process environment tanpa logging, output, atau fallback secret.
  - Production memvalidasi seluruh kontrak sebelum bootstrap CI; konfigurasi tidak lengkap menghasilkan HTTP 503 generik tanpa membocorkan nama atau nilai secret.
  - Encryption key dan empat parameter database memakai resolver; driver `mysqli` serta opsi koneksi non-kredensial dipertahankan.
  - Kontrak provisioning, cutover, perlindungan kompatibilitas session terenkripsi, dan aturan redaksi dicatat tanpa menyimpan nilai secret.
  - Smoke test memakai fixture environment sintetis, menguji wiring config/database dan child process production dengan environment kosong.
- Validasi:
  - `php -l` lima file PHP target: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/deployment_secret_config_smoke.php`: 39/39 lulus.
  - Regression smoke `auth_division_scope_smoke.php`: 39/39 lulus; inactive-role, POS mobile, inventory period guard, dan backup source isolation juga lulus.
  - Tidak ada koneksi/mutasi database, runner backup, atau penghapusan file runtime.
- Review auditor: PASS. Preflight berada sebelum bootstrap, resolver dan wiring benar, tidak ada defect fungsional pada delta batch, dan opsi koneksi non-kredensial identik dengan baseline VCS.
- Risiko sisa: process PHP saat ini belum memiliki variabel `FINANCE_*`; staging/production akan tetap 503 sampai variabel diprovision ke PHP-FPM dan CLI lalu worker di-reload. Belum ada runtime health test dengan environment lengkap, restore/session compatibility drill, atau rotasi secret. Temuan CORS wildcard, CSRF disabled, cookie flags, dan session lifetime masih terbuka untuk batch berikutnya.
- Batch berikutnya: minta auditor memilih sub-batch P0 konfigurasi/security berikutnya dengan acceptance test terukur; provisioning secret production dilakukan sebagai langkah operasional terpisah sebelum cutover.

## Batch 9 — P0-09A trust boundary Printer Agent lokal

- Waktu: 2026-09-02 10:18 WIB.
- Prioritas: P0 security — local Printer Agent menerima request lintas-origin secara terlalu longgar dan bootstrap belum mempunyai kontrak origin/key yang fail-closed.
- Diskusi auditor/fixer: auditor meminta allowlist origin exact, key header-only, bootstrap guard sebelum model/query, bind loopback, serta penghapusan jalur bootstrap legacy. Fixer menerapkan perubahan kecil pada agent, controller, helper diagnostik, dokumentasi, contoh konfigurasi, dan smoke test.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/controllers/Pos_printer_agent.php`
  - `tools/pos_printer_agent/agent.py`
  - `tools/pos_printer_agent/check_saved_printers.py`
  - `tools/pos_printer_agent/config.example.json`
  - `tools/pos_printer_agent/README.md`
  - `tools/pos_printer_agent/requirements.txt`
  - `application/views/pos/printer_guide.php`
  - `application/views/pos/printer_guide_config.php`
  - `tools/tests/printer_agent_trust_smoke.py`
- Perubahan utama:
  - CORS global dihapus; `/cetak` hanya menerima origin exact dari `api.base_url`/allowlist dan service bind ke `127.0.0.1`.
  - Bootstrap hanya menerima `X-Printer-Key`; environment kosong menghasilkan 503, key salah 403, dan guard dilakukan sebelum model/query.
  - `Pos::printer_bootstrap()` publik dihapus; kedua route resmi tetap menuju `pos_printer_agent/bootstrap`.
  - Key tidak lagi dikirim melalui query string; dependency CORS yang tidak dipakai dihapus.
- Validasi:
  - PHP lint target: lulus.
  - Python source compile tanpa bytecode dan static/source smoke: lulus.
  - Regression POS mobile, auth scope, inactive-role, inventory, backup, deployment: lulus.
  - `git diff --check`: lulus; `config.json`, `agent.log`, dan `routes.php` tidak berubah.
  - HTTP smoke penuh belum berjalan karena Flask tidak terpasang di environment ini.
- Review auditor: review pertama menemukan legacy `Pos::printer_bootstrap`; fixer menghapus method tersebut. Review final: PASS untuk delta batch.
- Risiko sisa: trust contract produksi lengkap masih membutuhkan signed/nonce/timestamp atau pairing per-device, rotasi token, installer/service manager, health/version contract, dan uji fisik printer. Field `key_query_param` pada config runtime lama masih ada tetapi tidak dibaca dan bukan bypass.
- Batch berikutnya: auditor memilih hardening provisioning secret sebelum melanjutkan ke kontrak HTTP/pairing yang lebih besar.

## Batch 10 — P0-09A gate provisioning config dan bundle

- Waktu: 2026-09-02 10:18 WIB.
- Prioritas: P0 security — `config_json` membawa `POS_PRINTER_BOOTSTRAP_KEY`, tetapi sebelumnya dapat diunduh dengan permission halaman printer umum yang juga dimiliki kasir/barista.
- Diskusi auditor/fixer: auditor menetapkan gate exact `pos.printer.connection:edit`, tanpa fallback ke `pos.printer.index`, dan meminta link UI ikut disembunyikan. Fixer menerapkan gate sebelum generator JSON/ZIP serta memperbarui smoke test dan kedua guide.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/printer_guide.php`
  - `application/views/pos/printer_guide_config.php`
  - `tools/tests/printer_agent_trust_smoke.py`
- Perubahan utama:
  - `config_json` dan `agent_bundle` memerlukan `pos.printer.connection:edit` secara langsung sebelum file dibuat.
  - Link provisioning pada dua guide hanya ditampilkan jika boolean permission exact bernilai true; backend tetap menjadi pengaman utama.
  - Seed permission dan route tidak diubah; bundle tetap mengecualikan `config.json`.
- Validasi:
  - PHP lint tiga file target: lulus.
  - Python source compile tanpa bytecode dan static/source smoke: lulus.
  - POS mobile, auth scope 39, inactive-role, inventory 9, backup 32, deployment 39: lulus.
  - `git diff --check`: lulus; file runtime secret/log dan routes tidak berubah.
  - HTTP smoke penuh tetap tertunda karena Flask tidak tersedia.
- Review auditor: PASS. Tidak ada fallback permission untuk secret-bearing download, gate mendahului generator/archive, tidak ada legacy bypass, dan test mencakup permission/route/view/seed policy.
- Risiko sisa: permission seed `pos.printer.connection` harus tersedia pada instalasi customer; jika belum, akses non-SUPERADMIN fail-closed. Shared bootstrap key belum per-agent/rotatable dan HTTP `/cetak` masih mengandalkan origin boundary lokal; ini memerlukan batch pairing terpisah. Runtime `FINANCE_*` juga belum diprovision ke PHP-FPM/CLI staging.
- Batch berikutnya: minta auditor memilih prioritas P0 berikutnya; kandidat terdekat adalah CSRF/CORS/session hardening atau dependency/release preflight, tetap tanpa License Hub.

## Batch 11 — P0-03B RBAC endpoint baca POS Mobile

- Waktu: 2026-09-02 10:36 WIB.
- Prioritas: P0 security/RBAC — endpoint baca POS Mobile sebelumnya sudah mengautentikasi token, tetapi belum konsisten memeriksa permission `view` sebelum mengambil katalog, member, order, status sesi, preview, voucher, target cetak, dan data printer.
- Diskusi auditor/fixer: auditor memilih hardening endpoint baca sebagai prioritas tertinggi setelah writer POS Mobile terlindungi. Fixer menambahkan autentikasi user/employee dan guard `view` sebelum kerja bisnis pada semua endpoint baca/print-target; `printers` memakai selector registry/fallback exact. Bootstrap API-key-only tanpa user ditolak. Ping, login, dan logout tetap public; writer dan fallback workspace tidak diubah.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - `bootstrap`, katalog, pencarian member/extra, orders, load/preview/reversal, target cetak, payment prepare/voucher, status sesi, dan cashier close preview kini memanggil `authorize_mobile(true)` lalu permission `view` sebelum query/model/printer work.
  - `printers` memeriksa `pos.printer.connection:view` bila registry tersedia, atau `pos.printer.index:view` bila tidak tersedia; tidak fallback ketika registry ada.
  - API key tanpa user/employee tidak dapat membuka bootstrap; selector cashier → draft dan preferensi paid untuk refund tetap dipertahankan.
  - Smoke test mencakup 403 deny-before-work, page/action, API-key/bearer regression, superadmin, selector printer, fallback workspace, serta writer regression.
- Validasi:
  - `php -l application/controllers/Pos_mobile.php`: lulus.
  - `php -l tools/tests/pos_mobile_authorization_smoke.php`: lulus.
  - `php tools/tests/pos_mobile_authorization_smoke.php`: lulus.
  - Regression `auth_division_scope_smoke.php`, `auth_inactive_role_permission_smoke.php`, `inventory_period_guard_smoke.php`, `backup_source_isolation_smoke.php`, dan `deployment_secret_config_smoke.php`: seluruhnya lulus.
  - `git diff --check`: lulus.
  - Tidak ada perubahan database, route, seed, schema, data, atau runtime artifact.
- Review auditor: PASS. Semua endpoint baca/print-target menjalankan autentikasi lalu permission sebelum kerja terlindungi; bootstrap API-key-only ditolak; selector printer dan fallback workspace sesuai; smoke dan regression lulus.
- Risiko sisa: client lama yang hanya memakai API key untuk bootstrap kini ditolak dan perlu login user/employee; scope record-level outlet/order, binding perangkat, rotasi token, serta uji least-privilege HTTP staging belum dilakukan. HTTP smoke Printer Agent tetap tertunda karena Flask tidak tersedia.
- Batch berikutnya: auditor memilih prioritas P0 berikutnya dengan batch kecil dan acceptance test terukur; kandidat tetap CSRF/CORS/session hardening atau dependency/release preflight, tanpa License Hub.

## Batch 12 — P0-06A-2 boundary browser global

- Waktu: 2026-09-02 10:52 WIB.
- Prioritas: P0 security/configuration — aplikasi masih mengirim CORS wildcard global dan cookie aplikasi belum `HttpOnly`/`Secure` sesuai environment.
- Diskusi auditor/fixer: auditor menilai CSRF global belum aman diaktifkan karena terdapat banyak form POST dan AJAX yang belum seragam membawa token. Fixer memilih hardening browser yang terpusat: menghapus CORS wildcard global, mengaktifkan `HttpOnly`, dan mengaktifkan `Secure` hanya pada production; Printer Agent tetap memakai boundary CORS exact miliknya.
- File berubah:
  - `application/config/config.php`
  - `tools/tests/web_runtime_boundary_smoke.php`
  - `docs/deployment_secret_contract.md`
- Perubahan utama:
  - Tiga header `Access-Control-Allow-*` global dihapus dari config aplikasi.
  - `cookie_secure` menjadi `(ENVIRONMENT === 'production')`, `cookie_httponly` menjadi `TRUE`, dan `cookie_samesite` tetap `Lax`.
  - Smoke DB-free baru memeriksa source aplikasi, mengecualikan controller Printer Agent, dan memuat config dalam child PHP untuk development/production tanpa bootstrap/DB/runtime write.
  - Kontrak deployment menjelaskan HTTPS sampai browser, TLS terminator tepercaya, `X-Forwarded-Proto: https`, dan verifikasi sebelum cutover.
- Validasi:
  - `php -l application/config/config.php`: lulus.
  - `php -l tools/tests/web_runtime_boundary_smoke.php`: lulus.
  - `php tools/tests/web_runtime_boundary_smoke.php`: 14/14 lulus.
  - Regression `deployment_secret_config_smoke.php`: 39/39; `pos_mobile_authorization_smoke.php`, auth scope, inactive-role, inventory, dan backup: lulus.
  - `git diff --check`: lulus.
  - Printer Agent static/source smoke: lulus; HTTP smoke belum berjalan karena Flask tidak tersedia.
  - Tidak ada perubahan database/schema/route, session lifetime/regenerasi, CSRF, Printer Agent, atau runtime artifact.
- Review auditor: PASS. Header global hilang, cookie production-only/HttpOnly/SameSite sesuai, smoke aman dan tidak membocorkan secret, serta delta tidak mengubah area di luar scope.
- Risiko sisa: client browser lintas-origin yang mengandalkan wildcard CORS akan gagal; production harus benar-benar HTTPS dengan proxy tepercaya. CSRF global, session satu tahun/regenerasi dua jam, login throttling, dependency/release preflight, migration/updater, dan endpoint HTTP Printer Agent tetap terbuka/tertunda sesuai scope.
- Batch berikutnya: auditor memilih sub-batch P0 berikutnya dengan acceptance terukur; kandidat utama CSRF inventory/contract bertahap atau dependency/runtime/release preflight, tanpa License Hub.

## Batch 13 — P0-06A-3 scoped CSRF Inventory Control

- Waktu: 2026-09-02 11:41 WIB.
- Prioritas: P0 security — CSRF global masih disabled, sementara lima writer Inventory Control memiliki dampak tinggi: write-off defisit, koreksi nilai stok, buka periode, posting cut-off, dan buka kembali periode.
- Diskusi auditor/fixer: auditor memilih perlindungan scoped agar tidak mengaktifkan CSRF global secara prematur pada seluruh form/AJAX aplikasi. Fixer menambahkan token session-bound khusus Inventory Control, guard setelah RBAC/superadmin dan sebelum payload/query/service/transaksi/writer, lalu memperkuat smoke test berdasarkan review auditor berulang sampai behavioral harness memenuhi acceptance.
- File berubah:
  - `application/controllers/Inventory_control.php`
  - `application/views/inventory/stock_deficit_detail.php`
  - `application/views/inventory/stock_period_detail.php`
  - `application/views/inventory/stock_period_index.php`
  - `application/views/inventory/stock_value_reconciliation_index.php`
  - `tools/tests/inventory_control_mutation_csrf_smoke.php`
- Perubahan utama:
  - Lima endpoint writer menolak non-POST dengan 405 dan token hilang/kosong/malformed/session mismatch/mismatch dengan 403 sebelum kerja bisnis.
  - Token memakai `random_bytes(32)`, hanya reuse bila tepat 64 karakter hex, disimpan pada session, dibandingkan dengan `hash_equals`, dan tidak dicatat ke log.
  - Empat view menerima token scoped eksplisit; semua form POST memakai hidden token non-empty, form GET tetap tanpa token; placeholder CSRF global di area ini dihapus.
  - Smoke test DB-free memuat controller dengan stub/reflection dan dependency tripwire, menguji lima writer pada seluruh kasus invalid termasuk key session yang benar-benar absent, valid private guard, token regeneration, serta render/DOM form empat view.
- Validasi:
  - `php -l` controller, view yang berubah, dan smoke test: lulus.
  - `php tools/tests/inventory_control_mutation_csrf_smoke.php`: 272/272 lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Regression web boundary 14/14, deployment secret 39/39, POS mobile, auth division, inactive-role, backup isolation: lulus.
  - `git diff --check`: lulus; tidak ada perubahan route, model, schema, data, atau runtime artifact pada batch ini.
- Review auditor: PASS setelah dua putaran perbaikan harness. Auditor mengonfirmasi urutan guard, strict token invariant, public-action behavioral cases, session absence, dependency tripwire, dan DOM form checks.
- Risiko sisa: test masih DB-free/stubbed sehingga E2E browser dengan cookie/session backend nyata dan jalur AJAX perlu dijalankan kemudian. CSRF global, session lifetime satu tahun/regenerasi dua jam, serta writer web/AJAX lain di luar lima endpoint ini belum tercakup. Uji HTTP Printer Agent tetap tertunda karena Flask tidak tersedia di environment.
- Batch berikutnya: minta auditor memilih prioritas P0 berikutnya; kandidat terdekat adalah audit coverage CSRF writer lain atau dependency/runtime/release preflight, tetap tanpa License Hub.

## Batch 14 — P0-06A-4 scoped CSRF repair Audit Commit Stok POS

- Waktu: 2026-09-02 12:09 WIB.
- Prioritas: P0 security/integritas inventory — empat endpoint AJAX repair Audit Commit Stok POS dapat menjalankan rekonsiliasi atau repair drift stok/HPP, tetapi sebelumnya hanya memiliki RBAC tanpa POST-only dan CSRF sementara CSRF global masih disabled.
- Diskusi auditor/fixer: auditor memilih batch kecil yang terisolasi pada satu controller dan satu view, karena repair material/component langsung menyentuh integritas stok/HPP. Fixer menambahkan token session-bound scoped, guard setelah RBAC dan sebelum body/payload/query/model, serta wrapper AJAX yang hanya dipakai empat repair tersebut.
- Review auditor putaran pertama: CHANGES. Auditor menemukan risiko casing header pada CGI/FastCGI dan false assurance pada stub smoke test yang melakukan exact lookup. Fixer menyesuaikan lookup ke canonical CodeIgniter `X-Pos-Stock-Commit-Csrf`, mempertahankan kontrak browser `X-Pos-Stock-Commit-CSRF`, lalu memperbarui stub agar memodelkan normalisasi header dan menambah jalur valid untuk keempat writer.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_stock_commit_repair_csrf_smoke.php`
- Perubahan utama:
  - Empat endpoint `stock_commit_audit_repair_*` menolak non-POST dengan 405 JSON dan header scoped hilang, kosong, malformed, session absent, cross-session, atau mismatch dengan 403 JSON.
  - Token dibuat dengan `random_bytes(32)`, disimpan pada session, hanya reuse jika tepat 64 karakter hex, dan diverifikasi memakai `hash_equals`.
  - Header dibaca memakai casing canonical CodeIgniter agar tetap cocok setelah normalisasi CGI/FastCGI; tidak ada fallback token dari query atau body.
  - Halaman Audit Commit Stok POS merender token setelah permission; shared `postJson` tetap tidak membawa header secara default, sedangkan wrapper scoped hanya dipakai empat panggilan repair. Repair lot material lain tetap memakai helper bersama.
  - Tidak ada perubahan model, route, schema, role matrix, fallback permission, data, atau konfigurasi CSRF global.
- Validasi:
  - `php -l` controller, view, dan smoke test: lulus.
  - `php tools/tests/pos_stock_commit_repair_csrf_smoke.php`: 317/317 lulus, termasuk normalisasi CGI/FastCGI, valid request keempat writer, invalid request sebelum body/dependency, token generation/reuse, dan render/header scope.
  - `php tools/tests/inventory_control_mutation_csrf_smoke.php`: 272/272 lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: seluruh skenario lulus.
  - `php tools/tests/web_runtime_boundary_smoke.php`: 14/14 lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Canonical header sesuai `system/core/Input.php`; guard tepat setelah RBAC dan sebelum payload/model pada empat writer; wrapper scoped tepat; tidak ada perubahan di luar scope batch.
- Risiko sisa: smoke masih DB-free/stubbed; reverse proxy/FastCGI harus meneruskan custom header dan cache halaman tidak boleh berbagi token lintas sesi. E2E staging perlu membuktikan empat request valid sukses pada user berizin serta GET/header hilang/token salah menghasilkan 405/403 tanpa mutasi. Writer lain, termasuk repair lot material di luar empat endpoint, masih belum tercakup scoped CSRF.
- Batch berikutnya: minta auditor memilih prioritas tertinggi berikutnya, dengan kandidat CSRF writer lain yang berdampak tinggi atau dependency/runtime/release preflight; tetap tanpa License Hub.

## Batch 15 — P0-06A-5 scoped CSRF transaksi inti POS web

- Waktu: 2026-09-02 12:39 WIB.
- Prioritas: P0 security/integritas transaksi — writer payment, void, dan refund POS web memiliki dampak langsung terhadap pembayaran, reversal, status order, sinkronisasi task, serta rebuild availability, sementara CSRF global masih disabled.
- Diskusi auditor/fixer: auditor memilih batch kecil pada tiga writer transaksi inti karena dampaknya finansial dan batas perbaikannya jelas. Fixer menambahkan token session-bound dan guard setelah RBAC namun sebelum payload/model/sinkronisasi/rebuild, lalu menghubungkan wrapper AJAX scoped pada tiga view. Review auditor pertama menemukan smoke test menghitung deklarasi wrapper sebagai invocation, assertion header terlalu global, dan belum menguji reuse token session valid tanpa write. Fixer memperbaiki smoke-only; review kedua menyatakan PASS.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `application/views/pos/order_paid_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_payment_save`, `order_void_save`, dan `order_refund_save` mempertahankan RBAC lalu menjalankan guard scoped sebelum `request_payload()`, model writer, sync task, atau rebuild availability.
  - Token memakai `random_bytes(32)`, invariant 64 karakter hex, reuse hanya untuk token session valid, dan `hash_equals`; non-POST ditolak 405 JSON, token invalid/missing/malformed/session mismatch ditolak 403 JSON.
  - Browser memakai header `X-Pos-Transaction-CSRF`; server membaca nama canonical CodeIgniter `X-Pos-Transaction-Csrf` agar sesuai normalisasi CGI/FastCGI. Tidak ada fallback query/body dan CSRF global tidak diaktifkan.
  - Cashier memakai wrapper hanya untuk payment dan void; draft hanya void; paid hanya refund. Shared `postJson` tetap tidak membawa header scoped secara default.
  - Smoke DB-free diperketat untuk menghitung invocation tanpa deklarasi, memeriksa badan wrapper, menghitung tepat tiga guard call-site, dan membuktikan token valid direuse tanpa `set_userdata`.
- Validasi:
  - `php -l` seluruh empat view/controller dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 258/258 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `auth_division_scope_smoke.php`: 39/39; `web_runtime_boundary_smoke.php`: 14/14; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `inventory_period_guard_smoke.php`: seluruh skenario lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Harness tidak lagi memberi false assurance pada wrapper/header/helper; implementasi tetap menjaga urutan RBAC → CSRF → payload/writer dan alur UI transaksi.
- Risiko sisa: smoke masih DB-free/source-based; E2E HTTP dengan session/cookie nyata dan stack FastCGI/proxy belum dilakukan. CSRF scoped belum mencakup writer POS lain seperti draft save/confirm, cashier open/close, runtime job, stock-live rebuild, dan endpoint web mutasi lain. Perlindungan ini juga tidak menggantikan mitigasi XSS atau session theft.
- Batch berikutnya: minta auditor memilih prioritas tertinggi; kandidat terdekat adalah E2E HTTP security probe untuk kontrak CSRF yang sudah dipasang atau scoped CSRF pada writer POS berisiko tinggi berikutnya, sambil tetap menunda License Hub.

## Batch 16 — P0-06A-6 scoped CSRF buka/tutup kasir POS web

- Waktu: 2026-09-02 12:54 WIB.
- Prioritas: P0 security/integritas transaksi — `cashier_open` membuat shift/sesi kasir, sedangkan `cashier_close` mengubah status shift, kas aktual, selisih, ringkasan, dan menyiapkan direct print. Keduanya sebelumnya hanya RBAC tanpa guard CSRF scoped.
- Diskusi auditor/fixer: auditor memilih dua writer ini sebagai delta terkecil dengan dampak finansial langsung. Fixer menambahkan guard setelah RBAC dan mengalihkan dua call AJAX ke wrapper transaksi yang sudah ada. Smoke diperluas dari tiga menjadi lima writer dan diberi stub daily-recon serta tripwire dependency agar request invalid berhenti sebelum payload/model.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `cashier_open` dan `cashier_close` menjalankan `require_pos_transaction_csrf()` tepat setelah permission check dan sebelum payload, daily recon, writer shift/session, atau direct print.
  - Pemanggilan `pos/cashier/open` dan `pos/cashier/close` memakai `postPosTransactionJson`; helper `postJson` umum tetap tidak mengirim header CSRF global.
  - Smoke DB-free menguji lima writer untuk GET 405, header/session invalid 403, valid browser/canonical CI header, urutan RBAC → CSRF → payload/recon/writer, serta tidak ada dependency model pada rejection.
- Validasi:
  - `php -l` controller, view, dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 437/437 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `inventory_period_guard_smoke.php`: seluruh skenario lulus; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Guard dan wrapper berada pada lokasi yang tepat; acceptance B1 terpenuhi dan smoke tidak memberi false assurance yang terlihat.
- Risiko sisa: validasi masih DB-free/source-based; E2E HTTP dengan session/cookie nyata melalui FastCGI/reverse proxy belum dilakukan. Writer POS web lain seperti draft save/confirm, runtime job, dan stock-live rebuild masih belum tercakup CSRF scoped.
- Batch berikutnya: minta auditor memilih prioritas tertinggi berikutnya, dengan kandidat scoped CSRF draft/confirm atau E2E HTTP security probe bila URL dan akun uji aman tersedia; tetap tanpa License Hub.

## Batch 17 — P0-06A-7 scoped CSRF + permission delete draft POS

- Waktu: 2026-09-02 13:11 WIB.
- Prioritas: P0 security/integritas transaksi — endpoint `order_draft_delete($id)` menghapus order draft beserta line, state log, snapshot, dan runtime job; sebelumnya hanya memakai permission `edit` tanpa pembatasan POST dan CSRF scoped.
- Diskusi auditor/fixer: auditor memilih satu writer dengan route ID yang jelas sebagai batch kecil berisiko terukur. Fixer mengganti authorization menjadi aksi `delete`, mempertahankan selector/fallback halaman POS yang ada, menambahkan guard CSRF setelah RBAC dan sebelum actor/model, serta mengalihkan dua pemanggil UI ke wrapper scoped.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_draft_delete` sekarang memakai `delete`, menolak non-POST dengan 405 dan token/header/session invalid dengan 403, tanpa mencapai `delete_order_draft` pada request invalid.
  - Request valid dengan header browser atau canonical CodeIgniter yang session-bound meneruskan route ID dan actor ke writer tepat sekali; endpoint tidak membaca payload body.
  - Delete draft pada halaman cashier dan draft memakai `postPosTransactionJson`; helper `postJson` umum tetap tidak membawa header scoped secara default.
  - Smoke DB-free diperluas untuk permission delete, route ID/actor, urutan guard, 405/403, invalid tanpa model, valid dua casing header, dan wrapper invocation kedua view.
- Validasi:
  - `php -l` controller, dua view, dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 529/529 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `inventory_period_guard_smoke.php`: seluruh skenario lulus; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS; batch layak diterima. Auditor mengonfirmasi guard berada setelah RBAC dan sebelum actor/model, kedua view memakai wrapper, serta smoke memenuhi acceptance tanpa temuan baru di luar scope.
- Risiko sisa: validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata, browser runtime, dan FastCGI/reverse proxy belum dilakukan. Perubahan permission dapat menghasilkan 403 bagi user yang sebelumnya hanya memiliki `edit`; cache JavaScript lama juga harus memuat token baru. Writer POS lain seperti draft save/confirm, runtime job, dan stock-live rebuild masih belum tercakup.
- Batch berikutnya: minta auditor memilih prioritas P0 tertinggi berikutnya, kandidat utama adalah scoped CSRF pada draft save/confirm atau probe E2E HTTP jika URL dan akun uji staging tersedia; tetap tanpa License Hub.

## Batch 18 — P0-06A-8 scoped CSRF + action guard draft save/confirm POS

- Waktu: 2026-09-02 13:36 WIB.
- Prioritas: P0 security/integritas transaksi — endpoint `order_draft_save`, `order_draft_save_confirm`, dan `order_draft_confirm` mengubah draft, menyimpan snapshot/HPP, membuat queue stock commit, dan mengonfirmasi status order; sebelumnya belum memiliki CSRF scoped dan pembatasan POST.
- Diskusi auditor/fixer: auditor memilih tiga endpoint ini sebagai batch kecil berikutnya setelah writer payment, void/refund, buka/tutup kasir, dan delete draft ditutup. Fixer menerapkan urutan `view RBAC → CSRF → payload/id → permission create/edit → writer` untuk endpoint berbasis body, serta `edit RBAC → CSRF → actor/downstream` untuk confirm berbasis route. Caller frontend pada cashier dan draft memakai wrapper scoped yang sama.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - Ketiga endpoint kini menolak non-POST dengan 405 dan token/header/session invalid dengan 403 sebelum payload, actor, model, snapshot, queue, atau service downstream.
  - `order_draft_save` dan `order_draft_save_confirm` tetap membedakan aksi `create` untuk `id=0` dan `edit` untuk `id>0`, dengan permission action diperiksa setelah CSRF dan sebelum writer.
  - `order_draft_confirm` tetap memakai permission `edit`, lalu menjalankan guard sebelum actor dan proses konfirmasi.
  - Semua caller draft save/confirm pada cashier dan draft memakai `postPosTransactionJson` dengan `X-Pos-Transaction-CSRF`; helper POST umum tidak diberi header scoped secara global.
  - Smoke DB-free mencakup tiga endpoint baru, 405 non-POST, invalid token tanpa body/model/downstream, dua bentuk header, create/edit `id=0/1701`, writer terkontrol, dan wrapper exact.
- Validasi:
  - `php -l` controller, dua view, dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 832/832 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `inventory_period_guard_smoke.php`: semua skenario lulus; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS; auditor mengonfirmasi urutan guard/RBAC, semantik create/edit, wrapper frontend, dan assertion smoke sesuai acceptance. Tidak ada perubahan schema atau algoritme bisnis pada batch ini.
- Risiko sisa: validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata, browser runtime, dan FastCGI/reverse proxy belum dilakukan. Writer POS lain seperti runtime job trigger/process/retry, stock-live rebuild, dan endpoint web mutasi lain masih perlu audit/guard scoped. Permission `view` tetap menjadi prasyarat sebelum action create/edit dan dapat menghasilkan 403 secara fail-closed bagi konfigurasi role yang tidak lengkap.
- Batch berikutnya: minta auditor memilih prioritas P0 tertinggi berikutnya; kandidat utama adalah scoped CSRF pada runtime-job writer yang dipicu setelah confirm atau E2E HTTP security probe jika endpoint dan akun uji staging tersedia, tetap tanpa License Hub.

## Batch 19 — P0 runtime-job trigger POS

- Waktu: 2026-09-02 14:11 WIB.
- Prioritas: P0 security/integritas stok — `POST /pos/orders/runtime-jobs/trigger/{orderId}` memproses queue commit stok dan rebuild availability; sebelumnya belum memiliki CSRF scoped, POST-only, dan binding kuat antara route order, job, dan snapshot.
- Diskusi auditor/fixer: auditor memilih route ini karena dipanggil setelah confirm dan dapat memicu writer stok. Fixer menambahkan urutan `RBAC edit → POST/CSRF → payload/ID → binding job/order/type/snapshot/status/scope → service`, serta wrapper header scoped pada enam caller. Global `postJson` tidak diubah.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `application/views/pos/reservation_index.php`
  - `application/views/pos/self_order_orders.php`
  - `application/views/pos/online_food_orders.php`
  - `application/views/pos/stock_live_index.php`
  - `tools/tests/pos_runtime_job_trigger_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - Route menolak non-POST dan CSRF invalid sebelum membaca payload, query, atau memanggil service.
  - `orderId`, `job_id`, `job.order_id`, `job_type`, `snapshot_id/order_id`, status job/order/snapshot, retry, dan scope divalidasi fail-closed.
  - Pemanggil UI mengirim `X-Pos-Transaction-CSRF` melalui wrapper scoped; token disediakan pada controller/view yang sebelumnya belum memilikinya.
- Validasi:
  - `php -l` pada controller, enam view, dan dua smoke test: lulus.
  - Runtime smoke: 247/247; transaction smoke: 832/832.
  - Regression stock-commit repair: 317/317; inventory-control mutation: 272/272; inventory period guard, POS Mobile authorization, dan web runtime boundary: lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Implementasi sesuai acceptance dan tidak mengubah schema maupun algoritme bisnis utama.
- Risiko sisa: smoke masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata dan locking/query aktual belum dilakukan. `candidate_jobs(job_id)` di service belum memfilter `order_id`, tetapi controller menutup binding sebelum service. Branch refund pada view draft/paid masih merupakan dead-code inconsistency; route PAID aktif memakai view paid terpisah.
- Batch berikutnya: minta auditor memilih prioritas P0 tertinggi berikutnya, dengan kandidat scoped CSRF pada runtime-job process/retry atau stock-live rebuild, lalu lanjutkan tanpa License Hub.

## Batch 20 — P0 scoped CSRF manual rebuild Stock Live POS

- Waktu: 2026-09-02 14:27 WIB.
- Prioritas: P0 security/integritas availability — endpoint `stock_live_rebuild` dan `stock_live_rebuild_all` menulis cache availability/HPP live serta audit log, tetapi sebelumnya belum POST-only dan belum memakai CSRF scoped.
- Diskusi auditor/fixer: auditor memilih dua writer ini sebagai batch kecil dengan dampak jelas. Fixer mempertahankan permission dan seluruh payload/context, menempatkan guard `RBAC edit → CSRF → payload/actor/service`, lalu mengganti tepat dua caller rebuild pada view Stock Live ke wrapper scoped. `postJson` global tetap netral.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_live_index.php`
  - `tools/tests/pos_stock_live_rebuild_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - Dua writer kini menolak GET/PUT/PATCH dengan 405 dan token missing/malformed/cross-session/mismatch dengan 403 sebelum body, actor, library, cache, atau log disentuh.
  - Header browser dan canonical CodeIgniter/FastCGI diterima hanya bila sama dengan token session; token valid direuse tanpa rotasi.
  - Rebuild per-produk dan rebuild-all mengirim `X-Pos-Transaction-CSRF` melalui `postPosTransactionJson`; route, model, service, schema, dan algoritme availability tidak diubah.
  - Regression smoke transaction disesuaikan dari 10 menjadi 12 guarded writers.
- Validasi:
  - `php -l` controller, view, dan dua smoke test: lulus.
  - Stock Live smoke: 237/237; transaction smoke: 832/832.
  - Regression runtime trigger: 247/247; stock-commit repair: 317/317; inventory-control: 272/272; inventory period guard, POS Mobile authorization, dan web runtime boundary: lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, rebuild nyata, backup runner, atau mutasi data.
- Review auditor final: PASS. Kedua writer memenuhi acceptance; tepat dua caller memakai wrapper, global `postJson` bebas header scoped, dan tidak ada artefak schema/data/runtime baru.
- Risiko sisa: smoke masih isolated/source-based; E2E browser melalui FastCGI/reverse proxy belum membuktikan header diteruskan. Rebuild-all tetap sinkron dan dapat menghasilkan banyak log bila diulang oleh operator berizin; pembatasan/queue/retensi perlu batch terpisah.
- Batch berikutnya: scoped-CSRF dan method hardening untuk mutasi runtime-job POS yang masih memakai `postJson`, terutama `process-all`, `retry`, dan `retry-failed-all`; tetap tanpa License Hub.

## Batch 21 — P0 scoped CSRF mutasi runtime-job POS

- Waktu: 2026-09-02 14:46 WIB.
- Prioritas: P0 security/integritas stok — endpoint `runtime-jobs/retry/{jobId}`, `process-all`, dan `retry-failed-all` masih menerima mutasi tanpa CSRF scoped dan sebagian caller UI masih memakai `postJson` netral.
- Diskusi auditor/fixer: auditor memilih tiga endpoint ini sebagai kelanjutan langsung Batch 19 karena retry/process dapat memicu `PosRuntimeJobService`, retry stok, dan pemrosesan queue. Fixer menerapkan urutan `edit RBAC → POST/session-bound CSRF → validasi input/ID → dependency/service`, memindahkan `set_time_limit(0)` setelah guard, serta menambah wrapper token pada view Stock Live, Audit Commit POS, dan reconcile Purchase. `postJson` bersama tetap netral.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/controllers/Purchase.php`
  - `application/views/pos/stock_live_index.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `application/views/purchase/stock_division_reconcile_index.php`
  - `tools/tests/pos_runtime_job_mutation_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `retry/{jobId}` menolak method non-POST, memverifikasi token session/header canonical, lalu memvalidasi route ID positif sebelum memuat service atau memanggil retry.
  - `process-all` dan `retry-failed-all` memverifikasi CSRF sebelum membaca body, mengubah time limit, memuat service, atau memproses queue.
  - Caller runtime-job aktif pada tiga view memakai `postPosTransactionJson` dengan `X-Pos-Transaction-CSRF`; token Audit Commit POS dan reconcile Purchase dirender dari session.
  - Smoke baru menguji 308 skenario source/behavioral DB-free, termasuk method invalid, token missing/malformed/cross-session, ID invalid, canonical header, urutan dependency, dan seluruh caller target.
- Validasi:
  - `php -l` lima file aplikasi target dan dua smoke test: lulus.
  - `php tools/tests/pos_runtime_job_mutation_csrf_smoke.php`: 308/308 lulus.
  - Regression `pos_transaction_csrf_smoke.php`: 832/832; runtime trigger: 247/247; stock-live rebuild: 237/237; stock-commit repair: 317/317.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, rebuild nyata, atau mutasi data.
- Review auditor: PASS. Route aktif tetap benar, guard dan validasi ID berada sebelum dependency, wrapper scoped dipakai pada seluruh caller target, dan helper `postJson` tidak membawa header transaksi secara default.
- Risiko sisa:
  - Smoke masih DB-free/source-based; E2E browser melalui session nyata, FastCGI, dan reverse proxy belum membuktikan custom header diteruskan.
  - Tombol `process-all` pada `stock_commit_audit_index.php` masih mengirim `{limit}` tanpa `outlet_id`, sementara controller mewajibkan outlet; ini temuan lama di luar delta batch dan menyebabkan 422 sampai kontrak UI diselaraskan.
  - Mutasi runtime lain (`dismiss/delete job` dan `retry/dismiss snapshot`) masih perlu scoped CSRF/method hardening; tiga endpoint Batch 21 sudah tertutup.
- Batch berikutnya: auditor mengarahkan scoped CSRF/method hardening untuk mutasi runtime POS lain, khususnya dismiss/delete job dan retry/dismiss snapshot, lalu probe E2E HTTP CSRF bila endpoint dan akun uji staging tersedia; tetap tanpa License Hub.

## Batch 22 — P0 scoped CSRF delete-draft runtime-job POS

- Waktu: 2026-09-02 15:08 WIB.
- Prioritas: P0 security/integritas transaksi dan stok — endpoint `runtime-jobs/delete-draft/{jobId}` dapat menghapus order beserta artefak job/snapshot/line, tetapi sebelumnya belum POST-only, belum memakai CSRF scoped, dan masih melakukan cast ID sebelum validasi.
- Diskusi auditor/fixer: auditor memilih subset delete-draft sebagai mutasi paling destruktif dengan delta terkecil; snapshot retry ditunda karena menyentuh rangkaian writer stok yang lebih luas. Fixer mempertahankan policy `edit` yang sudah ada, route, model, query, dan status rules; perubahan dibatasi pada guard/parser, caller, smoke test, dan hitungan regresi.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_runtime_failed_job_delete_draft_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_runtime_failed_job_delete_draft()` kini menjalankan urutan `edit RBAC → require_pos_transaction_csrf() → parse_positive_runtime_id(jobId) → DB/query → Pos_model`.
  - Caller aktif delete-draft pada Audit Commit Stok POS memakai `postPosTransactionJson` dengan header `X-Pos-Transaction-CSRF`; `postJson` bersama tetap netral.
  - Smoke DB-free baru mencakup 121 check: GET/PUT/PATCH `405`, CSRF missing/malformed/no-session/cross-session/mismatch `403`, ID `0/-1/1abc/float` `422` sebelum DB/model, dua bentuk header valid, binding job/type/status, route, dan writer `delete_order_draft(order_id, actor)` tepat sekali.
  - Regression count transaction diperbarui dari 15 menjadi 16 guarded writers.
- Validasi:
  - `php -l` empat file target: lulus.
  - `php tools/tests/pos_runtime_failed_job_delete_draft_csrf_smoke.php`: 121/121 lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 832/832 lulus.
  - `php tools/tests/pos_runtime_job_mutation_csrf_smoke.php`: 308/308 lulus.
  - `php tools/tests/pos_runtime_job_trigger_csrf_smoke.php`: 247/247 lulus.
  - `php tools/tests/pos_stock_live_rebuild_csrf_smoke.php`: 237/237 lulus.
  - `php tools/tests/pos_stock_commit_repair_csrf_smoke.php`: 317/317 lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query/mutasi database, backup runner, rebuild nyata, atau perubahan artefak backup/upload/credential/log/runtime.
- Review auditor: PASS. Auditor mengisolasi delta Batch 22 dari perubahan kumulatif Batch 1–21 dan mengonfirmasi guard, parser, query binding, caller wrapper, serta smoke sesuai acceptance. Route, `Pos_model`, permission policy, endpoint dismiss/snapshot, dan algoritme stok tidak berubah.
- Risiko sisa:
  - Validasi masih DB-free/source-based; E2E browser dengan session/cookie nyata dan FastCGI/reverse proxy belum dijalankan.
  - ID nonnumerik dibuktikan pada level method-controller; route `(:num)` dapat menolak sebagian input lebih awal pada HTTP nyata.
  - Endpoint audit delete-draft masih memakai permission `edit`, berbeda dari `order_draft_delete` yang memakai `delete`; ini follow-up kebijakan RBAC dan sengaja tidak diubah pada batch ini.
  - Mutasi runtime-job dismiss serta runtime-snapshot retry/dismiss masih terbuka dan membutuhkan batch tersendiri.
- Batch berikutnya: hardening scoped CSRF/method untuk `runtime-jobs/dismiss/{jobId}`, lalu `runtime-snapshots/retry/{snapshotId}` dan `runtime-snapshots/dismiss/{snapshotId}`; setelah itu probe E2E HTTP dengan akun uji staging bila tersedia. Tetap tanpa License Hub.

## Batch 23 — P0 scoped CSRF dismiss runtime-job POS

- Waktu: 2026-09-02 15:28 WIB.
- Prioritas: P0 security/integritas runtime POS — endpoint `runtime-jobs/dismiss/{jobId}` menutup job FAILED secara mutatif, tetapi sebelumnya belum POST-only, belum memakai CSRF scoped, melakukan load service terlalu awal, dan memakai cast ID longgar.
- Diskusi auditor/fixer: auditor memilih dismiss job sebagai batch kecil sesudah delete-draft. Snapshot retry/dismiss dipisahkan karena menyentuh refresh snapshot, pembuatan job, dan/atau beberapa job sekaligus. Fixer mempertahankan permission selector `edit`, route, query, policy status, alasan cancel, service/model/SQL/schema, delete-draft, dan snapshot; perubahan hanya pada guard/parser, caller, smoke, dan regression count.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_runtime_failed_job_dismiss()` kini menjalankan urutan `edit RBAC → POST/scoped CSRF → parse_positive_runtime_id() → readiness/lookup DB → status FAILED → load/cancel_job()`.
  - ID `0`, negatif, campuran, float, leading zero, whitespace, dan overflow ditolak `422` setelah CSRF dan sebelum DB/service.
  - Hanya job berstatus tepat `FAILED` yang boleh memanggil `cancel_job()`; caller `.sca_dismiss_failed_job_btn` memakai `postPosTransactionJson`, sedangkan `postJson` shared tetap netral.
  - Smoke DB-free baru menjalankan method controller aktual dengan fake/tripwire dan mencakup 256 check: RBAC selector, 405, CSRF 403, strict ID 422, query `LEFT JOIN`/binding/type, status policy, dua bentuk header valid, urutan dependency, alasan cancel, respons, dan kontrak route/caller.
  - Regression count transaction diperbarui dari 16 menjadi 17 guarded writers.
- Validasi:
  - `php -l` controller, view, dan dua smoke test: lulus.
  - `php tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`: 256/256 lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 832/832 lulus.
  - Regression delete-draft: 121/121; runtime mutation: 308/308; runtime trigger: 247/247; stock-live rebuild: 237/237; stock-commit repair: 317/317 lulus.
  - `git diff --check`: lulus untuk delta tracked dan smoke baru tidak memiliki whitespace error.
  - Tidak menjalankan query/mutasi database, backup runner, rebuild nyata, atau perubahan artefak backup/upload/credential/log/runtime.
- Review auditor final: PASS tanpa changes required. Auditor mengonfirmasi smoke memuat dan menjalankan method controller aktual, guard berada sebelum DB/service, query/status/reason/response tetap, caller tepat scoped, dan route/service/model/schema/delete-draft/snapshot tidak ikut berubah.
- Risiko sisa:
  - Validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata melalui FastCGI/reverse proxy belum dijalankan. Race antara lookup status FAILED dan conditional update service masih bergantung pada guard atomik di service.
  - Route `(:num)` dapat menolak sebagian ID nonkanonis lebih awal pada HTTP nyata; method-level smoke tetap menguji controller.
  - Mutasi runtime-snapshot retry/dismiss masih terbuka. Tombol `process-all` yang mengirim `{limit}` tanpa `outlet_id` juga tetap menjadi temuan lama di luar batch.
- Batch berikutnya: auditor mengarahkan hardening scoped CSRF/method untuk `runtime-snapshots/retry/{snapshotId}` sebagai batch terpisah; setelah itu `runtime-snapshots/dismiss/{snapshotId}` dan probe E2E HTTP bila akun/fixture staging tersedia. Tetap tanpa License Hub.

## Batch 24 — P0 scoped CSRF retry runtime-snapshot POS

- Waktu: 2026-09-02 15:56 WIB.
- Prioritas: P0 security/integritas stok — endpoint `runtime-snapshots/retry/{snapshotId}` dapat merefresh snapshot, mengubah state order, membuat job, dan langsung memproses stock commit, tetapi sebelumnya belum POST-only, belum memakai CSRF scoped, memuat dependency terlalu awal, dan melakukan cast ID longgar.
- Diskusi auditor/fixer: auditor memilih retry snapshot sebagai writer berisiko tertinggi berikutnya. Fixer mempertahankan permission selector `edit`, route, query, state policy, service/model/schema, sequence writer, dan response; perubahan dibatasi pada guard/parser, caller, smoke, serta assertion regression yang terdampak.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_runtime_failed_snapshot_retry_csrf_smoke.php`
  - `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_runtime_failed_snapshot_retry()` kini menjalankan urutan `edit RBAC → POST/scoped CSRF → parse_positive_runtime_id(snapshotId) → lookup/state gate → actor/service load → writer lama`.
  - ID nonkanonis seperti `0`, negatif, campuran, float, leading zero, whitespace, dan overflow ditolak `422` sebelum DB/service.
  - State gate tetap mensyaratkan snapshot `FAILED`, order valid dan bukan `VOID`, serta stock commit order bukan `POSTED`, `REVERSED`, atau `NOT_REQUIRED`.
  - Sequence lama dibuktikan tetap: refresh snapshot → mark queued → update order `QUEUED` → queue job → proses satu job → latest lookup.
  - Caller retry memakai `postPosTransactionJson`/`X-Pos-Transaction-CSRF`; caller snapshot dismiss tetap netral untuk batch berikutnya.
  - Smoke baru DB-free menjalankan method controller aktual via reflection/fake/tripwire dan mencakup 391 check: RBAC, method 405, semua failure CSRF, strict ID, query/state gate, refresh/queue/process failure, sequence writer, header canonical, dan response.
  - Assertion pada smoke dismiss diselaraskan agar memverifikasi split caller retry scoped vs snapshot dismiss netral. Regression count transaction tetap 832 dan sekarang mencakup 18 writer guarded.
- Validasi:
  - `php -l` controller, view, dan tiga smoke test: lulus.
  - Retry smoke: 391/391; dismiss smoke: 256/256; transaction smoke: 832/832 lulus.
  - Regression delete-draft: 121/121; runtime mutation: 308/308; runtime trigger: 247/247; stock-live rebuild: 237/237; stock-commit repair: 317/317 lulus.
  - `git diff --check`: lulus untuk delta tracked; smoke baru tidak memiliki whitespace error.
  - Tidak menjalankan query/mutasi database, backup runner, rebuild nyata, atau perubahan artefak backup/upload/credential/log/runtime.
- Review auditor final: PASS tanpa changes required. Auditor mengonfirmasi guard sebelum DB/actor/library/writer, parser strict, query dan policy tidak melonggar, sequence/response tetap, caller scoped tepat, smoke menguji method aktual dan failure paths, serta tidak ada spillover di luar retry snapshot dan assertion smoke terkait.
- Risiko sisa:
  - Validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata melalui FastCGI/reverse proxy belum dijalankan.
  - Lookup status awal dan rangkaian writer berada pada transaksi terpisah; race sempit masih mungkin dan idealnya ditutup dengan revalidasi conditional/atomic di service.
  - Snapshot dismiss dan `process-all` masih batch/follow-up terpisah; tombol `process-all` yang belum mengirim `outlet_id` tetap temuan lama di luar scope.
- Batch berikutnya: auditor mengarahkan hardening scoped CSRF/method untuk `runtime-snapshots/dismiss/{snapshotId}`; setelah itu probe E2E HTTP dan penyelarasan kontrak `process-all` bila fixture/akun staging tersedia. Tetap tanpa License Hub.

## Batch 25 — P0 hardening smoke boundary runtime-snapshot POS

- Waktu: 2026-09-02 16:24 WIB.
- Prioritas: P0 kualitas validasi/security regression — menutup false-pass pada smoke test Batch 25 untuk endpoint `runtime-snapshots/dismiss/{snapshotId}` dan regression smoke runtime-job dismiss.
- Diskusi auditor/fixer: auditor menemukan delimiter test memakai ID tombol `sca_process_all_jobs_btn` yang tidak ada di view nyata (`sca_process_all_btn`), sehingga blok handler dapat melebar sampai EOF. Fixer mengubah hanya dua smoke test, memakai selector nyata, dan menambahkan assertion bahwa delimiter ditemukan serta posisinya berada setelah handler snapshot dismiss; endpoint, view, route, service, model, dan schema tidak diubah.
- File berubah:
  - `tools/tests/pos_runtime_failed_snapshot_dismiss_csrf_smoke.php`
  - `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`
- Perubahan utama:
  - Mengganti delimiter stale `sca_process_all_jobs_btn` menjadi `sca_process_all_btn`.
  - Menambahkan assertion delimiter tidak `false` dan berada setelah selector snapshot dismiss sebelum `substr()` mengambil blok caller.
  - Menghilangkan jalur silent fallback ke EOF sebagai kondisi yang dapat menghasilkan false pass; smoke tetap memakai fallback teknis, tetapi assertion wajib gagal bila delimiter tidak ditemukan.
- Validasi:
  - `php -l` kedua smoke: lulus.
  - Snapshot dismiss smoke: 504/504 lulus.
  - Job dismiss smoke: 257/257 lulus.
  - Snapshot retry regression: 391/391 lulus.
  - POS transaction CSRF regression: 832/832 lulus.
  - Selector stale tidak ditemukan; delimiter nyata terverifikasi pada view dan kedua smoke.
  - `git diff --check`: lulus; tidak ada trailing whitespace pada dua smoke baru.
- Review auditor final: PASS. Auditor mengonfirmasi kedua test tidak lagi dapat false-pass karena EOF slicing, selector/caller/route/endpoint tetap selaras, seluruh validasi yang diminta lulus, dan tidak ada perubahan aplikasi di luar scope.
- Risiko sisa:
  - Dua smoke test masih untracked sehingga wajib ikut dalam release/change set agar perlindungan regresinya tidak hilang.
  - Belum ada E2E browser/database dengan session, RBAC, FastCGI/reverse proxy, atau fixture staging nyata.
  - Race antara perubahan status snapshot dan pembatalan runtime job tetap ada sebagai risiko desain terpisah.
- Batch berikutnya: pastikan dua smoke test masuk paket perubahan, lalu auditor memilih prioritas berikutnya; kandidat terdekat adalah probe E2E staging bila fixture tersedia atau penyelarasan tombol `process-all` dengan kontrak `outlet_id`. Tetap tanpa License Hub.

## Batch 25R — Rekonsiliasi log implementasi runtime-snapshot dismiss

- Waktu: 2026-09-02 16:52 WIB (entri rekonsiliasi; implementasi dilakukan sebelum koreksi smoke Batch 25).
- Prioritas: P0 integritas stok — endpoint `runtime-snapshots/dismiss/{snapshotId}` menutup snapshot FAILED dan job runtime terkait sehingga harus POST-only, session-bound, dan memiliki binding order/status yang ketat.
- Ringkasan auditor/fixer: implementasi guard scoped, parser ID positif, state gate, penutupan snapshot, dan pembatalan job sudah diterapkan; review auditor final PASS setelah selector boundary smoke diperbaiki. Entri ini ditambahkan agar implementasi tidak hilang dari jejak eksekusi.
- File terkait: `application/controllers/Pos.php`, `application/views/pos/stock_commit_audit_index.php`, `tools/tests/pos_runtime_failed_snapshot_dismiss_csrf_smoke.php`, `tools/tests/pos_runtime_failed_snapshot_retry_csrf_smoke.php`, `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`, `tools/tests/pos_transaction_csrf_smoke.php`.
- Validasi: snapshot dismiss 504 checks, snapshot retry 391 checks, job dismiss 257 checks, POS transaction 832 checks, lint terkait, selector boundary, dan `git diff --check` lulus.
- Risiko sisa: test masih DB-free/source-based; E2E HTTP, race status snapshot/job, dan kontrak process-all tanpa `outlet_id` tetap terbuka.

## Batch 26 — P0 scoped CSRF web-order verification writers

- Waktu: 2026-09-02 16:52 WIB.
- Prioritas: P0 security/integritas order dan stok — verifikasi reservasi, self-order, dan online-food dapat membuat/finalisasi order, snapshot stok, queue runtime job, sinkronisasi task, dan direct print, tetapi sebelumnya belum memakai guard POS transaction-CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih tiga writer sebagai batch kecil berisiko tinggi dengan infrastruktur token yang sudah tersedia. Process-all tidak dipilih karena audit page belum memiliki outlet selector yang aman. Fixer menempatkan `require_pos_transaction_csrf()` setelah RBAC dan sebelum payload/helper, mengganti hanya caller verifikasi terkait ke `postPosTransactionJson()`, serta memperluas smoke test dengan fake/tripwire.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/reservation_index.php`
  - `application/views/pos/self_order_orders.php`
  - `application/views/pos/online_food_orders.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `reservation_verify()`, `self_order_order_verify()`, dan `online_food_order_verify()` kini menjalankan urutan `edit RBAC → scoped CSRF/POST → payload atau helper lama`.
  - Caller first-party verifikasi memakai header `X-Pos-Transaction-CSRF` dengan `credentials: same-origin`; helper `postJson()` umum tetap tidak membawa header scoped.
  - Smoke membuktikan GET/PUT `405`, token missing/malformed/mismatch `403`, tidak ada payload/model/service/writer sebelum guard, serta valid-token mempertahankan actor, payload, context, label/event, dan flow service.
  - Tidak ada perubahan route, schema, order rule, outlet scope, global CSRF, data, secret, atau runtime artifact.
- Validasi:
  - `php -l` controller, tiga view, dan smoke: lulus.
  - `pos_transaction_csrf_smoke.php`: 1.152 checks lulus.
  - Runtime trigger: 247; runtime mutation: 308; failed-job dismiss: 257; failed-snapshot retry: 391; failed-snapshot dismiss: 504 checks lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query/mutasi database atau E2E browser karena fixture/session staging belum tersedia.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi urutan guard, caller scoped, smoke tripwire, dan perilaku valid-token sesuai acceptance.
- Risiko sisa: E2E browser/FastCGI/reverse proxy belum membuktikan custom header pada server nyata; CSRF global masih nonaktif; writer reject dan lifecycle reservasi/order lain di luar batch ini masih terbuka; worktree tetap memiliki perubahan kumulatif sebelumnya.
- Batch berikutnya: auditor memilih hardening scoped CSRF untuk writer lifecycle reservasi serta reject self-order/online-food, tetap tanpa License Hub.

## Batch 27 — P0 scoped CSRF lifecycle reservasi

- Waktu: 2026-09-02 17:07 WIB.
- Prioritas: P0 security/integritas transaksi reservasi — endpoint tambah DP, reject, dan cancel melakukan mutasi finansial/status, tetapi sebelumnya belum POST-only dan belum terikat CSRF scoped.
- Ringkasan diskusi auditor/fixer: auditor memilih tiga writer dalam satu halaman reservasi karena token dan wrapper scoped sudah tersedia dari batch sebelumnya. `reservation_save()` sengaja dipisahkan karena permission create/edit ditentukan dari payload; reject self-order/online-food dan process-all tetap batch lain. Fixer menempatkan guard setelah permission existing dan sebelum payload/model, lalu mengganti caller writer ke wrapper scoped.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/reservation_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `reservation_deposit()` memakai `edit → scoped CSRF/POST → request_payload() → add_deposit()`.
  - `reservation_reject()` memakai `edit → scoped CSRF/POST → request_payload() → reject_reservation()`.
  - `reservation_cancel()` memakai `delete → scoped CSRF/POST → request_payload() → cancel_reservation()`.
  - `saveMoreDeposit()` dan `saveClose()` memakai `postPosTransactionJson()`; helper `request()` generik dan caller read-only tetap netral.
  - Smoke diperluas dari 22 menjadi 25 guarded writers dan menguji method controller aktual, 405 GET/PUT, 403 token invalid tanpa payload/model, valid header, payload, actor, permission, response, serta writer invocation.
- Validasi:
  - `php -l` untuk controller, view, dan smoke: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 1.384 checks lulus.
  - Regression runtime POS: trigger 247, mutation 308, failed-job dismiss 257, failed-snapshot retry 391, failed-snapshot dismiss 504 checks lulus.
  - `git diff --check`: lulus.
  - Tidak ada perubahan route, schema, model production, global CSRF, business rule, database, secret, atau runtime artifact.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi guard placement, caller scoped, helper read-only yang tetap netral, smoke tripwire, dan validasi independen.
- Risiko sisa:
  - Smoke masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata, FastCGI/reverse proxy, dan mutasi finansial live belum dibuktikan.
  - Worktree berisi perubahan kumulatif batch sebelumnya sehingga review dibatasi pada delta Batch 27.
  - `reservation_save()`, reject self-order/online-food, dan bug kontrak process-all tanpa `outlet_id` masih terbuka.
- Batch berikutnya: auditor memilih satu batch kecil berikutnya; kandidat prioritas adalah authenticated HTTP integration smoke untuk satu reservation writer bila fixture/akun staging tersedia, atau hardening writer reject self-order/online-food bila E2E belum dapat dijalankan. Tetap tanpa License Hub.

## Batch 28 — P0 scoped CSRF reject Self Order dan Online Food

- Waktu: 2026-09-02 17:21 WIB.
- Prioritas: P0 security/integritas order — endpoint reject Self Order dan Online Food melakukan perubahan status order/pembayaran menjadi VOID/REJECTED, tetapi belum memakai scoped transaction-CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih dua endpoint sebagai batch kecil dengan risiko tinggi dan pola perbaikan mekanis. Fixer menempatkan urutan `edit RBAC → scoped CSRF/POST → payload/reason → actor → writer`, mengganti caller reject pada dua view ke wrapper scoped, dan memperkuat smoke dengan fixture writer, reflection tripwire, pemeriksaan urutan, 405/403, argumen, response flow, serta netralitas `postJson`. Tidak ada perluasan ke route, schema, model production, global CSRF, atau License Hub.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/self_order_orders.php`
  - `application/views/pos/online_food_orders.php`
  - `tools/tests/pos_transaction_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `self_order_order_reject()` dan `online_food_order_reject()` kini mempertahankan permission `edit`, menolak non-POST dengan 405 dan token tidak valid dengan 403 sebelum payload/model, lalu menjalankan writer lama hanya setelah token session-bound valid.
  - Caller `submitReject()` pada self-order dan online-food memakai `postPosTransactionJson()` sehingga mengirim `X-Pos-Transaction-CSRF` dengan `same-origin`; helper `postJson()` tetap netral.
  - Smoke mencakup 27 guarded writers dan membuktikan ID route `1701`, actor `1`, reason trim `Alasan smoke`, writer tepat satu kali, response error/success, serta wrapper/caller.
- Validasi:
  - `php -l` controller, dua view, dan smoke: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 1.591 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check` untuk file target: lulus.
  - Tidak menjalankan query/mutasi database atau authenticated browser/HTTP karena fixture/session staging belum tersedia.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi guard placement, perilaku 405/403, writer tripwire, caller scoped, dan smoke tidak false-pass pada area batch ini.
- Risiko sisa:
  - Validasi masih DB-free/source-based; authenticated HTTP dengan session/RBAC nyata, FastCGI/reverse proxy, dan transaksi database belum dibuktikan.
  - `reservation_save()` yang permission-nya ditentukan dari payload masih terpisah; process-all tanpa `outlet_id` dan kontrak writer lain tetap terbuka.
  - Worktree tetap berisi perubahan kumulatif batch sebelumnya.
- Batch berikutnya: auditor mengarahkan pembuatan authenticated HTTP/session integration smoke minimal untuk endpoint reject ini bila fixture/akun staging dapat digunakan; jika belum, pilih hardening writer lifecycle yang paling aman berikutnya. Tetap tanpa License Hub.

## Batch 29 — P0 scoped CSRF reservation_save dengan guard aksi

- Waktu: 2026-09-02 17:34 WIB.
- Prioritas: P0 security/RBAC/integritas transaksi — `reservation_save()` membuat atau mengubah reservasi, line, dan DP awal, tetapi sebelumnya membaca payload sebelum authorization dan belum POST-only/scoped CSRF.
- Ringkasan diskusi auditor/fixer: auditor tidak memilih E2E HTTP karena repository tidak memiliki harness HTTP, fixture session/akun, atau kontrak staging yang aman. Auditor memilih writer reservasi inti ini karena token dan wrapper scoped sudah tersedia. Fixer menerapkan urutan `view RBAC → scoped CSRF/POST → payload/id → create|edit RBAC → writer`, mengalihkan hanya caller `saveReservation()` ke wrapper scoped, dan menambah fake/reflection/tripwire untuk response sukses serta failure path.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/reservation_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `reservation_save()` sekarang memeriksa `pos.reservation.index:view` sebelum body, menolak non-POST dengan 405 dan token tidak valid dengan 403, lalu baru memilih permission `create` untuk `id=0` atau `edit` untuk `id>0` sebelum `save_reservation()`.
  - Caller `saveReservation()` memakai `postPosTransactionJson()`; helper `request()` dan caller read-only tetap netral.
  - Smoke membuktikan 28 guarded writers, GET/PUT, enam kelas token invalid, empat kombinasi header/id valid, urutan body/permission, payload, actor employee `1`/user `2`, writer tepat satu kali, dan kontrak JSON sukses.
- Validasi:
  - `php -l` controller, view, dan smoke: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 1.689 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check` untuk file target: lulus.
  - Tidak ada query/mutasi database atau E2E HTTP/browser.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi body-derived action permission tetap setelah CSRF, response-completion fake tidak melemahkan test lama, wrapper count tidak menghitung definisi helper, dan tidak ada spillover Batch 29.
- Risiko sisa:
  - Smoke masih DB-free/fake-based; authenticated HTTP dengan session/RBAC nyata, FastCGI/reverse proxy, dan transaksi database belum dibuktikan.
  - Process-all tanpa `outlet_id` dan writer POS order-monitor lain masih terbuka.
  - Worktree berisi perubahan kumulatif batch sebelumnya.
- Batch berikutnya: auditor mengarahkan hardening endpoint mutasi POS order-monitor yang masih tersisa dengan boundary method/scoped CSRF. Tetap tanpa License Hub.

## Batch 30 — P0 scoped CSRF task tunggal POS Order Monitor

- Waktu: 2026-09-02 17:54 WIB.
- Prioritas: P0 security/RBAC/integritas operasional — tiga endpoint task tunggal Order Monitor (`ack-task`, `ready-task`, `checker-task`) sebelumnya menerima mutasi tanpa POST-only dan scoped CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih batch kecil ini karena seluruh endpoint memakai shared handler dan tiga writer yang sudah memiliki kontrak scope; bulk action dan mutasi tersembunyi `bootstrap_open_tasks()` sengaja tetap dipisahkan. Fixer menambahkan token session-bound khusus Order Monitor, guard terpusat, serta wrapper browser hanya untuk tiga caller task tunggal.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/order_monitor_index.php`
  - `tools/tests/pos_order_monitor_task_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `order_monitor()` membuat atau memakai ulang token pada session key khusus setelah permission `view`, lalu merendernya ke halaman.
  - Shared handler menjalankan urutan `edit RBAC → POST/scoped CSRF → payload/task_id → actor/scope → writer`, dengan mapping tetap ke `ack_task`, `ready_task`, dan `checker_task` serta response lama.
  - Caller `*-task` memakai header `X-Pos-Order-Monitor-CSRF` dan `same-origin`; caller bulk `*-order`, route, model production, global CSRF, dan `bootstrap_open_tasks()` tidak diubah.
  - Smoke DB-free baru menguji 405/403, session binding, validasi `task_id`, mapping writer, response, render view, caller bulk, serta tripwire dependency.
- Validasi:
  - `php -l` controller, view, dan smoke: lulus.
  - `php tools/tests/pos_order_monitor_task_csrf_smoke.php`: 570 checks lulus.
  - Regression `pos_transaction_csrf_smoke.php`: 1.689 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check` untuk file target: lulus.
  - Tidak ada query/mutasi database atau authenticated HTTP/browser karena fixture session/RBAC nyata belum tersedia.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi urutan guard, penolakan method/token sebelum writer, mapping tiga aksi, tripwire smoke, dan isolasi caller bulk.
- Risiko sisa:
  - Verifikasi HTTP browser/FastCGI dengan session dan RBAC nyata belum dilakukan; smoke tetap DB-free.
  - Tiga writer bulk Order Monitor masih belum memiliki hardening scoped CSRF.
  - Mutasi tersembunyi pada `bootstrap_open_tasks()` masih terbuka dan membutuhkan keputusan lifecycle terpisah.
- Batch berikutnya: auditor mengarahkan audit/hardening terpisah untuk tiga aksi bulk Order Monitor dengan kontrak method, CSRF, payload, dan scope yang sama; tetap tanpa License Hub.

## Batch 31 — P0 scoped CSRF tiga aksi bulk POS Order Monitor

- Waktu: 2026-09-02 18:12 WIB.
- Prioritas: P0 security/RBAC/integritas operasional — tiga endpoint bulk Order Monitor (`ack-order-station`, `ready-order-station`, `checker-order`) sebelumnya melakukan mutasi tanpa POST-only dan scoped CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih hardening boundary HTTP/UI karena kontrak model bulk sudah menerima actor dan `monitorScope`; perubahan atomicity model serta lifecycle `bootstrap_open_tasks()` dinilai berisiko dan dipisahkan. Fixer membuat shared handler, parser payload ketat, dan memperluas smoke controller nyata dari tiga menjadi enam writer.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/order_monitor_index.php`
  - `tools/tests/pos_order_monitor_task_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Tiga public method bulk kini mendelegasikan tepat sekali ke shared handler dengan urutan `edit RBAC → POST/scoped CSRF → payload → validasi order_id/station → actor/scope → writer`.
  - `order_id` menolak nilai missing/null/zero/negative/zero-padded/float/bool/array/non-numeric/overflow tanpa coercion; station bulk hanya `BAR` atau `KITCHEN`.
  - Caller tiga aksi bulk memakai wrapper `same-origin` dan header `X-Pos-Order-Monitor-CSRF`; tiga caller task tunggal tetap scoped, helper `getJson`/`postJson` tetap netral.
  - Smoke memverifikasi route/model tetap, 405/403/422, mapping writer, actor `4242`, scope, response sukses/gagal, enam caller, serta fake DB/loader/controller tripwire.
- Validasi:
  - `php -l` controller, view, dan smoke: lulus.
  - `php tools/tests/pos_order_monitor_task_csrf_smoke.php`: 1.285 checks lulus.
  - Regression `pos_transaction_csrf_smoke.php`: 1.689 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check`: clean, termasuk pemeriksaan smoke untracked.
  - Tidak ada query/mutasi database atau authenticated HTTP/browser.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi delta terbatas pada controller/view/smoke, route dan model production tidak berubah, urutan guard benar, parser non-koersif, response terminal kompatibel, dan smoke tidak false-pass.
- Risiko sisa:
  - Validasi masih DB-free; authenticated browser/FastCGI dengan session, RBAC, scope pegawai restricted, dan database nyata belum dibuktikan.
  - Atomicity bulk model masih parsial bila salah satu task gagal; sengaja tidak diubah pada batch ini.
  - Mutasi tersembunyi `bootstrap_open_tasks()` pada GET/data masih terbuka dan perlu audit lifecycle terpisah.
- Batch berikutnya: auditor mengarahkan authenticated HTTP integration smoke untuk Order Monitor bila fixture aman tersedia; setelah itu audit atomicity bulk model dan lifecycle `bootstrap_open_tasks()`. Tetap tanpa License Hub.

## Batch 32 — P1 lifecycle read-only dan sinkronisasi task Order Monitor

- Waktu: 2026-09-02 18:34 WIB.
- Prioritas: P1 integritas lifecycle — `order_monitor()` dan polling `order_monitor_data()` sebelumnya memanggil `bootstrap_open_tasks()`, yang dapat memutasi task/order ketika user hanya membaca; beberapa writer juga belum menyinkronkan projection task setelah perubahan order tertentu.
- Ringkasan diskusi auditor/fixer: auditor menolak authenticated HTTP sebagai prioritas karena belum ada harness/fixture aman, lalu memilih purity read path dan penutupan gap sync. Fixer menghapus bootstrap dari read path dan model publik, menambah sync terarah setelah writer sukses, serta membuat smoke DB-free dengan tripwire. Atomicity bulk dan backfill historis tetap dipisahkan.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/controllers/Pos_mobile.php`
  - `application/models/Pos_order_monitor_model.php`
  - `tools/tests/pos_order_monitor_lifecycle_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - GET halaman dan polling Order Monitor hanya membaca options/board payload; `bootstrap_open_tasks()` dihapus dari controller dan model production.
  - Sync ditambahkan setelah standalone confirmed append dengan line baru pada web/mobile/`orders_push` non-confirm, setelah void/refund mobile sukses, dan setelah recovery finalisasi reservasi sukses.
  - Draft biasa, failure, header-only append, dan combined save-confirm tidak mendapat sync tambahan; confirm dan task action internal mempertahankan tepat satu sync pada jalur sukses.
  - Smoke baru menguji purity endpoint baca, payload board, urutan writer/failure, recovery, dan anti-double-sync menggunakan controller/model/DB tripwire.
- Validasi:
  - `php -l` pada 4 file Batch 32: lulus.
  - `php tools/tests/pos_order_monitor_lifecycle_smoke.php`: PASS 38 checks.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: PASS 1.689 checks.
  - `php tools/tests/pos_order_monitor_task_csrf_smoke.php`: PASS 1.285 checks.
  - `php tools/tests/pos_mobile_authorization_smoke.php`: seluruh checks lulus.
  - `git diff --check`: bersih; source production tidak lagi mengandung `bootstrap_open_tasks`.
  - Tidak ada query/mutasi database nyata atau authenticated HTTP/browser.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi delta Batch 32 terisolasi, GET/poll murni read-only, seluruh jalur sync diminta, dan tidak ada regression jelas.
- Risiko sisa:
  - Validasi tetap DB-free; authenticated HTTP/session/RBAC nyata, FastCGI/reverse proxy, dan transaksi database belum dibuktikan.
  - Order historis yang belum memiliki task tidak lagi dibackfill melalui read path dan memerlukan operasi rekonsiliasi terpisah bila diperlukan.
  - Sync projection masih best-effort setelah transaksi writer; atomicity bulk tetap terbuka.
  - Baseline PHP manifest/release masih perlu diselaraskan dengan source/runtime aktual.
- Batch berikutnya: auditor mengarahkan penyelarasan baseline/runtime PHP release secara terpisah, tetap tanpa License Hub.

## Batch 33 — P1 kontrak baseline PHP runtime dan release

- Waktu: 2026-09-02 18:57 WIB.
- Prioritas: P1 release readiness — metadata PHP/dependency belum mencerminkan source aktif dan belum ada kontrak runtime/release yang dapat dipakai sebagai preflight.
- Ringkasan diskusi auditor/fixer: auditor memilih audit kontrak baseline tanpa mengubah versi atau dependency secara spekulatif. Source memakai native PHP 8, CLI staging teramati PHP 8.1.32, sedangkan web/FPM dan worker belum terbukti. Fixer membakukan fakta, batas teknis, kandidat dukungan provisional, matrix extension, kebijakan Composer/lockfile, risiko `fileinfo` WhatsApp, dan checklist preflight dalam dokumentasi serta smoke DB-free.
- File berubah:
  - `docs/README.md`
  - `docs/release_runtime_contract.md` (untracked; wajib ikut change/release set)
  - `tools/tests/release_runtime_contract_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan link kontrak runtime/release pada indeks dokumentasi.
  - Mendokumentasikan floor teknis source `>=8.0`, runtime CLI yang teramati, kandidat `>=8.0 <8.2` yang belum menjadi janji dukungan, status web/FPM/worker, extension, Composer, lockfile, fileinfo, dan release preflight.
  - Menambahkan smoke tanpa bootstrap aplikasi, database, network, Composer/vendor, atau pembuatan artefak; smoke memeriksa PHP, extension inti, metadata Composer, status lockfile, parse 622 file PHP produksi, dan marker syntax/native PHP 8.
- Validasi:
  - `php -l tools/tests/release_runtime_contract_smoke.php`: lulus.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks, 3 warning (`fileinfo` tidak loaded, floor Composer stale, lockfile absent), 3 info.
  - `composer validate --no-check-publish`: exit 0, `composer.json` valid; Composer 2.0.14 mengeluarkan deprecation toolchain dan root warning.
  - `git diff --check` untuk README dan pemeriksaan whitespace file baru: bersih.
  - Tidak ada perubahan Batch 33 pada `composer.json`, `.gitignore`, `composer.lock`, application source, route, schema, atau execution log sebelum pencatatan ini.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi fakta dokumen, smoke tidak false-pass yang jelas/side effect berbahaya, deteksi parse/syntax konservatif, dan scope batch.
- Risiko sisa:
  - Lint/parser PHP 8.1 dan tripwire syntax bukan pengganti matrix aktual PHP 8.0/8.1.
  - Web/FPM dan worker/cron belum diverifikasi; fileinfo masih wajib untuk jalur WhatsApp/MIME.
  - Kebijakan support resmi, lockfile, toolchain Composer reproducible, release manifest/tag, dan bukti web/FPM masih terbuka.
- Batch berikutnya: auditor memilih prioritas lanjutan untuk keputusan support PHP, matrix CLI/web-FPM/worker, serta mekanisme dependency lock dan release reproducible; tetap tanpa License Hub.

## Batch 34 — P0 containment secret WhatsApp dan boundary view

- Waktu: 2026-09-02 19:24 WIB.
- Prioritas: P0 security — `wa/api/env-read` dan settings sebelumnya dapat membocorkan `DB_PASS`/`WA_TOKEN` kepada user `view` atau merender token di HTML; save kosong juga berisiko mereset secret.
- Ringkasan diskusi auditor/fixer: auditor memilih containment secret sebelum fileinfo/release. Fixer membatasi env-read ke `wa.settings:edit` sebelum file access, membuat response status-only, menghapus secret dari semua data view yang relevan, menjaga secret lama pada blank save, mempertahankan baris env lain, dan menambah smoke controller DB-free.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `api_env_read()` edit-only, permission gate sebelum path/file read, response hanya status configured/secret allowlist.
  - settings/dashboard/guide tidak lagi menerima token pada view; token write-only; JS tidak mengisi rahasia dan fallback manual `.env` dihapus.
  - blank secret pada DB setting dan `.env` dipertahankan; non-secret updates tetap bekerja; baris/secret tambahan dipertahankan; error tidak memantulkan content/path.
  - smoke memakai sentinel sintetis dan child process tanpa DB/network/secret runtime.
- Validasi:
  - lint 3 target: lulus.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20 checks.
  - `php tools/tests/deployment_secret_config_smoke.php`: PASS 39 checks.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks, 3 warning lama (`fileinfo` tidak loaded, floor Composer stale, lockfile absent).
  - `git diff --check` tracked dan no-index file baru: bersih.
  - tidak ada perubahan target pada Composer/lock/routes/schema; tidak ada file dihapus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - E2E HTTP/browser dengan session/RBAC nyata dan filesystem nyata belum dilakukan.
  - `.env` masih penyimpanan secret file karena secret manager migration out of scope.
  - `api_engine_logs` masih mengembalikan baris log mentah kepada `wa.settings:view`; log saat ini hanya menyatakan status konfigurasi, tetapi producer log baru wajib tidak mencetak credential.
  - CSRF global tetap disabled; template/group writer RBAC dan fallback `fileinfo` masih terbuka.
- Batch berikutnya: auditor mengarahkan pembatasan/redaksi engine-log dan smoke duplicate/CRLF `.env`, dengan kandidat prioritas P0 action-RBAC untuk writer `template()`/`group()` sebelum fileinfo; tetap tanpa License Hub.

## Batch 35 — P0 RBAC aksi server-side writer WhatsApp template/group

- Waktu: 2026-09-02 19:37 WIB.
- Prioritas: P0 security/RBAC — POST langsung pada `Whatsapp::template()` dan `Whatsapp::group()` sebelumnya hanya melewati `view`, sehingga user view-only berpotensi create/edit/toggle/delete melalui URL.
- Ringkasan diskusi auditor/fixer: auditor memilih menutup bypass writer yang aktif sebelum containment engine-log. Fixer menambahkan guard aksi setelah pembacaan minimal `action`/`id`, mempertahankan GET view-only dan `send_group:create`, lalu membuat smoke DB-free yang menguji jalur ditolak dan diizinkan.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `tools/tests/whatsapp_template_group_action_rbac_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Template dan group `save` memakai `create` untuk `id <= 0`, `edit` untuk `id > 0`.
  - `toggle` memerlukan `edit` sebelum SELECT/update; `delete` memerlukan `delete` sebelum operasi DB.
  - Jalur view-only ditolak HTTP 403 sebelum payload mutasi, query/write, session, upload, atau API bot disentuh.
  - GET tetap dapat dirender dengan izin `view`; permission `send_group:create` tetap dipertahankan.
  - Smoke mencakup 8 jalur CRUD, 2 GET, dan `send_group`, plus pemeriksaan dependency/order guard.
- Validasi:
  - `php -l application/controllers/Whatsapp.php`: lulus.
  - `php -l tools/tests/whatsapp_template_group_action_rbac_smoke.php`: lulus.
  - `php tools/tests/whatsapp_template_group_action_rbac_smoke.php`: PASS 63 checks.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20 checks.
  - `php tools/tests/deployment_secret_config_smoke.php`: PASS 39 checks.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks; warning baseline tetap (`fileinfo` tidak loaded, floor Composer stale, lockfile absent).
  - `git diff --check` tracked dan no-index file baru: bersih; routes tidak berubah.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - CSRF form template/group belum diperluas dalam batch ini.
  - `api_engine_logs` masih perlu containment/redaksi; `fileinfo` belum aktif.
  - E2E HTTP/session/RBAC nyata dan query database belum dijalankan.
  - `composer.json` masih meng-underclaim PHP source floor dan `composer.lock` belum tersedia.
- Batch berikutnya: auditor mengarahkan containment `api_engine_logs` agar raw log/path/directory tidak terbuka, disertai smoke redaction dan pemeriksaan duplicate/CRLF `.env`; tetap tanpa License Hub.

## Batch 36 — P0 containment engine-log dan deterministik `.env` WhatsApp

- Waktu: 2026-09-02 19:56 WIB.
- Prioritas: P0 security — `api_engine_logs()` sebelumnya dapat mengembalikan tail log mentah, path absolut, dan directory listing kepada pemegang izin `view`; merge `.env` juga membiarkan duplicate key yang disentuh.
- Ringkasan diskusi auditor/fixer: auditor memilih containment diagnostik sebelum CSRF endpoint engine. Fixer menjadikan endpoint edit-only sebelum path/filesystem, menghapus pembacaan/return raw log dan `ls`, membatasi UI ke status generik, lalu mengkanonisasi hanya managed key `.env` yang disentuh dengan sanitasi CR/LF/NUL.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_engine_log_boundary_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `api_engine_logs()` kini memeriksa `wa.settings:edit` sebelum resolve path/status file dan hanya mengembalikan `ok/status/available/message` generik.
  - `ls`, `tail`, file-content read, nama file, path, listing, PII, dan secret tidak lagi dikirim lewat endpoint; kontrol log di view hanya tampil untuk `$canEdit`.
  - `mergeWaEnvContent()` mempertahankan komentar/key tambahan dan untouched key, menghapus duplicate hanya pada key yang disentuh, memilih kemunculan pertama sebagai posisi kanonis, dan selalu menghasilkan LF.
  - `waEnvUpdatesFromPayload()` dan merge memberi defense-in-depth terhadap CR/LF/NUL serta blank secret tetap berarti tidak berubah.
  - Smoke memakai fixture sintetis/temp, probe filesystem, child process, dan reflection helper; tidak membaca `.env`/log runtime.
- Validasi:
  - lint controller, view, smoke: lulus.
  - `php tools/tests/whatsapp_engine_log_boundary_smoke.php`: PASS 23 checks.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20 checks.
  - `php tools/tests/deployment_secret_config_smoke.php`: PASS 39 checks.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks; warning baseline (`fileinfo` tidak loaded, floor Composer stale, lockfile absent).
  - `git diff --check` tracked dan no-index file baru: bersih; tidak ada perubahan route/schema/seed/CSRF/start-stop-reset/License Hub.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Smoke dynamic bergantung pada temp directory writable dan belum digabung dengan authenticated HTTP/browser E2E.
  - Detail log sekarang hanya dapat dibaca operator melalui akses server; `fileinfo`, CSRF endpoint engine, runtime web/FPM, Composer floor/lock, dan release reproducibility masih terbuka.
  - Producer log baru tetap wajib tidak menulis secret/PII sensitif.
- Batch berikutnya: auditor mengarahkan Batch 37 P0 POST-only dan scoped CSRF untuk `api_engine_start()`, `api_engine_stop()`, dan `api_session_reset()`; tetap tanpa License Hub.

## Batch 37 — P0 POST-only dan scoped CSRF kontrol engine WhatsApp

- Waktu: 2026-09-02 20:15 WIB.
- Prioritas: P0 security — endpoint `api_engine_start()`, `api_engine_stop()`, dan `api_session_reset()` dapat menjalankan/mematikan proses atau menghapus `auth_info`; sebelumnya hanya memakai RBAC dan CSRF global masih nonaktif.
- Ringkasan diskusi auditor/fixer: auditor memilih satu batch kecil untuk mengunci tiga kontrol engine dengan scope CSRF terpisah. Fixer mengikuti pola POS berupa token sesi 64-hex, header khusus, `hash_equals`, penolakan 405/403, serta menjaga urutan RBAC -> CSRF -> dependency/mutasi. Smoke awal sempat memiliki assertion UI restart yang terlalu sempit; fixer memperbaiki assertion test tanpa memperluas perubahan produksi.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_engine_control_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope sesi `wa_engine_control_csrf`, token 32 random bytes/64 hex, header browser `X-Wa-Engine-Control-CSRF`, dan lookup CI `X-Wa-Engine-Control-Csrf`.
  - Ketiga endpoint kini POST-only dan menolak method non-POST dengan 405 serta token missing/malformed/mismatch dengan 403 sebelum filesystem, `exec`, atau DB.
  - Token hanya dibuat/dikirim pada settings view bila pemegang `wa.settings:edit`; Start, Stop, dua request Restart, dan Reset mengirim header scoped.
  - Smoke DB-free menguji RBAC ordering, side-effect boundary, kedua bentuk header, larangan fallback query/body, token rendering, dan seluruh caller UI.
- Validasi yang dijalankan:
  - `php -l application/controllers/Whatsapp.php`: lulus.
  - `php -l application/views/wa/settings.php`: lulus.
  - `php -l tools/tests/whatsapp_engine_control_csrf_smoke.php`: lulus.
  - `php tools/tests/whatsapp_engine_control_csrf_smoke.php`: PASS 160 checks.
  - Regresi `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_settings_secret_boundary_smoke.php`: PASS 20; `whatsapp_template_group_action_rbac_smoke.php`: PASS 63; `deployment_secret_config_smoke.php`: PASS 39.
  - `release_runtime_contract_smoke.php`: PASS 19, dengan warning baseline `fileinfo` belum aktif, floor PHP Composer masih stale, dan lockfile belum tersedia/masih di-ignore.
  - `git diff --check` tracked dan file smoke baru: bersih.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi scope batch, urutan guard, validasi token, non-exposure pada view-only, dan regresi Batch 34-36.
- Risiko sisa:
  - E2E HTTP/browser dengan session, RBAC, web/FPM, dan proxy header nyata belum dijalankan.
  - `api_env_save()` serta writer template/group belum diberi scoped CSRF; global CSRF tetap disabled.
  - Jalur gagal `api_engine_start()` masih dapat menyertakan diagnostik berbasis log dan perlu containment generik tersendiri.
- Batch berikutnya: auditor mengarahkan Batch 38 P0 POST-only dan scoped CSRF untuk `api_env_save()`, termasuk header UI dan smoke DB-free sebelum filesystem/write; tetap tanpa License Hub.

## Batch 38 — P0 POST-only dan scoped CSRF `api_env_save()` WhatsApp

- Waktu: 2026-09-02 20:32 WIB.
- Prioritas: P0 security — `api_env_save()` adalah writer edit-only untuk `.env` konfigurasi sensitif, tetapi sebelumnya hanya memakai RBAC dan CSRF global masih disabled.
- Ringkasan diskusi auditor/fixer: auditor memilih writer `.env` yang paling sempit setelah kontrol engine dikunci. Fixer menambahkan scope CSRF mandiri, guard sebelum raw payload/path/file write, header UI, smoke DB-free, dan hanya mengadaptasi fixture Batch 34 agar caller save positif membawa token. Semantik blank secret, sanitasi CR/LF/NUL, canonical duplicate-key, dan response status-only dipertahankan.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_env_save_csrf_smoke.php` (untracked; wajib ikut change/release set)
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php` (fixture/header/session adaptasi; untracked; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope sesi `wa_env_save_csrf`, token 32 random bytes/64 lowercase hex, header browser `X-Wa-Env-Save-CSRF`, dan lookup CI `X-Wa-Env-Save-Csrf` dengan `hash_equals`.
  - `api_env_save()` kini menjalankan urutan `wa.settings:edit` -> POST/scoped CSRF -> raw JSON -> path/filesystem -> merge -> write; method non-POST ditolak 405 dan token invalid ditolak 403 sebelum side effect.
  - Settings hanya merender token bagi editor dan request env-save mengirim header scoped bersama JSON; tidak memakai token engine-control.
  - Smoke baru menguji RBAC/order, method/token rejection, side-effect boundary, body/query fallback, dua bentuk header, token isolation/rendering, source ordering, dan UI caller.
- Validasi yang dijalankan:
  - lint controller, view, smoke baru, dan smoke secret-boundary: lulus.
  - `php tools/tests/whatsapp_env_save_csrf_smoke.php`: PASS 70 checks.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20; `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_engine_control_csrf_smoke.php`: PASS 160; `whatsapp_template_group_action_rbac_smoke.php`: PASS 63.
  - `deployment_secret_config_smoke.php`: PASS 39.
  - `release_runtime_contract_smoke.php`: PASS 19 dengan warning baseline `fileinfo` belum aktif, floor PHP Composer stale, dan lockfile absent/ignored.
  - `git diff --check` tracked dan file smoke baru: bersih; tidak ada perubahan route/schema/global config.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi guard-before-body/path/write, isolasi scope, status-only/blank-secret/merge regression, dan tidak ada runtime secret/log yang dibaca.
- Risiko sisa:
  - E2E browser/FPM/proxy header dan persistence session produksi belum diuji.
  - Global CSRF tetap disabled; writer template/group masih memerlukan scoped CSRF terpisah.
  - Smoke baru dan smoke WhatsApp terkait masih untracked dan wajib masuk release change set.
- Batch berikutnya: auditor mengarahkan Batch 39 P0 scoped CSRF untuk mutation writer `template()` dan `group()`, mempertahankan RBAC Batch 35 serta GET view-only; tetap tanpa License Hub.

## Batch 39 — P0 scoped CSRF mutasi form WhatsApp template/group

- Waktu: 2026-09-02 20:50 WIB.
- Prioritas: P0 security — tujuh writer form `Whatsapp::template()`/`group()` (`save`, `toggle`, `delete`, dan `send_group`) sudah memiliki RBAC per aksi dari Batch 35, tetapi belum memiliki CSRF scoped sementara CSRF global tetap disabled.
- Ringkasan diskusi auditor/fixer: auditor memilih batch ini karena template/grup dapat mengubah data, sedangkan `send_group` juga dapat mengirim pesan/media eksternal. Fixer menambahkan satu token session/form khusus, mempertahankan urutan `view gate -> action/id minimal -> RBAC -> CSRF -> payload/DB/upload/API`, serta tidak mengubah permission matrix atau writer lain.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/template.php`
  - `application/views/wa/group.php`
  - `tools/tests/whatsapp_template_group_mutation_csrf_smoke.php` (baru; wajib ikut change/release set)
  - `tools/tests/whatsapp_template_group_action_rbac_smoke.php` (adaptasi fixture token/session/output; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope `wa_template_group_mutation_csrf` dengan token `random_bytes(32)`, lowercase 64-hex, session-local, dan verifikasi `hash_equals`.
  - Guard diterapkan tepat pada tujuh action mutation; method selain POST ditolak 405 dan token missing/malformed/mismatch ditolak 403 sebelum payload bisnis, query, upload, writer, flash, atau Bot API.
  - Hidden field token dirender pada 3 form template dan 4 form group, termasuk multipart `send_group`; token hanya disediakan bagi pemegang izin writer. GET view-only tetap tidak mint/render token.
  - Smoke DB-free baru memeriksa ordering, isolation dari scope Batch 37/38, side-effect boundary, token rendering, serta seluruh tujuh form; RBAC smoke tetap memeriksa denial dan permission order Batch 35.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan dua smoke: lulus.
  - `whatsapp_template_group_mutation_csrf_smoke.php`: PASS 151 checks.
  - `whatsapp_template_group_action_rbac_smoke.php`: PASS 63 checks.
  - Regresi `whatsapp_engine_control_csrf_smoke.php`: PASS 160; `whatsapp_env_save_csrf_smoke.php`: PASS 70; `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_settings_secret_boundary_smoke.php`: PASS 20.
  - `git diff --check` tracked dan file smoke baru: bersih.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi tujuh urutan guard, perlindungan multipart sebelum upload/query/API, token scoped tanpa fallback query/header/raw-body, rendering seluruh form, serta regresi Batch 35/37/38.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session nyata dan multipart/bot/database nyata; smoke menggunakan CI/DB doubles.
  - Token masih session-scoped, bukan one-time; kompromi session/XSS berada di luar cakupan CSRF.
  - Global CSRF tetap disabled dan smoke baru masih untracked sampai release change set dikemas.
- Batch berikutnya: auditor mengarahkan P0 scoped CSRF untuk `Whatsapp::report_schedules()`—terutama `send_now` yang dapat memicu pengiriman eksternal—setelah audit action/RBAC dan form/JS terkait.

## Batch 40 — P0 scoped CSRF mutasi jadwal laporan WhatsApp

- Waktu: 2026-09-02 21:07 WIB.
- Prioritas: P0 security — route `wa/template/schedules` menerima empat mutasi (`save_schedule`, `toggle_schedule`, `delete_schedule`, `send_now`) saat CSRF global masih disabled; `send_now` dapat memanggil Bot API serta menulis status/audit pengiriman.
- Ringkasan diskusi auditor/fixer: auditor memilih scoped form token terpisah dan mempertahankan `wa.report_schedule:edit` untuk `send_now`; fixer menerapkan guard setelah pembacaan action/id minimal dan RBAC, sebelum payload, DB, flash, atau `sendWaReportSchedule()`. Tidak ada perubahan matriks permission atau perilaku bisnis schedule.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/report_schedule.php`
  - `tools/tests/whatsapp_report_schedule_mutation_csrf_smoke.php` (baru; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope session/form `wa_report_schedule_mutation_csrf` dengan `random_bytes(32)`, lowercase 64-hex, dan `hash_equals`; tidak menerima query/header/raw-body fallback.
  - Keempat action menolak method non-POST dengan 405 dan token invalid dengan 403; `send_now` dijaga sebelum Bot API, `wa_send_log`, dan update status melalui `sendWaReportSchedule()`.
  - Token hanya disediakan bagi role yang memiliki create/edit/delete; hidden field ditambahkan ke empat form dan otomatis ikut AJAX `new FormData(form)` untuk `send_now`.
  - Smoke DB/network/bootstrap-free menguji ordering, side-effect boundary, isolation Batch 37–39, rendering role, empat form, dan FormData caller.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - `whatsapp_report_schedule_mutation_csrf_smoke.php`: PASS 140 checks.
  - Regresi `whatsapp_engine_control_csrf_smoke.php`: PASS 160; `whatsapp_env_save_csrf_smoke.php`: PASS 70; `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_settings_secret_boundary_smoke.php`: PASS 20; `whatsapp_template_group_mutation_csrf_smoke.php`: PASS 151; `whatsapp_template_group_action_rbac_smoke.php`: PASS 63.
  - `git diff --check` tracked dan file smoke baru: bersih.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi ordering empat action, guard sebelum `sendWaReportSchedule()`, strict scoped token, form/FormData, dan tidak ada perubahan di luar scope.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session, database, dan Bot API nyata; smoke menggunakan doubles.
  - Token session-scoped bukan one-time; global CSRF tetap disabled dan writer WhatsApp lain di luar batch belum seluruhnya dikunci.
- Batch berikutnya: auditor mengarahkan scoped CSRF untuk mutation WhatsApp broadcast yang menulis queue/status atau dapat memicu pengiriman.

## Batch 41 — P0 scoped CSRF mutasi dan dispatch WhatsApp broadcast

- Waktu: 2026-09-02 21:46 WIB.
- Prioritas: P0 security — `broadcast_delete()` masih dapat dipanggil melalui GET, `broadcast_deactivate()` belum memiliki CSRF scoped, dan `api_broadcast_start()`/bulk manual masih menggunakan dispatch GET tanpa token saat CSRF global disabled. Jalur ini dapat mengubah queue/status/log atau memicu pengiriman eksternal.
- Ringkasan diskusi auditor/fixer: auditor memilih satu kontrak scoped untuk mutation broadcast dan dispatch. Fixer menerapkan guard setelah RBAC dan sebelum payload, target DB, upload, flash, lock, atau Bot API; delete caller diubah menjadi POST form; dua caller dispatch diubah menjadi POST + dedicated header. Sesi fixer sempat tersendat saat mencetak diff besar, sehingga smoke test baru diselesaikan secara terkontrol oleh main agent tanpa memperluas production scope. Auditor kemudian melakukan review read-only final atas diff dan hasil validasi.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/broadcast.php`
  - `application/views/wa/broadcast_form.php`
  - `application/views/wa/broadcast_detail.php`
  - `application/views/wa/manual.php`
  - `tools/tests/whatsapp_broadcast_mutation_csrf_smoke.php` (baru; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope session/form/header `wa_broadcast_mutation_csrf`, token `random_bytes(32)` lowercase 64-hex, session-local, dan verifikasi `hash_equals`.
  - Mengunci `broadcast_create`, `broadcast_edit`, `broadcast_delete`, `broadcast_deactivate`, serta hanya branch `delivery_mode=bulk` pada `manual()` dengan POST + scoped form token; non-POST ditolak 405 dan token invalid ditolak 403.
  - `api_broadcast_start()` menjadi POST-only dan menerima token hanya dari header kanonis `X-Wa-Broadcast-Csrf`; query retry tetap dipertahankan dan tidak digunakan sebagai token fallback.
  - Token dirender hanya pada view writer yang relevan; delete list/detail menjadi form POST; broadcast detail dan manual bulk mengirim POST + header token.
  - Initial RBAC dispatch dan pemeriksaan izin berdasarkan tipe queue tetap dipertahankan; tidak ada perubahan route, schema, seed, matriks permission, circuit breaker, queue model, Bot API, atau kebijakan upload.
- Validasi yang dijalankan:
  - `php -l` controller, empat view, dan smoke baru: lulus.
  - `php tools/tests/whatsapp_broadcast_mutation_csrf_smoke.php`: PASS 157 checks, DB/bootstrap/network/secret-free.
  - Regresi lulus: report schedule 140; engine control 160; env save 70; engine log boundary 23; settings secret boundary 20; template/group mutation 151; template/group action RBAC 63.
  - `git diff --check` tracked Batch 41 scope dan smoke baru: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi urutan RBAC -> CSRF -> dependency, header-only dispatch, seluruh caller POST, delete tanpa GET anchor, dan smoke test yang bermakna serta tidak menyentuh runtime sensitif.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session, database, proxy header, dan Bot API nyata; jalur dispatch valid tidak dieksekusi melewati circuit breaker outbound yang sedang disabled.
  - Token masih session-scoped, bukan one-time; global CSRF tetap disabled dan writer WhatsApp lain di luar batch masih menjadi pekerjaan berikutnya.
  - Smoke baru masih untracked sampai masuk change/release set.
- Batch berikutnya: auditor diminta memilih prioritas P0 berikutnya; kandidat terdekat adalah scoped CSRF untuk `api_log_retry()` dan caller-nya setelah audit action/RBAC.

## Batch 42 — P0 scoped CSRF dan least-privilege RBAC `api_log_retry()`

- Waktu: 2026-09-02 22:26 WIB.
- Prioritas: P0 security — `api_log_retry()` sebelumnya menerima GET tanpa token dan `wa.log:view` dapat menjadi jalur retry outbound; tiga caller first-party juga belum mengirim CSRF scoped. Risiko terbesar adalah retry pesan GROUP oleh pemegang izin baca log serta CSRF terhadap pengiriman eksternal.
- Ringkasan diskusi auditor/fixer: auditor memilih penguncian endpoint retry dengan POST/header-only dan pemisahan izin aksi berdasarkan sumber log. Fixer menerapkan token session-scoped terpisah, exact RBAC (`wa.group:create` untuk GROUP, `wa.manual:create` untuk personal, `wa.broadcast:edit` untuk membuka detail broadcast), serta menghapus capability GROUP yang tidak terpakai dari view manual. Auditor awal meminta behavior smoke yang lebih kuat; fixer menambahkannya. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/dashboard.php`
  - `application/views/wa/log.php`
  - `application/views/wa/manual.php`
  - `tools/tests/whatsapp_log_retry_csrf_rbac_smoke.php` (tracked/staged sebagai file baru; wajib masuk change/release set)
- Perubahan utama:
  - Menambahkan scope sesi `wa_log_retry_csrf` dengan token `random_bytes(32)`, lowercase 64-hex, header kanonis `X-Wa-Log-Retry-Csrf`, dan verifikasi `hash_equals`; query, form, JSON, serta raw body tidak menjadi fallback.
  - `api_log_retry()` kini POST-only, menjalankan coarse writer gate lalu CSRF sebelum baca DB/rekonstruksi pesan/circuit breaker/Bot API/log writer.
  - `wa.log:view` tidak lagi memberi hak retry; klasifikasi BROADCAST dilakukan sebelum `group_jid`, broadcast hanya membuka detail, GROUP dan personal memakai permission terpisah, dan GROUP tetap tidak bergantung pada personal circuit breaker.
  - Dashboard, log, dan manual mengirim POST + `same-origin` + dedicated header; tombol/token hanya dirender untuk capability sumber yang sesuai. `manual()` hanya menerbitkan token pada tab single bagi `wa.manual:create`.
- Validasi yang dijalankan:
  - `php -l` controller, tiga view, dan smoke baru: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 158 checks.
  - Regresi lulus: broadcast 157; report schedule 140; template/group mutation 151; template/group action RBAC 63; engine control 160; env save 70; engine log boundary 23; settings secret boundary 20.
  - `git diff --check` dan `git diff --cached --check`: lulus; hanya smoke baru yang staged, file lain staged tidak ada.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi behavior cases benar-benar dieksekusi, urutan guard, klasifikasi, RBAC least-privilege, ketiga caller, dan tracking smoke.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session, database, proxy header, dan Bot API nyata; khususnya retry GROUP dan redirect detail broadcast masih perlu probe staging aman.
  - Token masih session-scoped, bukan one-time; global CSRF tetap disabled dan writer WhatsApp lain di luar batch masih perlu dikunci.
- Batch berikutnya: auditor memilih prioritas P0 berikutnya, dengan kandidat terdekat direct manual single-send (`api_send_test`) setelah penguncian retry selesai; tetap tanpa License Hub.

## Batch 43 — P0 scoped CSRF writer `Whatsapp::settings()`

- Waktu: 2026-09-02 22:44 WIB.
- Prioritas: P0 security — form settings mengubah endpoint Bot, token write-only, dan path Node, tetapi sebelumnya hanya memiliki RBAC tanpa CSRF scoped. Nilai tersebut berada pada trust boundary request Bot dan engine.
- Ringkasan diskusi auditor/fixer: auditor memilih `settings()` sebagai prioritas yang lebih kritis daripada direct send karena konfigurasi ini memengaruhi jalur Bot/engine. Fixer menambahkan token session/form khusus, guard sebelum input bisnis dan DB, serta rendering editor-only. Validasi awal menemukan fixture secret-boundary lama belum mengirim token; fixer menambahkan sentinel CSRF sintetis pada fixture tanpa mengubah production behavior. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_settings_mutation_csrf_smoke.php` (tracked/staged sebagai file baru)
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php` (fixture regression; tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan scope sesi/form `wa_settings_mutation_csrf` dengan `random_bytes(32)`, lowercase 64-hex, dan `hash_equals`; token tidak memakai scope engine/env/log sebelumnya.
  - `settings()` mempertahankan view/edit RBAC, lalu menjalankan POST-only scoped form CSRF sebelum membaca field, mengecek schema, atau update `wa_session`; GET tetap read-only.
  - Token hanya dimintakan dan dirender bagi editor settings. Semantik token Bot kosong tetap mempertahankan nilai lama; validasi URL, `node_path`, `.env`, engine runner, route, dan schema tidak diubah.
  - Fixture secret-boundary diberi token sentinel agar regression test tetap menguji preserve-old-value dan generic flash setelah guard baru.
- Validasi yang dijalankan:
  - `php -l` controller, view, smoke baru, dan fixture: lulus.
  - Smoke settings mutation: PASS 60 checks; secret boundary: PASS 20; env-save: PASS 70; log-retry: PASS 158; engine-control: PASS 160; engine-log-boundary: PASS 23.
  - `git diff --check` dan `git diff --cached --check`: lulus. Cached scope Batch 43 tepat dua smoke file; perubahan production tetap unstaged karena worktree telah memiliki perubahan sebelumnya.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi urutan view RBAC -> edit RBAC -> CSRF -> input/schema/DB, token isolation, editor-only rendering, blank-token preservation, dan smoke behavior-level tanpa DB/network/bootstrap.
- Risiko sisa:
  - Belum ada integrasi browser/full-stack dengan session, DB staging, FPM, proxy header, atau Bot/engine nyata; validasi deployment tetap diperlukan.
  - Global CSRF tetap disabled dan writer lain di luar batch masih perlu hardening; token settings session-scoped bukan one-time.
- Batch berikutnya: auditor diminta memilih P0 berikutnya, kandidat terdekat branch single-send `Whatsapp::manual()` lalu `api_send_test()`; tetap tanpa License Hub.

## Batch 44 — P0 scoped CSRF single-send `Whatsapp::manual()`

- Waktu: 2026-09-02 22:57 WIB.
- Prioritas: P0 security — form single-send manual langsung memanggil Bot API dan menulis log, tetapi sebelumnya hanya jalur bulk yang memiliki CSRF scoped. Ini membuka risiko CSRF pengiriman personal pada sesi pengguna berizin.
- Ringkasan diskusi auditor/fixer: auditor memilih writer personal langsung setelah settings dikunci. Fixer menambahkan token single-send yang terpisah dari token broadcast, guard setelah RBAC dan `delivery_mode` tetapi sebelum circuit breaker, upload, parsing target, Bot API, dan log. Bulk tetap memakai kontrak broadcast. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/manual.php`
  - `tools/tests/whatsapp_manual_single_send_csrf_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan scope sesi/form `wa_manual_single_send_csrf` dengan `random_bytes(32)`, lowercase 64-hex, dan `hash_equals`; tidak memakai token broadcast/log/settings/env.
  - `manual()` mempertahankan `wa.manual:create`, membaca mode minimal, lalu mewajibkan POST + token single untuk mode non-bulk; mode bulk tetap memakai token broadcast.
  - Token single hanya dimintakan pada GET tab single bagi editor dan hidden field ditambahkan ke `waManualForm`; token single tidak diterima untuk bulk.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level tanpa DB/network/bootstrap/secret: PASS 69 checks, termasuk view-only, valid/malformed/mismatch/foreign token, method rejection, early side-effect boundary, valid mock sender sekali, serta bulk isolation.
  - Regresi lulus: broadcast 157; log-retry/RBAC 158; settings mutation 60; settings secret boundary 20; env-save 70; engine-control 160; engine-log-boundary 23.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi scoped token, guard sebelum seluruh side effect, editor-only mint, isolasi bulk, dan tidak ada regression route/schema/RBAC/global-CSRF.
- Risiko sisa:
  - Token masih reusable sampai rotasi/session expiry dan bukan kontrol idempotency; duplicate submit oleh user tetap perlu dikendalikan terpisah.
  - Belum ada browser/full-stack HTTP/FPM dengan session, upload, DB, dan Bot API nyata.
- Batch berikutnya: auditor memilih P0 scoped header-only CSRF untuk `api_send_test()`; tetap tanpa perubahan global CSRF atau License Hub.

## Batch 45 — P0 scoped header-only CSRF `Whatsapp::api_send_test()`

- Waktu: 2026-09-02 23:11 WIB.
- Prioritas: P0 security — endpoint JSON `api_send_test()` dapat mengirim pesan personal dan menulis send log, sementara CSRF global disabled dan jalur single-send form baru saja dikunci. Circuit breaker bukan pengganti request guard karena dapat dibuka kembali.
- Ringkasan diskusi auditor/fixer: auditor memilih endpoint test sebagai writer outbound terakhir pada alur settings. Fixer menambahkan token header-only session-scoped terpisah, memperbarui caller settings, dan membuat smoke behavior-level. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_api_send_test_csrf_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan scope `wa_send_test_csrf`, token `random_bytes(32)` lowercase 64-hex, dan `hash_equals`; tidak memakai token settings/manual/broadcast/log/env.
  - `api_send_test()` kini mempertahankan `wa.settings:edit`, lalu mewajibkan POST dan header `X-Wa-Send-Test-Csrf` sebelum circuit breaker, raw JSON, validasi, Bot API, atau log writer. Query/form/raw body tidak menjadi fallback.
  - Settings hanya merender token/kontrol test bagi editor; fetch mempertahankan payload/response dan memakai POST, `same-origin`, serta header dedicated.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level tanpa DB/network/bootstrap/secret: PASS 91 checks, termasuk RBAC, method/token rejection, header normalization, isolation, side-effect boundary, circuit breaker, dan valid mock sender sekali.
  - Regresi lulus: manual single 69; broadcast 157; log-retry 158; settings mutation 60; settings secret boundary 20; env-save 70; engine-control 160; engine-log-boundary 23.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi urutan RBAC -> POST/header-only CSRF -> breaker/body/Bot/log, editor-only UI, token isolation, dan tidak ada perubahan route/schema/global-CSRF/circuit-breaker.
- Risiko sisa:
  - Smoke masih memakai CI-compatible doubles; authenticated browser POST melalui web server/FastCGI/header path tetap perlu diuji saat deployment.
  - Token session-scoped reusable dan bukan idempotency control; personal outbound tetap circuit-breaker disabled.
- Batch berikutnya: auditor memilih audit P0 terpisah untuk `api_schedule_run()` yang masih memiliki kontrak token query/service; jangan mencampurkannya dengan CSRF browser batch ini.

## Batch 46 — P0 CLI-only `Whatsapp::api_schedule_run()`

- Waktu: 2026-09-02 23:24 WIB.
- Prioritas: P0 security — `api_schedule_run()` adalah writer outbound terjadwal tetapi sebelumnya menerima HTTP anonymous dengan token query/header yang berbagi token Bot WA. URL token berisiko masuk access log/history dan endpoint dapat dipicu dari luar sesi.
- Ringkasan diskusi auditor/fixer: auditor menetapkan scheduler sebagai service/cron boundary, bukan browser CSRF. Fixer menjadikan endpoint CLI-only, menghapus pembacaan token lama, memperketat anonymous allowlist hanya untuk mempertahankan `api_group_command`, dan mengubah dokumentasi cron ke CLI lokal. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/core/MY_Controller.php`
  - `application/views/wa/guide.php`
  - `application/views/wa/report_schedule.php`
  - `tools/tests/whatsapp_api_schedule_run_cli_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - `api_schedule_run()` memeriksa `is_cli_request()` sebagai guard pertama dan menolak HTTP dengan 404 sebelum scheduler/DB/Bot/log; query/header token dan dependensi token Bot bersama dihapus dari endpoint.
  - `api_schedule_run` dihapus dari anonymous HTTP exception; akses CLI tetap didukung dan `api_group_command` tidak berubah.
  - Panduan dan petunjuk schedule memakai `php index.php whatsapp api_schedule_run` dari root aplikasi tanpa URL atau token.
- Validasi yang dijalankan:
  - `php -l` controller, core, dua view, dan smoke: lulus.
  - Smoke behavior-level tanpa bootstrap/DB/network/secret: PASS 30 checks, mencakup HTTP GET/POST + token lama, CLI once/no-token, auth allowlist, dan side-effect boundary.
  - Regresi lulus: auth/division 39; report-schedule CSRF 140; api-send-test 91; manual single 69; log-retry 158.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi CLI-first guard, scheduler sekali pada CLI, anonymous `api_group_command` tetap, route unchanged, dan dokumentasi tanpa token URL.
- Risiko sisa:
  - Smoke memakai CI/DB doubles; cron production/staging tetap perlu dijalankan sekali dengan jadwal due yang terkontrol untuk memastikan konfigurasi PHP/cron.
  - Atomic claim/lease dan actor/audit service scheduler belum diubah; paralel cron masih menjadi batch integrity terpisah.
- Batch berikutnya: auditor memilih P0 integrity atomic claim/lease pada `runDueWaReportSchedules()`; jangan mencampurkannya dengan kontrak CLI batch ini.

## Batch 47 — P0 atomic claim/lease scheduler laporan WA

- Waktu: 2026-09-02 23:39 WIB.
- Prioritas: P0 integrity — dua proses cron dapat memilih jadwal due yang sama sebelum `last_run_at` diperbarui setelah Bot API, sehingga satu jadwal berpotensi mengirim laporan ganda.
- Ringkasan diskusi auditor/fixer: auditor menetapkan claim/lease atomik sebagai batch terpisah setelah trigger HTTP ditutup. Karena `last_run_at` tidak cukup untuk membuktikan owner, fixer menambah token claim dan waktu lease nullable, mengunci klaim dengan conditional update, dan membatasi finalisasi pada token owner. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `sql/2026-09-02a_wa_report_schedule_claim_lease.sql` (migration idempoten)
  - `sql/2026-08-15b_wa_report_schedule.sql`
  - `sql/2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql`
  - `tools/tests/whatsapp_report_schedule_claim_lease_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan `run_claim_token CHAR(32) NULL` dan `run_claimed_at DATETIME NULL`; migration harus dijalankan setelah base table dan menggunakan `ADD COLUMN IF NOT EXISTS`.
  - Runner hanya boleh mengirim setelah conditional claim dengan syarat aktif, due, belum terkirim hari ini, retry eligible, dan claim kosong/kedaluwarsa menghasilkan `affected_rows() === 1`.
  - Finalisasi success/failure memakai `id + run_claim_token`, mengosongkan kedua kolom claim, dan menolak stale owner yang lease-nya telah direbut.
  - Lease/retry 10 menit, CLI scheduler Batch 46, dan manual `send_now` tetap dipertahankan; tidak ada perubahan route/global auth atau scheduler content.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - Smoke behavior-level in-memory: PASS 27 checks, mencakup race dua runner, active/expired lease, stale finalizer, owner-only success/failure, retry failure, manual/CLI/SQL source checks.
  - Regresi lulus: CLI scheduler 30; report mutation 140; api send 91; manual single 69; log retry 158.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi predicate grouping, claim CAS, token-owner finalisasi, sinkronisasi tiga sumber schema, dan manual `send_now` claim-free.
- Risiko sisa:
  - Lease 10 menit memberi delivery at-least-once, bukan exactly-once: runner yang tersendat dapat selesai mengirim setelah lease direbut runner lain. Idempotency eksternal Bot diperlukan untuk menutup crash/timeout ambiguity.
  - Migration memerlukan DB yang mendukung `ADD COLUMN IF NOT EXISTS`; perlu diverifikasi pada versi DB staging/target.
- Batch berikutnya: auditor memilih Batch 48 untuk idempotency outbound dengan `schedule_id` + tanggal bisnis/receipt bila kontrak Bot mendukung; keputusan ini dipisahkan dari claim lease.

## Batch 48 — P1-02 Dashboard Component mismatch nilai FIFO

- Waktu: 2026-09-02 23:57 WIB.
- Prioritas: P1 integrity/observability — dashboard sebelumnya hanya menghitung mismatch kuantitas dan dapat menampilkan Clear meskipun `monthly_lot_value_gap` berbeda.
- Ringkasan diskusi auditor/fixer: auditor menolak idempotency Bot sebagai batch langsung karena kontrak `/internal/send-group` belum memiliki idempotency key/receipt. Fixer menghubungkan dashboard ke flag kanonis `Production_model`. Review pertama menemukan scope creep berupa limit 2000 menjadi 300; fixer memulihkan limit/argumen lama dan memperkuat smoke. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Dashboard.php`
  - `application/views/dashboard/index.php`
  - `tools/tests/dashboard_component_value_mismatch_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menggunakan `is_match`, `qty_is_match`, `has_lot_value_mismatch`, dan `monthly_lot_value_gap` dari model tanpa tolerance baru.
  - Menghitung total mismatch row satu kali serta breakdown qty/value; mismatch combined masuk kedua breakdown.
  - Menampilkan nominal gap, label “Qty sama, nilai FIFO berbeda”, dan link reconcile yang ada; Clear/Aman hanya ketika kedua breakdown nol.
  - Mempertahankan material summary dengan limit 2000, destination `ALL`, diagnostics `true`; component result tetap limit 2000 agar tidak mengubah coverage/perilaku lama.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke DB/network/bootstrap-free: PASS 19 checks, termasuk qty-only, value-only, combined, gap tepat tolerance, link/Clear, material contract, dan mismatch pada sorted row 305.
  - Regresi scheduler claim/lease: PASS 27 checks.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: awal `NEEDS_FIX` untuk limit/scope creep; setelah fixer memulihkan limit dan menambah coverage, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Smoke masih fake-model/isolated; production-like DB fixture >300 component rows tetap berguna sebagai integration coverage.
  - Batas kontrak lama 2000 baris tetap berlaku; repair enam mismatch nilai historis dan idempotency outbound tidak termasuk batch.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya; idempotency Bot tetap memerlukan kontrak provider/engine yang jelas.

## Batch 49 — P0 menonaktifkan mutasi rekening dari command grup WA

- Waktu: 2026-09-03 00:07 WIB.
- Prioritas: P0 integrity — callback grup dapat mem-posting `IN/OUT/TRANSFER` tanpa identitas pengirim WhatsApp yang terpetakan ke user Finance, RBAC Finance, approval, atau aktor audit; writer memakai actor `0`.
- Ringkasan diskusi auditor/fixer: auditor memilih deny-by-default untuk mutasi rekening grup sebagai kontrol aman yang tidak memerlukan kontrak Bot baru. Fixer menambahkan rejection setelah autentikasi/token dan mapping grup aktif tetapi sebelum parser, lookup rekening, load `Purchase_model`, query/write, atau audit mutation. Menu WA dan panduan menghapus instruksi input/transfer, sementara report `mutasi` read-only dipertahankan. Auditor menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/report_schedule.php`
  - `tools/tests/whatsapp_group_command_mutation_disabled_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Command ter-normalisasi `mutasi in`, `mutasi out`, dan `mutasi transfer` langsung menghasilkan pesan bahwa mutasi grup dinonaktifkan dan harus dilakukan melalui Finance.
  - Help/menu WA tidak lagi mengajarkan format posting mutasi; kartu `Mutasi Rekening` di report schedule tetap hanya berisi query laporan hari ini/kemarin.
  - Tidak mengubah kontrak token/HTTP callback, parser/helper lama, schema, RBAC matrix, data, atau Bot engine.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 18 checks untuk tiga mode mutasi, prefix/spasi/case normalization, no mutation dependency/write, report read-only, serta menu/UI copy.
  - Regresi lulus: scheduler CLI 30; claim/lease 27; report mutation CSRF 140; api send 91; manual 69; log retry 158.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi active-group mapping/token tetap dipertahankan, guard sebelum writer, report mutasi read-only, dan tidak ada perluasan scope.
- Risiko sisa:
  - Helper parser mutasi lama tetap ada sebagai dead code private yang tidak terjangkau dari endpoint grup; perlu dihapus/retire dalam cleanup terpisah agar tidak tidak sengaja diaktifkan kembali.
  - `api_group_command` masih memakai token query/shared Bot token dan belum POST/header-only; itu menjadi batch P0 berikutnya.
- Batch berikutnya: auditor memilih hardening `api_group_command`—POST-only, token header-only, serta kontrak credential service terpisah—tanpa mencampurkan desain pairing sender/RBAC.

## Batch 50 — P0 service-auth callback `wa-engine -> Finance`

- Waktu: 2026-09-03 00:30 WIB.
- Prioritas: P0 security — `api_group_command` adalah endpoint anonymous service callback yang sebelumnya menerima token query/header legacy dan memakai shared `wa_session.bot_api_token`; query token dapat masuk telemetry/proxy log dan credential tidak dapat dirotasi per arah.
- Ringkasan diskusi auditor/fixer: auditor menetapkan kontrak service-to-service terpisah, bukan browser CSRF. Fixer membuat endpoint POST-only, header-only, fail-closed dari `FINANCE_WA_ENGINE_COMMAND_TOKEN`, memperketat anonymous bypass, mengubah caller Node dan dokumentasi provisioning. Review pertama menemukan redirect Node dapat membocorkan header credential; fixer menambahkan `redirect: 'error'` dan regression simulation. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/core/MY_Controller.php`
  - `wa-engine/index.js`
  - `wa-engine/.env.example` (template non-secret)
  - `docs/wa_group_command_service_auth_runbook.md` (runbook provisioning/cutover non-secret)
  - `tools/tests/whatsapp_group_command_service_auth_smoke.php`
  - `tools/tests/wa_engine_group_command_service_auth_smoke.js`
  - `tools/tests/whatsapp_group_command_mutation_disabled_smoke.php`
  - `tools/tests/whatsapp_api_schedule_run_cli_smoke.php`
- Perubahan utama:
  - Finance menerima hanya POST + `X-Finance-Group-Command-Token`, expected token dari env `FINANCE_WA_ENGINE_COMMAND_TOKEN`, nonempty fail-closed; query token dan `X-Sync-Token` ditolak sebelum raw body/group DB/report.
  - Anonymous `MY_Controller` bypass hanya untuk kombinasi tepat WhatsApp/`api_group_command` + POST; CLI dan kontrak endpoint lain dipertahankan.
  - `wa-engine` memakai credential env khusus, exact `FINANCE_COMMAND_URL`, JSON payload, header baru saja, tanpa token URL/legacy header; redirect fetch disetel `error` agar credential tidak diteruskan lintas-origin.
  - Runbook mewajibkan provision secret process-only di luar web root untuk PHP-FPM dan wa-engine, coordinated restart/cutover, serta URL final tanpa redirect; nilai secret aktual tidak dibuat/diubah.
  - Fixture scheduler lama disesuaikan agar preserved anonymous `api_group_command` case memakai POST, sementara GET denial diuji oleh smoke service-auth.
- Validasi yang dijalankan:
  - `php -l` seluruh PHP terkait: lulus; `node --check` engine/smoke: lulus.
  - Service-auth PHP: PASS 31; Node caller/redirect: PASS 16; mutation deny: PASS 18; scheduler CLI: PASS 30.
  - Regresi lulus: claim/lease 27; report mutation 140; api send 91; manual 69; log retry 158; settings 60/20; env 70; engine control 160; engine log 23.
  - `composer validate --no-check-publish`: composer.json valid dengan deprecation warnings baseline; `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: awal `NEEDS_FIX` untuk redirect credential; setelah fixer menambah `redirect: 'error'` dan smoke 16 checks, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Callback fail-closed sampai env token yang sama diprovision pada PHP-FPM dan wa-engine; cutover/restart serta satu health-check authenticated perlu dilakukan operasional.
  - Belum ada E2E FastCGI/reverse proxy/active group; HMAC/timestamp/nonce, sender pairing/RBAC, rate limit, dan replay protection di luar batch.
  - Arah Finance -> engine `callBotApi()` masih kontrak shared `WA_TOKEN`/`X-Sync-Token` dan sengaja belum diubah.
- Batch berikutnya: auditor memilih hardening arah Finance -> engine (`callBotApi()` dan internal engine API) dengan header-only credential terpisah, lalu signed request bila diperlukan; tetap tanpa License Hub.

## Batch 51 — P1 SSRF dan egress boundary `callBotApi()` WhatsApp

- Waktu: 2026-09-03 00:48 WIB.
- Prioritas: P1 security — URL Bot API tersimpan dan konfigurasi settings perlu dibatasi agar tidak menjadi SSRF/egress path, termasuk redirect dan proxy environment yang dapat menerima credential outbound.
- Ringkasan diskusi auditor/fixer: auditor menetapkan batch sempit tanpa migrasi credential. Fixer menambahkan validator URL loopback ber-port, validasi settings sebelum schema/DB, validasi stored URL sebelum `curl_init`, dan mematikan redirect. Review pertama meminta hardening tambahan karena cURL dapat menghormati proxy environment; fixer menambahkan `CURLOPT_PROXY => ''` dan assertion smoke. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `tools/tests/whatsapp_settings_mutation_csrf_smoke.php`
- Perubahan utama:
  - Hanya menerima `http://127.0.0.1:<port>` atau `http://localhost:<port>`, port 1–65535; `localhost` dinormalisasi ke `127.0.0.1`.
  - URL invalid pada settings ditolak sebelum pemeriksaan schema maupun update DB; URL tersimpan invalid ditolak sebelum `curl_init`.
  - `CURLOPT_FOLLOWLOCATION => false`, `CURLOPT_MAXREDIRS => 0`, dan `CURLOPT_PROXY => ''` mencegah redirect/proxy egress pada call ke engine.
  - Endpoint literal `/internal/*`, method, timeout, header `X-Sync-Token`, dan token query lama dipertahankan; migrasi credential Finance → engine tidak termasuk batch.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus; `git diff --check` dan `git diff --cached --check`: lulus.
  - Settings boundary smoke: PASS 123 checks, termasuk URL matrix, invalid-before-DB/cURL, no-follow, no-proxy, dan kontrak secret lama.
  - Regresi lulus: service-auth 31; Node service-auth 16; mutation-disabled 18; scheduler CLI 30; claim/lease 27; report schedule 140; API send 91; manual 69; log retry 158; settings-secret 20; env-save 70; engine-control 160; engine-log 23; broadcast 157; template/group CSRF 151; template/group RBAC 63.
  - `composer validate --no-check-publish`: `composer.json` valid dengan deprecation warning baseline.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi parser loopback/port, urutan validasi, no-follow, no-proxy, dan preservation kontrak outbound.
- Risiko sisa:
  - Assertion proxy masih source-level; integration test dengan proxy sungguhan belum ada.
  - Proses lokal berprivilege yang mengambil alih port loopback masih dapat menerima token; kontrol proses/host berada di luar batch.
  - Arah Finance → engine masih memakai `WA_TOKEN`/`X-Sync-Token`; credential header-only terpisah menjadi batch berikutnya.
- Batch berikutnya: auditor memilih hardening credential arah Finance → engine (`callBotApi()` dan internal engine API), dimulai dari kontrak header-only terpisah dan tanpa License Hub.

## Batch 52 — P1 service-auth API internal `Finance → wa-engine`

- Waktu: 2026-09-03 00:48–01:12 WIB.
- Prioritas: P1 security/readiness — `callBotApi()` masih mengirim `wa_session.bot_api_token` pada query `?token=` dan header `X-Sync-Token`, sementara `wa-engine` menerima credential legacy/fallback development. Credential di URL berisiko masuk telemetry/log dan shared token menyulitkan rotasi per arah.
- Ringkasan diskusi auditor/fixer: auditor menetapkan migrasi header-only ke credential process-only terpisah sebagai batch kecil yang siap dikerjakan. Fixer memindahkan Finance → engine ke `FINANCE_WA_ENGINE_API_TOKEN`/`X-Finance-Wa-Engine-Token`, mengunci gate `/internal/*` engine fail-closed, mengecualikan credential dari `.env` web-root dan launcher PHP, serta menghapus editor/status legacy dari UI/panduan. Review pertama menemukan runbook belum mencakup CLI/cron dan runbook Batch 50 masih menyebut `WA_TOKEN`; fixer memperbaiki dua runbook, menambah assertion smoke, lalu membersihkan blank line EOF. Auditor final menyatakan PASS.
- File berubah (14 file unik):
  - `application/controllers/Whatsapp.php`
  - `wa-engine/index.js`
  - `wa-engine/.env.example`
  - `application/views/wa/settings.php`
  - `application/views/wa/guide.php`
  - `docs/wa_engine_internal_service_auth_runbook.md`
  - `docs/wa_group_command_service_auth_runbook.md`
  - `tools/tests/whatsapp_engine_api_service_auth_smoke.php`
  - `tools/tests/wa_engine_internal_service_auth_smoke.js`
  - `tools/tests/wa_engine_group_command_service_auth_smoke.js`
  - `tools/tests/whatsapp_group_command_service_auth_smoke.php`
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php`
  - `tools/tests/whatsapp_engine_log_boundary_smoke.php`
  - `tools/tests/whatsapp_settings_mutation_csrf_smoke.php`
- Perubahan utama:
  - Finance membaca credential baru dari process environment, fail-closed sebelum session/DB/cURL; request ke engine hanya memakai header `X-Finance-Wa-Engine-Token`, tanpa token query atau `X-Sync-Token`.
  - Gate Node untuk seluruh `/internal/*` hanya menerima header dedicated yang tepat; query token, header legacy, credential kosong/salah, dan fallback `local-dev-token` ditolak.
  - Loader Node dan `buildEnvString()` PHP tidak mengimpor `FINANCE_WA_ENGINE_API_TOKEN` maupun `FINANCE_WA_ENGINE_COMMAND_TOKEN` dari `.env` di bawah web root; nilai legacy DB/.env tidak dihapus/ditimpa.
  - UI/panduan tidak lagi mengelola atau menginstruksikan `bot_api_token`/`WA_TOKEN`; endpoint, method, payload, timeout, loopback URL, no-follow, dan no-proxy dipertahankan.
  - Runbook mendokumentasikan provisioning process-only untuk PHP/FPM, scheduler CLI/cron, dan wa-engine; callback Batch 50 `FINANCE_WA_ENGINE_COMMAND_TOKEN` tetap terpisah.
- Validasi yang dijalankan:
  - PHP lint 8/8 dan Node `--check` 3/3: lulus.
  - Smoke Batch 52: PHP 17 checks; Node 21 checks.
  - Regresi: callback Batch 50 PHP 31; Node 16; settings CSRF 123; settings secret 20; engine log 24; env-save 70; engine-control 160; API send 91; manual 69; report schedule 140; scheduler CLI 30.
  - `composer validate --no-check-publish`: `composer.json` valid dengan deprecation warning baseline; `git diff --check` dan `git diff --cached --check`: lulus.
  - Seluruh smoke bersifat DB/network/bootstrap-free dan tidak mencetak credential.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` untuk coverage CLI/cron dan kontradiksi `WA_TOKEN` pada runbook; setelah dokumentasi + assertion diperbaiki, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Cutover fail-closed sampai credential yang sama diprovision secara process-only pada PHP/FPM, CLI/cron, dan wa-engine; ketiganya perlu reload/restart terkoordinasi serta health check terkontrol tanpa mengekspos secret.
  - Smoke menguji fixture fungsi/gate, bukan proses PHP-FPM/cron/Node production nyata; rotasi tidak mendukung overlap token.
  - Legacy `wa_session.bot_api_token` dan `.env` `WA_TOKEN` dipertahankan untuk rollback eksplisit, tetapi tidak lagi dipakai oleh API internal; pembersihan/retensi secret lama menjadi pekerjaan operasional terpisah.
- Batch berikutnya: minta auditor memilih prioritas tertinggi berikutnya dari P0/P1 audit; jangan melakukan cutover secret atau membuat License Hub tanpa kebutuhan eksplisit.

## Batch 53.1 — P0 login throttle atomik dan session-audit fail-closed

- Waktu: 2026-09-03 01:12–01:42 WIB.
- Prioritas: P0 authentication integrity — review awal Batch 53 menemukan count→bcrypt→insert tidak atomik terhadap request paralel, timing blocked/unknown dapat membocorkan status akun, dan session authenticated dapat tersimpan walau `auth_session_log` gagal.
- Ringkasan diskusi auditor/fixer: auditor mensyaratkan serialisasi per IP dan akun, common response-time floor, serta audit session sebelum session write. Fixer menambahkan advisory lock MySQL dengan urutan IP-hash → user-id dan release terbalik, menjaga lock sampai `log_login()` selesai; menambahkan floor failure 400 ms yang dibatasi 500 ms tanpa bcrypt untuk blocked; memeriksa insert/id audit dan menangani finalisasi dengan pesan maintenance generik. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Auth.php`
  - `application/models/Auth_model.php`
  - `tools/tests/auth_login_throttle_smoke.php`
- Perubahan utama:
  - Login web menolak non-POST sebelum lookup/throttle/bcrypt; jalur mobile dua-argumen tetap tidak memakai throttle web.
  - Request web dikunci per IP canonical/hash lalu akun, meliputi count, bcrypt, insert failure, persistence success, dan `auth_session_log`; kegagalan acquire/release fail-closed.
  - Unknown user memakai dummy bcrypt; blocked tidak menjalankan bcrypt; failure responses memakai floor waktu bersama dan pesan generik.
  - `auth_session_log` berhasil ditulis dan menghasilkan ID sebelum `auth_user`/`session_log_id` masuk session; kegagalan DB tidak membuat session authenticated.
  - Migration `auth_login_failure` dari Batch 53 tetap belum dieksekusi/diubah.
- Validasi yang dijalankan:
  - PHP lint 3/3: lulus.
  - Smoke login throttle: PASS 52 checks; auth division scope 39; inactive-role permission 19; POS mobile authorization lulus.
  - `composer validate --no-check-publish`: valid dengan deprecation warning baseline; `git diff --check` dan `git diff --cached --check`: lulus.
  - Smoke DB/network/bootstrap-free; SQL tidak dijalankan.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Contention dua koneksi MySQL nyata dan eksekusi migration belum diuji; deployment harus memastikan `GET_LOCK()`/`RELEASE_LOCK()` tersedia dan koneksi tetap sama.
  - Presisi timestamp `auth_login_failure.failed_at` vs `auth_session_log.login_at` masih menjadi Batch 53.2; proxy/IP bucket dan kebijakan retensi log perlu verifikasi operasional.
- Batch berikutnya: Batch 53.2 menyelaraskan presisi timestamp reset logis dan memperkuat migration compatibility, tanpa mengubah batas throttle atau flow login.

## Batch 53.2 — P0 presisi timestamp reset login dan cleanup migration

- Waktu: 2026-09-03 01:42–02:03 WIB.
- Prioritas: P0 integrity — `auth_login_failure.failed_at` sudah `DATETIME(6)`, sedangkan `auth_session_log.login_at` legacy masih presisi detik; failure dan successful login pada detik yang sama dapat salah dihitung setelah logical reset.
- Ringkasan diskusi auditor/fixer: auditor menetapkan batch sempit untuk microsecond boundary tanpa mengubah limit/lock. Fixer mengubah window/cutoff dan `log_login()` ke enam digit mikrodetik serta menambah migration guard yang hanya ALTER bila schema valid belum `DATETIME(6)`. Review pertama menemukan temporary procedure dapat tertinggal setelah `SIGNAL`; fixer menambah wrapper cleanup dengan `EXIT` trap, simulasi fake client, dan assertion safety. Auditor final menyatakan PASS.
- File berubah:
  - `application/models/Auth_model.php`
  - `sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql`
  - `tools/db/apply_auth_session_log_login_at_microsecond.sh` (mode executable 0755)
  - `tools/tests/auth_login_throttle_smoke.php`
- Perubahan utama:
  - Rolling window IP/account memakai `Y-m-d H:i:s.u`; timestamp login sukses memakai microseconds; legacy success row tanpa fraction dinormalisasi ke `.000000`; `failed_at > cutoff` dipertahankan.
  - Migration `53b` mem-preflight table/column/type/presisi/nullability melalui `information_schema`, SIGNAL pada mismatch, dan hanya mengubah `auth_session_log.login_at` menjadi `DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)`; tanpa DML/backfill/replacement/TIMESTAMP conversion.
  - Wrapper menjalankan file SQL utuh melalui client delimiter-aware, menerima konfigurasi client/option-file tanpa password CLI, selalu mencoba `DROP PROCEDURE IF EXISTS` pada EXIT trap, mempertahankan exit failure migration, dan mengembalikan nonzero bila cleanup gagal.
  - Smoke fake-client memverifikasi same-second boundary, payload enam digit, DDL-only/idempotent guard, migration failure cleanup, cleanup failure status, whole-file input, dan tidak ada secret/arg password.
- Validasi yang dijalankan:
  - `php -l` Auth_model dan smoke: lulus; `bash -n` wrapper: lulus.
  - Auth throttle smoke: PASS 74 checks; auth division 39; POS mobile authorization lulus.
  - `composer validate --no-check-publish`: valid dengan deprecation warning baseline; `git diff --check` dan `git diff --cached --check`: lulus.
  - Tidak menjalankan SQL ke DB nyata, tidak membuka network, dan tidak membaca/mengubah credential/runtime.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` karena procedure dapat tertinggal pada preflight `SIGNAL`; setelah wrapper + fake-client regression, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada eksekusi MySQL/MariaDB nyata pada clone non-produksi; perlu verifikasi preflight failure cleanup, successful ALTER, metadata default, DDL lock, dan dukungan `SIGNAL`/fractional datetime.
  - EXIT trap tidak dapat berjalan pada `SIGKILL`/host mati; bila koneksi/privilege cleanup gagal, wrapper melaporkan nonzero tetapi routine mungkin perlu dibersihkan manual.
  - Kebijakan retensi/monitoring `auth_login_failure` dan pengaturan proxy tepercaya/IP bucket masih operasional.
- Batch berikutnya: minta auditor memilih prioritas P0/P1 berikutnya dari audit total; migration runner global, role baseline, integrity stok, dan E2E DB tetap belum dijadikan batch tanpa desain/fixture yang aman.

## Batch 54 — P0 CSRF scoped dan POST-only writer Formula Component

- Waktu: 2026-09-03 02:17 WIB.
- Prioritas: P0 security/RBAC — global CSRF masih nonaktif, sementara `component_formula_save`, `component_formula_save_bulk`, dan `component_formula_delete` dapat mengubah formula yang menjadi dasar HPP, kebutuhan bahan, produksi, dan availability POS.
- Ringkasan diskusi auditor/fixer: auditor memilih guard scoped sebagai batch kecil tanpa schema/data change. Fixer mengubah halaman edit agar membutuhkan permission `edit`, menambahkan token session-scoped 64-hex, serta memaksa RBAC → POST → CSRF header sebelum payload/model pada ketiga writer. View editor mengirim header baru untuk save-bulk. Auditor menjalankan review ulang dan menyatakan PASS; catatan non-blocking hanya memperluas matriks smoke create-only/update dan edit-only/create.
- File berubah:
  - `application/controllers/Production.php`
  - `application/views/production/component_formula_edit.php`
  - `tools/tests/production_component_formula_mutation_csrf_smoke.php`
- Perubahan utama:
  - `component_formula_edit()` sekarang mewajibkan `production.component.formula.index:edit`.
  - Ketiga writer hanya menerima POST dan token melalui header canonical `X-Production-Component-Formula-Csrf`; token dibuat dengan `random_bytes(32)`, divalidasi strict, dan dibandingkan memakai `hash_equals` terhadap session key scoped.
  - Token form/query/JSON/raw body tidak diterima sebagai fallback; request invalid menghasilkan JSON 405/403 sebelum payload/model/DB.
  - Save tunggal tetap mempertahankan action `create` untuk insert dan `edit` untuk update; precheck memastikan caller memiliki salah satunya sebelum payload dibaca.
  - Editor merender token scoped dan mengirimkannya pada request `save-bulk` bersama header JSON/XHR yang sudah ada.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 191 checks, mencakup RBAC, method, missing/wrong/cross-scope token, fallback rejection, guard ordering, dan model reachability.
  - `git diff --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Caller direct/eksternal yang belum ditemukan harus mengirim header scoped baru; caller internal save-bulk sudah diperbarui.
  - Token mengikuti lifecycle session sehingga halaman perlu dimuat ulang setelah session berubah.
  - Jalur formula legacy `Master_relation` dan writer komersialisasi lain masih perlu audit/batch terpisah.
- Batch berikutnya: minta auditor memilih prioritas tertinggi P0/P1 berikutnya; fokus kandidat adalah writer formula legacy atau jalur stok/HPP yang masih belum memiliki guard setara, tanpa memperluas scope ke License Hub.

## Batch 55 — P0 CSRF scoped dan POST-only writer Resep Produk legacy

- Waktu: 2026-09-03 02:29 WIB.
- Prioritas: P0 security/RBAC — jalur legacy `Master_relation` masih memiliki writer resep yang menghapus–menulis ulang `mst_product_recipe`, sementara delete UI masih berupa anchor GET. Perubahan resep berdampak langsung pada HPP, kebutuhan bahan, produksi, dan availability POS.
- Ringkasan diskusi auditor/fixer: auditor memilih empat writer sebagai batch terkecil: `product_recipe_bulk_save`, `product_recipe_store`, `product_recipe_update`, dan `product_recipe_delete`. Fixer mempertahankan RBAC kanonis yang sudah ada, lalu menerapkan urutan RBAC → POST-only → CSRF form/session → parent/payload/model/DB. Token hanya disediakan pada render detail/bulk/create/edit; delete UI diubah menjadi form POST. Auditor final memisahkan delta ini dari perubahan RBAC pre-existing dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/relation_product_recipe_edit.php`
  - `application/views/master/relation_form.php`
  - `application/views/master/relation_list.php`
  - `tools/tests/master_relation_product_recipe_mutation_csrf_smoke.php`
- Perubahan utama:
  - Keempat writer wajib POST dan memvalidasi token session-scoped `master_relation_product_recipe_mutation_csrf` melalui field form khusus; token dibuat dengan `random_bytes(32)`, format 64 hex ketat, dan `hash_equals`.
  - Non-POST ditolak 405; token kosong/malformed/salah/lintas-scope ditolak 403 sebelum membaca parent, payload bisnis, model, atau DB. Header/query/raw JSON tidak diterima sebagai fallback.
  - Token disupply hanya setelah permission render recipe lolos; bulk form dan form create/update memiliki hidden field scoped.
  - Delete recipe pada daftar menjadi form POST dengan konfirmasi; delete formula component dan product-extra tetap tidak diubah.
- Validasi yang dijalankan:
  - `php -l` controller, tiga view, dan smoke: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 137 checks, mencakup RBAC, method, token missing/malformed/wrong/cross-scope, kanal alternatif, urutan guard, jalur valid, dan no-write boundary.
  - `git diff --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada probe browser dengan session CI nyata atau verifikasi transaksi DB nyata; itu diperlukan pada staging sebelum release.
  - Writer/delete formula component legacy dan product-extra masih belum mendapat CSRF batch ini; versioning/audit before-after resep juga belum ditambahkan.
  - Smoke belum mengeksekusi render sukses dengan token nyata; auditor menilai ini penyempurnaan non-blocking.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya, kemungkinan writer formula/extra legacy atau boundary stok/HPP; tetap satu batch kecil dan tanpa License Hub.

## Batch 56 — P0 CSRF scoped dan POST-only writer Formula Component legacy

- Waktu: 2026-09-03 02:41 WIB.
- Prioritas: P0 security/RBAC — endpoint legacy `Master_relation` untuk formula component masih hanya RBAC, sedangkan delete UI masih anchor GET. Writer langsung mengubah `mst_component_formula`, yang menjadi dasar HPP, kebutuhan bahan, produksi, dan availability POS; jalur ini melewati boundary modern Batch 54.
- Ringkasan diskusi auditor/fixer: auditor memilih `component_formula_store`, `component_formula_update`, dan `component_formula_delete` sebagai batch kecil tanpa schema/data change. Fixer menerapkan RBAC → POST-only → CSRF form/session sebelum parent, input, validation, model, atau DB; token disediakan hanya pada render formula yang terotorisasi. Delete formula di view menjadi form POST, sementara recipe dan product-extra dipisahkan. Validasi awal menemukan assertion smoke Batch 55 yang masih mengharapkan formula GET; fixer memperbaiki assertion agar formula POST dan product-extra tetap GET. Auditor final menerima remediasi delivery setelah smoke baru di-stage dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/relation_form.php`
  - `application/views/master/relation_list.php`
  - `tools/tests/master_relation_component_formula_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_product_recipe_mutation_csrf_smoke.php` (adaptasi regression assertion)
- Perubahan utama:
  - Tiga writer formula legacy hanya menerima POST; token `master_relation_component_formula_mutation_csrf` dibuat `random_bytes(32)`, strict lowercase 64-hex, dan dibandingkan dengan `hash_equals` terhadap session.
  - Guard hanya membaca field form scoped setelah method check; token header/query/raw JSON/field alternatif tidak diterima. Non-POST 405 dan token invalid 403 sebelum lookup/write.
  - Token formula di-render hanya pada detail/create/edit formula yang sudah lolos permission; generic form hanya menyisipkan token pada branch `component-formula`.
  - Delete formula pada relation list memakai POST form bertoken; recipe tetap POST dari Batch 55; product-extra tetap GET.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan dua smoke: lulus.
  - Formula legacy smoke: PASS 159 checks; recipe regression smoke: PASS 138 checks; Production formula regression: PASS 191 checks.
  - `git diff --check` dan `git diff --cached --check`: lulus setelah smoke baru ditambahkan ke index.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` hanya karena smoke baru untracked (delivery risk Tinggi); fixer men-stage file tanpa mengubah isinya dan mengulang validasi; auditor final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada probe browser dengan session CI nyata atau transaksi DB nyata; release staging tetap perlu uji GET→405, token kosong/salah→403, dan POST valid pada akun least-privilege.
  - Smoke memakai doubles sehingga tidak membuktikan deployment HTTP/session/database aktual.
  - Writer formula/extra lain di luar tiga endpoint ini, audit before-after/versioning resep/formula, dan hardening product-extra masih terbuka.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya dari writer product-extra/bundle atau jalur stok/HPP yang masih terbuka; tetap satu batch kecil dan tanpa License Hub.

## Batch 57 — P0 CSRF scoped dan POST-only writer Product Extra legacy

- Waktu: 2026-09-03 02:58 WIB.
- Prioritas: P0 security/RBAC — mapping `product-extra` menentukan opsi extra di kasir serta nilai/harga dan konsumsi stok terkait, tetapi `product_extra_store`/`product_extra_delete` belum memiliki boundary POST/CSRF dan delete masih GET.
- Ringkasan diskusi auditor/fixer: auditor memilih dua writer saja agar batch sempit. Fixer menambahkan token session/form product-extra terpisah dan menerapkan RBAC → POST-only → CSRF form sebelum lookup/input/DB. Token disediakan hanya pada detail/create product-extra; form dan relation-list memakai token sesuai domain. Dua smoke regression lama diadaptasi ketika assertion GET product-extra menjadi usang. Auditor pertama menemukan implementasi belum staged bersama test; fixer men-stage tiga implementation file dan smoke terkait tanpa mengubah isi, lalu auditor re-review final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/relation_form.php`
  - `application/views/master/relation_list.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_product_recipe_mutation_csrf_smoke.php` (adaptasi regression assertion)
  - `tools/tests/master_relation_component_formula_mutation_csrf_smoke.php` (adaptasi regression assertion)
- Perubahan utama:
  - `product_extra_store` dan `product_extra_delete` hanya menerima POST; token `master_relation_product_extra_mutation_csrf` dibuat dengan `random_bytes(32)`, format lowercase 64-hex ketat, dan `hash_equals` terhadap session.
  - Hanya form field scoped yang diterima; query/header/raw JSON/field alternatif tidak menjadi fallback. Non-POST 405 dan token invalid 403 sebelum parent/row lookup, input bisnis, model, atau DB.
  - Hidden token hanya berada di branch product-extra pada generic form; delete mapping product-extra menjadi confirmed POST form. Recipe dan component-formula tetap POST dengan token scoped masing-masing.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan smoke product-extra: lulus.
  - Smoke product-extra: PASS 141 checks; recipe regression: PASS 138; legacy component formula: PASS 159; modern Production formula: PASS 191.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Ketujuh berkas batch staged atomik; tidak ada commit atau staged deletion.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` karena implementasi unstaged sementara smoke staged (delivery risk Tinggi); fixer men-stage implementation bersama test tanpa mengubah isi; final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada browser probe dengan session CI nyata atau transaksi DB nyata; staging perlu uji create/delete mapping pada akun least-privilege dan cek opsi extra POS.
  - Global CSRF tetap nonaktif; guard ini masih route-scoped. Constraint schema `mst_product_extra_map` perlu diverifikasi terhadap drift sebelum release.
  - Extra-group AJAX, bundle, audit before-after/versioning, dan repair mismatch HPP belum dikerjakan.
- Batch berikutnya: auditor memilih P0/P1 berikutnya, kemungkinan extra-group/bundle writer atau jalur stok/HPP kritis; tetap satu batch kecil dan tanpa License Hub.

## Batch 58 — P0 CSRF scoped dan POST-only writer Bundle Produk

- Waktu: 2026-09-03 03:12 WIB.
- Prioritas: P0 security/RBAC — writer bundle produk masih menerima store/update/toggle tanpa CSRF scoped dan toggle masih GET. Bundle memengaruhi harga, komposisi, status aktif, katalog, dan perilaku POS.
- Ringkasan diskusi auditor/fixer: auditor memilih tiga writer bundle sebagai batch kecil tanpa schema/data change. Fixer menerapkan RBAC → POST-only → CSRF form/session sebelum normalisasi payload, load bundle, atau DB; token hanya disediakan pada hub/create/edit yang berizin, detail tetap token-free. Toggle hub diubah menjadi form POST bertoken. Auditor pertama menemukan implementasi belum staged bersama smoke; fixer men-stage tiga implementation file dan smoke tanpa perubahan isi, lalu auditor re-review final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/product_bundle_edit.php`
  - `application/views/master/product_bundle_hub.php`
  - `tools/tests/master_relation_product_bundle_mutation_csrf_smoke.php`
- Perubahan utama:
  - `product_bundle_store`, `product_bundle_update`, dan `product_bundle_toggle` hanya menerima POST; token `master_relation_product_bundle_mutation_csrf` dibuat dengan `random_bytes(32)`, lowercase 64-hex ketat, dan `hash_equals` terhadap session.
  - Token hanya diterima dari field form scoped; query/header/raw JSON/field alternatif tidak diterima. Non-POST 405 dan token invalid 403 sebelum akses bisnis.
  - Form editor memakai hidden token; hub memakai confirmed POST form untuk toggle; link detail/edit dipertahankan.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan smoke: lulus.
  - Bundle smoke: PASS 149 checks; regression Production formula 191, legacy formula 159, recipe 138, product-extra 141: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Empat artefak batch staged atomik; tidak ada commit/staged deletion.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` karena implementation unstaged sementara smoke staged (delivery risk Tinggi); fixer men-stage tiga implementation file bersama smoke tanpa mengubah isi; final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada browser probe dengan session CI nyata atau transaksi DB nyata; staging perlu uji create/update/toggle pada akun least-privilege dan verifikasi katalog/POS.
  - Global CSRF tetap nonaktif sehingga proteksi ini route-scoped. Constraint/foreign-key bundle perlu diverifikasi terhadap schema drift.
  - Audit integritas konsumsi bundle, snapshot/alokasi harga transaksi, extra-group AJAX, dan repair mismatch HPP masih terbuka.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya dari writer extra-group/AJAX atau integrity stok/HPP; tetap satu batch kecil dan tanpa License Hub.

## Batch 59 — P0 CSRF scoped dan POST-only writer AJAX Extra Group

- Waktu: 2026-09-03 03:28 WIB.
- Prioritas: P0 security/RBAC — dua endpoint AJAX replace-all untuk relasi extra-group masih menjalankan delete/reinsert pada `mst_extra_group_item` dan `mst_product_extra_map` setelah RBAC, tetapi tanpa POST-only dan CSRF scoped. Global CSRF CI3 tetap nonaktif.
- Ringkasan diskusi auditor/fixer: auditor memilih `extra_group_items_save_ajax` dan `extra_group_products_save_ajax` sebagai batch kecil tanpa schema/data change. Saat menelusuri caller aktual, ditemukan modal first-party di `master/index.php`; scope diperluas secara terarah agar token dibaca dari response GET editor, disimpan di state modal, save view-only dinonaktifkan, dan POST mengirim header canonical. Fixer menerapkan urutan RBAC → POST/header-CSRF guard → lookup/input/transaksi. Token read hanya diterbitkan untuk user dengan permission edit. Auditor final memeriksa controller, caller, smoke, serta staging dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/index.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - `extra_group_items_save_ajax` dan `extra_group_products_save_ajax` hanya menerima POST; token session scoped dibuat dengan `random_bytes(32)`, divalidasi lowercase 64-hex, dan dibandingkan dengan `hash_equals`.
  - Guard hanya menerima `X-Master-Extra-Group-Csrf`; query, form, raw body, JSON, dan header alternatif tidak menjadi fallback. Non-POST menghasilkan 405 dan token invalid/mismatch 403 sebelum lookup atau DB.
  - Response read GET editor memuat `mutation_csrf`; user view-only tidak menerima token dan tombol simpan disabled. Caller mengirim token hanya sebagai header pada POST.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Extra-group smoke: PASS 85 checks; regression bundle 149, product-extra 141, legacy component formula 159, recipe 138, modern Production formula 191: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Tiga artefak Batch 59 staged atomik; tidak ada route/schema baru, commit, atau staged deletion.
- Hasil review auditor: `PASS`; tidak ada finding blocking atau required fix. Auditor mengonfirmasi kedua writer menjalankan RBAC edit → POST/header-CSRF → DB dan tidak ada caller lain untuk dua route save tersebut.
- Risiko sisa:
  - Global CSRF tetap nonaktif sehingga proteksi ini route-scoped; belum ada browser probe dengan session CI nyata atau transaksi DB staging.
  - Writer extra-group non-AJAX dan writer AJAX/form lain di luar batch ini masih perlu audit/hardening; smoke behavior-level belum membuktikan deployment HTTP/session/database aktual.
- Batch berikutnya: auditor memilih writer mutasi P0 lain yang belum tercakup dan/atau memulai browser-level integration probe setelah rangkaian writer Master Relation selesai; tetap satu batch kecil dan tanpa License Hub.

## Batch 60 — P0 CSRF scoped dan POST-only checklist Extra Group non-AJAX

- Waktu: 2026-09-03 03:50 WIB.
- Prioritas: P0 security/RBAC — `extra_group_products_save` dan `extra_item_groups_save` adalah writer replace-all yang sebelumnya hanya memeriksa RBAC, menerima method apa pun, dan tanpa CSRF scoped. Karena itu GET berizin dapat menghapus seluruh mapping; dampaknya langsung ke katalog opsi extra dan perilaku POS.
- Ringkasan diskusi auditor/fixer: auditor memilih dua sisi checklist Extra Group sebagai satu batch atomik, melanjutkan hardening versi AJAX pada Batch 59. Fixer menambahkan token form/session terpisah, menerapkan urutan RBAC edit → POST-only → CSRF form-only → parent/payload/transaksi, serta hanya merender token dan kontrol simpan bagi editor. Kontrak header AJAX Batch 59 dipertahankan. Auditor final menyatakan PASS setelah memeriksa staged diff dan validasi.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/extra_group_products.php`
  - `application/views/master/extra_item_groups.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
- Perubahan utama:
  - Token `master_relation_extra_group_checklist_mutation_csrf` dibuat dengan `random_bytes(32)`, strict lowercase 64-hex, dan `hash_equals` terhadap session.
  - Kedua writer menolak GET/PUT/DELETE dan method non-POST lain dengan 405; token kosong, malformed, mismatch, cross-scope, query/header/JSON/raw-body fallback ditolak 403 sebelum lookup atau DB.
  - View editor mendapat hidden token dan kontrol aktif; view-only tidak mendapat token, tombol simpan disembunyikan, dan checkbox dinonaktifkan.
- Validasi yang dijalankan:
  - `php -l` keempat file: lulus.
  - Checklist smoke: PASS 157 checks; regresi AJAX Batch 59: 85; product-extra 141; component formula 159; product recipe 138; product bundle 149: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Empat artefak Batch 60 staged atomik tanpa overlay unstaged; tidak ada route/schema/config/data change, commit, atau deletion.
- Hasil review auditor: `PASS`; tidak ada finding blocking atau required fix. Urutan guard, isolasi token dari AJAX, rendering editor/view-only, dan kualitas smoke dinyatakan benar.
- Risiko sisa:
  - Filter `q` belum diubah: save replace-all saat daftar terfilter masih dapat menghapus mapping yang tidak tampil.
  - Payload belum memvalidasi status aktif dan kecocokan divisi/parent secara penuh; concurrent save belum memakai revision/optimistic locking.
  - Global CSRF tetap nonaktif dan belum ada browser probe dengan session CI nyata/transaksi DB staging.
- Batch berikutnya: auditor memprioritaskan perbaikan semantik filter checklist (delta update atau blok save saat `q` aktif), lalu validasi ID aktif/divisi dan concurrency secara terpisah; tetap satu batch kecil dan tanpa License Hub.

## Batch 61 — HIGH filter checklist Extra Group menjadi read-only

- Waktu: 2026-09-03 04:12 WIB.
- Prioritas: HIGH integrity — kedua writer checklist melakukan replace-all berdasarkan checkbox yang sedang tampil. Saat `q` aktif, ID mapping di luar hasil filter tidak ikut terkirim dan dapat terhapus diam-diam.
- Ringkasan diskusi auditor/fixer: auditor memilih solusi paling kecil dan fail-safe: halaman checklist terfilter menjadi read-only, bukan delta update yang berisiko mengubah semantics. Fixer tidak menerbitkan token dan tidak menampilkan kontrol simpan/select-all saat `q` aktif; kedua writer menolak POST dengan query `q` aktif setelah RBAC+CSRF dan sebelum parent/payload/transaksi, lalu redirect dengan `q` dipertahankan secara aman. Tanpa filter, replace-all dan clear-all eksplisit tetap kompatibel. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/extra_group_products.php`
  - `application/views/master/extra_item_groups.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
- Perubahan utama:
  - Reader hanya menerbitkan token checklist ketika `q` kosong; filter aktif menampilkan pesan bahwa reset filter diperlukan untuk mengubah checklist.
  - Checkbox/filter controls ter-disable atau disembunyikan pada mode terfilter; view-only tetap tidak mendapat kontrol edit.
  - Writer menolak query `q` nonkosong tanpa lookup/model/DB dan memakai redirect dengan encoding aman. Payload kosong pada `q` kosong tetap berarti clear-all yang disengaja.
- Validasi yang dijalankan:
  - `php -l` tiga file aplikasi dan smoke: lulus.
  - Checklist smoke: PASS 203 checks; regresi AJAX Batch 59: 85; recipe 138; formula legacy 159; product-extra 141; bundle 149; Production formula 191: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Empat artefak staged atomik tanpa overlay unstaged; tidak ada route/schema/config/data change atau deletion.
- Hasil review auditor: `PASS`; urutan guard, redirect, clear-all tanpa filter, isolasi kontrak AJAX, smoke, dan staging dinyatakan benar.
- Risiko sisa:
  - Replace-all masih last-writer-wins pada dua editor bersamaan tanpa optimistic locking/versioning.
  - Payload masih menerima ID positif/deduplikasi tanpa validasi server-side penuh untuk keberadaan, status aktif, kecocokan divisi, dan eligibility parent.
  - Belum ada browser probe dengan session CI nyata/transaksi DB staging.
- Batch berikutnya: auditor memilih hardening payload set untuk writer checklist Extra Group (non-AJAX dan AJAX) secara terukur; validasi concurrency dipisahkan agar batch tetap kecil.

## Batch 62 — HIGH validasi set Extra Group → Product

- Waktu: 2026-09-03 04:01 WIB.
- Prioritas: HIGH integrity — dua writer `Extra Group → Product` mengubah `mst_product_extra_map` dengan delete-all/insert berdasarkan ID positif/deduplikasi saja. Payload manipulatif dapat memasukkan produk tidak ada, nonaktif, atau lintas divisi dan memengaruhi opsi extra POS.
- Ringkasan diskusi auditor/fixer: auditor memilih dua writer yang setara (form dan AJAX) agar tidak ada bypass antar-caller. Karena aturan divisi produk bersifat objektif dari `mst_extra_group.product_division_id`, validator bersama ditambahkan; sisi `Extra → Group` ditunda karena `mst_extra` tidak memiliki aturan divisi yang aman untuk diinferensikan. Fixer mempertahankan guard RBAC/POST/CSRF/q, clear-all eksplisit, dedupe, dan sort order; invalid mixed set ditolak sebelum delete/transaksi. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - Helper shared memakai query set-based untuk memastikan seluruh product ID ada, `is_active = 1`, dan cocok dengan divisi group bila divisi group non-NULL; divisi NULL mengizinkan lintas divisi.
  - Field tidak ada/array kosong tetap clear-all, termasuk group nonaktif; group nonaktif dengan set nonempty, scalar/malformed/0/negatif/nonexistent/inactive/wrong-division/mixed-invalid ditolak sebelum write.
  - Form mengembalikan flash+redirect, AJAX JSON 422; delete dan insert tetap dalam transaksi. Kontrak header AJAX dan filter Batch 59–61 tidak berubah.
- Validasi yang dijalankan:
  - `php -l` controller dan dua smoke: lulus.
  - Checklist smoke: PASS 254 checks; AJAX smoke: PASS 119; Production formula 191; recipe 138; component formula 159; product-extra 141; bundle 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; hanya deprecation warning dari Composer lama dan peringatan root.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Tiga artefak staged tanpa overlay unstaged; tidak ada route/view/schema/config/data change atau deletion.
- Hasil review auditor: `PASS`; validator set, urutan sebelum delete, empty-set policy, division NULL behavior, transaction, smoke, dan staging dinyatakan benar.
- Risiko sisa:
  - Validasi state product/group masih dapat berlomba dengan perubahan bersamaan; last-writer-wins/concurrency belum memakai lock/versioning.
  - Writer legacy `product_extra_store/delete` belum mengikuti seluruh aturan eligibility; mapping invalid historis belum dibersihkan.
  - Belum ada query preflight dan browser/DB integration probe di staging karena environment DB tidak tersedia dalam CLI.
- Batch berikutnya: auditor memilih hardening payload `Extra → Group` dengan kebijakan existence/active yang jelas, atau lebih dahulu merancang probe DB/rollback untuk transaksi mapping; jangan menginfer aturan divisi tanpa dasar bisnis.

## Batch 63 — HIGH validasi existence/active Extra ↔ Group

- Waktu: 2026-09-03 04:15 WIB.
- Prioritas: HIGH integrity — dua writer sisi `Extra → Group` sebelumnya melakukan cast integer lalu replace-all pada `mst_extra_group_item`; ID rusak dapat menjadi clear-all diam-diam atau baru gagal di FK setelah delete.
- Ringkasan diskusi auditor/fixer: auditor menetapkan policy tanpa inferensi divisi/source-kind: parent dan selected child harus ada serta aktif untuk set nonempty; parent nonaktif tetap boleh clear-all; missing/empty field berarti clear-all eksplisit; invalid mixed set harus gagal sebelum transaksi. Fixer menambah helper shared dan memperluas dua smoke. Auditor pertama memberi catatan packaging karena index global kumulatif; fixer memverifikasi target-scoped staging tanpa mengubah staging batch lain; auditor re-review menyatakan PASS untuk workflow tanpa commit.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - Validator `Extra → Group` menolak scalar/null/malformed/0/negatif/float/bool/noncanonical numeric, nonexistent, inactive child, inactive parent dengan set nonempty, dan mixed-invalid sebelum delete/insert/transaksi.
  - ID valid dideduplikasi berdasarkan urutan pertama; sort order tetap 10/20; cross-domain, division, dan `source_kind` tetap diizinkan karena belum ada policy bisnis objektif.
  - Form memakai flash+redirect, AJAX JSON 422; absent/empty set tetap delete-only clear-all, termasuk parent nonaktif. Guard RBAC/POST/CSRF/q dan B62 product-side validation tidak berubah.
- Validasi yang dijalankan:
  - `php -l` controller dan dua smoke: lulus.
  - Checklist smoke: PASS 322 checks; AJAX smoke: PASS 161; regresi B54 191, B55 138, B56 159, B57 141, B58 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; warning hanya dari Composer sistem lama/root/deprecation.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Tiga target staged tanpa overlay unstaged; target-scoped atomik, sementara index global tetap kumulatif untuk menjaga batch sebelumnya/user changes.
- Hasil review auditor: awal `NEEDS_FIX` hanya pada interpretasi standalone commit; setelah workflow tanpa commit dikonfirmasi dan staging target diverifikasi, re-review `PASS`. Tidak ada finding kode atau delivery aktual.
- Risiko sisa:
  - TOCTOU dan last-writer-wins masih memungkinkan saat parent/child berubah bersamaan; belum ada lock/versioning.
  - Mapping historis orphan/nonaktif tidak diremediasi otomatis; hanya writer baru yang divalidasi.
  - Belum ada query preflight atau DB/browser integration probe pada staging karena kredensial DB CLI tidak tersedia.
- Batch berikutnya: auditor memilih integrity constraint/query preflight atau desain concurrency/revalidation transaction untuk mapping, tetap tanpa perubahan schema/data kecuali bukti staging mengharuskannya.

## Batch 64 — HIGH hardening legacy Product → Extra Group

- Waktu: 2026-09-03 04:28 WIB.
- Prioritas: HIGH integrity — `product_extra_store` legacy masih menjadi bypass terhadap invariant B62: hanya cast `extra_group_id`, lalu insert tanpa validasi product/group aktif atau kecocokan divisi. Opsi create juga menawarkan group nonaktif/beda divisi.
- Ringkasan diskusi auditor/fixer: auditor memilih jalur create legacy, bukan reconciliation/versioning yang lebih besar. Fixer membatasi opsi group ke group aktif generik atau sesuai divisi product, dan memvalidasi server-side setelah RBAC+CSRF sebelum duplicate/insert. Delete tetap tidak memeriksa status agar mapping historis dapat dibersihkan. Auditor pertama memberi catatan staging global kumulatif; fixer memverifikasi target scoped tanpa mengubah staging lain; auditor re-review menyatakan PASS untuk workflow tanpa commit.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
- Perubahan utama:
  - Create hanya menawarkan group aktif dengan `product_division_id IS NULL` atau sama dengan divisi product.
  - Store menolak product nonaktif, ID group kosong/noncanonical/malformed/0/negatif/float/bool, group tidak ada/nonaktif, dan mismatch divisi sebelum insert; generic NULL division tetap sah.
  - Duplicate valid tetap warning/no insert; delete mapping historis tetap boleh meski parent/child nonaktif.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - B57 smoke: PASS 252 checks; B59 161; B60 322; B54 191; B55 138; B56 159; B58 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; warning hanya dari Composer sistem/deprecation/root.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Dua target staged tanpa overlay unstaged; tidak ada route/schema/data/config/view change atau deletion.
- Hasil review auditor: awal `NEEDS_FIX` hanya staging gate bersyarat standalone commit; setelah target-scoped staging diverifikasi dan no-commit workflow dikonfirmasi, re-review `PASS` tanpa defect kode.
- Risiko sisa:
  - TOCTOU/status dan race duplicate masih mungkin antara validasi, duplicate check, dan insert; hasil insert belum ditangani secara eksplisit pada race unique key.
  - Mapping historis inactive/orphan/mismatch belum diremediasi otomatis dan tetap sengaja dapat dihapus.
  - Belum ada DB/browser integration probe nyata; schema snapshot lama belum membuktikan unique/FK aktif di deployment.
- Batch berikutnya: auditor memilih penanganan duplicate-key/insert failure secara atomik atau reconciliation read-only sebelum concurrency/versioning; tetap satu batch kecil dan tanpa License Hub.

## Batch 65 — HIGH atomic duplicate-key dan insert-failure handling

- Waktu: 2026-09-03 04:46 WIB.
- Prioritas: HIGH reliability/security — `product_extra_store` melakukan pre-check duplicate lalu generic insert yang mengabaikan hasil; race unique-key atau error DB dapat menghasilkan flash sukses palsu. Selain itu CI3 `DB_driver::query()` mencatat SQL/detail error bahkan saat `db_debug=false`.
- Ringkasan diskusi auditor/fixer: auditor memilih memperbaiki satu legacy writer aktif tanpa menyentuh `Master_model` generik. Fixer pertama menambahkan cek hasil insert, tetapi auditor menemukan SQL/detail masih dapat bocor melalui logging internal CI3. Fixer kemudian memakai prepared statement statis langsung dari connection `mysqli`, binding integer, menangani 1062 sebagai warning duplicate, error lain/throwable sebagai error generik, dan memulihkan `db_debug`/menutup statement di `finally`. Auditor re-review menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
- Perubahan utama:
  - Insert final tidak lagi memakai `Master_model->insert` atau `$db->insert`; memakai `conn_id->prepare` dengan SQL tabel/kolom statis dan `bind_param('iii')`.
  - Hanya errno numerik yang dipakai untuk routing/log context; SQL, DB message, exception detail, dan secret marker tidak ditampilkan/logged oleh aplikasi.
  - Error 1062 menjadi warning “Mapping sudah ada” dan redirect list; error lain/throwable menjadi pesan generik dan redirect create; pre-check duplicate dan delete historis tetap kompatibel.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - B57 smoke: PASS 299 checks; B59 161; B60/B62/B63 322; B54 191; B55 138; B56 159; B58 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; warning hanya deprecated Composer/Symfony lama dan root.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Dua target staged tanpa overlay unstaged; no-commit workflow dan staging target-scoped dipertahankan.
- Hasil review auditor: awal `NEEDS_FIX` karena CI3 query builder tetap berpotensi menulis SQL/detail ke log; setelah prepared statement langsung dan smoke log-sink, re-review `PASS`.
- Risiko sisa:
  - Requirement runtime kini eksplisit: CI3 harus memakai driver `mysqli` dengan `conn_id` kompatibel; belum ada uji database/browser/concurrency nyata.
  - Unique/FK deployment aktual belum dibuktikan; tanpa unique key, race tetap dapat menggandakan mapping.
  - Mapping historis invalid belum diremediasi dan empat replace-all writer masih last-writer-wins.
- Batch berikutnya: auditor memilih read-only reconciliation/preflight untuk memeriksa constraint dan mapping historis sebelum merancang concurrency/versioning; tetap tanpa mutasi data otomatis.

## Batch 66 — HIGH read-only Extra Group mapping preflight

- Waktu: 2026-09-03 05:30 WIB.
- Prioritas: HIGH integrity/release safety — sebelum memperbaiki data atau merancang concurrency, perlu bukti read-only tentang tabel mapping, 16 kolom wajib, unique pair, FK, orphan, duplicate, inactive master, dan mismatch divisi.
- Ringkasan diskusi auditor/fixer: auditor memilih preflight kecil yang tidak mengubah schema/data. Fixer menambahkan SQL SELECT-only, wrapper marker/exit-code, smoke, dan runbook. Review awal meminta `sort_order`/kontrak 16 kolom, identity serta aksi FK `RESTRICT`, parser yang menolak marker setelah `B66_END` dan detail melebihi count, serta runbook yang jujur pada schema-missing. Setelah diperbaiki, review akhir menemukan satu risiko HIGH: `--defaults-extra-file` masih membolehkan global option files; fixer menggantinya menjadi `--defaults-file` sebagai opsi client pertama, memperbarui smoke/runbook, dan auditor akhir menyatakan PASS.
- File berubah:
  - `tools/sql/2026-09-03_extra_group_mapping_preflight.sql`
  - `tools/run_extra_group_mapping_preflight.sh`
  - `tools/tests/extra_group_mapping_preflight_smoke.sh`
  - `docs/2026-09-03_extra_group_mapping_preflight_runbook.md`
- Perubahan utama:
  - Preflight memakai `START TRANSACTION READ ONLY`, SELECT terhadap target/`information_schema`, `COMMIT`, tanpa dynamic SQL, DDL, DML, atau remediation otomatis.
  - Memeriksa lima tabel InnoDB, kolom/`sort_order`, unique pair, empat FK dengan identity dan `DELETE/UPDATE RESTRICT`, serta sembilan kategori finding dengan detail maksimum 50 baris.
  - Wrapper memakai external option file yang dicanonicalisasi dan dilarang berada di repository; `--defaults-file` mengisolasi konfigurasi client; credential environment di-unset; output database/error dirahasiakan; marker dan exit code divalidasi.
  - Smoke menguji kontrak SQL, allow-list source, parser malformed/terminal/count, schema/data exit mapping, secret boundary, external option file, dan repository immutability. Runbook memberi prosedur akun SELECT-only dan follow-up browser/POS.
- Validasi yang dijalankan:
  - `bash -n tools/run_extra_group_mapping_preflight.sh tools/tests/extra_group_mapping_preflight_smoke.sh`: lulus.
  - B66 smoke: PASS 113 checks.
  - Regression B65 product-extra mutation CSRF smoke: PASS 299 checks.
  - Static SQL: 44 SELECT, tanpa match DML/DDL, sembilan `LIMIT 50`.
  - Wrapper bad argument exit `64` dan missing option file exit `66`: lulus; permission script `755`.
  - `git diff --check` dan `git diff --cached --check`: lulus; empat target B66 staged tanpa overlay unstaged; tidak ada commit.
  - Auditor review akhir: PASS, tanpa defect konkret.
- Risiko sisa:
  - Preflight database nyata belum dijalankan karena belum ada option file eksternal dan akun DB read-only; schema/deployment aktual belum terbukti.
  - UAT browser mapping dan probe POS web/mobile untuk group generic, group sesuai divisi, serta group beda divisi masih wajib sebelum release.
  - Hasil ini tidak meremediasi orphan/duplicate/inactive/mismatch; remediation dan concurrency/versioning tetap batch terpisah.
- Batch berikutnya: auditor memilih batch kecil berbasis bukti preflight/reconciliation atau hardening concurrency mapping; tetap read-only dahulu bila DB belum tersedia, tanpa License Hub dan tanpa mutasi data otomatis.

## Batch 67 — HIGH optimistic concurrency Extra Group → Product

- Waktu: 2026-09-03 06:01 WIB.
- Prioritas: HIGH integrity/concurrency — dua writer replace-all dapat mengalami last-writer-wins; editor kedua dengan snapshot lama dapat menghapus mapping yang baru disimpan editor pertama.
- Ringkasan diskusi auditor/fixer: auditor memilih dua writer Product mapping saja, tanpa mengubah arah Extra → Group atau schema/data. Fixer menambahkan revision SHA-256 deterministik, lock parent dan mapping, pemeriksaan transaksi, serta guard state modal. Review pertama menemukan tiga isu HIGH: response AJAX Group A dapat masuk ke state Group B, return `trans_begin`/`trans_commit` diabaikan, dan lock parent saja belum menahan legacy Product → Extra Group delete. Fixer memperbaiki ketiganya dan menambah fixture smoke. Auditor re-review menyatakan PASS. Catatan staging kumulatif 46 file diperlakukan sebagai packaging residual; perubahan batch/user lain tidak di-unstage.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/extra_group_products.php`
  - `application/views/master/index.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - Reader form/AJAX menghitung revision canonical dari seluruh pasangan `product_id + sort_order`; serialization length-prefixed dan SHA-256 dipakai konsisten pada reader dan writer.
  - Writer menolak revision missing/malformed; transaksi mengunci parent `mst_extra_group` lalu rows mapping `mst_product_extra_map` dengan `FOR UPDATE`, memverifikasi revision sebelum validasi dan DELETE/INSERT.
  - `trans_begin`/`trans_commit` failure tidak dapat menghasilkan sukses; rollback/error form/AJAX generik dipertahankan. Stale revision rollback tanpa mapping DML dan AJAX mengembalikan HTTP 409.
  - Modal memakai epoch/context group+kind serta request/query sequence; response stale tidak mengubah state group lain, filter tidak menimpa snapshot awal, dan save fail-closed jika revision/CSRF tidak valid.
  - Smoke mencakup canonical revision, race response A/B dan initial/filter, lock ordering, missing/malformed revision, begin/commit failure, conflict tanpa DML, clear-all/valid path, serta regresi permission/payload.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan dua smoke: lulus.
  - Checklist smoke: PASS 344 checks; AJAX smoke: PASS 185 checks.
  - Regression product-extra: PASS 299 checks; production formula: PASS 191 checks; B66 preflight smoke: PASS 113 checks.
  - `git diff --check` dan `git diff --cached --check`: lulus; lima target staged tanpa unstaged delta; tidak ada commit.
  - Auditor re-review: PASS, tanpa defect konkret.
- Risiko sisa:
  - Legacy `product_extra_delete` masih tidak mengambil parent lock sendiri; serialisasi lintas jalur bergantung pada InnoDB, FK `fk_mst_product_extra_map_group`, unique pair, dan index schema yang harus dibuktikan B66 pada DB target.
  - Smoke race frontend masih source-level; UAT dua browser/session dan initial/filter out-of-order belum dijalankan.
  - DB multi-session untuk deadlock/commit failure belum tersedia; mapping historis invalid dan arah Extra → Group tetap belum ditangani.
  - Index kerja tetap kumulatif 46 file staged; packaging/commit wajib memakai manifest/whitelist batch dan tidak boleh membawa file runtime/backup/user secara tidak sengaja.
- Batch berikutnya: auditor memilih hardening jalur legacy Product → Extra Group agar delete/insert mengikuti concurrency protocol, atau batch read-only untuk reconciliation berbasis hasil preflight; tetap satu batch kecil, tanpa mutasi data otomatis dan tanpa License Hub.

## Batch 68 — HIGH serialisasi legacy Product → Extra Group

- Waktu: 2026-09-03 06:32 WIB.
- Prioritas: HIGH integrity/concurrency — writer legacy `product_extra_store` dan `product_extra_delete` masih bypass protocol lock B67; insert dapat berinterleaving, dan delete dapat sukses palsu setelah mapping diganti atau hilang.
- Ringkasan diskusi auditor/fixer: auditor memilih serialisasi dua writer legacy saja. Review pertama menerima lock/order/transaction secara fungsional, tetapi menemukan query CI3 (`query`, `get_where`, query-builder delete, dan lookup awal) masih dapat mencatat SQL/detail error ke application log walau `db_debug=false`. Fixer mengganti seluruh read/lock/duplicate/insert/delete pada dua writer dengan prepared mysqli langsung, bind integer, `bind_result/fetch`, error code numerik, dan smoke failure fixtures. Auditor re-review menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
- Perubahan utama:
  - `product_extra_store` memulai transaksi terverifikasi, mengunci parent `mst_extra_group` sebelum revalidasi product/group, duplicate check, dan prepared insert; begin/status/commit/prepare/bind/execute failure di-rollback dan tidak pernah menghasilkan sukses palsu; duplicate `1062` tetap warning.
  - `product_extra_delete` melakukan initial lookup aman, mengunci parent dahulu bila ada, lalu exact mapping `id + extra_group_id` dengan `FOR UPDATE`; orphan parent-missing tetap bisa dibersihkan; exact delete wajib affected rows tepat satu; stale/zero-row menjadi warning, bukan sukses.
  - Seluruh alur B68 memakai `db_debug=false` boundary dan helper prepared langsung sehingga CI DB driver tidak menerima query yang dapat bocor; log hanya context method/id dan errno numerik; kebijakan delete historical inactive tidak berubah.
  - Smoke diperluas dari 350 menjadi 428 checks untuk lock order, prepared result/mutation failures, rollback, no-false-success, affected-row anomaly, duplicate 1062, orphan/inactive semantics, dan log redaction.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - B68 product-extra smoke: PASS 428 checks; B67 checklist: PASS 344; B67 AJAX: PASS 185; B66 preflight: PASS 113; production formula: PASS 191.
  - `git diff --check` dan `git diff-files --check`: lulus; kedua target B68 tidak memiliki unstaged overlay; target tetap staged tanpa commit.
  - Auditor re-review: PASS, tanpa defect B68 yang memerlukan perbaikan.
  - `git diff --cached --check` dan cached name listing belum dapat dijalankan: repository memiliki korupsi object yang telah ada, termasuk missing HEAD tree `b3d09b6c42f0e6cf8e53f2dc5f5bb93091f02e44`, banyak missing blob/tree/cache-tree, dan invalid remote pointer. Tidak ada upaya destructive untuk memulihkan object pada batch ini.
- Risiko sisa:
  - Contention/deadlock InnoDB nyata dan observasi application log pada error DB belum diuji; live/staging preflight B66 serta UAT dua session masih wajib.
  - B68 delete belum mengunci parent pada jalur tersendiri bila parent orphan; mapping row menjadi lock target sesuai kebutuhan cleanup. Arah Extra → Group tetap belum concurrency-hardened.
  - Korupsi object Git menghalangi pemeriksaan cached diff/status normal dan harus dipulihkan/ditangani terpisah sebelum packaging/release; jangan menjalankan commit dari index kumulatif 46 file tanpa manifest/whitelist.
- Batch berikutnya: auditor memilih perbaikan read-only/repository integrity dan/atau hardening concurrency arah Extra → Group setelah blocker Git ditangani dengan prosedur recovery aman; tidak melakukan remediation data otomatis atau License Hub.

## Batch 69 — Component HPP/value reconciliation tanpa perubahan saldo

- Waktu: 2026-09-03 13:00 WIB.
- Prioritas: HIGH integrity/value — mismatch SAUCE BANGKOK menunjukkan qty lot dan stok dapat sama sementara nilai/HPP berbeda; adjustment fisik tidak boleh dipakai untuk memperbaiki nilai.
- Ringkasan diskusi auditor/fixer: auditor mengarahkan agar reconcile component membuka `InventoryValueReconciliationService`, membawa `monthly_stock_id`, dan hanya menampilkan jalur koreksi pada bulan aktif ketika qty stok = qty lot. Saran HPP dibuat read-only dari weighted lot OPEN, formula live, formula normal, dan hpp standar; tidak ada auto-post.
- File berubah:
  - `application/libraries/InventoryValueReconciliationService.php`
  - `application/controllers/Inventory_control.php`
  - `application/controllers/Production.php`
  - `application/models/Production_model.php`
  - `application/views/inventory/stock_value_reconciliation_index.php`
  - `application/views/production/component_reconcile_index.php`
- Perubahan utama:
  - Reconcile component meneruskan ID saldo bulanan dan menyediakan tombol `Koreksi HPP/nilai` ke halaman koreksi nilai untuk mismatch nilai murni bulan aktif.
  - Koreksi manual dapat diisi sebagai HPP/unit; total dihitung dari qty saldo dan backend menghitung ulang memakai qty yang terkunci dalam transaksi.
  - Tambahan guard snapshot qty mencegah posting memakai saldo lama; angka negatif tetap ditolak dan lot CLOSED tidak disentuh.
  - Halaman menampilkan HPP stok saat ini sebagai diagnostik, weighted HPP lot OPEN, HPP formula live berbasis weighted lot, HPP formula normal, dan HPP standar master sebagai saran eksplisit.
- Validasi yang dijalankan:
  - `php -l` keenam file PHP berubah: lulus.
  - Dashboard component value mismatch smoke: PASS 19 checks.
  - Inventory control mutation CSRF smoke: PASS 272 checks.
  - Production component formula mutation CSRF smoke: PASS 191 checks.
  - `composer validate --no-check-publish`: `composer.json` valid; hanya muncul deprecation notice dari Composer lama/PHP runtime.
  - `git diff --check` pada target batch: lulus.
- Hasil review auditor: putaran pertama menemukan guard tanggal, kecocokan scope formula, mode source lot, validasi unit/total, dan output resep nol; seluruh temuan tersebut ditutup termasuk perbandingan divisi null-safe. Validasi ulang lint dan smoke tetap lulus.
- Risiko sisa:
  - Koreksi SAUCE BANGKOK belum diposting; operator tetap harus memverifikasi resep/lot dan memilih HPP secara eksplisit.
  - Validasi browser dan query DB nyata untuk memastikan qty 785 tetap 785, nilai stock = nilai lot, audit header/line tercatat, dan closed lots tidak berubah masih wajib.
  - Fondasi tabel `inv_stock_value_reconciliation*` harus sudah dijalankan di server target.
- Batch berikutnya: UAT terarah SAUCE BANGKOK dan probe DB read-only; setelah bukti cocok, baru pertimbangkan hardening pembulatan/post-condition revaluasi.

## Batch 70 — Perbaikan tampilan nilai stok dan VOID koreksi nilai

- Waktu: 2026-09-03.
- Prioritas: HIGH integrity/recovery — hasil koreksi lot sudah berubah, tetapi reconcile masih menimpa nilai ledger dengan proyeksi movement lama; dokumen POSTED belum dapat dibatalkan secara aman.
- Ringkasan diskusi auditor/fixer: auditor mengidentifikasi overlay proyeksi harian sebagai penyebab nilai stok tampak belum berubah dan merekomendasikan void bersyarat berdasarkan snapshot exact-state. Implementasi menjaga histori dokumen, memulihkan nilai dalam transaksi, dan menolak void bila ada perubahan lanjutan.
- File berubah:
  - `application/models/Production_model.php`
  - `application/libraries/InventoryValueReconciliationService.php`
  - `application/controllers/Inventory_control.php`
  - `application/config/routes.php`
  - `application/views/inventory/stock_value_reconciliation_index.php`
  - `tools/tests/inventory_control_mutation_csrf_smoke.php`
- Perubahan utama:
  - Hari aktif memakai nilai/avg dari ledger `inv_component_monthly_stock` setelah koreksi; tanggal histori tetap read-only memakai proyeksi harian.
  - Endpoint `inventory/stock/value-reconciliation/void` men-lock dokumen, stok, dan lot; memverifikasi qty/nilai/HPP masih sama dengan hasil dokumen; lalu memulihkan nilai sebelum koreksi dan memberi status `VOID`.
  - Riwayat menampilkan tombol VOID untuk Superadmin, meminta alasan, dan tidak menghapus header/line/audit.
- Validasi yang dijalankan:
  - `php -l` seluruh file PHP/controller/view/model/service/test dan route: lulus.
  - Inventory control mutation CSRF smoke: PASS 312 checks.
  - Dashboard component value mismatch smoke: PASS 19 checks.
  - Production component formula mutation CSRF smoke: PASS 191 checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS bersyarat; invariant qty, scope formula, source mode lot, validasi manual, output nol, dan cache weighted telah ditutup. UAT DB nyata tetap diperlukan.
- Risiko sisa:
  - Belum dilakukan void/post terhadap data SAUCE BANGKOK atau CHICKEN CUBE secara otomatis.
  - Void sengaja ditolak jika ada perubahan stok/HPP/koreksi lanjutan; dalam kondisi itu perlu audit manual, bukan overwrite.
  - UAT browser/DB wajib membuktikan qty tidak berubah, nilai stock = lot setelah post, dan nilai kembali ke sebelum dokumen setelah void.
- Batch berikutnya: UAT terarah dua component tersebut dan post-condition pembulatan revaluasi bila hasil DB menunjukkan sisa sen.

## Batch 71 — Pemisahan dua dokumen roadmap dan sinkronisasi status

- Waktu: 2026-09-03 13:50 WIB.
- Prioritas: dokumentasi/readiness — roadmap audit dan roadmap komersialisasi
  masih memiliki bagian yang tumpang tindih sehingga status bug, fondasi
  teknis, dan pekerjaan lisensi dapat terbaca sebagai satu antrean.
- Ringkasan diskusi auditor dan fixer: arah yang ditetapkan adalah `_30`
  sebagai sumber tunggal audit bug, security/RBAC, integritas stok/HPP,
  navigasi/UI, test, dependency, schema, backup, dan release foundation.
  `_28` menjadi sumber tunggal paket, harga, kontrak, productization customer,
  installer komersial, entitlement, License Hub, Product Control Center,
  pilot, support, dan penjualan. Laporan tanggal lain tetap hanya menjadi
  laporan batch/modul.
- File berubah:
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - Menambahkan checklist status P0/P1/P2 dan Batch 48, 53, 54–70 pada `_30`.
  - Memisahkan roadmap teknis `_30` menjadi Fase A0–A5 dan roadmap komersial
    `_28` menjadi Fase C0–C5.
  - Menandai Batch 69–70 sebagai workflow koreksi HPP/nilai dan VOID yang
    sudah lulus batch, tetapi repair data enam component serta UAT tetap
    terbuka.
  - Menghapus pengulangan langkah audit dari urutan komersialisasi dan
    menjadikan laporan progres 2026-09-03 sebagai laporan turunan.
- Validasi yang dijalankan:
  - Pembacaan ulang penuh dua dokumen induk dan execution log sampai Batch 70.
  - Pemeriksaan heading, status checklist, dan istilah lintas dokumen.
  - `git diff --check` untuk tiga dokumen target: lulus.
  - Tidak ada perubahan kode, database, SQL, route, config, backup, upload,
    credential, atau data runtime pada batch ini.
- Hasil review auditor: review awal menemukan klaim status lama pada P0-04,
  P0-06, P1-09, P1-10, P1-11, serta sisa tanggung jawab generator/PCC di `_30`.
  Semua koreksi tersebut sudah diterapkan; auditor menyatakan checklist Batch
  69–70 konsisten dan pemisahan scope `_30`/`_28` sudah jelas.
- Risiko sisa:
  - Beberapa bagian lama di `_30` dan `_28` masih berupa spesifikasi teknis
    yang saling merujuk; ownership sudah ditegaskan, tetapi perlu dipertahankan
    pada setiap batch baru.
  - Status database live, UAT browser, dan object Git tetap mengikuti checklist
    `_30`, bukan dianggap selesai hanya karena dokumen sudah diperbarui.
- Batch berikutnya: kembali ke Fase A2 — probe read-only dan preview repair
  enam mismatch component, lalu perbaiki trigger rebuild cache HPP live setelah
  bukti database tersedia.

## Batch 72 — Sinkronisasi cache HPP live setelah koreksi nilai

- Waktu: 2026-09-03.
- Prioritas: HIGH integritas HPP — koreksi/VOID nilai persediaan sudah mengubah
  ledger dan lot, tetapi cache availability POS/HPP live belum otomatis ikut
  dihitung ulang.
- Ringkasan diskusi auditor/fixer: auditor memilih hook post-commit terkecil.
  Setelah transaksi koreksi atau VOID commit, service memanggil rebuild
  availability sesuai domain component/material. Kegagalan queue/rebuild tidak
  boleh membatalkan koreksi yang sudah tersimpan; hasilnya dikembalikan sebagai
  metadata warning.
- File berubah:
  - `application/libraries/InventoryValueReconciliationService.php`
  - `tools/tests/inventory_value_reconciliation_availability_smoke.php`
- Perubahan utama:
  - `post()` dan `voidRecord()` memanggil `refreshAvailabilityAfterCommit()` di
    luar blok rollback setelah `trans_commit()`.
  - Component diarahkan ke `handle_component_change`, material ke
    `handle_material_change`, dengan `trigger_context`, `event_source`,
    `event_table`, event ID, dan actor user.
  - Koreksi tetap dilaporkan sukses bila cache gagal; warning dan log tersedia
    untuk tindak lanjut worker/cache.
- Validasi yang dijalankan:
  - `php -l` service dan smoke: lulus.
  - Availability smoke: PASS 19 checks.
  - Inventory control mutation smoke: PASS 312 checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS; urutan post-commit, isolasi exception, dispatch
  domain, dan metadata event sesuai.
- Risiko sisa:
  - Queue/worker dan cache DB nyata belum diuji; warning belum dibuat sebagai
    notifikasi UI khusus.
  - Data mismatch historis tidak diubah pada batch ini.
- Batch berikutnya: Fase A1 POS Mobile, dimulai dari POST-only seluruh writer.

## Batch 73 — Fase A1 POS Mobile: POST-only boundary

- Waktu: 2026-09-03.
- Prioritas: P0 security/RBAC — endpoint writer POS Mobile masih perlu menolak
  GET/PUT secara eksplisit sebelum autentikasi, pembacaan payload, query
  sinkronisasi, revoke token, model, atau percobaan printer.
- Ringkasan diskusi auditor/fixer: auditor memilih boundary request terkecil
  tanpa perubahan schema/route. Fixer menambahkan helper `require_mobile_post()`
  pada login, logout, printer test, order save/confirm/push, payment save,
  void/refund, dan buka/tutup kasir. Logout juga mengautentikasi token/session
  sebelum revoke.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - Semua 11 writer menolak metode selain POST dengan HTTP 405 dan daftar
    metode yang diizinkan.
  - Guard berada sebelum auth, payload, model, query sync, printer attempt,
    dan perubahan token.
  - Smoke dibuat table-driven untuk GET dan PUT pada seluruh writer dan
    membuktikan tidak ada side effect, termasuk `logout` tidak merevoke token.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke terkait: lulus.
  - POS Mobile authorization smoke: PASS; 22 kasus GET dan 22 kasus PUT.
  - Inventory control mutation smoke: PASS 312 checks.
  - Cache availability smoke: PASS 19 checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS; urutan guard dan cakupan smoke memenuhi syarat
  batch.
- Risiko sisa:
  - POS Mobile masih belum memiliki binding terminal/outlet/device yang kuat,
    step-up untuk void/refund, dan CSRF scoped bila memakai fallback session.
  - UAT HTTP/APK dengan akun dan perangkat nyata belum dilakukan.
- Batch berikutnya: lanjutkan A1 POS Mobile pada binding scope device/outlet
  atau action policy step-up, setelah memilih perubahan kecil yang tidak
  memerlukan migration baru.

## Batch 74 — Fase A1 POS Mobile: device binding fail-closed

- Waktu: 2026-09-03 16:20 WIB.
- Prioritas: P0 security — token mobile sebelumnya hanya menyimpan device key
  tetapi tidak memverifikasi bahwa request datang dari terminal aktif yang sama.
- Ringkasan diskusi auditor/fixer: auditor memilih binding device sebagai
  primitive yang sudah didukung schema, sementara step-up ditunda karena belum
  memiliki approval, TTL, dan audit contract. Fixer menambahkan validasi terminal
  aktif/unik untuk login dan bearer request, lalu auditor menemukan credential
  oracle pada urutan/pesan login. Urutan kemudian diperbaiki dan kegagalan
  kredensial/perangkat diseragamkan.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - Login mewajibkan identifier, password, dan `terminal_device_key`; field
    kosong ditolak sebelum autentikasi/lookup. Setelah kredensial valid, key
    harus menunjuk tepat satu `pos_terminal` aktif.
  - Kredensial salah dan terminal unknown/nonaktif/duplikat memakai 401 generik
    yang sama, sehingga status registry tidak dapat dijadikan oracle.
  - Bearer token wajib membawa `X-Pos-Mobile-Device-Key`; key dibandingkan
    dengan `hash_equals`, terminal harus aktif dan unik, baru `last_seen_at`
    diperbarui. Token lama tanpa binding otomatis ditolak; invalid bearer tidak
    fallback ke session/API key.
  - Device key tidak ditulis ke response atau log oleh endpoint ini. Logout
    mengikuti binding yang sama.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke: lulus, termasuk seluruh writer GET/PUT
    405, kasus anti-oracle login, token legacy, key missing/mismatch, terminal
    nonaktif/duplikat, invalid bearer, dan logout binding.
  - Availability smoke: PASS 19 checks; inventory-control smoke: PASS 312
    checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS setelah perbaikan anti-oracle; alur credential →
  device registry → RBAC/token issuance dan bearer header → token → terminal →
  `last_seen_at` dinyatakan benar.
- Risiko sisa:
  - Device key masih static shared secret, bukan hardware attestation.
  - Token lama tanpa binding harus login ulang; rollout APK harus mengirim
    device key pada body login dan header `X-Pos-Mobile-Device-Key` pada setiap
    request bearer.
  - Binding outlet/order dan step-up void/refund/reprint belum selesai; UAT
    HTTP/APK nyata belum dilakukan.
- Batch berikutnya: auditor memilih binding scope outlet/order atau rancangan
  action policy step-up, tetap satu batch kecil dan tanpa migration baru bila
  primitive existing mencukupi.

## Batch 75 — Fase A1 POS Mobile: terminal-bound cashier session

- Waktu: 2026-09-03 18:25 WIB.
- Prioritas: P0 security/scope — token yang sudah terikat device/terminal juga
  harus mencegah sesi kasir dibuka, ditutup, atau dilihat dari outlet/terminal
  yang berbeda.
- Ringkasan diskusi auditor dan fixer: auditor memilih guard controller kecil
  tanpa perubahan schema. Fixer menambahkan konteks `terminal_id` dan `outlet_id`
  dari terminal aktif pada bearer token, memeriksa konteks request, dan
  memvalidasi sesi kasir aktif sebelum operasi bisnis. Auditor meminta dua
  koreksi: `session_status` tanpa sesi aktif harus tetap 200, dan `cashier_open`
  harus menolak sesi aktif yang masih terikat terminal lain sebelum recon/open.
  Keduanya diterapkan dan direview PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - `cashier_open` mewajibkan `outlet_id` dan `terminal_id` yang sama dengan
    terminal token, serta menolak active session milik employee yang terikat
    terminal/outlet lain sebelum daily recon dan pembukaan sesi.
  - `cashier_close` dan `cashier_close_preview` hanya berjalan untuk sesi aktif
    pada terminal/outlet token.
  - `session_status` bearer hanya mengembalikan sesi milik perangkat; tanpa
    sesi aktif mengembalikan `session: null` dan `active_sessions: []`, bukan
    daftar sesi global.
  - Mismatch terminal/outlet, sesi nonaktif, dan sesi existing lintas terminal
    ditolak dengan HTTP 403 tanpa memanggil operasi bisnis, recon, writer, atau
    printer.
- Validasi yang dijalankan:
  - `php -l` controller, service, dan smoke test: lulus.
  - POS Mobile authorization smoke: PASS.
  - Availability smoke: PASS 19 checks.
  - `git diff --check` pada file source dan dokumen: lulus.
  - Tidak ada SQL/schema/database/backup/upload/credential/runtime yang diubah.
- Hasil review auditor: PASS. Auditor menyatakan binding sesi kasir dan
  pemisahan daftar sesi bearer sudah benar.
- Risiko sisa:
  - Masih ada race kecil bila sesi berubah bersamaan dari web/perangkat lain;
    validasi atomik berbasis session-id/terminal dapat menjadi batch berikutnya.
  - Binding belum merata ke seluruh endpoint katalog/order/read, step-up
    void/refund/reprint belum ada, dan UAT HTTP/APK nyata belum dilakukan.
  - Fallback web-session sengaja tetap kompatibel dan masih dapat menampilkan
    daftar sesi global.
- Batch berikutnya: lanjutkan A1 POS Mobile pada binding scope katalog/order/read
  yang dapat dipakai APK, lalu action policy step-up; tetap tanpa migration baru
  bila primitive schema yang ada sudah mencukupi.

## Batch 76 — Fase A1 POS Mobile: bearer outlet scope untuk order

- Waktu: 2026-09-03 18:31 WIB.
- Prioritas: P0 security/scope — endpoint daftar order dapat jatuh ke filter
  outlet `0`, sedangkan detail order berbasis ID belum memeriksa outlet token.
- Ringkasan diskusi auditor dan fixer: auditor memilih dua endpoint dengan risiko
  paling jelas dan dampak paling kecil, yaitu `orders()` dan `order_load($id)`.
  Fixer mempertahankan fallback web-session, tetapi membuat bearer memakai
  outlet terminal token secara authoritative. Review awal auditor menemukan
  fixture smoke tidak mengikuti kontrak nyata `find_order_draft()` karena
  memakai `outlet_id` datar. Fixer kemudian memperbaiki source dan fixture untuk
  memakai `header.outlet_id`; auditor mereview ulang dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - Bearer `orders()` tidak lagi mengambil outlet dari sesi kasir; endpoint
    memakai `mobileUser.outlet_id` dan menolak konteks outlet tidak valid dengan
    403 sebelum query order.
  - Bearer `order_load($id)` hanya mengembalikan order dengan `outlet_id` yang
    sama. Order lintas outlet atau tanpa outlet menghasilkan 404 generik tanpa
    payload order/customer/alamat.
  - Order dari terminal lain dalam outlet yang sama tetap dapat dilihat agar
    antrean kerja kasir tidak berubah.
  - Jalur web-session tanpa bearer tetap mempertahankan perilaku lama.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke test: lulus.
  - POS Mobile authorization smoke: PASS.
  - Smoke memakai struktur nyata `find_order_draft()` (`header` + `lines`) dan
    memverifikasi same outlet, terminal lain, cross/missing outlet, outlet `0`,
    serta fallback web.
  - `git diff --check` dan pemeriksaan trailing whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/backup/upload/credential/runtime
    yang diubah.
- Hasil review auditor: PASS. Scope outlet, 404 generik, pencegahan kebocoran
  payload, dan kompatibilitas web dinyatakan sesuai.
- Risiko sisa:
  - Endpoint order berbasis ID lain (`order_reversal_preview`, print-target,
    `payment_prepare`, `voucher_search`, dan writer) masih perlu scope.
  - `bootstrap`, `catalog`, dan `printers` masih menjadi batch berikutnya;
    validasi query database nyata belum dilakukan.
- Batch berikutnya: lanjutkan scope endpoint order berbasis ID yang tersisa,
  lalu resolver outlet untuk bootstrap/catalog/printer sebelum action policy
  step-up; tetap tanpa migration bila schema yang ada mencukupi.

## Batch 77 — Fase A1 POS Mobile: scope lima endpoint order-id

- Waktu: 2026-09-03 18:40 WIB.
- Prioritas: P0 security/scope — preview reversal, target cetak order,
  persiapan pembayaran, dan pencarian voucher menerima ID order tanpa guard
  outlet bearer.
- Ringkasan diskusi auditor dan fixer: auditor memilih lima endpoint dengan
  resolver order yang sama: `order_reversal_preview`, `order_reprint_targets`,
  `order_confirm_print_targets`, `payment_prepare`, dan `voucher_search`.
  Fixer menambahkan helper outlet bearer tanpa mengubah model atau schema.
  Review awal menemukan fixture smoke memakai bentuk order datar, sedangkan
  kontrak nyata memakai `header` dan `lines`. Fixer menyamakan source serta
  fixture ke `header.outlet_id`; auditor mereview ulang dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - Bearer harus memiliki `mobileUser.outlet_id` yang valid; outlet `0` ditolak
    dengan 403 sebelum resolver order.
  - Order tidak ada, outlet kosong, atau outlet berbeda menghasilkan 404 generik
    tanpa payload dan tanpa pemanggilan model preview/payment/voucher/printer
    downstream.
  - Terminal berbeda tetapi outlet sama tetap diizinkan; web-session fallback
    tidak berubah.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke test: lulus.
  - POS Mobile authorization smoke penuh: PASS.
  - Same outlet, terminal lain, cross outlet, missing order, outlet `0`,
    no-downstream, dan web fallback: PASS.
  - `git diff --check` dan pemeriksaan trailing whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/backup/upload/credential/runtime
    yang diubah.
- Hasil review auditor: PASS setelah perbaikan struktur fixture/model contract.
- Risiko sisa:
  - Endpoint document-ID void/refund/payment print dan writer void/refund/payment
    belum diberi resolver outlet.
  - `bootstrap`, `catalog`, dan `printers` masih belum sepenuhnya terikat outlet
    bearer; validasi database nyata belum dilakukan.
- Batch berikutnya: pilih satu resolver document-ID atau binding outlet
  bootstrap/catalog/printer, lalu lanjutkan action policy step-up secara terarah.

## Batch 78 — Fase A1 POS Mobile: scope writer finansial

- Waktu: 2026-09-03 19:01 WIB.
- Prioritas: P0 security/scope — bearer masih dapat mengirim `order_id` outlet
  lain ke writer void, refund, dan payment; replay payment juga berpotensi
  membaca sync-event sebelum outlet diverifikasi.
- Ringkasan diskusi auditor dan fixer: auditor memilih tiga writer finansial
  sebagai risiko tertinggi. Fixer menggunakan resolver order-outlet yang sudah
  lulus Batch 77 dan menempatkannya sebelum model, monitor task, serta seluruh
  lookup/insert/update sync-event. Auditor mereview diff dan smoke lalu PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - `order_void_save`, `order_refund_save`, dan `payment_save` memvalidasi
    `payload.order_id` terhadap outlet token setelah POST/auth/RBAC.
  - Outlet token tidak valid menghasilkan 403; order hilang atau lintas outlet
    menghasilkan 404 generik tanpa writer, monitor, atau sync-event downstream.
  - Payment replay lintas outlet tidak dapat membaca response event lama.
  - Terminal lain pada outlet sama dan fallback web-session tetap kompatibel.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke penuh: PASS.
  - Matrix tiga writer untuk same/cross/missing/outlet-zero/invalid order,
    no-downstream, replay, web fallback, serta GET/PUT 405: PASS.
  - `git diff --check` target batch: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: document-print void/refund/payment masih belum outlet-bound dan
  smoke belum menjadi probe HTTP/database nyata.
- Batch berikutnya: resolver outlet untuk tiga endpoint document-print mobile.

## Batch 79 — Fase A1 POS Mobile: scope document-print

- Waktu: 2026-09-03 19:08 WIB.
- Prioritas: P0 security/scope — endpoint cetak void, refund, dan payment
  menerima document ID tanpa memverifikasi outlet sebelum direct-print dapat
  membuat `print_attempt`.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan resolver dokumen ke
  order/outlet kanonik. Fixer menambahkan resolver model untuk VOID, REFUND,
  dan PAYMENT serta guard controller. Smoke awal memiliki source assertion
  yang salah format dan sudah diperbaiki; smoke penuh serta review auditor PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `application/models/Pos_model.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Resolver memakai allowlist tabel dokumen dan `INNER JOIN pos_order`; outlet
    serta terminal berasal dari order kanonik.
  - Payment deposit tanpa order, dokumen orphan/hilang, outlet nol, dan dokumen
    lintas outlet menghasilkan 404 generik tanpa direct-print/print-attempt.
  - Bearer dengan outlet token nol ditolak 403 sebelum resolver; terminal lain
    pada outlet sama dan fallback web-session tetap kompatibel.
- Validasi yang dijalankan:
  - `php -l` controller, model, dan smoke: lulus.
  - POS Mobile authorization smoke penuh: PASS.
  - Matrix tiga document-print, source-order assertion, no-downstream,
    payment-deposit, dan web fallback: PASS.
  - `git diff --check` target batch: lulus.
  - Tidak ada SQL/schema/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: UAT printer/config/schema nyata belum dilakukan.
- Batch berikutnya: binding outlet bearer untuk bootstrap, catalog, dan printer.

## Batch 80A — Fase A1 POS Mobile: scope bootstrap dan katalog

- Waktu: 2026-09-03 19:19 WIB.
- Prioritas: P0 security/scope — bearer dapat meminta outlet lain pada bootstrap
  atau katalog dan respons bootstrap membawa daftar outlet, terminal, serta sesi
  kasir global.
- Ringkasan diskusi auditor dan fixer: auditor memisahkan katalog/bootstrap dari
  kebijakan printer agar perubahan tetap kecil. Fixer mengikat request ke outlet
  dan terminal token, membatasi seluruh pilihan serta sesi pada respons, lalu
  menambah matrix smoke. Review menemukan `filter_options` top-level juga membawa
  outlet/terminal global; fixer ikut membatasinya. Auditor mereview ulang dan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Outlet/terminal binding bearer wajib positif; request outlet positif yang
    berbeda ditolak 403 sebelum model bisnis.
  - Katalog selalu memakai outlet perangkat. Bootstrap menetapkan default outlet
    dan terminal perangkat serta hanya mengembalikan pilihan outlet, terminal,
    dan sesi kasir yang cocok.
  - Pegawai tanpa sesi aktif tetap memperoleh respons 200 dengan sesi null/kosong;
    sesi aktif pada outlet/terminal lain ditolak.
  - Jalur web-session mempertahankan perilaku lama. Kebijakan printer belum diubah.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke penuh: 683 pemeriksaan PASS.
  - Matrix mismatch request/binding/session, no-session, scope response/catalog,
    no-downstream, dan web fallback: PASS.
  - `git diff --check` dan trailing-whitespace check: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: `order_reprint_printers` dan endpoint printer menunggu keputusan
  kebijakan printer global; smoke belum berupa probe HTTP/database/APK nyata.
- Batch berikutnya: authoritative order-upsert binding untuk `order_save`,
  `order_confirm`, dan `orders_push` sebelum model/sync-event.

## Batch 81 — Fase A1 POS Mobile: authoritative order-upsert

- Waktu: 2026-09-03 19:28 WIB.
- Prioritas: P0 security/scope — payload simpan/konfirmasi/push order masih dapat
  membawa outlet/terminal sendiri dan replay sync-event mendahului scope order.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan konteks perangkat harus
  menjadi sumber otoritatif sebelum writer/sync. Fixer menambahkan satu helper
  bersama untuk tiga endpoint dan binding replay event. Auditor mereview diff,
  kontrak field `id`, serta 715 smoke dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Outlet/terminal kosong diisi dari token; nilai positif berbeda ditolak 403.
  - Existing order wajib lolos resolver outlet sebelum writer dan sebelum akses
    sync-event. New order wajib memiliki sesi kasir perangkat yang cocok.
  - Replay memvalidasi konteks pada `request_json`; event lama tanpa konteks hanya
    diterima bila `server_order_id` lolos resolver outlet.
  - Respons replay lintas outlet/terminal tidak bocor; web-session tetap legacy.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke penuh: 715 pemeriksaan PASS.
  - Matrix new/same/cross/missing/no-session/payload mismatch/replay dan
    no-downstream: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: bukti HTTP/database/APK nyata dan kebijakan printer masih terbuka.
- Batch berikutnya: hapus fallback hak Purchase Order dari direct URL opening
  stock divisi dan export template.

## Batch 82 — Fase A1 Purchase: direct URL opening stock divisi

- Waktu: 2026-09-03 19:34 WIB.
- Prioritas: P0 authorization — permission Purchase Order dapat menjadi fallback
  untuk halaman dan export opening stock divisi.
- Ringkasan diskusi auditor dan fixer: auditor menemukan fallback lintas domain
  pada tiga endpoint. Fixer menghapus fallback dan membuat matrix role dengan
  tripwire downstream. Auditor mereview ulang dan PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `tools/tests/purchase_stock_opening_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Index/generated wajib `purchase.stock.division.index:view`.
  - Export template hanya menerima view atau create pada page stock divisi.
  - Permission Purchase Order saja tidak lagi membuka query, render, atau XLSX.
  - Perilaku opening warehouse tidak diubah.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - Smoke source/runtime: 46 pemeriksaan PASS.
  - Matrix order-only/none/anonymous/division-view/division-create dan
    no-downstream: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/view/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: probe HTTP/RBAC/database nyata belum dijalankan.
- Batch berikutnya: POST-only dan scoped CSRF untuk rebuild/reclassify Purchase.

## Batch 83 — Fase A1 Purchase: POST/CSRF maintenance

- Waktu: 2026-09-03 19:39 WIB.
- Prioritas: P0 request integrity — rebuild impact dan reclassify dapat dipanggil
  tanpa pembatasan metode dan tanpa token CSRF saat proteksi global nonaktif.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan token khusus domain
  maintenance Purchase, bukan token POS. Fixer menambah guard controller dan
  header pada dua halaman; smoke dibuat ringkas agar batch tidak melebar.
  Auditor mereview diff dan 23 pemeriksaan lalu PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `application/views/purchase/rebuild_impact_index.php`
  - `application/views/purchase/reclassify_profile_domain_index.php`
  - `tools/tests/purchase_maintenance_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Dua run hanya menerima POST; metode lain mendapat JSON 405 dan `Allow: POST`.
  - Token session 64-hex dikirim hanya lewat header khusus dan dibandingkan
    fail-closed dengan `hash_equals`; token tidak valid mendapat 403 generik.
  - Urutan tetap RBAC edit, request guard, payload, lalu model; actor, IP, dan
    pemulihan `db_debug` melalui `finally` dipertahankan.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan smoke: lulus.
  - Smoke source/helper: 23 pemeriksaan PASS.
  - Matrix method/token/view-only/success/error/exception dan source view: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session/database nyata belum diuji.
- Batch berikutnya: Master generic form `store/update` dengan POST-only dan
  scoped CSRF.

## Batch 84A — Fase A1 Master: CSRF form generik

- Waktu: 2026-09-03 19:45 WIB.
- Prioritas: P0/P1 request integrity — store/update Master berjalan saat CSRF
  global nonaktif dan belum mewajibkan metode POST serta token lokal.
- Ringkasan diskusi auditor dan fixer: auditor memecah Master menjadi form,
  mutasi inline, dan generator holiday. Fixer membuat fondasi token domain Master,
  memasangnya pada form generik, serta menjaga urutan redirect/config/RBAC lama.
  Auditor mereview 18 pemeriksaan dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master.php`
  - `application/views/master/form.php`
  - `tools/tests/master_generic_form_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Create/edit menerbitkan token session 64-hex setelah config dan RBAC.
  - Store/update wajib POST dan token valid sebelum validation, lookup row,
    payload, upload, insert/update, atau sinkronisasi employee.
  - Form multipart produk tetap bekerja; redirect/flash dan contract redirect
    lama dipertahankan.
- Validasi yang dijalankan:
  - `php -l` controller, form, dan smoke: lulus.
  - Smoke source/helper: 18 pemeriksaan PASS.
  - GET/PUT/token invalid/no-writer, valid token, header, token generator, dan
    multipart: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: DB/browser nyata belum diuji; toggle/stock mode/reorder/holiday
  belum memakai guard baru.
- Batch berikutnya: mutasi inline Master `toggle`, `stock_mode`, dan `reorder`.

## Batch 84B — Fase A1 Master: CSRF mutasi inline

- Waktu: 2026-09-03 19:50 WIB.
- Prioritas: P0/P1 request integrity — toggle dan stock mode dapat dimutasi lewat
  GET, sedangkan reorder belum memiliki CSRF lokal.
- Ringkasan diskusi auditor dan fixer: fixer memakai token Master dari 84A,
  menambah respons error sesuai tipe request, memperbarui AJAX, dan mengganti
  link toggle legacy dengan form POST. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master.php`
  - `application/views/master/index.php`
  - `application/views/master/material_index.php`
  - `tools/tests/master_generic_inline_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Toggle, stock mode, dan reorder memeriksa RBAC lalu POST/token sebelum
    lookup, schema, payload JSON, transaksi, atau writer.
  - AJAX mengirim header CSRF dan tetap menerima JSON; form fallback memakai
    hidden token dan mempertahankan flash/redirect.
  - Entity/reorder yang tidak didukung tetap memakai kontrak error lama.
- Validasi yang dijalankan:
  - `php -l` empat file: lulus.
  - Smoke inline 15 pemeriksaan dan regresi form 18 pemeriksaan: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser nyata dan regenerasi session belum diuji.
- Batch berikutnya: CSRF untuk generator hari libur tahunan Master.

## Batch 84C — Fase A1 Master: CSRF generator hari libur

- Waktu: 2026-09-03 19:54 WIB.
- Prioritas: P1 request integrity — generator massal hari libur hanya memeriksa
  metode, tetapi belum memakai token saat CSRF global nonaktif.
- Ringkasan diskusi auditor dan fixer: generator dipisahkan karena membaca schema
  `core` dan menjalankan banyak upsert. Fixer memakai token Master yang sama;
  auditor mereview urutan guard dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master.php`
  - `application/views/master/index.php`
  - `tools/tests/master_att_holiday_generate_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Permission create lalu POST/token diverifikasi sebelum tahun, query sumber,
    atau upsert.
  - Form Generate 1 Tahun membawa hidden token ter-escape.
  - Validasi tahun, flash, redirect, sumber core, dan alur upsert lama tetap ada.
- Validasi yang dijalankan:
  - `php -l` tiga file: lulus.
  - Smoke holiday 9, regresi 84A 18, dan regresi 84B 15: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: upsert massal lama belum transaksional; DB/browser nyata belum diuji.
- Batch berikutnya: integrity favorite sidebar (POST/CSRF, permission, ownership,
  dan cache invalidation).

## Batch 85A — Fase A1 Sidebar: integrity favorite user

- Waktu: 2026-09-03 20:09 WIB.
- Prioritas: P0/P1 authorization/request integrity — endpoint favorite hanya
  memeriksa AJAX, pin menerima menu arbitrer, favorite lama dapat membocorkan URL,
  dan cache reorder dapat stale.
- Ringkasan diskusi auditor dan fixer: fixer menambah token khusus, predicate
  akses kanonis, ownership reorder, dan invalidasi cache. Review pertama auditor
  menemukan reload penuh, subset reorder, dan tombol UI yang belum memakai
  predicate. Ketiganya diperbaiki; review ulang PASS.
- File berubah:
  - `application/controllers/Sidebar.php`
  - `application/models/Menu_model.php`
  - `application/core/MY_Controller.php`
  - `application/views/layout/footer.php`
  - `application/views/layout/sidebar.php`
  - `assets/js/app.js`
  - `tools/tests/sidebar_favorite_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Pin/unpin/reorder wajib POST dan header CSRF khusus sebelum payload/model.
  - Menu pin harus aktif, memiliki link nyata, dan lolos permission kanonis;
    favorite historis yang izinnya dicabut tidak ditampilkan.
  - Reorder wajib memuat exact set favorite milik user; subset/duplikat/asing
    ditolak sebelum transaksi. Unpin selalu dibatasi user+menu.
  - Cache user dibersihkan setelah sukses, fingerprint memuat urutan, dan UI
    diperbarui lokal tanpa reload halaman.
- Validasi yang dijalankan:
  - `php -l` seluruh file PHP terkait dan `node --check`: lulus.
  - Smoke favorite setelah revisi: 23 pemeriksaan PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/config/database/runtime yang diubah.
- Hasil review auditor: PASS setelah tiga required fixes.
- Risiko sisa: reorder dua tab bersamaan masih last-write-wins; tidak lintas-user.
- Batch berikutnya: POST/CSRF terpisah untuk penyimpanan struktur sidebar.

## Batch 85B — Fase A1 Sidebar: integrity struktur superadmin

- Waktu: 2026-09-03 20:11 WIB.
- Prioritas: P0/P1 request integrity — save structure superadmin belum POST-only
  dan belum memiliki token CSRF.
- Ringkasan diskusi auditor dan fixer: token structure dipisahkan dari token
  favorite karena boundary aktornya berbeda. Fixer menjaga kontrak payload/AJAX;
  auditor mereview urutan guard hingga invalidasi cache dan PASS.
- File berubah:
  - `application/controllers/Sidebar.php`
  - `application/views/sidebar/manage.php`
  - `tools/tests/sidebar_structure_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Manage menerbitkan token setelah superadmin gate.
  - Save structure memeriksa superadmin, POST, AJAX, dan header token sebelum
    sidebar type, JSON tree, transaksi, model, atau cache clear.
  - Method salah mendapat 405 + `Allow: POST`; token salah 403 generik; JSON
    rusak 400 tanpa transaksi.
- Validasi yang dijalankan:
  - `php -l` tiga file: lulus.
  - Smoke structure 12 dan regresi favorite 23: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser superadmin nyata belum diuji.
- Batch berikutnya: POST/CSRF untuk CRUD menu sidebar superadmin.

## Batch 85C — Fase A1 Sidebar: integrity CRUD admin

- Waktu: 2026-09-03 20:18 WIB.
- Prioritas: P0/P1 request integrity — CRUD/toggle menu superadmin belum memakai
  method dan token guard yang sama dengan penyimpanan struktur.
- Ringkasan diskusi auditor dan fixer: token struktur dipakai ulang sebagai satu
  boundary administrasi sidebar, dengan kanal hidden field untuk form dan header
  untuk AJAX. Auditor mereview urutan empat endpoint dan PASS.
- File berubah:
  - `application/controllers/Sidebar.php`
  - `application/views/sidebar/manage.php`
  - `tools/tests/sidebar_admin_menu_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Store/update/delete memeriksa superadmin dan POST/hidden token sebelum
    payload/lookup/query/write.
  - Toggle memeriksa superadmin, POST/header token, dan AJAX sebelum lookup/write.
  - Dua pemanggil toggle pada editor tree dan tabel mengirim header token.
- Validasi yang dijalankan:
  - PHP lint, Node check, dan diff/whitespace: lulus.
  - Smoke CRUD 24, regresi favorite 23, dan structure 12: PASS.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: cycle/parent lintas sidebar pada data admin belum dibatasi penuh.
- Batch berikutnya: POST/CSRF khusus antrean availability POS.

## Batch 86 — Fase A1 POS: CSRF antrean availability

- Waktu: 2026-09-03 20:23 WIB.
- Prioritas: P1 request integrity — proses/retry antrean availability sudah POST,
  tetapi belum terlindungi saat CSRF global nonaktif.
- Ringkasan diskusi auditor dan fixer: token dipisahkan dari transaksi POS karena
  boundary operasional berbeda. Fixer menjaga form/redirect lama dan endpoint CLI;
  auditor mereview lalu PASS.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/availability_queue_index.php`
  - `tools/tests/pos_availability_queue_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Process/retry memeriksa edit RBAC, POST, dan hidden token sebelum payload,
    load service, atau pemrosesan job.
  - Form proses dan seluruh form retry membawa token khusus sambil mempertahankan
    CSRF global bila kelak diaktifkan serta filter return.
  - Worker cron CLI tidak memakai boundary CSRF web.
- Validasi yang dijalankan:
  - PHP lint dan diff/whitespace: lulus.
  - Smoke B86 11 dan regresi inventory availability 19: PASS.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session nyata belum diuji.
- Batch berikutnya: POST/CSRF untuk tiga writer rekonsiliasi inventory divisi.

## Batch 87 — Fase A1 Inventory: CSRF rekonsiliasi stok divisi

- Waktu: 2026-09-03 20:30 WIB.
- Prioritas: P0/P1 request integrity — repair material ID, repair profile, dan
  merge profile masih dapat dijangkau melalui direct request setelah RBAC tanpa
  pembatasan method atau token CSRF khusus.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan satu boundary khusus
  untuk tiga writer rekonsiliasi. Fixer menerapkan urutan edit RBAC, POST/token,
  payload, lalu model sambil mempertahankan perilaku `division_id=0` dan kontrak
  respons lama. Auditor mereview ulang source, test, dan hasil validasi lalu PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `application/views/purchase/stock_division_reconcile_index.php`
  - `tools/tests/inventory_division_reconcile_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Tiga writer hanya menerima POST dan header token session khusus
    `X-Inventory-Reconcile-CSRF` sebelum membaca body atau memanggil model.
  - Method selain POST mendapat JSON 405 dan `Allow: POST`; token tidak valid
    mendapat JSON 403 generik tanpa menjalankan pekerjaan bisnis.
  - Wrapper JavaScript khusus hanya dipakai oleh tiga endpoint tersebut;
    wrapper transaksi POS dan caller runtime-job tetap terpisah.
- Validasi yang dijalankan:
  - PHP lint tiga file: lulus.
  - Smoke Batch 87: 28 pemeriksaan PASS.
  - Regresi purchase maintenance: 23 PASS; POS runtime-job: 308 PASS.
  - `git diff --check`: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session/database nyata belum diuji.
- Batch berikutnya: POST/CSRF untuk tujuh writer berisiko tinggi pada System
  Tools (backup, konfigurasi MySQL, replication, sync, dan failover).

## Batch 88 — Fase A1 System Tools: CSRF aksi berisiko tinggi

- Waktu: 2026-09-03 20:36 WIB.
- Prioritas: P0 request integrity — aksi backup, konfigurasi MySQL, setup master,
  initial sync, failover, dan restart replication dapat mencapai side effect
  setelah RBAC tanpa pembatasan method dan token khusus.
- Ringkasan diskusi auditor dan fixer: auditor memilih tujuh writer dalam satu
  boundary administrasi System Tools. Fixer menambahkan token session khusus dan
  header pada dua wrapper view. Auditor menjalankan review ulang dan menyatakan
  PASS; konfigurasi database lokal milik pengguna tidak direvert.
- File berubah:
  - `application/controllers/System_tools.php`
  - `application/views/system/dbtools.php`
  - `application/views/system/settings.php`
  - `tools/tests/system_tools_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - `settings_save`, run backup, apply MySQL config, setup master, initial sync,
    failover, dan restart replication wajib edit RBAC, POST, serta header token
    `X-System-Tools-CSRF` sebelum payload, query, file, script, atau service.
  - Method selain POST mendapat JSON 405 + `Allow: POST`; token invalid mendapat
    JSON 403 generik tanpa side effect. Endpoint baca tetap kompatibel.
- Validasi yang dijalankan:
  - PHP lint empat file: lulus.
  - Smoke Batch 88: 67 PASS; backup source isolation: 32 PASS.
  - `git diff --check`: lulus.
  - `deployment_secret_config_smoke.php` tetap gagal pada `database.php` karena
    pengguna memilih konfigurasi database eksplisit; file itu di luar scope B88.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session serta operasi backup/replication nyata belum diuji.
- Temuan lanjutan: smoke POS Mobile berhenti pada kegagalan login pertama dan
  membuktikan binding outlet/terminal B75–81 tidak lengkap di source aktif;
  statusnya dibuka kembali sebagai blocker A1.
- Batch berikutnya: Batch 89a memulihkan anti-oracle login dan identitas bearer
  terminal/outlet sebelum reader/writer POS Mobile dipulihkan bertahap.

## Batch 89a — Fase A1 POS Mobile: pemulihan device identity

- Waktu: 2026-09-03 20:42 WIB.
- Prioritas: P0 security regression — source aktif membedakan kegagalan password
  dan terminal serta belum membawa identitas outlet/terminal registry pada
  bearer yang sudah tervalidasi.
- Ringkasan diskusi auditor dan fixer: auditor membuka kembali bukti Batch 75–81
  setelah smoke monolitik berhenti pada failure pertama. Fixer memulihkan
  anti-oracle login dan binding identity dalam batch kecil; duplikat device key
  aktif maupun nonaktif ikut ditolak. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_device_binding_recovery_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Credential salah dan terminal invalid mendapat body 401 identik tanpa code
    pembeda, echo device key, atau token insert.
  - Registry wajib tepat satu row untuk device key, aktif, dan memiliki ID
    terminal/outlet positif.
  - Bearer menyimpan terminal/outlet registry sebelum memperbarui `last_seen`;
    seluruh binding invalid berhenti tanpa update tersebut.
- Validasi yang dijalankan:
  - PHP lint controller/test: lulus.
  - Smoke recovery: 33 PASS; inventory-control regression: 312 PASS.
  - `git diff --check`: lulus.
  - Smoke POS Mobile monolitik maju melewati login/token lalu berhenti pada gap
    reader berikutnya: bootstrap outlet mismatch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: endpoint reader dan writer/replay belum seluruhnya terikat ke
  outlet/terminal bearer; smoke monolitik belum PASS penuh.
- Batch berikutnya: 89b memulihkan binding bootstrap dan katalog.

## Batch 89b — Fase A1 POS Mobile: pemulihan bootstrap/katalog

- Waktu: 2026-09-03 20:55 WIB.
- Prioritas: P0 scope regression — bootstrap dan katalog pada source aktif masih
  dapat memakai outlet request/session/default, bukan identitas bearer.
- Ringkasan diskusi auditor dan fixer: auditor membatasi batch pada dua reader.
  Fixer mengikat respons dan query katalog ke outlet/terminal perangkat sambil
  mempertahankan fallback web. Review menemukan kontradiksi status pada harness;
  auditor menetapkan registry invalid ditolak 401 oleh autentikasi dan test
  monolitik diperbaiki tanpa melemahkan source. Review final PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_reader_outlet_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Bearer bootstrap/katalog hanya memakai outlet dan terminal hasil registry;
    request context berbeda ditolak sebelum model.
  - Bootstrap memfilter pilihan outlet/terminal serta sesi ke perangkat aktif;
    tanpa sesi tetap 200 dengan sesi null dan daftar kosong.
  - Web-session tetap memakai perilaku legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89a: 33 PASS; smoke 89b: 21 PASS; inventory-control: 312 PASS.
  - Monolithic POS smoke maju sampai gap writer `order_confirm`.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS setelah koreksi harness 401; tidak ada required fix.
- Risiko sisa: daftar/detail order dan writer/cashier/replay belum dipulihkan;
  smoke POS Mobile monolitik belum PASS penuh.
- Batch berikutnya: 89c memulihkan binding `orders` dan `order_load`.

## Batch 89c — Fase A1 POS Mobile: pemulihan reader order

- Waktu: 2026-09-03 20:59 WIB.
- Prioritas: P0 scope regression — daftar order masih mengambil outlet dari
  sesi kasir dan detail order belum memeriksa outlet bearer.
- Ringkasan diskusi auditor dan fixer: auditor memisahkan reader dari writer.
  Fixer membatasi daftar/detail ke outlet perangkat tanpa memerlukan sesi kasir
  aktif untuk membaca daftar. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_order_reader_binding_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Daftar order bearer selalu memakai outlet registry, bukan query atau sesi.
  - Detail missing, outlet nol, atau lintas outlet mendapat 404 generik identik
    tanpa isi order; terminal lain pada outlet sama tetap boleh.
  - Web-session tetap memakai perilaku lama.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89c: 13 PASS; 89a: 33; 89b: 21; inventory-control: 312 PASS.
  - `git diff --check`: lulus untuk scope batch.
  - Smoke monolitik masih berhenti lebih awal pada gap writer `order_confirm`,
    sehingga matrix reader-ID monolitik belum menjadi bukti final.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: writer finansial, kasir, draft/upsert/replay belum terikat penuh.
- Batch berikutnya: 89d.1 financial mutation/replay.

## Batch 89d.1 — Fase A1 POS Mobile: financial mutation/replay

- Waktu: 2026-09-03 21:05 WIB.
- Prioritas: P0 financial scope — payment, void, refund, dan replay idempotency
  belum membuktikan order/outlet bearer sebelum writer atau sync-event.
- Ringkasan diskusi auditor dan fixer: auditor memisahkan tiga writer finansial
  dari alur kasir dan upsert. Fixer menambah resolver order kanonis serta bukti
  replay yang konsisten. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_financial_writer_binding_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Bearer payment/void/refund memuat order dan mencocokkan outlet sebelum writer
    atau monitor.
  - Payment mengikat order sebelum membaca, membuat, atau mengulang sync-event.
    Replay tanpa bukti order aman atau dengan konteks berbeda mendapat 404
    generik tanpa mengembalikan response tersimpan.
  - Web-session tetap menggunakan perilaku lama.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89d.1: 26 PASS; 89a: 33; 89b: 21; 89c: 13;
    inventory-control: 312 PASS.
  - `git diff --check`: lulus untuk scope batch.
  - Smoke monolitik tetap berhenti lebih awal pada gap `order_confirm`.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: sesi kasir dan draft/upsert/confirm belum dipulihkan; smoke
  monolitik belum PASS penuh.
- Batch berikutnya: 89d.2 binding sesi kasir terminal/outlet.

## Batch 89d.2 — Fase A1 POS Mobile: cashier terminal-session

- Waktu: 2026-09-03 21:12 WIB.
- Prioritas: P0 session scope — buka/status/preview/tutup kasir belum memastikan
  sesi OPEN berasal dari employee, outlet, dan terminal bearer yang sama.
- Ringkasan diskusi auditor dan fixer: fixer menambah resolver sesi scoped.
  Review pertama menemukan payload buka kasir belum selalu ditimpa dengan
  context authoritative saat sesi cocok sudah ada; fixer memperbaiki dan auditor
  final menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_cashier_session_binding_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Status hanya menampilkan sesi perangkat sendiri; tanpa sesi tetap 200 dengan
    `session: null` dan daftar kosong.
  - Buka kasir menolak context positif berbeda lalu selalu menulis outlet dan
    terminal bearer ke payload sebelum recon/open.
  - Preview/tutup menolak sesi missing, bukan OPEN, atau terminal/outlet berbeda
    sebelum recon, close, dan print.
  - Web-session tetap legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89d.2 setelah revisi: 23 PASS; 89a: 33; 89b: 21; 89c: 13;
    89d.1: 26; inventory-control: 312 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS setelah satu required fix.
- Risiko sisa: draft/upsert/confirm/replay order belum dipulihkan; smoke
  monolitik belum PASS penuh.
- Batch berikutnya: 89d.3 binding draft, confirm, dan orders_push.

## Batch 89d.3 — Fase A1 POS Mobile: draft, confirm, dan push order

- Waktu: 2026-09-03 21:24 WIB.
- Prioritas: P0 order scope — simpan/konfirmasi/push order belum konsisten
  memakai outlet dan terminal bearer serta sesi kasir OPEN perangkat.
- Ringkasan diskusi auditor dan fixer: fixer memulihkan konteks authoritative
  untuk order baru dan binding order lama. Auditor memeriksa replay serta
  harness sesi kasir, lalu menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_draft_upsert_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - `order_save`, `order_confirm`, dan `orders_push` mengikat order ke outlet
    bearer serta memerlukan sesi kasir OPEN yang sesuai employee/perangkat.
  - Order baru selalu menerima outlet/terminal authoritative dari bearer;
    order lama yang missing atau lintas outlet ditolak sebelum writer.
  - Replay push hanya diterima bila jenis event, order, outlet, terminal,
    server event, dan response tersimpan membuktikan konteks yang sama.
  - Web-session tetap memakai perilaku legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89d.3: 49 PASS; seluruh smoke terarah 89a–89d.3: 165 PASS;
    inventory-control: 312 PASS.
  - Smoke POS Mobile monolitik maju sampai 237 PASS dan menemukan residual
    nyata pada `order_reversal_preview`.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; harness sudah selaras dengan resolver sesi model.
- Risiko sisa: lima reader aksi berbasis order dan tiga document-print belum
  seluruhnya membuktikan outlet order kanonis; smoke monolitik belum tamat.
- Batch berikutnya: 89e binding reader aksi berbasis `order_id`.

## Batch 89e — Fase A1 POS Mobile: reader aksi berbasis order

- Waktu: 2026-09-03 21:30 WIB.
- Prioritas: P0 order scope — preview reversal, reprint, target cetak konfirmasi,
  persiapan pembayaran, dan voucher masih dapat membaca order lintas outlet.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan urutan auth, RBAC,
  resolver order kanonis, lalu downstream. Fixer menerapkan satu resolver yang
  sama pada lima endpoint; auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_order_action_reader_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Bearer hanya dapat membaca aksi untuk order dengan outlet yang sama.
  - Order missing, outlet nol, atau lintas outlet mendapat 404 generik sebelum
    preview, query voucher, payment prepare, atau pencarian target cetak.
  - Order dari terminal lain pada outlet sama tetap boleh dan reader tidak
    mensyaratkan sesi kasir aktif; web-session tetap legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89e: 54 PASS; seluruh smoke terarah 89a–89e: 219 PASS;
    inventory-control: 312 PASS.
  - Smoke POS Mobile monolitik maju sampai 273 PASS dan menemukan residual
    pada tiga endpoint cetak berbasis dokumen.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: dokumen void/refund/payment belum diikat melalui order/outlet
  kanonis; smoke monolitik belum tamat.
- Batch berikutnya: 89f binding document-print.

## Batch 89f — Fase A1 POS Mobile: binding dokumen cetak

- Waktu: 2026-09-03 21:36 WIB.
- Prioritas: P0 print scope — ID void, refund, atau payment belum membuktikan
  parent order dan outlet bearer sebelum membuat target/attempt cetak.
- Ringkasan diskusi auditor dan fixer: auditor mengarahkan pemakaian resolver
  dokumen kanonis yang sudah ada. Fixer memasang guard pada tiga endpoint dan
  menyelaraskan matrix lama; auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_print_document_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Dokumen void/refund/payment bearer di-resolve ke parent order dan outlet
    sebelum `direct_print_targets*` dijalankan.
  - Dokumen/order missing, outlet nol, atau lintas outlet mendapat 404 generik
    tanpa target, print-attempt, atau marker response.
  - Terminal asal berbeda pada outlet sama tetap boleh; web-session tetap legacy.
  - Resolver model yang digunakan sudah memulihkan `db_debug` melalui `finally`.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89f: 34 PASS; seluruh smoke terarah 89a–89f: 253 PASS;
    inventory-control: 312 PASS.
  - Smoke POS Mobile monolitik selesai penuh: 717 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: printer discovery/test, surface API tambahan, step-up approval,
  serta UAT token/perangkat/APK nyata belum ditutup.
- Batch berikutnya: 90 mengamankan runtime sync POS dengan POST/CSRF.

## Batch 90 — Fase A1: POST/CSRF runtime sync POS

- Waktu: 2026-09-03 21:42 WIB.
- Prioritas: P0 request integrity — `order_runtime_sync` masih dapat memicu
  refresh/job tanpa pemeriksaan metode dan token transaksi POS.
- Ringkasan diskusi auditor dan fixer: auditor menemukan satu mutator runtime
  yang tertinggal. Fixer menambah guard scoped serta mengalihkan pemanggil UI
  ke wrapper bertoken; auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/online_food_orders.php`
  - `application/views/pos/self_order_orders.php`
  - `tools/tests/pos_runtime_sync_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Runtime sync kini berurutan RBAC, POST/token CSRF, payload, lalu refresh.
  - GET/PUT/PATCH serta token hilang/salah berhenti sebelum payload atau job.
  - Pemanggil online-food dan self-order mengirim header transaksi yang sama;
    pemanggil verify/reject pada kedua layar juga diselaraskan.
- Validasi yang dijalankan:
  - PHP lint: lulus; smoke fokus: 19 PASS.
  - Regression: transaction-CSRF 1693; runtime trigger 247; mutation 308;
    stock-live 237; monitor 38; delete job 121; dismiss job 257;
    dismiss snapshot 504; retry snapshot 391 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: policy export-existing terlalu longgar dan matrix direct-URL
  gabungan belum menjadi quality gate; UAT runtime nyata tetap diperlukan.
- Batch berikutnya: 91 mewajibkan permission export untuk data opening existing.

## Batch 91 — Fase A1: permission export opening divisi

- Waktu: 2026-09-03 21:48 WIB.
- Prioritas: P0 data access — unduhan data opening existing masih menerima
  permission view sebagai pengganti permission export.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan kebijakan strict
  export tanpa menambah grant role. Fixer mengubah guard server dan visibilitas
  UI; satu kegagalan smoke awal pada conditional view diperbaiki, lalu auditor
  menyatakan PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `application/views/purchase/stock_opening_division_index.php`
  - `tools/tests/purchase_stock_opening_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Export existing wajib `inventory.stock.opening.division:export` sebelum
    input, query, loader spreadsheet, atau output XLSX.
  - Index mengirim capability export dan form tidak dirender bagi view-only.
  - JavaScript tetap aman ketika form export existing tidak tersedia.
  - Export template tetap memakai kontrak lama view atau create.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Authorization smoke setelah revisi: 66 PASS.
  - Purchase maintenance regression: 23 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: role kustom view-only sengaja kehilangan unduhan existing dan
  perlu diverifikasi pada UAT permission deployment; tidak ada seed diubah.
- Batch berikutnya: 92 membentuk matrix direct-URL guard gabungan A1.

## Batch 93 — Fase A1 POS Mobile: printer bearer binding

- Waktu: 2026-09-03 22:13 WIB.
- Prioritas: P0 scope/secret — discovery printer memakai filter request setelah
  pagination dan test print belum membuktikan outlet/terminal bearer.
- Ringkasan diskusi auditor dan fixer: auditor menolak helper permission tak
  bertuan dan smoke statis sebagai bukti cukup. Backend finance_fixer gagal 404
  berulang, sehingga main agent menerapkan scope yang sudah diarahkan; auditor
  meminta behavioral test lalu menyatakan PASS setelah revisi.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `application/models/Pos_print_model.php`
  - `tools/tests/pos_mobile_printer_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - List printer bearer memakai outlet/terminal token sebelum count/pagination;
    hanya koneksi aktif exact-outlet dan route runtime-compatible diproyeksikan.
  - Test print memerlukan permission edit, bukan view, lalu memeriksa koneksi
    dan route exact/generic yang kompatibel sebelum preview atau attempt.
  - Attempt selalu mencatat outlet/terminal bearer; query context berbeda
    ditolak 403 dan objek printer lintas scope mendapat 404 generik.
  - Password Wi-Fi, agent host, dan port dirahasiakan secara rekursif dari
    response discovery/test bearer. Web-session mempertahankan lookup legacy.
- Validasi yang dijalankan:
  - PHP lint dan scoped `git diff --check`: lulus.
  - Smoke printer: 32 pemeriksaan behavioral/struktur; bersama monolitik dalam
    invocation terarah menghasilkan 750 PASS tanpa warning.
  - Smoke terarah POS Mobile 89a–89f + printer: 275 PASS; monolitik mandiri
    setelah expectation policy diperbarui: 718 PASS.
- Hasil review auditor: PASS setelah required behavioral coverage ditambahkan.
- Risiko sisa: general printer settings tetap global sesuai kontrak model;
  UAT printer/APK nyata dan surface API mobile tambahan masih diperlukan.
- Batch berikutnya: tuntaskan matrix A1.92 lalu audit/bind surface API tambahan.

## Batch 92 — Fase A1: matrix direct-URL gabungan

- Waktu: 2026-09-03 22:17 WIB; disahkan setelah Batch 93 masuk ke matrix.
- Prioritas: quality gate security — smoke A1 tersebar dan belum mempunyai satu
  runner yang memastikan route, method, serta seluruh family guard tetap aktif.
- Ringkasan diskusi auditor dan fixer: tiga percobaan finance_fixer gagal 404
  sebelum membuat patch, sehingga main agent membuat runner sesuai manifest
  auditor. Review pertama meminta coverage printer dan regex lebih ketat; setelah
  revisi auditor menyatakan PASS.
- File berubah:
  - `tools/tests/a1_direct_url_guard_matrix_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Manifest mencatat family, URI, target controller, method, policy, dan smoke.
  - Route assignment serta public method divalidasi dengan regex line-anchored.
  - Setiap smoke unik dijalankan satu kali pada proses PHP terisolasi; kegagalan
    menyebut family dan file sumber tanpa bergantung pada teks sukses.
  - Matrix mencakup Master, enam family Master Relation, Sidebar, Purchase,
    reconcile divisi, System Tools, POS web, POS Mobile, dan printer.
- Validasi yang dijalankan:
  - PHP lint dan scoped `git diff --check`: lulus.
  - 22 kontrak manifest dan 21 source smoke: PASS dalam 0,28 detik.
- Hasil review auditor: PASS setelah dua required fixes.
- Risiko sisa: matrix adalah guard A1, bukan pengganti browser/UAT akun, database,
  perangkat, proxy HTTPS, atau APK nyata.
- Batch berikutnya: audit dan binding surface API POS Mobile tambahan.

## Batch 94 — A2 period guard fail-closed

- Waktu: 2026-09-04 05:20 WIB.
- Prioritas: period guard tidak boleh mengizinkan writer ketika schema fondasi
  belum tersedia atau tanggal transaksi ambigu.
- Ringkasan auditor/fixer: auditor memilih kegagalan schema sebagai risiko
  tertinggi; fixer mengubah guard menjadi fail-closed dan memperketat tanggal.
- File berubah: `application/libraries/InventoryPeriodGuard.php`,
  `tools/tests/inventory_period_guard_smoke.php`.
- Perubahan utama: kode stabil `INVENTORY_PERIOD_SCHEMA_NOT_READY`; tanggal
  wajib kalender `Y-m-d` yang valid.
- Validasi: PHP lint, 13 period smoke, dan `git diff --check` lulus.
- Review auditor: PASS.
- Risiko sisa: race period dengan transaksi masih ditangani pada Batch 98.
- Batch berikutnya: propagasi cache HPP item-centric.

## Batch 95 — A2 cache HPP item-centric

- Waktu: 2026-09-04 05:30 WIB.
- Prioritas: koreksi material berbasis `item_id` dapat melewatkan rebuild HPP.
- Ringkasan auditor/fixer: fixer menambah resolver dampak produk langsung,
  formula component, component bertingkat, dan mapping material legacy.
- File berubah: `application/libraries/InventoryValueReconciliationService.php`,
  `application/libraries/PosAvailabilityRebuildService.php`, serta dua smoke A2.
- Perubahan utama: POST/VOID memilih handler material/item/component secara
  eksplisit dan de-duplicate queue/rebuild.
- Validasi: PHP lint; smoke reconciliation 23 dan item cache 9; diff-check lulus.
- Review auditor: PASS.
- Risiko sisa: data mismatch historis tidak disentuh.
- Batch berikutnya: invariant reversal POS.

## Batch 96 — A2 invariant reversal POS

- Waktu: 2026-09-04 05:40 WIB.
- Prioritas: quantity reversal mentah dapat mencapai rollback fisik sebelum
  cap dan status header dapat salah menjadi penuh.
- Ringkasan auditor/fixer: keputusan reversal dinormalisasi terhadap residual
  snapshot yang terkunci sebelum mutasi stok.
- File berubah: `application/libraries/PosOrderStockService.php`,
  `application/libraries/PosStockCommitService.php`, dan smoke reversal.
- Perubahan utama: quantity di-cap, policy invalid/duplikat/unknown ditolak,
  dan status partial/full dihitung dari seluruh residual line.
- Validasi: PHP lint; reversal invariant 8, POS transaction 1691, monitor 38;
  diff-check lulus.
- Review auditor: PASS.
- Risiko sisa: anomali POS historis tidak direplay.
- Batch berikutnya: refresh availability setelah reversal.

## Batch 97 — A2 cache setelah void/refund POS

- Waktu: 2026-09-04 05:50 WIB.
- Prioritas: void/refund dapat selesai tetapi cache availability tetap stale.
- Ringkasan auditor/fixer: shared `Pos_model` mengumpulkan outlet/produk lalu
  refresh satu kali setelah commit; controller web tidak menggandakan refresh.
- File berubah: `application/models/Pos_model.php`,
  `application/controllers/Pos.php`, dan smoke cache reversal.
- Perubahan utama: web dan APK memperoleh metadata rebuild/warning yang sama;
  kegagalan cache tidak membatalkan transaksi reversal yang sudah commit.
- Validasi: PHP lint; smoke cache 20, transaction 1691, invariant reversal dan
  monitor lulus; diff-check lulus.
- Review auditor: PASS. `Pos_mobile.php` dan `routes.php` tidak diubah.
- Risiko sisa: UAT APK mengikuti build milik pemilik.
- Batch berikutnya: barrier periode transaksional.

## Batch 98 — A2 barrier periode transaksional

- Waktu: 2026-09-04 06:16 WIB.
- Prioritas: close/rollover dapat beradu dengan writer stok dan dua pembuat
  periode baru dapat deadlock atau salah dianggap sukses.
- Ringkasan auditor/fixer: dua review auditor menemukan range race lalu
  missing-key creation race; fixer menutup keduanya secara bertahap.
- File berubah: `application/libraries/InventoryPeriodGuard.php`,
  `application/libraries/InventoryLedger.php`,
  `application/libraries/InventoryValueReconciliationService.php`,
  `application/libraries/PosOrderStockService.php`, dan atomic period smoke.
- Perubahan utama: writer mengunci periode di dalam transaksi; historical write
  memakai exact+newer-range lock; current-month creation memakai atomic upsert
  lalu exact lock; status close memakai CAS dan transaksi gagal selalu ditolak.
- Validasi: PHP lint; period guard dan 17 atomic checks lulus; regression value,
  reversal, POS transaction, dan monitor lulus; diff-check lulus.
- Review auditor: PASS setelah dua blocker diperbaiki.
- Risiko sisa: asumsi lock dibuktikan oleh engine InnoDB dan unique key staging.
- Batch berikutnya: matrix dan probe final A2.

## Batch 99 — Gerbang akhir Fase A2

- Waktu: 2026-09-04 06:20 WIB.
- Prioritas: satu bukti gabungan untuk menutup A2 tanpa repair data historis.
- Ringkasan auditor/fixer: fixer membuat runner terisolasi dan probe SELECT-only;
  auditor mereview seluruh batch A2 dan menyatakan tidak ada blocker tersisa.
- File berubah: `tools/tests/a2_inventory_transaction_matrix_smoke.php`,
  `tools/tests/a2_database_invariant_probe.php`, serta dokumen roadmap/log.
- Perubahan utama: matrix menjalankan 12 smoke; probe memeriksa engine/index,
  periode, lot/HPP negatif, defisit, queue cache, dan anomali terminal POS.
- Validasi: seluruh 12 smoke PASS. Probe staging: InnoDB dan unique domain/bulan;
  current COMPONENT/MATERIAL OPEN; lot quantity/cost negatif 0; defisit tersisa
  0; queue gagal 0; anomali historis 37/26 sampai 2026-08-31.
- Review auditor: PASS; A2 selesai pada gate kode, smoke, dan read-only DB.
- Risiko sisa: data historis tidak direplay; APK/browser UAT dikecualikan dari
  completion A2. Tidak ada SQL atau perubahan schema pada batch ini.
- Batch berikutnya: Fase A3 registry navigasi dan UI operasional.

## Batch 100 — A3 registry navigasi kanonis

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-03/P1-05 — hilangkan perbedaan struktur antara registry
  database dan sidebar yang dilihat pengguna.
- Ringkasan auditor/fixer: auditor meminta migration fail-closed, idempotent,
  dan tidak menebak page/parent; fixer menormalkan parent, ikon, URL, sales
  alias, dan urutan sibling dengan pre/postcondition sebelum commit.
- File berubah: `sql/2026-09-04a_a3_navigation_registry_canonicalization.sql`,
  `application/controllers/Sidebar.php`,
  `tools/tests/a3_navigation_registry_smoke.php`.
- Perubahan utama: Master dan Product Monitoring dimaterialisasi di registry;
  group Inventory/Component yang aktif dipakai ulang; Product Availability
  dipindahkan ke parent kanonis; lima ikon dilengkapi; duplikasi laporan sales
  dinonaktifkan setelah favorite dipindahkan; Online Food menjadi pure group.
- Validasi: migration sempat dua kali berhenti dan rollback aman pada ambiguity
  kolom serta action-bearing group; setelah direvisi berhasil dijalankan dua
  kali di staging. Missing icon, duplicate code/URL, sort collision, favorite
  invalid, dan action-bearing group semuanya 0. Hash state fungsional sebelum
  dan sesudah rerun identik:
  `78118baa84693c0a6aec669b84a5099b5dd9fe0dcc53f0d5727d832792498636`.
- Hasil review auditor: PASS setelah qualifier SQL dan idempotensi seed
  diperbaiki.
- Risiko sisa: struktur menu bisnis tetap perlu dinilai pengguna melalui UAT;
  migration belum dijalankan di server utama.
- Batch berikutnya: hapus renderer sidebar hardcode.

## Batch 101 — A3 sidebar database-only

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-03 — view tidak boleh menyuntikkan menu atau regrouping yang
  tidak ada di database.
- Ringkasan auditor/fixer: auditor menetapkan `sys_menu` sebagai satu-satunya
  sumber struktur; fixer menyederhanakan view menjadi renderer tree generik.
- File berubah: `application/views/layout/sidebar.php`,
  `application/controllers/Sidebar.php`,
  `tools/tests/a3_sidebar_renderer_single_source_smoke.php`.
- Perubahan utama: map ikon, regroup/injection, synthetic negative ID,
  `is_virtual`, relabel PO, dan helper struktur runtime dihapus. Label, ikon,
  URL, parent, dan sort berasal dari registry; ikon fallback hanya untuk
  defensif tampilan.
- Validasi: PHP lint dan source smoke sidebar single-source lulus.
- Hasil review auditor: PASS; tidak ada sumber tree kedua yang tersisa pada
  renderer.
- Risiko sisa: salah konfigurasi registry sekarang terlihat langsung dan harus
  diselesaikan melalui halaman pengelolaan/migration, bukan disamarkan view.
- Batch berikutnya: satukan resolver favorite dan permission sidebar.

## Batch 102 — A3 favorite dan menu fail-closed

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-04 — favorite/pin tidak boleh menampilkan atau menyimpan menu
  yang sudah kehilangan hak akses.
- Ringkasan auditor/fixer: auditor meminta tree, favorite, pin, dan reorder
  memakai keputusan akses yang sama; fixer menyatukan resolver efektif dan
  mempertahankan multi-role sebagai union permission.
- File berubah: `application/models/Menu_model.php`,
  `application/controllers/Sidebar.php`,
  `tools/tests/a3_sidebar_favorite_registry_smoke.php`.
- Perubahan utama: action menu wajib mempunyai menu dan page aktif; group tanpa
  page hanya menjadi container dan tidak dapat dipin; favorite stale/revoked
  otomatis tersembunyi; pin idempotent memakai unique user/menu; alias URL
  hardcode di model dihapus.
- Validasi: PHP lint, 12 favorite/registry checks, serta smoke CSRF mutasi
  sidebar admin/favorite/structure lulus.
- Hasil review auditor: PASS.
- Risiko sisa: baseline isi permission tiap role tetap merupakan keputusan
  pemilik; A3 hanya menjamin enforcement registry konsisten.
- Batch berikutnya: page alias eksplisit dan direct-URL fail-closed.

## Batch 103 — A3 page alias registry

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-05 — sepuluh page code alias tidak boleh bergantung pada
  perilaku “page tidak ditemukan maka lanjut”.
- Ringkasan auditor/fixer: review pertama menemukan precedence direct grant
  dan collision alias; fixer mengubah resolver menjadi canonical-only serta
  menambah pre/postcondition collision fail-closed.
- File berubah: `sql/2026-09-04b_a3_page_alias_registry.sql`,
  `application/core/MY_Controller.php`, `application/controllers/My.php`,
  `tools/tests/a3_page_alias_registry_smoke.php`.
- Perubahan utama: `sys_page_alias` menyimpan sepuluh mapping eksplisit; `can()`
  me-resolve alias aktif satu kali per request; schema/query alias bermasalah
  ditolak; My Schedule selalu melalui permission guard; tidak ada permission
  role/user yang disalin.
- Validasi: migration dijalankan dua kali di staging; active alias 10 dan
  invalid alias 0. PHP lint dan alias smoke lulus.
- Hasil review auditor: PASS setelah canonical precedence dan collision guard
  diperbaiki.
- Risiko sisa: SQL harus diterapkan setelah migration registry pada server
  utama; perubahan page code baru wajib mendaftarkan alias secara eksplisit.
- Batch berikutnya: fondasi design system dan gate akhir A3.

## Batch 104 — A3 UI shell dan gerbang akhir

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-06/P1-07 — pola tampilan dasar lintas modul dan bukti gabungan
  penutupan A3.
- Ringkasan auditor/fixer: auditor membatasi A3 pada shell/token/primitives
  global agar tidak mengubah 336 view sekaligus; fixer menambahkan fondasi UI,
  menyesuaikan halaman pengelolaan sidebar, dan membuat gate route collision.
- File berubah: `application/views/layout/main.php`,
  `application/views/sidebar/manage.php`, `assets/css/theme-custom.css`,
  `tools/tests/a3_finance_ui_shell_smoke.php`,
  `tools/tests/a3_route_collision_smoke.php`, serta dokumen roadmap/progres/log.
- Perubahan utama: shell lintas rumpun, token, page header, action bar, card,
  filter/table/state, focus keyboard, target aksi 40px, responsive, reduced
  motion, dan flash message escaped. Halaman registry menampilkan tujuh kategori
  hasil validator. Enam key route terminal/outlet identik dibiarkan karena
  freeze APK, tetapi tidak ada target route yang bertentangan.
- Validasi: PHP lint seluruh file PHP A3; UI shell 50/50; route collision
  conflicting 0 dan identical duplicate 6; seluruh smoke A3 serta regression
  sidebar/RBAC lulus; `composer validate --no-check-publish` valid; scoped
  `git diff --check` lulus. Probe staging akhir: missing page/icon, duplicate
  URL, sort collision, active child under inactive parent, dan invalid alias 0.
- Hasil review auditor: review pertama menemukan token CSRF favorite terputus
  antara main layout dan footer. Fixer meneruskan token secara eksplisit serta
  menambah assertion `render → main → footer → app.js`; review ulang PASS pada
  code/database/smoke gate.
- Risiko sisa: UAT visual browser/viewport nyata belum dijalankan. Dua ekspektasi
  A1 POS Mobile terkait sesi lintas terminal masih gagal pada kode APK milik
  pengguna dan dikecualikan; A3 tidak mengubah `Pos_mobile.php`, `Pos_model.php`,
  atau `routes.php`.
- Batch berikutnya: Fase A4 automated quality gate.

## Batch 105 — Sinkronisasi backup APK dan POS web

- Waktu: 2026-09-04; penutupan dicatat setelah review A3.
- Prioritas: menerima perubahan APK tanpa menimpa hardening web, cache
  availability A2, atau binding keamanan POS Mobile.
- Ringkasan auditor/fixer: auditor menolak copy langsung ketiga backup. Fixer
  melakukan merge terarah pada sanitizer cetak dan UI kasir; model aktif tetap
  dipertahankan karena backup model belum memiliki refresh availability setelah
  void/refund. Review pertama menemukan trailing `FEED` terpangkas dan test belum
  mengunci backup model; keduanya diperbaiki lalu auditor menyatakan PASS.
- File berubah: `application/controllers/Pos_mobile.php`,
  `application/views/pos/cashier_index.php`, dan
  `tools/tests/pos_apk_web_backup_merge_smoke.php`.
- Perubahan utama: output printer mobile menormalisasi newline, menghapus marker
  media termasuk `LOGO_BASE64`, mempertahankan UTF-8, dan menerjemahkan
  `[[FEED:n]]` menjadi 1–5 line feed. Kasir web memperoleh quick navigation serta
  modal order masuk yang hanya merender channel berizin, hanya membaca outlet
  sesi kasir aktif, memakai tanggal server, dan refresh on-demand agar tidak
  menggandakan notifier global.
- Validasi: PHP lint lulus; focused merge 28/28; print-document binding 34/34;
  order reader 13/13; order-action reader 54/54; financial writer 26/26;
  reversal availability 20/20; reversal invariant 8/8; order monitor 38/38;
  transaksi POS 1.691 checks; scoped diff-check bersih. Ketiga file `*_bak.php`
  tetap byte-for-byte sama.
- Hasil review auditor: PASS untuk merge backup APK/web.
- Risiko sisa: smoke A1 lama masih melaporkan dua mismatch cashier-session,
  satu draft-upsert, dan satu authorization terkait urutan/kontrak session.
  Area tersebut identik pada backup dan file aktif serta tidak diubah dalam
  Batch 105; perlu batch A1 terpisah dan UAT APK nyata.
- Batch berikutnya: tentukan apakah menutup residual A1 terlebih dahulu atau
  melanjutkan Fase A4 sesuai keputusan pemilik.

## Batch 106 — A4.1 runner quality gate deterministik

- Waktu: 2026-09-04 18:05 WIB.
- Prioritas: menyediakan satu pintu validasi otomatis yang dapat dipakai sambil
  APK masih dikembangkan tanpa mengaburkan syarat release.
- Ringkasan auditor/fixer: auditor menetapkan proses terisolasi, timeout,
  laporan ringkas, profil eksplisit, dan kontrak credential produksi tetap
  strict; fixer membangun runner serta contract smoke deterministik.
- File berubah: `tools/tests/finance_quality_gate.php` dan
  `tools/tests/finance_quality_gate_contract_smoke.php`.
- Perubahan utama: profil `parallel` menjalankan required, development, serta
  release check tetapi hanya required yang wajib; `release` mewajibkan ketiga
  tier; `staging` menambah probe database SELECT-only. Browser dan APK nyata
  selalu dilaporkan sebagai manual pending.
- Validasi: PHP lint; contract smoke 14/14; dua run parallel menghasilkan output
  identik dan lulus dengan required 19/19 serta development 4/4.
- Hasil review auditor: PASS untuk A4.1. Kegagalan release check tidak
  disembunyikan dan tidak dilonggarkan.
- Risiko sisa: profil release/staging masih exit 1 karena konfigurasi database
  staging memakai credential langsung sesuai pilihan operasional sebelumnya.
- Batch berikutnya: sinkronkan contract test POS Mobile terminal cadangan.

## Batch 107 — A4.2 contract test POS Mobile paralel

- Waktu: 2026-09-04 18:06 WIB.
- Prioritas: menghapus false failure test lama tanpa mengubah source APK/web
  yang sedang dikembangkan pemilik.
- Ringkasan auditor/fixer: auditor menyetujui sesi kasir OPEN pada terminal
  cadangan hanya bila pegawai dan outlet sama; fixer memperbarui harness dan
  assertion authorization, session, draft/upsert, printer, dan reader.
- File berubah: `tools/tests/a1_direct_url_guard_matrix_smoke.php`,
  `tools/tests/pos_mobile_authorization_smoke.php`,
  `tools/tests/pos_mobile_cashier_session_binding_smoke.php`,
  `tools/tests/pos_mobile_draft_upsert_binding_smoke.php`,
  `tools/tests/pos_mobile_printer_binding_smoke.php`, dan
  `tools/tests/pos_mobile_reader_outlet_binding_smoke.php`.
- Perubahan utama: owner terminal sesi tetap menjadi terminal transaksi;
  terminal perangkat dicatat sebagai origin, backup mode eksplisit, dan
  employee/outlet berbeda, sesi tutup, serta spoof request tetap ditolak.
- Validasi: PHP lint; authorization PASS; cashier-session 34/34; draft/upsert
  57/57; printer 32/32; reader 27/27; A1 required dan development PASS; quality
  gate parallel PASS dua kali; staging database probe PASS.
- Hasil review auditor: PASS. Hash `Pos_mobile.php`, `Pos_model.php`,
  `routes.php`, dan `cashier_index.php` sama dengan sebelum batch.
- Risiko sisa: browser role UAT serta APK/device/printer nyata tetap manual
  pending. Profil release tetap diblokir oleh direct database credential.
- Batch berikutnya: A4.3 contract test lintas modul, browser, migration, dan
  restore.

## Batch 108 — A4.3 matriks contract lintas modul

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: membentuk bukti otomatis lintas permission, finance, purchase/SR,
  inventory, production, people, asset, Printer Agent, dan WhatsApp.
- Ringkasan auditor/fixer: auditor menetapkan test DB-free yang deterministik;
  fixer membangun tujuh smoke A4 dan runner gabungan dengan proses terisolasi.
- File berubah: delapan `tools/tests/a4_*_smoke.php`, perbaikan fixture auth/WA,
  dan `tools/tests/printer_agent_trust_smoke.py`.
- Perubahan utama: 35 test lintas modul dijalankan dengan timeout dan output
  terbatas; migration/restore diuji pada fixture temporer tanpa database live.
- Validasi: matriks A4 lulus 35/35.
- Hasil review auditor: kontrak dasar diterima; test awal mengungkap gap nyata
  pada CSRF, transaksi finance, receipt PO, dan atomicity posting component.
- Risiko sisa: source contract bukan pengganti concurrency test database live.
- Batch berikutnya: perbaiki gap kode yang ditemukan matriks.

## Batch 109 — A4.3 CSRF Purchase dan Store Request

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: endpoint mutasi tetap aman saat global CSRF CodeIgniter `FALSE`.
- Ringkasan auditor/fixer: auditor menemukan endpoint create/update/status,
  receipt, mutasi finance, action, dan fulfill; fixer memasang token session dan
  header scoped sebelum payload/model.
- File berubah: `application/controllers/Purchase.php`,
  `application/controllers/Procurement.php`, lima view purchase, dua view
  procurement, dan `tools/tests/a4_purchase_sr_contract_smoke.php`.
- Perubahan utama: sembilan endpoint Purchase/Procurement wajib POST dan token
  session 64-hex yang cocok melalui `hash_equals`.
- Validasi: PHP lint lulus; Purchase/SR 55/55 dan CSRF-only 30/30 lulus.
- Hasil review auditor: PASS setelah endpoint edit PO dan edit SR ikut dijaga.
- Risiko sisa: browser E2E dengan session nyata belum dijalankan.
- Batch berikutnya: atomicity finance, receipt, dan production.

## Batch 110 — A4.3 atomicity finance, receipt, dan component

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: mencegah balance/lot/ledger committed ketika header atau audit
  gagal berubah.
- Ringkasan auditor/fixer: auditor mengharuskan lock dan status berada dalam
  transaksi yang sama; fixer memperbaiki writer dan model, lalu review kedua
  menutup race terhadap proses tutup periode.
- File berubah: `application/models/Purchase_model.php`,
  `application/models/Finance_report_model.php`,
  `application/libraries/ComponentStockWriter.php`,
  `application/controllers/Production.php`, serta smoke finance/purchase/
  inventory-production.
- Perubahan utama: mutasi rekening menolak future/CLOSED dan mengunci periode
  serta rekening; close period mengunci row sebelum snapshot; receipt mengunci
  PO/line, menolak status terminal, dan menghitung `PARTIAL_RECEIVED`/
  `RECEIVED`; status adjustment/batch component disimpan di transaksi writer.
- Validasi: PHP lint lulus; finance 20/20, Purchase/SR 55/55,
  inventory-production 27/27, dan matriks A2 12/12 lulus.
- Hasil review auditor: PASS untuk gap kode setelah koreksi kedua.
- Risiko sisa: uji dua transaksi database paralel masih perlu runner integrasi.
- Batch berikutnya: pisahkan gate source dan runtime lalu jalankan gate gabungan.

## Batch 111 — A4.3 gate source/runtime dan status akhir

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: gate harian tetap cepat tanpa menyamarkan syarat runtime release.
- Ringkasan auditor/fixer: browser/Printer Agent dipisahkan menjadi source
  contract dan runtime capability; backup APK yang untracked tidak lagi dikunci
  checksum lama tetapi tetap wajib terisolasi dari runtime.
- File berubah: `tools/tests/a4_browser_shell_runtime_smoke.php`,
  `tools/tests/printer_agent_trust_smoke.py`, matriks A4,
  `tools/tests/finance_quality_gate.php`, contract gate, smoke backup APK, serta
  tiga dokumen roadmap/progres/log.
- Perubahan utama: profile `parallel` menjalankan source contract; profile
  `release`/`staging` menambahkan browser render dan Printer Agent HTTP sebagai
  blocker. Tidak ada SQL/schema/data yang diubah.
- Validasi: PHP lint lulus; Composer valid dengan deprecation tool lama;
  matriks A4 35/35; gate `parallel` required 20/20 dan development 4/4; probe
  database A2 SELECT-only selesai.
- Hasil review auditor: gate semantik PASS, tetapi A4.3 tetap `[~]` sampai dua
  runtime benar-benar lulus.
- Risiko sisa: Firefox Snap gagal headless render pada server ini; Python Flask
  belum tersedia; release secret gate tetap blocked karena credential staging
  langsung.
- Batch berikutnya: sediakan runner browser/Python kompatibel, tutup A4.3, lalu
  masuk A4.4.

## Batch 112 — Penutupan runtime A4.3

- Waktu: 2026-09-04 19:35 WIB.
- Prioritas: membuktikan runtime browser dan Printer Agent yang sebelumnya
  tertahan tanpa mengubah source aplikasi atau database.
- Ringkasan auditor/fixer: runtime Chrome headless dipakai untuk fixture shell
  loopback desktop/mobile; Printer Agent dijalankan dari virtual environment
  terisolasi. Auditor menyatakan kedua bukti runtime cukup untuk menutup A4.3.
- File berubah: `tools/tests/a4_browser_shell_runtime_smoke.php`,
  `tools/tests/bootstrap_a4_runtime.sh`, `tools/tests/finance_quality_gate.php`,
  dan `tools/tests/finance_quality_gate_contract_smoke.php`.
- Perubahan utama: runner browser memilih Chrome native, mengambil dua
  screenshot nyata, dan membersihkan fixture temporer; bootstrap runtime
  idempotent menyiapkan Python venv di luar document root; gate kini memakai
  interpreter Python yang benar dan gagal tertutup bila runtime tidak ada.
- Perubahan runtime server: Google Chrome stable dan paket `python3-venv`
  dipasang; virtual environment dibuat di
  `/var/lib/finance-a4-runtime/printer-venv`. Direktori temporer hasil probe
  batch ini sudah dihapus.
- Validasi: browser runtime 28 check lulus; Printer Agent source dan HTTP Flask
  runtime lulus; bootstrap dijalankan dua kali; gate `release` dan `staging`
  masing-masing required 20/20, development 4/4, runtime 2/2, serta probe
  database staging 1/1 lulus.
- Hasil review auditor: A4.3 PASS `[x]`. Kegagalan kontrak secret produksi
  bukan blocker A4.3 dan tetap dipertahankan sebagai blocker release.
- Risiko sisa: UAT role melalui browser serta APK/device/printer fisik tetap
  validasi manual; dependency Python belum dikunci secara reproducible.
- Batch berikutnya: A4.4 lint, dependency, secret, dan package preflight.

## Batch 113 — A4.4-1 release preflight fail-closed

- Waktu: 2026-09-04 19:35 WIB.
- Prioritas: membangun pemeriksaan release read-only yang tidak dapat dilewati
  dengan memperkecil scope policy atau memperluas allowlist rahasia.
- Ringkasan auditor/fixer: auditor dua kali menemukan scope policy dan
  pengecualian fixture yang terlalu longgar. Fixer menyiapkan policy schema v2
  dan fingerprint fixture; integrasi diselesaikan main agent setelah runner
  fixer dihentikan agar batch tidak melebar. Review akhir auditor menyatakan
  PASS tanpa regresi keamanan baru.
- File berubah: `tools/release/package_policy.json`,
  `tools/tests/a4_release_preflight_smoke.php`,
  `tools/tests/a4_release_preflight_contract_smoke.php`,
  `tools/tests/a4_cross_module_contract_matrix_smoke.php`,
  `tools/tests/finance_quality_gate.php`,
  `tools/tests/finance_quality_gate_contract_smoke.php`, serta dokumen status
  `_30`, `_28`, progres, dan log ini.
- Perubahan utama: lint PHP dan syntax JS/Python, validasi dependency lock,
  secret scan, serta virtual package allow/deny policy masuk tier preflight.
  Policy mewajibkan seluruh root/file utama, menolak wildcard exception, dan
  hanya menerima fixture exact path/line/category/SHA-256. File symlink,
  unreadable, atau oversize gagal tertutup tanpa mengikuti target symlink;
  output temuan hanya kategori/path/baris.
- Validasi: PHP lint lulus; preflight contract 14/14; quality-gate contract
  23/23; matriks A4 35/35; dua output preflight byte-identik; quality gate
  `parallel` required 21/21 dan development 4/4, hasil keseluruhan PASS.
- Hasil review auditor: A4.4-1 hardening PASS. Tujuh temuan release yang tersisa
  teridentifikasi benar dan bukan regresi batch.
- Risiko sisa: `composer.lock` belum ada, empat requirement Python belum
  hash-pinned, dan dua baris credential database staging langsung tetap ditolak
  kontrak produksi. Virtual candidate set belum menjadi enforcement artefak
  paket aktual; semantic static analysis juga belum ditambahkan.
- Dampak aplikasi/data: tidak ada source aplikasi, SQL, schema, atau data yang
  diubah. File POS/mobile tidak disentuh.
- Batch berikutnya: A4.4-2 dependency reproducibility (`composer.lock`, Python
  hash lock, dan bootstrap `pip --require-hashes`), kemudian static/dependency
  vulnerability scan serta package artifact enforcement.

## Batch 114 — A4.4-2 dependency reproducibility

- Waktu: 2026-09-04 20:05 WIB.
- Prioritas: memastikan instalasi dependency PHP dan Printer Agent dapat diulang
  dengan versi serta checksum yang sama.
- Ringkasan auditor/fixer: auditor mengarahkan lock tanpa perubahan constraint
  aplikasi dan mempertahankan credential staging sebagai blocker terpisah.
  Fixer menghasilkan Composer lock serta Python direct-input/hash lock; main
  agent menyelesaikan pengikatan bootstrap dan preflight. Auditor akhir
  menyatakan batch PASS.
- File berubah: `.gitignore`, `composer.lock`,
  `tools/pos_printer_agent/requirements.in`,
  `tools/pos_printer_agent/requirements.txt`,
  `tools/tests/bootstrap_a4_runtime.sh`, `tools/release/package_policy.json`,
  `tools/tests/a4_release_preflight_smoke.php`, contract preflight, serta
  dokumen status `_30`, `_28`, progres, dan log ini. `composer.json` tidak
  diubah.
- Perubahan utama: `composer.lock` tidak lagi diabaikan Git; direct intent
  Python dipisahkan ke `requirements.in`; seluruh dependency transitif di
  `requirements.txt` exact-pinned dan memiliki SHA-256; bootstrap mewajibkan
  `pip --require-hashes`; preflight memahami lock multiline dan memvalidasi
  struktur Composer lock.
- Validasi: PHP lint dan Bash syntax lulus; Composer validate lulus; Composer
  install dry-run tanpa scripts/plugins lulus dengan rencana 29 paket dan tidak
  membuat `vendor/`; preflight contract 16/16; bootstrap existing venv lulus;
  instalasi fresh temporary venv dengan `--require-hashes` serta import Flask,
  serial, Pillow, dan qrcode lulus; quality gate `parallel` required 21/21 dan
  development 4/4 lulus.
- Hasil review auditor: PASS. Composer/Python lock, parser multiline, dan
  bootstrap hash enforcement dinilai konsisten.
- Risiko sisa: bootstrap terhadap venv lama tidak menghapus paket yatim;
  deployment bersih sebaiknya rebuild/swap venv. Versi Python dan tool pembuat
  lock harus dicatat saat regenerasi. Composer CLI server versi 2.0.14 masih
  mencetak peringatan deprecation pada PHP 8.1 walaupun validate/dry-run lulus.
  Dua credential database staging langsung tetap memblokir release/staging.
- Dampak aplikasi/data: patch batch ini tidak mengubah source aplikasi, SQL,
  schema, atau data. Saat batch berjalan terdeteksi perubahan concurrent pada
  `Pos_mobile.php` dan `Pos_model.php`; keduanya dianggap pekerjaan APK pemilik,
  dipertahankan, dan tidak di-revert. `routes.php` serta `cashier_index.php`
  tetap sama. Venv temporer validasi sudah dihapus.
- Batch berikutnya: A4.4-3 secret externalization bila kebijakan deployment
  diubah; bila direct config staging dipertahankan, lanjut static/dependency
  vulnerability scan dan enforcement artefak paket dengan release tetap BLOCKED.

## Batch 115 — A4.4-3a vulnerability gate offline

- Waktu: 2026-09-04 20:25 WIB.
- Prioritas: menutup advisory dependency nyata dan membuat scan kerentanan
  Composer/npm/Python yang repeatable tanpa mengirim dependency project saat
  quality gate berjalan.
- Ringkasan auditor/fixer: auditor memilih OSV-Scanner pinned dan database
  offline serta menerima update lock-only `mysql2`. Fixer memperbarui npm lock
  dan menyiapkan toolchain/bootstrap; main agent melengkapi smoke, contract, dan
  wiring gate. Dua review auditor menutup false-clean extraction serta race
  refresh/scan sebelum verdict akhir PASS.
- File berubah: `wa-engine/package-lock.json`,
  `tools/security/toolchain.lock.json`,
  `tools/tests/bootstrap_a4_security_runtime.sh`,
  `tools/tests/a4_dependency_vulnerability_smoke.php`, contract vulnerability,
  matriks A4, quality gate beserta contract, dan empat dokumen status. File
  `wa-engine/package.json` tetap byte-identik.
- Perubahan utama: `mysql2` naik 3.22.6 → 3.24.3 tanpa `audit fix --force`;
  OSV-Scanner Linux amd64 2.5.1 dikunci SHA-256; snapshot Packagist/npm/PyPI
  maksimal 48 jam ditempatkan di `/var/lib/finance-a4-security`. Scanner selalu
  `--offline --all-packages`, mewajibkan exact tiga source dan minimal satu
  package per source, serta memegang shared lock selama checksum+scan. Bootstrap
  memakai exclusive lock saat aktivasi database+snapshot.
- Validasi: PHP lint dan Bash syntax lulus; vulnerability source PASS; contract
  15/15; quality-gate contract 25/25; temporary `npm ci --ignore-scripts`
  memuat `mysql2` 3.24.3; npm audit produksi 0; seluruh matriks A4/WhatsApp
  35/35; OSV offline memindai 3 source/144 paket dengan 0 advisory; gate
  `parallel` required 23/23 dan development 4/4 lulus.
- Validasi release: runtime browser/Printer Agent 2/2 dan security 1/1 lulus.
  Profile release tetap exit 1 secara benar karena release-secret dan preflight
  mendeteksi dua credential database staging langsung.
- Hasil review auditor: PASS setelah bukti ekstraksi package diwajibkan dan race
  snapshot ditutup dengan shared/exclusive lock.
- Risiko sisa: snapshot advisory perlu direfresh maksimal tiap 48 jam. Versi
  Node/npm pembentuk lock perlu dipin agar metadata lock tidak churn pada update
  berikutnya. Runtime security memakai sekitar 565 MB di luar document root.
- Dampak aplikasi/data: tidak ada controller/model/view/POS, SQL, schema, atau
  data yang diubah. Direktori temporer dependency yang dibuat fixer telah
  dibersihkan.
- Batch berikutnya: A4.4-3b semantic static gate, lalu A4.4-3c deterministic
  package artifact enforcement. Secret staging tetap menjadi blocker terpisah.

## Batch 116 — A4.4-3b semantic static gate

- Waktu: 2026-09-04 20:45 WIB.
- Prioritas: menahan regresi semantik PHP baru pada seluruh source aplikasi
  tanpa menjalankan bootstrap CodeIgniter atau membuka database.
- Ringkasan auditor/fixer: auditor menetapkan full scope `application/`, runtime
  terpin di luar document root, baseline yang tidak boleh bertambah diam-diam,
  dan blocking pada profile release/staging. Fixer memasang PHPStan serta gate;
  auditor menyatakan PASS dan menerima level 0 sebagai baseline awal CI3.
- File berubah: `composer.json`, `composer.lock`, `tools/static/phpstan.neon`,
  `tools/static/ci3-stubs.php`, `tools/static/phpstan-baseline.neon`,
  `tools/static/toolchain.lock.json`, bootstrap/static smoke beserta contract,
  dan quality gate beserta contract.
- Perubahan utama: PHPStan 1.12.27 dikunci pada Composer lock dan dipasang di
  `/var/lib/finance-a4-static`; seluruh `application/`, termasuk POS/mobile,
  dipindai. Baseline 5.008 temuan lama dikunci tepat, unmatched ignore dilaporkan,
  timeout/output dibatasi, dan source/contract selalu menjadi required gate.
- Validasi: PHP lint dan Bash syntax lulus; Composer validate valid; static
  contract 14/14; quality-gate contract 27/27; scan PHPStan aktual PASS dengan
  `scope=application baseline_errors=5008`.
- Hasil review auditor: PASS; gate efektif memblokir error baru dan tidak
  menyentuh source aplikasi, database, SQL, atau empat file POS/APK yang dijaga.
- Risiko sisa: level analisis masih 0 dan baseline lama besar; peningkatan level
  serta pengurangan baseline dilakukan bertahap per modul setelah A4.
- Batch berikutnya: A4.4-3c shared package policy dan artifact builder.

## Batch 117 — A4.4-3c artifact builder dan penutupan A4

- Waktu: 2026-09-04 21:07 WIB.
- Prioritas: memastikan hanya source yang diizinkan dapat menjadi artefak rilis
  dan kegagalan gate tidak pernah meninggalkan paket final.
- Ringkasan auditor/fixer: auditor mewajibkan enumerator tunggal, urutan
  preflight→static→vulnerability, paket reproducible, checksum, dan publish
  atomik. Fixer mengekstrak policy bersama serta membuat builder/contract. Dua
  masalah runner yang ditemukan pada validasi gabungan diperbaiki: static smoke
  tidak lagi terhitung dua kali dan assertion overflow tidak lagi bergantung
  pada race exit code child process.
- File berubah: `tools/release/ReleasePackagePolicy.php`,
  `tools/release/build_release_artifact.php`, preflight smoke beserta contract,
  `tools/tests/a4_release_artifact_contract_smoke.php`, matriks lintas modul,
  quality gate, static contract, serta dokumen roadmap/progres/log.
- Perubahan utama: preflight dan builder memakai candidate set yang sama dari
  Git+policy; traversal, symlink, unreadable, backup, upload, log, vendor, secret,
  dan mutasi source ditolak. Tar memakai urutan, epoch, owner/group, serta mode
  ternormalisasi dan membawa `RELEASE-MANIFEST.json` berisi SHA-256 setiap file.
  File final hanya dipublish melalui rename setelah semua pemeriksaan lulus.
- Validasi: PHP lint lulus; preflight contract 18/18; artifact contract 9/9;
  quality-gate contract 27/27; cross-module 35/35; overflow contract 10/10 run;
  profile `parallel` required 26/26 dan development 4/4, hasil PASS. Dua fixture
  build byte-identik dan seluruh checksum archive cocok.
- Validasi workspace nyata: preflight menemukan tepat dua assignment credential
  database staging pada path/baris tanpa mencetak nilai. Builder exit 1 sebelum
  static/security/package dan `FINAL_ARTIFACT_PRESENT=no`.
- Hasil review auditor: PASS. Fase A4 dinyatakan `[x]` implementation-complete;
  tidak ada blocker A4 tersisa.
- Risiko sisa: release/staging tetap `BLOCKED` oleh keputusan direct credential
  A0. UAT role browser dan APK/device/printer fisik masih manual. PHPStan level
  dan baseline perlu ditingkatkan bertahap, bukan dengan memperlebar ignore.
- Batch berikutnya: A5 schema version registry dan migration runner
  deterministik; lalu clean install/upgrade/backup-restore/rollback/signature.

## Batch 118 — A5.1 katalog migration dan registry DB-free

- Waktu: 2026-09-04 21:22 WIB.
- Prioritas: membangun sumber tunggal urutan/checksum migration sebelum ada SQL
  yang boleh dieksekusi oleh release tooling.
- Ringkasan auditor/fixer: auditor memilih fondasi DB-free agar tujuh SQL aktif
  lama tidak langsung diasumsikan aman dan SQL historis/repair tidak terbawa.
  Fixer membuat katalog, DDL registry, runner validate/plan, serta contract;
  auditor akhir menyatakan PASS.
- File berubah: `tools/db/migration_catalog.json`,
  `tools/db/migration_runner.php`,
  `sql/2026-09-04c_a5_schema_migration_registry_foundation.sql`,
  `tools/tests/a5_migration_catalog_runner_contract_smoke.php`, dan wiring
  required pada quality gate beserta contract.
- Perubahan utama: katalog v1 mengelola satu migration registry dan mengakui
  tujuh SQL top-level sebagai legacy belum dikelola. Runner baru hanya memiliki
  mode DB-free `validate`/`plan`; checksum, ID/order/path/dependency/policy,
  symlink, cycle, SQL tak dikenal, dan support-repair semuanya fail-closed.
- Validasi: PHP lint lulus; A5.1 contract 27/27; quality-gate contract 27/27;
  A4 migration/backup/restore 19/19; katalog validate dan dua plan deterministik
  lulus; Composer validate valid.
- Hasil review auditor: PASS. SQL registry dinilai DDL-only, repeat-safe, dan
  tidak berisi seed/data customer; database staging tidak disentuh.
- Risiko sisa: tujuh SQL legacy belum memiliki checksum/dependency terkelola;
  runner belum memiliki executor, lock, ledger verification, atau mode apply.
- Batch berikutnya: A5.2 executor/state protocol dengan fake-client fixture;
  belum melakukan apply ke staging.

## Batch 119 — A5.2 executor dan state protocol fixture-only

- Waktu: 2026-09-04 21:37 WIB.
- Prioritas: memastikan apply migration memiliki lock, verifikasi ledger,
  timeout, replay aman, dan redaksi sebelum diizinkan menyentuh staging.
- Ringkasan auditor/fixer: fixer membuat executor satu sesi client dan fake
  client contract. Auditor awal menolak karena timeout belum membunuh child,
  pemeriksaan environment terlalu lemah/lebar, dan probe registry hanya memakai
  jumlah kolom. Fixer menutup ketiganya; review ulang menyatakan PASS.
- File berubah: `tools/db/migration_runner.php`,
  `tools/tests/a5_migration_executor_contract_smoke.php`, dan wiring quality
  gate beserta contract.
- Perubahan utama: mode `apply`/`--dry-run` memakai option file absolut di luar
  repo dengan permission aman; menolak credential CLI/environment; menjalankan
  advisory lock, exact registry contract, state/checksum verification, SQL,
  ledger insert setelah sukses, replay no-op, release lock, serta global timeout
  yang menghentikan client macet.
- Validasi: PHP lint lulus; A5.1 27/27; A5.2 21/21; quality-gate contract 27/27.
  Fixture membuktikan drift, contention, wrong schema/index, malformed output,
  SQL failure, timeout, permission, dan credential semuanya fail-closed.
- Hasil review auditor: PASS untuk controlled staging readiness; database nyata
  belum disentuh pada batch ini.
- Risiko sisa: controlled apply memerlukan backup dan client option file 0600;
  baru registry foundation yang terkelola, belum tujuh SQL legacy.
- Batch berikutnya: A5.3 backup staging, before evidence, bootstrap registry,
  read-only verification, dan replay `applied=0/skipped=1`.

## Batch 120 — A5.3 controlled staging bootstrap registry

- Waktu: 2026-09-04 21:45 WIB.
- Prioritas: membuktikan executor dan ledger pada MariaDB staging dengan backup,
  before/after evidence, serta replay tanpa mengadopsi SQL legacy.
- Ringkasan auditor/fixer: auditor mengizinkan bootstrap satu migration setelah
  A5.2 lulus. Percobaan awal fail-closed mengungkap representasi default `NULL`
  MariaDB dan konflik collation pada state lookup. Fixer membuat kompatibilitas
  yang sempit; auditor mereview kedua patch sebelum replay akhir.
- File berubah: `tools/db/migration_runner.php` dan
  `tools/tests/a5_migration_executor_contract_smoke.php`. Database staging
  mendapat tabel `sys_schema_migration` serta satu ledger row registry.
- Perubahan utama: exact registry contract menerima dua representasi metadata
  nullable MariaDB/MySQL hanya pada empat kolom nullable; state lookup memakai
  pembandingan byte ID kanonis agar bebas collation koneksi. Query registry
  diekstrak untuk test library-only tanpa menjalankan CLI main.
- Validasi: A5.1 27/27; A5.2 26/26; query registry aktual
  `COMPATIBLE_V1`; backup gzip sekitar 19 MB di luar repo memiliki sidecar
  SHA-256 valid dan permission 0600. Final apply menghasilkan
  `applied=1/skipped=0`; verifikasi row ID/path/checksum/catalog/tool tepat satu;
  replay menghasilkan `applied=0/skipped=1`.
- Hasil review auditor: patch nullable dan byte comparison PASS; dua kegagalan
  awal tidak menulis ledger palsu dan advisory lock telah bebas.
- Risiko sisa: backup staging berbentuk gzip+checksum dan belum terenkripsi;
  tujuh SQL legacy belum masuk ledger. Rollback registry tidak otomatis dan
  hanya boleh manual bila tidak ada migration setelahnya.
- Batch berikutnya: audit/enrollment tujuh SQL legacy dengan pemisahan tegas
  antara bukti already-applied staging dan urutan clean install.

## Batch 121 — A5.4 disposition tujuh SQL legacy

- Waktu: 2026-09-04 21:55 WIB.
- Prioritas: mencegah replay/adopsi palsu SQL lama sebelum tersedia bukti
  struktur dan riwayat deployment yang cukup.
- Ringkasan auditor/fixer: auditor menetapkan semua SQL lama harus tetap
  non-executable. Fixer membuat inventaris hash, fitur SQL, objek, dependency
  runtime, risiko, probe read-only, dan disposition; auditor menyatakan PASS.
- File berubah: `tools/db/legacy_sql_inventory.json`,
  `tools/tests/a5_legacy_sql_inventory_contract_smoke.php`, dan wiring quality
  gate beserta contract.
- Perubahan utama: tiga file ditandai `historical-unverified` dan empat file
  `candidate-canonical`; tidak satu pun masuk plan install/upgrade atau ledger.
  Probe inventaris dibatasi satu SELECT tanpa comment, multi-statement, DDL/DML,
  transaction, lock, prepared statement, atau CALL.
- Validasi: PHP lint lulus; A5.4 17/17; A5.1 27/27; A5.2 26/26;
  quality-gate contract 27/27. Plan upgrade tetap hanya registry foundation.
- Hasil review auditor: PASS; hash dan evidence tujuh file cocok serta semua
  disposition tetap non-deployable. Database/SQL tidak diubah pada batch ini.
- Risiko sisa: probe saat ini baru inventaris, belum fingerprint struktur exact;
  keberadaan tabel/kolom tidak membuktikan migration pernah diterapkan.
- Batch berikutnya: A5.5 structural fingerprint dan eligibility read-only;
  tanpa enrollment, replay, atau ledger adoption.

## Batch 122 — A5.5 structural fingerprint dan probe staging read-only

- Waktu: 2026-09-04 22:14 WIB.
- Prioritas: membuktikan kesesuaian struktur empat kandidat SQL lama tanpa
  replay, enrollment, ledger adoption, atau perubahan data aplikasi.
- Ringkasan auditor/fixer: fixer membuat manifest fingerprint, probe, dan
  contract. Auditor menemukan lalu meminta penutupan bentuk SELECT berefek
  samping/lock, integritas alias global, `FOR SHARE`, dan terminator protocol
  MariaDB. Fixer menutup seluruh temuan; auditor akhir menyatakan PASS.
- File berubah: `tools/db/legacy_schema_fingerprints.json`,
  `tools/db/schema_fingerprint_probe.php`,
  `tools/tests/a5_schema_fingerprint_contract_smoke.php`, serta wiring required
  quality gate/manifest.
- Perubahan utama: tiga SQL historical selalu `not_eligible`; empat kandidat
  diperiksa dengan SELECT metadata/semantik exact. Validator menolak stacked
  SQL, file output/read, variable assignment, lock, wait/sleep/benchmark, DDL,
  dan DML. Probe tidak memiliki jalur apply/adopt.
- Validasi: PHP lint dan manifest validate lulus; A5.5 contract 40/40;
  quality-gate contract 27/27; `git diff --check` lulus. Probe staging memakai
  akun sementara SELECT-only melalui option file 0600, environment bersih, dan
  menghasilkan tiga kandidat `eligible_evidence`. Kandidat auth throttle
  `not_eligible` hanya pada `auth_login_failure_fk`. Ledger sebelum/sesudah
  identik satu row; SQL legacy tidak dijalankan dan akun/file sementara bersih.
- Hasil review auditor: PASS untuk evidence probe. Kegagalan FK dinilai
  structural drift, bukan dasar replay atau adopsi migration lama.
- Risiko sisa: status migration auth throttle belum kanonis. Penyebab detail,
  kompatibilitas tipe/index, dan jumlah orphan harus dibuktikan sebelum DDL.
- Batch berikutnya: A5.6 forensic preflight FK auth read-only; forward migration
  baru hanya boleh dibuat bila state `missing_fk_clean`.

## Batch 123 — A5.6 forensic preflight FK auth

- Waktu: 2026-09-04 22:21 WIB.
- Prioritas: membedakan FK hilang yang aman ditambah dari orphan atau
  ketidakcocokan struktur sebelum membuat migration baru.
- Ringkasan auditor/fixer: auditor menetapkan empat state redacted dan seluruh
  pemeriksaan harus SELECT-only. Fixer membuat probe/contract. Auditor meminta
  pembatasan referenced schema agar FK lintas database tidak salah dianggap
  valid; fixer memperbaiki dan review akhir menyatakan PASS.
- File berubah: `tools/db/auth_login_failure_fk_probe.php`,
  `tools/tests/a5_auth_login_failure_fk_probe_contract_smoke.php`, serta wiring
  required pada quality gate beserta contract.
- Perubahan utama: probe memeriksa engine dua tabel, kompatibilitas tipe/sign/
  nullability, index sumber/target, FK bernama atau bersaing, dan jumlah orphan
  agregat. Output dibatasi `missing_fk_clean`, `missing_fk_orphan`,
  `structural_incompatible`, atau `already_present`; tidak ada row user.
- Validasi: PHP lint/validate lulus; A5.6 contract 38/38; quality-gate contract
  27/27; `git diff --check` lulus. Controlled staging probe memakai akun
  sementara SELECT-only dan menghasilkan `structural_incompatible`; ledger
  migration sebelum/sesudah identik dan akun/file sementara dibersihkan.
- Hasil review auditor: PASS untuk probe. State staging belum mengizinkan DDL
  atau forward migration karena bukan `missing_fk_clean`.
- Risiko sisa: alasan struktur exact belum diklasifikasikan; replay migration
  auth lama tetap dilarang dan data login failure tidak disentuh.
- Batch berikutnya: forensic reason-code schema auth read-only atau lanjut
  fondasi runtime/install sesuai pilihan auditor A5.

## Batch 124 — A5.7 reason-code detail FK auth

- Waktu: 2026-09-04 22:27 WIB.
- Prioritas: mengisolasi penyebab `structural_incompatible` tanpa membaca row
  login atau langsung mengubah constraint.
- Ringkasan auditor/fixer: auditor memilih metadata-only reason-code probe.
  Fixer membuat satu SELECT `information_schema`, precedence kode tetap, dan
  contract seluruh cabang; auditor menyatakan PASS tanpa revisi.
- File berubah: `tools/db/auth_login_failure_fk_detail_probe.php`,
  `tools/tests/a5_auth_login_failure_fk_detail_probe_contract_smoke.php`, serta
  wiring required quality gate beserta contract.
- Perubahan utama: probe membedakan metadata/engine/type/index/FK bernama/FK
  kompetitor melalui kode redacted. Tidak ada query ke row `auth_login_failure`,
  ledger, adoption, DDL, DML, atau eksekusi SQL legacy.
- Validasi: PHP lint/validate lulus; A5.7 contract 49/49; A5.5 40/40; A5.6
  38/38; quality-gate contract 27/27; `git diff --check` lulus. Controlled
  staging probe SELECT-only menghasilkan `named_fk_wrong_contract`; ledger
  identik dan seluruh akun/file sementara dibersihkan.
- Hasil review auditor: PASS; output fixed-code, urutan alasan, metadata exact,
  timeout/kill, dan redaksi dinilai layak.
- Risiko sisa: aspek kontrak FK yang salah belum dibedakan antara target/schema/
  kolom/action; constraint belum boleh di-drop atau dibuat ulang.
- Batch berikutnya: A5.8 exact FK contract preflight lalu forward migration baru
  hanya setelah review dan bukti orphan aman.

## Batch 125 — A5.8 exact named-FK contract preflight

- Waktu: 2026-09-04 22:31 WIB.
- Prioritas: memecah `named_fk_wrong_contract` menjadi aspek local column,
  schema, target, delete/update rule, cardinality, atau metadata incomplete.
- Ringkasan auditor/fixer: auditor mewajibkan satu SELECT metadata dan kode
  redacted berprecedence. Fixer membuat probe/contract; auditor menyatakan PASS.
- File berubah: `tools/db/auth_login_failure_named_fk_contract_probe.php`,
  `tools/tests/a5_auth_login_failure_named_fk_contract_probe_contract_smoke.php`,
  serta wiring required quality gate beserta contract.
- Perubahan utama: metadata constraint bernama diperiksa tanpa row data, DDL,
  ledger, adoption, atau replay SQL lama.
- Validasi: PHP lint/validate lulus; A5.8 contract awal 57/57 dan quality-gate
  contract 27/27. Probe SELECT-only menghasilkan
  `named_fk_metadata_incomplete`; ledger identik dan akun sementara bersih.
- Hasil review auditor: implementasi probe PASS, tetapi hasil staging belum
  mengizinkan DDL. Investigasi berikutnya membuktikan ini false negative
  visibility MariaDB, bukan FK rusak.
- Risiko sisa: akun SELECT-only tidak dapat melihat row
  `REFERENTIAL_CONSTRAINTS`; dikoreksi pada Batch 126.
- Batch berikutnya: A5.9 permission visibility correction dan rerun metadata.

## Batch 126 — A5.9 koreksi visibility metadata MariaDB

- Waktu: 2026-09-04 22:49 WIB.
- Prioritas: mencegah keterbatasan izin metadata dianggap sebagai schema drift
  dan menutup track FK auth tanpa DDL yang tidak perlu.
- Ringkasan auditor/fixer: bukti server menunjukkan akun SELECT-only melihat
  KCU tetapi tidak referential rule; penambahan REFERENCES sementara membuka
  metadata exact. Fixer mengoreksi tiga probe; auditor meminta mixed-state
  precedence dan penamaan acknowledgement yang jujur. Main menyelesaikan
  fixture akhir; review menyatakan PASS bersyarat grant eksternal.
- File berubah: tiga probe A5.6–A5.8 dan tiga contract terkait. Tidak ada file
  aplikasi, POS/APK, SQL migration, atau schema/data yang diubah.
- Perubahan utama: state `permission_visibility_limited` tidak lagi dianggap
  drift dan hanya muncul bila seluruh fakta KCU lain exact. Fakta
  table/type/index/KCU/competing selalu menang. Flag CLI bernama
  `--permission-profile-acknowledgement=metadata-read`, bukan klaim enforcement.
- Validasi: PHP lint lulus; A5.5 40/40; A5.6 48/48; A5.7 62/62; A5.8 76/76;
  quality-gate contract 27/27; diff check lulus. Controlled staging dengan
  grant table-scoped SELECT+REFERENCES menghasilkan A5.6 `already_present`,
  A5.7/A5.8 tanpa reason. Rerun fingerprint terpisah menghasilkan 4/4 kandidat
  `eligible_evidence`, tiga historical tetap excluded. Kedua run membuktikan
  ledger unchanged; akun/option file dibersihkan.
- Hasil review auditor: FK auth exact; tidak ada repair, orphan preflight, DDL,
  legacy replay, atau ledger adoption yang diperlukan. Redacted grant
  attestation tersimpan di luar repo:
  `/var/lib/finance-a5-runtime/evidence/a5_metadata_grant_attestation_20260904_224853.json`
  dan `a5_fingerprint_grant_attestation_20260904_224925.json`.
- Risiko sisa: acknowledgement tidak memverifikasi grant dari dalam tool;
  operator/DBA tetap wajib memverifikasi profil minimum dari luar.
- Batch berikutnya: kembali ke fondasi luas A5—clean install, upgrade,
  backup/restore, runtime matrix, dan signature—tanpa melanjutkan track FK auth.

## Batch 127 — A5.10 atomic backup bundle dan restore preflight

- Waktu: 2026-09-04 23:12 WIB.
- Prioritas: membentuk artefak backup yang utuh, terverifikasi, dan tidak dapat
  tertukar sebelum restore drill disposable dilakukan.
- Ringkasan auditor/fixer: fixer membuat manifest builder/preflight DB-free.
  Auditor menemukan resolver gzip dari PATH, TOCTOU publish, permission/fsync
  yang belum fail-closed, parent/race, dan final yang tertinggal saat sync gagal.
  Fixer menutup temuan secara bertahap; review akhir menyatakan PASS.
- File berubah: `tools/db/backup_bundle_manifest.php`,
  `tools/db/restore_preflight.php`,
  `tools/tests/a5_backup_bundle_restore_preflight_contract_smoke.php`, serta
  wiring required quality gate beserta contract. `backup_full.sh` tidak diubah.
- Perubahan utama: builder menyalin archive regular ke private sibling stage,
  memverifikasi identitas/hash/size/gzip/katalog, menulis manifest kanonis,
  memaksa mode 0700/0600, fsync, owner-controlled parent, parent flock, rename
  atomik, dan trusted `/usr/bin/sync`. Gzip dikunci ke `/usr/bin/gzip` yang
  canonical/root-owned/non-writable. Restore preflight hanya menghasilkan plan
  dan mewajibkan reverify tepat sebelum stream konsumsi; tidak memiliki client
  database atau target restore.
- Validasi: PHP lint lulus; A5.10 contract 24/24; A4 migration/backup/restore
  19/19; quality-gate contract 27/27. Quality profile `parallel` lulus required
  34/34 dan development 4/4. Release/preflight tetap diblokir tepat oleh direct
  credential staging, bukan regresi A5. Empat hash file POS/APK tetap identik.
- Hasil review auditor: PASS; A5.11 siap dimulai hanya untuk fresh disposable
  database dengan immediate reverify dan tanpa overwrite sumber/staging.
- Risiko sisa: A5.10 belum menjalankan restore nyata. Test hook hanya tersedia
  saat library mode dan tidak dapat diaktifkan lewat CLI normal. Failure sync
  membersihkan bundle final sambil lock masih dipegang dan mempertahankan error
  primer.
- Batch berikutnya: A5.11 controlled disposable restore drill, lalu verifikasi
  ledger/fingerprint/health sebelum cleanup target disposable.

## Batch 128 — A5.11 controlled disposable restore drill

- Waktu: 2026-09-05 05:51 WIB.
- Prioritas: membuktikan backup A5 dapat dipulihkan nyata tanpa menimpa atau
  mengubah database Finance sumber.
- Ringkasan auditor/fixer: auditor mengunci target/user acak lokal, akun restore
  target-only, immediate bundle reverify, registry/fingerprint, evidence
  redacted, dan cleanup exact. Fixer menyiapkan runner/contract serta wiring;
  review auditor menemukan metacommand client, endpoint remote, dan reap child.
  Temuan ditutup dengan sandbox, scanner fail-closed, local-only endpoint, dan
  cleanup process sebelum live drill. Review akhir menyatakan PASS.
- File berubah: `tools/db/disposable_restore_drill.php`,
  `tools/tests/a5_disposable_restore_drill_contract_smoke.php`, serta wiring
  required di `tools/tests/finance_quality_gate.php` dan contract-nya.
- Perubahan utama: runner membuat database/user `a511_restore_<16hex>` yang
  fresh, memberi privilege hanya pada target tanpa global/grant option,
  memverifikasi bundle ulang tepat sebelum stream, memakai trusted
  `/usr/bin/gzip` dan `/usr/bin/mariadb --sandbox --local-infile=0`, menolak
  metacommand/escape lintas database, menjalankan migration runner resmi dan
  fingerprint, lalu selalu membersihkan target serta option file sementara.
- Validasi: PHP lint lulus; A5.11 contract 42/42; A5.10 24/24; A4 migration,
  backup, restore 19/19; quality-gate contract 27/27. Live drill menghasilkan
  registry `COMPATIBLE_V1`, fingerprint 4/4, dan cleanup verified. Post-check
  independen memastikan target DB/user `0/0`, source registry tetap satu row,
  backup checksum valid, evidence mode 0600/redacted, dan tidak ada option
  target/admin sementara. Quality profile parallel lulus required 35/35 dan
  development 4/4. Empat hash file POS/APK tetap identik.
- Hasil review auditor: PASS A5.11 post-live. Evidence retained di luar repo:
  `/var/lib/finance-a5-runtime/evidence/a5_disposable_restore_962512dbd73727fb.json`.
- Risiko sisa: release/preflight masih diblokir direct credential database
  staging. Live drill membuktikan upgrade dari backup staging, belum menjadi
  bukti clean install customer, rollback release, atau retention operasional.
- Cleanup material: database dan user disposable dihapus permanen setelah
  verifikasi; keduanya hanya fixture dan dapat dibuat ulang dari bundle sumber
  yang tetap disimpan. Option credential sementara milik drill juga dihapus.
- Batch berikutnya: A5.12 baseline clean-install schema/seed kanonis, kemudian
  health check/rollback dan validasi retention.

## Batch 129 — Normalisasi roadmap dan control board tunggal

- Waktu: 2026-09-05 06:37 WIB.
- Prioritas: menghilangkan status fase yang tumpang tindih atau tampak selesai
  hanya karena satu batch lulus, terutama rollout UI A3 pada bagian 8.3.
- Ringkasan auditor/fixer: auditor memisahkan status implementasi, validasi,
  release/data, serta membedakan bukti batch dari penyelesaian fase. Fixer
  menambahkan pemeriksaan konsistensi roadmap dan memasukkannya ke quality gate.
  Main agent menata dua dokumen induk dan snapshot progress mengikuti kontrak
  tersebut.
- File berubah: `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`,
  `docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md`,
  `docs/2026-09-03_progress_roadmap_user_finance.md`,
  `tools/tests/roadmap_consistency_smoke.php`,
  `tools/tests/finance_quality_gate.php`,
  `tools/tests/finance_quality_gate_contract_smoke.php`, dan execution log ini.
- Perubahan utama: `_30` sekarang menjadi sumber status teknis tunggal dengan
  register ID permanen, status fase A0–A5, checklist rollout UI sembilan
  gelombang, dan register delapan SQL staging/server utama. `_28` hanya memuat
  C0–C5 komersialisasi serta membaca satu gerbang handoff dari `_30`. A3
  dikoreksi menjadi `PARTIAL`: A3.1 dan fondasi A3.2 lulus, tetapi rollout 8.3
  serta visual UAT belum selesai. A2 menjadi `OPERATIONAL_PENDING`, A4 menjadi
  `TOOLING_PASS`, sedangkan A0, A1, dan A5 tetap `PARTIAL`.
- Keputusan controller: tidak dibuat. Audit memuat path, deployment, database,
  security, dan backlog internal; menampilkannya melalui route web menambah
  surface akses tanpa manfaat operasional. Markdown terversi dan gate otomatis
  menjadi media kendali saat ini.
- Temuan tambahan auditor: `AUD-A1-SYS-01` mencatat halaman System Tools yang
  masih perlu memisahkan izin baca sensitif, meredaksi path/metadata backup,
  dan membatasi field konfigurasi. Temuan dicatat, belum diperbaiki pada batch
  dokumentasi ini.
- Database/SQL: tidak ada schema, data, atau SQL yang diubah/dijalankan pada
  batch ini.
- Validasi: PHP lint 3/3 lulus; roadmap consistency 22/22 dengan 38 register
  item dan 8 SQL; quality-gate contract 27/27; quality profile `parallel`
  lulus required 36/36 dan development 4/4. Release serta preflight tetap
  diblokir secara benar oleh credential database langsung pada staging. Diff
  whitespace lulus dan empat hash source POS/APK yang dilindungi tetap identik.
- Hasil review auditor: PASS tanpa temuan blocking. Seluruh P0/P1/P2, temuan
  System Tools, sembilan gelombang UI, dan delapan SQL root terpetakan; status
  A2/A3/A4 tidak menyesatkan, batas `_30`/`_28` konsisten, policy SQL aman,
  dan keputusan tidak membuat controller dinilai tepat. PASS hanya berlaku
  untuk normalisasi dokumentasi/gate, bukan klaim UAT atau release produksi.
- Risiko sisa: control board tetap perlu diperbarui bersamaan dengan setiap
  batch; test mencegah struktur/ID/SQL register hilang tetapi tidak dapat
  menggantikan penilaian acceptance atau UAT manusia. Recovery Git juga masih
  menjadi blocker packaging.
- Batch berikutnya: tutup temuan P0 `AUD-A1-SYS-01` dalam batch kecil, kemudian
  lanjutkan A5.12 baseline clean-install. Rollout A3.2 dikerjakan per rumpun
  tanpa menyentuh kontrak APK yang dilindungi.

## Batch 130 — Dashboard roadmap internal yang user-friendly

- Waktu: 2026-09-05 07:28 WIB.
- Prioritas: menampilkan control board audit dan roadmap komersialisasi sebagai
  halaman web yang mudah dibaca tanpa membuat sumber status ketiga.
- Ringkasan auditor/fixer: auditor menetapkan fixed-document parser, output
  ter-escape, GET-only, no-store, dan boundary internal. Fixer membuat reader,
  controller, view, filter, serta release exclusion. Review pertama membuka
  readiness hardcode dan guard non-production terlalu luas; fixer mengganti
  readiness menjadi turunan 12 fase dan membuat allowlist development plus
  marker internal. Review akhir auditor menyatakan PASS.
- File berubah/dibuat: `application/controllers/Audit.php`,
  `application/libraries/AuditRoadmapReader.php`,
  `application/views/audit/roadmap.php`,
  `.codex/internal_audit_dashboard.enabled`, tabel status C0–C5 pada roadmap
  `_28`, `tools/release/package_policy.json`,
  `tools/release/ReleasePackagePolicy.php`, contract artefak, smoke dashboard,
  wiring quality gate, roadmap `_30`, progress report, dan execution log ini.
- Konfigurasi staging di luar repository: vhost Nginx
  `/www/server/panel/vhost/nginx/pos.namuacoffee.com.conf` menolak akses statis
  ke `/docs/` dan `/.codex/`; syntax test dan reload Nginx lulus.
- Perubahan utama: `/audit/roadmap` membaca hanya tabel kanonis A0–A5, register
  temuan, rollout UI 8.3, register SQL, dan C0–C5. Halaman menampilkan cards,
  badge tiga dimensi, alasan blocker, tabel responsive, serta pencarian/filter
  temuan. Tidak ada edit, POST, export, AJAX, path input, raw Markdown, atau
  akses model bisnis.
- Boundary akses: hanya aktif bila `ENVIRONMENT=development` dan marker fixed
  valid; environment lain atau marker invalid menghasilkan 404 sebelum auth.
  Pada staging, hanya superadmin yang dapat membuka. Controller, reader, view,
  marker, `_NOTE`, dan docs bertanggal dikecualikan dari artefak customer.
- Database/SQL: tidak ada schema, data, migration, menu, atau SQL yang dibuat
  maupun dijalankan. URL memakai routing default CodeIgniter; `routes.php`
  tidak disentuh.
- Validasi: PHP lint lulus; dashboard smoke 27/27; artifact contract 10/10;
  roadmap consistency 22/22; quality-gate contract 27/27; Composer valid dengan
  warning deprecation dari Composer lama. Quality profile `parallel` lulus
  required 37/37 dan development 4/4. HTTP anonymous: dashboard 307 ke login,
  raw docs 404, marker 404, dan config `.codex` 404. Nginx config test lulus.
- Hasil review auditor: PASS tanpa blocker keamanan, logika, atau UX pada scope
  batch internal ini. Readiness hanya menjadi `READY` bila seluruh A0–A5 dan
  C0–C5 berstatus `DONE`.
- Risiko sisa: visual UAT dengan sesi superadmin pada desktop/mobile belum
  dilakukan. Aturan blok `/docs/` dan `/.codex/` harus diterapkan tersendiri
  pada vhost server internal lain; halaman sengaja tidak tersedia pada build
  production/customer.
- Batch berikutnya: kembali ke temuan P0 `AUD-A1-SYS-01`, lalu A5.12. Rollout
  UI A3.2 tetap berjalan per rumpun dan dashboard tidak mengubah status fase.

## Batch 131 — Dashboard roadmap bertab

- Waktu: 2026-09-05 07:51 WIB.
- Prioritas: mengurangi halaman roadmap yang terlalu panjang dan membuat tiap
  jenis pekerjaan lebih mudah dipahami tanpa membentuk sumber status baru.
- Ringkasan auditor/fixer: fixer membagi dashboard menjadi lima tab dan
  mempertahankan filter temuan. Auditor memeriksa aksesibilitas, state awal,
  hash URL, tampilan mobile, dan boundary read-only; review akhir `PASS`.
- File berubah: `application/views/audit/roadmap.php` dan
  `tools/tests/audit_roadmap_dashboard_smoke.php`.
- Perubahan utama: tab `Ringkasan`, `Temuan`, `UI 8.3`, `SQL`, dan
  `Komersialisasi`; hanya satu panel aktif saat awal, tab disimpan di URL hash,
  alias anchor lama tetap bekerja, dan navigasi mobile dapat digeser horizontal.
- Validasi: PHP lint lulus; dashboard smoke 30/30; diff whitespace lulus.
- Hasil review auditor: `PASS`; tidak ada form, writer, AJAX, export, atau
  sumber data baru.
- Risiko sisa: visual UAT sesi superadmin pada beberapa ukuran layar tetap
  diperlukan dan status roadmap tetap berasal dari dua Markdown induk.
- Batch berikutnya: fondasi Telegram Bot internal sebelum kembali ke backlog
  teknis berikutnya.

## Batch 132 — Fondasi Telegram Bot dan deployment migration

- Waktu: 2026-09-05 08:30 WIB.
- Prioritas: menyediakan kanal Telegram internal yang setara dengan kebutuhan
  laporan inti WhatsApp, tetapi memakai Bot API langsung dan tidak bergantung
  pada service Node WhatsApp.
- Ringkasan auditor/fixer: auditor membatasi MVP pada group/channel allowlist,
  command `/menu`, `/omzet`, `/belanja`, jadwal, queue, log, dan setting.
  Fixer membangun modul; review pertama memperbaiki sumber refund omzet,
  webhook sinkron, status `UNKNOWN`, TLS/response cap, dan kontrak test.
  Saat deployment, auditor menemukan validasi nama database runner terlalu
  longgar; fixer menutupnya dan review akhir menyatakan `PASS`.
- File berubah/dibuat: controller `Telegram` dan `Telegram_webhook`, model
  `Telegram_model`, library `TelegramBotClient` dan `TelegramReportService`,
  empat view `application/views/telegram/`, migration
  `sql/2026-09-05a_telegram_bot_foundation.sql`, katalog/runner migration,
  smoke Telegram/A5/quality gate, dua roadmap induk, progress report,
  deployment secret contract, dan execution log ini.
- Perubahan utama: target group/channel internal, jadwal omzet/belanja, queue
  idempoten dengan lease, webhook deduplikasi dan immediate ACK, worker CLI,
  log delivery, resolusi manual `UNKNOWN` dengan alasan/audit/resend baru,
  env-only token/secret, sidebar database, serta empat page permission yang
  awalnya hanya diberikan penuh kepada `SUPERADMIN`. Runner, fingerprint,
  probe FK, dan disposable restore sekarang selalu memilih database target
  lewat file nama database privat; MariaDB tidak lagi diandalkan membaca
  `database=` dari defaults file.
- Database/SQL: backup privat dibuat di
  `/var/lib/finance-a5-runtime/backups/pre_telegram_20260905_ITFKDL.sql.gz`
  dan checksum valid. Diagnosis awal menjalankan SQL idempoten langsung karena
  runner belum memilih database. Setelah runner diperbaiki, migration resmi
  mencatat `applied=1/skipped=1`; replay `applied=0/skipped=2`. Staging berisi
  6 tabel, 13 FK, 34 index, 4 page unik, 5 menu unik, 4 grant penuh
  `SUPERADMIN`, 0 grant role lain, dan 2 ledger checksum exact.
- Validasi: lint PHP lulus; Telegram smoke 50/50; quality-gate contract 27/27;
  A5 catalog 28/28; A5 executor 35/35; A5 fingerprint/FK/restore contracts
  lulus; migration validate/plan/dry-run/apply/replay lulus. Probe staging
  nyata menghasilkan fingerprint kandidat 4/4, FK `already_present`, detail
  FK tanpa alasan, dan named-FK tanpa alasan. CLI worker menghasilkan 0
  enqueue/0 proses pada target kosong;
  anonymous dashboard redirect login, webhook GET 405, dan POST tanpa secret
  403. Quality profile `parallel` lulus required 38/38 dan development 4/4;
  release/preflight tetap diblokir secara benar oleh credential database
  langsung staging. Empat hash source POS/APK tetap identik.
- Hasil review auditor: `PASS` untuk kode, migration, dan staging apply. Omzet
  memakai receipt PAID FINAL/DEPOSIT dikurangi refund POSTED; webhook hanya
  enqueue; pengiriman timeout tidak di-retry otomatis sebelum operator
  menyelesaikan status `UNKNOWN`.
- Risiko sisa: token bot, webhook secret/registrasi HTTPS, cron, target chat,
  serta UAT kirim nyata belum diprovision. MVP belum mencakup broadcast member,
  template/media, atau parity penuh seluruh fitur WhatsApp.
- Batch berikutnya: provision dan UAT Telegram saat credential bot tersedia,
  kemudian kembali ke `AUD-A1-SYS-01` atau A5.12 sesuai control board.

## Batch 133 — Panduan setup Telegram di aplikasi

- Waktu: 2026-09-05 09:20 WIB.
- Prioritas: memberi pengguna dan administrator urutan setup Telegram yang
  jelas dari pembuatan bot sampai webhook, target, jadwal, tes, dan diagnosis,
  tanpa menaruh credential dalam aplikasi atau dokumentasi.
- Ringkasan auditor/fixer: auditor memilih page registry `tg.guide` view-only
  dan migration seed baru agar sidebar tetap mempunyai satu sumber kebenaran.
  Fixer membuat panduan tujuh tab dan kontrak test. Review pertama menolak
  contoh `curl` yang dapat mendorong secret ke process list serta CTA Settings
  yang dapat berakhir 403; fixer mengganti contoh dengan checklist operasi
  non-eksekusi dan membuat CTA mengikuti permission. Review akhir `PASS`.
- File berubah/dibuat: `application/controllers/Telegram.php`, view
  `application/views/telegram/guide.php` dan `settings.php`, migration
  `sql/2026-09-05b_telegram_setup_guide.sql`, katalog migration, runner restore
  drill, contract Telegram/A5/roadmap, dua roadmap induk, progress report,
  deployment secret contract, dan execution log ini.
- Perubahan utama: `/telegram/guide` mempunyai tab Mulai, Buat Bot, Server,
  Chat ID, Webhook, Target & Jadwal, serta Tes & Masalah. Isi menjelaskan
  BotFather, Privacy Mode, environment FPM/CLI, `getUpdates`, operasi
  `getMe`–`setWebhook`–`getWebhookInfo`, allowlist, cron, status `UNKNOWN`, dan
  peringatan penggunaan grup internal tepercaya. Halaman tidak mempunyai form,
  writer, AJAX, custom script, pembacaan environment, atau nilai credential.
- Database/SQL: backup privat
  `/var/lib/finance-a5-runtime/backups/pre_telegram_guide_20260905_3CxfHA.sql.gz`
  dibuat dengan mode 0600 dan checksum valid. Preflight page/menu/ledger 0/0/0.
  Runner menghasilkan `applied=1/skipped=2`, lalu replay
  `applied=0/skipped=3`. Staging mempunyai satu page aktif `tg.guide`, satu menu
  child `grp.telegram`, satu grant SUPERADMIN view-only, nol grant role lain,
  dan ledger checksum exact.
- Validasi: PHP lint lulus; Telegram smoke 118/118; A5 catalog 29/29;
  executor 35/35; legacy inventory 17/17; fingerprint 43/43; disposable
  restore contract 50/50; roadmap consistency 22/22 dengan 10 SQL; migration
  validate/plan/dry-run/apply/replay dan backup checksum lulus. Quality profile
  `parallel` lulus required 38/38 dan development 4/4; release/preflight tetap
  diblokir secara benar oleh credential database langsung staging.
- Hasil review auditor: `PASS` untuk staging. Registry, dependency, checksum,
  escaping, permission view-only, conditional CTA, serta larangan secret pada
  UI/command sudah sesuai.
- Risiko sisa: UAT sesi SUPERADMIN dan role tanpa izin belum dijalankan di
  browser nyata. Token/webhook/cron/target belum diprovision; setiap anggota
  grup allowlist masih dapat meminta laporan sampai otorisasi per pengirim
  ditambahkan.
- Batch berikutnya: provision dan UAT Telegram bila credential tersedia;
  security berikutnya menambah otorisasi pengirim sebelum memakai grup yang
  tidak sepenuhnya tepercaya. Setelah itu kembali ke `AUD-A1-SYS-01`/A5.12.

## Batch 134 — Setup Assistant Telegram untuk pengguna non-programmer

- Waktu: 2026-09-05 10:04 WIB.
- Prioritas: mengubah onboarding Telegram dari panduan teknis menjadi alur
  pengguna yang jelas, sambil memisahkan credential dan tugas deployment yang
  memang tidak aman dikelola melalui UI biasa.
- Ringkasan auditor/fixer: auditor menetapkan token, webhook secret, URL HTTPS
  kanonis, environment PHP-FPM/CLI, dan cron sebagai tugas admin server.
  Verifikasi identitas bot, discovery target, simpan target, test kirim, master
  switch, pemasangan/pemeriksaan webhook, serta jadwal dipindahkan ke UI. Fixer
  menerapkan endpoint setup dan dua halaman; review integrasi menemukan nama
  field kandidat yang sempat berbeda lalu diselaraskan ke opaque
  `candidate_key`. Review pertama menyetujui migration staging; review akhir
  menemukan token bot lama masih tertulis di `_NOTE.md`, lalu token tersebut
  langsung direduksi tanpa ditampilkan. Rotasi melalui BotFather tetap harus
  dilakukan pemilik karena nilai yang pernah terekspos tidak boleh dipakai lagi.
- File berubah/dibuat: `application/controllers/Telegram.php`,
  `Telegram_webhook.php`, `application/libraries/TelegramBotClient.php`,
  `application/models/Telegram_model.php`, view Telegram `settings.php` dan
  `guide.php`, `sql/2026-09-05c_telegram_safe_activation_default.sql`, katalog
  dan disposable restore A5, contract test Telegram/A5, dua roadmap induk,
  progress report, `docs/deployment_secret_contract.md`, `docs/_NOTE.md`, dan
  log ini.
- Perubahan utama: Pengaturan Telegram menjadi Setup Assistant dengan tab
  pengguna dan admin server. Panduan menjadi tiga tab dan menjelaskan langkah
  BotFather sampai jadwal. Client hanya memakai tiga environment
  `FINANCE_TELEGRAM_*`; URL webhook tidak berasal dari host/request. Endpoint
  setup memakai RBAC, POST, scoped CSRF, PRG, discovery session 10 menit,
  kandidat satu kali pakai, dan hasil Telegram yang sudah disanitasi. Master
  switch ditolak backend bila konfigurasi/target belum siap. Webhook valid saat
  switch OFF di-ACK 200 tanpa menjalankan command.
- Database/SQL: sebelum perubahan dibuat backup privat
  `/var/lib/finance-a5-runtime/backups/pre_telegram_setup_assistant_20260905_octMcQ.sql.gz`
  mode 0600, size 21.062.486 byte, SHA256
  `2b56c6e7160f6f28875d4a8fb31bfe32169e4f7f8c6348c5502d348e27b669f1`.
  Runner menghasilkan `applied=1/skipped=3`, replay
  `applied=0/skipped=4`; `telegram.enabled=0` dengan status `UNTOUCHED`, ledger
  berjumlah empat dan checksum `09-05c` exact.
- Validasi: PHP lint seluruh file PHP batch lulus; Composer validate lulus
  dengan deprecation tool lama; Telegram smoke 102/102; A5 catalog 30/30,
  executor 35/35, legacy 17/17, fingerprint 43/43, disposable restore 50/50;
  migration validate/plan/apply/replay dan roadmap consistency 22/22 lulus.
  Quality gate `parallel` lulus required 38/38 dan development 4/4. Anonymous
  Settings/Guide redirect 307 ke login dan webhook tanpa secret ditolak 403.
  Hash empat file POS/APK yang dilindungi tetap identik.
- Hasil review auditor: migration aman diterapkan. Karena migration `09-05a`
  yang sudah immutable dahulu menanam switch ON, rollout wajib memastikan
  ledger `09-05c` sudah tercatat; kondisi ini sudah dipenuhi pada staging.
  Token mentah pada `_NOTE.md` telah direduksi dan scan ulang file teks tidak
  menemukan pola token lain. Angka gate lama pada roadmap juga diselaraskan
  menjadi required 38/38 dan development 4/4.
- Risiko sisa: tiga environment dan cron belum diprovision, sehingga uji bot
  nyata belum dapat dilakukan. Token yang pernah terekspos wajib di-revoke dan
  dibuat ulang melalui BotFather sebelum diprovision. Setiap anggota grup allowlist masih dapat
  meminta laporan; gunakan hanya grup internal tepercaya sampai otorisasi per
  pengirim tersedia. Release/preflight tetap diblokir keputusan credential DB
  langsung staging, bukan oleh batch Telegram.
- Batch berikutnya: pemilik membuat bot dan menyerahkan token secara aman;
  admin server memprovision tiga environment serta cron; pemilik menyelesaikan
  Setup Assistant dan UAT nyata. Setelah itu kembali ke `AUD-A1-SYS-01`/A5.12.

## Batch 135 — Provisioning nyata Telegram staging (parsial)

- Waktu: 2026-09-05 10:37–10:48 WIB.
- Prioritas: membuat panduan benar-benar implementatif bagi admin aaPanel,
  memprovision bot nyata tanpa menyimpan credential di Git/database, dan
  menyiapkan notifikasi penyelesaian Codex.
- Ringkasan fixer tunggal: token ditempatkan di file root-only di luar webroot;
  webhook secret dibuat acak di server; URL kanonis ditetapkan ke endpoint HTTPS
  staging; PHP-FPM dan cron memakai environment yang sama. Panduan UI diperjelas
  dengan lokasi file dan perintah aaPanel yang langsung dapat diikuti.
- File aplikasi berubah: `application/views/telegram/guide.php`,
  `docs/deployment_secret_contract.md`, dua roadmap induk, progress report, dan
  execution log. Konfigurasi host di luar repo: `/var/lib/finance-telegram/`,
  `/etc/init.d/php-fpm-81`, `/www/server/php/81/etc/php-fpm.conf`,
  `/usr/local/sbin/finance-telegram-run-due`, `/etc/cron.d/finance-telegram`,
  `/usr/local/sbin/finance-codex-notify`, dan user-level Codex config.
- Perubahan utama: file credential mode 0600, folder mode 0700, whitelist tiga
  environment pada pool PHP-FPM, restart penuh PHP 8.1, scheduler setiap menit,
  serta hook `agent-turn-complete` yang hanya mengirim pesan tetap tanpa prompt
  atau jawaban thread.
- Validasi: syntax PHP-FPM lulus, restart/status PHP-FPM lulus, probe lokal
  melalui Nginx/PHP-FPM menghasilkan `READY`, lint notification script lulus,
  cron wrapper berjalan dan secara benar melaporkan modul masih OFF. Bot API
  memverifikasi bot nyata sebagai `@cacacia_bot` dan webhook belum terpasang.
- Hasil review: boundary credential dan scheduler `PASS`; token tidak ditaruh
  di source, database, dokumentasi, maupun argumen proses. Penyelesaian target
  dan UAT `BLOCKED` karena API Telegram mengembalikan HTTP 409: ada consumer
  lain yang sedang melakukan `getUpdates`.
- Risiko sisa: target grup Namua, test kirim, master switch, webhook, dan hook
  Codex belum dapat diuji end-to-end sampai long-poll lain dihentikan dan
  `/menu` dikirim ulang. Token yang pernah dibagikan melalui chat harus di-revoke
  setelah UAT dan diganti hanya pada file privat.
- Batch berikutnya: selesaikan UAT Telegram setelah konflik hilang; pekerjaan
  kode dilanjutkan langsung ke `AUD-A1-SYS-01` tanpa menunggu.

## Batch 136 — Baca sensitif System Tools

- Waktu: 2026-09-05 10:42–10:48 WIB.
- Prioritas: menutup P0 `AUD-A1-SYS-01` tanpa memperluas controller atau
  menambah migration/permission baru.
- Ringkasan fixer tunggal: hak View dipertahankan untuk ringkasan umum; hak
  Export yang sudah tersedia dipakai sebagai izin baca sensitif terpisah.
  Teknisi yang melakukan writer wajib mempunyai Edit dan Export sekaligus.
- File berubah/dibuat: `application/controllers/System_tools.php`, view
  `application/views/system/dbtools.php`, `settings.php`, dan
  `dbtools_limited.php`; test `system_tools_mutation_csrf_smoke.php` dan
  `system_tools_sensitive_read_smoke.php`; roadmap/progress/log.
- Perubahan utama: user View-only tidak menerima root path, nama/tanggal/ukuran
  dump, log, detail replication/failover, atau konfigurasi. Full view hanya
  menerima whitelist key yang dikenal dan tidak pernah menerima password
  tersimpan. Endpoint status/detail memerlukan View+Export. Test koneksi DB
  berubah dari query URL menjadi POST+CSRF, memvalidasi parameter, dan tidak
  mengirim exception koneksi mentah ke browser.
- Validasi: lint seluruh PHP batch lulus; sensitive-read contract 18/18,
  mutation CSRF regression 74/74, roadmap consistency 22/22, Telegram
  regression 102/102, dan quality profile `parallel` required 38/38 serta
  development 4/4 lulus. Release/preflight tetap diblokir hanya oleh credential
  database langsung staging. Empat hash source POS/APK tetap identik.
- Hasil review: `PASS`; acceptance pemisahan izin, whitelist, redaksi, dan
  negative test terpenuhi. Tidak ada SQL baru dan tidak ada perubahan POS/APK.
- Risiko sisa: hak Export harus diberikan hanya kepada teknisi tepercaya;
  failover/replication nyata tetap membutuhkan prosedur serta UAT server.
- Batch berikutnya: A5.12 baseline clean-install schema/seed kanonis, sambil
  menyelesaikan UAT Telegram segera setelah konflik long-poll hilang.

## Batch 137 — Aktivasi Telegram dan notifikasi Codex ke Namua

- Waktu: 2026-09-05 11:09–11:18 WIB.
- Prioritas: menyelesaikan provisioning nyata Telegram staging setelah pemilik
  merotasi token secara mandiri pada file privat.
- Ringkasan fixer tunggal: token baru divalidasi tanpa dicetak; konflik
  `getUpdates` sudah hilang. Pesan grup menemukan target negatif bertipe GROUP
  dengan judul exact Namua. Target disimpan aktif, master switch dinyalakan,
  Chat ID notifikasi Codex disimpan root-only, dan webhook dipasang.
- Perubahan source: hanya pembaruan status pada dua roadmap induk, progress
  report, dan execution log. Tidak ada kode POS/APK atau SQL migration baru.
  Perubahan runtime normal terjadi pada `tg_target`, `tg_setting`, queue/log,
  `/var/lib/finance-telegram/codex_chat_id`, dan Telegram Bot API.
- Validasi: identitas bot `@cacacia_bot` valid; pesan koneksi langsung berstatus
  `SENT` HTTP 200 dengan message ID 537; hook Codex mencatat `notification sent`;
  webhook configured dan URL match; scheduler menghasilkan `processed=1`;
  queue UAT berstatus `SENT`, attempt 1, message ID 539, dan tepat satu delivery
  log. Pemeriksaan webhook akhir mempunyai pending update 0.
- Hasil review: `PASS` untuk outbound, queue, audit log, scheduler, webhook,
  serta notifikasi Codex. Token hasil rotasi tidak dimasukkan ke chat, source,
  database, dokumentasi, atau argumen proses.
- Risiko sisa: command inbound `/menu`, `/omzet`, dan `/belanja` belum diuji
  setelah webhook terpasang. Semua anggota target allowlist masih dapat meminta
  laporan; grup harus tetap internal tepercaya sampai allowlist pengirim ada.
- Batch berikutnya: A5.12 baseline clean-install schema/seed kanonis; UAT command
  Telegram dapat dilakukan paralel tanpa menyentuh POS Mobile/APK.

## Batch 138 — Baseline schema clean-install A5.12

- Waktu: 2026-09-05 11:18–11:28 WIB.
- Prioritas: menyediakan baseline schema-only yang dapat dipasang pada database
  kosong tanpa membawa data pelanggan, transaksi, credential, backup, staging,
  atau artefak operasional.
- Ringkasan fixer tunggal: metadata schema staging dipetakan menjadi 302 tabel;
  20 tabel backup/staging/temporary/history dikeluarkan sehingga baseline
  kanonis berisi 282 tabel. Guard dan test kontrak ditambahkan agar isi serta
  checksum baseline tidak berubah tanpa review.
- File dibuat/berubah: `sql/baseline/2026-09-05_clean_install_schema.sql`,
  `tools/db/clean_install_baseline_policy.json`,
  `tools/db/clean_install_baseline_guard.php`,
  `tools/tests/a5_clean_install_baseline_guard_smoke.php`, quality gate dan test
  kontraknya, dua roadmap induk, progress report, dan execution log.
- Perubahan utama: baseline hanya memuat DDL, membuang nilai runtime
  `AUTO_INCREMENT`, mengosongkan default token lama, membungkus pemulihan
  foreign-key check, serta melarang DML, user/grant, path absolut, default
  secret tidak kosong, dan tabel operasional. SHA-256 baseline:
  `dcea97a6cbaba13e404aab88122d41bfb79321ca788045f1c5e6f2401d843a0c`.
- Validasi: lint PHP lulus; guard baseline lulus; smoke baseline 12/12 dan
  kontrak quality gate 27/27. Drill database disposable berhasil mengimpor 282
  tabel, menjalankan 4 migration terdaftar, menghasilkan 0 row pada
  `auth_user`, `pos_order`, `crm_member`, dan `org_employee`, mempertahankan
  Telegram OFF, lalu menghapus database uji tanpa sisa. Quality gate required
  39/39 dan development 4/4 lulus.
- Hasil review: `PASS` untuk sub-batch baseline schema-only. Baseline layak
  dipakai sebagai titik awal instalasi baru, bukan untuk upgrade database lama.
- Risiko sisa: A5.12 secara keseluruhan belum selesai karena reference seed
  netral-pelanggan dan mekanisme bootstrap pemilik pertama belum diklasifikasi.
  Tujuh SQL legacy root juga masih berstatus non-deployable. Release/preflight
  tetap diblokir oleh credential DB langsung staging yang sudah diketahui.
- Batch berikutnya: lanjutkan A5.12 dengan klasifikasi seed minimal dan bootstrap
  pemilik pertama yang aman. Tidak ada file POS Mobile/APK yang disentuh.

## Batch 139 — Ringkasan hasil pada notifikasi Codex Telegram

- Waktu: 2026-09-05 11:30–11:38 WIB.
- Prioritas: membuat notifikasi penyelesaian ke Namua memberi informasi hasil,
  bukan hanya penanda bahwa tugas selesai.
- Ringkasan fixer tunggal: hook membaca hanya `last-assistant-message`; prompt
  pengguna dan output tool sengaja tidak digunakan. Teks diringkas dengan batas
  18 baris/2.400 karakter serta penyaringan blok kode, tautan, token Telegram,
  API key, password, dan secret.
- File dibuat/berubah: `tools/telegram/codex_notify.php`,
  `tools/tests/codex_telegram_notify_summary_smoke.php`, wrapper host
  `/usr/local/sbin/finance-codex-notify`, quality gate, deployment secret
  contract, roadmap audit, progress report, dan execution log.
- Validasi: lint tiga entrypoint PHP lulus; smoke redaksi/ringkasan 9/9;
  quality gate parallel required 40/40 dan development 4/4 lulus; scan
  repository menemukan 0 file yang memuat pola token Telegram. Wrapper tetap
  `root:root` mode 0700. Release/preflight masih diblokir hanya oleh credential
  database langsung staging yang sudah diketahui.
- Hasil review: `PASS`; laporan pengguna dapat dibaca langsung dari Telegram
  tanpa meneruskan prompt maupun data mentah proses.
- Risiko sisa: penyaringan berbasis pola bukan pengganti disiplin untuk tidak
  menulis rahasia pada jawaban akhir. Grup Namua tetap harus dibatasi sebagai
  grup internal tepercaya.
- Batch berikutnya: kembali ke klasifikasi seed dan bootstrap owner A5.12.
  Tidak ada file POS Mobile/APK atau schema database yang disentuh.

## Batch 140 — A5.12 seed clean-install dan bootstrap owner

- Waktu: 2026-09-05 11:39–11:57 WIB.
- Prioritas: menyelesaikan A5.12 agar database kosong tidak hanya mempunyai
  tabel, tetapi juga navigasi produk dan akun pemilik pertama yang aman.
- Ringkasan fixer tunggal: seed staging diklasifikasikan per tabel/kolom. Hanya
  `sys_matrix_group`, page/menu/alias, satu role global SUPERADMIN, dan
  permission SUPERADMIN turunan yang diterima. Nama usaha pada Menu Book
  dinormalisasi; user, pegawai, role operasional, transaksi, saldo, stok, lot,
  target Telegram, queue, log, dan credential ditolak.
- File dibuat/berubah: `sql/2026-09-05d_a5_clean_install_reference_seed.sql`,
  `tools/db/build_clean_install_reference_seed.php`, policy/guard baseline,
  migration catalog, `tools/db/bootstrap_first_owner.php`, dua smoke A5.12,
  quality gate/contract, dua roadmap induk, progress report, dan execution log.
- Perubahan utama: migration `09-05d` hanya masuk policy `clean_install` dan
  otomatis dilewati policy `upgrade`. Bootstrap menerima username/email/password
  hanya dari JSON privat mode 0600 di luar repository, mengunci proses, menolak
  bila user/assignment sudah ada, membuat hash bcrypt cost 12, lalu memberikan
  hanya role SUPERADMIN. Percobaan bootstrap kedua selalu ditolak.
- Validasi: guard schema/seed 18/18 dan kontrak bootstrap 12/12 lulus. Drill
  disposable mengimpor 282 tabel, menjalankan lima migration, menghasilkan
  20 matrix group, 206 page, 241 menu, 10 alias, 1 role, 206 permission, 1
  owner, dan 1 assignment. Verifikasi password benar, role SUPERADMIN exact,
  percobaan kedua ditolak, serta database dan file temporer tersisa 0/0.
  Quality gate parallel required 41/41 dan development 4/4 lulus; release dan
  preflight tetap diblokir hanya oleh credential DB langsung staging.
- Hasil review: `PASS`; kegagalan awal akibat urutan ID Telegram ditemukan oleh
  drill dan diperbaiki menjadi registry → seed → migration Telegram. Upgrade
  tetap tidak menjalankan seed sehingga konfigurasi customer lama tidak ditimpa.
- Risiko sisa: SQL `09-05d` tidak boleh dijalankan manual pada server yang sudah
  berisi data. Tujuh SQL legacy masih memerlukan disposition upgrade; rollback,
  runtime matrix, retention, dan signature A5 tetap terbuka.
- Batch berikutnya: A5.13 health check pascainstalasi dan drill rollback upgrade.
  Tidak ada file POS Mobile/APK atau data staging aktif yang diubah.

## Batch 141 — A5.13 health check pascainstalasi dan rollback upgrade

- Waktu: 2026-09-05 12:37–13:06 WIB.
- Prioritas: memastikan hasil instalasi/upgrade dapat diverifikasi terhadap
  artefak rilis dan database dapat dikembalikan utuh bila health check gagal.
- Ringkasan fixer tunggal: health checker CLI mengikat file rilis ke
  `RELEASE-MANIFEST.json`, memvalidasi baseline dan katalog, lalu memeriksa 25
  tabel wajib, ledger migration exact, role/owner SUPERADMIN, reference seed
  clean-install, dan default Telegram OFF. Upgrade tidak menimpa seed customer.
  Drill disposable memulihkan backup, menerapkan empat migration upgrade,
  meluluskan health check, menyuntik canary gagal, memastikan kegagalan
  terdeteksi, lalu memulihkan backup yang sama dan membandingkan kembali schema,
  ledger, serta digest seed.
- File dibuat/berubah: `tools/db/post_install_health_check.php`,
  `tools/db/disposable_upgrade_rollback_drill.php`, dua smoke A5.13,
  quality gate/contract, dua roadmap induk, laporan progress, dan execution log.
  Tidak ada SQL baru dan file POS Mobile/APK tidak disentuh.
- Perubahan utama: pemeriksaan database memakai query read-only satu proses per
  probe agar error MariaDB gagal cepat dan output client selalu disamarkan.
  Manifest lama A5.11 memang ditolak setelah katalog A5.12 berubah; bundle
  runtime `a513_source_20260905` dibangun ulang dari archive yang sama dengan
  manifest katalog terbaru, tanpa mengubah archive sumber.
- Validasi: lint PHP lulus; health contract 13/13 dan rollback contract 13/13
  lulus. Probe read-only `db_finance` lulus dengan 25 tabel wajib, empat ledger
  migration, dan tiga owner SUPERADMIN aktif. Drill nyata berstatus `ok`:
  upgrade health lulus, canary ditolak dengan `migration_ledger_count`, rollback
  terverifikasi, seed digest sebelum/sesudah identik, source archive tetap,
  cleanup database/user/file sementara 0/0. Evidence privat mode 0600 berada
  di `/var/lib/finance-a5-runtime/evidence/a5_upgrade_rollback_f67b0b68ed090c09.json`.
  Quality gate `parallel` lulus pada required 43/43 dan development 4/4;
  release/preflight tetap hanya gagal pada dua credential DB langsung staging
  yang sudah diketahui.
- Hasil review: `PASS` untuk A5.13. Health check tidak menganggap kekurangan row
  permission SUPERADMIN pada database upgrade sebagai kerusakan karena role itu
  memang bypass; coverage exact tetap diwajibkan pada clean install. Tidak ada
  mutasi pada `db_finance` selama probe.
- Risiko sisa: manifest pada drill adalah release-contract staging, belum
  signed artifact customer penuh. Release nyata tetap diblokir credential DB
  langsung staging. Tujuh SQL legacy, matrix runtime, retention, signature,
  updater customer, serta UAT perangkat masih terbuka.
- Batch berikutnya: A5.14 compatibility matrix runtime PHP/MariaDB/extension,
  Node/Python, dan dependency lock.

## Batch 142 — A5.14 matrix kompatibilitas runtime dan register tahap terlewat

- Waktu: 2026-09-05 13:13–13:22 WIB.
- Prioritas: menutup A5.14 dengan kontrak runtime yang dapat diuji, lalu
  memastikan pekerjaan fase lama yang belum selesai tidak hilang dari roadmap.
- Ringkasan fixer tunggal: metadata PHP lama diselaraskan dengan source aktif;
  matrix machine-readable menetapkan PHP/FPM, MariaDB, Node/npm, Python,
  Composer, extension, dan lock dependency. Pemeriksa mempunyai mode contract
  DB-free dan probe staging. Audit urutan memisahkan pekerjaan benar-benar
  terlewat dari data dan POS/APK yang sengaja ditunda.
- File dibuat/berubah: `composer.json`, `composer.lock`,
  `tools/release/runtime_compatibility.json`,
  `tools/release/runtime_compatibility_check.php`,
  `tools/tests/a5_runtime_compatibility_contract_smoke.php`, quality gate dan
  contract-nya, kontrak runtime, dua roadmap induk, laporan progress, serta log
  ini. Tidak ada SQL, database, controller/model/route/view POS yang diubah.
- Perubahan utama: constraint Composer menjadi `>=8.1 <8.2`; tiga lock wajib
  diverifikasi; npm dependency registry wajib integrity hash dan dependency Git
  wajib commit penuh; Python wajib exact-pin+SHA-256. Staging tier quality gate
  kini memuat probe runtime A5.14.
- Validasi: lint seluruh PHP batch lulus; Composer validate lulus dan lock hash
  disegarkan tanpa perubahan versi paket; contract A5.14 10/10 dan probe
  staging lulus pada PHP CLI/FPM 8.1.32, MariaDB 10.6.23, Node 20.20.2, npm
  10.8.2, Python 3.10.12, Composer 2.0.14. Quality-gate contract 27/27 lulus.
- Hasil review: `PASS` untuk baseline A5.14 staging. Warning tidak disembunyikan:
  build PHP 8.1 memakai `--disable-fileinfo`, sehingga kemampuan WhatsApp file
  belum siap; Composer 2.0.14 juga perlu dinaikkan untuk build customer.
- Risiko sisa: matrix ini membuktikan staging, bukan dukungan semua runtime
  customer. Release tetap diblokir credential langsung, qualification runtime
  yang masih mendapat security maintenance, `fileinfo`, retention, signature,
  SQL legacy/updater, recovery Git, dan UAT perangkat.
- Tahap terlewat/rencana: register kanonis 0.6 pada `_30` mencatat A0, sisa A1,
  P2 A2, rollout UI A3.2, UAT A4, Telegram inbound, serta SQL legacy/updater.
  Urutan berikutnya A5.15 → A5.16 → SQL/updater → A0 → sisa A1/A2 → A3.2 →
  UAT. Data historis dan POS/APK tetap ditunda sesuai keputusan pemilik.
- Batch berikutnya: A5.15 retention dan lifecycle. Tidak ada file POS
  Mobile/APK yang disentuh.

## Batch 143 — A5.15 retention dan lifecycle aman

- Waktu: 2026-09-05 13:29–13:40 WIB.
- Prioritas: menghentikan penghapusan backup berbasis umur yang tidak
  terverifikasi dan menyediakan lifecycle runtime yang dapat diaudit.
- Ringkasan fixer tunggal: policy menetapkan root runtime privat, umur minimum,
  retain-newest, batas item/run, kelas yang tidak boleh age-delete, dan rule
  database archive-gated. Engine selalu plan dahulu; apply memerlukan prefix
  hash policy, memverifikasi identitas file/checksum, lalu memindahkan ke
  quarantine alih-alih menghapus.
- File dibuat/berubah: `tools/release/retention_policy.json`,
  `tools/release/retention_manager.php`, `tools/db/retention_preflight.php`,
  template logrotate, backup runner Linux/Windows dan contoh environment,
  contract smoke/quality gate, kontrak lifecycle, dua roadmap induk, laporan
  progress, dan execution log.
- Perubahan utama: backup Linux/Windows tidak lagi menjalankan `find -delete`
  atau `forfiles del`; setiap dump memperoleh SHA-256. Default Linux diarahkan
  ke `/var/lib/finance-backup`. Upload, ledger transaksi, jurnal, movement stok,
  payroll, queue aktif, Telegram UNKNOWN, credential, config device, dan state
  WhatsApp tidak pernah menjadi target age-based deletion.
- Validasi: lint PHP dan `bash -n` lulus; contract retention 19/19 lulus pada
  fixture disposable termasuk tamper/symlink/bad-checksum, konfirmasi salah,
  quarantine recoverable, audit 0600, dan cleanup 0. Quality-gate contract
  tetap 27/27; A4 cross-module 35/35 dan quality gate parallel required 45/45
  serta development 4/4 lulus. Dry-run staging menghasilkan kandidat 0,
  invalid 0, deleted 0. Release/preflight tetap hanya ditolak oleh credential
  database langsung staging yang sudah diketahui.
- Bukti database read-only: 887.635 total availability rebuild log; 52.445 row
  sukses non-mismatch berumur >90 hari (1–6 Juni 2026) menjadi kandidat archive;
  mismatch lama 0. Kandidat auth/WA/Telegram menurut batas policy semuanya 0.
- Hasil review: `PASS` untuk baseline teknis A5.15. Backup Telegram lama yang
  belum memiliki companion diberi SHA-256 mode 0600 dan diverifikasi tanpa
  mengubah archive. Root `/var/lib/finance-backup/{dumps,logs}` dibuat root-only
  mode 0700. Tidak ada purge atau pemindahan file staging.
- Risiko sisa: database purge tetap `enabled=false` sampai agregasi/archive,
  batch purge, dan acceptance rollback tersedia. Off-site encryption, schedule
  customer, serta pemindahan log/session service dari source menjadi pekerjaan
  deployment/C3. Quarantine permanen hanya boleh dipurge melalui review lain.
- Batch berikutnya: A5.16 signature dan provenance artefak. Tidak ada SQL,
  mutasi database, atau perubahan POS Mobile/APK.

## Batch 144 — A5.16 signature dan provenance artefak release

- Waktu: 2026-09-05 15:42–15:57 WIB.
- Prioritas: memastikan installer/updater dapat membuktikan penerbit, source,
  manifest, dan policy setiap paket sebelum paket diekstrak atau dijalankan.
- Ringkasan fixer tunggal: tool Ed25519 dipisahkan dari builder agar build host
  tidak memegang private key. Key generator hanya menulis key baru di luar
  repository; signer mengikat artefak, manifest, revision, epoch, migration
  catalog, package policy, dan runtime policy; verifier hanya membutuhkan
  public key tepercaya serta gagal tertutup pada setiap mismatch.
- File dibuat/berubah: `tools/release/artifact_signature.php`,
  `tools/tests/a5_artifact_signature_contract_smoke.php`, policy/test runtime,
  quality gate/contract, `docs/release_signature_provenance.md`, kontrak
  runtime, dua roadmap induk, laporan progress, dan execution log ini.
- Perubahan utama: private key harus raw Ed25519 mode 0600, dimiliki process,
  berada di parent privat di luar source, dan tidak pernah masuk provenance.
  Verifier memeriksa canonical JSON, trusted `key_id`, signature, SHA-256 dan
  ukuran artefak, isi manifest, seluruh checksum file, file type regular,
  source revision immutable, serta tiga digest policy. PHP `sodium` menjadi
  extension wajib pada runtime matrix.
- Validasi: lint seluruh PHP batch lulus. Contract A5.16 lulus 20/20, contract
  runtime A5.14 11/11, runtime smoke 20 check dengan satu warning `fileinfo`,
  quality-gate contract 27/27, roadmap consistency 22/22, artifact builder
  fixture 10/10, source static dan vulnerability scan lulus. Probe matrix
  staging lulus dengan warning lama `fileinfo` dan Composer 2.0.14.
  Quality gate parallel lulus pada required 46/46 dan development 4/4.
- Hasil review: `PASS` untuk kontrak teknis A5.16. Test negatif membuktikan
  penolakan paket berubah/unsigned, provenance berubah, key asing, private key
  berizin longgar atau berada di repo, revision ambigu, policy hilang, checksum
  manifest salah, path traversal, dan symlink. Test memakai key disposable dan
  tidak meninggalkan private key produksi.
- Risiko sisa: artefak customer nyata belum dapat dibangun karena release gate
  tetap menemukan credential database langsung pada `application/config/database.php`
  baris 20–21. Signing key produksi, trust-store installer, release approval,
  updater customer, SBOM, registry, dan recovery Git belum tersedia. A5 tetap
  `PARTIAL` walaupun A5.1–A5.16 baseline teknis sudah lulus.
- Database/SQL/POS: tidak ada SQL baru, query, mutasi database, atau perubahan
  controller/model/route/view POS. Hash empat file integrasi POS/APK tetap
  identik dengan baseline perlindungan.
- Batch berikutnya: `GAP-07`, yaitu disposition tujuh SQL legacy dan kontrak
  jalur updater customer. SQL lama tidak akan direplay blind ke database aktif.

## Batch 145 — GAP-07 disposition SQL legacy dan batas updater

- Waktu: 2026-09-05 16:21–16:55 WIB.
- Prioritas: menutup klasifikasi tujuh SQL legacy tanpa replay blind atau
  pencatatan ledger palsu, lalu menambah pengganti seed WhatsApp yang aman.
- Ringkasan fixer tunggal: bukti inventaris, checksum, clean-install baseline,
  dan fingerprint staging dipakai kembali. Keputusan final adalah 1 `baseline`
  (`08-15b`), 4 `enroll` hanya lewat fingerprint exact (`09-02a`, `09-03a`,
  `09-03b`, `09-04b`), 1 `replace` (`08-17e`), dan 1 `retire` (`09-04a`).
  Jalur otomatis dibatasi ke source-line `finance-managed-v1`; instalasi
  pre-catalog tetap berhenti fail-closed dan memerlukan bridge manual.
- File dibuat/berubah: policy/guard GAP-07, katalog dan policy baseline,
  `sql/2026-09-05e_whatsapp_safe_reference_seed.sql`, kontrak migration/
  restore/rollback/health/Telegram/quality gate, dua roadmap induk, laporan
  progress, dan execution log ini. File POS Mobile/APK tidak disentuh.
- Perubahan utama: migration runner kini memiliki 6 migration managed dan
  policy upgrade memilih 5. Seed `09-05e` hanya memastikan satu
  `REPORT_DEFAULT` dan `wa_session.id=1`; tidak memiliki DDL, UPDATE, DELETE,
  repair transaksi, atau perubahan stok. Guard DB-free mengikat seluruh
  keputusan ke checksum bukti dan menolak replay legacy/adopsi ledger.
- Database staging: fingerprint kandidat kembali lulus 4/4. Backup privat
  `/var/lib/finance-a5-runtime/backups/pre_gap07_20260905_164348_a333c774.sql.gz`
  beserta checksum dan bundle terverifikasi dibuat sebelum apply. Dry-run
  merencanakan 5 migration; apply menghasilkan 1 baru/4 skip dan replay
  menghasilkan 0 baru/5 skip. Postcondition template, session, dan ledger
  masing-masing tepat 1 dengan checksum `09-05e` exact.
- Validasi: lint PHP seluruh file batch lulus; migration catalog 33/33,
  executor 35/35, legacy inventory 17/17, fingerprint 43/43, GAP-07 19/19,
  clean-install guard 18/18, restore contract 50/50, rollback contract 13/13,
  health contract 13/13, Telegram 102 check, quality-gate contract 27/27, dan
  roadmap consistency 22/22 lulus. Quality gate `parallel` lulus required
  47/47 serta development 4/4; release/preflight tetap hanya diblokir dua
  credential database langsung staging yang sudah diketahui.
- Catatan drill: re-run tambahan restore atas dump operasional historis berhenti
  pada fase import (`restore_failed`), tetapi cleanup database/user/file
  disposable terverifikasi. Hasil ini tidak dianggap PASS dan tidak mengubah
  bukti A5.11 sebelumnya; jalur managed-v1 batch ini dibuktikan langsung pada
  staging. Drill dump lama dapat diinvestigasi terpisah tanpa menahan
  disposition SQL.
- Hasil review: `PASS` untuk disposition teknis `GAP-07` dan managed-v1.
  Ketujuh SQL lama tetap berada di daftar non-deployable. Server utama hanya
  menjalankan migration runner policy `upgrade`; file legacy dan seed
  clean-install-only `09-05d` tidak dijalankan manual.
- Risiko sisa: updater customer lintas versi, bridge instalasi pre-catalog,
  installer, signing key produksi, recovery Git, credential/secret produksi,
  database archive activation, dan UAT artefak masih terbuka. A5 tetap
  `PARTIAL`; UAT updater berada di `GAP-05`/C3.
- Batch berikutnya: `GAP-01`, yaitu penutupan credential produksi, rotasi
  secret, recovery Git, dan pemisahan runtime customer tanpa mengganggu POS/APK.

## Batch 146 — GAP-01.1 credential dan runtime keluar dari source

- Waktu: 2026-09-05 17:15–17:46 WIB.
- Prioritas: memisahkan data runtime serta credential database staging dari
  source tanpa menghapus upload, backup, log, output, atau mengubah kontrak
  POS/APK.
- Kondisi awal Git: repository tersedia pada `main` dan HEAD sama dengan
  `origin/main`, tetapi clone hanya mempunyai shallow history satu commit.
  Working state berisi 119 modified, 341 untracked, dan 1.133 deleted. Object
  connectivity lulus; temp pack tak-terpakai berukuran sekitar 2,1 GB tidak
  dihapus atau diolah ulang pada batch ini.
- Implementasi index/runtime: 1.367 entri runtime lama dilepas dari index Git
  memakai `git rm --cached`; file fisik tidak dihapus. Sebelum/sesudah
  terverifikasi tetap 344 file upload, 27 backup/log aktif, 6 generated/tmp,
  dan file credential dengan digest identik. Index kini hanya menyimpan lima
  `.gitkeep` untuk backup, upload, tmp, dan output. `.gitignore` menutup runtime,
  `.env`, local agent/editor state, `__pycache__`, dan bytecode.
- Implementasi credential: konfigurasi aktif lama dipindahkan tanpa mencetak
  nilainya ke `/var/lib/finance-config/database.php`; directory `root:www 0750`
  dan file `root:www 0640`. Source `application/config/database.php` sekarang
  bebas nilai koneksi, memakai fallback file privat hanya untuk
  development/staging, dan tetap memakai `DeploymentConfig` pada production.
  `.user.ini` membuka hanya root privat tersebut. PHP-FPM 8.1 direload.
- File dibuat/berubah: `.gitignore`, `.user.ini`, tiga placeholder runtime,
  `application/config/database.php`, kontrak repository/runtime, quality gate,
  perbaikan environment PHPStan, deployment secret contract, dua roadmap,
  laporan progress, dan execution log. Tidak ada SQL atau mutasi database.
- Validasi aplikasi: lint config lulus; private config lengkap tanpa mencetak
  nilai; koneksi staging melalui loader lulus; halaman web merespons 200 tanpa
  pola error database. Deployment secret contract 39/39, source preflight
  0 finding, backup isolation 32/32, dan runtime source 20 check dengan warning
  lama `fileinfo` lulus.
- Validasi Git/release: repository/runtime boundary lulus 29/29 dengan status
  `SHALLOW_PENDING`; `git fsck --connectivity-only` lulus. Quality profile
  `release` meluluskan required 48/48, development 4/4, deployment 1/1,
  preflight 1/1, security 1/1, dan Printer Agent runtime. Browser runtime
  timeout pada desktop. Bug environment tmp PHPStan diperbaiki dan contract
  15/15 lulus; setelah benar-benar berjalan, static analysis menemukan
  80 error source baru sehingga tidak disamarkan sebagai PASS. Quality profile
  `parallel` final lulus required 48/48, development 4/4, deployment 1/1, dan
  preflight 1/1.
- Hasil review: `PASS` untuk sub-batch pemisahan credential dan runtime index.
  Aplikasi staging tetap aktif dan release tidak lagi diblokir credential
  langsung. `GAP-01` tetap `IN_PROGRESS`, bukan selesai.
- Risiko sisa: 1.367 penghapusan index dan tiga placeholder baru masih staged
  sampai baseline commit dibuat. Full history belum diambil; temp pack tidak
  boleh dihapus sembarang. Credential lama wajib dirotasi saat cutover karena
  pernah berada pada source/history; rotasi akun database tidak dilakukan
  otomatis karena dapat dipakai aplikasi lain. Off-site encryption dan storage
  customer juga belum diaktifkan.
- POS/APK: hash empat file integrasi tetap identik; tidak ada file POS yang
  diubah.
- Batch berikutnya: `GAP-01.2` menetapkan baseline commit/recovery history dan
  rencana rotasi terikat cutover. Ini memerlukan keputusan scope commit dan
  waktu rotasi agar tidak mencampur perubahan pengguna atau memutus aplikasi
  lain.

## Batch 147 — GAP-01.2 cutoff Git lokal dan matriks deployment SQL

- Waktu: 2026-09-05 WIB.
- Prioritas: membuat batas pelacakan yang pasti antara source awal dan seluruh
  perubahan audit, tanpa mengirim perubahan ke remote secara implisit.
- Kondisi awal: HEAD `677078143b16e8151764ee20738b696f86547cb7`
  adalah satu-satunya commit pada clone shallow; perubahan source, test, SQL,
  dokumentasi, dan pelepasan runtime dari index belum mempunyai cutoff commit.
- Audit file lama: 128 dari 139 file source lama yang hilang dari lokasi asal
  terbukti identik dan telah dipindah ke `docs/_old` atau `sql/_old`. Sebelas
  sisanya adalah satu gambar dokumentasi lama dan sepuluh probe/SQL sementara
  yang sudah tidak ada; penghapusan dicatat oleh Git. Tiga file backup handoff
  APK dipertahankan sebagai fixture audit dan ditolak oleh package policy
  melalui suffix `_bak.php`.
- Implementasi: menambahkan dokumen cutoff dan matriks SQL untuk server utama,
  customer existing, serta customer baru; mengunci titik sebelum perubahan,
  nama tag sesudah perubahan, perintah diff A/M/D/R, daftar managed migration,
  daftar legacy `DO_NOT_RUN`, boundary runtime/secret, dan deliverable panduan
  aplikasi final.
- Git: commit lokal dan annotated tag
  `finance-audit-cutoff-2026-09-05` dibuat setelah validasi staged diff. Push
  sengaja tidak dijalankan karena merupakan operasi eksternal terpisah dan
  menunggu perintah eksplisit pemilik.
- Database: tidak ada SQL baru dan tidak ada mutasi database pada batch ini.
  Server existing memakai migration runner policy `upgrade`; fresh customer
  memakai baseline schema-only lalu policy `clean_install`. SQL legacy,
  `sql/_old`, dan repair historis tidak boleh dijalankan massal.
- Risiko sisa: clone tetap shallow; pemulihan full history, push cutoff, rotasi
  secret lama, off-site encryption, dan cutover customer belum selesai.
- Batch berikutnya: setelah owner memerintahkan push, lanjutkan `GAP-01` pada
  strategi full-history/rotasi terjadwal; pekerjaan aplikasi berikutnya tetap
  mengikuti register gap `_30`.

## Batch 148 — GAP-02 inventaris endpoint Master fail-closed

- Waktu: 2026-09-05 WIB.
- Prioritas: melanjutkan A1 non-mobile tanpa menyentuh POS Mobile/APK, dimulai
  dari ketidakpastian inventaris endpoint Master generik pada P0-01.
- Hasil audit: controller aktif mempunyai 12 endpoint publik selain constructor
  dan 36 entity generik. Probe registry staging menemukan mapping `component`
  masih memakai page code lama `master.component.index` yang tidak tersedia;
  page aktifnya adalah `production.component.master.index`. Konfigurasi ke-37,
  `payment-channel`, sengaja ditolak dari controller generik. Writer store,
  update, toggle, stock mode, generate holiday, dan reorder sudah memakai POST
  serta scoped CSRF; read endpoint memakai izin view yang sesuai.
- Implementasi: memperbaiki mapping Component ke page code aktif serta
  menambahkan smoke DB-free yang mengunci daftar public method,
  kebijakan izin/CSRF setiap endpoint, kesetaraan registry entity-page,
  fail-closed unknown/legacy entity, daftar route, dan urutan route spesifik
  sebelum generic catch. Test dimasukkan ke required quality gate.
- File berubah: `application/controllers/Master.php`,
  `tools/tests/master_endpoint_registry_smoke.php`, quality gate beserta
  contract manifest, roadmap `_30`, dan execution log.
- SQL/database: tidak ada SQL baru dan tidak ada mutasi database.
- Risiko sisa: P0-01 belum ditutup karena mutasi master sensitif belum menulis
  audit trail atomik dan negative role UAT belum dilakukan. Formula versioning,
  baseline role/scope, anti-spam public review, serta MFA/step-up tetap item
  GAP-02 terpisah.
- Batch berikutnya: audit trail perubahan Master memakai
  `aud_transaction_log`, dengan before/after teredaksi dan transaksi atomik;
  tetap tanpa perubahan kontrak POS Mobile/APK.

## Batch 149 — GAP-02 audit trail atomik perubahan Master

- Waktu: 2026-09-05 WIB.
- Prioritas: menutup kekurangan audit trail P0-01 setelah endpoint dan registry
  Master dibuktikan pada Batch 148.
- Implementasi: enam jalur mutasi generik—create, update, toggle aktif, perubahan
  stock mode produk, generate kalender libur, dan reorder—sekarang memulai
  transaksi hanya setelah permission serta scoped CSRF lulus, lalu menulis
  `aud_transaction_log` sebelum commit. Gagal schema, insert, status transaksi,
  audit, atau commit menyebabkan perubahan ditolak/rollback.
- Isi audit: module/action, tabel dan ID entity, actor user, source IP, before,
  after, catatan aksi, dan timestamp. Key bertipe password, token, secret,
  credential, authorization, cookie, atau session disaring rekursif; payload
  bersarang dibatasi kedalamannya.
- Generate holiday mencatat snapshot tahun sebelum/sesudah beserta jumlah
  sumber/diproses. Reorder mencatat urutan ID dan sort order sebelum/sesudah.
- Schema: tabel `aud_transaction_log` dan kolom yang diperlukan sudah terbukti
  ada pada database staging serta baseline clean-install. Tidak ada SQL baru dan
  tidak ada mutasi data staging pada batch ini.
- File berubah: `application/controllers/Master.php`, smoke audit trail dan
  penyesuaian smoke inline, quality gate/contract, roadmap `_30`, dan execution
  log.
- Validasi: PHP lint, 22 contract audit trail, CSRF form 18, inline 15, holiday
  9, endpoint registry 47, quality-gate contract 27, serta quality gate
  `parallel` lulus: required 50/50, development 4/4, release 1/1, dan preflight
  1/1 tanpa temuan.
- Status: P0-01 menjadi `CODE_PASS + STAGING_PASS`; release tetap diblokir
  sampai negative role UAT. Tidak ada file POS Mobile/APK yang disentuh.
- Risiko sisa: alasan operator masih berupa catatan aksi sistem, bukan field
  alasan wajib pada setiap master; perubahan akun/role pegawai mempunyai jalur
  audit domain auth tersendiri yang masih perlu ditinjau pada baseline
  role/scope.
- Batch berikutnya: inventaris dan negative matrix baseline multi-role serta
  scope outlet/divisi pada database staging, tanpa mengubah definisi izin
  bisnis milik owner.

## Batch 150 — GAP-02 scope multi-role web/POS Mobile dan negative matrix

- Waktu: 2026-09-05 WIB.
- Prioritas: menyamakan fail-closed scope antara web dan POS Mobile serta
  membuktikan kondisi multi-role/outlet/terminal pada database staging.
- Temuan: web sudah menolak scope `NONE`/`AMBIGUOUS`, tetapi login POS Mobile
  belum memeriksa scope sebelum menerbitkan token dan request bearer belum
  memvalidasi ulang scope setelah perubahan role.
- Implementasi: login POS Mobile sekarang memvalidasi hasil
  `Auth_model::resolve_division_scope()` sebelum insert token. Setiap request
  bearer dan request mobile berbasis sesi memuat izin/scope aktif; status selain
  `GLOBAL`, `SINGLE` valid, atau superadmin ditolak sebelum identitas request,
  pembaruan `last_seen`, dan operasi bisnis. Respons login/bootstrap bearer
  mengirim konteks division, outlet, dan terminal yang otoritatif ke APK.
- Negative matrix: token tidak diterbitkan untuk `NONE`/`AMBIGUOUS`; token lama
  langsung ditolak bila kombinasi role menjadi ambigu; `SINGLE` tetap diterima;
  scope dan permission hanya dimuat sekali per request; session-backed endpoint
  mengikuti kebijakan yang sama.
- Probe staging read-only: 16 user aktif, 13 multi-role, 3 superadmin, 3 global,
  10 single, 0 `NONE`, 0 `AMBIGUOUS`; dua user bertoken mobile aktif mempunyai
  scope valid. Tidak ada orphan role assignment, duplicate active device key,
  atau terminal aktif dengan outlet invalid.
- Matrix permission: tidak diubah. Hak KASIR/BARISTA dan role lain tetap milik
  owner serta dapat disesuaikan lewat modul role; batch ini hanya memastikan
  script menerapkan union permission dan scope secara aman.
- File berubah: `application/controllers/Pos_mobile.php`, harness recovery POS,
  smoke negative scope baru, probe staging baru, quality gate/contract,
  package preflight fixture line, roadmap `_30`, dan execution log.
- SQL/database: tidak ada SQL baru dan seluruh query staging bersifat `SELECT`.
- Validasi: PHP lint; seluruh 11 smoke POS Mobile/APK; negative scope 20/20;
  auth division scope 42; inactive-role smoke; quality-gate contract; release
  preflight; probe staging; roadmap consistency; dan quality gate `parallel`
  lulus required 51/51, development 4/4, release 1/1, serta preflight 1/1.
- Risiko sisa: baseline hak per jabatan belum boleh di-reset tanpa keputusan
  bisnis owner; halaman simulator/report permission drift, step-up aksi sensitif,
  dan UAT APK/perangkat tetap terbuka.
- Batch berikutnya: simulator akses dan report permission drift read-only agar
  owner dapat meninjau dampak role/user tanpa mengubah matrix izin.

## Batch 151 — GAP-02 simulator akses dan report selisih permission

- Waktu: 2026-09-05, selesai pemeriksaan utama 20:13 WIB.
- Prioritas: P0-04/AUD-A1-RBAC-01. Menyediakan alat pemeriksaan dampak role
  sebelum owner mengubah izin bisnis; bukan mereset matrix role atau membangun
  License Hub/Control Center.
- Ringkasan arah dan implementasi: mengikuti pola fixer tunggal yang diminta
  owner. Resolver permission/scope dipakai bersama oleh jalur aktif dan
  simulator; mempertahankan signature publik lama untuk kompatibilitas login
  web/mobile dan turunannya. Simulasi tidak menyimpan role atau sesi.
- Perubahan utama:
  - Manajemen User/detail user mendapat tombol **Simulasi Akses**.
  - Empat tab: Akses Efektif, Perbandingan, Role & Scope, dan Baseline Paket;
    pencarian/filter modul dan paginasi 25 baris; scope sebelum/sesudah jelas.
  - Gabungan role aktif, GRANT lalu REVOKE, superadmin, akun nonaktif, scope
    kosong/konflik, serta menu memakai resolver yang sama dengan aplikasi.
  - Baseline paket berupa JSON berversi. Default belum disetujui; perbandingan
    tidak dianggap lulus atau gagal sebelum owner menetapkan acuan izin.
    Role di luar baseline ditandai belum dinilai, bukan otomatis salah.
  - Endpoint GET-only, no-store, membutuhkan izin lihat user dan permission
    user; input dibatasi dan output di-escape. Tidak ada impersonasi.
- File berubah:
  - `application/models/Auth_model.php`, `Access_audit_model.php` (baru).
  - `application/controllers/Users.php`, `application/config/routes.php`.
  - `application/config/rbac_permission_baseline.json` (baru).
  - `application/views/users/access_audit.php` (baru), `index.php`, `detail.php`.
  - `tools/tests/access_simulator_smoke.php` (baru), `finance_quality_gate.php`,
    `finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- SQL/database: tidak ada SQL/schema baru. Probe staging menggunakan transaksi
  read-only dan memverifikasi seluruh query laporan adalah SELECT. Hak akses,
  role, data transaksi, dan credential tidak diubah.
- Validasi:
  - PHP lint seluruh 10 file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke simulator 43 pemeriksaan lulus; memakai model/query builder CI asli
    dengan fixture SQLite in-memory yang dikunci read-only setelah setup.
  - Probe staging `CI_ENV=staging ...access_simulator_smoke.php --staging`:
    46 pemeriksaan, 22 akun aktif/nonaktif, 200 halaman aktif, 0 database write.
    Preview role aktual identik dengan resolver aktif; preview tanpa role
    selalu gagal tertutup, termasuk untuk user superadmin yang sebenarnya.
  - Auth division scope 42, inactive-role smoke, dan login throttle 74 lulus.
  - Quality gate `parallel`: required 52/52, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static tier tidak dijalankan oleh
    profil ini; UAT perangkat bukan bagian dari klaim lulus.
  - Roadmap consistency 22 dan roadmap dashboard 30 lulus. Render Chrome
    fixture desktop ditinjau; struktur tab disesuaikan agar tetap horizontal
    dengan CSS aplikasi. Ini bukan pengganti UAT login browser nyata.
  - Composer tidak berubah, sehingga composer validate tidak diperlukan.
- Hasil review fixer tunggal: layak untuk batch ini. Regresi awal signature
  subclass pada smoke throttle diperbaiki dengan mempertahankan signature lama,
  bukan melemahkan test. Filter modul tidak lagi menyembunyikan hasil ketika
  pindah ke tab baseline. Batas data pengguna versus simulasi tetap eksplisit.
- Risiko sisa: isi baseline izin per jabatan menunggu keputusan owner;
  simulator bukan bukti akses tiap dokumen/outlet/terminal dan tidak menguji
  transaksi. UAT role/APK, anti-spam public review, serta step-up/MFA masih
  terbuka. A1 secara keseluruhan **belum selesai**.
- Catatan roadmap: penundaan edit mobile DEFER-02 lama dicatat telah dicabut
  owner; repair mismatch/historis DEFER-01/03 tetap tidak dikerjakan otomatis.
- Batch berikutnya: audit dan penguatan anti-spam endpoint public review,
  dibatasi pada script/validasi tanpa mengubah izin bisnis atau data mismatch.
- Penyerahan: commit lokal terpisah untuk pelacakan cutoff; tidak push.
  Ringkasan penyelesaian dikirim ke Telegram Namua setelah commit.

## Batch 152 — GAP-02 anti-spam formulir ulasan publik

- Waktu/tanggal: 2026-09-05, validasi akhir 20:51 WIB.
- Prioritas: P2-06/AUD-A1-REVIEW-01, tindak lanjut Batch 151. Scope terbatas
  formulir QR nota dan QR area; tidak menyentuh APK, matrix izin, atau data lama.
- Diskusi/arah: fixer tunggal sesuai pola terbaru owner. Temuan utama adalah
  kiriman station tanpa limiter, input array yang memicu warning, respons/error
  yang membeberkan profil member, dan member baru yang dapat tertinggal ketika
  insert ulasan gagal. Solusi memakai penyimpanan runtime lokal terkunci,
  validasi sebelum writer, minimisasi informasi publik, dan transaksi atomik.
- File berubah:
  - `application/controllers/Customer_reviews.php`.
  - `application/models/Pos_customer_review_model.php`.
  - `application/libraries/CustomerReviewGuard.php` dan `CustomerReviewInput.php`
    (baru).
  - `application/views/pos/customer_review_form.php` dan
    `customer_review_station_form.php`.
  - `tools/tests/public_customer_review_smoke.php` (baru),
    `finance_quality_gate.php`, `finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Percobaan POST dibatasi 60/IP/10 menit dan 12/sesi browser/10 menit.
    IP menggunakan resolver CI, tidak membaca header forwarding sembarang.
  - Form bertanda tangan terikat sesi/QR, minimal 2 detik, kedaluwarsa 1 jam,
    honeypot, serta reservasi sekali pakai yang aman lintas PHP worker.
  - Cooldown 60 detik per sesi dan nomor/nota; deduplikasi 10 menit. Gagal simpan
    boleh dicoba ulang setelah cooldown biasa, bukan menahan selama 10 menit.
  - Rating, scalar input, UTF-8, nomor telepon, ukuran, serta consent diperiksa
    sebelum pendaftaran member. Persetujuan nomor berlaku juga untuk member lama.
  - Nama/nomor member tersimpan dan detail error tidak ditampilkan ke publik;
    halaman sukses sama untuk member baru/lama. Informasi member diarahkan ke
    kasir. Input telepon tetap bukan verifikasi kepemilikan nomor/OTP.
  - Pembuatan member dan ulasan station atomik; receipt tetap sekali pakai.
    Data IP/user-agent baru tidak ditambahkan ke tabel ulasan. Data historis
    tidak diubah. Header no-store/no-referrer/noindex dan frame guard diterapkan.
  - Diagnostik tersampling dibatasi 200 event/24 jam (dipangkas pada akses
    berikutnya); menggunakan HMAC IP dan ID internal, tanpa data isi ulasan,
    nomor telepon, session ID, token formulir, atau IP mentah.
- SQL/runtime: **tidak ada SQL baru**, tidak ada migration atau perubahan
  credential/database config. Direktori baru
  `application/cache/customer-review-guard` disiapkan `www:www` mode 0700;
  file state dibuat PHP-FPM mode 0600 dan dikecualikan Git/package. Tidak
  mengubah permission direktori cache lain, nginx, backup, upload, atau log lama.
- Validasi:
  - `php -l` sembilan file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke publik 43 pemeriksaan lulus: replay, honeypot, batas IP/sesi, expiry,
    cooldown/duplikasi, error storage, validasi, privasi, dan controller guard.
  - Empat proses PHP paralel memakai form identik: tepat satu diterima.
  - Model review/query builder CI asli pada SQLite disposable: receipt sekali
    pakai lulus; member writer fixture memakai nested transaction seperti POS;
    kegagalan insert ulasan membatalkan member baru. Tidak memakai DB staging
    untuk membuat member/ulasan uji.
  - HTTP staging via nginx/PHP-FPM: GET QR area 200 dengan signed form dan
    header privasi; empat POST negatif menghasilkan 403/403/422/403 tanpa
    warning PHP, termasuk route langsung controller. Jumlah review/member
    sebelum/sesudah tetap. Kedua tabel staging memakai InnoDB.
  - HTTP file state menghasilkan status 200 dengan body **0 byte** karena
    prefix PHP exit; tidak mengklaim URL tersebut diblokir nginx.
  - Quality gate `parallel`: required 53/53, development 4/4, release 1/1,
    preflight 1/1 lulus. Roadmap consistency 22 lulus. Runtime/security/static
    tier serta UAT QR fisik bukan bagian dari klaim lulus profil ini.
  - Composer tidak berubah; composer validate tidak diperlukan.
- Review akhir fixer tunggal: batch formulir publik layak. Review menemukan
  empat writer admin moderasi/pengaturan QR belum memiliki CSRF terarah; tidak
  memperluas patch ke sana pada batch ini. P2-06 tetap IN_PROGRESS/STAGING_PASS,
  bukan mengklaim A1 selesai.
- Risiko sisa: UAT Wi-Fi/proxy/QR/perangkat; limiter bersifat single-server dan
  harus diganti backend bersama untuk multi-node. Nomor WhatsApp belum OTP.
  CAPTCHA adaptif bukan fitur batch ini. Diagnostik bounded bukan audit permanen;
  relasi member/review dan catatan pendaftaran adalah jejak bisnis yang tersimpan.
- Batch berikutnya: guard CSRF untuk visibility, pengaturan ulasan, simpan QR
  area, serta aktif/nonaktif QR; perbaiki juga teks konfirmasi moderasi yang
  menyebut tindakan terbalik. Setelah itu kembali ke step-up/UAT A1.
- Penyerahan: commit lokal sesudah `b87db84`, tanpa push; ringkasan hasil dikirim
  ke Telegram Namua setelah commit. Roadmap `_30` memuat petunjuk runtime
  server utama/customer sehingga pemasangan tidak hanya bergantung pada SQL.

## Batch 153 — CSRF moderasi ulasan dan pengaturan QR admin

- Waktu/tanggal: 2026-09-05, validasi akhir 21:10 WIB.
- Prioritas: P2-06/AUD-A1-REVIEW-01, menutup empat writer admin yang ditemukan
  pada review Batch 152. Scope tetap modul Ulasan Pelanggan.
- Diskusi/arah: fixer tunggal sesuai pola terbaru owner. Writer visibility,
  pengaturan QR struk, simpan QR area, dan aktif/nonaktif QR sebelumnya belum
  memeriksa token CSRF. Tambahkan guard khusus modul tanpa mengubah izin bisnis,
  formulir publik, token transaksi POS, atau helper printer bersama.
- File berubah:
  - `application/controllers/Pos.php`.
  - `application/views/pos/customer_reviews_index.php`.
  - `tools/tests/customer_review_admin_csrf_smoke.php` (baru).
  - `tools/tests/finance_quality_gate.php` dan
    `tools/tests/finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Keempat writer mempertahankan pemeriksaan izin edit terlebih dahulu,
    kemudian wajib POST dan token CSRF sesi/header khusus sebelum membaca
    payload atau menjalankan model writer. Request tidak valid ditolak.
  - Token acak diterbitkan hanya melalui halaman yang diizinkan, tetap stabil
    dalam sesi untuk multi-tab, dan tidak menggunakan token transaksi POS.
    Halaman serta respons guard memakai no-store.
  - JavaScript lokal mengirim token lewat header, bukan URL; request dibatasi
    ke origin yang sama dan tidak mengikuti redirect. Respons gagal/non-JSON
    ditampilkan sebagai pesan, bukan dianggap berhasil.
  - Konfirmasi sembunyikan/tampilkan ulasan diperbaiki agar sesuai tindakan.
    Pesan schema belum tersedia mengarahkan admin memeriksa migrasi versi
    aplikasi, bukan menyuruh menjalankan ulang SQL lama secara sembarang.
  - Setelah update, muat ulang halaman Ulasan Pelanggan yang sudah terbuka
    supaya menerima token. Login ulang bila sesi telah berakhir.
- SQL/runtime: **tidak ada SQL baru**, perubahan schema/data, credential,
  permission/sidebar, atau konfigurasi runtime. Pos_mobile, Pos_model, routes,
  dan helper printer bersama tidak berubah.
- Validasi:
  - `php -l` kelima file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke admin CSRF 179 pemeriksaan lulus: controller asli dengan model/session
    doubles menguji izin, metode, token hilang/salah/malformed, jalur valid,
    fallback registry permission, multi-tab, serta pemisahan token POS.
  - JavaScript hasil render view asli dijalankan di Node dengan DOM/fetch
    doubles: hide/show, pengaturan struk, simpan/toggle QR, penolakan lintas
    origin, respons error/non-JSON, dan mode hanya-baca lulus. Ini bukan UAT
    browser admin login nyata dan tidak membuat data uji di database staging.
  - Regresi CSRF transaksi POS 1691 dan ulasan publik 43 pemeriksaan lulus.
  - Quality gate `parallel`: required 54/54, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static/staging tier dan UAT perangkat
    bukan bagian dari klaim lulus profil ini.
  - HTTP staging: empat POST tanpa sesi login menghasilkan 303; Location login
    dikonfirmasi pada endpoint settings. Tidak mengklaim ini bukti writer admin
    berhasil dengan sesi nyata; tidak mengubah konfigurasi QR/ulasan aktual.
  - Setelah update roadmap, consistency 22 dan dashboard 30 pemeriksaan lulus.
  - Composer tidak berubah; composer validate tidak diperlukan.
- Review akhir fixer tunggal: batch layak. Guard berjalan sebelum payload dan
  writer, izin existing dipertahankan, dan token tidak tercampur dengan modul
  lain. AUD-A1-REVIEW-01 menjadi CODE_PASS; STAGING_PASS merujuk juga bukti
  publik Batch 152. Release tetap BLOCKED sampai acceptance/UAT terpenuhi,
  bukan mengklaim seluruh A1 selesai.
- Risiko sisa: UAT admin login dan QR/perangkat/proxy nyata; batas single-server
  limiter publik tetap berlaku. Baseline izin owner serta step-up tindakan
  sensitif masih terbuka dan tidak diputuskan otomatis pada batch ini.
- Batch berikutnya: telaah approval void/refund/reopen yang sudah ada sebelum
  memperkuat konfirmasi identitas tindakan sensitif secara bertahap; lanjut
  acceptance A1 tanpa mengubah data mismatch historis.
- Penyerahan: commit lokal sesudah `f0b3ce4`, tanpa push; ringkasan penyelesaian
  dikirim ke Telegram Namua setelah commit.

## Batch 154 — CSRF Tutup Periode Keuangan

- Waktu/tanggal: 2026-09-05, validasi akhir 21:25 WIB.
- Prioritas: P0 / AUD-A1-FIN-01 baru, temuan ketika menelaah reopen/approval
  tindakan sensitif setelah Batch 153. Scope hanya draft, close, dan reopen
  periode keuangan web; tidak menyentuh POS Mobile, void/refund, data period,
  atau aturan izin owner.
- Diskusi/arah: fixer tunggal. Tiga writer sudah memakai login dan permission,
  tetapi belum mempunyai CSRF scoped. Aksi close juga menerima `redirect_to`
  dari POST. Pengamanan method/token dan redirect lokal diterapkan dulu;
  atomicity model reopen sengaja dicatat sebagai batch berikutnya, bukan
  digabung menjadi rewrite finance besar.
- File berubah:
  - `application/controllers/Finance_reports.php`.
  - `application/views/finance/period_close_index.php` dan
    `application/views/finance/period_close_detail.php`.
  - `tools/tests/finance_period_close_csrf_smoke.php` (baru),
    `finance_quality_gate.php`, dan `finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Simpan draft, proses close, dan reopen kini mempertahankan permission
    create/edit yang ada, lalu wajib POST serta token CSRF sesi khusus periode
    sebelum payload atau model dibaca. Token tidak menggunakan token POS dan
    halaman/respons diberi no-store.
  - Tombol dan form hanya dirender sesuai izin create/edit yang sudah berlaku;
    token tersembunyi disertakan pada setiap form mutasi resmi. Pemakai view
    tidak lagi melihat tombol yang tidak dapat dipakai.
  - Redirect sesudah close selalu menuju detail periode yang diproses. Nilai
    `redirect_to` dari POST tidak lagi digunakan, sehingga tidak dapat
    mengarahkan pengguna keluar dari aplikasi atau ke dokumen lain.
  - Pesan token kedaluwarsa menyarankan reload halaman Tutup Periode Keuangan.
    Tidak ada perubahan status close/reopen nyata selama batch ini.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging, perubahan
  schema/data, credential, RBAC/sidebar, atau konfigurasi runtime. Composer
  tidak berubah sehingga `composer validate` tidak relevan.
- Validasi:
  - `php -l` untuk enam file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke baru 685 pemeriksaan lulus dengan controller dan form view asli:
    GET/PUT/DELETE, token hilang/salah/array, Ajax/non-Ajax, RBAC negatif,
    token stabil multi-tab, redirect/actor yang dipalsukan, dan form sesuai
    izin/status diuji tanpa database atau period nyata.
  - Kontrak Finance 20, kontrak quality gate 27, roadmap consistency 22, dan
    dashboard roadmap 30 pemeriksaan lulus.
  - Quality gate `parallel`: required 55/55, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static/staging serta UAT browser
    nyata tetap di luar klaim profil ini.
  - HTTP staging tanpa sesi pada tiga endpoint menghasilkan 303 ke login.
    Ini membuktikan login gate, bukan UAT create/edit atau bukti mutasi berhasil;
    tidak ada POST terautentikasi ataupun perubahan period dilakukan.
- Review akhir fixer tunggal: layak untuk patch terarah ini. CSRF diperiksa
  setelah permission dan sebelum payload/model; token scoped tidak mencampuri
  modul lain. `AUD-A1-FIN-01` menjadi CODE_PASS/AUTO_PASS untuk boundary
  controller, tetapi release tetap BLOCKED.
- Risiko sisa: `Finance_report_model::reopen_period()` masih perlu lock dan
  transaksi terarah serta harus membedakan kegagalan write; UAT finance dengan
  akun create/edit/view nyata belum dilakukan. Step-up/reauth untuk void,
  refund, reopen, adjustment, dan reprint masih belum dibangun.
- Batch berikutnya: perbaiki atomicity reopen periode keuangan secara kecil dan
  teruji, lalu kembali ke telaah desain step-up aksi sensitif tanpa mengubah
  baseline izin maupun data historis.
- Penyerahan: commit lokal sesudah `56f1072`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 164 — CSRF dan reauth Transfer Stok Divisi

- Waktu/tanggal: 2026-09-06, implementasi dan validasi awal 06:08 WIB.
- Prioritas: P0 / AUD-A1-STEP-01. Post transfer memanggil writer FIFO untuk
  mengurangi stok sumber, menambah stok tujuan, serta merekam nilai/ledger;
  VOID membalik mutasi tersebut. Jalur Save dengan auto_post sebelumnya
  memungkinkan caller lama mem-post tanpa proof password.
- Diskusi/arah: fixer tunggal. Scope dibatasi pada halaman Transfer Bahan
  Baku Antar Divisi web, controller Purchase, dan kontrak proof; tidak
  mengubah FIFO/model bisnis, data mismatch historis, SQL/schema, role
  matrix, atau POS Mobile/APK.
- File berubah:
  - application/config/routes.php, application/controllers/Purchase.php, dan
    application/libraries/SensitiveActionStepUp.php.
  - application/views/purchase/stock_transfer_index.php.
  - tools/tests/stock_transfer_step_up_smoke.php (baru), manifest dan
    kontrak quality gate.
  - Roadmap induk _30, _28, serta execution log ini.
- Perubahan utama:
  - Save Draft dan Delete Draft wajib POST dengan header CSRF scoped
    X-Stock-Transfer-Csrf.
  - auto_post ditolak sebelum draft disimpan. Tombol Simpan dan Verifikasi
    kini menyimpan DRAFT terlebih dahulu; operator dapat membatalkan pada
    tahap password tanpa terjadi mutasi stok.
  - Endpoint verifikasi baru menerbitkan proof satu-kali 180 detik
    STOCK_TRANSFER_POST atau STOCK_TRANSFER_VOID, terikat user, sesi, dan
    satu dokumen transfer. Writer Post/VOID mengonsumsi proof sebelum
    MaterialFifoManager atau reversal dipanggil; password tidak diteruskan
    ke model.
  - UI meminta konfirmasi dan password masked untuk Post/VOID, menghapus
    nilai input sebelum request proof, serta memakai wrapper CSRF pada semua
    mutasi halaman.
- SQL/runtime: tidak ada SQL baru, migration, schema/data, query tulis
  staging, credential, sidebar, atau kontrak POS Mobile/APK yang berubah.
- Validasi:
  - php -l seluruh file PHP berubah, smoke Transfer Stok baru (18 kontrak),
    smoke Daily Recon (16), Component Batch (21), Adjustment Stok (17),
    dashboard audit (30), roadmap consistency (22), quality-gate contract
    (27), dan git diff --check lulus.
  - Quality gate parallel lulus: required 62/62, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static, query staging, serta
    UAT browser/APK/printer nyata tidak diklaim oleh profil otomatis.
- Review akhir fixer tunggal: layak untuk shared web flow. Request lama tanpa
  header/proof dan shortcut auto_post ditolak fail-closed. Permission transfer
  existing tidak dinaikkan atau digabung.
- Risiko sisa: stock opening, mutasi inventory/produksi lain, API/APK, MFA,
  baseline role nyata, serta UAT browser/perangkat per role masih terbuka.
- Batch berikutnya: petakan stock opening sebagai mutasi bernilai tinggi
  berikutnya; kemudian lanjutkan action inventory/production yang masih
  langsung memanggil writer.
- Penyerahan: commit lokal setelah semua gate lulus, tanpa push; ringkasan
  dikirim ke Telegram Namua setelah commit.

## Batch 166 — Reauth Stock Opening manual dan VOID

- Waktu/tanggal: 2026-09-06, validasi akhir 06:47 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Simpan opening manual dan VOID snapshot
  dapat mengubah saldo, lot awal, movement, nilai, serta histori stok.
- Diskusi/arah: fixer tunggal. Batch dibatasi pada opening manual dan VOID;
  import Excel tetap CSRF-only karena satu upload dapat memproses banyak baris
  dan divisi. Tidak ada repair data, SQL/schema, POS Mobile/APK, atau perubahan
  aturan FIFO/model.
- File berubah:
  - `application/config/routes.php`, `application/controllers/Purchase.php`,
    dan `application/libraries/SensitiveActionStepUp.php`.
  - `application/views/purchase/stock_opening_index.php` dan
    `application/views/purchase/stock_opening_division_index.php`.
  - `tools/tests/stock_opening_step_up_smoke.php` (baru), manifest, dan
    kontrak quality gate; roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - Route verifikasi baru menerima password hanya melalui endpoint CSRF scoped,
    menerbitkan proof acak satu-kali 180 detik, dan limiter gagal tetap milik
    `SensitiveActionStepUp`.
  - Simpan manual memakai `STOCK_OPENING_POST`: scope Gudang memakai target
    stabil, sedangkan Divisi memakai ID divisi. Karena opening manual belum
    mempunyai dokumen draft, proof tidak diklaim terikat nomor dokumen.
  - VOID memakai `STOCK_OPENING_VOID` terikat ID snapshot yang benar; snapshot
    dan izin scope diverifikasi sebelum proof diterbitkan atau writer rollback
    dipanggil. Password tidak pernah diteruskan ke model/writer.
  - UI Gudang dan Divisi menampilkan password masked setelah konfirmasi;
    browser mengirim password hanya ke verifier, lalu mengirim proof ke writer.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data mismatch, credential, sidebar, atau kontrak POS Mobile/APK.
- Validasi:
  - `php -l` seluruh file PHP berubah, `git diff --check`, smoke CSRF opening,
    smoke reauth opening baru, dan quality-gate contract dijalankan sebelum
    full gate.
  - Quality gate `parallel` wajib lulus setelah manifest bertambah; UAT browser
    nyata dan import Excel massal reauth belum diklaim oleh batch ini.
- Review akhir fixer tunggal: layak untuk scope manual/VOID; proof tidak dapat
  dipakai ulang, berbeda user/aksi/target, atau dipakai untuk mengirim password
  ke writer.
- Risiko sisa: reauth import opening massal, mutasi inventory/produksi lain,
  API/APK, MFA, baseline role nyata, serta UAT browser/perangkat per role.
- Batch berikutnya: desain batch-verification untuk import opening atau lanjut
  mutasi inventory bernilai tinggi setelah caller dan target otoritatif jelas.
- Penyerahan: commit lokal setelah gate lulus, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 168 — Revision dan audit editor massal Resep Produk

- Waktu/tanggal: 2026-09-06, validasi akhir 07:14 WIB.
- Prioritas: P0 / `AUD-A1-SEC-02`. Editor massal mengganti seluruh line resep,
  sehingga dua tab browser dapat menyebabkan lost update dan sebelumnya tidak
  meninggalkan before/after audit khusus.
- Diskusi/arah: fixer tunggal. Scope hanya `product_recipe_bulk_save`; writer
  line individual, Formula Component, Extra, dan Bundle sengaja tidak disentuh
  agar perubahan kecil dan mudah diverifikasi. Tidak ada SQL/schema, data HPP,
  POS Mobile/APK, atau perubahan matriks role.
- File berubah:
  - `application/controllers/Master_relation.php`.
  - `application/views/master/relation_product_recipe_edit.php`.
  - Regression fixture recipe, smoke revision/audit baru, manifest, kontrak
    quality gate, roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - Halaman editor membawa hash revision dari line resep saat dibuka.
  - Writer memvalidasi revision, mengunci produk serta line resep dengan
    `FOR UPDATE`, dan rollback dengan pesan muat ulang jika data telah berubah.
  - Replace line dan insert audit `REPLACE_PRODUCT_RECIPE` before/after berada
    dalam satu transaksi. Jika tabel/kolom audit, lock, write audit, atau commit
    gagal, perubahan resep dibatalkan.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data HPP/mismatch, credential, sidebar, atau kontrak POS Mobile/APK.
- Validasi:
  - `php -l`, regression guard resep (138 check), smoke revision/audit baru,
    matrix direct-URL A1 required, quality-gate contract, dan `git diff --check`
    lulus sebelum full gate.
  - Quality gate `parallel` wajib lulus setelah manifest bertambah. UAT browser
    dua-tab nyata masih perlu dilakukan terpisah.
- Review akhir fixer tunggal: layak untuk editor massal. Snapshot lama tidak
  dapat menimpa perubahan baru; perubahan dan audit berhasil atau rollback
  bersama-sama.
- Risiko sisa: writer resep individual, Formula Component, Extra, Bundle,
  API/APK, MFA, baseline role nyata, serta UAT browser/perangkat per role.
- Batch berikutnya: pilih writer Formula Component atau writer Resep individual
  sebagai batch A1 kecil berikutnya.
- Penyerahan: commit lokal setelah gate lulus, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 167 — Reauth import Excel Stock Opening satu divisi

- Waktu/tanggal: 2026-09-06, validasi akhir 06:52 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Import Excel dapat memanggil writer
  opening berulang sehingga mengubah snapshot, lot, saldo, nilai, dan histori.
- Diskusi/arah: import UI/template adalah per divisi, tetapi parser lama masih
  menerima kode divisi lain dari file. Batch ini menjadikan kontrak eksplisit:
  satu file untuk satu divisi aktif yang dipilih. Tidak ada SQL/schema, repair
  data, FIFO/model, POS Mobile/APK, atau perubahan matriks role.
- File berubah:
  - `application/controllers/Purchase.php` dan
    `application/libraries/SensitiveActionStepUp.php`.
  - Kedua view Stock Opening Gudang/Divisi.
  - `tools/tests/stock_opening_import_step_up_smoke.php` (baru), manifest,
    kontrak quality gate, roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - `STOCK_OPENING_IMPORT` menerbitkan proof one-use 180 detik setelah password
    dan izin create Divisi diperiksa; target wajib divisi aktif yang dipilih.
  - Endpoint multipart mengonsumsi proof sebelum parsing upload atau memanggil
    writer. Password hanya menuju verifier, bukan writer/model.
  - Baris Excel yang mengarah ke divisi berbeda dicatat sebagai gagal dan tidak
    diposting. UI menjelaskan batas satu file satu divisi serta meminta password
    sebelum form multipart dikirim.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data mismatch, credential, sidebar, atau kontrak POS Mobile/APK.
- Validasi:
  - Lint PHP, parse JavaScript browser, smoke CSRF/manual/VOID/import opening,
    quality-gate contract, dan `git diff --check` lulus sebelum full gate.
  - Quality gate `parallel` wajib lulus setelah manifest bertambah. UAT browser
    nyata tetap belum dijalankan pada batch ini.
- Review akhir fixer tunggal: layak; satu proof tidak dapat dipakai ulang atau
  untuk divisi selain target. Perubahan perilaku sengaja terbatas pada penolakan
  baris lintas divisi yang tidak sesuai halaman/template import Divisi.
- Risiko sisa: mutasi inventory/produksi lain, API/APK, MFA, baseline role
  nyata, serta UAT browser/perangkat per role.
- Batch berikutnya: petakan mutasi inventory bernilai tinggi berikutnya;
  pertahankan batch kecil dan target proof yang otoritatif.
- Penyerahan: commit lokal setelah gate lulus, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 155 — Reopen periode keuangan atomik

- Waktu/tanggal: 2026-09-05, validasi akhir 21:35 WIB.
- Prioritas: P0 / AUD-A1-FIN-01. Menyelesaikan risiko model yang ditemukan dan
  dicatat pada Batch 154: reopen dapat membaca lalu menulis tanpa row lock atau
  transaksi, dan kegagalan write dapat terbaca sukses.
- Diskusi/arah: fixer tunggal. Scope dibatasi satu method
  `Finance_report_model::reopen_period()`, kontrak Finance, dan smoke model.
  Tidak mengubah aturan siapa yang boleh reopen, data period lama, atau desain
  step-up void/refund/reopen yang memerlukan batch tersendiri.
- File berubah:
  - `application/models/Finance_report_model.php`.
  - `tools/tests/finance_period_close_reopen_atomic_smoke.php` (baru),
    `a4_finance_contract_smoke.php`, `finance_quality_gate.php`, dan
    `finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Reopen sekarang memulai transaksi, mengunci row periode melalui
    `SELECT ... FOR UPDATE`, dan hanya menerima status `CLOSED` setelah lock.
  - Update mengulang precondition `id` dan `status=CLOSED`, wajib tepat satu
    row berubah dan transaksi sehat. Gagal lock, row hilang/status berubah,
    writer, atau commit selalu rollback dan mengembalikan error.
  - Request kedua yang menunggu lock akan melihat status `REOPENED` setelah
    request pertama commit, lalu ditolak tanpa mengganti pelaku/waktu reopen.
    `updated_at` kini konsisten dengan `reopened_at`.
- SQL/runtime: **tidak ada SQL baru**, perubahan schema/data, query tulis
  staging, credential, permission/sidebar, atau POS Mobile. Schema kanonis
  sudah InnoDB sehingga mendukung row lock; tidak ada migration yang diperlukan.
- Validasi:
  - `php -l` lima file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke model 55 pemeriksaan lulus: input/foundation/begin invalid, lock gagal,
    row/status invalid, writer/affected-row/transaksi/commit gagal, jalur sukses,
    pelestarian audit actor pertama, dan actor kosong.
  - Smoke CSRF periode 685 dan kontrak Finance 21 lulus.
  - Quality gate `parallel`: required 56/56, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static/staging dan UAT browser nyata
    tetap di luar klaim profil ini.
  - Tidak ada endpoint terautentikasi maupun mutasi periode staging dijalankan.
    Test model memakai transaction double; row lock nyata perlu tetap dibuktikan
    lewat UAT finance pada kandidat release.
- Review akhir fixer tunggal: layak. Status controller/form Batch 154 tidak
  berubah; Batch 155 hanya menyelesaikan integritas writer reopen. AUD-A1-FIN-01
  tetap CODE_PASS/AUTO_PASS dan release tetap BLOCKED oleh UAT/step-up A1.
- Risiko sisa: izin baseline owner, UAT finance role nyata, serta reauth/approval
  tindakan sensitif void, refund, reopen, adjustment, dan reprint belum ada.
- Batch berikutnya: telaah titik entry web/APK untuk void/refund/reopen dan
  desain step-up minimal yang konsisten tanpa mengubah matrix izin existing.
- Penyerahan: commit lokal sesudah `f4456f7`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 156 — Reauth satu-kali Void dan Refund POS web

- Waktu/tanggal: 2026-09-05, validasi akhir 21:46 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Tindakan Void dan Refund mengubah stok,
  lot, kas, dan jejak audit, tetapi sebelumnya masih dapat dilakukan hanya dari
  sesi login/RBAC yang sudah terbuka pada perangkat kasir.
- Diskusi/arah: fixer tunggal. Reauth harus menambah konfirmasi identitas,
  bukan role baru atau perubahan matriks izin. Password tidak boleh diteruskan
  ke writer finansial, disimpan pada dokumen, atau dipakai sebagai token jangka
  panjang. Setelah pemeriksaan awal, ditemukan jalur Refund di halaman Pesanan
  Terbayar selain Void di Kasir; keduanya ditutup dalam batch yang sama agar
  writer baru tidak membuat UI Refund web gagal.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php` (baru).
  - `application/controllers/Pos.php`, `application/config/routes.php`.
  - `application/views/pos/cashier_index.php` dan
    `application/views/pos/order_paid_index.php`.
  - `tools/tests/pos_reversal_step_up_smoke.php` (baru),
    `pos_transaction_csrf_smoke.php`, `finance_quality_gate.php`, dan
    `finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Endpoint POST scoped `pos/orders/reversal-step-up/verify` memakai CSRF
    transaksi POS dan izin `edit` yang sama dengan action asal. Ia memverifikasi
    password user aktif lalu mengeluarkan proof acak 64-hex maksimal 180 detik.
  - Sesi menyimpan **hash** proof saja. Proof terikat ke user sesi, action
    `VOID`/`REFUND`, dan order yang tepat; hanya bisa dikonsumsi sekali sebelum
    model writer dijalankan. Payload model dibersihkan dari proof dan tidak
    pernah menerima password.
  - Lima password salah dalam jendela 10 menit mengunci verifikasi ulang sesi
    selama 10 menit. State gagal tidak menyimpan password maupun proof mentah.
  - Modal Void Kasir serta modal Refund Pesanan Terbayar meminta password
    masked. Field segera dikosongkan sebelum request proof; UI hanya meneruskan
    proof satu-kali ke writer. File backup APK dan endpoint `Pos_mobile.php`
    tidak diubah.
- SQL/runtime: **tidak ada SQL baru**, migration, perubahan schema/data,
  credential, permission/sidebar, ataupun query tulis staging. Composer tidak
  berubah sehingga `composer validate` tidak relevan.
- Validasi:
  - `php -l` seluruh sembilan file PHP aplikasi/test yang berubah/baru lulus;
    `git diff --check` lulus.
  - Smoke service baru 42 pemeriksaan lulus: input malformed tanpa query,
    password salah/limiter, hash-only session, binding user/action/order,
    expiry, one-use/replay, route, writer, dan dua UI web.
  - Regresi CSRF transaksi POS 1.692 pemeriksaan lulus, termasuk jalur writer
    Void/Refund valid dengan proof dan seluruh penolakan CSRF sebelumnya.
  - Quality gate `parallel`: required 57/57, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static/staging tier serta UAT browser
    nyata tetap bukan klaim profil ini. Kontrak quality gate 27, konsistensi
    roadmap 22, dan dashboard audit 30 pemeriksaan juga lulus.
- Review akhir fixer tunggal: layak untuk scope web POS. Kontrak APK/mobile
  tidak diam-diam dipalsukan sebagai selesai; tidak ada perubahan pada backup
  APK. Password hanya dikirim ke endpoint verifikasi dan proof habis dipakai
  bahkan jika writer berikutnya menolak aturan bisnis, sehingga operator perlu
  verifikasi ulang setelah memperbaiki input—trade-off sengaja untuk mencegah
  replay.
- Risiko sisa: step-up Reopen periode, adjustment, reprint, API/APK, dan MFA
  belum ada; UAT role kasir/finance nyata serta APK/device/printer masih wajib.
  Limiter masih per sesi/single-server dan bukan pengganti throttling login.
- Batch berikutnya: perluas `AUD-A1-STEP-01` ke satu action web sensitif lain
  (prioritas Reopen periode) dengan endpoint/UI/negative test terpisah; kontrak
  APK ditata sebagai batch sendiri agar tidak mengganggu aplikasi mobile.
- Penyerahan: commit lokal sesudah `91e14ec`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 157 — Reauth Reopen Periode Keuangan web

- Waktu/tanggal: 2026-09-05, validasi akhir 21:52 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01` dan `AUD-A1-FIN-01`. Reopen periode yang
  sudah ditutup memengaruhi audit/snapshot finance sehingga tidak cukup
  mengandalkan sesi browser yang dibiarkan terbuka.
- Diskusi/arah: fixer tunggal. Form finance adalah POST server-side, bukan
  kontrak JSON POS; batch ini memakai service proof yang sama tetapi proof
  diterbitkan dan langsung dikonsumsi di controller setelah CSRF+RBAC lulus.
  Dengan itu password tidak pernah menjadi data model, flash message, atau
  payload periode. POS Mobile/APK tidak disentuh.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php`.
  - `application/controllers/Finance_reports.php`.
  - `application/views/finance/period_close_detail.php`.
  - `tools/tests/finance_period_close_csrf_smoke.php` dan
    `tools/tests/pos_reversal_step_up_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Hanya form Reopen pada periode berstatus `CLOSED` kini memiliki field
    password masked, required, dan `autocomplete=current-password`; form draft
    dan close biasa tidak berubah.
  - Setelah guard permission edit dan CSRF form yang sudah ada, controller
    memverifikasi password aktif untuk action `PERIOD_REOPEN`, menerbitkan dan
    langsung mengonsumsi proof yang terikat user/action/id periode sebelum
    `Finance_report_model::reopen_period()` dipanggil.
  - Password salah, proof gagal, atau request tanpa password berhenti dengan
    redirect lokal+flash error; tidak memanggil writer. Limiter sesi 5 gagal/10
    menit dan proof hash-only 180 detik berasal dari service Batch 156.
- SQL/runtime: **tidak ada SQL baru**, migration, perubahan schema/data,
  credential, permission/sidebar, atau query tulis staging. Composer tidak
  berubah sehingga `composer validate` tidak relevan.
- Validasi:
  - `php -l` lima file aplikasi/test berubah lulus; `git diff --check` lulus.
  - Smoke step-up 43 pemeriksaan lulus, termasuk `PERIOD_REOPEN` proof satu
    kali. Smoke finance CSRF 707 pemeriksaan lulus: RBAC/verb/token, password
    hilang, issue/consume gagal, writer/model tidak terjangkau, form DOM nyata,
    dan pelestarian actor/redirect.
  - Regresi POS CSRF 1.692, reopen atomik 55, dan kontrak finance 21 lulus.
  - Quality gate `parallel`: required 57/57, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static/staging tier dan UAT browser
    nyata tetap bukan klaim profil ini.
- Review akhir fixer tunggal: layak dalam scope. Reopen tetap memakai izin edit
  yang ada dan transaksi/row lock Batch 155; reauth hanya menambah bukti
  identitas sebelum writer. Tidak ada password diteruskan ke model atau POS
  Mobile yang berubah.
- Risiko sisa: step-up adjustment/reprint, API/APK, dan MFA belum ada. UAT
  finance dengan akun edit nyata, APK/device/printer, serta baseline izin owner
  masih wajib sebelum menutup A1.
- Batch berikutnya: pilih satu writer adjustment atau reprint setelah memetakan
  seluruh entry web-nya; kontrak APK tetap batch terpisah agar tidak memutus
  aplikasi mobile.
- Penyerahan: commit lokal sesudah `abc3d9b`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 158 — Reauth Cetak Ulang Order Kasir web

- Waktu/tanggal: 2026-09-05, validasi akhir 21:59 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Cetak ulang membuat perintah ke printer
  berdasarkan order yang dipilih. Sebelumnya endpoint web hanya memakai izin
  view dan dapat menyiapkan target printer tanpa CSRF atau konfirmasi operator
  yang masih berada di depan kasir.
- Diskusi/arah: fixer tunggal. Reprint bukan mutasi stok/kas, sehingga matrix
  izin `view` yang berlaku tidak dinaikkan menjadi role baru. Pengamanan yang
  ditambahkan adalah CSRF transaksi dan reauth satu-kali khusus action
  `ORDER_REPRINT`; endpoint maupun kontrak `Pos_mobile.php` tidak diubah.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php`.
  - `application/config/routes.php`, `application/controllers/Pos.php`.
  - `application/views/pos/cashier_index.php`.
  - `tools/tests/pos_reversal_step_up_smoke.php` dan
    `tools/tests/pos_transaction_csrf_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Jalur web `pos/orders/reprint-step-up/verify` memakai permission view yang
    sama, POST+CSRF transaksi, password user aktif, dan proof hash satu-kali.
  - Endpoint pembuat target printer sekarang juga wajib POST+CSRF dan mengonsumsi
    proof yang tepat untuk user, order, dan action `ORDER_REPRINT` sebelum
    memanggil model. Proof tidak diteruskan ke model; password tidak pernah
    dikirim ke endpoint target printer.
  - Modal Cetak Ulang Kasir memiliki password masked; nilainya dikosongkan
    sebelum request proof. Bukti yang valid baru dipakai untuk menyiapkan target
    dan meneruskan ke Local Printer Agent seperti alur sebelumnya.
- SQL/runtime: **tidak ada SQL baru**, migration, perubahan schema/data,
  credential, permission/sidebar, printer configuration, atau query tulis
  staging. Composer tidak berubah sehingga `composer validate` tidak relevan.
- Validasi:
  - `php -l` enam file aplikasi/test berubah lulus; `git diff --check` lulus.
  - Smoke step-up 50 pemeriksaan lulus: action Reprint, route, endpoint,
    writer, UI, one-use proof, expiry, limiter, dan backup APK tetap terjaga.
  - Regresi CSRF POS 1.770 pemeriksaan lulus: endpoint reprint teruji untuk
    GET/token salah/benar, permission, payload, proof, dan writer target
    printer. Finance CSRF 707 serta reopen atomik 55 juga lulus.
  - Quality gate `parallel`: required 57/57, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static/staging tier dan UAT browser,
    printer fisik, serta APK nyata tetap bukan klaim profil ini.
- Review akhir fixer tunggal: layak untuk reprint web. Tidak mengubah akses
  role yang sudah diputuskan, model printer, backup APK, atau endpoint mobile.
  Reprint lama melalui script web yang belum diperbarui akan ditolak 403/428,
  sesuai fail-closed; hanya caller resmi Kasir yang telah diperbarui yang dapat
  menerbitkan proof dan menyiapkan target.
- Risiko sisa: step-up adjustment, API/APK, MFA, serta UAT finance/kasir dan
  printer fisik masih terbuka. Limiter per sesi bukan pengganti throttling
  login atau audit attempt printer.
- Batch berikutnya: petakan entry writer adjustment yang benar-benar sensitif,
  lalu tambah reauth secara sempit tanpa menyentuh repair mismatch historis.
- Penyerahan: commit lokal sesudah `cdd5270`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 159 — Reauth Posting Adjustment Base/Prepare web

- Waktu/tanggal: 2026-09-05, validasi akhir 22:12 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Posting draft Adjustment Base/Prepare
  menulis mutasi spoil, waste, plus, minus, stok, lot, dan nilai ke ledger
  component. Entry web ini sebelumnya hanya memakai izin `edit` tanpa CSRF
  endpoint maupun verifikasi ulang operator.
- Diskusi/arah: fixer tunggal. Scope dibatasi pada **posting** dokumen draft
  reguler agar data mismatch historis tidak berubah. Simpan draft, Void
  adjustment, Daily Recon, adjustment gudang/divisi, API, dan APK tidak
  disentuh diam-diam.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php`.
  - `application/config/routes.php`, `application/controllers/Production.php`.
  - `application/views/production/component_adjustment_index.php`.
  - `tools/tests/pos_reversal_step_up_smoke.php`, test baru
    `tools/tests/component_adjustment_step_up_smoke.php`, dan manifest/kontrak
    quality gate.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Route web baru `production/component-adjustments/step-up/verify` memeriksa
    izin `edit`, POST, CSRF header scoped, dan password user aktif; ia memberi
    proof hash satu-kali 180 detik dengan action
    `COMPONENT_ADJUSTMENT_POST` yang terikat ke dokumen adjustment.
  - Writer `component_adjustment_post` wajib mengonsumsi proof setelah RBAC dan
    CSRF, sebelum memanggil jalur `ComponentStockWriter`. Proof dibuang dari
    payload; password tidak pernah mencapai model atau writer.
  - Modal Post Adjustment web meminta password masked, mengosongkan field
    sebelum proof diminta, dan mencegah pembatalan/klik ganda ketika request
    verifikasi masih berjalan.
- SQL/runtime: **tidak ada SQL baru**, migration, schema/data, query tulis
  staging, credential, permission/sidebar, atau kontrak POS Mobile/APK yang
  berubah. Composer tidak berubah sehingga `composer validate` tidak relevan.
- Validasi:
  - `php -l` seluruh file PHP berubah lulus dan `git diff --check` lulus.
  - `component_adjustment_step_up_smoke.php` lulus 12 kontrak endpoint,
    CSRF, proof, writer, dan UI. `pos_reversal_step_up_smoke.php` lulus 51
    pemeriksaan termasuk action baru pada service nyata.
  - Quality gate `parallel` lulus: required 58/58, development 4/4, release
    1/1, preflight 1/1. Runtime/security/static/staging dan UAT browser nyata
    tidak diklaim oleh profil ini.
- Review akhir fixer tunggal: layak untuk scope Posting Adjustment Base/Prepare
  web. Perubahan fail-closed untuk script web lama tanpa header/proof, dan tidak
  mengubah role matrix atau memperbaiki data mismatch secara otomatis.
- Risiko sisa: Void adjustment dan action mutasi inventory lain, API/APK, MFA,
  serta UAT akun produksi/role nyata masih terbuka. Limiter reauth per sesi
  bukan pengganti throttling login.
- Batch berikutnya: pilih satu writer mutasi adjustment berikutnya berdasarkan
  dampak bisnis dan entry resmi, dengan scope sempit serta tanpa repair data.
- Penyerahan: commit lokal sesudah `cc421af`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 160 — Reauth VOID Adjustment Base/Prepare web

- Waktu/tanggal: 2026-09-06, validasi akhir 04:56 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. VOID Adjustment membalik issue/inbound
  lot, movement component, histori HPP, dan defisit dalam transaksi. Endpoint
  web sebelumnya hanya meminta izin `delete`, tanpa CSRF atau konfirmasi ulang
  operator yang aktif pada browser.
- Diskusi/arah: fixer tunggal. Scope hanya pembatalan dokumen **POSTED** dari
  halaman Adjustment Base/Prepare. Validasi bisnis, transaksi reversal model,
  Daily Recon, adjustment gudang/divisi, API, APK, dan data mismatch tidak
  diubah.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php`.
  - `application/config/routes.php`, `application/controllers/Production.php`.
  - `application/views/production/component_adjustment_index.php`.
  - `tools/tests/component_adjustment_step_up_smoke.php` dan
    `tools/tests/pos_reversal_step_up_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Route `production/component-adjustments/void-step-up/verify` memakai izin
    `delete`, POST, CSRF header scoped yang sama, password user aktif, dan
    menerbitkan proof hash satu-kali `COMPONENT_ADJUSTMENT_VOID` yang hanya
    berlaku 180 detik untuk satu dokumen.
  - Writer `component_adjustment_void` mengonsumsi proof sebelum model reversal
    dipanggil. Password dan proof dibuang dari payload sehingga model tidak
    pernah menerima credential.
  - Modal VOID terpisah menjelaskan dampak lot/stok/nilai/defisit, memakai
    password masked, mengosongkan password sebelum request proof, serta
    mencegah klik/batal ketika verifikasi sedang berlangsung.
- SQL/runtime: **tidak ada SQL baru**, migration, schema/data, query tulis
  staging, credential, role/sidebar, atau kontrak POS Mobile/APK yang berubah.
- Validasi:
  - `php -l` enam file aplikasi/test berubah lulus; `git diff --check` lulus.
  - Smoke component adjustment lulus 19 kontrak route/RBAC/CSRF/proof/writer/UI;
    service proof nyata lulus 52 pemeriksaan termasuk action VOID baru.
  - Quality gate `parallel` lulus: required 58/58, development 4/4, release
    1/1, preflight 1/1. Runtime/security/static dan UAT browser nyata tetap
    tidak diklaim oleh profil otomatis.
- Review akhir fixer tunggal: layak untuk scope VOID Adjustment web. Caller
  lama tanpa header/proof ditolak fail-closed; role matrix tetap sama, sementara
  pembalikan movement aktual tetap tunduk pada guard periode/transaksi model.
- Risiko sisa: Save/Delete draft, Daily Recon, adjustment dan mutasi inventory
  lain, API/APK, MFA, serta UAT role nyata masih terbuka.
- Batch berikutnya: petakan satu writer mutasi inventory bernilai besar di luar
  halaman Adjustment Base/Prepare, kemudian amankan tanpa mencampur repair data.
- Penyerahan: commit lokal sesudah `36146a9`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 161 — CSRF dan reauth Adjustment Stok Gudang/Divisi web

- Waktu/tanggal: 2026-09-06, validasi akhir 05:36 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Satu controller melayani Adjustment Stok
  Gudang dan Divisi. Posting menulis stok live, daily rollup, dan lot FIFO;
  VOID membalik FIFO, histori stok, dan defisit. Sebelumnya writer menerima
  POST tanpa CSRF/reauth, termasuk shortcut `auto_post` yang langsung memanggil
  writer sesudah save draft.
- Diskusi/arah: fixer tunggal. Kedua scope diamankan bersama karena endpoint
  dan view adalah shared; ini mencegah Gudang tertutup tetapi Divisi atau
  shortcut server-side tetap terbuka. Tidak mengubah saldo/data historis,
  aturan FIFO, model, atau POS Mobile/APK.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php`.
  - `application/config/routes.php`, `application/controllers/Purchase.php`.
  - `application/views/purchase/stock_adjustment_index.php`.
  - `tools/tests/stock_adjustment_step_up_smoke.php` (baru),
    `tools/tests/pos_reversal_step_up_smoke.php`, dan manifest/kontrak quality
    gate.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Semua mutasi halaman—Save Draft, Delete Draft, Post, dan VOID—wajib POST
    dengan header CSRF scoped. Izin writer tetap ditentukan dari `stock_scope`
    **dokumen tersimpan**, bukan scope kiriman browser.
  - Endpoint verifikasi baru menerbitkan proof satu-kali 180 detik yang terikat
    user/dokumen/action `STOCK_ADJUSTMENT_POST` atau
    `STOCK_ADJUSTMENT_VOID`. Writer mengonsumsi proof sebelum model dipanggil;
    password/proof tidak masuk ke model.
  - UI Gudang maupun Divisi memakai modal password masked yang sama, menghapus
    password sebelum proof diminta, dan baru meneruskan proof ke writer.
  - `auto_post` ditolak fail-closed sebelum save draft: ID dokumen belum ada
    untuk mengikat proof, sedangkan UI resmi selalu Save Draft lalu Post.
- SQL/runtime: **tidak ada SQL baru**, migration, schema/data, query tulis
  staging, credential, role/sidebar, atau kontrak POS Mobile/APK yang berubah.
- Validasi:
  - `php -l` controller/library/route/view/test berubah dan `git diff --check`
    lulus.
  - Smoke stock adjustment lulus 17 kontrak; proof service nyata lulus 54
    pemeriksaan untuk action baru. Manifest quality gate bertambah satu gate
    wajib dan kontraknya lulus.
  - Quality gate `parallel` lulus: required 59/59, development 4/4, release
    1/1, preflight 1/1. Runtime/security/static dan UAT browser nyata tetap
    tidak diklaim oleh profil otomatis.
- Review akhir fixer tunggal: layak untuk shared web flow. Caller lama tanpa
  CSRF/proof, termasuk `auto_post`, ditolak; permission Gudang/Divisi existing
  tidak digabung atau dinaikkan.
- Risiko sisa: posting produksi/component batch, Daily Recon, stock opening,
  transfer, API/APK, MFA, serta UAT role nyata masih terbuka.
- Batch berikutnya: amankan posting Component Batch/produksi setelah memetakan
  jalur writer dan dampak bahan/component secara terpisah.
- Penyerahan: commit lokal sesudah `41ba136`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 162 — CSRF dan reauth Component Batch Produksi/Daily Component

- Waktu/tanggal: 2026-09-06, validasi akhir 05:48 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Posting Component Batch mengurangi bahan,
  menambah stok component, mengubah lot/FIFO dan biaya/HPP melalui
  `ComponentStockWriter`; VOID membalik rangkaian tersebut. Endpoint draft dan
  writer sebelumnya tidak mempunyai boundary CSRF/reauth yang scoped.
- Diskusi/arah: fixer tunggal. Halaman Batch Produksi dan Quick Batch pada
  Daily Component memakai endpoint yang sama sehingga keduanya diamankan dalam
  satu batch. Quick Adjustment Daily juga diselaraskan dengan CSRF/proof
  Adjustment yang sudah ada agar tidak gagal setelah boundary draft ditutup.
  Tidak mengubah resep, saldo/data historis, model writer, SQL, role matrix,
  atau kontrak POS Mobile/APK.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php`.
  - `application/config/routes.php`, `application/controllers/Production.php`.
  - `application/views/production/component_batch_index.php`,
    `application/views/production/component_daily_index.php`, dan
    `application/views/production/component_adjustment_index.php`.
  - `tools/tests/component_batch_step_up_smoke.php` (baru) serta manifest dan
    kontrak quality gate.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Save/Delete Draft Component Batch dan Component Adjustment sekarang wajib
    POST dengan header CSRF scoped. Caller resmi Batch, Adjustment, dan Daily
    mengirim token endpoint masing-masing.
  - Route verifikasi baru menerbitkan proof satu-kali 180 detik
    `COMPONENT_BATCH_POST` atau `COMPONENT_BATCH_VOID`, terikat ke user dan
    satu batch. `component_batch_post`/`void` mengonsumsi proof sebelum writer
    atau model reversal dipanggil; password tidak pernah diteruskan ke model.
  - Halaman Batch Produksi memiliki modal password masked terpisah untuk Post
    dan VOID. Daily Component menyimpan draft dahulu lalu menampilkan modal
    verifikasi; operator boleh memilih “Nanti Saja” dan melanjutkan posting
    draft dari halaman Batch Produksi tanpa perubahan stok yang tersembunyi.
- SQL/runtime: **tidak ada SQL baru**, migration, schema/data, query tulis
  staging, credential, role/sidebar, atau kontrak POS Mobile/APK yang berubah.
- Validasi:
  - `php -l` seluruh controller/library/route/view/test berubah lulus dan
    `git diff --check` lulus.
  - `component_batch_step_up_smoke.php` lulus 21 kontrak route/RBAC/CSRF/proof/
    writer/UI/Daily; smoke Component Adjustment (19), Adjustment Stok (17),
    dashboard audit (30), roadmap consistency (22), dan quality-gate contract
    (27) juga lulus.
  - Quality gate `parallel` lulus: required 60/60, development 4/4, release
    1/1. Runtime/security/static dan UAT browser nyata tetap tidak diklaim oleh
    profil otomatis.
- Review akhir fixer tunggal: layak untuk scope Component Batch web dan Daily
  resmi. Request lama tanpa header/proof ditolak fail-closed; Daily tidak lagi
  melakukan post tanpa reauth. Tidak ada perbaikan mismatch data otomatis.
- Risiko sisa: Daily Recon selain quick action, stock opening, transfer, mutasi
  produksi lain, API/APK, MFA, baseline role nyata, dan UAT perangkat/role
  masih terbuka.
- Batch berikutnya: amankan stock opening/transfer atau Daily Recon sebagai
  batch kecil berikutnya, setelah memetakan caller resmi dan writer lot/stok.
- Penyerahan: commit lokal sesudah `e67186c`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 163 — CSRF dan reauth Daily Recon Component

- Waktu/tanggal: 2026-09-06, validasi akhir 06:00 WIB.
- Prioritas: P0 / `AUD-A1-STEP-01`. Daily Recon menyimpan hitungan fisik dan
  checkpoint, lalu endpoint quick-adjust dapat membuat **dan** mem-post
  adjustment yang mengubah stok, lot, nilai, dan defisit dalam satu request.
  Jalur ini sebelumnya melewati CSRF scoped dan reauth.
- Diskusi/arah: fixer tunggal. Quick-adjust tidak dipecah menjadi repair data
  atau perubahan model; proof satu-kali diikat ke identity component karena
  dokumen adjustment baru ada setelah writer mulai berjalan. Endpoint tetap
  memvalidasi snapshot, lokasi/divisi, period guard, lot, dan seluruh aturan
  business existing sebelum menulis. Tidak mengubah data historis atau POS
  Mobile/APK.
- File berubah:
  - `application/libraries/SensitiveActionStepUp.php`.
  - `application/config/routes.php`, `application/controllers/Production.php`.
  - `application/views/production/component_daily_recon_index.php`.
  - `tools/tests/component_daily_recon_step_up_smoke.php` (baru), manifest,
    dan kontrak quality gate.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Simpan hitungan fisik, konfirmasi checkpoint, verifikasi, dan quick-adjust
    Daily Recon wajib POST dengan header
    `X-Production-Component-Daily-Recon-Csrf` yang terikat sesi.
  - Route verifikasi baru memeriksa izin Daily Recon **dan** Adjustment,
    kemudian menerbitkan proof `COMPONENT_DAILY_RECON_POST` yang one-use,
    180 detik, terikat user dan component. Writer mengonsumsi proof sebelum
    membuat draft ataupun memanggil `post_component_adjustment_document`;
    password tidak memasuki model/writer.
  - UI meminta konfirmasi lalu password masked, menghapus password sebelum
    request proof, dan memakai wrapper CSRF yang sama untuk seluruh mutasi.
- SQL/runtime: **tidak ada SQL baru**, migration, schema/data, query tulis
  staging, credential, role/sidebar, atau kontrak POS Mobile/APK yang berubah.
- Validasi:
  - `php -l` file PHP berubah dan `git diff --check` lulus.
  - Smoke Daily Recon baru lulus 16 kontrak; smoke Component Batch (21) dan
    Adjustment (19), serta quality-gate contract (27) lulus.
  - Quality gate `parallel` dijalankan setelah penambahan manifest: required
    61/61, development 4/4, release 1/1, dan preflight 1/1 harus lulus sebelum
    commit. Runtime/security/static dan UAT browser nyata tetap di luar profil
    parallel.
- Review akhir fixer tunggal: layak untuk scope Daily Recon Component. Request
  lama tanpa header/proof ditolak fail-closed; tidak ada saldo mismatch yang
  direpair otomatis.
- Risiko sisa: stock opening/transfer, mutasi produksi lain, API/APK, MFA,
  baseline role nyata, dan UAT perangkat/role masih terbuka.
- Batch berikutnya: petakan dan amankan transfer stok Divisi atau stock opening
  sebagai mutasi inventory bernilai tinggi berikutnya.
- Penyerahan: commit lokal sesudah `62ffbb4`, tanpa push; ringkasan dikirim ke
  Telegram Namua setelah commit.

## Batch 165 — CSRF Stock Opening manual, VOID, dan import

- Waktu/tanggal: 2026-09-06, validasi awal 06:17 WIB.
- Prioritas: P0 / A1. Stock Opening langsung membuat atau mengganti snapshot,
  lot awal, movement, saldo, nilai, dan histori stok. Sebelumnya input manual,
  VOID, dan import Excel dapat dipanggil tanpa token mutasi khusus.
- Diskusi/arah: fixer tunggal. Batch dibatasi pada request integrity untuk
  opening Gudang/Divisi; tidak mengubah aturan opening, FIFO/model, data
  historis, SQL/schema, role matrix, atau POS Mobile/APK.
- File berubah:
  - application/controllers/Purchase.php.
  - application/views/purchase/stock_opening_index.php dan
    application/views/purchase/stock_opening_division_index.php.
  - tools/tests/stock_opening_csrf_smoke.php (baru), manifest, dan kontrak
    quality gate; roadmap _30, _28, serta log ini.
- Perubahan utama:
  - Header X-Stock-Opening-Csrf wajib untuk input manual dan VOID JSON.
  - Import Excel divisi memakai token tersembunyi yang dibandingkan dengan
    token sesi sebelum field request, parsing upload, atau writer per baris.
  - Caller lama tanpa token gagal tertutup; pencarian/read/export tetap tidak
    diperlakukan sebagai mutasi.
- SQL/runtime: tidak ada SQL baru, migration, query tulis staging, perubahan
  data mismatch, credential, sidebar, atau kontrak POS Mobile/APK.
- Validasi:
  - php -l file PHP berubah, smoke opening baru (10 kontrak), quality-gate
    contract (27), dan git diff --check lulus.
  - Quality gate parallel dijalankan sebelum commit. Reauth password dan UAT
    browser nyata belum diklaim oleh batch CSRF ini.
  - php -l file PHP berubah, smoke opening baru (10 kontrak), regression
    authorization Opening (66), matrix A2 (12 smoke), matrix lintas-modul
    A4 (35), quality-gate contract (27), dan git diff --check lulus.
  - Quality gate parallel lulus: required 63/63, development 4/4, release
    1/1, dan preflight 1/1. Reauth password serta UAT browser nyata belum
    diklaim oleh batch CSRF ini.
- Review akhir fixer tunggal: boundary CSRF konsisten untuk tiga jalur writer
  opening. Reauth untuk posting opening harus memakai target scope yang jelas
  karena opening manual/import belum membuat dokumen draft terpisah.
- Risiko sisa: reauth opening, mutasi inventory/produksi lain, API/APK, MFA,
  baseline role nyata, serta UAT browser/perangkat per role.
- Batch berikutnya: rancang proof reauth Stock Opening yang terikat scope
  resmi, lalu lanjutkan mutasi inventory lain.
- Penyerahan: commit lokal setelah semua gate lulus, tanpa push; ringkasan
  dikirim ke Telegram Namua setelah commit.

## Batch 169 — Revision dan audit editor massal Formula Component

- Waktu/tanggal: 2026-09-06, validasi akhir 07:31 WIB.
- Prioritas: P0 / `AUD-A1-SEC-02`. Editor Formula Component mengganti seluruh
  line formula; dua tab browser sebelumnya dapat menimpa formula terbaru tanpa
  konflik eksplisit atau jejak before/after atomik.
- Diskusi/arah: fixer tunggal. Scope hanya endpoint kanonis
  `production/component-formulas/save-bulk`, model Formula Component, dan
  editor web resminya. Tidak menyentuh POS Mobile/APK, stok, lot, HPP, data
  mismatch, SQL/schema, atau matriks role.
- File berubah:
  - `application/controllers/Production.php`,
    `application/models/Production_model.php`, dan
    `application/views/production/component_formula_edit.php`.
  - `tools/tests/production_component_formula_mutation_csrf_smoke.php` dan
    smoke baru `production_component_formula_revision_audit_smoke.php`.
  - Manifest/kontrak quality gate, roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - Editor membawa hash revision dari snapshot formula saat halaman dibuka.
  - Simpan massal memvalidasi revision, mengunci parent `mst_component` dan
    line `mst_component_formula` dengan `FOR UPDATE`, lalu menolak tab lama
    dengan HTTP 409 dan pesan untuk memuat ulang halaman.
  - Hapus/ganti seluruh line dan audit `REPLACE_COMPONENT_FORMULA` dengan
    payload sebelum/sesudah berada dalam satu transaksi. Audit/table/kolom,
    lock, write audit, atau commit yang gagal membatalkan perubahan.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data HPP/mismatch, credential, sidebar, atau kontrak POS
  Mobile/APK.
- Validasi:
  - Lint seluruh PHP berubah, `git diff --check`, smoke CSRF formula (192
    check), smoke revision/audit formula (13), matrix direct-URL A1, matrix A2,
    dan quality-gate contract (27) lulus sebelum full gate.
  - Quality gate `parallel` lulus: required 67/67, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static serta UAT browser dua-tab
    nyata tetap harus dibuktikan terpisah.
- Review akhir fixer tunggal: layak untuk editor massal Formula Component.
  Snapshot lama tidak dapat menimpa formula terbaru dan audit atomik
  fail-closed. Writer formula individual, Extra, Bundle, formula versioning,
  APK, MFA, baseline role nyata, serta UAT perangkat tetap terbuka.
- Batch berikutnya: petakan writer Resep individual atau Extra sebagai batch
  A1 kecil berikutnya.
- Penyerahan: commit lokal tanpa push setelah seluruh gate lulus; ringkasan
  dikirim ke Telegram Namua setelah commit.

## Batch 170 — Revision dan audit writer individual Resep Produk

- Waktu/tanggal: 2026-09-06, validasi akhir 07:45 WIB.
- Prioritas: P0 / `AUD-A1-SEC-02`. Jalur lama Tambah, Edit, dan Hapus satu
  line resep sudah memakai RBAC/CSRF, tetapi belum menolak snapshot resep yang
  kedaluwarsa dan belum selalu mencatat before/after audit atomik.
- Diskusi/arah: fixer tunggal. Scope hanya `Master_relation` dan form/list
  Resep Produk lama. Tidak mengubah aturan bahan/component, HPP, formula,
  stock, data mismatch, SQL/schema, POS Mobile/APK, atau role matrix.
- File berubah:
  - `application/controllers/Master_relation.php`.
  - `application/views/master/relation_form.php` dan
    `application/views/master/relation_list.php`.
  - Smoke CSRF/revision Resep Produk dan roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - Form tambah/edit dan form hapus membawa snapshot hash seluruh resep.
  - Tiga writer memulai transaksi audit, mengunci produk serta seluruh line
    resep `FOR UPDATE`, lalu membatalkan request dari tab yang kedaluwarsa.
  - Audit before/after atomik mencatat aksi terpisah
    `CREATE_PRODUCT_RECIPE_LINE`, `UPDATE_PRODUCT_RECIPE_LINE`, atau
    `DELETE_PRODUCT_RECIPE_LINE`; audit/lock/commit yang gagal membatalkan
    perubahan line.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data HPP/mismatch, credential, sidebar, atau kontrak POS
  Mobile/APK.
- Validasi:
  - Lint PHP, `git diff --check`, smoke CSRF Resep Produk (138 check), smoke
    revision/audit (18), matrix direct-URL A1, dan quality-gate contract (27)
    lulus sebelum full gate.
  - Quality gate `parallel` lulus: required 67/67, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static serta UAT browser dua-tab
    nyata tetap harus dibuktikan terpisah.
- Review akhir fixer tunggal: layak untuk tiga writer individual. Extra,
  Bundle, formula versioning, APK, MFA, baseline role nyata, serta UAT
  perangkat tetap terbuka.
- Batch berikutnya: petakan writer Extra sebagai batch A1 kecil berikutnya.
- Penyerahan: commit lokal tanpa push setelah seluruh gate lulus; ringkasan
  dikirim ke Telegram Namua setelah commit.

## Batch 171 — Audit atomik mapping Product Extra

- Waktu/tanggal: 2026-09-06, validasi akhir 07:51 WIB.
- Prioritas: P0 / `AUD-A1-SEC-02`. Writer tambah dan hapus mapping Product
  Extra sudah memakai CSRF, prepared statement, revalidasi, lock, dan
  transaksi; tetapi belum mencatat before/after audit sebagai bagian commit.
- Diskusi/arah: fixer tunggal. Jalur prepared statement dan aturan group aktif,
  divisi, duplicate, serta pembersihan mapping historis tidak diganti. Tidak
  mengubah POS Mobile/APK, HPP, stok, data mismatch, SQL/schema, atau role.
- File berubah:
  - `application/controllers/Master_relation.php`.
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`.
  - Roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - Audit wajib tersedia sebelum mutasi dimulai; bila tabel/kolom audit belum
    siap, mapping tidak ditulis.
  - Setelah prepared insert atau delete tepat satu row, transaksi menyimpan
    audit `CREATE_PRODUCT_EXTRA_MAP` atau `DELETE_PRODUCT_EXTRA_MAP` dengan
    payload sebelum/sesudah. Audit gagal membuat seluruh transaksi rollback.
  - Prepared statement, bind integer, lock, serta error aman yang telah ada
    tetap menjadi satu-satunya jalur DML mapping.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data HPP/mismatch, credential, sidebar, atau kontrak POS
  Mobile/APK.
- Validasi:
  - Lint PHP, `git diff --check`, smoke mapping Product Extra (434 check),
    matrix direct-URL A1, dan regression resep lulus sebelum full gate.
  - Quality gate `parallel` lulus: required 67/67, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static serta UAT browser nyata
    tetap harus dibuktikan terpisah.
- Review akhir fixer tunggal: layak untuk writer mapping Product Extra. Bundle,
  formula versioning, APK, MFA, baseline role nyata, serta UAT perangkat tetap
  terbuka.
- Batch berikutnya: petakan writer Bundle sebagai batch A1 kecil berikutnya.
- Penyerahan: commit lokal tanpa push setelah seluruh gate lulus; ringkasan
  dikirim ke Telegram Namua setelah commit.

## Batch 172 — Revision dan audit writer Bundle Produk

- Waktu/tanggal: 2026-09-06, validasi akhir 08:01 WIB.
- Prioritas: P0 / `AUD-A1-SEC-02`. Tambah, ganti isi, dan ubah status Bundle
  Produk sudah memakai RBAC/CSRF tetapi belum mempunyai snapshot konflik,
  row lock, atau jejak audit atomik; editor lama dapat menimpa isi terbaru.
- Diskusi/arah: fixer tunggal. Scope hanya writer Bundle di `Master_relation`,
  editor Bundle web, dan smoke terkait. Tidak menyentuh POS Mobile/APK, aturan
  harga bundle, POS transaksi, stok, HPP, data mismatch, SQL/schema, atau
  matriks role.
- File berubah:
  - `application/controllers/Master_relation.php` dan
    `application/views/master/product_bundle_edit.php`.
  - `tools/tests/master_relation_product_bundle_mutation_csrf_smoke.php` dan
    smoke baru `master_relation_product_bundle_revision_audit_smoke.php`.
  - Manifest/kontrak quality gate, roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - Editor kirim hash revision dari header dan seluruh line Bundle saat
    halaman dibuka. Simpan dari snapshot lama dibatalkan dengan pesan untuk
    memuat ulang, sehingga tidak dapat menimpa perubahan operator lain.
  - Ganti isi mengunci header dan line `FOR UPDATE`; tambah juga menulis lewat
    transaksi audit. Audit gagal, lock gagal, atau commit gagal membatalkan
    seluruh perubahan.
  - Audit before/after atomik mencatat `CREATE_PRODUCT_BUNDLE`,
    `REPLACE_PRODUCT_BUNDLE`, dan `TOGGLE_PRODUCT_BUNDLE`. Toggle status juga
    mengunci header supaya dua request tidak saling membaca status lama.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data HPP/mismatch, credential, sidebar, atau kontrak POS
  Mobile/APK.
- Validasi:
  - Lint seluruh PHP berubah, `git diff --check`, smoke CSRF Bundle (152
    check), smoke revision/audit Bundle (13), serta quality-gate contract (27)
    lulus sebelum full gate.
  - Quality gate `parallel` lulus: required 68/68, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static serta UAT browser dua-tab
    nyata tetap harus dibuktikan terpisah.
- Review akhir fixer tunggal: layak untuk writer Bundle Produk. Formula
  versioning, reauth APK, MFA, baseline role nyata, serta UAT perangkat tetap
  terbuka.
- Batch berikutnya: petakan formula versioning sebagai batch A1 kecil tanpa
  mengubah kontrak POS Mobile/APK.
- Penyerahan: commit lokal tanpa push setelah seluruh gate lulus; ringkasan
  dikirim ke Telegram Namua setelah commit.

## Batch 173 — Riwayat versi Formula Component kanonis

- Waktu/tanggal: 2026-09-06, validasi akhir 08:36 WIB.
- Prioritas: P0 / AUD-A1-SEC-02. Editor Formula Component kanonis sudah
  menolak tab lama dan mengaudit before/after, tetapi belum menyimpan snapshot
  yang dapat dibaca sebagai riwayat versi.
- Diskusi/arah: fixer tunggal. Snapshot bersifat append-only; tidak
  memodifikasi formula yang sudah dipakai batch produksi, stok, HPP, data
  mismatch, POS Mobile/APK, atau hak akses. Formula lama pertama yang diubah
  disimpan sebagai baseline, lalu hasil setiap simpan menjadi versi baru.
- File berubah:
  - Production controller/model dan detail Formula Component.
  - Migration 2026-09-06a_component_formula_version_history.sql, baseline
    clean-install, catalog, policy, dan kontrak migration/restore/health.
  - Smoke formula history, quality gate, roadmap _30, _28, serta log ini.
- Perubahan utama:
  - Save bulk fail-closed jika tabel audit atau tabel history belum siap.
  - Dalam satu transaksi berlock, baseline formula lama bila ada tersimpan
    sekali, formula live diganti, snapshot REPLACE dan audit before/after
    ditulis sebelum commit.
  - Detail formula menampilkan timeline versi read-only beserta aktor dan
    waktu; belum ada tombol restore agar pemulihan tidak dapat mengubah resep
    tanpa desain reauth/audit khusus.
- SQL/runtime:
  - Staging: migration runner upgrade applied 1, skipped 5; replay applied 0,
    skipped 6. Dua tabel history dibuat tanpa perubahan data formula/stok/HPP.
  - Server utama: PENDING_OWNER; jalankan hanya catalog-managed migration
    runner sesuai runbook deployment, bukan file SQL manual.
- Validasi:
  - PHP lint file berubah, migration runner validate/plan, smoke revision
    formula, smoke history baru, baseline, catalog, restore/rollback, health,
    schema fingerprint, legacy guard, dan Telegram lulus.
  - Git diff --check dan quality gate parallel lulus: required 69/69,
    development 4/4, release 1/1, preflight 1/1.
- Review akhir fixer tunggal: fondasi history editor kanonis layak diuji.
  Risiko sisa: writer Formula legacy masih dapat melewati history; restore
  versi, UAT dua-tab, reauth APK/MFA, dan UAT role/perangkat tetap terbuka.
- Batch berikutnya: alihkan jalur Formula legacy ke editor kanonis tanpa
  mengubah kontrak POS Mobile/APK.

## Batch 174 — Satu jalur kanonis Formula Component

- Waktu/tanggal: 2026-09-06, validasi akhir 09:06 WIB.
- Prioritas: P0 / `AUD-A1-SEC-02`. Riwayat versi Batch 173 hanya terjamin bila
  seluruh writer formula memakai editor bulk kanonis; route Master lama dan
  endpoint Production per-baris masih dapat melewati snapshot tersebut.
- Diskusi/arah: fixer tunggal. Bookmark lama harus tetap dapat dibuka dengan
  aman, tetapi tidak boleh lagi dapat menulis data. Tidak menyentuh POS
  Mobile/APK, stok, HPP, mismatch historis, hak role, atau data bisnis.
- File berubah:
  - `application/controllers/Master_relation.php`, `Production.php`, dan
    `application/models/Production_model.php`.
  - View relation formula legacy dan smoke direct-URL/Formula terkait.
  - Roadmap `_30`, `_28`, serta log ini.
- Perubahan utama:
  - Semua halaman/POST Formula legacy Master kini mengarahkan pengguna ke
    editor Formula Component kanonis; POST lama memerlukan guard lama lebih
    dahulu dan kemudian hanya memberi pesan pengalihan.
  - Endpoint Production simpan/hapus satu line mempertahankan RBAC dan CSRF,
    tetapi mengembalikan JSON `410 Gone` tanpa query atau mutasi.
  - Writer/model per-baris dihapus. Satu-satunya writer aktif adalah bulk
    save yang sudah memakai revision lock, audit atomik, dan history versi.
- SQL/runtime: **tidak ada SQL baru**, migration, query tulis staging,
  perubahan data formula/stok/HPP/mismatch, atau perubahan kontrak POS
  Mobile/APK.
- Validasi:
  - PHP lint seluruh file PHP berubah dan `git diff --check` lulus.
  - Smoke Formula Production 201 check, matrix A1 direct URL, A2 inventory
    transaction matrix, dan A4 cross-module matrix lulus.
  - Quality gate `parallel` lulus: required 69/69, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static serta UAT browser/perangkat
    tetap dijalankan melalui gate dan lingkungan yang sesuai.
- Review akhir fixer tunggal: jalur Formula Component kini tunggal dan dapat
  ditelusuri. Risiko sisa: restore versi belum ada; UAT browser dua-tab,
  reauth APK/MFA, baseline role nyata, dan UAT perangkat tetap terbuka.
- Batch berikutnya: desain dan implementasikan restore versi Formula dengan
  reauth one-use, preview before/after, lock/revision, dan audit atomik.

## Batch 175 — Restore versi Formula Component terotorisasi

- Waktu/tanggal: 2026-09-06, validasi akhir 09:19 WIB.
- Prioritas: P0 / `AUD-A1-SEC-02`. History append-only dan writer kanonis
  sudah ada, tetapi operator belum mempunyai jalur terkendali untuk kembali ke
  versi formula lama tanpa menyalin resep secara manual.
- Diskusi/arah: fixer tunggal. Restore harus satu transaksi, menolak tab lama,
  memakai password hanya untuk menerbitkan proof sekali pakai yang terikat ID
  versi, dan tidak menyentuh POS Mobile/APK, stok, HPP, atau data mismatch.
- File berubah:
  - `Production` controller/model, `SensitiveActionStepUp`, routes, dan detail
    Formula Component.
  - Migration `2026-09-06b_component_formula_restore_action.sql`, baseline,
    katalog, policy, drill, health/rollback contract, serta smoke restore.
  - Roadmap `_30`, `_28`, dan log ini.
- Perubahan utama:
  - Timeline formula menampilkan tombol Pulihkan hanya untuk user berizin edit.
    Modal meminta password; browser langsung menghapusnya lalu mengirim hanya
    proof satu-kali ke writer restore.
  - Writer mengunci component dan formula aktif, memeriksa revision browser,
    memastikan versi milik component yang sama dan lengkap, lalu mengganti
    formula dalam transaksi. Snapshot hasil diberi `RESTORE` dan audit
    `RESTORE_COMPONENT_FORMULA` menyimpan before/after serta ID versi asal.
- SQL/runtime:
  - Staging: migration runner upgrade applied 1/skipped 6; replay applied 0/
    skipped 7. Migration hanya memperluas enum action riwayat formula dengan
    `RESTORE`; tidak mengubah stok, HPP, atau formula aktif.
  - Server utama: `PENDING_OWNER`; jalankan via migration runner managed,
    bukan file SQL manual.
- Validasi:
  - PHP lint, `git diff --check`, smoke restore 21 check, A1 direct URL,
    A4 cross-module, formula revision/history, serta kontrak katalog,
    fingerprint, legacy upgrade, restore/rollback, health, dan Telegram lulus.
  - Quality gate `parallel` lulus: required 70/70, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static serta UAT browser/perangkat
    tetap dijalankan melalui gate dan lingkungan yang sesuai.
- Review akhir fixer tunggal: restore aman untuk UAT staging. Risiko sisa:
  UAT browser dua-tab/restore nyata, kontrak reauth APK, MFA, baseline role
  nyata, dan UAT perangkat masih terbuka.
- Batch berikutnya: jalankan matriks UAT Formula Component dan pilih hardening
  A1 berikutnya berdasarkan hasilnya.

## Batch 176 — Reauth one-use Void/Refund POS Mobile

- Waktu/tanggal: 2026-09-06, validasi akhir 09:51 WIB.
- Prioritas: P0 / `AUD-A1-POS-01` dan `AUD-A1-STEP-01`. Void dan Refund
  melalui POS Mobile sudah memakai bearer/device/outlet/RBAC, tetapi belum
  mewajibkan verifikasi ulang password seperti jalur web. Token perangkat yang
  tertinggal dapat dipakai untuk reversal finansial tanpa proof tambahan.
- Diskusi/arah: fixer tunggal. Kontrak API hanya berubah untuk Void/Refund:
  APK meminta proof melalui endpoint verify, kemudian mengirim proof itu pada
  writer. POS Mobile lain, stok, HPP, data mismatch historis, dan rule bisnis
  reversal tidak diubah.
- File berubah:
  - `application/controllers/Pos_mobile.php` dan `application/config/routes.php`.
  - `sql/2026-09-06c_pos_mobile_reversal_step_up.sql`, baseline clean-install,
    katalog migration, policy baseline, dan drill restore/rollback.
  - Smoke POS Mobile, matriks A1/A4, quality-gate, kontrak katalog/health,
    serta dua roadmap induk.
- Perubahan utama:
  - Endpoint `pos-mobile/orders/reversal-step-up/verify` menerima password
    hanya untuk menerbitkan proof acak 180 detik. Yang disimpan hanya hash,
    terikat token, user, terminal, action `VOID`/`REFUND`, dan `order_id`.
  - Writer Void/Refund mengonsumsi proof dengan update bersyarat atomik;
    proof replay, expired, beda terminal/user/action/order, atau tanpa proof
    ditolak `428 step_up_required`. Password dan proof dibuang sebelum model
    writer dipanggil.
  - Kegagalan password dibatasi per token (5 dalam 10 menit); schema yang
    belum diperbarui gagal tertutup `503`, bukan fallback tanpa reauth.
  - Jalur session lama memakai `SensitiveActionStepUp` web yang sama; tidak
    ada bypass pada route POS Mobile.
- SQL/runtime:
  - Staging: runner policy `upgrade` dry-run planned 8; apply applied 1/
    skipped 7; replay applied 0/skipped 8. Probe read-only membuktikan tabel
    proof dan tiga kolom limiter token tersedia.
  - Server utama/customer: migration dikelola catalog/updater; jangan
    menjalankan file SQL manual. APK kandidat harus menambahkan alur
    verify-password → `step_up_proof` → Void/Refund.
- Validasi:
  - PHP lint seluruh file PHP berubah dan `git diff --check` lulus.
  - Smoke proof baru 13 check, authorization POS Mobile, binding financial
    writer, A1 direct URL, migration catalog/baseline/health/rollback,
    roadmap consistency, dan preflight release lulus.
  - Quality gate `parallel` lulus: required 71/71, development 4/4, release
    1/1, preflight 1/1. Runtime/security/static dan UAT browser/APK/printer
    tetap berada pada profile/lingkungan terpisah.
- Review akhir fixer tunggal: layak untuk staging dan kontrak APK baru.
  Risiko sisa: APK lama tanpa proof akan menerima `428`; UAT perangkat,
  reprint/aksi mobile sensitif lain, MFA, dan baseline role nyata masih
  terbuka.
- Batch berikutnya: inventaris dan amankan reprint POS Mobile atau pilih aksi
  mobile sensitif bernilai tinggi berikutnya; jalankan UAT APK setelah build
  mengadopsi proof reversal.

## Batch 177 — Reauth one-use Reprint POS Mobile

- Waktu/tanggal: 2026-09-06, validasi akhir batch.
- Prioritas: P0 / `AUD-A1-POS-01` dan `AUD-A1-STEP-01`. Reprint APK sudah
  dibatasi bearer/RBAC/outlet, tetapi target printer masih dapat diminta tanpa
  reauth sehingga token perangkat yang tertinggal dapat mencetak ulang order.
- Diskusi/arah: fixer tunggal. Perubahan hanya pada kontrak Reprint POS Mobile;
  tidak mengubah writer order, pembayaran, stok, HPP, data mismatch, maupun
  aturan printer.
- File berubah:
  - `application/controllers/Pos_mobile.php` dan `application/config/routes.php`.
  - `sql/2026-09-06d_pos_mobile_reprint_step_up.sql`, baseline clean-install,
    catalog/policy migration, serta drill restore/upgrade.
  - Smoke POS Mobile, matriks A1, kontrak catalog/health/rollback, dan dua
    roadmap induk.
- Perubahan utama:
  - Endpoint baru `pos-mobile/orders/reprint-step-up/verify` menerima password
    hanya untuk menerbitkan `step_up_proof` aksi tetap `ORDER_REPRINT`.
  - `pos-mobile/orders/reprint-targets/{id}` kini POST-only, memeriksa bearer,
    view RBAC, dan outlet order sebelum mengonsumsi proof satu-kali. Proof Void
    atau Refund tidak dapat dipakai untuk Reprint; replay/expired/beda binding
    ditolak `428 step_up_required`.
  - Migration hanya memperluas enum proof untuk `ORDER_REPRINT`; tidak mengubah
    order, pembayaran, stok, atau HPP.
- SQL/runtime:
  - Staging: migration runner upgrade dry-run planned 9; apply applied 1/
    skipped 8; replay applied 0/skipped 9. Probe read-only membuktikan action
    `ORDER_REPRINT` tersedia pada schema proof.
  - Server utama/customer: tetap melalui catalog/updater managed; jangan
    menjalankan file SQL manual. APK mengirim verify-password → `step_up_proof`
    → POST reprint targets.
- Validasi:
  - PHP lint, `git diff --check`, authorization POS Mobile, smoke proof (19
    check), A1 direct URL, migration runner validate/plan, catalog, dan legacy
    guard lulus.
  - Quality gate `parallel` lulus: required 71/71, development 4/4, release
    1/1, dan preflight 1/1. Runtime/security/static serta UAT browser,
    APK/perangkat, dan printer fisik tetap berada pada profile/lingkungan
    terpisah.
- Review akhir fixer tunggal: layak di staging. Risiko sisa: APK lama yang
  memakai GET atau tidak mengirim proof menerima `405`/`428`; UAT perangkat,
  aksi mobile sensitif lain, MFA, dan baseline role nyata masih terbuka.
- Batch berikutnya: jalankan UAT APK atas alur Void/Refund/Reprint atau pilih
  aksi POS Mobile sensitif bernilai tinggi berikutnya.

## Batch 178 — Kontrak capability reauth APK POS Mobile

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1 / `AUD-A1-POS-01`. APK tidak boleh menebak endpoint atau
  method setelah perubahan proof Void/Refund/Reprint.
- Perubahan utama: respons bearer `pos-mobile/bootstrap` kini memuat kontrak
  non-secret versi 1: TTL proof dan route/method exact untuk `VOID`, `REFUND`,
  serta `ORDER_REPRINT`. Tidak mengubah permission, writer, stok, HPP, data,
  atau schema.
- Validasi: lint PHP, smoke authorization POS Mobile, smoke proof, matrix A1,
  dan quality gate penuh dijalankan pada batch ini.
- Risiko sisa/batch berikutnya: kontrak perlu diadopsi APK dan dibuktikan pada
  perangkat nyata; keputusan apakah pembayaran/tutup kasir perlu reauth harus
  mengikuti kebijakan operasional karena jalur web juga belum mewajibkannya.

## Batch 179 — Kandidat artefak release hanya dari Git bersih

- Waktu/tanggal: 2026-09-06.
- Prioritas: A0/A5 / `AUD-A5-PACK-01`. Builder artefak sebelumnya dapat
  mempertimbangkan file untracked; ini berisiko memasukkan helper lokal atau
  pekerjaan yang belum disetujui ke paket customer.
- Perubahan utama: candidate set hanya mengambil file tracked, dan builder
  menolak worktree dengan perubahan staged, unstaged, atau untracked sebelum
  menjalankan gate maupun sebelum menerbitkan archive. Tidak ada data runtime
  atau credential yang dihapus.
- Validasi: `php -l` untuk tiga file PHP berubah; contract artefak menguji dua
  build committed yang deterministik, symlink, mutasi source, dan untracked
  local source yang wajib ditolak; preflight dan contract policy lulus;
  `php tools/tests/finance_quality_gate.php parallel` lulus
  (required 71/71, development 4/4, release 1/1, preflight 1/1).
- Risiko sisa/batch berikutnya: artefak customer nyata tetap menunggu commit
  kandidat bersih, signing key produksi, installer/updater, serta UAT deploy.

## Batch 180 — State UI aman untuk Master Component

- Waktu/tanggal: 2026-09-06.
- Prioritas: A3 gelombang 2/5 (`AUD-A3-UI-02`, `AUD-A3-UI-05`). Halaman
  Master Component sudah memakai AJAX filter, tetapi belum mengadopsi state
  loading/gagal/retry dan primitive tabel global secara utuh.
- Perubahan utama: memakai shell header/card/action/filter/tabel yang sama,
  menampilkan loading, empty state, error dengan tombol retry, dan `aria-busy`.
  Pesan backend dimasukkan sebagai `textContent`, bukan HTML. Tidak ada
  perubahan endpoint, permission, formula, stok, HPP, atau data component.
- Validasi: `php -l` view dan smoke UI 54/54 lulus;
  `php tools/tests/finance_quality_gate.php parallel` lulus
  (required 71/71, development 4/4, release 1/1, preflight 1/1).
- Risiko sisa/batch berikutnya: ini baru satu halaman Production; migrasi UI
  tetap dilanjutkan satu rumpun kecil per batch dan memerlukan UAT visual.

## Batch 181 — Riwayat rekening jelas pada transaksi backdate

- Waktu/tanggal: 2026-09-06.
- Prioritas: A2 / `AUD-A2-FIN-01`. Kolom saldo sebelum/sesudah adalah chain
  saat posting, tetapi daftar sebelumnya mengurutkan tanggal bisnis sehingga
  backdate tampak seperti saldo yang putus.
- Perubahan utama: daftar diurutkan berdasarkan ID posting, filter tetap
  menggunakan tanggal bisnis, serta UI menunjukkan Tanggal Bisnis, waktu
  Diposting, dan label Backdate bila kedua tanggal berbeda. Tidak ada saldo,
  mutasi, periode, atau data historis yang diubah.
- Validasi: `php -l` untuk model/view/test/gate lulus; contract riwayat A2
  6/6 dan contract gate 27/27 lulus; quality gate parallel lulus
  (required 72/72, development 4/4, release 1/1, preflight 1/1). Probe
  database A2 yang hanya-baca dicoba tetapi tidak dapat tersambung pada
  konfigurasi PHP CLI saat ini; tidak ada query atau perubahan data dilakukan.
- Risiko sisa/batch berikutnya: acceptance finance untuk laporan as-of/rebuild
  saldo dan fixture backdate nyata masih diperlukan; uang makan/slip payroll
  belum disentuh karena memerlukan keputusan aturan bisnis.

## Batch 182 — Riwayat harga Purchase berbasis Receipt

- Waktu/tanggal: 2026-09-06.
- Prioritas: A2 / `AUD-A2-PUR-02`. Halaman `purchase/item-price-history/561`
  sebelumnya hanya membaca `inv_stock_movement_log` bertipe `PURCHASE_IN`.
  Receipt pembelian yang valid tetapi ledger-nya tidak lengkap akibat proses
  lama menjadi tampak tidak memiliki riwayat harga.
- Perubahan utama: `pur_purchase_receipt` berstatus `POSTED` beserta line PO
  menjadi sumber utama. HPP per isi dihitung dari kuantitas receipt aktual,
  lalu conversion/profile PO sebagai fallback. Ledger lama tetap ditampilkan
  hanya bila tidak punya `receipt_line_id`, sehingga tidak menduplikasi receipt.
  UI kini menunjukkan nomor receipt atau label Ledger lama.
- File berubah: `Purchase.php`, `Purchase_model.php`, view riwayat harga,
  smoke khusus, quality gate, dan roadmap audit induk.
- Validasi: PHP lint seluruh file berubah, `git diff --check`, smoke riwayat
  harga 9/9, serta contract quality gate 27/27 lulus.
- Risiko sisa/batch berikutnya: perlu UAT browser untuk item 561 dan satu
  receipt historis tanpa ledger; pembetulan angka/data receipt yang salah tetap
  tidak dilakukan otomatis.

## Batch 183 — Registry aktivitas akses dan transaksi

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1/A5 / `AUD-A1-ACT-01`. Sebelumnya aplikasi hanya memiliki log
  login dan audit transaksi parsial tanpa satu halaman yang menjawab siapa,
  kapan, dari IP/perangkat apa, membuka halaman mana, atau mengubah data apa.
- Perubahan utama: halaman **System → Log Aktivitas** menggabungkan login,
  akses halaman, dan transaksi audit yang ada. Akses halaman HTML yang sudah
  lolos login/permission direkam mulai fitur aktif. Pencatatan tidak menyimpan
  query URL, body request, password, token, atau payload before/after. Untuk
  transaksi, perangkat hanya ditampilkan bila ada sesi login cocok pada user,
  waktu, dan IP yang sama.
- Hak akses: page/menu baru `system.activity_audit.index`, hanya SUPERADMIN
  secara default; admin dapat memberi hak view melalui matrix role bila perlu.
- SQL/runtime: migration managed `2026-09-06e_activity_audit_foundation`
  membuat `aud_access_event`, indeks, FK, page, sidebar, dan grant. Baseline
  clean-install, catalog, restore/rollback, health contract, dan policy count
  ikut diperbarui; tidak ada payload atau data transaksi yang dimigrasikan.
- Staging apply (Batch 184): migration runner policy `upgrade` menghasilkan
  **applied 1, skipped 9**. Tabel `aud_access_event`, page registry, menu
  sidebar, serta grant view SUPERADMIN masing-masing ada satu dan terverifikasi
  melalui query metadata-only.
- Risiko sisa/batch berikutnya: page view sebelum migration tidak dapat
  direkonstruksi. Akses beberapa halaman sebagai user SUPERADMIN dan lakukan
  satu transaksi untuk memverifikasi menu, RBAC, event baru, serta korelasinya.
- Validasi akhir: PHP lint semua file PHP baru/berubah, migration catalog
  validate, smoke registry 12/12, clean-install baseline 18/18, catalog 38/38,
  legacy guard 19/19, dan quality gate `parallel` lulus (required 74/74,
  development 4/4, release 1/1, preflight 1/1). Migration staging kini sudah
  diterapkan melalui runner resmi; credential tetap tidak dicetak, disalin, atau
  dimasukkan ke konfigurasi aplikasi.

## Batch 184 — Riwayat harga dari Purchase yang sudah lunas

- Waktu/tanggal: 2026-09-06.
- Prioritas: A2 / `AUD-A2-PUR-02`. Operasional Finance tidak menggunakan
  Receipt Purchase sebagai tahapan wajib. Ketentuan Batch 182 yang hanya
  membaca receipt `POSTED` membuat PO berstatus `PAID`, termasuk TISSUE POP UP,
  tidak muncul di riwayat harga.
- Perubahan utama: line pada PO `PAID` kini menjadi sumber riwayat harga per
  profil item. Jika line yang sama memiliki receipt `POSTED`, receipt tetap
  dipilih karena memakai kuantitas aktual dan baris PO ditekan agar tidak
  duplikat. Ledger lama tanpa receipt line tetap fallback. Tidak ada stok, HPP
  live, pembayaran, purchase, atau data historis yang ditulis/diubah.
- File berubah: `Purchase_model.php`, view riwayat harga, smoke riwayat harga,
  roadmap audit induk, dan log eksekusi ini.
- Validasi: PHP lint tiga file PHP lulus; smoke riwayat harga **11/11**;
  `git diff --check`; dan quality gate `parallel` lulus (required **74/74**,
  development **4/4**, release **1/1**, preflight **1/1**).
- Risiko sisa/batch berikutnya: UAT browser TISSUE POP UP dan satu PO `PAID`
  lain diperlukan untuk membuktikan data staging. Harga hanya tetap aman
  karena pencocokan menggunakan ID profil item, bukan sekadar nama bahan.

## Batch 185 — Upload logo pada Tampilan Umum Printer POS

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1/A5 / `AUD-A1-PRINT-01`. Halaman
  `POS → Printer → Tampilan Umum` hanya menyediakan URL logo, sehingga customer
  tidak mempunyai cara yang jelas dan aman untuk mengganti logo struk.
- Perubahan utama: UI kini menampilkan logo aktif serta input unggah PNG/JPG
  (maksimum 1 MB, 2048 × 2048). Logo tersimpan di
  `assets/uploads/pos-printer-logo/` dengan nama acak; tanpa file baru,
  konfigurasi logo lama tetap dipakai. Form save memakai CSRF scoped. Model
  hanya menerima path logo aplikasi yang tervalidasi; URL luar/legacy yang
  tidak aman kembali ke logo bawaan. Printer Agent tidak lagi mendapat marker
  URL eksternal dari konfigurasi ini. File logo lama tidak dihapus.
- File berubah: `Pos.php`, `Pos_print_model.php`,
  `PosPrinterPreviewService.php`, view Printer General, smoke upload logo, dan
  manifest/contract quality gate.
- SQL/runtime: tidak ada migration atau SQL. Penggantian logo cukup dilakukan
  dari UI oleh user dengan hak **edit** Printer General.
- Validasi: `php -l` seluruh file PHP berubah; smoke logo 10/10; contract
  quality gate 27/27; `git diff --check`; dan quality gate `parallel` lulus
  (required **75/75**, development **4/4**, release **1/1**, preflight **1/1**).
- Risiko sisa/batch berikutnya: lakukan UAT browser dengan satu PNG nyata,
  lalu test cetak dari web dan APK/printer fisik. APK harus memakai
  `print_segments` yang sudah disediakan API agar gambar benar-benar dicetak.
  Lifecycle service, pairing, installer, rotasi secret, dan UAT fisik tetap
  merupakan pekerjaan P0-09 terpisah.

## Batch 185A — Perbaikan akses folder dan preview logo Printer General

- Waktu/tanggal: 2026-09-06.
- Masalah: PHP-FPM berjalan sebagai `www`, sedangkan induk
  `assets/uploads/` saat ini dimiliki `root:root` mode `0775`. Ia tidak dapat
  membuat subfolder baru sehingga upload menampilkan pesan folder tidak dapat
  disiapkan. Preview juga baru merefleksikan logo tersimpan setelah reload.
- Perubahan utama: folder runtime
  `assets/uploads/pos-printer-logo/` disiapkan sebagai `www:www` mode `0775`
  dan dibuktikan writable dari user PHP-FPM. UI sekarang membuat preview lokal
  langsung saat PNG/JPG dipilih, mengembalikan preview logo aktif jika pilihan
  dibatalkan, serta melepas object URL browser saat diganti/halaman ditutup.
- SQL/runtime: tidak ada SQL. Pada server/customer baru, deployer harus
  menyiapkan folder yang sama writable oleh user PHP-FPM; source aplikasi tidak
  boleh mengandalkan kemampuan membuat folder dari parent yang root-owned.
- Validasi: permission dicek langsung sebagai user `www`; lint PHP, smoke
  upload logo, dan quality-gate contract dijalankan ulang.

## Batch 186 — Reauth Tutup Kasir POS Mobile yang terikat sesi

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1/A5 / `AUD-A1-POS-01`, `AUD-A1-STEP-01`, dan
  `AUD-A5-MIG-01`. Menutup kasir mengunci shift dan kas; sebelumnya bearer APK
  hanya membutuhkan token aktif serta izin edit sehingga perangkat yang
  ditinggalkan berpotensi menutup sesi kasir aktif.
- Perubahan utama: endpoint verify baru menerima password hanya untuk membuat
  proof acak satu-kali selama 180 detik. Proof yang disimpan hanya hash dan
  terikat pada token, user, terminal, aksi `CASHIER_CLOSE`, serta ID sesi kasir
  tepatnya. Endpoint close mengonsumsi proof atomik sebelum daily recon,
  writer, dan cetak; proof tidak diteruskan ke model. Kontrak bootstrap APK
  menjadi versi 2 dan mengumumkan route/method aksi baru tanpa secret.
- SQL/runtime: `2026-09-06f_pos_mobile_cashier_close_step_up.sql` diterapkan
  melalui migration runner policy `upgrade` di staging: pertama `applied 1,
  skipped 10`; replay `applied 0, skipped 11`. Migration hanya menambah kolom
  target sesi, index konsumsi, dan enum proof; tidak mengubah order, kas,
  pembayaran, stok, HPP, atau data historis. Baseline, catalog checksum,
  restore drill, health check, dan legacy guard ikut diselaraskan menjadi 12
  migration managed.
- Validasi: lint PHP; smoke authorization POS Mobile, session binding, dan
  proof reversal/reauth seluruhnya lulus (termasuk 29/29 smoke proof); validate
  runner; query metadata staging untuk kolom/index/enum/ledger; health
  post-install, catalog, baseline fingerprint, legacy inventory, dan restore
  drill lulus. Quality gate parallel lulus required 75/75, development 4/4,
  release 1/1, dan preflight 1/1 sebelum Batch 187 menambah guard inbox.
- Risiko sisa/batch berikutnya: APK wajib mengadopsi kontrak versi 2: verify
  password lalu kirim `step_up_proof` saat Tutup Kasir. UAT perangkat nyata
  dan keputusan aksi mobile sensitif lain tetap diperlukan.

## Batch 187 — Guard inbox Reservasi, Self Order, dan Online Food POS Mobile

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1 / `AUD-A1-POS-01`. Endpoint inbox sudah dijaga di source,
  tetapi belum memiliki regression khusus yang membuktikan urutan POST,
  token, RBAC, outlet, dan writer.
- Perubahan utama: reservation ID yang tidak ada kini mengembalikan 404 dan
  berhenti sebelum writer. Smoke baru mengunci route serta urutan guard untuk
  verify/reject Reservasi, Self Order, dan Online Food; detail inbox dibuktikan
  memeriksa outlet dokumen kanonis sebelum mengirim payment/refund/void. Tidak
  ada perubahan status order, stock commit, kas, HPP, data, atau kontrak APK.
- A0 terkait: clone Git telah diubah dari shallow menjadi full history dengan
  `git fetch --unshallow origin`, tanpa merge/reset/commit/push. Dua orphan
  lokal kini dipertahankan sebagai ref `recovery/orphan-update-20260902` dan
  `recovery/orphan-backup-20260903`. Remote divergen dan memuat backup runtime;
  tidak diintegrasikan otomatis.
- Validasi: lint controller dan test; smoke inbox 15/15; contract quality gate
  27/27; `git diff --check` lulus. Quality gate penuh kemudian lulus pada
  Batch 189.
- Risiko sisa/batch berikutnya: merge/push remote membutuhkan keputusan
  integrasi terpisah. UAT APK/browser/printer fisik, baseline izin per jabatan,
  MFA, rollout UI, dan keputusan data historis tidak dapat disimpulkan dari
  smoke source.

## Batch 188 — Menutup baseline static analysis dan guard mutasi Landing Page

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1/A4 / `AUD-A1-LANDING-01` dan `AUD-A4-TEST-01`. Landing Page
  memiliki writer konfigurasi/konten yang ber-RBAC, tetapi belum memaksa method
  dan CSRF scoped. Static analysis juga masih menyimpan baseline lama yang
  menyamarkan error historis.
- File berubah: controller dan view Landing Page; stubs/static policy A4;
  beberapa source yang mempunyai error statis nyata; smoke Landing Page dan
  browser; roadmap serta log ini.
- Perubahan utama: 18 writer Landing Page kini wajib `POST` dan token CSRF
  scoped yang dikirim form maupun AJAX. Token memakai random 256-bit session,
  dibandingkan constant-time, dan request tidak sah berhenti sebelum writer.
  PHPStan kini baseline 0; error nyata pada helper JSON Landing Page, variabel
  WhatsApp, key duplikat Purchase Model, serta BOM view diperbaiki. Browser
  smoke dibuktikan terhadap loopback runtime aktual.
- Validasi: lint PHP file berubah; smoke Landing Page 24/24; browser runtime;
  static analysis baseline 0; `git diff --check`; dan profile release lulus.
- Risiko sisa/batch berikutnya: tidak ada data atau schema yang diubah. UAT
  editor Landing Page dan perangkat nyata tetap terpisah.

## Batch 189 — Quality gate staging penuh dan contract CLI staging

- Waktu/tanggal: 2026-09-06.
- Prioritas: A0/A4/A5. Dua probe database read-only sebelumnya gagal saat
  dijalankan CLI karena proses CLI tidak otomatis menerima environment PHP-FPM
  staging, bukan karena invariant database gagal.
- File berubah: `tools/tests/finance_quality_gate.php`, contract smoke quality
  gate, roadmap, dan log ini.
- Perubahan utama: manifest probe staging menetapkan `CI_ENV=staging` secara
  eksplisit pada child process sambil mewarisi environment proses. Dengan itu
  probe memakai file konfigurasi privat staging yang sama tanpa mencetak atau
  memasukkan credential ke source, command, atau log.
- Validasi: lint dua file; contract quality gate 28/28; A2 database invariant
  read-only lulus; RBAC scope staging lulus; kemudian profile `staging` penuh
  lulus: required 77/77, development 4/4, release 1/1, runtime 2/2,
  preflight 1/1, security 1/1, static 1/1, staging probe 3/3.
- Risiko sisa/batch berikutnya: gate otomatis tidak menggantikan UAT browser
  berbasis role, APK/device, printer fisik, atau keputusan integrasi Git dan
  release customer. Tidak ada SQL atau data bisnis yang diubah oleh Batch 188–189.

## Batch 190 — Reauth pengembalian DP Reservasi POS Mobile

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1/A5 / `AUD-A1-POS-01`, `AUD-A1-STEP-01`, dan `AUD-A5-MIG-01`.
  Penolakan reservasi dari APK telah memiliki POST, bearer, RBAC, dan scope
  outlet, tetapi opsi `refund_deposit` dapat menjalankan pengembalian DP tanpa
  reauth tambahan.
- Perubahan utama: hanya pengembalian DP saat menolak reservasi yang sekarang
  memerlukan password verify lebih dahulu. Verify menghasilkan proof acak
  satu-kali 180 detik; yang tersimpan hanya hash dan proof terikat token, user,
  terminal, aksi `RESERVATION_DEPOSIT_REFUND`, serta ID reservasi tepatnya.
  Writer mengonsumsi proof secara atomik sebelum penolakan/pengembalian DP dan
  membuang proof dari payload model. Penolakan reservasi tanpa pengembalian DP
  mempertahankan alur APK sebelumnya. Kontrak bootstrap APK menjadi versi 3.
- File berubah: `Pos_mobile.php`, `routes.php`, migration
  `2026-09-06g_pos_mobile_reservation_refund_step_up.sql`, baseline dan policy
  migration, contract restore/health/catalog, smoke proof POS Mobile, quality
  gate, roadmap audit induk, serta log ini.
- SQL/runtime: migration dikelola runner resmi dan telah diterapkan di staging
  dengan policy `upgrade`: pertama `applied 1, skipped 11`; replay `applied 0,
  skipped 12`. Ia hanya menambah `reservation_id`, enum aksi proof, dan index
  konsumsi; tidak mengubah DP, reservasi, order, pembayaran, stok, HPP, atau
  data historis.
- Validasi: lint `Pos_mobile.php` dan routes lulus; smoke proof Refund DP
  14/14 dan proof reversal POS Mobile 29/29; `git diff --check`; serta quality
  gate `staging` penuh lulus: required 78/78, development 4/4, release 1/1,
  runtime 2/2, preflight 1/1, security 1/1, static 1/1, dan probe read-only
  staging 3/3.
- Risiko sisa/batch berikutnya: APK harus membaca capability bootstrap versi 3
  dan menjalankan verify password lalu mengirim `step_up_proof` ketika user
  memilih pengembalian DP. APK lama hanya akan menerima `428 step_up_required`
  pada aksi sempit itu; penolakan tanpa refund tetap berjalan. UAT perangkat
  nyata, browser berbasis role, dan printer fisik masih diperlukan.

## Batch 191 — Reauth refund DP Reservasi POS web

- Waktu/tanggal: 2026-09-06.
- Prioritas: A1 / `AUD-A1-STEP-01`. POS web sudah memiliki CSRF dan permission
  untuk menolak atau membatalkan reservasi, tetapi ketika opsi “kembalikan DP”
  dipilih, writer dapat menjalankan `void_deposit` tanpa reauth tambahan.
- Perubahan utama: hanya cabang refund DP kini membutuhkan password saat ini.
  Password dikirim hanya ke endpoint verify CSRF-scoped, dibersihkan dari UI,
  lalu berubah menjadi proof hash satu-kali 180 detik dalam session. Proof
  terikat user, reservasi, serta aksi yang berbeda antara **tolak** dan
  **batalkan**; proof refund order tidak dapat dipakai. Writer mengonsumsi proof
  sebelum model dan tidak meneruskan password/proof ke model. Penolakan atau
  pembatalan tanpa refund DP tetap memakai alur sebelumnya.
- File berubah: `Pos.php`, `SensitiveActionStepUp.php`, routes, view Reservasi,
  smoke reauth refund reservasi web, smoke transaksi POS, quality gate,
  roadmap audit induk, dan log ini.
- SQL/runtime: tidak ada SQL, migration, atau perubahan data. Tidak ada order,
  DP, pembayaran, stok, HPP, atau jurnal yang diubah oleh batch ini.
- Validasi: lint PHP seluruh file berubah; smoke refund reservasi web 13/13;
  behavioral/source smoke CSRF transaksi POS 1.770 check; smoke reversal POS;
  contract quality gate 28/28; `git diff --check`; dan quality gate `staging`
  penuh lulus: required 79/79, development 4/4, release 1/1, runtime 2/2,
  preflight 1/1, security 1/1, static 1/1, serta probe read-only staging 3/3.
- Risiko sisa/batch berikutnya: UAT browser nyata perlu mencoba empat kondisi:
  tolak tanpa refund, batalkan tanpa refund, tolak+refund, dan batalkan+refund;
  proof salah/kedaluwarsa harus mendapat `428`. Baseline izin jabatan, MFA,
  UAT APK/perangkat/printer, dan aksi mobile lain tetap terbuka.

## Batch 192 — Roastery Label Studio: template, elemen, dan cetak seragam

- Waktu/tanggal: 2026-09-06.
- Prioritas: penyempurnaan `/roastery/packaging-labels`. Sebelumnya Model 1 dan
  Model 2 memakai renderer/editor cetak yang berbeda; template tidak dapat
  disimpan sebagai aset yang dipakai ulang, dan preview note hanya menampilkan
  tiga entri walaupun operator mengisi lebih banyak.
- Perubahan utama: kedua model lama dipertahankan sebagai template awal
  **Classic Portrait** dan **Retail Wide**, tetapi seluruh template kini memakai
  satu kanvas, satu daftar elemen, serta satu mesin preview/cetak. Operator dapat
  drag elemen langsung di kanvas, menampilkan/menyembunyikan tiap elemen, mengatur
  ukuran label/kertas/jumlah label/margin/gap untuk template mana pun, lalu
  menyimpan desain aktif sebagai template kustom. Semua tasting note yang diisi
  (termasuk lima note) dirender di preview dan ikut disalin ke sheet cetak yang
  sama.
- File berubah: `Roastery.php`, `Coffee_packaging_label_model.php`, routes,
  view Label Studio, migration `2026-09-06h_roastery_label_template_studio.sql`,
  baseline/policy/catalog migration dan kontrak release, roadmap register SQL,
  serta smoke test Label Studio.
- SQL/runtime: migration resmi `2026-09-06h` telah dijalankan di staging dengan
  policy `upgrade`: `applied 1, skipped 12`. Ia membuat tabel
  `coffee_packaging_label_template` dan seed dua template sistem; tidak mengubah
  atau menghapus label, produk, stok, HPP, order, maupun transaksi historis.
  Probe read-only setelahnya membuktikan dua template aktif tersedia.
- Validasi: lint semua PHP yang berubah; smoke Label Studio 9/9; migration
  catalog/baseline/health/restore/rollback contracts; roadmap consistency 22/22;
  `git diff --check`; lalu quality gate profile `release` lulus penuh: required
  80/80, development 4/4, release 1/1, runtime 2/2, preflight 1/1, security
  1/1, dan static 1/1.
- Risiko sisa/batch berikutnya: perlu UAT visual operator pada browser nyata
  untuk beberapa ukuran kertas/printer fisik, terutama bila artwork atau SVG
  eksternal dipakai. Perpindahan template dengan perubahan yang belum disimpan
  sengaja memuat ulang editor; operator perlu menyimpan label lebih dahulu jika
  ingin mempertahankan modifikasi saat itu.

## Batch 193 — Pemilih template Label Studio yang ringkas

- Waktu/tanggal: 2026-09-06.
- Prioritas: menyederhanakan pemilihan template di `/roastery/packaging-labels`
  agar tidak lagi memakai kartu-kartu lebar yang menghabiskan area editor.
- Perubahan utama: template kini dipilih dari satu dropdown di bagian paling
  atas editor. **Default — Classic Portrait** selalu berada di urutan pertama,
  disusul Retail Wide dan template operator. Penyimpanan template kini membuka
  dialog yang jelas dengan kolom **Nama template** wajib; prompt browser lama
  telah dihapus. Dua template sistem yang sudah ada telah memiliki nama, jadi
  tidak ada data template yang perlu diperbaiki.
- File berubah: `Coffee_packaging_label_model.php`, view Label Studio, smoke
  test Label Studio, dan log ini.
- SQL/runtime: tidak ada SQL, migrasi, atau perubahan data. Tabel dan template
  yang dibuat Batch 192 tetap dipakai apa adanya.
- Validasi: lint PHP file berubah, smoke Label Studio, contract quality gate,
  roadmap consistency, dan `git diff --check` pada perubahan batch ini.
- Risiko sisa/batch berikutnya: operator perlu menyimpan label sebelum mengganti
  dropdown template bila perubahan editor saat ini ingin dipertahankan. UAT
  browser/perangkat dan printer fisik tetap diperlukan untuk desain akhir.

## Batch 194 — A3.2 Riwayat Harga Item: state dan pagination operasional

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-02`. Halaman Riwayat Harga Item sudah membaca PO `PAID`
  dengan benar, tetapi belum mempunyai state awal yang jelas, retry yang seragam,
  atau cara melihat transaksi lama tanpa memilih batas data lebih besar dan
  memuat ulang seluruh halaman.
- Perubahan utama: endpoint read-only menerima offset yang dibatasi dan mengirim
  `has_more`. UI kini memiliki state pilih item, memuat, kosong, dan gagal yang
  jelas, tombol coba lagi, pembatalan request lama agar hasil pencarian/item
  sebelumnya tidak menimpa pilihan terbaru, serta tombol “Muat transaksi
  berikutnya” responsif. Grafik dan tabel menggunakan kumpulan transaksi yang
  sama setelah halaman tambahan dimuat.
- File berubah: `Purchase.php`, `Purchase_model.php`, view Riwayat Harga Item,
  smoke riwayat harga, roadmap audit, dan log ini.
- SQL/runtime: tidak ada SQL, migration, writer, atau perubahan data. Query
  tetap hanya membaca sumber receipt POSTED, PO PAID, dan ledger historis.
- Validasi: PHP lint file berubah; smoke riwayat harga 12/12; contract quality
  gate 28/28; roadmap consistency 22/22; `git diff --check` pada scope batch;
  serta quality gate `parallel` lulus dengan required 80/80, development 4/4,
  dan release contract 1/1.
- Risiko sisa/batch berikutnya: UAT browser nyata perlu mencoba item tanpa
  riwayat, item dengan lebih dari satu halaman, mode HPP/Harga Pack, dan kondisi
  koneksi gagal. Migrasi wave A3-UI-02 baru mencakup Master Component serta
  Riwayat Harga; halaman operasional lain tetap bertahap.

## Batch 195 — A3.2 Mutasi Stok Divisi: pagination server-side

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-02`. Halaman Mutasi Stok Divisi sebelumnya selalu
  mengambil hingga 500 log ke PHP/browser lalu memotong array untuk pagination.
  Ringkasan dan filter tipe juga hanya mencerminkan data yang sudah terambil.
- Perubahan utama: controller kini meminta `per_page + 1` log saja dengan offset
  terikat, membuang baris sentinel, dan menerbitkan `has_more` untuk navigasi
  Sebelumnya/Berikutnya. View merender langsung halaman dari server, memberi
  status saat filter/halaman dimuat, state kosong yang membantu operator kembali
  ke filter benar, serta navigasi yang aksesibel. KPI dan ringkasan tipe diberi
  label tegas sebagai **halaman aktif**, sehingga tidak mengklaim agregat global.
- File berubah: `Purchase.php`, `Purchase_model.php`, view Mutasi Stok Divisi,
  smoke pagination baru, manifest/contract quality gate, roadmap audit, dan log
  ini.
- SQL/runtime: tidak ada SQL, migration, writer, atau perubahan data. Endpoint
  tetap menggunakan pembacaan `inv_stock_movement_log` saja.
- Validasi: PHP lint file berubah; smoke pagination Mutasi Stok Divisi 5/5;
  smoke A3 54/54 dan A2 matrix lulus; contract quality gate 28/28; `git diff
  --check`; serta quality gate `parallel` lulus dengan required 81/81,
  development 4/4, dan release contract 1/1.
- Risiko sisa/batch berikutnya: UAT browser perlu mencoba page pertama/akhir,
  halaman kosong setelah filter berubah, pencarian otomatis, serta 10/25/200
  baris per halaman. Filter tipe global dapat ditambah sebagai batch terpisah
  bila operator memerlukannya; batch ini sengaja tidak mengubah atau menyaring
  data historis di luar halaman aktif.

## Batch 196 — A3.4 POS Reservasi web: state daftar yang aman

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-04`. Daftar Reservasi POS web telah mempunyai filter
  dan pagination, tetapi saat jaringan lambat/gagal operator hanya mendapat
  toast; hasil request lama juga berpotensi menimpa filter atau tab terbaru.
- Perubahan utama: daftar kini menunjukkan state **memuat**, **kosong**, dan
  **gagal** yang jelas. State gagal menyediakan tombol coba lagi; hasil kosong
  menjelaskan filter yang perlu diperiksa dan menyediakan reset filter. Request
  daftar sebelumnya dibatalkan dan hasilnya diabaikan saat operator mengganti
  tab/filter/halaman. Pagination mendapat label aksesibel dan status live.
- File berubah: view `pos/reservation_index.php`, smoke baru
  `a3_pos_reservation_ui_smoke.php`, smoke CSRF POS yang menyesuaikan helper
  pembacaan opsional, manifest/contract quality gate, roadmap audit, dan log
  ini.
- SQL/runtime: tidak ada SQL, migration, schema, query writer, atau perubahan
  data staging. `Pos_mobile.php`, route APK, model reservasi, DP, pembayaran,
  stok, HPP, serta jurnal tidak disentuh.
- Validasi: lint seluruh PHP berubah; smoke UI Reservasi 8/8; smoke reauth
  refund DP Reservasi 13/13; smoke CSRF transaksi POS 1.770 check; contract
  quality gate 28/28; roadmap consistency 22/22.
- Review: PASS. Diff dibatasi pada pembacaan daftar dan kontrak test;
  guard CSRF/reauth refund DP masih lulus. Tidak ada panggilan writer dari
  fungsi refresh daftar.
- Risiko sisa/batch berikutnya: perlu UAT browser pada koneksi lambat/gagal,
  pindah cepat tab/filter, hasil kosong, serta desktop/mobile. Subwave berikut
  A3-UI-04 adalah kasir POS web, self-order, dan online-food; POS Mobile/APK
  tetap tidak disentuh kecuali ada kebutuhan eksplisit.

## Batch 197 — A3.4 Kasir POS web: daftar order aktif yang tahan filter cepat

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-04`. Panel **Order Aktif Sesi Ini** langsung memuat
  setiap ketikan dan menggunakan alert saat gagal, sehingga hasil lama dapat
  muncul setelah filter terbaru dan mengganggu operator di layar kasir.
- Perubahan utama: pencarian kini debounce 250 ms. Permintaan daftar lama
  dibatalkan serta hasilnya diabaikan saat status/kata kunci/batas baris berubah.
  Panel memberi state memuat, kosong dengan tombol bersihkan filter, dan gagal
  dengan tombol coba lagi; status serta busy state dapat dibaca pembaca layar.
- File berubah: view `pos/cashier_index.php`, smoke baru
  `a3_pos_cashier_recent_ui_smoke.php`, manifest/contract quality gate,
  roadmap audit, dan log ini.
- SQL/runtime: tidak ada SQL, migration, schema, model, controller, writer,
  atau data staging yang diubah. Perhitungan order, pembayaran, void, cetak,
  stok, HPP, jurnal, route, dan `Pos_mobile.php` tidak disentuh.
- Validasi: lint PHP; smoke daftar Order Aktif Kasir 9/9; smoke CSRF transaksi
  POS 1.770 check; smoke handoff APK/web 28/28; contract quality gate 28/28.
- Review: PASS. Diff hanya memperluas helper pembacaan agar mendukung cancel
  request dan memperbarui panel daftar order. Refresh daftar tetap tidak punya
  panggilan `postJson` maupun `postPosTransactionJson`.
- Risiko sisa/batch berikutnya: UAT browser perlu menguji ketik cepat, tab
  Draft/Confirmed/Semua, kondisi jaringan gagal, order kosong, dan pemilihan
  order setelah refresh. Subwave A3-UI-04 berikutnya adalah self-order dan
  online-food web; POS Mobile/APK tetap terisolasi.

## Batch 198 — A3.4 Self Order web: daftar order yang tahan filter cepat

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-04`. Daftar Self Order sudah mempunyai filter, summary,
  pagination, serta polling notifikasi, tetapi pencarian memanggil server setiap
  ketikan dan error baca daftar dibuka sebagai modal yang mengganggu kasir.
- Perubahan utama: pencarian diberi debounce 250 ms. Request daftar yang lama
  dibatalkan dan hasil stale diabaikan saat filter/tab/halaman berubah. Tabel
  kini punya state memuat, kosong dengan reset filter, gagal dengan coba lagi,
  busy state, serta pagination yang lebih aksesibel. Polling order baru tetap
  berjalan sesuai interval sebelumnya dan hanya meminta refresh daftar read-only.
- File berubah: view `pos/self_order_orders.php`, smoke baru
  `a3_pos_self_order_ui_smoke.php`, manifest/contract quality gate, roadmap
  audit, dan log ini.
- SQL/runtime: tidak ada SQL, migration, schema, model, controller, writer,
  atau data staging yang diubah. Verifikasi, penolakan, pembayaran, printer,
  runtime stock sync, HPP, stok, route, dan POS Mobile/APK tidak disentuh.
- Validasi: lint PHP; smoke UI Self Order 10/10; CSRF transaksi POS 1.770
  check; runtime sync CSRF 19/19; runtime-job binding 247/247; contract quality
  gate 28/28.
- Review: PASS. Refresh daftar hanya memanggil endpoint data `GET`; tidak
  mempunyai `postJson` maupun `postPosTransactionJson`. Kontrak CSRF writer
  dan runtime sync tetap lulus.
- Risiko sisa/batch berikutnya: UAT browser perlu mencoba ketik cepat,
  perubahan mode pembayaran/status/outlet/tanggal, pagination, daftar kosong,
  jaringan gagal, notifikasi order baru, serta alur verifikasi dan penolakan.
  Subwave terakhir A3-UI-04 adalah Online Food web; POS Mobile/APK tetap
  terisolasi.

## Batch 199 — A3.4 Online Food web: daftar order yang tahan filter cepat

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-04`. Daftar Online Food memiliki filter, summary,
  pagination, dan polling order baru, tetapi pencarian masih mengirim request
  pada setiap ketikan; kegagalan pembacaan daftar juga membuka modal yang
  mengganggu fokus operator.
- Perubahan utama: pencarian memakai debounce 250 ms. Request daftar lama
  dibatalkan dan hasil stale diabaikan saat filter, tab, atau halaman berubah.
  Daftar menampilkan state memuat, kosong dengan reset filter, gagal dengan
  tombol coba lagi, busy state, serta pagination yang lebih aksesibel. Polling
  notifikasi tetap berjalan dan hanya memicu refresh daftar read-only.
- File berubah: view `pos/online_food_orders.php`, smoke baru
  `a3_pos_online_food_ui_smoke.php`, manifest/contract quality gate, roadmap
  audit, dan log ini.
- SQL/runtime: tidak ada SQL, migration, schema, model, controller, writer,
  atau data staging yang diubah. Verifikasi, penolakan, pembayaran, printer,
  runtime stock sync, HPP, stok, route, dan POS Mobile/APK tidak disentuh.
- Validasi: lint PHP; smoke UI Online Food 10/10; smoke tiga halaman POS web
  sebelumnya (Self Order 10/10, Kasir 9/9, Reservasi 8/8); CSRF transaksi POS
  1.770 check; runtime sync CSRF 19/19; runtime-job binding 247/247; contract
  quality gate 28/28; konsistensi roadmap 22/22; A4 release preflight PASS
  (0 temuan).
- Review: PASS. Refresh daftar hanya memanggil endpoint data `GET`; tidak
  memiliki `postJson` atau `postPosTransactionJson`. Guard CSRF writer dan
  runtime sync tetap lulus. Rollout kode `A3-UI-04` kini lengkap untuk empat
  daftar POS web yang menjadi scope wave ini.
- Risiko sisa/batch berikutnya: lakukan UAT browser desktop/mobile pada filter
  cepat, perubahan tab/outlet/tanggal, pagination, daftar kosong, koneksi
  gagal, notifikasi order baru, lalu verifikasi/penolakan/pembayaran sesuai
  hak akses. Berikutnya adalah `AUD-A3-UI-05` secara kecil dan terarah pada
  inventory/production; POS Mobile/APK tetap diisolasi kecuali diminta.

## Batch 200 — A3.5 Stok Komponen: pagination server-side yang jujur

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Opsi **Per Halaman** pada Stok Base/Prepare hanya
  menyembunyikan baris setelah urutan awal di browser. Operator tidak dapat
  membuka baris berikutnya, sedangkan ringkasan dan hasil filter berisiko
  dibaca sebagai seluruh data.
- Perubahan utama: controller menormalkan nomor halaman, model menghitung
  total hasil filter lalu memilih hanya halaman yang diminta sebelum memuat
  rincian lot. View sekarang mempunyai navigasi Sebelumnya/Berikutnya yang
  aksesibel dan menampilkan rentang data yang benar. KPI (jumlah komponen,
  BASE/PREPARE, minus, nol, dan nilai) tetap memakai total semua hasil filter,
  bukan hanya baris halaman aktif. Pilihan **Semua** tetap tersedia secara
  eksplisit.
- File berubah: `Production.php`, `Production_model.php`, view
  `production/component_stock_index.php`, smoke baru
  `a3_component_stock_pagination_smoke.php`, manifest/contract quality gate,
  roadmap audit, dan log ini.
- SQL/runtime: tidak ada SQL, migration, schema, writer, koreksi stok, lot,
  HPP, transaksi produksi, route, atau data staging yang diubah. Endpoint data
  lama dan POS Mobile/APK tidak disentuh.
- Validasi: lint PHP untuk controller, model, view, dan smoke; smoke pagination
  Stok Komponen 9/9; contract quality gate 28/28. Preflight A4 dan konsistensi
  roadmap dijalankan kembali pada review akhir batch.
- Review: PASS secara source. Pagination lama berbasis hide/show browser sudah
  dihapus; detail lot kini dipasang setelah page slice sehingga halaman yang
  tidak dibuka tidak memuat kartu lotnya.
- Risiko sisa/batch berikutnya: UAT browser perlu mencoba filter, halaman
  terakhir, URL `page` sangat besar, perubahan Per Halaman, pilihan Semua, dan
  expand lot pada tiap halaman. Lanjutkan `AUD-A3-UI-05` pada satu halaman
  inventory/production berikutnya secara terarah.

## Batch 201 — A3.5 Kontrak daftar stok: Gudang, Bahan Baku, dan Komponen

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Ketiga halaman stok sudah menampilkan domain yang
  benar, tetapi pengalaman daftar berbeda: Gudang memakai limit tanpa halaman,
  Bahan Baku punya pagination yang belum aksesibel sepenuhnya, dan Komponen
  baru saja memperoleh pagination server-side.
- Perubahan utama: Gudang kini mengelompokkan profil per item lalu menampilkan
  halaman server-rendered dengan pilihan 25/50/100/200 baris, ringkasan dari
  semua hasil filter, rentang item, serta tombol Sebelumnya/Berikutnya. Bahan
  Baku memakai pilihan ukuran halaman yang sama dan navigasi aksesibel
  (`aria-current`, tombol nonaktif yang bukan tautan). Komponen memperoleh
  empty state yang sama jelasnya. Struktur kolom, istilah domain, dan detail
  lot tetap dipertahankan agar tidak menyamarkan perbedaan bisnis ketiganya.
- File berubah: `Purchase.php`, view `purchase/stock_warehouse_index.php`,
  view `purchase/stock_division_index.php`, view
  `production/component_stock_index.php`, smoke baru
  `a3_stock_list_consistency_smoke.php`, manifest/contract quality gate,
  roadmap audit, dan log ini.
- SQL/runtime: tidak ada SQL, migration, schema, model writer, adjustment,
  koreksi saldo, lot, HPP, transaksi, route, atau data staging yang diubah.
  Gudang tetap membaca jendela source yang dibatasi 2.000 profil sebelum
  pengelompokan agar satu item tidak terpecah antarhalaman; batas ini perlu
  ditinjau hanya bila instalasi customer melampauinya.
- Validasi: lint PHP; smoke konsistensi daftar stok 7/7; pagination Stok
  Komponen 9/9; pagination mutasi divisi 5/5; riwayat harga purchase 12/12;
  CSRF transaksi POS 1.770 check; contract quality gate 28/28. Konsistensi
  roadmap, release preflight, dan diff check dijalankan pada review akhir.
- Review: PASS. Semua perubahan berada pada reader/controller view dan markup
  navigasi; tidak terdapat pemanggilan writer pada jalur daftar Gudang maupun
  Bahan Baku.
- Risiko sisa/batch berikutnya: UAT browser pada filter/tanggal, halaman
  pertama/terakhir, ganti Per Halaman, expand profil/lot, hasil kosong,
  desktop/mobile, dan data lebih dari 2.000 profil gudang. Lanjutkan
  `AUD-A3-UI-05` ke halaman inventory/production lain setelah UAT ini.

## Batch 202 — A3.5 Kontrak periode stok dan Daily Matrix

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Pemeriksaan kode menemukan Stok Gudang dan Stok
  Bahan Baku Live menggunakan range tanggal pada reader snapshot bulanan.
  Range tersebut dapat menyembunyikan saldo yang tidak mempunyai aktivitas
  pada rentang itu. Daily Matrix juga memiliki Bulan dan range tanggal, tetapi
  range belum dipaksa berada dalam bulan yang terlihat.
- Perubahan utama: daftar stok live sekarang memakai **Bulan Snapshot**
  tunggal, sama dengan Stok Komponen. Reader bulanan tidak lagi memfilter saldo
  menurut `last_movement_date`; ia memilih snapshot terakhir sampai bulan
  terpilih. Daily Matrix tetap memiliki Mulai/Sampai Tampilan karena berguna
  untuk membaca sebagian hari, tetapi labelnya diperjelas dan UI, controller,
  serta model membatasi tanggal itu ke dalam bulan aktif. Mutasi stok tetap
  memakai range tanggal; rekonsiliasi tetap memakai cutoff tersendiri.
- File berubah: `Purchase.php`, `Purchase_model.php`, view Stok Gudang dan
  Bahan Baku Live, empat view Daily Matrix/snapshot, smoke baru
  `a3_stock_period_contract_smoke.php`, pembaruan smoke konsistensi daftar,
  manifest/contract quality gate, roadmap audit, dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query audit database,
  koreksi data, adjustment, saldo, lot, HPP, atau transaksi yang dijalankan.
  Perubahan hanya kontrak reader dan UI periode.
- Validasi: lint seluruh PHP yang berubah; contract periode stok 9/9;
  konsistensi daftar stok 7/7; contract quality gate 28/28. Regression
  inventory, purchase, roadmap, release preflight, dan diff check dijalankan
  pada review akhir.
- Review: PASS secara source. Stok live tidak lagi memakai filter aktivitas
  sebagai pengganti periode snapshot. Semua endpoint Daily Matrix yang relevan
  melewati guard rentang bulanan; model juga memiliki pertahanan kedua bila
  endpoint dipanggil langsung.
- Risiko sisa/batch berikutnya: UAT browser perlu mencoba bulan berbeda,
  range Daily Matrix sebelum/sesudah bulan aktif, bulan Februari, halaman
  kosong, serta URL lama yang masih membawa `date_from/date_to` pada stok
  live. Tahap lanjutan adalah merapikan satu shell kartu/filter bersama untuk
  rumpun stok, tanpa mengubah kolom domain atau proses bisnis.

## Batch 203 — A3.5 Shell card ringkasan stok yang seragam

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Setelah periode dan pagination diseragamkan,
  ringkasan Gudang, Bahan Baku, dan Komponen masih memakai tiga gaya card yang
  berbeda sehingga satu rumpun stok terlihat seperti halaman yang tidak saling
  terkait.
- Perubahan utama: dibuat partial read-only `_stock_summary_cards.php` sebagai
  satu shell responsif untuk label, nilai, tone informasi/peringatan, dan
  aksesibilitas ringkasan. Ketiga daftar stok live menggunakannya. Metrik
  tetap sesuai domain: Gudang (profil/item/qty/nilai), Bahan Baku
  (item/divisi/material/qty/nilai/perhatian), dan Komponen
  (base/prepare/nol/minus/nilai).
- File berubah: partial card ringkasan baru, tiga view daftar stok live,
  pembaruan smoke konsistensi daftar, roadmap audit, dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query audit database,
  perubahan data, saldo, lot, HPP, atau transaksi. Tidak ada controller/model
  writer atau POS Mobile/APK yang disentuh.
- Validasi: lint PHP; smoke konsistensi daftar 8/8; contract periode stok
  9/9. Contract quality gate, roadmap, release preflight, dan diff check
  dijalankan pada review akhir.
- Review: PASS. Partial hanya menerima label/nilai hasil reader yang sudah
  ada dan meng-escape seluruh teks; ia tidak menghitung ulang atau menulis
  stok. Tampilan tetap responsif pada layar kecil.
- Risiko sisa/batch berikutnya: UAT visual desktop/mobile perlu membandingkan
  tiga halaman stok pada nilai nol, kondisi minus, dan angka nominal panjang.
  Tahap berikutnya adalah menata filter shell/header secara sama tanpa
  menghapus perbedaan kolom dan proses bisnis tiap domain.

## Batch 204 — A3.5 Urutan dan warna ringkasan stok yang tegas

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Review visual menemukan kontrak Batch 203 masih
  terlalu umum: kartu Komponen tampil sebelum filter, sementara kartu KPI
  normal memakai warna biru/hijau/kuning yang berbeda-beda pada tiga halaman.
- Perubahan utama: standar ditulis eksplisit pada roadmap: **Header/Tab →
  Filter → Ringkasan hasil filter → Tabel/Pagination**. Kartu Komponen
  dipindahkan setelah filter. Partial ringkasan kini hanya netral atau merah;
  merah dipakai eksklusif saat stok minus/habis memerlukan tindakan.
- File berubah: partial ringkasan, tiga view daftar stok live, smoke
  konsistensi daftar, roadmap audit, dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query audit database,
  atau perubahan data stok, lot, HPP, transaksi, controller/model writer, dan
  POS Mobile/APK.
- Validasi: lint semua PHP yang berubah; smoke konsistensi daftar 9/9;
  contract periode stok 9/9; roadmap consistency 22 cek; quality-gate contract
  28/28; dan diff check.
- Review: **SUPERSEDED oleh Batch 205**. Susunan filter-kartu-tabel tetap
  benar, tetapi keputusan kartu netral tidak memenuhi standar visual operator;
  tidak ada reader, perhitungan KPI, izin, maupun data yang berubah.
- Risiko sisa/batch berikutnya: UAT browser desktop/mobile tetap diperlukan
  untuk angka panjang, hasil filter kosong, dan kondisi merah. Halaman
  inventory/production lain dimigrasikan per rumpun setelah standar ini stabil.

## Batch 205 — A3.5 Tab global dan kartu grafis stok

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Koreksi review UI: keseragaman harus berlaku
  untuk seluruh strip tab Gudang, Bahan Baku, dan Component, bukan hanya tiga
  daftar live. Kartu ringkasan juga harus memakai visual grafis seperti pola
  Bahan Baku, bukan kartu netral.
- Perubahan utama: partial ringkasan kini menjadi kartu gradien berikon dengan
  enam warna KPI tetap (violet, aqua, biru, amber, teal, merah alert). Tiga
  daftar live menerapkan urutan warna yang sama berdasarkan arti KPI. Partial
  tab Gudang/Bahan Baku serta Component (termasuk filter Base/Prepare) kini
  memakai strip responsif yang sama, active state eksplisit, fokus keyboard,
  dan scroll horizontal pada mobile; semua halaman yang memanggil partial ini
  ikut memperoleh tampilan tersebut.
- File berubah: partial ringkasan, tiga view daftar stok live, tiga partial
  tab, smoke konsistensi daftar, roadmap audit, dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query audit database,
  atau perubahan data stok, lot, HPP, transaksi, controller/model writer, dan
  POS Mobile/APK.
- Validasi: lint PHP seluruh file yang berubah; smoke konsistensi daftar;
  contract periode stok, roadmap, quality gate, dan diff check.
- Review: PASS. Tab memakai partial shared sehingga perubahan berlaku ke semua
  halaman pemakainya tanpa menduplikasi daftar route. Kartu hanya menerima
  label/nilai hasil reader yang sudah ada; tidak menulis atau menghitung ulang
  stok, lot, maupun HPP.
- Risiko sisa/batch berikutnya: halaman tab yang mempunyai kartu KPI khusus
  (misalnya adjustment, lot, dan daily matrix) masih membawa data/metriknya
  sendiri; migrasi visual kartu itu dilakukan per jenis halaman tanpa
  menyamakan metrik bisnis yang memang berbeda.

## Batch 206 — A3.5 Ringkasan tab snapshot bulanan

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Tiga tab snapshot bulanan masih menggunakan tiga
  bentuk KPI yang berbeda: Gudang berbentuk card polos, Bahan Baku memiliki
  CSS lokal, dan Component menampilkan ringkasan sebelum filter.
- Perubahan utama: Stok Bulanan/Snapshot Gudang, Bahan Baku, dan Component
  memakai partial kartu grafis yang sama. Metrik tetap utuh sesuai domain;
  detail pack/isi dipindahkan ke subteks kartu. Component Bulanan dipindahkan
  ke urutan Filter → Ringkasan → Tabel. Merah pada Bahan Baku/Component hanya
  aktif saat terdapat alert stok terkait.
- File berubah: tiga view snapshot bulanan, smoke konsistensi daftar, roadmap
  audit, dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query audit database,
  atau perubahan data stok, lot, HPP, transaksi, controller/model writer, dan
  POS Mobile/APK.
- Validasi: lint PHP file berubah; smoke konsistensi daftar; contract periode,
  roadmap, quality gate, serta diff check.
- Review: PASS. Ketiga halaman hanya meneruskan nilai ringkasan yang sudah
  dihitung view/reader; perubahan tidak menyentuh query, writer, stok, lot,
  HPP, maupun izin.
- Risiko sisa/batch berikutnya: Daily Matrix interaktif, Adjustment, Lot,
  Opname, dan Mutasi tetap memakai kartu detail khusus. Migrasi berikutnya
  perlu dikelompokkan per jenis tampilan agar kontrak AJAX/aksi tidak terganggu.

## Batch 207 — A3.5 Daily Matrix Gudang, Bahan Baku, dan Component

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Tiga Daily Matrix memiliki kontrak pembacaan
  berbeda, tetapi KPI-nya masih tidak konsisten: Gudang polos, Bahan Baku
  memakai palet lokal, dan Component memakai card sebelum filter.
- Perubahan utama: Gudang memakai kartu grafis dengan ID statistik AJAX tetap;
  Bahan Baku memakai palet/ikon seragam dan kartu alert berganti merah hanya
  ketika hasil AJAX mempunyai minus/habis; Component memakai partial bersama
  setelah filter. Tidak ada selector data, endpoint, payload, atau writer
  yang diubah.
- File berubah: tiga view Daily Matrix, smoke konsistensi daftar, roadmap
  audit, dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query audit database,
  atau perubahan data stok, lot, HPP, transaksi, controller/model writer, dan
  POS Mobile/APK.
- Validasi: lint PHP file berubah; smoke konsistensi daftar 13/13; contract
  periode stok 9/9. Quality gate/roadmap/diff akan diulang pada penutupan A3.
- Review: PASS. Kontrak AJAX Daily Matrix dipertahankan; pembaruan hanya CSS,
  markup kartu, dan class visual dari hasil yang sudah ada.
- Risiko sisa/batch berikutnya: lanjutkan Lot/FIFO, Opname, Mutasi, dan
  Adjustment sebagai rumpun operasional stok; tiap writer tetap diisolasi dari
  pekerjaan UI.

## Batch 208 — A3.5 Ringkasan tab operasional stok

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-UI-05`. Setelah daftar, snapshot, dan matrix ditata,
  ringkasan pada enam tab operasional masih polos/lokal atau berada sebelum
  filter.
- Perubahan utama: Mutasi Gudang, Audit FIFO, Opname Bahan Baku, Audit Lot,
  Mutasi Component, dan Lot Component memakai partial kartu grafis setelah
  filter. Nilai/detail asal dipertahankan; hanya wrapper visual dan urutan
  tampilan berubah.
- File berubah: enam view operasional stok, smoke konsistensi daftar, roadmap
  audit, dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query audit database,
  atau perubahan data stok, lot, HPP, transaksi, controller/model writer, dan
  POS Mobile/APK.
- Validasi: lint PHP file berubah. Smoke, roadmap, quality gate, dan diff
  akan dijalankan bersama penutupan rumpun ini.
- Review: PASS. Ringkasan dipindahkan sesudah filter dan seluruh angka masih
  berasal dari kalkulasi view yang sama; tidak ada query, writer, atau
  endpoint aksi yang berubah.
- Risiko sisa/batch berikutnya: Opname Gudang, Opname Component, Adjustment,
  Reconcile, Batch, dan Stock Opening memiliki panel detail/writer khusus;
  lanjutkan tanpa mengganti kontrak aksinya.

## Batch 209 — A3 workspace lintas rumpun dan cleanup UI

- Waktu/tanggal: 2026-09-06.
- Prioritas: memperluas `AUD-A3-IA-01` serta gelombang `AUD-A3-UI-05`–`09`
  tanpa mengubah transaksi bisnis atau kontrak APK.
- Ringkasan implementasi: dibuat primitive `layout/_workspace_tabs.php` dan
  style global untuk halaman yang berpindah URL: responsif, scroll horizontal
  pada layar kecil, active state, fokus keyboard, URL yang di-escape, dan
  `aria-current`. Finance, laporan Purchase, Absensi, Payroll/Kasbon/Bonus,
  Asset, Access Audit, Master Extra, dan Loyalty memakainya. Tab Bootstrap
  yang benar-benar mengganti pane pada halaman yang sama tetap dipertahankan.
- File berubah: `theme-custom.css`, partial workspace baru, partial navigasi
  Finance/Purchase/Asset/Loyalty/Master, view rumpun Finance/Purchase/People/
  Payroll/Asset/Access Audit, cleanup CSS stale Lot/Mutasi Component/Audit Lot,
  smoke `a3_workspace_navigation_smoke.php`, manifest quality gate, roadmap,
  dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, schema, query database, repair
  data, saldo, stok, lot, HPP, transaksi, writer, route, permission resolver,
  atau `Pos_mobile.php`/`Pos_model.php` yang diubah.
- Validasi: lint semua PHP yang diubah; smoke workspace A3; smoke daftar dan
  periode stok; smoke shell UI Finance; roadmap consistency; quality-gate
  parallel; diff check.
- Review: **CODE PASS.** Linked navigation tidak lagi menyamar sebagai
  Bootstrap in-page tab; navigasi dan query yang sudah ada diteruskan apa
  adanya. CSS stale dibuang hanya setelah source scan memastikan selector tidak
  dipakai lagi.
- Risiko sisa/batch berikutnya: visual UAT role utama desktop/mobile wajib
  mencoba tab panjang, layar sempit, angka panjang, hasil kosong, filter,
  pagination, dan aksi bisnis per rumpun. Itu bukti operasional, bukan alasan
  untuk mengubah data staging atau memodifikasi kontrak POS Mobile/APK.

## Batch 210 — A3 penataan sidebar berbasis tugas

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A3-IA-01` — akar sidebar masih memisahkan Kasir POS, Self
  Order, Reservasi, dan Laporan POS; SDM/payroll, Menu Book, serta WhatsApp/
  Telegram juga belum dikelompokkan menurut tugas pengguna.
- Perubahan utama: migration terkelola menata `sys_menu` tanpa mengubah route,
  page registry, permission, status aktif menu, atau data bisnis. Root menjadi
  Dashboard; Penjualan & Pesanan; Pelanggan, Member & Promo; Pembelian &
  Permintaan; Stok & Persediaan; Produk & Produksi; Keuangan; SDM & Payroll;
  Aset; Master & Konfigurasi; Administrasi & Audit; Integrasi & Notifikasi.
  POS memiliki empat rumpun jelas: Operasional Kasir, Channel & Antrean
  Pesanan, Pengaturan POS & Printer, serta Laporan & Audit POS. Tree sidebar
  juga tidak lagi mengirim grup kosong ke view bagi role yang tidak memiliki
  leaf terotorisasi.
- File berubah: `Menu_model`, migration
  `2026-09-06i_a3_sidebar_task_oriented_layout.sql`, katalog migration,
  smoke A3 IA, quality gate, roadmap audit, dan log ini.
- SQL/runtime/data: SQL hanya menulis konfigurasi `sys_menu` (label,
  `parent_id`, `sort_order`) dan lima group murni; tidak mengubah transaksi,
  stok, lot, HPP, user, role, maupun permission. Migration berjalan di staging
  melalui runner dan tercatat di ledger schema.
- Validasi: lint PHP; smoke A3 IA 18/18; validasi katalog; runner `upgrade`
  applied 1/skipped 13 lalu replay applied 0/skipped 14; postcheck staging
  mendapat 12 root dan 0 collision urutan; quality gate penuh profil `parallel`
  **PASS** (90 required, 4 development, 1 release, 1 preflight).
- Review: **STAGING PASS.** Percobaan awal menghasilkan output ringkasan yang
  tidak cocok dengan protokol runner setelah transaksi sudah commit; output itu
  dihapus, checksum diperbarui, lalu replay idempoten mencatat ledger secara
  benar. Tidak ada rollback atau perubahan data bisnis.
- Risiko sisa/batch berikutnya: visual UAT desktop/mobile dengan akun Kasir,
  Barista, HR, Finance, dan Superadmin. Struktur dapat disesuaikan kembali
  melalui Manajemen Sidebar; perubahan berikutnya tetap tidak boleh mengubah
  route atau RBAC tanpa batch khusus.

## Batch 211 — Penutupan implementasi Fase A3 dan checklist anti-pengulangan

- Waktu/tanggal: 2026-09-06.
- Prioritas: menutup ambiguitas status `AUD-A3-UI-00`–`09` tanpa mengulang
  Inventory/Production yang telah selesai pada Batch 180 dan 200–208.
- Hasil pemeriksaan: wave 1–4 telah memiliki primitive, shell, dan rollout POS
  web; wave 5 sudah mencakup stok Gudang/Bahan Baku/Component, snapshot,
  matrix, mutasi, FIFO, opname, audit lot, serta lot component; wave 6–8
  memakai workspace responsif yang sudah diuji; wave 9 sudah membersihkan CSS
  stale pada rumpun yang dimigrasikan. Tidak ditemukan alasan teknis untuk
  mengulangnya sebagai pekerjaan UI umum.
- File berubah: roadmap audit kanonis, smoke konsistensi roadmap, dan log ini.
  Tidak ada controller, model, route, view bisnis, SQL, data transaksi, stok,
  lot, HPP, POS Mobile, atau RBAC yang diubah.
- Perubahan utama: fase A3 serta setiap wave UI 1–9 ditandai `CODE_PASS`.
  Status fase kini `CODE_COMPLETE_UAT_PENDING`; checklist otomatis menolak
  roadmap bila salah satu wave kembali terbuka tanpa batch temuan yang nyata.
- Validasi: roadmap-consistency smoke; seluruh smoke A3 yang sudah terdaftar;
  lint PHP; quality gate penuh profil `parallel`.
- Review: **CODE PASS.** Implementasi A3 ditutup; visual UAT bukan dipalsukan
  sebagai test otomatis. Satu-satunya sisa A3 adalah cek tampilan dan alur
  nyata oleh Kasir/Barista, HR, Finance, dan Superadmin pada desktop/mobile.
- Risiko sisa/batch berikutnya: temuan UAT yang dapat direproduksi dibuat
  sebagai bug terpisah; jangan membuka ulang A3 atau mengulang Inventory hanya
  karena status lama belum diperbarui.

## Batch 212 — A1 login APK dan lifecycle sesi browser

- Waktu/tanggal: 2026-09-06.
- Prioritas: P0 A1/A0 — login POS Mobile tidak mengaktifkan throttle akun/IP
  yang sudah digunakan login web; sesi browser masih satu tahun dengan rotasi
  ID dua jam dan membiarkan ID lama hidup.
- Perubahan utama: login APK sekarang meneruskan IP request ke `Auth_model`,
  sehingga limiter akun+IP, delay respons gagal, dan pencatatan gagal bersama
  berlaku sebelum lookup terminal maupun penerbitan token. Sesi web dibatasi
  12 jam, ID berotasi tiap lima menit, dan ID lama dihancurkan.
- File berubah: `Pos_mobile.php`, `config.php`, smoke login mobile, smoke
  runtime web, manifest quality gate, kontrak manifest, roadmap, dan log ini.
  Tidak ada route, payload/response APK, schema, SQL, data transaksi, stok,
  lot, HPP, role, permission, atau model POS yang diubah.
- Validasi: lint PHP; smoke login mobile 6/6; throttle login auth 74/74;
  runtime browser 23/23; secret deployment 39/39; authorization/scope/proof
  POS Mobile regression; diff check; quality gate penuh profil `parallel`.
- Review: **CODE PASS.** Respons gagal tetap generik sehingga tidak menjadi
  oracle akun/terminal. Throttle berlangsung sebelum token mobile dibuat.
  Batas sesi tidak memengaruhi bearer token APK dan tidak mengubah aturan login
  role/scope.
- Risiko sisa/batch berikutnya: sesi browser lama akan berakhir paling lambat
  setelah 12 jam tidak aktif dan pengguna perlu login kembali. MFA tidak
  dipaksakan sebelum kebijakan enrolment, recovery, serta kompatibilitas APK
  disepakati; baseline izin dan UAT perangkat juga tetap terbuka.

## Batch 213 — A2 riwayat rekening backdate: snapshot saldo bisnis

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A2-FIN-01` / `GAP-03` — daftar mutasi menggunakan urutan
  posting yang benar, tetapi hasil yang difilter berdasarkan tanggal bisnis
  dapat terlihat tidak berantai saat ada transaksi backdate.
- Perubahan utama: halaman **Keuangan → Mutasi Rekening** sekarang, saat satu
  rekening dipilih, menampilkan saldo bisnis per tanggal akhir filter. Nilai
  dihitung read-only dari saldo awal catatan ledger ditambah seluruh mutasi
  bertanggal bisnis sampai cut-off; ia sengaja tidak memakai kolom saldo
  sesudah suatu baris posting. Snapshot membandingkan hasil seluruh ledger
  dengan saldo aktif dan memberi peringatan bila tidak cocok. Kolom daftar
  diperjelas menjadi “Sebelum/Sesudah Diposting”.
- File berubah: `Purchase_model.php`, `Purchase.php`,
  `purchase/finance_mutation_index.php`, smoke A2 account mutation, roadmap,
  dan log ini.
- SQL/runtime/data: tidak ada SQL, migration, rebuild, insert, update, atau
  delete data. Tidak ada saldo, jurnal, periode, HPP, stok, atau transaksi
  historis yang disentuh.
- Validasi: PHP lint; smoke riwayat rekening 9/9; close/reopen periode
  finance 707+55 check; matrix A2 inventory 12 smoke; quality gate penuh
  `parallel` **PASS** (91 required, 4 development, 1 release, 1 preflight);
  diff check.
- Review: **CODE PASS.** Tanggal bisnis dan urutan posting kini dijelaskan
  sebagai dua hal berbeda. Nilai cut-off memakai semua jurnal rekening agar
  filter tampilan modul/IN-OUT tidak membuat snapshot parsial. Ketidakcocokan
  hanya diperingatkan; sistem tidak menjalankan rebuild otomatis.
- Risiko sisa/batch berikutnya: UAT finance dengan satu rekening yang punya
  backdate masih diperlukan. Rebuild historis dan aturan uang makan payroll
  tidak boleh dikerjakan sebelum acceptance pemilik/finance.

## Batch 214 — A2 payroll uang makan bulanan dan custom

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A2-PAY-01` / `GAP-03` — kebijakan pemilik menetapkan rate
  uang makan sebagai hak per hari: mode Bulanan dibayar bersama payroll;
  mode Custom hanya dicatat sebagai hak dan dibayar melalui rentang pencairan
  yang dapat harian, mingguan, atau lainnya.
- Perubahan utama: perhitungan absensi, preview payroll, dan portal pegawai
  kini mencatat hak harian pada kedua mode. `MONTHLY` masuk net/transfer
  payroll; `CUSTOM` tidak masuk transfer gaji dan hanya dapat dipilih batch
  Pencairan Uang Makan. Payroll result line serta slip baru memisahkan uang
  makan Bulanan, hak Custom, settlement Custom, total hak, sudah dibayar, dan
  sisa. Calendar/ledger pengguna juga hanya menampilkan item Custom agar
  uang makan Bulanan tidak salah terbaca sebagai tagihan belum cair. Hak
  Custom pada PH tetap dapat dibuat batch bila kebijakan PH mengizinkannya.
- File berubah: model Attendance, Payroll Preview, My Portal, Payroll;
  controller/view Calendar Uang Makan; settings attendance; view batch
  pencairan, payroll period, salary disbursement, dan slip; smoke kontrak
  baru serta manifest quality gate, roadmap, dan log ini.
- SQL/runtime/data: tidak ada SQL atau migration baru. Tidak ada recalculation,
  insert, update, delete, saldo rekening, jurnal, maupun data payroll
  historis yang dijalankan. Periode historis mempertahankan snapshot lama.
- Validasi: lint seluruh PHP yang berubah; smoke policy uang makan 7/7;
  kontrak quality-gate 28/28; quality gate penuh `parallel` **PASS**
  (92 required, 4 development, 1 release, 1 preflight).
- Review: **CODE PASS.** `MONTHLY` dan `CUSTOM` dibedakan berdasarkan
  snapshot kebijakan pada baris absensi, sehingga perubahan setting berikutnya
  tidak memindahkan hak periode lama. Batch custom fail-closed bila snapshot
  belum tersedia dan menolak kandidat bulanan.
- Risiko sisa/batch berikutnya: lakukan UAT payroll periode baru untuk kedua
  mode, lalu cek satu slip dan satu batch Custom bertanggal mingguan. Jangan
  meregenerate periode yang sudah dibayar/ditutup demi mengubah data historis.

## Batch 215 — A1 sinkronisasi APK POS dan kesiapan operasional

- Waktu/tanggal: 2026-09-06.
- Prioritas: `AUD-A1-POS-01` — client Flutter tertinggal dari capability
  server versi 3 sehingga Void, Refund, Cetak Ulang, Tutup Kasir, dan refund
  DP reservasi dapat gagal walaupun endpoint Finance sudah aman.
- Perubahan utama: APK meminta password hanya pada dialog verifikasi, menerima
  proof singkat satu-kali dari Finance, lalu mengirim proof pada aksi tepatnya;
  password tidak masuk outbox atau penyimpanan lokal. Penolakan reservasi kini
  memilih eksplisit apakah DP direfund. Kasir backup mendapat peringatan tegas
  sebelum menutup sesi yang juga dipakai web. Outbox offline kini menandai
  event 4xx sebagai BLOCKED tanpa menahan order independen, tetapi tetap
  menjaga urutan event untuk order yang sama. Order confirm yang diterima
  sesudah offline memperoleh antrean cetak Bluetooth lokal, retry bertahap
  bila belum ada printer, dan tidak mencetak otomatis ulang pada hasil parsial.
  Layar sempit memakai panel Order/Katalog/Keranjang agar alur kasir tablet
  tidak lagi berupa tiga daftar panjang.
- File berubah: project terpisah `pos_cashier_apk`: API client, local DB dan
  sync service, cashier/order workspace/inbox, dialog proof, theme, native
  Android identity/signing config, serta dokumentasi arsitektur/release.
- SQL/runtime/data: tidak ada SQL, migration Finance, insert/update/delete
  data transaksi, stok, HPP, kas, payment, maupun shift staging.
- Validasi: PHP lint `Pos_mobile.php` dan `routes.php`; smoke Finance untuk
  otorisasi mobile, proof reversal/cashier close, login throttle, scope inbox,
  dan refund DP semuanya **PASS**. Diff APK bebas whitespace error.
- Review: **CODE PASS, BUILD/UAT PENDING.** Staging tidak memiliki Flutter,
  Dart, Java, `android/gradlew`, maupun wrapper JAR sehingga binary APK tidak
  dapat dibangun di sini. Release sekarang fail-closed bila keystore belum
  dipasang; build dan UAT perangkat nyata harus dikerjakan pada komputer
  Flutter/Android sesuai `pos_cashier_apk/docs/release_build.md`.
- Risiko sisa/batch berikutnya: jalankan `flutter analyze`, `flutter test`,
  lalu build release dan UAT satu perangkat Android untuk online/offline,
  printer, payment, inbox, dan seluruh proof. Fitur pengaturan/admin POS web
  yang bukan alur kasir tetap dilakukan melalui web sampai ada keputusan scope
  APK admin terpisah.

## Batch 216 — A1 lifecycle dan pairing Printer Agent

- Waktu/tanggal: 2026-09-07.
- Prioritas: `AUD-A1-PRINT-01`. Agent lokal sudah memiliki trust boundary dan
  upload logo aman, tetapi masih memakai key global, perubahan koneksi dapat
  menyisakan endpoint lama, dan operator belum mempunyai lifecycle service
  yang dapat dipasang/dilepas dengan jelas.
- Ringkasan auditor/fixer: gunakan key unik per nama agent bila environment
  private `POS_PRINTER_AGENT_KEYS` tersedia; key lama dapat berada sementara
  pada `previous` saat rotasi. Map bersifat authoritative sehingga nama agent
  yang tidak terdaftar ditolak. Instalasi lama tanpa map tetap kompatibel
  melalui `POS_PRINTER_BOOTSTRAP_KEY` dan pasangan `..._PREVIOUS`.
- File berubah: `Pos.php`, `Pos_printer_agent.php`, Panduan Printer,
  `tools/pos_printer_agent/agent.py`, README/config example, script lifecycle
  Windows/Linux, dan smoke Printer Agent.
- Perubahan utama: endpoint bootstrap mengiklankan rentang protocol; agent
  mengirim versi/protocol, memeriksa kompatibilitas, menyajikan status lokal
  tanpa secret, merotasi log, serta menyimpan config refresh secara atomik.
  Perubahan koneksi kini menandai `restart_required` dan tidak membuka port
  baru/meninggalkan port lama dalam proses yang sama. UI meminta nama agent
  sebelum mengunduh `config.json`, lalu menyediakan script Task Scheduler
  Windows serta systemd Linux untuk install/uninstall. Tidak ada perubahan
  transaksi, stok, HPP, kas, shift, schema, maupun SQL.
- Validasi: `php -l` tiga PHP berubah **PASS**; `py_compile` **PASS**;
  Printer Agent static/source dan HTTP/lifecycle smoke (venv A4) **PASS**;
  smoke upload logo 10/10, binding printer mobile 32/32, contract quality-gate
  28/28, roadmap consistency 26/26, quality gate `parallel` penuh **92
  required / 4 development / 1 release PASS**, dan `git diff --check` **PASS**.
- Hasil review: **CODE PASS / STAGING PASS.** Secret tidak dicatat ke source
  atau log. Agent lama tetap dapat memakai bootstrap legacy sampai pairing
  per-agent diprovision secara sadar.
- Risiko sisa/batch berikutnya: admin server customer perlu memasang map key
  privat pada PHP-FPM, lalu UAT satu Windows dan satu Linux/printer fisik:
  pairing, start otomatis, restart setelah ubah port, test print, QR/logo,
  dan recovery ketika printer atau jaringan putus. Pekerjaan installer/updater
  customer menyeluruh tetap berada di A5/C3.

## Batch 217 — Review sinkronisasi Finance dan Control Center

- Waktu/tanggal: 2026-09-07.
- Prioritas: C1–C4 sebelum productization Finance. Audit read-only terhadap
  Control Center memastikan roadmap komersialisasi tidak mengklaim fondasi
  vendor sebagai integrasi Finance yang sudah selesai.
- Hasil: Control sudah menyediakan registry manifest/produk/edition/feature,
  customer-instance, release/artifact private, delivery claim-once/receipt,
  dan lisensi server Ed25519 generik. Kontrak heartbeat Finance selaras tetapi
  pilot Finance pada Control telah diarsipkan; tidak ada instance aktif yang
  boleh diasumsikan.
- Gap yang dicatat: Finance belum punya manifest v2 atau katalog Control,
  belum punya profil usaha/onboarding lokal, adapter release SemVer/installer/
  receipt, maupun FeatureGate/verifier/lease cache/kontrak APK. Control saat
  ini berorientasi lease/read-only untuk produk contoh; Finance harus
  memisahkan hak pakai perpetual dari masa maintenance agar transaksi tidak
  dibatasi setelah dukungan berakhir.
- File berubah: roadmap komersialisasi `_28` dan execution log ini saja.
  Tidak ada perubahan aplikasi Finance/Control, database, secret, SQL,
  heartbeat, cron, lisensi aktif, maupun data customer/transaksi.
- Tindak lanjut: mulai C1 dengan manifest Finance dan kontrak perpetual +
  maintenance Control; C2/C3/C4 mengikuti urutan adopsi yang tercatat di
  roadmap.

## Batch 218 — C1 katalog Finance dan hak perpetual

- Waktu/tanggal: 2026-09-07.
- Prioritas: C1 — membuat satu katalog Finance yang dibaca Control Center dan
  memisahkan hak menjalankan produk dari masa maintenance. Tidak ada
  FeatureGate atau lisensi runtime Finance pada batch ini.
- Ringkasan implementasi: `app-manifest.json` v2 mendefinisikan 28 feature,
  4 edition (`STARTER_POS`, `OPERATIONS`, `CONTROL`, `ENTERPRISE`), 29
  dependency, serta kapasitas outlet/terminal. Control mengimpor
  `NAMUA_FINANCE` dari source allowlist sebagai draft terjejak. Migration
  Control menambah dependency feature dan kolom `rights_model`,
  `perpetual_granted_at`, serta `maintenance_ends_at`; policy Finance memakai
  `PERPETUAL`, 365 hari maintenance default, dan `WARN_ONLY` agar berakhirnya
  maintenance tidak menjadi read-only transaksi.
- File berubah: `app-manifest.json`, package policy release Finance, roadmap
  komersialisasi, laporan C1 ini, serta source/model/view/test/migration
  Control Center. Registry source privat Control ditambah `NAMUA_FINANCE`.
- SQL/runtime/data: migration Control
  `20260907100000_c1_perpetual_maintenance_and_feature_dependencies.sql` telah
  dijalankan setelah backup otomatis Control. Tidak ada SQL Finance, perubahan
  schema/data Finance, customer, instance, activation, release, deployment,
  transaksi, stok, HPP, kas, payroll, atau data runtime Finance.
- Validasi: PHP lint; parser manifest 7/7; migration disposable; catalog
  registry contract; delivery/licensing contract; security/static scan Control
  PHP 8.4 (61/61); preflight release Finance; roadmap consistency; dan
  quality-gate contract semuanya **PASS**. Query Control mengonfirmasi 28
  feature, 4 edition, 29 dependency, `PERPETUAL`, maintenance 365 hari, dan
  `WARN_ONLY`.
- Hasil review: **STAGING PASS / CATALOG DRAFT READY.** Source Finance masih
  dirty dan belum dapat menjadi release customer; import katalog tidak
  mengubah runtime maupun data Finance.
- Risiko sisa/batch berikutnya: finalkan add-on dan entitlement override per
  customer, harga/EULA/SLA/data policy, lalu lanjut C2 profil usaha/onboarding
  lokal. C3/C4 tetap tidak boleh dimulai sebagai enforcement sebelum release
  Finance memiliki cutoff Git bersih dan C2 selesai.

## Batch 219 — C2–C4 fondasi productization, delivery, dan lisensi aman

- Waktu/tanggal: 2026-09-07 WIB.
- Prioritas: C2–C4, dilakukan sebagai satu rangkaian metadata-only agar staging
  tidak terkunci dan tidak mengubah transaksi, stok, HPP, kas, payroll, atau
  data historis.
- Ringkasan implementasi: C2 menambah `System > Profil Usaha & Tampilan` dengan
  RBAC, CSRF, audit perubahan, identitas/locality, serta unggah logo PNG/JPG
  tervalidasi. Printer memakai urutan override cetak/outlet -> profil usaha ->
  fallback, tanpa menimpa konfigurasi POS lama. C3 menambah preflight sumber
  Finance untuk Control Center dan plan installer read-only. C4 menambah
  registry instalasi, cache entitlement, feature/device/audit, halaman lisensi,
  dan `FeatureGate` yang defaultnya `AUDIT_ONLY`; tidak ada controller POS atau
  transaksi yang diberi enforcement lisensi.
- File berubah: migration managed `2026-09-07a`, baseline clean-install dan
  policy/catalog migration, model/controller/view Profil Usaha dan Lisensi,
  `Feature_gate`, fallback `Pos_print_model`, route System, manifest v2
  `0.1.0-alpha.2`, tools C3, smoke C2/C4, dan kontrak A5 terkait.
- SQL/runtime/data: migration Finance `2026-09-07a_c2_c4_business_profile_license_runtime_foundation.sql`
  telah dijalankan melalui migration runner di staging: `applied=1`, kemudian
  replay aman `applied=0/skipped=15`. Hanya tabel metadata, page/menu, dan
  grant SUPERADMIN dibuat. Query pasca-run membuktikan 9/9 tabel, ledger 1,
  2 page/menu, 2 grant SUPERADMIN, serta 0 override `ENFORCE`.
- Validasi: lint seluruh PHP baru/berubah; catalog validate; baseline guard
  18/18; C2/C4 smoke 10/10; migration catalog 43/43; schema fingerprint
  43/43; legacy guard 19/19; post-install health contract 13/13; required
  quality gate 93/93, development 4/4, release 1/1, serta preflight release
  terpisah lulus. Preflight C3 dan install plan clean/upgrade lulus. Scoped
  `git diff --check` batch lulus; global check masih melihat blank line pada
  `docs/_NOTE2.md` yang telah dirty dan bukan bagian batch ini.
- Hasil review: **STAGING PASS / FOUNDATION ONLY.** Staging tetap audit-only;
  maintenance tidak mengunci transaksi. Worktree masih kotor sehingga preflight
  dengan benar menolak artifact resmi.
- Risiko sisa/batch berikutnya: C2 masih perlu replacement hardcode dan wizard
  onboarding; C3 perlu commit/cutoff bersih, artifact signed, claim/receipt,
  installer dan UAT upgrade; C4 perlu public-key verifier, aktivasi Control,
  cache offline/grace, device/APK pairing, limit, dan enforcement bertahap
  setelah pilot customer.

## Batch 220 — C2 branding inti, onboarding admin, dan sidebar level 4

- Waktu/tanggal: 2026-09-07 WIB.
- Prioritas: rapikan navigasi sidebar level ke-4 terlebih dahulu, lalu tuntaskan
  jalur inti branding customer, setup admin, dan fallback preview/cetak tanpa
  mengubah struktur menu database, transaksi, outlet, atau konfigurasi printer
  yang sudah dipakai.
- Ringkasan implementasi: renderer sidebar sekarang memberi penanda kedalaman
  dan level 4+ memakai font/ikon/padding ringkas agar tidak jatuh ke ukuran
  bawaan tema. Profil Usaha menjadi sumber lokal untuk login, sidebar, footer,
  QR ulasan pelanggan, label aset, cetak kontrak, dan metadata Menu Book.
  Halaman Profil Usaha kini memandu admin dalam tiga langkah: identitas,
  kontak/lokalitas, dan logo/footer dokumen. Printer memakai footer profil
  hanya sebagai fallback; data outlet atau layout/general printer yang ada
  tetap menang. Pengaturan Landing Page menampilkan nama profil sebagai konteks
  dan tidak lagi menyediakan fallback URL/SEO Namua pada konfigurasi kosong.
- File berubah: shell `MY_Controller`, Auth, sidebar/layout/theme, Profil
  Usaha, POS print/preview, QR review, kontrak, aset, landing page, Menu Book,
  dan smoke C2/C4.
- SQL/runtime/data: tidak ada migration, query tulis, atau perubahan data pada
  batch ini. Data transaksi, stok, HPP, payroll, outlet, printer, dan konten
  marketing yang tersimpan tidak disentuh.
- Validasi: PHP lint seluruh PHP yang berubah; C2/C4 commercial smoke 14/14;
  Printer General logo smoke 10/10; A3 UI shell 54/54; A3 workspace navigation
  11/11; A4.3 browser runtime 28/28 dengan Chrome lokal; scoped
  `git diff --check` lulus.
- Hasil review: **STAGING PASS / C2 CORE BRANDING READY.** Perubahan mengikuti
  urutan override `outlet/layout printer -> Profil Usaha -> default netral`.
- Risiko sisa/batch berikutnya: static Menu Book Namua adalah konten/template
  customer (produk, gambar, sosial, dan narasi), bukan chrome aplikasi; jangan
  diganti massal oleh nama profil. Sebelum C2 selesai perlu pemisahan template
  menu/marketing, preset demo, pajak/service, integrasi, privacy/health, dan
  install profile customer. C3/C4 tetap menunggu cutoff Git/artifact bersih dan
  kontrak lisensi yang dapat diverifikasi.

## Batch 221 — Pemulihan permission seluruh folder upload

- Waktu/tanggal: 2026-09-08 WIB.
- Prioritas: kegagalan unggah logo Profil Usaha `Folder logo usaha belum dapat
  disiapkan`.
- Temuan: PHP-FPM berjalan sebagai user `www`, namun `assets/uploads` dan
  `uploads` beserta subfolder lama dimiliki `root:root` dengan mode `775`.
  User `www` tidak memiliki hak tulis sehingga tidak dapat membuat folder baru.
  Hanya `assets/uploads/pos-printer-logo` yang sebelumnya sudah writable.
- Perubahan runtime: dibuat `assets/uploads/business-profile-logo` dengan
  owner `www:www` dan mode `2775`. ACL `user:www:rwx` plus default ACL untuk
  folder baru diterapkan pada seluruh directory di bawah `assets/uploads` dan
  `uploads`; owner, isi, upload lama, backup, credential, serta data transaksi
  tidak diubah atau dihapus.
- Validasi: pemeriksaan PHP-FPM mengonfirmasi worker `www`; uji sebagai user
  `www` menyatakan `READY` untuk seluruh path aktif: logo usaha/printer,
  produk, aset (foto/evidence), coffee labels, produk legacy, dan WA tahun/bulan.
- Hasil review: **RUNTIME PASS.** Unggah logo Profil Usaha sekarang dapat
  membuat dan menulis folder tujuannya; inheritance ACL menjaga folder upload
  baru berikutnya tetap writable untuk PHP-FPM tanpa menjadi world-writable.
- Risiko sisa/batch berikutnya: lakukan unggah satu file PNG/JPG melalui UI
  Profil Usaha sebagai acceptance test browser; bila server deploy baru dibuat,
  installer C3 perlu menerapkan policy ACL yang sama untuk user pool PHP-FPM.

## Batch 222 — Kejelasan penolakan login POS APK

- Waktu/tanggal: 2026-09-08 WIB.
- Prioritas: hilangkan pesan APK lama yang menyebut `Finance2` saat login ke
  backend customer yang sebenarnya sudah dikonfigurasi sebagai URL sendiri.
- Temuan: endpoint `https://pos.namuacoffee.com/pos-mobile/ping` sehat dan
  mengarah ke aplikasi Finance ini. Penolakan `401` dari server sengaja generik
  untuk tidak membocorkan apakah password atau device key yang salah. Akun
  `fairus` aktif, terhubung ke employee, memiliki akses kasir, dan tidak sedang
  terkena throttle; maka pemeriksaan tersisa adalah password serta kecocokan
  device key dengan terminal aktif. Terminal Android `SAMSUNG` sudah ada,
  namun metadata platform-nya masih `DESKTOP` dan perlu dirapikan lewat UI POS.
- File berubah: APK `finance_api_client.dart`, `login_screen.dart`,
  `cashier_screen.dart`, `incoming_orders_screen.dart`, dan komentar
  `order_workspace_screen.dart`.
- Perubahan utama: pesan login sekarang menyebut URL backend yang sedang
  dipakai (mis. `https://pos.namuacoffee.com`), tanpa kata `Finance2`; semua
  pesan lain yang tersisa juga memakai istilah POS netral.
- Validasi: ping backend lulus, query registry/akses read-only lulus, tidak ada
  lagi referensi `Finance2` dalam source APK, dan `git diff --check` lulus.
  `dart format`/`dart analyze` belum dapat dijalankan di staging karena binary
  Dart/Flutter tidak terpasang; lakukan build/analyze APK di mesin Android
  development sebelum rilis.
- Hasil review: **SOURCE PASS / DEVICE CONFIGURATION REQUIRED.** Tidak ada
  data transaksi, token, maupun device key yang diubah.
- Risiko sisa/batch berikutnya: pada APK buka Pengaturan dan salin Device Key
  tepat ke terminal `SAMSUNG` di `POS > Outlet + Terminal`, pastikan terminal
  aktif serta Android, lalu login ulang. Jangan menampilkan device key di chat
  atau log.

## Batch 223 — Perbaikan katalog, append order, dan cetak POS APK

- Waktu/tanggal: 2026-09-08 WIB.
- Prioritas: paket/bundle kosong di APK, item tambahan pada order tersimpan
  tidak masuk, logo struk menjadi blok hitam, serta konfigurasi fisik printer
  harus dimiliki perangkat APK.
- Temuan: terdapat 9 bundle aktif dan berline di server; APK membawa filter
  divisi produk terakhir ke tab Bundle sehingga seluruh bundle dapat tersaring.
  Pemeriksaan event sinkron read-only menunjukkan line lama dari APK memakai
  `order_line_id`, namun normalizer server hanya mengenali `id`; append order
  lalu salah menilai line lama sebagai terhapus. Raster Android juga menganggap
  pixel PNG transparan ber-RGB hitam sebagai tinta hitam.
- File berubah: `Pos_model.php`; APK `cashier_screen.dart`,
  `local_database.dart`, `pos_print_dispatcher.dart`,
  `printer_settings_screen.dart`, dan `MainActivity.kt`.
- Perubahan utama: tab Bundle selalu memulai tanpa filter divisi; server kini
  menerima identitas line lama dari kontrak web maupun APK; bitmap transparan
  dikomposit ke putih sebelum raster; setiap binding printer APK menyimpan
  ukuran 58/80 mm serta karakter/baris secara lokal dan dispatcher menggunakan
  nilai lokal tersebut, bukan nilai lebar/karakter dari database Finance.
- SQL/runtime/data: tidak ada SQL Finance atau perubahan data transaksi.
  SQLite APK naik dari versi 4 ke 5 dan hanya menambah kolom lokal
  `chars_per_line` pada binding printer perangkat.
- Validasi: PHP lint `Pos_model.php` dan `Pos_mobile.php`; POS mobile
  authorization smoke lulus; POS transaction CSRF smoke lulus 1770/1770;
  ping `pos.namuacoffee.com` lulus; scoped diff check lulus. Dart/Flutter dan
  Gradle wrapper tidak tersedia di staging sehingga build APK harus dilakukan
  pada mesin development Android.
- Hasil review: **SOURCE PASS / BUILD APK REQUIRED.** Tidak ada stok, order,
  pembayaran, token, atau konfigurasi printer server yang diubah.
- Risiko sisa/batch berikutnya: pasang APK hasil build, buka ulang binding
  printer untuk memilih ukuran kertas/karakter lokal, lalu uji bundle, append
  order confirmed, dan logo PNG transparan pada printer fisik.

## Batch 224 — C2 Menu Book customer tanpa mengubah desain lama

- Waktu/tanggal: 2026-09-08 WIB.
- Prioritas: lanjut roadmap non-APK; pisahkan konten tetap Namua dari katalog customer.
- Diskusi/review: fixer tunggal sesuai arahan owner; desain lama harus tetap
  tersedia, publikasi produk harus opt-in, tidak membaca order/stok/HPP.
- File berubah: `application/libraries/Customer_publication.php`,
  `application/models/Business_profile_model.php`,
  `application/controllers/Business_profile.php`, `application/controllers/Menu_book.php`,
  `application/views/system/business_profile.php`, `application/views/menu_book/customer.php`,
  `tools/tests/c2_customer_publication_smoke.php`.
- Perubahan utama: pilihan template customer/legacy/nonaktif pada Profil Usaha;
  pilihan disimpan atomik dengan profil dan audit before/after; seluruh deep
  link Menu Book mengikuti pilihan; input/tautan publik di-escape/divalidasi;
  nilai template tidak dikenal tidak membuka konten legacy.
- SQL/data: tidak ada migration/schema/query transaksi atau penggantian
  pengaturan staging otomatis. Memakai `sys_app_config` yang sudah ada;
  pengaturan baru hanya ditulis saat admin menyimpan formulir.
- Validasi: PHP lint; 23 behavioral checks publikasi termasuk semua route,
  output escaping, master-data-only, commit/audit dan rollback mock; foundation
  C2/C4 14/14 PASS. Belum UAT browser nyata untuk pemilihan template.
- Hasil review mandiri: CODE_PASS untuk batch; tidak menutup keseluruhan C2.
- Risiko sisa: marketing/preset, locale menyeluruh, pajak/service/integrasi,
  dan validasi identitas/URL pada customer nyata tetap terbuka.
- Batch berikutnya: batas paket rilis dan runtime instalasi.

## Batch 225 — A0/A5/C3 isolasi upload dan runtime instalasi

- Waktu/tanggal: 2026-09-08 WIB.
- Prioritas: logo yang tidak sengaja tracked berpotensi ikut paket karena
  `assets/uploads/` belum termasuk deny-prefix wajib; folder buatan root
  sebelumnya juga menyebabkan unggah gagal. Hook Composer masih memanggil
  `sed` pada dependency development yang tidak selalu terpasang.
- Diskusi/review: fixer tunggal; isolasi paket tidak boleh menghapus logo
  runtime; pemeriksaan izin harus memakai user PHP-FPM, bukan root.
- File berubah: `Upload_storage_policy.php`, controller/view Profil Usaha,
  `tools/install/upload_storage.php`, `tools/install/finance_install_plan.php`,
  `tools/install/composer_compat.php`, `composer.json`,
  `tools/release/package_policy.json`, `tools/release/ReleasePackagePolicy.php`,
  `tools/tests/c3_upload_storage_smoke.php`, `tools/tests/c3_composer_compat_smoke.php`,
  `tools/tests/a4_release_artifact_contract_smoke.php`, manifest/contract quality gate.
- Perubahan utama: delapan folder diperiksa melalui UI dan CLI; prepare hanya
  membuat folder allowlist, menolak root/symlink, tanpa chmod/chown/delete
  runtime. Upload serta catatan privat `docs/_NOTE*` dikecualikan dari paket;
  file aslinya tetap dipertahankan. Plan upgrade tidak membuat
  owner atau menanam reference seed. Hook Composer portabel, idempotent,
  menolak symlink dan no-op pada install tanpa dependency development.
- Validasi: storage 18/18 PASS sebagai root fixture, delapan folder nyata READY
  melalui `runuser -u www -- php tools/install/upload_storage.php check`;
  Composer hook 6/6 PASS; `composer validate --no-check-publish` valid;
  post-install hook aktual `SKIPPED_NO_DEV_PACKAGE`; package contract 18/18 dan
  artifact fixture 12/12 PASS; plan clean_install 16 migration dan upgrade 15.
- Hasil review mandiri: CODE_PASS untuk perbaikan batch; installer tetap
  plan-only, belum installer customer nyata. Composer sistem versi lama masih
  mengeluarkan deprecation notice, tidak mengubah dependency lock/version.
- Risiko sisa: public artifact/installer/rollback belum bisa disahkan;
  worktree dirty dan Git lokal/origin masih divergen 37/150 pada HEAD
  `d462d4a9cb39fcb74ff8e874549982a74fc33e15`. Tidak commit, push atau stash
  perubahan pengguna. Control release-key provisioning masih spesifik produk
  Penatausahaan; protokol artifact Finance perlu keputusan integrasi terpisah.
- SQL/data: tidak ada SQL, DB/schema, atau permission runtime yang diubah;
  folder staging sudah READY sehingga prepare tidak dijalankan.
- Batch berikutnya: verifikasi signed entitlement Finance–Control.

## Batch 226 — C4 verifikasi signed entitlement, tanpa enforcement

- Waktu/tanggal: 2026-09-08 WIB.
- Prioritas: flag VERIFIED dan baris feature_cache lokal tidak boleh menjadi
  sumber pemberian hak fitur tanpa bukti tanda tangan penerbit.
- Diskusi/review: fixer tunggal; baca kontrak aktual Control secara read-only
  (`tools/process_license_issuance.php`, API/model activation dan public trust
  provisioning). Tidak mengarang format token dan tidak memakai key produk lain.
- File berubah: `application/libraries/Control_license_verifier.php`,
  `application/models/License_runtime_model.php`, `application/libraries/Feature_gate.php`,
  `application/controllers/License.php`, `application/views/system/license_index.php`,
  `tools/tests/c4_control_license_verifier_smoke.php`, manifest/contract quality gate.
- Perubahan utama: verifikasi Ed25519 atas konteks `NAMUA_LICENSE_V1` dan hash
  byte payload asli; periksa produk, trust fingerprint/key-id, instance,
  installation, public-key binding, lease/grace dan nilai entitlement.
  Runtime membaca hak dari payload signed, bukan feature_cache. Trust/identity
  deployment-owned di luar webroot. Flag SQL saja tidak bisa mengaktifkan mode
  enforcement; default tetap AUDIT_ONLY.
- Validasi: 26/26 behavioral tests memakai pasangan key fixture sekali pakai;
  tamper signature/payload, key tidak dikenal/revoked, installation salah,
  lease/grace boundary, maintenance berakhir, tanggal invalid dan forged flag
  ditolak sesuai kontrak. Foundation C2/C4 14/14 PASS.
- Hasil review mandiri: CODE_PASS verifier; bukan aktivasi/penjualan C4 selesai.
- Risiko sisa: belum ada activation/polling/cache writer, anti-rollback state
  lintas restart, Windows ACL/native guard, terminal pairing, atau enforcement
  lintas endpoint. Tidak membuat key produksi, instance customer, mengubah
  database Control, mengaktifkan lisensi, atau menyentuh APK.
- Batch berikutnya: pemeriksaan regresi akhir, panduan, dan keputusan cutoff
  release/penyelarasan artefak Control sebelum deployment customer.

## Batch 227 — A4 validasi gabungan dan catatan handoff non-APK

- Waktu/tanggal: 2026-09-08 WIB.
- Prioritas: buktikan batch 224–226, perbarui dua roadmap utama tanpa centang
  selesai palsu, dan berikan panduan setup yang bisa diikuti admin.
- Diskusi/review: fixer tunggal; tes fixture bukan penerimaan perangkat nyata
  atau aktivasi customer. APK tetap ditunda; tidak memperbaiki atau mengulang A3.
- File berubah: kedua roadmap `_30`/`_28`, log ini,
  `docs/customer_setup_and_release_guide.md`,
  `tools/static/phpstan.neon`, `tools/tests/a4_static_analysis_smoke.php`,
  manifest/contract quality gate dan tambahan fixture pada tes paket.
- Perubahan utama: panduan membedakan pengaturan UI, admin server, instalasi
  kosong versus upgrade **salinan** database lama, dan verifikasi lisensi yang
  belum diaktifkan. PHPStan sebelumnya kehabisan memory 1G; menjadi satu worker
  dengan batas 2G, tetap seluruh `application`, baseline nol, tidak menambah
  ignore atau mengeluarkan file dari analisis. Error global tool kini terlihat.
- Runtime: refresh basis advisory OSV dengan script bootstrap resmi project;
  hanya cache scanner di `/var/lib/finance-a4-security` diperbarui, tidak ada
  dependency aplikasi di-upgrade atau DB transaksi dibaca/ditulis.
- Validasi akhir:
  - PHP lint 24 file implementasi/tes yang disentuh: PASS.
  - `finance_quality_gate.php --profile=release`: PASS; 97 required, 4
    development, 1 release-config, 2 runtime fixture, 1 preflight, 1 security,
    dan 1 static — total 107 entry. Probe DB staging sengaja tidak dijalankan.
  - OSV: 3 sumber lock, 145 package, 0 advisory pada snapshot terbaru.
  - PHPStan: seluruh application PASS, baseline_errors=0.
  - Migration catalog validate: PASS, 16 managed/7 legacy unmanaged;
    tidak ada migration baru untuk batch ini.
  - Scoped diff check PASS. Whitespace lama pada `docs/_NOTE2.md` milik
    pengguna tetap dibiarkan; file tersebut tidak diedit.
  - Control release preflight: package_issues=0, worktree_clean=false,
    artifact_publishable=false. Ini blokir penerbitan yang benar, bukan
    alasan membuat artifact dari dirty tree.
- Hasil review mandiri: batch kode/tes layak, tetapi C0, keseluruhan C2–C4,
  C5, dan release customer belum selesai. UAT role/printer fisik belum lulus;
  laporan SOURCE PASS APK lama tidak diubah menjadi APK siap jual.
- Risiko sisa/batch berikutnya: persetujuan cutoff commit lokal terseleksi
  tanpa merge/push, koordinasi delivery artefak Finance di Control, kemudian
  installer nyata/upgrade-restore-rollback di salinan database. Harga/kontrak,
  aktivasi resmi, dan pilot masih membutuhkan owner. Tidak ada commit/push,
  deployment server utama, atau perubahan aplikasi/database Control pada batch ini.
- Notifikasi: ringkasan batch dikirim ke grup Telegram melalui notifier
  terkonfigurasi; log mengonfirmasi `notification sent` pada
  2026-09-08 22:36:25 WIB. Pesan menyebut dengan jelas bahwa seluruh fase
  belum selesai dan masih membutuhkan keputusan cutoff/delivery.

## Batch 228 — C3 cutoff lokal dan verifikasi paket Finance–Control

- Waktu/tanggal: 2026-09-09 WIB.
- Prioritas: cutoff yang dapat dilacak dan format delivery sesuai Control,
  bukan mengulang bug APK atau fase UI yang sudah dikerjakan.
- Arahan owner: commit lokal terseleksi diperbolehkan; tanpa merge/push.
  Komersialisasi APK boleh disentuh, bug operasional APK tetap ditunda.
- Diskusi/review: fixer tunggal. Ditemukan klaim PHP manifest sampai 8.4
  tidak sesuai kontrak PHP 8.1; importer Penatausahaan memakai migration PHP,
  sedangkan Finance memakai katalog SQL. Kedua format tidak dicampur.
- File batch: `app-manifest.json`, `tools/release/ControlReleaseBridge.php`,
  `tools/release/control_release.php`, smoke test C3 dan registry quality gate,
  test foundation C2/C4, kedua roadmap, log ini, `docs/control_release_delivery.md`.
  Control: `tools/provision_release_signing_key.php` (allowlist produk terpisah),
  `tools/verify_finance_release.php` (CLI read-only, tanpa DB).
- Perubahan utama: versi source alpha.3 tanpa SQL/schema baru; metadata
  runtime selaras. Verifikasi seluruh byte arsip/source, Ed25519 asli Control,
  katalog managed/legacy SQL, baseline, runtime, dan batas paket. APK terpisah
  serta release_ready=false. Tidak mengubah modul/aplikasi APK pada batch ini.
- Cutoff awal: `d462d4a9cb39fcb74ff8e874549982a74fc33e15`.
  Hasil cutoff, hash artefak dan validasi akhir menyusul di bagian hasil batch.
- Perlindungan workspace: `docs/_NOTE2.md` dan upload lokal tidak dipilih
  untuk commit. Backup file provisioning Control sebelum perubahan disimpan
  di `/var/lib/finance-cutoff-20260909.JOMHzG/`. Control belum memiliki HEAD;
  tidak membuat initial commit atas seluruh project Control.
- Validasi implementasi: behavioral fixture C3, contract catalog Control,
  lint dan quality gate; hasil akhir dicatat setelah checkout/build.
- Risiko sisa: belum registrasi DB Control/publish, installer executable,
  install/upgrade/rollback customer nyata, aktivasi/enforcement/UAT. Bukan
  pernyataan C0–C5 atau APK selesai. Tidak menyentuh database transaksi.
- Batch berikutnya: registrasi private artifact Finance di Control dan
  installer Linux/upgrade salinan DB dengan rollback disposable.

### Hasil Batch 228 — 2026-09-09, 05:15 WIB

- Cutoff source: `b10fa37a40a06b1867800327812ac1dc1490c176`, tag lokal
  `finance-web-alpha.3-cutoff-20260909`. Dibanding checkpoint awal `d462d4a`:
  **58 file baru + 141 file berubah**. Ini mengarsipkan pekerjaan terdahulu
  yang masih uncommitted, bukan 199 fitur/perbaikan baru pada batch ini.
  Commit agregat `f07524f`; `b10fa37` memperbaiki tes jumlah ID quality gate.
  Laporan setelah build akan berada di commit dokumen terpisah; cutoff
  source artefak tetap tag tersebut. Tidak merge/fetch/push.
- Validasi pertama menemukan satu tes mengunci jumlah lama (110 termasuk
  probe staging). Diperbaiki menjadi pemeriksaan keunikan terhadap daftar
  aktual; exact expected ID/tier tetap diuji, tidak menghapus pemeriksaan.
- Validasi final: quality gate profile release **PASS 108 entry** (98 required,
  4 development, 1 release-config, 2 runtime, 1 preflight, 1 security, 1 static).
  Probe DB staging SKIPPED sesuai scope. Tes bridge 22/22 PASS; kontrak
  katalog Control 7/7 PASS; roadmap consistency 26 checks PASS.
- Preflight source yang sudah masuk index: 1.621 kandidat, 553 excluded,
  870 PHP lint, 1 Node, 3 Python; findings=0. Lint PHP bridge/tools Control
  juga PASS. Migration validator: 16 managed, 7 legacy unmanaged PASS.
- Kendala build awal: PHPStan cold checkout timeout pada gate 150 detik.
  Analisis penuh pada checkout final dijalankan terpisah dengan batas
  diagnostik 300 detik, memory 2G, satu worker, scope seluruh application:
  errors=0/file_errors=0. Tidak mengganti baseline, exclusions, atau melewati
  gate. Build ulang dari checkout tersebut menjalankan seluruh gate dan PASS.
  **Risiko sisa tooling:** budget cold-start dan cache lintas checkout perlu
  dibenahi sebelum installer clean-machine/pipeline tanpa persiapan dinyatakan
  repeatable. Kelulusan warm-cache bukan penutupan risiko itu.
- Artefak: `/var/lib/finance-cutoff-20260909.JOMHzG/finance-0.1.0-alpha.3.tar`;
  1.621 file, 597.248.000 byte, source epoch 1788905198.
  SHA256 `ed47c76e39d49a718b8905a94fb2d88be88ddc89104a4a38c7aec7d361f3600e`.
  Inner manifest SHA256
  `77b40ddbd3fdb2dae0146864ea41167e5eb3aa3abf936ae0d8b26fb211e45685`.
- Repro-check: build kedua dari cutoff/epoch yang sama, dengan seluruh gate
  tetap dijalankan, menghasilkan SHA256 yang sama dan `cmp` byte-identical.
  Arsip bukti `finance-reprocheck.tar` dipertahankan di folder yang sama.
  Ini membuktikan determinisme paket; bukan menghapus batasan cold-cache di atas.
- Sidecar `.release.json` dan `.release.sig.json` di folder yang sama;
  CLI Control `verify_finance_release.php` PASS: source sesuai cutoff,
  16 managed/7 legacy SQL, INTERNAL_CANDIDATE, published=false,
  database_changed=false, apk_release_ready=false. Paket ini bukan upload
  UI Control 25 MiB; jalur registrasi private CLI Finance masih perlu dibuat.
- Satu pasangan key **release Finance**, bukan key lisensi, dibuat oleh
  provisioning Control di `/var/lib/namua-control/release-signing/`.
  Private/trust root-owned 0600 di luar webroot. Tidak merotasi/memakai key
  Penatausahaan, membuat instance customer, atau mengaktifkan entitlement.
- Tidak ada SQL/schema baru dan tidak mengeksekusi SQL pada batch ini.
  Lima SQL yang sekarang masuk cutoff sudah dibuat batch sebelumnya:
  `2026-09-06f`, `2026-09-06g`, `2026-09-06h`, `2026-09-06i`, `2026-09-07a`;
  baseline schema juga membawa perubahan terdahulu. Ini bukan instruksi
  menjalankan semuanya di server utama; deployment kelak memakai DB salinan
  dan policy migration yang sesuai.
- `docs/_NOTE2.md` dan tiga logo upload lokal diverifikasi SHA256 tetap sama.
  Tidak ada backup/upload/credential/log/data runtime milik user yang dihapus.
  Checkout build terpisah bersih; staging sengaja masih memperlihatkan file
  lokal tersebut sebagai modified/untracked. Daftar cutoff lengkap dan
  diff/backup Control disimpan di folder bukti di atas (root-only).
- Review mandiri: batch cutoff/signature/verify layak sebagai kandidat internal.
  Bukan C3 keseluruhan selesai atau persetujuan menjual/deploy customer.
  Berikutnya: rapikan tooling cold-start, registrasi DRAFT Finance/private
  artifacts Control, kemudian installer nyata dan uji upgrade/rollback DB
  disposable. Bug operasional APK tetap ditunda, komersialisasinya boleh berjalan.
- Notifikasi ringkasan Batch 228 dikirim ke grup Telegram terkonfigurasi;
  log mengonfirmasi `notification sent` pada 2026-09-09 05:18:54 WIB.

## Batch 229 — Cold-cache build: budget bertingkat dan isolasi checkout

- Waktu: 2026-09-09 WIB. Prioritas A4/A5→C3, tanpa mengurangi error gate.
- Review/diskusi: fixer tunggal; cold analysis sebelumnya sekitar empat menit,
  tetapi inner gate 150s dan outer 180s. Cache staging/worktree saling menimpa.
- File: `tools/static/toolchain.lock.json`, `tools/static/phpstan.neon`,
  `tools/tests/a4_static_analysis_smoke.php`, contract test-nya,
  `tools/tests/finance_quality_gate.php`, `tools/release/build_release_artifact.php`.
- Perubahan: inner 360s, outer khusus static 420s; tes lain tetap 180s.
  Cache di runtime eksternal dibagi hash path checkout, menolak symlink cache.
  Scope seluruh application, satu worker, memory 2G dan baseline nol tetap.
- Validasi: 20 static contract tests PASS; cold run aktual melalui gate baru
  PASS scope=application/baseline_errors=0, tanpa warming manual.
- Hasil review: CODE/RUNTIME PASS tooling. Artefak alpha.3 tetap immutable
  dengan tooling lamanya; perbaikan ini baru masuk kandidat berikutnya.
- Risiko/batch berikutnya: kode harus dipaketkan ulang sebagai versi baru;
  tidak mengubah hash atau tag cutoff b10fa37 yang telah diverifikasi.

## Batch 230 — Registrasi DRAFT Finance di private artifact storage Control

- Waktu: 2026-09-09 WIB. Prioritas C3 delivery, bukan publish/deployment.
- Review/diskusi: fixer tunggal; importer Finance khusus SQL, tidak mengubah
  importer Penatausahaan, alur maker-checker atau batas upload HTTP 25 MiB.
- File Control baru: `tools/lib/FinanceReleaseRegistrar.php`,
  `tools/register_finance_release.php`, `tools/test_finance_release_registrar.php`.
- Perubahan: root CLI dengan operator OWNER/RELEASE_MANAGER aktif; signature
  dan semua byte diverifikasi sebelum koneksi Control. Import selalu DRAFT/ALPHA.
  Product lock + unique version guard, transaksi metadata/artefak/evidence/audit,
  penolakan overwrite byte berbeda dan replay identik UNCHANGED. Gagal sebelum
  commit membersihkan hanya salinan baru; hasil COMMIT yang tidak pasti
  mempertahankan file untuk read-back, bukan berisiko menghapus file committed.
- Validasi: 18 tes SQLite in-memory/fixture filesystem PASS, termasuk rollback
  di artefak kedua, peran salah, account nonaktif, versi sudah published,
  tamper hash, replay dan lost commit reply. Lint tiga file Control PASS.
- Eksekusi staging: alpha.3 cutoff b10fa37 diregistrasikan; public ID
  `03b7746d-2e50-442d-94f7-edcc0c47686c`. Tepat 3 artefak dan 2 evidence
  (SOURCE_CLEAN, SIGNATURE_VERIFY). Replay aktual UNCHANGED. Ketiga file
  terbukti readable sebagai `www`; root:www, file 0640/direktori 0750.
- Dampak DB: hanya INSERT metadata Control untuk release Finance baru,
  compatibility, artifact, evidence dan audit. Tidak membuat customer,
  deployment, token claim, aktivasi atau publish; tidak ada SQL/schema baru.
- Hasil review: DRAFT_REGISTERED. Tidak membuat evidence INSTALL_TEST,
  BACKUP_RESTORE/SECURITY_SCAN yang belum berasal dari pengujian kandidat itu.
- Risiko: alpha.3 belum mengandung perbaikan Batch 229–231; importer tidak
  mengganti artefak lama. Control masih tanpa HEAD; tidak commit seluruh repo.
  Bukti before/after, replay dan hash source berada di
  `/var/lib/finance-c3-20260909.tfDmtz/` (private).

## Batch 231 — Executor DB clean-install dan bug first-owner bootstrap

- Waktu: 2026-09-09 WIB. Prioritas A5/C3: buktikan jalur install tanpa
  menyentuh data transaksi aplikasi yang berjalan.
- Review/diskusi: fixer tunggal. Ditemukan literal bootstrap 206 halaman,
  241 menu dan 206 permission, berbeda dari approved policy 209/244/209.
  Ini bug script, bukan alasan mengedit isi menu/database customer.
- File: `tools/install/clean_install_database.php`,
  `tools/db/bootstrap_first_owner.php`, `tools/tests/c3_clean_install_database_smoke.php`,
  test bootstrap/quality-gate contract/registry, kedua roadmap dan panduan delivery.
- Perubahan: bootstrap memakai approved policy yang tervalidasi. Executor
  memverifikasi signature/source exact, private credential files, database
  kosong, runtime PHP/DB, lock, baseline, managed clean_install, first owner
  dan health. Tidak menerima credential di argv, tidak menjalankan upgrade
  melalui clean-install, dan tidak wipe/rollback DDL otomatis.
- Validasi kode: 18 boundary tests installer tanpa DB PASS; 14 first-owner
  contract PASS. Existing first-owner guard (kosong, fixed SUPERADMIN,
  kekuatan password, locking) tetap dipertahankan.
- Trial aktual: buat **database kosong baru**
  `c3_finance_test_d93fbfabffe0` dan akun terpisah dengan grant hanya database
  itu. Admin credential server dibaca internal dari konfigurasi panel yang
  sudah ada, tidak dicetak/disalin ke source/argv. File akun trial/owner
  acak 0600 disimpan di folder bukti privat; tidak mengubah config Finance.
- Hasil trial **BLOCKED / DATABASE_RUNTIME_UNSUPPORTED** sebelum DDL.
  Server socket dan TCP terverifikasi 10.11.10-MariaDB-log (port 3306),
  sedangkan signed manifest alpha.3 hanya mendukung 10.6. Database trial
  dikonfirmasi tetap **0 tabel**; tidak ada owner aplikasi dibuat, migration
  diterapkan atau transaksi customer dibaca. DB kosong/account/source trial
  dipertahankan untuk inspeksi, tidak menghapus runtime/backup/user data.
- Hasil review: executor/guard dan perbaikan bootstrap CODE_PASS, bukan
  INSTALL_TEST/owner integration/health PASS. Signature/kontrak alpha.3 tidak
  dilonggarkan agar bisa lewat. Penetapan runtime kandidat berikutnya ditanyakan
  ke owner (ikuti 10.11 server sekarang atau sediakan lingkungan 10.6 terpisah).
- Validasi gabungan final: release profile **PASS 109 entry** (99 required,
  4 development, 1 release-config, 2 runtime, preflight/security/static masing-masing
  1); DB probes staging SKIPPED. UAT browser/peran/APK/printer fisik tetap manual.
  Tidak ada SQL/schema baru, merge/push, deployment web/customer atau perubahan APK.
- Batch berikutnya: putuskan runtime, kemas kandidat baru (jangan overwrite
  alpha.3), ulang clean-install/owner/health, lalu upgrade/rollback disposable.
  Fixture yang dibuat di staging bukan bukti support MariaDB 10.11 sampai
  kandidat dengan kontrak dan pengujian yang tepat benar-benar lulus.
- Commit kode/laporan lokal: `0fe7ff93ecb61ba19637ff9e43635364f7c16e8f`;
  tag/cutoff artefak alpha.3 tetap b10fa37. Tidak push. Telegram ringkasan
  terkirim dan terkonfirmasi pada 2026-09-09 05:52:11 WIB.

## Batch 232 — Kontrak kandidat MariaDB 10.11 dan pemisahan client/server

- Waktu: 2026-09-09 WIB. Owner menjawab "ya lanjutkan" pada usulan mengikuti
  MariaDB 10.11 yang tersedia. Prioritas C3/A5, bukan bug operasional APK.
- Review fixer tunggal: CLI `mariadb --version` mengembalikan client 10.6.23,
  bukan server 10.11.10-MariaDB-log. Runtime guard sebelumnya benar menolak
  alpha.3; tidak boleh memalsukan PASS dengan memperlebar kontraknya.
- Perubahan: alpha.4 PHP 8.1/MariaDB >=10.11 <10.12; policy sesuai. Bridge
  mempertahankan verifikasi kandidat lama 10.6, namun mewajibkan kecocokan
  manifest/policy. Installer memakai window signed untuk SELECT VERSION()
  sebelum DDL dan mengikat hasil install pada SHA256 artefak/source commit.
  Probe CLI memisahkan mariadb_client dan memperingatkan server belum diuji.
  Registrar Control mengambil compatibility dari signed manifest; tidak
  mengubah metadata/byte DRAFT alpha.3 yang sudah ada.
- File Finance: `app-manifest.json`, runtime policy/check, ControlReleaseBridge,
  clean_install_database dan empat smoke test runtime/bridge/installer/C2-C4.
  Control: `tools/lib/FinanceReleaseRegistrar.php`, test registrar.
- Validasi awal: lint 9 PHP PASS; installer boundary 32, bridge 27, runtime
  contract 14, C2/C4 14, registrar Control 21 checks PASS. Quality parallel
  105 entry PASS. Staging runtime probe PASS dengan warning client-only,
  extension fileinfo tidak tersedia, Composer 2.0.14 di bawah rekomendasi.
- Cutoff lokal: `b63a2300b52aa62d82df8b9eb04c3f1a84cc6b00` (9 file kode),
  epoch 1788908386. Checkout bersih dan bukti privat:
  `/var/lib/finance-alpha4-20260909.epD7mD/`. Tidak push/merge.
- Database target yang telah disiapkan dikonfirmasi 0 tabel sebelum trial.
  Tidak membaca/mengubah transaksi Finance berjalan, credential, upload,
  backup, APK atau catatan lokal `_NOTE2.md`. Tidak ada SQL/schema baru.
- Status review: CODE_PASS; build/sign dan trial aktual dilanjutkan pada
  batch berikut. Belum merupakan INSTALL_TEST PASS atau siap jual.

## Batch 233 — Bug baseline indeks ganda dan kontrak jumlah menu

- Waktu: 2026-09-09 WIB. Prioritas A5/C3: menindaklanjuti kegagalan actual
  clean-install, bukan memperbaiki data customer atau bug operasional APK.
- Review fixer tunggal: alpha.4 berhasil membangun 296 tabel dan menerapkan
  11 migrasi, lalu gagal `client_failure`. Metadata target membuktikan dua
  indeks consume cashier/reservation sudah ada pada baseline, sedangkan SQL
  2026-09-06f/06g memakai ADD KEY tanpa IF NOT EXISTS. Ini benturan script.
- Perbaikan pertama: kedua definisi indeks dilepas hanya dari **baseline
  clean-install**. SQL managed 06f/06g beserta checksum dan ledger upgrade
  tidak diubah; kedua indeks tetap dibuat oleh migrasi resmi. Hash baseline
  dan coverage policy diperbarui; baseline_schema_version clean-install-20260909.
  Tidak menghapus indeks/tabel/data dari aplikasi berjalan.
- Alpha.5 dibangun/signed dari cutoff `a682e8539255dcadc524fb658ae5d32f591a34eb`,
  hash TAR `9ff3dca82a7046297908c0d236a6d653859d4462018395d367380d77fef28b02`.
  Di DB disposable baru `c3_finance_test_223c8ea83bae`, seluruh **16 migrasi**
  berhasil. Bootstrap kemudian ditolak `owner_seed`: policy menu 244 belum
  menghitung lima grup task-oriented sidebar; actual 249, halaman/permission 209.
- Quality alpha.5 juga menemukan satu assertion lama yang mengharuskan indeks
  reservation langsung ada pada baseline. Test diperbaiki agar memastikan
  field proof ada dan indeks hanya dibuat sekali melalui managed migration,
  bukan menghilangkan pengecekan indeks hasil akhir.
- Perbaikan kedua: post_apply_counts sys_menu=249; test first-owner/health
  fixture diselaraskan. Status owner tetap dibatasi database kosong, satu
  SUPERADMIN dan permission penuh. Tidak menambah/mengubah izin customer.
- File: baseline SQL, `app-manifest.json`, baseline/legacy-disposition policy,
  tests baseline, first-owner, post-install health, reservation refund dan
  commercial foundation. Schema bisnis akhir tetap finance-20260907, katalog
  tetap 16 managed/7 legacy; tidak ada SQL upgrade baru untuk server utama.
- Validasi perbaikan: baseline guard 20, first-owner 14, post-install health 13,
  legacy upgrade guard 19, reservation refund 14, C2/C4 14 checks PASS. Lint PASS.
- Alpha.4 dan alpha.5 tetap disimpan sebagai **kandidat gagal**, tidak diubah,
  tidak diregistrasikan/publish di Control. DB gagal dipertahankan tanpa wipe.
  Alpha.4 TAR hash `f11a962254614b5700f2bb84d3f31af0e626ffef207b4511bb8b8079dc4c1357`;
  target gagal pertama `c3_finance_test_d93fbfabffe0`, tanpa owner.
- Kandidat perbaikan alpha.6: cutoff `efc69b0e5e387bf16f6292e06997aacbcfd39db4`,
  epoch 1788909235, tag finance-web-alpha.6-cutoff-20260909. Checkout private
  `/var/lib/finance-alpha4-20260909.epD7mD/source` kini detached pada cutoff ini;
  source kandidat lama tetap dapat direkonstruksi dari tag/commit, arsip signed
  dan ekstraksi lama tidak diubah. Cache checkout digunakan kembali dengan
  gate normal, tanpa bypass. Build PASS 1.623 file.
- Target alpha.6 baru `c3_finance_test_67dc705cdc7b`, akun DB hanya memiliki
  grant database tersebut; private credential/owner file 0600. Secret admin
  tidak dicetak/disalin ke repo atau argv. File Control yang berubah disalin
  sebagai bukti privat karena repo Control belum memiliki HEAD.
- Batch berikut: buktikan alpha.6 install/owner/health, verifikasi indeks dan
  password owner, guard rerun, kemudian DRAFT Control. Belum publish/deploy web.

## Batch 234 — Health checker menghormati halaman panduan read-only

- Waktu: 2026-09-09 WIB. Prioritas A5/C3, kelanjutan trial database baru.
- Alpha.6 telah membentuk 296 tabel, 16 ledger migrasi, satu owner/SUPERADMIN,
  209 permission/halaman, 249 menu dan dua indeks consume yang tepat. Hash
  password owner berhasil diverifikasi terhadap credential fixture privat.
  Namun health lama menolak `superadmin_contract` hanya pada `tg.guide`.
- Review: SQL managed 2026-09-05b sejak awal **sengaja** memberi VIEW=1 dan
  CREATE/EDIT/DELETE/EXPORT=0 untuk halaman panduan statis. Memaksa semua
  action=1 pada setiap halaman adalah bug checker, bukan kekurangan izin user.
- Perubahan: predicate health hanya menerima matriks view-only untuk kode
  halaman exact/case-sensitive `tg.guide`. Halaman lain tetap wajib sesuai
  matriks full-action; izin view hilang tetap ditolak. Tidak mengubah role,
  permission, seed, atau isi database untuk meloloskan pemeriksaan.
- File: `tools/db/post_install_health_check.php`, test health, manifest versi
  alpha.7 dan test commercial foundation. Tidak ada SQL baru.
- Validasi: 15 health contract checks PASS termasuk permission gap negatif.
  Diagnostic read-only menggunakan checker yang diperbaiki pada fixture alpha.6
  PASS: 39 tabel wajib, 16 ledger exact, seed exact, owner aktif satu, Telegram
  default OFF. Ini diagnosis fix, **bukan klaim paket alpha.6 lulus**: executor
  yang dibundel alpha.6 tetap lama dan hasil install-nya tetap BLOCKED.
- Alpha.6 disimpan, tidak diregistrasikan di Control. SHA256 TAR:
  `0ef30a850add9f8fb1769cafe6664d1385af9098ee6bd86ef3a3759d59287a07`.
- Alpha.7 dibangun dari cutoff `68e01142821647f541a60818696989e30832442c`,
  epoch 1788909601; tag finance-web-alpha.7-cutoff-20260909. Tidak push/merge.
  Bukti/artefak berada pada folder batch privat yang sama; checkout source
  sekarang detached alpha.7. Seluruh arsip/signature versi sebelumnya utuh.
- Lanjutan: full clean-install menggunakan executor **yang dibundel alpha.7**
  ke DB baru `c3_finance_test_3ff68a92e9c3`, lalu negative permission SQL,
  owner/index validation, guard rerun dan registrasi DRAFT Control.

## Batch 235 — Alpha.7 clean-install DB PASS dan Control DRAFT

- Waktu: 2026-09-09 WIB. Scope penutupan batch: runtime 10.11, paket signed,
  database trial baru, bootstrap/health; bukan deployment web/publish/customer.
- Hasil final actual executor paket **PASS**: database server
  10.11.10-MariaDB-log cocok dengan signed contract >=10.11 <10.12; 296 tabel,
  16 migrasi diterapkan/0 skipped, owner_id=1, health 39 tabel wajib/16 ledger
  exact, seed exact, satu owner SUPERADMIN aktif. Tidak mengisi bisnis/transaksi.
- Read-only validasi lanjutan: satu user/assignment/role, 209 halaman dan
  permission, 249 menu. Password bcrypt owner cocok dengan private fixture,
  dua indeks consume memiliki urutan tujuh kolom yang benar. Tujuh kasus SQL
  permission aktual PASS (guide valid, view hilang, hak berlebih, halaman biasa
  kurang/penuh, beda kapital dan suffix); tidak mengubah permission untuk test.
- Negative rerun: executor yang sama menolak DB nonempty dengan
  DATABASE_NOT_EMPTY sebelum DDL. Tidak menduplikasi owner atau migration,
  tidak wipe database agar bisa retry.
- Artefak: 1.623 file, 597.268.480 bytes. Cutoff/tag alpha.7 pada Batch 234;
  SHA256 TAR `f8a4f9e34ac6aef6b8792999f377cf18c385c0ac14b9510f0dbc0b5e3be62803`;
  inner manifest `e8ab90f28e85f67944b4b7c48a67788b9029c242b6baafbad799cc42a1b20a1d`.
  Private folder `/var/lib/finance-alpha4-20260909.epD7mD/` menyimpan semua
  log, source/artefak signed dan daftar perubahan alpha7-changes-from-alpha3.tsv.
- Control: alpha.7 **DRAFT/ALPHA**, public ID
  `5d8d9ed2-0d66-49d0-80c8-730b7bd3d3f4`, tepat 3 artefak dan 2 evidence
  SOURCE_CLEAN/SIGNATURE_VERIFY. Import ulang aktual UNCHANGED. Compatibility
  10.11 berasal dari signed manifest. Ketiga file 0640 root:www teruji readable
  sebagai www. Alpha.3 masih DRAFT dengan hash/kontrak 10.6 semula; alpha.4–6
  yang gagal tidak dimasukkan ke Control.
- Evidence DB install di log ini **tidak** dipalsukan menjadi full web
  INSTALL_TEST, BACKUP_RESTORE atau SECURITY_SCAN pada Control. Publikasi,
  claim/receipt, customer/deployment/aktivasi lisensi tidak dibuat.
- Validasi gabungan final: quality profile release **109 entry PASS** (99
  required, 4 development, 1 release-config, 2 runtime, preflight/security/static
  masing-masing 1); lint seluruh PHP perubahan PASS. Checklist/dashboard docs:
  26 roadmap consistency + 30 dashboard checks PASS. Staging business probes
  SKIPPED, UAT browser/peran/APK/printer tetap manual. Tidak mengklaim aplikasi
  sudah tanpa bug atau siap jual hanya karena installer DB lulus.
- File log/checklist/panduan: dua roadmap utama, execution log ini,
  `docs/control_release_delivery.md`. Dokumen hasil batch berada setelah cutoff
  artefak dan ikut kandidat selanjutnya; arsip signed tidak ditulis ulang.
- Dampak SQL: hanya baseline khusus instalasi baru direvisi untuk dua indeks;
  semua 16 checksum SQL managed tetap. Tidak ada SQL baru yang harus dijalankan
  pada aplikasi/server utama lama. Empat DB trial baru/yang sebelumnya kosong
  kini disimpan sebagai bukti; tidak ada backup/upload/log/credential dihapus.
  Config database Finance, transaksi customer, source APK dan `_NOTE2.md` utuh.
- Hasil review fixer: **DB_INSTALL_PASS_WEB_PENDING**. Bug baseline indeks,
  hitungan menu dan health guide selesai dibuktikan; A5/C3 keseluruhan belum DONE.
- Sisa risiko/urutan berikut: deployment web terpisah dengan dependency terkunci,
  secret/folder/web-server lalu smoke login/onboarding; upgrade/rollback
  disposable; UAT peran/printer. Audit identitas/aset legacy C2, aktivasi C4,
  dan publish/pilot C5 mengikuti gate masing-masing. Fileinfo staging belum
  tersedia dan Composer 2.0.14 di bawah rekomendasi tetap tercatat. Bug operasional
  APK dan MFA tetap ditunda owner. Tidak push/merge Git.
- Commit laporan lokal `f68a930`; cutoff paket tetap `68e0114`. Ringkasan
  Telegram terkirim dan terkonfirmasi pada 2026-09-09 **06:27:20 WIB**.

## Batch 236 — Isolasi instalasi customer dan health upgrade

- Waktu: 2026-09-09 WIB; permintaan owner melanjutkan C0–C5. Fixer tunggal,
  review kode dan tes langsung; tidak mengulang A3 atau bug operasional APK.
- Prioritas: clean-install DB sudah lulus, tetapi runtime web masih mengacu
  session bersama, Host request dan `.user.ini` staging; health upgrade menolak
  receipt seed awal yang sah. Ini menghalangi trial customer terpisah.
- File: `application/libraries/DeploymentConfig.php`, `application/config/config.php`,
  `index.php`, `tools/install/LinuxWebProfile.php`, `tools/release/ReleasePackagePolicy.php`,
  `tools/release/package_policy.json`, `tools/db/post_install_health_check.php`,
  dua tes baru deployment/profile, tes health/artifact serta registry/contract quality gate.
- Perubahan: JSON privat root-owned opt-in, ENV eksplisit tetap prioritas,
  URL HTTPS tetap, cookie/session/log/cache terpisah; generic production 503
  untuk file privat invalid. Renderer Linux loopback TLS/socket privat,
  deny internal/script upload; dukungan direktori Lua vendor opsional.
  `.user.ini` staging dikecualikan dari paket, file asli tidak disentuh.
- Upgrade memverifikasi receipt seed historis tanpa menjalankannya ulang;
  checksum/metadata/count tidak cocok tetap ditolak, termasuk baris asing.
- Validasi: deployment instance 15, secret contract 39, Linux profile 16,
  health 17, artifact 12, quality manifest 28 pemeriksaan PASS; lint PHP PASS.
- Commit kode awal: `80d5a11`. Hasil review: layak diuji pada web terisolasi;
  tidak dianggap installer final Windows/customer atau handoff selesai.
- Risiko sisa/batch berikut: jalankan login/profil/logo pada HTTPS nyata,
  backup/restore database percobaan dan gate paket baru.

## Batch 237 — HTTPS nyata, bug User-Agent, dan restore database percobaan

- Waktu: 2026-09-09 07:06–07:16 WIB. Hanya lingkungan
  `/var/lib/finance-web-20260909.fDgGsM`, akun sistem baru `finance_c3_trial`
  (nologin), Nginx/PHP-FPM terpisah dan listener `127.0.0.1:18443`.
  Sertifikat self-signed khusus test diverifikasi dengan CA file; tidak memakai
  bypass TLS. Tidak reload layanan aplikasi lama atau membuka domain publik.
- Source awal diagnostic snapshot dari commit 80d5a11 mengikuti package policy;
  bukan artefak signed. Tambahan diagnostik log_threshold=1 hanya di salinan
  percobaan. DB sumber `c3_finance_test_3ff68a92e9c3` adalah fixture alpha.7,
  bukan database transaksi Finance; hanya owner/profil/logo sintetis dipakai.
- Temuan: request valid tanpa header User-Agent menghasilkan NULL dari CI,
  lalu TypeError pada audit login berparameter string. Diperbaiki dengan cast
  di `application/controllers/Auth.php`, tidak melewati audit/RBAC/throttle.
- File tambahan: `tools/tests/c3_linux_web_acceptance.php` (opt-in, CLI root,
  hanya URL loopback dan DB beridentitas disposable),
  `tools/tests/auth_login_throttle_smoke.php`; commit lanjutan `d5ff56e`.
- Validasi: **22 HTTPS acceptance PASS**, **74 login regression PASS**.
  Login owner tanpa User-Agent, profil/CSRF, logo upload+served image, branding
  login, cookie Secure/HttpOnly/SameSite, URL spoof Host dan 9 private paths
  ditolak teruji. Composer install --no-dev dari lock dan validate exit 0;
  Composer sistem lama menghasilkan deprecation warning, tidak diupgrade.
- Backup `trial-before-upgrade.sql`, SHA256
  `02c89800c3be83bc9e4dd62d18aaf9230625edf55cc6162602ac4376dd4455df`, mode 0600.
  Dipulihkan ke DB kosong baru `c3_finance_test_0471d94e5ffe` (upgrade)
  dan `c3_finance_test_8960e1072aea` (restore). Akun hanya punya grant ke DB
  masing-masing; kredensial tidak ditaruh di Git, argv atau output.
- **296 tabel sama checksum** pada kedua salinan dan sumber fixture tetap
  sama. Upgrade katalog sama: applied=0, skipped=15, ledger=16, owner=1,
  required tables=39, health PASS. Profil sintetis dipertahankan. Tidak
  mengulang seed, membuat owner baru, wipe database, atau switch layanan live.
- Bukti privat: `web-acceptance.json`, `upgrade-restore-acceptance.json`,
  checksum source, output percobaan yang gagal dan backup di folder di atas.
  Nginx/PHP-FPM percobaan dihentikan setelah HTTP test; backup/upload/log/DB
  percobaan tetap disimpan. Tidak ada SQL baru di aplikasi/server utama lama.
- Hasil review: HTTPS diagnostik dan restore/upgrader idempotent layak.
  **Belum** install dari artefak alpha.8, upgrade lintas versi bermigrasi baru,
  web cutover/rollback, UAT semua role/printer atau customer pilot.

## Batch 238 — Handoff, panduan customer dan audit gerbang C0–C5

- Waktu: 2026-09-09 WIB. Sesuai pola fixer tunggal, status direview dari bukti,
  tanpa membuat penerimaan owner atau publikasi secara otomatis.
- File: dua roadmap utama, execution log, `docs/customer_setup_and_release_guide.md`,
  `app-manifest.json` dan tes versi; kandidat source menjadi **0.1.0-alpha.8**,
  schema finance-20260907 tetap, 16 SQL managed/checksum tidak diubah.
- C0/C1: daftar keputusan paket, harga, implementasi/support, kontrak/SLA/data,
  domain/customer pilot, batas runtime dan go-live dipisahkan dari hasil tes.
  Harga/kontrak tidak dikarang sebagai final; pertanyaan owner sudah diajukan.
- C2/C5: contoh file deployment JSON, lokasi/owner/mode, environment pool,
  prioritas setting, runtime/session, latihan penerimaan dan rancangan SOP
  support. Pengaturan admin usaha lewat UI dipisah dari pekerjaan admin server.
- C4: protokol activation/status Control diperiksa read-only; **26 tes verifier
  PASS**, termasuk expiry maintenance vs lease, signed binding dan penolakan
  entitlement palsu. Tidak mengklaim activation/polling/cache writer selesai.
  Tidak ada aktivasi, penandatanganan lisensi, enforcement atau perubahan Control.
- Review: C0 HANDOFF_CHECKLIST_READY; C1 tetap CATALOG_DRAFT_READY;
  C2 CORE_BRANDING_HTTP_PASS; C3 ISOLATED_WEB_DB_REHEARSAL_PASS;
  C4 tetap AUDIT_ONLY_FOUNDATION; C5 SUPPORT_DRAFT_READY. **Seluruh fase
  belum DONE**, karena masih ada engineering dan acceptance, bukan hanya dokumen.
- Validasi dokumen: 26 roadmap consistency + 30 dashboard PASS. Perubahan
  `_NOTE2.md`, logo/upload pengguna dan pekerjaan APK dipertahankan.
- Gate rilis pertama cutoff d5ff56e FAIL pada dua tes yang bergantung file
  staging tidak tracked: backup membutuhkan `.env`, dashboard mewajibkan
  marker enable. Tidak menyalin secret/marker untuk memaksa PASS. Test backup
  kini memeriksa template untuk checkout bersih dan `.env` hanya dengan flag
  `--staging-env`; kedua mode 32 checks PASS di workspace. Dashboard mengecek
  fail-closed bila marker tidak ada; negatif/valid fixture tetap wajib.
  Test artifact kini benar-benar memasukkan `.user.ini` sintetis dan membuktikan
  file itu tidak masuk arsip. Tidak mengurangi gate keamanan aplikasi.
- Berikutnya: ulang gate penuh dari cutoff baru, bangun/verifikasi kandidat;
  lanjut C3 installer/upgrade lintas versi dan C4 setelah identitas/profil
  target jelas. C1/C5 harga/kontrak/domain/pilot/publikasi tetap keputusan owner.

## Batch 239 — Alpha.8 signed dan uji web langsung dari paket

- Waktu: 2026-09-09 07:22–07:33 WIB. Cutoff final
  `39a82105ef9003d91f22595b10d661711381029d`, epoch 1788913376,
  tag `finance-web-alpha.8-cutoff-20260909`; cutoff d5ff56e tidak ditandatangani.
  **24 file** berubah/bertambah sejak awal turn `647c389`; daftar nama/status
  disimpan `alpha8-changes-from-previous-turn.tsv` pada folder bukti Batch 237.
- Gate penuh dari checkout bersih: **111 entry PASS** (101 required,
  4 development, 1 release-config, 2 runtime, preflight/security/static masing-masing
  1). PHPStan seluruh application PASS, baseline nol; probes DB operasional
  SKIPPED. Semua 18 PHP perubahan lint PASS. Tidak ada bypass gate atau copy
  secret/marker staging ke checkout release. Composer validate exit 0.
- Build/sign/verify PASS: `finance-0.1.0-alpha.8.tar`, **1.626 file**,
  **597.309.440 bytes**, SHA256
  `523b2234e219ec764973549987bd26f0a649ee55e91af385d8b1ab8a8670078f`;
  inner manifest
  `5c5c125b7f72c8b29c2e9ce864e3f73a813bc70d0e5a80bfc078b7b3c9556e8a`.
  Arsip/sidecar/log final ada di `/var/lib/finance-web-20260909.fDgGsM`.
  Key release Finance lama dipakai, tidak dirotasi atau disalin ke aplikasi.
- Ekstraksi verified alpha.8 dan runtime percobaan baru di
  `/var/lib/finance-web-20260909.uRE66a`. Database hanya salinan sintetis
  `c3_finance_test_0471d94e5ffe`. Encryption key dipertahankan dari percobaan
  sebelumnya untuk mencontohkan upgrade, bukan diganti setiap versi.
- Temuan provisioning: umask privat 0077 membuat 228 parent direktori kode
  hasil TAR menjadi 0700; request awal 500/permission denied. Diperbaiki
  hanya pada direktori root-owned yang terdaftar sebagai parent source signed,
  menjadi 0755. Tidak ada byte source, izin upload, credential atau data diubah
  untuk mengatasi masalah itu. Helper ekstraksi dan panduan diperbarui; tidak
  chmod rekursif aplikasi utama atau mengubah arsip signed.
- Composer install --no-dev dari lock exit 0; warning Composer 2.0.14 tetap
  dicatat. Nginx/PHP-FPM config test PASS, **22 HTTPS acceptance PASS** dari
  paket, tanpa edit source termasuk config.php/Auth.php. Setelah save logo/profil,
  health memverifikasi ulang 1.626 file, 39 tabel wajib, 16 receipt, 1 owner PASS.
  `signed-web-acceptance.json` mengikat hasil HTTP dan health ke hash paket/cutoff.
- Control read-back: alpha.8 **DRAFT/ALPHA**, public ID
  `4811a549-6525-4c80-ab6b-6b4cb5a83eed`, 3 artefak, 2 evidence
  SOURCE_CLEAN/SIGNATURE_VERIFY. Registrasi ulang UNCHANGED. Tidak membuat
  evidence full customer INSTALL_TEST/BACKUP_RESTORE dari tes parsial,
  tidak publish, claim/receipt, customer, kontrak atau lisensi baru.
- Kedua layanan HTTPS trial telah dihentikan; semua folder, backup, fixture DB,
  credential, log dan upload percobaan dipertahankan. Tidak mengubah layanan,
  database, upload maupun setting staging operasional. Tidak ada SQL baru,
  push/merge Git, rotasi secret atau intervensi transaksi pengguna/APK.
- Dokumen setelah cutoff: dua roadmap, log ini dan control delivery guide.
  26 roadmap consistency + 30 dashboard PASS diperiksa kembali sesudah
  finalisasi. Hasil review: **SIGNED_WEB_UPGRADE_TRIAL_PASS**, bukan seluruh
  C0–C5 selesai. C0/C1/C5 keputusan/pilot belum final; C2 marketing/preset dan
  UAT, C3 installer/cutover/upgrade lintas versi, C4 activation/poll/cache,
  replay/native/device/limit/enforcement masih terbuka. Panduan per modul dan
  latihan awam belum dinyatakan lulus. Windows/APK/printer tidak disertifikasi.
- Langkah berikut yang perlu arah owner: pilih customer/domain/instance dan
  paket pilot serta lokasi keputusan harga/kontrak/SLA. Jangan mem-publish
  paket atau mengaktifkan enforcement untuk menyiasati gerbang yang belum lulus.
- Laporan lokal commit `d682029`; cutoff paket tetap `39a8210`. Ringkasan
  hasil dan sisa C0–C5 dikirim Telegram, terkonfirmasi **07:34:51 WIB**.

## Batch 240 — Persiapan agen aktivasi dan signed cache Finance

- Tanggal: 2026-09-09; validasi/review akhir kode **09:16–09:22 WIB**.
  Arahan owner: siapkan barang lebih dahulu; harga/kontrak/customer, aktivasi
  dan praktik penjualan akan owner jalankan melalui UI Control. Keputusan
  penjualan tidak lagi dipakai sebagai penghambat engineering.
- Prioritas C4: verifier sudah ada tetapi Finance belum mempunyai pengirim
  aktivasi/polling dan writer cache. Fixer tunggal membandingkan kontrak
  API/issuer Control read-only, lalu mengimplementasikan adapter NAMUA_FINANCE.
  Tidak menyalin agen hardcoded produk lain atau mengubah Control.
- File kode: `application/libraries/Control_license_cache.php` (baru),
  `Control_license_verifier.php`, `application/models/License_runtime_model.php`,
  `application/controllers/License.php`, `application/views/system/license_index.php`.
  Baru: `tools/licensing/ControlLicenseProtocol.php`, `LicenseAgentFiles.php`,
  `FinanceLicenseAgent.php`, `finance_license.php`, dan dua template di
  `tools/licensing/systemd/`. `app-manifest.json` maju alpha.9; schema tetap
  finance-20260907 dan SQL managed/checksum tidak berubah.
- Perubahan: identitas Ed25519 persisten sebelum request sekali pakai; input
  kode dari file privat, bukan argv. HTTPS origin eksplisit, signed polling
  sama kontrak Control, tanpa redirect dan dengan batas waktu/ukuran respons.
  Lisensi diverifikasi sebelum publish cache; envelope dan watermark bersama
  pada satu file atomik. Poll token/private instance key hanya root.
- Model membaca cache root-owned di luar webroot, signature/binding/watermark
  diperiksa ulang. Cache yang eksplisit tetapi rusak tidak fallback ke SQL.
  Instalasi lama tanpa env cache mempertahankan jalur read lama. UI status
  menampilkan instance/sambungan/sinkron terakhir; RBAC dan AUDIT_ONLY tidak
  diubah. Tidak mengaktifkan enforcement atau membuat tombol bypass lokal.
- Validasi: **54 checks** `c4_control_license_agent_smoke.php` PASS, termasuk
  protokol, signature, maintenance vs lease, offline/grace, replay dan clock
  rollback lintas restart, revoke, konkurensi, machine binding, state/key
  tidak dirotasi saat timeout, actual model tanpa DB dan proses anak akun www
  yang bisa membaca cache tetapi tidak bisa mengubahnya/membaca private key.
  Test menghapus hanya fixture sintetisnya sendiri di prefix khusus
  `/var/lib/finance-license-test-*`; tidak menghapus runtime/backup pengguna.
- **26 verifier + 14 foundation + 28 quality contract + 26 roadmap + 30 dashboard
  checks PASS**, PHPStan application PASS baseline/errors nol. Quality registry
  menambah 22 checks protocol-only agar wajib dan portabel; acceptance lengkap
  file/model Linux-root dijalankan terpisah, tidak disamakan cakupannya.
- File tes: smoke agen baru, `finance_quality_gate.php`, contract-nya, dan
  `c2_c4_commercial_foundation_smoke.php` untuk versi. Dokumen: dua roadmap,
  panduan customer bagian 4/7/9 dan log ini. Panduan memisahkan tindakan
  penjual via UI dari admin server; template scheduler **belum dipasang**.
- Review fixer: public trust memakai whitelist (private/unknown field ditolak),
  koneksi gagal tidak menghapus cache sah, UI tidak mengklaim lisensi tersedia
  hanya karena network gagal. Runtime root harus menjalankan source verified
  yang tidak dapat ditulis web. Tidak ada proof melawan root memulihkan seluruh
  private snapshot+clock; native guard masih terpisah.
- Risiko sisa: belum acceptance HTTPS dengan Control nyata/issuer worker,
  recovery aktivasi yang responsnya hilang masih manual review, Windows ACL,
  native guard/pairing/limit/enforcement dan UAT belum lulus. **C4 tetap
  IN_PROGRESS / AGENT_CACHE_FIXTURE_PASS**, bukan C0–C5 selesai.
- Tidak ada SQL baru, query/mutasi bisnis, perubahan login/POS/APK, restart
  layanan staging, aktivasi customer, publish/push atau secret/key rotation.
  `_NOTE2.md` dan dua folder upload milik pengguna tetap di luar perubahan.
- Berikutnya: paket kandidat dari cutoff terseleksi dan gate penuh; setelah
  itu installer/cutover/rollback serta acceptance integrasi yang belum lulus.
  Penjualan/publikasi nyata tetap menunggu praktik owner di UI Control.

## Batch 241 — Paket alpha.9 privat sebelum praktik penjualan owner

- Waktu: 2026-09-09 **09:22–09:27 WIB**. Cutoff source
  `4d315484cdc60bdaf1894885c8312c167e829cad`, tag
  `finance-web-alpha.9-cutoff-20260909`, epoch `1788920512`.
  Commit lokal memuat 20 file; daftar A/M dari HEAD awal turn `e2ed1fd`
  disimpan pada `changes-from-e2ed1fd.tsv` di folder bukti.
- Checkout build bersih; `_NOTE2.md` dan kedua folder upload pengguna tidak
  diikutsertakan. Tidak push/merge atau mengubah alpha.8 yang sudah signed.
- **112 entry quality gate release PASS**: 102 required, 4 development,
  1 release config, 2 runtime, preflight/security/static masing-masing 1.
  PHPStan application baseline/errors nol. Probe DB bisnis SKIPPED dan manual
  UAT peran/APK/printer masih pending, tidak diklaim PASS dari gate ini.
- Full `c4_control_license_agent_smoke.php` di checkout bersih: **54 PASS**;
  `--protocol-only` dalam gate berisi 22 checks, bukan bukti acceptance file.
  **13 PHP perubahan php -l PASS**. Composer validate exit 0; warning
  deprecation Composer 2.0.14 lama tetap dicatat, dependency tidak diubah.
  Systemd verify template service/timer exit 0; warning `RestartMode` berasal
  dari unit snapd host yang tidak disentuh. Tidak memasang/menjalankan service.
- Build/sign/verify: **1.634 file**, **597.381.120 bytes**; TAR SHA256
  `8ed5ff28bc7c787f3de197f9e1721e623d2f340add8c1aeb8338325d7bd90bc7`;
  inner manifest SHA256
  `b7035a05bf29bf8d37b8149b4255bbc319bc6be7f0b511f81624a6d8b1ac9a11`.
  Verifier Finance dan `/control/tools/verify_finance_release.php` read-only
  keduanya PASS. Kunci release Finance lama digunakan tanpa rotasi.
- Paket `finance-0.1.0-alpha.9.tar`, sidecar, log gate/build/agen dan daftar
  perubahan ada di **`/var/lib/finance-release-20260909.WpqNVe`**, root 0700.
  Tidak register DRAFT baru, publish, claim/receipt, subscription/customer,
  aktivasi nyata, issuance worker, atau perubahan database Control.
- SQL: tidak ada SQL baru/dijalankan. 16 managed SQL dan kontrak schema tetap;
  7 legacy dalam deskripsi paket bukan instruksi menjalankan seluruh folder.
  Server utama lama tidak disentuh; trial nanti memakai database salinan.
- Review fixer: **INTERNAL_CANDIDATE**, bukan barang final/go-live atau
  C0–C5 DONE. Belum deployment/HTTPS alpha.9; bukti versi alpha.8 tetap disebut
  versi alpha.8. Status C4 AGENT_CACHE_FIXTURE_PASS dipertahankan.
- Risiko/berikutnya: installer layanan + cutover/rollback + upgrade lintas versi,
  integrasi aktivasi/recovery, native guard/pairing/limit/enforcement, panduan
  per modul dan penerimaan pengguna. Harga/customer/publikasi tidak dijadikan
  alasan menghentikan engineering; praktik komersial tetap milik owner via UI.
- Dokumen hasil setelah cutoff: kedua roadmap, delivery guide dan execution
  log ini. Paket signed tidak ditimpa untuk memasukkan catatan hasil build.
- Laporan lokal `ba32806`; notifikasi ringkasan hasil/batas kesiapan terkirim
  Telegram dan terkonfirmasi **2026-09-09 09:27:21 WIB**. Tidak ada push Git.
## Batch 242 — 2026-09-09: sambungan Control dan executor praktik Linux

- Prioritas: tuntaskan engineering untuk praktik owner di UI; bukan penjualan/publikasi otomatis.
- Pelaksana tunggal: telusuri kontrak Finance–Control, implementasi kecil dan test aktual terisolasi; tidak mengulang audit bisnis/APK.
- Finance: `tools/licensing/{ControlLicenseProtocol,FinanceLicenseAgent,finance_license}.php`; `tools/install/{PrivateDeployment,ControlDelivery,FinanceInstance,control_delivery,finance_instance,LinuxWebProfile}.php`; test c3/c4 dan registry gate; manifest alpha.10 tanpa SQL baru.
- Control (perubahan terpisah, source lama dibackup): route/CSRF recovery; controller/model aktivasi; issuer/config privat khusus fixture; receipt controller/model; controller/model/view deployment untuk token pengganti dengan step-up, CSRF, alasan dan konfirmasi installer lama berhenti.
- Perbaikan: recovery aktivasi memakai kunci instance lama, lease RESTRICTED dapat diperbarui setelah kembali online, pemeriksaan eligibility worker, format ID migrasi Finance diterima; receipt migrasi tidak menutup deployment sebelum aktivasi web.
- Installer: verifikasi paket/source, database kosong untuk pemasangan baru, database salinan dan backup terverifikasi untuk upgrade, source/runtime terpisah, dependency/upload, layanan HTTPS loopback dengan PID milik konfigurasi sendiri. Tidak menghapus DB/upload/backup; kegagalan ambigu dipertahankan untuk inspeksi.
- Bukti aktual: Control salinan di `/var/lib/finance-control-20260909.HusH3Z`, DB `finance_ctl_test_ed8fa9f24b35`; 18 tes HTTPS lisensi PASS. Alpha.9 signed dipasang lewat executor baru di `/var/lib/finance-web-20260909.8kCfz6`, DB baru `c3_finance_test_626455afbd41`; baseline/16 migrasi/owner/health serta 22 tes HTTPS UI PASS.
- Delivery aktual alpha.9: 597.381.120 byte melalui HTTPS, tiga artefak verified, 9 tes claim/download/receipt/replay/idempotensi PASS; bukti `delivery-8072f90227b4/acceptance.json` dalam fixture Control.
- Unit: 58 agen/model/izin PASS dan 19 kontrak delivery PASS. Gate release/final paket alpha.10 serta upgrade/rollback lintas versi dicatat pada batch berikut setelah dijalankan, belum diklaim pada batch ini.
- Risiko sisa: fixture bukan customer publik; enforcement/native guard, Windows, APK/printer/UAT produksi tetap terpisah. Tidak memakai DB transaksi Finance, tidak menjalankan worker pada subscription nyata.
- Cutoff sebelum pekerjaan: `fa6241e`; source paket alpha.9 tetap immutable `4d31548`. Perubahan pengguna `docs/_NOTE2.md` dan folder upload tidak ikut commit.
- Berikut: build/sign alpha.10, uji upgrade/cutover/rollback, registrasi DRAFT berbukti dan panduan praktik UI owner. Tidak push/publish/aktivasi nyata.

## Batch 243 — 2026-09-09 11:02 WIB: barang dan UI siap praktik terbatas

- Prioritas: penuntasan kandidat web Linux, pemasangan, update/pemulihan, bukti Control dan panduan owner. Pengerjaan/review tunggal sesuai arahan terbaru; tidak ada diskusi subagent atau pengulangan audit bisnis.
- Hasil: **LINUX_UI_PRACTICE_READY**, bukan C0–C5 DONE produksi. Owner memegang praktik customer/instance/subscription, keputusan release/publish, aktivasi dan domain. Tidak ada customer/aktivasi/publish nyata yang dibuat otomatis.
- Source Finance: cutoff final **15f9f62849e3ba91bd029a53acd0686748e93f39**, tag **finance-web-alpha.10-verified-cutoff-20260909**, epoch **1788925696**. Commit awal f7b92f1 dan 27b93b5 dipertahankan; tag awal alpha.10 menunjuk kandidat yang gagal pemindai username fixture, bukan paket final. Identitas owner fixture kemudian diacak; pemindai dan baseline tidak dilonggarkan.
- Paket privat `/var/lib/finance-release-20260909.5hPSTs/finance-0.1.0-alpha.10.tar`: **1.645 file, 597.483.520 bytes**, SHA256 **e10d6afd29deb611e40fab25206f51404a90586cf46ad79760c9d7edcec9458d**. Manifest Control **52d51ac39f9bf29a5f64c9a43caa3907782c7583bfc52bc2bddd89162523e2f3**, inner manifest **4dbd9a700a0829c148c3a01c262e321aee212324d7c2b0e804ce82778d4a088a**. Alpha.9 dan semua backup terdahulu tidak diubah.
- Validasi: **113 entry quality release PASS dari checkout bersih** (103 required + 4 development + 1 release + 2 runtime + preflight/security/static). Preflight lint **893 PHP**, satu JS dan tiga Python; Finance/Control verifier PASS. 58 unit agen/model/file/izin PASS; 19 kontrak delivery PASS. Composer validate valid dengan peringatan deprecation CLI Composer 2.0.14; tidak meng-upgrade binary global.
- Clean install signed alpha.10: `/var/lib/finance-web-20260909.cszJso`, DB baru **c3_finance_test_619ee54de0da**, 296 tabel/16 migrasi/satu owner, health exact dan **22 tes HTTPS UI PASS**. Tool dipakai dari source cutoff terverifikasi; kode hasil ekstraksi tidak diedit.
- Upgrade signed alpha.9→alpha.10: `/var/lib/finance-web-20260909.PnTYyw`, DB salinan baru **c3_finance_test_d2f3fc45cb0e**, sumber alpha.9 `.8kCfz6` berhenti dahulu. **296 checksum tabel identik** setelah restore/migrasi; satu logo customer disalin identik. **22 tes HTTPS**, pembacaan cache lisensi asli Control via halaman Finance, preservasi DB lama saat UI baru diuji, stop/start old web dan health rollback PASS (**8 pemeriksaan cutover**).
- Backup upgrade **734933cf6335acc4c25ed3bda611bec5fc9b178d46b920a29f297d8b522aeb65** (`PnTYyw/private/before-upgrade.sql`). Bukti `rollback-acceptance.json` dan `practice-evidence.json` berada dalam folder paket; snapshot konfigurasi/key/DB/upload lama tidak dihapus. Schema sama finance-20260907, bukan bukti migrasi bisnis baru di masa depan.
- Control aktual-code fixture final: `/var/lib/finance-control-20260909.wmwnYi`, DB baru **finance_ctl_test_45b59ccffc96**, 19 migration Control, data customer/owner/edisi/aktivasi/key sintetis. **18 tes lisensi HTTPS**, **9 delivery/receipt paket alpha.10**, **12 form UI deployment/reissue**, **2 akses guide** PASS. Folder `delivery-8b239f0a8499` dan `ui-840d74aef460` menyimpan bukti. Simulasi status PUBLISHED hanya di DB fixture, bukan Control operasional.
- Control: ditambah recovery aktivasi, pembatasan worker lewat `CONTROL_LICENSE_PRODUCT_CODE`, pemeriksaan eligibility/dokumen ulang di dalam lock agar worker bersamaan tidak menerbitkan payload berbeda pada timestamp sama, renewal setelah RESTRICTED; fixture produk terfilter menghasilkan issued=0/refreshed=0 saat tidak ada kandidat due. Worker **tidak dijalankan pada subscription operasional**.
- Kunci penerbit khusus **NAMUA_FINANCE** disiapkan root-only; public key ID **896dfa0c-2673-4987-92de-3679a0bf3a15**. Bukan key fixture, bukan aktivasi customer, tidak dimasukkan ke TAR Finance; private key tidak dicetak atau diserahkan ke web.
- Control operasional: registrasi teknis **DRAFT/ALPHA release 38**, UUID **cdcbd62d-f030-4842-b2f3-db446f03c829**, tiga artefak. CLI evidence mencatat SECURITY_SCAN/INSTALL_TEST/BACKUP_RESTORE dari bukti aktual terikat versi/hash; bersama SOURCE_CLEAN/SIGNATURE_VERIFY menjadi **lima PASS**. Read-back sesuai; replay evidence UNCHANGED. Tidak approve/publish.
- UI baru Control **/finance/practice** (izin releases.view), tombol **Panduan praktik Finance** di Release, kandidat/status/evidence dinamis dan langkah owner berurutan. Form token pengganti memakai izin execute, POST, CSRF, step-up, alasan 10–500 karakter serta konfirmasi installer lama berhenti; token lama dicabut, rencana/persetujuan tetap. Endpoint publik guide diuji HTTP307 ke login tanpa autentikasi.
- File Control berubah/baru **15**: config/config.php, config/routes.php; controllers/Api_license_activations.php, Api_deployment_receipts.php, Deployments.php, Finance_practice.php; models/License_activation_model.php, Deployment_receipt_model.php, Deployment_model.php; views/deployments/detail.php, releases/index.php, releases/finance_practice.php; tools/process_license_issuance.php, lib/license_issuer_config.php, record_finance_trial_evidence.php. Semua php -l PASS.
- Repo Control belum mempunyai commit baseline dan tidak diinisialisasi ulang/di-commit massal. Backup sebelum edit di `/var/lib/finance-practice-20260909.cS9jNa/control-before/`; checksum sesudah di `control-after.sha256`; handoff 15 source terarah `control-finance-contract.tar` SHA256 **6e841cd5aa9769e89eaf4f50f7db89ea6a66afeb93e0f2bcac1af80d2c0f4645**. Bundle privat tidak berisi key/DB/runtime dan jangan diekstrak membabi-buta ke Control versi lain.
- Catatan/cutoff: `changes-from-e2ed1fd.tsv` (seluruh delta sejak checkpoint audit) dan `changes-batch242.tsv` (delta tugas ini) di folder paket. Dokumentasi hasil setelah cutoff tidak ditambahkan dengan menimpa artefak signed; kedua roadmap, delivery guide, setup guide dan log diperbarui terpisah.
- SQL: **tidak ada SQL baru**; hanya migration yang sudah ada dijalankan pada DB fixture baru. Tidak membaca/merepair data transaksi Finance lama, tidak mengubah konfigurasi DB staging, tidak mengintervensi APK. Perubahan pengguna `docs/_NOTE2.md` dan folder upload tetap utuh/tidak di-commit.
- Penutupan layanan: semua listener percobaan 18443/18444 dihentikan melalui PID/config yang divalidasi; pemeriksaan socket kosong. Nginx/PHP-FPM Finance dan Control operasional tidak direstart. DB fixture, artefak gagal, backup, log dan credentials percobaan dipertahankan privat.
- Risiko sisa: trial Linux bukan production pilot, proteksi native/enforcement/pairing/limit penuh belum diterima, Windows/APK/printer fisik/UAT peran ditunda. Domain publik, vhost dan scheduler target dipasang setelah target latihan ditentukan; rollback setelah transaksi baru memerlukan rekonsiliasi/persetujuan owner. Harga/kontrak/support final tidak dikarang.
- Berikut **praktik owner**, bukan batch audit berulang: Control → Release → Panduan praktik Finance → Periksa paket dan bukti (release 38); ikuti customer/instance/subscription dan maker-checker sebelum publication trial. Admin membantu instalasi pada target yang dipilih. Panduan server konkret ada pada `customer_setup_and_release_guide.md` bagian 10.
- Handoff dokumen commit lokal **a2774e8**, tanpa push. Ringkasan hasil dan batas kesiapan terkonfirmasi terkirim Telegram **2026-09-09 11:04:35 WIB**; socket trial 18443/18444 diperiksa kembali kosong.

## Batch 244 — 2026-09-10: paket CUSTOMER_CLEAN, Finance saja

- Prioritas: paket jual tidak boleh membawa master/transaksi/foto/branding Namua, sementara Finance tetap menjadi development/debugging dengan data asli utuh.
- Analisis/implementasi: fixer tunggal sesuai pola terbaru. DB baseline sudah kosong dan reference seed terkurasi; celah utama adalah include-root yang memasukkan menu statis/foto/roastery, docs/sql repair, tools truncate dan konfigurasi printer lokal. Tidak mengedit source/database Control yang sedang dikerjakan thread lain.
- Kontrak bersama: `docs/customer_clean_release_contract.md`. Profil `CUSTOMER_CLEAN` v1, seed `REFERENCE_ONLY`, default tanpa dummy bisnis. Tetap tiga artifact, inner manifest schema 1, sidecar v2 dan signature yang sama. Tambahan field metadata/claim serta hash profil dijelaskan persis untuk Control.
- Packaging: `CustomerReleaseProfile.php` + `customer_clean_profile.json` mengatur allowlist eksplisit 1.108 file; 319 aset generik dan 17 SQL dipin SHA256. Ukuran source terpilih sekitar 54 MB sebelum overhead TAR; bukan ukuran artifact published. Profil berisi schema, 16 managed migrations, reference sistem dan template generik; SQL/data staging tidak digenerate ulang.
- Builder default CUSTOMER_CLEAN; LEGACY_INTERNAL hanya reproduksi internal. Build di salinan privat, wajib source bersih, source berubah selama build ditolak. Tidak menghapus/menulis ulang foto, upload, backup, log, config DB atau data staging.
- Audit TAR: path, kelengkapan, checksum asset/SQL, jumlah file, artifact SHA, inner-manifest SHA dan profile SHA. Paket tidak boleh mengesahkan allowlist sendiri. Klaim bersih paket baru hanya setelah audit; signature historis tetap dapat diverifikasi dengan eligibility false/NOT_AUDITED. Alpha.10 tidak ditimpa; versi source dinaikkan ke 0.1.0-alpha.11, schema tetap finance-20260907.
- Installer: clean-install menolak paket tanpa profil sebelum koneksi DB. Katalog tetap menyimpan nama legacy sebagai histori, tetapi customer archive tidak menyertakan file repair tersebut. Runner membaca metadata JSON saja, tidak mengeksekusi PHP artifact saat validasi katalog. Upgrade tidak menjalankan reference seed khusus first-install.
- Delivery Finance: field profil, versi integer, seed dan hash dari claim wajib cocok dengan hasil signature/audit. Control lama belum boleh mengirim paket baru tanpa metadata tersebut; tidak ada downgrade diam-diam.
- UI: Menu Book fallback ke katalog customer bila desain lama tidak ada; opsi legacy hanya tersedia bila file desain/asetnya tersedia. Login/sidebar/favicon/slip/label menggunakan placeholder netral untuk fallback kosong; printer tidak memakai URL logo lama yang hilang atau SVG. Logo/profile/override milik staging tidak ditulis ulang.
- File kode berubah: app-manifest.json; application/controllers/Pos.php (hanya default logo); libraries/Customer_publication.php, PosPrinterPreviewService.php; models/Business_profile_model.php, Pos_print_model.php; views/auth/login.php, layout/header.php, layout/sidebar.php, payroll/salary_slip.php, roastery/coffee_packaging_label_index.php, system/business_profile.php, templates/_header.php, templates/_sidebar.php; assets/img/business-placeholder.svg.
- File tooling berubah/baru: tools/release/CustomerReleaseProfile.php, customer_clean_profile.json, customer_content_audit.php, ControlReleaseBridge.php, build_release_artifact.php, control_center_release_preflight.php; tools/db/migration_runner.php; tools/install/ControlDelivery.php, clean_install_database.php.
- File test: c3_customer_clean_release_smoke.php (baru), c3_control_release_bridge_smoke.php, a4_release_artifact_contract_smoke.php, c2_c4_commercial_foundation_smoke.php, finance_quality_gate.php, finance_quality_gate_contract_smoke.php, gap01_repository_runtime_boundary_smoke.php.
- Dokumen: kedua roadmap diperbarui tanpa menduplikasi checklist komersialisasi ke audit; contract, control_release_delivery, customer_setup_and_release_guide dan log ini diperbarui.
- Validasi terarah PASS: 56 customer-clean fixture; 29 release bridge/historical signature; 12 deterministic artifact; 43 migration catalog; 32 empty-DB boundary; 20 baseline/seed guard; 23 publication; 10 printer upload; 14 C2/C4; 29 repository boundary; 28 gate contract. PHP lint untuk 28 file PHP berubah/baru PASS. Composer validate PASS dengan deprecation Composer lama; dependency/lock tidak diubah. PHPStan application baseline nol PASS.
- Review tes: run menyeluruh awal menemukan assertion versi lama, daftar gate yang perlu menambahkan tes baru, larangan include-PHP pada runner, dan asumsi Git harus kosong dari backup. Versi/count diselaraskan; runner tetap JSON-only (bukan melonggarkan larangan eksekusi PHP). GAP-01 disesuaikan dengan keputusan user mempertahankan backup development/history: setiap payload runtime di index harus ditolak sekaligus oleh base policy DAN clean profile; larangan .env/bytecode/dump di luar boundary backup tetap. Tidak menghapus backup atau mengurangi secret scanner.
- Validasi release menyeluruh final **2026-09-10 21:20 WIB PASS**: 114 entry (104 required, 4 development, 1 release, 2 runtime, preflight, security, static), tidak ada staging DB probe. Browser shell dan printer-agent HTTP fixture PASS; UAT peran/printer fisik tetap pending. Run final diulang setelah assertion versi diselaraskan.
- Cutoff source sebelum batch: `26a8dc029677f0102ba4ff801426f085cd2a8b3f`. Handoff sesudah batch: commit lokal `feat(release): isolate customer-clean Finance distribution`, tag source `finance-web-alpha.11-clean-profile-source-20260910` (bukan tag artifact verified). Tidak push; Control lokal memakai checkout/tag ini, Control remote perlu commit tersebut tersedia dahulu.
- SQL: tidak ada migration baru; tidak mengeksekusi SQL pada Finance/Control operasional. Tes membuat/menghapus hanya file dan Git/TAR sintetis miliknya sendiri di direktori temporer. Tidak menjalankan build ulang seed dari database.
- Risiko sisa: profile v1 adalah allowlist kurasi, bukan jaminan semua bug/UI/UAT sudah selesai. Jika menambah file runtime/aset/SQL, update versi profil secara terarah dan selaraskan validator tepercaya. Publisher/customer DB benar-benar baru dan upgrade-preservation harus diuji lagi dari artifact kandidat baru, bukan memakai bukti alpha.10. Composer host lama masih memberi deprecation.
- Berikutnya: thread Control menyelesaikan UI/build/gate/claim berdasarkan contract; lalu build/sign/register kandidat alpha.11 dari cutoff bersih dan latihan UI Control di instance terisolasi. Tidak publish/deploy Control atau push Git oleh batch ini.

## Batch 245 — 2026-09-12: adapter build Finance ↔ Control dan profil customer

- Prioritas: profil customer hanya terbaca internal; selaraskan Finance dengan kontrak Control terbaru tanpa overlap source/DB Control atau membersihkan Finance development.
- Diskusi/review: fixer tunggal sesuai arahan terbaru. Membaca kontrak build V1, worker, parser manifest, model build dan delivery gap Control secara read-only. Kode Control memiliki empat field distribution pada plan/claim dan nama sidecar Finance, tetapi masih menahan CUSTOMER dengan `FINANCE_TRUSTED_VALIDATOR_REQUIRED` / `finance_validator_unavailable`. Guard tidak dicabut oleh Finance.
- Penyebab Finance: app-manifest belum mempunyai `packaging`, adapter V1 belum tersedia, installer hanya mengenal sidecar v2. Control schema 1 memakai `filename` dan hash app-manifest, bukan `artifact` dan hash inner RELEASE-MANIFEST.
- Perubahan utama: source alpha.12, profil default **Customer bersih** (CUSTOMER_CLEAN / CUSTOMER / NONE), immutable rules v1/hash tetap `77d6352569e2ed3ee22f22ed357e68280423b5ab9a413d58256fc16aa34ed570`. Parser aktual Product_manifest_service Control menerima manifest baru tanpa constructor/DB.
- Adapter: validasi canonical request, commit, hash app/rules, source bersih/tidak writable dan output privat. PHP 8.4 dari worker didelegasikan ke PHP 8.1. Mandatory preflight/OSV/PHPStan, TAR allowlist, install/restore disposable, delapan gate/report terikat request dan source recheck; tidak menandatangani paket atau menerima key Control.
- Drill database: instance MariaDB baru dengan datadir/socket privat, tanpa default server config atau jaringan; proses berhenti bersama adapter. Baseline + managed migrations, seluruh tabel non-reference wajib kosong, owner sintetis, health, dump/restore dan perbandingan seluruh count/checksum. Database/dump/owner uji tidak dibundel, hanya dibersihkan di direktori uji milik sendiri.
- Validator pre-signing `verify_control_build.php`: read-only terhadap source dan TAR, memeriksa byte source sebenarnya dan policy tepercaya. Control tetap harus memverifikasi result/report/eight gates dan memasang validator ini sebelum membuka guard. Tidak menjalankan PHP yang diambil dari TAR.
- Signed delivery: verifier/installer menerima Control schema 1 sesudah verifikasi Ed25519 asli; memisahkan hash app-manifest/inner manifest; signature/profile/hash/gate palsu ditolak. Legacy v2 tetap dapat diverifikasi dengan eligibility sebelumnya.
- Runtime host: adapter/rules root-owned 0644; ACL namua-build hanya read/execute pada dependency umum `/var/lib/finance-a4-static/vendor` dan `/var/lib/finance-a4-security`. Cache Composer/PHPStan dibuat privat per build. Snapshot OSV diperbarui melalui bootstrap resmi; ACL baca diulang setelah refresh. Tidak membuka permission credential atau memberi akses DB aplikasi.
- Temuan gate baru: cache OSV lama tidak lagi memenuhi freshness. Snapshot baru menemukan sharp 0.35.3, advisory GHSA-rgj7-g3m4-5g8c. Patch override/lock 0.35.4 dan libvips transitif; tidak menurunkan gate, menambah exception advisory, atau mengubah timestamp cache secara palsu. [Rujukan pengembang](https://github.com/lovell/sharp/security/advisories/GHSA-rgj7-g3m4-5g8c).
- WA: hanya package.json/package-lock untuk paket baru; npm `--package-lock-only --ignore-scripts`. Uji `npm ci --ignore-scripts` dilakukan di direktori disposable, transform PNG sintetis PASS sharp 0.35.4/libheif 1.23.2. Folder dependency uji dihapus setelah tes; node_modules, sesi WA dan service staging tidak disentuh/restart. Aktivasi patch pada service yang sudah berjalan tetap deployment tersendiri.
- File kode/tool: app-manifest.json; tools/build/{CustomerBuild,DisposableBuildDatabase,customer_package,verify_control_build}.php; tools/release/{ControlReleaseBridge,control_release,build_release_artifact}.php; tools/install/{FinanceInstance,clean_install_database}.php; wa-engine/package.json dan package-lock.json.
- File test: c3_control_build_adapter_smoke.php dan c3_control_build_runtime_smoke.php baru; c3_customer_clean_release_smoke.php, c2_c4_commercial_foundation_smoke.php, a4_release_artifact_contract_smoke.php, finance_quality_gate.php dan finance_quality_gate_contract_smoke.php diperbarui. Builder memeriksa vulnerability sebelum static agar gagal lebih awal; assertion urutan diperbarui, semua tiga gate tetap wajib.
- Dokumen: kedua roadmap, customer_clean_release_contract.md dan log ini; status ringkas C3 membedakan histori trial alpha.10 dari integrasi alpha.12. Tidak menandai praktik UI/publish customer selesai dengan bukti fixture.
- Validasi terarah PASS: 81 customer-clean/modern-signed checks; 20 adapter contract; 29 historical bridge; 32 empty-database boundary; 15 deployment instance; 12 artifact; 28 gate contract; 14 C2/C4; 26 roadmap consistency; lint PHP berubah dan diff whitespace. Scan baru 145 paket, **0 advisory**. Composer validate lulus dengan deprecation Composer host lama.
- Validasi release menyeluruh **PASS 115 entry**: required 105, development 4, release 1, runtime 2, preflight/security/static masing-masing 1; staging probe sengaja tidak dipilih. Sesudah itu lint final 16 PHP dan tes terarah artifact/cancellation dijalankan untuk penyempurnaan delegasi/cleanup.
- Drill final **2026-09-12 14:19 WIB PASS**: `c3_control_build_runtime_smoke.php --isolated --control-root=/www/wwwroot/control`; adapter PHP 8.4→8.1 sebagai namua-build, 1.108 file paket, 296 tabel, 285 tabel non-reference kosong sebelum owner uji, 719 record reference sistem, 16 migration, 0 dummy/customer/secret finding. Restore seluruh 296 count/checksum dan health lulus. Verifier source/TAR Finance serta fungsi result/report Control aktual menerima hasil; Control dipanggil library-only, tanpa DB/key/worker main. Delapan gate PASS. Ini **fixture integrasi**, bukan publish atau uji UI Control aktual.
- Uji komponen DB terpisah `--database-only` juga PASS; ditandai eksplisit bukan build evidence. Uji `--cancel` PASS: signal hanya ke process-group fixture sendiri, result FAIL terikat request, cleanup selesai sebelum parent keluar. Seluruh proses namua-build milik tes berakhir; tidak ada layanan DB temporer tertinggal.
- Review hasil runtime: cache Composer awal tidak mempunyai home privat; username fixture literal terkena scanner (diganti username acak, tanpa exception); Git verifier membersihkan environment sehingga perlu `safe.directory` terbatas pada checkout canonical; opsi `connect-timeout` dipindahkan dari grup client bersama karena mariadb-dump tidak mendukungnya. Inventaris count/checksum dibatch kecil tanpa mengurangi cakupan. Percobaan lama yang diketahui belum memuat fix dihentikan khusus process-group tes; race cleanup yang ditemukan diperbaiki dengan parent menunggu child dan cleanup toleran folder yang sudah hilang. Sisa cache fixture dibersihkan, tidak ada data pengguna di target tersebut.
- SQL/data: tidak ada SQL baru, tidak ada query atau perubahan DB Finance/Control operasional, tidak menghapus backup/upload/log/credential/data master. Hanya schema/seed dalam database disposable yang dieksekusi oleh tes.
- Cutoff sebelum batch: `e6c464d63d451b15408d29321aa0b7b82a6a12a3`; kandidat alpha.12, profil v1 tidak berubah. Handoff source melalui commit lokal `feat(release): connect customer-clean builds to Control`, tag `finance-web-alpha.12-control-build-source-20260912`. Tidak push, publish, atau mengeksekusi workflow Control dalam batch ini.
- Risiko/sisa: Control wajib menuntaskan integrasi validator dan impor source baru; cache advisori harus direfresh berkala; install/upgrade/publish dan UAT melalui Control aktual masih terbuka. APK/printer fisik dan bug produksi yang ditunda tidak diklaim selesai.
- Berikutnya: handoff kontrak ke thread Control → pasang validator → scan/preview/impor Finance → draft CUSTOMER_CLEAN versi exact alpha.12 → build/sign/review/publish dan trial customer terisolasi oleh user.

## Batch 246 — 2026-09-13 06:55 WIB: SR Roastery dan mobile POS/PO

- Prioritas: permintaan user, perbaikan terarah A2/A3 pada tujuan Roastery yang kosong serta layout mobile detail penjualan dan daftar Purchase Order.
- Diskusi/review: fixer tunggal sesuai pola terbaru. Guard dan normalisasi model sudah menerima ROASTERY/ROASTERY_EVENT, tetapi `list_destination_options()` tidak menyertakannya sehingga frontend menghapus semua pilihan. Baseline schema yang tersedia sudah mengenal dua nilai tersebut; tidak memerlukan SQL baru untuk perbaikan opsi ini.
- Model: menambahkan dua opsi pada daftar bersama yang dipakai SR halaman/modal serta PO/SR Divisi. Guard antar-divisi, alur simpan, fulfillment dan transaksi tidak diubah.
- POS: masalah Invoice berada di view detail transaksi `/pos/reports/sales-detail/{id}`; grup empat tombol dahulu memakai flex tanpa wrap. Kini responsive wrap/grid dua kolom di ponsel, target sentuh 44px, identitas/catatan panjang dapat membungkus. Isi/perhitungan laporan dan endpoint cetak tidak diubah.
- PO: seluruh tab (Per Nota, Per Rincian, Per Tgl Paid) memakai baris yang sama sebagai kartu berlabel pada ponsel. Desktop tetap tabel; tablet scroll horizontal dalam tabel. Semua kolom tetap tersedia, angka mobile tidak ellipsis, status/aksi dapat disentuh. Tidak menggandakan form/handler, mengubah hak akses atau melepaskan CSRF.
- File aplikasi: `application/models/Procurement_model.php`, `application/views/pos/report_sales_transaction.php`, `application/views/purchase/index.php`.
- File tes: baru `tools/tests/sr_roastery_mobile_layout_smoke.php` dan `sr_roastery_mobile_layout_browser.cjs`; registrasi required di `finance_quality_gate.php` serta daftar ekspektasi `finance_quality_gate_contract_smoke.php`.
- Validasi PASS: lint enam PHP berubah/baru dan syntax Node; 48 tes model/view sintetis, 55 kontrak Purchase/SR, 28 kontrak quality gate; browser Chrome memakai CSS dan view aktual, 20 kombinasi halaman/lebar 360/390/600/768/1280px. Browser memblokir seluruh request dan merender fixture tanpa script mutasi, tidak login atau mengakses transaksi.
- Batas validasi dokumentasi: `roadmap_consistency_smoke.php` menjalankan 26 pemeriksaan, **dua gagal** pada jumlah/registry SQL top-level karena file baru milik pekerjaan Roast Connect `sql/2026-09-12a_roast_connect_catalog.sql` sudah ada sebelum batch. Tidak menghapus file, mengubah katalog, atau melonggarkan tes untuk menyamarkan kegagalan; sinkronisasi registry SQL perlu dilakukan pemilik pekerjaan Roast Connect. Checklist perbaikan tiga halaman tetap diperbarui.
- Perintah browser: `FINANCE_BROWSER_TEST_PUPPETEER=/tmp/finance2-studio-work/node_modules/puppeteer-core node tools/tests/sr_roastery_mobile_layout_browser.cjs --screenshots`. Dependency browser yang sudah tersedia hanya dibaca, tidak dipasang/diubah. Screenshot final di `/tmp/finance-sr-mobile-layout-0oE4iR`; font/icon eksternal tidak dimuat oleh fixture, bukan test printer fisik.
- Hasil review: tes menangkap override ukuran tombol global dan overflow catatan panjang desktop; keduanya diperbaiki, tes ulang PASS. Memperbaiki fixture CSS BOM/animasi agar pengukuran memakai gaya akhir. Screenshot invoice/PO ponsel diperiksa. Full release/build gate tidak dijalankan untuk batch UI terarah ini.
- Data/scope: tidak ada SQL, query DB operasional, perubahan credential/upload, commit/push, atau perubahan Control/APK. Worktree awal berisi perubahan Roast Connect pada routes/Settings/view/catalog dan file baru terkait; seluruhnya dipertahankan tanpa disentuh.
- Dokumen: checklist delta `_30` dan catatan silang `_28` diperbarui, tanpa menandai fase komersialisasi/publish selesai.
- Risiko sisa/batch berikutnya: pengguna memverifikasi pembuatan SR nyata ke Roastery dan layout pada perangkat sendiri; tes tidak membuat SR/PO, mengubah status, atau mencetak dokumen aktual. Paket release berikutnya perlu mengambil cutoff perubahan ini. Tidak melanjutkan modul lain otomatis di luar tiga permintaan user.

## Batch 247 — 2026-09-13 23:04 WIB: klasifikasi mutasi dan estimasi operasional

- Prioritas: menutup pemasukan manual/rekonsiliasi yang belum ikut estimasi, memisahkan modal/prive/koreksi saldo dari biaya/pendapatan, dan mencegah settlement diposting ulang.
- Pola review: fixer tunggal sesuai arahan terbaru. Analisis menemukan asimetri CASE laporan dan penyesuaian pendapatan yang sebelumnya selalu menghitung selisih penuh. Review implementasi juga menutup draf yang dapat menimpa POSTED, query NULL pada pengeluaran non-POS, CSRF rekonsiliasi, nominal non-finite, serta pembulatan sen pada formulir settlement.
- Perubahan inti: `Finance_mutation_policy.php` menjadi aturan kategori/agregasi bersama. Pendapatan lain/surplus kas menambah estimasi; biaya operasional/promo/platform/kekurangan kas mengurangi; modal/prive/koreksi saldo saja tidak menjadi hasil operasional. Transfer/hutang/piutang/payroll mengikuti pengecualian lama. Pembelian tetap berbasis kas, bukan HPP; basis gaji dan laporan margin per produk tidak diubah.
- Historis: tidak ada klasifikasi otomatis dari catatan bebas. IN yang belum diklasifikasikan belum menambah estimasi; OUT tetap dikurangkan sementara dan ditandai. Pasangan VOID tetap dikecualikan. Tidak mengubah snapshot CLOSED atau memposting perbaikan data aktual.
- Mutasi: pilihan kategori pada input IN/OUT, penjelasan dampak, konfirmasi biaya settlement dan referensi, retry key per form. Kategori mutasi historis dapat dikoreksi oleh pemegang izin edit dengan alasan, audit before/after, optimistic conflict check, dan transaksi atomik; nominal/tanggal/saldo immutable. Periode CLOSED serta baris pembalikan/VOID ditolak. Tab manual menyertakan rekonsiliasi pendapatan. Retry protection baru berlaku IN/OUT, bukan klaim idempotensi baru pada transfer.
- Rekonsiliasi: draft boleh belum berkategori; posting IN/OUT wajib kategori sesuai arah. Ronde pendapatan berikutnya memakai selisih tersisa setelah seluruh penyesuaian efektif pada tanggal pendapatan/metode tersebut. Expected POS atau adjustment berubah sejak draft → wajib reload/simpan ulang. Semua writer rekonsiliasi memakai CSRF scoped dan izin edit existing. Kategori saat posting dipertahankan sebagai riwayat; koreksi kategori laporan terkini dilakukan lewat Mutasi Rekening.
- UI: estimasi mempunyai enam kartu, komposisi pengeluaran/modal/prive, peringatan kategori tertunda, breakdown harian dan tautan penelusuran mutasi. Panduan contoh POS 100.000/settlement 80.000 dan larangan memotong biaya yang sudah masuk POS/mutasi lain ada pada halaman. Layout rekonsiliasi pendapatan dibenahi agar rekening/tindakan/catatan terbaca di desktop maupun mobile.
- File aplikasi: baru `application/libraries/Finance_mutation_policy.php`; berubah `Finance_report_model.php`, `Purchase_model.php`, `Finance_cash_reconciliation_model.php`, `Finance_revenue_reconciliation_model.php`, controller `Finance_reports.php`/`Purchase.php`, satu route klasifikasi pada `application/config/routes.php`, serta view `finance/financial_estimation.php`, `finance/cash_reconciliation.php`, `finance/revenue_reconciliation.php`, `purchase/finance_mutation_index.php`.
- SQL: baru `sql/2026-09-13a_finance_mutation_reporting_category.sql`, SHA-256 `42c2d3e122ff776254a8838d622fd99dacdced60d38fbc5336104772dcb975f9`. Tiga `report_category` nullable, `client_request_key` nullable dan unique `(account_id, client_request_key)`. Fixture menjalankan DDL dua kali tanpa perubahan nilai historis. Staging lokal `db_finance`: target tunggal diterapkan dengan migration lock, pemeriksaan foundation/checksum, batas tunggu DDL, dan pencatatan `sys_schema_migration`; pengecekan ulang mengembalikan `already_applied`. Tidak menjalankan migration lain, tidak membaca transaksi staging untuk diagnosis, dan tidak menyentuh server utama. Rollback aplikasi: pertahankan metadata nullable/ledger, jangan hapus kategori audit.
- Distribusi: `migration_catalog.json`, `customer_clean_profile.json`, binding rules SHA pada `app-manifest.json` diperbarui untuk kode/SQL ini. Kontrak profil tetap v1; SHA profil baru `4d92a4c7fafd9204cdeef93860f4b4945a763c476021171210da3e413b16dba4`. Tidak mengganti versi/tag/artifact alpha.12; commit/cutoff/version/build berikutnya merupakan langkah rilis terpisah. Tidak ada dependency Composer berubah.
- Tes baru: `tools/tests/finance_mutation_reporting_smoke.php` (required gate) dan `finance_mutation_reporting_browser.cjs`. Kontrak jumlah/urutan migration pada tes A5/GAP07/Telegram dan registry roadmap diselaraskan ke 18 managed, 17 upgrade, 7 legacy; entri Roast Connect yang sudah ada tetap diakui tanpa menyatakan modulnya selesai diuji.
- Validasi terarah PASS: **72** pemeriksaan SQLite (SQL CI nyata, kategori, VOID, breakdown, global target/period, CSRF negatif dan RBAC guard); **127** pemeriksaan MariaDB disposable (termasuk pemeriksaan tersebut, baseline DDL, migrasi repeat-safe, posting/retry, koreksi metadata, audit rollback, CLOSED, settlement ronde ulang/stale, dan kas); **84** pemeriksaan browser pada empat view × lebar 360/390/768/1280, semua request diblokir, form payload/CSRF/sen diperiksa. Tidak menganggap angka antar-suite sebagai tes unik. Lint 27 file PHP dalam diff saat pemeriksaan dan PHP baru lulus; syntax JS/diff whitespace lulus. Account mutation history 9, migration catalog 43, health contract 17, roadmap 26 juga lulus.
- Bukti runtime terakhir: `/tmp/finance-mutation-runtime-final2.log`, fixture terakhir `/tmp/finance-mutation-test-hAtb1z` sudah dihentikan; screenshot yang diperiksa di `/tmp/finance-mutation-ui-d7afWR`. Fixture memakai socket lokal privat dengan networking off, bukan DB aplikasi. Tidak ada backup/upload/credential/log/data runtime aplikasi yang dihapus.
- Review lintas suite: quality gate `parallel`: **106/107 required PASS, satu FAIL**, development 4/4, release 1/1 dan preflight 1/1 PASS; runtime/security/static/staging profile tidak dijalankan. Bukan klaim full release/staging gate. Masalah paket customer yang tersisa pada `c3-customer-clean-release`: katalog memuat SQL `2026-09-12a_roast_connect_catalog.sql` dari pekerjaan lain, tetapi profil customer belum mengizinkannya sehingga `MIGRATION_CHECKSUM` fail-closed. SQL/allowlist Roast Connect tidak diubah atau disisipkan diam-diam. Registry dokumentasi sekarang mencatatnya sebagai belum diverifikasi di batch ini. Log gate: `/tmp/finance-mutation-quality-gate-final2.log`.
- Risiko sisa: operator tetap harus memeriksa settlement lengkap; dana yang belum cair bukan otomatis biaya. Pencegahan duplikasi manual memakai rekening/kategori/referensi/tanggal; tidak menjamin pencatatan kejadian sama di modul berbeda terdeteksi otomatis. UAT multi-operator nyata, kategori historis, gaji aktual vs estimasi, dan laporan laba-rugi akrual/HPP tetap perlu tindak lanjut. Tidak menjanjikan bebas bug universal atau siap publish hanya dari fixture.
- Berikutnya: user mencoba klasifikasi contoh mutasi lama melalui `/finance/mutations`, mengecek `/finance-reports/financial-estimation` dan rekonsiliasi dengan bukti settlement. Pemilik Roast Connect menyelesaikan integrasi paket; sesudahnya ulang gate/build/clean-install/upgrade di cutoff release baru melalui Control. Tidak memperbaiki APK atau data bisnis secara otomatis.
- Notifikasi Telegram: dicoba melalui notifier resmi setelah validasi selesai; log `2026-09-13T23:06:47+07:00 notification failed`. Tidak mengklaim pesan diterima dan tidak mengubah credential/config bot dalam batch ini.

## Batch 248 — 2026-09-14 06:19 WIB: Kontrol Keuangan, permintaan pengembangan 1–5

- Prioritas/scope: settlement tertelusur, pencegahan biaya ganda lintas modul, kualitas laporan, proyeksi kas 7/30 hari, dan laba-rugi manajemen HPP. Fixer tunggal sesuai arahan terbaru; tidak menjalankan diskusi/subagent, membuka fase lain, mengubah APK/Control, atau memperbaiki transaksi lama otomatis.
- Pembahasan/review: pembayaran POS sudah menambah rekening sehingga konfirmasi pencairan harus metadata-only. Satu kontrol merepresentasikan tanggal pembayaran/refund dan metode, bukan cohort tanggal order atau setiap transfer provider. Refund/biaya ditautkan dan diterangkan; penerimaan harus kumulatif. Rujukan konsep IAS 2/IAS 7 dicatat di laporan modul, tanpa klaim IFRS-compliant/akrual lengkap.
- Hasil 1: tab Settlement pada `/finance-reports/control`, filter tanggal/metode, daftar sumber/pagination, refund, promo existing POS, penyesuaian, jumlah diterima, sisa/status. Konfirmasi memakai alasan, fingerprint sumber, revisi optimistis, transaction/audit; tidak memposting kas. Sumber berubah → tinjau ulang, tidak memakai angka lama diam-diam.
- Hasil 2: `settlement_control_id` pada ledger dan draft kas/pendapatan. Promo/fee baru wajib referensi terstruktur. Shared claim memeriksa rekening/tanggal, sumber terkini dengan locking read, status pencairan, kategori efektif dan pasangan VOID. Mutasi, Rekon Kas, Rekon Pendapatan serta koreksi kategori tertaut memakai guard sama. Rekon Pendapatan memperhitungkan mutasi tertaut dari modul lain dan memeriksa ulang adjustment setelah account lock. Retry fee tetap idempoten. Data lama tanpa tautan tidak diinferensikan otomatis.
- Perbaikan hasil review: tidak ada tombol VOID umum yang dapat diasumsikan tersedia pada Mutasi Rekening. Karena guard kategori membutuhkan jalan koreksi, ditambahkan **VOID khusus penyesuaian tertaut** melalui rincian Settlement. Membutuhkan edit Kontrol Keuangan dan edit modul asal, POST/CSRF, alasan dan konfirmasi, periode asli/hari ini terbuka, rekening aktif, saldo mencukupi. Membuat pembalikan + audit atomik, tidak menghapus riwayat/POS/transfer. Retry tidak membalik dua kali; failure audit mengembalikan saldo dan membatalkan insert reversal. Rekon yang telah POSTED tetap riwayat; koreksi dari sesi baru.
- Hasil 3: kualitas laporan menampilkan cakupan hari/metode non-tunai belum ditinjau, kategori tertunda, pending/selisih/stale settlement, selisih saldo awal+ledger vs rekening, dan audit HPP existing. Ada sumber/tindakan; tidak ada auto-repair atau klaim jumlah temuan adalah kerugian unik.
- Hasil 4: proyeksi book−pending terlacak, lalu pencairan ditambah sekali. Outstanding hutang/piutang, payroll final belum dibayar, dan rencana manual (OPEN/DONE/CANCELLED) ikut jadwal; komitmen/perkiraan terpisah. Payroll memakai asumsi H+1 akhir periode; non-IDR dikecualikan. Rencana hanya metadata/audit dengan request key dan revision; status selesai bukan posting pembayaran.
- Hasil 5: metode baru pada `Pos_report_model` membaca HPP transaksi, termasuk extra dan rekonstruksi HPP sebelum refund; refund mengikuti tanggal refund, koreksi/reversal HPP mengikuti recognition/reversal date. Pajak penjualan dipisahkan; service/pembulatan mengikuti POS. DP/PAID_PARTIAL tidak dihitung sebagai final sale. Laba-rugi menggabungkan biaya/pendapatan berkategori dan beban payroll final; pembelian stok/pencairan payroll tidak dikurangkan lagi, kasbon tidak mengurangi beban gaji. Estimasi kas lama tidak diganti.
- File aplikasi baru: `application/controllers/Finance_insights.php`, `application/models/Finance_insight_model.php`, `application/libraries/Finance_settlement_control.php`, `application/views/finance/control.php`, `application/views/finance/_settlement_select.php`. Berubah: controller `Purchase.php`/`Finance_reports.php`, model `Purchase_model.php`/`Finance_cash_reconciliation_model.php`/`Finance_revenue_reconciliation_model.php`/`Pos_report_model.php`, route baru, workspace tabs, dan tiga view mutasi/rekonsiliasi. Perubahan Batch 246/247 serta Roast Connect yang sudah ada tetap dipertahankan.
- SQL: `sql/2026-09-14a_finance_control_workspace.sql`, SHA `5ba57770cdd2b4b12d67cb06acb340423ea454e60bb19234f60546adaad4567e`. Dua tabel metadata kosong `fin_settlement_control`/`fin_cash_plan`, tiga kolom tautan nullable/index, page/menu Keuangan serta grant default SUPERADMIN. Katalog dependency `2026-09-13a`; 19 managed, 18 upgrade, 7 legacy. Staging `db_finance`: applied kemudian `already_applied`, lock migration + dependency/hash ledger + postcheck schema/sidebar. Hanya target ini dieksekusi; tidak menjalankan SQL Roast Connect/utama/customer. Helper privat `/tmp/finance-control-migration-XAjD8v/apply.php` membaca config tanpa mencetak/menulis credential. Tidak backfill atau mengubah nominal/saldo/transaksi aplikasi.
- Distribusi: runtime/SQL baru di `customer_clean_profile.json`, profil kini v2 (koreksi disiplin versioning setelah perubahan isi profil v1 Batch 247), digest `b9de222acc42e1a2ecffd70607e4d7560999900db4bfd874e5e6243f86669b9b`, binding app-manifest sesuai. Finance `CustomerReleaseProfile`, `ControlReleaseBridge`, `ControlDelivery`, `verify_control_build` membawa versi profil terverifikasi, tetap fail-closed untuk versi asing/hash atau plan yang berbeda. Tidak mengedit Control, mengganti version/tag alpha.12, membuat artifact publish, commit, push, atau mengosongkan data/files development.
- Dokumen: `_30` memperbarui delta 1–5 dan registry SQL, `_28` memuat handoff profil v2/risiko rilis, `customer_clean_release_contract.md` membedakan bukti cutoff lama vs working tree. Satu laporan/panduan modul baru `docs/2026-09-14_finance_control_workspace.md`; tidak menyebar checklist bisnis ke banyak roadmap.
- Validasi terarah PASS: **123** checks MariaDB disposable (baseline/DDL repeat, shared writer/cross-module, fingerprint/revisi/retry, VOID asli dan rollback audit, CLOSED, refund lintas bulan/extra/tax, koreksi HPP, komitmen/piutang/payroll, CSRF/GET/RBAC termasuk izin asal VOID). **27** kontrak tanpa DB (required gate) termasuk parser profil v2, penolakan v3, dan binding plan v1≠artifact v2. **188** browser checks: 4 tab × edit/read-only × 360/390/768/1280px, network diblokir; payload/sen/CSRF, pembatalan konfirmasi VOID dan edit rencana diuji. Hitungan antar-suite tidak dianggap tes unik gabungan.
- Regresi Batch 247: **72** SQLite, **127** MariaDB disposable dan **84** browser checks lulus. Assertion fee lama diperbarui: konfirmasi teks saja belum cukup tanpa structured reference; tes incremental legacy non-settlement tetap memakai kategori operasional. Test bootstrap include controller diperbaiki agar tidak membutuhkan konstanta APPPATH pada contract harness, tanpa mengurangi permission/CSRF. PHP lint semua 32 file PHP tracked dalam diff saat pemeriksaan, PHP baru dan syntax JS PASS; diff whitespace PASS. Composer/lock tidak berubah, composer validate tidak diperlukan untuk batch ini.
- Review lintas suite terakhir: gate `parallel` **107/108 required PASS, 1 FAIL**; development 4/4, release 1/1, preflight 1/1 PASS. Satu fail tetap `c3-customer-clean-release`/`MIGRATION_CHECKSUM`: katalog Roast Connect dari thread lain belum cocok allowlist customer; tidak dilonggarkan/dihapus diam-diam. Runtime/security/staging-probe profile tidak dijalankan. **Static analysis dijalankan terpisah: PASS scope=application, baseline_errors=0.** Release bridge 29 PASS, roadmap consistency 26 PASS (45 master rows, 26 SQL files). Bukan klaim full release siap publish.
- Bukti akhir: `/tmp/finance-control-test-final4.log` (123), `/tmp/finance-control-quality-gate-final2.log`, `/tmp/finance-mutation-control-regression.log`, UI/screenshot `/tmp/finance-control-ui-f344bcd3ee86` (sudah diperiksa desktop/mobile), fixture terakhir `/tmp/finance-mutation-test-AZ2ROA` dihentikan. Fixture tidak memakai config/DB transaksi aplikasi. Tidak ada backup/upload/credential/log/data runtime aplikasi yang dihapus.
- Risiko sisa/UAT: periksa bukti settlement nyata dan kategori historis; satu kontrol harian/metode, bukan integrasi impor API provider. Pilihan referensi saat ini 200 terbaru; rekonsiliasi historis lebih tua membutuhkan peninjauan, jangan membuat kontrol duplikat. Angka proyeksi bergantung cakupan kontrol dan asumsi jadwal, bukan saldo bank terverifikasi. HPP/laba-rugi ini manajemen, bukan akrual lengkap (depresiasi, pajak penghasilan, accrual di luar sumber belum masuk; payroll parsial tidak diprorata). Tes tidak membuktikan seluruh kasus konkurensi multi-operator/volume produksi atau bebas bug universal.
- Berikutnya: owner mencoba **Keuangan → Kontrol Keuangan** dengan satu contoh nyata, meninjau kategori/pencairan tanpa auto-repair. Pemilik Roast Connect/Control menyelesaikan handoff rilis v2, kemudian gate/build/clean-install/upgrade/UAT cutoff baru. Tidak melanjutkan fase atau perbaikan APK otomatis di luar lima pengembangan yang disetujui.
- Telegram: ringkasan Batch 248 dikirim melalui notifier resmi; log terverifikasi `2026-09-14T06:21:51+07:00 notification sent`. Tidak mengubah token/config bot atau memasukkan credential ke laporan.

## Batch 249 — 2026-09-14, penyempurnaan Kontrol Keuangan (selesai implementasi staging)

- Waktu pencatatan hasil akhir: **2026-09-14 07:33 WIB (+07:00)**.
- Prioritas/urutan sesuai persetujuan user: **1 pencairan per transfer → 2 identitas biaya → 4 pencarian settlement lama → 3 realisasi rencana kas → 5 bukti dan approval opsional**. Fixer tunggal sesuai pola terakhir; review dilakukan melalui diff, kontrak, fixture MariaDB dan HTTP/browser. Tidak memulai fase/modul lain atau memakai diskusi multi-agent.
- Pencairan: rekap baru mulai nol, rincian transfer dengan tanggal/referensi rekening/nominal/bukti, total otomatis, idempotent request key, revisi untuk tab usang, VOID metadata dan audit. Konfirmasi lama tidak dipalsukan menjadi transfer; dipertahankan sebagai `receipt_opening_amount` saat mulai memakai rincian. Tidak memposting penerimaan POS kedua kali.
- Biaya: dokumen + baris unik per rekening, bukan kategori sebagai identitas. Dua biaya sejenis dapat diposting; satu identitas efektif tidak dapat diklaim ulang di Mutasi, Rekon Kas, atau Rekon Pendapatan. Nominal/arah/kategori/tanggal/rekening harus sama. Biaya historis tertaut tanpa identitas harus diidentifikasi eksplisit memakai mutasi asal dan izin edit asal; tidak ditautkan berdasarkan dugaan catatan, tidak auto-repair data. Koreksi biaya setelah posting harus VOID dahulu.
- Pencarian: seluruh history dengan teks/tanggal dan 25 per halaman; kasus di luar 200 terbaru dapat dipilih. Selection lama dan identitas biaya POSTED dipertahankan di form baca/rekonsiliasi. Method historis nonaktif dapat dibaca dari rekap yang ada; posting tetap membutuhkan rekening IDR aktif.
- Rencana/realisasi: satu mutasi manual utuh untuk satu rencana, arah sama, taut/lepas dengan audit dan revisi tanpa cash write. Proyeksi hanya sisa, menampilkan kelebihan; VOID membuat realisasi tidak efektif dan sisa kembali. POS/payable/receivable/payroll otomatis tidak boleh dialokasikan ulang. Rencana tertaut selesai berdasarkan realisasi; tidak bisa menyembunyikan sisa melalui status DONE manual. Jadwal perkiraan payroll mengikuti tanggal 1–31 di Pengaturan, setelah akhir periode dan clamp akhir bulan; tidak mengubah gaji/uang makan/pembayaran payroll.
- Bukti/approval: file PDF/JPG/PNG maksimum 5 MB, gambar maksimum 24 MP, nama/ekstensi/isi diperiksa; kompatibel dengan PHP staging tanpa Fileinfo. File privat acak, chmod 0600, hash diverifikasi saat akses/penggunaan, hanya download attachment berizin dengan nosniff/no-store. Upload memakai POST+CSRF. Persetujuan/bukti wajib default OFF; hak `finance.control.settings` dan `finance.control.approve` edit terpisah, grant awal SUPERADMIN saja. Maker/requester tidak boleh self-approve. Approval mengikat exact target/rekening/nominal/metadata/bukti/revisi settlement/kebijakan; stale ditandai dan perlu diajukan ulang. Konsumsi approval satu kali dalam transaksi posting/VOID, rollback juga mengembalikan approval bila posting gagal. Scope hanya penyesuaian manual tertaut settlement, bukan seluruh aplikasi.
- File runtime yang dibuat: `application/models/Finance_control_operation_model.php`, `application/libraries/Finance_control_evidence.php`, `application/views/finance/control_operations.php`, `assets/js/finance-control-operations.js`, `assets/js/finance-settlement-picker.js`, SQL `sql/2026-09-14b_finance_control_operations.sql`.
- File runtime yang diperbarui: `application/controllers/Finance_insights.php`, `application/models/Finance_insight_model.php`, `application/libraries/Finance_settlement_control.php`, `Purchase_model.php`, kedua `Finance_*_reconciliation_model.php`, `application/config/routes.php`, `application/views/finance/control.php`, `_settlement_select.php`, kedua view rekonsiliasi dan `application/views/purchase/finance_mutation_index.php`. Perubahan lain dari batch terdahulu/Roast Connect di worktree dipertahankan, bukan hasil Batch 249.
- SQL staging: `2026-09-14b-finance-control-operations`, hash `9034c6db9dbea17054ff78ade67ee281d5a07f885c56a77f21a152d2e7b0f2af`, dependency `2026-09-14a` dicek terhadap ledger. Applied satu kali, eksekusi helper berikutnya mengembalikan `already_applied` dengan hash/kolom/tabel/sidebar/policy terverifikasi. Enam tabel metadata, tiga kolom charge nullable + dua kolom konfirmasi, dua page/grant dan singleton kebijakan; approval=0, bukti wajib=0, payroll_day=1. Tidak menjalankan migration lain atau backfill transaksi. DDL repeat-safe diuji dua kali pada setiap disposable fixture; DDL tidak dijanjikan atomic penuh.
- Runtime staging: folder `/var/lib/finance-control/f65b6a92a4ff9ea0/evidence` dibuat owner `www:www`, mode 0750; `.user.ini` hanya ditambah direktori privat itu pada `open_basedir`. Diuji dengan `runuser -u www` memakai konfigurasi PHP server dan open_basedir yang sama: READY. Tidak chmod 777, tidak menghapus backup/upload/credential/log/runtime. `.user.ini` bukan allowlist distribusi; panduan customer memakai root/hash/path instance sendiri. File bukti yang terlanjur diterima ketika DB gagal dipertahankan privat untuk peninjauan, bukan dihapus otomatis.
- Distribusi/dokumen: `tools/db/migration_catalog.json` kini 20 managed / 19 upgrade / 7 legacy, 27 SQL top-level. Profil CUSTOMER_CLEAN v3 hash `e167cc4f826f90976a59b023606d8af5aa0a43304e73c85ac346226238c48781` beserta manifest binding; runtime+SQL baru di-allowlist, data/bukti tidak dibundel. Parser/installer Finance menerima known v1/v2/v3 dan tetap exact hash/signature/plan. Fixture katalog/count/health-check ikut diperbarui, bukan mengurangi guard. `_30`, `_28`, `docs/customer_clean_release_contract.md`, panduan modul `docs/2026-09-14_finance_control_workspace.md` diselaraskan; cutoff historis tidak diubah menjadi klaim rilis baru.
- Uji baru: `finance_control_operations_smoke.php` + cases **192 PASS** di MariaDB socket-only disposable. Termasuk ulang migration, legacy total, sen, retry/stale/VOID, kategori ganda sah dan duplikasi ditolak, tiga writer nyata, identifikasi legacy, link/unlink/VOID plan, jadwal payroll, self-approval/stale amount/policy, failed-post rollback approval, required evidence, pagination >200/literal wildcard/bound SQL. HTTP nyata memakai controller dan DB fixture saja: izin kebijakan/reviewer/control, input nested, multipart upload, forged MIME/extension, case tidak ada, CSRF hilang, download berizin, file 0600 dan tampered hash. Tidak membaca config/DB transaksi aplikasi.
- Uji kontrak baru **35 PASS**, terdaftar required gate `finance-control-operations`; kontrak workspace **27 PASS** termasuk profil v3/reject v4 dan plan v1≠artifact v3. Browser operasi **170 PASS**, offline 4 tab baru/terpengaruh × edit/read-only × 360/390/768/1280, plus shared picker; payload transfer sen/CSRF/key, charge edit/reset ID, search case lama, link mutation/revisi, no viewport overflow. Screenshot mobile diperiksa.
- Regresi: workspace MariaDB **123 PASS**; mutation reporting SQLite **72 PASS** dan MariaDB **127 PASS**. Fixture browser workspace dasar **188 PASS** juga dijalankan, terpisah dari suite operasi terbaru. PHP lint tracked diff + semua file baru relevan, syntax kedua JS, `git diff --check`, roadmap consistency **26 PASS / 45 master rows / 27 SQL**, static analysis **PASS application baseline_errors=0**. Composer/dependency lock tidak berubah; composer validate tidak relevan.
- Gate terakhir `parallel`: **108/109 required PASS**, satu FAIL lama `c3-customer-clean-release` / `MIGRATION_CHECKSUM` (SQL Roast Connect `2026-09-12a` belum dalam customer allowlist). Development 4/4, release 1/1, preflight 1/1 PASS. Runtime/security/staging-probe release tidak dijalankan; static dijalankan terpisah. Tidak melonggarkan gate, menghapus/mengikutkan Roast Connect diam-diam, mengedit Control/APK, commit/push, publish, atau menjalankan SQL di server utama.
- Bukti final: `/tmp/finance-control-operations-test-final2.log`, UI `/tmp/finance-control-ops-ui-ee79a4c891e6`, `/tmp/finance-control-operations-browser-final2.log`, `/tmp/finance-control-operations-gate-final2.log`, `/tmp/finance-control-operations-static-last.log`, `/tmp/finance-control-legacy-regression.log`, `/tmp/finance-control-mutation-sqlite.log`, `/tmp/finance-control-mutation-mysql.log`. Semua server fixture yang selesai dihentikan; diagnostik privat tetap disimpan.
- Risiko sisa: UAT owner dengan bukti/role nyata; satu transfer mencakup beberapa rekap belum mendukung pemecahan alokasi (jangan digandakan), satu mutasi realisasi tidak dipisah ke banyak rencana. Konfirmasi awal lama perlu ditinjau sebelum transfer pertama; setelah mode rinci tidak ada penghapusan/penimpaan saldo awal secara otomatis. Identitas dokumen bukan deduplikasi semantik; referensi berbeda bisa mewakili kejadian sama jika operator salah. Proyeksi/manajemen bukan saldo bank terverifikasi atau laporan akrual penuh. Volume/konkurensi produksi dan malware PDF tidak dijanjikan tercakup semua; attachment privat bukan antivirus.
- Berikutnya: owner UAT **Keuangan → Kontrol Keuangan**, mulai satu rekap tanpa auto-repair, mencoba dua transfer/biaya terpisah dan tautan rencana. Approval dinyalakan hanya bila owner memilih serta pemeriksa berbeda tersedia. Pemilik Roast Connect/Control menyelesaikan blocker rilis dan scan ulang profil v3 sebelum cutoff/build/clean-install/upgrade berikutnya. Tidak membuka batch tambahan otomatis di luar daftar yang disetujui.
- Telegram: ringkasan perbaikan Batch 249 terkirim melalui notifier resmi; log terverifikasi **2026-09-14T07:33:46+07:00 notification sent**. Token/config bot tidak diubah atau dimasukkan dalam laporan.

## Batch 250 — 2026-09-14 15:29 WIB — Telegram sebagai transport coding internal

- Permintaan: pemilik menggunakan bot existing `@cacacia_bot` untuk `/codex resume`, memilih thread Finance, lalu memberi instruksi dan menerima ringkasan melalui DM. **Bukan pekerjaan customer/komersialisasi**; tidak menambah fase/checklist bisnis di dokumen `_30`/`_28`.
- Pola: fixer tunggal, tanpa subagent; OpenAI Docs digunakan untuk kontrak daftar/baca thread dan resume ID eksplisit. CLI server 0.154.0 sudah login menggunakan ChatGPT. Tes nyata membuktikan dua giliran pada thread diagnostik baru memiliki ID sama dan konteks giliran pertama tetap tersedia; thread tes diarsipkan, bukan mengubah thread pengguna untuk percobaan.
- File berubah: `application/controllers/Telegram_webhook.php` (relay opsional private-only setelah validasi secret existing), `tools/telegram/codex_notify.php` dan tes notifier (samarkan kode pairing pada ringkasan grup), `tools/release/package_policy.json` (exclude sumber bridge internal). Tidak mengubah versi/validator kontrak profil rilis.
- File baru: `tools/telegram/codex_bridge/{bridge.py,relay.php,finance-codex-bridge.service,test_bridge.py,probe_resume.py,.gitignore}`; panduan `docs/2026-09-14_codex_telegram_internal.md`. Worker Python stdlib, SQLite privat di luar Finance, socket lokal root:www, pairing sekali pakai dengan hash/expiry dan allowlist satu numeric Telegram user ID. Tidak ada endpoint shell/port publik atau polling Telegram.
- Fitur: daftar thread workspace Finance dengan pagination, pemilihan ID, instruksi teks, status/hasil, stop, deduplikasi update, satu tugas, antrean inbox/outbox persisten. Tugas terputus/restart tidak auto-replay; send timeout dicatat unknown. Menolak grup/forward/bot/anonymous/pemilik lain. Model berjalan workspace-write, approval never, network sandbox off; tidak menjalankan Codex danger-full-access. Tidak ada push/deploy atau perubahan server/DB otomatis melalui instruksi transport.
- Instalasi staging: worker root-only `/opt/finance-codex-bridge/bridge.py`; service `/etc/systemd/system/finance-codex-bridge.service` enabled/active; state 0700/DB0600 di `/var/lib/finance-codex-bridge`; socket0660 `/var/lib/finance-config/codex-bridge.sock`; relay Git-ignored `.codex/telegram_webhook_bridge.php`. Nginx vhost `pos.namuacoffee.com.conf` menambah blok 404 sumber internal, config-test lulus sebelum reload. Tidak mengubah credential, webhook URL/allowed_updates, konfigurasi PHP-FPM, SQL/schema/data transaksi, sidebar, Control, APK, atau file backup/upload/log.
- Validasi PASS: **19 tes Python** (termasuk success/failure job, exact thread, cancel dan conflict), PHP lint, Telegram smoke **102**, notifier **10** (pairing tidak bocor ke grup), release artifact contract **12**, release preflight contract **18**, pengecualian paket internal. Runtime `www` dapat mengakses socket; HTTPS webhook secret salah403, secret benar + dummy non-owner200 tanpa task. HTTP source internal404. Bot API getMe mengonfirmasi username cacacia_bot; webhook unchanged, pending_updates0. Probe CLI teks-only pertama ephemeral dan probe resume dua giliran lulus; tidak memanggil tool/file/DB aplikasi dalam probe model.
- Review: jalur perintah laporan grup lama tidak berubah. Pemisahan kode/state internal dan redaksi pairing dicek; template/source internal tidak masuk paket. Concurrency guard bersifat konservatif dan **bukan lock atomik semua IDE**: menolak client Codex Finance terbuka/rollout thread terbuka, memonitor client baru selama kerja; user harus menutup sesi Codex lain sebelum handoff. Stop tidak undo perubahan sebelumnya. Balasan hanya DM pemilik; notify grup dinonaktifkan per proses bot, konfigurasi global notifier tetap.
- Risiko sisa/UAT: owner belum pairing saat validasi; uji end-to-end dari HP perlu pairing pribadi → list/pilih thread → tutup sesi Codex Finance lain → satu prompt read-only → cek balasan dan riwayat. File/foto/voice belum didukung. Memakai login/kuota Codex server existing; sandbox/kuota/provider dapat membatasi tugas. State instruksi/hasil privat tetapi tidak terenkripsi; redaksi pola secret bukan detektor universal. Tidak menjalankan ulang full quality gate seluruh aplikasi pada batch internal ini.
- Berikutnya: pandu pairing dan UAT owner; tidak lanjut fase bisnis atau mengaktifkan perintah coding untuk anggota grup. Kode pairing tidak dicantumkan di log/dokumen/repo/grup. Tidak commit, push, publish, menghapus backup, atau merombak data runtime.

## Batch 251 — 2026-09-14 15:51 WIB — Dua akun Telegram untuk coding internal

- Persetujuan: pemilik meminta akun Telegram kedua memperoleh hak coding yang sama tanpa melepas akun pertama. Scope hanya bridge internal, fixer tunggal; protokol/model Codex dan modul bisnis Finance tidak diubah.
- File berubah: `tools/telegram/codex_bridge/bridge.py`, `test_bridge.py`, panduan `docs/2026-09-14_codex_telegram_internal.md`, dan log ini. Ditambahkan undangan lokal `pair-add`, maksimal **dua** numeric user ID, hash/expiry 24 jam, sekali pakai; akun pertama tidak dapat tanpa sengaja menghabiskan undangan akun kedua. Kode baru menggantikan undangan pending sebelumnya, bukan mencabut akses akun pertama.
- SQLite privat bridge naik ke skema v2 secara transaksional/idempotent: tabel `accounts`/`account_settings`, kolom `user_id` pada inbox/jobs/outbox. Primary owner tidak berubah; pilihan thread/cursor serta seluruh riwayat lama dipetakan satu kali ke akun pertama. Penerima balasan ditetapkan saat enqueue; list/pagination/pilihan/status/hasil terpisah per akun. Akses kumpulan thread Finance tetap sama, bukan isolasi tenant.
- Eksekusi tetap satu tugas global. Pesan kedua saat sibuk tidak dijalankan; stop hanya dari akun pengirim tugas. Restart memberi pemberitahuan kepada pengirim job yang terputus, tidak menyiarkan hasil ke akun lain. Group/forward/bot/anonymous/user asing tetap ditolak; tidak ada pairing bebas atau undangan akun ketiga.
- Deployment: idle diverifikasi (0 job aktif, 0 pending command), worker dihentikan singkat. Snapshot privat worker v1 dan SQLite konsisten dibuat di `/var/lib/finance-codex-bridge/pre-multiaccount-T6aMYJ/` sebelum upgrade. Worker root-only `/opt/finance-codex-bridge/bridge.py` diperbarui, service kembali active. Tidak menimpa/menghapus backup. Worker v1 tidak boleh dijalankan terhadap DB v2; rollback harus memakai worker+state yang cocok dan menyimpan state baru terlebih dahulu.
- Validasi PASS: **29 tes Python**, termasuk 10 tambahan untuk pairing kedua, cap dua akun, persaingan undangan atomik, salah akun/expiry/rotasi, migrasi idempotent, pemisahan context/hasil, one-job global/stop per pengirim, recipient pengiriman dan error inbox. Python compile PASS; Telegram existing **102** dan notifier **10** PASS. Tidak menjalankan model berbayar/meresume thread user atau mengirim pesan ke identitas kedua fiktif selama pengujian.
- Pemeriksaan staging: primary owner, semua field riwayat lama dan pilihan thread dibandingkan snapshot **PASS**; SQLite integrity `ok`, schema2, satu akun masih terpasang menunggu pairing kedua. Source/deployed worker identik; DB0600/socket0660 tetap. HTTPS webhook valid secret + dummy non-owner200/ack tanpa job; service sehat setelah restart.
- Risiko sisa/UAT: user masih harus mengirim kode tambahan dari DM akun kedua, kemudian `/codex resume` dan memilih thread. Belum mengklaim akun kedua sudah terhubung. Dua akun berbagi workspace/history Codex, hanya pilihan/antrean/balasan yang dipisahkan. Lock eksternal IDE tetap best-effort seperti batch250. Tidak mengubah token/webhook/SQL atau DB Finance, sidebar, runtime customer, Control/APK, maupun fase `_30`/`_28`; tidak commit/push/publish. Kode pairing tidak dimasukkan log/repo/grup.
- Berikutnya: pairing akun kedua dan satu prompt read-only untuk memastikan balasan diterima akun pengirim. Akun pertama tidak perlu pairing ulang.

## Batch 252 — 2026-09-14 16:03 WIB — Void/refund POS untuk produk tanpa resep

- Permintaan melalui transport Telegram: periksa dan perbaiki jalur void/refund order event tanpa resep/stock commit. Batas transport dipatuhi: tidak mengubah bridge, credential/config server/DB, tidak mengirim Telegram sendiri, push/deploy, atau tindakan destruktif.
- Penyebab terkonfirmasi: resolver/konfirmasi web dan mobile mengizinkan produk tanpa resep, memfinalkan `NOT_REQUIRED` tanpa snapshot. Model reversal tetap mengembalikan error `Snapshot stock commit belum tersedia.`. Tes sintetis baru gagal pada kode sebelum patch dan lulus setelah patch.
- Perubahan runtime terarah: **9 baris tambahan** pada `Pos_model::build_order_reversal_plan()`. Snapshot kosong + status tersimpan `NOT_REQUIRED` menghasilkan plan kosong yang valid (`stock_reversal_required=false`). Jalur selection/void/refund tetap utuh; helper stock reversal kosong menjadi no-op. Snapshot aktual selalu dibaca dahulu, termasuk header NOT_REQUIRED pada order campuran/append. Tidak memakai resep master saat ini untuk membenarkan bypass.
- File: `application/models/Pos_model.php`; baru `tools/tests/pos_reversal_no_stock_smoke.php`; pendaftaran required `a1-pos-reversal-no-stock` pada `finance_quality_gate.php` dan kontrak daftar gate pada `finance_quality_gate_contract_smoke.php`. Docs `_30`, `_28`, laporan modul `docs/2026-09-14_pos_void_refund_tanpa_resep.md`, log ini diperbarui. Tidak menyentuh controller mobile/fitur APK.
- Validasi: **45 pemeriksaan baru DB-free PASS** (preview valid, stok no-op, process state, full/partial, cap qty, pembayaran diskon, pembayaran habis/tidak ada, status snapshot hilang ditolak, order campuran, stock error tetap bubble, lock/writer rollback dan outgoing mutation tetap ada). Reversal invariant **8**, availability **20**, step-up web **54**, mobile **29**, CSRF transaksi **1770**, binding mobile financial writer **26** PASS. PHP lint file runtime/test/gate dan diff whitespace diperiksa; dependency Composer tidak berubah.
- Review: transaksi yang tidak memerlukan stok tidak lagi diblokir oleh snapshot kosong; transaksi yang mestinya mempunyai snapshot tidak diloloskan hanya karena snapshot hilang. Otorisasi, pilihan metode rekening, mutasi uang refund, batas refund, lock, dan rollback tidak dilonggarkan. Tidak ada SQL/migrasi atau data transaksi yang dibaca/diubah. Snapshot/modifikasi transaksi nyata harus diuji pengguna, bukan diposting otomatis dari transport ini.
- Risiko sisa/UAT: belum menjalankan browser + submit end-to-end terhadap order asli. Uji lewat POS Kasir: event belum dibayar → VOID; event dibayar → REFUND penuh/sebagian; campuran event + produk stok → reversal hanya untuk item yang dipilih; repeat setelah habis harus ditolak. Full quality gate seluruh aplikasi tidak dijalankan pada batch terarah ini; status rilis global tidak dinyatakan lulus.
- Berikutnya: pengguna mencoba transaksi uji di web/mobile. Tidak perlu menjalankan SQL. Paket berikutnya mengambil source cutoff yang memuat patch dan gate baru; tidak membuka fase komersialisasi atau memperbaiki data mismatch.

## Batch 253 — 2026-09-14 16:59 WIB — Alokasi, CSV bank, dan regresi rekonsiliasi

- Prioritas: P1 selisih rekon yang menyesatkan/stale posting; pengembangan terarah urutan 1 alokasi transfer → 2 realisasi parsial → 3 pembanding rekening koran, plus transfer koreksi metode. Fixer tunggal sesuai pola terbaru; tidak memakai subagent/bridge/Telegram notifier. Review berdasarkan kode dan fixture sintetis, bukan data transaksi asli.
- Temuan terkonfirmasi: Rekon Pendapatan hanya menerima NONE/IN/OUT dan menampilkan selisih historis posting sebagai sisa; Rekon Kas sudah mempunyai transfer tetapi dapat memposting nominal baru setelah saldo berubah. Patch memisahkan sisa/riwayat, memeriksa stale snapshot kas dan mata uang transfer, serta kategori IN/OUT/BOTH di UI.
- Alokasi: receipt distribution + riwayat per revisi, total tidak melebihi transfer, rekap satu rekening dan tanggal valid, sisa belum dialokasikan, saldo konfirmasi awal tetap, VOID menghitung ulang seluruh rekap. Kelengkapan rekap dibuka kembali saat alokasi berubah. Parsial rencana dibatasi total mutasi efektif, kompatibel tautan lama, tidak membukukan kas kedua kali; VOID mengembalikan sisa.
- Bank: CSV UTF-8 bounded, pemetaan eksplisit, preview terikat file/rekening/hasil pemetaan, deduplikasi identik, cocok/lepas berizin+CSRF dengan audit, mutasi berubah/VOID menjadi stale. Tidak ada import PDF/bank API/automatic posting; pencocokan awal satu baris ke satu mutasi bertanggal sama, maksimal enam saran.
- Rekon pendapatan: transfer hanya koreksi dua metode pada satu sesi, selisih sama/berlawanan, dua rekening IDR berbeda sesuai mapping; konfirmasi settlement lengkap diperiksa bila ada. Dua mutasi FINANCE_TRANSFER dan dua baris terhubung disimpan atomik dengan audit; bukan biaya/pendapatan. Perpindahan saldo murni tetap melalui Rekon Kas. Tidak menambah VOID transfer satu sisi.
- File runtime: controller Finance_insights; model Finance_control_operation/Finance_insight/Finance_revenue_reconciliation/Finance_cash_reconciliation; library baru Finance_allocation_policy, Finance_allocation_operations, Finance_bank_csv, Finance_bank_operations, Finance_revenue_transfer; view control/control_operations/revenue_reconciliation/cash_reconciliation dan control_allocations baru; JS finance-control-operations + finance-control-allocations baru. SQL baru 2026-09-14c; profil kode customer/manifest; gate dan dua test baru; docs _30/_28/workspace serta laporan modul diperbarui. Perubahan lama/user pada worktree tetap dipertahankan.
- Validasi: **108 PASS** pada writer/query builder CI + SQLite :memory: (DDL diadaptasi khusus fixture, bukan bukti SQL MariaDB). Cakupan negatif mencakup saldo kurang, pasangan tidak seimbang, beda rekening/mata uang, batas alokasi, duplicate/replay/stale, periode closed, rollback audit, missing schema, VOID, kategori, render edit/read-only, dan forecast nyata. Regresi 72 mutation reporting + 27 workspace + 35 operations + 28 quality-gate contract PASS. PHP lint 20 file; JS eksternal dan 37 script inline hasil render lolos sintaks. Required gate finance-allocation-bank ditambahkan. Composer tidak berubah.
- Browser: runner offline dependency-free CDP disiapkan. Chromium tidak dapat mulai dalam sandbox: setsockopt Operation not permitted. **Tidak mengklaim browser/responsif/UAT PASS.** HTML sintetis dan profil Chrome percobaan hanya artefak temporer di /tmp; tidak menghapus data runtime/backups.
- SQL **PREPARED, NOT APPLIED**: empat tabel metadata, tiga kolom pasangan dan perluasan enum rekon pendapatan. Tidak mengakses config/DB Finance, tidak mengubah server/credential/database, tidak push/build/deploy atau mengubah Control/APK/bridge. SQL sengaja belum didaftarkan pada katalog auto-apply/allowlist SQL sebelum validasi IDE. Library wajib sudah masuk allowlist kode, profil v3 hash 02940e1fa103fc99211e531d02f954ab131e5c35282918b5c276cc8627750466 dan manifest sesuai; tidak mengeluarkan cutoff/artifact baru.
- Review akhir: saldo baru tidak dibuat oleh alokasi/impor/link; mutasi transfer bernilai sama dan berlawanan serta dihitung sekali pada kedua metode; alur lama tetap berjalan sebelum schema baru tersedia. Tidak menyatakan bebas bug universal atau siap jual global.
- Risiko sisa/batch berikut: IDE perlu uji DDL MariaDB disposable dua kali + locking/concurrency, registrasi hash/dependency SQL/clean profile dan validasi customer seed, lalu staging apply melalui runner setelah persetujuan; jalankan browser offline dan UAT owner. Checklist belum diaktifkan/telah diuji operasional tetap terbuka. Panduan rinci: docs/2026-09-14_finance_alokasi_bank_rekonsiliasi.md.

## Batch 254 — 2026-09-14 17:21 WIB — Rekonsiliasi Pendapatan sebagai cek kas harian

- Prioritas/arah pemilik: pendapatan harian adalah lingkup kecil rekonsiliasi kas, dengan pilihan biarkan selisih, IN/OUT, atau transfer rekening. Aturan awal Batch 253 terlalu sempit: mewajibkan dua metode, dua baris tersimpan, selisih simetris dan settlement lengkap walaupun bukan biaya platform. Fixer tunggal menyesuaikan kode sesuai arahan ini; tidak ada koordinasi subagent atau notifikasi terpisah.
- Runtime berubah: `application/libraries/Finance_revenue_transfer.php`, `application/models/Finance_revenue_reconciliation_model.php`, `application/views/finance/revenue_reconciliation.php`. Tes diperbarui: `tools/tests/finance_allocation_bank_smoke.php`, `tools/tests/finance_allocation_browser.cjs`. Dokumen: `_30`, `_28`, laporan `2026-09-14_finance_alokasi_bank_rekonsiliasi.md`, log ini. Tidak menyentuh file pengguna lain di worktree.
- Transfer: pilih rekening utama/lawan aktif berbeda dan mata uang sama, tanpa membutuhkan metode/baris lawan. Posting membuat dua mutasi IN/OUT dalam transaksi yang sama, menyimpan kedua ID pada satu baris, audit wajib; hanya baris utama selesai. Sumber cukup, tanggal/periode, snapshot, active account/method, mapping opsional, dan ulang posting tetap divalidasi. Tidak menormalkan saldo tujuan negatif menjadi nol karena akan menciptakan saldo tanpa mutasi.
- Atribusi metode lawan tetap pilihan opsional untuk salah-metode, bukan prasyarat transfer. Mutasi sisi lawan ikut perhitungan metode terkait pada hari itu, tanpa menutup baris tersebut. Pembacaan transfer dua-baris lama tidak dihitung ganda; tidak memigrasikan/backfill data historis. Tidak menambah VOID transfer satu sisi.
- NONE hanya menyimpan hasil cek, boleh tanpa rekening/kategori. IN/OUT memakai kategori bersama dengan Mutasi Kas/Rekon Kas, termasuk koreksi saldo saja dua arah. Dihapus kewajiban referensi settlement untuk selisih biasa hanya karena rekap settlement tersedia. Jika sengaja dipilih, rekening/tanggal/metode/dokumen tetap diperiksa; promo/platform tetap wajib rincian dan rekap lengkap seperti alur kas, termasuk kebijakan bukti/persetujuan bersama. Transfer/NONE membersihkan kategori dan referensi biaya yang tidak relevan.
- UI: istilah penerimaan harian, petunjuk tidak memasukkan saldo awal/seluruh rekening, beda Simpan vs Posting, dropdown rekening lawan, arah transfer dijelaskan, metode lawan dalam opsi tambahan. Filter kategori sesuai arah dan lawan berbeda; referensi biaya disembunyikan pada NONE/TRANSFER. Perubahan disimpan sebelum posting agar tidak memakai formulir lama.
- Validasi: **175 PASS** writer/query builder CI + render PHP pada SQLite `:memory:`; sembilan kategori (koreksi saldo dua arah), kasus rekap sebagian biasa, fee tanpa bukti ditolak/valid diposting, transfer tanpa metode kedua dua arah, mapping/akun sama/nonaktif/mata uang/stale/saldo/rollback/periode/retry, legacy attribution, schema-off, saldo tujuan negatif. Regresi mutation reporting **72**, workspace contract **27**, operations contract **35**, quality-gate contract **28**, roadmap consistency **26** PASS. PHP lint empat file, sintaks runner JS dan **59** script inline hasil render PASS; Composer tidak berubah. Artefak UI sintetis temporer di `/tmp/finance-allocation-ui-DyedZE`.
- Review: alur bebas pasangan sudah lolos tes writer nyata pada fixture; kas dan kategori tidak dibuat ulang oleh Simpan atau dihitung dua kali oleh atribusi transfer. Tes MariaDB locking/DDL dan browser aktual belum dijalankan; runner browser diperbarui tetapi keterbatasan Chromium sandbox Batch 253 masih berlaku. Tidak mengklaim bebas bug universal atau rilis global siap.
- SQL/transport: tidak ada SQL tambahan; memakai `2026-09-14c` yang **masih PREPARED, NOT APPLIED**. User menyebut izin eksekusi SQL, tetapi wrapper transport masih melarang perubahan DB; konflik disampaikan di awal dan pekerjaan dibatasi pada kode/fixture. Tidak membaca config atau data Finance, mengubah DB/server/credential/bridge, mengirim Telegram sendiri, commit/push/deploy/build atau menghapus runtime.
- Risiko sisa/batch berikut: lanjut di IDE untuk menuntaskan validasi/apply SQL dan registrasi release yang masih tertunda, browser serta UAT operator (biarkan/IN/OUT/transfer biasa/atribusi metode opsional). Tidak menandai aktivasi SQL/UAT selesai atau mengulang fase bisnis lama. Hasil 108 tes dan pola pasangan di Batch 253 adalah riwayat yang digantikan hasil/alur Batch 254 ini.

## Tindak lanjut Batch 254 — 2026-09-14 — Percobaan terminal untuk SQL 2026-09-14c

- User kembali meminta eksekusi SQL secara eksplisit melalui terminal. Penjelasan sebelumnya diluruskan: terminal tersedia; izin migrasi spesifik tidak berarti boleh mengubah credential/konfigurasi server atau menyentuh produksi.
- Pemeriksaan read-only: SQL berisi empat CREATE TABLE IF NOT EXISTS, penambahan tiga kolom dan perluasan enum TRANSFER; tidak ada penghapusan/backfill transaksi. Konfigurasi staging yang sudah ada dimuat hanya di memori, target dibatasi loopback; credential tidak dicetak/disalin/diubah.
- Koneksi mysqli gagal dengan errno 2002 (`Unknown error while connecting`) sebelum query SQL apa pun. Diagnostik socket tanpa koneksi membuktikan `socket_create(AF_INET, SOCK_STREAM, SOL_TCP)` ditolak dengan errno 1, `Operation not permitted`.
- Bloker aktual: sandbox sesi melarang pembuatan socket; approval/escalation tidak tersedia. Tidak mencoba melewatinya dengan proses lain, bridge atau jalur jaringan alternatif. Ini bukan hasil pengujian benar/salah password atau syntax SQL MariaDB.
- Hasil: **0 statement migrasi dieksekusi**, DB/schema tidak berubah; status SQL tetap **PREPARED, NOT APPLIED**. Tidak membuat backup/ledger apply palsu atau menandai validasi DB berhasil. Lanjut melalui terminal server langsung atau sesi IDE yang memang mempunyai izin koneksi database; pindah UI saja tidak menjamin akses jika sandbox tetap sama.

## Tindak lanjut Batch 254 — 2026-09-14 18:12 WIB — Konfirmasi eksekusi SQL oleh pengguna

- Pengguna menyampaikan sudah menjalankan SQL yang diberikan lewat terminal. Target yang dikonfirmasi pada percakapan adalah staging `db_finance`; file `sql/2026-09-14c_finance_allocation_bank_review.sql`. Waktu di atas adalah waktu pencatatan konfirmasi, bukan timestamp eksekusi database yang telah diverifikasi.
- Status terkini **USER_REPORTED_APPLIED / DIJALANKAN PENGGUNA — BELUM DIVERIFIKASI** menggantikan status pending sebelumnya. Agent tidak mengeksekusi statement DB, menerima keluaran client/exit code, memeriksa schema/ledger atau memverifikasi koneksi. Tidak menandai STAGING_PASS/UAT_PASS dan tidak mengulang migrasi otomatis.
- Kesepakatan ke depan: setiap SQL baru yang tidak bisa dijalankan agent harus ditandai BELUM DIJALANKAN; sertakan target, urutan/prasyarat, dampak, dan baris terminal siap salin tanpa password. Setelah pengguna mengonfirmasi, tandai DIJALANKAN PENGGUNA; naikkan menjadi TERVERIFIKASI hanya dengan bukti pemeriksaan. Status staging dan kesiapan clean-install/upgrade customer tetap terpisah.
- File dokumentasi diperbarui: roadmap `_30` termasuk register SQL dan prosedur pencatatan, handoff `_28`, laporan `2026-09-14_finance_alokasi_bank_rekonsiliasi.md`, addendum `2026-09-14_finance_control_workspace.md`, serta execution log ini. Catatan hasil percobaan terdahulu dipertahankan sebagai riwayat. Tidak mengubah SQL/checksum, source aplikasi, katalog migrasi, allowlist release, credential/config, database, bridge atau data transaksi.
- Validasi dokumentasi: roadmap consistency dan diff whitespace diperiksa pada pembaruan ini. Bukti tes kode terdahulu tidak dipresentasikan sebagai tes baru maupun bukti keberhasilan eksekusi MariaDB.
- Langkah berikutnya: verifikasi empat tabel/tiga kolom/enum TRANSFER dan status ledger setelah apply manual, lalu UAT Rekon Pendapatan NONE/IN/OUT/transfer, pergerakan saldo serta klasifikasi laporan, pencegahan posting ganda, dan integrasi alokasi/rencana/CSV. Registrasi migrasi/allowlist customer dilakukan setelah validasi teknis yang masih terbuka; bukan otomatis selesai karena SQL sudah dijalankan di staging.

## Batch 255 — 2026-09-14 18:22 WIB — Integrasi rekonsiliasi, mutasi rekening dan laporan

- Prioritas: menuntaskan pengujian kode dari hasil Simpan/Posting sampai saldo, daftar Mutasi Rekening, estimasi harian/bulanan dan laba-rugi manajemen. Fixer tunggal, data sintetis terisolasi. Tidak mencoba ulang akses DB yang sudah ditolak sandbox atau melibatkan bridge/Telegram notifier.
- File: `application/models/Finance_cash_reconciliation_model.php` (patch kecil helper posting); `tools/tests/finance_allocation_bank_smoke.php` (fixture kolom request key dan pemanggilan kasus); baru `tools/tests/finance_reconciliation_reporting_cases.php` (guard CLI + hanya SQLite :memory:). Dokumen `_30`, `_28`, laporan modul alokasi/bank/rekonsiliasi dan log ini diperbarui. Perubahan worktree lain dipertahankan.
- Kasus integrasi memakai model dan query builder asli: Finance_revenue_reconciliation, Finance_cash_reconciliation, Purchase, Finance_report, Finance_control_operation/Finance_insight, dan Pos_report. Cakupan delta mutasi terhadap basis POS/HPP tetap; tidak mengklaim semua skenario penjualan/HPP turut diuji ulang. Reader saldo/ledger dan laporan benar-benar dijalankan pada fixture, bukan stub nilai hasil.
- Hasil yang dibuktikan: NONE/draft tidak memposting; sembilan kategori (koreksi saldo IN/OUT) masuk kelompok laporan sesuai arah tanpa penjualan ganda; perubahan kategori tidak mengubah saldo; biaya dari rekon pendapatan tidak bisa diulang lewat kas/manual; VOID biaya mengembalikan saldo/laporan/sisa selisih, retry tidak menggandakan, sesi baru dapat memposting koreksi sekali. Transfer dua arah bernilai berlawanan, kas gabungan dan laba tetap, ulang posting/klasifikasi pendapatan/VOID satu sisi ditolak. Rantai balance_before + nominal bertanda = balance_after diperiksa sampai saldo rekening terbaru.
- Bug nyata ditemukan: helper `post_locked_mutation` pada Rekon Kas memaksa saldo akhir negatif menjadi 0, termasuk rekening tujuan transfer. Contoh -50 + 10 sebelumnya menjadi 0, bukan -40. Tes baru gagal sebelum patch. Perubahan menghapus pemaksaan nol; guard OUT tidak cukup tetap. Tidak menulis ulang saldo atau riwayat transaksi pengguna.
- Validasi: suite **392 PASS** (175 sebelumnya + 217 tambahan). Regresi mutation reporting **72**, workspace contract **27**, operations contract **35**, finance source A4 **21**, quality-gate contract **28** PASS; PHP lint tiga file PASS. Kasus baru otomatis dijalankan oleh required gate `finance-allocation-bank` existing; tidak menjalankan full gate seluruh aplikasi. Roadmap consistency/diff whitespace diperiksa setelah pembaruan dokumen. Tidak ada JS atau dependency Composer yang berubah.
- Review: tes reproduksi gagal sebelum patch dan lulus setelah patch; hanya perhitungan saldo tujuan yang dibetulkan, bukan izin, routing, data historis atau kontrak mobile. Transfer kas kini menjaga aritmetika nominal sama seperti transfer pendapatan. Tidak menyatakan bebas bug universal.
- SQL: **tidak ada SQL baru**. `2026-09-14c` tetap **USER_REPORTED_APPLIED** berdasarkan konfirmasi pengguna, belum ada verifikasi schema/ledger independen. Tidak mengubah checksum/registry/allowlist untuk menyamarkan pending validasi; tidak ada operasi DB/server/credential, backup/data deletion, push/deploy/build atau perubahan Control/bridge.
- Risiko sisa/batch berikut: verifikasi schema/ledger setelah apply manual, UAT operator di browser dan konkurensi MariaDB belum tersedia dalam sandbox; jangan menyebut tes sintetis sebagai UAT staging. Jalankan verifikasi pada sesi berizin dan UAT dengan transaksi uji pilihan pemilik, lalu sinkronkan migrasi/allowlist clean-install dan upgrade customer setelah bukti tersebut tersedia. Panduan/report tetap `docs/2026-09-14_finance_alokasi_bank_rekonsiliasi.md`; tidak membuat dokumen pegangan tambahan.

## Batch 256 — 2026-09-14 18:46 WIB — Pemeriksaan pascamigrasi read-only untuk terminal pengguna

- Prioritas/arah: menyediakan langkah verifikasi nyata setelah pengguna menjalankan 2026-09-14c, tanpa koneksi DB dari sandbox atau eksekusi ulang migrasi. Fixer tunggal, tidak menyentuh bridge/notifier. Persiapan alat selesai; pemeriksaan staging sendiri belum dilakukan.
- File baru: `tools/db/finance_allocation_postcheck.php`, `tools/tests/finance_allocation_postcheck_smoke.php`. Gate required `finance-allocation-postcheck` ditambahkan pada `finance_quality_gate.php` dan kontrak manifest-nya. Docs `_30`, `_28`, laporan modul alokasi/bank/rekonsiliasi dan log ini diperbarui. Tidak mengubah SQL sumber/checksum, runtime aplikasi atau worktree pengguna lainnya.
- Tool dua mode: --sql hanya menghasilkan SELECT, --report membaca TSV client dari stdin dan menghasilkan ringkasan. Tidak memanggil client sendiri, memuat config/credential, membuka koneksi/socket atau mengubah DB/file. Satu perintah pipeline diberikan untuk terminal pengguna, memakai mysql -p tanpa password dalam argumen dan bash pipefail. Database tujuan dibatasi db_finance pada pemeriksaan.
- 95 kondisi: seluruh kolom empat tabel baru 14c, tipe/unsigned/precision/default/nullability/auto increment, engine/charset, tiga kolom lawan + enum TRANSFER, metadata prasyarat serta index termasuk urutan/unique/prefix. Catatan migrasi dibaca hanya untuk empat file 13a/14a/14b/14c dan dibandingkan checksum serta ID canonical. Tidak membaca rekening, saldo, baris transaksi, profil pengguna, setting atau credential.
- Hasil dibedakan: struktur+ledger cocok exit 0; struktur cocok tetapi ledger kosong exit 2/perlu review; ketidaksesuaian atau keluaran tidak lengkap exit 1. Tidak menganggap ledger kosong sebagai bukti SQL belum diterapkan. Tidak menulis/memperbaiki registry atau menyuruh apply ulang otomatis. Fingerprint sumber, penanda BEGIN/END, daftar cek lengkap dan output bounded menolak hasil terpotong/duplikat/tidak dikenal/berubah; error tidak memantulkan input/argumen privat.
- Validasi: **241 PASS** termasuk SELECT aktual terhadap metadata SQLite :memory: (BINARY cast diadaptasi), 18 kondisi metadata/ledger salah, target berbeda, file kontrak tercakup, keamanan jenis query/read scope, reporter lengkap/terputus/duplikat/besar, dan subprocess CLI dengan input sintetis. Ini bukan validasi MariaDB live. Regresi integrasi **392**, workspace contract **27**, quality-gate contract **28**, lint empat file PHP PASS. Roadmap consistency/diff whitespace diperiksa setelah dokumentasi. Tidak ada perubahan Composer/JS dan tidak menjalankan full quality gate global.
- Review: alat tidak dapat mengubah schema atau transaksi; SQL yang sudah dijalankan pengguna tetap USER_REPORTED_APPLIED, belum ditandai STAGING_PASS/TERVERIFIKASI. Migrasi bukan bagian dari perintah verifikasi. Laporan membatasi klaim pada metadata/ledger; tidak menyatakan saldo bisnis, concurrency, browser/UAT atau paket customer lulus.
- Risiko sisa/batch berikut: pengguna menjalankan perintah read-only di laporan modul lalu mengirim seluruh ringkasan/error tanpa password. Setelah hasil ditinjau, perbarui status schema/ledger yang memang terbukti; jika ledger manual kosong, review registrasi terpisah berdasarkan bukti. Customer migration catalog/allowlist, clean-install/upgrade, UAT dan concurrency tetap pending. Tidak push/deploy/build, mengubah Control/server/credential/bridge, menghapus data, atau membuat SQL migrasi baru.

## Batch 257 — 2026-09-14 19:46 WIB — Pemeriksaan SQL tertunda pada staging

- Permintaan: jalankan SQL yang belum dijalankan. Keputusan fixer tunggal setelah preflight: tidak ditemukan SQL upgrade tertunda dalam katalog dan empat migrasi keuangan terbaru; tidak mengulang migrasi yang sudah ada atau seed instalasi kosong.
- Koneksi awal sandbox ditolak kode 2002. Setelah izin eskalasi eksplisit, memakai konfigurasi privat staging yang sudah ada, hanya dalam memori, dengan guard target lokal `db_finance`. Tidak memakai password dari percakapan atau menampilkan credential. MariaDB terverifikasi versi 10.11.10-MariaDB-log. Tidak mengubah konfigurasi/server.
- Query nyata 2026-09-14 12:46:50 UTC / 19:46:50 WIB: 95 pemeriksaan diterima lengkap melalui alat Batch 256. Sebanyak 90 kondisi struktur lulus, tabel catatan migrasi dan tiga ledger prasyarat cocok; satu WARN karena ledger 14c belum ada. Hasil `SKEMA_LULUS_LEDGER_PENDING`, exit 2. Fingerprint sumber `a692c0c274c524e72c32ecc53f358bcd97f86e0010070de686496372dcad50e1`.
- Inventaris: 28 SQL top-level, terdiri dari 20 managed, 7 legacy dan satu file 14c belum managed. Seluruh 20 hash sumber sesuai katalog; 19 entri upgrade memiliki ID/path/checksum ledger staging yang cocok, termasuk Roast Connect. Satu entri tanpa ledger adalah `2026-09-05d_a5_clean_install_reference_seed.sql`, policy clean_install saja; bukan pending upgrade, tidak dipasang pada staging berisi data. Struktur keseluruhan SQL legacy/Roast Connect tidak diaudit ulang.
- SQL 14c: empat tabel, kolom, enum TRANSFER, index dan prasyarat sudah sesuai; status dinaikkan dari USER_REPORTED_APPLIED menjadi **SCHEMA_VERIFIED_LEDGER_PENDING**, bukan STAGING_PASS penuh/siap rilis. Tidak menulis ledger otomatis atau mengubah checksum/catalog untuk menyamarkan apply manual.
- Operasi DB: SELECT metadata/registry dalam transaksi read-only, ditutup rollback. **Tidak ada DDL/DML/apply ulang, pembacaan baris transaksi, data repair, SQL baru atau backup yang diperlukan untuk perubahan DB**, karena tidak ada perubahan DB dilakukan.
- File berubah pada batch ini hanya dokumentasi: roadmap `_30`, handoff `_28`, laporan `2026-09-14_finance_alokasi_bank_rekonsiliasi.md`, serta execution log ini. Perubahan worktree lama milik pengguna tetap dipertahankan; tidak mengubah runtime, SQL, registry, credential, Control, bridge/notifier, commit/push/deploy atau menghapus file.
- Validasi: postcheck nyata 95 kondisi lengkap seperti di atas; hash katalog 20/20 cocok; tes alat sintetis 241 PASS; roadmap consistency 26 PASS/28 file SQL; pemeriksaan whitespace diff lulus. PHP lint/Composer tidak relevan karena tidak ada kode/dependency berubah.
- Review/risiko sisa/batch berikut: pencatatan apply manual 14c dan registrasi katalog/allowlist customer perlu ditinjau terpisah, berikut uji migrasi/replay/konkurensi dan UAT. Bukti schema saat ini tidak membuktikan seluruh nilai data, alur bisnis, schema legacy, atau paket customer siap. Tidak ada SQL tambahan yang perlu diberikan ke pengguna untuk dieksekusi pada batch ini.

## Batch 258 — 2026-09-15 09:22 WIB — Arus kas aktual dan fondasi jurnal akuntansi

- Prioritas/arah: permintaan pengguna agar semua kas masuk/keluar, bukan hanya sales/purchase, tercakup dan tersedia jurnal/laporan akuntansi. Fixer tunggal sesuai pola terbaru; audit/review dilakukan sendiri, tanpa subagent atau notifier/bridge. Acuan konsep IAS 7/IAS 1 dari IFRS Foundation dicantumkan di panduan; tidak mengklaim sertifikasi/kepatuhan penuh.
- Implementasi baru: `Finance_journal_policy.php`, `Finance_accounting_model.php`, controller `Finance_accounting.php`, view `finance/accounting.php`, JS `assets/js/finance-accounting.js`. Route accounting/index+post ditambahkan secara aditif; tab finance ditambah. Tidak menimpa perubahan lama pada route/tab atau menyentuh kontrak mobile.
- Arus kas membaca seluruh mutasi menurut tanggal, termasuk asal dan reversal lintas bulan, bukan filter effective yang menghapus jejak kas periode sebelumnya. Menyertakan rekening nonaktif, memisahkan mata uang, menampilkan saldo awal/IN/OUT/akhir dan drift current balance. Klasifikasi yang belum ditinjau tetap terlihat. Tidak mengubah laporan estimasi lama atau data historis.
- Jurnal metadata terpisah: saldo awal satu kali (termasuk pembukaan nol), sumber kas unik dengan fingerprint/row lock, penyesuaian nonkas, pembalik jurnal nonkas, akun pembalik kas dari asal, transfer via perantara. Debit/kredit memakai integer sen dan harus seimbang; header+lines+audit atomik, period lock, request idempotency, POST/CSRF/create permission, tidak ada edit/delete atau penulisan saldo/mutasi sumber.
- Laporan: jurnal, buku besar, neraca saldo, laba-rugi, neraca, perubahan ekuitas; menampilkan antrean, stale source, selisih kas, dan perantara transfer belum nol. Berlabel draf. Pengakuan akrual seluruh modul belum otomatis dan kelengkapan nonkas tidak bisa dibuktikan hanya dari debit=kredit; batas ini dijelaskan di UI/panduan.
- SQL baru `sql/2026-09-15a_finance_general_ledger.sql`: **BELUM DIJALANKAN**. Empat tabel, 24 COA template, satu guard, page/menu/izin SUPERADMIN. Tidak backfill/clear/write nominal transaksi. Target db_finance, perintah tanpa password disimpan pada `_30`/laporan modul; konfirmasi user dan postcheck terpisah. SQL 14c tidak diulang; katalog/allowlist customer tidak diubah.
- Tes baru: `finance_accounting_smoke.php` dan `finance_accounting_browser.cjs`; required quality gate + contract diperbarui. **162 PASS** model/controller/render SQLite :memory:, termasuk kasus akuntansi pajak/DP/modal/prive/loan/HPP/payroll/depresiasi, VOID lintas bulan, duplicate/replay, closed period, rollback audit, XSS, RBAC/CSRF/payload dan 22 render varian. SQL fixture diadaptasi, bukan bukti DDL/constraint/locking MariaDB.
- Regresi allocation/bank **392**, klasifikasi **72**, gate contract **28**, UI shell **54**, route collision PASS. PHP lint/JS syntax dan konsistensi register roadmap diperiksa. Composer tidak relevan, tidak ada dependency berubah. Browser offline disiapkan untuk 360/768/1280, percobaan gagal saat memulai proses (`read ECONNRESET`), **belum PASS browser/UAT**. Tidak mencoba melewati sandbox.
- Dokumen: `_30` (delta + register 29 SQL top-level), `_28` (handoff belum rilis), report modul `2026-09-15_akuntansi_jurnal_dan_arus_kas.md`, dan log ini. Roadmap consistency test menyesuaikan jumlah 29 file, tanpa melonggarkan pemeriksaan kesesuaian register.
- Review/perbaikan internal: saldo awal nol tidak boleh memblokir customer kosong; jurnal tanpa baris saldo nol tetap terlihat di history. Transfer/pembalik tampil dengan akun lawan otomatis, bukan form yang memaksa akun sembarang. Verifikasi sumber menggunakan satu join bounded, bukan N+1 query. SQL list journal menghindari GROUP BY ambigu. Kas jurnal untuk rekening berubah mata uang/hilang dan akun perantara belum nol diperingatkan.
- Risiko sisa/batch berikut: aktivasi SQL/postcheck, MariaDB concurrency/replay, browser/UAT, integrasi pengakuan otomatis sumber nonkas, kebijakan COA/kas/setara kas, pemecahan kelompok arus kas dalam satu mutasi, closing akuntansi dan persetujuan penerbitan belum selesai. Registrasi customer/clean-install/upgrade terpisah. Lanjut di IDE untuk eksekusi DB yang dilarang transport ini.
- Batas akses dipatuhi: tidak menghubungkan/mengubah DB aplikasi atau credential/config server, tidak push/deploy/build, tidak mengirim Telegram sendiri/mengubah bridge atau Control, tidak menghapus backup/upload/log/data. Seluruh penulisan DB pengujian hanya SQLite :memory: dengan data sintetis.

## Tindak lanjut Batch 258 — 2026-09-15 09:35 WIB — Konfirmasi SQL dan batas integrasi jurnal

- Pengguna menyatakan SQL sudah dijalankan. Dicatat untuk `2026-09-15a_finance_general_ledger.sql`, target pada perintah sebelumnya db_finance: **USER_REPORTED_APPLIED**. Waktu ini adalah pencatatan konfirmasi, bukan waktu eksekusi DB yang diverifikasi. Tidak mengulang SQL/checksum atau menulis catatan migrasi otomatis.
- Pemeriksaan kode: Arus Kas Aktual membaca seluruh mutasi rekening; antrean jurnal memuat mutasi IDR nonnol sesudah saldo awal, tanpa filter hanya sales/purchase. Posting jurnal masih melalui tindakan review di modul accounting. Tidak ditemukan panggilan auto-post GL dari modul POS/purchase/payroll/produksi/asset; pengakuan nonkas belum otomatis. Laporan baru membaca GL yang sama, laporan lama tidak otomatis bermigrasi ke GL.
- Panduan sudah ada pada tab `guide`, URL `/finance-reports/accounting?tab=guide`, mencakup saldo awal, antrean, akun lawan, nonkas, koreksi dan batas. Ini panduan dasar, belum asisten awam dengan kamus istilah, contoh transaksi berangka dan pemetaan akun otomatis.
- Rekomendasi urutan: postcheck/UAT; pemetaan akun dan panduan/asisten; integrasi bertahap pengakuan + pembayaran/pembalikan tanpa duplikasi; akrual HPP/persediaan/payroll/penyusutan; kontrol kelengkapan/tutup buku/penerbitan; validasi paket customer. Tidak mengimplementasikan fitur baru pada pertanyaan status ini.
- File diubah hanya `_30`/register SQL, `_28`/handoff, laporan modul akuntansi, execution log. Validasi dokumen dan regresi sintetis dijalankan; tidak ada validasi DB/browser baru, perubahan runtime/config/credential, bridge/notifier, push/deploy atau data transaksi.

## Batch 259 — 2026-09-15 — Asisten jurnal, pengaturan akun dan panduan awam

- Prioritas/arah: meneruskan pengaturan akun/pemetaan + panduan awam sesudah pengguna mengonfirmasi SQL 15a. Batas disampaikan di awal: tidak mengaktifkan auto-post, bukan pernyataan seluruh modul sudah akrual/sinkron. Fixer tunggal melakukan implementasi dan review, sesuai pola kerja terbaru; tidak memakai subagent, notifier atau mengubah bridge.
- File runtime: library baru `application/libraries/Finance_journal_assistant.php` dan trait `Finance_accounting_setup.php`; model `Finance_accounting_model.php`, controller `Finance_accounting.php`, view `finance/accounting.php`, partial baru `accounting_settings.php`/`accounting_guide.php`, `assets/js/finance-accounting.js`, dua route aditif di `application/config/routes.php`. Perubahan route/modul lain yang sudah ada dipertahankan.
- Pengaturan: UI akun nonkas (kode/tipe existing immutable, kas/perantara dilindungi, tanpa delete), mapping 15 skenario menurut arah/kategori dan klasifikasi O/I/F, pencarian lokal dan penanda bawaan/tersimpan/nonaktif. Pemetaan default tidak disimpan otomatis. Izin view/edit `finance.accounting.settings` terpisah dari posting. Gagal/stale menampilkan pesan inline, penyimpanan melalui POST/CSRF/guard/audit atomik dan retry identik no-op.
- Asisten: operator memilih jenis sesuai bukti, membaca penjelasan, mengonfirmasi lalu mengisi satu baris lawan; kas tetap berasal dari sumber. Saran tidak dipakai diam-diam atau auto-post. Perubahan baris/akun/nominal/kelas/jenis atau pencabutan konfirmasi membuat mode manual. Server menguji hash mapping+akun, status, arah/kategori, nominal/account/flow exact dan konfirmasi boolean; audit GL menyimpan provenance. Mapping diubah tidak menulis ulang jurnal lama; reversal tetap memakai akun asli.
- Panduan: peran admin/pengelola/operator, langkah UI setup + jurnal, kamus awam, contoh listrik/stok/HPP/modal/pinjaman/DP dan checklist akhir bulan. Dibedakan utang/pelunasan dengan biaya agar tidak diakui ganda. Referensi IAS 2/IAS 7 ditautkan pada sumber IFRS resmi; bukan klaim kepatuhan penuh. Filter bulan tidak muncul pada tab pengaturan/panduan.
- SQL baru `sql/2026-09-15b_finance_journal_assistant.sql`: **BELUM DIJALANKAN / PREPARED_NOT_APPLIED**, target `db_finance`, setelah 15a. Satu tabel mapping kosong + page/grant awal SUPERADMIN, tanpa data transaksi atau saldo/backfill. Perintah siap salin tanpa password pada laporan modul. SQL 15a tetap USER_REPORTED_APPLIED, tidak diulang/disunting checksum; belum ada postcheck independen. Katalog/allowlist/registry/customer/Control tidak disentuh.
- Tes: `tools/tests/finance_accounting_smoke.php` diperluas dengan `finance_accounting_setup_cases.php`: **346 PASS** model/controller dan 27 render varian termasuk partial nyata. Kasus SQL belum ada, COA/mapping/hash/retry/proteksi, audit rollback, kategori/arah/konfirmasi/tampering, semua skenario serta pembalikan setelah mapping berubah; hanya SQLite `:memory:` tanpa koneksi DB aplikasi. Bukan bukti FK/locking/DDL MariaDB.
- JS: `finance_accounting_client_smoke.cjs` **38 PASS**, JS produksi dengan DOM sintetis untuk event/payload/CSRF/retry/error-text/filter/readonly. `finance_accounting_browser.cjs` diperluas untuk asisten/pengaturan pada 360/768/1280; proses browser masih gagal dibuka `read ECONNRESET`, **belum browser/visual/native-form/UAT PASS**. Tidak mencoba eskalasi atau melewati pembatasan sandbox.
- Regresi: allocation/bank 392, klasifikasi mutasi 72, quality-gate contract 28, UI shell 54, route collision (0 konflik), roadmap consistency 26 PASS. PHP lint seluruh PHP yang diubah + routes dan JS syntax PASS; Composer tidak relevan karena dependency tidak diubah. Required gate accounting tetap menjalankan suite PHP yang kini memuat kasus setup. Tes client DOM dicatat sebagai perintah terpisah.
- Dokumen: `_30` delta dan register **30** SQL top-level (20 managed + 7 legacy + 14c/15a/15b di luar katalog), `_28` handoff belum rilis, laporan modul `2026-09-15_akuntansi_jurnal_dan_arus_kas.md`, execution log ini; `roadmap_consistency_smoke.php` menyesuaikan count tanpa melonggarkan pemeriksaan register. Checklist COA dipisah dari kebijakan kas/setara kas dan pekerjaan lanjutan, bukan menutup semuanya sekaligus.
- Review/perbaikan selama batch: menghapus legacy prefill yang dapat mengabaikan mapping nonaktif; hash mencakup nama/status/tipe akun sehingga preview stale ditolak setelah rename; proteksi akun yang sudah digunakan menjaga jalur pembalik. Renderer test benar-benar memuat partial baru; JS setting menormalisasi trailing slash endpoint. Nama akun/error output menjadi teks escaped, bukan HTML.
- Risiko sisa/batch berikut: apply 15b oleh pengguna/IDE lalu konfirmasi + postcheck; MariaDB DDL/replay/FK/concurrency serta UI/UAT pengguna. Integrasi pengakuan otomatis berbasis dokumen vs pembayaran, HPP/nonkas/payroll/penyusutan, kebijakan rekening/cashflow campuran, tutup buku dan persetujuan/penerbitan tetap terbuka. Handoff registrasi/clean-install/upgrade customer terpisah; rilis bukan dinyatakan selesai.
- Batas akses: tidak membuka/mengubah DB nyata, credential/config server, source saldo/transaksi; tidak menghapus backup/upload/log atau data runtime; tidak commit/push/deploy, mengirim Telegram sendiri atau mengubah Control/bridge. Penulisan DB tes semata di memory sintetis.

## Batch 260 — 2026-09-15 10:25 WIB — Perbaikan presensi PH otomatis

- Arah: pengguna melaporkan jadwal PH ditolak dengan pesan “Layanan validasi PH belum tersedia”; PH dengan jatah/hak aktif seharusnya tercatat saat membuka halaman absen. Fixer tunggal, audit/review langsung tanpa subagent. Lingkup kode presensi saja; permintaan jurnal berikutnya dijawab sebagai rencana, bukan mengaktifkan posting otomatis pada batch ini.
- Penyebab terbukti: `My_portal_model::ensure_auto_ph_presence()` memakai `method_exists($CI, 'load')`, sementara `system/core/Controller.php` mendeklarasikan loader sebagai properti `public $load`. Akibatnya preflight saldo tidak pernah dicapai. Kondisi yang sama pada `recompute_daily()` juga melewati sinkron GRANT/USE setelah presensi biasa.
- File runtime berubah: `application/models/My_portal_model.php`, `application/views/my/attendance.php`. Pemeriksaan memakai properti loader/callable model; validasi hak/saldo/tanggal PH, reservasi dan kontrak aktif tetap dipertahankan. Tidak mengubah Attendance_model atau formula payroll, akses pegawai, route, kebijakan/config database/server.
- Auto PH/PHB: pencatatan HOLIDAY dan USE satu hari; hasil menyatakan recorded terpisah dari noop agar catatan non-PH existing tidak dianggap sukses. Refresh tidak memakai jatah kedua atau menghitung ulang gaji. Hasil insert/status transaksi dicek sebelum commit; rollback pada kegagalan simpan. Snapshot kontrak dan perbedaan uang makan MONTHLY/CUSTOM tetap dipakai, tanpa grant karena cuti PH.
- UI: status PH otomatis serta pesan sukses/alasan belum tercatat. Tidak meminta tombol check-in/out, lokasi atau GPS untuk PH otomatis; browser tidak memanggil geolocation bila tidak ada form presensi. Shift kerja biasa tetap memakai form/lokasi/GPS existing. Tidak menimpa catatan attendance lain atau melakukan backfill data gagal terdahulu.
- Tes baru `tools/tests/attendance_auto_ph_smoke.php`: **58 PASS**, model portal dan Attendance nyata pada SQLite `:memory:` dengan DDL baseline diadaptasi dan adapter kompensasi sintetis. Cakupan loader normal/missing, PH/PHB, active/expired/not-yet-effective/insufficient/used quota, hari terakhir masa berlaku, kontrak invalid, regular/OFF/no schedule, refresh, existing status lain, rollback insert/salary/ledger/commit, GRANT kerja libur nasional/replay, render/error/escaping/GPS/form reguler. Tidak membaca pegawai/kontrak/transaksi nyata.
- Pencegahan regresi: required gate baru `attendance-auto-ph` di `tools/tests/finance_quality_gate.php` dan expected order di `finance_quality_gate_contract_smoke.php`. Review menemukan expected list harus berada pada urutan manifest yang sama; diselaraskan tanpa melemahkan pemeriksaan count/order.
- Validasi akhir: lint PHP model/view/test/gate; PH 58 PASS; A4.3 People/Payroll/Attendance contract 253, payroll meal-mode 7, accounting 346, client accounting 38, quality-gate contract 28, roadmap consistency 26 PASS (30 SQL top-level). Tidak ada dependency berubah; composer validate tidak relevan. Diff/whitespace diperiksa. Browser/live/MariaDB concurrency/constraint belum diuji dan tidak diklaim PASS.
- SQL 15b: pengguna mengonfirmasi sudah dijalankan. Status **USER_REPORTED_APPLIED**, dicatat pukul 10:25 WIB, target pada perintah db_finance. Ini bukan timestamp apply terverifikasi; schema/FK/page/izin belum dipostcheck. Tidak mengeksekusi ulang, mengubah file SQL/checksum atau menulis registry. **Tidak ada SQL PH baru**.
- Dokumentasi: delta/checklist dan register SQL `_30`, handoff `_28`, laporan `docs/2026-09-15_presensi_ph_otomatis.md`, pembaruan status SQL pada laporan jurnal, serta log ini. Perintah 15b pada laporan kini ditandai arsip, bukan instruksi menjalankan ulang.
- Risiko sisa/UAT: pegawai membuka Absensi Saya pada jadwal PH yang valid dan memeriksa satu kehadiran/USE, lalu refresh; uji role/reguler dan MariaDB konkurensi terpisah. Agent tidak membuka endpoint live karena kunjungan tersebut dapat menulis presensi. Tidak ada perbaikan data historis yang dieksekusi.
- Berikut jurnal/keuangan: postcheck aktivasi dan UAT alur mutasi → jurnal → laporan → koreksi; integrasi pengakuan otomatis bertahap (mulai sumber berkategori jelas, kemudian POS/purchase/pelunasan/refund dan HPP/payroll/asset), mencegah pencatatan ganda; kontrol kelengkapan tutup buku dan persetujuan/penerbitan. Semua tetap mengikuti checklist yang belum selesai, bukan klaim seluruh modul sinkron.
- Batas akses: tidak menghubungkan/mengubah database aplikasi, credential/config server/Control/bridge; tidak push/deploy/notifier Telegram sendiri; tidak menghapus backup/upload/log/data runtime. Semua penulisan tes hanya pada database memory sintetis.

## Batch 261 — 2026-09-15 11:13 WIB — Pusat Panduan Aplikasi untuk pengguna dan admin server

- Prioritas/arah: pengguna menyetujui pembuatan panduan umum dari instalasi/cron sampai operasional dan laporan. Fixer tunggal, review langsung; tidak mengulang audit seluruh aplikasi, tidak menggunakan subagent atau mengubah bridge. Panduan customer terkurasi, bukan membuka file docs internal lewat browser.
- Runtime baru: `application/controllers/User_guide.php`, `application/libraries/Finance_user_guide.php`, `Finance_user_guide_catalog.php`, `application/views/system/user_guide.php`, `assets/css/finance-user-guide.css`, `assets/js/finance-user-guide.js`. Tambahan routes hanya `/guide`; route dan kode existing lain dipertahankan.
- UI: 26 bab/enam kategori, filter peran/pencarian, satu bab per layar, daftar bab desktop terbatas tinggi dan dropdown mobile, langkah bernomor, hasil yang dicek, peringatan, link modul sesuai izin, cetak bab dan salin contoh. Navigasi/pencarian tetap GET tanpa JS; clipboard gagal memiliki petunjuk manual. Versi sumber dibaca dari field version manifest tervalidasi, bukan klaim rilis/deployment.
- Konten: setup customer clean vs DB-copy trial; identitas, multi-role, terminal/device vs API key, POS/order/refund/printer, pembelian/SR/stok/produksi, PH/payroll/uang makan/aset, kas/rekon/jurnal/tutup bulan, integrasi dan latihan serah-terima. Bab server memuat lokasi file/contoh JSON/FPM, installer/prerequisite, CLI env, cron/lock/log, backup/restore/upgrade. Keterbatasan APK dan auto-journal tetap jujur, bukan dinyatakan selesai melalui dokumentasi.
- Review menemukan heartbeat lama hardcode path staging dan backup membaca `.env` terpisah; diberi peringatan, bukan diubah/dijadwalkan. Worker POS menangani runtime dan availability; Telegram run_due sekaligus drain queue; scheduler alternatif tidak diduplikasi otomatis. Repair/migration/seed dan printer agent bukan cron server rutin.
- Security: View `system.guide.index` wajib; empat bab server juga perlu `system.guide.server`. Pembatasan terjadi sebelum pencarian/nav/render, termasuk akses slug langsung; related links berdasarkan izin tujuan. Validasi tipe/panjang/UTF-8/karakter kontrol, escape HTML/query/code, private no-store, GET-only; tidak ada file path input, baca secret/config customer, endpoint eksekusi atau mutasi bisnis baru.
- SQL baru `sql/2026-09-15c_application_user_guide.sql`: **PREPARED_NOT_APPLIED**. Dua page, satu menu Sistem (`ri-book-open-line`, URL guide), initial View SUPERADMIN saja; grants/menu existing tidak ditimpa. SQL mempunyai SELECT postcheck. Tidak terhubung/dijalankan pada MariaDB; perintah manual pengguna ada di laporan modul. Konfirmasi 15a/15b sebelumnya tidak dianggap mengaktifkan SQL panduan ini.
- Validasi: PHP lint sembilan file PHP scoped lulus, JS syntax dua file lulus. Tes actual controller/library/view, search/auth/escaping/manifest, seluruh bab/link, entry scheduler/runtime drift, resolusi Node gate dan seed metadata sequential replay pada SQLite memory **398 PASS**. JS actual-script/DOM sintetis **10 PASS**. Quality-gate contract **28 PASS**, roadmap consistency **26 PASS / 31 SQL top-level**. Diff whitespace scoped diperiksa. Tidak ada dependency berubah sehingga Composer validate tidak relevan; full runtime/DB gate tidak dijalankan.
- Tes baru `tools/tests/application_user_guide_smoke.php`, `application_user_guide_client_smoke.cjs`, ditambahkan sebagai required gate ke `finance_quality_gate.php` dan expected manifest `finance_quality_gate_contract_smoke.php`. Review menemukan resolver membutuhkan path executable absolut: runtime client disetel `/usr/bin/node` dan diuji, bukan sekadar nama `node`. SQL inventory test berubah 30→31, mempertahankan kesamaan persis dengan register; tidak melemahkan validasi.
- Dokumentasi: laporan `docs/2026-09-15_pusat_panduan_aplikasi.md`, delta/checklist/register `_30`, handoff/checklist C5 `_28`, serta log ini. Source CODE_READY bukan aktivasi DB/UAT/NOT_RELEASED berubah menjadi rilis. Migration catalog/allowlist/Control/paket tidak diubah.
- Risiko sisa/batch berikut: owner apply SQL 15c dan kirim konfirmasi/postcheck; uji sidebar/role/login ulang, MariaDB replay, browser 360/768/desktop, keyboard/print/clipboard, walkthrough operator awam dan admin pada trial. Kemudian registrasi/packaging guide dan migration untuk customer bersih serta acceptance C5. Panduan tidak membuktikan semua script bisnis atau hasil finansial bebas bug.
- Batas akses: tidak ada query transaksi/DB aplikasi, push/deploy, credential/config server/database, penghapusan backup/upload/log/runtime, notifikasi Telegram manual atau perubahan bridge. Hanya fixture memory dan file sementara tes manifest terisolasi; worktree pengguna/pekerjaan lain dipertahankan.

### Tindak lanjut Batch 261 — 2026-09-15 11:24 WIB — Konfirmasi apply SQL panduan

- Pengguna menyatakan SQL sudah dijalankan; terkait file terakhir yang diberikan, `2026-09-15c_application_user_guide.sql`. Status terkini **USER_REPORTED_APPLIED**, menggantikan PREPARED_NOT_APPLIED pada riwayat implementasi di atas. Waktu ini adalah pencatatan konfirmasi, bukan waktu eksekusi terverifikasi; target pada perintah sebelumnya `db_finance`.
- Hanya docs diperbarui: register/checklist `_30`, handoff/checklist `_28`, laporan pusat panduan dan log ini. Perintah apply pada laporan dijadikan arsip agar tidak dijalankan ulang. Ringkasan inventaris `_30` yang masih menyebut 30 SQL diselaraskan menjadi 31 (20 managed + 7 legacy + 4 belum managed), sesuai register/test yang sudah ada.
- Tidak mengubah source SQL/checksum, schema, role/menu/grants nyata, runtime, credential, konfigurasi, Control atau bridge. Tidak menjalankan SQL ulang, query database, push/deploy atau notifikasi Telegram sendiri.
- Validasi dokumentasi: roadmap consistency dan diff whitespace scoped. PHP/JS/Composer tidak diulang karena kode/dependency tidak berubah.
- Sisa: hasil SELECT metadata, sidebar setelah login ulang, izin operator/admin server, walkthrough/browser/cetak dan pembuktian artifact customer. Arah pengguna: buka `/guide` sebagai SUPERADMIN atau Sistem → Panduan Aplikasi. Konfirmasi apply tidak menutup C5 atau menyatakan izin/UI sudah terverifikasi.

## Batch 262 — 2026-09-15 15:08 WIB — Laporan penjualan diurutkan dari order terbaru

- Permintaan/arah: ubah urutan tabel `/pos/reports/sales` berdasarkan jam order, bukan payment. Fixer tunggal; tidak memperluas ke perubahan tanggal pengakuan/nominal laporan atau modul APK.
- Penyebab: `Pos_report_model::sales_summary_rows()` sebelumnya memakai `COALESCE(o.paid_at, o.confirmed_at, o.ordered_at) DESC`, sehingga order lama yang dibayar belakangan naik ke atas.
- Perubahan runtime: `application/models/Pos_report_model.php` hanya mengganti sort utama menjadi `o.ordered_at DESC`; `o.id DESC` tetap sebagai tie-break sebelum limit/offset. `application/views/pos/report_sales_index.php` memperjelas caption. Desktop/mobile mengonsumsi rows yang sama. Perubahan model sebelumnya milik pengguna tetap dipertahankan.
- Tidak mengubah filter periode yang masih memakai basis existing paid/confirmed/ordered, status/outlet/scope/payment method, agregat penjualan/refund/HPP/laba, riwayat pembayaran atau data. Waktu order NULL legacy ditempatkan terakhir oleh DESC, tidak memakai waktu payment sebagai pengganti.
- Tes baru `tools/tests/pos_sales_order_sort_smoke.php` **18 PASS**: builder rows model asli + recorder filter/order/limit, SQL ORDER BY/pagination hasil builder dieksekusi ke SQLite memory sintetis. Kasus order lama dibayar terbaru, konfirmasi terlambat, waktu sama/tie ID, timestamp pecahan, payment NULL, ordered_at NULL, ID impor besar, page boundary/empty page dan filter tidak berubah. Join/perhitungan finansial lengkap tidak dijalankan pada tes terarah ini.
- Pencegahan regresi: required gate `pos-sales-order-sort` dan expected manifest ditambah pada `finance_quality_gate.php`/`finance_quality_gate_contract_smoke.php`. Tidak melemahkan count/order gate.
- Validasi: PHP lint model/view/test/dua gate; tes sort 18, quality-gate contract 28 dan roadmap consistency 26 PASS; diff whitespace scoped. Tidak perlu Composer validate atau SQL baru karena dependency/schema tidak berubah. Browser/live DB/UAT tidak dijalankan.
- Docs: delta `_30`, handoff `_28`, execution log ini. Berikutnya cukup refresh laporan dan periksa urutan order pada filter yang biasa dipakai; data tidak perlu diperbaiki atau dimigrasikan untuk perubahan sort.
- Batas akses: tidak ada query/database/server/credential/config changes, push/deploy, penghapusan data/runtime, notifier Telegram manual atau perubahan bridge. Penulisan fixture hanya SQLite memory, bukan DB aplikasi.

## Batch 263 — 2026-09-16 05:55 WIB — Stok divisi terlihat sebelum verifikasi Purchase

- Prioritas/arah pengguna: persetujuan pengadaan bahan baku harus mempertimbangkan stok tersisa di divisi, bukan asal verifikasi. Fixer tunggal sesuai pola terbaru; tidak menjalankan diskusi antaragent atau meluas ke repair data. Kolom gudang memang disembunyikan ketika `canVerify`; sebelumnya tidak ada pembanding stok divisi.
- Implementasi: tampilkan kembali snapshot gudang, panel saldo material divisi/gudang dengan satuan isi, lokasi reguler/event dan waktu sumber. Jika divisi masih ada stok/negatif atau saldo belum diketahui, operator wajib mengisi nama pihak divisi, alasan dan centang konfirmasi. Saldo diketahui nol tidak dipaksa memberi alasan jika gudang normal. Pengadaan tetap bisa disetujui setelah konfirmasi, tidak auto-reject.
- Pengaman: HMAC sesi mengikat user/request/revisi/lokasi/baris/snapshot, kedaluwarsa 15 menit, baca ulang sebelum transaksi dan setelah lock request; unique evidence per request. CSRF form/API, scope preview, payload bounded, teks di-escape. Insert bukti satu transaksi dengan verifikasi/link/SR/PO; failure begin/commit tidak diklaim sukses. Riwayat diwariskan lewat link pada detail SR/PO, bukan dicatat dua kali.
- File runtime: library `Procurement_stock_review.php`; controller `Procurement.php`, `Purchase.php`; model `Procurement_model.php`; routes; view `procurement/{division_po_sr_form,division_po_sr_detail,store_request_detail,_stock_review_panel,_stock_review_history}.php`, `purchase/order_detail.php`; JS `procurement-stock-review.js`. File validasi: tiga `procurement_stock_review*` test, dua quality-gate manifest/contract, roadmap consistency. Docs: `_30`, `_28`, laporan modul `2026-09-16_konfirmasi_stok_pengajuan_divisi.md`, log ini. Perubahan lama di worktree dipertahankan.
- SQL baru `2026-09-16a_procurement_stock_review.sql`: **PREPARED_NOT_APPLIED**, satu tabel bukti+unique+FK, tanpa backfill atau perubahan saldo/izin/sidebar. Perintah mysql dengan password prompt pada laporan modul. Verifikasi bahan baku memerlukan apply ini; operasional murni tidak. Inventaris menjadi 32 SQL top-level (20 managed + 7 legacy + 5 belum managed); belum masuk katalog migrasi atau profil customer.
- Validasi: actual reader/policy/partial view SQLite **52 PASS**; actual model verification/normalizer/identity dan HTTP controller **114 PASS**; client synthetic DOM **19 PASS**; Roastery/mobile regression **48**, gate manifest contract **28**. Lint 16 PHP scoped dan syntax dua JS. Tes PO adalah controlled boundary, SQLite tidak mensimulasikan lock MariaDB; tidak ada klaim test end-to-end DB live. Composer tidak berubah, validate tidak relevan.
- Review fixer: menemukan penutup conditional view hilang saat lint, diperbaiki dan ditambah render test mode create/verify. Mencocokkan jumlah kolom tabel, invalidasi stale response, konversi/unknown tidak menjadi nol, scope dari header request server, rollback seluruh bukti/link/status saat PO gagal, serta tipe/max ukuran JSON bukti. Tidak menyentuh nilai master/stok atau transaction history existing.
- Risiko sisa: apply/postcheck/UAT nyata, request concurrent dan full SR/PO writer belum diuji di MariaDB; saldo point-in-time bukan reservasi. Pending pengajuan/SR/PO lain dan guard SR/PO manual di luar batch ini. Handoff release hanya dicatat di `_28`; belum artifact customer.
- Batch berikut: pengguna apply SQL dan UAT verifikasi, lalu deteksi outstanding pengadaan tanpa double count. Tidak ada push/deploy, perubahan credential/konfigurasi server/database, modifikasi bridge atau notifikasi Telegram sendiri.
- Validasi penutup: tes baru diulang **52 + 114 + 19 PASS**; roadmap consistency **26 PASS / 32 SQL top-level**, diff whitespace scoped bersih. Kolom/ukuran tabel verifikasi disesuaikan menjadi 12 kolom; satuan pengajuan tetap terbaca meskipun material belum terpetakan. Browser/DB live tetap belum diuji.

## Batch 264 — 2026-09-16 06:21 WIB — Review konfirmasi stok dan checklist manual

- Permintaan pengguna: SQL sudah dijalankan; review implementasi sebelumnya, cari bug tersisa dan buat checklist tes manual. Scope pada pengajuan divisi/konfirmasi stok dan tautan SR/PO, bukan mengaudit seluruh Finance lagi. Fixer tunggal menjalankan review tanpa perubahan runtime.
- Status SQL `2026-09-16a`: **USER_REPORTED_APPLIED**, menggantikan status pending pada laporan current/register. Dicatat berdasarkan pesan pengguna, bukan koneksi/query agent; target pada command sebelumnya db_finance. Tidak mengulang apply, mengubah source SQL/checksum atau mengklaim schema verified. Command lama di laporan menjadi arsip.
- Pemeriksaan: baca writer edit/verifikasi, stock reader, nonce/scope/CSRF, UI preview, fixture dan laporan. Tes existing **52 + 114 + 19 PASS**, pendukung Roastery **48**, quality contract **28**, roadmap **26**. PASS existing tidak menutup kasus yang sebelumnya tidak tercakup.
- **PR-01 HIGH, terbukti interleaving fixture:** `update_division_request()` memeriksa status/link sebelum transaksi lalu menulis tanpa lock/recheck. Setelah disimulasikan verifier lain sudah menyimpan VERIFIED + PO/link/bukti, edit asli tetap sukses dan mengganti qty/status menjadi SUBMITTED. Uji ini tidak menjalankan dua koneksi MariaDB; tetap bukti jalur server menerima stale edit.
- **PR-02 MEDIUM, terbukti policy fixture:** lookup item→material yang gagal pada item berlabel OPERASIONAL/material_id payload kosong ditelan sehingga item dianggap nonmaterial dan dilewatkan; validasi snapshot kosong menerima tanpa konfirmasi. Bukan klaim semua BAHAN_BAKU bypass.
- **PR-03 MEDIUM, terbukti DOM fixture:** fetch preview yang tidak resolve membuat tombol refresh disabled dan submit tertahan tanpa timeout/recovery aplikasi. Tidak mengubah koneksi/server asli; browser network timeout riil belum diuji.
- File baru hanya diagnostik: `tools/tests/procurement_stock_review_review_probe.php` (actual model/reader + SQLite memory) dan `procurement_stock_review_review_client_probe.cjs` (actual JS + synthetic DOM). Keduanya **exit 1** karena mereproduksi temuan; tidak diklaim tes hijau, tidak menurunkan/menonaktifkan gate existing. Nanti convert ke assertion perilaku benar + register gate saat fix.
- Docs berubah: `_30` register SQL + temuan prioritas; `_28` release acceptance blocker; laporan modul `2026-09-16_konfirmasi_stok_pengajuan_divisi.md` ditambah 19 checklist U01–U19 (lihat data, validasi, simpan dokumen uji, akses/history/konkurensi), hasil yang diharapkan dan format pelaporan. Tidak membuat roadmap ketiga atau menandai tes manual sudah lulus.
- Risiko/arah: hindari edit bersamaan dengan verifikasi satu pengajuan; jangan tes race pada transaksi nyata. Perbaiki PR-01 → PR-02 → PR-03 sebelum outstanding procurement/customer packaging. Full UAT, postcheck DB, E2E writer SR/PO, concurrent MariaDB tetap belum selesai. Browser companion tidak dijalankan karena puppeteer-core tidak tersedia pada default module path; tidak menginstal dependency baru.
- Tidak mengedit runtime, file SQL, credential/config/server/database, transaksi/master, Control, bridge, push/deploy atau mengirim notifikasi Telegram sendiri. Tidak perlu Composer validate karena dependency tidak berubah.
- Validasi penutup review: lint 11 file PHP runtime terkait dan probe PHP lulus; syntax JS runtime/probe lulus; roadmap consistency 26 PASS / 32 SQL dan diff whitespace docs tracked bersih. Tidak ada UAT pengguna, koneksi DB atau klaim tiga temuan sudah diperbaiki.

## Batch 265 — 2026-09-16 06:43 WIB — Review lintas perbaikan dan checklist UAT terpadu

- Koreksi cakupan pengguna: semua perbaikan sebelumnya, bukan hanya Pengajuan Divisi. Menelusuri roadmap dan riwayat Batch 1–264, laporan modul dan source terpilih; tidak mengulang implementasi atau menyatakan audit setiap baris seluruh aplikasi. Cutoff HEAD `a63a8a69` ditambah dirty working tree yang dipertahankan.
- Hasil: `docs/2026-09-16_checklist_uat_seluruh_perbaikan_finance.md`, **98 skenario / 23 kelompok** dari auth, sidebar/master, POS/order/printer, purchase/SR/stok/HPP, kas/rekonsiliasi/kontrol/alokasi/jurnal, PH/payroll/aset sampai label/identitas/panduan/notifikasi/APK/paket customer. Setiap tes memuat harapan, kebutuhan lihat/tulis/perangkat/admin; seluruh kotak manual kosong. Bukan roadmap ketiga, tertaut dari `_30` (termasuk matrix tes) dan `_28`.
- Review tunggal sesuai pola terbaru: membedakan implementasi, bukti regresi, UAT dan artifact release. Tidak mengklaim berdiskusi dengan subagent. Tambahkan contoh saldo kas vs hasil operasional/modal/transfer dan peringatan PH dapat menulis otomatis saat halaman dibuka.
- Validasi ulang **22 suite / 1.606 assertion agregat PASS**, daftar suite dan batas fixture pada checklist. Meliputi kas/mutasi, alokasi/rekon, jurnal dan client, kontrak kontrol keuangan, PH/uang makan, void tanpa resep/refund DP, scope, invalidasi HPP/cache, harga beli, printer, audit aktivitas, inventory/sidebar, Label Studio dan panduan. Lint **62 file PHP application** yang berubah/baru PASS. Tidak menjalankan full quality gate, DB aplikasi, browser/perangkat atau installer; assertion bukan klaim seluruh kasus tercakup.
- PR-01 Tinggi dan PR-02/03 Sedang tetap terbuka, direproduksi pada Batch 264; tidak diperbaiki dalam tugas review. Bukti 185 pemeriksaan pengajuan divisi dan defect probe Batch 264 tidak dihitung sebagai tes baru. Jurnal masih integrasi parsial, belum auto-jurnal akrual seluruh modul/tutup buku lengkap. MFA/bug operasional APK tetap ditunda owner, mismatch historis tidak direpair.
- Handoff paket: pemeriksaan read-only `tools/db/migration_catalog.json` dan `tools/release/customer_clean_profile.json` menemukan SQL `14c/15a/15b/15c/16a` belum managed/allowlisted, `12a` sudah managed tetapi belum allowlist SQL, serta contoh kode jurnal/stock-review/panduan belum allowlisted. Rincian pada checklist dan `_28`; tidak mengubah Control/profile/catalog/artifact atau menyatakan paket lama otomatis sudah membawa source terbaru.
- File berubah pada batch ini hanya checklist baru, dua roadmap dan execution log ini. Tidak ada SQL baru; `16a` tetap USER_REPORTED_APPLIED sesuai konfirmasi sebelumnya, bukan schema verified. Tidak mengubah source runtime, transaksi/master, credential/server/config/database, APK/bridge, push/deploy atau mengirim Telegram sendiri. Composer tidak diulang karena dependency tidak berubah.
- Berikutnya: pengguna mulai FIN → REC → CTL → ALC → GL → POS → HR → PUR di data/instance uji; catat ID dan hasil. Engineer perlu PR-01 → PR-02 → PR-03 sebelum penerimaan procurement/customer; integrasi jurnal dan paket dikerjakan terpisah setelah penentuan prioritas. Jangan edit-verifikasi satu pengajuan bersamaan sampai PR-01 ditutup. Kelulusan source tidak mencentang tes manual.
- Validasi penutup dokumentasi: roadmap consistency **26 PASS / 45 master rows / 32 SQL**, quality-gate contract **28 PASS** (bukan menjalankan seluruh gate); 98 ID manual unik / 23 kelompok, 0 kotak manual tercentang, 22 baris suite / total 1.606 assertion, tautan file lokal valid. Diff whitespace tiga docs tracked dan checklist baru bersih. Dua suite pendukung ini tidak termasuk angka 22 suite regresi modul di atas.

## Batch 266 — 2026-09-16 — Perbaikan blocker audit CUSTOMER_CLEAN Control

- Input: baca `/www/wwwroot/control/docs/2026-09-16_audit_finance_customer_clean_v3.md`, cutoff Finance bersih `db76e809acc67b293a7fb1844dcd91c08b9172be`. Scope packaging/test/installer Finance, tanpa intervensi source/database/trust live Control. Fixer tunggal; tidak mengklaim diskusi subagent.
- Tiga finding credential dipastikan fixture sintetis/socket disposable. `package_policy.json` menambah exception persis path/baris/kategori/SHA; scanner/regex/scope tidak dilemahkan. Contract test negatif mencakup perubahan semua binding, fixture tetap dipindai tetapi tidak dibawa customer.
- Setelah preflight lulus, tes mengungkap SQL Roast Connect managed tidak allowlisted, OSV snapshot stale, serta pemeriksaan seed/default/permission yang belum mengikuti migrasi baru. Konektor beserta SQL immutable masuk profil **v4**; source **alpha.13** dengan binding hash baru, tanpa menimpa identitas v3/alpha.12. Tidak mengubah SQL/checksum/catalog atau proses bisnis.
- Safe defaults: dua setting row baru hasil migrasi diuji isi/jumlah tepat dan tanpa token/divisi aktif; bukan exemption data usaha. Expected 213 page/251 menu/213 permission dan daftar post-baseline diselaraskan. Health checker memeriksa hak sesuai seed minimum, tidak menambah grants agar lolos. Predicate default/permission diuji negatif pada SQLite memory.
- Runtime tes: refresh OSV publik melalui script repository, bukan reset batas umur/checksum. Restore read ACL namua-build yang sudah ada pada empat file cache baru; tidak mengubah credential atau konfigurasi server aplikasi. Scanner menguji 145 package dari tiga lockfile, 0 advisory pada snapshot baru.
- Validasi PASS: preflight aktual 1.741 kandidat/959 PHP/0 finding; exception contract 39; OSV contract 15; adapter/default/permission 59; customer-content/TAR/signature 108; bridge 29; artifact contract 12; first-owner 14; health 17; mutasi laporan 72; roadmap 26; lint 9 PHP dan whitespace scoped.
- Uji DB aktual pada checkout/socket MariaDB disposable: 20 migrasi, 306 tabel, 293 nonreferensi kosong, 735 reference/default, 0 customer/demo; bootstrap owner test, health dan backup/restore logical checksums PASS. Inspeksi TAR 1.135 file/319 aset/21 SQL PASS. Output berlabel DATABASE_COMPONENT_TEST_ONLY_NOT_BUILD_EVIDENCE; bukan release/signing/publish.
- Build penuh membuka `PACKAGE_GATE_STATIC_FAILED`: actual PHPStan menemukan satu undefined `$month` pada closure URL view jurnal. Fallback eksplisit ditambahkan tanpa mengubah bulan controller, saldo/perhitungan/writer; static aktual lalu PASS baseline 0, regresi jurnal 348 + client 38 PASS. Tidak menambah ignore atau mengubah scope/toolchain. Build penuh sedang diulang; hasil penutup pada `docs/2026-09-16_finance_customer_clean_v4_handoff.md`. Lima SQL development yang belum managed tetap membuat suite katalog root gagal; fixture health dibatasi pada inventaris release deklaratif, tidak mematikan guard unacknowledged SQL.
- File perubahan: manifest; package policy/profil/reader; ControlDelivery; DisposableBuildDatabase; clean-install policy/post-install health; lima suite contract terkait; view jurnal + regresi render; dua roadmap/log dan satu handoff dengan hash 27 dependensi. Tidak ada controller/model/transaksi yang diubah. Tidak memperbaiki bug void batch/PR procurement diam-diam. Validasi tambahan: lint menjadi 11 PHP, static contract 20 dan clean baseline guard 20 PASS.
- Handoff: commit lokal sesuai permintaan audit, tanpa push/publish/deploy. Control harus review cutoff/profile v4/dependency baru dan ulang gate sebelum approval. Tidak ada SQL yang perlu dieksekusi di Finance aktif; hanya file/DB test sementara milik tes yang dibersihkan. Data usaha, backup, upload, credential dan bridge tetap utuh.
- Hasil penutup: build penuh terakhir **exit 0 / ISOLATED_INTEGRATION_FIXTURE_NOT_RELEASE**, seluruh delapan gate + validator independen PASS. 1.135 file termasuk, 324 dikecualikan, 735 reference/default, 0 customer/demo/secret; 306 tabel/293 kosong, 20 migrasi; backup/restore logical checksums cocok dan health PASS. Command `php tools/tests/c3_control_build_runtime_smoke.php --isolated`, dijalankan PHP 8.1, adapter masuk via PHP 8.4 lalu re-exec 8.1. Log `/tmp/finance-alpha13-full-build.log`. 35 file pin/runtime/test dibandingkan source uji vs workspace: 0 mismatch. Fixture punya commit sintetis sendiri dan dibersihkan, bukan release untuk dipublish; Control tetap perlu review/tes cutoff final. Hash 27 file pada laporan diverifikasi ulang tanpa mismatch. Tidak ada source/runtime perubahan setelah snapshot putaran terakhir, kecuali dokumentasi hasil.

## Batch 267 — 2026-09-16/17 WIB — Installer/heartbeat Finance–Control, domain bukan batas lisensi

- Permintaan: integrasi produk saja, customer memilih domain dan waktu mulai instalasi; kuota berbasis instalasi server. Parent bersih `65bc4c2c0d6d6149264282318db5c6c45ccb1391`. Fixer tunggal sesuai pola owner; review internal diff/tes, tanpa mengklaim diskusi subagent. Tidak mengubah source/database Control atau release 66 PUBLISHED.
- Temuan: ControlDelivery constructor mewajibkan hostname dan validatePlan menyamakan domain claim dengan job. Seluruh config juga menjadi binding journal, sehingga metadata domain mengunci resume. Sementara Control sudah menerima metadata opsional. Keduanya dihapus **hanya untuk domain**; origin/instance/token/deployment/plan/release/profile/signature/hash tetap dijaga. Job dapat mem-pin deployment_id dan plan_sha256. Tidak menimpa FINANCE_BASE_URL atau menambah deadline hak instalasi.
- Token: CLI menampilkan pesan pemulihan untuk credential invalid/expired/used/revoked. `replace` menyiapkan state_dir baru dengan hash journal lama dan menolak token lama/binding berbeda/target bekas; tidak ada staging/install/SQL. Journal v1 diarsipkan sebelum upgrade binding v2. Download error token dikenali terpisah dari integrity error dan tersimpan setelah restart. Riwayat aktivasi disimpan tanpa raw credential, mempertahankan ID/fingerprint/key. Kuota Control tetap menolak aktivasi.
- Heartbeat: runtime_fields opt-in, allowlist primary_domain/region; hostname dari URL deployment/customer privat, bukan HTTP_HOST, maksimal 190 byte; region maksimal 80 karakter Unicode tanpa kontrol. Omission/empty berbeda, sender lama kompatibel. Metadata berada di body HMAC; TLS/nonce/idempotency tetap. Root sender relatif, config privat diperketat ke root-owned/nonwritable. Tidak mengubah config/cron/credential terpasang.
- Validasi: delivery contract 27; customer-clean + TLS localhost/token pengganti/profil/hash/signature/replay SQL 131; heartbeat 37 termasuk validator Control aktual read-only; agent 72; license verifier 26; bridge 29; URL deployment 15; adapter/default/permission 59 SQLite memory; roadmap 26. Lint 10 PHP berubah PASS, whitespace bersih. Preflight aktual awal 1.741 kandidat/959 PHP/0 finding. Detail/batas fixture dan pemulihan pada `docs/2026-09-17_installer_heartbeat_control_handoff.md`.
- Build penuh pertama PASS: delapan gate + verifier independen; 1.135 file paket/735 seed/20 migrasi/306 tabel/293 tabel nonreferensi kosong/0 customer-demo-secret, restore checksum/health cocok. Snapshot putaran pertama berbeda pada tambahan guard ControlDelivery terakhir; tidak disamakan dengan cutoff final. **Putaran final selesai 2026-09-17 00:25 WIB, exit 0/delapan gate + verifier independen PASS**, metrics/restore/health sama; log `/tmp/finance-alpha14-final-build.log`. Seluruh 1.135 anggota profil dibandingkan dengan snapshot final: 0 mismatch. Preflight ulang setelah file tes baru masuk index: 1.743 kandidat/961 PHP/0 finding. Tidak ada perubahan runtime setelah snapshot final. Fixture tetap bukan release/receipt produksi dan commit sintetisnya bukan cutoff final; Control wajib review/build commit akhir persis.
- Perubahan file: app-manifest (alpha.14), ControlDelivery dan CLI, FinanceLicenseAgent dan CLI, heartbeat sender; lima file tes; guide heartbeat; kedua roadmap; log ini; handoff baru. Profil CUSTOMER_CLEAN v4/hash sama karena inventaris runtime/SQL/seed tidak berubah. Toolchain/lock tidak berubah.
- Review: domain bebas tidak sama dengan melepas signature/identity/kuota. State INSTALLING tetap menolak SQL ulang; clean install tetap menolak DB berisi. Ini bukan automatic recovery DDL parsial. Source Control dibaca hanya untuk kontrak; tidak bootstrapping ke DB/credential Control. Operator UI Control sudah punya reissue dengan step-up/alasan/konfirmasi installer berhenti; API reissue otomatis belum ada dan tidak dikarang.
- Risiko/batch berikut: Control review commit final/hash/27 pin + lima runtime di luar pin, build/uji cutoff persis sebagai release baru; operator menyelesaikan instalasi parsial dengan pemeriksaan ledger/health bila ada. Tidak ada SQL baru untuk staging/utama. PR bisnis/jurnal/APK/UAT dan lima SQL belum managed tetap terbuka, tidak ditandai selesai oleh hasil packaging. Tidak push/publish/deploy atau mengirim notifikasi Telegram terpisah.
- Catatan operasional: `stat` tanpa baca secret menunjukkan direktori config root:www 0750, tetapi file heartbeat default belum tersedia. Integrasi yang diuji adalah kode/fixture, bukan klaim heartbeat live aktif. Tidak membuat credential/config dummy atau memasang cron; admin menyiapkan config customer dari Control melalui deployment sah. Commit lokal memuat batch ini sebagai cutoff baru, tanpa push.

## 2026-09-24 09:55 WIB — Component FIFO, void, rebuild dan laporan nilai

- Prioritas: hentikan mismatch berulang dari perbedaan biaya FIFO vs rata-rata dan rebuild yang menghapus koreksi nilai. Implementasi fixer tunggal sesuai pola terbaru; tidak mengklaim diskusi/review subagent.
- Perubahan: POS memakai actual allocated cost, retur parsial membawa biaya alokasi, lot retur menjaga revaluasi saldo sisa, writer produksi/adjustment memakai biaya yang sama, opening membawa nilai, hitung fisik mengikuti biaya lot struktural existing. Rebuild hanya bulan aktif dengan period guard/lock, tidak menghapus ID monthly atau histori lama; koreksi/void koreksi tetap direplay. Perbarui proyeksi harian dan bucket adjustment.
- File runtime: `PosOrderStockService.php`, `ComponentStockWriter.php`, `ComponentLotManager.php`, `Production_model.php`. Tambah dua suite FIFO valuation; daftarkan regresi in-memory dalam quality gate beserta kontraknya. Update `_30` dan laporan modul; `_28` tidak diubah karena ini bug inventory, bukan perubahan kontrak komersial.
- Validasi: 45 pemeriksaan in-memory, 65 MariaDB 10.11.10 disposable, dashboard 19, revaluasi/cache 23, inventory/produksi 27, reversal invariant 8, reversal tanpa stok 45, step-up batch/adjustment/daily 21/19/16, availability 20, quality-gate contract 28 PASS. Lint 8 PHP dan static A4 application baseline 0 PASS. Matriks A2 masih FAIL pada dua ekspektasi hitungan CSRF POS existing; controller/config/view terkait tidak diubah, guard tidak dilonggarkan. Bukan klaim full gate/build release lulus.
- Review: kasus retur dua harga, adjustment minus lalu void, batch dipakai lalu pemakaian dibalik, koreksi setelah transaksi/backdate, replay, bukti ambigu, bulan tertutup dan ID audit tercakup pada service/fixture. UAT browser, beban paralel dan kombinasi lintas periode tetap terpisah.
- Tidak ada migrasi/repair aktif; MariaDB uji berjalan pada datadir/socket `/tmp` terpisah, tanpa membaca config/credential aplikasi. `database.php` pengguna dipertahankan. Tidak commit/push/deploy atau mengubah Control.
- Risiko sisa/batch berikut: UAT pada data uji → review selisih historis per identitas → koreksi nilai terarah setelah dampaknya disetujui. Legacy quantity drift pada hitung fisik tidak otomatis diperbaiki nilainya. Tindak lanjut kegagalan CSRF matrix secara terpisah.
- Rincian dan checklist: `docs/2026-09-24_component_fifo_value_consistency.md`; source dasar `a3bda02`, perubahan batch belum di-commit.
- Validasi penutup: `git diff --check` PASS. Roadmap consistency FAIL 2/26 (harapan 34 SQL top-level vs aktual 37, register belum sama); tidak ada SQL/register yang diubah batch ini. Dicatat terpisah, tidak menyamarkan kegagalan atau mengubah jumlah tes untuk meluluskan. MariaDB disposable dihentikan; artefak uji tetap tersimpan, tidak ada penghapusan data.

## 2026-09-25 — Multi-kasir Store/Street dan ringkasan sesi aktif

- Prioritas: dua pegawai/terminal berjalan bersamaan, kas/penutupan terpisah dan penjualan harian tergabung. Fixer tunggal dengan review diff dan tes; tidak ada diskusi subagent yang diklaim.
- Perubahan: row lock pegawai/outlet/terminal lalu recheck sebelum membuat sesi/nomor shift; validasi terminal aktif sesuai outlet; rollback kegagalan parsial dan pesan aman. UI menandai terminal sibuk dan mencegah klik ganda. Daily Sales/UI/PDF membaca nilai sesi OPEN, menampilkan terminal/outlet, dan mencakup sesi lintas hari. Order VOID tidak masuk jumlah/penjualan pada penutupan baru; snapshot penutupan lama tidak direpair.
- File runtime: `application/controllers/Pos.php`, `application/models/Pos_model.php`, `application/models/Pos_report_model.php`, `application/views/pos/cashier_index.php`, `report_daily_sales.php`, `report_daily_sales_print.php`. Tambah dua suite multi-kasir, registrasi quality gate dan kontrak; panduan/rincian `docs/2026-09-25_multi_cashier_store_street.md`.
- Validasi: 85 pemeriksaan MariaDB 10.11.10 disposable dengan dua proses PHP paralel; 11 JS behavior; mobile cashier binding 34; UI recent 9; otorisasi mobile; Daily Sales/PDF/notifikasi 52 + JS 10; sales sort 18; reversal tanpa stok 45; step-up reversal 54; FIFO/retur in-memory 45; quality gate contract 28 PASS. Lint 9 PHP dan diff whitespace PASS. Dua masalah fixture awal diperbaiki tanpa melonggarkan aturan aplikasi.
- Review: terminal tunggal hanya satu pemenang, pegawai tunggal idempotent, nomor shift berbeda, pembayaran multi-line tidak menggandakan angka laporan, tutup Store tidak menutup Street, partial failure tidak meninggalkan shift yatim. Jalur backup web/APK dan guard RBAC/CSRF/recon tetap.
- Batas: belum UAT browser/APK/printer fisik atau full global gate/build release. Ringkasan sesi sepanjang durasi bukan subtotal harian; tidak mengubah pola DP/refund legacy maupun snapshot lama. Tidak membuat akun/terminal atau menyentuh data/config/credential aktif. Tidak ada SQL baru, commit, push, deploy, atau perubahan Control. Database disposable dihentikan dan diagnostik disimpan.
- Berikutnya: UAT dua perangkat sesuai checklist; gunakan pegawai dan terminal berbeda. Distribusi customer mengikuti build/review resmi Control.
