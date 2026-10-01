# Audit Total Aplikasi Finance dan Roadmap Pengembangan

**24 September 2026 — mismatch nilai component:** [x] samakan biaya saldo dengan alokasi lot pada POS/produksi/adjustment; [x] retur/void membawa biaya yang benar; [x] rebuild hanya bulan aktif, mempertahankan ID dan koreksi nilai; [x] proyeksi harian/opening/hitung fisik diselaraskan; [x] 45 regresi in-memory + 65 pemeriksaan MariaDB disposable, lint dan static PASS. [ ] UAT UI; [ ] repair data historis terpisah, belum dijalankan; [ ] review kegagalan existing hitungan guard/token CSRF pada matriks A2. Tidak mengubah database/config aktif atau artefak release. [Bukti dan checklist](2026-09-24_component_fifo_value_consistency.md).

**23 September 2026 — PDF PO/SR via WA:** [x] perbaiki jalur PDF yang melewatkan pembacaan stok dan salah menandai semua baris non-bahan baku; [x] samakan reader divisi/gudang/catatan dengan unduhan; [x] uji parity HTML/PDF nyata dan pembacaan read-only. [ ] UAT penerimaan PDF baru di grup. [Bukti](2026-09-23_module_notifications.md).

**23 September 2026 — Daily Sales PDF WA:** [x] event/manual send dari laporan sesuai tanggal/outlet; [x] PDF privat, dedup, permission/CSRF/lisensi; [x] uji renderer nyata dan antrean disposable. Pengaturan WA pengguna dipertahankan. [ ] UAT kirim PDF nyata; [ ] tindak lanjut mapping Procurement existing pada gate global. [Bukti/panduan](2026-09-23_module_notifications.md).

**23 September 2026 — revisi pengaturan WA:** [x] empat tab; [x] checklist beberapa grup per modul termasuk grup nonaktif; [x] pisahkan flag balasan chat dari penerima notifikasi hingga validasi/worker; [x] 62 uji DB disposable, 74 DOM + 15 JS, 19 Chrome PASS. [ ] UAT pesan nyata. Gate global 133/136, tersisa temuan konfigurasi lokal/scanner fixture existing yang tidak diubah dalam batch ini. [Rincian](2026-09-23_module_notifications.md).

**23 September 2026 — integrasi notifikasi modul:** [x] sakelar/tujuan per kejadian pada pengaturan WA/Telegram; [x] antrean self order/online order melalui worker existing; [x] tombol pengajuan PO/SR di daftar/detail, scope/CSRF/dedup; [x] uji MariaDB disposable dan UI. [ ] apply SQL aktif/konfirmasi operator dan UAT pengiriman bot nyata. WA personal tetap terkunci sesuai perlindungan akun existing. Detail dan checklist: [laporan modul](2026-09-23_module_notifications.md).

**20 September 2026 — kontrol stok procurement:** [x] stok divisi/gudang sekarang pada pengajuan, detail/list, PDF, PO/SR manual; [x] peringatan sebelum verifikasi/simpan; [x] regresi race edit/verifikasi dan timeout. Tidak mengubah saldo atau SQL aktif. Checklist UAT/bukti dan batas validasi pada [laporan modul](2026-09-20_procurement_current_stock.md). UAT pengguna dan build resmi Control tetap terpisah dari tes fixture.

**Update 20 September 2026 — alpha.22/v10:** lima SQL manual sudah masuk katalog customer dengan proof adopsi schema; tambahan korektif `2026-09-20a` menyelaraskan ENUM status POS tanpa resep. Total 26 clean-install / 25 upgrade / 7 legacy. Paket jurnal dan panduan dilengkapi. Status apply database aktif pada register tetap historis: batch ini hanya memakai MariaDB disposable. Hasil/gap update aplikasi aktif ada pada [handoff terbaru](2026-09-20_customer_v10_migrations_update_control_handoff.md); belum publish atau rollout customer.

**Batch 270 — 2026-09-18 (UX installer customer saja):** satu perintah persiapan
Linux dan wizard dengan probe database/progres/resume untuk alpha.18/v7.
Checklist/cutoff ada di `_28` dan [handoff](2026-09-18_guided_setup_control_handoff.md).
Tidak mengubah proses bisnis/DB development, tidak memasang ke core2 atau menutup
temuan bisnis maupun lima SQL belum managed. Hasil fixture bukan aktivasi Control live.

**Batch 269 — 2026-09-18 (installer/packaging saja):** satu folder customer dengan
document root `public/`, setup Indonesia, pendamping non-root, konfigurasi/key lokal
dan journal pemulihan diimplementasikan sebagai alpha.17 / profil v6. Checklist
komersialisasi berada di `_28`, detail pada [handoff Control](2026-09-18_single_folder_installer_control_handoff.md).
Tidak mengubah proses bisnis/DB development atau menutup PR procurement, void batch,
UAT bisnis, maupun lima SQL belum managed. Windows masih membutuhkan uji host nyata;
kelulusan Linux installer bukan klaim seluruh aplikasi siap jual.

**Batch 268 — 2026-09-18 (konfigurasi/installer/packaging saja):** konfigurasi lokal
customer alpha.16 dan pengamanan/readiness clean-install dicatat di `_28` serta
[handoff Control](2026-09-18_customer_local_config_control_handoff.md). Tidak ada
perubahan proses bisnis, SQL atau data master. Lima SQL development yang belum
managed tetap terbuka (`2026-09-14c`, `2026-09-15a/b/c`, `2026-09-16a`); tes A5 pada
repo mentah masih menolak `unacknowledged_sql`. Kelulusan paket terkurasi tidak
menutup isu bisnis maupun kewajiban registrasi/migrasi tersebut.

**Batch 267 — 2026-09-17 (integrasi produk saja):** domain installer menjadi metadata opsional; credential pengganti menjaga journal/binding/identitas dan tidak mengulang SQL; heartbeat memakai URL lokal tepercaya dan metadata allowlist. Proses bisnis, mismatch historis, PR-01/02/03 dan lima SQL belum managed **tidak** ditutup oleh batch ini. Checklist komersialisasi/cutoff di `_28`; [handoff dan uji](2026-09-17_installer_heartbeat_control_handoff.md).

**Batch 266 — 2026-09-16 (tindak lanjut audit build Control, bukan perbaikan bug bisnis):**

- [x] Tiga false positive credential fixture direview dan dikecualikan persis path/baris/kategori/hash; secret scan tetap aktif. Katalog SQL paket, implementasi konektor, safe-default settings, jumlah seed dan pemeriksaan izin installer diselaraskan. Detail pada [handoff CUSTOMER_CLEAN v4](2026-09-16_finance_customer_clean_v4_handoff.md).
- [x] Uji MariaDB disposable: clean-install/health dan backup–restore PASS, 306 tabel, 293 nonreferensi kosong, 0 data customer/demo; tidak membaca/mengubah DB usaha.
- [x] Finding STATIC satu variabel bulan pada view jurnal diperbaiki dengan fallback eksplisit; bulan pilihan controller tetap dipakai. Static aktual baseline 0, regresi jurnal 348 dan client 38 PASS. **Build penuh terisolasi terakhir exit 0 / delapan gate + validator independen PASS**; 0 customer/demo/secret findings. Bukan artifact published atau persetujuan cutoff Control.
- [ ] Integrasi bisnis/UAT dan temuan PR-01/02/03 tetap terbuka. Indikasi void batch setelah pemakaian dibalik telah dibuktikan terpisah, belum diimplementasikan; perubahan packaging bukan penutupan bug tersebut.
- [ ] Lima SQL development `14c/15a/15b/15c/16a` masih di luar katalog/paket, bukan diam-diam dimigrasikan. Readiness/approval cutoff customer hanya dicatat pada `_28`.

**Review Batch 265 — 2026-09-16 (lintas perbaikan Batch 1–264, bukan hanya pengajuan divisi):**

- [x] Disusun [checklist UAT seluruh perbaikan Finance](2026-09-16_checklist_uat_seluruh_perbaikan_finance.md): **98 skenario / 23 kelompok modul**, langkah/hasil yang diharapkan dan batas tes tulis/perangkat/admin. Ini lembar penerimaan, bukan roadmap ketiga. Semua kotak tes manual tetap kosong.
- [x] Regresi terpilih **22 suite / 1.606 assertion agregat PASS**, serta lint **62 file PHP application** yang berubah/baru PASS. Source/fixture/SQLite memory/DOM sintetis; bukan bukti UAT, kesesuaian nominal produksi atau E2E semua modul.
- [ ] PR-01/02/03 pada Batch 264 tetap terbuka. Jurnal belum mencakup seluruh pengakuan akrual otomatis; uji browser/perangkat/konkurensi MariaDB dan penerimaan pengguna belum lengkap. Prioritas engineer tetap PR-01 → PR-02 → PR-03; pengguna dapat mulai skenario FIN/REC/CTL/ALC/GL/POS/HR secara terisolasi.
- [x] Katalog/profil paket diperiksa read-only; beberapa SQL dan kode terbaru belum terdaftar untuk customer. Handoff ini dicatat di `_28`, tidak ditutup oleh tes source. Tidak ada perubahan runtime/SQL/database/server/credential/bridge, push atau deploy pada review ini.

**Review Batch 264 — 2026-09-16 06:21 WIB (A3 procurement: REVIEW_OPEN):**

- [x] SQL `2026-09-16a` ditandai **USER_REPORTED_APPLIED** sesuai konfirmasi pengguna; tidak dijalankan ulang/diubah checksum. Postcheck metadata/UI belum diterima; bukan SCHEMA_VERIFIED.
- [x] Review source dan ulang tes existing **185** pemeriksaan fitur + **102** pendukung lulus; dua probe tambahan justru mereproduksi **tiga temuan terbuka**, bukan dinyatakan PASS. Fixture SQLite/DOM sintetis, tidak membaca transaksi aplikasi. [Bukti teknis + 19 checklist tes manual U01–U19](2026-09-16_konfirmasi_stok_pengajuan_divisi.md).
- [ ] **PR-01 Tinggi:** jalur edit memeriksa status/link sebelum transaksi tanpa lock/recheck. Dalam interleaving dengan verifikasi, dapat mengembalikan request VERIFIED menjadi SUBMITTED dan mengganti baris meskipun sudah ada PO/bukti. Perbaiki locking/recheck writer edit dan review reject/void; hindari edit-verifikasi simultan sementara.
- [ ] **PR-02 Sedang:** barang OPERASIONAL yang terkait material bisa hilang dari review bila lookup pemetaan gagal. Bedakan bukan material yang valid dari lookup gagal; jangan menerima snapshot kosong akibat kegagalan layanan.
- [ ] **PR-03 Sedang:** fetch preview tanpa timeout aplikasi membuat refresh nonaktif dan verifikasi tertahan bila jaringan tidak menyelesaikan respons. Perlu abort/timeout dan retry tanpa snapshot lama.
- [x] Scope turn ini hanya review/checklist/docs/probe; tidak memperbaiki runtime, mengubah SQL/schema/konfigurasi, atau menyatakan UAT/release selesai. Prioritas berikut: PR-01 → PR-02 → PR-03, regression/UAT, baru outstanding procurement.

**Delta Batch 263 — 2026-09-16 (A3: kontrol stok saat verifikasi pengajuan divisi; implementasi, lihat temuan review Batch 264):**

- [x] Kolom snapshot gudang yang tersembunyi pada mode verifikasi ditampilkan. Panel baru membandingkan kuantitas pengajuan dengan saldo sistem divisi peminta dan gudang dalam satuan isi yang sama, terpisah per lokasi reguler/event.
- [x] Stok divisi tersisa/negatif atau saldo tidak diketahui memerlukan nama pihak divisi, alasan dan pernyataan konfirmasi. Tidak melarang pengadaan yang memang diperlukan; saldo tidak terbaca tidak dianggap nol. Saldo divisi diketahui nol tidak memerlukan alasan tambahan jika gudang terbaca normal.
- [x] Server memeriksa ulang saldo dan isi pengajuan, mengikat bukti ke request/actor/sesi/lokasi serta batas 15 menit. POST/CSRF/scope dijaga. Riwayat konfirmasi, snapshot, waktu dan pelaku disimpan atomik dengan hasil verifikasi dan ditampilkan pada detail pengajuan serta SR/PO turunannya.
- [x] Tes reader/policy/view **52**, model verifikasi/HTTP **114**, client **19 PASS**, required gate terdaftar. Tidak membaca/mengubah transaksi nyata, menghitung ulang stok, atau mengganti alur pembagian SR/PO. [Laporan modul dan langkah aktivasi](2026-09-16_konfirmasi_stok_pengajuan_divisi.md).
- [x] SQL `2026-09-16a_procurement_stock_review.sql` **USER_REPORTED_APPLIED**, konfirmasi dicatat 2026-09-16 06:21 WIB. Harus tersedia pada database instance aplikasi sebelum verifikasi bahan baku disimpan; belum postcheck langsung. Pengajuan operasional murni tanpa material tidak memerlukan tabel baru.
- [ ] Postcheck MariaDB, UAT desktop/mobile dan konkurensi nyata belum dilakukan; tiga temuan Batch 264 tetap terbuka.
- [ ] Batch lanjutan terpisah: tampilkan kebutuhan lain yang masih berjalan (pengajuan/SR/PO outstanding) tanpa double count. Guard ini ada pada verifikasi **pengajuan divisi**, belum pada SR/PO manual yang dibuat langsung. Kesiapan paket customer tetap di `_28`.

**Delta Batch 262 — 2026-09-15 (A3: urutan Laporan Penjualan POS):**

- [x] `/pos/reports/sales` memakai `ordered_at DESC, id DESC`, bukan waktu pembayaran/konfirmasi. Sorting dilakukan sebelum pagination; tabel desktop dan kartu mobile memakai dataset yang sama. Caption urutan diperjelas.
- [x] Filter periode, status, outlet, scope, metode pembayaran, agregat dan HPP tidak diubah. Tes model/query ordering dengan fixture SQLite memory **18 PASS**; required gate `pos-sales-order-sort` ditambahkan. Tidak membaca/mengubah data transaksi atau membuat SQL baru.
- [ ] UAT browser pada filter yang biasa digunakan belum dilakukan. Detail perubahan/bukti pada execution log Batch 262; handoff artifact tetap pada `_28`.

**Delta Batch 261 — 2026-09-15 (A3: pusat panduan aplikasi, implementasi pendukung C5):**

- [x] `/guide`: 26 bab terkurasi, enam topik, pencarian/peran, satu bab per layar, navigasi dropdown mobile, langkah → hasil yang dicek → peringatan → tautan modul sesuai izin. Bukan pembaca folder docs internal.
- [x] Bab UI dan admin server dipisahkan. `system.guide.index` wajib; empat bab server memerlukan tambahan `system.guide.server`. Filter/pencarian/navigasi dilakukan setelah pembatasan server; halaman hanya GET, contoh perintah tidak dieksekusi dan secret/config runtime tidak dibaca. Versi sumber dari field manifest terpilih, bukan klaim artifact/deployment terverifikasi.
- [x] Panduan instalasi/konfigurasi/cron/backup/update; alur POS, pembelian/stok/produksi, presensi/payroll/aset, kas/rekon/jurnal/tutup bulan. Keterbatasan APK dan jurnal otomatis dijelaskan. Scheduler staging lama, repair dan seed tidak ditawarkan sebagai cron customer rutin.
- [x] Tes controller/library/view dan seed metadata SQLite memory **398 PASS**, JS salin/cetak **10 PASS**; keduanya required quality gate. Detail dan UAT pada [laporan modul panduan](2026-09-15_pusat_panduan_aplikasi.md).
- [x] SQL `2026-09-15c_application_user_guide.sql` **USER_REPORTED_APPLIED**: pengguna mengonfirmasi sudah dijalankan, dicatat 2026-09-15 11:24 WIB. Dua page, satu menu di Sistem, izin awal View SUPERADMIN; metadata/sidebar/izin belum diverifikasi langsung. Agent tidak mengubah database, file SQL/checksum atau mengulang apply. Perintah pada laporan kini hanya arsip.
- [ ] Aktivasi sidebar/izin nyata, replay MariaDB, browser desktop/mobile/cetak dan walkthrough pengguna awam belum dibuktikan. Registrasi migrasi/paket customer dan acceptance C5 tetap pada `_28`, bukan ditutup oleh tes kode.

**Delta Batch 260 — 2026-09-15 (A4.3: regresi presensi PH otomatis):**

- [x] Penyebab pesan “Layanan validasi PH belum tersedia” terbukti: guard memeriksa method `load` padahal loader CodeIgniter adalah properti. Diperbaiki di auto PH dan sinkron GRANT/USE setelah presensi reguler pada `My_portal_model`, tanpa bypass hak/saldo/masa berlaku PH atau kontrak aktif.
- [x] Jadwal PH/PHB dengan hak/saldo valid diproses saat membuka Absensi Saya. Refresh tidak memakai jatah kedua; tidak menciptakan GRANT dari pemakaian PH. Status PH di UI jelas, tanpa tombol check-in/out/lokasi/GPS; shift biasa tetap menggunakan alur reguler. Catatan existing lain tidak ditimpa/dianggap sukses PH.
- [x] Hasil insert/status transaksi diperiksa sebelum commit. Tes aktual model portal/PH dengan fixture SQLite **58 PASS**, A4.3 contract **253**, meal policy **7**, regresi jurnal **346 + 38**, quality gate **28**, roadmap **26**. Tes `attendance-auto-ph` menjadi required gate. [Laporan modul PH](2026-09-15_presensi_ph_otomatis.md).
- [ ] UAT pegawai di staging dan konkurensi MariaDB belum dijalankan; agent tidak membuka halaman presensi live karena dapat menulis kehadiran. Tidak ada SQL PH baru, repair/backfill data lama, perubahan DB/config/credential, push/deploy atau perubahan bridge.
- [x] SQL jurnal **15b USER_REPORTED_APPLIED** berdasarkan konfirmasi pengguna, dicatat 2026-09-15 10:25 WIB. Bukan schema/izin terverifikasi; tidak dijalankan ulang. Langkah jurnal berikut: postcheck/UAT, lalu integrasi jurnal sumber bertahap tanpa duplikasi pengakuan/pembayaran, baru kelengkapan tutup buku/penerbitan.

**Delta Batch 259 — 2026-09-15 (A2/A3: asisten jurnal, pengaturan akun dan panduan awam):**

