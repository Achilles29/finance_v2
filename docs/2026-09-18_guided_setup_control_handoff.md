# Handoff Control — guided customer setup alpha.18 / v7

18 September 2026, Asia/Jakarta. **IMPLEMENTASI FINANCE; BUKAN IZIN PUBLISH/AKTIVASI LIVE**.
Cutoff implementasi: **`9ca2d389b060e43c6ac61d77d6a94eda9b1d557a`**. Commit dokumentasi sesudahnya hanya mencatat bukti uji; build harus menggunakan cutoff implementasi ini, bukan otomatis HEAD terbaru. Tidak di-push dari thread ini.

## 1. Penyebab alpha.17 dan perubahan produk

Alpha.17 hanya mengetahui apakah `storage/setup/browser.json` sudah dibuat melalui persiapan pendamping. ZIP lengkap saja belum membuat akun terpisah, permission privat, key unik atau jadwal. Pesan tersebut tidak selalu kegagalan lisensi. Jalur lama meminta permission helper, `check`, `prepare`, dan cron manual, sementara panduan masih meminta menyalin delivery yang sekarang sudah ada dalam ZIP Control.

Revisi menyediakan satu entry point Linux, wizard empat tahap, pemeriksaan kesiapan, uji DB metadata-only melalui pendamping, ringkasan, password toggle, progres/resume dan login. Root hanya menyiapkan layanan setelah konfirmasi; website dan installer selalu akun non-root terpisah. Persiapan memasang tiga jadwal dan menunggu bukti scheduler, bukan memanggil worker sendiri lalu mengklaim cron sehat.

## 2. Versi, profil, batas

- Baru: `0.1.0-alpha.18`, `CUSTOMER_CLEAN` **7**, `FINANCE_GUIDED_SETUP_V1`.
- Tetap: layout `FINANCE_SINGLE_FOLDER_V1`, config `FINANCE_CUSTOMER_LOCAL_V1` schema 1, permit `NAMUA_FINANCE_SETUP_V1`, browser bootstrap `FINANCE_SETUP_BROWSER_V1`.
- DB schema `finance-20260907`, baseline `clean-install-20260909`, baseline + 20 migrasi terdaftar **tidak berubah**. Tidak ada SQL baru untuk database development/produksi.
- SHA-256 app-manifest: `7bd0b4936adce76f5179a5dd1851ae70193371e5107fa213494b0e0ead8a479f`.
- SHA-256 customer_clean_profile: `75814d13059c3c448e24f0cfe22e925ff6a8c4814d06ee30cc5a38e1c80e5898`.
- Jangan menimpa alpha.17/v6 atau menyisipkan installer baru ke paket signed lama. Tidak memasang ke core2, menerbitkan deployment, memakai credential live, atau mengubah source/database Control.
- Parent awal repository `7e25749b5a6b7f981fae4427c78846eb19f63c69`; perubahan pengguna di config inti tetap dipertahankan.

## 3. Struktur dan jalur customer

ZIP tetap satu folder `finance/`: `public/`, `application/`, `system/`, `config/`, `storage/`, `private/`, `installer/`, `tools/install/portable/`, docs. Document root **public**, bukan parent. `private/delivery` sudah disertakan Control; customer tidak membuat file claim/permit/credential sendiri.

Admin dari folder hasil ekstrak menjalankan:

```sh
sudo sh tools/install/portable/prepare.sh
```

Perintah meminta akun PHP web jika ambigu, URL opsional, lalu konfirmasi `SIAP`. Default akun pendamping unik per path, dibuat hanya jika disetujui; akun existing harus terpisah dan sesuai grup. Hanya folder instalasi yang disentuh. Parent unsafe ditolak, bukan di-chown rekursif. Tidak menginstal PHP/MariaDB, mengganti vhost/global services atau mengakses DB.

