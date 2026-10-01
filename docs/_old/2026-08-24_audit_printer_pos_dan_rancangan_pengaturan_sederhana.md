# Audit Printer POS dan Rancangan Pengaturan Sederhana

Tanggal audit: 24 Agustus 2026  
Lingkup: halaman `/pos/printers` beserta seluruh halaman turunannya, jalur cetak dari POS, agent printer lokal, dan tabel konfigurasi printer pada database lokal.

## Ringkasan untuk operasional

Modul printer saat ini **sudah dapat mengirim cetak langsung ke agent lokal**, tetapi pengaturannya belum mempunyai satu sumber kebenaran. Akibatnya, halaman yang dilihat admin dapat menyebut satu template, sementara mesin cetak memakai template lain secara otomatis. Ada juga pilihan koneksi yang terlihat tersedia tetapi belum benar-benar dipakai oleh mesin cetak.

Prinsip yang perlu kita jadikan tujuan akhir:

1. Satu halaman khusus untuk koneksi fisik printer per outlet dan lokasi kerja.
2. Satu halaman khusus untuk tampilan umum cetak.
3. Satu halaman khusus untuk aturan: dokumen apa, dari lokasi mana, dicetak ke printer mana, dengan tampilan apa.
4. Satu halaman preview berdasarkan transaksi nyata dan satu halaman monitor cetak.
5. Satu panduan yang sesuai dengan form dan cara kerja yang benar-benar aktif.

Tidak disarankan merombak langsung data printer yang berjalan. Tahap pertama harus memetakan konfigurasi lama ke pola baru, lalu menguji per printer sebelum pola lama disembunyikan.

## Kondisi yang ditemukan saat ini

### Halaman yang ada

| Halaman | Fungsi yang tampak di UI | Catatan audit |
| --- | --- | --- |
| `/pos/printers` | Workspace berisi Template Dokumen, Pengaturan Output, dan Device Desktop. | Mengulang isi tiga halaman lain sehingga pengguna melihat data yang sama di banyak tempat. |
| `/pos/printers/templates` | Membuat dan mengubah template dokumen. | Ini seharusnya menjadi halaman Layout Cetak. Fungsinya valid, tetapi istilah dokumen masih teknis. |
| `/pos/printers/profiles` | Mengatur kertas, copy, logo, harga, footer. | Nama "profile" membingungkan; sebagian nilainya tidak dipakai oleh jalur cetak aktif. |
| `/pos/printers/devices` | Mengatur printer fisik, agent, MAC, port, dan test print. | Ini seharusnya menjadi halaman Koneksi Printer. Saat ini masih bercampur dengan role dan scope cetak. |
| `/pos/printers/settings` | Nama outlet, logo, Wi-Fi, loyalty, header/footer. | Konsepnya tepat sebagai Pengaturan Umum Cetak, tetapi datanya disimpan di tabel template master. |
| `/pos/printers/preview/:id` | Melihat dan mengetes simulasi cetak. | Preview memakai contoh data dan template yang dipilih manual; bukan hasil aturan cetak nyata. |
| `/pos/printers/guide` | Panduan pemasangan agent. | Ada istilah dan kolom yang sudah tidak sama dengan form aktif. |

Selain halaman di atas, cetak dipanggil dari kasir, pembayaran order, order online, self order, mobile POS, void, refund, reprint, dan tutup shift.

### Tabel dan pola yang ada

| Kelompok | Tabel | Keadaan saat ini |
| --- | --- | --- |
| Printer fisik | `pos_printer` | Dipakai aktif. Namun sekaligus menyimpan role dan scope cetak, padahal seharusnya hanya koneksi fisik. |
| Output printer | `pos_printer_profile` | Dipakai aktif untuk lebar kertas, karakter per baris, copy, dan template default. Satu printer sebenarnya diperlakukan sebagai satu profile. |
| Isi output | `pos_printer_content_setting` | Menyimpan logo/harga/footer, tetapi jalur cetak langsung tidak menjadikannya sumber tampilan akhir. |
| Template/layout | `pos_printer_template` | Dipakai aktif melalui `template_payload`; ini sumber layout yang paling nyata. |
| Template master dan umum | `pos_printer_template_master` | Dipakai untuk dua hal yang tidak sama: master template dan `POS-GLOBAL` untuk branding/Wi-Fi/loyalty. |
| Event cetak | `pos_printer_event_setting` | Dipakai untuk aktif/nonaktif otomatis dan batas reprint, tetapi istilah jenis dokumen berbeda dengan tabel template. |
| Routing lama | `pos_printer_route_rule` | Ada struktur tabel, tetapi kosong dan tidak dipakai oleh cetak aktif. |
| Device desktop lama | `pos_printer_desktop_device` | Ada struktur tabel, tetapi kosong dan tidak dipakai oleh cetak aktif. |
| Antrean/job lama | `pos_printer_job`, `pos_printer_job_log` | Ada struktur dan function pembuat job, tetapi jalur POS aktif tidak menggunakannya. |

Pada data lokal saat audit terdapat empat koneksi printer aktif dan empat profile output. Printer KASIR, BAR, KITCHEN, dan CHECKER masing-masing memiliki profile, namun profile BAR, KITCHEN, dan CHECKER semuanya menunjuk ke template **KASIR / RECEIPT**. Ini tidak sesuai dengan tujuan operasional KOT.

