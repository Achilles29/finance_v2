# Kontrak Runtime Finance — A5.14

**Diperbarui:** 5 September 2026
**Status:** `CODE_PASS + STAGING_PASS`, belum merupakan sertifikasi customer.

Sumber machine-readable kontrak ini adalah
`tools/release/runtime_compatibility.json`. Pemeriksa otomatisnya adalah
`tools/release/runtime_compatibility_check.php`. Dokumen ini hanya menjelaskan
hasilnya dalam bahasa deployment.

## Matrix yang Disetujui untuk Baseline Staging

| Komponen | Rentang yang diterima | Terbukti di staging | Pemakaian |
| --- | --- | --- | --- |
| PHP CLI dan FPM | `>=8.1.0 <8.2.0` | `8.1.32` | Web, cron, worker, dan alat CLI Finance |
| MariaDB | `>=10.6.0 <10.7.0` | `10.6.23` | Database aplikasi |
| Node.js | `>=20.0.0 <21.0.0` | `20.20.2` | WhatsApp engine |
| npm | `>=10.0.0 <11.0.0` | `10.8.2` | Build/install WhatsApp engine |
| Python | `>=3.10.0 <3.11.0` | `3.10.12` | POS Printer Agent |
| Composer | `>=2.0.0 <3.0.0` | `2.0.14` | Tool build, bukan bootstrap web |

PHP wajib memuat `mysqli`, `mbstring`, `curl`, `zip`, `xml`, `openssl`,
`sodium`, `session`, dan `json`. `sodium` digunakan untuk verifikasi tanda
tangan Ed25519 artefak release. `fileinfo` adalah syarat fitur unggah/MIME
WhatsApp.

Rentang di atas sengaja sempit: ia hanya menyatakan kombinasi yang benar-benar
dipakai untuk clean install, upgrade, health check, rollback, dan quality gate
staging. Minor runtime lain tidak otomatis didukung hanya karena source dapat
di-lint.

## Hasil Pemeriksaan Staging

- Situs `pos.namuacoffee.com` menggunakan include Nginx `enable-php-81.conf`
  dan socket PHP-FPM 8.1; binary FPM aktif terdeteksi sebagai `8.1.32`.
- PHP CLI, MariaDB, Node, npm, Python Printer Agent, dan Composer berada dalam
  rentang matrix.
- `composer.json` kini menyatakan `php >=8.1 <8.2`, sesuai source aktif dan
  policy. Klaim lama `>=5.3.7` sudah dihapus.
- `composer.lock`, `wa-engine/package-lock.json`, dan
  `tools/pos_printer_agent/requirements.txt` wajib ada. npm lock harus cocok
  dengan dependency root; dependency registry wajib mempunyai integrity hash,
  sedangkan dependency Git wajib mengunci commit SHA-1 penuh. Python wajib
  exact-pin dan mempunyai SHA-256.
- Clean install dan upgrade/rollback A5.12–A5.13 dijalankan dengan matrix
  staging ini.

Ada dua warning lingkungan yang belum menolak aplikasi inti:

1. PHP 8.1 staging dikompilasi dengan `--disable-fileinfo`. Jalur WhatsApp yang
   perlu mendeteksi MIME file belum boleh dianggap tersedia. Untuk deployment
   customer yang memakai fitur tersebut, gunakan build PHP yang memuat
   `fileinfo` lalu jalankan probe ulang.
2. Composer `2.0.14` masih dapat memvalidasi dan menyegarkan lock, tetapi
   menghasilkan deprecation pada PHP 8.1. Tool build disarankan dinaikkan ke
   Composer `>=2.2` dan diuji ulang; ini tidak mengubah runtime web karena
   `composer_autoload` aplikasi tetap `FALSE`.

## Cara Memeriksa

Dari root project:

```sh
php tools/release/runtime_compatibility_check.php --contract
php tools/release/runtime_compatibility_check.php --staging
php tools/tests/a5_runtime_compatibility_contract_smoke.php
```

Mode `--contract` hanya memeriksa policy dan lock repository. Mode `--staging`
menambahkan probe versi executable, extension PHP, dan binary PHP-FPM yang satu
instalasi dengan PHP CLI. Tidak ada query atau perubahan database.

## Aturan Release

- Installer harus menolak runtime di luar matrix, bukan mencoba melanjutkan.
- CLI, FPM, cron, worker, WhatsApp engine, dan Printer Agent diperiksa sebagai
  process terpisah.
- Pergantian minor PHP/MariaDB/Node/Python wajib membuat entry matrix baru dan
  menjalankan clean install, upgrade, rollback, serta regression gate ulang.
- Lock dependency dan policy matrix harus masuk manifest artefak A5.16.
- Baseline staging ini belum menutup gerbang customer: runtime yang masih
  mendapat security maintenance perlu dikualifikasi sebelum penjualan,
  `fileinfo` perlu tersedia bila WhatsApp file digunakan, dan Composer build
  perlu diperbarui.

Tidak ada controller/model/route POS Mobile atau data database yang diubah pada
A5.14.
