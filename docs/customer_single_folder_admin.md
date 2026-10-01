# Admin — persiapan satu perintah Finance

Berlaku bagi kandidat **alpha.20 / CUSTOMER_CLEAN v8 / FINANCE_GUIDED_SETUP_V1**, bukan perubahan retroaktif ZIP lama. Pemasang tidak mengubah vhost, menginstal/mengganti PHP/MariaDB, atau menyalakan layanan global otomatis.

## Prasyarat yang memang tugas admin

- Linux x86-64, PHP CLI **8.1** dan PHP web 8.1; ekstensi `pdo_mysql mysqli sodium curl mbstring json openssl zip xml session`. CLI memerlukan POSIX, proc_open, dan runuser. `fileinfo` tetap kebutuhan fitur MIME WhatsApp.
- MariaDB 10.11; database kosong dan user terbatas pada database tersebut. UI tidak meminta password root dan tidak menjanjikan membuat database.
- Website HTTPS ber-root **finance/public** dan berjalan sebagai akun non-root yang berbeda dari pemilik kode/pendamping. Jangan memberi sudo kepada web.
- `crontab` tersedia dan daemon cron sudah berjalan. Root menulis jadwal **akun pendamping**, bukan menjalankan PHP sebagai root.
- Parent direktori instalasi root-owned, tidak writable grup/publik. Jangan memakai `/tmp`, home writable milik akun web, symlink, atau direktori bersama yang bisa diganti user lain. Helper tidak mengubah parent/direktori website lain.

Gunakan virtual host sesuai `tools/install/portable/nginx.conf.example`, atau Apache dengan document root public, aturan `public/.htaccess` aktif dan eksekusi skrip upload ditolak. Jangan membuat alias ke config/private/storage/source. Tidak memerlukan aaPanel. Hosting yang tidak memungkinkan pemisahan akun/scheduler belum didukung.

## Jalur normal: satu perintah

Unduh dan ekstrak **seluruh ZIP Control** ke folder baru. `private/delivery/` sudah berisi TAR asli, manifest/tanda tangan/trust, izin setup, credential pengiriman, kode setup dan petunjuk. Jangan membuat atau menyalin ulang berkas itu pada instalasi baru.

Dari folder `finance`:

```sh
sudo sh tools/install/portable/prepare.sh
```

Sudah root? Gunakan `sh tools/install/portable/prepare.sh`.

Perintah tersebut:

1. Mencari PHP CLI 8.1 yang tersedia (PATH dan beberapa lokasi umum, tidak memasang PHP).
2. Memeriksa paket, hash, signature, profil dan izin pengiriman **sebelum perubahan**.
3. Meminta akun PHP website bila perlu, serta URL HTTPS opsional. Tidak menebak akun saat ada beberapa pilihan.
4. Menampilkan folder target, PHP, akun web, pendamping dan jadwal; **wajib ketik SIAP**.
5. Bila disetujui, membuat akun sistem khusus tanpa shell login, menata izin hanya di instalasi baru, menyiapkan identitas unik, lalu memasang tiga tugas milik pendamping.
6. Menunggu bukti ketiga tugas benar-benar dipanggil scheduler, maksimal 80 detik. Kalau gagal, perintah tidak mengklaim persiapan berhasil.
7. Menampilkan alamat `/setup`. DB/URL/admin selanjutnya lewat UI; tidak perlu check/prepare/run/sync atau salin cron satu per satu.

Jika PHP pada lokasi khusus: satu entry point yang sama dapat dipanggil dengan `/lokasi/php81 tools/install/portable/prepare.php` sebagai admin. Opsi `--web-user=nama --web-group=grup --installer-user=nama --url=https://alamat/` hanya untuk admin yang mengetahui akun target; jangan gunakan nama contoh secara membabi buta. Akun pendamping existing harus berbeda dari web dan menjadi anggota grup web.

## Jadwal, izin dan menjalankan ulang

`tick`, `license-sync`, dan `heartbeat` dijadwalkan terpisah setiap menit. Sesudah selesai, poll lisensi dan heartbeat masing-masing membatasi pengiriman minimal lima menit. Heartbeat tidak memberi hak lisensi. Sebelum pemasangan selesai, keduanya menunggu.

