# Checklist tes manual seluruh perbaikan Finance

Review lintas modul — Batch 265, 2026-09-16 06:43 WIB. Cakupan: riwayat audit/perbaikan Batch 1–264, bukan hanya Pengajuan Divisi. Dasar: dua roadmap, execution log, laporan modul, source terkait dan regresi terpilih. HEAD saat review `a63a8a69` **ditambah working tree yang belum di-commit**; bukan bukti isi suatu paket release.

Dokumen ini **lembar penerimaan pengguna**, bukan roadmap ketiga. Status pekerjaan/bug tetap pada [_30](2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md); penjualan/paket customer tetap pada [_28](2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md). Checklist Pengajuan Divisi yang rinci tetap menjadi lampiran, tidak menggantikan checklist lintas aplikasi ini.

## Cara pakai dan urutan tes

- Setiap kotak kosong berarti **BELUM DIUJI manual**. Hasil tes otomatis tidak otomatis mencentang kotak pengguna.
- Awali perbaikan terbaru: **FIN → REC → CTL → ALC → GL → POS → HR → PUR**. Berikutnya regresi modul lama dan UI; terakhir perangkat serta praktik customer.
- Label **[Lihat]** berarti tidak sengaja melakukan aksi simpan bisnis, meskipun login/akses halaman dapat mencatat sesi/log. **[Tulis uji]** membuat/mengubah dokumen, saldo atau setting pada data uji. **[Perangkat]** perlu APK/printer fisik. **[Admin/Control]** ditunda sampai ada lingkungan disposable dan otorisasi pelaksanaan.
- Gunakan instance/data uji terisolasi. Beri catatan `UAT-<kode tes>-<tanggal>`, catat ID dokumen dan saldo awal. Jangan membayar pelanggan/vendor sungguhan, mengubah payroll asli, menghapus bukti, mereset stok, atau mengulang seluruh SQL demi tes.
- Untuk data negatif, konversi tidak ada, atau status khusus yang belum tersedia, tandai **BELUM DIUJI**, jangan membuat kerusakan pada data produksi. Mismatch historis tetap milik owner; tidak otomatis direpair oleh checklist ini.
- Sampai PR-01 diperbaiki, **jangan edit dan verifikasi pengajuan yang sama secara bersamaan**. Pengujian race/failure injection hanya di fixture atau lingkungan disposable oleh engineer.
- Format hasil: `ID | LULUS/GAGAL/BELUM DIUJI | akun/peran | nomor dokumen | jam | harapan vs hasil | screenshot`. Stop percobaan tulis jika saldo/status tidak konsisten atau dokumen ganda.

## A. Login, multi-role, izin, dan scope

Bukti awal: Batch 1–18, 53, 73–93, 148–151, 212. Role adalah gabungan fungsi; jangan memecah satu pegawai menjadi beberapa akun hanya untuk STAFF + BARISTA. Asset yang sengaja dibuka untuk staf bukan bug izin.

- [ ] **AUTH-01 [Lihat]** Login akun multi-role yang valid → akses pribadi STAFF dan tugas role lain tersedia sesuai konfigurasi; bukan gagal login karena memiliki lebih dari satu role.
- [ ] **AUTH-02 [Tulis uji]** Pada akun uji, password salah mendapat pesan wajar; pengulangan dibatasi dan pemulihan mengikuti aturan. Jangan sengaja mengunci akun operasional.
- [ ] **AUTH-03 [Lihat]** Akun baca-saja mencoba URL/form simpan modul terbatas → ditolak server, bukan sekadar tombol disembunyikan.
- [ ] **AUTH-04 [Lihat]** Akun satu divisi/outlet membuka data divisi/outlet lain → sesuai batas scope; akun Purchase/admin yang memang diberi akses luas tidak dipaksa terbatas.
- [ ] **AUTH-05 [Tulis uji]** Keluar/login ulang atau pencabutan izin akun uji → sesi/aksi sensitif tidak memakai hak lama. Password konfirmasi salah/kedaluwarsa tidak boleh meloloskan refund/aksi terkait.

## B. Sidebar, favorite, dan aktivitas pengguna

Bukti: Batch 100–104, 183, 209–211, 220. Halaman audit: `/system/activity-audit`.

- [ ] **NAV-01 [Lihat]** Sidebar terkelompok berdasarkan pekerjaan, tanpa grup kosong/duplikat membingungkan; level keempat, penanda aktif dan tampilan ponsel terbaca.
- [ ] **NAV-02 [Tulis uji]** Tambah favorite menu yang diizinkan; saat akses akun uji dicabut, favorite tidak menjadi pintu belakang membuka modul.
- [ ] **NAV-03 [Tulis uji]** Akses halaman dan ubah satu master uji pada modul yang sudah diaudit → log menunjukkan pengguna, waktu, IP/User-Agent, aksi dan referensi/perubahan yang tersedia. Jangan mengharapkan histori sebelum logging aktif atau isi rahasia; tidak semua aktivitas tanpa instrumentasi otomatis memiliki before/after.

