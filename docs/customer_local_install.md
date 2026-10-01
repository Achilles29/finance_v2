# Instalasi customer: satu konfigurasi lokal

Berlaku mulai **0.1.0-alpha.16 / CUSTOMER_CLEAN v5 / FINANCE_CUSTOMER_LOCAL_V1**.
Paket alpha.15 tidak mempunyai kontrak ini. Dokumen ini bukan izin publish/aktivasi.

## File yang Anda isi

Di dalam folder Finance, salin `config/customer.example.json` menjadi
`config/customer.json`. Hanya file kedua yang diisi. Jangan mengedit
`application/config/database.php`, `constants.php`, atau environment PHP-FPM.
Jangan mengunggah customer.json ke Git, chat, tiket dukungan, atau paket distribusi.

Contoh berikut **bukan credential siap pakai**:

```json
{
  "schema": 1,
  "database": {
    "host": "127.0.0.1",
    "port": 3306,
    "socket": "",
    "name": "kedai_finance",
    "user": "kedai_app",
    "password": "REPLACE_DATABASE_PASSWORD"
  },
  "base_url": "https://kasir.contoh.com/",
  "encryption_key": "REPLACE_WITH_RANDOM_64_HEX_CHARACTERS",
  "runtime": {
    "directory": "../finance-runtime",
    "session_cookie": "kedai_finance_session"
  }
}
```

- Database: buat database **baru dan kosong** serta user khusus yang diberi akses
  hanya ke database tersebut. Nama database hanya huruf/angka/underscore, maksimal
  64 karakter. Jangan menunjuk database aplikasi yang sudah berjalan.
- `127.0.0.1` berarti koneksi TCP; port harus sesuai MariaDB customer. Untuk socket,
  isi host `localhost` **dan** socket absolut yang benar. `localhost` tanpa socket
  ditolak supaya CLI dan web tidak memilih server berbeda lewat default sistem.
- Password boleh mengandung tanda baca, tetapi bukan newline/NUL. Gunakan editor
  JSON yang benar; backslash dan tanda kutip harus di-escape sebagai JSON.
- URL adalah pilihan customer, HTTPS pada root domain, dengan `/` di akhir.
  Instalasi subpath seperti `/finance/` belum didukung installer ini. Domain Control
  bukan pengunci dan tidak menggantikan URL ini.
- Encryption key: buat unik, misalnya `openssl rand -hex 32`, lalu tempel hasilnya
  langsung ke editor privat. Jangan mengganti key pada instalasi yang sudah dipakai
  tanpa rencana migrasi data terenkripsi.
- Runtime berada **di luar DocumentRoot**. `../finance-runtime` dihitung relatif
  dari lokasi Finance, bukan dari direktori terminal. Bisa memakai path absolut
  yang aman. Jangan memakai folder bersama antar-customer atau menyimpan agent key
  di dalam Finance. Session/log/cache dibuat oleh installer.

## Urutan dari paket sampai login

1. Gunakan Linux, PHP CLI dan PHP-FPM **8.1**, MariaDB **10.11**, ekstensi yang diminta
   app-manifest, Composer, nginx atau Apache. Siapkan DNS, sertifikat HTTPS, database
   kosong, user database, dan akun service web non-root (aaPanel biasanya `www`).
2. Ambil paket **dan manifest/signature/trust terverifikasi** melalui alur delivery
   Control. Jangan sekadar mengunduh TAR dari sumber tak dikenal. Jalankan tahap
   `stage` installer terverifikasi; tahap ini memverifikasi signature, hash TAR,
   inventory, profil dan cutoff sebelum mengekstrak. Jangan extract manual lalu
   langsung membuka situs ke publik.
3. Untuk kontrak lokal, file job privat `finance_instance.php` yang disiapkan
   deployment Control memakai `"configuration_source":"customer_local"`.
   Hilangkan tiga field lama `deployment_file`, `defaults_extra_file`,
   `database_name_file`. Field teknis lain tetap diperlukan: release_root,
   signed_manifest, trust_file, private_dir, runtime_dir, user/group, binary
   PHP-FPM/nginx, TLS, Composer, owner_file, license_public_dir, instance_id, mode.
   `runtime_dir` harus sama dengan hasil resolusi `runtime.directory` customer.
   File job ini **tidak berisi ulang password database**. Control perlu mendukung
   kontrak v5 sebelum membuat job baru; jangan mengarang token atau endpoint.
4. Setelah `stage`, buat dan isi `config/customer.json`. Contoh perintah, sesuaikan
   folder instalasi dan group service Anda:

   ```bash
   cd /opt/customer/finance
   sudo install -o root -g www -m 0640 config/customer.example.json config/customer.json
   sudoedit config/customer.json
   sudo chown root:www config/customer.json
   sudo chmod 0640 config/customer.json
   sudo chmod 0755 config
   php tools/install/customer_config.php check
   ```

   Jangan menjalankan `install ... customer.json` di atas jika file sudah ada: itu
   langkah pembuatan pertama, bukan reset. Root/source/parent harus root-owned,
   tanpa group/world-write dan tanpa symlink; `customer.json` 0600 (CLI saja) atau
   0640 (dibaca group web). File harus bisa dibaca akun PHP-FPM. **Tidak boleh 777**.
   `check` hanya memeriksa konfigurasi, bukan bukti database/instalasi siap.