## Temuan penting

### P0 - Tampilan halaman dan cetakan nyata bisa memakai template berbeda

Contoh nyata pada data lokal:

- Halaman Output menyatakan printer BAR, KITCHEN, dan CHECKER memakai template `KASIR` dengan jenis `RECEIPT`.
- Saat order dikonfirmasi, mesin cetak meminta jenis `KITCHEN_TICKET`.
- Bila template profile tidak cocok, kode diam-diam mencari template lain berdasarkan nama/role, misalnya `TPL-BAR` atau `TPL-KITCHEN`.

Artinya admin dapat melihat "Template KASIR" pada halaman, tetapi printer BAR saat order masuk kemungkinan memakai `TPL-BAR`. Bila template cadangan tidak ditemukan, sistem mengambil template aktif lain atau payload bawaan.

**Risiko:** perubahan di halaman Output tidak selalu mengubah hasil cetak yang sesungguhnya. Ini adalah sumber utama kesan data ambigu dan tumpang tindih.

**Arah perbaikan:** aturan cetak harus menyimpan langsung pasangan `event + lokasi/divisi + printer + layout`. Tidak boleh ada pencarian template berdasarkan nama printer sebagai jalan utama.

### P0 - Pilihan koneksi LAN dan USB belum didukung oleh jalur cetak aktif

Form Device menyediakan `LOCAL_AGENT`, `USB`, dan `LAN`. Namun jalur cetak POS yang aktif hanya mengambil printer dengan `connection_type = LOCAL_AGENT`, `python_port`, dan agent lokal. Agent Python juga hanya menyiapkan printer yang punya MAC address dan port lokal.

**Risiko:** staf dapat menyimpan printer LAN atau USB, mengira printer siap dipakai, tetapi printer tersebut tidak pernah menjadi target cetak POS.

**Arah perbaikan:** selama implementasi LAN/USB belum benar-benar dibuat dan diuji, form hanya boleh menawarkan `Agent Lokal (Bluetooth)` sebagai koneksi produksi. Opsi lain dapat diberi status "belum didukung" atau disembunyikan.

### P0 - Hak akses terlalu lebar untuk konfigurasi printer

Semua halaman printer memakai satu permission `pos.printer.index`. Pada data lokal, role KASIR dan BARISTA mempunyai hak membuat dan mengubah konfigurasi printer. Tombol test print juga hanya membutuhkan hak lihat.

**Risiko:** kasir dapat tidak sengaja mengganti jalur cetak, mematikan printer, atau mengirim test print ke printer produksi.

**Arah perbaikan:**

- `Printer Connection`: hanya Superadmin, Admin teknis, atau manager yang ditunjuk.
- `Tampilan Umum`: Superadmin/manager pemasaran atau finance yang ditunjuk.
- `Aturan Cetak`: Superadmin/manager operasional.
- `Preview`: boleh lihat untuk kasir dan HOD.
- `Test Print`: hanya pemilik akses koneksi printer.
- `Monitor/Reprint`: kasir dapat reprint sesuai event yang diizinkan, tetapi tidak dapat mengubah konfigurasi.

### P1 - Routing cetak masih tertanam di kode, bukan di pengaturan lokasi

Untuk KOT, mesin cetak sekarang hanya mengenal bucket `BAR`, `KITCHEN`, dan `ALL`. Produk minuman diarahkan ke BAR, produk makanan/event diarahkan ke KITCHEN, lalu printer dipilih berdasarkan role `BAR`, `KITCHEN`, atau scope `ALL`.

Tabel yang sebenarnya dirancang untuk outlet, terminal, product division, operational division, event, dan prioritas (`pos_printer_route_rule`) tidak dipakai.

**Dampak:**

- Tidak ada pengaturan jelas untuk lokasi seperti `BAR Reguler`, `Kitchen Reguler`, roastery, atau lokasi baru.
- Menambah divisi baru berisiko harus mengubah kode, bukan cukup menambah aturan.
- CHECKER dengan scope `ALL` bisa menerima semua item tanpa aturan yang mudah dilihat admin.

**Arah perbaikan:** pindahkan routing ke halaman aturan cetak. Mesin cetak hanya membaca aturan aktif, bukan menebak dari nama role.

### P1 - Pengaturan output tersimpan di beberapa tempat dan sebagian tidak memengaruhi cetak

Saat ini logo, harga, dan footer muncul pada:

- payload template;
- `pos_printer_profile` secara konsep UI;
- `pos_printer_content_setting` secara data.

Saat profile disimpan, aplikasi memperbarui `pos_printer_profile`, seluruh baris `pos_printer_content_setting` untuk printer tersebut, dan status aktif printer sekaligus. Namun ketika direct print dibuat, resolver hanya memakai payload template dan pengaturan umum; pengaturan isi pada `pos_printer_content_setting` tidak digabungkan ke payload akhir.

**Dampak:** switch "Tampilkan harga", "Logo", atau "Footer" pada halaman Output dapat terlihat berhasil, tetapi tidak pasti mengubah cetakan operasional.

**Arah perbaikan:** layout menjadi satu-satunya tempat untuk menentukan data apa yang dicetak. Koneksi fisik hanya menyimpan karakteristik fisik: ukuran kertas, jumlah copy jika diperlukan, potong kertas, dan drawer.

