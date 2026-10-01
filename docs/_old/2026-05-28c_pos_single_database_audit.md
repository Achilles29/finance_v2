# Audit POS Single Database
**Tanggal:** 2026-05-28  
**Tujuan:** memetakan dependensi modul POS yang masih mengarah ke database `core`, membandingkannya dengan `db_finance`, dan menentukan tabel mana yang harus dimigrasikan agar POS benar-benar cukup memakai satu database fisik.

## 1) Ringkasan Eksekutif
Saat ini aplikasi sudah bisa dibuat memakai **satu konfigurasi koneksi** karena group `core` sudah dibuat opsional dan otomatis fallback ke `default`.

Namun, untuk menjadi **satu database fisik penuh**, kondisi saat ini masih belum selesai karena:
1. beberapa tabel POS/member masih hanya ada di database `core`
2. beberapa tabel sudah ada di `db_finance` tetapi masih kosong
3. ada beberapa master yang secara operasional lebih aman tetap memakai copy lokal `finance`, bukan tetap bergantung ke `core`

Kesimpulan:
1. **Masalah config koneksi:** selesai
2. **Masalah konsolidasi tabel ke satu database fisik:** masih perlu migrasi bertahap

## 2) Tabel POS yang Masih Dipakai `Pos_model`
Audit kode pada [Pos_model.php](c:\xampp\htdocs\finance\application\models\Pos_model.php) menunjukkan dependensi utama berikut:

1. `crm_customer`
2. `crm_member_account`
3. `m_bank_account`
4. `pos_outlet`
5. `pos_terminal`
6. `pos_payment_method`
7. `pos_printer`
8. `pos_printer_profile`
9. `pos_printer_content_setting`
10. `pos_printer_template`
11. `pos_printer_template_master`

## 3) Perbandingan `db_finance` vs `core`
Hasil cek tabel dan jumlah data:

1. `crm_customer`
- `db_finance`: tidak ada
- `core`: ada, `147` row

2. `crm_member_account`
- `db_finance`: tidak ada
- `core`: ada, `146` row

3. `m_bank_account`
- `db_finance`: tidak ada
- `core`: ada, `7` row

4. `pos_outlet`
- `db_finance`: ada, `0` row
- `core`: ada, `1` row

5. `pos_terminal`
- `db_finance`: ada, `0` row
- `core`: ada, `1` row

6. `pos_payment_method`
- `db_finance`: ada, `0` row
- `core`: ada, `16` row

7. `pos_printer`
- `db_finance`: tidak ada
- `core`: ada, `4` row

8. `pos_printer_profile`
- `db_finance`: ada, `0` row
- `core`: ada, `4` row

9. `pos_printer_content_setting`
- `db_finance`: ada, `0` row
- `core`: ada, `4` row

10. `pos_printer_template`
- `db_finance`: ada, `0` row
- `core`: ada, `4` row

11. `pos_printer_template_master`
- `db_finance`: ada, `7` row
- `core`: ada, `1` row

## 4) Klasifikasi Migrasi
### 4.1 Wajib Dipindah agar POS tidak tergantung `core`
1. `crm_customer`
2. `crm_member_account`
3. `m_bank_account`
4. `pos_printer`

Alasan:
1. tabel tidak ada di `db_finance`
2. fallback ke `default` akan tetap error bila modul menyentuh tabel ini

### 4.2 Sudah ada di `db_finance`, tetapi harus diisi/dirapikan
1. `pos_outlet`
2. `pos_terminal`
3. `pos_payment_method`
4. `pos_printer_profile`
5. `pos_printer_content_setting`
6. `pos_printer_template`

Alasan:
1. schema lokal sudah ada
2. data lokal belum ada
3. kalau koneksi benar-benar disatukan, halaman akan tampil kosong walau tidak error

### 4.3 Sudah siap dipakai lokal, tapi perlu sanity check
1. `pos_printer_template_master`

Alasan:
1. tabel lokal ada
2. data lokal sudah ada
3. namun jumlah row lokal dan `core` berbeda, jadi perlu verifikasi apakah struktur/seed lokal memang sengaja lebih kaya

## 5) Prioritas Migrasi yang Disarankan
### Prioritas A - Supaya halaman master POS stabil di satu DB
1. `crm_customer`
2. `crm_member_account`
3. `pos_printer`
4. `pos_outlet`
5. `pos_terminal`
6. `pos_payment_method`

Dampak:
1. halaman `Member POS`
2. halaman `Payment Method POS`
3. halaman `Outlet + Terminal POS`
4. halaman `Printer POS`

akan bisa hidup penuh dari `db_finance`

### Prioritas B - Supaya printer POS benar-benar utuh lokal
1. `pos_printer_profile`
2. `pos_printer_content_setting`
3. `pos_printer_template`
4. `pos_printer_template_master`

Dampak:
1. live preview printer
2. test print
3. profile output
4. template receipt/KOT/refund/void

tidak lagi tergantung `core`

### Prioritas C - Supaya relasi payment method bersih
1. `m_bank_account`

Catatan:
1. kalau payment method POS tetap perlu menaut ke rekening perusahaan, maka kita perlu putuskan apakah:
- `m_bank_account` ikut dipindah
- atau mapping dialihkan ke tabel keuangan lokal yang sudah menjadi sumber kebenaran di `finance`

## 6) Rekomendasi Arsitektur
Rekomendasi terbaik untuk repo `finance`:

1. **Member**
- pindahkan `crm_customer` dan `crm_member_account` ke `db_finance`
- setelah itu halaman `/pos/members` pakai lokal penuh

2. **Printer**
- jadikan semua tabel printer POS lokal di `db_finance`
- ini paling sehat karena printer adalah perangkat operasional outlet, bukan master lintas sistem

3. **Outlet/Terminal**
- pakai lokal `finance`
- sinkron sekali dari `core` boleh, tapi sumber bacanya tetap lokal

4. **Payment Method**
- idealnya lokal juga
- tetapi rekening acuannya perlu diputuskan lebih dulu

## 7) Checklist Implementasi Satu Database Fisik
1. buat SQL migrasi copy schema + copy data untuk tabel yang belum ada
2. backfill data `core -> db_finance`
3. ubah `Pos_model` agar default baca lokal penuh
4. jadikan `core` hanya fallback sementara selama masa transisi
5. setelah data lokal stabil, hentikan dependency `core` satu per satu

## 8) Rekomendasi Tahap Berikutnya
Urutan paling aman:

1. migrasi `pos_printer` + seed/copy data printer
2. migrasi `crm_customer` + `crm_member_account`
3. migrasi `pos_outlet`, `pos_terminal`, `pos_payment_method`
4. putuskan nasib `m_bank_account` vs tabel keuangan lokal
5. setelah itu baru hapus dependency baca `core` di modul POS
