# Handoff Control — installer satu folder Finance alpha.17

18 September 2026, Asia/Jakarta. **IMPLEMENTED / LINUX_ACCEPTANCE / CONTROL_REVIEW_REQUIRED / NOT_PUBLISHED**.
Windows masih **PENDING_REAL_HOST**, bukan platform yang sudah disetujui untuk dijual.

## 1. Cutoff dan batas perubahan

- Commit implementasi: `1bff31e195d44a17bb3759dd5fd396c3375bceac` (47 file), parent `2894fd57`.
- Versi `0.1.0-alpha.17`, `CUSTOMER_CLEAN` versi **6**, layout **FINANCE_SINGLE_FOLDER_V1**.
- Konfigurasi tetap `FINANCE_CUSTOMER_LOCAL_V1`, JSON schema 1. Schema DB `finance-20260907`, baseline `clean-install-20260909` tidak berubah.
- SHA-256 `app-manifest.json`: `d316f442260be220cd02a634b8b1acf464083788c311dc25b93fbcf64700f399`.
- SHA-256 `tools/release/customer_clean_profile.json`: `d12ff3b2705bcabc0d06d0a5a665d76bf32689ffcd05679e39dca077c4b37ac1`.
- Tidak ada TAR release live, hash TAR publishable, aktivasi live, publish, push, atau perubahan source/DB Control. Jangan memakai hash TAR fixture sebagai artefak penjualan.
- Penghapusan dua komentar URL oleh pengguna di `application/config/config.php` tetap di working tree, **tidak dimasukkan commit dan tidak ditimpa**. Gunakan cutoff bersih untuk build.
- Tidak memindahkan instalasi/master yang sedang berjalan, mengubah proses bisnis, menjalankan SQL di DB operasional, atau mengganti layanan/credential global. Perubahan `Pos`, `User_guide`, dan `Feature_gate` hanya resolusi path source setelah FCPATH menjadi public.

## 2. Isi paket dan pengalaman customer

```text
finance/
  public/                    document root; index.php, assets/, uploads/
  application/               kode privat dari HTTP
  system/
  config/customer.json       dibuat pemasang; tidak masuk Git/TAR kode
  config/customer.example.json
  storage/                   logs, cache, sessions, inbox terenkripsi, setup, license
  private/                   delivery, agent, journal, receipt; tidak terbaca akun web
  installer/layout.json      penanda immutable profil v6
  tools/install/portable/    pendamping CLI, helper izin, template web/scheduler
```

`CustomerLayout` memetakan aset dan front controller **saat build saja**. Root asli `index.php` tetap di parent sebagai bootstrap internal, tidak boleh menjadi entry HTTP paket v6. Web dengan document root yang salah ditolak. Semua core/profile/signature tetap diverifikasi; hanya `config/customer.json` menjadi konfigurasi lokal mutable. State baru dibatasi `private/` dan `storage/`, bukan pengecualian seisi kode/config.

Sesudah admin menyiapkan akun layanan/HTTPS/document root/pendamping sekali: customer membuka `/setup`, mengisi kode setup dari Control, DB kosong, URL, serta admin pertama, lalu **Pasang dan aktifkan**. UI Indonesia menunjukkan progres dan pemulihan; secret browser dikirim dalam POST HTTPS, bukan URL. Queue hanya berisi credential yang disegel sodium untuk pendamping.

Pendamping berjalan sebagai **pemilik paket non-root/non-Administrator**, terpisah dari akun web. Web tidak memegang private agent key atau hak mengubah kode/cache lisensi. Helper administratif hanya menata izin paket baru atau mendaftarkan Task Scheduler; PHP web tidak dinaikkan haknya. Shared hosting tanpa kemampuan pemisahan akun tidak dinyatakan kompatibel.

Panduan customer satu halaman: [customer_single_folder.md](customer_single_folder.md).
Detail admin: [customer_single_folder_admin.md](customer_single_folder_admin.md).

## 3. Perubahan wajib pada Control

Pekerjaan berikut **belum dijalankan oleh Finance**; jangan melewati persetujuan keamanan/pin Control:

1. Review cutoff, profil v6 dan pemetaan source→TAR. Perbarui dependency pin `Finance_build_trust` beserta validator inventory pada thread Control, bukan hanya mengizinkan angka 6. Pertahankan pin/artefak historis alpha.15/alpha.16/release 66.
2. Policy portal yang saat audit hanya mengenal profile 1–5 harus mengenali v6 **setelah review**. Installer lama Linux root/external tetap untuk versi lama; v6 memakai pendamping baru, jangan dikirim ke renderer/job lama secara diam-diam.
3. Siapkan satu pengiriman customer yang menempatkan bukti di `finance/private/delivery/`: `package.tar` asli, `release.json`, `release.sig.json`, `release-trust.json`, `permit.json`, `credentials.json`. Ini envelope distribusi customer, **bukan** memasukkan credential ke TAR kode generik. Tautan unduh/control access harus menjaga rahasianya. Semua tetap di satu parent Finance.
4. Terbitkan izin setup bertanda tangan sesuai kontrak di bawah dan kode setup acak minimal 32 byte. Customer tidak memilih ulang produk/paket/fitur. Persetujuan deployment, aktivasi, jumlah server, masa hak dan maintenance tetap otoritas Control.
5. `credentials.json` memuat `instance_id`, `environment`, `control_origin`, `activation_code`, `license_trust` publik dan `monitoring: {key_id, secret}`. Hash **byte file persis** harus diikat izin setup. Tidak memuat private signing key Control. Kunci agent/installation ID baru dihasilkan lokal saat `prepare`, bukan dibagikan dalam release.
6. Sediakan reissue izin/credential melalui workflow operator yang sah. Endpoint otomatis baru **tidak diimplementasikan/dianggap tersedia** oleh Finance. Reissue mempertahankan binding instance, deployment, plan, release, cutoff, profil dan environment; bukan menghapus journal/identitas atau mengulang DB.
7. Aktivasi/poll/recovery, deployment receipt dan heartbeat tetap memakai endpoint Control yang sudah ada. Receipt dikirim setelah baseline/migrasi/owner serta halaman login HTTPS nyata berhasil; setup baru ditutup setelah acknowledgment receipt. Heartbeat HMAC terpisah dari permintaan lease lisensi dan bukan grant aktivasi.
8. `heartbeat.runtime.primary_domain` hanya hostname dari base URL lokal yang tervalidasi, tanpa port/path, maksimum 190 byte. Tidak menggunakan HTTP_HOST dan tidak mengunci lisensi ke domain. Region opsional pada kontrak sender lama tetap kompatibel; sender baru tidak mengarang region.
9. Jangan menambah deadline instalasi dari pembelian/registrasi/build/publish. Expiry izin sementara dicek sebelum memulai submission baru; percobaan yang sudah tercatat dapat meneruskan langkah aman. Aktivasi tetap memerlukan credential yang diterima Control, tidak melewati expiry token aktivasi. Customer boleh meminta izin pengganti kapan diperlukan sesuai haknya.
10. Windows memakai `windows-amd64`, MachineGuid dan ACL nyata. Policy platform lisensi Control harus direview **sesudah acceptance Windows nyata**. Manifest pemasaran belum mengumumkan Windows supported. Jangan membuka kuota tambahan atau mengganti fingerprint server Linux lama.

### Izin setup NAMUA_FINANCE_SETUP_V1

Envelope JSON: `key_id`, `payload_base64`, `signature_base64`. Signature Ed25519 atas UTF-8:

```text
NAMUA_FINANCE_SETUP_V1\n<sha256 lowercase hex dari raw payload JSON>
```

`\n` di atas berarti satu byte newline. Key ID/public trust sama dengan release terverifikasi; Control perlu mereview lifecycle/rotasi public trust tersebut. Private issuer key tetap hanya di Control.

