# Panduan Operasional Payroll Disbursement & Kasbon (Finance)

Dokumen ini menjelaskan alur user untuk modul:
- `payroll/salary-disbursements`
- `payroll/cash-advances`

## 1) Alur Pencairan Gaji

### A. Generate/Refresh Payroll Period
Menu: `Payroll > Salary Disbursements` (bagian kiri)

Fungsi:
- Mengambil snapshot data payroll dari `att_daily` pada rentang tanggal.
- Menyimpan hasil per pegawai ke `pay_payroll_result`.

Kapan dipakai:
- Setiap ada perubahan absensi, lembur, manual adjustment, atau approval yang memengaruhi nilai gaji.

Output:
- Data period dan result per pegawai (gross, potongan, net) siap dicairkan.

### B. Generate Batch Pencairan Gaji
Menu: `Payroll > Salary Disbursements` (bagian kanan)

Fungsi:
- Membuat dokumen batch pencairan dari `pay_payroll_result` period terpilih.
- Menyiapkan baris transfer per pegawai ke `pay_salary_disbursement_line`.

Kapan dipakai:
- Setelah period sudah final dan angkanya dipastikan benar.

### C. Mark Paid / Void / Delete
Aksi pada daftar batch:
- `Mark Paid`: tandai baris PENDING/FAILED menjadi PAID.
- `Void`: batalkan batch yang belum PAID.
- `Delete`: hapus batch yang belum PAID dan tidak ada baris paid.

### D. Rekening Tujuan Tiap Pegawai
- Saat generate batch gaji, rekening tujuan transfer dibaca dari data bank masing-masing pegawai (`org_employee`).
- Jadi dalam 1 batch, tujuan transfer bisa beda-beda per pegawai.
- Rekening yang dipilih di form batch adalah rekening sumber perusahaan (opsional), bukan rekening tujuan pegawai.

Catatan penting mutation log:
- Jika batch memakai rekening perusahaan (`company_account_id` terisi), saat `Mark Paid` sistem akan:
  - mengurangi saldo `fin_company_account`,
  - menulis mutasi `OUT` ke `fin_account_mutation_log`.
- Jika rekening tidak diisi, status paid tetap bisa diproses tapi tidak ada posting mutasi rekening.

## 2) Kenapa nilai batch bisa beda dengan estimasi (contoh EKO)

Penyebab utama:
- Halaman estimasi membaca data absensi terkini (realtime).
- Batch gaji membaca snapshot dari period saat terakhir di-generate.

Solusi operasional:
1. Buka `payroll/salary-disbursements`.
2. Jalankan ulang `Generate/Refresh Payroll Period` untuk periode yang sama.
3. Cek detail result period.
4. Baru generate batch pencairan.

## 3) Kasbon (Revisi Lebih Fleksibel)

Menu: `Payroll > Cash Advances`

Perubahan skema operasional:
- `Tenor (bulan)` sekarang opsional.
- Isi `0` jika kasbon tidak punya tenor tetap (fleksibel).

Arti nilai tenor:
- `> 0`: sistem membuat rencana cicilan bulanan otomatis.
- `0`: tidak dibuat jadwal cicilan otomatis; pembayaran dicatat sesuai realisasi.

### Metode Pembayaran Kasbon
Saat posting pembayaran kasbon tersedia 3 metode:
1. `CASH`: pencairan tunai dari rekening sumber perusahaan.
2. `TRANSFER`: transfer dari rekening sumber perusahaan.
3. `SALARY_CUT`: dipotong dari gaji (tanpa mengurangi saldo rekening saat posting kasbon).

Catatan `SALARY_CUT`:
- Wajib isi tanggal potong gaji.
- Sistem membuat penyesuaian `DEDUCTION` otomatis pada tanggal tersebut (label: `Potongan Kasbon ...`).
- Nilai ini ikut masuk perhitungan estimasi/payroll sebagai pengurang THP.

### Input pembayaran kasbon
Di tabel kasbon tersedia form `Bayar`:
- Pilih cicilan tertentu jika pakai tenor.
- Pilih `Auto / fleksibel` untuk kasbon tanpa tenor (sistem buat entry pembayaran otomatis).

### Aksi data kasbon
- `Edit`: ubah data kasbon.
- `Void`: batalkan kasbon (hanya jika belum ada pembayaran).
- `Delete`: hapus kasbon (hanya jika belum ada pembayaran).

## 4) Relasi ke Mutation Log untuk Kasbon

Status saat ini:
- Pencatatan kasbon sudah mendukung rekening sumber kasbon.
- Untuk metode `CASH`/`TRANSFER`, posting pembayaran kasbon akan mengurangi saldo rekening dan menulis mutation log.
- Untuk metode `SALARY_CUT`, tidak ada mutasi rekening saat posting kasbon (karena dipotong di payroll).

Rekomendasi sementara:
- Jika pencairan kasbon dilakukan via rekening perusahaan, catat arus kas di `finance/mutations`.
- Setelah itu kasbon tetap dikelola di `payroll/cash-advances` untuk outstanding/cicilan.
