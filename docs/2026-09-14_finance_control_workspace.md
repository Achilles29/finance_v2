# Kontrol Keuangan — Batch 248–249, 14 September 2026

Status: alur awal Batch 248 dan penyempurnaan Batch 249 tersedia di staging. Pegangan checklist tetap `_30` (aplikasi) dan `_28` (distribusi). Tidak mengubah APK, Control, atau transaksi lama secara otomatis. Urutan pengerjaan Batch 249: transfer → identitas biaya → pencarian riwayat → realisasi rencana → bukti/persetujuan opsional.

Addendum Batch 253–254: [alokasi, CSV bank, dan perbaikan rekonsiliasi](2026-09-14_finance_alokasi_bank_rekonsiliasi.md). Pengguna mengonfirmasi SQL `2026-09-14c` sudah dijalankan lewat terminal staging pada 2026-09-14 (**USER_REPORTED_APPLIED**, belum diverifikasi schema/ledger). Alokasi lintas rekap/realisasi parsial/CSV/transfer rekening harian menunggu pemeriksaan hasil aktivasi dan UAT; jangan menganggap batas satu transfer/satu rekap atau mutasi utuh/satu rencana Batch 249 sebagai desain terbaru. Perbaikan tampilan sisa selisih pendapatan dan penjagaan stale saldo kas tidak memerlukan SQL baru. Validasi MariaDB, browser dan registrasi paket masih terbuka, lihat checklist addendum.

## Mulai dari mana?

Menu **Keuangan → Kontrol Keuangan**, URL `/finance-reports/control`. Menu baru secara default diberikan kepada SUPERADMIN. Admin dapat memberi izin `finance.control.index` **view** untuk membaca, **edit** untuk menyimpan konfirmasi/rencana. Hak posting Mutasi/Rekonsiliasi tetap memakai izin masing-masing modul, bukan otomatis dibuka oleh izin halaman baru.

### 1. Settlement / pencairan dana

1. Pilih tanggal **pembayaran/refund POS** dan metode pembayaran. Rekening metode harus aktif dan IDR. Ini rekap harian, bukan kelompok berdasarkan tanggal order pertama dibuat.
2. Lihat daftar pembayaran sumber; tautan order membuka rincian POS. Promo/diskon yang telah masuk POS hanya informasi, tidak dipotong ulang sebagai biaya baru.
3. Beri nama rekap, misalnya `PLATFORM-20260914`, tanggal perkiraan pencairan dan catatan. Satu tanggal/metode mempunyai satu kontrol. Rekap baru dimulai dari nol; simpan dahulu.
4. Buka **Bukti privat** jika ingin melampirkan PDF/JPG/PNG. Unggah dahulu, lalu buka **Rincian transfer pencairan**: isi tanggal transfer, nomor referensi bank/platform, nominal **transfer ini**, bukti dan catatan. Simpan. Ulangi untuk transfer berikutnya; total dijumlahkan otomatis.
5. Pilih **Belum/sebagian cair** bila masih menunggu. Setelah seluruh batch diterima, pilih lengkap dan simpan konfirmasi. **Konfirmasi dan rincian transfer tidak menambah saldo bank**, karena POS sudah membukukan pembayaran. Total tidak bisa ditimpa manual setelah rincian digunakan. Nilai konfirmasi lama dipertahankan sebagai saldo awal terpisah, bukan dibuatkan riwayat transfer palsu; jangan memasukkannya lagi sebagai transfer baru.
6. Jika ada biaya tambahan yang belum dicatat POS, buat **Identitas biaya/penyesuaian**: nomor dokumen, baris biaya, tanggal posting, arah/kategori, nominal, bukti dan catatan. Simpan rincian belum memposting kas. Selanjutnya buka **Mutasi Rekening**, **Rekon Kas** atau **Rekon Pendapatan**, pilih settlement **dan rincian biaya yang sama**. Tanggal, rekening, kategori, arah dan nominal harus sama. Rekon Pendapatan juga harus memakai penerimaan kumulatif yang sama dengan kontrol; settlement sebagian tidak boleh dijadikan biaya rekonsiliasi. Jika persetujuan diwajibkan, ikuti bagian persetujuan di bawah sebelum posting.
7. Buka kembali settlement. Pembayaran dikurangi refund dan ditambah/dikurangi penyesuaian tertaut dibandingkan dengan penerimaan aktual. Sisa nol = sesuai; sumber berubah = periksa dan simpan ulang.

