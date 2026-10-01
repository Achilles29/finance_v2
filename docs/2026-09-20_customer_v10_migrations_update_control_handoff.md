# Handoff Finance alpha.22 / CUSTOMER_CLEAN v10 — migrasi, modul, batas fitur, dan gap update

Tanggal: 20 September 2026. Menggantikan keputusan kesiapan paket pada handoff 19 September, bukan mengubah bukti historisnya.

## Status dan batas

- Kandidat source: `0.1.0-alpha.22`, `CUSTOMER_CLEAN` **10**. Belum release resmi, belum dipublikasikan, belum diunduh customer.
- Basis pekerjaan: `978086c028c0f82076d3d916148fb03fbfac86f0`. **Cutoff kode final: `92b5672b55bcff4ddcef64e53ad04c3b37755769`**, commit lokal; hash dan hasil uji di bagian akhir.
- Tidak mengakses core2; lokasinya server lain. Tidak mengubah source/database Control, database development/produksi, credential, konfigurasi aktif, transaksi, saldo atau stok. Tidak push/deploy/aktivasi live.
- Seluruh database pengujian dibuat dalam datadir MariaDB baru, `--no-defaults --skip-networking`, lalu dibersihkan hanya oleh harness pemilik fixture tersebut.
- **Jalur update aplikasi aktif BELUM siap.** Patch menyediakan verifikasi otorisasi dan preflight read-only; bukan pergantian kode atomik. Jangan menjalankan clean installer pada instalasi alpha.20 atau menimpa seluruh foldernya.
- Izin pemasangan tetap `UNTIL_USED_OR_REVOKED` untuk v8/v9/v10; tidak ada tenggat mulai memasang berdasarkan pembelian/build/draft. Hak komersial tetap entitlement dan kuota server; bukan domain atau jumlah pengguna.

## Keputusan lima SQL

Kelima file SQL lama **tidak diubah satu byte pun**. Komentar historis “BELUM DIJALANKAN/PREPARED ONLY” bukan status katalog terbaru. Laporan pernah dijalankan manual tidak digunakan untuk membuat ledger tanpa verifikasi.

| SQL | Urutan / klasifikasi | Kebijakan dan dependensi | Isi yang boleh masuk paket |
|---|---|---|---|
| `2026-09-14c_finance_allocation_bank_review.sql` | 2026091403 / schema | clean_install + upgrade; 14b, transitif 14a/13a | Empat tabel kosong, kolom lawan transfer dan ENUM rekonsiliasi. Tidak ada alokasi atau mutasi customer. |
| `2026-09-15a_finance_general_ledger.sql` | 2026091501 / schema dengan referensi generik | clean_install + upgrade; 14c dan sidebar 06i; tabel kas/period-close/audit harus ada | Guard tunggal, 24 akun generik, tabel jurnal/line **kosong**, menu/page/grant minimal. Tidak ada saldo awal/posting historis. |
| `2026-09-15b_finance_journal_assistant.sql` | 2026091502 / schema | clean_install + upgrade; 15a | Tabel mapping **kosong**, page/grant pengaturan. Mapping customer tidak menjadi seed. |
| `2026-09-15c_application_user_guide.sql` | 2026091503 / seed metadata | clean_install + upgrade; sidebar 06i dan registry/RBAC | Dua page, satu menu, grant SUPERADMIN view-only. Tidak menambah user atau hak role lain. |
| `2026-09-16a_procurement_stock_review.sql` | 2026091601 / schema | clean_install + upgrade; migration registry dan `pur_division_request` dengan PK kompatibel | Tabel bukti pemeriksaan **kosong**. Tidak membuat konfirmasi fiktif untuk dokumen lama. |

SHA-256 immutable:

```text
14c ea623937ac6456b9643d4216768744c9c4f3e45c59369d238b37f21dd7d5657d
15a 03aeae127ea8431480f1553c43e5a8e2d581a453279c2ecf2dddbbb560b4c195
15b 133f130b0c7286318923feacfc032e6c65eabb340eb82d510d7859b5cf5b238e
15c a3b68fb81101fdc7fd05a60c02c283100f17fcc677aedaed64595ba122657393
16a c09edc815f92d8d8628a29156790e9f74458a3203fef0c1670f5aaa3ef278665
```

