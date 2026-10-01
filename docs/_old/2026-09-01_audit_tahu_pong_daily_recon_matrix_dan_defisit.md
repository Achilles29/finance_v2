# Audit TAHU PONG: Daily Recon, Daily Matrix, dan Defisit

Tanggal pemeriksaan: 1 September 2026

## Kesimpulan user

TAHU PONG tidak rusak karena Daily Recon gagal. Angka 13 menjadi 0 memang sudah masuk ke stok live, tetapi operator mengirimkannya melalui adjustment biasa bertanggal 30 September, bukan melalui Daily Recon tanggal 1 September. Sesudah itu ada adjustment minus kedua sebesar 13 saat stok sudah nol. Adjustment kedua tidak menghasilkan movement atau issue lot, tetapi versi writer lama membuat defisit 13.

Akibatnya tiga layar membaca fakta yang berbeda:

- Stok live membaca saldo bulanan September yang langsung berubah menjadi nol.
- Daily Matrix tanggal 1 belum melihat movement karena movement salah bertanggal 30 September, sehingga masih menampilkan 13.
- Dashboard membaca defisit palsu dari adjustment minus kedua.

Ini adalah bug kontrak writer dan pengaman tanggal, bukan konsekuensi normal dari model defisit.

## Bukti TAHU PONG

Identity yang diperiksa:

- Divisi: Kitchen (`division_id=3`)
- Tujuan: Kitchen Reguler
- Item: TAHU PONG (`item_id=283`)
- Material canonical: TAHU PONG (`material_id=197`)
- Saldo awal September: 13

Jejak yang benar sampai 31 Agustus:

- Daily Recon 31 Agustus menghitung stok sistem 10 dan fisik 13.
- Dokumen `IAD20260831-8405` tersimpan sebagai `PHYSICAL_COUNT` dengan adjustment plus 3.
- Saldo akhir Agustus menjadi 13.

Jejak bermasalah pada 1 September:

- Dokumen `IAD20260930-7730` dibuat dan diposting pada 1 September, tetapi tanggal bisnisnya 30 September.
- Dokumen itu bertipe `DELTA`, mengurangi variance 13, membuat movement `MV202609300001`, dan menghabiskan lot 13. Secara qty hasil akhirnya nol, tetapi tanggalnya salah.
- Dokumen `IAD20260901-6188` kemudian mencoba mengurangi 13 lagi saat saldo sudah nol.
- Tidak ada movement dan tidak ada FIFO issue dari dokumen kedua.
- Writer lama mencatat kekurangan itu sebagai defisit stok id 185. Defisit tersebut tidak sah karena sumbernya adalah koreksi operator, bukan konsumsi bisnis.

## Satu sumber kebenaran

Urutan sumber kebenaran yang benar adalah:

1. Movement ledger adalah bukti mutasi harian.
2. Monthly stock adalah saldo cepat yang dibangun dari opening dan movement.
3. FIFO lot adalah rincian sumber qty dan biaya yang harus sama dengan monthly stock.
4. Daily Matrix adalah proyeksi baca-saja dari opening dan movement, bukan tabel stok kedua.
5. Defisit adalah kewajiban konsumsi yang belum mendapat barang, bukan pengganti adjustment minus.

Bila kelimanya berbeda, sistem tidak boleh memilih angka secara diam-diam. Posting harus dibatalkan dan operator diarahkan ke Stock Health atau Daily Recon.

## Kontrak transaksi yang diluruskan

### Adjustment biasa

Adjustment biasa menerima selisih, misalnya waste 2 atau variance minus 3.

- Tanggal tidak boleh melewati hari ini.
- Qty minus harus tersedia penuh pada saldo monthly.
- Qty yang sama harus dapat dialokasikan penuh dari lot FIFO.
- Bila salah satu tidak cukup, seluruh dokumen gagal dan tidak ada movement, lot issue, maupun defisit yang dibuat.
- Adjustment plus membuat stok dan lot baru dengan biaya referensi yang sah.

### Daily Recon

Daily Recon menerima angka akhir hasil hitung fisik, bukan selisih.

Contoh sistem 13, fisik 0:

- Sistem menghitung delta minus 13.
- Adjustment disimpan sebagai `PHYSICAL_COUNT`.
- Movement minus 13 diposting pada tanggal hitung fisik.
- Monthly stock menjadi 0.
- Lot diselaraskan ke 0 dalam transaksi database yang sama.
- Daily Matrix pada tanggal tersebut menampilkan adjustment minus 13 dan akhir 0.
- Tidak ada defisit baru yang dibuat.

Daily Recon dapat menyelesaikan defisit lama hanya bila operator mencentang penyelesaian dan stok fisik positif membuktikan barang tersedia. Angka fisik nol tidak dapat menyelesaikan defisit.