Payload wajib: `purpose=NAMUA_FINANCE_SETUP_V1`, `product_code=NAMUA_FINANCE`, `permit_id` UUID, `instance_id`, `deployment_id` UUID, `plan_sha256`, `release_public_id`, `source_commit`, `release_manifest_sha256`, `artifact_sha256`, `profile_sha256`, `profile_version=6`, `environment`, `credentials_sha256`, `setup_secret_sha256`, `issued_at` dan `expires_at` integer Unix UTC. Tidak ada binding domain atau tanggal pembelian. Izin baru harus punya permit_id baru; journal/attempt lama diarsipkan.

## 4. Database, konfigurasi dan pemulihan

- Baseline + **20 migrasi clean_install terdaftar**, bukan semua SQL. DB kosong diperiksa pada metadata tables/routines/events; password salah/nonempty berhenti tanpa DROP/TRUNCATE.
- Native PDO memakai konfigurasi customer yang sama dengan aplikasi, tanpa memerlukan mysql option file eksternal atau argument password. Enkripsi lokal dibuat acak. Local JSON invalid/berkonflik dengan env/file eksternal tidak fallback ke database lain; legacy tanpa lokal tetap kompatibel.
- Journal mengikat endpoint/nama/user DB dan release, mencatat checksum langkah/ledger. `READY` sesudah langkah durable boleh dilanjutkan. `RUNNING` berarti DDL tidak pasti: wajib pemeriksaan admin, tidak replay otomatis. `COMPLETE` tidak mengulang SQL.
- `retry-input` hanya sebelum journal DB ada, mengarsipkan input lama dan mempertahankan agent. Setup setelah sukses terkunci. Reissue token tidak menghapus DB/history atau mengganti identitas instalasi.
- Hilangnya config/context/lease valid tidak membuka legacy/development. Runtime memverifikasi core, signature, machine binding dan signed lease. Penolakan kuota tidak meminta pencabutan server lain.
- Lima SQL development yang belum managed (`14c`, `15a/b/c`, `16a`) masih backlog `_30`; tidak diselundupkan dalam perubahan installer. Kelulusan instalasi bukan UAT seluruh modul bisnis atau kesiapan semua fitur terbaru.

## 5. Bukti pengujian

Pengujian memakai runtime/MariaDB baru terisolasi, socket `--skip-networking`, HTTPS nginx nyata dan PHP-FPM web non-root tanpa FINANCE_* environment khusus. Akun/signing key/credential acak hanya fixture; Control transport sintetis yang memeriksa signature/HMAC, **bukan** aktivasi atau receipt live. Hanya fixture milik tes dibersihkan.

| Pengujian | Hasil |
| --- | --- |
| customer_portable_acceptance --disposable | **59 PASS dari checkout bersih commit `1bff31e1…`**, termasuk kuota ditolak sebelum SQL dan izin pengganti tanpa reset identitas; putaran awal 56 PASS |
| customer_portable_contract_smoke | 42 PASS: parser SQL/procedure, semua baseline/migrasi terdaftar, mapping, owner policy |
| customer_local_config_smoke | 58 PASS: field/JSON/path/symlink/izin dan prioritas/konflik legacy |
| c3_customer_clean_release_smoke | 118 PASS: build deterministik, TAR verifier, secret/data exclusions, signature/profil/gate binding |
| c4_control_license_agent_smoke | 72 PASS: invalid/expired/used token, kuota, replacement, recovery, revocation, preservasi identitas |
| c4_control_license_verifier_smoke | 26 PASS |
| c4_customer_bootstrap_guard_smoke | 60 PASS; fixture historis v5 tetap diuji, v6 diuji melalui HTTP pada suite baru |
| c3_control_delivery_smoke | 27 PASS |
| c2_c4_commercial_foundation_smoke | 14 PASS |
| a4_release_preflight_smoke | 1.779 kandidat; 983 PHP lint; 1 Node; 3 Python; 0 finding |
| a4_static_analysis_smoke | PASS, scope application, baseline_errors=0 |
| a4_dependency_vulnerability_smoke | PASS: 3 sumber/145 package, 0 advisory pada snapshot lokal |
| c3_control_build_runtime_smoke --isolated | **8 gate + verifier independen PASS dari checkout cutoff `1bff31e1…`**; 1.164 file, 306 tabel, restore checksum cocok; fixture bukan release |
| Composer / whitespace / sh -n | Valid / PASS / PASS; Composer sistem mengeluarkan deprecation PHP 8.1 |

