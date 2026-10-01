# Migrasi Saldo Awal PH dari Core

> Status dokumen: migrasi V1 di bawah sudah pernah dijalankan sebagai tahap awal.
> V1 dan V2 kini digantikan oleh [migrasi PH V3](2026-08-27_migrasi_ph_v3_saldo_awal_core_dan_rekonsiliasi.md).
> Jangan menjalankan ulang file apply V1 atau V2; V3 memakai
> `core.org_employee.ph` sebagai saldo awal resmi dan menutup hasil V1/V2
> secara berjejak.

## Tujuan

Finance mulai dipakai pada **1 Juni 2026**. Karena itu, saldo PH sebelum tanggal tersebut tidak boleh dihitung ulang dari presensi Finance April-Mei. Saldo awal yang benar diambil dari database `core`, lalu dibawa ke Finance sebagai lot PH yang tetap memiliki tanggal asal dan tanggal berlaku sampai masing-masing.

## Perbaikan yang dilakukan

### 1. Penggunaan PH sebelum cutover dinetralkan

Rekonsiliasi lama `PH-HIST-20260827-V1` sempat membuat `USE` dari presensi April-Mei. Sembilan baris tersebut tidak dihapus, tetapi statusnya diubah menjadi `VOID` oleh migrasi cutover. Dengan begitu jejak tetap ada, namun baris itu tidak lagi mengurangi hak PH Finance.

Tanggal yang dinetralkan adalah:

- 6 April 2026: Fadilla Hartono Putri
- 7 April 2026: Bagas Bhakti .R dan Fairuz Sabri Rafif
- 9 April 2026: Nur Aisyah Irvana Putri
- 5 Mei 2026: Fairuz Sabri Rafif
- 7 Mei 2026: Nur Aisyah Irvana Putri
- 12 Mei 2026: Carolus Aloysius E.S dan Fairuz Sabri Rafif
- 19 Mei 2026: Fairuz Sabri Rafif

### 2. Saldo pembuka dibawa sebagai lot, bukan angka total

Sebanyak **33 lot PH tersisa** pada `31 Mei 2026` di database `core` akan dimigrasikan. Lot membawa tiga informasi penting:

- pemilik yang dipetakan berdasarkan nama pegawai yang dinormalisasi;
- tanggal PH didapatkan di sistem lama;
- tanggal **berlaku sampai** asli dari sistem lama.

Mapping tidak memakai `employee_id`, karena dua ID Finance dan Core pernah bertukar untuk Muhammad Firman Abimanyu dan Zella Aprilia. Migrasi otomatis berhenti jika sebuah nama tidak ditemukan atau cocok ke lebih dari satu pegawai Finance.

Contoh Eko Budi Lestari pada cutover:

- Lot `1 Mei 2026`, berlaku sampai `1 Agustus 2026`.
- Lot `27 Mei 2026`, berlaku sampai `27 Agustus 2026`.

Jadi saldo pembuka Eko adalah `2`, bukan `0`. Setelah grant dan penggunaan Finance sesudah 1 Juni ikut dihitung, proyeksi saldo aktif Eko pada 27 Agustus 2026 adalah `4`.

### 3. Aturan expiry diseragamkan

Makna `expired_at` sekarang adalah **berlaku sampai**:

- PH tetap bisa dipakai pada tanggal `expired_at`.
- Sistem baru membuat mutasi `EXPIRE` pada hari berikutnya.
- Filter admin, portal pegawai, dan guard jadwal menggunakan aturan yang sama.

Nilai bulan expiry pada kebijakan Finance disamakan dengan kebijakan aktif di `core`; hasil audit saat ini adalah `3 bulan`.

## File V1 yang pernah dijalankan

Bagian ini hanya catatan historis. Jangan menjalankan ulang file ini untuk mengoreksi data saat ini. Gunakan urutan file V3 pada dokumen migrasi V3 yang ditautkan di atas.

1. [Preview cutover](../sql/2026-08-27e_preview_ph_core_cutover_opening_balance.sql)
2. [Apply cutover](../sql/2026-08-27f_apply_ph_core_cutover_opening_balance.sql)

`2026-08-27f` sudah dibuat idempoten. Bila dijalankan ulang, ia tidak menggandakan lot, VOID, maupun expiry.

## Urutan aman

1. Backup database Finance terlebih dahulu.
2. Jalankan script preview `2026-08-27e`.
3. Pastikan bagian `CORE_LEDGER_ISSUE`, `NAME_MAPPING_ISSUE`, dan `FINANCE_LEDGER_ISSUE` kosong.
4. Pastikan preview menampilkan `9` USE pra-cutover dan `33` lot saldo awal.
5. Jalankan script apply `2026-08-27f` satu kali.
6. Pastikan hasil akhir menampilkan:
   - `VOID_PRE_CUTOVER_USE = 9`
   - `IMPORT_CORE_OPENING_LOT = 33`
   - `CREATE_POST_CUTOVER_EXPIRE = 8` pada data audit saat ini
7. Buka `Attendance > Ledger & Log PH` dan cek Eko. Lot dari core tampil dengan mode `MIGRATION`; baris April-Mei yang keliru tampil sebagai `VOID`.

## Yang perlu diuji setelah apply

1. Eko tidak lagi memiliki saldo minus dan dapat melihat saldo aktif `4` pada data saat ini.
2. Pegawai yang memakai PH setelah 1 Juni tetap memiliki satu `USE` per presensi shift PH, tanpa duplikasi.
3. Jadwal PH pada tanggal berlaku terakhir masih boleh dibuat jika saldo tersedia.
4. Jadwal PH satu hari setelah tanggal berlaku terakhir ditolak oleh guard saldo.
5. Baris `VOID` tidak mengubah saldo grant, use, expiry, maupun saldo akhir.

## Batasan yang disengaja

Script tidak mengubah grant/use historis Finance yang sudah terjadi sejak 1 Juni, selain sembilan `USE` pra-cutover yang terbukti salah. Penggunaan setelah cutover tidak dibuat ulang sebagai baris baru agar tautan audit ke presensi tetap utuh; skrip memvalidasi ulang alokasi FIFO-nya terhadap saldo pembuka dari core.
