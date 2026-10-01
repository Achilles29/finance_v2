# Audit Menyeluruh Finance App dan Konsistensi Persediaan

**Tanggal audit:** 2026-08-13  
**Revisi ruang lingkup:** 2026-08-13, setelah konfirmasi bahwa persediaan memakai cut-off bulanan.  
**Status:** Audit awal dilakukan baca-saja. Dokumen ini kemudian diperbarui dengan catatan implementasi yang telah disiapkan; perubahan data bisnis hanya terjadi bila SQL deploy yang tercantum dijalankan.
**Fokus:** Controller, model, dan UI inventory aktif; data bulan lampau hanya menjadi konteks forensik.

## Revisi Penting: Cut-off Bulanan

Persediaan aplikasi bekerja dengan pola berikut:

1. Pada akhir bulan dibuat stok opname.
2. Hasilnya menjadi stok awal bulan berikutnya.
3. Operasional bulan baru menggunakan stok awal baru sebagai stok berjalan.

Karena itu, ketidaksesuaian lot, movement, harga, atau monthly stock pada **bulan yang telah lewat** tidak otomatis merupakan bug stok aktif. Temuan data historis pada dokumen ini tidak boleh menjadi dasar repair massal dan tidak menjadi prioritas operasional selama stok pembuka bulan aktif sudah bersih.

Fokus audit direvisi menjadi:

1. mencegah mismatch baru selama bulan aktif;
2. memastikan cut-off menghasilkan opening yang dapat diaudit;
3. menyatukan semua halaman penyesuaian ke satu jalur writer;
4. menghilangkan jalur controller/model yang dapat mengubah lot tanpa movement setara;
5. mengunci periode yang sudah ditutup.

## Keputusan Desain yang Disepakati

### Stok habis tidak memblokir POS

POS tetap harus dapat menyimpan penjualan. Jika sistem belum memiliki stok yang cukup, stok tidak perlu dibuat minus. Sistem mencatat kebutuhan yang belum memiliki stok pendukung sebagai Defisit Persediaan.

Contoh sederhana:

1. Sistem mencatat stok kopi nol, tetapi secara fisik ternyata masih ada.
2. POS tetap menjual satu porsi dan membuat defisit satu porsi, bukan saldo minus.
3. Saat operator melakukan recon fisik, sistem menyamakan stok dan lot yang terlihat hari ini. Operator dapat memilih: hanya menyamakan stok, atau sekaligus menyelesaikan defisit yang barang, area, UOM, dan profilnya sama.
4. Penerimaan, transfer SR, hasil batch, atau adjustment tambah tetap menambah stok dan lot sebesar jumlah fisiknya. Pada saat yang sama, sumber tersebut dapat menutup defisit yang identitasnya sama; stok masuk tidak dikurangi untuk menutup defisit.

Pada layar operator, informasi ini dibaca sebagai Stok tersedia nol dan Selisih penggunaan satu, bukan angka stok minus yang sulit ditindaklanjuti.

### Cut-off tetap membuat awal bulan bersih

Cut-off tetap boleh membentuk stok pembuka baru tanpa meminta operator memeriksa semua lot lama satu per satu. Perbedaannya hanya pada jejak sistem:

1. Sistem merangkum selisih hasil cut-off ke dokumen koreksi cut-off.
2. Sistem membuat opening lot baru dari saldo final yang disetujui.
3. Lot lama ditutup dan diarsipkan sebagai data periode sebelumnya.
4. Jika ada koreksi cut-off, sistem menyimpan alasan dan movement ringkasnya.

Jadi tujuan awal bulan bersih tetap tercapai, sementara histori tidak rusak karena angka lot lama diubah diam-diam.

### Dua cara input adjustment, satu jalur posting

Kedua kebutuhan yang pernah dibuat tetap diperlukan, tetapi harus memakai dokumen dan writer yang sama:

| Cara input | Dipakai ketika | Sistem melakukan |
| --- | --- | --- |
| Mutasi tambah/kurang | Operator tahu kuantitas yang perlu ditambah atau dikurangi | Menambahkan delta tersebut |
| Stok fisik akhir | Operator menghitung stok nyata saat recon/opname | Menghitung delta otomatis dari stok sistem ke stok fisik |

Daily recon cukup membuka form Stok Fisik Akhir yang sudah terisi saldo sistem. Adjustment biasa membuka form Mutasi Tambah/Kurang. Keduanya pada akhirnya membuat adjustment, movement, lot, dan nilai melalui jalur yang sama.

### Item tetap menjadi pusat tampilan

UI tidak akan memisahkan Item dan Material. Semua layar operasional memakai Item sebagai objek utama. Material hanya atribut internal yang menandai bahwa item tersebut adalah bahan baku dan diperlukan untuk relasi resep, purchase, dan legacy compatibility.

Untuk component, identitas internalnya berbeda karena component tidak memiliki purchase profile seperti bahan baku. Ini tidak boleh terlihat sebagai pemisahan UI; operator tetap mencari dan membaca nama item/component yang sama.

### Void tetap tercatat tetapi tidak mengotori analisa operasional

Void tidak menghapus data asal. Sistem membuat pembalikan stok dan menandai dokumen asal sebagai VOID atau CANCELLED INPUT. Laporan omzet, HPP, stok aktif, dan analisa operasional secara default mengecualikan transaksi VOID. Halaman audit tetap dapat menampilkannya bila diperlukan untuk menelusuri salah input.

### Update 2026-08-24: Void POS gagal dan pasangan lot

Pemeriksaan bulan aktif menemukan contoh nyata ketika server lambat: snapshot POS yang gagal kemudian di-VOID sempat mengembalikan jumlah ke stok divisi, tetapi lot FIFO-nya tidak ikut kembali. Pada database pemeriksaan, jejak ini berasal dari `PSC-202608-0632` dan `PSC-202608-0634`; keduanya berstatus VOID dan memakai fallback lama `POS return to stock aggregate reversal`.

Jadi penyebabnya merupakan gabungan dua hal:

1. Server lambat/gagal memicu snapshot POS tidak selesai.
2. Fallback lama pada void mengembalikan stok tanpa bukti lot atau movement yang sepadan. Inilah bug yang membuat mismatch tersisa setelah transaksi gagal.

Pengamanan baru untuk transaksi berikutnya:

1. POS hanya memakai FIFO lintas lot/profil saat stok tersedia.
2. Kekurangan stok dicatat sebagai Defisit Stok, bukan saldo atau lot minus.
3. Void/refund hanya mengembalikan stok bila ada jejak lot atau movement asal yang terbukti milik snapshot POS tersebut.
4. Bila jejak lama tidak cukup, sistem tidak menambah stok sendiri; snapshot aman untuk masuk antrean/retry dan jejaknya muncul di Audit Commit Stok POS.
5. Audit Commit Stok POS menampilkan kandidat mismatch yang berkaitan dengan fallback Void POS lama, tanpa mengubah data apa pun.
6. Pola aman yang sama diterapkan ke component: kekurangan menjadi defisit, sedangkan pengembalian hanya berjalan setelah lot component dipulihkan atau dibuatkan pasangan yang dapat dilacak.

Repair aktif disediakan pada `sql/2026-08-24a_repair_failed_pos_void_material_lot_pairs.sql`. Script tersebut hanya membuat lot pasangan untuk stok yang sudah terlanjur kembali dari jejak fallback lama. Mode awalnya pratinjau (`@apply = 0`); tidak ada stok, movement, HPP, transaksi, atau keuangan yang diubah. Repair hanya boleh dijalankan setelah kandidatnya diperiksa pada database server dan hanya untuk bulan aktif yang memang terdampak.

## Ringkasan Eksekutif

Aplikasi sudah memiliki fondasi yang cukup matang: transaksi purchase, store request, FIFO lot, produksi component, POS commit, void/refund, adjustment, payroll, dan laporan keuangan sudah terhubung. Kode PHP pada direktori `application/` lolos linting sintaks.

Masalah utama bukan ketiadaan fitur, melainkan **terlalu banyak state persediaan yang perlu tetap selaras**:

1. Dokumen bisnis: receipt, fulfillment, batch, order POS, refund, void, adjustment.
2. Movement ledger material dan component.
3. FIFO lot material dan component.
4. Stok bulanan material dan component yang menjadi state live saat ini.
5. Cache ketersediaan POS dan HPP live.
6. Profile item/material, UOM beli, UOM isi, dan konversi.

Saat satu jalur mengizinkan stok minus tetapi lot tidak boleh minus, atau saat satu halaman memperbaiki lot tanpa memakai mekanisme yang sama dengan halaman lain, mismatch dapat terjadi **di bulan aktif**. Cut-off membatasi dampak historisnya, tetapi tidak menggantikan guard pada proses berjalan.

**Kesimpulan prioritas:** jangan menambah repair massal baru terlebih dahulu. Tetapkan satu kontrak persediaan untuk bulan aktif, lalu jadikan adjustment, daily matrix, daily recon, reconcile, PO/SR, produksi, POS, void, dan refund sebagai pemanggil service yang sama.

## Batas Audit

Audit ini mencakup:

1. Pembacaan struktur controller, model, library, konfigurasi, SQL historis, dan dokumentasi.
2. Pemeriksaan sintaks seluruh file PHP pada `application/` menggunakan PHP XAMPP: **lolos**.
3. Pemeriksaan data material/component, lot, movement, monthly stock, profile UOM, dan sumber movement sebagai bukti forensik; bukan keputusan repair untuk bulan lampau.
4. Pembacaan jalur utama purchase, adjustment, batch component, POS confirmation, void/refund, FIFO, dan ledger.

Audit ini belum mensimulasikan seluruh kombinasi UI di browser atau setiap transaksi bisnis satu per satu. Tidak ada automated test suite yang ditemukan, sehingga pengujian integrasi harus menjadi fase wajib setelah refactor.

## Catatan Data Historis

Audit awal sempat membaca beberapa angka mismatch pada periode yang telah lewat. Setelah konfirmasi bahwa aplikasi memakai cut-off bulanan, rincian angka, nama barang, dan daftar prioritas historis tersebut dihapus dari laporan operasional ini agar tidak disalahartikan sebagai masalah bulan aktif.

Data lama hanya boleh dibuka kembali bila diperlukan untuk forensik atas satu dokumen tertentu. Acuan pengendalian sehari-hari adalah bulan aktif dan opening hasil cut-off terakhir.
## Akar Masalah Utama

### P0 - Stok negatif diizinkan, tetapi defisit FIFO tidak memiliki model

Kebijakan bisnis Anda benar: POS tetap boleh menjual saat stok sistem habis/minus agar selisih fisik terlihat. Namun implementasi saat ini memiliki dua aturan yang berlawanan:

1. `InventoryLedger` dapat menerima `allow_negative_balance`, sehingga monthly stock dan movement bisa turun ke minus.
2. `MaterialFifoManager` dan `ComponentLotManager` menolak lot dengan saldo negatif.

Artinya ketika barang habis:

1. saldo buku turun/minus;
2. lot tidak dapat ikut turun/minus;
3. HPP untuk bagian yang tidak punya lot menjadi tidak jelas;
4. receipt atau adjustment berikutnya dapat membuat lot dan monthly tetap berbeda;
5. halaman yang memakai lot, monthly, atau cache POS dapat menampilkan hasil berbeda.

**Keputusan desain yang disarankan:** jangan membuat lot biasa menjadi negatif. Buat konsep `inventory deficit` atau `negative demand` yang eksplisit per identitas stok.

Identitas defisit minimal:

```text
scope + division + destination + item/material + UOM isi + profile_key
```

Alur yang disarankan:

1. POS/usage menghabiskan lot yang benar-benar tersedia dengan FIFO.
2. Sisa kebutuhan yang tidak punya lot disimpan sebagai defisit, bukan dipaksa ke lot.
3. Monthly stock boleh negatif karena mencerminkan buku stok.
4. Receipt/adjustment plus berikutnya menambah stok dan lot secara penuh, lalu mencatat penyelesaian defisit yang identitasnya sama.
5. Biaya receipt dipakai sebagai jejak biaya penyelesaian agar HPP tertunda atau koreksi HPP dapat ditelusuri pada tahap berikutnya.
6. Defisit adalah jejak kekurangan terpisah; stok fisik saat ini tidak dikurangi lagi oleh angka defisit yang sudah diselesaikan.

Dengan pola ini kebijakan minus tetap dipertahankan, tetapi lot dan biaya tidak perlu berpura-pura memiliki stok yang tidak ada.

### P0 - Cut-off component dapat mengubah saldo lot tanpa event setara

Pemeriksaan statis pada ComponentStockWriter menemukan jalur cut-off yang dapat langsung memperbarui saldo lot agar sama dengan target monthly component. Jalur ini tidak tampak sekaligus menaikkan total keluar lot maupun membentuk movement koreksi yang setara.

Konsekuensinya, audit lot periode yang sudah lewat dapat menemukan saldo yang tidak sama dengan kuantitas masuk dikurangi kuantitas keluar. Dengan kebijakan cut-off, jejak lama tersebut tidak perlu dibersihkan ulang. Namun jalur kode ini perlu diperbaiki sebelum cut-off berikutnya agar histori baru tetap dapat diaudit.

Perbaikan yang disarankan:

1. Jangan ubah saldo lot secara langsung saat cut-off.
2. Jika lot perlu dikurangi, gunakan event cut-off keluar melalui writer lot agar total keluar dan movement ikut tercatat.
3. Jika lot perlu ditambah, gunakan event cut-off masuk atau opening lot yang memiliki source dokumen opname/cut-off.
4. Simpan source table, source id, user, dan alasan pada setiap koreksi cut-off.
5. Tutup lot periode lama dengan lifecycle eksplisit, bukan dengan mengubah angka tanpa event.

Referensi: [ComponentStockWriter.php](/C:/xampp/htdocs/finance/application/libraries/ComponentStockWriter.php)

### P0 - Material dan component mempunyai writer/rebuild yang berbeda

Material menggunakan `InventoryLedger` dan `MaterialFifoManager`. Component menggunakan `ComponentStockWriter`, `ComponentLotManager`, `Production_model`, serta movement monthly component sendiri. Keduanya serupa secara bisnis tetapi tidak memiliki kontrak lifecycle yang sama.

Konsekuensinya:

1. void material adjustment menghapus movement sumber lalu rebuild identitas;
2. void component adjustment/batch membentuk reverse movement lalu rebuild;
3. lot material/component dapat berakhir pada status dan angka yang berbeda untuk peristiwa void yang semakna;
4. biaya negatif component dapat lolos dari jalur historis walaupun writer baru mencoba menahan biaya negatif.

**Arah perbaikan:** buat kontrak yang sama untuk kedua domain. Bukan berarti tabel harus disatukan sekarang, tetapi perilakunya harus identik: post, allocation, reverse, void, rebuild, defisit, dan audit.

### P0 - Adjustment tersebar pada empat halaman per domain

Untuk bahan baku setidaknya ada adjustment, daily matrix, daily recon, reconcile, serta opname. Untuk component ada component adjustments, component daily, component daily recon, component reconcile, dan lot-only repair.

Masalahnya bukan banyak halaman, tetapi banyak kemungkinan writer. Halaman rekonsiliasi dan lot-only repair secara konsep mudah mengubah satu lapisan state tanpa memperbarui lapisan lain.

**Kontrak UI yang disarankan:**

| Halaman | Peran yang aman | Yang tidak boleh dilakukan langsung |
| --- | --- | --- |
| Adjustment | Satu-satunya pembuat dokumen mutasi kuantitas/nilai | Mengubah monthly atau lot tanpa movement |
| Daily matrix | Pembaca harian dan pintu buat draft adjustment | Menulis saldo sendiri |
| Daily recon | Membandingkan, memberi checkpoint, mengusulkan adjustment | Repair lot/balance otomatis |
| Reconcile | Audit teknis dan membuat repair plan | Tombol `repair all` langsung tanpa review identitas |
| Opname | Mengubah hasil hitung fisik menjadi dokumen adjustment khusus | Menimpa state live secara langsung |

Semua aksi koreksi harus berakhir ke satu `InventoryAdjustmentService` atau `ComponentAdjustmentService` yang membentuk event, lot allocation, cost, dan projection secara atomik.

### P1 - Monthly stock, lot, dan nilai belum punya guard otomatis

Unique index untuk identity monthly dan lot sudah ada. Ini bagus, tetapi index hanya mencegah duplikasi key; ia tidak menjamin angka antar tabel sama.

Data historis menunjukkan guard seperti ini pernah belum ada. Guard berikut harus dijalankan untuk transaksi bulan aktif dan saat proses cut-off:

**Guard wajib setelah setiap post/void/rebuild:**

```text
1. lot_balance = qty_in - qty_out, atau status lifecycle menjelaskan pengecualian.
2. sum(lot aktif) - deficit = closing_qty monthly.
3. total_value monthly = cost subledger yang valid.
4. same-UOM selalu memakai faktor 1.
5. satu dokumen hanya boleh punya satu posting aktif per line/event type.
6. reverse event menunjuk movement asal dan tidak menghapus jejak asal.
```

### P1 - Void/refund dan adjustment belum sepenuhnya immutable

Void adjustment material saat ini melakukan rollback lot, menghapus movement terkait, lalu melakukan rebuild. Walaupun dibungkus transaksi, penghapusan movement membuat audit lebih sulit dan berbeda dari jalur component yang menulis reverse movement.

**Arah yang disarankan:** movement posted tidak dihapus. Void selalu menambah event `VOID_REVERSE` yang menunjuk movement/dokumen asal. Rebuild hanya menyusun projection dari event aktif, bukan menggantikan histori.

Manfaatnya:

1. audit HPP sebelum/sesudah void tetap dapat ditelusuri;
2. retry lebih aman dan idempotent;
3. refund parsial tidak perlu melakukan delete-replay yang luas;
4. laporan keuangan dapat membedakan transaksi asli dan pembatalannya.

### P1 - Profile identity dan transisi item-centric belum sepenuhnya steril

Jejak profile lama dan script repair menunjukkan sistem pernah menjalani transisi material-centric ke item-centric. Beberapa lot/stok masih memiliki kombinasi `item_id`, `material_id`, profile, serta UOM yang tidak sinkron.

Perlu satu `ItemIdentityResolver` yang dipanggil oleh seluruh writer sebelum post dan sebelum rebuild. Resolver harus menghasilkan identitas canonical yang sama untuk PO, SR, adjustment, batch, POS, void, dan refund.

### P1 - Period lock inventory belum terlihat sebagai guard seragam

Generator cut-off material dan component sudah ada, termasuk guard bahwa periode yang sedang berjalan tidak digenerate sebagai opening. Namun belum terlihat status periode yang formal dan diterapkan seragam pada post, void, refund, serta backdate inventory.

Tanpa period lock yang eksplisit, periode yang sudah dianggap selesai masih berisiko berubah oleh transaksi tertanggal lama, void, atau generate ulang.

Rekomendasi:

1. Tambahkan status periode OPEN, CLOSING, CLOSED, dan REOPENED untuk material dan component.
2. Setelah opening berhasil dibuat, periode sebelumnya menjadi CLOSED.
3. Post, void, refund, dan adjustment bertanggal periode CLOSED harus ditolak.
4. Reopen hanya untuk role tertentu, dengan alasan, approver, waktu, dan audit log.
5. Koreksi setelah tutup dilakukan dengan reversal bulan berjalan atau reopen resmi, bukan repair lot langsung.

### P1 - Rebuild availability POS sinkron di setiap perubahan material

Setiap post `InventoryLedger` dapat memanggil rebuild availability produk terdampak untuk semua outlet. Ini benar dari sisi konsistensi, tetapi berisiko berat ketika receipt, adjustment, atau batch memiliki banyak line karena satu transaksi bisa memicu banyak perhitungan resep dan cache.

Perbaikan yang aman:

1. dalam satu transaksi, kumpulkan seluruh material/component terdampak;
2. tandai product cache `dirty` sekali;
3. enqueue satu job rebuild per outlet/product setelah commit;
4. worker atau endpoint terkontrol memproses batch;
5. POS tetap dapat membaca cache terakhir plus tanda freshness.

Jangan menjalankan rebuild seluruh produk dari setiap baris adjustment.

### Prinsip performa POS

POS tidak boleh menunggu proses laporan, rebuild semua resep, atau sinkronisasi layar lain. Simpan transaksi POS dibagi menjadi dua tahap:

1. Tahap cepat dan wajib: simpan order, pembayaran, penggunaan stok/defisit, biaya dasar, serta event stok dalam satu transaksi database.
2. Tahap latar belakang: refresh availability menu, cache laporan, ringkasan daily matrix, dan notifikasi.

Job latar belakang perlu memiliki identitas transaksi, status, percobaan ulang, dan mekanisme deduplikasi. Dengan begitu satu order tidak dapat menjalankan refresh yang sama berkali-kali, sementara halaman POS dapat segera kembali siap menerima order berikutnya.

## Guard Otomatis dengan Bahasa Operasional

Setelah adjustment, receipt, batch, POS, refund, void, atau cut-off, sistem memeriksa:

1. Apakah stok berjalan, lot, dan defisit masih sejalan.
2. Apakah kuantitas lot masuk, keluar, dan sisa masih masuk akal.
3. Apakah nilai stok dan biaya tidak negatif atau kosong secara tidak wajar.
4. Apakah UOM beli dan UOM isi sesuai aturan konversi.
5. Apakah transaksi memakai periode yang masih terbuka.

Jika ada masalah, halaman menampilkan penyebab dan dokumen sumber. Untuk transaksi non-POS, posting dapat ditahan sampai diperbaiki. Untuk POS, transaksi tetap selesai tetapi ditandai sebagai Defisit Persediaan agar ditangani melalui recon/adjustment.

## Temuan Aplikasi Umum

### P1 - Migrasi schema dijalankan dari request aplikasi

Beberapa model/controller masih menjalankan `ALTER TABLE` atau membuat kolom saat request, misalnya pada procurement, POS, production component adjustment, master, dan WhatsApp.

Risikonya:

1. request pertama di production bisa lambat atau lock tabel;
2. deployment dua server dapat menjalankan DDL bersamaan;
3. struktur server lokal dan Ubuntu dapat berbeda tanpa jejak migration resmi;
4. error schema muncul saat user melakukan transaksi, bukan saat deployment.

**Perbaikan:** semua DDL dipindahkan ke file migration SQL versioned. Runtime hanya memvalidasi versi schema dan menampilkan pesan maintenance yang jelas bila belum sesuai.

### P1 - Konfigurasi production perlu diketatkan

Konfigurasi default aplikasi masih `development` bila environment server tidak diisi. Selain itu CORS mengizinkan semua origin dan CSRF global tidak aktif.

Ini bukan penyebab mismatch stok, tetapi cukup penting untuk aplikasi keuangan.

Tindakan yang disarankan:

1. Ubuntu wajib memakai `CI_ENV=production`.
2. Gunakan base URL yang dipatok per environment, bukan host request mentah.
3. Batasi CORS ke origin yang memang dipakai.
4. Aktifkan CSRF untuk form web; buat pengecualian yang sangat terbatas untuk endpoint API yang memakai autentikasi lain.
5. Pindahkan credential dan encryption key dari file tracked ke environment secret sebelum deployment berikutnya.

### P2 - Duplikasi source dan ketiadaan test otomatis

`Purchase_model.php` dan `Purchase_model_server.php` memiliki blok logic besar yang serupa. Pencarian tidak menemukan pemanggilan runtime langsung ke versi `_server`, tetapi keberadaannya tetap berisiko dipakai manual pada server dan menyebabkan perbedaan perilaku.

Tidak ditemukan direktori test/spec aplikasi. Saat ini perbaikan stok bergantung pada SQL repair dan pengujian manual. Untuk domain keuangan/persediaan, kondisi ini terlalu berisiko.

## Sumber Kebenaran yang Harus Disepakati

Gunakan pembagian tanggung jawab berikut:

| Lapisan | Peran | Tidak boleh menjadi |
| --- | --- | --- |
| Dokumen bisnis | Bukti sebab transaksi | Saldo live yang diedit manual |
| Movement ledger immutable | Urutan semua akibat stok | Dihapus ketika void |
| FIFO lot / issue line | Subledger kuantitas lot dan biaya aktual | Tempat menampung stok minus fiktif |
| Defisit stok | Hutang kuantitas karena usage melebihi lot | Lot positif/negatif biasa |
| Monthly stock | Projection/cache saldo per identitas | Sumber yang diedit terpisah |
| Daily matrix/recon | Read model, audit, dan UX | Writer saldo langsung |
| Availability POS | Cache hasil recipe terhadap stok | Sumber persediaan utama |

Dengan pembagian ini, pertanyaan "stock, lot, atau movement mana yang benar?" memiliki jawaban operasional:

1. periksa dokumen sumber;
2. periksa movement immutable yang diturunkan dari dokumen;
3. periksa allocation lot/defisit untuk biaya;
4. rebuild projection monthly/cache dari event tersebut;
5. jangan memilih salah satu tabel lalu menimpa tabel lain tanpa event koreksi.

## Standar UI Bulan Aktif dan Cut-off

### Satu kartu status per identitas stok

Halaman detail material maupun component sebaiknya menampilkan kartu yang sama:

| Informasi | Arti untuk operator |
| --- | --- |
| Saldo berjalan | Saldo stok state pada bulan aktif |
| Lot tersedia | Lot FIFO yang dapat dipakai |
| Defisit | Kekurangan saat pemakaian/penjualan melampaui lot |
| Nilai persediaan | Nilai aktif yang menjadi dasar biaya |
| Periode | Bulan aktif serta status OPEN atau CLOSED |
| Pembaruan terakhir | Movement terakhir, waktu, dan sumber dokumen |

Jika saldo berjalan, lot, dan defisit tidak selaras, tampilkan status merah serta tombol Lihat Penyebab. Jangan langsung menampilkan tombol repair.

### Peran setiap halaman

1. Adjustment adalah satu-satunya halaman yang dapat membuat koreksi kuantitas dan nilai.
2. Daily matrix hanya menampilkan rekonstruksi dari opening bulan aktif dan movement. Tampilan perlu memuat label sumber data serta waktu pembaruan.
3. Daily recon hanya menemukan selisih dan membuat draft adjustment.
4. Reconcile hanya untuk diagnosa, preview repair plan, dan ekspor audit.
5. Opname membuat dokumen opname atau adjustment khusus, lalu writer resmi yang melakukan posting.
6. Endpoint lot-only dan repair massal hanya tersedia di workspace recovery Superadmin, dengan dry run, alasan, dan log audit.

### Guard saat cut-off

Sebelum periode ditutup, sistem perlu menampilkan checklist:

1. tidak ada POS commit gagal/pending;
2. tidak ada adjustment draft yang belum diputuskan;
3. tidak ada batch component setengah posting;
4. tidak ada deficit yang belum direview;
5. audit profile/UOM bulan aktif lulus;
6. movement, lot, dan monthly state dapat direkonsiliasi;
7. opening bulan berikutnya berhasil dibuat dan diverifikasi.

## Rencana Perbaikan Bertahap

### Fase 0 - Amankan operasional dan data

1. Backup database sebelum repair berikutnya.
2. Batasi endpoint `repair all`, `lot only adjust`, dan repair profile untuk superadmin; jalankan hanya dengan audit export.
3. Tambahkan halaman/cron audit baca-saja harian untuk mismatch material, component, profile UOM, nilai negatif, dan deficit.
4. Jangan menghapus lot atau movement sebagai respons pertama terhadap mismatch.
5. Sediakan laporan daftar identitas bermasalah beserta sumber dokumen dan rekomendasi tindakan, bukan hanya total selisih.

### Fase 1 - Tetapkan kontrak dan service tunggal

1. Tambahkan tabel defisit material/component atau subledger serupa.
2. Bentuk command/service tunggal untuk material dan component dengan kontrak post/reverse yang identik.
3. Semua UI adjustment, daily recon, matrix, reconcile, dan opname hanya memanggil command tersebut.
4. Void/refund memakai reversing event; tidak ada delete movement posted.
5. Validasi canonical profile/UOM sebelum movement dibuat.

### Fase 2 - Validasi cut-off dan repair terbatas dengan dry run

Fase ini hanya dilakukan bila audit bulan aktif atau hasil opening cut-off terakhir menunjukkan masalah. Daftar selisih periode lampau di bawah adalah contoh pola forensik, bukan antrean repair otomatis.

Urutan aman untuk setiap identitas bulan aktif atau opening terakhir yang bermasalah:

1. Tentukan dokumen sumber yang sah.
2. Audit semua movement, issue line, lot, dan monthly untuk identitas itu.
3. Tentukan apakah selisih berasal dari profile lama, void, adjustment, opening, atau usage minus.
4. Buat repair event/rebuild projection yang bisa diulang.
5. Verifikasi seluruh invariant sebelum identitas dinyatakan sehat.

Fokus pemeriksaan selalu dimulai dari identitas yang ditandai oleh audit bulan aktif atau oleh validasi opening bulan berjalan. Tidak ada daftar barang lama yang diproses otomatis.
### Fase 3 - Uji integrasi wajib

Sebelum refactor diaktifkan untuk semua transaksi, buat test otomatis untuk minimal skenario ini:

1. PO receipt ke gudang, transfer SR ke divisi, lalu usage POS.
2. Batch component memakai beberapa lot bahan dengan biaya berbeda.
3. POS menjual saat lot kurang: lot habis, defisit bertambah, tanpa membuat lot baru menjadi minus.
4. Receipt atau adjustment tambah dengan sumber yang jelas: defisit ditutup dan jejak biaya tertunda tercatat.
5. Recon fisik dari stok sistem nol ke stok fisik nol/positif: stok dan lot akhir harus sama, sementara jejak defisit tetap dapat diaudit sampai sumbernya diputuskan.
6. Adjustment plus dengan biaya, waste, spoil, process loss, dan variance.
7. Void adjustment, void batch, void POS, refund parsial, dan retry dua kali.
8. Same-UOM, cross-UOM, profile berbeda untuk material yang sama.
9. Rebuild projection berulang menghasilkan angka yang sama.
10. Monthly, lot, movement, cache availability, dan HPP konsisten setelah setiap skenario.

## Jawaban untuk Kekhawatiran Adjustment Minus

Ketika sistem mencatat stok nol dan ada defisit `5`, hasil hitung fisik tidak selalu membuktikan dari mana lima unit yang kurang itu berasal. Karena itu ada dua tindakan yang sengaja dibedakan:

```text
Recon fisik          => menyamakan stok dan lot yang ada hari ini.
Receipt/adjustment + => menutup defisit bila sumber tambahan barangnya sudah diketahui.
```

Contoh: stok sistem nol, defisit lima, lalu stok fisik terhitung tiga. Recon menampilkan tiga sebagai stok hari ini dan menyelaraskan lot ke tiga. Jejak defisit lima tidak dihapus diam-diam; operator dapat menutupnya melalui dokumen penerimaan atau adjustment tambah yang menjelaskan sumbernya. Pemisahan ini menjaga audit tetap jujur dan mencegah angka fisik hari ini dipakai untuk menebak histori transaksi lama.

## Prioritas Keputusan

1. Setujui model defisit stok sebagai cara resmi mendukung penjualan saat stok habis.
2. Setujui movement immutable/reversal sebagai aturan void dan refund.
3. Setujui adjustment tunggal sebagai writer, sementara matrix/recon/reconcile menjadi pembaca/pengusul.
4. Setelah itu, audit dan dry-run dibatasi pada bulan aktif serta opening hasil cut-off terakhir; bukan SQL massal yang mengubah lot bulan lampau.

## Status Implementasi Tahap 1 - Fondasi Bulan Berjalan

Status per 17 Agustus 2026: **fondasi kode dan migration siap diuji pada bulan aktif**. Fondasi ini baru aktif penuh pada sebuah database setelah migration dijalankan di database tersebut. Tidak ada repair otomatis untuk bulan yang sudah lewat.

Yang sudah disiapkan:

1. Tabel `inv_stock_deficit` dan settlement untuk mencatat kekurangan tanpa membuat lot atau saldo buku baru menjadi negatif.
2. POS material/component akan memakai lot yang tersedia, lalu mencatat sisa kebutuhan sebagai defisit terpisah.
3. Adjustment plus dapat menutup defisit dengan identitas yang sama, tetapi seluruh jumlah fisik adjustment tetap menjadi stok dan lot baru.
4. Void/refund parsial membatalkan defisit hanya sebesar kuantitas yang diretur, lalu mengembalikan lot hanya untuk bagian yang sebelumnya benar-benar terbit.
5. Tabel `inv_stock_period` dan library guard disiapkan untuk lock/reopen resmi setelah cut-off.
6. Perubahan kolom adjustment component dipindahkan ke migration; request web tidak lagi menjalankan `ALTER TABLE`.
7. `inv_stock_cutoff_event` disediakan sebagai jejak koreksi lot saat cut-off component.

Yang pada akhir Tahap 1 masih belum diaktifkan (statusnya telah diperbarui pada Fase 4B di bagian bawah dokumen ini):

1. Posting cut-off resmi yang membentuk opname dan stok awal bulan berikutnya dalam satu alur. Fitur ini sekarang tersedia sebagai **Posting Cut-off Resmi**, dengan audit run, preflight, dan guard agar saldo baru tidak terbentuk diam-diam.
2. Satu class writer teknis untuk seluruh domain. Saat ini perilaku operasionalnya sudah disamakan, tetapi writer material dan component masih berbeda di dalam kode karena struktur keduanya tidak sama.
3. Laporan HPP defisit khusus belum tersedia sebagai layar operator tersendiri. Namun fondasi koreksi biaya saat defisit ditutup sudah tersedia; POS tetap harus menyimpan HPP sementara penuh pada saat transaksi terjadi.
4. Test integrasi POS, PO/SR, batch, void/refund, dan cut-off pada database salinan atau data uji.

## Tahap 2 - Penyatuan Adjustment

Status: **siap uji terkontrol pada bulan aktif**.

Prinsip yang mulai diterapkan:

1. `Tambah/Kurangi` adalah mode `DELTA`: operator memasukkan jumlah perubahan.
2. `Stok Fisik` adalah mode `PHYSICAL_COUNT`: sistem menghitung perubahan dari `stok fisik - stok sistem`.
3. Daily Recon material dan component kini memakai normalizer input yang sama sebelum membuat adjustment. Catatan adjustment menyimpan mode asalnya.
4. Daily Matrix tetap menjadi pembaca movement/proyeksi; tidak diberi endpoint untuk menulis saldo atau lot.
5. Detail adjustment sekarang menyimpan snapshot stok sistem, stok fisik (jika mode stok fisik), dan kuantitas plus yang digunakan untuk menutup defisit.
6. Posting adjustment component dari halaman Adjustment maupun Daily Recon kini melewati helper yang sama untuk validasi draft, resolusi divisi, writer lot/movement, dan perubahan status.
7. Saat operator memakai mode `Stok Fisik`, sistem membaca saldo server terbaru. Jika halaman browser memakai angka lama, simpan ditolak dengan pesan yang menjelaskan agar halaman dimuat ulang. Ini mencegah adjustment fisik lama menimpa transaksi yang baru masuk.
8. Stok fisik membuat satu movement adjustment normal. Sesudah itu lot hanya disejajarkan secara struktural dan dicatat pada `inv_stock_cutoff_event`; sistem tidak lagi membuat movement adjustment kedua hanya untuk mengejar lot.
9. Saat mode `Tambah/Kurangi` mengurangi lebih besar dari stok aktif, yang benar-benar keluar dibatasi sampai saldo tersedia. Sisa kebutuhan dicatat sebagai defisit, bukan membuat monthly stock atau lot negatif.
10. Aturan yang sama diterapkan pada bahan baku dan component. Jalur lot-only/rebuild langsung tidak lagi menjadi alat koreksi operasional.

