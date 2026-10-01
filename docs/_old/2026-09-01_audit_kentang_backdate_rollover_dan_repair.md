# Audit KENTANG: Backdate Setelah Rollover dan Repair

Tanggal pemeriksaan: 1 September 2026

## Kesimpulan untuk user

Mismatch KENTANG bukan karena stok hilang, POS mengambil lot yang salah, atau skema defisit membuat stok minus.

Stok fisik FIFO yang benar masih **589 GR** senilai **Rp11.191**. Mismatch terjadi karena adjustment plus **609 GR** dibuat pada 1 September pukul 14:11, tetapi diberi tanggal bisnis 31 Agustus. Sementara itu, opening September sudah dibuat lebih dahulu pada 1 September pukul 05:36 ketika saldo KENTANG masih nol.

Akibatnya:

- lot FIFO menerima 609 GR dan kemudian POS memakai 20 GR, sehingga sisa lot benar 589 GR;
- ledger Agustus menerima tambahan 609 GR setelah proses rollover selesai;
- opening September tetap nol karena tidak dibangun ulang;
- POS September mengurangi 20 GR dari ledger nol, sehingga ledger September menjadi minus 20 GR;
- dashboard membandingkan ledger minus 20 dengan lot 589 dan menemukan gap 609 GR.

Jadi penyebabnya adalah **transaksi mundur setelah bulan berikutnya sudah dimulai**, bukan kekurangan stok.

## Identitas yang diperiksa

- Bahan baku: KENTANG
- `item_id`: 102
- `material_id`: 116
- Divisi: Kitchen (`division_id=3`)
- Lokasi: Kitchen Reguler
- Profile: KENTANG / NO MERK / PACK ke GR
- `profile_key`: `dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f`
- Biaya FIFO: Rp19 per GR

## Runtutan kejadian

1. Pada 31 Agustus, seluruh KENTANG lama selesai dipakai untuk batch produksi. Ledger dan stok tersisa menjadi nol.
2. Pada 1 September pukul 05:36, generator bulanan membentuk opening September. Karena KENTANG saat itu nol, tidak ada opening KENTANG yang dibawa.
3. Pada 1 September pukul 14:11, adjustment `IAD20260831-5521` diposting sebesar plus 609 GR, tetapi tanggal dokumennya 31 Agustus.
4. Adjustment membuat movement `MV202608310394` dan lot `LOT20260831-96D01BD4F41F`. Lotnya nyata dan benar, tetapi delta ledger masuk ke Agustus yang sudah dilewati rollover.
5. Pada 1 September pukul 19:14, POS commit `PSC-202609-0011` memakai 20 GR dari lot tersebut.
6. FIFO berubah dari 609 menjadi 589 GR, tetapi ledger September berubah dari 0 menjadi minus 20 GR.

## Bukti angka sebelum repair

### Ledger Agustus

- adjustment plus: 3.270,3 GR;
- saldo akhir: 609 GR;
- nilai akhir: Rp11.571;
- movement terakhir: adjustment #3181 yang secara fisik baru dibuat 1 September.

### Ledger September

- opening: 0 GR;
- pemakaian POS: 20 GR;
- saldo akhir: minus 20 GR;
- nilai akhir: minus Rp380.

### FIFO aktif

- lot masuk: 609 GR;
- dipakai POS: 20 GR;
- sisa: 589 GR;
- nilai: Rp11.191.

Gap aktifnya tepat:

- kuantitas: `589 - (-20) = 609 GR`;
- nilai: `Rp11.191 - (-Rp380) = Rp11.571`.

## Perbaikan logika aplikasi

### 1. Backdate setelah rollover ditolak

Writer stok pusat sekarang menolak transaksi bulan lama bila periode bulan yang lebih baru sudah tersedia. Pesan mengarahkan operator mencatat koreksi pada bulan aktif.

Pengaman ini berlaku untuk writer yang memakai ledger resmi, termasuk:

- adjustment material dan component;
- PO dan SR;
- produksi;
- POS dan antrean stock commit;
- transfer dan writer inventory lain.

