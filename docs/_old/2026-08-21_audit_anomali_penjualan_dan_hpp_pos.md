# Audit Anomali Penjualan dan HPP POS

## Tujuan

Halaman `/pos/reports/sales-audit` adalah antrean pemeriksaan untuk angka
penjualan dan HPP POS. Halaman ini hanya membaca data. Menjalankan audit tidak
mengubah order, refund, stok, lot, HPP, kas, atau jurnal keuangan.

Audit hanya berjalan setelah pengguna memilih periode lalu menekan tombol
`Jalankan`. Cara ini disengaja agar halaman laporan biasa tetap ringan dan
proses kasir tidak ikut terbebani.

## Kebijakan Angka

Pajak dan service tetap dianggap pendapatan kafe. Audit memakai rumus yang
sama dengan laporan transaksi. Refund mengurangi nilai produk/extra yang
dikembalikan, tetapi tidak menghapus pajak dan service yang sudah menjadi
pendapatan transaksi.

```text
Nilai Tagihan Sebelum Refund =
  paid_total, bila order sudah memiliki refund posted
  grand_total, bila belum ada refund posted

Penjualan Bersih Tagihan = Nilai Tagihan Sebelum Refund - Refund Posted
HPP Terkini = HPP Saat Jual - HPP Refund Dibalik + Koreksi HPP Defisit
Laba Kotor = Penjualan Bersih Tagihan - HPP Terkini
```

Alasan `paid_total` dipakai sebagai nilai sebelum refund hanya pada order yang
memiliki refund adalah writer refund memang mengurangi header dan line order
setelah refund diposting. `paid_total` tetap menyimpan uang yang diterima
sebelum refund. Tanpa aturan ini laporan akan mengurangi refund dua kali.

Pada order tanpa refund, `Uang Diterima` tetap bukan penjualan bersih karena
order dapat belum lunas atau menggunakan pembayaran bertahap.

## Pemeriksaan yang Dilakukan

| Kode | Arti | Perlakuan |
| --- | --- | --- |
| `REFUND_EXCEEDS_PAYMENT` | Refund posted lebih besar dari uang pembayaran yang pernah diterima. | Perlu tindakan. Periksa transaksi dan dokumen refund asal. |
| `FINAL_HPP_NEGATIVE` | HPP setelah refund dan koreksi defisit menjadi negatif. | Perlu tindakan. Periksa pembalikan HPP refund serta koreksi HPP defisit. |
| `ZERO_HPP_WITH_NET_SALE` | Ada penjualan bersih, tetapi HPP masih nol. | Perlu dicek. Produk tanpa biaya dapat valid, produk ber-resep harus ditelusuri ke stock commit/HPP snapshot. |
| `NEGATIVE_GROSS_PROFIT` | HPP lebih besar dari penjualan bersih. | Perlu dicek. Kondisi dapat terjadi karena harga jual, promo, refund, atau cost snapshot. |
| `DEFICIT_HPP_CORRECTION_UNLINKED` | Koreksi HPP defisit tidak menunjuk ke order atau line order yang benar. | Perlu tindakan. Jangan hapus baris; telusuri defisit dan transaksi asal. |
| `DEFICIT_HPP_SCHEMA_UNAVAILABLE` | Database belum memiliki fondasi koreksi HPP defisit. | Perlu menjalankan migration HPP defisit Fase 5A. |

## Cara Memakai

1. Buka `POS > Laporan POS > Audit Penjualan & HPP`, atau buka tab `Audit Penjualan` dari laporan penjualan.
2. Pilih tanggal mulai dan selesai. Bawaan halaman adalah bulan berjalan hingga hari ini.
3. Jika perlu, pilih satu outlet atau cari nomor order.
4. Tekan `Jalankan`.
5. Buka temuan merah lebih dahulu, lalu gunakan tombol detail untuk melihat transaksi sumber.
6. Jangan melakukan adjustment stok hanya untuk membuat audit bersih. Perbaikan harus mengikuti sumber masalahnya: refund, HPP snapshot, stock commit, atau koreksi HPP defisit.

## Yang Bukan Fungsi Halaman Ini

Halaman audit bukan tempat untuk memposting refund, mengubah HPP, menyelesaikan
defisit, atau menghapus riwayat. Perbaikan tetap dilakukan dari modul asal dan
harus meninggalkan jejak dokumen yang dapat diaudit.

## Catatan Refund Ganda Historis

Audit data 2 Agustus 2026 menemukan satu transaksi yang benar-benar perlu
ditindaklanjuti: `POS-20260802-0076`. Order menerima Rp35.000, tetapi dua
dokumen refund masing-masing Rp18.000 telah diposting sehingga total refund
Rp36.000. Ini bukan diperbaiki otomatis karena refund tersebut sudah
mempengaruhi mutasi rekening dan reversal stok.

Gunakan `sql/2026-08-21d_audit_pos_refund_overpayment.sql` untuk melihat daftar
terbarunya. Setelah itu buat reversal/void refund yang terkontrol dari modul
POS; jangan menghapus `pos_refund` langsung dari database.
# Addendum 1 September 2026

Audit lanjutan atas kasus TAHU PONG menegaskan bahwa defisit tidak boleh dipakai sebagai fallback adjustment operator. Kontrak global Daily Recon, Daily Matrix, monthly stock, FIFO lot, dan defisit beserta repair terukur didokumentasikan di `docs/2026-09-01_audit_tahu_pong_daily_recon_matrix_dan_defisit.md`.