Blok crontab bertanda hash folder diganti secara idempotent; jadwal lain dipertahankan, salinan jadwal sebelumnya disimpan privat. Menjalankan ulang perintah mempertahankan akun, identitas, activation attempt, config, SQL journal dan data. Tidak menjalankan SQL. Jika pemilik/izin instalasi lama rusak, perintah berhenti: review izin sesuai catatan awal, jangan chmod 777 atau rekursif ke parent.

Kode umumnya 0750/0640, `private/` 0700 dengan berkas 0600. Web dapat menulis hanya cache/log/session/upload serta inbox tersegel yang terbatas; tidak dapat membaca agent key atau menulis cache lisensi/kode. Semua state tetap di folder Finance; crontab OS adalah satu-satunya registrasi scheduler di luar paket.

## Troubleshooting yang terarah

| Pesan/kondisi | Tindakan |
|---|---|
| Paket belum lengkap | Ekstrak seluruh ZIP, bukan TAR saja. Jangan menimpa instalasi yang sudah mulai. |
| PHP/runtime tidak cocok | Sediakan PHP CLI/web yang sesuai pada situs ini. Helper tidak mengganti PHP situs lain. |
| Parent/permission tidak aman | Pilih parent terlindungi atau review ACL/izin folder target. Jangan membuka akses publik. |
| Layanan belum berjalan | Periksa daemon cron, kebijakan akses crontab akun pendamping, PHP CLI dan permission target. Jalankan lagi perintah yang sama. |
| Database belum ada/password salah | Customer memperbaiki melalui UI dan menguji ulang. Host `localhost` tanpa socket ambigu; gunakan `127.0.0.1` untuk TCP atau hostname server DB. |
| Izin ditolak/dicabut | Paket v8 tidak memiliki tenggat pemasangan (`permission_policy=UNTIL_USED_OR_REVOKED`, `expires_at=null`). Aktivasi tetap memeriksa hak paket, kuota, identitas server dan pencabutan di Control. Jangan ubah file bertanda tangan. Jika paket lama menampilkan EXPIRED, minta penggantian yang sah; jangan timpa instalasi yang telah disiapkan atau database. |
| Kuota server habis | Operator Control meninjau slot/izin. Jangan membuat identitas baru; server sah lain tidak diubah. |
| SQL terputus/hasil belum pasti | Simpan seluruh folder dan DB. Review journal; tidak ada replay DROP/TRUNCATE atau reset otomatis. |
| Login HTTPS belum lolos | Periksa URL, sertifikat dan document root. DB yang sudah selesai tidak perlu diulang. |
| Hasil belum diterima Control | Status dapat diperiksa ulang; pendamping mengirim receipt yang sama tanpa mengulang aktivasi/SQL. |

Sebelum SQL, UI boleh memperbaiki input setelah uji ulang; riwayat input privat diarsipkan. Setelah journal SQL ada, penggantian DB ditolak. `READY` dengan checkpoint selesai dapat dilanjutkan; `RUNNING` tak pasti harus diperiksa manual; `COMPLETE` hanya health/receipt. Jangan menghapus journal.

## Kontrak konfigurasi dan batas dukungan

`config/customer.json` schema 1 / FINANCE_CUSTOMER_LOCAL_V1, runtime literal `storage`. Resolver aplikasi, probe, dan installer sama. Environment/file eksternal lama tetap didukung; **nilai yang bertentangan dengan konfigurasi lokal ditolak**, bukan memilih database diam-diam. Kunci agent tidak ditempatkan di konfigurasi database.

Windows memiliki adaptor ACL/MachineGuid/Task Scheduler lama, tetapi **belum diuji di host Windows nyata**, dan entry point satu perintah ini khusus Linux. Jangan menjual dukungan Windows selesai atau mengganti pemeriksaan ACL dengan chmod. Apache/IIS membutuhkan acceptance lingkungan sebenarnya.

Pengamanan PHP pada server yang sepenuhnya dikuasai customer tidak dapat dijanjikan mustahil dibypass. Signature, integritas, binding, aktivasi dan kuota tetap dipertahankan; domain bukan pengunci lisensi.

Dokumen handoff untuk pengembang berada di repository Finance, terpisah dari panduan customer dalam ZIP.
