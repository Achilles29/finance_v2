# Panduan setup customer dan pemeriksaan sebelum rilis

**Update 10 September 2026:** untuk penjualan baru gunakan source **0.1.0-alpha.11** dengan profil `CUSTOMER_CLEAN` dan seed `REFERENCE_ONLY`, setelah artifact serta integrasi Control lulus pemeriksaan. Paket alpha.10 yang disebut dalam bukti historis di bawah belum bersih dari aset/menu Namua; jangan dipakai sebagai paket jual bersih.

Instalasi bersih menampilkan usaha/katalog kosong, bukan produk contoh Namua. Nama/logo diisi melalui Profil Usaha; logo printer boleh kosong sampai logo customer diunggah. Pilihan Menu Book legacy tidak ditawarkan jika file desainnya tidak dibundel. Source development dan database staging tidak dikosongkan. Upgrade customer lama bukan clean-install/reset.

Detail integrasi untuk admin dan thread Control: [kontrak paket customer bersih](customer_clean_release_contract.md).

Status 2026-09-09, source kandidat **0.1.0-alpha.10**: panduan praktik web Linux; lihat **bagian 9 untuk owner**, **bagian 10 untuk admin server**. Ini **bukan pernyataan
seluruh aplikasi/installer/APK siap jual**. Status utama tetap pada roadmap
audit `_30` dan komersialisasi `_28`.

Cara paling mudah bagi owner: di Control buka **Release → Panduan praktik
Finance** (`/finance/practice`). Halaman itu menampilkan kandidat terbaru dan
tombol langkah berurutan. Batch 243 menyiapkan alpha.10 DRAFT release 38 dengan
3 artefak/5 evidence; belum melakukan review/publish/customer/aktivasi nyata.

## 1. Pengaturan lewat aplikasi — admin usaha

1. Masuk menggunakan akun yang memiliki akses **System → Profil Usaha & Tampilan**.
2. Isi nama dagang, nama legal bila ada, alamat, dan kontak.
3. Pilih logo PNG/JPG, lihat preview, lalu isi footer dokumen bila diperlukan.
4. Pada **Menu Book publik**, pilih:
   - **Katalog usaha** untuk customer baru;
   - **Tidak dipublikasikan** bila katalog belum boleh dibuka;
   - **Desain Namua lama** hanya untuk instalasi lama yang memang memakai desain itu.
5. Klik **Simpan Pengaturan Usaha**. Pemilihan template dan identitas disimpan
   bersama serta dicatat dalam audit. File logo/desain lama tidak dihapus.
6. Klik **Pilih produk publik**. Pada Landing Page → Menu, pilih produk yang
   boleh terlihat publik, kemudian buka **Lihat Menu Book tersimpan**.
7. Katalog menampilkan nama, kategori, deskripsi, dan harga jual produk aktif
   yang dipublikasikan. Data order, stok, HPP, serta identitas pembeli tidak
   ditampilkan. Promo/pajak/service bukan bagian perhitungan harga katalog.

Nama/alamat/logo outlet atau pengaturan cetak yang telah diisi tetap didahulukan.
Jika struk belum memakai logo utama, periksa **POS → Pengaturan Cetak**
(`/pos/printers/general`), jangan mengubah database secara manual.
Pengisian zona waktu/locale/mata uang pada profil belum merupakan konversi
transaksi historis atau dukungan multi-currency seluruh aplikasi.

Pengaturan lain tidak diubah massal:

| Keperluan | Halaman | Yang harus diperiksa admin |
| --- | --- | --- |
| Outlet dan terminal | `/pos/outlets-terminals` | Nama, alamat, terminal aktif dan pemilik perangkat |
| Metode pembayaran | `/pos/payment-methods` | Metode aktif dan rekening yang benar |
| Konten publik dan URL | `/landing-page?tab=config` | Kontak, tautan order/member, SEO, dan domain customer |
| Self Order | `/pos/self-order/settings` | Aktivasi, URL, dan alur penerimaan pesanan |
| Online Food | `/pos/online-food/settings` | Pengaturan layanan yang memang dipakai |
| Telegram | `/telegram/guide` lalu `/telegram/settings` | Ikuti panduan; jangan memakai token customer lain |
| Lisensi | `/system/license` | Mode pemantauan dan status verifikasi, bukan tombol naik paket |

Tautan tidak memberikan hak akses baru; RBAC halaman tujuan tetap berlaku.

## 2. Folder upload — admin server

Buka bagian **Pemeriksaan folder upload oleh server web** di Profil Usaha.
Hasilnya memakai akun PHP-FPM yang melayani aplikasi. Status:

- **Siap:** folder ada dan dapat ditulis akun PHP tersebut.
- **Belum dibuat:** folder belum tersedia.
- **Tidak bisa ditulis:** admin server harus meninjau owner/ACL.
- **Lokasi perlu diperiksa:** terdapat file/symlink yang tidak sesuai kontrak.

