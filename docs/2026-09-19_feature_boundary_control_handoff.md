# Batas fitur customer — alpha.21 / CUSTOMER_CLEAN v9

Status: batas paket terimplementasi dan alur Starter diuji terisolasi. **Bukan persetujuan publish/rollout**, bukan bukti integrasi Control live. Update alpha.20 aktif masih mempunyai gap di bawah.

## Penyebab dan keputusan

- Alpha.20 memiliki verifier customer tetapi `Feature_gate` masih audit-only dan tidak menjadi batas route. Superadmin RBAC dapat membuka semua modul.
- Alpha.21 mengambil snapshot **dokumen lisensi terverifikasi pada bootstrap**, kemudian memeriksa controller/method/argumen kanonis sebelum constructor controller bisnis dijalankan. `MY_Router` mengalihkan penolakan ke shell aman bahkan jika controller opsional tidak dikemas; `MY_Hooks` menolak payload aksi dan tetap bekerja meskipun optional CI hooks dimatikan.
- `FINANCE_FEATURE_BOUNDARY_V1`: daftar eksplisit di `application/config/feature_access.php`, tidak menebak entitlement berdasarkan prefix URL. Alias CI berujung pada keputusan kanonis yang sama. Method/entity baru yang belum direview ditolak.
- Grant fitur harus boolean `true`, status ACTIVE/GRACE, dan dependensi katalog terpenuhi. Angka/string tidak menjadi grant boolean. Nama paket hanya label; browser/session/SQL tidak menjadi sumber hak produk.
- Tidak ada perubahan entitlement/edisi di Control. Matriks menggunakan katalog Finance yang telah ada (Starter hanya lima boolean dasar). Penafsiran kemampuan dasar di bawah perlu direview Control sebelum cutoff disetujui.
- Akun customer superadmin tidak mempunyai bypass paket. Setelah paket mengizinkan request, RBAC/CSRF/otorisasi objek yang sudah ada tetap berjalan. Legacy/development tanpa konteks customer tetap mempertahankan perilaku sebelumnya.
- Sidebar hanya mencache struktur/RBAC, bukan hasil entitlement. Badge SVG inline dihitung ulang dari snapshot lisensi per request. Sinkronisasi membuka/menutup fitur pada request berikutnya tanpa logout.
- Penolakan halaman memakai shell/sidebar; POST/AJAX/API mengembalikan 403 `FEATURE_UPGRADE_REQUIRED`. Route belum dipetakan memakai `FEATURE_ACCESS_UNAVAILABLE`. Controller bisnis tidak diinstansiasi. Pencatatan akses/session tetap dapat berlangsung pada halaman terkunci, bukan transaksi bisnis.

## Matriks paket dan dependensi

Sumber otoritatif endpoint lengkap: `application/config/feature_access.php` (`groups`, `entities`, `pages`). Anggota grup adalah daftar method eksplisit, bukan wildcard izin. Endpoint baru wajib masuk review/test.

