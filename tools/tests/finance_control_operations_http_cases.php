<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function finance_control_operations_http_cases($db,$check,string $root,int $caseId): void
{
    $socket=$db->hostname;if(!preg_match('~\A/tmp/finance-mutation-test-[A-Za-z0-9]+/db.sock\z~D',$socket))throw new RuntimeException('Unexpected fixture socket');
    $dir=dirname($socket);$listen=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);if(!$listen)throw new RuntimeException('Fixture port unavailable');$address=stream_socket_get_name($listen,false);fclose($listen);
    $nonce=bin2hex(random_bytes(32));$server=proc_open([PHP_BINARY,'-d','display_errors=0','-d','upload_max_filesize=6M','-d','post_max_size=7M','-S',$address,$root.'/tools/tests/finance_control_operations_http_fixture.php'],[0=>['file','/dev/null','r'],1=>['file',$dir.'/http.log','a'],2=>['file',$dir.'/http.log','a']],$pipes,$root,['PATH'=>'/usr/bin:/bin','FINANCE_CONTROL_TEST_SOCKET'=>$socket,'FINANCE_CONTROL_TEST_NONCE'=>$nonce,'FINANCE_CONTROL_EVIDENCE_DIR'=>$dir.'/private-evidence']);
    if(!is_resource($server))throw new RuntimeException('Fixture web server unavailable');
    try{
        $curl=static function(string $path,$payload=null,array $extra=[])use($address,$nonce):array{
            $headers=['X-Fixture-Nonce'=>$nonce,'X-Finance-Control-CSRF'=>str_repeat('a',64)];foreach($extra as $k=>$v)$headers[$k]=$v;
            $ch=curl_init('http://'.$address.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>array_map(static fn($k,$v)=>$k.': '.$v,array_keys($headers),$headers)]);
            if($payload!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);}
            $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$size=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);return ['code'=>$code,'headers'=>substr((string)$raw,0,$size),'body'=>substr((string)$raw,$size),'json'=>json_decode(substr((string)$raw,$size),true)];
        };
        for($i=0;$i<30;$i++){usleep(100000);$r=$curl('/lookup/settlements');if($r['code']===200)break;}
        $check($r['code']===200 && !empty($r['json']['ok']),'real HTTP controller lookup: '.$r['body']);
        foreach(['policy'=>'finance.control.settings','approval-review'=>'finance.control.approve','receipt'=>'finance.control.index'] as $action=>$page){$r=$curl('/save/'.$action,'{}',['Content-Type'=>'application/json','X-Fixture-Denied-Page'=>$page]);$check($r['code']===403,'separate HTTP permission '.$page);}
        $r=$curl('/save/receipt',json_encode(['amount'=>[]]),['Content-Type'=>'application/json']);$check($r['code']===400,'controller rejects nested financial input');
        $fixturePdf=$dir.'/proof.pdf';file_put_contents($fixturePdf,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $r=$curl('/upload',['settlement_id'=>$caseId,'evidence'=>new CURLFile($fixturePdf,'application/pdf','transfer.pdf')]);
        $check($r['code']===200&&!empty($r['json']['ok']),'HTTP multipart upload to private storage: '.$r['body']);$evidenceId=(int)$r['json']['id'];
        $r=$curl('/download/'.$evidenceId);$check($r['code']===200 && str_starts_with($r['body'],'%PDF-1.4') && stripos($r['headers'],'Content-Disposition: attachment')!==false && stripos($r['headers'],'nosniff')!==false && stripos($r['headers'],'no-store')!==false,'authenticated attachment-only download headers/content');
        $r=$curl('/download/'.$evidenceId,null,['X-Fixture-Denied-Page'=>'finance.control.index']);$check($r['code']===403 && !str_contains($r['body'],'%PDF-1.4'),'unauthorized evidence download denied');
        $badFile=$dir.'/not-image.jpg';file_put_contents($badFile,'<?php echo "Not a photo";');
        $r=$curl('/upload',['settlement_id'=>$caseId,'evidence'=>new CURLFile($badFile,'image/jpeg','photo.jpg')]);$check($r['code']===422,'forged extension/MIME upload rejected');
        $r=$curl('/upload',['settlement_id'=>99999,'evidence'=>new CURLFile($fixturePdf,'application/pdf','transfer.pdf')]);$check($r['code']===422,'cannot upload for nonexistent settlement');
        $r=$curl('/upload',['settlement_id'=>$caseId,'evidence'=>new CURLFile($fixturePdf,'application/pdf','transfer.pdf')],['X-Finance-Control-CSRF'=>'']);$check($r['code']===403,'upload rejects missing scoped CSRF');
        $row=$db->get_where('fin_control_evidence',['id'=>$evidenceId])->row_array();$path=$dir.'/private-evidence/'.$row['storage_name'];
        $check(is_file($path) && (fileperms($path)&0777)===0600,'private evidence owner-only outside app');
        file_put_contents($path,'tampered synthetic evidence');$r=$curl('/download/'.$evidenceId);$check($r['code']===404 && !str_contains($r['body'],'tampered synthetic'),'tampered evidence hash rejected');
        // Artifacts deliberately retained with private fixture diagnostics.
    }finally{proc_terminate($server);proc_close($server);}
}