### P1 - Status aktif device dan output bukan dua status terpisah

Halaman Profile dan halaman Device sama-sama menyalakan/mematikan kolom `pos_printer.is_active`. Jadi menonaktifkan "profile" sebenarnya mematikan device, dan sebaliknya.

**Arah perbaikan:**

- `Koneksi aktif`: printer fisik boleh menerima pekerjaan.
- `Aturan aktif`: event tertentu boleh diarahkan ke printer tersebut.

Keduanya harus berbeda agar admin dapat menghentikan cetak KOT tanpa mematikan kemampuan test/diagnostic printer.

### P1 - Preview dan Test Print bukan preview transaksi nyata

Halaman preview memilih template manual. Default-nya adalah receipt default, lalu data yang dicetak berasal dari contoh order. Test Print mengirim contoh tersebut langsung ke agent lokal.

**Dampak:**

- Printer BAR dapat dipreview atau ditest menggunakan receipt kasir yang memuat informasi yang tidak seharusnya ada di KOT.
- Preview tidak membuktikan bahwa order nyata akan diarahkan ke printer dan layout yang sama.
- Preview dapat menampilkan informasi umum yang tidak relevan untuk area produksi, termasuk data Wi-Fi bila template mengaktifkannya.

**Arah perbaikan:** buat "Preview Aturan Nyata": pilih order nyata atau data simulasi per event, sistem menunjukkan alasan pemilihan route, printer tujuan, layout, isi yang akan dicetak, dan jumlah copy. Tombol test dipisahkan jelas dari preview.

### P1 - Tidak ada monitor hasil kirim cetak di server

POS saat ini mengirim target cetak dari browser langsung ke `127.0.0.1:{python_port}/cetak`. Pola ini cepat dan tidak membebani request transaksi POS, tetapi server tidak menyimpan hasil respons agent. Tabel job/log lama juga kosong karena tidak dipakai oleh jalur aktif.

**Dampak:** ketika printer mati, laptop agent tidak aktif, kertas habis, atau Bluetooth putus, admin tidak punya riwayat pusat untuk membedakan:

- target memang tidak dibuat;
- target dibuat tetapi browser gagal mengirim;
- agent menerima tetapi printer gagal mencetak;
- user sudah melakukan reprint.

**Arah perbaikan:** setelah browser menerima respons dari agent, browser mengirim acknowledgement singkat ke server. Halaman Monitor Cetak dapat menampilkan `Dibuat`, `Terkirim ke agent`, `Gagal`, `Diulang`, dan alasan. POS tetap boleh menyimpan transaksi lebih dulu; kegagalan printer tidak boleh membatalkan transaksi penjualan.

### P1 - Istilah jenis dokumen berbeda-beda

Event memakai `KOT`, `VOID`, `REFUND`, dan `SHIFT_CLOSE`; template memakai `KITCHEN_TICKET`, `VOID_SLIP`, `REFUND_SLIP`, dan `SHIFT_CLOSE`. Kode memiliki penerjemahan sendiri untuk sebagian istilah.

**Arah perbaikan:** gunakan satu kamus internal dan satu label ramah pengguna:

| Kode internal tunggal | Label UI |
| --- | --- |
| `ORDER_KOT` | Tiket Produksi Order |
| `PAYMENT_RECEIPT` | Struk Pembayaran |
| `PRE_BILL` | Bill Sementara |
| `VOID_SLIP` | Slip Pembatalan |
| `REFUND_SLIP` | Slip Refund |
| `SHIFT_CLOSE` | Ringkasan Tutup Kasir |

### P2 - Panduan sudah tidak sesuai dengan form aktif

Panduan masih meminta terminal, driver `ESC_POS`, dan connection `BLUETOOTH`, sedangkan form saat ini tidak memiliki field terminal/driver dan memakai nilai `LOCAL_AGENT`, `LAN`, atau `USB`.

**Arah perbaikan:** panduan harus dibuat dari alur UI baru, bukan dari istilah teknis lama. Panduan teknis agent dipisahkan dari panduan operator kasir.

### P2 - Ada struktur lama yang tidak dipakai tetapi tetap membingungkan

`pos_printer_route_rule`, `pos_printer_desktop_device`, `pos_printer_job`, dan `pos_printer_job_log` ada di database, tetapi data lokal kosong dan jalur cetak POS aktif tidak menggunakannya. Ada function pembuat job KOT, tetapi tidak dipanggil oleh alur order yang aktif.

**Arah perbaikan:** jangan hapus terlebih dahulu. Tandai sebagai legacy, berhentikan penambahan fitur baru ke pola tersebut, lalu arsipkan setelah jalur baru stabil dan hasil auditnya aman.

## Rancangan sederhana yang disarankan

### 1. Koneksi Printer

Halaman: `/pos/printers/connections`

Tujuan halaman ini hanya memastikan perangkat fisik dapat menerima cetak.

Field yang diperlukan:

- nama koneksi printer;
- outlet;
- lokasi fisik, misalnya `Bar Reguler`, `Kitchen Reguler`, atau `Kasir Utama`;
- jenis koneksi yang benar-benar didukung;
- nama laptop/agent;
- MAC address dan port lokal untuk agent Bluetooth;
- ukuran kertas, karakter per baris, potong kertas, dan drawer;
- status koneksi;
- tombol `Cek Koneksi` dan `Test Cetak Teknis`.