## C. Master, resep, formula, extra, dan bundle

Bukti: Batch 54–68, 148–149, 168–175, 180.

- [ ] **MST-01 [Tulis uji]** Buat/edit master uji dengan izin yang tepat → tersimpan sekali dan audit tersedia; role tanpa izin tidak bisa melakukan aksi yang sama lewat URL langsung.
- [ ] **MST-02 [Tulis uji]** Edit resep/formula yang sama di dua tab pada data uji → versi yang sudah tertinggal ditolak/minta muat ulang, tidak diam-diam menimpa perubahan tab lain.
- [ ] **MST-03 [Tulis uji]** Ubah mapping extra/group/bundle → pilihan terkait di POS mengikuti konfigurasi; duplikat, relasi hilang/nonaktif dan mapping yang tidak valid tidak menghasilkan setengah perubahan.
- [ ] **MST-04 [Tulis uji]** Lihat versi Formula Component dan restore versi uji dengan izin/konfirmasi → tercatat versi restore baru; riwayat lama tidak hilang dan editor legacy tidak menulis jalur kedua.

## D. POS web, pembayaran, void/refund, dan laporan

Bukti: Batch 15–32, 96–97, 156/158, 197–199, 252/262. Laporan: `/pos/reports/sales`.

- [ ] **POS-01 [Tulis uji]** Buka sesi kasir, simpan order, buka lagi dan tambah item → item/qty/harga tersimpan, bukan hilang atau berlipat setelah refresh.
- [ ] **POS-02 [Tulis uji]** Produk biasa + bundle + extra + voucher pada order uji → rincian, diskon, pajak/service dan total konsisten dari keranjang, tagihan, pembayaran sampai struk.
- [ ] **POS-03 [Tulis uji]** Bayar order uji, termasuk metode/split payment yang digunakan usaha → sisa tagihan, rekening, status dan laporan sesuai; submit/refresh ulang tidak membayar dua kali.
- [ ] **POS-04 [Tulis uji]** Produk event tanpa resep: VOID sebelum bayar; REFUND sebagian lalu sisanya setelah bayar → jalur tidak ditutup karena tidak ada commit stok, nominal tidak melebihi yang boleh dikembalikan, tidak membuat stok fiktif.
- [ ] **POS-05 [Tulis uji]** Order campuran produk resep + event: refund event saja tidak mengembalikan stok produk lain; reversal produk resep memakai snapshot asal, bukan resep yang kebetulan berubah setelah penjualan. Kas dan HPP mengikuti bagian yang dibatalkan.
- [ ] **POS-06 [Lihat]** Laporan penjualan diurutkan jam order terbaru, bukan jam pembayaran; coba lintas halaman, filter dan order lama dibayar belakangan. Di ponsel, invoice/tombol detail tidak terpotong. Filter periode tetap memakai basis laporan existing, bukan otomatis ikut berubah menjadi tanggal order.

## E. Order member/online, Self Order, dan reservasi

Bukti: Batch 26–31, 176–191, 196–199.

- [ ] **ORD-01 [Tulis uji]** Order member/online dan Self Order uji masuk inbox yang benar, diverifikasi sekali dan terbuka di kasir; pilih filter cepat tidak menampilkan hasil respons lama sebagai hasil terbaru.
- [ ] **ORD-02 [Tulis uji]** Order Online Food dengan promo/biaya → harga order, pembayaran dan selisih settlement dapat ditelusuri; jangan membuat biaya kedua sebelum mengecek potongan yang sudah tercatat.
- [ ] **ORD-03 [Tulis uji]** Tolak/batalkan reservasi **tanpa refund DP** → tidak meminta proof refund yang tidak diperlukan dan tidak mengembalikan uang.
- [ ] **ORD-04 [Tulis uji]** Tolak/batalkan reservasi **dengan refund DP** → perlu konfirmasi sensitif yang benar; pembayaran balik dan status tercatat satu kali. Refresh tidak mengembalikan DP kedua kali.

## F. Printer, logo, dan koneksi perangkat

Bukti: Batch 9–10, 185/185A, 216/221. UI: `/pos/printers/general`.

- [ ] **PRN-01 [Tulis uji]** Pilih file logo baru → preview langsung mengikuti file; simpan dan buka kembali → logo tetap. Simpan tanpa file baru tidak menghilangkan logo lama.
- [ ] **PRN-02 [Perangkat]** Cetak struk pada printer fisik → logo bukan blok hitam, lebar/margin/karakter dan potongan kertas sesuai. Preview HTML saja tidak membuktikan ini.
- [ ] **PRN-03 [Perangkat]** Agent/printer offline lalu pulih → status/pesan jelas dan retry terkontrol; reprint memakai izin/proof yang benar dan tidak menggandakan pembayaran.
- [ ] **PRN-04 [Perangkat]** Uji beda printer kasir/dapur/bar dan konfigurasi lokal APK saat dilanjutkan → tujuan cetak benar, pengaturan kertas/karakter perangkat tidak tertimpa konfigurasi yang tidak sesuai. Status APK tetap belum diterima operasional.

