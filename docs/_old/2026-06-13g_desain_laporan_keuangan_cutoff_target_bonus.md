# Desain Laporan Keuangan, Cut Off, dan Target Bonus

## 1. Masalah yang Sedang Kita Hadapi

Saat ini saldo rekening utama dibaca dari `fin_company_account.current_balance`.

Itu bagus untuk:
- membaca posisi live hari ini
- validasi mutasi masuk/keluar
- kebutuhan operasional cepat

Tetapi itu belum cukup untuk:
- laporan bulanan yang harus bisa dibuka ulang beberapa bulan ke belakang
- laporan tahunan yang stabil walau data live sudah berubah
- audit per rekening pada akhir bulan
- pembandingan target vs realisasi yang konsisten

Intinya:
- `current_balance` adalah **saldo live sekarang**
- laporan bulanan butuh **saldo per cut off periode**

Kalau kita hanya mengandalkan `current_balance`, maka laporan bulan lalu bisa ikut berubah saat ada koreksi atau mutasi baru hari ini.

## 2. Bahasa Bisnis yang Kita Pakai

Supaya user tidak bingung, kita pakai 3 istilah ini secara konsisten:

### 2.1 Saldo Fisik
Saldo yang benar-benar sedang tercatat di kas / bank / rekening.

Contoh:
- BCA: Rp 2.000.000
- Brankas: Rp 1.500.000

Ini adalah angka rekening apa adanya.

### 2.2 Saldo Riil Kafe
Saldo fisik yang sudah dibaca bersama eksposur aktif.

Rumus kerja manajerial:

`saldo riil = saldo fisik + piutang aktif + kasbon aktif - utang aktif - payroll belum cair`

Catatan penting:
- ini **boleh dipakai untuk dashboard manajerial**
- ini **bukan berarti piutang = uang kas**
- jadi labelnya harus jelas: ini posisi riil / kekuatan kas operasional, bukan saldo rekening murni

### 2.3 Historis Saldo Tetap
Transaksi yang memang dicatat ke rekening tertentu, tetapi saat input **tidak boleh mengubah saldo fisik** karena kejadian aslinya sudah terjadi sebelum aplikasi dipakai.

Contoh:
- utang lama Rp 1.000.000 dicatat ke rekening BCA, mode `KEEP_BALANCE`
- saldo fisik rekening tetap Rp 2.000.000
- tetapi analisa eksposur tetap membaca bahwa ada kewajiban Rp 1.000.000

Jadi konsep yang Anda minta **bisa diterima**, asalkan:
- saldo fisik tetap dipisah
- saldo riil diberi label jelas
- transaksi `KEEP_BALANCE` tetap wajib punya rekening referensi

## 3. Prinsip Laporan Keuangan yang Aman

### 3.1 Harian
Laporan harian boleh banyak membaca data live.

Tujuan:
- cek pemasukan hari ini
- cek pengeluaran hari ini
- cek estimasi gaji berjalan
- cek kebutuhan SR / belanja pending

### 3.2 Bulanan
Laporan bulanan sebaiknya membaca:
- snapshot rekening bulanan
- snapshot eksposur bulanan
- snapshot metrik manajerial bulanan

Bila period belum ditutup:
- laporan boleh memakai mode `LIVE PREVIEW`

Bila period sudah ditutup:
- laporan harus memakai mode `CLOSED SNAPSHOT`

### 3.3 Tahunan
Laporan tahunan tidak perlu replay mutasi dari nol.

Lebih aman:
- tarik dari akumulasi snapshot bulanan yang sudah closed

Jadi arsitekturnya:
- harian = live / near-live
- bulanan = snapshot per cut off
- tahunan = agregasi snapshot bulanan

## 4. Sumber Data per Kelompok Laporan

### 4.1 Pendapatan
Sumber utama:
- POS payment
- POS refund

### 4.2 Belanja
Sumber utama:
- purchase order
- payment plan / real payment purchase

Pisahkan minimal menjadi:
- bahan baku
- operasional sesuai tipe purchase
- utilitas dan beban rutin seperti listrik, air, internet, gas, dan sejenisnya
- aset / inventaris / equipment
- lainnya

Catatan:
- kelompok `operasional` sebaiknya mengikuti `purchase type` agar pembacaan biaya tidak bercampur
- jadi nanti user bisa membaca dengan jelas mana:
  - belanja bahan baku
  - belanja operasional outlet
  - belanja utilitas
  - belanja aset
  - belanja lain-lain