Yang **tidak** boleh ada di halaman ini: pilihan template, logo, harga, footer, role bisnis, atau aturan produk. Itu bukan karakteristik perangkat fisik.

### 2. Tampilan Umum Cetak

Halaman: `/pos/printers/general`

Tujuan halaman ini adalah data bersama yang pantas muncul pada struk pelanggan, misalnya nama outlet, logo, alamat/kontak, dan footer standar.

Aturan penting:

- data yang sudah ada di master outlet/perusahaan dibaca dari sana bila memungkinkan;
- override cetak hanya diizinkan bila memang berbeda dari master;
- informasi Wi-Fi, loyalty, stamp, atau promo tidak otomatis muncul di semua dokumen;
- KOT/produksi tidak boleh otomatis menerima data promosi, harga, atau Wi-Fi.

Secara data, pengaturan umum perlu dipisahkan dari `pos_printer_template_master`. Template master tidak seharusnya dipakai sebagai tempat branding umum.

### 3. Layout Dokumen

Halaman: `/pos/printers/layouts`

Ini adalah pengganti yang lebih jelas untuk halaman Template.

Setiap layout memilih satu jenis dokumen, kemudian admin menentukan data yang tampil. Contoh:

| Dokumen | Data yang lazim dicetak |
| --- | --- |
| Struk Pembayaran | outlet, nomor nota, kasir, produk, extra, subtotal, diskon, pajak/service, total, pembayaran, footer. |
| Tiket Produksi | nomor order, waktu, meja/layanan, produk, qty, extra, catatan. Tanpa harga dan informasi promosi. |
| Bill Sementara | nomor order, produk, qty, harga, perkiraan total. |
| Void/Refund | nomor nota, item/nominal terkait, alasan, petugas dan persetujuan. |
| Tutup Kasir | kasir, shift, ringkasan pembayaran dan selisih. |

Satu layout menyimpan seluruh switch tampil/tidak tampil. Tidak ada switch logo/harga/footer kedua di halaman lain.

### 4. Aturan Cetak Lokasi

Halaman: `/pos/printers/rules`

Ini menjadi pusat operasi. Satu baris aturan menjawab empat pertanyaan:

1. Kejadian apa yang memicu cetak?
2. Barang dari outlet, terminal, divisi, atau lokasi mana?
3. Dicetak ke koneksi printer mana?
4. Menggunakan layout dan berapa copy?

Contoh aturan yang mudah dibaca:

| Kejadian | Sumber | Tujuan printer | Layout |
| --- | --- | --- | --- |
| Order dikonfirmasi | Outlet Namua, divisi BAR | Printer Bar Reguler | KOT Bar 58 mm |
| Order dikonfirmasi | Outlet Namua, divisi KITCHEN | Printer Kitchen Reguler | KOT Kitchen 80 mm |
| Order dikonfirmasi | Outlet Namua, semua divisi | Printer Checker | KOT Checker |
| Pembayaran selesai | Outlet Namua | Printer Kasir Utama | Struk Pembayaran |
| Void | Outlet Namua | Printer Kasir Utama | Slip Pembatalan |

Urutan pemilihan aturan harus eksplisit:

1. outlet + terminal + lokasi/divisi + event;
2. outlet + lokasi/divisi + event;
3. outlet + event;
4. global + event.

Sistem harus menolak dua aturan aktif yang tingkat kekhususannya sama untuk sumber dan event yang sama. Bila ada pengecualian yang sah, gunakan prioritas yang terlihat jelas di UI.

### 5. Preview Aturan Nyata dan Monitor Cetak

Halaman: `/pos/printers/preview-live` dan `/pos/printers/monitor`

`Preview Aturan Nyata`:

- pilih order nyata atau simulasi event;
- tampilkan aturan yang terpilih, alasan pilihannya, lokasi sumber, printer tujuan, layout, copy, dan hasil teks;
- tidak mencetak saat hanya preview;
- test cetak memakai tombol terpisah dengan konfirmasi.

`Monitor Cetak`:

- daftar target cetak per nota/event;
- status `Dibuat`, `Terkirim ke agent`, `Gagal`, `Diulang`, dan `Dibatalkan`;
- pesan gagal yang dapat dipahami operator;
- tombol reprint sesuai hak akses;
- filter tanggal, outlet, printer, event, dan status.

Halaman ini sangat penting karena pola direct print tetap dipertahankan agar transaksi POS cepat, tetapi hasil kirimnya tetap dapat diaudit.

### 6. Panduan

Halaman: `/pos/printers/guide`

Panduan dipisahkan menjadi dua bagian:

- **Panduan Operator:** membaca status, menjalankan preview, melakukan reprint, dan melapor bila printer gagal.
- **Panduan Teknisi:** memasang agent, menghubungkan Bluetooth, mengisi MAC/port, menjalankan validasi, dan restart agent bila port berubah.

Panduan harus hanya menyebut field yang benar-benar ada pada form produksi.

## Model data target

Nama tabel dapat disesuaikan, tetapi pemisahan tanggung jawab perlu seperti ini:

| Data | Fungsi |
| --- | --- |
| `pos_printer_connection` | Satu baris satu perangkat fisik/agent. Tidak menyimpan template atau route. |
| `pos_print_general_setting` | Branding dan data umum yang memang dipakai bersama. Dapat di-scope per outlet. |
| `pos_print_layout` | Layout per jenis dokumen beserta seluruh switch isi cetaknya. |
| `pos_print_route` | Event, scope outlet/terminal/divisi/lokasi, koneksi printer, layout, copy, prioritas, dan status. |
| `pos_print_attempt` | Jejak target cetak dan acknowledgement browser/agent untuk monitor dan reprint. |

Nama tabel lama tidak perlu langsung dihapus. Kita dapat memigrasikan konfigurasi ke tabel target, menjalankan keduanya dalam mode baca selama uji, lalu menjadikan tabel target satu-satunya sumber cetak setelah hasilnya sama.

## Rencana perbaikan yang aman

### Tahap A - Bersihkan definisi tanpa mengubah hasil cetak

1. Dokumentasikan peta empat printer aktif, lokasi fisik, ukuran kertas, dan fungsi masing-masing.
2. Tandai jenis koneksi yang didukung sekarang hanya `LOCAL_AGENT`.
3. Perbaiki permission agar kasir/barista tidak dapat mengubah koneksi atau aturan.
4. Perbaiki panduan agar sesuai form yang aktif.
5. Tampilkan peringatan pada halaman lama bahwa profile/template belum menjadi sumber rute tunggal.

### Tahap B - Bangun sumber konfigurasi baru

1. Buat tabel koneksi, layout, aturan cetak, dan log attempt.
2. Migrasikan empat koneksi printer dan template yang aktif tanpa menghapus data lama.
3. Buat aturan awal berdasarkan perilaku yang sekarang: BAR, KITCHEN, CHECKER, dan KASIR.
4. Pastikan tiap event memiliki layout eksplisit, termasuk refund dan tutup shift.
5. Validasi bahwa tidak ada dua aturan aktif yang bentrok.

### Tahap C - Uji hasil cetak nyata

1. Order hanya minuman: hanya KOT BAR yang menerima item.
2. Order hanya makanan: hanya KOT KITCHEN yang menerima item.
3. Order gabungan: BAR dan KITCHEN menerima potongan item masing-masing, CHECKER menerima sesuai aturan.
4. Pembayaran: kasir menerima receipt dan KOT tidak menampilkan harga.
5. Void/refund: printer dan layout yang benar menerima slip dengan alasan.
6. Printer/agent mati: order tetap tersimpan, tetapi monitor menampilkan kegagalan dan dapat reprint.
7. Uji 58 mm dan 80 mm di printer fisik. Nilai karakter per baris sekarang perlu divalidasi melalui hasil kertas, bukan diasumsikan benar.

### Tahap D - Cutover dan pensiun pola lama

1. Resolver POS beralih membaca `pos_print_route`, bukan role hardcoded dan pencarian nama template.
2. Halaman `/pos/printers` lama diubah menjadi dashboard ringkas atau redirect ke halaman Koneksi/Aturan.
3. Tabel route/job lama hanya diarsipkan setelah monitor cetak baru stabil dan sudah ada bukti hasil test.
4. Jangan menghapus konfigurasi/data lama sebelum satu periode operasional berjalan tanpa salah cetak.

## Keputusan yang sebaiknya disepakati sebelum implementasi

1. Apakah printer CHECKER menerima semua item atau hanya item yang membutuhkan pemeriksaan?
2. Apakah satu lokasi kerja boleh mempunyai lebih dari satu printer aktif untuk event yang sama? Bila ya, apakah keduanya memang harus mencetak atau salah satunya cadangan?
3. Siapa yang boleh mengubah koneksi fisik, layout, dan aturan cetak?
4. Apakah reprint KOT dibatasi per kasir/shift atau hanya oleh supervisor?
5. Apakah data Wi-Fi dan promosi memang diizinkan pada receipt pelanggan, dan di outlet mana?

## Kesimpulan

Masalah utamanya bukan jumlah printer, melainkan konfigurasi fisik, layout, dan routing dicampur dalam beberapa tabel dan halaman. Mesin cetak saat ini masih bekerja karena memiliki fallback berdasarkan role/nama template, tetapi fallback tersebut membuat hasil sulit diprediksi dari UI.

Rancangan baru harus membuat admin selalu bisa menjawab, dari satu layar: **"Order dari lokasi ini, saat event ini, akan dicetak ke printer ini, dengan layout ini, sebanyak ini."** Bila jawaban tersebut terlihat jelas, konfigurasi printer akan kembali sederhana dan aman dioperasikan.

## Implementasi 24 Agustus 2026

Rancangan di atas sudah mulai diterapkan. Jalur cetak baru tidak lagi mencari template berdasarkan nama printer atau role sebagai fallback utama. Selama ada minimal satu aturan cetak aktif, POS membaca konfigurasi baru sebagai satu sumber keputusan cetak.

### Sumber data yang benar-benar dipakai saat mencetak

