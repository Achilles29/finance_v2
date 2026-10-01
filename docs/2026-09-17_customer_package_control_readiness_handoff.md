# Handoff lanjutan paket customer Finance: heartbeat per instalasi dan guard customer

17 September 2026. Scope integrasi instalasi/lisensi/monitoring, bukan perubahan proses bisnis. Kandidat ditujukan untuk **alpha.15** setelah versi/commit final ditetapkan reviewer Control; dokumen ini tidak memberikan persetujuan cutoff, signature, publish, atau bukti instalasi customer otomatis.

Handoff [alpha.14](2026-09-17_installer_heartbeat_control_handoff.md) tetap histori. Build aktual Control pada commit alpha.14 dilakukan sebelum penyesuaian lanjutan; perubahan runtime berikut membutuhkan commit, review dan build baru. Artefak release 66 tidak diubah.

## Dua koreksi sender heartbeat

- Konfigurasi heartbeat customer dapat dipilih melalui `FINANCE_HEARTBEAT_CONFIG_FILE`, menggunakan file JSON privat root-owned di luar root aplikasi. Field tidak diberikan berarti path legacy tetap `/var/lib/finance-config/control-center-heartbeat.json`; field eksplisit kosong/relatif/unsafe ditolak, bukan memilih konfigurasi master.
- DB customer berasal dari `DeploymentConfig`: file `FINANCE_DEPLOYMENT_FILE` atau environment `FINANCE_DB_HOST`, `FINANCE_DB_NAME`, `FINANCE_DB_USER`, `FINANCE_DB_PASSWORD`. Adanya salah satu selector/field customer, termasuk `FINANCE_CUSTOMER_INSTALLATION_FILE` atau override `FINANCE_HEARTBEAT_CONFIG_FILE` saja, melarang fallback `/var/lib/finance-config/database.php`. Nilai yang tidak lengkap/tidak valid menghasilkan komponen database DOWN tanpa membuka DB master; tidak ada SQL bisnis atau operasi apply schema pada sender.
- Versi paket tidak dicari dari `.git`. Dengan `FINANCE_CUSTOMER_INSTALLATION_FILE`, sender memanggil `Control_license_cache::customer_context()` untuk memeriksa context privat, signature Ed25519 Control asli, binding release/profil serta hash app/inner/core. Versi berasal dari context yang dihasilkan installer setelah verifikasi paket. Instance ID pada config heartbeat harus sama dengan identitas context. Key ID heartbeat tidak dipin pada context agar rotasi key yang sah tetap dapat dilakukan.
- Tanpa konfigurasi customer/deployment eksplisit, perilaku legacy tetap sama, termasuk sumber DB dan versi Git. Ini tidak mengubah cron/config/master yang sedang berjalan.
- Metadata runtime tetap opt-in, hanya `primary_domain` dan `region`, berasal dari URL/konfigurasi tepercaya dan dimasukkan sebelum digest/HMAC. Tidak ada HTTP_HOST, data transaksi, credential DB, token aktivasi atau lisensi dalam payload.

Loader context tidak mensyaratkan lisensi ACTIVE untuk **monitoring**. Instalasi yang lisensinya belum aktif/terbatas tetap perlu dapat dimonitor. Penguncian route bisnis serta validasi ACTIVE/GRACE merupakan boundary customer tersendiri, bukan keputusan sender heartbeat atau perubahan flag `Feature_gate` master.

## File customer yang disiapkan operator

Contoh path di bawah bukan path konfigurasi server yang sudah dipasang. Semua file milik instalasi customer yang sama, bukan milik source master Finance:

| File | Sumber/fungsi |
| --- | --- |
| `deployment.json` | DB/URL/encryption key serta direktori session/log/cache customer, disiapkan admin privat. |
| `runtime/customer-installation.json` | Dihasilkan installer untuk release yang diverifikasi; jangan disalin dari release lain atau diedit untuk mengatasi penolakan. |
| `heartbeat.json` | Instance ID/key ID/secret dari Control, endpoint heartbeat dan URL lokal customer. |
| `license/private/agent.json` | Identitas/kunci agent privat, dibuat `finance_license.php init`; tidak dibagikan ke web process. |
| `license/public/identity.json`, `trust.json`, `runtime.json` | Identitas, public trust dan signed cache; root-owned read-only untuk group web. Bukan secret signing key Control. |

