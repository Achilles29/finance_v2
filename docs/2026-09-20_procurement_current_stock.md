# Kontrol stok sekarang pada pengajuan divisi, PO dan SR

Tanggal: 20 September 2026. Perubahan source Finance; tidak mengubah database aktif, saldo, transaksi, Control atau instalasi customer.

**Koordinasi lanjutan Control:** [audit gabungan procurement + updater](../../control/docs/2026-09-20_audit_gabungan_procurement_updater.md) memeriksa source final termasuk dua perbaikan terakhir di bawah. Allowlist kandidat dilengkapi journal/preflight U1; hash profil/manifest pada catatan awal dokumen ini adalah histori sebelum penggabungan. Release resmi 74 ditahan karena dependensi procurement pada TAR lama tidak lengkap. Tidak mengubah SQL, proses bisnis, data customer atau paket Starter.

## Hasil

- [x] Form buat/edit/verifikasi pengajuan: stok **divisi/lokasi** dan **gudang** langsung di sebelah nama barang, dalam satuan isi yang sama dengan pengajuan.
- [x] Detail pengajuan dan tab rincian purchase memakai pembaca stok yang sama. Daftar per dokumen menampilkan paling banyak dua baris pratinjau yang termuat, dengan tautan stok seluruh barang; bukan penjumlahan satuan berbeda.
- [x] PDF/print memiliki kolom stok sekarang, waktu pembacaan, catatan arti saldo, lebar kolom tetap dan header tabel berulang saat mencetak.
- [x] Snapshot gudang saat pengajuan tetap dipertahankan dan diberi nama yang berbeda; bukan diganti seolah-olah saldo terkini.
- [x] PO/SR manual: stok otomatis diperiksa ketika barang, jumlah, satuan atau tujuan berubah; diperiksa ulang sebelum simpan, dengan dialog konfirmasi bila perlu. PO dengan stok gudang tersisa menyarankan mempertimbangkan SR.
- [x] Detail PO/SR manual juga menampilkan saldo terkini. PO tujuan `GUDANG` tidak membutuhkan divisi tujuan untuk membaca stok gudang.
- [x] Barang yang baru dipilih masih dapat menampilkan stok walaupun isian belum lengkap; belum mendapat token verifikasi sampai valid.
- [x] Pembacaan gagal/konversi belum ada/tidak ada catatan saldo ditampilkan **Belum diketahui**, bukan nol. HTTP timeout dapat dicoba ulang; respons lama tidak mengganti hasil terbaru.
- [x] Verifikasi purchase tetap memeriksa bukti bertanda tangan di server, actor/request/lokasi/isi pengajuan, saldo baru, dan batas 15 menit. Stok tersisa/negatif/tidak pasti tetap memerlukan pihak konfirmasi dan alasan. Hasil disimpan pada tabel review yang sudah ada.
- [x] PR-01: edit ditolak jika verifikasi lebih dahulu selesai, dengan lock dan pemeriksaan ulang sebelum header/baris ditulis. PR-02: kegagalan lookup item yang ditandai operasional tidak boleh menghilangkan pemeriksaan material. PR-03: timeout preview tidak mengunci tombol selamanya.

**Arti “masih banyak”:** jika saldo tujuan dalam satuan isi >= jumlah pengajuan, tampil “stok masih mencukupi”; jika positif tetapi lebih kecil, tampil “stok masih tersisa”. Tidak menambah angka minimum stok arbitrer atau larangan membeli. Saldo bersumber dari catatan monthly-stock canonical terbaru sampai bulan berjalan, lintas profil material, bukan hitung fisik, saldo bebas reservasi, atau prediksi kebutuhan. BAR dan BAR_EVENT tetap terpisah. Tidak ada rebuild/repair persediaan.

Konfirmasi PO/SR manual adalah kontrol UI sebelum simpan, **bukan** kebijakan baru yang menolak endpoint simpan manual berdasarkan jumlah stok. Bukti konfirmasi wajib di server tetap berlaku pada **verifikasi pengajuan divisi**. Mode edit pembayaran-saja PO tidak dipaksa mengulang kontrol kebutuhan barang.

