# Laporan Penjualan dan Margin POS

## Tujuan

Laporan ini membantu membaca penjualan POS dari empat sudut yang berbeda tanpa
memakai ulang proses stok ketika kasir menyimpan transaksi:

1. Per transaksi: nilai tagihan, refund, HPP, dan laba kotor satu order.
2. Per produk: kontribusi setiap produk, termasuk extra yang dipilih bersama
   produk tersebut.
3. Per extra: rincian tambahan yang dibeli pelanggan.
4. Audit penjualan dan HPP: antrean transaksi yang perlu ditelusuri sebelum
   angka dipakai sebagai laporan manajemen.

Halaman laporan hanya membaca data yang sudah tersimpan. Membuka laporan tidak
mengubah stok, lot, HPP snapshot, pembayaran, refund, atau kas.

## Halaman yang Dipakai

| Halaman | Fungsi |
| --- | --- |
| `/pos/reports/sales` | Daftar transaksi dengan penjualan bersih, HPP terkini, laba kotor, dan margin. |
| `/pos/reports/sales-detail/{id}` | Audit lengkap satu transaksi, termasuk HPP setiap line produk dan extra. |
| `/pos/reports/sales-detail` | Ringkasan kontribusi per produk. |
| `/pos/reports/sales-extra` | Ringkasan penjualan dan HPP per extra. |
| `/pos/reports/sales-audit` | Pemeriksaan baca-saja untuk refund, HPP, margin, dan tautan koreksi HPP defisit yang perlu ditelusuri. |

Setelah SQL menu dijalankan, laporan transaksi juga muncul pada sidebar
`Keuangan > Penjualan & Margin POS`.

## Cara Membaca Angka Transaksi

Pada laporan per transaksi:

1. `Penjualan Kotor Item` adalah subtotal produk dan extra sebelum potongan.
2. `Potongan Penjualan` adalah gabungan diskon, promo, voucher, poin, dan
   compliment.
3. `Nilai Tagihan Sebelum Refund` adalah nilai akhir order sebelum uang refund
   dikembalikan. Untuk order yang sudah direfund, laporan membacanya dari
   pembayaran awal yang tersimpan agar header order yang telah dikurangi refund
   tidak dihitung dua kali. Angka ini dapat mencakup pajak, service, dan
   pembulatan sesuai pengaturan transaksi.
4. `Refund Posted` hanya membaca refund yang sudah benar-benar diposting.
5. `Penjualan Bersih Tagihan` adalah nilai tagihan akhir dikurangi refund.
6. `HPP Saat Jual` adalah biaya produk dan extra ketika transaksi dibuat.
7. `HPP Refund Dibalik` mengurangi HPP karena barang tersebut sudah direfund.
8. `Koreksi HPP Defisit` adalah selisih biaya saat defisit stok kemudian
   diselesaikan dengan barang nyata yang biayanya berbeda.
9. `HPP Terkini` adalah HPP saat jual dikurangi HPP refund lalu ditambah
   koreksi HPP defisit.
10. `Laba Kotor` adalah penjualan bersih tagihan dikurangi HPP terkini.

Rumus ringkasnya:

```text
Nilai Tagihan Sebelum Refund =
  paid_total jika ada refund posted
  grand_total jika belum ada refund posted

Penjualan Bersih Tagihan = Nilai Tagihan Sebelum Refund - Refund Posted
HPP Terkini = HPP Saat Jual - HPP Refund Dibalik + Koreksi HPP Defisit
Laba Kotor = Penjualan Bersih Tagihan - HPP Terkini
```

`Uang Diterima` tetap ditampilkan terpisah sebagai informasi kas/pembayaran.
Pada transaksi refund, nilai pembayaran awal dipakai hanya sebagai dasar untuk
merekonstruksi tagihan sebelum refund. Pada transaksi tanpa refund, uang
diterima tetap tidak boleh disamakan dengan penjualan bersih: sebuah order bisa
sudah menjadi penjualan tetapi belum lunas, atau lunas dengan DP.

## Kebijakan Pajak dan Service

Pajak dan service diperlakukan sebagai **pendapatan kafe**. Karena itu keduanya
tetap berada di `Grand Total Order`, lalu ikut masuk ke `Penjualan Bersih
Tagihan` dan laba kotor pada laporan **per transaksi**.

Saat customer hanya merefund produk atau extra, pajak dan service tidak ikut
direfund oleh kebijakan ini. Keduanya tetap menjadi pendapatan transaksi.

Laporan **per produk** dan **per extra** tidak membagi pajak/service ke item
secara paksa. Kedua nilai itu berada di header transaksi, bukan pada satu
produk atau extra tertentu. Jadi gunakan laporan transaksi untuk membaca total
pendapatan kafe, dan laporan produk/extra untuk membaca kontribusi item.

## Cara Membaca Produk dan Extra

### Laporan Produk

Laporan produk menjawab: produk mana yang paling besar kontribusinya terhadap
penjualan dan margin.

- Nilai produk mencakup extra yang dipilih bersama produk induk.
- Potongan transaksi dibagi proporsional berdasarkan kontribusi nilai item
  terhadap subtotal order.