File config heartbeat harus root-owned dengan mode `0600` atau `0640`, parent root-owned tanpa group/world write, di luar webroot. `FINANCE_CUSTOMER_INSTALLATION_FILE` menunjuk file context **release tersebut**, bukan context master atau release lama. Public trust release dan public trust lisensi mempunyai purpose berbeda; jangan saling menggantikan.

Contoh isi `heartbeat.json` setelah credential nyata diperoleh lewat UI Control dan disimpan langsung pada server:

```json
{
  "endpoint": "https://control.namuaprojects.com/api/v1/heartbeats",
  "public_url": "https://finance.customer.example/",
  "instance_id": "<INSTANCE ID DARI CONTROL>",
  "key_id": "<KEY ID HEARTBEAT DARI CONTROL>",
  "secret": "<SECRET HEARTBEAT DARI CONTROL>",
  "environment": "PRODUCTION",
  "runtime_fields": ["primary_domain", "region"],
  "region": "Jakarta"
}
```

Placeholder di atas harus diganti pada **editor server privat**, bukan di source/Git/chat/log. Secret heartbeat bukan install token `ndi_…` atau activation code `nla_…`.

## Tes manual monitoring pada server customer

Setelah paket terverifikasi, instance disiapkan dan URL HTTPS lokal tersedia, jalankan memakai selector file—tidak memasukkan password/token pada argumen command:

```bash
FINANCE_DEPLOYMENT_FILE=/var/lib/finance/customer-a/deployment.json \
FINANCE_CUSTOMER_INSTALLATION_FILE=/var/lib/finance/customer-a/runtime/customer-installation.json \
FINANCE_HEARTBEAT_CONFIG_FILE=/var/lib/finance/customer-a/heartbeat.json \
/www/server/php/81/bin/php /opt/finance/releases/alpha15/scripts/control_center_heartbeat.php
```

Sesuaikan path dengan target customer yang benar. Output sukses hanya status, HTTP status, health dan flag duplicate; tidak menampilkan credential. Periksa **Control → Instalasi → detail instance yang sama**: waktu heartbeat bergerak, versi cocok dengan paket yang diinstal, schema dari migration ledger, hostname/region monitoring mengikuti konfigurasi customer. Koneksi DB gagal dapat menghasilkan heartbeat diterima dengan health DOWN; itu bukan bukti DB/instalasi siap.

Setelah satu heartbeat nyata diterima, admin memasang jadwal lima menit memakai tiga selector file tersebut dan script **paket customer**, bukan `/www/wwwroot/finance` master. Wrapper/cron harus root-owned, log privat, dan tidak mencetak environment/credential. Batch ini tidak memasang cron atau mengubah credential server otomatis.

## Uji reproduksi tanpa DB/HTTP aktif

```bash
/www/server/php/81/bin/php tools/tests/c3_customer_heartbeat_runtime_smoke.php
/www/server/php/81/bin/php tools/tests/c3_control_heartbeat_smoke.php --control-root=/www/wwwroot/control
/www/server/php/81/bin/php tools/tests/c4_customer_bootstrap_guard_smoke.php
```

Hasil awal koreksi sender: **85 customer-runtime checks + 37 metadata/interoperabilitas checks PASS**. Runtime suite memakai mapping deployment pure/memory, helper versi no-Git, pemeriksaan selector/fail-closed termasuk environment aktual dengan override-only, dan file privat disposable yang dibuat/dibersihkan hanya milik tes tersebut. Tidak memanggil PDO, HTTP, bootstrap aplikasi atau DB aktif. Metadata suite mengimpor validator Control read-only tanpa bootstrap/DB.

Uji boundary customer terpisah: **60 bootstrap guard checks PASS**, termasuk interoperabilitas sender dengan context yang benar-benar bertanda tangan Control, paket tanpa `.git`, versi paket persis, serta monitoring tetap membaca versi yang benar saat signed cache masih UNACTIVATED. Pengujian juga menolak binding/root/profil/hash/signature yang berubah dan lisensi host yang hilang, revoked, kedaluwarsa di luar GRACE, atau berbeda fingerprint. Hasil ini berasal dari fixture disposable dan tidak mengaktifkan lisensi atau menginstal paket pada server customer nyata.

Review/build final perlu menjalankan guard/installer/license suites serta build penuh terisolasi dari **commit final persis**. Uji memory/helper tidak boleh disebut bukti customer install end-to-end. Tidak ada jaminan anti-bypass absolut pada PHP/server milik customer; root yang dapat mengganti bootstrap bisa memodifikasi source. Boundary customer membatasi kesalahan/duplikasi/credential tidak sah tanpa merusak DB/master.
