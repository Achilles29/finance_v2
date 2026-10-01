# HPP POS Saat Stok Defisit

## Tujuan

Dokumen ini menjelaskan cara sistem membaca HPP saat stok resep tidak cukup,
tanpa memperlambat kasir dan tanpa membuat lot menjadi minus.

## Keputusan Utama

1. Kasir tidak menghitung ulang resep, lot, atau FIFO saat menyimpan order.
2. Kasir membaca HPP produk yang sudah tersedia di cache halaman
   `/pos/stock-live`.
3. HPP produk tetap dihitung penuh sesuai resep, walaupun lot yang tersisa
   hanya cukup sebagian atau nol.
4. Kekurangan stok dicatat sebagai defisit operasional, bukan lot minus.
5. Bila barang nyata datang kemudian dengan harga berbeda, selisihnya dicatat
   sebagai koreksi HPP terpisah. Order POS lama tidak diubah.

## Contoh NORI

Produk membutuhkan NORI `1,50 GR`.

Kondisi saat transaksi:

- Lot NORI yang benar-benar dapat diambil hanya `0,47 GR`.
- Biaya NORI yang berlaku di stock-live adalah misalnya `Rp937,98/GR`.
- Stok fisik mungkin sebenarnya ada, tetapi belum masuk atau belum direkon di
  sistem.

Perlakuan sistem:

1. HPP produk di order tetap memasukkan `1,50 x Rp937,98` untuk NORI, ditambah
   seluruh bahan resep lain.
2. Lot hanya berkurang `0,47 GR`, karena itu satu-satunya lot yang benar-benar
   tersedia di sistem.
3. Kekurangan `1,03 GR` tercatat sebagai defisit NORI. Tidak ada lot atau
   stok aktif yang dipaksa menjadi minus.
4. Ketika NORI diterima atau hasil recon fisik dicatat, stok baru tetap masuk
   penuh sebagai stok nyata. Sistem juga dapat menyelesaikan defisit yang
   identitasnya sama.
5. Jika harga NORI yang benar-benar datang berbeda dari biaya sementara,
   selisih biaya `1,03 GR` dicatat sebagai koreksi HPP. HPP order yang sudah
   disimpan tidak ditulis ulang.

Dengan pola ini, kekeliruan stok sistem tidak membuat HPP penjualan menjadi
nol atau hanya menghitung bagian lot `0,47 GR`.

## Sumber HPP Pada Stock Live

Urutan biaya untuk satu bahan atau component adalah:

1. Lot FIFO aktif paling depan pada lokasi/divisi yang tepat.
2. Biaya stok berjalan atau biaya terakhir pada bulan aktif.
3. Untuk bahan baku, harga katalog pembelian aktif yang telah dikonversi ke
   UOM resep.
4. HPP standar master bahan/component.
5. Jika seluruh sumber biaya kosong, sistem memakai HPP standar/cache produk
   sebagai perlindungan terakhir. Sistem tidak boleh mengarang harga baru.

Urutan tersebut dipakai bersama oleh stock-live dan jejak biaya stock commit
POS. Tujuannya agar angka yang dibaca kasir dan angka pada audit persediaan
tidak mengambil sumber biaya yang saling bertentangan.

## Jalur Yang Memperbarui Stock Live

Setelah deploy, berikut jalur yang memperbarui cache HPP dan ketersediaan
produk:

| Kegiatan | Dampak cache stock-live |
| --- | --- |
| PO/penerimaan pembelian | Material terkait ditandai perlu diperbarui lalu masuk antrean availability POS. |
| Store request dan transfer gudang ke divisi | Material terkait ditandai perlu diperbarui setelah ledger diposting. |
| Adjustment, daily recon, dan stok fisik bahan baku | Material terkait masuk antrean setelah ledger berhasil diposting. |
| Batch produksi dan adjustment component | Component terkait masuk antrean setelah component stock writer selesai. |
| Order POS | Cache produk terdampak direbuild sekali setelah seluruh stock commit selesai. Tidak direbuild per lot. |
| Void/refund POS | Cache produk terdampak direbuild sekali setelah reversal selesai. |