- [x] Tab **Pengaturan Akun**: tambah/perjelas nama akun nonkas, aktif/nonaktif terbatas, serta pemetaan 15 jenis transaksi ke akun/kelompok arus kas. Izin `finance.accounting.settings` terpisah dari izin posting; kas/perantara/kode/kelompok existing dilindungi. Tidak menghapus akun, menghitung ulang jurnal atau memindahkan uang.
- [x] Asisten pada **Belum Dijurnal → Tinjau & jurnal**: pilihan jenis berdasarkan arah/kategori sumber, penjelasan dan konfirmasi bukti sebelum mengisi satu akun lawan. Stok/aset, biaya, pelunasan, pinjaman, DP dan modal dibedakan. Bukan auto-post; edit manual membatalkan penanda saran. Mapping/hash akun stale/nonaktif ditolak server, provenance dicatat dalam audit; pembalik tetap memakai akun jurnal asal.
- [x] Tab **Panduan** diperluas: pembagian tugas admin/operator/pengelola, langkah UI, kamus, contoh nominal, pencegahan pengakuan ganda dan checklist akhir bulan. Pengaturan/panduan tidak menampilkan filter bulan yang tidak relevan.
- [x] Model/controller/partial render **346 PASS** SQLite memory; event/payload JS **38 PASS** DOM sintetis. Detail validasi dan sisa pada [laporan modul akuntansi](2026-09-15_akuntansi_jurnal_dan_arus_kas.md#batch-259--asisten-dan-pengaturan-akun). Uji browser kembali gagal memulai `read ECONNRESET`, bukan kelulusan visual/UAT.
- [x] SQL **2026-09-15b USER_REPORTED_APPLIED**: pengguna mengonfirmasi sudah menjalankan (dicatat Batch 260). Target pada perintah `db_finance`, prasyarat 15a. Tidak mengulang apply atau menyamakan konfirmasi dengan verifikasi schema/izin. UAT dan postcheck tetap terbuka.
- [ ] Berikutnya tetap postcheck/DDL MariaDB/UAT melalui IDE, lalu integrasi pengakuan dokumen per modul tanpa duplikasi pembayaran. Posting otomatis, kelengkapan akrual dan penerbitan/tutup buku belum selesai. Handoff customer hanya di `_28`; tidak mengubah Control/DB/credential/bridge atau push/deploy.

**Delta Batch 258 — 2026-09-15 (A2/A3: arus kas aktual + fondasi jurnal, SQL dijalankan pengguna; belum diverifikasi):**

- [x] Halaman `/finance-reports/accounting`: arus kas seluruh sumber (termasuk non-sales/non-purchase dan VOID menurut tanggal), semua rekening termasuk nonaktif dipisahkan per mata uang. Saldo awal + IN − OUT direkonsiliasi ke saldo akhir; selisih saldo tersimpan ditampilkan, bukan diperbaiki otomatis.
- [x] Kode jurnal debit–kredit IDR: saldo awal satu kali, mutasi sumber ditinjau manual, penyesuaian nonkas, pembalik nonkas dan jurnal pembalik kas menurut sumber. POST/CSRF/RBAC, periode CLOSED, integer sen, unique source/request, lock, audit atomik; tidak menulis saldo/transaksi sumber atau menghapus jurnal.
- [x] Buku besar, neraca saldo, laba-rugi, neraca dan perubahan ekuitas dari jurnal yang sama. Antrean belum dijurnal, sumber stale, perantara transfer dan selisih kas GL ditampilkan. **Laporan draf, bukan klaim pengakuan akrual otomatis lengkap/SAK/IFRS tersahkan.** HPP/pajak/payroll/penyusutan/penjualan kredit masih memerlukan jurnal berdasarkan bukti.
- [x] 162 tes sintetis model/controller/render PASS; regresi 392/72, gate contract 28, UI shell 54 dan route collision PASS. Required gate `finance-accounting`; laporan modul dan daftar sisa: [akuntansi, jurnal dan arus kas](2026-09-15_akuntansi_jurnal_dan_arus_kas.md).
- [x] Pengguna mengonfirmasi SQL `2026-09-15a_finance_general_ledger.sql` sudah dijalankan (konfirmasi dicatat 2026-09-15 09:35 WIB). Status **USER_REPORTED_APPLIED**, bukan schema/sidebar/RBAC terverifikasi. Tidak mengulang SQL atau menulis registry secara otomatis.
- [ ] Berikutnya: postcheck/UAT melalui IDE, integrasi pengakuan otomatis serta kelengkapan penerbitan/tutup buku akuntansi. DDL/concurrency MariaDB dan browser belum lulus; browser sebelumnya gagal dimulai (`read ECONNRESET`). Handoff katalog/allowlist dan uji customer hanya di `_28`. Jangan menyamakan fondasi ini dengan selesainya seluruh akuntansi.

**Pencatatan SQL — kesepakatan pengguna 2026-09-14:** setiap SQL baru yang belum bisa dieksekusi wajib ditandai **BELUM DIJALANKAN**, disertai target database, prasyarat/dampak dan perintah terminal siap salin tanpa password. Setelah pengguna mengonfirmasi eksekusi, tandai **DIJALANKAN PENGGUNA — BELUM DIVERIFIKASI**; naikkan ke **TERVERIFIKASI** hanya setelah ada hasil pemeriksaan schema/ledger yang sesuai. Status staging, validasi, dan kesiapan paket customer tidak disamakan. Catatan eksekusi disimpan di execution log; register SQL di dokumen ini menjadi acuan terkini.

**Delta Batch 257 — 2026-09-14 (status SQL staging terverifikasi; tidak perlu apply ulang):**

- [x] Pemeriksaan langsung dengan izin eskalasi pada `db_finance`, MariaDB 10.11.10, pukul 19:46:50 WIB: 95 kondisi diterima lengkap; seluruh kondisi struktur lulus. SQL `2026-09-14c` sudah terpasang, termasuk empat tabel metadata, tiga kolom rekening lawan dan enum TRANSFER.
- [x] Seluruh 19 migrasi katalog ber-policy `upgrade` mempunyai catatan/checksum yang cocok, termasuk Roast Connect. Hash lokal seluruh 20 entri katalog juga cocok. Satu entri `2026-09-05d` khusus `clean_install` sengaja tidak dipasang pada staging berisi data. Tujuh SQL legacy tidak diperiksa ulang struktur keseluruhannya dan tidak dijalankan ulang.
- [x] Tidak ditemukan SQL upgrade tertunda dalam cakupan tersebut; tidak mengeksekusi DDL/DML, mengubah saldo, konfigurasi, credential, atau menerapkan seed instalasi kosong.
- [ ] SQL `2026-09-14c`: **SCHEMA_VERIFIED_LEDGER_PENDING**. Struktur nyata sesuai, tetapi apply manual belum tercatat di `sys_schema_migration` dan belum terdaftar di katalog/allowlist customer. Registrasi terkontrol, uji migrasi/replay/konkurensi dan UAT tetap terpisah; bukan alasan mengulang DDL. Bukti rinci: [laporan modul](2026-09-14_finance_alokasi_bank_rekonsiliasi.md#verifikasi-staging--batch-257).

**Delta Batch 256 — 2026-09-14 (alat postcheck read-only; hasil ditindaklanjuti Batch 257):**

- [x] Menyiapkan satu perintah terminal untuk memeriksa 95 kondisi target/schema/index/metadata prasyarat dan checksum catatan migrasi. Hanya SELECT pada information_schema dan sys_schema_migration; tidak membaca/menulis transaksi atau credential. Panduan/perintah: [postcheck pada laporan modul](2026-09-14_finance_alokasi_bank_rekonsiliasi.md#pemeriksaan-setelah-sql-dijalankan--batch-256).
- [x] Membedakan struktur sesuai + ledger kosong akibat apply manual dari schema/checksum salah. Tidak menganjurkan apply ulang otomatis, menulis ledger, atau menyamakan hasil parsial dengan kelulusan. Output diikat fingerprint file sumber dan harus lengkap.
- [x] 241 pemeriksaan alat pada metadata sintetis, regresi integrasi 392, workspace 27, quality-gate 28 dan PHP lint PASS; required gate baru finance-allocation-postcheck. Tidak ada SQL migrasi baru/perubahan runtime aplikasi.
- [x] **Postcheck staging selesai pada Batch 257** setelah izin eskalasi: struktur lulus, catatan migrasi manual 14c belum ada. Batas sandbox pada Batch 256 adalah riwayat; UAT/konkurensi/registrasi customer belum selesai.

**Delta Batch 255 — 2026-09-14 (integrasi rekonsiliasi, mutasi dan laporan; tanpa SQL baru):**

- [x] Uji lintas model nyata: NONE/draft tidak mengubah kas; sembilan kategori IN/OUT sinkron ke Mutasi Rekening, estimasi harian/bulanan dan laba-rugi manajemen; transfer dua sisi menjaga total saldo serta tidak menambah laba/biaya. Posting ulang ditolak.
- [x] Klasifikasi ulang mengubah laporan saja; identitas biaya sama tidak dapat diposting lagi lewat Rekon Kas/Mutasi Kas. VOID biaya tertaut mengembalikan saldo dan sisa selisih, retry idempotent, koreksi di sesi baru dihitung sekali.
- [x] Regresi Rekon Kas terkonfirmasi dan diperbaiki: transfer ke rekening bersaldo negatif tidak lagi memaksa saldo tujuan menjadi nol. Nominal transfer sama dengan perubahan saldo; guard kecukupan sumber OUT tetap. Tidak memperbaiki/mengubah data historis.
- [x] 217 pemeriksaan tambahan, total required gate `finance-allocation-bank` **392 PASS** in-memory; PHP lint tiga file dan regresi terkait PASS. Kasus baru berada pada suite existing, bukan fase/fitur baru terpisah.
- [ ] Schema/ledger MariaDB setelah eksekusi pengguna, concurrency, browser dan UAT masih terbuka. SQL `2026-09-14c` tetap **USER_REPORTED_APPLIED**, bukan terverifikasi; registrasi paket customer menunggu bukti lanjutan. Rincian: [laporan modul](2026-09-14_finance_alokasi_bank_rekonsiliasi.md#batch-255--integrasi-rekonsiliasi--mutasi--laporan).

**Delta Batch 254 — 2026-09-14 (pelurusan Rekonsiliasi Pendapatan harian, menggantikan pembatasan pasangan metode Batch 253):**

- [x] Rekonsiliasi Pendapatan adalah cek penerimaan harian, bukan wajib settlement platform. UI menjelaskan perbedaan dengan seluruh saldo rekening dan memisahkan Simpan hasil cek dari Posting mutasi.
- [x] Selisih boleh NONE/biarkan terbuka, IN, OUT, atau TRANSFER antar rekening. Transfer tidak perlu baris/metode lawan atau selisih simetris; rekening lawan boleh tanpa metode POS. Dua rekening aktif berbeda/mata uang sama, saldo sumber, snapshot terbaru, periode, audit atomik dan larangan posting ulang tetap diperiksa.
- [x] Satu baris menyimpan kedua mutasi transfer; tidak menutup baris lain. Atribusi metode lawan hanya opsional untuk koreksi salah metode, dengan pembacaan kompatibel pasangan lama tanpa hitung ganda. Total uang tidak bertambah; saldo tujuan negatif tidak dinormalisasi diam-diam.
- [x] Sembilan kategori IN/OUT sama dengan Mutasi Kas dan Rekon Kas. Selisih biasa tidak dipaksa memakai rekap settlement; promo/platform tetap memakai rincian/bukti/persetujuan sesuai kebijakan bersama. Kategori, referensi biaya dan settlement tidak dibawa ke transfer/NONE.
- [x] 175 tes sintetis SQLite in-memory; regresi 72/27/35/28, PHP lint empat file dan JS hasil render PASS. Tidak memperbaiki data transaksi asli.
- [x] Pengguna mengonfirmasi SQL `2026-09-14c` sudah dijalankan lewat terminal pada staging `db_finance` (konfirmasi diterima 2026-09-14). Status **USER_REPORTED_APPLIED**, bukan bukti verifikasi otomatis. Tidak ada SQL tambahan Batch 254.
- [ ] Verifikasi hasil schema/ledger MariaDB, browser dan UAT tetap terbuka karena koneksi DB sesi ini ditolak sandbox. Jangan mengulang migrasi hanya karena pemeriksaan belum tersedia. Laporan modul: [alur dan validasi terkini](2026-09-14_finance_alokasi_bank_rekonsiliasi.md).

**Delta Batch 253 — 2026-09-14 (Kontrol Keuangan + regresi rekonsiliasi; kode siap uji IDE, SQL belum aktif):**

- [x] Perbaikan Rekon Pendapatan: sisa selisih dihitung ulang setelah posting; nilai saat posting tetap riwayat terpisah. Rekon Kas menolak posting jika saldo/selisih berubah setelah disimpan. Kategori IN/OUT sesuai arah, koreksi saldo BOTH tetap tersedia, transfer kas berbeda mata uang ditolak.
- [x] Kode alokasi satu transfer ke beberapa rekap: batas nominal/rekening/tanggal, sisa belum dialokasikan, saldo konfirmasi lama dipertahankan, revisi/audit/VOID seluruh rekap terkait tanpa kas kedua kali.
- [x] Kode realisasi parsial rencana: batas total satu mutasi, kompatibel tautan lama, sisa/VOID/riwayat per bagian; bukan realisasi ulang POS/purchase/payroll.
- [x] Kode CSV rekening koran: pratinjau terikat rekening/pemetaan, konfirmasi impor, deduplikasi identik, pencocokan manual satu-ke-satu, audit lepas dan status stale setelah mutasi berubah. Tidak memposting kas otomatis.
- [x] Implementasi awal transfer koreksi metode; **batas wajib pasangan dan penutupan dua baris digantikan Batch 254 di atas**. Gunakan alur transfer rekening bebas dengan atribusi metode opsional pada laporan modul terkini, bukan aturan awal Batch 253.
- [x] 108 tes sintetis in-memory, regresi 72/27/35/28, PHP lint 20 file dan sintaks JS PASS. Test masuk required gate `finance-allocation-bank`.
- [x] Status historis Batch 253: SQL baru disiapkan. **Pembaruan 2026-09-14: pengguna sudah menjalankannya di staging**, lihat Batch 254/register. Validasi MariaDB/konkurensi, registrasi migrasi, browser dan UAT belum dinyatakan selesai.

Laporan dan langkah pengguna: [finance_alokasi_bank_rekonsiliasi](2026-09-14_finance_alokasi_bank_rekonsiliasi.md). Keterbatasan satu-transfer/satu-rencana pada Batch 249 tetap berlaku **sebelum SQL baru diaktifkan**; tidak mengklaim delta ini selesai operasional.

**Delta regresi Batch 252 — 2026-09-14 (A1, void/refund produk tanpa resep):**

- [x] Reproduksi kode: konfirmasi web/mobile memberi status stok `NOT_REQUIRED` tanpa membuat snapshot, tetapi preview void/refund menolak snapshot kosong. Tes sintetis gagal sebelum patch dan lulus setelah patch.
- [x] Model bersama mengizinkan plan stok kosong hanya untuk status tersimpan `NOT_REQUIRED` ketika snapshot memang tidak ada. Produk event tetap masuk pemilihan pembatalan/refund; tidak membuat snapshot, lot, pengembalian stok, atau adjustment palsu.
- [x] Snapshot yang sudah ada tetap diproses, termasuk order campuran/append event dengan header `NOT_REQUIRED`. Snapshot hilang pada `POSTED`/`FAILED`/status lain tetap ditolak. Batas uang refund, qty tersisa, lock transaksi, CSRF/RBAC/step-up tidak diubah.
- [x] 45 pemeriksaan baru DB-free lulus, ditambahkan ke required gate A1; suite reversal, availability, step-up web/mobile, CSRF transaksi dan mobile financial writer juga lulus. Tidak ada SQL, akses data transaksi, perubahan APK/bridge Telegram, atau deploy.
- [ ] UAT pengguna: void order event belum dibayar; refund penuh/sebagian order event sudah dibayar; order campuran event + produk stok. Gunakan transaksi uji yang disiapkan pengguna. Tes batch ini tidak memposting pembatalan/refund pada DB aplikasi.

**Delta Batch 249 — 2026-09-14 (lanjutan Kontrol Keuangan, urutan 1 → 2 → 4 → 3 → 5):**

- [x] Pencairan per transfer: tanggal, referensi unik per rekening, nominal, bukti, riwayat VOID, retry/revisi/audit. Rekap baru mulai nol; konfirmasi lama dipertahankan sebagai saldo konfirmasi awal saat rincian mulai digunakan. Tidak menambah saldo bank kedua kali.
- [x] Identitas biaya memakai nomor dokumen + baris biaya per rekening. Beberapa biaya satu kategori diperbolehkan; satu identitas efektif tidak boleh diposting ulang lewat Mutasi/Rekon Kas/Rekon Pendapatan. Mutasi lama tanpa identitas harus ditautkan eksplisit, bukan ditebak atau diposting ulang.
- [x] Pencarian seluruh settlement, termasuk melewati batas lama 200, dengan filter tanggal/teks dan pagination 25. Pilihan settlement/biaya lama tetap tersimpan di formulir rekonsiliasi; tidak membuat rekap duplikat.
- [x] Rencana kas → mutasi manual aktual → sisa proyeksi. Satu mutasi utuh untuk satu rencana; taut/lepas tidak mengubah kas. VOID mengembalikan sisa otomatis, over-realisasi ditampilkan. POS/tagihan/payroll otomatis tidak boleh ditautkan sebagai realisasi manual kedua kali. Tanggal proyeksi payroll dapat diatur, tanpa mengubah perhitungan gaji/uang makan.
- [x] Bukti PDF/JPG/PNG privat di luar webroot, maksimal 5 MB, download berizin/attachment, verifikasi isi/hash dan CSRF. Kompatibel dengan PHP tanpa Fileinfo. Pengaturan persetujuan dan bukti **default tidak wajib**; hak kebijakan/pemeriksa terpisah. Persetujuan posting/VOID tertaut mengikat nominal, rekening, rincian, bukti, revisi settlement dan kebijakan; pembuat/pengaju tidak boleh menyetujui sendiri, perubahan mengharuskan pengajuan ulang.
- [x] SQL `2026-09-14b` applied + pemeriksaan ulang ledger di staging saja. Enam tabel metadata, kolom tautan/konfirmasi, dua page izin dan default kebijakan. Folder bukti privat staging serta allowlist `open_basedir` disiapkan; tidak memperbaiki/menghapus transaksi, upload, backup atau credential lama.
- [ ] UAT owner dengan bukti nyata: rincian pencairan parsial → biaya terpisah → posting → rencana/realisasi → koreksi/VOID. Identitas dokumen yang benar tetap tanggung jawab operator; ini bukan pencocokan semantik atau impor otomatis platform. Laporan tetap indikatif/manajemen, bukan akrual lengkap.

Batch 249 menggantikan batas **satu kategori/200 terbaru/H+1 tetap** pada catatan Batch 248 di bawah; fase lama tidak dibuka ulang. Panduan dan validasi ada pada [laporan Kontrol Keuangan](2026-09-14_finance_control_workspace.md), execution log Batch 249. Handoff paket tetap hanya di `_28`.

**Delta Batch 248 — 2026-09-14 (A2 kontrol finance + A3 penelusuran, permintaan 1–5):**

- [x] `/finance-reports/control`: satu halaman bertab untuk settlement, kualitas laporan, proyeksi kas, dan laba-rugi manajemen HPP. Sidebar Keuangan dan page `finance.control.index`; grant awal hanya SUPERADMIN, izin staf tetap diatur pemilik.
- [x] Rekap settlement per tanggal pembayaran/metode: sumber POS + refund, promo yang sudah masuk POS, biaya tertaut, penerimaan kumulatif, status sebagian/lengkap, dan pemeriksaan fingerprint/revisi. Simpan konfirmasi **tidak memposting kas lagi**.
- [x] Referensi settlement bersama pada Mutasi Rekening, Rekon Kas, Rekon Pendapatan. Promo/fee baru wajib referensi; kategori efektif yang sama tidak boleh diposting dua kali. Ronde pendapatan memperhitungkan penyesuaian tertaut dari modul lain. Settlement sebagian tertaut tidak boleh diubah menjadi biaya rekonsiliasi. VOID melepaskan klaim kategori; pasangan VOID tetap tidak dihitung.
- [x] Kualitas laporan menampilkan mutasi belum berkategori, cakupan settlement non-tunai yang belum ditinjau, pending/selisih/sumber berubah, saldo vs ledger, dan audit HPP. Tidak memperbaiki data otomatis.
- [x] Proyeksi 7/30 hari: saldo buku dikurangi pending settlement terlacak, lalu jadwal pencairan ditambah sekali; hutang, piutang, payroll final belum dibayar, dan rencana manual dengan audit/revisi/retry key. Komitmen dan perkiraan dipisahkan, rekening non-IDR dikecualikan.
- [x] Laba-rugi **manajemen transaksi lunas**, terpisah dari estimasi kas lama: snapshot HPP + extra, refund berdasarkan tanggal refund, koreksi/reversal HPP berdasarkan tanggal pengakuan, pajak penjualan dipisahkan, pendapatan/biaya berkategori, payroll final. Pembelian stok dan pencairan payroll tidak dipotong lagi; kasbon bukan pengurang beban gaji.
- [x] SQL `2026-09-14a` diterapkan/replay di staging; dua tabel metadata, tiga kolom tautan nullable, sidebar/RBAC dan ledger migration. Tidak backfill, tidak membaca transaksi staging untuk fixture, tidak menjalankan SQL lain.
- [ ] UAT pengguna: cocokkan satu rekap platform nyata dan kategori historis, serta tanggal pencairan/payroll yang sesungguhnya. Referensi biaya historis tidak diinferensikan dari catatan bebas; guard bukan detektor semantik jika kejadian sama diberi kategori/referensi berbeda.
- [ ] Batas laporan: proyeksi bukan saldo bank terverifikasi, cakupan settlement perlu dilengkapi; satu kontrol adalah rekap harian/metode (bukan impor otomatis API platform). Laba-rugi belum akrual lengkap, depresiasi/pajak penghasilan/biaya belum dicatat belum tercakup. Beban payroll rentang parsial tidak diprorata otomatis.

Detail penggunaan dan bukti: [laporan modul Kontrol Keuangan](2026-09-14_finance_control_workspace.md), execution log Batch 248. Distribusi/profil customer hanya dicatat pada `_28`; APK dan Control tidak diubah.

**Delta laporan Batch 247 — 2026-09-13 (A2 finance + A3 keterlacakan):**

- [x] Mutasi pendapatan lain/biaya operasional, promo, platform, dan selisih kas terverifikasi ikut estimasi melalui kategori eksplisit. Modal, prive, transfer, dan koreksi saldo saja dipisahkan dari hasil operasional.
- [x] Estimasi harian dan metrik profit global memakai satu aturan mutasi, termasuk pengecualian pasangan VOID. Basis gaji tidak diubah: harian dari absensi, global dari payroll tergenerate bila tersedia.
- [x] UI estimasi menampilkan komposisi, peringatan data belum diklasifikasikan, panduan settlement, dan tautan mutasi. Mutasi lama dapat diklasifikasikan dengan alasan/audit tanpa mengubah saldo, nominal, atau tanggal; periode CLOSED dan mutasi VOID ditolak.
- [x] Rekonsiliasi kas/pendapatan menyimpan kategori; posting tanpa kategori ditolak. Rekonsiliasi pendapatan ronde berikutnya hanya memposting sisa selisih, menolak draf stale dan penimpaan baris POSTED. Mutasi manual IN/OUT memakai identitas permintaan untuk mencegah retry menggandakan saldo.
- [x] Migrasi sempit `2026-09-13a` diterapkan di staging, tanpa backfill atau perubahan data transaksi. SQL/kode baru masuk katalog dan allowlist Finance; tidak menjalankan SQL Roast Connect atau menyentuh Control/APK.
- [ ] User meninjau kategori mutasi historis dan memverifikasi contoh settlement nyata. Belum diklasifikasikan: IN belum menambah estimasi, OUT tetap pengurang sementara dengan peringatan. Tidak menebak catatan bebas, mengubah snapshot CLOSED, atau menjanjikan otomatis mendeteksi duplikasi lintas modul.
- [ ] Estimasi tetap berbasis arus kas pembelian + estimasi gaji, **bukan laba-rugi akrual berbasis HPP**. Penyusunan laba-rugi akuntansi penuh merupakan pekerjaan terpisah.

Bukti uji dan batas rilis dicatat di execution log Batch 247; status distribusi/Control tetap di `_28`, tidak mengulang fase yang sudah selesai.

**Delta bug/UI Batch 246 — 2026-09-13 (A2 procurement + A3 mobile):**

- [x] Tujuan Store Request Roastery kosong: opsi `ROASTERY` dan `ROASTERY_EVENT` sekarang tersedia dari model bersama, sesuai guard divisi yang sudah ada. Form halaman/modal dan PO/SR Divisi memakai daftar yang sama; tidak membuka tujuan divisi lain.
- [x] Detail transaksi POS `/pos/reports/sales-detail/{id}`: tombol Invoice, Kwitansi dan navigasi membungkus pada layar sempit; mobile memakai dua kolom, target sentuh 44px. Identitas/catatan panjang tidak lagi memaksa halaman melebar.
- [x] Purchase Order: **Per Nota, Per Rincian, Per Tgl Paid** memakai kartu berlabel di ponsel; desktop tetap tabel, tablet dapat menggeser tabel. Nilai/status tidak dipotong, kontrol status/aksi tidak diduplikasi dan izin/CSRF tetap.
- [x] 48 tes regresi data sintetis + 55 kontrak Purchase/SR + 20 layout browser (360/390/600/768/1280px) lulus. Tes PHP baru masuk required quality gate; tidak membaca/menulis DB transaksi.
- [ ] UAT pengguna: coba pilih Roastery Reguler/Event pada SR, simpan permintaan yang memang diperlukan, lalu cek ketiga tab PO dan tombol invoice di perangkat sendiri. Simpan transaksi aktual tidak dilakukan oleh tes ini.
- Tidak ada SQL baru, perubahan APK/Control/Roast Connect, atau pengulangan fase yang sudah ditutup. Bukti dan batas validasi: execution log Batch 246; status komersialisasi tetap di `_28`.

**Delta regresi Batch 245 — 2026-09-12:**

- [x] Perbedaan outer manifest Control vs Finance ditangani: `filename`/`artifact` dan hash app-manifest/inner manifest tidak tertukar pada verifier/installer.
- [x] Signature, profile/hash, kelengkapan gate dan batas instalasi database kosong tetap diperiksa; format signed historis tidak diputus.
- [x] Build unprivileged memiliki cache Composer/PHPStan privat dan database uji socket-only; tidak membuka credential/database aplikasi yang aktif.
- [x] Gate keamanan dengan snapshot OSV baru menemukan `sharp@0.35.3` (GHSA-rgj7-g3m4-5g8c). Lock paket WA dipatch ke 0.35.4; scan 145 paket kini nol advisory, uji gambar sintetis memakai libheif 1.23.2 PASS. Tidak me-restart WA atau mengganti node_modules/sesi layanan aktif. [Advisory pengembang](https://github.com/lovell/sharp/security/advisories/GHSA-rgj7-g3m4-5g8c).
- Checklist komersialisasi/profile/adapter dan sisa guard Control tetap di `_28` bagian C3, tidak menggandakan fase audit. Tidak ada perubahan POS/APK atau perbaikan data bisnis dalam batch ini.

**Delta regresi Batch 244 — 2026-09-10:**

- [x] Menu Book tidak memanggil view legacy yang absen pada paket customer; setting legacy tetap bekerja bila aset/desain tersedia di staging.
- [x] Fallback logo/favikon/label dan default printer tidak menunjuk aset lama yang tidak ikut distribusi. Tidak mengganti logo/upload asli atau nilai pengaturan staging.
- [x] Klaim paket bersih kini diperiksa dari allowlist dan checksum TAR, bukan boolean konstan; file titipan/SQL berubah/report palsu ditolak.
- [ ] UAT UI/printer pada instance customer aktual sesudah integrasi thread Control tetap diperlukan.

Checklist distribusi/clean-install dan sinkronisasi Control hanya di `_28` bagian C2/C3 serta `docs/customer_clean_release_contract.md`. Tidak ada perbaikan data stok, perubahan RBAC, atau SQL baru pada batch ini.

**Validasi penuntasan Batch 243 — 2026-09-09:** source cutoff `15f9f62`
lulus **113 entry release gate dari checkout bersih**, tanpa mengurangi gate
atau baseline. Pemasangan dan upgrade dari paket alpha.10 signed masing-masing
lulus health dan 22 tes HTTPS UI. Upgrade kode alpha.9→10 mempertahankan
296 checksum tabel dan file logo; web/DB lama dapat dikembalikan, database lama
tetap utuh saat versi baru diuji. Bukan test SQL bisnis baru atau seluruh UAT.

- [x] Pemindai credential tetap aktif; generator fixture memakai identitas acak.
- [x] Gate token/lisensi, recovery, receipt, PID/backup/upload dibuktikan melalui
  aplikasi Control/Finance terisolasi. Tidak memakai atau memperbaiki data transaksi.
- [ ] Native guard/enforcement, UAT peran, APK dan printer fisik tetap terpisah;
  tidak dicentang hanya karena alat deployment lulus.

Status praktik penjualan hanya di `_28`; panduan UI ada di Control
`/finance/practice`. Tidak ada SQL baru pada Finance atau Control untuk batch ini.

**Delta Batch 242 — 2026-09-09:** recovery koneksi aktivasi mempertahankan
identitas/kunci, perpanjangan setelah offline tidak tersangkut RESTRICTED,
dan receipt migrasi tidak boleh menandai seluruh deployment selesai. Bukti
HTTPS Control terisolasi/installer berada pada `_28` dan execution log.
Gate release 113 entry PASS di workspace; tes baru tidak membaca DB transaksi.
Source alpha.10 menyiapkan executor/root runtime terpisah, backup wajib untuk
upgrade, penjagaan PID dan preservasi upload. Verifikasi kandidat signed berikut
dicatat terpisah; tidak mengubah RBAC/enforcement, A3, bug APK atau data bisnis.

**Validasi Batch 241:** source cutoff `4d31548` lulus **112 entry quality gate
release** dari checkout bersih; 54 tes agen/model/file diulang PASS. UAT peran,
APK dan printer fisik tetap pending, probe DB bisnis tidak dijalankan. Tidak
ada pengurangan gate/baseline, SQL baru atau koreksi transaksi. Bukti paket
dan praktik penjualan hanya pada `_28` dan execution log.

**Delta teknis Batch 240 — 2026-09-09:** reader lisensi kini dapat memakai
cache deployment root-owned yang ditulis atomik, bukan mempercayai flag SQL.

- [x] Signature/binding dan watermark diperiksa kembali oleh model aplikasi;
  cache rusak/tidak aman tidak diam-diam memakai tabel SQL sebagai otoritas.
- [x] Replay lease lintas restart, jam mundur, revoke, koneksi gagal, izin akun
  web dan isolasi private key diuji dengan fixture tanpa DB transaksi.
- [x] 54 tes agen/model/izin dan 26 tes verifier PASS; PHPStan application
  baseline nol PASS. FeatureGate tetap audit-only; izin kasir tidak diubah.
- [ ] Anti-clone/native guard, pemulihan seluruh snapshot oleh root, dan
  enforcement end-to-end bukan klaim kelulusan tes ini.

Aktivasi/paket/praktik penjualan dan checklist C4 hanya pada `_28`. Tidak ada
SQL baru, perbaikan data bisnis, pengulangan A3 atau intervensi bug APK.

**Final Batch 239:** gate release dari checkout bersih lulus **111 entry**,
tanpa menyalin `.env` backup atau marker dashboard staging. Paket alpha.8
terverifikasi, web dari paket lulus 22 pemeriksaan HTTPS dan health upgrade
setelah UI save. Ini melengkapi bukti batch di bawah, bukan menutup seluruh
A5/handoff atau UAT peran/printer. Detail delivery hanya di `_28`.

**Update 2026-09-09 — Batch 236–238:** perbaikan fondasi instalasi customer
dan bug login yang ditemukan lewat pengujian HTTPS. Tidak mengulang A3,
mengoreksi data transaksi, atau mengerjakan bug operasional APK.

- [x] A1: login tanpa header User-Agent tidak lagi gagal saat penulisan audit
  karena NULL masuk parameter string. Audit tetap wajib; 74 tes login lulus.
- [x] A5: URL, cookie, session, cache dan log dapat dipisahkan per instalasi
  lewat JSON privat root-owned; konfigurasi staging lama tetap opt-in.
- [x] A5: `.user.ini` berisi path staging tidak ikut paket customer baru.
  File asli staging tidak dihapus atau diedit.
- [x] A5: health-check upgrade menerima receipt seed clean-install yang sah,
  memverifikasi checksum dan tetap menolak metadata/ledger asing atau drift.
- [x] HTTPS percobaan Linux: 22 pemeriksaan login/profil/logo/CSRF/URL/akses
  internal/session lulus. Ini bukan UAT seluruh modul atau pilot publik.
- [x] Backup dipulihkan ke dua DB kosong percobaan: 296 tabel sama checksum;
  upgrade katalog sama melewati 15 migrasi, mempertahankan 16 receipt dan
  profil sintetis customer. Tidak ada seed ulang/owner baru/DB lama dihapus.
- [ ] Upgrade lintas versi dengan migrasi baru, switch/rollback layanan web
  customer, Windows, UAT kasir/printer dan persetujuan handoff belum selesai.

Scope artefak/delivery dan C0–C5 tetap hanya pada `_28`; bukti detail di log
eksekusi. Perubahan batch ini **tidak memerlukan SQL baru** di aplikasi lama.

**Update 2026-09-09 — Batch 232–235:** clean-install database dari paket
telah **PASS** pada DB disposable; belum menutup A5/handoff web/customer.
Tiga bug yang baru terbukti saat menjalankan instalasi sudah diperbaiki:
indeks ganda baseline vs migration, hitungan menu lama, dan health checker
yang keliru mewajibkan hak mutasi pada panduan statis. Detail delivery di `_28`.

- [x] Baseline tidak membuat dua indeks yang menjadi tanggung jawab migrasi
  06f/06g. SQL managed/ledger upgrade tidak diubah; hasil akhir tetap berindeks.
- [x] Policy first-owner/health menghitung lima grup sidebar baru: 249 menu,
  209 halaman dan 209 permission; tidak mengubah isi izin customer.
- [x] Health menerima matriks view-only yang memang ditetapkan SQL `tg.guide`;
  view hilang, hak berlebih pada guide, dan hak kurang pada halaman lain ditolak.
- [x] Instalasi aktual: 296 tabel, 16 migrasi, satu owner, seed exact dan health
  PASS. Password owner, indeks, tujuh kasus permission SQL, dan guard rerun teruji.
- [ ] Deployment web lengkap, upgrade/rollback disposable, UAT peran/printer,
  dan handoff tetap terbuka. Bug operasional/build APK masih ditunda owner.

Bug pemeriksaan runtime A5 juga diperbaiki:
versi klien MariaDB tidak lagi dianggap bukti versi server. Probe CLI melaporkan
`mariadb_client` secara terpisah dan memperingatkan kewajiban cek server saat
install. Executor mencocokkan hasil `SELECT VERSION()` dengan kontrak signed
paket sebelum DDL. Keputusan runtime/delivery hanya di `_28`.

- [x] Tes negatif: server di luar minor signed, MySQL, respons rusak, dan
  kontrak luas yang belum disetujui ditolak; paket lama tidak dilonggarkan.
- [x] Baseline/migration/first-owner/health terbukti pada DB percobaan kosong.
- [ ] Upgrade/rollback disposable dan UAT web/peran/printer tetap terbuka.

**Riwayat Batch 229–231:** A4 cold-cache diperbaiki tanpa mengurangi
scope/error gate. Bug tambahan A5: bootstrap owner masih memakai jumlah
halaman/menu lama (206/241), berbeda dari policy rilis (209/244). Kini memakai
policy baseline tervalidasi; unit contract PASS. Trial executor DB berhenti
sebelum membuat tabel: staging aktual MariaDB 10.11.10, paket alpha.3 membatasi
10.6. Bukti runtime lama 10.6.23 bukan bukti versi server saat ini.
Detail DRAFT/delivery dan keputusan runtime kandidat berikutnya hanya di `_28`.

- [x] A4: gate cold-cache 360s/outer 420s, cache per checkout; analisis penuh
  application lulus dengan baseline/errors nol.
- [x] A5 CODE_PASS: hitungan first-owner mengikuti release policy, bukan literal.
- [x] A5 runtime: install DB/first owner/health lulus Batch 235; upgrade/rollback
  dan UAT web tetap terpisah, belum dinyatakan lulus.
- [x] Kode tooling masuk alpha.7; alpha.3 tetap immutable.

**Delta 2026-09-09 — Batch 228:** izin owner membedakan pekerjaan
komersialisasi APK (boleh) dari bug operasional/build APK (ditunda).
Tidak mengulang A3 atau memperbaiki transaksi. Batas PHP manifest dikoreksi
dari klaim sampai 8.4 menjadi PHP 8.1 sesuai runtime yang benar-benar diuji.
Cutoff Git terseleksi `b10fa37` selesai, tanpa merge/push. Quality gate release
lulus 108 entry; paket dari checkout bersih telah ditandatangani dan diverifikasi
Control sebagai kandidat internal. Cold-start PHPStan melampaui timeout 150 detik;
analisis penuh terpisah lulus nol error, lalu semua gate build lulus. Penataan
budget/cache cold-start tetap tindak lanjut A4/A5. Status akhir/hash ada di log.
Integrasi Git remote,
UAT, dan handoff produksi tetap belum selesai. Detail delivery hanya di `_28`.

**Riwayat 2026-09-08 — Batch 224–227:** APK ditunda atas arahan owner;
bug/build/UAT APK tetap terbuka, tidak dianggap selesai. Perbaikan baru
non-APK: batas paket rilis kini wajib mengecualikan `assets/uploads/`,
pemeriksaan delapan folder upload memakai policy bersama UI/installer,
hook Composer aman saat `--no-dev`, serta entitlement harus dibuktikan dari
tanda tangan Control, bukan flag/tabel fitur lokal. Desain/template customer
dan kelanjutan installer/lisensi dicatat hanya pada C2–C4 di `_28`.
Tidak ada SQL, pembacaan/koreksi data transaksi, commit/push, rotasi secret,
atau deployment ke server utama pada batch ini.

Checklist delta teknis (tidak mengulang A3 inventory/sidebar):

- [x] A0/A5: cegah logo/upload customer masuk artefak meskipun tidak sengaja tracked.
- [x] A5: pisahkan langkah clean-install dengan upgrade; upgrade tidak memuat seed demo atau bootstrap owner.
- [x] A5: policy folder upload bersama; delapan folder staging READY sebagai akun `www`.
- [x] A5: ganti hook `sed` Composer menjadi PHP portabel dengan no-op saat paket development tidak ada.
- [x] A1→C4: flag VERIFIED/feature_cache lokal tidak cukup untuk memberikan entitlement.
- [x] A0/A5: cutoff source lokal `b10fa37a40a06b1867800327812ac1dc1490c176`, tag `finance-web-alpha.3-cutoff-20260909`; catatan lokal/upload dipertahankan di luar commit.
- [x] A0/A5: paket kandidat internal signed dari cutoff bersih lulus verifikasi Control (Batch 228); bukan publish.
- [ ] A0/A5: integrasi Git remote, restore/upgrade/rollback customer nyata dan persetujuan handoff belum selesai.
- [x] A4/A5 kode Batch 229: cold-start dan isolasi cache diperbaiki dan sudah masuk alpha.7; alpha.3 tidak ditimpa.
- [ ] A1/A4: penerimaan per peran dan printer fisik; bug operasional/build/UAT APK serta MFA ditunda owner, pekerjaan komersialisasi APK diperbolehkan.

Laporan batch: `docs/2026-09-02_codex_execution_log.md`.

**Validasi Batch 227:** quality gate profile release lulus 107 entry (97
required + 4 development + 1 release-config + 2 runtime + preflight/security/static).
PHPStan seluruh application lulus dengan baseline nol; OSV terbaru memeriksa
145 package tanpa advisory. Pemeriksaan data transaksi tidak dijalankan.
Paket tetap tidak boleh diterbitkan dari worktree dirty; UAT perangkat dan
persetujuan handoff tidak digantikan oleh kelulusan tes otomatis.

**Tanggal audit awal:** 2026-08-30

**Pembaruan menyeluruh:** 2026-09-01

**Pembaruan status eksekusi:** 2026-09-06. Batch 196–199 menyelesaikan rollout
kode `A3-UI-04` pada daftar Reservasi, Order Aktif Kasir, Self Order, dan
Online Food web: state memuat/kosong/gagal dengan retry, reset filter yang
jelas, navigasi aksesibel, serta pembatalan request lama. Ia hanya membaca
data; tidak menyentuh writer, DP, stok, pembayaran, route, ataupun kontrak POS
Mobile/APK. UAT visual browser tetap diperlukan. Batch 191 sebelumnya menutup reauth one-use khusus
pengembalian DP saat penolakan atau pembatalan reservasi POS web; Batch 190
menutup jalur penolakan refund DP yang sama pada POS Mobile/APK.
Batch 200 melanjutkan `A3-UI-05` pada Stok Komponen: pagination yang sebelumnya
hanya menyembunyikan baris pada browser kini dibaca per halaman dari server,
sementara KPI tetap merepresentasikan seluruh hasil filter. Tidak ada koreksi
stok, lot, HPP, writer, atau data yang diubah.
Batch 201 menyamakan kontrak daftar Stok Gudang, Stok Bahan Baku, dan Stok
Komponen: pilihan 25/50/100/200 baris, jumlah/ringkasan dari hasil filter,
navigasi halaman yang eksplisit, serta empty state aksesibel. Struktur kolom
tetap sesuai domain masing-masing; tidak ada saldo, lot, HPP, atau writer yang
diubah.
Batch 202 menormalkan arti periode: Stok Gudang dan Bahan Baku Live kini
memakai **Bulan Snapshot** tunggal seperti Stok Komponen. Daily Matrix tetap
menyediakan rentang hari sebagai tampilan opsional, tetapi kode frontend,
controller, dan model membatasinya agar tidak pernah keluar dari bulan aktif.
Tidak ada data transaksi atau saldo yang dibaca/diperbaiki secara manual.
Batch 203 menyatukan shell card ringkasan pada ketiga daftar stok live. Nilai
dan metriknya tetap spesifik domain, tetapi struktur responsif, label, tone
perhatian, serta aksesibilitas kini berasal dari satu partial view.
Batch 204 menutup standar visual yang sebelumnya masih implisit pada
`A3-UI-05`: pada ketiga daftar stok live urutannya wajib Header/Tab → Filter →
Ringkasan hasil filter → Tabel/Pagination. Batch 205 menyempurnakannya setelah
review UI: card memakai satu desain grafis berwarna dan berikon, dengan urutan
warna yang konsisten; merah tetap khusus untuk alert aktif. Tab Gudang, Bahan
Baku, dan Component kini memakai shell responsif yang sama pada seluruh
halaman yang memanggil tab tersebut. Perbedaan kolom dan metrik bisnis tetap
dipertahankan.
Batch 206 memperluas card grafis yang sama ke tiga tab snapshot bulanan:
Gudang, Bahan Baku, dan Component. Component Bulanan kini juga mengikuti
urutan filter → ringkasan → tabel; tidak ada pembacaan atau perubahan data
operasional manual.
Batch 207 menyelaraskan tiga Daily Matrix: Gudang dan Bahan Baku mempertahankan
kontrak AJAX-nya tetapi memakai semantik warna/ikon KPI yang sama; Component
memakai partial kartu bersama setelah filter. Merah pada kartu alert kini hanya
aktif saat hasil matrix memang memiliki minus/habis.
Batch 208 melanjutkan penyeragaman ke tab operasional read-only: Mutasi Gudang,
Audit FIFO, Opname Bahan Baku, Audit Lot, Mutasi Component, dan Lot Component.
Semua ringkasan diposisikan setelah filter dan hanya meneruskan hasil pembacaan
yang telah ada.
Batch 209 melengkapi navigasi workspace A3 pada Finance, Purchase Report,
Attendance, Payroll/Bonus, Asset, Access Audit, Loyalty, dan Master Extra:
halaman yang berpindah URL kini memakai link responsif bersama dengan active
state, fokus keyboard, serta `aria-current`; in-page tab Bootstrap tetap tidak
diubah. CSS ringkasan lokal yang telah tergantikan pada Lot/Mutasi Component
dan Audit Lot dibersihkan. Tidak ada route, writer, permission, data, SQL, atau
kontrak POS Mobile/APK yang diubah.
Batch 188 menutup baseline PHPStan menjadi nol finding dan guard mutasi Landing Page.
Batch 186 menutup **Tutup Kasir POS Mobile/APK** dengan reauth password dan
proof satu-kali yang terikat sesi kasir (bukan order). Batch 187 menambahkan regression boundary untuk
reservasi, self-order, dan online-food POS Mobile. Batch 177 sebelumnya menutup
cetak ulang order POS Mobile/APK dengan proof terpisah dari reversal; Batch 176
menutup Void dan Refund POS Mobile/APK dengan reauth password dan proof satu-kali yang
terikat token, user, terminal, aksi, serta order. Proof disimpan hanya sebagai
hash, kadaluarsa dalam 180 detik, dikonsumsi atomik sebelum writer, dan gagal
tertutup saat schema belum siap; staging migration applied 1 lalu replay 0.
Batch 175 menambahkan
pemulihan versi Formula Component yang dilindungi reauth proof satu-kali,
revision lock, riwayat `RESTORE`, dan audit before/after atomik. Batch 174
memensiunkan seluruh writer Formula Component per-baris (jalur Master legacy
dan endpoint Production lama). Semua perubahan formula kini melalui editor
bulk kanonis atau restore versi terotorisasi; bookmark lama tetap dialihkan
dengan aman dan endpoint API lama memberi respons `410 Gone` tanpa mutasi.
Batch 173 menambahkan snapshot baseline lama dan snapshot penggantian baru
yang ditulis atomik bersama perubahan formula; migrasi sudah applied dan
replay di staging.
Batch 172
menutup writer Bundle Produk dengan snapshot revision, lock header/line, dan
audit atomik untuk tambah, ganti isi, serta ubah status. Batch 171 menambahkan audit
before/after atomik pada tambah dan hapus mapping Product Extra di dalam
transaksi prepared-statement yang sudah memakai lock. Batch 170 mengunci
writer individual Resep Produk; Batch 169 mengunci editor massal Formula
Component; dan Batch 168 mengunci editor massal Resep Produk. Batch 167
menambahkan reauth password one-use
pada import Excel Stock Opening Divisi. Satu file kini terikat pada satu divisi
aktif yang dipilih dan baris lintas divisi ditolak sebelum writer. Bersama
Batch 166, seluruh writer Stock Opening Gudang/Divisi memakai CSRF dan reauth.
Batch 164 menambahkan CSRF scoped serta reauth password dengan
proof satu-kali pada Save Draft, Delete Draft, Post, dan VOID Transfer Stok
Divisi web. Transfer tidak lagi
dapat langsung diposting melalui auto_post; alur resminya adalah simpan
draft lalu verifikasi password untuk Post/VOID. Batch 163 menambahkan CSRF
scoped pada simpan hitungan fisik/konfirmasi serta reauth password untuk
posting adjustment otomatis Daily Recon Component. Batch 162 menambahkan
CSRF scoped dan verifikasi ulang password pada Save/Delete Draft serta
Post/VOID Component Batch Produksi web dan jalur Quick Batch Daily Component;
Quick Adjustment Daily Component kembali memakai token/proof yang sesuai.
Batch 161 menambahkan CSRF scoped dan verifikasi ulang password pada Post/VOID Adjustment Stok
Gudang dan Divisi web; Batch 160 menambahkan
verifikasi ulang password pada VOID Adjustment Base/Prepare web; Batch 159 menambahkan
verifikasi ulang password pada Posting Adjustment Base/Prepare web; Batch 158 menambahkan
verifikasi ulang password pada Cetak Ulang Order Kasir web; Batch 157 menambahkan
verifikasi ulang password pada Reopen Periode Keuangan web; Batch 156 menambahkan
verifikasi ulang satu-kali pada Void Kasir dan Refund Pesanan Terbayar web;
Batch 155 membuat reopen periode keuangan atomik dengan lock/transaksi; Batch 154 mengamankan tiga
writer Tutup Periode Keuangan dengan CSRF dan redirect lokal tetap; Batch 153 menutup CSRF empat
aksi admin ulasan/QR dan memperbaiki konfirmasi moderasi; Batch 152 mengamankan formulir
ulasan publik (anti-spam, privasi, dan transaksi member/ulasan); Batch 151 menambahkan simulator
akses dan report selisih permission read-only; Batch 150 menyamakan scope
multi-role web dan POS Mobile, Batch 149 menambahkan audit trail atomik untuk
mutasi Master, Batch 148 mengunci inventaris endpoint Master generik, dan Batch
147 menetapkan cutoff
Git lokal bertag, memisahkan credential staging dan 1.367 file runtime dari
source/index Git tanpa menghapus file fisik, serta membakukan matriks SQL
server existing dan customer baru.

**Sifat audit:** Pemeriksaan baca-saja terhadap source code, konfigurasi, route,
sidebar, RBAC, struktur database, kesehatan data aktif, writer transaksi,
artefak operasional, dan konsistensi antarmuka.

**Status dokumen:** Sumber utama audit bug, risiko, integritas transaksi, dan
kesiapan teknis aplikasi. Dokumen ini bukan roadmap paket, harga, lisensi, atau
penjualan.

**Dokumen terkait:** docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md
tetap menjadi pegangan konsep produk dan lisensi. Dokumen ini menjadi daftar
utang teknis dan urutan implementasi yang harus diselesaikan.

Dokumen ini menggantikan status temuan pada versi 30 Agustus yang sudah tidak
sesuai dengan kondisi sekarang. Angka snapshot audit tetap berasal dari
1 September 2026 dan dapat berubah setelah transaksi atau sinkronisasi database
server berikutnya. Status pekerjaan setelah snapshot dicatat pada checklist
di bawah dan pada `docs/2026-09-02_codex_execution_log.md`.

**Batas dengan dokumen komersialisasi:**

- Dokumen ini memiliki pekerjaan perbaikan aplikasi: security, RBAC, data,
  transaksi, HPP, navigasi, UI, test, dependency, schema, backup, dan release
  foundation.
- `docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md` memiliki
  keputusan produk: paket, harga, EULA, entitlement, License Hub, Product
  Control Center, aktivasi device, pilot, support, dan penjualan.
- Laporan tanggal lain, termasuk progress per modul dan execution log, hanya
  mencatat pekerjaan yang benar-benar dilakukan. Laporan tersebut tidak
  membuat roadmap ketiga dan tidak menggantikan dua dokumen induk.

## 0. Audit Control Board (Sumber Status Tunggal)

Bagian ini adalah satu-satunya sumber status pekerjaan teknis. Bagian 1–15
menjelaskan detail masalah, target arsitektur, dan alasan keputusan. Execution
log hanya menjadi bukti historis batch; keberhasilan satu batch tidak otomatis
menutup fase atau seluruh temuan induknya.

### 0.1 Cara membaca status

Status tidak lagi diringkas dengan satu tanda `[x]`. Setiap item mempunyai tiga
dimensi yang harus dibaca bersama:

- **IMPLEMENTATION:** `NOT_STARTED`, `IN_PROGRESS`, atau `CODE_PASS`.
- **VALIDATION:** `NONE`, `AUTO_PASS`, `STAGING_PASS`, atau `UAT_PASS`.
- **RELEASE-DATA:** `N/A`, `BLOCKED`, `PROD_READY`, `DEFERRED_OWNER`, atau
  `REPAIRED_VALIDATED`.

Item hanya `DONE` apabila solusi/acceptance selesai, bukti tersedia, semua
validasi yang relevan lulus, dan tidak ada blocker atau data yang masih
ditunda. Fase hanya `DONE` bila seluruh child wajibnya `DONE`. `CODE_PASS` atau
`STAGING_PASS` sendiri tidak boleh diterjemahkan sebagai siap produksi.

### 0.2 Status fase A0–A5

| Fase | Implementasi | Validasi tertinggi | Release/data | Status fase | Alasan/gerbang berikutnya |
| --- | --- | --- | --- | --- | --- |
| A0 — baseline/deployment | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `PARTIAL` | Credential DB dan runtime index/package sudah dipisahkan; clone Git kini full history dan ref recovery lokal untuk dua orphan dibuat. Branch lokal dan `origin/main` divergen (37/150 commit, remote berisi backup runtime), sehingga merge/push, rotasi secret, off-site encryption, dan cutover customer menunggu keputusan integrasi yang eksplisit. |
| A1 — security/RBAC/scope | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `PARTIAL` | Multi-role/scope web-mobile terbukti fail-closed di staging; web serta POS Mobile Void/Refund/Reprint/**Tutup Kasir**/**Refund DP Reservasi**, Reopen/Reprint web, Post/VOID Adjustment Base/Prepare, Adjustment Stok Gudang/Divisi, Component Batch Produksi/Daily Component, Daily Recon Component, Transfer Stok Divisi, serta seluruh writer Stock Opening telah memakai boundary CSRF/reauth pada aksi irreversible. Batch 191 menutup refund DP pada penolakan/pembatalan reservasi **web**; Batch 190 menutup penolakan refund DP **APK**. Baseline izin per jabatan, MFA, aksi mobile sensitif lain, dan UAT role/APK belum selesai. |
| A2 — integritas bisnis/data | `CODE_PASS` | `STAGING_PASS` | `DEFERRED_OWNER` | `OPERATIONAL_PENDING` | Gate kode/query lulus; mismatch historis milik owner dan UAT browser/APK belum `UAT_PASS`. |
| A3 — navigasi/UI | `CODE_PASS` | `STAGING_PASS` | `N/A` | `CODE_COMPLETE_UAT_PENDING` | Seluruh gelombang kode UI 8.3, registry, dan sidebar berbasis tugas telah ditutup melalui smoke/regression. UAT visual role desktop/mobile tetap pekerjaan operasional dan tidak diklaim otomatis. |
| A4 — quality evidence | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | `TOOLING_PASS` | Tooling selesai; gate staging penuh lulus. Release nyata tetap diblokir A0 dan UAT fisik. |
| A5 — schema/release foundation | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `PARTIAL` | A5.1–A5.16 dan disposition teknis SQL legacy lulus. Batch 225 menutup gap pengecualian upload logo pada paket, policy folder PHP-FPM, pemisahan plan install/upgrade, dan hook Composer no-dev. Database archive activation, recovery Git/A0, updater lintas versi customer, dan release customer nyata belum selesai. |

**Update Batch 212:** login POS Mobile kini memakai throttle akun+IP yang sama
dengan login web sebelum terminal atau token diproses. Sesi browser dibatasi 12
jam, ID sesi berotasi setiap lima menit, dan ID lama dihancurkan. MFA, baseline
izin per jabatan, aksi mobile bernilai tinggi lain, serta UAT perangkat tetap
merupakan sisa A1 yang membutuhkan batch/kebijakan terpisah.

### 0.3 Register temuan audit

| ID | Sumber | Prioritas/fase | Masalah | Solusi/acceptance | Implementasi | Validasi | Release/data | Bukti atau langkah berikutnya |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `AUD-A1-SEC-01` | P0-01 | P0 / A1 | Endpoint Master belum seluruhnya deny-by-default. | Semua writer/read sensitif memakai permission aksi, scope, method, CSRF, dan negative test. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 82, 84A–C, 91–92, 148–149: 12 endpoint/36 entity terkunci; page Component kanonis dan 35 page unik aktif terbukti; enam writer memakai audit before/after atomik dan redaksi credential. Negative role UAT masih terbuka. |
| `AUD-A1-SEC-02` | P0-02 | P0 / A1 | Writer resep, formula, extra, dan bundle belum seragam. | Seluruh writer mempunyai RBAC aksi, CSRF/POST, concurrency, audit, dan formula versioning. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 54–68 memberi guard dasar; Batch 168–172 menutup snapshot konflik, lock, dan audit writer prioritas. Batch 173 menambah riwayat Formula Component append-only; Batch 174 mengalihkan jalur Master legacy dan memensiunkan endpoint Production per-baris dengan `410` tanpa DML. Batch 175 menambah restore terotorisasi: proof reauth terikat versi, revision lock, snapshot `RESTORE`, dan audit before/after atomik. UAT dua-tab/restore serta kontrak reauth APK tetap terbuka. |
| `AUD-A1-POS-01` | P0-03 | P0 / A1 | Surface POS Mobile/APK belum seluruhnya terikat terminal/outlet. | Semua endpoint memakai bearer context otoritatif, izin aksi, step-up, dan UAT perangkat. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 73–81, 89a–f, 93, 105–107, 150: token memvalidasi ulang role/scope serta membawa konteks division/outlet/terminal. Batch 176 menutup Void/Refund APK; Batch 177 Reprint; Batch 186 Tutup Kasir dengan proof sesi kasir; Batch 190 menutup penolakan reservasi **hanya bila mengembalikan DP** dengan proof terikat reservasi. Bootstrap capability contract kini versi 3 agar APK tidak menebak endpoint/method proof. Batch 187 mengunci regresi outlet/POST/RBAC inbox Reservasi, Self Order, dan Online Food. Batch 215 menyelaraskan client APK: proof password sekali pakai kini dikirim untuk Void/Refund/Reprint/Tutup Kasir/refund DP, outbox offline tidak menahan order independen saat satu event diblokir, serta order offline yang telah diterima server memiliki antrean cetak Bluetooth lokal dengan pencegahan duplikasi. Password hanya di endpoint verify; proof hash satu-kali 180 detik dikonsumsi atomik sebelum aksi sensitif. Aksi mobile lain serta UAT APK/perangkat masih terbuka. |
| `AUD-A1-RBAC-01` | P0-04 | P0 / A1 | Multi-role dan scope operasional terlalu luas. | Baseline role, precedence multi-role, outlet/division scope, dan negative matrix nyata lulus. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 6/7/150: union izin dan scope fail-closed lulus. Batch 151: simulator role/user/scope dan report selisih izin tersedia; 22 akun staging cocok dengan resolver aktif. Isi baseline hak per jabatan menunggu owner; UAT tetap terbuka. |
| `AUD-A1-RBAC-02` | P0-05 | P0 / A1 | Penghapusan role dahulu memakai kolom relasi salah. | Relasi benar, transaksi aman, dan regression test lulus. | `CODE_PASS` | `AUTO_PASS` | `PROD_READY` | Batch 2A. |
| `AUD-A0-SEC-01` | P0-06 | P0 / A0 | Konfigurasi keamanan belum layak produksi. | External secret contract, cookie/session final, CSRF boundary, rotasi secret, MFA/step-up, dan startup fail-closed. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 146: DB staging pindah ke file privat luar source, production tetap resolver, web/DB dan preflight 0 finding lulus; rotasi secret, cookie/session final, dan MFA tetap terbuka. |
| `AUD-A0-REPO-01` | P0-07 | P0 / A0+A5 | Backup/repository/runtime data belum sepenuhnya terisolasi. | Recovery Git non-destruktif, storage privat, enkripsi/retention, dan restore berkala. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 146–147 melepas 1.367 payload runtime tanpa menghapus data dan menetapkan cutoff lokal bertag. Batch 187A mengambil full history dari origin dan membuat ref recovery lokal untuk dua orphan tanpa merge/reset/push. `origin/main` masih berisi rangkaian backup runtime dan divergen dari staging, sehingga cleanup/integrasi remote, temp pack, serta off-site/enkripsi terbuka. |
| `AUD-A5-MIG-01` | P0-08 | P0 / A5 | Deployment schema belum sepenuhnya deterministik. | Semua schema/seed customer masuk katalog berurutan, checksum, clean-install, upgrade, dan rollback. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 138/140/141/145: baseline, seed, bootstrap, rollback, serta disposition tujuh SQL legacy lulus; updater dari release customer nyata dan delivery tetap belum selesai. |
| `AUD-A1-PRINT-01` | P0-09 | P0 / A1+A5 | Printer Agent/service lokal belum mempunyai lifecycle produksi lengkap; penggantian logo struk dahulu hanya menerima URL bebas. | Pairing, auth, rotation, installer service, recovery, version compatibility, UAT fisik, serta upload logo UI yang tersimpan di aplikasi dan tidak memaksa agent mengambil URL luar. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 185 menutup upload logo aman. Batch 216 menambah pairing key per-agent dengan masa rotasi, kontrak protocol/versi, health status, log rotation, persist config atomik, restart terkontrol bila koneksi berubah, serta installer/uninstaller Windows Task Scheduler dan Linux systemd. UAT fisik, provisioning secret customer, dan installer rilis customer tetap terbuka. |
| `AUD-A2-DATA-01` | P1-01 | P1 / A2 | Component mismatch nilai historis. | Script koreksi/VOID/cache benar; data hanya direpair owner dengan preview dan before/after. | `CODE_PASS` | `STAGING_PASS` | `DEFERRED_OWNER` | Batch 69–72; repair data tidak dikerjakan otomatis. |
| `AUD-A2-DASH-01` | P1-02 | P1 / A2 | Dashboard dahulu menyembunyikan mismatch nilai. | Quantity dan value mismatch dibedakan, dijelaskan, dan diuji. | `CODE_PASS` | `AUTO_PASS` | `PROD_READY` | Batch 48 dan regression dashboard. |
| `AUD-A3-NAV-01` | P1-03 | P1 / A3.1 | Sidebar mempunyai dua sumber kebenaran. | Renderer hanya memakai registry database terotorisasi. | `CODE_PASS` | `STAGING_PASS` | `PROD_READY` | Batch 100–101. |
| `AUD-A3-NAV-02` | P1-04 | P1 / A3.1 | Favorite/menu dapat berbeda dari permission resolver. | Favorite, pin, reorder, dan menu memakai resolver yang sama serta fail-closed. | `CODE_PASS` | `STAGING_PASS` | `PROD_READY` | Batch 102. |
| `AUD-A3-NAV-03` | P1-05 | P1 / A3.1 | Duplikasi URL dan alias page implisit. | URL/alias/parent/sort/icon kanonis tanpa collision. | `CODE_PASS` | `STAGING_PASS` | `PROD_READY` | SQL A3 dijalankan dua kali; Batch 100 dan 108. |
| `AUD-A3-IA-01` | P1-06 | P1 / A3.2 | Struktur menu dahulu memisahkan POS, SDM/payroll, Menu Book, dan integrasi pada akar yang tidak mengikuti tugas user. | Sidebar memusatkan area kerja, hanya menata `sys_menu` (label/parent/urutan), tidak mengubah route atau izin, tidak menampilkan grup kosong, dan lulus UAT. | `CODE_PASS` | `STAGING_PASS` | `N/A` | Batch 209 menyelesaikan workspace lintas halaman. Batch 210 menyatukan POS di Penjualan & Pesanan; SDM+payroll; Produk+Menu Book; serta WA+Telegram. Visual UAT role/desktop/mobile tetap menjadi gerbang A3. |
| `AUD-A3-UI-00` | P1-07 | P1 / A3.2 | Adopsi design system belum menyeluruh. | Seluruh wave 8.3 selesai, duplikasi dibersihkan, dan visual UAT lulus. | `CODE_PASS` | `STAGING_PASS` | `N/A` | `A3-CODE-CLOSED` pada Batch 211: wave 1–9 tidak lagi masuk antrean implementasi ulang. Checklist 0.4 menjadi bukti scope; visual UAT tetap pekerjaan operasional terpisah. |
| `AUD-C2-BRAND-01` | P1-08 | P1 / A0+A5→C2 | Branding/tenant dan hardcode identitas belum terpusat. | Boundary config/secret teknis selesai di `_30`; UI profil usaha/onboarding dikerjakan pada C2 `_28`. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 219–220 memusatkan jalur inti Profil Usaha: setup admin tiga langkah, login/sidebar/footer, QR ulasan, label aset, kontrak, dan fallback printer tanpa mengganti override outlet/layout. Template Menu Book/marketing, URL/SEO/customer install profile, pajak/service, dan integrasi tetap C2 terbuka; tidak ditangani sebagai bug transaksi `_30`. |
| `AUD-A5-RUNTIME-01` | P1-09 | P1 / A0+A5 | Runtime/dependency deployment belum mempunyai matrix final. | Versi PHP/MariaDB/extension/Node/Python diuji pada install/upgrade. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 142: matrix dan probe otomatis lulus pada PHP/FPM 8.1.32, MariaDB 10.6.23, Node 20.20.2, npm 10.8.2, Python 3.10.12; customer release masih menunggu runtime security qualification, `fileinfo` untuk WhatsApp file, dan Composer build yang lebih baru. |
| `AUD-A4-TEST-01` | P1-10 | P1 / A4+A5 | Test updater/install/upgrade belum lengkap. | Quality gate, clean install, upgrade, restore/rollback, dan UAT perangkat mempunyai bukti. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 188–190 menutup PHPStan baseline menjadi 0, browser harness aktual, dan contract refund DP Reservasi POS Mobile; gate staging penuh lulus (78/4/1/2/1/1/1/3). A5.11 restore, A5.12 clean install, dan A5.13 rollback lulus; updater customer dan UAT perangkat terbuka. |
| `AUD-A5-PACK-01` | P1-11 | P1 / A0+A5 | Repository belum menjadi paket customer yang repeatable. | Source recovery, manifest versi, signed artifact, delivery, dan rollback lulus. | `IN_PROGRESS` | `CODE_PASS` | `BLOCKED` | Batch 179 membuat builder menolak worktree kotor dan hanya mengemas file tracked dari commit bersih. Batch 219 menambah preflight SemVer/package/runtime/migration serta plan installer non-mutating. Full-history/commit recovery, signing key produksi, artifact bersih, installer/updater nyata, dan delivery customer belum. |
| `AUD-A2-PAY-01` | P2-01 | P2 / A2 | Slip payroll belum menjelaskan uang makan terpisah. | Aturan hitung, UI, slip, dan audit disepakati serta diuji. | `CODE_PASS` | `AUTO_PASS` | `BLOCKED` | Batch 214 menetapkan: rate uang makan adalah hak per hari; mode `MONTHLY` masuk transfer payroll, sedangkan `CUSTOM` dicatat per hari dan dibayar lewat batch terpisah pada rentang harian/mingguan/lainnya. Payroll result line dan slip memisahkan hak bulanan, hak custom, pembayaran custom, total hak, serta sisa custom. Calendar/ledger/batch custom menolak mode bulanan dan PH berhak tetap dapat dicairkan custom. Data/periode historis tidak dihitung ulang otomatis; UAT payroll baru dan audit pembayaran nyata masih perlu. |
| `AUD-A2-FIN-01` | P2-02 | P2 / A2 | Riwayat rekening membingungkan pada transaksi backdate. | Running balance/as-of dan label backdate konsisten. | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | Batch 181: riwayat utama diurutkan posting ID, menampilkan tanggal bisnis/waktu posting dan label Backdate. Batch 213 menambah snapshot **saldo bisnis per tanggal cut-off** untuk satu rekening: saldo awal catatan + mutasi bertanggal bisnis sampai cut-off, terpisah dari chain posting. Snapshot juga membandingkan ledger dengan saldo aktif secara read-only dan memperingatkan bila berbeda. Tidak ada rebuild atau perubahan saldo/data. Fixture finance dan keputusan acceptance/rebuild historis masih perlu. |
| `AUD-A2-PH-01` | P2-03 | P2 / A2 | Jadwal PH lama mendahului eligibility. | Keputusan migrasi/arsip dan audit entitlement tertulis. | `NOT_STARTED` | `NONE` | `DEFERRED_OWNER` | Tidak mengubah data tanpa keputusan owner. |
| `AUD-A2-PUR-01` | P2-04 | P2 / A2 | Receipt purchase historis belum lengkap. | Repair/arsip dengan preview dan rekonsiliasi stok/nilai. | `NOT_STARTED` | `NONE` | `DEFERRED_OWNER` | Data historis memerlukan persetujuan. |
| `AUD-A2-PUR-02` | P2-04A | P2 / A2 | Riwayat harga item harus mengikuti alur purchase operasional: PO `PAID`; receipt bukan prasyarat. | PO `PAID` tampil per profil item; receipt `POSTED` diprioritaskan bila ada agar tidak duplikat; ledger tanpa receipt line tetap fallback. | `CODE_PASS` | `AUTO_PASS` | `PROD_READY` | Batch 182 awalnya terlalu berpusat pada receipt; Batch 184 menyesuaikan sumber dengan alur Finance. UAT TISSUE POP UP dan item 561 tanpa mengubah data. |
| `AUD-A2-POSDATA-01` | P2-05 | P2 / A2 | Status terminal order lama belum dinormalisasi. | Aturan normalisasi dan replay-safe audit disetujui. | `CODE_PASS` | `STAGING_PASS` | `DEFERRED_OWNER` | Sudah diaudit tanpa replay; keputusan data tetap milik owner. |
| `AUD-A1-REVIEW-01` | P2-06 | P2 / A1 | Public review memerlukan anti-spam. | Rate limit, validation, abuse logging, dan privacy rule. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 152: fondasi anti-spam/privasi single-server dan transaksi member/ulasan lulus. Batch 153: empat writer admin wajib edit+POST+CSRF khusus; controller dan UI otomatis lulus. Sisa acceptance: UAT admin login, QR/perangkat/proxy nyata. |
| `AUD-A1-LANDING-01` | NEW-06 | P1 / A1 | Writer konfigurasi, menu, galeri, embed, dan tautan Landing Page sebelumnya hanya mengandalkan RBAC. | Semua mutasi wajib `POST`, CSRF scoped yang sama pada form/AJAX, dan request gagal sebelum writer bila token tidak sah. | `CODE_PASS` | `AUTO_PASS` | `BLOCKED` | Batch 188 menutup 18 writer dengan token session 256-bit, header/form token, serta smoke 24 check. UAT editor Landing Page tetap diperlukan sebelum release customer. |
| `AUD-A5-RET-01` | P2-07 | P2 / A5 | Availability rebuild log belum mempunyai retention. | Retention period, purge terukur, audit, backup, dan rollback. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 143: policy, read-only preflight, quarantine audit, checksum, dan restore rollback tersedia; 52.445 row sukses lama baru kandidat archive, database purge tetap OFF sampai archive/agregasi lulus. |
| `AUD-A5-LIFE-01` | P2-08 | P2 / A5→C3 | Upload dan service pendamping belum mempunyai lifecycle produk. | Lokasi runtime, permission, backup, upgrade, uninstall, dan retention terdokumentasi. | `CODE_PASS` | `AUTO_PASS` | `BLOCKED` | Batch 143 menetapkan lokasi/preservasi/logrotate/uninstall; pemindahan runtime dan installer customer tetap C3. |
| `AUD-A1-SYS-01` | NEW-01 | P0 / A1 | Halaman System Tools dapat mengirim path root, daftar dump, status replication/failover, dan seluruh config kepada satu izin view. | Pecah izin read-sensitive, whitelist field, redaksi path/backup metadata, dan negative test. | `CODE_PASS` | `AUTO_PASS` | `PROD_READY` | Batch 136: hak Export menjadi izin baca sensitif terpisah; View-only mendapat ringkasan tanpa path/metadata; config di-whitelist tanpa password; test DB menjadi POST+CSRF; 18 negative contract dan 74 regression lulus. |
| `AUD-A1-TG-01` | NEW-02 | P1 / A1+A5 | Laporan internal belum mempunyai kanal Telegram Bot yang terotorisasi dan berjejak. | Target group/channel allowlist, webhook secret, queue idempoten, jadwal, RBAC, CSRF, log, resolusi status tidak pasti, dan worker aman. | `CODE_PASS` | `UAT_PASS` | `BLOCKED` | Batch 132–139: bot, target Namua, outbound/queue/webhook aktif; notifikasi Codex kini membawa ringkasan jawaban akhir yang dibatasi dan disaring tanpa prompt/tool output. Penutupan release tetap menunggu UAT command inbound dan otorisasi per pengirim untuk grup non-tepercaya. |
| `AUD-A1-ACT-01` | NEW-05 | P1 / A1+A5 | Belum ada registry terpadu untuk mengetahui siapa mengakses halaman atau melakukan perubahan data. | Log metadata-only menggabungkan login, page view, dan audit transaksi; menampilkan user, waktu, IP, browser/perangkat, halaman/entitas; RBAC hanya SUPERADMIN secara default. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 183–184. Migration upgrade staging applied 1/skipped 9 dan table, page, menu, serta grant SUPERADMIN terverifikasi. Page view bersifat forward-only; UAT browser role/admin dan transaksi baru masih diperlukan sebelum release. |
| `AUD-A1-FIN-01` | NEW-03 | P0 / A1 | Writer draft, close, dan reopen periode keuangan pernah hanya mengandalkan login/RBAC; redirect proses juga menerima URL kiriman. | Semua mutasi periode wajib izin aksi, POST, CSRF scoped, token tidak bercampur, form mengikuti hak aksi, redirect tetap lokal, serta reopen lock/transaksi dengan update bersyarat. | `CODE_PASS` | `AUTO_PASS` | `BLOCKED` | Batch 154 mengunci boundary controller/form; Batch 155 mengunci row `CLOSED`, rollback gagal lock/write/commit, dan menolak reopen kedua; Batch 157 menambah reauth password sebelum writer reopen. UAT akun finance nyata tetap terbuka. |
| `AUD-A1-STEP-01` | NEW-04 | P0 / A1 | Tindakan finansial sensitif hanya bergantung pada sesi login/RBAC sehingga transaksi yang ditinggal di perangkat kasir dapat dipakai ulang. | Reauth tidak mengubah matrix izin: password diverifikasi pada endpoint scoped atau form server-side, mengeluarkan proof acak satu-kali yang terikat sesi/token+user+aksi+target, lalu writer mengonsumsi proof sebelum model. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 156: Void Kasir dan Refund Pesanan Terbayar **web** memakai password masked, CSRF transaksi, proof hash 180 detik, one-use, dan limiter kegagalan lokal sesi. Batch 157 memakai kontrak proof sama untuk Reopen Periode Keuangan web tanpa meneruskan password ke model. Batch 158 menutup Cetak Ulang Order Kasir web dan menambahkan CSRF sebelum target printer dibuat. Batch 159–160 menutup Post/VOID Adjustment Base/Prepare. Batch 161 menutup **Post/VOID Adjustment Stok Gudang dan Divisi web** dengan CSRF header scoped dan proof `STOCK_ADJUSTMENT_POST`/`STOCK_ADJUSTMENT_VOID`. Batch 162 menutup Save/Delete Draft dan Post/VOID Component Batch Produksi web dengan token `X-Production-Component-Batch-Csrf` serta proof `COMPONENT_BATCH_POST`/`COMPONENT_BATCH_VOID` sebelum `ComponentStockWriter`; jalur Quick Batch dan Quick Adjustment Daily Component mengikuti token/proof endpoint resmi. Batch 163 menutup Daily Recon Component; Batch 164 menutup Transfer Stok Divisi; Batch 166–167 menutup seluruh writer Stock Opening. Batch 176 menutup Void/Refund POS Mobile, Batch 177 Reprint, Batch 186 Tutup Kasir, Batch 190 pengembalian DP saat penolakan reservasi APK, dan Batch 191 pengembalian DP saat penolakan atau pembatalan reservasi web. Proof web maupun mobile satu-kali terikat aktor, aksi, dan target tepat; consume gagal tertutup sebelum writer. Password tidak diteruskan ke writer. Aksi mobile lain, MFA, dan UAT role/perangkat masih terbuka. |

Temuan tambahan September 13:

| ID | Sumber | Prioritas/fase | Masalah | Solusi/acceptance | Implementasi | Validasi | Release/data | Bukti atau langkah berikutnya |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `AUD-A2-FIN-04` | NEW-20260913 | P1 / A2 | Estimasi mengabaikan pemasukan manual, tidak membedakan modal/prive, dan settlement ronde ulang berpotensi terposting ganda. | Kategori eksplisit, agregasi bersama, koreksi metadata teraudit, posting sisa settlement, guard CSRF/closed/retry. | `CODE_PASS` | `AUTO_PASS` | `UAT_PENDING` | Batch 247: fixture SQLite/MariaDB dan browser; schema staging terpasang tanpa perubahan transaksi lama. UAT user serta review kategori historis tetap diperlukan. |

### 0.4 Checklist rollout UI 8.3

| ID | Gelombang | Scope/acceptance | Implementasi | Validasi | Status nyata |
| --- | ---: | --- | --- | --- | --- |
| `AUD-A3-UI-01` | 1 | Shared button, icon action, alert/confirm, loading, dan form validation tersedia serta dipakai konsisten. | `CODE_PASS` | `AUTO_PASS` | Primitive global tersedia; adopsi halaman lama tetap diperiksa per wave. |
| `AUD-A3-UI-02` | 2 | Filter, table, pagination, loading/error/empty state konsisten dan responsive. | `CODE_PASS` | `AUTO_PASS` | Batch 180, 194, dan 195 menutup pola list ber-volume tinggi: Master Component, Riwayat Harga Item, dan Mutasi Stok Divisi. Shell/filter/table/empty/loading/error responsif berlaku global melalui layout; halaman editor/print dengan kontrak interaksi khusus tidak dipaksa menjadi daftar AJAX. |
| `AUD-A3-UI-03` | 3 | Sidebar, page header, tabs, cards, keyboard focus, dan mobile shell konsisten. | `CODE_PASS` | `STAGING_PASS` | Shell/sidebar lulus; visual UAT belum. |
| `AUD-A3-UI-04` | 4 | POS web dan reservation memakai pola baru tanpa mengganggu kontrak APK. | `CODE_PASS` | `AUTO_PASS` | Batch 196 memigrasikan daftar Reservasi POS web; Batch 197 menyamakan daftar Order Aktif Kasir; Batch 198 menerapkan pola pada Self Order; Batch 199 menutup rollout kode pada Online Food web—state memuat/kosong/gagal, retry, reset filter, pencarian debounce, dan cancel request lama. Tidak ada writer, data, route, atau file APK yang diubah. UAT visual desktop/mobile (filter cepat, jaringan gagal, empty state, tab, pagination, serta tindakan bisnis) tetap `PENDING`. |
| `AUD-A3-UI-05` | 5 | Inventory dan production selesai dimigrasikan serta regression lulus. | `CODE_PASS` | `AUTO_PASS` | **Tidak diulang pada batch lanjutan.** Batch 180 dan 200–208 telah menutup Master Component, stok Gudang/Bahan Baku/Component, snapshot bulanan, tiga Daily Matrix, mutasi, FIFO, opname, audit lot, dan lot component. Kontraknya tetap **Header/Tab → Filter → Ringkasan grafis → Tabel/Pagination**. Writer/detail mempertahankan kontrak aksi A1 dan mendapat shell global, bukan rewrite berisiko. |
| `AUD-A3-UI-06` | 6 | Purchase dan finance selesai dimigrasikan serta regression lulus. | `CODE_PASS` | `AUTO_PASS` | Batch 209 menutup workspace navigation responsif Finance, Purchase/Store Request, serta switch Ringkasan/Matrix purchase. Filter, query, modal aksi, route, dan writer tidak diubah; detail/form memakai shell global dan tidak memerlukan duplikasi tab lokal. |
| `AUD-A3-UI-07` | 7 | Attendance, payroll, dan asset selesai dimigrasikan serta regression lulus. | `CODE_PASS` | `AUTO_PASS` | Batch 209 menutup rekap/pengajuan Absensi, Kasbon, Payroll Period/Pencairan, Bonus, navigasi Asset, serta drill-down Penyusutan/Rekon. In-page tab yang mengganti pane tetap dipertahankan; linked page memakai workspace responsif. |
| `AUD-A3-UI-08` | 8 | Master, reports, dan system selesai dimigrasikan dengan permission tetap fail-closed. | `CODE_PASS` | `AUTO_PASS` | Batch 209 menutup Master Extra, Loyalty, Access Audit, serta pola tab System/Telegram/WA. Tidak ada resolver izin, registry menu, route, atau endpoint sistem yang diganti. |
| `AUD-A3-UI-09` | 9 | Style/script duplikat dibersihkan per rumpun dan visual UAT desktop/mobile `UAT_PASS`. | `CODE_PASS` | `AUTO_PASS` | CSS tab/ringkasan stale pada rumpun yang sudah memakai primitive bersama telah dibersihkan dan seluruh smoke A3 masuk quality gate. **UAT visual manual tetap PENDING**; ia bukan pekerjaan kode yang boleh diklaim lulus otomatis. |

### 0.5 Register SQL staging dan server utama

SQL baru yang menjadi bagian implementasi wajib direview, dibackup/preflight,
langsung dijalankan di staging, diuji ulang bila idempoten, dan dicatat. Server
utama tetap dijalankan oleh pemilik. SQL repair data historis atau destructive
tidak otomatis dijalankan.

Pengecualian otorisasi Batch 253: transport Telegram melarang perubahan database.
SQL baru dicatat **PREPARED_NOT_APPLIED**, tidak otomatis dijalankan maupun
didaftarkan auto-apply. Validasi MariaDB, registrasi runner dan staging apply
dilanjutkan melalui IDE; checkbox aktivasi tetap terbuka.

| File SQL | Klasifikasi | Status staging | Bukti staging | Status server utama | Tindakan berikutnya |
| --- | --- | --- | --- | --- | --- |
| `2026-08-15b_wa_report_schedule.sql` | Legacy → `baseline` | `DISPOSITION_CLOSED` | DDL dimiliki clean-install baseline; seed default diganti `2026-09-05e`; file lama tidak direplay. | `DO_NOT_RUN` | Server existing memakai migration runner; fresh install memakai baseline dan seed kanonis. |
| `2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql` | Legacy → `replace` | `DISPOSITION_CLOSED` | File gabungan POS/WhatsApp berisiko dan tidak direplay; gap baru wajib migration sempit per domain. | `DO_NOT_RUN` | Jangan deploy file ini pada staging maupun server utama. |
| `2026-09-02a_wa_report_schedule_claim_lease.sql` | Legacy → `enroll` | `STAGING_PASS` | Fingerprint lease exact; state boleh masuk source-line managed tanpa klaim bahwa file lama pernah dieksekusi. | `DO_NOT_RUN` | Upgrade otomatis hanya dari receipt `finance-managed-v1`; instalasi lama perlu bridge manual. |
| `2026-09-03a_auth_login_throttle_foundation.sql` | Legacy → `enroll` | `STAGING_PASS` | Tabel, index, dan FK bernama exact pada fingerprint; tidak perlu replay. | `DO_NOT_RUN` | Enroll hanya lewat fingerprint/receipt, bukan insert ledger palsu. |
| `2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql` | Legacy → `enroll` | `STAGING_PASS` | Kontrak `DATETIME(6)` exact pada fingerprint; delimiter SQL lama tidak direplay. | `DO_NOT_RUN` | Enroll hanya lewat fingerprint/receipt setelah dependency auth lulus. |
| `2026-09-04a_a3_navigation_registry_canonicalization.sql` | Legacy → `retire` | `DISPOSITION_CLOSED` | DML reparent/deactivate/delete berisiko dipensiunkan; clean-install seed menjadi sumber kanonis. | `DO_NOT_RUN` | Perubahan navigasi berikutnya wajib migration sempit dengan postcondition. |
| `2026-09-04b_a3_page_alias_registry.sql` | Legacy → `enroll` | `STAGING_PASS` | Fingerprint struktur, FK, seed semantik, target, dan collision exact. | `DO_NOT_RUN` | Enroll hanya lewat fingerprint/receipt; DML lama tidak direplay. |
| `2026-09-04c_a5_schema_migration_registry_foundation.sql` | Managed migration | `STAGING_PASS` | Applied 1 dan replay skipped 1; ledger exact. | `PENDING_OWNER` | Jalankan melalui migration runner saat deployment utama. |
| `2026-09-05a_telegram_bot_foundation.sql` | Managed migration | `STAGING_PASS` | Backup valid; runner applied 1/skipped 1 lalu replay skipped 2; 6 tabel, 4 page, 5 menu, dan RBAC exact. | `PENDING_OWNER` | Jalankan melalui migration runner setelah `2026-09-04c`; provision secret dan worker secara terpisah. |
| `2026-09-05b_telegram_setup_guide.sql` | Managed seed | `STAGING_PASS` | Backup valid; runner applied 1/skipped 2 lalu replay skipped 3; page/menu tunggal dan grant SUPERADMIN view-only exact. | `PENDING_OWNER` | Jalankan melalui migration runner setelah `2026-09-05a`; hanya menambah page/menu panduan dan grant view-only. |
| `2026-09-05c_telegram_safe_activation_default.sql` | Managed seed | `STAGING_PASS` | Backup valid; runner applied 1/skipped 3 lalu replay skipped 4; setting untouched berubah OFF, ledger/checksum exact. | `PENDING_OWNER` | Wajib dijalankan tepat setelah `2026-09-05b`; jangan rollout aplikasi bila ledger `09-05c` belum tercatat. Nilai yang pernah diedit operator dipertahankan. |
| `2026-09-05d_a5_clean_install_reference_seed.sql` | Managed clean-install seed | `STAGING_PASS_DISPOSABLE` | Runner memasang seed sebelum Telegram; hasil exact 20 group, 206 page, 241 menu, 10 alias, 1 SUPERADMIN, dan 206 permission. | `FRESH_INSTALL_ONLY` | Jangan dijalankan pada staging/server utama yang sudah berisi data; migration runner otomatis mengecualikannya dari policy upgrade. |
| `2026-09-05e_whatsapp_safe_reference_seed.sql` | Managed repeat-safe seed | `STAGING_PASS` | Backup privat valid; runner applied 1/skipped 4 lalu replay applied 0/skipped 5; template, session, ledger, dan checksum exact. | `PENDING_OWNER` | Jalankan melalui migration runner policy `upgrade`; jangan menjalankan pengganti legacy secara manual di luar runner. |
| `2026-09-06a_component_formula_version_history.sql` | Managed schema migration | `STAGING_PASS` | Runner staging applied 1/skipped 5 lalu replay applied 0/skipped 6. Menambah header dan line snapshot formula append-only; tidak mengubah stok, HPP, atau formula transaksi lama. | `PENDING_OWNER` | Jalankan hanya melalui migration runner policy `upgrade`; jangan menjalankan manual atau mengisi history dengan repair data. |
| `2026-09-06b_component_formula_restore_action.sql` | Managed schema migration | `STAGING_PASS` | Runner staging applied 1/skipped 6 lalu replay applied 0/skipped 7. Hanya memperluas enum riwayat formula dengan `RESTORE`; tidak mengubah stok, HPP, atau baris formula aktif. | `PENDING_OWNER` | Jalankan hanya melalui migration runner policy `upgrade`, setelah `2026-09-06a`; jangan menjalankan file manual. |
| `2026-09-06c_pos_mobile_reversal_step_up.sql` | Managed schema migration | `STAGING_PASS` | Runner staging applied 1/skipped 7 lalu replay applied 0/skipped 8. Menambah limiter kegagalan pada token dan tabel proof hash satu-kali untuk Void/Refund POS Mobile; tidak mengubah order, pembayaran, stok, atau HPP historis. | `PENDING_OWNER` | Jalankan hanya melalui migration runner policy `upgrade`; APK harus memakai endpoint verify lalu mengirim `step_up_proof` saat Void/Refund. Jangan menjalankan file manual. |
| `2026-09-06d_pos_mobile_reprint_step_up.sql` | Managed schema migration | `STAGING_PASS` | Runner staging applied 1/skipped 8 lalu replay applied 0/skipped 9. Memperluas enum proof dengan `ORDER_REPRINT`; tidak mengubah order, pembayaran, stok, atau HPP historis. | `PENDING_OWNER` | Jalankan hanya melalui migration runner policy `upgrade`, setelah `2026-09-06c`; APK harus verify lalu mengirim `step_up_proof` saat Reprint. Jangan menjalankan file manual. |
| `2026-09-06e_activity_audit_foundation.sql` | Managed schema migration | `STAGING_PASS` | Runner policy `upgrade`: applied 1, skipped 9. Tabel audit, page registry, menu `System → Log Aktivitas`, dan grant view SUPERADMIN masing-masing terverifikasi satu. | `PENDING_UAT` | Akses beberapa halaman dan lakukan satu transaksi sebagai SUPERADMIN, lalu verifikasi page view/login/transaksi baru pada System → Log Aktivitas. |
| `2026-09-06f_pos_mobile_cashier_close_step_up.sql` | Managed schema migration | `STAGING_PASS` | Runner policy `upgrade`: applied 1, skipped 10 lalu replay applied 0, skipped 11. Menambah target `cashier_session_id`, index konsumsi, dan enum proof `CASHIER_CLOSE`; tidak mengubah order, pembayaran, stok, atau HPP historis. | `PENDING_OWNER` | Jalankan hanya melalui migration runner policy `upgrade`, setelah `2026-09-06d`; APK harus verify password lalu mengirim `step_up_proof` saat Tutup Kasir. Jangan menjalankan file manual. |
| `2026-09-06g_pos_mobile_reservation_refund_step_up.sql` | Managed schema migration | `STAGING_PASS` | Runner policy `upgrade`: applied 1, skipped 11 lalu replay applied 0, skipped 12. Menambah target `reservation_id`, index konsumsi, dan enum proof `RESERVATION_DEPOSIT_REFUND`; tidak mengubah DP, order, pembayaran, stok, atau HPP historis. | `PENDING_OWNER` | Jalankan hanya melalui migration runner policy `upgrade`, setelah `2026-09-06f`; APK harus verify password lalu mengirim `step_up_proof` hanya saat menolak reservasi dengan opsi pengembalian DP. Jangan menjalankan file manual. |
| `2026-09-06h_roastery_label_template_studio.sql` | Managed schema migration | `STAGING_PASS` | Runner policy `upgrade`: applied 1, skipped 12. Menambah penyimpanan template Label Studio serta dua template awal `Classic Portrait` dan `Retail Wide`; label lama tidak dihapus atau diubah. | `PENDING_OWNER` | Jalankan hanya melalui migration runner policy `upgrade`; jangan menjalankan file manual. Template kustom tersimpan di tabel baru dan dibawa oleh backup database customer. |
| `2026-09-06i_a3_sidebar_task_oriented_layout.sql` | Managed seed | `STAGING_PASS` | Runner `upgrade`: applied 1, skipped 13; replay applied 0, skipped 14. Postcheck: 12 root kerja, 0 collision urutan, dan parent POS/SDM/Menu Book/integrasi sesuai layout. Hanya `sys_menu` (label/parent/urutan) berubah. | `FRESH_INSTALL_OR_UPGRADE` | Paket customer menerapkan migration ini lewat runner; tidak perlu SQL manual terpisah. Route, page registry, dan RBAC tidak berubah. |
| `2026-09-07a_c2_c4_business_profile_license_runtime_foundation.sql` | Managed schema migration | `STAGING_PASS` | Runner `upgrade`: applied 1, skipped 14; replay applied 0, skipped 15. Membuat 9 tabel metadata profil/lisensi, 2 page/menu System, dan grant SUPERADMIN. Tidak mengubah transaksi, stok, HPP, kas, payroll, atau data historis; FeatureGate default audit-only. | `FRESH_INSTALL_OR_UPGRADE` | Paket customer menerapkannya melalui migration runner, bukan manual. Enforcement lisensi dilarang sampai signed entitlement, verifier, dan UAT offline tersedia. |
| `2026-09-12a_roast_connect_catalog.sql` | Managed schema, pekerjaan thread Roast Connect | `LEDGER_VERIFIED` | Batch 257: ID/path/checksum ledger staging cocok dengan katalog dan file sumber. Tidak dijalankan ulang; schema modul dan UAT tidak diaudit pada batch ini. | `PENDING` | Pemilik modul perlu memverifikasi kesiapan allowlist customer dan uji rilis secara terpisah; catatan apply staging bukan bukti kelulusan paket. |
| `2026-09-13a_finance_mutation_reporting_category.sql` | Managed schema migration | `STAGING_PASS` | Tiga kolom kategori nullable, request key nullable dan unique index; fixture migration dua kali PASS, staging applied dan ledger tercatat; tidak mengubah nominal/saldo/data lama. | `FRESH_INSTALL_OR_UPGRADE` | Dibawa release baru melalui runner clean_install/upgrade; tidak menjalankan SQL manual pada aplikasi utama lama. |
| `2026-09-14a_finance_control_workspace.sql` | Managed schema + metadata sidebar | `STAGING_PASS` | Dua tabel kontrol/rencana kosong, tiga kolom tautan nullable, page/menu dan grant SUPERADMIN; applied/replay dengan checksum ledger. Tidak mengubah transaksi/saldo lama. | `FRESH_INSTALL_OR_UPGRADE` | Melalui runner setelah 2026-09-13a; perlu kode Kontrol Keuangan dan profil customer v2 di cutoff baru. |
| `2026-09-14b_finance_control_operations.sql` | Managed schema + metadata kontrol | `STAGING_PASS` | Rincian transfer/biaya, realisasi rencana, bukti privat, kebijakan dan persetujuan; izin awal SUPERADMIN, kebijakan approval OFF. Applied + ledger replay staging tanpa backfill transaksi. | `FRESH_INSTALL_OR_UPGRADE` | Setelah 2026-09-14a; runtime/profil customer v3, folder bukti privat dan akses PHP-FPM. |
| `2026-09-14c_finance_allocation_bank_review.sql` | Managed schema customer | `SCHEMA_VERIFIED_LEDGER_PENDING` | Batch 257, 2026-09-14 19:46:50 WIB: postcheck langsung db_finance/MariaDB 10.11.10 lengkap 95 kondisi, struktur sesuai; catatan/checksum tiga prasyarat cocok. Hanya ledger 14c belum ada. Tidak mengulang SQL atau menulis ledger otomatis. | `NOT_RELEASED` | v10: terdaftar clean_install/upgrade dengan hash asli; adopsi manual wajib proof. Tes disposable, bukan apply ulang DB aktif. Review Control dan jalur update aman sebelum rollout. |
| `2026-09-15a_finance_general_ledger.sql` | Managed schema customer | `USER_REPORTED_APPLIED` | Pengguna mengonfirmasi sudah menjalankan SQL, dicatat 2026-09-15 09:35 WIB; target yang diberikan db_finance. Empat tabel/24 COA/sidebar/izin belum diverifikasi langsung. Tidak mengulang SQL atau menganggap tes SQLite sebagai validasi DDL MariaDB. | `NOT_RELEASED` | v10: terdaftar clean_install/upgrade dengan hash asli; adopsi manual wajib proof. Tes disposable, bukan apply ulang DB aktif. Review Control dan jalur update aman sebelum rollout. |
| `2026-09-15b_finance_journal_assistant.sql` | Managed schema customer | `USER_REPORTED_APPLIED` | Pengguna mengonfirmasi sudah dijalankan, dicatat 2026-09-15 10:25 WIB; target pada perintah db_finance, prasyarat 15a. Tabel/FK/page/izin belum diverifikasi langsung. Tidak mengulang SQL atau mengubah checksum sumber. | `NOT_RELEASED` | v10: terdaftar clean_install/upgrade dengan hash asli; adopsi manual wajib proof. Tes disposable, bukan apply ulang DB aktif. Review Control dan jalur update aman sebelum rollout. |
| `2026-09-15c_application_user_guide.sql` | Managed seed metadata customer | `USER_REPORTED_APPLIED` | Pengguna mengonfirmasi sudah dijalankan, dicatat 2026-09-15 11:24 WIB; target pada perintah db_finance. Output postcheck belum diterima. Tidak mengulang SQL atau mengubah checksum. | `NOT_RELEASED` | v10: terdaftar clean_install/upgrade dengan hash asli; adopsi manual wajib proof. Tes disposable, bukan apply ulang DB aktif. Review Control dan jalur update aman sebelum rollout. |
| `2026-09-16a_procurement_stock_review.sql` | Managed schema customer | `USER_REPORTED_APPLIED` | Pengguna mengonfirmasi apply, dicatat 2026-09-16 06:21 WIB; target perintah sebelumnya db_finance. Belum menerima postcheck. Agent tidak mengulang apply atau mengubah checksum. | `NOT_RELEASED` | v10: terdaftar clean_install/upgrade dengan hash asli; adopsi manual wajib proof. Tes disposable, bukan apply ulang DB aktif. Review Control dan jalur update aman sebelum rollout. |
| `2026-09-20a_pos_stock_commit_not_required.sql` | Managed schema korektif | `NOT_APPLIED_ACTIVE` | Menambah NOT_REQUIRED pada ENUM status stok POS yang sudah dipakai kode; uji hanya disposable, tidak memperbaiki transaksi lama otomatis. | `PENDING_CONTROL` | Melalui upgrade resmi setelah review; status kosong/drift wajib peninjauan. Jangan menjalankan ke database aktif dari thread ini. |
| `2026-09-23a_module_notifications.sql` | Managed schema notifikasi | `NOT_APPLIED_ACTIVE` | BELUM DIJALANKAN pada db_finance; dua tabel pengaturan/antrean kosong, tanpa mutasi transaksi. Lulus MariaDB disposable; perintah dan prasyarat pada laporan 2026-09-23. | `PENDING_CONTROL` | Hash terdaftar clean_install/upgrade; tujuan, pesan, nomor dan credential customer bukan seed. |
| `baseline/2026-09-05_clean_install_schema.sql` | Clean-install schema-only | `CODE_PASS` | 296 tabel baseline tetap immutable; dilanjutkan 27 migration terdaftar, tanpa impor data development. | `NOT_FOR_UPGRADE` | Hanya database customer kosong; bukan jalur update instalasi aktif. |

Migration runner kini mengelola 27 file: `2026-09-04c`, clean-install-only
`2026-09-05d`, repeat-safe `2026-09-05e`, `2026-09-05a`–`2026-09-05c`, dan
`2026-09-06a`–`2026-09-06b` Formula Component history/restore serta
`2026-09-06c`–`2026-09-06d` proof reversal/reprint POS Mobile dan
`2026-09-06e` registry aktivitas serta `2026-09-06f` proof Tutup Kasir dan
`2026-09-06g` proof Refund DP Reservasi POS Mobile serta `2026-09-06h` Label
Studio template, `2026-09-06i` layout sidebar berbasis tugas, dan `2026-09-07a`
fondasi profil usaha/lisensi audit-only, `2026-09-12a` Roast Connect (pekerjaan
thread lain, kelayakan rilis belum diverifikasi di Batch 247), serta `2026-09-13a`
kategori mutasi keuangan, `2026-09-14a` Kontrol Keuangan, `2026-09-14b` operasi kontrol, `2026-09-14c`, `2026-09-15a/b/c`, `2026-09-16a`, `2026-09-20a` koreksi ENUM POS, dan `2026-09-23a` notifikasi modul. Upgrade memuat 26 entri, tanpa seed clean-install-only.
Tujuh file lain tetap legacy/non-deployable, tetapi disposition-nya sudah final
dan dijaga otomatis: 1 baseline, 4 enroll via fingerprint, 1 replace, dan
1 retire. Jangan menjalankan seluruh folder `sql/` sekaligus.

Lima SQL 14c/15a/15b/15c/16a kini managed untuk clean-install dan upgrade; checksum asli tidak berubah. Status USER_REPORTED_APPLIED pada database aktif tidak diubah menjadi verifikasi palsu. Adopsi ledger customer memerlukan proof lengkap; partial/drift dihentikan. SQL korektif 20a tidak dijalankan ke database aktif. Jumlah top-level SQL **33** (26 managed + 7 legacy). Bukti tes disposable bukan persetujuan publish atau update aplikasi aktif.

### 0.6 Register tahap terlewat dan ditunda

Register ini mencegah pekerjaan lama hilang ketika eksekusi sudah maju ke fase
berikutnya. `TERLEWAT` berarti belum pernah ditutup walaupun fase sesudahnya
sudah berjalan. `DITUNDA` berarti keputusan penundaan memang disengaja.

| ID | Klasifikasi | Pekerjaan yang belum tertutup | Alasan/status nyata | Rencana tindak lanjut |
| --- | --- | --- | --- | --- |
| `GAP-01` | `IN_PROGRESS` | Penutupan A0: credential produksi, rotasi secret, recovery Git, dan pemisahan runtime data customer. | Credential DB dan runtime index/package lulus Batch 146; full history dan dua ref recovery lokal dibuat Batch 187A. Branch lokal dan `origin/main` masih divergen; secret lama belum dirotasi dan off-site encryption belum aktif. | Putuskan strategi integrasi remote sebelum merge/push, lalu rotasi secret pada cutover terjadwal; jangan menghapus runtime staging. |
| `GAP-02` | `IN_PROGRESS` | Sisa A1: isi baseline izin per jabatan, MFA, aksi mobile lain, dan UAT. | Batch 148–150 mengunci endpoint, audit Master, serta scope multi-role web/mobile. Batch 151–167 menutup writer keuangan/stok prioritas. Batch 168–175 menutup lost-update/audit, history, jalur kanonis, serta restore Formula Component. Batch 176–177 menutup Void/Refund/Reprint APK; Batch 186 Tutup Kasir; Batch 190 mengembalikan DP reservasi APK; Batch 191 menutup refund DP reservasi web dengan proof reauth terikat aktor, aksi, dan reservasi tepatnya. Aksi sensitif mobile lain, MFA, serta UAT perangkat belum tertutup. | Tetapkan baseline tanpa reset izin otomatis, inventaris aksi mobile yang masih bernilai tinggi, lalu jalankan UAT finance/admin/QR/proxy/APK pada build yang mendukung proof baru. |
| `GAP-03` | `IN_PROGRESS` | P2 bisnis A2: uang makan slip payroll dan running balance rekening backdate. | Batch 181 menutup urutan/label posting; Batch 213 menambah snapshot saldo bisnis cut-off dan signal kecocokan ledger/saldo aktif secara read-only. Batch 214 menutup aturan kode uang makan bulanan/custom, batch custom, dan penjelasan slip tanpa mengubah periode lama. Fixture data backdate terkontrol, UAT finance/payroll, serta keputusan acceptance/rebuild historis belum tertutup. | UAT satu rekening dengan transaksi backdate; bila ledger tidak cocok, review audit sebelum tindakan apa pun. UAT satu payroll baru untuk mode Bulanan dan satu untuk Custom—termasuk batch pembayaran custom. Setelah itu minta acceptance finance untuk as-of/rebuild. |
| `GAP-04` | `CODE_CLOSED_UAT_PENDING` | A3.2 rollout UI 8.3 dan visual UAT. | Batch 211 memeriksa ulang seluruh gelombang: Inventory/Production sudah selesai dan tidak diulang; gelombang 2 serta 4–9 ditutup sebagai implementasi kode dengan smoke/regression yang sudah ada. | Hanya jalankan UAT visual per role pada desktop/mobile; temuan nyata masuk sebagai bug baru, bukan membuka ulang rollout A3 secara umum. |
| `GAP-05` | `TERLEWAT_OPERASIONAL` | UAT browser role, APK/device, printer fisik, dan updater customer. | Automated tooling A4 lulus tetapi tidak menggantikan perangkat nyata. | Jalankan setelah kandidat build dan APK siap; bukti UAT harus terikat ke versi artefak. |
| `GAP-06` | `TERLEWAT_SEBAGIAN` | Telegram inbound `/menu`, `/omzet`, `/belanja` dan allowlist identitas pengirim. | Outbound/queue/webhook/Namua lulus; command inbound belum diterima sebagai UAT. | Uji pada Namua, lalu tambahkan sender allowlist sebelum dipakai pada grup non-tepercaya. |
| `GAP-07` | `SELESAI_TEKNIS` | Disposition tujuh SQL legacy dan batas jalur updater. | Batch 145 mengunci 1 baseline, 4 enroll, 1 replace, dan 1 retire; seluruh replay legacy serta adopsi ledger palsu ditolak. | Source-line `finance-managed-v1` dapat memakai migration managed; instalasi pre-catalog wajib bridge manual. UAT updater customer tetap `GAP-05`/C3. |
| `DEFER-01` | `DITUNDA_OWNER` | Repair mismatch component dan anomali transaksi historis. | Pemilik meminta data dibiarkan; script koreksi sudah lulus. | Owner memperbaiki melalui modul; otomatisasi hanya dengan preview dan persetujuan terpisah. |
| `DEFER-02` | `PENUNDAAN_DICABUT` | UAT POS Mobile/APK pada build terbaru. | Owner mengizinkan perubahan mobile kembali sebelum Batch 150; penundaan script lama tidak berlaku lagi. UAT perangkat tetap belum selesai. | Perubahan mobile boleh dalam batch terarah dengan regression test; jaga kompatibilitas kontrak APK. UAT build nyata mengikuti GAP-05. |
| `DEFER-03` | `DITUNDA_OWNER` | Migrasi/arsip PH lama dan receipt purchase historis. | Perubahan data historis berisiko dan membutuhkan keputusan owner. | Tetap read-only sampai aturan arsip/repair disetujui. |

**Urutan recovery yang berlaku:** `GAP-01` deployment/repo →
`GAP-02` A1 web/mobile dan `GAP-03` A2 bisnis → `GAP-04` UI per rumpun →
`GAP-05` UAT terikat artefak. Repair data `DEFER-01` dan `DEFER-03` tidak
dikerjakan otomatis; penundaan script `DEFER-02` telah dicabut owner.

### 0.7 Ringkasan historis temuan (bukan sumber status)

Daftar berikut mempertahankan konteks audit lama. Bila kalimatnya berbeda dari
control board 0.2–0.6, control board yang berlaku.

- `[~]` P0-01 — guard endpoint Master: sebagian writer sudah diperketat;
  seluruh registry, direct URL, dan role negative test masih terbuka.
- `[~]` P0-02 — recipe, formula, extra, dan bundle: writer prioritas sudah
  diberi RBAC/CSRF/POST-only, concurrency, dan audit; Formula Component
  kanonis memiliki timeline snapshot, tetapi jalur legacy dan restore versi
  belum ditutup.
- `[~]` P0-03 — POS Mobile: authorization endpoint baca/tulis dan POST-only 11
  writer sudah ada. Audit ulang setelah Batch 88 menemukan implementasi binding
  outlet/terminal yang dicatat pada Batch 75–81 tidak lengkap pada source aktif;
  Batch 89a–89f sudah memulihkan binding endpoint APK inti dan smoke monolitik
  mencapai akhir. Batch 105 menerima sanitizer cetak dan panel order masuk dari
  backup APK tanpa mengganti model aktif. Batch 107 menyelaraskan empat smoke
  lama dengan kontrak terminal cadangan: sesi OPEN milik pegawai dan outlet yang
  sama dapat dipakai, sedangkan spoof konteks, pegawai/outlet berbeda, dan sesi
  tutup tetap ditolak. Batch 150 menolak login/token saat scope role `NONE` atau
  `AMBIGUOUS`, memvalidasi ulang scope setiap request bearer, dan mengembalikan
  konteks division/outlet/terminal ke APK. Batch 176–177 menutup step-up
  Void/Refund/Reprint; UAT APK nyata dan aksi sensitif lain masih perlu ditutup.
- `[~]` P0-04 — multi-role dan scope: filter role nonaktif, union izin, serta
  fail-closed scope web/mobile sudah lulus negative test dan probe staging.
  Simulator akses/report selisih sudah tersedia pada Batch 151. Isi baseline
  izin per jabatan dan UAT role masih terbuka.
- `[x]` P0-05 — penghapusan permission role memakai relasi yang benar dan
  sudah diuji pada Batch 2A.
- `[~]` P0-06 — boundary secret, production preflight, session audit, login
  throttle, dan scoped CSRF sudah ada. Credential database staging kini berada
  di file privat luar source dan preflight bebas temuan; secure-cookie final,
  MFA, rotasi secret, dan secret store customer masih terbuka.
- `[~]` P0-07 — backup tidak lagi mendorong perubahan otomatis ke `main`;
  storage privat, checksum, retention quarantine, restore drill, dan untracking
  runtime tersedia. Connectivity Git lulus, tetapi clone masih shallow;
  baseline commit, temp pack, dan off-site terenkripsi masih terbuka.
- `[~]` P0-08 — migration runner, katalog checksum, registry, bundle,
  disposable restore, clean install, rollback, runtime matrix, retention, dan
  signature/provenance sudah lulus. Disposition tujuh SQL legacy telah dikunci
  tanpa replay; updater customer lintas release dan delivery masih terbuka.
- `[~]` P0-09 — trust boundary Printer Agent sudah diperketat; installer
  service, pairing produksi, rotation, dan recovery belum.
- `[x]` P1-01 pada sisi script — workflow koreksi nilai/VOID tidak mengubah
  kuantitas dan refresh cache HPP live mencakup material, item, dan component.
  Mismatch historis dipisahkan sebagai pekerjaan data pemilik, bukan blocker
  script A2.
- `[x]` P1-02 — dashboard sudah menghitung mismatch kuantitas dan nilai FIFO;
  smoke Batch 48 dan regression berikutnya lulus.
- `[x]` P1-03 — sidebar runtime sekarang hanya merender tree terotorisasi dari
  registry database; regroup, injection, ikon, dan menu sintetis hardcode sudah
  dikeluarkan dari renderer.
- `[x]` P1-04 — sidebar, favorite, pin, dan reorder memakai resolver akses yang
  sama serta fail-closed untuk page/menu nonaktif atau tidak terdaftar.
- `[x]` P1-05 — URL kanonis, ikon, urutan sibling, sales alias, dan sepuluh
  page alias sudah dinormalisasi. Enam deklarasi route identik tetap dibekukan
  karena dimiliki pekerjaan APK, tetapi probe memastikan tidak ada target yang
  bertentangan.
- `[x]` P1-06 — fondasi workspace kanonis untuk Master, Product Monitoring,
  Inventory/Component, dan Online Food sudah dipindahkan ke registry. Konsolidasi
  tab per halaman dapat dilanjutkan sebagai penyempurnaan UI tanpa membuat
  sumber navigasi kedua.
- `[x]` P1-07 pada fondasi A3 — app shell, token, header, action bar, card,
  filter, table region, state, focus, dan aturan responsive tersedia global.
  Modernisasi detail 336 view dilakukan bertahap di atas fondasi yang sama,
  bukan syarat mengulang arsitektur A3.
- `[ ]` P1-08 — branding/tenant masih menjadi pekerjaan productization di
  roadmap komersialisasi; hardcode runtime belum seluruhnya dihapus.
- `[~]` P1-09 — dependency Composer, npm, dan Python sudah dikunci serta
  diperiksa vulnerability gate. Matrix A5.14 dan probe staging sudah lulus;
  qualification runtime customer, `fileinfo`, dan pembaruan Composer build
  masih memblokir klaim produksi.
- `[~]` P1-10 — quality gate A4 sudah mencakup regression lintas modul, browser,
  Printer Agent, preflight, vulnerability, dan static analysis. Clean install,
  upgrade, restore, health, dan rollback A5 sudah lulus; updater customer serta
  UAT perangkat tetap terbuka.
- `[~]` P1-11 — allow/deny package policy, secret scan, builder deterministik,
  serta signature/provenance Ed25519 sudah tersedia. Recovery object Git,
  signing key produksi, installer/updater, dan delivery customer tetap
  pekerjaan A0/A5/C3.
- `[ ]` P2-01 sampai P2-06 masih menunggu batch/keputusan bisnis. P2-07 dan
  P2-08 sudah memperoleh baseline retention/lifecycle A5.15; aktivasi purge
  database dan installer customer tetap diblokir gerbang archive/C3.

### 0.8 Indeks bukti batch

Daftar ini menunjukkan batch yang pernah lulus pada scope masing-masing. Ia
tidak menjadi checklist penyelesaian fase; status aktif tetap berada pada
control board 0.2–0.6.

- `[x]` Batch 48: dashboard component membedakan mismatch quantity dan nilai.
- `[x]` Batch 53.1–53.2: login throttle, session audit, dan presisi timestamp.
- `[x]` Batch 54–65: hardening writer formula/recipe/extra/bundle dan validasi
  mapping Extra Group.
- `[x]` Batch 66–68: preflight read-only dan concurrency mapping.
- `[x]` Batch 69: koreksi HPP/nilai tanpa mengubah kuantitas, dengan saran HPP.
- `[x]` Batch 70: tampilan nilai aktif dan VOID koreksi berbasis exact-state.
- `[x]` Batch 72: sinkronisasi cache HPP live setelah koreksi nilai dan VOID.
- `[x]` Batch 73: POS Mobile writer wajib POST dan diuji sebelum autentikasi.
- `[x]` Batch 74: POS Mobile token terikat ke terminal aktif dan device key.
- `[x]` Batch 75: POS Mobile sesi kasir terikat ke terminal/outlet token dan
  tidak mengekspos daftar sesi global pada bearer request.
- `[x]` Batch 76: POS Mobile daftar dan detail order bearer terikat ke outlet
  token dengan 404 generik untuk order lintas outlet.
- `[x]` Batch 77: lima endpoint order-id POS Mobile bearer terikat ke outlet
  token sebelum preview, cetak, payment preparation, atau voucher query.
- `[x]` Batch 78: writer void, refund, dan payment POS Mobile terikat ke outlet
  token sebelum model, monitor task, atau idempotency sync-event.
- `[x]` Batch 79: document-print void, refund, dan payment POS Mobile terikat ke
  outlet order kanonik sebelum direct-print atau pembuatan print-attempt.
- `[x]` Batch 80A: bootstrap dan katalog POS Mobile terikat ke outlet/terminal
  perangkat; daftar outlet, terminal, dan sesi lintas perangkat tidak bocor.
- `[x]` Batch 81: simpan, konfirmasi, dan push order POS Mobile memakai konteks
  device otoritatif sebelum writer atau sync-event; replay lintas konteks ditolak.
- `[x]` Batch 82: direct URL opening stock divisi dan export template tidak lagi
  dapat dibuka hanya dengan permission Purchase Order.
- `[x]` Batch 83: rebuild impact dan reclassify Purchase wajib POST dan token
  CSRF maintenance khusus sebelum membaca payload atau menjalankan model.
- `[x]` Batch 84A: form generik create/edit Master menerbitkan token; store dan
  update wajib POST/CSRF sebelum validation, upload, lookup, atau writer.
- `[x]` Batch 84B: toggle, stock mode, dan reorder Master wajib POST/CSRF;
  fallback link toggle telah menjadi form POST dan AJAX mengirim header token.
- `[x]` Batch 84C: generator hari libur tahunan wajib POST/CSRF sebelum membaca
  tahun, sumber kalender `core`, atau menjalankan upsert.
- `[x]` Batch 85A: favorite sidebar wajib POST/CSRF, memfilter menu berdasarkan
  izin kanonis, mengisolasi ownership user, dan menyegarkan cache/UI secara aman.
- `[x]` Batch 85B: penyimpanan struktur sidebar superadmin wajib POST/AJAX dan
  token CSRF administrasi terpisah sebelum payload atau transaksi.
- `[x]` Batch 85C: store/update/delete/toggle menu sidebar superadmin wajib
  POST/CSRF sebelum payload, lookup, query, writer, atau cache clear.
- `[x]` Batch 86: proses dan retry antrean availability POS wajib POST/CSRF
  sebelum payload atau service; worker CLI tetap terpisah.
- `[x]` Batch 87: repair material ID, repair profile, dan merge profile pada
  rekonsiliasi stok divisi wajib POST/CSRF sebelum payload atau model.
- `[x]` Batch 88: tujuh writer System Tools—termasuk backup, konfigurasi MySQL,
  sinkronisasi, dan failover—wajib POST/CSRF sebelum side effect.
- `[x]` Batch 116: PHPStan terpin memindai seluruh `application/` dengan
  baseline lama yang dibatasi dan menjadi blocker profile release/staging.
- `[x]` Batch 117: preflight dan builder memakai policy package yang sama;
  artefak tar deterministik memiliki manifest SHA-256 dan fail-closed.

Catatan koreksi bukti: status PASS Batch 75–81 dibuka kembali setelah smoke POS
Mobile ternyata berhenti pada kegagalan pertama dan implementasi binding tidak
lengkap pada source aktif. Remediasi dimulai pada Batch 89; dokumentasi tidak
boleh menganggap binding tersebut selesai sebelum smoke penuh berakhir PASS.

- `[x]` Batch 89a: login POS Mobile tidak lagi membedakan credential versus
  terminal invalid; bearer tervalidasi membawa terminal dan outlet registry.
- `[x]` Batch 89b: bootstrap dan katalog POS Mobile memakai outlet/terminal
  bearer serta tidak mengekspos sesi, outlet, atau terminal perangkat lain.
- `[x]` Batch 89c: daftar dan detail order POS Mobile bearer dibatasi ke outlet
  perangkat; order lintas outlet menghasilkan 404 generik tanpa kebocoran.
- `[x]` Batch 89d.1: payment, void, refund, dan replay payment memvalidasi order
  serta outlet kanonis sebelum writer, monitor, atau sync-event.
- `[x]` Batch 89d.2: buka, status, preview tutup, dan tutup kasir POS Mobile
  terikat ke employee, outlet, terminal, dan sesi OPEN perangkat.
- `[x]` Batch 89d.3: simpan, konfirmasi, dan push order POS Mobile memakai
  outlet/terminal bearer serta sesi kasir OPEN; replay lintas konteks ditolak.
- `[x]` Batch 89e: preview reversal, reprint, target cetak konfirmasi, persiapan
  pembayaran, dan pencarian voucher dibatasi ke outlet order kanonis.
- `[x]` Batch 89f: cetak dokumen void, refund, dan payment memakai relasi dokumen
  ke order/outlet kanonis sebelum membuat target atau attempt cetak.
- `[x]` Batch 90: sinkronisasi runtime order POS wajib POST dan token transaksi
  sebelum payload, refresh stok live, atau job diproses.
- `[x]` Batch 91: export data opening divisi existing wajib permission export;
  pengguna view-only tidak lagi melihat tombol atau dapat mengunduh datanya.
- `[x]` Batch 92: matrix direct-URL A1 menjalankan 21 smoke terisolasi untuk 22
  kontrak route/controller lintas Master, Sidebar, Purchase, System, dan POS.
- `[x]` Batch 93: discovery dan test printer POS Mobile dibatasi ke outlet serta
  terminal bearer, memakai izin test lebih kuat, dan meredaksi secret jaringan.
- `[x]` Batch 94: period guard fail-closed bila schema belum siap dan tanggal
  ambigu/tidak valid ditolak.
- `[x]` Batch 95: perubahan HPP item-centric ikut menandai dan membangun ulang
  cache produk terdampak, termasuk resep dan component bertingkat.
- `[x]` Batch 96: reversal POS memakai quantity residual otoritatif, menolak
  keputusan invalid/duplikat, dan menjaga status partial/full tetap konsisten.
- `[x]` Batch 97: void/refund web maupun APK menyegarkan availability setelah
  commit melalui shared `Pos_model`, tanpa mengubah controller POS Mobile.
- `[x]` Batch 98: period lock menjadi barrier transaksional untuk ledger, koreksi
  nilai, POS commit/reversal, backdate, rollover, dan lifecycle close.
- `[x]` Batch 99: matrix 12 smoke terisolasi dan probe database read-only A2
  lulus; auditor menyatakan gerbang kode/smoke/read-only DB A2 selesai.
- `[x]` Batch 100–104: registry/sidebar database-only, favorite fail-closed,
  page alias eksplisit, dan fondasi design system menutup A3.1 serta fondasi
  A3.2 pada gate kode/database staging/smoke; rollout UI A3.2 tetap terbuka.
- `[x]` Batch 105: perubahan backup APK digabung terarah ke sanitizer printer
  mobile dan panel order masuk web; `Pos_model` aktif serta tiga backup tetap
  dipertahankan.
- `[x]` Batch 106: runner quality gate deterministik menyediakan profil
  parallel, release, dan staging dengan proses test terisolasi.
- `[x]` Batch 107: contract test POS Mobile untuk sesi kasir, draft/upsert,
  authorization, printer, dan reader sinkron dengan mode terminal cadangan.
- `[x]` Batch 129: control board tunggal memisahkan status implementasi,
  validasi, release/data, rollout UI, dan register SQL.
- `[x]` Batch 130: dashboard roadmap internal read-only menampilkan control
  board `_30` dan fase komersialisasi `_28`; akses dibatasi ke staging internal
  serta superadmin dan tidak masuk artefak customer.
- `[x]` Batch 131: dashboard roadmap dibagi menjadi lima tab—ringkasan,
  temuan, UI 8.3, SQL, dan komersialisasi—dengan URL hash dan tampilan mobile.
- `[x]` Batch 132: fondasi Telegram Bot internal menyediakan target allowlist,
  jadwal, command laporan, queue/lease, log, resolusi `UNKNOWN`, setting,
  sidebar, RBAC, migration managed, dan worker CLI tanpa menyentuh POS/APK.
- `[x]` Batch 133: halaman panduan Telegram bertab menjelaskan pembuatan bot,
  environment, Chat ID, webhook, target, jadwal, pengujian, dan troubleshooting;
  sidebar serta permission panduan tetap view-only.
- `[x]` Batch 134: panduan disederhanakan menjadi tiga tab dan Pengaturan
  menjadi Setup Assistant. Operasi aman dilakukan lewat UI, credential/URL
  kanonis/cron tetap server-side, dan master switch default OFF telah diterapkan
  serta direplay pada staging.
- `[~]` Batch 135: credential Telegram ditempatkan di file root-only di luar
  webroot, PHP-FPM 8.1 dan cron membaca environment yang sama, bot nyata
  terverifikasi, dan panduan aaPanel dilengkapi contoh langsung. Target/webhook
  dan UAT kirim menunggu penghentian consumer long-poll lain yang menghasilkan
  konflik `getUpdates` HTTP 409.
- `[x]` Batch 136: System Tools memisahkan baca sensitif melalui hak Export,
  memberi halaman ringkasan aman untuk View-only, memakai whitelist config
  tanpa password, melindungi test koneksi dengan POST+CSRF, dan lulus negative
  serta regression contract.
- `[x]` Batch 137: token bot di-rotate oleh pemilik tanpa dikirim ulang ke chat;
  grup Namua ditemukan dan di-allowlist, switch aktif, webhook HTTPS cocok,
  pesan langsung serta notifikasi Codex terkirim, dan antrean worker berakhir
  `SENT` dalam satu attempt dengan satu delivery log.
- `[~]` Batch 138: A5.12 menghasilkan baseline schema-only 282 tabel dan guard
  checksum/policy fail-closed. Import disposable, empat managed migration,
  default Telegram OFF, nol row customer, serta cleanup database/file lulus.
  Seed referensi dan bootstrap owner sengaja belum diekstrak dari staging.
- `[x]` Batch 139: hook penyelesaian Codex mengirim ringkasan jawaban akhir ke
  Namua. Prompt pengguna dan output tool tidak diteruskan; blok kode, URL,
  token, password, secret, dan API key disaring serta panjang pesan dibatasi.
- `[x]` Batch 140: A5.12 selesai pada staging disposable. Seed hanya membawa
  metadata navigasi netral-customer dan satu role global SUPERADMIN; bootstrap
  owner memakai file privat 0600, menolak database yang sudah mempunyai user,
  dan percobaan kedua ditolak. Lima migration, hash password, postcondition,
  serta cleanup database/file semuanya lulus.
- `[x]` Batch 141: A5.13 selesai. Health check pascainstalasi mengikat seluruh
  file pada release manifest, baseline, seed, 25 tabel wajib, ledger migration,
  SUPERADMIN, owner, serta default aman clean-install. Probe upgrade pada
  `db_finance` lulus tanpa mutasi. Drill database disposable membuktikan empat
  migration diterapkan, kegagalan canary ditolak, backup yang sama dipulihkan,
  schema/ledger/seed kembali identik, dan database/user sementara bersih 0/0.
- `[x]` Batch 142: A5.14 mengunci matrix PHP/FPM 8.1, MariaDB 10.6, Node 20,
  npm 10, Python 3.10, Composer 2, extension PHP, dan tiga dependency lock.
  Contract serta probe staging lulus; warning `fileinfo` WhatsApp dan Composer
  build lama dicatat sebagai blocker capability/release, bukan disembunyikan.
  Register 0.6 juga memisahkan tahap terlewat dari data/POS yang sengaja ditunda.
- `[x]` Batch 143: A5.15 mengganti penghapusan backup otomatis dengan
  SHA-256, retain-newest, dry-run, konfirmasi hash, quarantine, dan audit 0600.
  Preflight database hanya SELECT dan menemukan 52.445 detail availability
  sukses lama; mismatch, queue aktif, upload, ledger, serta transaksi tidak
  menjadi target. Tidak ada file/data staging yang dihapus.
- `[x]` Batch 144: A5.16 menambahkan signature/provenance Ed25519 terpisah.
  Verifier pra-instalasi menolak paket unsigned, berubah, memakai key asing,
  mempunyai path/symlink berbahaya, atau tidak cocok dengan manifest dan tiga
  policy release. Private key wajib 0600 di luar repository; sodium menjadi
  extension runtime wajib. Key produksi dan artefak customer tidak dibuat.

Status `[x]` di atas berarti batch lulus review dan smoke, bukan berarti
seluruh modul atau database customer sudah lulus UAT. Repair mismatch historis
tidak dilakukan pada batch ini sesuai batas pekerjaan operator.

## 1. Kesimpulan untuk Pemilik Aplikasi

Finance sudah memiliki fondasi bisnis yang jauh lebih lengkap daripada aplikasi
kasir biasa. Di dalam satu sistem sudah tersedia POS, reservasi, self order,
printer per divisi, loyalty, purchase order, store request, gudang, persediaan
divisi, produksi, HPP, keuangan, absensi, PH, payroll, aset, laporan, dan audit
operasional.

Aplikasi ini layak dikembangkan menjadi produk komersial, tetapi **belum aman
langsung dipaketkan dan dijual**. Hambatan utama sekarang bukan kekurangan
fitur. Hambatan utamanya adalah pengamanan aksi, konsistensi nilai data lama,
navigasi yang tumbuh tanpa satu sumber tunggal, tampilan yang belum seragam,
serta proses deployment dan backup yang masih bercampur dengan repository
source.

Kesimpulan terpenting dari scan ulang:

1. Stok bahan baku aktif saat ini sehat: tidak ada mismatch material, tidak ada
   lot negatif, dan tidak ada defisit terbuka.
2. Enam component masih mismatch nilai walaupun kuantitasnya sama. Selisih
   absolutnya sekitar Rp1.418.765.823,55 dan berasal dari nilai historis negatif
   yang terbawa ke bulan aktif.
3. Bug dashboard component yang hanya memeriksa kuantitas sudah diperbaiki pada
   Batch 48. Enam mismatch nilai tetap merupakan utang data dan masih perlu
   repair/UAT terarah.
4. Guard writer component sekarang sudah menolak unit cost negatif. Jadi masalah
   component tersebut adalah utang data historis yang harus direpair terarah,
   bukan bukti bahwa writer baru masih bebas membuat nilai negatif.
5. Endpoint master, resep, bundle, formula, dan POS mobile belum seluruhnya
   memeriksa izin per aksi. Menyembunyikan menu belum cukup untuk mengamankan
   URL.
6. Role operasional masih menerima hak yang terlalu luas. Kasir dan Barista
   dapat memperoleh hak mutasi pada area yang tidak sesuai tugasnya.
7. Sidebar saat ini mempunyai dua sumber kebenaran: tabel sys_menu dan
   penyusunan ulang secara hardcode di view sidebar. Kondisi ini membuat urutan,
   izin, ikon, dan grouping mudah berbeda.
8. Runner backup sudah dipisahkan dari perubahan otomatis ke `origin/main`,
   tetapi backup masih membutuhkan storage terpisah, retention, restore drill,
   dan pembersihan artefak/history.
9. Keamanan produksi belum layak untuk distribusi customer: sebagian boundary
   secret, CSRF, session audit, dan login throttling sudah diperbaiki, tetapi
   secure cookie, MFA, rotasi secret, dan verifikasi seluruh endpoint masih
   terbuka.
10. Fondasi UI bersama sebenarnya sudah ada, tetapi pemakaiannya belum merata.
    Ratusan view masih membawa style, script, modal, alert, filter, tabel, dan
    pagination sendiri-sendiri.
11. Focused smoke test sekarang sudah mencakup auth, POS, WhatsApp, Telegram, inventory,
    formula, recipe, mapping, release boundary, dan area prioritas lain.
    Regression browser/DB, migration/restore, CI, serta end-to-end finance,
    attendance, payroll, asset, dan printer masih belum cukup.
12. Schema deployment masih mengandalkan ratusan SQL manual. Belum ada registry
    versi schema dan migrasi deterministik untuk instalasi atau update customer.
13. Branding dan pengaturan tenant belum terpusat. Identitas Namua masih
    hardcode di banyak file dan belum dapat diubah aman oleh customer.
14. Repository pengembangan terlalu besar karena backup, upload, log, dan
    artefak operasional. Git pack lokal sudah sekitar 7,66 GiB.
15. Sebelum masuk ke pekerjaan komersialisasi, prioritas teknis tetap pengamanan,
    repair nilai component, penyederhanaan navigasi, test, schema, dan release
    foundation. Keputusan paket dan lisensi berada di dokumen `_28`.

## 2. Cara Audit Dilakukan

Pemeriksaan ini dilakukan tanpa menulis data bisnis dan tanpa menjalankan SQL
repair. Area yang diperiksa:

- 444 file PHP diperiksa dengan PHP lint; hasilnya 444 lulus dan 0 gagal.
- Smoke test inventory period guard dijalankan; 9 skenario lulus.
- Registry sys_page, sys_menu, auth_role_permission, controller page code, dan
  route dibandingkan.
- URL menu internal diuji tanpa login untuk memastikan tidak 404 atau 500.
- Hak setiap role dihitung dan dibandingkan dengan pekerjaan role tersebut.
- Controller mutasi penting dibaca untuk memastikan guard tidak hanya berada di
  tampilan.
- Kesehatan lot, ledger bulanan, defisit, runtime job, availability queue,
  rekening, PH, payroll, purchase, asset, dan relasi foreign key diperiksa.
- Inventory_control_model dipanggil untuk membandingkan ledger bulanan dengan
  lot aktual pada material dan component.
- View, CSS, JavaScript, modal, filter, tabel, pagination, ikon, warna, dan
  branding dipetakan secara statis.
- Isi repository, backup, upload, log, migration, dependency, dan script layanan
  pendamping diperiksa.

Batas audit:

- Audit ini bukan penetration test eksternal.
- Audit visual dilakukan dari struktur view dan pola komponen, bukan smoke test
  browser pada seluruh ratusan halaman.
- Tidak semua kombinasi transaksi dapat diuji hanya dengan snapshot database.
- Temuan historis tidak boleh langsung direpair massal tanpa preview,
  preflight, transaksi, audit trail, dan validasi sesudah apply.

## 3. Snapshot Kondisi Saat Ini

### 3.1 Source dan database

| Area | Hasil |
| --- | ---: |
| File PHP yang dilint | 444 |
| Kegagalan lint | 0 |
| Smoke test inventory | 9 lulus |
| Tabel aktif | 317 |
| View database | 0 |
| Foreign key | 663 |
| Engine tabel aktif | Seluruhnya InnoDB |
| sys_page | 201 total, 195 aktif |
| sys_menu | 234 total, 226 aktif |
| Role aktif | 12 |
| Baris auth_role_permission | 1.333 |
| SQL utama di folder sql | 74 |
| SQL arsip di sql/_old | 386 |

### 3.2 Kesehatan persediaan dan antrean

| Pemeriksaan | Hasil |
| --- | ---: |
| Lot material negatif | 0 |
| Lot component negatif | 0 |
| Mismatch kuantitas material | 0 |
| Mismatch nilai material | 0 |
| Mismatch kuantitas component | 0 |
| Mismatch nilai component | 6 |
| Defisit OPEN | 0 |
| Defisit SETTLED | 75 |
| Defisit VOID | 7 |
| Defisit WRITTEN_OFF | 9 |
| Adjustment material/component bertanggal masa depan | 0 |
| Runtime job SUCCESS | 2.447 |
| Runtime job CANCELLED | 3 |
| Runtime job aktif terminal atau gagal | 0 |
| Availability queue SUCCESS | 253 |
| Availability rebuild log | 885.829 baris, sekitar 304,30 MiB |

### 3.3 Kondisi sehat yang perlu dipertahankan

- Ledger rekening aktif sama dengan saldo awal ditambah seluruh mutasi.
- Hanya ada satu rekening default dari tujuh rekening aktif.
- Tidak ditemukan orphan pada PO line, receipt, SR line, salary assignment,
  schedule, asset change request, dan finance mutation account.
- Tidak ada saldo PH mentah negatif.
- Tidak ada USE PH sebelum cutover 1 Juni 2026 yang masih aktif.
- PH memakai FIFO dan expiry disinkronkan sebelum ringkasan saldo ditampilkan.
- Guard batas PH dan jumlah hari jadwal sudah tersedia.
- Fairuz memiliki saldo aktif yang diharapkan, yaitu 1 PH dari hak terbaru.
- Pengaman periode inventory menolak backdate, future date, reopen, dan gap lot
  pada smoke suite yang tersedia.
- Kasus TAHU PONG dan KENTANG tidak lagi menjadi anomali aktif setelah repair
  terarah dan pengaman periode.
- Writer component saat ini menolak unit cost negatif dan rollover produksi
  menolak valuation negatif.
- Reservasi menghitung ulang harga dan HPP saat diverifikasi kasir ke POS.
- Halaman asset management yang baru memakai guard aksi lebih baik daripada
  pola controller master lama.

Makna penting: temuan yang sudah sehat di atas tidak boleh dihapus dari test.
Ia harus diubah menjadi invariant permanen agar bug lama tidak kembali.

## 4. Temuan Prioritas 0: Harus Ditutup Sebelum Handoff Komersial

### P0-01. Endpoint master generik belum deny-by-default

**Bukti utama:** application/controllers/Master.php.

Store, update, toggle, stock mode, dan beberapa endpoint generik belum seluruhnya
memanggil guard page dan aksi yang kanonis.

**Risiko untuk user:** akun yang dapat login berpotensi memanggil URL perubahan
langsung walaupun menu disembunyikan.

**Perbaikan wajib:**

1. Buat registry satu entity ke satu page code.
2. Semua endpoint view, create, edit, delete, export, approve, post, void, dan
   reopen harus memanggil guard server.
3. Entity yang tidak terdaftar harus ditolak, bukan memakai fallback.
4. Tambahkan audit log untuk perubahan master sensitif.
5. Tambahkan negative test direct URL untuk setiap role.

### P0-02. Writer resep, extra, bundle, dan formula belum konsisten memakai RBAC

**Bukti utama:** application/controllers/Master_relation.php.

Sebagian workspace sudah dijaga, tetapi banyak endpoint mutasi recipe, formula,
extra, dan bundle belum menggunakan izin aksi yang eksplisit.

**Risiko untuk user:** perubahan formula dapat mengubah HPP, kebutuhan bahan,
stok POS, dan produksi tanpa hak yang semestinya.

**Perbaikan wajib:**

- Gunakan page code kanonis untuk setiap kelompok writer.
- Pisahkan hak melihat, mengubah, menyetujui, dan mempublikasikan formula.
- Simpan before/after, alasan, aktor, waktu, dan versi formula.
- Formula yang sudah dipakai transaksi tidak boleh diubah tanpa versioning.

### P0-03. POS Mobile memakai token dan izin per aksi, dengan scope lanjutan

**Bukti utama:** application/controllers/Pos_mobile.php.

Batch 73–81 sudah memetakan permission endpoint prioritas, mewajibkan POST pada
writer, mengikat token ke terminal aktif/device key, mengikat buka/tutup/status
kasir ke outlet serta terminal token, dan membatasi discovery/load order ke outlet
token. Lima endpoint order-id dan writer void/refund/payment juga sudah dibatasi;
document-print memakai outlet order kanonik, sedangkan bootstrap/katalog hanya
menampilkan konteks outlet serta terminal perangkat. Simpan/konfirmasi/push
order juga memakai konteks token secara otoritatif sebelum writer/sync-event.
Batch 176 menutup Void/Refund bearer dengan endpoint verifikasi password,
proof hash 180 detik yang terikat token/user/terminal/aksi/order, limiter gagal
per token, dan consume atomik sebelum writer. Batch 177 menerapkan proof
aksi-spesifik yang sama pada Reprint; endpoint target cetak sekarang POST-only
dan mengonsumsi `ORDER_REPRINT` sebelum membuat target printer. Aksi mobile
sensitif lain masih belum memakai proof ini.

**Risiko untuk user:** token valid dapat memiliki kemampuan lebih luas daripada
menu atau role pemilik token.

**Perbaikan wajib:**

- Token harus membawa user, employee, outlet, terminal, device, role, dan
  daftar entitlement.
- Setiap endpoint mobile memakai permission yang sama dengan web.
- Gunakan masa token pendek, refresh token, revoke device, dan rotasi token.
- Aksi void dan refund sudah membutuhkan step-up proof pada web dan APK;
  reprint, reopen, serta adjustment mobile harus ditutup dalam batch terpisah.
- Jangan membuat kebijakan terpisah antara web POS dan APK POS.

### P0-04. Matrix role operasional terlalu luas

**Status 2026-09-05: `[~]`.** Batch 6 sudah membuat scope `NONE` dan
`AMBIGUOUS` fail-closed; masalah `NULL` yang selalu dibaca sebagai bebas adalah
risiko historis pada jalur lama, bukan alasan untuk menganggap patch tersebut
belum ada. Batch 150 menerapkan resolver yang sama pada login, bearer token, dan
sesi POS Mobile. Probe staging membuktikan 16 user aktif (13 multi-role), 3
superadmin, 3 global, 10 single, serta 0 `NONE`/`AMBIGUOUS`; dua user dengan
token mobile aktif juga mempunyai scope valid. Batch 151 menambahkan simulator
akses dan report selisih permission; 22 akun (aktif/nonaktif) pada 200 halaman
aktif staging cocok dengan resolver login. Yang masih terbuka adalah persetujuan
isi baseline izin per jabatan, step-up, dan UAT.

Jumlah izin saat ini:

| Role | View | Create | Edit | Delete | Export |
| --- | ---: | ---: | ---: | ---: | ---: |
| SUPERADMIN | 200 | 188 | 189 | 185 | 198 |
| CEO | 183 | 106 | 108 | 83 | 153 |
| MGR | 181 | 147 | 147 | 148 | 158 |
| ADMIN | 153 | 94 | 97 | 65 | 121 |
| ADM_GDG | 117 | 81 | 80 | 55 | 100 |
| ADM_FIN | 98 | 43 | 41 | 24 | 83 |
| KASIR | 107 | 85 | 85 | 75 | 80 |
| BARISTA | 117 | 87 | 85 | 81 | 85 |
| CHEF | 79 | 46 | 44 | 42 | 45 |
| ADM_HR | 44 | 27 | 26 | 26 | 32 |
| HOD | 33 | 11 | 7 | 6 | 18 |
| STAFF | 16 | 2 | 0 | 0 | 0 |

Jumlah besar tidak otomatis salah, tetapi sampling menemukan overgrant nyata:

- KASIR dapat memperoleh mutasi attendance settings, schedule, PH, finance
  account, mutation, payroll setup, PO, receipt, opening stock, master item,
  material, product, recipe, dan vendor.
- BARISTA dapat memperoleh mutasi inventory health, deficit, period control,
  value correction, opname, master item/material/vendor, recipe, bundle,
  component master, transfer, adjustment, dan WA settings.

Catatan historis yang menjadi alasan perbaikan:

- Auth_model sebelumnya menggabungkan seluruh role dengan pola OR tanpa state
  scope yang eksplisit.
- `get_division_scope()` sekarang membedakan `SINGLE`, `GLOBAL`, `NONE`, dan
  `AMBIGUOUS`; nilai scope invalid atau konflik tidak boleh dipakai sebagai
  akses bebas.
- Multi-role tetap perlu diuji pada kombinasi role nyata karena union
  permission dan scope adalah dua hal yang berbeda.

**Perbaikan wajib:**

1. `[ ]` Definisikan baseline role dari tugas user; keputusan izin tetap milik
   owner. Tidak melakukan reset role pelanggan secara otomatis.
2. `[ ]` Setelah baseline disetujui, siapkan preview seed konvergen dan
   persetujuan perubahan; jangan mencabut izin bisnis tanpa review owner.
3. `[x]` Union permission dan state scope eksplisit/fail-closed web/mobile
   tersedia; acceptance UAT tetap dicatat terpisah di control board.
4. `[~]` Pemisahan permission operasional, approval, correction, system, dan
   audit masih membutuhkan acceptance baseline per jabatan.
5. `[x]` Batch 151: halaman simulasi user, kombinasi role, menu/URL, scope,
   dampak simulasi, dan pengecualian izin per user.
6. `[~]` Batch 151: mesin report selisih baseline paket vs database tersedia.
   Baseline belum disetujui, sehingga UI menampilkan **belum dinilai**, bukan
   mengklaim nihil selisih atau menganggap izin owner salah.

**Cara memakai simulator:**

- Buka **Manajemen User → Simulasi Akses**, pilih pengguna, lalu **Periksa Akses**.
- Tab **Akses Efektif** menampilkan izin per halaman dan menu yang dapat tampil;
  tersedia pencarian, filter modul, serta paginasi 25 baris.
- Tab **Role & Scope**: centang kombinasi role lalu **Hitung Simulasi**.
  Tab **Perbandingan** menunjukkan dampaknya serta pengecualian izin user.
  Semua ini baca-saja: tidak menyimpan role, mengubah sesi, atau login sebagai
  pengguna lain. **Kembali ke Akses Saat Ini** membatalkan tampilan simulasi.
- Tab **Baseline Paket** membandingkan role aktual, bukan kombinasi simulasi.
  Jumlah izin bukan penentu benar/salah; akses per dokumen/outlet/perangkat
  masih tunduk pada pemeriksaan masing-masing modul.
- Akses halaman memakai izin lihat `auth.users.index` dan
  `auth.users.permissions` yang sudah ada. Tidak ada SQL/sidebar baru;
  pintu masuk berada di daftar dan detail user.

**Kontrak baseline untuk pengelola aplikasi:**

- File versi Git: `application/config/rbac_permission_baseline.json`.
  Awal `approved: false`, `roles: {}`; jangan menyalin izin aktif lalu otomatis
  menandainya disetujui. Ini acuan audit RBAC, bukan enforcement lisensi.
- Format: `schema_version: 1`, `approved: true` hanya setelah persetujuan owner,
  `label` teks, `roles` dipetakan dari `role_code` → `page_code` → flag
  `can_view`, `can_create`, `can_edit`, `can_delete`, `can_export` bernilai 0/1.
  Hanya role yang disebut dalam baseline dinilai; halaman/aksi yang tidak
  disebut **pada role tersebut** dianggap tidak diizinkan. Role lain ditandai
  belum dinilai. Superadmin aktif tetap dihitung akses penuh sesuai resolver.
- Uji fixture tanpa DB staging: `php tools/tests/access_simulator_smoke.php`.
  Uji read-only staging: `CI_ENV=staging php tools/tests/access_simulator_smoke.php --staging`.

### P0-05. Penghapusan role salah kolom — selesai pada batch prioritas

**Status 2026-09-03: `[x]` untuk bug yang diaudit.**

**Bukti utama:** application/models/Role_model.php.

Delete auth_role_permission masih menggunakan kolom id, padahal relasi role
berada pada role_id. Operasi juga belum dibungkus transaksi dan validasi
ketergantungan.

**Risiko:** permission role dapat tertinggal atau baris yang salah terhapus.

**Perbaikan wajib:** perbaiki kondisi ke role_id, gunakan transaksi, tolak role
sistem, periksa user-role, dan lakukan post-delete assertion.

### P0-06. Konfigurasi keamanan belum layak produksi

Bagian yang sudah ditangani pada level kode atau smoke:

- Resolver environment/production preflight untuk secret aplikasi dan database.
- Scoped CSRF pada banyak writer prioritas, termasuk draft/close/reopen periode
  keuangan pada Batch 154.
- Login throttling atomik, session audit fail-closed, dan kompatibilitas
  timestamp microsecond.

Bagian yang masih aktif atau belum terbukti pada deployment:

- Staging memakai file privat `/var/lib/finance-config/database.php` di luar
  source; production sengaja mengabaikannya dan wajib memakai secret resolver.
- CSRF global dan pengecualian API belum diverifikasi menyeluruh.
- Cookie secure dan httponly belum aman.
- Environment default masih development.
- Session dapat hidup satu tahun dan regenerasi terlalu jarang.
- MFA/step-up, rotasi secret, dan secret store per instalasi belum lengkap.
- Secret printer, WhatsApp, tunnel, backup, dan integrasi belum seluruhnya
  memakai boundary per instalasi.

**Perbaikan wajib:**

- `[x]` Pindahkan credential database staging ke file privat di luar source dan
  pertahankan resolver environment untuk production.
- `[x]` Pastikan source config dan paket release tidak membawa nilai database.
- Aktifkan HTTPS-only cookie, httponly, samesite, CSRF, dan session pendek.
- Tambahkan rate limit login dan endpoint publik.
- Buat pemeriksaan startup yang gagal tertutup bila production config belum
  lengkap.
- Jangan pernah memasukkan database.php customer ke paket update.

### P0-07. Backup otomatis pernah mengubah origin/main — containment selesai

**Status 2026-09-05: `[~]`.** Batch 5 menghentikan alur commit, merge, dan push
backup ke `origin/main`. Batch 146 melepas 1.367 upload, dump, log, `.env`,
output sementara, dan bytecode dari index Git tanpa menghapus file fisik.
Contract 25/25 serta `git fsck --connectivity-only` lulus. Clone masih shallow,
perubahan belum menjadi baseline commit, dan temp pack 2,1 GB belum boleh
dihapus tanpa recovery terpisah.

**Bukti historis utama:**

- scripts/backup/backup_full.sh men-stage backup/dumps dan backup/logs, commit,
  fetch, merge, lalu push ke branch main.
- scripts/backup/backup_full.bat melakukan pola yang sama.
- Log backup 1 September mencatat Git push berhasil ke origin/main.

**Risiko untuk user dan developer:**

- Backup customer bercampur dengan source product.
- Main dapat berubah setiap 30 menit tanpa review.
- Pull developer dapat membawa dump dan log operasional.
- Backup job dapat memerge source saat working tree sedang dipakai.
- Konflik source, kebocoran data, ukuran repository, dan rollback menjadi jauh
  lebih sulit.

**Perbaikan wajib:**

1. Hentikan Git sebagai media backup database.
2. Backup ke storage terpisah: object storage, SFTP, NAS, atau repository backup
   khusus yang terenkripsi.
3. Branch source hanya berubah melalui commit developer dan release pipeline.
4. Enkripsi backup, buat checksum, retention, restore drill, dan alert gagal.
5. Tambahkan backup/dumps, backup/logs, upload customer, PID, cache, dan runtime
   artifact ke ignore serta package exclude.
6. Bersihkan riwayat Git dengan prosedur terencana setelah backup eksternal
   terverifikasi. Jangan menjalankan history rewrite langsung di server aktif.

### P0-08. Deployment schema belum deterministik

Kondisi audit awal sudah berubah setelah A5.1–A5.11. Kondisi aktif sekarang:

- Migration CodeIgniter tetap nonaktif agar tidak ada auto-latest dari web.
- Runner A5, katalog checksum, registry staging, bundle, preflight, dan restore
  disposable sudah tersedia serta teruji.
- Lima migration registry dikelola runner; tujuh SQL top-level lain
  masih legacy/non-deployable.
- Clean-install, upgrade/rollback, retention, compatibility, serta signature
  sudah mempunyai kontrak teknis; tujuh SQL legacy dan updater customer belum
  disatukan ke jalur upgrade kanonis.

**Risiko:** source baru dapat berjalan di schema lama, SQL terlewat, SQL
terulang, atau customer berbeda mempunyai struktur berbeda.

**Perbaikan wajib:**

- Tetapkan satu migration runner.
- Setiap migration mempunyai version, checksum, dependency, preflight, apply,
  verify, dan bila aman rollback.
- Updater harus menolak build bila versi schema tidak cocok.
- Installer memakai baseline schema bersih, bukan dump database operasional.
- Simpan migration history per instalasi dan tampilkan di dashboard system.

### P0-09. Printer Agent dan layanan lokal belum memakai trust contract produksi

Printer Agent telah jauh lebih rapi, tetapi masih ada risiko produk:

- Local HTTP service belum memakai autentikasi request yang kuat.
- Bootstrap dapat fail-open pada konfigurasi tertentu.
- Development Flask server belum layak menjadi service customer.
- Secret, device identity, pairing, retry, dan version compatibility belum
  menjadi kontrak rilis yang seragam.
- Sebelum Batch 185, administrator tidak mempunyai alur unggah logo struk;
  konfigurasi menerima URL bebas yang berpotensi tidak bisa dijangkau perangkat
  kasir atau memaksa agent mengambil sumber luar.

**Status Batch 185:** halaman **POS → Printer → Tampilan Umum** sekarang
menampilkan logo aktif dan menerima PNG/JPG maksimum 1 MB (maksimum 2048 ×
2048). File disimpan sebagai aset aplikasi; bila tidak memilih file baru, logo
lama tetap dipakai. Nilai URL lama/luar dinormalisasi ke fallback aman sehingga
printer tidak mengambil gambar dari internet. Ini bukan profil branding tenant
secara menyeluruh—pekerjaan itu tetap berada pada C2/P1-08.

**Perbaikan wajib:** signed request, nonce, timestamp, device pairing,
certificate/token rotation, service manager Windows/Linux, health endpoint,
version negotiation, installer, auto-start, log rotation, dan update rollback.

## 5. Temuan Prioritas 1: Stabilitas Data, Navigasi, dan Pengalaman User

### P1-01. Enam component mismatch nilai masih aktif

**Status 2026-09-04: `[x]` pada sisi script; data historis menjadi tanggung
jawab pemilik.** Batch 69–70 menyediakan koreksi nilai HPP dan VOID tanpa
mengubah kuantitas. Batch 72 dan 95 memastikan perubahan material/item/component
memicu refresh cache HPP live produk setelah commit. Aplikasi tidak melakukan
repair otomatis atau menebak HPP untuk data lama.

| Component | Qty ledger vs lot | Selisih nilai |
| --- | --- | ---: |
| SAUCE BANGKOK | 785 = 785 | Rp -1.416.006.811,46 |
| CHICKEN CUBE 40 | 25 = 25 | Rp -2.731.669,34 |
| CHICKEN SLICE SUSHI | 4 = 4 | Rp -27.197,02 |
| SAMBAL BAWANG GEPREK | sama | Rp -89,46 |
| SAMBAL DABU-DABU | sama | Rp -50,03 |
| NASI PUTIH | sama | Rp -6,24 |

Total selisih absolut sekitar Rp1.418.765.823,55. Tiga nilai terbesar sudah
muncul sejak ledger historis Juni/Juli lalu dibawa saat rollover. Opening lot
September bernilai nol, sementara ledger bulanan membawa nilai negatif.

**Kesimpulan:** ini bukan stok fisik minus dan bukan defisit. Ini adalah nilai
historis rusak yang terbawa ke periode aktif.

**Perbaikan:**

1. Buat preview per component dari sumber nilai pertama kali menjadi negatif.
2. Pastikan formula produksi, unit conversion, qty hasil, total input cost,
   waste, dan allocation denominator pada transaksi asal.
3. Repair hanya ledger/lot yang terbukti, tanpa menebak harga.
4. Simpan before/after dan alasan.
5. Jalankan ulang health check sampai qty gap dan value gap nol.
6. Tambahkan assertion unit cost, total value, dan average cost tidak negatif
   di seluruh jalan masuk component.
7. Tambahkan regression test rollover bulan dan production posting.

### P1-02. Dashboard component menampilkan clear walaupun mismatch nilai ada

**Status 2026-09-03: `[x]` pada kode, UAT masih diperlukan.** Batch 48 sudah
memasukkan `monthly_lot_value_gap` ke pemeriksaan dashboard.

Sebelum perbaikan, fungsi dashboard hanya membandingkan kuantitas monthly,
movement, dan lot sehingga pemilik dapat melihat 0 mismatch walaupun Stock
Health menemukan selisih nilai besar.

**Perbaikan yang sudah dilakukan:**

- Dashboard harus menghitung mismatch qty dan mismatch nilai.
- Card harus memisahkan “Qty berbeda” dan “Nilai FIFO berbeda”.
- Tampilkan nilai absolut, divisi, component, bulan, dan link ke detail health.
- Status clear hanya boleh muncul bila kedua gap berada dalam tolerance.
- Tambahkan test dengan qty sama tetapi nilai berbeda.

### P1-03. Sidebar mempunyai dua sumber kebenaran

Registry database saat ini relatif sehat:

- Tidak ada menu aktif dengan parent hilang.
- Tidak ada menu aktif yang menunjuk page nonaktif.
- Tidak ada page aktif tanpa permission.
- Probe URL tidak menemukan 404 atau 500.

Namun application/views/layout/sidebar.php berukuran sekitar 46 KB dan masih:

- Mengganti ikon dari map hardcode.
- Memindahkan grouping menu.
- Menyisipkan workspace sintetis.
- Menggabungkan master, inventory, availability, component, dan POS saat
  runtime.
- Dapat menghasilkan item sintetis tanpa page_id.

**Dampak:** perubahan sys_menu belum tentu sama dengan sidebar yang terlihat.
Audit database dapat lulus tetapi hasil user berbeda.

**Perbaikan:**

1. sys_menu menjadi satu-satunya sumber struktur, urutan, label, ikon, parent,
   route, dan page.
2. View sidebar hanya merender tree yang sudah diotorisasi.
3. Hapus injection dan regrouping hardcode.
4. Menu tanpa page_id hanya boleh berupa group yang tidak dapat memanggil aksi.
5. Tambahkan validator registry pada CI dan halaman System Health.

### P1-04. Favorites dan menu tanpa page berpotensi melewati filter izin

Menu_model memfilter page-linked menu berdasarkan can_view. Namun:

- Menu tanpa page_id selalu dapat tampil.
- Favorites memeriksa aktif/nonaktif, tetapi belum selalu memverifikasi izin
  page efektif.
- Pin menu dapat menerima ID menu tanpa validasi permission.

**Perbaikan:** query favorites dan pin harus memakai resolver permission yang
sama dengan sidebar. Saat izin dicabut, favorite otomatis hilang.

### P1-05. Registry masih mempunyai duplikasi dan alias implisit

Temuan aktif:

- Dua menu aktif menuju /pos/reports/sales:
  Penjualan & Margin POS dan Laporan Penjualan POS.
- Lima menu aktif tidak mempunyai ikon:
  opname divisi bulanan, opname gudang bulanan, daily recon component,
  opening component bulanan, dan opname component bulanan.
- Ada collision sort pada root, POS, finance, loyalty, POS report, asset,
  inventory control, dan menu personal.
- Enam key route terminal/outlet didefinisikan ganda.
- Sepuluh page code controller tidak terdaftar, sebagian memang alias:
  schedules v2, my schedule, stock commit audit, availability, component lot,
  component reconcile, purchase account, division lot, opening, dan warehouse
  lot.
- My schedule menjadi fail-open karena guard hanya bekerja bila page ditemukan.

**Perbaikan:**

- Setiap endpoint memiliki page sendiri atau alias eksplisit di registry.
- Alias tidak boleh bergantung pada “kalau page tidak ada maka lanjut”.
- Tambahkan unique rule konseptual untuk URL kanonis dan sort sibling.
- Gabungkan dua laporan sales menjadi satu workspace bertab.
- Lengkapi ikon dan accessibility label.

### P1-06. Struktur menu perlu disederhanakan berdasarkan pekerjaan user

Usulan susunan utama:

1. Dashboard
2. POS & Customer
3. Purchase & Supplier
4. Inventory & Production
5. Finance
6. People: Attendance & Payroll
7. Asset
8. Reports & Audit
9. Master Data
10. System
11. My Workspace

Prinsip penyederhanaan:

- User operasional melihat pekerjaan harian lebih dulu.
- Halaman setup, rebuild, repair, integrity, dan reclassify masuk System atau
  Diagnostics, bukan berdampingan dengan transaksi harian.
- Satu objek bisnis memakai satu workspace dengan tab, bukan banyak menu yang
  mengulang filter dan tabel serupa.
- Halaman personal tetap terpisah dari halaman administrasi.

Usulan penggabungan:

| Rumpun saat ini | Workspace target |
| --- | --- |
| Live stock, daily matrix, daily recon, monthly stock | Kontrol Stok Harian dengan tab Live, Matrix, Recon, Riwayat |
| Opening manual dan generated | Saldo Awal dengan sumber dan status jelas |
| Component opening, opname, recon, lot | Kontrol Component dengan tab |
| Sales dan sales margin | Penjualan & Margin |
| PH eligibility, ledger, calendar, expiry | Public Holiday dengan tab Hak, Kalender, Saldo, Audit |
| Schedule legacy dan schedules-v2 | Pertahankan V2, arsipkan legacy setelah parity |
| Payroll setup yang tersebar | Setup Payroll |
| Payroll input/approval | Input & Persetujuan |
| Payroll run/disbursement | Proses & Pembayaran |
| Asset master/change/recon/incident | Siklus Aset dengan tab |
| Purchase rebuild/reclassify | System Diagnostics, hak khusus |
| Printer setting dan customer review | Tetap terpisah di sidebar; integrasi QR berada di setting printer |

### P1-07. UI belum memakai design system tunggal

Snapshot statis:

| Pola | Jumlah |
| --- | ---: |
| View PHP | 336 |
| View dengan style inline | 282 |
| View dengan script inline | 167 |
| View memakai Remix icon | 180 |
| File terkait modal | 79 |
| Pemanggilan alert native | 62 |
| Pemanggilan confirm native | 79 |
| Halaman dengan spinner/loading | 61 |
| Table responsive | 154 |
| Empty state | 98 |
| Penanda pagination | 56 |
| Filter dengan clear/reset eksplisit | 9 |

FinanceUI di assets/js/app.js sudah menyediakan alert, confirm, prompt, toast,
dan loading button. Masalahnya adalah adopsinya belum menyeluruh.

Dampak yang dirasakan user:

- Search dan clear filter berbeda antarhalaman.
- Tombol aksi kadang teks, kadang kotak kosong, kadang ikon tanpa tooltip.
- Warna merah dipakai untuk aksi utama maupun bahaya.
- Tab, card, badge, tabel, modal, pagination, dan empty state berbeda.
- Loading dan error tidak selalu terlihat.
- Lebar tabel dan modal tidak konsisten di layar kecil.
- Tampilan satu rumpun terasa dibuat oleh aplikasi berbeda.

**Perbaikan:** bangun Finance Design System, bukan memperbaiki halaman satu per
satu tanpa pola.

Komponen minimum:

- App shell dan page header.
- Breadcrumb dan action bar.
- Primary/secondary/danger button.
- Icon action dengan tooltip dan aria-label.
- Tab utama dan subtab.
- Filter bar responsive dengan apply dan clear.
- KPI/card ringkasan.
- Data table, sticky column, empty state, skeleton, error state.
- Pagination tunggal.
- CRUD modal, drawer, dan confirm dialog.
- Form field, validation, help text, date/currency/quantity input.
- Toast dan job progress.
- Print/preview container.
- Mobile table strategy.

Aturan visual:

- Satu set token warna, typography, radius, spacing, shadow, dan z-index.
- Merah solid hanya untuk brand primary atau danger yang jelas; jangan
  menggunakan satu warna untuk dua arti pada konteks yang sama.
- Ikon harus berasal dari satu keluarga dan selalu terlihat.
- Tombol ikon wajib mempunyai title, aria-label, focus state, dan ukuran klik
  minimum.
- Apply filter dapat berupa ikon search; clear dapat berupa ikon reset, tetapi
  maknanya harus muncul pada tooltip dan pembaca layar.
- Modal create/edit pada satu rumpun memakai susunan dan footer yang sama.
- Tabel memakai header, alignment angka, badge status, action column, dan
  pagination yang sama.
- Halaman tidak boleh menambahkan token warna baru tanpa alasan desain.

### P1-08. Branding dan pengaturan tenant belum terpusat

Ditemukan referensi hardcode Namua, alamat Magnolia/Kabongan, dan identitas
usaha di sekitar 49 file, termasuk login, printer, review, dokumen HR, menu book,
asset, landing, dan roastery.

sys_app_config saat ini lebih banyak berisi backup, dashboard, POS, replication,
dan tunnel. Belum ada profil aplikasi/tenant lengkap.

Pengaturan yang harus dapat dikelola:

- Nama aplikasi dan nama badan usaha.
- Nama outlet, alamat, kontak, domain, timezone, locale, dan mata uang.
- Logo utama, logo dokumen, favicon, watermark, dan warna brand.
- Header/footer dokumen, invoice, receipt, QR, dan kontrak.
- Identitas WhatsApp/email.
- Format nomor transaksi.
- Kebijakan fiskal, service, tax, rounding, dan accounting date.
- Tema UI yang terbatas pada preset aman.
- Feature entitlement sesuai paket.

Jangan menyimpan password, token, private key, atau DB credential di halaman
pengaturan biasa.

### P1-09. Kontrak runtime dan dependency tidak sesuai kode aktual

- composer.json masih menyatakan PHP minimal 5.3.7.
- Source sudah memakai sintaks dan kemampuan PHP 8.
- Composer CLI 2.0.14 tersedia dan `composer validate --no-check-publish`
  lulus, tetapi toolchain mengeluarkan deprecation warning.
- composer.lock tidak menjadi artefak rilis yang dapat direproduksi.
- Python dependency Printer Agent dan service pendamping perlu dikunci.

**Perbaikan:** tetapkan PHP 8.x yang didukung, extension wajib, MariaDB minimum,
Node/browser bila diperlukan, Python version, lockfile, health check installer,
dan compatibility matrix per release.

### P1-10. Test otomatis belum cukup untuk updater

Focused smoke suite sekarang sudah mencakup auth, POS, WhatsApp, Telegram, inventory,
formula, recipe, mapping, release boundary, dan area prioritas lainnya. Namun
coverage belum cukup untuk:

- Permission setiap endpoint dan role.
- PO, SR, receipt, fulfillment, reversal, dan cancel.
- Produksi, lot, HPP, rollover, deficit, adjustment, recon, dan void.
- POS order, bundle, extra, DP, payment, refund, void, reservation, dan printer.
- Finance mutation, backdate, reversal, dan reconciliation.
- Attendance, PH, schedule, payroll, dan disbursement.
- Asset lock/change/incident/recon.
- Installer, migration, update, rollback, dan license entitlement.
- Browser regression pada komponen UI utama.

Updater tidak boleh diaktifkan untuk customer sebelum critical path mempunyai
test otomatis lintas modul, browser/DB integration, migration, restore, dan
quality gate CI.

### P1-11. Repository belum dapat menjadi paket customer

Snapshot working tree:

- Folder backup sekitar 706 MiB.
- Upload sekitar 339,77 MiB.
- Sekitar 837 file menyerupai log masih tracked.
- Sekitar 13 file tmp/fix/probe masih tracked.
- Git pack lokal sekitar 7,66 GiB.
- Ada 12 tabel backup tanpa primary key di schema aktif.
- Collation masih campur: 262 general_ci dan 55 unicode_ci.
- Object Git yang hilang menghalangi pemeriksaan cached diff/status normal dan
  membuat packaging/commit dari index kumulatif belum aman.

**Perbaikan:**

- Pisahkan source, runtime data, upload, backup, log, cache, config, dan secret.
- Buat manifest file yang boleh masuk paket.
- Buat manifest tabel seed awal dan tabel yang harus kosong.
- Pindahkan zz_bak dan backup table keluar schema aktif setelah arsip aman.
- Normalisasi collation secara bertahap.
- Buat cleanup history repository hanya setelah backup dan clone verification.
- Pulihkan atau verifikasi object Git secara non-destructive sebelum commit,
  packaging, atau history rewrite.

## 6. Temuan Prioritas 2 dan Utang Historis

### P2-01. Payroll belum menjelaskan uang makan yang dibayar terpisah

Ditemukan 30 detail payroll yang nilai rincian dan net pay berbeda. Selisih
mengikuti meal_total pada contoh yang diperiksa.

Ini belum tentu salah hitung, tetapi UI dan slip harus menjelaskan:

- Gaji bersih payroll.
- Uang makan terpisah.
- Total hak pegawai.
- Total yang sudah dibayar per rekening/tanggal.
- Sisa kewajiban.

Tambahkan assertion agar selisih selain komponen yang memang dibayar terpisah
ditolak.

### P2-02. Riwayat saldo rekening membingungkan pada transaksi backdate

Saldo akhir akun saat ini benar dan chain berdasarkan urutan posting ID tidak
putus. Namun jika diurutkan berdasarkan tanggal bisnis, ditemukan 62
diskontinuitas before/after.

Perbaikan:

- Bedakan tanggal transaksi, waktu posting, dan accounting period.
- Riwayat utama memakai urutan posting untuk running balance.
- Tanggal bisnis tetap dapat difilter, tetapi diberi penjelasan backdate.
- Setelah periode ditutup, backdate membutuhkan reopen/approval.
- Sediakan rebuild saldo yang idempotent dan ter-audit.

### P2-03. Enam jadwal PH lama mendahului eligibility

Fadilla memiliki enam jadwal PH dari April sampai Agustus yang lebih awal dari
effective date eligibility 17 Agustus 2026.

Data ini harus direview sebagai data historis. Jangan mengubah otomatis tanpa
dokumen kebijakan dan bukti hak pada periode tersebut.

### P2-04. Receipt purchase lama belum lengkap

Pada transaksi posted awal Juni masih ditemukan:

- 30 receipt line tanpa material_id.
- 6 receipt line tanpa lot_id.

Writer baru tidak boleh meniru pola ini. Buat audit detail per receipt untuk
menentukan apakah line adalah non-stock, mapping material lama, atau lot yang
hilang. Repair hanya kasus yang dapat dibuktikan.

### P2-05. Status terminal beberapa order POS belum dinormalisasi

Contoh:

- Order id 3469, MSO-20260822114341-AA80, berstatus PAID/PENDING tanpa commit
  dan job.
- Beberapa order VOID masih mempunyai stock_commit_status FAILED atau PENDING,
  termasuk id 3549, 3551, 3553, 3653, dan 3887.

Sebagian dapat merupakan jejak kegagalan server lama, bukan bug writer aktif.
Buat status normalization audit yang membedakan:

- Order selesai secara bisnis.
- Commit stok selesai/reversed.
- Job terminal cancelled/success.
- Data yang benar-benar perlu replay.
- Data yang hanya perlu dinormalisasi status.

### P2-06. Public customer review memerlukan anti-spam

**Status Batch 153 (2026-09-05): fondasi script single-server selesai,
acceptance UAT belum selesai; bukan penutupan seluruh A1.**
Sebelumnya station QR menerima kiriman berulang tanpa limiter; input array dapat
memicu warning; respons member dapat membeberkan nama/nomor member dari input
nomor WhatsApp yang belum diverifikasi. Pendaftaran member juga dapat tertinggal
jika penyimpanan ulasan berikutnya gagal.

- `[x]` Limiter bersama lintas PHP worker pada satu server: 60 percobaan per IP
  per 10 menit dan 12 per sesi browser per 10 menit, termasuk input invalid.
- `[x]` Formulir bertanda tangan terikat sesi dan QR tujuan; honeypot; waktu
  minimum 2 detik dan masa berlaku 1 jam. Satu token hanya dapat dipakai sekali.
- `[x]` Cooldown pengiriman 60 detik per sesi dan identitas kontak/nota;
  ulasan identik pada QR dan nomor sama dibatasi 10 menit. Kegagalan penyimpanan
  memperpendek penahanan duplikat menjadi cooldown 60 detik agar dapat dicoba lagi.
- `[x]` Input scalar/UTF-8/batas ukuran/rating divalidasi sebelum writer.
  Nomor telepon dinormalisasi sebelum pembatasan; persetujuan penggunaan nomor
  wajib untuk semua pengirim station QR, bukan hanya member baru.
- `[x]` Respons tidak menampilkan profil/nomor member atau detail error internal.
  Nama yang dimasukkan tidak dianggap identitas terverifikasi. Pengiriman baru
  tidak menyimpan IP mentah/hash tanpa kunci atau user-agent ke tabel ulasan.
- `[x]` Pembuatan member dan ulasan station dibungkus transaksi yang sama.
  Kegagalan insert ulasan membatalkan pembuatan member baru. Token nota tetap
  memakai update bersyarat `OPEN` → `SUBMITTED`, tidak dapat ditimpa.
- `[x]` Diagnostik abuse dibatasi 200 event, disampling, dan dipangkas setelah
  24 jam pada akses berikutnya. Berisi alasan, IP yang diberi HMAC, serta ID
  internal review/member saat sukses; tanpa nomor telepon, isi ulasan, token,
  session ID, atau IP mentah. Relasi member/review dan catatan sumber pendaftaran
  tetap menjadi bukti bisnis di database; diagnostik bukan audit permanen.
- `[x]` Batch 153: moderasi sembunyikan/tampilkan, pengaturan QR struk,
  simpan QR area, dan aktif/nonaktif QR wajib permission edit + POST + token
  sesi khusus `pos_customer_review_csrf` melalui header `X-Pos-Review-Csrf`.
  Permintaan tidak sah berhenti sebelum membaca payload atau memanggil writer.
  Token dibuat setelah izin lihat dan dipertahankan untuk mendukung beberapa tab.
- `[x]` Semua empat aksi UI mengirim token ke origin yang sama, tidak melalui
  URL dan tidak mengikuti redirect. CSRF transaksi POS, APK, serta formulir
  publik tidak diubah atau dipakai sebagai pengganti token admin ulasan.
  Teks konfirmasi kini sesuai dengan aksi sembunyikan/tampilkan yang sebenarnya.
- `[ ]` UAT QR cetak/browser/perangkat sebenarnya dan evaluasi batas pengiriman
  di Wi-Fi bersama/proxy. CAPTCHA adaptif/OTP serta limiter multi-server adalah
  tindak lanjut bila pola abuse/deployment membutuhkannya, bukan sudah tersedia.

**Penggunaan:** pelanggan tetap memindai QR dan mengisi formulir. Jika terlalu
sering, halaman memberi waktu tunggu; formulir lama perlu dimuat ulang. Informasi
keanggotaan diarahkan ke kasir, tidak ditampilkan dari input nomor publik.
Jangan menganggap nomor yang diisi sebagai autentikasi member.

Admin tetap memakai **Ulasan Pelanggan** untuk moderasi/pengaturan QR. Sesudah
update Batch 153, muat ulang halaman yang sudah lama terbuka agar memperoleh
token admin. Jika sesi kedaluwarsa, login kembali dan muat ulang halaman.
Tidak ada perubahan sidebar, role, atau daftar izin bisnis.

**Operasional/deployment:**

- Tidak ada SQL baru. Runtime berada di
  `application/cache/customer-review-guard/`, terpisah dari source/package Git.
  Direktori harus dapat ditulis oleh user PHP-FPM. Staging memakai `www`:
  `install -d -m 0700 -o www -g www /www/wwwroot/finance/application/cache/customer-review-guard`.
  Pada server utama/customer, sesuaikan path aplikasi dan user PHP-FPM.
- `state.php` mode 0600 menyimpan kunci acak lokal dan state terbatas; jangan
  mencetak isinya, memasukkannya ke Git, atau membersihkannya sebagai cache biasa.
  File diawali PHP `exit`; probe HTTP staging mendapat body kosong, bukan isi
  state. Jika korup/tidak dapat ditulis/lock sibuk, pengiriman ditolak sementara
  (503), bukan melewati limiter. Perbaiki akses direktori terlebih dahulu.
- Kunci/state bukan credential DB dan tidak membutuhkan environment baru.
  Kehilangan state membatalkan form lama dan mereset jendela limiter; bila perlu
  recovery, pengelola harus mencatat alasan dan mengamankan diagnostik lebih dulu.
- IP memakai `CI_Input::ip_address()` dan konfigurasi proxy tepercaya aplikasi,
  bukan mempercayai header forwarding sembarang. Proxy yang belum dikonfigurasi
  dapat membuat banyak pengunjung berbagi bucket IP. Tidak ada perubahan trust
  proxy atau nginx pada batch ini. Untuk multi-server, gunakan backend limiter
  bersama; direktori lokal tiap node tidak memberikan batas global.
- Bukti: `tools/tests/public_customer_review_smoke.php` (43 pemeriksaan),
  empat request HTTP staging negatif, jumlah ulasan/member tetap, dan quality
  gate `parallel` lulus. Tidak melakukan repair/moderasi ulasan historis.
- Batch 153: `tools/tests/customer_review_admin_csrf_smoke.php` (179
  pemeriksaan, termasuk eksekusi JavaScript halaman yang benar-benar dirender)
  dan regression POS transaction CSRF 1.691 lulus. Uji HTTP admin tanpa login
  tetap ditolak melalui redirect autentikasi; ini bukan UAT pengguna berizin.

### P2-07. Availability rebuild log memerlukan retensi

885.829 baris dan sekitar 304,30 MiB menunjukkan log tumbuh terus.

Terapkan:

- Ringkasan harian untuk dashboard.
- Retensi detail terbatas.
- Archive/export bila dibutuhkan audit.
- Index sesuai query.
- Alert hanya pada kegagalan bermakna, bukan setiap event sukses.

### P2-08. Upload dan layanan pendamping memerlukan lifecycle produk

Setiap upload harus mempunyai allowlist MIME, ukuran, random path, antivirus bila
tersedia, access policy, retention, dan delete audit.

Worker, cron, Printer Agent, WhatsApp, tunnel, dan backup harus mempunyai:

- Install/uninstall.
- Start/stop/restart.
- Health check.
- Log rotation.
- Version.
- Recovery.
- Least privilege.
- Dokumentasi Windows dan Linux.

## 7. Target Arsitektur Sidebar, Page, Route, dan RBAC

Bagian ini adalah target teknis untuk menutup temuan audit. Ia tidak
menentukan paket lisensi atau FeatureGate komersial; keputusan tersebut hanya
dicatat di roadmap `_28` setelah fondasi ini lulus.

### 7.1 Satu registry kanonis

Buat satu resolver dengan alur:

1. Route dikenali.
2. Route menunjuk page code.
3. Page menunjuk module dan capability.
4. Role/user memberi action permission.
5. Outlet/division scope dihitung eksplisit.
6. Menu hanya tampil bila can_view.
7. Endpoint mengulangi pemeriksaan action di server.
8. Audit log menyimpan permission dan scope efektif.

Tidak boleh ada tiga definisi terpisah di route, controller, dan sidebar tanpa
validator.

### 7.2 Permission tidak cukup hanya CRUD

Tambahkan capability bisnis bila diperlukan:

- view
- create
- edit
- delete
- export
- approve
- post
- void
- refund
- reopen
- reconcile
- adjust
- reclassify
- rebuild
- manage_settings
- manage_access
- impersonate
- download_sensitive

Jika schema permission belum diperluas, buat action policy layer di atas CRUD
sebagai transisi. Jangan menyamakan edit dengan approve atau post.

### 7.3 Role baseline yang lebih aman

- Superadmin: system dan emergency access, semua aksi tercatat.
- Management: laporan lintas divisi dan approval, bukan writer teknis otomatis.
- Finance: rekening, settlement, reconciliation, payroll payment; bukan recipe.
- Warehouse: PO/SR/receipt/warehouse stock; bukan payroll atau finance account.
- HOD: jadwal dan operasi divisinya; tidak lintas scope.
- Cashier: POS, payment, reservation verification, reprint terbatas.
- Barista/Chef: order produksi divisinya, stock view/recon yang ditugaskan.
- HR: employee, attendance, PH, payroll input sesuai tugas.
- Staff: self service dan tugas eksplisit.
- Auditor: read/export dengan data sensitif yang disamarkan sesuai kebijakan.

### 7.4 Validator otomatis registry

Build harus gagal bila ditemukan:

- Route mutasi tanpa policy.
- Controller page code tidak terdaftar dan tidak menjadi alias eksplisit.
- Menu aktif tanpa ikon atau label.
- Menu action tanpa page_id.
- URL kanonis ganda.
- Sort sibling ganda.
- Page aktif tanpa owner/module/permission baseline.
- Favorite menuju page yang tidak lagi diizinkan.
- Role package drift dari baseline.

## 8. Target Finance Design System

Bagian ini menetapkan standar UI aplikasi agar bug usability dan regression
visual dapat diperbaiki konsisten. Rancangan white-label dan paket branding
customer berada di roadmap `_28`.

### 8.1 Artefak yang perlu dibuat

- Halaman component gallery internal.
- File token CSS tunggal.
- Library komponen view/JS.
- Panduan penggunaan dan contoh benar/salah.
- Checklist visual QA desktop, tablet, dan mobile.
- Screenshot regression untuk halaman penting.

### 8.2 Pola halaman standar

Urutan halaman:

1. Page header: judul, penjelasan singkat, primary action.
2. Ringkasan/KPI bila memang membantu keputusan.
3. Tab dan subtab bila satu objek mempunyai beberapa sudut pandang.
4. Filter bar.
5. Tabel/card data.
6. Pagination dan total data.
7. Empty/error/loading state.
8. Modal atau drawer untuk CRUD sederhana.
9. Halaman khusus untuk editor kompleks dan live preview.

### 8.3 Migrasi UI bertahap

Urutan, status, dan acceptance sembilan gelombang UI berada pada checklist
kanonis `AUD-A3-UI-01` sampai `AUD-A3-UI-09` di bagian 0.4. Fondasi primitive
dan shell, serta implementasi seluruh gelombang kode, telah lulus.
`A3-CODE-CLOSED` membatasi pekerjaan berikutnya pada bug yang dapat
direproduksi; Inventory/Production atau rumpun lain tidak boleh diulang hanya
karena catatan status lama. Visual UAT tetap belum selesai, sehingga A3 bukan
fase `DONE` dan bukan pekerjaan A5.

Jangan melakukan big-bang CSS rewrite. Migrasi per rumpun dengan visual
regression agar halaman produksi tidak rusak.

## 9. Pengaturan Sistem untuk Produk Siap Jual

Bagian ini dibatasi sebagai checklist technical boundary: apa yang boleh berada
di source, deployment, atau secret store. Detail productization, onboarding
customer, entitlement, dan halaman komersial menjadi tanggung jawab roadmap
`_28`.

Pisahkan tiga jenis konfigurasi.

### 9.1 Product/build configuration

Dikendalikan vendor:

- Product ID dan release version.
- Schema version.
- Build channel.
- Feature catalog.
- License public key.
- Compatibility matrix.
- Update manifest.

Customer tidak boleh mengedit bagian ini.

### 9.2 Tenant/business configuration

Dapat dikelola customer sesuai izin:

- Profil usaha dan outlet.
- Branding.
- Locale, timezone, currency.
- Format dokumen.
- Kebijakan bisnis.
- Default account dan mapping.
- Printer layout.
- Feature entitlement yang diterima dari license server.

### 9.3 Secret dan machine configuration

Berada di luar source dan database setting biasa:

- DB credential.
- Encryption key.
- License device key.
- API token.
- Printer Agent secret.
- SMTP/WhatsApp secret.
- Backup credential.
- Tunnel credential.

### 9.4 Generator instalasi

Untuk scope audit, yang perlu disiapkan hanya kontrak input teknis dan
acceptance criteria release. Implementasi generator dan Product Control Center
adalah pekerjaan komersialisasi di `_28`.

Release foundation harus menyediakan:

- Build aplikasi berdasarkan versi.
- Baseline schema.
- Seed referensi wajib.
- Config template.
- Migration bundle.
- Checksum/signature sebagai kontrak artefak.
- SBOM/dependency manifest.
- Bukti backup/restore dan upgrade/rollback.

`_28` kemudian menggunakan kontrak tersebut untuk paket fitur, akun onboarding,
service installer, entitlement, dan Product Control Center. `_30` tidak
membuat atau mengimplementasikan License Hub.

Data yang tidak boleh masuk installer:

- Transaksi Namua.
- Data pegawai/customer/vendor nyata.
- Upload bukti.
- Database dump.
- Log.
- Token.
- Password.
- Device ID.
- Backup.
- Cache.
- PID.

## 10. Roadmap Audit dan Perbaikan Aplikasi

Roadmap ini hanya mengatur pekerjaan teknis sampai aplikasi aman, konsisten,
teruji, dan siap diserahkan ke proses komersialisasi. Paket, harga, lisensi,
FeatureGate, Product Control Center, pilot, dan penjualan tidak diulang di
sini; semuanya berada di roadmap `_28`.

### Fase A0 — Baseline source, runtime, dan data

- `[x]` Hentikan perubahan source dari job backup.
- `[~]` Pisahkan backup, upload, log, cache, credential, dan data customer:
  index/package serta credential DB lulus; storage persisten/off-site customer
  masih perlu provisioning.
- `[~]` Pulihkan/review object Git dengan prosedur non-destructive: connectivity
  lulus; shallow history, baseline commit, dan temp pack masih terbuka.
- Tetapkan dependency/runtime support dan baseline health.
- Pastikan tidak ada fitur besar baru sebelum bug P0/P1 tertutup.

**Gerbang:** source dapat diaudit dan dibangun tanpa mengambil data runtime.

### Fase A1 — Security, RBAC, dan scope

- Tutup guard Master, Master Relation, POS Mobile, export, job, favorite, pin,
  rebuild, dan reclassify.
- Perbaiki role baseline, multi-role, outlet/division scope, dan action policy.
- Lengkapi secure cookie, session, login throttling, MFA/step-up, dan secret
  boundary production.
- Uji direct URL/API/APK dengan role yang berhak dan tidak berhak.

**Gerbang:** aksi tanpa izin selalu ditolak dan tidak mengubah data.

### Fase A2 — Integritas stok, HPP, dan transaksi

- `[x]` Koreksi/preview HPP dan VOID menjaga saldo kuantitas; repair mismatch
  historis tidak diotomatisasi dan diserahkan kepada pemilik data.
- `[x]` Rebuild cache HPP live berjalan setelah perubahan material, item, dan
  component serta setelah reversal POS.
- `[x]` Guard periode fail-closed dan barrier transaksi mencakup backdate,
  rollover, close, ledger, koreksi nilai, POS commit, refund, dan void.
- `[x]` Matrix A2 menjalankan 12 smoke terisolasi untuk invariant, writer,
  recon, produksi/formula, purchase/opening, dan lifecycle POS.
- `[x]` Status terminal POS historis diaudit tanpa replay otomatis.

**Gerbang:** transaksi baru menjaga invariant quantity, value, lot, HPP, dan
audit trail; data historis yang belum terbukti tidak dianggap selesai.

**Status aktif: `CODE_PASS + STAGING_PASS + DEFERRED_OWNER + UAT_PENDING`.**
Probe staging menunjukkan period table InnoDB dengan unique key
domain/bulan, periode aktif OPEN, lot OPEN negatif 0, HPP lot negatif 0,
defisit tersisa 0, dan queue availability gagal 0. Sebanyak 37 relasi order
terminal/nonterminal commit dan 26 snapshot aktif historis sampai 31 Agustus
dicatat tanpa replay. Browser/APK UAT dan keputusan data historis tetap child
terbuka sehingga A2 berstatus `OPERATIONAL_PENDING`, bukan `DONE`.

### Fase A3 — Navigasi dan UI operasional

- Jadikan registry page/menu/route sebagai sumber navigasi tunggal.
- Benahi favorite, alias, duplicate URL, sort, ikon, dan menu teknis.
- Terapkan design system bertahap pada POS, inventory, production, purchase,
  finance, people, asset, master, dan reports.

**Gerbang:** role utama melihat menu yang benar dan tugas harian memiliki pola
UI yang konsisten pada desktop serta mobile.

**Status aktif: `CODE_PASS + STAGING_PASS + UAT_PENDING`.** `A3-CODE-CLOSED`
berarti seluruh implementasi wave 8.3 telah ditutup dan tidak boleh diulang
tanpa temuan baru yang dapat direproduksi. A3.1 registry/navigation dan A3.2
telah lulus source/regression; visual UAT tetap belum selesai.
`sys_menu` menjadi sumber tunggal tree sidebar; favorite dan action
menu memakai permission resolver yang sama; alias page eksplisit tersimpan di
`sys_page_alias`; validator staging menunjukkan missing page/icon, duplicate
URL, sort collision, parent nonaktif, dan alias tidak valid semuanya 0.
Migration registry dan alias berhasil dijalankan dua kali; hash state fungsional
registry tetap identik pada pengulangan. Shell/design-system global tersedia
untuk seluruh rumpun, tetapi belum berarti setiap view lama sudah dimigrasikan.
Enam deklarasi route terminal/outlet yang identik
tidak diubah selama freeze APK; probe memastikan conflicting duplicate 0.
UAT visual browser pada viewport nyata dan UAT APK tetap menjadi validasi
operasional terpisah dan tidak diklaim lulus oleh smoke source. Checklist UAT
yang tersisa: Kasir/Barista, HR, Finance, dan Superadmin pada desktop serta
mobile; cek sidebar, tab panjang, filter, empty/loading/error state,
pagination, dan satu aksi bisnis yang memang berizin pada setiap rumpun.

**Struktur sidebar kanonis (Batch 210):** Dashboard → Penjualan & Pesanan →
Pelanggan, Member & Promo → Pembelian & Permintaan → Stok & Persediaan →
Produk & Produksi → Keuangan → SDM & Payroll → Aset → Master & Konfigurasi →
Administrasi & Audit → Integrasi & Notifikasi. POS kini dikelompokkan menjadi
Operasional Kasir, Channel & Antrean Pesanan, Pengaturan POS & Printer, dan
Laporan & Audit POS. Pengelompokan hanya mengubah struktur `sys_menu`; route,
registry halaman, dan RBAC tetap sama.

### Fase A4 — Automated quality gate

- `[x]` **A4.1:** runner deterministik menjalankan test di proses PHP terpisah,
  memiliki timeout dan ringkasan kegagalan terbatas, serta menyediakan profil
  `parallel`, `release`, dan `staging`. Probe staging mewariskan environment
  proses secara aman lalu menetapkan `CI_ENV=staging` secara eksplisit, sehingga
  CLI membaca contract konfigurasi privat staging tanpa mencetak credential.
- `[x]` **A4.2:** contract test POS Mobile inti diselaraskan untuk pengembangan
  APK paralel, termasuk sesi/draft/reader terminal cadangan, authorization, dan
  printer. Tidak ada source controller/model/route/view POS yang diubah.
- `[x]` **A4.3:** contract test permission, finance, purchase/SR, inventory,
  production, people/payroll/attendance, asset, Printer Agent, WhatsApp, dan
  migration/restore sudah masuk matriks 35 test dan lulus. Chrome headless nyata
  merender shell desktop/mobile dan HTTP runtime Printer Agent lulus melalui
  virtual environment terisolasi di luar document root.
- `[x]` **A4.4:** preflight release read-only sudah mengintegrasikan PHP lint,
  validasi JavaScript/Python, dependency metadata, secret scan tanpa membocorkan
  nilai, dan package allow/deny policy fail-closed. Contract hardening
  menutup pengurangan scope, wildcard exception, symlink, unreadable, dan file
  oversize. Composer dan dependency Python sudah dikunci secara reproducible;
  vulnerability gate OSV offline memverifikasi tiga lockfile terhadap snapshot
  Packagist/npm/PyPI yang berumur maksimal 48 jam. PHPStan memindai seluruh
  `application/` tanpa menjalankan CodeIgniter/DB; baseline telah diturunkan ke
  **0 finding**, sehingga setiap temuan statis berikutnya memblokir release.
  Builder menghasilkan tar deterministik di
  luar document root dengan daftar file tunggal, metadata ternormalisasi, dan
  manifest SHA-256; kegagalan gate tidak meninggalkan artefak final.

**Gerbang:** release candidate gagal otomatis bila test kritis, scan, atau
  invariant gagal.

**Status aktif: `TOOLING_PASS`; release customer tetap `BLOCKED(A0)`.**
Implementasi A4.1–A4.4 selesai. Matriks lintas modul tetap lulus 35/35;
profil `staging` Batch 191 meluluskan required 79/79, development 4/4,
deployment 1/1, runtime 2/2, preflight 1/1, security 1/1, static 1/1, dan
probe staging read-only 3/3. A4.3
sekaligus menutup CSRF mutasi Purchase/Store Request, atomicity mutasi
rekening serta tutup periode, lifecycle receipt PO, finalisasi status posting
component, render browser desktop/mobile, dan HTTP runtime Printer Agent. Test
migration/restore memakai fixture temporer dan tidak mengubah database.
Preflight A4.4 sudah deterministik. `composer.lock` tervalidasi dan dependency
Printer Agent memiliki direct-input serta transitive lock exact+SHA-256 yang
berhasil dipasang dari virtual environment kosong dengan `--require-hashes`.
Batch 146 memindahkan credential database staging ke file privat di luar source
tanpa mewajibkan environment PHP-FPM. Production tetap memakai resolver.
Deployment-secret contract 39/39 dan preflight workspace lulus dengan
0 finding; nilai rahasia tidak dicetak atau dimasukkan ke paket.
Advisory `mysql2` ditutup dengan lock 3.24.3; temporary `npm ci`, seluruh smoke
WhatsApp, npm audit produksi, dan OSV offline tiga lockfile lulus dengan 144
paket serta 0 advisory. Runtime security menggunakan OSV-Scanner 2.5.1 yang
checksum-pinned dan snapshot database di luar document root. Gate `release`
sebelumnya telah meluluskan runtime dan security; hasil terbaru setelah
pemisahan credential dicatat pada Batch 146.

Static contract, static scan seluruh `application/`, preflight contract, dan
artifact contract lulus. Dua build fixture dengan epoch sama byte-identik;
archive hanya berisi candidate set serta manifest dan seluruh checksum cocok.
Workspace staging kini lolos source preflight tanpa temuan credential. A4 tetap
selesai pada level tooling/contract, sedangkan release customer masih `BLOCKED`
oleh baseline commit/full history, rotasi secret, operasi installer, browser
runtime yang timeout, 80 finding static analysis di source baru, dan UAT role
browser serta APK/device/printer fisik.

### Fase A5 — Schema dan release foundation

- `[~]` Schema version registry, katalog checksum, dan migration runner
  deterministik sudah dimulai. Mode DB-free `validate`/`plan`, executor dengan
  lock/state/timeout, serta contract lulus. Delapan migration dikelola; policy
  upgrade menjalankan tujuh dan mengecualikan seed navigasi clean-install.
  Tujuh SQL lama tetap non-deployable dan disposition finalnya 1 baseline,
  4 enroll berbasis fingerprint exact, 1 replace, serta 1 retire. Replay file
  lama dan adopsi ledger palsu ditolak. Source pre-catalog tetap memerlukan
  bridge manual; updater customer lintas release belum UAT.
- `[~]` Bundle backup kini memiliki builder staging privat, manifest kanonis,
  hash/ukuran/katalog, permission ketat, publish atomik, durability sync, dan
  restore preflight tanpa jalur DB. `[x]` A5.11 sudah menjalankan restore nyata
  ke database/user disposable lokal: registry bootstrap dan replay menjadi
  `COMPATIBLE_V1`, fingerprint schema cocok `4/4`, sumber bundle tidak berubah,
  serta target dan credential sementara terhapus terverifikasi. `[x]` A5.12
  clean install schema/seed/bootstrap owner sudah lulus disposable. `[x]`
  A5.13 health check upgrade lulus pada staging dan rollback schema/ledger/seed
  lulus pada database disposable. `[x]` A5.14 mengunci matrix PHP/FPM,
  MariaDB, Node/npm, Python, Composer, extension, dan tiga dependency lock;
  probe staging lulus dengan dua warning lingkungan yang tercatat. `[x]`
  A5.15 menetapkan retention/lifecycle, checksum backup, dry-run, quarantine,
  audit, dan preflight database read-only. Purge database tetap fail-closed
  sampai archive/agregasi tersedia. `[x]` A5.16 mengikat artefak, manifest,
  source revision, migration catalog, package policy, dan runtime policy dengan
  Ed25519 serta verifier fail-closed sebelum install/update.
- `[x]` Versi PHP/MariaDB/extension/Node/Python dan dependency lock sudah
  mempunyai policy machine-readable serta contract/staging probe.
- `[x]` Builder artefak teknis generik sudah menolak backup, upload, log,
  secret, dan data customer; manifest versi, signature, provenance, dan
  rollback teknis tersedia. Delivery/installer customer tetap pekerjaan C3.

**Gerbang handoff:** aplikasi dapat dipasang dan dipulihkan secara berulang;
setelah gerbang ini lulus, pekerjaan paket/lisensi dilanjutkan di `_28`.

## 11. Urutan Backlog yang Disarankan

### Batch teknis berikutnya

1. Tutup `GAP-01`: credential produksi, rotasi secret, recovery Git, dan
   pemisahan runtime data customer tanpa melonggarkan preflight fail-closed.
2. Jalankan UAT `AUD-A1-SEC-02` secara kecil: dua tab mengubah formula,
   restore versi lama, proof sekali pakai/replay, role view-only, dan rollback
   kegagalan audit. Kontrak reauth APK tetap terpisah dan tidak memakai
   password dalam writer.
3. Uji command inbound `/menu`, `/omzet`, dan `/belanja` dari grup Namua;
   sebelum grup tidak tepercaya dipakai, tambahkan allowlist identitas pengirim.
4. Jalankan A3.2 rollout UI melalui `AUD-A3-UI-01`–`09` per rumpun; jangan
   menutup A3 sebelum visual UAT yang relevan lulus.
5. Pertahankan freeze pada `Pos_mobile.php` dan `routes.php` selama pekerjaan
   APK pemilik; perubahan shared `Pos_model.php` wajib kompatibel ke belakang.
6. Kontrak credential produksi tidak boleh dilonggarkan oleh konfigurasi
   staging yang memakai nilai langsung.
7. Browser/APK UAT A1 dan A2 dijalankan terpisah saat build APK siap.
8. Data mismatch dan anomali POS historis hanya diperbaiki atas keputusan
   pemilik, dengan preview, before/after, dan post-check; jangan replay otomatis.

Paket, lisensi, FeatureGate, Product Control Center, pilot, dan penjualan baru
masuk antrean setelah gerbang Fase A5 lulus dan dikerjakan berdasarkan `_28`.

## 12. Matrix Pengujian Wajib

Lembar tes pengguna terkonsolidasi: [98 checklist lintas modul, hasil yang diharapkan dan bukti regresi Batch 265](2026-09-16_checklist_uat_seluruh_perbaikan_finance.md). Gunakan ID skenario untuk laporan LULUS/GAGAL/BELUM DIUJI; hasil otomatis tidak mencentang UAT. Matrix di bawah tetap menjadi cakupan teknis, bukan pengganti penerimaan pengguna.

### RBAC

- Setiap role membuka seluruh menu yang diizinkan.
- Direct URL create/edit/delete/post/void ditolak bila tidak berhak.
- APK memakai policy yang sama.
- Multi-role tidak memperluas scope secara diam-diam.
- Favorite tidak membocorkan menu.
- Permission seed dapat mencabut hak lama.

### POS dan printer

- Draft, confirm, payment, DP, reservation, bundle, extra.
- Void sebelum/sesudah stock commit.
- Refund partial/full.
- Reprint dan tanyakan cetak.
- Printer offline, timeout, duplicate response.
- QR review.
- HPP dan stock commit idempotent.

### Purchase, SR, dan gudang

- PO create/approve/receive/return/cancel.
- SR request/approve/fulfill/partial/reject.
- Lot dan account mutation.
- Reversal dan period close.
- Non-stock line.
- Direct URL role test.

### Inventory dan production

- Material/component receive, transfer, production, sale, void.
- Adjustment plus/minus dan recon.
- Deficit create/settle/write-off.
- Month rollover.
- Qty and value reconciliation.
- Backdate/future/reopen.
- Concurrent writer.

### Finance

- Mutation, payment, reversal, backdate.
- Account balance and chain.
- Daily/gate reconciliation.
- Closed period.
- Export and sensitive field masking.

### Attendance, PH, dan payroll

- Schedule normal/PH/OFF.
- Attendance manual/location/request.
- Eligibility, grant, FIFO use, expiry.
- Monthly day limit and exception approval.
- Payroll input, run, meal payment, disbursement, reversal.
- Employee scope and privacy.

### Asset

- Open data entry.
- Bulk lock.
- Change request.
- Incident, repair, lost, retire.
- Monthly recon.
- Audit history.

### Installer dan update

- Fresh install.
- Seed repeat.
- Upgrade N ke N+1.
- Failed migration rollback.
- Backup and restore.
- License offline/online.
- Feature entitlement.
- Customer data preservation.
- Source package excludes secret, dump, upload, and log.

## 13. Keputusan Arsitektur yang Tidak Boleh Berubah Diam-diam

1. Satu source code untuk seluruh paket.
2. Paket fitur memakai entitlement, bukan source fork manual.
3. Server selalu mengulang pemeriksaan permission.
4. Menu bukan mekanisme keamanan.
5. sys_menu/page registry menjadi sumber navigasi tunggal.
6. Lot/FIFO menjadi sumber kuantitas dan nilai persediaan.
7. Defisit tidak sama dengan mismatch.
8. Repair historis harus preview, preflight, transaksi, audit, dan post-check.
9. Backup tidak boleh berada di repository source.
10. Secret tidak boleh berada di source atau paket customer.
11. Update harus signed, versioned, tested, dan dapat rollback.
12. Branding customer tidak boleh membutuhkan edit source.
13. Data awal installer bukan dump database operasional.
14. Scope multi-role harus eksplisit.
15. Tampilan baru harus memakai design system.

## 14. Gerbang Handoff ke Roadmap Komersialisasi

Audit ini dinyatakan selesai pada level fondasi bila:

- seluruh child wajib pada control board berstatus `DONE`; status
  `CODE_PASS`, `STAGING_PASS`, `DEFERRED_OWNER`, atau `BLOCKED` tidak boleh
  dinaikkan diam-diam menjadi selesai;

- seluruh aksi sensitif yang masuk scope audit memiliki guard server-side;
- role, multi-role, outlet, dan division scope lulus negative test;
- mismatch quantity/value memiliki hasil repair atau keputusan tertulis yang
  dapat diaudit;
- HPP live, lot/FIFO, POS snapshot, dan koreksi/VOID lulus UAT yang relevan;
- navigasi kanonis dan invariant registry tidak lagi memiliki bypass jelas;
- critical path memiliki regression test dan database invariant;
- runtime/dependency, schema, backup/restore, dan package foundation dapat
  diulang tanpa membawa data customer atau secret;
- risiko yang tersisa sudah dicatat sebagai batas produk dan disetujui owner.

Kriteria siap jual, paket, lisensi, entitlement, pilot, dan operasi penjualan
ditetapkan serta dinyatakan lulus hanya di dokumen `_28`.

## 15. Penutup

Dokumen `_30` menjadi daftar masalah aplikasi dan urutan perbaikannya. Setiap
perubahan kode harus mempunyai bukti lint/test/query/UAT dan dicatat pada
execution log atau laporan modul. Setelah fondasi teknis lulus, dokumen `_28`
menjadi pegangan tunggal untuk productization dan komersialisasi.