Contoh aaPanel staging ini (akun pool `www`, PHP 8.1):

```bash
cd /www/wwwroot/finance
runuser -u www -- /www/server/php/81/bin/php tools/install/upload_storage.php check
```

Bila folder belum ada dan parent memang telah diberikan hak tulis yang sesuai:

```bash
runuser -u www -- /www/server/php/81/bin/php tools/install/upload_storage.php prepare
```

Sesuaikan lokasi aplikasi, binary PHP, dan akun pool di server customer.
`prepare` hanya membuat folder upload yang tercantum pada policy; tidak
menghapus file, tidak mengubah owner/izin folder lama, dan menolak berjalan
sebagai root. Jangan memperbaiki dengan `chmod 777` seluruh project.
Jika parent tidak bisa ditulis, admin harus menentukan owner/ACL deployment
terlebih dahulu. Symlink upload ditolak oleh checker ini dan perlu desain
shared-storage yang disetujui tersendiri. Pemeriksaan ini tidak menggantikan
aturan webserver yang melarang eksekusi script pada folder upload.

## 3. Instalasi baru berbeda dengan pemindahan database lama

| Skenario | Kebijakan |
| --- | --- |
| Customer baru tanpa data | Database kosong, baseline + migration clean-install, reference seed, owner pertama, konfigurasi customer |
| Trial dari database aplikasi berjalan | Salin database ke **database baru milik instance trial**; lakukan upgrade hanya pada salinan; jangan jalankan clean seed/owner bootstrap |
| Update customer yang sudah berjalan | Backup + bukti restore, migration upgrade, health check, uji penerimaan, baru pindah layanan |

Sesuai rencana owner, aplikasi utama lama tetap berjalan tanpa menjalankan SQL
baru di sana. Saat trial, sumber data lama hanya disalin; migration ditujukan
ke salinan database pada aplikasi hasil deploy.

Periksa rencana urutan tanpa mengubah database:

```bash
php tools/install/finance_install_plan.php plan --mode=clean_install
php tools/install/finance_install_plan.php plan --mode=upgrade
```

Kedua command masih **plan-only**, bukan installer satu klik. Jangan
mengartikan output `status=ok` sebagai database sudah diinstal. Mekanisme
credential/migration/owner berada pada `deployment_secret_contract.md`.
Jangan menjalankan semua file folder `sql` dengan wildcard.

Hook Composer sekarang memakai PHP, bukan `sed`, dan melewati compatibility
patch bila dependency development tidak terpasang. Ini memperbaiki hook
instalasi; bukan sertifikasi seluruh installer Windows.

## 4. Sambungan lisensi — pisahkan penjual dan admin server

Mulai source alpha.9 tersedia agen `init → activate → poll` untuk Linux AMD64,
PHP 8.1 CLI dengan curl/sodium/posix. Sebanyak 54 pemeriksaan fixture termasuk
file, restart dan model aplikasi lulus. **Belum merupakan aktivasi customer
nyata, installer satu klik, atau penerimaan enforcement.** Penjual menjalankan
praktik melalui UI Control nanti; persiapan teknis tidak menunggu harga/kontrak.

### 4.1 Penjual: nanti melalui UI Control

Urutan menu yang sudah ada: **Customer → Instalasi → Subscription & lisensi**.
Customer, produk **NAMUA_FINANCE**, dan edisi harus sama pada instalasi dan
subscription. Pilih instance yang benar, lalu **Buat kode** pada detail lisensi.
Kode `nla_…` ditampilkan sekali dan berlaku satu jam. Serahkan melalui jalur
privat kepada admin server; jangan tempel ke grup, Git, screenshot atau command.
Belum perlu membuat customer/kontrak/kode apa pun saat membaca panduan ini.

### 4.2 Admin server: siapkan identitas sebelum meminta kode

Contoh di bawah untuk **instalasi baru terpisah**, bukan instruksi mengganti
staging/website utama. Ganti `customer-a`, `customer-a-pos`, lokasi PHP/source
dan grup `www` sesuai instalasi sebenarnya. Jangan memakai folder customer lain.
Seluruh source yang dijalankan root (termasuk target `/opt/finance/current`)
harus berasal dari paket verified, root-owned dan tidak dapat ditulis PHP-FPM.
Private state terpisah dari kode dan database, tidak ikut package/upload.

1. Admin Control menyediakan **public trust** lisensi NAMUA_FINANCE dari
   `/var/lib/namua-control/license-signing/trusted/NAMUA_FINANCE.json`.
   Verifikasi fingerprint melalui jalur admin tepercaya; jangan ambil dari URL
   yang belum dipercaya. Bila belum tersedia, admin Control menyiapkannya melalui
   prosedur signing key Control. **Private key penerbit tidak pernah ke Finance**;
   signing key paket release juga bukan trust lisensi.