- Refund produk dan refund extra yang menempel pada produk itu ikut mengurangi
  penjualan bersih dan HPP produk.
- Koreksi HPP defisit tercatat pada produk induk karena sumber koreksi stok
  memang tersimpan pada line produk POS.
- Nilainya adalah `Penjualan Bersih Item`, sehingga tidak memasukkan pajak dan
  service header transaksi.

### Laporan Extra

Laporan extra menjawab: extra apa yang terjual dan berapa margin extra itu
sendiri.

- HPP extra dihitung dari `qty x biaya snapshot extra` saat order dibuat.
- HPP refund extra mengurangi HPP tersebut bila extra direfund.
- Koreksi HPP defisit tidak dibagi paksa ke extra, karena tabel koreksi stok
  belum memiliki hubungan khusus per line extra.

Laporan Produk sudah memasukkan extra yang melekat pada produk. Karena itu
jangan menjumlahkan total Laporan Produk dengan total Laporan Extra: Laporan
Extra adalah rincian tambahan untuk memahami sumber kontribusi produk, bukan
angka yang harus ditambahkan lagi ke total produk.

## Hubungan Dengan Stok Defisit

Saat stok bahan/component kurang, POS tetap menyimpan HPP sementara penuh dari
stock-live agar transaksi tidak memiliki HPP nol. Kekurangannya dicatat sebagai
defisit tanpa membuat lot minus.

Ketika defisit diselesaikan oleh penerimaan, transfer masuk, batch, atau
adjustment plus yang sah, sistem dapat membuat `Koreksi HPP Defisit`. Laporan
menampilkan koreksi ini terpisah supaya:

1. Harga yang terbaca saat kasir menyimpan order tidak diubah diam-diam.
2. Selisih biaya nyata tetap dapat diaudit pada transaksi asal.
3. Refund atau void dapat membalik koreksi secara proporsional.

Jika migration HPP defisit belum tersedia pada sebuah database, laporan tetap
bisa dibuka. Kolom koreksi akan bernilai nol sampai fondasinya dipasang.

## Kinerja

Pembagian potongan pada laporan memakai `subtotal_amount` yang sudah disimpan
di header order POS. Sistem tidak menghitung ulang seluruh line transaksi hanya
untuk mencari dasar pembagian diskon. Dengan demikian:

1. Laporan tetap membaca data yang konsisten dengan tagihan POS.
2. Query laporan lebih ringan untuk periode yang panjang.
3. Proses simpan transaksi kasir dan job stok background tidak berubah atau
   bertambah berat.

## Deploy

1. Deploy perubahan PHP.
2. Jalankan `sql/2026-08-21a_finance_sales_margin_pos_menu_alias.sql` di lokal
   dan server untuk menambahkan menu Keuangan serta akses peran keuangan.
3. Jalankan `sql/2026-08-21b_pos_sales_hpp_integrity_audit_menu_seed.sql` di
   lokal dan server untuk menambahkan halaman Audit Penjualan & HPP.
4. Jalankan `sql/2026-08-21c_pos_refund_cash_and_gross_amount_schema.sql` di
   lokal dan server sebelum deploy PHP. Script ini menambah satu kolom nilai
   kotor line refund dan mengisi fallback data lama; tidak membuat mutasi baru.
   Untuk refund historis yang dulu memakai diskon, fallback hanya dapat memakai
   nominal refund yang tersisa di database. Total transaksi tetap benar, tetapi
   pemisahan nilai kotor dan potongan per item mulai sepenuhnya presisi untuk
   refund yang dibuat setelah migration dipasang.
5. Pastikan migration HPP defisit Fase 5A sudah dipasang bila perusahaan ingin
   melihat koreksi HPP defisit pada laporan:
   - `2026-08-19c_pos_provisional_hpp_deficit_cogs_foundation.sql`
   - `2026-08-19h_pos_commit_deficit_reference_and_provisional_hpp.sql`
   - `2026-08-19i_backfill_active_pos_deficit_cogs_adjustments.sql`

## Pengujian Manual

1. Buat order normal dengan satu produk dan satu extra.
   - Pastikan detail transaksi menampilkan HPP produk dan HPP extra.
   - Pastikan total produk memuat kontribusi extra, sedangkan laporan extra
     menampilkan extra itu sebagai rincian tersendiri.
2. Buat order dengan promo atau diskon header.
   - Pastikan total potongan di detail order sama dengan header POS.
   - Pastikan laporan produk dan extra menunjukkan potongan secara proporsional.
3. Refund sebagian produk atau extra pada transaksi dengan diskon.
   - Pastikan uang refund dihitung proporsional terhadap potongan transaksi.
   - Pastikan penjualan bersih turun hanya sebesar refund posted.
   - Pastikan HPP refund dibalik sebesar biaya yang direfund.
4. Uji satu transaksi dengan defisit yang kemudian diselesaikan memakai biaya
   berbeda.
   - HPP saat jual tidak berubah.
   - Koreksi HPP Defisit muncul pada detail transaksi dan produk induk.
5. Bandingkan `Uang Diterima` dengan `Penjualan Bersih Tagihan` pada order yang
   belum lunas atau memakai DP. Angkanya boleh berbeda dan memang harus
   dipahami sebagai dua hal yang berbeda.