### Defisit

Defisit hanya boleh lahir ketika transaksi bisnis harus berlanjut walaupun sumber stok tidak cukup, misalnya:

- pemakaian resep POS yang sudah terkonfirmasi;
- input produksi yang sah dan diizinkan tetap berjalan;
- konsumsi operasional lain yang kontraknya memang mengizinkan shortage.

Defisit tidak boleh dibuat oleh:

- Daily Recon;
- opname atau cut-off;
- adjustment waste, spoil, process loss, atau variance;
- transfer yang stok sumbernya tidak cukup;
- repair atau rekonsiliasi teknis.

## TAHU EVENT

TAHU EVENT bukan data fiktif. Histori menunjukkan Kitchen Event pernah menerima 550 pada 22 Juli. Pada 1 September stok tersebut sudah dikurangi 550 dan saldo aktifnya nol.

Perlakuan yang benar:

- histori penerimaan dan adjustment tetap disimpan;
- saldo nol tidak tampil pada daftar "Stok Bahan Baku Live" secara default;
- user masih dapat memilih filter "Termasuk nol" untuk audit histori profile tersebut.

## Hasil audit global per 1 September

Pemeriksaan tidak berhenti pada TAHU PONG:

- hanya ada 1 movement bahan baku yang tanggalnya masih melewati hari pemeriksaan, yaitu movement TAHU PONG `MV202609300001`;
- tidak ada movement component yang masih bertanggal masa depan;
- hanya ada 1 defisit terbuka yang berasal dari adjustment operator, yaitu defisit TAHU PONG id 185;
- terdapat 9 adjustment bahan baku lama dan 6 adjustment component lama yang dahulu diposting sebelum tanggal bisnis dokumennya. Seluruh tanggal bisnis itu sudah berlalu, sehingga dicatat sebagai antrean audit historis dan tidak diubah massal tanpa pemeriksaan dokumen satu per satu.

Artinya kekacauan aktif tanggal 1 bukan kegagalan menyeluruh semua writer. Sumber aktifnya adalah satu tanggal adjustment yang salah ditambah pengulangan adjustment minus yang oleh writer lama diubah menjadi defisit. Guard baru menutup kedua jalur tersebut ke depan.

## Perbaikan aplikasi

Perubahan yang diterapkan:

- material adjustment `DELTA` tidak lagi membuat defisit;
- component adjustment `DELTA` tidak lagi membuat defisit;
- kedua writer mewajibkan pengurangan monthly dan FIFO secara penuh;
- tanggal adjustment masa depan ditolak di server dan form;
- tombol adjustment pada tanggal masa depan di Daily Matrix dikunci;
- stok divisi live menyembunyikan saldo nol secara default;
- filter "Termasuk nol" tetap tersedia untuk audit.

Defisit pada POS dan produksi tetap dipertahankan karena keduanya merupakan kebutuhan konsumsi nyata, bukan koreksi administratif.

## Repair data lokal dan server

Jalankan:

```text
sql/2026-09-01a_repair_tahu_pong_future_adjustment_and_false_deficit.sql
```

Script melakukan preflight sebelum menulis. Bila identity, qty, movement, FIFO issue, atau defisit tidak sama dengan kasus yang diaudit, script berhenti tanpa mengubah data.

Hasil yang diharapkan sesudah apply:

- adjustment utama tetap mengurangi 13 menjadi 0, tetapi tanggal bisnisnya 1 September;
- movement dan FIFO issue muncul pada Daily Matrix 1 September;
- adjustment minus kedua berstatus `VOID`;
- defisit id 185 berstatus `VOID` dan sisa nol;
- dashboard tidak lagi menampilkan defisit TAHU PONG yang bersumber dari adjustment tersebut;
- TAHU EVENT nol tidak muncul pada stok live kecuali filter "Termasuk nol" dipilih.

## Pemeriksaan operasional berikutnya

Sesudah deploy kode dan apply SQL:

1. Buka Daily Matrix September dan pastikan TAHU PONG tanggal 1 menunjukkan awal 13, adjustment minus 13, akhir 0.
2. Buka stok live Kitchen dan pastikan TAHU PONG serta TAHU EVENT tidak muncul sebagai saldo aktif.
3. Aktifkan filter "Termasuk nol" dan pastikan histori keduanya masih dapat diaudit.
4. Buka dashboard dan pastikan defisit TAHU PONG dari adjustment sudah hilang.
5. Coba adjustment minus pada stok nol dan pastikan transaksi ditolak tanpa membuat defisit.
6. Coba Daily Recon sistem 1 ke fisik 0 dan pastikan adjustment, movement, monthly, lot, dan Daily Matrix berubah bersama.
