# Audit Ulang Anomali Penjualan dan Operasional Global

## Status Audit

Tanggal pemeriksaan: 26 Agustus 2026.

Audit ini bersifat baca-saja. Tidak ada order, refund, stok, lot, defisit,
HPP, kas, jurnal, atau data master yang diubah selama pemeriksaan.

Tujuan audit ini adalah membedakan tiga hal yang mudah tercampur:

1. Bug proses yang masih dapat terjadi pada transaksi baru.
2. Pekerjaan operasional yang belum dijalankan, misalnya antrean latar
   belakang yang berhenti.
3. Sisa data atau nilai historis yang masih ikut terbaca pada bulan aktif.

Sistem memakai pola cut-off bulanan. Karena itu data lama tidak boleh
"dibersihkan" secara massal hanya demi membuat dashboard hijau. Yang perlu
diselesaikan adalah residu data lama yang masih ikut mempengaruhi bulan aktif,
serta jalur transaksi baru yang masih berpotensi menambah masalah.

## Kesimpulan Singkat

Fondasi baru untuk stok defisit, jejak writer stok, dan HPP sementara sudah
bekerja pada jalur utama. Audit aktif tidak menemukan saldo bulanan negatif,
dan jejak writer untuk adjustment, batch component, transfer, serta sebagian
besar commit POS dinyatakan lengkap.

Namun pekerjaan belum sepenuhnya selesai. Ada lima kelompok yang masih perlu
ditangani:

| Prioritas | Temuan | Arti praktis |
| --- | --- | --- |
| P0 | Job stok POS yang sudah dibatalkan masih antre | Void/refund dapat dicoba lagi oleh worker bila tidak dihentikan tegas. |
| P1 | Antrean ketersediaan produk POS tidak berjalan | Tampilan ketersediaan POS/self-order dapat tertinggal dari stok sebenarnya. |
| P1 | Self-order yang sudah dibayar masih menunggu verifikasi | Uang sudah masuk, tetapi stok dan HPP belum benar-benar diproses. |
| P1 | 47 temuan Kesehatan Stok Aktif | Mayoritas adalah nilai/lot historis yang masih terbawa ke bulan aktif, bukan bukti bahwa transaksi baru semuanya salah. |
| P1 | Dua defisit baru tidak membawa biaya yang sebenarnya sudah diketahui | Jumlah defisit benar, tetapi estimasi nilai uang defisit di dashboard menjadi terlalu kecil. |

Ada pula tujuh histori pembayaran final ganda dari Juni-Juli. Pemeriksaan jalur
kasir sekarang menunjukkan proses baru sudah mengunci order dan menolak order
yang sudah lunas, sehingga temuan tersebut tidak perlu dihapus atau diubah
secara tergesa-gesa. Tetap perlu perlindungan idempotensi tambahan untuk jalur
integrasi atau retry di masa depan.

## Hasil Yang Sudah Sehat

Pemeriksaan integritas bulan aktif Agustus menghasilkan 54 isu: 47 warning dan
7 error. Setelah ditelusuri, dua kelompok error tersebut adalah alarm audit yang
belum memahami status terminal, bukan bukti stok baru salah tulis.

Pemeriksaan berikut lulus:

- Struktur migration utama untuk stok, defisit, dan HPP defisit tersedia.
- Tidak ada saldo bulanan aktif yang bernilai minus.
- Jejak adjustment bahan baku yang sudah diposting tersedia.
- Jejak adjustment component yang sudah diposting tersedia.
- Jejak batch component yang sudah diposting tersedia.
- Jejak transfer yang sudah diposting tersedia.
- Pemeriksaan HPP sementara untuk defisit penuh dan sebagian lulus.
- Pemeriksaan label referensi HPP defisit lulus.
- Pemeriksaan aritmatika koreksi HPP defisit lulus.
- Pemeriksaan penautan refund header dan baris refund saat ini lulus.

