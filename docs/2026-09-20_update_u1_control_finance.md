# Handoff bersama — updater Finance / Control U1

Implementasi dilakukan dari thread Control atas izin pengguna, 20 September 2026.

**Status: fondasi pemeriksaan selesai, pemasangan update belum aktif.** core2, database Finance/master, proses bisnis, config customer dan release alpha.22 yang sudah dibangun tidak diubah.

**Pembaruan audit gabungan:** allowlist kandidat kini memasukkan journal/preflight U1 bersama procurement terbaru. Hash profil/manifest kandidat mengikuti isi tersebut; ini belum cutoff yang disetujui atau paket resmi baru. Release 74 ditahan karena TAR lama kehilangan dependensi procurement. [Bukti audit gabungan](../../control/docs/2026-09-20_audit_gabungan_procurement_updater.md) menggantikan status packaging lama di bawah.

Rincian tunggal: [kontrak, bukti dan roadmap U1–U5 di Control](../../control/docs/2026-09-20_modul_pembaruan_aplikasi_u1.md).

Perubahan di Finance:

- `tools/update/UpdateAuthorization.php`: kontrak purpose sama, sekarang **wajib scope PREFLIGHT_ONLY**. Input typed, profil explicit, UUID valid, max umur credential, hash binding canonical. Scope APPLY ditolak.
- `tools/update/UpdatePreflight.php`: pembaca `storage/customer-installation.json` memakai `Control_license_cache::customer_context`, bukan nama PortableStore yang tidak tersedia; status `PREFLIGHT_CHECKED_APPLY_BLOCKED`.
- `tools/update/UpdateJournal.php`: state percobaan tahan restart/replay, credential refresh tidak mengganti nonce/plan atau mengulang hasil selesai. Ini journal pemeriksaan, **bukan journal penerapan DDL**.
- `tools/update/preflight.php`: entry companion installer-only, mempertahankan recheck lisensi/integritas sebelum hasil cached. Tanpa SQL, activation, switching atau cron baru.
- `tools/tests/customer_update_authorization_smoke.php`: 40 PASS.

Pengujian tambahan di Control memakai class Finance langsung: kontrak/journal 24 PASS, dua TAR asli 73→74 dengan issuer/lease fixture 12 PASS. Config, identitas, cache tetap; signature salah dan pencabutan ditolak.

Tidak mengubah pekerjaan procurement yang belum committed, tidak memperbarui app-manifest/profil, tidak membuat commit/push/cutoff/publish. **File baru belum masuk paket customer.** Jangan menganggap update U1 sudah bisa dipasang pada core2 atau mengecualikan file dari integrity guard. Setelah transport dan worker update aman selesai, integrasikan dalam versi/profil baru beserta bootstrap alpha.20, hash/toolchain serta uji build penuh.

Tidak perlu meneruskan instruksi ini ke thread lain: arah pengembangan Control dan Finance dikelola bersama dari workspace ini. Updater tetap membutuhkan implementasi U2–U5 pada dokumen utama; clean installer bukan jalur update.