2. Sebagai root, buat direktori **baru** berikut (parent juga root-owned dan
   tidak group/world-writable). Periksa lokasi yang sudah ada; jangan memperbaiki
   dengan chmod rekursif atau mengganti owner data lama:

   ```bash
   install -d -o root -g root -m 0755 /var/lib/finance
   install -d -o root -g www -m 0750 /var/lib/finance/customer-a
   install -d -o root -g www -m 0750 /var/lib/finance/customer-a/license
   install -d -o root -g root -m 0700 /var/lib/finance/customer-a/license/private
   install -d -o root -g www -m 0750 /var/lib/finance/customer-a/license/public
   ```

3. Simpan salinan public trust dari langkah 1 sebagai
   `/var/lib/finance/customer-a/license/private/issuer-trust.json`, root:root
   mode 0600. Jalankan sekali, memakai **Instance ID yang dipilih di Control**:

   ```bash
   /usr/bin/php /opt/finance/current/tools/licensing/finance_license.php init \
     --private-dir=/var/lib/finance/customer-a/license/private \
     --public-dir=/var/lib/finance/customer-a/license/public --web-group=www \
     --instance-id=customer-a-pos --control-origin=https://control.namuaprojects.com \
     --trust-file=/var/lib/finance/customer-a/license/private/issuer-trust.json
   ```

   Hasil `PROVISIONED` berarti identitas/kunci instance sudah disimpan; **belum
   menghubungi Control dan belum memakai slot aktivasi**. Jangan jalankan init
   lagi, menyalin private state ke mesin lain, atau mengarang Installation ID.
4. Pada pool PHP-FPM **milik instance ini**, tambahkan hanya tiga path berikut:

   ```ini
   env[FINANCE_LICENSE_TRUST_FILE] = /var/lib/finance/customer-a/license/public/trust.json
   env[FINANCE_LICENSE_IDENTITY_FILE] = /var/lib/finance/customer-a/license/public/identity.json
   env[FINANCE_LICENSE_CACHE_FILE] = /var/lib/finance/customer-a/license/public/runtime.json
   ```

   Contoh aaPanel: konfigurasi pool ada di `/www/server/php/81/etc/`; gunakan
   berkas pool customer yang benar, **bukan pool bersama semua website**.
   Path ini belum termasuk whitelist `deployment.json` bagian 6. Bila
   `open_basedir` aktif, izinkan hanya direktori `license/public` untuk reader;
   jangan izinkan `license/private`. Test konfigurasi dahulu, baru reload pool
   customer saat jadwal yang disetujui. Tidak perlu mengedit database.php.

### 4.3 Admin server: pasang kode dari UI dan sinkronkan

1. Setelah penjual menekan **Buat kode**, buka editor server sebagai root.
   Simpan **hanya kode** ke
   `/var/lib/finance/customer-a/license/private/activation-code.txt`.
   Pastikan root:root mode 0600. Jangan menaruh kode di argv/environment/log.
2. Jalankan sekali:

   ```bash
   /usr/bin/php /opt/finance/current/tools/licensing/finance_license.php activate \
     --private-dir=/var/lib/finance/customer-a/license/private \
     --public-dir=/var/lib/finance/customer-a/license/public --web-group=www \
     --code-file=/var/lib/finance/customer-a/license/private/activation-code.txt
   ```

   `PENDING` berarti Control menerima permintaan, **bukan lisensi sudah aktif**.
   Kode tidak disimpan dalam state agen; file input tetap privat dan tidak
   dihapus otomatis. Admin menangani retensinya setelah aktivasi terkonfirmasi.
3. Worker penerbit lisensi di **server Control** harus sudah disiapkan admin
   Control. Agen Finance tidak menerbitkan atau menyetujui lisensi sendiri.
   Kunci produk Finance disiapkan Batch 243. Sesudah owner membuat kode dan
   instance mengirim aktivasi PENDING, admin Control dapat menjalankan worker
   terbatas produk (bukan seluruh produk lain):

   ```bash
   CONTROL_LICENSE_PRODUCT_CODE=NAMUA_FINANCE php /www/wwwroot/control/tools/process_license_issuance.php
   ```

   Untuk operasional nanti, jadwalkan perintah tersebut pada server Control
   sebagai root, misalnya per lima menit, sesudah alur trial diterima. Jangan
   memakai `CONTROL_LICENSE_ISSUER_CONFIG` fixture atau kunci percobaan.
   Kemudian jalankan:

   ```bash
   /usr/bin/php /opt/finance/current/tools/licensing/finance_license.php poll \
     --private-dir=/var/lib/finance/customer-a/license/private \
     --public-dir=/var/lib/finance/customer-a/license/public --web-group=www
   ```

   Hasil yang diharapkan: `status=ACTIVE`, `verified=true`, `connection=SYNCED`.
   Buka Finance **System → Lisensi & Aktivasi** (`/system/license`): instance,
   status sambungan, edisi dan waktu sinkron harus sesuai Control.