| Fitur/hak | Paket katalog minimum | Kebutuhan dasar / aksi diizinkan | Tetap terkunci tanpa hak tambahan |
|---|---|---|---|
| BUSINESS_PROFILE | Starter | profil usaha; bank/rekening metadata; outlet, terminal, kanal/metode bayar (`pos/payment_*`, `outlet_*`, `terminal_*` sesuai daftar exact) | koreksi saldo rekening langsung / mutasi / analisis Finance Advanced |
| RBAC_CORE | Starter | users, roles, sidebar; identitas pegawai, divisi/jabatan untuk kasir; tools pemulihan operasional dengan RBAC lama | detail pegawai berisi kompensasi/absensi, portal HR/payroll |
| POS_WEB | Starter | cashier open/close; katalog produk, satuan, kategori, harga, extra/bundle; draft, konfirmasi, pembayaran, void/refund; lookup identitas pelanggan dasar | program point/stamp/voucher; layar stok/HPP, resep, self-order, reservasi, online-inbox, APK |
| POS_PRINTER | Starter | pengaturan/cetak/reprint/struk dan agen printer yang terdaftar | tidak memberi hak membuat reservasi/online order |
| SALES_REPORTING | Starter | laporan penjualan/detail/bukti transaksi/pembayaran/refund/void/tutupkasir | HPP cost-control, rekonsiliasi pendapatan, jurnal/laporan Finance Advanced |
| INVENTORY_WAREHOUSE | Operations | layar gudang/bahan/divisi, master item/material dan pergerakan yang dipetakan | rekonsiliasi/adjustment lanjutan memerlukan INVENTORY_RECON |
| PROCUREMENT | Operations | procurement/store request/purchase serta master pemasok/katalog | finance/recon yang berada di controller Purchase tetap memerlukan hak masing-masing |
| INVENTORY_RECON | Operations | lot/FIFO/opname/reconcile/adjustment serta dashboard mismatch | bukan jalan memanggil produksi/payroll |
| CUSTOMER_LOYALTY / PROMOTION_VOUCHER | Operations | program, saldo/award, pencarian/penerapan voucher pada POS | payload promo yang disisipkan ke endpoint pembayaran biasa ditolak sebelum transaksi |
| SELF_ORDER / RESERVATION | Operations | seluruh layar, API dan aksi verifikasi yang terdaftar | Starter tidak membuat/mengelola pesanan di kanal tersebut |
| RECIPE_PRODUCT / COMPONENT_PRODUCTION / HPP_CONTROL | Control | resep, produksi/roastery, formula, component, analisis HPP | biaya lanjutan pada master product/detail dipisahkan dari katalog/harga jual dasar |
| FINANCE_ADVANCED / PERIOD_AUDIT | Control | mutasi/manual balance, utang/piutang, jurnal, insight, laporan resmi, tutup periode/audit | pencatatan kas internal POS bukan grant atas semua layar finance |
| ATTENDANCE / PAYROLL / ASSET_MANAGEMENT | Control | presensi, kontrak, payroll/bonus, asset | profil pegawai dasar tetap tersedia untuk akun kasir |
| POS_MOBILE_APK | Enterprise | mobile endpoints terdaftar; kanal tambahan tetap butuh hak masing-masing | entitlement APK sendiri tidak memberikan promo/reservasi/online |
| ONLINE_ORDER / INTEGRATION_API / AUTOMATION_MESSAGING | Enterprise | online food/landing, Roast integrations, Telegram/WA termasuk scheduler/webhook terdaftar | Starter tidak menjalankan kanal/integrasi berbayar |
| SINGLE_SIGN_ON / CUSTOM_REPORT | Enterprise | hanya grant yang diketahui katalog; bukan wildcard route | method baru tidak otomatis diizinkan |

Kemampuan internal yang sengaja tetap tersedia untuk POS:

- Model/service stock commit, FIFO/biaya internal, ketersediaan produk, queue konfirmasi order dan reversal tetap berjalan melalui transaksi POS yang sudah diotorisasi. Tidak membuka layar pengelolaan inventory.
- Posting penerimaan kas ke rekening pembayaran tetap berjalan. Struk dan bukti historis tetap tersedia. Penyelesaian/reversal kewajiban DP atau promo historis tidak menghapus bukti transaksi lama; pembuatan/penerbitan program baru mengikuti entitlement.
- Gate inventory daily-recon tidak memaksa kasir Starter masuk layar yang tidak dimiliki. Perhitungan uang/stok tidak diganti.
- Dashboard lama menggabungkan inventory/produksi/finance; paket yang tidak mempunyai seluruh hak dataset tersebut mendapat beranda netral dengan sidebar lengkap, bukan query data berbayar lalu menyembunyikannya.
- Master produk dan pegawai menyediakan detail dasar ketika analisis HPP/HR tidak dimiliki. Payload forged saldo/HPP/sumber-resep extra ditolak; field tersembunyi tidak mereset nilai lama saat edit metadata dasar.
- Akun yang hanya memiliki RBAC kasir dapat login langsung ke POS tanpa hak beranda atau portal HR. Nilai flash redirect `NULL` pada customer dinormalisasi sebelum memilih tujuan berlisensi; login development tidak berubah.