### Koreksi tambahan yang ditemukan oleh uji Starter nyata

Baseline lama belum mengizinkan `pos_order.stock_commit_status=NOT_REQUIRED`, padahal kode sudah memakai status itu untuk produk tanpa resep. Confirm memberi respons berhasil tetapi void kemudian gagal “Snapshot stock commit belum tersedia”. Unit test yang memasok header sintetis NOT_REQUIRED tidak menangkap ketidakcocokan schema tersebut.

- Baru: `sql/2026-09-20a_pos_stock_commit_not_required.sql`, urutan 2026092001, schema, clean_install + upgrade, dependensi 06c + prasyarat `pos_order`.
- SHA-256: `43fc95b64cfca96e271c22e0fa08a7eb0f084a0defd4c7f44505fb0c1d52946d`.
- Menambah nilai ENUM yang sudah digunakan kode; **tidak mengubah perhitungan/aturan void/refund, tidak mengisi ulang transaksi**, tidak mengubah baseline SQL lama.
- Kolom tidak sesuai kontrak atau status kosong/NULL lama → `MIGRATION_SCHEMA_DRIFT` / `MIGRATION_DATA_REVIEW_REQUIRED:pos_order.stock_commit_status`; perlu peninjauan, bukan menebak riwayat atau memperbaiki data otomatis.
- Tidak ada down migration otomatis: menghapus nilai ENUM yang sudah dipakai dapat merusak data.

### Eksekusi dan adopsi manual

`tools/db/ManagedMigrationProof.php` + `managed_migration_proofs.json` digunakan runner CLI serta installer PDO. Bukti struktur berasal dari MariaDB 10.11 disposable yang menjalankan SQL immutable, **bukan schema atau data development**.

1. Pemeriksaan prasyarat, primary key, kelengkapan objek, seluruh kolom/default/generated, indeks, foreign key/rule, engine/collation, trigger, referensi menu/page/izin dilakukan sebelum adopsi. Untuk 14c diperiksa pula empat kolom rekonsiliasi yang diubah.
2. Semua objek belum ada → jalankan SQL terdaftar lalu verifikasi postcondition sebelum ledger.
3. Semua objek sudah lengkap dan kompatibel → adopsi tanpa menjalankan ulang DDL/DML; runner mencatat `applied_by=verified_manual_adoption`. Nama akun, urutan/label menu dan grant yang sengaja diubah customer dipertahankan, bukan direset.
4. Struktur parsial/berbeda, menu/permission tidak lengkap, prasyarat hilang → hentikan dengan identitas pemeriksaan yang gagal. Tidak menghapus tabel, tidak menandai sukses palsu.
5. Ledger ada tetapi hash/path/version atau struktur tidak cocok → fail closed; bukan menjalankan SQL ulang.
6. Postcheck SELECT/SHOW di akhir 15c/16a tidak dikirim sebagai output bebas ke protokol client streaming. Hanya bagian postcheck dua SQL terpin tersebut dipisahkan; verifikasi schema/metadata terstruktur menggantikannya. File dan checksum aslinya tetap. PDO tetap menguras resultset SQL dengan benar.

Katalog final: **26 clean-install**, **25 upgrade**; reference seed navigasi hanya clean-install. Tujuh SQL legacy tetap tidak dieksekusi otomatis; lima SQL ini tidak dipindahkan ke daftar pengecualian. Total hasil fresh schema diharapkan 316 tabel (baseline 296 + perubahan terdaftar), bukan impor seluruh folder SQL.

DDL bukan transaksi atomik. Jika proses terputus saat DDL tidak pasti, journal dipertahankan dan installer berhenti untuk review. Uji replay/adopsi bukan klaim rollback DDL aman.

## Kelengkapan paket dan dependensi

Profil v10 menyertakan seluruh rantai berikut:

- `Finance_accounting.php` → `Finance_accounting_model.php` → `Finance_journal_policy.php`, `Finance_journal_assistant.php`, `Finance_accounting_setup.php`, dependensi mutasi yang sudah dipaketkan; view `finance/accounting*.php`, `assets/js/finance-accounting.js`, route existing accounting, SQL 15a/15b, page/menu/grant.
- `User_guide.php` → `Finance_user_guide.php`, katalog umum dan `Finance_customer_guide_catalog.php`, view `system/user_guide.php`, `assets/js/finance-user-guide.js`, `assets/css/finance-user-guide.css`, route `/guide`, SQL 15c dan dua hak view terpisah.
- Guide customer mengganti empat bab server legacy dengan alur ZIP satu-folder, `public/`, satu perintah persiapan, UI `/setup`, `config/customer.json`, jadwal terpisah dan peringatan update belum tersedia. Master/development tetap memakai bab legacy; pembatasan guide server tetap mengikuti RBAC.
- Procurement stock-review dan alokasi bank yang sebelumnya sudah termasuk kode sekarang mempunyai schema terdaftar.
- Runtime critical inventory v10 menambah katalog, proof schema, runner dan PDO executor; kehilangan/perubahan komponen ini tidak membuka paket.
- Build data gate hanya membolehkan guard id=1 dan **24 tuple akun generik tepat**, bukan semua isi chart of accounts. Seluruh jurnal, mapping, alokasi, bank statement dan bukti stok harus kosong. Tidak ada backup, foto usaha, konfigurasi aktif, private key atau data development yang ditambahkan ke allowlist.

Jurnal tetap membutuhkan pemetaan dan pencatatan yang ditinjau pengelola; tidak ada posting historis otomatis. Modul tersedia tidak berarti pembukuan semua customer langsung lengkap.

## Matriks batas paket dan dependensi Starter

| Fitur | Entitlement | Kemampuan dasar / jalur yang tetap tersedia | Tetap terkunci tanpa hak |
|---|---|---|---|
| Katalog & kasir web | POS_WEB | Master produk/harga/satuan, pegawai dasar untuk kasir, outlet/terminal, metode bayar/rekening dasar; draft/confirm/payment/void/refund dan step-up | Editor HPP/resep, stok/rekening administratif, HR lanjutan, payload saldo/HPP palsu |
| Konsumsi resep/lot internal | POS_WEB (bagian transaksi sah) | Resolver resep yang sudah ada, snapshot, queue, pengurangan lot dan pembalikan melalui POS; bukan membuka layar inventory | Inventory/reconcile/produksi dan repair langsung |
| Cetak transaksi | POS_PRINTER + dependensi manifest | Target struk, payload printer, dokumen transaksi | Fitur printer/APK lain yang tidak diberi entitlement |
| Laporan penjualan | SALES_REPORTING + dependensi manifest | Laporan transaksi/penjualan kasir | Finance Advanced, Payroll, laporan modul berbayar lain |
| Jurnal/akuntansi | FINANCE_ADVANCED | Index dengan tab queue/journal/ledger/trial/profit/balance/guide/settings, POST/save_setup hanya dengan RBAC | Starter ditolak di server, termasuk alias dan POST |
| Panduan / pemulihan | Pengecualian sempit existing | Guide sesuai RBAC; login/logout, informasi lisensi, sinkronisasi dan heartbeat | Pengecualian bukan wildcard akses bisnis |

Sumber pemetaan tetap `application/config/feature_access.php`, keputusan `Feature_policy.php`. Tidak ada entitlement baru di Control dan tidak ada pembukaan Enterprise paksa. Menu berbayar mengikuti RBAC, tetap tampak dengan SVG gembok; halaman terkunci memakai shell/sidebar; API/aksi menolak sebelum data dengan 403.

## Update aplikasi aktif: implementasi yang ada dan yang belum

**Upgrade entitlement**: dokumen lisensi valid tersinkron; keputusan/sidebar berubah tanpa logout, kehilangan data atau aktivasi ulang. Ini bukan update kode.

**Update kode**: installer `complete.json` tetap mengunci setup. `FinanceInstance` lama menggunakan instance/database salinan dan bukan updater atomik single-folder. Launcher alpha.20 belum mempunyai maintenance/switch capability untuk update aman.

Implementasi baru yang dapat direview:

- `tools/update/UpdateAuthorization.php`: verifier Ed25519 **usulan** `FINANCE_CUSTOMER_UPDATE_V1`, domain separator tersendiri; binding instance/installation/public-key/fingerprint, release asal/tujuan (versi, commit, artifact, manifest, profil), nonce, authorization ID, waktu credential dan policy **upgrade saja**. Tolak signature salah, trust dicabut, binding salah, clean_install, versi mundur dan credential kedaluwarsa.
- `tools/update/UpdatePreflight.php`: read-only, CLI companion, memverifikasi kedua paket melalui verifier terpasang (bukan menjalankan PHP candidate), trust yang sama, integritas current dan lisensi aktif/grace; menghasilkan perbedaan inventory serta kandidat migrasi upgrade. Tidak menerima credential DB, tidak mengakses DB, tidak menulis/switch/aktivasi/mengeksekusi SQL.
- Hasil selalu `BLOCKED_SWITCH_AND_CONTROL_CONTRACT_REQUIRED`. Tidak ada tombol update palsu, endpoint Control rekaan, receipt sukses rekaan, atau fitur mutasi tersembunyi.
- Verifier kontrak diuji dengan issuer sintetis. **Preflight end-to-end dua release resmi, staging/switch atomik, recovery update, rollback kode+schema, replay nonce persisten dan UI update belum tersedia/teruji.** Jangan menganggap unit verifier sebagai bukti jalur update siap.

### Kontrak yang perlu disepakati dan diimplementasikan Control

1. Review proposal otorisasi update di atas, trust/purpose/key rotation serta endpoint/format pengiriman. Tidak ada endpoint baru yang diasumsikan sudah ada. Validasi lisensi, pencabutan dan maintenance mengikuti keputusan Control, bukan diperpanjang Finance.
2. Bind approval pada installation ID dan kunci agent yang **sudah ada**, cutoff/manifest/profil asal serta tujuan; update tidak memanggil aktivasi baru atau menggunakan slot kuota baru. Domain tetap metadata opsional.
3. Penggantian credential update yang kedaluwarsa mengikat attempt/journal yang sama. Pertahankan semua bukti lama dan watermark; jangan ulang baseline/DDL atau mengganti identitas. Masa credential update tidak menjadi deadline instalasi customer.
4. Sepakati bootstrap maintenance/atomic-switch untuk launcher lama; instalasi aktif harus dikuiesce, request/worker bisnis dihentikan sementara dan backup terverifikasi. Pisahkan agent/web/non-root worker; jangan memberi website sudo/root.
5. Staging kode terverifikasi + pergantian pointer/konteks secara aman sambil menjaga config, DB, upload, private key/agent, cache lisensi dan journal. Migrasi hanya kandidat belum terledger setelah verifikasi schema; partial state memblokir otomatisasi.
6. Recovery wajib membedakan SQL belum dimulai, DDL selesai terverifikasi, DDL tak pasti, kode belum/sudah beralih dan transaksi baru. Restore backup hanya sesuai prosedur quiesced yang diuji, bukan DROP/down migration otomatis.
7. Receipt UPDATE terautentikasi/idempotent mengirim hasil health nyata (tanpa data bisnis/secret); sinkronisasi lisensi dan heartbeat tetap proses berbeda.
8. Uji pada salinan terisolasi instalasi lama, termasuk invalid/revoked/license quota, interruption, rollback dan terjaganya config/identity/data; setelah itu saja tawarkan UI update customer. **Core2 belum disentuh dan tidak boleh menjadi tempat percobaan pertama.**

## Review/build oleh Control

- Terima **v10 secara eksplisit** dengan `managed_migration_contract=FINANCE_MANAGED_ADOPTION_V1`; tetap feature boundary V1, single-folder V1, guided setup V1, local config V1, UNTIL_USED_OR_REVOKED.
- Gunakan source/cutoff, app-manifest, profil dan seluruh dependensi builder/validator dari commit yang sama. Jangan menyisipkan file ke TAR/ZIP yang sudah ditandatangani, jangan mengubah alpha.20/21 lama.
- Review allowlist lengkap (jurnal/guide/proof/update-preflight), enam SQL tambahan, reference gate dan critical inventory baru. Pin ulang hash adapter, validator, profil dan dependensinya, bukan hanya dua controller.
- Build resmi tetap tanggung jawab Control: review source → approval profil/cutoff → build dengan seluruh gate → review evidence → publish baru → deployment/unduhan. Dokumen ini bukan approval keamanan/publikasi.
- Dependencies: PHP CLI/FPM 8.1.x (sodium, mysqli/PDO_mysql, curl, mbstring, DOM, zip), adapter PHP 8.4 sesuai harness, MariaDB **10.11.x**, Git/GNU tar, Node/Chrome untuk browser; Composer/lock dan validator yang dipin repository. Testing Windows host nyata tidak dijalankan.

