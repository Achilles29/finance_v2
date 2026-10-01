<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Reviewed, customer-safe prose. This is not a reader for internal docs or live configuration.
$articles = [];
$link = static function (string $label, string $path, string $permission): array {
    return compact('label', 'path', 'permission');
};
$add = static function (string $id, string $category, array $audiences, string $title,
    string $summary, array $steps, string $check, string $warning = '', array $links = [], array $commands = []) use (&$articles): void {
    $articles[$id] = compact('id', 'category', 'audiences', 'title', 'summary', 'steps', 'check', 'warning', 'links', 'commands');
};

$add('welcome', 'start', ['owner','admin','cashier','stock','hr','finance','server'],
    '01 · Mulai: aplikasi ini dipakai untuk apa?',
    'Finance menghubungkan penjualan, pembelian, stok, produksi, pegawai dan pencatatan keuangan. Anda tidak perlu memahami semua modul sekaligus.', [
        'Pemilik usaha: mulai dari pembagian tugas, lalu setup awal, laporan kas dan checklist akhir bulan. Admin server menyiapkan mesin; admin aplikasi mengisi pengaturan lewat layar.',
        'Kasir: baca bab Kasir, Pesanan dan Printer. Gudang/produksi: baca Master Data, Pembelian, Stok dan Produksi. SDM: baca Presensi serta Payroll. Keuangan: ikuti bab Keuangan secara berurutan.',
        'Pilih tab topik di atas, kemudian bab. Gunakan pencarian seperti “logo”, “PH”, “selisih”, atau “cron”. Filter peran hanya membantu memilih bacaan, bukan memberi hak akses.',
        'Tombol Buka halaman hanya muncul jika Anda punya izin halaman tujuan. Jika menu tidak ada, minta admin memeriksa role dan lingkup divisi/outlet; jangan meminjam akun orang lain.',
        'Setiap bab berisi langkah, hasil yang harus dicek, dan peringatan. Nomor bab adalah urutan belajar, bukan tanda pekerjaan sudah selesai.'
    ], 'Anda tahu siapa yang menyiapkan server, siapa yang mengisi master, dan siapa yang menyetujui transaksi.',
    'Panduan ini menjelaskan kode yang tersedia, bukan pemeriksaan otomatis instalasi Anda. Fitur tetap mengikuti paket, izin dan kesiapan migrasi.');

$add('first-day', 'start', ['owner','admin','server'], '02 · Urutan menyiapkan usaha baru',
    'Pisahkan instalasi kosong untuk customer baru dari trial memakai salinan database usaha yang sudah berjalan.', [
        'Minta admin server menyerahkan URL HTTPS, akun pemilik pertama, versi paket, bukti health check, jadwal backup dan cara menghubungi support. Ganti password awal melalui pengaturan akun.',
        'Isi Profil Usaha terlebih dahulu, lalu outlet/terminal, rekening/metode pembayaran, pegawai, user, role dan lingkup akses.',
        'Masukkan satuan, item, produk, harga dan resep/formula sesuai usaha Anda. Isi stok dan saldo awal berdasarkan tanggal mulai serta bukti yang disetujui pemilik, bukan data demo.',
        'Atur printer dan metode pesanan yang benar-benar digunakan. Aktifkan integrasi opsional hanya setelah diuji.',
        'Latihan satu siklus di instance latihan: beli/terima stok → produksi bila perlu → jual → bayar → cetak → void/refund percobaan → tutup kasir → cocokkan kas/laporan.',
        'Sebelum mulai transaksi nyata, minta persetujuan pemilik, bukti backup dan restore yang berhasil, serta daftar keterbatasan yang masih diterima.'
    ], 'Identitas milik usaha sendiri; tidak ada produk, foto, saldo atau transaksi milik staging pada instalasi kosong.',
    'Jangan menjalankan seed clean-install pada database usaha berjalan. Trial data lama harus memakai database salinan terpisah.');

$add('roles', 'setup', ['owner','admin'], '03 · Akun, role dan izin',
    'Satu pegawai boleh memiliki beberapa role, misalnya STAFF untuk kebutuhan pribadi dan BARISTA untuk tugasnya.', [
        'Buka Users, buat akun yang terhubung ke pegawai yang benar. Berikan username unik dan akses hanya kepada pemilik akun.',
        'Buka Roles, pilih role lalu matriks izin. View untuk membaca; create/edit/delete/export diberikan sesuai tanggung jawab. Lingkup outlet/divisi tetap harus sesuai.',
        'Tambahkan role tugas tanpa menghapus role pribadi yang masih diperlukan. Uji menggunakan akun operator, bukan hanya SUPERADMIN.',
        'Agar pegawai membaca panduan ini, beri View pada “Panduan Aplikasi” (system.guide.index). Khusus pengelola server beri juga View pada “Panduan Admin Server” (system.guide.server).',
        'Setelah izin diperbarui, muat ulang atau login ulang. Bila menu belum terlihat, periksa izin halaman, status menu dan scope terlebih dahulu.'
    ], 'Operator bisa menjalankan tugasnya tetapi tidak otomatis dapat membuka pengaturan server atau modul keuangan.',
    'Pemberian izin panduan tidak memberi izin transaksi, pengaturan, atau membuka bab server kepada semua staf.', [
        $link('Users', 'users', 'auth.users.index'), $link('Roles', 'roles', 'auth.roles.index')]);

$add('identity', 'setup', ['owner','admin'], '04 · Nama usaha, logo dan identitas',
    'Pengaturan yang rutin diganti customer dilakukan melalui UI, bukan mengedit script.', [
        'Buka Sistem → Profil Usaha. Isi nama tampilan/nama legal, alamat, kontak dan identitas lain sesuai formulir.',
        'Pilih file logo; lihat preview, simpan, lalu buka ulang halaman untuk memastikan file tersimpan. Gunakan jenis dan ukuran file yang diizinkan formulir.',
        'Periksa halaman login, judul aplikasi, dokumen dan hasil cetak. Logo khusus outlet/printer dapat menimpa logo umum; atur juga Pengaturan Umum Printer jika diperlukan.',
        'Untuk katalog publik/Menu Book, pilih katalog milik usaha sendiri atau nonaktifkan sampai datanya siap.',
        'Jika muncul “folder logo belum dapat disiapkan”, berhenti mengulang upload dan minta admin mengecek storage sesuai bab Bantuan Upload.'
    ], 'Nama, kontak dan logo yang tampil/cetak adalah identitas customer, bukan usaha contoh.',
    'Mengganti timezone/mata uang tampilan tidak otomatis mengonversi transaksi historis atau menjadikan semua modul multi-mata-uang.', [
        $link('Profil Usaha', 'system/business-profile', 'system.business_profile'),
        $link('Logo cetak', 'pos/printers/general', 'pos.printer.general')]);

