# Paket Finance untuk Control — kandidat internal

## Update clean distribution — Batch 244, 10 September 2026

Source berikutnya **0.1.0-alpha.11** memakai profil `CUSTOMER_CLEAN` v1 dan `REFERENCE_ONLY`. Paket alpha.10 di bawah adalah bukti historis: masih memuat aset/menu Namua, sehingga **bukan paket bersih untuk dijual**. Jangan menimpa artifact/tag/signature lama.

Thread Control wajib mengikuti [kontrak customer-clean](customer_clean_release_contract.md): default build bersih, validasi audit TAR, tambahan metadata/claim, dan gate sebelum publish. Finance hanya mengubah repository Finance; UI, registry, plan dan database Control tetap milik thread Control. Belum ada publish/register/deploy operasional oleh batch ini.

## Kandidat praktik saat ini — Batch 243

**0.1.0-alpha.10 siap untuk praktik web Linux terbatas**, tercatat DRAFT/ALPHA
di Control: release **38**, UUID `cdcbd62d-f030-4842-b2f3-db446f03c829`.
Mulai di Control **Release → Panduan praktik Finance** (`/finance/practice`).
Tiga artefak dan lima evidence wajib tersedia; belum review/approve/publish,
belum customer/subscription/aktivasi nyata dan belum penerimaan produksi.

- Cutoff final: `15f9f62849e3ba91bd029a53acd0686748e93f39`, epoch `1788925696`,
  tag **finance-web-alpha.10-verified-cutoff-20260909**. Tag percobaan
  `finance-web-alpha.10-cutoff-20260909` menunjuk f7b92f1 yang belum lolos
  preflight; **bukan sumber paket final**. Riwayat tidak dihapus/ditulis ulang.
- Folder: `/var/lib/finance-release-20260909.5hPSTs`, root-private.
- File: `finance-0.1.0-alpha.10.tar` dan sidecar `.release.json/.release.sig.json`.
- 1.645 file, **597.483.520 bytes**; TAR SHA256:
  `e10d6afd29deb611e40fab25206f51404a90586cf46ad79760c9d7edcec9458d`.
- SHA256 manifest Control:
  `52d51ac39f9bf29a5f64c9a43caa3907782c7583bfc52bc2bddd89162523e2f3`.
- SHA256 inner manifest:
  `4dbd9a700a0829c148c3a01c262e321aee212324d7c2b0e804ce82778d4a088a`.
- Bukti: `quality-release.log` (113 PASS), `build.log`, `practice-evidence.json`
  dan `rollback-acceptance.json` dalam folder paket; verifier Control PASS.
- Clean install alpha.10: `/var/lib/finance-web-20260909.cszJso`, health dan
  22 tes HTTPS PASS. Upgrade dari alpha.9: `/var/lib/finance-web-20260909.PnTYyw`,
  DB salinan, 296 checksum tabel cocok, upload sama, 22 HTTPS PASS, cache lisensi
  Control aktual terbaca dan rollback layanan ke alpha.9 PASS.
- Control terisolasi `/var/lib/finance-control-20260909.wmwnYi`: 18 lisensi,
  9 delivery/receipt paket alpha.10, 12 UI deployment/reissue dan 2 akses panduan
  PASS. Simulasi customer/PUBLISHED di DB fixture tidak sama dengan Control asli.
- Database fixture, backup, log dan kunci uji dipertahankan. Listener percobaan
  dihentikan setelah pemeriksaan; layanan Finance/Control utama tidak direstart.

SQL bisnis/schema tetap finance-20260907, baseline clean-install-20260909,
16 migrasi managed; **tidak ada SQL baru untuk dijalankan pada aplikasi lama**.
Upgrade lintas versi kode tidak membuktikan semua migrasi SQL masa depan aman.
APK/Windows/printer fisik/native guard/enforcement dan pilot produksi tetap
terbuka di `_28`. Panduan server bagian 10 dan panduan owner bagian 9 pada
`customer_setup_and_release_guide.md`; target domain/scheduler ditentukan saat
owner memilih instalasi trial, bukan dibuatkan customer oleh engineer.