| Kebutuhan | Tabel sumber | Diatur dari halaman | Dampak pada cetakan nyata |
| --- | --- | --- | --- |
| Nama usaha, logo, alamat singkat, Wi-Fi, pesan pembuka/penutup, serta format pesan voucher | `pos_print_general_setting` | `Tampilan Umum` | Menyediakan isi data master; halaman ini tidak menentukan dokumen mana yang menampilkannya. |
| Data yang tampil atau disembunyikan | `pos_print_layout` | `Layout Dokumen` | Menentukan nomor nota, pelanggan, meja, waktu, kasir, produk, qty, extra, catatan, harga, total, pembayaran, alasan void/refund, footer, logo, Wi-Fi, barcode, poin, stamp, dan pesan voucher. |
| Tujuan fisik | `pos_print_connection` | `Koneksi Printer` | Menentukan agent, kode printer, port, ukuran kertas, jumlah copy default, potong kertas, dan laci kas. |
| Kapan dan ke mana dicetak | `pos_print_route` | `Aturan Cetak` | Menghubungkan event POS, outlet/divisi, koneksi, layout, dan copy. Ini yang dipakai engine untuk memilih tujuan cetak. |
| Jejak pengiriman | `pos_print_attempt` | `Monitor Cetak` | Menyimpan target cetak, aturan, koneksi, event, dokumen, dan jawaban browser/agent. |

Contoh: bila pada layout KOT BAR `Harga`, `Footer`, `Saldo poin`, `Stamp`, dan `Pesan voucher` dimatikan, KOT BAR yang benar-benar dikirim ke agent tidak akan berisi informasi tersebut, walaupun data pelanggan dan master masih tersedia. Bila `Logo` diaktifkan, engine mengirim penanda logo ke agent. Bila `Barcode footer` diaktifkan, engine mengirim nilai barcode yang dipilih dari nomor order, pembayaran, void, refund, voucher, atau nilai manual.

### Dokumen yang sudah mengikuti layout

- KOT order dan cetak ulang KOT.
- Bill sementara.
- Struk pembayaran dan cetak ulang struk.
- Slip void dan refund.
- Ringkasan tutup kasir.

Pengaturan umum tidak dapat lagi ditimpa secara diam-diam oleh layout. Layout hanya boleh menentukan **apakah** data umum itu tampil, sedangkan isi nama usaha, logo, Wi-Fi, dan footer tetap berasal dari Tampilan Umum Cetak.

### Pengaman yang diterapkan

- Aturan aktif hanya dapat memakai koneksi dan layout yang aktif.
- Koneksi produksi wajib memakai `Local Agent` dan memiliki port agent yang valid.
- Sistem menolak dua aturan aktif dengan event dan cakupan sumber yang benar-benar sama agar satu event tidak tercetak ganda tanpa sengaja.
- Tombol tambah, edit, aktif/nonaktif, simpan, dan test koneksi mengikuti hak akses halaman masing-masing. Pengguna baca-saja tetap dapat memeriksa konfigurasi, tetapi tidak diarahkan ke aksi yang akan ditolak server.
- Menjalankan migration ulang tidak menimpa nama, layout, koneksi, route, ataupun switch yang sudah diedit operator. Migration hanya menambahkan data awal yang belum ada; page, menu, dan hak akses tetap boleh diperbarui.
- Preview menampilkan aturan, event, tujuan fisik, layout data tampil, sumber master, lokasi, dan cakupan item yang dipakai.

### SQL yang perlu dijalankan

Jalankan sekali pada setiap database tujuan sebelum menggunakan halaman baru:

```powershell
Get-Content -Raw sql\2026-08-24c_pos_print_configuration_single_source_foundation.sql |
  C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root db_finance

Get-Content -Raw sql\2026-08-24d_pos_print_layout_customer_visibility.sql |
  C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root db_finance

Get-Content -Raw sql\2026-08-25a_pos_print_rule_mode_and_customer_review_foundation.sql |
  C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root db_finance
```

Di server, sesuaikan path binary MySQL dan nama database. Script `2026-08-24c` aman dijalankan ulang setelah perubahan konfigurasi printer karena seed konfigurasi sekarang bersifat tidak menimpa data yang telah ada. Script `2026-08-24d` menerapkan baseline aman bahwa KOT/slip/ringkasan shift tidak membawa poin, stamp, atau voucher pelanggan, sekaligus menyimpan switch visibilitas layout lama secara eksplisit di database tanpa menimpa pilihan yang telah ada.

### Checklist uji operator

1. Buka `Printer POS > Koneksi Printer`, lalu pastikan nama printer, kode printer di agent, dan port sesuai komputer fisik.
2. Buka `Tampilan Umum`, cek nama usaha, logo, dan footer yang memang ingin dipakai bersama.
3. Pada `Layout Dokumen`, buka KOT BAR/KITCHEN dan pastikan harga, pembayaran, Wi-Fi, poin, stamp, serta voucher dimatikan; untuk struk kasir, aktifkan hanya data pelanggan yang diperlukan.
4. Pada `Aturan Cetak`, pastikan BAR, KITCHEN, CHECKER, dan KASIR memiliki satu aturan aktif sesuai tujuan masing-masing.
5. Gunakan `Preview` untuk mengecek gabungan master, layout, dan koneksi sebelum membuat transaksi nyata.
6. Uji kertas dengan satu order minuman, satu order makanan, satu order gabungan, satu pembayaran, satu void/refund, dan satu tutup kasir.
7. Periksa `Monitor` setelah setiap uji. Status `Terkirim ke agent` berarti browser sudah meneruskan perintah ke komputer printer; tetap cocokkan dengan hasil kertas fisik.