Contoh sintetis: pembayaran POS sudah bersih Rp100.000, baru cair Rp40.000 → Rp60.000 masih ditelusuri, **bukan otomatis biaya**. Setelah semua cair Rp85.000 dan bukti menunjukkan biaya platform Rp10.000 + promo tambahan Rp5.000, catat masing-masing satu kali dengan referensi yang sama. Jangan catat promo yang sudah dipotong pada POS.

### 2. Pencegahan ganda dan koreksi

- Promo/platform fee baru wajib referensi kontrol dan identitas biaya. Kategori sama diperbolehkan untuk biaya berbeda: contoh `INV-01 / MDR` dan `INV-01 / PROMO-1`. Nomor dokumen + baris biaya unik per rekening. Identitas yang sudah efektif diposting ditolak di semua tiga modul; tidak cukup mengganti nomor mutasi untuk mengulangnya.
- Rekon Pendapatan ikut mengurangi sisa selisih dengan penyesuaian tertaut dari Mutasi/Rekon Kas. Form yang sudah usang ditolak; muat ulang dahulu.
- Settlement sebagian tertaut diblokir dari posting biaya melalui Rekon Kas/Pendapatan. Biaya pasti yang berdiri sendiri boleh dicatat manual meskipun pencairan belum selesai, dengan bukti dan kategori yang benar.
- Posting tertaut yang salah: buka tab Settlement → rincian refund/penyesuaian → **Batalkan penyesuaian ini**. Isi alasan, konfirmasi dampak saldo, lalu VOID. Memerlukan edit Kontrol Keuangan **dan** edit modul asal, periode asli/hari ini terbuka, rekening aktif dan saldo mencukupi bila membatalkan pemasukan. Pembalikan hanya untuk penyesuaian manual tertaut, bukan pembayaran POS/transfer. Setelah VOID, buat koreksi baru dari modul asal (sesi baru untuk rekonsiliasi yang sudah POSTED). Riwayat tetap ada; retry tidak membalik dua kali.
- Biaya lama tertaut tanpa identitas: saat membuat rincian biaya, pilih **Mutasi lama yang sudah diposting**. Nominal/tanggal/rekening/kategori/arah harus persis sama. Ini menambah identitas audit, bukan memposting ulang. Memerlukan izin edit modul asal. Biaya baru satu kategori akan diblokir sampai biaya lama yang tertaut diidentifikasi.
- Salah rincian transfer: **Koreksi rincian ini → Batalkan rincian**, isi alasan. Total turun tanpa refund/mutasi bank. Tambahkan rincian pengganti; nomor referensi yang dibatalkan dapat dipakai kembali. Salah biaya yang belum diposting: **Ubah rincian**. Setelah posting, VOID mutasi dahulu; lalu ubah rincian dan posting ulang. Persetujuan lama tidak dapat dipakai lagi.
- Cari kasus lama lewat **Cari settlement lama — seluruh riwayat** atau pencarian di atas pilihan referensi pada modul posting. Nomor/teks, rentang tanggal, 25 per halaman; tidak dibatasi 200 terbaru. Pilihan lama tetap dipertahankan walau tidak berada pada halaman hasil pencarian.
- Sistem tidak menebak bahwa dokumen berbeda adalah kejadian ekonomi yang sama. Referensi benar tetap tanggung jawab operator. Satu transfer bank yang mencakup beberapa tanggal/metode belum mendukung alokasi lintas rekap; jangan menggandakan transfer di beberapa rekap. Konfirmasi awal historis hanya bisa dikoreksi sebelum rincian transfer pertama digunakan; sesudahnya perlu peninjauan terpisah, bukan menghapus bukti/history.