Perubahan Control disertakan dalam backup/handoff privat terpisah
`/var/lib/finance-practice-20260909.cS9jNa/`; tidak berada dalam TAR Finance.
Key lisensi NAMUA_FINANCE sudah dibuat di key store root-only, bukan key dari
fixture dan bukan lisensi customer. Worker mendukung pembatasan produk lewat
`CONTROL_LICENSE_PRODUCT_CODE=NAMUA_FINANCE`; belum diaktifkan otomatis untuk
subscription operasional. Jangan menjalankan worker semua produk untuk latihan.

Catatan alpha.9 dan versi lebih lama di bawah adalah riwayat, bukan pilihan
kandidat terbaru.

Update Batch 241: **alpha.9 privat**, cutoff
`4d315484cdc60bdaf1894885c8312c167e829cad`, tag
`finance-web-alpha.9-cutoff-20260909`, source epoch `1788920512`.
Build, signature dan verifier Finance maupun Control read-only PASS.
112 gate release PASS; 54 fixture agen/model/izin PASS. Kandidat ini belum
diregistrasi/publish atau dipakai mengaktifkan customer melalui Control.

- Arsip: `/var/lib/finance-release-20260909.WpqNVe/finance-0.1.0-alpha.9.tar`.
- 1.634 file, 597.381.120 bytes; SHA256
  `8ed5ff28bc7c787f3de197f9e1721e623d2f340add8c1aeb8338325d7bd90bc7`.
- Manifest/signature: nama dasar yang sama + `.release.json` dan `.release.sig.json`.
- Schema finance-20260907, 16 SQL managed tetap; **tidak ada SQL baru**.
- Tambahan utama: agen lisensi, signed cache/model/UI, template scheduler dan
  panduan. Bukan native guard atau enforcement yang sudah diterima.

Folder privat tetap root:root 0700; belum disalin ke registry Control. Bukti
upgrade DB/HTTPS alpha.8 di bawah adalah hasil versi itu, bukan acceptance
deployment alpha.9. Owner menjalankan praktik UI nanti setelah gerbang teknis
selesai. Registrasi CLI yang dijelaskan di bawah bukan instruksi eksekusi sekarang.

Update Batch 239: alpha.8 cutoff `39a82105ef9003d91f22595b10d661711381029d`,
111 gate otomatis PASS, 1.626 file signed dan **DRAFT/ALPHA** di Control.
Web dari paket, 22 HTTPS test dan health upgrade dari salinan DB sintetis
lulus; tidak ada edit source setelah ekstraksi. Alpha.7 tetap immutable.
Ini belum installer final, publish/claim/receipt, migrasi lintas versi dengan
DDL baru, atau pilot customer. Batas lengkap berada di tabel kanonis C3 `_28`.

Saat ekstraksi TAR, direktori kode harus root-owned dan dapat dilalui akun
pool (contoh 0755); file kode 0644/0755 mengikuti manifest, tidak writable
oleh PHP. TAR berisi file tanpa entry direktori, sehingga ekstraksi dengan
umask 0077 dapat membuat parent kode 0700 dan login HTTP gagal meskipun checksum
benar. Gunakan umask 0022 **hanya saat mengekstrak kode ke direktori baru yang
telah divalidasi**, kemudian kembali ke umask 0077 untuk credential/backup.
Jangan chmod rekursif seluruh project: upload dan secret mempunyai owner/mode
berbeda. Runtime/upload/dependency dibuat setelah verifikasi source exact;
health sesudah operasi harus tetap memverifikasi semua byte source signed.

Alur ini menyiapkan bukti paket dan registrasi DRAFT, bukan publish atau
aktivasi lisensi. Executor database belum merupakan deployment web/customer
lengkap. Jalankan sebagai admin rilis Linux; jangan melalui browser.

