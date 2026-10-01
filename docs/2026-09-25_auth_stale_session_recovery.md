# Pemulihan otomatis sesi web yang tidak valid

Tanggal: 25 September 2026. Status: implementasi dan regresi terisolasi lulus; konfirmasi browser pengguna masih diperlukan.

## Masalah dan solusi

- Setelah database disalin/dipulihkan, file sesi PHP lama dapat tetap menyimpan `session_log_id` yang sudah tidak ada. INSERT audit halaman kemudian gagal FK 1452. `try/catch` saja tidak cukup karena CodeIgniter dapat menghentikan request ketika `db_debug` aktif.
- Sebelum controller terautentikasi menjalankan aksinya, periksa bahwa session log ada, sesuai akun, belum logout, dan akun masih aktif. SUPERADMIN juga diperiksa.
- Sesi tidak valid: hanya sesi request tersebut diakhiri; halaman biasa mendapat HTTP 303 ke `login?reason=session_expired`. Halaman login menampilkan pesan tetap yang mudah dipahami. POST lama tidak diteruskan sebagai POST login.
- AJAX/permintaan JSON mendapat HTTP 401, kode `AUTH_SESSION_EXPIRED`, dan `login_url`; aksi controller tidak diteruskan.
- Jika query validasi gagal, hentikan request dengan HTTP 503 dan pesan aman, tetapi jangan menghapus sesi. Gangguan pemeriksaan tidak disamakan dengan sesi kedaluwarsa.
- Perekaman audit mematikan `db_debug` hanya selama operasi tersebut dan selalu mengembalikannya. Jika FK gagal akibat parent hilang sesudah pemeriksaan awal, validasi ulang sesi. Kegagalan audit lainnya tidak otomatis mengeluarkan pengguna yang sah.
- FK, log lama, transaksi, database dan konfigurasi aktif tidak diubah. Tidak ada SQL/migrasi yang perlu dijalankan. Tidak ada penghapusan massal sesi.

## File

- `application/core/MY_Controller.php`: validasi sesi sebelum aksi, penolakan aman, penanganan kegagalan audit.
- `application/controllers/Auth.php`: pesan login ulang; parameter URL tidak ditampilkan mentah.
- `tools/tests/auth_stale_session_smoke.php`: regresi baru, 48 pemeriksaan.
- `tools/tests/auth_division_scope_smoke.php`: fixture sesi autentikasi sah; aturan/ekspektasi scope tetap.
- `tools/tests/finance_quality_gate.php` dan `finance_quality_gate_contract_smoke.php`: regresi sesi masuk daftar gate wajib.

## Validasi aktual

PHP 8.1.32 (`/www/server/php/81/bin/php`). Seluruh enam file PHP yang berubah lulus `php -l`; `git diff --check` lulus.

Dua belas suite terarah lulus, memakai source asli dan fixture in-memory/source checks, tanpa database aplikasi atau file sesi pengguna:

1. `auth_stale_session_smoke.php` — 48 pemeriksaan; sebelum patch 30 gagal, sesudah patch semuanya lulus.
2. `auth_division_scope_smoke.php` — 42 pemeriksaan.
3. `auth_inactive_role_permission_smoke.php`.
4. `auth_login_throttle_smoke.php` — 74 pemeriksaan.
5. `activity_audit_smoke.php` — 12 pemeriksaan.
6. `a3_page_alias_registry_smoke.php`.
7. `whatsapp_group_command_service_auth_smoke.php` — 32 pemeriksaan.
8. `whatsapp_engine_log_boundary_smoke.php` — 24 pemeriksaan.
9. `whatsapp_api_schedule_run_cli_smoke.php` — 30 pemeriksaan.
10. `finance_quality_gate_contract_smoke.php` — 28 pemeriksaan.
11. `pos_mobile_authorization_smoke.php`.
12. `pos_mobile_login_throttle_smoke.php` — 6 pemeriksaan.

Regresi baru meliputi sesi hilang, salah pemilik, sudah logout, akun nonaktif, ID sesi kosong, SUPERADMIN, AJAX/JSON POST, query gagal/exception, race penghapusan parent, audit gagal, audit sekali per halaman, pemulihan `db_debug`, pengecualian CLI/service yang sudah ada, dan pesan login tanpa refleksi input URL.

Batas bukti: ini bukan pengujian browser end-to-end atau seluruh quality gate aplikasi. Tidak mengakses/mengubah instalasi produksi maupun Control; tidak commit/push/deploy. Pemeriksaan ini juga bukan jaminan mendeteksi setiap kemungkinan benturan ID akibat restore database.

## Cek singkat pengguna

- [ ] Refresh dashboard yang sebelumnya menampilkan FK 1452: diarahkan ke login dengan pesan sesi tidak berlaku.
- [ ] Login ulang: dashboard terbuka dan navigasi berikutnya tetap berjalan.
- [ ] Sesi perangkat lain yang masih sah tidak ikut diakhiri.
- [ ] Permintaan dengan sesi tidak sah tidak menjalankan perubahan data; login lalu ulangi tindakan secara sadar.

Tidak perlu menghapus cookie secara manual atau mencari tombol logout di halaman error.