### 3. Kualitas laporan

Pilih bulan. Periksa cakupan rekap non-tunai, mutasi belum dikategorikan, pencairan belum lengkap, sumber berubah, selisih saldo vs seluruh ledger, dan masalah HPP. Buka sumber melalui tautan. Temuan tidak diperbaiki otomatis, dan satu transaksi dapat muncul pada beberapa pemeriksaan; angka temuan bukan nilai kerugian.

### 4. Proyeksi kas 7 / 30 hari

- Saldo buku sudah mengandung penerimaan POS. Saldo awal indikatif = saldo buku IDR aktif − pending settlement yang sudah terlacak. Proyeksi menambahkan pending sekali pada jadwalnya, bukan mencatat pemasukan baru.
- Hutang OPEN/PARTIAL memakai outstanding dan jatuh tempo (komitmen keluar); piutang belum pasti diterima (perkiraan masuk). Jadwal yang lewat dikelompokkan hari ini dan tetap ditandai.
- Payroll FINALIZED belum dibayar memakai net pay dan **tanggal proyeksi di tab Pengaturan**. Pilih tanggal 1–31; sistem memakai tanggal berikutnya setelah akhir periode, dibatasi hari terakhir bulan bila perlu. Contoh akhir periode 31 Januari, jadwal 31 → 28/29 Februari. Ini hanya jadwal proyeksi, bukan pembayaran atau perubahan gaji/uang makan. Payroll PAID tidak ditambahkan lagi.
- Rencana manual untuk hal yang **belum ada** di sumber otomatis. Pilih komitmen/perkiraan. Setelah mutasi manual benar-benar diposting, buka **Rencana → realisasi → sisa → Tautkan realisasi**: cari nomor/tanggal mutasi, pilih rencana dan mutasi, isi catatan, tautkan. Proyeksi hanya menghitung sisa, tanpa membayar dua kali. Realisasi berlebih juga ditampilkan.
- Satu mutasi utuh hanya bisa ditautkan satu rencana, arah IN/OUT harus sama. POS, tagihan dan payroll otomatis tidak boleh dialokasikan lagi sebagai realisasi manual. Tidak ada pemecahan sebagian satu mutasi ke beberapa rencana pada batch ini.
- Jika salah tautan, **Lepas tautan** dengan alasan; mutasi asli tetap ada. Jika mutasi di-VOID, realisasinya otomatis tidak dihitung dan sisa proyeksi kembali. Rencana tertaut selesai berdasarkan nominal realisasi, bukan tombol Selesai manual. Batalkan rencana hanya jika memang tidak dilanjutkan; riwayat realisasi tetap ditampilkan.
- Rekening non-IDR dikecualikan, tidak ada konversi kurs. Pembayaran yang belum dibuatkan kontrol belum bisa dipisahkan dari saldo buku: proyeksi **bukan saldo bank yang sudah terverifikasi**.

### 5. Laba-rugi manajemen HPP

Penjualan lunas tanpa pajak penjualan − refund pada tanggal refund − HPP snapshot/extra yang diakui + pembalikan HPP refund − koreksi HPP bersih pada tanggal pengakuan + pendapatan lain − biaya lain berkategori − beban payroll final.

Service/pembulatan mengikuti tagihan POS. DP/PAID_PARTIAL tanpa transaksi final belum menjadi penjualan laporan ini. Pembelian stok, modal/prive, transfer, pelunasan hutang/piutang dan pembayaran gaji tidak dijadikan biaya kedua. Potongan kasbon tidak mengurangi beban gaji, meskipun mengurangi kas yang dibayarkan.

Ini **laporan manajemen, bukan akrual akuntansi lengkap**: biaya belum dicatat, depresiasi, pajak penghasilan dan akrual di luar sumber tersebut belum masuk. Payroll hanya periode FINALIZED/PAID yang tercakup penuh, tanpa prorata otomatis. Mutasi belum berkategori belum termasuk laporan ini; estimasi kas lama tetap memiliki aturan sementara OUT historis yang dijelaskan di halamannya.

