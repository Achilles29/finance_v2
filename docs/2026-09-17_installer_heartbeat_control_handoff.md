# Handoff Finance–Control: domain metadata, credential pengganti, heartbeat

> Lanjutan review 17 September: Control menemukan DB/versi heartbeat masih bergantung konfigurasi master/Git, serta kebutuhan boundary lisensi customer yang terpisah dari mode audit master. Penyesuaian integrasi lanjutan ditujukan untuk kandidat alpha.15 pada [handoff readiness customer](2026-09-17_customer_package_control_readiness_handoff.md). Identitas dan hasil alpha.14 di bawah tetap bukti historis; bukan persetujuan otomatis untuk source lanjutan.

Batch 267 — 2026-09-16/17 WIB. Scope hanya integrasi produk. Parent Finance `65bc4c2c0d6d6149264282318db5c6c45ccb1391`. Kandidat baru **0.1.0-alpha.14**, bukan penggantian artefak release **66 PUBLISHED**. Commit final adalah commit Finance yang memuat laporan ini; hash disampaikan setelah commit. Tidak push/publish/deploy, tidak mengubah source/database Control, konfigurasi/credential server, atau data development.

## Checklist implementasi

- [x] `ControlDelivery` menerima primary_domain tidak ada, NULL, kosong, berbeda dan berubah. Domain tidak lagi divalidasi sebagai syarat constructor, dicocokkan ke claim, atau menjadi binding state baru. Tidak pernah menulis FINANCE_BASE_URL dari registry.
- [x] FINANCE_BASE_URL tetap milik customer. Validasi URL HTTPS dan health lokal dipertahankan. Tidak ada deadline instalasi berdasarkan pembelian, registrasi, draft, build, publikasi atau umur release. Expiry credential dan masa lisensi/maintenance tetap mengikuti Control.
- [x] Binding instance, token, origin, deployment, plan_sha256, release/version, signature, manifest/TAR, cutoff, profil dan seed tetap diperiksa. Profil harus cocok dengan **release yang diverifikasi**, bukan profil lain dari registry. Job boleh menyertakan pin `deployment_id` dan `plan_sha256`; jika ada harus cocok persis.
- [x] Percobaan unduh baru dengan credential pengganti memakai direktori state baru dan hubungan ke hash journal lama. Token lama tidak bisa didaur ulang dalam rantai percobaan. Release/deployment/plan yang sudah diketahui tidak boleh berubah saat penggantian. Artefak partial dan journal lama tidak dihapus.
- [x] Journal schema 1 hanya dapat dimigrasikan dengan job asli yang binding-nya cocok; snapshot `delivery-v1.json` disimpan. Setelah migrasi schema 2, perubahan metadata domain tidak mengunci resume. Jangan menebak job lama atau mengedit hash journal.
- [x] License agent menyimpan riwayat percobaan berupa hash credential/status/waktu; kode rahasia tidak disalin ke log/public cache. Kode pengganti mempertahankan installation ID, fingerprint, kunci agent dan instance. Penolakan kuota tidak menjadi lisensi aktif.
- [x] Heartbeat runtime opsional: hanya primary_domain dan region, masuk digest/HMAC sebelum dikirim. Host dari FINANCE_BASE_URL tepercaya atau public_url di config privat, tidak pernah HTTP_HOST. Maksimum hostname 190 byte; region 80 karakter Unicode tanpa control character. Tidak mengirim bisnis atau secret.
- [x] Runtime lama tanpa metadata kompatibel. Field absen tidak dikirim, string kosong tetap dikirim untuk mengosongkan metadata. URL lokal tetap digunakan untuk health. Root source heartbeat relatif terhadap paket; config wajib root-owned, di luar webroot, tidak group/world-writable.
- [ ] Review dan uji cutoff persis di Control, lalu buat release baru melalui alur approval normal. Tidak memberi approval otomatis atau menimpa release 66.

## Pemulihan token: langkah operator

Token unduh `ndi_…`, kode aktivasi `nla_…`, dan key/secret heartbeat **berbeda fungsi**. Jangan saling menggantikan. Control saat ini sengaja mengembalikan kode umum untuk credential invalid/expired/used/revoked; Finance tidak mengklaim tahu alasan yang lebih spesifik dari respons tersebut.