Artinya, pola baru sudah lebih aman dibanding sebelum ada stok defisit: stok
tidak lagi harus dipaksa menjadi negatif saat lot tidak cukup, dan writer utama
sudah memiliki jejak yang dapat diaudit.

## Temuan Aktif

### P0 - Job POS yang Sudah Direversal Masih Bisa Diambil Worker

Ditemukan dua job QUEUED yang sebenarnya sudah tidak boleh diproses lagi:

| Job | Nota | Status order | Status commit stok |
| --- | --- | --- | --- |
| 4363 | POS-20260824-0009 | VOID | REVERSED |
| 4364 | POS-20260824-0010 | REFUND_FULL | REVERSED |

Ketika void atau refund penuh dilakukan, stok memang sudah direversal. Namun
job lama di antrean runtime belum otomatis diubah menjadi CANCELLED.

Risikonya bukan berarti stok pasti dipotong dua kali hari ini. Refresh commit
masih dapat menolak order void/refund. Masalahnya adalah worker masih sempat
mengambil job tersebut, mengubah status commit menjadi PROCESSING atau FAILED,
dan membuat keadaan transaksi membingungkan. Pada kondisi tertentu ini dapat
membuka peluang proses ulang yang tidak seharusnya.

Perbaikan yang diperlukan:

1. Saat void/refund mereversal commit, batalkan semua job aktif untuk order dan
   commit yang sama di dalam transaksi database yang sama.
2. Sebelum worker mengambil job, cek kembali: order VOID atau REFUND_FULL,
   serta commit REVERSED atau VOID, wajib langsung dibatalkan.
3. Kunci aturan status: commit terminal tidak boleh kembali ke QUEUED,
   PROCESSING, COMMITTED, atau FAILED.
4. Setelah perbaikan aplikasi siap, dua job lama di atas dapat ditandai
   CANCELLED dengan skrip yang sangat kecil dan terkontrol. Tidak perlu
   mengubah stoknya lagi.

### P1 - Antrean Ketersediaan Produk POS Berhenti

Tabel antrean ketersediaan menunjukkan:

| Status | Jumlah | Job paling lama |
| --- | ---: | --- |
| QUEUED | 99 | 21 Agustus 2026 20:31 |
| SUCCESS | 136 | terakhir selesai 24 Agustus 2026 09:06 |

Semua job QUEUED belum pernah dicoba. Ini menunjukkan worker/cron
availability_queue_run tidak sedang berjalan, bukan karena stok langsung
gagal tersimpan.

Dampaknya:

- POS stock-live dan penanda tersedia/habis dapat terlambat memperbarui.
- Self-order atau order online dapat melihat ketersediaan lama.
- Ledger stok, lot, dan defisit tetap memakai writer transaksi; antrean ini
  terutama mempengaruhi cache/availability produk.

Tindakan yang diperlukan:

1. Pastikan cron aktif dan berjalan tiap menit pada server:

    * * * * * /usr/bin/php /www/wwwroot/finance/index.php pos availability_queue_run 100

2. Pastikan user cron punya akses ke PHP dan folder project.
3. Pantau umur job tertua, bukan hanya jumlah job. Antrean yang beberapa menit
   normal dapat diterima; antrean berhari-hari harus ditandai merah.
4. Tambahkan tindakan aman di halaman monitor: jalankan sekali dan lihat hasil,
   bukan tombol yang memproses tanpa batas.

### P1 - Self-Order Sudah Dibayar tetapi Belum Masuk Proses Stok

Terdapat satu order self-order:

    MSO-20260822114341-AA80
    Status order: PAID
    Status commit stok: PENDING
    Nilai: Rp19.000
    Dibayar: 22 Agustus 2026 11:46

Pola saat ini memang meminta verifikasi kasir sebelum stok/HPP diproses. Ini
dapat menjadi kebijakan yang benar, misalnya agar makanan tidak otomatis masuk
ke dapur sebelum pembayaran QRIS benar-benar tervalidasi. Akan tetapi, sistem
belum memberi alarm yang cukup jelas untuk order yang sudah dibayar tetapi
terlalu lama belum diverifikasi.

