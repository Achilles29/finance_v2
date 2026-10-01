# Component: konsistensi nilai stok, FIFO, void, dan rebuild

Tanggal: 24 September 2026. Dasar source: HEAD `a3bda02`; perubahan batch ini belum di-commit/push.
Pegangan prioritas tetap roadmap `_30`. Ini laporan modul, bukan roadmap baru.

## Kesimpulan

- Bukan hanya data lama. Audit sebelumnya menemukan 24 selisih nilai pada 153 identitas component; jumlah stoknya sama, tetapi valuasi saldo dan lot berbeda.
- Writer mengurangi saldo memakai biaya rata-rata, sedangkan lot keluar memakai FIFO. Retur memakai biaya asal sehingga nilai tidak kembali simetris.
- POS mengabaikan total biaya alokasi lot dan memakai snapshot HPP, termasuk saat satu pemakaian melintasi beberapa harga lot.
- Rebuild lama menghitung ulang semua bulan dari movement, menghapus baris saldo, dan mengabaikan koreksi nilai yang tersimpan terpisah. Ini dapat menghilangkan koreksi yang sebelumnya sudah benar.
- Opening/nilai historis yang rusak tetap membutuhkan pemeriksaan dan koreksi data terpisah. Batch ini tidak menjalankan repair terhadap `db_finance` atau database aktif lain.

## Checklist implementasi

- [x] Nilai keluar POS, input produksi, adjustment minus, waste, dan spoil mengikuti total biaya lot yang dialokasikan; lot berbiaya nol tidak diganti dengan rata-rata.
- [x] HPP komponen POS = biaya lot yang benar-benar keluar + estimasi hanya untuk bagian defisit. Defisit murni tidak membuat movement stok fiktif.
- [x] Retur sebagian memakai biaya alokasi lot yang dikembalikan. Retur berulang tidak menambah stok lagi; jumlah lot dan sisa movement harus konsisten.
- [x] Pengembalian ke lot yang pernah dikoreksi nilainya mempertahankan nilai sisa lot, ditambah biaya pengembalian. Tidak mengubah seluruh lot kembali ke harga lama.
- [x] Void batch/adjustment memakai nilai lot yang dihapus atau biaya movement asal. Waste/spoil dalam satu baris adjustment tidak menghitung biaya retur dua kali.
- [x] Rebuild otomatis dibatasi ke bulan aktif dengan period guard dan locking; saldo awal dan ID monthly dipertahankan. Tidak menghapus seluruh histori monthly.
- [x] Koreksi nilai menjadi patokan nilai absolut. Posting dan void koreksi sama-sama diperhitungkan; tanggal pencatatan asli menjaga reversal backdate tidak hilang.
- [x] Bukti koreksi yang tidak cocok/urutan satu detik yang ambigu menolak rebuild tanpa menimpa saldo, bukan ditebak atau diabaikan.
- [x] Proyeksi harian dan movement menggunakan recorded cost serta koreksi nilai, termasuk hari koreksi tanpa movement kuantitas. Saldo awal nilai nol yang sah tidak diganti rata-rata akhir.
- [x] Nilai opening dibawa bersama kuantitas saat writer membuat bulan baru. Opening tambahan memakai nilai movement, bukan rata-rata saldo gabungan.
- [x] Bucket adjustment plus/minus tetap terpisah setelah rebuild, meskipun net quantity harian nol.
- [x] Hitung fisik menggunakan biaya lot yang dipilih kebijakan struktural existing (LIFO), bukan mengubahnya menjadi FIFO penjualan. Quote memperhitungkan pengurangan yang masih pending dalam dokumen; penambahan memakai biaya inbound yang sama.
- [x] Pengurangan lot menggunakan selisih carrying value sebelum/sesudah pembulatan untuk mengurangi akumulasi selisih sen.
- [ ] Repair opening/saldo/valuasi transaksi historis: belum dijalankan.
- [ ] UAT transaksi melalui UI pengguna lengkap: belum dijalankan.

## File utama

- `application/libraries/PosOrderStockService.php`: FIFO actual cost, HPP defisit, reversal parsial, opening nilai.
- `application/libraries/ComponentStockWriter.php`: writer produksi/adjustment, biaya hitung fisik, opening nilai, lock saldo.
- `application/libraries/ComponentLotManager.php`: alokasi biaya, retur setelah revaluasi, bukti nilai lot void, quote hitung fisik.
- `application/models/Production_model.php`: replay koreksi, rebuild bulan aktif tanpa penghapusan ID, laporan, reversal dokumen.
- `tools/tests/component_fifo_value_regression_smoke.php`: regresi metode aktual dengan DB in-memory.
- `tools/tests/component_fifo_value_mariadb_smoke.php`: integrasi opt-in dengan MariaDB disposable saja.
- `tools/tests/finance_quality_gate.php` dan `finance_quality_gate_contract_smoke.php`: regresi baru menjadi gate wajib, ekspektasi daftar diperbarui tanpa mengendurkan gate lain.

Keempat file runtime sudah tercantum pada allowlist customer existing. Tidak menambah dependency runtime, migrasi, versi/profil release, atau mengubah artefak published. Build resmi/rollout tetap proses terpisah.

## Validasi aktual