Customer kemudian `/setup` → kode setup dari ZIP → DB/URL/admin → Uji koneksi & database kosong → ringkasan/konfirmasi → Pasang dan aktifkan → progres → login. URL awal ditawarkan dari origin browser HTTPS; authoritative URL tetap isian lokal tervalidasi, bukan domain registry atau HTTP_HOST server. `localhost` ambigu ditolak dengan petunjuk TCP `127.0.0.1`.

Config tersimpan `config/customer.json`; tidak ada pengisian ulang file inti/FPM. Resolver preview, installer, CLI dan aplikasi sama; konflik env/file eksternal lama ditolak. Admin password/DB password tidak masuk argv, URL, log publik, ringkasan, Control, atau paket Git.

## 4. Keamanan dan pemulihan

- Root preflight khusus read-only memeriksa signed release/permit/TAR/inventory sebelum perbaikan ownership; pengecualian ini hanya CLI root, tidak melemahkan CustomerPlatform global. Verifikasi diulang sebagai worker non-root sesudahnya.
- Queue web hanya ciphertext sodium, id unik terikat payload dan permit; publish atomik, maksimum 8 antrean/4 per tick. Probe berhasil terikat digest config+permit selama 15 menit; SQL memeriksa DB kosong lagi di bawah lock.
- Akun web tidak dapat membaca private agent/credential atau menulis code/license cache. Pengarsipan queue membuat inode milik pendamping 0600, bukan chmod inode milik web.
- Root helper idempotent, tiga cron ditandai per folder, jadwal lain dipertahankan. `tick`, `license-sync`, `heartbeat` terpisah; kedua sinkronisasi dibatasi 300 detik masing-masing. Heartbeat tidak mengaktifkan lisensi.
- Setup complete terkunci. Hilangnya local config/context tidak menjadi legacy/development. Signature, core/profile/release, plan/deployment/instance/credential binding, fingerprint, kuota, nonce dan kunci agent tetap diperiksa.
- Izin sementara expired menolak langkah baru, bukan mengakhiri hak memasang. Control menerbitkan pengganti terikat percobaan yang sama. Bukti lama diarsipkan; tidak mengganti identity/key atau DB. Tidak ada endpoint auto-renew baru yang diasumsikan.
- Receipt/health dapat dilanjutkan dari journal yang sama setelah koneksi terputus. `RUNNING` DDL yang belum pasti berhenti untuk review; tidak otomatis DROP/TRUNCATE/ulang SQL. Pengaturan hanya bisa dikoreksi lewat UI sebelum journal SQL ada.
- Pengecualian mutable tetap exact `config/customer.json`, runtime storage/private, serta upload di prefix yang telah ditetapkan sesudah complete. Bukan pengecualian semua kode/folder aplikasi. Jangan menjanjikan PHP di server customer mustahil dibypass.

## 5. Perubahan wajib di Control (belum dilakukan thread Finance)

Audit read-only menemukan `Finance_setup_packet::payload()` saat ini **hardcode profile 6**. `Finance_setup_packet::bundle()` sudah mampu membuat ZIP yang diperlukan dan dipakai langsung dalam fixture tanpa mengubah Control. Permit tetap ditandatangani dengan context yang sama; tidak ada field/signing scheme/endpoints baru.

1. Review cutoff alpha.18, manifest/profile baru, tambahan `setup_contract`, dan pin hash toolchain sebelum mengizinkan v7. Jangan melonggarkan trust secara generik untuk semua versi.
2. Issuer/verifier permit menggunakan profile **dari release terverifikasi yang sama** (v7 untuk alpha.18), bukan konstanta 6. Tetap kompatibel delivery lama v6 menurut kebijakan Control. Credential hash, expiry dan seluruh binding harus persis.
3. Portal/delivery text `MULAI-DI-SINI.html` dan petunjuk unduh mengganti prosedur manual dengan satu perintah + `/setup`; pertahankan `KODE-SETUP.txt` dan enam berkas delivery. Kode akses pengiriman, setup dan admin password harus dibedakan.
4. Regresi reissue menggunakan workflow Control yang sudah ada, tidak menimpa ZIP ke instalasi parsial. UI Control harus menjelaskan berkas izin/credential pengganti yang tepat. Jangan membuat deadline berdasarkan pembelian/build/release/instance.
5. Dari checkout cutoff bersih, scan/import metadata baru, build **release baru**, jalankan delapan gate/verifier pinned serta ZIP actual; lakukan walkthrough customer dan tes real-Control di lingkungan trial sesuai persetujuan. Jangan publish otomatis dari thread Finance.

