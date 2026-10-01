# Runbook service-auth API internal wa-engine

Batch 52 memisahkan autentikasi arah Finance ke `wa-engine` dari credential
callback arah sebaliknya. Finance memanggil endpoint `/internal/*` dengan
credential khusus `FINANCE_WA_ENGINE_API_TOKEN` melalui header
`X-Finance-Wa-Engine-Token`.

Repository, database, dan UI tidak menyimpan atau menampilkan nilai credential
ini. Nilai lama `wa_session.bot_api_token` dan baris `WA_TOKEN` pada `.env` yang
sudah ada dibiarkan utuh untuk rollback data, tetapi tidak lagi dipakai oleh API
internal dan tidak lagi dikelola dari UI.

## Kontrak runtime

- Nilai `FINANCE_WA_ENGINE_API_TOKEN` yang sama dan tidak kosong wajib tersedia
  pada ketiga konteks eksekusi: worker PHP/FPM web Finance, scheduler/cron CLI
  Finance yang menjalankan `php index.php whatsapp api_schedule_run`, dan proses
  `wa-engine`.
- Finance mengirim credential hanya melalui header
  `X-Finance-Wa-Engine-Token`; URL, endpoint, method, payload, dan timeout tetap.
- `wa-engine` bind ke loopback dan melindungi semua path `/internal/*`.
- Credential proses kosong, header baru kosong/salah, `?token=`, dan
  `X-Sync-Token` selalu ditolak. Tidak ada fallback ke `WA_TOKEN`,
  `wa_session.bot_api_token`, atau token development.
- Jika credential engine belum tersedia, proses boleh tetap hidup untuk menjaga
  lifecycle sesi, tetapi seluruh request `/internal/*` tetap fail-closed dengan
  HTTP 403. Finance juga berhenti sebelum membuka cURL bila credential PHP/FPM
  belum tersedia.

## Boundary konfigurasi

- Inject secret melalui secret manager atau konfigurasi service/process manager
  yang berada di luar web root/document root. Jangan menaruh nilainya di source,
  database, URL, command line, tiket, log, atau `wa-engine/.env`.
- Cron tidak otomatis mewarisi environment PHP-FPM. Provision
  `FINANCE_WA_ENGINE_API_TOKEN` secara terpisah ke environment service account
  atau process manager eksternal yang meluncurkan scheduler/cron Finance; jangan
  menaruh nilai secret langsung pada crontab atau argumen perintah.
- `wa-engine/.env.example` hanya mendokumentasikan nama variable dengan nilai
  kosong. Loader Node dan launcher PHP sengaja mengabaikan
  `FINANCE_WA_ENGINE_API_TOKEN` dari `wa-engine/.env`.
- Kontrak non-secret/DB tetap terpisah: `WA_PORT`, `DB_HOST`, `DB_USER`,
  `DB_PASS`, dan `DB_NAME` masih dapat dimuat dari process environment atau
  `.env` sesuai perilaku lama. Batch ini tidak mengubah schema maupun data DB.
- `FINANCE_WA_ENGINE_COMMAND_TOKEN` tetap credential callback Batch 50 untuk
  arah `wa-engine` ke Finance, dengan header
  `X-Finance-Group-Command-Token`. Jangan menyamakan atau menukar kedua token.
  Untuk arah Finance ke engine, runbook Batch 52 ini menggantikan catatan
  legacy `WA_TOKEN` pada runbook Batch 50; kontrak callback Batch 50 sendiri
  tetap berlaku tanpa perubahan.

## Provisioning tiga konteks eksekusi

Gunakan satu referensi secret terkelola dengan nilai yang sama, tetapi pasang
referensi/injection-nya secara eksplisit pada setiap konteks berikut:

1. **PHP/FPM web Finance:** inject ke environment pool/worker yang melayani
   request web dan dapat menjalankan `callBotApi`. Pastikan kebijakan PHP-FPM
   meneruskan variable tersebut ke worker.
2. **Finance CLI scheduler/cron:** inject ke service/process manager eksternal
   yang menjalankan `php index.php whatsapp api_schedule_run`. Perlakukan ini
   sebagai konteks tersendiri dari PHP-FPM karena cron tidak otomatis mewarisi
   environment pool PHP-FPM.