Update Batch 235: kandidat alpha.7 memakai PHP 8.1 dan MariaDB **10.11**
sesuai persetujuan owner. Alpha.3 tetap DRAFT lama, byte dan kontrak 10.6-nya
tidak diubah. Gate cold-cache 360 detik/outer 420 detik, cache per checkout;
tidak perlu warming manual. Status bukti instalasi terbaru ada pada checklist
C3 di roadmap `_28` dan execution log, bukan disimpulkan dari adanya DRAFT.

Alpha.7 sudah lulus instalasi DB kosong dari paket signed: 296 tabel,
16 migrasi, satu owner, 209 halaman/permission, 249 menu, health exact.
Versi alpha.4–6 gagal pada uji instalasi dan disimpan sebagai bukti; jangan
dipakai untuk deployment customer. Kegagalan indeks baseline, jumlah menu
dan pemeriksaan izin guide diperbaiki pada alpha.7. SQL managed lama tidak
diubah; baseline SQL khusus instalasi baru jangan dijalankan ke DB lama.

`mariadb --version` hanya menunjukkan versi **client**. Installer memeriksa
versi server dengan `SELECT VERSION()` pada database target dan wajib cocok
dengan kontrak signed paket. Probe runtime CLI melaporkan client secara terpisah;
kelulusannya tidak menggantikan pemeriksaan server atau health database.

Registrasi CLI Control:

```bash
php tools/register_finance_release.php CHECKOUT_FINANCE_ROOT MANIFEST.release.json --actor-id=ID_OPERATOR
```

Path harus absolut dan validator milik root. Actor harus OWNER/RELEASE_MANAGER
aktif. Hasil selalu DRAFT/ALPHA dengan tiga artefak privat. Import identik
UNCHANGED, isi berbeda pada versi sama ditolak. Tidak mengubah limit HTTP
25 MiB. File 0640/root:www, direktori 0750; web hanya membaca file kandidat.
Jika COMMIT kehilangan koneksi, file dipertahankan untuk read-back/recovery;
jangan menghapusnya sebelum hasil commit dipastikan.

Executor database `tools/install/clean_install_database.php apply` menerima
enam parameter file/path: `--release-root`, `--signed-manifest`, `--trust-file`,
`--defaults-extra-file`, `--database-name-file`, `--owner-file`. Source harus
ekstraksi persis paket signed, database harus kosong dan akun DB terbatas
ke database itu. Tidak menerima credential langsung di argv, tidak menghapus
DB agar bisa retry, dan tidak mem-publish/deploy web. Urutan signature,
source exact, empty-state/runtime guard, baseline, migration, bootstrap dan
health bersifat wajib. Kegagalan DDL tidak bisa di-rollback otomatis; simpan
DB dan file untuk inspeksi. Instalasi DB yang lulus pun belum membuktikan
web/customer sudah ter-deploy: dependency, secret, folder, web server dan UAT
masih memerlukan bukti tersendiri.

1. Tetapkan commit lokal source. Buat checkout bersih terpisah dengan
   `git worktree add --detach /var/lib/finance-release/source COMMIT`.
   Catatan lokal, upload, credential dan log pada staging tidak dipindah atau
   dihapus untuk membuat status Git terlihat bersih.
2. Dari checkout tersebut, jalankan builder yang sudah menyediakan gate
   preflight, analisis statis dan advisory dependency:

   ```bash
   php tools/release/build_release_artifact.php --output=/var/lib/finance-release/finance-alpha.tar --source-epoch=COMMIT_UNIX_TIME
   ```

   Catatan Batch 228: checkout baru/cold cache melampaui batas PHPStan gate
   150 detik. Analisis penuh dengan command PHPStan yang sama, scope seluruh
   `application`, satu worker dan memory 2G lulus nol error dalam batas
   diagnostik 300 detik. Builder kemudian dijalankan ulang dan semua gate
   lulus. Jangan menonaktifkan gate atau menyalin hasil PASS dari source lain.
   Budget cold-start dan isolasi cache diperbaiki pada Batch 229 dan masuk
   kandidat alpha.7; panduan ini belum mengklaim deployment clean-machine selesai.