## G. Purchase, harga beli, Store Request, dan pengajuan divisi

Bukti: Batch 3/82–83/109–110, 182–184, 194/246, 263–264.

- [ ] **PUR-01 [Lihat]** `/purchase/item-price-history/<id-item>` pada item dengan pembelian PAID, termasuk kasus TISSUE POP UP → riwayat harga tampil meski modul Receipt tidak dipakai; receipt/paid-PO/ledger dijelaskan dan transaksi yang sama tidak berlipat.
- [ ] **PUR-02 [Lihat]** `/purchase-order` semua tab, pencarian/filter/pagination dan detail di ponsel → kolom/aksi tetap dapat dijangkau, loading/kosong/error dibedakan.
- [ ] **PUR-03 [Tulis uji]** `/store-request` untuk divisi Roastery → tersedia Reguler/Event yang sesuai, tujuan tersimpan benar; tidak menukar stok lokasi lain.
- [ ] **PUR-04 [Tulis uji]** PO/SR uji parsial sampai pemenuhan yang dipilih → qty diminta/disetujui/dipenuhi/sisa dan status sesuai, pemenuhan ulang tidak menggandakan stok. Jangan menjalankan receipt pada transaksi nyata hanya demi tes.
- [ ] **PUR-05 [Tulis uji]** Ikuti [U01–U19 konfirmasi stok](2026-09-16_konfirmasi_stok_pengajuan_divisi.md#checklist-tes-manual-pengguna): stok divisi/gudang, satuan, konfirmasi beralasan, perubahan/expiry, bukti SR/PO. PR-01/02/03 masih terbuka; acceptance belum selesai.

## H. UI stok gudang, bahan baku, divisi, dan component

Bukti: Batch 48, 69–72, 195/200–208. Tes **semua tab**, bukan hanya halaman awal.

- [ ] **INV-01 [Lihat]** Ketiga rumpun stok memiliki urutan kartu ringkasan berwarna, filter, tabel dan pagination yang konsisten; arti jumlah/nilai pada setiap kartu jelas.
- [ ] **INV-02 [Lihat]** Snapshot bulanan dan Daily Matrix memakai pilihan bulan yang sesuai; tidak ada dua filter periode yang saling bertentangan. Ganti bulan/lokasi tidak menyisakan angka bulan sebelumnya.
- [ ] **INV-03 [Lihat]** Pencarian, jumlah per halaman, next/back, filter kosong dan reset di semua tab → hasil/total benar dan tidak tertukar oleh respons jaringan lama.
- [ ] **INV-04 [Tulis uji]** Transfer/adjustment bahan baku atau gudang pada data uji → perubahan lokasi asal/tujuan/qty benar, tidak tertulis dua kali setelah retry; mutasi tanggal periode CLOSED ditolak.
- [ ] **INV-05 [Lihat]** Dashboard membedakan mismatch kuantitas dan nilai; selisih lama tidak harus menjadi nol untuk tes UI lulus. Menampilkan masalah yang dulu tersembunyi bukan otomatis bug baru.

## I. HPP, koreksi nilai, lot/FIFO, defisit, dan produksi

Bukti: Batch 69–72, 94–99, 159–175.

- [ ] **HPP-01 [Tulis uji]** Pada component uji, koreksi HPP/unit dengan target saldo sama → qty tidak berubah, nilai stok/lot dan audit koreksi selaras; saran live/resep normal diberi sumber yang jelas.
- [ ] **HPP-02 [Tulis uji]** VOID koreksi nilai salah lalu koreksi ulang → nilai efektif mengikuti koreksi yang masih sah, tidak menumpuk efek koreksi yang dibatalkan.
- [ ] **HPP-03 [Tulis uji]** Produk yang memakai component tersebut → HPP live/cache ikut berubah setelah proses sinkron selesai; VOID koreksi memulihkan perhitungan terkait. Transaksi lama tidak ditulis ulang diam-diam.
- [ ] **HPP-04 [Tulis uji]** Produksi/resep/formula, lot FIFO dan penyelesaian defisit pada dataset khusus → pemakaian, hasil, qty/nilai dan reversal dapat ditelusuri. Simulasikan kegagalan/concurrent writer hanya oleh engineer di disposable, bukan lewat repair massal data usaha.

## J. Kas/bank, mutasi non-sales/non-purchase, dan estimasi

Bukti: Batch 181/213, 247–249/253–255. UI Mutasi Kas/Mutasi Rekening dan `/finance-reports/financial-estimation`.

- [ ] **FIN-01 [Tulis uji]** IN Pendapatan lain-lain dan OUT Biaya operasional di luar sales/purchase → masuk mutasi rekening dan arus kas; kategori laporan mengikuti arah, tidak hilang karena bukan POS/PO.
- [ ] **FIN-02 [Tulis uji]** OUT Promo ditanggung usaha/Komisi platform → mengurangi kas dan tercatat pada kategori biaya yang tepat, dengan referensi agar tidak dicatat ulang dari rekonsiliasi/settlement.
- [ ] **FIN-03 [Tulis uji]** Setoran modal, prive dan koreksi saldo saja → saldo bergerak tetapi tidak otomatis menjadi pendapatan/beban operasional. Jurnal lawan tetap harus dipilih sesuai bukti.
- [ ] **FIN-04 [Tulis uji]** Transfer antarrekening se-mata-uang → rekening asal turun, tujuan naik sama besar dan total gabungan tidak berubah; bukan pemasukan/beban baru. Transfer lintas mata uang tidak boleh diam-diam dianggap kurs 1:1.
- [ ] **FIN-05 [Tulis uji]** Mutasi backdate pada periode uji OPEN → saldo bisnis sesuai tanggal akhir filter, sementara kolom sebelum/sesudah posting menjelaskan urutan pencatatan. CLOSED menolak mutasi; jangan reopen periode usaha tanpa otorisasi.
- [ ] **FIN-06 [Lihat]** Estimasi membedakan pendapatan/biaya, perubahan saldo saja dan kategori belum ditentukan; filter tampilan tidak menghilangkan dasar saldo. Estimasi bukan otomatis laporan akrual final atau saldo bank terverifikasi.

## K. Rekonsiliasi Pendapatan harian dan Rekonsiliasi Kas

Bukti: Batch 247, 253–255. UI `/finance-reports/revenue-reconciliation` dan `/finance-reports/cash-reconciliation`.

- [ ] **REC-01 [Tulis uji]** Isi uang nyata berbeda dari sistem lalu pilih **biarkan selisih** → catatan/sisa selisih terlihat, tidak ada mutasi otomatis.
- [ ] **REC-02 [Tulis uji]** Pilih mutasi IN atau OUT sesuai sebab/arah selisih → pilihan kategori sama maknanya dengan Mutasi Kas; saldo dan selisih efektif berubah sekali setelah posting.
- [ ] **REC-03 [Tulis uji]** Pilih pindah antarrekening → pasangan keluar/masuk tertaut, saldo total tetap; Rekon Pendapatan tetap berfokus hari/pendapatan terpilih, tidak dipaksa menjadi khusus settlement platform.
- [ ] **REC-04 [Tulis uji]** Sesudah menyimpan draft rekonsiliasi, ubah saldo melalui transaksi uji sah lalu posting → diminta meninjau ulang, bukan memposting nominal baru yang belum dikonfirmasi.
- [ ] **REC-05 [Lihat]** Setelah posting/koreksi, bedakan "selisih saat posting" dan "sisa selisih kini". Submit ulang tidak menulis penyesuaian kedua; hubungan ke mutasi dan audit bisa ditelusuri.

## L. Kontrol Keuangan, settlement, biaya, persetujuan, dan proyeksi

Bukti: Batch 248–249. UI `/finance-reports/control`.

- [ ] **CTL-01 [Tulis uji]** Rekap platform dengan pencairan parsial → nominal diterima/pending/sisa tepat. Konfirmasi/rincian metadata bukan perintah mengirim uang atau menulis mutasi bank kedua.
- [ ] **CTL-02 [Tulis uji]** Tambah biaya dengan kategori dan referensi → posting dari satu jalur saja; mencoba kejadian yang sama dari jalur mutasi/rekon lain yang tertaut tidak membuat biaya ganda. Jangan mengharapkan deteksi semantik jika sengaja memakai referensi berbeda.
- [ ] **CTL-03 [Tulis uji]** Bukti privat dan persetujuan bila diaktifkan pada instance uji → role pengaju/pemeriksa terpisah; file tidak dapat diambil publik tanpa izin dan error storage tidak mengklaim upload sukses.
- [ ] **CTL-04 [Tulis uji]** Koreksi/VOID biaya/rincian yang didukung UI → mempertahankan audit, status dan dampak efektif sesuai. Pembatalan rincian konfirmasi tidak otomatis membatalkan uang di bank nyata.
- [ ] **CTL-05 [Lihat]** Proyeksi 7/30 hari memisahkan komitmen/perkiraan, pending platform, utang/piutang, payroll final belum dibayar dan rencana manual. Ubah tanggal proyeksi payroll hanya di instance uji: jadwal berubah tanpa menghitung ulang/membayar gaji.

## M. Alokasi transfer, realisasi rencana, dan CSV bank

Bukti: Batch 253–255. Tab Settlement/Proyeksi/Cocokkan Bank pada Kontrol Keuangan.

- [ ] **ALC-01 [Tulis uji]** Bagi satu transfer ke beberapa rekap → total alokasi tidak melebihi nominal asal, tidak membuat kas baru; ubah/lepas alokasi mengubah pembagian saja.
- [ ] **ALC-02 [Tulis uji]** Mutasi Rp100.000 dialokasikan Rp60.000 dan Rp40.000 ke dua rencana → sisa mutasi nol, proyeksi hanya sisa rencana; kelebihan ditolak. VOID sumber membuat realisasi tidak efektif tanpa saldo dicatat dua kali.
- [ ] **ALC-03 [Tulis uji]** CSV sintetis dengan tanggal/format Indonesia/Inggris yang dipilih eksplisit → preview nominal benar, perubahan mapping membatalkan konfirmasi, impor ulang tidak menggandakan baris; pencocokan tidak menciptakan kas atau otomatis menjurnal.

## N. Arus kas aktual, jurnal, dan laporan akuntansi

Bukti: Batch 258–259. UI `/finance-reports/accounting`; tab Panduan/Pengaturan Akun/Belum Dijurnal/Jurnal/Buku Besar/laporan.

- [ ] **GL-01 [Lihat]** Arus kas: saldo awal + IN − OUT = saldo akhir pada periode, rekening, dan mata uang yang sama, termasuk mutasi non-sales/non-purchase. Rekening nonaktif tidak menghapus histori; mata uang tidak dicampur tanpa kurs.
- [ ] **GL-02 [Tulis uji]** Pada **instance akuntansi kosong khusus uji**, masukkan saldo awal berimbang satu kali → retry tidak membuat dua saldo awal. Jangan membuat saldo awal kedua pada pembukuan usaha yang sudah berjalan.
- [ ] **GL-03 [Tulis uji]** Dari Belum Dijurnal, tinjau satu sumber → asisten hanya memberi saran setelah jenis/bukti dikonfirmasi; belum posting sampai disimpan. Debit tidak sama kredit ditolak; jurnal sah memakai sumber sekali tanpa memindahkan kas lagi.
- [ ] **GL-04 [Tulis uji]** Ubah baris saran atau pemetaan akun pada sesi uji → mode manual/stale dijelaskan; akun nonaktif/tidak valid ditolak. Menambah akun nonkas tidak membuat rekening bank/mutasi baru.
- [ ] **GL-05 [Lihat]** Jurnal yang sama muncul pada buku besar dan neraca saldo; laba-rugi, neraca dan perubahan ekuitas tersambung. Selisih kas GL/perantara transfer/antrean belum dijurnal harus ditelusuri, tidak dipaksa nol dengan jurnal penyeimbang tanpa bukti.
- [ ] **GL-06 [Tulis uji]** Koreksi melalui jurnal pembalik/prosedur sumber yang sesuai → riwayat asal tetap, efek reversal mengikuti periode dan sumber. Periode CLOSED dan sumber yang berubah tidak lolos hanya karena debit=kredit.
- [ ] **GL-07 [Lihat]** Panduan menjelaskan posting kas berbeda dari pengakuan penjualan/utang/HPP/payroll/penyusutan. **Belum ada auto-jurnal akrual lengkap semua modul atau tutup buku akuntansi final**; kelengkapan ini tetap backlog, bukan dianggap bug input pengguna.

## O. Presensi, PH, penalty, dan privasi pegawai

Bukti: A4.3, Batch 214/260. Membuka Absensi Saya pada jadwal PH dapat **langsung menulis absensi**, bukan pemeriksaan read-only.

- [ ] **HR-01 [Tulis uji]** Pegawai uji dengan shift PH dan jatah aktif membuka Absensi Saya → PH tercatat tanpa tombol/GPS dan tanpa pesan layanan validasi belum tersedia; refresh tidak mengurangi jatah dua kali.
- [ ] **HR-02 [Tulis uji]** Jatah habis/belum efektif/kedaluwarsa atau kontrak tidak aktif pada dataset uji → tidak diberikan PH sah; pesan alasan jelas, bukan tampilan sukses palsu.
- [ ] **HR-03 [Tulis uji]** Shift normal/OFF dan catatan hadir yang sudah ada → tidak ditimpa menjadi PH; check-in/out normal tetap sesuai aturan.
- [ ] **HR-04 [Tulis uji]** Terlambat/koreksi kehadiran/penalty sesuai kebijakan uji → perubahan masuk perhitungan yang tepat dengan audit, tidak mengubah periode final diam-diam.
- [ ] **HR-05 [Lihat]** Portal pegawai hanya menampilkan presensi/gaji/PH miliknya sesuai hak; administrator melihat cakupan yang memang diberikan.

## P. Payroll, uang makan, bonus, dan pembayaran

Bukti: A4.3, Batch 214; snapshot periode lama dipertahankan.

- [ ] **PAY-01 [Tulis uji]** Mode uang makan MONTHLY pada periode uji → hak masuk payroll/slip/net payment sesuai hari eligible; tidak menjadi tagihan Custom kedua.
- [ ] **PAY-02 [Tulis uji]** Mode CUSTOM → hak dicatat terpisah, tidak ikut pembayaran gaji; pencairan harian/mingguan/rentang lain mengikuti yang dipilih, sudah dibayar/sisa jelas.
- [ ] **PAY-03 [Tulis uji]** Preview → finalisasi → pembayaran payroll uji dan retry → bonus/potongan/penalty/kasbon sesuai rinciannya, kas keluar sekali; perubahan setting berikutnya tidak mengganti snapshot payroll lama.
- [ ] **PAY-04 [Lihat]** Bonus/audit bonus, slip dan laporan terkait dapat ditelusuri ke sumber; payroll PAID tidak lagi dihitung sebagai komitmen kas yang belum dibayar dan pembayaran bukan beban akuntansi kedua.

## Q. Aset dan permintaan perubahan

Bukti: A4.3 dan gelombang UI A3. Hak akses staf tetap mengikuti keputusan owner.

- [ ] **AST-01 [Tulis uji]** Staf dengan akses aset menambah/mengajukan perubahan aset uji → lokasi/penanggung jawab/status sesuai, tanpa akses tidak sah ke data pegawai lain.
- [ ] **AST-02 [Tulis uji]** Lock/change request/insiden/perbaikan/pensiun aset sesuai modul → transisi status, persetujuan dan audit terlihat; aksi terlarang ditolak server.
- [ ] **AST-03 [Lihat]** Rekonsiliasi aset dan label memakai identitas usaha serta histori yang benar; tidak menyatakan penyusutan otomatis masuk jurnal bila integrasinya belum dibuat.

## R. Roastery Label Studio

Bukti: Batch 192–193. UI `/roastery/packaging-labels`.

- [ ] **ROA-01 [Tulis uji]** Dropdown template di atas, default pertama, setiap template bernama; simpan sebagai template baru meminta nama dan tidak menimpa template yang tidak dipilih.
- [ ] **ROA-02 [Tulis uji]** Drag elemen, on/off, ukuran kertas, label per lembar dan lima tasting notes → opsi berlaku sama pada semua template dan lima notes tidak terpotong menjadi tiga.
- [ ] **ROA-03 [Perangkat]** Buka ulang desain, preview dan cetak ukuran nyata → posisi/ukuran/visibility sesuai; print scaling browser/printer tidak menyembunyikan perbedaan.

## S. Identitas customer, logo, upload, dan Menu Book

Bukti: Batch 219–225. UI `/system/business-profile` dan menu pengaturan terkait.

- [ ] **BRD-01 [Tulis uji]** Pada tenant uji, ganti nama/logo usaha → login, sidebar/footer dan dokumen terkait memakai identitas baru; override outlet/printer yang sengaja diatur tidak hilang.
- [ ] **BRD-02 [Tulis uji]** Upload gambar/logo pada tiap halaman yang digunakan → tersimpan, preview dan buka ulang sesuai; file tidak valid ditolak, tidak ada error folder yang disembunyikan atau kebutuhan chmod 777.
- [ ] **BRD-03 [Lihat]** Menu Book, QR/halaman publik, label/dokumen tidak membawa branding/data Namua ke customer baru; bahasa/timezone/mata uang/pajak/service mengikuti pengaturan yang memang didukung, bukan janji konversi kurs otomatis.

## T. Panduan aplikasi

Bukti: Batch 261. UI `/guide`.

- [ ] **GDE-01 [Lihat]** Operator dapat mencari panduan dari master/POS sampai laporan; langkah, tombol/tautan modul dan hasil yang diperiksa jelas di desktop/ponsel, tanpa harus membaca docs internal.
- [ ] **GDE-02 [Lihat]** Bab server hanya untuk role dengan izin server; contoh instalasi/cron/backup tidak menampilkan credential dan tombol salin tidak mengeksekusi perintah. Jangan mencoba cron mutasi/rebuild di server aktif sebagai tes membaca panduan.

## U. WhatsApp dan Telegram aplikasi

Bukti: Batch 34–52, 132–139. **Bridge coding internal bukan objek perubahan pada review ini.**

- [ ] **MSG-01 [Tulis uji]** Operator menggunakan setup/panduan/template/tujuan grup pada integrasi aplikasi yang diizinkan → pengaturan UI vs admin server jelas, secret tidak muncul pada log/preview.
- [ ] **MSG-02 [Tulis uji]** Dengan bot/nomor dan grup **khusus uji**, kirim satu pesan/laporan; status sukses/gagal dan retry jelas, tidak mengirim ke pelanggan nyata atau menduplikasi pengiriman.
- [ ] **MSG-03 [Admin/Control]** Engineer menguji scheduler claim/lease, retry dan service-auth di disposable; callback tanpa izin ditolak dan pesan grup tidak menjadi jalan pintas mutasi rekening. Jangan memanggil scheduler produksi berulang dari browser.

## V. APK POS — tercatat, tetapi acceptance ditunda owner

Bukti: Batch 73–81, 105–107, 176–178/186–190, 215/222–223. Source/build/perangkat APK tidak diperiksa ulang di batch review lintas modul ini.

- [ ] **APK-01 [Perangkat]** Saat owner melanjutkan: login memakai URL, Mobile API key dan kode perangkat pada kolom yang benar; terminal/outlet aktif cocok, pesan tidak menyebut nama folder development seperti finance2.
- [ ] **APK-02 [Perangkat]** Web dan APK berbagi sesi sesuai kontrak; offline order → server pulih → sinkron sekali, konflik terlihat dan tidak menggandakan order/pembayaran/stok/cetak. Aksi sensitif mengikuti kemampuan server, bukan diasumsikan boleh offline.
- [ ] **APK-03 [Perangkat]** Bundle, append item setelah save, logo/ukuran kertas/karakter lokal, voucher/rekening/reservasi/online order dan build Flutter diuji kembali pada APK aktual. **Belum ditandai selesai**, termasuk bug produksi yang ditunda.

## W. Paket bersih, instalasi, updater, lisensi, dan praktik jual

Bukti: A5.1–A5.16, C0–C5, Batch 217–245. Jalankan nanti di UI Control/instance disposable oleh owner dan admin, bukan dengan membersihkan Finance development.

- [ ] **REL-01 [Admin/Control]** Release dibentuk dari cutoff dan profil CUSTOMER_CLEAN yang benar → tidak membawa dump/transaksi/master usaha, foto produk/upload, log, secret atau bukti privat staging; hanya schema/reference/template yang disetujui.
- [ ] **REL-02 [Admin/Control]** Fresh install menghasilkan instance/database terpisah dan owner baru; brand/customer kosong/awal sesuai kontrak. Data Finance development dan aplikasi utama tetap utuh.
- [ ] **REL-03 [Admin/Control]** Upgrade, gagal migrasi dan rollback/restore pada disposable → checksum/dependency jelas, tidak ada replay merusak data customer; langkah cron, storage, runtime dan backup tercatat.
- [ ] **REL-04 [Admin/Control]** Customer → instance → paket/subscription → release → deployment/aktivasi di Control → identitas target, hak perpetual/maintenance dan batas outlet/terminal konsisten. Perangkat dihitung sebagai device, bukan sekadar jumlah role pengguna.
- [ ] **REL-05 [Admin/Control]** License cache/signature/offline/pemulihan diuji pada environment uji; **audit-only/signature valid bukan bukti enforcement penuh**. Jangan mengklaim native anti-clone/enforcement selesai jika masih backlog `_28`.
- [ ] **REL-06 [Admin/Control]** Paket aktual membawa semua perbaikan yang diterima dan panduan yang cocok versi; UAT owner, printer/APK, bukti update dan go/no-go terdokumentasi sebelum publish. Build/push/deploy tidak dijalankan otomatis oleh checklist.

## Satu skenario pembanding kas dan laporan

Hanya pada pembukuan uji, tanpa transaksi lain dan satu mata uang IDR. Tidak perlu memindahkan uang bank sungguhan. Catat nomor mutasi di setiap langkah.

| Langkah | Perubahan kas gabungan | Saldo gabungan yang diharapkan |
| --- | ---: | ---: |
| Awal uji | — | Rp1.000.000 |
| IN Pendapatan lain-lain | +Rp100.000 | Rp1.100.000 |
| OUT Biaya operasional | −Rp30.000 | Rp1.070.000 |
| IN Setoran modal pemilik | +Rp200.000 | Rp1.270.000 |
| Transfer Rp50.000 antarrekening sendiri | Rp0 | Rp1.270.000 |
| OUT Selisih kas kurang terverifikasi | −Rp10.000 | Rp1.260.000 |

Arus kas gabungan bertambah Rp260.000. Pada skenario sederhana ini pendapatan operasi Rp100.000 dan biaya Rp40.000 memberi selisih Rp60.000; setoran modal Rp200.000 bukan pendapatan usaha dan transfer internal bukan biaya. Jadi **kas harus bisa direkonsiliasi, tetapi bukan semua laporan wajib menampilkan angka laba sama dengan kenaikan kas**. Estimasi/manajemen dan laporan jurnal punya dasar berbeda. Jurnal/neraca baru diperiksa setelah sumber-sumber tersebut dijurnal ke akun yang tepat; jangan berharap antrean Belum Dijurnal otomatis menjadi laporan final.

## Hasil review kode dan batas bukti — Batch 265

### Temuan masih terbuka, jangan disamakan dengan kotak tes yang lulus

1. **PR-01 Tinggi, PR-02/03 Sedang** pada pengajuan divisi tetap terbuka: race edit-verifikasi, lookup material gagal yang terlewat, dan preview tanpa timeout. Reproduksi serta rencana fix pada [laporan modul](2026-09-16_konfirmasi_stok_pengajuan_divisi.md#hasil-review-ulang--temuan-masih-terbuka). Tidak diperbaiki diam-diam dalam permintaan review ini.
2. **Akuntansi masih fondasi/integrasi parsial**, bukan auto-jurnal akrual semua modul: pengakuan POS/purchase/HPP/payroll/penyusutan/utang/piutang, pemisahan arus kas campuran, multi-currency dan tutup buku/penerbitan masih ada pekerjaan. Ini backlog desain yang sudah dinyatakan, bukan temuan nominal baru dari data produksi.
3. **Paket customer belum sinkron dengan seluruh source terbaru**, diverifikasi read-only dari katalog/profil saat review: SQL `14c`, `15a`, `15b`, `15c`, `16a` belum ada di katalog dan allowlist SQL; `12a Roast Connect` ada di katalog tetapi belum allowlist SQL. Contoh kode yang belum ada di allowlist customer: `Finance_accounting_model`, `Procurement_stock_review`, controller `User_guide`, library `Finance_user_guide`. Registrasi harus berpasangan dengan dependency dan pengujian, bukan sekadar menyalin file. Tidak menyatakan artefak lama tiba-tiba sudah membawa perubahan.
4. **Bukti UAT belum lengkap:** perangkat/printer, browser visual, concurrency/DDL MariaDB, APK dan integrasi lintas modul dalam instance customer belum dibuktikan oleh unit/contract test. MFA dan bug operasional APK ditunda owner; mismatch data historis tetap keputusan owner.
5. **Status lama perlu dibaca dengan cutoff.** Istilah CODE_PASS/STAGING_PASS pada batch historis bukan status semua patch working tree saat ini. Temuan terbaru dan release acceptance tetap menghalangi klaim "semua selesai tanpa bug".

### Regresi terpilih yang dijalankan ulang

Semua di bawah adalah source/fixture/in-memory, **bukan** transaksi DB aplikasi. 22 suite lulus dengan jumlah **1.606 assertion agregat**; assertion antarsuite tidak dinyatakan unik atau bukti seluruh kemungkinan sudah diuji.

| Suite di `tools/tests/` | Hasil | Cakupan/batas |
| --- | ---: | --- |
| `finance_mutation_reporting_smoke.php` | 72 | Kategori, laporan, guard; SQLite |
| `finance_allocation_bank_smoke.php` | 392 | Alokasi/bank/rekon lintas sumber; SQLite, tanpa locking MariaDB |
| `finance_accounting_smoke.php` | 346 | Writer jurnal, akun, asisten, laporan; SQLite |
| `finance_accounting_client_smoke.cjs` | 38 | Event/payload DOM sintetis |
| `finance_control_workspace_contract_smoke.php` | 27 | Source/profil, bukan seluruh writer E2E |
| `finance_control_operations_contract_smoke.php` | 35 | Source/guard/storage policy |
| `attendance_auto_ph_smoke.php` | 58 | PH/portal; SQLite |
| `payroll_meal_mode_contract_smoke.php` | 7 | Kontrak MONTHLY/CUSTOM |
| `pos_reversal_no_stock_smoke.php` | 45 | Event tanpa resep dan campuran, fixture |
| `pos_reservation_refund_step_up_smoke.php` | 13 | Kontrak refund DP web |
| `auth_division_scope_smoke.php` | 42 | Scope auth, fake DB |
| `inventory_value_reconciliation_availability_smoke.php` | 23 | Invalidation sesudah koreksi |
| `pos_availability_item_change_smoke.php` | 9 | Invalidation item-centric |
| `pos_reversal_availability_smoke.php` | 20 | Invalidation reversal |
| `purchase_item_price_history_smoke.php` | 12 | Kontrak riwayat harga |
| `printer_general_logo_upload_smoke.php` | 10 | Source upload/preview, bukan cetak fisik |
| `activity_audit_smoke.php` | 12 | Kontrak audit aktivitas |
| `a3_stock_period_contract_smoke.php` | 9 | Kontrak filter/matrix |
| `a3_sidebar_information_architecture_smoke.php` | 18 | Kontrak struktur/sidebar |
| `roastery_label_template_studio_smoke.php` | 10 | Kontrak editor/template |
| `application_user_guide_smoke.php` | 398 | Controller/catalog/view/metadata fixture |
| `application_user_guide_client_smoke.cjs` | 10 | Salin/cetak DOM sintetis |

- PHP lint seluruh **62 file PHP application yang berubah/baru di working tree** lulus; bukan audit logika semua file aplikasi. Tidak mengubah file milik pekerjaan lain.
- Bukti 185 assertion pengajuan divisi + tiga defect probe berasal dari Batch 264; tidak dihitung ulang sebagai hasil baru 22 suite di atas. Defect probe masih berstatus temuan, bukan PASS.
- Tidak menjalankan full gate yang dapat memulai DB/browser/layanan/installer, tidak memakai config atau data transaksi aplikasi. Review ini tidak mengubah runtime, SQL, credential, server, Control, APK, bridge atau mengirim Telegram sendiri.
- SQL `16a` tetap USER_REPORTED_APPLIED (bukan konfirmasi ulang semua SQL lama). Register status SQL terkini tetap `_30`; tidak ada SQL baru pada checklist ini.

## Tindak lanjut

Engineer: PR-01 → PR-02 → PR-03 lebih dahulu, kemudian tutup gap otomatisasi jurnal dan acceptance paket secara terpisah sesuai prioritas owner. Pengguna dapat mulai tes lihat-data dan alur uji satu operator pada FIN/REC/CTL/GL/POS/HR; jangan menganggap seluruh modul sudah aman hanya karena checklist sudah tersedia. Tidak ada kotak manual yang dicentang oleh agent pada pembuatan dokumen ini.
