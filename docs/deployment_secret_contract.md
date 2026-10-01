# Deployment secret contract

Batch P0-06A-1 mengambil nilai deployment dari process environment. Dokumen
ini tidak menyimpan nilai secret.

## Nama variabel kontrak

- `FINANCE_ENCRYPTION_KEY`
- `FINANCE_DB_HOST`
- `FINANCE_DB_NAME`
- `FINANCE_DB_USER`
- `FINANCE_DB_PASSWORD`

Semua variabel di atas wajib tersedia dan tidak boleh kosong atau hanya berisi
whitespace ketika `CI_ENV=production`. Development dan testing tidak
menjalankan preflight production, tetapi tetap harus diprovision sesuai
kebutuhan runtime-nya.

## Variabel opsional Telegram Bot

- `FINANCE_TELEGRAM_BOT_TOKEN`
- `FINANCE_TELEGRAM_WEBHOOK_SECRET`
- `FINANCE_TELEGRAM_WEBHOOK_URL`

Ketiganya wajib tersedia pada proses PHP-FPM dan CLI/cron hanya bila modul
Telegram dipakai. Token berasal dari BotFather. Webhook secret harus berupa
nilai acak yang berbeda dari token bot dan dikirim Telegram melalui header
`X-Telegram-Bot-Api-Secret-Token`; jangan menyimpan keduanya di tabel, source,
URL, atau log. `FINANCE_TELEGRAM_WEBHOOK_URL` bukan secret, tetapi wajib berupa
URL HTTPS publik kanonis yang lengkap dan berakhir dengan `/telegram_webhook`.
Nilainya ditetapkan admin server dan tidak boleh dibentuk dari host/header
request browser.

### Pembagian pengaturan Telegram

**Dikerjakan admin server satu kali:** simpan token, webhook secret, dan URL
webhook pada environment; pastikan environment yang sama tersedia untuk
PHP-FPM serta CLI; reload layanan; lalu pasang cron
`php /www/wwwroot/finance/index.php telegram run_due` setiap menit. Nilai
credential tidak boleh diberikan melalui formulir aplikasi.

**Dikerjakan melalui UI aplikasi:** periksa identitas bot, temukan group/channel,
simpan target, kirim pesan uji, aktifkan master switch, pasang/periksa webhook,
dan atur jadwal. Setup Assistant memakai environment yang sudah disiapkan tanpa
pernah menampilkan token atau webhook secret. Hak akses UI tetap dikelola
melalui Role/Permission; worker CLI tidak menggantikan RBAC halaman.

Panduan onboarding berbahasa pengguna tersedia pada `/telegram/guide`, sedangkan
wizard operasional berada pada `/telegram/settings`. Nilai token dan secret
tetap tidak boleh dimasukkan atau ditampilkan melalui UI.

### Implementasi aaPanel pada staging Finance

Instalasi ini memakai PHP 8.1 dan menyimpan tiga nilai Telegram di luar webroot:

- file credential: `/var/lib/finance-telegram/runtime.env` (`root:root`, mode `0600`);
- whitelist pool: `/www/server/php/81/etc/php-fpm.conf`;
- pemuat environment: `/etc/init.d/php-fpm-81`;
- wrapper scheduler: `/usr/local/sbin/finance-telegram-run-due`;
- jadwal: `/etc/cron.d/finance-telegram`;
- log worker: `/var/log/finance-telegram-worker.log`.

Isi file credential adalah tiga baris `NAMA_VARIABEL=nilai`. Untuk menghindari
credential tertinggal di shell history, buka file menggunakan editor server dan
tempel nilai di sana; jangan menaruh token pada argumen command. Uji syntax lalu
restart penuh PHP-FPM agar master process menerima environment baru:

```bash
set -a
. /var/lib/finance-telegram/runtime.env
set +a
/www/server/php/81/sbin/php-fpm -t --fpm-config /www/server/php/81/etc/php-fpm.conf
/etc/init.d/php-fpm-81 restart
```

