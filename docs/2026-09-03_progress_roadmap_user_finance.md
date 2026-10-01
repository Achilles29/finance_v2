# Laporan Progres Roadmap Finance — Bahasa Pemilik Usaha

Tanggal penilaian: 5 September 2026

Pembaruan: status terakhir mencakup Batch 69–146. Dokumen ini adalah laporan
progres per modul, bukan roadmap ketiga. Pegangan utama tetap `_30` untuk
audit/perbaikan aplikasi dan `_28` untuk komersialisasi/lisensi.
Status pada laporan ini hanya snapshot; bila berbeda, control board `_30`
selalu berlaku.

Dokumen pembanding:

- [Roadmap komersialisasi dan lisensi](2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md)
- [Audit total aplikasi dan roadmap pengembangan](2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md)
- [Handoff pekerjaan Codex Batch 01–68](2026-09-03_codex_handoff_b01-b68.md)
- [Execution log Batch 01–146](2026-09-02_codex_execution_log.md)
- [Runbook SQL server](2026-09-03_codex_server_sql_runbook_b01-b68.md)

## Kesimpulan untuk Pemilik Aplikasi

Finance **belum siap dijual ke customer**. Pekerjaan yang sudah dilakukan
sebagian besar adalah pengamanan fondasi aplikasi, bukan pembuatan paket
produk komersial.

Status tidak lagi dihitung dari persentase kasar. Control board memisahkan
implementasi, validasi, dan readiness data/release:

- A4 sudah `TOOLING_PASS`, tetapi release tetap diblokir A0.
- A2 `OPERATIONAL_PENDING` karena data owner dan UAT belum selesai.
- A3 `PARTIAL` karena rollout UI 8.3 dan visual UAT belum selesai.
- A0, A1, A3, dan A5 masih `PARTIAL`; C0–C5 belum lulus.
- Kriteria “siap dijual” tetap dinilai di dokumen `_28`; belum ada bukti seluruh
  kriteria tersebut telah dipenuhi dan ditandatangani.

Batch 68, 69, dan 70 mendapat penilaian PASS/bersyarat dari auditor pada level
batch. Batch 69–70 menambah workflow koreksi nilai HPP dan VOID, bukan repair
otomatis enam component. Itu berarti batch tersebut lulus pemeriksaan kode dan
smoke, bukan berarti seluruh aplikasi atau database sudah siap dijual.

## Apa yang Sudah Diperbaiki

Berikut penjelasan dalam bahasa pekerjaan sehari-hari.