## Integritas / kontrak build

- Source version `0.1.0-alpha.21`, profil `CUSTOMER_CLEAN` **9**, kontrak baru `feature_boundary_contract=FINANCE_FEATURE_BOUNDARY_V1`.
- Single-folder V1, guided setup V1, customer local V1, aturan `UNTIL_USED_OR_REVOKED` tetap. Profil v8 yang sudah diterbitkan tidak diubah. Tidak ada migration/schema baru.
- Runtime critical inventory v9 mencakup hook/policy/config, framework loader/hook/controller, manifest, shell terkunci, sidebar dan seam Dashboard/Master/Pos_model. Komponen hilang/berubah menahan bootstrap, bukan membuka fitur. Inventory penuh tetap diverifikasi installer.
- Control perlu menerima **v9 secara eksplisit**, membaca hash rules dari manifest baru dan memakai validator/toolchain Finance cutoff yang sama. Jangan mengubah signed ZIP/TAR alpha.20, entitlement database, signature/trust, kuota, atau persetujuan keamanan lama.
- Guideline customer `docs/customer_packages.md` disertakan paket baru. Tidak ada link ke halaman admin Control atau endpoint pengajuan upgrade yang dikarang.
- Template server lama menolak semua `/system/...`. Template nginx/Apache/IIS baru mengarahkan **hanya route exact** license, business-profile, feature-access, activity-audit dan roast-connect beserta save/token ke front controller; seluruh path sumber `system/` lainnya tetap ditolak. Tidak ada perubahan server aktif. Linux nginx diuji pada fixture; Apache/IIS belum diuji pada host nyata. Control perlu memperhitungkan perubahan template ini pada update lane.
- Dependency build: PHP 8.1 + sodium/mysqli/PDO_mysql, Composer platform yang sudah dipin proyek, GNU tar/git; acceptance Linux menggunakan MariaDB 10.11, nginx/PHP-FPM, curl, akun worker/web terpisah. Control mock memakai issuer acak khusus test, tidak masuk allowlist paket.
- Builder menjalankan `feature_boundary_contract_smoke.php` sebagai pemeriksaan wajib. Bukti ditambahkan pada `security_scan.feature_boundary_sha256` dan anggota `required_gates=feature_boundary_contract`; delapan gate tingkat atas Control tetap. Control harus mereview dan memperbarui pin toolchain/validator beserta dependensi test ini, bukan memakai builder lama untuk profil v9.

## Update instalasi alpha.20 aktif — GAP yang harus ditutup

Companion portable saat ini adalah **clean installer + license sync/heartbeat**, bukan updater atomik release aplikasi aktif. `complete.json` mengunci pemasangan ulang. Memperbarui entitlement saja tidak memasang feature gate baru pada alpha.20.

Jangan menimpa seluruh folder, mengimpor ulang baseline, menghapus journal, atau menjalankan aktivasi baru. Thread ini tidak menyentuh core2.

Sebelum rollout ke core2, Finance/Control perlu update lane terverifikasi yang:

1. Menerima manifest/signature release baru dan authorization update yang mengikat instalasi/deployment lama; memakai endpoint/kontrak Control yang benar-benar disepakati.
2. Memeriksa cutoff, profil v9, hash inventory, platform, versi asal; mencatat journal update terpisah dan checkpoint pemulihan.
3. Menyiapkan kode immutable baru di staging lokal, dengan maintenance gate dan pergantian kode + bukti konteks secara atomik/rollback teruji.
4. Mempertahankan `config/customer.json`, storage/upload, database, private agent/trust/identity, installation ID, activation ID dan watermark anti-replay. Release ini tidak membutuhkan SQL.
5. Menjalankan health + login + Starter POS/denial smoke; kirim receipt UPDATE hanya bila kontraknya tersedia. Kuota tidak dikonsumsi lagi karena tidak membuat aktivasi server baru.

