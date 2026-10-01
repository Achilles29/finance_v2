# Antrean Ketersediaan POS dan Performa Writer Stok

## Tujuan

Dokumen ini menjelaskan perubahan agar penerimaan PO/SR, adjustment, daily
recon, dan batch produksi tidak perlu menunggu perhitungan ulang seluruh resep
produk POS yang terdampak.

Perubahan ini hanya memperbaiki cara cache menu POS diperbarui ke depan.
Tidak mengubah order, stok, lot, movement, HPP, refund, atau data historis.

## Sebelum

Saat admin menyimpan satu perubahan stok bahan atau component, aplikasi:

1. menyimpan stok, lot, dan movement;
2. langsung mencari seluruh produk POS yang memakai bahan/component tersebut;
3. menghitung ulang setiap produk itu pada setiap outlet aktif;
4. baru mengembalikan hasil ke halaman admin.

Jika satu PO, adjustment, atau batch menyentuh banyak bahan, langkah hitung
ulang ini dapat terjadi berulang. Halaman berhasil tersimpan, tetapi terasa
lambat karena admin ikut menunggu pekerjaan cache menu POS.

## Sesudah

Setelah migration dipasang, saat admin menyimpan perubahan stok:

1. stok, lot, nilai, dan movement tetap diposting seperti biasa dalam jalur
   transaksi utama;
2. produk POS yang memakai bahan/component tersebut ditandai `perlu diperbarui`;
3. sistem membuat satu antrean per kombinasi `outlet + produk`;
4. bila barang yang sama berubah berkali-kali sebelum worker bekerja, antrean
   digabung menjadi satu pekerjaan terbaru, bukan menghitung ulang berkali-kali;
5. worker menjalankan perhitungan availability dan HPP cache di belakang layar.

Jadi proses simpan PO/SR, adjustment, recon, dan batch menjadi lebih ringan,
tanpa mengorbankan jejak stok utama.

## Pengaman HPP POS

Antrean ini hanya menunda pembaruan **cache menu POS**, bukan menunda stok
atau lot.

Saat job stock commit POS memproses sebuah order, detail pemakaian resep tetap
mengambil biaya live bahan/component dari sumber stoknya. Artinya POS tidak
perlu menghitung ulang seluruh katalog saat kasir menyimpan order, tetapi HPP
di snapshot pemakaian tetap memakai biaya bahan/component yang berlaku saat
commit diproses.

Jika cache menu masih bertanda perlu diperbarui, kasir mungkin melihat status
menu beberapa saat sebelum worker menyegarkannya. Karena kebijakan bisnis
memang mengizinkan transaksi saat stok sistem tidak cukup, hal tersebut tidak
memblokir transaksi POS dan defisit tetap dicatat dengan mekanisme yang sudah
ada.

## Yang Harus Dijalankan

1. Jalankan `sql/2026-08-21e_pos_product_availability_queue.sql` di **lokal**.
2. Jalankan SQL yang sama di **server Ubuntu** sebelum kode PHP baru dipakai.
3. Deploy file PHP baru bersama-sama, jangan hanya salah satu file.
4. Jadwalkan worker pada server setiap satu menit. Untuk struktur server yang
   sedang dipakai, gunakan:

```bash
* * * * * /usr/bin/php /www/wwwroot/finance/index.php pos availability_queue_run 100
```

Jika nanti lokasi PHP atau folder aplikasi berubah, sesuaikan kedua path
tersebut. Redirect output ke file log bersifat opsional dan hanya dipasang bila
folder log server memang sudah tersedia serta dapat ditulis oleh user cron.

