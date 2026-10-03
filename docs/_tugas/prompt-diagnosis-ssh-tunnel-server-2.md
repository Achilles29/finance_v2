# Prompt Diagnosis SSH Tunnel Server 2

Salin seluruh prompt berikut ke Codex yang berjalan langsung di Server 2.

```text
Perbaiki masalah SSH tunnel di Server 2, pada aplikasi /www/wwwroot/finance. Jangan mengubah database, data bisnis, password, atau konfigurasi Server 1.

Konteks:
- /dbtools menampilkan error saat klik "Mulai Tunnel": HTTP 502 HTML dari Cloudflare, "pos.namuacoffee.com Host Error".
- Host key Server 1 sudah berhasil dipindai dan dipercaya.
- Konfigurasi yang dimaksud: DB host core.namuacoffee.com; SSH host vs.namuacoffee.com:22; user SSH root; Server 2 diakses lewat SSH port 2222; forward lokal 127.0.0.1:3307 ke 127.0.0.1:3306 di Server 1; database aplikasi db_finance.
- Kode perbaikan/checkpoint terakhir ada di commit 30e2ffd pada finance_v2. Tombol "Cek Status Tunnel" seharusnya menampilkan "Checkpoint start terakhir".
- Koneksi langsung ke vs:3306 sebelumnya tidak terjangkau. Jangan membuka MariaDB ke internet atau mengganti tunnel dengan koneksi DB publik tanpa pembatasan firewall yang aman.

Kerjakan berurutan:
1. Periksa git status, branch, dan apakah commit 30e2ffd sudah terpasang. Jangan menimpa perubahan lokal yang belum di-commit.
2. Buka /dbtools, klik "Cek Status Tunnel", catat checkpoint terakhir. Jangan tampilkan atau menyalin private key, password, token, atau isi .env.
3. Cocokkan request dbtools/action/tunnel-start dengan route/controller yang benar-benar dilayani host aplikasi pos.namuacoffee.com. Pastikan vhost, document root, site_url()/canonical URL, dan PHP-FPM pool memang menunjuk ke /www/wwwroot/finance, bukan aplikasi/folder lain.
4. Periksa log Nginx dan PHP-FPM pada waktu percobaan terbaru (UTC 2026-10-03 sekitar 10:47 dan percobaan sesudahnya). Cari fatal error, timeout, worker reset, atau FastCGI upstream error untuk tunnel-start. Laporkan file log dan baris relevan tanpa membocorkan secret.
5. Periksa dari PHP-FPM yang melayani aplikasi: versi PHP, apakah proc_open tersedia, open_basedir, izin user aplikasi pada direktori state tunnel, dan keberadaan /usr/bin/ssh. Jangan mengubah izin secara luas atau memakai chmod 777.
6. Audit endpoint action_tunnel_start dan helper _tunnel_start_process. Uji secara aman dengan timeout; jangan meninggalkan tunnel ganda atau proses SSH yatim. Verifikasi status port lokal 3307 dan ControlMaster sebelum/sesudah uji.
7. Berdasarkan bukti log/checkpoint, perbaiki penyebab sebenarnya. Jika masalahnya PHP-FPM timeout, permission, proses background, atau route ke vhost yang salah, perbaiki hanya komponen terkait. Jangan sekadar menambah timeout besar atau menonaktifkan keamanan.
8. Jalankan php -l untuk file PHP yang diubah, smoke test DB Tools, dan git diff --check. Laporkan root cause, perubahan, tes, serta apakah tunnel benar-benar aktif. Jangan mengklaim berhasil jika belum terverifikasi.
```

## Hasil eksekusi Server 2 — 3 Oktober 2026

- Basis kode: branch `main`, HEAD `7be522b`; commit `30e2ffd` sudah menjadi ancestor. Worktree bersih sebelum perbaikan; perubahan diagnosis ini belum di-commit/push.
- Pengaturan tersimpan (SELECT saja) sesuai prompt: SLAVE, tunnel aktif dalam pengaturan, SSH `vs.namuacoffee.com:22`, akun `root`, lokal `127.0.0.1:3307` menuju `127.0.0.1:3306`, database `db_finance`. **Toggle aktif tidak berarti proses tunnel sudah berjalan.**
- Vhost `/www/server/panel/vhost/nginx/pos.namuacoffee.com.conf:7` menunjuk `/www/wwwroot/finance`; include PHP 8.1 memakai `/tmp/php-cgi-81.sock`. Route `dbtools/action/tunnel-start` menuju `System_tools::action_tunnel_start`. Resolver canonical URL pada FPM menghasilkan `https://pos.namuacoffee.com/`.
- Checkpoint asli sebelum/sesudah diagnosis tetap `ssh_start_failed`, `2026-10-03T18:13:39+07:00`. Dibaca langsung dari state privat yang juga dipakai tombol status; **tidak mengklaim telah mengklik UI** karena tidak tersedia sesi browser admin terautentikasi.

### Penyebab dan bukti

