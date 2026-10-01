# Handoff perbaikan audit CUSTOMER_CLEAN Control

Batch 266, 16 September 2026. Referensi: audit Control `2026-09-16_audit_finance_customer_clean_v3.md`, cutoff lama `db76e809acc67b293a7fb1844dcd91c08b9172be`. Perubahan hanya di Finance; tidak mengubah persetujuan cutoff, source, database, signer, atau katalog live Control.

**Hasil penutup: ISOLATED_BUILD_PASS / CONTROL_REVIEW_REQUIRED / NOT_RELEASED.** Build penuh terakhir exit 0, seluruh delapan gate dan validator independen PASS. Ini bukan persetujuan penjualan/publish, dan bukan penutupan seluruh bug bisnis atau lima SQL/modul development yang belum didistribusikan.

## Hasil dan batas cakupan

- Tiga blocker `HARDCODED_DB_CREDENTIAL` telah direview: akun aplikasi sintetis ber-hash password non-login, serta koneksi root tanpa password pada socket MariaDB disposable yang baru dibuat dengan networking dimatikan. Router tes dibatasi localhost, socket temporary dan nonce. Bukan credential deployment/produksi.
- Tambah tiga exception **path + nomor baris + kategori + SHA-256**. File tetap dipindai dan tetap tidak boleh masuk paket customer. Matcher, ekstensi, scope, dan mekanisme fail-closed tidak dilonggarkan. Tidak mengubah baris fixture agar menyamarkan temuan.
- Pemeriksaan berikut menemukan `2026-09-12a_roast_connect_catalog.sql` sudah managed tetapi tidak ada di profil. Paket sekarang membawa SQL immutable itu beserta dua controller, model, view dan JS konektornya. Tidak mengubah isi/checksum SQL maupun data staging.
- Profil baru **v4**, source **0.1.0-alpha.13**; tidak menyatakan identitas profil v3/alpha.12 lama membawa perubahan baru. Audience tetap CUSTOMER, sample NONE, REFERENCE_ONLY, demo false. Pembaca Finance menerima v4 dengan binding hash; versi asing tetap ditolak. Control harus mereview v4 sendiri.
- Instalasi bersih mengenali dua baris setting yang dihasilkan SQL, bukan disalin dari staging: konektor nonaktif tanpa token/divisi dan kebijakan kontrol keuangan default. Pemeriksaan mengecek isi tepat, jumlah baris dan ketiadaan token; bukan pengecualian seluruh tabel tanpa validasi.
- Expected seed setelah 20 migrasi: 213 page, 251 menu, 213 role permission. Pemeriksaan hak SUPERADMIN mengikuti hak minimum yang memang dibuat migrasi (termasuk kontrol keuangan/Roast Connect), bukan memaksa tambahan create/delete/export. Tidak mengubah grants aplikasi.
- Cache OSV publik sebelumnya berusia lebih dari 48 jam sehingga gate menolak. Di-refresh melalui bootstrap resmi repository; batas usia, checksum dan tool binary tidak diubah. Akses baca `namua-build` pada empat file cache yang diganti dipulihkan seperti semula. Tidak membaca DB Finance atau mengirim source/dependency proyek ke layanan luar; unduhan hanya database kerentanan publik.
- Gate statis menemukan satu variabel `$month` belum didefinisikan eksplisit sebelum ditangkap closure pada view jurnal. Tambahkan fallback bulan saat ini tanpa mengganti bulan dari controller; tidak mengubah perhitungan atau transaksi jurnal. Regresi render memastikan bulan pilihan tetap dan payload tanpa bulan tidak memberi warning.

## Validasi