Mekanisme update aktif tersebut **belum diimplementasikan oleh patch ini**. Fresh package dapat diuji terisolasi; rollout alpha.20 harus ditahan sampai update lane dan simulasi rollback disetujui. PHP pada mesin milik customer tidak diklaim mustahil dibypass.

## Berkas yang berubah

Daftar source/config/view/tooling/test/dokumentasi pada batch ini; tidak termasuk artefak fixture yang sudah dibersihkan:

- `app-manifest.json`
- `application/config/feature_access.php`
- `application/config/routes.php`
- `application/controllers/Auth.php`
- `application/controllers/Dashboard.php`
- `application/controllers/Feature_access.php`
- `application/controllers/Master.php`
- `application/core/MY_Hooks.php`
- `application/core/MY_Router.php`
- `application/libraries/Control_license_cache.php`
- `application/libraries/Feature_gate.php`
- `application/libraries/Feature_policy.php`
- `application/models/Pos_model.php`
- `application/views/layout/sidebar.php`
- `application/views/master/detail_basic.php`
- `application/views/pos/cashier_index.php`
- `application/views/system/feature_home.php`
- `application/views/system/feature_locked.php`
- `application/views/system/feature_upgrade.php`
- `docs/2026-09-19_feature_boundary_control_handoff.md`
- `docs/customer_packages.md`
- `index.php`
- `tools/build/CustomerBuild.php`
- `tools/install/ControlDelivery.php`
- `tools/install/portable/LinuxPreparation.php`
- `tools/install/portable/PortablePackage.php`
- `tools/install/portable/SetupUi.php`
- `tools/install/portable/layout.json`
- `tools/install/portable/nginx.conf.example`
- `tools/install/portable/public.htaccess`
- `tools/install/portable/web.config`
- `tools/release/CustomerReleaseProfile.php`
- `tools/release/customer_clean_profile.json`
- `tools/static/ci3-stubs.php`
- `tools/tests/application_user_guide_smoke.php`
- `tools/tests/c2_c4_commercial_foundation_smoke.php`
- `tools/tests/c3_customer_clean_release_smoke.php`
- `tools/tests/customer_durable_permission_smoke.php`
- `tools/tests/customer_guided_acceptance.php`
- `tools/tests/customer_guided_contract_smoke.php`
- `tools/tests/customer_guided_control_fixture.php`
- `tools/tests/customer_portable_acceptance.php`
- `tools/tests/customer_portable_contract_smoke.php`
- `tools/tests/customer_portable_worker_fixture.php`
- `tools/tests/feature_boundary_browser.cjs`
- `tools/tests/feature_boundary_contract_smoke.php`
- `tools/tests/feature_boundary_http_cases.php`
- `tools/tests/finance_control_operations_contract_smoke.php`
- `tools/tests/finance_control_workspace_contract_smoke.php`
- `tools/tests/finance_quality_gate.php`
- `tools/tests/finance_quality_gate_contract_smoke.php`
- `tools/tests/master_endpoint_registry_smoke.php`

## Bukti dan cutoff

Tanggal pengujian: 19 September 2026, Linux. Base sebelum patch: `0a7e0720a4739f0cd189bf5a138b01421e65689f` (alpha.20). **Cutoff kode implementasi: `d13e350892b0d0b99e5fa9743137f73eb65c5a57`**, commit lokal; commit sesudahnya hanya melengkapi dokumen bukti/cutoff ini. Tidak ada push, publish, perubahan Control/core2, maupun SQL untuk database aktif.

