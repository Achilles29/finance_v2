# Modul Reservasi POS

## Tujuan

Reservasi adalah tempat mencatat pesanan customer sebelum benar-benar
dikerjakan oleh POS. Reservasi menyimpan jadwal, customer, produk, extra,
total tagihan, dan DP. Reservasi belum mengurangi stok dan belum mengirim KOT.
Stok, HPP, penjualan, void, refund, serta cetak baru mengikuti order POS setelah
kasir menerima reservasi.

Harga jual yang telah disetujui di reservasi tetap dipertahankan saat diterima.
Sebaliknya, HPP produk dan biaya extra dihitung ulang dari stok aktif outlet
tepat ketika kasir memilih `Terima ke POS`. Dengan begitu laporan margin memakai
biaya yang berlaku saat pesanan benar-benar menjadi transaksi POS, tanpa
mengubah tagihan yang sudah dijanjikan kepada customer.

## Alur Operator

1. Superadmin, Management, HOD, Admin, atau Barista membuka `POS > Reservasi`.
2. Buat reservasi, isi customer, jadwal, outlet, tipe layanan, meja, produk,
   extra, dan catatan.
   - Outlet otomatis mengikuti outlet sesi kasir aktif bila pengguna sedang
     membuka kasir; bila tidak ada sesi kasir, sistem memilih outlet aktif
     pertama dan operator tetap dapat menggantinya.
   - Perkiraan selesai otomatis diisi empat jam setelah waktu mulai. Operator
     dapat menggantinya bila acara membutuhkan waktu berbeda.
   - Saat produk dipilih, layar pilihan extra dan jumlah produk langsung
     dibuka terlebih dahulu, sama seperti pola kasir POS.
3. Bila customer membayar DP, pilih metode pembayaran dan masukkan nominalnya.
   DP langsung masuk ke mutasi rekening melalui mekanisme deposit POS yang
   sama dengan halaman kasir.
4. Kasir pada outlet yang sama membuka rincian reservasi dan memilih
   `Terima ke POS`.
5. Bila DP belum menutup tagihan, order masuk ke order aktif kasir. Kasir
   menerima pelunasan sisa seperti transaksi biasa.
6. Bila DP sudah menutup tagihan, order langsung masuk ke pesanan terbayar.
7. Saat diterima, tiket produksi dan struk mengikuti aturan printer yang sudah
   aktif. Tiket produksi dikirim sesuai divisi; struk hanya dicetak bila
   transaksi sudah lunas.

## Pengaman DP

- DP tidak boleh melebihi total tagihan reservasi.
- DP sebagian ditampung dalam satu dokumen pembayaran sementara. Saat kasir
  melunasi, dokumen yang sama diselesaikan sehingga penjualan dan DP tidak
  tercatat dua kali.
- DP penuh langsung menjadi pelunasan order POS.
- Reservasi yang masih menunggu dapat ditolak atau dibatalkan. Operator dapat
  memilih mengembalikan DP ke rekening asal atau membiarkannya sebagai kredit
  customer untuk transaksi berikutnya.
- Sesudah reservasi masuk POS, pembatalan dilakukan melalui void atau refund
  POS biasa agar stok dan keuangan tetap berpasangan.

## Tab Halaman

- `Per Transaksi`: memantau reservasi per customer.
  - `Aktif`: reservasi mendatang dan order POS aktif.
  - `Selesai`: lunas, ditolak, dibatalkan, atau order akhirnya selesai.
  - `Sudah Lewat`: jadwal yang sudah melewati waktunya tetapi belum selesai.
- `Rincian Produk`: melihat kebutuhan produk, divisi produksi, extra, dan
  nilai per line untuk membantu BAR/KITCHEN menghitung persiapan.

## Hak Akses

Halaman memakai page code `pos.reservation.index`. Hak penuh diberikan untuk
`SUPERADMIN`, `CEO`, `MGR`, `ADMIN`, `HOD`, dan `BARISTA`.

Membuat, mengubah, menambah DP, menolak, atau membatalkan reservasi tidak
memerlukan sesi kasir. Sistem mencatat akun login pembuat/pengubah pada jejak
reservasi dan mutasi rekening. Ini memungkinkan reservasi dicatat lebih awal
oleh staf berhak.

Hanya **Terima ke POS** yang membutuhkan pegawai kasir dengan sesi kasir aktif
di outlet yang sama. Pada tahap itu order, shift, kasir, stok, dan printer baru
diikat sebagai transaksi POS resmi.

## Pemasangan

Jalankan migration berikut pada setiap database aplikasi yang dipakai:

```sql
sql/2026-08-28a_pos_reservation_module_foundation.sql
sql/2026-08-28b_pos_reservation_creator_audit_and_cashier_independence.sql
```

Sesudah migration selesai, muat ulang halaman atau login ulang agar menu dan
permission baru terbaca dari sidebar.

## Pengujian Manual

1. Buat reservasi tanpa DP, lalu terima dari kasir pada outlet yang sama.
   Pastikan order masuk ke order aktif POS dan tiket BAR/KITCHEN mengikuti
   aturan cetak.
2. Buat reservasi dengan DP sebagian. Terima reservasi, lalu lunasi dari
   kasir. Pastikan DP hanya dilaporkan satu kali, pembayaran akhir satu kali,
   dan total order tidak ganda.
3. Buat reservasi dengan DP sama dengan total tagihan. Terima reservasi dan
   pastikan order masuk ke pesanan terbayar serta struk mengikuti aturan
   printer.
4. Buat reservasi dengan DP, lalu tolak dengan pilihan pengembalian DP.
   Pastikan payment deposit menjadi void dan mutasi rekening lawan tercatat.
5. Buat reservasi dengan DP, lalu tolak tanpa pengembalian DP. Pastikan DP
   tetap menjadi kredit member dan tidak lagi terikat sebagai reservasi aktif.
6. Dengan akun yang memiliki hak Reservasi tetapi tanpa membuka sesi kasir,
   buat reservasi dan DP. Pastikan berhasil serta riwayat reservasi menampilkan
   akun pembuat. Lalu coba `Terima ke POS`: sistem harus meminta sesi kasir
   aktif, bukan membuat order diam-diam.
7. Buka modal tambah pada layar sempit dan lebar. Pastikan outlet sudah terisi,
   akhir jadwal empat jam setelah mulai, tombol tambah produk terlihat, dan
   extra wajib tidak dapat dilompati.
8. Isi pencarian reservasi atau katalog, lalu pilih `Clear`. Pastikan nilai
   pencarian kembali kosong dan daftar kembali memuat data tanpa filter.
9. Buat reservasi, kemudian lakukan perubahan biaya/stok melalui transaksi
   resmi sebelum kasir menekan `Terima ke POS`. Pastikan total tagihan tetap
   seperti saat reservasi dibuat, sedangkan HPP pada order/laporan penjualan
   mengikuti nilai stok aktif pada saat verifikasi kasir.
