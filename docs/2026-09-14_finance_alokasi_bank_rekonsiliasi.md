# Kontrol Keuangan — alokasi, rekening koran, dan rekonsiliasi

Batch 253–257, 2026-09-14. **Struktur SQL sudah diverifikasi langsung di staging; pencatatan migrasi manual dan UAT masih terbuka.** Batch 254 meluruskan konsep Rekonsiliasi Pendapatan sesuai arahan pemilik: cek kas lingkup pendapatan harian, bukan khusus settlement platform atau transfer pasangan metode. Batch 255 menambahkan pengujian lintas modul dan memperbaiki transfer Rekonsiliasi Kas ke rekening bersaldo negatif. Batch 256 menyediakan alat pemeriksaan read-only; Batch 257 menjalankan pemeriksaan tersebut dengan izin eskalasi, tanpa mengulang migrasi.
Status terkini `2026-09-14c`: **SCHEMA_VERIFIED_LEDGER_PENDING**. Pengguna sebelumnya mengonfirmasi apply manual, lalu struktur diverifikasi pada `db_finance` MariaDB 10.11.10 tanggal 2026-09-14 pukul 19:46:50 WIB. Catatan migrasi 14c belum ada; SQL belum masuk katalog migrasi otomatis/allowlist SQL customer. Penolakan socket pada percobaan sebelumnya adalah riwayat. Credential/konfigurasi tidak diubah; tidak ada deploy, push atau perubahan bridge.

## Verifikasi staging — Batch 257

- 95 kondisi postcheck diterima lengkap: 90 kondisi target/struktur sesuai, tabel registry dan tiga catatan prasyarat sesuai, satu peringatan catatan 14c belum ada. Hasil **SKEMA_LULUS_LEDGER_PENDING** (exit 2), bukan kegagalan apply/schema.
- Fingerprint sumber: `a692c0c274c524e72c32ecc53f358bcd97f86e0010070de686496372dcad50e1`; waktu query 2026-09-14 12:46:50 UTC / 19:46:50 WIB. Empat tabel 14c, semua kolom barunya, enum TRANSFER, index serta metadata prasyarat diperiksa menggunakan SELECT nyata MariaDB.
- Inventaris: 28 file SQL top-level = 20 managed + 7 legacy + 14c belum managed. Hash file sumber cocok untuk seluruh 20 entri katalog; 19 entri ber-policy upgrade mempunyai ID/path/checksum ledger yang cocok, termasuk Roast Connect. Tidak mengaudit ulang seluruh struktur tujuh SQL legacy atau schema Roast Connect.
- Satu entri katalog tanpa ledger adalah `2026-09-05d_a5_clean_install_reference_seed.sql`, **khusus clean_install**. Bukan SQL tertunda untuk staging berisi data; jangan dijalankan di sini.
- Keputusan: **tidak ada DDL/DML yang dijalankan**. SQL 14c telah terpasang; tidak menulis catatan migrasi otomatis atau mengulang apply untuk menghilangkan peringatan. Hanya pembacaan metadata/registry dalam transaksi read-only; tidak membaca baris transaksi, mengubah server, saldo atau credential.
- Berikutnya: review pencatatan apply manual dan registrasi customer, uji migrasi/replay/konkurensi serta UAT. Verifikasi ini tidak membuktikan alur bisnis/rilis customer bebas masalah.

## Yang diperbaiki tanpa SQL baru

- Rekonsiliasi Pendapatan sebelumnya menampilkan `difference_amount` saat posting sebagai **sisa selisih**. Kini sisa dihitung dari penerimaan riil dikurangi pendapatan dan penyesuaian efektif. Nilai saat posting ditampilkan terpisah sebagai riwayat; data historis tidak ditulis ulang. Selisih baru setelah posting ditelusuri melalui sesi baru.
- Rekonsiliasi Kas sebelumnya menghitung ulang nominal saat posting meskipun saldo sudah berubah setelah penyimpanan/konfirmasi. Kini snapshot saldo/selisih dibandingkan dengan saldo terkunci: perubahan meminta pemeriksaan ulang, bukan memposting nominal yang belum disetujui.
- Kategori IN/OUT di kedua formulir mengikuti arah. `BALANCE_CORRECTION` tetap tersedia untuk kedua arah. Transfer tidak membutuhkan kategori pendapatan/biaya. Transfer kas lintas mata uang ditolak, bukan menggunakan nominal 1:1 tanpa kurs.

