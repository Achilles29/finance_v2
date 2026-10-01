# Batch 66 — Extra Group Mapping Preflight

Batch 66 memeriksa kontrak schema dan integritas historis mapping Extra Group tanpa mengubah schema maupun data. Preflight mencakup lima tabel terkait, engine InnoDB, 16 kolom wajib (termasuk `sort_order` pada kedua tabel mapping), dua unique pair, empat foreign key, orphan, duplicate pair, mapping yang melibatkan master nonaktif, serta ketidaksesuaian divisi Product–Extra Group. Extra Group dengan `product_division_id` NULL tetap sah. Preflight ini tidak menetapkan kebijakan `source_kind`.

Empat FK harus memakai identity dan relasi deployment berikut: `fk_mst_product_extra_map_group` ke `mst_extra_group(id)`, `fk_mst_product_extra_map_product` ke `mst_product(id)`, `fk_mst_extra_group_item_group` ke `mst_extra_group(id)`, dan `fk_mst_extra_group_item_extra` ke `mst_extra(id)`. Masing-masing wajib memiliki aksi `DELETE RESTRICT` dan `UPDATE RESTRICT`, yang diverifikasi melalui `information_schema.referential_constraints`; aksi `CASCADE`, `SET NULL`, atau aksi lain adalah schema failure (`10`).

SQL membuka `START TRANSACTION READ ONLY`, hanya menjalankan laporan `SELECT` terhadap tabel target/`information_schema`, lalu `COMMIT`; tidak ada dynamic SQL, DDL, DML, atau remediation. Detail SQL dibatasi 50 baris per kategori dan hanya memuat ID, status, serta ID divisi. Wrapper memerlukan tepat satu `B66_END\tOK` sebagai marker terakhir, menolak marker apa pun sesudahnya, dan memvalidasi bahwa jumlah detail setiap kategori tidak melebihi count finding. Wrapper tidak meneruskan output database mentah: hanya marker temuan dan count yang sudah divalidasi yang dapat tampil.

## Prasyarat aman

Jalankan lebih dahulu pada clone staging yang mutakhir. Gunakan akun database khusus baca-saja yang hanya memiliki hak `SELECT` terhadap schema target dan metadata `information_schema`; jangan memakai akun aplikasi dengan hak tulis atau akun administrator.

Simpan option file di luar repository, upload customer, backup, dan direktori runtime aplikasi. File harus dimiliki operator yang menjalankan preflight dan disarankan bermode `0600`:

```ini
[client]
host=127.0.0.1
port=3306
user=preflight_reader
password=PROVISION_SEPARATELY
database=finance_staging_clone
```

Contoh provisioning permission:

```bash
chmod 600 /etc/finance-preflight/b66-reader.cnf
```

Jangan menaruh option file di repository dan jangan memasukkan password pada argumen command line atau environment `MYSQL_PWD`. Wrapper menolak option file yang setelah resolusi canonical berada di dalam repository, tidak membaca `application/config/database.php`, dan tidak mencetak path maupun isi option file. Client dijalankan dengan `--defaults-file` sebagai opsi pertama, sehingga global option files tidak dibaca dan hanya option file eksternal yang dipilih wrapper yang memengaruhi konfigurasi client.

## Menjalankan preflight

Dari root repository:

```bash
MYSQL_DEFAULTS_EXTRA_FILE=/etc/finance-preflight/b66-reader.cnf \
  tools/run_extra_group_mapping_preflight.sh
```

Secara default wrapper memilih executable `mysql`, lalu `mariadb`. Override hanya menerima nama tepat `mysql`/`mariadb` atau path absolut ke executable yang aman:

```bash
MYSQL_DEFAULTS_EXTRA_FILE=/etc/finance-preflight/b66-reader.cnf \
MYSQL_CLIENT=/usr/bin/mariadb \
  tools/run_extra_group_mapping_preflight.sh
```

Untuk melihat laporan detail ID secara langsung pada clone staging, operator berwenang dapat menjalankan SQL dengan option file tetap menjadi opsi client pertama:

```bash
mariadb --defaults-file=/etc/finance-preflight/b66-reader.cnf \
  --batch --raw --skip-column-names \
  < tools/sql/2026-09-03_extra_group_mapping_preflight.sql
```

Jangan menyalin output detail ke tiket publik. Walaupun tidak berisi nama atau secret, ID tetap merupakan metadata internal.