Referensi konsep: [IAS 2 — biaya persediaan saat pendapatan terkait diakui](https://www.ifrs.org/issued-standards/list-of-standards/ias-2-inventories/) dan [IAS 7 — arus kas terpisah dari hasil usaha](https://www.ifrs.org/issued-standards/list-of-standards/ias-7-statement-of-cash-flows/). Rujukan ini bukan klaim aplikasi sudah memenuhi seluruh IFRS.

## Validasi dan operasi

- Batch 249: `php tools/tests/finance_control_operations_contract_smoke.php` (required gate), `php tools/tests/finance_control_operations_smoke.php --export-ui` (MariaDB disposable + HTTP multipart/izin/CSRF/download privat), dan `node tools/tests/finance_control_operations_browser.cjs /tmp/finance-control-ops-ui-<id>` (offline browser 360/390/768/1280px). Angka hasil akhir ada di execution log, bukan jaminan bebas bug universal.
- `php tools/tests/finance_control_workspace_contract_smoke.php`: kontrak tanpa DB, terdaftar required gate.
- `php tools/tests/finance_control_workspace_smoke.php --export-ui`: MariaDB disposable socket-only, data sintetis; tidak membaca config/kredensial DB aplikasi. Menjalankan migrasi dua kali, writer nyata, rollback audit, replay/VOID/stale, refund lintas bulan, koreksi HPP, proyeksi dan payroll. Mencetak direktori `UI_FIXTURE` untuk uji browser.
- `node tools/tests/finance_control_workspace_browser.cjs /tmp/finance-control-ui-<id>`: butuh Puppeteer/Chromium; semua network diblokir, delapan view edit/read-only pada 360/390/768/1280px. Variabel opsional `FINANCE_BROWSER_TEST_PUPPETEER` dan `FINANCE_BROWSER_TEST_CHROME` menunjuk instalasi lokal pengujian.
- Regresi Batch 247 tetap diuji; assertion biaya platform sekarang mewajibkan referensi terstruktur. VOID khusus penyesuaian tertaut diuji lewat writer asli, bukan mengasumsikan ada tombol VOID umum di Mutasi Rekening. Angka tes/gate final ada di execution log Batch 248, bukan jaminan tidak ada bug universal.
- SQL `2026-09-14a_finance_control_workspace.sql` SHA `5ba57770cdd2b4b12d67cb06acb340423ea454e60bb19234f60546adaad4567e`, tergantung `2026-09-13a`. Staging applied/replay, dua tabel baru kosong tanpa backfill dan tiga kolom nullable. DDL tidak transaksional penuh: bila deployment terputus, ulang melalui runner yang memeriksa checksum; SQL repeat-safe. Rollback kode mempertahankan metadata/audit, jangan drop tabel atau membersihkan transaksi.
- SQL `2026-09-14b_finance_control_operations.sql` SHA `9034c6db9dbea17054ff78ade67ee281d5a07f885c56a77f21a152d2e7b0f2af`, setelah `2026-09-14a`, sudah applied + ledger replay staging. Enam tabel metadata baru dan kolom nullable/konfirmasi; **tidak backfill saldo/history**. Dua permission page default SUPERADMIN, kebijakan awal persetujuan/bukti wajib OFF. SQL repeat-safe tidak mengubah kebijakan yang sudah dipilih pengguna. Bila deploy terputus, ulang runner dengan checksum yang sama; rollback tetap menyimpan audit/bukti.
- Paket customer memakai profil v3; data kontrol/rencana/bukti/pengajuan staging bukan seed. Control dan blocker paket Roast Connect hanya di `_28`; belum ada build/publish/cutoff baru dalam batch ini.

## Persetujuan dan bukti — pengaturan lewat UI

1. Admin membuka **Pengaturan**. Pilih apakah persetujuan wajib dan nominal minimalnya, serta apakah semua biaya tertaut perlu bukti. Default tidak diwajibkan. Simpan alasan perubahan; perubahan kebijakan diaudit dan membuat persetujuan lama tidak berlaku.
2. Izin tambahan: `finance.control.settings` edit untuk kebijakan, `finance.control.approve` edit untuk pemeriksa. Keduanya juga perlu akses Kontrol Keuangan (view/edit); izin posting modul asal tetap terpisah. Jika izin baru belum terlihat, muat ulang sesi/login setelah admin mengatur role.
3. Operator mengunggah bukti, membuat rincian biaya lengkap, lalu **Ajukan persetujuan** dari baris biaya. Pemeriksa **pengguna lain**, bukan pembuat/pengaju, membuka tab **Persetujuan**, membaca nominal/rekening/tanggal/kategori/dokumen/bukti, mengisi alasan dan menyetujui/menolak.
4. Persetujuan **tidak memposting kas**. Operator kembali ke modul asal dan posting. Persetujuan hanya dikonsumsi oleh posting berhasil; kegagalan dalam transaksi membatalkan perubahan. Perubahan rincian, bukti, konfirmasi settlement atau kebijakan memerlukan pengajuan ulang; UI menandai persetujuan usang.
5. Jika membatalkan penyesuaian nominal besar, ajukan **persetujuan VOID** dari rincian refund/penyesuaian, minta pemeriksa menyetujui, baru tekan VOID dengan alasan. Ini berbeda dari membatalkan rincian transfer (metadata saja).

Lingkup persetujuan **hanya posting/VOID penyesuaian manual tertaut settlement**, bukan seluruh sales, purchase, payroll atau semua mutasi aplikasi. Unggahan adalah bukti yang diunduh, bukan HTML/SVG yang dijalankan atau preview PDF inline. Validasi jenis/hash bukan antivirus; hanya unduh bukti dari operator tepercaya.

## Admin server — siapkan penyimpanan bukti privat

Staging ini sudah disiapkan: `/var/lib/finance-control/f65b6a92a4ff9ea0/evidence`, pemilik `www:www`, direktori 0750, file bukti 0600. `.user.ini` staging mengizinkan **hanya direktori tersebut** melalui `open_basedir`; diuji sebagai pengguna `www` dengan konfigurasi PHP server. Tidak memakai chmod 777. Perubahan `.user.ini` mengikuti TTL cache PHP-FPM (biasanya beberapa menit).

Untuk instance customer, jangan menyalin hash/path staging. Default direktori memakai 16 karakter awal SHA-256 dari **realpath root aplikasi customer**. Contoh admin melalui terminal, ganti root sesuai instance:

```bash
php -r 'echo "/var/lib/finance-control/".substr(hash("sha256",realpath("/path/customer-finance")),0,16)."/evidence\n";'
```

Salin hasilnya sebagai path literal, lalu buat folder dengan pengguna PHP-FPM customer (contoh `www`):

```bash
install -d -m 0750 -o www -g www /var/lib/finance-control/HASH_HASIL_PERINTAH/evidence
```

Tambahkan **path hasil yang sama** pada `open_basedir` instance jika pembatasan itu aktif, dengan mempertahankan path lain yang sudah ada. Jika memilih direktori lain, set `FINANCE_CONTROL_EVIDENCE_DIR` ke path absolut privat di environment PHP-FPM melalui mekanisme deployment yang ada, siapkan owner/izin dan open_basedir path itu. Tidak perlu token/API eksternal untuk bukti ini. Direktori tidak boleh berada di webroot atau berupa symlink. Bukti harus ikut backup **data privat customer**, bukan artifact rilis bersih. Jika simpan DB gagal setelah file diterima, file privat dipertahankan untuk peninjauan admin; tidak dibersihkan otomatis.

UAT berikutnya: owner membaca satu settlement nyata, mengonfirmasi pencairan sesuai bukti, memberi kategori bila memang ada biaya tambahan, lalu memeriksa saldo/proyeksi/laba-rugi. Pengujian tidak membuat transaksi bisnis nyata untuk menggantikan UAT.