## Validasi

- PHP 8.1 lint file berubah dan file baru; JavaScript syntax; `git diff --check`.
- `procurement_current_stock_smoke.php`: 91 pemeriksaan kumulatif, termasuk 52 pengujian pembaca awal; SQLite memory, tanpa koneksi ke DB Finance.
- `procurement_stock_review_verify_smoke.php`: 151 pemeriksaan; model/controller aktual dengan SQLite dan boundary pembentukan PO sintetis. Termasuk RBAC, POST, CSRF dan kegagalan API PO/SR manual sebelum stock-read.
- `procurement_stock_review_review_probe.php`: tidak lagi mereproduksi PR-01/PR-02. Interleaving sintetis, bukan klaim telah diuji beban konkurensi MariaDB produksi.
- `procurement_stock_review_client_smoke.cjs`: 22 pemeriksaan, termasuk timeout/retry dan pembatalan bukti lama.
- `procurement_stock_browser.cjs`: Chrome nyata, empat template produksi dirender dengan data/network sintetis; 15 pemeriksaan. Stok tampil pada form divisi, SR, PO; batal konfirmasi tidak mengirim simpan; PDF berhasil dihasilkan. PO/SR manual 201 baris dibaca dalam tiga request tanpa membatasi jumlah baris dokumen menjadi 100. Bukti contoh `/tmp/finance-stock-browser-CW8fZt/pengajuan-fixture.pdf` dan PNG. Bukan login/customer E2E.
- Feature-boundary contract: 2.146 PASS; Roastery/mobile regression: 48 PASS.
- Global quality gate dan build customer terisolasi: lihat pembaruan hasil akhir di bawah. Putaran global pertama mengungkap daftar tes kontrak belum diperbarui untuk dua regresi baru; diperbaiki tanpa mengurangi pemeriksaan.

## Checklist UAT pengguna

- [ ] Divisi membuat PO/SR bahan baku. Begitu barang dipilih, cek stok di kolom sebelah nama; ubah jumlah/satuan/lokasi dan pastikan hasil diperbarui.
- [ ] Pilih bahan yang saldonya cukup, tersisa sedikit, nol, negatif, dan belum memiliki mapping/catatan. Nol dan “Belum diketahui” tidak boleh sama.
- [ ] Buka daftar purchase (tab dokumen/rincian) dan detail. Cocokkan satuan, lokasi dan waktu; snapshot lama boleh berbeda dengan stok sekarang.
- [ ] Purchase verifikasi: tanpa konfirmasi/nama/alasan saat stok tersisa harus ditolak. Lengkapi alasan lalu verifikasi pada data uji yang disepakati.
- [ ] Cetak PDF; stok divisi/gudang, satuan, waktu baca dan catatan terlihat, termasuk dokumen dengan banyak baris.
- [ ] PO manual tujuan GUDANG dan tujuan divisi, lalu SR manual: perbarui stok, tekan simpan, batalkan dialog dan pastikan tidak tersimpan; konfirmasi untuk melanjutkan bila kebutuhan memang sah.
- [ ] Buka detail PO/SR yang sudah ada; ini hanya membaca stok sekarang dan tidak mengganti histori transaksi.
- [ ] Dua browser: purchase memverifikasi saat divisi masih mengedit. Simpan dari halaman lama harus ditolak dan diminta muat ulang.

## Catatan Control / packaging

