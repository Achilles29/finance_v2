# Verifikasi Rincian Pengajuan Divisi

Halaman create/edit memakai tujuh kolom dan berubah menjadi kartu pada layar
sempit. Tabel pencarian memakai enam kolom. Divisi dan lokasi pada daftar nota
maupun daftar rincian digabungkan.

Purchase memutuskan setiap rincian melalui modal pada kolom Aksi:

- Verifikasi: sesuaikan merk, spesifikasi, jumlah, harga, vendor dan pemakaian;
  tinjau stok dan lengkapi konfirmasi jika diperlukan. Simpan langsung membentuk
  SR/PO untuk rincian tersebut, mempertahankan ID rincian dan dokumen lainnya.
- Tolak: alasan wajib, tanpa membentuk SR/PO. Rincian lain tetap menunggu.
- Buka ulang: rincian ditolak kembali menunggu, dengan alasan dan identitas aktor.
  Rincian terverifikasi tidak dapat ditolak, dihapus, atau diproses ulang.

Status nota SUBMITTED selama ada rincian PENDING. Setelah semua diputuskan,
status VERIFIED jika ada rincian disetujui, atau REJECTED jika semuanya ditolak.
Jumlah keputusan pada daftar menjelaskan nota dengan hasil campuran. Membuka
ulang rincian ditolak mengembalikan nota ke SUBMITTED.

## Database

Jalankan `sql/2026-09-23c_division_request_line_review.sql` saat deployment.
Migrasi ini sudah diterapkan pada instance kerja tanggal 23 September 2026,
setelah backup empat tabel pengajuan di
`/var/backups/finance-division-review-20260923145221`.

Migrasi menambah harga estimasi, status/aktor/waktu/alasan keputusan pada rincian,
serta relasi rincian pada dokumen dan bukti pemeriksaan stok. Keunikan bukti stok
menjadi per request + rincian. Bukti lama memakai request_line_id=0 dan tetap
berlaku bagi dokumen lama. Tidak mengubah saldo atau membuat dokumen operasional.

## Verifikasi

- `php tools/tests/procurement_stock_review_verify_smoke.php`: transaksi parsial,
  klik ulang, salah nota, kegagalan PO, penolakan, buka ulang, bukti per dokumen.
- `node tools/tests/procurement_stock_browser.cjs`: Chrome, kesesuaian kolom,
  mobile, modal satu rincian, perubahan harga/merk dan konfirmasi stok.
- `node tools/tests/procurement_stock_review_client_smoke.cjs`: perubahan isian,
  respons terlambat, timeout dan validasi bukti stok.

Pengujian memakai SQLite dan respons jaringan simulasi; tidak membuat transaksi
uji pada database operasional.