| Area | Hasil yang sudah dikerjakan | Kondisi saat ini |
| --- | --- | --- |
| Hak akses pengguna | Banyak alamat dan tombol perubahan penting sekarang memeriksa hak pengguna di server. Pengguna tanpa hak ditolak meskipun mencoba membuka alamat langsung. | Sebagian besar jalur prioritas sudah diperketat, tetapi belum semua jalur, role, API, dan aplikasi mobile diuji lengkap. |
| Role dan pembagian area kerja | Penghapusan role diperbaiki agar tidak salah menghapus izin. Role nonaktif tidak lagi ikut memberi izin. Pengguna tanpa satu area kerja yang jelas ditolak. | Perbaikan dasar sudah ada, tetapi daftar izin Kasir, Barista, multi-role, outlet, dan divisi masih perlu dirapikan dan diuji ulang. |
| POS dan POS Mobile | Aksi penting sudah diberi pemeriksaan hak akses dan wajib POST. Batch 89a–89f memulihkan binding endpoint inti. Batch 105 menggabungkan format cetak APK serta panel order masuk ke web tanpa mengganti model cache A2. Batch 107 menyelaraskan test sesi/draft/reader untuk terminal cadangan yang aman. | Surface API tambahan, step-up approval, serta uji akun dan APK/device/printer nyata masih terbuka. |
| Backup dan runtime | Backup tidak lagi push ke source atau menghapus file lama langsung. Batch 146 juga melepas 1.367 upload, dump, log, `.env`, output sementara, dan bytecode dari index Git tanpa menghapus file server. | File runtime staging tetap utuh; off-site terenkripsi, full history/baseline commit, dan operasi customer masih perlu ditutup. |
| Keamanan konfigurasi | Credential database staging kini berada di file privat luar project; source `database.php` bebas password dan production tetap memakai resolver. PHP-FPM serta koneksi web lulus. | Rotasi rahasia lama, kebijakan cookie production, MFA, dan secret store customer masih belum lengkap. |
| System Tools | Pengguna dengan hak View saja kini hanya melihat status umum. Path server, nama dump, log, detail replication, dan konfigurasi teknis memerlukan hak Export; password tidak pernah dikirim ke halaman. Test database tidak lagi menaruh password di URL. | Hak Export perlu diberikan hanya kepada teknisi tepercaya. Operasi failover tetap memerlukan prosedur dan UAT server nyata. |
| Printer lokal | Batas kepercayaan Printer Agent dan konfigurasi provisioning diperketat. | Installer layanan, pairing perangkat, dan uji pemulihan di komputer customer belum dibuktikan. |
| POS, reservasi, order, dan pekerjaan latar belakang | Banyak aksi transaksi, reservasi, self-order, online order, stock-live, dan pekerjaan otomatis diberi perlindungan request dan hak akses. | Uji database nyata, browser, perangkat, retry, dan kondisi server lambat masih diperlukan. |
| WhatsApp | Aksi kirim, jadwal, broadcast, retry, pengaturan, dan komunikasi dengan service pendamping diberi pembatasan hak dan penyamaran log. | Pengiriman nyata, pencegahan kirim ganda, monitoring, serta uji saat service atau jaringan bermasalah belum ditutup. |
| Telegram Bot | Modul internal, panduan, Setup Assistant, credential privat, PHP-FPM, cron, target Namua, webhook, dan bot @cacacia_bot sudah aktif. Pesan langsung, antrean worker, serta notifikasi Codex berikut ringkasan hasil berhasil diterima grup. Ringkasan hanya mengambil jawaban akhir dan menyaring pola sensitif. | Masih perlu UAT perintah masuk `/menu`, `/omzet`, dan `/belanja`. Gunakan hanya grup internal tepercaya sampai allowlist pengirim ditambahkan. |
| Produksi, resep, formula, extra, bundle, dan HPP | Dashboard membedakan selisih jumlah/nilai; koreksi HPP dan VOID tidak mengubah quantity; perubahan material/item/component membangun ulang cache HPP live setelah commit. Period, backdate, rollover, POS commit/reversal juga memakai barrier transaksi. | Script A2 lulus. Selisih nilai historis tetap pekerjaan data pemilik dan tidak diperbaiki otomatis. |
| Mapping Extra Group | Validasi data, duplicate, rollback, konflik dua pengguna, dan jalur perubahan bersamaan sudah diperkuat. Batch 66–68 lulus smoke test dan review auditor. | Pemeriksaan database live, uji dua browser nyata, dan arah perubahan Extra → Group belum dibuktikan. |
| Pengujian | Runner deterministik mencakup A1–A5. Batch 145 mengunci SQL legacy; Batch 146 menambah kontrak runtime/index Git dan membuat source preflight lulus 0 finding. | Release customer tetap menunggu `fileinfo` WhatsApp, Composer build baru, updater, baseline Git, rotasi secret, database archive activation, dan UAT perangkat. |
| Navigasi dan tampilan dasar | A3.1 registry/sidebar/alias dan fondasi UI global sudah lulus staging/smoke. Dashboard internal `/audit/roadmap` menampilkan status `_30` dan `_28` dalam lima tab yang responsive. | A3.2 rollout sembilan gelombang UI dan visual UAT masih terbuka; A3 belum `DONE`. Dashboard hanya aktif pada staging internal untuk superadmin dan tidak masuk paket customer. |