Endpoints lama tidak berubah: `/api/v1/license-activations`, `/status` dan `/recover` di bawah prefix yang sama, `/api/v1/deployment-receipts`, `/api/v1/heartbeats`. Tidak ada customer DB/password atau transaksi dikirim.

## 6. File/dependensi yang perlu direview

Runtime berubah: `CustomerLocalConfig`, `DeploymentConfig`, `Control_license_cache`, `ControlDelivery`, `CustomerReleaseProfile`, `PortablePackage/Installer/Database/Store`, `SetupUi`, `finance_setup.php`, `setup.php`, `layout.json`, manifest/profil. Baru dan **allowlisted**: `LinuxPreparation.php`, `SetupService.php`, `prepare.php`, `prepare.sh`, `setup.js`, `setup.css`. Customer/admin docs ikut paket.

Test baru `customer_guided_contract_smoke.php`, `customer_guided_acceptance.php`, `customer_guided_browser.cjs`, `customer_guided_control_fixture.php`; fixture/test issuer **tidak ikut paket**. Portable/config/clean-release dan quality-gate tests disesuaikan; guided/portable contract masuk gate required.

Tidak mengganti dependency/lockfile/global runtime. Runtime lokal: PHP CLI/FPM 8.1.32, MariaDB 10.11.10, nginx, sodium/cURL/PDO, shell POSIX, runuser, useradd, crontab. Harness tambahan: BusyBox crond dengan spool root-owned terisolasi, OpenSSL/TAR/unzip, Google Chrome 152, Node 20 melalui CDP pipe tanpa paket npm tambahan, akun existing `namua-build` dan `www`; lokasi binary dalam harness adalah spesifik lab, bukan hardcode paket customer. PHP 8.4 hanya adapter build, bukan runtime web.

Hash SHA-256 dari checkout bersih cutoff di atas (bukan hash TAR rilis/persetujuan trust baru):