## Exit code

| Code | Arti | Tindakan |
|---:|---|---|
| `0` | Kontrak schema dan seluruh kategori data bersih. | Lanjutkan verifikasi staging. |
| `10` | Kontrak schema gagal: presence/engine/16 kolom wajib/unique pair/identity-relasi-aksi FK tidak sesuai. | Hentikan release dan bandingkan migration/schema deployment. |
| `11` | Ditemukan orphan atau duplicate pair. | Audit ID pada clone; rancang remediation terpisah dan direview. |
| `12` | Ditemukan mapping nonaktif atau mismatch divisi. | Konfirmasi kebijakan bisnis dan audit mapping terkait. |
| `64` | Argumen atau prerequisite lokal tidak valid. | Perbaiki invocation/client selector/file SQL. |
| `66` | Option file hilang, tidak terbaca, tidak valid, atau berada di repository. | Provision option file eksternal dengan permission aman. |
| `69` | Koneksi, query, output marker, atau kontrak output gagal. | Periksa secara privat dari host staging; detail database sengaja tidak ditampilkan. |
| `127` | Client `mysql`/`mariadb` tidak tersedia. | Pasang client resmi atau gunakan path absolut executable yang disetujui. |

Prioritas hasil adalah schema (`10`), lalu orphan/duplicate (`11`), lalu inactive/mismatch (`12`). Wrapper mem-parse dan memvalidasi seluruh marker schema, seluruh count finding, detail bounded, dan marker akhir lebih dahulu. Jika schema gagal, wrapper keluar `10` setelah mencetak marker schema yang disanitasi dan sebelum mencetak marker data finding; karena itu count data nonzero yang sudah diparse sengaja tidak tampil pada jalur schema failure. Jika schema lulus, seluruh count data nonzero yang relevan dicetak sebelum exit `11` atau `12`.

## Batas klasifikasi schema hilang

SQL sengaja statis dan tidak memakai dynamic statement maupun objek sementara. Karena query data merujuk tabel dan kolom secara langsung, tabel/kolom yang hilang dapat dihentikan oleh client sebelum marker akhir. Kondisi itu diklasifikasikan wrapper sebagai query/output tidak lengkap (`69`), bukan schema contract (`10`). Exit `10` berlaku ketika rangkaian query selesai dan marker `B66_SCHEMA` secara eksplisit melaporkan drift, misalnya engine, unique pair, identity/relasi FK, atau aksi referensial FK yang tidak sesuai.

Pembedaan ini disengaja agar smoke statis tetap jujur: satu file SQL SELECT-only tidak dapat secara portabel menghindari seluruh reference error pada schema yang hilang tanpa mekanisme dinamis. Baik `10` maupun `69` harus menghentikan release; jangan menganggap `69` sebagai hasil bersih.

## Tidak ada remediation otomatis

Batch ini tidak menambah constraint, memperbarui status/divisi, menghapus duplicate/orphan, atau menulis execution log. Simpan hasil, minta review `finance_auditor`, lalu buat batch remediation terpisah dengan backup, preflight yang lebih sempit, transaksi, dan post-check. Jangan menghapus backup, upload customer, credential, atau file runtime sebagai bagian investigasi.

## Follow-up staging dan live

Setelah hasil clone bersih dan reviewer menyetujui:

1. Jalankan preflight terhadap database staging aktual dengan akun baca-saja eksternal.
2. Buka halaman mapping Extra Group → Product, Extra → Group, dan legacy Product → Extra Group; pastikan daftar ID yang diuji konsisten dan tidak ada error browser/server.
3. Lakukan probe POS web dan POS mobile terhadap produk uji: group generic (divisi NULL) tetap muncul, group sesuai divisi muncul, dan group beda divisi tidak ditawarkan.
4. Ulangi preflight setelah probe. Karena batch ini tidak meremediasi data, hasil count harus tidak berubah.
5. Untuk live, gunakan akun baca-saja, jendela observasi yang disetujui, dan mulai dari preflight saja. Jangan melakukan perubahan mapping live sampai temuan direview dan remediation terpisah disetujui.

Jika external option file yang aman belum disediakan, validasi Batch 66 berhenti pada lint/static smoke; database preflight nyata belum dijalankan dan tidak boleh disimulasikan dengan membaca credential aplikasi.