$add('terminal-payment', 'setup', ['admin','cashier','finance'], '05 · Outlet, perangkat kasir dan rekening',
    'Terminal adalah perangkat kasir yang terdaftar. Device key bukan Mobile API key.', [
        'Buka POS → Outlet + Terminal. Buat/pilih outlet aktif, kemudian terminal untuk perangkat yang digunakan; pilih OS sesuai perangkat.',
        'Di APK isi URL backend HTTPS milik usaha. Isi Kode Perangkat dengan Device Key terminal Android yang terdaftar, persis sama. Jangan menaruh Device Key di kolom Mobile API key.',
        'Mobile API key mengikuti konfigurasi deployment yang diberikan admin, bukan username atau kode perangkat. Jangan menebak nilainya atau mengirimkannya ke grup umum.',
        'Buka Metode Pembayaran. Hubungkan tunai, rekening bank, QRIS atau kanal online ke rekening yang benar sesuai formulir; cek biaya/potongan dan status aktif.',
        'Uji satu pembayaran kecil di lingkungan latihan dan telusuri mutasi rekeningnya. Jika outlet/terminal tidak cocok, benahi pemetaan sebelum dipakai kasir.'
    ], 'Order, sesi kasir dan pembayaran tercatat pada outlet/terminal/rekening yang dimaksud.',
    'Kompatibilitas APK harus mengikuti versi server yang diuji bersama. Panduan ini tidak menandakan semua bug produksi APK telah selesai.', [
        $link('Outlet + Terminal', 'pos/outlets-terminals', 'pos.outlet_terminal.index'),
        $link('Metode Pembayaran', 'pos/payment-methods', 'pos.payment_method.index')]);

$add('master-opening', 'setup', ['admin','stock','finance'], '06 · Master data dan saldo awal',
    'Master menjelaskan barang/orang/rekening. Saldo awal menjelaskan posisi nyata pada tanggal mulai.', [
        'Siapkan satuan dan konversinya, kategori, divisi/gudang, supplier, rekening, pegawai, item pembelian dan produk jual. Hindari membuat item yang sama berkali-kali dengan ejaan berbeda.',
        'Pisahkan item stok, bahan baku, component hasil produksi dan produk jual. Periksa hubungan antaritem, resep/formula, bundle dan extra sebelum transaksi pertama.',
        'Tetapkan tanggal mulai bersama pengelola keuangan. Hitung fisik dan biaya per unit yang didukung bukti, lalu isi melalui halaman saldo awal yang sesuai jenis stok.',
        'Masukkan posisi kas/bank dan saldo awal pembukuan secara terkoordinasi. Jangan menganggap pencatatan saldo kas sudah otomatis menjadi jurnal saldo awal lengkap.',
        'Periksa hasil impor dan total sebelum posting. Jika master sudah digunakan transaksi, gunakan jalur koreksi resmi; jangan hapus langsung melalui database.'
    ], 'Satuan, qty, biaya dan rekening konsisten; saldo awal punya tanggal serta bukti, bukan hasil menyalin data contoh.',
    'Mengubah resep sekarang tidak otomatis memperbaiki HPP/transaksi masa lalu. Jangan repair massal untuk membuat dashboard tampak bersih.');

$add('cashier', 'operations', ['cashier','admin'], '07 · Kasir: dari buka sesi sampai tutup',
    'Ikuti satu order yang sama sejak disimpan hingga dibayar; hindari membuat salinan karena respons lambat.', [
        'Buka POS → Kasir, pilih terminal/outlet yang benar. Buka sesi dan isi modal awal sesuai uang nyata. Jika sesi aktif yang sah masih ada, lanjutkan sesi tersebut sesuai hak akses.',
        'Pilih produk, jumlah, variasi/extra/bundle, meja atau pelanggan bila diperlukan. Periksa harga, diskon/voucher dan catatan sebelum Simpan Order.',
        'Jika menambah item setelah disimpan, buka kembali order yang sama, simpan perubahan, lalu muat ulang dan cek item sebelum melakukan pembayaran.',
        'Pilih metode pembayaran/rekening yang tepat, periksa nominal dan kembalian, kemudian bayar sekali. Pastikan status lunas, nomor invoice dan riwayat pembayaran muncul.',
        'Cetak struk dan tiket produksi sesuai tujuan printer. Respons jaringan lambat: cek status order dahulu sebelum menekan bayar/cetak kembali.',
        'Akhir giliran: tinjau transaksi belum selesai, refund dan penerimaan per metode. Hitung kas fisik, isi penutupan, jelaskan selisih dan pastikan penutupan tersimpan.'
    ], 'Order tersimpan/lunas sesuai keadaan sebenarnya; pembayaran tidak ganda dan kas akhir bisa ditelusuri.',
    'Jangan menutup sesi hanya untuk berpindah web/APK jika sesi yang sama masih bisa dipakai. Perpindahan dan offline tetap perlu diuji pada pasangan versi yang digunakan.', [
        $link('Kasir POS', 'pos/cashier', 'pos.cashier.index')]);

$add('orders', 'operations', ['cashier','admin'], '08 · Self order, online order, member dan reservasi',
    'Pesanan yang masuk belum selalu berarti sudah diterima, dibayar atau uangnya sudah cair ke bank.', [
        'Aktifkan Self Order atau Online Food melalui pengaturan UI masing-masing. Periksa katalog, jam layanan, pemetaan produk/extra, harga kanal dan metode pembayaran.',
        'Buka daftar pesanan masuk sesuai kanal. Cocokkan identitas pelanggan/member, item, catatan dan total sebelum menerima atau menolak.',
        'Untuk reservasi, pastikan tanggal, kapasitas/meja, pelanggan dan DP tercatat pada reservasi yang sama. Saat pesanan dilunasi, periksa penggunaan DP agar tidak menagih dua kali.',
        'Jika reservasi dibatalkan/ditolak dan sudah menerima DP, tinjau opsi refund yang tersedia serta rekening asal. Menolak reservasi tidak berarti uang otomatis boleh diabaikan.',
        'Untuk online food, bandingkan tagihan, promo/biaya platform dan jumlah cair. Gunakan rekonsiliasi/mutasi untuk selisih yang dibuktikan, bukan mengedit harga lama agar cocok.'
    ], 'Status order, DP/pelunasan/refund dan pencairan kanal dapat ditelusuri tanpa pemasukan ganda.',
    'Jangan membuat order kasir baru untuk pesanan online yang sudah masuk tanpa memastikan alur konversi/link pesanan yang tersedia.', [
        $link('Self Order', 'pos/self-order/settings', 'pos.self_order.index'),
        $link('Online Food', 'pos/online-food/settings', 'pos.online_food.index')]);