## Panduan singkat customer

“Menu bergembok → lihat informasi upgrade → hubungi penjual → setelah upgrade disetujui dan lisensi tersinkron, fitur terbuka sesuai hak akun.”

Upgrade paket tidak menginstal versi aplikasi baru. Untuk aplikasi lama, tunggu jalur update resmi; jangan menimpa folder atau mengulang setup/database.

## Bukti pengujian dan cutoff final

Hasil eksekusi pada Linux disposable, PHP 8.1.32, MariaDB 10.11.10 (adapter diuji lintas PHP 8.4.21 → PHP 8.1), Node 20.20.2:

| Pemeriksaan | Hasil |
|---|---|
| Katalog + kebijakan + checksum + dependensi | PASS; 26 managed clean / 25 upgrade / 7 legacy. Unit runner 48 pemeriksaan termasuk schema enum dan penolakan status historis kosong. |
| MariaDB fresh → old-version upgrade → replay → adopsi manual | PASS; enam migrasi baru diterapkan berurutan; replay 0 perubahan; checksum semua tabel dipertahankan; adopsi hanya mengubah ledger. |
| Schema parsial / drift / referensi customer | PASS; drift dan partial ditolak sebelum false adoption; nama akun dan RBAC customer tidak direset. |
| HTTP Starter / Full / installer | PASS **181**; Starter transaksi/pembayaran/struk/laporan/tutup kasir, tanpa resep dan dengan resep, VOID/REFUND; lot/agregat/nilai/kas kembali tepat; Full jurnal/guide dapat dibuka, downgrade mengunci lagi; 9 file core dimodifikasi ditolak; private HTTP, recovery installer dan pemisahan akun tetap lolos. |
| Kontrak feature boundary | PASS 2142 pemeriksaan; entitlement boolean ketat, dependency, route/alias, RBAC bukan bypass lisensi. |
| Izin mulai pemasangan tanpa tenggat | PASS 33 pemeriksaan; profil 8/9/10, unknown 11 ditolak. |
| Usulan otorisasi update | PASS 34; issuer sintetis, signature/binding/expiry/issuer dicabut/nonce/purpose/versi mundur; bukan bukti update aplikasi. |
| Panduan aplikasi / adapter reference gate | PASS 402 / 61; halaman customer berbeda dari instruksi master; COA customer ditolak dari clean seed. |
| Build customer terisolasi, semua 8 gate dan trusted validator | PASS; 1.208 file, 316 tabel, 26 migrasi diterapkan, 301 tabel non-reference kosong, 776 record sistem, 0 dummy/data customer/secret; dump/restore seluruh tabel dan health cocok. |
| Quality gate global release | PASS **135/135**, exit 0: required 125, development 4, release 1, runtime 2, preflight 1, security 1, static 1. Staging probe sengaja tidak dipilih karena tidak mengakses database aktif. UAT manual perangkat/role tetap terpisah. |
| PHP lint + diff whitespace | PASS seluruh 40 file PHP berubah; lima SQL lama dan baseline identik dengan HEAD basis. |
| Composer validate strict (non-root) | PASS exit 0; Composer lama memunculkan deprecation/cache-writability warning, tidak mengubah dependency. |

Perintah untuk mengulang **hanya di lingkungan pengujian dengan fixture disposable**, bukan target customer:

```sh
/www/server/php/81/bin/php tools/db/migration_runner.php validate
/www/server/php/81/bin/php tools/tests/managed_migration_database_smoke.php --disposable
/www/server/php/81/bin/php tools/tests/customer_portable_acceptance.php --disposable --feature-boundary
/www/server/php/81/bin/php tools/tests/c3_control_build_runtime_smoke.php --isolated
/www/server/php/81/bin/php tools/tests/finance_quality_gate.php release
```

Harness `--disposable/--isolated` perlu administrator **hanya untuk membuat sandbox dan memisahkan akun uji**, bukan memberi web root. Jalankan pengujian berat berurutan.

Riwayat kegagalan **tidak dihapus dari penilaian**: percobaan paralel sebelumnya terkena timeout client build, worker installer dan browser; satu global run juga gagal register roadmap yang belum memasukkan SQL korektif (sudah diperbaiki dan targeted test lulus). Regresi no-recipe awal benar-benar gagal sebelum migrasi 20a ditambahkan. Hasil akhir rerun di atas menentukan status, bukan percobaan awal yang gagal.

Regresi refund beresep tambahan sempat gagal `Stok komponen tidak cukup untuk movement USAGE`: fixture awal hanya membuat lot/saldo bulanan, tanpa dokumen dan movement OPENING. Reversal yang sah membangun ulang agregat dari riwayat (`Production_model::rebuild_component_history_for_identity`), sehingga saldo fixture itu tidak dapat dibuktikan. Fixture diperbaiki dengan header/line/opening movement yang lengkap; ditambah assertion quantity **dan nilai agregat**, bukan mengubah service bisnis, membolehkan stok negatif, atau memaksa job sukses. Penantian worker dibatasi 30 detik dan status FAILED langsung menggagalkan tes.

Batas bukti:
- Issuer/endpoint Control pada acceptance test **sintetis**; MariaDB, Linux permission, nginx, PHP-FPM, browser dan request aplikasi nyata. Bukan aktivasi/receipt/sync ke Control live.
- Belum menjalankan integrasi Control resmi, Windows nyata, APK/perangkat/printer fisik, UAT seluruh role, atau update/rollback aplikasi aktif.
- Uji dump/restore build adalah database baru tanpa transaksi customer, **bukan** bukti rollback DDL live.
- Tidak ada hasil probe database aktif; status SQL lama pada development tetap laporan historis pengguna, bukan diverifikasi ulang/dieksekusi ulang. SQL 20a **NOT_APPLIED_ACTIVE**.
- `RELEASE-MANIFEST.json`, TAR/ZIP resmi serta tanda tangan baru hanya ditentukan build Control dari cutoff yang disetujui. Hash di bawah adalah source/kontrak, bukan pengganti approval/manifest release resmi.
- Manifest **fixture build terisolasi**, bukan release resmi: `a77ca4787ba3cda3ef2950eecb3c4747d198a560d1ac62a0e3bf33b35a1e1d70`; dump fixture `d000e908da4d69fb4c2d3ddb99325aa7a7e96ebe18ac914a848ddfdb2c1c2fbc`. Harness selesai dengan `signed_or_published=false`, `source_database_accessed=false`, kemudian membersihkan fixture miliknya sendiri.

### Hash source/toolchain untuk review

Semua SHA-256 berikut harus cocok dengan source cutoff; pin seluruh dependency pada commit yang sama, bukan menyalin satu wrapper validator.