### Hasil validasi lokal

- Seluruh 7 aturan aktif lokal memiliki koneksi `Local Agent`, port agent, dan jenis layout yang sesuai.
- Tidak ditemukan aturan aktif duplikat dengan event dan cakupan yang sama.
- Preview aturan membaca label, koneksi, layout, dan master dari konfigurasi baru tanpa error JavaScript.
- PHP lint bersih pada model, controller, library, dan seluruh halaman konfigurasi printer.

Uji kertas fisik belum dilakukan otomatis karena itu membutuhkan printer dan agent di lokasi. Langkah terakhir sebelum dipakai penuh adalah menjalankan checklist operator di atas pada printer 58 mm dan 80 mm.

## Penyederhanaan operasional 25 Agustus 2026

Konfigurasi cetak sekarang dipakai melalui empat halaman yang urut dan tidak saling menimpa:

1. `Koneksi Printer` untuk alat fisik. Isi nama printer, komputer/agent, kode printer, ukuran kertas, jumlah salinan, potong kertas, dan laci kas di sini.
2. `Tampilan Umum` untuk data bersama, misalnya nama usaha, logo, alamat, footer, dan pesan ulasan pelanggan.
3. `Layout Dokumen` untuk memilih bagian yang tampil pada masing-masing jenis kertas. Edit dilakukan di halaman penuh dengan preview langsung, bukan modal kecil.
4. `Aturan Cetak` untuk menghubungkan kejadian POS, lokasi/divisi, koneksi fisik, dan layout. Halaman ini juga menentukan apakah dokumen tidak dicetak, dicetak langsung, atau kasir ditanya terlebih dahulu.

URL template, profile, device, preview, dan test versi lama sekarang mengarahkan operator ke halaman baru atau memberi pesan jelas bila dipanggil oleh sistem lama. Tujuannya agar tidak ada lagi dua tempat yang terlihat bisa mengatur printer tetapi hanya salah satunya yang benar-benar dipakai saat mencetak.

### Pilihan pada Aturan Cetak

| Pilihan | Arti untuk kasir dan sistem |
| --- | --- |
| `Off` | Aturan disimpan, tetapi tidak mengirim kertas. Cocok untuk sementara menonaktifkan printer tanpa menghapus pengaturannya. |
| `Cetak otomatis` | Setelah kejadian POS berhasil tersimpan, sistem langsung mengirimnya ke koneksi printer pada aturan tersebut. |
| `Tanya dulu` | Setelah kejadian POS berhasil tersimpan, kasir mendapat pilihan `Cetak` atau `Tidak sekarang`. Ini cocok untuk struk yang tidak selalu dibutuhkan dan membantu menghemat kertas. |

### Pengaturan yang benar-benar sampai ke printer

Saat sebuah dokumen dikirim, sistem mengambil layout dan koneksi dari aturan yang aktif. Koneksi kemudian mengirim ukuran kertas, jumlah salinan, mode potong, dan pilihan buka laci ke agent. Agent mencetak sejumlah salinan yang ditetapkan, hanya membuka laci pada salinan pertama, dan memotong kertas sesuai pilihan `Tidak potong`, `Potong sebagian`, atau `Potong penuh`.

Tombol `Test` pada halaman Koneksi menguji alat fisik dengan pengaturan koneksi tersebut. Tombol `Test Cetak` pada editor Layout menguji gabungan layout dan salah satu aturan aktif yang memakai layout itu. Pesan berhasil atau gagal ditampilkan sebagai notifikasi, dan riwayatnya dapat dilihat pada `Monitor Cetak`.

### Ulasan pelanggan melalui QR pada struk

Sistem sekarang dapat menambahkan QR ulasan pelanggan pada struk pembayaran. QR tidak pernah muncul pada KOT, void, refund, atau ringkasan shift.

Supaya QR muncul, aktifkan dua switch berikut:

1. Di `Tampilan Umum`, aktifkan `Tampilkan QR ulasan pelanggan pada struk pembayaran` dan isi pesan singkatnya.
2. Di layout `Struk Pembayaran`, aktifkan bagian `QR ulasan pelanggan`.

Setelah pembayaran, sistem membuat tautan khusus untuk nota tersebut. Pelanggan memindai QR, memberi bintang satu sampai lima, dan boleh menulis ulasan. Operator dapat membaca serta menyembunyikan ulasan yang tidak layak dari halaman `Ulasan Pelanggan` di bawah Laporan POS. Satu nota hanya dapat mengirim satu ulasan agar hasilnya tidak dimanipulasi berulang.

### Tambahan pada komputer agent printer

Agent perlu memakai file `agent.py` terbaru dan memasang library QR satu kali agar penanda QR dalam struk berubah menjadi gambar QR yang benar. Jalankan di komputer yang terhubung ke printer:

```powershell
cd C:\path\ke\pos_printer_agent
python -m pip install -r requirements.txt
```

Jika library QR belum dipasang, sistem tetap mencetak tautan ulasan sebagai teks agar pelanggan masih dapat membukanya, tetapi gambar QR belum dapat dibuat oleh printer agent.

### Checklist uji setelah deploy

