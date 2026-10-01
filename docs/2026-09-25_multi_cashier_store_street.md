# Multi-kasir Store dan Street Coffee

Status 25 September 2026: implementasi dan pengujian terisolasi lulus. Tidak mengubah database aktif, terminal, akun, transaksi, konfigurasi koneksi, APK, atau Control. Tidak ada migrasi SQL yang harus dijalankan.

## Cara menggunakan

1. Di **POS → Outlet + Terminal** (`/pos/outlets-terminals`), siapkan dua terminal aktif: STORE dan STREET-COFFEE. Gunakan satu outlet jika keduanya bagian dari operasional/stok yang sama.
2. Siapkan dua akun kasir dengan izin POS, masing-masing terhubung ke **pegawai berbeda**. Dua username untuk pegawai yang sama tetap memakai satu sesi kasir.
3. Login melalui dua perangkat atau profil browser berbeda. Kasir Store memilih terminal STORE, kasir Street memilih STREET-COFFEE; masing-masing memasukkan modal awal yang benar-benar dipegang.
4. Terminal yang sedang digunakan tetap terlihat dengan nama kasir, tetapi tidak bisa dipilih. Jika semuanya sibuk, daftarkan terminal tambahan atau muat ulang setelah terminal dibebaskan.
5. Lihat `/pos/reports/daily-sales`: pilih tanggal dan outlet, atau Semua Outlet. Penjualan seluruh sesi tergabung tanpa memindahkan/menggabungkan transaksi.
6. Tutup masing-masing sesi dan masukkan uang aktual milik sesi tersebut. Menutup Store tidak menutup Street. Rincian penutupan ada di `/pos/reports/cashier-close`.

Web dan APK **sebagai cadangan pegawai yang sama** tetap melanjutkan sesi yang sama sesuai binding perangkat yang sudah berlaku. Dua kasir bekerja bersamaan memakai pegawai dan terminal berbeda.

## Perubahan dan alasan

- `application/models/Pos_model.php`: pembukaan baru memakai transaction dan row lock pegawai → outlet → terminal, lalu memeriksa ulang sesi pegawai/terminal di dalam transaksi. Lock outlet menjaga penomoran shift untuk pembukaan paralel. Terminal nonaktif/salah outlet ditolak. Retry pegawai yang sama tidak membuat sesi baru. Kegagalan parsial rollback; debug DB dipulihkan, pesan SQL mentah tidak dikirim ke kasir.
- `application/controllers/Pos.php`: kode penolakan pembukaan terminal diteruskan pada respons JSON, tanpa mengubah RBAC/CSRF/recon gate.
- `application/views/pos/cashier_index.php`: terminal sibuk ditandai dengan nama kasir dan disabled; default memilih terminal tersedia, bukan nomor terkecil yang sibuk. Tombol buka dilindungi dari klik ganda; ada petunjuk ketika semua terminal sibuk.
- `application/models/Pos_report_model.php`: sesi OPEN dihitung dari transaksi saat laporan dimuat, bukan snapshot kosong yang baru terisi saat tutup. Tambah identitas outlet/terminal dan cakup sesi yang melewati tengah malam. Sesi CLOSED tetap memakai snapshot lama; tidak memperbarui penutupan historis.
- `Pos_model::calculate_shift_summary`: order VOID tidak lagi menjadi penjualan/jumlah transaksi saat tutup kasir; nominal void tetap berada dalam rincian void. Konsisten dengan ringkasan sesi OPEN. Tidak melakukan repair snapshot/data lama.
- `application/views/pos/report_daily_sales.php` dan `report_daily_sales_print.php`: tampilkan outlet/terminal dan tanggal-jam sesi pada UI/PDF. Jelaskan beda ringkasan seluruh durasi sesi (termasuk DP mengikuti pola laporan existing) dengan total harian berdasarkan tanggal transaksi. Ringkasan sesi bukan angka yang boleh dijumlahkan ulang sebagai total harian.
- Dua suite multi-kasir baru dan registrasi gate/kontrak: `tools/tests/pos_multi_cashier_smoke.php`, `pos_multi_cashier_ui_smoke.cjs`, `finance_quality_gate.php`, `finance_quality_gate_contract_smoke.php`.

## Bukti uji aktual