## Alur 1 — satu transfer, beberapa rekap

Menu **Keuangan → Kontrol Keuangan → Settlement** setelah SQL aktif:

1. Buat/periksa rekap terkait. Catat satu transfer pada rekap asal, jangan duplikasi referensi di rekap lain.
2. Buka **Bagi satu transfer ke beberapa rekap**. Cari rekap, pilih dan tambahkan nominal untuk masing-masing.
3. Tujuan harus pada rekening IDR yang sama, dengan tanggal pendapatan tidak melewati tanggal transfer. Maksimal 25 tujuan; total tidak boleh melebihi transfer. Selisih yang belum dibagi tetap terlihat sebagai belum dialokasikan, bukan biaya baru.
4. Simpan dengan alasan. Total rekap sumber/tujuan dihitung ulang; saldo konfirmasi awal lama tetap dipertahankan. Status lengkap rekap terkait dibuka kembali untuk diperiksa/ditetapkan ulang.
5. Salah pembagian: ubah form yang sama; riwayat revisi dan alokasi lama tetap disimpan. Pembagian kosong melepas seluruh alokasi, bukan membatalkan uang masuk.
6. Salah transfer: batalkan **seluruh transfer dari rekap asal**. Semua rekap terkait dihitung ulang; saldo bank tetap. Rekap tujuan menautkan ke rekap asal untuk koreksi.

Pembagian tidak membuat mutasi bank, tidak mengalokasikan saldo konfirmasi historis yang tidak memiliki rincian, dan tidak memposting biaya. Revisi/request key/audit serta transaksi mencegah replay/perubahan sebagian.

## Alur 2 — realisasi rencana secara parsial

Menu **Kontrol Keuangan → Proyeksi kas → Rencana → realisasi → sisa**:

- Cari mutasi manual yang sudah efektif. Pilih rencana dan isi **nominal untuk rencana ini**. Pilihan mutasi menunjukkan nominal asli serta sisa yang dapat digunakan.
- Contoh mutasi Rp100.000: Rp60.000 untuk rencana A, Rp40.000 untuk B. Pembagian total maksimal Rp100.000; tidak menambah/mengurangi saldo lagi.
- Tautan lama utuh tidak dikonversi diam-diam. Lepaskan melalui tombol yang ada, kemudian buat pembagian baru. Maksimal satu tautan aktif per pasangan mutasi/rencana; untuk koreksi nominal, lepas tautan lalu buat ulang dengan alasan.
- Pembatalan mutasi membuat realisasi tidak dihitung dan sisa proyeksi kembali. Lepas alokasi hanya mengembalikan porsi tersebut. Arah rencana tidak boleh berubah selama masih tertaut. POS/purchase/payroll otomatis bukan realisasi manual kedua kali.

## Alur 3 — CSV rekening koran

Menu **Kontrol Keuangan → Cocokkan bank**:

1. Pilih rekening IDR aktif. Pilih CSV UTF-8 maksimal 1 MB/500 transaksi, dengan header dan kolom **tanggal, referensi, masuk, keluar**. Nomor kolom di UI mulai 1.
2. Pilih pemisah koma/titik koma, tanggal `dd/mm/yyyy` atau `yyyy-mm-dd`, serta format nominal Indonesia/Inggris secara eksplisit. Tidak menebak format angka. Referensi wajib terisi; satu baris harus IN atau OUT, bukan keduanya.
3. Pratinjau dan periksa hasil. Konfirmasi terikat pada file, rekening, dan hasil pemetaan; mengganti isian membutuhkan pratinjau baru.
4. Konfirmasi impor menyimpan baris pembanding saja. Baris identik tanggal/referensi/arah/nominal pada rekening sama dilewati dan jumlahnya dilaporkan, termasuk ketika file periode saling tumpang tindih. File asli tidak disimpan sebagai upload publik.
5. Periksa saran mutasi. Pilih pasangan dan beri alasan. Rekening, tanggal, arah dan nominal harus sama; satu mutasi hanya boleh dicocokkan sekali. Lepaskan kecocokan dengan alasan jika salah. VOID/perubahan fakta mutasi membuat status kecocokan perlu diperiksa ulang.