### 4.3 Store Request
SR bukan selalu uang keluar.

Karena itu perlu dibedakan:
- `SR pending value` = komitmen kebutuhan yang belum direalisasikan
- `SR fulfilled cost` = nilai barang yang sudah benar-benar keluar dari gudang / stok

### 4.4 Payroll
Pisahkan:
- `payroll estimate running`
- `payroll finalized`
- `salary disbursement paid`

### 4.5 Kasbon
Pisahkan:
- kasbon cair bulan ini
- outstanding kasbon aktif
- potongan kasbon lewat payroll

### 4.6 Utang dan Piutang
Pisahkan:
- transaksi yang memutasi rekening
- transaksi `KEEP_BALANCE`
- outstanding aktif
- overdue

## 5. Cut Off Bulanan yang Disarankan

Setiap akhir bulan kita butuh proses tutup periode:

1. Tentukan periode, misal `2026-06`
2. Bekukan angka per rekening:
   - opening balance fisik
   - total mutasi masuk
   - total mutasi keluar
   - closing balance fisik
3. Bekukan eksposur:
   - utang outstanding
   - piutang outstanding
   - kasbon outstanding
   - payroll pending
   - historis saldo tetap
4. Bekukan metrik manajerial:
   - omzet POS
   - refund
   - belanja bahan baku
   - belanja operasional
   - belanja lain
   - SR pending
   - estimasi payroll
5. Simpan ke tabel snapshot
6. Tandai period `CLOSED`

Kalau perlu koreksi:
- period bisa `REOPENED`
- lalu dibuat snapshot versi baru

## 6. Kenapa Snapshot Ini Penting

Tanpa snapshot:
- saldo bulan Mei bisa ikut berubah saat Juni ada pembetulan
- target bonus bisa diperdebatkan ulang
- angka rekening dan angka realisasi bisa tidak sinkron saat audit

Dengan snapshot:
- bulan yang sudah tutup punya angka tetap
- dashboard harian tetap boleh jalan live
- laporan bulanan tetap konsisten

## 7. Desain Target Harian dan Bulanan

Saya sarankan target dibuat fleksibel, bukan cuma omzet.

Minimal tiap target punya:
- periode target
- scope target: harian / bulanan / tahunan
- divisi / global
- daftar metrik yang dinilai
- bobot tiap metrik
- aturan bonus

Selain omzet dan beban kas, target juga sebaiknya membaca lapisan operasional persediaan dan biaya berjalan, yaitu:
- HPP live sebagai pembacaan variable cost
- adjustment gudang
- adjustment bahan baku
- adjustment component / base-prepare
- jumlah belanja per kelompok
- berapa barang masuk gudang
- berapa belanja operasional yang langsung habis pakai
- berapa bahan baku masuk
- berapa bahan baku terpakai
- berapa stok yang masih tersimpan di gudang
- berapa stok yang masih tersimpan di divisi
- estimasi gaji berjalan

Artinya target tidak hanya menjawab:
- "berapa penjualan"

Tetapi juga:
- "berapa biaya live yang sedang dikonsumsi"
- "berapa pemborosan / adjustment di gudang, divisi, dan component"
- "berapa stok yang berubah menjadi beban dan berapa yang masih jadi aset persediaan"

### 7.1 Contoh Metrik Target Harian
- omzet POS harian minimum
- refund maksimum
- belanja operasional harian maksimum
- HPP live harian maksimum atau rasio HPP live terhadap omzet
- adjustment gudang harian maksimum
- adjustment bahan baku harian maksimum
- adjustment component harian maksimum
- nilai bahan baku masuk harian
- nilai bahan baku terpakai harian
- nilai perpindahan stok ke divisi harian bila relevan
- saldo riil minimum
- estimasi payroll harian maksimum per rasio omzet

### 7.2 Contoh Metrik Target Bulanan
- omzet bulanan minimum
- laba operasional estimasi minimum
- food cost maksimum
- HPP live / variable cost maksimum
- operational expense maksimum
- utilitas maksimum
- belanja aset maksimum atau sesuai budget
- total belanja bahan baku bulan berjalan
- total belanja operasional bulan berjalan
- total barang masuk gudang
- total bahan baku masuk ke sistem
- total bahan baku terpakai
- stok tersimpan akhir bulan di gudang
- stok tersimpan akhir bulan di divisi
- adjustment gudang maksimum
- adjustment bahan baku maksimum
- adjustment component maksimum
- payroll ratio maksimum
- utang overdue maksimum
- piutang overdue maksimum
- SR pending maksimum

