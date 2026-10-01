# Kontrak paket customer bersih — Finance ↔ Control

## Kontrak kandidat 20 September 2026

Source `0.1.0-alpha.22`, `CUSTOMER_CLEAN` v10. Nilai otoritatif ada pada `app-manifest.json` dan `tools/release/customer_clean_profile.json` dari cutoff yang sama. Verifier mengenali versi profil yang didukung secara eksplisit; v10 mewajibkan `managed_migration_contract=FINANCE_MANAGED_ADOPTION_V1` selain feature boundary, single-folder, guided setup dan izin `UNTIL_USED_OR_REVOKED` yang tetap berlaku. Tidak ada deadline mulai instalasi dan domain bukan pengunci.

Katalog berisi 26 migrasi clean-install / 25 upgrade, termasuk 14c/15a/15b/15c/16a yang sebelumnya manual serta koreksi schema POS 20a. Adopsi manual wajib lolos proof; schema parsial/drift ditahan. Paket berisi jurnal beserta dependensinya dan panduan UI, hanya 24 akun referensi generik; jurnal/mapping/customer master/transaksi/bukti stok tetap kosong. Baseline lama dan checksum lima SQL lama tidak diubah.

Pemasangan baru mengikuti `customer_single_folder.md`: ZIP lengkap → root website `public/` → persiapan admin sekali → `/setup`; konfigurasi `config/customer.json`. Jangan memakai alur/toolkit historis di bawah sebagai pengganti petunjuk paket baru. **Update kode instalasi aktif belum tersedia**: preflight baru bersifat read-only, bukan switch/rollback yang siap digunakan. Jangan menimpa folder atau menjalankan clean installer ulang. Control tetap melakukan review, approval, build resmi dan publish; kandidat source bukan release yang siap diunduh.

## Riwayat kontrak (bukan status kandidat terbaru)

Delta working tree 14 September 2026 (Batch 249): profil aktif **version 3**, SHA `e167cc4f826f90976a59b023606d8af5aa0a43304e73c85ac346226238c48781`. Runtime Kontrol Keuangan dan migration `2026-09-14a/b` tersedia; konfirmasi/rencana/bukti/persetujuan staging bukan seed. Bukti privat disimpan di luar root aplikasi, tidak dibundel. Verifier mengeluarkan versi profil yang benar-benar diperiksa; installer mengikat versi/digest plan ke artifact dan mengenali v1/v2/v3 saja. Control perlu scan ulang, tidak meng-hardcode versi. Hash trusted-local, signature, larangan data/secret tetap berlaku. Tidak mengubah artifact/tag alpha.12, tidak ada build/publish baru. Blocker allowlist SQL Roast Connect masih terbuka; status rilis ada di `_28`/execution log Batch 249. `.user.ini` staging bukan runtime allowlist; pengaturan private evidence/open_basedir customer harus memakai path instance customer. Keterangan Batch 245 di bawah adalah bukti cutoff historis, bukan hasil gate working tree ini.

Status terkini: implementasi sisi Finance **0.1.0-alpha.12**, 12 September 2026 (Batch 245). Control dikerjakan thread lain; dokumen ini menjadi kontrak bersama. Jangan mengosongkan sumber Finance atau database development.

Histori Batch 244: tag `finance-web-alpha.11-clean-profile-source-20260910`, 114 entry release gate PASS dan 56 pemeriksaan paket bersih. Jangan memakai versi/tag itu untuk adapter baru. Hasil validasi terbaru ada di execution log Batch 245. Source baru bukan otomatis artifact signed/published atau deployment customer.

Handoff terbaru: tag lokal `finance-web-alpha.12-control-build-source-20260912` (belum push). Validasi: 115 entry release gate PASS, 81 customer-clean/signed checks, 20 adapter checks, serta drill adapter→verifier result/report Control aktual **PASS** memakai fixture terisolasi. Hasil DB: 296 tabel, 285 tabel non-reference kosong, 719 reference sistem, 16 migration, restore 296 checksum cocok. Pembatalan build juga diuji; parent menunggu cleanup child. Bukti ini belum menggantikan build/sign/publish dan UAT lewat UI Control.

## Sinkronisasi Control terbaru (Batch 245)

