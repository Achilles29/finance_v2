# Setup & Keputusan Teknis — Finance App
**Terakhir diperbarui:** 2026-05-18

> Dokumen ini dibaca pertama kali saat pindah laptop atau mulai sesi baru. Berisi semua yang perlu dikonfigurasi dan keputusan teknis yang sudah final — tidak perlu didiskusikan lagi.

---

## DAFTAR ISI

1. [Setup Lokal](#1-setup-lokal)
2. [Konfigurasi Aplikasi](#2-konfigurasi-aplikasi)
3. [Database](#3-database)
4. [Cara Jalankan Migration SQL](#4-cara-jalankan-migration-sql)
5. [Keputusan Arsitektur yang Sudah Final](#5-keputusan-arsitektur-yang-sudah-final)
6. [Keputusan Bisnis yang Sudah Final](#6-keputusan-bisnis-yang-sudah-final)
7. [File yang Tidak Boleh Diedit Sembarangan](#7-file-yang-tidak-boleh-diedit-sembarangan)
8. [Tools & Script Maintenance](#8-tools--script-maintenance)
9. [Cara Tambah Modul Baru](#9-cara-tambah-modul-baru)
10. [Troubleshooting Umum](#10-troubleshooting-umum)

---

## 1. Setup Lokal

### Prasyarat
- XAMPP (Apache + MySQL/MariaDB + PHP 7.4+)
- PHP 7.4 atau lebih baru (cek: `php -v`)
- MySQL 5.7+ atau MariaDB
- Browser modern (Chrome/Edge/Firefox)
- VS Code (atau editor pilihan)

### Langkah Setup

**1. Clone / copy direktori**
```
c:\xampp\htdocs\finance\   ← direktori aplikasi
```

**2. Buat database**
```sql
CREATE DATABASE db_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**3. Jalankan semua SQL migration**
Lihat [Bagian 4](#4-cara-jalankan-migration-sql) untuk urutan eksekusi.

**4. Konfigurasi database**  
Edit file: `application/config/database.php`
```php
$db['default'] = [
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => '',        // sesuaikan password MySQL lokal
    'database' => 'db_finance',
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => TRUE,      // set FALSE di production
    'char_set' => 'utf8mb4',
    'dbcollat' => 'utf8mb4_unicode_ci',
];
```

**5. Konfigurasi base URL**  
Edit file: `application/config/config.php`
```php
// Lokal
$config['base_url'] = 'http://localhost/finance/';

// Production (sesuaikan domain)
// $config['base_url'] = 'https://finance.namuacoffee.com/';
```

**6. Pastikan mod_rewrite aktif**  
Di XAMPP: `httpd.conf` → uncomment `LoadModule rewrite_module`  
File `.htaccess` sudah ada di root `finance/`.

**7. Akses aplikasi**  
Buka: `http://localhost/finance/`  
Login dengan akun admin yang di-seed di SQL migration.

---

## 2. Konfigurasi Aplikasi

### File Konfigurasi Kunci

| File | Fungsi |
|---|---|
| `application/config/config.php` | Base URL, encryption key, session, timezone |
| `application/config/database.php` | Koneksi database |
| `application/config/routes.php` | URL routing — semua route didefinisikan di sini |
| `application/config/autoload.php` | Library, helper, model yang auto-load |
| `application/config/constants.php` | Konstanta global aplikasi |

### Timezone
```php
// Di application/config/config.php
date_default_timezone_set('Asia/Jakarta');
```

### Session
Session menggunakan file-based (default CI3). Durasi: sesuai `sess_expiration` di `config.php`.  
Jika ada masalah session di production, pastikan folder `application/cache/` writable.

---

## 3. Database

### Info Database
- **Nama:** `db_finance`
- **Charset:** `utf8mb4` (penting untuk emoji dan karakter khusus)
- **Engine:** InnoDB (semua tabel — untuk foreign key dan transaction support)

### Konvensi Prefix Tabel

| Prefix | Domain |
|---|---|
| `sys_` | Sistem: menu, konfigurasi, audit log |
| `auth_` | Autentikasi & RBAC |
| `org_` | Organisasi & HR |
| `att_` | Absensi |
| `pay_` | Payroll |
| `mst_` | Master data |
| `pur_` | Purchase |
| `inv_` | Inventori |
| `prd_` | Produksi |
| `pos_` | POS |
| `crm_` | CRM / loyalty |
| `fin_` | Keuangan |
| `rpt_` | Laporan |
| `aud_` | Audit trail |

### Backup Database
Backup manual: `mysqldump -u root db_finance > backup_YYYYMMDD.sql`  
Backup ada di folder `finance/` root (jangan simpan di direktori publik di production).

---

## 4. Cara Jalankan Migration SQL

### Urutan Eksekusi
File SQL di `finance/sql/` diberi nama dengan prefix tanggal + huruf.  
**Jalankan secara berurutan sesuai nama file** — jangan loncat.

```
sql/
├── 2026-05-01a_auth_rbac_schema.sql          ← jalankan pertama
├── 2026-05-01b_auth_rbac_seed.sql
├── 2026-05-02a_master_data_schema.sql
├── 2026-05-02b_master_data_seed.sql
├── ...
└── 2026-05-18x_....sql                       ← jalankan terakhir
```

### Cara Menjalankan

**Via phpMyAdmin:**
1. Buka `http://localhost/phpmyadmin`
2. Pilih database `db_finance`
3. Tab "SQL" → paste konten file SQL → Execute

**Via command line (lebih cepat untuk banyak file):**
```bash
# Windows (PowerShell atau CMD)
cd c:\xampp\htdocs\finance\sql
mysql -u root db_finance < 2026-05-01a_auth_rbac_schema.sql

# Jalankan semua sekaligus (urutan abjad = urutan tanggal)
Get-ChildItem *.sql | Sort-Object Name | ForEach-Object {
    mysql -u root db_finance < $_.FullName
    Write-Host "Executed: $($_.Name)"
}
```

### Jika Ada Error saat Migration
- Baca pesan error dengan seksama — biasanya foreign key constraint atau duplikat
- Jangan skip — perbaiki dulu sebelum lanjut ke file berikutnya
- Jika tabel sudah ada (`Table already exists`): cek apakah file ini sudah pernah dijalankan

---

## 5. Keputusan Arsitektur yang Sudah Final

Keputusan berikut **sudah final dan tidak dibuka kembali** kecuali ada alasan teknis yang sangat kuat.

### Database Terpisah dari `core`
- `finance` pakai `db_finance` — tidak share dengan `core`
- Alasan: skema `core` terlalu banyak legacy debt, susah di-refactor

### CodeIgniter 3 (Tidak Naik CI4)
- Tetap CI3 untuk konsistensi dengan `core` dan menghindari rewrite besar
- Upgrade CI4 bisa dipertimbangkan setelah semua modul selesai

### `mst_item` dan `mst_material` Dipisah
- Bukan digabung, tapi dihubungkan via `mst_material_item_source`
- Alasan: item pembelian bisa berganti-ganti (merk, kemasan) tapi material di resep harus stabil
- **Jangan ubah keputusan ini**

### UOM BELI ≠ UOM ISI
- UOM BELI (kemasan transaksi): BOTOL, DUS
- UOM ISI (satuan resep/stok): ML, GR, PCS
- Konversi disimpan di `mst_purchase_catalog`

### 3 Layer Stok (Gudang → Material → Komponen)
- Tidak boleh digabung
- Distribusi dari gudang ke material via `mst_material_item_source`

### RBAC Sederhana (Tidak Berlapis)
- Role → Permission (halaman + aksi)
- Employee → Role
- Override per employee jika perlu
- Tidak ada scope berlapis seperti di `core`

### Fetch API (Bukan jQuery $.ajax)
- Semua AJAX baru pakai Fetch API
- jQuery masih ada (dari Materio) tapi tidak untuk AJAX baru

### `att_daily` sebagai Single Source of Truth Absensi
- Semua kalkulasi payroll baca dari `att_daily` — tidak dari `att_presence`
- Lock otomatis saat period punya batch aktif

---

## 6. Keputusan Bisnis yang Sudah Final

### Komponen Gaji
| Komponen | Kode | Keterangan |
|---|---|---|
| Gaji Pokok | `BASIC` | Tetap setiap bulan |
| Tunjangan Jabatan | `POSITION` | Sesuai jabatan |
| Tunjangan Objektif | `OBJECTIVE` | KPI-based, bisa 0 |
| Lembur | `OVERTIME` | Per jam/hari |
| Potongan Terlambat | `LATE_DEDUCTION` | Menit × rate |
| Potongan Alpha | `ALPHA_DEDUCTION` | Hari × rate |
| Potongan Kasbon | `ADVANCE_DEDUCTION` | Cicilan kasbon |

### Format Nomor Dokumen (Tidak Berubah)
| Dokumen | Format |
|---|---|
| PO | `PO-{YYYYMM}-{NNN}` |
| Store Request | `SR-{YYYYMM}-{NNN}` |
| Gaji | `SLD-{YYYYMM}-{NNN}` |
| Kasbon | `KSB-{YYYYMM}-{NNN}` |
| Uang Makan | `UM-{YYYY}W{WW}-{NNN}` |
| NIP | `EMP-{YYYYMM}{NNN}` |

### Status Enum yang Sudah Standar
| Domain | Status yang Valid |
|---|---|
| PO | `DRAFT`, `APPROVED`, `ORDERED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `CLOSED`, `VOID` |
| Payroll Batch | `PENDING`, `PAID`, `VOID` |
| Kasbon | `DRAFT`, `APPROVED`, `DISBURSED`, `PARTIAL_SETTLED`, `SETTLED`, `VOID` |
| Absensi | `HADIR`, `TERLAMBAT`, `SAKIT`, `IZIN`, `CUTI`, `ALPHA`, `LIBUR`, `PH`, `PHB` |
| Kontrak | `DRAFT`, `APPROVED`, `SIGNED`, `EXPIRED`, `TERMINATED` |
| Umum | `ACTIVE`, `INACTIVE` |

---

## 7. File yang Tidak Boleh Diedit Sembarangan

| File | Kenapa |
|---|---|
| `application/core/MY_Controller.php` | Base controller — perubahan berdampak ke semua controller |
| `application/config/routes.php` | Perubahan route bisa break URL yang sudah berjalan |
| `application/views/layout/header.php` | Berdampak ke semua halaman |
| `application/views/layout/footer.php` | Berdampak ke semua halaman |
| `application/views/layout/main.php` | Berdampak ke semua halaman |
| `application/helpers/ui_number_helper.php` | `ui_num()` dipakai di ratusan tempat |
| `application/libraries/InventoryLedger.php` | Logic inti ledger inventori |
| `tools/remap_*_profile_keys_to_catalog.php` | Script satu kali — jangan dijalankan ulang tanpa dry-run |

**Jika harus mengedit file di atas:** selalu baca seluruh file dulu, pahami dampaknya, dan test semua halaman terkait setelah edit.

---

## 8. Tools & Script Maintenance

### tools/payroll_audit_checker.php
**Fungsi:** Validasi konsistensi data payroll.  
**Kapan dijalankan:** Kapanpun ada kecurigaan data payroll tidak konsisten, atau sebelum finalisasi period.
```
Akses: http://localhost/finance/tools/payroll_audit_checker.php
```

### tools/remap_division_profile_keys_to_catalog.php
**Fungsi:** Remap key stok divisi yang non-catalog ke canonical catalog key.  
**Status:** Sudah dijalankan. Jangan jalankan ulang tanpa dry-run.
```
Mode dry-run: ?mode=dry
Mode apply:   ?mode=apply
```

### tools/remap_warehouse_profile_keys_to_catalog.php
**Fungsi:** Remap key stok gudang ke canonical catalog key.  
**Status:** Sudah dijalankan parsial. Sisa konflik menunggu merge terukur.
```
Mode dry-run: ?mode=dry
Mode apply:   ?mode=apply
```

---

## 9. Cara Tambah Modul Baru

Checklist saat membuat modul baru:

**1. Buat file SQL migration**
```
finance/sql/YYYY-MM-DD[a]_{nama_modul}_schema.sql
finance/sql/YYYY-MM-DD[b]_{nama_modul}_seed.sql
```

**2. Buat Controller**
```php
// application/controllers/NamaModul.php
class NamaModul extends MY_Controller {
    const PAGE_INDEX = 'modul.index';
    ...
}
```

**3. Buat Model**
```
// application/models/NamaModul_model.php
```

**4. Buat folder View**
```
application/views/nama_modul/
    index.php
    detail.php
    form.php
```

**5. Tambahkan Route**
```php
// application/config/routes.php
$route['nama-modul']              = 'nama_modul/index';
$route['nama-modul/(:num)']       = 'nama_modul/detail/$1';
$route['nama-modul/create']       = 'nama_modul/create';
...
```

**6. Tambahkan Menu di Database**
```sql
INSERT INTO sys_menu (code, label, url, icon, parent_code, sort_order, is_active)
VALUES ('modul.index', 'Nama Modul', '/nama-modul', 'ri-icon-name', NULL, 10, 1);
```

**7. Daftarkan Permission**
```sql
INSERT INTO auth_permission (code, label, module, is_active)
VALUES ('nama-modul.index', 'Lihat Nama Modul', 'nama-modul', 1);
```

**8. Assign Permission ke Role**
Melalui UI halaman manajemen role, atau via SQL seed.

**9. Tulis dokumentasi di MODULES.md**
Tambahkan bagian baru di file `MODULES.md` untuk modul ini.

---

## 10. Troubleshooting Umum

### Sidebar tidak highlight menu aktif
**Sebab:** `active_menu` di controller tidak diset atau salah kode.  
**Solusi:** Pastikan `$data['active_menu'] = 'kode.menu.sesuai.database'` di setiap controller method.

### Permission denied padahal sudah di-assign
**Sebab:** Permission ter-cache di session. Butuh logout-login agar permission baru terbaca.  
**Solusi:** User logout lalu login kembali.

### Filter hilang saat ganti halaman pagination
**Sebab:** Pagination link tidak menyertakan parameter filter.  
**Solusi:** Pastikan `$buildQuery()` dipakai di link pagination (lihat CODING_STANDARDS.md Bagian 10).

### Flash message tidak muncul
**Sebab:** `set_flashdata` dipanggil tapi tidak ada `redirect()` setelahnya, atau view tidak di-render via `MY_Controller::render()`.  
**Solusi:** Flash message hanya berfungsi setelah redirect. Untuk AJAX gunakan `showAlert()`.

### AJAX 500 error
**Sebab:** Error PHP di endpoint. CI3 mungkin return HTML error page, bukan JSON.  
**Solusi:** Buka Network tab browser → lihat response body. Fix error PHP-nya. Pastikan `db_debug = TRUE` di config saat development.

### Stok tidak update setelah receipt PO
**Sebab:** `profile_key` di PO line belum di-remap ke canonical catalog key.  
**Solusi:** Jalankan `tools/remap_warehouse_profile_keys_to_catalog.php?mode=dry` dulu untuk lihat konflik.

### Data payroll tidak cocok antar halaman
**Sebab:** Tab detail payroll tidak baca dari snapshot disbursement.  
**Solusi:** Pastikan semua tampilan histori gaji baca dari `pay_payroll_batch_line.*_snapshot` — bukan kalkulasi ulang dari `att_daily`.
