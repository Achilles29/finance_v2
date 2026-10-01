# Audit PH / Public Holiday dan Guard Jadwal

Tanggal audit lokal: 27 Agustus 2026  
Data yang dibaca: sejak jadwal dan absensi pertama tersedia pada April 2026.  
Status: guard dan aturan expiry sudah diseragamkan di kode. Riwayat sebelum cutover Finance ditangani oleh migrasi saldo awal Core, bukan dihitung ulang dari data April-Mei Finance.

> Pembaruan cutover: gunakan [migrasi PH V3](2026-08-27_migrasi_ph_v3_saldo_awal_core_dan_rekonsiliasi.md). Finance mulai dipakai 1 Juni 2026, sehingga penggunaan PH sebelum tanggal tersebut tidak boleh mengurangi saldo Finance. V3 memakai `core.org_employee.ph` sebagai saldo awal resmi, bukan seluruh grant historis Core, lalu memvalidasi penggunaan setelah cutover dengan urutan FIFO.

## Aturan yang Dipakai

1. Pegawai mendapat **1 jatah PH** bila memenuhi seluruh syarat berikut:
   - tercatat eligible pada `att_ph_eligibility` pada tanggal tersebut;
   - hari tersebut adalah hari libur nasional aktif di `att_holiday_calendar`;
   - jadwalnya adalah shift kerja biasa, bukan `PH`/`PHB`;
   - absensinya final (`PRESENT` atau `LATE`) melalui presensi lokasi/manual atau pengajuan yang telah disetujui;
   - shift pada presensi final (`att_daily.shift_id`) juga bukan `PH`/`PHB`. Jadwal adalah rencana, sedangkan shift pada presensi final adalah fakta yang dipakai untuk menentukan grant atau penggunaan;
   - sesuai pengaturan saat ini, check-in dan check-out harus valid. Pengaturan ini dapat dilonggarkan dari Pengaturan Absensi bila perusahaan ingin check-in saja sudah cukup.
2. Shift `PH` adalah libur pengganti berbayar. Saat pegawai membuka halaman absensi pada hari jadwal PH, sistem membuat kehadiran `HOLIDAY` otomatis dan mencatat pengurangan **1 PH**.
3. Jatah yang didapat akan kedaluwarsa tiga bulan setelah tanggal grant, sesuai pengaturan aktif saat audit.
4. Satu pegawai tidak boleh memiliki lebih dari 26 hari jadwal kehadiran dalam satu bulan, termasuk PH. Posisi `SECURITY` dikecualikan. Lebih dari batas hanya dapat disetujui Superadmin dengan alasan yang dicatat.

## Masalah Pola Lama

1. **Grant dan penggunaan PH tertukar.**
   Sebagian riwayat lama membuat `GRANT` ketika pegawai memakai shift PH. Seharusnya peristiwa ini adalah `USE` atau pengurangan jatah.
2. **PH yang dipakai tidak selalu mencatat pengurangan.**
   Membuka halaman absensi dapat membuat kehadiran `HOLIDAY`, tetapi belum selalu membuat baris `USE` pada ledger.
3. **Grant hari libur reguler tidak tersinkron dari semua jalur presensi.**
   Presensi lokasi/manual dan pengajuan absensi yang disetujui belum selalu membuat grant secara idempotent.
4. **Admin dapat menjadwalkan PH melebihi saldo.**
   Jadwal PH masa depan belum dihitung sebagai reservasi, sehingga saldo yang sama dapat dijadwalkan beberapa kali.
5. **Tidak ada guard 26 hari.**
   Jadwal bulan lama menunjukkan beberapa pegawai non-Security memiliki lebih dari batas 26 hari.
6. **Jadwal yang sudah menghasilkan absensi final masih dapat diubah.**
   Ini berbahaya karena mengubah arti kehadiran, gaji, dan ledger PH setelah fakta terjadi.
7. **Sinkron massal berisiko menyentuh payroll lama.**
   Tombol sinkron sebelumnya bisa dipakai untuk periode lama dan dapat menambah grant tanpa proses rekonsiliasi yang disetujui.
8. **Eligibility dapat berubah ketika masih ada PH masa depan.**
   Ini dapat membuat jadwal PH sudah ada tetapi hak pegawainya kemudian dicabut.

## Perbaikan yang Dibuat

