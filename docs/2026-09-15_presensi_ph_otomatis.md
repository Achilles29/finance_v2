# Presensi PH otomatis — perbaikan Batch 260

2026-09-15. Status **CODE / TEST SINTETIS PASS; UAT staging belum dilakukan**. Tidak ada SQL baru atau perubahan database/config melalui transport Telegram.

## Penyebab yang terbukti di kode

- `My_portal_model::ensure_auto_ph_presence()` mengecek `method_exists($CI, 'load')`. Pada CodeIgniter, `load` adalah properti controller yang berisi loader, bukan method. Guard menolak sebelum pemeriksaan saldo/hak PH sehingga menampilkan “Layanan validasi PH belum tersedia”, meskipun model Attendance tersedia.
- Kesalahan yang sama ada pada `recompute_daily()`: sinkronisasi GRANT/USE PH dilewati pada jalur presensi biasa. Ini juga diperbaiki, tanpa menjalankan rekonsiliasi/backfill data lama.
- Controller `My::attendance()` sudah memanggil auto PH untuk pegawai terpilih dan tanggal hari ini. Tidak perlu endpoint/cron atau tombol baru. Scope identitas pegawai dan hak akses tidak diubah.

## Perubahan

- [x] Periksa properti loader dan callable `model()`, lalu tetap gunakan validasi PH existing. Tidak melewati hak PH, tanggal efektif, saldo/masa berlaku, pemakaian/reservasi, maupun aturan kontrak kompensasi aktif yang sudah ada.
- [x] Jadwal PH/PHB otomatis membuat `att_daily` HOLIDAY dan satu ledger USE saat membuka `/my/attendance`. Tidak membuat event GPS palsu atau GRANT baru hanya karena memakai PH. Pengaturan gaji, snapshot kontrak dan MONTHLY/CUSTOM uang makan dipertahankan.
- [x] Refresh menemukan presensi/penggunaan yang sudah ada: tidak menggandakan pemakaian jatah, tidak menghitung ulang gaji lama. UI membedakan hasil benar-benar tercatat dari sekadar tidak ada perubahan. Catatan existing non-PH/status lain tidak ditimpa atau ditampilkan sebagai sukses PH.
- [x] Hasil insert dan status transaksi diperiksa sebelum commit; kegagalan simpan presensi/perhitungan gaji/ledger/commit membatalkan pencatatan PH baru pada pengujian.
- [x] UI menampilkan status PH otomatis. Tidak meminta tombol Check-in/Check-out, lokasi atau GPS pada shift PH otomatis. Form dan geofence shift kerja biasa dipertahankan. Geolocation tidak dipanggil bila tidak ada form presensi.
- [x] Tes regresi `attendance-auto-ph` masuk required quality gate agar bug ini tidak hanya terjaga oleh pengecekan teks kontrak.

File: `application/models/My_portal_model.php`, `application/views/my/attendance.php`, `tools/tests/attendance_auto_ph_smoke.php`, quality gate + contract, roadmap `_30`/`_28`, execution log; laporan akuntansi hanya pembaruan konfirmasi SQL 15b.

## Validasi

- `php tools/tests/attendance_auto_ph_smoke.php`: **58 PASS**. Memakai model portal/Attendance nyata dan schema baseline yang diadaptasi ke SQLite `:memory:`. Kompensasi memakai adapter sintetis, tidak membaca kontrak pegawai sebenarnya.
- Cakupan: reproduksi bentuk loader CI, PH/PHB, tanpa lokasi/GPS, refresh, hak tidak aktif/belum efektif, saldo nol/pecahan/habis, grant belum efektif/kedaluwarsa/valid sampai hari ini, kontrak tidak aktif, non-PH/OFF/tanpa jadwal, existing status lain, rollback insert/salary/USE/commit, GRANT kerja libur nasional dan retry, render status/error/escaping serta form reguler.
- A4 People/Payroll/Attendance contract **253 PASS**, payroll meal-mode **7 PASS**, accounting **346 PASS**, client accounting **38 PASS**, quality gate contract **28 PASS**, roadmap consistency **26 PASS**. PHP lint dan diff whitespace diperiksa.
- Belum UAT browser/staging, validasi MariaDB atau konkurensi dua request aktual. SQLite tidak membuktikan perilaku lock/FK MariaDB. Tidak membuka endpoint presensi live karena membukanya dapat menulis kehadiran; transport ini melarang perubahan DB oleh agent.

## Cara mencoba

1. Login sebagai pegawai yang memiliki jadwal PH/PHB hari ini, hak PH efektif, saldo yang masih berlaku dan kontrak aktif sesuai aturan existing.
2. Buka **Absensi Saya** (`/my/attendance`). Harus muncul **Presensi PH sudah tercatat** tanpa mengaktifkan GPS atau menekan check-in.
3. Periksa riwayat kehadiran dan penggunaan PH. Refresh halaman: tetap satu kehadiran dan satu penggunaan jatah untuk tanggal tersebut.
4. Bila saldo/hak tidak memenuhi syarat, pesan menjelaskan penyebab bisnis, bukan kesalahan pemeriksaan loader. Bila sudah ada catatan status/shift lain, admin meninjau lewat modul presensi; jangan mengganti data historis secara otomatis.

Tidak ada SQL baru untuk perbaikan ini. SQL jurnal 15b dicatat **USER_REPORTED_APPLIED** berdasarkan konfirmasi pengguna; bukan verifikasi schema/izin atau alasan mengulang SQL. Pekerjaan berikutnya terkait jurnal tetap merujuk laporan akuntansi dan checklist `_30`, bukan mengaktifkan posting otomatis diam-diam pada batch PH ini.