| Pengujian | Hasil |
| --- | --- |
| Preflight source | PASS, 0 finding (1.741 kandidat pada putaran awal perbaikan) |
| Contract exception/preflight | 39 PASS: path/baris/kategori/content/hash berbeda ditolak, wildcard invalid, fixture tetap dipindai dan di luar paket |
| OSV offline aktual | PASS, 3 sumber / 145 package / 0 advisory pada snapshot yang di-refresh |
| OSV contract | 15 PASS, termasuk cache stale/hash salah dan laporan advisory |
| Adapter/default setting/izin | 59 PASS; predicate SQL aktual dengan SQLite memory, perubahan setiap field default/token/izin serta jumlah baris ditolak |
| Customer content/TAR/signature/plan | 108 PASS, fixture deterministik dan key ephemeral; bukan penerbitan release |
| Bridge Finance–Control | 29 PASS |
| Artifact builder contract | 12 PASS; gate fixture bukan bukti scan aktual |
| First owner | 14 PASS |
| Post-install health contract | 17 PASS; fixture release hanya memuat SQL katalog yang dinyatakan, bukan draft SQL development |
| Regresi mutasi laporan | 72 PASS SQLite memory |
| Regresi jurnal dan client | 348 PASS SQLite memory, 38 PASS DOM sintetis |
| Analisis statis PHP aktual | PASS, scope seluruh application, baseline 0; tidak menambah ignore/baseline |
| Static policy contract / baseline guard | 20 + 20 PASS; scope, baseline nol, seed tanpa customer dan credential tetap dijaga |
| Roadmap consistency | 26 PASS, 45 register master / 32 SQL source |
| PHP lint | 11 file PHP berubah PASS |
| Clean-install + backup/restore MariaDB disposable | PASS: 306 tabel, 293 tabel nonreferensi kosong, 735 baris referensi/default, 0 data customer/demo, 20 migrasi; health dan checksum logis restore cocok |
| Inspeksi TAR komponen database | PASS: 1.135 file, 319 aset statis, 21 SQL (baseline + 20 managed); bukan bukti seluruh build gate PASS |
| Build penuh terisolasi + validator independen | **PASS**, exit 0, delapan gate: source_clean, security_scan, install_test, backup_restore, customer_data_scan, secrets_scan, clean_install, source_untouched |

Riwayat kegagalan saat pengerjaan tidak dihapus: setelah preflight diperbaiki, build pertama berhenti pada cache OSV stale; uji content menemukan MIGRATION_CHECKSUM; uji DB sebelum pengecekan safe-default diperbarui menolak CUSTOMER_DATA_FOUND. Ketiganya telah diperbaiki dan diuji ulang. Putaran berikut membuka blocker STATIC (`accounting.php:5`, undefined month), diperbaiki tanpa ignore, lalu static aktual PASS. Semua gate tetap aktif.

Suite runtime menyalin source terpilih ke checkout terisolasi dan membuat commit fixture sendiri. Artefak tersebut bukan release yang boleh dipublikasikan, bukan receipt untuk commit Finance final, dan dibersihkan setelah tes. Thread Control tetap wajib menguji ulang **commit final yang persis** sebelum memberi persetujuan cutoff; tidak cukup menyalin label PASS dari laporan ini.

Pada putaran akhir, 35 file (27 pin verifier + runtime/tes yang berubah) dibandingkan hash-nya dengan workspace Finance: **0 mismatch**. Dokumen laporan tidak menjadi isi paket customer. Bukti ini mengikat byte yang diuji, bukan menggantikan pemeriksaan commit final oleh Control.

Command developer: `/www/server/php/81/bin/php tools/tests/c3_control_build_runtime_smoke.php --isolated`. Log lokal putaran akhir: `/tmp/finance-alpha13-full-build.log`. Metrics akhir: 1.135 file termasuk, 324 file dikecualikan, 735 reference/default, 0 generic sample/customer/secret findings. Database sumber tidak dibuka; `signed_or_published=false`. Workspace, TAR dan DB sementara dibersihkan oleh tes, log ringkas tetap tersedia. Tidak menghapus backup atau data pengguna.

## Batas yang tidak ditutup batch ini

