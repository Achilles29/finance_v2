# Finance sebagai Pilot Control Center

Update 2026-09-17: sender memakai root paket relatif dan mendukung metadata runtime opsional dari URL konfigurasi lokal, tanpa domain lock. Langkah opt-in, batas konfigurasi, dan handoff customer: [installer/heartbeat Control](2026-09-17_installer_heartbeat_control_handoff.md). Tidak ada perubahan cron/credential staging otomatis. Path pada dokumen ini adalah contoh pilot internal, bukan alamat wajib customer.

Finance pada `https://pos.namuacoffee.com` adalah instalasi internal pertama
untuk Namua Application Control Center.

## Batas integrasi

- komunikasi hanya outbound dari Finance ke Control Center melalui HTTPS;
- Control Center tidak mendapat credential atau akses database Finance;
- sender hanya mengirim versi, status komponen, jumlah antrean, dan persentase
  disk;
- sender tidak mengirim transaksi, omzet, stok, resep, payroll, identitas
  pegawai/customer/member, token integrasi, session, log, maupun file backup;
- respons Control Center tidak mengandung remote command.

## Runtime

Sender berada di `scripts/control_center_heartbeat.php`. Konfigurasi dan secret
instance berada di luar repository pada:

```text
/var/lib/finance-config/control-center-heartbeat.json
```

File konfigurasi harus dimiliki `root:www`, mode `0640`, dan tidak boleh
disalin ke repository atau dokumentasi.

Jalankan manual:

```bash
/usr/bin/php /www/wwwroot/finance/scripts/control_center_heartbeat.php
```

Jadwal produksi berjalan setiap lima menit melalui
`/etc/cron.d/finance-control-heartbeat`. Kegagalan dicatat ke log aplikasi yang
berada di luar Git pada `/var/log/namua-control/finance-heartbeat.log`.

## Kontrak signature

Signature memakai HMAC-SHA256 atas string berikut, dipisahkan newline:

```text
POST
/api/v1/heartbeats
instance_id
key_id
timestamp
nonce
idempotency_key
sha256(raw_json_body)
```

Control Center menolak timestamp di luar lima menit, nonce yang pernah dipakai,
idempotency conflict, signature salah, key revoked/expired, payload lebih dari
64 KiB, field tidak dikenal, dan lebih dari 20 heartbeat per menit per instance.
