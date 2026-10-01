# Kebijakan masa sesi web

- Idle timeout CodeIgniter dinaikkan dari 12 jam (`43200`) menjadi 24 jam (`86400`). Driver file memperbarui waktu aktivitas ketika sesi dipakai; PHP garbage collection juga memakai batas yang sama.
- Rotasi session ID otomatis dikurangi dari setiap 5 menit menjadi 30 menit. ID lama tidak langsung dihapus pada rotasi berkala agar request bersamaan dari tab/AJAX yang masih membawa cookie lama tidak kehilangan data login.
- Saat login berhasil, session ID anonim pra-login tetap diregenerasi dengan penghancuran ID lama sebelum identitas autentikasi disimpan.
- Logout eksplisit tetap menandai baris `auth_session_log` sebagai logout. Pemeriksaan sesi pada tiap request menolak seluruh salinan ID rotasi yang menunjuk log login yang sama.
- Tidak ada perubahan database, SQL, atau sesi pengguna yang sedang aktif oleh patch ini. Nilai konfigurasi berlaku setelah deployment dan request baru.

Validasi: `auth_login_throttle_smoke.php` (74 pemeriksaan) dan `auth_stale_session_smoke.php` (48 pemeriksaan) lulus.