## Perbandingan dengan Fase Roadmap

Tabel ini adalah snapshot bahasa pengguna. Status kanonis dan child checklist
berada pada bagian 0 dokumen `_30`.

### `_30` — Audit dan perbaikan teknis

| Fase | Fokus | Status kanonis | Ringkasan |
| --- | --- | --- | --- |
| A0 | Baseline source, runtime, data, dan repository | `PARTIAL` | Credential DB dan runtime index/package sudah dipisahkan; clone masih shallow, perubahan belum menjadi baseline commit, rotasi secret dan off-site customer terbuka. |
| A1 | Security, RBAC, dan scope | `PARTIAL` | System Tools sensitif sudah ditutup; endpoint sisa, role/scope nyata, step-up/MFA, dan UAT tetap terbuka. |
| A2 | Stok, HPP, lot, dan integritas transaksi | `OPERATIONAL_PENDING` | Kode, 12 smoke, dan probe staging lulus; data historis `DEFERRED_OWNER` dan UAT belum lulus. |
| A3 | Navigasi dan UI operasional | `PARTIAL` | A3.1 registry dan fondasi UI lulus; A3.2 rollout 8.3 serta visual UAT belum selesai. |
| A4 | Automated quality gate | `TOOLING_PASS` | Tooling lulus; release nyata tetap `BLOCKED(A0)`. |
| A5 | Schema dan release foundation | `PARTIAL` | A5.1–A5.16 dan disposition SQL legacy lulus; updater customer lintas versi, database archive activation, recovery A0, dan delivery customer masih terbuka. |

### `_28` — Komersialisasi dan lisensi

| Fase | Fokus | Progres | Status |
| --- | --- | ---: | --- |
| C0 | Handoff dan keputusan go/no-go | 0% | `[ ]` menunggu gerbang A0–A5 dari `_30`. |
| C1 | Paket, harga, kontrak, dan katalog fitur | ±20% | `[~]` keputusan konsep ada; katalog machine-readable, harga final, dan EULA/SLA belum siap. |
| C2 | Productization customer dan onboarding | 0% | `[ ]` branding/profil usaha generik belum dibangun. |
| C3 | Installer customer dan delivery update | 0% | `[ ]` menunggu release foundation teknis. |
| C4 | License Hub, entitlement, terminal, dan APK | 0% | `[ ]` belum dibangun. |
| C5 | Pilot berbayar dan operasi penjualan | 0% | `[ ]` belum dimulai. |

**Kesimpulan fase:** hanya tooling A4 yang sudah `TOOLING_PASS`. A2 masih
`OPERATIONAL_PENDING`; A0, A1, A3, dan A5 masih `PARTIAL`. `_28` berjalan
setelah handoff atau paralel hanya pada spesifikasi non-mutatif.

## Temuan Audit yang Sudah Terjawab dan yang Belum

### Sudah dijawab sebagian atau cukup untuk batch tertentu

- P0-05: penghapusan izin role yang salah kolom sudah diperbaiki dan diuji.
- P0-07: backup tidak lagi memakai push ke source main.
- Bagian P0-01 sampai P0-04: banyak endpoint Master, Master Relation, POS
  Mobile, scope divisi, dan role nonaktif sudah diperketat.
- Bagian P0-06: secret boundary, CSRF, login throttle, session audit, dan
  beberapa konfigurasi produksi sudah ditangani.
- P0-09: batas kepercayaan Printer Agent sudah diperbaiki sebagian.
- P1-02: dashboard component sekarang memeriksa jumlah dan nilai.
- P1-03 sampai P1-07 pada scope A3: registry database menjadi sumber sidebar,
  favorite mengikuti hak efektif, alias page eksplisit, duplikasi kanonis
  dibersihkan, dan fondasi UI global tersedia.
- Bagian P1-10: automated quality gate A4 sudah selesai, termasuk regression
  lintas modul, browser/Printer Agent runtime, vulnerability, dan static scan.