Perbaikan yang diperlukan:

1. Tampilkan kartu atau notifikasi "Self-order sudah dibayar, menunggu
   verifikasi" untuk umur lebih dari lima menit.
2. Beri kasir/manager tombol verifikasi yang jelas dan berjejak.
3. Jangan mengubah menjadi auto-confirm tanpa keputusan bisnis khusus, karena
   itu mengubah alur produksi dan risiko pembayaran.

### P1 - Kesehatan Stok Aktif Masih Memiliki 47 Temuan

Distribusi temuan Agustus:

| Domain | Jenis temuan | Jumlah | Arti |
| --- | --- | ---: | --- |
| Bahan baku | Jumlah dan nilai berbeda | 7 | Saldo bulanan tidak sama dengan total lot aktif. |
| Bahan baku | Nilai berbeda | 2 | Jumlah sama, nilai uang stok dan lot berbeda. |
| Component | Nilai berbeda | 38 | Jumlah sama, tetapi nilai bulanan dan nilai lot berbeda. |

Tidak ada mismatch jumlah component pada hasil ini. Ini sinyal baik: jumlah
fisik/sistem component dan lot aktif sudah bergerak bersama. Masalah terbesar
component berada pada nilai uang historisnya.

#### Contoh bahan baku: lot pembuka lama masih terbuka

Pada SIRUP STRAWBERRY gudang, stok bulanan aktif berjumlah 4.340 ML dengan
nilai Rp175.000. Namun lot aktif total menunjukkan 9.300 ML dengan nilai sekitar
Rp375.000. Ada beberapa lot OPEN dengan identitas profil yang sama:

- lot terbaru sebesar 4.340 ML, yang nilainya sama dengan stok bulanan aktif;
- dua lot pembuka lama sebesar 3.720 ML dan 1.240 ML yang masih terbuka.

Kesimpulannya bukan "SR hari ini pasti menggandakan stok". Yang terlihat adalah
lot pembuka/cut-off lama masih ikut terbaca bersama lot aktif terbaru. Bila
dilakukan recon fisik secara langsung tanpa klasifikasi, stok fisik yang benar
justru bisa berubah salah.

#### Contoh component: rebuild membawa nilai defisit sejarah

Pada CHICKEN CUBE 40 Kitchen, jumlah stok bulanan dan lot aktif sama-sama 33.
Namun nilai bulanan negatif sekitar Rp2,7 juta, sedangkan lot aktif bernilai
sekitar Rp66 ribu. Rebuild component sedang memutar ulang sejarah pemakaian
lama, termasuk urutan lama ketika pemakaian melebihi nilai yang tersedia.

Fondasi defisit baru mencegah transaksi baru memaksa lot menjadi minus. Namun
rebuild historis belum membedakan kekurangan lama dengan stok bernilai negatif,
sehingga nilai negatif masa lalu dapat muncul kembali saat rebuild dijalankan.

#### Cara penyelesaian yang aman

Jangan memakai adjustment atau recon fisik massal untuk membuat halaman
Kesehatan Stok kosong. Gunakan urutan berikut:

1. Buat laporan dry-run per identitas: barang, area, profile, lot, sumber lot,
   tanggal pembuka, jumlah, dan nilai.
2. Klasifikasikan tiap temuan menjadi:
   - lot pembuka/cut-off lama yang harus ditutup dari perhitungan aktif;
   - nilai lama yang perlu koreksi nilai saja;
   - jumlah fisik memang berbeda dan perlu recon stok fisik;
   - transaksi baru yang benar-benar salah tulis.
3. Untuk selisih nilai saja, gunakan Koreksi Nilai Stok/Lot agar jumlah tidak
   berubah dan ada jejak alasan.
