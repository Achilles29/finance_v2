# Codex melalui Telegram — khusus internal

Status 14 September 2026: bridge terpasang di server pengembangan; akun pertama sudah terhubung. Dukungan **maksimal dua akun Telegram milik pemilik** ditambahkan tanpa mencabut akun pertama. Akun kedua menggunakan undangan pairing baru. Bukan modul customer atau perubahan fase komersialisasi.

## Cara menggunakan

1. Buka **chat pribadi `@cacacia_bot`**, bukan grup Namua. Kirim perintah pairing yang diberikan secara pribadi melalui thread Codex. Sekali pakai, berlaku 24 jam. Jangan membagikan kode pairing.
2. Bot membalas bahwa akun terpasang. Identitas yang disimpan adalah **user ID Telegram**, bukan nama/username yang dapat diganti. Anggota grup tidak otomatis mendapatkan akses.
3. Kirim `/codex resume`. Bot menampilkan daftar thread **workspace `/www/wwwroot/finance`** dari akun Codex server ini, delapan per halaman. Thread di laptop/akun lain tidak otomatis tersedia.
4. Kirim `/codex pilih 1` sesuai nomor daftar. Bot menyebut judul dan ID thread pilihan. Daftar berlaku 15 menit; `/codex berikut` membuka halaman selanjutnya.
5. **Tutup sesi Codex Finance di terminal/IDE terlebih dahulu**, tanpa menghapus thread. Editor biasa boleh tetap terbuka. Untuk CLI, keluar setelah tugas selesai; riwayat dapat dibuka kembali dengan `codex resume`.
6. Kirim teks biasa, misalnya: `Periksa filter halaman stok component; jelaskan dulu penyebabnya, jangan edit.` Pesan ini menjadi instruksi baru dalam thread terpilih, bukan thread baru secara diam-diam.
7. Bot mengirim tanda mulai, pemberitahuan jika masih berjalan, dan ringkasan hasil/validasi. Hasil juga tersimpan dalam riwayat Codex. Untuk melihatnya di IDE, buka/resume kembali thread yang sama.

Perintah tambahan:

| Perintah | Kegunaan |
| --- | --- |
| `/codex status` | Thread pilihan, status tugas, dan hasil terakhir **dari akun pengirim**. Berguna jika balasan sebelumnya tidak sampai. |
| `/codex stop` | Menghentikan **tugas bot yang dimulai akun pengirim**, bukan tugas akun lain atau proses IDE. Perubahan yang telanjur dibuat **tidak di-undo**. |
| `/codex keluar` | Melepas pilihan thread setelah tugas selesai; riwayat tidak dihapus. |
| `/codex help` | Panduan singkat. |

## Batas yang disengaja

- Maksimal dua akun pemilik, chat pribadi saja, **satu tugas pada satu waktu untuk kedua akun**. Pesan tambahan ketika tugas berjalan ditolak dengan penjelasan, **bukan antrean pekerjaan tersembunyi**; kirim ulang setelah selesai.
- Setiap akun mempunyai daftar/pagination, pilihan thread, status/hasil, dan tujuan balasan sendiri. Mengganti thread di akun kedua tidak mengganti pilihan akun pertama. Keduanya tetap memiliki hak coding dan akses kumpulan thread Finance yang sama; pemisahan DM bukan isolasi tenant atau pembatasan membaca riwayat thread bersama.
- Pesan forward, bot, identitas anonim, grup/channel, dan user ID lain tidak menjadi instruksi coding. Foto/file/voice belum diteruskan; gunakan teks dulu.
- Setiap update Telegram dideduplikasi sebelum diproses. Setelah worker restart, tugas yang terputus ditandai `interrupted`, **tidak diulang otomatis** karena perubahan parsial mungkin sudah terjadi.
- Deteksi konflik melihat proses Codex pada cwd Finance, file rollout thread yang dibuka client lain, dan status yang diketahui app-server. Ini **bukan lock atomik lintas semua IDE**: jangan membuka writer lain ketika bot bekerja. Client baru yang terdeteksi saat tugas berjalan menyebabkan bot berhenti; perubahan sebelumnya perlu diperiksa.
- Codex berjalan dengan `workspace-write`, approval `never`, network sandbox off, tanpa tambahan writable roots/rules. Tidak menggunakan `danger-full-access`. Instruksi transport melarang push/deploy, perubahan credential/server/database dan tindakan destruktif; pekerjaan yang memerlukan perluasan izin dilanjutkan di IDE. Ini tooling pemilik server tepercaya, bukan layanan eksekusi untuk pengguna umum.
- Batas tugas 45 menit. Kegagalan transport/model/sandbox tidak dilabeli sukses. `stop` atau kegagalan tidak menjamin rollback transaksi/file.
- Ringkasan dikirim hanya ke **akun pengirim tugas**, dengan penerima ditetapkan saat pesan masuk antrean. Akun pertama mendapat pemberitahuan ketika akun kedua berhasil terhubung. Hook notifikasi grup dinonaktifkan **per proses Codex bot** agar tidak menggandakan/membocorkan hasil DM. Notifikasi pekerjaan IDE ke Namua tetap berjalan.
- Token/kode pairing/pola secret umum disamarkan, tool output/stderr tidak diteruskan. Penyaringan bukan jaminan mengenali seluruh jenis secret: jangan meminta bot mengirim credential. Chat bot Telegram bukan secret chat end-to-end.
- Bila pengiriman balasan timeout, status pengiriman menjadi `unknown`, tidak diulang otomatis agar tidak menggandakan pesan. `/codex status` membuat balasan baru dari hasil yang tersimpan.