3. **Proses `wa-engine`:** inject ke environment service/process manager engine
   agar endpoint `/internal/*` memvalidasi header dari kedua konteks Finance.

Seluruh konfigurasi secret wajib berada di luar web root. Jangan menulis nilai
aktual ke repository, `.env` runtime, crontab, command line, output preflight,
atau log. Pemeriksaan keberadaan credential harus hanya menghasilkan status
tersanitasi seperti tersedia/tidak tersedia, bukan panjang, hash, prefix, atau
nilai credential.

## Preflight sebelum cutover

1. Pastikan secret manager memiliki credential target dan ketiga konfigurasi
   proses merujuk ke secret yang sama, tanpa menampilkan atau menyalin nilainya.
2. Jalankan pemeriksaan non-printing yang terkontrol pada calon environment
   PHP/FPM web, service scheduler/cron CLI Finance, dan service `wa-engine`.
   Ketiganya harus melaporkan variable tersedia dan tidak kosong tanpa merekam
   nilainya pada command line maupun log.
3. Pastikan konfigurasi scheduler benar-benar menjalankan
   `php index.php whatsapp api_schedule_run` melalui process manager/secret
   injection eksternal, bukan mengandalkan environment PHP-FPM.
4. Pastikan health check dan probe negatif telah disiapkan agar header
   credential tidak dicetak oleh shell tracing, process listing, access log,
   application log, atau laporan CI.
5. Jika salah satu dari tiga konteks belum siap, hentikan rencana cutover. Jangan
   mengaktifkan fallback legacy.

## Cutover terkoordinasi

1. Setelah seluruh preflight lulus, deploy source Batch 52 dalam maintenance
   window yang disetujui.
   Dokumen staging ini tidak menjalankan restart atau cutover apa pun.
2. Aktifkan injection secret pada PHP/FPM web, scheduler/cron CLI Finance, dan
   `wa-engine` sebagai satu perubahan terkoordinasi.
3. Reload/restart PHP-FPM dan `wa-engine` melalui prosedur operasional. Aktifkan
   ulang environment scheduler/cron; restart worker scheduler jika long-running,
   atau pastikan invokasi cron berikutnya menerima environment hasil injection.
4. Dari health-check terkontrol, panggil `/internal/status` melalui konteks web
   Finance dan konteks CLI scheduler tanpa mencetak header. Pastikan respons
   normal diterima, lalu pastikan proses `wa-engine` tetap sehat.
5. Pastikan credential kosong/salah, query `token`, dan `X-Sync-Token` menerima
   HTTP 403. Verifikasi pula satu operasi POST internal yang aman sesuai prosedur
   operasional dari jalur yang berlaku.
6. Jika provisioning salah satu proses belum siap, jalankan rollback
   terkoordinasi. Jangan menambahkan fallback legacy.

## Rotasi dan rollback

Rotasi memerlukan update nilai pada ketiga process environment lalu aktivasi
terkoordinasi dalam window yang sama; overlap token lama/baru tidak didukung.

Untuk rollback:

1. Hentikan cutover dan tahan invokasi scheduler baru sampai ketiga konteks dapat
   dikembalikan secara konsisten; jangan mencetak atau memindahkan secret melalui
   command line/log.
2. Kembalikan referensi secret/configuration revision sebelumnya pada PHP/FPM
   web, service scheduler/cron CLI Finance, dan service `wa-engine` melalui
   process manager eksternal di luar web root.
3. Terapkan reload/restart terkoordinasi sesuai prosedur operasional pada
   PHP/FPM, worker scheduler bila long-running, dan `wa-engine`; untuk cron
   one-shot, pastikan invokasi berikutnya memakai revision rollback.
4. Ulangi pemeriksaan non-printing pada ketiga konteks, lalu probe status dan
   penolakan credential invalid. Buka kembali scheduler hanya setelah hasilnya
   konsisten.
5. Bila rollback juga mengembalikan source, lakukan sebagai keputusan eksplisit
   dan sementara. Data legacy yang sengaja tidak dihapus tetap tersedia, tetapi
   jangan mengaktifkan transport query, shared token, atau fallback legacy.