4. Untuk jumlah fisik berbeda, gunakan recon stok fisik sesuai profile dan
   lokasi yang benar.
5. Ubah rebuild component agar tidak mengubah kekurangan historis menjadi
   nilai stok negatif bulan aktif. Kekurangan lama harus ditandai sebagai
   pembukaan defisit/penyesuaian migrasi yang berjejak, atau rebuild harus
   memberi preview sebelum posting.

### P1 - Defisit Baru dari Adjustment Kehilangan Nilai Biaya

Saat ini dashboard memang masih menampilkan defisit aktif. Itu benar dan
diinginkan: dashboard membaca inv_stock_deficit dengan status OPEN dan jumlah
tersisa lebih dari nol, bukan membaca stok minus historis.

Ada dua defisit bahan baku aktif dari adjustment nomor 2775:

| Barang | Defisit | Biaya pada baris adjustment | Biaya pada defisit |
| --- | ---: | ---: | ---: |
| ICE CUBE | 150 | Rp1,375 per unit | Rp0 |
| CARAMEL CRUMB | 5 | Rp98 per unit | Rp0 |

Jumlah defisitnya benar. Masalahnya, writer defisit membuat estimasi biaya nol
walaupun adjustment sumber sudah mempunyai unit_cost. Akibatnya nilai risiko
di dashboard menjadi terlalu kecil.

Perbaikan yang diperlukan:

1. Writer adjustment harus meneruskan unit_cost ke defisit saat pengeluaran
   melampaui lot.
2. Nilai defisit lama yang nol boleh diperbaiki dari data sumber adjustment,
   bukan dengan mengubah jumlah stok atau lot.
3. Laporan defisit perlu membedakan "nilai belum diketahui" dan "nilai nol"
   agar operator tidak mengira biaya sebenarnya nol.

### P2 - Dua Alarm Audit Memerlukan Perbaikan Rumus

Tujuh error dari command audit bukan semuanya masalah data baru.

#### POS_COMMIT_LINE_TRACE (2 baris)

Dua commit REVERSED tidak mempunyai committed_at dan tidak punya movement line.
Itu masuk akal karena order dibatalkan sebelum stok pernah diposting. Audit saat
ini tetap menuntut jejak movement untuk commit yang sudah terminal.

Perbaikan: audit hanya wajib meminta jejak movement untuk commit yang benar-
benar sudah diposting. Commit yang dibatalkan sebelum posting cukup dilaporkan
sebagai informasi, bukan error.

#### ACTIVE_DEFICIT_ARITHMETIC (5 baris)

Lima defisit sudah berstatus WRITTEN_OFF dan saldo tersisa nol. Rumus audit
masih menghitung saldo hanya dari requested, issued, settled, dan reversed;
rumus belum mengurangi written_off_qty dan belum mengenali WRITTEN_OFF sebagai
status terminal.

Perbaikan: tambahkan written_off_qty pada aritmatika dan perlakukan
WRITTEN_OFF seperti status selesai bila saldonya nol.

### P2 - Pembayaran Final Ganda Lama

Ditemukan tujuh nota Juni-Juli yang memiliki lebih dari satu pembayaran FINAL
berstatus PAID. Contoh: nota bernilai Rp20.500 memiliki dua payment final sebesar
total Rp41.000. Header order tetap menunjukkan pembayaran yang benar, tetapi
laporan yang menjumlahkan tabel payment secara langsung dapat terlihat lebih
besar.

Temuan ini kemungkinan berkaitan dengan retry atau kondisi server lambat pada
masa lalu. Data lama tidak disarankan untuk dihapus langsung karena dapat sudah
terkait kas, shift, loyalty, atau refund.

Jalur kasir saat ini sudah memiliki perlindungan penting:

- order dibaca dengan FOR UPDATE sebelum pembayaran dibuat;
- order yang sudah PAID tidak termasuk status yang boleh diproses ulang;
- pembayaran dan update order berada di dalam satu transaksi database.

