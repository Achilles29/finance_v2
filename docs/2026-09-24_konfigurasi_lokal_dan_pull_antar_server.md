# Konfigurasi lokal dan pull antarserver — 24 September 2026

## File yang diedit admin

- **Database:** `finance/config/customer.json` (lokal, diabaikan Git).
- **Pembatasan PHP:** `finance/.user.ini` (lokal, diabaikan Git).
- **Kode bersama:** `application/config/database.php` hanya pembaca konfigurasi.
- Jangan edit `database copy.php`: file salinan pengguna ini dipertahankan,
  tetapi tidak dipanggil aplikasi.

Untuk source/master/server lama, gunakan contoh `config/server-database.example.json`:

```json
{
  "schema": 1,
  "scope": "database_only",
  "database": {
    "host": "127.0.0.1",
    "port": 3306,
    "socket": "",
    "name": "REPLACE_DATABASE_NAME",
    "user": "REPLACE_DATABASE_USER",
    "password": "REPLACE_DATABASE_PASSWORD"
  }
}
```

Ganti placeholder menggunakan nilai **server tujuan**, bukan credential server lain.
Tidak perlu environment PHP-FPM. Perubahan JSON berlaku pada request berikutnya.
Scope ini tidak mengganti mode aplikasi, URL, encryption key, session, atau
state aktivasi. Paket customer **tidak boleh** menggunakan scope ini; installer
customer tetap menulis konfigurasi lengkap dari `customer.example.json`.

## Prioritas dan keamanan

1. Jika JSON lokal tersedia, nilainya divalidasi. JSON rusak/incomplete,
   permission longgar, symlink/hardlink, atau konflik environment ditolak.
2. Untuk database-only, hanya DB yang diambil dari JSON. Environment non-DB
   yang sebelumnya dipakai tetap dipertahankan.
3. Tanpa JSON lokal, urutan legacy tetap environment > JSON eksternal melalui
   `FINANCE_DEPLOYMENT_FILE`. Tidak ada lagi fallback PHP ke path server lain.
4. Paket customer tetap membutuhkan konfigurasi lengkap, identitas dan
   verifikasi lisensi. Exception permission source/master tidak berlaku
   pada paket, dan scope database-only tidak bisa digunakan oleh installer.

Folder `config/`: root:grup-web `0750`, JSON `0640`; web dapat membaca tetapi
tidak menulis. Source checkout tetap dapat memakai ACL developer. Pemeriksaan
source berada dalam root instalasi agar kompatibel dengan `open_basedir`.
Aturan ketat ancestor/akun terpisah pada paket customer tetap dipertahankan.

**Sebelum menyimpan credential**, aktifkan aturan nginx contoh
`config/nginx-deny.conf.example` dalam server block website tersebut, validasi
nginx, lalu reload konfigurasi. Pastikan `/config/` dan template memberi
403/404. Apache sudah memakai blokir pada root `.htaccess` dan `config/.htaccess`.
Paket customer tetap memakai `finance/public` sebagai document root.

## Pull pertama pada server lain — penting

Jangan langsung menarik perubahan loader sebelum menyiapkan konfigurasi lokal.

1. Simpan salinan privat `database.php` dan `.user.ini` server tujuan. Jangan
   commit, unggah ke chat, atau menaruh backup yang bisa diunduh lewat web.
2. Blokir HTTP `/config`, lalu buat `config/customer.json` berisi koneksi lokal
   server tujuan dan atur permission. Jangan memakai DB dari server ini.
3. Jika helper sudah tersedia sementara `database.php` masih konvensional,
   admin dapat menangkap nilai lama tanpa mengetik ulang credential:
   `php tools/install/capture_server_database.php www --http-protection-confirmed`.
   Ganti `www` dengan grup PHP web yang sudah diperiksa. Helper menolak file
   lokal yang sudah ada dan tidak menjalankan SQL; tidak mengubah loader.
   Jika helper belum tersedia sebelum pull, isi contoh JSON secara manual.
4. Ketika pull melepas `.user.ini` dari Git, Git dapat menghapus file terlacak
   lama. Pulihkan **salinan lokal server tujuan** setelah pull. Jangan memakai
   path contoh tanpa menggantinya. Atur jendela pemeliharaan singkat untuk
   transisi ini agar pembatasan PHP tetap benar.
