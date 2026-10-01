<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Private, download-only evidence. Not part of customer release assets. */
class Finance_control_evidence
{
    public const MAX_BYTES=5242880;
    public static function directory(bool $prepare=false): string
    {
        $web=realpath(FCPATH);
        if ($web===false) throw new RuntimeException('Root aplikasi tidak valid.');
        $configured=getenv('FINANCE_CONTROL_EVIDENCE_DIR');
        $path=rtrim($configured!==false && $configured!==''?$configured:'/var/lib/finance-control/'.substr(hash('sha256',$web),0,16).'/evidence','/');
        if ($path==='' || $path[0]!=='/' || strpos($path,"\0")!==false || preg_match('~(?:^|/)\.{1,2}(?:/|$)~',$path)) throw new RuntimeException('Lokasi bukti privat tidak valid.');
        $walk='';foreach(explode('/',trim($path,'/')) as $segment){$walk.='/'.$segment;if(@is_link($walk))throw new RuntimeException('Lokasi bukti tidak boleh berupa symbolic link.');}
        if ($path===$web || strpos($path,$web.'/')===0) throw new RuntimeException('Bukti wajib disimpan di luar folder web publik.');
        if ($prepare && !is_dir($path) && !@mkdir($path,0750,true) && !is_dir($path)) throw new RuntimeException('Folder bukti privat belum siap. Admin: siapkan FINANCE_CONTROL_EVIDENCE_DIR di luar webroot dengan pemilik pengguna PHP-FPM.');
        $real=realpath($path);
        if ($real===false || $real===$web || strpos($real,$web.'/')===0 || ($prepare && !is_writable($real))) throw new RuntimeException('Folder bukti privat belum tersedia/dapat ditulis. Hubungi admin server; jangan gunakan chmod 777.');
        return $real;
    }
    public static function inspect_upload(array $file): array
    {
        if ((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name']??''))) throw new RuntimeException('Unggahan tidak lengkap. Pilih PDF/JPG/PNG maksimal 5 MB.');
        $path=$file['tmp_name'];$size=filesize($path);
        if ($size===false || $size<1 || $size>self::MAX_BYTES) throw new RuntimeException('Bukti maksimal 5 MB dan tidak boleh kosong.');
        // Some supported PHP-FPM installations omit Fileinfo. Do not trust client MIME/extensions.
        $dimensions=@getimagesize($path);$mime=$dimensions['mime']??'';
        if (in_array($mime,['image/png','image/jpeg'],true)) {
            if ($dimensions[0]*$dimensions[1]>24000000) throw new RuntimeException('Gambar terlalu besar (maksimal 24 megapiksel).');
        } else {
            $handle=fopen($path,'rb');if(!$handle)throw new RuntimeException('Bukti tidak dapat dibaca.');
            try { $prefix=fread($handle,16);fseek($handle,max(0,$size-4096));$tail=stream_get_contents($handle); } finally { fclose($handle); }
            if (!preg_match('/\A%PDF-[12]\.\d[\r\n ]/',$prefix) || !preg_match('/%%EOF\s*\z/D',$tail)) throw new RuntimeException('Bukti hanya PDF, JPG, atau PNG yang valid. SVG/HTML/program tidak diizinkan.');
            $mime='application/pdf';
        }
        if (class_exists('finfo') && (new finfo(FILEINFO_MIME_TYPE))->file($path)!==$mime) throw new RuntimeException('Isi berkas tidak sesuai jenis bukti.');
        $original=basename((string)($file['name']??''));$extension=strtolower(pathinfo($original,PATHINFO_EXTENSION));
        $allowed=['application/pdf'=>['pdf'],'image/png'=>['png'],'image/jpeg'=>['jpg','jpeg']];
        if (!mb_check_encoding($original,'UTF-8') || !in_array($extension,$allowed[$mime],true)) throw new RuntimeException('Nama/ekstensi file harus sesuai isinya: PDF, JPG, atau PNG.');
        $stem=preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u','_',pathinfo($original,PATHINFO_FILENAME));
        $name=mb_substr($stem,0,150).'.'.$extension;
        return ['original_name'=>$name ?: 'bukti','mime_type'=>$mime,'byte_size'=>$size,'sha256'=>hash_file('sha256',$path)];
    }
    public static function download_path(array $row): string
    {
        $name=(string)($row['storage_name']??'');
        if (!preg_match('/\A[a-f0-9]{64}\z/D',$name)) throw new RuntimeException('Bukti tidak valid.');
        $path=self::directory().'/'.$name;
        if (is_link($path) || !is_file($path) || !is_readable($path) || filesize($path)!==(int)$row['byte_size'] || !hash_equals($row['sha256'],hash_file('sha256',$path))) throw new RuntimeException('Berkas bukti hilang/berubah. Hubungi administrator.');
        return $path;
    }
}