- Lima SQL development `14c/15a/15b/15c/16a` belum managed/allowlisted. Suite katalog pada root development masih gagal untuk SQL belum terdaftar; tidak dipindahkan ke legacy atau dibuat otomatis lolos. Registrasi/integrasi modul ini perlu batch terpisah, tidak disamakan dengan katalog 20 migrasi yang dibawa kandidat ini.
- Bug bisnis terbuka (PR-01/02/03 pengajuan divisi, void batch setelah pengembalian stok, integrasi jurnal dan APK/UAT) tidak diperbaiki oleh perubahan packaging ini. Lulus build bukan pernyataan aplikasi tanpa bug atau izin publish.
- Tidak ada SQL yang harus dijalankan ke database Finance aktif untuk batch ini. DDL/data uji hanya di MariaDB disposable, dengan socket privat dan `--skip-networking`.
- Tidak push, publish, deploy, menghapus backup/upload/transaksi, mengubah credential, atau mengubah source Control. Tes membuat dan membersihkan file/DB sementara miliknya sendiri; tidak menghapus data pengguna.

## Identitas untuk review Control

Commit baru adalah commit Finance yang memuat laporan ini; hash commit final disampaikan pada handoff setelah commit (tidak memasukkan hash dirinya sendiri ke isi commit). Parent awal `db76e809acc67b293a7fb1844dcd91c08b9172be`.

| Field | Nilai |
| --- | --- |
| Version | `0.1.0-alpha.13` |
| Profile version | `4` |
| Manifest SHA-256 | `69be551da6385bc9492bf36358d0315d988a23b390353634f01400c89144259b` |
| Profile SHA-256 | `3ada4d1e0881dacf55d911016c8b3083831de50674f87fd57bda8db5c93452bc` |
| Validator SHA-256 | `9dfa593caedf80d23cabb66afe378941daf5bb492ac8751272ed367add32b14e` |
| Inventaris 27 dependensi SHA-256 | `3ae3ce335869df3ba701c5db701dd77c0aef503f379744c4f710b1f22f3ab86e` |
| Static lock SHA-256 | `11e8744761043d2c011cbdea2706d703a8c54df0945af061058775add9bc81b4` |
| Security lock SHA-256 | `a6418adfd2e86061434d2c8b89aa084f483e9e948f0acaaf78a0b1417666e076` |

Inventaris memakai daftar 27 path yang direview Control sebelumnya, dihitung dari byte Finance terbaru sebagai SHA-256 JSON path→hash diurutkan, `JSON_UNESCAPED_SLASHES`. Tujuh dependency berubah: manifest, DisposableBuildDatabase, CustomerReleaseProfile, profil, package_policy, post_install_health_check dan clean_install_baseline_policy. `ControlDelivery.php` juga berubah (menerima v4 dengan pemeriksaan binding), berada di runtime customer meskipun bukan anggota 27 pin verifier tersebut.

Hash per-file dari Finance (bukan patch untuk langsung mengaktifkan trust Control):

