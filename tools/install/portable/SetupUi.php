<?php
declare(strict_types=1);
require_once __DIR__.'/PortableStore.php';

final class SetupUi
{
    /** Browser state is written by the verified installer, never by the browser. */
    public static function permissionExpired(array $b,?int $now=null): bool
    {
        if(($b['permission_policy']??'FIXED_EXPIRY')==='UNTIL_USED_OR_REVOKED') {
            return !in_array($b['profile_version']??null,[8,9,10,11],true) || !array_key_exists('expires_at',$b) || $b['expires_at']!==null;
        }
        return ($b['permission_policy']??'FIXED_EXPIRY')!=='FIXED_EXPIRY' || !is_int($b['expires_at']??null) || $b['expires_at']<=($now??time());
    }

    public static function message(string $code): string
    {
        if(in_array($code,['PACKAGE_MANIFEST_MISMATCH','PACKAGE_PROFILE_MISMATCH','ARTIFACT_HASH_INVALID','PACKAGE_EXTRA_FILE','RELEASE_GATE_MISSING','PORTABLE_PROFILE_REQUIRED'],true))
            return 'Isi atau bukti paket tidak cocok. Minta penjual memeriksa ZIP pengiriman yang lengkap. Jangan menimpa instalasi yang sudah berjalan atau menghapus database.';
        return [
            'SERVER_REQUIREMENTS_MISSING'=>'Kebutuhan server belum lengkap. Minta admin menjalankan pemeriksaan pemasang.',
            'SETUP_PERMISSION_EXPIRED'=>'Izin sementara sudah kedaluwarsa, bukan hak pembelian Anda. Minta izin pengganti dari Control; jangan mengulang database.',
            'SETUP_REQUEST_UNAUTHORIZED'=>'Kode pemasangan tidak cocok. Gunakan kode dari tautan pengiriman Control, bukan password akun Anda.',
            'SETUP_ALREADY_COMPLETE'=>'Pemasangan selesai. Halaman setup sudah dikunci.',
            'DATABASE_CONNECTION_FAILED'=>'Database belum dapat dihubungi. Periksa host dan port, serta pastikan layanan database berjalan. Pengaturan dapat diperbaiki lalu diuji lagi.',
            'DATABASE_CREDENTIAL_REJECTED'=>'Username atau password database ditolak. Periksa informasi dari panel/database manager, lalu klik Uji koneksi lagi. Jangan gunakan password admin Finance.',
            'DATABASE_ACCESS_DENIED'=>'Akun belum mendapat izin, atau database belum dibuat. Pastikan database tersedia dan beri user akses melalui panel/database manager, lalu uji lagi.',
            'DATABASE_NOT_FOUND'=>'Database belum ada. Buat database kosong melalui panel/database manager, beri akses kepada user database, lalu uji lagi. Tidak perlu password root database.',
            'DATABASE_VERSION_UNSUPPORTED'=>'Versi database belum sesuai. Paket ini memerlukan MariaDB 10.11. Minta admin menyediakan database yang sesuai tanpa mengganti database aplikasi lain.',
            'DATABASE_PROBE_REQUIRED'=>'Uji koneksi dan database kosong terlebih dahulu. Jika Anda mengubah pengaturan atau menunggu lebih dari 15 menit, lakukan pengujian ulang.',
            'OWNER_INVALID'=>'Username admin harus diawali huruf, 3–60 karakter. Email boleh kosong; jika diisi, gunakan alamat email yang benar.',
            'OWNER_PASSWORD_WEAK'=>'Password admin minimal 12 karakter, gabungkan huruf besar/kecil, angka atau simbol. Jangan memasukkan username di dalam password.',
            'CUSTOMER_CONFIG_HTTPS_ROOT_URL_REQUIRED'=>'Alamat aplikasi harus HTTPS, misalnya https://kasir.usahaanda.com/; jangan menambahkan /setup atau nama subfolder.',
            'CUSTOMER_CONFIG_INCOMPLETE'=>'Pengaturan belum lengkap. Isi host, port, nama/user/password database dan alamat aplikasi.',
            'CUSTOMER_CONFIG_EXPLICIT_SOCKET_OR_TCP_REQUIRED'=>'Untuk database pada server yang sama, isi host 127.0.0.1, bukan localhost, agar port TCP digunakan dengan jelas. Untuk server lain gunakan hostname/IP dari admin database.',
            'INITIALIZATION_REVIEW_REQUIRED'=>'Persiapan sebelumnya terputus saat menyimpan identitas. Admin perlu memeriksa berkas identitas yang sudah ada; jangan menghapus atau membuat identitas baru.',
            'CUSTOMER_CONFIG_SOURCE_CONFLICT'=>'Ada pengaturan lama server yang berbeda dari isian Anda. Admin perlu memeriksa pengaturan lama tersebut; aplikasi tidak memilih database lain secara diam-diam.',
            'SETUP_CONFIRM_REQUIRED'=>'Periksa ringkasan, lalu centang persetujuan pemasangan.',
            'SERVICE_UNAVAILABLE'=>'Layanan pemasangan belum terdeteksi berjalan. Minta admin menjalankan kembali satu perintah persiapan, lalu klik Periksa lagi. Jangan kirim password melalui chat.',
            'INSTALL_PARENT_UNSAFE'=>'Folder induk dapat diubah akun lain. Pilih folder instalasi di bawah direktori yang dilindungi admin; pemasang tidak mengubah izin folder induk atau website lain.',
            'PORTABLE_PERMISSION_UNSAFE'=>'Izin folder pemasangan belum sesuai. Jalankan kembali perintah persiapan pada folder Finance ini, bukan chmod 777.',
            'PORTABLE_PATH_UNSAFE'=>'Berkas belum lengkap atau jalur folder tidak aman. Admin perlu memeriksa hasil ekstrak dan menjalankan persiapan dari folder Finance yang benar; jangan menggunakan tautan folder atau chmod 777.',
            'WEB_INSTALLER_ACCOUNT_SEPARATION_REQUIRED'=>'Akun website belum dipisahkan dari pemilik paket. Admin perlu menjalankan persiapan satu kali dengan akun web non-root dan akun pendamping terpisah.',
            'DELIVERY_PREPARE_REQUIRED'=>'Berkas izin sudah berubah. Admin perlu menjalankan kembali perintah persiapan; data dan identitas instalasi tetap dipertahankan.',
            'ACTIVATION_REPLACEMENT_REQUIRED'=>'Izin aktivasi sebelumnya sudah ditolak. Minta penjual memperbarui izin di paket ini; jangan menghapus data atau mencoba kode lama berulang kali.',
            'DATABASE_NOT_EMPTY'=>'Database sudah berisi. Buat database kosong untuk instalasi baru. Jangan hapus data lama.',
            'DATABASE_PARTIAL_REVIEW_REQUIRED'=>'Proses SQL sebelumnya terputus. Bukti disimpan; admin harus memeriksa langkah terakhir sebelum melanjutkan. Tidak ada SQL yang diulang otomatis.',
            'INSTANCE_LIMIT_EXCEEDED'=>'Jumlah server aktif sudah mencapai kuota. Periksa slot instalasi di Control. Server lain tetap aktif.',
            'ACTIVATION_CREDENTIAL_REJECTED'=>'Kode aktivasi kedaluwarsa atau tidak berlaku. Minta pengganti melalui Control; identitas instalasi tetap dipertahankan.',
            'WEB_HEALTH_FAILED'=>'Database selesai, tetapi halaman login HTTPS belum lolos pemeriksaan. Periksa URL, sertifikat HTTPS, dan root website public/.',
            'CONTROL_ACK_REQUIRED'=>'Laporan hasil belum diterima Control. Data pemasangan tetap tersimpan; proses pendamping akan mencoba pengiriman ulang.',
            'PACKAGE_CORE_MODIFIED'=>'Isi paket berbeda dari paket terverifikasi. Jangan lanjutkan sebelum administrator memeriksa berkas.',
            'WAITING_FOR_CUSTOMER'=>'Siap menerima pengaturan Anda.',
            'SETUP_REQUEST_EXISTS'=>'Pengaturan sudah dikirim. Tunggu proses pendamping; jangan kirim ulang.',
        ][$code]??'Langkah ini belum berhasil. Simpan kode pemeriksaan di bawah dan minta administrator memeriksanya. Jangan hapus folder atau database.';
    }
    public static function authorize(string $root,string $secret): array
    {
        if(strlen($secret)<32||strlen($secret)>256)throw new RuntimeException('SETUP_REQUEST_UNAUTHORIZED');
        $b=CustomerPlatform::document($root,$root.'/storage/setup/browser.json');
        if(!hash_equals($b['secret_sha256']??'',hash('sha256',$secret)))throw new RuntimeException('SETUP_REQUEST_UNAUTHORIZED');
        return $b;
    }
    public static function request(string $root,array $input): array
    {
        $b=self::authorize($root,(string)($input['secret']??''));
        if(is_file($root.'/storage/setup/closed.json'))throw new RuntimeException('SETUP_ALREADY_COMPLETE');
        if(self::permissionExpired($b))throw new RuntimeException('SETUP_PERMISSION_EXPIRED');
        if(!is_array($input['config']??null)||!is_array($input['owner']??null))throw new RuntimeException('SETUP_REQUEST_INVALID');
        $key=base64_decode($b['public_key']??'',true);if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('SETUP_REQUEST_INVALID');
        $packet=['sealed'=>base64_encode(sodium_crypto_box_seal(json_encode(['permit_id'=>$b['permit_id'],'secret'=>$input['secret'],
            'config'=>$input['config'],'owner'=>$input['owner']],JSON_THROW_ON_ERROR),$key))];
        $path=$root.'/storage/inbox/request.json';
        // The only web-writable installer location contains sealed credentials, never executable code.
        if(is_link(dirname($path))||str_replace('\\','/',(string)realpath(dirname($path)))!==dirname($path))throw new RuntimeException('INBOX_UNSAFE');
        $mask=umask(0007);try{$h=@fopen($path,'xb');}finally{umask($mask);}
        if(!$h)throw new RuntimeException('SETUP_REQUEST_EXISTS');
        try{$bytes=json_encode($packet,JSON_THROW_ON_ERROR);if(fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h))throw new RuntimeException('SETUP_REQUEST_WRITE_FAILED');}
        finally{fclose($h);}
        return ['phase'=>'QUEUED','percent'=>15,'code'=>''];
    }

    public static function serverChecks(): array
    {
        $runtime=PHP_VERSION_ID>=80100&&PHP_VERSION_ID<80200&&PHP_INT_SIZE===8;
        foreach(['pdo_mysql','mysqli','sodium','curl','mbstring','json','openssl','zip','xml','session']as$ext)$runtime=$runtime&&extension_loaded($ext);
        return ['server'=>$runtime?'OK':'NEEDS_ADMIN'];
    }
    /** Safe, coarse information only. Private delivery and account/path details are never read by GET. */
    public static function publicChecks(string $root): array
    {
        return self::serverChecks()+['package'=>is_file($root.'/RELEASE-MANIFEST.json')&&is_file($root.'/installer/layout.json')?'CHECK_AFTER_ACCESS':'INCOMPLETE',
            'preparation'=>is_readable($root.'/storage/setup/browser.json')?'CHECK_AFTER_ACCESS':'NEEDS_ADMIN','service'=>'CHECK_AFTER_ACCESS'];
    }
    public static function status(string $root,string $secret,string $id=''): array
    {
        $b=self::authorize($root,$secret);
        $checks=self::serverChecks();$checks['package']='VERIFIED';$checks['preparation']='OK';
        $workerFile=$root.'/storage/setup/worker.json';
        $worker=is_file($workerFile)?CustomerPlatform::document($root,$workerFile):[];
        if(!empty($worker['code']))$checks['package']='NEEDS_REVIEW';
        $progress=CustomerPlatform::document($root,$root.'/storage/setup/status.json');
        $fresh=max((int)($worker['at']??0),($worker['state']??'')==='RUNNING'?(int)strtotime($progress['updated_at']??''):0);
        $checks['service']=$fresh>=time()-150?'OK':'UNAVAILABLE';
        $closed=is_file($root.'/storage/setup/closed.json');
        $expired=self::permissionExpired($b);
        $out=['checks'=>$checks,'closed'=>$closed,'permission_expired'=>$expired,'progress'=>$progress,
            'can_edit'=>empty($progress['database_started'])&&!is_file($root.'/storage/customer-installation.json')&&!in_array($progress['phase']??'', ['DATABASE','WEB_CHECK','REPORTING','COMPLETE'],true)];
        if($id!=='') {
            if(!preg_match('/\A[a-f0-9]{32}\z/D',$id))throw new RuntimeException('SETUP_REQUEST_INVALID');
            $path=$root.'/storage/setup/result-'.$id.'.json';
            $out['command']=is_file($path)?CustomerPlatform::document($root,$path):['id'=>$id,'phase'=>'QUEUED'];
        }
        if(!$closed&&$expired)$out['message']=self::message('SETUP_PERMISSION_EXPIRED');
        elseif(!empty($worker['code']))$out['message']=self::message($worker['code']);
        elseif($checks['service']!=='OK')$out['message']=self::message('SERVICE_UNAVAILABLE');
        return $out;
    }
    public static function enqueue(string $root,array $input): array
    {
        $secret=(string)($input['secret']??'');$b=self::authorize($root,$secret);
        $s=self::status($root,$secret);
        if($s['closed'])throw new RuntimeException('SETUP_ALREADY_COMPLETE');
        if($s['permission_expired'])throw new RuntimeException('SETUP_PERMISSION_EXPIRED');
        if($s['checks']['service']!=='OK')throw new RuntimeException('SERVICE_UNAVAILABLE');
        if($s['checks']['package']!=='VERIFIED')throw new RuntimeException('PACKAGE_CORE_MODIFIED');
        $id=$input['id']??'';$kind=$input['action']??'';
        if(!is_string($id)||!preg_match('/\A[a-f0-9]{32}\z/D',$id)||!in_array($kind,['probe','install'],true)||!is_array($input['config']??null))throw new RuntimeException('SETUP_REQUEST_INVALID');
        if(is_file($root.'/storage/setup/result-'.$id.'.json')||is_file($root.'/storage/inbox/command-'.$id.'.json'))return ['id'=>$id,'phase'=>'QUEUED'];
        if(count(glob($root.'/storage/inbox/command-*.json')?:[])>=8)throw new RuntimeException('SETUP_REQUEST_EXISTS');
        $command=['id'=>$id,'kind'=>$kind,'permit_id'=>$b['permit_id'],'secret'=>$secret,'config'=>$input['config']];
        if($kind==='install')$command+=['owner'=>$input['owner']??[], 'probe_id'=>$input['probe_id']??'', 'confirmed'=>$input['confirmed']??false, 'replace_input'=>$input['replace_input']??false];
        $key=base64_decode($b['public_key'],true);
        if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('SETUP_REQUEST_INVALID');
        $bytes=json_encode(['sealed'=>base64_encode(sodium_crypto_box_seal(json_encode($command,JSON_THROW_ON_ERROR),$key))],JSON_THROW_ON_ERROR);
        if(strlen($bytes)>32768)throw new RuntimeException('SETUP_REQUEST_INVALID');
        $dir=$root.'/storage/inbox';
        if(is_link($dir)||str_replace('\\','/',(string)realpath($dir))!==$dir)throw new RuntimeException('INBOX_UNSAFE');
        $mask=umask(0007);$h=false;$temp=$dir.'/.pending-'.bin2hex(random_bytes(16));
        try {
            $h=@fopen($temp,'xb');
            if(!$h)throw new RuntimeException('SETUP_REQUEST_EXISTS');
            if(fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h))throw new RuntimeException('SETUP_REQUEST_WRITE_FAILED');
            fclose($h);$h=false;
            $target=$dir.'/command-'.$id.'.json';
            if(!file_exists($target)&&!is_link($target)&&!rename($temp,$target))throw new RuntimeException('SETUP_REQUEST_WRITE_FAILED');
        }finally{if(is_resource($h))fclose($h);if(is_file($temp))unlink($temp);umask($mask);}
        return ['id'=>$id,'phase'=>'QUEUED'];
    }
}