### Masih terbuka dan harus masuk antrean berikutnya

- P0-01 sampai P0-04 belum boleh dianggap selesai sebelum semua endpoint,
  role, outlet/divisi, API, APK, dan direct URL lulus uji negatif.
- P0-06 masih membutuhkan secure cookie, MFA/step-up, rotasi secret, dan
  secret store yang benar untuk setiap instalasi.
- P0-08: fondasi migration runner, registry, fingerprint, backup, dan disposable
  restore sudah lulus; katalog 7 SQL lama yang masih berada di root `sql/`
  belum seluruhnya masuk jalur managed migration untuk clean install/update.
- P1-01 sisi script sudah selesai; mismatch component yang tersisa adalah
  pekerjaan data pemilik dan tidak menghalangi penutupan A2.
- Modernisasi detail view lama di atas design system A3 tetap dilakukan
  bertahap; ini bukan lagi pembuatan sumber navigasi atau token kedua.
- P1-08: branding dan profil tenant belum terpusat; identitas lama masih ada
  di banyak tempat.
- P1-09: dependency dan matrix runtime staging sudah reproducible serta diuji;
  qualification runtime customer, `fileinfo` WhatsApp, dan Composer build yang
  lebih baru belum selesai.
- P1-10 sampai P1-11: quality gate, clean install/upgrade/rollback, builder
  paket generik, serta signature/provenance sudah tersedia; updater dan paket
  customer nyata belum.
- Utang P2, installer, updater, lisensi, pilot, support, kontrak, dan operasi
  penjualan belum selesai.
- Repository Git mempunyai object yang hilang. Ini harus ditangani terpisah
  dengan prosedur recovery aman sebelum packaging atau commit final.

## File yang Sudah Diubah

### Perubahan terakhir yang paling relevan

Batch 100–104 menutup fondasi A3.1 dan sebagian A3.2: registry/sidebar
database-only, favorite/RBAC fail-closed, alias page eksplisit, fondasi UI
global, dan gate route collision. Migrasi seluruh halaman pada A3.2 belum
selesai sehingga fase A3 tetap `PARTIAL`.
Tidak ada perubahan A3 pada `Pos_mobile.php`, `Pos_model.php`, atau `routes.php`.
Batch 105 kemudian menggabungkan perubahan backup APK secara terarah ke
`Pos_mobile.php` dan `cashier_index.php`; `Pos_model.php` aktif dipertahankan.
Batch 106–107 hanya menambah/memperbaiki runner serta smoke test; empat source
POS/APK yang dilindungi tetap byte-identik selama A4.1–A4.2.
Batch 97 tetap menjadi perubahan shared web/APK terakhir pada reversal/cache;
Batch 98 menutup race period dan Batch 99 menutup gerbang A2.

Batch 144 menambah verifier dan signer Ed25519 untuk artefak release, kontrak
uji fail-closed, extension sodium pada matrix runtime, serta panduan pemisahan
build/sign/install. Private key produksi dan artefak customer tidak dibuat;
empat file POS/APK yang dilindungi tetap byte-identik.

File utama A3:

- application/controllers/Sidebar.php
- application/controllers/My.php
- application/core/MY_Controller.php
- application/models/Menu_model.php
- application/views/layout/sidebar.php
- application/views/layout/main.php
- application/views/sidebar/manage.php
- assets/css/theme-custom.css
- sql/2026-09-04a_a3_navigation_registry_canonicalization.sql
- sql/2026-09-04b_a3_page_alias_registry.sql
- tools/tests/a3_*.php

Catatan historis Batch 68 hanya mengubah dua file berikut:

- application/controllers/Master_relation.php
- tools/tests/master_relation_product_extra_mutation_csrf_smoke.php

Batch 69–70 juga mengubah service, controller, model, view, route, dan smoke
test koreksi nilai seperti yang dicatat lengkap pada execution log.

