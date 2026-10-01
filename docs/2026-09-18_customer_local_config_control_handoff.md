# Handoff Control — konfigurasi lokal customer Finance alpha.16

Tanggal: 18 September 2026 (Asia/Jakarta). Status: **IMPLEMENTED / REVIEW_REQUIRED / NOT_PUBLISHED**.
Tidak mengubah source/database Control, data/credential master, layanan global, aktivasi live,
atau artefak alpha.15 yang sudah PUBLISHED. Tidak ada SQL baru untuk dijalankan pada Finance aktif.

## Cutoff implementasi

- Commit kode final: `018c7173d68759c700ecfb324dc5f4946b0283bb`.
- Versi aplikasi: `0.1.0-alpha.16`; `CUSTOMER_CLEAN` **v5**, `REFERENCE_ONLY`, sample data `NONE`.
- Kontrak konfigurasi baru: `FINANCE_CUSTOMER_LOCAL_V1`, JSON schema `1`.
- `app-manifest.json` SHA-256: `7c1ea42f220afed9a852f329d360204c6b40d38940e7054739c287e902cc223c`.
- Profil SHA-256: `46511a8bad66cdf68dd4a8b0c82e0b3465ea2f4f149167c6700d2e743587db84`.
- Kontrak build/manifest/signature tetap versi yang sudah ada; schema DB
  `finance-20260907`, baseline `clean-install-20260909` tidak berubah.
- Beberapa dependency baru/berubah untuk review pin (hitung ulang dari commit,
  jangan mempercayai dokumen sebagai otoritas signature):

  | File | SHA-256 pada cutoff |
  | --- | --- |
  | application/libraries/CustomerLocalConfig.php | `19d0f6c5c6b94474ac8b126c8116e11af0b445108844fcff3279e4c35d33dfb1` |
  | tools/install/CustomerDatabase.php | `e23ae5405d024767d0cefe4ee7f3d04b5719bc44918f73922fb1a57250f9b7d4` |
  | tools/db/migration_runner.php | `8350243781da95b009a229f43dc1abc4529339e1e546bca2ec9a4ce7e4721e51` |
  | tools/install/PrivateDeployment.php | `a43c75fe5cc86e7a5961403a5a2b2e4be49cc1719d57c600c6f69e2cbb7ca209` |

- Commit dokumentasi handoff berikutnya bukan perubahan byte runtime/paket.
  Gunakan commit kode di atas sebagai cutoff review, bukan HEAD kerja yang dirty.
- Perubahan user di `application/config/config.php` (penghapusan dua komentar URL)
  sengaja tidak diikutsertakan dan tidak ditimpa. Build final diperiksa dari checkout
  bersih commit tersebut; jangan build langsung dari worktree development yang dirty.

## Perubahan kontrak

Customer mengisi **`config/customer.json`**, dari template
`config/customer.example.json`. Root ditemukan dari lokasi source, bukan path staging.
Database, port/socket, HTTPS base URL, encryption key dan session/log/cache mengikuti
file yang sama di CLI, CodeIgniter, installer dan pembacaan DB heartbeat.

Profil v5 memuat blok exact:

```json
{
  "local_configuration": {
    "contract": "FINANCE_CUSTOMER_LOCAL_V1",
    "mutable_path": "config/customer.json",
    "template_path": "config/customer.example.json",
    "included_in_artifact": false
  }
}
```

Hanya path **persis** `config/customer.json` dikecualikan dari exact installed inventory,
setelah file lolos validasi keamanan dan profil terverifikasi >=5. Tidak ada pengecualian
seluruh folder `config`/aplikasi. Template, parser, database.php, proteksi web dan core
tetap terverifikasi; customer.json tidak boleh ada di TAR atau Git.

Urutan konfigurasi:

1. Jika lokal ada, harus lengkap dan aman. Konflik nilai environment atau file eksternal
   ditolak; environment yang menutupi konflik file eksternal juga tetap ditolak.
