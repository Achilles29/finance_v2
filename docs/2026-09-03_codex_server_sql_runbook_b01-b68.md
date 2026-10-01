# Server SQL Runbook — Codex Finance Batch 01 sampai 68

Tanggal: 2026-09-03

Tujuan: memisahkan SQL yang perlu diverifikasi/dijalankan di server dari SQL
read-only preflight dan SQL historis. Batch 68 sendiri tidak membutuhkan SQL
schema atau data.

## Aturan wajib

- Jangan menjalankan SQL langsung ke production sebelum backup terverifikasi,
  approval operator, dan preflight schema.
- Execution log menunjukkan workflow tidak menjalankan SQL ke database nyata;
  status server harus diverifikasi dari `information_schema` dan deployment log.
- Jangan menjalankan file di `sql/_old/` untuk deployment baru.
- Jangan menyalin password ke command line atau memakai `MYSQL_PWD`.
- Jalankan migration di staging clone lebih dahulu.
- `git` repository saat ini memiliki object corruption; jangan melakukan commit,
  reset, checkout, garbage collection, atau recovery destructive sebagai bagian
  runbook SQL.

## Ringkasan artefak SQL

| Artefak | Jenis | Status dari workflow | Tindakan server |
|---|---|---|---|
| `sql/2026-09-03a_auth_login_throttle_foundation.sql` | Migration DDL | Belum dibuktikan pernah dijalankan | `RUN` setelah cek `auth_login_failure` belum ada atau schema belum sesuai. Tidak perlu jika sudah diverifikasi cocok. |
| `sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql` | Guarded migration DDL | Belum dibuktikan pernah dijalankan | `RUN` setelah 2026-09-03a dan backup; gunakan client delimiter-aware/wrapper. |
| `sql/2026-09-02a_wa_report_schedule_claim_lease.sql` | Migration DDL idempoten | Belum dibuktikan pernah dijalankan | `CONDITIONAL RUN` setelah base table ada dan kolom claim belum ada. |
| `sql/2026-08-15b_wa_report_schedule.sql` | Base table + seed template | Historis/conditional | Jangan jalankan blind; hanya jika table/template memang belum ada dan operator menyetujui seed. |
| `sql/2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql` | Consolidated schema migration/preflight | Historis/conditional | Jangan jalankan sebagai bagian B68; gunakan hanya setelah schema diff dan deployment plan menyetujuinya. |
| `tools/sql/2026-09-03_extra_group_mapping_preflight.sql` | Read-only SELECT preflight | Belum dijalankan ke DB nyata | `RUN READ-ONLY` pada staging/live dengan akun SELECT-only; tidak mengubah schema/data. |

Tidak ada SQL B68. Perubahan B68 hanya pada `Master_relation.php` dan smoke
test; deploy code dilakukan melalui release/package yang telah direview.

## 1. Migration login throttle — wajib conditional

File:

```text
sql/2026-09-03a_auth_login_throttle_foundation.sql
```

Tujuan: membuat tabel append-only `auth_login_failure` dan index waktu untuk
throttle login web. File ini DDL-only dan memakai `CREATE TABLE IF NOT EXISTS`.

Preflight minimal:

```sql
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'auth_login_failure';

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'auth_login_failure'
ORDER BY ORDINAL_POSITION;
```

Run hanya bila tabel belum ada atau deployment plan menyatakan migration perlu
di-apply. Setelah run, verifikasi table, primary key, dua index waktu, dan FK
ke `auth_user`. Migration ini belum pernah dibuktikan dieksekusi oleh workflow.

## 2. Migration timestamp login — jalankan setelah 1

File:

```text
sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql
```

Tujuan: memastikan `auth_session_log.login_at` kompatibel dengan boundary
`DATETIME(6)` milik `auth_login_failure.failed_at`. File memiliki preflight dan
guard; mismatch schema menghentikan proses sebelum perubahan.

Wrapper repository:

```text
tools/db/apply_auth_session_log_login_at_microsecond.sh
```

Jalankan wrapper dari root repository setelah option file client disiapkan
secara aman. Client harus mendukung directive `DELIMITER`; jangan memecah file
menjadi statement terpisah secara manual. Setelah selesai, verifikasi:

```sql
SELECT DATA_TYPE, DATETIME_PRECISION, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'auth_session_log'
  AND COLUMN_NAME = 'login_at';
```

Expected: `DATETIME`, precision `6`, `NOT NULL`, default timestamp microsecond,
dan tidak ada `ON UPDATE`. Migration ini belum pernah dibuktikan dieksekusi
oleh workflow.

## 3. Migration WA report claim/lease — conditional

File:

```text
sql/2026-09-02a_wa_report_schedule_claim_lease.sql
```

Preflight:

```sql
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wa_report_schedule';

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wa_report_schedule'
  AND COLUMN_NAME IN ('run_claim_token', 'run_claimed_at');
```

Expected after migration: `run_claim_token CHAR(32) NULL` dan
`run_claimed_at DATETIME NULL`. Jalankan hanya jika base table ada dan kolom
tersebut belum tersedia. Migration memakai `ADD COLUMN IF NOT EXISTS`, tetapi
versi MariaDB/MySQL target tetap wajib diverifikasi.

Base file berikut hanya conditional dan bukan bagian B68:

```text
sql/2026-08-15b_wa_report_schedule.sql
```

File base membuat table dan dapat menambah template default. Jangan menjalankan
ulang hanya untuk mengaktifkan claim/lease karena dapat mencampurkan perubahan
base/seed dengan migration B47.

## 4. Consolidated WA/POS schema file — jangan blind run

File:

```text
sql/2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql
```

File ini mencakup lebih dari B47: `pos_order.customer_name`, conditional
`wa_session.node_path`, conditional media fields `wa_broadcast`, serta
`wa_report_schedule`. Karena dampaknya lintas modul, file ini hanya boleh
dijalankan sebagai bagian deployment plan schema yang terpisah setelah audit
schema target. Statusnya bukan requirement baru B68.

## 5. B66 Extra Group mapping preflight — read-only

SQL:

```text
tools/sql/2026-09-03_extra_group_mapping_preflight.sql
```

Wrapper:

```text
tools/run_extra_group_mapping_preflight.sh
```

Preflight memeriksa lima table, engine InnoDB, 16 kolom wajib, unique pair,
empat FK dengan identity dan `RESTRICT`, orphan, duplicate, inactive master,
dan mismatch division. SQL hanya `START TRANSACTION READ ONLY`, `SELECT`, dan
`COMMIT`; tidak ada DDL/DML/remediation.

Sediakan option file di luar repository, misalnya:

```ini
[client]
host=127.0.0.1
port=3306
user=preflight_reader
password=PROVISION_SEPARATELY
database=finance_staging_clone
```

Permission file:

```bash
chmod 600 /etc/finance-preflight/b66-reader.cnf
```

Jalankan dari root repository:

```bash
MYSQL_DEFAULTS_EXTRA_FILE=/etc/finance-preflight/b66-reader.cnf \
  tools/run_extra_group_mapping_preflight.sh
```

Arti exit code:

- `0`: schema dan data preflight bersih.
- `10`: schema contract gagal; stop release.
- `11`: orphan atau duplicate; audit dan buat remediation terpisah.
- `12`: inactive/mismatch; konfirmasi policy bisnis.
- `64`/`66`: invocation atau option file tidak valid.
- `69`: koneksi/query/output tidak lengkap; jangan anggap bersih.
- `127`: client `mysql`/`mariadb` tidak tersedia.

Lihat [B66 runbook](2026-09-03_extra_group_mapping_preflight_runbook.md) untuk
detail akun read-only, marker, batas detail, dan follow-up UAT. Jangan memakai
akun aplikasi write atau administrator untuk preflight.

## 6. SQL yang tidak perlu dijalankan

- Tidak ada SQL untuk Batch 68 Product → Extra Group.
- Tidak ada SQL untuk B67 optimistic concurrency Extra Group → Product.
- Tidak ada SQL untuk scoped CSRF/RBAC, dashboard mismatch, Printer Agent,
  login controller, atau WhatsApp service-auth selain migration yang disebut
  pada tabel di atas.
- Jangan menjalankan ulang `sql/_old/*` sebagai deployment.
- Jangan menjalankan file preflight dengan akun write dan jangan mengubah hasil
  preflight menjadi remediation otomatis.

## 7. Urutan deployment yang disarankan

1. Pulihkan/validasi repository object secara terpisah; jangan commit dari index
   kumulatif sebelum object Git sehat.
2. Backup database dan uji restore ke clone.
3. Jalankan preflight schema untuk migration login/WA.
4. Jalankan `2026-09-03a` bila belum tersedia.
5. Jalankan `2026-09-03b` setelah 2026-09-03a sukses.
6. Jalankan `2026-09-02a` hanya bila base table ada dan claim columns belum ada.
7. Jalankan B66 read-only preflight dengan akun SELECT-only.
8. Deploy code B68 dari whitelist yang sudah direview; tidak ada SQL B68.
9. Jalankan smoke/health check, browser UAT dua session, dan contention probe
   sebelum membuka perubahan mapping untuk operator.