$add('refund', 'operations', ['cashier','admin','finance'], '09 · Salah transaksi: void dan refund',
    'Void membatalkan pencatatan yang diizinkan; refund mengembalikan pembayaran. Keduanya harus berjejak.', [
        'Temukan order/invoice yang benar, baca status, item, pembayaran dan apakah sudah pernah dibatalkan/dikembalikan.',
        'Gunakan aksi void/refund yang tersedia untuk status tersebut. Isi alasan dan konfirmasi/otorisasi pengelola jika diminta.',
        'Jika uang sudah diterima, periksa nominal, tujuan pengembalian dan bukti refund; membatalkan item saja bukan bukti uang sudah dikembalikan.',
        'Buka kembali order setelah berhasil, lalu cek riwayat pembayaran, mutasi kas dan pergerakan stok bila produk memang memakai stok.',
        'Produk event/jasa tanpa resep tidak memerlukan pengembalian stok yang tidak pernah dikomit. Jika tetap ditolak karena stok/resep, catat invoice, waktu dan pesan error untuk support.'
    ], 'Order/refund konsisten, saldo kas sesuai pengembalian nyata, dan reversal stok hanya untuk pemakaian stok yang memang tercatat.',
    'Jangan membuat transaksi negatif manual atau menghapus baris database untuk mengatasi void gagal.');

$add('purchasing', 'operations', ['stock','admin','finance'], '10 · Pembelian, Purchase Order dan Store Request',
    'PO adalah pesanan ke supplier; Store Request adalah permintaan kebutuhan internal. Status pembayaran dan penerimaan barang tidak sama.', [
        'Buat Store Request dari divisi peminta; pilih tujuan yang benar dan item/jumlahnya. Periksa saldo/satuan serta alasan kebutuhan sebelum mengirim untuk persetujuan.',
        'Pihak tujuan memeriksa lalu memenuhi permintaan atau membuat PO sesuai proses yang tersedia. Ikuti dokumen yang sama agar pengadaan tidak ganda.',
        'Buat/pilih Purchase Order, supplier, harga dan jumlah. Saat barang/tagihan diterima, cocokkan dengan dokumen pembelian dan prosedur penerimaan yang benar-benar dipakai usaha.',
        'Catat pembayaran ke rekening yang benar. Riwayat harga dapat memakai pembelian PAID; halaman Receipt Purchase bukan satu-satunya syarat harga terlihat.',
        'Periksa detail: status, qty penerimaan, pembayaran, histori harga dan mutasi stok. Jika memakai pembelian utang, bedakan pengakuan pembelian dengan pelunasannya di jurnal.'
    ], 'Dokumen SR/PO/pembelian terhubung dan stok serta pembayaran tidak digandakan.',
    'PAID tidak otomatis membuktikan barang diterima lengkap. Tinjau qty dan bukti; jangan mengubah status hanya untuk memaksa laporan muncul.', [
        $link('Store Request', 'store-requests', 'procurement.store_request.index'),
        $link('Purchase Order', 'purchase-orders', 'purchase.order.index')]);

$add('inventory', 'operations', ['stock','admin','finance'], '11 · Stok gudang, bahan baku, divisi dan component',
    'Bandingkan qty dengan qty dan nilai dengan nilai. Saldo stok dan lot bisa berbeda meskipun jumlahnya sama.', [
        'Buka stok sesuai jenisnya. Pilih divisi/gudang dan periode terlebih dahulu, kemudian baca kartu ringkasan serta tabel pada tab yang sama.',
        'Untuk daily matrix pilih bulan; untuk riwayat mutasi pilih periode yang relevan. Samakan tanggal dan scope sebelum membandingkan dua laporan.',
        'Klik rincian item/mutasi untuk menelusuri saldo awal, penerimaan, transfer, pemakaian, produksi, penjualan dan koreksi.',
        'Jika qty fisik salah, gunakan adjustment dengan bukti hitung fisik. Jika qty benar tetapi nilai lot/HPP salah, periksa koreksi nilai/HPP tanpa mengubah qty pada modul yang mendukungnya.',
        'Baca lot/FIFO/defisit: lot sisa adalah lapisan penerimaan yang masih tersisa; saldo minus/defisit perlu ditelusuri waktunya. Cek juga sinkronisasi HPP live setelah koreksi.',
        'Sesudah koreksi, buka ulang mutasi, lot dan ringkasan. Jika salah, gunakan riwayat/void koreksi yang tersedia sebelum membuat koreksi pengganti.'
    ], 'Qty, nilai stok, lot dan sumber mutasi bisa dijelaskan pada periode/scope yang sama.',
    'Mismatch adalah tanda untuk ditelusuri, bukan alasan menyamakan angka otomatis. Nilai negatif perlu bukti, bukan diubah menjadi nol tanpa persetujuan.', [
        $link('Stok Gudang', 'inventory/stock/warehouse', 'purchase.stock.warehouse.index'),
        $link('Stok Divisi', 'inventory/stock/division', 'purchase.stock.division.index'),
        $link('Stok Component', 'production/component-stock', 'production.component.stock.index')]);

$add('production', 'operations', ['stock','admin'], '12 · Produksi, resep, formula, bundle dan roastery',
    'Resep/formula menjelaskan pemakaian bahan; bundle menggabungkan produk; extra menambah pilihan pesanan.', [
        'Buat hubungan bahan/component dengan produk, satuan, takaran dan hasil produksi. Cocokkan konversi gram/ml/porsi serta yield sebelum menyimpan versi resep/formula.',
        'Atur isi bundle dan pemetaan extra secara lengkap. Pilihan aktif yang tidak punya mapping dapat membuat katalog atau pemakaian stok tidak sesuai.',
        'Saat produksi, pilih formula, divisi, qty hasil dan bahan yang benar. Tinjau stok, estimasi biaya serta hasil aktual sebelum posting batch.',
        'Setelah posting, cek pengurangan bahan, penambahan component/hasil, lot dan HPP. Pembaruan resep bukan pengganti koreksi batch lama.',
        'Pada roastery, cocokkan batch roasting, hasil dan packaging. Di Packaging Labels pilih template bernama, atur elemen/ukuran kertas, lalu cetak percobaan dan cocokkan dengan preview.'
    ], 'Bahan keluar dan hasil masuk sesuai batch; produk/bundle/extra terlihat serta dihitung sesuai pemetaan.',
    'Jangan memaksa semua produk mempunyai resep jika memang jasa/event tanpa stok. Klasifikasi harus sesuai proses bisnis.');