**Batas versi awal:** tidak membaca PDF, tidak ada login/integrasi bank, tidak memposting selisih otomatis; satu baris bank ↔ satu mutasi dengan tanggal sama. Maksimal enam kandidat per baris; pencocokan agregat/net settlement, beda tanggal, CSV dengan satu kolom nominal bertanda serta pencarian kandidat lanjutan belum termasuk. Referensi yang berbeda tidak dianggap transaksi identik secara semantik. Rujuk Mutasi Rekening bila tidak ada kandidat; jangan membuat transaksi ulang hanya untuk mencocokkan.

## Tambahan — Rekonsiliasi Pendapatan vs Rekonsiliasi Kas

Keduanya mempunyai tindakan yang sama. Bedanya, **Rekonsiliasi Pendapatan** membandingkan penerimaan riil untuk tanggal/metode tertentu dengan pembayaran POS final + deposit − refund dan penyesuaian sebelumnya. **Rekonsiliasi Kas** membandingkan seluruh saldo rekening saat diperiksa. Jangan memasukkan saldo awal atau seluruh saldo rekening ke isian penerimaan harian.

| Keadaan | Tindak lanjut |
| --- | --- |
| Belum pasti penyebabnya / belum ingin disesuaikan | Pilih **Biarkan terbuka**, lalu **Simpan**. Tidak membuat mutasi, tidak wajib rekening lawan/kategori/settlement. |
| Penerimaan riil kurang atau lebih | Pilih **Mutasi keluar** atau **Mutasi masuk**, rekening yang disesuaikan, kategori dan catatan. Simpan dahulu; Posting baru mengubah saldo. |
| Selisih diselesaikan dengan perpindahan rekening | Pilih **Transfer antar rekening** dan rekening lawan di Rekonsiliasi Pendapatan maupun Rekonsiliasi Kas. |
| Dana platform belum cair | Boleh tetap terbuka. Tidak otomatis OUT/biaya; periksa pencairan terlebih dahulu. |

### Contoh tindakan dan pilihan kategori

- **IN:** pendapatan lain-lain, selisih kas lebih terverifikasi, setoran modal pemilik, atau koreksi saldo saja.
- **OUT:** biaya operasional lain, promo ditanggung usaha, komisi/biaya platform, selisih kas kurang terverifikasi, prive/penarikan pemilik, atau koreksi saldo saja.
- Pilihan berasal dari `Finance_mutation_policy::categories()` yang juga dipakai Mutasi Kas dan Rekonsiliasi Kas. Setoran modal/prive/koreksi saldo tidak dianggap pendapatan atau biaya usaha. Kategori yang tidak sesuai arah tidak boleh diposting.
- Selisih biasa **tidak wajib** referensi Kontrol Settlement, walaupun ada rekap settlement belum lengkap pada tanggal/metode tersebut. Bila operator sengaja mengaitkannya, validasi rekening/tanggal/metode/dokumen tetap berlaku. Khusus promo/biaya platform, referensi dan rincian biaya tetap wajib seperti pada Mutasi Kas, dengan pemeriksaan belum dicatat dua kali serta persetujuan/bukti sesuai pengaturan.