## Letak instalasi (admin server)

- Sumber versioned: `tools/telegram/codex_bridge/`; PHP controller hanya memanggil relay opsional untuk chat pribadi setelah validasi webhook existing.
- Worker terpasang: `/opt/finance-codex-bridge/bridge.py`, root-only. Tidak dieksekusi langsung dari source webroot.
- Layanan: `/etc/systemd/system/finance-codex-bridge.service`.
- State internal: `/var/lib/finance-codex-bridge/bridge.sqlite` (direktori 0700, DB 0600). Skema v2 menambahkan `accounts`, `account_settings`, serta `user_id` pada inbox/job/outbox. Migrasi satu kali mempertahankan primary owner dan menautkan pilihan thread/riwayat lama ke akun pertama; tidak memindahkannya ke akun kedua. Tidak terenkripsi; jangan masukkan ke paket customer atau berbagi backup-nya.
- Socket lokal: `/var/lib/finance-config/codex-bridge.sock`, root:www 0660. Tidak membuka port publik. Relay dan worker memvalidasi secret webhook; koneksi hanya mengantrikan pesan, tidak menunggu model.
- Sakelar instalasi internal: `.codex/telegram_webhook_bridge.php` (Git-ignored). Bila tidak ada, perilaku Telegram customer tetap seperti semula.
- Bot menggunakan credential existing `/var/lib/finance-telegram/runtime.env`. Tidak mengganti bot, token, webhook URL, atau `allowed_updates`; tidak menggunakan polling `getUpdates` yang berbenturan dengan webhook.
- Codex memakai login ChatGPT yang sudah tersedia pada akun server. OpenAI Docs digunakan untuk memilih `thread/list`, `thread/read`, dan resume **ID eksplisit**, bukan `--last`.

Pemeriksaan dan penerbitan ulang kode pairing yang kedaluwarsa (hanya jika belum ada owner):

```bash
systemctl status finance-codex-bridge.service --no-pager
journalctl -u finance-codex-bridge.service -n 30 --no-pager
python3 /opt/finance-codex-bridge/bridge.py check
python3 /opt/finance-codex-bridge/bridge.py pair
```

Perintah `pair` khusus akun pertama. Setelah akun pertama terpasang, gunakan **`pair-add` dari terminal admin** untuk menerbitkan kode khusus akun kedua:

```bash
python3 /opt/finance-codex-bridge/bridge.py pair-add
```

1. Salin perintah `/codex pair KODE_BARU` yang ditampilkan ke **chat pribadi @cacacia_bot dari akun Telegram kedua**. Jangan gunakan kode awal yang sudah terpakai. Kode tambahan berlaku 24 jam, hanya sekali pakai.
2. Jika salah mengirim kode dari akun pertama, bot menjelaskan bahwa akun sudah terhubung; undangan akun kedua **tidak dikonsumsi**.
3. Akun kedua menerima konfirmasi, lalu dapat menjalankan `/codex resume` → `/codex pilih NOMOR`. Akun pertama tetap dapat dipakai dengan pilihan thread semula.
4. Kode yang terpakai, kedaluwarsa, atau digantikan kode baru tidak bisa dipakai lagi. Setelah dua akun terhubung, penerbitan undangan berikutnya ditolak. Mengganti/mencabut akun memerlukan tindakan admin terpisah.

Kode hanya ditampilkan di terminal lokal/thread privat; jangan mempostingnya ke grup/log bersama. Worker menyimpan hash dan expiry, bukan kode aslinya. HP kedua dengan **akun Telegram yang sama** tidak memerlukan pairing tambahan.