1. Hentikan installer lama. Simpan job privat, `delivery.json`, partial download, journal `instance.json`, serta hasil migrasi/health yang sudah ada. Jangan menghapus database, reset installation ID atau membuat ulang kunci untuk mengatasi expiry.
2. Jika paket sudah `VERIFIED`, gunakan artefak lokal yang sudah diverifikasi; token unduh tidak perlu diperpanjang hanya karena customer baru mulai instalasi belakangan. Ini **tidak** menggantikan keputusan aktivasi/lisensi Control.
3. Jika unduhan ditolak/terputus, buka detail deployment yang sama di Control. Alur UI `deployments/{id}/reissue` sudah ada: izin `deployments.execute`, step-up, konfirmasi installer berhenti, alasan 10–500 karakter, deployment masih RUNNING. Control mencabut token lama dan memberikan credential baru. Jangan memanggil route UI ini sebagai API tanpa autentikasi/CSRF.
4. Buat direktori percobaan baru privat, root-owned mode 0700. Salin job lama menjadi `job.json` baru mode 0600; ganti **state_dir dan install_token** saja (metadata primary_domain boleh dihapus/diubah). Target hanya boleh memuat job.json dan lock; tidak boleh berisi receipt/instance/partial/artefak lama.
5. Contoh setelah file privat dipersiapkan oleh admin (path contoh, bukan credential):

```bash
sudo php /path/finance/tools/install/control_delivery.php replace --previous-job-file=/var/lib/finance-delivery/attempt-01/job.json --job-file=/var/lib/finance-delivery/attempt-02/job.json
sudo php /path/finance/tools/install/control_delivery.php fetch --job-file=/var/lib/finance-delivery/attempt-02/job.json --trust-file=/var/lib/finance-delivery/release-trust.json
```

`replace` **tidak menginstal atau menjalankan SQL**. `fetch` hanya mengunduh/verifikasi. Setelah itu periksa journal instance dan keadaan DB: status INSTALLING/terpasang tidak boleh dijalankan ulang seolah belum mulai; clean install juga menolak DB tidak kosong. Cocokkan migration ledger, health, manifest/artifact dan konfigurasi instance lama. Jangan mengganti signed_manifest dalam konfigurasi instance yang sedang berjalan secara buta: binding konfigurasi memang akan menolak. Pemulihan setelah SQL parsial tetap perlu review admin, bukan reset otomatis.

Untuk kode **aktivasi** yang ditolak, minta kode pengganti lalu ulang `finance_license.php activate --code-file=...` dengan private/public directory yang sama dan argumen lain seperti semula. State INPUT_REJECTED/DENIED menerima credential baru, bukan credential yang sudah ditolak. Kuota tetap diputuskan Control. State REQUEST_UNCERTAIN tidak menerima aktivasi baru: gunakan `recover` terautentikasi untuk memulihkan respons percobaan yang sama; jika gagal, pemeriksaan Control diperlukan. Recovery tidak menciptakan hak, memperpanjang expiry atau mengubah masa lisensi.

### Batas otomatisasi Control

Tidak ditemukan API machine-to-machine untuk menerbitkan ulang install token; yang tersedia adalah UI operator di atas. Finance **tidak menambah atau mengarang endpoint**. Jika kelak diperlukan, kontraknya perlu disepakati Control: autentikasi owner/agent yang berwenang, binding instance+deployment+plan+release tetap, alasan/idempotency key, revokasi credential lama secara atomik, nonce/replay guard, audit event, expiry credential baru, tanpa reset kuota/identitas/masa lisensi. Journal dan pemeriksaan instalasi parsial tetap wajib.

## Mengaktifkan metadata monitoring

Tidak ada config staging yang diubah otomatis. Pada file privat heartbeat customer, field lama tetap seperti semula. Tambahan opt-in:

```json
{
  "runtime_fields": ["primary_domain", "region"],
  "region": "Jakarta"
}
```

Ini **potongan tambahan**, bukan seluruh file config. Tanpa `runtime_fields`, payload lama tidak berubah. `runtime_fields: ["region"]` hanya memperbarui region. `region: ""` mengosongkan region. Domain diambil dari FINANCE_BASE_URL pada environment/deployment file terproteksi; jika tidak didefinisikan, gunakan public_url privat. Nilai lokal eksplisit kosong menghasilkan metadata domain kosong; health tetap memerlukan URL HTTPS lokal valid. Jangan mengosongkan base URL aplikasi hanya untuk mengganti metadata. Domain output tidak memuat protokol, port, path, query atau credential; IPv6 literal tanpa bracket/port didukung sesuai validator Control.