- `app-manifest.json` sekarang mengumumkan profil default **Customer bersih / CUSTOMER_CLEAN**, audience CUSTOMER, sample NONE, adapter `tools/build/customer_package.php` dan hash rules v1 yang tetap. Parser aktual Control telah menerima deklarasi ini. Tidak menyediakan profil demo yang belum diimplementasikan.
- Adapter mengikuti `NAMUA_PRODUCT_BUILD_V1`: validasi request/commit/hash, source read-only, mandatory preflight/PHPStan/OSV, TAR bersih, uji instalasi dan backup–restore pada MariaDB disposable, report delapan gate, lalu pemeriksaan source ulang. Output hanya TAR dan build-report; **kunci signing tidak diterima adapter**.
- Worker Control memulai PHP 8.4; entry Finance mendelegasikan pemeriksaan aplikasi ke PHP 8.1. Composer/PHPStan cache ada di area build, bukan source atau direktori admin. MariaDB sementara memakai socket privat, `--skip-networking` dan datadir baru; tidak memakai config/database Finance.
- Gate advisori terbaru menemukan `sharp@0.35.3`; override/lock WA Engine diperbarui ke 0.35.4 (libheif 1.23.2) sesuai [advisory pengembang](https://github.com/lovell/sharp/security/advisories/GHSA-rgj7-g3m4-5g8c). Installer dependency paket baru mengambil versi patch; runtime/node_modules WA staging tidak diubah atau direstart. Uji gambar hanya pada dependency disposable. Builder kini menjalankan vulnerability gate sebelum analisis statis yang lebih lama; tidak mengurangi gate.
- Seluruh tabel non-referensi diuji kosong sebelum owner sintetis dibuat. Owner dan dump uji tidak masuk paket dan dibuang bersama area uji. Tidak ada migrasi baru atau SQL ke DB Finance/Control yang aktif.
- Verifier/installer Finance menerima **manifest Control schema 1** dan **sidecar Finance v2 historis**, sesudah signature asli diverifikasi. Nama artifact Control `filename` dinormalisasi untuk installer; hash `app-manifest.json` milik Control tidak disamakan dengan hash inner `RELEASE-MANIFEST.json` milik Finance. Semua profile/seed/plan binding tetap wajib.
- Pemeriksaan kode Control 12 September masih menemukan penahan `FINANCE_TRUSTED_VALIDATOR_REQUIRED` pada worker serta `finance_validator_unavailable` pada model build. Finance tidak mencabut guard atau mengubah source/database Control. Thread Control perlu memasang validator tepercaya berikut sebelum membuka tombol build CUSTOMER.

### Handoff validator tepercaya, sebelum Control menandatangani

Control harus menjalankan validator dari **checkout Finance tepercaya yang sama dengan commit request**, bukan mengeksekusi file PHP dari TAR atau file upload customer:

```sh
/www/server/php/81/bin/php /checkout/finance/tools/build/verify_control_build.php \
  --request=/private/build-id/request.json --output=/private/build-id/output
```

Perintah ini read-only: memeriksa binding request/source, audit TAR dengan policy lokal tepercaya, kelengkapan file dan checksum seluruh byte terhadap source. Hasil mengikat artifact SHA, source commit, inner manifest SHA dan profile SHA. Control tetap wajib menjalankan verifier result/report/eight-gates/signature miliknya; validator TAR **bukan pengganti** pemeriksaan tersebut. Pin/review seluruh dependency toolchain Finance, tidak hanya entry PHP.

Setelah validator itu terpasang dan tes lintas aplikasi lulus, thread Control boleh menyesuaikan guard miliknya. Jangan hanya menghapus guard tanpa pemanggilan validator. Empat field distribution dalam install-plan/claim dan tiga nama artifact sudah ada di kode Control yang diperiksa.

### Persiapan host build (administrator, satu kali)

Host ini sudah memiliki akun `namua-build`, PHP 8.1/8.4, MariaDB 10.11 dan toolchain Finance. Finance memberi akun build **akses baca/eksekusi saja** ke dependency umum, bukan credential, aplikasi atau DB. Setelah refresh cache OSV, ulangi ACL karena file snapshot diganti secara atomik:

```sh
chmod 0644 tools/build/*.php tools/release/customer_clean_profile.json
setfacl -m u:namua-build:rx /var/lib/finance-a4-static
setfacl -R -m u:namua-build:rX /var/lib/finance-a4-static/vendor
bash tools/tests/bootstrap_a4_security_runtime.sh --refresh
setfacl -R -m u:namua-build:rX /var/lib/finance-a4-security
```

Cache advisori harus berumur maksimum 48 jam sesuai policy lama; jangan mengubah timestamp untuk meloloskan gate. Perintah refresh hanya mengunduh database advisori publik, bukan mengubah dependency lock. Jalankan tes integrasi terpisah bila toolchain/protocol berubah:

```sh
php tools/tests/c3_control_build_runtime_smoke.php --isolated --control-root=/www/wwwroot/control
```

Tes tersebut membuat checkout Git sintetis, menjalankan adapter sebagai namua-build dan verifier result/report Control dalam mode library-only, tanpa worker utama/DB/signing Control. Artifact fixture bukan release untuk dijual.

### Langkah UI setelah handoff Control selesai

1. Gunakan cutoff Finance alpha.12 yang committed dan bersih; push diperlukan jika Control membaca server/checkout lain.
2. **Produk → Tambah dari source → Pindai sekarang**, lalu Finance → **Preview sinkronisasi → Impor**.
3. Pastikan dropdown memuat **Customer bersih**, lalu buat draft **versi exact alpha.12**, bukan mengubah release INTERNAL lama.
4. Minta build, periksa delapan gate dan hasil validator. Approval/publish/deployment customer tetap dilakukan melalui Control oleh user.

## Pilihan dan batas tanggung jawab

- Finance menyediakan `CUSTOMER_CLEAN`, versi profil `1`, seed `REFERENCE_ONLY`. Tidak menyediakan demo produk/transaksi pada versi ini.
- Builder: `php tools/release/build_release_artifact.php --profile=CUSTOMER_CLEAN --root=/checkout/committed --output=/private/finance-VERSION.tar --source-epoch=EPOCH`.
- Default builder baru adalah `CUSTOMER_CLEAN`. `LEGACY_INTERNAL` hanya untuk reproduksi/test internal, bukan penjualan. Tidak ada opsi pembersihan database sumber.
- Sumber harus checkout bersih pada commit tertentu. Output di direktori terpisah; file sumber tidak ditulis ulang. Profil memilih file dan checksum konten statis/SQL yang disetujui, bukan menyalin seluruh folder assets.
- Control memiliki UI pemilihan profil, job build, penyimpanan metadata/evidence, approval/publish, dan deployment plan. Finance tidak mengubah kode/database Control.

## Kontrak artifact dan manifest

Tetap tiga artifact delivery: `.tar`, `.release.json`, `.release.sig.json`; Ed25519 context tidak berubah. Control memakai outer `schema: 1` dan Finance export historis memakai `manifest_version: 2`; installer kini mendukung keduanya. Inner `RELEASE-MANIFEST.json` tetap schema 1. Profil `tools/release/customer_clean_profile.json` termasuk file yang checksum-nya dilindungi inner manifest.

Sidecar hasil export Finance v2 menambahkan (manifest Control schema 1 dinormalisasi setelah verifikasi):

```json
{
  "distribution_profile": "CUSTOMER_CLEAN",
  "distribution_profile_version": 1,
  "seed_profile": "REFERENCE_ONLY",
  "customer_content_audit": {
    "schema": "finance.customer-content-audit",
    "schema_version": 1,
    "status": "PASS",
    "profile_sha256": "<64 hex>",
    "artifact_sha256": "<64 hex>",
    "source_manifest_sha256": "<64 hex>",
    "files_checked": 0,
    "static_files_checked": 0,
    "sql_files_checked": 0,
    "demo_data": false,
    "source_database_accessed": false
  },
  "contains_customer_data": false
}
```

Angka jumlah file di atas hanya contoh; validator menghitung isi TAR sebenarnya. `PASS` berarti sesuai allowlist Finance dan checksum SQL/static yang dikurasi, bukan hasil memeriksa database customer. Secret scan dan install-test tetap gate terpisah.

- Control harus memanggil validator Finance yang dipercaya, bukan menerima `contains_customer_data=false` atau report upload tanpa verifikasi.
- Gate baru yang disarankan: `CUSTOMER_CONTENT_AUDIT`. Ikat profile/version/seed, artifact SHA, inner manifest SHA, source commit, dan policy SHA ke release; jangan mengandalkan versi/nama file saja.
- Paket lama tanpa profil tidak memenuhi syarat clean-customer. Signature lama tetap dapat diperiksa untuk provenance/rollback, tetapi bukan bukti bersih. Jangan mengubah artifact/tag alpha.10 yang sudah immutable; buat versi release baru.
- Semua checksum/report harus dihitung ulang dari artifact yang akan dipublish, bukan dari worktree lain. Update validator tepercaya di Control beserta dependensinya dari cutoff yang sama.

## Install mode bukan distribution profile

- `clean_install`: hanya database tujuan baru/kosong; schema + migration terkelola + reference seed + bootstrap owner. Tidak mengimpor backup Namua.
- `upgrade`: mempertahankan database, uploads, identitas, dan transaksi customer; reference seed clean-install tidak dijalankan. Bukan perintah reset/pembersihan.
- Control perlu membawa pilihan profil/seed ke metadata release dan deployment plan. Install mode tetap keputusan deployment instance, bukan toggle yang menghapus data pada saat build.
- Response claim `/api/v1/install-plans/claim` wajib menambahkan pada object `release`: `distribution_profile: "CUSTOMER_CLEAN"`, `distribution_profile_version: 1` (integer), `seed_profile: "REFERENCE_ONLY"`, dan `customer_content_profile_sha256` (hash profil dari audit signed). Keempat nilai juga harus masuk material pembentuk `plan_sha256`. Finance membandingkan semuanya setelah signature artifact diperiksa; field hilang/salah memblokir delivery paket baru, tanpa menurunkan ke mode legacy.
- Verifier CLI mengembalikan `customer_clean_eligible: true`, profile/version/seed dan `customer_content_audit`. Legacy mengembalikan `customer_clean_eligible: false` serta audit `NOT_AUDITED`, sekalipun signature valid. Installer clean-install Finance menolak paket legacy sebelum koneksi DB.
- Migrasi database aplikasi Namua yang berjalan merupakan workflow import/upgrade terpisah, bukan clean-install customer.

## Konten

- Masuk: program, dependency contract, tema/icon umum, placeholder netral, schema tanpa data, sidebar/RBAC sistem dan template generik yang disetujui.
- Tidak masuk: foto/menu statis Namua, materi roastery/promosi, logo usaha lama, uploads, backup/dump, log/cache, credential lokal, probe/repair/truncate development, SQL legacy yang tidak dikelola.
- Data staging tetap utuh. Legacy Menu Book tetap bisa dipakai di development; paket customer beralih ke katalog usaha jika desain legacy tidak disertakan.

## Checklist lintas thread

- [x] Finance: filter profil, audit TAR, fallback netral, deklarasi profil, adapter unsigned dan penerimaan format signed Control.
- [x] Kode Control: metadata profil/seed/hash di plan/claim dan penamaan sidecar Finance sudah tersedia (inspeksi read-only).
- [ ] Control: impor ulang source alpha.12 dan pasang validator Finance tepercaya sebelum membuka guard build CUSTOMER.
- [ ] Bersama: delapan gate, metadata plan, signature dan validator teruji dari build UI Control aktual, bukan fixture.
- [ ] Bersama: build release versi baru dari cutoff bersih, sign, register, clean-install terisolasi, cek layar kosong/owner dan upgrade preservasi.
- [ ] User: latihan penjualan melalui UI Control setelah semua gate di atas lulus.

Jangan tandai integrasi Control/latihan penjualan selesai hanya karena unit test Finance lulus.

## Perintah validator untuk thread Control

```sh
# Read-only terhadap artifact; bukan import/SQL/database cleanup.
php tools/release/customer_content_audit.php /private/finance-VERSION.tar
php tools/release/control_release.php verify /private/finance-VERSION.release.json /private/trust/NAMUA_FINANCE.json
```

Dependensi validator tepercaya kini juga mencakup `tools/release/CustomerReleaseProfile.php` dan `tools/release/customer_clean_profile.json`, selain bridge, artifact_signature dan package_policy yang sudah dipakai. Allowlist ini tidak boleh diganti memakai file dari upload sebelum diverifikasi. Pertahankan validator/cutoff lama untuk rollback kandidat historis; perubahan profil berikutnya membutuhkan review dan versi profil baru, bukan mengubah allowlist v1 diam-diam.

Data demo tidak dibawa, sehingga tidak ada `CUSTOMER_DEMO` atau `CLONE_NAMUA` pada kontrak ini. Jika nanti diperlukan, buat profil/seed terpisah dengan fixture sintetis dan gate sendiri.
