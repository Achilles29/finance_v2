# Cutoff Git dan Matriks SQL Deployment

Dokumen ini menetapkan batas pelacakan setelah rangkaian audit/perbaikan Finance.
Ia bukan roadmap ketiga: status pekerjaan tetap berada di dokumen audit `_30`
dan status komersialisasi tetap berada di roadmap `_28`.

## 1. Cutoff yang Berlaku

- Titik sebelum rangkaian perubahan: commit
  `677078143b16e8151764ee20738b696f86547cb7`.
- Titik sesudah rangkaian perubahan: tag lokal
  `finance-audit-cutoff-2026-09-05`.
- Branch kerja: `main`.
- Commit dan push adalah dua operasi terpisah. Cutoff dibuat sebagai commit/tag
  lokal terlebih dahulu; push hanya dilakukan atas perintah pemilik.
- Clone saat cutoff masih shallow. Tag tetap memberi batas diff yang pasti pada
  source lokal, tetapi pemulihan full history dari remote masih pekerjaan
  terpisah.

Perintah pemeriksaan setelah tag dibuat:

```bash
git diff --name-status 677078143b16e8151764ee20738b696f86547cb7..finance-audit-cutoff-2026-09-05
git diff --name-only --diff-filter=A 677078143b16e8151764ee20738b696f86547cb7..finance-audit-cutoff-2026-09-05
git diff --name-only --diff-filter=M 677078143b16e8151764ee20738b696f86547cb7..finance-audit-cutoff-2026-09-05
git diff --name-only --diff-filter=D 677078143b16e8151764ee20738b696f86547cb7..finance-audit-cutoff-2026-09-05
git diff --name-status 677078143b16e8151764ee20738b696f86547cb7..finance-audit-cutoff-2026-09-05 -- sql tools/db
```

Arti status: `A` file baru, `M` file berubah, `D` file dihapus, dan `R` file
dipindah/diubah nama. Payload runtime yang dilepas dari index Git tetap ada di
server dan sengaja tidak ikut paket source.

## 2. SQL untuk Server Utama yang Sudah Berisi Data

Jangan menjalankan seluruh folder `sql/` secara berurutan atau manual. Gunakan
migration runner dengan policy `upgrade`; runner memeriksa urutan, dependency,
checksum, dan ledger.

Rencana yang berlaku:

| Urutan | File managed | Tindakan server utama |
| ---: | --- | --- |
| 1 | `sql/2026-09-04c_a5_schema_migration_registry_foundation.sql` | Jalankan melalui runner; membuat registry migration bila belum ada. |
| 2 | `sql/2026-09-05e_whatsapp_safe_reference_seed.sql` | Jalankan melalui runner; seed aman dan repeat-safe. |
| 3 | `sql/2026-09-05a_telegram_bot_foundation.sql` | Jalankan melalui runner; schema/menu/RBAC Telegram. |
| 4 | `sql/2026-09-05b_telegram_setup_guide.sql` | Jalankan melalui runner; halaman panduan Telegram. |
| 5 | `sql/2026-09-05c_telegram_safe_activation_default.sql` | Jalankan melalui runner; default Telegram tetap aman/OFF bila belum diatur. |
| 6 | `sql/2026-09-06a_component_formula_version_history.sql` | Jalankan melalui runner; history Formula Component append-only. |
| 7 | `sql/2026-09-06b_component_formula_restore_action.sql` | Jalankan melalui runner; mengaktifkan aksi restore Formula terotorisasi. |
| 8 | `sql/2026-09-06c_pos_mobile_reversal_step_up.sql` | Jalankan melalui runner; proof Void/Refund POS Mobile. |
| 9 | `sql/2026-09-06d_pos_mobile_reprint_step_up.sql` | Jalankan melalui runner; proof Reprint POS Mobile setelah 06c. |

Urutan aktual ditentukan oleh angka `order` pada
`tools/db/migration_catalog.json`; tabel di atas menjelaskan dependency bisnis,
bukan instruksi untuk mengeksekusi file dengan `mysql < file.sql`.