Masih disarankan menambah idempotency key untuk request pembayaran, terutama
untuk aplikasi, retry jaringan, atau integrasi pembayaran lain. Ini adalah
lapisan pengaman tambahan, bukan alasan untuk melakukan penghapusan data lama.

### P2 - Hygiene Hak Akses dan Pengujian Otomatis

Halaman Kesehatan Stok pada controller saat ini bersifat baca-saja. Meski
demikian, matrix permission menyimpan beberapa flag create/edit/delete yang
lebih lebar dari fungsi halaman sebenarnya. Ini belum terbukti menjadi bypass,
namun sebaiknya dirapikan agar peran tidak tampak memiliki hak yang sebenarnya
tidak digunakan.

Selain itu belum ditemukan suite test otomatis untuk transaksi inti. Karena
writer POS, void, refund, lot, component batch, adjustment, dan queue saling
terhubung, regression test minimum sangat penting sebelum perubahan besar.

## Apakah Defisit Masih Tampil di Dashboard?

Ya, dan ini benar.

Dashboard Defisit Stok Aktif membaca tabel inv_stock_deficit yang masih OPEN dan
memiliki qty_remaining lebih dari nol. Dashboard tidak memakai saldo stok minus
historis sebagai indikator defisit.

Pada saat audit terdapat dua defisit bahan baku aktif, total 155 unit. Nilai
estimasi masih Rp0 hanya karena bug penerusan biaya adjustment yang dijelaskan
di atas. Setelah biaya diperbaiki, dashboard tetap akan menampilkan defisit
sampai ada penerimaan, adjustment plus, batch, atau penyelesaian administrasi
yang sah untuk identitas yang sama.

## Apakah Server Normal Masih Bisa Menghasilkan Mismatch?

Server normal sangat mengurangi risiko, tetapi bukan jaminan mutlak. Mismatch
masih dapat muncul bila salah satu kondisi berikut terjadi:

1. Worker antrean berhenti, sehingga data turunan seperti availability tertinggal.
2. Void/refund meninggalkan job lama yang masih dapat diproses ulang.
3. Rebuild memutar ulang sejarah lama tanpa aturan migrasi kekurangan historis.
4. Data pembuka/cut-off lama masih berstatus lot aktif bersamaan dengan lot baru.
5. Pengguna melakukan adjustment/recon pada identitas profile yang keliru.
6. Request pembayaran/integrasi dikirim ulang tanpa idempotency key.

Jadi, masalah yang terlihat sekarang tampaknya gabungan antara sisa perbaikan
data masa lalu, kondisi server/worker yang sempat terganggu, dan beberapa guard
proses yang belum selesai. Bukan bukti bahwa skema defisit baru gagal total.

## Rencana Perbaikan yang Direkomendasikan

### Fase A - Tutup Celah Proses POS dan Queue

Prioritas pertama karena mencegah masalah baru.

1. Tambahkan pembatalan job runtime saat void/refund.
2. Tambahkan guard terminal pada worker sebelum claim dan sebelum process.
3. Buat skrip kecil untuk membatalkan job lama yang sudah pasti terminal.
4. Aktifkan dan verifikasi cron availability queue.
5. Tambahkan indikator umur antrean dan alert jika melebihi batas.

Pengujian manual:

1. Buat order, lalu void sebelum job stok selesai.
2. Jalankan worker dan pastikan job berubah CANCELLED, bukan PROCESSING.
3. Pastikan stok tidak berubah kedua kali.
4. Ubah ketersediaan stok lalu jalankan availability queue; cek POS/self-order
   ikut berubah.

### Fase B - Perbaiki Kejujuran Audit

**Status 26 Agustus 2026: selesai di kode lokal.** Rincian perubahan dan hasil
uji dicatat pada `2026-08-26_fase_b_audit_jujur_terminal_dan_writeoff.md`.