$add('attendance', 'operations', ['hr','admin'], '13 · Presensi dan PH',
    'Pegawai menggunakan halaman presensi pribadi; pengelola SDM menyiapkan jadwal, kontrak dan hak PH.', [
        'Admin SDM memeriksa pegawai terhubung dengan user yang benar, kontrak aktif, jadwal tanggal tersebut dan kuota PH yang masih berlaku.',
        'Pada jadwal PH yang valid, pegawai membuka halaman presensi pribadi. Pencatatan PH diproses otomatis jika syarat terpenuhi; tidak perlu absen GPS biasa untuk shift PH.',
        'Periksa status/riwayat presensi tanggal tersebut. Membuka ulang tidak seharusnya menggandakan penggunaan PH.',
        'Jika gagal, baca pesan, lalu minta admin memeriksa tanggal/jadwal, masa berlaku dan sisa hak PH serta catatan presensi yang sudah ada. Jadwal OFF/cuti mengikuti alurnya sendiri.',
        'Sebelum payroll, review koreksi kehadiran, PH, keterlambatan dan penalty. Simpan alasan koreksi sesuai izin, jangan mengubah lewat database.'
    ], 'PH valid tercatat sekali pada tanggal yang benar dan kuota tidak terpakai ganda.',
    'Halaman presensi dapat mencatat kehadiran otomatis. Jangan membuka akun pegawai untuk uji baca saja pada tanggal produksi.');

$add('payroll-assets', 'operations', ['hr','admin','finance'], '14 · Payroll, bonus dan aset',
    'Tinjau dasar perhitungan sebelum mencairkan gaji/bonus; pencatatan aset juga memiliki riwayat sendiri.', [
        'Tentukan periode payroll dan periksa pegawai aktif, komponen gaji, presensi/PH, penalty, kasbon dan bonus sesuai kebijakan.',
        'Periksa pengaturan uang makan: mode bulanan ikut payroll; mode custom merupakan pencatatan dan pembayarannya mengikuti jadwal custom (harian/mingguan/lainnya), bukan otomatis dibayar lagi lewat payroll.',
        'Tinjau rincian/audit bonus dan preview gaji, minta persetujuan, baru catat pencairan ke rekening yang benar. Pastikan pencairan tidak terulang.',
        'Untuk aset, catat identitas, lokasi/pemegang, serah-terima, transfer, kerusakan atau maintenance pada modul terkait; verifikasi persetujuan dan riwayat.',
        'Serahkan ringkasan pembayaran gaji/bonus dan perubahan aset kepada keuangan untuk ditinjau di jurnal sesuai bukti; jangan menganggap semuanya otomatis diposting.'
    ], 'Gaji dan uang makan tidak dibayar ganda; bonus/aset memiliki dasar serta jejak perubahan.',
    'Laporan operasional payroll/aset bukan bukti jurnal akrual dan penyusutan sudah lengkap.');

$add('printing', 'operations', ['cashier','admin'], '15 · Printer dan logo struk',
    'Logo dari UI, koneksi printer dari perangkat yang benar. Preview layar tetap harus dibuktikan dengan hasil cetak.', [
        'Buka POS → Printer → Umum. Pilih logo, cek preview, simpan lalu muat ulang. Tinjau identitas outlet dan override logo yang aktif.',
        'Atur printer tujuan kasir/produksi sesuai koneksi yang digunakan. Ikuti Panduan Printer untuk memasang agent pada komputer yang benar-benar tersambung printer.',
        'Di APK, ukuran kertas dan jumlah karakter printer diatur pada pengaturan printer APK, bukan dipaksa mengikuti nilai database web.',
        'Cetak percobaan teks dan logo. Jika logo menjadi blok hitam, cek gambar sumber dan proses cetak versi perangkat; logo berwarna/transparan perlu diuji hasil monokromnya.',
        'Jika belum tercetak, cek koneksi, printer aktif, ukuran kertas, agent/perangkat dan antrean. Pastikan job belum selesai sebelum mengulang agar tidak tercetak ganda.'
    ], 'Logo terbaca, teks tidak terpotong, dan tiket sampai ke printer yang tepat.',
    'Agent printer berjalan pada komputer kasir, bukan cron printer di server web. Uji printer fisik tetap diperlukan.', [
        $link('Pengaturan Umum Printer', 'pos/printers/general', 'pos.printer.general'),
        $link('Panduan Printer', 'pos/printers/guide', 'pos.printer.guide')]);

$add('cash-mutations', 'finance', ['finance','owner','admin'], '16 · Semua uang masuk dan keluar harus tercatat',
    'Tidak semua penerimaan berasal dari sales dan tidak semua pengeluaran berasal dari purchase.', [
        'Untuk sales/purchase/payroll yang sudah membuat mutasi kas, telusuri mutasi asalnya. Jangan mencatat IN/OUT kedua untuk uang yang sama.',
        'Untuk penerimaan/pengeluaran di luar dokumen tersebut, buka Mutasi Kas. Pilih rekening, tanggal, arah IN/OUT, kategori yang sesuai, nominal dan keterangan/bukti.',
        'Pemindahan uang antar rekening milik usaha gunakan transfer antar rekening, bukan membuatnya sebagai pendapatan dan biaya baru.',
        'Cocokkan dengan Posisi Kas pada periode yang sama: saldo awal ditambah masuk dikurangi keluar menghasilkan saldo akhir. Bandingkan juga dengan kas fisik/rekening koran.',
        'Sebelum laporan resmi, lanjutkan peninjauan jurnal. Setoran modal/pinjaman/DP masuk kas tetapi tidak otomatis merupakan pendapatan.'
    ], 'Setiap pergerakan uang punya satu sumber, rekening dan kategori; perubahan saldo dapat dijelaskan.',
    'Kas masuk tidak sama dengan laba, dan semua kas keluar tidak otomatis menjadi beban periode tersebut.', [
        $link('Mutasi Kas', 'finance/mutations', 'purchase.order.index'),
        $link('Posisi Kas', 'finance-reports/cash-position', 'finance.cash_position.index')]);