Perintah worker di Windows lokal:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\finance\index.php pos availability_queue_run 100
```

`pos runtime_jobs_run` yang sudah dipakai POS juga sekarang ikut mencoba
memproses antrean availability. Namun cron `availability_queue_run` tetap
disarankan karena perubahan stok bisa terjadi pada hari yang belum ada order
POS sama sekali.

## Memantau Tanpa Terminal

Setelah SQL fondasi antrean sudah dijalankan, jalankan juga
`sql/2026-08-21f_pos_availability_queue_monitor_menu_seed.sql` di lokal dan
server. File tersebut hanya menambahkan menu, halaman, dan hak akses; tidak
mengubah stok, lot, movement, HPP, order, atau kas.

Halaman baru ada di **POS > Ketersediaan POS** (`/pos/availability-queue`).
Halaman ini dipakai untuk membaca keadaan cache menu POS dengan bahasa
operasional:

1. **Menunggu** berarti perubahan stok sudah tercatat dan cache menu tinggal
   diambil oleh worker.
2. **Sedang diproses** berarti worker sedang menghitung ulang cache produk.
   Job yang benar-benar berhenti lebih dari lima menit dapat diambil worker
   berikutnya.
3. **Perlu dicek** berarti perhitungan cache gagal. Ini bukan berarti stok atau
   lot salah. Baca pesannya, perbaiki penyebabnya, lalu tekan **Ulangi Job**.
4. **Selesai hari ini** hanya menunjukkan jumlah cache produk yang telah
   berhasil diperbarui.

Tombol **Proses Sekali** hanya menjalankan sejumlah job kecil dari antrean yang
sama dengan cron. Tombol ini tidak menjalankan command Linux dari browser dan
tidak memasang atau mengubah cron. Pemisahan ini disengaja agar akun aplikasi
tidak memiliki akses berbahaya ke server.

## Cara Menguji

1. Pilih satu bahan yang dipakai produk POS.
2. Lakukan adjustment tambah/kurangi kecil atau receipt uji pada bulan aktif.
3. Pastikan halaman transaksi stok kembali lebih cepat daripada sebelumnya.
4. Jalankan worker satu kali dari terminal.
5. Buka `/pos/stock-live` lalu pastikan produk yang memakai bahan tadi sudah
   memperoleh status dan HPP cache terbaru.
6. Ulangi dua atau tiga perubahan cepat pada bahan yang sama sebelum menjalankan
   worker. Hasil akhirnya harus mengikuti perubahan terakhir, bukan dihitung
   berulang untuk setiap perubahan.
7. Buat satu order POS uji setelah itu. Pastikan stock commit tetap berhasil,
   lot tidak menjadi minus, dan bila stok tidak cukup defisit tetap tercatat.

## Memeriksa Antrean Bila Ada Kendala

```sql
SELECT
  status,
  COUNT(*) AS total,
  MIN(run_after) AS antrean_tertua,
  MAX(updated_at) AS update_terakhir
FROM pos_product_availability_queue
GROUP BY status
ORDER BY status;

SELECT
  id,
  outlet_id,
  product_id,
  status,
  revision,
  event_count,
  attempts,
  last_error,
  updated_at
FROM pos_product_availability_queue
WHERE status IN ('FAILED', 'PROCESSING')
ORDER BY updated_at DESC;
```

- `QUEUED`: menunggu worker.
- `PROCESSING`: sedang dihitung; worker yang berhenti lebih dari lima menit
  dapat diambil ulang oleh worker berikutnya.
- `SUCCESS`: cache terakhir sudah dibangun.
- `FAILED`: worker sudah mencoba tetapi perhitungan tidak berhasil. Baca
  `last_error`, perbaiki sumbernya, lalu tekan **Ulangi Job** di halaman
  `POS > Ketersediaan POS` atau lakukan perubahan stok berikutnya. Perubahan
  stok baru pada target yang sama otomatis memberi antrean itu kesempatan
  retry baru; kegagalan lama tidak mengunci data terbaru.

## Fallback Aman

Jika kode PHP terdeploy lebih dahulu tetapi migration belum dijalankan,
aplikasi kembali memakai rebuild sinkron lama. Hal yang sama berlaku bila
tabel antrean ada tetapi saat itu gagal menerima pekerjaan. Ini sengaja
dilakukan agar menu POS tidak dibiarkan kedaluwarsa dan transaksi tidak gagal
hanya karena urutan deploy atau gangguan antrean. Setelah migration dan worker
normal, mode antrean aktif otomatis.