1. Sesuaikan POS_COMMIT_LINE_TRACE untuk commit yang dibatalkan sebelum posting.
2. Sesuaikan aritmatika defisit untuk WRITTEN_OFF.
3. Pisahkan hasil audit menjadi Error, Perlu Tindakan, dan Informasi Terminal
   agar operator tidak mengejar alarm yang tidak perlu.

Pengujian manual:

1. Jalankan audit bulan aktif.
2. Pastikan commit REVERSED tanpa movement tidak lagi merah.
3. Pastikan defisit WRITTEN_OFF saldo nol tidak lagi merah.

### Fase C - Selesaikan Nilai Defisit dan Self-Order Tertunda

1. Teruskan biaya adjustment ke record defisit.
2. Backfill nilai dua defisit aktif hanya dari baris adjustment sumbernya.
3. Tampilkan peringatan self-order sudah dibayar tetapi belum diverifikasi.
4. Tetapkan SLA verifikasi dan pemilik antrean kasir.

Pengujian manual:

1. Lakukan adjustment negatif yang melampaui lot dengan biaya katalog.
2. Pastikan defisit menerima jumlah dan nilai estimasi yang sama.
3. Buat self-order bayar; cek bahwa kasir mendapat daftar tunggu verifikasi.
4. Verifikasi dan pastikan stock commit serta HPP terbentuk satu kali.

### Fase D - Pemulihan Terkendali Kesehatan Stok Aktif

Ini harus dilakukan setelah Fase A-C, bukan sekaligus.

1. Buat laporan dry-run lot pembuka/lot duplikat per identitas.
2. Klasifikasikan 47 temuan satu per satu menurut jenis penyebab.
3. Tutup atau normalisasi lot pembuka lama hanya setelah sumbernya dipastikan.
4. Gunakan Koreksi Nilai Stok/Lot untuk selisih nilai saja.
5. Gunakan Recon Stok Fisik hanya bila hasil hitungan fisik memang berbeda.
6. Ubah rebuild component agar kekurangan historis tidak kembali menjadi nilai
   negatif di bulan aktif.

Pengujian manual:

1. Ambil satu bahan baku contoh yang memiliki lot pembuka lama dan lot aktif.
2. Cetak before/after jumlah, nilai, dan sumber lot.
3. Pastikan jumlah fisik tidak berubah ketika hanya memperbaiki nilai.
4. Jalankan rebuild pada satu component contoh dan pastikan nilai negatif lama
   tidak dimunculkan lagi sebagai stok aktif.

### Fase E - Pengaman Jangka Panjang

1. Tambahkan idempotency key pembayaran POS dan integrasi pembayaran.
2. Rapikan hak akses baca-saja untuk halaman audit/health.
3. Tambahkan test otomatis minimum untuk order, pembayaran, void, refund,
   adjustment, batch, stock deficit, runtime job, dan availability queue.
4. Jadikan smoke test ini bagian wajib sebelum deploy.

## Aturan Aman Saat Menangani Temuan

- Jangan menghapus payment, refund, lot, movement, atau defisit secara langsung
  dari database.
- Jangan melakukan recon fisik hanya agar dashboard tidak merah.
- Jangan menutup lot lama tanpa mengetahui apakah lot tersebut pembuka bulan,
  lot hasil migrasi, atau barang fisik yang masih benar-benar ada.
- Jangan menjalankan rebuild component massal sebelum Fase D memiliki preview
  dan aturan kekurangan historis yang jelas.
- Untuk data bulan tertutup yang tidak mempengaruhi bulan aktif, simpan sebagai
  arsip kecuali ada kebutuhan laporan atau audit resmi.

## Urutan Implementasi yang Disarankan

Urutan paling aman adalah Fase A, B, C, D, lalu E. Fase A mencegah data baru
bertambah kacau; Fase B membuat dashboard jujur; Fase C memperbaiki informasi
operasional aktif; Fase D membersihkan residu lama dengan terukur; Fase E
menjaga perbaikan tetap bertahan pada perubahan berikutnya.