| Pemeriksaan | Hasil |
|---|---|
| `php tools/tests/feature_boundary_contract_smoke.php` | 2142 PASS: 47 controller, 1025 action exact, 36 entity, dependensi, alias, grant boolean/numeric/absent, legacy |
| `php tools/tests/customer_portable_acceptance.php --disposable --feature-boundary` | **137 PASS**, database nyata disposable + HTTPS nginx/FPM non-root + Chrome nyata; Control sintetis dengan signature nyata |
| `php tools/tests/a4_static_analysis_smoke.php` | PASS, application baseline_errors=0; tidak menambah ignore/baseline error |
| `php tools/tests/c4_control_license_verifier_smoke.php` | 26 PASS |
| `php tools/tests/c4_control_license_agent_smoke.php --protocol-only` | 24 PASS; signature/cache/replay/grace/revocation |
| `php tools/tests/c3_customer_clean_release_smoke.php` | 118 PASS; fixture tanpa database aplikasi |
| `php tools/tests/c3_control_build_adapter_smoke.php` | 59 PASS; kontrak, bukan bukti build live |
| `php tools/tests/c3_control_build_runtime_smoke.php --isolated` | **PASS delapan gate**, builder non-root: source_clean, security_scan, install_test, backup_restore, customer_data_scan, secrets_scan, clean_install, source_untouched |
| portable / guided / durable contract | 42 / 23 / 27 PASS |
| commercial foundation / master endpoint registry | 14 / 47 PASS |
| quality gate manifest / finance workspace / finance operations contract | 28 / 27 / 35 PASS |
| auth login throttle / division scope / inactive-role permission | 74 / 42 / PASS; union RBAC dan scope lama dipertahankan |
| PHP lint seluruh PHP berubah; `git diff HEAD --check`; JS syntax | PASS |

Rincian 137 acceptance:

- Baseline + tepat 20 migrasi clean-install, akun pertama, login web dan config CLI/FPM; setup terkunci, resume checkpoint tidak mengulang baseline, status DDL yang tidak pasti berhenti.
- STARTER_POS memakai lima grant boolean dan limit numerik katalog, bukan Enterprise tersamar. Form produk tanpa HPP → simpan produk via web/CSRF → buka kasir → draft → konfirmasi → bayar Rp20.000 → rekening internal bertambah Rp20.000 → struk HTML/payload printer → laporan → tutup kasir selisih nol.
- Master/printer/payment/outlet/identitas pegawai dasar tetap dapat dibuka. Pengaturan daily-recon lama tidak memaksa Starter membuka Inventory yang terkunci. Akun kasir-only dapat login dan membuka POS.
- Superadmin customer ditolak pada Inventory, Payroll, Finance Advanced, reservasi, component dan APK. Halaman terkunci memakai layout/sidebar; alias/POST/AJAX/download ditolak sebelum handler bisnis. Snapshot row-count bisnis dan rekening tidak berubah setelah request terlarang.
- Chrome membuktikan halaman upgrade dan SVG gembok mempunyai kotak yang benar-benar terlukis. Tidak hanya menguji string HTML.
- Dokumen Enterprise valid melalui jalur sync biasa membuka fitur pada sesi yang sama, menghilangkan badge dan mempertahankan order, identity dan journal SQL. Pengguna Enterprise tanpa RBAC payroll tetap ditolak. Penggantian kembali ke Starter mengunci lagi tanpa logout.
- Grant kosong, signature rusak, revoked, fingerprint berbeda dan policy/router/hook/config dimodifikasi tidak membuka aplikasi. Kuota aktivasi sintetis yang habis menahan instalasi sebelum SQL. Tidak ada aktivasi ulang pada pengujian upgrade entitlement.
- Path config/private/system-source tetap 404; akun web tidak dapat membaca private agent atau mengganti cache lisensi. Route `/system/license`, `/system/business-profile`, `/system/feature-access` tetap berfungsi.