1. Grant otomatis sekarang hanya berasal dari **shift reguler pada hari libur nasional**, untuk pegawai eligible dengan absensi final yang valid. Engine memeriksa shift jadwal dan `att_daily.shift_id`; data PH pada absensi final tidak pernah menjadi grant.
2. Penggunaan PH sekarang selalu ditautkan ke baris `att_daily` yang benar dan membaca `att_daily.shift_id` sebagai fakta penggunaan. Reload halaman, presensi manual, dan approval pengajuan aman diulang karena ledger memakai referensi unik.
3. Saat memasukkan jadwal PH, sistem menghitung:
   - saldo ledger yang masih aktif;
   - PH masa lalu yang sudah benar-benar hadir tetapi belum pernah tercatat sebagai `USE`;
   - PH hari ini dan masa depan yang sudah dijadwalkan sebagai reservasi.
   Karena itu satu jatah tidak bisa dijadwalkan berkali-kali.
4. Jika saldo tidak cukup, kehadiran otomatis PH tidak dibuat untuk data baru dan admin tidak bisa menjadwalkan PH baru.
5. Guard 26 hari diterapkan pada halaman jadwal biasa, bulk, dan spreadsheet. Superadmin dapat melakukan override dengan alasan; rekamannya disimpan di `att_schedule_monthly_override`.
6. Jadwal yang sudah memiliki presensi final (`PRESENT`, `LATE`, atau `HOLIDAY`) tidak dapat diganti atau dihapus. Koreksi harus dilakukan dari jalur koreksi absensi agar histori tidak putus.
7. Sinkron grant massal dibatasi untuk **bulan berjalan**. Riwayat lama wajib dibaca lewat Audit PH dan hanya boleh dikoreksi setelah disetujui.
8. Eligibility PH tidak dapat dinonaktifkan, dihapus, atau dimundurkan tanggal berlakunya bila pegawai masih memiliki jadwal PH hari ini/masa depan yang terdampak.
9. Migration menambahkan perlindungan unik satu jadwal per pegawai per tanggal dan perlindungan idempotensi mutasi PH otomatis.

### Cara Sistem Membaca Saldo untuk Jadwal Baru

Riwayat ledger lama **tidak dihapus atau diubah otomatis**. Namun, agar jadwal baru tidak salah, sistem memakai saldo efektif saat memvalidasi PH:

1. Grant otomatis lama yang ternyata berasal dari absensi `shift_id = PH` diabaikan untuk perhitungan jadwal baru, karena itu sebenarnya bukan hak baru.
2. Kehadiran hari libur nasional yang benar tetapi belum sempat memperoleh grant dihitung sebagai hak virtual untuk validasi jadwal baru. Baris ledgernya tetap belum dibuat sampai rekonsiliasi historis disetujui.
3. PH yang sudah hadir di masa lalu tetapi belum memiliki `USE`, serta jadwal PH hari ini/masa depan, diperlakukan sebagai pemakaian atau reservasi.
4. Grant yang sudah lewat masa expired tidak dapat dipakai, walaupun baris `EXPIRE` fisiknya belum sempat ditulis ke ledger.

Karena itu angka `Saldo` di halaman Ledger PH tetap merupakan **riwayat tercatat**, sedangkan keputusan boleh/tidaknya menjadwalkan PH memakai saldo efektif yang lebih aman. Halaman ledger sekarang menampilkan penjelasan ini agar tidak membingungkan.

## Temuan Riwayat Lokal

Hasil ini adalah hasil basis data lokal pada tanggal audit. Jalankan SQL audit yang sama di server sebelum menentukan koreksi riwayat server.

| Temuan | Jumlah |
| --- | ---: |
| Hadir libur nasional valid tetapi belum mendapat grant | 24 |
| Shift PH sudah hadir tetapi belum tercatat sebagai penggunaan | 31 |
| Grant otomatis lama yang salah berasal dari shift PH | 12 |
| Presensi hari libur yang perlu review manual | 3 |
| Jadwal PH tanpa eligibility aktif | 0 |
| Jadwal PH dan shift presensi final tidak selaras | 0 |
| Kelompok jadwal non-Security melebihi 26 hari/bulan | 32 |

### Hak PH yang Seharusnya Masuk tetapi Belum Ada

| Tanggal | Pegawai |
| --- | --- |
| 16 Juni 2026 | Ahmad Setiawan, Bagas Bhakti R., Carolus Aloysius E.S, Eko Budi Lestari, Fadilla Hartono Putri, Fairuz Sabri Rafif, Nayla Syahfiqah, Nur Aisyah Irvana Putri, Pak Fajar, Siti Rhimah |
| 17 Agustus 2026 | Ahmad Setiawan, Bagas Bhakti R., Eko Budi Lestari, Fadilla Hartono Putri, Fairuz Sabri Rafif, Nayla Syahfiqah, Nur Aisyah Irvana Putri, Pak Fajar, Siti Rhimah |
| 25 Agustus 2026 | Eko Budi Lestari, Fadilla Hartono Putri, Fairuz Sabri Rafif, Nur Aisyah Irvana Putri, Siti Rhimah |