3. Control menyediakan key release khusus produk `NAMUA_FINANCE` melalui
   `tools/provision_release_signing_key.php NAMUA_FINANCE`. Hanya sekali oleh
   root; file private/trust berada di `/var/lib/namua-control/release-signing/`.
   Jangan memakai key Penatausahaan, key lisensi, atau key fixture.
4. Ekspor sidecar dengan format tanda tangan Control:

   ```bash
   php tools/release/control_release.php export /var/lib/finance-release/source /var/lib/finance-release/finance-alpha.tar /var/lib/namua-control/release-signing/private/NAMUA_FINANCE.json
   ```

   Hasil: `.tar`, `.release.json`, `.release.sig.json`. Folder output harus
   milik root dan tidak writable oleh group/others; output tidak boleh sudah
   ada. Kode menolak dirty source atau file arsip berbeda dari source commit.
5. Di checkout Control jalankan verifikasi read-only:

   ```bash
   php tools/verify_finance_release.php /var/lib/finance-release/source /var/lib/finance-release/finance-alpha.release.json
   ```

   Perintah Control menggunakan validator dari checkout Finance milik admin,
   bukan mengeksekusi script yang diambil dari arsip. Trust produk Finance
   berasal dari lokasi tetap di Control. Tidak membuka koneksi database.

Kontrak:

- Tanda tangan Ed25519 atas `NAMUA_RELEASE_MANIFEST_V1`, newline dan SHA256
  byte asli sidecar. Berbeda dari `NAMUA_LICENSE_V1` untuk entitlement.
- Plain TAR regular files, maksimum 1 GiB pada CLI ini. Batas upload HTTP
  Control 25 MiB **tidak diubah**. Upload UI/importer Penatausahaan bukan
  jalur untuk paket Finance ini.
- Seluruh file cocok dengan inner manifest; SQL managed harus sesuai katalog,
  SQL legacy dinyatakan eksplisit dan **tidak otomatis dieksekusi**. Upgrade
  harus menggunakan policy upgrade, bukan clean-install reference seed.
- Isi source/SQL di paket tidak berarti dependency vendor atau runtime
  customer sudah terpasang. Installer tetap harus memakai lock dependency,
  menyiapkan secret/folder, dan menguji install/restore/upgrade/rollback.
- Paket web tidak menyertakan binary/source APK. Komersialisasi APK boleh
  dilanjutkan, bug produksi/build/UAT masih tertunda. Jangan mengiklankan
  APK siap rilis atau menentukan versi minimum APK tanpa build teruji.
- Kandidat `INTERNAL_CANDIDATE`/ALPHA sudah DRAFT di DB Control (Batch 230).
  Belum publish, install-plan, deployment customer atau enforcement.
- Template Menu Book legacy masih disediakan untuk kompatibilitas. Customer
  baru harus memilih template customer di Profil Usaha; audit identitas/aset
  legacy menyeluruh tetap menjadi checklist C2, bukan dinyatakan selesai oleh
  tanda tangan paket.

Kandidat Batch 228: versi `0.1.0-alpha.3`, cutoff Git
`b10fa37a40a06b1867800327812ac1dc1490c176`. Bukti/hash dan lokasi arsip
staging dicatat pada execution log; source/tag terpisah dari commit laporan
setelah build. Tidak perlu menjalankan SQL tambahan untuk batch delivery ini.

Kandidat terbaru: alpha.7, cutoff `68e01142821647f541a60818696989e30832442c`,
tag `finance-web-alpha.7-cutoff-20260909`, DRAFT Control
`5d8d9ed2-0d66-49d0-80c8-730b7bd3d3f4`. Laporan dan SHA256 ada di execution log.

Setelah DB install PASS: buktikan deployment web Linux/upgrade/rollback
disposable. Trial awal menggunakan database kosong/sintetis, bukan mengambil
data transaksi customer untuk menguji tooling.
Server aplikasi lama tidak menjadi target migration.