Transaksi masa depan juga ditolak pada guard pusat, bukan hanya oleh form.

### 2. Reopen lama tidak boleh merusak bulan baru

Reopen manual periode lama ditolak bila bulan yang lebih baru sudah tersedia. Koreksi lintas rollover harus memakai transaksi bulan aktif atau repair terkontrol.

Pengecualian hanya diberikan kepada rollback internal ketika proses cut-off gagal di tengah jalan. Pengecualian ini tidak dapat dipakai oleh form transaksi biasa.

### 3. Void dan refund memakai tanggal reversal

Void/refund adalah event stok baru. Jika order Agustus di-void pada September, movement pengembaliannya masuk September. Order dan pemakaian awal tetap berada di Agustus. Dengan demikian reversal tidak mengubah bulan historis setelah opening bulan baru terbentuk.

### 4. Dashboard membaca gap kuantitas yang benar

Dashboard sebelumnya dapat melewatkan `lot_vs_balance_delta` dan keliru memberi label seolah-olah kuantitas sama tetapi nilai FIFO berbeda. Pembacaan sekarang memakai gap material dan breakdown profile yang benar, sehingga kasus KENTANG tampil sebagai gap 609 GR.

## Repair data

Jalankan setelah kode aplikasi terbaru sudah terpasang:

```text
sql/2026-09-01b_repair_kentang_late_backdated_adjustment_rollover.sql
```

Skrip akan:

1. Memastikan seluruh ID, profile, qty, HPP, lot, issue POS, dan gap masih cocok dengan hasil audit.
2. Memindahkan tanggal bisnis adjustment #3181, movement #73083, dan receipt lot #8879 ke 1 September.
3. Mengeluarkan 609 GR/Rp11.571 dari ledger Agustus sehingga saldo Agustus kembali nol.
4. Memasukkan 609 GR/Rp11.571 sebagai adjustment plus September.
5. Menambahkan 609 GR pada running balance movement September sesudah adjustment.
6. Menyimpan satu audit marker `INV-KENTANG-20260901-V1`.
7. Memastikan ledger September sama dengan FIFO sebelum commit.

Skrip tidak mengubah:

- `qty_in`, `qty_out`, dan `qty_balance` lot;
- FIFO issue POS 20 GR;
- HPP Rp19 per GR;
- order atau pembayaran POS;
- defisit historis KENTANG.

## Hasil yang diharapkan

### Agustus

- adjustment plus: 2.661,3 GR;
- saldo akhir: 0 GR;
- nilai akhir: Rp0;
- movement terakhir kembali ke batch produksi #1859.

### September

- opening: 0 GR;
- adjustment plus: 609 GR;
- pemakaian POS: 20 GR;
- saldo akhir: 589 GR;
- nilai akhir: Rp11.191;
- nilai rata-rata: Rp19 per GR.

### Rekonsiliasi

- ledger: 589 GR / Rp11.191;
- FIFO: 589 GR / Rp11.191;
- gap kuantitas: 0;
- gap nilai: 0;
- tidak ada defisit aktif baru yang dibuat.

## Hasil pengujian terisolasi

Repair telah diuji pada clone tabel dependensi KENTANG, bukan pada database utama.

- Apply pertama: berhasil.
- Apply kedua: berhasil dan tidak menggandakan delta.
- Audit marker setelah dua apply: tetap satu.
- Agustus: 0 GR / Rp0.
- September: 589 GR / Rp11.191.
- Gap ledger-FIFO: 0 GR / Rp0.

## Pemeriksaan sesudah apply di server

1. Pastikan hasil query terakhir skrip menunjukkan `qty_gap = 0` dan `value_gap = 0`.
2. Buka stok divisi Kitchen dan pastikan KENTANG menunjukkan 589 GR.
3. Buka Daily Matrix 1 September dan pastikan terlihat adjustment plus 609, out 20, akhir 589.
4. Buka halaman reconcile dan dashboard; KENTANG tidak boleh lagi muncul sebagai mismatch.
5. Coba membuat adjustment bertanggal Agustus setelah periode September tersedia; server harus menolak tanpa membuat movement atau lot.