Tahap lanjutan ini telah diselesaikan bertahap: layar defisit dan period lock tersedia untuk operator berwenang, sedangkan posting cut-off resmi didokumentasikan pada bagian Fase 4B.

## Panduan Uji Tahap 1 dan Tahap 2

### Yang perlu dilakukan sebelum uji

1. Jalankan `2026-08-13a_inventory_active_month_deficit_period_lock_foundation.sql` untuk fondasi defisit, period lock, dan audit cut-off.
2. Jalankan `2026-08-17b_component_batch_input_trace_schema.sql` untuk kolom jejak batch component.
3. Jalankan `2026-08-17c_inventory_lot_schema_preflight.sql` untuk memastikan tabel lot material dan component beserta index intinya sudah lengkap sebelum request transaksi dipakai.
4. Jalankan `2026-08-17d_purchase_usage_purpose_schema_preflight.sql` agar request PO, receipt, dan store request tidak pernah mencoba mengubah struktur tabel sendiri.
5. Jalankan `2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql` agar POS dan modul WhatsApp tidak pernah mencoba mengubah struktur tabel ketika halaman atau transaksi dibuka.
6. Jalankan kelima file tersebut pada **lokal dan server** sebelum deploy PHP. Jalankan berurutan seperti daftar di atas.
7. Deploy seluruh perubahan PHP bersamaan dengan SQL tersebut, lalu muat ulang browser secara penuh (`Ctrl+F5`).
8. Uji hanya pada bulan aktif dan satu item/component uji yang mudah diverifikasi. Jangan memakai data bulan lama sebagai patokan karena bulan itu berada di luar ruang lingkup cut-off aktif.

### Uji 1 - Daily Recon bahan baku

1. Buka `Stok > Daily Recon Divisi`.
2. Pilih satu bahan dengan saldo dan lot yang cukup, misalnya ALMOND bila lotnya tersedia.
3. Masukkan stok fisik lebih kecil dari stok sistem, pilih alasan yang sesuai, lalu posting.
4. Hasil yang diharapkan: muncul nomor adjustment, tidak ada pesan `Unexpected token`, stok dan lot berkurang dengan jumlah sama, serta dokumen muncul pada halaman Adjustment Divisi.
5. Buka rincian adjustment. Nantinya data audit harus menunjukkan stok sistem saat input, stok fisik, dan mode `Stok Fisik`.

### Uji 2 - Adjustment plus dan defisit

1. Gunakan item uji pada bulan aktif yang memiliki defisit tercatat, bukan data produksi normal.
2. Buat adjustment plus sebesar atau lebih besar dari defisitnya.
3. Hasil yang diharapkan: seluruh jumlah adjustment menambah stok dan lot. Bila identitasnya sama dengan defisit terbuka, defisit ikut berkurang atau tertutup.
4. Periksa `inv_stock_deficit` dan `inv_stock_deficit_settlement`: status/kuantitas defisit harus berubah, sementara lot bertambah sebesar jumlah adjustment yang benar-benar ditemukan/dimasukkan.

### Uji 3 - POS ketika stok/lot habis

1. Gunakan produk uji dengan bahan atau component yang stok lotnya sengaja tidak cukup.
2. Selesaikan satu transaksi POS dan tunggu job background commit stok selesai.
3. Hasil yang diharapkan: transaksi POS tetap dapat selesai, lot tidak menjadi negatif, dan kekurangan tampil sebagai defisit stok. Jangan memakai transaksi pelanggan nyata untuk pengujian ini.

### Uji 4 - Void atau refund parsial

1. Dari transaksi uji yang membuat defisit, lakukan refund sebagian.
2. Hasil yang diharapkan: defisit berkurang hanya sebesar qty refund. Lot hanya dikembalikan untuk bagian yang sebelumnya benar-benar dikeluarkan dari lot.
3. Lakukan refund sisa qty. Defisit tersisa harus berstatus `VOID` atau nol, tanpa pembalikan ganda.

### Uji 5 - Component

1. Lakukan Daily Recon Component dengan satu component dan lot yang tersedia.
2. Lakukan juga dari halaman Adjustment Component.
3. Hasil yang diharapkan: keduanya memiliki proses posting yang sama, status dokumen `POSTED`, movement/lot terbentuk sekali, dan rincian menyimpan snapshot input.

### Batas yang belum perlu diuji

1. Halaman laporan HPP defisit khusus belum dibuat. Namun saat defisit POS ditutup, fondasi koreksi biaya dapat mencatat selisih biaya aktual secara terpisah; snapshot HPP transaksi awal tetap tidak boleh dihapus atau dibuat nol.
2. Jangan memakai halaman Tutup Periode untuk membuat stok awal bulan baru. Halaman ini hanya mengunci transaksi; proses opname dan pembentukan opening tetap dijalankan melalui alur cut-off yang sudah digunakan.

## Perbaikan Lanjutan - Adjustment, Batch, dan Extra POS

Status per 17 Agustus 2026: **siap diuji di bulan aktif setelah seluruh migration pada panduan di atas dijalankan**.

1. Rincian Adjustment Divisi dan Adjustment Component kini menampilkan sampai tiga produk yang memakai bahan/component tersebut. Jika produknya lebih banyak, jumlah sisanya ditampilkan dan daftar lengkap tersedia melalui tooltip.
2. Endpoint simpan adjustment component dan batch kini menangkap error backend lalu mengirim respons JSON. Browser tidak lagi menampilkan potongan HTML atau stack trace sebagai pesan `non-JSON`; bila respons benar-benar terputus, operator mendapat petunjuk aman untuk memuat ulang dan memeriksa apakah dokumen sudah terbentuk.
3. Preview batch pada Daily Component diberi jeda singkat saat angka diketik sehingga server tidak menghitung preview untuk setiap karakter.
4. Setelah posting batch, refresh availability POS digabungkan per batch. Produk/outlet yang sama hanya dihitung sekali, bukan sekali untuk setiap material/component input. Saldo, movement, lot, dan cache POS tetap disinkronkan sebelum posting dinyatakan selesai.
5. Batch component tidak lagi menyinkronkan monthly stock dari lot setelah setiap input. Monthly stock berubah melalui movement ledger, sedangkan lot adalah subledger FIFO. Penghapusan sinkronisasi lama ini mencegah batch lambat dan mencegah angka monthly yang benar tertimpa angka lot yang belum selesai diproses.
6. Input component langsung dalam sebuah batch kini mengeluarkan lot component dengan sumber batch yang jelas, menyimpan biaya aktualnya ke input batch, lalu membentuk satu movement `PRODUCTION_OUT`. Saat batch di-VOID, lot input component itu dipulihkan sebelum lot output batch dinonaktifkan.
7. Ditemukan koreksi master resep khusus: `BEBEK GORENG BUMBU REMPAH` memakai `NASI PUTIH` sebanyak `1 GR`, sementara extra `TANPA NASI` memang dirancang mengurangi `90 GR`. File `2026-08-17a_repair_bebek_rempah_nasi_putih_recipe_qty.sql` memperbaiki resep menjadi `90 GR`. Audit database lokal pada 17 Agustus menunjukkan keduanya sekarang sudah `90 GR`.
8. Penyebab konkret respons `non-JSON` pada Quick Batch Component adalah request simpan sebelumnya mencoba menjalankan `ALTER TABLE` untuk menambah kolom jejak input. Perubahan struktur tabel tidak boleh terjadi saat operator menyimpan produksi karena dapat gagal atau tertahan oleh lock lalu mengirim halaman HTML. Kolom itu kini dipasang melalui `2026-08-17b_component_batch_input_trace_schema.sql`. Pemeriksaan struktur lot juga dipindahkan menjadi preflight read-only; tidak ada lagi `CREATE TABLE` atau `ALTER TABLE` dari request POS, batch, atau adjustment.
9. Teks tampilan Component Daily yang sebelumnya terbaca seperti `â€¢` telah dibersihkan pada view terkait. Respons error juga tidak lagi menampilkan kode HTML mentah kepada operator.
10. Sisa perubahan struktur `usage_purpose` pada PO, receipt, dan fulfillment dipindahkan dari `Purchase_model` ke `2026-08-17d_purchase_usage_purpose_schema_preflight.sql`. Request purchase sekarang hanya membaca struktur yang sudah dipasang, sehingga tidak dapat mengunci tabel melalui `ALTER TABLE` saat operator menyimpan dokumen.
11. Pemeriksaan struktur POS dan WhatsApp diperlakukan sama: request hanya memvalidasi kolom/tabel yang sudah ada. Kolom nama pelanggan POS dan struktur tambahan WhatsApp sekarang dipasang oleh `2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql`, bukan saat operator membuka halaman atau menyimpan transaksi.

### Uji perbaikan lanjutan

1. Jalankan `2026-08-17a_repair_bebek_rempah_nasi_putih_recipe_qty.sql` pada setiap database target sebelum menguji transaksi BEBEK GORENG BUMBU REMPAH dengan extra TANPA NASI. Database lokal sudah terverifikasi selaras, tetapi server tetap perlu diperiksa/dijalankan sendiri.
2. Buka `Inventory > Stock > Adjustment Divisi` dan `Production > Component Adjustments`, lalu buka tab rincian. Pastikan kolom produk terkait terlihat pada bahan/component yang digunakan di resep produk.
3. Dari `Production > Component Daily`, masukkan quick adjustment dan quick batch. Respons yang diharapkan adalah nomor dokumen atau pesan JSON yang jelas, bukan halaman HTML/`Unexpected token`.
4. Posting satu batch uji dengan beberapa input. Pastikan status menjadi `POSTED`, input berkurang, output/lot bertambah satu kali, dan POS masih membaca ketersediaan produk terkait. Waktu posting seharusnya lebih singkat terutama untuk batch dengan banyak bahan yang mengarah ke produk sama.
5. Setelah menjalankan `2026-08-17b_component_batch_input_trace_schema.sql` dan `2026-08-17c_inventory_lot_schema_preflight.sql`, lakukan `Ctrl+F5`, lalu coba Quick Batch Component lagi. Hasil yang diharapkan: batch berhasil diposting atau muncul pesan bisnis yang spesifik, misalnya bahan tidak cukup. Pesan umum `respons non-JSON` tidak boleh muncul lagi.
6. Buat batch uji yang memakai satu input component langsung. Pastikan lot input berkurang sekali, movement `PRODUCTION_OUT` terbentuk sekali, biaya input tersimpan, dan lot output bertambah sekali. VOID batch uji tersebut dan pastikan lot input kembali serta lot output tidak lagi aktif. Jangan memakai batch produksi nyata untuk uji VOID.
7. Buat satu PO/receipt atau store request uji setelah menjalankan `2026-08-17d_purchase_usage_purpose_schema_preflight.sql`. Hasil yang diharapkan: penyimpanan tidak memicu perubahan struktur database dan tujuan pemakaian tetap tersimpan sesuai pilihan operator/default item.
8. Buat satu transaksi POS uji dengan pelanggan, lalu simpan. Hasil yang diharapkan: transaksi berjalan normal tanpa pesan schema atau waktu tunggu akibat perubahan struktur tabel. Jika modul WhatsApp dipakai, buka halaman WhatsApp dan pastikan halaman juga tidak meminta perubahan schema saat dibuka.

## Verifikasi Teknis Yang Sudah Dilakukan

1. Lint PHP berhasil untuk 19 controller, model, library, dan view yang diubah.
2. Uji unit kecil untuk mode adjustment memastikan stok fisik memakai saldo server terbaru, delta memakai saldo terbaru secara aman, input browser yang stale ditolak pada stok fisik, dan stok fisik negatif ditolak.
3. Pemeriksaan statis memastikan manager FIFO material/component tidak lagi menjalankan DDL saat request transaksi serta writer batch tidak lagi menimpa monthly stock dari lot.
4. Migration `2026-08-17b_component_batch_input_trace_schema.sql` dan `2026-08-17c_inventory_lot_schema_preflight.sql` berhasil dijalankan dua kali pada database sementara untuk memeriksa syntax, struktur input batch, dan enam tabel lot. Tidak ada transaksi bisnis pada database aplikasi yang dibuat selama verifikasi ini.
5. Audit read-only database lokal memastikan resep BEBEK GORENG BUMBU REMPAH dan extra TANPA NASI sama-sama memakai `90 GR`.
6. Migration `2026-08-17d_purchase_usage_purpose_schema_preflight.sql` berhasil dijalankan dua kali pada database sementara bertabel minimal untuk memastikan aman dijalankan ulang.
7. Migration `2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql` berhasil dijalankan dua kali pada database sementara untuk memastikan perubahan POS/WhatsApp aman dijalankan ulang.
8. Audit read-only schema lokal memastikan seluruh kolom lot, `usage_purpose`, `pos_order.customer_name`, dan struktur tambahan WhatsApp yang diperlukan sudah tersedia.
9. Pemeriksaan statis seluruh folder `application` tidak menemukan DDL runtime yang aktif; satu hasil tersisa hanyalah komentar penjelasan pada `Purchase_model.php`.

## Status Implementasi Tahap 2A Sampai Tahap 4 - 18 Agustus 2026

Tahapan berikut sudah dibuat untuk bulan stok yang sedang berjalan. Tidak ada script di tahap ini yang memperbaiki atau mengubah angka stok bulan lama secara massal.

### Tahap 2A - Rincian batch component

Sebelum:

1. Tombol Pemakaian dan Trace pada `Production > Component Batches` dapat gagal dibuka ketika struktur jejak input batch di server belum lengkap atau backend mengirim error HTML.

Sesudah:

1. Tombol tersebut membuka halaman rincian batch yang lebih stabil.
2. Halaman tetap dapat membaca batch lama yang belum mempunyai kolom `plan_role`.
3. Jika backend bermasalah, operator memperoleh pesan JSON yang jelas, bukan modal kosong atau kode HTML.

### Tahap 3.1 - Halaman Defisit Stok

Halaman baru: `Inventory > Kontrol Stok > Defisit Stok`.

Sebelum:

1. Ketika penjualan atau pemakaian lebih besar daripada lot yang tersedia, operator hanya dapat menelusuri kekurangan dari tabel database atau banyak halaman stok.

Sesudah:

1. Operator dapat melihat sumber defisit, tanggal, lokasi, bahan/component, kebutuhan, jumlah yang sudah tertutup, dan sisa defisit.
2. Rincian defisit menjelaskan langkah yang aman: terima barang atau lakukan adjustment plus sesuai kondisi fisik. Sistem akan menutup defisit lebih dahulu sebelum membuat lot baru.
3. Halaman ini bersifat audit. Tidak ada tombol yang diam-diam menambah stok atau mengubah HPP.

### Tahap 3.2 - Tutup dan buka kembali periode stok

Halaman baru: `Inventory > Kontrol Stok > Tutup Periode Stok`.

Sebelum:

1. Tidak ada layar resmi untuk menutup atau membuka kembali bulan stok.
2. Perubahan setelah cut-off berisiko masuk ke bulan yang seharusnya sudah selesai.

Sesudah:

1. Admin berwenang dapat membuka periode bahan baku dan component untuk bulan aktif, melihat peringatan defisit/mismatch, lalu menutup periode dengan konfirmasi sadar.
2. Saat periode ditutup, jalur transaksi stok utama menolak perubahan baru pada bulan tersebut, termasuk POS, receipt, transfer, adjustment, batch, dan void/refund yang mengubah stok.
3. Jika koreksi memang harus dibuat, admin membuka kembali periode dengan alasan. Riwayat status periode tetap tersimpan.
4. Peringatan defisit atau selisih lot bukan blokir otomatis. Ini mengikuti kebijakan cut-off bulanan: operator dapat menilai keadaan fisik dan menutup periode setelah menyatakan sudah memahami peringatannya.
5. Menutup periode tidak membuat stok awal bulan berikutnya secara otomatis dan tidak mengubah stok lama. Opname serta pembentukan opening tetap memakai alur cut-off yang sudah ada.

### Tahap 3.3 - Penjagaan jalur transaksi stok

Sebelum:

1. Beberapa jalur bahan baku dan component dapat menulis lot atau stok tanpa membaca status periode secara seragam.
2. Operator mudah bingung karena halaman Adjustment dan Daily Recon sama-sama terlihat seperti tempat koreksi stok.

Sesudah:

1. Jalur utama bahan baku dan component kini membaca status periode sebelum membuat perubahan stok atau lot.
2. Periode bulan aktif dibuat otomatis sebagai `OPEN` pada transaksi stok pertama. Ini hanya membuat catatan kontrol, bukan mengubah saldo.
3. Bulan lama yang belum memiliki catatan periode tetap tidak diblokir agar data pengembangan/historis tidak mendadak berhenti. Namun bila suatu bulan sudah ditutup secara resmi, semua penulis stok akan menolaknya sampai dibuka kembali.
4. Halaman Adjustment kini menjelaskan bahwa operator mengisi jumlah perubahan yang sudah diketahui, misalnya waste, spoil, barang rusak, atau barang ditemukan.
5. Halaman Daily Recon kini menjelaskan bahwa operator mengisi stok fisik akhir. Sistem menghitung selisihnya lalu menyamakan lot dengan stok akhir saat posting.
6. Aturan ini diterapkan pada bahan baku dan component agar perilaku operator konsisten walaupun struktur profile bahan baku dan component memang berbeda.