Preflight yang aman:

```bash
php tools/db/migration_runner.php validate
php tools/db/migration_runner.php plan --policy=upgrade
```

Apply memakai file credential MySQL privat dan file privat yang hanya berisi
nama database:

```bash
php tools/db/migration_runner.php apply --policy=upgrade \
  --defaults-extra-file=/path/private/mysql-client.cnf \
  --database-name-file=/path/private/database-name
```

Server existing yang dibuat sebelum ledger `finance-managed-v1` dapat berhenti
fail-closed dan membutuhkan bridge/fingerprint terkontrol. Jangan mengakali
dengan memasukkan receipt palsu atau memutar ulang SQL legacy.

## 3. SQL untuk Customer Baru

Database customer baru memakai alur berikut:

1. Import schema-only
   `sql/baseline/2026-09-05_clean_install_schema.sql` ke database kosong.
2. Jalankan migration runner policy `clean_install`.
3. Jalankan health check, login bootstrap owner, dan acceptance test sesuai
   artefak release yang sama.

Policy `clean_install` menjalankan sepuluh migration managed, termasuk
`2026-09-05d_a5_clean_install_reference_seed.sql`. File `09-05d` khusus
database kosong dan tidak boleh dijalankan pada server utama/customer existing.

```bash
php tools/db/migration_runner.php validate
php tools/db/migration_runner.php plan --policy=clean_install
php tools/db/migration_runner.php apply --policy=clean_install \
  --defaults-extra-file=/path/private/mysql-client.cnf \
  --database-name-file=/path/private/database-name
```

Customer existing yang menerima update memakai policy `upgrade`, sama seperti
server utama, bukan baseline dan bukan policy `clean_install`.

## 4. SQL yang Tidak Boleh Dijalankan sebagai Deployment Baru

Tujuh file berikut dipertahankan hanya untuk bukti historis/disposition dan
berstatus `DO_NOT_RUN`:

- `sql/2026-08-15b_wa_report_schedule.sql`
- `sql/2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql`
- `sql/2026-09-02a_wa_report_schedule_claim_lease.sql`
- `sql/2026-09-03a_auth_login_throttle_foundation.sql`
- `sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql`
- `sql/2026-09-04a_a3_navigation_registry_canonicalization.sql`
- `sql/2026-09-04b_a3_page_alias_registry.sql`

Semua file di `sql/_old/`, SQL repair historis, dan probe sementara juga tidak
boleh dijalankan massal. Repair data hanya dilakukan dengan preview, backup,
persetujuan pemilik, dan rekonsiliasi before/after.

## 5. Data dan Rahasia yang Tidak Masuk Commit

- dump database, log backup, upload, PDF/output, cache, PID, dan file runtime;
- `.env` aktif serta credential database/Telegram/WhatsApp;
- state editor dan state internal agent lokal;
- private signing key dan credential deployment customer.

File fisik runtime staging tidak dihapus saat dilepas dari index. Konfigurasi
database staging aktif berada di boundary privat server, bukan di repository.

## 6. Aturan Kandidat Artefak Release

Builder release hanya menerima worktree Git yang benar-benar bersih dan hanya
mengemas file yang tracked pada commit tersebut. File lokal/untracked, perubahan
belum commit, runtime, backup, upload, log, credential, dan dashboard audit
internal menyebabkan build ditolak; tidak ada artefak parsial yang diterbitkan.
Selesaikan atau simpan pekerjaan lokal terlebih dahulu, commit kandidat yang
disetujui, lalu build artefak dari commit bersih itu.

## 7. Dokumentasi Akhir Produk

Setelah fitur, UI, installer, dan proses upgrade stabil, dokumentasi release
harus diterbitkan dalam empat bagian: panduan pengguna per peran/modul, panduan
admin aplikasi, panduan admin server untuk instalasi/backup/update/restore, dan
panduan troubleshooting/integrasi POS Mobile, printer, WhatsApp, serta
Telegram. Panduan harus mengikuti versi/tag release dan diuji oleh pengguna
non-programmer sebelum pilot customer.
