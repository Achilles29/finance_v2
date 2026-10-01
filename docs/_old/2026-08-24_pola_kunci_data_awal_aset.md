# Pola Kunci Data Awal Aset

## Tujuan

Saat pendataan aset masih berlangsung, data awal masih boleh diperbaiki langsung. Setelah data sudah benar, aset dikunci agar jumlah, harga, serial, dan data awal lain tidak dapat berubah diam-diam.

## Status Aset

| Status data awal | Arti | Yang boleh dilakukan |
| --- | --- | --- |
| Masih pendataan | Data belum final. | Edit data awal langsung bila pengguna memiliki hak edit aset. |
| Terkunci | Data awal telah disahkan. | Ajukan perubahan data; perubahan tidak langsung mengubah aset. |

Kunci data awal tidak menghentikan aktivitas aset. Lapor rusak, rekonsiliasi bulanan, mutasi, serah terima, maintenance, dan disposal tetap memakai formulir masing-masing supaya riwayat operasional tetap jelas.

## Cara Mengunci Aset

1. Selesaikan pemeriksaan data awal aset: nama, kategori, serial, harga, foto, dan catatan.
2. Buka `Asset Management > Daftar Aset`.
3. Centang produk atau unit aset yang sudah final.
4. Tekan `Kunci Produk Terpilih` atau `Kunci Unit Terpilih` pada halaman rincian produk.
5. Periksa jumlah yang terkunci pada notifikasi sistem.

Penguncian tersedia hanya untuk pengguna yang memiliki hak edit aset. Penguncian tidak mengubah jumlah fisik, nilai penyusutan, lokasi, kondisi, ataupun riwayat aset.

## Jika Data Terkunci Perlu Diperbaiki

1. Buka detail aset yang terkunci.
2. Tekan `Ajukan Perubahan`.
3. Isi nilai yang benar, alasan perubahan, dan bukti bila diperlukan.
4. Pengguna berwenang membuka `Perubahan Data`, lalu menyetujui atau menolak permintaan tersebut.
5. Setelah disetujui, tekan `Terapkan Perubahan`.

Sistem menyimpan data sebelum dan sesudah perubahan, alasan, pemohon, penyetuju, serta waktu penerapannya. Bila data awal sudah berubah lagi sebelum permintaan diterapkan, sistem menolak penerapan agar data baru tidak tertimpa tanpa sengaja.

## Batasan yang Disengaja

Perubahan data awal digunakan untuk identitas dan nilai aset: nama, kategori, brand, model, serial, batch, tanggal beli, harga, penyusutan, foto, dan catatan.

Untuk lokasi, PIC, kondisi rusak, pemeliharaan, dan penghapusan aset, tetap gunakan alur khusus yang sudah ada. Pemisahan ini penting agar setiap kejadian operasional memiliki riwayat sendiri.

## Pengujian Setelah Deploy

1. Jalankan migration `sql/2026-08-24b_asset_master_lock_and_change_request_foundation.sql`.
2. Pilih satu aset uji yang masih terbuka, lalu kunci dari daftar aset.
3. Pastikan tombol edit langsung berubah menjadi `Ajukan Perubahan`.
4. Ajukan satu koreksi kecil, setujui dengan akun berwenang, lalu terapkan.
5. Pastikan detail permintaan menampilkan nilai sebelum, nilai sesudah, alasan, dan status.
6. Uji tombol `Clear Filter` pada seluruh halaman Asset Management.