### Tahap 4 - Pemeriksaan kesehatan stok bulan aktif

1. Halaman Tutup Periode menampilkan ringkasan ringan bahan baku dan component: jumlah baris yang diperiksa, jumlah baris yang berbeda antara stok bulanan dan lot, serta total besar selisihnya.
2. Pemeriksaan ini hanya alarm untuk membantu cut-off. Sistem tidak menjalankan repair otomatis dan tidak memaksa operator memperbaiki data bulan lama satu per satu.
3. Jumlah temuan mengikuti bulan aktif yang dipilih dan dapat berubah setelah transaksi, recon, atau cut-off. Angka di layar hanya sinyal audit; penyebab dan tindak lanjut tetap harus diverifikasi pada transaksi bulan aktif terkait.

### Fase 4A - Simulasi cut-off stok awal, baca-saja - 20 Agustus 2026

Sebelum:

1. Halaman **Tutup Periode Stok** hanya memberi tombol mengunci bulan. Operator belum dapat melihat terlebih dahulu saldo akhir mana yang akan menjadi calon stok awal bulan berikutnya.
2. Untuk mengecek kesiapan cut-off, operator harus membuka beberapa halaman stok, lot, dan opname secara terpisah.

Sesudah:

1. Rincian setiap periode sekarang menampilkan **Simulasi Stok Awal Bulan Berikutnya** untuk bahan baku atau component.
2. Simulasi membaca saldo akhir dari stok bulanan periode yang dipilih dan menampilkan:
   - jumlah baris saldo akhir yang dibaca;
   - jumlah saldo positif yang dapat menjadi calon stok awal;
   - jumlah saldo nol yang tidak perlu dibawa;
   - jumlah saldo minus yang masih harus dibereskan;
   - nilai total calon stok awal; dan
   - apakah opening pada bulan berikutnya sudah pernah ada.
3. Tabel contoh memperlihatkan area, barang atau profil, satuan, saldo akhir, nilai, dan status calon opening. Bila opening sudah ada, statusnya ditandai agar operator tidak menimpa data manual tanpa pemeriksaan.
4. Simulasi ini **tidak membuat apa pun**: tidak membuat stok awal, opname, lot, movement, atau perubahan nilai. Angka pada bulan yang masih berjalan juga diberi peringatan karena dapat berubah mengikuti transaksi berikutnya.
5. Penutupan periode tetap hanya mengunci tanggal transaksi. Proses yang benar-benar membentuk opname, opening, dan lot carry-forward tetap menjadi tahap posting cut-off terpisah setelah preview ini disetujui.

Untuk pemeriksaan teknis baca-saja, tersedia perintah berikut:

```powershell
C:\xampp\php\php.exe index.php inventory_tools preview_period_cutoff domain MATERIAL month 2026-08 limit 5
C:\xampp\php\php.exe index.php inventory_tools preview_period_cutoff domain COMPONENT month 2026-08 limit 5
```

Perintah tersebut hanya mencetak hasil simulasi JSON. Tidak ada tabel bisnis yang ditulis.

### Penyempurnaan operator dan dashboard - 18 Agustus 2026

1. Dashboard sekarang membedakan dua hal yang sebelumnya mudah tercampur:
   - **Defisit stok aktif** adalah kekurangan nyata pada transaksi bulan berjalan. Angkanya dibaca dari `inv_stock_deficit`, bukan dari saldo minus bulanan lama.
   - **Stok kritis** hanya menampilkan stok rendah atau nol. Saldo minus historis tidak lagi ditampilkan sebagai alarm operasional baru.
2. Halaman `Inventory > Kontrol Stok > Defisit Stok` sekarang menggabungkan beberapa kejadian yang memiliki barang, area, UOM, dan profil yang sama menjadi satu baris kerja. Operator melihat sisa defisit terkini, tanggal aktivitas terakhir, jumlah kejadian sumber, profil katalog, dan nilai referensi sebelum memilih tindakan.
3. Riwayat sumber tetap tidak digabung di database. Ini penting agar POS void/refund tetap dapat membatalkan hanya kejadian miliknya. Tombol **Rincian** membuka semua kejadian tersebut untuk audit.
4. Defisit tidak memiliki satu lot khusus pada saat dibuat, karena lot memang belum tersedia. Satu atau beberapa receipt/adjustment berikutnya dapat menutup defisit tersebut. Oleh karena itu yang menjadi patokan operasional adalah **sisa defisit per profil**, bukan nomor lot yang belum ada.
5. Dari daftar Defisit Stok, tombol **Recon** sekarang membuka form recon langsung di halaman yang sama. Form membawa divisi, tujuan, UOM, dan `profile_key` yang sama sehingga operator tidak perlu berpindah ke Daily Recon atau berisiko memilih profil bernama serupa.
6. Pencarian pada Adjustment Bahan Baku memprioritaskan profil yang masih punya stok aktif di divisi yang dipilih. Sebagian pilihan katalog tetap disediakan sebagai fallback untuk adjustment plus. Bila biaya stok live nol, sistem menampilkan dan memakai referensi harga katalog terakhir/standar yang cocok dengan profil tersebut; operator tidak perlu menebak harga awal.
7. Nilai rupiah defisit selalu harus mengikuti kuantitas sisa. File `2026-08-18b_recalculate_stock_deficit_remaining_value.sql` merapikan data yang dibuat sebelum pembaruan ini, termasuk row `SETTLED` atau `VOID` yang masih menyimpan nilai sisa lama. File aman dijalankan berulang dan tidak membuat stok, lot, atau movement baru.
8. Daily Recon tetap dapat dibuka langsung bila dibutuhkan. Bahkan jika profil itu belum memiliki row saldo bulanan, halaman menampilkan profil defisit tersebut sebagai stok sistem nol dengan referensi harga katalog. Ini mencegah salah pilih profil lain yang namanya serupa.

**Batas penting:** writer baru menjaga lot agar tidak turun di bawah nol dan mencatat kekurangannya sebagai defisit. Ini tidak otomatis mengubah saldo minus dari bulan historis atau writer legacy yang belum melalui jalur baru. Karena aplikasi memakai cut-off bulanan, data historis itu tidak dijadikan alarm defisit bulan aktif.

### Penyempurnaan Defisit - 19 Agustus 2026

#### Cara membaca angka pada daftar Defisit Stok

1. Tampilan awal hanya menunjukkan status `OPEN`, yaitu pekerjaan yang masih perlu diperiksa. Baris diurutkan dari aktivitas terbaru.
2. Satu baris adalah akumulasi seluruh kejadian defisit yang masih terbuka untuk satu identitas stok. Rinciannya tetap tersedia melalui tombol `Rincian`.
3. Identitas bahan baku adalah lokasi, divisi, tujuan, item/material, UOM beli, UOM isi, dan profil pembelian yang sama.
4. Identitas component adalah lokasi, divisi, component, dan UOM yang sama.
5. Defisit tidak dikunci ke nomor lot tertentu. Saat defisit dibuat, memang tidak ada lot yang dapat dipakai. Nomor lot yang kemudian masuk tetap tercatat pada dokumen receipt, SR, batch, atau adjustment yang menyelesaikannya.

#### Recon langsung dari Defisit Stok

1. Tombol `Recon` pada daftar Defisit Stok membuka modal di halaman yang sama, bukan mengarahkan operator ke halaman Daily Recon yang kosong.
2. Operator memasukkan stok fisik akhir yang benar-benar dihitung. Sistem membaca ulang stok sistem ketika disimpan.
3. Jika stok fisik berbeda dari stok sistem, sistem membuat adjustment sesuai selisih, membentuk lot bila ada penambahan, lalu dapat menyelesaikan defisit identitas yang sama.
4. Jika stok fisik sudah sama dengan stok sistem, operator tetap dapat menutup defisit lama tanpa membuat adjustment nol. Ini dipakai bila barang sebenarnya sudah masuk setelah kejadian defisit, lalu hasil hitung fisik mengonfirmasi saldo sekarang benar.
5. Centang penyelesaian defisit hanya bila operator yakin barang fisik benar-benar ada. Jika belum yakin, simpan recon tanpa centang agar defisit tetap menjadi pekerjaan audit.
6. Jika stok sistem dan hitungan fisik sama-sama nol, layar tidak akan menutup defisit. Angka nol tidak dapat menjadi bukti bahwa kekurangan lama sudah selesai.

**Pemisahan yang perlu diingat:** Recon pada halaman Defisit Stok selalu dimulai dari **stok fisik saat ini**, bukan dari angka defisit. Defisit adalah jejak kekurangan lama. Jadi bila stok sistem 10 dan hasil hitung fisik juga 10, operator memasukkan 10: stok dan lot tetap 10. Dengan pilihan penyelesaian defisit, sistem hanya menutup jejak defisit yang cocok dan tidak mengurangi stok menjadi nol.

Jika hasil fisik lebih kecil dari stok sistem, layar hanya membuat pengurangan stok/lot sesuai hasil hitung. Pengurangan tidak dapat dipakai untuk menutup defisit karena tidak membuktikan adanya barang tambahan.

**Arti pilihan "Selesaikan defisit terbuka":**

1. Dicentang: setelah stok fisik positif dicatat, sistem juga membuat jejak penyelesaian untuk defisit yang identitasnya sama. Bila stok fisik sama dengan stok sistem, ini adalah satu-satunya perubahan yang disimpan.
2. Tidak dicentang: sistem hanya menyamakan stok dan lot bila angka fisik berbeda. Defisit tetap terbuka untuk ditelusuri kemudian.
3. Pada stok fisik nol atau saat hasil hitung lebih kecil daripada stok sistem, pilihan ini dinonaktifkan. Tidak ada dasar fisik untuk menutup defisit pada kondisi itu.
4. Defisit nol stok tidak wajib ditutup segera. Defisit tersebut tetap sebagai alarm sampai salah satu hal terjadi: stok fisik benar-benar ditemukan, receipt/transfer/batch yang sesuai menambah stok, atau sumber pemakaian yang salah dibatalkan/di-refund sesuai alur aslinya.

#### Contoh FRENCH FRIES FROZEN

Audit baca-saja database lokal pada 19 Agustus menemukan dua defisit FRENCH FRIES FROZEN di KITCHEN: satu dari POS 17 Agustus pukul 19:24 dan satu dari POS 18 Agustus pukul 09:22. Keduanya terjadi ketika lot belum tersedia. Batch `ICB202608180004` baru diposting pukul 10:03 pada 18 Agustus dan membuat lot aktif 16 porsi.

Artinya stok 16 porsi yang terlihat sekarang tidak membuktikan POS sebelumnya salah. Stok tersebut memang masuk setelah dua penjualan tadi. Operator perlu menghitung fisik. Bila fisik benar 16 porsi, gunakan Recon langsung dengan pilihan penyelesaian defisit agar dua kejadian lama tercatat selesai tanpa mengubah saldo 16 porsi.

#### Apa yang dapat menyelesaikan defisit

1. Adjustment Plus bahan baku/component: jumlah penuh tetap menambah stok dan lot. Defisit dengan identitas sama ikut ditutup sampai jumlah yang tersedia.
2. Receipt PO: menutup defisit pada lokasi penerimaan yang sama. PO ke gudang menutup defisit gudang; PO yang diterima langsung ke divisi menutup defisit divisi tersebut.
3. Fulfillment SR: saat barang benar-benar pindah dari gudang ke divisi, stok divisi bertambah penuh dan defisit bahan baku divisi yang sama dapat ditutup.
4. Hasil Batch Produksi: output component bertambah penuh sebagai lot baru dan dapat menutup defisit component pada lokasi/divisi/UOM output yang sama.
5. Semua penyelesaian menyimpan jejak sumber dan jumlah yang ditutup pada `inv_stock_deficit_settlement`. Tidak ada riwayat POS, batch, atau adjustment yang dihapus.

#### Contoh NORI

Jika NORI pada profil yang tepat memiliki stok dan lot nol serta defisit 1.000 GR, lalu hasil hitung fisik adalah 1.000 GR, lakukan Recon dengan pilihan penyelesaian defisit:

1. Stok dan lot menjadi 1.000 GR.
2. Defisit 1.000 GR menjadi nol/tertutup.
3. Jejak penyelesaian menunjukkan bahwa recon fisik tersebut yang mengonfirmasi kondisi tersebut.

Hasil ini berbeda dari pola lama yang mengurangi jumlah adjustment untuk menutup defisit. Pola baru tidak lagi membuat situasi "defisit selesai tetapi stok tetap nol".

### Tahap lanjutan - 19 Agustus 2026

#### Penyelesaian defisit dari sumber stok yang sah

1. Transfer bahan baku antar-divisi kini diperlakukan sama dengan receipt PO, fulfillment SR, adjustment plus, dan output batch component: stok tetap masuk penuh ke tujuan, lalu defisit lama hanya dapat ditutup pada identitas stok yang sama.
2. Identitas yang harus sama mencakup domain, lokasi/divisi/tujuan, item atau component, UOM, serta profile pembelian untuk bahan baku. Sistem tidak boleh memakai profil yang hanya bernama sama untuk menutup defisit secara otomatis.
3. Penyelesaian defisit tidak mengurangi stok baru. Ia hanya menambah jejak pada `inv_stock_deficit_settlement`, sehingga operator dapat melihat sumber yang membuat kekurangan lama dianggap selesai.

#### Kesehatan Stok Aktif

1. Halaman baru `Inventory > Kontrol Stok > Kesehatan Stok Aktif` adalah daftar kerja baca-saja untuk bulan yang dipilih. Halaman ini memeriksa bahan baku dan component pada stok bulanan, lot aktif, dan nilai persediaannya.
2. Daftar menandai tiga jenis masalah: lot aktif tanpa row stok bulanan yang relevan, jumlah stok berbeda dengan jumlah lot, dan nilai stok berbeda dengan nilai lot.
3. Lot lama yang tidak aktif pada bulan terpilih tidak dimasukkan sebagai pekerjaan operator agar sisa data pengembangan historis tidak memenuhi daftar bulan berjalan.
4. Tombol recon mengarah ke alur koreksi yang sudah ada. Halaman kesehatan tidak pernah mengubah stok, lot, atau value secara diam-diam.
5. Rekonsiliasi component sekarang juga membaca **nilai stok dibanding nilai lot**, bukan hanya jumlah. Jadi sebuah component tidak lagi ditandai `Match` bila jumlahnya sama tetapi HPP-nya berbeda.
6. Contoh nyata hasil audit `BEEF BURGER` per 18 Agustus 2026: stok bulanan dan lot sama-sama `11`, tetapi nilai stok `-Rp33.300,00` sedangkan nilai lot `Rp44.000,00`. Selisih `-Rp77.300,00` ini adalah temuan nilai/HPP. Jejaknya menunjukkan opening Juni `9` unit bernilai nol kemudian dipakai POS dengan biaya `Rp3.700` per unit; adjustment tambah `11` unit berikutnya juga bernilai nol. Jumlah kembali menjadi `11`, tetapi nilainya tetap negatif dan kemudian terbawa ke Agustus. Sementara itu lot opening Agustus menyimpan `11` unit dengan biaya `Rp4.000` per unit. Sistem tidak boleh menebak nilai yang benar atau menjalankan sinkron lot untuk kasus tersebut; operator perlu mengoreksi nilai berdasarkan bukti biaya yang sebenarnya.
7. Rekonsiliasi lot component menghitung seluruh lot yang masih aktif sampai tanggal acuan, termasuk lot yang diterima pada bulan sebelumnya. Dengan begitu, angka lot pada halaman rekonsiliasi dan Kesehatan Stok Aktif memakai arti saldo yang sama.

#### Penutupan periode

1. Detail Tutup Periode sekarang menampilkan jumlah selisih stok, lot, dan nilai beserta tautan langsung ke Kesehatan Stok Aktif.
2. Peringatan tetap memerlukan pengakuan sadar operator, bukan repair otomatis. Ini menjaga prinsip cut-off bulanan: stok awal bulan berikutnya dapat dibersihkan melalui opname yang terkontrol, sementara jejak transaksi dan alasan tetap dapat diaudit.
3. Pemeriksaan kesehatan hanya dijalankan ketika operator membuka layar kontrol stok atau detail periode. Ia tidak ditambahkan ke proses simpan POS sehingga tidak memperlambat transaksi kasir.

### Penyamaan Adjustment dan Daily Recon - 19 Agustus 2026

#### Masalah sebelumnya

1. Bahan baku dan component sama-sama memakai konsep Daily Recon, tetapi pilihan untuk menyelesaikan defisit belum diperlakukan sama.
2. Pada bahan baku, pilihan hasil hitung fisik tersimpan ke draft adjustment lalu dibaca ulang saat posting.
3. Pada component, pilihan tersebut sampai ke controller tetapi belum ikut tersimpan ke draft. Saat draft diposting, writer tidak lagi mengetahui bahwa operator sudah mengonfirmasi penyelesaian defisit.
4. Akibatnya hasil yang sama dapat berbeda: recon component bisa menambah stok dan lot, tetapi defisitnya tetap terbuka, atau hanya memakai jumlah selisih penambahan padahal stok fisik akhir lebih besar.

#### Perilaku sekarang

