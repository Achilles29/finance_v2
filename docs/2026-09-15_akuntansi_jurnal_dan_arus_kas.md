# Akuntansi, jurnal dan arus kas aktual

Batch 258–259, tindak lanjut Batch 260 — 2026-09-15. **CODE / TEST SINTETIS PASS; SQL 15a DAN 15b USER_REPORTED_APPLIED. POSTCHECK / UAT / RILIS BELUM SELESAI.**

Pembaruan 2026-09-15 10:25 WIB: pengguna mengonfirmasi SQL 15b sudah dijalankan. Waktu ini pencatatan konfirmasi, bukan waktu eksekusi DB yang diverifikasi. Tidak mengulang apply/checksum atau menandai schema/sidebar/izin sudah terverifikasi. Pada Batch 260 pekerjaan kode beralih ke presensi PH; tidak ada integrasi jurnal otomatis baru.

Pembaruan 2026-09-15 09:35 WIB: pengguna mengonfirmasi sudah menjalankan SQL `2026-09-15a_finance_general_ledger.sql`. Ini waktu pencatatan konfirmasi, bukan timestamp eksekusi DB terverifikasi. Tidak mengulang apply; schema, sidebar/izin dan alur pengguna belum diverifikasi langsung.

## Tujuan dan batas

- Memisahkan arus kas aktual dari estimasi. Semua mutasi masuk/keluar dicakup tanpa dibatasi sales/purchase atau kategori yang sudah dikenali.
- Membangun buku besar debit–kredit yang terpisah dari saldo transaksi. Laba-rugi, neraca saldo, neraca dan perubahan ekuitas membaca jurnal yang sama, bukan mencampur estimasi gaji dengan kas aktual.
- Ini **fondasi akuntansi yang ditinjau manual**, bukan integrasi akrual otomatis semua modul atau klaim kepatuhan penuh SAK/IFRS. Pengakuan, bukti, kebijakan kas/setara kas, kelengkapan HPP/persediaan/utang/piutang/pajak/payroll/penyusutan perlu ditinjau penanggung jawab keuangan. Neraca seimbang tidak membuktikan semua dokumen sudah dicatat.
- Rujukan prinsip: [IAS 7 — arus operasi, investasi, pendanaan dan rekonsiliasi kas](https://www.ifrs.org/issued-standards/list-of-standards/ias-7-statement-of-cash-flows/), [IAS 1 — komponen penyajian laporan](https://www.ifrs.org/issued-standards/list-of-standards/ias-1-presentation-of-financial-statements/). Rujukan ini bukan pernyataan bahwa aplikasi sudah memenuhi seluruh standar.

## Halaman dan alur operator

Halaman: `/finance-reports/accounting`. Sidebar yang disiapkan SQL: **Keuangan → Akuntansi dan Jurnal**. Izin `finance.accounting.index`: view untuk membaca, create untuk posting; SUPERADMIN mendapat izin awal. Tidak mengubah izin role lain.

1. **Arus Kas Aktual**: pilih bulan dan mata uang. Saldo awal + masuk − keluar = saldo akhir. Tabel per rekening menyertakan rekening nonaktif, serta selisih current balance terhadap saldo awal + seluruh riwayat. Selisih current balance bukan pembandingan langsung terhadap saldo bulan lama.
2. **Saldo awal pembukuan**: pilih akhir hari sebelum mulai akuntansi. Kas diambil dari semua mutasi sampai tanggal itu; periksa bukti kasnya, lalu lengkapi saldo persediaan/piutang/aset/utang/ekuitas. Satu jurnal saldo awal, tidak otomatis menaruh selisih ke modal. Perusahaan kosong boleh membuka pembukuan nol tanpa baris setelah operator mengisi referensi/keterangan dan mengonfirmasi. Tanggal setelah saldo awal merupakan lingkup pembukuan baru; histori sebelumnya tidak otomatis diimpor.
3. **Belum Dijurnal**: tinjau tiap mutasi IDR setelah saldo awal. Kas diisi otomatis dari sumber. Pilih akun lawan, pecah beberapa akun jika perlu, dan pilih operasi/investasi/pendanaan. Saran kategori manual hanya saran. POS/purchase/pinjaman tidak otomatis dipetakan menjadi pendapatan/beban.
4. **Jurnal nonkas**: catat HPP, persediaan, pengakuan penjualan kredit, gaji terutang, penyusutan dan penyesuaian lain dari bukti. Contoh HPP: debit HPP, kredit Persediaan; membayar stok bukan otomatis beban HPP. Jangan menduplikasi pengakuan yang sudah dicatat lewat sumber lain.
5. **Buku Besar / Neraca Saldo / Laba-Rugi / Neraca / Perubahan Ekuitas**: semua berdasarkan tanggal jurnal. Saldo laba sebelum periode dan laba berjalan diperhitungkan pada ekuitas, termasuk akun kontra aset/prive. Semua berlabel draf, dengan kelengkapan kas dan pengakuan nonkas terpisah.

Pemasukan/pengeluaran tidak harus sama, tetapi perubahan kas harus dapat direkonsiliasi. Semua mutasi termasuk pinjaman, DP, payroll, refund/VOID dan modul belum dikenal tetap masuk arus kas. Transaksi belum ditinjau ditampilkan sebagai belum diklasifikasikan; tidak disembunyikan untuk membuat laporan terlihat cocok.

## Aturan keselamatan

- Posting hanya POST, session CSRF terpisah, RBAC create, actor valid, payload maksimal 64 KiB dan maksimal 100 baris. Nominal maksimal 12 digit sebelum desimal, dua desimal; pengujian keseimbangan menggunakan integer sen.
- Setiap baris debit ATAU kredit positif; total persis seimbang. Jurnal sumber mengunci mutasi, memeriksa fingerprint, tanggal/nominal/rekening/mata uang, lalu memasang unique source dan request key. Retry sama menghasilkan ID sama; isi berbeda ditolak.
- Transaksi DB, mutex jurnal, lock periode, serta audit atomik. Gagal audit membatalkan header/baris. Tidak ada writer ke `fin_company_account` atau `fin_account_mutation_log` pada modul ini. Jurnal tidak mengubah uang dua kali.
- Jurnal immutable: tidak ada endpoint edit/delete. Penyesuaian nonkas bisa dibalik dengan jurnal baru pada tanggal yang sah, sekali saja. Pembalik tidak boleh mendahului jurnal asal.
- Koreksi nominal kas tetap lewat modul asal. Mutasi pembalik yang sudah punya jurnal asal otomatis membalik akun tersebut pada tanggal pembalikan; tidak menghapus efek bulan sebelumnya. Bila mutasi asal berada sebelum tanggal pembukaan buku, perlu pemilihan akun berdasarkan saldo awal/bukti. Salah akun pada jurnal kas dikoreksi lewat jurnal nonkas dengan referensi GL asal, bukan membalik kas sepihak.
- Transfer internal: satu sumber per sisi melalui akun perantara 1190; kedua sisi perlu dijurnal. Saldo perantara tidak nol diberi peringatan. Tidak ada pendapatan/beban dari transfer. Mata uang asing tidak dijumlahkan dengan IDR.
- Status belum lengkap ditampilkan jika saldo awal tidak sebelum periode, mutasi belum dijurnal (termasuk periode sebelumnya), sumber berubah/hilang, atau kas GL berbeda dari mutasi/current balance. Pemeriksaan fingerprint dibatasi 20.000 sumber dan menampilkan belum lengkap bila terlampaui, bukan menganggap lulus.

## SQL aktivasi — USER_REPORTED_APPLIED

File: `sql/2026-09-15a_finance_general_ledger.sql`. Target yang diberikan **db_finance**; pengguna sudah mengonfirmasi menjalankannya. Transport Telegram melarang perubahan DB/config; agent tidak menjalankan ulang SQL atau membaca transaksi staging pada tindak lanjut ini.

- Menambahkan empat tabel: `fin_gl_guard`, `fin_gl_account`, `fin_gl_journal`, `fin_gl_line`.
- Seed referensi: satu guard, 24 akun akuntansi, page/menu dan izin awal SUPERADMIN. **Tidak ada saldo awal, transaksi, backfill, pengubahan saldo rekening atau identitas perusahaan.**
- Prasyarat: schema aplikasi existing untuk rekening/mutasi/period-close/audit serta registry page/menu/role; `grp.finance` sudah ada. Jurnal tidak bergantung pada eksekusi ulang 14c.
- `CREATE TABLE IF NOT EXISTS` dan seed non-overwrite bukan bukti kompatibilitas schema parsial. Periksa kolom/unique/FK/engine sesudah apply. DDL melakukan implicit commit; jangan mengandalkan rollback transaksi untuk membatalkan pembuatan tabel.
- Rollback aplikasi mempertahankan semua tabel/riwayat. Jangan drop/clear jurnal. Tidak mengubah checksum SQL setelah dipakai.

Perintah terdahulu berikut disimpan sebagai arsip, **bukan instruksi mengulang SQL**:

```bash
mysql -u root -p db_finance < /www/wwwroot/finance/sql/2026-09-15a_finance_general_ledger.sql
```

Password tidak dicantumkan di perintah/chat. Status sudah USER_REPORTED_APPLIED berdasarkan konfirmasi pengguna; verifikasi struktur/registry terpisah sebelum menandai terverifikasi. **SQL belum didaftarkan ke katalog/allowlist customer; jangan jalankan runner release sebagai jalan pintas.**

## Validasi dan hasil

- [x] `php tools/tests/finance_accounting_smoke.php`: **162 PASS**, menggunakan model/writer nyata pada SQLite `:memory:`. Schema fixture diadaptasi dari SQL sumber; bukan bukti DDL/FK/locking MariaDB.
- [x] Cakupan: angka sen, saldo awal dan nol, keseimbangan jurnal, sumber IDR/akun nonaktif, seluruh jenis cashflow, pajak/DP/pinjaman/modal/prive bukan sales/beban, HPP vs purchase, akrual gaji, depresiasi, transfer, koreksi/VOID lintas bulan, replay, sumber stale, audit rollback, periode tertutup, persamaan neraca dan kas GL, serta antrean historis.
- [x] Controller nyata memakai stub HTTP: izin, method, CSRF, batas payload, invalid JSON, writer tidak dipanggil pada request ditolak. Render 22 varian tab/mode view, escaping nama rekening, read-only tanpa form writer, pesan schema belum aktif.
- [x] Regresi allocation/bank **392**, klasifikasi mutasi **72**, quality gate contract **28**, Finance UI shell **54**, route collision **PASS**. Gate required baru `finance-accounting`; sintaks PHP/JS dan konsistensi roadmap diperiksa.
- [ ] `node tools/tests/finance_accounting_browser.cjs`: disiapkan untuk 360/768/1280 px dengan HTML sintetis/network diblokir. Percobaan gagal saat membuka browser (`read ECONNRESET`); **belum ada PASS visual/interaktif**. Jalankan di IDE yang memungkinkan browser, jangan mengklaim render PHP sebagai UAT.
- [ ] DDL MariaDB disposable/replay, unique/FK/concurrency dan UAT staging belum dijalankan; perubahan DB dilarang pada transport ini.

## Pekerjaan lanjutan, jangan ditandai selesai

- [x] Pengguna mengonfirmasi apply SQL; tidak mengklaim hasil DB sudah diverifikasi.
- [ ] Postcheck staging termasuk metadata sidebar/RBAC; UAT pengelola dari saldo awal → mutasi/jurnal → penyesuaian → laporan → koreksi.
- [ ] Uji MariaDB concurrency/lock period closure serta replay migrasi; dampak dokumen sumber dihapus/berubah setelah posting perlu ditelusuri, bukan otomatis diperbaiki.
- [ ] Integrasi pengakuan otomatis berbasis dokumen POS/purchase/HPP/payroll/penyusutan/utang/piutang, dengan identitas sumber unik lintas pengakuan dan pembayaran. Saat ini seluruh akrual nonkas harus ditinjau/dimasukkan manual.
- [x] Kode administrasi COA nonkas dan pemetaan saran melalui UI + panduan awam: Batch 259 di bawah. Aktivasi/pengujian MariaDB tidak ikut ditandai selesai.
- [ ] Pengaturan awal kebijakan kas/setara kas, rincian arus kas campuran per satu pembayaran, multi-currency/kurs, perbandingan antarperiode, catatan laporan dan workflow persetujuan/penerbitan. Saat ini satu mutasi memakai satu kelompok cashflow; pemecahan kelompok dan kebijakan rekening perlu pengembangan.
- [ ] Tutup buku akuntansi/snapshot tersendiri, serta reclass berelasi terstruktur. Tutup periode existing tetap mencegah posting tanggal CLOSED, tetapi tidak otomatis menyatakan seluruh jurnal/bukti lengkap atau menutup laba ke saldo laba.
- [ ] Register hash/dependency/allowlist SQL dan kode customer, metadata-only clean install/upgrade, review readiness rilis. Tidak ada artifact/build/push/deploy atau perubahan Control pada batch ini.

## Batch 259 — Asisten dan pengaturan akun

### Sudah dikerjakan dalam kode

- Tab `/finance-reports/accounting?tab=settings`: daftar akun akuntansi (COA), pencarian, tambah akun nonkas, perjelas nama dan aktif/nonaktif terbatas; panel pemetaan jenis transaksi. Filter bulan disembunyikan di tab pengaturan/panduan karena tidak berkaitan dengan pengaturan global.
- Izin `finance.accounting.settings` **view/edit** terpisah dari `finance.accounting.index` create untuk posting. SQL 15b memberi grant awal hanya SUPERADMIN; izin role lain tetap ditentukan pengelola. Tidak membuat sidebar duplikat—akses dari tab modul Akuntansi dan Jurnal.
- Kode akun 4–10 angka, awalan menentukan kelompok; kelompok dan kode existing tidak dapat diubah. Akun kas/perantara transfer dilindungi. Nama boleh diperjelas dan akan tampil pada laporan lama; **nominal, kode akun baris, dan header jurnal lama tidak diubah**. Akun yang pernah dipakai jurnal atau pemetaan aktif tidak dapat dinonaktifkan, agar pembalik tetap dapat diposting. Tidak ada hapus akun.
- Lima belas saran: pendapatan lain, selisih lebih, biaya operasional/promo/platform/selisih kurang, modal/prive, stok tunai, aset tetap tunai, pelunasan utang/piutang, DP pelanggan dan pokok pinjaman masuk/keluar. Kategori manual hanya tersedia pada sumber FINANCE/FINANCE_RECON/REVENUE_RECON dengan arah/kategori yang cocok; POS/purchase tidak otomatis dianggap pendapatan/beban. Saran lain dipilih sendiri berdasarkan bukti, tidak otomatis menyimpulkan arti dokumen.
- Form **Belum Dijurnal → Tinjau & jurnal**: pilih jenis, baca penjelasan, centang pemeriksaan bukti, tekan **Isi baris sesuai saran**, periksa debit/kredit, kemudian posting. Pengisian saran tidak menyimpan jurnal. Satu akun lawan dan nominal persis sumber; transaksi pajak/bunga/DP campuran tetap jurnal rinci manual. Tidak ada legacy prefill yang mengabaikan pemetaan nonaktif.
- Mengubah akun/nominal/baris/kelompok arus kas/jenis atau mencabut konfirmasi mengembalikan ke mode manual, dengan pesan yang terlihat. Mengganti baris yang sudah diisi meminta konfirmasi. Operator tetap bertanggung jawab memeriksa pemilihan akun, bukan sekadar total debit=kredit.
- Simpan konfigurasi memakai POST, CSRF sesi, izin edit, guard yang sama dengan posting, validasi kelompok/nonkas, hash state dan akun, audit perubahan, serta transaksi atomik. Retry isi identik tidak membuat revisi/audit kedua. Konflik/stale tidak menimpa pengaturan baru; akun/saran yang berubah di antara preview dan posting ditolak. Jurnal mencatat jenis/hash/revisi saran yang dikonfirmasi pada audit. Pembalik mutasi tetap memakai akun asli meskipun pemetaan sekarang berubah.
- Tab `/finance-reports/accounting?tab=guide`: tugas admin server/pengelola/operator, cara setting UI, kamus awam, contoh listrik Rp100.000, stok Rp500.000/HPP terpisah, modal/pinjaman/DP, kapan perlu jurnal rinci dan pemeriksaan akhir bulan. Contoh merujuk prinsip IAS 2/IAS 7 pada tautan resmi, bukan jaminan kelengkapan akrual/kepatuhan penuh.

### SQL aktivasi 15b — USER_REPORTED_APPLIED

`sql/2026-09-15b_finance_journal_assistant.sql`, target pada perintah **db_finance**, prasyarat SQL 15a serta registry role/page/permission existing. Pengguna sudah mengonfirmasi apply. Agent tidak menghubungkan/menulis DB dari Telegram. SQL 15a dan 15b **tidak perlu diulang**.

- Menambahkan `fin_gl_mapping` dengan FK ke COA serta page/grant pengaturan. Mapping awal kosong: saran bawaan bukan konfigurasi yang sudah dikonfirmasi pengelola.
- Tidak memperbarui saldo/akun bank, mutasi, nominal jurnal, transaksi historis, customer identity, atau melakukan auto-post/backfill. Tidak mengubah 15a/checksum yang sudah dipakai.
- Perintah berikut adalah **arsip perintah yang sudah dikonfirmasi dijalankan, bukan instruksi mengulang**. Password tidak dicantumkan:

```bash
mysql -u root -p db_finance < /www/wwwroot/finance/sql/2026-09-15b_finance_journal_assistant.sql
```

- Status sekarang **USER_REPORTED_APPLIED**, bukan otomatis postcheck lulus. Verifikasi kolom/PK/FK/engine, page tunggal dan izin, serta alur UI. DDL MariaDB implicit commit; jangan menganggap rollback SQL bisa membatalkan semua DDL. `IF NOT EXISTS` bukan bukti tabel parsial sudah kompatibel.
- Tanpa 15b, kode baru mempertahankan jurnal manual dan saran bawaan dengan 15a; penyimpanan pengaturan ditutup dengan pesan jelas. Rollback kode mempertahankan seluruh konfigurasi dan audit/jurnal, tidak menghapus tabel.
- Belum managed/customer-allowlisted. Jangan menjalankan runner/seluruh folder SQL sebagai jalan pintas atau menyalin pemetaan customer staging ke paket baru.

### Validasi Batch 259

- [x] `php tools/tests/finance_accounting_smoke.php`: **346 PASS** dengan model/controller asli, SQLite `:memory:`, actual SQL diadaptasi ke fixture; mencakup fallback tanpa 15b, 15 jenis saran, beda kategori/arah, account CRUD terbatas, proteksi kas/akun dipakai, stale/retry, audit rollback, no source/history writes, pemetaan nonaktif, konfirmasi/tampering, pembalikan setelah mapping berubah, dan izin/CSRF/batas payload. Render **27 varian** mencakup partial panduan/pengaturan nyata, izin baca/tulis/schema belum aktif serta escaping nama akun.
- [x] `node tools/tests/finance_accounting_client_smoke.cjs`: **38 PASS** menggunakan JS asli + DOM sintetis: konfirmasi, pengisian exact, edit kembali manual, cancel, fingerprint/CSRF, retry, error sebagai teks, pencarian, readonly dan normalisasi endpoint. **Bukan browser/native form/visual UAT.**
- [x] PHP lint file accounting/routes/tes dan pemeriksaan sintaks JS; regresi allocation/bank 392, klasifikasi mutasi 72, quality-gate contract 28, UI shell 54, route collision, roadmap consistency 26/30 SQL.
- [ ] `node tools/tests/finance_accounting_browser.cjs`: fixture diperluas asisten/pengaturan untuk 360/768/1280 px; percobaan tetap gagal saat browser dimulai (`read ECONNRESET`). Tidak ada hasil visual/interaktif browser yang dinyatakan PASS.
- [ ] MariaDB actual DDL/replay/FK/concurrency, postcheck metadata/izin staging dan UAT pengguna belum dilakukan. Tidak ada koneksi DB aktual, perubahan data transaksi, pengaturan server/credential, push/deploy atau notifier/bridge pada batch ini.

### Berikutnya

1. Konfirmasi apply 15b sudah diterima; lanjut postcheck serta UAT lewat IDE, bukan apply ulang: Pengaturan Akun → tambah/ubah pemetaan → Tinjau mutasi → jurnal → laporan → koreksi. Pastikan perbedaan role baca/posting/pengaturan.
2. Integrasi pengakuan dokumen bertahap (POS/purchase/HPP/payroll/asset/utang/piutang) dengan identitas sumber dan aturan pengakuan vs pembayaran yang mencegah pencatatan ganda; belum diaktifkan batch ini.
3. Tutup buku/persetujuan/penerbitan dan laporan akrual lengkap tetap pekerjaan terbuka. Kelulusan tes sintetis atau saldo debit=kredit bukan bukti seluruh modul sudah otomatis sinkron.
