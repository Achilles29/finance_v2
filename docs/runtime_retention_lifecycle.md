# Retention dan Lifecycle Runtime Finance — A5.15

**Tanggal:** 5 September 2026
**Status:** kebijakan dan engine lulus; staging hanya menjalankan dry-run.

## Prinsip Aman

- Backup, upload, credential, konfigurasi device, session WhatsApp, jurnal,
  mutasi stok, payroll, dan audit transaksi tidak boleh dihapus hanya karena
  umur file/data.
- `retention_manager.php` selalu `plan` secara default. Mode `apply` memerlukan
  prefix hash policy dan hanya memindahkan file ke quarantine; tidak ada
  `unlink` atau penghapusan permanen.
- Backup baru wajib mempunyai pasangan `.sha256`. Backup tanpa checksum atau
  checksum salah tidak pernah menjadi kandidat retention.
- Minimal backup/evidence terbaru tetap dipertahankan walaupun sudah melewati
  umur retention.
- Quarantine disimpan minimal 14 hari. Penghapusan permanen memerlukan review
  audit, verifikasi backup lain, dan prosedur operasional terpisah.

## Jadwal Baseline

| Data runtime | Umur minimum | Minimum yang tetap disimpan | Tindakan |
| --- | ---: | ---: | --- |
| File temporer A5 (`run/`) | 2 hari | — | Quarantine, maksimal 100 file/run |
| Evidence A5 | 365 hari | 20 terbaru | Quarantine |
| Backup sebelum migration/perubahan | 90 hari | 14 terbaru | Hanya bila SHA-256 valid, lalu quarantine |
| Backup database reguler | 35 hari | 14 terbaru | Hanya bila SHA-256 valid, lalu quarantine |
| Log job backup | 30 hari | 20 terbaru | Quarantine |
| Log service sistem | 30 rotasi | — | `logrotate`, bukan retention manager |

Database memakai kebijakan konservatif. Detail availability sukses dapat
diringkas/diarsipkan setelah 90 hari; login failure setelah 180 hari; session,
WhatsApp, dan Telegram terminal setelah 365 hari. Semua rule database masih
`enabled=false`: sebelum purge harus ada archive tervalidasi, backup restoreable,
batch limit, audit jumlah/baris waktu, dan rollback melalui backup. Baris
mismatch, queue aktif, Telegram `UNKNOWN`, ledger transaksi, jurnal keuangan,
movement stok, payroll, dan audit transaksi selalu dipertahankan.

## Cara Operator Menjalankan

Lihat rencana tanpa mengubah apa pun:

```sh
cd /www/wwwroot/finance
php tools/release/retention_manager.php validate
php tools/release/retention_manager.php plan
```

Output `plan` menampilkan `confirmation`, jumlah kandidat, ukuran, dan file
yang tidak lolos verifikasi. Review output dan backup terlebih dahulu. Untuk
memindahkan kandidat ke quarantine:

```sh
php tools/release/retention_manager.php apply --confirm=PREFIX_DARI_PLAN
```

Jangan menyalin prefix contoh dari dokumentasi; selalu ambil dari plan saat
itu. Perubahan policy otomatis mengganti prefix sehingga rencana lama tidak
dapat dipakai.

Preflight database bersifat read-only dan memakai file koneksi privat di luar
repository:

```sh
php tools/db/retention_preflight.php probe \
  --defaults-extra-file=/path/private/client.cnf \
  --database-name-file=/path/private/database.name
```

## Lokasi Runtime Customer

| Komponen | Data persistent | Log | Saat uninstall |
| --- | --- | --- | --- |
| Finance web | Upload di storage privat/customer, bukan source release | `/var/log/finance/` | Source boleh diganti; upload dan database tetap dipertahankan |
| Backup | `/var/lib/finance-backup/dumps` mode privat | `/var/lib/finance-backup/logs` | Pertahankan sampai owner menyetujui pemusnahan |
| Migration/release evidence | `/var/lib/finance-a5-runtime` | evidence berada di root yang sama | Pertahankan evidence dan backup; file `run/` boleh diproses policy |
| Telegram | `/var/lib/finance-telegram` mode privat | `/var/log/finance/` | Hapus service, tetapi credential/target hanya dihapus melalui rotasi/revoke terpisah |
| WhatsApp engine | Target: `/var/lib/finance-whatsapp`, bukan `wa-engine/` | `/var/log/finance/` | Session tidak dihapus otomatis; unlink device melalui prosedur operator |
| Printer Agent | Data aplikasi OS user/service, bukan folder source | Log OS/service | Unpair/revoke token dahulu; konfigurasi printer dipertahankan bila owner meminta |

Template log rotation tersedia di
`tools/release/finance-runtime.logrotate.example`. Instalasi customer harus
menyalinnya ke konfigurasi sistem, menyesuaikan user service, lalu menjalankan
uji `logrotate -d` sebelum aktivasi.

## Perubahan Backup Runner

Runner Linux dan Windows sekarang membuat SHA-256 setelah dump berhasil dan
tidak lagi menghapus backup lama secara langsung. Default contoh Linux memakai
`/var/lib/finance-backup`, di luar document root. Retention dilakukan terpisah
agar kegagalan backup tidak sekaligus menghapus titik pemulihan lama.

## Status yang Masih Terbuka

- Tidak ada file staging yang dipindahkan pada A5.15; dry-run menemukan nol
  kandidat dan nol file invalid. Satu backup Telegram yang sebelumnya belum
  mempunyai companion kini sudah memperoleh SHA-256 terverifikasi tanpa
  mengubah archive aslinya.
- Preflight database read-only menemukan 52.445 detail availability sukses
  berumur lebih dari 90 hari (1–6 Juni 2026), sedangkan mismatch lama 0.
  Data ini baru kandidat archive, bukan izin purge.
- Purge database availability belum diaktifkan sampai agregasi harian/archive
  dan rollback jumlah baris dibangun serta disetujui.
- Upload lama memerlukan referential cleanup melalui modul pemiliknya; age-based
  filesystem deletion dilarang.
- Pemindahan log/session WhatsApp dan Printer Agent dari source ke lokasi
  runtime dilakukan bersama installer customer C3, bukan dengan memindahkan
  data staging secara mendadak.

Tidak ada SQL, mutasi database, atau perubahan POS Mobile/APK pada A5.15.