1. Adjustment bahan baku dan component tetap memiliki writer masing-masing karena struktur data keduanya memang berbeda. Namun keduanya kini memakai aturan keputusan yang sama untuk defisit.
2. **Adjustment Plus biasa:** stok dan lot bertambah penuh. Defisit identitas yang sama dapat selesai maksimal sebesar jumlah yang benar-benar ditambahkan.
3. **Daily Recon / Stok Fisik:** bila hitungan fisik lebih tinggi daripada stok sistem, sistem mencatat stok fisik akhir dan lotnya seperti biasa. Karena hasil fisik itu menjadi bukti barang benar-benar ada, defisit identitas yang sama dapat diselesaikan sampai jumlah stok fisik akhir yang dikonfirmasi.
4. Penyelesaian defisit tidak pernah mengambil kembali stok atau lot yang baru dicatat. Contoh: stok sistem 5, hasil fisik 10, defisit terbuka 10. Setelah recon, stok dan lot tetap 10; defisit dapat menjadi 0 bila identitasnya cocok.
5. Pada Daily Recon reguler, selisih fisik yang positif dikirim sebagai konfirmasi penyelesaian untuk identitas yang sama. Pada form Recon langsung dari halaman Defisit Stok, operator tetap dapat memilih untuk tidak menyelesaikan defisit; adjustment stok tetap berjalan tetapi defisit dibiarkan terbuka sebagai pekerjaan audit.
6. Bila stok sistem dan stok fisik sudah sama, halaman Defisit Stok tetap dapat menutup defisit tanpa membuat adjustment nol. Ini hanya menyimpan jejak settlement, tidak mengubah stok atau lot.

#### Batas aman

1. Defisit hanya dapat diselesaikan pada identitas yang sama: domain, lokasi/divisi, barang atau component, UOM, serta profil bahan baku bila ada.
2. Angka fisik nol atau pengurangan stok tidak dapat menutup defisit, karena bukan bukti bahwa barang yang sebelumnya kurang sudah tersedia.
3. Data bulan historis tidak diubah. Aturan ini berlaku pada transaksi dan recon setelah deploy, sesuai pola cut-off bulanan.

### Klarifikasi Kesehatan Stok Aktif - 19 Agustus 2026

#### Arti "Lot aktif" disamakan di seluruh layar

1. Pada halaman **Kesehatan Stok Aktif**, lot aktif sekarang berarti lot dengan status `OPEN` saja. Lot `CLOSED` adalah jejak periode/riwayat lama dan tidak boleh ikut dibandingkan dengan stok bulan aktif.
2. Ini menyamakan arti angka lot dengan halaman rekonsiliasi bahan baku yang sejak awal hanya memakai lot `OPEN`.
3. Tombol **Rekonsiliasi** dari halaman Health juga sekarang membawa area/tujuan yang benar ke halaman rekonsiliasi. Sebelumnya parameter tujuan salah nama sehingga hasil yang terbuka bisa lebih lebar dari area temuan.

#### Contoh JERUK NIPIS

1. Pemeriksaan database lokal menemukan satu lot JERUK NIPIS di BAR, profil `87fbe181b2ec9c...`, berstatus `CLOSED` tetapi masih menyimpan angka historis `10` dengan nilai `Rp12.000`.
2. Stok bulan aktif untuk profil itu sebenarnya `0`, dan lot `OPEN` juga `0`. Profil JERUK NIPIS lain di BAR maupun KITCHEN semuanya cocok antara stok dan lot aktif.
3. Sebelum pembaruan, halaman Health ikut menjumlahkan lot `CLOSED`, sehingga menampilkan temuan palsu seolah-olah ada selisih `10` dan `Rp12.000`. Halaman Rekonsiliasi tidak menampilkannya karena halaman tersebut sudah hanya membaca lot `OPEN`.
4. Tidak perlu membuat adjustment, recon, atau mengubah lot historis JERUK NIPIS untuk kasus ini. Setelah deploy PHP, temuan palsu tersebut tidak lagi muncul pada Kesehatan Stok Aktif.

#### Jika jumlah sama tetapi nilai stok dan nilai lot berbeda

1. Ini adalah masalah **nilai/HPP**, bukan masalah jumlah fisik. Jangan memakai Daily Recon untuk memaksa nilai menjadi sama, karena Daily Recon dipakai untuk mengoreksi jumlah fisik dan lot.
2. Operator terlebih dahulu memeriksa sumber biaya yang benar: dokumen PO/SR, hasil batch produksi, adjustment yang disetujui, serta biaya lot aktif yang benar-benar masih tersedia.
3. Jika sumber biaya sudah jelas, koreksi yang aman adalah **Koreksi Nilai/HPP** khusus: jumlah stok dan lot tetap, tetapi nilai total dan harga rata-rata/biaya lot aktif diperbarui bersamaan, disertai alasan serta dokumen bukti.
4. Koreksi tersebut harus membuat jejak transaksi nilai yang tidak dapat dihapus, tidak boleh langsung menimpa biaya lot dari tombol rekonsiliasi lama, dan harus ditolak bila periode stok sudah `CLOSED`.
5. Layar dan writer Koreksi Nilai Stok sekarang tersedia sebagai jalur terpisah. Jangan mengaktifkan kembali tombol repair nilai lama, karena repair itu berisiko mengubah HPP tanpa jejak keuangan yang lengkap.

### Penyelesaian Stock Health dan Recon Gudang - 19 Agustus 2026

#### Hasil cek lot CLOSED pada gudang dan component

1. Gudang tidak memiliki lot `CLOSED` yang masih bersaldo pada data lokal yang diperiksa. Jadi tidak ada pola JERUK NIPIS yang perlu dibersihkan pada gudang.
2. Component memiliki satu jejak historis `SIMPLE SYRUP` berstatus `CLOSED` dengan saldo lama. Saldo component aktif dan lot `OPEN`-nya sudah sama. Lot lama ini tidak lagi ikut dihitung oleh Stock Health.
3. Artinya pembanding Stock Health sekarang seragam: bahan baku divisi, bahan baku gudang, dan component semuanya hanya memakai lot `OPEN` untuk kondisi bulan aktif.
4. Bila sebuah temuan tetap tampil sesudah pembaruan ini, jangan langsung diasumsikan sebagai sisa lot `CLOSED`. Itu perlu diperlakukan sebagai selisih aktif sampai dibuktikan sebaliknya.

#### Cara menyelesaikan satu temuan Health

1. **Jumlah berbeda** atau **Qty dan nilai berbeda**: lakukan hitung fisik. Gunakan Recon Stok Fisik. Sistem menyamakan jumlah stok dan lot dengan angka fisik yang dicatat. Nilai belum boleh dikoreksi sebelum jumlah sudah sama.
2. **Nilai berbeda** dengan jumlah yang sudah sama: gunakan halaman `Koreksi Nilai Stok`. Halaman ini tidak mengubah jumlah barang sama sekali. Operator memilih nilai mana yang telah diverifikasi benar: nilai lot aktif, nilai stok sistem, atau total nilai dari dokumen yang diperiksa.
3. **Lot belum punya stok bulanan**: jangan langsung tambah atau kurangi nilai. Telusuri receipt, SR, batch, adjustment, atau pembentukan stok awal. Kasus ini biasanya perlu ditangani melalui alur stok fisik/cut-off terlebih dahulu.
4. Koreksi nilai hanya dapat diposting pada bulan aktif dan hanya bila jumlah stok sama dengan jumlah lot `OPEN`. Lot `CLOSED` tidak dapat dipilih atau diubah.
5. Semua koreksi nilai membuat nomor dokumen, nilai sebelum/sesudah, biaya tiap lot `OPEN`, alasan, catatan, dan pengguna yang memposting. Jika keputusan nilai ternyata salah, buat koreksi nilai baru. Jangan mengubah atau menghapus dokumen lama.

#### Recon gudang

1. Tautan Rekonsiliasi dari temuan gudang sekarang membuka `Recon Stok Fisik Gudang`, bukan lagi hanya audit lot gudang.
2. Operator memasukkan stok fisik akhir. Jika berbeda, sistem membuat dokumen Adjustment Gudang resmi dan menyelaraskan lot aktif melalui writer yang sama.
3. Jika stok sistem **sudah sama** dengan hasil hitung fisik tetapi lot `OPEN` masih berbeda, sistem tidak membuat adjustment nol. Sebagai gantinya, sistem menyelaraskan lot aktif ke angka fisik dan mencatat jejak audit koreksi lot. Ini penting untuk kasus seperti stok `76.000 ML` tetapi lot masih `190.000 ML`.
4. Pilihan `Selesaikan defisit terbuka` tetap terpisah dari hitung fisik. Pilihan ini hanya menutup jejak defisit identitas yang sama jika hasil fisik positif membuktikan barang memang tersedia. Pilihan tersebut tidak mengurangi stok yang baru dicatat.
5. Jika jumlah stok sistem dan fisik sudah sama, operator dapat memilih menyelesaikan defisit tanpa membuat adjustment nol. Jika keduanya nol, defisit tidak dapat ditutup dari angka nol.

#### Pesan setelah Koreksi Nilai Stok

1. Setelah koreksi nilai berhasil, halaman dapat terbuka kembali pada kondisi nilai stok dan lot sudah sama.
2. Kondisi itu bukan kegagalan posting. Layar sekarang menampilkan pesan hijau bahwa nilai telah selaras, bukan peringatan seolah-olah koreksi ditolak.

#### Temuan lama, defisit, dan selisih nilai

1. Stock Health dapat dibuka untuk bulan lama sebagai audit, tetapi halaman koreksi nilai hanya mengizinkan posting pada bulan aktif. Ini menjaga cut-off: data bulan lama tidak diubah diam-diam.
2. Temuan lama tidak otomatis disembunyikan hanya karena umurnya lama. Jika stok bulan aktif masih tidak sama dengan lot `OPEN`, perbedaannya tetap relevan untuk pembentukan stok awal dan cut-off berikutnya.
3. Defisit dan selisih nilai adalah dua antrean berbeda. Defisit mencatat jumlah yang dipakai ketika lot belum cukup. Selisih nilai membandingkan nilai stok bulan aktif dengan nilai lot `OPEN` yang ada sekarang.
4. Menutup defisit tidak otomatis mengubah nilai stok atau nilai lot. Sebaliknya, Koreksi Nilai tidak menutup defisit dan tidak mengubah jumlah. Keduanya dapat berakar dari transaksi lama yang sama, tetapi harus diperiksa dan ditutup dengan tindakan yang sesuai.
5. Bila ada defisit sekaligus selisih nilai, urutannya adalah: pastikan jumlah fisik dan lot terlebih dahulu, selesaikan defisit bila memang barang terbukti ada, lalu koreksi nilai berdasarkan dokumen biaya yang benar.
6. Bila operator memilih bulan lama di Stock Health, layar hanya boleh dipakai sebagai petunjuk investigasi. Lot adalah saldo berjalan, sehingga angka bulan lama tidak boleh menjadi dasar posting koreksi baru. Tombol tindakan pada bulan lama mengarahkan kembali ke bulan aktif.
7. Audit lokal 19 Agustus: lot `CLOSED` JERUK NIPIS BAR masih menyimpan riwayat `10 GR`, tetapi stok Agustus dan lot `OPEN` profil tersebut sama-sama `0`. Gudang tidak mempunyai lot `CLOSED` bersaldo; component hanya memiliki satu riwayat `SIMPLE SYRUP` bersaldo `171` yang tidak lagi dihitung oleh Stock Health.

### Perbaikan Identitas Defisit NORI dan Antrean Koreksi Nilai - 19 Agustus 2026

#### Penyebab NORI terlihat defisit padahal ada stok

1. Ditemukan 12 kejadian defisit POS NORI Kitchen, total `17,03 GR`, yang tersimpan pada profil katalog `a45...`.
2. Stok dan lot yang benar-benar dipakai Kitchen berada pada profil `6cce...`, dengan saldo bulan aktif dan lot `OPEN` sama-sama `150 GR`.
3. Karena penutupan defisit sengaja harus cocok sampai profil pembelian, halaman Defisit Stok benar membaca `0` untuk profil `a45...` di Kitchen. Saldo `150 GR` tidak hilang; ia hanya berada pada identitas profil yang berbeda. Saldo profil gudang juga tidak boleh dipakai untuk menutup defisit Kitchen.
4. Penyebab teknisnya adalah saat POS mengalami kekurangan, identitas defisit dapat tertimpa profil katalog pilihan meski FIFO telah memakai profil stok divisi yang lain.

#### Perilaku setelah perbaikan

1. POS sekarang mengutamakan profil stok aktif divisi. Bila FIFO sempat memakai lot, profil lot FIFO tersebut dipakai untuk mencatat defisit parsial selama seluruh lot yang dipakai berasal dari satu profil.
2. Bila resep masih membawa profil lama yang sudah tidak ada di stok divisi, sistem mencoba stok aktif divisi sebelum kembali ke katalog. Ini mencegah katalog gudang mengalahkan identitas stok Kitchen/Bar.
3. SQL `2026-08-19g_retarget_nori_kitchen_deficit_to_actual_profile.sql` memperbaiki 12 jejak NORI yang sudah telanjur salah. SQL tersebut **hanya mengganti penanda profil dan kunci defisit**; tidak mengubah stok, lot, movement, nilai, HPP, atau kas.
4. Setelah SQL dijalankan, halaman rincian NORI menampilkan stok sistem dan lot aktif Kitchen `150 GR`. Operator dapat memasukkan hasil hitung fisik sebenarnya, lalu memilih apakah defisit lama memang sudah layak ditutup. Sistem tidak menutupnya diam-diam.
5. Bila suatu barang benar-benar memiliki stok pada profil lain, halaman rincian menampilkan peringatan di bagian atas beserta tombol menuju Daily Recon / Join Profile. Tujuannya agar operator tidak memasukkan saldo profil lain ke form defisit dan tanpa sengaja menggandakan stok.

#### Halaman Koreksi Nilai Stok

1. Halaman `Koreksi Nilai Stok` sebelumnya memang hanya tampak seperti riwayat apabila dibuka dari sidebar tanpa satu temuan Health terpilih. Itu tidak cukup praktis.
2. Sekarang halaman tersebut memiliki dua bagian: antrean **Temuan nilai yang siap diperiksa** dan **Riwayat koreksi nilai bulan ini**.
3. Antrean hanya menampilkan `Nilai berbeda` ketika jumlah stok dan lot sudah sama. Baris `Jumlah berbeda` atau `Qty dan nilai berbeda` tetap harus melalui Recon Stok Fisik lebih dahulu.
4. Jadi halaman tersebut bukan sekadar riwayat. Operator dapat memilih baris aktif, memeriksa lot `OPEN`, lalu membuat koreksi nilai yang terdokumentasi tanpa mengubah jumlah barang.

### Fase 3A - Audit Integrasi Bulan Aktif dan HPP Defisit - 19 Agustus 2026

#### Tujuan

1. Audit ini memeriksa transaksi bulan aktif secara **baca-saja**. Ia tidak membuat adjustment, tidak memindahkan lot, tidak mengubah stok, dan tidak mengubah data penjualan.
2. Pemeriksaan memisahkan dua hal yang berbeda: jejak transaksi yang putus (masalah sistem) dan selisih stok/lot aktif (antrean pemeriksaan operator melalui Kesehatan Stok Aktif).
3. Audit tidak dipanggil dari halaman POS. Kasir tetap memakai proses background yang ada; pemeriksaan ini hanya dijalankan melalui CLI setelah deploy atau saat investigasi.

#### Yang diperiksa

1. Struktur tabel/kolom wajib untuk adjustment, batch, transfer, POS, defisit, dan period lock.
2. Adjustment bahan baku dan component posted harus mempunyai movement.
3. Batch component posted harus mempunyai movement output dan jejak pemakaian input yang sesuai.
4. Transfer posted harus memiliki movement keluar dan masuk.
5. Line POS harus memiliki salah satu bukti yang sah: movement stok/lot atau dokumen defisit yang terhubung ke line POS tersebut.
6. Aritmetika defisit harus konsisten: kebutuhan dikurangi qty terbit, settlement, dan pembalikan harus sama dengan sisa defisit.
7. Tidak boleh ada movement baru yang dibuat setelah periode stok berstatus `CLOSED`.
8. POS yang seluruh kebutuhannya menjadi defisit tetap harus membawa HPP sementara penuh. HPP tidak boleh menjadi nol hanya karena lot belum tersedia.
9. Audit juga memeriksa apakah database sudah mengenal label `INVENTORY_DEFICIT` dan sumber biaya `DEFICIT_PENDING`. Bila dua label ini belum tersedia, audit menyatakan **ERROR fondasi HPP**, walaupun POS tetap memakai fallback agar kasir tidak berhenti mendadak saat deploy bertahap.

#### Temuan lokal sebelum migration HPP

1. Audit Agustus menemukan **tidak ada** adjustment posted, batch posted, transfer posted, atau line POS yang benar-benar kehilangan jejak transaksi. Line POS yang sebelumnya terlihat `NONE` ternyata memang menunjuk ke defisit stok; audit kini mengenal hubungan tersebut sebagai bukti yang sah.
2. Ada `78` temuan Kesehatan Stok Aktif. Ini adalah antrean selisih stok/lot/nilai yang sudah terlihat pada layar Stock Health, bukan alasan untuk melakukan repair massal dari audit ini.
3. Ada `42` line POS defisit penuh di bulan aktif yang menyimpan HPP `0`. Nilai sementara yang seharusnya terbaca adalah total `Rp66.297,74`: bahan baku `Rp20.634,00` dan component `Rp45.663,74`.
4. Penyebab HPP nol: snapshot POS awal sebenarnya sudah membawa biaya, tetapi writer stok lama menimpa `total_cost_live` menjadi nol ketika seluruh kebutuhan masuk defisit. Ini bukan masalah lot baru, melainkan metadata HPP transaksi POS.