```text
c50b30218a48a18eb7b5432767457c592d47b736039e9633ddef843e96eeab65  app-manifest.json
b7a1a7703253dc1dd2149437a36aa0000438ae06a6fd7021c5553b419b7944df  tools/release/customer_clean_profile.json
6d20cfcc5c45305d7495bbb16d420cfd23567f5e65f49c1c6b607e7067dd6329  tools/build/customer_package.php
472aba88bc77d25f42da23fa15cd9031e9a1b9a4bdd0f86b97cda45839fd7d59  tools/build/CustomerBuild.php
f185b055007861bbb85549f8da9d0ecdfb449929f828b29778e6f826f3357f70  tools/build/DisposableBuildDatabase.php
9dfa593caedf80d23cabb66afe378941daf5bb492ac8751272ed367add32b14e  tools/build/verify_control_build.php
e96f6d8b83e260bb9d295e3a3e86802f777b5458b32a697c5fd8538d9b541e25  tools/release/ControlReleaseBridge.php
2ce61b14cd9e4aa7d4efec9bed640e471c3a6a2899c2252d1595d9e6bac64923  tools/release/CustomerReleaseProfile.php
bf1f3508c6531c2a5f3c579f0c4274f161e764161941b2855eadb8e23c34780a  tools/release/CustomerLayout.php
64c2d2c1d7779f7fe1c50391d9306ff86fe9621d9d1cf1bd5f95201cb255ac68  tools/release/ReleasePackagePolicy.php
c238177c9212eca877812715b89141afeb22ab985b75d3d14192acbc3c6f3815  tools/release/build_release_artifact.php
b3c67b5273e444f95e1e4bf1ee7a2f3f6b15f369a5f578976872ffe23f9f4cb0  tools/release/package_policy.json
ed57b74576a203b589cc3a2c6dac006a862f519da41ea2660add96350421a929  tools/release/runtime_compatibility.json
075eaeec36c888fd770034d945c9c271e1c97b6e4e04922f83e1c4cdf2ffd208  tools/db/migration_catalog.json
edaaf6f3c4b21d8d2f48b16ffbd3db392d69fa7463dd137b8f4f41ba8d177c81  tools/db/migration_runner.php
faa92855868a448b82226f7230b0badd078efc79580adee07de85d3183524c49  tools/db/ManagedMigrationProof.php
559f9a45c623ac9d2a39a1d604833001a0903eb44809eed218f551aadbe54667  tools/db/managed_migration_proofs.json
10b356b169f75aa3c4d7949ba62ab806dd019c43d39af24557413b43618d3458  tools/db/clean_install_baseline_policy.json
3e66ada8adbfad56e8be804b419213edb0d73fc622966a8abf2568e3abdcb66e  tools/db/post_install_health_check.php
ed810501beb9ffd1d11e96c9974725a157f762f7768a9e2d5fa685c5058e0c2d  tools/install/portable/PortableDatabase.php
4775c8e2de0d8954b4563f433709af49403f298e2c4a72a2e677249aa13b880f  tools/update/UpdateAuthorization.php
547ad04a11e47af111d0807875a8938aadf33ab5b1964e778ac05470ae925924  tools/update/UpdatePreflight.php
1b0facdbbf2056a4be3f27ac03a7268d2eeed8fe0c72af3f4f79edec1dc19f2a  application/libraries/Control_license_cache.php
4824bb030f2aa1aa3bd7d5170e1342202332057a173a4da33c3c010e41540636  composer.lock
11e8744761043d2c011cbdea2706d703a8c54df0945af061058775add9bc81b4  tools/static/toolchain.lock.json
a6418adfd2e86061434d2c8b89aa084f483e9e948f0acaaf78a0b1417666e076  tools/security/toolchain.lock.json
```

### Cutoff

- **Kode final alpha.22 / v10:** `92b5672b55bcff4ddcef64e53ad04c3b37755769` — 51 file berubah/tambah; lima SQL lama dan baseline tidak berubah. Gunakan cutoff ini untuk review toolchain/source, bukan HEAD alpha.21 sebelumnya.
- Dokumen bukti final ini berada pada commit dokumentasi terpisah sesudah cutoff kode. Perbedaan commit dokumentasi tidak mengubah manifest, profil, SQL atau kode paket. Daftar perubahan lengkap tersedia melalui `git diff --name-status 978086c028c0f82076d3d916148fb03fbfac86f0 92b5672b55bcff4ddcef64e53ad04c3b37755769`.
- **Tidak di-push.** Tidak ada publish, deployment, perubahan Control maupun core2. Control tetap harus mereview cutoff/profil v10 dan membangun/menyetujui release resmi; fixture build tidak untuk dikirim ke customer.
- Hasil akhir: katalog, migrasi disposable, kelengkapan paket, regresi Starter dan seluruh gate lokal lulus. **Update aplikasi aktif tetap BLOCKED**, sampai kontrak Control, adapter launcher lama, staging/switch, journal/recovery, rollback teruji dan UI update diselesaikan. Jangan menawarkan reinstall atau overwrite folder sebagai pengganti.