Batch 68 tidak mengubah database, route, view, migration, atau data. Tidak ada
SQL B68.

### Kelompok file dari pekerjaan Batch 01–68

Pekerjaan sebelumnya juga menyentuh kelompok berikut:

- Controller/model hak akses, POS, Purchase, System Tools, WhatsApp, Dashboard,
  Production, dan Master Relation.
- View POS, inventory, purchase, system, WhatsApp, dashboard, production, dan
  master relation.
- Smoke test untuk hak akses, backup, Printer Agent, POS, WhatsApp, formula,
  recipe, bundle, mapping, dan runtime.
- Script backup, Printer Agent, wrapper preflight, serta service wa-engine.
- Dokumen kontrak runtime, secret, Printer Agent, preflight mapping, dan
  runbook SQL.
- SQL migration yang tercatat di bagian berikutnya.

Daftar lengkap file historis yang tercatat berubah—131 path—tersedia di bagian
manifest pada [handoff Batch 01–68](2026-09-03_codex_handoff_b01-b68.md). Daftar
tersebut adalah catatan historis, bukan izin untuk memasukkan semua file ke
satu release. Packaging harus memakai whitelist batch yang telah disetujui.

Dokumen laporan ini sendiri menambah:

- docs/2026-09-03_progress_roadmap_user_finance.md
- pembaruan link pada docs/README.md

## Status SQL dan Deployment

Register kanonis tiga belas file SQL aktif, status staging, urutan, dan tindakan
server utama hanya berada pada bagian 0.5 dokumen `_30`. Laporan ini tidak
mengulang instruksi eksekusi agar dua daftar tidak berbeda lagi.

Aturan ringkasnya:

- SQL implementasi baru yang aman harus melalui backup/preflight, dijalankan
  dan divalidasi di staging, lalu dicatat; server utama tetap dijalankan pemilik.
- SQL repair data historis/destructive tidak dijalankan otomatis.
- Jangan menjalankan seluruh folder `sql/` atau file `sql/_old/` sekaligus.
- Migration runner saat ini mengelola enam file: `2026-09-04c`,
  clean-install-only `2026-09-05d`, repeat-safe `2026-09-05e`, lalu
  `2026-09-05a`–`2026-09-05c`.
- Tujuh SQL legacy tidak boleh dijalankan: disposition finalnya 1 baseline,
  4 enroll berbasis fingerprint, 1 replace, dan 1 retire.

## Pekerjaan yang Harus Dilakukan Berikutnya

Urutan yang paling masuk akal berdasarkan kedua roadmap:

1. Lanjutkan `GAP-01`: tetapkan baseline commit/full-history recovery dan
   lakukan rotasi secret saat cutover terjadwal; jangan menghapus runtime lama.
2. Uji command Telegram inbound dari Namua dan tambahkan otorisasi pengirim
   sebelum bot digunakan pada grup yang anggotanya tidak seluruhnya tepercaya.
3. Jangan mengubah `Pos_mobile.php` atau `routes.php` selama APK pemilik masih
   dikerjakan; shared `Pos_model.php` hanya boleh diubah secara kompatibel.
4. Kembali ke sisa A1 non-mobile serta utang bisnis A2 yang belum mendapat
   batch khusus; mobile/UAT menunggu build APK siap.
5. Kerjakan rollout UI A3.2 per rumpun dan catat tiap gelombang pada control
   board; jangan menutup A3 sebelum visual UAT lulus.
6. Repair data mismatch/anomali historis hanya atas keputusan pemilik dengan
   preview, before/after, dan post-check; jangan replay otomatis.
7. Setelah `_30` lulus, mulai C0–C5 pada roadmap `_28`.

**Status akhir:** Finance lebih aman daripada kondisi awal audit, tetapi masih
berada pada tahap penguatan fondasi. Belum ada dasar yang cukup untuk menyebut
produk ini siap dijual atau menjalankan deployment komersial penuh.