$add('reconciliation', 'finance', ['finance','owner'], '17 · Rekonsiliasi kas dan pendapatan',
    'Rekonsiliasi kas memeriksa posisi rekening; rekonsiliasi pendapatan mempersempit pemeriksaan pada penerimaan harian.', [
        'Pilih tanggal/periode dan rekening. Masukkan nominal nyata berdasarkan hitung fisik atau rekening koran, lalu bandingkan dengan sistem.',
        'Selisih belum diketahui: simpan catatan untuk ditelusuri; jangan memilih mutasi hanya supaya angka nol.',
        'Uang ternyata ada di rekening usaha lain: gunakan opsi transfer antar rekening dan pilih pasangan/rekening tujuan yang benar.',
        'Jika selisih merupakan penerimaan/pengeluaran nyata di luar sales/purchase, pilih mutasi IN/OUT beserta kategori seperti pola Mutasi Kas. Isi alasan dan bukti, tinjau sebelum posting.',
        'Contoh dana online food cair lebih kecil: periksa biaya/promo/selisih waktu pencairan dahulu. Catat sesuai penyebab, bukan otomatis menambah penjualan atau membebankan semua selisih.',
        'Sesudah posting, cek status ronde/baris, mutasi sumber/tujuan, saldo rekening dan laporan. Jangan posting ulang jika hasil pertama sudah tercatat.'
    ], 'Selisih memiliki penyelesaian atau alasan tertunda; transfer netral pada kas gabungan, IN/OUT berpengaruh sesuai bukti.',
    'Rekonsiliasi tidak membuat data fisik otomatis benar. Jangan gunakan koreksi untuk menyembunyikan transaksi yang belum masuk.', [
        $link('Rekonsiliasi Kas', 'finance-reports/cash-reconciliation', 'finance.cash_reconciliation.index'),
        $link('Rekonsiliasi Pendapatan', 'finance-reports/revenue-reconciliation', 'finance.revenue_reconciliation.index')]);

$add('journals', 'finance', ['finance','owner'], '18 · Jurnal untuk pengguna baru',
    'Jurnal mengelompokkan perubahan kas, aset, utang, modal, pendapatan dan beban. Posting jurnal dari mutasi tidak memindahkan uang lagi.', [
        'Buka Akuntansi dan Jurnal → Panduan. Pahami istilah akun (kelompok pembukuan, bukan username), debit/kredit (dua sisi pencatatan), posting dan saldo awal.',
        'Pengelola meninjau Pengaturan Akun serta pemetaan saran. Akun kas/perantara dilindungi; jangan membuat pemetaan seragam untuk biaya yang sebenarnya berbeda.',
        'Tentukan tanggal awal buku dan saldo awal lengkap berdasarkan bukti. Kas, persediaan, aset, piutang/utang dan ekuitas perlu ditinjau bersama pengelola akuntansi.',
        'Buka Belum Dijurnal, pilih mutasi lalu Tinjau & jurnal. Pilih jenis kejadian yang sesuai bukti; saran hanya membantu mengisi, bukan memposting otomatis.',
        'Periksa akun lawan, nominal, kelompok arus kas, tanggal dan apakah kejadian sudah diakui sebelumnya. Posting sekali, lalu periksa Buku Besar/Neraca Saldo.',
        'Untuk transaksi nonkas atau kondisi campuran, gunakan jurnal manual dengan bantuan pengelola. Jika salah, gunakan jurnal pembalik yang tersedia dan koreksi berjejak, bukan menghapus histori.'
    ], 'Jurnal seimbang debit/kredit, sumber tidak ganda dan akun dipilih sesuai kejadian.',
    'Integrasi jurnal otomatis seluruh sales/purchase/HPP/payroll/aset belum lengkap. Keseimbangan debit=kredit saja tidak membuktikan laporan lengkap atau benar.', [
        $link('Panduan Jurnal', 'finance-reports/accounting?tab=guide', 'finance.accounting.index'),
        $link('Akuntansi dan Jurnal', 'finance-reports/accounting', 'finance.accounting.index')]);

$add('month-end', 'finance', ['finance','owner'], '19 · Akhir bulan: laporan yang klop',
    'Estimasi untuk perkiraan; arus kas aktual untuk uang; laporan jurnal untuk pembukuan setelah kelengkapan ditinjau.', [
        'Samakan periode, rekening, mata uang dan scope. Cocokkan saldo awal + seluruh kas masuk − seluruh kas keluar = saldo akhir dengan rekening koran/fisik.',
        'Selesaikan atau jelaskan selisih rekonsiliasi, dana belum cair, refund dan transfer antar rekening. Periksa kedua sisi transfer, bukan hanya rekening asal.',
        'Review antrean Belum Dijurnal termasuk periode sebelumnya. Tinjau peringatan sumber berubah, kas GL berbeda dan saldo perantara transfer.',
        'Lengkapi transaksi nonkas yang belum tercatat otomatis, termasuk HPP/persediaan, piutang/utang, gaji terutang dan penyusutan, sesuai bukti dan kebijakan pengelola.',
        'Baca Neraca Saldo, Laba Rugi, Neraca dan Ekuitas. Telusuri perubahan terhadap arus kas; selisih laba dan kas bisa wajar tetapi harus dapat dijelaskan.',
        'Minta pengelola menyetujui kelengkapan, simpan bukti laporan serta backup sesuai kebijakan. Jangan menyebut laporan DRAFT sebagai laporan final hanya karena saldo cocok.'
    ], 'Arus kas cocok dengan kas/bank, jurnal lengkap untuk periode yang ditinjau dan perbedaan laba-versus-kas punya penjelasan.',
    'Estimasi Keuangan bukan pengganti laporan akuntansi final. Panduan tidak merupakan sertifikasi kepatuhan; penerbitan laporan tetap memerlukan peninjauan pengelola.', [
        $link('Akuntansi dan Jurnal', 'finance-reports/accounting', 'finance.accounting.index'),
        $link('Estimasi Keuangan', 'finance-reports/financial-estimation', 'finance.financial_estimation.index')]);

$add('integrations', 'help', ['admin','owner','cashier'], '20 · Telegram, WhatsApp dan lisensi',
    'Operator mengelola tujuan/jadwal lewat UI; admin server menyiapkan koneksi rahasia dan scheduler.', [
        'Telegram: buka Panduan Telegram. Buat bot melalui BotFather jika belum ada, lalu serahkan token melalui jalur privat kepada admin server. Jangan masukkan token ke catatan transaksi atau URL browser.',
        'Setelah admin mengaktifkan koneksi, ikuti Asisten Setup di Telegram: tautkan bot/tujuan, tambahkan bot ke grup yang dimaksud, uji satu pesan dan periksa log hasil.',
        'Atur laporan/tujuan/jadwal melalui UI. Bila tes manual berhasil tetapi jadwal tidak terkirim, minta admin memeriksa job Telegram serta konfigurasi CLI; jangan terus membuat jadwal duplikat.',
        'WhatsApp menggunakan engine dan sesi sendiri, bukan token bot Telegram. Ikuti panduan WhatsApp, hubungkan sesi/QR melalui operator berwenang, lalu uji tujuan dan jadwal.',
        'Lisensi & Aktivasi memperlihatkan status/fasilitas yang diketahui aplikasi. Aktivasi harus mengikuti instruksi paket/Control yang diterima admin; bukan menaruh license di database.php atau menganggap mode audit sudah membatasi paket secara penuh.'
    ], 'Pesan tes diterima tujuan yang benar; jadwal dan log diperiksa, status lisensi dicocokkan dengan paket yang dibeli.',
    'Jangan membagikan token, secret, password, kode aktivasi atau log mentah. Panduan integrasi lebih rinci tetap memerlukan izin modul masing-masing.', [
        $link('Panduan Telegram', 'telegram/guide', 'tg.guide'),
        $link('Panduan WhatsApp', 'wa/guide', 'wa.settings'),
        $link('Status Lisensi', 'system/license', 'system.license.index')]);