#### Perilaku setelah perbaikan

1. POS dengan lot cukup tetap memakai biaya lot/FIFO seperti sebelumnya.
2. POS dengan lot kurang tetap mengeluarkan bagian lot yang tersedia. Kekurangannya menjadi defisit, tetapi HPP transaksi tetap lengkap: biaya lot yang berhasil keluar ditambah biaya sementara untuk bagian yang defisit.
3. POS dengan lot nol tetap dapat selesai sesuai kebijakan. Stok/lot tidak dibuat negatif, defisit dicatat, dan HPP sementara dihitung `qty resep x biaya live` yang sudah ada di snapshot POS.
4. Saat barang nyata kemudian masuk dan menutup defisit, selisih antara biaya sementara dan biaya aktual dicatat sebagai koreksi HPP terpisah. Transaksi POS asal tidak ditulis ulang atau dihapus.
5. Referensi line POS sekarang memiliki label `INVENTORY_DEFICIT`, bukan `NONE` dengan angka ID yang membingungkan. Bila SQL belum dijalankan, aplikasi tetap menyimpan transaksi secara aman dan menggunakan label biaya fallback yang valid.

#### Cara menjalankan audit

Jalankan dari folder proyek dengan format argumen berikut. Jangan memakai format `--month=...`, karena controller CLI proyek ini memakai pasangan argumen biasa.

```powershell
C:\xampp\php\php.exe index.php inventory_tools audit_active_month_integrity month 2026-08 limit 20
```

Hasil `ERROR` berarti ada jejak transaksi yang perlu ditelusuri. Hasil `WARNING` pada Kesehatan Stok berarti ada pekerjaan recon/operator, tetapi audit tidak mengubah apa pun.

### Fase 3B - Koreksi HPP Saat Defisit Diselesaikan - 19 Agustus 2026

#### Masalah yang ditemukan

Audit bulan aktif memperlihatkan dua jejak biaya yang perlu dirapikan, terpisah dari jumlah stok dan lot:

1. Ada settlement defisit POS lama yang sudah berhasil menutup kekurangan barang dengan biaya sumber yang valid, tetapi belum mempunyai baris koreksi HPP. Ini terjadi sebelum integrasi `InventoryDeficitService` dan `InventoryDeficitCogsService` aktif bersama.
2. Ada beberapa koreksi HPP lama yang jumlahnya tidak sama dengan settlement asalnya. Baris seperti ini tidak boleh dibiarkan karena laporan HPP Live dapat membaca selisih rupiah yang salah.
3. Tidak ada perubahan jumlah fisik pada masalah ini. Stok, lot, movement, dan order POS tetap menjadi jejak asli; yang dirapikan hanya catatan selisih biaya antara HPP sementara saat transaksi dan biaya barang yang benar-benar menyelesaikan defisit.

#### Perilaku sekarang

1. Saat receipt, transfer masuk, batch component, atau adjustment plus yang sah menutup defisit POS, sistem membuat settlement defisit dan koreksi HPP dalam satu transaksi database.
2. HPP order POS lama tidak ditulis ulang. Jika biaya nyata lebih mahal atau lebih murah, selisihnya dicatat pada tabel koreksi HPP terpisah dan terbaca oleh laporan `HPP Live`.
3. Bila transaksi POS kemudian di-void atau direfund, koreksi HPP tersebut dibalik sesuai qty yang diretur. Sistem tidak membuat stok fiktif kembali dari bagian yang dulu hanya berupa defisit.
4. Audit baru memeriksa empat hal: fondasi tabel koreksi HPP, settlement POS tanpa koreksi HPP, settlement dengan biaya nol, serta hitungan koreksi/pembalikan yang tidak seimbang.

#### Migration perapihan bulan aktif

File `2026-08-19i_backfill_active_pos_deficit_cogs_adjustments.sql` hanya memproses settlement defisit POS pada bulan aktif:

1. Membuat koreksi HPP untuk settlement yang dahulu belum sempat tercatat.
2. Menormalkan koreksi yang belum pernah memiliki pembalikan refund/VOID bila jumlah/nominalnya tidak sama dengan settlement asalnya.
3. Tidak menyentuh stok, lot, movement, defisit, order POS, pembayaran, atau kas.
4. Baris yang sudah memiliki pembalikan refund/VOID tidak diubah otomatis. Baris tersebut tetap muncul sebagai antrean review manual agar histori refund tidak tertimpa.

### Fase 4B - Posting Cut-off Resmi - 20 Agustus 2026

#### Tujuan

1. Sebelumnya tombol **Kunci Periode** hanya memblokir transaksi tanggal lama. Tombol itu tidak membentuk opname, opening, atau lot bulan berikutnya. Kondisi tersebut berisiko meninggalkan periode terkunci padahal stok awal bulan baru belum resmi dibuat.
2. Sekarang proses yang dipakai untuk menutup bulan adalah **Posting Cut-off Resmi**. Proses ini memakai generator opname bahan baku dan component yang sudah ada, lalu memverifikasi opening sebelum periode benar-benar dikunci.
3. Tidak ada writer stok baru. Bahan baku tetap menggunakan generator gudang lalu divisi; component tetap menggunakan generator component. Layar cut-off hanya mengatur urutan, pengaman, dan jejak auditnya.

#### Urutan yang dilakukan sistem

1. Sistem membaca simulasi saldo akhir dan menolak posting bila ada saldo akhir minus.
2. Sistem mengecek defisit dan temuan Stock Health sebagai catatan yang harus dibaca. Temuan itu tidak otomatis mengubah stok.
3. Sistem menandai periode sebagai **Sedang ditutup** sehingga transaksi normal pada bulan sumber tertahan selama proses berjalan.
4. Sistem membentuk opname bulan sumber, opening bulan berikutnya, saldo monthly pembuka, dan lot carry-forward melalui writer resmi yang telah ada.
5. Sistem membaca ulang hasilnya. Semua saldo akhir positif harus sudah mempunyai opening bulan berikutnya.
6. Jika seluruh langkah berhasil, periode berubah menjadi **Terkunci**. Jika salah satu langkah gagal, periode otomatis berubah menjadi **Dibuka kembali**, dan riwayat run menyimpan hasil partial/error untuk ditinjau.

#### Pengaman cut-off terlambat

1. Posting cut-off hanya boleh dipakai sebelum bulan berikutnya mulai memiliki mutasi stok pada domain yang sama.
2. Contoh: cut-off Juli bahan baku tidak boleh diposting bila Agustus sudah memiliki receipt, POS, transfer, batch, atau adjustment bahan baku. Membentuk opening Juli setelah mutasi Agustus berjalan dapat mengubah dasar saldo Agustus secara diam-diam.
3. Sistem akan menolak kondisi tersebut dengan pesan jelas. Ini bukan error; ini pengaman data.
4. Cut-off yang terlambat memerlukan fase rebuild terkontrol terpisah. Jangan memaksa lewat tombol lock lama atau menghapus opening secara manual.
5. Sistem juga menolak bila ditemukan opening manual bulan berikutnya. Data manual harus ditinjau dahulu agar tidak tertimpa oleh carry-forward otomatis.

#### Halaman dan akses

1. Layar ada di `Inventory > Kontrol Stok > Tutup Periode Stok > Rincian`.
2. Semua user yang punya akses baca dapat melihat simulasi dan riwayat run.
3. Hanya Superadmin yang dapat menekan **Posting Cut-off Resmi**. Catatan resmi dan konfirmasi `POST CUT-OFF` wajib diisi.
4. Tombol kunci tanpa posting tidak lagi digunakan. Endpoint lama menampilkan arahan untuk memakai Posting Cut-off Resmi.
5. Jika server berhenti tepat saat status `Sedang ditutup`, Superadmin dapat memulihkan periode melalui form recovery setelah memastikan proses memang sudah tidak berjalan.

#### Migration dan preflight

1. Jalankan `2026-08-20a_inventory_official_cutoff_run_audit.sql` pada lokal dan server sebelum memakai tombol Posting Cut-off Resmi. SQL ini hanya membuat tabel audit `inv_stock_cutoff_run`; tidak mengubah stok, lot, movement, opname, opening, HPP, atau kas.
2. Tanpa SQL tersebut, layar tetap dapat dibuka tetapi tombol posting sengaja tidak muncul.
3. Preflight baca-saja dapat dijalankan dari CLI:

```powershell
C:\xampp\php\php.exe index.php inventory_tools preflight_period_cutoff domain MATERIAL month 2026-07
C:\xampp\php\php.exe index.php inventory_tools preflight_period_cutoff domain COMPONENT month 2026-07
```

Perintah ini tidak membuat periode, tidak membuat opening, dan tidak mengubah data apa pun. Hasil `can_post: true` berarti syarat teknis posting terpenuhi; operator tetap perlu memastikan opname fisik dan dokumen pendukung sudah selesai.

### Fase 5A - Kontrak HPP Defisit dan Preflight Deploy - 20 Agustus 2026

#### Yang diperbaiki

1. Writer POS yang baru sudah menghitung HPP resep secara penuh ketika lot nol atau hanya cukup sebagian. Bagian yang benar-benar keluar dari lot memakai biaya lot; bagian yang belum tersedia memakai biaya live yang tersimpan saat POS dikomit. Jadi stok tidak dipaksa minus, tetapi HPP transaksi tidak menjadi nol.
2. Audit integritas sekarang membedakan struktur tabel umum dengan dua label wajib untuk HPP defisit: `INVENTORY_DEFICIT` dan `DEFICIT_PENDING`. Sebelumnya audit dapat menyebut struktur tersedia walaupun dua label ini belum ada; sekarang ia memberi error deploy yang jelas beserta nama SQL yang perlu dijalankan.
3. Service koreksi HPP tidak lagi menganggap fondasi siap hanya karena dua tabel ada. Ia juga mengecek kolom pentingnya sehingga struktur setengah migration tidak menimbulkan insert koreksi yang tidak lengkap.
4. Ditambahkan SQL baca-saja `2026-08-20b_preflight_active_pos_deficit_hpp_repair.sql`. Gunakan file ini sebelum repair untuk melihat jumlah kandidat tanpa menyentuh stok, lot, movement, defisit, order, pembayaran, HPP, atau kas.

#### Hasil preflight lokal pada 20 Agustus 2026

1. Database lokal belum mengenal enum `INVENTORY_DEFICIT` dan `DEFICIT_PENDING`. Ini berarti `2026-08-19h` belum dijalankan di database tersebut.
2. Ditemukan `42` line POS defisit penuh dengan HPP sementara yang perlu dirapikan, `3` line defisit parsial yang belum membawa biaya kekurangannya, dan `44` line yang masih memakai label referensi lama.
3. Ditemukan `23` settlement defisit POS dengan biaya sumber yang sudah ada tetapi belum memiliki koreksi HPP, serta `4` koreksi lama yang jumlah/nominalnya belum sama dengan settlement asal.
4. Ada `1` koreksi yang sudah mempunyai jejak refund/VOID. Baris ini sengaja tidak boleh ditimpa otomatis dan tetap menjadi review manual.
5. Hasil ini tidak menunjukkan stok bulan aktif menjadi negatif. Audit menemukan `0` saldo monthly negatif serta `0` adjustment, batch, transfer, atau commit POS aktif yang kehilangan jejak transaksi.

#### Yang perlu dilakukan setelah deploy

1. Jalankan `2026-08-20b_preflight_active_pos_deficit_hpp_repair.sql` pada lokal terlebih dahulu. Pastikan hasilnya sesuai antrean yang ingin dirapikan.
2. Jalankan `2026-08-19h_pos_commit_deficit_reference_and_provisional_hpp.sql` pada lokal. SQL ini memperluas label POS dan memperbaiki HPP sementara bulan aktif, tanpa mengubah qty stok, lot, movement, defisit, order, pembayaran, atau kas.
3. Jalankan `2026-08-19i_backfill_active_pos_deficit_cogs_adjustments.sql` pada lokal. SQL ini membuat/merapikan koreksi biaya settlement pada bulan aktif dan tidak mengubah jumlah persediaan ataupun kas.
4. Bila hasil lokal sesuai, ulangi urutan yang sama pada server: `2026-08-19c` bila tabel koreksi belum ada, lalu `2026-08-19h`, kemudian `2026-08-19i`.
5. Jalankan ulang preflight dan audit CLI. Kandidat HPP dan koreksi tanpa refund/VOID harus menjadi `0`; antrean refund/VOID dapat tetap ada untuk review manual.

#### Uji operator setelah repair

1. Buat satu POS uji dengan bahan atau component yang lotnya nol. POS harus berhasil, lot tidak minus, defisit tercatat, dan detail stock commit menunjukkan HPP lebih dari nol untuk seluruh kebutuhan resep.
2. Ulangi dengan lot yang hanya cukup sebagian. HPP total harus terdiri dari biaya lot yang keluar ditambah biaya sementara untuk kekurangan, bukan hanya biaya lot yang tersedia.
3. Tutup defisit uji melalui receipt, transfer masuk, batch component, atau adjustment plus yang memiliki biaya. Defisit harus turun, stok/lot bertambah normal, dan laporan HPP Live membaca selisih biaya sebagai koreksi terpisah.
4. Lakukan void atau refund sebagian pada POS uji setelah defisit tertutup. Pastikan koreksi HPP berkurang proporsional, tanpa membuat stok fiktif dari bagian yang dahulu hanya berupa defisit.

### Fase 5B - Layar Audit Integritas Stok untuk Operator - 20 Agustus 2026

#### Yang diperbaiki

1. Sebelumnya audit integritas hanya nyaman dijalankan dari terminal/CLI. Sekarang tersedia halaman `Inventory > Kontrol Stok > Audit Integritas Stok` untuk membaca hasil pemeriksaan tanpa membuka SQL.
2. Audit tidak berjalan otomatis saat halaman dibuka. Operator memilih bulan lalu menekan `Jalankan Audit`, sehingga halaman biasa tetap ringan dan tidak membebani proses POS atau pekerjaan stok lain.
3. Setiap hasil ditampilkan dengan bahasa operasional: apa yang diperiksa, jumlah temuan, arti temuan, contoh data sumber, serta tombol menuju halaman penelusuran yang tepat.
4. Halaman ini sepenuhnya baca-saja. Menjalankan audit tidak membuat adjustment, tidak menyentuh stok/lot/defisit, tidak mengubah HPP, dan tidak membuat dokumen transaksi baru.

#### Yang perlu dilakukan setelah deploy

1. Jalankan `2026-08-20c_inventory_integrity_audit_menu_seed.sql` pada lokal dan server. SQL ini hanya menambahkan halaman, menu, dan hak akses.
2. Buka `Inventory > Kontrol Stok > Audit Integritas Stok`, pilih bulan aktif, lalu tekan `Jalankan Audit`.
3. Temuan berstatus `PERLU TINDAKAN` harus ditelusuri dari tombol halaman terkait. Jangan memperbaiki data langsung dari database hanya untuk menghilangkan temuan audit.
4. Untuk temuan kontrak HPP defisit, jalankan urutan preflight dan SQL Fase 5A terlebih dahulu. Audit hanya menjelaskan kebutuhan perbaikan; ia tidak mengeksekusi migration.

#### Uji operator

1. Buka halaman audit tanpa menekan tombol. Halaman harus tampil cepat dan tidak mengubah data apa pun.
2. Jalankan audit untuk bulan aktif dengan contoh data `5 baris`. Pastikan ringkasan dan daftar pemeriksaan muncul.
3. Buka satu temuan `Stok bulanan, lot OPEN, dan nilai`, lalu tekan tombol halaman terkait. Halaman Kesehatan Stok harus terbuka pada bulan yang sama.
4. Buka satu temuan POS/defisit. Pastikan contoh data sumber tampil, tetapi tidak ada tombol yang memposting repair dari halaman audit.

### Fase 6A - Laporan Penjualan Bersih dan Margin POS - 21 Agustus 2026

#### Yang diperbaiki

1. Laporan transaksi POS sekarang membaca nilai tagihan dan refund dengan pola yang konsisten. Khusus order yang telah direfund, nilai tagihan sebelum refund direkonstruksi dari pembayaran awal agar header order yang sudah berkurang tidak mengurangi refund dua kali. Uang diterima tetap terlihat sebagai informasi pembayaran terpisah.
2. Laporan transaksi sekarang menampilkan HPP saat jual, HPP yang dibalik akibat refund, koreksi HPP setelah defisit diselesaikan, HPP terkini, laba kotor, dan margin.
3. Laporan produk sekarang membagi diskon/promo/voucher/poin/compliment secara proporsional ke produk dan extra yang terkait. Refund yang belum posted tidak ikut mengurangi angka laporan.
4. HPP extra sebelumnya berisiko dibaca hanya dari biaya satuan. Sekarang HPP extra dihitung `qty x biaya snapshot extra`, lalu dikurangi biaya extra yang sudah direfund.
5. Perhitungan potongan memakai subtotal yang sudah tersimpan di header order POS. Dengan begitu laporan tidak membuat agregasi besar berulang dan tidak menambah beban saat kasir menyimpan transaksi.
6. Tabel laporan penjualan, produk, dan extra sekarang memiliki area scroll sendiri dengan judul kolom tetap terlihat.

#### Batasan yang disengaja

1. Laporan produk memasukkan extra yang melekat pada produk induk. Laporan extra adalah rincian dari nilai itu, sehingga dua laporan tidak boleh dijumlahkan menjadi satu total baru.
2. Koreksi HPP defisit tampil pada transaksi dan produk induk. Koreksi belum dibagi ke extra karena catatan koreksi persediaan saat ini tersimpan per line produk, bukan per line extra.
3. Penjualan bersih transaksi dapat mencakup pajak/service sesuai nilai tagihan POS. Penjualan bersih pada laporan produk/extra adalah penjualan item tanpa pajak/service.