```text
d65573093dec23cf23b9e7f81598adcd115dcc7f6c8c994d0e9c4dc6bf695901  application/libraries/CustomerPlatform.php
f17b5c0d65dc4602c60da3a3314a913dca2bbc03f9b5085d4ff340ab54edf721  application/libraries/CustomerLocalConfig.php
f3e7c4279aea267e6e4d0ea6798def96af43ebd14fe7f4823727008afe941a6d  application/libraries/DeploymentConfig.php
43e59d37ef1096586c19d07a13281da67375e3ea5e931ed6b6e27a94f102dba5  application/libraries/Control_license_cache.php
3a61000795299788a5353cc106e579ef1a89c7011e5fb459ce1be2646437aba5  tools/release/CustomerReleaseProfile.php
ba72bd528b0fb6be9077193539efbfd5e9aefe7a9b1961626d4c149b7a10db35  tools/install/ControlDelivery.php
f05cad274167b34ab98c32bd152b5a593d0203879da1d4705c2f52826ab7a2d8  tools/install/portable/LinuxPreparation.php
ff5120f41194d12723703d50d91a87ce11a89913d49d917175f92a6fa8ba7cce  tools/install/portable/PortableInstaller.php
ba8c83774ef446efdc3f4f7d1427baed1dee436d330168a5317cbcd8e227da68  tools/install/portable/PortablePackage.php
ac9f187433aa1cc5e103c4447322807b52f983f6b250e4694c59f3e593c4abae  tools/install/portable/PortableDatabase.php
0862c451c6eff88b9e4761343dd88fa72e591114bc415fbbf22d13d2cf5b7634  tools/install/portable/PortableStore.php
f562473f343cf62b7ff1a548e0960266cb658d6dd287234cb4fea1b0a7c26bdf  tools/install/portable/SetupService.php
0b58fa9765caff9c18f6439351e2d879c818caf9e3e763b87d370851100005d5  tools/install/portable/SetupUi.php
487cb467957776fbd59db6d5393c142c4ac6656e16edb8a2e42f502da3c1ef75  tools/install/portable/finance_setup.php
82533602c5473ef52c396227f997f2bf2548ffe41c9c9ff3d20c20cb8b757f95  tools/install/portable/prepare.php
d9ecb15b52d80ecfc1bc294eceac168af669f621d571e1600d6175b0b7c3face  tools/install/portable/prepare.sh
af8fb3e2822e58636ecbea7c80422f46fbfea4240f9195d4d70c08c45afb46ff  tools/install/portable/setup.php
152ca8c0029e6accc73dc35f0ab406591d076022e48003a946a073ff4c50f15d  tools/install/portable/setup.js
d00f65dcf722d8050611454eb736f07921b450e8599daeb9a8d2f212549b8334  tools/install/portable/setup.css
```

`CustomerPlatform.php` dicantumkan sebagai dependency pengamanan yang tetap, bukan file yang diubah batch ini. Untuk inventaris perubahan lengkap gunakan `git diff --name-status 7e25749b5a6b7f981fae4427c78846eb19f63c69 9ca2d389b060e43c6ac61d77d6a94eda9b1d557a`. Hash manifest/profil ada pada bagian 2; validator/build dependencies lain harus tetap mengikuti pin Control yang direview, jangan diganti otomatis berdasarkan daftar di atas.

## 7. Bukti dan keterbatasan

Putaran working-tree 18 September 2026, 19:31 WIB: **47 guided acceptance PASS**, **61 portable acceptance PASS**. Build awal delapan gate + verifier independen PASS: 1.170 file, 306 tabel, 20 migrasi, 735 referensi, 0 customer/dummy/secret; backup–restore checksum cocok. Angka awal ini bukan bukti cutoff final; pengulangan checkout bersih dicatat tersendiri di bawah. Bedakan tiga lapisan:

- Uji source/fixture fungsi dan config, tidak membuktikan layanan OS atau Control live.
- Linux disposable: ZIP dari class bundler Control aktual, scheduler benar-benar berjalan, nginx/FPM/DB/Chrome nyata; issuer/HTTP Control **sintetis**, bukan aktivasi/publish live.
- Release trial di Control dan customer asli **belum dilakukan**. Windows nyata/Apache/IIS belum acceptance. Pembuatan akun OS baru dan crontab sistem host tidak diuji dengan mengubah host bersama; fixture memakai akun existing dan scheduler terisolasi. Walkthrough manusia nonprogrammer masih diperlukan.

### Pengulangan dari cutoff implementasi bersih

18 September 2026, 19:48 WIB: checkout detached `9ca2d389b060e43c6ac61d77d6a94eda9b1d557a`, **delapan gate dan verifier independen PASS / exit 0**. Harness membangun snapshot terisolasi dari source cutoff; snapshot ini sengaja bukan release yang bisa diterbitkan.

