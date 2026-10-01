# Pusat Panduan Aplikasi — Batch 261

Status source: **CODE_READY**. SQL sidebar/izin: **USER_REPORTED_APPLIED**
(konfirmasi pengguna dicatat 2026-09-15 11:24 WIB; belum postcheck langsung).
Distribusi customer: **NOT_RELEASED**. Ditinjau 2026-09-15.

## Cara membuka

1. Login SUPERADMIN, buka `/guide`. Jalur langsung dapat digunakan sebelum
   seed sidebar karena SUPERADMIN memakai bypass izin bawaan aplikasi.
2. Pilih topik/peran, cari kata yang dibutuhkan, lalu pilih bab. Desktop memakai
   daftar bab terbatas tinggi; mobile memakai dropdown. Hanya satu bab ditampilkan.
3. Sesudah SQL menu diterapkan, masuk melalui **Sistem → Panduan Aplikasi**.
4. Admin memberi View `system.guide.index` ke role operator yang memerlukan.
   Untuk admin server, beri juga View `system.guide.server`. Izin panduan
   tidak memberikan hak operasional pada halaman terkait.

## Cakupan dan batas

- 26 bab / enam kategori: Mulai, Pengaturan UI, Operasional, Keuangan, Bantuan,
  Admin Server. Empat bab server hanya tersedia bagi izin server.
- Setup customer kosong vs trial DB-copy, identitas/logo, akun multi-role,
  outlet/device/API key, pembayaran/master/saldo awal; POS/simpan/tambah item,
  void/refund, order/member/DP, SR/PO, stok/FIFO/lot/koreksi/HPP, produksi,
  payroll/PH/uang makan/aset, printer/roastery, kas/rekon/jurnal/tutup bulan.
- Admin server: installer dan private file prerequisites, contoh JSON inti
  serta pool FPM, beda env CLI/FPM, job wajib/opsional/alternatif/legacy,
  backup/restore/upgrade/rollback. Semua contoh memakai placeholder generik.
- Filter hak akses terjadi di server sebelum search/selection/render; URL
  langsung bab server tanpa izin ditolak, termasuk alias CI controller/index.
  POST/HEAD ditolak 405, parameter non-string/terlalu panjang/invalid UTF-8
  ditolak 400. Konten HTML/query/contoh kode selalu escaped. No-store.
- Tidak membaca dokumen internal mentah, data transaksi, secret atau konfigurasi
  instance. Metadata manifest hanya field version tervalidasi; bukan status
  deployment maupun klaim versi artifact sudah diterbitkan.
- Tombol salin hanya clipboard; tidak menjalankan perintah. Tanpa JS seluruh
  navigasi/pencarian tetap bekerja. Cetak hanya bab aktif; browser/print nyata
  tetap belum diuji.
- Pipeline shell/session/sidebar/audit akses umum MY_Controller tetap berlaku;
  halaman tidak menambahkan mutasi bisnis atau executor server.
- Jurnal bukan integrasi auto-post penuh seluruh modul; DRAFT dan keterbatasan
  APK tetap disebutkan. Panduan tidak mengubah hasil pengujian/penerimaan rilis.

## SQL — DIJALANKAN PENGGUNA, BELUM DIVERIFIKASI

File: `sql/2026-09-15c_application_user_guide.sql`.
Pengguna mengonfirmasi SQL sudah dijalankan; dicatat 2026-09-15 11:24 WIB.
Ini waktu pencatatan konfirmasi, bukan timestamp eksekusi yang diverifikasi.
Target pada perintah sebelumnya: `db_finance`. Output postcheck belum diterima.

**Perintah berikut hanya arsip; tidak perlu dijalankan ulang untuk konfirmasi ini.**

```bash
mysql -u root -p db_finance < /www/wwwroot/finance/sql/2026-09-15c_application_user_guide.sql
```

Password diminta interaktif, tidak ditulis pada command. Agent **tidak menjalankan**
perintah ini atau menghubungkan DB. SQL tidak perlu mengulang 15a/15b jurnal;
prasyaratnya hanya tabel sidebar/RBAC yang sudah digunakan aplikasi dan grup
`grp.system` serta role `SUPERADMIN`.

