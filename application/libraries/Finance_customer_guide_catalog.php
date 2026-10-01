<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Overrides ONLY delivery instructions for verified single-folder customer layout; no live configuration read.
$chapter=static function(string $id,string $title,string $summary,array $steps,string $check,string $warning,array $commands=[]):array{
    return ['id'=>$id,'category'=>'server','audiences'=>['server'],'title'=>$title,'summary'=>$summary,'steps'=>$steps,'check'=>$check,'warning'=>$warning,'links'=>[],'commands'=>$commands];
};
return [
 'server-install'=>$chapter('server-install','23 · Server: pemasangan paket satu folder','Gunakan ZIP lengkap dari penjual dan panduan customer_single_folder.md yang disertakan.',[
  'Ekstrak seluruh ZIP sehingga berkas pengiriman tetap berada di dalam satu folder finance. Jangan menyalin database, foto, atau konfigurasi usaha lain.',
  'Administrator mengarahkan website HTTPS ke finance/public. PHP CLI dan web harus memenuhi kontrak paket: PHP 8.1 serta MariaDB 10.11. Windows masih memerlukan pengujian host nyata.',
  'Dari folder finance, administrator menjalankan satu perintah persiapan di bawah. Periksa target sebelum menyetujui; website tidak mendapat akses root. Tidak perlu memasang cron lisensi secara terpisah.',
  'Buka alamat aplikasi diikuti /setup. Gunakan kode setup dari pengiriman, bukan kode akses unduhan dan bukan password admin.',
  'Isi alamat aplikasi, host/port/nama/user/password database kosong, lalu Uji koneksi. Isi akun admin pertama, periksa ringkasan dan klik Pasang dan aktifkan.',
  'Tunggu selesai lalu Masuk ke Finance. Jika koneksi terputus gunakan Lanjutkan / periksa status, jangan impor SQL atau menghapus journal. Paket dan kuota mengikuti izin penjual; customer tidak memilih lisensi ulang.'
 ],'Setup selesai, akun pertama dapat login, identitas usaha masih kosong dan data contoh usaha lain tidak terbawa.','Jangan gunakan clean installer untuk memperbarui aplikasi yang telah berisi data. Izin pemasangan baru tidak membatasi kapan customer mulai memasang; signature, pencabutan dan kuota tetap diperiksa.',[
  ['label'=>'Satu kali persiapan Linux, dari folder finance; administrator memeriksa dan menyetujui target','code'=>'sudo sh tools/install/portable/prepare.sh']
 ]),
 'server-config'=>$chapter('server-config','24 · Server: konfigurasi customer','Pengaturan customer ada di finance/config/customer.json; formulir setup mengisinya otomatis.',[
  'Gunakan UI /setup untuk instalasi pertama. Informasi database hanya diisi sekali dan digunakan aplikasi serta installer yang sama.',
  'Untuk pemeriksaan atau perubahan terencana, admin mengacu pada config/customer.example.json dan docs/customer_single_folder_admin.md. Jangan mengganti constants.php atau file inti application/config.',
  'Jika instalasi lama menggunakan environment/file eksternal, jangan menambahkan konfigurasi database kedua. Selesaikan konflik sumber konfigurasi terlebih dahulu agar target koneksi tidak berubah tanpa diketahui.',
  'Folder config, private, storage dan source tidak boleh menjadi root website. Gunakan public saja. Jangan memberi permission 777 atau membuka private agent kepada akun web.',
  'Jangan mengirim customer.json, password, kode setup atau kunci privat ke chat dukungan. Kirim kode kesalahan dan versi paket saja.'
 ],'CLI dan web memakai konfigurasi lokal yang sama; koneksi dan database tujuan sesuai ringkasan pemasangan.','Perubahan konfigurasi pada aplikasi aktif memerlukan backup dan pemeriksaan admin. Menghapus konfigurasi atau identitas tidak membuka mode tanpa lisensi.'),
 'server-cron'=>$chapter('server-cron','25 · Server: pekerjaan terjadwal','BUKAN laporan jadwal aktif. Persiapan satu-perintah memasang pendamping setup, sinkronisasi lisensi dan heartbeat sebagai tiga pekerjaan terpisah.',[
  'Jangan menambahkan cron lisensi/heartbeat kedua jika persiapan sudah berhasil. Administrator memeriksa bukti jadwal nyata serta status terakhir pendamping; tidak cukup melihat bahwa baris cron ada.',
  'Pekerjaan bisnis POS runtime_jobs_run tetap memerlukan jadwal operasional saat POS digunakan. Jalankan dengan akun layanan yang sesuai, path PHP serta folder finance yang benar; uji satu kali di lingkungan latihan sebelum menjadwalkannya.',
  'Telegram run_due dan WhatsApp api_schedule_run hanya dijadwalkan bila fitur serta integrasinya dipakai. WA engine adalah layanan tambahan; tidak dihidupkan otomatis oleh cron PHP.',
  'Untuk pekerjaan bisnis, administrator mengikuti docs/customer_single_folder_admin.md dan panduan modul, menyiapkan lock/log privat per instance. Konfigurasi inti terbaca dari config/customer.json, tidak perlu menyalin password ke cron.',
  'Script legacy yang menyebut path staging bukan template siap salin. Migrasi, seed, repair data dan quality gate bukan cron operasional rutin. Backup serta uji restore tetap menjadi kewajiban operasional.',
  'Periksa beberapa siklus: tidak ada job menumpuk, log terbaru berhasil dan penerimaan heartbeat/sinkronisasi benar. Printer agent tetap berjalan pada perangkat kasir.'
 ],'Pendamping terjadwal tanpa duplikasi; job bisnis yang diperlukan mempunyai pemilik serta bukti eksekusi.','Jangan menjalankan proses website sebagai root. Jangan memasang semua contoh job tanpa meninjau target dan kebutuhan modul.'),
 'server-backup-update'=>$chapter('server-backup-update','26 · Server: upgrade paket berbeda dari update aplikasi','Upgrade lisensi membuka fitur. Update aplikasi mengganti kode; keduanya tidak perlu mengulang aktivasi server.',[
  'Menu bergembok: lihat informasi upgrade, hubungi penjual. Setelah disetujui dan dokumen lisensi tersinkron, hak fitur berubah tanpa instal ulang; RBAC tetap berlaku.',
  'Untuk update kode alpha.20 atau paket lama, tunggu jalur update resmi yang disetujui Control. Kandidat ini baru menyediakan verifikasi dan rencana update read-only, belum pergantian kode aktif yang aman.',
  'Jangan menimpa seluruh folder, menjalankan /setup ulang, mengimpor baseline, mengganti identitas instalasi atau menghapus private/storage. Itu bukan cara update yang aman.',
  'Sebelum update resmi: backup database, upload, konfigurasi dan identitas; buktikan pemulihan pada server terisolasi. DDL tidak otomatis dapat dibatalkan dengan ROLLBACK.',
  'Bila update gagal atau transaksi sudah berjalan sesudahnya, pertahankan kedua keadaan dan bukti percobaan. Hentikan perubahan dan minta prosedur pemulihan penjual, jangan menghapus tabel atau mengulang SQL sendiri.'
 ],'Rencana update dan pemulihan disetujui, konfigurasi/data/identitas terjaga, tidak memakai slot aktivasi baru.','Paket ini belum menyatakan jalur update aplikasi aktif siap. Uji sintetis tidak sama dengan integrasi Control nyata.')
];