1. Jalankan migration `2026-08-25a_pos_print_rule_mode_and_customer_review_foundation.sql` setelah dua migration printer sebelumnya.
2. Buka `Koneksi Printer`, klik ikon test pada setiap koneksi aktif, lalu cek toast dan `Monitor Cetak`.
3. Buka satu layout KOT dan satu layout receipt. Pastikan preview menampilkan lebar kertas yang benar pada area kertas putih di tengah latar gelap.
4. Dari editor layout, pilih aturan yang benar lalu lakukan test. Cocokkan jumlah copy, potongan kertas, dan laci kas dengan hasil fisik.
5. Ubah satu aturan aman ke `Tanya dulu`, buat transaksi uji, lalu pastikan kasir dapat memilih apakah dokumen dicetak.
6. Aktifkan QR pada pengaturan umum dan layout receipt, selesaikan transaksi uji, scan QR pada kertas, kirim ulasan, lalu cek data pada `Ulasan Pelanggan`.

### Validasi yang sudah dilakukan secara lokal

- Migration `2026-08-25a` berhasil dijalankan. Delapan aturan cetak awal terdeteksi dan dikonversi ke mode `Cetak otomatis` tanpa menghapus aturan yang ada.
- Seluruh PHP controller, model, library, halaman konfigurasi baru, dan halaman kasir yang berubah lolos pemeriksaan sintaks PHP.
- Agent printer lolos pemeriksaan sintaks Python dan mendukung jumlah copy, mode potong, laci kas, serta penanda QR.
- Cetak fisik QR belum dapat diuji otomatis pada komputer pengembangan karena library `qrcode` belum terpasang di lingkungan Python lokal. Ini diselesaikan dengan perintah instalasi agent di atas dan tetap perlu diuji pada printer fisik 58 mm maupun 80 mm.

## Penyelarasan lebar kertas dan teks 25 Agustus 2026

Preview di editor layout, preview langsung, test cetak, dan teks yang dikirim ke agent sekarang memakai aturan lebar yang sama:

| Ukuran kertas | Kapasitas teks |
| --- | --- |
| 58 mm | 32 karakter per baris |
| 80 mm | 48 karakter per baris |

Angka tersebut tidak lagi diisi bebas pada koneksi printer. Saat operator memilih kertas 58 mm, sistem menyimpan 32 karakter; saat memilih 80 mm, sistem menyimpan 48 karakter. Ini mencegah koneksi 58 mm lama tetap memakai 48 karakter sehingga tulisan melampaui lebar kertas.

Area kertas putih di preview juga dibuat proporsional: kertas 58 mm terlihat lebih sempit daripada 80 mm. Pesan panjang seperti informasi voucher, catatan, alamat, Wi-Fi, footer, dan URL ulasan pelanggan sekarang turun ke baris berikutnya. Teks panjang tidak lagi dipotong diam-diam di ujung kanan.

Pengaman ini bukan hanya di browser. Sebelum payload dikirim ke agent, engine cetak juga membungkus setiap baris teks sesuai kapasitas kertas. Karena itu data nyata yang lebih panjang daripada contoh preview tetap tidak boleh melewati lebar 58 mm atau 80 mm. Penanda logo, QR, dan barcode tetap dibiarkan utuh agar agent dapat memprosesnya sebagai gambar.

### SQL tambahan yang perlu dijalankan

Jalankan migration berikut pada database lokal dan server setelah migration printer sebelumnya:

```powershell
Get-Content -Raw sql\2026-08-25b_normalize_pos_print_paper_width_and_columns.sql |
  C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root db_finance
```

Di server, sesuaikan path MySQL dan nama database. Script ini aman dijalankan ulang. Ia hanya menormalkan konfigurasi koneksi printer menjadi 58 mm/32 karakter atau 80 mm/48 karakter; tidak mengubah transaksi, layout, maupun riwayat cetak.

### Checklist uji tampilan dan kertas

1. Buka `Koneksi Printer`. Pastikan BAR dan CHECKER yang menggunakan 58 mm tampil sebagai `58 mm / 32 karakter`; KASIR dan KITCHEN 80 mm tampil sebagai `80 mm / 48 karakter`.
2. Buka editor layout KOT BAR. Area kertas putih harus terlihat lebih sempit dibanding editor layout struk kasir 80 mm.
3. Buka `Preview Langsung`, pilih satu aturan BAR/KOT 58 mm dan satu aturan kasir/struk 80 mm. Pastikan label ukuran dan lebar kertas berubah mengikuti aturan.
4. Periksa contoh dengan catatan panjang dan voucher. Kalimat harus lanjut ke baris berikutnya, bukan hilang di ujung kanan.
5. Saat agent printer sudah hidup, lakukan `Test` pada setiap koneksi dan cocokkan hasil kertas fisik dengan preview.

### Validasi lokal

- Konfigurasi lokal berhasil dinormalisasi: BAR dan CHECKER menjadi 58 mm/32 karakter; KASIR dan KITCHEN menjadi 80 mm/48 karakter.
- Pemeriksaan sintaks PHP bersih pada controller, model, library, dan tiga halaman preview/koneksi yang terdampak.
- Uji preview dan uji pengaman payload cetak membuktikan tidak ada baris teks yang melampaui 32 maupun 48 karakter.
- Cetak fisik tetap harus diuji setelah agent printer aktif, karena komputer pengembangan tidak sedang terhubung ke printer fisik.
