# Koreksi Migrasi PH V2 dan Pemeriksaan Pegawai

> Status dokumen: V2 tidak lagi menjadi kebijakan akhir. V2 menjumlahkan seluruh
> grant historis Core dan membuat saldo pembuka terlalu besar. Gunakan
> [migrasi PH V3](2026-08-27_migrasi_ph_v3_saldo_awal_core_dan_rekonsiliasi.md),
> yang memakai `core.org_employee.ph` sebagai saldo awal resmi.
> Jangan menjalankan preview atau apply V2 lagi pada database aktif.

## Keputusan Aturan

Finance mulai digunakan pada **1 Juni 2026**. Untuk menjaga batas ini dengan tegas, aturan migrasi yang dipakai sekarang adalah:

1. Semua `GRANT` PH dari database Core sebelum 1 Juni dibawa sebagai lot historis ke Finance.
2. `USE` yang terjadi di Core sebelum 1 Juni tidak mengurangi saldo Finance.
3. Hanya `USE` di Finance pada atau setelah 1 Juni 2026 yang mengurangi lot PH secara FIFO.
4. Setiap lot lama tetap memakai tanggal `expired_at` asalnya. Jika tanggal itu sudah lewat pada hari pemeriksaan, sisa lot ditutup melalui mutasi `EXPIRE` berjejak.
5. Data V1 tidak dihapus. Grant dan expiry V1 dijadikan `VOID`, kemudian V2 membuat lot dan expiry yang sesuai aturan baru.

Aturan ini berarti saldo akhir tidak sekadar mengambil angka sisa dari Core. Core memberikan seluruh hak historis dan Finance menghitung pemakaiannya mulai tanggal go-live.

## Contoh Fairuz

Untuk **FAIRUZ SABRI RAFIF**, hasil V2 tetap **4 hari**. Ini bukan karena data Core diabaikan, melainkan karena perhitungannya sebagai berikut:

| Komponen | Hari |
| --- | ---: |
| Seluruh grant Core sebelum 1 Juni | 8 |
| Grant Finance sejak 1 Juni | 3 |
| Penggunaan Finance sejak 1 Juni | -3 |
| Lot historis yang telah expired | -4 |
| Saldo aktif | **4** |

Empat grant historis Fairuz telah expired, sedangkan tiga penggunaan setelah go-live memakai lot historis yang masih aktif pada tanggal penggunaan. Karena itu hasil akhirnya tetap empat hari, tetapi asal saldo dan expiry-nya sekarang jelas serta dapat diaudit per lot.

## Hasil Uji Pada Salinan Database

Skrip V2 diuji dua kali pada salinan database yang sudah berisi hasil V1.

| Pemeriksaan | Hasil |
| --- | --- |
| Grant Core V2 dimigrasikan | 48 lot / 48 hari |
| Grant V1 dinetralkan menjadi VOID | 33 baris |
| Expiry V1 dinetralkan menjadi VOID | 8 baris |
| Expiry V2 dibuat sesuai lot | 22 baris |
| Penggunaan aktif sebelum 1 Juni | 0 |
| Masalah FIFO pada simulasi | 0 |
| Jalankan ulang skrip V2 | Tidak menambah data apa pun |
| Procedure bantuan tertinggal | 0 |

Saldo hasil simulasi V2 pada tanggal pemeriksaan 27 Agustus 2026:

| Pegawai | Grant Core | Grant Finance | Pakai sejak Juni | Expiry | Saldo aktif |
| --- | ---: | ---: | ---: | ---: | ---: |
| Alfandita Auriel Ana | 1 | 0 | 0 | 1 | 0 |
| Bagas Bhakti .R | 5 | 3 | 5 | 1 | 2 |
| Carolus Aloysius E.S | 5 | 2 | 2 | 1 | 4 |
| Eko Budi Lestari | 5 | 3 | 1 | 3 | 4 |
| Fadilla Hartono Putri | 6 | 4 | 5 | 1 | 4 |
| Fairuz Sabri Rafif | 8 | 3 | 3 | 4 | 4 |
| Nur Aisyah Irvana Putri | 6 | 4 | 4 | 1 | 5 |
| Pak Fajar | 8 | 3 | 0 | 6 | 5 |
| Riza Dafa Alkhabsi | 2 | 0 | 0 | 2 | 0 |
| Yasinta Sindi S | 2 | 0 | 0 | 2 | 0 |

## Berkas yang Dipakai

1. [Preview V2](../sql/2026-08-27g_preview_ph_core_cutover_v2_all_grant_policy.sql) mensimulasikan perubahan tanpa menulis ledger.
2. [Apply V2](../sql/2026-08-27h_apply_ph_core_cutover_v2_all_grant_policy.sql) melakukan koreksi append-only setelah V1 sudah dijalankan.
3. [Audit seluruh pegawai](../sql/2026-08-27i_audit_ph_cutover_v2_all_employees.sql) memeriksa hasil per pegawai dan per lot setelah apply.

## Urutan Aman di Server

1. Ambil backup database terlebih dahulu.
2. Jalankan `2026-08-27g_preview_ph_core_cutover_v2_all_grant_policy.sql` dan pastikan tidak ada masalah mapping nama atau FIFO.
3. Jalankan `2026-08-27h_apply_ph_core_cutover_v2_all_grant_policy.sql`.
4. Jalankan `2026-08-27i_audit_ph_cutover_v2_all_employees.sql`.
5. Pada hasil audit, semua bagian temuan harus kosong atau bernilai nol.

Jangan menjalankan ulang `2026-08-27f_apply_ph_core_cutover_opening_balance.sql` untuk memperbaiki V2. Script `h` sudah secara khusus menetralkan hasil V1 dengan `VOID` dan dapat dijalankan ulang tanpa menggandakan saldo.