$add('troubleshooting', 'help', ['owner','admin','cashier','stock','hr','finance'], '21 · Bantuan: login, menu, upload dan transaksi',
    'Catat gejala secara spesifik agar pengelola bisa membantu tanpa mencoba-coba pada transaksi asli.', [
        'Login gagal: pastikan URL usaha, username dan password benar. APK juga memerlukan Device Key terminal Android dan Mobile API key sesuai konfigurasi. Jangan mengulang cepat berkali-kali karena pembatasan login.',
        'Menu hilang/403: minta admin memeriksa role, View dan scope. Panduan tidak menambah hak akses. Setelah perubahan izin/sidebar, login ulang bila perlu.',
        'Upload/logo gagal: periksa tipe/ukuran file pada formulir; coba simpan satu kali lalu muat ulang. Admin perlu memeriksa pemilik folder, batas upload PHP, fileinfo, open_basedir, ruang disk dan storage instance.',
        'Order tidak terlihat atau pembayaran meragukan: cari nomor order/invoice dan status terlebih dahulu. Jangan membuat pembayaran/order kedua sebelum hasil pertama dipastikan.',
        'Laporan berbeda: samakan periode/scope; pisahkan qty/nilai, estimasi/aktual, kas/jurnal dan belum cair/sudah diterima. Telusuri bukti sumber, bukan memperbaiki dashboard dengan menghapus data.',
        'Kirim ke support: URL halaman tanpa parameter rahasia, versi aplikasi, waktu kejadian, langkah, hasil yang diharapkan dan screenshot yang sudah disamarkan. Jangan kirim credential, data pribadi atau salinan database lewat grup.'
    ], 'Support menerima kasus yang dapat diulang dengan aman dan transaksi asli tidak digandakan.',
    'Jangan menggunakan chmod 777, menonaktifkan autentikasi, atau membagikan akun SUPERADMIN untuk mengatasi masalah.');

$add('handoff', 'help', ['owner','admin','server'], '22 · Checklist latihan dan serah-terima',
    'Gunakan instance latihan terpisah; checklist ini tidak menyatakan server Anda sudah lulus otomatis.', [
        'Admin server menyerahkan URL, versi/hash artifact, akun pemilik melalui jalur privat, lokasi backup, jadwal job dan bukti restore. Pemilik memastikan produk/foto/transaksi staging tidak terbawa pada instalasi kosong.',
        'Admin aplikasi mencoba identitas/logo, user multi-role, batas scope, outlet/terminal, rekening, produk/resep dan stok awal.',
        'Kasir mencoba simpan/tambah item, bundle/extra/voucher, bayar/cetak, void/refund produk stok dan event tanpa resep, reservasi/DP dan pesanan online yang dipakai.',
        'Gudang/SDM mencoba pembelian/SR/produksi, transfer/koreksi stok, presensi PH dan payroll/uang makan sesuai mode.',
        'Keuangan mencoba mutasi non-sales/non-purchase, dua sisi transfer, rekon IN/OUT, jurnal/pembalik, saldo kas dan laporan akhir bulan.',
        'Catat hasil nyata, penanggung jawab, kendala dan keputusan menerima/menunda. Latih minimal satu pengguna non-programmer tanpa diarahkan menebak langkah; revisi panduan bila tersendat.'
    ], 'Ada hasil latihan dan persetujuan orang yang bertanggung jawab, bukan hanya tanda centang dari pengembang.',
    'APK, printer fisik, integrasi eksternal dan migrasi setiap paket perlu bukti UAT tersendiri.');

$add('server-install', 'server', ['server'], '23 · Server: siapkan paket dan instalasi',
    'Dikerjakan admin server. Gunakan paket customer yang terverifikasi, bukan menyalin folder staging beserta data dan credential.', [
        'Sebelum terminal: siapkan domain HTTPS, user OS/pool PHP khusus, database baru, akses privat Control, ruang backup dan waktu pemeliharaan. Jangan menimpa database usaha yang berjalan.',
        'Cek kontrak runtime yang ikut paket melalui perintah pertama. Baseline kode ini PHP 8.1.x, MariaDB 10.11.x; Node 20 hanya untuk WA engine, Python 3.10 untuk printer agent. Cocokkan CLI/FPM dan extension; baseline teruji bukan klaim dukungan keamanan tanpa batas. Versi runtime lain perlu kualifikasi.',
        'Unduh toolkit resmi ke /opt/finance-toolkit (milik root, bukan writable oleh PHP). Perintah delivery/installer membutuhkan terminal admin root; worker operasional tidak. Dari Control, admin menerima delivery-job.json privat dan release-trust.json dari jalur tepercaya; kunci verifikasi jangan diambil begitu saja dari paket yang hendak diperiksa.',
        'Jalankan fetch dengan file job/trust privat. Lanjut hanya jika VERIFIED; itu membuktikan paket, belum membuktikan aplikasi terpasang.',
        'Siapkan deployment.json sesuai bab 24 dan file konfigurasi installer di direktori privat. Konfigurasi harus menyebut release_root, signed_manifest, trust_file, deployment_file, defaults_extra_file, database_name_file, owner_file, private_dir, runtime_dir, php_fpm, nginx, composer, mime_types, user, group, port, tls_certificate, tls_key dan mode clean_install. Gunakan nilai instance dari paket/handoff, bukan nilai staging.',
        'defaults_extra_file adalah file koneksi [client] untuk satu database; database_name_file hanya berisi nama database baru; owner_file JSON berisi username/email/password awal yang kuat. Direktori privat installer milik root 0700 dan file privatnya 0600, bukan file deployment runtime 0640 untuk pool. Jangan simpan di webroot. Bila tidak menerima konfigurasi installer lengkap, minta penyedia paket melengkapinya sebelum lanjut.',
        'Jalankan stage → install → start → health. Berhenti jika salah satu gagal. Hasil yang diharapkan STAGED → PREPARED → RUNNING → PASS. Install memang membuat schema/akun pada database tujuan; baca target sebelum konfirmasi.',
        'Pasang routing HTTPS publik/TLS ke instance yang benar. Profil Linux toolkit menguji loopback HTTPS, bukan otomatis membuat DNS/domain publik. Selesaikan receipt Control dengan identity/result privat dari eksekusi, lalu cek ACKNOWLEDGED/SUCCEEDED; jangan membuat result palsu.',
        'Login menggunakan akun pemilik, lanjut setup UI dan latihan. Health teknis bukan bukti seluruh proses bisnis sudah siap.'
    ], 'Artifact diverifikasi, target baru benar, health lulus, URL HTTPS bisa login dan receipt cocok dengan deployment di Control.',
    'Contoh path harus diganti sesuai instance. Halaman ini tidak menjalankan perintah, menginstal paket, mengubah database atau memasang service.', [], [
        ['label'=>'Baca matriks runtime paket (dari direktori source paket)', 'code'=>'php tools/release/runtime_compatibility_check.php --contract'],
        ['label'=>'Unduh dan verifikasi paket — hanya setelah file privat disiapkan', 'code'=>"php /opt/finance-toolkit/tools/install/control_delivery.php fetch --job-file=/var/lib/finance/customer-a/private/delivery-job.json --trust-file=/var/lib/finance/customer-a/private/release-trust.json"],
        ['label'=>'Tahapan installer — eksekusi satu per satu; install mengubah DB tujuan', 'code'=>"php /opt/finance-toolkit/tools/install/finance_instance.php stage --config=/var/lib/finance/customer-a/private/config.json\nphp /opt/finance-toolkit/tools/install/finance_instance.php install --config=/var/lib/finance/customer-a/private/config.json\nphp /opt/finance-toolkit/tools/install/finance_instance.php start --config=/var/lib/finance/customer-a/private/config.json\nphp /opt/finance-toolkit/tools/install/finance_instance.php health --config=/var/lib/finance/customer-a/private/config.json"]]);