- PHP 8.1.32; seluruh 9 PHP berubah lulus lint, `git diff --check` lulus.
- **85 pemeriksaan PASS** melalui `php tools/tests/pos_multi_cashier_smoke.php --mariadb`: MariaDB 10.11.10, baseline DDL terkait, data sintetis, server baru socket-only tanpa TCP. Tidak membaca credential/config/database aplikasi. Foreign key aktif untuk semua penulisan data uji. Dua proses PHP benar-benar bertemu di lock sebelum dilepas, bukan sekadar mock berurutan.
- Kasus paralel: dua pegawai/terminal berhasil dengan shift berbeda; dua pegawai berebut satu terminal hanya satu berhasil; satu pegawai membuka dua terminal menghasilkan satu sesi; retry/backup web–APK tetap sesi asli.
- Kasus negatif: terminal nonaktif, beda outlet, modal negatif, kegagalan INSERT sesi setelah shift dibuat (injeksi error pada trigger database sementara), rollback tanpa shift yatim, pesan aman, dan pemulihan db_debug.
- Laporan/tutup: dua transaksi 200.000 + 75.000 terbaca 275.000, dua payment line per pembayaran tidak menggandakan order, refund 10.000 menjadikan net harian 265.000, VOID tidak dihitung, dan tutup Store mempertahankan Street OPEN. Sesi lintas hari dan filter outlet turut diuji.
- **11 pemeriksaan perilaku JS PASS**: terminal sibuk, default/filter outlet, semua sibuk, klik ganda, retry gagal, batal gate, dan reload berhasil.
- Regresi PASS: mobile cashier binding 34; UI daftar kasir 9; otorisasi mobile; Daily Sales/PDF/notifikasi 52 + JS 10 (tanpa kirim pesan); urutan penjualan 18; reversal tanpa stok 45; step-up reversal 54; FIFO/retur stok in-memory 45; quality gate contract 28.
- Dua kegagalan awal berasal dari fixture: kolom `line_no` payment line wajib belum diisi dan antrean Promise lintas VM belum ditunggu. Fixture dilengkapi; tidak melonggarkan pengaman aplikasi. Uji final seluruhnya lulus. Hasil akhir disposable: `/tmp/finance-multi-cashier-m24s8B`; proses database sudah dihentikan, diagnostik dipertahankan.

Menjalankan ulang:

```sh
php tools/tests/pos_multi_cashier_smoke.php
php tools/tests/pos_multi_cashier_smoke.php --mariadb
node tools/tests/pos_multi_cashier_ui_smoke.cjs
```

Mode `--mariadb` membutuhkan PHP mysqli/posix, `mktemp`, dan toolchain MariaDB pada `/www/server/mysql`. Tanpa argumen hanya pemeriksaan kontrak source; jangan menyamakan itu dengan bukti concurrency database. Gate wajib mendaftarkan kontrak source dan perilaku JS; uji MariaDB dijalankan eksplisit pada batch ini.

## Batas dan UAT

Pengujian ini mencakup metode model produksi terhadap database sintetis serta fixture laporan/payment/refund, bukan checkout lengkap dari browser atau APK fisik. Tidak ada uji printer fisik, stress beban besar, atau seluruh quality gate/build release. Ketentuan stok, pembayaran, lisensi dan perangkat tidak diganti. Lock memakai tabel InnoDB sesuai baseline; tidak memperbaiki sesi duplikat historis secara diam-diam. Sesi CLOSED lama tetap menyimpan snapshot sebelumnya.

- [ ] Kasir A buka Store; kasir B buka Street tanpa menutup A.
- [ ] Kedua kasir membuat transaksi dan membayar; struk mengacu transaksi yang tepat.
- [ ] Daily Sales/UI/PDF menampilkan gabungan dan identitas kedua terminal.
- [ ] Tutup Store; Street tetap dapat membuat transaksi dan kemudian tutup sendiri.
- [ ] Uji void/refund sesuai hak kasir, periksa kas dan stok kembali pada transaksi asal.
- [ ] Pergantian web ke APK kasir yang sama tetap memakai sesi existing; bukan membuat sesi kedua.

Batch berikutnya: UAT dua perangkat. Belum commit/push/deploy; paket customer resmi tetap melalui build/review Control, bukan menimpa file paket bertanda tangan.
