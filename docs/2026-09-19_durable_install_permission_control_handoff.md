# Alpha.20 / profil v8 — customer bebas memilih waktu pemasangan

Permintaan owner: tidak ada tenggat satu jam setelah mengunduh. Batas produk tetap
paket yang dibeli dan jumlah server sesuai lisensi; bukan jumlah pengguna/browser.

## Kontrak baru (hanya paket v8)

- Permit bertanda tangan tetap memakai `NAMUA_FINANCE_SETUP_V1` dan binding exact
  release, profil, TAR, plan, deployment, instance, environment serta credential.
- `profile_version: 8`, `permission_policy: "UNTIL_USED_OR_REVOKED"`,
  `expires_at: null`, `issued_at` integer positif, tidak lebih dari 300 detik di masa depan.
- NULL bukan angka/tanggal jauh di masa depan. Field hilang/tipe salah ditolak.
- Permit lama v6/v7 tetap memerlukan deadline; tidak boleh diubah menjadi tanpa
  tenggat. Signature lama tidak bisa dipakai untuk v8 atau release lain.
- Setup UI menerima state tanpa deadline hanya ketika installer telah memverifikasi
  v8 dan menulis policy, profile_version serta expires_at NULL ke browser.json.
- Izin non-expiring bukan izin offline tanpa pemeriksaan: aktivasi masih menghubungi
  Control, memeriksa pembelian, pencabutan dan kuota server. Kode dikonsumsi satu kali;
  pemulihan respons yang hilang tetap memerlukan bukti identitas server.
- Masa lease/cache lisensi aktif bukan tenggat instalasi. Verifikasi signature,
  pengikat server, anti-replay, quota, dan pengunci aplikasi tetap berlaku.

## Scope

Hanya installer, pembaca kontrak/profil, deklarasi paket, dokumentasi dan tes.
Tidak mengubah controller/model proses bisnis, SQL atau database Finance, tidak
menjalankan instalasi pada server customer. Panduan utama HTML/TXT ikut ditandatangani.

Control menerapkan nullable expiry + explicit policy di token deployment, kode
aktivasi dan ZIP setup. ZIP tersimpan dapat diunduh ulang selama hak/tautan masih
valid dan pemasangan belum diklaim, tanpa merotasi kode hanya karena waktu berlalu.
Paket lama tidak ditimpa; pengiriman baru harus melalui build, review, signature,
deployment dan pengujian ZIP seperti biasa.

## Verifikasi sebelum pengiriman

Jalankan customer_durable_permission_smoke, customer_guided_contract_smoke,
customer_portable_contract_smoke, customer_root_guide_smoke dan
c3_customer_clean_release_smoke. Control menjalankan test_finance_guided_acceptance
dengan permit sintetis yang diterbitkan 45 hari lalu, tanpa tanggal kedaluwarsa,
scheduler/HTTPS/database terisolasi. Uji negatif: policy/expiry salah, signature
berubah, salah instance, file diubah, kode diulang, izin dicabut, kuota penuh.
Hasil build/pengiriman aktual dicatat di dokumen audit Control; dokumen ini bukan
pernyataan bahwa semua tes sudah selesai.