Panduan UI memuat contoh langkah aaPanel yang lebih rinci tanpa memuat nilai
credential aktual.

Notifikasi penyelesaian Codex memakai hook user-level
`/usr/local/sbin/finance-codex-notify`. Implementasi yang dilacak repository
berada di `tools/telegram/codex_notify.php`. Bot hanya mengirim jawaban akhir
Codex sebagai ringkasan; prompt pengguna dan output tool tidak diteruskan.
Ringkasan dibatasi panjangnya, blok kode serta URL dibuang, dan pola token,
password, secret, serta API key disamarkan sebelum dikirim ke grup.

## File privat untuk migration dan probe database

Tool migration/probe memakai dua file berbeda di luar repository, keduanya
regular non-symlink, hanya dapat dibaca operator, dan bermode tepat `0600`:

- `--defaults-extra-file=/secure/client.cnf` untuk host/user/password client;
- `--database-name-file=/secure/database.name` untuk satu identifier database
  `[A-Za-z0-9_]{1,64}` dengan maksimal satu line ending.

Jangan mengandalkan `database=` di defaults file karena MariaDB client tidak
memilih database dari nilai tersebut. Contoh deployment managed migration:

```bash
php tools/db/migration_runner.php apply --policy=upgrade \
  --defaults-extra-file=/secure/client.cnf \
  --database-name-file=/secure/database.name
```

Jangan menaruh password atau nama database sebagai argumen bebas, dan jangan
menjalankan seluruh folder `sql/` secara manual.

## Bootstrap owner pada instalasi baru

Bagian ini hanya untuk database customer baru yang masih kosong. Jangan
menjalankan `2026-09-05d_a5_clean_install_reference_seed.sql` secara manual pada
staging atau server utama yang sudah beroperasi; policy `upgrade` sengaja tidak
memasukkan seed tersebut.

Siapkan `/var/lib/finance-install/owner.json` melalui editor server, kemudian
ubah permission menjadi `0600`. Isinya tepat tiga field berikut; nilai password
ditulis pada baris setelah nama field agar contoh ini tidak dianggap credential
siap pakai:

```json
{
  "username": "owner",
  "email": "owner@customer.example",
  "password":
    "<ISI PASSWORD KUAT 12-72 KARAKTER>"
}
```

Jalankan schema baseline, migration policy `clean_install`, lalu bootstrap:

```bash
mysql --defaults-extra-file=/secure/client.cnf DATABASE_BARU \
  < sql/baseline/2026-09-05_clean_install_schema.sql
php tools/db/migration_runner.php apply --policy=clean_install \
  --defaults-extra-file=/secure/client.cnf \
  --database-name-file=/secure/database.name
php tools/db/bootstrap_first_owner.php apply \
  --defaults-extra-file=/secure/client.cnf \
  --database-name-file=/secure/database.name \
  --owner-file=/var/lib/finance-install/owner.json
```

Bootstrap hanya berhasil ketika belum ada user maupun assignment, role yang
diberikan selalu `SUPERADMIN`, dan percobaan kedua akan ditolak. Setelah login
pertama berhasil, hapus file `owner.json` melalui pengelola file server agar
salinan password tidak tertinggal.

## Provisioning

### Staging tanpa environment PHP-FPM

Sejak 2026-09-24 server source/master ini memakai `config/customer.json`
di dalam folder Finance, tanpa mengisi environment pool PHP-FPM:

- source `application/config/database.php` hanya menjadi loader dan tidak
  menyimpan hostname, user, password, atau nama database;
- server lama/source menggunakan `schema: 1`, `scope: "database_only"`, dan
  objek `database` (contoh: `config/server-database.example.json`);
- scope ini hanya untuk source **non-package**: mode aplikasi, URL, encryption
  key, session, dan konteks lisensi yang sudah ada tidak diganti;
- directory `config/` harus `root:WEB_GROUP` mode `0750` dan file mode `0640`;
- nginx wajib memblokir `/config` dan `/config/` sebelum file rahasia dibuat;
  Apache memakai aturan yang sudah tersedia pada `.htaccess`;
