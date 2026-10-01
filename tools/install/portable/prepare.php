<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/LinuxPreparation.php';
try {
    $options=[];foreach(array_slice($argv,1)as$arg){if(!preg_match('/\A--([a-z-]+)=(.*)\z/D',$arg,$m)||isset($options[$m[1]]))throw new RuntimeException('PREPARATION_OPTION_INVALID');$options[$m[1]]=$m[2];}
    $result=(new LinuxPreparation(dirname(__DIR__,3),$options))->run();
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){
    $code=SetupService::code($e);
    $messages=[
        'LINUX_CLI_PREPARATION_REQUIRED'=>'Persiapan satu perintah ini khusus terminal Linux. Windows belum lulus pengujian nyata; jangan melewati pemeriksaan platform.',
        'RUN_PREPARATION_WITH_SUDO'=>'Jalankan perintah ini sebagai administrator server: sudo sh tools/install/portable/prepare.sh. Website tetap tidak boleh menjadi root.',
        'NEW_PORTABLE_PACKAGE_REQUIRED'=>'Buka folder Finance hasil ekstrak seluruh ZIP customer baru. Jangan menjalankan persiapan pada source development atau instalasi lain.',
        'CONTROL_ZIP_INCOMPLETE'=>'Ada berkas paket pengiriman yang belum lengkap. Ekstrak seluruh ZIP dari Control, bukan hanya TAR; jangan membuat token atau file izin sendiri.',
        'PREPARATION_CANCELLED'=>'Dibatalkan. Tidak ada persiapan baru yang diterapkan.',
        'SERVER_REQUIREMENTS_MISSING'=>'PHP CLI memerlukan versi 8.1 64-bit beserta pdo_mysql, mysqli, sodium, curl, mbstring, openssl, zip, xml, json, session dan dukungan POSIX. Tidak ada runtime global yang diinstal/diubah otomatis.',
        'INSTALLER_ACCOUNT_SEPARATION_REQUIRED'=>'Akun pendamping harus non-root, berbeda dari akun web, dan anggota grup web. Gunakan akun khusus baru yang disarankan atau akun yang sudah memenuhi syarat.',
        'WEB_ACCOUNT_REQUIRED'=>'Akun/grup PHP website belum cocok. Periksa akun PHP-FPM/Apache website ini lalu jalankan kembali dengan pilihan akun yang benar.',
        'WEB_RUNTIME_PERMISSION_FAILED'=>'Akun web belum mendapat akses baca kode/status dan tulis inbox yang sesuai, atau memiliki akses tulis kode/lisensi berlebihan. Periksa akun/grup website yang dipilih; jangan chmod 777.',
        'SCHEDULER_NOT_RUNNING'=>'Jadwal tersimpan tetapi belum terbukti berjalan. Periksa layanan cron dan izin akun pendamping pada server ini, lalu jalankan kembali perintah yang sama. Identitas/database tidak direset.',
        'SCHEDULER_UNAVAILABLE'=>'Layanan crontab belum tersedia. Admin perlu menyediakan scheduler server. Pemasang tidak mengubah layanan global otomatis.',
        'PREPARATION_IDENTITY_CHANGE_REJECTED'=>'Instalasi ini sudah memakai identitas akun lain. Jalankan ulang tanpa mengganti akun layanan; jangan menimpa instalasi dengan ZIP baru.',
    ];
    fwrite(STDERR,($messages[$code]??SetupUi::message($code))."\nKode pemeriksaan: $code\n");exit(1);
}
