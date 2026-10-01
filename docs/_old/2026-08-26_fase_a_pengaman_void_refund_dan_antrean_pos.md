# Fase A - Pengaman Void/Refund dan Antrean POS

## Tujuan

Menutup celah ketika transaksi POS sudah di-void atau direfund penuh, tetapi
pekerjaan stok yang berjalan di belakang masih menunggu antrean. Sebelumnya
pekerjaan lama itu berpotensi berjalan terlambat dan memengaruhi stok lagi.

## Sebelum dan Sesudah

### Sebelum

- Kasir dapat membatalkan atau refund penuh transaksi dengan benar.
- Stok dapat sudah dikembalikan oleh proses pembatalan.
- Namun job stok POS yang sebelumnya sudah dibuat bisa tetap berstatus
  `QUEUED`, sehingga worker berisiko membaca job tersebut di waktu lain.
- Pada kondisi refund sebagian yang terjadi lebih cepat daripada worker, worker
  berisiko membaca ulang kebutuhan awal jika snapshot tidak dijaga.

### Sesudah

- Saat order menjadi `VOID` atau `REFUND_FULL`, job stock commit yang masih
  aktif langsung dibatalkan dalam transaksi yang sama.
- Worker memeriksa ulang status order dan snapshot saat mengambil job, sebelum
  menulis stok, dan saat menutup hasil kerja. Job terminal tidak dapat hidup
  kembali lewat retry atau worker yang terlambat.
- Refund/void sebagian tetap dapat menyelesaikan stok yang masih sah, tetapi
  tidak lagi mengirim kuantitas yang telah direversal ke stok.
- Pembatalan job hanya mengubah status antrean. Ia tidak mengubah nilai order,
  pembayaran, stok, lot, HPP, atau jurnal lama.

## Perbaikan Data Lama yang Sudah Dijalankan Lokal

Script [2026-08-26a_cancel_terminal_pos_runtime_jobs.sql](../sql/2026-08-26a_cancel_terminal_pos_runtime_jobs.sql)
menutup dua job lama berikut karena order dan snapshot-nya sudah terminal:

| Order | Kondisi order | Snapshot | Hasil |
| --- | --- | --- | --- |
| `POS-20260824-0009` | `VOID` | `REVERSED` | Job runtime dibatalkan |
| `POS-20260824-0010` | `REFUND_FULL` | `REVERSED` | Job runtime dibatalkan |

Kedua snapshot tersebut tidak memiliki referensi movement stok, sehingga
pembatalan job tidak membalik atau mengubah stok apa pun.

## Yang Perlu Dilakukan di Server

1. Deploy empat file PHP berikut bersama SQL fase ini.
   - `application/libraries/PosRuntimeJobService.php`
   - `application/libraries/PosStockCommitService.php`
   - `application/libraries/PosOrderStockService.php`
   - `application/models/Pos_model.php`
2. Jalankan sekali SQL
   [2026-08-26a_cancel_terminal_pos_runtime_jobs.sql](../sql/2026-08-26a_cancel_terminal_pos_runtime_jobs.sql).
   Script aman dijalankan ulang dan hanya menutup job terminal yang masih aktif.
3. Pastikan cron availability POS berjalan tiap menit. Contoh Linux:

```cron
* * * * * /usr/bin/php /www/wwwroot/finance/index.php pos availability_queue_run 100
```

Cron ini hanya memperbarui cache ketersediaan produk POS. Ia tidak memposting
stok, lot, atau transaksi keuangan.

## Hasil Uji Lokal

- Semua empat file PHP lolos pemeriksaan sintaks.
- SQL pembatalan berhasil menutup tepat dua job terminal.
- Worker dipanggil langsung untuk job void `4363`; hasilnya `CANCELLED` dan
  `skipped`, tanpa referensi movement stok baru.
- Satu job availability diproses sebagai uji terbatas dan berhasil. Antrean
  tersisa 98 job lama untuk ditangani cron secara bertahap.
- Pemeriksaan diff tidak menemukan whitespace error.

## Pengujian Manual Setelah Deploy

1. Buat transaksi POS kecil, lalu void sebelum worker selesai. Pastikan stok
   tidak berubah lagi setelah beberapa menit dan job tidak dapat di-retry.
2. Buat transaksi POS kecil, lalu lakukan refund penuh. Pastikan stok hanya
   mengikuti hasil refund, bukan dipotong lagi oleh worker.
3. Lakukan refund sebagian pada satu item. Pastikan hanya porsi yang tidak
   direfund yang tetap berpengaruh ke stok.
4. Periksa monitor availability setelah satu sampai dua menit. Jumlah antrean
   harus turun bertahap saat cron aktif, tanpa error berulang.