- 1.170 file masuk / 337 dikecualikan; 735 referensi sistem, 0 dummy, 0 temuan data customer, 0 secret.
- Clean install: 306 tabel diperiksa, 293 tabel nonreferensi kosong, 20 migrasi diterapkan / 0 dilewati.
- Backup–restore: 306 tabel, checksum logis cocok; health OK, 1 owner aktif.
- Hash dump fixture: `123d1c9a96d77fc456dc4dff3ce9c46a7789f9f54eb6905af41a4c4c2d86f07a`.
- Hash inner manifest fixture: `815132dddc29e702e327a3fd92d3e9cf7249c0a385d7ed38cf076e11ed977f80`.
- Gate: `source_clean`, `security_scan`, `install_test`, `backup_restore`, `customer_data_scan`, `secrets_scan`, `clean_install`, `source_untouched`.
- `source_database_accessed=false`, `signed_or_published=false`, `ISOLATED_INTEGRATION_FIXTURE_NOT_RELEASE`. Hash fixture **bukan** hash TAR final yang nanti dibentuk/disetujui Control.
- Regresi pada checkout yang sama: guided contract 23, local config 60, portable contract 42, customer-clean release 118, delivery 27, verifier 26, agent 72, legacy deployment config 39, quality gate contract 28, roadmap consistency 26 PASS. PHP lint 26 file berubah, JS syntax dan shell syntax PASS.

Guided acceptance dari checkout yang sama: **47 PASS / exit 0**, termasuk tambahan reload browser lalu memeriksa ID percobaan yang sama. Bukti alurnya:

| Kelompok | Hasil nyata di fixture Linux |
|---|---|
| ZIP → persiapan | ZIP dibuat class bundler Control aktual read-only, diekstrak, satu entry point shell dijalankan; konfirmasi BATAL tidak mengubah identitas/jadwal. |
| Idempotensi dan jadwal | Persiapan dua kali mempertahankan key/identitas dan tepat tiga cron; ketiganya benar-benar dipanggil BusyBox crond terisolasi. Tidak ada SQL saat persiapan. |
| UI dan database | Chrome mobile 390 px, kode setup, toggle password, probe, ringkasan, pasang, reload/resume, lalu login admin nyata. Password salah, database belum ada, isian tidak lengkap dan DB berisi ditolak; tabel lama tetap ada. |
| Gangguan dan pemulihan | Control HTTPS sintetis sengaja menolak ack receipt pertama setelah DB selesai; scheduler mengirim ulang percobaan yang sama. Aktivasi satu kali, receipt dua kali, hash journal DB tidak berubah. |
| Proteksi | Izin expired/binding salah/core berubah/parent unsafe ditolak; HTTP privat/source/config diblokir; pengunjung tanpa kode ditolak; web tidak bisa membaca key atau menulis cache lisensi; core dimodifikasi menghasilkan 423. |
| Setelah selesai | Setup ditolak untuk pemakaian ulang; poll lisensi dan heartbeat terjadwal terpisah benar-benar mencapai endpoint HTTPS sintetis. |

Perintah fixture menggunakan entry point `sh tools/install/portable/prepare.sh` yang sama, dengan opsi akun existing dan lokasi spool **khusus lab** agar tidak mengubah akun/jadwal host bersama. Ini membuktikan helper + scheduler berjalan, bukan bukti pengujian pembuatan akun baru atau semua implementasi cron distro. Semua proses/database test dibersihkan oleh harness. Checkout sementara dapat dibuat ulang dari commit, bukan backup/data pengguna yang dihapus.

Perintah lab (bukan perintah untuk server customer/produksi):

```sh
php tools/tests/customer_guided_contract_smoke.php
php tools/tests/customer_local_config_smoke.php
php tools/tests/customer_portable_contract_smoke.php
php tools/tests/customer_portable_acceptance.php --disposable
php tools/tests/customer_guided_acceptance.php --disposable
php tools/tests/c3_customer_clean_release_smoke.php
php tools/tests/c3_control_build_runtime_smoke.php --isolated
```

Jangan menandai “siap praktik pada alpha.17”. Finance v7 perlu review/build pengiriman Control baru, tanpa langkah terminal tersembunyi dalam jalur normal.