- `.user.ini` adalah file lokal, tidak dilacak Git. Nilai server ini tetap
  `open_basedir=/www/wwwroot/finance/:/tmp/`, tanpa akses ke folder eksternal;
- file privat tidak tercatat Git dan tidak masuk artefak release;
- paket customer tetap memakai konfigurasi lengkap `FINANCE_CUSTOMER_LOCAL_V1`
  dari installer. Scope database-only ditolak pada paket/installer;
- fallback PHP hardcoded `/var/lib/finance-config/database.php` **tidak lagi
  dibaca otomatis**. Salinan lama dipertahankan untuk recovery saja;
- tanpa JSON lokal, kompatibilitas environment / `FINANCE_DEPLOYMENT_FILE`
  tetap berlaku. Konflik nilai database dengan JSON lokal ditolak, tidak
  memilih koneksi lain secara diam-diam.

Jangan mengubah file PHP inti untuk mengganti koneksi. Pengaturan database
diedit hanya di `config/customer.json`. Jangan menyalin isinya ke tiket, log,
chat, atau repository. Prosedur pindah/pull antarmesin ada di
`docs/2026-09-24_konfigurasi_lokal_dan_pull_antar_server.md`.

Provision variabel melalui secret manager, container orchestrator, atau
konfigurasi process manager di luar webroot. Nilai harus masuk ke process
environment sebelum worker PHP/FPM atau proses CLI aplikasi dimulai. Jangan
menaruh secret di source repository, file yang dapat diunduh dari webroot,
URL, command line argument, atau output aplikasi.

## Prasyarat HTTPS untuk cookie production

Production harus disajikan melalui HTTPS sampai ke browser agar cookie Secure
dapat digunakan. Jika TLS dihentikan oleh reverse proxy atau load balancer,
terminator tersebut wajib meneruskan X-Forwarded-Proto: https ke aplikasi
untuk request HTTPS. Web server aplikasi harus hanya mempercayai header itu
dari terminator/proxy yang dikelola dan tidak boleh menerima nilai tersebut
secara bebas dari klien.

Jika TLS berakhir langsung di web server PHP, indikator HTTPS native seperti
HTTPS=on harus tersedia. Sebelum traffic production dialihkan, verifikasi
request HTTPS melalui jalur terminasi yang benar dan pastikan cookie tidak
diterbitkan melalui jalur HTTP biasa. Bagian ini tidak memerlukan atau
menyimpan secret.

## Urutan cutover aman

1. Identifikasi konfigurasi runtime saat ini tanpa menyalin atau mencetak
   nilainya.
2. Provision seluruh nama variabel kontrak pada target process environment.
3. Jalankan pemeriksaan kelengkapan dari target process dan health check
   DB-free sebelum menerima traffic.
4. Deploy source P0-06A-1 dan restart/reload worker agar process environment
   baru digunakan.
5. Verifikasi health check production, lalu alihkan traffic secara bertahap.
6. Pertahankan jalur rollback sampai health check dan fungsi terenkripsi
   terverifikasi.

`FINANCE_ENCRYPTION_KEY` lama harus dipertahankan sebelum rotasi. Rotasi hanya
dilakukan setelah rencana re-enkripsi/migrasi data dan invalidasi atau migrasi
state terkait selesai, serta rollback window ditutup. Tanpa langkah tersebut,
data yang dibuat dengan key lama dapat tidak dapat didekripsi dan sesi lama
dapat terdampak.

## Aturan redaksi

- Jangan mencetak nilai variabel kontrak ke log, exception, response, trace,
  screenshot, tiket, atau command history.
- Pesan kegagalan production harus generik; jangan menampilkan nama atau nilai
  konfigurasi kepada caller.
- Diagnostic internal hanya boleh menyebut status kelengkapan tanpa nilai.
- Redact nilai sebelum meneruskan output tool, process manager, atau secret
  manager ke pihak lain.