| Pemeriksaan | Hasil |
| --- | --- |
| Regresi component in-memory | 45 pemeriksaan PASS |
| MariaDB 10.11.10 isolated, driver/query builder CI aktual | 65 pemeriksaan PASS |
| Dashboard mismatch | 19 PASS |
| Revaluasi/cache | 23 PASS |
| Kontrak inventory/produksi | 27 PASS |
| Invariant POS reversal | 8 PASS |
| POS tanpa stok/resep | 45 PASS |
| Step-up batch / adjustment / daily recon | 21 / 19 / 16 PASS |
| Refresh availability reversal | 20 PASS |
| Kontrak quality gate | 28 PASS |
| PHP lint | 8 file PHP berubah/baru PASS |
| Analisis statis A4 seluruh application | PASS, baseline 0 |
| Matriks A2 keseluruhan | FAIL pada dua ekspektasi hitungan endpoint/token dalam `pos_transaction_csrf_smoke.php` |
| Konsistensi roadmap/SQL | FAIL: tes mengharapkan 34 SQL top-level; workspace memiliki 37 dan register belum sama |

Dua kegagalan A2: jumlah view yang membuat token serta jumlah writer/proof issuer yang memakai guard. Controller `Pos.php`, konfigurasi CSRF, dan view yang diperiksa tes tersebut tidak diubah dalam batch ini. Perlu review terpisah atas kesesuaian tes/controller; tidak menonaktifkan guard atau mengurangi assertion untuk meluluskannya. Full quality gate/build customer tidak dijalankan.

Kegagalan register SQL juga existing: tidak ada file SQL atau bagian register SQL yang diubah batch ini. Perlu sinkronisasi register dengan 37 file yang ada, bukan sekadar mengganti angka harapan agar tes hijau. `git diff --check` lulus; server MariaDB disposable telah dihentikan, berkas uji tetap tersimpan.

Tes MariaDB tidak menggunakan credential/config aplikasi: server sementara Unix socket, TCP dinonaktifkan, datadir `/tmp/finance-component-regression.5o8rqFt5/data`. Schema lulus terakhir `component_regression_b14511c92e`; hanya schema baseline dan master/transaksi sintetis. Foreign key aktif saat uji data; dinonaktifkan hanya saat membuat tabel kosong yang saling mereferensi. Percobaan awal fixture gagal karena pemotongan SQL pada tanda `;` di komentar tabel; parser fixture diperbaiki, bukan SQL aplikasi. Uji final memakai 16 tabel relevan, bukan impor data usaha.

Keterbatasan:

- Tes integrasi menjalankan service/writer/lot/rebuild/proyeksi yang sebenarnya, bukan login browser dan seluruh pembayaran kasir atau worker live.
- Hitung fisik dengan kuantitas lot **sudah berbeda sejak awal** tetap jalur repair struktural lama, bukan diperlakukan sebagai pengurangan biasa. Nilai legacy tetap harus diperiksa lewat koreksi nilai; tidak ada klaim otomatis membereskan data historis.
- Audit timestamp lama dapat ambigu. Rebuild menolak bukti ambigu untuk ditinjau; tidak menghapus journal/koreksi atau membuka periode tertutup.
- Retur lintas periode, beban paralel multi-kasir, UI/APK/perangkat cetak dan semua kombinasi bundle/extra membutuhkan UAT tersendiri. Tidak mengubah aturan periode atau jalur pembayaran.
- Tidak mengubah `application/config/database.php` milik pengguna; tidak commit, push, deploy, mengubah Control, atau mengirim notifikasi sendiri.

## Checklist uji pengguna — gunakan data uji, bukan transaksi usaha

1. [ ] Siapkan dua lot component: 10 unit @10 dan 10 unit @20. Saldo awal 20 / nilai 300. Pakai 5 melalui POS: sisa 15 / nilai 250, sama dengan lot.
2. [ ] Refund sebagian lalu sisanya dengan kembalikan stok: saldo dan nilai kembali ke awal. Ulangi permintaan refund: tidak bertambah lagi. Refund tanpa kembalikan stok tidak boleh menambah lot.
3. [ ] Pakai 12 unit dari dua lot: biaya 140. Retur 1 dari alokasi terakhir mengembalikan biaya 20, bukan rata-rata 11,666667.
4. [ ] Adjustment minus/waste/spoil lalu void, termasuk beberapa jenis dalam satu baris: kuantitas dan nilai kembali, bucket laporan tidak menyisakan nilai pengeluaran palsu.
5. [ ] Produksi 20 pack → gunakan 5 → void batch harus ditolak. Void pemakaian dengan pengembalian stok → batch dapat di-void bila tidak ada pemakaian aktif lain.
6. [ ] Koreksi nilai → transaksi → refund/void/rebuild: koreksi tetap tercatat, ID saldo tidak berganti, nilai laporan harian sama dengan nilai yang diharapkan. Void koreksi mengikuti syarat existing.
7. [ ] Daily Recon 20 menjadi 15 pada dua lot berbeda harga: nilai mengikuti lot struktural yang berkurang. Periksa juga penambahan fisik dengan HPP input, lot biaya nol, dan kasus defisit.
8. [ ] Bulan baru membawa opening quantity **dan value**. Rebuild bulan aktif tidak mengubah angka/ID bulan sebelumnya. Data lama yang tidak cocok dipisahkan untuk review, jangan dikoreksi massal.

Tidak ada SQL baru yang perlu dijalankan pada database aktif untuk batch ini.