Perubahan: dua `sys_page`, satu `sys_menu` di grup Sistem, initial View pada
kedua page hanya bagi SUPERADMIN. Tidak mengisi master/saldo/transaksi,
tidak menghapus atau mengubah grants/menu existing. Sequential replay diuji
pada fixture SQLite memory; bukan bukti sintaks/constraint/replay MariaDB.

Di akhir SQL terdapat SELECT postcheck: harus terlihat dua page aktif,
satu menu `system.guide` URL `guide` dengan parent benar, dan dua grant
SUPERADMIN. Jika parent/grant tidak ada, hentikan aktivasi dan periksa metadata;
jangan membuat root menu/role duplikat. Login ulang jika cache sidebar belum segar.

Status telah dinaikkan ke USER_REPORTED_APPLIED berdasarkan konfirmasi pengguna.
Schema/sidebar/RBAC nyata hanya dinyatakan verified bila hasil postcheck diterima.
File SQL dan checksum tidak diubah atau diterapkan ulang.
SQL belum masuk migration catalog/allowlist: handoff rilis terpisah sebelum
artifact customer, tidak auto-apply hanya karena source tersedia.

## Validasi

- PHP lint controller/library/catalog/view/routes/test/gates.
- `php tools/tests/application_user_guide_smoke.php`: **398 PASS**. Real
  controller/library/view dengan auth/output doubles; filter/role/URL, query
  invalid, XSS, empty search, metadata version, seluruh 26 artikel, tautan,
  scheduler/runtime drift, seed/replay/izin existing di SQLite memory,
  serta resolusi executable `/usr/bin/node` pada required client gate.
- `node tools/tests/application_user_guide_client_smoke.cjs`: **10 PASS**.
  Real client JS, DOM sintetis: copy literal, clipboard denied/unavailable,
  print eksplisit, scoped copy dan tidak mengganggu halaman lain.
- `node --check assets/js/finance-user-guide.js` lulus.
- Kedua tes menjadi required gate; expected manifest ditambah tanpa melemahkan
  pemeriksaan order/count. Quality-gate contract **28 PASS** dan roadmap
  consistency **26 PASS**; register kini 31 SQL top-level (20 managed, 7 legacy,
  empat belum managed: 14c/15a/15b/15c).
- Tidak ada perubahan dependency; Composer validate tidak relevan. Tidak
  menjalankan full gate yang bisa membuka DB/server/integrasi live.

## UAT berikut / status belum selesai

- [x] Pengguna mengonfirmasi apply SQL 15c, dicatat 2026-09-15 11:24 WIB.
- [ ] Postcheck metadata MariaDB, sidebar, role guide-only/server-only,
  operator kedua dan login ulang. Script SQL tidak diverifikasi live oleh agent.
- [ ] Browser lebar 360/768/desktop: tab, filter, dropdown, judul, code wrap,
  empty search, keyboard, salin HTTPS dan cetak bab. Tes DOM bukan visual UAT.
- [ ] Operator non-programmer menyelesaikan latihan memakai bab UI, tanpa
  menebak lokasi menu. Admin server menjalankan runbook pada instance trial
  terisolasi; tidak memasang seluruh cron pada server produksi sekaligus.
- [ ] Review perintah path/user/secret per instance. Runtime baseline bukan
  komitmen dukungan/security lifecycle tanpa evaluasi lebih lanjut.
- [ ] Sertakan source/asset/metadata migration pada artifact bersih; uji
  clean-install/upgrade dan versi panduan dari artifact, bukan hanya workspace.

Sumber yang dicocokkan: `docs/customer_setup_and_release_guide.md`,
`tools/install/{FinanceInstance,PrivateDeployment,LinuxWebProfile}.php`,
`tools/release/runtime_compatibility.json`, controller dan routes terkait,
`tools/licensing/systemd`, panduan printer serta script backup/heartbeat/replikasi.
Script heartbeat legacy masih punya path staging; backup legacy punya `.env`
sendiri. Keduanya diberi batasan, tidak dinyatakan siap dipasang customer.

Checklist teknis kanonis ada pada `_30`; penerimaan paket/panduan C5 pada `_28`.
Tidak mengubah Control/bridge, credential/config server, DB, notifier, atau
melakukan commit/push/deploy. Semua edit scoped ke pusat panduan, tes dan docs.