$add('server-config', 'server', ['server'], '24 · Server: isi konfigurasi di mana?',
    'Contoh customer-a adalah nama instance contoh. Tidak ada secret asli di panduan ini; nilai rahasia Anda tidak dibaca atau ditampilkan.', [
        'Dengan editor server, buat /etc/finance/customer-a/deployment.json di luar webroot. Isi JSON contoh di bawah dengan nilai instance yang benar. Database user dibatasi pada database instance; jangan menggunakan root untuk aplikasi.',
        'Ganti GANTI_DENGAN_KUNCI_ACAK_PRIVAT dengan kunci acak kuat yang dibuat sekali dan disimpan privat. FINANCE_ENCRYPTION_KEY tidak boleh berubah setiap update. FINANCE_DB_PASSWORD adalah password database instance, bukan password login operator.',
        'File deployment dimiliki root dan group pool PHP, izin 0640; direktori induk tidak writable oleh group/publik dan dapat dilintasi pool. Jangan membuat file world-readable. Jika tidak tahu nama pool/group, minta admin hosting memastikannya dahulu.',
        'Siapkan direktori session, log dan cache terpisah pada /var/lib/finance/customer-a/, dimiliki user pool, izin privat (0700). Sesuaikan juga storage upload per instance; code tidak perlu dibuat writable seluruhnya.',
        'Buka file pool PHP-FPM yang melayani domain ini, tambahkan dua baris env contoh pada bagian pool tersebut. Bukan di terminal SSH saja, bukan di database.php. Lokasi file pool berbeda menurut hosting/aaPanel; pastikan mapping domain → versi PHP → nama pool.',
        'Uji konfigurasi dan reload hanya layanan/pool yang benar sesuai prosedur hosting. Jika open_basedir dipakai, izinkan path source, file privat, runtime dan temp yang diperlukan; jangan menonaktifkan pembatasan seluruh server.',
        'CLI/cron tidak mewarisi env PHP-FPM. Gunakan prefix CI_ENV dan FINANCE_DEPLOYMENT_FILE yang sama pada bab cron. Environment langsung mengungguli nilai JSON; cek konflik tanpa mencetak password/token.',
        'Contoh ini hanya konfigurasi inti. Secret WA/Telegram/licensing memakai kontrak integrasi sendiri; jangan berasumsi semua file/token boleh dimasukkan ke JSON inti. Admin menyiapkan secret melalui mekanisme privat deployment, operator melanjutkan UI.'
    ], 'FPM dan CLI mengarah ke instance/database/runtime yang sama, login berjalan, session tersimpan dan upload bekerja tanpa izin publik berlebihan.',
    'Jangan menyalin .user.ini, credential, database.php privat atau secret staging. Jangan menaruh password pada argumen shell, screenshot atau log.', [], [
        ['label'=>'Contoh isi deployment.json — semua nilai contoh harus diganti', 'code'=>"{\n  \"FINANCE_ENCRYPTION_KEY\": \"GANTI_DENGAN_KUNCI_ACAK_PRIVAT\",\n  \"FINANCE_DB_HOST\": \"127.0.0.1\",\n  \"FINANCE_DB_NAME\": \"db_customer_a\",\n  \"FINANCE_DB_USER\": \"finance_customer_a\",\n  \"FINANCE_DB_PASSWORD\": \"GANTI_DENGAN_PASSWORD_DB_PRIVAT\",\n  \"FINANCE_BASE_URL\": \"https://kasir.example.com/\",\n  \"FINANCE_SESSION_PATH\": \"/var/lib/finance/customer-a/sessions\",\n  \"FINANCE_SESSION_COOKIE\": \"finance_customer_a_session\",\n  \"FINANCE_LOG_PATH\": \"/var/lib/finance/customer-a/logs/\",\n  \"FINANCE_CACHE_PATH\": \"/var/lib/finance/customer-a/cache/\"\n}"],
        ['label'=>'Contoh pada bagian pool PHP-FPM customer-a', 'code'=>"env[CI_ENV] = production\nenv[FINANCE_DEPLOYMENT_FILE] = /etc/finance/customer-a/deployment.json"]]);