End-to-end mencakup first-owner **POST login nyata**, akses privat/encoded path melalui HTTP, setup tanpa izin/duplikat/terkunci, DB nonempty/password salah, config CLI/web, perubahan core/signature/fingerprint, hilangnya context, symlink/permission/JSON, web tidak bisa membaca key atau mengganti cache, lease sync/heartbeat terpisah, serta penghentian proses setelah baseline durable dan resume tanpa replay. DDL berstatus RUNNING ditolak dengan ledger/journal tetap sama.

Windows nyata, Apache/IIS HTTP dan scheduler OS **belum dieksekusi**. Jangan menyatakan mock/static check sebagai dukungan Windows selesai. Status PASS gate di manifest fixture end-to-end adalah stimulus tes, bukan evidence build live. **Build penuh terisolasi yang terpisah telah benar-benar menjalankan delapan gate + backup/restore**, exit 0 dari checkout cutoff `1bff31e1…`; hasil di bawah. Control tetap wajib mengulang gate dan approval pada cutoff persis sebelum rilis.

```json
{
  "source_cutoff": "1bff31e195d44a17bb3759dd5fd396c3375bceac",
  "status": "PASS",
  "kind": "ISOLATED_INTEGRATION_FIXTURE_NOT_RELEASE",
  "included_files": 1164,
  "excluded_files": 333,
  "system_seed_records": 735,
  "generic_sample_records": 0,
  "customer_data_findings": 0,
  "secret_findings": 0,
  "tables_checked": 306,
  "non_reference_tables_empty": 293,
  "migrations_applied": 20,
  "backup_restore": "PASS",
  "logical_checksums_match": true,
  "fixture_inner_manifest_sha256": "b48f3874ca454328879b7753a113bfd93b9c33965c04d2b08099398704fbf0c4",
  "fixture_dump_sha256": "62acd714e28874fa5674f9962ada6ee8df4e47bea1aadb5db522741aa07ce31b",
  "source_database_accessed": false,
  "signed_or_published": false
}
```

Gate: source_clean, security_scan, install_test, backup_restore, customer_data_scan, secrets_scan, clean_install, source_untouched. Adapter membuat commit snapshot sintetis untuk fixture dari cutoff tersebut; itu bukan source_commit release customer. Hash inner manifest fixture bukan app-manifest atau hash TAR untuk publish. Semua fixture/checkout uji dibersihkan; tidak menghapus artefak release lama atau data aplikasi.

Reproduksi Linux (harness membutuhkan akun/binary yang tercantum di source; bukan command untuk DB customer/produksi):

```sh
php tools/tests/customer_portable_contract_smoke.php
php tools/tests/customer_portable_acceptance.php --disposable
php tools/tests/c3_customer_clean_release_smoke.php
php tools/tests/c4_control_license_agent_smoke.php
php tools/tests/a4_release_preflight_smoke.php
php tools/tests/a4_static_analysis_smoke.php
php tools/tests/c3_control_build_runtime_smoke.php --isolated
```

## 6. Dependency build/validator dan hash cutoff

Runtime uji: Linux x86-64, PHP CLI/FPM 8.1.32, MariaDB 10.11.10, nginx 1.30.4, GNU tar, OpenSSL, sodium, PDO/mysql; Composer 2.0.14, PHPStan 1.12.27 (baseline 0), OSV scanner 2.5.1. Tidak mengganti lock/dependency/server global. `fileinfo` tidak tersedia pada CLI uji; kontrak lama menjadikannya kebutuhan fitur MIME WhatsApp, bukan pemasang dasar. Windows memerlukan PHP x64 8.1+sodium/PDO, PowerShell, ACL/fsutil, HTTPS/IIS URL Rewrite atau Apache, dan akun layanan non-admin.