2. Jika lokal tidak ada, legacy tetap environment > FINANCE_DEPLOYMENT_FILE. Fallback
   PHP staging tidak dipakai jika ada pilihan DB eksplisit atau source berupa paket.
3. `localhost` tanpa socket eksplisit ditolak pada kontrak lokal; gunakan `127.0.0.1`
   untuk TCP. Client installer memakai `--defaults-file` sementara, bukan mewarisi
   endpoint dari `/etc/my.cnf` atau `~/.my.cnf`. Credential tidak menjadi argumen proses.
4. Root/source/config harus root-owned tanpa group/world-write, file 0600/0640,
   regular non-link, satu hardlink, JSON valid <=16 KiB; field asing/path berbahaya
   ditolak. Runtime di luar source, tidak otomatis dibuat oleh request HTTP.

## Penyesuaian yang wajib dilakukan thread Control

1. Review cutoff alpha.16/profile v5 dan perbarui pin yang relevan di
   `Finance_build_trust.php` **oleh thread Control**, termasuk inventory hash gabungan.
   Jangan sekadar mengganti VERSION/BUILD_APPROVED tanpa review byte dan gate baru.
   Pin alpha.15 harus tetap tersedia untuk artefak historis.
2. Review dependency baru: `application/libraries/CustomerLocalConfig.php`,
   `tools/install/CustomerDatabase.php`, `tools/install/customer_config.php`, template
   JSON dan proteksi nginx/Apache. Dependency yang berubah termasuk DeploymentConfig,
   database.php, index.php, Control_license_cache, License_runtime_model, heartbeat,
   FinanceInstance, LinuxWebProfile, PrivateDeployment, clean_install_database,
   migration_runner, CustomerReleaseProfile, ControlDelivery, package_policy dan profil.
   `ControlReleaseBridge`/algoritme signature/kunci agent tidak dilonggarkan.
3. Scan/impor ulang manifest produk yang sama; pilih CUSTOMER_CLEAN v5 sesuai hash
   manifest **yang sama**. Buat release baru alpha.16; jangan menimpa alpha.15/release 66.
4. Job `finance_instance.php`: set `configuration_source: "customer_local"` dan
   **jangan** mengirim `deployment_file`, `defaults_extra_file`, `database_name_file`.
   Field operasional lain tetap: root/runtime/private, manifest/trust/TLS, akun service,
   binary, Composer, owner_file, public-license directory, instance_id, mode dan port.
   Tiga field DB lama masih sah untuk job legacy tanpa local mode.
5. Setelah `stage` berhasil, minta customer mengisi template lokal **sebelum install**.
   Runtime pada job harus cocok dengan runtime.directory pada customer.json. Tidak perlu
   mengisi DB user/password lagi di job atau PHP-FPM. Jangan menimpa konfigurasi lokal
   yang sudah diisi ketika retry/reissue token.
6. Private agent key/activation identity/heartbeat secret tetap di luar aplikasi.
   Public signed context `runtime/customer-installation.json` dibuat installer setelah
   verifikasi manifest/TAR/profil dan binding instance. Web menemukan context otomatis
   melalui runtime lokal; nginx/PHP-FPM hasil renderer tidak mempunyai env[] khusus.
   Tanpa context/lease sah, route bisnis tetap terkunci. Domain bukan binding lisensi.
7. Site nginx/aaPanel custom wajib menyertakan snippet deny /config, bukan hanya
   .htaccess. Renderer installer sudah otomatis memasang exact + ^~ prefix deny dan
   autoindex off. Apache harus AllowOverride yang tepat atau Directory deny di vhost.
8. Pertahankan aktivasi/kuota/fingerprint/nonce/deployment/token/release/signature/cutoff.
   Kontrak ini tidak menciptakan endpoint/token baru atau deadline mulai instalasi.
