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