4. Setelah percobaan berhasil, admin dapat memasang template
   `tools/licensing/systemd/finance-license.service.example` dan `.timer.example`
   sebagai `/etc/systemd/system/finance-license.service` dan `.timer`.
   Sesuaikan semua path, binary PHP (aaPanel: `/www/server/php/81/bin/php`), grup,
   dan nama unit bila lebih dari satu instance. Jalankan `systemd-analyze verify`
   atas kedua unit sebelum `systemctl daemon-reload` dan
   `systemctl enable --now finance-license.timer`. Template menjadwalkan polling
   sekitar lima menit; **tidak dipasang atau diaktifkan otomatis pada staging**.
   Periksa `systemctl status finance-license.timer` dan
   `journalctl -u finance-license.service -n 20` tanpa mengirim credential.

### 4.4 Jika belum berhasil

| Status/pesan | Arti dan tindakan |
| --- | --- |
| `PENDING` | Permintaan diterima, penerbitan belum selesai; periksa worker dan detail aktivasi Control. |
| `SYNC_UNAVAILABLE` / exit 2 | Koneksi/response ditolak; cache sah tidak dihapus. Periksa HTTPS, waktu mesin dan status Control, lalu poll lagi. |
| `REQUEST_UNCERTAIN` / `ACTIVATION_ALREADY_ATTEMPTED` | Request pertama mungkin sudah diterima meskipun koneksi putus. Jangan init ulang/mengganti kunci. Setelah memeriksa aktivasi di Control, jalankan perintah `recover` dengan argumen privat yang sama seperti `activate` dan file kode asli. Recovery membuktikan kepemilikan kunci lama dan mengganti credential polling, bukan memakai slot baru. Lalu `poll`. |
| `ALREADY_PROVISIONED` | Identitas sudah ada atau init sebelumnya belum lengkap. Simpan seluruh state; admin memeriksa file yang kurang tanpa menghapus private key. |
| `CLOCK_ROLLBACK` | Jam mundur lebih dari toleransi 5 menit; perbaiki sinkronisasi waktu, bukan watermark cache. |
| `LEASE_REPLAY` / `LEASE_SEQUENCE_CONFLICT` | Dokumen lebih lama atau isi berbeda pada waktu penerbitan yang sama ditolak; admin memeriksa penerbitan Control. |
| `MANAGED_CACHE_UNAVAILABLE` | Path/izin/trust/cache tidak sesuai; periksa pool dan owner. Tidak otomatis mengambil hak dari tabel SQL. |
| `REVOKED` | Aktivasi dicabut di Control. Jangan memulihkan dokumen lama untuk mencoba membuka kembali. |

**Mode tetap AUDIT_ONLY.** Tidak mengaktifkan
`FINANCE_LICENSE_ENFORCEMENT_APPROVED`, mengubah RBAC, atau memblokir kasir.
Lease/grace mengikuti tanggal signed, berbeda dari berakhirnya maintenance.
Proteksi restart/rollback DB telah diuji; ini bukan proteksi terhadap root yang
mengembalikan seluruh snapshot private state sekaligus jam mesin. Windows ACL,
native guard, pairing/limit terminal, enforcement dan UAT nyata masih terbuka.

## 5. Checklist sebelum pelanggan benar-benar memakai aplikasi

- [ ] Source terpilih dan commit cutoff disetujui; tidak mengikutsertakan backup/upload/secret.
- [ ] Paket bersih, signature artefak, serta proses delivery Control untuk Finance lulus.
- [ ] Clean-install atau upgrade pada **database salinan** lulus; restore dan rollback terbukti.
- [ ] Nama, logo, domain, katalog, outlet, dan dokumen benar untuk customer.
- [ ] Pajak/service, rekening, integrasi, scheduler dan privacy diperiksa sesuai kebutuhan customer.
- [ ] Uji alur web per peran pengguna dan printer fisik selesai.
- [ ] Kontrak paket/harga/support dan persetujuan go-live disepakati owner.
- [ ] APK diuji terpisah jika akan ikut dijual; saat ini ditunda dan belum lulus.

Panduan operasional seluruh modul, latihan pengguna, dan pilot non-Namua
tetap pekerjaan C5. File ini tidak menggantikan pekerjaan tersebut.

## 6. URL dan runtime instalasi — hanya admin server

Mulai source alpha.8, admin dapat memakai **satu file JSON privat** tanpa edit
`database.php` atau `config.php`. Ini opt-in: konfigurasi staging lama tidak
diganti otomatis. `.user.ini` staging tidak boleh disalin ke customer karena
berisi pembatasan direktori server lama; paket baru mengecualikannya.

Contoh lokasi: `/etc/finance/customer-a/deployment.json`. Buat lewat editor
server sebagai root, bukan melalui browser, Git, atau halaman Profil Usaha.
Isi contoh berikut dengan nilai instalasi customer yang sebenarnya:

```json
{
  "FINANCE_ENCRYPTION_KEY": "GANTI_DENGAN_KUNCI_ACAK_KHUSUS_INSTALASI",
  "FINANCE_DB_HOST": "localhost",
  "FINANCE_DB_NAME": "database_customer_a",
  "FINANCE_DB_USER": "akun_database_customer_a",
  "FINANCE_DB_PASSWORD": "GANTI_DENGAN_PASSWORD_DATABASE_CUSTOMER",
  "FINANCE_BASE_URL": "https://kasir.customer.example/",
  "FINANCE_SESSION_PATH": "/var/lib/finance-customer-a/sessions",
  "FINANCE_SESSION_COOKIE": "finance_customer_a",
  "FINANCE_LOG_PATH": "/var/lib/finance-customer-a/logs",
  "FINANCE_CACHE_PATH": "/var/lib/finance-customer-a/cache"
}
```

Nilai `GANTI_...` bukan credential siap pakai. Buat encryption key dengan
`openssl rand -hex 32` di terminal privat, simpan sekali, dan masukkan dalam
backup rahasia terenkripsi; jangan menggantinya setiap restart/update.
DB_HOST boleh berupa path socket MariaDB, misalnya `/tmp/mysql.sock`, jika
itulah socket di server. Jangan menyalin akun database staging ke customer.

File JSON harus `root:<grup-pool-PHP-customer>` dengan mode **0640**, seluruh
parent root-owned dan tidak group/world-writable. Tiga direktori runtime
di atas harus sudah dibuat di luar source, dimiliki akun pool customer,
mode **0700**. Gunakan akun pool dan direktori tersendiri tiap customer.
Pengaturan environment eksplisit mengalahkan nilai JSON; hapus override lama
dari pool **customer tersebut** bila ingin memakai JSON sepenuhnya.

Pada konfigurasi pool PHP-FPM customer, tambahkan:

```ini
env[CI_ENV] = production
env[FINANCE_DEPLOYMENT_FILE] = /etc/finance/customer-a/deployment.json
```

Uji konfigurasi pool dan reload **layanan customer yang benar**. Jangan
mengganti pool/vhost aplikasi lama. Jika ada `open_basedir`, admin harus
memasukkan source customer, file konfigurasi privat, runtime dan temporary
directory customer yang benar. JSON invalid/tidak aman ditolak sebelum
aplikasi melakukan koneksi database; respons browser tetap generik.

`tools/install/LinuxWebProfile.php` menyediakan renderer konfigurasi Nginx
dan PHP-FPM **percobaan Linux loopback HTTPS**, bukan provisioner domain publik
atau installer Windows. Output hanya menjalankan front-controller `index.php`,
menolak direktori internal/script upload, memakai socket privat serta
`CI_ENV=production`. Pada Nginx aaPanel yang memuat Lua, `lua_root` harus
ditetapkan ke direktori modul vendor; Nginx biasa tidak memakai opsi itu.
Jangan membuka port percobaan ke internet untuk menggantikan deployment resmi.

## 7. Penerimaan sebelum serah-terima — owner dan admin usaha

Panduan ini terikat source kandidat alpha.9; pengujian percobaan Linux bukan
bukti customer nyata telah lulus. Keputusan berikut diisi saat praktik/pilot
oleh owner, **bukan prasyarat melanjutkan persiapan engineering**:

| Keputusan | Sumber/penanggung jawab | Status |
| --- | --- | --- |
| Paket dan add-on | Manifest NAMUA_FINANCE yang sama dengan Control; owner memilih edisi/limit | Katalog draft, belum penawaran final |
| Harga dan biaya implementasi/support | Owner menetapkan nominal dan cakupan tertulis | Menunggu owner |
| Kontrak, data policy, SLA | Dokumen disetujui pihak berwenang; waktu respons dan jam layanan jelas | Menunggu dokumen final |
| Customer pilot dan domain | Owner menunjuk customer/instance/domain serta PIC | Belum ditetapkan pada batch ini |
| Batas produk | Linux/PHP 8.1/MariaDB 10.11 diuji; Windows dan bug operasional APK belum lulus | Harus tertulis di penawaran |
| Go-live | Hasil install/update/restore, UAT kasir dan printer, serta risiko tersisa | Belum disetujui |

Latihan admin usaha, berurutan: masuk sebagai owner → ganti nama/logo → atur
outlet/terminal/rekening → cek pajak/service sesuai kebutuhan → cetak satu
struk percobaan → undang pengguna dengan role yang tepat → minta pengguna
tersebut mencoba alurnya → catat hasil. Pengujian wajib memakai data percobaan
atau salinan customer yang disetujui, bukan mengubah aplikasi utama lama.