Contoh penerimaan POS Rp100.000, riil Rp90.000: biarkan selisih Rp10.000 terbuka, atau OUT sesuai penyebabnya, atau transfer Rp10.000 **keluar dari rekening yang disesuaikan menuju rekening lawan**. Jika riil Rp110.000, transfer Rp10.000 **dari rekening lawan masuk ke rekening yang disesuaikan**. Jangan memilih biaya bila uang hanya berpindah rekening.

### Transfer harian (menggantikan batas pasangan metode Batch 253)

Transfer pendapatan memerlukan SQL `2026-09-14c` yang sudah disiapkan; tidak ada SQL tambahan Batch 254. Wajib dua rekening aktif berbeda dengan mata uang sama, sumber cukup, alasan terisi, dan selisih/sumber POS belum berubah setelah disimpan. Rekening lawan boleh tidak mempunyai metode POS. **Tidak perlu mengisi baris metode kedua atau mencari selisih sama besar/berlawanan.**

Posting membuat dua mutasi `FINANCE_TRANSFER` pada satu transaksi, menyimpan ID kedua mutasi pada baris utama, dan mencatat audit. Hanya baris utama diposting; baris lain tidak otomatis ditutup. Transfer tidak memakai kategori biaya/pendapatan atau rincian settlement; total dana tidak bertambah. Saldo tujuan negatif tidak diubah diam-diam menjadi nol.

Jika memang koreksi pembagian antar metode pada hari yang sama, buka pilihan **Kaitkan sisi lawan (opsional)**. Pilih metode yang terkait rekening lawan. Penyesuaian sisi lawan ikut diperhitungkan sehingga selisih metode itu tidak diposting ulang; operator tetap memeriksa baris tersebut. Kosongkan untuk transfer biasa. Data transfer dua-baris lama tetap dibaca sekali per metode, tanpa backfill atau menutup baris tambahan.

Tidak menambahkan jalur VOID satu sisi; koreksi transfer yang sudah diposting perlu peninjauan kedua sisi, bukan pembatalan satu mutasi melalui VOID biaya. Koreksi data historis tidak dijalankan otomatis.

## SQL dan handoff IDE

- File: `sql/2026-09-14c_finance_allocation_bank_review.sql` (**SCHEMA_VERIFIED_LEDGER_PENDING**, staging `db_finance`; struktur diverifikasi Batch 257, catatan migrasi manual belum ada). Status prepared/USER_REPORTED_APPLIED pada batch sebelumnya adalah riwayat, bukan status terkini.
- Prasyarat: `2026-09-13a`, `2026-09-14a`, `2026-09-14b`. Empat tabel metadata baru; tiga kolom lawan pada baris rekon pendapatan; perluasan enum tindak lanjut menjadi `NONE/IN/OUT/TRANSFER`.
- Tidak berisi pengosongan/backfill transaksi/saldo/credential. ALTER enum tetap DDL dan mungkin mengunci tabel; verifikasi versi DB dan jendela pelaksanaan melalui IDE. Jangan menjalankannya langsung pada sumber produksi.
- Kode baru memeriksa kesiapan schema dan mempertahankan form lama bila belum siap. File runtime yang di-require masuk allowlist kode customer supaya paket tidak kehilangan dependency. SQL belum didaftarkan untuk auto-apply; clean install baru belum dinyatakan siap untuk fitur ini.
- Profil kode v3 hash baru `02940e1fa103fc99211e531d02f954ab131e5c35282918b5c276cc8627750466`, manifest diselaraskan. Tidak membuat artifact/cutoff baru atau mengubah Control.

Perintah yang diberikan kepada pengguna (arsip, **bukan instruksi untuk mengulang**):

```sh
mysql -u root -p db_finance < /www/wwwroot/finance/sql/2026-09-14c_finance_allocation_bank_review.sql
```

SQL berikutnya yang belum dieksekusi harus dilaporkan beserta status, database tujuan, prasyarat/dampak, dan perintah siap salin tanpa password. Konfirmasi pengguna dicatat terpisah dari verifikasi schema dan registrasi ledger; jangan membuat entri ledger sukses tanpa pemeriksaan.