5. Periksa halaman login dan target database lewat query read-only. Jangan
   menjalankan baseline/migrasi hanya untuk mengubah lokasi konfigurasi.
6. Pull berikutnya tidak lagi menimpa JSON atau `.user.ini` yang diabaikan Git.

## Yang diterapkan di server ini

- Semua opsi dan credential DB sebelum/sesudah dibandingkan: identik; hanya
  default port 3306 menjadi eksplisit. `SELECT DATABASE()` mengonfirmasi target
  sama. Tidak ada INSERT/UPDATE/DDL atau perubahan saldo/stok.
- `config/customer.json` tersedia, root:www `0640`; `config/` root:www `0750`.
- Isi `.user.ini` tidak berubah. Dilepas dari indeks Git (`D` berarti berhenti
  dilacak, **bukan** file lokal dihapus). Ignore + contoh ditambahkan.
- Aturan nginx hanya pada website `pos.namuacoffee.com`, di
  `/www/server/panel/vhost/nginx/extension/pos.namuacoffee.com/finance-local-config.conf`.
  `nginx -t` lulus, reload graceful. Website lain tidak diedit.
- Backup privat: `tmp/config-migration-20260924-104521-c89062/`, directory `0700`,
  file `0600`. Backup dan file lama di `/var/lib/finance-config/` tidak dihapus.
- Probe PHP web sementara sudah dihapus setelah dipakai; tidak menyisakan
  endpoint diagnostik baru.

## Validasi aktual

- PHP lint file terkait: lulus.
- `server_local_database_smoke.php`: 48 checks lulus, fixture sementara tanpa DB,
  termasuk helper capture yang tidak menimpa konfigurasi dan menolak input
  invalid sebelum menyentuh konfigurasi aktif.
- `customer_local_config_smoke.php`: 61 checks lulus, termasuk penolakan scope
  source pada paket dengan permission valid.
- `deployment_secret_config_smoke.php`: 39 checks lulus.
- `customer_portable_contract_smoke.php`: 49 checks lulus.
- `gap01_repository_runtime_boundary_smoke.php`: 29 checks lulus.
- `c4_control_license_verifier_smoke.php`: 26 checks lulus.
- `customer_durable_permission_smoke.php`: 39 checks lulus.
- PHP-FPM nyata: `fpm-fcgi`, environment `development`, local config terbaca,
  environment deployment tidak terpasang, konteks customer tidak berubah,
  open_basedir tidak membutuhkan `/var/lib/finance-config/`. Query hanya SELECT.
- Login HTTP 200 dengan formulir password dan tanpa warning PHP/database.
- HTTP 404 pada `/config`, `/config/`, JSON, template, variasi slash/encoding,
  serta `.user.ini`. Diuji langsung ke vhost lokal HTTP port 80. TLS lokal
  port 443 memberi sertifikat hostname berbeda; tidak diubah dan tidak
  digunakan sebagai bukti pengujian HTTPS publik.
- Tes fitur global **belum lulus**: `feature_boundary_contract_smoke.php`
  menemukan public action `procurement/division_po_sr_line_action` belum
  dipetakan. Controller/policy/tes fitur tersebut tidak diubah batch ini;
  catat sebagai tindak lanjut terpisah, bukan alasan membuka batas lisensi.

## Control / Git

Kontrak konfigurasi customer tetap `FINANCE_CUSTOMER_LOCAL_V1`, profil tetap
CUSTOMER_CLEAN v11. Scope tambahan hanya untuk source non-package. Tidak ada
endpoint/perubahan database/entitlement Control, publish atau deploy customer.
Loader, resolver dan bootstrap yang diubah sudah masuk allowlist kode customer;
build resmi berikutnya harus memakai cutoff source baru serta hash/signature
yang baru. Tidak mengedit artefak terbit. Template/helper khusus source tidak
masuk profil customer; JSON lokal dan `.user.ini` tetap ditolak paket.

Belum commit/push oleh batch ini. Pelepasan `.user.ini` sudah berada di indeks
Git; simpan dalam commit yang sama dengan ignore, loader, resolver, tes dan
panduan agar rollout tidak terpisah. Tidak menghapus secret dari histori Git
lama atau merotasi credential dalam pekerjaan ini.