- Baseline Git sebelum batch: `1119c9f61688810a230793af5eccf493698a67c1`. Perubahan batch ini belum di-commit/push. Control harus memakai cutoff baru yang mencakup file baru, **bukan** cutoff lama `92b5672b...`.
- Kandidat source tetap alpha.22 / CUSTOMER_CLEAN v10 yang sebelumnya berstatus NOT_PUBLISHED. Tidak membuat release/publish/deployment. Kontrak wire, entitlement, SQL dan profil seed tidak berubah; inventaris profil v10 direvisi sebelum persetujuan/build kandidat. Jika kandidat terdahulu sudah diterbitkan oleh pihak lain, Control wajib menyiapkan versi/profil baru, tidak mengganti artefak lama.
- Allowlist kini memasukkan `Procurement_stock_review.php`, panel/history review, partial stok terkini/toolbar, serta kedua JS. Empat dependensi review lama ternyata belum tercakup allowlist dan sekarang disertakan. Tidak membawa transaksi, customer seed, upload atau credential.
- Endpoint baru `purchase-orders/stock-preview` dan `procurement/store-request/stock-preview` dipetakan eksplisit ke fitur `PROCUREMENT`; tetap RBAC + POST + CSRF + no-store. Tidak membuka Inventory berbayar atau mengubah hak Starter.
- Tidak ada SQL baru; memakai `2026-09-16a_procurement_stock_review.sql` yang sudah masuk katalog v10. Jangan menjalankan ulang SQL aktif hanya untuk perubahan tampilan ini.
- Harness build disposable menyertakan file baru secara eksplisit, tanpa `git add`/commit ke repo kerja. Commit sintetis sandbox bukan cutoff publish.
- Dependensi tetap PHP aplikasi 8.1, adapter 8.4, MariaDB 10.11, Node 20 dan Chrome untuk tes tampilan/PDF. Tidak memasang paket atau layanan global.

Hash review source batch:

```text
5061fb623e036dd352c45ef3229a8a3003ef2c47974e7f5c476b95250e88a2bc  app-manifest.json
b65a4af24efd00059f07af4135642577a25c62944cfa0b710ae82772bab3fca4  tools/release/customer_clean_profile.json
37f549cb1d660aed644880823f666ff72a7312abad0a7cfc3a92431a59ce84b4  application/models/Procurement_model.php
6984799796e9f6dce6a7c8cf0f7afd582263ec510e279127fab7a5ef48bf3bcd  application/libraries/Procurement_stock_review.php
9fb13b89ecbd9e1b3acdc47a22eaae633b1f2729dc3e09cc5426932b2ab0b39e  assets/js/procurement-current-stock.js
```

## Hasil akhir pemeriksaan

- Lint: 25 PHP berubah/baru PASS; JS parse dan whitespace PASS.
- Global `finance_quality_gate.php parallel`: **133/133 PASS** (127 required, 4 development, 1 release-contract, 1 preflight). Profil parallel tidak menjalankan gate runtime/security/static/staging; bukan pengganti gate release.
- Build terisolasi `c3_control_build_runtime_smoke.php --isolated`: **8 gate PASS** dan validator independen PASS. 1.215 file, 316 tabel, 301 tabel nonreferensi kosong, 776 seed sistem, 26 migrasi; 0 customer-data/secret finding. Restore/checksum/health cocok. PHP aplikasi 8.1, adapter 8.4 dan MariaDB 10.11 disposable; tidak mengakses DB Finance/Control.
- Batas bukti build: snapshot diambil sebelum tambahan akhir peringatan PO mempertimbangkan stok gudang (`Procurement_model.php`) dan pemecahan preview manual >100 baris (`procurement-current-stock.js`). Kedua tambahan diuji lagi pada source melalui lint/regresi/browser; **build penuh cutoff final tetap harus diulang Control**. Tidak menyebut fixture antara ini sebagai release final.
- PR-03 probe lama sudah diperbarui untuk memeriksa timeout sesungguhnya; PASS retry-enabled dan verifikasi tetap tertutup. PR-01/02 probe PASS.
- UAT login/peran dan angka aktual pengguna belum dilakukan; angka fixture bukan pembuktian saldo produksi. File kerja tetap M/U, belum di-commit/push. Tidak publish, deploy, mengubah konfigurasi aktif atau data transaksi.