$add('server-cron', 'server', ['server'], '25 · Server: cron dan layanan berkala',
    'Daftar ini adalah rekomendasi pemasangan per instance, BUKAN status scheduler yang sedang aktif. Jangan memasang semua contoh sekaligus.', [
        'Wajib secara operasional bila POS dipakai: worker POS runtime_jobs_run memproses pekerjaan tertunda dan availability. Sarankan setiap menit. availability_queue_run hanya worker availability tambahan/alternatif untuk kebutuhan khusus; jangan otomatis menambah duplikatnya.',
        'Opsional Telegram: telegram run_due memeriksa jadwal sekaligus memproses antrean hingga 50. Sarankan setiap menit jika modul aktif dan secret tersedia pada proses CLI. process_queue bukan cron kedua yang wajib.',
        'Opsional WhatsApp: whatsapp api_schedule_run menjalankan laporan terjadwal. Sarankan setiap menit jika modul/engine aktif. Engine WA adalah service terpisah; cron PHP tidak menjalankan engine Node atau menghubungkan sesi QR.',
        'Admin menyiapkan folder logs/locks privat, path php dan flock yang benar, serta user layanan sesuai pool (contoh finance-a). Edit /etc/cron.d/finance-customer-a sebagai admin; format di bawah MEMAKAI kolom user. Jika memakai crontab -e milik user, hapus kolom user tersebut. File cron root-owned, tanpa credential di dalamnya.',
        'Ganti /opt/finance/customer-a/release dengan release_root yang aktif. Prefix deployment file harus sama dengan FPM. Jadwalkan hanya command yang sudah diuji satu kali pada instance latihan; setiap job mempunyai lock sendiri untuk mencegah overlap.',
        'Jika Telegram/WA perlu env integrasi tambahan, jalankan lewat wrapper privat yang disiapkan admin sebelum memasang jadwal; FINANCE_DEPLOYMENT_FILE inti saja belum membuktikan secret integrasi tersedia. Jangan taruh token langsung dalam crontab.',
        'Backup wajib menurut kebijakan operasional: pilih interval sesuai toleransi kehilangan data (misalnya 30 menit ATAU satu jam), storage privat, off-site dan uji restore. Script backup_full.sh lama membutuhkan scripts/backup/.env sendiri, tidak membaca deployment.json. Jangan menjadwalkannya sebelum jalur konfigurasi dan perlindungannya ditinjau.',
        'Lisensi: bila agen sudah diaktifkan, gunakan template tools/licensing/systemd/finance-license.service.example + timer.example, nama unit unik per instance. Template poll dijalankan service root untuk private state, bukan user POS. Interval template 5 menit dengan jitter; jangan pasang cron poll kedua. Kode/toolkit tetap root-owned dan bukan writable oleh web.',
        'Heartbeat Control lama scripts/control_center_heartbeat.php masih mengandung path staging; BUKAN template customer yang siap salin. Gunakan integrasi yang sesuai paket/Control. Health replikasi hanya bila replikasi memang dipasang; skrip legacy membutuhkan review jalur/credential sebelum digunakan.',
        'Printer agent hidup pada komputer kasir melalui autostart/service lokal. Migrasi, seed, repair stok, rebuild histori, promote replica dan seluruh quality gate bukan cron operasional rutin.',
        'Verifikasi 2–3 siklus: timestamp log terbaru, exit/status sukses, job tidak menumpuk dan pesan uji diterima tujuan. Siapkan rotasi log dan alarm kegagalan. Setelah upgrade, cek ulang path worker. Adanya baris cron bukan bukti job berhasil.'
    ], 'Hanya job yang diperlukan dipasang, tidak overlap, memakai konfigurasi instance yang benar dan mempunyai bukti hasil/log serta penanggung jawab.',
    'Contoh berikut bukan untuk copy-paste tanpa penyesuaian. Ini tidak memasang cron di server; Telegram/WA perlu konfigurasi integrasi tambahan sebelum digunakan.', [], [
        ['label'=>'Contoh /etc/cron.d/finance-customer-a — POS (path/user/folder wajib disiapkan)', 'code'=>"SHELL=/bin/sh\nPATH=/usr/local/bin:/usr/bin:/bin\n* * * * * finance-a CI_ENV=production FINANCE_DEPLOYMENT_FILE=/etc/finance/customer-a/deployment.json /usr/bin/flock -n /var/lib/finance/customer-a/locks/pos.lock /usr/bin/php /opt/finance/customer-a/release/index.php pos runtime_jobs_run 5 >> /var/lib/finance/customer-a/logs/pos-jobs.log 2>&1"],
        ['label'=>'Target CLI opsional — gunakan prefix/wrapper, lock dan log per job seperti POS; bukan dua job wajib', 'code'=>"php /opt/finance/customer-a/release/index.php telegram run_due\nphp /opt/finance/customer-a/release/index.php whatsapp api_schedule_run"]]);

$add('server-backup-update', 'server', ['server'], '26 · Server: backup, update dan pemulihan',
    'Backup yang belum pernah dipulihkan belum membuktikan usaha dapat pulih. Lindungi database, file upload dan konfigurasi privat.', [
        'Tetapkan jadwal backup, retensi, penanggung jawab dan lokasi salinan terenkripsi di luar server. Dump database saja tidak mencakup foto/logo/upload, kunci enkripsi dan konfigurasi instance.',
        'Periksa keberhasilan, ukuran/checksum dan salinan off-site. Restore percobaan ke database/instance baru yang terisolasi; jangan restore percobaan menimpa data produksi.',
        'Sebelum update: baca changelog/migrasi paket dan buat backup terverifikasi. Freeze transaksi dan hentikan hanya instance yang dituju; jangan menghentikan layanan seluruh customer.',
        'Sediakan release_root baru serta salinan DB/runtime. Installer mode upgrade membutuhkan backup_file, backup_sha256, previous_config_file dan from_schema sesuai bukti sebelumnya. Pertahankan kunci enkripsi yang benar. Jangan menjalankan seluruh sql/*.sql atau seed clean-install untuk upgrade.',
        'Verifikasi paket bertanda tangan, jalankan tahapan executor dan health pada salinan, lalu uji login, identitas, POS, mutasi, jurnal, upload dan worker sebelum mengarahkan domain ke versi baru.',
        'Jika gagal sebelum transaksi baru, kembali ke instance lama yang masih disimpan sesuai runbook. Jika sudah ada transaksi baru, jangan langsung menimpa database: tahan transaksi, simpan kedua keadaan dan minta keputusan rekonsiliasi pemilik/support.',
        'Catat waktu, versi sumber/target, migrasi, hasil health/UAT, lokasi backup dan keputusan go/no-go. Update receipt/monitoring Control sesuai alur paket; tidak cukup menekan git pull.'
    ], 'Ada bukti restore, titik pemulihan, persetujuan perubahan dan hasil uji; transaksi customer serta upload tidak hilang.',
    'Jangan menghapus backup, log, upload atau data lama untuk membuat upgrade terlihat berhasil. Pembersihan/retensi perlu target dan persetujuan terpisah.');

return $articles;