#### Yang perlu dilakukan setelah deploy

1. Jalankan `2026-08-21a_finance_sales_margin_pos_menu_alias.sql` pada lokal dan server untuk menampilkan shortcut `Keuangan > Penjualan & Margin POS` dan memberi akses peran keuangan.
2. Baca panduan rinci di `docs/2026-08-21_laporan_penjualan_dan_margin_pos.md` sebelum memakai angka margin sebagai acuan laporan manajemen.
3. Jika kolom Koreksi HPP Defisit selalu nol, periksa urutan migration HPP defisit Fase 5A. Laporan tetap aman dibuka, tetapi belum memiliki sumber data koreksi sampai migration dipasang.

#### Uji operator

1. Buka `/pos/reports/sales`, lalu pastikan `Penjualan Bersih` berbeda dari `Uang Diterima` untuk contoh transaksi belum lunas atau memakai DP.
2. Buka detail satu order yang memakai promo, extra, atau refund. Pastikan blok Audit Penjualan & HPP menjelaskan sumber angka dari gross sampai laba kotor.
3. Buka `/pos/reports/sales-detail` dan `/pos/reports/sales-extra`, lalu gulir di dalam tabel. Judul kolom harus tetap terlihat.
4. Refund satu produk atau extra uji. Pastikan angka laporan berubah hanya setelah refund berstatus posted.

### Fase 6B - Audit Anomali Penjualan dan HPP POS - 21 Agustus 2026

#### Yang diperbaiki

1. Ditambahkan halaman `POS > Laporan POS > Audit Penjualan & HPP` untuk menampilkan antrean pemeriksaan transaksi tanpa mengubah data apa pun.
2. Audit memeriksa refund posted yang melebihi uang pembayaran, HPP transaksi negatif, penjualan ber-HPP nol, margin negatif, dan koreksi HPP defisit yang kehilangan tautan ke order atau line order asal.
3. Audit hanya membaca satu dataset order untuk seluruh kategori temuan. Halaman tidak menjalankan rebuild stok, tidak membuat job background, dan tidak mengulang agregasi HPP berat untuk setiap pemeriksaan.
4. Pajak dan service ditegaskan tetap sebagai pendapatan kafe. Keduanya ikut berada di nilai tagihan transaksi dan laba kotor transaksi, tetapi tidak dipaksa dibagi ke produk atau extra individual.
5. Setiap temuan memberi langkah aman dan tautan ke transaksi atau modul sumber. Tidak ada tombol repair otomatis karena refund, HPP snapshot, dan koreksi defisit harus tetap meninggalkan jejak audit yang benar.

#### Yang perlu dilakukan setelah deploy

1. Jalankan `2026-08-21b_pos_sales_hpp_integrity_audit_menu_seed.sql` pada server. File ini hanya menambahkan page, menu, dan hak akses; tidak mengubah transaksi, stok, lot, HPP, atau kas.
2. Baca [panduan audit penjualan dan HPP](/C:/xampp/htdocs/finance/docs/2026-08-21_audit_anomali_penjualan_dan_hpp_pos.md) sebelum memakai hasilnya sebagai dasar perbaikan data.
3. Bila audit memberi `DEFICIT_HPP_SCHEMA_UNAVAILABLE`, jalankan lebih dahulu migration HPP defisit Fase 5A. Jangan menganggap status itu sebagai kerusakan persediaan baru.

#### Uji operator

1. Buka `/pos/reports/sales-audit` tanpa parameter. Halaman harus terbuka cepat dan belum menghitung data.
2. Pilih bulan berjalan, lalu tekan `Jalankan`. Pastikan ringkasan dan pemeriksaan tampil tanpa mengubah stok, lot, refund, atau kas.
3. Buka satu contoh temuan refund atau HPP, lalu tekan `Detail`. Pastikan order sumber terbuka pada halaman Detail Transaksi POS.
4. Uji filter outlet dan nomor order. Hasil audit harus hanya membaca periode dan outlet yang dipilih.
5. Bandingkan satu transaksi di audit dengan `/pos/reports/sales-detail/{id}`. Nilai tagihan, refund, HPP terkini, dan laba kotor harus konsisten.

### Fase 6C - Pengaman Refund dan Konsistensi Laporan - 21 Agustus 2026

#### Yang diperbaiki

1. Refund satu order sekarang dikunci selama proses simpan. Jika dua klik/refund datang hampir bersamaan, permintaan kedua wajib membaca kondisi terbaru dan ditolak bila line atau sisa uang refund sudah habis. Ini mencegah refund ganda dari preview lama.
2. Nilai barang yang direfund dan uang yang benar-benar dikembalikan sekarang dipisahkan. Hal ini penting bila transaksi memakai diskon, promo, voucher, poin, atau compliment: laporan tetap mengetahui nilai kotor item sekaligus uang refund yang sesungguhnya. Untuk refund lama, migration mengisi fallback dari nominal refund yang tersedia; pemisahan diskon per item paling presisi mulai refund baru setelah migration dipasang.
3. Header order setelah refund sekarang menghitung ulang subtotal dan sisa potongan secara proporsional. Pajak dan service sengaja tidak ikut direfund karena tetap pendapatan kafe.
4. Laporan transaksi, produk, extra, dan audit tidak lagi mengurangi refund atau HPP refund dua kali. Perbaikan juga membetulkan query laporan extra yang sebelumnya dapat membaca ekspresi refund sebagai teks SQL.
5. Ditambahkan SQL audit khusus untuk refund posted yang lebih besar daripada pembayaran order. Script ini baca-saja dan tidak mengubah transaksi.

#### Temuan data yang perlu ditindaklanjuti

1. Empat refund historis yang semula terlihat melebihi tagihan ternyata sah; header ordernya memang sudah dikurangi saat refund diposting.
2. Satu transaksi benar-benar memiliki refund berlebih: `POS-20260802-0076`, pembayaran Rp35.000 dan total refund Rp36.000. Jangan dihapus langsung dari database karena kas dan stok sudah terpengaruh. Telusuri memakai `2026-08-21d_audit_pos_refund_overpayment.sql`, lalu lakukan reversal/void refund yang tercatat.

#### Yang perlu dilakukan setelah deploy

1. Jalankan `2026-08-21c_pos_refund_cash_and_gross_amount_schema.sql` pada lokal dan server sebelum deploy PHP. Script ini hanya menambah kolom dan melengkapi fallback nilai kotor refund lama.
2. Jalankan `2026-08-21d_audit_pos_refund_overpayment.sql` untuk membaca daftar refund yang perlu penanganan manual. Script ini tidak mengubah data.
3. Deploy model dan halaman laporan POS bersama-sama. Jangan deploy hanya view laporan karena rumusnya berada di model dan writer refund.

#### Uji operator

1. Buat order uji Rp100.000 dengan diskon Rp10.000 berisi dua item bernilai sama. Refund satu item harus mengembalikan Rp45.000, bukan Rp50.000, dan penjualan bersih tersisa Rp45.000.
2. Uji klik simpan refund dua kali secara cepat pada order yang sama. Hanya satu dokumen refund yang boleh terbentuk; percobaan kedua harus memberi pesan bahwa line atau nilai refund sudah tidak tersedia.
3. Bila pengaturan POS memakai pajak/service, refund seluruh produk harus menyisakan pajak/service sebagai pendapatan transaksi sesuai kebijakan perusahaan.
4. Buka `/pos/reports/sales-audit` setelah pengujian. Refund uji yang benar tidak boleh masuk kategori `REFUND_EXCEEDS_PAYMENT`.

### Fase 7A - Antrean Availability POS untuk Writer Stok - 21 Agustus 2026

#### Yang diperbaiki

1. PO/SR, adjustment, daily recon, dan batch produksi tidak lagi harus menunggu rebuild cache resep POS untuk setiap perubahan stok yang berhasil diposting.
2. Produk POS terdampak ditandai perlu diperbarui, lalu pekerjaan digabung per outlet dan produk. Banyak perubahan pada barang yang sama sebelum worker berjalan tetap menghasilkan satu rebuild akhir yang mengikuti perubahan terbaru.
3. Stok, lot, movement, nilai, defisit, dan HPP snapshot POS tidak dipindahkan ke antrean. Semua itu tetap memakai jalur transaksi utama yang sudah ada.
4. Ditambahkan worker CLI khusus yang dapat dijalankan cron. Worker POS yang telah ada juga mencoba memproses antrean baru, tetapi cron khusus tetap diperlukan agar pembaruan stok tetap berjalan pada hari tanpa transaksi POS.
5. Bila kode PHP masuk lebih dulu daripada SQL migration, aplikasi tetap fallback ke rebuild sinkron lama. Tidak ada transaksi yang ditolak karena tabel antrean belum tersedia.
6. Bila tabel antrean tersedia tetapi gagal menerima satu pekerjaan, kejadian tersebut juga langsung memakai rebuild sinkron lama. Pengaman ini mencegah menu POS dibiarkan membaca cache lama saat antrean sedang bermasalah.

#### Yang perlu dilakukan setelah deploy

1. Jalankan `2026-08-21e_pos_product_availability_queue.sql` pada lokal dan server sebelum deploy PHP.
2. Pasang cron Ubuntu setiap satu menit untuk menjalankan `php index.php pos availability_queue_run 100`. Sesuaikan path PHP dan aplikasi server.
3. Baca [panduan antrean availability POS](/C:/xampp/htdocs/finance/docs/2026-08-21_antrean_availability_pos_dan_performa_writer_stok.md) sebelum mengaktifkan cron.

#### Uji operator

1. Simpan satu PO/SR, adjustment, atau batch uji yang memakai bahan/component produk POS. Form transaksi harus kembali lebih cepat.
2. Jalankan worker sekali, lalu buka `/pos/stock-live`. Cache produk terdampak harus sudah kembali bersih dan mengikuti stok/HPP terbaru.
3. Lakukan beberapa perubahan cepat pada barang yang sama, baru jalankan worker. Produk harus mengikuti nilai terakhir tanpa rebuild berulang per perubahan.
4. Bila worker gagal, periksa `pos_product_availability_queue.last_error`; jangan melakukan repair data stok hanya untuk menghapus antrean gagal.

### Fase 7B - Monitor Operasional Antrean Availability POS - 21 Agustus 2026

1. Ditambahkan halaman `POS > Ketersediaan POS` untuk melihat job yang menunggu, sedang diproses, gagal, dan cache yang berhasil diperbarui hari ini tanpa menjalankan kalkulasi resep ketika halaman dibuka.
2. Operator berizin dapat menjalankan `Proses Sekali` dengan batas maksimal 100 job. Tombol ini memakai service antrean yang sama dengan cron; tidak menjalankan shell, tidak memasang cron, dan tidak mengubah stok, lot, movement, HPP transaksi, maupun kas.
3. Job berstatus `FAILED` dapat dimasukkan ulang satu per satu setelah penyebabnya diperbaiki. Aksi ini hanya mengembalikan job ke status menunggu dan memberi retry baru; worker berikutnya yang tetap melakukan hitung cache.
4. Halaman menyediakan baris cron yang dapat disalin dan tautan ke `Stock Live POS` untuk mengecek hasil cache per produk/outlet.

#### Yang perlu dilakukan

1. Jalankan `2026-08-21f_pos_availability_queue_monitor_menu_seed.sql` di lokal dan server setelah `2026-08-21e_pos_product_availability_queue.sql`.
2. Gunakan cron server yang sudah disiapkan: `* * * * * /usr/bin/php /www/wwwroot/finance/index.php pos availability_queue_run 100`.
3. Setelah menu muncul, simpan satu perubahan stok kecil, buka `POS > Ketersediaan POS`, lalu tekan `Proses Sekali`. Job harus berpindah ke selesai atau menunjukkan pesan error yang bisa ditindaklanjuti.

### Fase 8A - VOID/Reversal yang Tidak Menghitung Transaksi - 22 Agustus 2026

#### Prinsip yang dipakai

1. Dokumen yang dibatalkan tidak dihapus dari database. Sistem menyimpan dokumen asal dan jejak pembatalannya agar alasan serta pelakunya masih dapat diaudit.
2. Jejak pembatalan ditautkan langsung ke movement atau mutasi rekening asal. Dengan tautan ini, laporan operasional dan keuangan dapat mengabaikan keduanya sebagai satu transaksi yang tidak pernah berlaku.
3. Refund pelanggan tetap berbeda dari VOID. Refund adalah uang yang benar-benar kembali kepada pelanggan, sehingga tetap dibaca sebagai transaksi keuangan dan pengurang penjualan yang sah.
4. Perubahan ini berlaku untuk tindakan baru setelah migration dipasang. Data historis tidak dipaksa diubah atau dibersihkan kembali.

#### Yang diperbaiki

1. VOID adjustment bahan baku, transfer bahan baku, receipt/PO yang dibatalkan, penggunaan POS, dan pembalikan stok material sekarang menyimpan movement lawan berjenis `VOID_REVERSE` yang terhubung ke movement asal. Saldo stok dan lot kembali seperti sebelum dokumen dibuat, tetapi histori asli tidak dihapus.
2. VOID batch component sekarang membatalkan output dan pemakaian bahan/component dengan movement yang terhubung. Batch yang keliru tidak lagi terbaca sebagai produksi, pemakaian, waste, spoil, atau adjustment aktif pada ringkasan operasional.
3. Ringkasan harian dan bulanan material/component membaca pasangan movement tersebut sebagai nilai nol. Pembatalan spoil, waste, proses susut, selisih, dan adjustment sekarang membatalkan kategori asalnya juga, bukan dipindahkan ke kolom barang keluar biasa. Artinya adjustment spoil yang kemudian di-VOID tidak lagi dihitung sebagai spoil, dan batch yang di-VOID tidak lagi dihitung sebagai produksi.
4. VOID PO yang sudah dibayar sekarang mengembalikan saldo rekening melalui satu mutasi lawan untuk setiap pembayaran asal. Ringkasan PO hanya menghitung PO yang masih berlaku. PO penuh senilai Rp1.000.000 yang di-VOID tidak lagi masuk total belanja; jika nilai PO aktif diperbaiki menjadi Rp900.000, ringkasan membaca Rp900.000 dari line aktifnya.
5. VOID pencairan gaji, uang makan, dan kasbon yang belum memiliki cicilan sekarang memakai pasangan mutasi rekening yang terhubung. Saldo rekening kembali, tetapi laporan kas, estimasi keuangan, laporan periodik, rekap kas shift POS, dan laporan WhatsApp tidak menampilkan pasangan itu sebagai arus uang biasa.
6. VOID DP POS mendapat perlakuan yang sama. Sebaliknya, refund POS tidak disembunyikan karena refund merupakan pengembalian uang yang benar-benar terjadi.
7. Jika migration belum dijalankan, tindakan VOID yang membutuhkan pasangan movement/mutasi ditolak dengan pesan jelas sebelum data berubah. Ini lebih aman daripada membuat saldo berubah tanpa jejak pembalikan yang lengkap.
8. Perbaikan lanjutan: perhitungan reversal sekarang dibuat di dalam writer stok bulanan, bukan hanya di pintu masuk ledger. Receipt PO dan fulfillment Store Request yang normal tidak lagi mencoba memakai variabel reversal yang belum ada.
9. Halaman Purchase Order dan Store Request tidak lagi menampilkan potongan HTML atau pesan PHP mentah bila server gagal. Operator menerima pesan singkat untuk memuat ulang atau meneruskan error ke admin/server.

#### Yang perlu dilakukan setelah deploy

1. Jalankan `2026-08-22a_inventory_void_reversal_movement_link_foundation.sql` pada **lokal dan server** sebelum deploy PHP. File ini hanya menambah kolom tautan reversal, index, dan tipe movement `VOID_OUT` untuk component; tidak mengubah stok, lot, kas, transaksi, atau data historis.
2. Deploy seluruh perubahan PHP fase ini sekaligus: writer stok, model PO/production/payroll/POS/keuangan, laporan kas, dan laporan WhatsApp. Jangan menyalin satu file saja karena satu VOID dapat menyentuh stok sekaligus rekening.
3. Tidak perlu menjalankan repair data historis untuk fase ini. Fokusnya memastikan pembatalan baru tidak lagi masuk ke angka transaksi yang berlaku.

#### Uji operator

1. Buat adjustment bahan baku uji dengan kategori spoil atau waste, post, lalu VOID. Stok dan lot harus kembali; movement asal dan movement pembalik tetap terlihat di audit; ringkasan spoil/waste aktif harus kembali nol.
2. Buat batch component uji, post, lalu VOID. Output component dan seluruh input harus kembali; halaman laporan produksi tidak boleh lagi menghitung batch tersebut sebagai produksi yang berlaku.
3. Buat PO uji, bayar dari rekening, lalu VOID. Saldo rekening harus kembali. Daftar audit mutasi dapat menunjukkan asal dan pembatalannya, tetapi ringkasan belanja/keuangan/kas tidak boleh menghitung keduanya sebagai pengeluaran maupun pemasukan biasa.
4. Void pencairan gaji atau uang makan uji. Saldo rekening harus kembali dan laporan keuangan normal tidak boleh lagi memasukkan nominal itu sebagai biaya gaji.
5. Void kasbon uji yang belum mempunyai cicilan/pembayaran. Saldo rekening harus kembali. Kasbon yang sudah memiliki cicilan tetap harus ditolak, karena tidak aman dibatalkan tanpa proses koreksi tersendiri.
6. Buat DP POS uji lalu VOID DP tersebut. Saldo rekening harus kembali dan rekap kas POS tidak boleh menganggap DP itu sebagai penjualan. Uji refund POS terpisah; refund tetap harus tampil sebagai uang keluar yang nyata.