| Path | SHA-256 |
| --- | --- |
| `app-manifest.json` | `69be551da6385bc9492bf36358d0315d988a23b390353634f01400c89144259b` |
| `tools/build/CustomerBuild.php` | `565d9e5aa10ce2ec010d660a409c5f4144f5d845d0ebe2ea0b644bd463255240` |
| `tools/build/DisposableBuildDatabase.php` | `7cfe983f1765598406f877c855ec56f8d99da084576e45b6c273c7617fbc0456` |
| `tools/build/customer_package.php` | `6d20cfcc5c45305d7495bbb16d420cfd23567f5e65f49c1c6b607e7067dd6329` |
| `tools/build/verify_control_build.php` | `9dfa593caedf80d23cabb66afe378941daf5bb492ac8751272ed367add32b14e` |
| `tools/db/bootstrap_first_owner.php` | `e20a19e0d72206f9907187985ff2ebfe33a47c40a8eff779497c0d68ce80c8d5` |
| `tools/db/clean_install_baseline_guard.php` | `423e01ecc3a166a60df4ada24d6a6af78b62664a56c4c7cc66507192274ee10e` |
| `tools/db/clean_install_baseline_policy.json` | `db5db6b1b171a7dee16a78f3af0758e3314177cf40226319057cce596b6b8d06` |
| `tools/db/migration_catalog.json` | `119160980a2636411467edc7249adcbd17f311df6410e16380e8ac10fbe94c9e` |
| `tools/db/migration_runner.php` | `3d8ae021d3d81f04421b53c40d40441843ad31e198f08b6d9213201c5db44236` |
| `tools/db/post_install_health_check.php` | `bfbecce31c942220921ac268e0232065a267a7db3ee9d91d549c63103aaa946f` |
| `tools/release/ControlReleaseBridge.php` | `41a4198fb9a704efadd7e235c60656e9f33d65d3a910c6305ebab610be1d62fd` |
| `tools/release/CustomerReleaseProfile.php` | `08c068252dad2e37b1679d728d3c54610304ba44a2759cd84c3f7799fe090572` |
| `tools/release/ReleasePackagePolicy.php` | `64c2d2c1d7779f7fe1c50391d9306ff86fe9621d9d1cf1bd5f95201cb255ac68` |
| `tools/release/artifact_signature.php` | `f18f3fd83dee0f32dea183bb79620610b1f7dbd108232cac4995c938e581ee37` |
| `tools/release/build_release_artifact.php` | `f408a6150961008ecc82d23d7912591b933f78a5b94ba3d2f4b8d2b6c39d98f1` |
| `tools/release/customer_clean_profile.json` | `3ada4d1e0881dacf55d911016c8b3083831de50674f87fd57bda8db5c93452bc` |
| `tools/release/package_policy.json` | `6755f1c2bb325aee0d66c1d28f0edc2f10dc53d74eb7b6834960c420c43ad515` |
| `tools/release/runtime_compatibility.json` | `ed57b74576a203b589cc3a2c6dac006a862f519da41ea2660add96350421a929` |
| `tools/security/toolchain.lock.json` | `a6418adfd2e86061434d2c8b89aa084f483e9e948f0acaaf78a0b1417666e076` |
| `tools/static/ci3-stubs.php` | `418107ca71d8ec14a91587b1003c6d21d2dc061afc8f8838f4c224b5b8c8e07d` |
| `tools/static/phpstan-baseline.neon` | `c173a8d094359176c8ec3d975a0e95a69d3957ee68c90959f507ebe62c5662bf` |
| `tools/static/phpstan.neon` | `83e4fabe003027c9ec7186861fa6e1a0852a6e0bf56af2a95bc311152e806f3b` |
| `tools/static/toolchain.lock.json` | `11e8744761043d2c011cbdea2706d703a8c54df0945af061058775add9bc81b4` |
| `tools/tests/a4_dependency_vulnerability_smoke.php` | `15361b67e5d57f27cbcffe433a2c43291febe478d27d0297a1fe639518651ccb` |
| `tools/tests/a4_release_preflight_smoke.php` | `e91947d55979354590552137110da5d5ab988949462562a270c265ac3d994fec` |
| `tools/tests/a4_static_analysis_smoke.php` | `991250a904df51867bd83d4ee5b8c342bced6e0ad7a09cb4d0547eccc72f46c6` |

Hash runtime `tools/install/ControlDelivery.php`: `82b8f34190186010b21b6222bec4f81ebb177a7c4e5c4973cb2294b2d7cfc928`.

## Langkah berikut di Control

1. Review commit dan diff Finance, profil v4, hash dependensi serta hasil uji penutup; pertahankan cutoff historis terpisah.
2. Perbarui trust hanya setelah review dan uji aktual cutoff baru. Jangan mengubah `BUILD_APPROVED` sebagai jalan pintas atau memakai INTERNAL untuk pelanggan.
3. Scan → preview → impor pada produk NAMUA_FINANCE yang sama, tanpa membuat duplikat produk.
4. Setelah semua blocker yang relevan diterima/diselesaikan, lakukan build draft melalui UI. Review/publish tetap keputusan operator; laporan ini tidak membuat release customer.
