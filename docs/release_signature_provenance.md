# Tanda Tangan dan Provenance Artefak Release — A5.16

**Diperbarui:** 5 September 2026
**Status:** `CODE_PASS`; paket customer nyata belum diterbitkan.

## Tujuan

Setiap paket release Finance harus mempunyai dua file:

1. artefak, misalnya `finance-1.0.0.tar`;
2. bukti terpisah, misalnya `finance-1.0.0.signature.json`.

Bukti tersebut ditandatangani dengan Ed25519. Installer/updater wajib menolak
paket yang tidak mempunyai bukti, berubah setelah ditandatangani, memakai key
tidak dikenal, atau mempunyai manifest/policy yang tidak cocok.

## Pemisahan yang Wajib

- **Build host/CI** membuat `.tar`, tetapi tidak menyimpan private key.
- **Signing host** menyimpan private key dan menandatangani artefak yang sudah
  lulus quality gate.
- **Server customer/installer** hanya menyimpan public key untuk verifikasi.
- Tool verifikasi dan public key harus berasal dari installer tepercaya, bukan
  dijalankan dari isi paket yang belum diverifikasi.

Private key tidak boleh masuk Git, paket customer, backup aplikasi, UI,
database, environment PHP-FPM, atau argumen berisi secret. Path private key
boleh menjadi argumen CLI; isi key tidak pernah dicetak.

## 1. Membuat Key di Signing Host

Dilakukan satu kali oleh admin release pada mesin khusus signing:

```sh
install -d -m 0700 /var/lib/finance-signing
php tools/release/artifact_signature.php keygen \
  --private-key=/var/lib/finance-signing/release-private.key \
  --public-key=/var/lib/finance-signing/release-public.key
```

Hasilnya:

- `release-private.key`: mode `0600`, hanya berada di signing host;
- `release-public.key`: mode `0644`, boleh disalin ke trust store installer;
- `key_id`: fingerprint SHA-256 public key yang dicatat dalam release approval.

Tool menolak membuat key di dalam repository dan menolak menimpa key lama.
Simpan backup private key pada secret manager/offline vault yang aksesnya
tercatat. Jangan mengirim private key ke server customer.

## 2. Membuat Artefak di Build Host

Gunakan output di luar repository dan revision commit immutable:

```sh
install -d -m 0700 /var/lib/finance-release-candidates
git rev-parse HEAD
php tools/release/build_release_artifact.php \
  --output=/var/lib/finance-release-candidates/finance-1.0.0.tar \
  --source-epoch=1700000000
```

Catat hasil `git rev-parse HEAD`; nilai 40 karakter itulah yang diteruskan ke
signing host sebagai source revision. Artefak hanya boleh dikirim ke signing
host setelah semua gate build lulus. Builder staging saat ini masih akan
memblokir paket nyata bila menemukan credential langsung; blocker itu tidak
boleh dibypass.

## 3. Menandatangani di Signing Host

Contoh revision di bawah hanya contoh format; gunakan commit hasil build yang
sebenarnya:

```sh
php tools/release/artifact_signature.php sign \
  --artifact=/var/lib/finance-release-candidates/finance-1.0.0.tar \
  --private-key=/var/lib/finance-signing/release-private.key \
  --output=/var/lib/finance-release-candidates/finance-1.0.0.signature.json \
  --source-revision=0123456789abcdef0123456789abcdef01234567
```

Signature mengikat:

- nama, ukuran, dan SHA-256 artefak;
- SHA-256 `RELEASE-MANIFEST.json`;
- source revision dan source epoch;
- fingerprint signing key;
- checksum migration catalog, package policy, dan runtime compatibility policy.

File signature bersifat deterministik untuk kombinasi artefak, revision, dan
key yang sama. Artefak serta signature harus dipublikasikan sebagai pasangan.

## 4. Memasang Public Key pada Installer/Server Customer

Salin **public key saja** melalui deployment channel tepercaya:

```sh
install -d -m 0755 /etc/finance-release
install -o root -g root -m 0644 release-public.key \
  /etc/finance-release/trusted-release-public.key
```

Fingerprint file yang dipasang harus dicocokkan dengan release approval:

```sh
sha256sum /etc/finance-release/trusted-release-public.key
```

## 5. Verifikasi Wajib Sebelum Extract/Install/Update

Jalankan tool dari installer tepercaya:

```sh
php /opt/finance-installer/artifact_signature.php verify \
  --artifact=/srv/finance-update/finance-1.0.0.tar \
  --provenance=/srv/finance-update/finance-1.0.0.signature.json \
  --public-key=/etc/finance-release/trusted-release-public.key
```

Hanya output JSON dengan `"status":"ok"` dan exit code `0` yang boleh
dilanjutkan ke backup, extract, migration, health check, dan rollout. Exit code
selain `0` wajib menghentikan update tanpa mengubah aplikasi/database.

Urutan updater yang benar:

1. unduh artefak dan signature;
2. verifikasi signature/provenance dengan tool dan public key tepercaya;
3. jalankan compatibility preflight;
4. buat backup bundle;
5. extract ke staging release directory;
6. jalankan migration terkelola, health check, lalu atomic switch;
7. rollback bila health check gagal.

## Rotasi atau Insiden Key

- Buat pasangan key baru di signing host; jangan menimpa key lama.
- Distribusikan public key baru melalui installer/update yang masih dipercaya.
- Selama masa transisi, trust store boleh memuat key lama dan baru dengan
  daftar `key_id` eksplisit.
- Bila private key diduga bocor, hentikan signing, cabut `key_id`, rotasi key,
  dan terbitkan ulang artefak dari source immutable.

Batch A5.16 tidak membuat private key produksi dan tidak menerbitkan artefak
customer. Pengujian memakai key sekali pakai di direktori sementara lalu
menghapusnya.