Untuk memasang revisi worker dari sumber yang telah dites, tunggu tugas bot selesai:

```bash
install -o root -g root -m 0600 tools/telegram/codex_bridge/bridge.py /opt/finance-codex-bridge/bridge.py
systemctl restart finance-codex-bridge.service
```

Upgrade multiakun mencadangkan worker dan state SQLite v1 di `/var/lib/finance-codex-bridge/pre-multiaccount-*` sebelum migrasi. **Jangan menjalankan worker v1 terhadap DB v2** karena worker lama tidak mengenali penerima per akun. Jika perlu rollback, hentikan worker dan lakukan pemulihan worker+DB yang cocok dari snapshot secara terencana; simpan state v2 terlebih dahulu agar pesan/akun baru tidak hilang. Jangan menghapus atau menimpa DB live secara langsung.

Untuk mematikan integrasi secara recoverable, pindahkan relay ke lokasi cadangan privat terlebih dahulu, lalu stop service. Jangan menimpa cadangan yang sudah ada:

```bash
mv -n .codex/telegram_webhook_bridge.php /var/lib/finance-codex-bridge/relay.disabled.php
systemctl disable --now finance-codex-bridge.service
```

Pastikan relay sudah tidak berada di `.codex` sebelum mematikan worker; relay aktif + worker mati menyebabkan webhook chat pribadi membalas 503 untuk retry. Perintah laporan grup tidak melewati relay. DB internal, riwayat Codex, dan log tetap disimpan.

## Isolasi dari penjualan dan hasil uji

- `.codex/` sudah dikecualikan dari paket. Tambahan `tools/telegram/codex_bridge/` pada deny-prefix paket mengecualikan seluruh sumber, unit service, relay template, dan tes internal. Tidak mengubah versi kontrak atau validator kebijakan paket lama.
- Nginx staging memblokir akses HTTP ke `/.codex/` dan `/tools/telegram/codex_bridge/` (404). Kode pairing tidak dicatat di dokumen ini, repo, atau grup. Notifier lama juga dites menyamarkan kode pairing.
- Tidak ada perubahan SQL/schema/data transaksi **Finance**, sidebar/RBAC customer, Control, APK, atau credential bot. Penambahan skema hanya SQLite privat milik bridge. Tidak commit/push/publish.
- Validasi: **19 tes** unit/integration lokal owner pairing, penolakan identitas asing, expiry, deduplikasi, socket auth, pilih thread, resume ID eksplisit, status/stop, conflict guard, kegagalan/keberhasilan tugas, pemulihan setelah restart; PHP lint; smoke Telegram 102 pemeriksaan dan notifier 10 pemeriksaan; kontrak artifact 12 dan preflight 18 pemeriksaan.
- Upgrade dua akun: **29 tes PASS**, termasuk sepuluh tes tambahan untuk migrasi idempotent/histori primary, pairing tambahan/cap dua akun, undangan bersamaan hanya menang sekali, kode dipakai dari akun yang salah, expiry/rotasi, pemisahan pilihan/status, satu tugas global, stop per pengirim, serta penerima nyata pada mock pengiriman dan error inbox. Smoke Telegram 102 dan notifier 10 kembali PASS. Tidak memanggil model atau mengirim pesan ke akun kedua fiktif untuk pengujian.
- Probe Codex nyata: thread diagnostik **baru**, dua giliran teks tanpa tool/file; ID sama saat resume dan kode konteks giliran pertama masih diingat. Thread diagnostik kemudian diarsipkan, tidak dihapus. Tidak mencoba melanjutkan thread bisnis pengguna untuk test.
- HTTP webhook dengan secret salah mendapat 403; secret benar + pesan dummy yang tidak memenuhi identitas owner mendapat 200/ack tanpa menjalankan tugas. Koneksi Unix socket sebagai user `www` lulus. Tes ini bukan bukti pairing akun/pekerjaan nyata dari HP sudah dilakukan.
- UAT tersisa: pairing **akun kedua** lewat DM dengan kode tambahan, pilih thread, tutup sesi Codex lain, lalu satu prompt read-only. Periksa balasan tiba di akun pengirim dan pilihan akun pertama tidak berubah sebelum memberi tugas edit.

Referensi protokol: [Codex App Server](https://learn.chatgpt.com/docs/app-server), [Codex non-interactive mode](https://learn.chatgpt.com/docs/non-interactive-mode), [Telegram Bot API](https://core.telegram.org/bots/api).