Admin server memperlihatkan bukti backup berhasil **dipulihkan** ke database
terpisah, bukan hanya file backup tersedia. Update tidak menjalankan kembali
seed clean-install atau membuat owner baru. Catatan seed dari instalasi awal
tetap dipertahankan dan checksum-nya diperiksa saat health-check upgrade.

## 8. Penanganan masalah pilot — rancangan SOP support

1. Pengguna melapor: URL, versi aplikasi, waktu kejadian, halaman/tindakan,
   pesan yang tampil, dan dampaknya. Jangan mengirim password, token bot,
   API key, nomor rekening penuh, atau dump transaksi ke grup support.
2. Admin usaha memeriksa sesi, hak akses, pengaturan outlet/printer dan koneksi.
   Jangan menghapus transaksi untuk mencoba memperbaiki error.
3. Admin server memeriksa layanan, kapasitas disk, sertifikat HTTPS, koneksi
   database, izin upload, dan log instalasi yang sesuai. Bukti disamarkan.
4. Jika transaksi berisiko rusak: hentikan tindakan terkait, pertahankan bukti
   dan backup, eskalasi ke engineer. Jangan menekan bayar/sinkron berulang.
5. Rollback hanya setelah snapshot kode + database + upload + konfigurasi
   privat cocok dan prosedur pemulihan teruji. Simpan kondisi gagal untuk audit.
6. Catat penyebab, versi perbaikan, hasil uji pengguna, dan persetujuan penutupan.

SOP ini belum menetapkan SLA berbayar atau kanal support resmi. Walkthrough
pengguna awam, pelatihan per modul, dan pilot Starter/Operations/Control tetap
perlu dilaksanakan; keberadaan dokumen tidak mencentang penerimaannya.

## 9. Urutan praktik penjualan nanti — owner melalui UI Control

Target pertama adalah **latihan penjualan web Linux pada instalasi baru**, bukan
mengganti aplikasi utama atau langsung menjual APK. Bukti kesiapan kandidat terbaru
ada di bagian awal `control_release_delivery.md` dan tabel C0–C5 `_28`.
Tidak perlu membuka terminal untuk mengisi customer dan subscription. Admin server
menangani pemasangan pada bagian 10; token dari UI diserahkan melalui kanal privat.

Siapkan dua akun berwenang: pemohon dan pemeriksa. Control melarang orang yang
mengajukan release/deployment menyetujui permohonannya sendiri. Ini bukan error.
Jika diminta konfirmasi identitas, masukkan password/kode keamanan akun sendiri,
kembali ke halaman tadi, lalu ulangi tombol tindakan yang belum diproses.

1. **Periksa produk dan versi.** Pastikan NAMUA_FINANCE dan edisi yang dipilih
   sesuai katalog. Paket bertanda alpha/internal candidate bukan otomatis rilis
   publik; evidence dan persetujuan release tetap diperiksa.
2. **Customer** (`/customers/create`). Owner mengisi identitas customer latihan
   dan PIC, menggunakan data yang disetujui, lalu menyimpan.
3. **Instalasi** (`/instances/create`). Pilih customer tadi, produk/edisi, domain
   trial terpisah, timezone dan environment yang sesuai. Catat Instance ID.
   Secret heartbeat bila ditampilkan berbeda dari kode aktivasi lisensi.
4. **Subscription & lisensi** (`/licensing/create`). Pilih customer dan edisi
   yang sama, isi kode unik/batas server. Status ACTIVE hanya dipilih ketika
   owner memang menyetujui aktivasi. Harga/kontrak adalah keputusan owner;
   jangan menganggap formulir subscription sebagai bukti pembayaran.
5. **Release** (`/releases`). Cari **NAMUA_FINANCE 0.1.0-alpha.10**. Periksa
   status DRAFT/ALPHA, tiga file paket dan bukti pengujian. Jangan memilih alpha
   lama hanya karena urutannya lebih atas. Pemohon mengirim review; pemeriksa
   menyetujui; owner memublikasikan hanya jika setuju untuk trial terbatas.
   Label ALPHA tidak berubah menjadi stabil oleh tombol publish.
6. **Deployment** (`/deployments/create`). Pilih instalasi langkah 3, release
   tersebut dan aksi DEPLOY. Simpan → Kirim untuk approval → pemeriksa berbeda
   menekan Setujui deployment → Terbitkan token instalasi. Simpan token yang
   hanya muncul sekali dan berlaku satu jam; jangan kirim ke grup umum.
   RUNNING artinya token/rencana tersedia, **bukan aplikasi sudah terpasang**.
   Admin mengunduh, memeriksa signature, memasang dan memeriksa web, lalu mengirim
   receipt. Baru setelah itu Control menunjukkan SUCCEEDED.
7. **Buat kode** pada detail subscription, setelah admin siap menerima kode.
   Admin menjalankan langkah 4.2–4.3; owner melihat status di detail aktivasi
   Control dan mencocokkannya dengan halaman Lisensi & Aktivasi Finance.