5. Di konfigurasi **site** nginx/aaPanel, pasang snippet
   `tools/install/nginx_customer_security.conf.example` di dalam `server {}`.
   DocumentRoot harus folder Finance, bukan parentnya. Installer LinuxWebProfile
   sudah menghasilkan aturan tersebut secara otomatis. Pada site custom, jalankan
   `nginx -t` dan reload melalui admin setelah review. Jangan menambah `env[...]`
   database. Apache 2.4 harus mengizinkan `.htaccess` dengan `AllowOverride All`
   pada DocumentRoot; root/config .htaccess memblokir `/config` dan listing.
   Pada Apache `AllowOverride None`, admin wajib memasang `<Directory
   "/opt/customer/finance/config">Require all denied</Directory>` serta
   `Options -Indexes` pada vhost. Jangan membuka situs sebelum aturan aktif.
6. Siapkan akun owner pertama melalui `owner_file` privat di luar webroot, mode
   0600, dengan field `username`, `email`, `password` (minimal 12 karakter, 3 kelas
   karakter). Ini akun login aplikasi, **berbeda** dari user database.
7. Identitas agent/kunci privat dibuat oleh alur `finance_license.php init` yang
   sudah ada. Kunci privat tetap root-only di luar aplikasi; public trust/cache
   root-owned read-only untuk web. Instance/token/aktivasi/kuota server tetap wajib.
   Installer menghasilkan context bertanda-bukti-release di
   `runtime/customer-installation.json`; jangan membuat atau mengeditnya manual.
8. Jalankan installer memakai job privat dari Control:

   ```bash
   php tools/install/finance_instance.php install --config=/opt/customer/private/instance.json
   php tools/install/finance_instance.php start --config=/opt/customer/private/instance.json
   php tools/install/finance_instance.php health --config=/opt/customer/private/instance.json
   ```

   `stage/install` **tidak** menjalankan semua folder sql. Hanya baseline terverifikasi
   dan migration plan `clean_install` terdaftar, kemudian owner bootstrap dan health.
   `start` profile uji hanya listener loopback; konfigurasi publik, TLS dan DNS
   dipasang admin pada site customer setelah pengecekan. Perintah ini bukan deploy
   otomatis ke server utama.
9. Bila hanya perlu tahap database setelah extract terverifikasi (belum deployment
   web/aktivasi), gunakan konfigurasi yang sama tanpa file credential tambahan:

   ```bash
   php tools/install/clean_install_database.php apply \
     --release-root=/opt/customer/finance \
     --signed-manifest=/opt/customer/private/finance.release.json \
     --trust-file=/opt/customer/private/release-trust.json \
     --owner-file=/opt/customer/private/owner.json
   ```

   **Pilih** alur penuh langkah 8 atau tahap database mandiri ini, jangan keduanya
   terhadap DB yang sama. Tahap mandiri tidak menerbitkan context lisensi, memasang
   runtime web atau mengaktifkan customer; deployment terverifikasi tetap diperlukan.
10. Periksa `https://DOMAIN/config`, `/config/`, `/config/customer.json` dan
    `/config/customer.example.json`: harus 403/404, tidak boleh JSON atau directory
    listing. Selesaikan aktivasi melalui Control. `/login` menerima akun owner
    langkah 6; akses bisnis baru terbuka dengan lease ACTIVE/GRACE terverifikasi.
    Health yang sukses mensyaratkan lisensi valid, DB benar, versi/hash cocok dan
    halaman login HTTPS nyata. Uji login owner lalu pengaturan identitas usaha.

## Prioritas dan pemulihan

- **Ada customer.json:** seluruh field wajib berasal dari file itu; environment
  atau file eksternal yang mendefinisikan field sama dengan nilai berbeda membuat
  `CUSTOMER_CONFIG_SOURCE_CONFLICT`. Nilai identik diperbolehkan. Bahkan konflik
  file eksternal yang tertutup override environment tetap ditolak.
  Client MySQL installer menggunakan `--defaults-file` sementara yang dibuat dari
  konfigurasi lokal: setting `/etc/my.cnf` atau `~/.my.cnf` tidak dapat mengganti
  endpoint/user/password diam-diam. Perilaku MySQL legacy tidak diubah.
- **Tidak ada customer.json:** instalasi lama masih menggunakan environment di atas
  FINANCE_DEPLOYMENT_FILE; fallback PHP staging hanya untuk source development tanpa
  pilihan DB eksplisit. Paket customer tanpa context terverifikasi tidak menjadi
  instalasi LEGACY hanya karena selector dihapus. Konfigurasi rusak tidak pernah
  memilih DB staging secara diam-diam.
- Password salah/nonempty DB berhenti. Betulkan input dan ulangi preflight sebelum
  ada SQL. Jika SQL pernah dimulai, simpan
  `runtime/.installer/database-attempt.json`, journal instance, log dan tabel yang
  sudah terbentuk. Status STARTED berarti DDL mungkin sudah commit. Jangan hapus
  journal, jangan impor ulang baseline, jangan otomatis DROP/TRUNCATE.
  Admin memeriksa migration ledger dan backup, lalu menggunakan target baru yang
  benar-benar kosong untuk percobaan baru atau prosedur recovery terverifikasi.
- Error web sengaja tidak menampilkan password/JSON/stack trace. Jalankan `check`
  melalui CLI untuk kode diagnosis aman. Kesalahan URL memerlukan perbaikan DNS/TLS
  lokal, bukan perubahan domain registry lisensi.
- Heartbeat masih memakai credential monitoring privat melalui
  FINANCE_HEARTBEAT_CONFIG_FILE (cron, **bukan** konfigurasi database PHP-FPM).
  DB/URL/context customer otomatis berasal dari customer.json. Jangan memilih
  heartbeat master. Kunci monitoring/agent tidak disimpan dalam customer.json.
- Backuplah customer.json secara terenkripsi/privat dan pisahkan dari TAR release.
  Upgrade ke folder baru harus menyalin file lokal secara sadar, memvalidasi target
  database/runtime, dan menghasilkan context release baru. Tidak menyalin kunci
  agent ke source atau menonaktifkan verifikasi core.