## Batch 255 — integrasi rekonsiliasi → mutasi → laporan

- [x] **217 pemeriksaan tambahan**, total suite `finance-allocation-bank` menjadi **392 PASS**. Memakai writer/model/query builder aplikasi asli dengan SQLite `:memory:`, bukan DB Finance atau sekadar pemeriksaan string kode. Gate required yang sudah ada otomatis menjalankan kasus baru `tools/tests/finance_reconciliation_reporting_cases.php`.
- [x] Simpan NONE maupun draft IN/OUT tidak memposting saldo/laporan. Sembilan kategori diuji sampai daftar Mutasi Rekening, estimasi harian/bulanan dan laba-rugi manajemen; koreksi saldo diuji dua arah. IN/OUT mengikuti kategori, modal/prive/koreksi saldo tidak menjadi laba/biaya, penjualan tidak digandakan. Basis POS/HPP tetap saat mutasi manual berubah; tes ini bukan pengujian ulang seluruh akuntansi POS/HPP.
- [x] Perubahan kategori pada Mutasi Rekening memindahkan kelompok laporan tanpa mengubah saldo atau membuka selisih rekon yang sudah selesai. Revisi kategori stale ditolak.
- [x] Biaya yang telah diposting dari Rekon Pendapatan ditolak jika dicoba lagi melalui Rekon Kas maupun Mutasi Kas dengan identitas biaya yang sama. Tes memeriksa alasan penolakan spesifik, bukan sekadar respons gagal.
- [x] VOID penyesuaian biaya tertaut: saldo kembali, mutasi asal/pembalikan dikecualikan dari biaya efektif, sisa selisih terlihat kembali. Retry VOID tidak mengubah saldo; koreksi melalui sesi rekon baru dihitung tepat sekali. Riwayat pembalikan tetap tampil di Mutasi Rekening. Ini bukan penambahan fitur VOID transfer biasa.
- [x] Transfer kedua arah: dua rekening mempunyai mutasi berlawanan dengan nominal sama, saldo gabungan tetap, estimasi/laba-rugi tidak berubah, sisa selisih nol. Posting ulang, menjadikan transfer sebagai pendapatan lewat klasifikasi, dan VOID satu sisi lewat jalur biaya ditolak.
- [x] Regresi ditemukan dan direproduksi sebelum patch: helper posting Rekon Kas mengubah saldo akhir negatif menjadi nol. Contoh saldo tujuan -50 ditambah IN 10 mestinya -40, sebelumnya menjadi 0, sehingga tercipta saldo tanpa mutasi. Patch menghapus pemaksaan nol, mempertahankan batas kecukupan saldo OUT. Tes gagal sebelum patch dan lulus sesudahnya; rantai saldo sebelum + mutasi = saldo sesudah diperiksa sampai saldo rekening terbaru.
- [x] Lint tiga file PHP dan regresi mutation reporting 72, workspace 27, operations 35, kontrak finance A4 21, quality-gate 28 PASS. Tidak ada perubahan JS/dependency Composer/schema, perbaikan data, push/deploy, atau perubahan credential/bridge.
- [ ] Verifikasi schema/ledger MariaDB setelah apply pengguna, perilaku transaksi serentak, browser dan UAT operator tetap belum dibuktikan. Jangan mengubah status SQL menjadi STAGING_PASS berdasarkan tes SQLite atau menganggap paket customer selesai.

Untuk UAT, gunakan sesi/transaksi uji yang disiapkan pemilik: simpan selisih tanpa posting → IN/OUT sesuai kategori → cek Mutasi Rekening/laporan → transfer dengan saldo sumber cukup → coba ulang → VOID biaya tertaut dan koreksi lewat sesi baru. Jangan memakai transaksi riil hanya untuk pengujian otomatis. Tidak perlu membuat saldo negatif pada data staging untuk mereproduksi regresi; kasus negatif sudah dicakup fixture terisolasi.

## Checklist validasi

### Pemeriksaan setelah SQL dijalankan — Batch 256