Batas bukti: tidak ada panggilan aktivasi/sinkronisasi ke Control live; issuer dibuat khusus test. Printer fisik tidak dikirimi job, hanya payload dan struk diuji. Penjualan end-to-end memakai produk tanpa resep; FIFO/recipe multi-divisi, bundle/extra kompleks, refund/void lengkap, semua kombinasi pembayaran dan semua modul Enterprise belum seluruhnya diuji end-to-end dengan entitlement baru. Matriks/regresi memastikan jalur POS tersebut tetap berizin, tetapi bukan pengganti UAT lengkap. Windows/IIS dan Apache belum diuji pada host nyata.

### Gate global yang belum hijau — jangan disamakan dengan 137 acceptance

`php tools/tests/finance_quality_gate.php release` belum lulus keseluruhan. Setelah penyesuaian test versi/profile dan stub CI, kontrak/static terkait patch lulus. Gate lama katalog/migration executor/legacy inventory/schema fingerprint/legacy-upgrade masih gagal karena `php tools/db/migration_runner.php validate` menghasilkan `unacknowledged_sql`. Lima file yang sudah ada sebelum patch dan belum terdaftar:

- `sql/2026-09-14c_finance_allocation_bank_review.sql`
- `sql/2026-09-15a_finance_general_ledger.sql`
- `sql/2026-09-15b_finance_journal_assistant.sql`
- `sql/2026-09-15c_application_user_guide.sql`
- `sql/2026-09-16a_procurement_stock_review.sql`

Katalog dan SQL tersebut tidak diubah oleh patch. Jangan mendaftarkan/menjalankannya sembarangan untuk menghijaukan gate. Browser CSP global juga pernah timeout; bukan kegagalan probe Chrome feature-boundary yang sudah lulus. Percobaan build adapter penuh terisolasi awal berhenti `CLIENT_TIMEOUT`; **pengulangan pada kode final lulus seluruh delapan gate build**. Keberhasilan build profil customer tidak menghapus kegagalan katalog SQL keseluruhan source dan bukan persetujuan keamanan Control.

Build fixture final: 1182 file terkemas, 342 dikecualikan; 306 tabel diperiksa, 293 tabel non-reference kosong, 735 record seed sistem, 0 data sample/customer/secret ditemukan, tepat 20 migrasi diterapkan. Backup/restore mempunyai logical checksum sama dan health lulus. `RELEASE-MANIFEST.json` **fixture sintetis** berhash `906045e9a124c69f9033f278f1a10f1f3115a73f3a9c36fa4afa86b0bd726853`; bukan manifest release resmi atau cutoff Control. Artefak sementara dibersihkan oleh harness, tidak dipublish/disalin ke instalasi customer. Control tetap harus membangun bukti baru dari cutoff Git Finance yang direview.

### Hash dan dependensi untuk pin Control