8. **Latihan customer.** Login, identitas/logo/outlet/rekening, transaksi uji,
   struk, backup/restore dan update/rollback. Catat hasil; jangan mengklaim APK
   atau printer fisik lulus hanya dari tes web.
9. **Persetujuan pemakaian.** Owner meninjau hasil, batas produk, kontrak/support
   dan risiko, baru menyetujui go-live/publikasi. Langkah ini tidak dilakukan
   otomatis oleh engineer atau oleh keberhasilan unit test.

Token hilang/unduhan putus: buka detail deployment RUNNING, bagian **Token hilang
atau unduhan terputus?**. Minta admin menghentikan installer lama dan memeriksa
hasilnya; isi alasan dan konfirmasi, baru terbitkan pengganti. Token lama dicabut,
rencana/persetujuan tetap. Admin memakai folder unduhan baru dan menyimpan folder
lama untuk audit. Jangan mengulang installer pada database setengah terpasang.

Praktik owner/customer nyata belum dilakukan otomatis. Pairing/limit/native guard,
enforcement, Windows, APK dan printer fisik tetap mengikuti checklist `_28`.

## 10. Pemasangan paket dan pemulihan — admin server, bukan operator UI

### 10.1 Pemisahan tugas dan file

Pilih domain trial dan **database baru**; aplikasi utama lama tidak menjadi
target SQL. Migrasi data lama nanti melalui backup ke database salinan setelah
mapping/backup disetujui, bukan menjalankan semua SQL pada database operasional.
Praktik awal memakai database kosong agar kegagalan bisnis historis tidak tercampur.

- Tooling: checkout rilis terverifikasi, root-owned, contoh `/opt/finance-toolkit`.
- Unduhan dan konfigurasi CLI: root:root 0700/0600, di luar webroot.
- Code release: direktori baru, root-owned 0755, tidak writable oleh akun web.
- Runtime: direktori tersendiri root:grup-pool 0750; installer membuat session,
  log/cache/tmp privat serta folder upload writable oleh akun pool yang ditunjuk.
- `deployment.json`: bagian 6, root:grup-pool 0640; DB/URL/encryption key/path harus
  sesuai konfigurasi CLI. Tidak perlu mengubah `database.php` atau `config.php`.
- Sertifikat HTTPS, PHP-FPM 8.1, MariaDB server 10.11, Nginx, Composer dan akun
  pool non-root harus tersedia. Profil tool ini **hanya listen loopback HTTPS**;
  vhost/reverse proxy domain publik harus dipasang admin dengan TLS dan akses
  terarah ke port instance. Jangan membuka direktori runtime sebagai document root.

### 10.2 Unduh paket dari rencana Control

Buat JSON privat `delivery-job.json` dengan field `state_dir` (direktori 0700),
`control_origin` (origin HTTPS Control tanpa path), `install_token` (dari UI),
`instance_id` dan `primary_domain` persis registry. Token tidak ditulis di perintah:

```bash
php /opt/finance-toolkit/tools/install/control_delivery.php fetch \
  --job-file=/var/lib/finance/customer-a/private/delivery-job.json \
  --trust-file=/var/lib/finance/customer-a/private/release-trust.json
```

`VERIFIED` membuktikan tiga artefak sesuai hash/signature, belum memasang aplikasi.
Trust adalah **public key release NAMUA_FINANCE dari admin Control**, bukan key
lisensi dan bukan file yang dipercaya hanya karena ikut paket. Unduhan ditulis
streaming; file `.partial`/status UNCERTAIN tidak otomatis dipakai atau diulang.

### 10.3 Konfigurasi executor (file privat root 0600)

Contoh struktur `/var/lib/finance/customer-a/private/config.json`; sesuaikan
path yang benar-benar ada. Jangan isi placeholder lalu langsung menekan Enter.

```json
{
  "private_dir": "/var/lib/finance/customer-a/private",
  "runtime_dir": "/var/lib/finance/customer-a/runtime",
  "release_root": "/opt/finance/customer-a/releases/alpha10",
  "signed_manifest": "/var/lib/finance/customer-a/download/finance-0.1.0-alpha.10.release.json",
  "trust_file": "/var/lib/finance/customer-a/private/release-trust.json",
  "deployment_file": "/var/lib/finance/customer-a/deployment.json",
  "defaults_extra_file": "/var/lib/finance/customer-a/private/database.cnf",
  "database_name_file": "/var/lib/finance/customer-a/private/database.name",
  "owner_file": "/var/lib/finance/customer-a/private/first-owner.json",
  "php_fpm": "/www/server/php/81/sbin/php-fpm",
  "nginx": "/www/server/nginx/sbin/nginx",
  "composer": "/usr/bin/composer",
  "mime_types": "/www/server/nginx/conf/mime.types",
  "lua_root": "/www/server/nginx/lib/lua",
  "user": "finance_customer_a",
  "group": "finance_customer_a",
  "port": 18443,
  "tls_certificate": "/var/lib/finance/customer-a/tls/fullchain.pem",
  "tls_key": "/var/lib/finance/customer-a/tls/private.key",
  "mode": "clean_install"
}
```

