# Runbook service-auth command grup WhatsApp

Callback `wa-engine` ke Finance `Whatsapp/api_group_command` menggunakan satu
credential service khusus bernama `FINANCE_WA_ENGINE_COMMAND_TOKEN`. Dokumen dan
template repository ini sengaja tidak memuat nilainya.

## Kontrak runtime

- Finance menerima hanya HTTP `POST` pada URL callback yang dikonfigurasi.
- `wa-engine` mengirim JSON ke nilai `FINANCE_COMMAND_URL` secara persis, tanpa
  token pada query string.
- Credential hanya dikirim lewat header
  `X-Finance-Group-Command-Token`.
- Nilai `FINANCE_WA_ENGINE_COMMAND_TOKEN` yang sama dan tidak kosong wajib
  tersedia pada process environment PHP/FPM dan `wa-engine`.
- Batch 52 memakai `FINANCE_WA_ENGINE_API_TOKEN` melalui header
  `X-Finance-Wa-Engine-Token` khusus untuk arah Finance ke `wa-engine`.
  `FINANCE_WA_ENGINE_COMMAND_TOKEN` tetap hanya untuk arah callback
  `wa-engine` ke Finance. Kedua credential tidak boleh dipertukarkan dan tidak
  memiliki fallback legacy.

## Provisioning tanpa menyimpan secret di web root

1. Buat credential acak berentropi tinggi di secret manager. Jangan mencetak
   atau menyalinnya ke source, URL, tiket, log, screenshot, maupun command line.
2. Inject `FINANCE_WA_ENGINE_COMMAND_TOKEN` ke environment pool PHP-FPM melalui
   secret manager atau konfigurasi service yang berada di luar document/web
   root. Pastikan kebijakan PHP-FPM mengizinkan variable itu diteruskan ke
   worker.
3. Inject credential yang sama ke environment service/process manager
   `wa-engine`, juga di luar web root. Set `FINANCE_COMMAND_URL` ke URL endpoint
   Finance final tanpa query string.
4. Jangan menaruh credential production pada `wa-engine/.env`; file
   `.env.example` hanya template nama variable tanpa nilai secret. Loader Node
   dan launcher PHP sengaja mengabaikan key credential dari file `.env` agar
   nilainya tetap process-only.

## Cutover terkoordinasi

1. Provision kedua process environment sebelum source Batch 50 diaktifkan.
2. Reload/restart worker PHP-FPM agar environment baru terbaca.
3. Restart `wa-engine` dalam window yang sama agar kedua sisi memakai credential
   yang sama.
4. Jalankan health check callback POST dari service terkontrol tanpa mencetak
   header. Verifikasi menu dan satu laporan read-only dari grup aktif.
5. Pastikan GET/PUT/DELETE, token query, `X-Sync-Token`, credential kosong, dan
   credential salah ditolak; pastikan mutasi tanpa identitas admin/pesan tetap ditolak.
6. Setelah verifikasi berhasil, lanjutkan traffic normal. Jika salah satu sisi
   belum menerima environment baru, rollback deployment source atau selesaikan
   provisioning lalu restart keduanya; jangan mengaktifkan fallback credential.

## Rotasi

Rotasi membutuhkan update terkoordinasi pada PHP/FPM dan `wa-engine`, kemudian
restart keduanya. Endpoint bersifat fail-closed dan tidak menyediakan overlap
token lama/baru, jadi lakukan rotasi dalam maintenance window singkat. Jangan
menambahkan fallback ke `wa_session.bot_api_token`, `WA_TOKEN`, query token,
`X-Sync-Token`, atau token development lokal.

## Pemeriksaan URL Callback

Di server Namua ini, aplikasi Finance berada di `core.namuacoffee.com`.
URL callback yang benar adalah
`https://core.namuacoffee.com/wa/api/group-command`.
`finance.namuacoffee.com` melayani aplikasi lain, bukan endpoint ini.