9. Retry DB: database nonempty ditolak; journal STARTED/COMPLETE tidak dihapus. Salah
   password pada preflight belum dianggap SQL mulai. Setelah DDL dimulai, inspection
   ledger/backup wajib; tidak ada DROP/TRUNCATE atau replay baseline otomatis.
10. Jalankan kembali build/gate dari cutoff yang dipilih Control, kemudian praktik
    instalasi melalui UI Control. Tes Finance bukan receipt aktivasi/publish live.

## Bukti pengujian

Semua credential/signing key/owner pengujian dihasilkan saat runtime, hanya untuk
fixture yang dihapus setelah selesai. Tidak ada koneksi ke DB Finance/master/Control.

| Uji | Hasil |
| --- | --- |
| customer_local_config_smoke | 58 PASS: schema/field kosong, JSON, URL, path, permission, symlink, prioritas, konflik env/file, temporary credentials, legacy |
| customer_local_install_acceptance --disposable | **41 PASS final**, termasuk exclusive MySQL options dan encoded HTTP paths; putaran sebelumnya 38 PASS |
| c3_customer_clean_release_smoke --delivery | 141 PASS, termasuk penolakan member secret/extra, signature/hash/profile/plan, domain opsional dan credential pengganti |
| c4_customer_bootstrap_guard_smoke | 60 PASS; core/context/signature/fingerprint/revocation/recovery-route/tampering |
| c4_control_license_verifier_smoke | 26 PASS |
| c4_control_license_agent_smoke | 72 PASS, termasuk penolakan quota/credential/replacement dan preservasi identitas/history |
| deployment_secret_config_smoke | 39 PASS, legacy secret contract |
| c3_customer_heartbeat_runtime_smoke | 85 PASS |
| c3_deployment_instance_smoke / c3_linux_web_profile_smoke | 15 / 16 PASS |
| c3_clean_install_database_smoke | 32 PASS |
| php -l / preflight / PHPStan | Lulus; preflight 968 PHP, 0 temuan, PHPStan baseline 0 |
| composer validate | Valid; Composer sistem 2.0.14 mengeluarkan deprecation PHP 8.1, tanpa error validasi |

End-to-end benar-benar menyalakan **nginx + PHP-FPM tanpa env[] khusus** dan MariaDB
baru `--no-defaults --skip-networking`, bukan memanggil helper JSON saja. Baseline,
20 migration clean_install, owner bootstrap, login owner via HTTPS dan health dilakukan.
Negatif: password salah, DB sudah berisi, kegagalan SQL dengan DDL parsial, retry,
file tambahan, core berubah, HTTP config/encoded URL, hilangnya config/context,
JSON rusak, permission dan symlink. Lease valid memakai key sintetis; tidak melakukan
aktivasi live. Test quota adalah fixture agent, bukan mengubah kuota Control produksi.

Pengulangan end-to-end **dari checkout bersih `018c7173…`** juga lulus 41/41.
Health: 1.142 file, 39 tabel wajib, 20 migration ledger, reference seed exact dan
1 owner sintetis aktif. Inner manifest fixture uji tersebut:
`20570161ae3a97891ca0898ab2bd792a668ab2f8d1afa5f31918c4670b29c78c`.
Ini hash **RELEASE-MANIFEST fixture**, bukan app-manifest atau TAR release Control.

Build terisolasi awal dan **pengulangan dari checkout bersih `018c7173…` sama-sama
8 gate + independent TAR validator PASS**: 1.142 file, 306 tabel,
293 tabel nonreferensi kosong sebelum bootstrap owner, 735 referensi, 20 migrasi,
0 data customer/dummy/secret; restore 306 tabel checksum cocok. Ini fixture sintetis,
**bukan** release publishable. Hasil putaran final:

```json
{
  "source_cutoff": "018c7173d68759c700ecfb324dc5f4946b0283bb",
  "status": "PASS",
  "kind": "ISOLATED_INTEGRATION_FIXTURE_NOT_RELEASE",
  "included_files": 1142,
  "tables_checked": 306,
  "non_reference_tables_empty": 293,
  "system_seed_records": 735,
  "generic_sample_records": 0,
  "customer_data_findings": 0,
  "secret_findings": 0,
  "migrations_applied": 20,
  "backup_restore": "PASS",
  "logical_checksums_match": true,
  "fixture_inner_manifest_sha256": "e20a3123aec279ba75b249ad4cfc303284d052b66118107e214979cd486e8e20",
  "fixture_dump_sha256": "600906155d00f0bb8c18b02201fd63dd90f1af16d7fae88c081f8407700b9173",
  "source_database_accessed": false,
  "signed_or_published": false
}
```

Adapter suite membuat commit fixture sintetis dari file cutoff tersebut; commit
sintetisnya bukan source_commit yang boleh dipakai untuk release customer. Delapan
gate: source_clean, security_scan, install_test, backup_restore, customer_data_scan,
secrets_scan, clean_install, source_untouched. Reproduksi di checkout bersih:

```bash
php tools/tests/customer_local_config_smoke.php
php tools/tests/customer_local_install_acceptance.php --disposable
php tools/tests/c3_customer_clean_release_smoke.php --delivery
php tools/tests/c4_customer_bootstrap_guard_smoke.php
php tools/tests/c3_control_build_runtime_smoke.php --isolated
```

Suite disposable memerlukan root Linux, akun/binary toolchain lokal yang dicantumkan
di script; bukan perintah untuk dijalankan pada database operasional. Control tetap
wajib membuat build normal dengan source_commit final yang dipilihnya sendiri.

## Toolchain / batas pembuktian

- Linux amd64, PHP CLI/FPM **8.1.32**, MariaDB disposable **10.11.10**, nginx **1.30.4**.
- Composer **2.0.14** sistem (deprecation); PHPStan **1.12.27**, OSV scanner **2.5.1**.
- Static lock SHA: `11e8744761043d2c011cbdea2706d703a8c54df0945af061058775add9bc81b4`.
- Security lock SHA: `a6418adfd2e86061434d2c8b89aa084f483e9e948f0acaaf78a0b1417666e076`.
- Toolchain lock tidak diubah; aturan umur OSV <=48 jam tidak dilemahkan.
- Apache tidak tersedia pada staging ini: aturan disediakan/review, tetapi HTTP
  acceptance yang dieksekusi adalah nginx. Apache harus diuji pada environment target.
- Subpath URL belum didukung oleh installer; HTTPS root-domain didukung. PHP selain
  8.1/MariaDB selain kontrak signed tetap ditolak. Jangan mengklaim portabilitas Windows.
- Lima SQL development sudah belum terdaftar sebelum batch ini: `2026-09-14c`,
  `2026-09-15a`, `2026-09-15b`, `2026-09-15c`, `2026-09-16a`. Tes A5 pada repo mentah
  gagal `unacknowledged_sql`; bukan dibypass/dihapus untuk membuat hijau. Paket memakai
  katalog/allowlist clean_install yang sudah terdaftar, bukan seluruh folder SQL.
  Readiness fitur bisnis yang membutuhkan lima SQL itu tetap pekerjaan terpisah.
- Dua batas test harness ditemukan dan diperbaiki: pipe SQL besar harus didrain saat
  SQL error, dan log privat harus 0600 terlepas dari umask caller. Inisialisasi datadir
  fixture diberi timeout 180 detik karena 30 detik tidak cukup saat I/O paralel.

## Panduan customer

[Panduan langkah demi langkah](customer_local_install.md) ikut paket,
ditautkan dari README. Mencakup file yang diisi, contoh JSON, permission, nginx/Apache,
stage/install, database-only alternatif, aktivasi, login pertama dan pemulihan kegagalan.