Selain pin lama yang tetap relevan, Control harus memeriksa `CustomerLayout`, seluruh `tools/install/portable`, `CustomerPlatform`, `LicenseStateStore`, serta perubahan CustomerLocalConfig/DeploymentConfig/cache/verifier/agent/protocol/build/bridge/profile. Daftar lengkap: `git show --stat 1bff31e195d44a17bb3759dd5fd396c3375bceac`; hitung ulang hash dari checkout ini, jangan menjadikan dokumen otoritas trust.

```text
d65573093dec23cf23b9e7f81598adcd115dcc7f6c8c994d0e9c4dc6bf695901  application/libraries/CustomerPlatform.php
70f8829fb8647967ff9dc4ef8a25adc3811cc34b455764856d063378a28f5c6a  application/libraries/CustomerLocalConfig.php
64835a5ec0a6dee3ace5fe995bc4010a3bd665879372286f8b05bda432090845  application/libraries/DeploymentConfig.php
51b8cbbdebbb4a20fc52a9c41885b0866646176ea5faca46970101fbec84ed26  application/libraries/Control_license_cache.php
ed567fb5c9cb2439a3f18c12a3f244c5c80d6134c7473cf66062b6fe4fdfdf9a  application/libraries/Control_license_verifier.php
bf1f3508c6531c2a5f3c579f0c4274f161e764161941b2855eadb8e23c34780a  tools/release/CustomerLayout.php
606484f6daee779c326aeff824580e8a5d95527fef3840ff29419674f4cd445c  tools/release/CustomerReleaseProfile.php
e96f6d8b83e260bb9d295e3a3e86802f777b5458b32a697c5fd8538d9b541e25  tools/release/ControlReleaseBridge.php
c238177c9212eca877812715b89141afeb22ab985b75d3d14192acbc3c6f3815  tools/release/build_release_artifact.php
099694ebb7ea8f3bdcf2096c1b539a754501de4f796bdab93584d35bc630f67b  tools/install/portable/PortablePackage.php
9cd73711275701842773fce1082fa511fbdae55b9d4c9a3dc76a4fd4c1f58b86  tools/install/portable/PortableInstaller.php
596457cf6234f43bf852a9e5f57e1014c4adcb02ca51440fe78f75fc33ba0d39  tools/install/portable/PortableDatabase.php
f1bb0ec517405a1f04e0c06d9291d06aa2bf3fe9652fcbc5acbe269ec3824ef3  tools/install/portable/PortableStore.php
f7bf4a669286a956986393528e3be17b0824ddb38dce40e41527b9206cf57f9e  tools/install/portable/setup.php
078eace6126960f24de19141be0e31191551a1b7d2b63c8a1341590f4a20278f  tools/install/portable/windows-inspect.ps1
b8414252fbafbb6dfa6e785b34065a9258ab32e2c3df3479b4e3b86c33537a6a  tools/licensing/LicenseStateStore.php
b276c3c823c02b0e6103e1b5680463f649fd9654719e5bda5abe346b9066ab7b  tools/licensing/FinanceLicenseAgent.php
dcc7b98a660f341d03ec00dd77dc09c9cb82233bdf0b0c0a6f11a22b51fc5134  tools/licensing/ControlLicenseProtocol.php
```

## 7. Checklist acceptance tersisa

- [ ] Control: review pin/profile/layout/setup permit, reissue dan pengiriman satu folder.
- [ ] Control: build penuh delapan gate, backup/restore dan installer v6 pada cutoff yang disetujui; jangan reuse evidence alpha.16.
- [ ] Uji Windows nyata: ACL parent/child lintas akun, reparse/hardlink, MachineGuid, atomic rename/flock, IIS FastCGI/HTTPS/URL Rewrite, scheduler non-interaktif, waktu eksekusi pemeriksaan ACL.
- [ ] Uji Apache target dan walkthrough operator awam/mobile; HTTP acceptance saat ini nginx.
- [ ] Praktik UI Control dengan customer/kuota nyata hanya setelah persetujuan owner; tidak dijalankan pada tugas ini.

Kode PHP di server yang dikuasai penuh customer tidak dijanjikan mustahil dibypass. Pengamanan ini menjaga trust, signature, identitas dan kuota sesuai batas akses OS yang dinyatakan.