Pada 24 September 2026, `FINANCE_COMMAND_URL` di
`/etc/finance-wa-engine.env` masih mengarah ke domain yang salah. Callback
perintah grup menerima HTTP 404, sementara notifikasi dan laporan terjadwal
tetap berhasil karena memakai arah koneksi yang berbeda. URL runtime sudah
dikoreksi dan engine direstart tanpa menghapus sesi atau mengubah credential.

Environment proses mengalahkan default URL dalam source. Karena itu, Git pull
saja tidak memperbaiki URL runtime yang salah. Periksa konfigurasi service di
luar web root dan restart engine setelah mengubahnya. Jangan mencetak token
untuk diagnosis. Simpan cadangan file konfigurasi di lokasi root-only.

Caller sekarang mencatat kegagalan HTTP, JSON tidak valid, dan pesan kosong
melalui log command grup, tanpa menulis body respons atau credential. Callback
dibatasi 30 detik dan tetap menolak redirect; jangan menonaktifkan pemeriksaan
token atau mengikuti redirect sebagai jalan pintas untuk memperbaiki URL.

## Input Mutasi Grup (30 September 2026)

Penolakan mutasi tanpa syarat telah diganti dengan pemeriksaan admin grup.
Engine harus direstart setelah pembaruan ini. Perintah `menu`, laporan, dan
`mutasi bantuan` tetap dapat dibaca anggota grup aktif yang terdaftar.

- Engine memeriksa admin dari metadata WhatsApp, bukan nama pengirim atau teks
  pesan. ID peserta LID maupun nomor WhatsApp didukung.
- Callback mutasi memerlukan `sender_jid`, boolean `sender_is_admin`,
  `message_id`, dan `message_timestamp` dari engine terautentikasi.
- Hanya pesan baru (`notify`) berumur maksimal 15 menit yang dapat diposting.
  Sinkronisasi riwayat tidak mengeksekusi instruksi keuangan lama.
- Contoh: `mutasi in TUNAI 50000 setoran owner kategori:OWNER_CAPITAL`.
- Contoh: `mutasi out TUNAI 25000 beli bensin kategori:OPERATING_EXPENSE`.
- Contoh: `mutasi transfer TUNAI MANDIRI 100000 setor bank`.
- Gunakan nama/kode rekening aktif. Ketik `mutasi bantuan` untuk kategori dan
  format lengkap. Kategori IN/OUT wajib dipilih, tidak ditebak dari catatan.
  Biaya promo/platform tetap lewat Finance dengan rincian settlement.
- Posting menggunakan model Finance yang sama, termasuk larangan periode
  CLOSED, rekening nonaktif, nominal tidak valid, dan saldo tidak cukup.
- Kunci berdasarkan grup dan ID pesan dikunci saat pemrosesan dan disimpan di
  ledger. Retry pesan yang sama tidak menggandakan IN/OUT maupun pasangan
  transfer. Pesan baru memiliki ID baru: periksa laporan sebelum mengirim ulang.
- Pengirim dan ID pesan tercatat pada catatan audit. Tidak diperlukan SQL baru
  jika kolom dari migrasi `2026-09-13a` sudah tersedia.

Uji tanpa mengirim WA atau mengubah database operasional:

```sh
node tools/tests/wa_group_mutation_context_smoke.js
node tools/tests/wa_engine_group_command_service_auth_smoke.js
php tools/tests/whatsapp_group_command_service_auth_smoke.php
php tools/tests/whatsapp_group_command_mutation_disabled_smoke.php
php tools/tests/whatsapp_group_mutation_input_smoke.php --mysql-fixture
```

Nama uji `mutation_disabled` dipertahankan untuk kompatibilitas daftar uji lama;
yang diuji adalah penolakan payload lama tanpa identitas pengirim terverifikasi.
Uji `--mysql-fixture` membuat MariaDB sementara dengan socket terpisah di `/tmp`.
