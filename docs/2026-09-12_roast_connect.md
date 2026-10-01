# Finance ↔ NAMUA Roast Studio: katalog dan token

Token dibuat oleh **superadmin Finance**, melalui Administrasi & Audit → Integrasi Roast Studio (`system/roast-connect`). Tautan juga tersedia di Pengaturan Akun. Token bukan token login, password akun, atau password database.

## Pemakaian

1. Pilih nama koneksi, divisi pemilik stok, serta lokasi penggunaan. Untuk instalasi Namua saat ini: divisi ROASTERY, lokasi ROASTERY.
2. Centang izin akses katalog, pilih masa berlaku 30/90/365 hari, lalu klik **Buat token koneksi**. Tombol ini sekaligus menyimpan pilihan lingkup dan status. Token hanya tampil satu kali di respons pembuatan; salin sebelum meninggalkan halaman.
3. Pada Roast Studio → Integrasi, masukkan alamat dasar HTTPS Finance, misalnya `https://pos.namuacoffee.com`, serta token. Simpan dan uji koneksi, lalu tarik katalog.
4. Tandai green bean berdasarkan ID bahan dan simpan pilihan. Aktifkan penggunaan katalog Finance ketika pilihan sudah siap. Bahan lain tidak ditebak sebagai kopi berdasarkan nama.
5. Saat mulai roasting, Studio memeriksa saldo terbaru. Ini belum reservasi atau pemotongan stok. Sesi yang sudah berjalan tetap dapat direkam dan diselesaikan ketika Finance terputus.

## Pengelolaan akses

- Hanya superadmin yang dapat melihat pengaturan, membuat/mengganti token, dan mengubah lingkup. Form POST dilindungi token CSRF terpisah. Nomor revisi menolak perubahan dari halaman lama/perangkat lain.
- Finance menyimpan hash SHA-256 token acak 32 byte, akhiran empat karakter, tanggal pembuatan dan kedaluwarsa. Token asli tidak disimpan di database Finance, audit, sesi, URL, atau localStorage. JSON pembuatan token dan halaman pengaturan memakai `Cache-Control: no-store`.
- **Ganti token koneksi** langsung membatalkan token lama. Tempel token baru di Studio, uji ulang, dan tarik ulang katalog. Jika respons pembuatan hilang atau token terlupa, muat ulang lalu ganti token; token lama tidak bisa ditampilkan lagi.
- Menonaktifkan akses di Finance langsung menolak API walaupun token belum kedaluwarsa. Divisi yang dinonaktifkan juga menolak akses. Kejadian perubahan dicatat di `sys_roast_connect_audit` tanpa isi/hash token.
- Satu koneksi aktif per instalasi saat ini. Masa berlaku menggunakan UTC. Pengaturan tidak menulis stok/produksi, dan tidak membuka database ke server Studio.

## Kontrak antarserver

Protokol tetap `namua-finance/1`; autentikasi `Authorization: Bearer TOKEN` pada GET:

- `/index.php/roast_connect/health`: identitas instalasi, scope, kemampuan.
- `/index.php/roast_connect/catalog?page=1`: maksimal 500 bahan aktif per halaman.
- `/index.php/roast_connect/material?id=ID`: bahan dan saldo terbaru dalam scope.

API tidak memakai sesi login browser. `instance_id` dibuat sekali oleh migration dan dipertahankan saat pembaruan. Katalog membaca `mst_material`, `mst_uom`, dan lot terbuka `inv_material_fifo_lot` pada DIVISION/divisi/destination yang dipilih. Lot dengan satuan berbeda dari master membuat saldo tidak terverifikasi. Biaya/FIFO dan transaksi tidak dikirim. `posting_enabled=false` pada setiap respons.

Studio tetap memiliki database sendiri dan menyimpan token secara terenkripsi. Transport Studio saat ini mendukung server publik IPv4 dengan HTTPS port 443, tanpa redirect, dan memverifikasi TLS serta DNS. Reverse proxy Finance harus meneruskan Authorization ke PHP. Tidak diperlukan CORS antar aplikasi karena permintaan berjalan dari server ke server.

## Pemasangan dan pemindahan server

- Perubahan schema terdaftar pada `sql/2026-09-12a_roast_connect_catalog.sql` dan checksum pada migration catalog untuk clean install/upgrade. Tabel tambahan hanya `sys_roast_connect` dan `sys_roast_connect_audit`, beserta registrasi navigasi/izin khusus superadmin. Migration tidak mengaktifkan akses atau membuat token.
- Distribusikan controller `Roast_connect.php` dan `Roast_integrations.php`, model `Roast_connect_model.php`, view `system/roast_connect.php`, JS `assets/js/roast-connect-admin.js`, serta route `system/roast-connect`, `/save`, dan `/token`. Pengaturan akun memiliki tautan tambahan.
- Adapter awal yang memakai `/var/lib/finance-config/roast-connect.php` digantikan pengaturan database dan halaman admin. Versi ini tidak membaca file token lama tersebut. Jika adapter awal pernah dipakai pada server lain, buat token baru dari menu ini dan ganti pengaturan Studio.
- Saat Finance dipindahkan, migrasikan aplikasi dan database beserta tabel konektor; lindungi backup database karena mengandung hash token. Untuk perpindahan instalasi yang sama, pertahankan instance ID. Ubah alamat HTTPS di Studio dan isi ulang token (atau ganti token di Finance bila tidak menyimpan aslinya). Mengubah URL Studio membuang token tujuan lama untuk mencegah pengiriman ke host yang salah.
- Untuk pelanggan/instalasi baru, jangan menyalin token maupun baris identitas konektor pelanggan lama. Migration pada database kosong membuat identitas baru dan mode nonaktif.
- Backup kredensial Studio memerlukan database dan `storage/private/finance-link.key`; kunci tidak termasuk backup SQL otomatis. Tanpa kunci, isi ulang token melalui Integrasi.

## Batas versi ini

Katalog, klasifikasi bahan, dan pemeriksaan saldo tersedia. Pemilihan formula/component, reservasi, outbox, posting batch dan adjustment masih tahap lanjutan. Output produksi kelak tetap mengikuti formula Finance; susut fisik/spoil/sortasi dibukukan terpisah. Contoh formula 1.000 g → 1.000 g, hasil fisik 850 g: output batch buku 1.000 g dan adjustment terpisah −150 g.