Antrean hanya aktif setelah migration
`sql/2026-08-21e_pos_product_availability_queue.sql` dipasang. Sebelum itu,
aplikasi tetap memakai rebuild sinkron sebagai fallback agar transaksi tidak
berhenti. Panduan worker tersedia di
[antrean availability POS](/C:/xampp/htdocs/finance/docs/2026-08-21_antrean_availability_pos_dan_performa_writer_stok.md).

Order draft yang belum dikonfirmasi tidak mengubah stok maupun HPP histori.
Order yang sudah dikonfirmasi tidak dapat diubah langsung dari kasir; koreksi
stok dilakukan melalui void/refund agar audit tetap jelas.

## Koreksi HPP Defisit

Migration `sql/2026-08-19c_pos_provisional_hpp_deficit_cogs_foundation.sql`
menambahkan dua tabel audit:

- `inv_stock_deficit_cogs_adjustment`: selisih antara biaya sementara saat
  defisit dan biaya sumber barang yang benar-benar menutup defisit.
- `inv_stock_deficit_cogs_reversal`: pembalikan proporsional jika transaksi
  POS terkait kemudian di-void atau direfund.

Koreksi tersebut sudah dibaca oleh metrik `HPP Live` pada laporan keuangan.
Aturannya:

- Jika penjualan dan penyelesaian defisit masih dalam bulan yang sama dan
  bulan keuangan belum ditutup, koreksi dibaca pada tanggal penjualan.
- Jika barang datang pada bulan lain, atau bulan penjualan sudah ditutup,
  koreksi dibaca pada tanggal barang datang sebagai koreksi periode berjalan.

Cara ini menjaga laporan bulan yang sudah ditutup tetap stabil, tanpa
menyembunyikan perbedaan biaya aktual di bulan berikutnya.

## Batasan Yang Sengaja Ada

- Data penjualan dan defisit lama sebelum migration tidak dibackfill otomatis.
  Hal ini sengaja agar histori pengembangan lama tidak berubah diam-diam.
- Jika semua sumber biaya suatu bahan kosong, admin perlu melengkapi katalog
  atau HPP standar. Sistem tidak boleh memberi nilai biaya palsu.
- Defisit tetap identitas-spesifik: domain, lokasi, divisi, tujuan, item/
  material/component, UOM, dan profile harus cocok. Defisit tidak dihapus oleh
  barang lain yang namanya mirip.

## Langkah Deploy dan Pengujian

1. Jalankan migration `2026-08-19c_pos_provisional_hpp_deficit_cogs_foundation.sql`
   di lokal dan server sebelum kode baru dipakai.
2. Jalankan migration `2026-08-19h_pos_commit_deficit_reference_and_provisional_hpp.sql`
   untuk merapikan HPP sementara dan label defisit pada transaksi POS **bulan
   aktif** yang sudah terlanjur tercatat.
3. Jalankan migration `2026-08-19i_backfill_active_pos_deficit_cogs_adjustments.sql`
   untuk membuat atau menormalkan koreksi HPP atas settlement POS **bulan
   aktif** yang terjadi sebelum integrasi ini aktif. File ini tidak mengubah
   stok, lot, movement, order, pembayaran, atau kas.
4. Deploy kode aplikasi.
5. Jalankan rebuild stock-live sekali untuk setiap outlet agar cache produk
   lama memperoleh aturan HPP baru.
6. Uji satu produk yang bahannya hanya tersisa sebagian:
   - Pastikan `/pos/stock-live` tetap menampilkan HPP lebih dari nol.
   - Simpan transaksi POS dan pastikan HPP order tetap menghitung seluruh
     kebutuhan resep.
   - Pastikan kekurangan muncul sebagai defisit, bukan lot minus.
7. Terima barang atau lakukan recon fisik pada identitas yang sama:
   - Pastikan defisit berkurang/selesai.
   - Pastikan stok baru tetap masuk sesuai jumlah fisik.
   - Periksa laporan keuangan: HPP Live dapat memiliki koreksi selisih biaya.
8. Uji void/refund transaksi tersebut:
   - Lot yang pernah benar-benar keluar dapat kembali sesuai aturan return.
   - Bagian yang sebelumnya hanya defisit tidak membuat stok fiktif.
   - Koreksi HPP defisit dibalik secara proporsional.
