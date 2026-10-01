# Migrasi PH V3: Saldo Awal Core dan Rekonsiliasi Historis

## Tujuan

Migrasi ini memperbaiki saldo PH sejak Finance mulai dipakai pada `2026-06-01`.
Sumber saldo awal yang dipakai adalah `core.org_employee.ph`, bukan penjumlahan
seluruh grant lama di ledger Core.

Dengan pola ini, hak PH lama tidak terhitung berkali-kali. Grant Finance setelah
cutover tetap menjadi hak baru yang sah sesuai presensi pada hari libur nasional.

## Contoh Fairuz

Fairuz memiliki saldo awal Core `1 PH`.

| Tanggal | Kejadian | Dampak saldo |
| --- | --- | --- |
| Sebelum 1 Juni | Saldo awal dari Core | `+1` |
| 9 Juni | Memakai PH | `-1` |
| 16 Juni | Bekerja pada hari libur nasional | `+1` |
| 23 Juni | Memakai PH | `-1` |
| 17 Agustus | Bekerja pada hari libur nasional | `+1` |
| 18 Agustus | Memakai PH | `-1` |
| 25 Agustus | Bekerja pada hari libur nasional | `+1` |

Hasil akhirnya adalah `1 PH`, yaitu hak yang didapat pada `25 Agustus`.

## Perlakuan Riwayat Lama

Masa sebelum `2026-06-01` bukan pemakaian Finance. Semua penggunaan PH sebelum
tanggal tersebut disimpan sebagai `VOID`, tidak dihapus, agar jejak audit tetap
lengkap.

Terdapat tiga PH lama yang sudah disetujui sebelum guard saldo tersedia:

| Pegawai | Tanggal PH | Perlakuan V3 |
| --- | --- | --- |
| Bagas Bhakti .R | 19 Agustus | Koreksi saldo pembuka `+1` karena tidak ada grant berikutnya untuk menutupnya. Saldo akhir tetap `0`. |
| Fadilla Hartono Putri | 5 Agustus | Koreksi sementara, lalu ditutup oleh grant 17 Agustus. Saldo akhir tidak bertambah. |
| Fadilla Hartono Putri | 11 Agustus | Koreksi sementara, lalu ditutup oleh grant 25 Agustus. Saldo akhir tidak bertambah. |

Koreksi ini memiliki `entry_mode = MIGRATION` dan referensi khusus, sehingga
jelas berbeda dari grant atau penggunaan PH operasional biasa.

## Fallback Tanggal Lot

Empat pegawai memiliki saldo awal pada `core.org_employee.ph`, tetapi Core tidak
memiliki lot grant historis untuk menentukan tanggal asalnya. V3 tetap membawa
saldo yang resmi, memakai penanda cutover `2026-06-01`, dan masa berlaku tiga
bulan sesuai pengaturan Finance.

Pegawai yang memakai fallback:

- Anis Fitriya
- Catur Aris Widiyanto
- Mukhamad Anwar Fuadi
- Restika Dian L

Dengan aturan Finance, tanggal `expired_at` berarti "berlaku sampai". Lot ini
masih dapat dipakai pada tanggal tersebut dan otomatis expired pada hari
berikutnya.

## Urutan Eksekusi

1. Jalankan [preview V3](C:\xampp\htdocs\finance\sql\2026-08-27k_preview_ph_core_field_opening_balance_v3_reconciled.sql).
2. Pastikan kolom `temuan_fifo_setelah_koreksi` bernilai `0` dan Fairuz memiliki
   `estimasi_saldo_aktif = 1`.
3. Jalankan [apply V3](C:\xampp\htdocs\finance\sql\2026-08-27l_apply_ph_core_opening_balance_v3_reconciled.sql) satu kali.
4. Jalankan [audit akhir V3](C:\xampp\htdocs\finance\sql\2026-08-27m_audit_ph_core_opening_balance_v3_reconciled.sql).

Jangan menjalankan ulang script V1, V2, atau apply V3 setelah V3 selesai.
Apply V3 kedua kali sengaja berhenti dengan pesan `already=1` dan tidak mengubah
data. Itu adalah pengaman, bukan kegagalan data.

## Hasil Audit yang Diharapkan

Audit akhir harus menunjukkan:

- `apply_v3_selesai = 1`
- `temuan_integritas = 0`
- `expiry_yang_belum_tercatat = 0`
- `legacy_v1_v2_masih_aktif = 0`
- Fairuz: saldo ledger dan lot aktif `1`
- Bagas: saldo ledger dan lot aktif `0`
- Fadilla: saldo ledger dan lot aktif `0`

## Pengujian Manual Setelah Migrasi

1. Buka rekap PH dan pastikan saldo Fairuz adalah `1`.
2. Pastikan Bagas dan Fadilla tidak memiliki saldo negatif.
3. Buka ledger PH Bagas dan Fadilla. Koreksi historis harus terlihat sebagai
   mutasi migrasi, bukan grant kerja baru.
4. Coba jadwalkan shift PH untuk pegawai yang saldo PH-nya `0`. Sistem harus
   menolak jadwal tersebut.
5. Coba jadwalkan shift PH untuk pegawai yang masih memiliki saldo. Sistem harus
   menerima jadwal dan mengurangi saldo ketika presensinya final.