## 8. Konsep Bonus yang Saya Sarankan

Jangan bonus hanya berdasarkan omzet.

Lebih aman pakai dua lapis:

### Lapis 1: Gate Wajib
Bonus tidak keluar kalau salah satu gagal, misalnya:
- omzet minimum tidak tercapai
- laba estimasi negatif
- payroll ratio terlalu tinggi

### Lapis 2: Skor Bobot
Kalau gate lolos, baru hitung skor.

Contoh:
- omzet 40%
- margin / laba 25%
- efisiensi belanja 15%
- kontrol refund / void / waste 10%
- kedisiplinan biaya payroll 10%

Jadi pegawai tidak terpacu jualan saja, tapi juga jaga efisiensi.

## 9. Laporan yang Sebaiknya Kita Bangun Bertahap

### Tahap 1
- Posisi Kas & Eksposur
- Rekap Arus Kas Harian / Bulanan
- Rekap Utang / Piutang / Kasbon / Payroll Pending

### Tahap 2
- Laporan Belanja per kelompok biaya
- Laporan SR vs Realisasi Belanja
- Laporan Estimasi Laba Operasional Bulanan

### Tahap 3
- Dashboard target harian
- Dashboard target bulanan
- Evaluasi bonus berdasarkan target

## 10. Keputusan Desain yang Saya Anggap Paling Aman

1. `fin_company_account.current_balance` tetap dipakai untuk saldo live.
2. Laporan bulanan/tahunan jangan membaca live saja; harus punya snapshot cut off.
3. Transaksi `KEEP_BALANCE` tetap wajib menyimpan rekening referensi.
4. Saldo fisik dan saldo riil harus dipisahkan di semua halaman.
5. Target bonus harus membaca snapshot atau summary period, bukan hitung ulang bebas dari data live.

## 11. Output Fondasi yang Dibutuhkan

Untuk menopang desain ini, fondasi minimal yang perlu ada:
- tabel period close
- tabel snapshot rekening per periode
- tabel metric summary per periode
- tabel target plan
- tabel target line
- tabel realisasi target

## 12. Tambahan yang Sebaiknya Ikut Disiapkan

Supaya modul ini benar-benar kuat saat dipakai operasional, saya sarankan kita juga menyiapkan:

### 12.1 Kamus Metric Standar
Satu daftar metric resmi supaya:
- report
- target
- bonus
- dashboard

semua memakai istilah yang sama.

Contoh metric penting:
- `POS_REVENUE`
- `POS_REFUND`
- `PURCHASE_RAW_MATERIAL`
- `PURCHASE_OPERATIONAL`
- `PURCHASE_UTILITY`
- `PURCHASE_ASSET`
- `SR_PENDING_VALUE`
- `PAYROLL_ESTIMATE_RUNNING`
- `PAYROLL_DISBURSED`
- `CASH_ADVANCE_OUTSTANDING`
- `PAYABLE_OUTSTANDING`
- `RECEIVABLE_OUTSTANDING`
- `LIVE_HPP_VALUE`
- `WAREHOUSE_ADJUSTMENT_VALUE`
- `DIVISION_ADJUSTMENT_VALUE`
- `COMPONENT_ADJUSTMENT_VALUE`
- `RAW_MATERIAL_IN_VALUE`
- `RAW_MATERIAL_USAGE_VALUE`
- `WAREHOUSE_ENDING_STOCK_VALUE`
- `DIVISION_ENDING_STOCK_VALUE`

### 12.2 Budget vs Actual
Target nantinya akan lebih kuat kalau bisa dibedakan:
- target
- budget
- realisasi

Karena ada kasus:
- target penjualan tinggi
- tapi budget belanja juga memang dinaikkan

### 12.3 Aging
Untuk utang dan piutang, analisa akan jauh lebih berguna kalau ada kelompok umur:
- belum jatuh tempo
- 1-30 hari
- 31-60 hari
- 61-90 hari
- >90 hari

### 12.4 Guard Period
Kalau periode sudah `CLOSED`, perubahan transaksi lama idealnya:
- diblok
- atau masuk sebagai koreksi periode berikutnya
- atau memaksa `REOPEN` dengan jejak audit

Ini penting agar laporan bulanan tidak berubah diam-diam.

Itu saya siapkan di file SQL foundation terpisah agar nanti UI laporan tinggal menempel ke struktur yang sama.