`first-owner.json` tepat tiga field username/email/password kuat; file `.cnf`
bagian `[client]` berisi host/protocol/user/password untuk **satu database saja**;
`.name` hanya nama database. DB wajib kosong, parent release sudah dibuat tetapi
direktori release tujuan belum ada. Akun/port berbeda untuk instance simultan.
Nginx tanpa Lua tidak memerlukan `lua_root`. Lisensi opsional menggunakan
`license_public_dir` dari bagian 4; tidak memasukkan private key ke pool web.

Jalankan berurutan, hentikan bila salah satu tidak lulus:

```bash
php /opt/finance-toolkit/tools/install/finance_instance.php stage --config=/var/lib/finance/customer-a/private/config.json
php /opt/finance-toolkit/tools/install/finance_instance.php install --config=/var/lib/finance/customer-a/private/config.json
php /opt/finance-toolkit/tools/install/finance_instance.php start --config=/var/lib/finance/customer-a/private/config.json
php /opt/finance-toolkit/tools/install/finance_instance.php health --config=/var/lib/finance/customer-a/private/config.json
```

Urutan hasil: STAGED → PREPARED → RUNNING → PASS. Tool mempertahankan file/log/
database bila gagal, bukan menghapus agar retry terlihat bersih. Sesudah start,
admin tetap memeriksa domain publik dan owner menjalankan latihan UI bagian 7.
`health` ini mengecek integritas source, ledger/seed/owner dan login HTTPS, bukan
jaminan semua perangkat/proses bisnis sudah lolos UAT.

Receipt menggunakan **secret heartbeat instance** dari UI, bukan token instalasi
atau kode lisensi. Simpan JSON privat dengan `instance_id`, `environment`,
`key_id`, `secret`, lalu:

```bash
php /opt/finance-toolkit/tools/install/control_delivery.php receipt \
  --job-file=/var/lib/finance/customer-a/private/delivery-job.json \
  --identity-file=/var/lib/finance/customer-a/private/heartbeat.json \
  --result-file=/var/lib/finance/customer-a/private/result.json
```

Harus ACKNOWLEDGED dan deployment Control SUCCEEDED. Jika balasan receipt putus,
perintah yang sama aman diulang: ID dan payload lama dipakai kembali. Jangan
mengarang result.json untuk mengubah status Control.

### 10.4 Upgrade dan kembali ke versi lama

1. Umumkan maintenance; hentikan layanan dan scheduler penulis untuk instance
   target. `finance_instance.php stop --config=CONFIG_LAMA` hanya menghentikan PID
   yang cocok dengan konfigurasi tersebut; tidak me-reload seluruh Nginx staging.
2. Buat backup konsisten DB, inventory/checksum upload dan snapshot konfigurasi.
   Restore backup **ke DB baru kosong** memakai akun khusus DB itu. DB lama
   tetap disimpan. Jangan memasukkan dump berisi CREATE DATABASE/USE database
   lama dan jangan restore menggunakan root pada database operasional.
3. Konfigurasi baru: `mode=upgrade`, `backup_file`, `backup_sha256`,
   `previous_config_file=CONFIG_LAMA`, `from_schema` dari health lama; path code,
   private/runtime dan DB baru berbeda. Pertahankan encryption key, URL dan
   identitas lisensi. Jalankan stage/install/start/health di konfigurasi baru.
   Installer menyalin file upload yang diizinkan dengan checksum tanpa mengubah
   file code signed; benturan/symlink membuat proses berhenti untuk inspeksi.
4. Sebelum menerima transaksi, cocokkan profil/logo, jumlah tabel/ledger, login
   dan hasil pemeriksaan. Setelah PASS, kirim receipt deployment versi baru.
5. Bila gagal **sebelum ada transaksi baru**: hentikan konfigurasi baru, start
   konfigurasi lama, jalankan health lama. Source, DB, upload dan secret lama
   tetap utuh; tidak perlu SQL down atau menghapus kondisi gagal. Laporkan
   rollback dengan rencana Control yang memang beraksi ROLLBACK, bukan receipt
   SUCCEEDED untuk release yang gagal.
6. Bila transaksi sudah masuk ke versi baru, rollback berpotensi meninggalkan
   transaksi tersebut. Bekukan penulisan, simpan kedua sisi dan minta keputusan
   rekonsiliasi owner; jangan kembali ke snapshot secara diam-diam.

Tool tidak memasang boot service atau scheduler customer secara global. Admin
menjadwalkan startup/scheduler per instance setelah praktik diterima. Windows,
native guard dan migrasi bisnis baru di masa depan memerlukan acceptance sendiri.