Status alat: **SUDAH DIJALANKAN PADA DATABASE STAGING — Batch 257**. Hasil **SKEMA_LULUS_LEDGER_PENDING**: struktur sesuai, catatan manual 14c belum ada. Perintah berikut tetap tersedia untuk pemeriksaan ulang read-only, bukan perintah apply migrasi.

Di terminal **server staging**, salin satu baris ini:

```bash
bash -o pipefail -c 'php /www/wwwroot/finance/tools/db/finance_allocation_postcheck.php --sql | mysql -u root -p --batch --raw --skip-column-names --skip-reconnect db_finance | php /www/wwwroot/finance/tools/db/finance_allocation_postcheck.php --report'
```

Masukkan password MySQL saat client meminta; karakter tidak terlihat. Jangan menuliskan password di perintah, argumen atau balasan chat. PHP hanya menghasilkan SELECT dan membaca output, tidak memuat konfigurasi aplikasi/credential atau membuka koneksi. Client MySQL dijalankan oleh pengguna lewat terminal; perintah ini tidak dijalankan agent untuk melewati sandbox.

Hasil akhir:

- `SKEMA_LEDGER_LULUS` (exit 0): 95 pemeriksaan struktur/index/prasyarat dan catatan migrasi cocok dengan checksum file sumber yang diperiksa.
- `SKEMA_LULUS_LEDGER_PENDING` (exit 2): struktur cocok, tetapi satu/lebih migrasi belum tercatat di `sys_schema_migration`. Ini dapat terjadi setelah menjalankan SQL manual. **Bukan perintah untuk mengulang migrasi.** Registrasi harus ditinjau tersendiri, bukan ditulis sebagai sukses tanpa bukti.
- `PERLU_PERBAIKAN` (exit 1): hasil lengkap tetapi ada struktur/index/target/checksum yang tidak sesuai. Baris GAGAL menyebut pemeriksaannya; tidak memperbaiki secara otomatis.
- `Pemeriksaan belum berhasil` (exit 1): keluaran kosong/terputus/tidak cocok, query/client gagal, atau sumber berubah. Contohnya tabel catatan migrasi tidak tersedia sehingga query terakhir gagal. Tidak menganggap hasil parsial sebagai kelulusan. `pipefail` mempertahankan kegagalan salah satu proses pipeline.

Kirim keluaran dari judul pemeriksaan sampai akhir, termasuk fingerprint sumber dan pesan error jika ada, **tanpa password**. Alat hanya membaca `information_schema` dan catatan migrasi untuk empat file SQL terkait; tidak membaca saldo, rekening koran, transaksi, identitas pengguna, credential atau pengaturan. Struktur setiap kolom baru, nullable/default/auto-increment, engine/charset, enum TRANSFER, urutan/unique/index tanpa prefix, dan metadata prasyarat diperiksa. Ini bukan audit lengkap seluruh schema legacy, bukti locking/konkurensi, tes nominal transaksi atau UAT browser.

- [x] Tool `tools/db/finance_allocation_postcheck.php`; tes `tools/tests/finance_allocation_postcheck_smoke.php`; gate required `finance-allocation-postcheck` ditambahkan.
- [x] 241 pemeriksaan alat PASS, termasuk evaluasi SELECT terhadap metadata SQLite sintetis, tipe/nullable/default/index salah, ledger manual/konflik checksum, target salah, output terputus/duplikat/berubah, dan protokol CLI. Adaptasi SQLite menghapus cast BINARY; **bukan bukti sintaks/izin/hasil MariaDB live**.
- [x] Regresi integrasi 392, workspace contract 27, quality-gate contract 28 serta lint empat file PHP PASS. Tidak ada perubahan runtime aplikasi, SQL migrasi, registry, allowlist paket atau Control.
- [x] Batch 257: agent menjalankan SELECT alat dengan izin eskalasi dan konfigurasi staging privat tanpa menampilkan credential. Struktur sesuai, catatan prasyarat cocok, ledger 14c belum ada. Hasil tidak disamakan dengan UAT atau kesiapan rilis customer.

