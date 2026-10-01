<?php
declare(strict_types=1);
ini_set('display_errors','0');
require_once __DIR__.'/SetupUi.php';
$root=str_replace('\\','/',dirname(__DIR__,3));
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
$nonce=base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$nonce'; style-src 'nonce-$nonce'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if(SetupUi::serverChecks()['server']!=='OK')throw new RuntimeException('SERVER_REQUIREMENTS_MISSING');
        CustomerPlatform::path($root,$root);
        if(!in_array($_SERVER['HTTPS']??'',['on','1'],true))throw new RuntimeException('HTTPS_REQUIRED');
        if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json'||(int)($_SERVER['CONTENT_LENGTH']??0)>16384)throw new RuntimeException('SETUP_REQUEST_INVALID');
        $raw=file_get_contents('php://input',false,null,0,16385);
        if(strlen($raw)>16384)throw new RuntimeException('SETUP_REQUEST_INVALID');
        $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($input))throw new RuntimeException('SETUP_REQUEST_INVALID');
        $secret=(string)($input['secret']??'');
        if(($input['action']??'')==='status')$out=SetupUi::status($root,$secret,(string)($input['id']??''));
        elseif(in_array($input['action']??'',['probe','install'],true))$out=SetupUi::enqueue($root,$input);
        else throw new RuntimeException('SETUP_REQUEST_INVALID');
        foreach(['command','progress']as$part)if(!empty($out[$part]['code']))$out[$part]['message']=SetupUi::message($out[$part]['code']);
        echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){$code=preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'SETUP_NOT_READY';http_response_code(400);echo json_encode(['code'=>$code,'message'=>SetupUi::message($code)],JSON_UNESCAPED_UNICODE);}
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);exit;}
header('Content-Type: text/html; charset=utf-8');
$checks=SetupUi::publicChecks($root);$closed=is_file($root.'/storage/setup/closed.json');
$h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pasang Finance</title>
<style nonce="<?=$h($nonce)?>"><?=file_get_contents(__DIR__.'/setup.css')?></style></head>
<body><main><header><span class="brand" aria-hidden="true">F</span><div><span class="eyebrow">MULAI USAHA ANDA</span><h1>Selamat datang di Finance.</h1><p>Kami bantu dari pengaturan pertama hingga siap login.</p></div></header>
<?php if($closed):?><section class="panel done"><span class="success-mark" aria-hidden="true">✓</span><h2>Pemasangan sudah selesai</h2><p>Halaman pemasangan telah dikunci. Pengaturan dan data Anda tetap tersimpan.</p><a class="primary button" href="/login">Masuk ke Finance →</a></section>
<?php else:?><nav class="steps" aria-label="Tahap pemasangan"><span class="active" data-step="access">1. Persiapan</span><span data-step="settings">2. Pengaturan</span><span data-step="review">3. Periksa & pasang</span><span data-step="progress">4. Selesai</span></nav>
<section class="checks" aria-label="Kesiapan pemasangan">
<?php foreach(['server'=>'Kebutuhan server','package'=>'Kelengkapan paket','preparation'=>'Persiapan server','service'=>'Layanan pemasangan']as$key=>$label):?>
<div class="check" data-check="<?=$h($key)?>"><span class="check-dot" aria-hidden="true">•</span><div><strong><?=$h($label)?></strong><span class="check-text"><?=$checks[$key]==='OK'?'Memenuhi syarat':($checks[$key]==='NEEDS_ADMIN'?'Perlu persiapan admin':($checks[$key]==='INCOMPLETE'?'Paket belum lengkap':'Dicek setelah kode setup'))?></span></div></div>
<?php endforeach;?></section>
<div id="notice" role="status" aria-live="polite" class="notice" hidden></div>
<section class="panel" id="access"><span class="eyebrow">LANGKAH PERTAMA</span><h2>Buka pemasangan Anda</h2><p>Masukkan <strong>kode setup</strong> dari berkas <code>KODE-SETUP.txt</code> di dalam ZIP yang Anda unduh.</p>
<form id="access-form"><label for="setup-code">Kode setup</label><div class="password-field"><input id="setup-code" type="password" required minlength="32" maxlength="256" autocomplete="off"><button type="button" data-toggle="setup-code" aria-label="Tampilkan kode setup" aria-pressed="false">Lihat</button></div><button class="primary" type="submit">Periksa & lanjutkan →</button></form>
<details class="explain"><summary>Kode yang mana? Apa bedanya dengan password?</summary><ul><li><strong>Kode akses pengiriman</strong> digunakan untuk membuka halaman unduh di Control.</li><li><strong>Kode setup</strong> digunakan di halaman ini, tersedia dalam ZIP pada <code>private/delivery/KODE-SETUP.txt</code>.</li><li><strong>Password admin</strong> adalah password login Finance yang Anda buat pada langkah berikut.</li></ul></details>
<?php if($checks['preparation']==='NEEDS_ADMIN'||$checks['server']!=='OK'||$checks['package']==='INCOMPLETE'):?>
<aside class="admin-note"><h3>Admin server perlu menyiapkan satu kali</h3><p>Ekstrak <strong>seluruh ZIP</strong> ke folder Finance. Admin membuka terminal di folder itu dan menjalankan satu perintah:</p><code class="command">sudo sh tools/install/portable/prepare.sh</code><p>Perintah ini memeriksa server, izin paket, dan layanan pemasangan. Anda tidak perlu membuat ulang berkas yang sudah ada di ZIP. Setelah selesai, muat ulang halaman ini.</p><p class="muted">Website harus diarahkan ke folder <code>finance/public</code>. Jangan jalankan website sebagai root/Administrator.</p></aside>
<?php endif;?></section>
<form id="settings" class="panel" hidden autocomplete="off"><span class="eyebrow">PENGATURAN ANDA</span><h2>Hubungkan aplikasi & database</h2><p>Paket dan lisensi sudah ditentukan penjual. Anda cukup mengisi informasi server dan akun pertama.</p>
<fieldset><legend>Alamat aplikasi</legend><label for="base-url">Alamat HTTPS Finance</label><input id="base-url" name="base_url" type="url" placeholder="https://kasir.usahaanda.com/" required><small>Nilai awal mengikuti alamat yang sedang Anda buka. Periksa kembali; jangan tambahkan /setup.</small></fieldset>
<fieldset><legend>Database kosong</legend><div class="grid"><label>Host database<input name="host" value="127.0.0.1" required autocomplete="off"></label><label>Port<input name="port" type="number" value="3306" min="1" max="65535" required></label><label>Nama database<input name="database" pattern="[A-Za-z0-9_]{1,64}" required></label><label>Username database<input name="db_user" required autocomplete="off"></label><div class="full"><label for="db-password">Password database</label><div class="password-field"><input id="db-password" name="db_password" type="password" required autocomplete="new-password"><button type="button" data-toggle="db-password" aria-label="Tampilkan password database" aria-pressed="false">Lihat</button></div></div></div>
<details class="explain"><summary>Belum punya database?</summary><p>Buka panel hosting atau database manager. Buat satu database kosong, buat user database, lalu berikan user tersebut akses ke database itu. Catat nama, user, password, host dan port. Tidak perlu memberikan password root database kepada Finance.</p><p>Pemasang tidak menghapus database lama dan tidak mengimpor semua folder SQL. Jangan pilih database milik aplikasi yang sedang berjalan.</p></details>
<button type="button" id="probe" class="secondary">Uji koneksi & database kosong</button><p id="probe-result" aria-live="polite" class="muted">Belum diuji. Pengujian ini tidak membuat atau menghapus tabel.</p></fieldset>
<fieldset><legend>Admin pertama</legend><div class="grid"><label>Username admin<input name="username" pattern="[A-Za-z][A-Za-z0-9._-]{2,59}" required autocomplete="username"></label><label>Email (opsional)<input name="email" type="email" autocomplete="email"></label><div class="full"><label for="admin-password">Password admin Finance</label><div class="password-field"><input id="admin-password" name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"><button type="button" data-toggle="admin-password" aria-label="Tampilkan password admin" aria-pressed="false">Lihat</button></div><small>Minimal 12 karakter, gabungkan huruf besar/kecil, angka atau simbol. Jangan memuat username. Ini berbeda dari password database.</small></div></div></fieldset>
<div class="actions"><button type="button" data-go="access" class="quiet">← Kembali</button><button type="submit" class="primary" id="review-button" disabled>Periksa ringkasan →</button></div></form>
<section id="review" class="panel" hidden><span class="eyebrow">SEBELUM MEMULAI</span><h2>Periksa sekali lagi</h2><dl id="summary"></dl><p class="notice good">Koneksi berhasil dan database kosong. Pemasang akan memeriksa ulang sebelum membuat tabel.</p><p>Aktivasi mengikuti paket dari penjual. Tidak ada database lain yang dibersihkan. Password tidak ditampilkan dalam ringkasan atau dikirim ke Control.</p><label class="consent"><input id="confirm" type="checkbox">Saya sudah memeriksa alamat dan database di atas, dan ingin memasang Finance di database kosong ini.</label><div class="actions"><button type="button" data-go="settings" class="quiet">← Ubah pengaturan</button><button type="button" id="install" class="primary" disabled>Pasang dan aktifkan →</button></div></section>
<section id="progress" class="panel" hidden><span class="eyebrow">KAMI SEDANG MENYIAPKAN</span><h2 id="progress-heading">Pemasangan berlangsung</h2><progress id="bar" max="100" value="0" aria-label="Kemajuan pemasangan"></progress><ol class="phases"><li data-phase="QUEUED">Pengaturan diterima</li><li data-phase="ACTIVATING">Aktivasi dan kuota server</li><li data-phase="DATABASE">Database dan akun admin</li><li data-phase="WEB_CHECK">Pemeriksaan halaman login</li><li data-phase="REPORTING">Konfirmasi hasil ke Control</li><li data-phase="COMPLETE">Selesai</li></ol><p id="progress-message" role="status" aria-live="polite"></p><div class="actions"><button type="button" id="resume" class="secondary">Lanjutkan / periksa status</button><button type="button" id="edit-input" class="quiet" hidden>Perbaiki pengaturan</button><a id="login" class="primary button" href="/login" hidden>Masuk ke Finance →</a></div></section>
<script nonce="<?=$h($nonce)?>"><?=file_get_contents(__DIR__.'/setup.js')?></script>
<?php endif;?><footer>Finance · Data tetap milik Anda · Persiapan server satu kali</footer></main></body></html>