1. **Autentikasi SSH ditolak Server 1.** Log privat `ssh-start.stderr` berisi `Permission denied (publickey,password)`. Uji helper yang sama melalui FPM nyata, memakai key/trust yang sudah ada dan tanpa perintah remote, menghasilkan exit `255` dalam 0,3 detik. Ini bukan bukti pasti key belum terdaftar: kebijakan akun/SSH Server 1 juga perlu diperiksa adminnya. Mempercayai host key tidak otomatis mengizinkan client key.
2. **Kesalahan SSH dilaporkan sebagai HTTP 502 oleh aplikasi**, sehingga pesan JSON dapat terganti halaman error gateway. Vhost memiliki `error_page 502 /502.html` (baris 20), dan `/www/server/nginx/conf/nginx.conf:51` mengaktifkan `fastcgi_intercept_errors`. Tidak perlu menaikkan timeout atau mengubah konfigurasi global.
3. `/www/wwwlogs/pos.namuacoffee.com.log:499473`: `03/Oct/2026:17:47:04 +0700`, `POST /dbtools/action/tunnel-start`, status `502` (10:47:04 UTC). Baris `501429`: `18:13:39 +0700`, endpoint sama, status `502`. Tidak ditemukan catatan timeout/reset/FastCGI fatal terkait percobaan tersebut di `/www/wwwlogs/pos.namuacoffee.com.error.log` maupun `/www/server/php/81/var/log/php-fpm.log`.
4. Probe privat langsung ke socket FPM: PHP `8.1.32`, SAPI `fpm-fcgi`, user `www` (uid 1001), `proc_open` tersedia, `open_basedir` kosong, SSH executable, state readable/writable, key dan known_hosts readable. State `0700`, key/checkpoint `0600`. Tidak mengubah permission state asli atau pengaturan FPM.

### Perubahan dan pengujian

- `application/controllers/System_tools.php`: kegagalan operasi start/listener/stop tidak lagi memakai 502; start memakai JSON `422` dengan kode alasan yang di-allowlist, tanpa raw output SSH. Checkpoint menyimpan kode alasan; status juga mengenali kegagalan autentikasi pada checkpoint versi lama tanpa menulis ulang bukti tersebut. Timeout start tetap 15 detik, exit timeout `124`, pembacaan output dibatasi 8 KiB.
- `application/views/system/dbtools.php`: membedakan host key dan client public key; status menampilkan alasan kegagalan terakhir serta langkah berikutnya. RBAC, CSRF, pinned host key, private key dan bind loopback tetap dipertahankan.
- `tools/tests/dbtools_tunnel_failure_runtime_smoke.php`: **35 pemeriksaan lulus**, memakai state sementara, adapter CI tanpa DB, proses anak nyata, dan endpoint SSH loopback sementara. Mencakup klasifikasi/redaksi error, HTTP 422, RBAC/CSRF, port terpakai tidak membuka tunnel kedua, checkpoint lama/rusak, exit code, timeout dan child process selesai tanpa yatim.
- Tes existing: SSH contract **21**, mutation CSRF **74**, sensitive read **18** lulus. Total **148 pemeriksaan**. `php -l` tiga file PHP, pemeriksaan sintaks JavaScript inline dengan Node, serta `git diff --check` lulus.
- Setelah perubahan, metode respons error diuji lagi pada **FPM nyata** dengan hasil SSH yang direkam probe: `Status: 422`, `Content-Type: application/json`, `ok:false`, `code:SSH_AUTH_REJECTED`, pesan pemulihan Indonesia. Ini bukan klaim uji klik browser end-to-end atau keberhasilan autentikasi remote.
- Port 3307 dan ControlMaster diperiksa sebelum/sesudah: **keduanya tidak aktif**, tidak ada proses client SSH tertinggal. Probe sementara berada di luar document root dan dihapus setelah selesai; key/trust/log/checkpoint asli dipertahankan. Tidak ada perubahan database, password, konfigurasi server, maupun Server 1; tidak menjalankan restart replication/initial sync.

### Tindak lanjut yang masih diperlukan

1. Di Server 2 buka `/dbtools` → **Cek Status Tunnel**; kini alasan autentikasi ditolak dapat terbaca dari checkpoint lama.
2. Klik **Buat / Tampilkan Public Key** untuk menampilkan key yang sudah ada, bukan membuat pengganti. Administrator Server 1 perlu memeriksa baris terbatas `authorized_keys` untuk akun SSH `root` pada `vs.namuacoffee.com:22`, kecocokan public key, izin file, serta kebijakan SSH/forwarding. **Tidak dikerjakan dari diagnosis ini**, sesuai larangan mengubah Server 1.
3. Setelah admin mengonfirmasi akses key sudah benar, klik **Mulai Tunnel**, lalu **Cek Status Tunnel**. Listener dan ControlMaster harus sama-sama aktif. Jangan membuka port database publik atau mematikan verifikasi host key.
4. Tunnel aktif belum membuktikan database/posisi binlog siap direplikasi. Verifikasi kesiapan snapshot dan replikasi secara terpisah sebelum menekan **Hubungkan ke server utama**.