### PH yang Sudah Dipakai tetapi Belum Mengurangi Jatah

| Pegawai | Jumlah hari | Tanggal yang perlu ditinjau |
| --- | ---: | --- |
| Bagas Bhakti R. | 6 | 07 Apr, 23 & 26 Jun, 03, 18 & 19 Agu |
| Carolus Aloysius E.S | 3 | 12 Mei, 05 & 23 Jun |
| Eko Budi Lestari | 1 | 08 Jun |
| Fadilla Hartono Putri | 6 | 06 Apr, 10 Jun, 06 & 30 Jul, 05 & 11 Agu |
| Fairuz Sabri Rafif | 7 | 07 Apr, 05, 12 & 19 Mei, 09 & 23 Jun, 18 Agu |
| Nur Aisyah Irvana Putri | 6 | 09 Apr, 07 Mei, 04 Jun, 21 Jul, 06 & 18 Agu |
| Ahmad Setiawan | 2 | 13 Jul, 04 Agu |

### Grant Otomatis yang Salah karena Dibuat Saat Memakai PH

| Pegawai | Jumlah baris | Tanggal |
| --- | ---: | --- |
| Ahmad Setiawan | 2 | 13 Jul, 04 Agu |
| Bagas Bhakti R. | 3 | 03, 18, 19 Agu |
| Fadilla Hartono Putri | 4 | 06 & 30 Jul, 05 & 11 Agu |
| Nur Aisyah Irvana Putri | 3 | 21 Jul, 06 & 18 Agu |

### Presensi yang Perlu Dicek Manual

| Pegawai | Tanggal | Alasan |
| --- | --- | --- |
| Fairuz Sabri Rafif | 01 Jun 2026 | Check-out tercatat 17 Jun; jelas di luar rentang shift. |
| Bagas Bhakti R. | 25 Agu 2026 | Check-out belum ada. |
| Pak Fajar | 25 Agu 2026 | Check-in belum ada/tidak berada pada tanggal kerja. |

### Konsistensi Jadwal dan Presensi PH

Audit lokal tidak menemukan kasus jadwal PH yang berbeda dengan shift PH pada presensi final. Pemeriksaan ini tetap ada di SQL audit server karena data lama atau proses approval yang tidak lengkap berpotensi menghasilkan perbedaan tersebut.

### Jadwal Melebihi Batas 26 Hari

Ada 32 kelompok pegawai-bulan non-Security di atas batas. Polanya terkonsentrasi pada April--Agustus dan terutama Bagas, Fadilla, Ahmad, Carolus, Eko, Fairuz, Nur, Siti, Firman, Michael, dan Zella. Ini adalah riwayat lama; sistem baru tidak mengubahnya otomatis. Laporan SQL menampilkan daftar lengkap pegawai dan bulan untuk ditinjau.

## Cara Deploy

1. Backup database terlebih dahulu.
2. Jalankan [2026-08-27a_ph_public_holiday_schedule_guard_foundation.sql](../sql/2026-08-27a_ph_public_holiday_schedule_guard_foundation.sql) pada lokal dan server. Script aman dijalankan ulang.
3. Deploy perubahan PHP bersama migration tersebut.
4. Jalankan [2026-08-27b_audit_ph_historical_reconciliation.sql](../sql/2026-08-27b_audit_ph_historical_reconciliation.sql) pada server. Script ini **hanya membaca data**.
5. Bandingkan hasil server dengan daftar lokal ini.
6. Setelah manajemen menyetujui tiap koreksi, buat dan jalankan script rekonsiliasi historis terpisah. Jangan menjalankan koreksi otomatis sekarang karena ada PH lama yang sudah dipakai lebih banyak daripada grant yang tercatat dan koreksi dapat memengaruhi perhitungan payroll/bonus lama.

## Rekonsiliasi Riwayat yang Disetujui

Migration `2026-08-27a` hanya memasang guard dan audit `2026-08-27b` hanya membaca data. Keduanya memang **tidak mengubah** grant, penggunaan, atau saldo PH lama.

Jika perusahaan menyetujui pembetulan riwayat, gunakan tahap berikut secara berurutan:

1. Jalankan [2026-08-27c_preview_ph_historical_ledger_reconciliation.sql](../sql/2026-08-27c_preview_ph_historical_ledger_reconciliation.sql). Script ini hanya menampilkan grant/use yang akan ditambahkan dan grant salah yang akan dikoreksi.
2. Review terutama pegawai yang estimasi saldonya menjadi negatif. Saldo negatif tidak disembunyikan karena berarti penggunaan PH lama lebih banyak daripada hak yang dapat dibuktikan.
3. Setelah hasil preview disetujui, backup database lagi lalu jalankan [2026-08-27d_apply_ph_historical_ledger_reconciliation.sql](../sql/2026-08-27d_apply_ph_historical_ledger_reconciliation.sql).
4. Script apply tidak menghapus baris ledger. Untuk 12 bug lama, tipe transaksi baris asal dikoreksi dari `GRANT` menjadi `USE` dan snapshot sebelum/sesudahnya disimpan di `att_ph_ledger_reconciliation_audit`.
5. Jalankan audit `2026-08-27b` lagi. Sisa temuan adalah data ambigu atau presensi yang memang belum valid untuk menerima PH.

Contoh Eko pada audit lokal: tiga grant valid (16 Juni, 17 dan 25 Agustus) serta satu penggunaan PH (8 Juni) akan menghasilkan saldo ledger `2`, karena belum ada PH masa depan yang direservasi dan belum ada grant yang expired pada tanggal audit.

## Skenario Uji Manual

1. Pilih pegawai eligible, jadwalkan shift reguler pada hari libur nasional aktif, lalu lakukan check-in dan check-out valid. Di Ledger PH harus muncul `GRANT +1` sekali saja.
2. Pilih pegawai dengan saldo PH 1. Jadwalkan satu PH pada tanggal depan: harus berhasil. Jadwalkan PH kedua sebelum ada grant baru: harus ditolak karena saldo sudah direservasi.
3. Pilih pegawai dengan saldo PH 0. Input jadwal PH harus ditolak.
4. Pada hari jadwal PH dengan saldo cukup, buka `/my/attendance`. Kehadiran harus menjadi `HOLIDAY` dan Ledger PH harus menambah `USE 1`.
5. Jadwalkan hari ke-27 untuk pegawai non-Security. Admin/HOD harus ditolak. Superadmin hanya dapat melanjutkan setelah memberi alasan override.
6. Jadwalkan lebih dari 26 hari untuk Security. Sistem tetap mengizinkan.
7. Coba ubah atau hapus jadwal yang sudah memiliki presensi final. Sistem harus menolak dan meminta koreksi melalui jalur absensi.
8. Buat jadwal PH tanggal depan untuk pegawai eligible, lalu coba nonaktifkan/hapus eligibility PH pegawai tersebut. Sistem harus menolak sampai jadwal PH tersebut diganti atau dihapus.
9. Dari Ledger PH, coba masukkan rentang sinkron bulan lalu. Sistem harus menolak. Rentang bulan berjalan tetap dapat disinkron tanpa membuat grant ganda.

## Catatan Operasional

- `Grant wajib check-out` pada Pengaturan Absensi saat ini aktif. Artinya check-in tanpa check-out tidak memberikan PH sampai absensi dilengkapi/disetujui. Jika kebijakan perusahaan menganggap check-in saja cukup, matikan opsi tersebut dengan sadar.
- Masa kedaluwarsa dihitung dari tanggal grant, bukan dari tanggal admin membuka halaman ledger.
- Untuk keamanan saldo, validasi PH sudah menghormati `expired_at` meskipun baris audit `EXPIRE` belum sempat ditampilkan. Membuka halaman Ledger/Rekap PH juga akan menuliskan baris `EXPIRE` yang tertunda.
- Audit ini dan perbaikan guard tidak mengubah 24 grant yang belum tercatat, 31 penggunaan yang belum tercatat, maupun 12 grant lama yang salah. Koreksi data historis harus menjadi tahap terpisah karena dapat memengaruhi payroll dan bonus periode lama.
# Pembaruan Cutover 27 Agustus 2026

Rekonsiliasi historis versi awal terbukti ikut membaca presensi Finance April-Mei, padahal cutover aplikasi adalah `2026-06-01`. Perbaikan final tidak lagi memakai periode itu sebagai sumber hak atau penggunaan Finance. Lihat [migrasi saldo awal PH dari Core](2026-08-27_migrasi_saldo_awal_ph_core_dan_expiry.md) untuk preview, apply, aturan expiry, dan langkah pengujian yang benar.