| Berkas | SHA-256 |
|---|---|
| `app-manifest.json` | `9ce9a38f775fcbffde17fd79a63cbe9b373ec1f03d68c0809695e899e048f1c6` |
| `tools/release/customer_clean_profile.json` | `fe34c057f99845943d61ab2a9180563cc803c2a0b0bc2e646aac70ed5427a2ae` |
| `tools/build/CustomerBuild.php` | `472aba88bc77d25f42da23fa15cd9031e9a1b9a4bdd0f86b97cda45839fd7d59` |
| `tools/build/verify_control_build.php` | `9dfa593caedf80d23cabb66afe378941daf5bb492ac8751272ed367add32b14e` |
| `tools/release/CustomerReleaseProfile.php` | `8a0b2067d875b53a360aad4f40f6c0299ab8714cf8e5782c60fd7507ada32a95` |
| `tools/release/ControlReleaseBridge.php` | `e96f6d8b83e260bb9d295e3a3e86802f777b5458b32a697c5fd8538d9b541e25` |
| `tools/release/build_release_artifact.php` | `c238177c9212eca877812715b89141afeb22ab985b75d3d14192acbc3c6f3815` |
| `tools/install/portable/PortablePackage.php` | `7c8901964ba8ff87dddbcfad402bc77a3698e9fe52d62985edc56258b733717d` |
| `tools/install/ControlDelivery.php` | `68615efb2393f1652c1bbf1362d344fa0b824fcb3ddd0def3c3a9e082548c254` |
| `application/config/feature_access.php` | `55a85a67d9a3e990cde74dd090ac94b9fb737ea2aab054f90e12f386bd9f8e5b` |
| `application/libraries/Feature_policy.php` | `625391136c6e9f42274e63c83d4585ba3c1d85da31862d8ffb2f641cff6417df` |
| `tools/tests/feature_boundary_contract_smoke.php` | `5a2be80e6606af2e0ce491467baf79020d2f0bd8faa08fb3589d45f02b6ff984` |
| `tools/tests/feature_boundary_http_cases.php` | `f92e2aedbe044082bab823fae278c7cb5ac38476147e1bb2af3909cb128ec815` |
| `tools/tests/feature_boundary_browser.cjs` | `9e1e33620d22e074677af18a393c0ec27afb1a26a1b67115ef0632c89a8fdbaa` |
| `tools/static/ci3-stubs.php` | `d7db81fd8b4aaea8f6b96eec50917c918ff9ad87692bc0db6a054f6f48e2e60d` |
| `composer.lock` (tidak berubah) | `4824bb030f2aa1aa3bd7d5170e1342202332057a173a4da33c3c010e41540636` |

Runtime uji: PHP CLI/FPM 8.1.32, MariaDB **10.11.10** pada socket sementara (bukan client distro 10.6), nginx 1.30.4, Node 20, Chrome 152.0.7977.82. Builder adapter memakai PHP 8.4 lalu gate PHP 8.1, PHPStan/OSV offline sesuai tooling proyek yang ada; tidak memasang/mengganti layanan global. Hash di atas bukan hash TAR/ZIP atau `RELEASE-MANIFEST.json` release baru: artefak resmi belum dibangun/ditandatangani/publish di thread ini.

### Langkah thread Control

1. Review diff cutoff terhadap base alpha.20, matriks kemampuan dasar dan gap UAT; perbarui pin toolchain yang relevan dari commit yang sama.
2. Terima profil v9 dan `FINANCE_FEATURE_BOUNDARY_V1` secara eksplisit; pertahankan entitlement boolean bertipe benar, signature, domain sebagai metadata, kuota server dan izin pemasangan tanpa tenggat.
3. Jalankan ulang kontrak + acceptance dengan license issuer staging Control yang sah, selain fixture sintetis. Selesaikan gate global katalog/CSP dan UAT lanjutan yang belum lulus/tercakup sebelum persetujuan release; delapan gate builder telah lulus pada fixture tetapi harus menghasilkan bukti build cutoff resmi sendiri.
4. Bangun **release baru alpha.21**, CUSTOMER_CLEAN v9 / sample_data NONE, dari cutoff bersih yang direview melalui alur build Control biasa. Jangan mengubah alpha.20 PUBLISHED atau menyalin bukti fixture menjadi approval produksi.
5. Untuk core2 aktif, tutup gap updater atomik dan uji rollback/identity/kuota terlebih dahulu. Upgrade entitlement pada kode baru tidak memerlukan database ulang; pemasangan kode baru pada alpha.20 memerlukan update lane tersebut.

Temuan kelengkapan paket yang terpisah dari pembatas: `Finance_accounting.php` dan `User_guide.php` ada di source tetapi tidak masuk allowlist customer v8. Patch tidak diam-diam memasukkan modul/SQL jurnal yang belum terdaftar clean-install. Starter tetap mendapat panduan paket statis dan halaman upgrade baru. Kelengkapan jurnal/guide untuk paket penuh harus direview sebagai batch packaging tersendiri; jangan menyebut seluruh modul Enterprise sudah lulus end-to-end hanya berdasarkan tes hak akses ini.