### SQL dan deploy yang perlu dilakukan

1. Jalankan `2026-08-13a_inventory_active_month_deficit_period_lock_foundation.sql` bila belum pernah dijalankan pada database target.
2. Jalankan `2026-08-18a_inventory_operator_control_menu_seed.sql` pada lokal dan server setelah deploy PHP. File ini hanya membuat halaman, menu, dan hak akses; tidak mengubah stok, lot, movement, atau saldo.
3. Jalankan `2026-08-18b_recalculate_stock_deficit_remaining_value.sql` pada lokal dan server. File ini hanya menyamakan nilai rupiah dengan sisa defisit; tidak menciptakan mutasi persediaan.
4. Deploy semua perubahan PHP sekaligus. Jangan hanya menyalin controller atau view baru, karena pengaman periode berada di library, controller, model, serta writer transaksi.
5. Setelah deploy, lakukan hard refresh browser (`Ctrl+F5`) dan pastikan hak akses role operator/admin sudah memuat menu `Kontrol Stok`.
6. Jalankan `2026-08-19d_inventory_adjustment_recon_settlement_contract.sql` pada lokal dan server. File ini hanya menambahkan satu kolom penanda pada draft adjustment component; tidak mengubah stok, lot, movement, atau defisit lama.
7. Jalankan `2026-08-19e_inventory_stock_value_reconciliation_foundation.sql` pada lokal dan server. File ini hanya membuat tabel dokumen Koreksi Nilai Stok dan rincian lotnya; tidak mengubah stok, lot, movement, HPP, atau data lama.
8. Jalankan `2026-08-19f_inventory_stock_value_reconciliation_menu_seed.sql` pada lokal dan server setelah SQL fondasi sebelumnya. File ini menambahkan halaman/menu `Koreksi Nilai Stok` serta hak akses baca. Posting tetap dibatasi oleh aplikasi untuk Superadmin.
9. Jalankan `2026-08-19b_inventory_stock_health_menu_seed.sql` pada lokal dan server. File ini mengembalikan menu `Kesehatan Stok Aktif` di bawah `Kontrol Stok` serta hak akses bacanya. Bila menu sudah ada, file aman dijalankan ulang.
10. Jalankan `2026-08-19g_retarget_nori_kitchen_deficit_to_actual_profile.sql` pada database yang memiliki kasus NORI yang sama. Periksa tiga baris guard sebelum lanjut: kandidat harus `12`, target profil Kitchen harus `1`, dan konflik kunci baru harus `0`. Bila salah satu berbeda, SQL tidak memperbarui data dan harus ditinjau dulu.
11. Jalankan `2026-08-19c_pos_provisional_hpp_deficit_cogs_foundation.sql` pada lokal dan server sebelum memakai koreksi HPP defisit. File ini hanya membuat tabel koreksi HPP dan pembalikan refund/VOID; tidak mengubah stok, lot, movement, defisit, atau penjualan.
12. Jalankan `2026-08-19h_pos_commit_deficit_reference_and_provisional_hpp.sql` pada lokal dan server setelah deploy PHP. File ini tidak mengubah qty stok, lot, movement, atau defisit. Ia hanya menambahkan label referensi defisit POS, sumber biaya `DEFICIT_PENDING`, dan memperbaiki HPP sementara **bulan aktif** yang sebelumnya nol.
13. Jalankan `2026-08-19i_backfill_active_pos_deficit_cogs_adjustments.sql` pada lokal dan server setelah SQL `2026-08-19c` dan `2026-08-19h`. File ini hanya merapikan catatan koreksi HPP untuk settlement POS **bulan aktif**; tidak mengubah jumlah stok, lot, movement, defisit, order, pembayaran, atau kas.
14. Jalankan `2026-08-20a_inventory_official_cutoff_run_audit.sql` pada lokal dan server sebelum menggunakan Posting Cut-off Resmi. File ini hanya membuat tabel audit run cut-off; tidak mengubah data persediaan atau keuangan.
15. Setelah deploy Fase 5A, jalankan audit CLI. Bila hasil menampilkan `POS_DEFICIT_HPP_SCHEMA` sebagai `ERROR`, jangan menganggapnya sebagai kerusakan stok. Ini berarti migration `2026-08-19h` belum dijalankan di database tersebut. Setelah itu jalankan `2026-08-19i` untuk merapikan koreksi HPP settlement bulan aktif.
16. Bila ingin melihat jumlah kandidat sebelum menjalankan repair, jalankan `2026-08-20b_preflight_active_pos_deficit_hpp_repair.sql`. File ini hanya membaca database. Hasil `RUN_2026_08_19H` berarti SQL `2026-08-19h` wajib dijalankan terlebih dahulu.
17. Jalankan `2026-08-20c_inventory_integrity_audit_menu_seed.sql` pada lokal dan server setelah deploy PHP Fase 5B. File ini hanya menambahkan menu, halaman, dan hak akses Audit Integritas Stok.
18. Jalankan `2026-08-21a_finance_sales_margin_pos_menu_alias.sql` pada lokal dan server untuk menampilkan shortcut laporan penjualan dan margin POS di rumpun Keuangan.
19. Jalankan `2026-08-21b_pos_sales_hpp_integrity_audit_menu_seed.sql` pada lokal dan server untuk menampilkan menu Audit Penjualan & HPP dan hak aksesnya.
20. Jalankan `2026-08-21c_pos_refund_cash_and_gross_amount_schema.sql` pada lokal dan server sebelum deploy pengaman refund Fase 6C.
21. Jalankan `2026-08-21e_pos_product_availability_queue.sql` pada lokal dan server sebelum deploy Fase 7A. Setelah itu pasang cron `php index.php pos availability_queue_run 100` setiap satu menit di Ubuntu.
22. Jalankan `2026-08-21f_pos_availability_queue_monitor_menu_seed.sql` pada lokal dan server setelah SQL antrean Fase 7A. File ini hanya membuat menu, halaman, dan hak akses monitor; tidak mengubah data persediaan atau transaksi.
23. Jalankan `2026-08-22a_inventory_void_reversal_movement_link_foundation.sql` pada lokal dan server sebelum deploy refactor VOID/reversal Fase 8A. File ini aman dijalankan ulang dan tidak mengubah data bisnis lama.

### Uji manual yang disarankan

1. Buka satu batch lama dan satu batch baru dari `Production > Component Batches`, lalu klik Pemakaian atau Trace. Pastikan detail terbuka tanpa modal kosong atau error non-JSON.
2. Buka `Inventory > Kontrol Stok > Defisit Stok`. Pastikan daftar dan rincian dapat dibaca, lalu buka tautan Adjustment dari satu defisit tanpa membuat perubahan apa pun.
3. Buka `Inventory > Kontrol Stok > Tutup Periode Stok`, buat periode uji bulan aktif untuk satu domain, dan pastikan statusnya `OPEN`.
4. Pada item uji, lakukan Adjustment dengan perubahan kecil yang diketahui. Pastikan Anda memasukkan selisih, misalnya `-2`, bukan stok fisik akhir.
5. Pada item uji lain, lakukan Daily Recon. Masukkan stok fisik akhir, misalnya `18` ketika stok sistem `20`. Pastikan hasil yang diposting adalah selisih `-2` dan lot ikut selaras.
6. Setelah data uji selesai, tutup periode uji. Coba simpan adjustment atau batch pada bulan tersebut. Hasil yang diharapkan adalah pesan periode tertutup, tanpa stok/lot/movement baru.
7. Buka kembali periode dengan alasan, lalu ulangi satu transaksi uji. Setelah berhasil, tutup kembali periode bila pengujian sudah selesai.
8. Uji satu component dengan stok sistem lebih kecil daripada hasil fisik dan defisit terbuka yang identitasnya sama. Posting dari Daily Recon. Pastikan stok/lot mengikuti angka fisik akhir dan defisit ikut turun tanpa membuat stok menjadi nol.
9. Ulangi dengan kondisi yang sama tetapi jangan konfirmasi penyelesaian defisit pada form Defisit Stok. Pastikan stok/lot tetap berubah sesuai hasil fisik, sedangkan defisit tetap terbuka.
10. Buka `Inventory > Kontrol Stok > Kesehatan Stok Aktif` dengan jumlah baris cukup banyak, lalu gulir **di dalam tabel**. Judul kolom harus tetap terlihat di bagian atas tabel.
11. Cari `JERUK NIPIS` pada Agustus 2026. Profil aktif yang sesuai tidak lagi boleh muncul sebagai temuan hanya karena ada lot lama berstatus `CLOSED`. Bila masih muncul, lakukan hard refresh (`Ctrl+F5`) untuk memastikan file PHP/CSS/JS baru sudah termuat.
12. Untuk temuan **Nilai berbeda**, pastikan jumlah stok dan lot memang sudah diperiksa lebih dahulu. Jangan menjalankan recon fisik hanya untuk menghilangkan angka rupiah; catat dokumen sumber biaya yang benar untuk dipakai pada Koreksi Nilai/HPP terkontrol.
13. Dari Stock Health, buka satu temuan gudang dengan jumlah berbeda. Pastikan tombol Rekonsiliasi membuka `Recon Stok Fisik Gudang`, lalu gunakan tombol `Gunakan stok sistem` tanpa menyimpan untuk memastikan profil dan nilai referensi terbaca benar.
14. Dari Stock Health, buka satu temuan **Nilai berbeda** yang jumlahnya sudah sama. Pastikan halaman `Koreksi Nilai Stok` menampilkan hanya lot `OPEN`, angka jumlah yang sama, dan menolak tombol posting bila bulan bukan bulan aktif atau jumlah belum sama.
15. Buka `Koreksi Nilai Stok` langsung dari sidebar. Pastikan antrean temuan nilai aktif muncul di atas riwayat, bukan halaman kosong. Pilih satu temuan, lalu batalkan tanpa menyimpan untuk memeriksa bahwa jumlah stok dan lot tidak berubah.
16. Buka rincian defisit NORI Kitchen setelah menjalankan SQL `2026-08-19g`. Pastikan stok sistem serta lot aktif terbaca `150 GR` pada profil Kitchen yang benar, sedangkan total defisit tetap `17,03 GR` sampai operator mengonfirmasi hitung fisik.
17. Uji satu temuan gudang dengan stok sistem sama dengan angka fisik, tetapi total lot `OPEN` berbeda. Contoh: stok `76.000 ML`, lot `190.000 ML`, lalu masukkan fisik `76.000`. Setelah simpan, stok bulanan harus tetap `76.000 ML`, lot `OPEN` harus menjadi `76.000 ML`, dan satu jejak audit koreksi lot baru harus tercatat.
18. Setelah memposting Koreksi Nilai Stok, buka kembali barang yang sama. Hasil yang diharapkan adalah pesan hijau `Nilai stok dan lot sudah selaras`, bukan pesan bahwa posting gagal.
19. Setelah menjalankan SQL `2026-08-19h`, jalankan audit CLI Fase 3A. Hasil yang diharapkan: `POS_COMMIT_LINE_TRACE` dan `POS_FULL_DEFICIT_PROVISIONAL_HPP` keduanya `PASS`. Temuan Stock Health boleh tetap menjadi `WARNING` sampai diperiksa satu per satu.
20. Buat satu transaksi POS uji dengan bahan/component yang lotnya sengaja kurang. Setelah job background selesai, pastikan POS berhasil, lot tidak negatif, ada defisit pada identitas yang sama, dan rincian stock commit menampilkan HPP lebih dari nol untuk seluruh kebutuhan resep.
21. Ulangi dengan lot yang hanya cukup sebagian. Pastikan HPP transaksi terdiri dari biaya bagian lot yang keluar ditambah biaya sementara bagian defisit; total HPP tidak hanya menghitung bagian lot yang tersedia.
22. Terima barang atau lakukan adjustment plus yang sah untuk identitas defisit uji. Pastikan defisit berkurang, stok/lot baru bertambah penuh, dan transaksi POS asal tetap dapat dilacak tanpa angka HPP nol.
23. Lakukan void/refund pada transaksi POS uji. Pastikan defisit berkurang hanya sesuai qty yang diretur; lot hanya kembali untuk bagian yang memang pernah keluar dari lot.
24. Setelah SQL `2026-08-19i` dijalankan, ulangi audit CLI. Hasil yang diharapkan: `POS_DEFICIT_SETTLEMENT_COGS`, `POS_DEFICIT_SETTLEMENT_ZERO_COST`, dan `DEFICIT_COGS_ARITHMETIC` menjadi `PASS`. Bila ada baris yang telah memiliki pembalikan refund/VOID, jangan dipaksa melalui SQL; buka detail sumbernya untuk review manual.
25. Buat satu POS uji yang menghasilkan defisit, lalu selesaikan dengan receipt/adjustment plus yang memiliki biaya. Pastikan HPP POS awal tetap seperti saat transaksi, sedangkan laporan HPP Live mendapatkan selisih biaya baru bila biaya receipt berbeda.
26. Refund sebagian transaksi uji setelah defisit sudah selesai. Pastikan jumlah koreksi HPP berkurang proporsional dan lot tidak bertambah dari bagian transaksi yang awalnya tidak pernah keluar dari lot.
27. Jalankan SQL `2026-08-20a_inventory_official_cutoff_run_audit.sql`, lalu buka satu periode **bulan yang sudah selesai** tetapi bulan berikutnya belum memiliki mutasi pada environment uji.
28. Buka rinciannya. Pastikan bagian **Posting Cut-off Resmi** menampilkan simulasi, catatan yang perlu dibaca, dan form konfirmasi hanya untuk Superadmin.
29. Jalankan CLI preflight untuk domain yang sama. Pastikan perintah hanya membaca data dan tidak membuat opening, lot, movement, maupun period baru.
30. Pada data uji yang memang aman, isi catatan, centang catatan bila muncul, ketik `POST CUT-OFF`, lalu posting. Pastikan run tercatat `POSTED`, opname dan opening terbentuk, lalu periode menjadi `CLOSED`.
31. Coba simpan adjustment atau batch dengan tanggal pada bulan yang baru ditutup. Sistem harus menolak transaksi tersebut tanpa membuat movement atau lot baru.
32. Uji kondisi terlambat: pilih bulan yang bulan berikutnya sudah memiliki mutasi. Hasil yang diharapkan adalah tombol posting ditolak dengan penjelasan bahwa opening tidak boleh dibentuk di atas bulan yang sudah berjalan. Tidak ada data yang boleh berubah.
33. Setelah Fase 8A dipasang, post lalu VOID satu adjustment spoil uji. Pastikan stok/lot pulih, histori movement tetap ada, dan jumlah spoil aktif tidak bertambah.
34. Post lalu VOID satu batch component uji. Pastikan output/input tidak lagi dihitung sebagai aktivitas produksi pada laporan normal.
35. Bayar lalu VOID satu PO uji. Pastikan saldo rekening pulih dan laporan keuangan normal tidak menghitung pembayaran maupun pengembaliannya sebagai arus kas operasional.
36. Uji refund POS secara terpisah dari VOID DP. Refund harus tetap terlihat sebagai uang keluar karena memang terjadi, sedangkan VOID DP tidak boleh masuk penjualan atau kas normal.

### Verifikasi teknis tahap ini

1. Lint PHP berhasil untuk controller, model, library, dan empat halaman inventory yang terdampak.
2. Pemeriksaan database lokal memastikan tabel period, defisit, settlement, dan cutoff event tersedia. Tidak ada data bisnis baru yang dibuat untuk pengujian ini.
3. Akses HTTP tanpa login ke halaman baru dan detail batch diuji; semua diarahkan ke halaman login sebagaimana mestinya.
4. Pengujian klik dengan akun operator tetap perlu dilakukan manual karena verifikasi ini tidak memakai sesi login pengguna.
5. Pengujian fungsi writer material secara terisolasi mencakup pembatalan spoil, waste, receipt/pembelian, serta adjustment spoil. Masing-masing membatalkan bucket bulanan asalnya dan tidak berubah menjadi transaksi keluar biasa.
6. Pengujian jalur receipt normal secara terisolasi memastikan update stok bulanan tetap berhasil setelah tambahan metadata reversal, tanpa membutuhkan transaksi VOID.

## Referensi Kode dan Dokumen

1. [InventoryLedger.php](/C:/xampp/htdocs/finance/application/libraries/InventoryLedger.php) - writer material monthly/movement dan izin negative balance.
2. [MaterialFifoManager.php](/C:/xampp/htdocs/finance/application/libraries/MaterialFifoManager.php) - FIFO material dan larangan lot negatif.
3. [ComponentLotManager.php](/C:/xampp/htdocs/finance/application/libraries/ComponentLotManager.php) - lot component dan larangan lot negatif.
4. [Purchase_model.php](/C:/xampp/htdocs/finance/application/models/Purchase_model.php) - adjustment material serta void/rebuild.
5. [Production_model.php](/C:/xampp/htdocs/finance/application/models/Production_model.php) - adjustment, batch, reconcile, dan void component.
6. [Pos_model.php](/C:/xampp/htdocs/finance/application/models/Pos_model.php) - POS commit, void, dan refund.
7. [PosAvailabilityRebuildService.php](/C:/xampp/htdocs/finance/application/libraries/PosAvailabilityRebuildService.php) - cache ketersediaan POS.
8. [Audit legacy daily rollup](/C:/xampp/htdocs/finance/docs/2026-06-07b_legacy_daily_rollup_stock_balance_audit.md) - status deprecate tabel legacy.