### Riwayat dan validasi yang masih terbuka

- [x] Batch 254: uji writer nyata, query builder CI dan render PHP: **175 pemeriksaan PASS** dengan SQLite in-memory. SQL MySQL diadaptasi ke SQLite untuk pengujian fungsi; ini **bukan validasi DDL/locking MariaDB**. Hasil 108 Batch 253 merupakan cutoff historis sebelum pelurusan konsep.
- [x] Cakupan tambahan: NONE tanpa perubahan saldo/rekening wajib; transfer tanpa metode/baris lawan; dua arah/saldo total tetap; rekening sama/nonaktif/beda mata uang/mapping opsional salah; saldo tujuan negatif; replay/snapshot stale/periode tutup/audit rollback; kompatibilitas pasangan lama tanpa hitung ganda; semua sembilan kategori (koreksi saldo diuji dua arah); selisih biasa tidak diblokir rekap sebagian; promo/platform tetap membutuhkan rincian dan settlement lengkap.
- [x] Regresi alokasi/revisi/retry/kelebihan/beda rekening/VOID/rollback audit; proyeksi parsial dan VOID; CSV preview/hash/mapping/duplikasi/nominal/UTF-8; bank-match stale; IN/OUT/transfer kas dan stale balance; form edit/read-only serta fallback schema lama tetap lulus.
- [x] Regresi sebelumnya: mutation reporting 72, workspace contract 27, operations contract 35, quality-gate contract 28 PASS. Test baru terdaftar required `finance-allocation-bank`.
- [x] Roadmap consistency 26 PASS; register mencakup tepat 28 top-level SQL. Batch 257 memperbarui status `2026-09-14c` menjadi SCHEMA_VERIFIED_LEDGER_PENDING berdasarkan SELECT MariaDB nyata, bukan tes SQLite. Tidak menyatakan deployable.
- [x] Batch 254: PHP lint empat file yang diubah, sintaks runner JS dan 59 script inline hasil render PASS. Batch 253 sebelumnya lint 20 file/37 script. Tidak ada perubahan dependency Composer.
- [ ] Browser interaktif: runner offline `tools/tests/finance_allocation_browser.cjs` disiapkan, tetapi Chromium tidak bisa mulai dalam sandbox (`setsockopt: Operation not permitted`). Belum ada hasil PASS browser/responsif; jangan menyamakan render PHP dengan UAT browser.
- [x] Pengguna mengonfirmasi menjalankan SQL di staging melalui terminal; hasil struktur diverifikasi agent pada Batch 257. Tidak ada apply ulang.
- [ ] Pencatatan migrasi manual 14c masih terbuka setelah verifikasi struktur. Uji migrasi MariaDB disposable dua kali, invariant data lama dan concurrency/replay/rollback; kemudian masukkan hash/dependency SQL ke katalog dan allowlist distribusi. Rekonsiliasikan eksekusi manual dengan runner berdasarkan bukti, bukan menandai ledger sukses atau mengulang DDL tanpa pemeriksaan.
- [ ] UAT operator: transfer lintas rekap → koreksi → VOID; satu mutasi dua rencana → VOID; CSV → cocok/lepas; pendapatan NONE/IN/OUT/transfer rekening bebas dan atribusi metode opsional; kas IN/OUT/transfer dan transaksi serentak. Gunakan data uji pilihan pemilik.

Perintah tes aman tanpa DB aplikasi:

```sh
php tools/tests/finance_allocation_bank_smoke.php
php tools/tests/finance_allocation_bank_smoke.php --export-ui
# Di IDE dengan Chromium yang dapat berjalan; pakai UI_FIXTURE dari keluaran tes:
node tools/tests/finance_allocation_browser.cjs /tmp/finance-allocation-ui-XXXXXX
```

Full quality gate/release build tidak dijalankan dalam batch Telegram ini. Batasan dan blocker rilis lain di `_28` tetap berlaku.