Default config path tetap `/var/lib/finance-config/control-center-heartbeat.json`; cron harus menggunakan environment/deployment file **instalasi customer tersebut**, bukan request browser. Konfigurasi database legacy sender tetap perlu disiapkan admin per instalasi; batch ini tidak mengganti credential atau memasang cron. HMAC, nonce dan idempotency tetap sama.

Pemeriksaan metadata filesystem (tanpa membaca secret) pada workspace ini: direktori `/var/lib/finance-config` root:www 0750, tetapi file heartbeat default tersebut tidak ada. Karena itu **tidak mengklaim heartbeat live sudah aktif/terkirim**. Admin perlu menyiapkan config/key instance customer dari Control melalui alur deployment yang sah; tidak membuat credential dummy menjadi konfigurasi nyata.

## Uji dan batas pembuktian

| Uji | Hasil |
| --- | --- |
| Installer contract | 27 PASS: domain NULL/kosong/berbeda/absen, binding instance/deployment/plan/artifact tetap ditolak bila salah |
| CUSTOMER_CLEAN + delivery HTTPS lokal | 131 PASS; kunci/TLS/TAR ephemeral, token malformed/unknown/rejected/used, replacement, resume, partial, journal, profil/signature/hash, SQL replay guard |
| Heartbeat + validator Control aktual read-only | 37 PASS; format lama/baru, allowlist, URL spoof, 190 byte, Unicode 80 karakter, control character, omission/empty, digest |
| License agent | 72 PASS; invalid/replacement/uncertain, fingerprint, signature/cache/nonce, state/OS permission, penolakan kuota tidak menerbitkan lisensi |
| Release bridge | 29 PASS; signature/hash/runtime/cutoff salah ditolak |
| License verifier | 26 PASS; binding identitas dan dokumen bertanda tangan |
| Local URL/deployment | 15 PASS; HTTPS dan konfigurasi URL customer tidak ditimpa HTTP host |
| Preflight aktual terakhir | PASS: 1.743 kandidat, 961 PHP, 1 Node, 3 Python, 0 finding; termasuk dua file tes baru setelah masuk index Git |
| Lint / roadmap / adapter | 10 PHP berubah PASS; roadmap 26 PASS; adapter/default/permission 59 PASS SQLite memory; whitespace bersih |
| Build penuh terisolasi terakhir | PASS, exit 0, delapan gate + verifier independen; 1.135 file, 735 referensi/default, 20 migrasi, 306 tabel/293 nonreferensi kosong, 0 customer/demo/secret; backup–restore logical checksum dan health cocok |

Tes delivery HTTPS memakai issuer fixture lokal dengan respons Control, bukan server Control aktif. Quota/expiry diuji sebagai penolakan protokol pada Finance; algoritme/concurrency kuota dalam database Control live **tidak** diklaim teruji oleh fixture ini. Validator metadata Control aktual diimpor tanpa bootstrap/database. Pengujian instalasi menggunakan DB/socket disposable; tidak mengakses DB development. SQL replay guard diuji sebelum operasi SQL, bukan me-reset DB demi tes. Tidak ada SQL baru atau migrasi untuk diterapkan ke staging/utama.

Build final selesai 2026-09-17 00:25 WIB, log `/tmp/finance-alpha14-final-build.log`, status `ISOLATED_INTEGRATION_FIXTURE_NOT_RELEASE`, `signed_or_published=false`. Seluruh **1.135 anggota profil** dibandingkan byte/hash-nya antara snapshot build final dengan workspace: **0 mismatch**. Tidak ada perubahan runtime setelah snapshot final; hanya pencatatan hasil di dokumen. Putaran pertama (`/tmp/finance-alpha14-full-build.log`) juga PASS tetapi belum memuat guard delivery terakhir, sehingga sengaja diulang. Fixture memiliki commit sintetis sendiri dan dibersihkan; Control tetap wajib membangun/menguji **commit final persis** sebelum approval/publish, bukan menganggap log ini sebagai receipt produksi.

Perintah reproduksi (PHP 8.1; `--delivery`/agent/build memerlukan Linux root untuk membuat private fixture di luar webroot, bukan untuk membuka DB aktif):

