# Fase B - Audit Jujur untuk Commit Terminal dan Defisit Write-Off

Tanggal: 26 Agustus 2026

## Tujuan

Merapikan hasil **Audit Integritas Stok** agar operator hanya mengejar masalah
yang benar-benar perlu diperbaiki. Fase ini hanya mengubah cara audit membaca
data. Tidak ada stok, lot, movement, HPP, pembayaran, jurnal, atau transaksi
lama yang diubah.

## Yang Diperbaiki

### Commit POS yang dibatalkan sebelum stok diposting

Sebelumnya: commit POS yang sudah `VOID` atau `REVERSED`, tetapi belum pernah
membuat movement stok, tetap tampil sebagai error merah `POS_COMMIT_LINE_TRACE`.
Padahal transaksi seperti ini berhenti sebelum stok sempat dipotong.

Setelah: audit membedakan dua kondisi berikut.

1. Commit sudah pernah memotong stok tetapi jejak movement/defisitnya hilang.
   Kondisi ini tetap **Perlu Tindakan**.
2. Commit dibatalkan sebelum worker memposting stok, lot, atau defisit.
   Kondisi ini tampil sebagai **Info Terminal** dan tidak memerlukan repair.

### Defisit yang ditutup administratif

Sebelumnya: audit menghitung sisa defisit dari kebutuhan, lot keluar,
penyelesaian, dan pembalikan. Kuantitas yang sudah ditutup administratif
(`written_off_qty`) belum ikut dikurangi, sehingga defisit yang sebenarnya
sudah selesai tampil merah.

Setelah: `written_off_qty` ikut masuk rumus sisa defisit. Status
`WRITTEN_OFF` juga dikenali sebagai status terminal selama sisa defisitnya nol.

### Tampilan hasil audit

Halaman `/inventory/stock/integrity-audit` sekarang memisahkan ringkasannya
menjadi:

1. **Perlu perhatian**: error yang memerlukan tindakan.
2. **Perlu ditelusuri**: peringatan operasional yang perlu dicek.
3. **Info terminal**: transaksi yang sudah selesai/dibatalkan secara benar dan
   tidak perlu diperbaiki.
4. **Total catatan audit**: gabungan seluruh catatan, termasuk informasi.

## File yang Berubah

1. `application/libraries/InventoryActiveMonthIntegrityAudit.php`
2. `application/controllers/Inventory_control.php`
3. `application/views/inventory/stock_integrity_audit_index.php`

Tidak ada migration atau SQL baru untuk Fase B.

## Hasil Uji Lokal

Pemeriksaan memakai query baca-saja yang sama dengan audit pada periode
Agustus 2026 menghasilkan:

| Pemeriksaan | Sebelum | Sesudah |
| --- | ---: | ---: |
| `POS_COMMIT_LINE_TRACE` | 2 error | 0 error |
| Commit terminal sebelum posting | tidak dipisahkan | 4 Info Terminal |
| `ACTIVE_DEFICIT_ARITHMETIC` | 5 error palsu | 0 error |

Empat commit terminal yang terdeteksi adalah `PSC-202608-0632`,
`PSC-202608-0634`, `PSC-202608-0656`, dan `PSC-202608-0657`. Audit memeriksa
bahwa semuanya tidak mempunyai jejak posting stok/lot/defisit, sehingga aman
ditampilkan sebagai informasi saja.

PHP lint berhasil untuk ketiga file yang diubah dan `git diff --check` bersih.

## Yang Perlu Dicoba Manual

1. Buka `/inventory/stock/integrity-audit`.
2. Pilih bulan `2026-08`, lalu tekan **Jalankan Audit**.
3. Pastikan `Commit POS dengan line tanpa jejak movement atau defisit` tidak
   merah hanya karena commit terminal di atas.
4. Pastikan `Commit POS dibatalkan sebelum stok diposting` tampil dengan badge
   **Info Terminal** dan tidak menawarkan tindakan repair.
5. Pastikan `Aritmetika defisit bulan aktif` tidak lagi merah hanya karena
   defisit berstatus **Ditutup administratif** dengan sisa nol.
6. Bila masih ada error lain, buka hanya error tersebut. Jangan membuat
   adjustment untuk menghilangkan baris Info Terminal.

## Catatan Lingkungan Lokal

Command audit CodeIgniter di CLI belum dapat dipakai pada komputer lokal ini
karena `application/config/database.php` menyimpan password MySQL yang berbeda
dengan MySQL lokal saat ini. Perubahan Fase B tidak mengubah file konfigurasi
tersebut. Jika ingin menjalankan audit CLI lokal, samakan konfigurasi database
lokal terlebih dahulu tanpa menyalin konfigurasi lokal ke server.