```bash
php tools/tests/c3_control_delivery_smoke.php
php tools/tests/c3_customer_clean_release_smoke.php --delivery
php tools/tests/c3_control_heartbeat_smoke.php --control-root=/www/wwwroot/control
php tools/tests/c4_control_license_agent_smoke.php
php tools/tests/c4_control_license_verifier_smoke.php
php tools/tests/c3_control_release_bridge_smoke.php
php tools/tests/c3_deployment_instance_smoke.php
php tools/tests/c3_control_build_adapter_smoke.php
php tools/tests/a4_release_preflight_smoke.php
php tools/tests/c3_control_build_runtime_smoke.php --isolated
```

`--control-root` hanya mengimpor class validator read-only; jangan menggantinya dengan bootstrap aplikasi Control atau suite operasional yang menulis DB aktif. TLS/download fixture tidak memerlukan akses internet, key nyata, atau install token pelanggan.

## Identitas cutoff untuk reviewer Control

| Field | Nilai |
| --- | --- |
| Version | `0.1.0-alpha.14` |
| Profile | `CUSTOMER_CLEAN`, version `4`, seed `REFERENCE_ONLY` |
| App manifest SHA-256 | `cd634e8ee40653adf991f163df28cdc230ad61920529ac3598bb276a9b3a0aba` |
| Profile SHA-256 (tidak berubah) | `3ada4d1e0881dacf55d911016c8b3083831de50674f87fd57bda8db5c93452bc` |
| Validator SHA-256 (tidak berubah) | `9dfa593caedf80d23cabb66afe378941daf5bb492ac8751272ed367add32b14e` |
| Digest inventaris 27 dependency Control | `fa3c17bbf3d7afd022fed387f03aa74b58cdae43797d51eb208248032a97a7cc` |
| Static toolchain lock SHA-256 | `11e8744761043d2c011cbdea2706d703a8c54df0945af061058775add9bc81b4` |
| Security toolchain lock SHA-256 | `a6418adfd2e86061434d2c8b89aa084f483e9e948f0acaaf78a0b1417666e076` |

Dari 27 dependency yang dipin Control, hanya app-manifest berubah dibanding Batch 266; 26 hash lain sama dengan tabel pada [handoff v4](2026-09-16_finance_customer_clean_v4_handoff.md). Digest dihitung SHA-256 JSON path→hash, SORT_STRING, JSON_UNESCAPED_SLASHES. **Runtime berikut juga berubah dan wajib direview meskipun tidak termasuk 27 pin verifier**:

| File runtime | SHA-256 |
| --- | --- |
| `scripts/control_center_heartbeat.php` | `9ef65246df28cd1430c529f40915a088047e904613f1cc71e4541d833b200be3` |
| `tools/install/ControlDelivery.php` | `3b86bf26b3948c25602e9dec3c6f6b89cac02922c16aff2e46c5b8dd30b42aea` |
| `tools/install/control_delivery.php` | `65455258227d8624129cbd249fff9598e8c4898235de548fdd336bbe38a7b2e6` |
| `tools/licensing/FinanceLicenseAgent.php` | `24bd4e517de80fe12d75414b7842221f16b0d9fbbe894730a410581438b75389` |
| `tools/licensing/finance_license.php` | `9a173325dc4e5d7c7fccdd580464de20ae217b2fc33b1e7f68e16e4c44c453ec` |

File lain: app-manifest, lima suite tes (delivery contract, delivery runtime baru, customer-clean, heartbeat baru, license agent), panduan heartbeat, kedua roadmap, execution log, laporan ini. Profil allowlist tidak berubah karena seluruh file runtime sudah tercakup; tidak menambah SQL/aset/data seed.

Toolchain: Linux amd64; aplikasi PHP >=8.1 <8.2 (uji 8.1.32), MariaDB >=10.11 <10.12; build adapter masuk via PHP 8.4 lalu re-exec 8.1. PHP curl/OpenSSL/sodium/posix, pcntl untuk fixture; OpenSSL CLI/TAR/Git untuk fixture/verifikasi; PHPStan 1.12.27 baseline nol dan OSV Scanner 2.5.1 sesuai lock, snapshot offline maksimum 48 jam. Tidak mengganti dependency/lock/compiler atau memperluas runtime contract. Tidak ada signed manifest/TAR customer baru yang dipublish; hash release/TAR final baru dihasilkan dan diverifikasi oleh build Control pada cutoff yang disetujui.
